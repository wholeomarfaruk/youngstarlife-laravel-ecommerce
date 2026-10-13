<?php

namespace Database\Seeders;

use App\Models\Ops\{Account, Batch, Brand, Campaign, Category, Courier, CourierSettlement, Customer, CustomerReturn, DeliveryZone, Order, Product, ProductVariant, PurchaseOrder, Shipment, Supplier, SupplierReturn, Transaction, Warehouse};
use App\Models\User;
use App\Services\Ops\{BatchAllocator, OrderWorkflow, ReceivingService, StockService};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo data from the YoungStar Ops design (spec §9). Runs through the real services
 * (PO receive → batches B001–B013, FIFO allocate, pack captures cost, returns restock the original batch).
 *   php artisan db:seed --class=OpsDemoSeeder      (on empty ops_ tables)
 */
class OpsDemoSeeder extends Seeder
{
    protected User $admin;
    protected array $v = [];   // 'CGO-BLU-M' => variant

    public function run(): void
    {
        $this->call(OpsSeeder::class);
        if (Product::exists()) { $this->command?->warn('Ops demo data already present — truncate the ops_ tables first.'); return; }
        $this->admin = User::where('role', 'admin')->firstOrFail();
        $this->admin->forceFill(['ops_role' => 'Admin', 'is_active' => true])->save();
        auth()->setUser($this->admin);
        $mirpur = Warehouse::where('name', 'Mirpur WH')->first();

        // ── team (random passwords — invite them through Settings → Users & roles to set their own)
        foreach ([['Rakib Hasan', 'Admin'], ['Sumaiya Akter', 'Manager'], ['Jahid', 'Packer'], ['Mamun', 'Packer'], ['Faruk', 'Accounts']] as [$name, $role]) {
            $email = str($name)->before(' ')->lower().'@youngstarlife.test';
            User::where('email', $email)->exists() || User::forceCreate(['name' => $name, 'email' => $email, 'password' => Hash::make(str()->random(24)), 'role' => 'staff', 'ops_role' => $role, 'is_active' => true, 'warehouse_id' => $mirpur->id]);
        }

        // ── catalog
        $brand = Brand::create(['name' => 'YoungStar', 'slug' => 'youngstar']);
        $cat = collect(['Pants', 'T-shirts', 'Dresses', 'Jackets'])->mapWithKeys(fn ($n) => [$n => Category::create(['name' => $n, 'slug' => str($n)->slug()])]);
        $catalog = [
            ['Cargo Pant', 'CGO', 'Pants', 1450, 1250, ['Blue', 'Black', 'Olive'], ['M', 'L', 'XL']],
            ['Polo T-shirt', 'POL', 'T-shirts', 650, null, ['White', 'Navy'], ['M', 'L', 'XL']],
            ['Summer Dress', 'SDR', 'Dresses', 1850, 1690, ['Floral', 'Yellow'], ['S', 'M', 'L']],
            ['Denim Jacket', 'DNJ', 'Jackets', 2600, null, ['Blue'], ['M', 'L', 'XL']],
            ['Jogger', 'JOG', 'Pants', 950, null, ['Grey', 'Black'], ['M', 'L', 'XL']],
        ];
        $n = 0;
        foreach ($catalog as [$name, $code, $c, $price, $sale, $colors, $sizes]) {
            $p = Product::create(['type' => 'variable', 'name' => $name, 'slug' => str($name)->slug(), 'code' => $code, 'brand_id' => $brand->id, 'price' => $price, 'sale_price' => $sale, 'status' => 'active', 'low_stock_threshold' => 15,
                'short_description' => $name.' — everyday comfort', 'size_chart' => "Size | Waist | Length\nM | 30 | 40\nL | 32 | 41\nXL | 34 | 42", 'weight_kg' => 0.45]);
            $p->categories()->attach($cat[$c]);
            foreach ($colors as $col) foreach ($sizes as $s) {
                $sku = $code.'-'.strtoupper(substr($col, 0, 3)).'-'.$s;
                $this->v[$sku] = $p->variants()->create(['sku' => $sku, 'barcode' => '890123'.str_pad((string) ++$n, 6, '0', STR_PAD_LEFT).'0', 'color' => $col, 'size' => $s]);
            }
        }

        // ── suppliers 2, 3, 5, 7, 9
        $sup = collect([2 => ['Karim', '01711-000002', 'Cargo pant, Jogger'], 3 => ['Selina', '01811-000003', 'Pants'], 5 => ['Jamal', '01911-000005', 'Cargo pant'], 7 => ['Habib', '01611-000007', 'Polo, Denim'], 9 => ['Nasrin', '01511-000009', 'Dresses']])
            ->map(fn ($s, $i) => Supplier::create(['name' => 'Supplier '.$i, 'contact_name' => $s[0], 'phone' => $s[1], 'address' => 'Islampur, Old Dhaka', 'categories' => $s[2]]));
        foreach ([['CGO', 5, 'S5-CG22', 'Cargo 6-pocket'], ['CGO', 2, 'CG-22', 'Cargo pant premium'], ['POL', 7, 'PL-01', 'Polo pique'], ['SDR', 9, 'SD-7', 'Summer frock']] as [$code, $s, $sc, $sn])
            Product::where('code', $code)->first()->suppliers()->attach($sup[$s]->id, ['supplier_code' => $sc, 'supplier_name' => $sn]);

        // ── purchase orders PO-101 … PO-107 → batches B001 … B013 (in this receiving order)
        $po = [];
        $mk = function (int $num, int $s, int $daysAgo, array $items, string $status = 'sent') use (&$po, $mirpur, $sup) {
            $p = PurchaseOrder::create(['number' => 'PO-'.$num, 'supplier_id' => $sup[$s]->id, 'warehouse_id' => $mirpur->id, 'ordered_at' => today()->subDays($daysAgo), 'expected_at' => today()->subDays($daysAgo)->addDays(7), 'status' => $status, 'created_by' => $this->admin->id]);
            foreach ($items as [$sku, $qty, $cost]) $p->items()->create(['variant_id' => $this->v[$sku]->id, 'qty' => $qty, 'unit_cost' => $cost]);
            return $po[$num] = $p;
        };
        $mk(101, 5, 45, [['CGO-BLU-M', 100, 500], ['CGO-BLU-L', 80, 500]]);
        $mk(102, 2, 42, [['CGO-BLU-XL', 50, 520], ['CGO-BLA-M', 60, 520], ['CGO-BLA-L', 60, 520], ['JOG-GRE-L', 60, 420]]);
        $mk(103, 3, 38, [['CGO-OLI-M', 40, 510]], 'cancelled');
        $mk(104, 7, 30, [['POL-WHI-M', 80, 260], ['POL-WHI-L', 80, 260], ['POL-NAV-M', 70, 260], ['DNJ-BLU-M', 25, 1300], ['DNJ-BLU-L', 25, 1300]]);
        $mk(105, 9, 20, [['SDR-FLO-S', 40, 820], ['SDR-FLO-M', 40, 820]]);
        $mk(106, 3, 5, [['CGO-OLI-M', 40, 510], ['CGO-OLI-L', 40, 510]]);
        $mk(107, 5, 1, [['CGO-BLU-M', 60, 500]], 'draft');
        $recv = function (int $num, array $lines, int $daysAgo) use (&$po) {
            $p = $po[$num]->fresh('items');
            $grn = app(ReceivingService::class)->receive($p, $p->items->filter(fn ($i) => isset($lines[$i->variant->sku]))->mapWithKeys(fn ($i) => [$i->id => ['now' => $lines[$i->variant->sku], 'damaged' => 0]])->all());
            $grn->batches()->update(['received_at' => now()->subDays($daysAgo)->setTime(11, 0)]); $grn->forceFill(['created_at' => now()->subDays($daysAgo)])->save();
        };
        $recv(101, ['CGO-BLU-M' => 100], 40);                                                // B001 · Supplier 5 · PO-101 · 100 pcs ৳500
        $recv(102, ['CGO-BLU-XL' => 50], 38);                                               // B002 · Supplier 2 · PO-102 · 50 pcs ৳520
        $recv(101, ['CGO-BLU-L' => 80], 36);                                                // B003
        $recv(102, ['CGO-BLA-M' => 60, 'CGO-BLA-L' => 60], 34);                              // B004 B005
        $recv(104, ['POL-WHI-M' => 80, 'POL-WHI-L' => 80, 'POL-NAV-M' => 70], 26);           // B006 B007 B008
        $recv(105, ['SDR-FLO-S' => 30, 'SDR-FLO-M' => 30], 16);                              // B009 B010 (PO-105 partial)
        $recv(104, ['DNJ-BLU-M' => 25, 'DNJ-BLU-L' => 25], 12);                              // B011 B012
        $recv(102, ['JOG-GRE-L' => 60], 9);                                                 // B013
        $po[101]->update(['paid' => 50000, 'paid_from' => 'Bank']); $po[102]->update(['paid' => 30000, 'paid_from' => 'bKash']); $po[104]->update(['paid' => 40000, 'paid_from' => 'Bank']);
        foreach ([[101, 50000, 'Bank', 5], [102, 30000, 'bKash', 2], [104, 40000, 'Bank', 7]] as [$num, $amt, $acc, $s]) {
            Transaction::create(['account_id' => Account::where('name', $acc)->value('id'), 'direction' => 'out', 'category' => 'Supplier', 'amount' => $amt, 'description' => 'Supplier payment · PO-'.$num.' · Supplier '.$s, 'date' => today()->subDays(30), 'ref_type' => $po[$num]->getMorphClass(), 'ref_id' => $po[$num]->id, 'user_id' => $this->admin->id]);
            $sup[$s]->decrement('due', $amt);
        }

        // ── campaigns, customers
        $camp = collect([['Eid Collection', 'Facebook', 18000], ['Cargo Reels', 'Instagram', 6500], ['Brand Search', 'Google', 3000]])
            ->map(fn ($c) => Campaign::create(['name' => $c[0], 'platform' => $c[1], 'utm_campaign' => str($c[0])->slug(), 'spend' => $c[2], 'starts_at' => today()->subDays(30), 'status' => 'active']));
        $zones = DeliveryZone::orderBy('id')->get()->values();
        $people = [['Nusrat Jahan', 'Mirpur', 0, 'facebook'], ['Sadia Rahman', 'Dhanmondi', 0, 'website'], ['Tania Ahmed', 'Uttara', 0, 'instagram'], ['Arif Khan', 'Savar', 1, 'facebook'], ['Rafi Hasan', 'Chattogram', 2, 'facebook'],
            ['Mim Chowdhury', 'Gulshan', 0, 'website'], ['Farzana Akter', 'Mohammadpur', 0, 'instagram'], ['Rumana Islam', 'Sylhet', 2, 'facebook'], ['Shila Das', 'Banani', 0, 'website'], ['Kamrul Hasan', 'Gazipur', 2, 'phone'], ['Jannat Ara', 'Tongi', 1, 'facebook'], ['Tahmina Begum', 'Khulna', 2, 'instagram']];
        $cust = collect($people)->map(fn ($p, $i) => Customer::create(['name' => $p[0], 'phone' => '017'.str_pad((string) (11000000 + $i * 7919), 8, '0', STR_PAD_LEFT), 'address' => 'House '.($i + 3).', Road '.($i + 2).', '.$p[1], 'area' => $p[1], 'source' => $p[3], 'created_at' => now()->subDays(60 - $i)]))->values();

        // ── orders #1 … #30
        $wf = app(OrderWorkflow::class); $alloc = app(BatchAllocator::class);
        $plan = [
            // [#, customer, [[sku, qty]], days ago, target, advance]
            [1, 0, [['CGO-BLU-M', 1]], 13, 'delivered', 0], [2, 1, [['POL-WHI-M', 2]], 13, 'delivered', 0], [3, 3, [['CGO-BLA-M', 1]], 12, 'returned', 0], [4, 2, [['SDR-FLO-S', 1]], 12, 'delivered', 0],
            [5, 0, [['CGO-BLU-M', 1], ['CGO-BLU-XL', 1]], 11, 'partial_return', 'full'], [6, 5, [['DNJ-BLU-M', 1]], 11, 'delivered', 0], [7, 4, [['CGO-BLU-L', 2]], 10, 'returned', 0], [8, 6, [['POL-NAV-M', 1], ['POL-WHI-L', 1]], 10, 'delivered', 0],
            [9, 8, [['SDR-FLO-M', 1]], 9, 'returned_refund', 0], [10, 7, [['CGO-BLA-L', 1]], 9, 'delivered', 0], [11, 1, [['JOG-GRE-L', 2]], 8, 'delivered', 0], [12, 9, [['CGO-BLU-M', 1]], 8, 'returning', 0],
            [13, 10, [['POL-WHI-M', 1]], 7, 'delivered', 0], [14, 2, [['CGO-BLU-XL', 1]], 7, 'shipped', 0], [15, 5, [['DNJ-BLU-L', 1]], 6, 'delivered', 0], [16, 11, [['SDR-FLO-S', 1]], 6, 'returning', 0],
            [17, 0, [['POL-NAV-M', 2]], 5, 'shipped', 0], [18, 3, [['CGO-BLA-M', 1]], 5, 'on_hold', 0], [19, 6, [['CGO-BLU-L', 1], ['JOG-GRE-L', 1]], 4, 'packed', 0], [20, 1, [['POL-WHI-L', 1]], 4, 'shipped', 0],
            [21, 8, [['CGO-BLU-M', 1]], 3, 'processing', 0], [22, 4, [['CGO-BLA-L', 1]], 3, 'cancelled', 0], [23, 2, [['SDR-FLO-M', 1]], 2, 'confirmed', 0], [24, 5, [['DNJ-BLU-M', 1]], 2, 'confirmed', 0],
            [25, 7, [['CGO-BLU-XL', 1]], 1, 'pending', 0], [26, 0, [['JOG-GRE-L', 1]], 1, 'pending', 0], [27, 9, [['POL-WHI-M', 1]], 1, 'pending', 0], [28, 10, [['CGO-BLU-M', 2]], 0, 'pending', 0],
            [29, 11, [['SDR-FLO-S', 1]], 0, 'pending', 0], [30, 6, [['POL-NAV-M', 1]], 0, 'pending', 0],
        ];
        $orders = [];
        foreach ($plan as [$num, $ci, $lines, $ago, $target, $adv]) {
            $c = $cust[$ci]; $zone = $zones[$people[$ci][2]];
            $items = collect($lines)->map(fn ($l) => ['variant_id' => $this->v[$l[0]]->id, 'qty' => $l[1], 'unit_price' => $this->v[$l[0]]->fresh('product')->effective_price]);
            $sub = $items->sum(fn ($i) => $i['qty'] * $i['unit_price']); $total = $sub + $zone->charge; $advance = $adv === 'full' ? $total : 0;
            $at = now()->subDays($ago)->setTime(9 + $num % 10, 5 * ($num % 11));
            $o = Order::create(['number' => '#'.$num, 'customer_id' => $c->id, 'campaign_id' => $num % 3 ? $camp[$num % 3]->id : null, 'source' => $c->source, 'ship_name' => $c->name, 'ship_phone' => $c->phone, 'ship_address' => $c->address, 'delivery_zone_id' => $zone->id,
                'subtotal' => $sub, 'delivery_charge' => $zone->charge, 'total' => $total, 'advance' => $advance, 'cod' => $total - $advance, 'payment_status' => $advance ? 'paid' : 'pending', 'created_by' => $this->admin->id, 'placed_at' => $at, 'created_at' => $at]);
            foreach ($items as $i) $o->items()->create($i);
            $o->log('created', 'Order placed');
            $c->update(['orders_count' => $c->orders_count + 1, 'total_spent' => $c->total_spent + $total, 'last_order_at' => $at]);
            if ($advance) Transaction::create(['account_id' => Account::where('name', 'bKash')->value('id'), 'direction' => 'in', 'category' => 'Sales', 'amount' => $advance, 'description' => 'Advance · #'.$num, 'date' => $at->toDateString(), 'user_id' => $this->admin->id]);
            $orders[$num] = $this->progress($o, $target, $at, $wf, $alloc);
        }

        // ── returns R1001 … R1009 (R1005 = order #5 partial, refunded bKash ৳1,250 · R1009 refund due ৳1,360)
        $this->ret($orders[3], ['CGO-BLA-M' => ['good', 'Customer Changed Mind']], 'R1001', 10);
        $this->ret($orders[7], ['CGO-BLU-L' => ['damaged', 'Damaged']], 'R1002', 8);
        $r3 = $this->ret($orders[9], ['SDR-FLO-M' => ['good', 'Wrong Size']], 'R1003', 7, refund: 0);
        $this->ret($orders[1], ['CGO-BLU-M' => ['good', 'Wrong Size']], 'R1004', 7, manual: true);
        $r5 = $this->ret($orders[5], ['CGO-BLU-XL' => ['damaged', 'Damaged']], 'R1005', 6, manual: true, refund: 1250);   // keeps Blue M → NOT returned
        $this->refund($r5, 'bKash');
        $this->ret($orders[13], ['POL-WHI-M' => ['used', 'Product Defect']], 'R1006', 4, manual: true, refund: 0);
        $this->ret($orders[8], ['POL-NAV-M' => ['good', 'Wrong Color']], 'R1007', 3, manual: true, refund: 0);
        $this->ret($orders[6], ['DNJ-BLU-M' => [null, 'Wrong Size']], 'R1008', 2, manual: true, refund: 0);   // not inspected yet
        $this->ret($orders[15], ['DNJ-BLU-L' => ['good', 'Customer Changed Mind']], 'R1009', 1, manual: true, refund: 1360);   // refund due

        // ── supplier returns SR1001 … SR1004
        $sr = fn ($code, $supId, $items, $status, $resolution, $dates) => tap(SupplierReturn::create(['code' => $code, 'supplier_id' => $supId, 'status' => $status, 'resolution' => $resolution, 'step_dates' => $dates, 'resolved_at' => $status === 'resolved' ? now()->subDays(1) : null]), fn ($s) => collect($items)->each(fn ($i) => $s->items()->create($i)));
        $b = fn ($code) => Batch::where('code', $code)->first();
        $ri = fn ($rcode) => CustomerReturn::where('code', $rcode)->first()->items()->first();
        $i2 = $ri('R1002');
        $sr('SR1001', $sup[5]->id, [['batch_id' => $b('B003')->id, 'customer_return_item_id' => $i2->id, 'source_ref' => 'R1002', 'qty' => $i2->qty, 'unit_cost' => 500, 'reason' => 'Damaged']], 'resolved', 'replacement', ['pending' => today()->subDays(7)->toDateString(), 'sent' => today()->subDays(6)->toDateString(), 'received' => today()->subDays(4)->toDateString(), 'approved' => today()->subDays(3)->toDateString(), 'resolved' => today()->subDays(1)->toDateString()]);
        $b('B003')->decrement('damaged', $i2->qty); ProductVariant::whereKey($b('B003')->variant_id)->decrement('damaged', $i2->qty);   // sent back to the supplier
        app(StockService::class)->move($b('B003'), 'replacement', $i2->qty, SupplierReturn::where('code', 'SR1001')->first(), 'Replacement from supplier');
        $i5 = $ri('R1005');
        $sr('SR1002', $sup[2]->id, [['batch_id' => $b('B002')->id, 'customer_return_item_id' => $i5->id, 'source_ref' => 'R1005', 'qty' => 1, 'unit_cost' => 520, 'reason' => 'Damaged']], 'sent', 'replacement', ['pending' => today()->subDays(5)->toDateString(), 'sent' => today()->subDays(3)->toDateString()]);
        $b('B002')->decrement('damaged'); ProductVariant::whereKey($b('B002')->variant_id)->decrement('damaged');   // physically sent
        $i6 = $ri('R1006');
        $sr('SR1003', $sup[7]->id, [['batch_id' => $b('B006')->id, 'customer_return_item_id' => $i6->id, 'source_ref' => 'R1006', 'qty' => 1, 'unit_cost' => 260, 'reason' => 'Product Defect']], 'approved', 'credit', ['pending' => today()->subDays(4)->toDateString(), 'sent' => today()->subDays(3)->toDateString(), 'received' => today()->subDays(2)->toDateString(), 'approved' => today()->subDay()->toDateString()]);
        $b('B006')->decrement('damaged'); ProductVariant::whereKey($b('B006')->variant_id)->decrement('damaged');   // SR1003 already with the supplier
        app(StockService::class)->damage($b('B010'), 2);   // QC on arrival (PO-105)
        $sr('SR1004', $sup[9]->id, [['batch_id' => $b('B010')->id, 'source_ref' => 'QC-001', 'qty' => 2, 'unit_cost' => 820, 'reason' => 'Damaged on arrival']], 'pending', 'replacement', ['pending' => today()->subDays(2)->toDateString()]);

        // ── courier settlement (Steadfast) for some delivered parcels
        $st = Courier::where('code', 'steadfast')->first();
        $parcels = Shipment::where('courier_id', $st->id)->where('courier_status', 'delivered')->orderBy('delivered_at')->limit(3)->get();
        if ($parcels->isNotEmpty()) {
            $settle = CourierSettlement::create(['courier_id' => $st->id, 'reference' => 'SF-INV-2026-031', 'cod_total' => (int) $parcels->sum('cod_amount'), 'charges' => $parcels->count() * $st->charge, 'received' => (int) $parcels->sum('cod_amount') - $parcels->count() * $st->charge, 'received_on' => today()->subDays(2)]);
            Shipment::whereIn('id', $parcels->pluck('id'))->update(['settlement_id' => $settle->id]);
            Transaction::create(['account_id' => Account::where('name', 'Bank')->value('id'), 'direction' => 'in', 'category' => 'Courier payment', 'amount' => $settle->received, 'description' => 'Steadfast settlement · '.$parcels->count().' parcels', 'date' => today()->subDays(2), 'ref_type' => $settle->getMorphClass(), 'ref_id' => $settle->id, 'user_id' => $this->admin->id]);
        }

        // ── money: opening balances + expenses
        foreach ([['Cash', 'in', 'Other', 150000, 'Opening balance', 40], ['Bank', 'in', 'Other', 500000, 'Opening balance', 40], ['bKash', 'in', 'Other', 50000, 'Opening balance', 40], ['Nagad', 'in', 'Other', 20000, 'Opening balance', 40],
                  ['Cash', 'out', 'Packaging', 2400, 'Poly bags + tape', 6], ['bKash', 'out', 'Marketing', 6500, 'Facebook ads top-up', 4], ['Bank', 'out', 'Salary', 36000, 'Packer salaries', 3], ['Cash', 'out', 'Other', 900, 'Tea & snacks', 1]] as [$acc, $dir, $cat, $amt, $desc, $ago])
            Transaction::create(['account_id' => Account::where('name', $acc)->value('id'), 'direction' => $dir, 'category' => $cat, 'amount' => $amt, 'description' => $desc, 'date' => today()->subDays($ago), 'user_id' => $this->admin->id]);

        // ── courier entry sessions (hand-overs + return receipts → sessions)
        \App\Services\Ops\EntrySessions::backfill(); \App\Services\Ops\EntrySessions::demo();

        cache()->flush();
        $this->command?->info('Ops demo data: '.Batch::count().' batches, '.Order::count().' orders, '.CustomerReturn::count().' returns, '.SupplierReturn::count().' supplier returns');
    }

