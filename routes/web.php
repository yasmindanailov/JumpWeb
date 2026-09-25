<?php

use App\Domain\Booking\Services\PartyInvitations;
use App\Http\Controllers\Account\AccountController;
use App\Http\Controllers\Account\EmailChangeController;
use App\Http\Controllers\Admin\AnalyticsExportController;
use App\Http\Controllers\Admin\CalendarEventsController;
use App\Http\Controllers\Admin\DailySummaryController;
use App\Http\Controllers\Admin\GoogleBusinessConnectController;
use App\Http\Controllers\Admin\PanelLocaleController;
use App\Http\Controllers\Admin\ReservationSlipController;
use App\Http\Controllers\Admin\SegmentsExportController;
use App\Http\Controllers\Admin\WaiverProofController;
use App\Http\Controllers\AttractionsController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\BarController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CookieConsentController;
use App\Http\Controllers\EventsController;
use App\Http\Controllers\GuardianAuthorizationController;
use App\Http\Controllers\GuestFormController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InvitationPageController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PaqueteDelCajonController;
use App\Http\Controllers\Payments\RedsysReturnController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\ReviewPhotoController;
use App\Http\Controllers\ServicesController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SurveyPageController;
use App\Http\Instancia\InstancePages;
use App\Http\Middleware\ResolveVisitor;
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
// **Vincular** a la cuenta en la que ya se está (`#347`, §21.3). Ruta APARTE y no un `?intent=` sobre
// la de arriba: así la protege `auth` —sin sesión no hay nada que vincular— y la intención se anota
// en el reto del SERVIDOR, que es lo único que impide que la ponga quien vuelve.
// ⚠️ **`auth` y NO `verified`**: vincular no exige el correo verificado, por la misma puerta y el
// mismo motivo que `#332` abrió el área de cuenta — y porque quien viene de Google trae su propia
// prueba del buzón, que aquí ni siquiera se usa para identificar.
Route::get('/auth/google/vincular', [GoogleAuthController::class, 'link'])
    ->middleware(['auth', 'throttle:10,1'])->name('auth.google.link');
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

// Las imágenes de las reseñas de Google, servidas desde NUESTRO servidor (T2·4 de
// `specs/google-business-profile.md` §4.3·6, `DECISIONES #730`).
//
// ⚠️⚠️ **Es lo que quita la dependencia del consentimiento**: mientras la cara del autor se pidiera
// a `lh3.googleusercontent.com`, la petición la hacía el NAVEGADOR DEL VISITANTE y sin la categoría
// `maps` aceptada no se podía pintar (`RGPD-05`). Desde aquí la portada no le pide nada a nadie.
// ⚠️ **Pública y sin firma a propósito**: la portada la ve cualquiera y se cachea; una URL firmada
// caduca y dejaría huecos. Lo que protege es la FORMA del nombre —el hash— no un permiso.
// ⚠️ El `.` del nombre iría fuera del parámetro con el `where` por defecto, así que se declara.
// ⚠️ La restricción es ANCHA a propósito: quien decide es `GoogleReviewImages::isOwnName()`, en el
// controlador, que es la MISMA regla que usa el borrado. Con la forma exacta escrita también aquí,
// habría dos sitios que definen qué es un fichero nuestro y el del controlador dejaría de poder
// ponerse en rojo. Lo que sí hace falta aquí es que no entre una barra, y eso ya lo hace `{fichero}`.
Route::get('/resenas/foto/{fichero}', ReviewPhotoController::class)
    ->where('fichero', '[A-Za-z0-9._-]+')
    ->name('resenas.foto');

// Páginas de catálogo (contenido desde la BD).
Route::get('/precios', PricingController::class)->name('precios');
Route::get('/cumpleanos', EventsController::class)->name('cumpleanos');

// Las 23 atracciones, con su zona en la pestaña (carril de diseño, T2d · `Atracciones PJP` 1a/1c).
// ⚠️ La sección 03 de la portada enseña CINCO y su única puerta lleva aquí: esta ruta es el destino
// que la regla del canvas exige («si dos secciones cerradas apuntan al mismo destino inexistente,
// ese destino existe: hay que escribirlo»). La zona de llegada va en `?zona=`, no en el hash.
Route::get('/atracciones', AttractionsController::class)->name('atracciones');

// Servicios: página data-driven (#256, modelo A). Las secciones editoriales salen de la entidad CMS
// `LandingService` (panel); cada una conserva su anchor estable (el nav enlaza a /servicios#slug).
Route::get('/servicios', ServicesController::class)->name('servicios');

