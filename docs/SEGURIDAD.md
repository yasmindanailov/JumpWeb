# Estándar de seguridad — JumpWeb

> Adaptado del proyecto origen (2026-08-12). Describe la BASE HEREDADA: el refactor
> (`00-REFACTOR.md`) puede haberla cambiado. Verifica contra el código antes de construir
> encima (CONVENCIONES §7).

Reglas de seguridad **transversales** a todo el producto. Nivel adoptado: **Reforzado**
(decisión heredada del origen). Basado en **OWASP ASVS**, **OWASP Top 10 (2021)** y
**NIST SP 800-63B**. No es contenido editable de negocio: son reglas que el código debe cumplir.

> Los números `#NN` citados abajo son decisiones del repo origen (histórico allí, no en
> `DECISIONES.md` de JumpWeb); los comentarios del código heredado los referencian, se
> conservan para poder mapearlos.

## Nivel adoptado
**Reforzado** = base "Sólido" (autenticación profesional por defecto) **+** anti-bot en
formularios públicos **+** auditoría de eventos de seguridad **+** bloqueo temporal de cuenta
tras varios intentos fallidos. Motivo: el producto trata **datos personales** (RGPD) y
**pagos**; blindar desde el inicio es más barato que parchear.

## Base ya cubierta (verificado en el código del fork, 2026-08-12)
- Contraseñas con **bcrypt factor 12** (`.env.example`, `BCRYPT_ROUNDS=12`).
- **Sesiones en BD**; cookie `http_only` + `same_site=lax` (`config/session.php`).
- Enlaces de **reset con caducidad** 60 min y antiflood (`config/auth.php`, `expire => 60`).
- Protección **CSRF** de Laravel activa en formularios web.
- Cabeceras de seguridad + **CSP progresiva** vía middleware `SecurityHeaders` (ver regla 9).
- **Turnstile** data-driven (`app/Domain/Platform/Services/Turnstile.php`; ver regla 5).
- `User::anonymize()` y logging sensible con hash (`logSensitive`; ver regla 7).

## Reglas (qué debe cumplir el código)

### 1. Contraseñas — NIST 800-63B
- Mínimo **8** caracteres; permitir contraseñas **largas / frases**; sin reglas de composición absurdas.
- ❌ ~~**Rechazar contraseñas filtradas**~~ — **RETIRADO el 2026-09-02** (`[DECIDIDO owner]`,
  `DECISIONES #351`). Hasta esa fecha la política llevaba `->uncompromised()` (consulta por
  k-anonimato a *Have I Been Pwned*) y rechazaba **cualquier** contraseña que apareciera en el corpus,
  aunque fuera una sola vez. El owner lo retiró por FRICCIÓN en el alta: es un «no» que el cliente no
  sabe cómo arreglar. ⚠️ **El coste está asumido y dicho: `12345678` es hoy una contraseña válida.**
  Se le ofreció la vía intermedia —`uncompromised(500)`, que rechaza solo las muy comunes— y la
  descartó. ▶ Lo que sostiene la defensa ahora son los **límites de la sección 2**, la
  reconfirmación de contraseña de la 4 y `RGPD-06`.
  ⚠️ La cita de superficies ya estaba caducada: los tres componentes Livewire que nombraba se
  retiraron con el modal (`#122`); desde `#144` la política vive en **`Identity\Services\PasswordPolicy`**,
  fuente única, y lo vigila `PasswordPolicySingleSourceTest` —que desde `#351` asevera **lo contrario**,
  para que nadie la reponga creyendo que arregla un descuido.
- Hash **bcrypt 12** (o `argon2id` si el hosting lo soporta).

