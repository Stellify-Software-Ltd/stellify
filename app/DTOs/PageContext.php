<?php

namespace App\DTOs;

use Illuminate\Http\Request;
use App\Models\Route;
use App\Models\User;

class PageContext
{
    public function __construct(
        public readonly Request $request,
        public readonly ?Route $route,
        public readonly ?User $user,
        public readonly array $variables,
        public readonly array $settings,
        public readonly string $path,
        public readonly array $uriSegments,
    ) {}

    /**
     * Get a variable value
     */
    public function getVariable(string $key, $default = null)
    {
        return $this->variables[$key] ?? $default;
    }

    /**
     * Get a setting value
     */
    public function getSetting(string $key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Check if user is authenticated
     */
    public function isAuthenticated(): bool
    {
        return $this->user !== null;
    }

    /**
     * Check if email is verified
     */
    public function isEmailVerified(): bool
    {
        return $this->user && $this->user->email_verified_at !== null;
    }
}
