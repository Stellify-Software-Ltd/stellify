<?php

namespace App\Services;

class PhpAssemblerService
{
    private array $tokenMap = [
        'T_FUNCTION' => 'function',
        'T_PUBLIC' => 'public ',
        'T_PROTECTED' => 'protected ',
        'T_PRIVATE' => 'private ',
        'T_STATIC' => 'static ',
        'T_STRING' => '(string) ',
        'T_BOOLEAN' => '(bool) ',
        'T_ELSE' => ' else ',
        'T_ELSEIF' => 'elseif ',
        'T_FOREACH' => 'foreach ',
        'T_FOR' => 'for ',
        'T_WHILE' => 'while ',
        'T_DO' => 'do ',
        'T_NEW' => 'new ',
        'T_AND_EQUAL' => '&=',
        'T_OR_EQUAL' => '|=',
        'T_XOR_EQUAL' => '^=',
        'T_PLUS_EQUAL' => '+=',
        'T_MINUS_EQUAL' => '-=',
        'T_MUL_EQUAL' => '*=',
        'T_DIV_EQUAL' => '/=',
        'T_MOD_EQUAL' => '%=',
        'T_COALESCE' => '??',
        'T_SPACESHIP' => '<=>',
        'T_IS_EQUAL' => '==',
        'T_IS_NOT' => '!',
        'T_IS_NOT_EQUAL' => '!=',
        'T_IS_IDENTICAL' => '===',
        'T_IS_NOT_IDENTICAL' => '!==',
        'T_GREATER_EQUAL' => '>=',
        'T_LESS_EQUAL' => '<=',
        'T_CLASS' => '(class) ',
        'T_EVENT_METHOD' => 'event',
        'T_EMPTY' => 'empty',
        'T_ISSET' => 'isset',
        'T_INSTANCEOF' => 'instance of',
        'T_DOUBLE_COLON' => '::',
        'T_DOUBLE_ARROW' => ' => ',
        'T_EQUAL' => ' = ',
        'T_IF' => 'if',
        'T_RETURN' => 'return ',
        'T_OPEN_PARENTHESIS' => '(',
        'T_CLOSE_PARENTHESIS' => ')',
        'T_OPEN_BRACE' => '{',
        'T_CLOSE_BRACE' => '}',
        'T_OPEN_BRACKET' => '[',
        'T_CLOSE_BRACKET' => ']',
        'T_COMMA' => ',',
        'T_CONCAT' => '.',
        'T_BACKSLASH' => '\\',
        'T_END_LINE' => ';',
        'T_OBJECT_OPERATOR' => '->',
        'T_THIS' => '$this',
    ];

    private array $disallowedFunctions = [
        // Existing system functions
        'exec', 'shell_exec', 'system', 'passthru', 'local',
        'eval', 'popen', 'proc_open', 'unlink',
        'file_get_contents', 'file_put_contents',
        'include', 'include_once', 'require', 'require_once',
        'fopen', 'fwrite', 'fclose', 'fread', 'echo', '()',
        
        // SQL Injection Prevention
        'DB::raw',
        'DB::unprepared',
        'whereRaw',
        'havingRaw',
        'orderByRaw',
        'selectRaw',
        'DB::select',
        'DB::insert',
        'DB::statement',
        'DB::affectingStatement',
        'DB::update',
        'DB::delete',
        'PDO::exec',
        'PDO::query',

        // Dangerous Laravel/Database methods
        'env',
        'dd',
        'dump',
        'var_dump',
        'print_r',
        'getDatabaseName',
        'raw',
        'whereRaw',
        'havingRaw',
        'orderByRaw',
        'selectRaw',
        'createDatabase',
        'dropDatabase',
        'statement',
        'unprepared',
        'setDatabaseName',
        'dropIfExists',
        'truncate',
        
        // File system operations
        'Storage::delete',
        'Storage::deleteDirectory',
        'File::delete',
        'File::deleteDirectory',
        
        // Dangerous config/cache operations
        'config:cache',
        'config:clear',
        'cache:clear',
        'view:clear',
        
        // Dangerous Artisan commands
        'Artisan::call',
        'migrate:fresh',
        'migrate:reset',
        'db:seed'
    ];

    private array $disallowedPhrases = [
        'phpinfo', '<?php', '<?=', '?>', 
        'base64_decode', 'base64_encode',
        'create_function', 'assert'
    ];

    private array $sqlPatterns = [
        '/union\s+select/i',
        '/into\s+outfile/i',
        '/load_file/i',
        '/group\s+by/i',
        '/having\s+[0-9]/i',
        '/sleep\s*\([^)]*\)/i',
        '/benchmark\s*\([^)]*\)/i',
        '/(?:delete|drop|truncate|alter|create|replace|insert|update)\s+/i'
    ];

    private string $code = '';

    private array $namespaceMap = [
        'model' => 'App\\Models\\',
    ];

    public function startClassDeclaration(array $class, ?array $extends, ?array $implements): void
    {
        $this->code .= "class " . $class['name'];
        if (!empty($extends)) {
            $this->code .= " extends " . $extends['name'];
        }
        if (!empty($implements)) {
            $this->code .= " implements " . $implements['name'];
        }
        $this->code .= " {\n";
    }

