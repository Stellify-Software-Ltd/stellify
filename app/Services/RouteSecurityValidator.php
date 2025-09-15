<?php

namespace App\Services;

use App\Models\User;
use App\Models\Route;
use App\Models\Project;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Exception;

class RouteSecurityValidator
{
    /**
     * Validate route data before creation/update
     */
    public static function validateRoute(array $routeData, User $user): void
    {
        // Basic data validation
        static::validateBasicData($routeData);
        
        // Security validations
        static::validatePathSecurity($routeData['path']);
        static::validateMiddlewareSecurity($routeData, $user);
        static::validateProjectAccess($routeData, $user);
        static::validateRateLimit($user);
        
        Log::info('Route validation passed', [
            'user_id' => $user->id,
            'path' => $routeData['path'],
            'middleware' => $routeData['middleware'] ?? []
        ]);
    }

    /**
     * Validate basic route data structure
     */
    protected static function validateBasicData(array $routeData): void
    {
        $required = ['path', 'method', 'project_id'];
        
        foreach ($required as $field) {
            if (empty($routeData[$field])) {
                throw new \Exception("Required field missing: {$field}");
            }
        }

        // Validate HTTP method
        $validMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
        if (!in_array(strtoupper($routeData['method']), $validMethods)) {
            throw new \Exception('Invalid HTTP method');
        }

        // Validate path format
        if (!preg_match('/^\/[a-zA-Z0-9\/_\-\{\}]*$/', $routeData['path'])) {
            throw new \Exception('Invalid path format');
        }
    }

    /**
     * SECURITY: Validate path doesn't conflict with system routes
     */
    protected static function validatePathSecurity(string $path): void
    {
        // System routes that users cannot override
        // $systemRoutes = [
        //     '/admin',
        //     '/login', 
        //     '/register',
        //     '/logout',
        //     '/email/verify',
        //     '/email/verification-notification',
        //     '/password/reset',
        //     '/password/email',
        //     '/password/confirm',
        //     '/api/system',
        //     '/stellify/api',
        //     '/documentation',
        //     '/application'
        // ];
        
        // foreach ($systemRoutes as $systemRoute) {
        //     if ($path === $systemRoute || str_starts_with($path, $systemRoute . '/')) {
        //         throw new \Exception("Cannot create routes that override system path: {$systemRoute}");
        //     }
        // }

        // Prevent dangerous path patterns
        $dangerousPatterns = [
            '/\.env',
            '/\.git',
            '/admin',
            '/config',
            '/storage',
            '/..',
            '/php',
            '/shell',
            '/cmd'
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (str_contains(strtolower($path), $pattern)) {
                throw new \Exception("Path contains restricted pattern: {$pattern}");
            }
        }

        // Validate path length
        if (strlen($path) > 255) {
            throw new \Exception('Path too long (max 255 characters)');
        }
    }

    /**
     * SECURITY: Validate middleware assignments
     */
    protected static function validateMiddlewareSecurity(array $routeData, User $user): void
    {
        if (empty($routeData['middleware'])) {
            return; // No middleware is fine
        }

        if (!is_array($routeData['middleware'])) {
            throw new \Exception('Middleware must be an array');
        }

        // Get allowed middleware for this user
        $allowedMiddleware = static::getAllowedMiddlewareForUser($user, $routeData['project_id']);

        foreach ($routeData['middleware'] as $middleware) {
            if (!is_string($middleware)) {
                throw new \Exception('All middleware must be strings');
            }

            if (!static::isMiddlewareAllowed($middleware, $allowedMiddleware)) {
                static::logSecurityViolation('unauthorized_middleware_assignment', [
                    'user_id' => $user->id,
                    'middleware' => $middleware,
                    'path' => $routeData['path'],
                    'project_id' => $routeData['project_id']
                ]);
                
                throw new \Exception("Middleware not allowed: {$middleware}");
            }
        }

        // Validate middleware count (prevent spam)
        if (count($routeData['middleware']) > 10) {
            throw new \Exception('Too many middleware assigned (max 10)');
        }
    }

    /**
     * Get allowed middleware for a specific user
     */
    protected static function getAllowedMiddlewareForUser(User $user, string $projectId): array
    {
        // Base safe middleware
        $allowed = [
            'web', 
            'guest', 
            'throttle:60,1',
            'throttle:6,1',
            'throttle:10,1', 
            'config.merge',
            'force.https',
            'model.eager'
        ];

        // Authenticated users get additional middleware
        $allowed = array_merge($allowed, [
            'auth', 
            'verified'
        ]);

        try {
            $databaseConnection = request()->root() == 'https://stellisoft.com' ? 'pgsql' : 'mysql';
            
            // Check if user owns the project
            $project = Project::on($databaseConnection)->where('uuid', $projectId)->first();
            if ($project && $project->user_id === $user->id) {
                $allowed[] = 'can:admin-project';
            }

            // Check user permissions for this project
            $permissions = $user->permissions->where('project_id', $projectId)->first();
            if ($permissions && $permissions->super === 1) {
                $allowed[] = 'can:super-admin';
            }
        } catch (\Exception $e) {
            Log::warning('Could not check project permissions for middleware validation', [
                'user_id' => $user->id,
                'project_id' => $projectId,
                'error' => $e->getMessage()
            ]);
        }

        return $allowed;
    }

