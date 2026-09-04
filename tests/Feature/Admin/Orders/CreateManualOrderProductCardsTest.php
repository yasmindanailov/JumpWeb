<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\ProductIcon;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Filament\Pages\CreateManualOrderPage as Cmo;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * `#462` · T2 — LAS TARJETAS DE PRODUCTO, y con ellas el crítico **C1** de la auditoría del panel.
 *
 * **El defecto que esto cierra ya costó dinero.** Una admin vendió un cumpleaños como diez entradas
 * sueltas: medido con el catálogo real, **119,00 € en vez de 180,00 €**, la sala de cumpleaños sin
 * reservar, **60 min de ocupación en vez de 120** y el formulario de invitados que no se pide —sin
 * él no hay edades, y sin edades no hay suplemento de fiesta mixta—. La causa era un desplegable
 * plano de 18 opciones cuyo rótulo, `«{zona} · {nombre}»`, **no decía de qué tipo era nada**.
 *
 * Lo que vigila, en orden de daño:
 *
 *  1. **Que los productos sigan AGRUPADOS por tipo.** Es lo que cierra el agujero: no la tarjeta, no
 *     el icono. Si alguien devuelve una lista plana, vuelve el defecto entero.
 *  2. **Que un pack enseñe su rango de invitados y una entrada no.** Es la marca inconfundible de un
 *     producto de grupo, y la única que no depende de cómo el cliente haya llamado al producto.
 *  3. **Que `pickProduct()` siga siendo UNA puerta que valida en el SERVIDOR.** Un `wire:click` se
 *     puede llamar con cualquier id.
 *  4. **Que elegir producto siga OLVIDANDO lo de la línea anterior** (hora, mínimo, campos, menores).
 *     Ese olvido lo hacía el `afterStateUpdated` del `Select` que ya no existe: si se pierde, una
 *     línea nueva nace con la hora de otro producto y nada falla.
 */
class CreateManualOrderProductCardsTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $entrada;

    private TicketType $pack;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);
        $normal = RateType::where('key', 'normal')->value('id');

        $this->entrada = TicketType::create([
            'name' => ['es' => 'Jump · 1 hora'], 'zone_id' => $this->zone->id, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
            'description' => ['es' => 'Una hora de salto libre.'],
            // ⚠️ En TRES idiomas a propósito: es el sujeto que hace observable que «Más info» usa el
            // puente `tr()` y no un recorrido a mano — el primero sacaba las tres lenguas juntas.
            'features' => [
                'es' => ['Acceso a la zona Jump'],
                'en' => ['Access to the Jump zone'],
                'fr' => ['Accès à la zone Jump'],
            ],
        ]);
        $this->entrada->prices()->create(['rate_type_id' => $normal, 'amount_cents' => 1190]);

        $this->pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños Jump'], 'type' => TicketType::TYPE_PACK,
            'zone_id' => $this->zone->id, 'duration_min' => 120,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1,
            'min_qty' => 8, 'max_qty' => 20, 'position' => 2,
            // ⚠️⚠️ **Un icono ELEGIDO, y es lo que hace observable la regla.** Sin él, `iconKey()` y
            // «dedúcelo del tipo» dan lo mismo (`gift`), así que la guarda pasaba con la deducción
            // puesta: *una guarda sin sujeto no vigila nada.* Lo dijo la mutación, no la lectura.
            'icon' => 'party',
        ]);
        $this->pack->prices()->create(['rate_type_id' => $normal, 'amount_cents' => 18000]);
    }

    private function staff(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'staff')->value('id')]);

        return $u;
    }

    private function page(): Testable
    {
        return Livewire::actingAs($this->staff())->test(Cmo::class);
    }

    // ─── 1 · El agrupado, que es lo que cierra C1 ────────────────────────────────────────────

    public function test_products_are_grouped_by_type_with_entries_first(): void
    {
        $grupos = $this->page()->instance()->productCards();

        $this->assertSame(
            [TicketType::TYPE_ENTRY, TicketType::TYPE_PACK],
            array_column($grupos, 'type'),
            'Los productos han dejado de agruparse por tipo (o las celebraciones se han puesto delante '
            .'de las entradas, que es lo que más se vende en mostrador).'
        );

        // Y cada producto cae en SU grupo: es lo que hace imposible confundir las dos familias.
        $this->assertSame([$this->entrada->id], array_column($grupos[0]['items'], 'id'));
        $this->assertSame([$this->pack->id], array_column($grupos[1]['items'], 'id'));
    }

    public function test_the_group_labels_are_the_ones_the_operator_already_knows(): void
    {
        // Mismo vocabulario que el catálogo: dos nombres para «pack» en el mismo panel sería el
        // problema que esta tanda viene a resolver.
        $grupos = $this->page()->instance()->productCards();

        $this->assertSame(__('admin.orders.create_manual.product_group.entry'), $grupos[0]['label']);
        $this->assertSame(__('admin.orders.create_manual.product_group.pack'), $grupos[1]['label']);
        $this->assertStringContainsString('Entradas', $grupos[0]['label']);
    }

    // ─── 2 · Lo que distingue un pack de una entrada ─────────────────────────────────────────

    public function test_only_a_pack_carries_its_guest_range(): void
    {
        $grupos = $this->page()->instance()->productCards();
        [$entrada, $pack] = [$grupos[0]['items'][0], $grupos[1]['items'][0]];

        $this->assertNull($entrada['min'], 'Una entrada no tiene rango de invitados: enseñarlo la haría parecer un grupo.');
        $this->assertSame(8, $pack['min']);
        $this->assertSame(20, $pack['max']);
        $this->assertTrue($pack['is_pack']);
        $this->assertFalse($entrada['is_pack']);
    }

    public function test_the_icon_comes_from_the_products_own_marker(): void
    {
        // ⚠️ **No se deduce aquí**: `iconKey()` es el puente único (`#259`), el mismo que usan la
        // tarjeta de precio pública y el cajón. Deducirlo del tipo en esta pantalla crearía una
        // segunda fuente que se separaría en cuanto alguien elija un icono en el catálogo.
        $grupos = $this->page()->instance()->productCards();

        $this->assertSame($this->entrada->iconKey(), $grupos[0]['items'][0]['icon']);
        $this->assertSame($this->pack->iconKey(), $grupos[1]['items'][0]['icon']);

        // El pack tiene icono ELEGIDO: gana el suyo, no el defecto de su tipo. Es la mitad que
        // distingue «leer el marcador» de «deducirlo del tipo», y sin ella la guarda es ciega.
        $this->assertSame('party', $grupos[1]['items'][0]['icon']);
        $this->assertNotSame(ProductIcon::DEFAULT_PACK, $grupos[1]['items'][0]['icon']);

        // Y sin icono elegido, la entrada cae al defecto de SU tipo en vez de quedarse en blanco.
        $this->assertSame(ProductIcon::DEFAULT_OTHER, $grupos[0]['items'][0]['icon']);
    }

    // ─── 3 · La puerta valida en el servidor ─────────────────────────────────────────────────

    public function test_pick_product_refuses_a_product_that_is_not_on_sale(): void
    {
        $retirado = TicketType::create([
            'name' => ['es' => 'Retirado'], 'zone_id' => $this->zone->id, 'duration_min' => 60,
            'is_sellable' => false, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 9,
        ]);

        // El navegador propone; el servidor decide (`AFORO-02`). Un `wire:click` se puede llamar con
        // cualquier número, y el producto retirado no sale en ninguna tarjeta.
        $this->page()
            ->call('pickProduct', $retirado->id)
            ->assertSet('data.sel_product_id', null)
            ->assertSet('step', Cmo::STEP_CUSTOMER);
    }

    public function test_pick_product_refuses_an_addon(): void
    {
        $addon = TicketType::create([
            'name' => ['es' => 'Tarta'], 'type' => TicketType::TYPE_ADDON,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 0, 'position' => 8,
        ]);

        // Un complemento no es una línea del pedido: se engancha a una. Elegirlo como producto
        // principal crearía una reserva que no ocupa aforo ni tiene franja.
        $this->page()->call('pickProduct', $addon->id)->assertSet('data.sel_product_id', null);
    }

    // ─── 4 · Elegir producto OLVIDA lo de la línea anterior ──────────────────────────────────

    public function test_choosing_another_product_forgets_the_previous_line(): void
    {
        // ⚠️ Este olvido lo hacía el `afterStateUpdated` del `Select` que ya no existe. Sin él, una
        // línea nueva nace con la hora, los campos y los menores del producto anterior — y **nada
        // falla**: el operador cobra una reserva con datos que no puso.
        $page = $this->page()
            ->call('pickProduct', $this->pack->id)
            ->set('data.sel_time', '10:00:00')
            ->set('data.sel_below_minimum', true)
            ->set('data.event_data', ['celebrante' => 'Ana'])
            ->set('data.sel_dependent_ids', [7]);

        $page->call('pickProduct', $this->entrada->id)
            ->assertSet('data.sel_product_id', $this->entrada->id)
            ->assertSet('data.sel_time', null)
            ->assertSet('data.sel_below_minimum', false)
            ->assertSet('data.event_data', [])
            ->assertSet('data.sel_dependent_ids', []);
    }

    public function test_choosing_a_pack_starts_at_its_contractable_minimum(): void
    {
        // Un pack de 8 a 20 no puede nacer en 1: el operador tendría que corregirlo siempre, y la
        // oferta de horas se calcularía para una cantidad que no se puede vender.
        $this->page()
            ->call('pickProduct', $this->pack->id)
            ->assertSet('data.sel_qty', 8);

        $this->page()
            ->call('pickProduct', $this->entrada->id)
            ->assertSet('data.sel_qty', 1);
    }

    // ─── 5 · «Más info» solo enseña lo que el panel sabe rellenar ────────────────────────────

    public function test_more_info_shows_the_features_in_one_language_only(): void
    {
        // ⚠️⚠️ **Medido en navegador antes de tener guarda**: «Access to the Jump zone · Acceso a la
        // zona Jump · Accès à la zone Jump» — las tres lenguas en la misma línea, porque el primer
        // recorrido leía `features` en crudo en vez de pasar por `tr()`. Nada fallaba.
        // ⚠️ **Por CONDUCTA, no por `assertSee`**: el modal de Filament es un `wire:partial` y no
        // está en el HTML de la página — la trampa de `#161`, que este caso volvió a pagar.
        $campos = $this->page()->instance()->productInfoFields($this->entrada->id);

        $ventajas = collect($campos)
            ->first(fn ($c): bool => $c->getName() === 'info_features');

        $this->assertNotNull($ventajas, 'El modal ha dejado de enseñar las ventajas del producto.');

        $texto = (string) $ventajas->getState();
        $this->assertStringContainsString('Acceso a la zona Jump', $texto);
        $this->assertStringNotContainsString('Access to the Jump zone', $texto);
        $this->assertStringNotContainsString('Accès à la zone Jump', $texto);
    }

    public function test_more_info_is_offered_only_when_there_is_something_public_to_show(): void
    {
        $grupos = $this->page()->instance()->productCards();

        $this->assertTrue($grupos[0]['items'][0]['has_info'], 'La entrada tiene descripción: tiene que ofrecer «Más info».');
        $this->assertFalse($grupos[1]['items'][0]['has_info'], 'Sin nada público que enseñar, el botón abriría un modal vacío.');
    }
}
