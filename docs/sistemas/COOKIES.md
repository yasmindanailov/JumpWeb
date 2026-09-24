# Sistema — Consentimiento de cookies (banner + bloqueo previo + acreditación)

> Adaptado del proyecto origen (2026-08-12). Describe la BASE HEREDADA: el refactor
> (`00-REFACTOR.md`) puede haberla cambiado. Verifica contra el código antes de construir
> encima (CONVENCIONES §7).

> **Estado REAL (verificado contra el código de este repo, 2026-08-12): ✅ IMPLEMENTADO.**
> El doc origen (`PLAN-COOKIES.md`, histórico en el repo origen) figuraba como 🟦 «en ejecución»,
> pero todos los artefactos existen aquí: helper, gate, componente de bloqueo, banner, endpoint,
> log de acreditación con poda programada, toggle en el panel, i18n y suite de tests completa
> (§6–§7 abajo son el **mapa del código real**, no un plan).
> Los comentarios del código citan «#219» y `PLAN-COOKIES.md`: son la decisión y el doc del
> **repo origen**; este documento los sustituye como referencia.

Solución **a medida** (sin CMP de terceros) para cumplir **art. 22.2 LSSI-CE**, **RGPD**
(arts. 4.11, 7, 13) y la **Guía de cookies de la AEPD (mayo 2024)** en una web comercial en
España, encajada en los principios del producto: data-driven · white-label · i18n · CSP estricta.

---

## 1. Inventario de cookies (heredado del sector origen)

El sitio fija **3 cookies propias + hasta 4 de tercero condicionales**; **cero
analítica/marketing** (no hay GA, GTM, Meta Pixel, Hotjar…). La **CSP**
([SecurityHeaders.php](../../app/Http/Middleware/SecurityHeaders.php)) es la prueba
arquitectónica del universo cerrado de orígenes externos.

| Cookie | Origen | Categoría | ¿Consentimiento? | Cuándo |
|---|---|---|---|---|
| Sesión Laravel (nombre derivado de `APP_NAME`) | Propia | Técnica necesaria | **No** | Siempre |
| `XSRF-TOKEN` | Propia | Seguridad (CSRF) | **No** | Siempre (formularios/Livewire) |
| `remember_web_<hash>` | Propia | Funcional (login) | **No** | Solo si marca «recordarme» |
| Google Maps (`NID`, `SOCS`…) y reseñas de Google (foto del autor en `lh3.googleusercontent.com`) | Tercero (google.com) | **Mapa y reseñas** (clave `maps`) | **SÍ** | Mapa: solo si hay `address.maps_embed_url`, home `#info` (`/contacto` ya no lleva mapa, `#535`). Reseñas: home `#reviews` (`#592`); la NOTA media no pide permiso, la trae el servidor |
| Feed social (SnapWidget/LightWidget) | Tercero | **Social** | **SÍ** | Solo si hay `social.feed_embed_url`; home `#gallery` |
| Cloudflare Turnstile (`__cf_bm`) | Tercero | Seguridad | **No** (exenta) | Si Turnstile está activo |
| Redsys | Tercero (en SU dominio) | Técnica necesaria | **No** | Solo al pagar (redirección, no iframe) |
| Bunny Fonts | Tercero | **Sin cookies** | **No** | Siempre (elegido GDPR-friendly) |

**Cuatro categorías con consentimiento** (`CookieConsent::OPTIONAL`, en el orden del panel): `maps` y
`social` (iframes opcionales, configurables desde el panel) y, desde la **T3a de la analítica**
(`specs/analitica.md` §4.3, 2026-09-24), **`analytics`** (análisis de uso IDENTIFICADO: atar la navegación a
la cuenta al entrar o comprar, y la herramienta de análisis con id opaco) y **`marketing`** (píxeles de
anuncios y comunicación de la compra; los píxeles llegan en la T3b). El idioma **no añade cookie** (vive
en la sesión server-side, [SetLocale.php](../../app/Http/Middleware/SetLocale.php)).

