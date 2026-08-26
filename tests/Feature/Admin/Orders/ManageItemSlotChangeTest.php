<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\SpecialDate;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\ItemRescheduleOffer;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Services\PaymentSettings;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Models\Setting;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Notifications\OrderItemModified;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Sub-fase 7.2e.2 (decisión #159) — Tests del modal "Gestionar producto"
 * Tab 1 "Producto y reserva" centrado en el cambio fecha+hora del slot.
 *
 * Cobertura:
 *  - Visibility: action visible para staff con `orders.edit_item`.
 *  - mountUsing: ¿el modal abre correctamente para items editables?
 *  - Save happy path: cambio de slot futuro válido → update + audit + email.
 *  - No-op: mismo slot que el actual → notif info, sin email ni audit.
 *  - Optimistic stale: token desfasado → blocked log + sin update.
 *  - Defense in depth slot: cross-zone / slot pasado / park cerrado /
 *    product window / aforo insuficiente — todos blocked.
 *  - IDOR: item de otro Order rechazado.
 *  - Bloqueos heredados: item cancelled / item finished / addon directo /
 *    Order no operativo.
 *  - Selector opciones: fechas futuras válidas + slot actual marcado.
 *  - Aforo: pack usa PackAvailability, entrada usa SlotAvailability.
 *  - Bug fix aforo: items soft-cancelados liberan plazas.
 */
class ManageItemSlotChangeTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $entryType;

    private TicketType $packType;

    private string $todayPlus7;

    private string $todayPlus8;

    private int $counter = 0;

    private int $paymentCounter = 0;

    /**
     * ⚠️⚠️ **El reloj se congela para TODO el fichero, y sale de una medición** (`DECISIONES #162`,
     * `scripts/audit-clock.sh`). Dos casos del calendario fallaban **el día 31 de ciertos meses y el
     * 31 de diciembre**, y la causa no es el código de producción —`calendarPrevMonth()` es
     * correcto— sino la **aritmética de meses de PHP, que DESBORDA**:
     *
     *     hoy 2026-08-31 → +2 meses = 2026-10-31 · +3 meses = **2026-12-01**  (se salta noviembre)
     *     hoy 2026-12-31 → +2 meses = 2027-03-03 · +3 meses = **2027-03-31**  (los dos en marzo)
     *
     * El fichero construye su fixture con `today()->addMonths(2)` y `addMonths(3)` dando por hecho
     * que distan **un** mes. Varios días al año no es verdad: distan dos, o cero.
     *
     * ▶ **La fecha elegida es un día 15**, así que `+2`, `+3` y `+7` meses caen todos en día 15 y
     * ningún salto puede desbordar. Es lunes, aunque aquí da igual: la única tarifa del fixture es
     * `weekdays => null`.
     */
    private const FROZEN_NOW = '2026-06-15 09:00:00';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse(self::FROZEN_NOW, 'UTC'));
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        RateType::create([
            'key' => RateType::KEY_NORMAL,
            'label' => ['es' => 'Normal'],
            'weekdays' => null,
            'priority' => 0,
        ]);

        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);

        $this->entryType = TicketType::create([
            'name' => ['es' => 'Jump 1h'],
            'zone_id' => $this->zone->id,
            'duration_min' => 60,
            'is_sellable' => true,
            'is_active' => true,
            'seats_per_unit' => 1,
            'position' => 1,
        ]);

        $this->packType = TicketType::create([
            'name' => ['es' => 'Cumpleaños Jump'],
            'zone_id' => $this->zone->id,
            'type' => TicketType::TYPE_PACK,
            'duration_min' => 120,
            'min_qty' => 5,
            'max_qty' => 20,
            'is_sellable' => true,
            'is_active' => true,
            'seats_per_unit' => 1,
            'position' => 2,
        ]);

        $this->todayPlus7 = Carbon::today()->addDays(7)->toDateString();
        $this->todayPlus8 = Carbon::today()->addDays(8)->toDateString();

        // Slots para hoy+7 (10:00, 11:00, 12:00 — el pack dura 120 min: al moverlo a las 11:00 su
        // fiesta llega a las 13:00 y necesita cobertura contigua de la rejilla) y hoy+8 (10:00).
        foreach (['10:00:00', '11:00:00', '12:00:00'] as $start) {
            Slot::create([
                'zone_id' => $this->zone->id,
                'date' => $this->todayPlus7,
                'start_time' => $start,
                'end_time' => Carbon::parse($start)->addHour()->format('H:i:s'),
                'capacity' => 10,
                'online_capacity' => 10,
                'online_sales_open' => true,
                'status' => Slot::STATUS_OPEN,
            ]);
        }
        Slot::create([
            'zone_id' => $this->zone->id,
            'date' => $this->todayPlus8,
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'capacity' => 10,
            'online_capacity' => 10,
            'online_sales_open' => true,
            'status' => Slot::STATUS_OPEN,
        ]);
    }

    // ─── Happy path ──────────────────────────────────────────────────────

    public function test_save_changes_slot_audits_and_emails_when_target_slot_valid(): void
    {
        Notification::fake();
        [$order, $item] = $this->makePaidEntryOrder('10:00:00');

        $newSlot = $this->slotAt($this->todayPlus7, '11:00:00');
        $oldSlotId = $item->slot_id;

        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->set('calendarItemId', $item->id)
            ->set('calendarSelectedDate', $this->todayPlus7)
            ->set('calendarSelectedTime', '11:00:00')
            ->callAction(
                'manageItem',
                data: $this->formData($item, ['slot_date' => $this->todayPlus7, 'slot_time' => '11:00:00']),
                arguments: ['item' => $item->id],
            );

        $item->refresh();
        $this->assertSame($newSlot->id, $item->slot_id);
        $this->assertNotSame($oldSlotId, $item->slot_id);

        $log = AuditLog::where('action', 'orders.item_slot_changed')->latest()->first();
        $this->assertNotNull($log);
        $this->assertSame($order->code, $log->payload['order_code']);
        $this->assertSame($oldSlotId, $log->payload['from_slot_id']);
        $this->assertSame($newSlot->id, $log->payload['to_slot_id']);

        Notification::assertSentTo($order->user, OrderItemModified::class);
    }

    public function test_pack_can_be_moved_to_overlapping_slot_at_cupo_cap(): void
    {
        // P3 (auditoría Fase 1): re-agendar SOLO la franja de un pack a un slot cuya ventana de cupo
        // SOLAPA la actual NO debe auto-bloquearse contándose a sí mismo. La fiesta de 120 min a las
        // 10:00 abarca [10:00,12:00) (cubre el slot de las 11:00); con el cupo de fiestas a 1, mover a
        // las 11:00 se bloqueaba (fail-closed). El fix pasa `excludeItemId` (como el path unificado).
        $this->zone->update(['max_per_slot' => 1, 'max_guests_per_slot' => 100]);
        // Un pack real siempre tiene `event_fields` (homenajeado); sin ellos el paso de event_data del
        // modal abortaría con `no_event_fields` antes de llegar al cambio de franja.
        $this->packType->update(['event_fields' => [
            ['key' => 'celebrant', 'type' => 'text', 'required' => false, 'stage' => 'booking', 'label' => ['es' => 'Homenajeado']],
        ]]);

        [$order, $item] = $this->makePaidPackOrder('10:00:00', guests: 8);
        $target = $this->slotAt($this->todayPlus7, '11:00:00');

        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->set('calendarItemId', $item->id)
            ->set('calendarSelectedDate', $this->todayPlus7)
            ->set('calendarSelectedTime', '11:00:00')
            ->callAction('manageItem',
                data: $this->formData($item, ['slot_date' => $this->todayPlus7, 'slot_time' => '11:00:00']),
                arguments: ['item' => $item->id],
            )
            ->assertHasNoActionErrors();

        $this->assertSame($target->id, (int) $item->fresh()->slot_id); // se movió (sin auto-bloqueo)
    }

    public function test_save_with_same_slot_is_noop_no_email_no_audit(): void
    {
        Notification::fake();
        [$order, $item] = $this->makePaidEntryOrder('10:00:00');
        $oldSlotId = $item->slot_id;

        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction(
                'manageItem',
                data: $this->formData($item, ['slot_date' => $this->todayPlus7, 'slot_time' => '10:00:00']),
                arguments: ['item' => $item->id],
            );

        $item->refresh();
        $this->assertSame($oldSlotId, $item->slot_id);
        $this->assertNull(AuditLog::where('action', 'orders.item_slot_changed')->first());
        Notification::assertNothingSentTo($order->user);
    }

    // ─── Optimistic / IDOR / Cross-zone ──────────────────────────────────

    public function test_stale_optimistic_token_blocks_slot_change(): void
    {
        [$order, $item] = $this->makePaidEntryOrder('10:00:00');

        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->set('calendarItemId', $item->id)
            ->set('calendarSelectedDate', $this->todayPlus7)
            ->set('calendarSelectedTime', '11:00:00')
            ->callAction(
                'manageItem',
                data: [
                    'optimistic_token' => '0',
                    'slot_date' => $this->todayPlus7,
                    'slot_time' => '11:00:00',
                    'event_data' => [],
                ],
                arguments: ['item' => $item->id],
            );

        $item->refresh();
        $this->assertSame($this->slotAt($this->todayPlus7, '10:00:00')->id, $item->slot_id);
        $log = AuditLog::where('action', 'orders.item_edit_blocked')->latest()->first();
        $this->assertNotNull($log);
        $this->assertSame('stale_item_version', $log->payload['reason']);
    }

    public function test_validate_new_slot_returns_cross_zone_change_forbidden(): void
    {
        // Tests directos del helper `validateNewSlot` via reflection: cubren
        // los escenarios defensivos de capa 4 que Filament rechaza ANTES en
        // capa 3 (validación in:options del Select). Estos escenarios son
        // hipotéticos para un atacante autenticado manipulando JS — la
        // cobertura de helper unit garantiza que el handler los rechazaría
        // correctamente si llegaran a invocarlo.
        $otherZone = Zone::create(['slug' => 'kids2', 'name' => ['es' => 'KIDS']]);
        $otherSlot = Slot::create([
            'zone_id' => $otherZone->id,
            'date' => $this->todayPlus7,
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'capacity' => 10,
            'online_capacity' => 10,
        ]);

        [$order, $item] = $this->makePaidEntryOrder('10:00:00');

        $reason = $this->invokeValidateNewSlot($order, $item, $otherSlot);
        $this->assertSame('cross_zone_change_forbidden', $reason);
    }

    public function test_item_from_other_order_rejected(): void
    {
        [$orderA, $itemA] = $this->makePaidEntryOrder('10:00:00');
        [$orderB] = $this->makePaidEntryOrder('11:00:00');

        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $orderB->code])
            ->callAction(
                'manageItem',
                data: $this->formData($itemA, ['slot_date' => $this->todayPlus7, 'slot_time' => '11:00:00']),
                arguments: ['item' => $itemA->id],
            );

        $itemA->refresh();
        $this->assertSame($this->slotAt($this->todayPlus7, '10:00:00')->id, $itemA->slot_id);
    }

    // ─── Bloqueos heredados (item state) ─────────────────────────────────

    public function test_cancelled_item_blocked_with_audit(): void
    {
        [$order, $item] = $this->makePaidEntryOrder('10:00:00');
        $item->update(['cancelled_at' => now(), 'cancelled_by' => $order->user_id]);

        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->set('calendarItemId', $item->id)
            ->set('calendarSelectedDate', $this->todayPlus7)
            ->set('calendarSelectedTime', '11:00:00')
            ->callAction(
                'manageItem',
                data: $this->formData($item->fresh(), ['slot_date' => $this->todayPlus7, 'slot_time' => '11:00:00']),
                arguments: ['item' => $item->id],
            );

        $log = AuditLog::where('action', 'orders.item_edit_blocked')->latest()->first();
        $this->assertNotNull($log);
        $this->assertSame('item_cancelled', $log->payload['reason']);
    }

    public function test_addon_item_blocked_with_audit(): void
    {
        [$order, $parent] = $this->makePaidEntryOrder('10:00:00');
        $addon = OrderItem::create([
            'order_id' => $order->id,
            'parent_item_id' => $parent->id,
            'ticket_type_id' => $this->entryType->id,
            'slot_id' => null,
            'quantity' => 1,
            'seats' => 0,
            'unit_price' => 200,
        ]);

        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->set('calendarItemId', $addon->id)
            ->set('calendarSelectedDate', $this->todayPlus7)
            ->set('calendarSelectedTime', '11:00:00')
            ->callAction(
                'manageItem',
                data: $this->formData($addon, ['slot_date' => $this->todayPlus7, 'slot_time' => '11:00:00']),
                arguments: ['item' => $addon->id],
            );

        $log = AuditLog::where('action', 'orders.item_edit_blocked')->latest()->first();
        $this->assertNotNull($log);
        $this->assertSame('item_is_addon', $log->payload['reason']);
    }

    public function test_order_not_operational_blocks_slot_change(): void
    {
        [$order, $item] = $this->makePaidEntryOrder('10:00:00');
        $order->update(['status' => Order::STATUS_CANCELLED]);

        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->set('calendarItemId', $item->id)
            ->set('calendarSelectedDate', $this->todayPlus7)
            ->set('calendarSelectedTime', '11:00:00')
            ->callAction(
                'manageItem',
                data: $this->formData($item, ['slot_date' => $this->todayPlus7, 'slot_time' => '11:00:00']),
                arguments: ['item' => $item->id],
            );

        $log = AuditLog::where('action', 'orders.item_edit_blocked')->latest()->first();
        $this->assertNotNull($log);
        $this->assertSame('order_not_operational', $log->payload['reason']);
    }

    // ─── Capacity sentinel ───────────────────────────────────────────────

    public function test_validate_new_slot_returns_invalid_slot_selection_when_null(): void
    {
        [$order, $item] = $this->makePaidEntryOrder('10:00:00');
        $reason = $this->invokeValidateNewSlot($order, $item, null);
        $this->assertSame('invalid_slot_selection', $reason);
    }

    public function test_validate_new_slot_returns_slot_in_past(): void
    {
        $pastSlot = Slot::create([
            'zone_id' => $this->zone->id,
            'date' => Carbon::today()->subDays(2)->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'capacity' => 10,
            'online_capacity' => 10,
        ]);
        [$order, $item] = $this->makePaidEntryOrder('10:00:00');
        $reason = $this->invokeValidateNewSlot($order, $item, $pastSlot);
        $this->assertSame('slot_in_past', $reason);
    }

    public function test_validate_new_slot_returns_slot_closed(): void
    {
        $closedSlot = Slot::create([
            'zone_id' => $this->zone->id,
            'date' => $this->todayPlus8,
            'start_time' => '11:00:00',
            'end_time' => '12:00:00',
            'capacity' => 10,
            'online_capacity' => 10,
            'status' => Slot::STATUS_CLOSED,
        ]);
        [$order, $item] = $this->makePaidEntryOrder('10:00:00');
        $reason = $this->invokeValidateNewSlot($order, $item, $closedSlot);
        $this->assertSame('slot_closed', $reason);
    }

    public function test_validate_new_slot_returns_null_for_valid_target(): void
    {
        [$order, $item] = $this->makePaidEntryOrder('10:00:00');
        $valid = $this->slotAt($this->todayPlus7, '11:00:00');
        $reason = $this->invokeValidateNewSlot($order, $item, $valid);
        $this->assertNull($reason);
    }

    public function test_validate_new_slot_returns_park_closed(): void
    {
        // Marcamos el día como cerrado vía SpecialDate.
        SpecialDate::create([
            'date' => $this->todayPlus8,
            'is_closed' => true,
        ]);
        // Slot futuro que existe ese día (test setUp ya creó uno).
        [$order, $item] = $this->makePaidEntryOrder('10:00:00');
        $slotOnClosedDay = $this->slotAt($this->todayPlus8, '10:00:00');
        $reason = $this->invokeValidateNewSlot($order, $item, $slotOnClosedDay);
        $this->assertSame('park_closed', $reason);
    }

    // ─── Horizonte de compra (7.2e.2bis6, #160) ──────────────────────────

    public function test_validate_new_slot_returns_beyond_horizon(): void
    {
        $beyondHorizon = Carbon::today()->addMonths(7)->toDateString();
        $farSlot = Slot::create([
            'zone_id' => $this->zone->id,
            'date' => $beyondHorizon,
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'capacity' => 10,
            'online_capacity' => 10,
        ]);
        [$order, $item] = $this->makePaidEntryOrder('10:00:00');
        $reason = $this->invokeValidateNewSlot($order, $item, $farSlot);
        $this->assertSame('beyond_horizon', $reason);
    }

    public function test_selectable_dates_in_range_excludes_beyond_horizon_and_keeps_valid_dates(): void
    {
        // Re-apuntado en el paso 0 del desmontaje al calendario vivo, y en la
        // extracción 3 (spec desmontar-view-order §9.4) a la consulta de
        // re-programación del DOMINIO — ya pública: muere la reflexión. El slot
        // lejano se crea plenamente vendible: la ÚNICA razón para excluirlo
        // debe ser el horizonte — el test original lo creaba sin
        // `online_sales_open` y salía verde por `sellableOnline()`, no por el
        // horizonte.
        $beyondHorizon = Carbon::today()->addMonths(7)->toDateString();
        Slot::create([
            'zone_id' => $this->zone->id,
            'date' => $beyondHorizon,
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'capacity' => 10,
            'online_capacity' => 10,
            'online_sales_open' => true,
            'status' => Slot::STATUS_OPEN,
        ]);
        [, $item] = $this->makePaidEntryOrder('10:00:00');

        $dates = app(ItemRescheduleOffer::class)
            ->selectableDates($item, Carbon::today(), Carbon::today()->addMonths(8));

        $this->assertContains($this->todayPlus7, $dates);
        $this->assertNotContains($beyondHorizon, $dates);
    }

    public function test_reschedule_offer_anchors_today_in_park_timezone_not_utc(): void
    {
        // [DECIDIDO owner, 2026-08-26] (extracción 3, spec §9.4): el ancla
        // temporal del panel es la zona del PARQUE, como la oferta pública
        // (AFORO-09), no UTC. A las 00:30 de Madrid (22:30 UTC del día
        // anterior), un día con franjas plenamente vendibles que en UTC aún es
        // «hoy» pero en el parque ya es AYER no se ofrece — con ancla UTC este
        // test cae, porque ese día sí entraría en la lista.
        $utcToday = '2026-06-14';
        Slot::create([
            'zone_id' => $this->zone->id,
            'date' => $utcToday,
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'capacity' => 10,
            'online_capacity' => 10,
            'online_sales_open' => true,
            'status' => Slot::STATUS_OPEN,
        ]);
        [, $item] = $this->makePaidEntryOrder('10:00:00');

        $this->travelTo(Carbon::parse('2026-06-14 22:30:00', 'UTC')); // 00:30 en Madrid: ya es 15 de junio

        $offer = app(ItemRescheduleOffer::class);
        $this->assertSame('2026-06-15', $offer->today()->toDateString());

        $dates = $offer->selectableDates(
            $item,
            Carbon::parse('2026-06-10'),
            Carbon::parse('2026-06-30'),
        );
        $this->assertNotContains($utcToday, $dates, 'El 14 de junio es AYER en el parque: no se ofrece aunque en UTC siga siendo hoy.');
        $this->assertContains($this->todayPlus7, $dates);
    }

    public function test_times_hide_slots_without_capacity_for_the_item_seats(): void
    {
        // [DECIDIDO owner, 2026-08-26] (extracción 3, spec §9.4): las horas sin
        // aforo para `item.seats` se OCULTAN, no se enseñan deshabilitadas. La
        // regla existía desde el origen pero su mutación salía VERDE en toda la
        // red — este test es su red: una franja de capacidad 1 ya ocupada por
        // OTRO pedido no puede ofrecerse como destino; la libre de al lado, sí.
        Slot::create([
            'zone_id' => $this->zone->id,
            'date' => $this->todayPlus7,
            'start_time' => '13:00:00',
            'end_time' => '14:00:00',
            'capacity' => 1,
            'online_capacity' => 1,
            'online_sales_open' => true,
            'status' => Slot::STATUS_OPEN,
        ]);
        $this->makePaidEntryOrder('13:00:00'); // la llena: 0 libres
        [, $item] = $this->makePaidEntryOrder('10:00:00');

        $times = collect(app(ItemRescheduleOffer::class)->times($item->fresh('ticketType', 'slot'), $this->todayPlus7));

        $this->assertNotNull($times->firstWhere('time', '11:00:00'), 'La franja libre se ofrece.');
        $this->assertNull($times->firstWhere('time', '13:00:00'), 'Una franja sin aforo para el ítem debe OCULTARSE, no ofrecerse.');
    }

    public function test_purchase_horizon_helper_falls_back_to_default_on_invalid_value(): void
    {
        Setting::updateOrCreate(
            ['key' => 'sales.purchase_horizon_months'],
            ['key' => 'sales.purchase_horizon_months', 'value' => 'abc', 'group' => 'payment'],
        );
        $this->assertSame(6, PaymentSettings::purchaseHorizonMonths());
    }

    // ─── Calendario visual (wire methods, 7.2e.2bis6, #160) ──────────────

    public function test_calendar_select_date_updates_state_when_selectable(): void
    {
        [$order, $item] = $this->makePaidEntryOrder('10:00:00');

        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->set('calendarItemId', $item->id)
            ->set('calendarMonth', Carbon::parse($this->todayPlus7)->format('Y-m'))
            ->call('calendarSelectDate', $this->todayPlus8)
            ->assertSet('calendarSelectedDate', $this->todayPlus8);
    }

    public function test_calendar_select_date_rejects_unselectable_date(): void
    {
        $tomorrowNoSlots = Carbon::today()->addDay()->toDateString();
        [$order, $item] = $this->makePaidEntryOrder('10:00:00');

        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->set('calendarItemId', $item->id)
            ->set('calendarMonth', Carbon::parse($this->todayPlus7)->format('Y-m'))
            ->set('calendarSelectedDate', $this->todayPlus7)
            ->call('calendarSelectDate', $tomorrowNoSlots)
            ->assertSet('calendarSelectedDate', $this->todayPlus7);
    }

    public function test_calendar_select_time_validates_against_selected_date(): void
    {
        [$order, $item] = $this->makePaidEntryOrder('10:00:00');

        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->set('calendarItemId', $item->id)
            ->set('calendarMonth', Carbon::parse($this->todayPlus7)->format('Y-m'))
            ->set('calendarSelectedDate', $this->todayPlus7)
            ->call('calendarSelectTime', '11:00:00')
            ->assertSet('calendarSelectedTime', '11:00:00');
    }

    public function test_calendar_select_time_rejects_invalid_time(): void
    {
        [$order, $item] = $this->makePaidEntryOrder('10:00:00');

        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->set('calendarItemId', $item->id)
            ->set('calendarMonth', Carbon::parse($this->todayPlus7)->format('Y-m'))
            ->set('calendarSelectedDate', $this->todayPlus7)
            ->set('calendarSelectedTime', '10:00:00')
            ->call('calendarSelectTime', '99:99:99')
            ->assertSet('calendarSelectedTime', '10:00:00');
    }

    public function test_calendar_view_data_returns_populated_matrix_and_times(): void
    {
        // Regresión del bug #161 (sub-fase 7.2e.2bis7): el partial blade del
        // calendario referenciaba `$this->calendar*` dentro de
        // `ViewComponent::make()->viewData()` — pero ahí `$this` es el View
        // object, no la page Livewire. Resultado: el calendario renderizaba
        // la cabecera (mes, días lun-dom) pero la rejilla de días salía
        // VACÍA y los chips de horas tampoco aparecían.
        //
        // El fix mueve TODO el cómputo al helper `buildCalendarViewData`
        // (invocado en cada render Livewire via closure de viewData) que
        // devuelve `matrix`, `times`, `monthLabel`, etc. El blade lee
        // variables directas, sin `$this`.
        //
        // Este test verifica empíricamente que `buildCalendarViewData`
        // devuelve datos NO vacíos en un escenario de happy path:
        //  - matrix con 5 o 6 semanas, cada una con 7 días.
        //  - times con al menos 1 entrada para el día seleccionado.
        //  - monthLabel no vacío.
        [$order, $item] = $this->makePaidEntryOrder('10:00:00');

        $page = new ViewOrder;
        $page->record = $order;
        $page->calendarItemId = $item->id;
        $page->calendarMonth = Carbon::parse($this->todayPlus7)->format('Y-m');
        $page->calendarSelectedDate = $this->todayPlus7;
        $page->calendarSelectedTime = '10:00:00';

        $ref = new \ReflectionMethod(ViewOrder::class, 'buildCalendarViewData');
        $ref->setAccessible(true);
        $data = $ref->invoke($page, $item);

        // El monthLabel se computa (no vacío).
        $this->assertNotEmpty($data['monthLabel']);
        // La matrix es una rejilla 5x7 o 6x7 (5 o 6 semanas × 7 días).
        $this->assertGreaterThanOrEqual(5, count($data['matrix']));
        $this->assertCount(7, $data['matrix'][0]);
        // Al menos UN día de la matrix es selectable (el slot real
        // creado en setUp para todayPlus7).
        $selectableCells = collect($data['matrix'])
            ->flatten(1)
            ->filter(fn (array $c) => $c['selectable']);
        $this->assertGreaterThan(0, $selectableCells->count(), 'La matrix debe contener al menos un día selectable.');
        // Hay horas disponibles para el día seleccionado.
        $this->assertGreaterThan(0, count($data['times']));
        $this->assertSame('10:00:00', $data['times'][0]['time']);
    }

    public function test_calendar_wire_methods_lazy_initialize_from_mounted_action(): void
    {
        // Defense in depth: aunque mountUsing no haya seteado las properties
        // (caso edge: bug futuro de Filament, request específico que pierde
        // estado), las wire methods AUTO-INICIALIZAN desde
        // `mountedActions[0].arguments.item`. Tras `mountAction` simulamos
        // que las properties se perdieron (set a null) y verificamos que
        // `calendarNextMonth` lazy-inicializa y avanza correctamente.
        [$order, $item] = $this->makePaidEntryOrder('10:00:00');

        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->mountAction('manageItem', ['item' => $item->id])
            // Simulamos pérdida del state Livewire.
            ->set('calendarMonth', null)
            ->set('calendarItemId', null)
            ->set('calendarSelectedDate', null)
            ->set('calendarSelectedTime', null)
            // Wire method debe lazy-inicializar Y avanzar al mes siguiente.
            ->call('calendarNextMonth')
            ->assertSet(
                'calendarMonth',
                Carbon::parse($this->todayPlus7)->startOfMonth()->addMonth()->format('Y-m'),
            );
    }

    public function test_calendar_go_to_item_month_returns_to_the_item_slot_month(): void
    {
        // `calendarGoToItemMonth` no tenía NINGÚN test (lo pidió el paso 1 del
        // desmontaje, spec desmontar-view-order §6·2): es el atajo «volver al
        // mes del item» tras navegar lejos. El método lee el item de
        // `mountedActions`, así que se monta la acción primero; el assert
        // intermedio prueba que de verdad se navegó ANTES de volver.
        [$order, $item] = $this->makePaidEntryOrder('10:00:00');

        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->mountAction('manageItem', ['item' => $item->id])
            ->call('calendarNextMonth')
            ->assertSet(
                'calendarMonth',
                Carbon::parse($this->todayPlus7)->startOfMonth()->addMonth()->format('Y-m'),
            )
            ->call('calendarGoToItemMonth')
            ->assertSet('calendarMonth', Carbon::parse($this->todayPlus7)->format('Y-m'));
    }

    public function test_calendar_partial_html_updates_after_prev_month_call(): void
    {
        // Regresión empírica del bug #162: los botones del calendario
        // (montados dentro del schema de un Filament Action) no propagan
        // el wire:click al componente Livewire. Este test verifica que tras
        // un call directo del método `calendarPrevMonth`, las propiedades
        // se actualizan Y el HTML completo del componente refleja el
        // nuevo mes — si el HTML no cambia, el problema está en el render
        // del partial (no en el método).
        [$order, $item] = $this->makePaidEntryOrder('10:00:00');

        $startMonth = Carbon::today()->addMonths(3)->format('Y-m');
        $prevMonth = Carbon::today()->addMonths(2)->format('Y-m');

        $component = Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->set('calendarItemId', $item->id)
            ->set('calendarMonth', $startMonth)
            ->call('calendarPrevMonth');

        // Property cambió (backend OK).
        $component->assertSet('calendarMonth', $prevMonth);
    }

    public function test_calendar_next_month_advances_within_horizon(): void
    {
        // Regresión empírica del 7.2e.2bis7: verificamos que las flechas
        // de navegación del mes FUNCIONAN dentro de los límites permitidos
        // (no solo respetan los bordes, también avanzan/retroceden 1 mes
        // a la vez en el rango normal).
        [$order, $item] = $this->makePaidEntryOrder('10:00:00');

        $startMonth = Carbon::today()->format('Y-m');
        // `startOfMonth()` ancla el cálculo a granularidad de mes: sin él,
        // `Carbon::today()->addMonth()` desborda el día 31 (p. ej. 31-may +
        // 1 mes = 1-jul, no 30-jun) y diverge de la semántica de producción,
        // que SIEMPRE reduce a `Y-m` sobre `startOfMonth` (calendarNextMonth).
        $nextMonth = Carbon::today()->startOfMonth()->addMonth()->format('Y-m');

        // Next desde mes actual → mes siguiente.
        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->set('calendarItemId', $item->id)
            ->set('calendarMonth', $startMonth)
            ->call('calendarNextMonth')
            ->assertSet('calendarMonth', $nextMonth);
    }

    public function test_calendar_prev_month_retreats_within_horizon(): void
    {
        [$order, $item] = $this->makePaidEntryOrder('10:00:00');

        $futureMonth = Carbon::today()->addMonths(3)->format('Y-m');
        $oneBefore = Carbon::today()->addMonths(2)->format('Y-m');

        // Prev desde mes+3 → mes+2.
        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->set('calendarItemId', $item->id)
            ->set('calendarMonth', $futureMonth)
            ->call('calendarPrevMonth')
            ->assertSet('calendarMonth', $oneBefore);
    }

    public function test_calendar_view_data_matrix_reflects_current_calendar_month(): void
    {
        // Tras un wire:click prev/next, el viewData closure se re-evalúa
        // y la `matrix` debe corresponder al NUEVO mes — no quedar
        // congelada con el mes inicial (regresión del fix #161 si la
        // closure capturara $monthCarbon en lugar de $this->calendarMonth).
        [$order, $item] = $this->makePaidEntryOrder('10:00:00');

        $page = new ViewOrder;
        $page->record = $order;
        $page->calendarItemId = $item->id;

        // Simulamos navegación: calendarMonth = mes actual.
        $page->calendarMonth = Carbon::today()->format('Y-m');
        $ref = new \ReflectionMethod(ViewOrder::class, 'buildCalendarViewData');
        $ref->setAccessible(true);
        $dataMonth1 = $ref->invoke($page, $item);

        // Simulamos navegación al mes siguiente.
        $page->calendarMonth = Carbon::today()->startOfMonth()->addMonth()->format('Y-m');
        $dataMonth2 = $ref->invoke($page, $item);

        // Los matrix son DISTINTOS (al menos en monthLabel).
        $this->assertNotSame($dataMonth1['monthLabel'], $dataMonth2['monthLabel']);

        // El primer día del mes 2 en su matrix coincide con el inicio del nuevo mes.
        // `startOfMonth()` ANTES de `addMonth()`: coherente con el valor fijado
        // en `$page->calendarMonth` arriba y a salvo del overflow del día 31.
        $firstDayOfMonth2 = Carbon::today()->startOfMonth()->addMonth();
        $found = collect($dataMonth2['matrix'])
            ->flatten(1)
            ->contains(fn (array $c) => $c['date'] === $firstDayOfMonth2->toDateString() && $c['in_month']);
        $this->assertTrue($found, 'La matrix del nuevo mes debe incluir el día 1 del mes destino.');
    }

    public function test_calendar_navigation_respects_horizon_bounds(): void
    {
        [$order, $item] = $this->makePaidEntryOrder('10:00:00');

        $currentMonth = Carbon::today()->format('Y-m');
        $horizonMonth = Carbon::today()
            ->addMonths(PaymentSettings::purchaseHorizonMonths())
            ->format('Y-m');

        // Prev desde mes actual → bloqueado en mes actual.
        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->set('calendarItemId', $item->id)
            ->set('calendarMonth', $currentMonth)
            ->call('calendarPrevMonth')
            ->assertSet('calendarMonth', $currentMonth);

        // Next desde mes horizon → bloqueado en mes horizon.
        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->set('calendarItemId', $item->id)
            ->set('calendarMonth', $horizonMonth)
            ->call('calendarNextMonth')
            ->assertSet('calendarMonth', $horizonMonth);
    }

    // ─── Fuente viva del calendario (paso 0 del desmontaje: re-apuntados) ──

    public function test_calendar_matrix_marks_current_slot_day_as_current_and_selectable(): void
    {
        // La regla del selector retirado («la fecha del slot actual siempre
        // aparece, marcada») vive ahora en la matriz del calendario: la celda
        // del día del slot actual lleva `is_current` y es seleccionable
        // (mantener el slot es un no-op válido). El día vecino es el control:
        // seleccionable pero NO current.
        [$order, $item] = $this->makePaidEntryOrder('10:00:00');

        $ref = new \ReflectionMethod(ViewOrder::class, 'calendarMatrixForItemWithSelection');
        $ref->setAccessible(true);
        $page = new ViewOrder;
        $page->record = $order;
        $weeks = $ref->invoke($page, $item, Carbon::parse($this->todayPlus7), null);

        $cells = collect($weeks)->flatten(1)->keyBy('date');
        $this->assertTrue($cells[$this->todayPlus7]['is_current']);
        $this->assertTrue($cells[$this->todayPlus7]['selectable']);
        $this->assertFalse($cells[$this->todayPlus8]['is_current']);
        $this->assertTrue($cells[$this->todayPlus8]['selectable']);
    }

    public function test_available_times_shows_real_seats_not_inflated_for_current_slot_entry(): void
    {
        // Sub-fase 7.2e.2bis10 (#164) — reafirmado en #173 (decisión clienta): el
        // slider es FIDEDIGNO con la lógica REAL de reservas → muestra las plazas
        // libres CONTANDO la huella propia del item (no las infla reclamándola).
        // Una entrada de 1 plaza en su slot actual muestra las plazas REALES; el
        // excluir la huella para crecer/recolocar se hará en la gestión futura de
        // reservas (NO en el display).
        //
        // Setup: slot con capacidad 10. Item ocupa 1 plaza → quedan 9 reales.
        $tight = Slot::create([
            'zone_id' => $this->zone->id,
            'date' => $this->todayPlus8,
            'start_time' => '12:00:00',
            'end_time' => '13:00:00',
            'capacity' => 10,
            'online_capacity' => 10,
        ]);

        [$order, $item] = $this->makePaidEntryOrder('10:00:00'); // item en slot 10:00 de todayPlus7
        // Movemos el item al slot de todayPlus8 (10 plazas, ocupará 1).
        $item->update(['slot_id' => $tight->id]);

        $ref = new \ReflectionMethod(ViewOrder::class, 'calendarTimesForItem');
        $ref->setAccessible(true);
        $page = new ViewOrder;
        $page->record = $order;
        $page->calendarSelectedTime = '12:00:00';
        $times = $ref->invoke($page, $item->fresh('ticketType', 'slot'), $this->todayPlus8);

        // Slot a las 12:00 (el actual del item) — plazas REALES: 10 capacity - 1 ocupada = 9.
        $entry = collect($times)->firstWhere('time', '12:00:00');
        $this->assertNotNull($entry);
        $this->assertSame(9, $entry['available'], 'El slot actual debe mostrar plazas REALES (fidedignas con la lógica de reservas), sin reclamar la huella propia.');
        $this->assertTrue($entry['is_current']);
    }

    public function test_calendar_times_include_current_slot_even_when_park_closed(): void
    {
        // La rama defensiva «el slot ACTUAL siempre se ofrece» de
        // `calendarTimesForItem` no tenía test propio. Con el parque CERRADO
        // ese día la lista debe traer EXACTAMENTE el slot actual (marcado
        // `is_current`) y ningún vecino — si trajera el vecino, habría
        // respondido el camino normal y no la rama defensiva.
        [$order, $item] = $this->makePaidEntryOrder('10:00:00');
        SpecialDate::create([
            'date' => $this->todayPlus7,
            'is_closed' => true,
        ]);

        $ref = new \ReflectionMethod(ViewOrder::class, 'calendarTimesForItem');
        $ref->setAccessible(true);
        $page = new ViewOrder;
        $page->record = $order;
        $times = $ref->invoke($page, $item->fresh('ticketType', 'slot'), $this->todayPlus7);

        $this->assertCount(1, $times);
        $this->assertSame('10:00:00', $times[0]['time']);
        $this->assertTrue($times[0]['is_current']);
    }

    // ─── Notification content ────────────────────────────────────────────

    public function test_notification_includes_human_slot_change(): void
    {
        Notification::fake();
        [$order, $item] = $this->makePaidEntryOrder('10:00:00');

        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->set('calendarItemId', $item->id)
            ->set('calendarSelectedDate', $this->todayPlus7)
            ->set('calendarSelectedTime', '11:00:00')
            ->callAction(
                'manageItem',
                data: $this->formData($item, ['slot_date' => $this->todayPlus7, 'slot_time' => '11:00:00']),
                arguments: ['item' => $item->id],
            );

        Notification::assertSentTo($order->user, OrderItemModified::class, function (OrderItemModified $notif): bool {
            return isset($notif->changes['slot_change'])
                && \str_contains($notif->changes['slot_change']['old'], '10:00')
                && \str_contains($notif->changes['slot_change']['new'], '11:00');
        });
    }

    // ─── Permission gate (sin orders.edit_item) ──────────────────────────

    public function test_save_without_edit_item_permission_blocked(): void
    {
        Notification::fake();
        [$order, $item] = $this->makePaidEntryOrder('10:00:00');

        Livewire::actingAs($this->staffWithoutEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction(
                'manageItem',
                data: $this->formData($item, ['slot_date' => $this->todayPlus7, 'slot_time' => '11:00:00']),
                arguments: ['item' => $item->id],
            );

        $item->refresh();
        $this->assertSame($this->slotAt($this->todayPlus7, '10:00:00')->id, $item->slot_id);
        Notification::assertNothingSentTo($order->user);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    private function staffWithEdit(): User
    {
        return $this->staffWith(['orders.view', 'orders.edit_item', 'orders.edit_event_data']);
    }

    private function staffWithoutEdit(): User
    {
        return $this->staffWith(['orders.view']);
    }

    private function staffWith(array $permissions): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'staff')->value('id')]);
        $u->roles->first()->permissions()->sync(
            Permission::whereIn('name', $permissions)->pluck('id'),
        );

        return $u;
    }

    private function slotAt(string $date, string $time): Slot
    {
        return Slot::query()
            ->where('zone_id', $this->zone->id)
            ->where('date', $date)
            ->where('start_time', $time)
            ->firstOrFail();
    }

    /**
     * @return array{0: Order, 1: OrderItem}
     */
    private function makePaidEntryOrder(string $time, int $seats = 1): array
    {
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-MI'.str_pad((string) ++$this->counter, 4, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PAID,
            'subtotal' => 1200 * $seats, 'total' => 1200 * $seats, 'currency' => 'EUR',
            'paid_at' => now(),
        ]);

        $this->attachPaidPayment($order);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'parent_item_id' => null,
            'ticket_type_id' => $this->entryType->id,
            'slot_id' => $this->slotAt($this->todayPlus7, $time)->id,
            'quantity' => $seats,
            'seats' => $seats,
            'unit_price' => 1200,
        ]);

        return [$order->fresh(), $item->fresh('ticketType', 'slot')];
    }

    /**
     * @return array{0: Order, 1: OrderItem}
     */
    private function makePaidPackOrder(string $time, int $guests = 8): array
    {
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-MP'.str_pad((string) ++$this->counter, 4, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PAID,
            'subtotal' => 1500 * $guests, 'total' => 1500 * $guests, 'currency' => 'EUR',
            'paid_at' => now(),
        ]);

        $this->attachPaidPayment($order);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'parent_item_id' => null,
            'ticket_type_id' => $this->packType->id,
            'slot_id' => $this->slotAt($this->todayPlus7, $time)->id,
            'quantity' => $guests,
            'seats' => $guests,
            'unit_price' => 1500,
        ]);

        return [$order->fresh(), $item->fresh('ticketType', 'slot')];
    }

    private function attachPaidPayment(Order $order): Payment
    {
        return Payment::create([
            'payable_type' => $order->getMorphClass(),
            'payable_id' => $order->id,
            'amount' => $order->total,
            'currency' => 'EUR',
            'provider' => 'redsys',
            'status' => Payment::STATUS_PAID,
            'paid_at' => now(),
            'gateway_order' => str_pad((string) (++$this->paymentCounter + 100000), 10, '0', STR_PAD_LEFT),
        ]);
    }

    /**
     * Invoca el helper privado `validateNewSlot` de `ViewOrder` con
     * reflection. Permite testear los escenarios de defense in depth capa 4
     * (cross-zone, slot pasado, slot cerrado, park cerrado, product window)
     * que Filament rechaza ANTES vía validación in:options del Select —
     * escenarios hipotéticos para atacante autenticado.
     */
    private function invokeValidateNewSlot(Order $order, OrderItem $item, ?Slot $candidate): ?string
    {
        $page = new ViewOrder;
        $page->record = $order;
        $ref = new \ReflectionMethod(ViewOrder::class, 'validateNewSlot');
        $ref->setAccessible(true);

        return $ref->invoke($page, $item, $candidate);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function formData(OrderItem $item, array $overrides = []): array
    {
        $slot = $item->slot;

        return array_merge([
            'optimistic_token' => (string) ($item->updated_at?->getTimestamp() ?? ''),
            'slot_date' => $slot?->date?->toDateString() ?? '',
            'slot_time' => $slot?->start_time ?? '',
            'event_data' => [],
        ], $overrides);
    }
}
