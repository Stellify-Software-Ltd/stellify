<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Http\Controllers\MethodController;
use App\Http\Controllers\StatementController;
use App\Http\Controllers\ClauseController;

use Prism\Prism\Prism;
use Prism\Prism\Enums\Provider;

use App\Services\PhpAssemblerService;


class AiPromptHandler
{
    protected $methodController;
    protected $statementController;
    protected $clauseController;

    public function __construct(
        MethodController $methodController, 
        StatementController $statementController, 
        ClauseController $clauseController,
        PhpAssemblerService $phpAssemblerService
    ) {
        $this->methodController = $methodController;
        $this->statementController = $statementController;
        $this->clauseController = $clauseController;
        $this->phpAssemblerService = $phpAssemblerService;
    }

    public function handlePrompt(array $prompt): array
    {
        $name = '';
        $description = '';
        $code = '';
        $userText = '';
        $language = '';
        $construct = '';
        $provider = 'openai';

        $apiReference = $this->getApiReference();

        if (!empty($prompt['statement'])) {
            $statement = $this->statementController->getStatement($prompt['statement'], false);
            $name = $statement['name'] ?? '';
            $description = $statement['description'] ?? '';
            foreach ($statement['data'] as $clauseUUID) {
                $clause = $this->clauseController->getClause($clauseUUID, false);
                if (!$this->phpAssemblerService->validateClause($clause)) {
                    continue; // Skip invalid clauses
                }
                $this->phpAssemblerService->assembleStatement($clause);
            }
            $this->phpAssemblerService->addCode("\n\t\t}");
            $code = $this->phpAssemblerService->getCode();
        } else if (!empty($prompt['method'])) {
            $method = $this->methodController->getMethod($prompt['method'], false);
            $name = $method['name'] ?? '';
            $description = $method['description'] ?? '';
            if (!empty($method)) {
                $methodCode = $this->phpAssemblerService->assembleFunction($method);
                foreach ($method['data'] as $statementUUID) {
                    $statement = $this->statementController->getStatement($statementUUID, false);
                    foreach ($statement['data'] as $clauseUUID) {
                        $clause = $this->clauseController->getClause($clauseUUID, false);
                        if (!$this->phpAssemblerService->validateClause($clause)) {
                            continue; // Skip invalid clauses
                        }
                        $this->phpAssemblerService->assembleStatement($clause);
                    }
                }
                $this->phpAssemblerService->addCode("\n\t\t}");
                $code = $this->phpAssemblerService->getCode();
                //$clauses = collect($statement['data'])->map(fn($uuid) => $this->clauseController->getClause($uuid, false))->toArray();
            }
        }

        if (!empty($prompt['element'])) {
            //fetch the element and construct the HTML
        }

        if (!empty($prompt['provider'])) {
            $provider = $prompt['provider'];
        }

        if (!empty($prompt['prompt'])) {
            $userText = $prompt['prompt'];
        }

        $response = $this->askGPT($userText, $apiReference, $name, $description, $code, $language, $construct, $provider);

        return $response;
    }

    private function getResource(string $path)
    {
        
    }

