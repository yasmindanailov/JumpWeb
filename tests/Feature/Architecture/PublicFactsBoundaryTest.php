<?php

namespace Tests\Feature\Architecture;

use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\PublicFacts;
use App\Http\Resources\Api\V1\SiteFactsResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

/**
 * **NINGÚN RECURSO PÚBLICO LEE UN AJUSTE FUERA DE SU LISTA BLANCA** (F5 · T1,
 * `docs/specs/instancia-y-landing-fuera.md` §4.1 y §5; `DECISIONES #631`).
 *
 * Es la guarda que la spec pedía, y el peligro que cubre es concreto y está medido: la tabla `settings` son
 * **71 filas** y ahí conviven `contact.email` y **`redsys_secret_key`** (spec §1.3). La API pública de
 * lectura existe para que una landing ajena pinte hechos; el modo de fallo no es que falte un dato, es que
 * salga uno que no debía, y eso **no rompe ningún test** por sí solo: el JSON sigue siendo válido.
 *
 * Tres cierres, y hacen falta los tres porque cada uno tapa lo que el anterior no ve:
 *
 *  1. **De código** — un recurso público no puede llamar a `Setting::` a pelo. Si pudiera, la lista blanca
 *     sería decorativa.
 *  2. **De ejecución** — pedir por `PublicFacts` una clave no declarada revienta, no devuelve `null`.
 *  3. **De contenido** — un secreto no sale **ni declarándolo**, porque una lista blanca protege del olvido
 *     y no del copiar y pegar.
 */
class PublicFactsBoundaryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Los recursos de la API pública que sirven hechos de la instalación.
     *
     * ⚠️ La lista crece con el menú (horario, normas, legales, precios…). Lo que NO puede es que un recurso
     * nuevo se quede fuera sin que nadie lo note: por eso el caso de abajo la contrasta con el disco.
     *
     * @var array<class-string, string>
     */
    private const RECURSOS = [
        SiteFactsResource::class => 'app/Http/Resources/Api/V1/SiteFactsResource.php',
    ];

    // ─────────────────────────────────────────────────────────────────────────────────
    //  1 · De código: la lista blanca es el ÚNICO camino
    // ─────────────────────────────────────────────────────────────────────────────────

    public function test_no_public_facts_resource_reads_settings_by_hand(): void
    {
        foreach (self::RECURSOS as $clase => $ruta) {
            $fuente = $this->sinComentarios((string) file_get_contents(base_path($ruta)));

            $this->assertStringNotContainsString(
                'Setting::', $fuente, implode("\n", [
                    "«{$clase}» lee la tabla `settings` directamente.",
                    '',
                    'Un hecho público se pide por `PublicFacts`, con la lista de ese recurso. Si se lee a',
                    'mano, la lista blanca no vigila nada y la primera clave nueva que alguien añada al',
                    'panel puede salir a la web sin que nadie la haya mirado.',
                ]),
            );
        }
    }

    /**
     * **Y la lista de arriba está completa.** Sin esto, añadir un recurso público nuevo y olvidarse de
     * declararlo aquí deja la guarda verde mirando a otro lado — el modo de fallo clásico de una lista.
     */
    public function test_the_list_of_public_resources_is_complete(): void
    {
        $enDisco = array_map(
            fn (string $ruta): string => basename($ruta),
            glob(base_path('app/Http/Resources/Api/V1/*FactsResource.php')) ?: [],
        );

        $declarados = array_map('basename', array_values(self::RECURSOS));

        sort($enDisco);
        sort($declarados);

        $this->assertSame(
            $enDisco, $declarados,
            'Hay recursos `*FactsResource` en disco que esta guarda no vigila (o al revés). El menú de '.
            'hechos crece por recursos; la lista de arriba tiene que crecer con él.',
        );
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  2 · De ejecución: lo no declarado revienta
    // ─────────────────────────────────────────────────────────────────────────────────

    public function test_asking_for_an_undeclared_key_fails_closed(): void
    {
        $hechos = PublicFacts::allowing(['business.name']);

        $this->assertSame('', (string) $hechos->get('business.name', ''));

        $this->expectException(LogicException::class);
        $hechos->get('contact.email');
    }

    /**
     * ⚠️ **Falla cerrado, no devuelve `null`.** La diferencia importa: un `null` silencioso se confunde con
     * «la instalación no lo ha rellenado» y el recurso seguiría sirviendo — con un campo menos y sin que
     * nadie se entere de que alguien pidió algo que no está revisado.
     */
    public function test_an_unfilled_key_is_not_the_same_as_an_undeclared_one(): void
    {
        $hechos = PublicFacts::allowing(['business.name']);

        $this->assertNull($hechos->get('business.name'), 'una clave declarada y sin fila devuelve su respaldo');

        try {
            $hechos->get('legal.jurisdiction');
            $this->fail('una clave NO declarada tenía que reventar');
        } catch (LogicException $e) {
            $this->assertStringContainsString('lista blanca', $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  3 · De contenido: un secreto no sale ni declarándolo
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * El caso que da sentido a toda la clase. Se prueban las cinco familias, no un nombre: la lista de
     * secretos de mañana no la conoce nadie hoy.
     */
    public function test_a_secret_cannot_be_whitelisted(): void
    {
        $secretos = [
            'redsys_secret_key',
            'redsys_merchant_code',
            'auth.google_client_secret',
            'algo.api_key',
            'servicio.token',
            'cuenta.password',
        ];

        foreach ($secretos as $clave) {
            try {
                PublicFacts::allowing([$clave]);
                $this->fail("«{$clave}» se pudo declarar en una lista blanca pública");
            } catch (LogicException $e) {
                $this->assertStringContainsString('SECRETO', $e->getMessage());
            }
        }
    }

    /**
     * **Y el simétrico, que es el que evita una guarda inútil**: los hechos que HOY sirve el menú tienen que
     * poder declararse. Una negación demasiado ancha —prohibir todo lo que lleve `id`, por ejemplo— dejaría
     * fuera `social.google_place_id` y la primera reacción de quien tuviera prisa sería relajar el patrón.
     */
    public function test_every_fact_the_menu_serves_today_can_be_whitelisted(): void
    {
        $hechos = PublicFacts::allowing(SiteFactsResource::AJUSTES);

        $this->assertNull($hechos->get('social.google_place_id'), 'un hecho legítimo no se puede declarar');
        $this->assertCount(19, SiteFactsResource::AJUSTES, 'la lista de `/site` cambió: repasa el contrato');
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Y la respuesta, de punta a punta
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **Ningún secreto sale por `/site`**, mirando la respuesta ENTERA como texto y no campo a campo: un
     * campo nuevo mal puesto no aparecería en una lista de campos esperados.
     */
    public function test_the_site_endpoint_never_leaks_a_secret(): void
    {
        foreach (['redsys_secret_key' => 'sk_secreta_de_prueba', 'auth.google_client_secret' => 'gsecret-prueba'] as $clave => $valor) {
            Setting::query()->updateOrCreate(['key' => $clave], ['value' => $valor, 'group' => 'payment']);
        }
        Setting::flushMemo();

        $cuerpo = $this->getJson('/api/v1/site')->assertOk()->getContent();

        $this->assertStringNotContainsString('sk_secreta_de_prueba', (string) $cuerpo);
        $this->assertStringNotContainsString('gsecret-prueba', (string) $cuerpo);
        $this->assertStringNotContainsString('redsys', strtolower((string) $cuerpo));
    }

    private function sinComentarios(string $fuente): string
    {
        return (string) preg_replace(['#/\*.*?\*/#s', '#//[^\n]*#'], ' ', $fuente);
    }
}
