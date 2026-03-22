<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('enforces naming conventions for database indexes', function (): void {
    $tables = Schema::getTables();
    $ignoredTables = config('model-integrity.ignored_tables', []);

    $failures = [];

    foreach ($tables as $table) {
        $tableName = $table['name'];
        if (in_array($tableName, $ignoredTables, true)) {
            continue;
        }

        $indexes = Schema::getIndexes($tableName);

        foreach ($indexes as $index) {
            // 1. Handle Primary Keys (Postgres Standard)
            if ($index['primary']) {
                $expectedPrimaryName = $tableName.'_pkey';
                if ($index['name'] !== $expectedPrimaryName) {
                    $failures[] = "Table [{$tableName}]: Primary Key [{$index['name']}] does not follow Postgres convention. \n      Expected: '{$expectedPrimaryName}'";
                }
                continue;
            }

            // 2. Handle Unique and Regular Indexes
            $type = $index['unique'] ? 'unique' : 'index';
            $columns = $index['columns'];

            // Detect if it's a morph pair (e.g., taggable_id + taggable_type)
            $isMorph = count($columns) === 2
                && Str::endsWith($columns[0], ['_id', '_type'])
                && Str::endsWith($columns[1], ['_id', '_type']);

            // Alphabetical sort of columns for the name (Predictable)
            sort($columns);

            // Expected name: {table}_{col1}_{col2}_{type}
            $expectedName = strtolower($tableName.'_'.implode('_', $columns).'_'.$type);

            // Handle names longer than 60 characters
            if (strlen($expectedName) > 60) {
                $customName = config("model-integrity.custom_index_names.{$tableName}.{$expectedName}");

                if ($customName) {
                    $expectedName = $customName;
                } else {
                    $failures[] = "Table [{$tableName}]: Generated index name [{$expectedName}] is too long (".strlen($expectedName)." chars). \n      Please add a shorter alias in 'config/model-integrity.php' under 'custom_index_names.{$tableName}.{$expectedName}'.";

                    continue;
                }
            }

            if ($index['name'] !== $expectedName) {
                $msg = "Table [{$tableName}]: Index [{$index['name']}] does not follow convention.";
                if ($isMorph) {
                    $msg .= ' (Polymorphic pair detected)';
                }
                $msg .= "\n      Expected: '{$expectedName}'";

                $failures[] = $msg;
            }
        }
    }

    if ($failures !== []) {
        $this->fail("Database Index Convention Failures:\n\n".implode("\n\n", $failures));
    }

    expect(true)->toBeTrue();
});

it('ensures all foreign keys are indexed', function (): void {
    $tables = Schema::getTables();
    $ignoredTables = config('model-integrity.ignored_tables', []);

    $failures = [];

    foreach ($tables as $table) {
        $tableName = $table['name'];
        if (in_array($tableName, $ignoredTables, true)) {
            continue;
        }

        $columns = Schema::getColumnListing($tableName);
        $indexes = Schema::getIndexes($tableName);

        // Map columns that are at the first position of an index
        $firstIndexedColumns = collect($indexes)->map(fn ($index) => $index['columns'][0] ?? null)->filter()->unique()->toArray();

        foreach ($columns as $column) {
            // We look for foreign keys (ending in _id) but skip the primary key 'id'
            if (Str::endsWith($column, '_id') && $column !== 'id') {
                $isIndexed = in_array($column, $firstIndexedColumns, true);

                // If not indexed in first position, check if it's part of a polymorphic index pair
                if (!$isIndexed) {
                    $morphPrefix = Str::beforeLast($column, '_id');
                    $typeColumn = $morphPrefix.'_type';

                    if (in_array($typeColumn, $columns, true)) {
                        // It's a morph. Is there an index covering both columns?
                        foreach ($indexes as $index) {
                            if (in_array($column, $index['columns'], true) && in_array($typeColumn, $index['columns'], true)) {
                                $isIndexed = true;
                                break;
                            }
                        }
                    }
                }

                if (!$isIndexed) {
                    $failures[] = "Table [{$tableName}]: Foreign key column [{$column}] is not indexed (neither directly nor as part of a polymorphic pair).";
                }
            }
        }
    }

    if ($failures !== []) {
        $this->fail("Missing Database Indexes for Foreign Keys:\n\n".implode("\n", $failures));
    }

    expect(true)->toBeTrue();
});

it('ensures unique indexes on soft-deletable tables have whereNull deleted_at', function (): void {
    $tables = Schema::getTables();
    $ignoredTables = config('model-integrity.ignored_tables', []);
    $ignoredColumnsMap = config('model-integrity.ignored_soft_delete_uniqueness', []);

    $failures = [];

    foreach ($tables as $table) {
        $tableName = $table['name'];
        if (in_array($tableName, $ignoredTables, true)) {
            continue;
        }

        $columns = Schema::getColumnListing($tableName);
        if (!in_array('deleted_at', $columns, true)) {
            continue;
        }

        $indexes = Schema::getIndexes($tableName);
        $ignoredColumns = $ignoredColumnsMap[$tableName] ?? [];

        foreach ($indexes as $index) {
            // Only care about unique indexes that are NOT primary keys
            if (!$index['unique'] || $index['primary']) {
                continue;
            }

            // Check if ANY column in the index is explicitly ignored
            $hasIgnoredColumn = array_intersect($index['columns'], $ignoredColumns) !== [];
            if ($hasIgnoredColumn) {
                continue;
            }

            // Fetch the raw index definition from Postgres to check the WHERE clause
            $indexName = $index['name'];
            $definition = DB::selectOne('SELECT indexdef FROM pg_indexes WHERE tablename = ? AND indexname = ?', [$tableName, $indexName]);

            if ($definition && !Str::contains($definition->indexdef, 'WHERE (deleted_at IS NULL)')) {
                $failures[] = "Table [{$tableName}]: Unique index [{$indexName}] on columns [".implode(', ', $index['columns'])."] is missing 'WHERE deleted_at IS NULL'.";
            }
        }
    }

    if ($failures !== []) {
        $this->fail("Soft Delete Uniqueness Failures (Unique indexes on SoftDelete tables should be partial):\n\n".implode("\n", $failures));
    }

    expect(true)->toBeTrue();
});
