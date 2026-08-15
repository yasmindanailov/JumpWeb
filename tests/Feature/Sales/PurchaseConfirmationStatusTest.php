<?php

namespace Tests\Feature\Sales;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\User;
use App\Livewire\Tickets\Purchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pantalla final de la compra (paso 6, #224): el mensaje de pago refleja el ESTADO REAL del
 * pedido. Antes se mostraba «Tu reserva está pendiente de pago» SIEMPRE, también cuando el pago
 * ya estaba confirmado (vuelta OK de Redsys / notificación) → incoherente. Ahora: pagado → «Pago
 * confirmado»; pendiente (flujo de verificar email) → «pendiente de pago».
 *
 * ⚠️ **Este fichero ENTERO muere con el componente** (medido el 2026-08-15, `DECISIONES #88`). Sus
 * tres casos prueban lo que PINTA el paso 6 del motor Livewire: su sujeto es la superficie, no la
 * regla, así que no hay nada a lo que re-apuntarlos. ·2b·3 lo borra entero, que es lo preferible.
 *
 * **Su cobertura equivalente está localizada y medida.** Clavando `buildConfirmation()` a `'pending'`
 * —o sea, que el resumen deje de distinguir pagado de pendiente— caen dos casos y **los dos
 * sobreviven**: `SidebarDomContractTest::test_the_confirmed_step_emits_the_same_tree_in_both_engines`
 * (que a partir de ·2b·3 afirma el manifiesto congelado) y
 * `SidebarOutcomeParityTest::test_an_expired_hold_is_reported_as_expired_by_the_api`. Este fichero
 * **no cae** con esa mutación: es la prueba de que ejerce otro camino, el del Blade.
 */
class PurchaseConfirmationStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Tarifa base que el `RateResolver` exige (render del sidebar la consulta).
        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
    }

    private function orderFor(User $user, string $status): Order
    {
        $zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'Jump'], 'is_active' => true, 'position' => 1]);
        $type = TicketType::create([
            'name' => ['es' => 'Entrada'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $zone->id,
            'duration_min' => 60, 'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
        $type->prices()->create(['rate_type_id' => RateType::where('key', RateType::KEY_NORMAL)->value('id'), 'amount_cents' => 1000]);
        $slot = Slot::create([
            'zone_id' => $zone->id, 'date' => now()->addDays(3)->format('Y-m-d'),
            'start_time' => '10:00:00', 'end_time' => '11:00:00', 'capacity' => 50, 'online_capacity' => 50,
        ]);
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'JJ-'.Str::upper(Str::random(6)),
            'status' => $status, 'total' => 1000,
            'paid_at' => $status === Order::STATUS_PAID ? now() : null,
        ]);
        $order->items()->create([
            'ticket_type_id' => $type->id, 'slot_id' => $slot->id, 'parent_item_id' => null,
            'quantity' => 1, 'seats' => 1, 'unit_price' => 1000,
        ]);

        return $order;
    }

    public function test_paid_order_shows_payment_confirmed_not_pending(): void
    {
        $user = User::factory()->create();
        $order = $this->orderFor($user, Order::STATUS_PAID);

        $this->actingAs($user);

        Livewire::test(Purchase::class)
            ->set('orderCode', $order->code)
            ->set('step', 6)
            ->assertSee(__('tickets.payment_confirmed_note'))
            ->assertDontSee(__('tickets.pending_payment'));
    }

    public function test_pending_order_shows_pending_payment_not_confirmed(): void
    {
        $user = User::factory()->create();
        $order = $this->orderFor($user, Order::STATUS_PENDING);

        $this->actingAs($user);

        Livewire::test(Purchase::class)
            ->set('orderCode', $order->code)
            ->set('step', 6)
            ->assertSee(__('tickets.pending_payment'))
            ->assertDontSee(__('tickets.payment_confirmed_note'));
    }

    public function test_step_6_is_simplified_single_primary_action_no_see_my_orders(): void
    {
        // #225 F3: el paso 6 se simplifica — «Ver mis reservas» se retira (ya está en el sidebar
        // #221) y queda «Hacer otra reserva» como ÚNICA acción primaria; se quita el párrafo de
        // agradecimiento (menos texto). El split y los avisos (solo si aplican) se mantienen.
        $user = User::factory()->create();
        $order = $this->orderFor($user, Order::STATUS_PAID);

        $this->actingAs($user);

        Livewire::test(Purchase::class)
            ->set('orderCode', $order->code)
            ->set('step', 6)
            ->assertSee(__('tickets.new_purchase'))            // «Hacer otra reserva» (única acción)
            ->assertDontSee(__('tickets.see_my_orders'))       // «Ver mis reservas» retirado
            ->assertDontSee(__('tickets.reservation_thanks')); // párrafo de agradecimiento retirado
    }
}
