<?php

namespace App\DTOs;

use Illuminate\Http\Request;

class CodeExecutionContext
{
    public function __construct(
        public readonly Request $request,
        public readonly array $controllerData,
        public readonly array $methodData,
        public readonly array $includes,
        public readonly array $models,
        public readonly array $methods,
        public readonly array $statements,
        public readonly array $clauses,
        public readonly array $variables,
        public readonly array $settings,
        public readonly ?string $type = null,
        public readonly bool $returnCode = false
    ) {}
}
