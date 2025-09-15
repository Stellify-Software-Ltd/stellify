<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

use App\Services\PhpAssemblerService;
use App\DTOs\CodeExecutionContext;

use App\Traits\DatabaseConnectionTester;

class CodeExecutionService
{
    use DatabaseConnectionTester;

    private CodeExecutionContext $context;

    public function __construct(private PhpAssemblerService $phpAssembler) 
    {
        $this->phpAssembler = $phpAssembler;
    }

    /**
     * Execute controller code from JSON data structures
     */
    public function execute(CodeExecutionContext $context): mixed
    {
        // Store context as instance property
        $this->context = $context;

        if (!empty($this->context->controllerData['models'])) {
            foreach ($this->context->controllerData['models'] as $modelUuid) {
                if (!empty($this->context->models[$modelUuid])) {
                    $this->executeModelCode($this->context->models[$modelUuid]);
                }
            }
        }

        $generatedCode = $this->assembleControllerCode();

        if ($context->returnCode) {
            return $generatedCode;
        }

        // Execute the generated code
        return $this->executeControllerCode($generatedCode);
    }

    private function executeModelCode(array $model): void
    {
        $originalConfigs = $this->backupOriginalConfigs();

        try {
            $this->setupExternalDatabase();

            // Assemble model code
            $modelCode = $this->assembleModelCode($model);

            // Validate model code
            if (!$this->phpAssembler->validateFile($modelCode)) {
                throw new \Exception('Generated model code failed security validation');
            }
            
            // Execute model code
            eval($modelCode);
            
        } catch (\Throwable $e) {
            //dd($e);
            Log::error('Model execution failed', [
                'error' => $e->getMessage(),
                'model_name' => $model['name'] ?? 'unknown'
            ]);
            
            throw $e;
        } finally {
            $this->restoreOriginalConfigs($originalConfigs);
        }
    }

    private function executeControllerCode(string $code): mixed
    {
        $originalConfigs = $this->backupOriginalConfigs();
        
        try {
            $this->setupExternalDatabase();
            
            // Validate the generated code before execution
            if (!$this->phpAssembler->validateFile($code)) {
                throw new \Exception('Generated code failed security validation');
            }
 
            // Execute the generated code
            eval($code);
            
            // Get the controller class name and instantiate it
            $sanitizedProjectId = $this->sanitizeForNamespace($this->context->projectId);
            $controllerName = $this->context->controllerData['name'];
            $className = "\\App\\Sandbox\\{$sanitizedProjectId}\\Controllers\\{$controllerName}";
            
            if (!class_exists($className)) {
                throw new \Exception("Controller class {$className} not found after execution");
            }
            
            $controller = new $className();
            $methodName = $this->context->methodData['name'];
            
            if (!method_exists($controller, $methodName)) {
                throw new \Exception("Method {$methodName} not found in controller");
            }
            
            // Execute the method with parameters
            $parameters = $this->buildMethodParameters();
            return $controller->$methodName(...$parameters);
            
        } catch (\Throwable $e) {
            Log::error('Code execution failed', [
                'error' => $e->getMessage(),
                'project_id' => $this->context->projectId
            ]);
            
            throw $e;
        } finally {
            $this->restoreOriginalConfigs($originalConfigs);
        }
    }

    private function buildMethodParameters(): array
    {
        $parameters = [];
        
        if (!empty($this->context->methodData['parameters'])) {
            foreach ($this->context->methodData['parameters'] as $param) {
                switch ($param['type']) {
                    case 'Request':
                        $parameters[] = $this->context->request;
                        break;
                    case 'variable':
                        $parameters[] = $this->context->variables[$param['name']] ?? null;
                        break;
                    default:
                        $parameters[] = $param['value'] ?? null;
                }
            }
        }
        
        return $parameters;
    }

    private function buildIncludes(array $includes): void
    {
        foreach ($includes as $include) {
            // Each include should have namespace and name
            if (!empty($this->context->includes[$include]['namespace']) && !empty($this->context->includes[$include]['name'])) {
                $this->phpAssembler->assembleUseStatement(
                    $this->context->includes[$include]['namespace'], 
                    $this->context->includes[$include]['name']
                );
            }
        }
    }

