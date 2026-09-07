<?php

namespace Tests\Feature\Dependents;

use App\Domain\Identity\Exceptions\DependentWaiverRequiredException;
use App\Domain\Identity\Exceptions\WaiverDocumentStaleException;
use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Identity\Services\DependentRegistry;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\WaiverAcceptance;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Models\Setting;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * `#441` · **DECLARAR UN MENOR Y ACEPTAR SU EXENCIÓN SON UN SOLO GESTO**
 * (`specs/firma-al-declarar-menor.md` §4.3, `[DECIDIDO owner, 2026-09-06]`).
 *
 * El encargo del owner: *«muchos clientes añaden un menor y después no firman»*. Hasta hoy eran dos
 * gestos y **cinco puertas ninguna de las cuales obligaba**; ahora no existe el estado intermedio.
 *
 * ❗❗❗ **Y la firma NO nace sobre un buzón sin demostrar.** La primera versión del diseño proponía
 * relajar la regla del correo verificado (`#179`) y la revisión adversarial reprodujo el daño: un
 * tercero declara veinte menores REALES con la cuenta de otra persona y los firma; cuando la víctima
 * reclama su cuenta se los queda con las firmas intactas y **no puede deshacerlo**, porque con firma
 * detrás `remove()` solo desvincula y `anonymize()` conserva (art. 17.3.e). Con la aceptación
 * RETENIDA el gesto sigue siendo uno y lo único que se aplaza es el efecto probatorio.
 *
 * Lo que vigila, en orden de daño:
 *  1. que **no se cree el menor** si la aceptación falta o el texto caducó — es un invariante de
 *     ESCRITURA, no un aviso de pantalla;
 *  2. que con el correo sin verificar quede **retenida y sin firma**, y que verificar la convierta;
 *  3. que fuera de `interno`, o sin versión publicada, el alta funcione **exactamente como antes**.
 */
class DependentWaiverAtSignupTest extends TestCase
{
    use RefreshDatabase;

    private function mode(string $mode): void
    {
        Setting::updateOrCreate(['key' => 'waiver.mode'], ['value' => $mode, 'group' => 'waiver']);
        Setting::flushMemo();
    }

    private function publish(): LegalDocumentVersion
    {
        return app(LegalDocumentPublisher::class)->publish('waiver', [
            'es' => ['title' => 'Exención', 'body' => [['h' => 'Riesgo', 'p' => 'Saltar implica riesgos.']]],
        ])->first();
    }

    private function registry(): DependentRegistry
    {
        return app(DependentRegistry::class);
    }

    private function request(): WaiverSignatureRequest
    {
        return WaiverSignatureRequest::web('10.0.0.7', 'test-agent');
    }

    // ── 1 · Sin aceptar no se crea NADA ──────────────────────────────────────────────────────

    public function test_declaring_without_accepting_creates_nothing(): void
    {
        $this->mode('interno');
        $this->publish();
        $holder = User::factory()->create(['email_verified_at' => now()]);

        $this->expectException(DependentWaiverRequiredException::class);

        try {
            $this->registry()->add($holder, 'Lior', '2017-03-12', 'Gil', 'father');
        } finally {
            // ⚠️ Se cuentan FILAS, no se confía en la excepción: un `add()` que lanzara DESPUÉS de
            // escribir dejaría la ficha creada y el caso seguiría en verde mirando solo el throw.
            $this->assertSame(0, Dependent::query()->count());
            $this->assertSame(0, WaiverSignature::query()->count());
        }
    }

    public function test_a_stale_document_rolls_the_whole_thing_back(): void
    {
        $this->mode('interno');
        $v1 = $this->publish();
        $holder = User::factory()->create(['email_verified_at' => now()]);

        // Se republica DESPUÉS de que la pantalla sirviera `v1`: `WaiverSigner` lo caza bajo el lock
        // (S-3 de `#181`) y lanza desde DENTRO de la transacción del alta.
        $this->publish();

        try {
            $this->registry()->add($holder, 'Lior', '2017-03-12', 'Gil', 'father', $v1, $this->request());
            $this->fail('un texto caducado tiene que impedir el alta');
        } catch (WaiverDocumentStaleException) {
            // esperado
        }

        $this->assertSame(0, Dependent::query()->count(), 'la transacción se deshace ENTERA: el menor no existe');
        $this->assertSame(0, WaiverSignature::query()->count());
    }

