<?php

namespace App\Http\Controllers;

use App\Domain\Booking\Contracts\AuthorizableReservation;
use App\Domain\Booking\Contracts\AuthorizableReservations;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Services\PartyInvitations;
use App\Domain\Identity\Exceptions\GuardianAuthorizationExistsException;
use App\Domain\Identity\Exceptions\GuardianAuthorizationRefusedException;
use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\GuardianAuthorization;
use App\Domain\Identity\Services\DependentRegistry;
use App\Domain\Identity\Services\GuardianAuthorizationSigner;
use App\Domain\Identity\Services\GuardianPlaces;
use App\Domain\Identity\Services\LegalDocuments;
use App\Domain\Identity\Services\WaiverAcceptance;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Platform\Services\Analytics\EmailUtm;
use App\Domain\Platform\Services\Turnstile;
use App\Http\Concerns\AuthorizesGuardianAuthorization;
use App\Notifications\GuardianAuthorizationSigned;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * El JUSTIFICANTE de un menor INVITADO a una reserva — «waiver offshore»
 * (`docs/specs/waiver-por-reserva.md` §4.6, §4.7; tanda T2).
 *
 * Una **hoja en blanco** (`[DECIDIDO owner]` §7·2): quien reservó reparte UN enlace y cada padre que
 * lo abre rellena SUS datos y los de SU hijo. **Nadie ve lo que han escrito los demás** — la pantalla
 * no lista nada, solo escribe, y por eso repartir este enlace no es una fuga como sí lo sería
 * repartir el del post-form, que enseña los datos de todos los invitados.
 *
 * ⚠️ Es la superficie **más expuesta** del producto: pública, sin sesión y que **crea personas**. Las
 * cuatro defensas de §4.7 no se sustituyen entre sí —Turnstile, límite por IP en la ruta, tope por
 * pedido y la caducidad de la firma— y la que decide sigue siendo la última: **el dominio re-comprueba
 * las tres puertas bajo el lock** (`GuardianAuthorizationSigner`), porque entre pintar y enviar puede
 * pasar la visita, cancelarse el pedido o llenarse el cupo.
 *
 * ⚠️⚠️ **La validación NO usa `$request->validate()`**: ése redirige a `url()->previous()`, que aquí
 * sale del `Referer` del navegador. El enlace de vuelta tiene que ser una URL **firmada** que este
 * controlador construye, porque quien rellena no tiene sesión con la que re-autorizarse.
 */
class GuardianAuthorizationController extends Controller
{
    use AuthorizesGuardianAuthorization;