    /** move an order through the real workflow up to $target */
    protected function progress(Order $o, string $target, $at, OrderWorkflow $wf, BatchAllocator $alloc): Order
    {
        if (in_array($target, ['pending'])) return $o;
        if ($target === 'cancelled') { $wf->setStatus($o, 'confirmed'); $wf->setStatus($o->fresh(), 'cancelled'); return $o->fresh(); }
        $wf->setStatus($o, 'confirmed');
        if ($target === 'confirmed') return $o->fresh();
        if ($target === 'on_hold') { $wf->setStatus($o->fresh(), 'on_hold'); return $o->fresh(); }
        $wf->setStatus($o->fresh(), 'processing');
        if ($target === 'processing') return $o->fresh();
        $alloc->pack($o->fresh());
        $s = $o->fresh()->shipment; $s->update(['packed_at' => $at->copy()->addHours(4), 'tracking' => strtoupper(substr($s->courier->code, 0, 2)).(553000 + $o->id * 17), 'status_updated_at' => $at->copy()->addHours(4)]);
        if ($target === 'packed') return $o->fresh();
        $wf->setStatus($o->fresh(), 'shipped'); $s->refresh()->update(['handed_over_at' => $at->copy()->addHours(8)]);
        $wf->courierStatus($s->refresh(), 'in_transit', $s->courier->name);
        if ($target === 'shipped') { $s->update(['status_updated_at' => $at->copy()->addDay()]); return $o->fresh(); }
        if (in_array($target, ['delivered', 'partial_return'])) {
            $wf->courierStatus($s->refresh(), $target === 'partial_return' ? 'partial_delivered' : 'delivered', $s->courier->name);
            $s->update(['delivered_at' => $at->copy()->addDays(2), 'status_updated_at' => $at->copy()->addDays(2)]);
            return $o->fresh();   // COD sits with the courier until settlement
        }
        // returning / returned / returned_refund → parcel coming back
        $wf->courierStatus($s->refresh(), 'returning', $s->courier->name); $s->update(['status_updated_at' => $at->copy()->addDays(2)]);
        return $o->fresh();
    }

