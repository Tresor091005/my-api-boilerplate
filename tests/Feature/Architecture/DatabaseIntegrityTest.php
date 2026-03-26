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

        // Fetch actual foreign keys from Postgres
        $foreignKeys = DB::select("
            SELECT kcu.column_name
            FROM information_schema.table_constraints AS tc
            JOIN information_schema.key_column_usage AS kcu
              ON tc.constraint_name = kcu.constraint_name
              AND tc.table_schema = kcu.table_schema
            WHERE tc.constraint_type = 'FOREIGN KEY'
              AND tc.table_name = ?
        ", [$tableName]);

        $fkColumns = collect($foreignKeys)->pluck('column_name')->unique()->toArray();
        $indexes = Schema::getIndexes($tableName);

        // Map columns that are at the first position of an index
        $firstIndexedColumns = collect($indexes)->map(fn ($index) => $index['columns'][0] ?? null)->filter()->unique()->toArray();

        foreach ($fkColumns as $column) {
            $isIndexed = in_array($column, $firstIndexedColumns, true);

            // Special case: check if it's part of a composite index where it's not first,
            // but we usually want it first for performance on joins.
            if (!$isIndexed) {
                $failures[] = "Table [{$tableName}]: Foreign key column [{$column}] is not indexed in first position.";
            }
        }
    }

    if ($failures !== []) {
        $this->fail("Missing Database Indexes for Foreign Keys:\n\n".implode("\n", $failures));
    }

    expect(true)->toBeTrue();
});

it('ensures indexes on soft-deletable tables have whereNull deleted_at', function (): void {
    $tables = Schema::getTables();
    $ignoredTables = config('model-integrity.ignored_tables', []);
    $ignoredColumnsMap = config('model-integrity.ignored_soft_delete_partial_index', []);

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
            // Skip primary keys
            if ($index['primary']) {
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
                $failures[] = "Table [{$tableName}]: Index [{$indexName}] on columns [".implode(', ', $index['columns'])."] is missing 'WHERE deleted_at IS NULL'.";
            }
        }
    }

    if ($failures !== []) {
        $this->fail("Soft Delete Index Failures (Indexes on SoftDelete tables should be partial):\n\n".implode("\n", $failures));
    }

    expect(true)->toBeTrue();
});

it('ensures all primary keys are named id and use UUIDs', function (): void {
    $tables = Schema::getTables();
    $ignoredTables = config('model-integrity.ignored_tables', []);

    $failures = [];

    foreach ($tables as $table) {
        $tableName = $table['name'];
        if (in_array($tableName, $ignoredTables, true)) {
            continue;
        }

        $indexes = Schema::getIndexes($tableName);
        $primaryKey = collect($indexes)->firstWhere('primary', true);

        if (!$primaryKey) {
            $failures[] = "Table [{$tableName}]: Missing primary key.";
            continue;
        }

        if (count($primaryKey['columns']) > 1) {
            $failures[] = "Table [{$tableName}]: Composite primary key detected [".implode(', ', $primaryKey['columns'])."]. Expected a single 'id' column.";
            continue;
        }

        $pkColumn = $primaryKey['columns'][0];
        if ($pkColumn !== 'id') {
            $failures[] = "Table [{$tableName}]: Primary key is named [{$pkColumn}]. Expected 'id'.";
        }

        // Check type via Postgres information_schema
        $columnInfo = DB::selectOne('
            SELECT data_type, udt_name
            FROM information_schema.columns
            WHERE table_name = ? AND column_name = ?
        ', [$tableName, $pkColumn]);

        if ($columnInfo && $columnInfo->udt_name !== 'uuid') {
            $failures[] = "Table [{$tableName}]: Primary key [{$pkColumn}] is of type [{$columnInfo->udt_name}]. Expected 'uuid'.";
        }
    }

    if ($failures !== []) {
        $this->fail("Primary Key Integrity Failures:\n\n".implode("\n", $failures));
    }

    expect(true)->toBeTrue();
});

it('ensures all JSON columns use jsonb for better performance', function (): void {
    $tables = Schema::getTables();
    $ignoredTables = config('model-integrity.ignored_tables', []);

    $failures = [];

    foreach ($tables as $table) {
        $tableName = $table['name'];
        if (in_array($tableName, $ignoredTables, true)) {
            continue;
        }

        $jsonColumns = DB::select("
            SELECT column_name, data_type
            FROM information_schema.columns
            WHERE table_name = ? AND data_type = 'json'
        ", [$tableName]);

        foreach ($jsonColumns as $column) {
            $failures[] = "Table [{$tableName}]: Column [{$column->column_name}] uses 'json' type. Use 'jsonb' instead for indexing and performance.";
        }
    }

    if ($failures !== []) {
        $this->fail("JSON Type Integrity Failures:\n\n".implode("\n", $failures));
    }

    expect(true)->toBeTrue();
});

it('ensures boolean columns follow naming conventions (is_, has_, can_, should_)', function (): void {
    $tables = Schema::getTables();
    $ignoredTables = config('model-integrity.ignored_tables', []);
    $allowedPrefixes = ['is_', 'has_', 'can_', 'should_'];

    $failures = [];

    foreach ($tables as $table) {
        $tableName = $table['name'];
        if (in_array($tableName, $ignoredTables, true)) {
            continue;
        }

        $boolColumns = DB::select("
            SELECT column_name
            FROM information_schema.columns
            WHERE table_name = ? AND data_type = 'boolean'
        ", [$tableName]);

        foreach ($boolColumns as $column) {
            $name = $column->column_name;
            $hasValidPrefix = false;
            foreach ($allowedPrefixes as $prefix) {
                if (str_starts_with($name, $prefix)) {
                    $hasValidPrefix = true;
                    break;
                }
            }

            if (!$hasValidPrefix) {
                $failures[] = "Table [{$tableName}]: Boolean column [{$name}] does not follow naming convention. Expected prefix: ".implode(', ', $allowedPrefixes);
            }
        }
    }

    if ($failures !== []) {
        $this->fail("Boolean Naming Convention Failures:\n\n".implode("\n", $failures));
    }

    expect(true)->toBeTrue();
});

test('todo: ensure non-negativity constraints on critical columns (stock, prices)');
test('todo: ensure polymorphic type columns are indexed');
test('todo: ensure timestamps are present on all business tables');
