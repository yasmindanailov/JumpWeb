<?php

namespace App\Domain\Identity\Services;

use App\Domain\Booking\Contracts\CustomerReservations;
use App\Domain\Booking\Contracts\PendingGuestForm;
use App\Domain\Booking\Contracts\UpcomingReservation;
use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;

/**
 * Contexto de cuenta del cliente para la web pública (#221): saludo, próxima reserva, número de
 * reservas próximas y formularios de reserva (#217) pendientes. Lo consumen el icono de cuenta
 * del nav (puntito de aviso) y el bloque del sidebar de compra (avatar + sub-línea + contador).
 * Se registra como SINGLETON con memoización por usuario → una sola pasada aunque varias vistas
 * lo pidan en la misma petición.
 *
 * Módulo **Identity**. Los datos de reservas se piden al contrato
 * `App\Domain\Booking\Contracts\CustomerReservations` (Fase 2, paso 1): aquí solo queda lo de
 * Identity (el nombre) y **la única presentación que sigue siendo suya**: la URL del formulario de
 * invitados, que se compone con `route()` y por tanto es del servidor.
 *
 * ⚠️ **La etiqueta de fecha YA NO se compone aquí** (2026-08-23): la próxima reserva sale como el DTO
 * del contrato, sin formatear. El porqué, en el docblock de `for()`.
 *
 * Defensivo por diseño (convención de helpers del panel): ante cualquier fallo devuelve un
 * contexto vacío seguro. Una cortesía de UI nunca debe tumbar una página.
 */
class CustomerAccountContext
{
    /** @var array<int, array<string, mixed>> */
    private array $cache = [];

    public function __construct(private readonly CustomerReservations $reservations) {}

    /**
     * ⚠️ **`nextReservation` viaja como el DTO del contrato, SIN formatear** (2026-08-23). Antes se
     * devolvía un array con la etiqueta de día ya compuesta, y eso era un atajo que contradecía al
     * propio contrato: el docblock de `UpcomingReservation` dice que **«la FORMATEA quien la
     * muestra — el contrato no decide idioma ni formato de fecha»**, y por eso `date` viaja en
     * `Y-m-d`. Formatear aquí obligaba además a que `Http\Resources\Api\V1\AccountContextResource`
     * recompusiera la forma de `UpcomingReservationResource` en un segundo sitio, que es como dos
     * formas del mismo dato acaban divergiendo (`specs/account-context-vue.md` §4.4).
     *
     * ▶ Quien pinta la etiqueta llama a `Platform\Services\DisplayTime::dayLabel()`, que es su fuente
     * única y lo vigila `DayLabelSingleSourceTest`.
     *
     * ▶ Fase 6 · waiver (`specs/waiver-probatorio.md` §4.8): «la re-firma se pide en el siguiente
     * momento natural —compra o login—, nunca en el mostrador». Este contexto es lo que el cajón
     * repinta al conseguir sesión, así que es donde viaja **si hay que firmar y qué texto**:
     * `waiver.required` (modo interno y sin firma), `waiver.outdated` (firmado en una versión
     * anterior) y `waiver.documentId` (el vigente en el idioma de la petición, para `POST /me/waiver`).
     *
     * ▶ **`#331` — y si el correo está VERIFICADO**, porque desde esa tanda el alta suelta abre sesión
     * y el área de cuenta es donde se le pide al cliente que lo verifique. Antes no hacía falta: quien
     * llegaba aquí venía de una compra pagada (que verifica sola) o de pulsar el enlace del correo.
     *
     * ▶ **`#349` — y si le faltan las CONDICIONES**, que es el mismo tipo de hecho que `waiver.pending`
     * y por eso vive aquí: «qué le debe esta cuenta al producto antes de poder contratar». El cajón
     * repinta este contexto al conseguir sesión, así que el embudo lo tiene **sin una petición más**.
     * ⚠️ Es una PISTA para saber qué pintar, nunca la autoridad: quien decide es el servidor al crear
     * el pedido (`OrdersController`), y por eso el cliente sabe además reaccionar a su 422.
     *
     * @return array{firstName: string, emailVerified: bool, upcomingCount: int, nextReservation: ?UpcomingReservation, pendingForms: list<array{productName: string, url: string}>, pendingFormsCount: int, hasPendingForm: bool, extrasInvite: ?array{productName: string, url: string}, waiver: array{mode: string, required: bool, pending: bool, outdated: bool, documentId: ?int, dependentsPending: bool}, termsPending: bool, termsUpdated: bool, phoneMissing: bool}
     */
    /**
     * ¿Le queda algún menor ACTIVO sin firma de la versión vigente? (`#441`)
     *
     * Cubre los dos casos que el producto deja vivos: el menor declarado **antes** de que declarar
     * exigiera aceptar, y el que queda `outdated` cuando se publica un texto nuevo —que es el
     * recurrente, porque `DependentAssigner` exige firma VIGENTE y ese día los rechaza a todos—.
     *
     * ⚠️ `EXISTS` y no un recuento: la pregunta es «¿alguno?», y contar veinte menores para
     * responderla sería trabajo que nadie mira.
     */
    private function hasUnsignedDependents(User $user, LegalDocumentVersion $vigente): bool
    {
        return Dependent::query()
            ->where('user_id', $user->getKey())
            ->active()
            ->whereNotExists(function ($query) use ($user, $vigente): void {
                $query->selectRaw('1')
                    ->from('waiver_signatures')
                    ->whereColumn('waiver_signatures.subject_id', 'dependents.id')
                    ->where('waiver_signatures.subject_type', WaiverSignature::SUBJECT_DEPENDENT)
                    ->where('waiver_signatures.user_id', $user->getKey())
                    ->where('waiver_signatures.legal_document_version_id', $vigente->getKey());
            })
            ->exists();
    }

