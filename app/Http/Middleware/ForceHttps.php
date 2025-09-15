<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Auth;

class ForceHttps
{
    public function handle(Request $request, Closure $next)
    {
        $settings = app('settings');
        if (!app()->environment('local') && !empty($settings['app']) && !empty($settings['app']['force_https'])) {
            URL::forceScheme('https');
        }
        return $next($request);
    }
}