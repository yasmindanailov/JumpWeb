<?php

namespace App\Console\Commands;

use App\Domain\Content\Services\GoogleSocialProof;
use App\Domain\Content\Services\SocialProofRefresh;
use Illuminate\Console\Command;

/**
 * **Refresca las reseñas de Google en la caché corta** (`DECISIONES #491`,
 * `specs/google-reviews.md` §4.2).
 *
 * ❗❗❗ **Es el ÚNICO sitio del producto que habla con Google.** La portada lee de la caché y no
 * llama nunca: `PERF-02` mide el presupuesto de la home, y una llamada HTTP síncrona a un tercero en
 * el camino del render es peor que las ~1.900 consultas que aquella invariante existe para evitar.
 *
 * ❗❗ **Cada hora** (`[DECIDIDO owner, 2026-09-10]`), y el motivo no es el coste: a este volumen da
 * igual —~720 llamadas al mes, gratis—. Es que **la caché puede evictarse antes de su TTL**
 * (`allkeys-lru`, `#137`), y cuanto más corta sea la cadencia, más corto es el rato en que la
 * sección cae al respaldo propio.
 *
 * ⚠️⚠️ **NINGUNA salida vacía es un error**: sin configurar, con la cuota agotada, con Google caído
 * o por debajo del umbral de reseñas, la sección enseña las opiniones propias — que es su conducta
 * declarada, no una degradación. El comando lo DICE y termina en 0; sin eso, un cron que informa
 * de fallo cada hora acaba silenciado y entonces sí se pierden los fallos de verdad.
 *
 * ⚠️ **Bloqueo heredado que conviene no descubrir tarde** (`#115`): el scheduler **no corre en
 * staging** —crontab instalado, sin demonio—, así que allí hay que dispararlo a mano.
 */
class RefreshSocialProof extends Command
{
    protected $signature = 'social-proof:refresh';

    protected $description = 'Trae las reseñas de Google a la caché corta (la landing nunca llama a Google).';

    public function handle(GoogleSocialProof $google): int
    {
        $r = $google->refresh();

        // ⚠️ Cada salida dice QUÉ hay que ir a mirar, no solo que no hubo datos. Y todas terminan
        // en 0: un cron que informa de fallo cada hora acaba silenciado, y entonces sí se pierden
        // los fallos de verdad.
        $this->line(match ($r->outcome) {
            SocialProofRefresh::CACHED => "Reseñas en caché: {$r->reviews}.",
            SocialProofRefresh::NOT_CONFIGURED => 'Sin `place_id` o sin clave de API. Se configuran en '.
                'Ajustes y en el `.env`. La sección usa las opiniones propias.',
            SocialProofRefresh::BELOW_THRESHOLD => 'Google responde, pero el sitio no llega a '.
                GoogleSocialProof::MIN_REVIEWS.' reseñas: no hay media publicable. La sección usa las '.
                'opiniones propias. No es un fallo — es cuestión de tiempo.',
            SocialProofRefresh::REJECTED => 'Google ha rechazado la petición (HTTP '.$r->status.'). '.
                'Mira la consola: clave, restricción de IP, API habilitada o cuota diaria.',
            SocialProofRefresh::UNREACHABLE => 'No se ha podido hablar con Google (red o timeout). '.
                'La sección usa las opiniones propias.',
            default => 'Resultado desconocido: '.$r->outcome,
        });

        return self::SUCCESS;
    }
}
