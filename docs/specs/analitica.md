# [SPEC] La analítica — medir la conversión en cada sitio y conocer al cliente, dentro del marco legal y a favor del negocio

> Estado: 🟦 **en revisión (v2 tras la revisión adversarial del 23-09, §7.1) — dirección `[DECIDIDO owner]`
> (`#678`)**; el owner revisa §0 y §4 antes del primer commit de código · Última actualización: 2026-09-24 ·
> Decisión asociada: `DECISIONES #678`. Carril: **plataforma** (T0–T1, banda 670–699) → **SPA** (T2→T5, banda
> 730–759, `#735`). Origen: el objetivo del owner
> del 23-09 —*«medir todo, tener todos los números para tomar decisiones; lo más importante la CONVERSIÓN en
> cada sitio; lo segundo conocer al cliente»*— y el hueco que `#670` dejó nombrado («las analíticas»).

## §0 · Antes de tocar

- **Regla que ordena todo**: la VERDAD de la conversión vive en un **libro de eventos** propio con DOS
  regímenes: el **agregado y exento** (sesiones anónimas: embudo, fuentes, campañas, al 100 %; guía AEPD 2024)
  y el **identificado** (navegación atada a la cuenta, PostHog), **solo con la categoría `analytics`**. Toda
  herramienta externa es intercambiable y solo ve lo que se le envía, con consentimiento.
- **Empieza por** §1 → §4.1 → §4.2 → §4.3 → §7.1 (lo que corrigió la revisión); para el cuadro, §4.5. Las
  tandas, en §4.8.
- **Trampas** (las cinco las destapó la revisión, §7.1):
  - ⚠️⚠️ **Tres hechos de dinero NO son transiciones de `Order`**: rechazado vive en `Payment`, expirado es un
    UPDATE de query builder (`ExpireOrders`) y reembolsado son filas `PaymentRefund`. Y un observador diferido
    **no ve la transición**: se captura en `saving` (escalares) y se difiere con `DB::afterCommit`.
  - ⚠️⚠️ **La analítica NUNCA tumba un pago**: el callback traga todo con `Log` (un `throw` tras el commit
    propaga igual). `RedsysReturnHandler`, `OrderCreator` y `CheckoutOrchestrator` no se tocan.
  - ⚠️ **El sello nace en `creating`** desde `AttributionContext`: `created`+`afterCommit` es un UPDATE
    tardío, y el pedido manual heredaría la sesión del OPERADOR.
  - ⚠️ **`/events` sale del `throttle:api` y del stateful** (`withoutMiddleware`): apilado se come el embudo y
    `sendBeacon` vuelve 419. Un nombre de SERVIDOR desde el cliente se rechaza.
  - ⚠️ **`nullOnDelete` no se dispara nunca**: `anonymize()` y el export cubren las tablas nuevas.
- **Estado**: T0 ✅ · T1 ✅ · **T2→T5 en el carril del SPA** (`#735`; la T2 en cinco, §4.5 y §4.8). `[DECIDIDO
  owner]` 23-09: todo con la v2.0.0, sin la pregunta tras pagar. `[PENDIENTE: asesoría]`: los tres puntos de §7.
- **Invariantes**: `RGPD-01`, `RGPD-05`, `RGPD-07`, `SEC-01`, `PAY-14`, `PAY-21`, `SUITE-01`. Dinero: ninguno cambia.

## 1. Contexto y problema — MEDIDO (2026-09-23)

| Qué | Medida | Cómo |
|---|---|---|
| Rastreo | **Ninguno** en producto ni instancia: ni GA, GTM, píxel, Hotjar, Plausible, PostHog ni Matomo | `grep -rniE 'gtag\|dataLayer\|plausible\|posthog\|matomo\|fbq\(' app resources routes instancias/` |
| Origen del cliente | **No se guarda**: `orders` tiene 14 columnas y ninguna de canal; ningún `utm_` en app, vistas ni correos | `Schema::getColumnListing('orders')` · `grep -rn utm_` |
| Contacto | `ContactController::store()` **solo envía un correo**; no hay tabla; teléfono y WhatsApp son `<a>` sin medir | lectura + `Schema::hasTable('contact_messages')` = no |
| Hecho fiable | El **pago** lo confirma el servidor (`PAY-01`); el rechazo del banco lo escribe en `Payment`, la caducidad `ExpireOrders` por UPDATE de query builder, y el reembolso son filas `PaymentRefund` (el pedido sigue `paid`) | la revisión (§7.1 de esta spec) · `INVARIANTES.md` §1 |
| Eventos de dominio | Solo tres (`Lockout`, `PasswordReset`, `Verified`); la auditoría (`audit_logs`) es OPERATIVA, no de embudo; **ningún observador de `Order`** | `grep -rn 'event(new '` · `grep observe app/Providers` |
| Panel | Dos números: reservas y ocupación del periodo (`DashboardStatsWidget`); idiomas del panel **`es` y `zh_CN`** | lectura |
| Consentimiento | Banner PROPIO (`COOKIES.md` D1) con TRES acciones en igualdad; categorías `maps` y `social` **quemadas en seis sitios** (helper, controlador, layout, store, banner, `InstanceViews`); versión `2026-09-13`; acreditación en `cookie_consent_logs`; la política vive en `pages.body` y solo se refresca por migración quirúrgica (`#592`) | `CookieConsent`, `app.js`, `cookie-banner.blade.php` |
| CSP | Orígenes en CÓDIGO (`SecurityHeaders`, `SocialEmbed::ALLOWED_DOMAINS`), nunca desde `settings` | lectura |
| Cajón | Once pasos con **mapa explícito de transiciones** (`machine.js`), un cliente HTTP (`api.js`, `same-origin`, `X-XSRF-TOKEN`), el cajón **nace abierto** sin `open()` en enlace profundo y vuelta del banco (`controller.js` `start()`), la cesta en `localStorage` sin caducar | lectura |
| Landing (instancia) | 9 vistas en `<x-layout>`, que carga **`app.js`** (no `/cajon/paquete.js`); CTAs con Alpine `openWith`; salidas: `tel:` ×3 y mapas ×3 en portada, formulario en contacto, WhatsApp/`mailto:` por `contact-channels` | `grep -c` por vista |
| Presupuestos JS | `SidebarBundleBudgetTest`: entrada de la landing 24,5/26 KiB · cargador 6,1/8 · chunk del cajón 287,1/288 | lectura |
| Correos | 26 (22 `Notification` + 2 `Mailable`, `ShouldQueue`), **23 al cliente**; sin UTM; **cinco enlaces FIRMADOS** (post-form, tutor, verificación de correo, invitación) validados sin `ignoreQuery`; invitación y post-form usan `x-focused-layout` (sin `app.js`) | `grep temporarySignedRoute` |
| Cliente | `users`: `locale`, `last_login_at`, `marketing_opt_in` (se ofrece en «Mi cuenta → Privacidad» por `PUT /me/marketing` con prueba en `consents`; lo que no existe es el MOMENTO tras comprar); `dependents` nacen para la exención (`RGPD-01`); `User::anonymize()` **sobrescribe la fila, nunca la borra**: ningún `nullOnDelete` se dispara; `AccountPrivacy::exportFor()` enumera a mano | lectura |
| Hosting | PHP 8.5 + **MariaDB 11.4** (`json` = `LONGTEXT`, sin índices funcionales) + Redis; sin node ni Docker; scheduler y `queue:work` en el cron del PANEL (en staging no corre); `deploy.sh` cuenta las tareas del scheduler («esperadas 6»); `trustProxies(at: '*')`; copias en `~/backups/` **sin rotación** | `ENTORNOS.md` §4 y §6 · `bootstrap/app.php` |
| Limitador | `throttle:api` = 60/min por IP en TODO el grupo `api` (los de ruta se SUMAN); el grupo lleva `EnsureFrontendRequestsAreStateful` (CSRF en peticiones del propio origen) | `config/api.php` · `bootstrap/app.php` |
| Anuncios | Google Ads, Meta y TikTok **activos y sin medir** (owner); remarketing, no todavía | owner |
| Marco legal | AEPD, enero 2024: la medición de audiencia con cookie propia queda **exenta** si produce **solo estadística anónima del editor**, sin cesión ni cruce, cookie ≤ 13 meses no renovada, datos ≤ 25 meses, informada en la política. La política sembrada (`LegalContent`) dice hoy «consentimiento para toda cookie no necesaria» y «ni elaboramos perfiles» | guía AEPD · `LegalContent::pages()` |

**El problema, en una frase**: el negocio decide a ciegas. No sabe cuánta gente entra, dónde se cae, qué
anuncio trae compras ni quién vuelve — y cada día sin medir es un día que no se recupera.

## 2. Objetivo

1. **Conversión en cada sitio**: para cada paso (entrada → página → cajón paso a paso → banco → después) un
   número comparable por periodo, fuente, página, dispositivo, idioma, hora (del parque) y producto; y el
   abandono con su motivo, incluidos los fallos técnicos.
