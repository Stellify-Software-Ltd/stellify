<?php

namespace App\Services\Response;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\DTOs\ProcessedRequest;

class ResponseFactory
{
    /**
     * Create appropriate response based on request type and result
     */
    public function make(Request $request, ProcessedRequest $result): Response
    {
        // Handle error states
        if ($result->isNotFound()) {
            return $this->handleNotFound($request);
        }

        if ($result->hasDatabaseError()) {
            return $this->handleDatabaseError($request);
        }

        if ($result->hasError()) {
            return $this->handleError($request, $result);
        }

        // Handle redirects
        if ($result->shouldRedirect()) {
            return redirect(
                $result->route->redirect_url,
                $result->route->status_code ?? 302
            );
        }

        // Handle email verification requirements
        if ($this->requiresEmailVerification($result)) {
            return redirect('/email/verify');
        }

        // Handle POST requests - return controller result
        if ($result->route->method === 'POST') {
            return $this->makeJsonResponse($result->controllerResult);
        }

        // Handle JSON API requests
        if ($request->wantsJson()) {
            return $this->makeJsonResponse($result->controllerResult);
        }

        // Handle web page requests
        return $this->makePageResponse($request, $result);
    }

    /**
     * Handle 404 Not Found
     */
    private function handleNotFound(Request $request): Response
    {
        if ($request->wantsJson()) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->view('errors.404', [], 404);
    }

    /**
     * Handle database connection errors
     */
    private function handleDatabaseError(Request $request): Response
    {
        if ($request->wantsJson()) {
            return response()->json([
                'error' => 'Database connection unavailable'
            ], 503);
        }

        return response()->view('errors.database', [], 503);
    }

    /**
     * Handle general errors
     */
    private function handleError(Request $request, ProcessedRequest $result): Response
    {
        $error = $result->error;

        if ($request->wantsJson()) {
            return response()->json([
                'error' => $error->getMessage(),
            ], 500);
        }

        return response()->view('errors.code-error', [
            'error' => $error->getMessage(),
            'file' => $error->getFile(),
            'line' => $error->getLine(),
            'trace' => $error->getTraceAsString(),
        ], 500);
    }

    /**
     * Check if email verification is required
     */
    private function requiresEmailVerification(ProcessedRequest $result): bool
    {
        $user = $result->context->user;
        $route = $result->route;
        $settings = $result->context->settings;

        // Check route-level requirement
        if ($route->email_verify && $user && !$user->email_verified_at) {
            return true;
        }

        // Check global requirement
        if (!empty($settings['auth']['MustVerifyEmail']) && $user && !$user->email_verified_at) {
            return true;
        }

        return false;
    }

    /**
     * Create JSON response
     */
    private function makeJsonResponse($data): Response
    {
        if ($data instanceof Response) {
            return $data;
        }

        return response()->json($data);
    }

    /**
     * Create page response with view
     */
    private function makePageResponse(Request $request, ProcessedRequest $result): Response
    {
        $viewData = $this->prepareViewData($request, $result);

        return response()->view('page', $viewData);
    }

    /**
     * Prepare data array for view rendering
     */
    private function prepareViewData(Request $request, ProcessedRequest $result): array
    {
        $variables = $result->context->variables;
        $pageData = $result->pageData;

        // Merge old input from validation errors
        if ($old = $request->old()) {
            $variables = array_merge($variables, $old);
        }

        // Merge validation errors
        if ($errors = $request->session()->get('errors')) {
            foreach ($errors->getMessages() as $key => $error) {
                if ($key === 'stellifyError') {
                    // Handle special Stellify errors
                    $variables['stellify_error'] = $error[0];
                } else {
                    $variables[$key . '-error'] = $error;
                }
            }
        }

        return [
            'settings' => $result->context->settings['app'] ?? null,
            'fonts' => $pageData?->fonts ?? null,
            'project' => $pageData?->project ?? '',
            'meta' => $pageData?->meta ?? null,
            'body' => $pageData?->body ?? null,
            'css' => $pageData?->css ?? null,
            'content' => $pageData?->content ?? null,
            'clauses' => $pageData?->clauses ?? null,
            'statements' => $pageData?->statements ?? null,
            'methods' => $pageData?->methods ?? null,
            'files' => $pageData?->files ?? null,
            'user' => $result->context->user,
            'variables' => $variables,
            'config' => $pageData?->config ?? null,
            'path' => $result->context->path === '/' ? 'home' : $result->context->path,
        ];
    }
}
