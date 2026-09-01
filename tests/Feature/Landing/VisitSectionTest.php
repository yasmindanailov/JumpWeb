<?php

namespace Tests\Feature\Landing;

use App\Domain\Booking\Models\OpeningHour;
use App\Domain\Platform\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * «Visítanos» en tarjetas (`DECISIONES #307`, `specs/idioma-visual-heredado.md` §3.octies).
 *
 * Lo que vigila NO es la maqueta —eso cambia— sino las tres reglas que la sección tiene que
 * seguir cumpliendo cuando alguien la retoque:
 *
 *  1. **UN solo encabezado.** Era el caso extremo del molde editorial de `#297`: cuatro
 *     encabezados para cuatro líneas de dato. Quien vuelva a meter un `h3` «Horarios» dentro
 *     está reconstruyendo el molde que este carril existe para deshacer.
 *  2. **El teléfono está.** El dato ya viajaba en `$site` y la sección no lo usaba; §3.quinquies.4
 *     lo dio por decidido y no hay que volver a discutirlo.
 *  3. **«Parking gratis 2h» NO está.** Es un dato de negocio escrito en el código y no en el
 *     panel, y su retirada es `[DECIDIDO owner]` (§3.quinquies.5·2).
 *
 * ⚠️ Todo se asevera sobre el subárbol de `#info`, no sobre la página: la portada dice «Visítanos»
 * también en el menú y en el pie, así que un `assertSee` sobre el documento entero pasaría en
 * verde con la sección BORRADA — que es exactamente cómo `#295` se quedó con una guarda vacía.
 */
class VisitSectionTest extends TestCase
{
    use RefreshDatabase;

    /** El subárbol de `<section id="info">`, aislado del resto del documento. */
    private function seccion(): string
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString(
            '<section id="info"',
            $html,
            'La portada ya no trae `<section id="info">`: esta guarda se quedó sin sujeto.',
        );

        // Hasta el cierre de sección: `#info` no anida otra `</section>`.
        preg_match('#<section id="info".*?</section>#s', $html, $m);

        return $m[0];
    }

    public function test_la_seccion_tiene_un_solo_encabezado(): void
    {
        $seccion = $this->seccion();

        preg_match_all('/<h([1-6])[\s>]/i', $seccion, $encabezados);

        $this->assertCount(
            1,
            $encabezados[0],
            'La sección volvió a tener más de un encabezado: '.implode(', ', $encabezados[0]).
            '. El molde editorial de `#297` se rehace metiendo un `h3` «Horarios» aquí dentro.',
        );
        $this->assertSame('2', $encabezados[1][0], 'El encabezado de sección tiene que ser un `h2`.');
    }

    public function test_las_tres_tarjetas_se_pintan(): void
    {
        $seccion = $this->seccion();

        // ⚠️ Se cuenta la clase BASE, que llevan las TRES (la del mapa es `visit-card
        // visit-card--map`). El `(?![-\w])` es lo que impide contar de más si alguien añade un
        // `visit-cardX`; no excluye a la del mapa, y no debe — es una tarjeta más.
        preg_match_all('/class="visit-card(?![-\w])/', $seccion, $tarjetas);

        $this->assertCount(3, $tarjetas[0], 'La sección tiene que pintar TRES tarjetas: cuándo, dónde y el mapa.');
        $this->assertStringContainsString('visit-card--map', $seccion, 'Falta la tarjeta del mapa.');
        // Y ninguna finge ser pulsable: la pegatina de `#303` responde al puntero porque lleva un
        // CTA dentro; éstas no llevan a ninguna parte (`#295`: afordancia sin consumidor).
        $this->assertStringNotContainsString('cursor:pointer', $seccion);
    }

    public function test_el_telefono_configurado_entra_en_la_seccion(): void
    {
        Setting::updateOrCreate(['key' => 'contact.phone'], ['value' => '+34 641 99 57 14', 'group' => 'contact']);

        $seccion = $this->seccion();

        $this->assertStringContainsString('tel:+34641995714', $seccion);
        $this->assertStringContainsString('+34 641 99 57 14', $seccion);
    }

    public function test_sin_telefono_configurado_la_seccion_no_lo_finge(): void
    {
        Setting::updateOrCreate(['key' => 'contact.phone'], ['value' => '', 'group' => 'contact']);

        $this->assertStringNotContainsString('tel:', $this->seccion());
    }

    public function test_la_seccion_ya_no_dice_parking_gratis(): void
    {
        // El texto sigue existiendo en `lang/*/landing.php` (retirarlo es otra decisión), pero
        // ninguna vista pública puede volver a pintarlo: es un dato de negocio que el panel no
        // gobierna. La aserción va contra el TEXTO renderizado, no contra la clave.
        $this->assertStringNotContainsString(
            (string) __('landing.info.parking'),
            $this->seccion(),
        );
    }

    public function test_estando_abierto_la_seccion_dice_hasta_que_hora(): void
    {
        // Ventana que cubre con holgura la hora del reloj de test, para que el caso no dependa de
        // cuándo corre la suite. `HeroStatus` resuelve el cierre con la MISMA fuente que las
        // reservas, y `closes_at` es el campo que `#297` echó en falta.
        OpeningHour::query()->delete();
        foreach (range(0, 6) as $dia) {
            OpeningHour::create([
                'weekday' => $dia,
                'open_time' => '00:00:00',
                'close_time' => '23:59:00',
                'is_closed' => false,
            ]);
        }

        $seccion = $this->seccion();

        $this->assertStringContainsString(
            (string) __('landing.info.until', ['time' => '23:59']),
            $seccion,
            'Con el parque abierto, la sección tiene que decir hasta qué hora.',
        );
    }
}
