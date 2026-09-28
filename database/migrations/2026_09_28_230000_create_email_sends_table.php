<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **Los correos que recibe el cliente** (`specs/correos-salientes.md` §4.1 y §4.6, `DECISIONES #794`, la C1): una fila por
 * correo AL CLIENTE que sale, con la COPIA de lo que salió para verlo tal cual.
 *
 * - `send_key` es el id de la notificación: el framework lo fija por destinatario y no cambia entre reintentos, así que un
 *   envío que falla y luego sale bien es UNA fila.
 * - Sin FK a `users`, como el libro de la analítica: la fila de `users` no se borra nunca (`RGPD-01`) y la poda va por edad.
 * - La copia (`html`, `subject`, `attachments`) vive 6 meses (`copy_purged_at` dice que se borró); la fila, 24 (`#794`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_sends', function (Blueprint $table) {
            $table->id();
            $table->uuid('send_key')->unique();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('recipient', 255)->nullable();
            $table->string('mail_key', 64)->index();
            $table->string('subject', 255)->nullable();
            $table->longText('html')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamp('copy_purged_at')->nullable();
            $table->unsignedTinyInteger('failures')->default(0);
            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_sends');
    }
};
