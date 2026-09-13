<?php

namespace App\Domain\Booking\Contracts;

/**
 * Un motivo por el que una línea NO puede entrar en la cesta ({@see CartLineValidation}).
 *
 * `reason` es una **clave estable**, no una clave de traducción ni un texto — misma lección que
 * `AdmissionDecision` y `ApiErrorCode`: la API la publica como código y quien pinta decide el
 * mensaje, así que reorganizar `lang/` no rompe a ningún cliente.
 *
 * `field` existe porque la compra web **resalta el input concreto** que falta, y perder eso al bajar
 * la regla al dominio habría sido una regresión de interfaz disfrazada de refactor. Es la clave del
 * campo del evento (`celebrant`, `age`…), o `null` cuando el problema es de la línea entera.
 *
 * `context` lleva los DATOS para componer el aviso (el mínimo del pack, la etiqueta del campo, el
 * tope de la cesta), nunca el texto ya compuesto.
 */
final readonly class CartLineProblem
{
    /** El producto no existe, no se vende online o su zona no opera. Las tres dan lo mismo (anti-oráculo). */
    public const PRODUCT_UNAVAILABLE = 'product_unavailable';

    /**
     * Esa fecha y hora no se ofrecen para este producto. Cubre de una vez las razones de
     * CALENDARIO que la oferta ya aplica —franja inexistente o cerrada, día pasado, hora que ya
     * pasó, fuera de la ventana del producto, sin la antelación mínima— y también el pack cuyo cupo
     * libre no llega a su mínimo de invitados, que no llega a ofrecerse.
     */
    public const TIME_UNAVAILABLE = 'time_unavailable';

    /** La hora se ofrece, pero está completa. Se distingue de la anterior: reintentar tiene sentido. */
    public const SOLD_OUT = 'sold_out';

    /** Por debajo del mínimo contratable (1 en una entrada, `min_qty` en un pack). `context.minimum`. */
    public const QUANTITY_BELOW_MINIMUM = 'quantity_below_minimum';

    /** La cesta ya tiene el máximo de líneas (`PAY-12`). `context.maximum`. */
    public const CART_FULL = 'cart_full';

    /** Un campo obligatorio del pack no está respondido. `field` = su clave; `context.label`. */
    public const EVENT_FIELD_REQUIRED = 'event_field_required';

    /**
     * La edad del cumpleañero no cabe en el tramo del pack (`#588`). `field` = la clave del campo;
     * `context.minimum`/`context.maximum` = el tramo; `context.suggestion` = el pack de la familia que
     * sí la admite (`{product_id, name}`) o `null`.
     */
    public const CELEBRANT_AGE_OUT_OF_RANGE = 'celebrant_age_out_of_range';

    /**
     * @param  self::*  $reason
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $reason,
        public ?string $field = null,
        public array $context = [],
    ) {}
}