2. **Atribución por CAMPAÑA al 100 %**: cada pedido sabe de qué fuente y campaña vino (primer y último
   toque), sin depender del banner; el panel da ingresos, CPA y ROAS por plataforma y campaña, una compra
   contada UNA vez. ▶ Corregido por la revisión: la atribución **por PERSONA** (qué navegó antes de comprar)
   no es al 100 %: exige la categoría `analytics`.
3. **Conocer al cliente identificado**: sus compras, productos, frecuencia, contactos y opt-in, al 100 %
   (datos del contrato); su navegación y su primera fuente, con consentimiento; ofrecerle más valor solo con
   `marketing_opt_in`.
4. **El «por qué»**: grabaciones, mapas de calor y experimentos, bajo consentimiento y sin que la verdad
   dependa de ellos.
5. **Cero peticiones a terceros sin consentimiento**, y con consentimiento **las necesarias cargan**,
   medido en navegador.

**Fuera de alcance**: remarketing (misma categoría, cuando el owner lo pida); **Consent Mode «avanzado»**
(pings a Google antes de consentir: contradice el objetivo 5; si un día se quiere, cambia `RGPD-07`, la CSP y
la sonda); la subida de conversiones a Google Ads por API (OAuth; cuando haya acceso a la cuenta); la carga de
gasto por API (primero tecleado); mapas de calor propios; píxel de apertura en correos; **landings en OTRO
origen** (sin cookie: exigirían CORS y visitante en el cuerpo; la vía A vive en el mismo dominio, como
`cajon-empaquetable.md` §0); la app móvil (el contrato le sirve; su SDK es de F6); JumpPoints.

## 3. Opciones consideradas

- **A · Una herramienta SaaS como fuente de verdad** (GA4 o PostHog a pelo). Descartada: la verdad quedaría
  gateada por el banner, con las cifras en manos de un tercero y el ROAS contado una vez por plataforma.
- **B · Matomo en el propio hosting como todo**. Descartada como verdad: embudos, heatmaps, grabaciones y A/B
  son complementos de 149–249 €/año cada uno, y es otra aplicación en el servidor de producción. Queda como
  **driver alternativo** (§4.3).
- **C · Libro propio + herramienta intercambiable** — **ELEGIDA** (`[DECIDIDO owner]`). ▶ Corregido por la
  revisión (rgpd-1, ux-1): el libro exento es el AGREGADO; el cruce con la cuenta va bajo `analytics`.
- **D · Esperar a la v2.0.0 para desplegar**: no es diseño sino el CUÁNDO, y el owner lo eligió (§7).

## 4. Diseño elegido

### 4.1 El libro de eventos, propio (T1)

⚠️ **Revisión 23-09** (§7.1): (1) dos regímenes, sin `user_id` en el exento; (2) la fuente REAL de cada hecho
de servidor; (3) el sello en `creating` desde un contexto; (4) la ingesta fuera del limitador y del stateful;
(5) `anonymize()` y export; (6) índices, poda por `model:prune` y columnas planas del sello.

**Los dos regímenes.**
- **Agregado y exento** (LSSI 22.2, guía AEPD 2024): sesiones y eventos con `visitor_id` y **sin `user_id`**,
  para producir estadística: embudo, fuentes, páginas, pasos, campañas, ingresos por campaña. La cookie
  `visitor_id` es propia, `HttpOnly`, `SameSite=Lax`, **13 meses fijos** (no se renueva). El panel **nunca
  abre el libro por persona**. La política lo declara como exento. `[PENDIENTE: asesoría]`: confirmar que
  `order_id` en `order_paid` (clave seudónima para cifras por campaña) cabe en «estadística del editor».
- **Identificado** (categoría **`analytics`** del banner, §4.3): al identificarse o comprar, el servidor ata
  `user_id` a la sesión actual y a las del mismo `visitor_id` de los últimos **90 días**, escribe
  `users.first_attribution` (inmutable, al primer enlace) y permite la 360 de navegación (§4.6) y PostHog. Sin
  la categoría, nada de esto; retirarla **desvincula** (`user_id = null`) y dispara el olvido en el driver.
- El opuesto de la LIA: ya no hace falta interés legítimo para la cookie; queda para los **hechos del
  contrato** (`order_*`, `user_registered`, `contact_received`), que no necesitan cookie.

**Datos** (migraciones aditivas y reversibles, `(futuro)`):
- `analytics_sessions`: `id`, `visitor_id`, `user_id` (nullable, **sin FK en cascada**: la purga es de
  `anonymize()`), `started_at`, `last_seen_at`, `entry_route`, `referrer_host`, `utm_source|medium|campaign|
  content|term`, `ref`, `click_ids` json (`gclid`, `fbclid`, `ttclid`; solo con `marketing`), `device`,
  `locale`, `consent` json, `surface` (`web|app`), `is_bot`, `is_internal`. Índices `(visitor_id, last_seen_at)`
  y `(last_seen_at)`. Una sesión = un visitante con < 30 min entre `received_at`; **se crea en el primer
  evento**, no al escribir la cookie.
- `analytics_events`: `id`, `event_id` (ULID del cliente; **único** con `visitor_id`), `session_id` (sin FK),
  `visitor_id`, `user_id` (nullable), `name`, `route` (nombre de ruta + path con parámetros ENMASCARADOS,
  nunca la query), `props` json (≤ 2 KB), `occurred_at` (acotado a `received_at ± 5 min`), `received_at`
  (**la verdad temporal**; sesiona y ordena), `order_id` (nullable), `payment_id`/`refund_id` (nullable).
  Índices `(received_at)`, `(name, received_at, session_id)` y `(session_id)`. **Sin IP ni user agent**: el UA
  se lee para `is_bot`/`device` y se descarta.
- **Retención**: los dos modelos son `MassPrunable` y entran en la lista de `model:prune` de
  `routes/console.php` (eventos primero, sesiones después; 25 meses por `received_at`/`last_seen_at`); el
  recuento de tareas de `deploy.sh` sube en el mismo commit. Las copias de `~/backups/` necesitan rotación
  escrita (`ENTORNOS.md` §6; fila en `DEUDA.md`), o el plazo de la política sería falso en el servidor.

**El sello del pedido** (`orders`): columnas planas indexadas `attribution_channel` (`web|app|panel|system`),
`attribution_source`, `attribution_medium`, `attribution_campaign` + `attribution` json (`content`, `term`,
`ref`, `entry_route`, `referrer_host`, `device`, `locale`, `first_touch`, `last_touch`, `consent`, y **solo
con consentimiento** `visitor_id` y `click_ids`, que `anonymize()` y la poda vacían). Dos capas: la de
CAMPAÑA se queda con el pedido (dato del contrato); la de IDENTIFICADORES caduca a los 25 meses del
`created_at`. **Modelo de atribución**: `last_touch` = la sesión actual; `first_touch` = la primera sesión no
directa del `visitor_id` en los últimos **30 días** (resuelta en servidor); `gclid` → `google/cpc`; `fbclid` y
`ttclid` cuentan como pago **solo con `utm_medium` de pago** (plantilla de UTM obligatoria en los anuncios,
§4.3); `ref` se normaliza a `utm_source`. El panel enseña las dos y calcula CPA/ROAS sobre `first_touch` no
directo, con la nota de método. `attribution IS NULL` = «anterior a la medición», nunca «directo».
- **Cómo nace**: `Platform\Services\Analytics\AttributionContext` es un singleton **de petición** (`scoped`)
  que rellena el middleware `ResolveVisitor` (grupos `web` y `api`; en `web` acuña además la cookie) desde
  la cookie, y que resuelve la sesión y el primer toque PEREZOSAMENTE: la ruta `POST /orders` lleva el
  middleware `attribution`, que lo resuelve antes de entrar en el dominio (el controlador es `CRITICAL_RE`
  y no se toca). `CreateManualOrderPage` lo fija a `{channel: panel, source: <Select obligatorio
  phone|counter|email|other>, operator_id}` ANTES de llamar a `fulfill()` (la firma no cambia; T1d);
  consola y jobs → `{channel: system}`; Bearer → `{channel: app}`. `Booking\Observers\OrderAnalyticsObserver`
  copia el contexto en el modelo en **`creating`**, antes del INSERT: **cero consultas, cero UPDATE bajo
  el lock de aforo**, `try/catch` con `Log` (`analytics.seal_failed`) y el pedido nace igual.
- ⚠️ **Dónde vive el módulo, y por qué**: en `Platform` (`Platform\Models\Analytics*`,
  `Platform\Services\Analytics\*`), como `AuditLogger`: todos los módulos pueden mirar a Platform y Platform
  no mira a nadie, así que Booking, Payments e Identity le escriben con ESCALARES y el grafo de
  `ModuleBoundariesTest` no cambia. Los observadores viven en el módulo del modelo que observan.
