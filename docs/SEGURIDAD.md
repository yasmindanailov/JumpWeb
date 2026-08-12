# Estándar de seguridad — JumpWeb

> Adaptado del proyecto origen (2026-08-12). Describe la BASE HEREDADA: el refactor
> (`00-REFACTOR.md`) puede haberla cambiado. Verifica contra el código antes de construir
> encima (CONVENCIONES §Verificación).

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
- **Turnstile** data-driven (`app/Support/Turnstile.php`; ver regla 5).
- `User::anonymize()` y logging sensible con hash (`logSensitive`; ver regla 7).

## Reglas (qué debe cumplir el código)

### 1. Contraseñas — NIST 800-63B
- Mínimo **8** caracteres; permitir contraseñas **largas / frases**; sin reglas de composición absurdas.
- **Rechazar contraseñas filtradas** (`Password::...->uncompromised()`; consulta segura a
  "Have I Been Pwned"). En uso en `Livewire/Auth/Register`, `ResetPassword` y
  `Livewire/Account/UpdatePassword`.
- Hash **bcrypt 12** (o `argon2id` si el hosting lo soporta).

### 2. Fuerza bruta y enumeración — OWASP A07 / ASVS V2.2
- **Límite de intentos** en login, registro, reset y reenvío de verificación (p. ej. 5/min por email+IP).
- **Bloqueo temporal** de la cuenta tras N fallos seguidos *(Reforzado)*.
- **Mensajes genéricos**: nunca revelar si un email existe ("credenciales incorrectas"; "si la
  cuenta existe, te hemos enviado un correo").

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
  `app/Support/Turnstile.php` + `Livewire/Auth/Register` + `ContactController`.

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
  (`frame-src`) y el feed social vía `SocialEmbed::cspFrameSrc()`.
- **`form-action` limitado al origen Redsys del entorno configurado** (setting
  `redsys_environment`): en producción el navegador rechaza envíos al TPV sandbox y viceversa —
  hardening de defensa en profundidad (origen #113): un entorno mal configurado falla de forma
  visible antes de enviar el form.
- En `local` añade los orígenes del dev-server de Vite (5173, HTTP y WS) para HMR.
- El middleware **no pisa** una CSP que una respuesta concreta ya haya fijado.
- **HSTS**: NO lo emite el middleware; en el origen se activó en la capa CDN/proxy al ir a
  producción. En JumpWeb queda **pendiente para su propio despliegue** (junto con la CSP estricta).

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
