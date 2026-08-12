<?php

namespace App\Http\Controllers;

use App\Domain\Platform\Services\AuditLogger;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TicketType;
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
    public function show(Request $request, OrderItem $reservation): View
    {
        $this->authorizeAccess($request, $reservation);

        $type = $reservation->ticketType;

        return view('reservation.guests', [
            'order' => $reservation->order,
            'reservation' => $reservation,
            'type' => $type,
            'guestFields' => $type->guestFields(),
            'generalFields' => $type->eventFields(TicketType::EVENT_STAGE_POSTFORM),
            'rows' => $reservation->guestData(),
            'progress' => $reservation->guestFormProgress(),
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
        $this->authorizeAccess($request, $reservation);

        // Reserva ya celebrada → SOLO LECTURA: no se guarda ni se re-introducen datos de menores (la UI
        // ya no ofrece editar; esto blinda un POST forjado o una pestaña vieja). Vuelve a la vista
        // read-only con un aviso. La caducidad evento+14d sigue como tope RGPD del enlace.
        if ($reservation->isFinishedInPractice()) {
            return redirect()
                ->to($this->backUrl($request, $reservation))
                ->with('status', 'guest-form-readonly');
        }

        $type = $reservation->ticketType;

        // Datos por-niño: saneados contra N = cantidad ACTUAL de invitados de ESTA reserva (regla 12).
        $rawGuests = $request->input('guests', []);
        $guestData = $type->sanitizeGuestData(is_array($rawGuests) ? $rawGuests : [], (int) $reservation->quantity);

        // Datos generales del post-form (event_fields fase postform): se MEZCLAN en `event_data`.
        // Conservamos TODO lo ya guardado SALVO los campos postform actuales (que se reemplazan por
        // el envío saneado): así no se pierden los datos de la fase de reserva —aunque el esquema
        // haya cambiado entre reservar y rellenar— y, a la vez, vaciar un campo postform sí lo borra.
        $rawGeneral = $request->input('general', []);
        $postformData = $type->sanitizeEventData(is_array($rawGeneral) ? $rawGeneral : [], TicketType::EVENT_STAGE_POSTFORM);
        $postformKeys = array_column($type->eventFields(TicketType::EVENT_STAGE_POSTFORM), 'key');
        $preserved = array_diff_key($reservation->event_data ?? [], array_flip($postformKeys));

        $reservation->forceFill([
            'guest_data' => $guestData,
            'event_data' => array_merge($preserved, $postformData),
        ])->save();

        // Sello de "completado" solo si de verdad lo está (auditoría; el estado es derivado).
        if ($reservation->isGuestFormComplete()) {
            $reservation->markGuestFormCompleted();
        }

        AuditLogger::log('orders.guest_form_submitted', $reservation->order, [
            'order_code' => $reservation->order->code,
            'order_item_id' => $reservation->id,
            'via' => $request->hasValidSignature() ? 'signed_link' : 'account',
        ]);

        return redirect()
            ->to($this->backUrl($request, $reservation))
            ->with('status', 'guest-form-saved');
    }

    /**
     * Control de acceso de UNA reserva. Orden deliberado: autorización ANTES de comprobar
     * elegibilidad/anonimización, para no filtrar a un tercero la existencia de una reserva por el
     * código de estado (no-enumeración).
     */
    private function authorizeAccess(Request $request, OrderItem $reservation): void
    {
        $user = $request->user();
        $isOwner = $user !== null && (int) ($reservation->order?->user_id ?? 0) === (int) $user->id;
        abort_unless($request->hasValidSignature() || $isOwner, 403);

        // RGPD art. 17 (auditoría Fase 1 · P5): tras anonimizar al titular, el enlace firmado NO debe
        // seguir abriendo NI re-escribiendo nombres/alergias de menores en la reserva «borrada». 410.
        abort_if($reservation->order?->user?->isAnonymized() ?? false, 410);

        // Debe ser una reserva (pack principal no cancelado con post-form) de un pedido PAGADO. Un
        // pedido pendiente/cancelado, o una entrada/complemento, no tienen post-form.
        abort_unless(
            $reservation->isGuestFormReservation() && $reservation->order?->status === Order::STATUS_PAID,
            404,
        );
    }

    /** Tras guardar: "Mis pedidos" si está autenticado; si vino por enlace firmado, recarga firmada. */
    private function backUrl(Request $request, OrderItem $reservation): string
    {
        $user = $request->user();
        if ($user !== null && (int) ($reservation->order?->user_id ?? 0) === (int) $user->id) {
            return route('account.orders');
        }

        return URL::temporarySignedRoute('reservation.guests', $reservation->guestFormLinkExpiresAt(), ['reservation' => $reservation]);
    }
}