- ⚠️⚠️ **Los observadores son SINGLETON, y lo destapó la T1a**: el dispatcher resuelve `Clase@método` del
  contenedor EN CADA evento, así que la transición capturada en `saving` la leía un `saved` de otra
  instancia, vacía (medido en tinker: `order_created` llegaba y `order_cancelled` no). Los tres se
  registran con `singleton()` antes de `observe()`, y resuelven el contexto y el recorder al usarse.

**Los hechos de servidor** (`Platform\Services\Analytics\Recorder`), cada uno con su FUENTE:

| Hecho | Fuente | Lleva |
|---|---|---|
| `order_created` | observador `saving`/`created` de `Order` | `order_id`, `total_cents`, productos |
| `order_paid` | transición a `paid` capturada en `saving` (`getOriginal('status')` → escalares en `OrderTransition`) y diferida con `DB::afterCommit`; en el callback, `tickets()->exists()` distingue la compra de la **incidencia** (`PAY-02`) → `order_paid` o `order_paid_incident(kind)` | `order_id`, `paid_cents` = `Payment.amount` del cobro, `total_cents` = `Order.total` |
| `order_declined` | observador de `Payment` (transición a `STATUS_FAILED`) | `order_id`, `payment_id`, código de respuesta |
| `order_expired` | `ExpireOrders`, en el mismo `if` del CAS afectado (no es `CRITICAL_RE`) | `order_id` |
| `order_payment_init_failed` | `releaseAfterFailedPaymentStart()` (el único `save()` a `expired`): es un fallo nuestro, no un abandono | `order_id` |
| `order_cancelled` | transición a `cancelled` (capturada en `saving`; **dos `save()` en una transacción** se registran cada uno) | `order_id` |
| `order_refunded` | escritor de `PaymentRefund` al pasar a su estado de éxito (`PAY-17` I4) | `order_id`, `refund_id`, `refunded_cents` |
| `user_registered`, `user_logged_in` | `SelfSignup`, `PasswordLogin`, Google (con `method`) | `user_id`; **enlazan visitante↔usuario solo con `analytics`** |
| `contact_received`, `guest_form_submitted`, `invitation_replied`, `email_sent`, `email_clicked` | sus servicios; `email_sent` desde `NotificationSent` (`RecordEmailSent`); `email_clicked` lo registra el middleware `RecordEmailClick` del grupo `web` (también en `x-focused-layout`) al ver `utm_source=email` con una clave de correo real, una vez por sesión | claves, nunca texto |

**Reglas del recorder**: se captura en el evento SÍNCRONO (escalares, nunca el modelo: patrón de
`SignPendingWaiverOnVerification`), se difiere con `DB::afterCommit`, y **el callback traga todo** con
`Log::warning('analytics.record_failed')` —un `throw` tras el commit propaga a la petición aunque el pago ya
esté confirmado—. Ingresos del panel = Σ `paid_cents` − Σ `refunded_cents`; valor vendido = Σ `total_cents`.
El canal `panel` cuenta en ingresos por canal y **no** en el embudo web.

