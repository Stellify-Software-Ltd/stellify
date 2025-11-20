<?php

namespace App\DTOs;

class PageData
{
    public function __construct(
        public readonly array $meta,
        public readonly object $body,
        public readonly array $content,
        public readonly array $clauses,
        public readonly array $statements,
        public readonly array $methods,
        public readonly array $files,
        public readonly array $variables = [],
        public readonly mixed $response = null,
        public readonly mixed $fonts = null,
        public readonly mixed $project = null,
        public readonly mixed $css = null,
        public readonly mixed $config = null,
    ) {}

    /**
     * Convert to array for view rendering
     */
    public function toArray(): array
    {
        return [
            'meta' => $this->meta,
            'body' => $this->body,
            'content' => $this->content,
            'clauses' => $this->clauses,
            'statements' => $this->statements,
            'methods' => $this->methods,
            'files' => $this->files,
            'variables' => $this->variables,
            'fonts' => $this->fonts,
            'project' => $this->project,
            'css' => $this->css,
            'config' => $this->config,
        ];
    }
}
