<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Dealer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class PosPointCustomerSeeder extends Seeder
{
    private const POINT_CUSTOMERS = [
        [
            'code' => 'POINT-NAKIT',
            'name' => 'ERZURUM POINT NAKIT SATIS',
            'sale_type' => 'cash',
        ],
        [
            'code' => 'POINT-KREDI-KARTI',
            'name' => 'ERZURUM POINT KREDI KARTI SATIS',
            'sale_type' => 'card',
        ],
        [
            'code' => 'POINT-CARISI-OLMAYAN',
            'name' => 'CARİSİ OLMAYAN HIZLI SATIŞ',
            'sale_type' => null,
            'anonymous_sale' => true,
        ],
    ];

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $dealers = $this->resolvePosDealers();

        foreach ($dealers as $dealer) {
            foreach (self::POINT_CUSTOMERS as $pointCustomer) {
                Customer::query()->updateOrCreate(
                    [
                        'dealer_id' => $dealer->id,
                        'code' => $pointCustomer['code'],
                    ],
                    [
                        'name' => $pointCustomer['name'],
                        'contact_name' => 'POINT POS',
                        'email' => null,
                        'phone' => null,
                        'city' => 'Erzurum',
                        'district' => 'Merkez',
                        'tax_office' => $dealer->tax_office,
                        'tax_number' => $dealer->tax_number,
                        'credit_limit' => 0,
                        'is_active' => true,
                        'meta' => [
                            'source' => 'pos_point_customer_seed',
                            'dealer_code' => $dealer->code,
                            'pos_default' => true,
                            'sale_type' => $pointCustomer['sale_type'] ?? null,
                            'anonymous_sale' => (bool) ($pointCustomer['anonymous_sale'] ?? false),
                        ],
                    ]
                );
            }
        }
    }

    /**
     * @return Collection<int, Dealer>
     */
    private function resolvePosDealers(): Collection
    {
        $posDealers = Dealer::query()
            ->where('is_active', true)
            ->whereHas('users.roles', function ($query) {
                $query->whereIn('slug', ['dealer_admin', 'cashier', 'point']);
            })
            ->get();

        if ($posDealers->isNotEmpty()) {
            return $posDealers;
        }

        $activeDealers = Dealer::query()
            ->where('is_active', true)
            ->get();

        if ($activeDealers->isNotEmpty()) {
            return $activeDealers;
        }

        return collect([
            Dealer::query()->firstOrCreate(
                ['code' => 'DLR-001'],
                ['name' => 'Powersa Demo Dealer', 'is_active' => true]
            ),
        ]);
    }
}
