<?php

namespace App\Services\Request;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\Route\RouteResolver;
use App\Services\Controller\ControllerExecutor;
use App\Services\Page\PageDataLoader;
use App\DTOs\ProcessedRequest;
use App\DTOs\PageContext;
use App\Traits\DatabaseConnectionTester;

class RequestProcessor
{
    use DatabaseConnectionTester;

    public function __construct(
        private RouteResolver $routeResolver,
        private ControllerExecutor $controllerExecutor,
        private PageDataLoader $pageDataLoader,
    ) {}

    /**
     * Process the incoming request through all stages
     */
    public function process(Request $request): ProcessedRequest
    {
        try {
            // Find matching route
            $route = $this->routeResolver->findMatchingRoute($request);
            
            if (!$route) {
                return ProcessedRequest::notFound();
            }

            // Build request context
            $context = $this->buildContext($request, $route);
            
            // Test database connection
            $dbConnection = $this->testConnection();
            if (!$dbConnection) {
                return ProcessedRequest::databaseError();
            }

            // Execute controller if present
            $controllerResult = null;
            if ($route->controller && $route->controller_method) {
                $controllerResult = $this->controllerExecutor->execute($route, $context);
            }

            // Load page assets only if not requesting JSON
            $pageData = null;
            if (!$request->wantsJson() && $route->method !== 'POST') {
                $pageData = $this->pageDataLoader->load($route, $context, $controllerResult);
            }

            return new ProcessedRequest(
                route: $route,
                context: $context,
                controllerResult: $controllerResult,
                pageData: $pageData,
                dbConnection: $dbConnection,
            );

        } catch (\Throwable $e) {
            \Log::error('Request processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'path' => $request->path(),
            ]);

            return ProcessedRequest::error($e);
        }
    }

    /**
     * Execute a specific file method (for admin/testing purposes)
     */
    public function executeSpecificMethod(string $fileUuid, string $methodUuid, Request $request)
    {
        try {
            $context = $this->buildBasicContext($request);
            
            return $this->controllerExecutor->executeSpecificMethod(
                $fileUuid,
                $methodUuid,
                $context
            );
        } catch (\Throwable $e) {
            \Log::error('Method execution failed', [
                'error' => $e->getMessage(),
                'file_uuid' => $fileUuid,
                'method_uuid' => $methodUuid,
            ]);

            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Build complete page context including route variables
     */
    private function buildContext(Request $request, $route): PageContext
    {
        $variables = $this->extractRouteVariables($request, $route);

        return new PageContext(
            request: $request,
            route: $route,
            user: Auth::user(),
            variables: $variables,
            settings: app('settings'),
            path: $request->path(),
            uriSegments: explode('/', $request->path()),
        );
    }

    /**
     * Build basic context without route
     */
    private function buildBasicContext(Request $request): PageContext
    {
        return new PageContext(
            request: $request,
            route: null,
            user: Auth::user(),
            variables: [],
            settings: app('settings'),
            path: $request->path(),
            uriSegments: explode('/', $request->path()),
        );
    }

    /**
     * Extract route parameters from dynamic routes
     */
    private function extractRouteVariables(Request $request, $route): array
    {
        $variables = [];
        $routePath = explode('/', $route->path);
        $requestPath = explode('/', $request->path());

        foreach ($routePath as $index => $parameter) {
            if (!empty($parameter[0]) && $parameter[0] === '{') {
                preg_match('/(?<={).*?(?=})/', $parameter, $match);
                if (!empty($match[0]) && isset($requestPath[$index])) {
                    $variables[$match[0]] = $requestPath[$index];
                    $request->merge([$match[0] => $requestPath[$index]]);
                }
            }
        }

        return $variables;
    }
}
