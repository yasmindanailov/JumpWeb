<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Booking\Contracts\OperatingCalendar;
use App\Domain\Content\Services\OpeningState;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OpeningNowResource;
use App\Http\Resources\Api\V1\ScheduleFactsResource;

/**
 * El HORARIO del menú de hechos (F5 · T2), en dos lecturas que no se cachean igual:
 *  · `schedule` — el calendario: semana, temporadas y fechas especiales. Cambia cuando alguien toca el panel.
 *  · `now` — si está abierto en este momento. Cambia dos veces al día, y en ese minuto es cuando importa.
 */
class ScheduleFactsController extends Controller
{
    public function schedule(OperatingCalendar $calendario): ScheduleFactsResource
    {
        return new ScheduleFactsResource($calendario);
    }

    public function now(OpeningState $estado): OpeningNowResource
    {
        return new OpeningNowResource($estado);
    }
}