    public function show(Request $request, OrderItem $reservation): View
    {
        $this->authorizeGuardianAccess($request, $reservation);

        $context = app(AuthorizableReservations::class)->find((int) $reservation->getKey());
        abort_if($context === null, 404);

        // El texto que se va a firmar, en el idioma de quien lo lee. Sin versión publicada no hay nada
        // que aceptar y esta pantalla no existe (la maquinaria puede estar montada antes que el texto).
        $document = LegalDocuments::current(WaiverSettings::SLUG, app()->getLocale());
        abort_if($document === null, 404);

        $user = $request->user();
        $desdeLaInvitacion = $this->invitationExtras($request);

        return view('reservation.authorization', [
            'reservation' => $reservation,
            'context' => $context,
            'document' => $document,
            // Por qué NO se puede firmar, si es el caso. Se decide aquí solo para PINTAR: la puerta
            // que manda está en el dominio, bajo el lock.
            'blocked' => $this->blockedReason($context),
            'relationships' => Dependent::RELATIONSHIPS,
            // §12.4 (`[DECIDIDO owner, 2026-09-01]`) — **QUIÉN RESPONDE del menor durante la visita**.
            // Un padre que firma esto está confiando a su hijo a un adulto que no es él, y hasta ahora
            // la pantalla no decía ni quién era.
            //
            // ⚠️⚠️ **Nombre y TELÉFONO, nunca el correo.** El enlace lo reparte el propio responsable
            // por WhatsApp a gente que no conocemos: su buzón no tiene por qué viajar con él. El
            // teléfono sí, porque es lo que permite localizarle el día de la visita — que es
            // exactamente para lo que un padre lo quiere.
            //
            // ⚠️ **«Apellidos del responsable» NO EXISTE y no es una omisión de esta pantalla**:
            // `users` tiene UNA sola columna `name` (verificado sobre el esquema). Partirla por el
            // primer espacio sería fabricar un apellido en una pantalla que acompaña a una prueba
            // legal.
            'responsible' => [
                'name' => (string) ($reservation->order?->user?->name ?? ''),
                'phone' => (string) ($reservation->order?->user?->phone ?? ''),
            ],
            // ── Lo que llega DESDE la invitación digital (§4.5·7, `DECISIONES #703`) ──
            //
            // Quien elige «lo dejo y me voy» en su recibo aterriza aquí con dos cosas atadas **dentro
            // de la firma**: la respuesta a la que pertenece —para que el firmador no le cobre una
            // plaza que ya tiene dueño (`#576`)— y el nombre del niño, que ya escribió una vez.
            //
            // ⚠️⚠️ **El nombre entra ENTERO en el primer campo y NO se parte** (`#236`): partirlo por
            // el primer espacio fabricaría un apellido en una pantalla que acompaña a una prueba
            // legal, que es justo el defecto asumido que la nota de abajo describe para el adulto.
            // Aquí no hay que asumirlo: viene de un campo que pedía «nombre y apellidos».
            //
            // ⚠️ Llegan por la QUERY FIRMADA, así que no se pueden forjar sin romper el HMAC. Aun así
            // el id **no se cree**: quien decide si esa respuesta es de esta reserva es el contrato,
            // dentro del firmador.
            'fromInvitation' => [
                'reply_id' => $desdeLaInvitacion['invitation_reply_id'] ?? null,
                'minor' => $desdeLaInvitacion['minor'] ?? '',
            ],
            // §4.6: con sesión, los datos del adulto vienen rellenos. ⚠️ Iniciar sesión no cambia nada
            // más: no verifica, no enlaza la cuenta y el justificante sigue siendo puntual.
            //
            // ⚠️ **`guardian_name` recibe el nombre COMPLETO de la cuenta y el apellido queda vacío, y
            // eso es un DEFECTO ASUMIDO, no un descuido**: el formulario parte nombre y apellidos en
            // dos campos y la cuenta no los tiene partidos. Rellenar los dos partiendo por el primer
            // espacio acertaría con «Ana López» y fallaría con «María del Carmen Ruiz Gil» — y quien
            // firma no suele revisar lo que ya viene puesto. Se prellena lo que se sabe y se deja el
            // resto a la persona.
            'prefill' => [
                'guardian_name' => $user?->name,
                'guardian_email' => $user?->email,
                'guardian_phone' => $user?->phone,
            ],
            // §12.5 — **con sesión, el menor se ELIGE en vez de teclearse.** `Dependent` tiene
            // exactamente los cuatro campos que este formulario pide (nombre, apellidos, fecha de
            // nacimiento y la relación con quien firma), así que un padre registrado rellena el bloque
            // entero de un clic.
            //
            // ⚠️ **Esto NO enlaza la cuenta con la firma** (§4.3 lo prohíbe: una columna `ON DELETE
            // SET NULL` no puede estar dentro de un hash que se verifica). Lo que se hace es COPIAR el
            // dato, exactamente como el prellenado del adulto de aquí arriba. El justificante sigue
            // siendo puntual y la prueba, la misma.
            //
            // ⚠️ Solo los que HOY son menores: un mayor de edad firma por sí mismo, y ofrecerlo aquí
            // llevaría a un rechazo del validador con el nombre ya puesto.
            'dependents' => $user === null ? [] : app(DependentRegistry::class)
                ->activeFor($user)
                ->filter(fn (Dependent $d): bool => $d->isMinor())
                ->map(fn (Dependent $d): array => [
                    'id' => (int) $d->getKey(),
                    'name' => (string) $d->name,
                    'surname' => (string) $d->surname,
                    'born_on' => $d->born_on?->toDateString(),
                    'relationship' => (string) $d->relationship,
                    'label' => $d->fullName(),
                ])
                ->values()
                ->all(),
            // ❗❗ **Los extras de la invitación viajan también en la firma del POST** (`#704`, §10.6):
            // hasta aquí solo iban en el `GET`, y el `invitation_reply_id` llegaba al envío por un campo
            // oculto del CUERPO. Para ATAR la firma eso basta —el dominio lo contrasta con el contrato y
            // no se fía—, pero para decidir **a dónde se vuelve** no: una URL de recibo es una credencial
            // de dos horas sobre los datos de un menor y no se emite a partir de un número que cualquiera
            // puede escribir. Dentro del HMAC, no se puede.
            'formAction' => URL::temporarySignedRoute(
                'reservation.authorization.store',
                $context->linkExpiresAt,
                ['reservation' => $reservation] + $desdeLaInvitacion,
            ),
        ]);
    }

