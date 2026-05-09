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
            $columns = DB::table('pg_index as i')
                ->join('pg_class as t', 't.oid', '=', 'i.indrelid')
                ->join('pg_class as idx', 'idx.oid', '=', 'i.indexrelid')
                ->join(DB::raw('LATERAL unnest(i.indkey) WITH ORDINALITY AS k(attnum, ord)'), DB::raw('true'), '=', DB::raw('true'))
                ->join('pg_attribute as a', function ($join): void {
                    $join->on('a.attrelid', '=', 't.oid')
                        ->on('a.attnum', '=', 'k.attnum');
                })
                ->where('t.relname', $tableName)
                ->where('idx.relname', $index['name'])
                ->orderBy('k.ord')
                ->pluck('a.attname')
                ->toArray();

            if ($columns === []) {
                $columns = $index['columns'];
            }

            // Detect strict morph pair: same prefix + *_type and *_id.
            $isMorph = false;
            if (count($columns) === 2) {
                [$first, $second] = $columns;

                $firstIsType = Str::endsWith($first, '_type');
                $secondIsType = Str::endsWith($second, '_type');
                $firstIsId = Str::endsWith($first, '_id');
                $secondIsId = Str::endsWith($second, '_id');

                if (($firstIsType && $secondIsId) || ($firstIsId && $secondIsType)) {
                    $firstPrefix = preg_replace('/_(id|type)$/', '', (string) $first);
                    $secondPrefix = preg_replace('/_(id|type)$/', '', (string) $second);
                    $isMorph = $firstPrefix === $secondPrefix;
                }
            }

            // Expected name: {table}_{col1}_{col2}_{type}
            $expectedName = strtolower($tableName.'_'.implode('_', $columns).'_'.$type);

            // Handle names longer than 60 characters or specific custom naming
            $customIndexNames = config("model-integrity.custom_index_names.{$tableName}", []);
            $isCustom = false;
            foreach ($customIndexNames as $expected => $actual) {
                if (is_array($actual)) {
                    if (in_array($index['name'], $actual, true)) {
                        $isCustom = true;
                        break;
                    }
                } elseif ($index['name'] === $actual) {
                    $isCustom = true;
                    break;
                }
            }

            if (strlen($expectedName) > 60 && !$isCustom) {
                $customName = $customIndexNames[$expectedName] ?? null;

                if (is_array($customName)) {
                    // If the index name is one of the allowed custom names for this expected pattern
                    if (in_array($index['name'], $customName, true)) {
                        $expectedName = $index['name'];
                    } else {
                        // Just pick the first one for the error message if none match
                        $expectedName = $customName[0];
                    }
                } elseif ($customName) {
                    $expectedName = $customName;
                } else {
                    $failures[] = "Table [{$tableName}]: Generated index name [{$expectedName}] is too long (".strlen($expectedName)." chars). \n      Please add a shorter alias in 'config/model-integrity.php' under 'custom_index_names.{$tableName}.{$expectedName}'.";

                    continue;
                }
            }

            // If it's explicitly allowed in custom names, it passes naming convention
            if ($isCustom) {
                continue;
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

            // The single-column deleted_at index is intentionally global.
            if ($index['columns'] === ['deleted_at']) {
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

            if ($definition && !Str::contains($definition->indexdef, 'deleted_at IS NULL')) {
                $failures[] = "Table [{$tableName}]: Index [{$indexName}] on columns [".implode(', ', $index['columns'])."] is missing 'WHERE deleted_at IS NULL'.";
            }
        }
    }

    if ($failures !== []) {
        $this->fail("Soft Delete Index Failures (Indexes on SoftDelete tables should be partial):\n\n".implode("\n", $failures));
    }

    expect(true)->toBeTrue();
});

