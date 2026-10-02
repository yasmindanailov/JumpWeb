<?php

namespace App\Domain\Booking\Contracts;

/**
 * Un GRUPO DE OPCIONES de venta posterior tal y como lo ve una reserva (`[DECIDIDO owner]` `DECISIONES #914`,
 * `fiesta-sistema-nuevo.md` §4.21): «¿Qué merienda?» con sus opciones, si hay que elegir y cuál está elegida.
 *
 * Es lo que leen a la vez la lista de invitados (la pregunta y «Falta elegir…»), el parque («sin elegir») y la API. Sus
 * opciones son filas de {@see PostFormAddonView} con su `group`: aquí van por id, en su orden.
 */
final readonly class PostFormChoiceGroupView
{
    public function __construct(
        public string $key,
        /** El título traducido («¿Qué merienda?»); `''` si el panel no lo puso. */
        public string $title,
        /** «Hay que elegir»: sin elegida, falta elegir (y, cerrada, el parque la ve «sin elegir»). */
        public bool $required,
        /** El id del complemento elegido, o `null` si ninguno. */
        public ?int $chosenAddonId,
        /** @var list<int> los ids de sus opciones, en el orden de la lista */
        public array $addonIds,
        /** `true` si aún se puede elegir o cambiar (alguna opción abierta). */
        public bool $open,
    ) {}

    /** ¿Hay que elegir y no se ha elegido? */
    public function pending(): bool
    {
        return $this->required && $this->chosenAddonId === null;
    }
}
