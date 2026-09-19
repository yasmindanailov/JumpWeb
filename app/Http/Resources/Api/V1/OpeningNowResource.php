<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Content\Services\OpeningState;
use App\Domain\Platform\Services\DisplayTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * **`GET /api/v1/schedule/now` — ¿está abierto AHORA?** (F5 · T2 del menú,
 * `docs/specs/instancia-y-landing-fuera.md` §4.1).
 *
 * ⚠️⚠️ **Es una ruta aparte del horario Y ÉSA ES LA DECISIÓN.** Los hechos del calendario cambian cuando
 * alguien toca el panel y se cachean cinco minutos; esto cambia **dos veces al día** y justo en el minuto en
 * que cambia es cuando importa. Servirlo dentro de `/schedule` obligaba a elegir: o el calendario se
 * recalcula en cada visita, o el cartel dice «abierto» cinco minutos después de cerrar. Es el mismo motivo
 * exacto por el que `boot` y `session` son dos rutas en F4 · T1 — *lo que no se cachea igual, no va junto*.
 *
 * Su caché es de **un minuto**: suficiente para que una landing que lo pida en cada visita no castigue al
 * servidor, y un error acotado y declarado en el peor momento posible.
 *
 * ⚠️ **Por qué lo calcula el servidor y no la landing**: hacerlo en el cliente obliga a reimplementar
 * `special_dates` > `seasons` > `opening_hours` y la zona horaria del parque. Eso es una regla de negocio
 * naciendo en el cliente —lo que el propio `/config` de este repo dice que no se hace— y además cada landing
 * la implementaría distinto.
 */
class OpeningNowResource extends JsonResource
{
    public static $wrap = null;

    public function __construct(private readonly OpeningState $estado)
    {
        parent::__construct(null);
    }

    public function toArray(Request $request): array
    {
        $ahora = $this->estado->current();

        return [
            'open_now' => $ahora->openNow,
            'timezone' => DisplayTime::timezone(),
            // ⚠️ `closes_at` SOLO abierto y `opens_at` SOLO cerrado, y no es una casualidad: decir «cierra a
            // las 21:30» de un parque cerrado es la clase de dato que se pinta sin pensar y deja un cartel
            // mintiendo. Lo que no aplica, no viaja — igual que en `/site`.
            ...($ahora->openNow
                ? ['closes_at' => $ahora->closesAt]
                : array_filter(['opens_at' => $ahora->opensAt?->toIso8601String()])),
        ];
    }
}
