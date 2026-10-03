<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\EnvironmentFileInspector;
use Illuminate\Console\Command;

class EnvironmentCheck extends Command
{
    protected $signature = 'env:check
        {--runtime : Compare the running process environment instead of .env}';

    protected $description = 'Compare the environment with .env.example';

    public function handle(EnvironmentFileInspector $inspector): int
    {
        $examplePath = base_path('.env.example');
        $environmentPath = $this->option('runtime') ? null : base_path('.env');

        if (!is_file($examplePath)) {
            $this->error('.env.example does not exist.');

            return self::FAILURE;
        }

        if ($environmentPath !== null && !is_file($environmentPath)) {
            $this->error('.env does not exist. Use --runtime for injected container variables.');

            return self::FAILURE;
        }

        $comparison = $inspector->compare($examplePath, $environmentPath);
        $source = $environmentPath === null ? 'runtime environment' : '.env';

        $this->line("Comparing {$source} with .env.example");
        $this->renderKeys('Missing', $comparison['missing']);
        $this->renderKeys('Extra', $comparison['extra']);
        $this->renderKeys('Empty', $comparison['empty']);

        return $comparison['missing'] === []
            && $comparison['extra'] === []
            && $comparison['empty'] === []
            ? self::SUCCESS
            : self::FAILURE;
    }

    /**
     * @param  list<string>  $keys
     */
    private function renderKeys(string $label, array $keys): void
    {
        if ($keys === []) {
            $this->info("{$label}: none");

            return;
        }

        $this->warn("{$label}: ".implode(', ', $keys));
    }
}
