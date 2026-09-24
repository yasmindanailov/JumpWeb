<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Services\Balance;
use App\Domain\Booking\Services\GuestCountPolicy;
use App\Domain\Booking\Services\OrderBook;
use App\Domain\Booking\Services\PostFormAddons;
use App\Domain\Booking\Services\ProductIcon;
use App\Domain\Platform\Services\DisplayTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fase 3 · paso 1 — una línea de pedido vista por su dueño.
 *
 * **Todo campo derivado sale de un método del dominio**, ninguno se recalcula aquí:
 * `displayStatusForCustomer()`, `isCancelled()`, `chargedSubtotalCents()`, `displayTimeWindow()` y
 * `guestFormStatus()` son ya la fuente única que usan «Mis pedidos», el calendario del panel y la
 * ficha del pedido. Reimplementar cualquiera de ellos en el serializador crearía la segunda fuente
 * de verdad que toda esta fase existe para no crear.
 *
 * Los tres valores de estado que salen al contrato son **claves estables**, no etiquetas: el
 * dominio devuelve `active`/`finished`, `ok`/`pending`/`null` y el `status` crudo del pedido; la
 * traducción la hace quien pinta. Verificado leyendo los métodos, no supuesto.
 *
 * @property-read OrderItem $resource
 */
class OrderItemResource extends JsonResource
{
    /**
     * El pedido al que pertenece la línea.
     *
     * ⚠️ **Se INYECTA desde `OrderResource`, no se navega.** `$item->order` sería una consulta por
     * línea —y en `me/orders` son hasta 50 pedidos paginados—, así que el pedido baja desde arriba,
     * donde ya está cargado. Mismo patrón que `OrderPaymentResource::withPaymentTicket()`.
     */
    private ?Order $order = null;

