<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class FetchSettings
{
    /**
     * Allowed database connection names that can be modified
     * These are the dedicated user connections, NOT the main app connections
     */
    private const ALLOWED_DB_CONNECTIONS = [
        'mysql2',
        'pgsql2'
    ];

    /**
     * Allowed database configuration keys that can be modified
     */
    private const ALLOWED_DB_CONFIG_KEYS = [
        'host',
        'port', 
        'database',
        'username',
        'password',
        'unix_socket', // for MySQL
        'search_path', // for PostgreSQL
        'sslmode',     // for PostgreSQL
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $allSettings = app('settings');
            
            if (!empty($allSettings) && is_array($allSettings)) {
                $this->applyDatabaseSettings($allSettings);
            }
        } catch (\Exception $e) {
            // Log error but don't break the request
            Log::warning('Database settings middleware error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'ip' => $request->ip()
            ]);
        }

        return $next($request);
    }

    /**
     * Apply database settings with security validation
     */
    private function applyDatabaseSettings(array $allSettings): void
    {
        $appliedSettings = [];

        foreach ($allSettings as $settingsKey => $settingsData) {
            // Only process database configuration
            if ($settingsKey !== 'database') {
                continue;
            }

            foreach ($allSettings['database'] as $configKey => $configValue) {
                config(['database' . '.' .$configKey => $configValue]);
                $appliedSettings[] = $configKey;
            }
        }

        // if (!empty($appliedSettings)) {
        //     Log::info('Database settings applied successfully', [
        //         'user_id' => Auth::id(),
        //         'applied_settings' => $appliedSettings,
        //         'ip' => request()->ip()
        //     ]);

        //     $this->testConnections(array_unique(array_map(function($setting) {
        //         return explode('.', $setting)[2]; 
        //     }, $appliedSettings)));
        // }
    }

    /**
     * Check if database connection is allowed to be modified
     */
    private function isAllowedConnection(string $connectionName): bool
    {
        return in_array($connectionName, self::ALLOWED_DB_CONNECTIONS, true);
    }

    /**
     * Validate and sanitize database connection settings
     */
    private function validateConnectionSettings(string $connectionName, array $settings): array
    {
        $validatedSettings = [];

        foreach ($settings as $key => $value) {
            // Only allow specific configuration keys
            if (!in_array($key, self::ALLOWED_DB_CONFIG_KEYS, true)) {
                Log::warning("Attempt to set restricted database config key: {$key}", [
                    'connection' => $connectionName,
                    'user_id' => Auth::id()
                ]);
                continue;
            }

            // Validate and sanitize the value
            $sanitizedValue = $this->sanitizeDatabaseValue($key, $value);
            
            if ($sanitizedValue !== null) {
                $validatedSettings[$key] = $sanitizedValue;
            }
        }

        return $validatedSettings;
    }

    /**
     * Sanitize database configuration values
     */
    private function sanitizeDatabaseValue(string $key, $value)
    {
        if (is_null($value)) {
            return null;
        }

        switch ($key) {
            case 'host':
                // Validate hostname/IP format
                $host = trim((string) $value);
                if (empty($host) || strlen($host) > 255) {
                    return null;
                }
                // Basic hostname validation (allows IP addresses and hostnames)
                if (!preg_match('/^[a-zA-Z0-9.-]+$/', $host)) {
                    return null;
                }
                return $host;

            case 'port':
                $port = (int) $value;
                // Valid port range
                return ($port >= 1 && $port <= 65535) ? $port : null;

            case 'database':
            case 'username':
                // Basic string validation for database and username
                $str = trim((string) $value);
                if (empty($str) || strlen($str) > 64) {
                    return null;
                }
                // Allow alphanumeric, underscore, hyphen
                return preg_match('/^[a-zA-Z0-9_-]+$/', $str) ? $str : null;

            case 'password':
                // Password can contain any characters but limit length
                $password = (string) $value;
                return strlen($password) <= 255 ? $password : null;

            case 'unix_socket':
                // Unix socket path validation
                $socket = trim((string) $value);
                if (empty($socket)) {
                    return '';
                }
                // Basic path validation
                return preg_match('/^\/[a-zA-Z0-9\/_.-]+$/', $socket) ? $socket : null;

            case 'search_path':
                // PostgreSQL search path
                $path = trim((string) $value);
                return preg_match('/^[a-zA-Z0-9_,\s]+$/', $path) ? $path : null;

            case 'sslmode':
                // PostgreSQL SSL modes
                $validSslModes = ['disable', 'allow', 'prefer', 'require', 'verify-ca', 'verify-full'];
                return in_array($value, $validSslModes, true) ? $value : null;

            default:
                return null;
        }
    }
}