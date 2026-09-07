<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `#441` (`specs/firma-al-declarar-menor.md` §4.2, `[DECIDIDO owner, 2026-09-06]`): **declarar un
 * menor y aceptar su exención pasan a ser un solo gesto**, y —cuando el titular todavía no ha
 * verificado su correo— esa aceptación queda RETENIDA aquí hasta que lo haga.
 *
 * Son las cuatro hermanas exactas de las de `users` (`#179` y su S-1 de `#181`): qué texto se
 * aceptó, por qué canal llegó, y la IP y el navegador **del momento de aceptar** — no los de la
 * petición que verifique, que en pay-first puede ser la notificación S2S de Redsys.
 *
 * ⚠️ **Por qué en la FILA DEL MENOR y no en `users`**: allí es UNA sola ranura, y un titular puede
 * tener N menores pendientes a la vez. Una fila, una aceptación pendiente — un menor no puede tener
 * dos, así que no hace falta tabla y se conserva la simetría con el titular.
 *
 * ⚠️⚠️ **Y por qué RETENER en vez de firmar en el acto**: la primera versión del diseño proponía
 * relajar la regla del correo verificado (`#179`) para el menor a cargo, y la revisión adversarial
 * reprodujo el daño — un tercero declara veinte menores REALES con la cuenta de otra persona y los
 * firma; cuando la víctima reclama su cuenta se los queda con las firmas intactas, y no puede
 * deshacerlo porque con firma detrás `remove()` solo desvincula y `anonymize()` conserva (art.
 * 17.3.e). Con la aceptación retenida el gesto sigue siendo uno solo y **ninguna firma nace sobre un
 * buzón sin demostrar**: lo único que se aplaza es el efecto probatorio.
 *
 * `waiver_pending_document_id` apunta a una versión INMUTABLE (nunca se borra): FK `RESTRICT`, como
 * la del titular.
 *
 * ⚠️ Las cuatro son PII derivada del alta y viven en la fila del menor: se van con ella cuando
 * `remove()` la BORRA, y las limpia `Dependent::unlink()` cuando la conserva — una aceptación
 * pendiente de un menor retirado no puede convertirse en firma, y su IP no tiene por qué quedarse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dependents', function (Blueprint $table): void {
            $table->foreignId('waiver_pending_document_id')
                ->nullable()
                ->after('relationship')
                ->constrained('legal_document_versions')
                ->restrictOnDelete();
            $table->string('waiver_pending_channel', 8)->nullable()->after('waiver_pending_document_id');
            $table->string('waiver_pending_ip', 45)->nullable()->after('waiver_pending_channel');
            $table->string('waiver_pending_user_agent', 512)->nullable()->after('waiver_pending_ip');
        });
    }

    public function down(): void
    {
        Schema::table('dependents', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('waiver_pending_document_id');
            $table->dropColumn(['waiver_pending_channel', 'waiver_pending_ip', 'waiver_pending_user_agent']);
        });
    }
};
