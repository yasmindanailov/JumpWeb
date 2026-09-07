<?php

namespace Tests\Feature\Reservation;

use App\Domain\Booking\Contracts\GuestCountChange;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\GuestCountAdjuster;
use App\Domain\Booking\Services\GuestCountPolicy;
use App\Domain\Booking\Services\OrderBook;
use App\Domain\Booking\Services\OrderCreator;
use App\Domain\Booking\Services\PostFormAddons;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\DisplayTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * **EL CLIENTE CAMBIA SUS INVITADOS DESDE EL POST-FORMULARIO**
 * (`specs/invitados-en-post-form.md`, `DECISIONES #444`).
 *
 * ❗❗❗ **Es la primera puerta por la que el cliente mueve AFORO**, y por eso cada caso de dinero
 * lleva su control de aforo al lado: la trampa de esta feature es exactamente que las dos cosas se
 * muevan cuando solo una debe hacerlo.
 *
 * ▶ El hueco que cierra está REPRODUCIDO (§1.1): un envío de 12 fichas sobre una línea de 8 guardaba
 * 8 y **descartaba 4 en silencio**, porque el saneo recorta a `quantity` y la vista pinta exactamente
 * `quantity` fichas sin botón de añadir.
 */
class GuestCountTest extends TestCase
{
    use RefreshDatabase;

    private const UNIT_CENTS = 1495;   // 14,95 €/invitado, el del catálogo real

    private OrderCreator $creator;

    private Zone $zone;

    private TicketType $pack;

    private string $date;

