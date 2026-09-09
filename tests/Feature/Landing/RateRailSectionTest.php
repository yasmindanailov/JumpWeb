<?php

namespace Tests\Feature\Landing;

use App\Domain\Booking\Models\ProductAddon;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Content\Services\LandingAddonPresenter;
use App\Domain\Platform\Services\Money;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\ReadsSiteStylesheets;
use Tests\TestCase;

/**
 * **SECCIÓN 02 DE LA PORTADA · «CUÁNTO»** (`docs/specs/rediseno-desde-canvas.md` §5.4 · T2c ·
 * `DECISIONES #479`).
 *
 * Artboards: `Precios PJP` 6a (móvil) y `Escritorio PJP` 2a (escritorio, aprobada el 8 sep).
 *
 * ⚠️⚠️ **Lo que esta guarda NO puede ver, y por eso no lo intenta**: si la sección se PARECE al
 * mockup. Eso se mide con `scripts/comparar-con-mockup.mjs` y con la sonda de geometría, que es la
 * lección que `#478` dejó escrita — *«idéntico al mockup» no se comprueba mirando, y tampoco desde
 * PHP*. Aquí se vigilan las propiedades que sobreviven a un rediseño: de dónde sale cada palabra,
 * qué distingue una frase de otra y qué piezas no pueden compartirse.
 */
