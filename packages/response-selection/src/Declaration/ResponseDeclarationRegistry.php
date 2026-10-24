<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Declaration;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\Exceptions\ResponseDeclarationException;

/**
 * Knows what each controller action answers with. In production it reads a prebuilt cache file;
 * otherwise it reads each action's source the first time it is asked.
 */
final class ResponseDeclarationRegistry
{
    /** @var array<string, ResponseDeclaration|null> */
    private array $declarations = [];

    /** @var array<string, array<string, mixed>|null>|null */
    private ?array $cached = null;

    private bool $cacheRead = false;

    public function __construct(
        private readonly Router $router,
        private readonly ResponseDeclarationAnalyzer $analyzer,
        private readonly ?string $cachePath = null,
    ) {}

    public function for(Route $route): ?ResponseDeclaration
    {
        $action = $route->getActionName();

        if (!str_contains($action, '@')) {
            return null;
        }

        if (array_key_exists($action, $this->declarations)) {
            return $this->declarations[$action];
        }

        $cached = $this->cached();

        // A null entry means no ApiResponse call; a missing entry means the action is newer than the cache
        if ($cached !== null && array_key_exists($action, $cached)) {
            return $this->declarations[$action] = $cached[$action] === null ? null : ResponseDeclaration::fromArray($cached[$action]);
        }

        [$controller, $method] = explode('@', $action, 2);

        return $this->declarations[$action] = $this->analyzer->forAction($controller, $method);
    }

    /**
     * Reads every routed action, so a bad declaration fails when the cache is built.
     *
     * @return array<string, array{resource: class-string<ApiResource>|null, shape: string|null, statuses: array<int, int>, can_be_empty: bool}|null>
     */
    public function build(): array
    {
        $declarations = [];

        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            $action = $route->getActionName();

            if (!str_contains($action, '@') || array_key_exists($action, $declarations)) {
                continue;
            }

            [$controller, $method] = explode('@', $action, 2);
            $declarations[$action] = $this->analyzer->forAction($controller, $method)?->toArray();
        }

        ksort($declarations);

        return $declarations;
    }

    public function cache(): void
    {
        $declarations = $this->build();

        File::put($this->cachePath(), '<?php return '.var_export($declarations, true).';'.PHP_EOL);

        $this->forget();
    }

    public function clear(): void
    {
        File::delete($this->cachePath());

        $this->forget();
    }

    public function cachePath(): string
    {
        return $this->cachePath ?? App::bootstrapPath('cache/response-declarations.php');
    }

    private function forget(): void
    {
        $this->declarations = [];
        $this->cached = null;
        $this->cacheRead = false;
    }

    /**
     * The cache file's content, read once and only in production.
     *
     * @return array<string, array<string, mixed>|null>|null
     */
    private function cached(): ?array
    {
        if ($this->cacheRead) {
            return $this->cached;
        }

        $this->cacheRead = true;

        if (config('app.env') !== 'production' || !File::exists($this->cachePath())) {
            return null;
        }

        $cached = require $this->cachePath();

        if (!is_array($cached)) {
            throw ResponseDeclarationException::cacheInvalid($this->cachePath());
        }

        return $this->cached = $cached;
    }
}
