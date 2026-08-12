<?php

use App\Http\Controllers\Api\V1\MeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
|
| Fase 3 (`docs/specs/api-v1.md`). El prefijo `/api/v1` y el grupo de middleware se aplican en
| `bootstrap/app.php`: aquí no se repiten ni el uno ni el otro.
|
| **La versión va en la URL** (§4.1). `v1` es evolutiva —se le añaden endpoints— hasta que exista
| el primer cliente móvil (Fase 6); ahí se congela y cualquier cambio incompatible abre `v2`.
|
| **Toda ruta lleva nombre `api.v1.*`.** No es cosmética: la guarda de contrato empareja las rutas
| REGISTRADAS con los `paths` del documento OpenAPI, y el prefijo es lo que le permite saber qué
| rutas son suyas sin adivinar por el path.
|
| Orden de llegada (spec §9): paso 0 los cimientos · paso 1 solo lectura (`me/reservations`,
| `me/orders`, catálogo) · paso 3 auth · paso 4 el dinero · paso 5 el post-form.
|
*/

Route::name('api.v1.')->group(function (): void {

    // ── Zona autenticada ──────────────────────────────────────────────────────────────────────
    // `auth:sanctum` cubre los DOS modos del §4.2 con el mismo código: cookie de sesión para la
    // SPA de primera parte y Bearer para el móvil. El guard resuelve primero los guards de sesión
    // (`sanctum.guard`) y solo después el token.
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [MeController::class, 'show'])->name('me.show');
    });
});
