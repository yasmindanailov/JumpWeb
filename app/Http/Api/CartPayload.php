<?php

namespace App\Http\Api;

use App\Domain\Booking\Services\OrderCreator;

/**
 * La forma de una CESTA en el cuerpo de una petición de `/api/v1` (Fase 3 · paso 4a).
 *
 * Vive aquí, y no dentro de un controlador, porque tres endpoints del paso 4 reciben la misma
 * cesta: el presupuesto (`POST orders/quote`), la disponibilidad —que la lleva porque
 * `SlotOffer::offerableTimes()` descuenta los ocupantes provisionales de la propia cesta
 * (`AFORO-02`)— y la creación del pedido. Escribir sus reglas tres veces es exactamente cómo
 * empiezan a divergir: `Cart::sanitize()` nació de ese mismo problema en el lado del dominio.
 *
 * **Traduce, no decide.** Es capa de entrega: pone el vocabulario público (`product_id`,
 * `quantity`) sobre el del dominio (`ticket_type_id`, `qty`) y valida la FORMA. Qué se puede
 * comprar, a qué precio y si cabe, lo deciden los contratos de Booking; aquí no se consulta nada.
 *
 * **Por qué validar en vez de sanear en silencio**: `Cart::sanitize()` descarta las líneas que no
 * encajan, que es lo correcto para una cesta de SESIÓN que puede venir de una versión anterior del
 * flujo. Para una petición de API es lo peor que se puede hacer: un cliente con un `date` mal
 * formado recibiría un presupuesto de menos líneas sin que nadie le dijera por qué. Con reglas, se
 * lleva un 422 que NOMBRA el campo.
 */
final class CartPayload
{
    /**
     * Tope de líneas del cuerpo, alineado con el invariante de servidor `PAY-12`
     * (`OrderCreator::MAX_LINES_PER_CART`).
     *
     * No lo sustituye —el del dominio sigue siendo el que manda, y es el que se aplica al crear el
     * pedido—: lo que hace es que una cesta desmedida se rechace con un 422 que dice qué pasa, en
     * vez de recorrerse entera para acabar en el mismo sitio. La cifra se lee del dominio para que
     * no puedan separarse.
     */
    private const MAX_LINES = OrderCreator::MAX_LINES_PER_CART;

    /**
     * Reglas de validación del cuerpo, con la cesta bajo la clave `items`.
     *
     * `time` se acepta en `H:i` y en `H:i:s` porque las dos formas circulan: el dominio guarda la
     * hora canónica de BD (`H:i:s`) y las interfaces suelen pintar `H:i`. Se normaliza al traducir,
     * de modo que el dominio recibe siempre una sola forma.
     *
     * @param  bool  $requireItems  `false` donde una cesta VACÍA es legítima. Lo es en la
     *                              disponibilidad —la primera compra empieza sin nada elegido, y la
     *                              cesta solo sirve para descontar lo que uno mismo ya retiene—, y
     *                              no lo es al presupuestar o al crear el pedido, donde una cesta
     *                              vacía es un error del cliente y merece decírselo.
     * @return array<string, array<int, string>>
     */
    public static function rules(bool $requireItems = true): array
    {
        return [
            'items' => $requireItems
                ? ['required', 'array', 'min:1', 'max:'.self::MAX_LINES]
                : ['sometimes', 'array', 'max:'.self::MAX_LINES],
            ...self::lineRules('items.*'),
        ];
    }

