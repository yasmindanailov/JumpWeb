<?php

namespace Tests\Feature\Identity;

use App\Domain\Identity\Models\Consent;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\TermsAcceptance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **LAS CONDICIONES SE ACEPTAN VERSIONADAS Y EN EL MOMENTO DEL CONTRATO**
 * (`specs/auth-con-google.md` §21.4, `#348`).
 *
 * `[DECIDIDO owner, 2026-09-02]`: se piden en la compra, una vez, y **se vuelven a pedir cuando el
 * texto cambia de versión — igual que el descargo**.
 *
 * ⚠️⚠️ **La versión sale de la PUBLICACIÓN y no de una constante.** `Consent::CURRENT_VERSION` existe
 * desde el primer día, es un `'2026-05-23'` escrito a mano y —medido— **no lo lee nadie**: con ella,
 * alguien edita el texto en el panel, se olvida de subirla, y el producto afirma que el cliente
 * aceptó un texto que nunca vio. Aquí la versión **no puede divergir del texto**.
 */
class TermsAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Sin publicar: el hueco falla hacia invisible
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * ⚠️⚠️ **Una instalación que no haya publicado nunca NO puede quedarse sin poder vender.** Es el
     * mismo criterio que las claves de Google o el kit del cliente: el hueco falla hacia invisible.
     *
     * ⚠️ Va con CONTROL en la segunda mitad — sin él, este caso no distingue «no hay nada que pedir»
     * de «esto no pide nunca nada».
     */
    public function test_without_any_published_version_nothing_is_asked(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($this->service()->pendingFor($user));
        $this->assertNull(TermsAcceptance::currentVersion());

        // CONTROL: en cuanto hay una versión publicada, sí se pide.
        $this->publish(1);

        $this->assertTrue($this->service()->pendingFor($user->fresh()));
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  El caso normal
    // ─────────────────────────────────────────────────────────────────────────────────

    public function test_accepting_records_the_version_the_ip_and_the_date(): void
    {
        $this->publish(1);
        $user = User::factory()->create(['terms_accepted_at' => null]);

        $version = $this->service()->accept($user, '203.0.113.7');

        $this->assertSame(1, $version?->version);
        $this->assertFalse($this->service()->pendingFor($user->fresh()));

        $consent = $user->consents()->where('type', Consent::TYPE_TERMS)->sole();

        $this->assertSame('v1·es', $consent->version);
        $this->assertSame('203.0.113.7', $consent->ip);
        $this->assertNotNull($consent->accepted_at);

        // La columna que ya leían el panel y el export se mantiene al día, no se sustituye.
        $this->assertNotNull($user->fresh()->terms_accepted_at);
    }

    /** Dos pestañas o un doble clic no pueden dejar dos pruebas del mismo consentimiento. */
    public function test_accepting_twice_leaves_one_single_proof(): void
    {
        $this->publish(1);
        $user = User::factory()->create();

        $this->service()->accept($user, '203.0.113.7');
        $this->service()->accept($user->fresh(), '203.0.113.7');

        $this->assertSame(1, $user->consents()->where('type', Consent::TYPE_TERMS)->count());
    }

    /**
     * **Publicar una versión nueva vuelve a pedirlas**, que es la mitad que el owner pidió: *«si se
     * cambian las condiciones, se pide de nuevo diciendo que se han actualizado»*.
     */
    public function test_publishing_a_new_version_asks_again(): void
    {
        $this->publish(1);
        $user = User::factory()->create();
        $this->service()->accept($user, '203.0.113.7');

        $this->assertFalse($this->service()->pendingFor($user->fresh()));

        $this->publish(2);

        $this->assertTrue($this->service()->pendingFor($user->fresh()));

        // Y al aceptar la nueva, la vieja SIGUE ahí: es la prueba de lo que rigió cada compra anterior.
        $this->service()->accept($user->fresh(), '203.0.113.9');

        $this->assertSame(
            ['v1·es', 'v2·es'],
            $user->consents()->where('type', Consent::TYPE_TERMS)->orderBy('id')->pluck('version')->all(),
        );
    }

    /**
     * ⚠️ **La misma versión en OTRO idioma no se vuelve a pedir**: `v1·es` y `v1·en` son la misma
     * versión, y quien la aceptó en inglés y vuelve en castellano ya la aceptó. Se compara por NÚMERO.
     */
    public function test_the_same_version_in_another_language_counts_as_accepted(): void
    {
        $this->publish(1);
        $user = User::factory()->create();

        $user->consents()->create([
            'type' => Consent::TYPE_TERMS, 'accepted_at' => now(), 'ip' => '203.0.113.7', 'version' => 'v1·en',
        ]);

        $this->assertFalse($this->service()->pendingFor($user->fresh()));
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  La regla de gracia, que es la decisión del owner
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * ⚠️⚠️ **`[DECIDIDO owner, 2026-09-02]`: a quien ya las aceptó en el alta NO se le vuelve a pedir**
     * al estrenar el versionado. Su consentimiento es anterior al versionado —lleva el
     * `Consent::CURRENT_VERSION` de siempre— y cuenta como la PRIMERA versión.
     *
     * ▶ **Y se hace con una REGLA, no reescribiendo su fila**: cambiarle la versión de `2026-05-23` a
     * `v1·es` dejaría el registro afirmando que aceptó un documento que no existía cuando firmó. *Una
     * prueba no se edita para que la consulta salga más corta.*
     */
    public function test_an_acceptance_from_before_versioning_counts_as_the_first_version(): void
    {
        $this->publish(1);
        $user = User::factory()->create();

        $user->consents()->create([
            'type' => Consent::TYPE_TERMS,
            'accepted_at' => now()->subMonths(3),
            'ip' => '203.0.113.7',
            'version' => Consent::CURRENT_VERSION,
        ]);

        $this->assertFalse($this->service()->pendingFor($user->fresh()));

        // Y la fila NO se ha tocado: sigue diciendo exactamente lo que decía.
        $this->assertSame(Consent::CURRENT_VERSION, $user->consents()->where('type', Consent::TYPE_TERMS)->sole()->version);
    }

    /**
     * ⚠️⚠️ **Y LA GRACIA MUERE EN LA v2.** Es lo que impide que se convierta en una puerta abierta: una
     * aceptación anterior al versionado vale para estrenar el mecanismo, no para siempre.
     */
    public function test_the_grace_rule_dies_with_the_second_version(): void
    {
        $this->publish(1);
        $this->publish(2);
        $user = User::factory()->create();

        $user->consents()->create([
            'type' => Consent::TYPE_TERMS,
            'accepted_at' => now()->subMonths(3),
            'ip' => '203.0.113.7',
            'version' => Consent::CURRENT_VERSION,
        ]);

        $this->assertTrue($this->service()->pendingFor($user->fresh()));
    }

    /**
     * ⚠️ **Un consentimiento de OTRO tipo no cuenta.** Sin esta comprobación, la privacidad —que se
     * escribe en el alta y lleva la misma versión vieja— daría por aceptadas las condiciones.
     */
    public function test_a_consent_of_another_type_does_not_count(): void
    {
        $this->publish(1);
        $user = User::factory()->create();

        $user->consents()->create([
            'type' => Consent::TYPE_PRIVACY, 'accepted_at' => now(), 'ip' => '203.0.113.7', 'version' => Consent::CURRENT_VERSION,
        ]);

        $this->assertTrue($this->service()->pendingFor($user->fresh()));
    }

    /** El lector de etiquetas, con sus dos formas y su control. */
    public function test_the_version_label_reader_tells_versioned_from_legacy(): void
    {
        $this->assertSame(1, TermsAcceptance::numberOf('v1·es'));
        $this->assertSame(12, TermsAcceptance::numberOf('v12·fr'));
        $this->assertNull(TermsAcceptance::numberOf(Consent::CURRENT_VERSION));
        $this->assertNull(TermsAcceptance::numberOf(null));
        $this->assertNull(TermsAcceptance::numberOf('v1'), 'sin el separador no es una etiqueta de versión');
    }

    // ─────────────────────────────────────────────────────────────────────────────────

    private function service(): TermsAcceptance
    {
        return app(TermsAcceptance::class);
    }

    private function publish(int $version): void
    {
        $title = 'Condiciones';
        $sections = [['h' => 'Reserva', 'p' => 'Texto de la versión '.$version.'.']];

        LegalDocumentVersion::create([
            'slug' => TermsAcceptance::SLUG,
            'version' => $version,
            'locale' => 'es',
            'title' => $title,
            'body' => $sections,
            'body_hash' => LegalDocumentVersion::hashFor($title, $sections),
            'published_at' => now(),
        ]);
    }
}
