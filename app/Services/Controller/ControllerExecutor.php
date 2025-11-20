<?php

namespace App\Services\Controller;

use App\Models\File;
use App\Models\Method;
use App\Services\CodeExecutionService;
use App\Services\Controller\ControllerDataLoader;
use App\DTOs\CodeExecutionContext;
use App\DTOs\PageContext;

class ControllerExecutor
{
    public function __construct(
        private ControllerDataLoader $dataLoader,
        private CodeExecutionService $codeExecutor,
    ) {}

    /**
     * Execute the controller method for a route
     */
    public function execute($route, PageContext $context): mixed
    {
        $method = Method::where('uuid', $route->controller_method)->first();
        
        if (!$method) {
            throw new \Exception("Controller method not found: {$route->controller_method}");
        }

        $methodData = json_decode($method->data, true);

        // Load all controller data
        $controllerData = $this->dataLoader->loadAll($route->controller);

        // Build execution context
        $executionContext = new CodeExecutionContext(
            request: $context->request,
            controllerData: $controllerData->controller,
            methodData: $methodData,
            includes: $controllerData->includes,
            models: $controllerData->models,
            methods: $controllerData->methods,
            statements: $controllerData->statements,
            clauses: $controllerData->clauses,
            variables: $context->variables,
            settings: $context->settings,
            user: $context->user,
            debug: false,
            editMode: false,
        );

        // Execute the controller method
        $result = $this->codeExecutor->execute($executionContext);

        // Handle errors
        if (!empty($result['errors'])) {
            throw new \Exception('Controller execution errors: ' . json_encode($result['errors']));
        }

        return $result;
    }

    /**
     * Execute a specific file method (for admin/testing)
     */
    public function executeSpecificMethod(string $fileUuid, string $methodUuid, PageContext $context): mixed
    {
        $controller = File::where('uuid', $fileUuid)->first();
        $method = Method::where('uuid', $methodUuid)->first();

        if (!$controller || !$method) {
            throw new \Exception('File or method not found');
        }

        $methodData = json_decode($method->data, true);

        // Load all controller data
        $controllerData = $this->dataLoader->loadAll($fileUuid);

        // Build execution context
        $executionContext = new CodeExecutionContext(
            request: $context->request,
            controllerData: $controllerData->controller,
            methodData: $methodData,
            includes: $controllerData->includes,
            models: $controllerData->models,
            methods: $controllerData->methods,
            statements: $controllerData->statements,
            clauses: $controllerData->clauses,
            variables: $context->variables,
            settings: $context->settings,
            user: $context->user,
            debug: false,
            editMode: false,
        );

        // Execute
        $result = $this->codeExecutor->execute($executionContext);

        // Handle errors
        if (!empty($result['errors'])) {
            return response()->json(['errors' => $result['errors']], 500);
        }

        if (empty($result)) {
            return response()->json([
                'status' => 200,
                'message' => $methodData['name'] . ' has finished executing.'
            ], 200);
        }

        return $result;
    }

    /**
     * Process the controller return value and extract variables
     */
    public function processControllerReturn($return): array
    {
        $variables = [];
        $response = null;

        if ($return instanceof \Illuminate\Contracts\View\View) {
            $response = $return;
        } else if (!empty($return['type']) && $return['type'] === 'view') {
            // Will be handled by ViewAssemblerService later
            $response = $return;
        } else if (is_object($return) && method_exists($return, 'links')) {
            // Handle paginator
            $paginatorMethods = [
                'currentPage', 'hasMorePages', 'hasPages', 'lastItem',
                'lastPage', 'nextPageUrl', 'perPage', 'previousPageUrl', 'total'
            ];
            
            foreach ($paginatorMethods as $method) {
                $variables[$method] = $return->{$method}();
            }
            $variables['output'] = $return;
        } else {
            if ($return instanceof \Illuminate\Database\Eloquent\Model) {
                foreach ($return->getAttributes() as $key => $value) {
                    $variables[$key] = $value;
                }
            } else if ($return instanceof \Illuminate\Database\Eloquent\Collection) {
                $variables['count'] = $return->count();
                $variables['isEmpty'] = $return->isEmpty();
                if ($return->count() > 0) {
                    $variables['first'] = $return->first();
                    $variables['last'] = $return->last();
                }
            }
            $variables['output'] = $return;
        }

        return [
            'variables' => $variables,
            'response' => $response,
        ];
    }
}