    public function for(User $user): array
    {
        return $this->cache[$user->id] ??= $this->build($user);
    }

    /** @return array<string, mixed> */
    private function build(User $user): array
    {
        $context = [
            'firstName' => $user->firstName(),
            // Fuera del `try`: es un campo del propio usuario, no una consulta que pueda fallar, y el
            // contexto de respaldo tiene que decir la verdad sobre él aunque el resto se caiga.
            'emailVerified' => $user->hasVerifiedEmail(),
            'upcomingCount' => 0,
            'nextReservation' => null,
            'pendingForms' => [],
            'pendingFormsCount' => 0,
            'extrasInvite' => null,
            'hasPendingForm' => false,
            'waiver' => ['mode' => WaiverSettings::MODE_EXTERNAL, 'required' => false, 'pending' => false, 'outdated' => false, 'documentId' => null, 'dependentsPending' => false],
            // ⚠️ **`false` es el respaldo SEGURO y no el cómodo** (`#349`): si el contexto se cae, el
            // cliente no pinta la casilla — y el pedido lo rechaza igualmente el SERVIDOR, que dice
            // qué falta. Al revés —pintarla por si acaso— se le pediría aceptar a quien ya aceptó.
            'termsPending' => false,
            'termsUpdated' => false,
            // El teléfono no está «pendiente»: está AUSENTE. El nombre lo dice, porque de él depende
            // que la pantalla de pagar pinte un campo en vez de una casilla. El valor lo resuelve
            // `CheckoutDuties` dentro del `try`; esto es el respaldo si el contexto se cae.
            'phoneMissing' => false,
        ];

        try {
            $status = WaiverStatus::for($user);
            $vigente = $status->mode === WaiverSettings::MODE_INTERNAL
                ? LegalDocuments::current(WaiverSettings::SLUG, app()->getLocale())
                : null;

            $context['waiver'] = [
                'mode' => $status->mode,
                'required' => $status->mode === WaiverSettings::MODE_INTERNAL && ! $status->signed,
                'pending' => $user->waiver_pending_document_id !== null,
                'outdated' => $status->isOutdated(),
                'documentId' => $vigente?->getKey(),
                // `#441` · **si alguno de sus MENORES sigue sin firma vigente.** Hasta hoy el índice
                // de la cuenta miraba solo el waiver del TITULAR, así que un menor sin firma no
                // generaba ningún aviso en ninguna parte: el cliente se enteraba en el embudo —al
                // intentar asignarle una entrada— o en la puerta del parque.
                //
                // ⚠️ **UNA consulta y solo en modo `interno`** (medido: el contexto pasa de 7 a 8 en
                // cada página con sesión). La alternativa evidente, `WaiverStatus::forDependents()`,
                // cuesta tres más el `SELECT` de los menores: cuatro sobre siete, en cada página, para
                // un aviso.
                // ⚠️⚠️ **La vigencia NO se redacta aquí**: sale de `$vigente`, que es el mismo
                // documento que el resto del bloque usa. Una segunda definición de «firma al día»
                // divergiría el día que alguien toque una — y aquí divergir significa avisar de algo
                // que no pasa, o callar algo que sí.
                'dependentsPending' => $vigente !== null && $this->hasUnsignedDependents($user, $vigente),
            ];

            // ⚠️ **La MISMA fuente que el controlador de pedidos** (`CheckoutDuties`): si esto y el
            // servidor respondieran por su cuenta, el cajón pintaría un campo que el servidor no pide
            // —o al revés— y nada fallaría. Aquí es una PISTA; allí es la autoridad; el criterio, uno.
            $due = app(CheckoutDuties::class)->pendingFor($user);
            $context['termsPending'] = $due['terms'];
            $context['termsUpdated'] = $due['updated'];
            $context['phoneMissing'] = $due['phone'];

            $upcoming = $this->reservations->upcomingFor((int) $user->id);
            $context['upcomingCount'] = count($upcoming);
            $context['nextReservation'] = $upcoming[0] ?? null;

            $notices = $this->reservations->guestFormNoticesFor((int) $user->id);
            $link = fn (PendingGuestForm $form): array => [
                'productName' => $form->productName,
                'url' => route('reservation.guests', $form->reservationId),
            ];

            $pending = array_map($link, $notices->pending);
            $context['pendingForms'] = $pending;
            $context['pendingFormsCount'] = count($pending);
            $context['hasPendingForm'] = $pending !== [];
            // D15 · la INVITACIÓN a añadir extras, que es otra cosa que la deuda de arriba: sin
            // ella el aviso de esta tarjeta **muere en cuanto el cliente completa las fichas**, que
            // es exactamente cuando le quedan extras por elegir.
            $context['extrasInvite'] = $notices->extras === null ? null : $link($notices->extras);
        } catch (\Throwable $e) {
            // Cortesía de UI: nunca rompemos la página por el contexto de cuenta.
            report($e);
        }

        return $context;
    }
}
