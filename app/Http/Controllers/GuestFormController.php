<?php

namespace App\Http\Controllers;

use App\Domain\Booking\Contracts\GuestCountChange;
use App\Domain\Booking\Contracts\PostFormAddonView;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Services\GuestAgeMixReader;
use App\Domain\Booking\Services\GuestCountAdjuster;
use App\Domain\Booking\Services\GuestCountPolicy;
use App\Domain\Booking\Services\MixedPartySettings;
use App\Domain\Booking\Services\MixedPartySurcharge;
use App\Domain\Booking\Services\PostFormAddons;
use App\Domain\Platform\Services\DisplayTime;
use App\Domain\Platform\Services\Money;
use App\Http\Concerns\AuthorizesGuestForm;
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

    public function show(Request $request, OrderItem $reservation): View
    {
        $this->authorizeGuestFormAccess($request, $reservation);

        $type = $reservation->ticketType;
        $ageMix = app(GuestAgeMixReader::class)->for($reservation);
        $ageSurcharge = app(MixedPartySurcharge::class)->written($reservation);
        $guestRegimes = app(GuestAgeMixReader::class)->guestRegimes($reservation);
        $addons = app(PostFormAddons::class)->viewFor($reservation);

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
            // El dinero solo se mueve al guardar con TODAS las edades (`#285` §20.6): si hay dinero
            // escrito —cargo O descuento— y falta alguna, se le dice que está congelado y cuántas.
            'frozenMissingAges' => ($ageMix->applies && ! $ageMix->allAgesDeclared()
                && ($ageSurcharge['charge_cents'] > 0 || $ageSurcharge['credit_cents'] > 0))
                ? $ageMix->withoutAge
                : 0,
            // SOLO LECTURA cuando la reserva ya se ha celebrado (su franja terminó): el post-form solo
            // sirve para PREPARAR la fiesta; pasada, se muestra pero no se edita. El enlace sigue
            // caducando a evento+14d (tope RGPD), pero la edición se cierra al terminar el evento.
            'readonly' => $reservation->isFinishedInPractice(),
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
            // ⚠️ La firma la compone el DOMINIO (`guestFormSignedStoreUrl`), no esta capa: desde D14
            // toda URL firmada del post-form lleva además la VERSIÓN del enlace, y una compuesta a
            // mano aquí sería la que se queda sin ella.
            'formAction' => $request->hasValidSignature()
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

            if ($changes->blocked !== []) {
                // Se le dice, no se calla: quien creyó pedir tapas tiene que enterarse aquí y no en
                // la puerta del parque. Sus datos SÍ se guardaron, y el aviso lo separa.
                $status = collect($changes->blocked)->every(fn (array $b): bool => $b['reason'] === 'stale')
                    ? 'guest-form-stale'
                    : 'guest-form-extras-blocked';
            }
        }

        return redirect()
            ->to($this->backUrl($request, $reservation))
            ->with('status', $status);
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
}
