<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Symfony\Component\Finder\Finder;

/**
 * Words that are commonly found in code but are not user-facing phrases.
 * This helps reduce false positives.
 *
 * @var array<int, string>
 */
const IGNORED_WORDS = [
    // Common programming keywords & constructs
    'class', 'public', 'protected', 'private', 'function', 'return', 'true', 'false', 'null', 'void',
    'string', 'int', 'array', 'bool', 'float', 'object', 'mixed', 'static', 'self', 'parent', 'echo',
    '__construct', '__destruct', '__call', '__callStatic', '__get', '__set', '__isset', '__unset', '__sleep',
    '__wakeup', '__serialize', '__unserialize', '__toString', '__invoke', '__set_state', '__clone', '__debugInfo',

    // Laravel specific
    'Illuminate', 'App', 'Http', 'Controllers', 'Requests', 'Models', 'Providers', 'Console', 'Kernel',
    'database', 'migrations', 'seeders', 'factories', 'config', 'routes', 'resources', 'views', 'lang',
    'storage', 'public', 'bootstrap', 'nova', 'livewire', 'inertia', 'blade', 'eloquent', 'assert',
    'belongsTo', 'hasMany', 'hasOne', 'morphTo', 'morphMany', 'belongsToMany', 'with', 'without',
    'firstOrCreate', 'updateOrCreate', 'findOrFail', 'where', 'orderBy', 'groupBy', 'select', 'from', 'join',
    'DB::', 'Schema::', 'Route::', 'Log::', 'Cache::', 'Mail::', 'Notification::', 'Queue::', 'Storage::',
    'Request::', 'Response::',
    'created_at', 'updated_at', 'deleted_at', 'remember_token', 'password', 'email', 'id', 'uuid',
    'string', 'integer', 'boolean', 'timestamp', 'json', 'text',

    // Validation Rules
    'required', 'nullable', 'string', 'integer', 'numeric', 'boolean', 'array', 'min', 'max', 'size',
    'in', 'not_in', 'exists', 'unique', 'email', 'url', 'ip', 'date', 'after', 'before', 'confirmed',

    // Common file/path segments
    'index', 'create', 'store', 'show', 'edit', 'update', 'destroy',
    'api', 'web', 'channels', 'console',
    'Middleware', 'Policies', 'Rules', 'Exceptions', 'Listeners', 'Events', 'Jobs',

    // HTML/CSS/JS/Fonts
    'div', 'span', 'p', 'a', 'img', 'ul', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'table', 'tr', 'td', 'th',
    'form', 'input', 'button', 'label', 'select', 'option', 'textarea',
    'class', 'id', 'style', 'href', 'src', 'alt', 'type', 'name', 'value',
    'flex', 'grid', 'block', 'inline-block', 'hidden', 'text-center', 'font-bold',
    'const', 'let', 'var', 'function', '=>',
    'apple color emoji', 'segoe ui emoji', 'segoe ui symbol', 'noto color emoji', 'times new roman',
    'courier new', 'liberation mono', 'sfmono-regular', 'instrument sans', 'ui-sans-serif', 'system-ui',
];

/**
 * Patterns that indicate a string is likely not a user-facing phrase.
 *
 * @return Collection<int, string>
 */
function getIgnoredPatterns(): Collection
{
    return collect([
        '/^[\w\-\/\_.:*]+$/',      // Matches paths, slugs, keys (e.g., 'my-key', 'path/to/file', 'users.*')
        '/^(\w+::\w+)$/',           // Matches Class::method calls
        '/^\w+_\w+$/',              // Matches snake_case variables or keys
        '/^\w+-\w+$/',              // Matches kebab-case variables or keys
        '/^(\w+)\s(\w+)$/',         // Matches two simple words, likely code not a phrase
        '/^[a-z]+$/',               // Matches single lowercase words
        '/^[A-Z_]+$/',              // Matches uppercase consts
        '/^@/',                     // Matches blade directives
        '/^trans\(|^__\(|@lang\(/', // Matches translation functions
        '/^\$this->/',              // Matches method calls
        '/^`/',                     // Matches shell commands
        '/^<\?php/',                // Matches php open tag
        '/^--[\w-]+/',               // Matches CSS variables
        '/^[a-zA-Z0-9\s]+,\s*[a-zA-Z0-9\s]+/', // Matches font stacks or comma-separated lists
        '/^(SUM|COUNT|AVG|MIN|MAX)\(.*\)(\sas\s\w+)?$/i', // SQL Aggregates
    ]);
}

it('does not contain hardcoded user-facing strings', function (): void {
    $finder = new Finder();

    $ignoredFiles = [
        base_path('app-modules/inventory/src/Services/TransactionErrorKeyMapper.php'),
        base_path('app-modules/shared/src/Data/MissingValue.php'),
        base_path('app-modules/shared/src/Support/DeterministicCursorPagination.php'),
    ];

    $finder->in([
        app_path(),
        base_path('app-modules'),
        resource_path('views'),
    ])
        ->exclude([
            'database',
            'config',
            'routes',
            'storage',
            'bootstrap/cache',
            'tests',
            'stubs',
            'lang',
            'Providers', // Service providers often contain non-translatable strings
            'Console',   // Console command descriptions/signatures are often not translated
            'Exceptions',
            'Validation',
            'Registries',
        ])
        ->notName('welcome.blade.php')
        ->name('*.php')
        ->files();

    $failures = [];
    $pattern = "/'([^']{4,})'|\"([^\"]{4,})\"/"; // Find strings with 4+ chars

    foreach ($finder as $file) {
        if (in_array($file->getRealPath(), $ignoredFiles, true)) {
            continue;
        }

        $content = $file->getContents();

        if (!preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            continue;
        }

        $allMatches = array_merge($matches[1], $matches[2]);

        foreach ($allMatches as $match) {
            if (empty($match[0])) {
                continue;
            }

            $string = $match[0];
            $offset = $match[1];
            $lineNumber = substr_count(mb_substr($content, 0, $offset), '
') + 1;

            // Rule 1: Must contain a space, indicating a phrase.
            if (!str_contains($string, ' ')) {
                continue;
            }

            // Rule 2: Must not be in the ignored words list.
            $lowerString = strtolower($string);
            if (in_array($lowerString, IGNORED_WORDS, true)) {
                continue;
            }

            // Rule 3: Must not match any of the ignored patterns.
            $isIgnoredPattern = getIgnoredPatterns()->first(fn ($p): bool => (bool) preg_match($p, $string));
            if ($isIgnoredPattern) {
                continue;
            }

            // Rule 4: Check if it's just a concatenation of variables.
            if (preg_match('/^[\\w\\s\\$\\._\'"]+$/', $string) && substr_count($string, '$') > 1) {
                continue;
            }

            // Rule 5: Should start with an uppercase letter if it's a real sentence.
            if (!preg_match('/^[A-Z]/', $string)) {
                continue;
            }

            $failures[] = "File: {$file->getRelativePathname()}:{$lineNumber}\nString: \"{$string}\"";
        }
    }

    if ($failures !== []) {
        $this->fail('Found hardcoded strings that may need translation:

'.implode('

', array_unique($failures)));
    }

    expect(true)->toBeTrue();
});
