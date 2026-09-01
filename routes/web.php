<?php

use App\Http\Controllers\Account\AccountController;
use App\Http\Controllers\Account\EmailChangeController;
use App\Http\Controllers\Admin\CalendarEventsController;
use App\Http\Controllers\Admin\DailySummaryController;
use App\Http\Controllers\Admin\PanelLocaleController;
use App\Http\Controllers\Admin\ReservationSlipController;
use App\Http\Controllers\Admin\WaiverProofController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CookieConsentController;
use App\Http\Controllers\EventsController;
use App\Http\Controllers\GuardianAuthorizationController;
use App\Http\Controllers\GuestFormController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\Payments\RedsysReturnController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\ServicesController;
use App\Http\Controllers\SitemapController;
use App\Http\Middleware\SetAdminLocale;
use App\Http\Middleware\SetLocale;
use App\Livewire\Admin\Puerta\ValidarRegistro;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// Autenticación (Fase 4). Registro y login abren un modal sobre la home (#38);
// la verificación de email son páginas (llegan desde el correo).
Route::get('/registro', HomeController::class)->name('registro');
// La PUERTA de la pantalla que completa un alta con Google (`specs/auth-con-google.md` §7). Sirve la
// home y el cajón se abre solo en su zona, exactamente como las otras tres puertas de auth: el
// mecanismo es el de `Http\Sidebar\AccountDoor`, no uno nuevo.
// ⚠️ Aquí no se comprueba nada: **quien decide si hay algo que completar es la zona**, preguntando a
// `GET /api/v1/auth/google/pending`. Poner la guarda en la ruta obligaría a duplicar la lectura de la
// sesión y dejaría dos sitios que pueden discrepar.
Route::get('/registro/google', HomeController::class)->name('registro.google');
Route::get('/login', HomeController::class)->name('login');
Route::get('/recuperar-contrasena', HomeController::class)->name('password.request');
Route::get('/restablecer-contrasena/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
Route::get('/email/verificar', [EmailVerificationController::class, 'notice'])->name('verification.notice');
Route::get('/email/verificar/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
// Reenvío del correo de verificación para el usuario AUTENTICADO sin verificar (pay-first: quien se
// registró en la compra y abandonó sin pagar). `auth` (sabemos a quién) + throttle de ruta; el
// controlador añade un cooldown por usuario (1/min). POST con CSRF estándar.
Route::post('/email/verificar/reenviar', [EmailVerificationController::class, 'resend'])
    ->middleware(['auth', 'throttle:6,1'])->name('verification.send');
Route::post('/logout', LogoutController::class)->middleware('auth')->name('logout');

// Entrar y registrarse con GOOGLE (`docs/specs/auth-con-google.md` §6.4). Dos rutas de NAVEGADOR:
// van en `web` y no en `/api/v1` porque son redirecciones con sesión, no una superficie de datos —y
// el contrato exige paridad en las dos direcciones, así que declararlas allí obligaría a describir
// un `302` hacia Google.
//
// ⚠️⚠️ **La URI de redirección es la RUTA COMPLETA `/auth/google/callback`**, y es la que hay que dar
// de alta en la consola de Google (§10.1). Con el origen pelado, el primer inicio de sesión devuelve
// `Error 400: redirect_uri_mismatch` y no hay nada que depurar en nuestro lado.
//
// ⚠️ Sin las dos claves configuradas las dos responden **404** (el controlador lo impone): una
// instalación que no use Google no tiene por qué enterarse de que esto existe.
//
// Los limitadores son por IP y sobre la IDA sobre todo: es la que cualquiera puede pedir en bucle.
// La vuelta la trae Google con un código de un solo uso, y su reto ya la acota.
Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])
    ->middleware('throttle:10,1')->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])
    ->middleware('throttle:30,1')->name('auth.google.callback');