    /**
     * Reglas de UNA línea, bajo el prefijo que se le dé.
     *
     * Existe porque desde Fase 4 · paso 4.0b·6 una línea también viaja **suelta**: `cart/validate-line`
     * pregunta por una candidata que todavía no está en ninguna cesta. Escribir sus reglas otra vez
     * es exactamente cómo empiezan a divergir dos ideas de «qué es una línea» — el mismo motivo por
     * el que esta clase existe.
     *
     * ⚠️ **Una línea suelta se valida IGUAL que una de cesta**, `quantity >= 1` incluido. Se probó
     * relajarlo a 0 —«todavía no he elegido cuántos» tiene respuesta de negocio— y no compensa:
     * obligaba a un segundo esquema casi idéntico a `CartLine` en el contrato, y duplicar un esquema
     * es la deuda que `ApiContractTest` ya vigila a mano en el único sitio donde existe. Pedir cero
     * unidades es un problema de FORMA en un contrato público; lo que sí es de negocio —no llegar al
     * mínimo de invitados de un pack— sigue contestándose como tal.
     *
     * @param  string  $prefix  `items.*` para las líneas de una cesta, `line` para una suelta
     * @return array<string, array<int, string>>
     */
    public static function lineRules(string $prefix): array
    {
        return [
            $prefix.'.product_id' => ['required', 'integer', 'min:1'],
            $prefix.'.date' => ['required', 'date_format:Y-m-d'],
            $prefix.'.time' => ['required', 'date_format:H:i:s,H:i'],
            $prefix.'.quantity' => ['required', 'integer', 'min:1'],
            // Respuestas de los campos del evento de un pack. No influyen en el precio y el
            // presupuesto no las devuelve —son datos personales de un menor (`RGPD` §3) y el cliente
            // ya los tiene—; se aceptan para que el MISMO cuerpo sirva también para crear el pedido.
            $prefix.'.event_data' => ['sometimes', 'array'],
            $prefix.'.addons' => ['sometimes', 'array'],
            $prefix.'.addons.*.product_id' => ['required', 'integer', 'min:1'],
            $prefix.'.addons.*.quantity' => ['required', 'integer', 'min:1'],
            // Fase 6 · menores a cargo, tanda 4 (`specs/menores-a-cargo.md` §9.9.3 D1): los ids de los
            // menores para los que son estas entradas. Es FORMA: enteros, sin repetidos. Si son suyos, si
            // son menores ese día y si han firmado lo decide `Identity\Services\DependentAssigner`, y
            // SOLO `POST /orders` lo lee — los otros tres endpoints que comparten esta línea lo validan
            // e ignoran, y `toCart()` no se lo pasa a Booking (§4.6: Booking no conoce a los menores).
            $prefix.'.dependent_ids' => ['sometimes', 'array', 'max:'.self::MAX_LINES],
            $prefix.'.dependent_ids.*' => ['integer', 'min:1', 'distinct'],
            // El JUSTIFICANTE de un menor invitado (`specs/waiver-por-reserva.md` §12.2): «viene un
            // menor que NO está a mi cargo». Es FORMA —un booleano— y nada más: si el producto lo
            // ofrece, si lo exige, o si esto se ignora por completo lo decide `OrderCreator` con el
            // catálogo delante. Es el mismo reparto que `dependent_ids`.
            $prefix.'.guardian_authorization' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Cuerpo validado → cesta en la forma canónica del dominio.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array{ticket_type_id:int, date:string, time:string, qty:int, event_data:array<string,mixed>, addons:array<int, array{ticket_type_id:int, qty:int}>}>
     */
    public static function toCart(array $items): array
    {
        return array_values(array_map(self::toLine(...), $items));
    }

    /**
     * Cuerpo validado → lo que la ASIGNACIÓN de menores necesita de cada línea, en el orden de la
     * cesta (Fase 6 · tanda 4, `specs/menores-a-cargo.md` §9.9.3 D1/D2).
     *
     * Van TODAS las líneas, también las que no piden nada, y con su `index`: `DependentAssigner` usa el
     * recuento para comprobar que el pedido tiene exactamente las líneas que la cesta tenía antes de
     * escribir una sola fila —la correlación cesta ↔ ítem la promete `Booking\Contracts\CheckoutLines`
     * por posición, y una cuenta que no cuadre significa que nadie sabe qué ítem es qué línea—.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return list<array{index:int, product_id:int, date:string, quantity:int, dependent_ids:list<int>}>
     */
    public static function assignments(array $items): array
    {
        $lines = [];
        foreach (array_values($items) as $index => $item) {
            $lines[] = [
                'index' => $index,
                'product_id' => (int) $item['product_id'],
                'date' => (string) $item['date'],
                'quantity' => (int) $item['quantity'],
                'dependent_ids' => array_values(array_map('intval', is_array($item['dependent_ids'] ?? null) ? $item['dependent_ids'] : [])),
            ];
        }

        return $lines;
    }

    /**
     * @param  list<array{dependent_ids:list<int>}>  $lines
     */
    public static function hasAssignments(array $lines): bool
    {
        foreach ($lines as $line) {
            if ($line['dependent_ids'] !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * Una línea validada → la forma canónica del dominio.
     *
     * ⚠️ `guardian_authorization` SÍ cruza a Booking, a diferencia de `dependent_ids`: aquello son
     * personas de Identity —que Booking no conoce— y esto es una propiedad de la propia línea, como
     * `event_data`. Lo que llega es lo que el cliente DIJO; lo que se guarda lo decide `OrderCreator`.
     *
     * @param  array<string, mixed>  $item
     * @return array{ticket_type_id:int, date:string, time:string, qty:int, event_data:array<string,mixed>, guardian_authorization:bool, addons:array<int, array{ticket_type_id:int, qty:int}>}
     */
    public static function toLine(array $item): array
    {
        return [
            'ticket_type_id' => (int) $item['product_id'],
            'date' => (string) $item['date'],
            'time' => self::normalizeTime((string) $item['time']),
            'qty' => (int) $item['quantity'],
            'event_data' => is_array($item['event_data'] ?? null) ? $item['event_data'] : [],
            'guardian_authorization' => filter_var($item['guardian_authorization'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'addons' => array_values(array_map(static fn (array $addon): array => [
                'ticket_type_id' => (int) $addon['product_id'],
                'qty' => (int) $addon['quantity'],
            ], is_array($item['addons'] ?? null) ? $item['addons'] : [])),
        ];
    }

    /**
     * `H:i` → `H:i:s`. La hora canónica de BD lleva segundos y el dominio compara franjas por
     * cadena (`SlotOffer::passesIntradayFloor` es una comparación lexicográfica), así que una hora
     * sin segundos no casaría con ninguna franja: fallaría en silencio, ofreciendo un presupuesto
     * sin líneas en vez de un error.
     */
    private static function normalizeTime(string $time): string
    {
        return mb_strlen($time) === 5 ? $time.':00' : $time;
    }
}
