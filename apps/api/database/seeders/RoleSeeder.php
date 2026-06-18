<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Role::updateOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Admin']
        );

        Role::updateOrCreate(
            ['slug' => 'dealer_admin'],
            ['name' => 'Bayi Yöneticisi']
        );

        Role::updateOrCreate(
            ['slug' => 'moderator'],
            ['name' => 'Moderatör']
        );

        Role::updateOrCreate(
            ['slug' => 'salesperson'],
            ['name' => 'Plasiyer']
        );

        Role::updateOrCreate(
            ['slug' => 'cashier'],
            ['name' => 'Kasiyer']
        );

        Role::updateOrCreate(
            ['slug' => 'point'],
            ['name' => 'Point (Bayi)']
        );

        Role::updateOrCreate(
            ['slug' => 'warehouse'],
            ['name' => 'Depo']
        );

        Role::updateOrCreate(
            ['slug' => 'customer'],
            ['name' => 'Müşteri']
        );
    }
}