    private function askGPT(string $userText, string $apiReference, string $name, string $description, string $code, string $language, string $construct, string $provider): array
    {
        if ($code === '') {
            $promptTemplate = <<<EOT
                I have a Laravel development API with these endpoints:
                {$apiReference}
                
                User Request: {$userText}
                
                Please create a detailed step-by-step plan using my API endpoints to build this feature.
                
                Return your response as a JSON array of steps, where each step has:
                - action: the API endpoint to call
                - method: HTTP method (GET, POST, PUT, DELETE)
                - data: any data to send with the request
                - description: what this step accomplishes
                
                Example format:
                [
                    {
                        \"action\": \"/file\",
                        \"method\": \"POST\",
                        \"data\": {\"name\": \"UserController\", \"type\": \"controller\"},
                        \"description\": \"Create the main controller file\"
                    }
                ]
                
                Focus on the logical order: files before methods, methods before statements, etc.

                EOT;
        } else {

            $promptTemplate = <<<EOT
                I have a Laravel development API with these endpoints:
                {$apiReference}
                
                User Request: {$userText}
                
                Please create a detailed step-by-step plan using my API endpoints to build this feature.
                
                Return your response as a JSON array of steps, where each step has:
                - action: the API endpoint to call
                - method: HTTP method (GET, POST, PUT, DELETE)
                - data: any data to send with the request
                - description: what this step accomplishes
                
                Example format:
                [
                    {
                        \"action\": \"/file\",
                        \"method\": \"POST\",
                        \"data\": {\"name\": \"UserController\", \"type\": \"controller\"},
                        \"description\": \"Create the main controller file\"
                    }
                ]
                
                Focus on the logical order: files before methods, methods before statements, etc.

                EOT;

        }

        // $res = Http::withToken(env('OPENAI_API_KEY'))
        //     ->post('https://api.openai.com/v1/chat/completions', [
        //         'model' => 'gpt-4',
        //         'messages' => [
        //             ['role' => 'system', 'content' => 'You are an expert code reviewer.'],
        //             ['role' => 'user', 'content' => $promptTemplate],
        //         ],
        //         'temperature' => 0.4,
        //     ]);

        // $content = $res->json()['choices'][0]['message']['content'] ?? '{}';
        $response = [];
        $content = null;
        if ($provider == 'openai') {
            $content = Prism::text()
                ->using(Provider::OpenAI, 'gpt-4')
                ->withPrompt($promptTemplate)
                ->asText();
        } else {
            $content = Prism::text()
                ->using(Provider::OpenAI, 'gpt-4')
                ->withPrompt($promptTemplate)
                ->asText();
        }
        if (!empty($content) && !empty($content->text)) {
            $json = json_decode($content->text, true);
            dd($json);
            if (!empty($json) && is_array($json)) {
                foreach($json as $item) {
                    if (isset($item['text']) && isset($item['type'])) {
                        if ($item['type'] === 'code') {
                            $response[] = [
                                'uuid' => \Str::uuid()->toString(),
                                'text' => $item['text'],
                                'type' => 's-wrapper',
                                'tag' => 'code'
                            ];
                        } else if ($item['type'] === 'paragraph') {
                            $response[] = [
                                'uuid' => \Str::uuid()->toString(),
                                'text' => $item['text'],
                                'type' => 's-wrapper',
                                'tag' => 'p'
                            ];
                        } else {
                            // Handle other types if necessary
                            continue;
                        }
                    }   
                }
            }
        } else {
            $response[] = [
                'uuid' => \Str::uuid()->toString(),
                'text' => 'No response from AI',
                'type' => 's-wrapper',
                'tag' => 'p'
            ];
        }
        return $response;
    }

    private function prettyJson($data)
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Get condensed API reference for Claude
     */
    private function getApiReference()
    {
        return "
        # Your API Reference
        
        ## File & Code Structure
        - POST /file - Create file (name, type required)
        - GET /file/{uuid} - Get file
        - PUT /file/{uuid} - Update file
        - DELETE /file/{directory_uuid}/{file_uuid} - Delete file
        
        - POST /method - Create method (file_uuid, name, parameters)
        - GET /method/{uuid} - Get method
        - PUT /method/{uuid} - Update method
        - DELETE /method/{file_uuid}/{method_uuid} - Delete method
        
        - POST /statement - Create statement (method_uuid, code, type)
        - PUT /statement/{uuid} - Update statement
        - DELETE /statement/{method_uuid}/{statement_uuid} - Delete statement
        
        - POST /clause - Create clause (statement_uuid, condition, action)
        - PUT /clause/{uuid} - Update clause
        - DELETE /clause/{statement_uuid}/{clause_uuid} - Delete clause
        
        ## Database Operations
        - POST /database/migration/run - Run migrations
        - POST /database/factory/run - Run factories
        - POST /database/seeder/run - Run seeders
        
        ## Pages & UI
        - POST /page - Create page (name, route)
        - GET /pages - List pages
        - POST /element - Create element (page_uuid, type, properties)
        - POST /element/duplicate - Duplicate element
        
        ## Utilities
        - POST /code - Execute/test code
        - POST /config - Create configuration
        ";
    }
}