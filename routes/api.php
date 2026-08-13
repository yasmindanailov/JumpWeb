<?php

use App\Http\Controllers\Api\V1\AuthRegistrationController;
use App\Http\Controllers\Api\V1\AuthSessionController;
use App\Http\Controllers\Api\V1\AvailabilityController;
use App\Http\Controllers\Api\V1\CatalogProductsController;
use App\Http\Controllers\Api\V1\CatalogZonesController;
use App\Http\Controllers\Api\V1\ConfigController;
use App\Http\Controllers\Api\V1\GuestFormController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\MeOrdersController;
use App\Http\Controllers\Api\V1\MeReservationEligibilityController;
use App\Http\Controllers\Api\V1\MeReservationsController;
use App\Http\Controllers\Api\V1\OrderPaymentController;
use App\Http\Controllers\Api\V1\OrderPaymentStatusController;
use App\Http\Controllers\Api\V1\OrdersController;
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

    // ── Ajustes de la instalación (Fase 4 · paso 4.0b) — PÚBLICO ────────────────────────────
    // Los cuatro valores que un cliente necesita para pintar el cajón bien a la primera: el bloque
    // de registro externo, el umbral del buscador, la clave pública del anti-bot y el tope de
    // líneas de la cesta. Sin ellos, un cliente aprende las reglas CHOCÁNDOSE (descubre el tope con
    // un 422), que es una regla de negocio naciendo en el cliente.
    //
    // Lo que cambia mientras el cliente navega NO va aquí: la pausa de reservas vive en su propio
    // endpoint, porque un snapshot de arranque mentiría en cuanto la dueña accionara el interruptor.
    Route::get('/config', ConfigController::class)->name('config.show');

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

    // ── Disponibilidad (paso 4b) — PÚBLICA ────────────────────────────────────────────────────
    // El segundo momento en que el servidor participa en el carrito sin guardarlo: qué días y qué
    // horas quedan. La fuente es `SlotOffer` (`AFORO-02`), la misma que el panel.
    //
    // Las HORAS van por POST y llevan la cesta: `offerableTimes()` descuenta los ocupantes que la
    // propia cesta ya retiene, así que un GET sin cesta ofrecería horas que el checkout rechazaría.
    // Las FECHAS van por GET porque no dependen de la cesta (un día se ofrece si tiene franjas).
    //
    // `whereNumber` no es cosmética: sin ella `/availability/abc/dates` llegaría al controlador y la
    // coerción a `int` reventaría con un 500 en vez del 404 que corresponde.
    Route::get('/availability/{product}/dates', [AvailabilityController::class, 'dates'])
        ->whereNumber('product')
        ->name('availability.dates');
    Route::post('/availability/{product}/times', [AvailabilityController::class, 'times'])
        ->whereNumber('product')
        ->name('availability.times');

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

    // ── Post-form de invitados (paso 5) — FIRMA o TITULAR ────────────────────────────────────
    // El segundo consumidor de la API, y el único que se autoriza con una FIRMA: el enlace viaja
    // por correo semanas antes del evento, sin cuenta detrás. La firma HMAC es la prueba de
    // titularidad, igual que en la página web — y como cubre la URL exacta, la de la API se firma
    // aparte con la misma caducidad (`OrderItem::guestFormApiUrls()`, el canje del spec §4.6.5).
    //
    // Fuera del grupo `auth:sanctum` a propósito: el titular autenticado también entra, pero
    // exigir sesión cerraría la vía del correo, que es la principal. La autorización la hace el
    // controlador con la escalada 403 → 410 → 404 (`Http\Concerns\AuthorizesGuestForm`).
    //
    // ⚠️ `no-store` EXPLÍCITO (`RGPD-04`): estas respuestas llevan nombres y alergias de menores, y
    // como la ruta es accesible sin sesión, el `no-store` por defecto de la superficie autenticada
    // no las cubriría. El `throttle:30,1` de escritura es el mismo que ya aplica la web.
    Route::get('/reservations/{reservation}/guest-form', [GuestFormController::class, 'show'])
        ->whereNumber('reservation')
        ->middleware('no-store')
        ->name('reservations.guest-form.show');
    Route::put('/reservations/{reservation}/guest-form', [GuestFormController::class, 'update'])
        ->whereNumber('reservation')
        ->middleware(['throttle:30,1', 'no-store'])
        ->name('reservations.guest-form.update');

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

        // Aviso TEMPRANO de «¿puedo reservar?» (Fase 4 · paso 4.0b). Consulta pura: pregunta a
        // `mayReserve()`, que NO consume ficha del limitador — usar `admitReservation()` aquí haría
        // que pasar dos veces por la cesta impidiera confirmar la segunda compra del minuto.
        // Sin parámetros a propósito: la única fuente de identidad es el guard.
        Route::get('/me/reservation-eligibility', MeReservationEligibilityController::class)
            ->name('me.reservation-eligibility.show');

        // ── Pedido y cobro (paso 4c) ──────────────────────────────────────────────────────────
        // Autenticado y acotado al titular por el guard: ninguna de las tres rutas acepta un
        // identificador de titular por la petición, y un código ajeno responde 404 —no 403— para no
        // convertirlas en un oráculo de códigos de pedido.
        //
        // El REINTENTO conserva su propio techo `throttle:6,1` además del suelo del grupo: es una
        // operación que abre un cobro real contra la pasarela, y el suelo genérico no basta. El
        // número es el mismo que ya aplica la web (spec §4.7); `PAY-15` (120/min) es otra cosa —el
        // throttle de las callbacks de Redsys— y confundirlos deja el reintento 20× más laxo.
        Route::post('/orders', [OrdersController::class, 'store'])->name('orders.store');
        Route::get('/orders/{code}', [OrdersController::class, 'show'])->name('orders.show');
        Route::post('/orders/{code}/payment', [OrderPaymentController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('orders.payment.store');
        // Sondeo del desenlace (paso 4d). Se consulta EN BUCLE mientras se espera la notificación
        // de la pasarela, así que se queda con el suelo genérico del grupo y no con el techo del
        // reintento: aquí no se abre ningún cobro, solo se lee. No transiciona nada — `PAY-01` dice
        // que el único que pasa una Order a `paid` es `RedsysReturnHandler`, con la firma delante.
        Route::get('/orders/{code}/payment-status', OrderPaymentStatusController::class)
            ->name('orders.payment.status');
    });
});
