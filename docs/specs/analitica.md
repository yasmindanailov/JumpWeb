# [SPEC] La analítica — medir la conversión en cada sitio y conocer al cliente, dentro del marco legal y a favor del negocio

> Estado: 🟦 **en revisión — dirección `[DECIDIDO owner]` el 2026-09-23 (`#678`)**; el owner revisa §0 y §4
> antes del primer commit de código · Última actualización: 2026-09-23 · Decisión asociada: `DECISIONES #678`.
> Carril: **plataforma** (banda 670–699). Origen: el objetivo del owner del 23-09 —*«medir todo, tener todos
> los números para tomar decisiones; lo más importante la CONVERSIÓN en cada sitio; lo segundo conocer al
> cliente»*— y el hueco que `#670` dejó nombrado («las analíticas») dentro de la v2.0.0.

## §0 · Antes de tocar

- **Regla que ordena todo**: la VERDAD de la conversión vive en el producto —un **libro de eventos** propio,
  en servidor, exento de consentimiento (guía AEPD 2024)—; cualquier herramienta externa es **intercambiable**
  y solo ve lo que se le envía, con consentimiento. Como el libro del pedido para el dinero (`PAY-16`).
- **Empieza por** §1 (lo medido) → §4.1 (el libro y el sello) → §4.2 (el contrato de eventos) → §4.3
  (consentimiento y herramientas). Las tandas, en §4.7.
- **Trampas, antes de tocar**:
  - ⚠️⚠️ **La analítica NUNCA puede tumbar un pago**: escribe `afterCommit` y falla en silencio con rastro
    (§4.1); `RedsysReturnHandler`, `OrderCreator` y `CheckoutOrchestrator` son `CRITICAL_RE` y **no se
    tocan**: se escucha al modelo, no al orquestador.
  - ⚠️⚠️ **Ni un dato personal en `props`** (§4.2): el cliente se ata por `user_id`; a la herramienta externa
    va un id OPACO, nunca el correo.
  - ⚠️ **Sin consentimiento, CERO peticiones a terceros**, medido con el registro de red de la sonda (§6).
    Una categoría nueva sube `CookieConsent::POLICY_VERSION`: se re-pide a todos.
  - ⚠️ **El origen se sella en el pedido al nacer** (`orders.attribution`, como `PAY-19`): las sesiones se
    podan a los 25 meses; el pedido no.
  - ⚠️ Lo compartido (`layout.blade.php`, `app.js`, `resources/js/sidebar/**`, la CSP) **se avisa en el buzón
    ANTES** (`CONVENCIONES §10`).
- **Estado**: T0 la spec ✅ · T1→T5 ⬜ (§4.7). `[DECIDIDO owner]` 23-09: **todo con la v2.0.0** (sin
  excepción a `#670`) y **sin** la pregunta «¿cómo nos has conocido?» (§7). `[PENDIENTE: asesoría]`: la LIA
  y los textos (§4.3).
- **Invariantes**: `RGPD-05`, `SEC-01`, `PERF-02`, `SUITE-01`, `PAY-14` (§5). Dinero: ninguno cambia;
  `redsys:verify-concurrency` tras T1.

## 1. Contexto y problema — MEDIDO (2026-09-23)

