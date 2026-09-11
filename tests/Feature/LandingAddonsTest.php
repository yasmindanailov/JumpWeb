<?php

namespace Tests\Feature;

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Content\Services\LandingAddonPresenter;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * #194 — Landing: complementos por producto (coherentes con el pivote) y N packs de cumpleaños.
 *
 * ⚠️⚠️ **`#528` REPARTIÓ ESTOS CASOS ENTRE DOS SUPERFICIES, y re-apuntar no es debilitar.**
 * El bloque compacto (`addons-mini`: incluidos, «Gratis», «Más info») vivía en la banda heredada
 * de `/cumpleanos`, que se retiró; el componente sigue vivo en la tarjeta de TARIFA (`/precios`) y
 * en `/servicios`, así que sus casos se miran en `/precios` con una entrada. Lo que es de los
 * PACKS —una columna por pack, el menú en su bloque, los complementos en el carril— se mira en la
 * página rehecha, que es donde hoy se pinta.
 */
class LandingAddonsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LandingContentSeeder::class);
        Cache::flush();
    }

    private function anEntry(): TicketType
    {
        return TicketType::ofType(TicketType::TYPE_ENTRY)->orderBy('position')->firstOrFail();
    }

    private function addon(string $name, int $cents, int $position, array $extra = []): TicketType
    {
        $addon = TicketType::create(['name' => ['es' => $name], 'type' => TicketType::TYPE_ADDON, 'is_active' => true, 'is_sellable' => true, 'seats_per_unit' => 1, 'position' => $position] + $extra);
        $addon->prices()->create(['rate_type_id' => RateType::where('key', RateType::KEY_NORMAL)->value('id'), 'amount_cents' => $cents]);

        return $addon;
    }

    public function test_entries_show_their_assigned_addons_compactly(): void
    {
        $res = $this->get('/precios')->assertOk();

        // El bloque compacto de complementos aparece (etiqueta) con los addons enganchados a
        // las entradas (el seeder engancha Calcetines + Taquilla a todas las entradas).
        $res->assertSee('Complementos disponibles');
        $res->assertSee('addons-mini', false);
        $res->assertSee('Calcetines antideslizantes');
        $res->assertSee('Taquilla');
    }

    public function test_the_comparison_gets_one_column_per_pack(): void
    {
        // El caso que rompía el selector binario de antes: un TERCER pack. Hoy cada pack es una
        // columna de la comparativa, y el titular cuenta los que hay.
        $jump = TicketType::where('name->es', 'Cumpleaños Jump')->firstOrFail();
        $loco = TicketType::create([
            'name' => ['es' => 'Cumple Loco', 'en' => 'Crazy Party', 'fr' => 'Fête Folle'],
            'type' => TicketType::TYPE_PACK, 'zone_id' => $jump->zone_id,
            'is_active' => true, 'is_sellable' => true, 'seats_per_unit' => 1,
            'min_qty' => 8, 'max_qty' => 20, 'deposit_type' => 'fixed', 'deposit_value' => 3000,
            'duration_min' => 120, 'position' => 99,
        ]);
        $loco->prices()->create(['rate_type_id' => RateType::where('key', RateType::KEY_NORMAL)->value('id'), 'amount_cents' => 2000]);

        $html = (string) $this->get('/cumpleanos')->assertOk()->getContent();

        $this->assertSame(3, substr_count($html, '<th scope="col" class="party-compare__pack">'));
        foreach (['Cumpleaños Jump', 'Cumpleaños Kids', 'Cumple Loco'] as $name) {
            $this->assertStringContainsString('<th scope="col" class="party-compare__pack">'.$name.'</th>', $html);
        }
        $this->assertStringContainsString(trans_choice('landing.birthday.packs_title', 3, ['count' => 3]), $html);
    }

    public function test_the_packs_booking_addons_go_to_the_rail(): void
    {
        // El seeder engancha Tarta y Monitor extra a los packs de cumpleaños → al carril.
        $res = $this->get('/cumpleanos')->assertOk();

        $res->assertSee('<span class="addon-card__name">Tarta</span>', false);
        $res->assertSee('<span class="addon-card__name">Monitor extra</span>', false);
    }

    public function test_choice_group_addons_get_their_own_block_and_leave_the_rail(): void
    {
        // Dos complementos del mismo grupo en un pack: el menú es una ELECCIÓN dentro del pack, no
        // un complemento que se suma — va a su bloque y no al carril (`#528`).
        $jump = TicketType::where('name->es', 'Cumpleaños Jump')->firstOrFail();
        $m1 = $this->addon('Menú Mago', 0, 80);
        $m2 = $this->addon('Menú Pizza', 800, 81);
        $jump->configurableAddons()->attach($m1->id, ['is_included' => true, 'included_quantity' => 1, 'quantity_mode' => 'per_guest', 'choice_group' => 'menu', 'position' => 1]);
        $jump->configurableAddons()->attach($m2->id, ['quantity_mode' => 'per_guest', 'choice_group' => 'menu', 'position' => 2]);

        $res = $this->get('/cumpleanos')->assertOk();

        $res->assertSee(__('landing.birthday.menu_title'));
        $res->assertSee('<h3 class="party-menu__name">Menú Mago</h3>', false);
        $res->assertSee('<h3 class="party-menu__name">Menú Pizza</h3>', false);
        $res->assertDontSee('<span class="addon-card__name">Menú Mago</span>', false);
        $res->assertDontSee('<span class="addon-card__name">Menú Pizza</span>', false);
    }

    public function test_per_guest_addon_note_includes_the_plus_sign(): void
    {
        // #270-bis punto 3: un complemento POR-INVITADO de pago muestra «+X€/invitado» (con «+»,
        // coherente con los sueltos «+X€»), no «X€/invitado» a secas.
        $jump = TicketType::where('name->es', 'Cumpleaños Jump')->firstOrFail();
        $menu = $this->addon('Menú extra', 200, 90);
        $jump->configurableAddons()->attach($menu->id, ['quantity_mode' => 'per_guest', 'position' => 3]);

        // La nota del bloque compacto (la que pintan la tarjeta de tarifa y `/servicios`).
        $row = collect(LandingAddonPresenter::rows($jump->fresh(['addons.prices.rateType']), true))->firstWhere('name', 'Menú extra');
        $this->assertSame('+2,00 €/invitado', $row['note']);

        // Y en la página, el carril escribe la cifra con su signo y la unidad del PIVOTE.
        $this->get('/cumpleanos')->assertOk()
            ->assertSee('<span class="addon-card__unit">'.__('landing.rates.addon_per_guest').'</span>', false);
    }

    public function test_addon_features_open_from_an_inline_more_info_button_on_the_landing(): void
    {
        // Decisión clienta: en la LANDING las ventajas se despliegan con el botón «Más info +»
        // (mismo patrón que el sidebar de compra), INLINE en la fila, junto al título del
        // complemento. Las ventajas (.addons__features) van debajo.
        $deluxe = $this->addon('Photocall', 1500, 82, ['features' => ['es' => ['Atrezzo temático', 'Fotos ilimitadas']]]);
        $this->anEntry()->configurableAddons()->attach($deluxe->id, ['quantity_mode' => 'fixed', 'position' => 5]);

        $res = $this->get('/precios')->assertOk();
        $res->assertSee('addons__moreinfo', false);          // botón «Más info» (mismo del sidebar)
        $res->assertSee('addons-mini__moreinfo', false);     // modificador INLINE (en la fila, junto al título)
        $res->assertSee('addons__features', false);          // lista de ventajas desplegable
        $res->assertSee('Atrezzo temático');                 // la ventaja, debajo
        // Revertido: ya NO se usa el patrón «nombre como disparador».
        $res->assertDontSee('addons-mini__name--toggle', false);
    }

    public function test_free_addon_is_not_labelled_twice(): void
    {
        // Regresión del bug reportado: un complemento gratis (0 €) no debe mostrar "Gratis"
        // como etiqueta Y como precio. El badge "Gratis" aparece; el precio "Gratis" no.
        $gratis = $this->addon('Pulsera', 0, 83);
        $this->anEntry()->configurableAddons()->attach($gratis->id, ['quantity_mode' => 'fixed', 'position' => 6]);

        $res = $this->get('/precios')->assertOk();
        $res->assertSee('Pulsera');
        $res->assertDontSee('addons-mini__price">Gratis<', false);
    }

    public function test_static_socks_note_is_gone(): void
    {
        // La nota estática de calcetines se sustituye por los complementos reales por producto.
        $this->get('/cumpleanos')->assertOk()->assertDontSee('Calcetines antideslizantes obligatorios para saltar');
    }
}
