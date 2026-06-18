<?php

namespace App\Console\Commands\Users;

use App\Models\Customer;
use App\Models\Dealer;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\OperationalUserRoster;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SyncOperationalUsersCommand extends Command
{
    protected $signature = 'users:sync-operational
        {--password= : Yeni kullanicilar icin ortak sifre}
        {--password-map-json= : Username/password JSON objesi. Ornek: {"user":"Pass@123"}}
        {--password-map-file= : Username/password JSON dosya yolu}
        {--reset-passwords : Listedeki admin disi kullanicilarin sifresini de guncelle}
        {--prune=delete : Liste disi kullanicilar icin keep, deactivate veya delete}
        {--dry-run : Degisiklikleri yazmadan sadece raporla}';

    protected $description = 'Powersa operasyon kullanici listesini hedef kadroya gore senkronize eder';

    public function handle(): int
    {
        $pruneMode = (string) $this->option('prune');
        if (! in_array($pruneMode, ['keep', 'deactivate', 'delete'], true)) {
            $this->error('--prune degeri keep, deactivate veya delete olmali.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $sharedPassword = $this->normalizedPasswordOption();
        $passwordMap = $this->normalizedPasswordMapOptions();
        $resetPasswords = (bool) $this->option('reset-passwords');
        $passwordOnlyMode = $passwordMap !== [] && $sharedPassword === null && ! $resetPasswords;
        $generatedPasswords = [];

        $summary = DB::transaction(function () use ($pruneMode, $dryRun, $sharedPassword, $passwordMap, $resetPasswords, $passwordOnlyMode, &$generatedPasswords): array {
            $roles = $this->ensureRoles($dryRun);
            $dealer = $this->resolveDealer($dryRun);
            $this->ensureWarehouses($dryRun);

            $created = 0;
            $updated = 0;

            foreach (OperationalUserRoster::users() as $definition) {
                $username = $definition['username'];
                if ($passwordOnlyMode && ! array_key_exists($username, $passwordMap)) {
                    continue;
                }

                $user = User::query()->where('username', $username)->first();
                $isNew = ! $user instanceof User;
                $password = null;

                if (array_key_exists($username, $passwordMap)) {
                    $password = $passwordMap[$username];
                    $generatedPasswords[$username] = '[provided]';
                } elseif ($isNew) {
                    $password = $sharedPassword ?? Str::password(20, symbols: true);
                    $generatedPasswords[$username] = $password;
                } elseif ($resetPasswords && $username !== 'admin') {
                    $password = $sharedPassword ?? Str::password(20, symbols: true);
                    $generatedPasswords[$username] = $password;
                }

                if (! $dryRun) {
                    if ($passwordOnlyMode && $user instanceof User && $password !== null) {
                        $user->forceFill(['password' => Hash::make($password)])->save();
                    } else {
                        $user = $this->upsertRosterUser($definition, $roles, $dealer, $password);
                    }
                }

                $isNew ? $created++ : $updated++;
            }

            $pruneSummary = $passwordOnlyMode
                ? ['deleted' => 0, 'deactivated' => 0, 'kept' => 0]
                : $this->pruneUsers($pruneMode, $dryRun);

            return [
                'created' => $created,
                'updated' => $updated,
                ...$pruneSummary,
            ];
        });

        $this->table(
            ['Created', 'Updated', 'Deleted', 'Deactivated', 'Kept', 'Dry Run'],
            [[
                $summary['created'],
                $summary['updated'],
                $summary['deleted'],
                $summary['deactivated'],
                $summary['kept'],
                $dryRun ? 'yes' : 'no',
            ]]
        );

        if ($generatedPasswords !== []) {
            $this->warn('Olusturulan/guncellenen sifreler:');
            $this->table(
                ['Username', 'Password'],
                collect($generatedPasswords)
                    ->map(fn (string $password, string $username): array => [$username, $password])
                    ->values()
                    ->all()
            );
        }

        if ($summary['deactivated'] > 0 && $pruneMode === 'delete') {
            $this->warn('Operasyon kaydi olan bazi liste disi kullanicilar fiziksel silinemedi, pasife alindi.');
        }

        $this->info('Operasyon kullanici senkronizasyonu tamamlandi.');

        return self::SUCCESS;
    }

    private function normalizedPasswordOption(): ?string
    {
        $password = $this->option('password');

        if (! is_string($password)) {
            return null;
        }

        $password = trim($password);

        return $password !== '' ? $password : null;
    }

    /**
     * @return array<string, string>
     */
    private function normalizedPasswordMapOptions(): array
    {
        $rawJson = $this->option('password-map-json');
        $filePath = $this->option('password-map-file');

        if (is_string($filePath) && trim($filePath) !== '') {
            $path = trim($filePath);
            if (! is_file($path) || ! is_readable($path)) {
                throw new \InvalidArgumentException('--password-map-file okunamadi.');
            }

            $rawJson = file_get_contents($path);
        }

        if (! is_string($rawJson) || trim($rawJson) === '') {
            return [];
        }

        $decoded = json_decode($rawJson, true);
        if (! is_array($decoded)) {
            throw new \InvalidArgumentException('--password-map-json gecersiz JSON.');
        }

        $map = [];
        foreach ($decoded as $username => $password) {
            if (! is_string($username) || ! is_string($password)) {
                continue;
            }

            $normalizedUsername = mb_strtolower(trim($username), 'UTF-8');
            $normalizedPassword = trim($password);

            if ($normalizedUsername === '' || $normalizedPassword === '') {
                continue;
            }

            $map[$normalizedUsername] = $normalizedPassword;
        }

        return $map;
    }

    /**
     * @return array<string, Role>
     */
    private function ensureRoles(bool $dryRun): array
    {
        $roleNames = [
            'admin' => 'Admin',
            'dealer_admin' => 'Bayi Yöneticisi',
            'salesperson' => 'Plasiyer',
            'point' => 'Point (Bayi)',
            'warehouse' => 'Depo',
            'moderator' => 'Moderatör',
            'cashier' => 'Kasiyer',
            'customer' => 'Müşteri',
        ];

        $roles = [];

        foreach ($roleNames as $slug => $name) {
            $roles[$slug] = $dryRun
                ? (Role::query()->firstOrNew(['slug' => $slug], ['name' => $name]))
                : Role::query()->updateOrCreate(['slug' => $slug], ['name' => $name]);
        }

        return $roles;
    }

    private function resolveDealer(bool $dryRun): ?Dealer
    {
        $dealer = Dealer::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if ($dealer instanceof Dealer || $dryRun) {
            return $dealer;
        }

        return Dealer::query()->updateOrCreate(
            ['code' => 'DLR-001'],
            [
                'name' => 'Powersa Dealer',
                'is_active' => true,
            ]
        );
    }

    private function ensureWarehouses(bool $dryRun): void
    {
        if ($dryRun) {
            return;
        }

        foreach (OperationalUserRoster::warehouses() as $warehouse) {
            Warehouse::query()->updateOrCreate(
                ['code' => $warehouse['code']],
                [
                    'name' => $warehouse['name'],
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, Role>  $roles
     */
    private function upsertRosterUser(array $definition, array $roles, ?Dealer $dealer, ?string $password): User
    {
        $roleSlugs = $definition['roles'];
        $isAdmin = in_array('admin', $roleSlugs, true);

        $payload = [
            'dealer_id' => $isAdmin ? null : $dealer?->id,
            'customer_scope' => $isAdmin ? 'dealer' : $definition['customer_scope'],
            'region_code' => $isAdmin ? null : $definition['region_code'],
            'region_name' => $isAdmin ? null : $definition['region_name'],
            'branch_code' => $isAdmin ? null : $definition['branch_code'],
            'branch_name' => $isAdmin ? null : $definition['branch_name'],
            'logo_customer_specode4' => $definition['logo_customer_specode4'] ?? null,
            'logo_cashbox_code' => null,
            'logo_cashbox_name' => null,
            'selected_customer_id' => null,
            'name' => $definition['name'],
            'email' => null,
            'phone' => null,
            'is_active' => true,
            'menu_permissions' => $definition['menu_permissions'],
        ];

        if ($password !== null) {
            $payload['password'] = Hash::make($password);
        }

        if (! User::query()->where('username', $definition['username'])->exists() && $password === null) {
            $payload['password'] = Hash::make(Str::password(20, symbols: true));
        }

        $user = User::query()->updateOrCreate(
            ['username' => $definition['username']],
            $payload
        );

        $roleIds = collect($roleSlugs)
            ->map(fn (string $slug): ?int => $roles[$slug]->id)
            ->filter()
            ->values()
            ->all();

        $user->roles()->sync($roleIds);

        return $user;
    }

    /**
     * @return array{deleted:int,deactivated:int,kept:int}
     */
    private function pruneUsers(string $mode, bool $dryRun): array
    {
        $summary = [
            'deleted' => 0,
            'deactivated' => 0,
            'kept' => 0,
        ];

        if ($mode === 'keep') {
            $summary['kept'] = User::query()
                ->whereNotIn('username', OperationalUserRoster::usernames())
                ->count();

            return $summary;
        }

        $users = User::query()
            ->whereNotIn('username', OperationalUserRoster::usernames())
            ->orderBy('id')
            ->get();

        foreach ($users as $user) {
            if ($mode === 'deactivate' || $this->hasDeleteBlockingRecords($user)) {
                $summary['deactivated']++;

                if (! $dryRun) {
                    $this->deactivateUser($user);
                }

                continue;
            }

            $summary['deleted']++;

            if (! $dryRun) {
                $user->delete();
            }
        }

        return $summary;
    }

    private function hasDeleteBlockingRecords(User $user): bool
    {
        return DB::table('pos_sessions')->where('opened_by', $user->id)->exists()
            || DB::table('pos_sales')->where('created_by', $user->id)->exists()
            || DB::table('shipments')->where('created_by', $user->id)->exists()
            || DB::table('shipment_scans')->where('scanned_by', $user->id)->exists()
            || DB::table('customer_card_requests')->where('requested_by_user_id', $user->id)->exists()
            || DB::table('customer_card_requests')->where('reviewed_by_user_id', $user->id)->exists()
            || DB::table('customer_card_requests')->where('converted_by_user_id', $user->id)->exists()
            || DB::table('customer_card_request_attachments')->where('uploaded_by_user_id', $user->id)->exists()
            || DB::table('return_requests')->where('requested_by_user_id', $user->id)->exists()
            || DB::table('return_requests')->where('reviewed_by_user_id', $user->id)->exists();
    }

    private function deactivateUser(User $user): void
    {
        Customer::query()
            ->where('salesperson_user_id', $user->id)
            ->update(['salesperson_user_id' => null]);

        $user->forceFill([
            'selected_customer_id' => null,
            'is_active' => false,
        ])->save();
    }
}
