<?php

namespace Database\Seeders;

use App\Models\{Account, Courier, DeliveryZone, Warehouse};
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        Warehouse::firstOrCreate(['name' => 'Mirpur WH'], ['is_default' => true]);
        foreach (['Cash', 'Bank', 'bKash', 'Nagad'] as $a) Account::firstOrCreate(['name' => $a]);
        foreach ([['Inside Dhaka city', 80, '1–2 days'], ['Inside Dhaka Suburbs', 120, '2–3 days'], ['Outside Dhaka', 140, '3–5 days']] as [$n, $c, $e]) DeliveryZone::firstOrCreate(['name' => $n], ['charge' => $c, 'eta' => $e]);
        foreach ([['Steadfast', 'steadfast'], ['Pathao', 'pathao'], ['RedX', 'redx']] as [$n, $c]) Courier::firstOrCreate(['code' => $c], ['name' => $n, 'webhook_secret' => str()->random(32)]);
    }
}
