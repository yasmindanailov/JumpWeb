# [SPEC] Entrar con un código al correo — la contraseña del cliente se retira

> Estado: ✅ aprobada (29-09: el owner contestó el §7) → **A1 ✅** (§4.8) · **A2 ✅** (§4.9) · **A3 ✅** (§4.10), sigue la A4
> (SPA) · Última actualización: 2026-09-30 ·
> Decisiones: `#847` (el owner: código al correo y Google; fuera la contraseña) · `#848` (el §7: una sola puerta, borrar
> las contraseñas, 90 días, solo el código) · `#849` (corrige el 1: el registro NO espera al código, hay cola en la puerta) ·
> `#853`/`#854` (la A1: el código en el servidor; el dispositivo recordado y `RGPD-06`) · `#855` (la A2a: reconfirmar con
> un código; cada sesión atada al token) · `#856` (la A2b: el correo nuevo con su código, y a su buzón) · `#857` (la A3,
> con las piezas de la isla: el owner) ·
> Carril: plataforma (el servidor, el contrato y la isla); el cajón, del SPA por buzón.

## §0 · Antes de tocar

- **La regla**: el cliente ENTRA con un código de un solo uso al correo, o con Google. UNA puerta, el correo: con cuenta,
  el código; nuevo, sus datos y dentro, SIN código —el registro no espera a ningún correo: en la puerta hay cola (`#849`)—,
  y el correo se confirma después, sin frenar. El personal del panel NO cambia (su contraseña, y el authenticator de los
  administradores, `#847`).
- **Empieza por** §1 (lo que hoy pide contraseña: sus rutas, 21 pantallas y 35 ficheros de pruebas) → §4 (el diseño) →
  §7 (lo que decidió el owner).
- **Trampas**: (1) la cola sale por el cron CADA MINUTO en producción (`ENTORNOS.md` §6): un código encolado puede tardar
  60 s; se envía en la misma petición, tras la respuesta (§4.3). (2) La reconfirmación de las acciones sensibles
  (`SEGURIDAD.md` §3: borrar la cuenta, cambiar el correo, desvincular Google) hoy es la contraseña: pasa a un código
  (§4.4). (3) Las cuentas de Google llevan hoy una contraseña aleatoria (0 nulas de 53 en local). (4) Los limitadores
  son de DOMINIO (`SEC-06`): ningún controlador los reimplementa. (5) La puerta dice si un correo tiene cuenta, como el
  alta de hoy (`#31`): la acotan los límites de §4.2.
- **Estado**: ✅ aprobada (`#848`). **A1 ✅** (29-09, `#853`/`#854`, §4.8) · **A2 ✅** (30-09, `#855`/`#856`, §4.9: reconfirmar
  con un código, cada sesión atada al token y el correo nuevo con su código) · **A3a ✅** (`#857`, §4.10: «Entra» y el alta
  de la isla con el código; visto por el owner) · **A3b ✅** (los Ajustes de Mi cuenta con el código). Sigue la A4 (SPA).
- **Invariantes**: `SEC-06` (se amplía al código), `RGPD-01` (la purga borra los códigos), `RGPD-06` (sin cambio de
  forma). Ningún fichero del `CRITICAL_RE`.

## 1. Contexto y problema — medido el 29-09

- **El owner** (`#847`): «Quitar contraseña, se entra con código único al correo», con Google como segunda vía. El brief
  del 23-09 ya pedía «la cuenta nace sola, sin contraseña»; el producto la pedía igualmente.
- **Lo que hoy pide contraseña** (`routes/api.php` y `routes/web.php`): `POST /auth/login`, `POST /auth/tokens` (la app,
  `#630`), `POST /auth/register`, `POST /auth/password/{forgot,reset}`, `PUT /me/password`, y reconfirman con
  `current_password` `DELETE /me` (`AccountPrivacy::anonymize`), el cambio de correo (`MeProfileController`),
  `DELETE /me/identities/{provider}` y «cerrar las demás sesiones»; en la web, `/recuperar-contrasena` y
  `/restablecer-contrasena/{token}`. En el contrato (`openapi/v1.yaml`, 1.54.0), las siete de `/auth/*` y
  `current_password` en cuatro de `/me`.
