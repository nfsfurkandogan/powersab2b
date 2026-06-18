<?php

namespace Database\Seeders;

use App\Models\PriceList;
use Illuminate\Database\Seeder;

class PriceListSeeder extends Seeder
{
    public function run(): void
    {
        PriceList::updateOrCreate(
            ['code' => 'A'],
            ['name' => 'List A', 'discount_rate' => 0, 'is_active' => true]
        );

        PriceList::updateOrCreate(
            ['code' => 'B'],
            ['name' => 'List B', 'discount_rate' => 0, 'is_active' => true]
        );

        PriceList::updateOrCreate(
            ['code' => 'C'],
            ['name' => 'List C', 'discount_rate' => 0, 'is_active' => true]
        );
    }
}
