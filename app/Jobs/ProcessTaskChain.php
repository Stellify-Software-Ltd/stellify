<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\FileController;
use App\Http\Controllers\MethodController;
use App\Http\Controllers\CodeController;
use App\Events\TaskChainProgress;

class ProcessTaskChain implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes
    public $tries = 3;

    protected $tasks;
    protected $userId;
    protected $sessionId;
    protected $currentFile;
    protected $currentMethod;
    protected $results;

    public function __construct(array $tasks, $userId = null, $sessionId = null)
    {
        $this->tasks = $tasks;
        $this->userId = $userId;
        $this->sessionId = $sessionId ?? uniqid();
        $this->currentFile = null;
        $this->currentMethod = null;
        $this->results = [];
    }

    /**
     * Execute the job - process all tasks sequentially
     */
    public function handle()
    {
        try {
            DB::beginTransaction();
            
            foreach ($this->tasks as $index => $task) {
                Log::info("Processing task {$index}: {$task['description']}");
                
                // Broadcast progress
                // broadcast(new TaskChainProgress([
                //     'session_id' => $this->sessionId,
                //     'current_step' => $index + 1,
                //     'total_steps' => count($this->tasks),
                //     'description' => $task['description'],
                //     'status' => 'processing'
                // ]));

                // Execute the task
                $result = $this->executeTask($task);
                
                if ($task['endpoint'] === '/api/file') {
                    $this->currentFile = $result['data']['uuid'];
                }
                
                if ($task['endpoint'] === '/api/method') {
                    $this->currentMethod = $result['data']['uuid'];
                }
                
                Log::info("Completed task {$index}: " . json_encode($result));
            }
            
            DB::commit();
            
            // Broadcast completion
            // broadcast(new TaskChainProgress([
            //     'session_id' => $this->sessionId,
            //     'current_step' => count($this->tasks),
            //     'total_steps' => count($this->tasks),
            //     'description' => 'All tasks completed successfully',
            //     'status' => 'completed',
            //     'results' => $this->results
            // ]));
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error("Task chain failed: " . $e->getMessage());
            
            // Broadcast error
            // broadcast(new TaskChainProgress([
            //     'session_id' => $this->sessionId,
            //     'status' => 'failed',
            //     'error' => $e->getMessage(),
            //     'failed_at_step' => $index ?? 0
            // ]));
            
            throw $e;
        }
    }

    /**
     * Execute a single task
     */
    private function executeTask(array $task)
    {
        $endpoint = $task['endpoint'];
        $method = $task['method'];
        $data = $task['data'];
        
        switch ($endpoint) {
            case '/api/file':
                return $this->executeFileTask($data);
                
            case '/api/method':
                return $this->executeMethodTask($data);
                
            default:
                if (str_starts_with($endpoint, '/api/code/')) {
                    return $this->executeCodeTask($endpoint, $data);
                }
                
                throw new \Exception("Unknown endpoint: {$endpoint}");
        }
    }

    /**
     * Execute file creation task
     */
    private function executeFileTask(array $data)
    {
        $controller = new FileController();
        $request = new \Illuminate\Http\Request($data);
        
        $response = $controller->createFile($request);
        
        if ($response->getStatusCode() !== 201) {
            throw new \Exception("File creation failed: " . $response->getContent());
        }
        
        return json_decode($response->getContent(), true);
    }

    /**
     * Execute method creation task
     */
    private function executeMethodTask(array $data)
    {
        if (empty($this->currentFile)) {
            throw new \Exception("No current file set for method creation");
        }

        $controller = new MethodController();
        $request = new \Illuminate\Http\Request($data);
        $request->merge(['file' => $this->currentFile]);
        $response = $controller->createMethod($request);
        
        if ($response->getStatusCode() !== 201) {
            throw new \Exception("Method creation failed: " . $response->getContent());
        }
        
        return json_decode($response->getContent(), true);
    }

    /**
     * Execute code parsing task
     */
    private function executeCodeTask(string $endpoint, array $data)
    {

        if (!$this->currentFile || !$this->currentMethod) {
            throw new \Exception("Invalid code endpoint format: {$endpoint}");
        }
        
        $controller = $controller = app(\App\Http\Controllers\CodeController::class);
        $request = new \Illuminate\Http\Request($data);

        $response = $controller->saveCode($this->currentFile, $this->currentMethod, $request);
        
        if ($response->getStatusCode() !== 200) {
            throw new \Exception("Code save failed: " . $response->getContent());
        }
        
        return json_decode($response->getContent(), true);
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception)
    {
        Log::error("ProcessTaskChain job failed: " . $exception->getMessage());
        
        broadcast(new TaskChainProgress([
            'session_id' => $this->sessionId,
            'status' => 'failed',
            'error' => $exception->getMessage()
        ]));
    }
}