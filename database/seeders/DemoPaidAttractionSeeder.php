<?php

namespace Database\Seeders;

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Content\Models\Attraction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Ejemplo de ATRACCIÓN DE PAGO (#228), solo para DEMO/desarrollo (lo llama `DatabaseSeeder`, no el
 * `LandingContentSeeder` que usan los tests de composición del catálogo). La atracción «Tirolina
 * aérea» (zona Jump) se vende como un complemento (addon) enganchado a las entradas de Jump → en la
 * landing sale la primera de su zona, con precio + CTA «Comprar». Marca además «Foam Pit gigante»
 * como DESTACADA (resalte visual sin venta). Idempotente; placeholder editable desde el panel.
 *
 * Depende de que `LandingContentSeeder` ya haya sembrado zonas/entradas/atracciones.
 */
class DemoPaidAttractionSeeder extends Seeder
{
    public function run(): void
    {
        $jump = Zone::where('slug', 'jump')->first();
        if ($jump === null) {
            return;
        }

        $rates = RateType::pluck('id', 'key');
        if ($rates->isEmpty()) {
            return;
        }

        // Complemento «Tirolina aérea» (5 €), enganchado a las entradas de la zona Jump.
        $addon = TicketType::updateOrCreate(
            ['position' => 24],
            [
                'name' => ['es' => 'Tirolina aérea', 'en' => 'Aerial zipline', 'fr' => 'Tyrolienne aérienne'],
                'type' => TicketType::TYPE_ADDON,
                'tax_rate' => 21,
                'is_sellable' => true,
                'is_active' => true,
            ],
        );
        foreach ([RateType::KEY_NORMAL, RateType::KEY_SPECIAL] as $key) {
            if (isset($rates[$key])) {
                $addon->prices()->updateOrCreate(
                    ['rate_type_id' => $rates[$key]],
                    ['amount_cents' => 500, 'currency' => 'EUR'],
                );
            }
        }
        foreach (TicketType::ofType(TicketType::TYPE_ENTRY)->where('zone_id', $jump->id)->pluck('id') as $entryId) {
            DB::table('product_addons')->updateOrInsert(
                ['product_id' => $entryId, 'addon_id' => $addon->id],
                ['position' => 24],
            );
        }

        // «Tirolina aérea» (Jump, position 3) → vendible vía el complemento.
        Attraction::where('zone_id', $jump->id)->where('position', 3)
            ->update(['ticket_type_id' => $addon->id]);

        // «Foam Pit gigante» (Jump, position 2) → DESTACADA (resalte visual, sin venta).
        Attraction::where('zone_id', $jump->id)->where('position', 2)
            ->update(['is_special' => true]);
    }
}