- **Pantallas**: 21 `.vue` entre la isla y el cajón nombran la contraseña (entre ellas `PantallaEntrar`, `PantallaDatos`,
  `CuentaAjuste`, `CampoClaveActual`, y del cajón `LoginForm`, `RegisterForm`, `ForgotZone`, `PasswordZone`).
- **Pruebas**: 35 ficheros de `tests/` usan `password`.
- **Cuentas** (local): 53; 3 con Google; 31 con rol de panel; ninguna sin contraseña. En producción, sin medir (ssh).
- **Correo**: `sendmail` local en producción (dos pruebas a Gmail sin spam, `ENTORNOS.md` §6); la cola, `database`,
  la vacía el cron del panel cada minuto con `queue:work --stop-when-empty --max-time=50`.
- **Hoy el alta dice si un correo ya tiene cuenta** (`#31`, decisión de conversión). ✱ Corregido por `#849`: la puerta lo
  sigue diciendo (el correo decide si va al código o al registro); lo acotan los límites. Antes decía: «con el código,
  entrar y darse de alta se funden y esa enumeración desaparece sin coste».
- **En la puerta del parque hay cola** (el owner, 29-09): quien llega sin cuenta se registra en su móvil para enseñar su
  descargo. Hoy la verificación del correo no frena el alta, y no debe empezar a frenarla.

## 2. Objetivo

- Ningún cliente escribe una contraseña: entra con correo + código (o Google), en la isla, en el cajón y en la app.
- Las acciones sensibles se confirman con un código al correo de la cuenta.
- **Éxito**: el código llega en segundos (medido en staging con `sendmail` real); un código no se adivina (límites de
  §4.2 con su prueba y su mutación); el registro no espera a ningún correo (prueba y sonda); las superficies de
  contraseña del cliente, retiradas con sus pruebas clasificadas por su sujeto (`CONVENCIONES` §3.quater).
- **Fuera**: el panel (su contraseña y el authenticator de los administradores van aparte, `#847`); el teléfono como
  vía de entrada; las claves de acceso (passkeys), que serían una tanda futura.

## 3. Opciones

- **A (la elegida): código de 6 cifras en el correo**, que se escribe en la pantalla que lo pidió. Funciona aunque el
  correo se lea en otro dispositivo (el móvil y el portátil), que es el caso de la compra.
- **B: enlace mágico** (un toque en el correo abre la sesión). Descartado como vía única: abre la sesión en el navegador
  del CORREO, no en el de la compra, y los filtros de correo que visitan enlaces lo gastan. Queda como opción de §7.
- **C: mantener la contraseña como alternativa**. Descartada por el owner (`#847`): dos caminos que mantener y la
  contraseña olvidada sigue siendo la primera causa de «no puedo entrar».
- **D: el código también para darse de alta** (antes o al final del registro), para confirmar el correo antes de crear
  la cuenta. Descartada (`#849`): con cola en la puerta, esperar un correo frena el alta, que hoy no espera. La errata
  en el correo se ataja sin frenar (§4.4).

## 4. Diseño elegido

### 4.1 El código
- 6 cifras, válido **10 minutos**, de un solo uso, **5 intentos** y muere. Uno vivo por (correo, propósito): pedir otro
  anula el anterior. Se guarda su HMAC (clave de la app + correo), nunca el código.
- Dos propósitos: `entrar` (una cuenta que ya existe) y `confirmar` (una acción sensible, ya con sesión).
- Tabla `login_codes` (futuro): correo, propósito, huella, caduca, intentos, usado, IP, creado.

### 4.2 Los límites (`SEC-06`, de dominio)
- Pedir: 1 por minuto y 5 por hora por correo; por IP, un techo para el rociado. Verificar: los 5 intentos del código
  y un limitador por IP. Probabilidad de adivinar: 5/10⁶ por código y, con 5 códigos por hora, 2,5·10⁻⁵ por hora.
- ✱ Corregido por `#849`: la puerta dice si el correo tiene cuenta (va al código) o no (va al registro), como el alta de
  hoy (`#31`); lo acotan el límite por IP y el de por correo. Antes decía: «la respuesta es la MISMA con cuenta y sin ella».
  Rechazos y bloqueos, auditados sin PII (`SEGURIDAD.md` §8).