    public function within(Order $order): self
    {
        $this->order = $order;

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $item = $this->resource;

        // Sin el pedido no se puede componer el desglose de la reserva, y devolver ceros en
        // silencio sería peor que fallar: el cliente pintaría «0 € pendientes en puerta» sobre una
        // reserva que sí debe dinero. Como el único llamante es `OrderResource`, esto no puede
        // ocurrir sin que alguien haya añadido un segundo llamante y se haya saltado `within()`.
        $order = $this->order;
        assert($order !== null, OrderItemResource::class.' necesita su Order: usa ->within($order)');

        // ⚠️⚠️ **La relación INVERSA, puesta antes de nada.** Media docena de predicados de aquí
        // preguntan por el pedido de la línea (`acceptsGuestForm()`, `needsGuestForm()`,
        // `can_add_extras`…), y sin esto Eloquent lo resuelve con **una consulta por reserva** — en
        // la lista paginada del cliente, una por tarjeta. El pedido ya está aquí, lo trae
        // `within()`: es gratis. Medido con CONTROL en `PostFormDemandSurfacesTest`, era un N+1
        // PREEXISTENTE que ningún presupuesto vigilaba.
        $item->setRelation('order', $order);

        // El libro de ESTA reserva (T3·1 de `specs/desglose-libro.md`): nacimiento, gestiones, cobro
        // y devoluciones ATRIBUIDOS, y su propio saldo — la puerta y la hoja de sala leen el mismo.
        $book = OrderBook::forReservation($order, $item);

        return [
            'id' => $item->id,
            'product_name' => $item->displayProductName(),
            'date' => $item->slot?->date?->toDateString(),
            // ⚠️ **La ETIQUETA va al lado de la fecha cruda, y es el mismo criterio que ya rige para
            // `time_window`**: el servidor publica el texto ya compuesto y el cliente no lo recompone.
            // Medido el 2026-08-22 (`specs/area-cliente.md`): `Intl` **no reproduce** lo que Carbon
            // compone en español —«Sáb 5 sept» frente a «Sáb. 5 sep.»: ICU no abrevia con punto—, y
            // reproducirlo exigiría una tabla de meses traducidos en el cliente, que es justo lo que
            // `i18n.js` existe para que no haya. La fecha cruda se conserva para un cliente NATIVO
            // que prefiera formatear a su manera (Fase 6).
            'date_label' => DisplayTime::dayLabel($item->slot?->date),
            'time_window' => $item->displayTimeWindow(),
            'quantity' => (int) $item->quantity,
            'charged_subtotal_cents' => $item->chargedSubtotalCents(),
            'status' => $item->displayStatusForCustomer(),
            'cancelled' => $item->isCancelled(),
            // `null` = esta línea no pide datos por invitado (entrada, o pack sin esquema).
            // El cliente distingue así «no aplica» de «pendiente», que es lo que necesita para
            // decidir si enseña el aviso de formulario.
            'guest_form_status' => $item->guestFormStatus(),
            'needs_guest_form' => $item->needsGuestForm(),
            // ⚠️ **Sin los complementos FANTASMA**, por lo mismo que la lista de principales
            // (`OrderResource`): un complemento cancelado que nunca se cobró ni se reembolsó es
            // net-cero y las otras tres superficies lo ocultan. El predicado es el del dominio, no
            // uno reescrito aquí.
            'addons' => OrderItemAddonResource::collection(
                $item->children->reject(fn ($child) => $order->isVoidedLeftoverItem($child))->values(),
            )->resolve($request),
            // ── Añadidos en Fase 4 · paso 4.0b, para que el resumen de la reserva confirmada no
            //    tenga que adivinarse. Van a la COLA de la lista a propósito: `ApiContractTest`
            //    compara `required` con las propiedades **en el mismo orden**.
            //
            // Un pack se pinta distinto que una entrada («N invitados · Nombre» vs «N× Nombre»),
            // y el cliente no puede deducirlo de ningún otro campo.
            'is_pack' => $item->ticketType?->isPack() ?? false,
            // La hora de INICIO en crudo. `time_window` es un texto ya compuesto para mostrar
            // («10:00–11:00»); un cliente que necesite la hora sola tendría que partirlo, que es
            // exactamente la clase de parseo frágil que un contrato existe para evitar.
            'start_time' => $item->slot?->start_time,
            // El libro de la RESERVA (principal + sus complementos), no del pedido: en una cesta
            // mixta entrada+pack, etiquetar el agregado como «señal pagada» engaña (#225 F3).
            // ⚠️ Misma forma que el del pedido y compuesto por el MISMO value object: si la reserva
            // publicara su propia selección de campos, volveríamos a tener dos desgloses.
            'ledger' => LedgerResource::make($book)->resolve($request),
            // Y si procede enseñar «señal pagada · resto en el parque». Son TRES condiciones y se
            // publican COMPUESTAS —el pedido está cobrado, la reserva nació con señal (un HECHO del
            // libro, no el catálogo) y queda algo que pagar en el parque—: publicar solo los
            // números obligaría al cliente a recomponerlas, y es como divergen las cuatro
            // superficies que pintan este bloque.
            'shows_deposit_note' => $order->paid_at !== null
                && $book->hasDeposit
                && $book->balance->kind === Balance::KIND_PAY_AT_PARK,
            // ⚠️ **La cantidad, ya compuesta con su sustantivo** («8 invitados», «2 entradas»), por el
            // mismo criterio que `date_label` y `time_window`. Sin ella el cliente pintaba `quantity`
            // pegado al importe de la línea —`8×216,00 €`— y eso se lee como 8 × 216 = 1.728 €
            // (`specs/desglose-dinero-cliente.md` §17.1 · `L2`). Va a la COLA: `ApiContractTest`
            // compara `required` con las propiedades EN ORDEN.
            'quantity_label' => $item->displayQuantityLabel(),
            // ⚠️ **La URL del post-form la compone el SERVIDOR**, y `null` cuando esta reserva no lo
            // admite —`acceptsGuestForm()` exige pedido PAGADO y producto con invitados—. Componerla
            // en el cliente significaría quemar el enrutador de Laravel en JavaScript.
            // ⚠️ Es la ruta WEB, no la firmada de la API: acepta al dueño autenticado sin firma, y en
            // la tanda 1 el post-form se sigue abriendo como página, igual que hoy hace el bloque de
            // cuenta (`specs/area-cliente.md` §4.7).
            'guest_form_url' => $item->acceptsGuestForm()
                ? route('reservation.guests', ['reservation' => $item])
                : null,
            // ⚠️ **El icono que marca el producto, resuelto por el DOMINIO** (`DECISIONES #140`). El
            // cajón lo derivaba de `is_pack` con la geometría copiada dentro, así que un catálogo
            // entero se pintaba con dos dibujos. Va a la COLA: `ApiContractTest` compara `required`
            // con las propiedades EN ORDEN.
            'icon' => $item->ticketType?->iconKey() ?? ProductIcon::DEFAULT_OTHER,
            // ⚠️ **Si esta reserva admite EXTRAS ahora** (D15): es lo que permite al cajón nombrarlos
            // en el botón que lleva al post-form. Va a la COLA: `ApiContractTest` compara `required`
            // con las propiedades EN ORDEN.
            //
            // ⚠️⚠️ El dato es de la RESERVA, no del catálogo: `false` con la fiesta pasada, con los
            // plazos vencidos y en la instalación que no configura ninguno —el caso por defecto—.
            // Publicar «el catálogo tiene extras» habría invitado a comprar donde ya no se puede.
            'can_add_extras' => app(PostFormAddons::class)->offerableFor($item)->isNotEmpty(),
            // ⚠️ **Hasta cuándo se rellena el formulario y se ajustan los invitados** (T3e·5 de
            // `specs/isla-y-landing-nueva.md`): el MISMO instante que publica el formulario
            // (`GuestFormResource::guest_count_deadline`), del mismo método del dominio, y con la misma condición
            // que `guest_form_url` —sin formulario no hay plazo que decir—. Va a la COLA, como los de arriba.
            'guest_count_deadline' => $item->acceptsGuestForm()
                ? app(GuestCountPolicy::class)->deadlineFor($item)?->toIso8601String()
                : null,
            // Dónde se COMPARTE la invitación digital de esta reserva, o `null` si no la ofrece: vive dentro del
            // formulario, en su bloque (`reservation/guests.blade.php`, `#gf-invite`), y el ancla la pone el
            // servidor, que es quien conoce la vista. La regla es del producto (`offersGuestInvitation()`:
            // interruptor, pack y columna de nombre) y la del formulario (`acceptsGuestForm()`), no del cliente.
            'invitation_url' => $item->acceptsGuestForm() && ($item->ticketType?->offersGuestInvitation() ?? false)
                ? route('reservation.guests', ['reservation' => $item]).'#gf-invite'
                : null,
        ];
    }
}
