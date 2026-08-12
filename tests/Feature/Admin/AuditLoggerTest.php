<?php

namespace Tests\Feature\Admin;

use App\Domain\Identity\Models\User;
use App\Domain\Platform\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Fase 7.0 — Auditoría inmutable de acciones sensibles del panel.
 *
 * Ver `docs/PLAN-FASE-7-PANEL.md` §1.4 y `docs/DECISIONES.md` #118.
 */
class AuditLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_logs_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('audit_logs', [
            'user_id', 'action', 'target_type', 'target_id',
            'payload', 'payload_hash', 'ip', 'user_agent', 'created_at',
        ]));

        $this->assertFalse(Schema::hasColumn('audit_logs', 'updated_at'),
            'audit_logs debe ser inmutable (append-only): sin updated_at.');
    }

    public function test_log_stores_payload_visible_and_hash(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $payload = ['previous_status' => 'pending', 'new_status' => 'cancelled'];

        $log = AuditLogger::log('orders.cancelled', target: null, payload: $payload);

        $this->assertNotNull($log);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('orders.cancelled', $log->action);
        $this->assertSame($payload, $log->payload);
        $this->assertSame(hash('sha256', json_encode($payload)), $log->payload_hash);
        $this->assertNull($log->target_type);
        $this->assertNull($log->target_id);
    }

    public function test_log_sensitive_hashes_identifier_and_leaves_payload_null(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $email = 'cliente@example.com';
        $log = AuditLogger::logSensitive('registrations.validated', $email);

        $this->assertNotNull($log);
        $this->assertNull($log->payload, 'Acciones con dato personal NO guardan payload claro.');
        $this->assertSame(hash('sha256', $email), $log->payload_hash);

        // El log no debe almacenar el email en ninguna columna.
        $row = $log->fresh()->getAttributes();
        $this->assertStringNotContainsString($email, json_encode($row),
            'El identificador con dato personal NO debe aparecer en claro en ninguna columna.');
    }

    public function test_log_records_polymorphic_target(): void
    {
        $actor = User::factory()->create();
        $targetUser = User::factory()->create();
        $this->actingAs($actor);

        $log = AuditLogger::log('users.anonymized', target: $targetUser, payload: ['previous_email_hash' => 'sha256...']);

        $this->assertSame($targetUser->getMorphClass(), $log->target_type);
        $this->assertSame($targetUser->id, $log->target_id);
        $this->assertTrue($log->target->is($targetUser));
        $this->assertSame($actor->id, $log->user_id, 'El actor (quien anonimiza) ≠ el target (quien fue anonimizado).');
    }

    public function test_log_without_authenticated_user_records_null_user_id(): void
    {
        Auth::logout();

        $log = AuditLogger::log('orders.expired', payload: ['code' => 'JJ-TEST']);

        $this->assertNotNull($log);
        $this->assertNull($log->user_id, 'Acciones del sistema (scheduler/jobs) tienen user_id null.');
    }

    public function test_logs_are_immutable_by_convention(): void
    {
        // El modelo no tiene timestamps automáticos: no debe modificarse `created_at`
        // al actualizar el modelo, y no existe `updated_at` en absoluto.
        $log = AuditLogger::log('test.action', payload: ['a' => 1]);
        $this->assertFalse($log->timestamps,
            'AuditLog debe deshabilitar `timestamps` para no aceptar updated_at.');
    }
}