### 4.3 El envío
- En la misma petición, tras la respuesta (`afterResponse`), no por la cola: la cola espera al cron. ⚠️ Con LiteSpeed,
  si la respuesta no se suelta antes, el envío la retrasa lo que tarde `sendmail` (medir en staging).
- El correo, con el molde de `BrandedMailMessage`: el código grande, para cuánto vale y «si no lo has pedido tú,
  ignóralo». Sin enlace (§3).

### 4.4 Los flujos
- ✱ **Corregido por `#849`** (el registro no espera al código). **UNA puerta**, «Entra o crea tu cuenta» (isla, cajón): el
  correo. **Con cuenta** → «Te hemos enviado un código» → el código → dentro. **Nuevo** → la pantalla del registro, sin
  código: **el nombre, aceptar el descargo (con lo legal que ya se pide) y «Tu cumpleaños», opcional** («y poco más», el
  owner); el teléfono, solo donde hoy (un pack, `#787`) → dentro, con su QR y su descargo al momento. Antes decía: «correo
  → código → con cuenta, dentro; sin ella, el registro» (el código delante del alta).
- **El correo del registro se confirma DESPUÉS, sin frenar**: el mensaje de verificación de hoy. Contra la errata, tres
  redes que no esperan: la sugerencia de `ui/correo.js` («¿Quisiste decir…?»), el correo a la vista al terminar («Te hemos
  escrito a …, ¿está bien?») y, en Mi cuenta, «Confirma tu correo» con la opción de corregirlo mientras dure la sesión
  (90 días). Un código de entrar verificado también lo confirma (`email_verified_at`). El riesgo de registrar el correo de
  otro queda como hoy.
- **Si en la puerta no llega el código** a quien vuelve: el personal lo busca por su nombre, como hoy.
- ⚠️⚠️ **Medido el 29-09, y es de ANTES**: `User::purgeSessions()` solo actúa con `session.driver = database`, y
  `ENTORNOS.md` §6 da producción con `SESSION_DRIVER=redis` (medido el 01-09; hoy, sin mirar). Si sigue así, «cerrar las
  demás sesiones», el cambio de contraseña y el borrado de la cuenta NO cierran las sesiones de otros dispositivos allí
  (`RGPD-06`). Con sesiones de 90 días pesa más: la A2 lo resuelve (la sesión en base de datos, como staging `#137`, o una
  revocación que no dependa del driver), medido en producción antes de elegir. ✱ **Resuelto en la A2a** (`#855`, §4.9):
  cada sesión va atada al token de la cuenta, y la revocación ya no depende del driver.
- ⚠️ **El panel no se abre con estas entradas** desde `#850` (`SEC-14`, `specs/panel-a-salvo.md`): el código al correo y
  Google abren la WEB; el panel tiene su propio guard. Sin eso, este acceso habría sido un atajo al panel.
- **La app** (`#630`): `POST /auth/tokens` con correo + código en vez de contraseña; la rotación, igual.
- **Acciones sensibles**: `current_password` → un código `confirmar` al correo de la cuenta (borrar la cuenta, cambiar el
  correo, desvincular Google, cerrar las demás sesiones).
- **Cambio de correo**: el nuevo se verifica con un código a ESE correo.
- **Google**: sin cambios; su alta sigue completándose donde hoy.
- **Sesión**: se regenera al entrar; el dispositivo queda **recordado 90 días sin uso** o hasta cerrar sesión (`#848`: cada
  entrada cuesta un correo). ✱ **Corregido por `#858`** (`[DECIDIDO owner]` 30-09): SOLO si la persona marca «Mantener la
  sesión iniciada en este dispositivo» al escribir el código (sin marcar de serie); el alta, la sesión de siempre. Una cookie
  de autenticación persistente no está exenta de consentimiento (GT29, dictamen 4/2012, §3.2; la guía de la AEPD exime solo
  las «de sesión»), y la casilla es la forma de que la pida quien la quiere (una premarcada no vale: Planet49). Solo la
  pregunta, sin texto debajo (el owner, 30-09): los 90 días y la cookie los explica `/cookies`, que nombra la casilla.

