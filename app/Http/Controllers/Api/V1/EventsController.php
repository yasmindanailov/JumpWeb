<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Platform\Services\Analytics\Contract;
use App\Domain\Platform\Services\Analytics\EventIngestor;
use App\Domain\Platform\Services\Analytics\Visitor;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `POST /api/v1/events` — **la ingesta del libro de eventos** (`docs/specs/analitica.md` §4.1).
 *
 * Anónima y STATELESS: la ruta sale del `throttle:api` (apilado, la analítica se comería el cubo del
 * embudo) y del modo stateful de Sanctum (`sendBeacon` no lleva cabecera CSRF y volvía 419) — es la única
 * ruta del grupo con esa excepción, escrita en `routes/api.php` y en `SEC-01`. Limitador propio por
 * visitante (`throttle:events`), `no-store`, respuesta `202`.
 *
 * ⚠️ El `user_id` NUNCA viene del cliente: lo ata el servidor al identificarse, y solo con `analytics`.
 * ⚠️ Un nombre de SERVIDOR aquí se rechaza (`Contract`): nadie fabrica un `order_paid` con `curl`.
 * ⚠️ Sin cookie, el `202` la acuña: es la única respuesta de la API que la escribe (es `no-store`).
 */
class EventsController extends Controller
{
    public function __invoke(Request $request, EventIngestor $ingestor): JsonResponse
    {
        $data = $request->validate([
            'events' => ['required', 'array', 'min:1', 'max:'.(int) config('api.events.batch_max')],
            'events.*' => ['array'],
            'meta' => ['sometimes', 'array'],
            'meta.webdriver' => ['sometimes', 'boolean'],
            'meta.internal' => ['sometimes', 'boolean'],
            'meta.consent' => ['sometimes', 'array'],
        ]);

        $visitorId = Visitor::fromRequest($request);
        $minted = $visitorId === null;
        $visitorId ??= Visitor::mint();

        $result = $ingestor->ingest($visitorId, array_values($data['events']), $data['meta'] ?? [], $request);

        $response = response()->json($result, 202);

        // La app manda su id en la cabecera y no tiene cookies: solo se acuña para un navegador.
        if ($minted && $request->bearerToken() === null) {
            $response->withCookie(Visitor::cookie($visitorId, $request));
        }

        return $response;
    }

    /** Los nombres que el cliente puede emitir: lo que el contrato OpenAPI cierra con su `enum`. */
    public static function clientEvents(): array
    {
        return Contract::names(Contract::CLIENT);
    }
}