// El bar (`#536`, carril de diseño Fase 3 · T3b, artboard `Bar PJP`). ⚠️ La CARTA se publica como
// IMAGEN (`[DECIDIDO owner]`), subida en «Ajustes → El bar». ⚠️⚠️ **Sin nombre del bar en el panel
// la ruta da 404**: el titular de la página es el nombre del local y el producto no se lo inventa
// —lo decide `BarPage::isPublished()`, el mismo predicado que decide si el destino sale en el menú,
// en el pie y en la portada—.
Route::get('/bar', BarController::class)->name('bar');

// Compra de entradas (Fase 5.2): el sidebar de compra se abre sobre la página. `/entradas`
// es un enlace profundo: renderiza la home y abre el sidebar (vía data-purchase-open en el layout).
Route::get('/entradas', HomeController::class)->name('entradas');

// El PAQUETE del cajón (F4 · T5, `specs/cajon-empaquetable.md` §4.1): la ruta estable del cargador, para
// que una landing que no pinta el producto pueda escribir su `<script>` una vez y no volver a tocarlo en
// cada despliegue. La otra línea del paquete es la hoja, `/css/cajon.css`, que ya es estática.
Route::get('/cajon/paquete.js', PaqueteDelCajonController::class)->name('cajon.paquete');

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

