<?php

namespace Tests\Feature\Sales;

use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\PackAvailability;
use App\Domain\Platform\Models\Setting;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 7.9 (adelanto) — El cupo de packs se resuelve POR ZONA con fallback al ajuste global:
 * una zona con override manda; una zona sin override (null) usa el valor global de Configuración.
 */
class ZoneCupoOverrideTest extends TestCase
{
    use RefreshDatabase;

    public function test_zone_without_override_uses_global_setting(): void
    {
        Setting::updateOrCreate(['key' => PackAvailability::SETTING_MAX_GUESTS_PER_SLOT], ['value' => '40', 'group' => 'packs']);
        Setting::updateOrCreate(['key' => PackAvailability::SETTING_MAX_PER_SLOT], ['value' => '3', 'group' => 'packs']);
        Setting::updateOrCreate(['key' => PackAvailability::SETTING_PREP_BLOCKS_CUPO], ['value' => '1', 'group' => 'packs']);

        $zone = Zone::create(['slug' => 'cumpleanos', 'name' => ['es' => 'Cumpleaños']]); // sin override

        $pa = new PackAvailability;
        $this->assertSame(40, $pa->maxGuestsPerSlot($zone));
        $this->assertSame(3, $pa->maxPartiesPerSlot($zone));
        $this->assertTrue($pa->prepBlocksCupo($zone));
    }

    public function test_zone_override_wins_over_global(): void
    {
        Setting::updateOrCreate(['key' => PackAvailability::SETTING_MAX_GUESTS_PER_SLOT], ['value' => '40', 'group' => 'packs']);
        Setting::updateOrCreate(['key' => PackAvailability::SETTING_MAX_PER_SLOT], ['value' => '3', 'group' => 'packs']);
        Setting::updateOrCreate(['key' => PackAvailability::SETTING_PREP_BLOCKS_CUPO], ['value' => '1', 'group' => 'packs']);

        $zone = Zone::create([
            'slug' => 'eventos', 'name' => ['es' => 'Eventos'],
            'max_guests_per_slot' => 15, 'max_per_slot' => 1, 'prep_blocks_cupo' => false,
        ]);

        $pa = new PackAvailability;
        $this->assertSame(15, $pa->maxGuestsPerSlot($zone), 'la zona manda sobre el global');
        $this->assertSame(1, $pa->maxPartiesPerSlot($zone));
        $this->assertFalse($pa->prepBlocksCupo($zone), 'el override booleano false manda sobre el global true');
    }

    public function test_zero_override_means_no_limit_not_fallback(): void
    {
        Setting::updateOrCreate(['key' => PackAvailability::SETTING_MAX_GUESTS_PER_SLOT], ['value' => '40', 'group' => 'packs']);

        $zone = Zone::create(['slug' => 'eventos', 'name' => ['es' => 'Eventos'], 'max_guests_per_slot' => 0]);

        // 0 es un override explícito ("sin tope"), NO debe caer al global 40.
        $this->assertSame(0, (new PackAvailability)->maxGuestsPerSlot($zone));
    }

    public function test_null_zone_uses_global(): void
    {
        Setting::updateOrCreate(['key' => PackAvailability::SETTING_MAX_GUESTS_PER_SLOT], ['value' => '40', 'group' => 'packs']);

        $this->assertSame(40, (new PackAvailability)->maxGuestsPerSlot(null));
    }

    public function test_zone_override_limits_real_availability(): void
    {
        // Global SIN tope (0); la zona limita a 12 niños/franja. El cálculo real debe respetar
        // el override de zona, no solo los getters.
        Setting::updateOrCreate(['key' => PackAvailability::SETTING_MAX_GUESTS_PER_SLOT], ['value' => '0', 'group' => 'packs']);
        Setting::updateOrCreate(['key' => PackAvailability::SETTING_MAX_PER_SLOT], ['value' => '0', 'group' => 'packs']);
        Setting::updateOrCreate(['key' => PackAvailability::SETTING_PREP_BLOCKS_CUPO], ['value' => '0', 'group' => 'packs']);

        $zone = Zone::create(['slug' => 'eventos', 'name' => ['es' => 'Eventos'], 'max_guests_per_slot' => 12]);
        $slot = Slot::create([
            'zone_id' => $zone->id, 'date' => Carbon::tomorrow()->toDateString(),
            'start_time' => '17:00:00', 'end_time' => '18:00:00', 'capacity' => 200, 'online_capacity' => 200,
        ]);
        $pack = TicketType::create([
            'name' => ['es' => 'Evento'], 'zone_id' => $zone->id, 'type' => TicketType::TYPE_PACK,
            'duration_min' => 60, 'min_qty' => 8, 'max_qty' => 20,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);

        // Global sin tope → solo limitan la zona (12) y el max_qty del pack (20) → min = 12.
        // Si el override de zona se ignorara, saldría 20 (el max del pack).
        $this->assertSame(12, (new PackAvailability)->availableGuestsFor($slot, $pack));
    }
}