| Cookie | Origen | Categoría | ¿Consentimiento? | Cuándo |
|---|---|---|---|---|
| `visitor_id` (13 meses, no se renueva) | Propia | **Medición de audiencia exenta** (guía AEPD 2024: estadística anónima del editor, sin cruce ni cesión, datos ≤ 25 meses) | **No** (se declara en la política y en «Necesarias») | Siempre (`ResolveVisitor`, `specs/analitica.md` §4.1) |
| Herramienta de análisis (PostHog/Matomo) | Tercero | **`analytics`** | **SÍ** | Solo con la categoría y el driver configurado (T3a·2) |
| Píxeles (Google Ads, Meta, TikTok) | Tercero | **`marketing`** | **SÍ** | Solo con la categoría y el id configurado (T3b) |

**Exentas** (sin consentimiento, pero **sí transparencia** en la política): sesión, XSRF,
`remember_web` (acción del usuario), Turnstile (seguridad), Redsys (técnica, en su dominio) y la
medición de audiencia propia (`visitor_id`).

## 2. Marco legal (resumen accionable — AEPD mayo 2024 + LSSI 22.2 + RGPD)

1. **Banner de 1.ª capa** claro + enlace a la 2.ª capa (política).
2. **Aceptar / Rechazar / Configurar** en **igualdad** (mismo nivel, formato, visibilidad, nº de
   clics). Degradar «Rechazar» es el incumplimiento más sancionado.
3. **Sin** casillas premarcadas, **sin** «scroll = consentir». Por defecto todo lo no necesario OFF.
4. **Granularidad por finalidad**: mapa y social son finalidades distintas → toggles separados.
5. **Bloqueo previo**: no instalar/leer cookies no exentas hasta el consentimiento.
6. **Revocar tan fácil como aceptar** (art. 7.3): enlace permanente «Configuración de cookies».
7. **Plazo: 24 meses**; re-pedir antes si cambian finalidades/terceros/política.
8. **Acreditación** (art. 5.2/7.1): registro de quién/cuándo/qué.
9. **2.ª capa**: listado por categoría con finalidad, titular, duración y **transferencias
   internacionales** (Google LLC y Cloudflare → EE. UU., cubiertas por el EU-US Data Privacy
   Framework; el proveedor del feed social queda `[PENDIENTE: verificar DPF/SCC]`).

**Invariante:** prohibido el «cookie wall». Rechazar **no** degrada comprar/reservar (dependen
solo de cookies técnicas exentas). Test que lo protege: `CookieWallInvariantTest`.

## 3. Decisiones de diseño

- **D1 — A medida, no CMP comercial.** El universo sujeto a consentimiento es mínimo y ya está
  aislado tras el composer ([AppServiceProvider.php](../../app/Providers/AppServiceProvider.php),
  `$site['maps_embed']`/`$site['social_feed']`). Un CMP metería otro script/cookie/transferencia,
  chocaría con la CSP y rompería data-driven/white-label/i18n.
- **D2 — Categorías separadas, y SIN QUEMAR desde la T3a** (2026-09-24): `maps`, `social`, `analytics`
  y `marketing` viven SOLO en `CookieConsent::OPTIONAL`; `state()`, `encode()`, el controlador, los
  `data-cookie-*` del `<body>`, el almacén de Alpine (`ui/cookie-consent.js`, que lee
  `data-consent-categories`) y el panel del banner las RECORREN. Añadir una finalidad es añadirla ahí,
  darle sus textos (`lang/{es,en,fr}/cookies.php` `panel.<cat>_title/_desc`, `CookiePolicyContent`) y
  subir `POLICY_VERSION`. `preferences` sigue sin construir.
- **D3 — Fail-safe a privacidad:** sin cookie / corrupta / versión caducada ⇒ **NO** consentido ⇒
  no se cargan terceros + banner visible. (Opuesto a `MaintenanceSettings`, fail-safe a
  «disponible»: aquí el fallo seguro es **no** instalar cookies.)
