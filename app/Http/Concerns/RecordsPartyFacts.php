<?php

namespace App\Http\Concerns;

use App\Domain\Booking\Models\OrderItem;
use App\Domain\Platform\Services\Analytics\Device;
use App\Domain\Platform\Services\Analytics\PartyFacts;
use App\Domain\Platform\Services\Analytics\Recorder;
use Illuminate\Http\Request;

/**
 * **Los hechos de la fiesta, desde la capa de entrega** (`docs/specs/analitica-fiesta.md` §4.1 y §4.2).
 *
 * Los tres controladores de las páginas enfocadas —el post-form, el justificante y la invitación— dicen aquí
 * lo que acaba de pasar, tras el éxito, y este trait compone el hecho: **de la RESERVA** (`order_id`), sin
 * visitante ni titular (`Recorder::factOfOrder()`), con los días que faltan para la fiesta, el dispositivo
 * y el idioma de la petición. Lo que no es de este hecho, el contrato lo descarta (`Contract::allowedProps()`).
 *
 * ⚠️ Un robot no deja hecho: el enlace de una invitación se pega en un chat y las vistas previas de las apps
 * de mensajería lo abren (`Device::isBot()` las conoce); contarlas inflaría las «aperturas».
 * ⚠️ Nunca desde un servicio del `CRITICAL_RE`: el hecho sale del controlador y el `Recorder` lo difiere al
 * `afterCommit` y traga con `Log`. Una firma o una compra no pueden fallar por la analítica.
 */
trait RecordsPartyFacts
{
    /**
     * @param  array<string, scalar|null>  $props  lo propio de este hecho; los tres comunes se añaden aquí
     */
    protected function partyFact(Request $request, OrderItem $reservation, string $name, array $props = []): void
    {
        if (Device::isBot($request->userAgent())) {
            return;
        }

        app(Recorder::class)->factOfOrder($name, (int) $reservation->order_id, $props + [
            'days_before' => PartyFacts::daysBefore($reservation->slot?->date),
            'device' => Device::classify($request->userAgent()),
            'locale' => app()->getLocale(),
        ]);
    }
}
