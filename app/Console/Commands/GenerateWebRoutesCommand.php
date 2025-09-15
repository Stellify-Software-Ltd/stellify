<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


// Run using the command: php artisan dynamic-execution:generate-web-routes

class GenerateWebRoutesCommand extends Command
{
    protected $signature = 'dynamic-execution:generate-web-routes 
                           {--connection= : Database connection to use}
                           {--project= : Specific project ID to generate routes for}
                           {--backup : Create backup of existing web.php}';
                           
    protected $description = 'Generate web.php routes file from database routes table';
    
    public function handle(): int
    {
        try {
            $connection = $this->option('connection') ?? config('database.default');
            $projectId = $this->option('project');
            
            // Check if routes table exists
            if (!$this->routesTableExists($connection)) {
                $this->error("Routes table does not exist in database connection: {$connection}");
                return self::FAILURE;
            }
            
            // Backup existing web.php if requested
            if ($this->option('backup')) {
                $this->backupExistingRoutes();
            }
            
            // Get routes from database
            $routes = $this->getRoutesFromDatabase($connection, $projectId);
            
            if ($routes->isEmpty()) {
                $this->warn('No routes found in database');
                return self::SUCCESS;
            }
            
            // Generate the routes file content
            $content = $this->generateRoutesFileContent($routes);
            
            // Write to web.php
            $routesPath = base_path('routes/web.php');
            file_put_contents($routesPath, $content);
            
            $this->info("Generated {$routes->count()} routes in {$routesPath}");
            
            if ($projectId) {
                $this->info("Routes generated for project: {$projectId}");
            } else {
                $this->info("Routes generated for all projects");
            }
            
            return self::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("Failed to generate routes: " . $e->getMessage());
            return self::FAILURE;
        }
    }
    
    protected function routesTableExists(string $connection): bool
    {
        return Schema::connection($connection)->hasTable('routes');
    }
    
    protected function backupExistingRoutes(): void
    {
        $webRoutesPath = base_path('routes/web.php');
        
        if (file_exists($webRoutesPath)) {
            $backupPath = base_path('routes/web.php.backup.' . now()->format('Y-m-d-H-i-s'));
            copy($webRoutesPath, $backupPath);
            $this->info("Backup created: {$backupPath}");
        }
    }
    
    protected function getRoutesFromDatabase(string $connection, ?string $projectId)
    {
        $query = DB::connection($connection)
            ->table('routes')
            ->where('public', true)
            ->orderBy('path');
            
        if ($projectId) {
            $query->where('project_id', $projectId);
        }
        
        return $query->get();
    }
    
    protected function generateRoutesFileContent($routes): string
    {
        $content = "<?php\n\n";
        $content .= "/*\n";
        $content .= "|--------------------------------------------------------------------------\n";
        $content .= "| Web Routes\n"; 
        $content .= "|--------------------------------------------------------------------------\n";
        $content .= "|\n";
        $content .= "| Auto-generated from database routes table\n";
        $content .= "| Generated at: " . now() . "\n";
        $content .= "| Total routes: " . $routes->count() . "\n";
        $content .= "|\n";
        $content .= "*/\n\n";
        
        $content .= "use Illuminate\Support\Facades\Route;\n";
        $content .= "use App\\Http\\Controllers\\AppController;\n\n";
        
        // Group routes by project for better organization
        $routesByProject = $routes->groupBy('project_id');
        
        foreach ($routesByProject as $projectId => $projectRoutes) {
            $content .= "// Project: {$projectId}\n";
            
            foreach ($projectRoutes as $route) {
                $content .= $this->generateSingleRoute($route);
            }
            
            $content .= "\n";
        }
        
        return $content;
    }
    
    protected function generateSingleRoute(object $route): string
    {
        $method = strtolower($route->method);
        $path = $route->path;
        $routeData = json_decode($route->data, true);
        
        // Extract middleware from route data
        $middleware = $routeData['middleware'] ?? ['web'];
        $middlewareString = $this->formatMiddleware($middleware);
        
        // Generate route name
        $routeName = $route->name ?? $this->generateRouteName($route);
        
        $routeString = "Route::{$method}('{$path}', [AppController::class, 'index'])";
        
        // Add middleware if not just 'web'
        if ($middleware !== ['web']) {
            $routeString .= "\n    ->middleware({$middlewareString})";
        }
        
        // Add route name
        $routeString .= "\n    ->name('{$routeName}')";
        
        // Add defaults for route data
        $routeString .= "\n    ->defaults('route_uuid', '{$route->uuid}')";
        
        $routeString .= ";\n";
        
        return $routeString;
    }
    
    protected function formatMiddleware(array $middleware): string
    {
        if (count($middleware) === 1) {
            return "'{$middleware[0]}'";
        }
        
        $quoted = array_map(fn($m) => "'{$m}'", $middleware);
        return '[' . implode(', ', $quoted) . ']';
    }
    
    protected function generateRouteName(object $route): string
    {
        if (!empty($route->name)) {
            return $route->name;
        }
        
        // Generate name from path
        $name = str_replace(['/', '{', '}'], ['.', '', ''], $route->path);
        $name = trim($name, '.');
        $name = $name ?: 'home';
        
        return strtolower($route->method) . '.' . $name;
    }
}

