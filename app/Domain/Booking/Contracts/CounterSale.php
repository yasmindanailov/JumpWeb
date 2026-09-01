<?php

namespace App\Domain\Booking\Contracts;

/**
 * **Las reglas del AUTOSERVICIO que no atan al mostrador** (`#329`).
 *
 * Hay reglas de venta que existen para gobernar a un cliente que compra SOLO, sin nadie delante que
 * pueda juzgar el caso: la antelación mínima de un producto («las fiestas se piden con tres días»)
 * y el mínimo de invitados de un pack. Cuando quien vende es un operador con el cliente al teléfono
 * o en el mostrador, esa premisa no se cumple — él sabe si la cocina llega y si el grupo de 24
 * merece la pena.
 *
 * Este objeto es **quién pregunta**, y viaja desde la página del panel hasta `OrderCreator` y
 * `SlotOffer` para que la OFERTA y el COBRO respondan lo mismo. Sin él, la excepción se colaba como
 * un booleano por método y ya iban dos en una sola tanda.
 *
 * ⚠️⚠️ **Las dos excepciones NO son iguales, y por eso hay dos campos y no uno:**
 *
 *  · **La antelación mínima no ata al mostrador, punto.** No hay interruptor ni permiso: es una regla
 *    del autoservicio (`[DECIDIDO owner, 2026-09-01]`: «el operador no tenga límites para crear el
 *    pedido»). Un operador que coge el teléfono para hoy no tiene que pedir permiso para atenderlo.
 *  · **El mínimo del pack sigue atando salvo que el operador lo LEVANTE a propósito**, con su permiso
 *    (`orders.edit_item_below_minimum`) y su interruptor por línea, y queda registrado. Ahí no se está
 *    saltando una regla de autoservicio: se está vendiendo por debajo de lo que el negocio declaró
 *    rentable, y eso es una decisión con consecuencias que alguien tiene que poder auditar.
 *
 * ⚠️ **Lo que NO relaja, ni siquiera en mostrador**: el AFORO (`AFORO-01`), el máximo de invitados,
 * el `>= 1`, la ventana de horario del producto y el corte intra-día —una franja de hoy cuya hora ya
 * pasó no se ofrece—. Eso no son reglas del autoservicio: son el parque y el tiempo.
 */
final class CounterSale
{
    private function __construct(
        /** ¿Vende un OPERADOR? (lo que retira las reglas pensadas para quien compra solo) */
        public readonly bool $byOperator,
        /** ¿Y además levantó a propósito el mínimo de invitados del pack? */
        public readonly bool $belowPackMinimum,
    ) {}

    /**
     * La venta de siempre: el cliente comprando por su cuenta.
     *
     * ⚠️ **Es el DEFAULT de todo el dominio**, y por eso la web queda intacta por construcción: quien
     * no diga nada vende con las reglas completas.
     */
    public static function no(): self
    {
        return new self(false, false);
    }

    /** Un operador vendiendo por el panel. `$belowPackMinimum` llega ya resuelto contra el permiso. */
    public static function byOperator(bool $belowPackMinimum = false): self
    {
        return new self(true, $belowPackMinimum);
    }

    /**
     * ¿Se ignora la antelación mínima del producto?
     *
     * Se pregunta así —y no leyendo `byOperator` en cada sitio— para que el porqué viaje con la
     * pregunta: quien lea `if ($sale->ignoresMinAdvance())` en `SlotOffer` no tiene que saber que la
     * antelación es cosa del autoservicio; lo dice el nombre.
     */
    public function ignoresMinAdvance(): bool
    {
        return $this->byOperator;
    }

    /** ¿Se admite un pack por debajo de su mínimo de invitados? Solo si el operador lo levantó. */
    public function allowsBelowPackMinimum(): bool
    {
        return $this->byOperator && $this->belowPackMinimum;
    }
}
