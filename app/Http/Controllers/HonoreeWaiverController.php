<?php

namespace App\Http\Controllers;

use App\Domain\Booking\Models\OrderItem;
use App\Domain\Identity\Exceptions\DependentNotFoundException;
use App\Domain\Identity\Exceptions\DependentNotMinorException;
use App\Domain\Identity\Exceptions\DependentsLimitReachedException;
use App\Domain\Identity\Exceptions\DependentWaiverRequiredException;
use App\Domain\Identity\Exceptions\WaiverDocumentStaleException;
use App\Domain\Identity\Exceptions\WaiverEmailUnverifiedException;
use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\DependentAssigner;
use App\Domain\Identity\Services\DependentRegistry;
use App\Domain\Identity\Services\WaiverAcceptance;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Identity\Services\WaiverSigner;
use App\Domain\Identity\Services\WaiverStatus;
use App\Http\Concerns\AuthorizesGuestForm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * **EL DESCARGO DE QUIEN CUMPLE, por el camino de la CUENTA** (`specs/fiesta-sistema-nuevo.md` §4.13, `[DECIDIDO owner]`
 * `#752`): desde el panel bajo su fila en la lista, el titular dice cuál de sus hijos a cargo es —o lo añade— y firma su
 * descargo; su hijo queda atado a quien cumple (`DependentAssigner::assignHonoree()`).
 *
 * ⚠️⚠️ **EXIGE la sesión del TITULAR**, aunque la lista se abra con un enlace firmado (`RGPD-03`): ese enlace no da sesión,
 * se reenvía, y una firma a nombre del titular no puede hacerla quien tenga el enlace. Sin ella se vuelve al panel con
 * «entra antes»; el otro camino —su justificante, que firma quien sea con su propia identidad— no pasa por aquí.
 * ⚠️ Es su propio POST, como personalizar o descartar: no escribe `guest_data` ni mueve el testigo del formulario.
 * ⚠️ Si el titular no verificó su correo, la firma quedaría RETENIDA (`#179`) y el hijo no se podría atar: se le dice antes
 * de escribir nada, en vez de dejar medio paso hecho.
 */
class HonoreeWaiverController extends Controller
{
    use AuthorizesGuestForm;

    public function store(Request $request, OrderItem $reservation): RedirectResponse
    {
        $this->authorizeGuestFormAccess($request, $reservation);

        if ($reservation->isFinishedInPractice()) {
            return $this->back($request, $reservation, 'guest-form-readonly');
        }
        $holder = $request->user();
        if (! $holder instanceof User || ! $this->ownsGuestForm($request, $reservation) || ! WaiverSettings::isInternal()) {
            return $this->back($request, $reservation, 'cumple-entrar');
        }
        if ($holder->email_verified_at === null) {
            return $this->rechazo($request, $reservation, __('fiesta.lista.cumple_firma.verifica'));
        }

        $nuevo = $request->input('hijo') === 'nuevo';
        $validator = Validator::make($request->all(), [
            'hijo' => ['required', 'string'],
            'document_id' => ['required', 'integer'],
            'accept_waiver' => ['accepted'],
            'minor_name' => [$nuevo ? 'required' : 'nullable', 'string', 'max:'.Dependent::NAME_MAX],
            'minor_surname' => [$nuevo ? 'required' : 'nullable', 'string', 'max:'.Dependent::SURNAME_MAX],
            'minor_born_on' => [$nuevo ? 'required' : 'nullable', 'date_format:Y-m-d'],
            'relationship' => [$nuevo ? 'required' : 'nullable', 'string', 'in:'.implode(',', Dependent::RELATIONSHIPS)],
        ], [
            'accept_waiver.accepted' => __('fiesta.firma.err_casilla'),
            'minor_name.required' => __('fiesta.autorizacion.err_nino_nombre'),
            'minor_surname.required' => __('fiesta.autorizacion.err_nino_apellidos'),
            'minor_born_on.*' => __('fiesta.lista.cumple_firma.err_nacimiento'),
            'relationship.*' => __('fiesta.lista.cumple_firma.err_relacion'),
        ]);
        if ($validator->fails()) {
            return $this->rechazo($request, $reservation, (string) $validator->errors()->first());
        }

        // «La versión que el servidor SIRVIÓ» (`waiver-probatorio.md` §4.4): si el texto cambió entre pintarlo y firmarlo,
        // se vuelve a leer.
        $document = WaiverAcceptance::currentDocument((int) $request->input('document_id'));
        if ($document === null) {
            return $this->back($request, $reservation, 'cumple-stale');
        }
        $peticion = WaiverSignatureRequest::web($request->ip(), (string) $request->userAgent());

        try {
            if ($nuevo) {
                $hijo = app(DependentRegistry::class)->add(
                    $holder,
                    (string) $request->input('minor_name'),
                    (string) $request->input('minor_born_on'),
                    (string) $request->input('minor_surname'),
                    (string) $request->input('relationship'),
                    $document,
                    $peticion,
                );
            } else {
                $hijo = app(DependentRegistry::class)->findActive($holder, (int) $request->input('hijo'));
                // Un hijo con el descargo de un texto viejo (o sin él) lo firma AQUÍ, con la casilla que acaba de marcar.
                $estado = WaiverStatus::forDependent($hijo);
                if (! $estado->signed || $estado->isOutdated()) {
                    app(WaiverSigner::class)->sign($holder, $document, $peticion->forDependent((int) $hijo->getKey()));
                }
            }
        } catch (WaiverDocumentStaleException) {
            return $this->back($request, $reservation, 'cumple-stale');
        } catch (DependentNotMinorException) {
            return $this->rechazo($request, $reservation, __('fiesta.lista.cumple_firma.err_mayor'));
        } catch (DependentsLimitReachedException) {
            return $this->rechazo($request, $reservation, __('fiesta.lista.cumple_firma.err_tope'));
        } catch (DependentWaiverRequiredException|WaiverEmailUnverifiedException) {
            return $this->rechazo($request, $reservation, __('fiesta.lista.cumple_firma.verifica'));
        } catch (DependentNotFoundException) {
            return $this->rechazo($request, $reservation, __('api.dependents.not_yours'));
        }

        $out = app(DependentAssigner::class)->assignHonoree($holder, (int) $reservation->order_id, (int) $reservation->getKey(), (int) $hijo->getKey());
        if (! $out->ok()) {
            $motivo = array_values($out->rejections)[0] ?? null;

            return $this->rechazo($request, $reservation, $motivo !== null ? __('api.dependents.'.$motivo) : __('fiesta.lista.cumple_firma.err_otro'));
        }

        return $this->back($request, $reservation, 'cumple-firmado');
    }

    /**
     * A la lista, al panel de quien cumple. ⚠️ Con la sesión del titular, por su ruta; sin ella, por el enlace FIRMADO: la
     * ruta a secas le daría un 403 justo a quien viene a que le digan «entra antes» (el mismo cuidado que `invitationBackUrl()`).
     */
    private function back(Request $request, OrderItem $reservation, string $status): RedirectResponse
    {
        $lista = $this->ownsGuestForm($request, $reservation)
            ? route('reservation.guests', ['reservation' => $reservation])
            : $reservation->guestFormSignedUrl();

        return redirect()->to($lista.'#pli-cumple-firma')->with('status', $status);
    }

    private function rechazo(Request $request, OrderItem $reservation, string $texto): RedirectResponse
    {
        return $this->back($request, $reservation, 'cumple-rechazo')->with('honoree_reason', $texto);
    }
}
