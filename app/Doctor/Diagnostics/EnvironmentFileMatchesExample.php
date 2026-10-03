<?php

declare(strict_types=1);

namespace App\Doctor\Diagnostics;

use App\Support\EnvironmentFileInspector;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Message;

class EnvironmentFileMatchesExample extends Diagnostic
{
    public string $name = 'Environment matches .env.example';

    public string $group = 'configuration';

    /**
     * Get the diagnostic's named message definitions.
     *
     * @return array<string, string|Message>
     */
    protected function messages(): array
    {
        return [
            'missing-files' => Message::make(
                summary: 'The environment example file is missing.',
                remediation: 'Restore .env.example before checking environment consistency.',
            ),
            'mismatch' => Message::make(
                summary: 'The environment does not match .env.example.',
                remediation: 'Run `php artisan env:check` to inspect missing, extra, or empty variables.',
            ),
            'matches' => 'The environment matches .env.example.',
        ];
    }

    public function check(): DiagnosticResult
    {
        $examplePath = base_path('.env.example');

        if (!is_file($examplePath)) {
            return $this->fail('missing-files');
        }

        $environmentPath = is_file(base_path('.env')) ? base_path('.env') : null;
        $comparison = app(EnvironmentFileInspector::class)->compare($examplePath, $environmentPath);

        if ($comparison['missing'] !== [] || $comparison['extra'] !== [] || $comparison['empty'] !== []) {
            return $this->fail('mismatch')->withDetails($this->formatDetails($comparison));
        }

        return $this->pass('matches');
    }

    /**
     * @param  array{missing: list<string>, extra: list<string>, empty: list<string>}  $comparison
     */
    private function formatDetails(array $comparison): string
    {
        return collect($comparison)
            ->filter()
            ->map(fn (array $keys, string $type): string => ucfirst($type).': '.implode(', ', $keys))
            ->implode(PHP_EOL);
    }
}
