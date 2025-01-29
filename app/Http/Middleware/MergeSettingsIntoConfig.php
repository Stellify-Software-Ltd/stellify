<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Setting;

class MergeSettingsIntoConfig
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $allSettings = Setting::where(['active_domain' => $request->root()])->get();
        if (!empty($allSettings)) {
            foreach($allSettings as $setting) {
                if (!empty($setting->name)) {
                    $settingsData = json_decode($setting->data, 'true');
                    foreach($settingsData as $key => $value) {
                        config([$setting->name . '.' . $key => $value]);
                    }
                }
            }
        }
        return $next($request);
    }
}
