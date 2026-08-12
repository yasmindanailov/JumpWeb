<?php

namespace Tests\Feature;

use App\Models\RateType;
use App\Models\TicketType;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * #194 — Landing: complementos por producto (coherentes con el pivote) y selector de
 * cumpleaños que soporta N packs (antes el selector binario jump/kids mezclaba 3 packs).
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

    public function test_entries_show_their_assigned_addons_compactly(): void
    {
        $res = $this->get('/')->assertOk();

        // El bloque compacto de complementos aparece (etiqueta) con los addons enganchados a
        // las entradas (el seeder engancha Calcetines + Taquilla a todas las entradas).
        $res->assertSee('Complementos disponibles');
        $res->assertSee('addons-mini', false);
        $res->assertSee('Calcetines antideslizantes');
        $res->assertSee('Taquilla');
    }

    public function test_birthday_selector_supports_three_packs_with_unique_keys(): void
    {
        // Tercer pack con accent "jump" (no contiene "kids") — el caso que rompía el selector
        // binario: "Cumple Loco" y "Cumpleaños Jump" colapsaban a la misma clave 'jump'.
        $jump = TicketType::where('name->es', 'Cumpleaños Jump')->firstOrFail();
        $kids = TicketType::where('name->es', 'Cumpleaños Kids')->firstOrFail();
        $loco = TicketType::create([
            'name' => ['es' => 'Cumple Loco', 'en' => 'Crazy Party', 'fr' => 'Fête Folle'],
            'type' => TicketType::TYPE_PACK, 'zone_id' => $jump->zone_id,
            'is_active' => true, 'is_sellable' => true, 'seats_per_unit' => 1,
            'min_qty' => 8, 'max_qty' => 20, 'deposit_type' => 'fixed', 'deposit_value' => 3000,
            'duration_min' => 120, 'position' => 99,
        ]);
        $loco->prices()->create(['rate_type_id' => RateType::where('key', RateType::KEY_NORMAL)->value('id'), 'amount_cents' => 2000]);

        $res = $this->get('/')->assertOk();

        // Los 3 packs aparecen como pestañas...
        $res->assertSee('Cumpleaños Jump');
        $res->assertSee('Cumpleaños Kids');
        $res->assertSee('Cumple Loco');

        // ...y el selector usa el ID ÚNICO de cada pack (no la clave binaria jump/kids), así
        // que "Cumpleaños Jump" y "Cumple Loco" ya NO comparten clave.
        $res->assertSee("pack === '{$jump->id}'", false);
        $res->assertSee("pack === '{$kids->id}'", false);
        $res->assertSee("pack === '{$loco->id}'", false);
        $res->assertDontSee("pack === 'jump'", false);
        $res->assertDontSee("pack === 'kids'", false);
    }

    public function test_packs_show_their_addons_below_the_card(): void
    {
        // El seeder engancha Tarta y Monitor extra a los packs de cumpleaños → deben anunciarse.
        $res = $this->get('/')->assertOk();

        $res->assertSee('Tarta');
        $res->assertSee('Monitor extra');
    }

    public function test_choice_group_addons_render_as_choose_one(): void
    {
        // Dos complementos del mismo grupo en un pack → la landing los presenta bajo
        // «Elige una opción» (excluyentes), no como líneas que se suman.
        $jump = TicketType::where('name->es', 'Cumpleaños Jump')->firstOrFail();
        $rate = RateType::where('key', RateType::KEY_NORMAL)->value('id');
        $m1 = TicketType::create(['name' => ['es' => 'Menú Mago'], 'type' => TicketType::TYPE_ADDON, 'is_active' => true, 'is_sellable' => true, 'seats_per_unit' => 1, 'position' => 80]);
        $m2 = TicketType::create(['name' => ['es' => 'Menú Pizza'], 'type' => TicketType::TYPE_ADDON, 'is_active' => true, 'is_sellable' => true, 'seats_per_unit' => 1, 'position' => 81]);
        $m1->prices()->create(['rate_type_id' => $rate, 'amount_cents' => 0]);
        $m2->prices()->create(['rate_type_id' => $rate, 'amount_cents' => 800]);
        $jump->configurableAddons()->attach($m1->id, ['is_included' => true, 'included_quantity' => 1, 'quantity_mode' => 'per_guest', 'choice_group' => 'menu', 'position' => 1]);
        $jump->configurableAddons()->attach($m2->id, ['quantity_mode' => 'per_guest', 'choice_group' => 'menu', 'position' => 2]);

        $res = $this->get('/')->assertOk();
        $res->assertSee('addons-mini__group', false);
        $res->assertSee('Elige una opción');
        $res->assertSee('Menú Mago');
        $res->assertSee('Menú Pizza');
    }

    public function test_per_guest_addon_note_includes_the_plus_sign(): void
    {
        // #270-bis punto 3: un complemento POR-INVITADO de pago muestra «+X€/invitado» (con «+»,
        // coherente con los sueltos «+X€»), no «X€/invitado» a secas.
        $jump = TicketType::where('name->es', 'Cumpleaños Jump')->firstOrFail();
        $rate = RateType::where('key', RateType::KEY_NORMAL)->value('id');
        $menu = TicketType::create(['name' => ['es' => 'Menú extra'], 'type' => TicketType::TYPE_ADDON, 'is_active' => true, 'is_sellable' => true, 'seats_per_unit' => 1, 'position' => 90]);
        $menu->prices()->create(['rate_type_id' => $rate, 'amount_cents' => 200]);
        $jump->configurableAddons()->attach($menu->id, ['quantity_mode' => 'per_guest', 'position' => 3]);

        $res = $this->get('/')->assertOk();
        $res->assertSee('+2,00 €/invitado'); // con el «+» (la nota de precio del chip)
    }

    public function test_addon_features_open_from_an_inline_more_info_button_on_the_landing(): void
    {
        // Decisión clienta: en la LANDING (entradas y cumpleaños) las ventajas se despliegan con el
        // botón «Más info +» (mismo patrón que el sidebar de compra), pero INLINE en la fila, junto al
        // título del complemento (no en su propia línea). Las ventajas (.addons__features) van debajo.
        $jump = TicketType::where('name->es', 'Cumpleaños Jump')->firstOrFail();
        $rate = RateType::where('key', RateType::KEY_NORMAL)->value('id');
        $deluxe = TicketType::create(['name' => ['es' => 'Photocall'], 'type' => TicketType::TYPE_ADDON, 'is_active' => true, 'is_sellable' => true, 'seats_per_unit' => 1, 'position' => 82, 'features' => ['es' => ['Atrezzo temático', 'Fotos ilimitadas']]]);
        $deluxe->prices()->create(['rate_type_id' => $rate, 'amount_cents' => 1500]);
        $jump->configurableAddons()->attach($deluxe->id, ['quantity_mode' => 'fixed', 'position' => 5]);

        $res = $this->get('/')->assertOk();
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
        $res = $this->get('/')->assertOk();
        // El seeder tiene Calcetines a 0 € → badge "Gratis"; no debe haber un span de precio "Gratis".
        $res->assertDontSee('addons-mini__price">Gratis<', false);
    }

    public function test_static_socks_note_is_gone(): void
    {
        // La nota estática de calcetines se sustituye por los complementos reales por producto.
        $this->get('/')->assertOk()->assertDontSee('Calcetines antideslizantes obligatorios para saltar');
    }
}
