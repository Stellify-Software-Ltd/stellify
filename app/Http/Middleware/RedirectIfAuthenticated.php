<?php

namespace App\Http\Middleware;

use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next, ...$guards)
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                // Allow authenticated users on auth routes (they're customizing)
                $authRoutes = ['register', 'login'];
                $currentPath = trim($request->path(), '/');
                
                if (in_array($currentPath, $authRoutes)) {
                    // Let authenticated users through to access their custom auth pages
                    return $next($request);
                }
                
                // For all other routes, redirect as normal
                return redirect(RouteServiceProvider::HOME);
            }
        }

        return $next($request);
    }
}
