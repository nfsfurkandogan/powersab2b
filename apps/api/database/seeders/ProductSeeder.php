<?php

namespace Database\Seeders;

use App\Models\BasePrice;
use App\Models\Brand;
use App\Models\Category;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\StockSummary;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $priceListId = PriceList::query()
            ->where('code', 'A')
            ->value('id');

        if ($priceListId === null) {
            return;
        }

        $brands = [
            'BOSCH',
            'DONALDSON',
            'FLEETGUARD',
            'HENGST',
            'MANN',
            'WIX',
        ];

        $brandIdByName = [];
        foreach ($brands as $brandName) {
            $brand = Brand::query()->updateOrCreate(
                ['slug' => Str::slug($brandName)],
                ['name' => $brandName, 'is_active' => true]
            );
            $brandIdByName[$brandName] = (int) $brand->id;
        }

        $rootCategory = Category::query()->updateOrCreate(
            ['slug' => 'filtre'],
            ['name' => 'Filtre', 'parent_id' => null, 'is_active' => true]
        );

        $childCategories = [
            'yag-filtresi' => 'Yag Filtresi',
            'yakit-filtresi' => 'Yakit Filtresi',
            'hava-filtresi' => 'Hava Filtresi',
            'polen-filtresi' => 'Polen Filtresi',
        ];

        $categoryIdBySlug = [];
        foreach ($childCategories as $slug => $name) {
            $category = Category::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'parent_id' => $rootCategory->id, 'is_active' => true]
            );
            $categoryIdBySlug[$slug] = (int) $category->id;
        }

        $rows = [
            ['sku' => '4514S4E001', 'oem' => '0986AF1010', 'brand' => 'DONALDSON', 'category' => 'yag-filtresi', 'name' => 'S4E G4508 AGRO INO 100 C3 FIH SMART TUYLU', 'price' => 769.00, 'stock' => 20, 'kod2' => 'YAG'],
            ['sku' => 'BAL-PC0110', 'oem' => null, 'brand' => 'DONALDSON', 'category' => 'yakit-filtresi', 'name' => 'ZF BCC RENAULT SANTRIFUJ YAG FILTRESI', 'price' => 432.00, 'stock' => 20, 'kod2' => 'YAKIT'],
            ['sku' => 'BAL-PS7699', 'oem' => null, 'brand' => 'DONALDSON', 'category' => 'yakit-filtresi', 'name' => 'PERKINS YAKIT FILTRESI', 'price' => 519.00, 'stock' => 20, 'kod2' => 'YAKIT'],
            ['sku' => 'BOS-0450900477', 'oem' => 'P550784', 'brand' => 'BOSCH', 'category' => 'yakit-filtresi', 'name' => 'MAZOT FILTRESI MERCEDES W123 W124 200D UMB30 1993', 'price' => 575.00, 'stock' => 20, 'kod2' => 'YAKIT'],
            ['sku' => 'BOS-0450902622', 'oem' => 'WK842/3', 'brand' => 'BOSCH', 'category' => 'yakit-filtresi', 'name' => 'MAZOT FILTRESI NEW CASES 580 SUPERMIMO 91-2017', 'price' => 625.00, 'stock' => 20, 'kod2' => 'YAKIT'],
            ['sku' => 'BOS-0450903622', 'oem' => 'WK853/12', 'brand' => 'BOSCH', 'category' => 'polen-filtresi', 'name' => 'MAZOT FILTRESI VOLVO C30 C70 C60 C4D A3 B7', 'price' => 266.00, 'stock' => 20, 'kod2' => 'POLEN'],
            ['sku' => 'BOS-0450904173', 'oem' => 'WK854/3', 'brand' => 'BOSCH', 'category' => 'yag-filtresi', 'name' => 'MAZOT FILTRESI FORD FIESTA 95 1.4 TDDI FOCUS 98', 'price' => 603.00, 'stock' => 20, 'kod2' => 'YAG'],
            ['sku' => 'BOS-0450904227', 'oem' => 'WK854/7', 'brand' => 'BOSCH', 'category' => 'yakit-filtresi', 'name' => 'MAZOT FILTRESI AUDI A4 A6 A8 1.9 TDI 1995-2004', 'price' => 578.00, 'stock' => 20, 'kod2' => 'YAKIT'],
            ['sku' => 'BOS-0450904947', 'oem' => 'WK853/3', 'brand' => 'BOSCH', 'category' => 'yakit-filtresi', 'name' => 'MAZOT FILTRESI IVECO DAILY 2.8 JTD 1999-2006', 'price' => 703.00, 'stock' => 20, 'kod2' => 'YAKIT'],
            ['sku' => 'BOS-0450906690', 'oem' => 'WK829/2', 'brand' => 'BOSCH', 'category' => 'yakit-filtresi', 'name' => 'MAZOT FILTRESI TOYOTA COROLLA 1.4 D4D 2004-2013', 'price' => 763.00, 'stock' => 20, 'kod2' => 'YAKIT'],
            ['sku' => 'BOS-0450906948', 'oem' => 'WK939/12', 'brand' => 'BOSCH', 'category' => 'yakit-filtresi', 'name' => 'MAZOT FILTRESI OPEL INSIGNIA 2.0 CDTI 2009-2017', 'price' => 728.00, 'stock' => 20, 'kod2' => 'YAKIT'],
            ['sku' => 'BOS-0450907166', 'oem' => 'WK640/8', 'brand' => 'BOSCH', 'category' => 'hava-filtresi', 'name' => 'MAZOT FILTRESI CUMMINS 5.9D SENSORLU TP', 'price' => 927.00, 'stock' => 20, 'kod2' => 'HAVA'],
            ['sku' => 'BOS-0451030336', 'oem' => 'WF7156', 'brand' => 'BOSCH', 'category' => 'yag-filtresi', 'name' => 'YAG FILTRESI FIAT DOBLO CARGO 1.3 JTD', 'price' => 210.00, 'stock' => 20, 'kod2' => 'YAG'],
            ['sku' => 'BOS-0451030138', 'oem' => 'W719/5', 'brand' => 'BOSCH', 'category' => 'yag-filtresi', 'name' => 'YAG FILTRESI HYUNDAI ACCENT 1.3 1.5 1998+', 'price' => 246.00, 'stock' => 20, 'kod2' => 'YAG'],
            ['sku' => 'BOS-0451103316', 'oem' => 'W712/52', 'brand' => 'BOSCH', 'category' => 'yag-filtresi', 'name' => 'YAG FILTRESI GOLF VOLVO V8 FORD APOLLO 8V', 'price' => 161.00, 'stock' => 20, 'kod2' => 'YAG'],
            ['sku' => 'FL-PP9812', 'oem' => 'LF17503', 'brand' => 'FLEETGUARD', 'category' => 'yag-filtresi', 'name' => 'CUMMINS ISBE 4.5 YAG FILTRESI', 'price' => 448.00, 'stock' => 20, 'kod2' => 'YAG'],
            ['sku' => 'MANN-WK11029X', 'oem' => 'WK11029X', 'brand' => 'MANN', 'category' => 'yakit-filtresi', 'name' => 'MAZOT FILTRESI RENAULT MASTER 2.3 DCI', 'price' => 692.00, 'stock' => 20, 'kod2' => 'YAKIT'],
            ['sku' => 'WIX-WL7480', 'oem' => 'WL7480', 'brand' => 'WIX', 'category' => 'hava-filtresi', 'name' => 'HAVA FILTRESI DAF CF EURO 6', 'price' => 538.00, 'stock' => 20, 'kod2' => 'HAVA'],
        ];

        foreach ($rows as $row) {
            $brandId = $brandIdByName[$row['brand']] ?? null;
            $categoryId = $categoryIdBySlug[$row['category']] ?? null;

            if ($brandId === null || $categoryId === null) {
                continue;
            }

            $product = Product::query()->updateOrCreate(
                ['sku' => $row['sku']],
                [
                    'brand_id' => $brandId,
                    'category_id' => $categoryId,
                    'oem_code' => $row['oem'],
                    'name' => $row['name'],
                    'description' => null,
                    'unit' => 'adet',
                    'vat_rate' => 20.00,
                    'weight_kg' => null,
                    'is_active' => true,
                    'meta' => [
                        'source' => 'sample-excel-format-seed',
                        'kod1' => 'FILTRE',
                        'kod2' => $row['kod2'],
                        'kod3' => 'SPIN-ON',
                        'stok_turu' => 'T',
                    ],
                ]
            );

            StockSummary::query()->updateOrCreate(
                ['product_id' => $product->id],
                [
                    'available_total' => (int) $row['stock'],
                    'reserved_total' => 0,
                    'updated_at' => now(),
                ]
            );

            BasePrice::query()->updateOrCreate(
                [
                    'price_list_id' => $priceListId,
                    'product_id' => $product->id,
                ],
                [
                    'list_price' => number_format((float) $row['price'], 2, '.', ''),
                    'currency' => 'TRY',
                    'updated_at' => now(),
                ]
            );
        }
    }
}
