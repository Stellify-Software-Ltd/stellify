<?php

namespace App\Services\Controller;

use App\Models\File;
use App\Models\Method;
use App\Models\Statement;
use App\Models\Clause;
use App\DTOs\ControllerData;

class ControllerDataLoader
{
    /**
     * Load all controller data including dependencies
     */
    public function loadAll(string $controllerUuid): ControllerData
    {
        $controller = File::where('uuid', $controllerUuid)->firstOrFail();
        $controllerData = json_decode($controller->data, true);

        // Load direct dependencies
        $includes = $this->loadIncludes($controllerData['includes'] ?? []);
        $models = $this->loadModels($controllerData['models'] ?? []);
        $methods = $this->loadMethods($controllerData['data'] ?? []);
        
        // Load nested dependencies
        $statementIds = $this->extractNestedIds($methods, 'data');
        $statements = $this->loadStatements($statementIds);
        
        $clauseIds = array_merge(
            $this->extractNestedIds($statements, 'data'),
            $this->extractNestedIds($methods, 'parameters')
        );
        $clauses = $this->loadClauses($clauseIds);

        // Load model includes
        $modelIncludeIds = $this->extractNestedIds($models, 'includes');
        if (!empty($modelIncludeIds)) {
            $modelIncludes = $this->loadIncludes($modelIncludeIds);
            $includes = array_merge($includes, $modelIncludes);
        }

        return new ControllerData(
            controller: $controllerData,
            includes: $includes,
            models: $models,
            methods: $methods,
            statements: $statements,
            clauses: $clauses,
        );
    }

    /**
     * Extract nested IDs from array of items
     */
    private function extractNestedIds(array $items, string $key): array
    {
        return collect($items)
            ->pluck($key)
            ->filter()
            ->flatten()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Generic loader with JSON decoding
     */
    private function loadByType(string $model, array $uuids, bool $includeMetadata = false): array
    {
        if (empty($uuids)) {
            return [];
        }

        $items = $model::whereIn('uuid', $uuids)->get();

        return $items->mapWithKeys(function ($item) use ($includeMetadata) {
            $data = json_decode($item->data, true);
            
            // Some models need additional metadata
            if ($includeMetadata && $item instanceof File) {
                $data['name'] = $item->name;
                $data['namespace'] = $item->namespace;
            }
            
            return [$item->uuid => $data];
        })->all();
    }

    /**
     * Load file includes
     */
    private function loadIncludes(array $uuids): array
    {
        return $this->loadByType(File::class, $uuids, true);
    }

    /**
     * Load model files
     */
    private function loadModels(array $uuids): array
    {
        return $this->loadByType(File::class, $uuids, false);
    }

    /**
     * Load methods
     */
    private function loadMethods(array $uuids): array
    {
        return $this->loadByType(Method::class, $uuids, false);
    }

    /**
     * Load statements
     */
    private function loadStatements(array $uuids): array
    {
        return $this->loadByType(Statement::class, $uuids, false);
    }

    /**
     * Load clauses
     */
    private function loadClauses(array $uuids): array
    {
        return $this->loadByType(Clause::class, $uuids, false);
    }
}
