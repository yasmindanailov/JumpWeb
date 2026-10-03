<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **El 12 ampliado** (la C1a de `specs/correos-rediseno.md` §4.4, `#920`): «El cumple se acerca» sale también a quien
 * declaró a un menor en su cuenta y marcó «novedades». `birthday_mail_for` es su marca: el cumpleaños para el que ya salió
 * —como `birthday_reminders.sent_for`—, escrita ANTES de encolar, para que una pasada repetida no escriba dos veces.
 *
 * ⚠️ Vive en la fila del menor y se va con ella: no es un dato nuevo de nadie (la fecha sale de `born_on`), y por eso ni el
 * censo de `users` ni la supresión cambian.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dependents', function (Blueprint $table): void {
            $table->date('birthday_mail_for')->nullable()->after('born_on');
        });
    }

    public function down(): void
    {
        Schema::table('dependents', function (Blueprint $table): void {
            $table->dropColumn('birthday_mail_for');
        });
    }
};
