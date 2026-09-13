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
 *  2. **Ningún complemento se publica aquí, tampoco la hora extra** (`#583`): solo se ofrecen en el
 *     cajón, al reservar. Revierte `#531`, que la había puesto como fila de la tabla.
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
     * ❗❗ **NI LA HORA EXTRA NI NINGÚN COMPLEMENTO SE PUBLICAN EN `/precios`** (`[DECIDIDO owner,
     * 2026-09-13]`, `#583`): solo se ofrecen en el cajón, al reservar. Revierte `#531`, que había
     * puesto la hora extra como fila de la tabla y el resto en un bloque de filas.
     */
    public function test_neither_the_extra_hour_nor_any_addon_is_published(): void
    {
        // ⚠️ El complemento de TIEMPO se siembra aquí: el catálogo de la suite no trae ninguno, y sin
        // él que no esté en la tabla no demostraría nada.
        $extra = TicketType::create([
            'name' => ['es' => 'Hora extra'], 'type' => TicketType::TYPE_ADDON,
            'is_active' => true, 'is_sellable' => true, 'seats_per_unit' => 1,
            'occupies_after_parent' => true, 'duration_min' => 60, 'position' => 90,
        ]);
        $extra->prices()->create([
            'rate_type_id' => RateType::where('key', RateType::KEY_SPECIAL)->value('id'),
            'amount_cents' => 500,
        ]);
        $this->anEntry()->configurableAddons()->attach($extra->id, [
            'quantity_mode' => 'fixed', 'max_qty' => 2, 'position' => 1,
        ]);

        $html = $this->html();

        $this->assertNotEmpty($this->rows($html), 'la tabla no se pinta: el caso miraría el vacío');
        $this->assertStringNotContainsString('Hora extra', implode(' ', $this->rows($html)),
            'la hora extra ha vuelto a la tabla de precios');
        $this->assertStringNotContainsString('extras__list', $html, 'ha vuelto el bloque de complementos');
    }

    /**
     * **La etiqueta destacada sale en toda fila que la tenga, lidere o no** (`#585`, `[DECIDIDO owner,
     * 2026-09-13]`; revierte `#480`). Y sin `badge` no hay chip, ni siquiera en la destacada: no se
     * inventa aquí una palabra que el dueño no ha escrito.
     */
    public function test_every_entry_with_a_badge_carries_its_chip(): void
    {
        TicketType::ofType(TicketType::TYPE_ENTRY)->update(['featured' => false, 'badge' => null]);

        $this->assertStringNotContainsString('rate-table__badge', $this->html(), 'sin etiqueta no hay chip');

        $entrada = $this->anEntry();
        $entrada->forceFill(['featured' => true])->save();
        $this->assertStringNotContainsString('rate-table__badge', $this->html(), 'una destacada sin etiqueta pinta chip');

        // Con etiqueta y SIN destacar: el chip sale igual.
        $entrada->forceFill(['featured' => false, 'badge' => ['es' => 'La favorita']])->save();

        $html = $this->html();
        $this->assertSame(1, substr_count($html, 'rate-table__badge'), 'la etiqueta de una fila que no lidera no sale');
        $this->assertStringContainsString('<span class="rate-table__badge">La favorita</span>', $html);
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

    /** La explicación nombra la tarifa **con el rótulo del panel**, no con una lista escrita aquí. */
    public function test_the_special_rate_card_names_the_rate_from_the_panel(): void
    {
        RateType::query()->where('is_special', true)->update(['label' => ['es' => 'Viernes, findes, festivos y vísperas']]);
        RateType::forgetSpecialMemo();

        $html = $this->html();

        $this->assertStringContainsString('Viernes, findes, festivos y vísperas', $html);
        $this->assertStringContainsString(__('landing.pricing.special_calm'), $html);
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
    //  4 · La salida y lo que la página NO hace
    // ─────────────────────────────────────────────────────────────────────────────────

    // ⚠️ Aquí vivía `test_each_addon_row_writes_the_advantage_from_the_panel`, y se fue con su bloque
    // (`#583`): los complementos ya no se publican en `/precios`. Lo vigila, al revés, el caso de la
    // hora extra de arriba.

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

    /**
     * La salida de la página lleva a la página de los packs.
     *
     * ▶ **La PROPIEDAD no cambia; cambia quién la cumple.** Hasta las bandas de enlace esto lo
     * pintaba `.rate-page__birthdays`, una línea con su enlace escrita solo para esta página; hoy
     * es la banda GORDA, que en el reparto del canvas contesta desde aquí exactamente eso. El caso
     * se re-apunta al bloque nuevo y **no queda más débil**: sigue acotado y sigue exigiendo la
     * ruta de cumpleaños dentro de él.
     */
    public function test_the_page_ends_pointing_at_the_birthday_packages(): void
    {
        $html = $this->html();

        // ⚠️ **Acotado al BLOQUE y no a la página**, que fue lo que dijo el arnés: la ruta de
        // cumpleaños la escriben también el menú y el pie, así que sobre el documento entero la
        // aserción pasaba aunque el enlace de la salida apuntara a ninguna parte.
        preg_match('#<div class="band-wide".*?</div>\s*</div>#s', $html, $bloque);
        $this->assertNotEmpty($bloque, 'la página no pinta su salida a cumpleaños');

        $this->assertStringContainsString(__('site.bands.ask.precios.q'), $bloque[0]);
        $this->assertStringContainsString(route('cumpleanos'), $bloque[0]);
    }
}
