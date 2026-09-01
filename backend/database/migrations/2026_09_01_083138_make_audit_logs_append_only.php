<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        match (Schema::getConnection()->getDriverName()) {
            'pgsql' => $this->createPostgresTriggers(),
            'sqlite' => $this->createSqliteTriggers(),
            default => null,
        };
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        match (Schema::getConnection()->getDriverName()) {
            'pgsql' => $this->dropPostgresTriggers(),
            'sqlite' => $this->dropSqliteTriggers(),
            default => null,
        };
    }

    private function createPostgresTriggers(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION reject_audit_log_mutation() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'audit_logs is append-only';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER audit_logs_reject_update
            BEFORE UPDATE ON audit_logs
            FOR EACH ROW EXECUTE FUNCTION reject_audit_log_mutation();

            CREATE TRIGGER audit_logs_reject_delete
            BEFORE DELETE ON audit_logs
            FOR EACH ROW EXECUTE FUNCTION reject_audit_log_mutation();
            SQL);
    }

    private function dropPostgresTriggers(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS audit_logs_reject_update ON audit_logs;
            DROP TRIGGER IF EXISTS audit_logs_reject_delete ON audit_logs;
            DROP FUNCTION IF EXISTS reject_audit_log_mutation();
            SQL);
    }

    private function createSqliteTriggers(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER audit_logs_reject_update
            BEFORE UPDATE ON audit_logs
            BEGIN
                SELECT RAISE(ABORT, 'audit_logs is append-only');
            END;

            CREATE TRIGGER audit_logs_reject_delete
            BEFORE DELETE ON audit_logs
            BEGIN
                SELECT RAISE(ABORT, 'audit_logs is append-only');
            END;
            SQL);
    }

    private function dropSqliteTriggers(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS audit_logs_reject_update;
            DROP TRIGGER IF EXISTS audit_logs_reject_delete;
            SQL);
    }
};
