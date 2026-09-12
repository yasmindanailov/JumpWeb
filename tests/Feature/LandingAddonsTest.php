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

    /*
     * ⚠️⚠️ **AQUÍ VIVÍAN DOS CASOS DEL BLOQUE COMPACTO SOBRE ENTRADAS, y se van con su SUJETO**
     * (`#531`, `CONVENCIONES §3.quater`): `test_entries_show_their_assigned_addons_compactly` y
     * `test_addon_features_open_from_an_inline_more_info_button_on_the_landing`.
     *
     * El bloque `addons-mini` —con su «Más info» desplegable— lo pintaba `<x-site.price-card>` bajo
     * cada tarjeta de entrada de `/precios`. Esa página se rehizo desde su artboard: hoy publica una
     * TABLA por zona y los complementos van en su propio bloque de filas, con el nombre y la ventaja
     * que el panel escribe en cada uno. **No es que la regla se relaje: es que su superficie ya no
     * existe** — y lo que la sustituye lo vigila `PricingPageTest`.
     *
     * ▶ `<x-site.product-addons>` sigue vivo en `/servicios`, para un servicio que vincule un pack
     * comprable. ⚠️ En la suite **ningún servicio sembrado lo vincula** (`ticket_type_id => null`),
     * así que re-apuntar estos casos allí los habría dejado mirando el vacío, que es exactamente lo
     * que esta convención existe para impedir. Ficha en `DEUDA.md`.
     */

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

    /**
     * ⚠️ **Re-apuntado en `#531`, y la propiedad no se relaja.** El caso nació de un defecto real —un
     * complemento GRATIS rotulado dos veces, como etiqueta y como precio— y su superficie era la
     * lista compacta de la tarjeta de entrada. Hoy ese complemento sale en la FILA del bloque «Lo que
     * se añade» de `/precios`, así que la regla se comprueba ahí: **la palabra «Gratis» aparece una
     * sola vez**.
     */
    public function test_free_addon_is_not_labelled_twice(): void
    {
        $gratis = $this->addon('Pulsera', 0, 83);
        $this->anEntry()->configurableAddons()->attach($gratis->id, ['quantity_mode' => 'fixed', 'position' => 6]);

        $html = (string) $this->get('/precios')->assertOk()->getContent();

        // ⚠️ Se acota al BLOQUE, no a la página: el diccionario del cajón viaja en el payload de
        // todas las vistas y lleva esa misma palabra. Contarla sobre el documento entero mediría
        // otra cosa (la lección de `#295`: acota al elemento antes de creerte un recuento).
        preg_match('#<ul class="extras__list".*?</ul>#s', $html, $bloque);
        $this->assertNotEmpty($bloque, 'el bloque de complementos no se pinta: el caso miraría el vacío');

        $this->assertStringContainsString('Pulsera', $bloque[0]);
        $this->assertSame(
            1, substr_count($bloque[0], __('tickets.addon_badge_free')),
            'el complemento gratis se rotula dos veces: la etiqueta dice la categoría y el precio no repite la palabra.',
        );
    }

    public function test_static_socks_note_is_gone(): void
    {
        // La nota estática de calcetines se sustituye por los complementos reales por producto.
        $this->get('/cumpleanos')->assertOk()->assertDontSee('Calcetines antideslizantes obligatorios para saltar');
    }

    /**
     * **EL «MÁS INFO» DE LA FICHA ABRE LO QUE EL PANEL HAYA ESCRITO, Y SOLO SI HAY ALGO** (`#549`).
     *
     * `[owner]`: *«les añadimos la opción de "más info" y eso abre la desc de ese complemento»*.
     *
     * ❗❗ **Lo que de verdad protege este caso son los DOS campos.** El texto de un complemento vive
     * en `features` (lista) o en `description` (prosa), y en el catálogo real están repartidos: once
     * complementos llevan lo primero y ninguno lo segundo, los dos extensores de sala lo segundo y
     * ninguna lo primero. Una versión que leyera solo uno **dejaría muda a una mitad sin que nada
     * fallara**, así que aquí hay un sujeto de cada tipo.
     *
     * ▶ Y el tercer sujeto es el que no tiene nada: ahí el botón **no nace**, porque un «Más info»
     * que abre el vacío son 48 px para no decir nada (la regla de `#487`).
     *
     * ⚠️ El recuento se hace sobre la página porque en `/cumpleanos` **el único emisor de esa clase
     * es este carril** (los chips de `.addons-mini` viven en `/precios` y `/servicios`), y los
     * complementos que siembra el seeder no traen texto: la línea base es 0. Si algún día otra pieza
     * de esta página pinta un «Más info», este recuento mide otra cosa y hay que acotarlo.
     *
     * ⚠️⚠️ **Esto RECUPERA la propiedad de `test_addon_features_open_from_an_inline_more_info_button
     * _on_the_landing`**, que `#531` retiró con su sujeto (el bloque compacto de la banda vieja de
     * `/cumpleanos`). Vuelve en otra superficie y con el campo de prosa añadido.
     */
    public function test_the_more_info_button_opens_the_addon_text_and_only_exists_when_there_is_text(): void
    {
        $jump = TicketType::where('name->es', 'Cumpleaños Jump')->firstOrFail();

        $conVentajas = $this->addon('Piñata', 1500, 84, ['features' => ['es' => ['Con chuches dentro', 'La cuelga el monitor']]]);
        $conProsa = $this->addon('Photocall', 2000, 85, ['description' => ['es' => 'Un fondo para las fotos del grupo.']]);
        $sinNada = $this->addon('Globos', 500, 86);

        foreach ([$conVentajas, $conProsa, $sinNada] as $i => $extra) {
            $jump->configurableAddons()->attach($extra->id, ['quantity_mode' => 'fixed', 'position' => 10 + $i]);
        }

        $html = (string) $this->get('/cumpleanos')->assertOk()->getContent();

        // Las dos fichas con texto llevan su control, y ninguna más.
        $this->assertSame(
            2, substr_count($html, 'class="addons__moreinfo addon-card__more"'),
            'el «Más info» no sale una vez por ficha CON texto: o falta en una, o lo pinta la que no '.
            'tiene nada que abrir.',
        );
        $this->assertStringContainsString(__('tickets.addon_more_info'), $html);

        // El texto viaja en el marcado, plegado: se lee sin una segunda petición.
        $this->assertStringContainsString('<li>Con chuches dentro</li>', $html);
        $this->assertStringContainsString('Un fondo para las fotos del grupo.', $html);

        // Y el área táctil: el control mide ~20 px y el suelo del sistema es 48 (`#264`).
        $this->assertSame(
            2, substr_count($html, 'addon-card__more" data-tap'),
            'el «Más info» de la ficha ha perdido su área táctil.',
        );

        // Control: el complemento sin texto sí se pinta. Sin esto, los recuentos de arriba también
        // pasarían con las tres fichas fuera del carril.
        $this->assertStringContainsString('<span class="addon-card__name">Globos</span>', $html);
    }

    /**
     * **LAS FLECHAS DEL CARRIL SE EMITEN SIEMPRE Y SE VEN SOLO SI EL CARRIL NO CABE** (`#549`).
     *
     * `[owner]`: *«unas flechas para que el usuario sepa que hay que hacer slide, y si no puede hacer
     * slide con móvil entonces por accesibilidad necesitamos unas flechas»*.
     *
     * ❗❗ **Quién las enseña no es el servidor: es el CSS, a partir de `data-rail-scroll`** —el hecho
     * que `ui/rail-sails.js` ya publicaba para las velas—. No se puede decidir aquí porque depende
     * del ANCHO y no del número de fichas (medido en `#498`: el mismo carril cabe entero en
     * escritorio y desborda 330 px en móvil).
     * ▶ Por eso este caso comprueba las dos mitades: que el marcado sale y que la regla que las
     * muestra **cuelga del atributo**. Sin la segunda, un carril que cabe enseñaría dos botones
     * muertos; y sin JavaScript no hay atributo, que es el defecto del lado seguro.
     */
    public function test_the_rail_arrows_are_emitted_and_only_shown_when_the_rail_overflows(): void
    {
        $html = (string) $this->get('/cumpleanos')->assertOk()->getContent();

        $this->assertStringContainsString('<div class="addons-rail__nav" data-rail-nav>', $html);
        $this->assertStringContainsString('data-rail-prev', $html);
        $this->assertStringContainsString('data-rail-next', $html);
        $this->assertStringContainsString(__('landing.rates.addon_next'), $html);

        $css = (string) file_get_contents(public_path('css/landing.css'));

        $this->assertMatchesRegularExpression(
            '/\.addons-rail__nav\s*\{[^}]*display:\s*none/s', $css,
            'la nave del carril ya no nace oculta: sin JavaScript se verían dos flechas que no '.
            'pueden mover nada.',
        );
        $this->assertMatchesRegularExpression(
            '/\.addons-rail__wrap\[data-rail-scroll\]\s+\.addons-rail__nav\s*\{[^}]*display:\s*block/s', $css,
            'las flechas ya no cuelgan de `data-rail-scroll`: o no se ven nunca, o se ven en un '.
            'carril que cabe entero.',
        );

        // ❗❗ **Flotan sobre los cantos del carril y NO se tragan el gesto** (`[owner]`: en los
        // laterales). Sin `pointer-events: none` en la capa, deslizar con el dedo por encima del
        // carril dejaría de funcionar justo donde las flechas vienen a ayudar — y eso no lo ve
        // ninguna captura.
        $this->assertMatchesRegularExpression(
            '/\.addons-rail__nav\s*\{[^}]*position:\s*absolute/s', $css,
            'la nave ha dejado de flotar sobre el carril: las flechas volverían a caer debajo.',
        );
        $this->assertMatchesRegularExpression(
            '/\.addons-rail__nav\s*\{[^}]*pointer-events:\s*none/s', $css,
            'la capa de las flechas recibe el puntero: se traga el deslizamiento con el dedo sobre '.
            'el carril entero.',
        );
        $this->assertMatchesRegularExpression(
            '/\.addons-rail__nav\s\.rail-arrow\s*\{[^}]*pointer-events:\s*auto/s', $css,
            'las flechas no recuperan el puntero dentro de una capa que no lo recibe: no se podrían pulsar.',
        );
    }
}
