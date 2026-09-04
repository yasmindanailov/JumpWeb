<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Filament\Pages\CreateManualOrderPage;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * **El panel descuenta su PROPIA cesta al ofrecer horas** (`#464`, T3 de
 * `specs/asistente-crear-pedido.md` §2.3 — la única corrección de DOMINIO del asistente).
 *
 * Hasta hoy `CreateManualOrderPage::timeMap()` llamaba a `SlotOffer::offerableTimes()` **sin
 * ocupantes provisionales** mientras la web sí se los pasaba (`AvailabilityReader`). Medido: la
 * oferta no divergía en ninguna otra cosa —los dos son `SlotOffer`, 177 días y 11 horas idénticos—,
 * pero el operador que metía 20 entradas de las 17:00 en el carrito y volvía a por más veía **las
 * 40 plazas de antes**, y el checkout se lo rechazaba después.
 *
 * Era un borde declarado (`specs/hora-extra.md` §8.3) mientras añadir una segunda línea al mismo
 * pedido era raro. El asistente lo puso en el camino normal con «Añadir más productos».
 *
 * ⚠️ **Lo que estas guardas fijan no es un número: es de dónde sale.** La cuenta la hace
 * `CartOccupants::forCart()` —la derivación ÚNICA que comparten el cobro (`OrderCreator`) y la
 * oferta (web y API)— y el panel la PIDE. Una copia local que contara distinto las hijas que ocupan
 * o las líneas de pack volvería a ofrecer horas que el propio checkout rechaza (`AFORO-02`).
 */
class CreateManualOrderCartAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zonaEntradas;

    private Zone $zonaPacks;

    private TicketType $entrada;

    private TicketType $pack;

    private string $dia;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-14 09:00:00');

        RateType::firstOrCreate(
            ['key' => RateType::KEY_NORMAL],
            ['label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0],
        );
        $tarifa = RateType::where('key', RateType::KEY_NORMAL)->value('id');

        $this->dia = Carbon::parse('2026-09-16')->toDateString();

        $this->zonaEntradas = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP'], 'position' => 1]);
        $this->entrada = TicketType::create([
            'name' => ['es' => 'Jump · 1 hora'], 'zone_id' => $this->zonaEntradas->id, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
        $this->entrada->prices()->create(['rate_type_id' => $tarifa, 'amount_cents' => 990]);

        // La zona de PACKS es otro pool de aforo (#82): cupo en INVITADOS por franja.
        $this->zonaPacks = Zone::create([
            'slug' => 'cumples', 'name' => ['es' => 'Cumpleaños'], 'position' => 2,
            'max_guests_per_slot' => 30,
        ]);
        $this->pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños Jump'], 'type' => TicketType::TYPE_PACK,
            'zone_id' => $this->zonaPacks->id, 'duration_min' => 120,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1,
            'min_qty' => 8, 'max_qty' => 20, 'position' => 2,
        ]);
        $this->pack->prices()->create(['rate_type_id' => $tarifa, 'amount_cents' => 1500]);

        // Dos franjas seguidas en cada zona: la fiesta dura 120 min y tiene que caber entera.
        foreach ([$this->zonaEntradas, $this->zonaPacks] as $zona) {
            foreach (['10:00:00' => '11:00:00', '11:00:00' => '12:00:00'] as $desde => $hasta) {
                Slot::create([
                    'zone_id' => $zona->id, 'date' => $this->dia,
                    'start_time' => $desde, 'end_time' => $hasta,
                    'capacity' => 20, 'online_capacity' => 20,
                ]);
            }
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ─── Los casos ────────────────────────────────────────────────────────────────────────────

    /**
     * ⚠️ **El caso que el asistente convirtió en camino normal**: una línea ya añadida retiene sus
     * plazas, así que la siguiente ve lo que QUEDA. Con el control delante (carrito vacío → 20) para
     * que el número no pueda salir bien por casualidad.
     */
    public function test_a_line_in_the_cart_discounts_the_seats_of_its_own_slot(): void
    {
        $componente = $this->pagina($this->entrada);

        $this->assertSame(
            [['10:00:00', 20, true], ['11:00:00', 20, true]],
            $this->horas($componente),
            'CONTROL: con el carrito vacío la franja tiene que ofrecer su aforo entero.',
        );

        $componente->set('cart', [$this->linea($this->entrada, '10:00:00', 15)]);

        $this->assertSame(
            [['10:00:00', 5, true], ['11:00:00', 20, true]],
            $this->horas($componente),
            "La oferta del panel ha vuelto a ignorar su propia cesta.\n".
            'Con 15 plazas retenidas a las 10:00 quedan 5, y las 11:00 no se tocan.',
        );
    }

    /**
     * ⚠️⚠️ **Una franja que la cesta deja llena se enseña DESHABILITADA, no se esconde** —igual que
     * en la web—, y `pickTime()` la rechaza **en el servidor**: el `wire:click` lo pinta el navegador
     * y se puede llamar con cualquier hora (`AFORO-02`).
     */
    public function test_a_slot_filled_by_the_cart_is_offered_disabled_and_refused_by_the_server(): void
    {
        $componente = $this->pagina($this->entrada)
            ->set('cart', [$this->linea($this->entrada, '10:00:00', 20)]);

        $this->assertSame(
            [['10:00:00', 0, false], ['11:00:00', 20, true]],
            $this->horas($componente),
            'La franja llena por la cesta tiene que seguir VIÉNDOSE, marcada como no vendible.',
        );

        $componente->call('pickTime', '10:00:00');

        $this->assertNull(
            $componente->get('data.sel_time'),
            'El servidor ha aceptado una hora que su propia cesta deja sin plazas.',
        );
    }

    /**
     * ⚠️ **El CUPO de packs es otro pool y también se descuenta** (`CartOccupants::packs()`): una
     * fiesta en el carrito ocupa invitados de la zona de packs durante todo su tramo —incluida la
     * franja siguiente, porque dura 120 min—, y por eso las 11:00 bajan igual que las 10:00.
     */
    public function test_a_pack_in_the_cart_discounts_the_guest_quota_of_its_whole_span(): void
    {
        $componente = $this->pagina($this->pack);

        $this->assertSame(
            [['10:00:00', 30, true]],
            $this->horas($componente),
            'CONTROL: sin cesta, la fiesta ve el cupo entero de la zona (30 invitados).',
        );

        $componente->set('cart', [$this->linea($this->pack, '10:00:00', 12)]);

        $this->assertSame(
            [['10:00:00', 18, true]],
            $this->horas($componente),
            "El cupo de fiestas ha dejado de descontar la cesta.\n".
            'Con 12 invitados retenidos quedan 18 de 30.',
        );
    }

    /**
     * ⚠️⚠️ **La línea EN CURSO no se cuenta a sí misma**, y es lo que hace que el número sea usable:
     * lo que el operador está configurando todavía no está en `$this->cart` —entra al pulsar «Añadir
     * al carrito»—, así que pedir 15 no puede hacer que la franja ofrezca 5 antes de añadirla.
     */
    public function test_the_line_being_configured_does_not_count_itself(): void
    {
        $componente = $this->pagina($this->entrada)
            ->set('data.sel_qty', 15)
            ->set('data.sel_time', '10:00:00');

        $this->assertSame(
            [['10:00:00', 20, true], ['11:00:00', 20, true]],
            $this->horas($componente),
            'La selección en curso se está contando como si ya estuviera en el carrito.',
        );
    }

    /**
     * Una línea de OTRO día no retiene nada aquí, **ni en las plazas ni en el cupo de fiestas**.
     * Parece obvio y es justo lo que un filtro mal escrito rompe sin que falle nada: descontaría
     * plazas de un día en el que nadie ha reservado.
     */
    public function test_a_cart_line_of_another_day_discounts_nothing(): void
    {
        $otroDia = '2026-09-17';

        $this->assertSame(
            [['10:00:00', 20, true], ['11:00:00', 20, true]],
            $this->horas($this->pagina($this->entrada)->set('cart', [
                $this->linea($this->entrada, '10:00:00', 15, $otroDia),
            ])),
            'Una línea de otro día está descontando PLAZAS del día que se está ofreciendo.',
        );

        $this->assertSame(
            [['10:00:00', 30, true]],
            $this->horas($this->pagina($this->pack)->set('cart', [
                $this->linea($this->pack, '10:00:00', 12, $otroDia),
            ])),
            'Una fiesta de otro día está descontando CUPO del día que se está ofreciendo.',
        );
    }

    /**
     * ⚠️⚠️ **El cupo de fiestas solo lo consumen las FIESTAS, y la paridad con lo ALMACENADO es la
     * razón**: la ocupación guardada (`PackAvailability::occupancyMaps`) filtra por
     * `ticket_types.type = pack`, así que una cuenta provisional que metiera ahí una entrada
     * ofrecería MENOS invitados de los que el checkout va a admitir — y nadie lo vería fallar, solo
     * se vendería de menos.
     *
     * ▶ No es un caso de laboratorio: en el catálogo real conviven entradas y packs en la misma
     * zona («JUMP · Cumpleaños E2E extras» es un pack de la zona de entradas).
     */
    public function test_an_entry_line_does_not_eat_the_guest_quota_of_a_pack(): void
    {
        $entradaEnZonaDePacks = TicketType::create([
            'name' => ['es' => 'Entrada de la sala'], 'zone_id' => $this->zonaPacks->id,
            'duration_min' => 60, 'is_sellable' => true, 'is_active' => true,
            'seats_per_unit' => 1, 'position' => 3,
        ]);

        $componente = $this->pagina($this->pack)
            ->set('cart', [$this->linea($entradaEnZonaDePacks, '10:00:00', 12)]);

        $this->assertSame(
            [['10:00:00', 30, true]],
            $this->horas($componente),
            'Una ENTRADA está consumiendo cupo de invitados, que solo consumen las fiestas.',
        );
    }

    /**
     * ⚠️⚠️ **El memo tiene que ver entrar la cesta.** `timeMap()` se memoiza por petición y su clave
     * es lo único que decide si el número se recalcula: sin la huella del carrito, añadir una línea
     * dentro de la misma petición seguiría enseñando las plazas de antes — **el defecto de `#329`
     * (el interruptor del mínimo escondido en una caché) por la otra puerta**, y esta vez el número
     * de antes son plazas que ya no están libres.
     */
    public function test_the_memo_does_not_serve_the_seats_of_before_when_the_cart_changes(): void
    {
        $componente = $this->pagina($this->entrada);
        $pagina = $componente->instance();

        $this->assertSame([['10:00:00', 20, true], ['11:00:00', 20, true]], $this->chips($pagina));

        // La misma instancia: si la clave del memo no mirara la cesta, esto devolvería 20.
        $pagina->cart = [$this->linea($this->entrada, '10:00:00', 15)];

        $this->assertSame(
            [['10:00:00', 5, true], ['11:00:00', 20, true]],
            $this->chips($pagina),
            'El memo de las horas está sirviendo las plazas de antes de tocar el carrito.',
        );
    }

    // ─── Andamio ──────────────────────────────────────────────────────────────────────────────

    /** La página con un operador dentro, en el paso de la fecha y con producto y día elegidos. */
    private function pagina(TicketType $producto): Testable
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->roles()->sync([Role::where('name', 'admin')->value('id')]);

        return Livewire::actingAs($admin)
            ->test(CreateManualOrderPage::class)
            ->set('step', CreateManualOrderPage::STEP_PRODUCT)
            ->call('pickProduct', $producto->id)
            ->set('data.sel_date', $this->dia);
    }

    /**
     * Una línea de carrito con la forma que escribe `addLineToCart()`.
     *
     * ⚠️ Lleva también las claves de PRESENTACIÓN (`when`, `addon_display`…) aunque la oferta no las
     * mire: el carrito se pinta en el mismo render, y una línea a medias haría fallar la vista en vez
     * de la aserción — un rojo que no habla del sujeto.
     */
    private function linea(TicketType $producto, string $hora, int $cantidad, ?string $dia = null): array
    {
        $dia ??= $this->dia;

        return [
            'ticket_type_id' => $producto->id,
            'date' => $dia,
            'time' => $hora,
            'qty' => $cantidad,
            'event_data' => [],
            'addons' => [],
            'addon_display' => [],
            'label' => (string) $producto->tr('name'),
            'when' => Carbon::parse($dia)->format('d/m/Y').' '.substr($hora, 0, 5),
            'line_total_cents' => 0,
            'deposit_cents' => null,
            'dependent_ids' => [],
            'dependent_display' => [],
            'below_minimum' => false,
            'guardian_authorization' => false,
        ];
    }

    /** @return list<array{0:string,1:int,2:bool}> hora → plazas → vendible */
    private function horas(Testable $componente): array
    {
        return $this->chips($componente->instance());
    }

    /** @return list<array{0:string,1:int,2:bool}> */
    private function chips(CreateManualOrderPage $pagina): array
    {
        return array_map(
            static fn (array $chip): array => [$chip['time'], $chip['seats'], $chip['sellable']],
            $pagina->timeChips(),
        );
    }
}
