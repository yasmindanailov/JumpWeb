<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Contracts\AvailabilityOffer;
use App\Domain\Booking\Contracts\CartLineProblem;
use App\Domain\Booking\Contracts\CartLineValidation;
use App\Domain\Booking\Contracts\CartLineVerdict;
use App\Domain\Booking\Contracts\OfferedTime;
use App\Domain\Booking\Models\TicketType;

/**
 * Si una línea puede entrar en la cesta ({@see CartLineValidation}).
 *
 * Es la decisión que `Livewire\Tickets\Purchase::addToCart()` tomaba en su propio cuerpo, sacada de
 * una clase de interfaz para que la SPA —cuya cesta vive en el navegador y no hace ningún viaje al
 * añadir— pueda preguntarla en vez de transcribirla.
 *
 * **La franja se comprueba contra la OFERTA, no contra el aforo a secas.** Es la única diferencia de
 * fondo con lo que hacía la web, y no cambia su conducta: allí la hora siempre venía de
 * `availableTimes()`, así que ya estaba ofrecida. Pero un cliente de API puede enviar cualquiera, y
 * `AvailabilityOffer::maxQuantity()` responde de una franja concreta **aunque no se ofrezca** —no
 * mira día pasado, corte intradía, ventana del producto ni antelación mínima—: validar solo con él
 * daría por buena una línea que `OrderCreator` va a rechazar. Un endpoint de validación que miente
 * es peor que no tenerlo.
 *
 * Consulta la oferta **una sola vez**: `times()` ya trae por hora el `max_quantity` que acota el
 * selector, así que preguntar después por `maxQuantity()` sería repetir el mismo cálculo de aforo
 * para obtener el mismo número.
 */
class CartLineValidator implements CartLineValidation
{
    public function __construct(private AvailabilityOffer $availability) {}

    public function validate(array $cart, array $line): CartLineVerdict
    {
        $cart = Cart::sanitize($cart);
        $candidate = $this->normalizeCandidate($line);

        // Una línea que no tiene ni la forma de una línea no se puede evaluar contra nada. Es el
        // mismo «no has elegido nada» que enseña la web cuando falta la selección.
        if ($candidate === null) {
            return CartLineVerdict::reject([new CartLineProblem(CartLineProblem::PRODUCT_UNAVAILABLE)]);
        }

        $product = $this->selectableProduct($candidate['ticket_type_id']);

        if ($product === null) {
            return CartLineVerdict::reject([new CartLineProblem(CartLineProblem::PRODUCT_UNAVAILABLE)]);
        }

        // La cesta va SIN la candidata: es lo que ya está retenido, y meterla la haría competir
        // consigo misma por el cupo.
        $offer = $this->offeredTime($candidate, $cart);

        if ($offer === null) {
            return CartLineVerdict::reject([new CartLineProblem(CartLineProblem::TIME_UNAVAILABLE)]);
        }

        if (! $offer->sellable) {
            // La hora existe y se ofrece, pero está completa. Se distingue de `time_unavailable`
            // porque aquí reintentar más tarde tiene sentido: alguien puede soltar su reserva.
            return CartLineVerdict::reject([new CartLineProblem(CartLineProblem::SOLD_OUT)], $offer->maxQuantity);
        }

        $minimum = $this->minimumQuantity($product);

        // El cupo no llega ni al mínimo contratable: no es que el cliente pida de más, es que ahí
        // no cabe una línea de este producto. La web lo enseña como «no has elegido nada» porque
        // agrupa los tres casos de selección en un aviso; la API los separa, que es lo que permite
        // a un cliente decidir si ofrece otra hora o solo baja la cantidad.
        if ($offer->maxQuantity < $minimum) {
            return CartLineVerdict::reject([new CartLineProblem(CartLineProblem::SOLD_OUT)], $offer->maxQuantity);
        }

        if ($candidate['qty'] < $minimum) {
            return CartLineVerdict::reject([
                new CartLineProblem(CartLineProblem::QUANTITY_BELOW_MINIMUM, context: ['minimum' => $minimum]),
            ], $offer->maxQuantity);
        }

        // Tope de líneas (`PAY-12`): impide inflar la cesta para hacer contención sobre los locks de
        // franjas de `OrderCreator`. Va DESPUÉS de la selección, igual que en la web.
        //
        // ⚠️ **Se aplica AUNQUE la línea fuese a fundirse con otra**, que es como lo hace la compra
        // web desde siempre. Eximir la fusión parece más fino —la cesta no crece, luego el tope no
        // debería morder— pero es un cambio de conducta en una defensa anti-abuso, y colarlo dentro
        // de una extracción es justo lo que no se hace aquí (mismo criterio que la divergencia de
        // `contact.phone` en `DEUDA.md`). Si algún día se decide, se decide aparte y con su prueba.
        if (count($cart) >= OrderCreator::MAX_LINES_PER_CART) {
            return CartLineVerdict::reject([
                new CartLineProblem(CartLineProblem::CART_FULL, context: ['maximum' => OrderCreator::MAX_LINES_PER_CART]),
            ], $offer->maxQuantity);
        }

        if (($problems = $this->missingEventFields($product, $candidate)) !== []) {
            return CartLineVerdict::reject($problems, $offer->maxQuantity);
        }

        // La EDAD del cumpleañero tiene que caber en el tramo del pack (`#588`, `[DECIDIDO owner]`): la
        // web no deja reservar un pack que no es el suyo, y recomienda el de su familia que sí lo es.
        if (($mismatch = $product->celebrantAgeMismatch($candidate['event_data'])) !== null) {
            return CartLineVerdict::reject([
                new CartLineProblem(CartLineProblem::CELEBRANT_AGE_OUT_OF_RANGE, field: $mismatch->field, context: [
                    'minimum' => $mismatch->min,
                    'maximum' => $mismatch->max,
                    'suggestion' => $mismatch->suggestedProductId === null ? null : [
                        'product_id' => $mismatch->suggestedProductId,
                        'name' => $mismatch->suggestedProductName,
                    ],
                ]),
            ], $offer->maxQuantity);
        }

        $mergesWith = $this->mergeTarget($product, $candidate, $cart);

        // Re-tope en servidor (anti-manipulación): la cantidad efectiva nunca pasa del cupo. La web
        // lo hace en silencio; aquí se dice, porque un cliente que pidió 10 y entra con 6 tiene que
        // poder enseñar 6.
        $quantity = min($candidate['qty'], $offer->maxQuantity);

        return CartLineVerdict::accept(
            quantity: $quantity,
            quantityCapped: $quantity < $candidate['qty'],
            maxQuantity: $offer->maxQuantity,
            mergesWithIndex: $mergesWith,
        );
    }