### 4.5 Lo que se retira (tanda de retirada, §3.quater)
`POST /auth/login` con contraseña, `/auth/password/{forgot,reset}`, `PUT /me/password`, las páginas web de recuperar y
restablecer, «Cambiar la contraseña» de Mi cuenta y del cajón, `PasswordPolicy` para clientes (el panel la conserva), y
las pruebas que solo probaban eso. El contrato cambia de forma: su versión, al implementarlo (mirar `info.version`).

### 4.6 Las contraseñas que ya existen
Las de los clientes dejan de servir en cualquier superficie de cliente y **se borran** en las cuentas sin rol de panel
(`#848`; minimización, RGPD art. 5.1.c: un hash que no se usa solo sirve a quien robe la base). El personal conserva la
suya. Es un borrado de datos en producción: con su receta de `ENTORNOS.md` §5 (valor esperado por fila, doble pasada).

### 4.7 Las tandas
**A1** el código en el servidor (tabla, servicio de dominio, límites, correo, `request`/`verify`, sesión y token) ·
**A2** las acciones sensibles con `confirmar` · **A3** la isla (Entra, «Tus datos», Mi cuenta) · **A4** el cajón (SPA,
por buzón) · **A5** la retirada y las contraseñas de §4.6 · **A6** staging: la latencia real del correo, antes de la v2.0.0.

### 4.8 La A1, hecha — `[DECIDIDO]` 2026-09-29 (`#853`, `#854`)
- **La API** (contrato **1.55.0**): `POST /auth/code` `{email}` → `200 {next: code|register}`; con `code` el código ya va
  de camino. `429` con `retry_after` y, si el tope fue el del CORREO, `params.next = code` (la pantalla puede seguir a
  escribirlo). El código se escribe en `POST /auth/login` o `POST /auth/tokens`: **`password` o `code`**, nunca los dos
  (422). `POST /auth/register` ya no exige `password`. Lo que la A3 y la A4 necesitan para pintar, esto.
- **Las piezas**: `Identity\Models\LoginCode` (tabla `login_codes`, poda diaria a las 24 h) · `Services\LoginCodes` (emitir
  y gastar: HMAC con la clave de la app sobre propósito, correo y código; intento y uso CONDICIONADOS) ·
  `Services\EmailCodeLogin` (la puerta y verificar) · `Services\LoginGate` (el núcleo `guarded()` de `PasswordLogin`,
  movido tal cual: los dos cubos de `SEC-06` los comparten la contraseña y el código) · `Notifications\LoginCode` (el
  código en el asunto y como titular, «482 913»). Límites: IP 10/min (todas las peticiones de la puerta); correo 1/min y
  5/h (solo cuando se envía).
- **El envío**: `sendNow()` dentro de `defer()`, tras la respuesta. La notificación sigue `ShouldQueue` (`PAY-14`): quien
  la notificara por el camino normal la encolaría. En local, el correo está en Mailpit a los ~300 ms de la petición; en
  LiteSpeed, sin medir (A6).
- **La copia**: el registro de correos salientes (`#794`) guardaba el HTML y el asunto TAL CUAL, código incluido;
  `HidesSecretsInCopy` lo tapa («••• •••»). Medido en MySQL tras la sonda.
- **El dispositivo recordado** (`#854`): la cookie «recuérdame» del guard `web`, 90 días (`Max-Age=7776000`, medido), la
  alarga `RefreshRememberedDevice` en cada página; salir cierra solo este dispositivo. Google y el enlace de verificación
  no recuerdan: su entrada no cuesta un correo. `User::getAuthPassword()` da `''` sin contraseña (con `NULL`, PHP 8.5
  avisaba de obsoleto en cada entrada recordada).
- **Medido con control**: la carrera de diez `POST /auth/tokens` con el mismo código contra MySQL da un solo 201… y su
  control (sin las condiciones) también: cuatro procesos no abren el hueco. La prueba que discrimina es determinista
  (`LoginCodesTest`, el segundo uso dentro del hueco con `DB::listen`), con su mutación.