class RateRailSectionTest extends TestCase
{
    use ReadsSiteStylesheets;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LandingContentSeeder::class);
    }

    /** El marcado de la sección, acotado a su `<section>`. */
    private function seccion(): string
    {
        $html = (string) $this->get('/')->assertOk()->getContent();

        preg_match('#<section id="pricing".*?</section>#s', $html, $m);

        $this->assertNotEmpty(
            $m,
            'La sección de tarifas perdió su `id`. ⚠️ Sin este localizador TODOS los casos de abajo '.
            'mirarían la página entera, que es donde este repo ya se ha equivocado cuatro veces.',
        );

        return $m[0];
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Guarda de la guarda
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **El localizador acota de verdad, y acota a ESTA sección.**
     *
     * ⚠️ Es el caso que `#314` pagó: un recorte «desde `id=X` hasta `id=Y`» se comía media portada
     * cuando alguien reordenaba las secciones. Aquí se comprueba que dentro hay lo de tarifas y
     * **no** lo de la sección vecina.
     */
    public function test_the_locator_frames_this_section_only(): void
    {
        $seccion = $this->seccion();

        $this->assertStringContainsString('rates__rail', $seccion);
        $this->assertStringNotContainsString('zone-cards', $seccion, 'se ha colado la sección «Para quién».');
        $this->assertStringNotContainsString('id="rides-section"', $seccion);
        $this->assertLessThan(
            60_000, strlen($seccion),
            'la sección mide sospechosamente mucho: el localizador se está comiendo lo que viene detrás.',
        );
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  El molde del canvas
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **La cabecera es rótulo · titular · entradilla, y el rótulo NO repite al titular.**
     *
     * ⚠️ El rótulo vuelve tras `#303` porque el canvas lo trae, pero lo que aquella decisión
     * diagnosticó sigue valiendo: en cuatro de siete secciones la etiqueta **repetía una palabra del
     * titular**. Esto es lo que impide que vuelva ese defecto con el rótulo nuevo puesto.
     */
    public function test_the_header_follows_the_canvas_and_the_eyebrow_does_not_echo_the_headline(): void
    {
        $seccion = $this->seccion();

        foreach (['sec-head__eyebrow', 'sec-head__title', 'sec-head__lede'] as $pieza) {
            $this->assertStringContainsString($pieza, $seccion, "falta la pieza `{$pieza}` de la cabecera.");
        }

        $rotulo = mb_strtolower(__('landing.rates.eyebrow'));
        $titular = mb_strtolower(__('landing.rates.title'));

        $this->assertStringNotContainsString(
            $rotulo, $titular,
            'el rótulo repite una palabra del titular: es el molde que `#303` retiró, con otro nombre.',
        );
    }

    /**
     * **La entradilla vende con una cifra del CATÁLOGO, y calla cuando no hay ninguna.**
     *
     * ⚠️ `null` no es un fallo: es la otra rama. Una entradilla que prometa un «desde» sin catálogo
     * detrás anuncia un precio que no existe.
     */
    public function test_the_lede_quotes_the_cheapest_price_and_falls_back_without_one(): void
    {
        $barato = TicketType::ofType(TicketType::TYPE_ENTRY)->where('is_active', true)->get()
            ->min(fn (TicketType $t): int => $t->displayPriceCents());

        $this->assertStringContainsString(
            Money::showcase((int) $barato), $this->seccion(),
            'la entradilla no dice el precio más barato del catálogo.',
        );

        // Sin entradas: la variante SIN precio, y la sección sigue existiendo.
        TicketType::ofType(TicketType::TYPE_ENTRY)->update(['is_active' => false]);

        $seccion = $this->seccion();
        $this->assertStringContainsString(__('landing.rates.intro_plain'), $seccion);
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Todo sale de la base de datos
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **Cada palabra de una tarjeta viene del catálogo.**
     *
     * Nombre, minutos, unidad y chip: si alguno se escribiera en la plantilla, cambiarlo en el panel
     * no cambiaría la web — que es la definición de dato quemado.
     */
    public function test_every_word_of_a_card_comes_from_the_catalogue(): void
    {
        $entrada = TicketType::ofType(TicketType::TYPE_ENTRY)->where('is_active', true)
            ->orderBy('position')->firstOrFail();

        $seccion = $this->seccion();

        $this->assertStringContainsString(e($entrada->tr('period_label')), $seccion);

        // El nombre, ya sin el prefijo de la zona: ver `nameAndNuance()`.
        $limpio = preg_replace('/^'.preg_quote($entrada->zone->tr('name'), '/').' · /iu', '', (string) $entrada->tr('name'));
        $this->assertStringContainsString(e($limpio), $seccion);
    }

    /**
     * ❗❗❗ **EL NOMBRE MANDA Y LA ZONA SE DICE UNA VEZ, EN EL BOTÓN** (`Precios PJP` 9a y 10a).
     *
     * ⚠️⚠️ **La cadena de esta instalación es `{ZONA} · {nombre}` y la del mockup `{nombre} ·
     * {matiz}`**, o sea al revés. Aplicar su `split` tal cual daría nombre «Jump» y matiz «1 hora».
     * Por eso primero se retira el prefijo **cuando es exactamente el nombre de la zona** —una
     * comprobación, no una adivinanza— y solo después se parte. Este caso fija las dos mitades: que
     * el nombre sale limpio y que la zona aparece en el rótulo de comprar.
     */
    public function test_the_name_drops_the_zone_and_the_button_carries_it(): void
    {
        $seccion = $this->seccion();
        $zona = Zone::where('slug', 'jump')->firstOrFail();

        // El nombre de la tarjeta NO repite la zona…
        $this->assertStringNotContainsString(
            '<span class="rate-card__name">'.e($zona->tr('name')).' · ', $seccion,
            'el nombre de la tarjeta repite la zona que ya dice la pestaña.',
        );

        // …y el botón sí la lleva, junto al nombre.
        $this->assertStringContainsString(
            e(__('landing.rates.book_in', ['name' => '1 hora', 'zone' => $zona->tr('name')])), $seccion,
        );
    }

    /**
     * **El MATIZ solo se pinta cuando añade un dato.**
     *
     * ⚠️ Es la regla del propio artboard (turno 8): «120 min» es «2 horas» dicho otra vez en otra
     * unidad y no se pinta; «sin límite» sí. Sin ella la tarjeta repite su propia duración al lado
     * del nombre, que es exactamente el ruido que el rediseño vino a quitar.
     */
    public function test_the_nuance_is_only_painted_when_it_adds_something(): void
    {
        $entrada = TicketType::ofType(TicketType::TYPE_ENTRY)->where('is_active', true)
            ->orderBy('position')->firstOrFail();

        // Un matiz que repite la duración: no se pinta.
        $entrada->forceFill(['name' => ['es' => $entrada->zone->tr('name').' · 1 hora · 60 min']])->save();
        $this->assertStringNotContainsString('rate-card__nuance', $this->seccion());

        // Uno que añade: sí.
        $entrada->forceFill(['name' => ['es' => $entrada->zone->tr('name').' · Todo el día · sin límite']])->save();
        $seccion = $this->seccion();
        $this->assertStringContainsString('rate-card__nuance', $seccion);
        $this->assertStringContainsString('sin límite', $seccion);
    }

    /**
     * ❗❗ **LOS COMPLEMENTOS SON PÍLDORAS Y SALEN DEL MISMO PRESENTADOR.**
     *
     * ⚠️ La forma cambia (`Precios PJP` 10a: cápsula con «+ nombre · precio»), **la fuente no**:
     * `LandingAddonPresenter` sigue decidiendo qué admite una entrada. Un segundo camino aquí
     * ofrecería en la landing algo que el embudo rechaza — el invariante que ese presentador
     * declara en su propio docblock.
     */
    public function test_the_addons_are_pills_from_the_shared_presenter(): void
    {
        $entrada = TicketType::ofType(TicketType::TYPE_ENTRY)->where('is_active', true)
            ->orderBy('position')->firstOrFail();

        $filas = LandingAddonPresenter::rows($entrada, false);
        $this->assertNotEmpty($filas, 'la entrada de referencia no admite complementos: este caso miraría el vacío.');

        $seccion = $this->seccion();

        $this->assertStringContainsString('addon-pill', $seccion);
        $this->assertStringContainsString(e($filas[0]['name']), $seccion);
        // ⚠️ Y NO la lista de la tarjeta antigua, que sigue viva en `/precios` y en cumpleaños.
        $this->assertStringNotContainsString('addons-mini', $seccion);

        // La píldora lleva la unidad del PRODUCTO junto al complemento suelto.
        $this->assertMatchesRegularExpression(
            '/addon-pill__u">'.preg_quote(e((string) $entrada->tr('period_label')), '/').'/', $seccion,
        );
    }

    /**
     * ❗❗ **UN COMPLEMENTO POR INVITADO NO LLEVA ENCIMA LA UNIDAD DE LA ENTRADA.**
     *
     * ⚠️⚠️ El artboard pone la unidad del producto al lado de cada píldora, y con un complemento que
     * se cuenta **por invitado** eso afirma DOS unidades para el mismo precio: su propia nota ya
     * dice «/invitado». *Una unidad de más no se lee como ruido: se lee como otro precio.*
     * ▶ Lo encontró el arnés de mutación —quitar la condición salía VERDE—, no una relectura: el
     * fixture no tiene ningún complemento por invitado en una entrada, así que la rama existía sin
     * que ningún caso la ejercitara.
     */
    public function test_a_per_guest_addon_does_not_borrow_the_ticket_unit(): void
    {
        $entrada = TicketType::ofType(TicketType::TYPE_ENTRY)->where('is_active', true)
            ->orderBy('position')->firstOrFail();

        $this->assertNotEmpty($entrada->addons, 'la entrada de referencia no admite complementos: este caso miraría el vacío.');

        // Control: por unidad, la píldora SÍ toma la unidad de la entrada.
        $this->assertStringContainsString('addon-pill__u', $this->seccion());

        /*
         * ⚠️⚠️ **TODOS los enganches de TODAS las entradas, y no el de la primera.** La sección pinta
         * la zona que abre la sección, que no tiene por qué ser la de la entrada con `position` más
         * baja: con un solo pivote convertido el caso encontraba la unidad en la píldora de otra
         * tarjeta y **fallaba con el producto sano**. Es un falso negativo del instrumento, no del
         * producto — y el tipo de error que hace desconfiar de una guarda buena.
         */
        DB::table('product_addons')
            ->update(['quantity_mode' => ProductAddon::MODE_PER_GUEST]);

        preg_match_all('#<span class="addon-pill">.*?</span>\s*</span>#s', $this->seccion(), $m);
        $this->assertNotEmpty($m[0], 'no queda ninguna píldora: el caso miraría el vacío.');

        foreach ($m[0] as $pildora) {
            $this->assertStringNotContainsString(
                'addon-pill__u', $pildora,
                'un complemento por invitado lleva encima la unidad de la entrada: son dos unidades para un precio.',
            );
        }
    }

    /**
     * **LA CHAPA DE ZONA NO ESTÁ, y este caso lo fija.**
     *
     * `[owner, 2026-09-09]`: *«ese card negro lo quitamos, no es necesario»* — y su propio artboard
     * la tiene detrás de un interruptor apagado por el recorte de presupuesto del 7 sep.
     * ⚠️ Se vigila porque **volvería sola**: la edad, la altura y los calcetines son datos que esta
     * sección tiene a mano, y la tentación de recordarlos aquí es justo lo que infló la sección de
     * 668 a 947 px. Donde el visitante los busca es en «Para quién» y en Normas.
     */
    public function test_the_zone_plate_is_not_here(): void
    {
        $seccion = $this->seccion();

        $this->assertStringNotContainsString('zone-plate', $seccion);
        $this->assertStringNotContainsString(__('landing.rules.socks_title'), $seccion);
    }

    /**
     * ❗❗❗ **UNA ENTRADA SIN TARIFA ESPECIAL DICE «SOLO», y ésa es la mitad honesta de la sección.**
     *
     * ⚠️⚠️ No es un matiz de estilo: verificado contra el dominio, `RateResolver::priceCents()`
     * devuelve **`null`** un sábado para una entrada sin precio especial — o sea que ese día **no se
     * vende**, no es que cueste lo mismo. Escribir la misma frase en los dos casos publicaría un
     * precio para un día en el que no se puede comprar.
     */
    public function test_an_entry_without_a_special_price_says_only(): void
    {
        // ⚠️⚠️ **El sujeto se CONSTRUYE.** El fixture siembra las cinco entradas con sus dos tarifas,
        //    así que sin esto el caso mediría un mundo donde la rama de «solo» no existe — y pasaría
        //    en verde sin haberla ejercitado nunca.
        $sinEspecial = TicketType::ofType(TicketType::TYPE_ENTRY)->where('is_active', true)
            ->orderBy('position')->firstOrFail();
        $especialId = RateType::where('is_special', true)->value('id');
        $sinEspecial->prices()->where('rate_type_id', $especialId)->delete();

        $conEspecial = TicketType::ofType(TicketType::TYPE_ENTRY)->where('is_active', true)
            ->where('id', '!=', $sinEspecial->id)->get()
            ->first(fn (TicketType $t): bool => $t->specialRateSurcharges() !== []);

        $this->assertNotNull($conEspecial, 'no queda ninguna entrada CON tarifa especial: falta el control.');

        // ⚠️ Los días se fijan AQUÍ y no se dan por sabidos: el fixture siembra la especial en
        //    sábado y domingo y la instalación real en viernes, sábado y domingo. *Un dato presente
        //    en tu base no es un dato que exista* — la frase esperada se deriva del estado que este
        //    caso deja puesto.
        RateType::where('is_special', true)->update(['weekdays' => [5, 6, 0]]);
        RateType::forgetSpecialMemo();

        $seccion = $this->seccion();

        $dias = __('landing.rates.days_range', ['from' => 'lunes', 'to' => 'jueves']);
        $solo = __('landing.rates.days_only', ['days' => $dias]);

        $this->assertStringContainsString($solo, $seccion, 'la entrada que no se vende el finde no lo dice.');
        $this->assertStringContainsString($dias, $seccion);
    }

    /**
     * ❗❗ **LOS DÍAS SE DERIVAN DE `rate_types.weekdays`, NO SE ESCRIBEN.**
     *
     * ⚠️ Es lo que hace que el producto sirva para otra instalación: con otra configuración de días
     * la frase tiene que cambiar sola. Con la frase escrita a mano sería cierta aquí y falsa en el
     * siguiente cliente **sin que nada fallara**.
     */
    public function test_the_plain_days_are_derived_from_the_special_rate_configuration(): void
    {
        // Viernes, sábado y domingo especiales → lo que sobra es de lunes a jueves.
        RateType::where('is_special', true)->update(['weekdays' => [5, 6, 0]]);
        RateType::forgetSpecialMemo();

        $this->assertStringContainsString(
            __('landing.rates.days_range', ['from' => 'lunes', 'to' => 'jueves']), $this->seccion(),
        );

        // La especial pasa a cubrir SOLO el domingo → lo que sobra es de lunes a sábado.
        RateType::where('is_special', true)->update(['weekdays' => [0]]);
        RateType::forgetSpecialMemo();

        $this->assertStringContainsString(
            __('landing.rates.days_range', ['from' => 'lunes', 'to' => 'sábado']), $this->seccion(),
            'la frase de días no siguió a la configuración: está escrita en algún sitio.',
        );
    }

    /**
     * ❗❗ **UN CONJUNTO DE DÍAS QUE NO ES CONTIGUO SE ENUMERA, NUNCA SE ESCRIBE COMO RANGO.**
     *
     * ⚠️⚠️ Con la especial solo el miércoles, lo que sobra es lunes, martes, jueves, viernes,
     * sábado y domingo — y «de lunes a domingo» sería **literalmente falso**: incluiría el miércoles,
     * que es justo el día en que esa tarifa no rige. Un rango sobre un conjunto con agujeros no es
     * una simplificación: es otra afirmación.
     * ▶ Lo pidió el arnés de mutación: forzar `$contiguos = true` salía verde porque ningún caso
     * ejercitaba un conjunto roto.
     */
    public function test_a_non_contiguous_set_of_days_is_listed_and_not_written_as_a_range(): void
    {
        RateType::where('is_special', true)->update(['weekdays' => [3]]);
        RateType::forgetSpecialMemo();

        $seccion = $this->seccion();

        $this->assertStringContainsString('lunes, martes, jueves', $seccion);
        $this->assertStringNotContainsString(
            __('landing.rates.days_range', ['from' => 'lunes', 'to' => 'domingo']), $seccion,
            'un conjunto con agujeros se ha escrito como rango: la frase incluye el día que la especial reclama.',
        );
    }

    /**
     * **Sin días que sobren, no se escribe ninguna frase.**
     *
     * ⚠️ Es el borde donde una derivación mal hecha inventa texto: si la tarifa especial cubriera la
     * semana entera, «de lunes a domingo» sería literalmente falso para la normal.
     */
    public function test_no_day_phrase_when_the_special_rate_covers_the_whole_week(): void
    {
        RateType::where('is_special', true)->update(['weekdays' => [0, 1, 2, 3, 4, 5, 6]]);
        RateType::forgetSpecialMemo();

        $this->assertStringNotContainsString('rate-card__days', $this->seccion());
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  El foco y la destacada
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * ❗❗ **LA DESTACADA LA DICE EL CATÁLOGO (`featured`), y sin ninguna el carril abre por la
     * primera.**
     *
     * ⚠️ **Destacar y rotular son dos cosas.** `featured` decide quién manda —ancho mayor y foco de
     * salida— y `badge` escribe el chip. Fundirlas obligaría a inventar el texto del chip en el
     * código en vez de leerlo del panel, y haría que marcar una destacada cambiara lo que dice.
     */
    public function test_the_catalogue_decides_which_card_opens_the_rail(): void
    {
        // El fixture marca destacadas las entradas de 120 min, así que la sección las señala…
        $destacada = TicketType::ofType(TicketType::TYPE_ENTRY)->where('is_active', true)
            ->where('featured', true)->orderBy('position')->firstOrFail();

        $seccion = $this->seccion();
        $this->assertStringContainsString('data-featured', $seccion);
        // …y el carril de su zona abre por ELLA: su índice viaja al componente de foco.
        // ⚠️ `@js()` escapa las comillas como `\u0022`, no como `&quot;`: aseverar la entidad HTML
        //    daba un caso que no encontraba nada y **fallaba con el producto sano**.
        $this->assertMatchesRegularExpression('/'.$destacada->zone->slug.'\\\\u0022:[1-9]/', $seccion);

        // ⚠️ **Y el CONTROL, que es la mitad que de verdad prueba de dónde sale**: sin ninguna
        //    destacada en el catálogo no hay ninguna en la página, y el carril abre por la primera.
        TicketType::ofType(TicketType::TYPE_ENTRY)->update(['featured' => false]);

        $seccion = $this->seccion();
        $this->assertStringNotContainsString('data-featured', $seccion);
        $this->assertMatchesRegularExpression('/'.$destacada->zone->slug.'\\\\u0022:0/', $seccion);
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Lo que no se puede compartir
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * ❗❗❗ **LA PESTAÑA DE ESTA SECCIÓN NO ES `.zone-tab`, Y ES UNA FRONTERA DE FASE.**
     *
     * `.zone-tab` la comparten `/servicios` y **el cajón de compra en Vue** (`IdentifyStep`,
     * `AuthTabs`), que es Fase 4. Reutilizarla aquí habría cambiado el aspecto del embudo desde una
     * tanda de la portada — y eso no lo ve ninguna captura de la landing.
     */
    public function test_the_section_does_not_reuse_the_tab_of_the_purchase_drawer(): void
    {
        $seccion = $this->seccion();

        $this->assertStringContainsString('tabset__tab', $seccion);
        $this->assertStringNotContainsString('zone-tab', $seccion);
    }

    /**
     * ❗❗❗ **NINGUNA PIEZA DE ESTA SECCIÓN SE PINTA CON EL COLOR DE UNA ZONA.**
     *
     * Es la **grieta 01** que el propio canvas nos reportó al auditar el cajón: el botón que avanza
     * la compra se pintaba con `var(--zone-1)`, o sea con la paleta de una zona, que llega desde los
     * DATOS. Aquí hay dos zonas, dos pestañas y una chapa con el nombre de la zona dentro — es
     * exactamente el sitio donde la tentación de teñir con la paleta es mayor.
     */
    public function test_no_piece_of_the_section_is_painted_with_a_zone_colour(): void
    {
        // ⚠️ `siteSheets()` devuelve un MAPA hoja → contenido, no una cadena: concatenarlo es lo que
        //    permite mirar la familia esté donde esté declarada.
        $css = implode("\n", $this->siteSheets());

        foreach (['.tabset', '.rate-card', '.rates__', '.addon-pill'] as $familia) {
            preg_match_all('/'.preg_quote($familia, '/').'[^{}]*\{([^{}]*)\}/', $css, $m);

            $this->assertNotEmpty($m[1], "no hay ninguna regla de `{$familia}`: este caso miraría el vacío.");

            foreach ($m[1] as $cuerpo) {
                $this->assertStringNotContainsString(
                    '--zone-', $cuerpo,
                    "una regla de `{$familia}` usa la paleta de una zona.\n".
                    '▶ Es la grieta 01: el color de un DATO decidiendo el aspecto de un componente.',
                );
            }
        }
    }
}
