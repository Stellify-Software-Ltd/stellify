<?php

namespace App\DTOs;

use App\Models\Route;

class ProcessedRequest
{
    public function __construct(
        public readonly ?Route $route = null,
        public readonly ?PageContext $context = null,
        public readonly mixed $controllerResult = null,
        public readonly ?PageData $pageData = null,
        public readonly mixed $dbConnection = null,
        public readonly ?\Throwable $error = null,
        private readonly string $status = 'success',
    ) {}

    /**
     * Create a not found result
     */
    public static function notFound(): self
    {
        return new self(status: 'not_found');
    }

    /**
     * Create a database error result
     */
    public static function databaseError(): self
    {
        return new self(status: 'database_error');
    }

    /**
     * Create an error result
     */
    public static function error(\Throwable $error): self
    {
        return new self(error: $error, status: 'error');
    }

    /**
     * Check if route was not found
     */
    public function isNotFound(): bool
    {
        return $this->status === 'not_found';
    }

    /**
     * Check if there was a database error
     */
    public function hasDatabaseError(): bool
    {
        return $this->status === 'database_error';
    }

    /**
     * Check if there was a general error
     */
    public function hasError(): bool
    {
        return $this->status === 'error';
    }

    /**
     * Check if should redirect
     */
    public function shouldRedirect(): bool
    {
        return $this->route && !empty($this->route->redirect_url);
    }

    /**
     * Check if request was successful
     */
    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }
}
