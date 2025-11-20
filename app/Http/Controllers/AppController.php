<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use App\Services\Request\RequestProcessor;
use App\Services\Response\ResponseFactory;
use App\Models\Route;

class AppController extends Controller implements HasMiddleware
{
    public function __construct(
        private RequestProcessor $requestProcessor,
        private ResponseFactory $responseFactory,
    ) {}

    public static function middleware(): array
    {
        $middleware = [];
        $request = request();

        try {
            $matchingRoute = static::findMatchingRoute($request);
                
            if ($matchingRoute && !empty($matchingRoute->data)) {
                $routeData = json_decode($matchingRoute->data);
                
                // Apply route-specific middleware from JSON
                if (!empty($routeData->middleware) && is_array($routeData->middleware)) {
                    foreach ($routeData->middleware as $middlewareName) {
                        $middleware[] = new Middleware($middlewareName);
                    }
                }
            }
            
        } catch (\Exception $e) {
            // SECURITY: On any error, use safe default middleware
            $middleware = [
                new Middleware('web'),
                new Middleware('force.https'),
                new Middleware('model.eager')
            ];
            
            \Log::warning('Middleware resolution failed', [
                'error' => $e->getMessage(),
                'path' => $request->path(),
            ]);
        }
        
        return $middleware;
    }

    protected static function findMatchingRoute(Request $request)
    {
        $path = $request->path();
        $method = $request->method();
        
        $query = Route::where('method', $method)
            ->where('public', true);
        
        // Try exact match first
        $route = (clone $query)->where('path', $path)->first();
        if ($route) {
            return $route;
        }
        
        // Try pattern matching for dynamic routes
        $routes = $query->get();
        
        foreach ($routes as $route) {
            // Convert route pattern like /user/{id} to regex
            $pattern = preg_replace('/\{[^}]+\}/', '([^/]+)', str_replace('/', '\/', $route->path));
            if (preg_match("/^{$pattern}$/", $path)) {
                return $route;
            }
        }
        
        return null;
    }

    /**
     * Handle both web and API requests
     */
    public function handle(Request $request): Response
    {
        $result = $this->requestProcessor->process($request);
        
        return $this->responseFactory->make($request, $result);
    }

    /**
     * Main entry point for web requests
     */
    public function index(Request $request): Response
    {
        return $this->handle($request);
    }

    /**
     * Execute a specific file method (for internal/admin use)
     */
    public function executeFileMethod(string $fileUuid, string $methodUuid, Request $request)
    {
        return $this->requestProcessor->executeSpecificMethod($fileUuid, $methodUuid, $request);
    }
}
