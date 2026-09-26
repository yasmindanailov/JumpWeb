<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Exceptions\DependentNotFoundException;
use App\Domain\Identity\Exceptions\DependentNotMinorException;
use App\Domain\Identity\Exceptions\DependentsLimitReachedException;
use App\Domain\Identity\Exceptions\WaiverDocumentStaleException;
use App\Domain\Identity\Exceptions\WaiverEmailUnverifiedException;
use App\Domain\Identity\Exceptions\WaiverNotInternalException;
use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\DependentRegistry;
use App\Domain\Identity\Services\LegalDocuments;
use App\Domain\Identity\Services\WaiverAcceptance;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Platform\Services\DisplayTime;
use App\Http\Api\ApiCollection;
use App\Http\Api\ApiErrorCode;
use App\Http\Api\ApiErrorResponse;
use App\Http\Api\Concerns\BuildsWaiverSignatureRequest;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DependentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * `/api/v1/me/dependents` — **mis menores a cargo** (`specs/menores-a-cargo.md` §4.2, §4.4, §4.5,
 * §4.9). Tres verbos y ninguna edición: una fecha de nacimiento corregida es OTRA persona a cargo, y
 * con una firma detrás sería reescribir lo que se firmó (§4.4: quitar y volver a añadir son dos filas).
 *
 *  · `GET`    — las activas, en el orden en que se declararon. Nunca las retiradas.
 *  · `POST`   — declarar una. El servidor valida la forma; el dominio decide: solo menores
 *    (`422 dependent_not_minor`) y hasta el tope de la instalación (`422 dependents_limit_reached`,
 *    con `params.max`). El tope es de servidor (`PAY-12`), no de pantalla.
 *  · `DELETE` — quitarla. Con waiver firmado detrás se DESVINCULA y sin él se borra; el cliente no
 *    distingue los dos casos y no le hace falta. Un id ajeno, inexistente o ya retirado es `404`.
 *  · `POST {dependent}/waiver` — ACEPTAR el waiver vigente EN SU NOMBRE (§4.3: lo firma el adulto). Las
 *    mismas reglas que `POST /me/waiver` —`document_id` del texto servido, `409` si cambió, si el modo
 *    no es interno o si el correo del titular no está verificado— más las del sujeto: suyo y activo
 *    (`404`) y menor (`422 dependent_not_minor`). Cada firma queda en la lista de `GET /me/waiver` y
 *    su PDF se sirve por `GET /me/waiver/{signature}/pdf`.
 *
 * Todo lo que decide vive en `Identity\Services\{DependentRegistry,WaiverAcceptance,WaiverSigner}`;
 * aquí se traduce a HTTP.
 */
class MeDependentsController extends Controller
{
    use BuildsWaiverSignatureRequest;

    public function index(Request $request, DependentRegistry $registry): ApiCollection
    {
        /** @var User $user */
        $user = $request->user();

        return new ApiCollection($registry->activeFor($user), DependentResource::class);
    }

