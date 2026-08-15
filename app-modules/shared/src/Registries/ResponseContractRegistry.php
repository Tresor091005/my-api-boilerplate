<?php

declare(strict_types=1);

namespace Lahatre\Shared\Registries;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Lahatre\Shared\Http\Responses\ResponseContract;
use Symfony\Component\Finder\Finder;

final class ResponseContractRegistry
{
    /** @var array<string, ResponseContract> */
    private array $contracts = [];

    /**
     * @param  array<string, array<string, mixed>>  $definitions
     */
    public function registerMany(array $definitions): void
    {
        $collisions = array_intersect(array_keys($definitions), array_keys($this->contracts));

        if ($collisions !== []) {
            throw new InvalidArgumentException(__('shared::exceptions.response_contract_collision', [
                'routes' => implode(', ', $collisions),
            ]));
        }

        foreach ($definitions as $routeName => $definition) {
            $this->contracts[$routeName] = ResponseContract::fromArray($definition);
        }
    }

    public function discover(): void
    {
        if (!$this->loadFromCache()) {
            $this->registerMany($this->discoverDefinitions());
        }
    }

    public function cache(): void
    {
        $definitions = $this->discoverDefinitions();

        $this->contracts = [];
        $this->registerMany($definitions);

        File::put(
            App::bootstrapPath('cache/response-contracts.php'),
            '<?php return '.var_export($definitions, true).';'.PHP_EOL,
        );
    }

    public function clear(): void
    {
        $cachePath = App::bootstrapPath('cache/response-contracts.php');

        if (File::exists($cachePath)) {
            File::delete($cachePath);
        }

        $this->contracts = [];
    }

    /** @return list<string> */
    public function routeNames(): array
    {
        return array_keys($this->contracts);
    }

    public function forRoute(?string $routeName): ?ResponseContract
    {
        return $routeName === null ? null : ($this->contracts[$routeName] ?? null);
    }

    /** @return array<string, array<string, mixed>> */
    private function discoverDefinitions(): array
    {
        $definitions = [];
        $rootConfig = config_path('response-contracts.php');

        if (File::exists($rootConfig)) {
            $definitions = $this->mergeDefinitions(
                $definitions,
                $this->loadDefinitionsFromConfig($rootConfig),
            );
        }

        $modulesDirectory = base_path(config('app-modules.modules_directory', 'app-modules'));

        if (!File::isDirectory($modulesDirectory)) {
            return $definitions;
        }

        $finder = new Finder;
        $finder->directories()->in($modulesDirectory)->depth(0);

        foreach ($finder as $moduleDirectory) {
            $moduleConfig = $moduleDirectory->getPathname().'/config/response-contracts.php';

            if (!File::exists($moduleConfig)) {
                continue;
            }

            $definitions = $this->mergeDefinitions(
                $definitions,
                $this->loadDefinitionsFromConfig($moduleConfig),
            );
        }

        return $definitions;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadDefinitionsFromConfig(string $configPath): array
    {
        $config = require $configPath;

        if (!is_array($config)) {
            throw new InvalidArgumentException(__('shared::exceptions.response_contract_config_invalid', [
                'path' => Str::after($configPath, base_path().DIRECTORY_SEPARATOR),
            ]));
        }

        $sharedShapes = $config['_shapes'] ?? [];

        if (!is_array($sharedShapes)) {
            throw new InvalidArgumentException(__('shared::exceptions.response_contract_shapes_invalid', [
                'path' => Str::after($configPath, base_path().DIRECTORY_SEPARATOR),
            ]));
        }

        unset($config['_shapes']);

        return $this->resolveShapeReferences($config, $sharedShapes, $configPath);
    }

    /**
     * @param  array<string, mixed>  $definitions
     * @param  array<string, mixed>  $sharedShapes
     * @return array<string, array<string, mixed>>
     */
    private function resolveShapeReferences(
        array $definitions,
        array $sharedShapes,
        string $configPath,
    ): array {
        foreach ($definitions as $routeName => $definition) {
            if (!is_array($definition) || !isset($definition['shapes'])) {
                continue;
            }

            if (!is_array($definition['shapes'])) {
                throw new InvalidArgumentException(__('shared::exceptions.response_contract_shapes_invalid', [
                    'path' => Str::after($configPath, base_path().DIRECTORY_SEPARATOR),
                ]));
            }

            foreach ($definition['shapes'] as $shapeName => $shapeDefinition) {
                $definition['shapes'][$shapeName] = $this->resolveShapeDefinition(
                    $shapeDefinition,
                    $sharedShapes,
                    $configPath,
                    [],
                );
            }

            $definitions[$routeName] = $definition;
        }

        return $definitions;
    }

    /**
     * @param  array<string, mixed>  $sharedShapes
     * @param  list<string>  $references
     * @return array<string, mixed>
     */
    private function resolveShapeDefinition(
        mixed $shapeDefinition,
        array $sharedShapes,
        string $configPath,
        array $references,
    ): array {
        if (!is_array($shapeDefinition)) {
            throw new InvalidArgumentException(__('shared::exceptions.response_contract_shapes_invalid', [
                'path' => Str::after($configPath, base_path().DIRECTORY_SEPARATOR),
            ]));
        }

        if (!array_key_exists('ref', $shapeDefinition)) {
            return $shapeDefinition;
        }

        $reference = $shapeDefinition['ref'];

        if (!is_string($reference) || $reference === '') {
            throw new InvalidArgumentException(__('shared::exceptions.response_shape_reference_invalid', [
                'reference' => is_scalar($reference) ? (string) $reference : get_debug_type($reference),
            ]));
        }

        if (in_array($reference, $references, true)) {
            throw new InvalidArgumentException(__('shared::exceptions.response_shape_reference_cycle', [
                'references' => implode(' -> ', [...$references, $reference]),
            ]));
        }

        if (!array_key_exists($reference, $sharedShapes)) {
            throw new InvalidArgumentException(__('shared::exceptions.response_shape_reference_missing', [
                'reference' => $reference,
            ]));
        }

        return $this->resolveShapeDefinition(
            $sharedShapes[$reference],
            $sharedShapes,
            $configPath,
            [...$references, $reference],
        );
    }

    /**
     * @param  array<string, array<string, mixed>>  $definitions
     * @param  array<string, mixed>  $additionalDefinitions
     * @return array<string, array<string, mixed>>
     */
    private function mergeDefinitions(array $definitions, array $additionalDefinitions): array
    {
        $collisions = array_intersect(array_keys($definitions), array_keys($additionalDefinitions));

        if ($collisions !== []) {
            throw new InvalidArgumentException(__('shared::exceptions.response_contract_collision', [
                'routes' => implode(', ', $collisions),
            ]));
        }

        return [...$definitions, ...$additionalDefinitions];
    }

    private function loadFromCache(): bool
    {
        if (config('app.env') !== 'production') {
            return false;
        }

        $cachePath = App::bootstrapPath('cache/response-contracts.php');

        if (!File::exists($cachePath)) {
            return false;
        }

        $definitions = require $cachePath;

        if (!is_array($definitions)) {
            throw new InvalidArgumentException(__('shared::exceptions.response_contract_cache_invalid'));
        }

        $this->registerMany($definitions);

        return true;
    }
}
