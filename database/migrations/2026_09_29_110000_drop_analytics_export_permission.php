<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * **Fuera el permiso `analytics.export`** (TP·3b de `specs/analitica-para-decidir.md` §4.14, `#793`): «Exportar segmento» (una
 * lista de personas) se retiró —el público es anónimo, nada del cuadro sale con nombres—, y su permiso se queda huérfano. Se
 * borra su fila; el pivote `permission_role` cae en cascada (como `messages.view` en `drop_contact_messages_table`).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->where('name', 'analytics.export')->delete();
    }

    /** La vuelta deja el permiso como lo sembraba el seeder, sin rol (el admin lo tiene por `Gate::before`). */
    public function down(): void
    {
        if (DB::table('permissions')->where('name', 'analytics.export')->doesntExist()) {
            DB::table('permissions')->insert([
                'name' => 'analytics.export', 'label' => 'Exportar segmentos de clientes (con opt-in)',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
};
