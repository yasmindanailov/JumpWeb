<?php

namespace App\Http\Controllers;

use App\Domain\Booking\Contracts\AuthorizableReservations;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Services\PartyInvitations;
use App\Domain\Identity\Exceptions\GuardianAuthorizationExistsException;
use App\Domain\Identity\Exceptions\GuardianAuthorizationRefusedException;
use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\GuardianAuthorization;
use App\Domain\Identity\Services\GuardianAuthorizationSigner;
use App\Domain\Identity\Services\LegalDocuments;
use App\Domain\Identity\Services\WaiverAcceptance;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Platform\Services\Analytics\EmailUtm;
use App\Domain\Platform\Services\Turnstile;
use App\Http\Concerns\AuthorizesGuardianAuthorization;
use App\Http\Concerns\ComposesGuardianForm;
use App\Http\Concerns\RecordsPartyFacts;
use App\Http\Fiesta\Autorizacion;
use App\Http\Fiesta\Sitio;
use App\Http\Instancia\InstanceViews;
use App\Notifications\GuardianAuthorizationSigned;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
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
    use ComposesGuardianForm;
    use RecordsPartyFacts;

    /**
     * Cuándo abrió ESTA persona el justificante de ESTA reserva, en su sesión técnica: de ahí sale
     * `hours_since_open` al firmar (`specs/analitica-fiesta.md` §4.2). Es un sello de tiempo, no un dato suyo. Lo
     * pone también el RECIBO cuando pinta la firma dentro (F6a), que es donde ahora se «abre».
     */
    public const OPENED_AT_SESSION_KEY = 'analytics.authorization_opened_at.';

    public function show(Request $request, OrderItem $reservation): View
    {
        $this->authorizeGuardianAccess($request, $reservation);

        $context = app(AuthorizableReservations::class)->find((int) $reservation->getKey());
        abort_if($context === null, 404);

        // El texto que se va a firmar, en el idioma de quien lo lee. Sin versión publicada no hay nada
        // que aceptar y esta pantalla no existe (la maquinaria puede estar montada antes que el texto).
        $document = LegalDocuments::current(WaiverSettings::SLUG, app()->getLocale());
        abort_if($document === null, 404);

        $desdeLaInvitacion = $this->invitationExtras($request);

        // La analítica de la fiesta (`specs/analitica-fiesta.md` §4.2): una apertura, como hecho de la RESERVA y
        // sin visitante; `via` dice si llegó desde su respuesta a la invitación o por el enlace repartido.
        $this->partyFact($request, $reservation, 'authorization_opened', [
            'via' => isset($desdeLaInvitacion['invitation_reply_id']) ? 'invitation' : 'link',
        ]);
        $request->session()->put(self::OPENED_AT_SESSION_KEY.$reservation->getKey(), now()->getTimestamp());

        // El modelo de página (`App\Http\Fiesta\Autorizacion`, T3 de `fiesta-sistema-nuevo.md`): la vista lee SOLO `$m`,
        // y el banco pinta lo mismo con los datos del diseño. Lo que necesita el formulario —el bloqueo, las
        // relaciones, lo que llega desde la invitación, el prellenado y los menores a cargo, la URL firmada del envío y
        // lo que vuelve por la sesión— lo compone `ComposesGuardianForm`, la misma fuente que el recibo (F6a).
        $m = Autorizacion::componer([
            'reservation' => $reservation,
            'context' => $context,
            'document' => $document,
            // La invitación de la fiesta, si la hay: su tema pinta la página y quien cumple da el titular.
            'invitation' => app(PartyInvitations::class)->existingFor($reservation),
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
            // ── Lo que llega DESDE la invitación digital (§4.5·7, `DECISIONES #703`) viaja DENTRO de la firma: la
            // respuesta a la que pertenece (para que el firmador no le cobre una plaza que ya tiene dueño, `#576`) y el
            // nombre del niño ENTERO, sin partirlo (`#236`). ⚠️ `guardian_name` se prellena con el nombre COMPLETO de la
            // cuenta y el apellido queda vacío: DEFECTO ASUMIDO (partir por el primer espacio fabricaría un apellido en una
            // prueba legal). Con sesión, el menor se ELIGE copiando el dato, sin enlazar la cuenta con la firma (§4.3).
        ] + $this->guardianFormInputs($request, $reservation, $context, $desdeLaInvitacion), Sitio::datos());

        return view('fiesta.autorizacion', ['m' => $m, 'hojas' => InstanceViews::hojas('fiesta')]);
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
                    // `#745`: el adulto escribe «tu nombre y apellidos» en UNA casilla (el brief); el apellido queda
                    // vacío, como ya pasaba con el prellenado de la cuenta. Quien aún mande los dos, se le guardan.
                    'guardian_surname' => (string) ($data['guardian_surname'] ?? ''),
                    'guardian_relationship' => $data['guardian_relationship'],
                    'guardian_email' => $data['guardian_email'] ?? null,
                    'guardian_phone' => $data['guardian_phone'],
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

        // El hecho de la RESERVA (`specs/analitica-fiesta.md` §4.2), solo cuando la autorización se ACABA de
        // crear: el reenvío del mismo padre no es una firma nueva. Las horas desde que abrió la hoja salen
        // de su sesión técnica; sin apertura en esta sesión, sin dato.
        if ($result['created']) {
            $opened = $request->session()->pull(self::OPENED_AT_SESSION_KEY.$reservation->getKey());
            $this->partyFact($request, $reservation, 'authorization_signed', [
                'via' => isset($data['invitation_reply_id']) ? 'invitation' : 'link',
                'hours_since_open' => is_int($opened) ? round((now()->getTimestamp() - $opened) / 3600, 2) : null,
            ]);
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
        // Con quién firmó, por flash, para el Listo del recibo (el diseño: «Firmada» con nombre · teléfono debajo). Y
        // «firmada» con la respuesta de ESE recibo (F6a): en un REENVÍO el dominio devuelve la autorización que ya existía
        // —atada, si lo estaba, a otra respuesta del mismo niño— y el recibo, que pregunta por la atadura, volvía a pintar
        // el formulario vacío sin decir nada. La página de la autorización ya enseña el Listo en ese caso; el recibo, igual.
        $recibo = $this->receiptUrl($request, $reservation);
        if ($recibo !== null) {
            return redirect()->to($recibo.'#inv-h-aut')
                ->with('guardian_status', 'signed')
                ->with('guardian_reply', (int) $request->query('invitation_reply_id'))
                ->with('guardian_minor', $result['authorization']->minorFullName())
                ->with('guardian_signer', trim($result['authorization']->guardianFullName().' · '.(string) $result['authorization']->guardian_phone, ' ·'));
        }

        // El Listo del brief nombra al niño y a quien firma (nombre · teléfono): lo que acaba de escribir, por flash.
        return $this->back(
            $request,
            $reservation,
            'signed',
            $result['authorization']->minorFullName(),
            trim($result['authorization']->guardianFullName().' · '.(string) $result['authorization']->guardian_phone, ' ·'),
        );
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

            // `#745` (T3 del sistema nuevo): «tu nombre y apellidos» en UNA casilla —el apellido aparte ya no se pide,
            // pero se admite— y el TELÉFONO es obligatorio, como en el brief: es lo que permite localizar al padre el
            // día de la visita.
            'guardian_name' => ['required', 'string', 'max:'.GuardianAuthorization::NAME_MAX],
            'guardian_surname' => ['nullable', 'string', 'max:'.GuardianAuthorization::SURNAME_MAX],
            'guardian_relationship' => ['required', 'string', 'in:'.implode(',', Dependent::RELATIONSHIPS)],
            'guardian_email' => ['nullable', 'email:filter', 'max:'.GuardianAuthorization::EMAIL_MAX],
            'guardian_phone' => ['required', 'string', 'max:'.GuardianAuthorization::PHONE_MAX],
        ];
    }

    /**
     * Los errores, con las palabras del brief (`fiesta.firma.*`, `fiesta.autorizacion.*`): se dicen al pintar, junto a
     * su campo, y nunca dicen si otro nombre ya firmó.
     *
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'accept_waiver.accepted' => __('fiesta.firma.err_casilla'),
            'minor_name.required' => __('fiesta.autorizacion.err_nino_nombre'),
            'minor_surname.required' => __('fiesta.autorizacion.err_nino_apellidos'),
            'guardian_name.required' => __('fiesta.firma.err_nombre'),
            'guardian_phone.required' => __('fiesta.firma.err_tel'),
            // Los dos extremos de la fecha dicen cosas distintas y merecen frases distintas: una fecha
            // futura es una errata; una de hace veinte años dice que esa persona ya es adulta.
            'minor_born_on.before' => __('guardian.errors.born_on_future'),
            'minor_born_on.after' => __('guardian.errors.born_on_adult'),
        ];
    }

    private function back(Request $request, OrderItem $reservation, string $status, ?string $minorName = null, ?string $signer = null): RedirectResponse
    {
        return redirect()->to($this->backUrl($request, $reservation))
            ->with('guardian_status', $status)
            ->with('guardian_minor', $minorName)
            ->with('guardian_signer', $signer);
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
        // F6a: quien firma DENTRO de su recibo vuelve a su recibo —con el error junto a su campo o con el desenlace—, a
        // la sección de la autorización. Solo si lo dice la FIRMA del enlace (`desde=recibo` va dentro del HMAC) y el
        // recibo se puede emitir por ella ({@see receiptUrl()}); si no, a la página de la autorización, como siempre.
        if ($request->query('desde') === 'recibo' && ($recibo = $this->receiptUrl($request, $reservation)) !== null) {
            return $recibo.'#inv-h-aut';
        }

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