- **D4 — Cookie `cookie_consent`:** first-party, **sin cifrar** (la leen el servidor —gate— y
  Alpine —UI—) → en `encryptCookies(except:)` de [bootstrap/app.php](../../bootstrap/app.php).
  `SameSite=Lax`, `Max-Age=24 meses`, `Secure` según config, `httpOnly=false`. Valor =
  **base64(JSON)** `{"v":<versión>,"cats":{"maps":bool,"social":bool}}` (base64 evita `;`/comas
  en el valor). La **escribe siempre el servidor**; Alpine solo mantiene prefs en memoria.
  Renombrada de `jj_cookie_consent` a `cookie_consent` en Fase 1 (los navegadores con la cookie antigua re-consienten). Nota histórica de
  `00-REFACTOR.md` (renombrarla invalida consentimientos ya dados → re-pediría a todos).
- **D5 — Versión de política como constante** (`CookieConsent::POLICY_VERSION`, patrón de
  `Consent::CURRENT_VERSION`): subirla fuerza re-consentir (el gate la ve caducada). v1 = `2026-06-08`.
  v2 = `2026-09-13` (`#592`, `[DECIDIDO owner]`): la categoría `maps` pasa a cubrir también las
  reseñas de Google y la foto de quien las escribe —dependían de ella desde `#491` sin que el banner lo
  dijera—, así que se vuelve a pedir. La clave `maps` NO se renombra. **v3 = `2026-09-24`** (T3a de
  `specs/analitica.md`, `#678`): dos finalidades nuevas (`analytics`, `marketing`) y la medición propia
  declarada exenta → consentimiento nuevo para todos, la misma noche que la v2.0.0; el controlador
  exige las CUATRO claves (un banner de la v2 que mande dos recibe 422, no un «no» tácito).
- **D6 — Acreditación en tabla propia `cookie_consent_logs`** (NO reutiliza `consents`: su
  `user_id` es FK NOT NULL y no tiene `user_agent` → no cubre al visitante anónimo). Modelo
  `App\Domain\Identity\Models\CookieConsentLog` (nombre distinto del helper `App\Domain\Identity\Services\CookieConsent` para no
  colisionar). Se registra **solo en decisiones explícitas**, no en cada visita (proporcionalidad).
- **D7 — Textos del banner en i18n** (`lang/{es,en,fr}/cookies.php`), no en settings: muchas
  cadenas, y los lang files son traducibles y white-label. Único ajuste data-driven:
  `cookies.banner_enabled`.
- **D8 — CSP sin cambios:** el bloqueo es a nivel de **render del iframe**, no de cabecera.
- **D9 — Turnstile y Redsys fuera del bloqueo** (seguridad / pago iniciado por el usuario).
  Diferir el script de Turnstile al abrir el modal quedó **fuera de alcance** (no romper auth);
  se informa en la política.

## 4. Arquitectura (artefactos reales en este repo)

**Autoridad única — [`App\Domain\Identity\Services\CookieConsent`](../../app/Domain/Identity/Services/CookieConsent.php)**
(helper estático, sin BD):
- `COOKIE_NAME='cookie_consent'` · `POLICY_VERSION='2026-09-24'` ·
  `OPTIONAL=['maps','social','analytics','marketing']` · `LIFETIME_MINUTES` (24 meses).
- `state(Request): array<string,bool>` — una clave por categoría de `OPTIONAL` más `decided`; defensivo:
  base64/JSON inválido o versión distinta ⇒ todo `false`, `decided=false`. Nunca lanza (se invoca en
  cada render). `allSetTo(bool)` da el mapa entero a un valor.
- `encode(array $cats): string` — valor de cookie (base64 JSON con versión); lo usa el endpoint.
- `bannerEnabled(): bool` — lee `cookies.banner_enabled`, default `'1'`; solo el literal `'0'`
  apaga. **Apagar el banner NO desactiva el bloqueo previo** (los iframes siguen gateados por
  `state()`); solo oculta el aviso.

**Composer** ([AppServiceProvider](../../app/Providers/AppServiceProvider.php)): expone a todas
las vistas `$cookieConsent` (= `CookieConsent::state(request())`) y `$cookieBannerEnabled`. En la
rama sin tabla `settings` (CI/instalación limpia): no decidido + banner off.

