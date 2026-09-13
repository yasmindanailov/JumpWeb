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
 * #194 — Landing: complementos por producto y N packs de cumpleaños.
 *
 * ❗❗❗ **`#583` · LOS COMPLEMENTOS YA NO SE PUBLICAN EN LA WEB** (`[DECIDIDO owner, 2026-09-13]`):
 * *«quita los complementos de la página web, solo los dejamos en el SPA al reservar»*. Se retiraron
 * el carril de la portada, «Tu fiesta, tu manera» (portada y `/cumpleanos`), el bloque de `/precios`
 * y la fila de la hora extra, y con ellos los casos que vigilaban su FORMA —el «Más info» de la ficha,
 * las flechas del carril, el «Gratis» de la lista—, que se van con su sujeto (`CONVENCIONES §3.quater`).
 *
 * ▶ Lo que queda aquí es la GUARDA de esa decisión y lo que no era complemento: una columna por pack
 * y el menú, que el canvas define como *«una elección dentro del pack»*.
 * ▶ `<x-site.product-addons>` sigue vivo en `/servicios` (pausada); su nota por invitado se vigila en
 * el presentador, que es donde se compone.
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

    private function addon(string $name, int $cents, int $position, array $extra = []): TicketType
    {
        $addon = TicketType::create(['name' => ['es' => $name], 'type' => TicketType::TYPE_ADDON, 'is_active' => true, 'is_sellable' => true, 'seats_per_unit' => 1, 'position' => $position] + $extra);
        $addon->prices()->create(['rate_type_id' => RateType::where('key', RateType::KEY_NORMAL)->value('id'), 'amount_cents' => $cents]);

        return $addon;
    }

    /**
     * **NINGUNA PÁGINA PÚBLICA PUBLICA COMPLEMENTOS** (`#583`).
     *
     * ⚠️ El caso nace CON sujeto —los packs sembrados traen complementos de reservar y aquí se engancha
     * uno a una entrada—: sin él, que no salga ninguna ficha no demostraría nada.
     * ⚠️ Se buscan las PIEZAS que los pintaban y no sus nombres: el diccionario y el catálogo del cajón
     * viajan en el payload de todas las vistas, y un nombre de complemento puede estar ahí con toda razón.
     */
    public function test_the_web_does_not_publish_addons(): void
    {
        $pulsera = $this->addon('Pulsera', 0, 83);
        TicketType::ofType(TicketType::TYPE_ENTRY)->orderBy('position')->firstOrFail()
            ->configurableAddons()->attach($pulsera->id, ['quantity_mode' => 'fixed', 'position' => 6]);

        $this->assertNotEmpty(
            LandingAddonPresenter::unique(TicketType::birthdaySurfacePacks()->with('addons.prices.rateType')->get()),
            'el caso nace sin sujeto: los packs no tienen complementos',
        );

        foreach (['/', '/cumpleanos', '/precios'] as $ruta) {
            $html = (string) $this->get($ruta)->assertOk()->getContent();

            foreach (['addons-rail', 'addon-card', 'extras__list'] as $pieza) {
                $this->assertStringNotContainsString($pieza, $html,
                    "«{$ruta}» vuelve a publicar complementos (`{$pieza}`). `[DECIDIDO owner]`: solo se ".
                    'ofrecen en el cajón, al reservar (`#583`).');
            }
        }
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

    public function test_choice_group_addons_get_their_own_block(): void
    {
        // Dos complementos del mismo grupo en un pack: el menú es una ELECCIÓN dentro del pack, no
        // un complemento que se suma — tiene su bloque (`#528`), y se queda aunque los complementos
        // salieran de la web (`#583`).
        $jump = TicketType::where('name->es', 'Cumpleaños Jump')->firstOrFail();
        $m1 = $this->addon('Menú Mago', 0, 80);
        $m2 = $this->addon('Menú Pizza', 800, 81);
        $jump->configurableAddons()->attach($m1->id, ['is_included' => true, 'included_quantity' => 1, 'quantity_mode' => 'per_guest', 'choice_group' => 'menu', 'position' => 1]);
        $jump->configurableAddons()->attach($m2->id, ['quantity_mode' => 'per_guest', 'choice_group' => 'menu', 'position' => 2]);

        $res = $this->get('/cumpleanos')->assertOk();

        $res->assertSee(__('landing.birthday.menu_title'));
        $res->assertSee('<h3 class="party-menu__name">Menú Mago</h3>', false);
        $res->assertSee('<h3 class="party-menu__name">Menú Pizza</h3>', false);
    }

    public function test_per_guest_addon_note_includes_the_plus_sign(): void
    {
        // #270-bis punto 3: un complemento POR-INVITADO de pago muestra «+X€/invitado» (con «+»,
        // coherente con los sueltos «+X€»), no «X€/invitado» a secas. Es la nota del bloque compacto
        // que pinta `/servicios`.
        $jump = TicketType::where('name->es', 'Cumpleaños Jump')->firstOrFail();
        $menu = $this->addon('Menú extra', 200, 90);
        $jump->configurableAddons()->attach($menu->id, ['quantity_mode' => 'per_guest', 'position' => 3]);

        $row = collect(LandingAddonPresenter::rows($jump->fresh(['addons.prices.rateType']), true))->firstWhere('name', 'Menú extra');
        $this->assertSame('+2,00 €/invitado', $row['note']);
    }

    public function test_static_socks_note_is_gone(): void
    {
        // La nota estática de calcetines se sustituyó por los complementos reales por producto.
        $this->get('/cumpleanos')->assertOk()->assertDontSee('Calcetines antideslizantes obligatorios para saltar');
    }
}
