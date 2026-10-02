<?php

use App\Domain\Identity\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * LAS CONTRASEÑAS DE LOS CLIENTES QUE YA EXISTEN, BORRADAS (A5d de `docs/specs/acceso-con-codigo.md` §4.6 y §4.12,
 * `DECISIONES #848`/`#869`). Desde la A5b ninguna superficie de cliente entra con contraseña —el código al correo o
 * Google—, así que su hash ya no abre nada y solo le serviría a quien robe la base (minimización, RGPD art. 5.1.c).
 *
 * Se pone `NULL` en las cuentas SIN rol del panel: `User::customers()`, la misma frontera que `canAccessPanel()`, leída
 * del modelo y no copiada —una segunda lista de roles envejecería en silencio (`User::PANEL_ROLES`)—. El personal
 * conserva la suya (`#847`); las anonimizadas, que llevaban un hash aleatorio, quedan como las deja ya `anonymize()`.
 * Sin tocar `updated_at`: no es un cambio de la cuenta. Idempotente: la segunda pasada no encuentra ninguna.
 *
 * ⚠️ En PRODUCCIÓN corre SOLO al desplegar la v2.0.0 (`#670`), medida antes y después (`ENTORNOS.md` §6). La prueba,
 * `CustomerPasswordsErasedMigrationTest`.
 */
return new class extends Migration
{
    public function up(): void
    {
        $borradas = User::query()->customers()->whereNotNull('password')->toBase()->update(['password' => null]);

        if ($borradas > 0) {
            Log::info('users.customer_passwords_erased', ['count' => $borradas]);
        }
    }

    public function down(): void
    {
        // Sin vuelta atrás, a propósito: un hash borrado no se recupera, y devolverlo sería devolver una puerta que ya no
        // existe (`#848`).
    }
};
