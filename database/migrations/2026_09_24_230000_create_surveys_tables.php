<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **Las encuestas** (`docs/specs/encuestas.md` §4.1, T1; `DECISIONES #740`): qué se pregunta, a quién y cuándo,
 * y lo que contestaron.
 *
 *  · `surveys`: una encuesta es DATO del panel. `key` es la clave que lleva el libro de eventos
 *    (`[a-z][a-z0-9_-]{0,47}`); `kind` es `internal` (en la puerta, con la persona delante) o `external` (por
 *    correo al día siguiente de la visita); `questions` es la lista ordenada de preguntas con el molde de los
 *    esquemas de campos (`{key, type, required, label{es,en,fr}, options?}`); vivo = `active` y dentro de la
 *    ventana. `name` e `intro` van en tres idiomas (json).
 *  · `survey_responses`: una fila por cliente y encuesta (la unicidad de abajo). `user_id` es el cliente —se
 *    pone a NULL al anonimizar, y las respuestas de texto libre se borran con él— y `visited_on` el día de la
 *    visita que la originó; `channel` repite la clase por la que llegó; `answered_by` es el operador (interna);
 *    `token` abre la página del correo (externa) hasta que `answered_at` lo cierra; `declined_at` es «no
 *    preguntar» en la puerta. `answers` guarda `{clave de pregunta: valor}`.
 *  · `users.surveys_opt_out`: «no quiero recibir más encuestas», con su interruptor y el enlace del correo.
 *
 * Ninguna respuesta entra en el libro de eventos (`RGPD-07`): allí van solo los hechos con la clave y el canal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surveys', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 48)->unique();
            $table->json('name');
            $table->json('intro')->nullable();
            $table->string('kind', 16);
            $table->boolean('active')->default(false);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('questions');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['kind', 'active']);
        });

        Schema::create('survey_responses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('visited_on')->nullable();
            $table->string('channel', 16);
            $table->foreignId('answered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token', 40)->nullable()->unique();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->json('answers')->nullable();
            $table->string('locale', 5)->nullable();
            $table->timestamps();
            $table->unique(['survey_id', 'user_id']);
            $table->index(['survey_id', 'answered_at']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('surveys_opt_out')->default(false)->after('analytics_opt_out');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('surveys_opt_out');
        });
        Schema::dropIfExists('survey_responses');
        Schema::dropIfExists('surveys');
    }
};
