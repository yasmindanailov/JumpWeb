<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\CustomerAccountContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AccountContextResource;
use Illuminate\Http\Request;

/**
 * **El contexto de cuenta del cliente**, en una sola petición
 * (`docs/specs/account-context-vue.md` §4.4).
 *
 * El controlador **no consulta nada**: pide a `Identity\Services\CustomerAccountContext`, que es el
 * mismo servicio que alimentan el nav y el bloque de cuenta de la web desde `#221` y que a su vez
 * pide las reservas al contrato de Booking. Aquí no se decide ninguna regla — es el mismo criterio
 * que `MeReservationsController`, y por el mismo motivo: dos definiciones de «mi próxima reserva»
 * en el mismo producto acaban divergiendo.
 *
 * ⚠️⚠️ **Por qué existe, si `/me`, `/me/reservations` y `/me/orders` ya publican las piezas.** Porque
 * la que falta no se puede componer bien desde fuera: **`GET /me/orders` PAGINA**, así que contar
 * formularios pendientes sobre *una página* es contar mal, y `upcoming_count` no sale de ahí en
 * absoluto. Y porque el consumidor real —el bloque de cuenta del cajón, cuando repinta tras
 * conseguir sesión sin recargar— necesita las tres cosas **a la vez y en un viaje**, en mitad de una
 * compra. *(La v1 de la spec lo justificaba diciendo que derivarlo de `/me/orders` obligaría a
 * repetir una regla del dominio en JS. Era falso —`OrderItemResource` ya publica `needs_guest_form`
 * y `guest_form_url` resueltos— y la revisión adversarial lo tumbó. El motivo real es éste.)*
 *
 * **Scoping por el guard**: el titular sale del usuario autenticado y nunca de la petición, así que
 * no hay superficie de IDOR — igual que el resto de `me/*`.
 *
 * ⚠️ **No exige `verified`**, como `/me`, `/me/reservations` y `/me/orders`: el alta *pay-first* del
 * embudo abre sesión sin correo verificado, y ese cliente también tiene bloque de cuenta que pintar.
 * Se dice aquí y en el contrato en vez de dejarlo implícito, porque `SEGURIDAD.md` regla 6 pide
 * `verified` para el área privada de la WEB y esto podría leerse como una excepción olvidada.
 *
 * ⚠️ **`no-store` no se declara**: lo pone `Api\NoStoreWhenAuthenticated` por defecto en toda
 * respuesta autenticada de `/api/v1` (`RGPD-04`). El cuerpo lleva nombre de pila, el producto de una
 * reserva y las URLs de sus post-forms.
 *
 * **Defensivo, como el servicio**: `CustomerAccountContext` envuelve su carga en un `try` y devuelve
 * un contexto vacío seguro ante cualquier fallo. Este endpoint hereda esa conducta a propósito —una
 * cortesía de UI no tumba una pantalla—, así que un 200 con el contexto vacío es una respuesta
 * legítima y no un error silenciado.
 */
class MeAccountContextController extends Controller
{
    public function __invoke(Request $request, CustomerAccountContext $context): AccountContextResource
    {
        /** @var User $user */
        $user = $request->user();

        return new AccountContextResource($context->for($user));
    }
}
