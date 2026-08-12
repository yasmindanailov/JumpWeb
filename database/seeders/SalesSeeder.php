<?php

namespace Database\Seeders;

use App\Models\OpeningHour;
use App\Models\RateType;
use App\Models\Room;
use App\Models\SlotTemplate;
use App\Models\SpecialDate;
use App\Models\Zone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

/**
 * Datos de venta (Fase 5.0): horario del parque, plantillas de franja por zona, días
 * especiales de ejemplo y generación de franjas para las próximas semanas. PLACEHOLDERS
 * editables desde el panel (Fase 7); horario/aforo reales [PENDIENTE].
 * Requiere zonas y tarifas ya sembradas (LandingContentSeeder).
 */
class SalesSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedOpeningHours();
        $this->seedRooms();
        $this->seedSlotTemplates();
        $this->seedSpecialDates();
        $this->generateUpcomingSlots();
    }

    /** Mesas PLACEHOLDER para packs (cumpleaños). Estructura §9.3; nº/capacidades reales [PENDIENTE]. */
    private function seedRooms(): void
    {
        foreach (range(1, 3) as $n) {
            Room::updateOrCreate(
                ['position' => $n],
                ['name' => ['es' => "Mesa {$n}", 'en' => "Table {$n}", 'fr' => "Table {$n}"], 'capacity' => 12, 'is_active' => true],
            );
        }
    }

    /** Horario del parque PLACEHOLDER: abierto todos los días 10:00–21:00 (§9.1). [PENDIENTE] reales. */
    private function seedOpeningHours(): void
    {
        for ($weekday = 0; $weekday <= 6; $weekday++) {
            OpeningHour::updateOrCreate(
                ['weekday' => $weekday],
                ['open_time' => '10:00:00', 'close_time' => '21:00:00', 'is_closed' => false],
            );
        }
    }

    /** Franjas cada hora 10:00–20:00 (hora de entrada), todos los días, por zona. */
    private function seedSlotTemplates(): void
    {
        $config = [
            'jump' => ['capacity' => 60, 'online' => 40],
            'kids' => ['capacity' => 40, 'online' => 25],
            // Zona "Cumpleaños": estas franjas solo aportan fecha/hora a los packs; su aforo va por
            // CUPO (PackAvailability, #82), así que online_capacity es nominal (no se vende por plaza).
            'cumpleanos' => ['capacity' => 200, 'online' => 200],
        ];

        foreach (Zone::pluck('id', 'slug') as $slug => $zoneId) {
            if (! isset($config[$slug])) {
                continue;
            }

            for ($weekday = 0; $weekday <= 6; $weekday++) {
                foreach (range(10, 20) as $hour) {
                    SlotTemplate::updateOrCreate(
                        [
                            'zone_id' => $zoneId,
                            'weekday' => $weekday,
                            'start_time' => sprintf('%02d:00:00', $hour),
                        ],
                        [
                            'duration_min' => 60, // rejilla de aforo horaria
                            'capacity' => $config[$slug]['capacity'],
                            'online_capacity' => $config[$slug]['online'],
                            'is_active' => true,
                        ],
                    );
                }
            }
        }
    }

    /** Un festivo (tarifa especial) y un día cerrado, de ejemplo. */
    private function seedSpecialDates(): void
    {
        $specialRateId = RateType::where('key', RateType::KEY_SPECIAL)->value('id');

        SpecialDate::updateOrCreate(['date' => '2026-08-15'], [
            'is_closed' => false,
            'rate_type_id' => $specialRateId,
            'note' => ['es' => 'Festivo (Asunción)', 'en' => 'Public holiday', 'fr' => 'Jour férié'],
        ]);

        SpecialDate::updateOrCreate(['date' => '2026-09-01'], [
            'is_closed' => true,
            'note' => ['es' => 'Cerrado por mantenimiento', 'en' => 'Closed for maintenance', 'fr' => 'Fermé pour maintenance'],
        ]);
    }

    /** Genera franjas reales para los próximos 14 días con el comando idempotente. */
    private function generateUpcomingSlots(): void
    {
        Artisan::call('slots:generate', [
            'from' => Carbon::today()->toDateString(),
            'to' => Carbon::today()->addDays(14)->toDateString(),
        ]);
    }
}
