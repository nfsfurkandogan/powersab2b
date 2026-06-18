<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'menu_permissions')) {
                $table->json('menu_permissions')->nullable()->after('is_active');
            }
        });

        $allMenuPermissions = [
            'dashboard',
            'search',
            'catalogs',
            'cart',
            'orders',
            'customers',
            'new-customer-card',
            'ledger',
            'collections',
            'reports',
            'returns',
            'pos',
            'warehouse',
            'moderator',
            'extra',
        ];

        $users = DB::table('users')->select(['id'])->get();

        foreach ($users as $user) {
            $roleSlugs = DB::table('role_user')
                ->join('roles', 'roles.id', '=', 'role_user.role_id')
                ->where('role_user.user_id', $user->id)
                ->pluck('roles.slug')
                ->all();

            $permissions = [];

            if (in_array('admin', $roleSlugs, true)) {
                $permissions = $allMenuPermissions;
            } else {
                if (in_array('dealer_admin', $roleSlugs, true)) {
                    $permissions = array_merge($permissions, [
                        'dashboard',
                        'search',
                        'catalogs',
                        'cart',
                        'orders',
                        'returns',
                        'extra',
                    ]);
                }

                if (in_array('salesperson', $roleSlugs, true)) {
                    $permissions = array_merge($permissions, [
                        'dashboard',
                        'search',
                        'catalogs',
                        'cart',
                        'customers',
                        'new-customer-card',
                        'ledger',
                        'collections',
                        'reports',
                        'orders',
                        'returns',
                        'extra',
                    ]);
                }

                if (in_array('warehouse', $roleSlugs, true)) {
                    $permissions[] = 'warehouse';
                }

                if (in_array('moderator', $roleSlugs, true)) {
                    $permissions[] = 'moderator';
                }

                if (in_array('point', $roleSlugs, true) || in_array('cashier', $roleSlugs, true)) {
                    $permissions[] = 'pos';
                }
            }

            DB::table('users')
                ->where('id', $user->id)
                ->update(['menu_permissions' => json_encode(array_values(array_unique($permissions)))]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'menu_permissions')) {
                $table->dropColumn('menu_permissions');
            }
        });
    }
};
