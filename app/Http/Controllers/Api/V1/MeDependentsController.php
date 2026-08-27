<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Exceptions\DependentNotFoundException;
use App\Domain\Identity\Exceptions\DependentNotMinorException;
use App\Domain\Identity\Exceptions\DependentsLimitReachedException;
use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\DependentRegistry;
use App\Domain\Platform\Services\DisplayTime;
use App\Http\Api\ApiCollection;
use App\Http\Api\ApiErrorCode;
use App\Http\Api\ApiErrorResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DependentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
 *
 * Todo lo que decide vive en `Identity\Services\DependentRegistry`; aquí se traduce a HTTP.
 */
class MeDependentsController extends Controller
{
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
        $data = $request->validate([
            'name' => ['required', 'string', 'max:'.Dependent::NAME_MAX],
            'born_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.DisplayTime::today()->toDateString()],
        ]);

        try {
            $dependent = $registry->add($user, (string) $data['name'], (string) $data['born_on']);
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
}
