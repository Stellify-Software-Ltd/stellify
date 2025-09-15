<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Setting;
use Illuminate\Http\Request;
//use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('settings', function ($app) {
            try {
                DB::connection()->getPdo();

                $request = $app->make(Request::class);
                $settings = null;
                $extractedSettings = [];
                if ($request->root() == 'http://localhost' || $request->root() == 'https://stellisoft.com') {
                    if (Auth::id()) {
                        $settings = Setting::where([
                            'project_id' => Auth::user()->project_id,
                            'active_domain' => $request->root()
                        ])->get();
                    } else {
                        $settings = Setting::where([
                            'project_id' => 'c7f596ed-fca0-4d64-9c10-15f9ac60dfca',
                            'active_domain' => $request->root()
                        ])->get();
                    }
                } else {
                    $settings = Setting::where([
                        'active_domain' => $request->root()
                    ])->get();
                }
                foreach($settings as $key => $value) {
                    $settingsData = json_decode($value->data, true);
                    if (!empty($settingsData)) {
                        foreach($settingsData as $dataKey => $dataValue) {
                            $extractedSettings[$value->name][$dataKey] = $dataValue;
                        }
                    }
                }
                return $extractedSettings;

            } catch (\Exception $e) {
                return [];
            }
        });
    }

    public function boot(Request $request)
    {
        Storage::extend('null', function ($app, $config) {
            return new class {
                public function ensureDirectoryExists($path) {
                    return true;
                }
            };
        });
    }
}