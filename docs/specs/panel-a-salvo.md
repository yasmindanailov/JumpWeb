# [SPEC] El panel, a salvo — su propia puerta, una dirección secreta y el authenticator de los administradores

> Estado: ✅ técnica del agente (`#630`) dentro de lo que el owner aprobó en `#847` · Última actualización: 2026-09-29 ·
> Decisiones: `#847` (el owner: dirección secreta, authenticator solo para administradores) · `#850` (el guard propio) ·
> Carril: plataforma.

## §0 · Antes de tocar

- **La regla**: el panel solo confía en SU inicio de sesión (guard `admin`): una sesión de la web —la contraseña de hoy,
  el código al correo o Google mañana— no abre el panel. Su dirección sale de configuración (`PANEL_PATH`, `admin` por
  defecto; en producción, secreta) y todo lo del personal cuelga de ella. Los administradores entran con su contraseña y
  el código de su app de autenticación.
- **Empieza por** §1 (el guard compartido, medido) → §4 (P1 → P3).
- **Trampas**: (1) Filament autentica con su guard y hace `shouldUse`: DENTRO del panel `auth()->user()` es el del guard
  `admin`; las rutas del personal en `routes/web.php` y las peticiones Livewire de la puerta lo necesitan dicho
  (`auth:admin`). (2) `EnsureSiteAvailable` (`SEC-02`) excluye el panel POR RUTA y deja al personal ver la web: los dos,
  a la dirección y al guard. (3) Las pruebas: `TestCase::be()` sin guard autentica los dos (§4.1). (4) La dirección
  secreta no sale en ninguna página pública, ni en el repo: vive en el `.env` de producción.
- **Estado**: **P1 ✅** (§4.1, `SEC-14`) · **P2 ✅** (§4.2) · **P3 ✅** (§4.3, `#851`), 29-09. Al desplegar: `ENTORNOS.md` §6.
- **Invariantes**: `SEC-01`, `SEC-02` (a la dirección configurada), `SEC-04`; nace la del guard propio (§5).

## 1. Contexto — medido el 29-09

- **Un solo guard**, `web` (`config/auth.php`), y el panel lo usa (`AdminPanelProvider` no declara `authGuard`). Quien
  entra por la web (`POST /api/v1/auth/login`, Google) queda dentro del panel si su cuenta tiene rol: `canAccessPanel()`
  mira el ROL, no por dónde entró. Hoy apenas se nota (la contraseña es la misma), salvo Google: una cuenta del personal
  con Google entra al panel sin su contraseña. Con el código al correo (`#848`) y el authenticator (`#847`), toda
  entrada por la web se saltaría los dos.
- **Trece rutas del personal fuera de Filament**, bajo `/admin/…` en `routes/web.php` con `['web', 'auth', 'panel_role']`
  (idioma, la puerta, la hoja de la reserva, la ficha de Google ×6, el PDF del descargo, el calendario ×2, el CSV). A
  mano, dos vistas: `url('/admin')` (volver al panel desde la puerta) y `url('/admin/configuracion/maintenance')` (el
  aviso de mantenimiento). `EnsureSiteAvailable` excluye `admin` y `admin/*`, y su paso libre del personal lee
  `$request->user()` (el guard por defecto).
- **Pruebas**: 55 ficheros llaman a `/admin…` a pelo; con `admin` por defecto no cambian.
- **Filament 5 trae el authenticator** (`Auth/MultiFactor/App`): el modelo implementa `HasAppAuthentication` (y
  `…Recovery`) y guarda su secreto y sus códigos de recuperación; `pragmarx/google2fa` ya viene con Filament. Sin
  dependencia nueva. `robots.txt` no nombra el panel.

## 2. Objetivo

- **Éxito**: una sesión de la web no abre el panel, y la del panel no abre la web (pruebas); con `PANEL_PATH` secreta,
  `/admin` y `/admin/login` dan 404 y el panel entero —también sus trece rutas— vive bajo la suya (prueba); un
  administrador no entra sin el código de su app (prueba); mostrador y puerta, como hoy; la puerta en una tablet, viva
  (sonda); nada del panel en una página pública.