// Mi cuenta. Zona privada: requiere sesión y email verificado.
//
// ⚠️⚠️ **Las VISTAS se retiraron en la tanda 3 del área de cliente y las RUTAS sobreviven como
// PUERTA** (`specs/area-cliente.md` §4.8, `DECISIONES #120(u)`): sirven la home y el cajón se abre
// solo en su zona, que es el mecanismo de `/entradas`. El mapa ruta→zona vive en
// `Http\Sidebar\AccountDoor` y lo consume el layout.
// ▶ Y no es cosmética: **8 notificaciones ya entregadas** apuntan a `account.orders` —un correo
// enviado no se puede editar— y **11 redirecciones del servidor** aterrizan en `account` con un
// `->with('status', …)` que el layout pinta. Borrar las rutas las convertiría en 404 para siempre.
// ▶ El middleware se conserva casi tal cual: la puerta sigue siendo zona privada, así que un
// invitado va al login.
//
// ❗❗ **`#332` — `verified` SALE, y no es una relajación: es lo que la decisión de `#331` exige.**
// Desde que el alta suelta abre sesión, quien acaba de registrarse llega aquí SIN verificar — y esta
// es **la puerta que le trae** (`account/after-auth.js` navega a `urls.account` porque los textos del
// área solo viajan con sesión). Con `verified` puesto, el alta terminaba rebotando a
// `/email/verificar`: **una pantalla web fuera del cajón**, que es justo el callejón que `#331`
// vino a cerrar. Y sin llegar a su cuenta no hay QR, que es lo que le identifica en la puerta del
// parque (`[DECIDIDO owner]`: «no quiero que se haga cola esperando que verifiquen sus correos»).
//
// ▶ **Lo que falta se le pide DENTRO**, con su botón de reenviar y sus límites: el índice del área
// pinta el aviso (`account/waiver.js::accountNoticeFrom()`).
//
// ⚠️ **La EXPORTACIÓN RGPD entra en el mismo trato a sabiendas**: es el derecho del titular sobre lo
// suyo, y una cuenta sin verificar solo contiene lo que esa misma persona acaba de teclear — no hay
// datos de un tercero que puedan salir por aquí, porque el alta con el correo de otro crea una cuenta
// NUEVA y vacía, no abre la suya.
Route::middleware('auth')->group(function () {
    Route::get('/mi-cuenta', HomeController::class)->name('account');
    Route::get('/mi-cuenta/pedidos', HomeController::class)->name('account.orders');
    // `no-store` (auditoría Fase 1, Sistema 5): el JSON de portabilidad RGPD lleva el perfil
    // completo, consentimientos con IP y `event_data` (nombre + ALERGIAS de menores, art. 9). Es
    // un controlador (no Livewire), así que NO recibe el `no-store` que Livewire estampa solo en
    // sus componentes → se aplica explícito, coherente con las demás superficies con PII (L1).
    //
    // ⚠️ **Esta NO es una vista: es una DESCARGA**, así que sobrevive intacta a la retirada. El
    // cajón usa `GET /api/v1/me/export`, que sirve el mismo documento (`MePrivacyTest` lo compara).
    Route::get('/mi-cuenta/exportar', [AccountController::class, 'export'])
        ->middleware('no-store')
        ->name('account.export');
});

// Confirmación del cambio de email (auditoría 2026-05-26, hallazgo A): enlace firmado del correo
// enviado al NUEVO email. NO requiere sesión (la firma prueba la titularidad del nuevo buzón).
Route::get('/mi-cuenta/email/confirmar/{id}/{hash}', [EmailChangeController::class, 'confirm'])
    ->middleware(['signed', 'throttle:6,1'])->name('account.email.confirm');

// Páginas de texto legal (contenido desde la tabla `pages`).
foreach (['privacidad', 'condiciones', 'waiver', 'cookies', 'aviso-legal'] as $slug) {
    Route::get('/'.$slug, fn () => app(PageController::class)->show($slug))->name('legal.'.$slug);
}

// Consentimiento de cookies (#219): el banner registra la decisión (aceptar/rechazar/configurar) y
// el servidor escribe la cookie canónica + una fila de prueba. POST con CSRF estándar (mismo origen)
// + throttle. Controlador plano (no Livewire) → funciona para visitantes anónimos.
Route::post('/cookies/consentimiento', [CookieConsentController::class, 'store'])
    ->middleware('throttle:30,1')->name('cookies.consent');

// Normas del parque (desde `park_rules`).
Route::get('/normas', [PageController::class, 'rules'])->name('normas');

// Páginas de catálogo (contenido desde la BD).
Route::get('/precios', PricingController::class)->name('precios');
Route::get('/cumpleanos', EventsController::class)->name('cumpleanos');

// Servicios: página data-driven (#256, modelo A). Las secciones editoriales salen de la entidad CMS
// `LandingService` (panel); cada una conserva su anchor estable (el nav enlaza a /servicios#slug).
Route::get('/servicios', ServicesController::class)->name('servicios');

// Compra de entradas (Fase 5.2): el sidebar de compra se abre sobre la página. `/entradas`
// es un enlace profundo: renderiza la home y abre el sidebar (vía data-purchase-open en el layout).
Route::get('/entradas', HomeController::class)->name('entradas');