    private function buildModelIncludes(array $models): void
    {        
        $sanitizedProjectId = $this->sanitizeForNamespace($this->context->projectId);
        
        foreach ($models as $model) {
            // Models use a standard namespace pattern
            if (!empty($model['name'])) {
                $this->phpAssembler->assembleUseStatement(
                    "App\\Sandbox\\{$sanitizedProjectId}\\Models\\", 
                    $model['name']
                );
            }
        }
    }

    private function buildPropertyDeclarations(array $variables): void
    {
        foreach ($variables as $variable) {
            $this->phpAssembler->assemblePropertyDeclaration($variable);
        }
    }

    private function sanitizeForNamespace(string $projectId): string
    {
        return str_replace('-', '', $projectId);
    }

    private function buildClass($controller): void
    {
        $extends = !empty($controller['extends']) && !empty($this->context->includes[$controller['extends']]) 
            ? $this->context->includes[$controller['extends']] 
            : null;

        $implements = !empty($controller['implements']) && !empty($this->context->includes[$controller['implements']]) 
            ? $this->context->includes[$controller['implements']] 
            : null;

        $this->phpAssembler->startClassDeclaration(
            $controller, 
            $extends, 
            $implements
        );

        if (!empty($controller['variables'])) {
            // Add properties for each include as an example
            foreach ($controller['variables'] as $variable) {
                $this->phpAssembler->assemblePropertyDeclaration($variable);
            }
        }

        if (!empty($controller['data'])) {
            foreach ($controller['data'] as $method) {
                $this->buildMethod($this->context->methods[$method]);
            }
        }
        

        // Close class definition
        $this->phpAssembler->addCode("}\n");
        //dd($this->phpAssembler->getCode());
    }

    private function buildMethod($method): void
    {
        $this->phpAssembler->assembleFunction($method);

        if (!empty($method['data'])) {
            foreach ($method['data'] as $statement) {
                if (
                    !empty($this->context->statements[$statement]) &&
                    !empty($this->context->statements[$statement]['data'])
                ) {
                    foreach ($this->context->statements[$statement]['data'] as $clause) {
                        if (
                            !empty($this->context->clauses[$clause]) &&
                            !empty($this->context->clauses[$clause]['type'])
                        ) {
                            $this->phpAssembler->assembleStatement($this->context->clauses[$clause]);
                        }
                    }
                }
            }
        }

        // Close method definition
        $this->phpAssembler->addCode("\t}\n\n");
    }

    private function assembleControllerCode(): string
    {
        // Reset assembler for clean start
        $this->phpAssembler->resetCode();

        // Define namespace based on project ID
        $sanitizedProjectId = $this->sanitizeForNamespace($this->context->projectId);
        $namespace = "App\\Sandbox\\{$sanitizedProjectId}\\Controllers";

        //$this->phpAssembler->addCode("<?php\n");
        $this->phpAssembler->addCode("namespace {$namespace};\n\n");

        // Build controller includes using your existing assembler
        $this->buildIncludes($this->context->controllerData['includes']);
        
        // Build model includes 
        $this->buildModelIncludes($this->context->models);
        
        // Build class structure
        $this->buildClass($this->context->controllerData);

        return $this->phpAssembler->getCode();
    }

    private function assembleModelCode(array $model): string
    {
        // Reset assembler for clean start
        $this->phpAssembler->resetCode();

        // Define namespace based on project ID
        $sanitizedProjectId = $this->sanitizeForNamespace($this->context->projectId);
        $namespace = "App\\Sandbox\\{$sanitizedProjectId}\\Models";

        //$this->phpAssembler->addCode("<?php\n");
        $this->phpAssembler->addCode("namespace {$namespace};\n\n");

        // Build model includes using your existing assembler
        $this->buildIncludes($model['includes']);

        // Build class structure
        $this->buildClass($model);

        return $this->phpAssembler->getCode();
        //dd($this->phpAssembler->getCode());
    }

    private function backupOriginalConfigs(): array
    {
        $configsToBackup = [
            'database.connections',
            'cache.stores', 
            'session.driver',
            'mail.mailers',
            'services',
            'app.timezone',
            'app.locale',
            'app.debug',
        ];
        
        $backup = [];
        foreach ($configsToBackup as $configKey) {
            $backup[$configKey] = config($configKey);
        }
        
        return $backup;
    }

