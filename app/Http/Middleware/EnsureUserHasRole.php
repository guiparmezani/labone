<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * @param  string  ...$roles  Valores de App\Enums\Role
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $allowed = array_map(fn (string $role) => Role::from($role), $roles);

        if (! $user || ! in_array($user->role, $allowed, true)) {
            abort(403, 'Você não tem acesso a esta página.');
        }

        return $next($request);
    }
}