- ⚠️ **Hereda la A2**: `AccountCredentials` reconfirma con la contraseña y usa `logoutOtherDevices($password)`, que sin
  contraseña no puede; y las sesiones de producción en Redis (§4.4). ⚠️ **Del owner**: `/cookies` dice que la cookie de
  persistencia solo se pone «si marcas recuérdame» —ya no es así— y la AEPD exime las de autenticación «de sesión»:
  el texto y el aviso en la pantalla (A3), `[PENDIENTE: owner]`.

### 4.9 La A2, diseño — `[DECIDIDO]` 2026-09-30 (`#855`)
Partida en dos tandas que se verifican solas. **A2a · reconfirmar con un código y revocar sin depender del driver**:
- `POST /me/confirm-code` `{action}` (`delete_account`, `change_email`, `unlink_google`, `close_sessions`) → `202`: un código
  `confirm` al correo de la cuenta, con el mismo mecanismo (`LoginCodes`, tras la respuesta). El correo dice PARA QUÉ es:
  quien no lo pidió sabe que alguien tiene su sesión. Límites: 1/min y 5/h por cuenta.
- `DELETE /me`, `PATCH /me` (si cambia el correo), `DELETE /me/identities/{provider}` y `POST /me/sessions/revoke-others`
  aceptan `code` o `current_password` (la contraseña se va en la A5), con el mismo limitador de siempre (titular, IP). El
  422 va sobre el campo que se mandó. Un código de ENTRAR no confirma nada (el propósito va en la huella).
- **La revocación que no depende del driver** (§4.4): cada SESIÓN de la web queda atada al `remember_token` de la cuenta
  (su huella, puesta al entrar por un oyente del `Login`); un middleware en la web y en la API con sesión cierra la que ya
  no casa. `revokeOtherAccess()` rota el token y re-ata la sesión en curso; `revokeAllAccess()` lo vacía. Así «cerrar las
  demás» echa también a las sesiones vivas con Redis, no solo a las cookies de recuerdo; la medida de producción ya no
  decide nada (queda como comprobación al desplegar). El token ya no está nunca vacío con alguien dentro: se crea al entrar.
  ⚠️ Salir del PANEL (`logout()` del guard `admin`) rota el token y cierra también la web de esa persona: se acepta.
- `POST /logout` (la web) cierra solo este dispositivo, como `auth/logout` (el hueco de la A1: rotaba el token).
- ✅ **A2a HECHA** (30-09, contrato **1.56.0**): `Reconfirmation` (contraseña o código) en `AccountCredentials::verify`,
  `AccountProfile`, `AccountPrivacy` y `SocialIdentities`; `CodeMail` (el envío tras la respuesta, extraído en la segunda
  copia); `ConfirmationCode`; `SessionBinding` + `BindSessionOnLogin` + `EnsureSessionIsCurrent`. Pruebas
  `MeConfirmationCodeTest` y `SessionBindingTest` —con el driver `array` de la suite, donde `purgeSessions()` no hace nada:
  la medida que vale—; arnés `mutar-acceso-codigo.sh` 40/40. En local (driver `database`): dos dispositivos, el código por
  Mailpit, el portátil a 401 y el móvil dentro; esa sonda NO discrimina el driver (allí la purga también lo echaría).
  ⚠️ **Dos trampas medidas**: (1) Laravel reordena por `$middlewarePriority` y sube `Authenticate` por delante de lo
  añadido a un grupo —la sesión cerrada llegaba al controlador con un titular nulo (500)—: `EnsureSessionIsCurrent` va en
  la lista de prioridad, delante de la autenticación; (2) el guard `sanctum` guarda en caché al titular (lo resuelve antes
  el `AuthenticateSession` de Sanctum): cerrar el `web` no basta, hace falta `Auth::forgetGuards()`.