    public function assembleStatement(array $clause): void
    {
        if (!isset($clause['type'])) {
            throw new \InvalidArgumentException('Clause type is required');
        }

        if (isset($this->tokenMap[$clause['type']])) {
            $this->code .= $this->tokenMap[$clause['type']];
            if ($clause['type'] === 'T_END_LINE') {
                $this->code .= "\n";
            }
        }

        // Handle special cases
        if ($clause['type'] === 'method') {
            $this->assembleMethod($clause);
        }

        if ($clause['type'] === 'variable' && isset($clause['name'])) {
            $this->code .= "$" . $clause['name'];
        }

        if ($clause['type'] === 'string' && isset($clause['name'])) {
            $this->code .= "'" . addslashes($clause['name']) . "'";
        }

        if ($clause['type'] === 'number' && isset($clause['name'])) {
            if (is_numeric($clause['name']) && $this->validateClause($clause)) {
                $this->code .= $clause['name'];
            }
        }

        if ($clause['type'] === 'model') {
            //check property exists on object
            if ($this->validateClause($clause)) {
                $this->code .= $clause['name'];
            }
        }

        if ($clause['type'] === 'property') {
            //check property exists on object
            if ($this->validateClause($clause)) {
                $this->code .= $clause['name'];
            }
        }

        if ($clause['type'] == 'class') {
            $pathSegments = explode('\\', $clause['name']);
            if (count($pathSegments) > 1) {
                $this->code .= $pathSegments[count($pathSegments) - 1];
            } else {
                //check class exists
                if ($this->validateClause($clause)) {
                    $this->code .= $clause['name'];
                }
            }
        }
    }

    private function assembleMethod(array $clause): void
    {

        if ($clause['name'] === 'view') {
            $this->code .= '$this->element';
        } else {
            $this->code .= $clause['name'];
        }

        // $this->code .= $clause['name'] . '(';
        
        // if (!empty($clause['parameters'])) {
        //     $params = array_map(function($param) {
        //         if ($param['type'] === 'variable') {
        //             return '$' . $param['name'];
        //         }
        //         if ($param['type'] === 'string') {
        //             return "'" . addslashes($param['name']) . "'";
        //         }
        //         if (($param['type'] === 'number' || $param['type'] === 'int') && is_numeric($param['name'])) {
        //             return $param['name'];
        //         }
        //     }, $clause['parameters']);
            
        //     $this->code .= implode(', ', $params);
        // }
        
        // $this->code .= ')';
    }

    public function assembleFunction(array $clause): string
    {
        $this->code .= "\t";
        if (empty($clause['scope'])) {
            $this->code .= 'public';
        } else {
            $this->code .= $clause['scope'];
        }

        $this->code .= ' function ';

        $this->code .= $clause['name'] . '(';
        
        if (!empty($clause['parameters'])) {
            $params = array_map(function($param) {
                if ($param['type'] === 'variable') {
                    return '$' . $param['name'];
                }
                if ($param['type'] === 'string') {
                    return "'" . addslashes($param['name']) . "'";
                }
                if (($param['type'] === 'number' || $param['type'] === 'int') && is_numeric($param['name'])) {
                    return $param['name'];
                }
                if ($param['type'] === 'mixed') {
                    return $param['name'];
                }
                if ($param['type'] === 'class') {
                    return $param['class'] . ' $' . $param['name'];
                }
            }, $clause['parameters']);
            
            $this->code .= implode(', ', $params);
        }
        
        $this->code .= ")";
        if (!empty($clause['returnType'])) {
            $this->code .= ': ' . $clause['returnType'] . "\n";
        } else {
            $this->code .= ": void\n";
        }
        $this->code .= "\t{\n\t\t";
        return $this->code;
    }

    public function addCode($code): string
    {
        return $this->code .= $code;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function resetCode(): void
    {
        $this->code = '';
    }

    public function assembleUseStatement(?string $namespace, string $class): void
    {
        if (isset($this->namespaceMap[$namespace])) {
            $namespace = $this->namespaceMap[$namespace];
        }
        $this->code .= 'use ' . $namespace . $class . ';' . "\n";
    }

    public function assemblePropertyDeclaration(array $property): string
    {
        if (!empty($property['name'])) {
            $scope = in_array($property['scope'] ?? 'private', ['private', 'protected', 'public']) 
                ? $property['scope'] 
                : 'private';

            $name = $property['name'];
            $value = $this->formatVariableValue($property['value'] ?? null);
            $this->code .= "\t{$scope} \${$name} = {$value};\n";
        }
    }

    private function formatVariableValue($value): string
    {
        if (is_null($value)) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_numeric($value)) {
            return (string) $value;
        }
        if (is_string($value)) {
            return "'" . addslashes($value) . "'";
        }
        if (is_array($value)) {
            return '[]';
        }
        
        return 'null';
    }

    public function validateFile(string $code): bool
    {
        $valid = true;
        if (!empty($code)) {
            $valid = $this->validateSqlInput($code);
        }
        foreach ($this->disallowedPhrases as $phrase) {
            if (stripos($code, $phrase) !== false) {
                $valid = false;
                dd('Disallowed phrase found: ' . $phrase);
            }
        }
        return $valid;
    }

    public function validateClause(array $clause): bool
    {
        if (!empty($clause['name'])) {
            if (!empty($clause['name']) && in_array(strtolower($clause['name']), $this->disallowedFunctions)) {
                return false;
            }
        }

        foreach ($this->disallowedPhrases as $phrase) {
            if (!empty($clause['name']) && stripos($clause['name'], $phrase) !== false) {
                return false;
            }
        }
        return true;
    }

    protected function validateSqlInput(string $input): bool 
    {
        foreach ($this->sqlPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return false;
            }
        }
        return true;
    }
}
