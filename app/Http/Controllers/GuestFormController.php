<?php

namespace App\Http\Controllers;

use App\Domain\Booking\Contracts\GuestCountChange;
use App\Domain\Booking\Contracts\PostFormAddonView;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\PartyInvitation;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Services\GuestAgeMixReader;
use App\Domain\Booking\Services\GuestCountAdjuster;
use App\Domain\Booking\Services\GuestCountPolicy;
use App\Domain\Booking\Services\MixedPartySettings;
use App\Domain\Booking\Services\MixedPartySurcharge;
use App\Domain\Booking\Services\PartyInvitations;
use App\Domain\Booking\Services\PostFormAddons;
use App\Domain\Platform\Services\Analytics\EmailUtm;
use App\Domain\Platform\Services\DisplayTime;
use App\Domain\Platform\Services\Money;
use App\Domain\Platform\Services\PublicFreeText;
use App\Http\Concerns\AuthorizesGuestForm;
use App\Http\Concerns\RecordsPartyFacts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    use RecordsPartyFacts;

    public function show(Request $request, OrderItem $reservation): View
    {
        $this->authorizeGuestFormAccess($request, $reservation);

        // La analítica de la fiesta (`specs/analitica-fiesta.md` §4.2): un hecho de la RESERVA, sin visitante.
        $this->partyFact($request, $reservation, 'guest_form_opened');

        $type = $reservation->ticketType;
        $ageMix = app(GuestAgeMixReader::class)->for($reservation);
        $ageSurcharge = app(MixedPartySurcharge::class)->written($reservation);
        $guestRegimes = app(GuestAgeMixReader::class)->guestRegimes($reservation);
        $addons = app(PostFormAddons::class)->viewFor($reservation);
        // Celebrada = solo lectura: ni se pintan propuestas ni se materializa ninguna invitación.
        $readonly = $reservation->isFinishedInPractice();
        // ⚠️ Las propuestas se piden UNA vez: cada llamada consulta, y esta pantalla tiene su
        // presupuesto de consultas medido. Las usan las dos mitades —las fichas y el bloque—.
        $proposals = ($type !== null && $type->offersGuestInvitation() && ! $readonly)
            ? app(PartyInvitations::class)->proposalsFor($reservation)
            : [];
        // Lo que PROPONEN las respuestas pendientes, ya colocado sobre sus fichas (T6·2).
        $proposed = $this->withProposals($reservation, $reservation->guestData(), $proposals);

        return view('reservation.guests', [
            'order' => $reservation->order,
            'reservation' => $reservation,
            'type' => $type,
            'guestFields' => $type->guestFields(),
            'generalFields' => $type->eventFields(TicketType::EVENT_STAGE_POSTFORM),
            'rows' => $proposed['rows'],
            // La marca de cada ficha propuesta, POR POSICIÓN: la chapa «Por la invitación», el id que
            // viaja en `adopt[]` y si esa familia contestó más de una vez.
            'proposals' => $proposed['marks'],
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
            // El dinero solo se mueve al guardar con TODAS las edades (`#285` §20.6): si hay dinero
            // escrito —cargo O descuento— y falta alguna, se le dice que está congelado y cuántas.
            'frozenMissingAges' => ($ageMix->applies && ! $ageMix->allAgesDeclared()
                && ($ageSurcharge['charge_cents'] > 0 || $ageSurcharge['credit_cents'] > 0))
                ? $ageMix->withoutAge
                : 0,
            // SOLO LECTURA cuando la reserva ya se ha celebrado (su franja terminó): el post-form solo
            // sirve para PREPARAR la fiesta; pasada, se muestra pero no se edita. El enlace sigue
            // caducando a evento+14d (tope RGPD), pero la edición se cierra al terminar el evento.
            'readonly' => $readonly,
            // Si se entró por enlace firmado (sin sesión), el POST también debe ir firmado para
            // re-autorizar; si es el dueño autenticado, basta la ruta normal (la sesión autoriza).
            // El POST hereda la MISMA caducidad que el enlace del email (A7), de ESTA reserva.
            // Los EXTRAS de venta posterior (T3 de `#413`): la lista completa —abiertos y cerrados—
            // y su total. ⚠️ El total es **el de los extras**, NO el saldo del pedido: esta página se
            // abre con un enlace que se reenvía, y el saldo es dinero del titular que hoy no enseña.
            // ⚠️ Se compone UNA vez: cada llamada tarifica los complementos, y el presupuesto de
            // consultas de esta superficie está medido y con guarda.
            'addons' => $addons,
            'extrasTotal' => Money::format(
                array_sum(array_map(fn (PostFormAddonView $a): int => $a->chargedCents, $addons)),
                $reservation->order?->currency ?? 'EUR',
            ),
            'version' => PostFormAddons::versionOf($reservation),
            // El control de invitados (`specs/invitados-en-post-form.md` §4.8, `#444`): sus límites y
            // su plazo salen de `GuestCountPolicy`, que es la MISMA fuente que revalida bajo el lock.
            // ⚠️ La pantalla no es la autoridad (`SEC-04`): esto decide qué se OFRECE.
            'guestCount' => $this->guestCountView($reservation),
            // El BLOQUE DE LA INVITACIÓN (T6·1, `specs/celebracion-e-invitacion.md` §4.7): compartir,
            // personalizar, el resumen y el plazo escrito como fecha. `null` cuando este producto no
            // ofrece invitación — y entonces no se pinta nada, ni se crea ninguna fila.
            'invitation' => $this->invitationView($request, $reservation, $proposals, $readonly),
            // ⚠️ La firma la compone el DOMINIO (`guestFormSignedStoreUrl`), no esta capa: desde D14
            // toda URL firmada del post-form lleva además la VERSIÓN del enlace, y una compuesta a
            // mano aquí sería la que se queda sin ella.
            // ⚠️ Ignorando las claves de atribución (`EmailUtm`): el enlace del correo las trae pegadas
            // DESPUÉS de la firma, y con `hasValidSignature()` a secas el formulario POSTearía a la ruta
            // de cuenta — y el padre sin sesión se quedaría fuera al enviar.
            'formAction' => $request->hasValidSignatureWhileIgnoring(EmailUtm::IGNORED_QUERY)
                ? $reservation->guestFormSignedStoreUrl()
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

        // El estado de la reserva ANTES de nuestra propia escritura: el testigo de los extras se
        // compara contra él, porque `submitGuestForm()` mueve `updated_at` en esta misma petición
        // ({@see addonsExpectedVersion}).
        $before = PostFormAddons::versionOf($reservation);

        // ❗❗❗ **LA CANTIDAD DE INVITADOS VA PRIMERO, Y EL ORDEN ES LA PROPIEDAD**
        // (`specs/invitados-en-post-form.md` §4.7·2 y §7.1·A2/A3, `#444`). Son tres reglas, y las
        // tres se pagaron por adelantado leyendo el código en vez de descubrirlas:
        //
        //  1. **Después de `$before`**: el testigo de los extras sustituye lo que el cliente vio por
        //     la versión de DESPUÉS *solo si coincide con la de antes de nuestra propia escritura*
        //     ({@see addonsExpectedVersion}). Ajustar antes de capturarlo dejaría a `$seen` fuera de
        //     sitio y **ningún guardado normal podría comprar un extra**.
        //  2. **Antes de `submitGuestForm`**: aquél sanea las fichas contra la cantidad ACTUAL, así
        //     que con el orden al revés un cliente que sube a 12 guardaría 10 fichas — el hueco
        //     original, reproducido dentro de su propio arreglo.
        //  3. **Con la reserva RE-LEÍDA en medio**: el saneo mira `$this->quantity` de la INSTANCIA
        //     que recibe, y la de esta capa sigue teniendo la cantidad vieja en memoria.
        $countChange = null;
        $desiredCount = $this->submittedGuestCount($request);
        if ($desiredCount !== null) {
            $countChange = app(GuestCountAdjuster::class)->adjust(
                $reservation,
                $desiredCount,
                $this->guestFormVia($request),
                is_string($request->input('expected_version')) ? $request->input('expected_version') : null,
            );
            $reservation = $reservation->fresh(['ticketType', 'slot', 'order', 'children']) ?? $reservation;
        }

        // Qué se persiste de un formulario con datos de MENORES lo decide el dominio, no esta capa
        // (Fase 3 · paso 5): saneado contra el esquema, mezcla que preserva los datos de la fase de
        // reserva, sello de completado y rastro de auditoría, en una sola operación. La API hace
        // exactamente esta llamada, así que las dos superficies no pueden guardar cosas distintas.
        // ⚠️ Ausente = «no lo toques»; presente = el estado completo. La distinción vive en
        // `submittedGuestFormArray()` (compartida con la API) porque el `input('guests', [])` que
        // había aquí convertía un cuerpo parcial en un BORRADO de las fichas de los menores.
        $reservation->submitGuestForm(
            $this->submittedGuestFormArray($request, 'guests'),
            $this->submittedGuestFormArray($request, 'general'),
            $this->guestFormVia($request),
        );

        // ── La ADOPCIÓN de las respuestas de la invitación (T6·2, §4.7) ───────────────────────────
        //
        // ⚠️⚠️ **Va DESPUÉS de guardar las fichas, y el orden es la regla** (lo mismo que hace la API,
        // y por eso está escrito igual en los dos sitios): adoptar marca la respuesta con la clave del
        // nombre que el anfitrión ACABA de escribir, así que antes de escribirlo no hay contra qué
        // emparejarla. Y la reconciliación va la última, porque compara contra las fichas que han
        // quedado guardadas: una respuesta adoptada cuyo nombre ya no está es una que **él quitó**.
        //
        // ⚠️ Solo se reconcilia si vinieron `guests`: sin ellas las fichas no se han tocado, y
        // recorrerlas igual descartaría respuestas por un envío que solo cambiaba las observaciones.
        //
        // ⚠️ Los ids se filtran a ESCALARES antes de convertirlos: `adopt[]` llega de un formulario
        // público, y un cuerpo forjado con `adopt[0][x]=1` reventaría el `intval` con un TypeError en
        // vez de no adoptar nada, que es lo que tiene que pasar.
        $adopt = array_filter($this->submittedGuestFormArray($request, 'adopt') ?? [], 'is_scalar');
        if ($adopt !== []) {
            app(PartyInvitations::class)->adopt($reservation, array_map(intval(...), $adopt));
        }
        if ($this->submittedGuestFormArray($request, 'guests') !== null) {
            app(PartyInvitations::class)->reconcileAdopted($reservation->fresh(['ticketType']) ?? $reservation);
        }

        // Los extras van DESPUÉS y en su propia transacción (§4.5.3): un id que dejó de ofrecerse no
        // puede tumbar el guardado de los nombres y las alergias, que es la razón de ser de esta
        // página. La no-atomicidad es deliberada, y por eso el desenlace la DICE.
        // El desenlace lo DICE: un cambio de invitados rechazado no puede irse en silencio — es
        // exactamente el modo de fallo que esta feature viene a cerrar (12 fichas para una línea de
        // 10, y dos se pierden sin que nadie avise).
        $status = ($countChange !== null && ! $countChange->applied && $countChange->reason !== GuestCountChange::REASON_NOOP)
            ? 'guest-count-'.$countChange->reason
            : 'guest-form-saved';
        $desired = $this->submittedGuestFormArray($request, 'addons');
        $extrasCents = 0;
        if ($desired !== null) {
            $fresh = $reservation->fresh(['ticketType.addons', 'order', 'slot', 'children']);
            $changes = app(PostFormAddons::class)->reconcile(
                $fresh,
                self::desiredQuantities($desired),
                $this->guestFormVia($request),
                null,
                $this->addonsExpectedVersion(
                    is_string($request->input('expected_version')) ? $request->input('expected_version') : null,
                    $before,
                    $fresh,
                ),
            );

            $extrasCents = $changes->deltaCents;

            if ($changes->blocked !== []) {
                // Se le dice, no se calla: quien creyó pedir tapas tiene que enterarse aquí y no en
                // la puerta del parque. Sus datos SÍ se guardaron, y el aviso lo separa.
                $status = collect($changes->blocked)->every(fn (array $b): bool => $b['reason'] === 'stale')
                    ? 'guest-form-stale'
                    : 'guest-form-extras-blocked';
            }
        }

        // El hecho de la RESERVA (`specs/analitica-fiesta.md` §4.2), con lo que esta petición movió: invitados
        // (solo si el ajuste se aplicó), céntimos de extras (con signo) y respuestas adoptadas. Sin nombres.
        $this->partyFact($request, $reservation, 'guest_form_submitted', [
            'guests_delta' => ($countChange !== null && $countChange->applied) ? $countChange->to - $countChange->from : 0,
            'extras_cents' => $extrasCents,
            'replies_adopted' => count($adopt),
        ]);

        return redirect()
            ->to($this->backUrl($request, $reservation))
            ->with('status', $status);
    }

    /**
     * **Personalizar la invitación** (T6·1, `specs/celebracion-e-invitacion.md` §4.7): tema, quién
     * cumple, la línea «Te invita» y si se enseña el teléfono de la cuenta.
     *
     * ⚠️⚠️ **Escribe SOLO `party_invitations` y por eso es otro POST** (§4.7, y la misma razón que la
     * API escribió en `InvitationHostController`): `order_items.updated_at` es el testigo optimista
     * de los extras, así que personalizar dentro del guardado de siempre dejaría obsoleta la página
     * abierta **por cambiar el color de una banda**.
     *
     * ⚠️ **Un texto con un enlace se rechaza y SE DICE.** El dominio no publica «paga el regalo en
     * este enlace» (§7.2·R9) y tampoco lo limpia a medias; sin este aviso el anfitrión vería su
     * campo intacto y creería que se guardó. La API puede callarlo —devuelve el recurso entero y el
     * cliente compara—; una pantalla, no.
     */
    public function updateInvitation(Request $request, OrderItem $reservation): RedirectResponse
    {
        $this->authorizeGuestFormAccess($request, $reservation);

        // Celebrada = solo lectura, igual que el formulario: la invitación de una fiesta que ya pasó
        // no se personaliza. Blinda un POST forjado o una pestaña vieja.
        if ($reservation->isFinishedInPractice()) {
            return redirect()
                ->to($this->backUrl($request, $reservation))
                ->with('status', 'guest-form-readonly');
        }

        $invitations = app(PartyInvitations::class);
        $invitation = $invitations->forReservation($reservation);

        if ($invitation === null) {
            abort(404);
        }

        // ⚠️⚠️ **`nullable` en los dos textos, y no es laxitud**: `ConvertEmptyStringsToNull` convierte
        // un campo vacío del formulario en `null` ANTES de llegar aquí, así que con `string` a secas
        // un anfitrión que borrara «Te invita» y guardara recibía un **422 y ningún cambio**. Medido:
        // el caso lo cazó en el primer intento. Lo que el dominio hace con un vacío ya está decidido
        // —es «no lo toques», porque `clean()` devuelve `null`— y eso sigue igual.
        $data = $request->validate([
            'theme' => ['sometimes', 'string', 'max:16'],
            'honoree_name' => ['sometimes', 'nullable', 'string', 'max:'.PartyInvitation::HONOREE_NAME_MAX],
            'honoree_age' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:255'],
            'host_line' => ['sometimes', 'nullable', 'string', 'max:'.PartyInvitation::HOST_LINE_MAX],
            'show_host_phone' => ['sometimes', 'boolean'],
        ]);

        // ⚠️ Se PREGUNTA por el rechazo antes de escribir, con el mismo predicado del dominio: lo que
        // se compara después no serviría, porque `clean()` además recorta y colapsa espacios y una
        // diferencia no significaría «no se admitió».
        $rejected = $this->rejectedFreeText($data);

        $invitations->personalize($invitation, $data);

        return redirect()
            ->to($this->invitationBackUrl($request, $reservation))
            ->with('status', $rejected ? 'invitation-text-rejected' : 'invitation-saved');
    }

    /**
     * **«No lo apuntes»** (T6·3, §7.2·R11): el anfitrión retira de su lista una respuesta que no
     * quiere apuntar.
     *
     * ❗❗ **Sin esto se queda ATRAPADO**: desde `#576` un «sí» pendiente es una plaza con dueño y sube
     * el suelo por debajo del cual no puede bajar el número de invitados. Es el par de esa regla, no
     * una comodidad.
     *
     * ⚠️ **Mismo desenlace aunque ya estuviera descartada**, como el 204 de la API: el gesto es
     * idempotente y dos pestañas del mismo anfitrión no tienen por qué pelearse. Distinguir «no
     * existe» de «ya estaba» sería además información sobre su propia lista que no hace falta dar.
     *
     * ⚠️ Descartar **no borra**: la respuesta vive hasta que la poda de los 14 días se la lleve (V3).
     * Lo que cambia es que deja de contar, de proponerse y de ocupar plaza.
     */
    public function dismissReply(Request $request, OrderItem $reservation): RedirectResponse
    {
        $this->authorizeGuestFormAccess($request, $reservation);

        if ($reservation->isFinishedInPractice()) {
            return redirect()
                ->to($this->invitationBackUrl($request, $reservation))
                ->with('status', 'guest-form-readonly');
        }

        // El `where` de la reserva vive dentro de `dismiss()`: una respuesta de OTRA fiesta no se toca
        // aunque su id llegue en este cuerpo.
        $reply = $request->input('reply');
        app(PartyInvitations::class)->dismiss($reservation, is_numeric($reply) ? (int) $reply : 0);

        return redirect()
            ->to($this->invitationBackUrl($request, $reservation))
            ->with('status', 'invitation-dismissed');
    }

    /**
     * **«Escribir el recordatorio»** (T6·6, §4.7): compone el texto con el enlace —y con los nombres
     * de quienes faltan **solo si el anfitrión marca la casilla**— y deja escrito que avisó.
     *
     * ❗❗ **No envía nada** (§2.2): del padre no tenemos correo y no se le pide. Lo que esta pantalla
     * puede hacer es escribirle el mensaje al anfitrión para que lo pegue donde ya repartió el enlace.
     *
     * ⚠️ El texto vuelve por la SESIÓN y no por la URL: lleva los nombres de menores de la lista del
     * anfitrión, y un `?texto=` acabaría en el historial del navegador y en cualquier referer.
     *
     * ⚠️ Escribe SOLO `party_invitations`, así que es su propio POST, como personalizar y descartar.
     */
    public function writeReminder(Request $request, OrderItem $reservation): RedirectResponse
    {
        $this->authorizeGuestFormAccess($request, $reservation);

        if ($reservation->isFinishedInPractice()) {
            return redirect()
                ->to($this->backUrl($request, $reservation))
                ->with('status', 'guest-form-readonly');
        }

        $invitations = app(PartyInvitations::class);
        $invitation = $invitations->forReservation($reservation);

        if ($invitation === null) {
            abort(404);
        }

        // La casilla, con la misma trampa que `show_host_phone`: sin marcar **no se envía**, así que
        // el valor por defecto es «sin nombres» — el que no señala a nadie.
        $withNames = $request->boolean('with_names');

        // ⚠️ El texto se compone ANTES de marcar el aviso: si la composición fallara, el anfitrión se
        // habría quedado con una fecha de aviso y sin nada que pegar.
        $text = $invitations->reminderTextFor($reservation, $withNames);

        $invitations->remind($invitation);

        return redirect()
            ->to($this->invitationBackUrl($request, $reservation))
            ->with('status', 'invitation-reminded')
            ->with('reminder_text', $text);
    }

    /**
     * ¿Alguno de los dos textos libres trae un enlace o un correo? (§7.2·R9.)
     *
     * ⚠️ Vacío **no** es rechazo: es «no lo toques», y decirle al anfitrión que no caben enlaces
     * cuando lo que hizo fue borrar una línea sería un aviso que no explica nada.
     *
     * @param  array<string, mixed>  $data
     */
    private function rejectedFreeText(array $data): bool
    {
        foreach (['honoree_name', 'host_line'] as $field) {
            $value = $data[$field] ?? null;
            if (is_string($value) && PublicFreeText::rejects($value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * **Las fichas con lo que PROPONEN las respuestas pendientes** (T6·2, §4.7 y §4.5·4).
     *
     * ⚠️⚠️ **Se prerrellenan SOLO los campos vacíos**: lo que el anfitrión escribió manda siempre, y
     * una propuesta que pisara su texto convertiría una sugerencia en una corrección. El nombre sale
     * de `child_name` —el que escribió el padre, con apellidos— y solo si su ficha no tiene ninguno.
     *
     * ⚠️ **Esto NO guarda nada.** Lo propuesto viaja en los mismos `<input>` de siempre, así que se
     * escribe cuando él pulsa Guardar y lo ha visto (V1). Por eso el medidor del servidor sigue
     * contando lo GUARDADO: es la misma honestidad que el pegado de la T2 —los nombres pegados
     * tampoco mueven el contador hasta que se guardan—.
     *
     * ⚠️ Un «no» no se pinta sobre ninguna ficha, y un «sí» **que no cabe** no tiene dónde pintarse:
     * los dos llegan con `slot_index === null` —el dominio no le da ficha a un «no»— y los dos los
     * dice el aviso de la T6·3, no una ficha inventada.
     * ▶ Aquí hubo además un `! $proposal['attending']`, y **se retiró**: el arnés enseñó que ninguna
     * prueba podía ponerlo en rojo, porque el contrato de `proposalsFor()` ya garantiza lo mismo. Una
     * guarda que nada puede tumbar es ruido, no defensa (`#704`).
     *
     * @param  list<array<string, string>>  $rows
     * @param  list<array{id: int, child_name: string, attending: bool, companion: string|null, guest_data: array<string, string>, slot_index: int|null, repeated: bool}>  $proposals
     * @return array{rows: list<array<string, string>>, marks: array<int, array{id: int, repeated: bool}>}
     */
    private function withProposals(OrderItem $reservation, array $rows, array $proposals): array
    {
        $nameKey = $reservation->ticketType?->guestNameFieldKey();
        $marks = [];

        foreach ($proposals as $proposal) {
            $index = $proposal['slot_index'];

            if ($index === null) {
                continue;
            }

            $proposed = $proposal['guest_data'];

            if ($nameKey !== null && trim((string) ($proposed[$nameKey] ?? '')) === '') {
                $proposed[$nameKey] = $proposal['child_name'];
            }

            $row = $rows[$index] ?? [];
            foreach ($proposed as $key => $value) {
                if (trim((string) ($row[$key] ?? '')) === '' && trim((string) $value) !== '') {
                    $row[$key] = (string) $value;
                }
            }

            $rows[$index] = $row;
            $marks[$index] = ['id' => $proposal['id'], 'repeated' => $proposal['repeated']];
        }

        return ['rows' => $rows, 'marks' => $marks];
    }

    /**
     * Lo que la pantalla necesita para pintar el bloque de la invitación, ya resuelto (§4.7).
     *
     * ⚠️⚠️ **La invitación NACE aquí, en el GET, y es deliberado** (§4.5·1): Web Share necesita el
     * enlace **en el mismo gesto** del usuario y un `fetch` previo pierde la activación en Safari.
     *
     * ⚠️ **Pasada la fiesta no se pinta ni se crea la fila.** El formulario entero es de solo lectura
     * y una invitación que ya no se puede repartir sería un control muerto; además, materializarla
     * escribiría una fila por cada reserva vieja que alguien abra a consultar.
     *
     * ⚠️ El PLAZO se escribe **como fecha** (canvas, turno 3a): «hasta el jue 2 oct», no un número de
     * horas que el anfitrión tenga que sumar. Sale de `GuestCountPolicy`, la misma fuente que cierra
     * las respuestas (D14), y no de una cuenta propia.
     *
     * ⚠️ **Los «no» y los que no caben viven aquí y no en las fichas** (T6·3): un «no» no se apunta en
     * ninguna parte —lo que hace es llevar a BAJAR el número de invitados (D3)— y un «sí» que ya no
     * cabe no tiene ficha donde pintarse. Los dos son avisos sobre la lista, no filas de la lista.
     *
     * @param  list<array{id: int, child_name: string, attending: bool, companion: string|null, guest_data: array<string, string>, slot_index: int|null, repeated: bool}>  $proposals
     * @return array{invitation: PartyInvitation, url: string|null, shareable: bool, replies_open: bool, deadline: string, summary: array{yes: int, no: int, pending: int}, action: string, dismiss: string, remind: string, awaiting: int, reminded_on: string, themes: list<string>, declined: list<array{id: int, child_name: string, slot_index: int|null}>, unplaced: int}|null
     */
    private function invitationView(Request $request, OrderItem $reservation, array $proposals, bool $readonly): ?array
    {
        if ($readonly) {
            return null;
        }

        $invitations = app(PartyInvitations::class);
        $invitation = $invitations->forReservation($reservation);

        if ($invitation === null) {
            return null;
        }

        $deadline = app(GuestCountPolicy::class)->deadlineFor($reservation);
        $signed = $request->hasValidSignatureWhileIgnoring(EmailUtm::IGNORED_QUERY);

        return [
            'invitation' => $invitation,
            'url' => $invitations->shareUrlFor($invitation),
            'shareable' => $invitations->isShareable($reservation, $invitation),
            'replies_open' => $invitations->repliesOpenFor($reservation),
            'deadline' => $deadline === null ? '' : DisplayTime::dayLabel($deadline),
            'summary' => $invitations->summaryFor($reservation),
            // La misma regla que `formAction`: quien entró por enlace firmado POSTea firmado, y la
            // firma la compone el DOMINIO para que lleve la versión del enlace (D14).
            'action' => $signed
                ? $reservation->invitationSignedUpdateUrl()
                : route('reservation.invitation.update', ['reservation' => $reservation]),
            'dismiss' => $signed
                ? $reservation->invitationSignedDismissUrl()
                : route('reservation.invitation.dismiss', ['reservation' => $reservation]),
            'remind' => $signed
                ? $reservation->invitationSignedRemindUrl()
                : route('reservation.invitation.remind', ['reservation' => $reservation]),
            // Quiénes faltan (T6·6): la pantalla solo necesita CUÁNTOS son —para ofrecer la casilla y
            // decir a cuántos señala—. Los nombres los pone el texto, y el texto lo compone el
            // dominio: pintarlos aquí sería una segunda copia de la misma lista.
            'awaiting' => count($invitations->awaitingNamesIn($reservation)),
            // ⏰ **`dayLabel()` NO convierte de zona** —sus llamantes le pasan un Carbon ya construido
            // en la del parque—, y `reminded_at` sale de la BD en UTC: sin este `setTimezone` un aviso
            // escrito a las 00:30 de Madrid se fecharía **el día anterior**. La trampa de `#426`, en
            // otra superficie.
            'reminded_on' => $invitation->reminded_at === null
                ? ''
                : DisplayTime::dayLabel($invitation->reminded_at->copy()->setTimezone(DisplayTime::timezone())),
            'themes' => PartyInvitation::THEMES,
            'declined' => $invitations->declinedPendingIn($reservation),
            // Los «sí» que llegaron cuando ya no quedaba ficha (§7.1·3): la carrera, dicha. No es una
            // lista de espera — la decisión vuelve al anfitrión, que es quien sabe quién va.
            'unplaced' => count(array_filter(
                $proposals,
                static fn (array $p): bool => $p['attending'] && $p['slot_index'] === null,
            )),
        ];
    }

    /**
     * `[{product_id, quantity}]` → `[productId => quantity]`. Con un id repetido gana el ÚLTIMO:
     * sumar dos valores del mismo extra convertiría un envío torpe en una compra que nadie pidió.
     *
     * @param  array<mixed>  $rows
     * @return array<int,int>
     */
    private static function desiredQuantities(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (is_array($row) && isset($row['product_id'], $row['quantity'])) {
                $out[(int) $row['product_id']] = (int) $row['quantity'];
            }
        }

        return $out;
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

    /**
     * Lo que la pantalla necesita para pintar el control de invitados, ya resuelto.
     *
     * ⚠️ **La PISTA se compone aquí y no en el Blade**: es la que dice el plazo, el techo o por qué
     * está cerrado, y una plantilla que la recomponga es una segunda copia de la regla.
     *
     * @return array{editable:bool, min:int, max:?int, locked_reason:?string, hint:string}
     */
    private function guestCountView(OrderItem $reservation): array
    {
        $policy = app(GuestCountPolicy::class);
        $reason = $policy->lockedReason($reservation);
        $deadline = $policy->deadlineFor($reservation);
        $max = $policy->maxFor($reservation);

        $hint = match ($reason) {
            GuestCountChange::REASON_CUTOFF => __('guestform.count_closed_cutoff'),
            null => $deadline === null
                ? ''
                : __('guestform.count_hint', [
                    'max' => $max ?? '—',
                    'when' => DisplayTime::dayLabel($deadline),
                ]),
            default => __('guestform.count_closed'),
        };

        return [
            'editable' => $reason === null,
            'min' => $policy->floorFor($reservation),
            'max' => $max,
            'locked_reason' => $reason,
            'hint' => $hint,
        ];
    }

    /** Tras guardar: "Mis pedidos" si está autenticado; si vino por enlace firmado, recarga firmada. */
    private function backUrl(Request $request, OrderItem $reservation): string
    {
        if ($this->ownsGuestForm($request, $reservation)) {
            return route('account.orders');
        }

        return $reservation->guestFormSignedUrl();
    }

    /**
     * Tras un gesto de la INVITACIÓN se vuelve **al formulario**, nunca a «Mis pedidos».
     *
     * ⚠️⚠️ **Es distinto de `backUrl()` a propósito, y arregla un defecto de la T6·1**: guardar el
     * formulario es terminar —de ahí que el titular autenticado acabe en su lista de pedidos—, pero
     * personalizar la invitación o retirar una respuesta son gestos **dentro** de la pantalla, y
     * echarle de ella le obligaría a volver a entrar para seguir repasando su lista. Lo vio el
     * recorrido, no un test: los casos afirmaban «redirige» sin mirar a dónde.
     */
    private function invitationBackUrl(Request $request, OrderItem $reservation): string
    {
        if ($this->ownsGuestForm($request, $reservation)) {
            return route('reservation.guests', ['reservation' => $reservation]);
        }

        return $reservation->guestFormSignedUrl();
    }
}
