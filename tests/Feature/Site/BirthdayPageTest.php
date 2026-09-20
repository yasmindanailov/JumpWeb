<?php

namespace Tests\Feature\Site;

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Platform\Services\Money;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * **`/cumpleanos`: la CONDUCTA del producto** (`DECISIONES #528`; partida por lo que afirma en F5 · T2b,
 * `#659`).
 *
 * ❗❗ **Aquí no se lee el HTML** (`#649`): el contrato con la landing de una instancia son los DATOS que
 * recibe la vista —`compare` (la comparativa entera, con el contador y sus totales por número de niños),
 * `form`, `choices`, `zone` y `packages`—, y sobre ellos se afirma. El marcado de la página de PlayJump
 * ya no está en el producto; lo que garantizaba vive en la doc de la instancia (`paginas/cumpleanos.md`)
 * y el anfitrión mínimo tiene su guarda propia (`AnfitrionCumpleanosTest`).
 *
 * Lo que se rompería EN SILENCIO y esto sostiene:
 *  · **El total es el precio que cobra la cesta por el número de niños**, para cada número, con los
 *    tramos de volumen dentro. Un total calculado de otra forma publicaría una cifra que el checkout no
 *    cobra — y nadie lo notaría hasta pagar.
 *  · **La regla de la comparativa**: lo que coincide en todos los packs va a «Igual» y lo que difiere a
 *    la tabla, decidido comparando el catálogo y no con una lista escrita.
 *  · **La especial va ENTERA o no va**: nunca vuelve a publicarse como recargo.
 *  · **Lo retirado no vuelve** (el editor de invitaciones, el paso a paso, `html2canvas`).
 *
 * ⚠️ Cada caso que cambia el catálogo vuelve a pedir la página: la comparativa se compone en la
 * petición, así que un caso que midiera antes de sembrar miraría el catálogo de antes.
 */
class BirthdayPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LandingContentSeeder::class);
        Cache::flush();
        app()->setLocale('es');
    }

    /** @return array<string, mixed> Lo que el controlador le pasa a la vista: el sujeto de esta guarda. */
    private function datos(): array
    {
        return $this->get('/cumpleanos')->assertOk()->original->getData();
    }

    /** @return array<string, mixed> La comparativa ya compuesta. */
    private function compare(): array
    {
        return $this->datos()['compare'];
    }

    /** Los textos de una fila de la tabla, aplanados: el sujeto de «esto se compara». */
    private function filaTexto(array $compare, string $label): string
    {
        $fila = collect($compare['rows'])->firstWhere('label', $label);

        return $fila === null ? '' : json_encode($fila['cells'], JSON_UNESCAPED_UNICODE);
    }

    /** Todo lo que viaja en «Igual en los dos», aplanado. */
    private function igualTexto(array $compare): string
    {
        return json_encode($compare['shared'], JSON_UNESCAPED_UNICODE);
    }

    /** Toda la tabla, aplanada. */
    private function tablaTexto(array $compare): string
    {
        return json_encode($compare['rows'], JSON_UNESCAPED_UNICODE);
    }

    /** @return Collection<int, TicketType> */
    private function packs(): Collection
    {
        return TicketType::birthdaySurfacePacks()
            ->with(['prices.rateType', 'priceTiers', 'addons.prices.rateType'])
            ->orderBy('position')->get();
    }

    private function pack(string $name): TicketType
    {
        return TicketType::where('name->es', $name)->firstOrFail();
    }

    private function addon(string $name, int $cents, int $position): TicketType
    {
        $addon = TicketType::create(['name' => ['es' => $name], 'type' => TicketType::TYPE_ADDON, 'is_active' => true, 'is_sellable' => true, 'seats_per_unit' => 1, 'position' => $position]);
        $addon->prices()->create(['rate_type_id' => RateType::where('key', RateType::KEY_NORMAL)->value('id'), 'amount_cents' => $cents]);

        return $addon;
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Guarda de la guarda
    // ─────────────────────────────────────────────────────────────────────────────────

    /** Sin comparativa compuesta, todo lo de abajo miraría el vacío. */
    public function test_the_page_composes_a_real_comparison(): void
    {
        $datos = $this->datos();

        $this->assertNotNull($datos['compare'], 'la página no compone comparativa: los casos de abajo no medirían nada');
        $this->assertGreaterThan(1, count($datos['compare']['columns']), 'hacen falta dos packs para comparar');
        $this->assertNotEmpty($datos['compare']['rows']);
        $this->assertNotEmpty($datos['compare']['shared'], 'no hay bloque «Igual»: los casos de reparto mirarían el vacío');
        $this->assertNotNull($datos['form'], 'no viaja lo que pide el post-form');
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  El dinero
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * ❗❗❗ **EL TOTAL ES EL PRECIO DE LA CESTA POR EL NÚMERO DE NIÑOS, PARA CADA NÚMERO.**
     *
     * Es exactamente la línea de `CartPricer` (`priceCentsForRate($tarifa, $n) × $n`), y por eso el caso
     * siembra un TRAMO: sin él, «el precio del pack × n» y «el de la cesta × n» coinciden y una
     * implementación que ignorase los tramos pasaría en verde.
     */
    public function test_every_total_is_the_basket_price_times_the_count_tiers_included(): void
    {
        $jump = $this->pack('Cumpleaños Jump');
        $normal = RateType::where('key', RateType::KEY_NORMAL)->firstOrFail();
        $jump->priceTiers()->create(['rate_type_id' => $normal->id, 'min_qty' => 12, 'amount_cents' => 1200]);

        $packs = $this->packs();
        $compare = $this->compare();
        $i = $packs->search(fn (TicketType $p): bool => $p->id === $jump->id);

        $this->assertSame(['from' => 8, 'to' => 20], $compare['counter']);
        foreach ($compare['live']['total'] as $n => $cells) {
            $this->assertSame(Money::showcase($packs[$i]->priceCentsForRate($normal, $n) * $n).' €', $cells[$i],
                "el total de {$n} niños no es el que cobra la cesta");
        }

        // Y el tramo muerde de verdad: 11 niños al precio base, 12 al del tramo.
        $this->assertSame('15 €', $compare['live']['each'][11][$i]);
        $this->assertSame('12 €', $compare['live']['each'][12][$i]);
        $this->assertSame('144 €', $compare['live']['total'][12][$i]);
    }

    /**
     * **LA ESPECIAL VA ENTERA, Y SI NO DICE NADA NUEVO NO SE PINTA.**
     *
     * ⚠️ La negativa es la que protege: que salga «18 €» es fácil; lo que no puede volver es el «+».
     */
    public function test_the_special_rate_rows_are_whole_and_leave_when_they_add_nothing(): void
    {
        $compare = $this->compare();

        $this->assertNotSame('', $this->filaTexto($compare, __('landing.birthday.row_each_special')),
            'la tarifa especial no trae su fila');
        $this->assertStringContainsString('18 €', $this->tablaTexto($compare));
        $this->assertStringNotContainsString('+3 €', $this->tablaTexto($compare),
            'la especial ha vuelto a publicarse como recargo');
        $this->assertTrue($compare['special'], 'con precios distintos, la nota de los días tiene que ofrecerse');

        // Con la especial al MISMO precio que la normal en todos los packs, sus filas repetirían las de
        // arriba: se van, y la nota de los días con ellas.
        $special = RateType::where('key', RateType::KEY_SPECIAL)->value('id');
        foreach (TicketType::birthdaySurfacePacks()->get() as $pack) {
            $pack->prices()->where('rate_type_id', $special)->update(['amount_cents' => 1500]);
        }

        $compare = $this->compare();
        $this->assertSame('', $this->filaTexto($compare, __('landing.birthday.row_each_special')));
        $this->assertSame('', $this->filaTexto($compare, __('landing.birthday.row_total_special')));
        $this->assertFalse($compare['special'], 'se sigue ofreciendo la nota de los días de la especial');
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  La regla de la comparativa: solo compara lo que difiere
    // ─────────────────────────────────────────────────────────────────────────────────

    public function test_what_all_packs_share_goes_to_its_block_and_what_differs_to_the_table(): void
    {
        $compare = $this->compare();

        // «Mesa reservada para el grupo» la escriben los dos packs del seeder; el acceso a la zona, uno.
        $this->assertStringContainsString('Mesa reservada para el grupo', $this->igualTexto($compare));
        $this->assertStringNotContainsString('Mesa reservada para el grupo', $this->tablaTexto($compare));
        $this->assertStringContainsString('Acceso exclusivo a la zona Kids', $this->tablaTexto($compare));
        $this->assertStringNotContainsString('Acceso exclusivo a la zona Kids', $this->igualTexto($compare));

        // Un HECHO igual en los dos va a «Igual»; en cuanto difiere, baja a su fila.
        $this->assertStringContainsString('Desde 8 niños', $this->igualTexto($compare));

        // ⚠️ El MÁXIMO no es un hecho publicado (`#586`): con topes distintos sigue siendo «Igual».
        $this->pack('Cumpleaños Kids')->update(['max_qty' => 15]);
        $this->assertStringContainsString('Desde 8 niños', $this->igualTexto($this->compare()));

        $this->pack('Cumpleaños Kids')->update(['min_qty' => 10]);
        $compare = $this->compare();

        $this->assertStringNotContainsString('Desde 8 niños', $this->igualTexto($compare));
        $this->assertStringContainsString('Desde 10 niños', $this->filaTexto($compare, __('landing.birthday.row_kids')));
    }

    /**
     * **La etiqueta destacada de un pack viaja con su columna** (`#585`, `[DECIDIDO owner, 2026-09-13]`):
     * el panel la deja escribir también en los packs. Sin etiqueta, la columna no se inventa ninguna.
     */
    public function test_a_pack_badge_travels_with_its_column(): void
    {
        $this->assertEmpty(array_filter(array_column($this->compare()['columns'], 'badge')), 'sin etiqueta viaja una etiqueta');

        $this->pack('Cumpleaños Jump')->update(['badge' => ['es' => 'La favorita', 'en' => 'Favourite', 'fr' => 'La préférée']]);

        $columnas = $this->compare()['columns'];
        $this->assertSame(['La favorita'], array_values(array_filter(array_column($columnas, 'badge'))));
        $this->assertSame('Cumpleaños Jump', collect($columnas)->firstWhere('badge', 'La favorita')['name']);
    }

    public function test_with_a_single_pack_everything_is_what_it_includes(): void
    {
        $this->pack('Cumpleaños Kids')->update(['is_sellable' => false]);
        $compare = $this->compare();

        $this->assertCount(1, $compare['columns']);
        // Con un pack solo, todo coincide consigo mismo: nada que comparar en la tabla.
        $this->assertSame('', $this->filaTexto($compare, __('landing.birthday.row_features')));
        $this->assertStringContainsString('Acceso exclusivo a la zona Jump', $this->igualTexto($compare));
        // ⚠️ La duración va donde va lo que incluye (`#583`): aquí, abriendo «Igual», no sola en la tabla.
        $duracion = __('landing.events.duration_feature', ['duration' => '2 h']);
        $this->assertStringContainsString($duracion, $this->igualTexto($compare), 'con un pack, la duración no abre lo que incluye');
        $this->assertStringNotContainsString($duracion, $this->tablaTexto($compare));
    }

    /**
     * **LA DURACIÓN ABRE LO QUE INCLUYE CADA PACK, EN SU COLUMNA** (`[DECIDIDO owner, 2026-09-13]`,
     * `#583`). El reloj «Las 2 h, a vuestro ritmo» se retiró; la duración va con «Acceso exclusivo a la
     * zona…», que es lo que se lee para elegir, aunque coincida en todos los packs.
     * ⚠️ El control cambia UNA duración: cada columna tiene que decir la suya, sale del catálogo.
     */
    public function test_the_duration_leads_what_each_pack_includes(): void
    {
        $linea = fn (string $d): string => __('landing.events.duration_feature', ['duration' => $d]);

        $this->assertSame(2, substr_count($this->tablaTexto($this->compare()), $linea('2 h')),
            'la duración no abre la columna de cada pack');

        $this->pack('Cumpleaños Kids')->update(['duration_min' => 90]);
        $tabla = $this->tablaTexto($this->compare());

        $this->assertStringContainsString($linea('1 h 30 min'), $tabla, 'la columna del pack cambiado no dice su duración');
        $this->assertSame(1, substr_count($tabla, $linea('2 h')), 'la otra columna perdió la suya');
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  La foto, el menú y lo que se añade después
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **La foto de la zona es DATO y su ausencia es una respuesta** (`#532`). Sale de `zones.image`, el
     * campo del panel, y la resuelve el PRODUCTO (`Zone::imageUrl()`, `#645`): con foto viaja su URL
     * absoluta; sin foto viaja `null`, y una landing que reserve hueco lo hace contra el dato.
     */
    public function test_the_zone_photo_is_data_and_its_absence_is_an_answer(): void
    {
        $zone = $this->pack('Cumpleaños Kids')->zone;
        $zone->update(['image' => 'images/attractions/cumplea_1.webp']);

        $this->assertSame($zone->fresh()->imageUrl(), $this->datos()['zone']->imageUrl());
        $this->assertStringContainsString('images/attractions/cumplea_1.webp', (string) $this->datos()['zone']->imageUrl());

        $zone->update(['image' => null]);
        $this->assertNull($this->datos()['zone']->imageUrl(), 'sin foto en el panel sigue viajando una URL');
    }

    /** El menú es una ELECCIÓN dentro del pack, no un complemento: viaja en su propio grupo. */
    public function test_the_menu_travels_as_its_own_choice_group(): void
    {
        $menu = $this->addon('Menú Pizza', 800, 81);
        $this->pack('Cumpleaños Jump')->configurableAddons()->attach($menu->id, ['quantity_mode' => 'per_guest', 'choice_group' => 'menu', 'position' => 2]);

        $grupos = $this->datos()['choices'];
        $menuGroup = collect($grupos)->firstWhere('key', 'menu');

        $this->assertNotNull($menuGroup, 'el grupo del menú no viaja');
        $this->assertContains('Menú Pizza', array_column($menuGroup['rows'], 'name'));
    }

    /**
     * **LO QUE SE AÑADE DESPUÉS DE RESERVAR va con su precio y su PLAZO** (nunca al carril de la compra,
     * que es lo que se vende al reservar, `#413`).
     */
    public function test_after_booking_carries_the_form_and_the_extras_sold_later(): void
    {
        $jump = $this->pack('Cumpleaños Jump');
        $cubo = $this->addon('Cubo de refrescos', 2399, 95);
        $jump->configurableAddons()->attach($cubo->id, [
            'quantity_mode' => 'fixed', 'stage' => 'postform', 'postform_cutoff_hours' => 48, 'max_qty' => 5, 'position' => 9,
        ]);

        $form = $this->datos()['form'];

        foreach ($jump->guestFields() as $field) {
            $rotulo = $jump->guestFieldLabel($field);
            $this->assertContains($rotulo, $form['children'], "el post-form no anuncia el campo «{$rotulo}»");
        }
        $this->assertContains('Nº aproximado de adultos', $form['group']);

        $extra = collect($form['extras'])->firstWhere('name', 'Cubo de refrescos');
        $this->assertNotNull($extra, 'el complemento de venta posterior no viaja');
        $this->assertSame('23,99 €', $extra['price']);
        $this->assertSame('hasta 48 h antes', $extra['cutoff'], 'el plazo de corte no viaja con el complemento');
    }

    /** La tarjeta de edades mezcladas necesita DOS packs de la misma familia: si no, no hay fiesta mixta. */
    public function test_the_mixed_age_card_needs_a_shared_age_family(): void
    {
        $this->assertLessThan(2, $this->compare()['mixed'],
            'se anuncia la fiesta mixta sin que dos packs compartan familia de edades');

        TicketType::birthdaySurfacePacks()->update(['guest_age_family' => 'cumple']);

        $this->assertSame(2, $this->compare()['mixed']);
    }

    /** **Sin tramo de niños no hay contador**: un control que no elige nada no se ofrece. */
    public function test_without_a_range_there_is_no_counter(): void
    {
        $this->assertGreaterThan($this->compare()['counter']['from'], $this->compare()['counter']['to'],
            'el caso nace sin sujeto: no hay tramo');

        TicketType::birthdaySurfacePacks()->update(['min_qty' => 10, 'max_qty' => 10]);
        $compare = $this->compare();

        $this->assertSame($compare['counter']['from'], $compare['counter']['to']);
    }

    /** Sin packs vendibles no hay comparativa: vacío es una respuesta, y la vista lo recibe como `null`. */
    public function test_without_packs_nothing_is_composed(): void
    {
        TicketType::birthdaySurfacePacks()->update(['is_active' => false]);
        $datos = $this->datos();

        $this->assertNull($datos['compare']);
        $this->assertNull($datos['form']);
        $this->assertSame([], $datos['choices']);
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Lo retirado no vuelve
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * `[DECIDIDO owner]` (`#528`): el paso a paso y el editor de invitaciones se retiran con su
     * JavaScript y su dependencia. ⚠️ Esto mira el PRODUCTO —su `package.json`, su `app.js` y sus
     * componentes—, no el marcado de una landing: por eso sobrevive a la mudanza.
     */
    public function test_the_retired_pieces_leave_no_trace(): void
    {
        $this->assertStringNotContainsString('html2canvas', (string) file_get_contents(base_path('package.json')));
        $this->assertStringNotContainsString("Alpine.data('birthdayInvite'", (string) file_get_contents(base_path('resources/js/app.js')));
        $this->assertStringNotContainsString("Alpine.data('birthdayProcess'", (string) file_get_contents(base_path('resources/js/app.js')));
        $this->assertFileDoesNotExist(resource_path('views/components/site/events-section.blade.php'));
    }
}