- **Fuera**: filtrar por IP (`#847`); el authenticator del resto del personal.

## 3. Opciones

- **A (elegida): guard propio `admin`**, el estándar de Laravel para dos públicos con dos formas de entrar.
- **B: una marca en la sesión** que pone solo el login del panel. Descartada: Filament saca del login a quien ya está
  autenticado, y un panel que exige la marca a un usuario autenticado sin ella da un bucle.
- **C: negar el código y Google a las cuentas con rol**. Descartada: el personal que también es cliente se queda sin la
  web, y no cubre la contraseña de la web de hoy.

## 4. Diseño

### 4.1 P1 · El guard propio
Guard `admin` (sesión, proveedor `users`) en `config/auth.php`; `->authGuard('admin')` en el panel; `auth:admin` en las
trece rutas; el paso libre de `EnsureSiteAvailable` mira el guard `admin`. Las peticiones Livewire de la puerta, con su
middleware persistente. `TestCase::be()` sin guard autentica `web` y `admin` (un miembro del personal en una prueba es lo
que era); las pruebas de la frontera nombran su guard.

✅ **Hecho (29-09)**. Medido al cambiar solo `be()`: la suite entera, 3 fallos de 6535, los tres pruebas que entraban al
panel NOMBRANDO el guard `web` (`AuthTokenTest`, `BookSurfacesParityTest`, `RefundIntentGovernsTest`): pasan a `admin`, su
sujeto es el panel. El aviso de mantenimiento del layout también leía `auth()`: al guard `admin`. **Guardas**:
`PanelOwnGuardTest` (5: la entrada REAL de la web no abre el panel ni sus rutas; el login de Filament abre el panel y no la
web; el mantenimiento; las trece rutas con `auth:admin`, cifra tecleada; ningún `web` a mano en el código del panel) y
`mutar-panel-guard.sh` **5/5**. ⚠️ Cazado al escribirla: con `actingAs(…, 'admin')` el guard por defecto queda en `admin`, y
una página pública en producción va con `web`: la prueba lo devuelve a `web` antes de pedirla, o el mutante del aviso
sobrevivía. **En navegador**, `scripts/sonda-panel.mjs` (su administrador temporal, borrado al terminar) **9/9 a 1280 y a
390**: el login del panel, su sesión sin la web (401), Livewire en Pedidos (200), **la puerta buscando** (200, pinta
`registered_with_waiver`: Livewire reaplica `auth:admin`), el PDF del resumen del día (200) y la web que no abre el panel.

### 4.2 P2 · La dirección
`config/panel.php` (futuro): `path` = `PANEL_PATH`, `admin` por defecto. El panel y las trece rutas cuelgan de ella; las dos
vistas, por nombre de ruta; `EnsureSiteAvailable`, por la configuración. Guarda del despliegue: en producción,
`PANEL_PATH` existe y no es `admin`. La dirección la elige el owner y se le da en el chat, nunca en el repo.

✅ **Hecho (29-09)**. `config/panel.php` y `App\Http\PanelPath` (la dirección en UN sitio: `path()`, `matches()`, `url()`);
Filament, las trece rutas (`$panel` en `routes/web.php`), los TRES middleware que excluyen el panel por ruta (el
mantenimiento, `RecordEmailClick` y `ResolveVisitor`: medido al buscar, no dos) y los dos enlaces. `phpunit.xml` fija
`PANEL_PATH=admin` (la suite no depende del `.env` de la máquina); `.env.example` y `.env.production.example` la nombran.
**Guarda 10** de `deploy.sh`, en producción: 8-64 [a-z0-9-] y no `admin` (controles en bash: vacío, `admin`, mayúsculas,
corta, con barra y con guion inicial paran; `gestion-7f3k9q2x` pasa); no imprime la dirección. **Pruebas**:
`PanelSecretPathTest` (4: arranca la aplicación con una secreta; `/admin` y `/admin/login` 404; las trece rutas debajo; lo
que excluye o enlaza por ruta; la analítica, que deja el panel en paz) y `mutar-panel-direccion.sh` **9/9**. ⚠️ **Cazado
por el arnés, dos veces**: la exclusión del mantenimiento sobrevivía. Medido por qué: Filament no va por el grupo `web`;
sin sesión, Laravel sube `auth:admin` por prioridad y redirige ANTES del mantenimiento; `admin` y `staff` pasan por su
guard. **Solo decide para el rol `puerta`** (con la sesión del panel y sin paso libre): la prueba lo fija ahí. **En
navegador**, `sonda-panel.mjs` con `PANEL_PATH` temporal en el `.env` local (devuelto byte a byte): **10/10**, y `/admin`,
su login y la puerta de siempre, 404. **Al desplegar** (`ENTORNOS.md` §6): la dirección en el `.env`, los favoritos de las
tablets de la puerta y la URI de retorno de la ficha de Google.