| Qué | Medida | Cómo |
|---|---|---|
| Rastreo | **Ninguno** en producto ni instancia: ni GA, GTM, píxel, Hotjar, Plausible, PostHog ni Matomo | `grep -rniE 'gtag\|dataLayer\|plausible\|posthog\|matomo\|fbq\(' app resources routes instancias/` |
| Origen del cliente | **No se guarda**: `orders` tiene 14 columnas y ninguna de canal; ningún `utm_` en app, vistas ni correos | `Schema::getColumnListing('orders')` · `grep -rn utm_` |
| Contacto | `ContactController::store()` **solo envía un correo**; no hay tabla; teléfono y WhatsApp son `<a>` sin medir | lectura + `Schema::hasTable('contact_messages')` = no |
| Hecho fiable | El **pago** lo confirma el servidor (`PAY-01`): pagado / rechazado / expirado / reembolsado | `INVARIANTES.md` §1 |
| Eventos de dominio | Solo tres (`Lockout`, `PasswordReset`, `Verified`); la auditoría (`audit_logs`) es OPERATIVA (panel), no de embudo | `grep -rn 'event(new '` |
| Panel | Dos números: reservas y ocupación del periodo (`DashboardStatsWidget`) | lectura |
| Consentimiento | Banner PROPIO (`COOKIES.md` D1), categorías `maps` y `social`; **`analytics` prevista y sin construir** (D2); versión de política `2026-09-13`; acreditación en `cookie_consent_logs` | `CookieConsent::OPTIONAL` |
| CSP | Orígenes por lista en `SecurityHeaders::contentSecurityPolicy()` (Turnstile, Bunny, Google Maps, feed social) | lectura |
| Cajón | Once pasos con **mapa explícito de transiciones** (`machine.js` `STEPS`) y **un solo cliente HTTP** (`api.js` `request()`, `same-origin`) | lectura |
| Landing (instancia) | 9 vistas; salidas medibles: `Reservar` ×2 portada, ×1 cumpleaños, ×1 servicios · `tel:` ×3 y mapas ×3 en portada · formulario en contacto · WhatsApp/`mailto:` por `contact-channels` | `grep -c` por vista |
| Correos | 26 (22 `Notification` + 2 `Mailable`, todos `ShouldQueue`); sin UTM | `ls app/Notifications` |
| Cliente | `users` guarda `locale`, `last_login_at`, `marketing_opt_in` (nace `false` y **no se ofrece en ningún sitio**), `privacy_accepted_at`; los menores a cargo, en `dependents` | lectura |
| Hosting | PHP 8.5 + MariaDB + Redis; **sin node ni Docker**; el cron del sitio no corre (el del PANEL sí); `QUEUE_CONNECTION=database` | `ENTORNOS.md` §4 y §6 |
| Limitador | `throttle:api` = 60/min por IP, **compartido** con catálogo y disponibilidad | `config/api.php` |
| Anuncios | Google Ads, Meta y TikTok **activos y sin medir** (owner, 23-09); remarketing, no todavía | owner |
| Marco legal | AEPD, enero 2024: la medición de audiencia con cookie propia queda **exenta** si es solo estadística del editor, sin cesión ni cruce, cookie ≤ 13 meses, datos ≤ 25 meses, informada en la política | guía AEPD |

**El problema, en una frase**: el negocio decide a ciegas. No sabe cuánta gente entra, dónde se cae, qué
anuncio trae compras ni quién vuelve — y cada día sin medir es un día que no se recupera.

## 2. Objetivo

1. **Conversión en cada sitio**: para cada paso del camino (entrada → página → cajón paso a paso → banco →
   después) un número, comparable por periodo, fuente, página, dispositivo, idioma, hora y producto; y el
   abandono con su motivo.
2. **Atribución al 100 %**: cada pedido pagado sabe de qué fuente y campaña vino, sin depender del banner; el
   panel da ingresos, CPA y ROAS por plataforma y campaña, una compra contada UNA vez.
3. **Conocer al cliente identificado**: su primera fuente, qué miró antes de comprar, cuántas veces vuelve,
   qué compra, con base legal documentada; y ofrecerle más valor solo con `marketing_opt_in`.
4. **El «por qué»**: grabaciones, mapas de calor y experimentos, bajo consentimiento y sin que la verdad
   dependa de ellos.
5. **Cero peticiones a terceros sin consentimiento**, medido en navegador.

**Fuera de alcance**: remarketing (misma categoría, cuando el owner lo pida); la carga de gasto por API de
las plataformas (primero tecleado); mapas de calor propios; píxel de apertura en correos (se mide el CLIC por
UTM, no la apertura); la app móvil (el contrato ya le sirve; su SDK es de F6); JumpPoints.

## 3. Opciones consideradas

- **A · Una herramienta SaaS como fuente de verdad** (GA4 o PostHog a pelo). Descartada: la verdad quedaría
  gateada por el banner (se pierde a quien no acepta), con las cifras en manos de un tercero y el ROAS
  contado una vez por plataforma. Y GA4 exige Consent Mode v2 y no sirve para «conocer al cliente» propio.
- **B · Matomo en el propio hosting como todo**. Descartada como verdad: es PHP y cabe en el servidor, pero
  embudos, heatmaps, grabaciones y A/B son complementos de 149–249 €/año cada uno, y es una segunda
  aplicación en el servidor de producción con su base, su archivado y sus actualizaciones. Queda como
  **driver alternativo** para un cliente que exija «ningún tercero» (§4.3).