// Pagos Redsys (Fase 5.5): vuelta del navegador (UrlOK/UrlKO) y notificación servidor-a-servidor.
// UrlOK/UrlKO aceptan **GET y POST**: el método depende de la configuración del terminal
// Redsys (verificado empíricamente 2026-05-26, #106) — el ejemplo oficial v2.0
// `ejemploRecepcionaPet.php` hace `array_merge($_GET, $_POST, $jsonBody)` por la misma razón.
// La notificación es POST estricto (server-to-server). Las 3 rutas están EXCLUIDAS de CSRF
// en `bootstrap/app.php` (POST cross-site sin cookie; autenticidad por `Ds_Signature`
// HMAC SHA-512 V2). Procesamiento real = `RedsysReturnHandler` (#104, PLAN-REDSYS §13).
// Rate-limit GENEROSO por IP (auditoría Fase 1, L5): no interfiere con los reintentos legítimos de
// Redsys, pero acota el log-flooding / amplificación de CPU (cada petición cuesta cripto AES+HMAC +
// varios Setting::value). La firma sigue siendo la barrera de autenticidad; esto solo es disponibilidad.
Route::middleware('throttle:120,1')->group(function (): void {
    Route::match(['get', 'post'], '/pago/redsys/retorno-ok', [RedsysReturnController::class, 'returnOk'])
        ->name('payments.redsys.return.ok');
    Route::match(['get', 'post'], '/pago/redsys/retorno-ko', [RedsysReturnController::class, 'returnKo'])
        ->name('payments.redsys.return.ko');
    Route::post('/pago/redsys/notificacion', [RedsysReturnController::class, 'notification'])
        ->name('payments.redsys.notification');
});

// Contacto (formulario → guarda en BD + envía email).
Route::get('/contacto', [ContactController::class, 'show'])->name('contacto');
Route::post('/contacto', [ContactController::class, 'store'])
    ->middleware('throttle:5,1') // A5/A4 auditoría Fase 1: corta el mail-bombing al admin (envío SMTP síncrono)
    ->name('contacto.store');

// Post-formulario de datos por invitado de un cumpleaños (#217): el cliente rellena los datos de
// cada niño DESPUÉS de reservar. INDIVIDUALIZADO POR RESERVA — el parámetro es el `OrderItem` del
// pack (1 post-form por reserva, no por pedido). Acceso por enlace FIRMADO del email (sin sesión; la
// firma prueba la titularidad de ESA reserva) o autenticado desde "Mis pedidos" — el controlador
// valida AMBOS (no usa el middleware `signed` para no excluir al dueño autenticado). POST con CSRF
// estándar + throttle. `no-store` (L1): la página lleva nombres+alergias de menores (art. 9).
Route::get('/reserva/{reservation}/datos-invitados', [GuestFormController::class, 'show'])
    ->middleware('no-store')
    ->name('reservation.guests');
Route::post('/reserva/{reservation}/datos-invitados', [GuestFormController::class, 'store'])
    ->middleware(['throttle:30,1', 'no-store'])->name('reservation.guests.store');

// El JUSTIFICANTE de un menor INVITADO a una reserva («waiver offshore», `#328`): un adulto SIN
// cuenta autoriza a un menor que no es menor a cargo de quien reservó. Va por PEDIDO —es «el papelito
// de la excursión», uno solo que el responsable reparte— y el acceso lo da la firma HMAC del enlace,
// porque quien lo abre no tiene sesión.
//
// ⚠️ El POST es la superficie MÁS expuesta del producto: pública, sin sesión y **crea personas**.
// Lleva `throttle` por IP además de Turnstile y del honeypot (`SEC-06`): un CAPTCHA resuelto no es una
// barrera de volumen. El tope por pedido y la ventana temporal los impone el DOMINIO bajo el lock.
// `no-store` (`RGPD-04`): la pantalla lleva el nombre y la fecha de nacimiento de un menor.
Route::get('/autorizacion/{reservation}', [GuardianAuthorizationController::class, 'show'])
    ->middleware('no-store')
    ->name('reservation.authorization');
Route::post('/autorizacion/{reservation}', [GuardianAuthorizationController::class, 'store'])
    ->middleware(['throttle:10,1', 'no-store'])
    ->name('reservation.authorization.store');

// SEO: mapa del sitio para buscadores.
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

