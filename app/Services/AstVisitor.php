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
        dd($node);
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
                'uuid' => $paramUuid,
                'name' => $param->var->name,
                'type' => 'variable',
            ];
        }, $node->params);;

        $this->functions[] = [
            'uuid' => $uuid,
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
            'uuid' => $stmtUuid,
            'data' => [$varUuid, $opUuid, $valUuid],
        ];

        if ($node->var instanceof Node\Expr\Variable) {
            $this->clauses[$varUuid] = [
                'uuid' => $varUuid,
                'type' => 'variable',
                'value' => $node->var->name,
            ];
        }

        $this->clauses[$opUuid] = [
            'uuid' => $opUuid,
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
            'uuid' => $stmtUuid,
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
            'uuid' => $stmtUuid,
            'type' => $type,
            'data' => [],
        ];

        if ($this->currentFunction) {
            $this->currentFunction['data'][] = $stmtUuid;
        }
    }

    private function processValue(Node $node, string $uuid): array {
        if ($node instanceof Node\Scalar\String_) {
            return ['uuid' => $uuid, 'type' => 'string', 'value' => '"' . $node->value . '"'];
        }
        if ($node instanceof Node\Scalar\LNumber) {
            return ['uuid' => $uuid, 'type' => 'integer', 'value' => (string) $node->value];
        }
        if ($node instanceof Node\Scalar\DNumber) {
            return ['uuid' => $uuid, 'type' => 'float', 'value' => (string) $node->value];
        }
        if ($node instanceof Node\Expr\Variable) {
            return ['uuid' => $uuid, 'type' => 'variable', 'value' => $node->name];
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
                'uuid' => $methodUuid,
                'type' => 'method',
                'name' => $node->name->toString()
            ];
    
            return [
                'uuid' => $uuid,
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
                'uuid' => $classUuid,
                'type' => 'class',
                'name' => $node->class->toString()
            ];
    
            // Store method call
            $this->clauses[$methodUuid] = [
                'uuid' => $methodUuid,
                'type' => 'method',
                'name' => $node->name->toString()
            ];
    
            // Return the static call structure
            return [
                'uuid' => $uuid,
                'type' => 'static_call',
                'data' => array_merge([$classUuid, $methodUuid], $argsUuids)
            ];
        }
        return ['uuid' => $uuid, 'type' => 'unknown', 'value' => ''];
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