<?php

namespace App\Livewire\Admin;

use App\Models\Order;
use App\Support\Attribution;
use Carbon\Carbon;
use Livewire\Component;

class OrderCampaigns extends Component
{
    public $start_date;
    public $end_date;
    public $order_status = '';
    public $campaign_search = '';
    public $product_search = '';
    public $order_search = '';
    public $group_by = 'campaign'; // campaign | adset | ad
    public $traffic = '';          // '' | ads | untracked

    protected $queryString = [
        'start_date' => ['except' => ''],
        'end_date' => ['except' => ''],
        'order_status' => ['except' => ''],
        'campaign_search' => ['except' => ''],
        'product_search' => ['except' => ''],
        'order_search' => ['except' => ''],
        'group_by' => ['except' => 'campaign'],
        'traffic' => ['except' => ''],
    ];

    /** Meta {{site_source_name}} values */
    private const SITE_SOURCES = ['fb' => 'Facebook', 'ig' => 'Instagram', 'msg' => 'Messenger', 'an' => 'Audience Network'];

    public function mount()
    {
        $today = now()->toDateString();
        $this->start_date = $this->start_date ?: $today;
        $this->end_date = $this->end_date ?: $today;
        if (!in_array($this->group_by, ['campaign', 'adset', 'ad'], true)) {
            $this->group_by = 'campaign';
        }
    }

    public function setRange($range)
    {
        $today = now();
        [$start, $end] = match ($range) {
            'yesterday' => [$today->copy()->subDay(), $today->copy()->subDay()],
            'last7' => [$today->copy()->subDays(6), $today],
            'last30' => [$today->copy()->subDays(29), $today],
            'this_month' => [$today->copy()->startOfMonth(), $today],
            default => [$today, $today],
        };
        $this->start_date = $start->toDateString();
        $this->end_date = $end->toDateString();
    }

    public function resetFilters()
    {
        $this->reset(['order_status', 'campaign_search', 'product_search', 'order_search', 'group_by', 'traffic']);
        $this->setRange('today');
    }

    public function render()
    {
        $status_group = Order::whereNot('status', 'deleted')->select('status')->groupBy('status')->pluck('status');

        if (!Attribution::columnsReady()) {
            return view('livewire.admin.order-campaigns', [
                'ready' => false, 'groups' => [], 'summary' => null, 'status_group' => $status_group,
            ]);
        }

        $start = $this->parseDate($this->start_date) ?? now()->startOfDay();
        $end = $this->parseDate($this->end_date) ?? now();
        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }

