<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Support\CustomerFeaturePermissions;
use App\Support\MenuPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $username = mb_strtolower(trim((string) $request->input('username')));
        $password = (string) $request->input('password');
        $user = User::query()
            ->whereRaw('LOWER(username) = ?', [$username])
            ->first();

        if (! $user instanceof User || ! Hash::check($password, $user->password)) {
            return response()->json([
                'message' => 'Kullanıcı adı veya şifre hatalı.',
            ], HttpStatus::HTTP_UNPROCESSABLE_ENTITY);
        }

        Auth::login($user, false);

        if (! $user->is_active) {
            Auth::guard('web')->logout();

            return response()->json([
                'message' => 'Kullanıcı hesabı pasif.',
            ], HttpStatus::HTTP_FORBIDDEN);
        }

        $user = $this->clearSelectedCustomer($user);

        $request->session()->regenerate();

        return response()->json([
            'user' => $this->serializeAuthenticatedUser($user),
        ]);
    }

    public function logout(Request $request): Response
    {
        $this->clearSelectedCustomer($request->user());

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->serializeAuthenticatedUser($request->user()),
        ]);
    }

    private function serializeAuthenticatedUser(?User $user): ?User
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

    private function clearSelectedCustomer(?User $user): ?User
    {
        if (! $user instanceof User || $user->selected_customer_id === null) {
            return $user;
        }

        $user->forceFill([
            'selected_customer_id' => null,
        ])->save();

        return $user->fresh() ?? $user;
    }
}
