<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Booking\Contracts\AvailabilityOffer;
use App\Domain\Booking\Contracts\ProductCatalog;
use App\Http\Api\ApiCollection;
use App\Http\Api\CartPayload;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OfferedDateResource;
use App\Http\Resources\Api\V1\OfferedTimeResource;
use Illuminate\Http\Request;

/**
 * Fase 3 · paso 4b — la DISPONIBILIDAD de un producto (`docs/specs/api-v1.md` §4.4).
 *
 * Es el segundo de los tres momentos en que el servidor participa en un carrito que no guarda: qué
 * días y a qué horas se puede reservar, y cuánto cabe. Lo decide `Booking\Contracts\AvailabilityOffer`
 * sobre `SlotOffer`, que es la fuente ÚNICA de oferta web↔panel (`AFORO-02`); aquí no se consulta
 * una franja ni se cuenta un aforo.
 *
 * **Las horas van por `POST` y llevan la cesta, y eso no es una rareza REST.** `offerableTimes()`
 * descuenta los ocupantes provisionales que la propia cesta del cliente ya retiene: un `GET` sin
 * cesta ofrecería horas que la web no ofrece y que el checkout rechazaría, rompiendo `AFORO-02` en
 * la práctica aunque llamara a la fuente correcta. La cesta —líneas con complementos anidados— no
 * cabe con garantías en una query string, así que viaja en el cuerpo. No cambia nada del servidor.
 *
 * **Las fechas van por `GET` y NO llevan cesta**: un día se ofrece si tiene franjas vivas, y decidir
 * si cabe la cantidad deseada exigiría evaluar el cupo de todas las horas de todos los días del
 * horizonte para pintar un calendario.
 *
 * **Público**, como el catálogo: la web deja mirar y llegar hasta el pago sin cuenta.
 *
 * ⚠️ Nada de lo que responde es una reserva. Es cierto en el instante en que se calcula; quien
 * garantiza la plaza es `OrderCreator` bajo lock (`AFORO-01`), que vuelve a comprobarlo todo. Por
 * eso el nombre `Availability*` entra en el `CRITICAL_RE` del `pre-push` (`INVARIANTES §6`).
 */
class AvailabilityController extends Controller
{
    /**
     * Días reservables, con el precio de cada uno.
     *
     * Un producto que no está en el catálogo da 404 venga de donde venga —no existe, no se vende o
     * su zona no opera—, exactamente igual que `GET catalog/products/{id}`: distinguirlo le contaría
     * a un desconocido qué hay en la base de datos.
     */
    public function dates(int $product, AvailabilityOffer $offer, ProductCatalog $catalog): ApiCollection
    {
        abort_if($catalog->product($product) === null, 404);

        return new ApiCollection($offer->dates($product), OfferedDateResource::class);
    }

    /** Horas reservables de un día, con el cupo ya descontada la cesta que envía el cliente. */
    public function times(int $product, Request $request, AvailabilityOffer $offer, ProductCatalog $catalog): ApiCollection
    {
        abort_if($catalog->product($product) === null, 404);

        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            // La cesta es OPCIONAL aquí: la primera compra empieza sin nada elegido. Solo sirve para
            // descontar lo que uno mismo ya retiene, así que ausente equivale a vacía.
        ] + CartPayload::rules(requireItems: false));

        return new ApiCollection(
            $offer->times($product, $validated['date'], CartPayload::toCart($validated['items'] ?? [])),
            OfferedTimeResource::class,
        );
    }
}