    // ── 2 · Con el correo verificado: firma en el acto ───────────────────────────────────────

    public function test_a_verified_holder_signs_in_the_same_transaction(): void
    {
        $this->mode('interno');
        $version = $this->publish();
        $holder = User::factory()->create(['email_verified_at' => now()]);

        $dependent = $this->registry()->add($holder, 'Lior', '2017-03-12', 'Gil', 'father', $version, $this->request());

        $firma = WaiverSignature::query()->where('subject_id', $dependent->id)->first();
        $this->assertNotNull($firma, 'con el buzón demostrado la firma nace con el menor');
        $this->assertSame(WaiverSignature::SUBJECT_DEPENDENT, $firma->subject_type);
        $this->assertTrue($firma->verifyHash());
        $this->assertNull($dependent->fresh()->waiver_pending_document_id, 'firmada no deja pendiente');
    }

    // ── 3 · Sin verificar: RETENIDA, y se sella al verificar ─────────────────────────────────

    public function test_an_unverified_holder_leaves_the_acceptance_pending_and_no_signature(): void
    {
        $this->mode('interno');
        $version = $this->publish();
        $holder = User::factory()->create(['email_verified_at' => null]);

        $dependent = $this->registry()->add($holder, 'Lior', '2017-03-12', 'Gil', 'father', $version, $this->request());

        $this->assertSame(0, WaiverSignature::query()->count(), 'ninguna firma nace sobre un buzón sin demostrar');

        $fresh = $dependent->fresh();
        $this->assertSame($version->id, $fresh->waiver_pending_document_id);
        $this->assertSame('10.0.0.7', $fresh->waiver_pending_ip, 'la IP es la de ACEPTAR, no la de verificar');
        $this->assertSame('test-agent', $fresh->waiver_pending_user_agent);
    }

    public function test_verifying_the_email_turns_the_pending_acceptance_into_a_signature(): void
    {
        $this->mode('interno');
        $version = $this->publish();
        $holder = User::factory()->create(['email_verified_at' => null]);
        $uno = $this->registry()->add($holder, 'Lior', '2017-03-12', 'Gil', 'father', $version, $this->request());
        $dos = $this->registry()->add($holder, 'Vera', '2019-11-02', 'Gil', 'mother', $version, $this->request());

        // ⚠️ **DOS menores y no uno**: la ranura del titular es única, así que con un solo sujeto no
        // se vería si el mecanismo escala a N pendientes — que es justo por lo que estas columnas
        // viven en la fila del menor y no en `users`.
        $holder->forceFill(['email_verified_at' => now()])->save();
        Event::dispatch(new Verified($holder));

        $this->assertSame(2, WaiverSignature::query()->where('subject_type', WaiverSignature::SUBJECT_DEPENDENT)->count());
        foreach ([$uno, $dos] as $dependent) {
            $this->assertNull($dependent->fresh()->waiver_pending_document_id, 'la pendiente se limpia al sellarla');
            $this->assertTrue(
                WaiverSignature::query()->where('subject_id', $dependent->id)->first()?->verifyHash(),
                "la firma de {$dependent->name} verifica"
            );
        }
    }

    public function test_a_republished_text_drops_the_pending_acceptance_without_signing(): void
    {
        $this->mode('interno');
        $version = $this->publish();
        $holder = User::factory()->create(['email_verified_at' => null]);
        $this->registry()->add($holder, 'Lior', '2017-03-12', 'Gil', 'father', $version, $this->request());

        // Se publica otra versión antes de que verifique: lo aceptado ya no es lo vigente.
        $this->publish();

        $holder->forceFill(['email_verified_at' => now()])->save();
        Event::dispatch(new Verified($holder));

        $this->assertSame(0, WaiverSignature::query()->count(),
            'en ningún caso se firma un texto que no se ha leído: la pendiente se descarta');
    }

