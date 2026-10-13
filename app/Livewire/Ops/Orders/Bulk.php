<?php

namespace App\Livewire\Ops\Orders;

use App\Actions\Ops\PlaceBulkOrder;
use App\Models\Ops\{Account, DeliveryZone, Product};
use App\OrderIntake\{CustomerDirectory, IntakeSettings, OrderIntakeService};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Log, RateLimiter};
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\{Layout, Renderless, Title};
use Livewire\Component;
use Livewire\WithFileUploads;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Orders → Bulk order (ported from ecomx-automation BulkOrderCreate + HandlesOrderIntake). A spreadsheet-style sheet where many
 * orders are typed or pasted from Excel at once; the grid (editing, paste parsing, product matching, totals) runs in the browser,
 * this component serves the catalogue, customer lookups, delivery charges, Excel upload / template and places the orders — one
 * transaction per order (App\Actions\Ops\PlaceBulkOrder). "AI Order" (the modal) turns a pasted chat / screenshots into rows.
 */
#[Layout('layouts.ops', ['module' => 'Orders & Packing', 'title' => 'Bulk order', 'tabs' => 'orders'])]
#[Title('Bulk order')]
class Bulk extends Component
{
    use WithFileUploads;

    /** Excel / CSV picked in "Upload Excel" — read by readUpload() and handed to the sheet. */
    public $sheetFile;

    protected function sourceKeys(): array { return array_keys(config('ops.sources')); }

    /** active products with their sellable variants, prices and available stock (Ops: simple / combo sell through one variant) */
    #[Renderless]
    public function catalog(): array
    {
        return Product::where('status', 'active')->with(['variants' => fn ($q) => $q->where('is_active', true)->orderBy('id')])->orderBy('name')->get()
            ->map(function (Product $p) {
                $variable = $p->type === 'variable';
                $first = $p->variants->first();
                return [
                    'id' => $p->id, 'name' => $p->name, 'code' => (string) $p->code, 'variable' => $variable, 'price' => (float) $p->sellPrice(),
                    'stock' => $variable ? null : (float) max(0, (int) ($first?->stock - $first?->reserved)),
                    'image' => $p->thumb_url,
                    'variants' => $variable ? $p->variants->map(fn ($v) => [
                        'id' => $v->id, 'sku' => (string) $v->sku, 'label' => $v->option_text ?: (string) $v->sku,
                        'values' => array_values(array_map(fn ($o) => mb_strtolower((string) $o), array_filter((array) ($v->options ?: ['Size' => $v->size, 'Color' => $v->color])))),
                        'price' => (float) $v->setRelation('product', $p)->effective_price, 'stock' => (float) max(0, $v->stock - $v->reserved),
                    ])->values()->all() : [],
                ];
            })->values()->all();
    }

    #[Renderless]
    public function lookupCustomers(array $phones): array { return CustomerDirectory::lookup($phones); }

    /** delivery charge per sheet row = the chosen zone's charge ("method" = Ops delivery zone) */
    #[Renderless]
    public function quoteShipping(array $requests): array
    {
        $zones = DeliveryZone::where('is_active', true)->pluck('charge', 'id');
        $quotes = [];
        foreach (array_slice($requests, 0, 500) as $r)
            if (isset($r['key']) && $zones->has($r['method_id'] ?? 0)) $quotes[$r['key']] = (float) $zones[$r['method_id']];
        return $quotes;
    }

