<?php

namespace Tests\Feature\Landing;

use App\Domain\Booking\Models\TicketType;
use App\Domain\Platform\Services\Money;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **SECCIÓN 04 · «CUMPLEAÑOS» · LOS DOS PACKS** (carril de diseño Fase 2 · T2e, `DECISIONES #483`).
 * Artboard `Cumpleanos PJP` **7b** (móvil) + `Escritorio PJP` **5a** (escritorio).
 *
 * ▶ **Esta guarda es la red de la sección NUEVA.** La banda heredada —polaroid, selector de packs,
 * bloque compacto y paso a paso— salió de la portada y **sigue entera en `/cumpleanos`**, con sus
 * guardas re-apuntadas allí (`LandingAddonsTest`, `PublicPagesTest`, `TouchTargetTest`).
 *
 * ⚠️⚠️ Lo que se vigila aquí NO es el aspecto —eso lo midió la sonda (packs 544×377 en la misma
 * fila, reloj a dos columnas, foto 21:9) y lo mira el owner—: son las cosas que se romperían **en
 * silencio**, con la página cargando y la suite en verde.
 */
class PartySectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LandingContentSeeder::class);
        app()->setLocale('es');
    }

    /** El recorte de la sección 04, del `<section id="events">` a su cierre. */
    private function seccion(): string
    {
        preg_match('#<section id="events".*?</section>#s', (string) $this->get('/')->assertOk()->getContent(), $m);

        return $m[0] ?? '';
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Guarda de la guarda
    // ─────────────────────────────────────────────────────────────────────────────────

    public function test_the_probe_frames_a_real_section(): void
    {
        $seccion = $this->seccion();

        $this->assertGreaterThan(1500, strlen($seccion), 'el recorte de la sección 04 es sospechosamente corto');
        $this->assertStringContainsString('party-card', $seccion, 'no hay ni una tarjeta de pack dentro');
        $this->assertStringNotContainsString('bd-pack', $seccion, 'ha vuelto la banda heredada a la portada');
    }

    /**
     * **La etiqueta destacada de un pack sale junto a la chapa de su nombre** (`#585`, `[DECIDIDO
     * owner, 2026-09-13]`): el panel la deja escribir en los packs y la web la pinta donde salga el
     * producto. Sin etiqueta, no hay chip.
     */
    public function test_a_pack_badge_sits_next_to_its_name_chip(): void
    {
        TicketType::ofType(TicketType::TYPE_PACK)->update(['badge' => null]);
        $this->assertStringNotContainsString('party-card__badge', $this->seccion(), 'sin etiqueta no hay chip');

        $pack = TicketType::ofType(TicketType::TYPE_PACK)->where('is_sellable', true)->orderBy('position')->firstOrFail();
        $pack->update(['badge' => ['es' => 'La favorita', 'en' => 'Favourite', 'fr' => 'La préférée']]);

        $seccion = $this->seccion();
        $this->assertSame(1, substr_count($seccion, 'party-card__badge'), 'el chip sale en una tarjeta que no lo lleva');
        $this->assertMatchesRegularExpression(
            '#<span class="party-card__chip">'.preg_quote(e($pack->tr('name')), '#').'</span>\s*<span class="party-card__badge">La favorita</span>#',
            $seccion,
            'la etiqueta no va junto a la chapa del nombre de SU pack',
        );
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Lo que se vigila
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **LA EDAD QUE SE PUBLICA ES LA DEL PACK, NO LA DE SU ZONA.**
     *
     * ❗❗ Es la divergencia que el propio canvas dejó abierta —*«la página dice 4–7 y desde 8; la
     * portada dice la de la zona, 2–6 y desde 7»*— y la resuelve la BD, como `#478` ya decidió para
     * las zonas. ⚠️ Y no es cosmético: `guest_age_min/max` es el campo que **cobra el suplemento de
     * fiesta mixta**, así que publicar otro tramo sería prometer un precio distinto del que se cobra.
     */
    public function test_the_published_age_is_the_packs_own_and_moves_with_it(): void
    {
        $pack = TicketType::birthdaySurfacePacks()->orderBy('position')->firstOrFail();

        /*
         * ⚠️⚠️ **El tramo se SIEMBRA aquí, y no es un atajo: el seeder de test no lo trae.** Medido:
         * los packs de producción declaran 4–7 y 8+, y los del seeder no declaran ninguno — así que
         * sin esto el caso pasaría en VACÍO, que es exactamente cómo nacieron ciegos dos casos de
         * `#295`. ▶ Y de paso deja escrito que la tarjeta **degrada bien**: sin tramo no pinta el
         * titular de edad en vez de inventarse un rango.
         */
        $this->assertStringNotContainsString('party-card__age', $this->seccion(),
            'el pack del seeder ya declara edad: revisa este caso, su premisa ha cambiado');

        $pack->update(['guest_age_min' => 4, 'guest_age_max' => 7]);
        $this->assertStringContainsString('De 4 a 7 años', $this->seccion());

        $pack->update(['guest_age_min' => 3, 'guest_age_max' => 9]);
        $this->assertStringContainsString('De 3 a 9 años', $this->seccion());

        $pack->update(['guest_age_min' => 8, 'guest_age_max' => null]);
        $this->assertStringContainsString('Desde 8 años', $this->seccion());
    }

    /**
     * **LOS PRECIOS SON LOS DEL CATÁLOGO, Y LA ESPECIAL SE PUBLICA ENTERA.**
     *
     * ⚠️ Regla dura del canvas, aplicada a toda la web en `#479`: un recargo no se publica como
     * recargo. Y aquí menos, porque **no es plano** entre los dos packs —+2 en uno y +4 en el otro—,
     * así que el cliente tendría que recordar cuál le toca.
     */
    public function test_the_special_rate_is_published_whole_and_never_as_a_surcharge(): void
    {
        $seccion = $this->seccion();

        foreach (TicketType::birthdaySurfacePacks()->with('prices.rateType')->get() as $pack) {
            $especial = $pack->specialRateSurcharges()[0] ?? null;

            if ($especial === null) {
                continue;
            }

            /*
             * ⚠️ Se escribe con `Money::showcase()`, que es el formateador de ESCAPARATE que usa la
             * sección: «18 €» y no «18,00 €». Un `number_format` aquí haría fallar el caso con el
             * producto sano — y fue justo lo que pasó al escribirlo.
             */
            $entero = Money::showcase($especial['priceCents']);
            $recargo = Money::showcase($especial['surchargeCents']);

            /*
             * ⚠️⚠️ **El espacio es DURO en las dos, y en la NEGATIVA importa más** (`#661`). Con el
             * espacio normal, «+2 €» ya no puede aparecer en la página —el producto lo escribe con
             * U+00A0 desde `Money::showcaseWithSymbol()`—, así que la aserción seguiría verde **sin
             * ejercer nada**: diría «no se publica como recargo» siendo cierta por el motivo
             * equivocado. *Lo que se afirma que NO pasa hay que hacerlo POSIBLE primero.*
             */
            $this->assertStringContainsString($entero."\u{00A0}€ en tarifa especial", $seccion);
            $this->assertStringNotContainsString('+'.$recargo."\u{00A0}€", $seccion,
                'la tarifa especial ha vuelto a publicarse como recargo');
        }
    }

    /**
     * **LA TARJETA ENTERA ES EL ENLACE, Y LLEVA A LA PÁGINA** (`[DECIDIDO owner]`).
     *
     * ⚠️⚠️ **Y va SIN ancla, que es lo medido**: `/cumpleanos` no emite `#cumple-kids` ni
     * `#cumple-jump` —desde `#528` compara los dos packs lado a lado, así que no hay un pack al que
     * llegar—. Inventar un ancla sin destino es el defecto que `#482` fichó de `#478`.
     */
    public function test_each_card_is_a_link_to_the_page_and_carries_no_dead_anchor(): void
    {
        // ⚠️ El atributo de superficie va entre la clase y el `href`, así que el localizador no
        // puede dar por hecho que van pegados: es la trampa de aseverar por SUBCADENA.
        preg_match_all('#<a class="party-card"[^>]*?href="([^"]+)"#s', $this->seccion(), $m);

        $this->assertCount(2, $m[1], 'no hay dos tarjetas-enlace');

        foreach ($m[1] as $href) {
            $this->assertSame(route('cumpleanos'), $href);
            $this->assertStringNotContainsString('#', $href, 'la tarjeta enlaza a un ancla');
        }
    }

    /**
     * **LA SECCIÓN NO VENDE: no abre el cajón.** `[DECIDIDO owner]`: *«en la landing va la promesa,
     * no la lista»*. ⚠️ Su consecuencia está fichada —la portada se queda sin ninguna puerta que
     * abra el cajón posicionado— y por eso este caso existe: si alguien devuelve un `openWith` aquí,
     * que sea una decisión y no un descuido.
     */
    public function test_the_section_does_not_open_the_purchase_drawer(): void
    {
        $this->assertStringNotContainsString('openWith(', $this->seccion());
    }

    /**
     * **LA DURACIÓN ABRE LO QUE INCLUYE CADA PACK, Y EL RELOJ YA NO ESTÁ** (`[DECIDIDO owner,
     * 2026-09-13]`, `#583`). La tarjeta «Las 2 h, a vuestro ritmo» se retiró y la duración pasó a la
     * tarjeta del pack, como primera línea de lo que incluye.
     *
     * ⚠️ Sale del CATÁLOGO (`duration_min`), no del texto que escribe el panel: por eso el control
     * cambia la duración y comprueba que la línea la sigue. Una línea escrita a mano pasaría la
     * primera mitad y fallaría la segunda.
     */
    public function test_the_pack_card_leads_with_its_duration_and_the_clock_is_gone(): void
    {
        TicketType::birthdaySurfacePacks()->update(['duration_min' => 120]);
        $seccion = $this->seccion();

        $this->assertStringNotContainsString('party__clock', $seccion, 'ha vuelto el reloj de las dos horas');
        $this->assertStringContainsString(e(__('landing.events.duration_feature', ['duration' => '2 h'])), $seccion,
            'la tarjeta del pack no dice su duración');

        TicketType::birthdaySurfacePacks()->update(['duration_min' => 90]);

        $this->assertStringContainsString(e(__('landing.events.duration_feature', ['duration' => '1 h 30 min'])), $this->seccion(),
            'la duración de la tarjeta no sale del catálogo: no ha seguido el cambio de `duration_min`');
    }

    /**
     * **LA TARJETA OSCURA DECLARA SU SUPERFICIE, y sin eso se ve MAL sin que nada falle.**
     *
     * ❗❗❗ Defecto REPRODUCIDO al construir la sección: pintar el fondo de una tarjeta **no cambia
     * sus tokens**. Medido en el navegador antes de corregirlo, dentro de la tarjeta de tinta el
     * punto de la viñeta salía en `rgb(16,20,24)` sobre un fondo `rgb(26,31,37)` —**tinta sobre
     * tinta**, contraste ≈ 1,1— y los términos en el gris del PAPEL. La página cargaba, la suite
     * estaba verde y las viñetas no se veían.
     *
     * ▶ Lo arregla el mecanismo de SUPERFICIE del producto (`#192`): `data-surface="ink"` flipa
     * `--fg`, `--fg-mute`, `--line`, `--interactive` y `--money` de golpe. Tras ponerlo, medido: el
     * punto sale en el lima vivo (8,12), los términos en el gris de SU superficie (6,35) y el enlace
     * en cian (6,14) — que son **exactamente los colores que el artboard escribe a mano**.
     */
    public function test_the_dark_card_declares_its_surface(): void
    {
        $seccion = $this->seccion();

        preg_match_all('#<a class="party-card"([^>]*)href=#s', $seccion, $m);

        $this->assertCount(2, $m[1], 'no hay dos tarjetas');
        $this->assertStringNotContainsString('data-surface', $m[1][0],
            'la PRIMERA tarjeta declara superficie de tinta: el artboard alterna papel → tinta');
        $this->assertStringContainsString('data-surface="ink"', $m[1][1],
            "la segunda tarjeta ha dejado de declarar su superficie.\n".
            "▶ Pintarle el fondo NO le cambia los tokens: la viñeta se queda en tinta sobre tinta y\n".
            '  el gris de los términos, en el del papel. No falla nada; simplemente no se ve.');

        /*
         * ❗❗❗ **Y EL ATRIBUTO VA EN LA TARJETA, NUNCA EN SU `<li>`.** El owner vio un «recuadro sin
         * radio» detrás de la tarjeta de Jump y era esto: `[data-surface]` no solo declara la
         * superficie, **la PINTA** (`background: var(--bg)`), así que el contenedor se volvía un
         * rectángulo de tinta pura **con radio 0** y la misma caja que la tarjeta, asomando por las
         * cuatro esquinas redondeadas. *Declarar una superficie no es solo cambiar tokens: es pintar.*
         */
        $this->assertDoesNotMatchRegularExpression(
            '#<li class="party__pack-item"[^>]*data-surface#', $seccion,
            "el `<li>` vuelve a declarar la superficie, y eso PINTA un rectángulo sin radio detrás\n".
            '  de la tarjeta. El atributo va en la tarjeta, que es la que tiene el canto redondeado.'
        );
    }

    /** Sin packs vendibles no hay sección: ni cabecera, ni reloj, ni complementos. */
    public function test_without_packs_the_section_disappears(): void
    {
        $this->assertNotSame('', $this->seccion(), 'el caso nace sin sujeto');

        TicketType::birthdaySurfacePacks()->update(['is_active' => false]);

        $this->assertSame('', $this->seccion());
    }
}
