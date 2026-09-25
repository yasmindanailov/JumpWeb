<?php

namespace App\Domain\Content\Services;

use App\Domain\Content\Models\Testimonial;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * **Las imágenes de una reseña COPIADA de la ficha** (`DECISIONES #771`): la foto del autor y las que adjuntó, traídas
 * al disco `uploads` (`resenas/`) para servirlas desde casa —el visitante no le pide nada a Google—.
 *
 * ⚠️⚠️ **Las mismas reglas que {@see GoogleReviewImages}**, que es quien ya descarga bytes de Google y está endurecido
 * en seis frentes: de allí salen la lista blanca EXACTA de hosts y el tipo por los BYTES (sus `allowed()` y `sniff()`,
 * reutilizados, no copiados); aquí se repiten las otras cuatro —sin redirecciones, tope leyendo a trozos, el nombre es
 * el hash del contenido y ninguna librería toca los bytes—.
 * ⚠️ **No comparte su disco**: el barrido de aquél borra lo que no referencian las reseñas del Perfil de Empresa, y
 * éstas son otra tabla.
 */
final class CopiedReviewImages
{
    public const DIRECTORY = 'resenas';

    /** Una foto de reseña a 1200 px no llega ni de lejos; el tope está para que una respuesta sin fin no llene el disco. */
    public const MAX_BYTES = 3 * 1024 * 1024;

    /** Trae una imagen y devuelve su ruta en el disco `uploads`, o `null` si no se puede (sale la inicial, nunca la URL). */
    public function fetch(?string $url): ?string
    {
        if ($url === null || ! GoogleReviewImages::allowed($url)) {
            return null;
        }

        try {
            $respuesta = Http::withOptions(['allow_redirects' => false, 'stream' => true])->timeout(10)->connectTimeout(4)->get($url);
        } catch (ConnectionException) {
            return null;
        }

        if (! $respuesta->successful()) {
            return null;
        }

        try {
            $flujo = $respuesta->toPsrResponse()->getBody();
            $bytes = '';
            while (! $flujo->eof()) {
                $bytes .= $flujo->read(8192);
                if (strlen($bytes) > self::MAX_BYTES) {
                    return null;
                }
            }
        } catch (RuntimeException) {
            return null;
        }

        $extension = $bytes === '' ? null : GoogleReviewImages::sniff($bytes);
        if ($extension === null) {
            return null;
        }

        $ruta = self::DIRECTORY.'/'.hash('sha256', $bytes).'.'.$extension;
        $disco = Storage::disk(Testimonial::IMAGE_DISK);

        return $disco->exists($ruta) || $disco->put($ruta, $bytes) ? $ruta : null;
    }
}
