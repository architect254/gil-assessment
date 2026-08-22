<?php

namespace Database\Seeders;

use App\Models\Item;
use Illuminate\Database\Seeder;

class ItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['code' => 'I001', 'description' => 'Steel Nail 2 inch (Kg)', 'unit_price' => 180.500],
            ['code' => 'I002', 'description' => 'Cement Bag 50Kg', 'unit_price' => 750.000],
            ['code' => 'I003', 'description' => 'Corrugated Iron Sheet 3m', 'unit_price' => 1250.990],
            ['code' => 'I004', 'description' => 'Paint White 20L', 'unit_price' => 4300.000],
            ['code' => 'I005', 'description' => 'PVC Pipe 4 inch (6m)', 'unit_price' => 890.250],
            ['code' => 'I006', 'description' => 'Electric Cable 2.5mm (100m)', 'unit_price' => 5600.000],
            ['code' => 'I007', 'description' => 'Door Lock Brass', 'unit_price' => 1450.750],
            ['code' => 'I008', 'description' => 'Ceramic Tile 60x60 (m2)', 'unit_price' => 1600.000],
            ['code' => 'I009', 'description' => 'Window Glass 4mm (m2)', 'unit_price' => 950.500],
            ['code' => 'I010', 'description' => 'Timber Board 2x4x14ft', 'unit_price' => 1100.000],
        ];

        foreach ($items as $item) {
            Item::query()->updateOrCreate(['code' => $item['code']], $item);
        }
    }
}