**Bloqueo previo —
[`<x-site.consent-frame>`](../../resources/views/components/site/consent-frame.blade.php)**
(componente ÚNICO; así no se puede olvidar gatear un iframe nuevo):
- Props: `category` (`maps|social`), `src` (embed configurado o null), `title`, clases/estilos
  de wrapper e iframe; slot por defecto = estado «no configurado» (pin del mapa / galería estática).
- Tres estados: **no configurado** → slot. **Configurado + consentido** → `<iframe src>` directo
  (server-side, sin JS). **Configurado + NO consentido** → placeholder con botón «Cargar …» +
  enlace a la política; el iframe lleva `data-src` y Alpine (`consentFrame` en
  [app.js](../../resources/js/app.js)) inyecta el `src` al consentir, sin recarga. Reacciona al
  store: si el banner concede la categoría, el frame carga vía effect.
- La decisión la toma el **servidor** (`$cookieConsent`), no el cliente: las cookies del tercero
  se fijan dentro de su iframe; la única defensa real es no cargarlo.
- Usos (3): [home](../../resources/views/home.blade.php) `#info` (mapa) y `#gallery` (social) +
  [/contacto](../../resources/views/pages/contact.blade.php) (mapa).

**Banner —
[`<x-site.cookie-banner>`](../../resources/views/components/site/cookie-banner.blade.php) +
`Alpine.store('cookies')`** ([ui/cookie-consent.js](../../resources/js/ui/cookie-consent.js), registrado
desde [app.js](../../resources/js/app.js)):
- Inyectado en [layout.blade.php](../../resources/views/components/layout.blade.php) para todos;
  el estado inicial servidor→cliente viaja por atributos `data-cookie-<categoría>` del `<body>` (uno por
  `OPTIONAL`) más `data-consent-categories` (la lista, que es de donde el almacén las lee; no empieza por
  `cookie` a propósito: `track.js` manda al libro todas las `data-cookie-*` como foto del consentimiento).
- Store: `{categories, decided, enabled, prefs:{<cat>…}, panel, visible, showing}` + `acceptAll`,
  `rejectAll`, `grant(cat)`, `openPanel`, `closePanel`, `savePanel`, `persist`, `noteShown`.
  **`showing`** es lo que se pinta: la primera capa o el panel, y **nunca con el cajón de compra abierto**
  (`$store.purchase.isOpen`; el aviso espera a que se cierre). **`noteShown()`** cuenta `consent_shown`
  UNA vez por página por `JumpWeb.track()` (el buzón que el tracker vacía al llegar). Probado con
  `node --test` (`cookie-consent.test.js`): la atomicidad de `RGPD-05` tiene test desde la T3a.
- **Capa 1**: 3 botones en igualdad «Aceptar» · «Rechazar» · «Configurar» + texto breve que INFORMA
  (qué se mide y para qué) + enlace a la política. Sin preselección.
- **Capa 2** (panel): Necesarias (ON, disabled; explica la medición exenta) · un toggle por categoría
  de `OPTIONAL` (`data-consent-category`) + «Guardar preferencias». Al reabrir desde el pie el foco va al
  título (`tabindex="-1"`): anuncio accesible.
- `persist()` → `fetch POST /cookies/consentimiento` con las CUATRO claves (el servidor registra y
  escribe la cookie) + actualiza prefs en memoria (los frames cargan por el effect).

**Endpoint — `POST /cookies/consentimiento`**
([CookieConsentController](../../app/Http/Controllers/CookieConsentController.php), name
`cookies.consent`, [routes/web.php](../../routes/web.php)):
- Grupo `web` (CSRF por cabecera `X-CSRF-TOKEN` desde el `<meta name="csrf-token">` del layout),
  `throttle:30,1`. **Controlador plano, no Livewire** → funciona para anónimos.
- Valida un booleano OBLIGATORIO por categoría de `OPTIONAL`; crea fila en `cookie_consent_logs` (`user_id` nullable,
  `categories`, `version`, `ip`, `user_agent` truncado a 512, `accepted_at`); responde
  `{ok:true}` con la cookie (`CookieConsent::encode`, httpOnly=false, SameSite=Lax, 24 meses).

