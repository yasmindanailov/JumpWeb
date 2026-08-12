<?php

namespace Tests\Feature\Support;

use App\Domain\Identity\Models\User;
use App\Models\Order;
use App\Models\OrderAdjustment;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\TicketType;
use App\Models\Zone;
use App\Support\LegacyAddonAdjustmentRepair;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #193 — Reparación de datos: re-atribuir al child los ajustes `extra_due` de complementos
 * que el código previo ataba al PRINCIPAL (caso real JJ-KDKD1W). Reproduce el estado legacy
 * y verifica que tras el repair la cuenta queda coherente (net-cero al cancelar el complemento).
 */
class LegacyAddonAdjustmentRepairTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    private function entry(string $name, int $priceCents): TicketType
    {
        return TicketType::create([
            'name' => ['es' => $name], 'type' => TicketType::TYPE_ENTRY,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => ++$this->counter,
        ]);
    }

    private function addon(string $name): TicketType
    {
        return TicketType::create([
            'name' => ['es' => $name], 'type' => TicketType::TYPE_ADDON,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => ++$this->counter,
        ]);
    }

    /**
     * Construye el estado LEGACY: pedido pagado (solo el principal), un complemento de pago
     * AÑADIDO después (child cancelado) y su `extra_due` atado al PRINCIPAL (código pre-#193).
     *
     * @return array{0:Order,1:OrderItem,2:OrderItem,3:OrderAdjustment}
     */
    private function legacyOrder(): array
    {
        Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);
        $principalType = $this->entry('Cumpleaños', 1200);
        $taquillaType = $this->addon('Taquilla');

        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-LEG'.str_pad((string) ++$this->counter, 3, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PAID, 'subtotal' => 1200, 'total' => 1200, 'currency' => 'EUR', 'paid_at' => now(),
        ]);
        Payment::create([
            'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id,
            'amount' => 1200, 'currency' => 'EUR', 'provider' => 'redsys',
            'status' => Payment::STATUS_PAID, 'paid_at' => now(),
            'gateway_order' => str_pad((string) (300000 + $this->counter), 10, '0', STR_PAD_LEFT),
        ]);

        $principal = OrderItem::create([
            'order_id' => $order->id, 'ticket_type_id' => $principalType->id, 'slot_id' => null,
            'quantity' => 1, 'seats' => 1, 'unit_price' => 1200,
        ]);
        // Taquilla añadida luego (8 × 2,00) y CANCELADA (cambio de menú la sustituyó).
        $taquilla = OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => $principal->id, 'ticket_type_id' => $taquillaType->id,
            'slot_id' => null, 'quantity' => 8, 'seats' => 0, 'unit_price' => 200, 'free_quantity' => 0,
            'cancelled_at' => now(), 'cancelled_by' => null,
        ]);
        // Ajuste LEGACY: atado al PRINCIPAL (no al child Taquilla).
        $adj = OrderAdjustment::create([
            'order_id' => $order->id, 'order_item_id' => $principal->id, 'type' => OrderAdjustment::TYPE_EXTRA_DUE,
            'amount_cents' => 1600, 'currency' => 'EUR', 'reason' => 'addon_edit',
            'applied_by' => $order->user_id,
            'context' => ['addon_change' => ['added' => [['name' => 'Taquilla', 'qty' => 8]], 'removed' => [], 'updated' => []]],
        ]);

        return [$order->fresh(['items.children.ticketType', 'adjustments.orderItem', 'payments.refunds']), $principal, $taquilla, $adj];
    }

    public function test_legacy_state_is_incoherent_before_repair(): void
    {
        [$order, , $taquilla] = $this->legacyOrder();

        // El cargo (atado al principal activo) cuenta como pendiente; y el child cancelado
        // se ve como "cobrado" (no hay ajuste atado a él) → pendiente de reembolso. Las dos
        // caras de la incoherencia reportada.
        $this->assertSame(1600, $order->financialSummary()->pendingAtGate());
        $this->assertSame(1600, $order->itemCollectedCents($taquilla)); // → pendiente reembolso 16
    }

    public function test_repair_repoints_to_child_and_nets_to_zero(): void
    {
        [$order, , $taquilla, $adj] = $this->legacyOrder();

        $result = LegacyAddonAdjustmentRepair::run();
        $this->assertSame(1, $result['repointed']);
        $this->assertSame(0, $result['skipped']);

        // El ajuste ahora apunta al child Taquilla.
        $this->assertSame($taquilla->id, (int) $adj->fresh()->order_item_id);

        $fresh = $order->fresh(['items.children.ticketType', 'adjustments.orderItem', 'payments.refunds']);
        $s = $fresh->financialSummary();
        $this->assertSame(0, $s->extraDue);                 // anulado (child cancelado)
        $this->assertSame(0, $s->pendingAtGate());          // nada "a cobrar"
        $this->assertSame(1200, $s->totalWithChanges());    // vuelve al total pagado
        $this->assertSame(1200, $fresh->totalWithChangesCents());
        $this->assertSame(0, $fresh->itemCollectedCents($taquilla)); // nada que reembolsar
        $this->assertTrue($fresh->isVoidedLeftoverItem($taquilla));  // se ocultará del desglose
    }

    public function test_repair_is_idempotent_and_leaves_child_tied_adjustments(): void
    {
        [, , $taquilla, $adj] = $this->legacyOrder();
        // Simula ya-reparado: atado al child.
        $adj->update(['order_item_id' => $taquilla->id]);

        $result = LegacyAddonAdjustmentRepair::run();
        $this->assertSame(0, $result['repointed']);
        $this->assertSame($taquilla->id, (int) $adj->fresh()->order_item_id);
    }

    public function test_repair_skips_aggregate_adjustments_with_multiple_addons(): void
    {
        [, $principal, , $adj] = $this->legacyOrder();
        // Ajuste agregado pre-#193 (dos complementos en uno) → no se puede partir: se deja.
        $adj->update(['context' => ['addon_change' => [
            'added' => [['name' => 'Taquilla', 'qty' => 8], ['name' => 'Bebida', 'qty' => 1]], 'removed' => [], 'updated' => [],
        ]]]);

        $result = LegacyAddonAdjustmentRepair::run();
        $this->assertSame(0, $result['repointed']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame($principal->id, (int) $adj->fresh()->order_item_id); // intacto
    }
}
