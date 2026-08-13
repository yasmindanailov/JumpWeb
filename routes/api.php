<?php

use App\Http\Controllers\Api\V1\AuthRegistrationController;
use App\Http\Controllers\Api\V1\AuthSessionController;
use App\Http\Controllers\Api\V1\CatalogProductsController;
use App\Http\Controllers\Api\V1\CatalogZonesController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\MeOrdersController;
use App\Http\Controllers\Api\V1\MeReservationsController;
use App\Http\Controllers\Api\V1\PasswordRecoveryController;
use App\Http\Controllers\Api\V1\QuoteController;
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

    // ── Sesión (paso 3b) ──────────────────────────────────────────────────────────────────────
    // Modo SPA de Sanctum: cookie de sesión + CSRF, nunca un token en `localStorage`. El CSRF lo
    // monta `EnsureFrontendRequestsAreStateful` para los orígenes declarados *stateful*, así que
    // aquí no se declara nada al respecto — y por eso el login NO puede vivir fuera del grupo `api`.
    //
    // El limitador de `SEC-06` (por email+IP y por IP sola) está DENTRO de
    // `Identity\Services\PasswordLogin`, no en la ruta: es la misma protección que aplica la web,
    // no una copia con otros números. El `throttle:api` del grupo cuenta además cada intento,
    // porque esta ruta es pública (spec §10, punto 2).
    Route::post('/auth/login', [AuthSessionController::class, 'login'])->name('auth.login');
    // El logout se declara con `auth:sanctum`: cerrar sesión sin tenerla no es una operación, y
    // dejarlo público daría una respuesta idéntica a quien no ha entrado nunca.
    Route::post('/auth/logout', [AuthSessionController::class, 'logout'])
        ->middleware('auth:sanctum')
        ->name('auth.logout');

    // ── Alta y contraseña (paso 3c) — PÚBLICO ────────────────────────────────────────────────
    // Las cuatro capas de defensa del alta (honeypot, límite por IP, límite por correo y Turnstile)
    // y los limitadores de la recuperación viven en los servicios de Identity, no en la ruta: son
    // los MISMOS que aplica la web, no una copia con otros números. El `throttle:api` del grupo se
    // suma como suelo genérico, y está bien que se sume.
    //
    // `email/resend` es público a propósito: quien acaba de darse de alta suelta todavía no tiene
    // sesión, y es justo cuando necesita pedir el reenvío. Lo que impide que sea un cañón de
    // correos hacia un buzón ajeno son sus dos cooldowns —por IP y por correo destinatario—.
    Route::post('/auth/register', [AuthRegistrationController::class, 'register'])->name('auth.register');
    Route::post('/auth/email/resend', [AuthRegistrationController::class, 'resendVerification'])->name('auth.email.resend');
    Route::post('/auth/password/forgot', [PasswordRecoveryController::class, 'sendLink'])->name('auth.password.forgot');
    Route::post('/auth/password/reset', [PasswordRecoveryController::class, 'reset'])->name('auth.password.reset');

    // ── Catálogo (paso 1b) — PÚBLICO ──────────────────────────────────────────────────────────
    // El escaparate se mira sin cuenta: la web ya deja llegar hasta el pago como invitado, y pedir
    // identidad aquí cerraría ese flujo. Al ser rutas públicas, el limitador `throttle:api` sí las
    // cuenta (en una ruta con `auth:`, el 401 se lanza antes de llegar a él — spec §10, punto 2).
    Route::get('/catalog/zones', [CatalogZonesController::class, 'index'])->name('catalog.zones.index');
    Route::get('/catalog/products', [CatalogProductsController::class, 'index'])->name('catalog.products.index');
    // `whereNumber` no es cosmética: sin ella, `/catalog/products/abc` llegaría al controlador y la
    // coerción a `int` reventaría con un 500 en vez del 404 que corresponde.
    Route::get('/catalog/products/{product}', [CatalogProductsController::class, 'show'])
        ->whereNumber('product')
        ->name('catalog.products.show');

    // ── Presupuesto (paso 4a) — PÚBLICO ───────────────────────────────────────────────────────
    // El servidor participa en el carrito sin guardarlo: aquí pone el PRECIO. Es público porque la
    // web deja llegar hasta el pago como invitado, y al serlo el `throttle:api` del grupo sí lo
    // cuenta (en una ruta con `auth:`, el 401 se lanza antes — spec §10, punto 2).
    //
    // No crea nada, no bloquea aforo y no admite la reserva: eso son `ReservationAdmission` y
    // `OrderCreator`, y llegan con `POST orders`. Es `POST` porque la cesta —líneas, complementos
    // anidados y respuestas del evento— no cabe con garantías en una query string, no porque tenga
    // efectos.
    Route::post('/orders/quote', QuoteController::class)->name('orders.quote');

    // ── Zona autenticada ──────────────────────────────────────────────────────────────────────
    // `auth:sanctum` cubre los DOS modos del §4.2 con el mismo código: cookie de sesión para la
    // SPA de primera parte y Bearer para el móvil. El guard resuelve primero los guards de sesión
    // (`sanctum.guard`) y solo después el token.
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [MeController::class, 'show'])->name('me.show');

        // «Mis reservas» y «Mis pedidos» (paso 1, solo lectura). El scoping es por el guard en los
        // dos: ninguno acepta un identificador de titular por la petición.
        Route::get('/me/reservations', [MeReservationsController::class, 'index'])->name('me.reservations.index');
        Route::get('/me/orders', [MeOrdersController::class, 'index'])->name('me.orders.index');
    });
});
