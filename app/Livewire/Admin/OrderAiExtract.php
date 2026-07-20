<?php

namespace App\Livewire\Admin;

use App\Enums\OrderSource;
use App\Exceptions\ClaudeExtractionException;
use App\Models\AiOrderExtraction;
use App\Models\Customer;
use App\Models\delivery_areas;
use App\Models\Device;
use App\Models\Order;
use App\Models\Order_Item;
use App\Models\products;
use App\Notifications\NewOrderPushNotification;
use App\Services\OrderExtractionServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class OrderAiExtract extends Component
{
    use WithFileUploads;

    public string $step = 'input';

    public string $pastedText = '';
    public array $uploadedImages = [];
    public string $source = '';

    public ?int $extractionId = null;
    public array $customer = [
        'name' => null,
        'phone' => null,
        'alternative_phone' => null,
        'address' => null,
        'district' => null,
        'area' => null,
    ];
    public ?array $existingCustomer = null;
    public array $lines = [];
    public array $orderMeta = [
        'payment_method' => 'cod',
    ];
    public ?float $confidence = null;
    public array $warnings = [];
    public ?int $deliveryAreaId = null;
    public float $subtotal = 0;
    public float $deliveryCharge = 0;
    public float $discount = 0;
    public float $total = 0;
    public ?float $amountToCollect = null;
    public string $totalSource = 'calculated';

    public array $sourceOptions = [];

    public function mount()
    {
        $this->sourceOptions = collect(OrderSource::cases())
            ->map(fn ($case) => ['value' => $case->value, 'label' => $case->label()])
            ->toArray();
    }

    public function removeImage(int $index)
    {
        unset($this->uploadedImages[$index]);
        $this->uploadedImages = array_values($this->uploadedImages);
    }

    public function updatedUploadedImages()
    {
        if (count($this->uploadedImages) > 10) {
            $this->uploadedImages = array_slice($this->uploadedImages, 0, 10);
            $this->addError('uploadedImages', 'You can upload a maximum of 10 images.');
        }
    }

    public function runExtraction(OrderExtractionServiceInterface $service)
    {
        $this->validate([
            'source' => 'required|string',
            'uploadedImages.*' => 'nullable|image|max:5120',
        ]);

        $hasImages = !empty($this->uploadedImages);
        $hasText = trim($this->pastedText) !== '';

        if (!$hasImages && !$hasText) {
            $this->addError('pastedText', 'Upload at least one image or add some order details.');
            return;
        }

        try {
            if ($hasImages) {
                $storedPaths = [];
                $images = [];

                foreach ($this->uploadedImages as $uploadedImage) {
                    $storedPath = $uploadedImage->store('ai-extractions', 'local');
                    $storedPaths[] = $storedPath;
                    $images[] = [
                        'path' => Storage::disk('local')->path($storedPath),
                        'media_type' => $uploadedImage->getMimeType(),
                    ];
                }

                $extracted = $service->extractFromImages($images, $this->pastedText);

                $extraction = AiOrderExtraction::create([
                    'source' => $this->source,
                    'input_type' => $hasText ? 'image_and_text' : 'image',
                    'raw_text_input' => $hasText ? $this->pastedText : null,
                    'image_paths' => $storedPaths,
                    'extracted_json' => $extracted,
                    'confidence' => $extracted['confidence'] ?? null,
                    'warnings' => $extracted['warnings'] ?? [],
                    'status' => 'pending_review',
                    'created_by_user_id' => auth()->id(),
                ]);
            } else {
                $extracted = $service->extractFromText($this->pastedText);

                $extraction = AiOrderExtraction::create([
                    'source' => $this->source,
                    'input_type' => 'text',
                    'raw_text_input' => $this->pastedText,
                    'extracted_json' => $extracted,
                    'confidence' => $extracted['confidence'] ?? null,
                    'warnings' => $extracted['warnings'] ?? [],
                    'status' => 'pending_review',
                    'created_by_user_id' => auth()->id(),
                ]);
            }
        } catch (ClaudeExtractionException $e) {
            Log::warning('AI order extraction failed: ' . $e->getMessage());
            $this->dispatch('extraction-failed', message: $e->getMessage());
            return;
        }

        $this->extractionId = $extraction->id;
        $this->confidence = $extracted['confidence'] ?? null;
        $this->warnings = $extracted['warnings'] ?? [];
        $this->customer = array_merge($this->customer, $extracted['customer'] ?? []);
        if (!empty($this->customer['address'])) {
            $this->customer['address'] = trim(preg_replace('/\s*[\r\n]+\s*/', ', ', $this->customer['address']));
        }
        $this->orderMeta = array_merge(
            $this->orderMeta,
            array_filter($extracted['order'] ?? [], fn ($v, $k) => $k === 'payment_method' && $v, ARRAY_FILTER_USE_BOTH)
        );
        $this->amountToCollect = isset($extracted['order']['amount_to_collect'])
            ? (float) $extracted['order']['amount_to_collect']
            : null;

        $this->lookupExistingCustomer();
        $this->buildLinesFromExtraction($extracted['products'] ?? []);
        $this->resolveDeliveryArea();
        $this->recomputeSubtotal();

        $this->step = 'review';
    }

    private function lookupExistingCustomer(): void
    {
        $phone = $this->normalizePhone($this->customer['phone'] ?? '');

        if (!$phone) {
            $this->existingCustomer = null;
            return;
        }

        $customer = Customer::where('phone', $phone)->first();

        if (!$customer) {
            $this->existingCustomer = null;
            return;
        }

        $this->existingCustomer = [
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'order_count' => Order::where('phone', $customer->phone)->count(),
            'is_blocked' => $customer->is_blocked,
        ];
    }

    private function normalizePhone(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $phone);

        if (str_starts_with($digits, '88') && strlen($digits) > 11) {
            $digits = substr($digits, 2);
        }

        return $digits ?: null;
    }

    private function buildLinesFromExtraction(array $extractedProducts): void
    {
        $this->lines = [];

        foreach ($extractedProducts as $index => $product) {
            $candidates = $this->findProductCandidates($product['product_name'] ?? '', $product['color'] ?? null);

            $matchedId = $candidates->first()?->id;
            $matchState = $candidates->isEmpty() ? 'no_match' : ($candidates->count() > 1 ? 'ambiguous' : 'matched');

            $this->lines[] = [
                'key' => $index,
                'product_name_raw' => $product['product_name'] ?? '',
                'color_raw' => $product['color'] ?? null,
                'size' => $product['size'] ?? null,
                'variant' => $product['variant'] ?? null,
                'quantity' => $product['quantity'] ?? 1,
                'matched_product_id' => $matchedId,
                'match_state' => $matchState,
                'candidates' => $candidates->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'price' => $p->discount_price ?? $p->price,
                ])->toArray(),
            ];
        }
    }

    private function findProductCandidates(string $rawName, ?string $color = null, int $limit = 5)
    {
        if (trim($rawName) === '') {
            return collect();
        }

        $query = products::query()
            ->where('status', 1)
            ->where(function ($q) use ($rawName) {
                $q->where('name', 'like', '%' . $rawName . '%');
                foreach (preg_split('/\s+/', trim($rawName)) as $word) {
                    if (mb_strlen($word) >= 3) {
                        $q->orWhere('name', 'like', '%' . $word . '%');
                    }
                }
            });

        if ($color) {
            $query->orderByRaw('CASE WHEN name LIKE ? THEN 0 ELSE 1 END', ['%' . $color . '%']);
        }

        return $query->limit($limit)->get(['id', 'name', 'price', 'discount_price', 'image']);
    }

    public function askAiToResolve($lineKey, OrderExtractionServiceInterface $service)
    {
        $lineIndex = $this->findLineIndex($lineKey);
        if ($lineIndex === null) {
            return;
        }

        $line = $this->lines[$lineIndex];

        try {
            $matchedId = $service->resolveProductMatch(
                $line['product_name_raw'] . ($line['color_raw'] ? ' ' . $line['color_raw'] : ''),
                $line['candidates']
            );
        } catch (ClaudeExtractionException $e) {
            Log::warning('AI product resolver failed: ' . $e->getMessage());
            $this->dispatch('extraction-failed', message: $e->getMessage());
            return;
        }

        if ($matchedId) {
            $this->lines[$lineIndex]['matched_product_id'] = $matchedId;
            $this->lines[$lineIndex]['match_state'] = 'matched';
        }

        $this->recomputeSubtotal();
    }

    public function selectProductForLine($lineKey, $productId)
    {
        $lineIndex = $this->findLineIndex($lineKey);
        if ($lineIndex === null) {
            return;
        }

        $this->lines[$lineIndex]['matched_product_id'] = $productId ?: null;
        $this->lines[$lineIndex]['match_state'] = $productId ? 'matched' : 'no_match';

        $this->recomputeSubtotal();
    }

    public function addLine()
    {
        $key = empty($this->lines) ? 0 : max(array_column($this->lines, 'key')) + 1;

        $this->lines[] = [
            'key' => $key,
            'product_name_raw' => '',
            'color_raw' => null,
            'size' => null,
            'variant' => null,
            'quantity' => 1,
            'matched_product_id' => null,
            'match_state' => 'no_match',
            'candidates' => [],
        ];
    }

    public function removeLine($lineKey)
    {
        $lineIndex = $this->findLineIndex($lineKey);
        if ($lineIndex === null) {
            return;
        }

        unset($this->lines[$lineIndex]);
        $this->lines = array_values($this->lines);

        $this->recomputeSubtotal();
    }

    private function findLineIndex($lineKey): ?int
    {
        foreach ($this->lines as $index => $line) {
            if ($line['key'] == $lineKey) {
                return $index;
            }
        }

        return null;
    }

    public function resolveDeliveryArea()
    {
        $district = $this->customer['district'] ?? '';
        $area = $this->customer['area'] ?? '';
        $needle = trim($district . ' ' . $area);

        $query = delivery_areas::query();

        if ($needle !== '') {
            $query->orderByRaw('CASE WHEN name LIKE ? THEN 0 ELSE 1 END', ['%' . $needle . '%']);
        }

        $match = $query->first();

        if ($match) {
            $this->deliveryAreaId = $match->id;
            $this->deliveryCharge = (float) $match->charge;
        }

        $this->recomputeSubtotal();
    }

    public function updatedDeliveryAreaId($value)
    {
        $area = delivery_areas::find($value);
        $this->deliveryCharge = $area ? (float) $area->charge : 0;
        $this->recomputeSubtotal();
    }

    public function recomputeSubtotal()
    {
        $subtotal = 0;

        foreach ($this->lines as $line) {
            if (!$line['matched_product_id']) {
                continue;
            }

            $product = products::find($line['matched_product_id']);
            if (!$product) {
                continue;
            }

            $price = (float) ($product->discount_price ?? $product->price);
            $subtotal += $price * (float) ($line['quantity'] ?: 1);
        }

        $this->subtotal = $subtotal;
        $this->total = max($subtotal - $this->discount, 0) + $this->deliveryCharge;

        if ($this->totalSource === 'collect' && $this->amountToCollect !== null
            && round($this->total, 2) !== round($this->amountToCollect, 2)) {
            $this->totalSource = 'calculated';
        }
    }

    public function updatedDiscount()
    {
        $this->recomputeSubtotal();
    }

    public function useCalculatedTotal()
    {
        $this->discount = 0;
        $this->totalSource = 'calculated';
        $this->recomputeSubtotal();
    }

    public function useAmountToCollect()
    {
        if ($this->amountToCollect === null) {
            return;
        }

        $baseTotal = $this->subtotal + $this->deliveryCharge;
        $this->discount = max($baseTotal - $this->amountToCollect, 0);
        $this->totalSource = 'collect';
        $this->recomputeSubtotal();
    }

    public function confirm()
    {
        $this->validate([
            'customer.name' => 'required|string',
            'customer.phone' => 'required|string|min:11',
        ]);

        if (empty($this->lines)) {
            $this->dispatch('extraction-failed', message: 'Add at least one product before confirming.');
            return;
        }

        foreach ($this->lines as $line) {
            if (!$line['matched_product_id']) {
                $this->dispatch('extraction-failed', message: 'Every product line must be matched to a real product before confirming.');
                return;
            }
        }

        $phone = $this->normalizePhone($this->customer['phone']);

        $customerRecord = Customer::where('phone', $phone)->first();
        if ($customerRecord && $customerRecord->is_blocked) {
            $this->dispatch('extraction-failed', message: 'This customer is blacklisted from ordering.');
            return;
        }

        $order = DB::transaction(function () use ($phone) {
            $order = new Order();
            $order->name = $this->customer['name'];
            $order->phone = $phone;
            $order->address = trim(($this->customer['address'] ?? '') . ' ' . ($this->customer['district'] ?? '') . ' ' . ($this->customer['area'] ?? ''));
            $order->delivery_area_id = $this->deliveryAreaId;
            $order->cod_percentage = 0;
            $order->cod_charge = 0;
            $order->subtotal = $this->subtotal;
            $order->discount = $this->discount;
            $order->fee = $this->deliveryCharge;
            $order->total = $this->total;
            $order->status = 'pending';
            $order->source = $this->source;
            $order->is_paid = false;
            $order->json_data = [
                'ai_extraction' => [
                    'extraction_id' => $this->extractionId,
                    'confidence' => $this->confidence,
                    'warnings' => $this->warnings,
                    'order_meta' => $this->orderMeta,
                    'amount_to_collect' => $this->amountToCollect,
                ],
            ];
            $order->save();

            foreach ($this->lines as $line) {
                $product = products::find($line['matched_product_id']);

                $orderItem = new Order_Item();
                $orderItem->order_id = $order->id;
                $orderItem->product_id = $product->id;
                $orderItem->quantity = $line['quantity'] ?: 1;
                $orderItem->price = $product->discount_price ?? $product->price;
                $orderItem->options = json_encode([
                    'size' => $line['size'] ?? null,
                    'color' => $line['color_raw'] ?? null,
                ]);
                $orderItem->save();
            }

            if (!$order->customer && strlen($order->phone) == 11) {
                $customer = $order->customer()->create([
                    'name' => $order->name,
                    'phone' => $order->phone,
                ]);

                $customer->devices()->create([
                    'user_agent' => request()->server('HTTP_USER_AGENT'),
                    'ip_address' => request()->server('REMOTE_ADDR'),
                ]);
            }

            if ($this->extractionId) {
                AiOrderExtraction::where('id', $this->extractionId)->update([
                    'status' => 'confirmed',
                    'order_id' => $order->id,
                    'resolved_json' => [
                        'customer' => $this->customer,
                        'lines' => $this->lines,
                        'order_meta' => $this->orderMeta,
                        'amount_to_collect' => $this->amountToCollect,
                    ],
                ]);
            }

            return $order;
        });

        try {
            Notification::send(\App\Models\User::all(), new NewOrderPushNotification($order));
        } catch (\Throwable $th) {
            Log::warning('New order push notification failed: ' . $th->getMessage());
        }

        session()->flash('status', 'Order created successfully from AI extraction.');
        return redirect()->route('admin.orders.details', $order->id);
    }

    public function discard()
    {
        if ($this->extractionId) {
            AiOrderExtraction::where('id', $this->extractionId)->update(['status' => 'discarded']);
        }

        $this->reset(['step', 'pastedText', 'uploadedImages', 'extractionId', 'existingCustomer', 'lines', 'confidence', 'warnings', 'deliveryAreaId', 'subtotal', 'deliveryCharge', 'discount', 'total', 'amountToCollect', 'totalSource']);
        $this->customer = [
            'name' => null, 'phone' => null, 'alternative_phone' => null,
            'address' => null, 'district' => null, 'area' => null,
        ];
        $this->orderMeta = [
            'payment_method' => 'cod',
        ];
        $this->step = 'input';
    }

    public function render()
    {
        $deliveryAreas = delivery_areas::all();

        $allProducts = products::where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'price', 'discount_price', 'image'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => $p->discount_price ?? $p->price,
                'image' => $p->image ? asset('storage/images/products/thumbnails/' . $p->image) : null,
            ])
            ->values();

        $productImagesById = $allProducts->pluck('image', 'id');

        return view('livewire.admin.order-ai-extract', compact('deliveryAreas', 'allProducts', 'productImagesById'));
    }
}
