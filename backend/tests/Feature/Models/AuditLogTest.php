<?php

namespace Tests\Feature\Models;

use App\Models\AuditLog;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_model_rejects_updates_to_existing_audit_records(): void
    {
        $audit = AuditLog::factory()->create(['action' => 'original.action']);

        $updated = $audit->update(['action' => 'tampered.action']);

        $this->assertFalse($updated);
        $this->assertDatabaseHas('audit_logs', ['id' => $audit->id, 'action' => 'original.action']);
    }

    public function test_model_rejects_deletion_of_existing_audit_records(): void
    {
        $audit = AuditLog::factory()->create();

        $deleted = $audit->delete();

        $this->assertFalse($deleted);
        $this->assertModelExists($audit);
    }

    public function test_database_rejects_mutations_that_bypass_the_model(): void
    {
        $audit = AuditLog::factory()->create(['action' => 'original.action']);

        try {
            DB::table('audit_logs')->where('id', $audit->id)->update(['action' => 'tampered.action']);
            $this->fail('The database accepted a mutation to an append-only audit record.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('audit_logs is append-only', $exception->getMessage());
        }

        $this->assertDatabaseHas('audit_logs', ['id' => $audit->id, 'action' => 'original.action']);
    }
}
