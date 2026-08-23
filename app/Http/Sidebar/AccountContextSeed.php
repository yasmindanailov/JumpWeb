<?php

namespace App\Http\Sidebar;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\CustomerAccountContext;
use App\Http\Resources\Api\V1\AccountContextResource;
use Illuminate\Support\Facades\Auth;

/**
 * **El contexto de cuenta que el servidor deja en el montaje del cajón**
 * (`docs/specs/account-context-vue.md` §4.3).
 *
 * El bloque de cuenta pasa a pintarlo Vue, y Vue llega con un `import()` en la primera apertura. Si
 * sus datos se pidieran a la API al montar, cada apertura del cajón costaría una petición más para
 * algo que **el servidor ya tiene resuelto al pintar la página**. Así que viaja con el HTML, igual
 * que `messages`, `ui`, `userId` y el desenlace del pago.
 *
 * ⚠️⚠️ **Sale del MISMO `AccountContextResource` que el endpoint**, no de una composición paralela.
 * Ése es el punto entero: si la semilla y `GET /me/account-context` se escribieran por separado, el
 * store del cajón tendría que normalizar **dos formas** del mismo dato —una al cargar la página y
 * otra al refrescar tras entrar sin recargar— y una de las dos envejecería sin que nadie lo notara.
 *
 * ⚠️⚠️ **Se poda por CARDINALIDAD, nunca por campo**, y la regla importa más que el ahorro:
 *  · **por cardinalidad** — `pending_forms` llega con **como mucho uno**, porque el bloque solo pinta
 *    uno: con varios formularios pendientes su aviso lleva al listado y solo usa el CONTADOR. La
 *    lista es la única parte sin cota del contexto, y sin podarla un cliente con ocho packs
 *    pendientes arrastraría ocho nombres y ocho URLs de post-form —**PII**— en el HTML de **cada**
 *    página;
 *  · **nunca por campo** — de `next_reservation` no se quita nada, aunque el bloque no pinte
 *    `time_window`. Quitarlo daría al cliente dos formas distintas del mismo objeto según viniera de
 *    la semilla o del refresco, que es exactamente lo que este fichero existe para evitar. Son
 *    cuatro campos cortos: el ahorro no paga el riesgo.
 *
 * ⚠️ **La clave viaja SIEMPRE, con `null` para el visitante anónimo.** Cuesta 24 B (medido) y evita que cada
 * consumidor tenga que distinguir «no está» de «está vacío» — el mismo argumento que
 * `BookingStatusResource` escribe para su aviso, y el mismo patrón que `userId` y `outcome`.
 */
final class AccountContextSeed
{
    /** Cuántos formularios pendientes viajan en el HTML. El bloque solo pinta uno. */
    private const MAX_PENDING_FORMS = 1;

    /**
     * El contexto del titular de esta petición, ya podado; `null` si no hay sesión.
     *
     * @return array<string, mixed>|null
     */
    public static function forCurrentRequest(): ?array
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user === null) {
            return null;
        }

        $payload = AccountContextResource::make(
            app(CustomerAccountContext::class)->for($user)
        )->resolve(request());

        $payload['pending_forms'] = array_slice(
            (array) $payload['pending_forms'], 0, self::MAX_PENDING_FORMS
        );

        return $payload;
    }
}
