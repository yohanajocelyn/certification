<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets a request through only when the logged-in user has the given role (route alias: role).
 */
class EnsureUserHasRole
{
    /**
     * Abort with 403 when the user's role is not the one the route requires.
     *
     * @param  string  $role  a UserRole value, e.g. "admin" in `role:admin`
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if ($request->user()?->role !== UserRole::from($role)) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
