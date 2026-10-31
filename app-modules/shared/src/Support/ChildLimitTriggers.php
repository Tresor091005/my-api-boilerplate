<?php

declare(strict_types=1);

namespace Lahatre\Shared\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;

/**
 * Keeps the database triggers in line with config/child-limits.php: one insert
 * and one update trigger per foreign key with a maximum.
 */
final class ChildLimitTriggers
{
    private const string PREFIX = 'child_limit_';

    /**
     * Creates the missing triggers and drops the ones no longer declared, in one transaction.
     *
     * @throws LogicException when a declaration cannot be enforced
     */
    public function sync(): void
    {
        DB::transaction(function (): void {
            TransactionLock::acquire('child-limits:sync');
            ['missing' => $missing, 'obsolete' => $obsolete] = $this->differences();

            foreach ($obsolete as $trigger) {
                DB::statement(sprintf('DROP TRIGGER IF EXISTS %s ON %s', $trigger['name'], $trigger['table']));
            }

            foreach ($missing as $trigger) {
                DB::statement($trigger['definition']);
            }
        });
    }

    /** Removes every child limit trigger, so migrations can drop, retype or rename the columns they depend on. */
    public function dropAll(): void
    {
        DB::transaction(function (): void {
            TransactionLock::acquire('child-limits:sync');

            foreach ($this->existing() as $trigger) {
                DB::statement(sprintf('DROP TRIGGER IF EXISTS %s ON %s', $trigger['name'], $trigger['table']));
            }
        });
    }

    /**
     * @throws LogicException when a declaration cannot be enforced
     *
     * @return array{
     *     missing: list<array{table: string, name: string, definition: string}>,
     *     obsolete: list<array{table: string, name: string}>,
     * }
     */
    public function differences(): array
    {
        $declared = $this->declared();
        $existing = $this->existing();

        return [
            'missing'  => array_values(array_diff_key($declared, $existing)),
            'obsolete' => array_values(array_diff_key($existing, $declared)),
        ];
    }

    /** @return array<string, array{table: string, name: string, definition: string}> */
    private function declared(): array
    {
        $triggers = [];

        /** @var array<string, array<string, mixed>> $limits */
        $limits = config('child-limits', []);

        foreach ($limits as $table => $foreignKeys) {
            foreach ($foreignKeys as $foreignKey => $limit) {
                if ($limit === null) {
                    continue;
                }

                ['max' => $max, 'where' => $where] = $this->readLimit($table, $foreignKey, $limit);
                $softDeletes = Schema::hasColumn($table, 'deleted_at');
                $filter = implode(' AND ', array_filter([
                    "\"{$foreignKey}\" IS NOT NULL",
                    $softDeletes ? 'deleted_at IS NULL' : null,
                    $where === null ? null : "({$where})",
                ]));
                $types = $this->columnTypes($table);
                $scope = $this->indexedScope($table, $foreignKey, $softDeletes, $where);

                $tuple = 'ARRAY['.implode(', ', array_map(fn (string $column): string => "\"{$column}\"::text", $scope)).']';
                $matches = array_map(
                    fn (string $column, int $position): string => sprintf('"%s" = $1[%d]::%s', $column, $position + 1, $types[$column]),
                    $scope,
                    array_keys($scope),
                );
                $probe = sprintf('SELECT EXISTS (SELECT 1 FROM %s WHERE %s AND %s OFFSET %d LIMIT 1)', $table, implode(' AND ', $matches), $filter, $max);
                $arguments = implode(', ', array_map(
                    fn (string $argument): string => "'".str_replace("'", "''", $argument)."'",
                    ["{$table}.{$foreignKey}", (string) $max, $tuple, $filter, $probe],
                ));
                $name = sprintf('%s%s_%d_%s', self::PREFIX, $foreignKey, $max, substr(md5($arguments), 0, 8));

                // Without a condition, only a change of parent or a restore can add a child.
                $when = $where === null
                    ? sprintf(
                        ' WHEN (ROW(%s) IS DISTINCT FROM ROW(%s)%s)',
                        implode(', ', array_map(fn (string $column): string => "NEW.\"{$column}\"", $scope)),
                        implode(', ', array_map(fn (string $column): string => "OLD.\"{$column}\"", $scope)),
                        $softDeletes ? ' OR (OLD.deleted_at IS NOT NULL AND NEW.deleted_at IS NULL)' : '',
                    )
                    : '';

                $triggers["{$table}.{$name}_insert"] = [
                    'table'      => $table,
                    'name'       => "{$name}_insert",
                    'definition' => "CREATE TRIGGER {$name}_insert AFTER INSERT ON {$table} REFERENCING NEW TABLE AS inserted_rows "
                        ."FOR EACH STATEMENT EXECUTE FUNCTION shared_child_limit_after_insert({$arguments})",
                ];
                $triggers["{$table}.{$name}_update"] = [
                    'table'      => $table,
                    'name'       => "{$name}_update",
                    'definition' => "CREATE TRIGGER {$name}_update AFTER UPDATE ON {$table} FOR EACH ROW{$when} "
                        ."EXECUTE FUNCTION shared_child_limit_after_update({$arguments})",
                ];
            }
        }

        return $triggers;
    }

    /** @return array<string, array{table: string, name: string}> */
    private function existing(): array
    {
        $rows = DB::select(<<<'SQL'
            SELECT c.relname AS table_name, t.tgname AS name
            FROM pg_trigger t
            JOIN pg_class c ON c.oid = t.tgrelid
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE NOT t.tgisinternal AND n.nspname = current_schema() AND starts_with(t.tgname, ?)
            SQL, [self::PREFIX]);

        $triggers = [];

        foreach ($rows as $row) {
            $triggers["{$row->table_name}.{$row->name}"] = ['table' => $row->table_name, 'name' => $row->name];
        }

        return $triggers;
    }

