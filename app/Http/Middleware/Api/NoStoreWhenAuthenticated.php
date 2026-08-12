<?php

namespace App\Http\Middleware\Api;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fase 3 · paso 0 — `Cache-Control: no-store` en toda respuesta AUTENTICADA de `/api/v1`
 * (`RGPD-04`, spec §4.7).
 *
 * `RGPD-04` habla de «toda superficie NO-Livewire con PII», y una API lo es por definición: sus
 * respuestas autenticadas llevan perfil, pedidos y —en cuanto lleguen los pasos 1 y 4— datos de
 * invitados con alergias de menores (art. 9). El middleware `NoStore` de la web se aplica ruta a
 * ruta; aquí se aplica **por defecto**, porque en una superficie que solo crece, lo que se recuerda
 * poner en cada ruta nueva acaba olvidándose en alguna.
 *
 * **Por qué se decide DESPUÉS de la respuesta y no por ruta:** `auth:sanctum` llama a
 * `Auth::shouldUse()` al autenticar (verificado en `Auth\Middleware\Authenticate::authenticate`),
 * así que al volver del pipeline `$request->user()` ya devuelve al usuario resuelto —de sesión o de
 * Bearer— sin coste ni consulta adicional. Un endpoint público responde `null` y conserva su
 * cacheabilidad, que `PERF-02` necesita para `catalog/*` y `availability/*`.
 *
 * Efecto deliberado: un endpoint PÚBLICO pedido por un visitante con sesión iniciada también sale
 * `no-store`. Es el lado conservador —hay identidad, luego puede haber personalización— y el precio
 * es perder caché de navegador para usuarios con sesión, no para el tráfico anónimo, que es el que
 * `PERF-02` protege.
 */
class NoStoreWhenAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->user() !== null) {
            $response->headers->set('Cache-Control', 'no-store, max-age=0, private');
            $response->headers->set('Pragma', 'no-cache');
        }

        return $response;
    }
}