    /** places a chunk of orders; one result per row key (the order, or why it failed) */
    #[Renderless]
    public function submit(array $orders, array $settings): array
    {
        validator($settings, [
            'status' => ['required', Rule::in(['pending', 'confirmed'])], 'batch' => 'required|string|max:40', 'admin_note' => 'nullable|string|max:1000',
            'advance_account_id' => 'nullable|integer|exists:ops_accounts,id', 'advance_method' => 'nullable|string|max:20',
        ])->validate();

        $results = []; $placed = 0;
        foreach (array_slice($orders, 0, 50) as $o) {
            $key = (string) ($o['key'] ?? '');
            $intakeIds = collect($o['intake_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->take(10)->values()->all();
            try {
                $order = app(PlaceBulkOrder::class)->handle([
                    'phone' => (string) ($o['phone'] ?? ''), 'name' => isset($o['name']) ? mb_substr((string) $o['name'], 0, 255) : null,
                    'address' => isset($o['address']) ? mb_substr((string) $o['address'], 0, 1000) : null,
                    'items' => collect($o['items'] ?? [])->take(100)->map(fn ($i) => ['product_id' => (int) ($i['product_id'] ?? 0), 'variant_id' => isset($i['variant_id']) ? (int) $i['variant_id'] : null,
                        'quantity' => (float) ($i['quantity'] ?? 0), 'unit_price' => isset($i['unit_price']) && is_numeric($i['unit_price']) ? (float) $i['unit_price'] : null])->all(),
                    'shipping_method_id' => ! empty($o['method_id']) ? (int) $o['method_id'] : null,
                    'delivery_charge' => is_numeric($o['delivery'] ?? null) ? (float) $o['delivery'] : 0.0, 'delivery_manual' => (bool) ($o['delivery_manual'] ?? false),
                    'discount' => is_numeric($o['discount'] ?? null) ? (float) $o['discount'] : 0.0, 'advance' => is_numeric($o['advance'] ?? null) ? (float) $o['advance'] : 0.0,
                    'advance_account_id' => ! empty($settings['advance_account_id']) ? (int) $settings['advance_account_id'] : null,
                    'source' => in_array($o['source'] ?? '', $this->sourceKeys(), true) ? $o['source'] : $this->sourceKeys()[0],
                    'status' => $settings['status'], 'customer_note' => isset($o['note']) ? mb_substr((string) $o['note'], 0, 1000) : null,
                    'admin_note' => trim("Bulk order {$settings['batch']}".($intakeIds ? ' · AI Order #'.implode(', #', $intakeIds) : '').(($settings['admin_note'] ?? '') ? ' · '.$settings['admin_note'] : '')),
                ]);
                $placed++;
                foreach ($intakeIds as $id) app(OrderIntakeService::class)->recordPlaced($id, $order->id);
                $results[$key] = ['ok' => true, 'id' => preg_replace('/^\D+/', '', $order->number),   // the sheet shows it as #id
                    'total' => (float) $order->total, 'url' => route('ops.orders.show', $order)];
            } catch (InvalidArgumentException $e) {
                $results[$key] = ['ok' => false, 'error' => $e->getMessage()];
            } catch (\Throwable $e) {
                report($e);
                $results[$key] = ['ok' => false, 'error' => 'Could not place this order: '.$e->getMessage()];
            }
        }
        cache()->forget('customers.count');
        return ['results' => $results, 'placed' => $placed];
    }

    /** uploaded .xlsx / .csv → plain grid of strings (first sheet, ≤1000 rows × 30 columns); the sheet maps the columns */
    #[Renderless]
    public function readUpload(): array
    {
        $this->validate(['sheetFile' => 'required|file|max:5120|mimes:xlsx,csv,txt']);
        try {
            $rows = IOFactory::load($this->sheetFile->getRealPath())->getActiveSheet()->toArray(null, true, true, false);
        } finally {
            $this->sheetFile = null;
        }
        $grid = [];
        foreach ($rows as $row) {
            $cells = array_map(fn ($v) => is_float($v) && floor($v) == $v && abs($v) < 1e15 ? (string) (int) $v : trim((string) $v), array_slice($row, 0, 30));
            if (array_filter($cells, fn ($c) => $c !== '') !== []) $grid[] = $cells;
            if (count($grid) >= 1000) break;
        }
        return $grid;
    }

    /** blank .xlsx with the sheet's columns and two example rows from the real catalogue */
    public function downloadTemplate()
    {
        $ex = Product::where('status', 'active')->with(['variants' => fn ($q) => $q->where('is_active', true)->orderBy('id')])->latest('id')->limit(2)->get();
        $text = fn ($p, $qty) => $p ? (($p->type === 'variable' && $p->variants->first()?->sku) ? $p->variants->first()->sku : $p->code)." x$qty" : 'SKU-001 x1';
        $book = new Spreadsheet(); $sheet = $book->getActiveSheet()->setTitle('Bulk Orders');
        $sheet->fromArray([
            ['Phone', 'Customer Name', 'Address', 'Products', 'Qty', 'Price', 'Delivery Zone', 'Delivery Charge', 'Discount', 'Advance', 'Source', 'Note'],
            ['01712345678', 'Rahim Uddin', 'House 12, Road 5, Dhanmondi, Dhaka', $text($ex->get(0), 1), '', '', '', '', '', '', 'facebook', 'Call before delivery'],
            ['01812345678', 'Karim Ahmed', 'Sadar Road, Cumilla', $text($ex->get(0), 2).', '.$text($ex->get(1) ?? $ex->get(0), 1), '', '', '', '', '100', '200', 'phone', ''],
        ], null, 'A1', true);
        $sheet->getStyle('A1:L1')->getFont()->setBold(true);
        foreach (['A' => 15, 'B' => 20, 'C' => 40, 'D' => 36] as $col => $w) $sheet->getColumnDimension($col)->setWidth($w);
        $sheet->freezePane('A2');
        $path = tempnam(sys_get_temp_dir(), 'bulk').'.xlsx';
        (new Xlsx($book))->save($path);
        return response()->download($path, 'bulk-order-template.xlsx')->deleteFileAfterSend();
    }

    // ── AI Order modal (HandlesOrderIntake) ─────────────────────────────────────────────────────────
    /** text + base64 attachments ([{name, type, data}]) → OrderIntakeService::extract() result (rows for the sheet) or ['error' => …] */
    #[Renderless]
    public function extractOrders(string $text, string $source, bool $forceAi = false, array $attachments = []): array
    {
        $settings = app(IntakeSettings::class);
        $files = $this->attachmentFiles(array_slice($attachments, 0, $settings->maxFiles() + 1));
        $v = validator(['text' => $text, 'source' => $source, 'files' => $files], [
            'text' => 'nullable|string|max:'.OrderIntakeService::MAX_TEXT, 'source' => ['required', Rule::in($this->sourceKeys())],
            'files' => 'array|max:'.$settings->maxFiles(), 'files.*' => 'file|max:'.$settings->maxFileKb().'|mimetypes:'.implode(',', $settings->allowedMimes()),
        ], ['files.max' => 'Add at most :max files at a time.', 'files.*.max' => 'Each file must be under '.round($settings->maxFileKb() / 1024, 1).' MB.', 'files.*.mimetypes' => 'That file type isn\'t allowed for AI Order.']);
        if ($v->fails()) { Log::warning('AI Order: rejected', ['error' => $v->errors()->first()]); return ['error' => $v->errors()->first()]; }
        if (trim($text) === '' && $files === []) return ['error' => 'Paste a message or add a screenshot first.'];

        $key = 'order-intake:'.auth()->id();
        if (RateLimiter::tooManyAttempts($key, 30)) return ['error' => 'Too many requests — wait '.RateLimiter::availableIn($key).'s.'];
        RateLimiter::hit($key, 60);
        @set_time_limit($settings->timeout() + 60);
        try {
            return app(OrderIntakeService::class)->extract($text, $files, $source, $forceAi, auth()->id());
        } catch (\Throwable $e) {
            report($e);
            return ['error' => 'Could not read this message: '.$e->getMessage()];
        }
    }

    protected function attachmentFiles(array $attachments): array
    {
        $files = [];
        foreach ($attachments as $a) {
            $data = is_array($a) && is_string($a['data'] ?? null) ? base64_decode(preg_replace('/^data:[^,]*,/', '', $a['data']), true) : false;
            if ($data === false || $data === '') continue;
            $path = tempnam(sys_get_temp_dir(), 'intake'); file_put_contents($path, $data);
            register_shutdown_function(fn () => @unlink($path));
            $files[] = new UploadedFile($path, mb_substr(basename((string) ($a['name'] ?? 'attachment')), 0, 100), null, null, true);
        }
        return $files;
    }

    public function render()
    {
        $zones = DeliveryZone::where('is_active', true)->orderBy('charge')->orderBy('id')->get(['id', 'name', 'charge']);
        $s = app(IntakeSettings::class);
        return view('livewire.ops.orders.bulk', ['config' => [
            'userId' => auth()->id(), 'phoneCode' => '880', 'accountsOn' => true, 'defaultMethodId' => $zones->first()?->id,
            'methods' => $zones->map(fn ($z) => ['id' => $z->id, 'label' => $z->name.' · ৳'.$z->charge, 'zone' => mb_strtolower($z->name)])->all(),
            'sources' => collect(config('ops.sources'))->map(fn ($l, $k) => ['value' => $k, 'label' => $l])->values()->all(),
            'accounts' => Account::orderBy('id')->get(['id', 'name'])->map(fn ($a) => ['id' => $a->id, 'label' => $a->name])->all(),
            'ordersUrl' => route('ops.orders.index'),
            'intake' => ['aiAvailable' => $s->aiAvailable(), 'maxFiles' => $s->maxFiles(), 'maxFileKb' => $s->maxFileKb(), 'mimes' => $s->allowedMimes(),
                'open' => request()->boolean('intake'), 'settingsUrl' => route('ops.settings.index', ['tab' => 'ai_order'])],
        ]]);
    }
}
