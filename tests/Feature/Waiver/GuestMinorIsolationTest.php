<?php

namespace Tests\Feature\Waiver;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\GuardianAuthorization;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Identity\Services\GuardianAuthorizationSigner;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Identity\Services\WaiverSigner;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 6 · el JUSTIFICANTE de un menor invitado — **el AISLAMIENTO**
 * (`docs/specs/waiver-por-reserva.md` §4.5).
 *
 * ❗❗ **Esta es la fuga que encontró la revisión adversarial (§11.2·A3), medida antes de acotarla.**
 * Poner al RESPONSABLE en `user_id` hace que las firmas de menores invitados apunten a su cuenta —
 * pero **no son suyas**: llevan el nombre del hijo de otra familia y los datos de otro adulto. Con
 * las lecturas de entonces, `GET /me/waiver` devolvía
 *
 *     signed=false · nº signatures = 2
 *       -> subject=guest_minor dependent_id=0 dependent_name='Luis' pdf=…/me/waiver/320/pdf
 *
 * **y el PDF se servía**, porque autorizaba solo por `user_id`. Con §4.13 dentro, el responsable se
 * descargaría el nombre, el teléfono y el correo del otro padre.
 *
 * ▶ La regla vive en `User::waiverSignatures()`, que es **fail-closed**: lo ancho hay que pedirlo por
 * su nombre (`guardianSignatures()`). Lo que llega por RUTA —el PDF— necesita además su propia
 * condición, porque una relación no lo alcanza.
 */
class GuestMinorIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function version(): LegalDocumentVersion
    {
        return app(LegalDocumentPublisher::class)->publish(WaiverSettings::SLUG, [
            'es' => ['title' => 'Exención', 'body' => [['h' => 'Riesgo', 'p' => 'Saltar implica riesgos.']]],
        ])->first();
    }

    /** @return array{responsible: User, authorization: GuardianAuthorization, signature: WaiverSignature} */
    private function scenario(): array
    {
        // ⚠️ El nombre va FIJADO y no lo pone la factoría, y lo pidió la auditoría del reloj: en uno
        // de sus diez pases `User::factory()` generó un nombre que **contenía «Carlos»**, y este
        // fichero asevera que esa cadena NO está en el HTML del panel. *Un nombre aleatorio
        // enfrentado a una aserción por SUBCADENA es una moneda al aire disfrazada de test.*
        $responsible = User::factory()->create(['email_verified_at' => now(), 'name' => 'Titular Responsable']);
        // ⚠️ Con una reserva REAL: desde la T2 el tope sale de las líneas principales vivas, así que
        // un pedido sin ellas tiene capacidad 0 y no admite ni un justificante.
        $zone = Zone::firstOrCreate(['slug' => 'jump'], ['name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true]);
        $type = TicketType::firstOrCreate(['zone_id' => $zone->id, 'type' => TicketType::TYPE_ENTRY], [
            'name' => ['es' => 'Entrada'], 'duration_min' => 60, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
        $slot = Slot::create([
            'zone_id' => $zone->id, 'date' => now()->addMonth()->toDateString(),
            'start_time' => '10:00:00', 'end_time' => '11:00:00', 'capacity' => 200, 'online_capacity' => 200,
        ]);
        $order = Order::create([
            'user_id' => $responsible->id,
            'code' => 'R-'.strtoupper(substr(md5((string) mt_rand()), 0, 6)),
            'status' => Order::STATUS_PAID,
            'subtotal' => 500, 'tax' => 0, 'total' => 500, 'currency' => 'EUR', 'paid_at' => now(),
        ]);
        $order->items()->create([
            'ticket_type_id' => $type->id, 'slot_id' => $slot->id,
            'quantity' => 4, 'unit_price' => 500, 'seats' => 4,
        ]);

        $result = app(GuardianAuthorizationSigner::class)->sign(
            $responsible,
            (int) $order->id,
            $this->version(),
            [
                'minor_name' => 'Luis', 'minor_surname' => 'Pérez Soto', 'minor_born_on' => '2016-11-20',
                'guardian_name' => 'Carlos', 'guardian_surname' => 'Pérez Gil', 'guardian_relationship' => 'father',
                'guardian_email' => 'carlos@example.com', 'guardian_phone' => '600333444',
            ],
            WaiverSignatureRequest::web('10.0.0.9', 'Mozilla/5.0 (padre)'),
        );

        return ['responsible' => $responsible, 'authorization' => $result['authorization'], 'signature' => $result['signature']];
    }

    // ─── La relación es fail-closed ───────────────────────────────────────────

    public function test_the_holders_own_signatures_do_not_include_the_guest_minor_ones(): void
    {
        ['responsible' => $responsible, 'signature' => $signature] = $this->scenario();

        $this->assertSame(0, $responsible->waiverSignatures()->count(), 'esa firma NO es suya');
        $this->assertSame(1, $responsible->guardianSignatures()->count(), 'pero sí la ancla como responsable');
        $this->assertSame($signature->id, $responsible->guardianSignatures()->first()->id);
        // Y la fila SÍ apunta a su cuenta: lo que aísla es el filtro, no un `user_id` distinto.
        $this->assertSame($responsible->id, $signature->user_id);
    }

    // ⚠️ Los tres casos de la API (`GET /me/waiver` y su PDF) viven en
    // `tests/Feature/Api/V1/MeWaiverGuestMinorTest.php`, que hereda de `ApiTestCase` y por tanto
    // valida cada respuesta contra `openapi/v1.yaml`. Aquí pasarían igual de verdes **sin ejercer
    // esa guarda**, que es justo la que la spec (§4.5) dice estar usando.

    // ─── El panel: la ficha del titular y su contador ─────────────────────────

    /**
     * ⚠️⚠️ Se renderiza el PARTIAL, no la página. La primera versión de este caso hacía
     * `get(/admin/users/{id})->assertDontSee(…)` y **pasaba en VACÍO**: el registro probatorio vive
     * en un modal de Filament (`ViewUser::waiverProofAction`), así que esa página no lo pinta nunca
     * y el `assertDontSee` no vigilaba nada. Es el escalón que `#161` ya había documentado.
     *
     * Y lleva CONTROL: la firma propia del titular **sí** tiene que salir. Sin él, un filtro que lo
     * tapara todo pasaría igual de verde.
     */
    public function test_the_admin_proof_panel_shows_the_holders_signatures_and_not_the_guest_minor_ones(): void
    {
        ['responsible' => $responsible] = $this->scenario();
        app(WaiverSigner::class)->sign(
            $responsible,
            LegalDocumentVersion::query()->latest('id')->first(),
            WaiverSignatureRequest::web('10.0.0.1', 'UA'),
        );

        $html = view('filament.users.partials.waiver-proof', ['record' => $responsible->fresh()])->render();

        // CONTROL: el partial está pintando de verdad las firmas del titular.
        $this->assertStringContainsString('Titular Responsable', $html);
        // Y NO las del hijo de otra familia, que además rotularía como «menor a cargo» — mentir en
        // una pantalla probatoria es peor que no enseñarlo.
        $this->assertStringNotContainsString('Pérez Soto', $html);
        // Por NOMBRE COMPLETO, no por el de pila: «Carlos» a secas lo puede traer cualquier otro dato.
        $this->assertStringNotContainsString('Carlos Pérez Gil', $html);
    }

    /** El contador que `ViewUser` audita al abrir el registro sale de la MISMA relación acotada. */
    public function test_the_audited_signature_count_does_not_include_the_guest_minor_ones(): void
    {
        ['responsible' => $responsible] = $this->scenario();

        $this->assertSame(0, $responsible->waiverSignatures()->count());

        app(WaiverSigner::class)->sign(
            $responsible,
            LegalDocumentVersion::query()->latest('id')->first(),
            WaiverSignatureRequest::web('10.0.0.1', 'UA'),
        );

        // CONTROL: cuenta 1, no 0 — el filtro acota, no borra.
        $this->assertSame(1, $responsible->fresh()->waiverSignatures()->count());
    }

    // ─── La poda está cableada, y DESPUÉS de las firmas ───────────────────────

    public function test_the_authorisation_prune_is_wired_in_the_scheduler_after_the_signatures(): void
    {
        $events = collect(app(Schedule::class)->events());

        $prune = $events->first(fn ($event): bool => str_contains((string) $event->command, 'model:prune')
            && str_contains((string) $event->command, 'GuardianAuthorization'));

        $this->assertNotNull($prune, 'un Prunable que no entre en la lista EXPLÍCITA de routes/console.php no se poda NUNCA');
        $command = (string) $prune->command;
        $this->assertLessThan(
            strpos($command, 'GuardianAuthorization'),
            strpos($command, 'WaiverSignature'),
            'las firmas se podan ANTES: la FK es RESTRICT y al revés `model:prune` abortaría a mitad',
        );
    }

    // ─── La limpieza de go-live sigue cerrando (§4.2.1) ───────────────────────

    public function test_the_go_live_purge_closes_with_the_two_restrict_keys_in_the_way(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        ['responsible' => $responsible, 'authorization' => $authorization, 'signature' => $signature] = $this->scenario();

        $admin = User::factory()->create(['email' => 'admin-keep@x.test', 'email_verified_at' => now()]);
        $admin->roles()->sync([Role::where('name', 'admin')->value('id')]);

        // ⚠️ El caso que rompía: el pedido es de una cuenta que la purga NO conserva, pero la purga
        // borra TODOS los pedidos, así que la cadena RESTRICT
        // (firma → autorización → pedido) tiene que resolverse antes o la transacción entera aborta.
        $this->artisan('app:purge-customers', ['--keep' => ['admin-keep@x.test'], '--force' => true])
            ->assertSuccessful();

        $this->assertDatabaseMissing('waiver_signatures', ['id' => $signature->id]);
        $this->assertDatabaseMissing('guardian_authorizations', ['id' => $authorization->id]);
        $this->assertDatabaseMissing('orders', ['user_id' => $responsible->id]);
        $this->assertDatabaseHas('users', ['email' => 'admin-keep@x.test']);
    }

    public function test_the_purge_also_clears_an_authorisation_of_a_kep_t_account(): void
    {
        // El caso incómodo, y va escrito porque parece un error: la purga se lleva la prueba de una
        // cuenta que CONSERVA. Es correcto — borra todos los pedidos, y la prueba de una visita cuyo
        // pedido ya no existe no prueba nada. Sin esto, `Order::query()->delete()` reventaría.
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        ['responsible' => $responsible, 'authorization' => $authorization] = $this->scenario();
        $responsible->roles()->sync([Role::where('name', 'admin')->value('id')]);

        $this->artisan('app:purge-customers', ['--keep' => [$responsible->email], '--force' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['id' => $responsible->id]);
        $this->assertDatabaseMissing('guardian_authorizations', ['id' => $authorization->id]);
    }
}
