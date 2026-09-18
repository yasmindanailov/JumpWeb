# [SPEC] Login por token — el emisor de Bearer de la API v1 (F4 del programa)

> Estado: ⬜ **borrador, pendiente de revisión del owner** · Última actualización: 2026-09-18 ·
> Decisión asociada: `#63x` de la banda de plataforma al aprobarse · Carril: **plataforma**.
> Origen: `specs/producto-e-instancias.md` §4.5 (F4) y `specs/api-v1.md` §4.2, que aplazó la EMISIÓN (`#29`).
> Hermana: `specs/cajon-empaquetable.md` (la otra mitad de F4). Sube el contrato a **1.1.0**.

## §0 · Antes de tocar

- **Regla que ordena todo**: un token es una CREDENCIAL. Nace por la misma puerta que el login (los DOS
  limitadores de `SEC-06`, con las MISMAS claves) y muere por el mismo sitio que todo acceso
  (`User::revokeAllAccess()` / `revokeOtherAccess()`, `RGPD-06`). Nada de esto se copia: se comparte.
- **Casi todo está hecho desde la Fase 3** (medido, §1): Sanctum instalado, `HasApiTokens` en `User`, caducidad
  de 30 días, poda en el scheduler, revocación por las cinco vías con su test de mutación, `bearerAuth` en el
  contrato (30 menciones) y 35 de 59 rutas tras `auth:sanctum`. **Falta el emisor** y lo que lo rodea.
- **El cajón NO usa token**: vive en el mismo dominio que la API y sigue con cookie de sesión + CSRF. El token
  es para el cliente nativo (F6). Sin token en `localStorage`, nunca.
- **Trampa medida**: `PasswordLogin::attempt()` autentica con `Auth::attempt()` sobre el guard de SESIÓN. El
  emisor no puede llamarlo: necesita verificar SIN abrir sesión, por un camino del mismo servicio que comparta
  limitadores (§4.2). Una segunda copia de los limitadores es el defecto que `SEC-06` describe.
- **Un Bearer no abre el panel**: el único guard es `web`; Filament no mira `sanctum`. Se fija con un test (§6).
- **Empieza por** §4.1 (contrato) → §4.2 (servicio) → §4.3 (controlador). El contrato se cambia ANTES que el
  código (`#21`). Ningún fichero casa con el `CRITICAL_RE` (medido); no pide `VERIFY_CONC`.
- **Fuera**: emisión por Google y alta desde la app (F6), listar dispositivos, el retorno del pago para un
  cliente Bearer (F6). Están nombrados en §2 para que nadie los dé por hechos.

## 1. Contexto y problema — MEDIDO (2026-09-18)

| Pieza | Estado | Evidencia |
|---|---|---|
| Paquete | `laravel/sanctum ^4.3` | `composer.json` |
| Modelo | `HasApiTokens` en `User` | `use HasApiTokens, HasFactory, Notifiable` |
| Tabla | `personal_access_tokens` con `abilities`, `last_used_at`, `expires_at` (índice); morph **sin FK** | la migración; `specs/api-v1.md` §10 |
| Caducidad | 43.200 min (30 días), `SANCTUM_TOKEN_EXPIRATION_MINUTES` | `config/sanctum.php` |
| Poda | `sanctum:prune-expired --hours=24` en el scheduler | `routes/console.php` |
| Revocación | `revokeAllAccess()`, `revokeOtherAccess()`, `revokeCurrentAccessToken()`; `logout` ya revoca el Bearer de la petición | `User`, `AuthSessionController::logout` |
| Tests de revocación | las 5 vías, verificadas por mutación | `ApiTokenRevocationTest`, `AccessRevocationTest` |
| Contrato | `bearerAuth` declarado y nombrado 30 veces; versión `1.0.0`; su descripción cita un `POST /auth/tokens` que NO existe | `openapi/v1.yaml` |
| Rutas | 59 en `/api/v1`: 35 tras `auth:sanctum`, 24 públicas (re-medido tras `#578`, que añadió 4 públicas de la invitación sin mover la versión del contrato) | `route:list --path=api/v1 --json` |
| Emisor | **ninguno**: `createToken` aparece 0 veces en `app/` y `routes/`, y en 8 ficheros de `tests/` | `grep -rn createToken` |
| Abilities | **nadie las mira**: 0 usos de `tokenCan`, `ability:` o `abilities:` | `grep` en `app/`, `routes/`, `bootstrap/` |
| Exigen sesión (no valen con Bearer) | `auth/login`, los dos de `auth/google/*` y el alta dentro de una compra | `requireSession` en 3 controladores |
| Reconfirmar contraseña en `me/*` | por `current_password` en el cuerpo: vale igual con Bearer | `MeProfileController`, `MeCredentialsController` |
| Correo sin verificar | el login NO lo mira | `PasswordLogin`, `LoginResult` |

