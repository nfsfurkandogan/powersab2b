<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'username')) {
                $table->string('username', 64)->nullable()->after('name');
            }
        });

        $used = [];

        DB::table('users')
            ->select(['id', 'name', 'email', 'username'])
            ->orderBy('id')
            ->get()
            ->each(function ($user) use (&$used): void {
                $existing = $this->normalizeUsername($user->username ?? null);

                if ($existing !== null) {
                    $used[$existing] = true;

                    return;
                }

                $source = explode('@', (string) ($user->email ?? ''))[0] ?: (string) ($user->name ?? '');
                $base = $this->normalizeUsername($source) ?? 'kullanici';
                $username = $base;
                $suffix = 1;

                while (isset($used[$username])) {
                    $suffix++;
                    $username = $base.'.'.$suffix;
                }

                $used[$username] = true;

                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['username' => $username]);
            });

        Schema::table('users', function (Blueprint $table): void {
            $table->unique('username', 'users_username_unique');
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('email')->nullable(false)->change();
            $table->dropUnique('users_username_unique');
            $table->dropColumn('username');
        });
    }

    private function normalizeUsername(mixed $value): ?string
    {
        $normalized = Str::of((string) $value)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9._-]+/', '.')
            ->replaceMatches('/[.]{2,}/', '.')
            ->trim('.-_')
            ->toString();

        return $normalized !== '' ? Str::limit($normalized, 64, '') : null;
    }
};
