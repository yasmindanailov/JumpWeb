<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Cumpleaños MIXTO · T4 — el PRODUCTO QUE LLEVA EL DESCUENTO (`docs/specs/cumple-mixto.md` §24.2).
 *
 * Espejo de `2026_08_29_000200_create_mixed_party_surcharge_product`: el −X € es una LÍNEA
 * (§20.1) y una línea necesita producto. **Un producto PROPIO y no el del suplemento** porque su
 * nombre lo imprimen ~6 superficies de lectura (ficha, hoja, correos, API, «Mis pedidos») desde
 * `ticketType->tr('name')` — con el portador del cargo todas dirían «Suplemento» sobre un
 * descuento; con producto propio el nombre sale bien en todas gratis y el parque puede renombrarlo
 * en su catálogo (white-label), igual que el del suplemento.
 *
 * ⚠️⚠️ Mismas trampas YA PAGADAS por la migración hermana, y valen aquí igual:
 *  - **JAMÁS el pack de destino** (§12.3): `PackAvailability` cuenta toda fila de producto `pack`
 *    de esa zona/día sin mirar si es hija — consumiría cupo y plazas en silencio (`AFORO-01`).
 *    Por eso es un COMPLEMENTO sin zona.
 *  - **Posición 0, fija** — fuera del espacio del seeder (`LandingContentSeeder` identifica sus
 *    productos POR posición desde 1) y fuera del `max(position)+1` del alta del catálogo.
 *  - `is_sellable=false` + `is_active=false`: fuera del embudo por las dos puertas.
 *
 * Idempotente: si el ajuste ya apunta a un producto vivo, no crea nada.
 */
return new class extends Migration
{
    private const SETTING_KEY = 'mixed_party.credit_product_id';

    private const POSITION = 0;

    public function up(): void
    {
        DB::transaction(function () {
            $existing = DB::table('settings')->where('key', self::SETTING_KEY)->value('value');
            if ($existing !== null && DB::table('ticket_types')->where('id', (int) $existing)->exists()) {
                return;
            }

            $id = DB::table('ticket_types')->insertGetId([
                'name' => json_encode([
                    'es' => 'Descuento fiesta mixta',
                    'en' => 'Mixed party discount',
                    'fr' => 'Remise fête mixte',
                ], JSON_UNESCAPED_UNICODE),
                'type' => 'addon',
                'zone_id' => null,
                'seats_per_unit' => 1,
                'tax_rate' => 0,
                'is_sellable' => false,
                'is_active' => false,
                'featured' => false,
                'position' => self::POSITION,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('settings')->updateOrInsert(
                ['key' => self::SETTING_KEY],
                ['value' => (string) $id, 'group' => 'mixed_party', 'updated_at' => now(), 'created_at' => now()],
            );
        });
    }

    /**
     * ⚠️ El producto NO se borra al revertir (mismo criterio que la hermana): si alguna reserva lo
     * usó, borrarlo destruiría una línea de venta. Se retira solo el puntero.
     */
    public function down(): void
    {
        DB::table('settings')->where('key', self::SETTING_KEY)->delete();
    }
};
