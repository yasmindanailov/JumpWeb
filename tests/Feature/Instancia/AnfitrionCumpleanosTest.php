<?php

namespace Tests\Feature\Instancia;

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\TicketType;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * **El ANFITRIÓN MÍNIMO de `/cumpleanos`** — lo que el producto sirve sin paquete de instancia (F5 · T2b,
 * `DECISIONES #659`). Es marcado del PRODUCTO, y por eso aquí SÍ se mira el HTML (`#649`): tiene que
 * pintar TODO lo que el contrato de vista le da —la comparativa con una columna por pack, lo que es igual
 * en todos, el menú, lo que se decide después de reservar y el cierre— y NO puede inventarse lo que no le
 * dan.
 *
 * ❗❗ **Dos cosas se heredan de `Site/BirthdayPageTest` porque miran MARCADO y el marcado del producto es
 * éste**: que el contador solo se ofrezca con JavaScript (`x-cloak`) —un control que no hace nada no se
 * enseña— y que el botón de reservar sea FANTASMA y abra el cajón en los packs. La segunda la vigila
 * además `SidebarSeamTest`, que lee esta vista como texto.
 *
 * ⚠️ Sin arte a propósito: sin el TRÍO, que es de la instancia (`trio--page`, declarado en
 * `InstanceViews::MATERIAL_CONSUMIDO_POR_LA_INSTANCIA`).
 */
class AnfitrionCumpleanosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LandingContentSeeder::class);
        Cache::flush();
        $this->withSession(['locale' => 'es']);
        app()->setLocale('es');
    }

    private function html(): string
    {
        return (string) $this->get('/cumpleanos')->assertOk()->assertViewIs('anfitrion.cumpleanos')->getContent();
    }

    /** @return array<string, mixed> */
    private function datos(): array
    {
        return $this->get('/cumpleanos')->assertOk()->original->getData();
    }

    public function test_it_paints_a_column_per_pack_and_a_row_per_compared_fact(): void
    {
        $compare = $this->datos()['compare'];
        $html = $this->html();

        $this->assertNotNull($compare, 'el caso nace sin sujeto: no hay comparativa');
        $this->assertSame(count($compare['columns']), substr_count($html, '<th scope="col" class="party-compare__pack">'));
        $this->assertSame(count($compare['rows']), substr_count($html, 'class="party-compare__row'));
        $this->assertSame(count($compare['shared']), substr_count($html, 'class="party-shared__item"'));

        // Y lo que se compara se lee: el total del mínimo está escrito, no solo en el estado de Alpine.
        $i = 0;
        $this->assertStringContainsString($compare['live']['total'][$compare['counter']['from']][$i], $html,
            'sin JavaScript no se lee la tabla del mínimo');
    }

    /** La etiqueta de un pack se pinta bajo su nombre, y solo la de la columna que la trae. */
    public function test_a_pack_badge_is_painted_under_its_name(): void
    {
        $this->assertStringNotContainsString('party-compare__badge', $this->html(), 'sin etiqueta se pinta un chip');

        TicketType::where('name->es', 'Cumpleaños Jump')->firstOrFail()
            ->update(['badge' => ['es' => 'La favorita']]);

        $html = $this->html();
        $this->assertSame(1, substr_count($html, 'party-compare__badge'), 'el chip sale en una columna que no lo lleva');
        $this->assertStringContainsString('Cumpleaños Jump<span class="party-compare__badge">La favorita</span></th>', $html);
    }

    /**
     * **La foto de la zona es DATO y su ausencia es una respuesta**: con foto, `<figure>` con el nombre de
     * la zona por `alt`; sin foto, **ni figura ni hueco gris esperando**.
     */
    public function test_the_zone_photo_is_painted_only_when_there_is_one(): void
    {
        $zone = TicketType::where('name->es', 'Cumpleaños Kids')->firstOrFail()->zone;
        $zone->update(['image' => 'images/attractions/cumplea_1.webp']);

        $html = $this->html();
        $this->assertStringContainsString('class="party-photo"', $html, 'la foto de la zona no se publica');
        $this->assertStringContainsString((string) $zone->fresh()->imageUrl(), $html, 'la foto no sale por `imageUrl()`');
        $this->assertStringContainsString('alt="'.e($zone->tr('name')).'"', $html, 'el `alt` no es el nombre del panel');

        $zone->update(['image' => null]);
        $this->assertStringNotContainsString('party-photo', $this->html(), 'se reserva un hueco de foto que no existe');
    }

    /**
     * ❗❗ **Sin JavaScript el contador no se ofrece** (`x-cloak`): la tabla se lee en el mínimo y un botón
     * que no hace nada no se enseña. **Y sin tramo de niños, no hay contador.**
     */
    public function test_the_counter_is_offered_only_with_javascript_and_only_with_a_range(): void
    {
        preg_match_all('#<button type="button" class="party-count__btn"([^>]*)>#s', $this->html(), $m);

        $this->assertCount(2, $m[1], 'no hay dos botones en el contador');
        foreach ($m[1] as $attrs) {
            $this->assertStringContainsString('x-cloak', $attrs);
        }

        TicketType::birthdaySurfacePacks()->update(['min_qty' => 10, 'max_qty' => 10]);
        $this->assertStringNotContainsString('party-count__btn', $this->html());
    }

    /**
     * ❗ **La nota de los días de la tarifa especial se pinta SOLO si la comparativa la ofrece**
     * (`$compare['special']`), y eso lo decide el producto: si las dos tarifas cuestan lo mismo, sus filas
     * se van y la nota con ellas — una nota que explica una diferencia que no existe es ruido que además
     * contradice a la tabla. Lo cazó el arnés: al partir las pruebas, esta mitad se quedó sin guarda.
     */
    public function test_the_special_rate_note_is_painted_only_when_the_comparison_offers_it(): void
    {
        $this->assertTrue($this->datos()['compare']['special'], 'el caso nace sin sujeto');
        $this->assertStringContainsString('rates__note', $this->html());

        $special = RateType::where('key', RateType::KEY_SPECIAL)->value('id');
        foreach (TicketType::birthdaySurfacePacks()->get() as $pack) {
            $pack->prices()->where('rate_type_id', $special)->update(['amount_cents' => 1500]);
        }

        $this->assertFalse($this->datos()['compare']['special']);
        $this->assertStringNotContainsString('rates__note', $this->html(),
            'se explica una tarifa especial que no cambia ningún precio');
    }

    /** El botón de reservar es FANTASMA —el único relleno de acción es el del racimo— y abre los packs. */
    public function test_the_page_books_with_a_ghost_button_that_opens_the_packs(): void
    {
        $this->assertMatchesRegularExpression(
            '#<button type="button" class="btn btn--ghost party-compare__cta"\s+@click="\$store\.purchase\.openWith\(\{ type: \'packs\' \}\)">#',
            $this->html(),
        );
    }

    /** Sin packs vendibles la página lo dice y ofrece el contacto: vacío es una respuesta. */
    public function test_without_packs_the_page_says_so_and_offers_contact(): void
    {
        TicketType::birthdaySurfacePacks()->update(['is_active' => false]);
        $html = $this->html();

        $this->assertStringContainsString(e(__('landing.events.coming_soon')), $html);
        $this->assertStringContainsString('<a href="'.route('contacto').'" class="btn">', $html);
        $this->assertStringNotContainsString('party-compare', $html);
    }

    /** El anfitrión es el motor sin el arte: el trío es de la instancia. */
    public function test_the_host_carries_no_art(): void
    {
        $html = $this->html();

        $this->assertStringNotContainsString('trio--page', $html);
        $this->assertStringNotContainsString('trio-stand', $html);
    }

    /**
     * **Lo retirado no vuelve al marcado** (`[DECIDIDO owner]`, `#528`): ni el paso a paso ni el editor
     * de invitaciones de la página vieja. ⚠️ La clase se busca como CLASE y no como subcadena: un hash
     * de Vite puede contener «bd-» por azar.
     */
    public function test_no_class_of_the_old_page_comes_back(): void
    {
        $this->assertDoesNotMatchRegularExpression('/class="[^"]*(?<![\w-])bd-/', $this->html(),
            'ha vuelto una clase `bd-*` de la página vieja');
    }
}