    /**
     * A limit is a maximum, or a maximum with a condition the counted children meet.
     *
     * @throws LogicException when the column, the maximum or the condition is not usable
     *
     * @return array{max: int, where: string|null}
     */
    private function readLimit(string $table, string $foreignKey, mixed $limit): array
    {
        $where = "child-limits.{$table}.{$foreignKey}";
        $max = is_array($limit) ? ($limit['max'] ?? null) : $limit;
        $condition = is_array($limit) ? ($limit['where'] ?? null) : null;

        if (preg_match('/^[a-z_][a-z0-9_]*$/', $table.$foreignKey) !== 1 || !Schema::hasColumn($table, $foreignKey)) {
            throw new LogicException("{$where}: no such column.");
        }

        if (!is_int($max) || $max < 1) {
            throw new LogicException("{$where}: the maximum must be an integer of at least 1, or null for no limit.");
        }

        if ($condition !== null && !$this->isValidCondition($table, $condition)) {
            throw new LogicException("{$where}: the condition is not valid SQL on {$table}.");
        }

        return ['max' => $max, 'where' => $condition];
    }

    private function isValidCondition(string $table, mixed $condition): bool
    {
        if (!is_string($condition) || trim($condition) === '') {
            return false;
        }

        try {
            DB::transaction(fn (): array => DB::select("SELECT 1 FROM {$table} WHERE ({$condition}) LIMIT 0"));
        } catch (QueryException) {
            return false;
        }

        return true;
    }

    /** @return array<string, string> */
    private function columnTypes(string $table): array
    {
        $rows = DB::select(<<<'SQL'
            SELECT attname AS name, format_type(atttypid, atttypmod) AS type
            FROM pg_attribute
            WHERE attrelid = ?::regclass AND attnum > 0 AND NOT attisdropped
            SQL, [$table]);

        return array_column($rows, 'type', 'name');
    }

    /**
     * The columns an existing index lets the check filter on: the organization or
     * the parent type when the index starts with them, then the key. Children of
     * one parent share these values, so the count stays the same.
     *
     * @throws LogicException when no index can serve the check
     *
     * @return list<string>
     */
    private function indexedScope(string $table, string $foreignKey, bool $softDeletes, ?string $condition): array
    {
        $indexes = DB::select(<<<'SQL'
            SELECT array_to_json(array_agg(a.attname ORDER BY k.ord)) AS columns, pg_get_expr(i.indpred, i.indrelid) AS predicate
            FROM pg_index i
            CROSS JOIN LATERAL unnest(i.indkey[0:i.indnkeyatts - 1]) WITH ORDINALITY AS k(attnum, ord)
            LEFT JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = k.attnum
            WHERE i.indrelid = ?::regclass
            GROUP BY i.indexrelid
            SQL, [$table]);

        $filters = array_filter([$softDeletes ? 'deleted_at IS NULL' : null, $condition]);
        // Only columns every child of one parent has in common may come first.
        $sharedColumns = ['organization_id', preg_replace('/_id$/', '_type', $foreignKey)];
        $candidates = [];

        foreach ($indexes as $index) {
            /** @var list<string|null> $columns */
            $columns = json_decode($index->columns, true);
            $position = array_search($foreignKey, $columns, true);

            if ($position === false || !$this->coversPredicate($index->predicate, $filters)) {
                continue;
            }

            $scope = array_slice($columns, 0, $position + 1);

            if (array_diff(array_slice($scope, 0, -1), $sharedColumns) === []) {
                $candidates[] = $scope;
            }
        }

        usort($candidates, fn (array $left, array $right): int => count($left) <=> count($right));

        return $candidates[0] ?? throw new LogicException("child-limits.{$table}.{$foreignKey}: no index can serve the check, or each check would scan the table.");
    }

    /**
     * A partial index serves the check only when each part of its condition is
     * one the check applies too.
     *
     * @param  list<string>  $filters
     */
    private function coversPredicate(?string $predicate, array $filters): bool
    {
        if ($predicate === null) {
            return true;
        }

        $known = array_map($this->normalize(...), $filters);

        foreach ($this->conjuncts($predicate) as $part) {
            if (!in_array($this->normalize($part), $known, true)) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> */
    private function conjuncts(string $expression): array
    {
        $expression = $this->stripParentheses($expression);
        $parts = [];
        $depth = 0;
        $start = 0;

        for ($i = 0, $length = strlen($expression); $i < $length; $i++) {
            $depth += match ($expression[$i]) {
                '('     => 1,
                ')'     => -1,
                default => 0,
            };

            if ($depth === 0 && substr_compare($expression, ' AND ', $i, 5, true) === 0) {
                $parts[] = substr($expression, $start, $i - $start);
                $start = $i + 5;
            }
        }

        $parts[] = substr($expression, $start);

        return array_map($this->stripParentheses(...), $parts);
    }

    private function normalize(string $expression): string
    {
        return strtolower((string) preg_replace('/\s+/', ' ', $this->stripParentheses($expression)));
    }

    private function stripParentheses(string $expression): string
    {
        $expression = trim($expression);

        while (str_starts_with($expression, '(') && str_ends_with($expression, ')') && $this->wrapsWhole($expression)) {
            $expression = trim(substr($expression, 1, -1));
        }

        return $expression;
    }

    private function wrapsWhole(string $expression): bool
    {
        $depth = 0;

        for ($i = 0, $last = strlen($expression) - 1; $i <= $last; $i++) {
            $depth += match ($expression[$i]) {
                '('     => 1,
                ')'     => -1,
                default => 0,
            };

            if ($depth === 0 && $i < $last) {
                return false;
            }
        }

        return true;
    }
}
