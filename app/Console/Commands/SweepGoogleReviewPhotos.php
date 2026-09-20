<?php

namespace App\Console\Commands;

use App\Domain\Content\Models\GoogleBusinessReview;
use App\Domain\Content\Services\GoogleReviewImages;
use Illuminate\Console\Command;

/**
 * **BARRIDO DE HUÉRFANOS** de las imágenes de reseñas (T2·4,
 * `specs/google-business-profile.md` §4.3·6; `DECISIONES #524`, `#730`).
 *
 * La regla de la casa es que **el fichero se va con su fila**, y eso lo hace el evento `deleting`
 * del modelo. Esto es la red por debajo, para lo que ese evento no puede cubrir: una descarga que
 * escribió el fichero y cuya transacción se cayó después, un worker muerto a media pasada, o una
 * fila borrada a mano en la base durante un arreglo.
 *
 * ⚠️⚠️ **No es «la forma de limpiar», es el respaldo.** Si algún día esto empieza a borrar mucho,
 * lo que hay que mirar es por qué se están quedando huérfanos, no subirle la frecuencia.
 *
 * ⚠️ **Solo toca ficheros de más de una hora** ({@see GoogleReviewImages::sweep()}): entre que la
 * descarga escribe y la transacción confirma pasa un instante, y barrer justo ahí borraría la foto
 * de una reseña que se estaba guardando bien.
 */
class SweepGoogleReviewPhotos extends Command
{
    protected $signature = 'business-profile:sweep-photos';

    protected $description = 'Borra las imágenes de reseñas que ya no referencia ninguna fila. Red de seguridad, no la forma normal de limpiar.';

    public function handle(GoogleReviewImages $images): int
    {
        $enUso = [];

        foreach (GoogleBusinessReview::query()->get(['author_photo_path', 'photos']) as $resena) {
            foreach ($resena->imagePaths() as $ruta) {
                if (is_string($ruta) && $ruta !== '') {
                    $enUso[] = $ruta;
                }
            }
        }

        $borrados = $images->sweep($enUso);

        $this->line(sprintf('En uso %d · huérfanos borrados %d.', count($enUso), $borrados));

        return self::SUCCESS;
    }
}