**Premisa corregida**: `specs/producto-e-instancias.md` §1.5 dice que la API «declara un único esquema de
seguridad». Medido, declara DOS (`sessionCookie` y `bearerAuth`); lo que no existe es quien emita el segundo.

**El problema**: la app nativa (F6, `#627`) no puede identificarse. No es un origen *stateful*, así que
`auth/login` la rechaza a propósito, y no hay otra puerta.

## 2. Objetivo

1. Un cliente no-navegador obtiene un Bearer con correo y contraseña, lo usa en las 35 rutas autenticadas y lo
   pierde por las cinco vías de `RGPD-06` — demostrado con peticiones HTTP reales, no con `actingAs`.
2. Atacar la puerta nueva cuesta lo mismo que atacar el login: cubos COMPARTIDOS (un fallo en una cuenta en la otra).
3. El contrato dice 1.1.0 y describe lo que hay; los tests heredan de `Api\ApiTestCase`, que valida la RESPUESTA
   REAL contra `openapi/v1.yaml` con Spectator.
4. Cero cambios para el cajón y para la web: la suite de sesión no se mueve.

**Fuera de alcance, con nombre**: (a) **emisión por Google** — una cuenta solo-Google no tiene contraseña y por
esta puerta no entra; la app necesitará `id_token` verificado en servidor (F6); (b) **alta desde la app** —
`auth/register` exige Turnstile, que es de navegador (F6); (c) **listar y revocar dispositivos uno a uno** — hoy
basta «cerrar las demás» (`me/sessions/revoke-others`); (d) **el desenlace del pago para un cliente Bearer**
(`specs/api-v1.md` §10: «un cliente Bearer no recibe nada»), que es de F6 con su esquema de URL.

## 3. Opciones consideradas

| Tema | Elegida | Descartada y por qué |
|---|---|---|
| Mecanismo | Tokens opacos de Sanctum (ya instalado, revocación ya probada) | JWT: no se revoca sin lista negra, y `RGPD-06` exige revocar. Passport/OAuth: `specs/api-v1.md` §3 ya lo descartó (cliente de primera parte) |
| Verificar credenciales | Un método nuevo en `PasswordLogin` que comparte el núcleo (limitadores + comprobación) con `attempt()` | Llamar a `attempt()`: abre sesión en un guard sin sesión arrancada y encola la cookie `remember`. Copiar los limitadores al controlador: el defecto exacto de `SEC-06` |
| Revocar el propio | `POST auth/logout`, que ya lo hace | Un `DELETE auth/tokens/current` nuevo: dos puertas para lo mismo |
| Abilities | Una, `api-v1`, exigida en el grupo autenticado | `['*']`: el día que exista una superficie privilegiada, los tokens de la app ya emitidos la abrirían. Sin comprobarla: una ability que nadie mira no es una guarda |
| Duración | **`[PENDIENTE: owner]`** — ver §4.4 | — |
| Tope por cuenta | 10 tokens vivos; el 11.º retira el de `last_used_at` más antiguo | Sin tope: quien tiene la contraseña llena la tabla; con tope que RECHAZA: un móvil nuevo no entra hasta que caduque otro |

## 4. Diseño elegido

### 4.1 Contrato (`openapi/v1.yaml`, 1.0.0 → 1.1.0)
- **`POST /auth/tokens`** · público · cuerpo `{email, password, device_name}` (`device_name`: 1–60 caracteres) ·
  **201** `{token, token_type: "Bearer", expires_at, user}` con `user` = el mismo `UserResource` de `GET me` ·
  **401** `invalid_credentials` genérico · **422** validación · **429** con `retry_after` y `Retry-After`. Mismos
  códigos y sobre que `auth/login`. La respuesta lleva `Cache-Control: no-store` explícito: es pública para el
  middleware (`NoStoreWhenAuthenticated` no la ve) y transporta una credencial.
