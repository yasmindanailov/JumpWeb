<?php

namespace Tests\Feature\Sales;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Post-form de datos por invitado (#217, iter. 1) — lógica de modelo:
 *  - `TicketType::guestFields()` (normalización del esquema por-niño, gemelo de `event_fields`).
 *  - `TicketType::sanitizeGuestData()` / `guestDataComplete()` (saneo + completitud contra N).
 *  - `OrderItem::guestFormStatus()` / `isGuestFormComplete()` / `needsGuestForm()` (estado FORM OK/NO
 *    DERIVADO en vivo contra la cantidad actual).
 */
class GuestFieldsTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->zone = Zone::create([
            'slug' => 'jump', 'name' => ['es' => 'Jump'], 'accent' => 'jump',
            'color' => '#FF5B22', 'position' => 1, 'is_active' => true,
        ]);
    }

    private function makePack(array $guestFields, array $overrides = []): TicketType
    {
        return TicketType::create(array_merge([
            'name' => ['es' => 'Cumpleaños Jump'],
            'type' => TicketType::TYPE_PACK,
            'zone_id' => $this->zone->id,
            'duration_min' => 120,
            'min_qty' => 2,
            'max_qty' => 20,
            'deposit_type' => TicketType::DEPOSIT_NONE,
            'deposit_value' => 0,
            'guest_fields' => $guestFields,
            'seats_per_unit' => 1,
            'tax_rate' => 21,
            'is_sellable' => true,
            'is_active' => true,
            'position' => 9,
        ], $overrides));
    }

    /** Crea un item-pack persistido con su tipo cargado, listo para los helpers de estado. */
    private function packItem(TicketType $pack, int $quantity, ?array $guestData = null): OrderItem
    {
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-'.Str::upper(Str::random(6)),
            'status' => Order::STATUS_PAID,
        ]);

        $item = $order->items()->create([
            'ticket_type_id' => $pack->id,
            'slot_id' => null,
            'quantity' => $quantity,
            'unit_price' => 1000,
            'seats' => $quantity,
            'guest_data' => $guestData,
        ]);

        return $item->load('ticketType');
    }

    // ─── TicketType: esquema guest_fields ────────────────────────────────────

    public function test_guest_fields_are_normalized_like_event_fields(): void
    {
        $pack = $this->makePack([
            ['key' => 'name', 'type' => 'bogus', 'required' => '1', 'label' => ['es' => 'Nombre']],
            ['key' => '', 'type' => 'text', 'label' => ['es' => 'Sin clave']],   // sin clave → fuera
            ['type' => 'text'],                                                  // sin clave → fuera
        ]);

        $fields = $pack->guestFields();

        $this->assertCount(1, $fields);
        $this->assertSame('name', $fields[0]['key']);
        $this->assertSame('text', $fields[0]['type']);   // tipo inválido → text
        $this->assertTrue($fields[0]['required']);
    }

    public function test_default_guest_fields_constant_is_well_formed(): void
    {
        $pack = $this->makePack(TicketType::DEFAULT_GUEST_FIELDS);

        $keys = array_column($pack->guestFields(), 'key');
        $this->assertSame(['name', 'allergy', 'notes', 'special_menu'], $keys);

        // Solo "nombre" es obligatorio por defecto.
        $required = array_values(array_filter($pack->guestFields(), fn ($f) => $f['required']));
        $this->assertCount(1, $required);
        $this->assertSame('name', $required[0]['key']);
    }

    // ─── sanitizeGuestData ───────────────────────────────────────────────────

    public function test_sanitize_guest_data_clamps_to_count_and_strips_unknown_keys(): void
    {
        $pack = $this->makePack([
            ['key' => 'name', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre']],
            ['key' => 'age', 'type' => 'number', 'required' => false, 'label' => ['es' => 'Edad']],
        ]);

        $clean = $pack->sanitizeGuestData([
            ['name' => '  Ana  ', 'age' => '7 años', 'hacker' => 'x'],
            ['name' => 'Leo', 'age' => ''],
            ['name' => 'Sobra'],   // 3.ª fila con count=2 → se descarta
        ], 2);

        $this->assertCount(2, $clean);
        $this->assertSame('Ana', $clean[0]['name']);     // recortado
        $this->assertSame('7', $clean[0]['age']);        // number → solo dígitos
        $this->assertArrayNotHasKey('hacker', $clean[0]); // clave fuera del esquema → descartada
        $this->assertSame('Leo', $clean[1]['name']);
        $this->assertArrayNotHasKey('age', $clean[1]);    // vacío → no se guarda
    }

    public function test_sanitize_guest_data_pads_missing_rows(): void
    {
        $pack = $this->makePack(TicketType::DEFAULT_GUEST_FIELDS);

        // Solo se envía 1 fila pero hay 3 niños → 3 entradas, las 2 últimas vacías.
        $clean = $pack->sanitizeGuestData([['name' => 'Ana']], 3);

        $this->assertCount(3, $clean);
        $this->assertSame('Ana', $clean[0]['name']);
        $this->assertSame([], $clean[1]);
        $this->assertSame([], $clean[2]);
    }

    public function test_sanitize_guest_data_handles_non_contiguous_lists(): void
    {
        $pack = $this->makePack(TicketType::DEFAULT_GUEST_FIELDS);

        // Lista ASOCIATIVA (claves string, como un repeater de Livewire por UUID) → no pierde datos.
        $assoc = $pack->sanitizeGuestData(['a' => ['name' => 'Ana'], 'b' => ['name' => 'Leo']], 2);
        $this->assertSame('Ana', $assoc[0]['name'] ?? null);
        $this->assertSame('Leo', $assoc[1]['name'] ?? null);

        // Lista con HUECOS (índices 0 y 2, falta el 1) → se compacta sin perder al 3.º niño.
        $gapped = $pack->sanitizeGuestData([0 => ['name' => 'Ana'], 2 => ['name' => 'Leo']], 2);
        $this->assertSame('Ana', $gapped[0]['name'] ?? null);
        $this->assertSame('Leo', $gapped[1]['name'] ?? null);
    }

    public function test_sanitize_caps_value_length(): void
    {
        $pack = $this->makePack(TicketType::DEFAULT_GUEST_FIELDS);
        $long = str_repeat('x', TicketType::ANSWER_MAX_LENGTH + 500);

        // Cap defensivo (#217): un valor gigante se trunca al tope, no infla la columna JSON.
        $clean = $pack->sanitizeGuestData([['name' => $long]], 1);
        $this->assertSame(TicketType::ANSWER_MAX_LENGTH, mb_strlen($clean[0]['name']));
    }

    public function test_sanitize_and_complete_handle_zero_or_negative_count(): void
    {
        $pack = $this->makePack(TicketType::DEFAULT_GUEST_FIELDS);

        $this->assertSame([], $pack->sanitizeGuestData([['name' => 'x']], 0));
        $this->assertTrue($pack->guestDataComplete([], 0));
        // Cantidad negativa (cálculo erróneo aguas arriba) se clampa a 0 sin iterar en negativo.
        $this->assertSame([], $pack->sanitizeGuestData([], -3));
        $this->assertTrue($pack->guestDataComplete([], -3));
    }

    // ─── guestDataComplete (derivado contra N) ───────────────────────────────

    public function test_guest_data_complete_requires_all_children_required_columns(): void
    {
        $pack = $this->makePack([
            ['key' => 'name', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre']],
        ]);

        $this->assertTrue($pack->guestDataComplete([['name' => 'Ana'], ['name' => 'Leo']], 2));
        $this->assertFalse($pack->guestDataComplete([['name' => 'Ana']], 2));        // falta el 2.º
        $this->assertFalse($pack->guestDataComplete([['name' => 'Ana'], []], 2));    // 2.º vacío
    }

    public function test_guest_data_complete_recomputes_against_quantity(): void
    {
        $pack = $this->makePack([
            ['key' => 'name', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre']],
        ]);
        $rows = [['name' => 'Ana'], ['name' => 'Leo']];

        $this->assertTrue($pack->guestDataComplete($rows, 2));
        // Sube el nº de niños → el mismo dato ya no completa (falta el 3.º).
        $this->assertFalse($pack->guestDataComplete($rows, 3));
    }

    public function test_guest_data_complete_true_when_no_required_columns(): void
    {
        $pack = $this->makePack([
            ['key' => 'notes', 'type' => 'textarea', 'required' => false, 'label' => ['es' => 'Notas']],
        ]);

        // Sin columnas obligatorias, basta con que existan las N filas (aunque vacías).
        $this->assertTrue($pack->guestDataComplete([], 5));
    }

    // ─── OrderItem: estado del post-form ─────────────────────────────────────

    public function test_status_is_null_for_entry(): void
    {
        $entry = TicketType::create([
            'name' => ['es' => 'Jump 1h'], 'type' => TicketType::TYPE_ENTRY,
            'zone_id' => $this->zone->id, 'duration_min' => 60, 'seats_per_unit' => 1,
            'tax_rate' => 21, 'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
        $item = $this->packItem($entry, 2);

        $this->assertNull($item->guestFormStatus());
        $this->assertTrue($item->isGuestFormComplete());
        $this->assertFalse($item->needsGuestForm());
    }

    public function test_status_is_null_for_pack_without_guest_fields(): void
    {
        $pack = $this->makePack([], ['guest_fields' => null]);
        $item = $this->packItem($pack, 4);

        $this->assertNull($item->guestFormStatus());
    }

    public function test_status_pending_when_incomplete(): void
    {
        $pack = $this->makePack(TicketType::DEFAULT_GUEST_FIELDS);
        $item = $this->packItem($pack, 2, [['name' => 'Ana']]); // falta el 2.º niño

        $this->assertSame(OrderItem::GUEST_FORM_STATUS_PENDING, $item->guestFormStatus());
        $this->assertFalse($item->isGuestFormComplete());
        $this->assertTrue($item->needsGuestForm());
    }

    public function test_status_ok_when_complete(): void
    {
        $pack = $this->makePack(TicketType::DEFAULT_GUEST_FIELDS);
        $item = $this->packItem($pack, 2, [['name' => 'Ana'], ['name' => 'Leo']]);

        $this->assertSame(OrderItem::GUEST_FORM_STATUS_OK, $item->guestFormStatus());
        $this->assertTrue($item->isGuestFormComplete());
        $this->assertFalse($item->needsGuestForm());
    }

    public function test_raising_quantity_reverts_status_to_pending(): void
    {
        $pack = $this->makePack(TicketType::DEFAULT_GUEST_FIELDS);
        $item = $this->packItem($pack, 2, [['name' => 'Ana'], ['name' => 'Leo']]);
        $this->assertSame(OrderItem::GUEST_FORM_STATUS_OK, $item->guestFormStatus());

        // El empleado añade un niño → el form vuelve a estar pendiente (fila nueva vacía).
        $item->update(['quantity' => 3]);
        $this->assertSame(OrderItem::GUEST_FORM_STATUS_PENDING, $item->fresh()->load('ticketType')->guestFormStatus());
    }

    public function test_lowering_quantity_keeps_status_ok(): void
    {
        $pack = $this->makePack(TicketType::DEFAULT_GUEST_FIELDS);
        $item = $this->packItem($pack, 3, [['name' => 'Ana'], ['name' => 'Leo'], ['name' => 'Max']]);
        $this->assertSame(OrderItem::GUEST_FORM_STATUS_OK, $item->guestFormStatus());

        // Un niño cancela → baja la cantidad; el form sigue completo (la fila sobrante se ignora).
        $item->update(['quantity' => 2]);
        $this->assertSame(OrderItem::GUEST_FORM_STATUS_OK, $item->fresh()->load('ticketType')->guestFormStatus());
    }

    public function test_mark_guest_form_completed_only_stamps_timestamp(): void
    {
        $pack = $this->makePack(TicketType::DEFAULT_GUEST_FIELDS);
        $item = $this->packItem($pack, 2, [['name' => 'Ana'], ['name' => 'Leo']]);

        $this->assertNull($item->guest_form_completed_at);
        $item->markGuestFormCompleted();

        $this->assertNotNull($item->fresh()->guest_form_completed_at);
        // El sello es auditoría: el estado sigue derivándose de los datos.
        $this->assertSame(OrderItem::GUEST_FORM_STATUS_OK, $item->fresh()->load('ticketType')->guestFormStatus());
    }
}
