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
        'T_EMPTY' => 'empty',
        'T_ISSET' => 'isset',
        'T_INSTANCEOF' => 'instance of',
        'T_DOUBLE_COLON' => '::',
        'T_DOUBLE_ARROW' => ' => ',
        'T_EQUALS' => ' = ',
        'T_IF' => 'if',
        'T_RETURN' => 'return ',
        'T_OPEN_PARENTHESIS' => '(',
        'T_CLOSE_PARENTHESIS' => ')',
        'T_OPEN_BRACE' => '{',
        'T_CLOSE_BRACE' => '}',
        'T_OPEN_BRACKET' => '[',
        'T_CLOSE_BRACKET' => ']',
        'T_COMMA' => ',',
        'T_END_LINE' => ';',
        'T_OBJECT_OPERATOR' => '->',
        'T_THIS' => '$this',
    ];

    private array $disallowedFunctions = [
        // Existing system functions
        'exec', 'shell_exec', 'system', 'passthru', 
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

    public function assembleStatement(array $clause): string
    {
        if (!isset($clause['type'])) {
            throw new \InvalidArgumentException('Clause type is required');
        }

        if (isset($this->tokenMap[$clause['type']])) {
            $this->code .= $this->tokenMap[$clause['type']];
            if ($clause['type'] === 'T_END_LINE') {
                $this->code .= "\n\t\t\t\t";
            }
        }

        // Handle special cases
        if ($clause['type'] === 'method') {
            $this->assembleMethod($clause);
        }

        if ($clause['type'] === 'variable' && isset($clause['name'])) {
            $this->code .= "$" . $clause['name'];
        }

        if ($clause['type'] === 'string' && isset($clause['value'])) {
            $this->code .= "'" . addslashes($clause['value']) . "'";
        }

        if ($clause['type'] === 'number' && isset($clause['value'])) {
            if (is_numeric($clause['value']) && $this->validateClause($clause)) {
                $this->code .= $clause['value'];
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

        return $this->code;
    }

    private function assembleMethod(array $clause): void
    {
        $this->code .= $clause['name'] . '(';
        
        if (!empty($clause['parameters'])) {
            $params = array_map(function($param) {
                if ($param['type'] === 'variable') {
                    return '$' . $param['name'];
                }
                if ($param['type'] === 'string') {
                    return "'" . addslashes($param['value']) . "'";
                }
                if (($param['type'] === 'number' || $param['type'] === 'int') && is_numeric($param['value'])) {
                    return $param['value'];
                }
            }, $clause['parameters']);
            
            $this->code .= implode(', ', $params);
        }
        
        $this->code .= ')';
    }

    public function assembleFunction(array $clause): string
    {
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
                    return "'" . addslashes($param['value']) . "'";
                }
                if (($param['type'] === 'number' || $param['type'] === 'int') && is_numeric($param['value'])) {
                    return $param['value'];
                }
                if ($param['type'] === 'mixed') {
                    return $param['value'];
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
        $this->code .= "\t\t{\n\t\t\t\t";
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

    public function assembleUseStatement(string $namespace = null, string $class): string
    {
        if (isset($this->namespaceMap[$namespace])) {
            $namespace = $this->namespaceMap[$namespace];
        }
        return 'use ' . $namespace . $class . ';' . "\n";
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

        if (!empty($clause['value'])) {
            if (!empty($clause['value']) && in_array(strtolower($clause['value']), $this->disallowedFunctions)) {
                return false;
            }
        }

        foreach ($this->disallowedPhrases as $phrase) {
            if (!empty($clause['value']) && stripos($clause['value'], $phrase) !== false) {
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