- **C · Libro propio + herramienta intercambiable** — **ELEGIDA** (`[DECIDIDO owner]`): la verdad en casa,
  exenta y al 100 %; PostHog nube UE para el «por qué», bajo consentimiento, con un `driver` por instalación.
- **D · Esperar a la v2.0.0 para desplegar**. No es una opción de diseño sino del CUÁNDO, y el owner la
  eligió (23-09, §7): ninguna excepción a `#670`. La T1 se construye igual y sale con la versión grande.

## 4. Diseño elegido

### 4.1 El libro de eventos, propio (T1)

**Datos** (migraciones `(futuro)`):
- `analytics_sessions`: `id`, `visitor_id`, `user_id` (nullable, `nullOnDelete`), `started_at`, `last_seen_at`,
  `entry_path`, `referrer_host`, `utm_source|medium|campaign|content|term`, `click_ids` json (`gclid`,
  `fbclid`, `ttclid`), `device` (`mobile|tablet|desktop`), `locale`, `consent` json (foto de las categorías),
  `surface` (`web|app`). Una sesión = un visitante con < 30 min de inactividad.
- `analytics_events`: `id`, `session_id`, `visitor_id`, `user_id` (nullable), `name`, `page`, `props` json,
  `occurred_at` (ms, del cliente), `received_at` (del servidor), `order_id` (nullable, para los de dinero).
  Índices por `(name, occurred_at)` y `(session_id)`. **Sin IP, sin user agent completo**: solo `device`.
- `orders.attribution` json: **el sello** de la sesión al nacer el pedido (fuente, campaña, click ids, entrada,
  consentimiento, `visitor_id`), escrito por un observador `created` de `Order` — no por `OrderCreator`,
  que es `CRITICAL_RE`. Un pedido manual del panel se sella con `channel: panel` y la **fuente que elige el
  operador** (`phone`, `counter`, `email`): así se mide la reserva por teléfono.
- **Retención**: comando `analytics:prune` (25 meses) en el scheduler del panel. Es la obligación de la guía y
  la razón de sellar el pedido: la sesión se va, el pedido se queda.

**El visitante**: cookie propia `visitor_id`, `HttpOnly`, `SameSite=Lax`, **13 meses fijos** —no se renueva en
cada visita, como pide la guía—; la escribe el servidor en la primera respuesta. Fuera del mismo origen (la
app móvil, una landing estática) viaja en la cabecera `X-Visitor`. Exenta: mide audiencia del editor, no
cruza sitios ni se cede.

**La ingesta**: `POST /api/v1/events` `(futuro)`, lotes de ≤ 50, anónima, **limitador propio** (`throttle:events`,
aparte del `throttle:api` de 60/min que hoy comparten catálogo y disponibilidad: la analítica no puede
comerse el embudo), `no-store`, respuesta `202` vacía. Un nombre fuera del contrato → `422`: quien escribe
una landing se entera en desarrollo, no en silencio. `Http\Controllers\Api\V1\EventsController` `(futuro)`.

**Los hechos de servidor** (`Domain\Analytics\Recorder` `(futuro)`): un observador de `Order` traduce sus
transiciones de estado —creado, pagado, rechazado, expirado, cancelado, reembolsado— a eventos con
`order_id` y `amount_cents`; el alta, el login, el contacto, el post-form, la respuesta a una invitación y el
envío de cada correo se registran en su servicio. **Reglas**: se escribe `afterCommit` —nunca dentro del
lock de un pago (`PAY-05`, corolario)— y **todo error se traga con `Log`**: un fallo de analítica no puede
convertir un pago en incidencia. Verificado por mutación (§6).

**Los correos**: cada enlace de las 26 notificaciones lleva `utm_source=email&utm_medium=<clave>` puesto por
UN sitio (`Notifications\Concerns\TracksLinks` `(futuro)`), y `email_sent` se registra al enviar. El clic vuelve
como `page_viewed` con esa fuente. La ficha de Google Business Profile enlaza con `?ref=gbp` (retira el
«sin UTM» de `google-business-profile.md` §4.5).

### 4.2 `track()` y el CONTRATO de eventos (T1)