    private function setupExternalDatabase(): void
    {
        // Set up the external database configuration
        // Move your existing setupExternalDatabase logic here
        Config::set('queue.connections.user_database', [
            'driver' => 'database',
            'table' => 'jobs', 
            'queue' => 'default',
            'retry_after' => 90,
            'after_commit' => false,
            'connection' => $this->getExternalConnection(),
        ]);
        
        Config::set('queue.default', 'user_database');
        
        // Apply user settings
        $this->replaceConfigWithWhitelistedSettings();
    }

    private function restoreOriginalConfigs(array $originalConfigs): void
    {
        foreach ($originalConfigs as $configKey => $originalValue) {
            Config::set($configKey, $originalValue);
        }
    }

    private function getExternalConnection(): ?string
    {
        // Logic to get the external database connection name
        // Move from your controller's testConnection method
        return $this->testConnection();
    }

    /**
     * Replace entire config with only whitelisted settings from database
     */
    private function replaceConfigWithWhitelistedSettings(): void
    {        
        // Clear all existing configuration
        $this->clearAllConfig();

        // Set only the allowed configurations
        $this->setWhitelistedConfig();
    }

    /**
     * Clear all existing configuration
     */
    private function clearAllConfig(): void
    {
        // List of config sections to completely clear
        $configSections = [
            'app',
            'database', 
            'cache',
            'session',
            'mail',
            'services',
            'queue',
            'broadcasting',
            'filesystems',
            'logging',
            'auth',
            'view',
            'cors',
            'sanctum',
            'telescope',
            'horizon',
            'scout',
            'cashier'
        ];
        
        foreach ($configSections as $section) {
            Config::set($section, []);
        }
    }

    /**
     * Set only whitelisted configuration values
     */
    private function setWhitelistedConfig(): void
    {
        // Set essential app configs with safe defaults
        $this->setEssentialConfigs($this->context->settings);

        // Set user-defined configurations
        foreach ($this->context->settings as $key => $value) {
            // Parse and validate the setting
            $parsedValue = $this->parseSettingValue($value);
            
            if ($parsedValue !== null) {
                foreach ($this->context->settings[$key] as $k => $v) {
                    // Additional validation can be added here if needed
                    Config::set($key . '.' . $k, $v);
                }
            }
        }
    }

