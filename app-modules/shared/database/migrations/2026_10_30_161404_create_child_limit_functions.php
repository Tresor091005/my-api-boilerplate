<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Trigger functions behind config/child-limits.php. `child-limits:sync` attaches
 * them to the declared tables after every `migrate`, with the SQL they run.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION shared_assert_child_room(
                label text,
                max_children integer,
                probe text,
                scope text[]
            ) RETURNS void
            LANGUAGE plpgsql
            SET lock_timeout = '5s'
            AS $$
            DECLARE
                is_full boolean;
            BEGIN
                PERFORM pg_advisory_xact_lock(hashtextextended(format('child-limit:%s:%s', label, scope[array_upper(scope, 1)]), 0));

                EXECUTE probe INTO is_full USING scope;

                IF is_full THEN
                    RAISE EXCEPTION USING
                        ERRCODE = 'check_violation',
                        MESSAGE = format('child_limit:%s:%s', label, max_children);
                END IF;
            END;
            $$;

            CREATE OR REPLACE FUNCTION shared_child_limit_after_insert() RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            DECLARE
                scope text[];
            BEGIN
                FOR scope IN EXECUTE format('SELECT DISTINCT %s FROM inserted_rows WHERE %s ORDER BY 1', TG_ARGV[2], TG_ARGV[3]) LOOP
                    PERFORM shared_assert_child_room(TG_ARGV[0], TG_ARGV[1]::integer, TG_ARGV[4], scope);
                END LOOP;

                RETURN NULL;
            END;
            $$;

            CREATE OR REPLACE FUNCTION shared_child_limit_after_update() RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            DECLARE
                new_scope text[];
                old_scope text[];
            BEGIN
                EXECUTE format('SELECT %s FROM (SELECT ($1).*) AS changed_row WHERE %s', TG_ARGV[2], TG_ARGV[3]) INTO new_scope USING NEW;

                IF new_scope IS NULL THEN
                    RETURN NULL;
                END IF;

                EXECUTE format('SELECT %s FROM (SELECT ($1).*) AS changed_row WHERE %s', TG_ARGV[2], TG_ARGV[3]) INTO old_scope USING OLD;

                IF new_scope IS DISTINCT FROM old_scope THEN
                    PERFORM shared_assert_child_room(TG_ARGV[0], TG_ARGV[1]::integer, TG_ARGV[4], new_scope);
                END IF;

                RETURN NULL;
            END;
            $$;
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP FUNCTION IF EXISTS shared_child_limit_after_update() CASCADE;
            DROP FUNCTION IF EXISTS shared_child_limit_after_insert() CASCADE;
            DROP FUNCTION IF EXISTS shared_assert_child_room(text, integer, text, text[]);
            SQL);
    }
};