**La ingesta**: `POST /api/v1/events` (`EventsController` → `Platform\Services\Analytics\EventIngestor`), dentro del grupo `api` pero
`->withoutMiddleware(['throttle:api', EnsureFrontendRequestsAreStateful::class])`: **sin sesión, sin CSRF, con
limitador propio** que SUSTITUYE al del grupo (única ruta así; se escribe en `SEC-01`). `RateLimiter::for('events')`:
clave `visitor_id` (cookie) con respaldo por IP, **30 lotes/min**, ≤ 50 eventos, cuerpo ≤ 64 KB, `props` ≤ 2 KB,
tope diario por visitante; `trustProxies` **MEDIDO al cerrar T1 (`SEC-13`)**: no hay proxy delante de PHP
(LiteSpeed en el mismo host) y con el `*` heredado una `X-Forwarded-For` falsa del cliente **se honraba**
(65 con XFF rotatoria → 0 × 429; 65 sin cabecera → 429 en la #61, en staging Y en producción), así que se
retiró el `*` y no se confía en nadie; un CDN futuro se acota a sus rangos. Anónima: el `user_id` **nunca viene del cliente**; lo pone el servidor
al enlazar. Validación **por evento**: acepta los válidos, descarta los inválidos con `Log::warning` y contador
diario, responde `202 {accepted, rejected: [{index, reason}]}`; `422` solo con el sobre malformado. Un nombre
de **servidor** desde el cliente → rechazado. `route` y `referrer` se normalizan en `track.js` Y en el servidor
(patrón de ruta, query por lista blanca `utm_*|ref|gclid|fbclid|ttclid`); una expresión de correo/teléfono en
cualquier valor lo vacía; el `Log` nunca vuelca el lote. `insertOrIgnore` por `event_id`. Sin cookie, el `202`
la acuña (la respuesta es `no-store`; el arranque cacheado nunca). `is_bot` por UA conocido (lista en
`Contract`, probada) y `navigator.webdriver`; `is_internal` por cookie `jw_internal` que el panel pone a
quien entra con rol de equipo, por cabecera de la sonda, o por sesión de `PANEL_ROLES`; `robots.txt` cierra
`/api/`. El cuadro excluye ambos por defecto y los enseña aparte.

**Los correos** (T1c ✅ 23-09): el UTM (`utm_source=email&utm_medium=<clave>`, clave = `Str::snake` de la clase,
`EmailUtm::keyOf()`) lo pega el MOLDE —`new BrandedMailMessage($this)` en cada `toMail()`— al botón, al
logotipo y a los cuatro enlaces del pie, solo si el enlace es de esta casa. ⚠️ **CORREGIDO en la T1c: el UTM se
pega DESPUÉS de firmar y la validación lo IGNORA**; «antes de firmar Y `ignoreQuery`» era contradictorio: el
HMAC cubre la query entera y `hasCorrectSignature()` retira las claves ignoradas antes de recalcularlo, así que
un enlace firmado con el UTM dentro y validado ignorándolo daría 403 (leído en el framework). Lo ignorado es la
lista blanca de atribución (`EmailUtm::IGNORED_QUERY` = `RouteNormalizer::QUERY_ALLOWLIST`), en
`validateSignatures(except:)` (`bootstrap/app.php`, el middleware `signed`) y en los siete
`hasValidSignatureWhileIgnoring()` de los accesos por firma; cualquier otra clave pegada sigue dando 403. Solo
los **24 correos al cliente** (25 notificaciones menos `GoogleBusinessLocationChanged`, por clave en
`EmailUtm::NOT_TO_CUSTOMERS`; los dos `Mailable` al negocio no pasan por el molde). `email_sent(key)` lo
registra `RecordEmailSent` desde `NotificationSent` (canal `mail`, también en el worker); `email_clicked(key)`
el middleware `RecordEmailClick` del grupo `web`, una vez por sesión y clave, y solo claves de correos que
existen. `EmailUtmTest` renderiza, lee las fuentes y abre enlaces firmados con UTM. La ficha de Google Business
Profile enlaza con `?ref=gbp` (lo escribe el owner en Google; `ref` → fuente `gbp`, medio `referral`).

### 4.2 `track()` y el CONTRATO de eventos (T1)

⚠️ **Revisión 23-09**: la landing carga `app.js`, no `/cajon/paquete.js`; `track.js` es un trozo DIFERIDO con
techo propio; el contrato cierra NOMBRES (no `props`) en el yaml; `data-jw-track`; abandono derivado, no evento.

- **Un solo emisor**: `resources/js/cajon/track.js` `(futuro)`, importado con `import()` tras `load`/idle desde
  `cajon/index.js` (patrón `standalone.js`), así llega a las DOS entradas (`app.js` y `paquete.js`) sin pesar
  en sus presupuestos; **techo propio en `SidebarBundleBudgetTest`** (≤ 3 KiB min+gzip), y el JS lleva solo
  los nombres que EMITE, no el contrato. Cola en `sessionStorage`, envío cada 5 s o 10 eventos, reintento con
  espera exponencial ante 429/5xx/red (máximo 3) y `batch_dropped(count, status)` en el siguiente lote;
  `fetch(keepalive)` o `sendBeacon` con `Blob` JSON al salir (la ruta es stateless: sin CSRF). Con
  `navigator.webdriver` emite igual, marcado en `meta.webdriver` (el servidor lo guarda `is_bot`): así la sonda
  verifica el camino entero. Captura sola: `page_viewed` (con entrada, referer, `utm_*`, `ref` y click ids en la
  primera vista), `section_viewed` (`IntersectionObserver`, umbral ≥ 0,5 y 500 ms), `request_failed` (desde
  `result(false, …)` de `api.js`: ruta normalizada, `status`, `offline`), `client_error` (hash, ≤ 5 por
  sesión), `consent_shown`/`consent_updated`. Clics que no abren el cajón por **`data-jw-track="call_clicked"`**
  (el prefijo del paquete, documentado en `declarative.js`; en un `<form>`, al enfocarlo, una vez; los enlaces
  `tel:`, de WhatsApp y de mapas se reconocen SIN atributo); los que lo abren ya producen `drawer_opened`.
  Lo que pase antes de que el trozo llegue —el cajón que nace abierto, su primer paso— lo guarda un BUZÓN en
  `JumpWeb.track.pending` (`index.js`) y el tracker lo vacía al instalarse; `JumpWeb.track(name, props)` es la
  puerta para los eventos que emiten los stores del motor (`product_chosen`, `date_chosen`…, carril del SPA).
- **El cajón**: `drawer_opened` lo emite el motor **también al nacer abierto** (`start()`, con `reason:
  user|deeplink|return|restored`); `step_entered(from, to)` desde `machine.js`; `drawer_closed(step, outcome,
  reloading)`; nada en `REDIRECTING` al descargar. **El abandono no es un evento**: lo deriva el servidor
  (sesión con `step_entered` cuyo último paso no es desenlace y sin `order_paid` del visitante en 24 h).
- **La app móvil**: mismo endpoint con Bearer; `X-Visitor` **solo** con Bearer (UUID v4 validado); en el mismo
  origen la cookie gana y la cabecera se ignora.
- **El contrato**: `Platform\Services\Analytics\Contract::EVENTS` (PHP, la verdad) declara por evento `source:
  client|server` y sus `props` permitidas. El yaml cierra `name` con un `enum` y deja `props` como objeto
  abierto (como `SidebarBoot.messages`); `AnalyticsContractTest` compara PHP↔`enum` (molde:
  `test_the_event_field_types_are_the_same_in_the_domain_and_in_the_contract`) y PHP↔JS leyendo `track.js`
  (molde: `SidebarMountTest` con `file_get_contents`) para los nombres emitidos. **Ninguna `prop` es dato
  personal**: lista negra de CLAVES y de VALORES (regex de correo y teléfono) en cliente y servidor.

| Superficie | Eventos |
|---|---|
| Entrada | `page_viewed` (route, entry, referrer_host, utm, ref, click ids, device, locale) |
| Landing | `section_viewed` · `call_clicked` · `whatsapp_clicked` · `map_clicked` · `contact_form_started` |
| Cajón | `drawer_opened` (route, product, reason) · `step_entered` (from, to) · `product_chosen` · `date_chosen` · `availability_missing` (product, month) · `time_chosen` · `line_added` / `line_removed` · `identify_started` (method) · `identified` · `email_verification_pending` · `pay_started` (amount_cents) · `drawer_closed` (step, outcome, reloading) · `request_failed` · `client_error` |
| Consentimiento | `consent_shown` · `consent_updated` (categorías) |
| Servidor | `order_created` · `order_paid` · `order_paid_incident` · `order_declined` · `order_expired` · `order_payment_init_failed` · `order_cancelled` · `order_refunded` · `user_registered` · `user_logged_in` · `contact_received` · `guest_form_opened` · `guest_form_submitted` · `invitation_replied` · `email_sent` · `email_clicked` · `visit_checked_in` (cuando la puerta vuelva) |
| Sistema | `batch_dropped` · `experiment_exposed` (key, variant) |

**Definición de conversión** (lo que el panel cuenta, sin bots ni internos): *visita* = sesión; *interés* =
`drawer_opened` o contacto; *intención* = `date_chosen`; *cesta* = `line_added`; *identificado*; *pago
iniciado*; **compra** = `order_paid` con canal `web|app`; *contacto* = `contact_received` + `call_clicked` +
`whatsapp_clicked`. El paso «banco» se cierra con `order_declined` y `order_expired`.

### 4.3 Consentimiento y drivers (T3)

⚠️ **Revisión 23-09**: la categoría `analytics` gatea también el enlace con la cuenta; Consent Mode BÁSICO;
orígenes de la CSP en código; secretos fuera de `settings`; la política en BD se repara por sección; el
banner conserva sus tres acciones; el job relee el consentimiento vivo; `marketing_opt_in` ya existe.

- **Dos categorías nuevas**: `analytics` («análisis de uso identificado: atar tu navegación a tu cuenta y la
  herramienta de grabaciones») y `marketing` (píxeles y comunicación de la compra a las plataformas). Lo
  EXENTO se declara aparte y no se pide. Las categorías dejan de estar quemadas: `CookieConsent::state()`,
  `CookieConsentController`, `layout.blade.php`, el store de `app.js`, `cookie-banner.blade.php` e
  `InstanceViews` recorren `OPTIONAL`, y un test lo prueba de punta a punta (POST → cookie → `state()` →
  `data-*`). `POLICY_VERSION` sube: finalidad nueva, consentimiento nuevo para todos (la misma noche que la
  v2.0.0: decisión consciente, §4.8).
- **El banner**: las **tres acciones actuales en igualdad** (Rechazar / Aceptar / Configurar); el texto
  **informa** (qué se mide y para qué), no persuade; sin premarcar; segunda capa por categoría; anuncio
  accesible al reaparecer (`aria-live` o foco al título); **suprimido mientras `purchase.isOpen`** y en los
  pasos de desenlace, probado en JS (`controller.test.js`) y en la sonda; la barra móvil de compra **no se
  oculta** por el banner (se apila). `consent_shown` permite segmentar el escalón de la v2.0.0.
- **La política**: `lang/{es,en,fr}/cookies.php` y `CookiePolicyContent` ganan las dos categorías, el driver
  configurado y el párrafo de lo exento; en una instalación existente llegan por **migración quirúrgica por
  sección** (molde `#592`), con `CookiePolicyContentTest`; `/cookies` nombra al driver cuando ≠ `none`. La
  privacidad **no** se publica por `LegalDocumentPublisher` (`PUBLISHABLE` la excluye a propósito): se
  **informa** a las cuentas existentes (aviso en el cajón y correo) antes de activar el enlace, y `LegalContent`
  deja de decir «ni elaboramos perfiles». Oposición para cuentas: interruptor en «Mi cuenta → Privacidad» y
  `PUT /me/analytics` (`consents` tipo nuevo), que desvincula y dispara `ForgetPersonInDriver` `(futuro)`.
- **Driver por instalación**: `analytics.driver` ∈ `posthog|matomo|none`, `analytics.posthog_project` (token
  PÚBLICO de proyecto; el nombre no casa con la familia de secretos de `PublicFacts`), `analytics.matomo_host`
  (validado en `Settings.php` como `https://` + host, reconstruido con `parse_url`). **Los orígenes de la CSP
  viven en código**, `Domain\Analytics\Drivers::csp()` `(futuro)` por driver y directiva (PostHog:
  `https://*.posthog.com` en `script`, `connect` e `img`, como pide su documentación); `SecurityHeaders` los
  añade solo con el driver activo (el gate real es no inyectar el script, como `COOKIES.md` D8). El cargador
  lee `document.body.dataset.cookie*`; sin ese dato (página ajena, F4) trata todo como no consentido. PostHog:
  `person_profiles: identified_only`, entradas enmascaradas, sin IP, sin vista automática, los mismos eventos
  de `track()`, `identify` con id opaco (`hash(user_id, APP_KEY)`) **solo en el régimen identificado**, y
  `ForgetPersonInDriver` al oponerse o anonimizar. El driver se inyecta `async` tras `load`. Staging arranca
  con `driver=none` y esos ajustes **no se copian** entre entornos.
- **Anuncios (T3b)**: `marketing` inyecta gtag (**Consent Mode v2 BÁSICO**: gtag no existe en el DOM sin
  `marketing`; `analytics_storage` siempre `denied`), el píxel de Meta y el de TikTok, con ids públicos en
  `settings` (`marketing.meta.pixel_id`, `marketing.tiktok.pixel_id`, `marketing.google_ads.conversion_id`)
  y sus orígenes por directiva en `Drivers::csp()`. Los **tokens** de Meta CAPI y TikTok Events API van en
  `config/services.php` desde `.env` (`PAY-06`), nunca en `settings`. En `order_paid` (transición del modelo,
  nunca un evento ingerido) un job `SendConversionToPlatforms` `(futuro)` (`ShouldQueue`, `PAY-14`) **relee el
  consentimiento vivo** (última fila de `cookie_consent_logs` del visitante, que gana columna `visitor_id`) y
  envía: `event_id` = código del pedido (dedup con el píxel), `event_time`, `value`/`currency`, `fbc`/`fbp`,
  `ttclid`, y `em`/`ph` **hasheados SHA-256** normalizados. `[PENDIENTE: asesoría]`: nombrar a Meta Platforms
  Ireland y TikTok Technology Ltd como destinatarios con su base de transferencia en la política y en la fila
  de `COOKIES.md` §1, y que el texto de `marketing` diga que la compra se comunica. Plantilla de UTM
  obligatoria en los tres anunciantes (`utm_medium` de pago), documentada para el operador.
- **Comunicaciones**: `marketing_opt_in` se ofrece ya en «Mi cuenta → Privacidad» (`AccountPrivacy::
  setMarketing()`, **único escritor**, con fila en `consents`). Lo nuevo es el MOMENTO: en `ConfirmedStep.vue`,
  solo con `hasSession && marketing_opt_in === false` y sin retirada previa (`consents.revoked_at`), una casilla
  desmarcada que llama al mismo `PUT /me/marketing`; la app, el mismo endpoint.

### 4.4 Experimentos (T5)

⚠️ **Revisión 23-09**: la cookie es `HttpOnly`, así que la asignación es del SERVIDOR, no del cliente.

Tabla `experiments` `(futuro)` (clave, variantes, pesos, activo). La asignación la calcula el servidor con
`hash(visitor_id, clave)` —o `hash(user_id, clave)` en el régimen identificado— y viaja en **`/sidebar/session`**
(`no-store`, por visitante; el arranque cacheado no cambia) y en los datos de vista de las páginas SSR;
`experiment_exposed` es anónimo. Los `user_id` expuestos a dos variantes se cuentan y se enseñan como
contaminados; el panel da conversión por variante con su intervalo. Los experimentos del driver solo ven a
quien consintió.

### 4.5 El cuadro de mando en el panel (T2) — ampliado el 24-09 (`#735`, carril del SPA)

⚠️ **Revisión 23-09**: permiso propio, zona horaria del parque, saneado de lo que teclea el visitante,
agregados diarios desde T2, «anterior a la medición». ▶ **24-09, owner**: el cuadro cuenta además **el
dinero al detalle**, **los registros** y **la puerta**; la T2 se parte en cinco (§4.8) en ese orden.

**La página.** `App\Filament\Pages\AnalyticsPage` `(futuro)` (`/admin/analitica`), **quinto sitio del menú
plano** del admin («Hoy · Calendario · Pedidos · Clientes · Analítica»; `AdminNavigationTest` lo fija) y fuera
del menú de quien no tenga el permiso. Permiso **`reports.view`** —sembrado en F7.11 («Ver informes y
exportaciones»), grupo `gestion`, admin por defecto y **sin consumidor hasta hoy**: se le da uno en vez de
crear un sinónimo—; `canAccess()` en la página y `canView()` en cada widget; `puerta` y `staff` reciben 403
(`AdminNavigationTest`, dos casos). El CSV con **`reports.export`** (nuevo), auditado (`AuditLogger`, sin PII,
con recuento). Rótulos en `lang/es/admin.php` y `lang/zh_CN/admin.php` (`analytics.*`). **Filtro de periodo
propio** (no el de «Hoy»): hoy · ayer · esta semana · la pasada · este mes · el pasado · 30 días · 90 días;
cada cifra lleva el **Δ contra el periodo anterior** de la misma longitud; granularidad de tablas y gráficos:
día (≤ 31 días), semana (≤ 90), mes (más largo; solo desde T2e).

**El tiempo.** Todo corte por día u hora es en `DisplayTime::timezone()` (test que cruza la medianoche, molde
`test_reschedule_offer_anchors_today_in_park_timezone_not_utc`). ⚠️ **Cómo**: el SQL agrupa por **HORA UTC**
(`Platform\Services\Analytics\Reports\SqlTime::hourBucket()` `(futuro)`: `DATE_FORMAT` en MySQL/MariaDB,
`strftime` en SQLite) y PHP asigna cada cubo al día, la semana o el mes del parque. Ni `CONVERT_TZ` —exige las
tablas de zona cargadas en MariaDB, y en el hosting no se controlan— ni un desplazamiento fijo, que miente en
el cambio de hora. Lo que se corta por **fecha de visita** (`slots.date`, hora de pared) no se convierte.

**T2a · Dinero** (`Reports\MoneyReport` `(futuro)`; en céntimos, de las **mismas filas que el libro**,
`PAY-16`/`PAY-17`, nunca del catálogo; el cuadro **no compone ningún `OrderBook`**: son agregados SQL con
presupuesto de consultas):
- **Cobrado online** = Σ `payments.amount` (`status = paid`) por `paid_at` (I2, el hecho de caja) · **Devuelto**
  = Σ `payment_refunds.amount_cents` (`succeeded`) por `processed_at` (I4) · **Ingresos** = cobrado − devuelto
  (§4.1) · **Vendido** = Σ `orders.total` de los pedidos cobrados (`paid`/`refunded`) por `paid_at` (lo
  facturado al nacer, I1); las **gestiones posteriores** aparte, Σ `edit`+`mixed` por su `created_at`. El valor
  VIVO de un pedido lo dice su libro, no el cuadro.
- **La señal**: pedidos con señal y Σ `deposit_split` de líneas vivas de pedidos cobrados, partida en
  **pendiente de cobrar en el parque** (visita futura) y **liquidado en el parque** (visita pasada: la
  inferencia D9 del libro, rotulada «inferido»), por `slots.date`.
- **Nº de pedidos cobrados**, **valor medio del pedido** (vendido / pedidos) y **cobro medio online**.
- **Por canal** (`attribution_channel`: web · app · panel · «anterior a la medición») · **por método de cobro**
  (`payments.provider`: pasarela o mostrador, el vocabulario de `Settlement`) · **por producto**
  (`ticket_types`: unidades y valor, Σ `chargedSubtotalCents` en SQL de líneas vivas; los diez primeros).
- **Clientes que compran**: compradores distintos, **nuevos** (primer pedido cobrado en el periodo) frente a
  **recurrentes**, **valor medio por cliente en el periodo** (vendido / compradores) y **valor de vida medio**
  (Σ vendido histórico / clientes con compra). Solo agregados: **ningún nombre** (la persona es la 360, T4).
- **Perdido**: pedidos expirados, cancelados y rechazados, con su valor, por `created_at` (histórico) y, desde
  la medición, por sus hechos (`order_expired`, `order_cancelled`, `order_declined`).
- **Incidencias de cobro** (`PAY-05`, `audit_logs`) aparte.
- Serie por día/semana/mes (tabla y gráfico de Filament, Chart.js) de cobrado, devuelto, vendido y pedidos.
- Índices aditivos en la migración de la T2a: `orders (status, paid_at)`, `payments (status, paid_at)`,
  `payment_refunds (status, processed_at)`, `users (created_at)`; `EXPLAIN` en staging, en el carril.
- ✅ **Lo que enseñó la T2a (24-09)**: el informe vive en `App\Filament\Analytics\MoneyReport` porque cruza
  cuatro módulos y solo la capa de entrega puede componerlos (`ModuleBoundariesTest`); `reports.view` es el
  permiso (huérfano desde F7.11); con DOS cuadros en el panel, «Hoy» tiene que DECLARAR sus widgets
  (`Filament::getWidgets()` devuelve todos los descubiertos); el desglose lleva la **tabla por día/semana**,
  que es a la vez el «total por mes y semana» pedido y la vista de tabla que la paleta del gráfico exige (el
  verde-agua queda a 2,74:1; el verde y el violeta se midieron y descartaron); y el `EXPLAIN` local con nueve
  filas no es veredicto (el optimizador recorre): se repite en staging con volumen. Para el ojo, el fixture local
  «probe-ojo-analitica» de la carpeta de almacenamiento (fuera de git; `OJO=montar|estado|desmontar`, por
  `tinker --execute="require base_path('storage/app/…')"`) siembra dos meses deterministas.

**T2b · Registros y puerta** (`Reports\CustomersReport` `(futuro)`):
- **Registros** por día/semana/mes: cuentas de cliente (`User::customers()`, sin `PANEL_ROLES`) por
  `created_at`; **verificadas** (`email_verified_at`); **con compra** alguna vez; y el **método**
  (`user_registered.props.method`, desde la medición; antes, «sin dato»).
- **La puerta, desde su RASTRO**: `audit_logs` guarda cada búsqueda desde agosto (`registrations.validated` el
  tecleo, `puerta.card_scanned` el escaneo; `target_id` dice si se encontró; `puerta.profile_viewed` la ficha)
  y `customer_visits` cada **visita acreditada** (una por cliente y día). El cuadro da por día/semana/mes:
  **búsquedas** (tecleadas y escaneadas), **encontradas** y **no encontradas**, **clientes distintos**
  (`COUNT(DISTINCT target_id)`), **fichas abiertas** y **visitas acreditadas**; y la **distribución por hora
  del parque** (los picos de afluencia). Solo agregados: el `payload` no se lee y ningún nombre sale. ▶ Además
  `GateVisits::register()` emite `visit_checked_in` (ya en el contrato) para que el libro también lo tenga.
  ❌ Descartado medir la puerta solo con eventos nuevos: nacerían vacíos, y el rastro ya existe con su
  índice `(action, created_at)`.
- ✅ **Lo que enseñó la T2b (24-09)**: el método del alta se lee del JSON de `props` con `SqlJson::string()`
  —`JSON_UNQUOTE(JSON_EXTRACT())` en MySQL/MariaDB y `json_extract()` en SQLite— porque **el operador `->>` no
  existe en MariaDB**, que es el hosting; «cuentas de cliente» es la regla de `User::customers()` escrita en
  SQL (`NOT EXISTS` sobre `role_user`); los CLIENTES DISTINTOS buscados salen de una consulta agrupada por
  (hora, `target_id`) que PHP pliega por día y en total, así que una persona buscada dos veces el mismo día
  cuenta una; las visitas se cortan por `visited_on` (fecha civil del parque) sin convertir; y las horas del
  parque salen de los mismos cubos por hora UTC que la serie, convertidos: una búsqueda a las 22:30 UTC es la
  hora 0 del día siguiente en Madrid. Las cuentas dadas de alta desde el panel o antes de la medición salen
  como «sin dato», nunca como «contraseña».

**T2c · Embudo y fuentes** (lo de la spec original, desde `analytics_sessions`/`analytics_events`): **embudo**
por periodo con su conversión paso a paso (§4.2, sin bots ni internos); **fuentes** y campañas (visitas,
compras, ingresos con `order_paid.paid_cents`, CPA/ROAS con `ad_spend` desde T2e), con `first_touch` y
`last_touch`; **páginas** de entrada y salidas; dispositivo, idioma, hora y producto; **abandono por paso y
motivo** (derivado, con los fallos técnicos); **contacto**; **cobros con incidencia** aparte; **eventos
rechazados y descartados** (7 días); bots e internos aparte. Los pedidos con `attribution IS NULL` se rotulan
«anterior a la medición».
- ✅ **Lo que enseñó la T2c (24-09)**: **la compra no es un paso del embudo por sesión** —sin la categoría
  `analytics` el `order_paid` no ata sesión— así que el embudo va de visita a pago iniciado por SESIÓN y las
  compras son los pedidos cobrados por `web`/`app` (los mismos del dinero, con sus cobros), y la conversión es
  compras entre visitas; el abandono es el paso más alto de cada sesión antes del pago iniciado, y **las
  sesiones sin ningún hecho del embudo no tienen fila**: son las que «solo miraron» y se cuentan por resta. Los
  ingresos por campaña salen de los `payments` de esos pedidos, no de `order_paid.paid_cents` (lo mismo, y con
  historia). Las fuentes de las VISITAS aplican la regla del toque a las sesiones agrupadas en SQL con
  `AttributionContext::touchOf()` (un solo dueño); las de los PEDIDOS, el sello: columnas planas = primer
  toque, `attribution->last_touch` = último; un pedido `web` sin sesión sale «sin dato», nunca «directo». Los
  rechazados de la ingesta no tenían dónde vivir (la T1 los dejaba en el log): `RejectedEvents` los cuenta en
  caché por día del parque y motivo, ocho días de vida, siete se enseñan; los tirados por el navegador son los
  `batch_dropped` del periodo. `CPA`/`ROAS` esperan a `ad_spend` (T2e); el «motivo» del abandono más allá del
  fallo técnico (`drawer_closed.outcome`) queda para cuando haya datos reales que lo pidan.

**T2d · CSV** de cada tabla con `reports.export`: prefija con `'` toda celda que empiece por `= + - @ \t \r`;
saneado de `utm_*`, `referrer_host` y `route` (≤ 255 y alfabeto acotado en la ingesta); ninguna columna
analítica usa `->html()`.

**T2e · `analytics_daily` y `ad_spend`** `(futuro)`: roll-up diario por comando programado (`analytics:rollup`,
+1 tarea en el recuento de `deploy.sh`) para los periodos de más de 90 días y el año (el único sitio para
«medir antes» sería producción); `ad_spend` tecleado (plataforma, campaña, mes, céntimos). Ventana **en
directo ≤ 90 días** con caché de 5 min en Redis por informe y periodo.

### 4.6 Conocer al cliente (T4)

⚠️ **Revisión 23-09**: la navegación por persona solo con `analytics`; los menores no se segmentan por
`born_on`; la 360 tiene permiso; la pregunta tras pagar se descartó.

En la ficha de usuario del panel, la **pestaña 360** (permiso **`customers.insights`**): pedidos, ingresos,
última compra, frecuencia, productos, contactos recibidos, `marketing_opt_in` — todo del contrato, al 100 %;
y, **solo en el régimen identificado**, primera fuente y campaña (`users.first_attribution`) y sesiones antes
de comprar. Los menores a cargo se muestran con la sección existente (`admin.users.section_dependents`) y su
permiso, sin exponer edades en otro sitio. **Segmentos** (compró una vez y no volvió, fiesta hace ~11 meses
—calculada desde los PEDIDOS, nunca desde `dependents.born_on`—, invitado que no compró, contacto sin pedido),
exportables **solo con opt-in**, con `analytics.export` y rastro. Cualquier consulta a `dependents` pasa por
`active()`. La pregunta «¿cómo nos has conocido?» **se descartó** (`[DECIDIDO owner]`): lo offline queda con la
fuente del operador en el pedido manual (§4.1).

### 4.7 Derechos del interesado

⚠️ **Revisión 23-09** (rgpd-2, seguridad-3, faltas-2): esta sección no existía.

**T1e ✅ (23-09)**: `User::anonymize()` pone `user_id = null` en `analytics_sessions` y `analytics_events` y
vacía `visitor_id`, `session_id` y `click_ids` del sello de sus pedidos (conserva la capa de campaña: es del
pedido, no de la persona), con `saveQuietly()` para no volver a sellar; la celda `RGPD-01` se amplía y
`MePrivacyTest` lo cubre con un control (la sesión de otro titular no se toca). `AccountPrivacy::exportFor()`
gana el bloque `analytics` (`visits` —las sesiones atadas, en el vocabulario de §4.2—: cuántas y entre qué
fechas; hechos; la primera fuente) y cada
pedido exportado lleva `attribution` (canal, fuente, medio, campaña; `null` = anterior a la medición) — lo
compone Booking, que es su dueño—; esquemas `ExportedAnalytics`, `ExportedFirstSource` y `ExportedAttribution`,
contrato **1.19.0**. Resumen y no volcado: el detalle es una petición de acceso (art. 15), no el fichero del
art. 20. Quedan para sus tandas: `ForgetPersonInDriver` (T3, con el driver) y `users.first_attribution` si T4
la crea (el censo `AnonymizeCoversEveryUserColumnTest` obligará a declararla). La oposición es el interruptor
de §4.3.

### 4.8 El orden de las tandas

| | Tanda | Entrega | Verificación (§6) |
|---|---|---|---|
| T0 | ✅ spec v2, el contrato de eventos, `#678` y la revisión (§7.1) | | docs-check |
| T1 | el libro, en cinco sub-tandas: **T1a ✅ (23-09)** tablas y poda, cookie, `ResolveVisitor` y `AttributionContext`, la ingesta stateless con su limitador, los hechos de servidor por fuente, el sello en `creating`, `robots.txt`, contrato **1.18.0** (`POST /events`) · **T1b ✅ (23-09)** `track.js` diferido (2,3 KiB gzip, techo 3) y el cajón · **T1c ✅ (23-09)** UTM en los 24 correos al cliente (pegado tras firmar, ignorado al validar), `email_sent` y `email_clicked` · **T1d ✅ (23-09)** la fuente del pedido manual (cuatro tarjetas, obligatoria, sin defecto; `forPanel()` valida) · **T1e ✅ (23-09)** `anonymize()` desata el libro y el export lleva `analytics` y `attribution` (contrato **1.19.0**) · y el **CIERRE ✅ (23-09)**: `scripts/mutar-analitica.sh` **19/19** (1 control, 1 declarado), `redsys:verify-concurrency --workers=16` ✓, la sonda, y `trustProxies` medido y retirado (`SEC-13`) | contrato **1.19.0** (`experiments` en `/sidebar/session` puede esperar a T5) | tests + arnés + `redsys:verify-concurrency` + sonda |
| T1·bis | migración de historia: «anterior a la medición», arranque de cuadros en la fecha del despliegue, `model:prune` y recuento de `deploy.sh` 6→7, ensayo en staging con `schedule:run` a mano, `POLICY_VERSION` la misma noche | | ensayo |
| T2 | el cuadro de mando, en cinco — **la hace el carril del SPA** (traspaso `#679`, tomado en `#735`, 24-09): **T2a ✅ (24-09)** el dinero: `AnalyticsPage` (5.º sitio del menú, `reports.view`; `reports.export` sembrado), `ReportPeriod` · `Window` · `SqlTime` (hora UTC → día del parque), `MoneyReport` en la capa de entrega (17 consultas, caché 5 min), cuatro widgets (tarjetas, gráfico, clientes, desglose con la tabla por día/semana), cuatro índices; 35 casos en `ReportPeriodTest`, `MoneyReportTest` y `AnalyticsPageTest`; `scripts/sonda-analitica-panel.mjs` 9/9 en escritorio y móvil; **queda el OJO del owner** y el `EXPLAIN` con volumen en staging · **T2b ✅ (24-09)** registros y puerta: `CustomersReport` (10 consultas), `SqlJson` (el método del alta desde `props`, sin `->>` porque MariaDB no lo tiene), `visit_checked_in` desde `GateVisits::register()`, cinco widgets más (registros, la puerta, la serie, las horas del parque, el desglose); `CustomersReportTest` (7 casos) + el hecho en `GateVisitsTest`; sonda ampliada a nueve widgets; **queda el ojo del owner** · **T2c ✅ (24-09)** embudo y fuentes: `FunnelReport` (20 consultas), el embudo por sesión y la compra por pedido, el abandono por paso con los fallos técnicos, fuentes por primer y último toque (`AttributionContext::touchOf()`, un solo dueño de la regla), entradas y salidas, dispositivo, idioma, horas, productos, contacto, y los rechazados de la semana (`RejectedEvents`, contadores en caché desde la ingesta); seis widgets más (quince en total); `FunnelReportTest` (8 casos); sonda 14/14; **queda el ojo del owner** · **T2d** CSV · **T2e** `analytics_daily`, `ad_spend` | | tests + presupuesto de consultas + `EXPLAIN` + el ojo del owner en vivo |
| T3a | categorías sin quemar, banner, política por sección, aviso a cuentas, `PUT /me/analytics`, driver y PostHog con `Drivers::csp()` | `POLICY_VERSION` | tests + sonda (cero terceros sin consentir; con consentimiento, sin `securitypolicyviolation`) |
| T3b | píxeles, Consent Mode básico, job de conversiones con relectura del consentimiento, tokens en `.env` | | tests con `Http::fake` |
| T4 | la 360, los segmentos, el opt-in tras comprar | | tests |
| T5 | experimentos | | tests + una prueba real |

## 5. Impacto en invariantes

- `PAY-01`, `PAY-02`, `PAY-05`, `PAY-16`, `PAY-17`: no cambian; se capturan escalares en el evento síncrono y
  el callback diferido traga todo; la incidencia de `PAY-02` no se cuenta como compra. `redsys:verify-concurrency`
  tras T1 aunque ningún fichero del `CRITICAL_RE` cambie.
- `PAY-06`: los tokens de las plataformas, en `config/services.php` desde `.env`. `PAY-14`: jobs en cola.
- `RGPD-01`: la purga y el export cubren las tablas nuevas y el sello. `RGPD-05`: grabaciones enmascaradas.
- `SEC-01`: `/events` es la única ruta del grupo `api` fuera del limitador y del stateful, escrito ahí.
- `PERF-02`: nada por visitante en el arranque cacheado (experimentos en `/sidebar/session`). `AFORO-09`:
  agregados en la zona del parque. `SUITE-01`: PostHog, Meta y TikTok con `Http::fake`.
- **Escritas al cerrar T1 (23-09)**: **`RGPD-07`** (*el libro exento no lleva persona y ninguna `prop` es dato
  personal*; la cláusula del tercero marcada **[T3]** hasta que exista el driver) y **`PAY-21`** (*el fallo de la
  analítica nunca alcanza al pago*). Las dos con su mutante en `scripts/mutar-analitica.sh`.

## 6. Plan de verificación empírica

- `AnalyticsEventsTest` (T1a ✅): lote mixto → `202` con rechazados; nombre de servidor → rechazado; PII en
  claves y en valores (correo en `route`) → vaciado; tokens de invitación/reset/firma no se guardan; POST sin
  `X-XSRF-TOKEN` con `Referer` propio → `202`; **60 lotes seguidos y `GET /availability` desde la misma IP sigue
  en 200**; `X-Forwarded-For` falsa no estrena cubo; el mismo lote dos veces → una fila; cookie de 13 meses sin
  renovación y acuñada en el `202`; UA de Googlebot → `is_bot`; sesión de `staff` → `is_internal`; `X-Visitor`
  sin Bearer → ignorada.
- `AnalyticsContractTest` (T1a ✅, T1b ✅): PHP↔`enum` y PHP↔JS (`track.js` y los `data-jw-track` de las vistas), con mutante que añade un evento solo en una.
- `AttributionSealTest` (T1a ✅): el pedido se escribe con UN INSERT que ya lleva el sello (`DB::listen`) y
  ningún UPDATE posterior; pedido manual desde un navegador con `visitor_id` y `utm_source=google` → sello
  `panel/phone`; sin contexto → `panel/unknown`; contexto que lanza → el pedido nace igual, sin sello, con
  `analytics.seal_failed`; `first_touch` no directo a 30 días; usuario anonimizado → sin `visitor_id` ni
  `click_ids`; visitante anónimo con UTM que se registra dos sesiones después (con `analytics`) hereda su
  primera fuente; sin `analytics`, nada se ata.
- `ServerEventsTest` (T1a ✅): `orders:expire` real → `order_expired`; `Payment` fallido → `order_declined`;
  devolución parcial real → `order_refunded` con su importe; reembolso+cancelación en una transacción → un
  `order_cancelled` y un `order_refunded`; `AuthorizedAfterExpiration` → `order_paid_incident`, no `order_paid`;
  **el recorder lanza → el pago queda pagado Y la petición termina sin excepción** (`RedsysReturnHandlerTest`
  en verde con el recorder roto); ingresos por campaña con señal = `paid_cents`, no `total`.
- `ConsentCategoriesTest` `(futuro)`: `OPTIONAL` de punta a punta; sin `analytics` no hay script del driver
  ni orígenes; con él, las directivas EXACTAS; host con `;` no entra; versión vieja re-pide; retirar
  `analytics` desvincula; el job de conversiones aborta si el consentimiento se retiró.
- `TrustedProxiesTest` (cierre de T1 ✅, `SEC-13`): las `X-Forwarded-For/Proto/Host/Port` del cliente no cambian
  IP, esquema ni host, y el `*` no vuelve al fuente. `scripts/mutar-analitica.sh`: **19/19** muerden (PII en
  claves, ruta sin enmascarar, `server_only`, el sello, el recorder que re-lanza, `forPanel('fax')`, cobrar sin
  fuente, el botón, la UTM sin pegar, la firma sin ignorar, el `signed`, los dos de `email_clicked`, los tres de
  la supresión —uno SIN ACOTAR con control—, el export sin `analytics`, el temporizador y el ULID del tracker),
  1 control, 1 declarado (el `Log` de rechazos). `redsys:verify-concurrency --workers=16` ✓ tras T1.
- `EmailUtmTest` (T1c ✅): los 25 `toMail()` pasan `$this` al molde (fuentes); los correos de cuenta renderizados
  llevan la UTM en botón, logotipo y pie y el aviso al negocio no; una URL firmada con UTM pegada valida
  ignorándola y NO valida a secas (la prueba de que va fuera del HMAC); el post-form, la verificación y la
  confirmación de correo abren con UTM y una clave ajena sigue dando 403; `email_sent` solo para clientes;
  `email_clicked` una vez por sesión y solo con claves reales.
- **T2a ✅ (24-09)**: `ReportPeriodTest` (los ocho rangos, el mes pasado desde un 31, la ventana anterior, la
  granularidad, los cubos sin huecos, y en Madrid los bordes en UTC y el cubo de las 22:30 con su control en
  UTC); `MoneyReportTest` (un junio sembrado con todo: el cobro de las 00:30 de Madrid del 1 de junio cuenta en
  junio y el del 1 de julio no —con el control en UTC—, la señal partida por fecha de visita, la línea cancelada
  y la de crédito, canal · método · producto, nuevos frente a recurrentes y las dos medias, lo perdido, el
  periodo vacío, **el presupuesto de 17 consultas que no crece con las filas** y la caché); `AnalyticsPageTest`
  (invitado, cliente, empleado, puerta y admin; el permiso concedido en vivo; cada widget re-pregunta; «Hoy»
  conserva sus dos widgets; las seis tablas; el gráfico en euros por día). `AdminNavigationTest` fija cinco sitios.
- **T2b ✅ (24-09)**: `CustomersReportTest` (las cuentas de cliente sin el equipo ni la de mayo, verificadas y
  compradoras, el método desde el libro con «sin dato» para el alta del panel, el alta de las 22:30 UTC que es
  del 10 en el parque; la puerta desde `audit_logs` con su control —una búsqueda sin `target` cuenta como no
  encontrada, la misma persona dos veces el mismo día cuenta una—, las fichas, las visitas y los clientes con
  visita, las 24 horas del parque, el periodo vacío, el presupuesto de 10 consultas y la caché) y
  `GateVisitsTest` (la visita escrita es un hecho `visit_checked_in`, una vez por cliente y día).
- **T2c ✅ (24-09)**: `FunnelReportTest` (ocho sesiones limpias con el embudo repartido, un bot, una interna, una
  de mayo; los pasos por sesión y el abandono con el fallo técnico; las compras son pedidos web y el del panel y
  el anterior a la medición quedan fuera; la compra de las 00:30 de Madrid en su día; las fuentes fusionan
  visitas y sello —`gclid` sin UTM es `google/cpc`, la referida por su host, el panel no es fuente web— y el
  último toque distinto del primero; entradas, salidas, dispositivo, idioma, horas del parque, el producto por
  su nombre, el contacto; los rechazados desde la ingesta real y los tirados desde el libro; el periodo vacío;
  el presupuesto que no crece con las filas —salvo la traducción de productos, una— y la caché) y, en
  `AnalyticsPageTest`, campaña `=1+1` como texto y `<img onerror>` escapado en las fuentes.
- `PrivacyTest`/`MePrivacyTest`: `anonymize()` y `exportFor()` cubren analytics; `PUT /me/analytics`.
- `machine.test.js`, `controller.test.js`: transiciones emiten `step_entered`; `drawer_opened` al nacer
  abierto; el banner se suprime con el cajón abierto; `SidebarBundleBudgetTest` con el techo de `track.js`.
- Arnés `scripts/mutar-analitica.sh` `(futuro)` con las diez reglas de `/mutar`.
- **En vivo**, con la sonda (`scripts/sonda-analitica.mjs`, T1b ✅: landing, secciones, cajón, cookie, beacon y
  segunda vista; los lotes van con `meta.webdriver` y la sesión queda `is_bot`): además, una compra en sandbox (marcada `is_internal`); el
  registro de red **sin ningún dominio de tercero sin consentir**, y con consentimiento **sin ningún
  `securitypolicyviolation`**; el lote de salida llega tras cerrar la pestaña; en la página `standalone` no
  carga ningún driver; LCP con y sin consentimiento; `redsys:verify-concurrency` en verde tras T1.

## 7. Revisión y decisión

- **23-09, owner**: aprobó la dirección entera (`#678`) con las palabras *«Perfecto, vamos a ello, así lo
  haremos»*; y en segunda ronda, con opciones cerradas: **todo con la v2.0.0** (sin excepción a `#670`),
  **no** a la pregunta «¿cómo nos has conocido?», y **sí** a la revisión adversarial con enjambre antes de
  codificar.
- **24-09, owner**: delega la analítica ENTERA al carril del SPA («el otro agente cerró sesión para delegarte
  toda la analítica») y amplía la T2 con **el dinero al detalle** (valor medio del cliente, totales por mes y
  semana, la señal, lo pagado online), **los registros** por día y mes, y **las búsquedas de la puerta** como
  medida de los clientes del día (`#735`; §4.5).
- `[PENDIENTE: asesoría]`: (1) que el libro agregado con `order_id` cabe en la exención; (2) los textos de
  política y banner y el aviso a las cuentas; (3) Meta/TikTok como destinatarios con hashes bajo `marketing`.

### 7.1 La revisión adversarial (23-09) — 16 agentes, 72 hallazgos confirmados, 1 refutado

Siete lentes (RGPD · dinero · seguridad · rendimiento · producto · medición · consentimiento) intentaron refutar
el §4 y un verificador de OTRA lente intentó tumbar cada hallazgo con el código delante; más un crítico de lo
que faltaba, verificado. **Cada corrección está escrita en su subsección, delante del texto corregido.** Lo
confirmado, agrupado por lo que cambió:

| Lo que la spec v1 afirmaba | Hallazgos | Corrección (dónde) |
|---|---|---|
| «Libro exento» con `user_id` y sello con `visitor_id`/click ids | rgpd-1, ux-1, rgpd-5 | dos regímenes; el cruce bajo `analytics`; capas del sello (§4.1, §2) |
| `nullOnDelete`; sin `anonymize()` ni export ni oposición | rgpd-2, seguridad-3, faltas-2, rgpd-9 | §4.7 nueva; `PUT /me/analytics`; aviso a cuentas; la privacidad no se publica por `LegalDocumentPublisher` |
| Observador de `Order` traduce seis transiciones | dinero-1, dinero-2, medicion-1, faltas-1, dinero-6 | fuente por hecho; captura en `saving`, difiere escalares; incidencia ≠ compra (§4.1) |
| `amount_cents` sin definir | dinero-3 | `paid_cents`, `total_cents`, `refunded_cents` |
| Sello por `created`; pedido manual «con la fuente del operador» | dinero-4, dinero-7, medicion-9, producto-7, rendimiento-6, rendimiento-8 | `creating` + `AttributionContext` + `Select` del panel; columnas planas (§4.1) |
| Último toque de una sesión de 30 min | medicion-3, medicion-10 | `first_touch`/`last_touch`, reglas por click id, `users.first_attribution` |
| `throttle:events` «aparte» dentro del grupo | producto-1, rendimiento-1, seguridad-6 | `withoutMiddleware`, clave por visitante, topes, `trustProxies` |
| `sendBeacon` con CSRF stateful | seguridad-5, rendimiento-2, faltas-4 | ruta stateless; `keepalive`/`Blob`; la vía A en el mismo dominio |
| Cliente fabrica `order_paid`; PII solo por claves; tokens en `page` | seguridad-1, seguridad-2, seguridad-10 | `source: server` rechazado; `route` enmascarada; regex de valores; saneado del CSV |
| 422 al lote entero | producto-3, faltas-9, medicion-7 | validación por evento, `event_id` único, reintentos, `batch_dropped` |
| Bots e internos sin filtro | medicion-6, rendimiento-5, faltas-3 | `is_bot`, `is_internal`, staging con `driver=none` |
| `track.js` «dentro de `paquete.js`»; `data-track`; `reserve_clicked` | rendimiento-4, ux-8, producto-10 | trozo diferido con techo; `data-jw-track`; `drawer_opened` cubre el CTA |
| Abandono = `drawer_closed`; cajón nacido abierto sin `drawer_opened` | medicion-5, faltas-5 | abandono derivado; `reason`; `request_failed`, `client_error` |
| Hash de la cookie `HttpOnly` en el cliente | medicion-4, rendimiento-3, seguridad-4, producto-5 | asignación en servidor por `/sidebar/session`; cookie acuñada en el `202` |
| CSP desde `settings`; `analytics.key`; un solo host de PostHog | seguridad-7, producto-4, ux-4 | `Drivers::csp()` en código; `analytics.posthog_project`; `*.posthog.com` |
| Consent Mode «avanzado» pendiente; mapeo `analytics_storage` | rgpd-3, ux-5 | BÁSICO; fuera de alcance (§2) |
| Tokens de CAPI en `settings`; sin payload; consentimiento congelado | seguridad-8, rgpd-4, ux-7 | `.env`; payload definido con hashes; relectura del consentimiento |
| «`marketing_opt_in` no se ofrece» | rgpd-6, ux-2 | falso: existe; único escritor; solo el momento |
| Segmentos por `born_on` | rgpd-7 | desde los pedidos; `active()`; sección existente |
| Categorías «en `OPTIONAL`»; política «en tres idiomas»; banner «dos botones» | ux-10, ux-6, ux-9, ux-3, producto-9 | seis sitios; migración por sección; tres acciones; suprimido con el cajón; panel `es`/`zh_CN` |
| UTM en «cada enlace de las 26» | medicion-2, producto-2 | antes de firmar + `ignoreQuery`; 23 al cliente; `email_clicked` de servidor |
| `ContactTopics` como molde triple | producto-6 | `enum` de nombres; `props` en PHP; los dos moldes reales |
| `RGPD-06` nuevo | producto-8 | ya existe: `RGPD-07` |
| Sin permisos; sin zona horaria; sin historia; índices y poda | seguridad-9, faltas-6, medicion-8, faltas-7, faltas-8, rendimiento-7 | `analytics.view`/`customers.insights`; `DisplayTime`; T1·bis; `model:prune` |
| Copias de seguridad sin rotación | rgpd-8 | requisito en `ENTORNOS.md` §6 y fila en `DEUDA.md` |

**Refutado**: dinero-5 («la mutación "el recorder lanza y el pago sigue pagado" no prueba nada»): el
verificador midió que el `commit` va dentro del `try` y los callbacks `afterCommit` fuera, así que un `throw`
en el callback propaga con el pago ya confirmado. No tumba el hallazgo: lo AFINA — el test tiene que asertar
las dos cosas (pagado Y sin excepción), y por eso el callback traga todo. Escrito en §4.1 y §6.

## Anexo · fila del enrutador

`| Analítica · conversión · atribución (UTM, anuncios) · consentimiento analytics/marketing | docs/specs/analitica.md §0 |`
