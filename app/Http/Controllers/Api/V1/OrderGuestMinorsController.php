<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Booking\Contracts\AuthorizableOrders;
use App\Domain\Booking\Models\Order;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\GuardianRoster;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Fase 6 · el JUSTIFICANTE de un menor invitado — **lo que ve el RESPONSABLE de la reserva**
 * (`docs/specs/waiver-por-reserva.md` §4.10, tanda T3).
 *
 * Quién ha firmado ya, cuántos van y **el enlace para repartir**, en su propio endpoint.
 *
 * ❗❗ **Es su propia ruta y eso es la mitad del diseño**, por dos razones que se refuerzan:
 *
 *  1. **Son datos de menores de OTRAS familias.** Mismo principio que
 *     {@see OrderEventDataController}: si viajaran como un campo de `OrderItem`, irían en cada
 *     página de `me/orders` —una lista de hasta 50 pedidos que se pide para ver el historial—.
 *     Pedirlos es un acto explícito, no el efecto de listar.
 *  2. **El enlace es una CREDENCIAL PORTADORA.** `AccountContextResource` lo tiene escrito para su
 *     gemelo del post-form: *«PROHIBIDO publicar aquí la URL firmada […] esta misma respuesta se
 *     siembra en el HTML de cada página con sesión»*. Esta ruta **no se siembra en ninguna parte**:
 *     se pide cuando el responsable abre su lista, que es la «acción explícita» que §4.10 exige.
 *
 * ⚠️ Devuelve la forma `forResponsible()` del roster, que **no lleva ni el nombre ni el contacto de
 * ningún otro adulto**. Que no los lleve no es un filtro de esta clase: es que esa forma no los
 * tiene (`GuardianRoster`), así que este endpoint no puede filtrarlos mal.
 *
 * ⚠️ **Sin denominador inventado**: se devuelve `count` y la `capacity` del pedido, y la pantalla
 * dice «3 justificantes · la reserva es de 100 personas». **No se puede saber cuántos menores
 * vienen** —una reserva de 100 puede traer 100 menores o 60 y 40 adultos—, y un «3 de 100» sería una
 * cifra falsa con aspecto de dato.
 *
 * `Cache-Control: no-store` (`RGPD-04`) lo pone `NoStoreWhenAuthenticated` en toda respuesta
 * autenticada de la API.
 */
class OrderGuestMinorsController extends Controller
{
    public function __invoke(Request $request, string $code): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $order = Order::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('code', $code)
            ->first();

        // Un código ajeno responde igual que uno inexistente: un 403 le confirmaría a un desconocido
        // que ese pedido existe, y quien pregunta por él está preguntando por datos de menores.
        abort_if($order === null, 404);

        $context = app(AuthorizableOrders::class)->find((int) $order->getKey());
        $roster = app(GuardianRoster::class);

        return response()->json([
            'data' => [
                'minors' => $roster->forResponsible((int) $order->getKey()),
                'capacity' => $context?->capacity ?? 0,
                // El enlace SOLO mientras se pueda usar: pasada la visita o con el pedido sin pagar,
                // repartirlo sería mandar a un padre a una pantalla que le dirá que no.
                'link' => $context !== null && $context->isPaid && ! $context->visitFinished
                    ? $order->guardianAuthorizationSignedUrl()
                    : null,
            ],
        ]);
    }
}
