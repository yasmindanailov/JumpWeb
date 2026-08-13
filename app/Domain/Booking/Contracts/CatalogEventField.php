<?php

namespace App\Domain\Booking\Contracts;

/**
 * Un campo del formulario de EVENTO de un pack (data-driven, #86), visto desde fuera de Booking.
 *
 * Los define la clienta desde el panel («Nombre del cumpleañero», «Edad»…) y el catálogo los expone
 * para que cualquier cliente pinte el formulario sin conocer la columna `event_fields`.
 *
 * **Solo los de la etapa `booking`** —los que se piden AL RESERVAR—. Los de etapa `postform` son de
 * otra superficie (el formulario por invitado, paso 5 del spec) y los servirá su propio endpoint:
 * un catálogo que los mezclara invitaría a pedirlos en la pantalla equivocada. Por eso el DTO no
 * lleva `stage`: dentro de esta lista siempre vale lo mismo, y un campo constante es ruido que
 * termina leyéndose mal.
 */
final readonly class CatalogEventField
{
    public function __construct(
        /** Clave estable con la que se envía la respuesta. */
        public string $key,
        /** Etiqueta ya resuelta al idioma activo. */
        public string $label,
        /** Tipo de control: `text`, `number` o `textarea`. */
        public string $type,
        public bool $required,
    ) {}
}
