<?php

namespace App\Services\Page;

use App\Models\Element;
use App\Models\Statement;
use App\Models\Clause;
use App\Services\PhpAssemblerService;
use App\DTOs\HierarchyResult;
use Illuminate\Support\Facades\Cache;

class ElementHierarchyBuilder
{
    private array $blockHierarchy = [];
    private array $data = [];
    private array $statements = [];
    private array $clauses = [];

    public function __construct(
        private PhpAssemblerService $phpAssemblerService,
    ) {}

    /**
     * Build element hierarchy from root elements
     */
    public function build(array $elementUuids): HierarchyResult
    {
        // Reset state
        $this->blockHierarchy = [];
        $this->data = [];
        $this->statements = [];
        $this->clauses = [];

        // Try to get from cache
        $cacheKey = 'element_hierarchy_' . md5(json_encode($elementUuids));
        
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return new HierarchyResult(
                hierarchy: $cached['hierarchy'],
                data: $cached['data'],
                statements: $cached['statements'],
                clauses: $cached['clauses'],
            );
        }

        // Build hierarchy
        $this->traverseElements($elementUuids);

        $result = new HierarchyResult(
            hierarchy: $this->blockHierarchy,
            data: $this->data,
            statements: $this->statements,
            clauses: $this->clauses,
        );

        // Cache the result
        Cache::put($cacheKey, [
            'hierarchy' => $result->hierarchy,
            'data' => $result->data,
            'statements' => $result->statements,
            'clauses' => $result->clauses,
        ], 3600);

        return $result;
    }

    /**
     * Recursively traverse elements and build hierarchy
     */
    private function traverseElements(array $blocks): void
    {
        foreach ($blocks as $uuid) {
            $this->blockHierarchy[] = $uuid;
            
            // Skip if we already have this block's data
            if (isset($this->data[$uuid])) {
                $blockData = $this->data[$uuid];
            } else {
                $block = Element::where('uuid', $uuid)->first();
                
                if (!$block || empty($block->data)) {
                    continue;
                }

                $blockData = json_decode($block->data);

                // Handle s-directive elements with statements
                if ($block->type === 's-directive' && !empty($blockData->statement)) {
                    $this->processDirectiveStatement($blockData);
                }

                // Cache the data immediately
                $this->data[$uuid] = $blockData;
            }

            // Recurse into children
            if (!empty($blockData->data)) {
                $this->traverseElements($blockData->data);
            }
        }
    }

    /**
     * Process a directive statement (s-if, s-for, etc.)
     */
    private function processDirectiveStatement(object $blockData): void
    {
        $statement = Statement::where('uuid', $blockData->statement)->first();
        
        if (!$statement) {
            return;
        }

        $this->phpAssemblerService->resetCode();
        $statementData = json_decode($statement->data);
        $this->statements[$statement->uuid] = $statementData;

        if (empty($statementData->data)) {
            return;
        }

        // Get statement clauses
        $clauses = Clause::whereIn('uuid', $statementData->data)
            ->get()
            ->keyBy('uuid');

        foreach ($statementData->data as $clauseUuid) {
            if (!isset($clauses[$clauseUuid])) {
                continue;
            }

            $clauseData = json_decode($clauses[$clauseUuid]->data, true);
            $this->clauses[$clauseUuid] = $clauseData;

            if ($clauseData) {
                $this->phpAssemblerService->assembleStatement($clauseData);
            }
        }

        $blockData->value = $this->phpAssemblerService->getCode();
    }
}