    private User $holder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creator = app(OrderCreator::class);
        $this->holder = User::factory()->create();
        $this->date = Carbon::today()->addDays(10)->toDateString();

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0, 'is_active' => true]);

        $this->zone = Zone::create([
            'slug' => 'cumpleanos', 'name' => ['es' => 'Cumpleaños'], 'is_active' => true, 'show_in_landing' => false,
            'max_per_slot' => 2, 'max_guests_per_slot' => 30, 'prep_blocks_cupo' => false,
        ]);

        foreach (range(15, 20) as $hour) {
            Slot::create([
                'zone_id' => $this->zone->id, 'date' => $this->date,
                'start_time' => sprintf('%02d:00:00', $hour),
                'end_time' => sprintf('%02d:00:00', $hour + 1),
                'capacity' => 200, 'online_capacity' => 200,
            ]);
        }

        $this->pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños'], 'zone_id' => $this->zone->id, 'type' => TicketType::TYPE_PACK,
            'duration_min' => 120, 'prep_before_min' => 0, 'prep_after_min' => 0,
            'min_qty' => 8, 'max_qty' => 20, 'seats_per_unit' => 1,
            'guest_fields' => TicketType::DEFAULT_GUEST_FIELDS,
            'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
        $this->pack->prices()->create([
            'rate_type_id' => RateType::where('key', 'normal')->value('id'), 'amount_cents' => self::UNIT_CENTS,
        ]);
    }

    // ─── Lo que el cliente puede hacer ───────────────────────────────────────────────

    public function test_the_customer_can_add_guests_and_the_money_lands_in_the_book(): void
    {
        $item = $this->reservation(10);

        $change = $this->adjust($item, 13);

        $this->assertTrue($change->applied);
        $this->assertSame(10, $change->from);
        $this->assertSame(13, $change->to);
        $this->assertSame(3 * self::UNIT_CENTS, $change->deltaCents);

        $fresh = $item->fresh();
        $this->assertSame(13, (int) $fresh->quantity);
        $this->assertSame(13, (int) $fresh->seats, 'las plazas siguen a los invitados');
        $this->assertSame(self::UNIT_CENTS, (int) $fresh->unit_price, 'el precio es el HISTÓRICO (PAY-19)');

        // El dinero aparece donde aparece todo lo demás: una línea del libro con su fecha.
        $this->assertDatabaseHas('order_adjustments', [
            'order_item_id' => $item->id,
            'amount_cents' => 3 * self::UNIT_CENTS,
            'reason' => 'guest_count_increase',
        ]);
    }

    public function test_the_customer_can_remove_guests_and_the_delta_is_negative(): void
    {
        // `[DECIDIDO owner, 2026-09-07]`: se puede bajar. «No hay arbitraje, no es un negocio de este
        // estilo… aparte lo ve el operador y hará algo al respecto.»
        $item = $this->reservation(15);

        $change = $this->adjust($item, 10);

        $this->assertTrue($change->applied);
        $this->assertSame(-5 * self::UNIT_CENTS, $change->deltaCents);
        $this->assertSame(10, (int) $item->fresh()->quantity);
        $this->assertDatabaseHas('order_adjustments', [
            'order_item_id' => $item->id,
            'amount_cents' => -5 * self::UNIT_CENTS,
            'reason' => 'guest_count_decrease',
        ]);
    }

    public function test_the_book_still_closes_after_going_up_and_back_down(): void
    {
        // ⚠️ El criterio 1 de §2 exige que el libro cierre **después**, no solo antes: las cuatro
        // identidades viven de que cada gestión deje su hecho, y una subida seguida de una bajada es
        // donde una cascada mal escrita se nota.
        $item = $this->reservation(10);

        $this->adjust($item, 14);
        $this->adjust($item->fresh(), 9);

        $order = $item->order->fresh();
        $book = OrderBook::forOrder($order);

        $this->assertTrue($book->isConsistent, 'el libro tiene que seguir cuadrando tras subir y bajar');
        $this->assertSame(9 * self::UNIT_CENTS, (int) $item->fresh()->chargedSubtotalCents());
    }

    // ─── El TECHO y los DOS suelos ───────────────────────────────────────────────────

    public function test_it_refuses_to_go_over_the_product_maximum(): void
    {
        $item = $this->reservation(18);

        $change = $this->adjust($item, 21);

        $this->assertFalse($change->applied);
        $this->assertSame(GuestCountChange::REASON_ABOVE_MAX, $change->reason);
        $this->assertSame(18, (int) $item->fresh()->quantity);
    }

    public function test_it_refuses_to_go_under_the_contractable_minimum(): void
    {
        // Bajar del mínimo existe, pero es una excepción del OPERADOR con permiso propio y rastro
        // (D7): al cliente no se le da.
        $item = $this->reservation(10);

        $change = $this->adjust($item, 7);

        $this->assertFalse($change->applied);
        $this->assertSame(GuestCountChange::REASON_BELOW_MIN, $change->reason);
    }

    public function test_it_refuses_to_go_under_what_already_has_an_owner_and_says_so_differently(): void
    {
        // ❗❗ **Los dos suelos son motivos DISTINTOS y no se funden**: el remedio no es el mismo. Y
        // este cierra por su lado una ficha viva — sin él, bajar deja la hoja de sala imprimiendo
        // plazas NEGATIVAS (reproducido: cantidad 1 con 3 menores asignados → −2).
        $item = $this->reservation(14);
        $this->giveOwnersTo($item, 12);

        $change = $this->adjust($item, 10);

        $this->assertFalse($change->applied);
        $this->assertSame(GuestCountChange::REASON_BELOW_ASSIGNED, $change->reason);
        $this->assertNotSame(GuestCountChange::REASON_BELOW_MIN, $change->reason);

        // CONTROL: hasta lo asignado sí se puede bajar, o el suelo sería un cerrojo.
        $this->assertTrue($this->adjust($item->fresh(), 12)->applied);
    }

    // ─── El AFORO, que es lo que hace peligrosa esta puerta ──────────────────────────

    public function test_it_refuses_to_grow_past_what_the_room_admits(): void
    {
        $item = $this->reservation(10);
        // Otra fiesta llena el cupo de invitados de la franja: 30 − 10 − 18 = 2 libres.
        $this->reservation(18, user: User::factory()->create());

        $change = $this->adjust($item, 15);

        $this->assertFalse($change->applied);
        $this->assertSame(GuestCountChange::REASON_SOLD_OUT, $change->reason);
    }

    public function test_control_the_same_growth_passes_when_the_room_is_free(): void
    {
        // CONTROL del anterior: sin la fiesta que estorba, la MISMA subida pasa. Sin este caso, el
        // de arriba saldría verde con la revalidación rota del todo.
        $item = $this->reservation(10);

        $this->assertTrue($this->adjust($item, 15)->applied);
    }

    public function test_growing_counts_its_own_footprint_out(): void
    {
        // ⚠️ Sin excluir la huella propia, crecer en su propia franja se contaría a sí mismo: la
        // fiesta se bloquearía a sí misma.
        //
        // ⚠️⚠️ **La fiesta compañera no es decoración: sin ella el caso no distinguía nada** y su
        // mutación no mordía. Con la sala vacía, 10 (propia) + 20 (pedida) = 30 **cabe igual** en un
        // cupo de 30, así que excluir o no la huella daba el mismo veredicto. Con otras 8 ocupadas,
        // la cuenta sin exclusión es 18 + 20 = 38 > 30 y la correcta 8 + 20 = 28 ≤ 30. *Un caso que
        // mide donde el defecto no puede aparecer no mide nada.*
        // ⚠️ Y son 8 y no 6 porque el `min_qty` del pack es 8: una fiesta de 6 ni siquiera se vende.
        $item = $this->reservation(10);
        $this->reservation(8, user: User::factory()->create());

        $this->assertTrue($this->adjust($item, 20)->applied, 'la fiesta compite consigo misma si no se excluye su huella');
    }

    public function test_a_party_with_an_extra_hour_is_revalidated_with_its_longe_r_window(): void
    {
        // ⚠️⚠️ **Los `extra_minutes` no son decoración en la revalidación**: una fiesta con hora extra
        // ocupa la sala más rato, y comprobar su crecimiento con la ventana CORTA la deja crecer
        // encima de otra fiesta que empieza después — es la lección de `#425` aplicada a esta puerta.
        //
        // Geometría: la fiesta A (10) va de 15:00 a 17:00 y su hora extra la lleva hasta las 18:00;
        // la fiesta B (18) ocupa las 17:00. Subir A a 20 tiene que RECHAZARSE, porque a las 17:00
        // habría 20 + 18 = 38 en un cupo de 30. Sin los minutos extra, A ni siquiera miraría esa
        // franja y pasaría.
        $item = $this->reservation(10);
        $item->forceFill(['extra_minutes' => 60])->save();
        $this->reservation(18, user: User::factory()->create(), time: '17:00:00');

        $change = $this->adjust($item->fresh(), 20);

        $this->assertFalse($change->applied);
        $this->assertSame(GuestCountChange::REASON_SOLD_OUT, $change->reason);
    }

    // ─── El PLAZO ────────────────────────────────────────────────────────────────────

    public function test_it_refuses_outside_the_cutoff_window(): void
    {
        $item = $this->reservation(10);

        // La fiesta empieza a las 15:00 del día D; con 24 h de corte, a las 16:00 del día D−1 ya venció.
        // ⚠️ Se viaja en hora del PARQUE, no en UTC: la franja guarda hora de pared y el plazo se mide
        // con `DisplayTime::now()`. Con el mismo número en UTC, en verano el viaje cae DOS horas más
        // tarde de lo que dice — y el caso mediría otra cosa.
        $this->travelTo(Carbon::parse($this->date.' 16:00', DisplayTime::timezone())->subDay());

        $change = $this->adjust($item, 12);

        $this->assertFalse($change->applied);
        $this->assertSame(GuestCountChange::REASON_CUTOFF, $change->reason);
    }

    public function test_control_it_still_works_just_inside_the_window(): void
    {
        // CONTROL del anterior, y el que impide que el plazo se vuelva un cerrojo: una hora ANTES del
        // corte sí se puede.
        $item = $this->reservation(10);
        $this->travelTo(Carbon::parse($this->date.' 13:00', DisplayTime::timezone())->subDay());

        $this->assertTrue($this->adjust($item, 12)->applied);
    }

    public function test_the_cutoff_is_configurable_by_the_park(): void
    {
        Setting::query()->updateOrCreate(
            ['key' => GuestCountPolicy::SETTING_CUTOFF_HOURS],
            ['value' => '72', 'group' => 'packs'],
        );
        Setting::flushMemo();

        $item = $this->reservation(10);
        $this->travelTo(Carbon::parse($this->date.' 12:00', DisplayTime::timezone())->subDays(2));

        $this->assertSame(72, app(GuestCountPolicy::class)->cutoffHours());
        $this->assertSame(GuestCountChange::REASON_CUTOFF, $this->adjust($item, 12)->reason);
    }

    // ─── Concurrencia con el operador ────────────────────────────────────────────────

    public function test_a_stale_form_does_not_overwrite_the_operator(): void
    {
        $item = $this->reservation(10);
        $seen = PostFormAddons::versionOf($item);

        // El operador toca la reserva entre que el cliente carga y guarda.
        $this->travel(1)->seconds();
        $item->fresh()->touch();

        $change = app(GuestCountAdjuster::class)->adjust($item->fresh(), 12, 'signed_link', $seen);

        $this->assertFalse($change->applied);
        $this->assertSame(GuestCountChange::REASON_STALE, $change->reason);
        $this->assertSame(10, (int) $item->fresh()->quantity);
    }

    // ─── Lo que se pierde al bajar ───────────────────────────────────────────────────

    public function test_it_reports_how_many_fille_d_forms_a_reduction_discards(): void
    {
        // ⚠️ Cuenta las RELLENAS y no las filas: decirle «se perderán 5» de cinco fichas vacías es
        // ruido, y el cliente no reconocería de qué le hablan.
        $item = $this->reservation(12);
        $rows = array_fill(0, 12, []);
        $rows[9] = ['name' => 'Ana'];
        $rows[10] = ['name' => 'Leo'];
        $item->forceFill(['guest_data' => $rows])->save();

        $change = $this->adjust($item->fresh(), 9);

        $this->assertTrue($change->applied);
        $this->assertSame(2, $change->discardedForms, 'la 12ª estaba vacía y no cuenta');
    }

    // ─── Lo que NO se toca ───────────────────────────────────────────────────────────

    public function test_changing_the_count_does_not_re_seal_the_conditions(): void
    {
        // `PAY-19`: solo re-sella un cambio de PRODUCTO o de DÍA. Un invitado añadido entra con las
        // condiciones que se le comunicaron al comprar.
        $item = $this->reservation(10);
        $sealBefore = $item->age_family_seal;

        $this->adjust($item, 14);

        $this->assertEquals($sealBefore, $item->fresh()->age_family_seal);
    }

    public function test_a_finished_or_cancelled_reservation_is_closed(): void
    {
        $item = $this->reservation(10);
        $item->forceFill(['cancelled_at' => now()])->save();

        $this->assertSame(GuestCountChange::REASON_CLOSED, $this->adjust($item->fresh(), 12)->reason);
    }

    public function test_asking_for_the_same_count_is_a_noop(): void
    {
        $item = $this->reservation(10);

        $change = $this->adjust($item, 10);

        $this->assertFalse($change->applied);
        $this->assertSame(GuestCountChange::REASON_NOOP, $change->reason);
        $this->assertDatabaseMissing('order_adjustments', ['order_item_id' => $item->id, 'reason' => 'guest_count_increase']);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────────────

    private function adjust(OrderItem $item, int $desired): GuestCountChange
    {
        return app(GuestCountAdjuster::class)->adjust($item, $desired, 'signed_link');
    }

    /** Una reserva PAGADA de `$guests` invitados, a las 15:00 salvo que se pida otra hora. */
    private function reservation(int $guests, ?User $user = null, string $time = '15:00:00'): OrderItem
    {
        $order = $this->creator->createPendingOrder($user ?? $this->holder, [[
            'ticket_type_id' => $this->pack->id, 'date' => $this->date, 'time' => $time,
            'qty' => $guests, 'event_data' => [], 'addons' => [],
        ]]);
        // ⚠️ `paid_at` NO es decoración: el libro lo lee como «¿se cobró?» y sin él `online_nac`
        // vale 0 en TODAS las líneas («sin cobro no hay cobro»), así que la identidad de CAJA
        // falla y el pedido sale «en revisión» — un caso de dinero sobre ese fixture mediría otra
        // cosa. Se LEGALIZA el fixture, nunca se excepciona la identidad (la regla de `#311`).
        $order->forceFill(['status' => Order::STATUS_PAID, 'expires_at' => null, 'paid_at' => now()])->save();

        // El libro exige un cobro REAL por lo facturado: un pedido «pagado» sin `Payment` responde
        // «en revisión» (la identidad I2), y ahí un caso de dinero mediría otra cosa.
        Payment::create([
            'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id,
            'amount' => (int) $order->total, 'currency' => 'EUR', 'provider' => 'redsys',
            'status' => Payment::STATUS_PAID, 'paid_at' => now(),
            'gateway_order' => str_pad((string) (400000 + $order->id), 10, '0', STR_PAD_LEFT),
        ]);

        return $order->items()->whereNull('parent_item_id')->with(['ticketType', 'slot', 'order'])->firstOrFail();
    }

    /** Deja `$n` plazas de la reserva con dueño, por la vía que las cuenta: justificantes firmados. */
    private function giveOwnersTo(OrderItem $item, int $n): void
    {
        for ($i = 0; $i < $n; $i++) {
            DB::table('guardian_authorizations')->insert([
                'order_item_id' => $item->id,
                'minor_name' => 'Menor '.$i,
                'minor_surname' => 'Probe',
                'minor_key' => 'menor-'.$i,
                'minor_born_on' => now()->subYears(8)->toDateString(),
                'guardian_name' => 'Padre '.$i,
                'guardian_surname' => 'Probe',
                'guardian_relationship' => 'padre',
                'guardian_phone' => '600000000',
                'created_at' => now(),
            ]);
        }
    }
}