- **Un solo emisor**: `resources/js/cajon/track.js` `(futuro)`, servido dentro de `/cajon/paquete.js`, que la
  landing YA carga: **la instancia no añade código**. Cola en memoria, envío cada 5 s o 10 eventos, y
  `sendBeacon` al salir. Captura sola: `page_viewed` con entrada, referer y `utm_*`/click ids en la primera
  vista; `section_viewed` por `IntersectionObserver`; los clics por **atributo declarativo**
  (`data-track="reserve_clicked" data-track-product="395"`): la landing marca, no programa.
- **El cajón** emite desde `machine.js` (una transición = un `step_entered` con `from`/`to`) y desde el
  controlador de apertura (`drawer_opened`/`drawer_closed` con el paso: **el abandono**). Motivos:
  `availability_missing`, `paused`, `line_problem`.
- **La app móvil** usa el mismo endpoint con Bearer y `X-Visitor`.
- **El contrato** vive en `Domain\Analytics\Contract::EVENTS` `(futuro)` (PHP, la verdad), copiado en el JS y en
  `openapi/v1.yaml` (`POST /events`, contrato **1.18.0**); `AnalyticsContractTest` `(futuro)` exige que las
  tres listas sean idénticas —el molde de `ContactTopics`—. Cada evento declara sus `props` permitidas; una
  clave fuera → `422`. **Ninguna `prop` es dato personal** (guarda por lista negra: `name`, `email`,
  `phone`, `age*`, `dni`).

| Superficie | Eventos |
|---|---|
| Entrada | `page_viewed` (path, entry, referrer_host, utm, click ids, device, locale) |
| Landing | `section_viewed` · `reserve_clicked` (product) · `call_clicked` · `whatsapp_clicked` · `map_clicked` · `contact_form_started` |
| Cajón | `drawer_opened` (page, product) · `step_entered` (from, to) · `product_chosen` · `date_chosen` · `availability_missing` (product, month) · `time_chosen` · `line_added` / `line_removed` (product, qty) · `identify_started` (method: login/register/google) · `identified` · `email_verification_pending` · `pay_started` (amount_cents) · `drawer_closed` (step) |
| Servidor | `order_created` · `order_paid` · `order_declined` · `order_expired` · `order_cancelled` · `order_refunded` (order_id, amount_cents, product ids) · `user_registered` (method) · `user_logged_in` · `contact_received` (topic) · `guest_form_submitted` · `invitation_replied` · `email_sent` (key) · `consent_updated` (categories) |
| Después | `email_clicked` (por UTM) · `guest_form_opened` · `visit_checked_in` (cuando la puerta vuelva) · `experiment_exposed` (key, variant) |

**Definición de conversión** (lo que el panel cuenta): *visita* = sesión; *interés* = `drawer_opened` o
contacto; *intención* = `date_chosen`; *cesta* = `line_added`; *identificado*; *pago iniciado*; **compra** =
`order_paid` (hecho de servidor); *contacto* = `contact_received` + `call_clicked` + `whatsapp_clicked`.

### 4.3 Consentimiento y herramientas (T3)

- **Dos categorías nuevas** en `CookieConsent::OPTIONAL`: `analytics` (grabaciones y mapas de calor) y
  `marketing` (píxeles y conversiones a plataformas). Se corresponden con las señales de Consent Mode v2
  (`analytics_storage` ← `analytics`; `ad_storage`, `ad_user_data`, `ad_personalization` ← `marketing`).
  `POLICY_VERSION` sube: finalidad nueva, consentimiento nuevo para todos. Los textos del banner y de la
  política, en los tres idiomas; la política **declara además lo exento** (el libro) como pide la guía.
- **El banner, profesional y a favor**: dos botones del mismo peso (la AEPD exige que rechazar cueste lo mismo
  que aceptar), una frase de valor, segunda capa por categoría, sin muro y sin premarcar; no se enseña dentro
  de una compra en curso (`CookieWallInvariantTest`); no se repite mientras no cambie la versión.
- **Driver por instalación** (`settings`: `analytics.driver` ∈ `posthog|matomo|none`, `analytics.key`,
  `analytics.host`): con `analytics` consentido, el cargador inyecta el script del driver y la CSP añade SOLO
  sus orígenes (`SecurityHeaders` los lee del ajuste, como hace `SocialEmbed::cspFrameSrc()`). PostHog:
  nube UE (`eu.i.posthog.com`), `person_profiles: identified_only`, entradas enmascaradas, sin IP, sin vista
  automática — **recibe los mismos eventos de `track()`**, para poder cuadrarlos con el libro—, e identifica
  con un **id opaco** (`hash(user_id, APP_KEY)`), nunca el correo.
