<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fuerza `Cache-Control: no-store` en respuestas que sirven PII SENSIBLE (auditoría Fase 1 · L1):
 * las hojas/resúmenes PDF operativos y la página del post-form llevan nombres y ALERGIAS de MENORES
 * (datos de salud, art. 9 RGPD).
 *
 * Por defecto Symfony ya emite `Cache-Control: no-cache, private` cuando el controlador no fija
 * cabeceras de caché (DomPDF `stream()`, `view()`) — eso ya prohíbe proxies/cachés compartidas. Pero
 * `no-cache ≠ no-store`: los bytes pueden persistir en la caché EN DISCO del navegador de un
 * dispositivo COMPARTIDO de puerta y reaparecer con «Atrás»/bfcache o por análisis forense. `no-store`
 * impide esa persistencia. Mismo patrón ya aplicado puntualmente en `RedsysReturnController`.
 */
class NoStore
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, max-age=0, private');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
