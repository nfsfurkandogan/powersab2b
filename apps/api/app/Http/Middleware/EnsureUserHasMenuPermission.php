<?php

namespace App\Http\Middleware;

use App\Support\MenuPermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasMenuPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(Response::HTTP_UNAUTHORIZED, 'Unauthenticated.');
        }

        if ($user->hasRole('admin')) {
            return $next($request);
        }

        $requestedPermissions = MenuPermissions::normalize($permissions);
        $userPermissions = MenuPermissions::forUser($user);

        if ($requestedPermissions === [] || array_intersect($requestedPermissions, $userPermissions) === []) {
            abort(Response::HTTP_FORBIDDEN, 'Bu menü için yetkiniz yok.');
        }

        return $next($request);
    }
}