    public function test_unlinking_a_dependent_clears_its_pending_acceptance(): void
    {
        $this->mode('interno');
        $version = $this->publish();
        $holder = User::factory()->create(['email_verified_at' => null]);
        $dependent = $this->registry()->add($holder, 'Lior', '2017-03-12', 'Gil', 'father', $version, $this->request());

        // Se le asigna algo para que `remove()` DESVINCULE en vez de borrar (si borrara, las columnas
        // se irían con la fila y este caso no mediría nada).
        $dependent->forceFill(['removed_at' => null])->save();
        $dependent->unlink();

        $fresh = $dependent->fresh();
        $this->assertNotNull($fresh->removed_at);
        $this->assertNull($fresh->waiver_pending_document_id,
            'una aceptación de un menor retirado no puede convertirse en firma cuando el titular verifique');
        $this->assertNull($fresh->waiver_pending_ip);
    }

    // ── 4 · Y donde no hay nada que aceptar, todo sigue como antes ───────────────────────────

    public function test_outside_internal_mode_the_signup_is_untouched(): void
    {
        $this->mode('externo');
        $this->publish();
        $holder = User::factory()->create(['email_verified_at' => now()]);

        $dependent = $this->registry()->add($holder, 'Lior', '2017-03-12', 'Gil', 'father');

        $this->assertNotNull($dependent->id);
        $this->assertSame(0, WaiverSignature::query()->count());
    }

    public function test_internal_mode_without_a_published_version_is_untouched(): void
    {
        // ⚠️ La otra mitad, y la que más protege: una instalación en `interno` que todavía NO ha
        // publicado la v1 —el paso manual de despliegue que `#348` documenta— **no puede quedarse sin
        // poder declarar menores**. Falla hacia lo de siempre, nunca hacia lo nuevo.
        $this->mode('interno');
        $holder = User::factory()->create(['email_verified_at' => now()]);

        $dependent = $this->registry()->add($holder, 'Lior', '2017-03-12', 'Gil', 'father');

        $this->assertNotNull($dependent->id);
        $this->assertSame(0, WaiverSignature::query()->count());
    }

    // ── 5 · Por la API, que es por donde entra el cliente ────────────────────────────────────

    public function test_the_api_refuses_to_declare_without_the_checkbox_and_names_the_field(): void
    {
        $this->mode('interno');
        $version = $this->publish();
        $holder = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($holder, 'sanctum')
            ->postJson('/api/v1/me/dependents', [
                'name' => 'Lior', 'surname' => 'Gil', 'relationship' => 'father', 'born_on' => '2017-03-12',
            ])
            ->assertStatus(422)
            // ⚠️ Se asevera que el error NOMBRA el campo, no su texto: con la casilla ausente salta
            // `required` y con ella a `false` salta `accepted`, y las dos son la respuesta correcta.
            // Atar la frase habría convertido esta guarda en un test de traducción.
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonStructure(['error' => ['fields' => ['accept_waiver']]]);

        $this->assertSame(0, Dependent::query()->count());

        // CONTROL: con la casilla y el documento, entra y firma.
        $this->actingAs($holder, 'sanctum')
            ->postJson('/api/v1/me/dependents', [
                'name' => 'Lior', 'surname' => 'Gil', 'relationship' => 'father', 'born_on' => '2017-03-12',
                'accept_waiver' => true, 'waiver_document_id' => $version->id,
            ])
            ->assertCreated();

        $this->assertSame(1, Dependent::query()->count());
        $this->assertSame(1, WaiverSignature::query()->count());
    }