**A2b · el correo nuevo, con un código a ESE correo** (propósito `new_email`, para no mezclarlo con el de confirmar): el
cambio se pide como hoy (`pending_email`) y se completa con `POST /me/pending-email/confirm` `{code}` (✱ junto a sus hermanas
`DELETE /me/pending-email` y `/resend`; antes decía `/me/email/confirm`); el enlace firmado de hoy sigue valiendo hasta que
la isla y el cajón pinten el código (A3/A4) y se retira en la A5.
- ✅ **A2b HECHA** (30-09, `#856`, contrato **1.57.0**): pedir el cambio y reenviarlo emiten el código al buzón nuevo, tras
  la respuesta (`CodeMail`), en el mismo correo que el enlace; `AccountProfile::confirmPendingEmail()` (limitador propio por
  titular e IP) y `completeEmailChange()`, bajado TAL CUAL del controlador del enlace, que ahora lo usa. Reenvíos: 5/h.
  ⚠️⚠️ **Medido: los dos correos del cambio salían al buzón equivocado desde siempre** —el enlace de «confirma tu nuevo email»
  al VIEJO, el aviso de «ha cambiado» al NUEVO—: declaraban su destinatario en la notificación y Laravel solo lee el del
  titular. `User::routeNotificationForMail()` respeta ahora `ChoosesRecipient`, y el correo viejo viaja dentro del aviso
  (va por la cola y el titular vuelve de ella con el nuevo). Pruebas `MePendingEmailCodeTest` y `EmailChangeRecipientsTest`
  (el envío de verdad y su dirección); arnés 48/48. En local contra Mailpit, de punta a punta: cada correo a su buzón.

### 4.10 La A3, la isla — `[DECIDIDO owner]` 2026-09-30 (`#857`)
El mockup no dibuja el código: se construye con las piezas de la isla, se enseña en vivo y el owner lo rediseña si quiere.
Partida en dos: **A3a**, entrar y darse de alta; **A3b**, los Ajustes de Mi cuenta (reconfirmar con código, el correo
nuevo con el suyo). Lo de la A3a:
- **«Entra»** (`PantallaEntrar`, compra y Mi cuenta): el correo → la puerta → con cuenta, el código (`#pjc-ent-codigo`,
  `one-time-code`, «Pedir otro código» con «otro» en la pista); nuevo, a «Tus datos» o a «Crea tu cuenta» con el correo
  puesto y una nota NEUTRA. El tope del correo (`429` con `next: code`) va al código sin error: hay uno recién enviado.
  La flecha del código vuelve al correo. Fuera el olvido de la contraseña. `[DECIDIDO owner]` 30-09: Google y Apple van
  ARRIBA y «— o —» los separa de los campos, aquí y en el alta (`AccesoSocial` con `separador`: solo si queda algún botón),
  para que nadie tome el de Google por el que envía el correo.
- **El alta** («Tus datos», «Crea tu cuenta»): sin contraseña y con la PUERTA delante: un correo con cuenta recibe su
  código y lo escribe allí mismo («ya existe») o en «Entra» (Mi cuenta), sin intentar el alta —que avisaría al titular
  de «alguien intentó registrarse»—.
- `[DECIDIDO owner]` 30-09 · **«Tus datos» solo cuando falta algo de la cuenta**: tras entrar o darse de alta en ella, si
  ya no falta nada, sale del camino (`sinDatos`): «Pagar» queda como la de quien llega con sesión («Reservas como Ana», sin
  «Paso 2 de 2») y su flecha vuelve a la RESERVA, no a un «Hola» vacío. `sonda-isla.mjs` lo mira, con su mutación.
- **Dónde viaja**: la puerta, entrar y sus «no», en `compra/acceso.js`, con los pasos (la compra lo pide con `import()` de
  su trozo ya descargado): dentro, la compra medía 166,46 de 166. Pesos, con la base construida aparte: compra 165,44 →
  165,84, pasos 44,45 → 45,37 (techo 46), Mi cuenta 117,22 → 118,87 (techo 120). Las sondas leen el código de Mailpit.
- ⚠️⚠️ **Medido en el navegador, de la A1**: con la cookie de recuerdo, «Cerrar sesión» NO salía —`/me` 200 después—. El
  `Auth::forgetGuards()` de salir deja resolver otro guard, y el `$request->user()` de `NoStoreWhenAuthenticated` a la
  vuelta volvía a entrar con la cookie que aún traía la PETICIÓN. `RememberedDevice::logOutHere()` (las dos salidas) la
  quita también de la petición; prueba en `RememberedDeviceTest` y su mutación. La prueba de la A1 miraba solo la cookie.

