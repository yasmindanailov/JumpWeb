<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\Balance;
use App\Domain\Booking\Services\ReservationSlip;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentRefund;
use App\Domain\Platform\Models\AuditLog;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Decisión #183 — Hoja de reserva imprimible (PDF A4) por OrderItem principal,
 * para la operativa física del parque.
 *
 * Cubre: acceso (auth/permiso/rol), defensa IDOR cross-pedido, solo-principales,
 * audit log, idioma forzado a español, contenido operativo presente y AUSENCIA
 * de datos de cobro sensibles, y el cálculo "a cobrar en puerta" por reserva.
 */
class ReservationSlipTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $entry;

    private TicketType $pack;

    private TicketType $addon;

    private int $slotCounter = 0;

    private int $paymentCounter = 0;

    private int $ownerCounter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);

        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'Zona Jump'], 'color' => '#FF5B22']);

        $this->entry = TicketType::create([
            'name' => ['es' => 'Entrada 1 hora'], 'type' => TicketType::TYPE_ENTRY,
            'zone_id' => $this->zone->id, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);

        $this->pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños Jump'], 'type' => TicketType::TYPE_PACK,
            'zone_id' => $this->zone->id, 'duration_min' => 120,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 2,
            'event_fields' => [
                ['key' => 'celebrant', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Homenajeado']],
                ['key' => 'age', 'type' => 'number', 'required' => false, 'label' => ['es' => 'Edad']],
            ],
        ]);

        $this->addon = TicketType::create([
            'name' => ['es' => 'Calcetines antideslizantes'], 'type' => TicketType::TYPE_ADDON,
            'zone_id' => null, 'duration_min' => null,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 3,
        ]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function userWithRole(string $role): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', $role)->value('id')]);

        return $u;
    }

    private function staff(): User
    {
        return $this->userWithRole('staff');
    }

    private function makeSlot(string $date = '2099-01-01', ?string $start = null): Slot
    {
        $h = str_pad((string) ($this->slotCounter++ % 23), 2, '0', STR_PAD_LEFT);
        $start ??= "{$h}:00:00";

        return Slot::create([
            'zone_id' => $this->zone->id, 'date' => $date,
            'start_time' => $start, 'end_time' => '23:59:00',
            'capacity' => 20, 'online_capacity' => 20,
        ]);
    }

    private function makeOrder(string $status = Order::STATUS_PAID, ?User $owner = null): Order
    {
        $owner ??= User::factory()->create([
            'name' => 'Ana Pérez', 'phone' => '612345678',
            'email' => 'ana'.(++$this->ownerCounter).'@example.test',
            'waiver_accepted_at' => now(),
        ]);

        $attrs = [
            'user_id' => $owner->id,
            'code' => 'JJ-T'.bin2hex(random_bytes(2)),
            'status' => $status,
            'subtotal' => 18000, 'total' => 18000, 'currency' => 'EUR',
        ];
        if ($status === Order::STATUS_PAID) {
            $attrs['paid_at'] = now();
        }

        return Order::create($attrs);
    }

    private function makeItem(Order $order, TicketType $type, ?Slot $slot, array $attrs = []): OrderItem
    {
        return OrderItem::create(array_merge([
            'order_id' => $order->id, 'parent_item_id' => null,
            'ticket_type_id' => $type->id, 'slot_id' => $slot?->id,
            'quantity' => 1, 'seats' => 1, 'unit_price' => 18000,
        ], $attrs));
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
            'transaction_id' => 'TXN-SECRET-9988',
            'auth_code' => 'AUTHSECRET77',
            'gateway_order' => str_pad((string) (++$this->paymentCounter + 100000), 10, '0', STR_PAD_LEFT),
            'raw_response' => ['Ds_Response' => '0000', 'Ds_Card_Number' => '************1234'],
        ]);
    }

    /** Pedido pagado completo: pack + complemento + pago. */
    private function fullPaidReservation(): array
    {
        $order = $this->makeOrder();
        $item = $this->makeItem($order, $this->pack, $this->makeSlot('2099-06-20', '16:00:00'), [
            'quantity' => 16, 'seats' => 16, 'unit_price' => 18000,
            'event_data' => ['celebrant' => 'Lucía', 'age' => '7'],
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => $item->id,
            'ticket_type_id' => $this->addon->id, 'slot_id' => null,
            'quantity' => 16, 'seats' => 0, 'unit_price' => 200,
        ]);
        // Lo facturado = las líneas (16 × 180,00 + 16 × 2,00): desde el libro (T3·2) un pedido cuyo
        // total no es la suma de lo que nació responde «en revisión» (identidad I1), con razón.
        $order->forceFill(['subtotal' => 291200, 'total' => 291200])->save();
        $this->attachPaidPayment($order);

        return [$order, $item];
    }

    private function slipUrl(Order $order, OrderItem $item): string
    {
        return route('admin.orders.items.slip', [$order, $item]);
    }

    // ─── Acceso ─────────────────────────────────────────────────────────────

    public function test_guest_is_redirected_to_login(): void
    {
        [$order, $item] = $this->fullPaidReservation();

        $this->get($this->slipUrl($order, $item))->assertRedirect(route('login'));
    }

    public function test_customer_gets_403(): void
    {
        $customer = $this->userWithRole('customer');
        [$order, $item] = $this->fullPaidReservation();

        $this->actingAs($customer)->get($this->slipUrl($order, $item))->assertForbidden();
    }

    public function test_staff_without_orders_view_gets_403(): void
    {
        $staff = $this->staff();
        $perm = Permission::where('name', 'orders.view')->value('id');
        $staff->roles->first()->permissions()->detach($perm);

        [$order, $item] = $this->fullPaidReservation();

        $this->actingAs($staff)->get($this->slipUrl($order, $item))->assertForbidden();
    }

    public function test_staff_can_download_pdf(): void
    {
        $staff = $this->staff();
        [$order, $item] = $this->fullPaidReservation();

        $response = $this->actingAs($staff)->get($this->slipUrl($order, $item));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        // L1 (auditoría Fase 1): la hoja imprime nombres+alergias de menores → no-store (sin caché en disco).
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        // Cabecera de PDF válido (los bytes empiezan por la firma "%PDF-").
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_admin_can_download_pdf(): void
    {
        $admin = $this->userWithRole('admin');
        [$order, $item] = $this->fullPaidReservation();

        $this->actingAs($admin)->get($this->slipUrl($order, $item))->assertOk();
    }

    // ─── Defensa IDOR + solo principales ─────────────────────────────────────

    public function test_404_when_item_belongs_to_a_different_order(): void
    {
        $staff = $this->staff();
        [$orderA, $itemA] = $this->fullPaidReservation();
        $orderB = $this->makeOrder();

        $this->actingAs($staff)->get($this->slipUrl($orderB, $itemA))->assertNotFound();
    }

    // ─── #F11 — voided-leftover (fantasma net-cero) oculto / no imprimible ─────

    public function test_addons_excludes_voided_leftover_child(): void
    {
        // #F3: el inventario operativo del PDF (addons) excluye el complemento
        // "fantasma" net-cero, igual que addonBreakdown — antes solo este último
        // lo rechazaba y el inventario pintaba una línea tachada huérfana.
        [$order, $item] = $this->fullPaidReservation();   // pack + 1 addon "Calcetines"

        // Complemento fantasma: cancelado, nunca cobrado online (gratis) ni
        // reembolsado → isVoidedLeftoverItem() true. (El predicado en sí se prueba
        // en ManageItemAddonsTest con el escenario realista de extra_due.)
        $ghostType = TicketType::create([
            'name' => ['es' => 'Tarta fantasma'], 'type' => TicketType::TYPE_ADDON,
            'zone_id' => null, 'is_sellable' => true, 'is_active' => true,
            'seats_per_unit' => 1, 'position' => 9,
        ]);
        $ghost = OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => $item->id,
            'ticket_type_id' => $ghostType->id, 'slot_id' => null,
            'quantity' => 1, 'seats' => 0, 'unit_price' => 0, 'cancelled_at' => now(),
        ]);
        $order->load(['payments.refunds', 'adjustments']);
        $this->assertTrue($order->isVoidedLeftoverItem($ghost->fresh()));

        $slip = ReservationSlip::make($order, $item->fresh(['children.ticketType']));

        $names = array_column($slip->addons(), 'name');
        $this->assertContains('Calcetines antideslizantes', $names);   // el normal sí
        $this->assertNotContains('Tarta fantasma', $names);            // el fantasma no
        // Paridad con addonBreakdown (que ya excluía el fantasma).
        $this->assertCount(count($slip->addonBreakdown()), $slip->addons());
    }

    public function test_404_for_voided_leftover_principal_slip(): void
    {
        // Un principal fantasma (entrada gratuita 0€ cancelada, net-cero) no es una
        // reserva real → no se imprime su hoja (defensivo: sin botón que lleve aquí).
        $staff = $this->staff();
        $order = $this->makeOrder();
        $ghost = $this->makeItem($order, $this->entry, $this->makeSlot('2099-06-21', '17:00:00'), [
            'unit_price' => 0, 'cancelled_at' => now(),
        ]);
        $this->assertTrue($order->fresh()->isVoidedLeftoverItem($ghost->fresh()));

        $this->actingAs($staff)->get($this->slipUrl($order, $ghost))->assertNotFound();
    }

    public function test_cancelled_but_charged_principal_still_prints_slip(): void
    {
        // Control positivo: un cancelado que SÍ se cobró online (no net-cero) NO es
        // voided-leftover → su hoja se sigue imprimiendo (el guard #F11 solo bloquea
        // el fantasma).
        $staff = $this->staff();
        $order = $this->makeOrder();
        $charged = $this->makeItem($order, $this->entry, $this->makeSlot('2099-06-22', '18:00:00'), [
            'unit_price' => 18000, 'cancelled_at' => now(),
        ]);
        $this->assertFalse($order->fresh()->isVoidedLeftoverItem($charged->fresh()));

        $this->actingAs($staff)->get($this->slipUrl($order, $charged))->assertOk();
    }

    public function test_404_when_item_is_an_addon(): void
    {
        $staff = $this->staff();
        [$order, $item] = $this->fullPaidReservation();
        $addonChild = $order->items()->whereNotNull('parent_item_id')->first();

        $this->assertNotNull($addonChild);
        $this->actingAs($staff)->get($this->slipUrl($order, $addonChild))->assertNotFound();
    }

    // ─── Audit log ───────────────────────────────────────────────────────────

    public function test_audit_log_is_written(): void
    {
        $staff = $this->staff();
        [$order, $item] = $this->fullPaidReservation();

        $this->actingAs($staff)->get($this->slipUrl($order, $item))->assertOk();

        $log = AuditLog::where('action', 'orders.slip_printed')->latest()->first();
        $this->assertNotNull($log);
        $this->assertSame($staff->id, $log->user_id);
        $this->assertSame($order->code, $log->payload['order_code']);
        $this->assertSame($item->id, $log->payload['order_item_id']);
        $this->assertSame($item->ticket_type_id, $log->payload['ticket_type_id']);
        $this->assertSame($order->getMorphClass(), $log->target_type);
        $this->assertSame($order->id, $log->target_id);
    }

    // ─── ④ Hoja con/sin precios (decisión clienta 2026-06-14) ────────────────

    public function test_slip_hides_the_economic_breakdown_by_default(): void
    {
        App::setLocale('es');
        [$order, $item] = $this->fullPaidReservation();

        // Sin `showPrices`: hoja OPERATIVA de sala. Mantiene los datos de la reserva y la LISTA de
        // complementos contratados, pero OMITE por completo el desglose económico (totales, importes).
        $html = view('pdf.reservation-slip', [
            'slip' => ReservationSlip::make($order->fresh(), $item->fresh()),
        ])->render();

        $this->assertStringContainsString('Datos de la reserva', $html);          // contenido operativo
        $this->assertStringContainsString('Calcetines antideslizantes', $html);   // complementos listados
        // «Total del producto» es la etiqueta de la línea de total (solo en la tabla renderizada; NO
        // en los comentarios del <style>) → discriminador limpio de que la sección económica se omite.
        $this->assertStringNotContainsString('Total del producto', $html);
        $this->assertStringNotContainsString('€', $html);                         // sin NINGÚN importe
    }

    public function test_slip_shows_the_economic_breakdown_with_the_prices_flag(): void
    {
        App::setLocale('es');
        [$order, $item] = $this->fullPaidReservation();

        $html = view('pdf.reservation-slip', [
            'slip' => ReservationSlip::make($order->fresh(), $item->fresh()),
            'showPrices' => true,
        ])->render();

        // El libro de la reserva con su caja de saldo (T3·2): todo pagado y visita por delante → saldado.
        $this->assertStringContainsString('data-book-balance="settled"', $html);
        $this->assertStringContainsString(__('admin.orders.book.balance_settled'), $html);
        $this->assertStringContainsString('€', $html);                  // sí hay importes
    }

    public function test_audit_records_whether_prices_were_shown(): void
    {
        $staff = $this->staff();
        [$order, $item] = $this->fullPaidReservation();

        // Hoja operativa (por defecto) → precios = false.
        $this->actingAs($staff)->get($this->slipUrl($order, $item))->assertOk();
        $this->assertFalse(AuditLog::where('action', 'orders.slip_printed')->latest()->first()->payload['precios']);

        // Hoja con precios (`?precios=1`) → precios = true.
        $this->actingAs($staff)->get($this->slipUrl($order, $item).'?precios=1')->assertOk();
        $this->assertTrue(AuditLog::where('action', 'orders.slip_printed')->latest()->first()->payload['precios']);
    }

    // ─── Idioma forzado a español ────────────────────────────────────────────

    public function test_locale_is_forced_to_spanish_even_when_panel_locale_is_chinese(): void
    {
        $staff = $this->staff();
        $staff->forceFill(['panel_locale' => 'zh_CN'])->save();
        [$order, $item] = $this->fullPaidReservation();

        App::setLocale('zh_CN');

        $this->actingAs($staff)->get($this->slipUrl($order, $item))->assertOk();

        // El controlador fuerza ES durante la request; el override persiste tras
        // la respuesta en el proceso de test → prueba de que la hoja va en español.
        $this->assertSame('es', App::getLocale());
    }

    // ─── Contenido operativo + ausencia de PII de cobro ──────────────────────

    public function test_view_renders_operative_data_in_spanish(): void
    {
        App::setLocale('es');
        [$order, $item] = $this->fullPaidReservation();

        // Hoja CON precios: este test cubre el render COMPLETO en español (incluye los totales).
        $html = view('pdf.reservation-slip', [
            'slip' => ReservationSlip::make($order->fresh(), $item->fresh()),
            'showPrices' => true,
        ])->render();

        // Identificación + cabecera.
        $this->assertStringContainsString('Hoja de reserva', $html);
        $this->assertStringContainsString($order->code, $html);
        // Wordmark data-driven (Fase 1): sin `business.name` sembrado cae al nombre de producto.
        $this->assertStringContainsString(Str::upper(config('app.name')), $html);
        // Producto (el nombre ya incluye la zona; el chip de zona se quitó).
        $this->assertStringContainsString('Cumpleaños Jump', $html);
        // Datos de la reserva: cumpleañero + padre/tutor (cliente) + teléfono.
        $this->assertStringContainsString('Datos de la reserva', $html);
        $this->assertStringContainsString('Homenajeado', $html);
        $this->assertStringContainsString('Lucía', $html);
        $this->assertStringContainsString('Padre/madre o tutor legal', $html);
        $this->assertStringContainsString('Ana Pérez', $html);
        $this->assertStringContainsString('612345678', $html);
        // Complemento + totales del producto.
        $this->assertStringContainsString('Calcetines antideslizantes', $html);
        $this->assertStringContainsString('Totales del producto', $html);
        // Pulidos clienta: NO waiver, NO email, NO badge "Completado".
        $this->assertStringNotContainsString('Waiver', $html);
        $this->assertStringNotContainsString('Aceptado', $html);
        $this->assertStringNotContainsString((string) $order->user->email, $html);
        $this->assertStringNotContainsString('Completado', $html);
        // Ninguna clave i18n cruda debe filtrarse al PDF (todas traducidas).
        $this->assertStringNotContainsString('admin.orders.', $html);
    }

    public function test_prepared_is_a_manual_checkbox_without_digital_state(): void
    {
        App::setLocale('es');
        $staff = $this->staff();
        $order = $this->makeOrder();
        $item = $this->makeItem($order, $this->pack, $this->makeSlot('2099-06-20', '16:00:00'), [
            'event_data' => ['celebrant' => 'Lucía'],
        ]);
        // La hoja lleva una casilla "Preparado" para tachar A MANO (papel), sin
        // ningún estado digital (el sistema "preparado/no preparado" se retiró, #202).

        $html = view('pdf.reservation-slip', [
            'slip' => ReservationSlip::make($order->fresh(), $item->fresh()),
        ])->render();

        // Casilla manual presente.
        $this->assertStringContainsString('prepared-box', $html);
        $this->assertStringContainsString('Preparado', $html);
        // Sin meta "Preparado el … por …" ni el nombre del empleado.
        $this->assertStringNotContainsString('Preparado el', $html);
        $this->assertStringNotContainsString((string) $staff->name, $html);
    }

    public function test_entry_reservation_uses_entries_quantity_label(): void
    {
        App::setLocale('es');
        $order = $this->makeOrder();
        $item = $this->makeItem($order, $this->entry, $this->makeSlot('2099-06-20', '16:00:00'), [
            'quantity' => 4, 'seats' => 4,
        ]);

        $slip = ReservationSlip::make($order->fresh(), $item->fresh());

        $this->assertFalse($slip->isPack());
        $this->assertSame('Entradas', $slip->quantityHeading());
        $this->assertSame('4 entradas', $slip->quantityLabel());

        $html = view('pdf.reservation-slip', ['slip' => $slip])->render();
        $this->assertStringContainsString('4 entradas', $html);
        $this->assertStringContainsString('Entrada 1 hora', $html);
    }

    // ─── Datos de la reserva: orden y etiquetas (pulido clienta) ─────────────

    public function test_reservation_data_rows_follow_requested_order_for_pack(): void
    {
        App::setLocale('es');
        $order = $this->makeOrder();
        // event_data en orden inverso al esquema para probar el reordenado.
        $item = $this->makeItem($order, $this->pack, $this->makeSlot('2099-06-20', '16:00:00'), [
            'event_data' => ['age' => '7', 'celebrant' => 'Lucía'],
        ]);

        $rows = ReservationSlip::make($order->fresh(), $item->fresh())->reservationDataRows();

        // 1) cumpleañero · 2) padre/tutor (= cliente) · 3) teléfono · 4) resto custom.
        $this->assertSame('Homenajeado', $rows[0]['label']);
        $this->assertSame('Lucía', $rows[0]['value']);
        $this->assertSame('Padre/madre o tutor legal', $rows[1]['label']);
        $this->assertSame('Ana Pérez', $rows[1]['value']);
        $this->assertSame('Teléfono', $rows[2]['label']);
        $this->assertSame('612345678', $rows[2]['value']);
        $this->assertSame('Edad', $rows[3]['label']);
        $this->assertSame('7', $rows[3]['value']);
    }

    public function test_reservation_data_rows_use_client_label_for_entry(): void
    {
        App::setLocale('es');
        $order = $this->makeOrder();
        $item = $this->makeItem($order, $this->entry, $this->makeSlot('2099-06-20', '16:00:00'), [
            'quantity' => 4, 'seats' => 4,
        ]);

        $rows = ReservationSlip::make($order->fresh(), $item->fresh())->reservationDataRows();

        // Una entrada no tiene homenajeado/tutor → "Nombre del cliente" + teléfono.
        $this->assertCount(2, $rows);
        $this->assertSame('Nombre del cliente', $rows[0]['label']);
        $this->assertSame('Ana Pérez', $rows[0]['value']);
        $this->assertSame('Teléfono', $rows[1]['label']);
    }

    // ─── Totales del producto (espejo de la sub-card) ─────────────────────────

    public function test_product_totals_breakdown(): void
    {
        $order = $this->makeOrder();
        $item = $this->makeItem($order, $this->pack, $this->makeSlot('2099-06-20', '16:00:00'), [
            'quantity' => 8, 'seats' => 8, 'unit_price' => 1800,   // 8 invitados × 18,00 € = 144,00 €
        ]);
        $child = OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => $item->id,
            'ticket_type_id' => $this->addon->id, 'slot_id' => null,
            'quantity' => 2, 'seats' => 0, 'unit_price' => 200,    // 2 × 2,00 € = 4,00 €
        ]);
        $order->forceFill(['subtotal' => 14800, 'total' => 14800])->save(); // lo facturado = las líneas
        $payment = $this->attachPaidPayment($order);                       // 148,00 por web

        // Devolución confirmada de 5,00 € sobre el principal, con sus columnas (identidad I4).
        PaymentRefund::create([
            'payment_id' => $payment->id,
            'order_item_id' => $item->id,
            'amount_cents' => 500,
            'currency' => 'EUR',
            'status' => PaymentRefund::STATUS_SUCCEEDED,
            'mode' => PaymentRefund::MODE_REST,
            'requested_by' => $this->staff()->id,
            'requested_at' => now(),
            'processed_at' => now(),
        ]);
        $order->forceFill(['refund_amount_cents' => 500, 'refunded_at' => now()])->save();

        $slip = ReservationSlip::make($order->fresh(), $item->fresh());

        // Agregados de las LÍNEAS (qué se compró).
        $this->assertSame(14400, $slip->principalTotalCents());
        $this->assertSame(400, $slip->addonsTotalCents());
        $this->assertSame(14800, $slip->grandTotalCents());

        // EL LIBRO de la reserva (T3·2): el Total, la devolución como liquidación y lo pagado.
        $book = $slip->book();
        $this->assertTrue($book->isConsistent);
        $this->assertSame(14800, $book->totalCents);
        $this->assertSame(-500, collect($book->settlements)->firstWhere('kind', 'refund')?->amountCents);
        $this->assertSame(14300, $book->paidCents, '148,00 cobrados − 5,00 devueltos');

        // Desglose del principal: "8 invitados × 18,00 €".
        $pb = $slip->principalBreakdown();
        $this->assertSame('8 invitados', $pb['quantityLabel']);
        $this->assertSame(1800, $pb['unitPriceCents']);
        $this->assertSame(14400, $pb['totalCents']);
        $this->assertFalse($pb['cancelled']);

        // Desglose de cada complemento: "2 × 2,00 €".
        $ab = $slip->addonBreakdown();
        $this->assertCount(1, $ab);
        $this->assertSame('Calcetines antideslizantes', $ab[0]['name']);
        $this->assertSame(2, $ab[0]['quantity']);
        $this->assertSame(200, $ab[0]['unitPriceCents']);
        $this->assertSame(400, $ab[0]['totalCents']);

        // El render CON precios muestra el desglose por línea y el libro.
        App::setLocale('es');
        $html = view('pdf.reservation-slip', ['slip' => $slip, 'showPrices' => true])->render();
        $this->assertStringContainsString('Totales del producto', $html);
        $this->assertStringContainsString('8 invitados × 18,00 €', $html);
        $this->assertStringContainsString('144,00 €', $html);
        $this->assertStringContainsString('2 × 2,00 €', $html);
        $this->assertStringContainsString(__('tickets.journal.refund_card'), $html);
        $this->assertStringContainsString('−5,00 €', $html);

        // Cancelar el complemento (sin reembolsar) → una línea NEGATIVA en el libro y el Total baja.
        $child->markCancelled($this->staff());
        $book = ReservationSlip::make($order->fresh(), $item->fresh())->book();
        $cancel = collect($book->movements)->firstWhere('kind', 'cancel');
        $this->assertNotNull($cancel);
        $this->assertSame(-400, $cancel->amountCents);
        $this->assertSame(14400, $book->totalCents);
    }

    public function test_pdf_shows_prominent_pending_refund_box_when_owed(): void
    {
        // #200: el "Pendiente de devolución" se destaca en su propia caja (misma
        // prominencia que "A cobrar en puerta"), solo si queda algo por devolver.
        App::setLocale('es');
        [$order, $item] = $this->fullPaidReservation();

        // Sin nada que devolver → la caja de saldo es la neutra («nada pendiente»), nunca la roja.
        $clean = view('pdf.reservation-slip', ['slip' => ReservationSlip::make($order->fresh(), $item->fresh()), 'showPrices' => true])->render();
        $this->assertStringContainsString('data-book-balance="settled"', $clean);
        $this->assertStringNotContainsString('class="refund-box"', $clean);

        // Cancelar el complemento cobrado online → el libro debe 32,00 y hay visita por delante (2099):
        // «a devolver en el parque», en la caja roja (D-T3·7, D-T3·8).
        $item->children()->first()->markCancelled($this->staff());
        $slip = ReservationSlip::make($order->fresh(), $item->fresh());
        $this->assertSame(Balance::KIND_REFUND_AT_PARK, $slip->book()->balance->kind);
        $this->assertSame(-3200, $slip->book()->balance->cents);

        $html = view('pdf.reservation-slip', ['slip' => $slip, 'showPrices' => true])->render();
        $this->assertStringContainsString('class="refund-box"', $html);                          // caja prominente
        $this->assertStringContainsString(__('admin.orders.book.balance_refund_at_park'), $html);
        $this->assertStringContainsString('32,00', $html);                                       // importe (16 × 2,00 €)
    }

    public function test_real_pdf_with_pending_refund_box_renders_without_error(): void
    {
        // Genera el PDF REAL (dompdf) con la caja "Pendiente de devolución" → confirma
        // que el CSS nuevo no rompe el render (mismo subset que la caja de puerta). Con `?precios=1`
        // para que la caja económica se renderice (la hoja operativa por defecto la omite).
        $staff = $this->staff();
        [$order, $item] = $this->fullPaidReservation();
        $item->children()->first()->markCancelled($staff); // deja pendiente de devolución

        $response = $this->actingAs($staff)->get($this->slipUrl($order, $item).'?precios=1');

        $response->assertOk();
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_view_never_exposes_payment_pii(): void
    {
        App::setLocale('es');
        [$order, $item] = $this->fullPaidReservation();
        $payment = $order->payments()->first();

        $html = view('pdf.reservation-slip', [
            'slip' => ReservationSlip::make($order->fresh(), $item->fresh()),
        ])->render();

        // NADA de la dimensión de cobro debe aparecer en una hoja de puerta.
        $this->assertStringNotContainsString($payment->gateway_order, $html);
        $this->assertStringNotContainsString('AUTHSECRET77', $html);
        $this->assertStringNotContainsString('TXN-SECRET-9988', $html);
        // PAN enmascarada del raw_response (no colisiona con el teléfono del cliente).
        $this->assertStringNotContainsString('************1234', $html);
        $this->assertStringNotContainsString('Redsys', $html);
    }

    // ─── El libro: el saldo de la reserva en la hoja ─────────────────────────

    public function test_a_gate_charge_on_an_open_visit_is_money_to_pay_at_the_park(): void
    {
        $order = $this->makeOrder();
        $item = $this->makeItem($order, $this->pack, $this->makeSlot('2099-06-20', '16:00:00'));
        $this->attachPaidPayment($order);                                  // 180,00 por web
        // Una subida de precio de 24,00 desde el panel: el valor sube y el ajuste lo explica.
        $item->update(['unit_price' => 20400]);
        OrderAdjustment::create([
            'order_id' => $order->id, 'order_item_id' => $item->id,
            'type' => OrderAdjustment::TYPE_EDIT, 'amount_cents' => 2400, 'currency' => 'EUR',
            'context' => ['changes' => ['unit_price_change' => ['old' => 18000, 'new' => 20400]]],
            'applied_by' => $this->staff()->id,
        ]);

        $slip = ReservationSlip::make($order->fresh(), $item->fresh());
        $book = $slip->book();

        $this->assertTrue($book->isConsistent);
        $this->assertSame(Balance::KIND_PAY_AT_PARK, $book->balance->kind);
        $this->assertSame(2400, $book->balance->cents);

        App::setLocale('es');
        $html = view('pdf.reservation-slip', ['slip' => $slip, 'showPrices' => true])->render();
        $this->assertStringContainsString('class="gate-box"', $html);
        $this->assertStringContainsString(__('admin.orders.book.balance_pay_at_park'), $html);
        $this->assertStringContainsString('24,00 €', $html);
    }

    public function test_a_gate_charge_on_a_finished_visit_is_settled_at_the_park(): void
    {
        $order = $this->makeOrder();
        // Franja en el pasado → visita hecha → el cargo consta LIQUIDADO en el parque.
        $item = $this->makeItem($order, $this->pack, $this->makeSlot('2000-01-01', '16:00:00'));
        $this->attachPaidPayment($order);
        $item->update(['unit_price' => 20400]);
        OrderAdjustment::create([
            'order_id' => $order->id, 'order_item_id' => $item->id,
            'type' => OrderAdjustment::TYPE_EDIT, 'amount_cents' => 2400, 'currency' => 'EUR',
            'context' => ['changes' => ['unit_price_change' => ['old' => 18000, 'new' => 20400]]],
            'applied_by' => $this->staff()->id,
        ]);

        $book = ReservationSlip::make($order->fresh(), $item->fresh())->book();

        $this->assertTrue($book->isConsistent);
        $this->assertSame(Balance::KIND_SETTLED, $book->balance->kind);
        $this->assertSame(2400, collect($book->settlements)->firstWhere('kind', 'gate')?->amountCents, 'liquidado en el parque, con su fecha');
    }

    // ─── Presenter: datos del evento y complementos ──────────────────────────

    public function test_event_data_rows_follow_schema_order(): void
    {
        $order = $this->makeOrder();
        // event_data guardado en orden inverso al esquema (age antes que celebrant)
        // + una clave huérfana que NO está en el esquema → debe ir al final.
        $item = $this->makeItem($order, $this->pack, $this->makeSlot('2099-06-20', '16:00:00'), [
            'event_data' => ['age' => '7', 'legacy_note' => 'tarta sin gluten', 'celebrant' => 'Lucía'],
        ]);

        $rows = ReservationSlip::make($order->fresh(), $item->fresh())->eventDataRows();

        $this->assertSame('Homenajeado', $rows[0]['label']);
        $this->assertSame('Lucía', $rows[0]['value']);
        $this->assertSame('Edad', $rows[1]['label']);
        // La clave huérfana va al final con label humanizado.
        $this->assertSame('Legacy note', $rows[2]['label']);
        $this->assertSame('tarta sin gluten', $rows[2]['value']);
    }

    public function test_addons_are_listed_with_cancel_flag(): void
    {
        $order = $this->makeOrder();
        $item = $this->makeItem($order, $this->pack, $this->makeSlot('2099-06-20', '16:00:00'));
        $child = OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => $item->id,
            'ticket_type_id' => $this->addon->id, 'slot_id' => null,
            'quantity' => 16, 'seats' => 0, 'unit_price' => 200,
        ]);
        $child->markCancelled($this->staff());

        $addons = ReservationSlip::make($order->fresh(), $item->fresh())->addons();

        $this->assertCount(1, $addons);
        $this->assertSame('Calcetines antideslizantes', $addons[0]['name']);
        $this->assertSame(16, $addons[0]['quantity']);
        $this->assertTrue($addons[0]['cancelled']);
    }
}