    /**
     * La línea candidata en forma canónica, o `null` si no tiene ni la forma de una línea.
     *
     * ⚠️ **No se sanea con `Cart::sanitize()`, y la diferencia importa**: aquélla fuerza
     * `max(1, qty)` porque una cesta YA GUARDADA con cantidad 0 es corrupción y lo sensato es
     * repararla. Aquí una cantidad 0 significa **«todavía no he elegido cuántos»**, que es un estado
     * legítimo del paso 3 y tiene que llegar tal cual a la comparación con el mínimo. Aplicarle el
     * saneo de cesta convertía ese 0 en un 1 y dejaba añadir una entrada que nadie pidió — lo
     * encontró la suite de la compra web al hacer que delegara, no una revisión de código.
     *
     * @param  array<mixed>  $line
     * @return array{ticket_type_id:int, date:string, time:string, qty:int, event_data:array<string,mixed>, addons:array<int,array{ticket_type_id:int,qty:int}>}|null
     */
    private function normalizeCandidate(array $line): ?array
    {
        if (! isset($line['ticket_type_id'], $line['date'], $line['time'], $line['qty'])) {
            return null;
        }

        // Los complementos sí se sanean como en la cesta: una cantidad no positiva ahí es una fila
        // que no aporta nada, no una elección pendiente.
        $addons = [];
        foreach (is_array($line['addons'] ?? null) ? $line['addons'] : [] as $addon) {
            if (is_array($addon) && isset($addon['ticket_type_id'], $addon['qty']) && (int) $addon['qty'] > 0) {
                $addons[] = ['ticket_type_id' => (int) $addon['ticket_type_id'], 'qty' => (int) $addon['qty']];
            }
        }

        return [
            'ticket_type_id' => (int) $line['ticket_type_id'],
            'date' => (string) $line['date'],
            'time' => (string) $line['time'],
            'qty' => (int) $line['qty'],
            'event_data' => is_array($line['event_data'] ?? null) ? $line['event_data'] : [],
            'addons' => $addons,
        ];
    }