**La A3b, los Ajustes de Mi cuenta** (30-09): cerrar las otras sesiones, desvincular Google, cambiar el correo y borrar la
cuenta se confirman con un CÓDIGO (`CampoCodigoConfirmar`, fuera `CampoClaveActual`). El primer toque de la acción dice
«Enviarme el código» y lo pide (`POST /me/confirm-code`); sale el campo, con el foco y «Pedir otro código»; el segundo hace
la acción con él —pedirlo al ENTRAR en el paso mandaría un correo a quien solo mira—. El correo, en tres tiempos en la misma
pantalla: el nuevo y el código de confirmar (al de ahora); ya pendiente, el código del NUEVO y «Confirmar el correo»
(`/me/pending-email/confirm`). El cambio pendiente dura 60 min y cada código 10: la pantalla habla del cambio, no del código.
- **Sin tocar los stores del motor** (del SPA): el cuerpo con `code` viaja por sus guardianes públicos (`run`, `runForm`),
  que limpian, llaman y colocan el veredicto; su `current_password` queda para el cajón hasta su A4.
- **«Cambiar la contraseña» sale de Mi cuenta ya** (antes, en la A5): en la isla nadie entra con ella (`#848`), y era un
  callejón. La A5 retira su API. Con ella, sus textos de `lang/*/isla.php`.
- Pesos: Ajustes 28,50 → 29,03 (techo 30). `sonda-cuenta.mjs` confirma con los códigos de Mailpit —el correo, cambiado de
  verdad y devuelto por tinker; cerrar las otras sesiones; el código de borrar, sin borrar—: 255 a 390 y 1280.

## 5. Impacto en invariantes

`SEC-06` se amplía: los dos limitadores cubren también pedir y verificar el código, y el límite de la puerta acota que
diga si un correo tiene cuenta (`#849`, como el alta de `#31`). `RGPD-01`: la purga borra los códigos
del titular. `RGPD-06`: sin cambio de forma (entrar con código crea la misma sesión o el mismo token). `SEGURIDAD.md` §1
(contraseñas) pasa a ser solo del panel y §3 (reconfirmar) se reescribe con el código. El cambio de invariantes es del
owner (`CONVENCIONES` §9): va con la aprobación de esta spec.

## 6. Plan de verificación

Pruebas de dominio del código (caduca, un solo uso, 5 intentos, uno vivo, el HMAC) con su arnés de mutación; pruebas de
API por endpoint (y que el registro no pide código); la suite entera tras la retirada; sonda de la isla (entrar con el
código leído de Mailpit, darse de alta en la compra SIN esperar ningún correo, borrar la cuenta con código); y en
staging, la latencia del correo real. El ojo del owner en las pantallas nuevas.

## 7. Revisión y decisión

✅ **`[DECIDIDO owner]` 2026-09-29, más tarde (`#849`) — CORRIGE el 1 de abajo**: «hay cola y los clientes se tienen que
registrar para entrar y mostrar su descargo; en el registro actual la verificación no frena el registro, pero ahora sí».
El agente había defendido el código al final del registro (la errata deja sin cuenta a quien no tiene contraseña; el
descargo se prueba con el correo) y cambió su recomendación con el argumento de la cola. Queda: UNA puerta, su idea
original —el correo; con cuenta, el código; nuevo, sus datos y dentro, sin código— y el correo confirmado después (§4.4).

✅ **`[DECIDIDO owner]` 2026-09-29 (`#848`)**, con opciones y la recomendada en las cuatro:
1. ~~**Una sola puerta**, «Entra o crea tu cuenta»: correo → código → dentro, o, si el correo es nuevo, la pantalla del
   registro (nombre, descargo, cumpleaños opcional).~~ Corregido por `#849` (arriba). Explicado con dos ejemplos: el owner
   preguntó «si no hay crear cuenta, ¿cómo se crean?» — la pantalla existe, se llega sola cuando el correo es nuevo.
2. **Las contraseñas de los clientes se borran** (§4.6).
3. **El dispositivo, recordado 90 días sin uso** o hasta cerrar sesión.
4. **Solo el código** en el correo de entrar, sin enlace (la confirmación del alta, `#849`, sigue siendo el mensaje de hoy).
