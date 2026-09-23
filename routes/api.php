<?php

use App\Domain\Identity\Services\ApiTokenIssuer;
use App\Http\Controllers\Api\V1\AuthRegistrationController;
use App\Http\Controllers\Api\V1\AuthSessionController;
use App\Http\Controllers\Api\V1\AuthTokenController;
use App\Http\Controllers\Api\V1\AvailabilityController;
use App\Http\Controllers\Api\V1\BookingStatusController;
use App\Http\Controllers\Api\V1\CartLineController;
use App\Http\Controllers\Api\V1\CatalogAddonsController;
use App\Http\Controllers\Api\V1\CatalogProductsController;
use App\Http\Controllers\Api\V1\CatalogZonesController;
use App\Http\Controllers\Api\V1\ConfigController;
use App\Http\Controllers\Api\V1\FaqsFactsController;
use App\Http\Controllers\Api\V1\GoogleSignupController;
use App\Http\Controllers\Api\V1\GuestFormController;
use App\Http\Controllers\Api\V1\InvitationHostController;
use App\Http\Controllers\Api\V1\InvitationsController;
use App\Http\Controllers\Api\V1\LegalDocumentsController;
use App\Http\Controllers\Api\V1\LegalWaiverController;
use App\Http\Controllers\Api\V1\MeAccountContextController;
use App\Http\Controllers\Api\V1\MeCardController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\MeCredentialsController;
use App\Http\Controllers\Api\V1\MeDependentsController;
use App\Http\Controllers\Api\V1\MeOrdersController;
use App\Http\Controllers\Api\V1\MePrivacyController;
use App\Http\Controllers\Api\V1\MeProfileController;
use App\Http\Controllers\Api\V1\MeReservationEligibilityController;
use App\Http\Controllers\Api\V1\MeReservationsController;
use App\Http\Controllers\Api\V1\MeWaiverController;
use App\Http\Controllers\Api\V1\OrderEventDataController;
use App\Http\Controllers\Api\V1\OrderGuestMinorsController;
use App\Http\Controllers\Api\V1\OrderPaymentController;
use App\Http\Controllers\Api\V1\OrderPaymentStatusController;
use App\Http\Controllers\Api\V1\OrdersController;
use App\Http\Controllers\Api\V1\PasswordRecoveryController;
use App\Http\Controllers\Api\V1\PricesFactsController;
use App\Http\Controllers\Api\V1\QuoteController;
use App\Http\Controllers\Api\V1\RulesFactsController;
use App\Http\Controllers\Api\V1\ScheduleFactsController;
use App\Http\Controllers\Api\V1\SidebarBootController;
use App\Http\Controllers\Api\V1\SiteFactsController;
use App\Http\Controllers\Api\V1\SocialProofFactsController;
use App\Http\Middleware\EnsureOnlineSalesEnabled;
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

    // ⚠️ **Toda ruta con `auth:sanctum` lleva además esta ability** (F4, `DECISIONES #630`). Los tokens
    // de un cliente nacen con UNA ability (`ApiTokenIssuer::ABILITY`), nunca con el comodín, y esta
    // superficie la exige: un token emitido mañana para otra cosa (la puerta, un kiosko) no abre la
    // cuenta de nadie. Con cookie de sesión Sanctum entrega un `TransientToken` que responde «sí» a
    // todo, así que la SPA no lo nota. Lo vigila `ApiTokenAbilityTest`: ruta autenticada sin ella, rojo.
    $tokenAbility = 'abilities:'.ApiTokenIssuer::ABILITY;

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
        ->middleware(['auth:sanctum', $tokenAbility])
        ->name('auth.logout');

    // ── Tokens Bearer (F4, `docs/specs/token-bearer.md`) — la puerta del cliente NATIVO ───────
    // Emitir es PÚBLICO y no pide sesión ni CSRF: es para quien no es un navegador de primera
    // parte. No es una segunda puerta para un atacante: `PasswordLogin::verify()` comparte con el
    // login los dos limitadores de `SEC-06` y sus claves. `no-store` porque la respuesta lleva una
    // credencial y `NoStoreWhenAuthenticated` no la ve (todavía no hay nadie autenticado).
    Route::post('/auth/tokens', [AuthTokenController::class, 'issue'])
        ->middleware('no-store')
        ->name('auth.tokens.issue');
    // Rotar no pasa por los limitadores del login —no hay contraseña que adivinar— pero sí por uno
    // propio: cada llamada escribe y borra una credencial.
    Route::post('/auth/tokens/rotate', [AuthTokenController::class, 'rotate'])
        ->middleware(['auth:sanctum', $tokenAbility, 'throttle:6,1', 'no-store'])
        ->name('auth.tokens.rotate');

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

    // ── Completar el alta que viene de GOOGLE (`specs/auth-con-google.md` §7) — PÚBLICO ───────
    // Públicas porque quien las usa **todavía no tiene cuenta**: acaba de volver de Google y le falta
    // el teléfono y las aceptaciones. Lo que las acota no es un guard, es que **el perfil vive en la
    // SESIÓN del servidor**: sin él las dos responden 404, y ni el `sub` ni el correo se aceptan de
    // la petición. Sin claves de Google configuradas, 404 también.
    Route::get('/auth/google/pending', [GoogleSignupController::class, 'pending'])->name('auth.google.pending');
    Route::post('/auth/google/complete', [GoogleSignupController::class, 'complete'])->name('auth.google.complete');

    // ── Ajustes de la instalación (Fase 4 · paso 4.0b) — PÚBLICO ────────────────────────────
    // Los cuatro valores que un cliente necesita para pintar el cajón bien a la primera: el bloque
    // de registro externo, el umbral del buscador, la clave pública del anti-bot y el tope de
    // líneas de la cesta. Sin ellos, un cliente aprende las reglas CHOCÁNDOSE (descubre el tope con
    // un 422), que es una regla de negocio naciendo en el cliente.
    //
    // Lo que cambia mientras el cliente navega NO va aquí: la pausa de reservas vive en su propio
    // endpoint, porque un snapshot de arranque mentiría en cuanto la dueña accionara el interruptor.
    Route::get('/config', ConfigController::class)->name('config.show');

    // ── El ARRANQUE del cajón (F4 · T1, `docs/specs/cajon-empaquetable.md` §4.5) — PÚBLICO ─────
    // Lo que el layout del producto pinta en `data-boot`, para una página que NO pinte Blade (la
    // landing a mano de una instancia). Es el MISMO modelo de lectura, `Http\Sidebar\SidebarBoot`, por
    // un segundo transporte. Dos rutas porque no se cachean igual:
    //  · `boot` — los rótulos del idioma y las rutas. No depende de quién mira y el idioma viaja en
    //    la URL (`?lang=`), así que se cachea: `public`, cinco minutos y `ETag` para revalidar con 304.
    //  · `session` — quién es el titular, su contexto y el desenlace de un pago pendiente, que se
    //    CONSUME al leerlo. `no-store` (`RGPD-04`). Pública a propósito: al anónimo le responde
    //    `null`, que es justo lo que el cajón necesita saber.
    Route::get('/sidebar/boot', [SidebarBootController::class, 'boot'])
        ->middleware('cache.headers:public;max_age=300;etag')
        ->name('sidebar.boot');
    Route::get('/sidebar/session', [SidebarBootController::class, 'session'])
        ->middleware('no-store')
        ->name('sidebar.session');

    // ── El MENÚ DE HECHOS (F5 · T1, `docs/specs/instancia-y-landing-fuera.md` §4.1) — PÚBLICO ───
    // La landing de una instancia deja de vivir dentro del producto, así que necesita LEER lo que
    // hasta ahora le inyectaba un `View::composer('*')`: identidad, dirección, contacto y redes.
    // Es un MENÚ: quien pinta la landing usa lo que quiera y **todo es opcional** (`#631`), y lo que
    // la instalación no ha rellenado no viaja (sin clave, no `""`).
    //
    // ⚠️ Se cachea como `/sidebar/boot` —`public`, cinco minutos, `ETag`— porque no depende de quién
    // mira. Y NO lleva `?lang=`: ninguno de sus campos se traduce (un NIF es el mismo en tres lenguas).
    // ⚠️⚠️ Sus claves salen por LISTA BLANCA (`SiteFactsResource::AJUSTES` y `PublicFacts`): la tabla
    // `settings` tiene `redsys_secret_key` a dos filas de `contact.email`.
    Route::get('/site', SiteFactsController::class)
        ->middleware('cache.headers:public;max_age=300;etag')
        ->name('site.facts');

    // El HORARIO, en dos lecturas por el mismo motivo que `boot` y `session`: no se cachean igual.
    // ⚠️ `/schedule` son los hechos del calendario —semana, temporadas, fechas especiales— y cambian
    // cuando alguien toca el panel: cinco minutos. `/schedule/now` cambia DOS VECES AL DÍA, y justo en
    // ese minuto es cuando importa: un minuto de caché, que acota el error y protege al servidor de una
    // landing que lo pida en cada visita.
    Route::get('/schedule', [ScheduleFactsController::class, 'schedule'])
        ->middleware('cache.headers:public;max_age=300;etag')
        ->name('schedule.facts');
    Route::get('/schedule/now', [ScheduleFactsController::class, 'now'])
        ->middleware('cache.headers:public;max_age=60;etag')
        ->name('schedule.now');

    // Las NORMAS del recinto. ⚠️ Primer plato del menú que se TRADUCE, así que el idioma viaja en la
    // URL (`?lang=`, obligatorio) como en `/sidebar/boot`: una respuesta cacheable tiene que ser
    // función de su URL, o la primera caché sirve francés a quien pidió español.
    Route::get('/rules', RulesFactsController::class)
        ->middleware('cache.headers:public;max_age=300;etag')
        ->name('rules.facts');

    // Las DUDAS que el negocio contesta. Mismo régimen que las normas —`?lang=` obligatorio, cinco
    // minutos, `ETag`— porque son la misma clase de cosa: texto que el panel opera y la web consume.
    // ⚠️ **Una duda a medias no viaja**: una pregunta sin respuesta publica algo que el negocio no
    // contesta. Hoy las portadas pintan el acordeón entero y solo el `FAQPage` salta las inservibles;
    // aquí la regla baja al DATO, que es donde una landing no la puede incumplir.
    Route::get('/faqs', FaqsFactsController::class)
        ->middleware('cache.headers:public;max_age=300;etag')
        ->name('faqs.facts');

    // Los TEXTOS LEGALES: el índice y cada documento, con los marcadores (`:legal_name`…) ya resueltos.
    // ⚠️ Cuelgan de `/legal/documents` y no de `/legal/{clave}` porque `/legal/waiver` ya existe y dice
    // otra cosa —el RÉGIMEN del justificante para el cajón—: una ruta genérica ahí la ensombrecería.
    // Los PRECIOS por tarifa: el desglose que pinta un carril de tarifas («Lunes a jueves» / «Viernes,
    // findes y festivos»). ⚠️ NO duplica `/catalog/products`, que publica el «desde» para el embudo:
    // aquí está el precio de CADA tarifa, que no vivía en ningún sitio público.
    Route::get('/prices', PricesFactsController::class)
        ->middleware('cache.headers:public;max_age=300;etag')
        ->name('prices.facts');

    Route::get('/legal/documents', [LegalDocumentsController::class, 'index'])
        ->middleware('cache.headers:public;max_age=300;etag')
        ->name('legal.documents.index');
    Route::get('/legal/documents/{clave}', [LegalDocumentsController::class, 'show'])
        ->middleware('cache.headers:public;max_age=300;etag')
        ->name('legal.documents.show');

    // La CIFRA de prueba social: media, recuento, fuente y enlace a la ficha (`#616`, `#646`).
    // ⚠️ **La única ruta del menú SIN `?lang=`**, y a propósito: una media, un recuento y una URL son
    // los mismos en los tres idiomas. El texto de la atribución («en Google») lo pone quien pinta.
    // ⚠️⚠️ Las RESEÑAS no están aquí todavía: su fuente está a mitad de cambio (Places → Business
    // Profile, `#524`) y su forma cambia con ella. Llegan en la T2 de esa spec como clave hermana
    // dentro del mismo sobre, sin romper este contrato.
    Route::get('/social-proof', SocialProofFactsController::class)
        ->middleware('cache.headers:public;max_age=300;etag')
        ->name('social.proof.facts');

    // ── Estado de las reservas (Fase 4 · paso 4.0b) — PÚBLICO ──────────────────────────────
    // Si se puede reservar online ahora, y qué enseñar si no (#218). Hasta este paso la pausa solo
    // existía por API como el código de un 409: el cliente se enteraba DESPUÉS de intentar crear el
    // pedido, mientras que la web sustituye el flujo por un aviso con el teléfono del negocio.
    //
    // Va SEPARADO de `/config` porque es ESTADO y se relee: la dueña acciona el interruptor con
    // clientes navegando, y un snapshot de arranque mentiría desde ese segundo.
    Route::get('/booking/status', BookingStatusController::class)->name('booking.status');

    // ── El texto firmable del waiver (Fase 6, `specs/waiver-probatorio.md` §4.4) — PÚBLICO ────
    // Quien se da de alta aún no tiene sesión y es justo cuando lo necesita. Publica el SNAPSHOT
    // vigente con su identificador: lo que se enseña es exactamente lo que se acepta. `document`
    // es `null` fuera del modo interno o sin versión publicada — no es un error.
    Route::get('/legal/waiver', [LegalWaiverController::class, 'show'])->name('legal.waiver.show');

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
    // Los complementos RESUELTOS contra la selección del cliente (Fase 4 · paso 4.0b·5). El `show`
    // de arriba publica la CONFIGURACIÓN de cada enganche; esto publica el resultado de aplicarla:
    // grupos excluyentes, notas, unidades gratis, topes y la poda EN CADENA de las dependencias.
    //
    // `POST` porque lleva la selección, no porque tenga efectos. Devuelve además el dinero de la
    // línea —delegando en `CartPricing`, no recalculándolo— porque es la pantalla con más clics del
    // embudo: con dos endpoints cada clic costaría 2 peticiones contra un `throttle:api` de 60/min
    // COMPARTIDO con disponibilidad, catálogo y presupuesto (spec §4.4.3).
    Route::post('/catalog/products/{product}/addons', CatalogAddonsController::class)
        ->whereNumber('product')
        ->name('catalog.products.addons');

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
    Route::post('/orders/quote', QuoteController::class)
        ->middleware(EnsureOnlineSalesEnabled::class)
        ->name('orders.quote');

    // ── ¿Cabe esta línea en mi cesta? (Fase 4 · paso 4.0b·6) — PÚBLICO ───────────────────────
    // El tercer momento en que el servidor participa en el carrito sin guardarlo: aquí dice si una
    // línea puede ENTRAR. Existe porque la cesta de la SPA vive en `localStorage` y al añadir no
    // queda ninguna ida y vuelta, así que la alternativa era transcribir a JavaScript reglas de
    // servidor — incluida la que nadie encuentra leyendo código: un campo `number` se sanea a
    // dígitos, así que la edad contestada «cinco» el servidor la ve VACÍA (`DECISIONES #38(f)`).
    //
    // `POST` por lo mismo que el presupuesto: lleva la cesta entera —para descontar el cupo que uno
    // mismo ya retiene— y eso no cabe con garantías en una query string. No tiene efectos.
    //
    // Responde 200 aunque la línea no sirva: preguntar «¿puedo?» y que te digan «no, y por esto» no
    // es un error de la petición. Mismo criterio que `me/reservation-eligibility`.
    Route::post('/cart/validate-line', CartLineController::class)
        ->middleware(EnsureOnlineSalesEnabled::class)
        ->name('cart.validate-line');

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
        // El mismo par que la web: por IP contra el barrido y **por RESERVA** desde que aquí se
        // compran extras (`SEC-06`, D12). Ver el comentario de `routes/web.php`.
        ->middleware(['throttle:30,1', 'throttle:guest-form', 'no-store'])
        ->name('reservations.guest-form.update');

    // La INVITACIÓN DIGITAL del ANFITRIÓN (T4·6, `specs/celebracion-e-invitacion.md` §4.10;
    // `DECISIONES #578`). Misma puerta y mismo trait que el formulario de arriba: es su reserva, y
    // abrir aquí una vía propia habría duplicado los sitios donde equivocarse con quién puede tocar
    // los datos de menores de una fiesta.
    //
    // ⚠️ Endpoint APARTE del `PUT` del formulario a propósito: personalizar escribe **solo** la
    // invitación, y `order_items.updated_at` es el testigo con el que ese formulario detecta que el
    // parque movió la reserva. Meterlo dentro dejaría obsoleta la página abierta del anfitrión por
    // cambiar el color de una banda.
    Route::put('/reservations/{reservation}/invitation', [InvitationHostController::class, 'update'])
        ->whereNumber('reservation')
        ->middleware(['throttle:30,1', 'throttle:guest-form', 'no-store'])
        ->name('reservations.invitation.update');
    Route::delete('/reservations/{reservation}/invitation/replies/{reply}', [InvitationHostController::class, 'destroyReply'])
        ->whereNumber('reservation')
        ->whereNumber('reply')
        ->middleware(['throttle:30,1', 'throttle:guest-form', 'no-store'])
        ->name('reservations.invitation.replies.destroy');

    // ── La INVITACIÓN vista desde FUERA (T4·6, §4.6 y §4.10) ──────────────────────────────────
    //
    // La única superficie del contrato cuya credencial es **un token en la URL** —doce caracteres
    // opacos, una fila que el operador puede anular— y no una sesión ni una firma HMAC: el enlace se
    // reparte a un grupo de clase entero por un chat de padres.
    //
    // ⚠️ `no-store` EXPLÍCITO por el mismo motivo que el formulario: se entra sin sesión y lo que se
    // sirve es el nombre y la edad de un menor (`RGPD-04`).
    //
    // ⚠️⚠️ El throttle del POST es **por IP y por TOKEN**: sin el segundo, quien tuviera el enlace
    // podría ir probando nombres desde muchas IPs. Lo que protege la hoja en blanco es que contestar
    // no distingue un nombre repetido de uno nuevo, pero un tope por invitación lo pone más caro.
    Route::get('/invitations/{token}', [InvitationsController::class, 'show'])
        ->where('token', '[A-Za-z0-9]{12}')
        ->middleware(['throttle:60,1', 'no-store'])
        ->name('invitations.show');
    Route::post('/invitations/{token}', [InvitationsController::class, 'reply'])
        ->where('token', '[A-Za-z0-9]{12}')
        ->middleware(['throttle:20,1', 'throttle:invitation-reply', 'no-store'])
        ->name('invitations.reply');

    // ── Zona autenticada ──────────────────────────────────────────────────────────────────────
    // `auth:sanctum` cubre los DOS modos del §4.2 con el mismo código: cookie de sesión para la
    // SPA de primera parte y Bearer para el móvil. El guard resuelve primero los guards de sesión
    // (`sanctum.guard`) y solo después el token.
    Route::middleware(['auth:sanctum', $tokenAbility])->group(function (): void {
        Route::get('/me', [MeController::class, 'show'])->name('me.show');

        // «Mis reservas» y «Mis pedidos» (paso 1, solo lectura). El scoping es por el guard en los
        // dos: ninguno acepta un identificador de titular por la petición.
        // ── Gestiones de credenciales (tanda 2 · paso 6, `specs/area-cliente.md` §9.3) ─────────
        // ⚠️ **Sin `throttle` de ruta a propósito, y no es un olvido**: el techo de estas dos lo pone
        // `AccountCredentials` por (titular, IP) y cuenta **solo los fallos**, no las llamadas. Un
        // `throttle` de ruta contaría también los aciertos y castigaría a quien se equivoca una vez y
        // acierta a la segunda. El `throttle:api` del grupo sigue siendo el suelo de todo.
        // ── El PERFIL y el ciclo del cambio de correo (tanda 2 · paso 7) ───────────────────────
        Route::patch('/me', [MeProfileController::class, 'update'])->name('me.update');
        Route::delete('/me/pending-email', [MeProfileController::class, 'cancelEmailChange'])
            ->name('me.pending-email.cancel');
        Route::post('/me/pending-email/resend', [MeProfileController::class, 'resendPendingEmail'])
            ->name('me.pending-email.resend');

        // `#329` — el hermano AUTENTICADO de `auth/email/resend`: reenvía la verificación del correo
        // de quien ya tiene sesión, sin que tenga que decir cuál es. Existe porque **se puede entrar
        // sin haber verificado** y ahí el área de cuenta necesita ofrecer la salida: quien aceptó la
        // exención en el alta la tiene retenida hasta que verifique.
        //
        // ⚠️ **No se aflojó el público en su lugar**: su cuerpo es el `EmailRequest` del contrato,
        // compartido con `auth/password/forgot`. Y la sesión identifica mejor que un correo escrito
        // en el cuerpo. Es el mismo patrón que `/me/pending-email/resend`, justo encima.
        Route::post('/me/email/resend', [MeProfileController::class, 'resendVerification'])
            ->middleware('throttle:6,1,verification-resend')
            ->name('me.email.resend');

        Route::put('/me/password', [MeCredentialsController::class, 'updatePassword'])->name('me.password.update');
        Route::post('/me/sessions/revoke-others', [MeCredentialsController::class, 'revokeOtherSessions'])
            ->name('me.sessions.revoke-others');

        // ── Las identidades EXTERNAS del titular (`specs/auth-con-google.md` §8) ──────────────────
        // ⚠️⚠️ Desvincular es el **contrapeso** del aviso de vinculación: el vínculo se crea solo y se
        // avisa por correo, y ese aviso solo sirve si quien lo recibe puede deshacerlo. Sin esto, la
        // única salida de un vínculo no pedido era borrar la cuenta.
        // ⚠️ Exige la contraseña (`[DECIDIDO owner]`) y comparte el limitador de `PUT /me/password`:
        // un segundo contador serían cinco intentos más por endpoint, que es como se afloja `SEC-06`.
        Route::get('/me/identities', [MeCredentialsController::class, 'identities'])->name('me.identities.index');
        Route::delete('/me/identities/{provider}', [MeCredentialsController::class, 'unlinkIdentity'])
            ->whereIn('provider', ['google'])
            ->name('me.identities.destroy');

        // ── Los dos derechos RGPD (tanda 2 · paso 8, `specs/area-cliente.md` §9.3) ─────────────
        // ⚠️ `DELETE /me` **no borra la fila**: anonimiza (`RGPD-01`). La factura sigue vinculada y
        // sin PII. Exige reconfirmar la contraseña y comparte el limitador de `PUT /me/password`,
        // así que tampoco lleva `throttle` de ruta —contaría también los aciertos—.
        // ⚠️ `GET /me/export` es el cuerpo con más PII del producto; sale con `no-store` por el
        // middleware del grupo (`RGPD-04`), no por una cabecera escrita aquí.
        Route::delete('/me', [MePrivacyController::class, 'destroy'])->name('me.destroy');
        Route::get('/me/export', [MePrivacyController::class, 'export'])->name('me.export');
        // Los consentimientos otorgados (tanda 3 · paso 11). Se abre porque `/mi-cuenta` los enseña
        // y esa página se retira: sin esto, el borrado le quitaría al cliente la prueba visible del
        // art. 7.1. NO publica la IP — eso viaja en el export, que es un acto explícito.
        Route::get('/me/consents', [MePrivacyController::class, 'consents'])->name('me.consents.index');
        // ⚠️⚠️ **RETIRAR el consentimiento de marketing** (art. 7.3, `#344`), que hasta hoy no se podía
        // por ninguna superficie. **Sin `current_password` a propósito**: la ley exige que retirarlo
        // sea *tan fácil como darlo*, y pedir fricción solo para la retirada sería incumplirlo por
        // otra puerta. Lo que sí queda es la PRUEBA: el dominio sella la fila en vez de borrarla.
        Route::put('/me/marketing', [MePrivacyController::class, 'marketing'])->name('me.marketing.update');

        // ── Mi waiver (Fase 6, `specs/waiver-probatorio.md` §4.4, §4.5, §4.8) ──────────────────
        // Estado según el modo · ACEPTAR el texto vigente con el `document_id` que se sirvió (si
        // cambió entre medias, 409 y se vuelve a leer) · el PDF de una firma PROPIA, auditado. El
        // `throttle` de las dos últimas acota filas append-only y generación de PDF; `no-store` lo
        // pone el grupo (`RGPD-04`). ⚠️ Con PREFIJO (tercer parámetro): un `throttle:N,1` sin prefijo
        // comparte UN cubo por usuario con todos los demás sin prefijo —incluido el reintento del
        // pago—, y siete descargas del PDF dejaban 60 s sin poder pagar (revisión `#169` §10.5).
        Route::get('/me/waiver', [MeWaiverController::class, 'show'])->name('me.waiver.show');
        Route::post('/me/waiver', [MeWaiverController::class, 'store'])
            ->middleware('throttle:10,1,waiver-sign')
            ->name('me.waiver.store');
        Route::get('/me/waiver/{signature}/pdf', [MeWaiverController::class, 'pdf'])
            ->middleware('throttle:10,1,waiver-pdf')
            ->name('me.waiver.pdf');

        // ── Menores a cargo (Fase 6 · C, `specs/menores-a-cargo.md` §4.2, §4.4, §4.5, §4.9) ───────
        // Solo LEER, AÑADIR y QUITAR: no hay edición —una fecha de nacimiento corregida es otra
        // persona a cargo, y con una firma detrás sería reescribir lo firmado—. El tope es de SERVIDOR
        // (`dependents.max_per_account`, `PAY-12`) y la pertenencia se re-valida en el dominio: un id
        // ajeno «no existe» (404). El `throttle` acota una superficie que crea PII de terceros; con
        // PREFIJO, como los del waiver (revisión `#169` §10.5).
        // Fase 6 · subsistema A (`specs/identidad-qr-puerta.md` §9.2 A·8): el CARNÉ QR. `GET` lo emite si
        // no existe; `rotate` mata el viejo en el acto. Con `throttle` por prefijo: rotar escribe.
        Route::get('/me/card', [MeCardController::class, 'show'])->name('me.card.show');
        Route::post('/me/card/rotate', [MeCardController::class, 'rotate'])
            ->middleware('throttle:10,1,card-rotate')
            ->name('me.card.rotate');
        // §9.6 B·1: el carné como IMAGEN (los mismos bytes que el adjunto del correo). Genera un PNG
        // por petición, así que lleva su cubo propio, como el PDF del waiver.
        Route::get('/me/card/png', [MeCardController::class, 'png'])
            ->middleware('throttle:30,1,card-png')
            ->name('me.card.png');
        Route::get('/me/dependents', [MeDependentsController::class, 'index'])->name('me.dependents.index');
        Route::post('/me/dependents', [MeDependentsController::class, 'store'])
            ->middleware('throttle:30,1,dependents-write')
            ->name('me.dependents.store');
        Route::delete('/me/dependents/{dependent}', [MeDependentsController::class, 'destroy'])
            ->middleware('throttle:30,1,dependents-write')
            ->whereNumber('dependent')
            ->name('me.dependents.destroy');
        // La firma EN NOMBRE de un menor (tanda 2, `#197`): mismo cubo que la firma del titular — es
        // la misma clase de escritura append-only, y así siete firmas de menores no dejan sin poder
        // pagar (revisión `#169` §10.5).
        Route::post('/me/dependents/{dependent}/waiver', [MeDependentsController::class, 'acceptWaiver'])
            ->middleware('throttle:10,1,waiver-sign')
            ->whereNumber('dependent')
            ->name('me.dependents.waiver.store');

        Route::get('/me/reservations', [MeReservationsController::class, 'index'])->name('me.reservations.index');

        // **El historial POR RESERVA**, que es como lo lee un cliente: un pedido puede llevar tres
        // reservas de tres fechas distintas y enseñarlas juntas no le dice nada
        // (`specs/mis-reservas-por-reserva.md`). Dos URLs porque son dos pantallas del cajón —«Mis
        // reservas» y «Historial»—, un solo manejador y, sobre todo, **un solo predicado**: el
        // reparto lo hace `CustomerReservations::pageFor()` con `where`/`whereNot` sobre la misma
        // expresión, para que ninguna reserva pueda caerse entre las dos (§3.4).
        //
        // ⚠️ Va DESPUÉS de `/me/reservations` a propósito: con el orden inverso, `{scope}` no llega a
        // capturar nada porque la ruta literal ya no existiría, pero un `{scope}` declarado antes sí
        // se comería cualquier segmento futuro que colgara de aquí.
        Route::get('/me/reservations/{scope}', [MeReservationsController::class, 'page'])
            ->name('me.reservations.page');
        Route::get('/me/orders', [MeOrdersController::class, 'index'])->name('me.orders.index');

        // **El contexto de cuenta en UN viaje**: saludo, próxima reserva, contador y formularios
        // pendientes (`specs/account-context-vue.md` §4.4). Lo consume el bloque de cuenta del cajón
        // cuando tiene que repintarse tras conseguir sesión **sin recargar** —en mitad de una
        // compra—, y de las tres cosas la última no se puede componer bien desde fuera: `me/orders`
        // PAGINA, así que contar pendientes sobre una página cuenta mal.
        // Sin parámetros a propósito: la única fuente de identidad es el guard.
        Route::get('/me/account-context', MeAccountContextController::class)
            ->name('me.account-context.show');

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
        Route::post('/orders', [OrdersController::class, 'store'])
            ->middleware(EnsureOnlineSalesEnabled::class)
            ->name('orders.store');
        Route::get('/orders/{code}', [OrdersController::class, 'show'])->name('orders.show');
        // Las respuestas del pack (Fase 4 · paso 4.0b·4b), APARTE del pedido y a propósito: son
        // datos de un menor —el nombre del homenajeado, su edad y las alergias, art. 9—, y como
        // campo de `OrderItem` viajarían en cada página de `me/orders`. Pedirlas es un acto
        // explícito del cliente, no el efecto de listar el historial.
        Route::get('/orders/{code}/event-data', OrderEventDataController::class)
            ->name('orders.event-data.show');
        // Los JUSTIFICANTES de menores invitados de un pedido, vistos por su RESPONSABLE
        // (`specs/waiver-por-reserva.md` §4.10, `#336`). APARTE del pedido por las mismas dos razones
        // que `event-data`, y una tercera propia: **el enlace es una credencial portadora** y no
        // puede viajar en nada que se siembre en el HTML de cada página con sesión.
        Route::get('/orders/{code}/guest-minors', OrderGuestMinorsController::class)
            ->name('orders.guest-minors.show');
        Route::post('/orders/{code}/payment', [OrderPaymentController::class, 'store'])
            ->middleware(['throttle:6,1', EnsureOnlineSalesEnabled::class])
            ->name('orders.payment.store');
        // Sondeo del desenlace (paso 4d). Se consulta EN BUCLE mientras se espera la notificación
        // de la pasarela, así que se queda con el suelo genérico del grupo y no con el techo del
        // reintento: aquí no se abre ningún cobro, solo se lee. No transiciona nada — `PAY-01` dice
        // que el único que pasa una Order a `paid` es `RedsysReturnHandler`, con la firma delante.
        Route::get('/orders/{code}/payment-status', OrderPaymentStatusController::class)
            ->name('orders.payment.status');
    });
});
