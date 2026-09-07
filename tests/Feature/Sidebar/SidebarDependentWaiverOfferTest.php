<?php

namespace Tests\Feature\Sidebar;

use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Platform\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DeclaresDependents;
use Tests\TestCase;

/**
 * `#441` · **LA TARJETA DE UN MENOR NO PUEDE OFRECER UN BOTÓN QUE SOLO PUEDE FALLAR.**
 *
 * El defecto, reproducido con control antes de tocar nada:
 *
 * ```
 * (a) ALTA del menor con el titular SIN correo verificado: OK
 * (b) GET /me/dependents -> waiver={"signed":false,...}      ← la tarjeta ofrecía firmar
 * (c) POST .../waiver (correo SIN verificar) -> 409 waiver_email_unverified
 * (d) CONTROL con el correo verificado        -> 201
 * ```
 *
 * `WaiverSigner` exige el correo del titular verificado para firmar por un menor a cargo, pero
 * `dependentNeedsSignature()` miraba `is_minor`, `mode`, `signed` y `outdated` — **nunca el correo**.
 * ▶ *Es el mismo defecto que `#329` arregló para el waiver del TITULAR*, vivo en la puerta por la
 * que aquella corrección no pasó. Y no es una ventana de minutos: se puede iniciar sesión sin
 * verificar, así que quien no abre el correo lo veía cada vez que entraba.
 *
 * ⚠️⚠️ **La regla del correo verificado NO se relaja, y el caso 3 de aquí es lo que lo sostiene.**
 * La primera versión del diseño proponía exceptuar al menor a cargo; la revisión adversarial
 * reprodujo el daño (declarar 20 menores reales con la cuenta de un tercero y firmarlos convierte
 * fichas borrables en PII indeleble) y `[DECIDIDO owner]` se retiró la excepción. Sin un caso que
 * fije el 409, alguien «terminará el trabajo» relajándolo para que la pantalla sea más cómoda.
 *
 * ⚠️ La CONDUCTA del predicado vive en `account/dependents.test.js` con `node --test`; aquí se fija
 * el **cableado** —que el dato llegue desde el contexto de cuenta hasta la tarjeta— porque las zonas
 * de la cuenta no las monta el contrato de árbol y **ninguna otra guarda las mira**.
 */
class SidebarDependentWaiverOfferTest extends TestCase
{
    use DeclaresDependents;
    use RefreshDatabase;

    private const CARD = 'resources/js/sidebar/account/zones/DependentCard.vue';

    private const ZONE = 'resources/js/sidebar/account/zones/DependentsZone.vue';

    private const MODULE = 'resources/js/sidebar/account/dependents.js';

    private function source(string $path): string
    {
        return (string) file_get_contents(base_path($path));
    }

    // ── 1 · El cableado: el dato llega desde el contexto hasta la tarjeta ─────────────────────

    public function test_the_card_decides_what_to_offer_with_the_verified_email(): void
    {
        $card = $this->source(self::CARD);

        // El formulario cuelga de la ACCIÓN, no del predicado viejo: `dependentNeedsSignature()`
        // contesta «¿falta firma?», que no es «¿puede firmarla ahora?».
        $this->assertStringContainsString('dependentWaiverAction(props.dependent, props.emailVerified)', $card);
        $this->assertStringNotContainsString('dependentNeedsSignature(props.dependent)', $card,
            'La tarjeta volvió a decidir con el predicado que no mira el correo verificado.');

        // Y el aviso existe: sin él la tarjeta se quedaría muda para quien no puede firmar.
        $this->assertStringContainsString('v-if="mustVerify"', $card);
    }

    public function test_the_zone_feeds_the_card_from_the_account_context(): void
    {
        $zone = $this->source(self::ZONE);

        // ⚠️ El dato sale del CONTEXTO DE CUENTA —el mismo que gobierna el aviso del índice—, no de
        // la respuesta de menores: si cada pantalla lo dedujera por su cuenta, acabarían
        // discrepando sobre si esta persona puede firmar.
        $this->assertStringContainsString('useAccountContextStore', $zone);
        $this->assertStringContainsString(':email-verified="context.emailVerified"', $zone);
    }

    public function test_an_unloaded_context_still_offers_to_sign(): void
    {
        // ⚠️ `=== false` y no `!`: con el contexto aún sin cargar (`undefined`) se ofrece FIRMAR.
        // Suponer lo peor escondería la acción a quien sí puede hacerla — el mismo criterio que
        // `accountNoticeFrom()`. Si esto se escribiera con `!`, `undefined` pasaría a «verificar».
        $this->assertStringContainsString('emailVerified === false', $this->source(self::MODULE));
    }

    // ── 2 · Y la regla del servidor SIGUE, que es lo que hace honesto el aviso ────────────────

    public function test_the_server_still_refuses_to_sign_without_a_verified_email(): void
    {
        // ❗❗ **CONTROL DE DOMINIO.** El aviso de la tarjeta solo tiene sentido mientras el servidor
        // rechace de verdad: si alguien relajara `WaiverSigner`, este caso se pone rojo y obliga a
        // reabrir la decisión en vez de dejar la pantalla mintiendo por exceso de celo.
        Setting::updateOrCreate(['key' => 'waiver.mode'], ['value' => 'interno', 'group' => 'waiver']);
        Setting::flushMemo();

        $doc = app(LegalDocumentPublisher::class)->publish('waiver', [
            'es' => ['title' => 'Exención', 'body' => [['h' => 'Riesgo', 'p' => 'Saltar implica riesgos.']]],
        ])->first();

        $holder = User::factory()->create(['email_verified_at' => null]);
        $dep = $this->declareLegacyDependent($holder, 'Sonda', '2018-05-05', 'Menor', 'father');

        $this->actingAs($holder, 'sanctum');

        $this->postJson("/api/v1/me/dependents/{$dep->id}/waiver", ['document_id' => $doc->id])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'waiver_email_unverified');

        // CONTROL del control: con el correo verificado, el MISMO gesto sale. Sin esta mitad, un
        // endpoint que devolviera 409 siempre pasaría el caso de arriba.
        $holder->forceFill(['email_verified_at' => now()])->save();

        $this->postJson("/api/v1/me/dependents/{$dep->id}/waiver", ['document_id' => $doc->id])
            ->assertStatus(201);

        $this->assertTrue(Dependent::query()->whereKey($dep->id)->exists());
    }
}
