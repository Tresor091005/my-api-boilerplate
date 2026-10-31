<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Declaration;

use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ApiResponse;
use Lahatre\ResponseSelection\Enums\ResponseShape;
use Lahatre\ResponseSelection\Exceptions\ResponseDeclarationException;
use Lahatre\ResponseSelection\FieldComputer;
use Lahatre\ResponseSelection\ResourceResolver;
use Lahatre\ResponseSelection\VirtualIncludeLoader;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

/**
 * Reads what each controller action answers with straight from its source code, without running it,
 * and checks that the Resources it names are valid.
 */
final class ResponseDeclarationAnalyzer
{
    /**
     * Position of each argument of the ApiResponse factories, for calls that pass them by position.
     */
    private const array FACTORY_ARGUMENTS = [
        'item'       => ['resourceClass' => 1, 'status' => 2, 'possibleStatuses' => 3],
        'list'       => ['resourceClass' => 1, 'status' => 2, 'possibleStatuses' => 3],
        'cursorPage' => ['resourceClass' => 1, 'status' => 2, 'possibleStatuses' => 3],
    ];

    /**
     * Parsed source files, so each file is read only once. Null for a file that never names ApiResponse.
     *
     * @var array<string, array<Node>|null>
     */
    private array $files = [];

    /**
     * Last parse of each file in this process, reused while the file's content stays the same.
     *
     * @var array<string, array{hash: string, statements: array<Node>}>
     */
    private static array $parsed = [];

    private static ?Parser $parser = null;

    /**
     * What a controller action answers with, or null when it does not use ApiResponse.
     */
    public function forAction(string $controller, string $method): ?ResponseDeclaration
    {
        $action = "{$controller}@{$method}";
        $reflection = new ReflectionMethod($controller, $method);
        $file = $reflection->getFileName();

        if ($file === false) {
            return null;
        }

        $statements = $this->parse($file, $action);

        // a file that never names ApiResponse cannot call it
        if ($statements === null) {
            return null;
        }

        // Match by line range, not by name alone: a trait method imported under an alias keeps its
        // original name in the file
        $line = $reflection->getStartLine();
        $candidates = array_values(array_filter(
            (new NodeFinder)->findInstanceOf($statements, ClassMethod::class),
            fn (ClassMethod $node): bool => $node->getStartLine() <= $line && $line <= $node->getEndLine(),
        ));
        $named = array_values(array_filter($candidates, fn (ClassMethod $node): bool => $node->name->toLowerString() === strtolower($method)));
        $node = $named[0] ?? (count($candidates) === 1 ? $candidates[0] : null);

        if (!$node instanceof ClassMethod) {
            throw ResponseDeclarationException::actionNotFound($action, $file);
        }

        $calls = (new NodeFinder)->find($node->stmts ?? [], fn (Node $node): bool => $node instanceof StaticCall
            && $node->class instanceof Name && strtolower($node->class->toString()) === strtolower(ApiResponse::class));

        return $calls === [] ? null : $this->declaration($node, $action);
    }

    /**
     * @return array<Node>|null null when the file never names ApiResponse
     */
    private function parse(string $file, string $action): ?array
    {
        if (array_key_exists($file, $this->files)) {
            return $this->files[$file];
        }

        $code = file_get_contents($file);

        if ($code === false) {
            throw ResponseDeclarationException::actionNotFound($action, $file);
        }

        // a call to ApiResponse names it, in the call or in a `use`, so such a file is not parsed
        if (stripos($code, class_basename(ApiResponse::class)) === false) {
            return $this->files[$file] = null;
        }

        $hash = md5($code);

        if ((self::$parsed[$file]['hash'] ?? null) !== $hash) {
            self::$parser ??= (new ParserFactory)->createForNewestSupportedVersion();
            $statements = self::$parser->parse($code) ?? [];
            self::$parsed[$file] = ['hash' => $hash, 'statements' => new NodeTraverser(new NameResolver)->traverse($statements)];
        }

        return $this->files[$file] = self::$parsed[$file]['statements'];
    }

    public function declaration(?ClassMethod $method, ?string $action = null): ResponseDeclaration
    {
        $action ??= $method?->name->toString() ?? 'unknown action';
        $dynamic = $method === null ? [] : (new NodeFinder)->find($method->stmts ?? [], fn (Node $node): bool => $node instanceof StaticCall
            && $node->class instanceof Name && strtolower($node->class->toString()) === strtolower(ApiResponse::class)
            && !$node->name instanceof Identifier);

        if ($dynamic !== []) {
            throw ResponseDeclarationException::dynamicFactoryCall($action);
        }

        $calls = $this->factoryCalls($method);

        if ($calls === [] && $this->apiResponseCalls($method, ['noContent']) !== []) {
            return ResponseDeclaration::noContentOnly();
        }

        if (count($calls) !== 1) {
            throw ResponseDeclarationException::singleFactoryCallExpected($action);
        }

        $factory = $this->factoryName($calls[0]);
        $arguments = $calls[0]->getArgs();
        $positions = self::FACTORY_ARGUMENTS[$factory];
        $resource = $this->argument($arguments, 'resourceClass', $positions['resourceClass'])?->value;
        $status = $this->argument($arguments, 'status', $positions['status'])?->value;
        $allowed = $this->argument($arguments, 'possibleStatuses', $positions['possibleStatuses'])?->value;

        if (!$resource instanceof ClassConstFetch || !$resource->class instanceof Name
            || !$resource->name instanceof Identifier || $resource->name->toString() !== 'class') {
            throw ResponseDeclarationException::resourceClassNotLiteral($action, $factory);
        }

        $codes = $this->possibleStatuses($allowed, $action);
        if ($status !== null && !$status instanceof Int_ && $codes === []) {
            throw ResponseDeclarationException::statusNotLiteral($action, $factory);
        }

        if ($status === null || $status instanceof Int_) {
            $code = $status->value ?? 200;
            $this->validateStatus($code, $action);

            if ($codes !== [] && !in_array($code, $codes, true)) {
                throw ResponseDeclarationException::statusNotPossible($action, $code);
            }

            $codes = $codes !== [] ? $codes : [$code];
        }

        $resourceClass = $resource->class->toString();
        $this->validateResource($resourceClass);

        return new ResponseDeclaration($resourceClass, ResponseShape::tryFrom($factory), $codes, $this->apiResponseCalls($method, ['noContent']) !== []);
    }

