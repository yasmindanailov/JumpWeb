<?php

namespace App\Domain\Booking\Contracts;

use Carbon\CarbonInterface;

/**
 * El calendario de operación del recinto, para quien vive fuera de Booking (Fase 2, paso 7:
 * `docs/specs/modulos-dominio.md` §4.octies).
 *
 * Extraído de lo que la landing ya hacía: `HeroStatus` («¿abrimos hoy?»), `ScheduleDisplay`
 * (horario semanal, temporadas y próximas excepciones) y `StructuredData` (JSON-LD de
 * `OpeningHoursSpecification` para buscadores). Ni un método más.
 *
 * **Por qué existe.** Content consultaba `OpeningHour`, `Season` y `SpecialDate` por su cuenta y
 * reimplementaba DOS reglas de Booking —cuál es la temporada vigente y cuál la ventana efectiva
 * de una fecha especial— con comentarios que decían literalmente «para no divergir de lo que
 * aplican las reservas». Eso es la misma trampa que el paso 1 encontró duplicada en dos consultas
 * SQL (coherencia #226): dos copias de una regla acaban separándose. Aquí la regla la aplica su
 * dueño y Content recibe datos ya resueltos, que solo tiene que formatear.
 *
 * La prioridad del dominio (fecha especial > temporada > semanal) y el fallback no destructivo
 * (sin configuración ⇒ abierto sin ventana) quedan dentro de la implementación.
 *
 * Implementación: `App\Domain\Booking\Services\OperatingSchedule` (bind en `BookingServiceProvider`).
 */
interface OperatingCalendar
{
    /**
     * Ventana EFECTIVA de un día, con la prioridad del dominio ya aplicada.
     *
     * Es la versión tipada de `OperatingSchedule::effectiveFor()`, que sigue devolviendo un array
     * porque la consumen el generador de franjas y la disponibilidad —código de aforo que este
     * paso NO toca (`INVARIANTES` §2 `AFORO-01`/`AFORO-03`)—. El contrato tipa la frontera; el
     * interior de Booking se queda como está.
     */
    public function windowFor(CarbonInterface $date): OperatingWindow;

    /**
     * Horario semanal configurado, indexado por `weekday` de Carbon (0=domingo … 6=sábado).
     * Un día AUSENTE del mapa es un día sin configurar (≠ cerrado).
     *
     * @return array<int, WeeklyOpening>
     */
    public function weeklyOpenings(): array;

    /**
     * Temporadas activas, ordenadas por fecha de inicio, con `isCurrent` ya resuelto.
     *
     * @return list<SeasonWindow>
     */
    public function activeSeasons(): array;

    /**
     * Excepciones de calendario desde HOY (incluido), ordenadas por fecha.
     *
     * @param  int  $limit  máximo de días a devolver (mínimo efectivo 1)
     * @return list<SpecialDay>
     */
    public function upcomingSpecialDays(int $limit): array;

    /** ¿Hay una excepción de calendario configurada para esta fecha? */
    public function hasSpecialDay(CarbonInterface $date): bool;
}
