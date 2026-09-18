<?php

namespace Tests\Feature\Landing;

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Content\Services\LandingAddonPresenter;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\Money;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    /**
     * El marcado del panel de UNA zona.
     *
     * ⚠️⚠️ **Se recorta hasta el panel SIGUIENTE, no con un `.*?</div>\s*</div>`.** El recorte no
     * codicioso para en el primer par de cierres que encuentre, y cuántos hay dentro depende de si
     * la tarjeta pinta complementos, matiz o tarifa especial — o sea **del dato**. Con el fixture
     * corto capturaba una sola tarjeta y una guarda que contaba chips **pasaba en verde con dos
     * pintados**. Lo cazó el arnés de mutación, no una relectura.
     */
    private function panel(string $slug): string
    {
        $html = (string) $this->get('/')->assertOk()->getContent();

        $ini = strpos($html, 'id="rate-panel-'.$slug.'"');
        $this->assertNotFalse($ini, "no existe el panel de la zona `{$slug}`: este caso miraría el vacío.");

        /*
         * ⚠️⚠️ **EL RECORTE TERMINA EN LA SECCIÓN, NO «AL LLEGAR AL SIGUIENTE PANEL»**, y esto lo
         * cazó `#483`. Antes, para el ÚLTIMO panel no había «siguiente» y el localizador se llevaba
         * **el resto del documento**: al nacer un segundo bloque de complementos más abajo —el de
         * cumpleaños—, este caso contó 6 fichas donde su panel pinta 2.
         * ▶ Es la lección de `#314` en otra puerta: *un localizador que depende de qué viene DESPUÉS
         * no acota una sección, acota un tramo de página*. El cierre de `<section>` sí es suyo.
         */
        $siguiente = strpos($html, 'id="rate-panel-', $ini + 10);
        $cierre = strpos($html, '</section>', $ini);

        $fin = min(array_filter([$siguiente, $cierre], static fn ($n): bool => $n !== false) ?: [strlen($html)]);

        return substr($html, $ini, $fin - $ini);
    }

    /**
     * Dos entradas de la MISMA zona, la primera de las cuales hará de unidad.
     *
     * ⚠️ Se apagan las demás para que la zona quede con exactamente dos: el ahorro se mide contra la
     * entrada de MENOR duración de su zona, y con más productos alrededor el sujeto del caso
     * dependería de cuál sembró el fixture.
     *
     * @return array{0: TicketType, 1: TicketType}
     */
    private function dosEntradasDeUnaZona(): array
    {
        $zonaId = Zone::where('slug', 'kids')->value('id');

        $entradas = TicketType::ofType(TicketType::TYPE_ENTRY)->where('is_active', true)
            ->where('zone_id', $zonaId)->orderBy('position')->get();

        $this->assertGreaterThan(1, $entradas->count(), 'la zona no tiene dos entradas: el caso miraría el vacío.');

        $entradas->skip(2)->each(fn (TicketType $t) => $t->update(['is_active' => false]));

        return [$entradas[0], $entradas[1]];
    }

    /**
     * Los importes de las fichas de complemento, de las dos zonas.
     *
     * ⚠️ Acotar a este elemento es lo que hace fiable la aserción del «desde»: la palabra vive
     * también en la entradilla de la sección y en el sello de las tarjetas de zona.
     *
     * @return list<string>
     */
    /** Deja una entrada con UN solo precio, el de la tarifa normal. */
    private function reprecio(TicketType $ticket, int $cents): void
    {
        $ticket->prices()->delete();
        $ticket->prices()->create([
            'rate_type_id' => RateType::where('is_special', false)->value('id'),
            'amount_cents' => $cents,
            'currency' => 'EUR',
        ]);
        $ticket->load('prices.rateType');
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
     * ❗❗❗ **EL NOMBRE MANDA Y LA ZONA SE DICE UNA VEZ, EN LA CHAPA** (`Precios PJP` 15b).
     *
     * ⚠️⚠️ **La cadena de esta instalación es `{ZONA} · {nombre}` y la del mockup `{nombre} ·
     * {matiz}`**, o sea al revés. Aplicar su `split` tal cual daría nombre «Jump» y matiz «1 hora».
     * Por eso primero se retira el prefijo **cuando es exactamente el nombre de la zona** —una
     * comprobación, no una adivinanza— y solo después se parte. Este caso fija las dos mitades: que
     * el nombre sale limpio y que la zona aparece **una sola vez**, en su chapa.
     *
     * ⚠️⚠️ **La llevó el BOTÓN hasta `#480`** —era el turno 9a, «se dice en el único sitio donde
     * equivocarse cuesta dinero»— y salió de ahí al entrar la chapa por tarjeta: con las dos, la
     * zona se decía **dos veces por tarjeta**. Lo dice la nota del propio 15b: *«con la zona en la
     * chapa, en el botón sobra»*.
     */
    public function test_the_name_drops_the_zone_and_the_chip_carries_it(): void
    {
        $seccion = $this->seccion();
        $zona = Zone::where('slug', 'jump')->firstOrFail();

        // El nombre de la tarjeta NO repite la zona…
        $this->assertStringNotContainsString(
            '<span class="rate-card__name">'.e($zona->tr('name')).' · ', $seccion,
            'el nombre de la tarjeta repite la zona que ya dice la pestaña.',
        );

        // …la chapa sí la lleva…
        $this->assertStringContainsString(
            '<span class="rate-card__zone">'.e(__('landing.rates.zone_chip', ['zone' => $zona->tr('name')])).'</span>',
            $seccion,
        );

        // …y el botón ya NO, o se diría dos veces en la misma tarjeta.
        $this->assertStringContainsString(e(__('landing.rates.book_name', ['name' => '1 hora'])), $seccion);
        $this->assertStringNotContainsString(
            e(__('landing.rates.book_name', ['name' => '1 hora'])).' en ', $seccion,
            'el botón vuelve a decir la zona: con la chapa arriba, se dice dos veces por tarjeta.',
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
     * ❗❗❗ **LOS COMPLEMENTOS NO SE PUBLICAN EN ESTA SECCIÓN** (`[DECIDIDO owner, 2026-09-13]`, `#583`):
     * *«solo los dejamos en el SPA al reservar»*. Vivieron en un carril debajo de las tarjetas
     * (`#480`) y antes dentro de cada tarjeta; se fueron los dos, y con ellos los casos que vigilaban
     * su foco y su deduplicación, que se van con su sujeto (`CONVENCIONES §3.quater`).
     * ⚠️ El caso nace con sujeto: la zona tiene complementos, así que su ausencia es decisión y no vacío.
     */
    public function test_the_section_does_not_publish_addons(): void
    {
        $zona = Zone::where('slug', 'kids')->firstOrFail();
        $entradas = TicketType::ofType(TicketType::TYPE_ENTRY)->where('is_active', true)
            ->where('zone_id', $zona->id)->orderBy('position')->get();
        $this->assertNotEmpty(LandingAddonPresenter::unique($entradas), 'la zona no ofrece complementos: el caso miraría el vacío.');

        $seccion = $this->seccion();

        foreach (['addons-rail', 'addon-card', 'rate-card__addons', 'addons-mini'] as $pieza) {
            $this->assertStringNotContainsString($pieza, $seccion, "la sección vuelve a publicar complementos (`{$pieza}`)");
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
        // ⚠️ Aquí se aseveraba además que la NOTA DE CALCETINES no se repetía en esta sección. Se
        // fue con su sujeto (`#531`): la nota estática desapareció del producto cuando `/precios`
        // pasó a publicar cada complemento con el texto que el panel escribe en él. Lo que la
        // sección no puede hacer —recordar aquí lo que «Para quién» ya dice— lo siguen vigilando la
        // chapa de zona y el censo de fichas del carril.
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
    /**
     * ❗❗❗ **LA ETIQUETA DESTACADA SALE EN TODA TARJETA QUE LA TENGA, LIDERE O NO** (`#585`).
     *
     * `[DECIDIDO owner, 2026-09-13]`: «si un producto tiene badge destacado, lo ponemos en la página
     * web, en todas las páginas que salga». ⚠️ Revierte `#479`, donde el chip era el marcador de la
     * que lidera; la que lidera se sigue diciendo con el ancho y el foco (`data-featured`).
     * ⚠️ Y la otra mitad no cambia: sin `badge` no hay chip, ni siquiera en la que lidera.
     */
    public function test_every_card_with_a_badge_carries_its_chip(): void
    {
        // ⚠️ El reset masivo, ANTES de leer: ver el motivo en `test_two_featured_entries…`.
        TicketType::ofType(TicketType::TYPE_ENTRY)->update(['featured' => false, 'badge' => null]);

        $entradas = TicketType::ofType(TicketType::TYPE_ENTRY)->where('is_active', true)
            ->where('zone_id', Zone::where('slug', 'kids')->value('id'))
            ->orderBy('position')->get();
        $this->assertGreaterThan(1, $entradas->count(), 'hace falta más de una entrada en la zona para distinguir líder de vecina.');

        // La que lidera, SIN etiqueta: no hay chip — no se inventa una palabra que el panel no escribió.
        $entradas->first()->update(['featured' => true]);
        $this->assertStringNotContainsString('rate-card__badge', $this->seccion(), 'una tarjeta sin etiqueta pinta chip.');

        // Una que NO lidera, CON etiqueta: lleva su chip, con SU texto.
        $entradas->get(1)->update(['badge' => ['es' => 'Top', 'en' => 'Top', 'fr' => 'Top']]);

        $seccion = $this->seccion();
        $this->assertStringContainsString('<span class="rate-card__badge">Top</span>', $seccion,
            'la etiqueta de una tarjeta que no lidera no sale: el panel la escribe y no aparece.');
        $this->assertSame(1, substr_count($seccion, 'rate-card__badge'), 'hay un chip en una tarjeta sin etiqueta.');
    }

    /**
     * ❗❗ **SI EL PANEL MARCA DOS DESTACADAS EN LA MISMA ZONA, LIDERA UNA.**
     *
     * ⚠️⚠️ Nada impide marcar dos: `featured` es un booleano por producto. Si cada tarjeta se
     * preguntara «¿soy destacada?», saldrían **dos tarjetas anchas con dos chips** — y con eso el
     * marcador deja de decir cuál coger, que es lo único para lo que existe. Se resuelve por ÍNDICE:
     * manda la primera y las demás son tarjetas normales.
     * ▶ **Es una resolución determinista, no un empate**, y por eso se fija con un caso: el día que
     * alguien vuelva a preguntárselo producto a producto, esto se pone rojo.
     */
    public function test_two_featured_entries_in_one_zone_still_yield_a_single_leader(): void
    {
        /*
         * ❗❗❗ **EL RESET MASIVO VA ANTES DE LEER LOS MODELOS, y no es orden estético.**
         * Un `update()` de Eloquent solo escribe los atributos SUCIOS. Si el modelo se lee primero
         * y después una escritura masiva le cambia la fila por detrás, reponer el MISMO valor que
         * ya tiene en memoria **no lo ensucia y no emite ninguna sentencia**: la fila se queda como
         * la dejó el masivo. Aquí eso dejaba **una sola destacada donde el caso creía marcar dos**,
         * así que el caso pasaba… sin haber montado nunca el escenario que dice montar.
         * ▶ Lo cazó el arnés de mutación —la mutación no mordía—, no una relectura.
         */
        TicketType::ofType(TicketType::TYPE_ENTRY)->update(['featured' => false, 'badge' => null]);

        $entradas = TicketType::ofType(TicketType::TYPE_ENTRY)->where('is_active', true)
            ->where('zone_id', Zone::where('slug', 'kids')->value('id'))
            ->orderBy('position')->get();

        $this->assertGreaterThan(1, $entradas->count(), 'la zona no tiene dos entradas: este caso miraría el vacío.');

        $entradas->take(2)->each(fn (TicketType $t) => $t->update([
            'featured' => true,
            'badge' => ['es' => 'Top', 'en' => 'Top', 'fr' => 'Top'],
        ]));

        $panel = $this->panel('kids');

        // ⚠️ La cifra se DERIVA del catálogo, no se teclea: el fixture tiene cuatro entradas en
        //    kids y la instalación local tres. *Un dato presente en tu base no es un dato que exista.*
        $this->assertSame(
            $entradas->count(), substr_count($panel, 'class="rate-card"'),
            'el localizador no ve todas las tarjetas de la zona: contaría sobre un recorte.',
        );
        $this->assertSame(1, substr_count($panel, 'data-featured'), 'hay dos tarjetas anchas en la misma zona.');
        // ⚠️ `#585`: el chip ya no es del líder, es de toda tarjeta con etiqueta — aquí las dos la llevan.
        $this->assertSame(2, substr_count($panel, 'rate-card__badge'), 'una de las dos tarjetas con etiqueta ha perdido su chip.');
    }

    /**
     * ❗❗ **EL `badge` DE UNA TARJETA QUE NO LIDERA ES SU CHIP, NO EL MATIZ** (`#585`).
     *
     * Hasta `#585` bajaba al matiz para no perderse en silencio. Hoy toda etiqueta tiene su chip, y
     * dejarla también en el matiz la diría dos veces en la misma tarjeta.
     */
    public function test_a_badge_on_a_card_that_does_not_lead_is_its_chip_not_the_nuance(): void
    {
        // ⚠️ El reset masivo, ANTES de leer: ver el motivo en `test_two_featured_entries…`.
        TicketType::ofType(TicketType::TYPE_ENTRY)->update(['featured' => false, 'badge' => null]);

        $entrada = TicketType::ofType(TicketType::TYPE_ENTRY)->where('is_active', true)
            ->orderBy('position')->firstOrFail();

        $entrada->update([
            'name' => ['es' => $entrada->zone->tr('name').' · Ilimitada'],
            'badge' => ['es' => 'Todo el día', 'en' => 'All day', 'fr' => 'Toute la journée'],
        ]);

        $seccion = $this->seccion();

        $this->assertStringContainsString('<span class="rate-card__badge">Todo el día</span>', $seccion);
        $this->assertStringNotContainsString('<span class="rate-card__nuance">Todo el día</span>', $seccion,
            'la etiqueta se dice dos veces: en su chip y en el matiz.');
    }

    /**
     * **El matiz del NOMBRE y la etiqueta destacada son dos cosas y salen las dos** (`#585`).
     *
     * ⚠️ Hasta `#585` competían por el mismo sitio y ganaba el nombre. Hoy el matiz es solo del
     * nombre y la etiqueta tiene su chip, así que una tarjeta con las dos las enseña a la vez.
     */
    public function test_the_name_nuance_and_the_badge_are_both_painted(): void
    {
        // ⚠️ El reset masivo, ANTES de leer: ver el motivo en `test_two_featured_entries…`.
        TicketType::ofType(TicketType::TYPE_ENTRY)->update(['featured' => false, 'badge' => null]);

        $entrada = TicketType::ofType(TicketType::TYPE_ENTRY)->where('is_active', true)
            ->orderBy('position')->firstOrFail();

        $entrada->update([
            'name' => ['es' => $entrada->zone->tr('name').' · Todo el día · sin límite'],
            'badge' => ['es' => 'Top', 'en' => 'Top', 'fr' => 'Top'],
        ]);

        $seccion = $this->seccion();

        $this->assertStringContainsString('<span class="rate-card__nuance">sin límite</span>', $seccion);
        $this->assertStringContainsString('<span class="rate-card__badge">Top</span>', $seccion);
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  El AHORRO · el único argumento de valor de la tarjeta
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * ❗❗❗ **EL MULTIPLICADOR SALE DE LA DURACIÓN, NO DEL ORDEN DE LAS TARJETAS.**
     *
     * ⚠️⚠️ El artboard lo saca del índice —«la segunda vale por dos, la tercera por tres»—, y eso es
     * cierto **solo mientras las tarjetas estén ordenadas por duración creciente y cada escalón sea
     * un múltiplo exacto de la primera**. Aquí la segunda tarjeta dura el TRIPLE, así que por orden
     * diría «dos» y por duración dice «tres»: la frase publicaría una comparación que no es.
     */
    public function test_the_saving_multiplier_comes_from_the_duration_and_not_the_order(): void
    {
        [$unidad, $larga] = $this->dosEntradasDeUnaZona();

        $unidad->update(['duration_min' => 60]);
        $larga->update(['duration_min' => 180]);
        $this->reprecio($unidad, 1000);
        $this->reprecio($larga, 2500);

        $seccion = $this->seccion();

        // 3 × 10,00 − 25,00 = 5,00
        $this->assertStringContainsString('5 €', $seccion);
        $this->assertStringContainsString(
            e(__('landing.rates.saving_base', ['count' => __('landing.rates.times.3'), 'unit' => '1 hora'])),
            $seccion,
            'la frase compara por el ORDEN de las tarjetas y no por su duración.',
        );
    }

    /**
     * ❗❗❗ **UN PRODUCTO SIN DURACIÓN NO PINTA LA LÍNEA, y ésa es la mitad de la decisión.**
     *
     * `[DECIDIDO owner, 2026-09-09]`. «Todo el día» no declara minutos, así que compararlo con tres
     * sueltas es **suponer cuánto se queda el cliente medio**, no medirlo — y si viene dos horas, la
     * frase le promete un ahorro que no tiene. *Cuando el dato no existe, la línea no se escribe.*
     */
    public function test_a_ticket_without_duration_gets_no_saving_line(): void
    {
        [$unidad, $larga] = $this->dosEntradasDeUnaZona();

        $unidad->update(['duration_min' => 60]);
        $this->reprecio($unidad, 1000);
        $this->reprecio($larga, 1500);

        // ⚠️ Se mira el PANEL de la zona y no la sección: el marcado trae también el panel de la
        //    otra zona —oculto, pero presente—, y allí sí hay una entrada que ahorra. *Aseverar
        //    sobre la sección mediría la zona de al lado.*
        // Control: CON duración la línea existe…
        $larga->update(['duration_min' => 120]);
        $this->assertStringContainsString('rate-card__marker', $this->panel('kids'));

        // …y sin ella, no.
        $larga->update(['duration_min' => null]);
        $this->assertStringNotContainsString(
            'rate-card__marker', $this->panel('kids'),
            'se anuncia un ahorro sobre un producto que no declara duración: eso es suponer, no medir.',
        );
    }

    /**
     * **Un ahorro que no existe no se escribe.**
     *
     * ⚠️ Es el borde donde la frase se volvería falsa: si la entrada larga cuesta MÁS que comprar
     * varias cortas, «Ahorras −2 €» sería un anuncio al revés. Medido con el catálogo real: «Todo el
     * día» a 18,00 € contra dos de 8,00 € sale a **−2,00 €**.
     */
    public function test_no_saving_line_when_the_long_ticket_is_not_cheaper(): void
    {
        [$unidad, $larga] = $this->dosEntradasDeUnaZona();

        $unidad->update(['duration_min' => 60]);
        $larga->update(['duration_min' => 120]);
        $this->reprecio($unidad, 800);
        $this->reprecio($larga, 1800);   // 2 × 8 = 16 < 18 → no ahorra nada

        $this->assertStringNotContainsString('rate-card__marker', $this->panel('kids'));
    }

    /**
     * ❗❗ **EL KEYLINE SIGUE SIENDO UNA VARIANTE DE LA FAMILIA — y desde `#540` NO LLEVA BORDE.**
     *
     * ⚠️⚠️ **ESTA ASERCIÓN CAMBIÓ DE PREMISA, no se relajó.** Exigía `border: 2px solid var(--fg)`
     * porque la hoja de componentes del sistema declara DOS rellenos de acción —«Completo», sin
     * borde, y «Keyline», con él— y `#480` eligió el segundo. `[DECIDIDO owner, 2026-09-12]`: el
     * producto se queda con el primero, que era el DEFECTO del propio sistema.
     * ▶ Lo que esta guarda vigila sigue siendo lo mismo y es lo que importa: que el botón tome su
     * piel de la FAMILIA y no de un valor escrito en el selector de la tarjeta, que es justo como
     * nace una divergencia. La clase no se retira: su `:not(.is-focus)` la usa para el fantasma.
     */
    public function test_the_card_button_takes_the_keyline_from_the_family(): void
    {
        $seccion = $this->seccion();

        $this->assertStringContainsString('class="btn btn--keyline rate-card__btn"', $seccion);

        // La variante existe en la hoja, o la clase no pintaría nada.
        $hojas = implode("\n", $this->siteSheets());
        $this->assertMatchesRegularExpression('/\.btn--keyline\s*\{[^}]*border:\s*0/', $hojas);

        // Y el borde no ha vuelto por la puerta de atrás, escrito en la tarjeta.
        $this->assertDoesNotMatchRegularExpression(
            '/\.rate-card__btn\s*\{[^}]*border:\s*\dpx/', $hojas,
            'el borde ha vuelto escrito en el selector de la tarjeta: ahí nadie puede reutilizarlo.',
        );
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

        // ⚠️ `.addon-card` y `.addons-rail` estaban en esta lista y se fueron con su carril (`#583`).
        foreach (['.tabset', '.rate-card', '.rates__'] as $familia) {
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

    // ─────────────────────────────────────────────────────────────────────────────────
    //  La promo: el precio de ANTES tachado y el recuadro (chapuza declarada, 2026-09-18)
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **El precio de antes se tacha SOLO con el ajuste `promo.percent`, y deshace esa rebaja.**
     *
     * `[DECIDIDO owner, 2026-09-18]`: «no quiero spec, ni sistema ni nada». El catálogo guarda el
     * precio ya rebajado (la promo del 17-09 se aplicó como dato) y el «antes» es `nuevo / 0,8`,
     * escrito en `<s>` con la palabra para el lector de pantalla. Sin ajuste, la tarjeta es la de
     * siempre: ni un `<s>` en toda la sección.
     */
    public function test_the_previous_price_is_struck_only_with_the_promo_setting(): void
    {
        [$unidad] = $this->dosEntradasDeUnaZona();
        $this->reprecio($unidad, 960);

        $this->assertStringNotContainsString('rate-card__was', $this->seccion(), 'sin ajuste no hay «antes».');

        Setting::updateOrCreate(['key' => 'promo.percent'], ['value' => '20', 'group' => 'promo']);
        Setting::flushMemo();
        $panel = $this->panel('kids');

        $this->assertMatchesRegularExpression(
            '#<s class="rate-card__was"><span class="sr-only">'.preg_quote(__('landing.rates.was'), '#').' </span>12 €</s>\s*<span class="rate-card__num">9,60</span>#',
            $panel,
            '960 céntimos con una rebaja del 20 % eran 12 €, y van tachados delante de la cifra viva.',
        );
    }

    /**
     * **El «antes» de la tarifa especial acompaña a su cifra, y el ahorro no se mide contra él.**
     *
     * ⚠️ El ahorro sale del catálogo (`saving()`), o sea de los precios REBAJADOS: tachar no lo mueve.
     */
    public function test_the_special_price_is_struck_too_and_the_saving_is_untouched(): void
    {
        Setting::updateOrCreate(['key' => 'promo.percent'], ['value' => '20', 'group' => 'promo']);
        Setting::flushMemo();

        [$unidad, $larga] = $this->dosEntradasDeUnaZona();
        $this->reprecio($unidad, 960);
        $this->reprecio($larga, 1440);
        $larga->prices()->create([
            'rate_type_id' => RateType::where('is_special', true)->value('id'),
            'amount_cents' => 1760,
            'currency' => 'EUR',
        ]);

        $panel = $this->panel('kids');

        $this->assertStringContainsString('rate-card__was--special"><span class="sr-only">'.__('landing.rates.was').' </span>22 €</s>', $panel);
        $this->assertStringContainsString('<span class="rate-card__saving-num">4,80 €</span>', $panel, 'dos de 9,60 menos 14,40: el ahorro sigue saliendo del catálogo.');
    }

    /**
     * **El recuadro de la oferta es el ajuste `promo.banner.{idioma}`, sin respaldo del diccionario.**
     * Vacío, no hay recuadro; puesto, es un `<p role="note">` encima del carril, escapado.
     */
    public function test_the_promo_box_is_the_installation_setting_or_nothing(): void
    {
        $this->assertStringNotContainsString('rates__promo', $this->seccion());

        Setting::updateOrCreate(['key' => 'promo.banner.es'], ['value' => '−20 % en todas las entradas online <b>x</b>', 'group' => 'promo']);
        Setting::flushMemo();

        $seccion = $this->seccion();
        $this->assertStringContainsString('<p class="rates__promo" role="note">−20 % en todas las entradas online &lt;b&gt;x&lt;/b&gt;</p>', $seccion);
        $this->assertLessThan(strpos($seccion, 'class="tabset"'), strpos($seccion, 'rates__promo'), 'el recuadro va ENCIMA de las pestañas.');
    }

    /**
     * **`/precios` tacha con el mismo ajuste y el mismo cálculo**, en su propia línea sobre la cifra.
     */
    public function test_the_pricing_page_strikes_the_previous_price_with_the_same_setting(): void
    {
        [$unidad] = $this->dosEntradasDeUnaZona();
        $this->reprecio($unidad, 960);

        $this->assertStringNotContainsString('rate-table__was', (string) $this->get('/precios')->assertOk()->getContent());

        Setting::updateOrCreate(['key' => 'promo.percent'], ['value' => '20', 'group' => 'promo']);
        Setting::flushMemo();

        $this->assertStringContainsString(
            '<s class="rate-table__was"><span class="sr-only">'.__('landing.rates.was').' </span>12 €</s>',
            (string) $this->get('/precios')->assertOk()->getContent(),
        );
    }
}