    /**
     * Which factory a call uses, spelled as in FACTORY_ARGUMENTS.
     */
    private function factoryName(StaticCall $call): string
    {
        $called = $call->name instanceof Identifier ? $call->name->toString() : '';

        foreach (array_keys(self::FACTORY_ARGUMENTS) as $factory) {
            if (strcasecmp($factory, $called) === 0) {
                return $factory;
            }
        }

        throw ResponseDeclarationException::notAResponseFactory($called);
    }

    /**
     * The ApiResponse::item(), list() and cursorPage() calls of an action.
     *
     * @return array<int, StaticCall>
     */
    public function factoryCalls(?ClassMethod $method): array
    {
        return $this->apiResponseCalls($method, array_keys(self::FACTORY_ARGUMENTS));
    }

    /**
     * @param  array<int, string>  $names  case-insensitive, like PHP
     * @return array<int, StaticCall>
     */
    private function apiResponseCalls(?ClassMethod $method, array $names): array
    {
        if ($method === null) {
            return [];
        }

        $names = array_map(strtolower(...), $names);

        /** @var array<int, StaticCall> $calls */
        $calls = (new NodeFinder)->find($method->stmts ?? [], fn (Node $node): bool => $node instanceof StaticCall
            && $node->class instanceof Name && strtolower($node->class->toString()) === strtolower(ApiResponse::class)
            && $node->name instanceof Identifier && in_array($node->name->toLowerString(), $names, true));

        return $calls;
    }

    /**
     * @return array<int, int>
     */
    private function possibleStatuses(?Node $node, string $action): array
    {
        if ($node === null) {
            return [];
        }

        if (!$node instanceof Array_ || $node->items === []) {
            throw ResponseDeclarationException::possibleStatusesNotLiteral($action);
        }

        $codes = [];
        foreach ($node->items as $item) {
            if ($item === null || $item->unpack || $item->key !== null || !$item->value instanceof Int_) {
                throw ResponseDeclarationException::possibleStatusesNotLiteral($action);
            }

            $this->validateStatus($item->value->value, $action);
            $codes[] = $item->value->value;
        }

        return array_values(array_unique($codes));
    }

    private function validateStatus(int $code, string $action): void
    {
        if ($code < 200 || $code >= 300 || in_array($code, [204, 205], true)) {
            throw ResponseDeclarationException::statusWithoutContent($action, $code);
        }
    }

    public function validateResource(string $resourceClass): void
    {
        $visited = [];
        $this->visit($resourceClass, $resourceClass, $visited);
    }

    /**
     * @param  array<string, true>  $visited
     */
    private function visit(string $resourceClass, string $path, array &$visited): void
    {
        $this->requireClass($resourceClass, ApiResource::class, $path);

        if (isset($visited[$resourceClass])) {
            return;
        }
        $visited[$resourceClass] = true;

        try {
            $definition = $resourceClass::definition();
        } catch (Throwable $exception) {
            throw ResponseDeclarationException::definitionNotBuilt($path, $exception);
        }

        foreach ($definition->fieldKeys() as $key) {
            $computedBy = $definition->fieldDefinition($key)['computedBy'] ?? null;

            if (is_string($computedBy)) {
                $this->requireClass($computedBy, FieldComputer::class, "{$path}.{$key}");
            }
        }

        foreach ($definition->includeNames() as $name) {
            $include = $definition->includeDefinition($name);
            $childPath = "{$path}.{$name}";

            if ($include === null) {
                continue;
            }

            if (is_string($include['loader'])) {
                $this->requireClass($include['loader'], VirtualIncludeLoader::class, $childPath);
            }

            if ($include['resource'] !== null) {
                $this->requireClass($include['resource'], ApiResource::class, $childPath);
            }

            if (is_string($include['resolver'])) {
                $this->requireClass($include['resolver'], ResourceResolver::class, $childPath);
            }

            try {
                $children = $definition->includeResourceClasses($name);
            } catch (Throwable $exception) {
                throw ResponseDeclarationException::childResourcesNotResolved($childPath, $exception);
            }

            if ($children === []) {
                throw ResponseDeclarationException::includeWithoutChildResource($childPath);
            }

            foreach ($children as $child) {
                $this->visit($child, $childPath, $visited);
            }
        }
    }

    private function requireClass(string $class, string $contract, string $path): void
    {
        if (!is_subclass_of($class, $contract) || !new ReflectionClass($class)->isInstantiable()) {
            throw ResponseDeclarationException::classNotConcrete($path, $class, $contract);
        }
    }

    /**
     * @param  array<int, Arg>  $arguments
     */
    private function argument(array $arguments, string $name, int $position): ?Arg
    {
        foreach ($arguments as $index => $argument) {
            if ($argument->name?->toString() === $name || ($argument->name === null && $index === $position)) {
                return $argument;
            }
        }

        return null;
    }
}
