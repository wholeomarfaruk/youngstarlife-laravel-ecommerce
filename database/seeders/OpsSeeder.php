<?php

namespace Database\Seeders;

use App\Models\Ops\{Account, Courier, DeliveryZone, Warehouse};
use Illuminate\Database\Seeder;

/** Ops panel base data (safe to re-run): warehouses, accounts, couriers, delivery zones. */
class OpsSeeder extends Seeder
{
    public function run(): void
    {
        Warehouse::firstOrCreate(['name' => 'Mirpur WH'], ['is_default' => true, 'address' => 'Mirpur 10, Dhaka']);
        Warehouse::firstOrCreate(['name' => 'Uttara WH'], ['is_default' => false, 'address' => 'Sector 7, Uttara, Dhaka']);
        foreach (config('ops.accounts') as $a) Account::firstOrCreate(['name' => $a]);
        foreach ([['Steadfast', 'steadfast', 60], ['Pathao', 'pathao', 70], ['RedX', 'redx', 65]] as [$n, $c, $charge]) Courier::firstOrCreate(['code' => $c], ['name' => $n, 'charge' => $charge, 'webhook_secret' => str()->random(32)]);
        foreach ([['Inside Dhaka city', 80, '1–2 days', 'steadfast', ['Mirpur', 'Dhanmondi', 'Gulshan', 'Uttara', 'Mohammadpur', 'Banani']], ['Inside Dhaka Suburbs', 120, '2–3 days', null, ['Savar', 'Keraniganj', 'Tongi', 'Narayanganj']], ['Outside Dhaka', 140, '3–5 days', 'pathao', ['Chattogram', 'Sylhet', 'Khulna', 'Rajshahi', 'Gazipur']]] as [$n, $c, $e, $courier, $areas])
            DeliveryZone::updateOrCreate(['name' => $n], ['charge' => $c, 'eta' => $e, 'default_courier' => $courier, 'areas' => $areas, 'is_active' => true]);
        foreach (Courier::all() as $c) if (! $c->default_zone_id) $c->update(['default_zone_id' => DeliveryZone::where('default_courier', $c->code)->value('id')]);
    }
}
