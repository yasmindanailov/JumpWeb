<?php

namespace Tests\Feature\Account;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\Consent;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\AccountCredentials;
use App\Domain\Platform\Models\AuditLog;
use App\Livewire\Account\DeleteAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 4.5b — RGPD: descargar datos, ver consentimientos y borrar la cuenta
 * (borrado real, #53). Acciones tras `auth` + `verified`; el borrado exige contraseña.
 */
class PrivacyTest extends TestCase
{
    use RefreshDatabase;

    private function userWithConsents(): User
    {
        $user = User::factory()->create(['name' => 'Ana', 'email' => 'ana@example.com']);
        $user->consents()->create(['type' => 'privacy', 'accepted_at' => now(), 'ip' => '127.0.0.1', 'version' => Consent::CURRENT_VERSION]);
        $user->consents()->create(['type' => 'waiver', 'accepted_at' => now(), 'ip' => '127.0.0.1', 'version' => Consent::CURRENT_VERSION]);

        return $user;
    }

    public function test_export_returns_json_with_profile_and_consents(): void
    {
        $user = $this->userWithConsents();

        $response = $this->actingAs($user)->get(route('account.export'));

        $response->assertOk();
        $this->assertStringContainsString('application/json', $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));
        // `no-store` (auditoría Fase 1, Sistema 5): el export RGPD es la PII más densa de la app
        // (perfil + consents con IP + event_data con nombre y ALERGIAS de menores, art. 9). Es un
        // controlador (no Livewire) → necesita el alias explícito, como las demás superficies con PII.
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $data = $response->json();
        $this->assertSame('ana@example.com', $data['profile']['email']);
        $this->assertCount(2, $data['consents']);
        $this->assertArrayHasKey('orders', $data);
        $this->assertSame([], $data['orders']); // sin pedidos aún
    }

    public function test_export_includes_orders_with_line_detail(): void
    {
        // Auditoría 2026-05-26 (hallazgo I): el derecho de portabilidad (RGPD art. 20) cubre
        // TODOS los datos personales, incluido el historial de pedidos.
        $user = $this->userWithConsents();
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'JJ-EXPORT', 'status' => 'paid',
            'subtotal' => 1000, 'total' => 1000, 'currency' => 'EUR',
        ]);

        $data = $this->actingAs($user)->get(route('account.export'))->json();

        $this->assertCount(1, $data['orders']);
        $this->assertSame('JJ-EXPORT', $data['orders'][0]['code']);
        $this->assertSame(1000, $data['orders'][0]['total_cents']);
        $this->assertSame('EUR', $data['orders'][0]['currency']);
    }

    public function test_export_requires_authentication(): void
    {
        $this->get(route('account.export'))->assertRedirect(route('login'));
    }

    public function test_account_page_lists_consents(): void
    {
        $user = $this->userWithConsents();

        $this->actingAs($user)->get(route('account'))
            ->assertOk()
            ->assertSee(__('account.account.privacy.consent_types.privacy'))
            ->assertSee(__('account.account.privacy.consent_types.waiver'));
    }

    public function test_delete_requires_correct_password(): void
    {
        $user = $this->userWithConsents();

        Livewire::actingAs($user)
            ->test(DeleteAccount::class)
            ->set('current_password', 'incorrecta')
            ->call('destroy')
            ->assertHasErrors('current_password');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_delete_anonymizes_account_clears_consents_and_logs_out(): void
    {
        // Auditoría 2026-05-26 (hallazgo C): NO se hace hard-delete. La cuenta se ANONIMIZA
        // (fila `users` conservada con datos neutros) para que las facturas (AEAT) sigan
        // vinculadas. Los datos personales se sobrescriben y consents/roles se eliminan.
        $user = $this->userWithConsents();
        $role = Role::create(['name' => 'customer', 'label' => 'Cliente']);
        $user->roles()->attach($role);
        $originalId = $user->id;

        Livewire::actingAs($user)
            ->test(DeleteAccount::class)
            ->set('current_password', 'password')
            ->call('destroy')
            ->assertRedirect('/');

        // La fila sigue existiendo, pero anonimizada.
        $this->assertDatabaseHas('users', ['id' => $originalId]);
        $anonymized = User::find($originalId);
        $this->assertNotNull($anonymized);
        $this->assertTrue($anonymized->isAnonymized());
        $this->assertSame('Cliente eliminado', $anonymized->name);
        $this->assertNull($anonymized->phone);
        $this->assertNotSame('ana@example.com', $anonymized->email);
        $this->assertStringEndsWith('@deleted.local', $anonymized->email);

        // Consents y roles eliminados/desvinculados.
        $this->assertDatabaseMissing('consents', ['user_id' => $originalId]);
        $this->assertDatabaseMissing('role_user', ['user_id' => $originalId]);

        // El cliente queda deslogueado y el email original libre.
        $this->assertGuest();
    }

    public function test_anonymize_purges_guest_pii_sessions_and_reset_tokens(): void
    {
        // Auditoría Fase 1 (A1/A2/A10): anonymize() (RGPD art. 17) purga la PII de TERCEROS (nombres y
        // ALERGIAS de menores en order_items.guest_data/event_data), las SESIONES activas del titular y
        // su TOKEN de reset (email en claro), además de neutralizar la fila `users`. El pedido se conserva.
        config(['session.driver' => 'database']);
        $user = User::factory()->create(['email' => 'ana@example.com']);

        $zone = Zone::create(['slug' => 'z', 'name' => ['es' => 'Z']]);
        $type = TicketType::create([
            'name' => ['es' => 'Cumple'], 'zone_id' => $zone->id, 'is_sellable' => true,
            'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'JJ-PII', 'status' => Order::STATUS_PAID,
            'subtotal' => 1000, 'total' => 1000,
        ]);
        $item = $order->items()->create([
            'ticket_type_id' => $type->id, 'quantity' => 1, 'unit_price' => 1000, 'seats' => 1,
            'guest_data' => [['name' => 'Niño', 'allergy' => 'frutos secos']],
            'event_data' => ['birthday_child' => 'Niño'],
        ]);

        DB::table('sessions')->insert([
            'id' => 'sess-ana', 'user_id' => $user->id, 'ip_address' => '127.0.0.1',
            'user_agent' => 'x', 'payload' => '', 'last_activity' => 1750000000,
        ]);
        DB::table('password_reset_tokens')->insert([
            'email' => 'ana@example.com', 'token' => 'hash', 'created_at' => now(),
        ]);

        $user->anonymize();

        // A1: PII de invitados purgada; el pedido y sus importes se conservan (AEAT).
        $item->refresh();
        $this->assertNull($item->guest_data);
        $this->assertNull($item->event_data);
        $this->assertSame(1000, (int) $order->fresh()->total);

        // A2: sesión activa del titular eliminada.
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        // A10: token de reset con el email ORIGINAL eliminado.
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'ana@example.com']);
    }

    public function test_anonymize_redacts_event_data_audit_payloads(): void
    {
        // P2 (auditoría Fase 1 · RGPD art.17): el audit `event_data_updated` de pedidos LEGACY pudo
        // guardar el nombre del homenajeado (PII de menor) en el payload (hoy se guardan solo las
        // claves). anonymize() lo redacta para los items del titular → la PII no sobrevive ahí tampoco.
        $user = User::factory()->create();
        $zone = Zone::create(['slug' => 'z-aud', 'name' => ['es' => 'Z']]);
        $type = TicketType::create([
            'name' => ['es' => 'Cumple'], 'zone_id' => $zone->id, 'is_sellable' => true,
            'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'JJ-AUD', 'status' => Order::STATUS_PAID,
            'subtotal' => 1000, 'total' => 1000,
        ]);
        $item = $order->items()->create([
            'ticket_type_id' => $type->id, 'quantity' => 1, 'unit_price' => 1000, 'seats' => 1,
        ]);

        // Fila LEGACY con PII en el payload (como las que se escribían antes del fix P2).
        $legacy = AuditLog::create([
            'action' => 'order_items.event_data_updated',
            'target_type' => (new OrderItem)->getMorphClass(),
            'target_id' => $item->id,
            'payload' => ['diff' => ['changed' => ['celebrant' => ['Antiguo', 'Lucía']]]],
            'payload_hash' => 'x',
            'created_at' => now(),
        ]);

        $user->anonymize();

        $this->assertNull($legacy->fresh()->payload);
        $this->assertStringNotContainsString('Lucía', json_encode($legacy->fresh()->payload ?? []));
    }

    public function test_anonymize_redacts_email_resent_audit_payloads(): void
    {
        // Sistema 5 (auditoría Fase 1 · RGPD art.17): el audit `orders.email_resent` de pedidos
        // LEGACY pudo guardar el EMAIL del titular en claro en el payload (hoy ya no se persiste).
        // anonymize() lo redacta para los pedidos del titular → el email no sobrevive a la supresión.
        $user = User::factory()->create(['email' => 'ana@example.com']);
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'JJ-RESEND', 'status' => Order::STATUS_PAID,
            'subtotal' => 1000, 'total' => 1000,
        ]);

        $legacy = AuditLog::create([
            'action' => 'orders.email_resent',
            'target_type' => (new Order)->getMorphClass(),
            'target_id' => $order->id,
            'payload' => ['order_code' => 'JJ-RESEND', 'type' => 'confirmation', 'email' => 'ana@example.com'],
            'payload_hash' => 'x',
            'created_at' => now(),
        ]);

        $user->anonymize();

        $this->assertNull($legacy->fresh()->payload);
        $this->assertStringNotContainsString('ana@example.com', json_encode($legacy->fresh()->payload ?? []));
    }

    public function test_anonymization_preserves_orders_for_fiscal_reasons(): void
    {
        // Caso crítico: si la cuenta tenía pedidos (factura), borrar la cuenta cascadearía las
        // facturas, lo que incumple AEAT. La anonimización las preserva intactas.
        $user = $this->userWithConsents();

        // Pedido directo (no usamos OrderCreator para no traer toda la maquinaria de aforos).
        Order::create([
            'user_id' => $user->id,
            'code' => 'JJ-TESTAB',
            'status' => Order::STATUS_PAID,
            'subtotal' => 1000, 'total' => 1000,
        ]);

        Livewire::actingAs($user)
            ->test(DeleteAccount::class)
            ->set('current_password', 'password')
            ->call('destroy')
            ->assertRedirect('/');

        $this->assertDatabaseHas('orders', ['user_id' => $user->id, 'code' => 'JJ-TESTAB']);
    }

    /**
     * ⚠️⚠️ **El limitador que la web heredó del dominio, y su aviso EN PANTALLA** (tanda 2 · paso 8).
     *
     * Éste era el ÚLTIMO de los cuatro sitios que reconfirman contraseña sin techo
     * (`DECISIONES #120(o)`) y el más grave: lo que protege es la única acción irreversible del
     * producto. Con una sesión secuestrada se podían probar contraseñas sin límite antes de borrar
     * la cuenta ajena.
     *
     * ⚠️ Y el caso mira **las dos mitades**, porque la segunda faltaba: el mensaje del limitador va
     * a `_global` y esta vista no pintaba ninguna clave global — al agotar los intentos, el
     * formulario no hacía nada y no decía nada (`#117`).
     */
    public function test_the_delete_form_is_rate_limited_and_the_screen_says_why(): void
    {
        // ⚠️ **Con el reloj PARADO** (`DECISIONES #64`): el aviso lleva los segundos que quedan, y
        // `availableIn()` devuelve 59 en cuanto el bucle cruza un segundo. Sin congelarlo, el caso
        // pasa casi siempre y falla cuando la máquina va lenta — que es la peor clase de rojo.
        $this->freezeTime();

        $user = $this->userWithConsents();
        $component = Livewire::actingAs($user)->test(DeleteAccount::class);

        for ($i = 0; $i < AccountCredentials::MAX_ATTEMPTS; $i++) {
            $component->set('current_password', 'mal-'.$i)->call('destroy')->assertHasErrors('current_password');
        }

        // Con la contraseña BUENA también corta: el techo es del intento, no del acierto.
        $component->set('current_password', 'password')->call('destroy')
            ->assertHasErrors('_global')
            ->assertSee(__('auth.throttle', ['seconds' => 60]));

        $this->assertFalse(
            User::find($user->id)->isAnonymized(),
            'la cuenta se ha borrado durante el bloqueo del limitador'
        );
    }

    public function test_email_is_free_after_anonymization(): void
    {
        // El email original libera tras anonimización (nadie debería quedar bloqueado por una
        // cuenta borrada que conserva su email).
        $user = $this->userWithConsents();

        Livewire::actingAs($user)
            ->test(DeleteAccount::class)
            ->set('current_password', 'password')
            ->call('destroy');

        // Otra cuenta puede registrarse con el email original.
        $newUser = User::create([
            'name' => 'Otra Ana', 'email' => 'ana@example.com', 'password' => 'password',
        ]);
        $this->assertNotSame($user->id, $newUser->id);
    }
}
