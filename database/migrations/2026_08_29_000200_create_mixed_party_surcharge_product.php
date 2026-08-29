<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Cumpleaños MIXTO · el PRODUCTO QUE LLEVA EL SUPLEMENTO (`docs/specs/cumple-mixto.md` §12).
 *
 * El suplemento de una fiesta mixta tiene que ser una **LÍNEA** de la reserva y no un ajuste suelto
 * (§8.3, medido: un `extra_due` sin línea no cobra nada, mueve dinero ya pagado). Una línea es un
 * `order_items`, y un `order_items` necesita un producto.
 *
 * ⚠️⚠️ **Y ese producto NO puede ser el pack de destino** («Cumpleaños Jump»). Medido el 2026-08-29:
 * `PackAvailability` cuenta TODA fila de `order_items` cuyo producto sea de tipo `pack` en esa
 * zona y día **sin mirar si es una línea hija**. Una línea de suplemento con el pack de destino
 * consumiría una fiesta entera del cupo y sus plazas, en silencio y en la peor pieza del sistema
 * (`AFORO-01`). De ahí que el portador sea un COMPLEMENTO: los complementos no tienen zona ni
 * plazas y ninguna consulta de aforo los ve.
 *
 * **Lo crea el sistema y no el operador** `[DECIDIDO owner, 2026-08-29]`: es un producto que ninguna
 * instalación querría configurar a mano y que, si falta, deja de cobrar en silencio. Naciendo con la
 * instalación no hay nada que olvidar. Sigue siendo data-driven: el nombre se edita en el catálogo
 * como el de cualquier producto, y su PRECIO es irrelevante — el importe de cada línea se deriva
 * siempre de los dos packs para ese día (`GuestAgeMixReader`).
 *
 * ⚠️ **`is_sellable = false` es lo que lo mantiene fuera del embudo**: el catálogo público, el alta
 * manual y `OrderCreator` filtran por ahí (`TicketType::sellable()`), y los complementos ofrecidos
 * de un pack salen de `addons()`, que exige vendible Y activo. Este no está en el pivote de ningún
 * pack, así que tampoco se ofrece por esa vía.
 *
 * Idempotente: si el ajuste ya apunta a un producto vivo, no crea nada.
 */
return new class extends Migration
{
    private const SETTING_KEY = 'mixed_party.surcharge_product_id';

    /**
     * Posición del portador: **0**, y las dos propiedades importan.
     *
     * Fuera de la clave del seeder —`LandingContentSeeder` numera desde 1— y, sobre todo, **fuera
     * del `max(position) + 1`** con el que `CreateCatalog` coloca cada producto nuevo al final: una
     * posición alta (se probó con 900) empujaba a 901 a todo lo que el operador crease después.
     * Un producto de sistema no puede mover la numeración de los productos del cliente.
     */
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
                    'es' => 'Suplemento fiesta mixta',
                    'en' => 'Mixed party supplement',
                    'fr' => 'Supplément fête mixte',
                ], JSON_UNESCAPED_UNICODE),
                'type' => 'addon',
                'zone_id' => null,
                'seats_per_unit' => 1,
                'tax_rate' => 0,
                // Fuera del embudo por las dos puertas: no vendible y no visible.
                'is_sellable' => false,
                'is_active' => false,
                'featured' => false,
                // ⚠️⚠️ **Una posición FIJA y fuera del espacio del seeder, no `max(position) + 1`.**
                // (Y fuera también del `max` que usa el alta del catálogo — ver la constante.)
                // `LandingContentSeeder` —que es el fixture de la suite y la semilla de demo—
                // identifica sus productos con `updateOrCreate(['position' => N])`, así que la
                // posición ES su clave. Sobre una base VACÍA `max(position)` es null y esto daba
                // **1**: el seeder encontraba este producto en «su» posición 1 y lo SOBRESCRIBÍA
                // con una entrada, dejando la instalación sin portador y sin decir nada (medido el
                // 2026-08-29: la suite lo cazó como «5 complementos vendibles» — el quinto era
                // «Jump · 1 hora» con `type` de complemento).
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
     * ⚠️ El producto NO se borra al revertir. Si alguna reserva lo usó, borrarlo destruiría una
     * línea de venta (`order_items.ticket_type_id`), y una migración hacia atrás no es sitio para
     * decidir eso. Se retira solo el puntero: sin él, la función queda apagada y el producto
     * huérfano es visible en el catálogo para que un humano decida.
     */
    public function down(): void
    {
        DB::table('settings')->where('key', self::SETTING_KEY)->delete();
    }
};
