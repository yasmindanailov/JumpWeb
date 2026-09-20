<?php

namespace Tests\Feature\Instancia;

use App\Domain\Booking\Models\Price;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\SpecialDate;
use App\Domain\Booking\Models\TicketType;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **El ANFITRIÓN MÍNIMO de `/precios`** — lo que el producto sirve sin paquete de instancia (F5 · T2b,
 * `DECISIONES #658`). Es marcado del PRODUCTO, y por eso aquí SÍ se mira el HTML (`#649`): tiene que pintar
 * TODO lo que el contrato de vista le da —una tabla por zona con sus dos columnas, la raya del día que no
 * se vende con su texto para lector de pantalla, la semana, la tarjeta de la tarifa especial, el calendario
 * y el QR del registro externo— y no puede pintar lo que no le dan.
 *
 * ⚠️ Sin arte a propósito: ni fachada ni rayos. El diseño es de la instancia.
 */
class AnfitrionPreciosTest extends TestCase
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
        return (string) $this->get('/precios')->assertOk()->assertViewIs('anfitrion.precios')->getContent();
    }

    /** @return array<string, mixed> */
    private function datos(): array
    {
        return $this->get('/precios')->assertOk()->original->getData();
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

    public function test_it_paints_one_table_per_zone_and_one_row_per_entry(): void
    {
        $rateTable = $this->datos()['rateTable'];
        $html = $this->html();

        $this->assertNotEmpty($rateTable, 'el caso nace sin sujeto: no viaja ninguna zona');
        $this->assertSame(count($rateTable), substr_count($html, 'class="rate-zone__head"'), 'no se pinta una cabecera por zona');

        foreach ($rateTable as $zona) {
            $this->assertStringContainsString('id="zone-'.$zona['slug'].'"', $html);
            $this->assertStringContainsString('background: '.$zona['color'], $html, "la zona «{$zona['slug']}» pierde su cuadrado de identidad");
        }

        $filas = collect($rateTable)->flatMap(fn (array $z): array => $z['rows']);
        $this->assertSame($filas->count(), substr_count($html, 'class="rate-table__row"'));
        $this->assertStringContainsString(__('landing.pricing.vat_note'), $html, 'la nota del IVA no se pinta');
    }

    /**
     * ❗❗ **La raya no es «gratis»: es que ese día no se vende**, y hay que decirlo en voz alta. Sin el
     * texto para lector de pantalla, una celda con «—» es una cifra que nadie puede leer.
     */
    public function test_a_price_that_does_not_exist_is_a_dash_that_says_it_is_not_sold(): void
    {
        $entrada = $this->anEntry();
        $this->reprice($entrada, RateType::KEY_NORMAL, 1500);
        $this->reprice($entrada, RateType::KEY_SPECIAL, null);

        $html = $this->html();

        $this->assertStringContainsString('<span aria-hidden="true">—</span>', $html);
        $this->assertStringContainsString(__('landing.pricing.not_sold'), $html);
        // ⚠️ Espacio DURO entre cifra y símbolo (`#661`): lo escribe `Money::showcaseWithSymbol()` y
        // aquí se teclea a mano, no se deriva de ella, para que un cambio de formato ponga esto rojo.
        $this->assertStringContainsString("15\u{00A0}€", $html, 'el precio que SÍ existe no se pinta');

        // Y con precio los dos días no hay ninguna raya: la tabla no inventa excepciones.
        $this->reprice($entrada, RateType::KEY_SPECIAL, 1500);
        TicketType::ofType(TicketType::TYPE_ENTRY)->where('is_active', true)->get()
            ->each(fn (TicketType $t) => $this->reprice($t, RateType::KEY_SPECIAL, 1500));

        $this->assertStringNotContainsString('<span aria-hidden="true">—</span>', $this->html());
    }

    /** La etiqueta y la nota se pintan SOLO si el dato las trae: ni hueco ni frase inventada. */
    public function test_the_badge_and_the_note_are_painted_only_when_the_data_brings_them(): void
    {
        TicketType::ofType(TicketType::TYPE_ENTRY)->update(['badge' => null]);
        $this->assertStringNotContainsString('rate-table__badge', $this->html(), 'sin etiqueta se pinta un chip');

        $this->anEntry()->forceFill(['badge' => ['es' => 'La favorita']])->save();

        $html = $this->html();
        $this->assertSame(1, substr_count($html, 'rate-table__badge'));
        $this->assertStringContainsString('<span class="rate-table__badge">La favorita</span>', $html);
    }

    public function test_the_week_strip_follows_the_special_rate(): void
    {
        RateType::query()->where('is_special', true)->update(['weekdays' => [6]]);
        RateType::forgetSpecialMemo();

        $html = $this->html();

        $this->assertSame(7, substr_count($html, '<li class="week__day'), 'la tira no dibuja los siete días');
        $this->assertSame(1, substr_count($html, 'week__day--special'));
        $this->assertStringContainsString('sábado: '.__('landing.pricing.week_special'), $html, 'el día no lleva su nombre completo');

        // Sin tarifa especial no hay tira NI término que definir: la vista no dibuja lo que no le dan.
        RateType::query()->where('is_special', true)->update(['is_active' => false]);
        RateType::forgetSpecialMemo();

        $html = $this->html();
        $this->assertStringNotContainsString('week__day', $html);
        $this->assertStringNotContainsString('rate-note__title', $html);
    }

    /**
     * ⚠️⚠️ **El rótulo de la tarifa entra en un `{!! !!}` para poder ir en negrita, así que se ESCAPA
     * antes**: el dato lo escribe el panel y no tiene permiso para traer marcado. Sin esto, «Findes
     * <script>» sería marcado ejecutable en la página pública.
     */
    public function test_the_special_label_is_bold_but_cannot_bring_markup(): void
    {
        RateType::query()->where('is_special', true)->update(['label' => ['es' => 'Findes <b>y</b> festivos']]);
        RateType::forgetSpecialMemo();

        $html = $this->html();

        $this->assertStringContainsString('<b>Findes &lt;b&gt;y&lt;/b&gt; festivos</b>', $html,
            'el rótulo del panel entra con su marcado en la página');
    }

    public function test_the_holidays_block_states_each_fact_and_disappears_without_dates(): void
    {
        // ⚠️ El calendario publica lo que VIENE, así que el caso se siembra su fecha: las del seeder
        // pueden haber pasado ya y el bloque saldría vacío sin que nada estuviera roto.
        SpecialDate::create([
            'date' => now()->addDays(4)->toDateString(),
            'is_closed' => false, 'note' => ['es' => 'Víspera de Reyes'],
            'rate_type_id' => RateType::firstSpecial()?->id,
        ]);

        $holidays = $this->datos()['holidays'];
        $this->assertNotEmpty($holidays, 'el caso nace sin sujeto: no viaja ninguna fecha');

        $html = $this->html();
        $this->assertSame(count($holidays), substr_count($html, 'holidays__row'), 'no se pinta una fila por fecha');
        $this->assertStringContainsString((string) $holidays[0]['fact'], $html, 'la fecha se pinta sin su hecho');

        SpecialDate::query()->delete();

        $html = $this->html();
        $this->assertStringNotContainsString('holidays__list', $html);
        $this->assertStringNotContainsString(__('landing.pricing.holidays_title'), $html);
    }

    /**
     * ❗❗ **LA PÁGINA NO LLEVA CTA PROPIO, y el armazón sigue ofreciendo la acción.** Es la mitad que evita
     * que retirar el botón deje la página sin salida. Vivía en `Site/PricingPageTest` y se queda aquí con
     * la mudanza (`#658`): mira MARCADO, y el marcado del producto es este anfitrión.
     */
    public function test_the_page_has_no_cta_of_its_own_but_the_frame_still_offers_to_buy(): void
    {
        $html = $this->html();

        $this->assertStringNotContainsString('page__cta', $html, 'la página ha recuperado un CTA propio');
        $this->assertStringContainsString('purchase.open()', $html, 'con la venta abierta, el armazón no ofrece comprar');
    }

    /**
     * La salida lleva a la página de los packs, y va **acotada al BLOQUE**: la ruta de cumpleaños la
     * escriben también el menú y el pie, así que sobre el documento entero la aserción pasaría aunque el
     * enlace de la salida apuntara a ninguna parte (lo dijo el arnés en su día).
     */
    public function test_the_page_ends_pointing_at_the_birthday_packages(): void
    {
        preg_match('#<div class="band-wide".*?</div>\s*</div>#s', $this->html(), $bloque);

        $this->assertNotEmpty($bloque, 'la página no pinta su salida a cumpleaños');
        $this->assertStringContainsString(__('site.bands.ask.precios.q'), $bloque[0]);
        $this->assertStringContainsString(route('cumpleanos'), $bloque[0]);
    }

    /** El anfitrión es el motor sin el arte: la fachada de esta página es de la instancia. */
    public function test_the_host_carries_no_art(): void
    {
        $this->assertStringNotContainsString('fac-p--page-precios', $this->html());
    }
}
