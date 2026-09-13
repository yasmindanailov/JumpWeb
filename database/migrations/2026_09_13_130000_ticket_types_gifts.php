<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * LOS REGALOS DE UN PRODUCTO (`#589`, `[DECIDIDO owner]`): lo que el parque da SIN COBRAR —«cono de
 * chuches», «calcetines antideslizantes para todos», «un profesor gratis por cada 15 alumnos»—, aparte
 * de lo que el producto INCLUYE (`features`).
 *
 * ▶ Columna propia y no una marca dentro de `features`: se pintan distinto —cada uno en su etiqueta
 * amarilla, en la web y en el cajón— y un prefijo escrito en el texto sería una convención que el
 * panel no puede validar.
 * ▶ Misma forma que `features` (`{es: [...], en: [...], fr: [...]}`) y nullable: sin regalos no hay
 * nada que guardar. Idempotente, como las demás de esta banda.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('ticket_types', 'gifts')) {
            return;
        }

        Schema::table('ticket_types', function (Blueprint $table): void {
            $table->json('gifts')->nullable()->after('features');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('ticket_types', 'gifts')) {
            return;
        }

        Schema::table('ticket_types', function (Blueprint $table): void {
            $table->dropColumn('gifts');
        });
    }
};
