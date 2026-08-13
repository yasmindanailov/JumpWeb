<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Booking\Models\Order;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OrderEventDataResource;
use Illuminate\Http\Request;

/**
 * Fase 4 · paso 4.0b·4b — **las respuestas del pack de un pedido, en su propio endpoint**
 * (`docs/specs/sidebar-spa.md` §4.4.1, hueco 4).
 *
 * Al confirmar la compra, el cajón enseña bajo cada línea lo que el cliente contestó al reservar:
 * el nombre del homenajeado, su edad y las alergias. Son datos personales de un MENOR —las alergias
 * son dato de salud del **art. 9**—, y esa es toda la razón de que esto no sea un campo más de
 * `OrderItem`: si viajara ahí, **iría en cada página de `me/orders`**, que es una lista paginada de
 * hasta 50 pedidos que un cliente pide para ver el historial, no para leer alergias de niños. La
 * separación es el principio de minimización hecho ruta, y su prueba es el test de que `me/orders`
 * y `GET orders/{code}` **nunca** los llevan.
 *
 * **Solo las respuestas de la fase `booking`.** `event_data` guarda juntas las de las dos fases, y
 * las del post-form ya tienen endpoint propio —`GET reservations/{id}/guest-form`, que además se
 * abre con firma— con la simetría escrita al revés en `GuestFormResource::generalAnswers()`. Un
 * segundo camino hacia el mismo dato del art. 9 amplía la superficie sin que nadie lo haya pedido.
 * Es coherente además con lo que la web enseña: al paso 6 solo se llega volviendo de la pasarela, y
 * en ese instante no existe ninguna respuesta de post-form.
 *
 * **Va por PEDIDO y no por reserva** porque así se pide: el resumen pinta todas las líneas a la vez,
 * y una petición por línea multiplicaría por N un endpoint que ya devuelve PII.
 *
 * `Cache-Control: no-store` (`RGPD-04`) lo pone `NoStoreWhenAuthenticated` por defecto en toda
 * respuesta autenticada de la API — a diferencia del post-form, que al ser accesible SIN sesión sí
 * necesita declararlo en su ruta. Hay test de que la cabecera llega.
 */
class OrderEventDataController extends Controller
{
    public function __invoke(Request $request, string $code): OrderEventDataResource
    {
        /** @var User $user */
        $user = $request->user();

        // Solo las líneas de PRODUCTO (`parent_item_id` nulo): un complemento nace con
        // `event_data` a `null` (`AddonResolver`), así que traerlos sería recorrer filas que no
        // pueden aportar nada. `ticketType` va eager-loaded porque la composición lo consulta por
        // línea —esquema y etiquetas— y sin él serían N consultas.
        $order = Order::query()
            ->with(['items' => fn ($q) => $q->whereNull('parent_item_id')->with('ticketType')])
            ->where('user_id', $user->getAuthIdentifier())
            ->where('code', $code)
            ->first();

        // Un código ajeno responde igual que uno inexistente. Aquí el 404 pesa más que en el resto
        // de la superficie: un 403 confirmaría a un desconocido que ese pedido existe, y quien
        // pregunta por él está preguntando por los datos de un menor.
        abort_if($order === null, 404);

        return new OrderEventDataResource($order);
    }
}
