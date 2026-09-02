<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Booking\Contracts\AuthorizableReservation;
use App\Domain\Booking\Contracts\AuthorizableReservations;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\GuardianPlaces;
use App\Domain\Identity\Services\GuardianRoster;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Fase 6 · el JUSTIFICANTE de un menor invitado — **lo que ve el RESPONSABLE de la reserva**
 * (`docs/specs/waiver-por-reserva.md` §4.10, §13).
 *
 * Quién ha firmado ya, cuántas plazas quedan y **el enlace para repartir**, **una entrada por
 * RESERVA**.
 *
 * ⚠️⚠️ **Devolvía UNA cosa por pedido hasta `#401`, y lo cazó el owner**: un pedido con una excursión
 * el lunes y una entrada el miércoles daba un solo enlace que, abierto, decía las dos fechas. El
 * justificante cuelga de la VISITA.
 *
 * ❗❗ **Es su propia ruta y eso es la mitad del diseño**, por dos razones que se refuerzan:
 *
 *  1. **Son datos de menores de OTRAS familias.** Mismo principio que
 *     {@see OrderEventDataController}: si viajaran como un campo de `OrderItem`, irían en cada
 *     página de `me/orders` —una lista de hasta 50 pedidos—. Pedirlos es un acto explícito.
 *  2. **El enlace es una CREDENCIAL PORTADORA.** `AccountContextResource` lo tiene escrito para su
 *     gemelo del post-form: *«PROHIBIDO publicar aquí la URL firmada […] esta misma respuesta se
 *     siembra en el HTML de cada página con sesión»*. Esta ruta **no se siembra en ninguna parte**.
 *
 * ⚠️ Devuelve la forma `forResponsible()` del roster, que **no lleva ni el nombre ni el contacto de
 * ningún otro adulto**. Que no los lleve no es un filtro de esta clase: es que esa forma no los
 * tiene, así que este endpoint no puede filtrarlos mal.
 *
 * ⚠️ **`places` son las plazas LIBRES de la reserva** —su cantidad menos los menores a cargo ya
 * asignados y los justificantes ya firmados—, no un denominario inventado. §4.10 prohíbe decir «3 de
 * 100» porque no se sabe cuántos menores vienen; esto sí se sabe: esas plazas ya tienen dueño.
 *
 * `Cache-Control: no-store` (`RGPD-04`) lo pone `NoStoreWhenAuthenticated`.
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

        $reader = app(AuthorizableReservations::class);
        $roster = app(GuardianRoster::class);
        $places = app(GuardianPlaces::class);

        // Las que nacieron marcadas MÁS las que ya tienen justificantes: el operador pudo mandar el
        // enlace a mano de una que nadie marcó (el caso «el cliente no sabía»), y esconderla aquí
        // dejaría al responsable sin ver quién ha firmado.
        $reservations = $reader->markedForOrder((int) $order->getKey());
        $known = array_map(fn (AuthorizableReservation $r): int => $r->reservationId, $reservations);

        foreach ($order->items()->whereNull('parent_item_id')->whereNull('cancelled_at')->orderBy('id')->get() as $item) {
            $id = (int) $item->getKey();
            if (! in_array($id, $known, true) && $roster->countFor($id) > 0) {
                $found = $reader->find($id);
                if ($found !== null) {
                    $reservations[] = $found;
                }
            }
        }

        return response()->json([
            'data' => [
                'reservations' => array_map(
                    fn (AuthorizableReservation $r): array => [
                        'reservation_id' => $r->reservationId,
                        'product_name' => $r->productName,
                        'date' => $r->date,
                        'minors' => $roster->forResponsible($r->reservationId),
                        'places' => $places->freeIn($r),
                        // El enlace SOLO mientras se pueda usar. Tres condiciones, y la tercera la
                        // destapó la sonda del owner: con el pedido sin pagar, con la visita pasada
                        // **o sin plazas libres**, repartirlo sería mandar a un padre a una pantalla
                        // que le dirá que no. El caso real: una entrada asignada a su propia hija.
                        'link' => $r->isPaid && ! $r->visitFinished && $places->freeIn($r) > 0
                            ? OrderItem::query()->whereKey($r->reservationId)->first()?->guardianAuthorizationSignedUrl()
                            : null,
                    ],
                    $reservations,
                ),
            ],
        ]);
    }
}