- **Anuncios (T3b)**: con `marketing`, el cargador inyecta gtag (Consent Mode v2, todo denegado por defecto),
  el píxel de Meta y el de TikTok con sus ids en `settings`; y en `order_paid` un **job en cola**
  (`SendConversionToPlatforms` `(futuro)`, `PAY-14`) envía la conversión a Meta CAPI y TikTok Events API
  **solo si el sello del pedido lleva `marketing: true`**, con `event_id` = código del pedido para no
  duplicar con el píxel. Google Ads por servidor (subida de conversiones) queda para cuando haya acceso
  a la cuenta: API con OAuth. ⚠️ El modo «avanzado» de Consent Mode (pings sin cookie antes de consentir)
  es a favor del negocio y Google lo defiende; **lo firma la asesoría** (`[PENDIENTE: asesoría]`).
- **Base legal para atar navegación y cliente**: interés legítimo para la analítica propia de la relación,
  con **LIA escrita** y su párrafo en la política de privacidad (derecho a oponerse); comunicaciones
  comerciales solo con `marketing_opt_in`, que se pide **tras la confirmación de compra**, sin premarcar
  (hoy nace `false` y no se ofrece en ningún sitio). `[PENDIENTE: asesoría]`.

### 4.4 El cuadro de mando en el panel (T2)

Página `Analítica` en el panel (`specs/panel-navegacion.md` §0: se coloca en `AdminSettingsHub::areas()` o el
menú, o `AdminNavigationTest` la rechaza) con: **embudo** por periodo (visita → interés → intención → cesta →
identificado → pago → compra) y su conversión paso a paso; **fuentes** y campañas (visitas, compras,
ingresos, CPA/ROAS con el gasto tecleado en `ad_spend` `(futuro)`: plataforma, campaña, mes, céntimos);
**páginas** de entrada y salidas; dispositivo, idioma, hora y producto; **abandono por motivo**; **contacto**
(formulario, llamadas, WhatsApp); **repetición** y tiempo hasta la compra; exportación CSV. Se consulta en
directo con caché de 5 min en Redis; los agregados diarios (`analytics_daily` `(futuro)`) se construyen **solo
si se mide lento** (`PERF-07`: medir antes de tocar).

### 4.5 Conocer al cliente (T4)

En la ficha de usuario del panel, la **pestaña 360**: primera fuente y campaña, primera visita, pedidos,
ingresos totales, última compra, frecuencia, productos, menores a cargo (edades ya guardadas en
`dependents`), idioma, contactos recibidos, `marketing_opt_in`. **Segmentos** calculados (compró una vez y no
volvió, cumpleaños en los próximos 60 días, invitado que no compró, contacto sin pedido) exportables **solo
con opt-in**. La pregunta «¿cómo nos has conocido?» tras la confirmación **se descartó** (`[DECIDIDO owner]`,
23-09): lo offline queda con la fuente que marca el operador en el pedido manual (§4.1), y
`attribution_answered` sale del contrato.

### 4.6 Experimentos (T5)

Tabla `experiments` `(futuro)` (clave, variantes, pesos, activo); la config viaja en `/sidebar/boot` (pública y
cacheable) y la **asignación es determinista en el cliente**, `hash(visitor_id, clave)`, con `experiment_exposed`;
el resultado, conversión por variante en el panel con su intervalo. Cubre al 100 %; los experimentos del
driver externo solo ven a quien consintió.

### 4.7 El orden de las tandas

| | Tanda | Entrega | Verificación (§6) |
|---|---|---|---|
| T0 | ✅ esta spec, el contrato de eventos y `#678` | | docs-check |
| T1 | el libro: tablas, cookie, ingesta, `track()`, cajón, hechos de servidor, sello, UTM en correos, poda | contrato **1.18.0** | tests + arnés + `redsys:verify-concurrency` + sonda |
| T2 | el cuadro de mando en el panel y `ad_spend` | | tests + presupuesto de consultas |
| T3a | las dos categorías, el banner, la política, el driver y PostHog | `POLICY_VERSION` | tests + sonda (cero terceros sin consentimiento) |
| T3b | píxeles y conversiones por servidor a Meta y TikTok; gtag con Consent Mode | | tests con `Http::fake` |
| T4 | la ficha 360, los segmentos, el opt-in tras comprar, la pregunta | | tests |
| T5 | experimentos | | tests + una prueba real |

