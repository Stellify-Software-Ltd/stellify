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
use App\Models\Profile;
use App\Models\Definition;
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
    private $projectId;
    private $files;
    private $directories;
    private $definitions;
    private $methods;
    private $statements;
    private $clauses;
    private $variables;
    private $user;
    private $rootUser;
    private $users;
    private $profile;
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
    private $sectionRelation;
    private $settings;
    private $databaseConnection;
    private $externalDatabaseConnection;
    private $config;
    private $fonts;
    private $views;
    private $dbConnected;
    private $editMode;
    private $debug;
    private $production;
    private $blockHierarchy = [];
    private $pageHierarchy;
    private $statementHierarchy;
    private $paginatorMethods;
    private $errors;
    private $systemErrors;

    public function __construct(CodeExecutionService $codeExecutor, PhpAssemblerService $phpAssemblerService) {
        $this->codeExecutor = $codeExecutor;
        $this->phpAssemblerService = $phpAssemblerService;
    }

    /**
     * Get the middleware that should be assigned to the controller.
     * Only applies dynamic middleware when user is NOT in edit mode.
     */
    public static function middleware(): array
    {
        $middleware = [];
        $request = request();
        $root = $request->root();
        $path = $request->path();
        $method = $request->method();

        if (static::isSystemRouteCheck($path)) {
            return static::getSystemRouteMiddleware($path);
        }

        
        // SECURITY: Check if user is in edit mode
        $isEditMode = $request->has('edit') || $request->has('preview');
        
        // Initialize database connection
        $databaseConnection = $root == 'https://stellisoft.com' ? 'pgsql' : 'mysql';

        try {
            // Get current user to determine project context
            $user = Auth::user();
            
            if (!empty($user)) {
                $projectId = $user->project_id;
            } else if ($root == 'https://stellisoft.com' || $root == 'http://localhost') {
                $projectId = 'c7f596ed-fca0-4d64-9c10-15f9ac60dfca'; // stellifyProjectId
            }
            
            // SECURITY: Only apply dynamic middleware when NOT in edit mode
            if (!$isEditMode) {
                // Find the matching route
                $matchingRoute = static::findMatchingRoute($request, $databaseConnection, $projectId);
                
                if ($matchingRoute && !empty($matchingRoute->data)) {
                    $routeData = json_decode($matchingRoute->data);
                    
                    // Apply route-specific middleware from JSON
                    if (!empty($routeData->middleware) && is_array($routeData->middleware)) {
                        foreach ($routeData->middleware as $middlewareName) {
                            $middleware[] = new Middleware($middlewareName);
                        }
                    }
                }
            } else {
                // EDIT MODE: Use safe default middleware for editing
                $middleware[] = new Middleware('web');
                $middleware[] = new Middleware('auth'); // Always require auth for edit mode
                $middleware[] = new Middleware('config.merge');
                $middleware[] = new Middleware('force.https');
                $middleware[] = new Middleware('model.eager');
                
                // Log edit mode access for security monitoring
                if ($user) {
                    \Log::info('Edit mode accessed', [
                        'user_id' => $user->id,
                        'path' => $path,
                        'ip' => $request->ip()
                    ]);
                }
            }
            
            // Default fallback middleware if no route-specific middleware found
            if (empty($middleware)) {
                $middleware[] = new Middleware('web');
                $middleware[] = new Middleware('config.merge');
                $middleware[] = new Middleware('force.https');
                $middleware[] = new Middleware('model.eager');
            }
            
        } catch (\Exception $e) {
            // SECURITY: On any error, use safe default middleware
            $middleware = [
                new Middleware('web'),
                new Middleware('config.merge'),
                new Middleware('force.https'),
                new Middleware('model.eager')
            ];
            
            // If in edit mode and error occurs, require auth
            if ($isEditMode) {
                array_splice($middleware, 1, 0, [new Middleware('auth')]);
            }
            
            // Log the error for monitoring
            \Log::warning('Middleware resolution failed', [
                'error' => $e->getMessage(),
                'path' => $path,
                'edit_mode' => $isEditMode
            ]);
        }
        
        return $middleware;
    }

    /**
     * Find the matching route for the current request
     */
    protected static function findMatchingRoute(Request $request, string $connection, string $projectId = null)
    {
        $path = $request->path();
        $method = $request->method();
        
        $query = \App\Models\Route::on($connection)
            ->where('method', $method)
            ->where('public', true);
            
        if ($projectId) {
            $query->where('project_id', $projectId);
        }
        
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
     * SECURITY: Check if path conflicts with system routes (static version for middleware method)
     */
    public static function isSystemRouteCheck(string $path): bool
    {
        $systemRoutes = [
            '/admin', '/login', '/register', '/logout',
            '/email/verify', '/email/verification-notification', 
            '/password/reset', '/password/email', '/password/confirm',
            '/api/system', '/stellify/api', '/documentation', '/application'
        ];
        
        foreach ($systemRoutes as $systemRoute) {
            if ($path === $systemRoute || str_starts_with($path, $systemRoute . '/')) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * SECURITY: Get appropriate middleware for system routes
     */
    protected static function getSystemRouteMiddleware(string $path): array
    {
        $middleware = [
            new Middleware('web'),
            new Middleware('config.merge'),
            new Middleware('force.https'),
            new Middleware('model.eager')
        ];

        // Admin routes always require auth
        if (str_starts_with($path, '/admin')) {
            $middleware[] = new Middleware('auth');
            $middleware[] = new Middleware('verified');
        }
        
        // Auth routes - use edit-aware guest middleware
        if (in_array($path, ['/login', '/register'])) {
            $middleware[] = new Middleware('guest');
        }
        
        // Email verification routes
        if (str_starts_with($path, '/email/verify')) {
            $middleware[] = new Middleware('auth');
            $middleware[] = new Middleware('signed');
            $middleware[] = new Middleware('throttle:6,1');
        }
        
        return $middleware;
    }

    protected function initializeBasicProperties(Request $request) 
    {
        $this->root = $request->root();
        $this->path = $request->path();
        $this->projectId = null;
        $this->uriSegments = explode('/', $this->path);
        $this->page = [];
        $this->errors = [];
        $this->navPages = [];
        $this->routes = new RouteCollection;
        $this->url = $request->url();
        $this->user = Auth::user();
        $this->users = [];
        $this->statistics = [];
        $this->sectionRelation = [];
        $this->permissions = [];
        $this->directories = [];
        $this->files = [];
        $this->views = [];
        $this->methods = [];
        $this->return = null;
        $this->statements = [];
        $this->clauses = [];
        $this->data = [];
        $this->entities = [];
        $this->variables = [];
        $this->editor = ["selection" => []];
        $this->profile = null;
        $this->project = null;
        $this->meta = [];
        $this->classes = [];
        $this->rules = [];
        $this->baseClasses = ['nobreakpoint' => [], 'sm:' => [], 'md:' => [], 'lg:' => [], 'xl:' => []];
        $this->css = '';
        $this->settings = app('settings');
        $this->config = [];
        $this->events = ['beforeMount', 'mounted', 'beforeUnmount', 'unmounted'];
        $this->editMode = $request->has('edit');
        $this->previewMode = $request->has('preview');
        $this->debug = $request->has('debug');
        $this->production = false;
        $this->blockHierarchy = [];
        $this->navSlugs = [];
        $this->pageHierarchy = [];
        $this->definitions = [];
        $this->statementHierarchy = [];
        $this->paginatorMethods = ["currentPage", "hasMorePages", "hasPages", "lastItem", "lastPage", "nextPageUrl", "perPage", "previousPageUrl", "total"];
        $this->code = "";
        $this->functions = null;
        $this->vueMethods = ['beforeMount', 'mounted'];
        $this->vueTypes = ['Computed'];
        $this->rootUser = false;
        $this->databaseConnection = $this->root == 'https://stellisoft.com' ? 'pgsql' : 'mysql';
        $this->externalDatabaseConnection = null;
    }

    protected function loadProjectContext(Request $request)
    {
        if (!empty($this->user)) {
            $project = Project::on($this->databaseConnection)->where(['uuid' => $this->user->project_id])->first();
            if ($project->user_id == $this->user->id) {
                $this->rootUser = true;
            }
            $this->project = json_decode($project->data);
            $this->projectId = $this->project->uuid;
        } else {
            $this->projectId = 'c7f596ed-fca0-4d64-9c10-15f9ac60dfca';
        }
    }

    protected function findAndLoadPage(Request $request)
    {
        // Try exact match first
        $route = Route::on($this->databaseConnection)
            ->where(['project_id' => $this->projectId, 'path' => $this->path, 'method' => $request->method()])
            ->first();

        if (!empty($route)) {
            $this->page = $route;
        } else {
            // Pattern matching logic
            $routes = Route::on($this->databaseConnection)->where(['project_id' => $this->projectId])->get();
            
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
            $this->page = Route::on($this->databaseConnection)->where([
                'project_id' => $this->projectId,
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
        
        $includes = File::on($this->databaseConnection)
            ->whereIn('uuid', $controllerData['includes'])
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
        
        $models = File::on($this->databaseConnection)
            ->where('project_id', $this->projectId)
            ->whereIn('uuid', $controllerData['models'])
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
        
        $files = File::on($this->databaseConnection)
            ->whereIn('uuid', $modelIncludeIds)
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
        
        $methods = Method::on($this->databaseConnection)
            ->where('project_id', $this->projectId)
            ->whereIn('uuid', $controllerData['data'])
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
        
        $statements = Statement::on($this->databaseConnection)
            ->where('project_id', $this->projectId)
            ->whereIn('uuid', $statementIds)
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

    protected function fetchClauses(array $clauseIds): array
    {
        if (empty($clauseIds)) {
            return [];
        }

        $clauses = Clause::on($this->databaseConnection)
            ->whereIn('uuid', $clauseIds)
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
        if (!empty($this->page->type) && $this->page->type == 'web' && !empty($this->page->controller) && !empty($this->page->controller_method)) {
            $controller = File::on($this->databaseConnection)->where(['project_id' => $this->projectId, 'uuid' => $this->page->controller])->first();
            $method = Method::on($this->databaseConnection)->where(['project_id' => $this->projectId, 'uuid' => $this->page->controller_method])->first();
            
            if (!empty($controller) && !empty($method)) {
                $methodData = json_decode($method->data, true);
                $controllerData = json_decode($controller->data, true);

                // Fetch all related data
                $includes = $this->fetchIncludes($controllerData);
                $models = $this->fetchModels($controllerData);
                $methods = $this->fetchMethods($controllerData);
                $statementIds = $this->fetchStatementIds($methods);
                $statements = $this->fetchStatements($statementIds);
                $clauseIds = $this->fetchClauseIds($statements);
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
                        $this->projectId,
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
                    );
                    
                    $this->return = $this->codeExecutor->execute($context);
                }
                
                if (!empty($this->return)) {
                    $this->processControllerReturn();
                }
            }
        }
    }

    protected function processControllerReturn()
    {
        if (method_exists($this->return, 'links') && gettype($this->return) == 'object') {
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
            $jsFiles = File::on($this->databaseConnection)->whereIn('uuid', $this->pageData->files)->get();
            foreach($jsFiles as $jsFile) {
                if (!empty($jsFile)) {
                    $jsFileData = json_decode($jsFile->data, true);
                    // ... existing file processing logic
                    $this->files[$jsFileData['uuid']] = $jsFileData;
                    // ... method and statement processing
                }
            }
        }
    }

    protected function loadPageMeta()
    {
        if (!empty($this->pageData->meta)) {
            $meta = Meta::on($this->databaseConnection)->whereIn('uuid', $this->pageData->meta)->get();
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
            $cacheKey = "page_elements_{$this->page->uuid}_{$this->projectId}";
            
            $cachedResult = Cache::get($cacheKey);
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

    protected function initializeEditor()
    {
        /**
         * Stellify editor configuration, including:
         * * Establishing whether the user is subscribed
         * * Fetching permissions
         * * Fetching team member permissions
         * * Initialising editor variables
         * * Fetching the current branch
         * * Fetching views
         * * Fetching profiles
         */
        if (!empty($this->user) && !empty($this->project) && ($this->editMode || $this->previewMode)) {
            $this->statistics = Statistic::where(['user_id' => $this->user->id])->first();
            $this->user->subscribed = $this->user->subscribed();
            $this->permissions = $this->user->permissions->where('project_id', $this->project->uuid)->first();
            if (!empty($this->permissions->super)) {
                //get users for current project
                $users = Permission::where(['project_id' => $this->project->uuid])->get();
                if (!empty($users)) {
                    forEach($users as $user) {
                        $userData = [];
                        $userData['name'] = $user->name;
                        $userData['id'] = $user->uuid;
                        $userData['read'] = $user->read == 1 ? true : false;
                        $userData['write'] = $user->write == 1 ? true : false;
                        $userData['execute'] = $user->execute == 1 ? true : false;
                        $userData['super'] = $user->super == 1 ? true : false;
                        $userData['updated_at'] = $user->updated_at;
                        $this->users[] = $userData;
                    }
                }
            }
            $this->editor = [
                'deviceWidth' => '',
                'variablePanel' => true,
                'showEditor' => false,
                'showSearch' => false,
                'guidelines' => true,
                'displayCurrentSelection' => false,
                'currentTaskSelection' => [],
                'displayVariables' => false,
                'currentMode' => 'interface',
                'currentPlan' => '',
                'showComponents' => false,
                'selectOnInsert' => false,
                'pageAttributes' => false,
                'codeView' => false,
                'debug' => true,
                'canvasBackgroundColour' => '#000',
                'currentKeyframe' => null,
                'selectedElements' => [],
                'currentSearchType' => 'all',
                'currentType' => 's-wrapper',
                'currentIndicator' => null,
                'navigationTree' => [],
                'currentTokenName' => '',
                'currentTokenValue' => '',
                'currentScopes' => [],
                'prefix' => '',
                'selectMode' => false,
                'methods' => [],
                'files' => [],
                'migrations' => [],
                'currentSelection' => null,
                'classSelection' => 'classes',
                'statePrefix' => '',
                'editorPosition' => 'right',
                'classbar' => true, 
                'missingElements' => [],
                'componentName' => '',
                'componentCategory' => '',
                'split' => false, 
                'toolbar' => true, 
                'navigator' => true,
                'showMenuBar' => true,
                'currentInputValue' => '',
                'recordingRelativePathToTarget' => '',
                'recordKeyPress' => false,
                'loading' => false,
                'fullscreen' => false,
                'quotaExceeded' => false,
                'quickActions' => true,
                'currentProject' => $this->user->project_id,
                'currentFile' => '',
                'models' => [],
                'includes' => [],
                'jsIncludes' => [],
                'message' => '',
                'currentDriver' => '',
                'currentMethod' => '',
                'currentStatement' => '',
                'currentLine' => null,
                'currentClause' => '',
                'currentHightlightedClause' => null,
                'currentHighlightedStatement' => null,
                'currentMigration' => '',
                'currentMiddleware' => '',
                'currentBranch' => !empty($this->user->branch_id) ? $this->user->branch_id : '',
                'currentName' => '',
                'currentTag' => '',
                'currentView' => 'entities',
                'currentVariable' => '',
                'currentMethodVariables' => [],
                'currentInputType' => 'text',
                'currentStatementType' => 'expression',
                'currentConfig' => '',
                'currentDefinition' => '',
                'currentEvent' => '',
                'currentDirectory' => '',
                'methodSignatures' => '',
                'classes' => [],
                'branches' => [],
                'modelSearch' => '',
                'currentSearch' => '',
                'searchResults' => [],
                'resourceName' => '',
                'resourceSelection' => [],
                'currentVariableName' => '',
                'currentVariableScope' => '',
                'currentVariableValue' => '',
                'currentVariableType' => '',
                'currentMeta' => '',
                'currentSelectedData' => null,
                'currentRef' => '',
                'currentAiModel' => '',
                'currentPrompt' => '',
                'currentAiModels' => [],
                'currentSearchItems' => []
            ];
            if ($this->user->subscribed == false && !empty($this->statistics)) {
                if (
                    $this->statistics->routes > 4 ||
                    $this->statistics->files > 9 ||
                    $this->statistics->elements > 499 ||
                    $this->statistics->methods > 49 ||
                    $this->statistics->statements > 499 ||
                    $this->statistics->clauses > 999
                ) {
                    $this->editor['quotaExceeded'] = true;
                }
            }
            if (!empty($this->settings['prism']) && is_array($this->settings['prism'])) {
                foreach ($this->settings['prism'] as $key => $value) {
                    $explodedKey = explode('.',$key);
                    if (!empty($explodedKey[2]) && $explodedKey[2] == 'api_key') {
                        if ($explodedKey[1] == 'openai') {
                            $this->editor['currentAiModels'] = array_merge($this->editor['currentAiModels'], [
                                'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
                                'gpt-4' => 'GPT-4',
                                'gpt-4o' => 'GPT-4 Turbo',
                                'gpt-4o-mini' => 'GPT-4 Turbo Mini',
                                'gpt-4-turbo-preview' => 'GPT-4 Turbo Preview',
                                'gpt-3.5-turbo-preview' => 'GPT-3.5 Turbo Preview'
                            ]);
                        }
                        if ($explodedKey[1] == 'anthropic') {
                            $this->editor['currentAiModels'] = array_merge($this->editor['currentAiModels'], [
                                'claude-2' => 'Claude 2',
                                'claude-instant-100k' => 'Claude Instant 100K',
                                'claude-3-opus' => 'Claude 3 Opus',
                                'claude-3-haiku' => 'Claude 3 Haiku'
                            ]);
                        }
                    }
                }
            }
            if (!empty($this->settings['filesystems']) && !empty($this->settings['filesystems']['disks'])) {
                $this->editor['filesystem'] = true;
            }
            if (!empty($this->user->branch_id)) {
                $branch = Branch::on($this->databaseConnection)->where(['project_id' => $this->project->uuid, 'uuid' => $this->user->branch_id])->first();
                $this->editor['branches'][$branch->uuid] = json_decode($branch->data);
            }
            // $namedElements = Element::on($this->databaseConnection)->where(['project_id' => $this->project->uuid])->where('name', '<>', '')->whereNotNull('name')->get();
            // if (!empty($namedElements)) {
            //     $this->views = $namedElements;
            // }
            //Once major development changes cease then call this from js and store in local storage 
            $profiles = Profile::on($this->databaseConnection)->select('data')
                ->where(['uuid' => 'general'])
                ->get();

            if (!empty($profiles)) {
                $key = 'general';
                foreach($profiles as $profile) {
                    $this->profile[$key] = json_decode($profile->data, true);
                    $key = 'methods';
                }
            }
            if (!empty($this->project->data)) {
                $directories = Directory::on($this->databaseConnection)->whereIn('uuid', $this->project->data)->get();
                if (!empty($directories)) {
                    foreach($directories as $directory) {
                        $this->directories[$directory->uuid] = json_decode($directory->data);
                    }
                }
            }
        }
    }

    protected function initializePreview()
    {
        if ($this->previewMode) {
            $this->editor['currentIndicator'] = null;
            $this->editor['guidelines'] = true;
        }
    }

    protected function init(Request $request) 
    {
        try {
            $this->initializeBasicProperties($request);

            $this->loadProjectContext($request);

            $this->initializeEditor();

            $this->initializePreview();

            $this->findAndLoadPage($request);

            $this->externalDatabaseConnection = $this->testConnection();

            if (!empty($this->externalDatabaseConnection)) {
                $this->executeRouteController($request);
            }

            $this->loadPageAssets();

            $this->buildElementHierarchy();

        } catch (\Throwable $e) {
            //dd($e);
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

    protected function traverseElements($blocks) {
        foreach($blocks as $uuid) {
            array_push($this->blockHierarchy, $uuid);
            
            // Skip if we already have this block's data
            if (isset($this->data[$uuid])) {
                $blockData = $this->data[$uuid];
            } else {
                $block = Element::on($this->databaseConnection)->where(['uuid' => $uuid, 'project_id' => $this->projectId])->first();
                if (!empty($block->data)) {
                    $blockData = json_decode($block->data);
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

    protected function traverseStatement($statements) {
        foreach($statements as $uuid) {
            array_push($this->statementHierarchy, $uuid);
            $statement = Statement::on($this->databaseConnection)->where('uuid', $uuid)->first();
            if (!empty($statement->data)) {
                $statementData = json_decode($statement->data);
                if (!empty($statementData->data)) {
                    $this->traverseStatement($statementData->data);
                }
            }
        }
        return;
    }

    public function index(Request $request) {
        $initResult = $this->init($request);

        // Check if init returned an error response
        if ($initResult instanceof \Illuminate\Http\Response) {
            return $initResult;
        }

        if (empty($this->user) && $this->path == '/' && !$this->editMode && ($this->root == 'https://stellisoft.com' || $this->root == 'http://localhost')) {
            $recentTasks = Task::with(['createdBy:id,name', 'assignedTo:id,name'])
            ->where('status', 'available')
            ->orderBy('created_at', 'desc')
            ->limit(6)
            ->get();
        
            // Get task stats
            $taskStats = [
                'total' => Task::count(),
                'available' => Task::where('status', 'available')->count(),
                'in_progress' => Task::where('status', 'in_progress')->count(),
                'completed' => Task::where('status', 'completed')->count(),
            ];
            
            // Get high priority tasks
            $urgentTasks = Task::with(['createdBy:id,name'])
                ->where('status', 'available')
                ->where('priority', 'high')
                ->orWhere('priority', 'critical')
                ->orderBy('priority', 'desc')
                ->orderBy('created_at', 'desc')
                ->limit(3)
                ->get();
            
            return view('general.home', compact('recentTasks', 'taskStats', 'urgentTasks'));
        }
        if (!empty($this->user) && empty($this->user->email_verified_at)) {
            return redirect('/email/verify');
        }
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
        //Stellify MustVerifyEmail
        if ($this->root == 'https://stellisoft.com' && !empty($this->user) && $this->user->email_verified_at == null) {
            return redirect('/email/verify');
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
        //privacy controls
        if (
            $this->editMode && 
            !empty($this->page) && 
            empty($this->user)
        ) {
            return abort(404);
        }
        //We're proceeding to render the page for the current route

        // $cacheKey = sprintf(
        //     'page_data_%s_%s_%s', 
        //     $this->user?->id ?? 'guest',
        //     $this->path,
        //     $this->editMode ? 'edit' : 'view'
        // );





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
        if (!$this->editMode && !empty($this->page->type) && $this->page->type == 'mail') {
            $html = view('email', [
                'body' => !empty($this->pageData) ? $this->pageData : null, 
                'content' => !empty($this->data) ? $this->data : null, 
                'settings' => !empty($this->settings['app']) ? $this->settings['app'] : null,
                "variables" => !empty($this->variables) ? $this->variables : null
            ])->render();
            return new DynamicMail($html);
        } else if ($this->editMode) {
            if ($this->root == 'https://stellisoft.com') {
                $this->production = true;
            }
            return view('page', [
                "editMode" => $this->editMode ? 'edit' : '',
                "project" => !empty($this->project) ? $this->project : '',
                "meta" => !empty($this->meta) ? $this->meta : null,
                "fonts" => !empty($this->fonts) ? $this->fonts : null,
                "body" => !empty($this->pageData) ? $this->pageData : null,
                "editor" => !empty($this->editor) ? $this->editor : null,
                "content" => !empty($this->data) ? $this->data : null,
                "includes" => !empty($this->includes) ? $this->includes : null,
                "scripts" => !empty($this->scripts) ? $this->scripts : null,
                "user" => !empty($this->user) ? $this->user : null,
                "variables" => $this->variables,
                "files" => !empty($this->files) ? $this->files : null,
                "directories" => !empty($this->directories) ? $this->directories : null,
                "methods" => !empty($this->methods) ? $this->methods : null,
                "users" => !empty($this->users) ? $this->users : null,
                "statements" => !empty($this->statements) ? $this->statements : null,
                "clauses" => !empty($this->clauses) ? $this->clauses : null,
                "errors" => !empty($this->errors) ? $this->errors : null,
                "systemErrors" => !empty($this->systemErrors) ? $this->systemErrors : null,
                "settings" => !empty($this->settings['app']) ? $this->settings['app'] : null,
                "config" => !empty($this->config) ? $this->config : null,
                "profile" => !empty($this->profile) ? $this->profile : null,
                "views" => !empty($this->views) ? $this->views : null,
                "definitions" => !empty($this->definitions) ? $this->definitions : null,
                "permissions" => !empty($this->permissions) ? $this->permissions : null,
                'statistics' => !empty($this->statistics) ? $this->statistics : null,
                'rootUser' => $this->rootUser,
                'production' => $this->production
            ]);
        } else if ($this->previewMode) {
            return response()->view('page', [
                "editor" => !empty($this->editor) ? $this->editor : null,
                "previewMode" => $this->previewMode ? 'preview' : '',
                "settings" => !empty($this->settings['app']) ? $this->settings['app'] : null,
                "editMode" => $this->editMode ? 'edit' : '',
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
                "definitions" => !empty($this->definitions) ? $this->definitions : null,
                "config" => !empty($this->config) ? $this->config : null,
                "path" => ($this->path == '/' ? 'home' : $this->path),
                'production' => $this->production
            ])->header('X-Frame-Options', 'ALLOW-FROM https://stellisoft.com/');
        } else {
            if ($this->root == 'https://stellisoft.com') {
                $this->production = true;
            }
            return view('page', [
                "settings" => !empty($this->settings['app']) ? $this->settings['app'] : null,
                "editMode" => $this->editMode ? 'edit' : '',
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
                "definitions" => !empty($this->definitions) ? $this->definitions : null,
                "config" => !empty($this->config) ? $this->config : null,
                "path" => ($this->path == '/' ? 'home' : $this->path),
                'production' => $this->production
            ]);
        }
    }

    public function api() {
        $this->init($request);
        if (!$this->editMode && !empty($this->page->type) && $this->page->type == 'api') {
            $controller = File::on($this->databaseConnection)->where('uuid', $this->pageData->controller)->first();
            $methods = Method::on($this->databaseConnection)->where('uuid', $this->pageData->controller_method)->get();
            if (!empty($methods)) {
                foreach($methods as $method) {
                    $methodData = json_decode($method->data, true);
                    $includes = [];
                    if (!empty($controller)) {
                        $controllerData = json_decode($controller->data, true);
                        if (!$this->debug) {
                            if (($this->root == 'http://localhost' || $this->root == 'https://stellisoft.com') && empty($this->user)) {
                                $this->return = $this->php($this->projectId, $request, $controllerData, $methodData, $this->variables, $this->pageData->type, false);
                            } else {
                                $this->return = $this->php($this->project->uuid, $request, $controllerData, $methodData, $this->variables, $this->pageData->type, false);
                            }
                            return $this->return;
                        }
                    }
                }
            }
        } 
    }

    public function share(Request $request, string $project, string $page) {
        $this->page = Route::on($this->databaseConnection)->where(['method' => 'GET', 'public' => true, 'project_id' => $project])->first();
        if (!empty($this->page)) {
            $this->pageData = json_decode($this->page->data);
            /**
             * Execute the controller for current page
             */
            if (!empty($this->page->type) && $this->page->type == 'web' && !empty($this->page->controller) && !empty($this->page->controller_method)) {
                $controller = File::on($this->databaseConnection)->where('uuid', $this->page->controller)->first();
                $method = Method::on($this->databaseConnection)->where('uuid', $this->page->controller_method)->first();
                if (!empty($method)) {
                    $methodData = json_decode($method->data, true);
                    $includes = [];
                    if (!empty($controller)) {
                        $controllerData = json_decode($controller->data, true);
                        $this->return = null;
                        if (!$this->debug) {
                            if ($this->root == 'https://stellisoft.com' && empty($this->user)) {
                                $this->return = $this->php($this->projectId, $request, $controllerData, $methodData, $this->variables, null, false);
                            } else {
                                $this->return = $this->php($this->project->uuid, $request, $controllerData, $methodData, $this->variables, null, false);
                            }
                        }
                        if (!empty($this->return)) {
                            if (method_exists($this->return, 'links') && gettype($this->return) == 'object') {
                                foreach($this->paginatorMethods as $paginatorMethod) {
                                    $this->variables[$paginatorMethod] = $this->return->{$paginatorMethod}();
                                }
                                $this->variables['output'] = $this->return;
                            } else {
                                if ($this->return instanceof \Illuminate\Database\Eloquent\Collection){
                                    $this->variables['output'] = $this->return;
                                } else if ($this->return instanceof \Illuminate\Database\Eloquent\Model) {
                                    foreach($this->return->getAttributes() as $key => $value) {
                                        $this->variables[$key] = $value;
                                    }
                                } else {
                                    $this->variables['output'] = $this->return;
                                }
                            }
                        }
                    }
                }
            }
            /**
             * Fetch global file includes
             */
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
            /**
             * Fetch global events
             */
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
            /**
             * Fetch file includes for current page
             */
            if (!empty($this->pageData->files)) {
                $jsFile = File::on($this->databaseConnection)->whereIn('uuid', $this->pageData->files)->first();
                if (!empty($jsFile)) {
                    $jsFileData = json_decode($jsFile->data, true);
                    if (!empty($jsFileData['variables'])) {
                        foreach($jsFileData['variables'] as $variable) {
                            if ($variable['value'] == '[]') {
                                $this->variables[$variable['name']] = [];
                            } else {
                                $this->variables[$variable['name']] = $variable['value'];
                            }
                        }
                    }
                    $this->files[$jsFileData['uuid']] = $jsFileData;
                    $jsMethods = Method::on($this->databaseConnection)->whereIn('uuid', $jsFileData['data'])->get();
                    if (!empty($jsMethods)) {
                        foreach($jsMethods as $jsMethod) {
                            if (!empty($jsMethod->data)) {
                                $jsMethodData = json_decode($jsMethod->data, true);
                                $this->methods[$jsMethodData['uuid']] = $jsMethodData;
                                $this->traverseStatement($jsMethodData['data']);
                                $jsStatements = Statement::on($this->databaseConnection)->whereIn('uuid', $this->statementHierarchy)->get();
                                foreach($jsStatements as $jsStatement) {
                                    $jsStatementData = json_decode($jsStatement->data, true);
                                    if (!empty($jsStatementData['data'])) {
                                        $jsClauses = Clause::on($this->databaseConnection)->whereIn('uuid', $jsStatementData['data'])->get();
                                        foreach($jsStatementData['data'] as $clause) {
                                            $jsClause = Clause::on($this->databaseConnection)->where('uuid', $clause)->first();
                                            $jsClauseData = json_decode($jsClause->data, true);
                                            $this->clauses[$jsClauseData['uuid']] = $jsClauseData;
                                        }
                                    }
                                    $this->statements[$jsStatementData['uuid']] = $jsStatementData;
                                }
                            }
                        }
                    }
                }
            }
            /**
             * Fetch meta data for current page
             */
            if (!empty($this->pageData->meta)) {
                $meta = Meta::on($this->databaseConnection)->whereIn('uuid', $this->pageData->meta)->get();
                if (!empty($meta)) {
                    foreach($meta as $tag) {
                        $metaData = json_decode($tag->data, true);
                        $this->meta[$metaData['uuid']] = $metaData;
                    }
                }
            }
            /**
             * Fetch the page data
             */
            if (!empty($this->pageData->data)) {
                $this->blockHierarchy = [];
                $this->projectId = $project;
                $this->project = new \stdClass();
                $this->project->uuid = $project;
                $this->traverseElements($this->pageData->data);
                if (!empty($this->blockHierarchy)) {
                    $blocks = Element::on($this->databaseConnection)->where('project_id', $this->project->uuid)->where(function ($query) {
                        $query->whereIn('uuid', $this->blockHierarchy);
                    })->get();
                    foreach($blocks as $block) {
                        $blockData = json_decode($block->data);
                        if (isset($blockData->anchor) && !empty($this->sectionRelation)) {
                            $blockData->data = $this->sectionRelation;
                        }
                        $this->data[$block->uuid] = $blockData;
                    }
                }
            }
            if ($this->root == 'https://stellisoft.com') {
                $this->production = true;
            }
            return view('page', [
                "settings" => !empty($this->settings['app']) ? $this->settings['app'] : null,
                "editMode" => $this->editMode ? 'edit' : '',
                "previewMode" => true,
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
                'production' => false
            ]);
        }
    }

    public function requestJavascript(Request $request) {
        $this->init($request);
        $uriSegments = explode('/', $this->path);
        $this->code = "";
        $variables = "";
        if (!empty($uriSegments) && is_array($uriSegments) && count($uriSegments) == 2) {
            if (!empty($this->user)) {
                $file = File::on($this->databaseConnection)->where(['name' => $uriSegments[1], 'project_id' => $this->project->uuid, 'type' => 'js'])->first();
            } else if ($this->root != 'https://stellisoft.com') {
                //site is in production and accessed via user's domain
                $file = File::on($this->databaseConnection)->where(['name' => $uriSegments[1], 'type' => 'js', 'public' => true])->first();
            } 
            if (empty($file)) {
                return abort(404);
            }
            $fileData = json_decode($file->data, true);
            if (!empty($fileData['imports'])) {
                $imports = File::on($this->databaseConnection)->whereIn('uuid', $fileData['imports'])->get();
                foreach($imports as $import) {
                    $importData = json_decode($import->data, true);
                    $this->constructJsMethods($importData);
                }
            } else {
                $this->constructJsMethods($fileData);
            }
        }
        if (!empty($fileData['variables'])) {
            foreach($fileData['variables'] as $variable) {
                $variables .= $variable['name'] . ': ' . $variable['value'];
            }
        }

        if (!empty($fileData['template']) && $fileData['template'] == 'vue') {
            $methods = $this->code;
            $beforeMount = '';
            $mounted = '';
            $computedMethods = '';
            if (!empty($this->methods)) {
                foreach($this->methods as $method) {
                    $this->code = '';
                    $methodData = json_decode($method->data, true);
                    if ($methodData['name'] == 'beforeMount') {
                        $this->code .= ', ';
                        $this->constructJsMethod($methodData);
                        $beforeMount = $this->code;
                    }
                    if ($methodData['name'] == 'mounted') {
                        $this->code .= ', ';
                        $this->constructJsMethod($methodData);
                        $mounted = $this->code;
                    }
                    if ($methodData['type'] == 'Computed') {
                        $this->constructJsMethod($methodData);
                        $computedMethods .= $this->code;
                    }
                }
            }
            $computed = '';
            if (!empty($computedMethods)) {
                $computed = <<<EOD
                    , computed: {
                        $computedMethods
                    }
                EOD;
            }
    
            $this->code = <<<EOD
            const { createApp } = Vue

            createApp({
                data() {
                    return {
                        $variables
                    }
                }
                $computed
                , methods: {
                    $methods
                }
                $beforeMount
                $mounted
            }).mount('#app')
            EOD;
        }

        if (!empty($fileData['template']) && $fileData['template'] == 'react') {
            $this->code = <<<EOD

                $this->code

                const container = document.getElementById('app');
                const root = ReactDOM.createRoot(container);
                root.render(<MyApp />);
            EOD;
        }

        //dd($this->code);
        return response($this->code, 200)
                ->header('Content-Type', 'application/javascript');
    }

    public function requestPhp(Request $request) {
        $this->init($request);
        $uriSegments = explode('/', $this->path);
        if (!empty($uriSegments) && is_array($uriSegments) && count($uriSegments) == 2) {
            if (!empty($this->user)) {
                $file = File::on($this->databaseConnection)->where(['name' => $uriSegments[1], 'project_id' => $this->project->uuid, 'type' => 'controller', 'public' => true])->first();
            }
            if (empty($file)) {
                return abort(404);
            }
            if (!empty($file)) {
                $fileData = json_decode($file->data, true);
                return $this->php($this->project->uuid, $request, $fileData, null, null, null, true);
            }
        }
    }

    public function constructJsMethods($fileData) {
        if (!empty($fileData['object'])) {
            $this->code .= "\n". $fileData['name'] . ": {\n";
        }
        $this->methods = Method::on($this->databaseConnection)->whereIn('uuid', $fileData['data'])->get();
        if (!empty($this->methods)) {
            foreach($this->methods as $method) {
                $methodData = json_decode($method->data, true);
                if (in_array($methodData['name'], $this->vueMethods) || in_array($methodData['type'], $this->vueTypes)) {
                    continue;
                }
                if (!empty($fileData['template']) && $fileData['template'] == 'vue') {
                    $this->code .= $methodData['name'] . "() {";
                } else {
                    $this->code .= "function " . $methodData['name'] . "(";
                    if (!empty($methodData['parameters'])) {
                        foreach($methodData['parameters'] as $parameterIndex => $parameter) {
                            $this->code .= $parameter['value'];
                            if (($parameterIndex + 1) < count($function['parameters'])){
                                $this->code .= ", ";
                            }
                        }
                    }
                    $this->code .= ") {";
                }
                if (!empty($methodData['data'])) {
                    $statements = Statement::on($this->databaseConnection)->whereIn('uuid', $methodData['data'])->get();
                    foreach($methodData['data'] as $uuid) {
                        $statement = $statements::where('uuid', $uuid)->first();
                        $statementData = json_decode($statement->data, true);
                        $this->constructJsStatement($statementData);
                    }
                }
                if (!empty($methodData['property'])) {
                    $this->code .= "\n},";
                } else {
                    $this->code .= "\n}\n\n";
                }
            }
        }
        if (!empty($fileData['object'])) {
            $this->code .= "\n}";
        }
    }

    public function constructJsMethod($methodData) {
        if (in_array($methodData['name'], $this->vueMethods) || in_array($methodData['type'], $this->vueTypes)) {
            $this->code .= $methodData['name'] . "(";
        } else {
            $this->code .= "function " . $methodData['name'] . "(";
        }
        if (!empty($methodData['parameters'])) {
            foreach($methodData['parameters'] as $parameterIndex => $parameter) {
                $this->code .= $parameter['value'];
                if (($parameterIndex + 1) < count($function['parameters'])){
                    $this->code .= ", ";
                }
            }
        }
        $this->code .= ") {\n";
        if (!empty($methodData['data'])) {
            $statements = Statement::on($this->databaseConnection)->whereIn('uuid', $methodData['data'])->get();
            foreach($methodData['data'] as $uuid) {
                $statement = $statements::where('uuid', $uuid)->first();
                $statementData = json_decode($statement->data, true);
                $this->constructJsStatement($statementData);
            }
        }
        if (in_array($methodData['name'], $this->vueMethods)) {
            $this->code .= "\n\n}";
        } else {
            $this->code .= "}\n\nr";
        }

    }

    public function constructJsStatement($statementData) {
        $this->code .= "\n\t\t";
        if (!empty($statementData['data'])) {
            foreach($statementData['data'] as $index => $clauseSlug) {
                $clause = Clause::on($this->databaseConnection)->where(['uuid' => $clauseSlug])->first();
                $clauseData = json_decode($clause->data, true);
                if ($clauseData['type'] == 'var') {
                    $this->code .= 'var ';
                }
                if ($clauseData['type'] == 'let') {
                    $this->code .= 'let ';
                }
                if ($clauseData['type'] == 'const') {
                    $this->code .= 'const ';
                }
                if ($clauseData['type'] == 'function') {
                    $this->code .= 'function ';
                }
                if ($clauseData['type'] == 'if') {
                    $this->code .= 'if ';
                }
                if ($clauseData['type'] == 'else') {
                    $this->code .= ' else ';
                }
                if ($clauseData['type'] == 'for') {
                    $this->code .= 'for ';
                }
                if ($clauseData['type'] == 'while') {
                    $this->code .= 'while';
                }
                if ($clauseData['type'] == 'do') {
                    $this->code .= 'do';
                }
                if ($clauseData['type'] == 'switch') {
                    $this->code .= 'switch';
                }
                if ($clauseData['type'] == 'case') {
                    $this->code .= 'case';
                }
                if ($clauseData['type'] == 'default') {
                    $this->code .= 'default';
                }
                if ($clauseData['type'] == 'return') {
                    $this->code .= 'return ';
                }
                if ($clauseData['type'] == 'break') {
                    $this->code .= 'break';
                }
                if ($clauseData['type'] == 'continue') {
                    $this->code .= 'continue';
                }
                if ($clauseData['type'] == 'throw') {
                    $this->code .= 'throw';
                }
                if ($clauseData['type'] == 'try') {
                    $this->code .= 'try';
                }
                if ($clauseData['type'] == 'catch') {
                    $this->code .= 'catch';
                }
                if ($clauseData['type'] == 'finally') {
                    $this->code .= 'finally';
                }
                if ($clauseData['type'] == 'class') {
                    $this->code .= 'class';
                }
                if ($clauseData['type'] == 'extends') {
                    $this->code .= 'extends';
                }
                if ($clauseData['type'] == 'super') {
                    $this->code .= 'super';
                }
                if ($clauseData['type'] == 'import') {
                    $this->code .= 'import';
                }
                if ($clauseData['type'] == 'export') {
                    $this->code .= 'export';
                }
                if ($clauseData['type'] == 'new') {
                    $this->code .= 'new';
                }
                if ($clauseData['type'] == 'this') {
                    $this->code .= 'this';
                }
                if ($clauseData['type'] == 'in') {
                    $this->code .= 'in';
                }
                if ($clauseData['type'] == 'instanceof') {
                    $this->code .= 'instanceof';
                }
                if ($clauseData['type'] == 'typeof') {
                    $this->code .= 'typeof';
                }
                if ($clauseData['type'] == 'void') {
                    $this->code .= 'void';
                }
                if ($clauseData['type'] == 'delete') {
                    $this->code .= 'delete';
                }
                if ($clauseData['type'] == 'async') {
                    $this->code .= 'async';
                }
                if ($clauseData['type'] == 'await') {
                    $this->code .= 'await';
                }
                if ($clauseData['type'] == 'yield') {
                    $this->code .= 'yield';
                }
                if ($clauseData['type'] == 'with') {
                    $this->code .= 'with';
                }
                if ($clauseData['type'] == 'true') {
                    $this->code .= 'true';
                }
                if ($clauseData['type'] == 'false') {
                    $this->code .= 'false';
                }
                if ($clauseData['type'] == 'null') {
                    $this->code .= 'null';
                }
                if ($clauseData['type'] == 'undefined') {
                    $this->code .= 'undefined';
                }
                if ($clauseData['type'] == 'nan') {
                    $this->code .= 'NaN';
                }
                if ($clauseData['type'] == 'infinity') {
                    $this->code .= 'Infinity';
                }
                if ($clauseData['type'] == 'addition') {
                    $this->code .= '+';
                }
                if ($clauseData['type'] == 'subtraction') {
                    $this->code .= '-';              
                }
                if ($clauseData['type'] == 'multiplication') {
                    $this->code .= '*';
                }
                if ($clauseData['type'] == 'division') {
                    $this->code .= '/';
                }
                if ($clauseData['type'] == 'modulus') {
                    $this->code .= '%';
                }
                if ($clauseData['type'] == 'increment') {
                    $this->code .= '++';
                }
                if ($clauseData['type'] == 'decrement') {
                    $this->code .= '--';
                }
                if ($clauseData['type'] == 'assignment') {
                    $this->code .= ' = ';
                }
                if ($clauseData['type'] == 'addition_assignment') {
                    $this->code .= ' += ';
                }
                if ($clauseData['type'] == 'subtraction_assignment') {
                    $this->code .= ' -= ';
                }
                if ($clauseData['type'] == 'multiplication_assignment') {
                    $this->code .= ' *= ';
                }
                if ($clauseData['type'] == 'division_assignment') {
                    $this->code .= ' /= ';
                }
                if ($clauseData['type'] == 'modulus_assignment') {
                    $this->code .= ' %= ';
                }
                if ($clauseData['type'] == 'equal') {
                    $this->code .= ' == ';
                }
                if ($clauseData['type'] == 'strict_equal') {
                    $this->code .= ' === ';
                }
                if ($clauseData['type'] == 'not_equal') {
                    $this->code .= ' != ';
                }
                if ($clauseData['type'] == 'strict_not_equal') {
                    $this->code .= ' !== ';
                }
                if ($clauseData['type'] == 'greater_than') {
                    $this->code .= ' > ';
                }
                if ($clauseData['type'] == 'greater_than_or_equal') {
                    $this->code .= ' >= ';
                }
                if ($clauseData['type'] == 'less_than') {
                    $this->code .= ' < ';
                }
                if ($clauseData['type'] == 'less_than_or_equal') {
                    $this->code .= ' <= ';
                }
                if ($clauseData['type'] == 'logical_and') {
                    $this->code .= ' && ';
                }
                if ($clauseData['type'] == 'logical_or') {
                    $this->code .= ' || ';
                }
                if ($clauseData['type'] == 'logical_not') {
                    $this->code .= '!';
                }
                if ($clauseData['type'] == 'bitwise_and') {
                    $this->code .= '&';
                }
                if ($clauseData['type'] == 'bitwise_or') {
                    $this->code .= '|';
                }
                if ($clauseData['type'] == 'bitwise_not') {
                    $this->code .= '~';
                }
                if ($clauseData['type'] == 'bitwise_xor') {
                    $this->code .= '^';
                }
                if ($clauseData['type'] == 'left_shift') {
                    $this->code .= '<<';
                }
                if ($clauseData['type'] == 'right_shift') {
                    $this->code .= '>>';
                }
                if ($clauseData['type'] == 'unsigned_right_shift') {
                    $this->code .= '>>>';
                }
                if ($clauseData['type'] == 'ternary') {
                    $this->code .= '?';
                }
                if ($clauseData['type'] == 'open_parenthesis') {
                    $this->code .= '(';
                }
                if ($clauseData['type'] == 'close_parenthesis') {
                    $this->code .= ')';
                }
                if ($clauseData['type'] == 'open_bracket') {
                    $this->code .= '[';
                }
                if ($clauseData['type'] == 'close_bracket') {
                    $this->code .= ']';
                }
                if ($clauseData['type'] == 'open_brace') {
                    $this->code .= '{';
                }
                if ($clauseData['type'] == 'close_brace') {
                    $this->code .= '}';
                }
                if ($clauseData['type'] == 'semicolon') {
                    $this->code .= ';';
                }
                if ($clauseData['type'] == 'colon') {
                    $this->code .= ':';
                }
                if ($clauseData['type'] == 'comma') {
                    $this->code .= ',';
                }
                if ($clauseData['type'] == 'period') {
                    $this->code .= '.';
                }
                if ($clauseData['type'] == 'start') {
                    $this->code .= '`';
                }
                if ($clauseData['type'] == 'end') {
                    $this->code .= '`';
                }
                if ($clauseData['type'] == 'placeholder_start') {
                    $this->code .= '${';
                }
                if ($clauseData['type'] == 'placeholder_end') {
                    $this->code .= '}';
                }
                if ($clauseData['type'] == 'variable') {
                    $this->code .= $clauseData['name'];
                }
                if ($clauseData['type'] == 'object') {
                    $this->code .= $clauseData['name'];
                }
                if ($clauseData['type'] == 'method') {
                    $this->code .= $clauseData['name'] . '(';
                    if (!empty($clauseData['parameters'])) {
                        foreach($clauseData['parameters'] as $parameter) {
                            if ($parameter['type'] == 'variable') {
                                $this->code .= '$' . $parameter['name'] . ', ';
                            } else if ($parameter['type'] == 'string') {
                                $this->code .= "'" . $parameter['value'] . "', ";
                            } else {
                                $this->code .= $parameter['value'] . ', ';
                            }
                        }
                        $this->code = rtrim($this->code, ', ');
                    }
                    $this->code .= ');';
                }
                if ($clauseData['type'] == 'property') {
                    $this->code .= $clauseData['name'];
                }
                if ($clauseData['type'] == 'element') {
                    $this->code .= $clauseData['name'];
                }
                if ($clauseData['type'] == 'number') {
                    $this->code .= $clauseData['value'];
                }
                if ($clauseData['type'] == 'boolean') {
                    $this->code .= $clauseData['value'];                
                }
                if ($clauseData['type'] == 'string') {
                    $this->code .= "'".$clauseData['value']."'";
                }
                if ($clauseData['type'] == 'key') {
                    $this->code .= $clauseData['value'];
                }
            }
        }
    }

    public function generateSitemap(Request $request) {
        $this->init($request);
        $pages = [];
        $pages = Route::on($this->databaseConnection)->where(['public' => 1])->get();
        foreach($pages as $page) {
            $pageData = json_decode($page->data, true);
            if (!empty($pageData)) {
                if (!empty($pageData['children'])) {
                    $templatePages = Route::on($this->databaseConnection)->whereIn('uuid', $pageData['children'])->get();
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

    public function populateStyles() {
        if (file_exists(base_path() . '/tailwind-insert.sql')) {
            // Load the JSON file
            $json = file_get_contents(base_path() . '/tailwind-classes.json');
            $classes = json_decode($json, true);

            // Start the INSERT query
            $query = "INSERT INTO styles (name, category, rule, data) VALUES\n";

            // Build values for each class
            $values = [];
            foreach ($classes as $class) {
                $name = $class['name'];
                if (!empty($class['rule'])) {
                    $rule = $class['rule'];
                } else {
                    $rule = null;
                }
                if (!empty($class['category'])) {
                    $category = $class['category'];
                } else {
                    $category = null;
                }
                $data = json_encode($class['data'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $values[] = "('$name', '$category', '$rule', '$data')";
            }

            // Combine values and complete the query
            $query .= implode(",\n", $values) . ";";

            // Save the query to a file
            file_put_contents(base_path() . '/tailwind-insert.sql', $query);

            dd("SQL query saved to tailwind-insert.sql");
        } else {
            dd('Generate data file.');
        }
    }

    public function constructCSS() {
        $classes = Style::where('category', '')->whereIn('name', $this->classes)->get();
        $this->classes = [];
        foreach($classes as $class) {
            $this->rules[$class['name']] = $class['rule'];
            $this->classes[$class['name']] = $class['data'];
            if ($class['name'] == 'prose') {
                $proseClasses = Style::where('category', 'prose')->get();
            }
        }
        foreach($this->baseClasses as $breakpointName => $breakpoint) {
            if ($breakpointName == 'sm:' && !empty($breakpoint)) {
                $this->css .= ' @media (min-width: 640px) {';
            }
            if ($breakpointName == 'md:' && !empty($breakpoint)) {
                $this->css .= ' @media (min-width: 768px) {';
            }
            if ($breakpointName == 'lg:' && !empty($breakpoint)) {
                $this->css .= ' @media (min-width: 1024px) {';
            }
            if ($breakpointName == 'xl:' && !empty($breakpoint)) {
                $this->css .= ' @media (min-width: 1280px) {';
            }
            if ($breakpointName == '2xl:' && !empty($breakpoint)) {
                $this->css .= ' @media (min-width: 1536px) {';
            }
            foreach($breakpoint as $className => $classValue) {
                $classValue = null;
                if (
                    $breakpointName == 'sm:' || 
                    $breakpointName == 'md:' ||
                    $breakpointName == 'lg:' ||
                    $breakpointName == 'xl:' ||
                    $breakpointName == '2xl:'
                ) {
                    if (!empty($this->classes[substr($className, 3)])) {
                        $classValue = $this->classes[substr($className, 3)];
                        $className = str_replace(":", "\\:", $className);
                    }
                } else {
                    if (!empty($this->classes[$className])) {
                        $classValue = $this->classes[$className];
                    }
                }
                //handle classes such as py-2.5 by escaping .
                if (str_contains($className, '.')) {
                    $className = str_replace('.', '\\.', $className);
                }
                
                if (!empty($classValue)) {
                    $styles = json_decode($classValue, true);
                    $count = count($styles);
                    if ($count > 1) {
                        $classValue = str_replace('}', ';}', str_replace(',', ';', $classValue));
                    }
                    $this->css .= '.'.$className . (!empty($this->rules[$className]) ? $this->rules[$className] : '') . str_replace('}', ';}', str_replace('"', '', $classValue));
                }
            }
            if ($breakpointName != 'nobreakpoint') {
                $this->css .= '}';
            }
        }
        if (!empty($proseClasses)) {
            foreach($proseClasses as $proseClass) {
                $this->css .= '.'.$proseClass['name'] . (!empty($proseClass['rule']) ? ' ' . $proseClass['rule'] : '') . str_replace('}', ';}', str_replace(',', ';', str_replace('"', '', $proseClass['data'])));
            }
        }
        file_put_contents('css/' . str_replace('/', '-', $this->path == '/' ? 'home' : $this->path) . '.css', $this->css);
        redirect($this->path);
    }

    public function documentation($page = 'index')
    {
        $view = "documentation.{$page}";
        
        if (!view()->exists($view)) {
            abort(404);
        }
        
        return view($view);
    }

    public function application($page = 'index')
    {
        $view = "api.{$page}";
        if (!view()->exists($view)) {
            abort(404);
        }

        $path = request()->path();
            
        // Define menu items and their positions
        $menuItems = [
            'stellify/api/projects' => 0,
            'stellify/api/users' => 1,
            'stellify/api/config' => 2,
            'stellify/api/routes' => 3,
            'stellify/api/elements' => 4,
            'stellify/api/definitions' => 5,
            'stellify/api/files' => 6,
            'stellify/api/methods' => 7,
            'stellify/api/statements' => 8,
            'stellify/api/clauses' => 9,
            'stellify/api/database' => 10,
            'stellify/api/utilities' => 11,
        ];

        $activeMenuItem = $menuItems[$path] ?? 0;
        return view($view, [
            'activeMenuItem' => $activeMenuItem,
            'path' => $path,
        ]);
    }

    public function show($page = 'index')
    {
        $view = "general.{$page}";
        if (!view()->exists($view)) {
            abort(404);
        }
        
        return view($view);
    }
}