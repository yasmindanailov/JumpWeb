<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * **Las encuestas, ANÓNIMAS** (`docs/specs/encuestas.md` §4.7, T5; `[DECIDIDO owner]` `DECISIONES #754`): la fila que
 * decía quién contestó qué se parte en dos que no comparten clave.
 *
 *  · `survey_participations` — **a quién se preguntó**, sin respuestas: una por cliente y encuesta (el árbitro de
 *    «una vez nada más», `#740`), el día de la visita que la originó, quién preguntó en la puerta, cuándo se mandó el
 *    correo, el idioma de la página y el HASH del token (el token en claro solo viaja en el correo). No guarda el
 *    desenlace ni la hora de la puerta.
 *  · `survey_responses` (rehecha) — **qué se contestó**, sin persona: clave UUID v4 ALEATORIA (ni autoincremental ni
 *    v7: el orden uniría las dos tablas), el día del parque y la FRANJA (no la hora: el registro de actividad apunta
 *    cada escaneo con su hora y su empleado), quién preguntó, dos datos gruesos (primera visita, tipo de visita), «no
 *    preguntar» como fila, y el SELLO: el cliente cifrado, que solo sirve para saber si volvió y se borra al saberlo o a
 *    los 90 días. Sin `timestamps()` y sin el idioma (el del correo es el del cliente, y `users.locale` lo cruzaría).
 *  · `survey_spent_tokens` — el hash de un token YA contestado, sin fecha ni clave foránea: cierra la página sin
 *    tocar la participación, y unir las dos exige el token en claro, que solo tiene el destinatario.
 *
 * Las filas que ya existían (nada está desplegado, `#670`: son las de la base local) se parten igual, hacia delante,
 * y sin sello: no se sella hacia atrás.
 *
 * ⚠️ El HMAC y la franja van ESCRITOS aquí y no llamados: una migración no puede depender de una clase que mañana
 * cambie. Son los mismos que `SurveyResponses::tokenHash()` y `SurveyResponse::bandAt()`, congelados.
 * ⚠️ Las claves foráneas de la tabla nueva llevan nombre PROPIO: la vieja se renombra antes de copiar, y en MySQL el
 * nombre de una restricción es único en todo el esquema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_participations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 16);
            $table->foreignId('asked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('asked_on');
            $table->timestamp('sent_at')->nullable();
            $table->char('token_hash', 64)->nullable()->unique();
            $table->string('locale', 5)->nullable();
            $table->unique(['survey_id', 'user_id']);
            $table->index(['user_id', 'sent_at']);
            $table->index('asked_on');
        });

        Schema::create('survey_spent_tokens', function (Blueprint $table): void {
            $table->char('hash', 64)->primary();
        });

        Schema::rename('survey_responses', 'survey_responses_legacy');

        Schema::create('survey_responses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('survey_id')->constrained('surveys', 'id', 'survey_answers_survey_fk')->cascadeOnDelete();
            $table->string('channel', 16);
            $table->date('answered_on');
            $table->string('band', 16);
            $table->foreignId('asked_by')->nullable()->constrained('users', 'id', 'survey_answers_asked_by_fk')->nullOnDelete();
            $table->boolean('first_visit')->nullable();
            $table->string('visit_kind', 16)->nullable();
            $table->boolean('declined')->default(false);
            $table->json('answers')->nullable();
            $table->text('seal')->nullable();
            $table->boolean('returned')->nullable();
            $table->unsignedSmallInteger('returned_after_days')->nullable();
            $table->index(['survey_id', 'answered_on'], 'survey_answers_survey_day_index');
            $table->index('answered_on', 'survey_answers_day_index');
        });

        $this->split();

        Schema::drop('survey_responses_legacy');
    }

    public function down(): void
    {
        // ⚠️ Volver atrás recupera la FORMA, no la unión: lo que se separó no se puede volver a atar (esa es la promesa).
        Schema::drop('survey_responses');
        Schema::drop('survey_spent_tokens');
        Schema::drop('survey_participations');

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
    }

    /** Cada fila vieja da una participación y, si se cerró (contestada o «no preguntar»), una respuesta sin persona. */
    private function split(): void
    {
        $timezone = $this->parkTimezone();
        $key = (string) config('app.key');

        DB::table('survey_responses_legacy')->orderBy('id')->get()->each(function (object $row) use ($timezone, $key): void {
            $closedAt = $row->answered_at ?? $row->declined_at;
            $closed = $closedAt === null ? null : Carbon::parse((string) $closedAt, 'UTC')->setTimezone($timezone);
            $firstMoment = $row->sent_at ?? $closedAt ?? $row->created_at;
            $askedOn = $row->visited_on
                ?? ($firstMoment === null ? null : Carbon::parse((string) $firstMoment, 'UTC')->setTimezone($timezone)->toDateString());
            $external = $row->channel === 'external';
            $token = is_string($row->token) && $row->token !== '' ? $row->token : null;

            DB::table('survey_participations')->insert([
                'survey_id' => $row->survey_id,
                'user_id' => $row->user_id,
                'channel' => $row->channel,
                'asked_by' => $external ? null : $row->answered_by,
                'asked_on' => $askedOn ?? Carbon::now($timezone)->toDateString(),
                'sent_at' => $row->sent_at,
                'token_hash' => $token === null ? null : hash_hmac('sha256', 'lookup:'.$token, $key),
                'locale' => $external ? $row->locale : null,
            ]);

            if ($external && $token !== null && $row->answered_at !== null) {
                DB::table('survey_spent_tokens')->insert(['hash' => hash_hmac('sha256', 'spent:'.$token, $key)]);
            }

            if ($closed === null) {
                return;
            }

            $hour = (int) $closed->format('G');
            DB::table('survey_responses')->insert([
                'id' => (string) Str::uuid(),
                'survey_id' => $row->survey_id,
                'channel' => $row->channel,
                'answered_on' => $closed->toDateString(),
                'band' => $hour < 13 ? 'morning' : ($hour < 16 ? 'midday' : 'afternoon'),
                'asked_by' => $external ? null : $row->answered_by,
                'declined' => $row->answered_at === null,
                'answers' => $row->answered_at === null ? null : $row->answers,
            ]);
        });
    }

    /** La zona del parque, leída como `DisplayTime::timezone()` (el ajuste, o Madrid) pero sin depender de la clase. */
    private function parkTimezone(): string
    {
        $tz = trim((string) DB::table('settings')->where('key', 'display_timezone')->value('value'));

        return in_array($tz, DateTimeZone::listIdentifiers(), true) ? $tz : 'Europe/Madrid';
    }
};
