<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class EnvironmentFormat extends Command
{
    protected $signature = 'env:format
        {--dry-run : Display the result without writing .env}';

    protected $description = 'Reorder .env to match .env.example';

    public function handle(Filesystem $filesystem): int
    {
        $environmentPath = base_path('.env');
        $examplePath = base_path('.env.example');

        if (!$filesystem->isFile($environmentPath) || !$filesystem->isFile($examplePath)) {
            $this->error('Both .env and .env.example must exist.');

            return self::FAILURE;
        }

        $current = $filesystem->lines($environmentPath)->all();
        $example = $filesystem->lines($examplePath)->all();
        $values = $this->values($current);
        $used = [];
        $formatted = [];

        foreach ($example as $line) {
            $key = $this->key($line);

            if ($key === null) {
                $formatted[] = $line;

                continue;
            }

            $formatted[] = array_key_exists($key, $values)
                ? $this->replaceValue($line, $values[$key])
                : $line;
            $used[$key] = true;
        }

        $extras = array_diff_key($values, $used);

        if ($extras !== []) {
            $formatted[] = '';
            $formatted[] = '# Variables not declared in .env.example';

            foreach ($extras as $key => $value) {
                $formatted[] = "{$key}={$value}";
            }
        }

        $content = implode(PHP_EOL, $formatted).PHP_EOL;

        if ($this->option('dry-run')) {
            $this->output->write($content);

            return self::SUCCESS;
        }

        if ($content === $filesystem->get($environmentPath)) {
            $this->info('.env is already ordered. No changes made.');

            return self::SUCCESS;
        }

        $backupPath = $environmentPath.'.backup-'.now()->format('YmdHis');
        $filesystem->copy($environmentPath, $backupPath);
        $filesystem->put($environmentPath, $content);

        $this->info(".env reordered. Backup created at {$backupPath}.");

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $lines
     * @return array<string, string>
     */
    private function values(array $lines): array
    {
        $values = [];

        foreach ($lines as $line) {
            $key = $this->key($line);

            if ($key !== null && preg_match('/^\s*(?:export\s+)?[A-Za-z_][A-Za-z0-9_]*\s*(?:=|$)(.*)$/', $line, $matches)) {
                $values[$key] = trim($matches[1]);
            }
        }

        return $values;
    }

    private function key(string $line): ?string
    {
        return preg_match('/^\s*(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*(?:=|$)/', $line, $matches)
            ? $matches[1]
            : null;
    }

    private function replaceValue(string $line, string $value): string
    {
        return preg_replace_callback(
            '/^(\s*(?:export\s+)?[A-Za-z_][A-Za-z0-9_]*\s*=).*/',
            static fn (array $matches): string => $matches[1].$value,
            $line,
        ) ?? $line;
    }
}
