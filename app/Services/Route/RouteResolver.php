<?php

namespace App\Services\Route;

use Illuminate\Http\Request;
use App\Models\Route;

class RouteResolver
{
    /**
     * Find the matching route for the given request
     */
    public function findMatchingRoute(Request $request): ?Route
    {
        $path = $request->path();
        $method = $request->method();
        
        // Try exact match first
        $route = $this->findExactMatch($path, $method);
        
        if ($route) {
            return $route;
        }
        
        // Try pattern matching for dynamic routes
        $route = $this->findPatternMatch($path, $method);
        
        if ($route) {
            return $route;
        }
        
        // Fallback to 404 route
        return $this->find404Route();
    }

    /**
     * Find exact path match
     */
    private function findExactMatch(string $path, string $method): ?Route
    {
        return Route::where('path', $path)
            ->where('method', $method)
            ->where('public', true)
            ->first();
    }

    /**
     * Find pattern match for dynamic routes
     */
    private function findPatternMatch(string $path, string $method): ?Route
    {
        $routes = Route::where('method', $method)
            ->where('public', true)
            ->get();
        
        foreach ($routes as $route) {
            if ($this->pathMatchesPattern($path, $route->path)) {
                return $route;
            }
        }
        
        return null;
    }

    /**
     * Check if path matches route pattern
     */
    private function pathMatchesPattern(string $path, string $pattern): bool
    {
        // Convert route pattern like /user/{id} to regex
        $regex = $this->convertPatternToRegex($pattern);
        
        return preg_match($regex, $path) === 1;
    }

    /**
     * Convert route pattern to regex
     */
    private function convertPatternToRegex(string $pattern): string
    {
        $pattern = preg_replace('/\{[^}]+\}/', '([^/]+)', $pattern);
        $pattern = str_replace('/', '\/', $pattern);
        
        return "/^{$pattern}$/";
    }

    /**
     * Find the 404 fallback route
     */
    private function find404Route(): ?Route
    {
        return Route::where('name', '404')->first();
    }

    /**
     * Load and decode route data
     */
    public function loadRouteData(Route $route): array
    {
        if (empty($route->data)) {
            return [];
        }

        return json_decode($route->data, true) ?? [];
    }
}
