<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\QueryException;

use App\Services\CodeExecutionService;
use App\Services\PhpAssemblerService;
use App\Services\ViewAssemblerService;

use App\DTOs\CodeExecutionContext;

use App\Models\User;
use App\Models\Route;
use App\Models\Element;
use App\Models\Project;
use App\Models\Setting;
use App\Models\Permission;
use App\Models\File;
use App\Models\Directory;
use App\Models\Method;
use App\Models\Statement;
use App\Models\Clause;
use App\Models\Meta;
use App\Models\Branch;
use App\Models\Statistic;
use App\Models\AccessLog;
use App\Models\Style;
use App\Models\Task;

use App\Mail\DynamicMail;

use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\Matching\UriValidator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

use App\Traits\DatabaseConnectionTester;

class AppController extends Controller implements HasMiddleware
{
    use DatabaseConnectionTester;

    protected $codeExecutor;
    private $code;
    private $body;
    private $data;
    private $response;
    private $files;
    private $directories;
    private $methods;
    private $statements;
    private $clauses;
    private $variables;
    private $user;
    private $rootUser;
    private $root;
    private $path;
    private $classes;
    private $rules;
    private $baseClasses;
    private $uriSegments;
    private $page;
    private $navPages;
    private $return;
    private $routes;
    private $project;
    private $meta;
    private $permissions;
    private $templates;
    private $settings;
    private $externalDatabaseConnection;
    private $config;
    private $fonts;
    private $views;
    private $availableDirectories;
    private $dbConnected;
    private $blockHierarchy = [];
    private $pageHierarchy;
    private $statementHierarchy;
    private $paginatorMethods;
    private $errors;
    private $systemErrors;

    public function __construct(CodeExecutionService $codeExecutor, PhpAssemblerService $phpAssemblerService, ViewAssemblerService $viewAssemblerService) 
    {
        $this->codeExecutor = $codeExecutor;
        $this->phpAssemblerService = $phpAssemblerService;
        $this->viewAssemblerService = $viewAssemblerService;
    }

    public static function middleware(): array
    {
        $middleware = [];
        $request = request();
        $root = $request->root();
        $path = $request->path();
        $method = $request->method();

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
                //new Middleware('config.merge'),
                new Middleware('force.https'),
                new Middleware('model.eager')
            ];
            
