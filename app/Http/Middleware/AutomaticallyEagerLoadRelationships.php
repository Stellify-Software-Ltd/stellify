<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;

class AutomaticallyEagerLoadRelationships
{
    public function handle(Request $request, Closure $next)
    {
        $settings = app('settings');
        if (!empty($settings['app']) && !empty($settings['app']['automatically_eager_load_relationships'])) {
            Model::automaticallyEagerLoadRelationships();
        }
        return $next($request);
    }
}