// ═══ LAS PÁGINAS ENFOCADAS DE LA FIESTA: EL INVITADO NO ES UN VISITANTE ═══════════════════════════
// (`specs/analitica-fiesta.md` §4.1, `DECISIONES #739`). El post-form, el justificante y la invitación
// salen del acuñado de la cookie del visitante: `ResolveVisitor:mint` no corre aquí, así que quien abre
// una de estas páginas no recibe `visitor_id` ni se ata a la que traiga de otra visita. Lo que hace queda
// como HECHO DE LA RESERVA (`Recorder::factOfOrder()`), sin cookie. Las técnicas (sesión, XSRF) siguen:
// los formularios las necesitan. `FocusedPagesAreCookieFreeTest` vigila que ninguna ruta de estos tres
// controladores se quede fuera del grupo.
Route::withoutMiddleware([ResolveVisitor::class.':'.ResolveVisitor::MINT])->group(function (): void {

    // Post-formulario de datos por invitado de un cumpleaños (#217): el cliente rellena los datos de
    // cada niño DESPUÉS de reservar. INDIVIDUALIZADO POR RESERVA — el parámetro es el `OrderItem` del
    // pack (1 post-form por reserva, no por pedido). Acceso por enlace FIRMADO del email (sin sesión; la
    // firma prueba la titularidad de ESA reserva) o autenticado desde "Mis pedidos" — el controlador
    // valida AMBOS (no usa el middleware `signed` para no excluir al dueño autenticado). POST con CSRF
    // estándar + throttle. `no-store` (L1): la página lleva nombres+alergias de menores (art. 9).
    //
    // ⚠️⚠️ **`->missing()` NO es cosmético: sostiene la escalada 403 → 410 → 404** (`RGPD-03`,
    // `Http\Concerns\AuthorizesGuestForm`). Con el *route model binding* implícito a secas, un id
    // INEXISTENTE responde 404 **antes** de que corra la autorización, y uno existente 403: la
    // diferencia le cuenta a cualquier desconocido qué reservas hay. Medido con `curl` el 2026-09-03,
    // antes de este cambio: id existente → 403, id inventado → 404, mientras la API respondía 403 a los
    // dos porque su controlador resuelve a mano. *El orden de la escalada solo se sostiene si nada
    // responde antes que ella.*
    Route::get('/reserva/{reservation}/datos-invitados', [GuestFormController::class, 'show'])
        ->middleware('no-store')
        ->missing(fn () => abort(403))
        ->name('reservation.guests');
    // ⚠️ **DOS limitadores y no uno** (`SEC-06`, D12 de `specs/complementos-post-reserva.md`): el de
    // siempre —por IP, contra el barrido— y `guest-form`, que limita **por RESERVA**. Desde que aquí se
    // compran extras este formulario mueve dinero, y su enlace viaja por correo y se reenvía: sin el
    // segundo, treinta peticiones por minuto **por cada IP** caben sobre la misma reserva, con sus
    // treinta correos al titular — que es la única señal de que alguien está encargando en su nombre.
    Route::post('/reserva/{reservation}/datos-invitados', [GuestFormController::class, 'store'])
        ->middleware(['throttle:30,1', 'throttle:guest-form', 'no-store'])
        ->missing(fn () => abort(403))
        ->name('reservation.guests.store');
    // PERSONALIZAR la invitación digital de esa reserva (T6·1, `specs/celebracion-e-invitacion.md` §4.7).
    //
    // ⚠️⚠️ **Endpoint APARTE del de guardar, y no por comodidad**: personalizar escribe SOLO
    // `party_invitations`, mientras que `order_items.updated_at` es el testigo con el que el formulario
    // detecta que el parque movió la reserva. Metido dentro del POST de siempre, cambiar el color de una
    // banda dejaría obsoleta la página que el anfitrión tiene abierta y le tumbaría los extras. Es la
    // misma separación que la API tomó en `InvitationHostController`, por el mismo motivo.
    //
    // ⚠️ Mismos dos limitadores, misma escalada y mismo `no-store` que el formulario: es su misma puerta
    // —el trait `AuthorizesGuestForm`— y lo que se sirve al volver sigue llevando datos de menores.
    Route::post('/reserva/{reservation}/invitacion', [GuestFormController::class, 'updateInvitation'])
        ->middleware(['throttle:30,1', 'throttle:guest-form', 'no-store'])
        ->missing(fn () => abort(403))
        ->name('reservation.invitation.update');
    // «No lo apuntes» (T6·3, §7.2·R11): el anfitrión retira una respuesta de su lista.
    //
    // ⚠️⚠️ **Es su propio POST por el mismo motivo que personalizar**: descartar escribe SOLO
    // `invitation_replies`, y meterlo en el guardado de siempre movería el testigo de los extras. Y es el
    // gesto que evita que se quede ATRAPADO: desde `#576` un «sí» pendiente sube el suelo por debajo del
    // cual no puede bajar el número de invitados, así que sin esto no tendría forma de retirarlo.
    Route::post('/reserva/{reservation}/invitacion/descartar', [GuestFormController::class, 'dismissReply'])
        ->middleware(['throttle:30,1', 'throttle:guest-form', 'no-store'])
        ->missing(fn () => abort(403))
        ->name('reservation.invitation.dismiss');
    // «Escribir el recordatorio» (T6·6, §4.7): compone el texto para que el anfitrión lo pegue donde ya
    // repartió el enlace, y deja escrito que avisó.
    //
    // ❗❗ **NO ENVÍA NADA** (§2.2): del padre no tenemos correo y no se le pide. Por eso es un POST y no
    // un GET —escribe `reminded_at` y `reminded_count`—, pero lo único que viaja de vuelta es un texto.
    //
    // ⚠️ Su propio POST, como sus dos hermanas y por lo mismo: escribe SOLO `party_invitations` y meterlo
    // en el guardado de siempre movería `order_items.updated_at`, que es el testigo de los extras.
    Route::post('/reserva/{reservation}/invitacion/recordatorio', [GuestFormController::class, 'writeReminder'])
        ->middleware(['throttle:30,1', 'throttle:guest-form', 'no-store'])
        ->missing(fn () => abort(403))
        ->name('reservation.invitation.remind');

    // El JUSTIFICANTE de un menor INVITADO a una reserva («waiver offshore», `#328`): un adulto SIN
    // cuenta autoriza a un menor que no es menor a cargo de quien reservó. Va por PEDIDO —es «el papelito
    // de la excursión», uno solo que el responsable reparte— y el acceso lo da la firma HMAC del enlace,
    // porque quien lo abre no tiene sesión.
    //
    // ⚠️ El POST es la superficie MÁS expuesta del producto: pública, sin sesión y **crea personas**.
    // Lleva `throttle` por IP además de Turnstile y del honeypot (`SEC-06`): un CAPTCHA resuelto no es una
    // barrera de volumen. El tope por pedido y la ventana temporal los impone el DOMINIO bajo el lock.
    // `no-store` (`RGPD-04`): la pantalla lleva el nombre y la fecha de nacimiento de un menor.
    //
    // ⚠️ Y el mismo `->missing()` que el post-form, por el mismo motivo: `AuthorizesGuardianAuthorization`
    // repite la escalada 403 → 410 → 404 y el binding implícito la cortocircuitaba igual.
    Route::get('/autorizacion/{reservation}', [GuardianAuthorizationController::class, 'show'])
        ->middleware('no-store')
        ->missing(fn () => abort(403))
        ->name('reservation.authorization');
    Route::post('/autorizacion/{reservation}', [GuardianAuthorizationController::class, 'store'])
        ->middleware(['throttle:10,1', 'no-store'])
        ->missing(fn () => abort(403))
        ->name('reservation.authorization.store');

    // ── La INVITACIÓN DIGITAL de una fiesta (`specs/celebracion-e-invitacion.md` §4.6, T5·1) ──────────
    //
    // La tercera página enfocada y pública del producto, y la que tiene la credencial más rara de las
    // tres: un TOKEN en la URL. Las otras dos se abren con una firma HMAC sobre la URL exacta; ésta con
    // doce caracteres opacos que son una fila, porque su enlace se reparte **a un grupo de clase entero**
    // por un chat de padres y tiene que poder anularse sin esperar a ninguna caducidad.
    //
    // ⚠️ **El nombre `invitation.show` es contrato**: `PartyInvitations::PUBLIC_ROUTE` pregunta por él
    // para componer el enlace que reparte el anfitrión, y por eso el campo `url` de la API se rellena
    // solo desde que esta línea existe. Renombrarla lo devuelve a `null` **sin romper ningún test** — hay
    // un caso que lo vigila (`InvitationPageTest`).
    //
    // ⚠️ `no-store` (`RGPD-04`): se entra sin sesión y lo que se sirve es el nombre y la edad de un menor.
    // El `Referrer-Policy: no-referrer` lo pone el controlador — sin él, «Cómo llegar» le mandaría el
    // token a Google en el `Referer`.
    //
    // ⚠️ El regex del token va en la ruta: un identificador que no tiene su forma ni llega a mirarse
    // contra la base de datos.
    Route::get('/invitacion/{token}', [InvitationPageController::class, 'show'])
        ->where('token', '[A-Za-z0-9]{12}')
        ->middleware(['throttle:60,1', 'no-store'])
        ->name(PartyInvitations::PUBLIC_ROUTE);
    // Contestar. ⚠️ Es la superficie MÁS expuesta de esta feature: pública, sin sesión y **escribe en
    // nombre de un desconocido**. Lleva las tres defensas que no se sustituyen entre sí — Turnstile en el
    // controlador, límite por IP aquí y el tope `3 × invitados` en el DOMINIO, que es el único que no se
    // puede esperar a que expire. Y el `throttle:invitation-reply` por TOKEN, porque el enlace lo tiene un
    // grupo de clase entero y un techo por IP no dice gran cosa entre familias distintas.
    Route::post('/invitacion/{token}', [InvitationPageController::class, 'reply'])
        ->where('token', '[A-Za-z0-9]{12}')
        ->middleware(['throttle:20,1', 'throttle:invitation-reply', 'no-store'])
        ->name('invitation.reply');

    // «Añadir al calendario» (§4.6, T5·4): el `.ics` de la fiesta, servido por el servidor.
    //
    // ⚠️ Mismo portero y mismo 404 que la página —`resolvePublic()` en el controlador—: una ruta que
    // distinguiera un token caducado de uno inventado abriría la rendija que §4.5·12 cerró. Y `no-store`
    // porque el fichero lleva el nombre del niño y la dirección del parque.
    Route::get('/invitacion/{token}/calendario.ics', [InvitationPageController::class, 'calendar'])
        ->where('token', '[A-Za-z0-9]{12}')
        ->middleware(['throttle:60,1', 'no-store'])
        ->name('invitation.calendar');

    // ── LA ENCUESTA POR CORREO (`specs/encuestas.md` §4.3, T3; `#740`) ──
    // El token de 40 caracteres es la credencial entera (como en la invitación): sin sesión, sin cookie de
    // medición (este grupo), `no-store` (`RGPD-04`) y UN solo 404 para lo inventado, lo contestado y lo apagado.
    // El regex va en la ruta: lo que no tiene su forma ni llega a mirarse contra la base de datos.
    Route::get('/encuesta/{token}', [SurveyPageController::class, 'show'])
        ->where('token', '[A-Za-z0-9]{40}')
        ->middleware(['throttle:60,1', 'no-store'])
        ->name('survey.show');
    // Contestar: escribe UNA sola vez (bajo candado en el dominio); limitador por IP (`SEC-06`), el token acota.
    Route::post('/encuesta/{token}', [SurveyPageController::class, 'answer'])
        ->where('token', '[A-Za-z0-9]{40}')
        ->middleware(['throttle:20,1', 'no-store'])
        ->name('survey.answer');
    // «Gracias» en su propia URL (POST → redirect → GET): recargar no reenvía nada.
    Route::get('/encuesta/{token}/gracias', [SurveyPageController::class, 'thanks'])
        ->where('token', '[A-Za-z0-9]{40}')
        ->middleware(['throttle:60,1', 'no-store'])
        ->name('survey.thanks');
    // «No quiero recibir más encuestas»: una página con UN botón y su confirmación. Un enlace que escribiera al
    // abrirse lo pulsarían los escáneres de enlaces de los gestores de correo.
    Route::get('/encuesta/{token}/baja', [SurveyPageController::class, 'optOut'])
        ->where('token', '[A-Za-z0-9]{40}')
        ->middleware(['throttle:60,1', 'no-store'])
        ->name('survey.optout');
    Route::post('/encuesta/{token}/baja', [SurveyPageController::class, 'confirmOptOut'])
        ->where('token', '[A-Za-z0-9]{40}')
        ->middleware(['throttle:20,1', 'no-store'])
        ->name('survey.optout.confirm');

    // El RECIBO de una respuesta (§4.5·6): **DOS HORAS** y no es un enlace de edición (D9).
    //
    // ⚠️⚠️ Lo autoriza la FIRMA de la URL, **no el token de la invitación**: son dos alcances distintos —
    // el token abre la fiesta entera, esto abre UNA respuesta—. Mezclarlos le daría a cualquiera con el
    // enlace de la fiesta los datos de todos los niños.
    //
    // ⚠️ `signed` va en la ruta: pasadas las dos horas Laravel responde 403 **antes** de que el
    // controlador mire nada. Y `no-store` porque lo que se sirve son las alergias de un menor (art. 9).
    Route::get('/invitacion/recibo/{reply}', [InvitationPageController::class, 'receipt'])
        ->whereNumber('reply')
        ->middleware(['signed', 'throttle:60,1', 'no-store'])
        ->missing(fn () => abort(403))
        ->name(PartyInvitations::RECEIPT_ROUTE);
    Route::post('/invitacion/recibo/{reply}', [InvitationPageController::class, 'saveReceipt'])
        ->whereNumber('reply')
        ->middleware(['signed', 'throttle:20,1', 'no-store'])
        ->missing(fn () => abort(403))
        ->name('invitation.receipt.save');

});
// ═══ fin de las páginas enfocadas de la fiesta ════════════════════════════════════════════════════

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