    public function store(Request $request, DependentRegistry $registry): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // «Hoy» es el del parque (`DisplayTime`, doctrina `AFORO-09`): a las 00:30 de Madrid en
        // verano el UTC todavía va por ayer, y una fecha de nacimiento «de hoy» sería futura.
        // ⚠️ `relationship` es OBLIGATORIA en el alta nueva (`#236`) aunque en la tabla sea nulable:
        // las fichas anteriores a esa tanda no la tienen y no se inventa. Se cierra contra el
        // catálogo —es lo que sostiene que este adulto pueda firmar por el menor—.
        // ▶ `surname`, OPCIONAL desde `#773`·a (`[DECIDIDO owner]`, revoca esa parte de `#236`): Mi
        // cuenta de la isla no la pide, y la firma guarda entonces el nombre que se declaró. Vacío o
        // ausente, la ficha lo guarda como `null` (`DependentRegistry::add`), nunca como un texto vacío.
        // ❗❗ `#441` · **la exención se acepta AQUÍ, en el mismo gesto.** Los dos campos son
        // obligatorios SOLO si esta instalación tiene algo que firmar: en modo `externo`, o sin
        // versión publicada, exigirlos dejaría a esa instalación sin poder declarar un menor
        // (`specs/firma-al-declarar-menor.md` §4.5, la doctrina de `#348`).
        //
        // ⚠️⚠️ **La autoridad es el SERVIDOR y no la casilla**: si la decisión viviera en el cliente
        // se declararía sin aceptar quitando un `input` del DOM — el defecto que `#400` documenta
        // para el justificante. Aquí se decide, y `DependentRegistry::add()` lo vuelve a exigir como
        // cinturón para que ningún llamante futuro nazca por fuera.
        $exigible = WaiverSettings::isInternal()
            && LegalDocuments::latestVersionNumber(WaiverSettings::SLUG) !== null;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:'.Dependent::NAME_MAX],
            'surname' => ['nullable', 'string', 'max:'.Dependent::SURNAME_MAX],
            'relationship' => ['required', 'string', Rule::in(Dependent::RELATIONSHIPS)],
            'born_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.DisplayTime::today()->toDateString()],
            // ⚠️ La rama de «no exigible» NO puede llevar `accepted`: es una regla IMPLÍCITA de
            // Laravel y falla también con el campo AUSENTE, aunque vaya junto a `nullable`. Con ella,
            // una instalación en modo `externo` recibía 422 al declarar un menor — o sea, el defecto
            // que §4.5 existe para impedir, colado por la puerta de la validación.
            'accept_waiver' => $exigible ? ['required', 'accepted'] : ['nullable', 'boolean'],
            'waiver_document_id' => [$exigible ? 'required' : 'nullable', 'integer', 'min:1'],
        ]);

        // ⚠️ El texto se resuelve contra la versión VIGENTE —`currentDocument()` devuelve `null` si el
        // identificador no es el que manda hoy—, así que una pantalla que llevara abierta desde antes
        // de una republicación recibe un 409 en vez de firmar algo que su dueño no leyó.
        $waiver = null;
        if ($exigible) {
            $waiver = WaiverAcceptance::currentDocument((int) $data['waiver_document_id']);

            if ($waiver === null) {
                return ApiErrorResponse::make(ApiErrorCode::WaiverDocumentStale, 409);
            }
        }

        try {
            $dependent = $registry->add(
                $user,
                (string) $data['name'],
                (string) $data['born_on'],
                (string) ($data['surname'] ?? ''),
                (string) $data['relationship'],
                $waiver,
                $this->signatureRequest($request),
            );
        } catch (WaiverDocumentStaleException) {
            // Se republicó entre la comprobación de arriba y la escritura bajo el lock (S-3 de `#181`).
            // El menor NO se ha creado: la transacción se deshizo entera.
            return ApiErrorResponse::make(ApiErrorCode::WaiverDocumentStale, 409);
        } catch (DependentNotMinorException) {
            return ApiErrorResponse::make(ApiErrorCode::DependentNotMinor, 422);
        } catch (DependentsLimitReachedException $e) {
            // El tope viaja DOS veces a propósito: en `params.max` para el cliente que programa, y ya
            // interpolado en el mensaje para el que solo lo muestra.
            return ApiErrorResponse::make(
                ApiErrorCode::DependentsLimitReached,
                422,
                message: __(ApiErrorCode::DependentsLimitReached->messageKey(), ['max' => $e->max]),
                params: ['max' => $e->max],
            );
        }

        return (new DependentResource($dependent))->response($request)->setStatusCode(201);
    }

    public function destroy(Request $request, DependentRegistry $registry, int $dependent): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $registry->remove($user, $dependent);
        } catch (DependentNotFoundException) {
            return ApiErrorResponse::make(ApiErrorCode::NotFound, 404);
        }

        return response()->json(status: 204);
    }

    public function acceptWaiver(Request $request, WaiverAcceptance $acceptance, DependentRegistry $registry, int $dependent): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'document_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $row = $registry->findActive($user, $dependent);
            $acceptance->acceptForDependent($user, $row, (int) $data['document_id'], $this->signatureRequest($request));
        } catch (DependentNotFoundException) {
            return ApiErrorResponse::make(ApiErrorCode::NotFound, 404);
        } catch (DependentNotMinorException) {
            return ApiErrorResponse::make(ApiErrorCode::DependentNotMinor, 422);
        } catch (WaiverNotInternalException) {
            return ApiErrorResponse::make(ApiErrorCode::WaiverNotInternal, 409);
        } catch (WaiverDocumentStaleException) {
            return ApiErrorResponse::make(ApiErrorCode::WaiverDocumentStale, 409);
        } catch (WaiverEmailUnverifiedException) {
            return ApiErrorResponse::make(ApiErrorCode::WaiverEmailUnverified, 409);
        }

        return (new DependentResource($row->fresh()))->response($request)->setStatusCode(201);
    }
}