    /**
     * Set essential configurations needed for Laravel to function
     */
    private function setEssentialConfigs(array $allowedSettings): void
    {
        // Minimal app configuration required for Laravel
        $essentialConfigs = [
            'auth.defaults.guard' => 'web',
            'auth.defaults.passwords' => 'users',
            'auth.guards.web' => [
                'driver' => 'session',
                'provider' => 'users',
            ],
            'auth.guards.api' => [
                'driver' => 'token',
                'provider' => 'users',
                'hash' => false,
            ],
            'auth.providers.users' => [
                'driver' => 'eloquent',
                'model' => "App\\Sandbox\\{$this->sanitizeForNamespace($this->context->projectId)}\\Models\\User",
            ],
            'auth.passwords.users' => [
                'provider' => 'users',
                'table' => 'password_reset_tokens',
                'expire' => 60,
                'throttle' => 60,
            ],
            'app.name' => $allowedSettings['app.name'] ?? 'Sandbox App',
            'app.env' => 'sandbox',
            'app.debug' => false,
            'app.url' => $allowedSettings['app.url'] ?? 'http://localhost',
            'app.timezone' => $allowedSettings['app.timezone'] ?? 'UTC',
            'app.locale' => $allowedSettings['app.locale'] ?? 'en',
            'app.fallback_locale' => 'en',
            'app.faker_locale' => 'en_US',
            'app.key' => 'base64:' . base64_encode('sandbox_key_32_characters_long'),
            'app.cipher' => 'AES-256-CBC',
            
            // Minimal database configuration
            'database.default' => $allowedSettings['database.default'] ?? 'mysql2',
            'database.connections.mysql2' => [
                'driver' => 'mysql',
                'host' => $allowedSettings['database.connections.mysql2.host'] ?? '127.0.0.1',
                'port' => $allowedSettings['database.connections.mysql2.port'] ?? '3306',
                'database' => $allowedSettings['database.connections.mysql2.database'] ?? 'sandbox',
                'username' => $allowedSettings['database.connections.mysql2.username'] ?? 'sandbox_user',
                'password' => $allowedSettings['database.connections.mysql2.password'] ?? null,
                'unix_socket' => $allowedSettings['database.connections.mysql2.unix_socket'] ?? '',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'prefix_indexes' => true,
                'strict' => false,
                'engine' => null,
            ],
            'database.connections.pgsql2' => [
                'driver' => 'pgsql',
                'host' => $allowedSettings['database.connections.pgsql2.host'] ?? '127.0.0.1',
                'port' => $allowedSettings['database.connections.pgsql2.port'] ?? '5432',
                'database' => $allowedSettings['database.connections.pgsql2.database'] ?? 'sandbox',
                'username' => $allowedSettings['database.connections.pgsql2.username'] ?? 'sandbox_user',
                'password' => $allowedSettings['database.connections.pgsql2.password'] ?? null,
                'charset' => 'utf8',
                'prefix' => '',
                'prefix_indexes' => true,
                'search_path' => $allowedSettings['database.connections.pgsql2.search_path'] ?? 'public',
                'sslmode' => $allowedSettings['database.connections.pgsql2.sslmode'] ?? 'prefer',
            ],
            'database.migrations' => 'migrations',
            
            // Safe cache configuration
            'cache.default' => $allowedSettings['cache.default'] ?? 'array',
            'cache.stores.array' => [
                'driver' => 'array',
                'serialize' => false,
            ],
            'cache.stores.file' => [
                'driver' => 'file',
                'path' => storage_path('framework/cache/sandbox'),
            ],
            'cache.prefix' => 'sandbox_cache',
            
            // Safe session configuration
            'session.driver' => $allowedSettings['session.driver'] ?? 'array',
            'session.lifetime' => $allowedSettings['session.lifetime'] ?? 120,
            'session.expire_on_close' => false,
            'session.encrypt' => false,
            'session.files' => storage_path('framework/sessions/sandbox'),
            'session.connection' => null,
            'session.table' => 'sessions',
            'session.store' => null,
            'session.lottery' => [2, 100],
            'session.cookie' => 'sandbox_session',
            'session.path' => '/',
            'session.domain' => null,
            'session.secure' => false,
            'session.http_only' => true,
            'session.same_site' => 'lax',
            
            // Safe mail configuration - always log
            'mail.default' => 'log',
            'mail.mailers.log' => [
                'transport' => 'log',
                'channel' => null,
            ],
            'mail.mailers.array' => [
                'transport' => 'array',
            ],
            'mail.from.address' => 'sandbox@example.com',
            'mail.from.name' => 'Sandbox App',
            
            // Safe queue configuration
            'queue.default' => 'database',
            'queue.connections.database' => [
                'driver' => 'database',
                'table' => 'jobs',
                'queue' => 'default',
                'retry_after' => 90,
                'after_commit' => false,
            ],
            
            // Safe logging configuration
            'logging.default' => 'single',
            'logging.deprecations' => 'single',
            'logging.channels.single' => [
                'driver' => 'single',
                'path' => storage_path('logs/sandbox.log'),
                'level' => 'debug',
            ],
            'logging.channels.daily' => [
                'driver' => 'daily',
                'path' => storage_path('logs/sandbox.log'),
                'level' => 'debug',
                'days' => 14,
            ],
            
            // Safe broadcasting
            'broadcasting.default' => 'log',
            'broadcasting.connections.log' => [
                'driver' => 'log',
            ],
            
            // Safe filesystem configuration
            'filesystems.default' => 'local',
            'filesystems.disks.local' => [
                'driver' => 'local',
                'root' => storage_path('app/sandbox'),
                'throw' => false,
            ],
            'filesystems.disks.public' => [
                'driver' => 'local',
                'root' => storage_path('app/public/sandbox'),
                'url' => '/storage/sandbox',
                'visibility' => 'public',
                'throw' => false,
            ],
            
            // Minimal view configuration
            'view.paths' => [resource_path('views')],
            'view.compiled' => env('VIEW_COMPILED_PATH', realpath(storage_path('framework/views'))),
        ];

        // Set all essential configs
        foreach ($essentialConfigs as $key => $value) {
            Config::set($key, $value);
        }
    }

    /**
     * Parse setting value from database (JSON decode if needed)
     */
    private function parseSettingValue($value)
    {
        if (is_null($value)) {
            return null;
        }
        
        // If it's already decoded or a simple value, return as-is
        if (!is_string($value)) {
            return $value;
        }
        
        // Try to decode JSON, if it fails, return as string
        $decoded = json_decode($value, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }
        
        return $value;
    }
}