    /**
     * Check if middleware is allowed
     */
    protected static function isMiddlewareAllowed(string $middleware, array $allowedMiddleware): bool
    {
        // Handle middleware with parameters
        $middlewareName = explode(':', $middleware)[0];
        
        foreach ($allowedMiddleware as $allowed) {
            $allowedName = explode(':', $allowed)[0];
            if ($middlewareName === $allowedName) {
                // Special validation for throttle middleware
                if ($middlewareName === 'throttle') {
                    return static::isValidThrottleMiddleware($middleware);
                }
                return true;
            }
        }
        
        return false;
    }

    /**
     * Validate throttle middleware parameters
     */
    protected static function isValidThrottleMiddleware(string $middleware): bool
    {
        if (!str_contains($middleware, ':')) {
            return false;
        }
        
        $parts = explode(':', $middleware, 2);
        $params = explode(',', $parts[1]);
        
        if (count($params) !== 2) {
            return false;
        }
        
        $requests = (int) $params[0];
        $minutes = (int) $params[1];
        
        // Reasonable limits
        return $requests > 0 && $requests <= 1000 && $minutes > 0 && $minutes <= 60;
    }

    /**
     * SECURITY: Validate user has access to the project
     */
    protected static function validateProjectAccess(array $routeData, User $user): void
    {
        if ($routeData['project_id'] !== $user->project_id) {
            // Check if user has permissions for this project
            $permissions = $user->permissions->where('project_id', $routeData['project_id'])->first();
            
            if (!$permissions || ($permissions->write !== 1 && $permissions->super !== 1)) {
                static::logSecurityViolation('unauthorized_project_access', [
                    'user_id' => $user->id,
                    'target_project_id' => $routeData['project_id'],
                    'user_project_id' => $user->project_id
                ]);
                
                throw new \Exception('Access denied: You cannot create routes in this project');
            }
        }
    }

    /**
     * SECURITY: Rate limiting for route creation
     */
    protected static function validateRateLimit(User $user): void
    {
        try {
            $databaseConnection = request()->root() == 'https://stellisoft.com' ? 'pgsql' : 'mysql';
            
            // Check recent route creation activity
            $recentRoutes = Route::on($databaseConnection)
                ->where('project_id', $user->project_id)
                ->where('created_at', '>', now()->subMinutes(5))
                ->count();
                
            if ($recentRoutes > 20) {
                static::logSecurityViolation('route_creation_rate_limit', [
                    'user_id' => $user->id,
                    'project_id' => $user->project_id,
                    'recent_count' => $recentRoutes
                ]);
                
                throw new \Exception('Rate limit exceeded: Too many routes created recently');
            }

            // Check total routes per project (prevent spam)
            $totalRoutes = Route::on($databaseConnection)
                ->where('project_id', $user->project_id)
                ->count();
                
            $maxRoutes = $user->subscribed() ? 1000 : 50; // Higher limits for paid users
            
            if ($totalRoutes >= $maxRoutes) {
                throw new \Exception("Route limit exceeded: Maximum {$maxRoutes} routes per project");
            }
            
        } catch (\Exception $e) {
            if ($e instanceof \Exception) {
                throw $e;
            }
            
            Log::warning('Could not check route creation rate limits', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Log security violations
     */
    protected static function logSecurityViolation(string $violationType, array $context): void
    {
        Log::warning("Route security violation: {$violationType}", array_merge($context, [
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toISOString()
        ]));

        // You could also:
        // - Store in a security_logs table
        // - Send alerts for repeated violations
        // - Temporarily restrict user access
    }

    /**
     * Sanitize route data before saving
     */
    public static function sanitizeRouteData(array $routeData): array
    {
        // Sanitize path
        $routeData['path'] = trim($routeData['path']);
        $routeData['path'] = str_replace(['..', '//', '\\'], ['', '/', '/'], $routeData['path']);
        
        // Ensure path starts with /
        if (!str_starts_with($routeData['path'], '/')) {
            $routeData['path'] = '/' . $routeData['path'];
        }

        // Sanitize method
        $routeData['method'] = strtoupper(trim($routeData['method']));

        // Sanitize middleware
        if (!empty($routeData['middleware']) && is_array($routeData['middleware'])) {
            $routeData['middleware'] = array_map('trim', $routeData['middleware']);
            $routeData['middleware'] = array_filter($routeData['middleware']); // Remove empty values
            $routeData['middleware'] = array_unique($routeData['middleware']); // Remove duplicates
        }

        // Set public flag
        $routeData['public'] = $routeData['public'] ?? true;

        return $routeData;
    }
}
