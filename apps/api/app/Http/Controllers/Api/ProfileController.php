<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\CustomerFeaturePermissions;
use App\Support\MenuPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'username' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($user->id)],
            'avatar_url' => ['nullable', 'string', 'max:800000'],
            'current_password' => ['nullable', 'string', 'max:255'],
            'new_password' => ['nullable', 'string', 'max:255', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);

        $usernameChanged = $validated['username'] !== $user->username;
        $passwordChanged = filled($validated['new_password'] ?? null);

        if (($usernameChanged || $passwordChanged) && ! Hash::check((string) ($validated['current_password'] ?? ''), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Mevcut şifre hatalı.'],
            ]);
        }

        $user->username = $validated['username'];
        $user->avatar_url = $validated['avatar_url'] ?? null;

        if ($passwordChanged) {
            $user->password = Hash::make((string) $validated['new_password']);
        }

        $user->save();

        return response()->json([
            'user' => $this->serializeUser($user->fresh()),
        ]);
    }

    private function serializeUser(?User $user): ?User
    {
        if (! $user instanceof User) {
            return null;
        }

        $user->load([
            'roles',
            'dealer',
            'selectedCustomer:id,dealer_id,salesperson_user_id,region_code,region_name,branch_code,branch_name,source_system,source_reference,code,name,contact_name,email,city,district,phone,tax_office,tax_number,credit_limit,is_active,meta,last_synced_at',
            'selectedCustomer.salesperson:id,name,email,phone,avatar_url',
        ]);

        $user->setAttribute('menu_permissions', MenuPermissions::forUser($user));
        $user->setAttribute('feature_permissions', CustomerFeaturePermissions::forUser($user));

        return $user;
    }
}