// Panel admin — conectar la FICHA DE GOOGLE (`specs/google-business-profile.md` §4.2·2, `#524`).
// ⚠️⚠️ **La URI que hay que dar de alta en el cliente OAuth CENTRAL de JumpSystem es la RUTA COMPLETA
// de `callback`** (`https://<host>/admin/ficha-google/callback`), una por instalación (§7·A·5). Con el
// origen pelado, el primer intento devuelve `Error 400: redirect_uri_mismatch` — lo pagó el login.
// ⚠️ **`settings.manage` se comprueba EN EL CONTROLADOR, en la ida y en la vuelta**: `panel_role` deja
// pasar a `staff`, y entre las dos peticiones caben un cambio de rol y un cambio de sesión.
// ⚠️ La ida es POST: abrir el reto escribe en la sesión del admin, y un GET lo dejaría al alcance de
// cualquier página que le cargue una imagen. El callback es GET porque lo redirige Google.
// Limitadores en las dos (`SEC-06`, §4.2·2).
Route::post('/admin/ficha-google/conectar', [GoogleBusinessConnectController::class, 'connect'])
    ->middleware(['web', 'auth', 'panel_role', 'throttle:10,1'])
    ->name('admin.google_business.connect');
Route::get('/admin/ficha-google/callback', [GoogleBusinessConnectController::class, 'callback'])
    ->middleware(['web', 'auth', 'panel_role', 'throttle:30,1'])
    ->name('admin.google_business.callback');
