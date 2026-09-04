<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Filament\Pages\CreateManualOrderPage as Cmo;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * `#462` · T1 — LA ESTRUCTURA DE SIETE PASOS del asistente de crear pedido.
 *
 * Lo que vigila, en orden de daño:
 *
 *  1. **Que un paso sin nada que preguntar se SALTE.** Medido sobre el catálogo real: de los 18
 *     productos vendibles, **16 no tienen ni un campo que rellenar** y 7 no tienen ni campos ni
 *     complementos. Sin el salto, vender una entrada obliga a pasar por una pantalla en blanco y
 *     una excursión por dos — más fricción, que es lo contrario del encargo.
 *  2. **Que el auto-avance no atrape al operador.** Si se enganchase al ESTADO en vez de al CAMBIO,
 *     volver atrás a «Cuándo» con la hora ya puesta rebotaría hacia adelante otra vez y no habría
 *     forma de corregir nada. Es el modo de fallo más caro de esta tanda y **no se ve en una
 *     captura**: la pantalla parece correcta, simplemente no deja volver.
 *  3. **Que «Añadir al carrito» cuelgue del ÚLTIMO paso con algo que preguntar**, que es variable
 *     según el producto. Si se clavara en «Extras», un producto sin complementos se quedaría sin
 *     forma de añadir la línea.
 *  4. **Que el indicador no permita saltar hacia ADELANTE**: sería contestar preguntas por omisión.
 */
class CreateManualOrderStepsTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    /** Entrada pelada: sin campos de evento y sin complementos → «Datos» y «Extras» se saltan. */
    private TicketType $entrada;

    /** Pack con campos de evento Y complemento → los siete pasos, ninguno saltado. */
    private TicketType $pack;

    /** Entrada CON complemento: «Datos» vacío y «Extras» no → el salto del MEDIO. */
    private TicketType $entradaConExtra;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);
        $this->date = Carbon::today()->addDays(2)->toDateString();

        // ⚠️ **TRES franjas seguidas, y no es adorno**: el pack dura 120 min, así que con una sola
        // franja de 60 `SlotOffer` no lo ofrece y `pickTime()` sale sin elegir nada. La primera
        // versión de este fixture tenía una, y con ella un caso pasaba **por el motivo equivocado**
        // —«no avanza» era cierto, pero porque no se había elegido hora—.
        foreach ([['10:00:00', '11:00:00'], ['11:00:00', '12:00:00'], ['12:00:00', '13:00:00']] as [$de, $a]) {
            Slot::create([
                'zone_id' => $this->zone->id, 'date' => $this->date,
                'start_time' => $de, 'end_time' => $a,
                'capacity' => 60, 'online_capacity' => 60,
            ]);
        }

        $normal = RateType::where('key', 'normal')->value('id');

        $this->entrada = TicketType::create([
            'name' => ['es' => 'Jump · 1 hora'], 'zone_id' => $this->zone->id, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
        $this->entrada->prices()->create(['rate_type_id' => $normal, 'amount_cents' => 1000]);

        $this->pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños'], 'type' => TicketType::TYPE_PACK,
            'zone_id' => $this->zone->id, 'duration_min' => 120,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1,
            'min_qty' => 8, 'max_qty' => 20, 'position' => 2,
            'event_fields' => [['key' => 'celebrante', 'label' => ['es' => 'Nombre'], 'type' => 'text', 'required' => true]],
        ]);
        $this->pack->prices()->create(['rate_type_id' => $normal, 'amount_cents' => 18000]);

        $tarta = TicketType::create([
            'name' => ['es' => 'Tarta'], 'type' => TicketType::TYPE_ADDON,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 0, 'position' => 3,
        ]);
        $tarta->prices()->create(['rate_type_id' => $normal, 'amount_cents' => 2500]);
        $this->pack->addons()->attach($tarta->id, ['position' => 1, 'max_qty' => 2]);

        // ⚠️ **El tercer sujeto es el que hace visible el SALTO DEL MEDIO**: una entrada con
        // complemento deja «Datos» vacío y «Extras» con algo, así que avanzar desde «Cuándo» tiene
        // que aterrizar en el 5 saltándose el 4. Sin este producto, `next()` podía dejar de saltar
        // y la suite seguía verde (lo dijo la mutación, no la lectura).
        $this->entradaConExtra = TicketType::create([
            'name' => ['es' => 'Jump · 2 horas'], 'zone_id' => $this->zone->id, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 4,
        ]);
        $this->entradaConExtra->prices()->create(['rate_type_id' => $normal, 'amount_cents' => 1500]);
        $this->entradaConExtra->addons()->attach($tarta->id, ['position' => 1, 'max_qty' => 2]);
    }

    private function staff(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'staff')->value('id')]);

        return $u;
    }

    private function customer(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'customer')->value('id')]);

        return $u;
    }

    /** Deja el asistente con cliente y producto elegidos, o sea en el paso «Cuándo». */
    private function conProducto(TicketType $type): Testable
    {
        return Livewire::actingAs($this->staff())
            ->test(Cmo::class)
            ->set('data.customer_id', $this->customer()->id)
            ->call('pickProduct', $type->id);
    }

    // ─── 1 · Saltar lo que no pregunta nada ──────────────────────────────────────────────────

    public function test_a_step_with_nothing_to_ask_is_skipped(): void
    {
        $page = $this->conProducto($this->entrada)->assertSet('step', Cmo::STEP_WHEN);
        $instancia = $page->instance();

        // La entrada no tiene campos de evento, ni menores (el cliente no declaró ninguno), ni
        // complementos: los dos pasos condicionales no preguntan nada.
        $this->assertFalse($instancia->stepHasSomethingToAsk(Cmo::STEP_DETAILS));
        $this->assertFalse($instancia->stepHasSomethingToAsk(Cmo::STEP_EXTRAS));

        // Y por tanto «Cuándo» es el último paso de la línea: de él cuelga «Añadir al carrito».
        $this->assertTrue($instancia->isLastLineStep());
    }

    public function test_a_pack_with_fields_and_addons_asks_in_every_step(): void
    {
        $page = $this->conProducto($this->pack)->assertSet('step', Cmo::STEP_WHEN);
        $instancia = $page->instance();

        $this->assertTrue($instancia->stepHasSomethingToAsk(Cmo::STEP_DETAILS));
        $this->assertTrue($instancia->stepHasSomethingToAsk(Cmo::STEP_EXTRAS));

        // Con pasos por delante, «Cuándo» NO es el último: aquí se avanza, no se añade.
        $this->assertFalse($instancia->isLastLineStep());
    }

    public function test_choosing_the_hour_of_a_bare_entry_does_not_jump_over_the_add_to_cart(): void
    {
        // ⚠️ El auto-avance NO puede saltarse el último paso de la línea: si lo hiciera, elegir la
        // hora de una entrada llevaría directo al carrito **sin haber añadido nada**.
        $page = $this->conProducto($this->entrada)
            ->set('data.sel_qty', 2)
            ->set('data.sel_date', $this->date)
            ->call('pickTime', '10:00:00');

        // ⚠️ **La hora tiene que haberse ELEGIDO de verdad**: sin esta línea el caso pasaría también
        // si `pickTime()` hubiera salido sin hacer nada, que es un mundo distinto y no el que se
        // quiere probar. (Pasó: con una sola franja el pack no era ofrecible y el caso salía verde.)
        $page->assertSet('data.sel_time', '10:00:00');
        $page->assertSet('step', Cmo::STEP_WHEN);
        $this->assertSame([], $page->get('cart'));
    }

    public function test_the_hour_of_a_pack_advances_to_the_next_step_that_asks(): void
    {
        $this->conProducto($this->pack)
            ->set('data.sel_qty', 8)
            ->set('data.sel_date', $this->date)
            ->call('pickTime', '10:00:00')
            ->assertSet('step', Cmo::STEP_DETAILS);
    }

    public function test_advancing_jumps_over_an_empty_step_in_the_middle(): void
    {
        // «Datos» no pregunta nada para una entrada, pero «Extras» sí: avanzar desde «Cuándo» tiene
        // que aterrizar en Extras, no en la pantalla en blanco de Datos.
        $page = $this->conProducto($this->entradaConExtra)->assertSet('step', Cmo::STEP_WHEN);

        $this->assertFalse($page->instance()->stepHasSomethingToAsk(Cmo::STEP_DETAILS));
        $this->assertTrue($page->instance()->stepHasSomethingToAsk(Cmo::STEP_EXTRAS));

        $page->set('data.sel_qty', 1)
            ->set('data.sel_date', $this->date)
            ->call('pickTime', '10:00:00')
            ->assertSet('step', Cmo::STEP_EXTRAS);
    }

    public function test_going_back_jumps_over_the_empty_steps(): void
    {
        // Desde el carrito, con una entrada pelada, atrás tiene que llevar a «Cuándo»: Extras y
        // Datos no preguntan nada y pararse en ellos sería enseñar una pantalla vacía.
        $page = $this->conProducto($this->entrada)
            ->set('data.sel_qty', 1)
            ->set('data.sel_date', $this->date)
            ->set('data.sel_time', '10:00:00')
            ->call('next')
            ->assertSet('step', Cmo::STEP_CART);

        $page->call('back')->assertSet('step', Cmo::STEP_WHEN);
    }

    // ─── 2 · El auto-avance no atrapa ────────────────────────────────────────────────────────

    public function test_going_back_with_the_hour_already_chosen_does_not_bounce_forward(): void
    {
        // ⚠️⚠️ **Éste es el caso que separa «engancharse al CAMBIO» de «mirar el ESTADO».** Con la
        // hora ya puesta, volver atrás desde «Datos» tiene que DEJARTE en «Cuándo». Si el avance
        // mirase el estado, rebotaría y el operador no podría corregir la hora nunca.
        $page = $this->conProducto($this->pack)
            ->set('data.sel_qty', 8)
            ->set('data.sel_date', $this->date)
            ->call('pickTime', '10:00:00')
            ->assertSet('step', Cmo::STEP_DETAILS);

        $page->call('back')->assertSet('step', Cmo::STEP_WHEN);

        // Y sigue ahí tras un render más: no hay rebote diferido.
        $page->set('data.sel_qty', 9)->assertSet('step', Cmo::STEP_WHEN);
    }

    public function test_back_from_when_skips_nothing_and_lands_on_the_product(): void
    {
        $this->conProducto($this->entrada)
            ->call('back')->assertSet('step', Cmo::STEP_PRODUCT)
            ->call('back')->assertSet('step', Cmo::STEP_CUSTOMER)
            // El primero no retrocede más.
            ->call('back')->assertSet('step', Cmo::STEP_CUSTOMER);
    }

    // ─── 3 · Añadir la línea termina en el carrito ───────────────────────────────────────────

    public function test_adding_the_line_leaves_the_operator_in_the_cart(): void
    {
        $page = $this->conProducto($this->entrada)
            ->set('data.sel_qty', 2)
            ->set('data.sel_date', $this->date)
            ->set('data.sel_time', '10:00:00')
            ->call('next');

        $page->assertSet('step', Cmo::STEP_CART);
        $this->assertCount(1, $page->get('cart'));

        // Y «añadir más productos» devuelve al principio de una línea nueva, no al paso anterior.
        $page->call('addMoreProducts')->assertSet('step', Cmo::STEP_PRODUCT);
    }

    // ─── 4 · El indicador solo lleva hacia atrás ─────────────────────────────────────────────

    public function test_the_step_indicator_only_goes_backwards(): void
    {
        $page = $this->conProducto($this->entrada)->assertSet('step', Cmo::STEP_WHEN);

        // Hacia adelante, no: sería contestar preguntas por omisión.
        $page->call('goToStep', Cmo::STEP_CART)->assertSet('step', Cmo::STEP_WHEN);

        // Hacia atrás, sí.
        $page->call('goToStep', Cmo::STEP_PRODUCT)->assertSet('step', Cmo::STEP_PRODUCT);
    }

    public function test_the_step_indicator_never_lands_on_a_step_with_nothing_to_ask(): void
    {
        // Con una entrada pelada, «Datos» está saltado: el indicador no puede llevar a una pantalla
        // en blanco aunque el paso quede por detrás.
        $page = $this->conProducto($this->entrada)
            ->set('data.sel_qty', 1)
            ->set('data.sel_date', $this->date)
            ->set('data.sel_time', '10:00:00')
            ->call('next')
            ->assertSet('step', Cmo::STEP_CART);

        $page->call('goToStep', Cmo::STEP_DETAILS)->assertSet('step', Cmo::STEP_CART);
    }

    public function test_no_step_is_marked_skipped_before_a_product_is_chosen(): void
    {
        // ⚠️ Lo que decide si «Datos» y «Extras» preguntan algo es el PRODUCTO: antes de elegirlo la
        // respuesta no se sabe. El indicador arrancaba diciendo «sin nada que rellenar» en el paso 1
        // —afirmando lo que todavía no se sabía—, y lo vio la sonda de navegador, no un test.
        $page = Livewire::actingAs($this->staff())->test(Cmo::class);

        foreach ([Cmo::STEP_DETAILS, Cmo::STEP_EXTRAS] as $paso) {
            $this->assertFalse($page->instance()->stepIsSkipped($paso));
        }

        // Con el producto elegido sí se sabe, y entonces se dice.
        $conProducto = $this->conProducto($this->entrada);
        $this->assertTrue($conProducto->instance()->stepIsSkipped(Cmo::STEP_DETAILS));
        $this->assertTrue($conProducto->instance()->stepIsSkipped(Cmo::STEP_EXTRAS));
    }

    // ─── 5 · La vista ────────────────────────────────────────────────────────────────────────

    public function test_the_cart_step_is_one_column_and_does_not_repeat_the_summary(): void
    {
        $vista = (string) file_get_contents(base_path('resources/views/filament/pages/create-manual-order.blade.php'));

        // ⚠️ El aside se RETIRA en el paso del carrito, no se esconde con CSS: si solo se ocultara,
        // el resumen seguiría en el árbol y se anunciaría dos veces a un lector de pantalla.
        $this->assertStringContainsString('@unless ($enCarrito)', $vista);
        $this->assertStringContainsString("'cmo-layout--single' => \$enCarrito", $vista);
    }
}
