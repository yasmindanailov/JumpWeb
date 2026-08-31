<?php

namespace App\Http\Controllers;

use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Services\GuestAgeMixReader;
use App\Domain\Booking\Services\MixedPartySettings;
use App\Domain\Booking\Services\MixedPartySurcharge;
use App\Http\Concerns\AuthorizesGuestForm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * Post-formulario de datos por invitado de un cumpleaños (#217), INDIVIDUALIZADO POR RESERVA.
 *
 * El parámetro de ruta `{reservation}` es el `OrderItem` del pack: hay **un post-form por reserva**
 * (no por pedido). Un pedido con dos cumpleaños tiene dos post-forms independientes, cada uno con su
 * propio enlace y su propia caducidad (la fecha de SU franja + 14 días).
 *
 * El cliente rellena, DESPUÉS de reservar, los datos de cada niño (nombre/alergia/observaciones/menú
 * especial, esquema data-driven `guest_fields`) más unos datos generales (`event_fields` fase
 * `postform`). Editable cuantas veces haga falta hasta el día del cumpleaños.
 *
 * Acceso (validado en CADA petición, GET y POST, en este orden para no enumerar reservas):
 *  1. firma HMAC válida del email (sin sesión) **o** usuario autenticado dueño del pedido → si no, 403;
 *  2. titular anonimizado (RGPD art. 17) → 410 (auditoría Fase 1 · P5: la supresión cierra el canal);
 *  3. la reserva debe ser un pack con post-form de un pedido PAGADO → si no, 404.
 * El estado FORM OK/NO se deriva en vivo de `guest_data` contra `quantity`
 * ({@see OrderItem::guestFormStatus}); aquí solo se persisten datos saneados en servidor (regla 12).
 */
class GuestFormController extends Controller
{
    use AuthorizesGuestForm;

    public function show(Request $request, OrderItem $reservation): View
    {
        $this->authorizeGuestFormAccess($request, $reservation);

        $type = $reservation->ticketType;
        $ageMix = app(GuestAgeMixReader::class)->for($reservation);
        $ageSurcharge = app(MixedPartySurcharge::class)->written($reservation);
        $guestRegimes = app(GuestAgeMixReader::class)->guestRegimes($reservation);

        return view('reservation.guests', [
            'order' => $reservation->order,
            'reservation' => $reservation,
            'type' => $type,
            'guestFields' => $type->guestFields(),
            'generalFields' => $type->eventFields(TicketType::EVENT_STAGE_POSTFORM),
            'rows' => $reservation->guestData(),
            'progress' => $reservation->guestFormProgress(),
            // El suplemento de fiesta MIXTA, para decírselo al cliente EN EL SITIO donde declara las
            // edades (`docs/specs/cumple-mixto.md` §12).
            //
            // ⚠️ Se le enseña lo ESCRITO en su pedido, no el veredicto derivado. Son casi siempre
            // lo mismo —al guardar se reconcilian—, pero cuando difieren (falta el producto que
            // lleva el suplemento, o el parque cambió una tarifa después) lo derivado sería una
            // promesa que su pedido no respalda. Prometer un importe que no está en su desglose es
            // peor que no decir nada; lo derivado es información para el OPERADOR y vive en el panel.
            'ageSurcharge' => $ageSurcharge,
            // Y el VEREDICTO derivado, que es lo que permite EXPLICAR el importe en vez de soltarlo
            // («2 invitados corresponden a Kids, 11,00 € por invitado, en vez de Jump, 15,00 €»).
            //
            // ⚠️ Los dos, y cada uno para lo suyo: el DINERO sale de lo escrito —es lo que su pedido
            // dice— y la EXPLICACIÓN del veredicto. Antes el aviso solo miraba lo escrito, y por eso
            // **una fiesta mixta sin cargo no decía nada**: el cliente veía la etiqueta «MIXTA» en su
            // pedido y ni una línea que la interpretase (§14, medido sobre `R-BEEL3E`).
            'ageMix' => $ageMix,
            // El régimen que le toca a CADA invitado, para el rótulo dentro de su recuadro
            // (`[owner, 2026-08-29]`, §15). Sale del mismo recorrido que el veredicto: si tuviera su
            // propia copia de la regla, una ficha podría decir «Kids» mientras el total dice otra cosa.
            'guestRegimes' => $guestRegimes,
            // Una edad SIN PRODUCTO (`#284` D6, §22.5): las fichas afectadas —que no cuentan como
            // completas— y UN texto por caso presente (por debajo · por encima · hueco), el del parque
            // si lo escribió en Ajustes, con su teléfono. Se le dice que llame: lo resuelve el parque.
            'noProductIndexes' => $reservation->guestAgesWithoutProduct(),
            'noProductNotices' => $this->noProductNotices($guestRegimes),
            // El dinero solo se mueve al guardar con TODAS las edades (`#285` §20.6): si hay cargo
            // escrito y falta alguna, se le dice que está congelado y cuántas faltan.
            'frozenMissingAges' => ($ageMix->applies && ! $ageMix->allAgesDeclared() && $ageSurcharge['cents'] > 0)
                ? $ageMix->withoutAge
                : 0,
            // SOLO LECTURA cuando la reserva ya se ha celebrado (su franja terminó): el post-form solo
            // sirve para PREPARAR la fiesta; pasada, se muestra pero no se edita. El enlace sigue
            // caducando a evento+14d (tope RGPD), pero la edición se cierra al terminar el evento.
            'readonly' => $reservation->isFinishedInPractice(),
            // Si se entró por enlace firmado (sin sesión), el POST también debe ir firmado para
            // re-autorizar; si es el dueño autenticado, basta la ruta normal (la sesión autoriza).
            // El POST hereda la MISMA caducidad que el enlace del email (A7), de ESTA reserva.
            'formAction' => $request->hasValidSignature()
                ? URL::temporarySignedRoute('reservation.guests.store', $reservation->guestFormLinkExpiresAt(), ['reservation' => $reservation])
                : route('reservation.guests.store', ['reservation' => $reservation]),
        ]);
    }

    public function store(Request $request, OrderItem $reservation): RedirectResponse
    {
        $this->authorizeGuestFormAccess($request, $reservation);

        // Reserva ya celebrada → SOLO LECTURA: no se guarda ni se re-introducen datos de menores (la UI
        // ya no ofrece editar; esto blinda un POST forjado o una pestaña vieja). Vuelve a la vista
        // read-only con un aviso. La caducidad evento+14d sigue como tope RGPD del enlace.
        if ($reservation->isFinishedInPractice()) {
            return redirect()
                ->to($this->backUrl($request, $reservation))
                ->with('status', 'guest-form-readonly');
        }

        // Qué se persiste de un formulario con datos de MENORES lo decide el dominio, no esta capa
        // (Fase 3 · paso 5): saneado contra el esquema, mezcla que preserva los datos de la fase de
        // reserva, sello de completado y rastro de auditoría, en una sola operación. La API hace
        // exactamente esta llamada, así que las dos superficies no pueden guardar cosas distintas.
        $rawGuests = $request->input('guests', []);
        $rawGeneral = $request->input('general', []);

        $reservation->submitGuestForm(
            is_array($rawGuests) ? $rawGuests : [],
            is_array($rawGeneral) ? $rawGeneral : [],
            $this->guestFormVia($request),
        );

        return redirect()
            ->to($this->backUrl($request, $reservation))
            ->with('status', 'guest-form-saved');
    }

    /**
     * Un texto por CASO presente entre las fichas sin producto, en el orden en que aparecen. Dos
     * invitados con el mismo caso comparten frase: repetirla no añade información.
     *
     * @param  array<int, array{state:string, name:?string, own:bool, reason:?string}>  $regimes
     * @return list<string>
     */
    private function noProductNotices(array $regimes): array
    {
        $reasons = [];
        foreach ($regimes as $row) {
            if ($row['state'] === GuestAgeMixReader::ROW_OUT_OF_RANGE && $row['reason'] !== null) {
                $reasons[$row['reason']] = true;
            }
        }

        return array_map(
            static fn (string $reason): string => MixedPartySettings::noProductText($reason),
            array_keys($reasons),
        );
    }

    /** Tras guardar: "Mis pedidos" si está autenticado; si vino por enlace firmado, recarga firmada. */
    private function backUrl(Request $request, OrderItem $reservation): string
    {
        if ($this->ownsGuestForm($request, $reservation)) {
            return route('account.orders');
        }

        return URL::temporarySignedRoute('reservation.guests', $reservation->guestFormLinkExpiresAt(), ['reservation' => $reservation]);
    }
}
