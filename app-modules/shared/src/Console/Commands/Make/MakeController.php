<?php

declare(strict_types=1);

namespace Lahatre\Shared\Console\Commands\Make;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use InterNACHI\Modular\Console\Commands\Make\MakeController as ModularMakeController;

final class MakeController extends ModularMakeController
{
    protected function buildClass($name): string
    {
        $class = str_replace('IlluminateHttpJsonResponse', JsonResponse::class, parent::buildClass($name));

        return str_replace('IlluminateHttpResponse', Response::class, $class);
    }

    protected function handleTestCreation($path): bool
    {
        if (!$this->option('test') && !$this->option('pest') && !$this->option('phpunit')) {
            return false;
        }

        $name = pathinfo($path, PATHINFO_FILENAME).'Test';

        return $this->call('make:test', [
            'name'      => $name,
            '--pest'    => $this->option('pest'),
            '--phpunit' => $this->option('phpunit'),
            '--force'   => $this->option('force'),
        ]) === 0;
    }

    protected function buildFormRequestReplacements(array $replace, $modelClass): array
    {
        $replace = parent::buildFormRequestReplacements($replace, $modelClass);

        if ($module = $this->module()) {
            $requestNamespace = rtrim((string) $module->namespaces->first(), '\\').'\\Http\\Requests';
            $storeRequest = $replace['{{ storeRequest }}'];
            $updateRequest = $replace['{{ updateRequest }}'];
            $replace['{{ namespacedRequests }}'] = $requestNamespace.'\\'.$storeRequest.';';

            if ($storeRequest !== $updateRequest) {
                $replace['{{ namespacedRequests }}'] .= PHP_EOL.'use '.$requestNamespace.'\\'.$updateRequest.';';
            }

            $replace['{{namespacedRequests}}'] = $replace['{{ namespacedRequests }}'];
            $replace['{{ namespacedStoreRequest }}'] = $requestNamespace.'\\'.$storeRequest;
            $replace['{{namespacedStoreRequest}}'] = $replace['{{ namespacedStoreRequest }}'];
            $replace['{{ namespacedUpdateRequest }}'] = $requestNamespace.'\\'.$updateRequest;
            $replace['{{namespacedUpdateRequest}}'] = $replace['{{ namespacedUpdateRequest }}'];
        }

        return $replace;
    }

    protected function generateFormRequests($modelClass, $storeRequestClass, $updateRequestClass): array
    {
        $entity = class_basename($modelClass);
        $createRequestClass = $entity.'CreateRequest';
        $updateRequestClass = $entity.'UpdateRequest';

        $this->call('make:request', [
            'name' => $createRequestClass,
        ]);

        $this->call('make:request', [
            'name' => $updateRequestClass,
        ]);

        return [$createRequestClass, $updateRequestClass];
    }
}