### 4.3 P3 · El authenticator de los administradores
`->multiFactorAuthentication([AppAuthentication::make()->recoverable()], isRequired: …)`, obligatorio solo para el rol
`admin`: el primer inicio de sesión le pide configurarlo (QR para la app y códigos de recuperación). Migración con el
secreto y los códigos, cifrados (`encrypted`), ocultos (`Hidden`) y fuera de la exportación RGPD.

✅ **Hecho (29-09, `#851`)**. ⚠️ **Medido al leer Filament**: su «obligatorio» se evalúa al REGISTRAR las rutas (sin usuario):
vale para todo el panel o para nadie. Se enciende para el panel y `RequiresAdminAppAuthentication` ocupa el sitio de su
middleware (`multiFactorAuthenticationRequiredMiddlewareName`) y lo exige solo a `admin`; va también en las trece rutas
del personal (alias `panel_mfa`). `AppAuthentication::make()->recoverable()->codeWindow(2)` (±1 min; la de Filament, ±4).
`User` implementa `HasAppAuthentication(+Recovery)`; migración con las dos columnas; `anonymize()` las borra (el censo de
`RGPD-01`, `AnonymizeCoversEveryUserColumnTest`, lo exigió: SCRUBBED). `panel.admin_mfa` = `true`, **sin `.env`**: medido,
con el requisito encendido **132** pruebas del panel caían (entran como administrador sin app); `TestCase` lo apaga y
`PanelAppAuthenticationTest` lo enciende. Sin página de perfil: `panel:quitar-authenticator <email>` (auditado,
`panel.app_authentication_removed`). **Pruebas**: `PanelAppAuthenticationTest` (5: sin authenticator, a configurarlo —también
desde un PDF—; mostrador y puerta sin él; el login real pide el código y solo entra con el de la app; cifrado, oculto y
borrado al anonimizar; el comando) y `mutar-panel-authenticator.sh` **9/9**. **En navegador**, `sonda-panel.mjs` con un
administrador de secreto conocido (la sonda calcula el TOTP) y otro sin él: **11/11 a 1280 y 390** —la contraseña sola no
entra; con el código, sí; sin authenticator, a `/admin/multi-factor-authentication/set-up`—, y tres pasadas seguidas tras
limpiar el limitador del login de Filament (sin eso, la segunda dio 9/11). ⚠️ **En local**, el administrador del owner
también lo configurará en su próximo inicio de sesión.

## 5. Impacto en invariantes

Nace uno de SEGURIDAD: **el panel solo confía en su guard** (una sesión de la web no lo abre), con su prueba. `SEC-02`
pasa a la dirección configurada. `RGPD-06`: las sesiones de los dos guards viven en la misma tabla `sessions`, así que
`revokeAllAccess()` las alcanza igual (se prueba).

## 6. Plan de verificación

P1: la sesión web no abre el panel ni sus rutas, y al revés; la suite entera (el cambio es transversal). P2: una
aplicación con `PANEL_PATH` propia (404 en `/admin`, el panel en la suya). P3: el administrador sin código no entra; el
de mostrador, sí. Arnés de mutación de las tres. Sonda: el login del panel, la puerta en tablet (buscar y validar), un
PDF del personal y el paso libre del mantenimiento.

## 7. Revisión y decisión

`[DECIDIDO]` 2026-09-29 (`#850`), técnica del agente (`#630`) dentro de `#847`: el guard propio es la condición para que
el authenticator y el código al correo signifiquen algo.
