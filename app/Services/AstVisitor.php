<?php

namespace App\Services;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class AstVisitor extends NodeVisitorAbstract 
{
    private array $functions = [];
    private array $statements = [];
    private array $tokens = [];
    private $currentFunction = null;

    public function enterNode(Node $node) {
        if ($node instanceof Node\Stmt\Function_) {
            $this->processFunction($node);
        } elseif ($node instanceof Node\Stmt\Expression && $node->expr instanceof Node\Expr\Assign) {
            $this->processAssignment($node->expr);
        } elseif ($node instanceof Node\Stmt\If_) {
            $this->processIfStatement($node);
        } elseif ($node instanceof Node\Stmt\For_ || $node instanceof Node\Stmt\Foreach_ || $node instanceof Node\Stmt\While_) {
            $this->processLoop($node);
        }
    }

    private function processFunction(Node\Stmt\Function_ $node) {
        $uuid = $this->generateUuid();
        $params = array_map(function($param) {
            $paramUuid = $this->generateUuid();
            return [
                'slug' => $paramUuid,
                'name' => $param->var->name,
                'type' => 'variable',
            ];
        }, $node->params);;

        $this->functions[] = [
            'slug' => $uuid,
            'name' => $node->name->name,
            'params' => $params,
            'data' => [],
        ];

        // Store reference to current function
        $this->currentFunction = &$this->functions[count($this->functions) - 1];
    }

    private function processAssignment(Node\Expr\Assign $node) {
        $stmtUuid = $this->generateUuid();
        $varUuid = $this->generateUuid();
        $opUuid = $this->generateUuid();
        $valUuid = $this->generateUuid();

        $this->statements[] = [
            'slug' => $stmtUuid,
            'type' => 'assignment',
            'data' => [$varUuid, $opUuid, $valUuid],
        ];

        if ($node->var instanceof Node\Expr\Variable) {
            $this->clauses[$varUuid] = [
                'slug' => $varUuid,
                'type' => 'variable',
                'value' => '$' . $node->var->name,
            ];
        }

        $this->clauses[$opUuid] = [
            'slug' => $opUuid,
            'type' => 'operator',
            'value' => '=',
        ];

        $this->clauses[$valUuid] = $this->processValue($node->expr, $valUuid);

        if ($this->currentFunction) {
            $this->currentFunction['data'][] = $stmtUuid;
        }
    }

    private function processIfStatement(Node\Stmt\If_ $node) {
        $stmtUuid = $this->generateUuid();
        $condUuid = $this->generateUuid();

        $this->statements[] = [
            'slug' => $stmtUuid,
            'type' => 'if',
            'condition' => $condUuid,
            'statements' => [],
        ];

        $this->clauses[$condUuid] = $this->processValue($node->cond, $condUuid);

        if ($this->currentFunction) {
            $this->currentFunction['data'][] = $stmtUuid;
        }
    }

    private function processLoop(Node $node) {
        $stmtUuid = $this->generateUuid();
        $type = ($node instanceof Node\Stmt\For_) ? 'for' : (($node instanceof Node\Stmt\Foreach_) ? 'foreach' : 'while');

        $this->statements[] = [
            'slug' => $stmtUuid,
            'type' => $type,
            'data' => [],
        ];

        if ($this->currentFunction) {
            $this->currentFunction['data'][] = $stmtUuid;
        }
    }

    private function processValue(Node $node, string $uuid): array {
        if ($node instanceof Node\Scalar\String_) {
            return ['slug' => $uuid, 'type' => 'string', 'value' => '"' . $node->value . '"'];
        }
        if ($node instanceof Node\Scalar\LNumber) {
            return ['slug' => $uuid, 'type' => 'integer', 'value' => (string) $node->value];
        }
        if ($node instanceof Node\Scalar\DNumber) {
            return ['slug' => $uuid, 'type' => 'float', 'value' => (string) $node->value];
        }
        if ($node instanceof Node\Expr\Variable) {
            return ['slug' => $uuid, 'type' => 'variable', 'value' => '$' . $node->name];
        }
        if ($node instanceof Node\Expr\MethodCall) {
            $targetUuid = $this->generateUuid();
            $methodUuid = $this->generateUuid();
            $argsUuids = [];
            
            // Process the target object
            $this->clauses[$targetUuid] = $this->processValue($node->var, $targetUuid);
            
            // Process arguments
            foreach ($node->args as $arg) {
                $argUuid = $this->generateUuid();
                $argsUuids[] = $argUuid;
                $this->clauses[$argUuid] = $this->processValue($arg->value, $argUuid);
            }
    
            // Store method call
            $this->clauses[$methodUuid] = [
                'slug' => $methodUuid,
                'type' => 'method',
                'name' => $node->name->toString()
            ];
    
            return [
                'slug' => $uuid,
                'type' => 'method_call',
                'data' => array_merge([$targetUuid, $methodUuid], $argsUuids)
            ];
        }
        if ($node instanceof Node\Expr\StaticCall) {
            $classUuid = $this->generateUuid();
            $methodUuid = $this->generateUuid();
            $argsUuids = [];
            
            // Process arguments
            foreach ($node->args as $arg) {
                $argUuid = $this->generateUuid();
                $argsUuids[] = $argUuid;
                $this->clauses[$argUuid] = $this->processValue($arg->value, $argUuid);
            }
    
            // Store class reference
            $this->clauses[$classUuid] = [
                'slug' => $classUuid,
                'type' => 'class',
                'name' => $node->class->toString()
            ];
    
            // Store method call
            $this->clauses[$methodUuid] = [
                'slug' => $methodUuid,
                'type' => 'method',
                'name' => $node->name->toString()
            ];
    
            // Return the static call structure
            return [
                'slug' => $uuid,
                'type' => 'static_call',
                'data' => array_merge([$classUuid, $methodUuid], $argsUuids)
            ];
        }
        return ['slug' => $uuid, 'type' => 'unknown', 'value' => ''];
    }

    private function generateUuid(): string {
        return \Str::uuid()->toString();
    }

    public function getResults(): array {
        return [
            'methods' => $this->functions,
            'statements' => $this->statements,
            'clauses' => $this->clauses
        ];
    }
}