// Elegir la ficha (§4.2·4). POST: cambia de qué negocio son las reseñas que publica la portada.
Route::post('/admin/ficha-google/elegir', [GoogleBusinessConnectController::class, 'chooseLocation'])
    ->middleware(['web', 'auth', 'panel_role', 'throttle:20,1'])
    ->name('admin.google_business.choose');
// Ocultar una reseña y dejar de ocultarla (T2·5, §4.3·7, `#731`). POST las dos: ocultar borra en el
// acto el nombre, la foto y el texto de un tercero, y eso no puede depender de abrir un enlace.
// ⚠️ El limitador es MÁS ancho que el de conectar: ocultar es una tarea de repaso, y un admin que
// atienda varias peticiones seguidas no puede chocar con un 429.
Route::post('/admin/ficha-google/ocultar', [GoogleBusinessConnectController::class, 'hideReview'])
    ->middleware(['web', 'auth', 'panel_role', 'throttle:60,1'])
    ->name('admin.google_business.hide_review');
Route::post('/admin/ficha-google/mostrar', [GoogleBusinessConnectController::class, 'unhideReview'])
    ->middleware(['web', 'auth', 'panel_role', 'throttle:60,1'])
    ->name('admin.google_business.unhide_review');
// Desconectar (§4.2·8). **Solo POST**: retirar el permiso sobre la ficha del parque no puede
// depender de que alguien abra un enlace.
Route::post('/admin/ficha-google/desconectar', [GoogleBusinessConnectController::class, 'disconnect'])
    ->middleware(['web', 'auth', 'panel_role', 'throttle:10,1'])
    ->name('admin.google_business.disconnect');

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