**Acreditación + retención:**
- Migración `2026_06_08_000001_create_cookie_consent_logs_table` — `user_id` nullable
  (`nullOnDelete`), `categories` json, `version`, `ip` string(45) nullable, `user_agent`
  string(512) nullable, `accepted_at`, timestamps.
- [CookieConsentLog](../../app/Domain/Identity/Models/CookieConsentLog.php): casts categories→array,
  accepted_at→datetime; `belongsTo(User)`. **Es `Prunable`**: borra filas más antiguas que
  `LIFETIME_MINUTES` (24 meses), con `model:prune` programado en
  [routes/console.php](../../routes/console.php). *(El plan origen lo listaba como «futuro»;
  aquí está implementado.)*

**Panel (data-driven)** — sección «Cookies» en
[Settings.php](../../app/Filament/Pages/Settings.php): `Toggle cookies.banner_enabled`
(default ON; en `MANAGED` grupo `cookies` + `BOOL_KEYS` + `BOOL_DEFAULT_ON`). i18n admin
es/zh_CN (`admin.settings.cookies_banner_enabled*`).

**Footer** ([footer.blade.php](../../resources/views/components/site/footer.blade.php)): enlace
permanente «Configuración de cookies» → `$store.cookies.openPanel()` (revocar = art. 7.3).

**Política de cookies (2.ª capa)** — fuente única
[`App\Domain\Content\Services\CookiePolicyContent`](../../app/Domain/Content/Services/CookiePolicyContent.php): estructura
`pages.body` por idioma (secciones `{h,p}`), reutilizada por (a) el seeder
(`LandingContentSeeder::seedPages`) y (b) la migración de reparación **idempotente**
`2026_06_08_000002_refresh_cookie_policy_content` (refresca la página `cookies` **solo si** aún
contiene el marcador `[PENDIENTE]/[PENDING]/[À COMPLÉTER]` → no pisa ediciones del operador).
Los datos fiscales del responsable van como tokens `:legal_name/:legal_nif/...` que
`LegalIdentity::interpolate()` sustituye en el render con los settings del panel.
**T3a (2026-09-24)**: tres secciones nuevas —la medición propia exenta, el análisis identificado y la
publicidad— y el párrafo de transferencias que las nombra, en constantes públicas de la clase; llegan a
una BD ya sembrada por la migración quirúrgica `2026_09_24_120000_cookie_policy_adds_analytics_and_marketing`
(inserta detrás de «Cookies técnicas» solo si ninguna está; sustituye transferencias solo si es el texto
del producto). La PRIVACIDAD (`LegalContent::PROFILING_P`) deja de decir «ni elaboramos perfiles» a secas
(`2026_09_24_120100_privacy_policy_names_identified_analytics`, mismo criterio).

## 5. Tests (existen todos)

- [tests/Unit/CookieConsentStateTest.php](../../tests/Unit/CookieConsentStateTest.php) (sin BD):
  parseo válido/corrupto/ausente/versión caducada (la v2 incluida) → fail-safe; round-trip
  `encode`/`state` con las cuatro; `bannerEnabled` defensivo.
- `resources/js/ui/cookie-consent.test.js` (`node --test`): categorías desde el body, «aceptar todo»
  acepta TODAS, solo `res.ok` decide (`RGPD-05`), la tarjeta calla con el cajón abierto, `consent_shown`
  una vez.