### 2. Fuerza bruta y enumeración — OWASP A07 / ASVS V2.2
- **Límite de intentos** en login, registro, reset y reenvío de verificación.
- **Bloqueo temporal** de la cuenta tras N fallos seguidos *(Reforzado)*.
- **Mensajes genéricos**: nunca revelar si un email existe ("credenciales incorrectas"; "si la
  cuenta existe, te hemos enviado un correo"). ⚠️ **Excepción DECIDIDA en el alta**
  (`DECISIONES #31a`): el registro sí dice que un correo ya tiene cuenta —decisión de producto de la
  clienta, prima la conversión—, y la API replica esa política a propósito. Lo que acota la
  enumeración ahí es el límite de 3 altas/hora por correo, no el mensaje. En la recuperación de
  contraseña la no-enumeración es **estricta**.
- **Dónde vive** (Fase 3 · paso 3, `DECISIONES #29`–`#31`): los limitadores y las reglas son de
  DOMINIO y los consumen por igual la web y `/api/v1` —`Identity\Services\PasswordLogin` (los DOS
  limitadores: por email+IP y por IP sola, anti-spraying), `SelfSignup` (señuelo + límite por IP +
  límite por correo + anti-bot) y `PasswordRecovery`—. Ningún controlador ni componente los
  reimplementa: si alguno lo hiciera, la puerta floja sería la que se olvidara del segundo.

### 3. Sesión — ASVS V3
- **Regenerar** el ID de sesión al iniciar sesión; invalidar al cerrar.
- **"Cerrar sesión en todos los dispositivos"** disponible.
- Cookies: `http_only` (ok), `same_site=lax` (ok), **`secure=true` en producción**, valorar `encrypt=true`.
- **Reconfirmar contraseña** antes de acciones sensibles (cambiar email, borrar cuenta).

### 4. Verificación de email — ASVS V2.5
- Enlace **firmado y caduco** (~60 min). **Re-verificar** si el usuario cambia su email.

### 5. Anti-bot *(Reforzado)*
- **Cloudflare Turnstile** (o hCaptcha) en registro y formularios públicos. Clave en `settings`
  (data-driven); si no hay clave configurada, se desactiva solo. Implementado en
  `app/Domain/Platform/Services/Turnstile.php`, y lo aplica `Identity\Services\SelfSignup` —el
  servicio de alta que consumen el modal de la web y `POST /api/v1/auth/register`— más
  `ContactController`.

### 6. Autorización — OWASP A01
- **Roles / permisos** (`admin`, `customer`; preparado para `staff`) con Gates/Policies.
- Área privada tras middleware **`auth` + `verified`**.

### 7. Datos personales / RGPD
- **Consentimientos versionados**: tipo, fecha, IP, versión del documento aceptado.
- **Minimización**; **no** registrar contraseñas ni tokens en los logs.
- **Exportar** y **borrar** la propia cuenta. Borrado = **real (hard delete)** mientras no haya
  pagos del usuario; **anonimización** cuando existan pagos (conservar facturas) — decisión
  heredada del origen (#53).
- **Anonimización desde el panel:** el admin puede anonimizar a un cliente (`User::anonymize()`)
  y enviarle un enlace de cambio de contraseña, **solo sobre cuentas de cliente** (no
  admin/staff, no uno mismo, no ya-anonimizada). El **audit de la anonimización NO reintroduce
  PII**: guarda el email como **sha256** (`email_hash`) + el motivo + conteos (consents/roles),
  nunca el dato en claro; el reset usa `logSensitive` (email solo como hash). El formulario de
  contacto es **email-only** (no persiste datos personales en BD). (Decisión origen #180.)
- Teléfono **en claro**: se busca por él en **puerta** (vocabulario del sector origen; su
  generalización se decide en `00-REFACTOR.md` Fase 1/2 — ver
  `OPERATIVA-SECTOR-ORIGEN.md`); se protege con **control de acceso por rol**, no con cifrado.

### 8. Auditoría / logging *(Reforzado)*
- Registrar eventos: login correcto/fallido, bloqueo, reset, cambio de email/contraseña,
  borrado de cuenta. Sin datos sensibles en el log (hash en vez de email → `logSensitive`).

### 9. Cabeceras HTTP — OWASP Secure Headers
Implementación real heredada: middleware **`app/Http/Middleware/SecurityHeaders.php`**
(el doc origen decía "CSP → fase futura", pero la CSP progresiva YA está en el middleware):
- Fijas: `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`,
  `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy` (camera/mic/geo/topics off).
- **CSP "progresiva"**: `default-src 'self'`, `object-src 'none'`, `base-uri 'self'`,
  `frame-ancestors 'self'`; permite `'unsafe-inline'`/`'unsafe-eval'` porque Alpine, Livewire y
  los estilos inline del mockup los necesitan hoy. La **CSP estricta** (sin `unsafe-*`) quedó
  diferida en el origen (#54).
- Orígenes externos permitidos (gotchas al editar la CSP): Bunny Fonts (`fonts.bunny.net`),
  Turnstile (`challenges.cloudflare.com` en script/connect/frame), mapa embebido de Google
  (`frame-src`), el feed social vía `SocialEmbed::cspFrameSrc()`, las fotos de autor de las reseñas
  (`lh3.googleusercontent.com`, solo `img-src`) y **la herramienta de análisis SOLO con el driver activo**
  (`specs/analitica.md` §4.3, T3a·2): `Drivers::csp()` da sus orígenes por directiva —`https://*.posthog.com`
  o el host de Matomo, reconstruido— en script/connect/img; sin driver la CSP no cambia. El gate real es no
  inyectar el script sin la categoría `analytics` (`COOKIES.md` D8); esto es la segunda cerradura. Y **los
  píxeles de anuncios SOLO con el píxel configurado** (T3b·1): `Pixels::csp()` da los orígenes de Google Ads
  (`googletagmanager.com`, `google.com`, `googleadservices.com`, `googleads.g.doubleclick.net`), Meta
  (`connect.facebook.net`, `www.facebook.com`) y TikTok (`analytics.tiktok.com`) por plataforma y directiva
  (script/connect/img, nunca frame); `SecurityHeaders` los funde con los del driver. El gate real es no
  inyectarlos sin la categoría `marketing` (`cajon/pixels.js`).
- **`form-action` limitado al origen Redsys del entorno configurado** (setting
  `redsys_environment`): en producción el navegador rechaza envíos al TPV sandbox y viceversa —
  hardening de defensa en profundidad (origen #113): un entorno mal configurado falla de forma
  visible antes de enviar el form.
- En `local` añade los orígenes del dev-server de Vite (5173, HTTP y WS) para HMR.
- El middleware **no pisa** una CSP que una respuesta concreta ya haya fijado.
- **HSTS**: NO lo emite el middleware; en el origen se activó en la capa CDN/proxy al ir a
  producción. En JumpWeb queda **pendiente para su propio despliegue** (junto con la CSP estricta).
- **También en `/api/v1`** desde Fase 3 · paso 0 (`SEC-01`), y colocado el PRIMERO del grupo para
  que las cabeceras lleguen igualmente al 429 del limitador y al 503 de mantenimiento. Coste
  medido: 1 consulta por petición (la lectura memoizada de `settings` que la CSP necesita), fijada
  por `ApiOverheadTest`.

### 9.bis CORS — solo orígenes exactos *(Fase 3 · paso 0, `DECISIONES #24f`)*
`HandleCors` es middleware **global** de Laravel y su configuración por defecto —la del framework,
mientras `config/cors.php` no esté publicado— aplica `allowed_origins: ['*']` a `api/*`. Es decir:
**crear `routes/api.php` abrió CORS a cualquier origen sin que nadie lo decidiera** (comprobado con
`curl -I`: la cabecera aparecía solo bajo `/api/v1`).
- Cerrado publicando `config/cors.php`: orígenes **exactos** derivados de `APP_URL`, nunca `*` ni
  patrones (un comodín de subdominio dejaría entrar a `evil.cliente.com`).
- `supports_credentials: true`, que es seguro precisamente porque no hay comodín: el navegador
  prohíbe combinar credenciales con `*`.
- La SPA de Fase 4 vive en el MISMO dominio, así que en el caso normal no se emite ninguna cabecera
  CORS. Una instalación que sirva la SPA en otro dominio lo declara en `CORS_ALLOWED_ORIGINS`, como
  decisión de despliegue con nombre y sitio.
- ⚠️ Al abrir cualquier superficie nueva, revisar no solo qué middleware se HEREDA sino qué
  middleware **global** se despierta con ella.

### 10. Endurecimiento de entorno (producción) — `[PENDIENTE]` hasta el despliegue de JumpWeb
- `APP_DEBUG=false`, `APP_ENV=production`, `SESSION_SECURE_COOKIE=true`, HTTPS forzado,
  secretos fuera del repositorio, HSTS.

### 11. Pruebas automáticas (cultura del proyecto)
- No se entra al área privada sin login / sin verificar; el límite de intentos corta; el token
  de reset caduca; el registro exige las casillas legales; etc. (Ver `TESTING.md`.)

### 12. Livewire
- **Validar siempre en servidor** (`#[Validate]`); no confiar en propiedades del cliente;
  **autorizar** cada acción; no exponer datos sensibles en propiedades públicas.

## Cobertura en la base heredada
El origen aplicó estas reglas por fases de su roadmap (histórico); el estado heredado es:

| Área | Estado heredado |
|---|---|
| Cuentas (reglas 1–9 base, 11, 12) | Implementado en la base heredada |
| Pagos (Redsys) | `same_site` revisado en la vuelta del TPV; idempotencia de pagos; **no** se guardan tarjetas |
| Panel admin | Permisos por rol implementados. **2FA de administrador NO implementada** (comentario en `app/Filament/Pages/Settings.php`: "queda para sus sub-fases") — sigue siendo deuda |
| Producción | Regla 10 + HSTS + CSP estricta → pendientes del entorno propio de JumpWeb |

> Relacionado: `MODELO-DATOS.md` (usuarios/consents), `FLUJOS.md` (registro/login),
> `ARQUITECTURA.md`, `OPERATIVA-SECTOR-ORIGEN.md`, `TESTING.md`.
