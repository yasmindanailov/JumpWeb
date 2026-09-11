<?php

namespace Tests\Feature\Site;

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Services\BirthdayComparison;
use App\Domain\Content\Services\LandingAddonPresenter;
use App\Domain\Platform\Services\Money;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * **`/cumpleanos` · EL CUMPLE, AL DETALLE** (carril de diseño Fase 3 · T3b, `DECISIONES #528`).
 * Artboard `Cumpleanos Pagina PJP` 1a/1b.
 *
 * ▶ Lo que se vigila NO es el aspecto —eso lo midió la sonda y lo mira el owner—: son las cosas que
 * se romperían **en silencio**, con la página cargando y la suite en verde.
 *  · **El total es el precio que cobra la cesta por el número de niños**, para cada número, con
 *    los tramos de volumen dentro. Un total calculado de otra forma publicaría una cifra que el
 *    checkout no cobra.
 *  · **La regla de la comparativa**: lo que coincide en todos los packs va a «Igual» y lo que
 *    difiere a la tabla — decidido comparando el catálogo, no con una lista.
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

    private function page(): string
    {
        return (string) $this->get('/cumpleanos')->assertOk()->getContent();
    }

    private function cut(string $html, string $pattern): string
    {
        preg_match($pattern, $html, $m);

        return $m[0] ?? '';
    }

    private function table(string $html): string
    {
        return $this->cut($html, '#<table class="party-compare__table">.*?</table>#s');
    }

    private function shared(string $html): string
    {
        return $this->cut($html, '#<ul class="party-shared__list".*?</ul>#s');
    }

    private function after(string $html): string
    {
        return $this->cut($html, '#<section class="party-page__block" aria-labelledby="party-after">.*?</section>#s');
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

    public function test_the_probe_frames_a_real_page(): void
    {
        $html = $this->page();

        $this->assertStringContainsString(__('landing.birthday.title'), $html);
        $this->assertGreaterThan(1500, strlen($this->table($html)), 'la comparativa es sospechosamente corta');
        $this->assertNotSame('', $this->shared($html), 'no hay bloque «Igual»: los casos de reparto mirarían el vacío');
        $this->assertNotSame('', $this->after($html), 'no hay bloque «Después de reservar»');
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  El dinero
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * ❗❗❗ **EL TOTAL ES EL PRECIO DE LA CESTA POR EL NÚMERO DE NIÑOS, PARA CADA NÚMERO.**
     *
     * Es exactamente la línea de `CartPricer` (`priceCentsForRate($tarifa, $n) × $n`), y por eso
     * el caso siembra un TRAMO: sin él, «el precio del pack × n» y «el de la cesta × n» coinciden
     * y una implementación que ignorase los tramos pasaría en verde.
     */
    public function test_every_total_is_the_basket_price_times_the_count_tiers_included(): void
    {
        $jump = $this->pack('Cumpleaños Jump');
        $normal = RateType::where('key', RateType::KEY_NORMAL)->firstOrFail();
        $jump->priceTiers()->create(['rate_type_id' => $normal->id, 'min_qty' => 12, 'amount_cents' => 1200]);

        $packs = $this->packs();
        $compare = (new BirthdayComparison)->compose($packs);
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

        // La página arranca en el mínimo con esos mismos números (sin JavaScript se lee esto).
        $this->assertStringContainsString($compare['live']['total'][8][$i], $this->table($this->page()));
    }

    /**
     * **LA ESPECIAL VA ENTERA, Y SI NO DICE NADA NUEVO NO SE PINTA.**
     *
     * ⚠️ La negativa es la que protege: que salga «18 €» es fácil; lo que no puede volver es el «+».
     */
    public function test_the_special_rate_rows_are_whole_and_leave_when_they_add_nothing(): void
    {
        $tabla = $this->table($this->page());
        $this->assertStringContainsString(__('landing.birthday.row_each_special'), $tabla);
        $this->assertStringContainsString('18 €', $tabla);
        $this->assertStringNotContainsString('+3 €', $tabla, 'la especial ha vuelto a publicarse como recargo');

        // Con la especial al MISMO precio que la normal en todos los packs, sus filas repetirían
        // las de arriba: se van, y la nota de los días con ellas.
        $special = RateType::where('key', RateType::KEY_SPECIAL)->value('id');
        foreach (TicketType::birthdaySurfacePacks()->get() as $pack) {
            $pack->prices()->where('rate_type_id', $special)->update(['amount_cents' => 1500]);
        }

        $html = $this->page();
        $this->assertStringNotContainsString(__('landing.birthday.row_each_special'), $this->table($html));
        $this->assertStringNotContainsString(__('landing.birthday.row_total_special'), $this->table($html));
        $this->assertStringNotContainsString('class="rates__note"', $html);
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  La regla de la comparativa: solo compara lo que difiere
    // ─────────────────────────────────────────────────────────────────────────────────

    public function test_what_all_packs_share_goes_to_its_block_and_what_differs_to_the_table(): void
    {
        $html = $this->page();

        // «Mesa reservada para el grupo» la escriben los dos packs del seeder; el acceso a la zona, uno.
        $this->assertStringContainsString('Mesa reservada para el grupo', $this->shared($html));
        $this->assertStringNotContainsString('Mesa reservada para el grupo', $this->table($html));
        $this->assertStringContainsString('Acceso exclusivo a la zona Kids', $this->table($html));
        $this->assertStringNotContainsString('Acceso exclusivo a la zona Kids', $this->shared($html));

        // Un HECHO igual en los dos va a «Igual»; en cuanto difiere, baja a su fila.
        $this->assertStringContainsString('De 8 a 20 niños', $this->shared($html));

        $this->pack('Cumpleaños Kids')->update(['max_qty' => 15]);
        $html = $this->page();

        $this->assertStringNotContainsString('De 8 a 20 niños', $this->shared($html));
        $this->assertStringContainsString(__('landing.birthday.row_kids'), $this->table($html));
        $this->assertStringContainsString('De 8 a 15 niños', $this->table($html));
    }

    public function test_with_a_single_pack_everything_is_what_it_includes(): void
    {
        $this->pack('Cumpleaños Kids')->update(['is_sellable' => false]);
        $html = $this->page();

        $this->assertStringContainsString('<h2 class="party-page__title" id="party-packs">'.trans_choice('landing.birthday.packs_title', 1).'</h2>', $html);
        $this->assertStringContainsString(trans_choice('landing.birthday.shared_title', 1), $html);
        $this->assertSame(1, substr_count($html, '<th scope="col" class="party-compare__pack">'));
        // Con un pack solo, todo coincide consigo mismo: nada que comparar en la tabla.
        $this->assertStringNotContainsString(__('landing.birthday.row_features'), $this->table($html));
        $this->assertStringContainsString('Acceso exclusivo a la zona Jump', $this->shared($html));
    }

    /**
     * **EL RELOJ SOLO HABLA POR UNA DURACIÓN COMPARTIDA.** Si los packs duran distinto, su titular
     * diría la de uno como si fuera de todos: se retira y la duración baja a una fila.
     */
    public function test_the_clock_speaks_only_for_a_shared_duration(): void
    {
        $this->assertStringContainsString('class="party__clock"', $this->page());

        $this->pack('Cumpleaños Kids')->update(['duration_min' => 90]);
        $html = $this->page();

        $this->assertStringNotContainsString('class="party__clock"', $html);
        $this->assertStringContainsString(__('landing.birthday.row_duration'), $this->table($html));
        $this->assertStringContainsString('1 h 30 min', $this->table($html));
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Los complementos: el menú, el carril y lo que se añade después
    // ─────────────────────────────────────────────────────────────────────────────────

    public function test_the_rail_leaves_the_menu_to_its_own_block(): void
    {
        $menu = $this->addon('Menú Pizza', 800, 81);
        $this->pack('Cumpleaños Jump')->configurableAddons()->attach($menu->id, ['quantity_mode' => 'per_guest', 'choice_group' => 'menu', 'position' => 2]);

        $html = $this->page();

        $this->assertSame(count(LandingAddonPresenter::unique($this->packs(), true)), substr_count($html, 'class="addon-card"'),
            'el carril no tiene los complementos de reservar sin el menú');
        $this->assertStringNotContainsString('<span class="addon-card__name">Menú Pizza</span>', $html);
        $this->assertStringContainsString('<h3 class="party-menu__name">Menú Pizza</h3>', $html);
    }

    /**
     * **LO QUE SE AÑADE DESPUÉS DE RESERVAR va a su bloque con su precio y su PLAZO, nunca al carril**
     * (que es lo que se compra al reservar, `#413`).
     */
    public function test_after_booking_lists_the_form_and_the_extras_sold_later(): void
    {
        $jump = $this->pack('Cumpleaños Jump');
        $cubo = $this->addon('Cubo de refrescos', 2399, 95);
        $jump->configurableAddons()->attach($cubo->id, [
            'quantity_mode' => 'fixed', 'stage' => 'postform', 'postform_cutoff_hours' => 48, 'max_qty' => 5, 'position' => 9,
        ]);

        $html = $this->page();
        $after = $this->after($html);

        $this->assertStringContainsString(e(__('guestform.title')), $after);
        foreach ($jump->guestFields() as $field) {
            $this->assertStringContainsString(e($jump->guestFieldLabel($field)), $after);
        }
        $this->assertStringContainsString('Nº aproximado de adultos', $after);
        $this->assertStringContainsString('Cubo de refrescos', $after);
        $this->assertStringContainsString('23,99 € · hasta 48 h antes', $after);
        $this->assertStringNotContainsString('<span class="addon-card__name">Cubo de refrescos</span>', $html);
    }

    public function test_the_mixed_age_card_needs_a_shared_age_family(): void
    {
        $this->assertStringNotContainsString(__('landing.birthday.mixed_text'), $this->page(),
            'la tarjeta de edades mezcladas sale sin que dos packs compartan familia de edades');

        TicketType::birthdaySurfacePacks()->update(['guest_age_family' => 'cumple']);
        $html = $this->page();

        $this->assertStringContainsString(e(trans_choice('landing.birthday.mixed_title', 2)), $html);
        $this->assertStringContainsString(__('landing.birthday.mixed_text'), $html);
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  El contador, el botón de reservar y la página vacía
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **Sin JavaScript el contador no se ofrece** (`x-cloak`): la tabla se lee en el mínimo y un
     * botón que no hace nada no se enseña. **Y sin tramo de niños, no hay contador.**
     */
    public function test_the_counter_is_offered_only_with_javascript_and_only_with_a_range(): void
    {
        preg_match_all('#<button type="button" class="party-count__btn"([^>]*)>#s', $this->page(), $m);

        $this->assertCount(2, $m[1], 'no hay dos botones en el contador');
        foreach ($m[1] as $attrs) {
            $this->assertStringContainsString('x-cloak', $attrs);
        }

        TicketType::birthdaySurfacePacks()->update(['min_qty' => 10, 'max_qty' => 10]);
        $this->assertStringNotContainsString('party-count__btn', $this->page());
    }

    public function test_the_page_books_with_a_ghost_button_that_opens_the_packs(): void
    {
        $this->assertMatchesRegularExpression(
            '#<button type="button" class="btn btn--ghost party-compare__cta"\s+@click="\$store\.purchase\.openWith\(\{ type: \'packs\' \}\)">#',
            $this->page(),
        );
    }

    public function test_without_packs_the_page_says_so_and_offers_contact(): void
    {
        TicketType::birthdaySurfacePacks()->update(['is_active' => false]);
        $html = $this->page();

        $this->assertStringContainsString(e(__('landing.events.coming_soon')), $html);
        $this->assertStringContainsString('<a href="'.route('contacto').'" class="btn">', $html);
        $this->assertStringNotContainsString('party-compare', $html);
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Lo retirado no vuelve
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * `[DECIDIDO owner]` (`#528`): el paso a paso y el editor de invitaciones se retiran con su
     * JavaScript y su dependencia. ⚠️ La clase se busca como CLASE, no como subcadena: un hash de
     * Vite puede contener «bd-» por azar.
     */
    public function test_the_retired_pieces_leave_no_trace(): void
    {
        $html = $this->page();

        foreach (['birthdayInvite', 'birthdayProcess', 'html2canvas'] as $rastro) {
            $this->assertStringNotContainsString($rastro, $html);
        }
        $this->assertDoesNotMatchRegularExpression('/class="[^"]*(?<![\w-])bd-/', $html, 'ha vuelto una clase `bd-*` de la página vieja');

        $this->assertStringNotContainsString('html2canvas', (string) file_get_contents(base_path('package.json')));
        $this->assertStringNotContainsString("Alpine.data('birthdayInvite'", (string) file_get_contents(base_path('resources/js/app.js')));
        $this->assertFileDoesNotExist(resource_path('views/components/site/events-section.blade.php'));
    }
}
