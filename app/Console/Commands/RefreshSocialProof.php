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
 * ❗❗ **Cada media hora y en UN idioma** (`[DECIDIDO owner, 2026-09-13]`, `#591`), para que las
 * reseñas estén siempre puestas: la caché dura 35 minutos y el refresco siguiente llega antes. Hasta
 * `#591` eran tres idiomas cada tres horas con una caché de media hora, y la sección enseñaba Google
 * media hora de cada tres.
 * ⚠️ **Cuesta dinero, poco, y conviene tenerlo escrito**: el campo `reviews` es el SKU Place Details
 * Enterprise + Atmosphere (25 USD por 1.000 llamadas y 1.000 gratis al mes, tarifa leída el
 * 2026-09-13). Son 48 llamadas al día, ~1.440 al mes: ~440 de pago, unos 11 USD.
 * ⚠️ La caché puede además evictarse antes de su TTL (`allkeys-lru`, `#137`): ahí la sección cae al
 * respaldo propio hasta el refresco siguiente, como mucho media hora.
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
        // ⚠️⚠️ **UNA LLAMADA POR PASADA, en el idioma de la instalación** (`#591`). Fueron tres —una
        // por idioma, `#491`— y obligaban a refrescar cada tres horas; hoy las tres versiones leen la
        // misma caché. Cada media hora son **48 al día**, así que el tope diario que se ponga en la
        // consola de Google tiene que quedar holgado POR ENCIMA (100): con 50, un par de refrescos a
        // mano lo agotan y la sección se apaga hasta el día siguiente.
        $r = $google->refresh();
        $this->line('['.GoogleSocialProof::sourceLocale().'] '.$this->explica($r));

        return self::SUCCESS;
    }

    private function explica(SocialProofRefresh $r): string
    {

        // ⚠️ Cada salida dice QUÉ hay que ir a mirar, no solo que no hubo datos. Y todas terminan
        // en 0: un cron que informa de fallo cada hora acaba silenciado, y entonces sí se pierden
        // los fallos de verdad.
        return match ($r->outcome) {
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
        };
    }
}
