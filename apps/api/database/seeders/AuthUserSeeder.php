<?php

namespace Database\Seeders;

use App\Models\Dealer;
use App\Models\PriceList;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\OperationalUserRoster;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthUserSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $roles = collect(['admin', 'moderator', 'dealer_admin', 'salesperson', 'cashier', 'point', 'warehouse', 'customer'])
            ->mapWithKeys(fn (string $slug) => [
                $slug => Role::query()->firstOrCreate(
                    ['slug' => $slug],
                    ['name' => match ($slug) {
                        'admin' => 'Admin',
                        'moderator' => 'Moderatör',
                        'dealer_admin' => 'Bayi Yöneticisi',
                        'salesperson' => 'Plasiyer',
                        'cashier' => 'Kasiyer',
                        'point' => 'Point (Bayi)',
                        'warehouse' => 'Depo',
                        'customer' => 'Müşteri',
                        default => Str::headline(str_replace('_', ' ', $slug)),
                    }]
                )->id,
            ]);

        $defaultPriceListId = PriceList::query()
            ->where('code', 'A')
            ->value('id');

        $dealer = Dealer::updateOrCreate(
            ['code' => 'DLR-001'],
            [
                'name' => 'Powersa Dealer',
                'price_list_id' => $defaultPriceListId,
                'is_active' => true,
            ]
        );

        foreach (OperationalUserRoster::warehouses() as $warehouse) {
            Warehouse::query()->updateOrCreate(
                ['code' => $warehouse['code']],
                [
                    'name' => $warehouse['name'],
                    'is_active' => true,
                ]
            );
        }

        $seedPassword = (string) env('POWERSA_SEEDED_USER_PASSWORD', 'password');

        foreach (OperationalUserRoster::users() as $definition) {
            $roleSlugs = $definition['roles'];
            $isAdmin = in_array('admin', $roleSlugs, true);
            $user = User::query()->firstOrNew(['username' => $definition['username']]);

            $user->fill([
                'dealer_id' => $isAdmin ? null : $dealer->id,
                'customer_scope' => $isAdmin ? 'dealer' : $definition['customer_scope'],
                'region_code' => $isAdmin ? null : $definition['region_code'],
                'region_name' => $isAdmin ? null : $definition['region_name'],
                'branch_code' => $isAdmin ? null : $definition['branch_code'],
                'branch_name' => $isAdmin ? null : $definition['branch_name'],
                'logo_customer_specode4' => null,
                'logo_cashbox_code' => null,
                'logo_cashbox_name' => null,
                'selected_customer_id' => null,
                'name' => $definition['name'],
                'email' => null,
                'phone' => null,
                'is_active' => true,
                'menu_permissions' => $definition['menu_permissions'],
            ]);

            if (! $user->exists) {
                $user->password = Hash::make($seedPassword);
            }

            $user->save();

            $user->roles()->sync(
                collect($roleSlugs)
                    ->map(fn (string $slug): ?int => $roles[$slug] ?? null)
                    ->filter()
                    ->values()
                    ->all()
            );
        }
    }
}