it('ensures soft-deletable tables are indexed for deleted_at filtering', function (): void {
    $tables = Schema::getTables();
    $ignoredTables = config('model-integrity.ignored_tables', []);

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
        $hasDeletedAtColumnIndex = collect($indexes)->contains(
            fn (array $index): bool => in_array('deleted_at', $index['columns'], true)
        );

        $hasPartialDeletedAtPredicate = collect($indexes)->contains(function (array $index) use ($tableName): bool {
            if ($index['primary']) {
                return false;
            }

            $definition = DB::selectOne('SELECT indexdef FROM pg_indexes WHERE tablename = ? AND indexname = ?', [$tableName, $index['name']]);

            return $definition !== null && Str::contains($definition->indexdef, 'deleted_at IS NULL');
        });

        if (!$hasDeletedAtColumnIndex && !$hasPartialDeletedAtPredicate) {
            $failures[] = "Table [{$tableName}] has soft deletes but neither a deleted_at index nor a partial index with 'WHERE deleted_at IS NULL'.";
        }
    }

    if ($failures !== []) {
        $this->fail("Missing deleted_at filtering indexes on soft-deletable tables:\n\n".implode("\n", $failures));
    }

    expect(true)->toBeTrue();
});

it('ensures all primary keys are named id and use UUIDs', function (): void {
    $tables = Schema::getTables();
    $ignoredTables = array_merge(
        config('model-integrity.ignored_tables', []),
        config('model-integrity.composite_pkey', []),
    );

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
                if (str_starts_with((string) $name, $prefix)) {
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

it('ensures all business tables have a multi-tenancy column (organization_id or tenant_id)', function (): void {
    $tables = Schema::getTables();
    $ignoredTables = array_merge(
        config('model-integrity.ignored_tables', []),
        config('model-integrity.tenancy_ignored_tables', []),
    );

    $failures = [];

    foreach ($tables as $table) {
        $tableName = $table['name'];
        if (in_array($tableName, $ignoredTables, true)) {
            continue;
        }

        $hasOrgId = Schema::hasColumn($tableName, 'organization_id');
        $hasTenantId = Schema::hasColumn($tableName, 'tenant_id');

        if (!$hasOrgId && !$hasTenantId) {
            $failures[] = "Table [{$tableName}] is missing a multi-tenancy column ('organization_id' or 'tenant_id').";
        }
    }

    if ($failures !== []) {
        $this->fail("Multi-tenancy Integrity Failures:\n\n".implode("\n", $failures));
    }

    expect(true)->toBeTrue();
});

it('ensures all unique indexes in business tables include multi-tenancy columns', function (): void {
    $tables = Schema::getTables();
    $ignoredTables = config('model-integrity.ignored_tables', []);
    $exemptions = config('model-integrity.exempt_global_uniqueness', []);

    $failures = [];

    foreach ($tables as $table) {
        $tableName = $table['name'];
        if (in_array($tableName, $ignoredTables, true)) {
            continue;
        }

        $tenancyColumn = null;
        if (Schema::hasColumn($tableName, 'organization_id')) {
            $tenancyColumn = 'organization_id';
        } elseif (Schema::hasColumn($tableName, 'tenant_id')) {
            $tenancyColumn = 'tenant_id';
        }

        if (!$tenancyColumn) {
            continue;
        }

        $indexes = Schema::getIndexes($tableName);
        $tableExemptions = $exemptions[$tableName] ?? [];

        foreach ($indexes as $index) {
            // Skip primary keys
            if ($index['primary']) {
                continue;
            }

            // Only check unique indexes
            if (!$index['unique']) {
                continue;
            }

            // Skip if explicitly exempt
            if (in_array($index['name'], $tableExemptions, true)) {
                continue;
            }

            // Check if tenancy column is in the columns
            if (!in_array($tenancyColumn, $index['columns'], true)) {
                $failures[] = "Table [{$tableName}]: Unique index [{$index['name']}] on columns [".implode(', ', $index['columns'])."] is missing '{$tenancyColumn}'.";
            }
        }
    }

    if ($failures !== []) {
        $this->fail("Unique Index Multi-tenancy Failures:\n\n".implode("\n", $failures));
    }

    expect(true)->toBeTrue();
});

todo('ensure non-negativity constraints on critical columns (stock, prices)');
todo('ensure polymorphic type columns are indexed');
todo('ensure timestamps are present on all business tables');
