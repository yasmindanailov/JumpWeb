<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Content\Models\Attraction;
use App\Http\Controllers\Controller;
use App\Http\Middleware\SetLocale;
use App\Http\Resources\Api\V1\AttractionsFactsResource;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * `GET /api/v1/attractions?lang=` — los JUEGOS del recinto (F5 · el menú de hechos,
 * `docs/specs/instancia-y-landing-fuera.md` §4.1).
 *
 * ⚠️⚠️ **El gate de la zona es `show_in_landing`, NO `is_active`**, y la diferencia está documentada
 * desde que se desacoplaron (`2026_06_07_000002`): `is_active` dice que **la zona OPERA** y
 * `show_in_landing` dice que **sale en la web**. Medido hoy: la zona de cumpleaños opera —vende packs—
 * y NO está en la landing. Gatear por `is_active` publicaría las atracciones de una zona que el negocio
 * retiró de su web, o sea devolverla por la puerta de atrás; y es además el gate exacto que usa la
 * página, así que API y web no pueden discrepar sobre qué es público.
 *
 * ⚠️ **El orden es el de la web**: zona por su posición, y dentro cada juego por la suya, con `id` de
 * desempate —`position` no es única ni aquí— para que una respuesta cacheada con `ETag` no baraje.
 */
class AttractionsFactsController extends Controller
{
    public function __invoke(Request $request): AttractionsFactsResource
    {
        $datos = $request->validate(['lang' => ['required', 'string', Rule::in(SetLocale::SUPPORTED)]]);

        app()->setLocale($datos['lang']);

        return new AttractionsFactsResource(
            Attraction::query()
                ->join('zones', 'zones.id', '=', 'attractions.zone_id')
                // ⚠️ CUALIFICADAS las dos: `is_active` existe en `attractions` Y en `zones`, y sin el
                // prefijo SQLite responde «ambiguous column name» — lo cazó este test en rojo. Y aquí
                // la ambigüedad no era solo sintáctica: las dos columnas dicen cosas distintas, así que
                // la que se elige por descuido puede ser la equivocada.
                ->where('attractions.is_active', true)
                ->where('zones.show_in_landing', true)
                ->with('zone')
                ->orderBy('zones.position')
                ->orderBy('attractions.position')
                ->orderBy('attractions.id')
                ->select('attractions.*')
                ->get()
        );
    }
}
