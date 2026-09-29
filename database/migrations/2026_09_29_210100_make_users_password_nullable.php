<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LA CUENTA SIN CONTRASEÑA (A1 de `docs/specs/acceso-con-codigo.md`, `DECISIONES #848`/`#849`): el cliente entra con un
 * código al correo o con Google, y el alta ya no la pide. `users.password` pasa a admitir `NULL`, que es «esta cuenta no
 * tiene contraseña»: el proveedor de Laravel no valida contra un `NULL` (`EloquentUserProvider::validateCredentials()`),
 * así que ninguna contraseña abre esa cuenta. El personal del panel conserva la suya (`#847`).
 *
 * ▶ La A5 BORRARÁ las de los clientes que ya existen (§4.6, con la receta de `ENTORNOS.md` §5); esta migración no toca
 * ningún valor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Revertir exige que ninguna cuenta se haya quedado sin contraseña. Se aborta con un mensaje claro en vez de un
        // error SQL crudo (el mismo criterio que `make_users_email_nullable`).
        if (DB::table('users')->whereNull('password')->exists()) {
            throw new RuntimeException(
                'No se puede revertir make_users_password_nullable: hay cuentas SIN contraseña (el acceso con código, #848). '
                .'Dales una o elimínalas antes de revertir.'
            );
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('password')->nullable(false)->change();
        });
    }
};
