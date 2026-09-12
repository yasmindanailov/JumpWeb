<?php

namespace Tests\Feature\Site;

use App\Domain\Booking\Models\Price;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\SpecialDate;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * **LA PÁGINA `/precios`, REHECHA DESDE SU ARTBOARD** (`DECISIONES #531`, Fase 3 · T3b).
 *
 * ▶ Lo que se vigila **no es el aspecto** —eso lo mide la sonda y lo mira el owner—: son las cosas
 * que se rompen **en silencio**, con la página cargando y la suite en verde. Las tres que más:
 *
 *  1. **Una entrada que no se vende un día dice que no se vende** («—» y «solo …»), y una que sí se
 *     vende al mismo precio **NO lo dice**. Es el defecto que `#531` reprodujo con control.
 *  2. **La hora extra vive en la tabla** —con «—» en la columna donde no tiene precio— y **no** en el
 *     bloque de complementos, donde su excepción habría que escribirla a mano.
 *  3. **Los bloques que alimenta el panel desaparecen con cero filas** (los festivos), que es regla
 *     dura del sistema de diseño.
 */
class PricingPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LandingContentSeeder::class);
        $this->withSession(['locale' => 'es']);
        app()->setLocale('es');
    }

    private function html(): string
    {
        return (string) $this->get('/precios')->assertOk()->getContent();
    }

    /** Las filas de la tabla, en orden de documento, con su texto aplanado. */
    private function rows(string $html): array
    {
        preg_match_all('#<tr class="rate-table__row">(.*?)</tr>#s', $html, $m);

        return array_map(
            fn (string $row): string => trim(preg_replace('/\s+/u', ' ', strip_tags($row))),
            $m[1],
        );
    }

    /** Reescribe el precio de un producto para una tarifa (null = sin precio ese día). */
    private function reprice(TicketType $product, string $rateKey, ?int $cents): void
    {
        $rate = RateType::where('key', $rateKey)->firstOrFail();

        Price::where('priceable_type', $product->getMorphClass())
            ->where('priceable_id', $product->id)
            ->where('rate_type_id', $rate->id)
            ->delete();

        if ($cents !== null) {
            $product->prices()->create(['rate_type_id' => $rate->id, 'amount_cents' => $cents]);
        }
    }

    private function anEntry(): TicketType
    {
        return TicketType::ofType(TicketType::TYPE_ENTRY)->where('is_active', true)
            ->orderBy('position')->firstOrFail();
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  1 · La tabla
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **Una tabla por zona, y sus entradas dentro** — con la zona dicha UNA vez, en su cabecera.
     */
    public function test_each_landing_zone_gets_a_table_with_its_entries(): void
    {
        $html = $this->html();
        $zonas = Zone::where('show_in_landing', true)->orderBy('position')->get();

        $this->assertGreaterThan(0, $zonas->count(), 'sin zonas de landing este caso miraría el vacío');
        $this->assertSame(
            $zonas->count(), substr_count($html, 'class="rate-zone__head"'),
            'la página no pinta una cabecera por zona',
        );

        foreach ($zonas as $zona) {
            $this->assertStringContainsString('id="zone-'.$zona->slug.'"', $html);
        }

        $this->assertNotEmpty($this->rows($html), 'la tabla no tiene filas: todo lo de abajo miraría el vacío');
    }

    /**
     * ❗❗❗ **EL PRECIO DE CADA COLUMNA ES EL DE SU TARIFA, y una raya dice «ese día no se vende».**
     *
     * ⚠️ Es la propiedad que hace honesta la tabla: escribir el precio de referencia en las dos
     * columnas publicaría una cifra para un día en el que no se puede comprar.
     */
    public function test_each_column_shows_the_price_of_its_own_rate(): void
    {
        $entrada = $this->anEntry();
        $this->reprice($entrada, RateType::KEY_NORMAL, 1234);
        $this->reprice($entrada, RateType::KEY_SPECIAL, 4321);

        [$nombre] = explode(' · ', (string) $entrada->tr('name'), 2);
        $nombre = trim(str_replace((string) $entrada->zone?->tr('name'), '', $nombre)) ?: $nombre;

        $fila = collect($this->rows($this->html()))
            ->first(fn (string $f): bool => str_contains($f, '12,34'));

        $this->assertNotNull($fila, 'la fila no escribe el precio de la tarifa normal');
        $this->assertStringContainsString('43,21', $fila, 'la fila no escribe el precio de la tarifa especial');
    }

    /**
     * ❗❗❗ **UNA ENTRADA SIN PRECIO ESPECIAL DICE «SOLO», Y UNA CON EL MISMO PRECIO NO.**
     *
     * ⚠️⚠️ **El control es la mitad que importa** (`#531`): hasta esta tanda la frase colgaba de
     * «¿tiene recargo?», así que una entrada con el MISMO precio los siete días —que se vende el
     * sábado— se anunciaba como «solo de lunes a jueves». *Existir un precio y ser distinto son dos
     * preguntas.*
     */
    public function test_only_an_entry_that_is_not_sold_on_the_special_rate_says_only(): void
    {
        $entrada = $this->anEntry();

        // (a) SIN precio especial → la fila lo dice y su columna especial queda en raya.
        $this->reprice($entrada, RateType::KEY_NORMAL, 1500);
        $this->reprice($entrada, RateType::KEY_SPECIAL, null);

        $fila = collect($this->rows($this->html()))->first(fn (string $f): bool => str_contains($f, '15 €'));
        $this->assertNotNull($fila);
        $this->assertStringContainsString('solo', $fila, 'la entrada que no se vende el finde no lo dice');
        $this->assertStringContainsString(__('landing.pricing.not_sold'), $fila);

        // (b) CONTROL: con el MISMO precio los dos días, se vende los siete y NO puede decir «solo».
        $this->reprice($entrada, RateType::KEY_SPECIAL, 1500);

        $fila = collect($this->rows($this->html()))->first(fn (string $f): bool => str_contains($f, '15 €'));
        $this->assertNotNull($fila);
        $this->assertStringNotContainsString('solo', $fila, 'una entrada que SÍ se vende el finde se anuncia como si no');
        $this->assertStringNotContainsString(__('landing.pricing.not_sold'), $fila);
    }

    /**
     * ❗❗ **LA HORA EXTRA ES UNA FILA DE LA TABLA, no una ficha del bloque de complementos**
     * (`[DECIDIDO owner, 2026-09-11]`): son dos productos con dos precios y **solo** se venden en
     * tarifa especial, así que en la tabla esa excepción la cuenta la columna sola.
     */
    public function test_the_extra_hour_is_a_row_of_its_zone_and_not_an_addon_card(): void
    {
        // ⚠️ El complemento de TIEMPO se siembra aquí: el catálogo de la suite no trae ninguno, y un
        // caso que lo buscara se quedaría mirando el vacío en verde.
        $extra = TicketType::create([
            'name' => ['es' => 'Hora extra'], 'type' => TicketType::TYPE_ADDON,
            'is_active' => true, 'is_sellable' => true, 'seats_per_unit' => 1,
            'occupies_after_parent' => true, 'duration_min' => 60, 'position' => 90,
        ]);
        // **Solo precio ESPECIAL**: es el caso real —la hora extra es de fin de semana (`#415`)— y
        // es lo que hace que su columna normal tenga que decir que ese día no se vende.
        $extra->prices()->create([
            'rate_type_id' => RateType::where('key', RateType::KEY_SPECIAL)->value('id'),
            'amount_cents' => 500,
        ]);
        $this->anEntry()->configurableAddons()->attach($extra->id, [
            'quantity_mode' => 'fixed', 'max_qty' => 2, 'position' => 1,
        ]);

        $html = $this->html();

        [$nombre] = explode(' · ', (string) $extra->tr('name'), 2);

        $fila = collect($this->rows($html))->first(fn (string $f): bool => str_contains($f, $nombre));
        $this->assertNotNull($fila, 'la hora extra no está en la tabla');
        $this->assertStringContainsString(__('landing.pricing.not_sold'), $fila, 'su columna normal no dice que ese día no se vende');

        // Y NO está en el bloque de complementos, que es lo que la decisión separó.
        preg_match('#<ul class="extras__list".*?</ul>#s', $html, $m);
        $this->assertNotEmpty($m, 'el bloque de complementos no se pinta: la otra mitad miraría el vacío');
        $this->assertStringNotContainsString($nombre, strip_tags($m[0]));

        /*
         * ⚠️⚠️ **Y LA OTRA MITAD, que el arnés obligó a escribir**: a la tabla suben SOLO los
         * complementos de TIEMPO. Sin esta aserción, quitarle el predicado del mecanismo dejaba
         * pasar la mutación —la hora extra seguía en su fila y fuera del carril—, con **todos** los
         * complementos convertidos en filas de precio. *Un caso que mira lo que debe estar no ve lo
         * que no debería.*
         */
        $enLaTabla = implode(' ', $this->rows($html));
        $this->assertStringNotContainsString('Taquilla', $enLaTabla, 'un complemento que no es tiempo se ha colado en la tabla');
    }

    /**
     * **El CHIP marca a la que lidera y su palabra la escribe el panel.** Una entrada destacada sin
     * `badge` no pinta chip: no se inventa aquí una palabra que el dueño no ha escrito.
     */
    public function test_the_chip_only_marks_the_featured_entry(): void
    {
        TicketType::ofType(TicketType::TYPE_ENTRY)->update(['featured' => false]);

        $this->assertStringNotContainsString('rate-table__badge', $this->html(), 'sin destacada no hay chip');

        $entrada = $this->anEntry();
        $entrada->forceFill(['featured' => true, 'badge' => ['es' => 'La favorita']])->save();

        $html = $this->html();
        $this->assertSame(1, substr_count($html, 'rate-table__badge'), 'el chip tiene que ser uno y solo uno por zona destacada');
        $this->assertStringContainsString('La favorita', $html);
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  2 · La semana y la tarifa especial
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **La semana dibujada sale de los días que declara la tarifa especial**, no de una lista escrita.
     *
     * ⚠️ Y su nombre completo viaja para quien no ve la inicial: «L» no es un nombre accesible, y en
     * francés la del martes y la del miércoles son la misma.
     */
    public function test_the_week_strip_marks_the_days_the_special_rate_declares(): void
    {
        RateType::query()->where('is_special', true)->update(['weekdays' => [6]]); // solo el sábado
        RateType::forgetSpecialMemo();

        $html = $this->html();

        $this->assertSame(7, substr_count($html, '<li class="week__day'), 'la tira no dibuja los siete días');
        $this->assertSame(1, substr_count($html, 'week__day--special'), 'solo un día es de tarifa especial');
        $this->assertStringContainsString('sábado: '.__('landing.pricing.week_special'), $html);
        $this->assertStringContainsString('lunes: '.__('landing.pricing.week_normal'), $html);
    }

    /** Sin tarifa especial **no se dibuja** la semana: un diagrama sin dato afirma lo que nadie midió. */
    public function test_without_a_special_rate_there_is_no_week_strip(): void
    {
        RateType::query()->where('is_special', true)->update(['is_active' => false]);
        RateType::forgetSpecialMemo();

        $html = $this->html();

        $this->assertStringNotContainsString('week__day', $html);
        $this->assertStringNotContainsString('rate-note__title', $html, 'sin tarifa especial tampoco hay término que definir');
    }

    /**
     * La explicación nombra los días **derivados de `weekdays`**, no del rótulo tecleado en el panel.
     *
     * ⚠️⚠️ **CAMBIÓ DE FUENTE en `#542`, y ése es justo el defecto que evita.** Aseveraba el RÓTULO
     * (`rate_types.label`), que un operador escribe a mano: en esta instalación dice «Viernes,
     * findes y festivos» mientras `weekdays` dice `[5, 6, 0]` — viernes, sábado y domingo. Los dos
     * existen, no coinciden, y el rótulo **puede desmentir al cálculo sin que nada falle**, que es
     * el mismo modo de fallo que `#531` encontró con «solo de lunes a jueves».
     * ▶ Por eso el caso mueve `weekdays` y comprueba que el texto lo sigue; y comprueba además que
     * el rótulo tecleado **NO** se publica, para que nadie lo reintroduzca «porque es más bonito».
     */
    public function test_the_special_rate_tip_derives_its_days_from_the_configuration(): void
    {
        RateType::query()->where('is_special', true)->update([
            'label' => ['es' => 'Viernes, findes, festivos y vísperas'],
            'weekdays' => [6, 0],
        ]);
        RateType::forgetSpecialMemo();

        $html = $this->html();

        $this->assertStringContainsString(
            __('landing.rates.days_range', ['from' => 'sábado', 'to' => 'domingo']), $html,
            'los días no siguieron a `weekdays`: el texto está escrito en otro sitio.',
        );
        $this->assertStringNotContainsString('Viernes, findes, festivos y vísperas', $html,
            'ha vuelto el rótulo tecleado del panel: puede desmentir al cálculo sin que nada falle.');
        $this->assertStringContainsString(__('landing.rates.tip_calm'), $html);
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  3 · Los festivos
    // ─────────────────────────────────────────────────────────────────────────────────

    /** Con cero fechas especiales **el bloque no existe**: regla dura del sistema. */
    public function test_with_no_special_dates_the_holidays_block_disappears(): void
    {
        SpecialDate::query()->delete();

        $html = $this->html();

        $this->assertStringNotContainsString('holidays__list', $html);
        $this->assertStringNotContainsString(__('landing.pricing.holidays_title'), $html);
    }

    /**
     * ❗❗ **Cada fecha dice SU hecho**: cerrada dice «Cerrado», con tarifa dice el rótulo de esa
     * tarifa —que es lo que se viene a saber en una página de precios— y si no, su horario.
     *
     * ⚠️ **No hay ninguna frase general**: «cuentan como fin de semana, en precio y en horario» es lo
     * que `#487` retiró de la portada porque el producto no puede afirmarlo.
     */
    public function test_each_special_date_states_its_own_fact(): void
    {
        SpecialDate::query()->delete();
        $especial = RateType::firstSpecial();

        /*
         * ⚠️⚠️ **La fecha cerrada lleva TARIFA a propósito, y lo obligó el arnés**: sin ella la
         * mutación que ignora `is_closed` sobrevivía —un día cerrado no tiene horario que enseñar,
         * así que el detalle ya decía «Cerrado» y las dos ramas daban lo mismo—. Con tarifa, lo que
         * se comprueba es la PRECEDENCIA: un día cerrado dice que está cerrado **aunque tenga
         * tarifa declarada**, que es el caso real de un festivo de cierre.
         */
        SpecialDate::create([
            'date' => Carbon::now()->addDays(3)->toDateString(),
            'is_closed' => true, 'note' => ['es' => 'Navidad'],
            'rate_type_id' => $especial->id,
        ]);
        SpecialDate::create([
            'date' => Carbon::now()->addDays(5)->toDateString(),
            'is_closed' => false, 'note' => ['es' => 'Víspera de Reyes'],
            'rate_type_id' => $especial->id,
        ]);

        $html = $this->html();

        preg_match('#<ul class="holidays__list".*?</ul>#s', $html, $m);
        $this->assertNotEmpty($m, 'con fechas cargadas el bloque tiene que pintarse');
        $lista = strip_tags($m[0]);

        $this->assertStringContainsString('Víspera de Reyes', $lista);
        $this->assertStringContainsString((string) $especial->tr('label'), $lista, 'la fecha con tarifa no dice cuál');

        // La fila de la fecha CERRADA, acotada: dice «Cerrado» y **no** el rótulo de su tarifa.
        preg_match('#<li [^>]*holidays__row[^>]*>(?:(?!</li>).)*Navidad(?:(?!</li>).)*</li>#s', $m[0], $fila);
        $this->assertNotEmpty($fila, 'la fecha cerrada no se pinta: el caso miraría el vacío');
        $this->assertStringContainsString(__('landing.info.closed'), strip_tags($fila[0]));
        $this->assertStringNotContainsString((string) $especial->tr('label'), strip_tags($fila[0]),
            'un día CERRADO anuncia su tarifa: lo que hay que decir es que no se abre');
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  4 · Lo que se añade, la salida y lo que la página NO hace
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **Cada complemento dice su ventaja con el texto que el panel escribe en él.**
     *
     * ⚠️ Es lo que permite publicar «imprescindibles para saltar» **sin que el producto deduzca cuál
     * de sus complementos son los calcetines**, que sería usar un campo de presentación como
     * identidad (`#485`).
     */
    public function test_each_addon_row_writes_the_advantage_from_the_panel(): void
    {
        // La ventaja la escribe el PANEL en el propio complemento: se siembra aquí porque el
        // catálogo de la suite no la trae, y el caso tiene que tener sujeto.
        $complemento = TicketType::create([
            'name' => ['es' => 'Calcetines antideslizantes'], 'type' => TicketType::TYPE_ADDON,
            'is_active' => true, 'is_sellable' => true, 'seats_per_unit' => 1, 'position' => 91,
            'features' => ['es' => ['Imprescindibles para saltar', 'Te los quedas']],
        ]);
        $complemento->prices()->create([
            'rate_type_id' => RateType::where('key', RateType::KEY_NORMAL)->value('id'),
            'amount_cents' => 200,
        ]);
        $this->anEntry()->configurableAddons()->attach($complemento->id, ['quantity_mode' => 'fixed', 'position' => 2]);

        $html = $this->html();

        preg_match('#<ul class="extras__list".*?</ul>#s', $html, $m);
        $this->assertNotEmpty($m, 'el bloque de complementos no se pinta');

        $bloque = strip_tags($m[0]);

        $this->assertStringContainsString('Calcetines antideslizantes', $bloque);
        // ⚠️ La PRIMERA ventaja, no todas: la fila dice una línea, y publicar la lista entera
        // convertiría el bloque en la ficha desplegable que esta página ya no tiene.
        $this->assertStringContainsString('Imprescindibles para saltar', $bloque);
        $this->assertStringNotContainsString('Te los quedas', $bloque);
    }

    /**
     * ❗❗ **LA PÁGINA NO LLEVA CTA PROPIO, y el armazón sigue ofreciendo la acción en los dos
     * estados de la venta.** Es la mitad que evita que retirar el botón deje la página sin salida.
     */
    public function test_the_page_has_no_cta_of_its_own_but_the_frame_still_offers_to_buy(): void
    {
        $html = $this->html();

        $this->assertStringNotContainsString('page__cta', $html, 'la página ha recuperado un CTA propio');
        $this->assertStringContainsString('purchase.open()', $html, 'con la venta abierta, el armazón no ofrece comprar');
    }

    /** La salida de la página es su línea a cumpleaños, que lleva a la página de los packs. */
    public function test_the_page_ends_pointing_at_the_birthday_packages(): void
    {
        $html = $this->html();

        // ⚠️ **Acotado al BLOQUE y no a la página**, que fue lo que dijo el arnés: la ruta de
        // cumpleaños la escriben también el menú y el pie, así que sobre el documento entero la
        // aserción pasaba aunque el enlace de la salida apuntara a ninguna parte.
        preg_match('#<div class="rate-page__birthdays">.*?</div>#s', $html, $bloque);
        $this->assertNotEmpty($bloque, 'la página no pinta su salida a cumpleaños');

        $this->assertStringContainsString(__('landing.pricing.birthdays'), $bloque[0]);
        $this->assertStringContainsString(route('cumpleanos'), $bloque[0]);
    }
}