    public function store(Request $request, OrderItem $reservation): RedirectResponse
    {
        $this->authorizeGuardianAccess($request, $reservation);

        $context = app(AuthorizableReservations::class)->find((int) $reservation->getKey());
        abort_if($context === null, 404);

        // Anti-spam: HONEYPOT. Un campo oculto que una persona no ve y que un bot rellena. Aquí sí se
        // responde como si todo fuera bien y no se escribe nada: **un campo invisible relleno es señal
        // de bot y de nada más**, así que callar no engaña a ninguna persona y no le dice al bot qué
        // le delató.
        //
        // ⚠️ El campo NO se llama `website` —como en `/contacto`— a propósito: `website` mapea al tipo
        // de autocompletado `url` del navegador, y un gestor de contraseñas puede rellenarlo a una
        // persona real. En un formulario de contacto eso cuesta un mensaje; aquí costaría una prueba
        // legal que su firmante cree tener.
        if (filled($request->input('contact_ref'))) {
            return $this->back($request, $reservation, 'signed');
        }

        // Anti-bot TURNSTILE (`SEC-06`): no-op sin claves configuradas.
        //
        // ⚠️⚠️ **Y aquí NO se calla, a diferencia de `/contacto` y del alta.** Copiar aquel patrón fue
        // un defecto REAL de esta tanda, encontrado en navegador: sin token —widget bloqueado por una
        // extensión, red inestable, JS caído— el formulario **no escribía nada y decía «Listo»**. Un
        // mensaje de contacto perdido es barato; un padre que cree tener firmada la autorización de su
        // hijo y no la tiene se entera **en la puerta del parque**. Turnstile falla a personas, no solo
        // a bots, y por eso su fallo se DICE. La asimetría con el honeypot es deliberada.
        if (! Turnstile::verify((string) $request->input('cf-turnstile-response'), (string) $request->ip())) {
            return $this->back($request, $reservation, 'antibot');
        }

        $validator = Validator::make($request->all(), $this->rules(), $this->messages());
        if ($validator->fails()) {
            return redirect()->to($this->backUrl($request, $reservation))
                ->withErrors($validator)
                ->withInput();
        }

        // «La versión que el servidor SIRVIÓ» (`waiver-probatorio.md` §4.4): sin el identificador de la
        // versión vigente, la firma no queda atada a ningún texto y todo lo demás es decorado. Si el
        // texto se publicó de nuevo entre servirlo y aceptarlo, se rechaza para que vuelva a leerlo.
        $document = WaiverAcceptance::currentDocument((int) $request->input('document_id'));
        if ($document === null || ! WaiverSettings::isInternal()) {
            return $this->back($request, $reservation, 'stale');
        }

        $data = $validator->validated();

        try {
            $result = app(GuardianAuthorizationSigner::class)->sign(
                $reservation->order->user,
                (int) $reservation->getKey(),
                $document,
                [
                    'minor_name' => $data['minor_name'],
                    'minor_surname' => $data['minor_surname'],
                    'minor_born_on' => $data['minor_born_on'],
                    'guardian_name' => $data['guardian_name'],
                    'guardian_surname' => $data['guardian_surname'],
                    'guardian_relationship' => $data['guardian_relationship'],
                    'guardian_email' => $data['guardian_email'] ?? null,
                    'guardian_phone' => $data['guardian_phone'] ?? null,
                ],
                WaiverSignatureRequest::web($request->ip(), (string) $request->userAgent()),
                // «Lo dejo y me voy» (§4.5·7, `#576`): el padre llega desde su respuesta a la
                // invitación. ⚠️ **No se cree**: el firmador lo contrasta con el contrato de Booking y,
                // si no es un «sí» vivo de ESTA reserva, lo ignora y aplica el tope como siempre. Por
                // eso puede venir del formulario sin ser una credencial.
                isset($data['invitation_reply_id']) ? (int) $data['invitation_reply_id'] : null,
            );
        } catch (GuardianAuthorizationExistsException $e) {
            // «Un niño, un papel» (§7·9): el otro progenitor ve el nombre del menor y nada más — ni
            // quién lo firmó ni cómo contactarle.
            return $this->back($request, $reservation, 'already', $e->minorName);
        } catch (GuardianAuthorizationRefusedException $e) {
            return $this->back($request, $reservation, $e->reason);
        }

        // La COPIA para quien firma (§4.15, `[DECIDIDO owner]` §7·7), **fuera de la transacción y
        // solo si dejó correo**. Va aquí y no dentro del firmador a propósito: un fallo del correo
        // no puede tumbar una firma YA ESCRITA — la prueba existe, el acuse es una cortesía. La cola
        // se encarga del reintento.
        //
        // ⚠️ Solo cuando la autorización se ACABA de crear: un reenvío del mismo padre no vuelve a
        // mandarle el PDF, y el segundo progenitor nunca llega aquí (se para en «un niño, un papel»).
        if ($result['created'] && $result['authorization']->guardian_email !== null) {
            Notification::route('mail', $result['authorization']->guardian_email)
                ->notify(new GuardianAuthorizationSigned($result['signature']));
        }

        // ❗❗ **El flujo se CIERRA donde empezó** (`#704`, §10.6·D): quien llegó desde su invitación
        // vuelve a SU recibo, y allí ve que ya está firmado (§10.6·C). Antes se quedaba en el
        // justificante mirando la misma hoja que acababa de enviar.
        //
        // ⚠️ Solo en el ÉXITO. Un formulario rechazado vuelve al formulario con lo que escribió: sacarle
        // de la pantalla del error le dejaría sin saber qué corregir.
        $recibo = $this->receiptUrl($request, $reservation);
        if ($recibo !== null) {
            return redirect()->to($recibo);
        }

        return $this->back($request, $reservation, 'signed', $result['authorization']->minorFullName());
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'document_id' => ['required', 'integer'],
            // La respuesta de la invitación desde la que se llega (§4.5·7). Opcional: el justificante
            // se firma igual sin ella. No lleva `exists:` a propósito — quien decide si vale es el
            // dominio, contra la reserva, y un 422 aquí le diría a un desconocido si ese id existe.
            'invitation_reply_id' => ['nullable', 'integer', 'min:1'],
            // Casilla SEPARADA y desmarcada por defecto (§4.4 del subsistema): no se da por aceptado
            // por el hecho de enviar el formulario.
            'accept_waiver' => ['accepted'],

            'minor_name' => ['required', 'string', 'max:'.GuardianAuthorization::NAME_MAX],
            'minor_surname' => ['required', 'string', 'max:'.GuardianAuthorization::SURNAME_MAX],
            // ⚠️ Tiene que ser MENOR, y se decide como en el resto del subsistema: sobre la edad de
            // HOY, igual que `WaiverSigner` con un menor a cargo (`$dependent->isMinor()`). Un adulto
            // no necesita que nadie le autorice: firma por sí mismo, y este documento no es el suyo.
            'minor_born_on' => [
                'required', 'date',
                'before:today',
                'after:'.now()->subYears(Dependent::ADULT_AGE)->toDateString(),
            ],

            'guardian_name' => ['required', 'string', 'max:'.GuardianAuthorization::NAME_MAX],
            'guardian_surname' => ['required', 'string', 'max:'.GuardianAuthorization::SURNAME_MAX],
            'guardian_relationship' => ['required', 'string', 'in:'.implode(',', Dependent::RELATIONSHIPS)],
            'guardian_email' => ['nullable', 'email:filter', 'max:'.GuardianAuthorization::EMAIL_MAX],
            'guardian_phone' => ['nullable', 'string', 'max:'.GuardianAuthorization::PHONE_MAX],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'accept_waiver.accepted' => __('guardian.errors.accept_waiver'),
            // Los dos extremos de la fecha dicen cosas distintas y merecen frases distintas: una fecha
            // futura es una errata; una de hace veinte años dice que esa persona ya es adulta.
            'minor_born_on.before' => __('guardian.errors.born_on_future'),
            'minor_born_on.after' => __('guardian.errors.born_on_adult'),
        ];
    }

    /** Por qué no se puede firmar, para PINTARLO. La puerta que manda vive en el dominio. */
    private function blockedReason(AuthorizableReservation $context): ?string
    {
        if (! $context->isPaid) {
            return GuardianAuthorizationRefusedException::REASON_NOT_PAID;
        }
        if ($context->visitFinished) {
            return GuardianAuthorizationRefusedException::REASON_CLOSED;
        }
        // Las plazas LIBRES, no la cantidad: descuenta los menores a cargo ya asignados y los
        // justificantes ya firmados (`GuardianPlaces`). El owner compró UNA entrada, se la asignó a
        // su hija y la pantalla seguía ofreciendo firmar.
        if (app(GuardianPlaces::class)->freeIn($context) < 1) {
            return GuardianAuthorizationRefusedException::REASON_FULL;
        }

        return null;
    }

    private function back(Request $request, OrderItem $reservation, string $status, ?string $minorName = null): RedirectResponse
    {
        return redirect()->to($this->backUrl($request, $reservation))
            ->with('guardian_status', $status)
            ->with('guardian_minor', $minorName);
    }

    /**
     * A dónde se vuelve: **una URL firmada de nuevo**. Quien rellena no tiene sesión, así que un
     * `back()` a secas le dejaría en un 403 con lo que acaba de escribir perdido.
     *
     * ❗❗ **Y con los extras de la invitación dentro** (`#704`, §10.6). Sin ellos, un padre que se
     * equivocaba en la fecha de nacimiento volvía a un formulario **sin la atadura y sin el nombre**:
     * su segundo intento ya no iba atado a la respuesta, así que **cobraba plaza** y con la lista llena
     * acababa en «no quedan plazas» — justo el fallo que `#576` existe para impedir. Medido antes de
     * arreglarlo, con la pantalla real.
     */
    private function backUrl(Request $request, OrderItem $reservation): string
    {
        $extras = $this->invitationExtras($request);

        if ($this->ownsOrder($request, $reservation)) {
            return route('reservation.authorization', ['reservation' => $reservation] + $extras);
        }

        return $reservation->guardianAuthorizationSignedUrl($extras);
    }

    /**
     * **A dónde se vuelve cuando el padre llegó desde su invitación** (`#704`, §10.6·D): a SU recibo,
     * que es donde estaba. Hasta aquí se le devolvía al justificante, así que se quedaba mirando la
     * misma hoja que acababa de enviar y sin saber si había servido de algo.
     *
     * ⚠️⚠️ **Solo por la FIRMA del enlace, nunca por el cuerpo.** El recibo abre los datos de un menor
     * durante dos horas: emitirlo a partir del `invitation_reply_id` que manda el navegador le daría a
     * cualquiera con un enlace de firma el recibo del hijo de otro. Dentro del HMAC no se puede forjar,
     * y el que hay dentro del HMAC lo puso esta casa al pintar el recibo de ESA respuesta.
     *
     * ⚠️ Al titular no se le emite: tiene cuenta y su sitio es el panel, no una credencial temporal
     * pensada para un adulto sin sesión.
     */
    private function receiptUrl(Request $request, OrderItem $reservation): ?string
    {
        // Las claves de atribución no cuentan para la firma (`EmailUtm`): el correo las pega DESPUÉS de firmar.
        if (! $request->hasValidSignatureWhileIgnoring(EmailUtm::IGNORED_QUERY)) {
            return null;
        }

        $replyId = (int) $request->query('invitation_reply_id', 0);

        return $replyId > 0
            ? app(PartyInvitations::class)->receiptUrlForReplyIn($replyId, (int) $reservation->getKey())
            : null;
    }

    /**
     * Lo que trae la URL desde la invitación —la respuesta y el nombre del menor, ENTERO y sin partir
     * (`#236`)— para volver a ponerlo en la siguiente. Es lo que hace que un formulario rechazado
     * conserve la atadura.
     *
     * ⚠️ **Aquí no se re-comprueba quién pregunta, y no es un descuido**: a estos métodos solo se llega
     * después de `authorizeGuardianAccess()`, que ya exige firma válida **o** ser el titular. Repetirlo
     * añadiría una rama que ninguna prueba puede poner en rojo, y una guarda que no puede morder es
     * ruido. Lo que sí se comprueba aparte es la emisión del RECIBO ({@see receiptUrl()}), porque eso
     * **no** es volver a una pantalla: es entregar una credencial.
     *
     * @return array{invitation_reply_id?: int, minor?: string}
     */
    private function invitationExtras(Request $request): array
    {
        $extras = [];

        if (($id = (int) $request->query('invitation_reply_id', 0)) > 0) {
            $extras['invitation_reply_id'] = $id;
        }
        if (($minor = trim((string) $request->query('minor', ''))) !== '') {
            $extras['minor'] = $minor;
        }

        return $extras;
    }
}