## 5. Impacto en invariantes

- `PAY-01`, `PAY-05`, `PAY-16`: **no cambian**; el observador escribe `afterCommit` y falla en silencio. Se
  corre `redsys:verify-concurrency` tras T1 aunque ningún fichero del `CRITICAL_RE` cambie.
- `PAY-14`: los envíos a plataformas son jobs `ShouldQueue`.
- `RGPD-05`: se extiende a las grabaciones (entradas enmascaradas, sin avatares ni correos).
- `SEC-01`: `/events` dentro del grupo `api`, con limitador propio.
- `PERF-02`: nada por visitante en el arranque cacheado (la config de experimentos es pública; la asignación,
  del cliente).
- `SUITE-01`: PostHog, Meta y TikTok se simulan con `Http::fake`.
- **Propuesta de invariante nuevo** (`RGPD-06`, al cerrar T1/T3): *ningún dato personal en `props`; ninguna
  petición a un tercero sin su categoría consentida; el fallo de la analítica nunca alcanza al dinero*.

## 6. Plan de verificación empírica

- `AnalyticsEventsTest` `(futuro)`: ingesta por lotes, `422` por nombre o `prop` fuera de contrato, la lista
  negra de PII, el limitador propio, `no-store`, la cookie de 13 meses **sin renovación**.
- `AnalyticsContractTest` `(futuro)`: PHP = JS = OpenAPI, con mutante que añade un evento solo en una.
- `AttributionSealTest` `(futuro)`: la primera vista captura y la sesión conserva; el pedido nace sellado; el
  sello **no cambia** si la sesión cambia; el pedido manual lleva la fuente del operador.
- `ServerEventsTest` `(futuro)`: cada transición de `Order` produce su evento con `order_id`; **mutación: el
  recorder lanza y el pago sigue pagado** (`RedsysReturnHandlerTest` verde con el recorder roto).
- `ConsentCategoriesTest` `(futuro)`: sin `analytics` no hay script del driver ni sus orígenes en la CSP; con
  versión vieja se re-pide; `POLICY_VERSION` subida.
- `EmailUtmTest` `(futuro)`: los 26 correos enlazan con su UTM (barrido como `QueuedEmailsTest`).
- `AnalyticsDashboardTest` `(futuro)`: cifras contra hechos sembrados a mano y presupuesto de consultas por
  pendiente (`api-v1.md` §10·17).
- `machine.test.js`: cada transición emite `step_entered`.
- Arnés `scripts/mutar-analitica.sh` `(futuro)` con las diez reglas de `/mutar`.
- **En vivo**, con la sonda: los eventos llegan de la landing, del cajón y de una compra en sandbox; el
  registro de red de Playwright **no contiene ningún dominio de tercero sin consentir**, y sí con
  consentimiento; `redsys:verify-concurrency` en verde tras T1.

## 7. Revisión y decisión

- **23-09, owner**: aprobó la dirección entera —libro propio exento como verdad, PostHog UE intercambiable,
  atar la navegación al cliente con interés legítimo, anuncios medidos por atribución propia y conversiones
  por servidor con consentimiento, sin remarketing todavía— con las palabras *«Perfecto, vamos a ello, así lo
  haremos»*. Registrado en `#678`.
- **23-09, owner, segunda ronda** (preguntado con opciones cerradas y la recomendada delante): **el cuándo**
  — todo con la v2.0.0, sin excepción a `#670`, aunque se le puso delante que cada semana sin medir no vuelve;
  **la pregunta «¿cómo nos has conocido?»** — no; y **sí** a la revisión adversarial con enjambre antes de
  codificar (unos 14 agentes: siete lentes que intentan refutar el §4 y una verificación cruzada por lente).
- `[PENDIENTE: asesoría]`: la LIA, los textos de política y banner, y Consent Mode «avanzado».
- **§7.1 · La revisión adversarial**: sus hallazgos confirmados se escriben aquí, y cada corrección va
  DELANTE del texto que corrige, no se reescribe en silencio (`/spec` §4).

## Anexo · fila del enrutador

`| Analítica · conversión · atribución (UTM, anuncios) · consentimiento analytics/marketing · PostHog | docs/specs/analitica.md §0 |`
