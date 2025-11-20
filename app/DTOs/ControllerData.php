<?php

namespace App\DTOs;

class ControllerData
{
    public function __construct(
        public readonly array $controller,
        public readonly array $includes,
        public readonly array $models,
        public readonly array $methods,
        public readonly array $statements,
        public readonly array $clauses,
    ) {}

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'controller' => $this->controller,
            'includes' => $this->includes,
            'models' => $this->models,
            'methods' => $this->methods,
            'statements' => $this->statements,
            'clauses' => $this->clauses,
        ];
    }
}
