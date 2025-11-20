<?php

namespace App\DTOs;

class HierarchyResult
{
    public function __construct(
        public readonly array $hierarchy,
        public readonly array $data,
        public readonly array $statements,
        public readonly array $clauses,
    ) {}

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'hierarchy' => $this->hierarchy,
            'data' => $this->data,
            'statements' => $this->statements,
            'clauses' => $this->clauses,
        ];
    }
}
