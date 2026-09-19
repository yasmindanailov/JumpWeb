<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Content\Models\Page;
use App\Http\Controllers\Controller;
use App\Http\Middleware\SetLocale;
use App\Http\Resources\Api\V1\LegalDocumentsResource;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Los TEXTOS LEGALES del menú de hechos (F5 · T4): el índice y cada documento.
 *
 * ⚠️ **Solo los ACTIVOS.** Una página desactivada en el panel es una página retirada: servirla por la API la
 * devolvería a la web por la puerta de atrás. Misma regla que las normas.
 *
 * ⚠️ **No cuelga de `/legal/{clave}` a propósito.** `/legal/waiver` ya existe y dice otra cosa —el RÉGIMEN
 * del justificante para el cajón: si se firma dentro, fuera, o no se firma—, así que una ruta genérica ahí
 * la ensombrecería y dos peticiones que se escriben igual devolverían formas distintas. `documents` dice lo
 * que hay.
 */
class LegalDocumentsController extends Controller
{
    public function index(Request $request): LegalDocumentsResource
    {
        $this->useRequestedLocale($request);

        return new LegalDocumentsResource($this->activas(), conCuerpo: false);
    }

    public function show(Request $request, string $clave): LegalDocumentsResource
    {
        $this->useRequestedLocale($request);

        $paginas = $this->activas()->where('slug', $clave);

        if ($paginas->isEmpty()) {
            throw new NotFoundHttpException('legal_document_not_found');
        }

        return new LegalDocumentsResource($paginas, conCuerpo: true);
    }

    /** @return Collection<int, Page> */
    private function activas(): Collection
    {
        return Page::query()->where('is_active', true)->orderBy('slug')->get()->collect();
    }

    /** El idioma es parte de la petición, no del visitante: la respuesta se cachea. */
    private function useRequestedLocale(Request $request): void
    {
        $datos = $request->validate(['lang' => ['required', 'string', Rule::in(SetLocale::SUPPORTED)]]);

        app()->setLocale($datos['lang']);
    }
}
