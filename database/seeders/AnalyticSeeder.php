<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Analytic;

class AnalyticSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Analytic::create([
            'slug' => 'google-analytics',
        ]);

        Analytic::create([
            'slug' => 'facebook-pixels',
        ]);
    }
}