        $orders = Order::query()
            ->where('status', '!=', 'deleted')
            ->whereBetween('created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->when($this->order_status, fn($q) => $q->where('status', $this->order_status))
            ->when($this->traffic === 'ads', fn($q) => $q->where(fn($q) => $q
                ->whereNotNull('utm_campaign')->orWhereNotNull('campaign_id')->orWhereNotNull('campaign_name')
                ->orWhereNotNull('fbclid')->orWhereNotNull('gclid')))
            ->when($this->traffic === 'untracked', fn($q) => $q
                ->whereNull('utm_campaign')->whereNull('campaign_id')->whereNull('campaign_name')
                ->whereNull('fbclid')->whereNull('gclid'))
            ->when(trim($this->campaign_search) !== '', function ($q) {
                $search = trim($this->campaign_search);
                $q->where(function ($q) use ($search) {
                    foreach (['utm_campaign', 'campaign_name', 'adset_name', 'ad_name', 'utm_term', 'utm_content', 'utm_source'] as $column) {
                        $q->orWhere($column, 'like', "%{$search}%");
                    }
                    $q->orWhere('campaign_id', $search)->orWhere('adset_id', $search)->orWhere('ad_id', $search);
                });
            })
            ->when(trim($this->product_search) !== '', function ($q) {
                $search = trim($this->product_search);
                $q->whereHas('Order_Item.product', fn($q) => $q->where(fn($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('id', $search)));
            })
            ->when(trim($this->order_search) !== '', function ($q) {
                // "#123" = order number only; phone matching needs 6+ chars so short order numbers don't hit every phone
                $raw = trim($this->order_search);
                $search = ltrim($raw, '#');
                $q->where(function ($q) use ($raw, $search) {
                    $q->where('id', $search);
                    if (!str_starts_with($raw, '#') && strlen($search) >= 6) {
                        $q->orWhere('phone', 'like', "%{$search}%");
                    }
                });
            })
            ->withSum('Order_Item as pieces', 'quantity')
            ->orderByDesc('created_at')
            ->get([
                'id', 'name', 'phone', 'status', 'total', 'created_at', 'source',
                'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
                'campaign_id', 'campaign_name', 'adset_id', 'adset_name', 'ad_id', 'ad_name',
                'placement', 'site_source', 'fbclid', 'gclid', 'referrer',
            ]);

        $groups = [];
        foreach ($orders as $order) {
            [$key, $group] = $this->groupFor($order);
            $groups[$key] ??= $group + ['orders' => [], 'order_count' => 0, 'pieces' => 0, 'amount' => 0, 'statuses' => []];
            $row = &$groups[$key];
            $row['order_count']++;
            $row['pieces'] += (int) $order->pieces;
            $row['amount'] += (float) $order->total;
            $row['statuses'][$order->status] = ($row['statuses'][$order->status] ?? 0) + 1;
            $row['orders'][] = [
                'id' => $order->id,
                'name' => $order->name,
                'phone' => $order->phone,
                'status' => $order->status,
                'total' => (float) $order->total,
                'pieces' => (int) $order->pieces,
                'ad' => $this->group_by === 'ad' ? null : ($order->ad_name ?: $order->utm_content),
                'time' => $order->created_at->format('d M, h:i A'),
            ];
            unset($row);
        }

        foreach ($groups as &$row) {
            $lost = ($row['statuses']['cancelled'] ?? 0) + ($row['statuses']['returned'] ?? 0);
            $row['lost_rate'] = $row['order_count'] ? round($lost / $row['order_count'] * 100) : 0;
            arsort($row['statuses']);
        }
        unset($row);

        // tracked groups first (by orders), untracked buckets last
        uasort($groups, fn($a, $b) => [$a['tracked'] ? 0 : 1, -$a['order_count'], -$a['amount']]
            <=> [$b['tracked'] ? 0 : 1, -$b['order_count'], -$b['amount']]);

        $tracked = collect($groups)->where('tracked', true);
        $summary = [
            'orders' => $orders->count(),
            'tracked_orders' => $tracked->sum('order_count'),
            'campaigns' => $tracked->count(),
            'pieces' => $orders->sum('pieces'),
            'amount' => $orders->sum('total'),
        ];

        return view('livewire.admin.order-campaigns', [
            'ready' => true,
            'groups' => $groups,
            'summary' => $summary,
            'status_group' => $status_group,
        ]);
    }

    /** [group key, group info] for an order at the selected level */
    private function groupFor(Order $order): array
    {
        [$id, $name] = match ($this->group_by) {
            'adset' => [$order->adset_id, $order->adset_name ?: $order->utm_term],
            'ad' => [$order->ad_id, $order->ad_name ?: $order->utm_content],
            default => [$order->campaign_id, $order->campaign_name ?: $order->utm_campaign],
        };
        $channel = self::SITE_SOURCES[strtolower((string) $order->site_source)]
            ?? self::SITE_SOURCES[strtolower((string) $order->utm_source)]
            ?? $order->utm_source;

        if ($id || $name) {
            return ['c:' . ($id ?: mb_strtolower($name)), [
                'tracked' => true,
                'label' => $name ?: 'ID ' . $id,
                'id' => $id,
                // ads often share names across ad sets, so show where each one lives
                'parent' => match ($this->group_by) {
                    'campaign' => null,
                    'adset' => $order->campaign_name ?: $order->utm_campaign,
                    default => collect([$order->campaign_name ?: $order->utm_campaign, $order->adset_name ?: $order->utm_term])
                        ->filter()->implode(' › ') ?: null,
                },
                'channel' => $channel,
                'medium' => $order->utm_medium,
            ]];
        }

        // campaign is known but this level (ad set / ad) wasn't tagged on the ad
        $campaign = $order->campaign_name ?: $order->utm_campaign;
        if ($campaign || $order->campaign_id) {
            $levelName = $this->group_by === 'adset' ? 'ad set' : 'ad';
            return ['n:' . ($order->campaign_id ?: mb_strtolower($campaign)), [
                'tracked' => true,
                'label' => "({$levelName} not tagged)",
                'id' => null,
                'parent' => $campaign ?: 'ID ' . $order->campaign_id,
                'channel' => $channel,
                'medium' => $order->utm_medium,
            ]];
        }

        // no campaign info at all: explain where the order came from instead
        $referrerHost = $order->referrer ? preg_replace('/^www\./', '', (string) parse_url($order->referrer, PHP_URL_HOST)) : null;
        [$key, $label, $hint] = match (true) {
            (bool) $order->fbclid => ['x:fbclid', 'Facebook / Instagram ad (no URL parameters)', 'Ad clicked, but the ad has no campaign URL parameters set'],
            (bool) $order->gclid => ['x:gclid', 'Google ad (no URL parameters)', 'Ad clicked, but no UTM tags on the ad'],
            (bool) $order->utm_source => ['x:utm:' . $order->utm_source, 'utm_source: ' . $order->utm_source, 'Tagged link without a campaign name'],
            (bool) $referrerHost => ['x:ref:' . $referrerHost, 'Referral · ' . $referrerHost, 'Came from another website'],
            default => ['x:none', 'No campaign data', 'Direct visit, organic, manual/AI order, or placed before tracking was added'],
        };

        return [$key, ['tracked' => false, 'label' => $label, 'id' => null, 'parent' => null, 'channel' => null, 'medium' => null, 'hint' => $hint]];
    }

    private function parseDate($value)
    {
        try {
            return $value ? Carbon::parse($value) : null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
