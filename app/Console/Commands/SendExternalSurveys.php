<?php

namespace App\Console\Commands;

use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Survey;
use App\Domain\Platform\Services\DisplayTime;
use App\Domain\Platform\Services\Surveys\SurveyResponses;
use App\Domain\Platform\Services\Surveys\SurveySettings;
use App\Notifications\SurveyInvitation;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as Query;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * **LA ENCUESTA DEL DÍA SIGUIENTE, por correo** (`docs/specs/encuestas.md` §4.3, T3; `DECISIONES #740`).
 *
 * A quien acreditó su visita AYER (día del parque) —el escaneo del carné la acredita, `#741`— se le manda UN correo
 * con la encuesta externa viva, si la hay. Es un correo de SERVICIO (`[DECIDIDO owner]` §7·4): a todo el que
 * visitó, sin una línea comercial dentro, con la baja de un toque al pie.
 *
 * ## A quién NO
 *  · sin correo verificado, o anonimizado (art. 17): no hay a quién escribir;
 *  · con `surveys_opt_out` («no quiero recibir más encuestas»);
 *  · con fila para ESTA encuesta (ya la contestó en la puerta, la declinó o ya se le mandó);
 *  · con un correo de encuesta en los últimos `surveys.cooldown_days` días (30 por defecto): una persona que
 *    viene cada semana no recibe una encuesta cada semana.
 *
 * ## Por qué corre CADA HORA y decide él, desde las 10:00 del parque
 * Las dos razones de `reservations:eve-notice`: la zona del parque es un AJUSTE que se resuelve en la EJECUCIÓN,
 * y una hora de cron caído no se lleva la encuesta por delante — la fila de `survey_responses` (una por cliente y
 * encuesta) es la marca, así que la siguiente pasada recupera y nunca duplica. Idempotente por construcción.
 *
 * ⚠️ **La fila nace ANTES de encolar** (`SurveyResponses::send()`): si el envío revienta, el cliente se queda sin
 * correo pero no con seis; queda el rastro en el log. El molde es `analytics:notify-accounts`.
 */
class SendExternalSurveys extends Command
{
    /** La hora del PARQUE a partir de la cual se manda (spec §4.3). */
    public const FROM_HOUR = 10;

    private const CHUNK = 200;

    protected $signature = 'surveys:send-external
        {--force : Manda aunque no sean todavía las 10:00 del parque (staging y pruebas a mano).}
        {--dry-run : Enseña a cuántos mandaría y no escribe ni encola nada.}';

    protected $description = 'Manda por correo la encuesta externa viva a quien acreditó su visita ayer.';

    public function handle(SurveyResponses $responses): int
    {
        $now = DisplayTime::now();

        if ($now->hour < self::FROM_HOUR && ! $this->option('force')) {
            return self::SUCCESS;
        }

        $survey = Survey::runningOfKind(Survey::KIND_EXTERNAL);
        if ($survey === null) {
            $this->info('No hay ninguna encuesta externa viva: nada que mandar.');

            return self::SUCCESS;
        }

        $yesterday = $now->copy()->subDay()->toDateString();
        $dryRun = (bool) $this->option('dry-run');
        $sent = 0;
        $failed = 0;

        $this->eligible($survey, $yesterday)->chunkById(self::CHUNK, function (Collection $users) use ($responses, $survey, $yesterday, $dryRun, &$sent, &$failed): void {
            /** @var User $user */
            foreach ($users as $user) {
                if ($dryRun) {
                    $sent++;

                    continue;
                }

                $response = $responses->send($survey, (int) $user->getKey(), $yesterday, $user->preferredLocale());
                if ($response === null) {
                    continue;
                }

                try {
                    $user->notify(new SurveyInvitation($survey, $response));
                    $sent++;
                } catch (Throwable $e) {
                    // La fila se queda a propósito: reintentar mandaría el correo dos veces si el fallo fue
                    // después de encolar. Queda el rastro para mirarlo.
                    $failed++;
                    Log::warning('surveys.invitation_not_queued', [
                        'user_id' => $user->getKey(),
                        'survey' => $survey->key,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        $this->info(sprintf('%s %d encuestas «%s» por la visita del %s%s.',
            $dryRun ? 'Mandaría' : 'Mandadas',
            $sent,
            $survey->key,
            $yesterday,
            $failed > 0 ? sprintf(' (%d sin encolar, ver el log)', $failed) : '',
        ));

        return self::SUCCESS;
    }

    /**
     * Quien visitó AYER y puede recibir la encuesta. Todo en la consulta: el volumen de un día son decenas, pero
     * la pasada es horaria y no debe leer la tabla de clientes entera.
     *
     * @return Builder<User>
     */
    private function eligible(Survey $survey, string $yesterday): Builder
    {
        $cooldownSince = Carbon::now()->subDays(SurveySettings::cooldownDays());

        return User::query()
            ->whereNotNull('email_verified_at')
            ->where('email', 'not like', '%@'.User::ANONYMIZED_EMAIL_DOMAIN)
            ->where('surveys_opt_out', false)
            ->whereExists(static fn (Query $q) => $q->selectRaw('1')->from('customer_visits')
                ->whereColumn('customer_visits.user_id', 'users.id')
                ->where('customer_visits.visited_on', $yesterday))
            ->whereNotExists(static fn (Query $q) => $q->selectRaw('1')->from('survey_responses')
                ->whereColumn('survey_responses.user_id', 'users.id')
                ->where('survey_responses.survey_id', $survey->getKey()))
            ->whereNotExists(static fn (Query $q) => $q->selectRaw('1')->from('survey_responses')
                ->whereColumn('survey_responses.user_id', 'users.id')
                ->where('survey_responses.channel', 'external')
                ->where('survey_responses.sent_at', '>=', $cooldownSince))
            ->orderBy('id');
    }
}