    /** receive a return (same rules as Return receive): good → original batch · damaged/used → damaged stock · null → not inspected */
    protected function ret(Order $o, array $lines, string $code, int $ago, bool $manual = false, ?int $refund = null): CustomerReturn
    {
        $o->load('items.variant', 'items.allocations.batch', 'shipment', 'customer');
        $s = $o->shipment; $stock = app(StockService::class);
        $returnedIds = $o->items->filter(fn ($i) => isset($lines[$i->variant->sku]))->pluck('id');
        $all = $returnedIds->count() === $o->items->count();
        $r = CustomerReturn::create(['code' => $code, 'order_id' => $o->id, 'shipment_id' => $s?->id, 'courier_id' => $s?->courier_id, 'session' => $manual ? null : 'RRN-'.now()->subDays($ago)->format('md').'-01',
            'type' => $all ? 'full' : 'partial', 'status' => 'inspected', 'received_by' => $this->admin->id, 'refund_amount' => $refund ?? max(0, $o->advance - $o->delivery_charge), 'created_at' => now()->subDays($ago)]);
        foreach ($o->items as $i) {
            if (! isset($lines[$i->variant->sku])) continue;   // kept by the customer
            [$cond, $reason] = $lines[$i->variant->sku];
            $allocs = $i->allocations->whereNotNull('packed_at');
            $r->items()->create(['order_item_id' => $i->id, 'batch_id' => $allocs->first()?->batch_id, 'qty' => $i->qty, 'reason' => $reason, 'condition' => $cond, 'action' => $cond ? config("ops.return_conditions.$cond.1") : null]);
            foreach ($allocs as $a) {
                if ($cond === 'good') $stock->move($a->batch, 'return_restock', $a->qty, $r);
                if (in_array($cond, ['damaged', 'used', 'broken'])) $stock->damage($a->batch, $a->qty, $r);
            }
            $i->increment('returned_qty', $i->qty);
        }
        $partial = ! $all;
        $o->update(['status' => $partial ? 'partially_delivered' : 'returned']); $o->log('return', 'Return '.$code.' received');
        if ($s && ! $partial && ! $manual) $s->update(['courier_status' => 'returned', 'status_updated_at' => now()->subDays($ago)]);
        if (! $manual) $o->customer->increment('returned_count');   // courier parcel came back (manual returns of delivered orders don't change the delivery rate)
        if ($manual && $s?->courier_status === 'partial_delivered') $o->customer->increment('delivered_count');
        return $r;
    }

    protected function refund(CustomerReturn $r, string $via): void
    {
        Transaction::create(['account_id' => Account::where('name', $via)->value('id'), 'direction' => 'out', 'category' => 'Refund', 'amount' => $r->refund_amount, 'description' => 'Refund · '.$r->code.' · '.$r->order->number, 'date' => $r->created_at->toDateString(), 'ref_type' => $r->getMorphClass(), 'ref_id' => $r->id, 'user_id' => $this->admin->id]);
        $r->update(['refunded_at' => $r->created_at, 'refund_via' => $via, 'status' => 'closed']);
        $r->order->update(['payment_status' => 'refunded']);
    }
}
