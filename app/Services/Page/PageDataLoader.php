<?php

namespace App\Services\Page;

use App\Models\File;
use App\Models\Method;
use App\Models\Statement;
use App\Models\Clause;
use App\Models\Meta;
use App\Models\Element;
use App\Services\PhpAssemblerService;
use App\Services\ViewAssemblerService;
use App\Services\Controller\ControllerExecutor;
use App\DTOs\PageContext;
use App\DTOs\PageData;
use Illuminate\Support\Facades\Cache;

class PageDataLoader
{
    private array $events = ['beforeMount', 'mounted', 'beforeUnmount', 'unmounted'];
    private array $paginatorMethods = [
        'currentPage', 'hasMorePages', 'hasPages', 'lastItem',
        'lastPage', 'nextPageUrl', 'perPage', 'previousPageUrl', 'total'
    ];

    public function __construct(
        private PhpAssemblerService $phpAssemblerService,
        private ViewAssemblerService $viewAssemblerService,
        private ControllerExecutor $controllerExecutor,
        private ElementHierarchyBuilder $hierarchyBuilder,
    ) {}

    /**
     * Load all page data including assets and elements
     */
    public function load($route, PageContext $context, $controllerResult): ?PageData
    {
        $pageData = json_decode($route->data);
        
        if (empty($pageData)) {
            return null;
        }

        // Process controller return if present
        $variables = $context->variables;
        $response = null;
        
        if ($controllerResult) {
            $processed = $this->controllerExecutor->processControllerReturn($controllerResult);
            $variables = array_merge($variables, $processed['variables']);
            $response = $processed['response'];
        }

        // Load page assets
        $this->loadGlobalFiles($pageData, $context->settings);
        $this->loadGlobalEvents($pageData, $context->settings);
        
        $files = $this->loadPageFiles($pageData);
        $meta = $this->loadPageMeta($pageData);
        
        // Build element hierarchy
        $hierarchy = $this->hierarchyBuilder->build($pageData->data ?? []);

        return new PageData(
            meta: $meta,
            body: $pageData,
            content: $hierarchy->data,
            clauses: $hierarchy->clauses,
            statements: $hierarchy->statements,
            methods: $files['methods'],
            files: $files['files'],
            variables: $variables,
            response: $response,
            fonts: null,
            project: null,
            css: null,
            config: null,
        );
    }

    /**
     * Load global files from settings
     */
    private function loadGlobalFiles(object $pageData, array $settings): void
    {
        if (empty($settings['app']['files'])) {
            return;
        }

        if (empty($pageData->files)) {
            $pageData->files = $settings['app']['files'];
        } else {
            foreach ($settings['app']['files'] as $file) {
                if (!in_array($file, $pageData->files)) {
                    $pageData->files[] = $file;
                }
            }
        }
    }

    /**
     * Load global event handlers from settings
     */
    private function loadGlobalEvents(object $pageData, array $settings): void
    {
        foreach ($this->events as $event) {
            if (empty($settings['app'][$event])) {
                continue;
            }

            if (empty($pageData->{$event})) {
                $pageData->{$event} = $settings['app'][$event];
            } else {
                foreach ($settings['app'][$event] as $eventId) {
                    if (!in_array($eventId, $pageData->{$event})) {
                        $pageData->{$event}[] = $eventId;
                    }
                }
            }
        }
    }

    /**
     * Load page JavaScript files and their methods
     */
    private function loadPageFiles(object $pageData): array
    {
        $filesData = [];
        $methodsData = [];

        if (empty($pageData->files)) {
            return ['files' => $filesData, 'methods' => $methodsData];
        }

        $jsFiles = File::whereIn('uuid', $pageData->files)->get();

        foreach ($jsFiles as $jsFile) {
            $jsFileData = json_decode($jsFile->data, true);
            $filesData[$jsFileData['uuid']] = $jsFileData;

            if (empty($jsFileData['data'])) {
                continue;
            }

            // Load methods for this file
            $jsMethods = Method::whereIn('uuid', $jsFileData['data'])->get();

            foreach ($jsMethods as $jsMethod) {
                $jsMethodData = json_decode($jsMethod->data, true);
                $methodsData[$jsMethodData['uuid']] = $jsMethodData;
            }
        }

        return ['files' => $filesData, 'methods' => $methodsData];
    }

    /**
     * Load page meta tags
     */
    private function loadPageMeta(object $pageData): array
    {
        if (empty($pageData->meta)) {
            return [];
        }

        $meta = Meta::whereIn('uuid', $pageData->meta)->get();
        
        return $meta->mapWithKeys(function ($tag) {
            $metaData = json_decode($tag->data, true);
            return [$metaData['uuid'] => $metaData];
        })->all();
    }
}
