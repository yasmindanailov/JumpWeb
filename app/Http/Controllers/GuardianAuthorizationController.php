<?php

namespace App\Http\Controllers;

use App\Domain\Booking\Contracts\AuthorizableOrder;
use App\Domain\Booking\Contracts\AuthorizableOrders;
use App\Domain\Booking\Models\Order;
use App\Domain\Identity\Exceptions\GuardianAuthorizationExistsException;
use App\Domain\Identity\Exceptions\GuardianAuthorizationRefusedException;
use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\GuardianAuthorization;
use App\Domain\Identity\Services\GuardianAuthorizationSigner;
use App\Domain\Identity\Services\LegalDocuments;
use App\Domain\Identity\Services\WaiverAcceptance;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Platform\Services\Turnstile;
use App\Http\Concerns\AuthorizesGuardianAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function show(Request $request, Order $order): View
    {
        $this->authorizeGuardianAccess($request, $order);

        $context = app(AuthorizableOrders::class)->find((int) $order->getKey());
        abort_if($context === null, 404);

        // El texto que se va a firmar, en el idioma de quien lo lee. Sin versión publicada no hay nada
        // que aceptar y esta pantalla no existe (la maquinaria puede estar montada antes que el texto).
        $document = LegalDocuments::current(WaiverSettings::SLUG, app()->getLocale());
        abort_if($document === null, 404);

        $user = $request->user();

        return view('reservation.authorization', [
            'order' => $order,
            'context' => $context,
            'document' => $document,
            // Por qué NO se puede firmar, si es el caso. Se decide aquí solo para PINTAR: la puerta
            // que manda está en el dominio, bajo el lock.
            'blocked' => $this->blockedReason($context),
            'relationships' => Dependent::RELATIONSHIPS,
            // §4.6: con sesión, los datos del adulto vienen rellenos. ⚠️ Iniciar sesión no cambia nada
            // más: no verifica, no enlaza la cuenta y el justificante sigue siendo puntual.
            'prefill' => [
                'guardian_name' => $user?->name,
                'guardian_email' => $user?->email,
                'guardian_phone' => $user?->phone,
            ],
            'formAction' => URL::temporarySignedRoute(
                'reservation.authorization.store',
                $context->linkExpiresAt,
                ['order' => $order],
            ),
        ]);
    }

    public function store(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeGuardianAccess($request, $order);

        $context = app(AuthorizableOrders::class)->find((int) $order->getKey());
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
            return $this->back($request, $order, 'signed');
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
            return $this->back($request, $order, 'antibot');
        }

        $validator = Validator::make($request->all(), $this->rules(), $this->messages());
        if ($validator->fails()) {
            return redirect()->to($this->backUrl($request, $order))
                ->withErrors($validator)
                ->withInput();
        }

        // «La versión que el servidor SIRVIÓ» (`waiver-probatorio.md` §4.4): sin el identificador de la
        // versión vigente, la firma no queda atada a ningún texto y todo lo demás es decorado. Si el
        // texto se publicó de nuevo entre servirlo y aceptarlo, se rechaza para que vuelva a leerlo.
        $document = WaiverAcceptance::currentDocument((int) $request->input('document_id'));
        if ($document === null || ! WaiverSettings::isInternal()) {
            return $this->back($request, $order, 'stale');
        }

        $data = $validator->validated();

        try {
            $result = app(GuardianAuthorizationSigner::class)->sign(
                $order->user,
                (int) $order->getKey(),
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
            );
        } catch (GuardianAuthorizationExistsException $e) {
            // «Un niño, un papel» (§7·9): el otro progenitor ve el nombre del menor y nada más — ni
            // quién lo firmó ni cómo contactarle.
            return $this->back($request, $order, 'already', $e->minorName);
        } catch (GuardianAuthorizationRefusedException $e) {
            return $this->back($request, $order, $e->reason);
        }

        return $this->back($request, $order, 'signed', $result['authorization']->minorFullName());
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'document_id' => ['required', 'integer'],
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
    private function blockedReason(AuthorizableOrder $context): ?string
    {
        if (! $context->isPaid) {
            return GuardianAuthorizationRefusedException::REASON_NOT_PAID;
        }
        if ($context->visitFinished) {
            return GuardianAuthorizationRefusedException::REASON_CLOSED;
        }
        if (GuardianAuthorization::query()->where('order_id', $context->orderId)->count() >= $context->capacity) {
            return GuardianAuthorizationRefusedException::REASON_FULL;
        }

        return null;
    }

    private function back(Request $request, Order $order, string $status, ?string $minorName = null): RedirectResponse
    {
        return redirect()->to($this->backUrl($request, $order))
            ->with('guardian_status', $status)
            ->with('guardian_minor', $minorName);
    }

    /**
     * A dónde se vuelve: **una URL firmada de nuevo**. Quien rellena no tiene sesión, así que un
     * `back()` a secas le dejaría en un 403 con lo que acaba de escribir perdido.
     */
    private function backUrl(Request $request, Order $order): string
    {
        if ($this->ownsOrder($request, $order)) {
            return route('reservation.authorization', ['order' => $order]);
        }

        return $order->guardianAuthorizationSignedUrl();
    }
}