// Enhanced version with more options
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GenerateWebRoutesEnhancedCommand extends Command
{
    protected $signature = 'dynamic-execution:generate-web-routes 
                           {--connection= : Database connection to use}
                           {--project= : Specific project ID to generate routes for}
                           {--backup : Create backup of existing web.php}
                           {--controller= : Controller class to use (default: AppController)}
                           {--method= : Controller method to use (default: index)}
                           {--output= : Output file path (default: routes/web.php)}
                           {--cache : Clear and cache routes after generation}';
                           
    protected $description = 'Generate web.php routes file from database routes table with advanced options';
    
    public function handle(): int
    {
        try {
            $connection = $this->option('connection') ?? config('database.default');
            $projectId = $this->option('project');
            $controller = $this->option('controller') ?? 'AppController';
            $method = $this->option('method') ?? 'index';
            $outputPath = $this->option('output') ?? 'routes/web.php';
            
            // Validate and prepare
            if (!$this->routesTableExists($connection)) {
                $this->error("Routes table does not exist in database connection: {$connection}");
                return self::FAILURE;
            }
            
            if ($this->option('backup')) {
                $this->backupExistingRoutes($outputPath);
            }
            
            // Get and validate routes
            $routes = $this->getRoutesFromDatabase($connection, $projectId);
            
            if ($routes->isEmpty()) {
                $this->warn('No routes found in database');
                if (!$this->confirm('Generate empty routes file?')) {
                    return self::SUCCESS;
                }
            }
            
            // Generate content
            $content = $this->generateRoutesFileContent($routes, $controller, $method);
            
            // Write file
            $fullPath = base_path($outputPath);
            $this->ensureDirectoryExists(dirname($fullPath));
            file_put_contents($fullPath, $content);
            
            $this->info("Generated {$routes->count()} routes in {$fullPath}");
            
            // Clear and cache routes if requested
            if ($this->option('cache')) {
                $this->call('route:clear');
                $this->call('route:cache');
                $this->info('Route cache updated');
            }
            
            return self::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("Failed to generate routes: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return self::FAILURE;
        }
    }
    
    protected function ensureDirectoryExists(string $directory): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }
    
    protected function routesTableExists(string $connection): bool
    {
        return Schema::connection($connection)->hasTable('routes');
    }
    
    protected function backupExistingRoutes(string $outputPath): void
    {
        $routesPath = base_path($outputPath);
        
        if (file_exists($routesPath)) {
            $backupPath = $routesPath . '.backup.' . now()->format('Y-m-d-H-i-s');
            copy($routesPath, $backupPath);
            $this->info("Backup created: {$backupPath}");
        }
    }
    
    protected function getRoutesFromDatabase(string $connection, ?string $projectId)
    {
        $query = DB::connection($connection)
            ->table('routes')
            ->where('public', true)
            ->orderBy('project_id')
            ->orderBy('path');
            
        if ($projectId) {
            $query->where('project_id', $projectId);
        }
        
        return $query->get();
    }
    
    protected function generateRoutesFileContent($routes, string $controller, string $method): string
    {
        $content = $this->generateFileHeader($routes);
        $content .= $this->generateImports($controller);
        $content .= $this->generateRouteDefinitions($routes, $controller, $method);
        
        return $content;
    }
    
    protected function generateFileHeader($routes): string
    {
        return "<?php\n\n" .
               "/*\n" .
               "|--------------------------------------------------------------------------\n" .
               "| Web Routes\n" . 
               "|--------------------------------------------------------------------------\n" .
               "|\n" .
               "| Auto-generated from database routes table\n" .
               "| Generated at: " . now() . "\n" .
               "| Total routes: " . $routes->count() . "\n" .
               "| Command: php artisan dynamic-execution:generate-web-routes\n" .
               "|\n" .
               "*/\n\n";
    }
    
    protected function generateImports(string $controller): string
    {
        return "use Illuminate\\Support\\Facades\\Route;\n" .
               "use App\\Http\\Controllers\\{$controller};\n\n";
    }
    
    protected function generateRouteDefinitions($routes, string $controller, string $method): string
    {
        if ($routes->isEmpty()) {
            return "// No routes found in database\n";
        }
        
        $content = '';
        $routesByProject = $routes->groupBy('project_id');
        
        foreach ($routesByProject as $projectId => $projectRoutes) {
            $content .= "// Project: {$projectId} ({$projectRoutes->count()} routes)\n";
            
            foreach ($projectRoutes as $route) {
                $content .= $this->generateSingleRoute($route, $controller, $method);
            }
            
            $content .= "\n";
        }
        
        return $content;
    }
    
    protected function generateSingleRoute(object $route, string $controller, string $method): string
    {
        $httpMethod = strtolower($route->method);
        $path = $route->path;
        $routeData = json_decode($route->data, true);
        
        $middleware = $routeData['middleware'] ?? ['web'];
        $middlewareString = $this->formatMiddleware($middleware);
        $routeName = $route->name ?? $this->generateRouteName($route);
        
        $routeString = "Route::{$httpMethod}('{$path}', [{$controller}::class, '{$method}'])";
        
        if ($middleware !== ['web']) {
            $routeString .= "\n    ->middleware({$middlewareString})";
        }
        
        $routeString .= "\n    ->name('{$routeName}')";
        $routeString .= "\n    ->defaults('route_uuid', '{$route->uuid}')";
        
        // Add comment with additional route info
        if (!empty($route->description)) {
            $routeString = "// {$route->description}\n" . $routeString;
        }
        
        return $routeString . ";\n\n";
    }
    
    protected function formatMiddleware(array $middleware): string
    {
        if (count($middleware) === 1) {
            return "'{$middleware[0]}'";
        }
        
        $quoted = array_map(fn($m) => "'{$m}'", $middleware);
        return '[' . implode(', ', $quoted) . ']';
    }
    
    protected function generateRouteName(object $route): string
    {
        if (!empty($route->name)) {
            return $route->name;
        }
        
        $name = str_replace(['/', '{', '}'], ['.', '', ''], $route->path);
        $name = trim($name, '.');
        $name = $name ?: 'home';
        
        return strtolower($route->method) . '.' . $name;
    }
}