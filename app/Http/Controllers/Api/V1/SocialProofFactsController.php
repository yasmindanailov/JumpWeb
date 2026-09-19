<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Content\Contracts\SocialProof;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SocialProofFactsResource;

/**
 * `GET /api/v1/social-proof` — la CIFRA de prueba social (F5 · T6 del menú, `#646`).
 *
 * ⚠️⚠️ **Pide el CONTRATO, nunca `GoogleSocialProof`**, y ahí está todo el valor de esta ruta: la
 * fuente está a mitad de cambio —Business Profile sustituye a Places (`#524`)— y el día que se
 * sustituya el binding, esto no se toca. Nombrar aquí la implementación convertiría ese cambio en
 * tocar la API pública.
 *
 * ⚠️ **`rating()` no mira el consentimiento del visitante y no tiene por qué**: la cifra la trae
 * NUESTRO servidor, no lleva autor ni foto, y una media de un negocio no es dato personal
 * (`FallingBackSocialProof` lo argumenta y lo separa de las opiniones). Por eso esta ruta es
 * pública y cacheable, y por eso el cierre del consentimiento que el contenedor le pasa al
 * decorador no llega a evaluarse nunca por este camino.
 */
class SocialProofFactsController extends Controller
{
    public function __invoke(SocialProof $prueba): SocialProofFactsResource
    {
        return new SocialProofFactsResource($prueba->rating());
    }
}