    /**
     * El producto ELEGIBLE por id, o `null`.
     *
     * Mismo conjunto que el catálogo y que la disponibilidad: en venta online, zona operativa y
     * seleccionable (entrada o pack, nunca un complemento suelto). Que no exista, que no se venda o
     * que su zona esté apagada dan la misma respuesta a propósito — distinguirlas le contaría a un
     * desconocido qué hay en la base de datos.
     */
    private function selectableProduct(int $productId): ?TicketType
    {
        return TicketType::sellable()
            ->inOperationalZone()
            ->whereIn('type', [TicketType::TYPE_ENTRY, TicketType::TYPE_PACK])
            ->whereKey($productId)
            ->first();
    }

    /**
     * La hora de la línea entre las OFRECIDAS ese día, o `null` si no se ofrece.
     *
     * @param  array{ticket_type_id:int, date:string, time:string, qty:int, event_data:array<string,mixed>, addons:array<int,array{ticket_type_id:int,qty:int}>}  $candidate
     * @param  array<int, array<string, mixed>>  $cart
     */
    private function offeredTime(array $candidate, array $cart): ?OfferedTime
    {
        foreach ($this->availability->times($candidate['ticket_type_id'], $candidate['date'], $cart) as $offered) {
            if ($offered->time === $candidate['time']) {
                return $offered;
            }
        }

        return null;
    }

    /** 1 para una entrada; el mínimo de invitados para un pack (`min_qty`), nunca menos de 1. */
    private function minimumQuantity(TicketType $product): int
    {
        return $product->isPack() ? max(1, (int) ($product->min_qty ?? 1)) : 1;
    }

    /**
     * Índice de la línea de la cesta que absorbería a la candidata, o `null` si entra como nueva.
     *
     * **Solo se funden entradas sin complementos.** Un pack es siempre su propio bloque —tiene sus
     * propias respuestas de evento, y sumarle invitados de otra línea mezclaría dos fiestas— y una
     * línea con complementos tampoco se funde, porque la fusión cambiaría cantidad, señal y
     * ocupación sin tocar los complementos que ya lleva.
     *
     * @param  array{ticket_type_id:int, date:string, time:string, addons:array<int,array<string,mixed>>}  $candidate
     * @param  array<int, array<string, mixed>>  $cart
     */
    private function mergeTarget(TicketType $product, array $candidate, array $cart): ?int
    {
        if ($product->isPack() || $candidate['addons'] !== []) {
            return null;
        }

        foreach ($cart as $index => $line) {
            if ($line['ticket_type_id'] === $candidate['ticket_type_id']
                && $line['date'] === $candidate['date']
                && $line['time'] === $candidate['time']
                && $line['addons'] === []) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Campos obligatorios del pack que el servidor ve SIN responder.
     *
     * ⚠️ **«Sin responder» lo decide el saneo, no si el cliente escribió algo.** Un campo `number`
     * pasa por `preg_replace('/\D+/', '')`, así que la edad contestada «cinco» queda vacía: el
     * cliente la ve rellena y el servidor no. Ese caso es la razón entera de que esto sea un
     * endpoint y no una regla transcrita (`DECISIONES #38(f)`).
     *
     * Solo la fase de RESERVA: los campos `postform` se piden después, en el formulario por invitado.
     *
     * @param  array{event_data:array<string,mixed>}  $candidate
     * @return list<CartLineProblem>
     */
    private function missingEventFields(TicketType $product, array $candidate): array
    {
        if (! $product->isPack()) {
            return [];
        }

        $missing = $product->missingRequiredEventFields($candidate['event_data'], TicketType::EVENT_STAGE_BOOKING);

        if ($missing === []) {
            return [];
        }

        $fields = [];
        foreach ($product->eventFields(TicketType::EVENT_STAGE_BOOKING) as $field) {
            $fields[$field['key']] = $field;
        }

        $problems = [];
        foreach ($missing as $key) {
            $problems[] = new CartLineProblem(
                CartLineProblem::EVENT_FIELD_REQUIRED,
                field: $key,
                // La etiqueta viaja con el problema porque quien lo pinta necesita NOMBRAR lo que
                // falta, y los textos de estos campos viven en BD (data-driven por instalación), no
                // en `lang/`: un cliente no puede resolverla por su cuenta.
                context: isset($fields[$key]) ? ['label' => $product->eventFieldLabel($fields[$key])] : [],
            );
        }

        return $problems;
    }
}