    public function test_the_api_answers_409_when_the_served_text_is_no_longer_current(): void
    {
        $this->mode('interno');
        $v1 = $this->publish();
        $holder = User::factory()->create(['email_verified_at' => now()]);
        $this->publish();

        $this->actingAs($holder, 'sanctum')
            ->postJson('/api/v1/me/dependents', [
                'name' => 'Lior', 'surname' => 'Gil', 'relationship' => 'father', 'born_on' => '2017-03-12',
                'accept_waiver' => true, 'waiver_document_id' => $v1->id,
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'waiver_document_stale');

        $this->assertSame(0, Dependent::query()->count());
    }

    public function test_a_stale_text_is_refused_for_an_unverified_holder_too(): void
    {
        // ⚠️⚠️ **El caso que faltaba, y lo dijo la MUTACIÓN.** Con el titular VERIFICADO hay DOS
        // capas —la del controlador y la re-comprobación de `WaiverSigner` bajo el lock—, así que
        // quitar la primera no cambiaba nada y la mutación pasaba en verde.
        //
        // ▶ Pero con el correo SIN verificar no se firma: se RETIENE, y esa rama **no vuelve a
        // comprobar la vigencia**. Ahí la del controlador es la ÚNICA capa, y sin ella se guardaría
        // como pendiente un texto que ya nadie puede leer — para sellarlo semanas después.
        $this->mode('interno');
        $v1 = $this->publish();
        $holder = User::factory()->create(['email_verified_at' => null]);
        $this->publish();

        $this->actingAs($holder, 'sanctum')
            ->postJson('/api/v1/me/dependents', [
                'name' => 'Lior', 'surname' => 'Gil', 'relationship' => 'father', 'born_on' => '2017-03-12',
                'accept_waiver' => true, 'waiver_document_id' => $v1->id,
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'waiver_document_stale');

        $this->assertSame(0, Dependent::query()->count());
        $this->assertSame(0, Dependent::query()->whereNotNull('waiver_pending_document_id')->count());
    }

    public function test_outside_internal_mode_the_api_does_not_ask_for_anything(): void
    {
        $this->mode('externo');
        $holder = User::factory()->create(['email_verified_at' => now()]);

        // ⚠️ El caso que caza la regla `accepted`, que es IMPLÍCITA en Laravel y falla también con el
        // campo AUSENTE: con ella en la rama de «no exigible», una instalación en `externo` recibía
        // 422 al declarar un menor. Lo pagó la primera versión de este controlador.
        $this->actingAs($holder, 'sanctum')
            ->postJson('/api/v1/me/dependents', [
                'name' => 'Lior', 'surname' => 'Gil', 'relationship' => 'father', 'born_on' => '2017-03-12',
            ])
            ->assertCreated();
    }

    public function test_the_waiver_acceptance_never_reaches_the_audit_log(): void
    {
        // `RGPD-02`: la auditoría del alta lleva ids, nunca PII — tampoco la IP de la aceptación.
        $this->mode('interno');
        $version = $this->publish();
        $holder = User::factory()->create(['email_verified_at' => null]);

        $dependent = $this->registry()->add($holder, 'Lior', '2017-03-12', 'Gil', 'father', $version, $this->request());

        $log = AuditLog::query()->where('action', 'dependents.added')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame(['dependent_id' => $dependent->id], $log->payload);
    }

    // ── 6 · El sujeto que sobrevive: el menor HEREDADO ───────────────────────────────────────

    public function test_a_dependent_declared_before_this_change_can_still_be_signed_later(): void
    {
        // ⚠️ La tarjeta del cajón conserva su formulario de firma justamente por esto: los menores
        // declarados antes de `#441` y los que quedan `outdated` al publicar una versión nueva.
        $this->mode('externo');
        $holder = User::factory()->create(['email_verified_at' => now()]);
        $heredado = $this->registry()->add($holder, 'Heredado', '2017-03-12', 'Gil', 'father');

        $this->mode('interno');
        $version = $this->publish();

        app(WaiverAcceptance::class)->acceptForDependent($holder, $heredado, (int) $version->id, $this->request());

        $this->assertSame(1, WaiverSignature::query()->where('subject_id', $heredado->id)->count());
    }
}
