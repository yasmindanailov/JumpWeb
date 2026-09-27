<?php

namespace App\Console\Commands;

use App\Domain\Platform\Contracts\VisitFacts;
use App\Domain\Platform\Services\DisplayTime;
use App\Domain\Platform\Services\Surveys\SurveySeals;
use Illuminate\Console\Command;

/**
 * **¿VOLVIÓ QUIEN PUNTUÓ?** (`docs/specs/encuestas.md` §4.7, T5; `[DECIDIDO owner]` `DECISIONES #754`): cada noche,
 * cada respuesta aún sellada se mira contra las visitas de su cliente —acreditadas en la puerta o reservas cobradas,
 * `VisitFacts`— en los {@see SurveySeals::DAYS} días siguientes. Si volvió, se anota «volvió» y cuántos días tardó; si
 * el plazo entero pasó sin volver, «no volvió». En los dos casos el SELLO se borra en la misma escritura: desde ahí
 * la respuesta ya no es de nadie.
 *
 * Idempotente: solo toca sellos vivos, y una pasada perdida la recupera la siguiente (el plazo se cuenta desde el día
 * de la respuesta, no desde la pasada). Cuenta hasta AYER: el día de hoy aún no ha pasado.
 */
class ResolveSurveyReturns extends Command
{
    protected $signature = 'surveys:resolve-returns';

    protected $description = 'Anota si volvió quien contestó una encuesta y borra su sello (a la vuelta o a los 90 días).';

    public function handle(SurveySeals $seals, VisitFacts $visits): int
    {
        $out = $seals->resolve($visits, DisplayTime::today()->toDateString());

        $this->info(sprintf('Volvieron %d · no volvieron en el plazo %d · siguen selladas %d.', $out['returned'], $out['expired'], $out['pending']));

        return self::SUCCESS;
    }
}