// Panel admin — «Analítica», el CSV (`specs/analitica.md` §4.5, T2d): un informe (dinero · registros y puerta ·
// conversión) y un periodo. Permiso `reports.export` en el controlador (el botón solo lo esconde), auditado con
// recuento y sin PII: el fichero solo lleva agregados. `no-store`: son las cifras del parque.
Route::get('/admin/analitica/csv', AnalyticsExportController::class)
    ->middleware(['web', 'auth', 'panel_role', SetAdminLocale::class, 'throttle:30,1', 'no-store'])
    ->name('admin.analitica.csv');
// Y la exportación de un SEGMENTO (`specs/analitica.md` §4.6, T4b): una lista de personas, solo las que dieron el
// opt-in de comunicaciones, con permiso PROPIO `analytics.export` en el controlador y rastro con el recuento.
Route::get('/admin/analitica/segmentos/csv', SegmentsExportController::class)
    ->middleware(['web', 'auth', 'panel_role', SetAdminLocale::class, 'throttle:30,1', 'no-store'])
    ->name('admin.analitica.segmentos.csv');

/*
 * ══ EL LABORATORIO DE FACHADA ══════════════════════════════════════════════════════════════════
 * `DECISIONES #545` · pasada de vestido (`#497`). Dos pantallas donde el owner elige MIRANDO cómo
 * se coloca el material de fachada, con el mecanismo real (`<x-site.ilu>` sobre el kit de la
 * instalación) y no con una maqueta aparte.
 *
 * ❗❗❗ **CERRADAS POR ENTORNO Y TEMPORALES.** Fuera de `local` la ruta **no se registra**, así que
 * en producción es un 404 y no una página oculta: una pantalla de prototipo alcanzable por URL es
 * exactamente la clase de superficie que nadie revisa y que acaba indexada. Llevan además su
 * `noindex`. ▶ El owner ya eligió (la variante 2, `#580`) y 14 de las 18 ranuras que esto declaraba
 * tienen hoy pantalla de producción: el día que estas dos rutas se vayan, con ellas se van solo las
 * cuatro manchas que nadie más pinta (`IllustrationKit::SLOTS`, «EL LABORATORIO DE FACHADA»).
 *
 * ⚠️ Sin `web` completo a propósito: no necesitan sesión, ni CSRF, ni cookies. Lo único que piden
 * es el idioma, para que los tokens y las fuentes sean los de la web.
 */
if (app()->environment('local')) {
    Route::get('/_diseno/splash', fn () => view('lab.splash'))->name('lab.splash');
    Route::get('/_diseno/siluetas', fn () => view('lab.siluetas'))->name('lab.siluetas');
}

/*
 * **LAS PÁGINAS QUE DECLARA EL PAQUETE DE LA INSTANCIA** (T4b de `specs/isla-y-landing-nueva.md` §4.2; `#681`):
 * «kids» o «jump» son nombres de un cliente, no rutas del producto. Van las ÚLTIMAS a propósito: así ven todas las
 * del producto y descartan la página que pisaría una (`InstancePages::registrarRutas()`). ⚠️ Las rutas se cachean
 * al desplegar: una página nueva en el paquete necesita volver a construir esa caché.
 */
app(InstancePages::class)->registrarRutas(app('router'));