            // Log the error for monitoring
            \Log::warning('Middleware resolution failed', [
                'error' => $e->getMessage(),
                'path' => $path,
                'edit_mode' => $isEditMode
            ]);
        }
        
        return $middleware;
    }

    protected static function findMatchingRoute(Request $request)
    {
        $path = $request->path();
        $method = $request->method();
        
        $query = \App\Models\Route::where('method', $method)
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

    protected function initializeBasicProperties(Request $request) 
    {
        $this->root = $request->root();
        $this->path = $request->path();
        $this->uriSegments = explode('/', $this->path);
        $this->page = [];
        $this->errors = [];
        $this->navPages = [];
        $this->routes = new RouteCollection;
        $this->url = $request->url();
        $this->user = Auth::user();
        $this->users = [];
        $this->statistics = [];
        $this->permissions = [];
        $this->directories = [];
        $this->availableDirectories = ['controllers', 'models', 'migrations', 'factories', 'seeders', 'js', 'tests', 'rules', 'middleware', 'policies', 'events', 'jobs', 'services', 'requests', 'notifications', 'mail'];
        $this->files = [];
        $this->views = [];
        $this->methods = [];
        $this->return = null;
        $this->statements = [];
        $this->clauses = [];
        $this->data = [];
        $this->entities = [];
        $this->variables = [];
        $this->project = null;
        $this->meta = [];
        $this->classes = [];
        $this->rules = [];
        $this->settings = app('settings');
        $this->config = [];
        $this->events = ['beforeMount', 'mounted', 'beforeUnmount', 'unmounted'];
        $this->blockHierarchy = [];
        $this->navSlugs = [];
        $this->pageHierarchy = [];
        $this->statementHierarchy = [];
        $this->paginatorMethods = ["currentPage", "hasMorePages", "hasPages", "lastItem", "lastPage", "nextPageUrl", "perPage", "previousPageUrl", "total"];
        $this->code = "";
        $this->functions = null;
        $this->rootUser = false;
        $this->externalDatabaseConnection = null;
    }

    protected function findAndLoadPage(Request $request)
    {
        // Try exact match first
        $route = Route::where(['path' => $this->path, 'method' => $request->method()])
            ->first();

        if (!empty($route)) {
            $this->page = $route;
        } else {
            // Pattern matching logic
            $routes = Route::all();
            
            foreach($routes as $route) {
                $currentRoute = new \Illuminate\Routing\Route($route->method, $route->path, []);
                if ($currentRoute->matches($request)) {
                    $this->page = $route;
                    // Extract route parameters
                    $routePath = explode('/', $route->path);
                    $requestPath = explode('/', $this->path);
                    foreach ($routePath as $index => $parameter) {
                        if (!empty($parameter[0]) && $parameter[0] == '{') {
                            preg_match('/(?<={).*?(?=})/', $parameter, $match);
                            $this->variables[$match[0]] = $requestPath[$index];
                            $request->merge([$match[0] => $requestPath[$index]]);
                        }
                    }
                    break;
                }
            }
        }

        // Handle 404 fallback
        if (empty($this->page)) {
            $this->page = Route::where([
                'name' => '404'
            ])->first();
        }

        // Load page data
        if (!empty($this->page)) {
            $this->pageData = json_decode($this->page->data);
        }
    }

    protected function fetchIncludes(array $controllerData): array
    {
        if (empty($controllerData['includes'])) {
            return [];
        }
        
        $includes = File::whereIn('uuid', $controllerData['includes'])
            ->get();

        if (empty($includes)) {
            return [];
        }

        $includeData = [];
            
        foreach ($includes as $include) {
            $includeData[$include->uuid] = json_decode($include->data, true);
            $includeData[$include->uuid]['name'] = $include->name;
            $includeData[$include->uuid]['namespace'] = $include->namespace;
        }

        return $includeData;
    }

    protected function fetchModels(array $controllerData): array
    {
        if (empty($controllerData['models'])) {
            return [];
        }
        
        $models = File::whereIn('uuid', $controllerData['models'])
            ->get();
            
        if (empty($models)) {
            return [];
        }

        $modelData = [];

        foreach ($models as $model) {
            $modelData[$model->uuid] = json_decode($model->data, true);
        }

        return $modelData;
    }

    protected function fetchModelIncludeIds(array $models): array
    {
        $includeIds = [];
        foreach ($models as $model) {
            if (!empty($model['includes'])) {
                $includeIds = array_merge($includeIds, $model['includes']);
            }
        }
        return $includeIds;
    }

    protected function fetchModelIncludes(array $modelIncludeIds): array
    {
        if (empty($modelIncludeIds)) {
            return [];
        }
        
        $files = File::whereIn('uuid', $modelIncludeIds)
            ->get();

        if (empty($files)) {
            return [];
        }

        $fileData = [];

        foreach ($files as $file) {
            $fileData[$file->uuid] = json_decode($file->data, true);
            $fileData[$file->uuid]['name'] = $file->name;
            $fileData[$file->uuid]['namespace'] = $file->namespace;
        }

        return $fileData;
    }

    protected function fetchMethods(array $controllerData): array
    {
        if (empty($controllerData['data'])) {
            return [];
        }
        
        $methods = Method::whereIn('uuid', $controllerData['data'])
            ->get();

        if (empty($methods)) {
            return [];
        }

        $methodData = [];

        foreach ($methods as $method) {
            $methodData[$method->uuid] = json_decode($method->data, true);
        }

        return $methodData;
    }

    protected function fetchStatementIds(array $methods): array
    {
        $statementIds = [];
        foreach ($methods as $methodUuid => $methodData) {
            if (!empty($methodData['data']) && is_array($methodData['data'])) {
                $statementIds = array_merge($statementIds, $methodData['data']);
            }
        }
        return $statementIds;
    }

    protected function fetchStatements(array $statementIds): array
    {
        if (empty($statementIds)) {
            return [];
        }
        
        $statements = Statement::whereIn('uuid', $statementIds)
            ->get();

        if (empty($statements)) {
            return [];
        }

        $statementData = [];

        foreach ($statements as $statement) {
            $statementData[$statement->uuid] = json_decode($statement->data, true);
        }

        return $statementData;
    }

    protected function fetchClauseIds(array $statements): array
    {
        $clauseIds = [];
        foreach ($statements as $statementUuid => $statementData) {
            if (!empty($statementData['data']) && is_array($statementData['data'])) {
                $clauseIds = array_merge($clauseIds, $statementData['data']);
            }
        }
        return $clauseIds;
    }

    protected function fetchParameterClauseIds(array $methods): array
    {
        $methodIds = [];
        foreach ($methods as $methodUuid => $methodData) {
            if (!empty($methodData['parameters']) && is_array($methodData['parameters'])) {
                $methodIds = array_merge($methodIds, $methodData['parameters']);
            }
        }
        return $methodIds;
    }

    protected function fetchClauses(array $clauseIds): array
    {
        if (empty($clauseIds)) {
            return [];
        }

        $clauses = Clause::whereIn('uuid', $clauseIds)
            ->get();

        if (empty($clauses)) {
            return [];
        }

        $clauseData = [];

        foreach ($clauses as $clause) {
            $clauseData[$clause->uuid] = json_decode($clause->data, true);
        }

        return $clauseData;
    }

    protected function executeRouteController(Request $request)
    {
        if (!empty($this->page->controller) && !empty($this->page->controller_method)) {
            $controller = File::where(['uuid' => $this->page->controller])->first();
            $method = Method::where(['uuid' => $this->page->controller_method])->first();

            if (!empty($controller) && !empty($method)) {
                $methodData = json_decode($method->data, true);
                $this->methods[$method->uuid] = $methodData;
                $controllerData = json_decode($controller->data, true);

                // Fetch all related data
                $includes = $this->fetchIncludes($controllerData);
                $models = $this->fetchModels($controllerData);
                $methods = $this->fetchMethods($controllerData);
                $statementIds = $this->fetchStatementIds($methods);
                $statements = $this->fetchStatements($statementIds);
                $clauseIds = $this->fetchClauseIds($statements);
                $parameterClauseIds = $this->fetchParameterClauseIds($methods);
                $clauseIds = array_merge($clauseIds, $parameterClauseIds);
                $clauses = $this->fetchClauses($clauseIds);
                $settings = $this->settings;
                $modelIncludeIds = $this->fetchModelIncludeIds($models);
                if (!empty($modelIncludeIds)) {
                    $modelIncludes = $this->fetchModelIncludes($modelIncludeIds);
                    $includes = array_merge($includes, $modelIncludes);
                }
                $this->return = null;
                if (!$this->debug) {
                    $context = new CodeExecutionContext(
                        $request,
                        $controllerData,
                        $methodData,
                        $includes,
                        $models,
                        $methods,
                        $statements,
                        $clauses,
                        $this->variables,
                        $settings,
                        null,
                        false,
                        $this->editMode
                    );
                    $this->return = $this->codeExecutor->execute($context);
                }

                if (!empty($this->return['errors'])) {
                    $this->errors[$this->page->controller] = [];
                    $this->errors[$this->page->controller][$this->page->controller_method] = $this->return['errors'];
                } else if (!empty($this->return)) {
                    $this->processControllerReturn();
                }
            }
        }
    }

    public function executeFileMethod(string $file_uuid, string $method_uuid, Request $request) 
    {
        if (!empty($file_uuid) && !empty($method_uuid)) {
            $this->initializeBasicProperties($request);
            $controller = File::where(['uuid' => $file_uuid])->first();
            $method = Method::where(['uuid' => $method_uuid])->first();
            if (!empty($controller) && !empty($method)) {
                $methodData = json_decode($method->data, true);
                $controllerData = json_decode($controller->data, true);
                $databaseSettings = Setting::where(['name' => 'database'])->first();
                $settings = [];
                $settings['database'] = !empty($databaseSettings) ? json_decode($databaseSettings->data, true) : [];
                // Fetch all related data
                $includes = $this->fetchIncludes($controllerData);
                $models = $this->fetchModels($controllerData);
                $methods = $this->fetchMethods($controllerData);
                $statementIds = $this->fetchStatementIds($methods);
                $statements = $this->fetchStatements($statementIds);
                $clauseIds = $this->fetchClauseIds($statements);
                $parameterClauseIds = $this->fetchParameterClauseIds($methods);
                $clauseIds = array_merge($clauseIds, $parameterClauseIds);
                $clauses = $this->fetchClauses($clauseIds);
                $modelIncludeIds = $this->fetchModelIncludeIds($models);
                if (!empty($modelIncludeIds)) {
                    $modelIncludes = $this->fetchModelIncludes($modelIncludeIds);
                    $includes = array_merge($includes, $modelIncludes);
                }
                $this->return = null;
                if (!$this->debug) {
                    $context = new CodeExecutionContext(
                        $request,
                        $controllerData,
                        $methodData,
                        $includes,
                        $models,
                        $methods,
                        $statements,
                        $clauses,
                        [],
                        $settings,
                        null,
                        false,
                        false
                    );
                    $this->return = $this->codeExecutor->execute($context);
                }

                if (!empty($this->return['errors'])) {
                    return $this->return['errors'];
                } else if (!empty($this->return)) {
                    $this->processControllerReturn();
                } else {
                    return response()->json(['status' => 200, 'message' => $method['name'] . ' has finished executing.'], 200);
                }
            }
        }
    }

    protected function processControllerReturn()
    {
        if ($this->return instanceof \Illuminate\Contracts\View\View) {
            $this->response = $this->return;
        } else if (!empty($this->return['type']) && $this->return['type'] == 'view') {
            //construct view
            $this->response = $this->viewAssemblerService->render($this->return['name'], $this->return['data']);
        } else if (gettype($this->return) == 'object' && method_exists($this->return, 'links')) {
            foreach($this->paginatorMethods as $paginatorMethod) {
                $this->variables[$paginatorMethod] = $this->return->{$paginatorMethod}();
            }
            $this->variables['output'] = $this->return;
        } else {
            if ($this->return instanceof \Illuminate\Database\Eloquent\Model) {
                foreach($this->return->getAttributes() as $key => $value) {
                    $this->variables[$key] = $value;
                }
            } else if ($this->return instanceof \Illuminate\Database\Eloquent\Collection) {
                $this->variables['count'] = $this->return->count();
                $this->variables['isEmpty'] = $this->return->isEmpty();
                if ($this->return->count() > 0) {
                    $this->variables['first'] = $this->return->first();
                    $this->variables['last'] = $this->return->last();
                }
            }
            $this->variables['output'] = $this->return;
        }
    }

    protected function loadPageAssets()
    {
        if (!empty($this->pageData)) {
            $this->loadGlobalFiles();
            $this->loadGlobalEvents();
            $this->loadPageFiles();
            $this->loadPageMeta();
        }
    }

    protected function loadGlobalFiles()
    {
        if (!empty($this->settings['app']) && !empty($this->settings['app']['files'])) {
            if (empty($this->pageData->files)) {
                $this->pageData->files = $this->settings['app']['files'];
            } else {
                foreach($this->settings['app']['files'] as $file) {
                    $index = array_search($file, $this->pageData->files);
                    if ($index === false) {
                        array_push($this->pageData->files, $file);
                    }
                }
            }
        }
    }

    protected function loadGlobalEvents()
    {
        if (!empty($this->events)) {
            foreach($this->events as $event) {
                if (!empty($this->settings['app'][$event])) {
                    if (empty($this->pageData->{$event})) {
                        $this->pageData->{$event} = $this->settings['app'][$event];
                    } else {
                        foreach($this->settings['app'][$event] as $eventId) {
                            $index = array_search($eventId, $this->pageData->{$event});
                            if ($index === false) {
                                array_push($this->pageData->{$event}, $eventId);
                            }
                        }
                    }
                }
            }
        }
    }

    protected function loadPageFiles()
    {
        if (!empty($this->pageData->files)) {
            $jsFiles = File::whereIn('uuid', $this->pageData->files)->get();
            foreach($jsFiles as $jsFile) {
                if (!empty($jsFile)) {
                    $jsFileData = json_decode($jsFile->data, true);
                    // ... existing file processing logic
                    $this->files[$jsFileData['uuid']] = $jsFileData;
                    if (!empty($jsFileData['data'])) {
                        foreach ($jsFileData['data'] as $key => $value) {
                            $jsMethods = Method::whereIn('uuid', $jsFileData['data'])->get();
                            if (!empty($jsMethods)) {
                                foreach($jsMethods as $jsMethod) {
                                    if (!empty($jsMethod->data)) {
                                        $jsMethodData = json_decode($jsMethod->data, true);
                                        $this->methods[$jsMethodData['uuid']] = $jsMethodData;
                                        $this->traverseStatement($jsMethodData['data']);
                                        $jsStatements = Statement::whereIn('uuid', $this->statementHierarchy)->get();
                                        foreach($jsStatements as $jsStatement) {
                                            $jsStatementData = json_decode($jsStatement->data, true);
                                            if (!empty($jsStatementData['data'])) {
                                                $jsClauses = Clause::whereIn('uuid', $jsStatementData['data'])->get();
                                                foreach($jsStatementData['data'] as $clause) {
                                                    $jsClause = Clause::where('uuid', $clause)->first();
                                                    if (!empty($jsClause)) {
                                                        $jsClauseData = json_decode($jsClause->data, true);
                                                        $this->clauses[$jsClauseData['uuid']] = $jsClauseData;
                                                    }
                                                }
                                            }
                                            $this->statements[$jsStatementData['uuid']] = $jsStatementData;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    protected function loadPageMeta()
    {
        if (!empty($this->pageData->meta)) {
            $meta = Meta::whereIn('uuid', $this->pageData->meta)->get();
            if (!empty($meta)) {
                foreach($meta as $tag) {
                    $metaData = json_decode($tag->data, true);
                    $this->meta[$metaData['uuid']] = $metaData;
                }
            }
        }
    }

    protected function buildElementHierarchy()
    {
        if (!empty($this->pageData->data)) {
            $cacheKey = "page_elements_{$this->page->uuid}";
            
            //$cachedResult = Cache::get($cacheKey);
            $cachedResult = false;
            if ($cachedResult) {
                $this->blockHierarchy = $cachedResult['hierarchy'];
                $this->data = $cachedResult['data'];
            } else {
                $this->blockHierarchy = [];
                $this->traverseElements($this->pageData->data);
                
                // Cache the complete result
                Cache::put($cacheKey, [
                    'hierarchy' => $this->blockHierarchy,
                    'data' => $this->data
                ], 3600);
            }
        }
    }

    protected function init(Request $request) 
    {
        try {
            $this->initializeBasicProperties($request);

            $this->findAndLoadPage($request);

            $this->externalDatabaseConnection = $this->testConnection();

            if (!empty($this->externalDatabaseConnection)) {
                $this->executeRouteController($request);
            }

            if ($request->wantsJson()) {
                return $this->return;
            }

            $this->loadPageAssets();

            $this->buildElementHierarchy();

        } catch (\Throwable $e) {
            // Handle the error gracefully
            \Log::error('Error occurred while processing request', [
                'error' => $e->getMessage(),
                'stack' => $e->getTraceAsString()
            ]);

            return response()->view('errors.code-error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    protected function api(Request $request) 
    {
        try {
            $this->initializeBasicProperties($request);

            $this->findAndLoadPage($request);

            $this->externalDatabaseConnection = $this->testConnection();

            if (!empty($this->externalDatabaseConnection)) {
                $this->executeRouteController($request);
            }

        } catch (\Throwable $e) {
            // Handle the error gracefully
            \Log::error('Error occurred while processing request', [
                'error' => $e->getMessage(),
                'stack' => $e->getTraceAsString()
            ]);

            return response()->view('errors.code-error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    protected function traverseElements($blocks) 
    {
        foreach($blocks as $uuid) {
            array_push($this->blockHierarchy, $uuid);
            
            // Skip if we already have this block's data
            if (isset($this->data[$uuid])) {
                $blockData = $this->data[$uuid];
            } else {
                $block = Element::where(['uuid' => $uuid])->first();
                if (!empty($block->data)) {
                    $blockData = json_decode($block->data);
                    if ($block->type == 's-directive' && !empty($blockData->statement)) {
                        $statement = Statement::where(['uuid' => $blockData->statement])->first();
                        if (!empty($statement)) {
                            $this->phpAssemblerService->resetCode();
                            $statementData = json_decode($statement->data);
                            $this->statements[$statement->uuid] = $statementData;
                            if (!empty($statementData->data)) {
                                //get statement clauses
                                $clauses = Clause::whereIn('uuid', $statementData->data)
                                    ->get()
                                    ->keyBy('uuid');
                                if (!empty($clauses)) {
                                    foreach ($statementData->data as $clause) {
                                        $clauseData = json_decode($clauses[$clause]->data, true);
                                        $this->clauses[$clause] = $clauseData;
                                        if ($clauseData) {
                                            $this->phpAssemblerService->assembleStatement($clauseData);
                                        }
                                    }
                                    $blockData->value = $this->phpAssemblerService->getCode();
                                }
                            }
                        }
                    }
                    // Cache the data immediately
                    $this->data[$uuid] = $blockData;
                } else {
                    continue;
                }
            }
            // Check for children and recurse
            if (!empty($blockData->data)) {
                $this->traverseElements($blockData->data);
            }
        }
        return;
    }

    protected function traverseStatement($statements) 
    {
        foreach($statements as $uuid) {
            array_push($this->statementHierarchy, $uuid);
            $statement = Statement::where('uuid', $uuid)->first();
            if (!empty($statement->data)) {
                $statementData = json_decode($statement->data);
                if (!empty($statementData->data)) {
                    $this->traverseStatement($statementData->data);
                }
            }
        }
        return;
    }

    public function index(Request $request) 
    {
        $initResult = $this->init($request);

        //not valid resource
        if (empty($this->page)) {
            return abort(404);
        }

        //There's no controller or method assigned to this route
        if ($this->page->method == 'POST' && (empty($this->page->controller) || empty($this->page->controller_method))) {
            return response()->view('errors.route-error', [], 500);
        }

        //If POST request then return output from controller
        if ($this->page->method == 'POST') {
            return $this->return;
        }

        if ($request->wantsJson()) {
            return $this->return;
        }

        //page MustVerifyEmail
        if ($this->page->email_verify && !empty($this->user) && $this->user->email_verified_at == null) {
            return redirect('/email/verify');
        }
        //global MustVerifyEmail
        if (!empty($this->settings['auth']) && !empty($this->settings['auth']['MustVerifyEmail']) && (!empty($this->user) && $this->user->email_verified_at == null)) {
            return redirect('/email/verify');
        }

        //redirect
        if ($this->page->redirect_url) {
            return redirect($this->page->redirect_url, $this->page->status_code ? $this->page->status_code : '302');
        }

        //get any errors from the current session and pass them to the view
        $errors = $request->session()->get('errors');
        $previous = $request->old();
        if (!empty($previous)) {
            foreach ($previous as $key => $value) {
                $this->variables[$key] = $value;
            }
        }
        if (!empty($errors)) {
            foreach ($errors->getMessages() as $key => $error) {
                if ($key == 'stellifyError') {
                    $this->editor['message'] = $error[0];
                } else {
                    $this->variables[$key . '-error'] = $error;
                }
            }
        }
        return view('page', [
            "settings" => !empty($this->settings['app']) ? $this->settings['app'] : null,
            "fonts" => !empty($this->fonts) ? $this->fonts : null,
            "project" => !empty($this->project) ? $this->project : '',
            "meta" => !empty($this->meta) ? $this->meta : null,
            "body" => !empty($this->pageData) ? $this->pageData : null,
            "css" => !empty($this->css) ? $this->css : null,
            "content" => !empty($this->data) ? $this->data : null,
            "clauses" => !empty($this->clauses) ? $this->clauses : null,
            "statements" => !empty($this->statements) ? $this->statements : null,
            "methods" => !empty($this->methods) ? $this->methods : null,
            "files" => !empty($this->files) ? $this->files : null,
            "user" => !empty($this->user) ? $this->user : null,
            "variables" => !empty($this->variables) ? $this->variables : null,
            "config" => !empty($this->config) ? $this->config : null,
            "path" => ($this->path == '/' ? 'home' : $this->path),
        ]);
    }

    public function generateSitemap(Request $request) 
    {
        $this->init($request);
        $pages = [];
        $pages = Route::where(['public' => 1])->get();
        foreach($pages as $page) {
            $pageData = json_decode($page->data, true);
            if (!empty($pageData)) {
                if (!empty($pageData['children'])) {
                    $templatePages = Route::whereIn('uuid', $pageData['children'])->get();
                    if (!empty($templatePages)) {
                        foreach($templatePages as $templatePage) {
                            if (!empty($templatePage['path'])) {
                                $templatePage['path'] = preg_replace('/{[^}]+}/', $templatePage['path'], $pageData['path']);
                            }
                        }
                        $pages = $pages->merge($templatePages);
                    }
                }
            }
        }
        return response()->view('pages.sitemap', ['pages' => $pages, 'date' => date('Y-m-d'), 'root' => $this->root])->header('Content-Type', 'text/xml');
    }
}