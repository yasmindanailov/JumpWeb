<?php

namespace App\Console\Commands;

use App\Domain\Content\Enums\GoogleBusinessSyncOutcome;
use App\Domain\Content\Services\GoogleBusinessSync;
use Illuminate\Console\Command;

/**
 * **LA PASADA DIARIA DE LAS RESEÑAS** (T2·3, `specs/google-business-profile.md` §4.3·1;
 * `DECISIONES #524`, `#729`).
 *
 * Recorre la ficha de Google del parque y deja `google_business_reviews` como está la ficha. Corre
 * **una vez al día** desde el programador, y el botón del panel encolará **este mismo trabajo**: una
 * sola forma de sincronizar, que es lo que hace que el candado de §4.3·1 valga para las dos.
 *
 * ⚠️ **No imprime ni un texto ni un nombre de reseña** (`RGPD-02`): cuántas, no cuáles. Su salida
 * acaba en el log de un cron y en la captura que alguien pega en un chat.
 *
 * ⚠️ **Sale con 0 también cuando no llama.** «Sin configurar», «caducada» o «ya hay una pasada en
 * curso» no son fallos del comando: son desenlaces normales, y un cron que se pone rojo todas las
 * noches en una instalación sin conectar es un cron que nadie vuelve a mirar. Solo un «no» de Google
 * sale distinto de cero.
 */
class SyncGoogleBusinessReviews extends Command
{
    protected $signature = 'business-profile:sync';

    protected $description = 'Recorre la ficha de Google y actualiza las reseñas publicables. No imprime textos ni nombres.';

    public function handle(GoogleBusinessSync $sync): int
    {
        $resultado = $sync->run();

        $this->line('Estado de la conexión: <info>'.$resultado->status->value.'</info>');

        switch ($resultado->outcome) {
            case GoogleBusinessSyncOutcome::Idle:
                $this->line('No se ha llamado a Google: el estado de la conexión no lo permite.');

                return self::SUCCESS;

            case GoogleBusinessSyncOutcome::Busy:
                $this->line('Ya había una pasada en curso. No se ha hecho nada.');

                return self::SUCCESS;

            case GoogleBusinessSyncOutcome::Failed:
                $this->error('Google ha rechazado la llamada. Si el «no» es permanente, el estado de arriba ya lo dice.');

                return self::FAILURE;

            case GoogleBusinessSyncOutcome::Done:
                $this->line(sprintf(
                    'Vistas %d · guardadas %d · retiradas %d.',
                    $resultado->seen,
                    $resultado->kept,
                    $resultado->deleted,
                ));

                if (! $resultado->coherent) {
                    // No es un fallo, y por eso es un aviso y no un error: la pasada se guardó, pero
                    // **no borró nada** y el resumen se quedó como estaba (§4.3·3).
                    $this->warn('La pasada no se ha podido dar por buena: no se ha retirado nada ni se ha tocado la media.');
                }

                if ($resultado->ambiguous > 0) {
                    // §4.3·8: el formato de la traducción de Google no está documentado. Que esto
                    // suba es la señal de que hay una variante que todavía no se ha medido.
                    $this->warn(sprintf('%d reseña(s) con un texto que no se ha podido separar de su traducción.', $resultado->ambiguous));
                }

                return self::SUCCESS;
        }
    }
}
