<?php

namespace Tests\Feature\Admin\Puerta;

use App\Domain\Identity\Exceptions\WaiverDocumentStaleException;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\WaiverCounterDeclaration;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Models\Setting;
use App\Livewire\Admin\Puerta\ValidarRegistro;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * `#336` — **LA PUERTA CIERRA LA FIRMA** (`[DECIDIDO owner, 2026-09-01]`).
 *
 * El alta no firma: guarda la aceptación EN ESPERA y la convierte en firma al verificar el correo
 * (`#179`). Quien no abre el correo llega al parque sin exención, y pasarle la tablet a cada uno es
 * la cola que el owner quiere evitar. Aquí hay un **tercer suceso** que la cierra, junto al enlace y
 * al pago: **el operador, con la persona delante** — que es más prueba que un enlace, no menos.
 *
 * ⚠️⚠️ **La regla que este fichero existe para proteger: el operador acredita a la PERSONA, NUNCA al
 * BUZÓN.** Si esto marcara `email_verified_at`, estaríamos afirmando sin prueba que esa dirección es
 * suya — y de ahí cuelga la recuperación de contraseña: quien se registrara con el correo de otro y
 * pasara por la puerta se llevaría de regalo el control de esa dirección. Hay caso propio.
 */
class DeclareWaiverAtGateTest extends TestCase
{
    use RefreshDatabase;

    private LegalDocumentVersion $documento;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        Setting::updateOrCreate(['key' => WaiverSettings::KEY_MODE], ['value' => WaiverSettings::MODE_INTERNAL, 'group' => 'waiver']);
        Setting::flushMemo();

