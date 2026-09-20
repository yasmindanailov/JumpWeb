<?php

namespace App\Domain\Content\Services;

use App\Domain\Platform\Exceptions\GoogleBusinessApiException;
use App\Domain\Platform\Services\GoogleBusinessApi;
use App\Domain\Platform\Services\GoogleBusinessLocation;

/**
 * **Recorre la ficha entera y se queda con las candidatas** (T2·2,
 * `docs/specs/google-business-profile.md` §4.3·1 → §4.3·3 y §4.3·10; `DECISIONES #524`, `#728`).
 *
 * Es el paso de «hay 320 reseñas en Google» a «éstas doce son las que se pueden enseñar». No escribe
 * nada: devuelve un {@see GoogleReviewPass} y la decisión de persistirlo —y sobre todo la de
 * **borrar**— es de la T2·3, que es quien tiene el candado.
 *
 * ❗❗ **Se recorren TODAS las páginas aunque solo se guarden doce** (§4.3·1), y no es desperdicio:
 * sin recorrerlo todo no se sabe si lo que no apareció es que no existe o que no se llegó, y ésa es
 * exactamente la pregunta de la que depende borrar o no borrar. El recorrido va **en memoria** y lo
 * que se tira se tira sin tocar la base.
 *
 * ⚠️⚠️ **Hay un tope de páginas, y es una guarda de verdad.** Un `nextPageToken` que no termina
 * nunca —porque Google lo repita, o porque una respuesta venga mal— convierte esto en un bucle
 * infinito dentro de un worker. El tope corta, y lo que sale es una pasada marcada como **incompleta**
 * (`complete = false`), que §4.3·3 no deja borrar con ella. Parar es seguro; parar en silencio, no.
 */
final class GoogleReviewReader
{
    /**
     * Cuántas páginas se recorren como mucho: {@see GoogleBusinessApi::REVIEWS_PAGE_SIZE} × esto son
     * 2.000 reseñas, muy por encima de cualquier parque, y por debajo de «para siempre».
     */
    public const MAX_PAGES = 40;

    public function __construct(private readonly GoogleBusinessApi $api) {}

    /**
     * @param  string  $parent  `accounts/{id}/locations/{id}` ({@see GoogleBusinessLocation::reviewsParent()})
     * @param  int|null  $deadline  marca de tiempo UNIX a partir de la cual **no se pide otra página**
     *                              (§4.3·1, el presupuesto de la pasada). `null` = sin presupuesto.
     *
     * @throws GoogleBusinessApiException
     */
    public function pass(
        #[\SensitiveParameter] string $refreshToken,
        string $parent,
        GoogleReviewFilter $filter,
        ?int $deadline = null,
    ): GoogleReviewPass {
        /** @var array<string,IncomingGoogleReview> $candidatas */
        $candidatas = [];
        $vistas = 0;
        $paginas = 0;
        $completa = false;
        $pageToken = null;
        $primeraMedia = null;
        $primerTotal = 0;
        $ultimaMedia = null;
        $ultimoTotal = 0;

        do {
            $pagina = $this->api->reviews($refreshToken, $parent, $pageToken);
            $paginas++;

            if ($paginas === 1) {
                $primeraMedia = $pagina['averageRating'];
                $primerTotal = $pagina['totalReviewCount'];
            }

            // La última siempre: al salir del bucle, éstas son las cifras del final del recorrido.
            $ultimaMedia = $pagina['averageRating'];
            $ultimoTotal = $pagina['totalReviewCount'];

            foreach ($pagina['reviews'] as $fila) {
                // ⚠️ Se cuenta la fila ANTES de saber si sirve: `seen` es el testigo de que el
                // recorrido trajo algo, y §4.3·3 lo usa para distinguir «no hay reseñas» de «no se
                // llegó a ellas». Contando solo las candidatas, un filtro estricto se leería como
                // una ficha vacía y borraría la tabla.
                $vistas++;

                $resena = IncomingGoogleReview::fromApi($fila);

                if ($resena === null || ! $filter->accepts($resena)) {
                    continue;
                }

                // Deduplicada por el nombre de recurso (§4.3·3): dos páginas pueden traer la misma
                // reseña si la ficha se mueve mientras se pagina.
                $candidatas[$resena->name] = $resena;
            }

            $pageToken = $pagina['nextPageToken'];

            if ($pageToken === null) {
                $completa = true;
                break;
            }

            // ⚠️⚠️ **El presupuesto se mira ANTES de pedir la página siguiente, no después** (§4.3·1).
            // Parar aquí deja una pasada **incompleta**, que §4.3·3 no deja borrar — que es
            // exactamente lo que se quiere de una pasada que se ha quedado sin tiempo. La otra forma
            // de quedarse sin tiempo es que el worker muera a media petición, y entonces no se
            // escribe nada: las dos salidas son seguras, pero ésta además deja lo que ya vio.
            if ($deadline !== null && now()->getTimestamp() >= $deadline) {
                break;
            }
        } while ($paginas < self::MAX_PAGES);

        $lista = array_values($candidatas);

        // Las más recientes primero (§4.3·10). Se ordena aquí y no en la consulta porque el recorte
        // a `keep` tiene que quedarse con las recientes: recortar antes de ordenar guardaría doce
        // cualesquiera.
        usort($lista, fn (IncomingGoogleReview $a, IncomingGoogleReview $b) => $b->createdAt <=> $a->createdAt);
        $lista = array_slice($lista, 0, $filter->keep);

        return new GoogleReviewPass(
            candidates: $lista,
            firstAverage: $primeraMedia,
            firstTotal: $primerTotal,
            lastAverage: $ultimaMedia,
            lastTotal: $ultimoTotal,
            seen: $vistas,
            ambiguous: count(array_filter($lista, fn (IncomingGoogleReview $r) => $r->textAmbiguous)),
            complete: $completa,
        );
    }
}
