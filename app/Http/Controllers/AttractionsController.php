<?php

namespace App\Http\Controllers;

use App\Domain\Booking\Models\Zone;
use Illuminate\Http\Request;

/**
 * `/atracciones` — la página que dos secciones cerradas ya prometían (carril de diseño, T2d).
 *
 * Artboard `Atracciones PJP` **1a** (móvil) + **1c** (escritorio), cerrado en `doc/paginas.md`.
 * Es la **capa 3** del sistema de contenido —el detalle, «quien llega aquí ha querido llegar»—, así
 * que aquí sí caben las 23 fichas con su dato, que es exactamente lo que la portada no puede dar:
 * su sección 03 enseña **cinco** y delega el resto en esta página.
 *
 * ⚠️⚠️ **LA ZONA DE LLEGADA VIAJA EN LA QUERY, NO EN EL HASH**, y es una divergencia declarada con
 * el artboard, que escribe `#atracciones-jump` / `#atracciones-kids`. Motivo medido: un hash **no
 * llega al servidor**, así que una pestaña seleccionada por ancla solo funciona con JavaScript —y
 * este proyecto exige suelo sin JS—. El precedente está aquí al lado y está ROTO: `#478` enlazó la
 * tarjeta de zona a `/precios#zona-<slug>` y `/precios` emite **cero** `id="zona-…"` (medido), o sea
 * que ese ancla lleva al principio de la página sin que nada falle. Ficha en `DEUDA.md`.
 *
 * ⚠️ **Un valor desconocido en `?zona=` no es un error: es la primera zona.** La página existe para
 * enseñar lo que hay dentro, y un 404 por un parámetro tecleado a mano la haría desaparecer.
 */
class AttractionsController extends Controller
{
    public function __invoke(Request $request)
    {
        /*
         * Solo las zonas de la landing que TIENEN atracciones activas. Una pestaña que abre una
         * rejilla vacía no informa de nada — y en esta instalación «Cumpleaños» es exactamente ese
         * caso: es una zona de venta, no un sitio con juegos que enseñar (medido: 0 atracciones).
         */
        $zones = Zone::with(['attractions' => fn ($q) => $q->where('is_active', true)->orderBy('position')])
            ->where('show_in_landing', true)->orderBy('position')->get()
            ->filter(fn (Zone $zone): bool => $zone->attractions->isNotEmpty())
            ->values();

        $requested = (string) $request->query('zona', '');

        return view('pages.attractions', [
            'zones' => $zones,
            // El recuento que la entradilla publica y que la portada repite en su puerta. Sale de
            // lo que la página ENSEÑA —las zonas con atracciones—, no de `Attraction::count()`:
            // dos cifras del mismo hecho tienen que salir del mismo sitio o divergen.
            'total' => $zones->sum(fn (Zone $zone): int => $zone->attractions->count()),
            'active' => $zones->firstWhere('slug', $requested)?->slug ?? $zones->first()?->slug,
        ]);
    }
}
