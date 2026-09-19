<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Content\Contracts\Rating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * **`GET /api/v1/social-proof` — LA CIFRA de prueba social** (F5 · T6 del menú de hechos,
 * `docs/specs/instancia-y-landing-fuera.md` §4.1; `DECISIONES #616` y `#646`).
 *
 * Sirve la valoración agregada de la instalación —media, recuento, fuente y enlace a la ficha— para
 * que la landing de una instancia pueda pintar su chapa sin el producto.
 *
 * ⚠️⚠️ **Hoy NO sirve las reseñas, y es una decisión, no una carencia** (`#646`). `#616` las quería
 * aquí, pero la fuente está a mitad de cambio: `specs/google-business-profile.md` —aprobada, código
 * no empezado— sustituye Places por Business Profile y dice que el contrato `SocialProof` CAMBIA
 * (§4.3·9): una reseña gana la respuesta del parque, las fotos y la marca de anónimo, y aparecen el
 * enlace a la ficha, «Escribir una reseña» y **la línea del filtro**, que no es estética —la ley
 * Ómnibus considera engañoso enseñar solo las buenas sin decirlo—. Publicar hoy las reseñas sería
 * repartir contenido de Places a otro repo y comprometerse con una forma que ya sabemos que cambia.
 * ▶ Llegan en la T2 de esa spec, **añadiéndose a este sobre** como clave hermana: por eso la cifra
 * viaja envuelta y no suelta en la raíz.
 *
 * ⚠️ **La cifra no se traduce, así que esta ruta no lleva `?lang=`** —y es la única del menú que no
 * lo lleva—: una media, un recuento y una URL son los mismos en los tres idiomas. Quien pinta pone
 * el texto de la atribución («en Google»), que es lo que la fuente exige acreditar.
 *
 * ⚠️⚠️ **`source` no es adorno**: quien publique la cifra tiene que decir de quién es. Y **no se
 * publica como `aggregateRating` de JSON-LD** con datos de un tercero — la spec de Business Profile
 * lo prohíbe con guarda para nuestra portada, y una landing de instancia tiene la misma obligación.
 */
class SocialProofFactsResource extends JsonResource
{
    public static $wrap = null;

    public function __construct(private readonly ?Rating $cifra)
    {
        parent::__construct(null);
    }

    /**
     * ⚠️⚠️ **Sin esto, una instalación sin cifra publica `[]` en vez de `{}`** — la trampa que la
     * receta del menú tiene escrita («un array vacío de PHP sale `[]` y el tipo no puede depender de
     * si alguien rellenó el panel»), aquí en la RAÍZ. Los hermanos la esquivan con `(object)` por
     * bloque, pero eso no vale para el objeto de arriba: `JsonResource::resolve()` hace `(array)`
     * sobre lo que devuelva `toArray()`, así que devolver un objeto no cambia nada. El único sitio
     * donde se puede fijar el tipo es la entrega.
     *
     * Medido antes de escribir esto: la respuesta salía `[]`, y un cliente tipado habría intentado
     * leer una LISTA de cifras.
     */
    public function toResponse($request): JsonResponse
    {
        return new JsonResponse((object) $this->toArray($request));
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($this->cifra === null) {
            // **Sin cifra el sobre va VACÍO, y eso es una respuesta normal, no un fallo.** Pasa
            // cuando la instalación no tiene fuente configurada, cuando su caché está fría o cuando
            // no llega al umbral que hace publicable una media. Ninguna se registra como error, y
            // ninguna justifica inventar un `0` — que una landing pintaría como «0,0 sobre 5».
            return [];
        }

        return [
            'rating' => array_filter([
                'value' => $this->cifra->value,
                'count' => $this->cifra->count,
                'source' => $this->cifra->source,
                'url' => $this->cifra->url,
            ], fn (mixed $valor): bool => $valor !== null),
        ];
    }
}
