<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Models\User;
use App\Models\Page;
use App\Models\Element;
use App\Models\Project;
use App\Models\Setting;
use App\Models\File;
use App\Models\Directory;
use App\Models\Method;
use App\Models\Statement;
use App\Models\Clause;
use App\Models\Meta;
use App\Models\Illuminate;
use App\Models\UserAccess;

use App\Mail\DynamicMail;

use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\Matching\UriValidator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class StellifyController extends Controller implements HasMiddleware
{
    private $code;
    private $body;
    private $data;
    private $files;
    private $directories;
    private $methods;
    private $statements;
    private $clauses;
    private $variables;
    private $user;
    private $rootUser;
    private $users;
    private $root;
    private $path;
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
    private $config;
    private $fonts;
    private $views;
    private $dbConnected;
    private $blockHierarchy;
    private $pageHierarchy;
    private $statementHierarchy;
    private $paginatorMethods;

    protected function init(Request $request) {
        try {
            $this->root = $request->root();
            $this->path = $request->path();
            $this->uriSegments = explode('/', $this->path);
            $this->page = [];
            $this->navPages = [];
            $this->routes = new RouteCollection;
            $this->url = $request->url();
            $this->user = Auth::user();
            $this->users = [];
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
            $this->variables = ['currentParent' => null];
            $this->profile = null;
            $this->project = null;
            $this->meta = [];
            $this->classes = [];
            $this->rules = [];
            $this->css = '';
            $this->settings = [];
            $this->config = [];
            $this->events = ['beforeMount', 'mounted', 'beforeUnmount', 'unmounted'];
            $this->blockHierarchy = [];
            $this->databaseConnection = 'mysql';
            $this->navSlugs = [];
            $this->pageHierarchy = [];
            $this->statementHierarchy = [];
            $this->paginatorMethods = ["currentPage", "hasMorePages", "hasPages", "lastItem", "lastPage", "nextPageUrl", "perPage", "previousPageUrl", "total"];
            $this->code = "";
            $this->functions = null;
            $this->vueMethods = ['beforeMount', 'mounted'];
            $this->vueTypes = ['Computed'];
            $this->rootUser = false;

            /**
             * Fetch site settings
             */
            $settings = Setting::on($this->databaseConnection)->where(['active_domain' => $this->root])->get()->keyBy('name');

            /**
             * Log all requests made to the application and block requests where needed
             */
            if ($settings->trackUserAccess) {
                $datetime = \Carbon\Carbon::now();
                $ip_address = $request->ip();
                $userAccessing = UserAccess::on($this->databaseConnection)->updateOrCreate(
                    ['ip_address' => $ip_address],
                    ['user_id' => !empty($this->user) ? $this->user->id : null, 'ip_address' => $request->ip(), 'last_accessed_at' => $datetime]
                );
                if ($userAccessing->blocked) {
                    return abort(404);
                }
                if ($userAccessing->tracked) {
                    UserAccess::on($this->databaseConnection)->where(['ip_address' => $ip_address])->increment('visits', 1);
                }
            }

            /**
             * Match route
             */
            $routes = Page::on($this->databaseConnection)->get();
            $routesObjects = [];
            foreach($routes as $route) {
                $currentRoute = new Route($route->method, $route->path, []);
                $match = $currentRoute->matches($request);
                if ($match) {
                    $this->page = $route;
                    $routePath = explode('/', $route->path);
                    $requestPath = explode('/', $this->path);
                    //store injected params as variables
                    foreach ($routePath as $index => $parameter) {
                        if (!empty($parameter[0]) && $parameter[0] == '{') {
                            preg_match('/(?<={).*?(?=})/', $parameter, $match);
                            $this->variables[$match[0]] = $requestPath[$index];
                        }
                    }
                }
            }

            /**
             * Fetch the requested page data
             */
            if (!empty($this->page)) {
                $this->pageData = json_decode($this->page->data);

                /**
                 * Execute the controller for requested page
                 */
                if (!empty($this->page->type) && $this->page->type == 'webpage' && !empty($this->pageData->controller) && !empty($this->pageData->controllerMethod)) {
                    $controller = File::on($this->databaseConnection)->where('slug', $this->pageData->controller)->first();
                    $method = Method::on($this->databaseConnection)->where('slug', $this->pageData->controllerMethod)->first();
                    if (!empty($method)) {
                        $methodData = json_decode($method->data, true);
                        $includes = [];
                        if (!empty($controller)) {
                            $controllerData = json_decode($controller->data, true);
                            $this->return = null;
                            if (!$this->debug) {
                                $this->return = $this->php($this->project->slug, $request, $controllerData, $methodData, $this->variables, null);
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
                 * Assemble navigation tree
                 */
                if (!empty($this->settings['app']['navigation']) && is_array($this->settings['app']['navigation'])) {
                    $this->traversePages($this->settings['app']['navigation']);
                    $this->navPages = Page::on($this->databaseConnection)->whereIn('slug', $this->pageHierarchy)->get();
                    if (!empty($this->navPages)) {
                        $this->variables['navigation'] = [];
                        foreach($this->settings['app']['navigation'] as $slug) {
                            $link = $this->navPages->where('slug', $slug)->first();
                            $linkData = json_decode($link->data, true);
                            if (!empty($this->uriSegments[1]) && $linkData['path'] == $this->uriSegments[1]) {
                                $linkData['activeRoute'] = true;
                            }
                            $this->variables['navigation'][$linkData['slug']] = $linkData;
                            if (!empty($linkData['path']) && !empty($linkData['children'])) {
                                $this->createNestedNav($linkData['path'], $linkData['children']);
                            }
                        }
                    }
                }


                /**
                 * When a page is constructed in the browser i.e. not SSR, Stellify uses VueJS components to render the page
                 * using Stellify's JSON representation of the DOM. There is also a means of using JS to manipulate this data
                 * internally, in other words, within the VueJS application that renders the page. The following blocks of code
                 * are concerned with importing files that perform these manipulations.
                 */

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
                 * Fetch internal js files for current page
                 */
                if (!empty($this->pageData->files)) {
                    $jsFile = File::on($this->databaseConnection)->whereIn('slug', $this->pageData->files)->first();
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
                        $this->files[$jsFileData['slug']] = $jsFileData;
                        $jsMethods = Method::on($this->databaseConnection)->whereIn('slug', $jsFileData['data'])->get();
                        if (!empty($jsMethods)) {
                            foreach($jsMethods as $jsMethod) {
                                if (!empty($jsMethod->data)) {
                                    $jsMethodData = json_decode($jsMethod->data, true);
                                    $this->methods[$jsMethodData['slug']] = $jsMethodData;
                                    $this->traverseStatement($jsMethodData['data']);
                                    $jsStatements = Statement::on($this->databaseConnection)->whereIn('slug', $this->statementHierarchy)->get();
                                    foreach($jsStatements as $jsStatement) {
                                        $jsStatementData = json_decode($jsStatement->data, true);
                                        if (!empty($jsStatementData['data'])) {
                                            $jsClauses = Clause::on($this->databaseConnection)->whereIn('slug', $jsStatementData['data'])->get();
                                            foreach($jsStatementData['data'] as $clause) {
                                                $jsClause = $jsClauses->where('slug', $clause)->first();
                                                $jsClauseData = json_decode($jsClause->data, true);
                                                $this->clauses[$jsClauseData['slug']] = $jsClauseData;
                                            }
                                        }
                                        $this->statements[$jsStatementData['slug']] = $jsStatementData;
                                    }
                                }
                            }
                        }
                    }
                }

                /**
                 * Include any global events that are to be fired before, during and after the page has loaded
                 */
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

                /**
                 * Fetch meta data for current page
                 */
                if (!empty($this->pageData->meta)) {
                    $meta = Meta::on($this->databaseConnection)->whereIn('slug', $this->pageData->meta)->get();
                    if (!empty($meta)) {
                        foreach($meta as $tag) {
                            $metaData = json_decode($tag->data, true);
                            $this->meta[$metaData['slug']] = $metaData;
                        }
                    }
                }

                /**
                 * Fetch layout elements
                 */
                if (!empty($this->pageData->layout)) {
                    if (!empty($this->uriSegments[1])) {
                        $section = Page::on($this->databaseConnection)->where(['path' => $this->uriSegments[1]])->first();
                        if (!empty($section)) {
                            $sectionData = json_decode($section->data);
                            if (!empty($sectionData->data) && !empty($sectionData->data[0])) {
                                $this->sectionRelation = $sectionData->data;
                            }
                            $this->blockHierarchy = [];
                            $this->traverseBlocks($sectionData->data);
                            $sectionBlocks = Element::on($this->databaseConnection)->whereIn('slug', $this->blockHierarchy)->get();
                            foreach($sectionBlocks as $block) {
                                $blockData = json_decode($block->data);
                                $this->data[$block->slug] = $blockData;
                            }
                        }
                    }
                }

                /**
                 * Fetch page elements
                 */
                if (!empty($this->pageData->data)) {
                    $this->blockHierarchy = [];
                    $this->traverseBlocks($this->pageData->data);
                    if (!empty($this->blockHierarchy)) {
                        $blocks = Element::on($this->databaseConnection)->whereIn('slug', $this->blockHierarchy)->get();
                        foreach($blocks as $block) {
                            $blockData = json_decode($block->data);
                            if ($request->has('generateCSS') && !empty($blockData->classes)) {
                                $breakpoints = ['sm:', 'md:', 'lg:', 'xl:', '2xl:'];
                                foreach($blockData->classes as $class) {
                                    if (in_array(substr($class, 0, 3), $breakpoints)) {
                                        array_push($this->classes, substr($class, 3));
                                        $this->baseClasses[substr($class, 0, 3)][$class] = null;
                                    } else {
                                        array_push($this->classes, $class);
                                        $this->baseClasses['nobreakpoint'][$class] = null;
                                    }
                                }
                            }
                            if (isset($blockData->anchor) && !empty($this->sectionRelation)) {
                                $blockData->data = $this->sectionRelation;
                            }
                            $this->data[$block->slug] = $blockData;
                        }
                        if ($request->has('generateCSS') && !empty($this->classes)) {
                            $this->constructCSS();
                        } else if (file_exists(resource_path('css/' . str_replace('/', '-', $this->path == '/' ? 'home' : $this->path) . '.css'))) {
                            $this->css = file_get_contents(resource_path('css/' . str_replace('/', '-', $this->path == '/' ? 'home' : $this->path) . '.css'));
                        }
                    }
                }
            }

        } catch (\Throwable $e) {
            return redirect()->back()->with('error');
        }
    }

    public static function middleware(): array {
        $middleware = [];
        $page = [];
        $root = \Request::root();
        $request = request();
        $routes = Page::on($this->databaseConnection)->where(['method' => 'GET', 'public' => true])->get();
        foreach($routes as $route) {
            $currentRoute = new Route($route->method, $route->path, []);
            $match = $currentRoute->matches($request);
            if ($match) {
                $page = $route;
                $routePath = explode('/', $route->path);
                $requestPath = explode('/', $request->path);
                foreach ($routePath as $index => $parameter) {
                    if (!empty($parameter[0]) && $parameter[0] == '{') {
                        preg_match('/(?<={).*?(?=})/', $parameter, $match);
                        $this->variables[$match[0]] = $requestPath[$index];
                    }
                }
            }
        }
        if (!empty($page)) {
            $pageData = json_decode($page->data, true);
            if (!empty($pageData['middleware']) && is_array($pageData['middleware'])) {
                $middleware = $pageData['middleware'];
            }
        }
        if (!empty($request->input('middleware')) && is_array($request->input('middleware'))) {
            $middleware = $request->input('middleware');
        }
        return $middleware;
    }

    protected function createNestedNav($parent, $children) {
        $this->variables[$parent] = [];
        foreach($children as $slug) {
            $link = $this->navPages->where('slug', $slug)->first();
            $linkData = json_decode($link->data, true);
            if (!empty($this->uriSegments[1]) && $linkData['path'] == $this->uriSegments[1]) {
                $linkData['activeRoute'] = true;
                $this->variables['currentParent'] = $parent;
            }
            $this->variables[$parent][$linkData['slug']] = $linkData;
            if (!empty($linkData['path']) && !empty($linkData['children'])) {
                $this->createNestedNav($linkData['path'], $linkData['children']);
            }
        }
    }

    protected function traversePages($pages) {
        foreach($pages as $slug) {
            array_push($this->pageHierarchy, $slug);
            $page = Page::on($this->databaseConnection)->where(['slug' => $slug])->first();
            if (!empty($page->data)) {
                $pageData = json_decode($page->data);
                if (!empty($pageData->children)) {
                    $this->traversePages($pageData->children);
                }
            }
        }
        return;
    }

    protected function traverseBlocks($blocks) {
        foreach($blocks as $slug) {
            array_push($this->blockHierarchy, $slug);
            $block = Element::on($this->databaseConnection)->where(['slug' => $slug])->first();
            if (!empty($block->data)) {
                $blockData = json_decode($block->data);
                if (!empty($blockData->data)) {
                    $this->traverseBlocks($blockData->data);
                }
            }
        }
        return;
    }

    protected function traverseStatement($statements) {
        foreach($statements as $slug) {
            array_push($this->statementHierarchy, $slug);
            $statement = Statement::on($this->databaseConnection)->where('slug', $slug)->first();
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
        $this->init($request);
        //not valid resource
        if (empty($this->page)) {
            return abort(404);
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
        if ($this->page->redirectUrl) {
            return redirect($this->page->redirectUrl, $this->page->statusCode ? $this->page->statusCode : '302');
        }
        $previous = $request->old();
        if (!empty($previous)) {
            foreach ($previous as $key => $value) {
                $this->variables[$key] = $value;
            }
        }
        $errors = $request->session()->get('errors');
        if (!empty($errors)) {
            foreach ($errors->getMessages() as $key => $error) {
                if ($key == 'stellifyError') {
                    $this->editor['message'] = $error[0];
                } else {
                    $this->variables[$key . '-error'] = $error;
                }
            }
        }
        if (!empty($this->page->type) && $this->page->type == 'mail') {
            $html = view('email', [
                'body' => !empty($this->pageData) ? $this->pageData : null, 
                'content' => !empty($this->data) ? $this->data : null, 
                'settings' => !empty($this->settings['app']) ? $this->settings['app'] : null,
                "variables" => !empty($this->variables) ? $this->variables : null
            ])->render();
            return new DynamicMail($html);
        } else if (!empty($this->page->type) && $this->page->type == 'api') {
            $controller = File::on($this->databaseConnection)->where('slug', $this->pageData->controller)->first();
            $methods = Method::on($this->databaseConnection)->where('slug', $this->pageData->controllerMethod)->get();
            if (!empty($methods)) {
                foreach($methods as $method) {
                    $methodData = json_decode($method->data, true);
                    $includes = [];
                    if (!empty($controller)) {
                        $controllerData = json_decode($controller->data, true);
                        if (!$this->debug) {
                            $this->return = $this->php($this->project->slug, $request, $controllerData, $methodData, $this->variables, $this->pageData->type, false);
                            return $this->return;
                        }
                    }
                }
            }
        } else {
            return view('page', [
                "settings" => !empty($this->settings['app']) ? $this->settings['app'] : null,
                "meta" => !empty($this->meta) ? $this->meta : null,
                "route" => !empty($this->route) ? $this->route : null,
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
                "path" => ($this->path == '/' ? 'home' : $this->path)
            ]);
        }
    }

    public function requestJavascript(Request $request) {
        $this->init($request);
        $uriSegments = explode('/', $this->path);
        $this->code = "";
        $variables = "";
        if (!empty($uriSegments) && is_array($uriSegments) && count($uriSegments) == 2) {
            $file = File::on($this->databaseConnection)->where(['name' => $uriSegments[1], 'type' => 'js', 'public' => true])->first(); 
            if (empty($file)) {
                return abort(404);
            }
            $fileData = json_decode($file->data, true);
            if (!empty($fileData['imports'])) {
                $imports = File::on($this->databaseConnection)->whereIn('slug', $fileData['imports'])->get();
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

        return response($this->code, 200)
                ->header('Content-Type', 'application/javascript');
    }

    public function requestPhp(Request $request) {
        $this->init($request);
        $uriSegments = explode('/', $this->path);
        if (!empty($uriSegments) && is_array($uriSegments) && count($uriSegments) == 2) {
            $file = File::on($this->databaseConnection)->where(['name' => $uriSegments[1], 'type' => 'controller', 'public' => true])->first();
            if (empty($file)) {
                return abort(404);
            }
            if (!empty($file)) {
                $fileData = json_decode($file->data, true);
                return $this->php($this->project->slug, $request, $fileData, null, null, null, true);
            }
        }
    }

    public function constructJsMethods($fileData) {
        if (!empty($fileData['object'])) {
            $this->code .= "\n". $fileData['name'] . ": {\n";
        }
        $this->methods = Method::on($this->databaseConnection)->whereIn('slug', $fileData['data'])->get();
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
                    $statements = Statement::on($this->databaseConnection)->whereIn('slug', $methodData['data'])->get();
                    foreach($methodData['data'] as $slug) {
                        $statement = $statements->where('slug', $slug)->first();
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

    public function constructPhpMethods($fileData) {
        if (!empty($fileData['object'])) {
            $this->code .= "\n". $fileData['name'] . ": {\n";
        }
        $this->methods = Method::on($this->databaseConnection)->whereIn('slug', $fileData['data'])->get();
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
                    $statements = Statement::on($this->databaseConnection)->whereIn('slug', $methodData['data'])->get();
                    foreach($methodData['data'] as $slug) {
                        $statement = $statements->where('slug', $slug)->first();
                        $statementData = json_decode($statement->data, true);
                        $this->constructPhpStatement($statementData);
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
            $statements = Statement::on($this->databaseConnection)->whereIn('slug', $methodData['data'])->get();
            foreach($methodData['data'] as $slug) {
                $statement = $statements->where('slug', $slug)->first();
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
                $clause = Clause::on($this->databaseConnection)->where(['slug' => $clauseSlug])->first();
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
                if ($clauseData['type'] == 'string') {
                    $this->code .= "'".$clauseData['value']."'";
                }
                if ($clauseData['type'] == 'variable') {
                    $this->code .= $clauseData['name'];
                }
                if ($clauseData['type'] == 'object') {
                    $this->code .= $clauseData['name'];
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
                if ($clauseData['type'] == 'key') {
                    $this->code .= $clauseData['value'];
                }
            }
        }
    }

    public function php($project, $request, $controllerData, $methodData, $variables, $type, $returnCode) {
        try {
            if (\DB::connection('mysql2')->getDatabaseName()) {
                $includes = '';
                if (!empty($controllerData['includes'])) {
                    foreach($controllerData['includes'] as $include) {
                        $includeLibrary = Illuminate::on($this->databaseConnection)->where('slug', $include)->first();
                        $includes .= 'use ' . $includeLibrary->name . ';' . "\n";
                    }
                }
                if (!empty($controllerData['models'])) {
                    foreach($controllerData['models'] as $include) {
                        $includeModel = File::on($this->databaseConnection)->where(['slug' => $include, 'project_id' => $project])->first();
                        $includes .= 'use App\\Models\\' . $includeModel->name . ';' . "\n";
                        $instanceVariables = '';
                        if (!empty($includeModel)) {
                            $includeModelData = json_decode($includeModel->data, true);
                            $includeModelMethodString = '';
                            if (!empty($includeModelData['data'])) {
                                $includeMethods = Method::on($this->databaseConnection)->where('project_id', $project)->where(function ($query) use ($includeModelData) {
                                    $query->whereIn('slug', $includeModelData['data']);
                                })->first();
                            }
                            $includeModelName = $includeModel->name;
                            $guarded = '';
                            $fillable = '';
                            $timestamps = 'false';
                            if (!empty($includeModelData['variables'])) {
                                foreach($includeModelData['variables'] as $key) {
                                    $value = '';
                                    if (isset($key['value'])) {
                                        if (is_bool($key['value'])) {
                                            if ($key['value'] == false) {
                                                $value = 'false';
                                            } else {
                                                $value = 'true';
                                            }
                                        } else if (is_array($key['value'])) {
                                            $value = (string) $key['value'];
                                        } else {
                                            $value = $key['value'];
                                        }
                                    }
                                    $instanceVariables .= $key['scope'] . ' $' . $key['name'] . ' = ' . $value . ";\n\t\t";
                                }
                            }
                            $modelIncludeTemplate = <<<EOD
                            namespace App\Models; 
                            use Illuminate\Database\Eloquent\Model;
                            class $includeModelName extends Model {
                                protected \$connection= 'mysql2';
                                protected \$fillable = [$fillable];
                                public \$timestamps = $timestamps;

                                public function setTable(\$tableName)
                                {
                                    \$this->table = \$tableName;
                                }
                            }
                            EOD;
                            eval($modelIncludeTemplate);
                        }
                    }
                }

                //define variables
                $controllerInstanceVariables = '';
                if (!empty($controllerData['variables'])) {
                    foreach($controllerData['variables'] as $key) {
                        $value = '';
                        if (isset($key['value'])) {
                            if (is_bool($key['value'])) {
                                if ($key['value'] == false) {
                                    $value = 'false';
                                } else {
                                    $value = 'true';
                                }
                            } else if (is_array($key['value'])) {
                                $value = (string) $key['value'];
                            } else {
                                $value = $key['value'];
                            }
                        }
                        $controllerInstanceVariables .= $key['scope'] . ' $' . $key['name'] . ' = ' . $value . ";\n\t\t";
                    }
                }
                //Construct method
                if (!empty($methodData)) {
                    $this->code = '';
                    if (empty($methodData['scope'])) {
                        $this->code .= 'public';
                    } else {
                        $this->code .= $methodData['scope'];
                    }
                    $this->code .= ' function ';
                    $this->code .= $methodData['name'];
                    $this->code .= '(';
                    if (!empty($methodData['parameters'])) {
                        foreach($methodData['parameters'] as $parameterIndex => $parameter) {
                            if (!empty($parameter['class'])) {
                                $segments = explode("\\", $parameter['name']);
                                $modelName = $segments[count($segments) - 1];
                                $this->code .= \Str::ucfirst($modelName) . ' ';
                            }
                            $this->code .= '$' . $parameter['name'];
                            if (!empty($parameter['parameters'][$parameterIndex + 1]) && !empty($methodData['parameters'][$parameterIndex + 1]['name'])) {
                                $this->code .= ', ';
                            }
                        }
                    }
                    $this->code .= ") {\n\t\t";
                    if (!empty($methodData['variables'])) {
                        foreach($methodData['variables'] as $variableIndex => $variable) {
                            //dd($variable);
                            if ($variable['type'] == 'string') {
                                $this->code .= '$' . $variable['name'] . ' = "' . $variable['value'] . '";';
                            } else {
                                $this->code .= '$' . $variable['name'] . ' = ' . $variable['value'] . ';';
                            }
                        }
                    }
                    if (!empty($methodData['data'])) {
                        $statements = Statement::on($this->databaseConnection)->whereIn('slug', $methodData['data'])->get();
                        foreach($methodData['data'] as $slug) {
                            $statement = $statements->where('slug', $slug)->first();
                            $statementData = json_decode($statement->data, true);
                            dd($statementData);
                            $this->constructPhpStatement($statementData);
                        }
                    }
                    $this->code .= "\n\t";
                    $this->code .= "}";
                } else if (!empty($controllerData['data'])) {
                    $this->constructPhpMethods($controllerData);
                }

                $controllerName = $controllerData['name'];

                $controllerTemplate = <<<EOD
                namespace App\Http\Controllers;
                use App\Http\Controllers\Controller;
                $includes
                class $controllerName extends Controller 
                {
                    $controllerInstanceVariables

                    $this->code
                }
                EOD;
                if ($returnCode) {
                    return $controllerTemplate;
                } else {
                    eval($controllerTemplate);
                    $fullyQualifiedClassName = "\\App\\Http\\Controllers\\$controllerName";
                    $dynamicController = new $fullyQualifiedClassName();
                    if ($type == 'api') {
                        return $dynamicController->{$methodData['name']}($request);
                    } elseif (!empty($methodData['parameters']) && is_array($methodData['parameters']) && count($methodData['parameters'])) {
                        return $dynamicController->{$methodData['name']}($request);
                    } else {
                        return $dynamicController->{$methodData['name']}();
                    }
                }
            }
        } catch (\Throwable $e) {
            //report($e);
            $this->editor['message'] = $e->getMessage();
        }
    }

    public function constructPhpStatement($statementData) {
        $this->code .= "\n\t\t";
        if (!empty($statementData['data'])) {
            foreach($statementData['data'] as $index => $clauseSlug) {
                $clause = Clause::on($this->databaseConnection)->where(['slug' => $clauseSlug])->first();
                $clauseData = json_decode($clause->data, true);
                if ($clauseData['type'] == 'T_FUNCTION') {
                    $this->code .= 'function';
                }
                if ($clauseData['type'] == 'T_PUBLIC') {
                    $this->code .= 'public ';
                }
                if ($clauseData['type'] == 'T_PROTECTED') {
                    $this->code .= 'protected ';
                }
                if ($clauseData['type'] == 'T_PRIVATE') {
                    $this->code .= 'private ';
                }
                if ($clauseData['type'] == 'T_STATIC') {
                    $this->code .= 'static ';
                }
                if ($clauseData['type'] == 'T_STRING') {
                    $this->code .= '(string) ';
                }
                if ($clauseData['type'] == 'T_BOOLEAN') {
                    $this->code .= '(bool) ';
                }
                if ($clauseData['type'] == 'T_ELSE') {
                    $this->code .= ' else ';
                }
                if ($clauseData['type'] == 'T_ELSEIF') {
                    $this->code .= 'elseif ';
                }
                if ($clauseData['type'] == 'T_FOREACH') {
                    $this->code .= 'foreach ';
                }
                if ($clauseData['type'] == 'T_FOR') {
                    $this->code .= 'for ';
                }
                if ($clauseData['type'] == 'T_WHILE') {
                    $this->code .= 'while ';
                }
                if ($clauseData['type'] == 'T_DO') {
                    $this->code .= 'do ';
                }
                if ($clauseData['type'] == 'T_NEW') {
                    $this->code .= 'new ';
                }
                if ($clauseData['type'] == 'T_AND_EQUAL') {
                    $this->code .= '&=';
                }
                if ($clauseData['type'] == 'T_OR_EQUAL') {
                    $this->code .= '|=';
                }
                if ($clauseData['type'] == 'T_XOR_EQUAL') {
                    $this->code .= '^=';
                }
                if ($clauseData['type'] == 'T_PLUS_EQUAL') {
                    $this->code .= '+=';
                }
                if ($clauseData['type'] == 'T_MINUS_EQUAL') {
                    $this->code .= '-=';
                }
                if ($clauseData['type'] == 'T_MUL_EQUAL') {
                    $this->code .= '*=';
                }
                if ($clauseData['type'] == 'T_DIV_EQUAL') {
                    $this->code .= '/=';
                }
                if ($clauseData['type'] == 'T_MOD_EQUAL') {
                    $this->code .= '%=';
                }
                if ($clauseData['type'] == 'T_COALESCE') {
                    $this->code .= '??';
                }
                if ($clauseData['type'] == 'T_SPACESHIP') {
                    $this->code .= '<=>';
                }
                if ($clauseData['type'] == 'T_IS_EQUAL') {
                    $this->code .= '==';
                }
                if ($clauseData['type'] == 'T_IS_NOT') {
                    $this->code .= '!';
                }
                if ($clauseData['type'] == 'T_IS_NOT_EQUAL') {
                    $this->code .= '!=';
                }
                if ($clauseData['type'] == 'T_IS_IDENTICAL') {
                    $this->code .= '===';
                }
                if ($clauseData['type'] == 'T_IS_NOT_IDENTICAL') {
                    $this->code .= '!==';
                }
                if ($clauseData['type'] == 'T_GREATER_EQUAL') {
                    $this->code .= '>=';
                }
                if ($clauseData['type'] == 'T_LESS_EQUAL') {
                    $this->code .= '<=';
                }
                if ($clauseData['type'] == 'T_CLASS') {
                    $this->code .= '(class) ';
                }
                if ($clauseData['type'] == 'T_EMPTY') {
                    $this->code .= 'empty';
                }
                if ($clauseData['type'] == 'T_ISSET') {
                    $this->code .= 'isset';
                }
                if ($clauseData['type'] == 'T_INSTANCEOF') {
                    $this->code .= 'instance of';
                }
                if ($clauseData['type'] == 'T_DOUBLE_COLON') {
                    $this->code .= '::';
                }
                if ($clauseData['type'] == 'T_DOUBLE_ARROW') {
                    $this->code .= ' => ';
                }
                if ($clauseData['type'] == 'T_EQUALS') {
                    $this->code .= ' = ';
                }
                if ($clauseData['type'] == 'T_IF') {
                    $this->code .= 'if';
                }
                if ($clauseData['type'] == 'T_RETURN') {
                    $this->code .= 'return ';
                }
                if ($clauseData['type'] == 'T_OPEN_PARENTHESIS') {
                    $this->code .= '(';
                }
                if ($clauseData['type'] == 'T_CLOSE_PARENTHESIS') {
                    $this->code .= ')';
                }
                if ($clauseData['type'] == 'T_OPEN_BRACE') {
                    $this->code .= '{';
                }
                if ($clauseData['type'] == 'T_CLOSE_BRACE') {
                    $this->code .= '}';
                }
                if ($clauseData['type'] == 'T_OPEN_BRACKET') {
                    $this->code .= '[';
                }
                if ($clauseData['type'] == 'T_CLOSE_BRACKET') {
                    $this->code .= ']';
                }
                if ($clauseData['type'] == 'T_IS_NOT') {
                    $this->code .= '!';
                }
                if ($clauseData['type'] == 'T_COMMA') {
                    $this->code .= ",";
                }
                if ($clauseData['type'] == 'T_END_LINE') {
                    $this->code .= ";";
                }
                if ($clauseData['type'] == 'T_OBJECT_OPERATOR') {
                    $this->code .= "->";
                }
                if ($clauseData['type'] == 'T_THIS') {
                    $this->code .= "\$this";
                }
                if ($clauseData['type'] == 'string') {
                    $this->code .= "'".$clauseData['value']."'";
                }
                if ($clauseData['type'] == 'number') {
                    $this->code .= $clauseData['value'];
                }
                if ($clauseData['type'] == 'method') {
                    $this->code .= $clauseData['name'];
                }
                if ($clauseData['type'] == 'model') {
                    $this->code .= $clauseData['name'];
                }
                if ($clauseData['type'] == 'variable') {
                    $this->code .= "$" . $clauseData['name'];
                }
                if ($clauseData['type'] == 'property') {
                    $this->code .= $clauseData['name'];
                }
                if ($clauseData['type'] == 'class') {
                    $pathSegments = explode('\\', $clauseData['name']);
                    if (count($pathSegments) > 1) {
                        $this->code .= $pathSegments[count($pathSegments) - 1];
                    } else {
                        $this->code .= $clauseData['name'];
                    }
                }
            }
        }
    }

    public function generateSitemap(Request $request) {
        $this->init($request);
        $pages = [];
        $pages = Page::on($this->databaseConnection)->where(['public' => 1])->get();
        foreach($pages as $page) {
            $pageData = json_decode($page->data, true);
            if (!empty($pageData)) {
                if (!empty($pageData['children'])) {
                    $templatePages = Page::on($this->databaseConnection)->whereIn('slug', $pageData['children'])->get();
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

    public function run(Request $request) {
        try {
            $inputData = $request->validate([
                'file' => 'required',
                'method' => 'required'
            ]);
            $this->init($request);
            $output = null;
            $file = File::on($this->databaseConnection)->where('slug', $inputData['file'])->first();
            $method = Method::on($this->databaseConnection)->where('slug', $inputData['method'])->first();
            if (!empty($method)) {
                $methodData = json_decode($method->data, true);
                $includes = [];
                if (!empty($file)) {
                    $fileData = json_decode($file->data, true);
                    $this->return = null;
                    if (!$this->debug) {
                        $this->return = $this->php($this->project->slug, $request, $fileData, $methodData, $this->variables, null, false);
                    }
                    if (!empty($this->return)) {
                        if ($this->return instanceof \Illuminate\Database\Eloquent\Collection){
                            $output = $this->return;
                        } else if ($this->return instanceof \Illuminate\Database\Eloquent\Model) {
                            foreach($this->return->getAttributes() as $key => $value) {
                                $output = $value;
                            }
                        } else {
                            $output = $this->return;
                        }
                    }
                }
            }
            return response()->json(['status' => 200, 'message' => !empty($this->editor['message']) ? $this->editor['message'] : 'No errors.', 'data' => json_encode($output)]);
        } catch (\Throwable $e) {
            //report($e);
            return response()->json(['status' => 400, 'message' => 'An error occurred.', 'data' => json_encode($e)]);
        }
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
}