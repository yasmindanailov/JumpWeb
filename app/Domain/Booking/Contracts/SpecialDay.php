<?php

namespace App\Domain\Booking\Contracts;

/**
 * Excepción de calendario: un día concreto cerrado o con horario propio.
 *
 * La ventana que viaja aquí es la EFECTIVA (la del día especial o, si no la define, la del
 * horario semanal de ese día), resuelta por Booking. Content solo la pinta: antes repetía esa
 * resolución a mano en `specialDetail()`.
 */
final readonly class SpecialDay
{
    public function __construct(
        /** `Y-m-d` */
        public string $date,
        public bool $isClosed,
        /** Nota traducida al idioma activo, o null. */
        public ?string $note,
        public ?string $opensAt,
        public ?string $closesAt,
        /**
         * **La TARIFA que ese día aplica, escrita** —hoy «Viernes, findes y festivos»— o `null` si
         * la fecha no declara ninguna (entonces manda la regla por día de la semana).
         *
         * ▶ Existe desde `#531`, y lo pide la página `/precios`: su lista de festivos está ahí
         * **porque cambian el PRECIO**, así que una fila que solo dijera «cerrado» o su horario no
         * contestaría la pregunta por la que el visitante ha entrado. La 07 de la portada no la usa.
         * ⚠️ Es el rótulo del PANEL (`rate_types.label`), no una cadena nuestra: la tarifa se nombra
         * igual en todas las superficies (regla dura del canvas) y aquí solo se transcribe.
         */
        public ?string $rateLabel = null,
    ) {}

    public function hasHours(): bool
    {
        return $this->opensAt !== null && $this->closesAt !== null;
    }
}