- `bearerAuth`: se corrige la descripción (hoy cita «paso 3»). `auth/logout` documenta que con Bearer revoca ESE token.
- Si el owner elige rotación (§4.4): **`POST /auth/tokens/rotate`** · `bearerAuth` · 201 con la misma forma.

### 4.2 Dominio (`Identity`)
- `PasswordLogin` gana **`verify(email, password, ip): LoginResult`**: mismos limitadores, mismas claves
  (`email|ip` y `login-ip|ip`), mismo log sin PII, `Auth::validate()` en vez de `Auth::attempt()`, sella
  `last_login_at`. `attempt()` y `verify()` comparten un núcleo privado: el segundo limitador no se puede olvidar.
- `Identity\Services\ApiTokenIssuer` (futuro): emite con `createToken(device_name, ['api-v1'], expiresAt)`,
  aplica el tope de 10 dentro de una transacción y deja `Log::info('auth.token_issued', user_id, ip)`.
  La escritura en `personal_access_tokens` pasa por `User` o se declara por nombre en `AccessRevocationTest`.

### 4.3 Superficie
- `Api\V1\AuthTokenController` (futuro): valida, llama a `verify()` y al emisor, responde. Sin lógica propia
  (`ApiBoundariesTest`). **No usa `requireSession`**: esta puerta es justo para quien no tiene sesión.
- Grupo autenticado de `routes/api.php`: gana `abilities:api-v1`. Con cookie, Sanctum usa un `TransientToken`
  que responde `true` a todo: la SPA no nota nada (se prueba).

### 4.4 Duración — `[PENDIENTE: owner]`, tres opciones
- **A · 30 días y fuera** (lo que hay configurado): la app pide contraseña una vez al mes aunque se use a diario.
- **B · 30 días con rotación** (recomendada): la app cambia su token por uno nuevo al arrancar si tiene más de
  7 días; quien la usa no vuelve a teclear la contraseña y un token olvidado muere a los 30 días. Un token
  robado que rota expulsa al dueño, que al volver a entrar puede «cerrar las demás».
- **C · 180 días sin rotación**: lo más cómodo y lo que más tiempo deja vivo un token perdido.

## 5. Impacto en invariantes

- `RGPD-06`: sin cambio de regla; **caso nuevo**: tokens EMITIDOS por la puerta real caen por las cinco vías.
- `SEC-06`: se amplía — la emisión de tokens comparte los dos limitadores del login, con cubos comunes.
- `RGPD-04`: la respuesta del emisor es `no-store`. `RGPD-02`: el log no lleva correo ni nombre del dispositivo.
- `SEC-01`: la ruta nueva está en el grupo `api`, hereda `SecurityHeaders`. `AFORO-*`, `PAY-*`: ninguno.

## 6. Plan de verificación empírica

- `Api\V1\AuthTokenTest` (futuro): emisión feliz; 401 indistinguible (correo inexistente ≡ contraseña mala, byte a
  byte); **cubos compartidos** (5 fallos en `auth/login` bloquean `auth/tokens` y al revés; 30 por IP igual);
  `no-store`; el token abre `GET me` y una ruta de escritura; caducado → 401; tope de 10; `abilities`.
- `ApiTokenRevocationTest`: las cinco vías repetidas con un token salido de `POST /auth/tokens`.
- Un Bearer válido contra `/admin` → redirección al login del panel, nunca 200.
- La SPA no se mueve: `AuthSessionTest` y la suite del cajón en verde sin tocar; `abilities:api-v1` con cookie → 200.
- Mutación (`scripts/mutar-token-bearer.sh` (futuro)): quitar el limitador de IP en `verify()`, emitir con `['*']`,
  quitar `abilities:` del grupo, quitar el tope, quitar `no-store`, saltarse `expires_at`. Todas deben morir.
- Empírico: `curl` contra `localhost:8081` — emitir, usar, `logout`, reusar → 401.

## 7. Revisión y decisión

Pendiente. El owner decide §4.4 y revisa §0 y §4. Al aprobarse: `/decision`, estado ✅ y casilla en el tracker.

## Anexo · fila del enrutador

`F4 · cajón empaquetable · login por token` → `docs/specs/cajon-empaquetable.md` §0 · `docs/specs/token-bearer.md` §0.