Route::get('/lang/{locale}', function (string $locale) {
    if (in_array($locale, SetLocale::SUPPORTED, true)) {
        session(['locale' => $locale]);
    }

    // No hacer `back()` ciego: prioriza la cabecera `Referer` (controlada por el cliente) sobre la
    // sesión → un `Referer` externo lo convertía en open-redirect (phishing con lavado por el dominio
    // de confianza; auditoría Fase 1 · Sistema 6 · W7). Solo redirigimos al destino previo si es del
    // MISMO host (o una ruta relativa sin host); en cualquier otro caso, a la home.
    $previous = url()->previous();
    $host = parse_url($previous, PHP_URL_HOST);

    return redirect()->to($host === null || $host === request()->getHost() ? $previous : '/');
})->name('lang.switch');

// Panel admin (Fase 7.0): cambio de idioma del panel — separado del de la web pública.
// Soporta solo `es` y `zh_CN` (decisión #123). Persistido en `users.panel_locale`.
Route::post('/admin/lang/{locale}', PanelLocaleController::class)
    ->middleware(['web', 'auth', 'panel_role'])
    ->name('admin.lang.switch');

// Panel admin — Fase 7.1a (decisiones #119, #126): validar registro en puerta.
// Página dedicada (Livewire minimal, fuera del shell Filament) pensada para
// tablet/PC dedicado en la entrada del parque. Aplica el locale del panel
// (ES/ZH_CN) para coherencia con el resto del entorno admin.
Route::get('/admin/puerta/validar', ValidarRegistro::class)
    ->middleware(['web', 'auth', 'panel_role', SetAdminLocale::class])
    ->name('admin.puerta.validar');

// Panel admin — Hoja de reserva imprimible (PDF A4, decisión #183): un OrderItem
// principal como hoja operativa para puerta/sala. Permiso `orders.view`. La hoja
// se fuerza en español en el controlador (documento del personal del parque).
// `throttle:30,1` acota el abuso de ancho de banda (generación de PDF).
Route::get('/admin/pedidos/{order}/items/{item}/imprimir', ReservationSlipController::class)
    ->middleware(['web', 'auth', 'panel_role', SetAdminLocale::class, 'throttle:30,1', 'no-store'])
    ->name('admin.orders.items.slip'); // L1: la hoja imprime nombres+alergias de menores (art. 9).

// Panel admin — Fase 6 · waiver: PDF del REGISTRO probatorio de una firma (`specs/waiver-probatorio.md`
// §4.5). Permiso PROPIO `waiver.view` (comprobado en el controlador) + IDOR (la firma debe ser del
// usuario de la URL) + auditoría de cada consulta. `no-store` (`RGPD-04`): lleva nombre, email, ip y
// user-agent del firmante. Se sirve en el idioma del texto firmado.
Route::get('/admin/usuarios/{user}/waiver/{signature}/pdf', WaiverProofController::class)
    ->middleware(['web', 'auth', 'panel_role', SetAdminLocale::class, 'throttle:30,1', 'no-store'])
    ->name('admin.users.waiver.proof');

// Panel admin — Fase 7.4 (decisión #14): feed JSON del calendario unificado.
// Eventos = productos individuales de pedidos pagados, acotados al rango que
// pide FullCalendar. Permiso `calendar.view` (validado también en el controlador).
// `no-store` (auditoría Fase 1, Sistema 5): el feed emite nombres de pila de clientes + códigos de
// pedido por reserva del rango (hasta 120 días). Es un controlador que devuelve `response()->json()`
// (no Livewire) → Symfony solo pone `no-cache, private`; se fuerza `no-store` como en sus hermanas
// con PII (slip y resumen-día), evitando la persistencia en disco de tablets compartidas de puerta.
Route::get('/admin/calendario/eventos', CalendarEventsController::class)
    ->middleware(['web', 'auth', 'panel_role', SetAdminLocale::class, 'no-store'])
    ->name('admin.calendario.eventos');

// Panel admin — Resumen del día imprimible (PDF A4 horizontal, decisión #184):
// listado de las reservas/entradas de un día (filtro todas/cumpleaños/entradas).
// Botón "Imprimir resumen" en Calendario y Escritorio. Permiso `calendar.view`;
// la hoja se fuerza en español en el controlador. `throttle` por ancho de banda.
Route::get('/admin/calendario/resumen-dia', DailySummaryController::class)
    ->middleware(['web', 'auth', 'panel_role', SetAdminLocale::class, 'throttle:30,1', 'no-store'])
    ->name('admin.calendario.resumen-dia'); // L1: el resumen lista clientes/teléfonos/cumpleañeros (PII).