        $this->documento = app(LegalDocumentPublisher::class)->publish(WaiverSettings::SLUG, [
            'es' => ['title' => 'Exención', 'body' => [['h' => 'Condiciones', 'p' => 'Texto de la versión uno.']]],
        ])->first();
    }

    private function operador(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'staff')->value('id')]);

        return $u;
    }

    /** Un titular que aceptó la exención al registrarse y NO ha verificado su correo. */
    private function conAceptacionRetenida(): User
    {
        $u = User::factory()->unverified()->create();
        $u->forceFill([
            'waiver_pending_document_id' => $this->documento->getKey(),
            'waiver_pending_channel' => WaiverSignature::CHANNEL_WEB,
            'waiver_pending_ip' => '203.0.113.7',
            'waiver_pending_user_agent' => 'Mozilla/5.0 (el navegador de la ACEPTACIÓN)',
        ])->save();

        return $u;
    }

    public function test_the_operator_turns_a_held_acceptance_into_a_signature(): void
    {
        $cliente = $this->conAceptacionRetenida();
        $operador = $this->operador();

        $firma = app(WaiverCounterDeclaration::class)->declare($cliente, $operador);

        $this->assertNotNull($firma);
        $this->assertSame((int) $operador->getKey(), (int) $firma->declared_by_user_id, 'la firma no dice quién dio fe');
        $this->assertSame((int) $this->documento->getKey(), (int) $firma->legal_document_version_id);
        $this->assertNull($cliente->fresh()->waiver_pending_document_id, 'la aceptación retenida sigue colgada');
    }

    /**
     * ⚠️⚠️ **EL CASO QUE MÁS PROTEGE DE TODO EL FICHERO.** El operador ve a la persona; no puede ver
     * que un buzón sea suyo. Si esto se pusiera a `true`, quien se registrase con el correo de un
     * tercero saldría del parque pudiendo recuperar esa cuenta por correo.
     */
    public function test_declaring_the_waiver_does_not_verify_the_email(): void
    {
        $cliente = $this->conAceptacionRetenida();

        app(WaiverCounterDeclaration::class)->declare($cliente, $this->operador());

        $this->assertNull($cliente->fresh()->email_verified_at, 'la puerta ha verificado un buzón que nadie ha demostrado');
    }

    /**
     * ⚠️ **La firma conserva la IP y el navegador de la ACEPTACIÓN**, no los del mostrador: ese rastro
     * es el del momento en que la persona leyó el texto. Lo que aporta el operador va en
     * `declared_by_user_id`. Mismo criterio que la firma por verificación del correo.
     */
    public function test_the_signature_keeps_the_trace_of_the_moment_the_text_was_read(): void
    {
        $cliente = $this->conAceptacionRetenida();

        $firma = app(WaiverCounterDeclaration::class)->declare($cliente, $this->operador());

        $this->assertSame('203.0.113.7', $firma->ip);
        $this->assertStringContainsString('ACEPTACIÓN', (string) $firma->user_agent);
        $this->assertSame(WaiverSignature::CHANNEL_WEB, $firma->channel);
    }

    /**
     * ⚠️ **Sin aceptación retenida no hay nada que declarar**, y eso es lo que hace legítimo el gesto:
     * el operador confirma una aceptación que EXISTE. Quien no aceptó nada sigue con la tablet.
     */
    public function test_without_a_held_acceptance_nothing_is_signed(): void
    {
        $cliente = User::factory()->unverified()->create();

        $this->assertNull(app(WaiverCounterDeclaration::class)->declare($cliente, $this->operador()));
        $this->assertSame(0, WaiverSignature::where('user_id', $cliente->getKey())->count());
    }

    /**
     * ⚠️⚠️ **Si el texto cambió, NO se firma el viejo.** La aceptación retenida se descarta y la puerta
     * vuelve a ofrecer la tablet — es la misma regla que aplica la firma por verificación del correo
     * (`#179`: «en ningún caso se firma algo que no se ha leído»).
     */
    public function test_a_newer_text_is_never_signed_from_the_counter(): void
    {
        $cliente = $this->conAceptacionRetenida();

        app(LegalDocumentPublisher::class)->publish(WaiverSettings::SLUG, [
            'es' => ['title' => 'Exención', 'body' => [['h' => 'Condiciones', 'p' => 'Texto de la versión DOS.']]],
        ]);

        try {
            app(WaiverCounterDeclaration::class)->declare($cliente, $this->operador());
            $this->fail('se ha firmado un texto que el cliente no leyó');
        } catch (WaiverDocumentStaleException) {
            $this->assertSame(0, WaiverSignature::where('user_id', $cliente->getKey())->count());
            $this->assertNull($cliente->fresh()->waiver_pending_document_id,
                'la aceptación caducada se queda colgada y el operador podría reintentarla para siempre');
        }
    }

    /** Dos pulsaciones —o dos operadores a la vez— producen UNA firma, no dos. */
    public function test_declaring_twice_signs_once(): void
    {
        $cliente = $this->conAceptacionRetenida();
        $servicio = app(WaiverCounterDeclaration::class);

        $this->assertNotNull($servicio->declare($cliente, $this->operador()));
        $this->assertNull($servicio->declare($cliente->fresh(), $this->operador()), 'la segunda vez no hay nada retenido');
        $this->assertSame(1, WaiverSignature::where('user_id', $cliente->getKey())->count());
    }

    /** El parque revisa la operativa por el registro de auditoría, no fila a fila de las firmas. */
    public function test_the_declaration_leaves_an_audit_trail(): void
    {
        $cliente = $this->conAceptacionRetenida();

        app(WaiverCounterDeclaration::class)->declare($cliente, $this->operador());

        $this->assertSame(1, AuditLog::where('action', 'puerta.waiver_declared')->count());
    }

    /**
     * ❗❗ **EL GESTO DEL OPERADOR, POR LA PANTALLA — y es el caso que faltaba** (2026-09-02).
     *
     * Los siete casos de arriba conducen el SERVICIO. Ninguno pasaba por el componente, así que el
     * cableado entre el botón y el dominio no lo miraba nadie: `declareWaiver()` recomponía la ficha
     * con `GateProfile::for($customer)` —**un argumento de tres**— y el operador se llevaba un
     * `ArgumentCountError` 500 **justo después** de que la firma se hubiera escrito. Salió en
     * producción, en la puerta, el día siguiente al despliegue.
     *
     * ▶ *Que el dominio haga lo correcto no es que la pantalla sepa pedírselo.*
     */
    public function test_the_gate_screen_signs_and_repaints_the_card(): void
    {
        $cliente = $this->conAceptacionRetenida();

        Livewire::actingAs($this->operador())
            ->test(ValidarRegistro::class)
            ->set('input', $cliente->email)
            ->call('search')
            ->call('declareWaiver')
            ->assertOk()
            ->assertSet('profile.waiver.signed', true)
            ->assertSet('profile.waiver.pending_acceptance', false);

        $this->assertSame(1, WaiverSignature::where('user_id', $cliente->getKey())->count());
    }

    /**
     * ⚠️⚠️ **La ficha recompuesta tiene que seguir siendo una FICHA.**
     *
     * `openProfile()` envuelve los datos del dominio con tres claves de PRESENTACIÓN —`via`,
     * `expires_at` y `ttl_minutes`— que la vista lee para el distintivo de origen, el velo de
     * privacidad y el reloj de cierre. Recomponer con un `toArray()` a secas las tira: el
     * `ArgumentCountError` tapaba un segundo defecto que habría dejado la tarjeta a medias **sin
     * que nada fallara**, porque una clave ausente en Blade es una cadena vacía.
     */
    public function test_the_repainted_card_keeps_its_presentation_keys(): void
    {
        $cliente = $this->conAceptacionRetenida();

        $componente = Livewire::actingAs($this->operador())
            ->test(ValidarRegistro::class)
            ->set('input', $cliente->email)
            ->call('search');

        $antes = $componente->get('profile');
        $componente->call('declareWaiver');
        $despues = $componente->get('profile');

        foreach (['via', 'expires_at', 'ttl_minutes'] as $clave) {
            $this->assertArrayHasKey($clave, $despues, "la ficha recompuesta perdió «{$clave}»");
        }

        $this->assertSame($antes['via'], $despues['via'], 'el origen de la ficha no cambia al firmar');
        $this->assertSame($antes['ttl_minutes'], $despues['ttl_minutes']);
    }
}
