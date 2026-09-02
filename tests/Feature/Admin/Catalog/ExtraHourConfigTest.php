<?php

namespace Tests\Feature\Admin\Catalog;

use App\Domain\Booking\Models\ProductAddon;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\OrderCreator;
use App\Domain\Identity\Models\User;
use App\Filament\Resources\Catalog\Pages\CreateCatalog;
use App\Filament\Resources\Catalog\Pages\EditCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use ReflectionMethod;
use Tests\TestCase;

/**
 * La CONFIGURACIÓN de la hora extra (`specs/hora-extra.md` §4.1, §4.9 y §4.11, `#410`): las puertas
 * por las que el dato entra — el guard del modelo, el guard del PIVOTE (y su dirección cruzada, la
 * lección de `#324`), y las dos páginas del catálogo, porque hasta `#410` **el panel borraba el dato
 * del que depende el interruptor** y el diseño no se podía encender.
 *
 * ⚠️ El pivote dispara sus eventos porque la relación usa `->using(ProductAddon::class)`: `attach()`
 * y `updateExistingPivot()` crean el MODELO custom y pasan por `saving`. Sin el `using`, el guard
 * del pivote sería letra muerta — si alguien lo quita, estos casos se ponen rojos.
 */
class ExtraHourConfigTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);
    }

    // ─── El guard del MODELO (§4.1) ─────────────────────────────────────────────────────────────

    public function test_an_occupant_without_duration_is_impossible(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        TicketType::create([
            'name' => ['es' => 'Hora extra rota'], 'type' => TicketType::TYPE_ADDON,
            'occupies_after_parent' => true, 'duration_min' => null,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
    }

    public function test_only_an_addon_can_occupy_after_a_parent(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        TicketType::create([
            'name' => ['es' => 'Entrada torcida'], 'type' => TicketType::TYPE_ENTRY,
            'zone_id' => $this->zone->id, 'occupies_after_parent' => true, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
    }

    public function test_a_sane_occupant_saves_and_reads_back(): void
    {
        // El CONTROL de los dos de arriba: sin él, un guard que rechazara TODO saldría verde igual.
        $addon = $this->occupyingAddon();

        $fresh = $addon->fresh();
        $this->assertTrue($fresh->occupiesAfterParent());
        $this->assertTrue($fresh->hasSaneOccupancyConfig());
        $this->assertSame(60, (int) $fresh->duration_min);
    }

    // ─── El guard del PIVOTE, en las DOS direcciones (§4.4·5 + §7·D2, la lección de #324) ───────

    public function test_an_occupying_addon_cannot_be_attached_per_guest(): void
    {
        $entry = $this->entry();
        $addon = $this->occupyingAddon();

        $this->expectException(\InvalidArgumentException::class);

        $entry->addons()->attach($addon->id, $this->pivot(['quantity_mode' => ProductAddon::MODE_PER_GUEST]));
    }

    public function test_an_occupying_addon_cannot_be_attached_as_mandatory(): void
    {
        $entry = $this->entry();
        $addon = $this->occupyingAddon();

        $this->expectException(\InvalidArgumentException::class);

        $entry->addons()->attach($addon->id, $this->pivot(['is_mandatory' => true]));
    }

    public function test_an_occupying_addon_cannot_hang_from_a_pack(): void
    {
        $pack = TicketType::create([
            'name' => ['es' => 'Cumple'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $this->zone->id,
            'duration_min' => 120, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1,
            'min_qty' => 8, 'max_qty' => 20, 'position' => 2,
        ]);
        $addon = $this->occupyingAddon();

        $this->expectException(\InvalidArgumentException::class);

        $pack->addons()->attach($addon->id, $this->pivot());
    }

    public function test_reconfiguring_an_attached_occupant_to_per_guest_is_refused(): void
    {
        $entry = $this->entry();
        $addon = $this->occupyingAddon();
        $entry->addons()->attach($addon->id, $this->pivot());

        $this->expectException(\InvalidArgumentException::class);

        // El camino REAL de «Configurar» del panel (`updateExistingPivot` con pivote custom).
        $entry->configurableAddons()->updateExistingPivot($addon->id, ['quantity_mode' => ProductAddon::MODE_PER_GUEST]);
    }

    public function test_turning_occupancy_on_with_a_conflicting_attachment_is_refused(): void
    {
        // La OTRA dirección (#324): el complemento YA cuelga como por-invitado y ENTONCES alguien
        // enciende el interruptor — el guard del pivote no corre (no se toca el pivote) y sin esta
        // mitad la configuración prohibida quedaría en pie.
        $entry = $this->entry();
        $addon = TicketType::create([
            'name' => ['es' => 'Menú'], 'type' => TicketType::TYPE_ADDON,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 3,
        ]);
        $entry->addons()->attach($addon->id, $this->pivot(['quantity_mode' => ProductAddon::MODE_PER_GUEST]));

        $this->expectException(\InvalidArgumentException::class);

        $addon->fresh()->forceFill(['occupies_after_parent' => true, 'duration_min' => 60])->save();
    }

    // ─── El ALTA del panel conserva el dato (§4.9 / §6·10) ──────────────────────────────────────

    public function test_create_keeps_the_duration_of_an_occupying_addon(): void
    {
        // Hasta #410, este `unset` incondicional era EL bloqueo: el diseño dependía de un dato que
        // el panel borraba al guardar. El caso conduce el saneo real de `CreateCatalog`.
        $out = $this->normalizeByType(TicketType::TYPE_ADDON, [
            'occupies_after_parent' => true, 'duration_min' => 60,
            'available_after_open_min' => 30, 'zone_id' => 4,
        ]);

        $this->assertSame(60, $out['duration_min'] ?? null, 'la duración de un OCUPANTE sobrevive al guardado');
        $this->assertNull($out['zone_id'], 'la zona sigue nula: la hija hereda la de la franja que ocupa');
        $this->assertArrayNotHasKey('available_after_open_min', $out, 'las ventanas horarias siguen fuera');
    }

    public function test_create_still_strips_the_duration_of_a_neutral_addon(): void
    {
        // El CONTROL: la política histórica no cambia para el complemento neutro.
        $out = $this->normalizeByType(TicketType::TYPE_ADDON, [
            'occupies_after_parent' => false, 'duration_min' => 60,
        ]);

        $this->assertArrayNotHasKey('duration_min', $out);
        $this->assertFalse((bool) $out['occupies_after_parent']);
    }

    public function test_create_never_lets_a_non_addon_occupy(): void
    {
        $out = $this->normalizeByType(TicketType::TYPE_ENTRY, [
            'occupies_after_parent' => true, 'duration_min' => 60, 'zone_id' => $this->zone->id,
        ]);

        $this->assertFalse((bool) $out['occupies_after_parent'], 'regla 12: un payload manipulado no convierte una entrada en ocupante');
    }

    // ─── La EDICIÓN es simétrica (§4.9: `EditCatalog` no tenía `normalizeByType`) ───────────────

    public function test_edit_strips_the_switch_from_a_non_addon(): void
    {
        $out = $this->normalizeOnEdit($this->entry(), ['occupies_after_parent' => true, 'name' => ['es' => 'x']]);

        $this->assertArrayNotHasKey('occupies_after_parent', $out);
    }

    public function test_edit_clears_the_duration_when_the_switch_goes_off_without_sales(): void
    {
        $addon = $this->occupyingAddon();

        $out = $this->normalizeOnEdit($addon, ['occupies_after_parent' => false, 'duration_min' => 60]);

        $this->assertFalse($out['occupies_after_parent']);
        $this->assertNull($out['duration_min'], 'un complemento neutro no guarda duración (la política del alta)');
    }

    public function test_edit_reverts_turning_the_switch_off_once_sold(): void
    {
        // Con ventas OCUPANTES hechas, apagar el interruptor anularía la duración que las hijas ya
        // vendidas leen — para `occupancyMap`, duración nula es «hasta el cierre». Se revierte con
        // rastro, el espejo exacto del candado de la zona.
        [$entry, $addon] = $this->soldOccupant();

        $out = $this->normalizeOnEdit($addon->fresh(), ['occupies_after_parent' => false, 'duration_min' => null]);

        $this->assertTrue($out['occupies_after_parent'], 'el interruptor no se apaga con ventas hechas');
        $this->assertArrayNotHasKey('duration_min', $out, 'y la duración del registro se conserva');
        $this->assertDatabaseHas('audit_logs', ['action' => 'catalog.update_blocked']);
    }

    // ─── Fixture ────────────────────────────────────────────────────────────────────────────────

    private function entry(): TicketType
    {
        return TicketType::create([
            'name' => ['es' => 'Entrada'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1,
            'position' => (int) TicketType::max('position') + 1,
        ]);
    }

    private function occupyingAddon(): TicketType
    {
        return TicketType::create([
            'name' => ['es' => 'Hora extra'], 'type' => TicketType::TYPE_ADDON,
            'occupies_after_parent' => true, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1,
            'position' => (int) TicketType::max('position') + 1,
        ]);
    }

    /** @param  array<string,mixed>  $overrides */
    private function pivot(array $overrides = []): array
    {
        return $overrides + [
            'position' => 1, 'is_included' => false, 'included_quantity' => 1,
            'is_mandatory' => false, 'quantity_mode' => ProductAddon::MODE_FIXED,
            'allow_extra' => true, 'choice_group' => null, 'max_qty' => null, 'requires_addon_id' => null,
        ];
    }

    /** Una hora extra VENDIDA de verdad (por `OrderCreator`, con su hija ocupando). */
    private function soldOccupant(): array
    {
        $normalRateId = (int) RateType::create([
            'key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0,
        ])->id;

        $date = Carbon::today()->addDays(2)->toDateString();
        foreach (['10:00:00', '11:00:00'] as $start) {
            Slot::create([
                'zone_id' => $this->zone->id, 'date' => $date,
                'start_time' => $start, 'end_time' => Carbon::parse($start)->addHour()->format('H:i:s'),
                'capacity' => 50, 'online_capacity' => 50,
            ]);
        }

        $entry = $this->entry();
        $entry->prices()->create(['rate_type_id' => $normalRateId, 'amount_cents' => 1000]);
        $addon = $this->occupyingAddon();
        $addon->prices()->create(['rate_type_id' => $normalRateId, 'amount_cents' => 500]);
        $entry->addons()->attach($addon->id, $this->pivot());

        app(OrderCreator::class)->createPendingOrder(User::factory()->create(), [[
            'ticket_type_id' => $entry->id, 'date' => $date, 'time' => '10:00:00', 'qty' => 2,
            'addons' => [['ticket_type_id' => $addon->id, 'qty' => 1]],
        ]]);

        return [$entry, $addon];
    }

    /** Conduce el saneo real del ALTA (`CreateCatalog::normalizeByType`). */
    private function normalizeByType(string $type, array $data): array
    {
        $method = new ReflectionMethod(CreateCatalog::class, 'normalizeByType');
        $method->setAccessible(true);

        return $method->invoke(new CreateCatalog, $type, $data);
    }

    /** Conduce el saneo real de la EDICIÓN (`EditCatalog::normalizeAddonOccupancyOnEdit`). */
    private function normalizeOnEdit(TicketType $record, array $data): array
    {
        $method = new ReflectionMethod(EditCatalog::class, 'normalizeAddonOccupancyOnEdit');
        $method->setAccessible(true);

        return $method->invoke(new EditCatalog, $record, $data);
    }
}
