<?php

namespace Tests\Feature\Sales;

use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Platform\Models\Setting;
use Database\Seeders\LandingContentSeeder;
use Database\Seeders\SalesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Fase 5 (Capa 2a) — Cimientos de datos de los packs (cumpleaños): columnas de pack en
 * `ticket_types`, señal configurable (#83), config de cupo (#82), zona "Cumpleaños" oculta
 * y pack de ejemplo. Ver docs/PLAN-COMPRA-PRODUCTOS.md (§3,§9.3) y DECISIONES (#82/#83).
 */
class PackFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_types_has_pack_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('ticket_types', [
            'min_qty', 'max_qty', 'deposit_type', 'deposit_value',
        ]));
    }

    public function test_seeded_pack_exists_with_limits_prep_and_per_child_prices(): void
    {
        $this->seed(LandingContentSeeder::class);

        $pack = TicketType::ofType(TicketType::TYPE_PACK)->with('prices')->first();

        $this->assertNotNull($pack, 'Debe sembrarse al menos un pack de ejemplo.');
        $this->assertTrue($pack->isPack());
        $this->assertTrue($pack->is_sellable);
        $this->assertSame(8, $pack->min_qty);  // mín. 8 invitados para reservar (#7).
        $this->assertSame(20, $pack->max_qty);
        $this->assertSame(120, $pack->duration_min);
        $this->assertSame(60, $pack->prep_before_min);
        $this->assertSame(30, $pack->prep_after_min);
        $this->assertSame('por niño', $pack->tr('period_label', 'es'));

        // Señal fija de 30€ para reservar (#7).
        $this->assertSame(TicketType::DEPOSIT_FIXED, $pack->deposit_type);
        $this->assertSame(3000, $pack->deposit_value);

        // Precio POR NIÑO en la matriz de tarifas (normal y especial).
        $this->assertCount(2, $pack->prices);
        $this->assertGreaterThan(0, $pack->prices->min('amount_cents'));
    }

    public function test_pack_lives_in_an_operational_but_landing_hidden_zone(): void
    {
        $this->seed(LandingContentSeeder::class);

        $zone = Zone::where('slug', 'cumpleanos')->first();
        $this->assertNotNull($zone);
        // Modelo desacoplado (#82 → show_in_landing): la zona de cumpleaños SÍ opera (vende
        // packs) pero NO se muestra como tarjeta de zona en la landing.
        $this->assertTrue($zone->is_active, 'La zona Cumpleaños opera (vende packs).');
        $this->assertFalse($zone->show_in_landing, 'La zona Cumpleaños no se muestra en la landing.');

        // La landing/precios listan solo zonas con show_in_landing → la de cumpleaños queda fuera.
        $this->assertTrue(
            Zone::where('show_in_landing', true)->where('slug', 'cumpleanos')->doesntExist()
        );

        // El pack apunta a esa zona.
        $pack = TicketType::ofType(TicketType::TYPE_PACK)->first();
        $this->assertSame($zone->id, $pack->zone_id);
    }

    public function test_entries_flow_is_kept_separate_from_packs(): void
    {
        $this->seed(LandingContentSeeder::class);

        // 8 entradas + 2 packs (Jump/Kids, #1) + 4 complementos = 14 vendibles; el flujo de
        // entradas (type=entry) ve solo las 8.
        $this->assertSame(14, TicketType::sellable()->count());
        $this->assertSame(8, TicketType::sellable()->ofType(TicketType::TYPE_ENTRY)->count());
        $this->assertSame(2, TicketType::sellable()->ofType(TicketType::TYPE_PACK)->count());
        $this->assertSame(4, TicketType::sellable()->ofType(TicketType::TYPE_ADDON)->count());
    }

    public function test_pack_capacity_settings_are_seeded(): void
    {
        $this->seed(LandingContentSeeder::class);

        $this->assertSame('5', Setting::value('packs.max_per_slot'));
        $this->assertSame('60', Setting::value('packs.max_guests_per_slot'));
    }

    public function test_birthday_slots_are_generated(): void
    {
        $this->seed(LandingContentSeeder::class);
        $this->seed(SalesSeeder::class);

        $zone = Zone::where('slug', 'cumpleanos')->first();

        $this->assertGreaterThan(
            0,
            Slot::where('zone_id', $zone->id)->count(),
            'Deben generarse franjas para la zona Cumpleaños (aportan fecha/hora a los packs).'
        );
    }

    public function test_deposit_cents_computes_none_percent_and_fixed(): void
    {
        $total = 10000; // 100,00 €

        $none = new TicketType(['deposit_type' => TicketType::DEPOSIT_NONE, 'deposit_value' => 0]);
        $this->assertSame($total, $none->depositCents($total)); // pago total

        $percent = new TicketType(['deposit_type' => TicketType::DEPOSIT_PERCENT, 'deposit_value' => 30]);
        $this->assertSame(3000, $percent->depositCents($total)); // 30 %

        $fixed = new TicketType(['deposit_type' => TicketType::DEPOSIT_FIXED, 'deposit_value' => 5000]);
        $this->assertSame(5000, $fixed->depositCents($total)); // 50,00 € fijos

        // La señal nunca supera el total.
        $tooBig = new TicketType(['deposit_type' => TicketType::DEPOSIT_FIXED, 'deposit_value' => 20000]);
        $this->assertSame($total, $tooBig->depositCents($total));

        // Redondeo del porcentaje.
        $rounded = new TicketType(['deposit_type' => TicketType::DEPOSIT_PERCENT, 'deposit_value' => 21]);
        $this->assertSame(210, $rounded->depositCents(999)); // round(209.79) = 210
    }

    public function test_seeded_pack_has_configurable_event_fields(): void
    {
        $this->seed(LandingContentSeeder::class);

        $pack = TicketType::ofType(TicketType::TYPE_PACK)->first();

        // Esquema editable en el panel (#86): homenajeado (obligatorio), edad y notas se piden al
        // RESERVAR; nº de adultos y observaciones en el POST-FORM (#217, stage=postform).
        $this->assertSame(['celebrant', 'age', 'notes'], array_column($pack->eventFields(TicketType::EVENT_STAGE_BOOKING), 'key'));
        $this->assertSame(['adults_approx', 'observations'], array_column($pack->eventFields(TicketType::EVENT_STAGE_POSTFORM), 'key'));
        // La COMPRA solo exige los obligatorios de la fase de reserva.
        $this->assertSame(['celebrant'], $pack->missingRequiredEventFields([], TicketType::EVENT_STAGE_BOOKING));

        // Saneo (fase reserva): recorta, descarta vacíos/claves ajenas y deja los números en dígitos.
        $this->assertSame(
            ['celebrant' => 'Lucía', 'age' => '7'],
            $pack->sanitizeEventData(['celebrant' => '  Lucía ', 'age' => '7 años', 'notes' => '   ', 'x' => 'y'], TicketType::EVENT_STAGE_BOOKING),
        );
    }

    public function test_event_field_stage_filters_booking_vs_postform(): void
    {
        // Un campo postform OBLIGATORIO no debe bloquear la compra (solo el post-form lo exige);
        // y cada fase sanea/lee SOLO sus campos. Sin `stage` → comportamiento histórico (booking).
        $pack = TicketType::create([
            'name' => ['es' => 'Pack'], 'type' => TicketType::TYPE_PACK,
            'duration_min' => 120, 'min_qty' => 2, 'max_qty' => 20,
            'deposit_type' => TicketType::DEPOSIT_NONE, 'deposit_value' => 0,
            'seats_per_unit' => 1, 'tax_rate' => 21, 'is_sellable' => true, 'is_active' => true, 'position' => 9,
            'event_fields' => [
                ['key' => 'celebrant', 'type' => 'text', 'required' => true, 'stage' => 'booking', 'label' => ['es' => 'Homenajeado']],
                ['key' => 'adults', 'type' => 'number', 'required' => true, 'stage' => 'postform', 'label' => ['es' => 'Adultos']],
                ['key' => 'legacy', 'type' => 'text', 'required' => false, 'label' => ['es' => 'Sin fase']],  // sin stage → booking
            ],
        ]);

        // La compra (booking) ve celebrant + legacy, NO adults.
        $this->assertSame(['celebrant', 'legacy'], array_column($pack->eventFields(TicketType::EVENT_STAGE_BOOKING), 'key'));
        $this->assertSame(['adults'], array_column($pack->eventFields(TicketType::EVENT_STAGE_POSTFORM), 'key'));

        // Rellenando solo celebrant, la compra NO se bloquea por el `adults` obligatorio postform.
        $this->assertSame([], $pack->missingRequiredEventFields(['celebrant' => 'Ana'], TicketType::EVENT_STAGE_BOOKING));
        // El post-form sí lo exige.
        $this->assertSame(['adults'], $pack->missingRequiredEventFields([], TicketType::EVENT_STAGE_POSTFORM));

        // El saneo por fase no mezcla: booking ignora adults; postform ignora celebrant.
        $this->assertSame(['celebrant' => 'Ana'], $pack->sanitizeEventData(['celebrant' => 'Ana', 'adults' => '4'], TicketType::EVENT_STAGE_BOOKING));
        $this->assertSame(['adults' => '4'], $pack->sanitizeEventData(['celebrant' => 'Ana', 'adults' => '4'], TicketType::EVENT_STAGE_POSTFORM));
    }

    public function test_seeded_pack_has_configurable_guest_fields(): void
    {
        $this->seed(LandingContentSeeder::class);

        // El seed cablea el esquema por-niño por defecto (#217) en los packs REALES; sin esta red,
        // borrar la línea del seeder dejaría a los packs sin columnas y la suite seguiría verde.
        foreach (TicketType::ofType(TicketType::TYPE_PACK)->get() as $pack) {
            $this->assertSame(
                ['name', 'allergy', 'notes', 'special_menu'],
                array_column($pack->guestFields(), 'key'),
            );
        }
    }
}