- `tests/Feature/Cookies/`:
  - `CookieGateBlockingTest` — sin cookie, `/` no contiene el `src` de Maps (placeholder presente);
    con consentimiento por categoría, sí; los `data-cookie-*` y `data-consent-categories` del body; un
    toggle por categoría con su texto; el marcado engancha `showing`, `noteShown()` y el foco al título.
  - `CookieConsentEndpointTest` — POST escribe cookie + crea fila (anónimo/autenticado); las cuatro
    claves obligatorias (dos → 422); de punta a punta POST → cookie → `state()` → `data-*`; prune.
  - `CookieBannerSettingTest` — visibilidad del banner según decisión y setting.
  - `CookiePolicyContentTest` — `/cookies` sin marcadores `[PENDIENTE]…`; proveedores reales;
    i18n; las tres secciones de la T3a en los tres idiomas y su orden; migraciones de reparación
    idempotentes que no pisan ediciones (`#592` y la de la T3a).
  - `PrivacyPolicyProfilingTest` — la privacidad nombra el análisis identificado y cómo retirarlo; su
    migración quirúrgica.
  - `CookieWallInvariantTest` — sin consentimiento, comprar/reservar sigue accesible.
- En vivo: `scripts/sonda-cookies.mjs` (tarjeta, panel con las cuatro, guardar, `consent_shown` y
  `consent_updated` en el libro, recarga, el pie con el foco, móvil, y la espera al cajón abierto).

## 6. Pendientes reales / fuera de alcance

- **Minimización de Turnstile** (diferir el script a abrir el modal): no hecho; se informa de la
  transferencia en la política.
- **Verificación DPF/SCC del proveedor del feed social**: la política y el helperText del ajuste
  `social.feed_embed_url` avisan; el texto de `CookiePolicyContent` conserva un
  `[PENDIENTE: confirmar adhesión al DPF o SCC]` que cada operador/su asesoría debe cerrar.
- **Redacción legal definitiva**: el texto de la política es técnico-orientativo y refleja las
  cookies reales; la validación jurídica corresponde a la asesoría de cada cliente del producto.
- **Analítica**: el libro propio existe (`specs/analitica.md`, T1) y es exento; el análisis
  IDENTIFICADO y la herramienta externa van bajo `analytics` (T3a·1 ✅: categoría, banner, política; T3a·2 ✅:
  el driver —`Drivers`, «Ajustes → Herramienta de análisis», `cajon/driver.js` solo con la categoría, sus
  orígenes en la CSP solo con driver activo, `/cookies` lo nombra al pintar, `ForgetPersonInDriver`—; T3a·3 ✅:
  el enlace sesión↔cuenta al entrar, al alta y al cobro solo con la categoría, la oposición desde «Mi cuenta →
  Privacidad» y `PUT /me/analytics`, que desvincula, sella la prueba y pide el olvido; T3a·4 ✅: las cuentas
  anteriores a la v3 se INFORMAN antes de activar el enlace —el correo `AnalyticsLinkNotice` por
  `analytics:notify-accounts`, una vez por cuenta, y el aviso del índice del cajón, que viaja con su texto en
  el contexto de cuenta hasta que se despide con `DELETE /me/analytics-notice`—); los píxeles bajo
  `marketing` (T3b).
- **Refactor de marca**: HECHO en Fase 1 (cookie renombrada a `cookie_consent`); quedan las
  referencias históricas «#219 / PLAN-COOKIES.md» en comentarios (tabla de equivalencias en
  `docs/README.md`).

## 7. Riesgos y mitigaciones

- **JS deshabilitado:** el banner Alpine no aparece → los frames quedan en placeholder (no cargan
  terceros = conforme).
- **Cookie sin cifrar manipulable:** solo afecta a la experiencia del propio usuario (no es dato
  sensible ni de seguridad). Aceptable.
- **Olvidar gatear un iframe futuro:** mitigado por el componente único `consent-frame` + tests
  que recorren `/` y `/contacto`. **Regla:** todo embed de tercero nuevo pasa por
  `<x-site.consent-frame>` (o gate equivalente) y se añade a `OPTIONAL` si su finalidad es nueva.
- **Pisar ediciones del operador en la política:** la migración de reparación solo actúa si
  persiste el marcador `[PENDIENTE]`.

## 8. Vocabulario heredado

El setting hermano `puerta.waiver_check_enabled` citado como patrón de `bannerEnabled` usa
«puerta»/«waiver» (vocabulario del sector origen; su generalización se decide en
`00-REFACTOR.md` Fase 1/2). Las páginas legales sembradas incluyen el slug `waiver`.
