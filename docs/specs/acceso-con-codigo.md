# [SPEC] Entrar con un código al correo — la contraseña del cliente se retira

> Estado: ✅ aprobada (29-09: el owner contestó el §7) → **A1 ✅** (§4.8) · **A2 ✅** (§4.9) · **A3 ✅** (§4.10) · **A4a ✅**
> (SPA, §4.11) · **A4b ✅** (vista por el owner) · sigue la A5 (plataforma) · Última actualización: 2026-10-01 ·
> Decisiones: `#847` (el owner: código al correo y Google; fuera la contraseña) · `#848` (el §7: una sola puerta, borrar
> las contraseñas, 90 días, solo el código) · `#849` (corrige el 1: el registro NO espera al código, hay cola en la puerta) ·
> `#853`/`#854` (la A1: el código en el servidor; el dispositivo recordado y `RGPD-06`) · `#855` (la A2a: reconfirmar con
> un código; cada sesión atada al token) · `#856` (la A2b: el correo nuevo con su código, y a su buzón) · `#857` (la A3,
> con las piezas de la isla: el owner) · `#810` (la A4a: recuperar sigue hasta la A4b, solo con sesión; `register` lleva a
> la puerta) · `#811` (el `CodeInput` del diseño en el cajón) · `#812` (el código del cajón, como la isla; el servidor dice
> la espera de verdad: el owner) · `#813` (la A4b: Mi cuenta del cajón confirma con el `CodeInput`) ·
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
  de la isla con el código; visto por el owner) · **A3b ✅** (los Ajustes de Mi cuenta con el código). La A4 (SPA, §4.11),
  vista por el owner: **A4a ✅** (entrar y el alta en el cajón, `#810`→`#812`) · **A4b ✅** (Mi cuenta confirma con un
  código, `#813`). Sigue la A5 (plataforma): retirar la contraseña de los clientes.
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
Y el ENLACE del correo nuevo (`VerifyPendingEmail`): al quitar su botón, `emails.verify_pending_email.action` sale de
`MailTextCatalog::CORREOS` (los textos editables del SPA, R1·T), o `MailPreviewsTest` dirá que ese bloque ya no se pinta.
Y de la isla, `PLEGABLE_DE_ZONA.password` (`isla/cuenta/vista.js`), que se queda sin zona cuando la A4 quite la del motor.

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

### 4.11 La A4, el cajón — al detalle (medido el 30-09 noche, antes de codificar; del SPA contra `#848`, `#849`, `#857` y `#858`; vetable al ojo)
**Lo que hoy pide contraseña en el cajón** (medido, `resources/js/sidebar/**`):
- **La compra** (paso 5, `IdentifyStep`): dos pestañas (`AuthTabset`) —«Inicia sesión» (correo, contraseña, «recuérdame»,
  «¿olvidaste…?») y «Crea tu cuenta» (nombre, correo, teléfono, cumpleaños, contraseña, descargo, anti-bot)—.
- **Mi cuenta sin sesión**: las zonas `LOGIN`, `REGISTER` (con sus pestañas, `AuthTabs`) y `FORGOT`, y sus puertas
  `/login`, `/registro` y `/recuperar-contrasena` (`App\Http\Sidebar\AccountDoor`).
- **Mi cuenta con sesión**: `PASSWORD` (cambiarla) y, con `current_password`, `SESSIONS` (cerrar las otras y desvincular
  Google, un solo campo para las dos), `PRIVACY` (borrar la cuenta) y `PROFILE` (el correo nuevo); `NoPasswordHint` en cuatro.
- **La isla usa del motor, y eso NO cambia de forma** (medido con `grep`): `INVALID_CREDENTIALS` de `login.js`,
  `submitRegister()` y `enterWith()` del flujo de compra, `registerStandalone`, `resendVerification` y
  `allowVerificationResend` de `auth`, `ensureIdentities` y `run` de `credentials`, `run`, `cancelPending`, `resendPending` y
  `profileBody` de `profile`, `runForm`, `fieldError`, `landOnAccount` y `google.js`. `runRegister` sigue mandando el
  teléfono que traiga el formulario (lo pone la isla). **Servidor y contrato, sin cambios**: todo existe (1.55.0–1.58.0).

**El diseño**, en dos tandas que se verifican solas, como la A3:
- **A4a · entrar y darse de alta** (la compra y Mi cuenta sin sesión). **UNA puerta, sin pestañas**: Google arriba con su
  «o» (como hoy), el correo y «Continuar» (`POST /auth/code`). Con `next: code`, en el mismo sitio: «Te hemos enviado un código
  a …», el código (`one-time-code`, numérico), «Mantener la sesión iniciada en este dispositivo» SIN marcar (`remember`,
  `#858`), «Entrar», «Pedir otro código» con su espera y «Cambiar el correo»; `POST /auth/login {email, code, remember}` y
  la compra sigue por `enterWith()` (como hoy tras entrar), Mi cuenta aterriza (`landOnAccount`). El tope del CORREO (`429`
  con `next: code`) va al código sin error; el de la IP, arriba con su espera. Con `next: register`, el alta SIN contraseña ni
  teléfono: el correo a la vista con «Cambiar», el nombre, «Tu cumpleaños» (opcional), el descargo (si hay texto), la
  privacidad, el anti-bot y el señuelo (`purchase` en la compra, `standalone` en Mi cuenta: las dos abren sesión desde
  `#331`). El teléfono lo pide el paso de pagar cuando el pedido lo exige (`buyer-due.js`, `#787`). Fuera «¿olvidaste…?»,
  la zona `FORGOT` y `forgot.js`; `/recuperar-contrasena` y `/registro` abren la puerta (la A5 retira la ruta de recuperar).
- **A4b · Mi cuenta con sesión**: las cuatro acciones con un código **por acción** (el servidor emite un `confirm` sin atarlo
  a la acción y el correo dice para qué es: se pide para la que se va a hacer). Una pieza, como `CampoCodigoConfirmar` de la
  isla: el primer toque «Enviarme el código» (`POST /me/confirm-code {action}`), sale el campo con el foco y «Pedir otro
  código», el segundo hace la acción con `code`. En `SESSIONS`, cada acción con la suya; en `PRIVACY`, el código y después la
  pregunta de siempre (`ConfirmInline`); en `PROFILE`, el correo nuevo en tres tiempos, como la isla (el código al de ahora →
  pendiente → el del NUEVO y «Confirmar el correo», `POST /me/pending-email/confirm`). Fuera `PASSWORD` («Cambiar la
  contraseña», como la isla en la A3b), `NoPasswordHint` y `PasswordInput`; la API la retira la A5.
- **Dónde vive**: la puerta, entrar y sus «no», sin estado, en el motor (`login.js` pasa a ser la del código y conserva
  `INVALID_CREDENTIALS`), con su `node --test`; la etapa, el correo y la espera del reenvío, en `stores/auth.js`. Los textos,
  los que el owner ya vio en la isla, en las claves del cajón (`lang/*/account.php`). La isla sigue con su `acceso.js`.

**Guardas**: `node --test` de cada módulo (la puerta, el código, sus «no», el `429` con `next`, «Pedir otro» con su espera,
`remember` solo marcado); las pruebas PHP de las zonas re-apuntadas; los árboles congelados de la identificación, el alta y
su banner REGENERADOS a propósito (`MANIFEST_REFRESH=1`, justificado en el commit: ya no hay segundo motor que los contradiga);
`SidebarBundleBudgetTest` y `SidebarMountTest` (lo que sale contra lo que entra, medido); un arnés de mutación por tanda; la
sonda en el navegador (en la local sirve el cajón: sin fila `sidebar.shell`) leyendo los códigos de Mailpit, a 390 y 1280.
**Por buzón a plataforma**: con la A4 en `main`, la A5 puede retirar la contraseña de los clientes; su `PLEGABLE_DE_ZONA`
(`isla/cuenta/vista.js`) guarda una entrada `password` que se queda sin zona.

**La A4a ✅** (30-09 noche → 01-10; el owner la vio en vivo: «buen trabajo, visto bueno»):
- **La puerta** (`steps/EntryForm.vue`, en el paso 5 y en Mi cuenta): el correo y «Continuar»; con cuenta, la cara del
  código en el mismo sitio: «Te hemos enviado un código de 6 cifras a …» (`role="status"`) con «Cambiar el correo» al lado,
  el **`CodeInput` del diseño** (`#861`, `steps/CodeInput.vue` + `code-input.js`: seis casillas con su guion sobre un solo
  campo `one-time-code` a 16 px, el foco en él, se comprueba solo con la sexta, se vacía tras un «no»), «Pedir otro código»
  dentro y siempre a mano, un solo «no» y sin decir cuánto dura el código —**como la isla, `#812`, el owner**: si se pide
  antes del minuto, «Espera N segundos para pedir otro código.» del servidor bajo el código, sin darlo por enviado
  (`login.js::runResend`)—, «Mantener la sesión…» sin marcar y «Entrar», apagado hasta tener las seis. El componente baja
  con su cara (`defineAsyncComponent`). Nuevo, el alta (`RegisterForm`) sin contraseña ni teléfono, con el correo a la
  vista y «Cambiar el correo». «¿Querías decir…?» al salir del campo, con `import()`: 1,45 KiB que el motor no baja al
  abrir. La lógica, en `login.js` (`runDoor`, `runResend`, `runCodeLogin`, `doorErrors`); la cara, en `stores/auth.js`.
- ✱ **El servidor decía «espera una hora»** (`#812`, medido en la sonda): con solo el límite del minuto agotado, el
  `max()` contaba también la ventana de la hora (1 de 5) y respondía `retry_after: 3599`, en la puerta, el código de
  confirmar y el reenvío del correo nuevo. `EmailCodeLogin::secondsToWait` cuenta solo los límites agotados; los límites
  (1/min, 5/h) no cambian, y la isla lo hereda sin tocarla.
- **Fuera**: las pestañas (`AuthTabset`, `AuthTabs`), `LoginForm`, la zona `REGISTER` y sus textos (`register.cta`,
  `login.password`, `login.forgot`), y del montaje `auth.failed` y `auth.password` (nadie los pintaba ya).
- ✱ **Dos cambios sobre lo de arriba** (`#810`): (1) **`FORGOT` sigue hasta la A4b, solo con sesión**: la abre el aviso de
  las cuatro acciones que aún piden contraseña (`NoPasswordHint`, de la A4b); sus textos viajan con lo personal y
  `/recuperar-contrasena` abre la puerta. (2) **El nombre `register` lleva a la puerta** (`navigation.js::zoneFor`): los
  cinco CTA de alta de la landing lo piden y es API del paquete; sin el alias, un invitado caía en el índice en blanco.
- **Pesos**, medidos tras `#812`: el motor, 300,60 → 301,79 KiB (techo 302; el `CodeInput`, 2,18 KiB, y la sugerencia,
  1,45, bajan aparte); el montaje sin sesión, 2.742 → 2.457 B (techo 2.800 → 2.550); con sesión, 10.791 → 10.926 B (techo
  10.900 → 11.000; la poda que falta es la de la A4b, `forgot` y la contraseña).
- **Guardas**: los `node --test` de `login.js`, `stores/auth.js`, `register.js`, `code-input.js`, `navigation.js` y
  `stores/account.js`; los árboles congelados de la puerta, **su cara del código** (nuevo, con el `CodeInput`), el alta y su
  banner (con los cuatro «no» que ESTE formulario puede dar), regenerados a propósito; el montaje y el arranque por la API
  (`SidebarMountTest`, `SidebarBootTest`); la espera de verdad (`AuthCodeTest`, `MeConfirmationCodeTest`,
  `MePendingEmailCodeTest`); el cursor, declarado en `MotionBudgetTest`; arnés `scripts/mutar-cajon-a4a.sh` **44/44**;
  `scripts/sonda-cajon-a4a.mjs` (28 puntos a 390 y a 1280: el cableado, pedir otro enseguida —la espera del minuto, sin
  «otro»—, la comprobación sola con la sexta —también repitiendo el mismo código, con su control—, la cookie de recordar
  solo con la casilla, el alta sin esperar). Las seis sondas que entraban con contraseña entran con el código por una pieza
  común, `scripts/entrar-con-codigo.mjs`.
- ⚠️ **Trampas medidas**: (1) la local sirve la landing de la INSTANCIA con `cajon.css`, no con `site.css`: un cambio en
  `site.css` no se ve hasta regenerar la hoja (`python3 scripts/hoja-del-cajon.py --aplicar`), y el control de la sonda
  sobre `site.css` no mordía por eso. (2) La casilla del descargo del alta llega con `GET /legal/waiver`, después de
  pintarse: mirarla al rellenar es una carrera (la perdió una corrida de tres a 390); la sonda lo sabe por el servidor.
  (3) La cuenta de pruebas tiene menores: tras entrar en la compra vuelve al carrito a asignarlos (`#202`), no a «Pagar».

**La A4b al detalle** — `[DECIDIDO]` 2026-10-01 (`#813`; medido antes de codificar; vetable al ojo, como la A4a):
- **Lo que hay** (medido): `PasswordZone` (cambiarla); `SessionsZone`, UN campo de contraseña para cerrar las otras y para
  desvincular; `PrivacyZone`, la contraseña antes de la pregunta; `ProfileZone`, la contraseña si cambia el correo y el
  pendiente con «Reenviar enlace»; `NoPasswordHint` en las cuatro lleva a `ForgotZone` (`forgot.js` y su parte de
  `stores/auth.js`). Nadie de fuera abre `password` ni `forgot` (ni el producto ni la instancia). La isla llama del motor
  `apply`, `run`, `resendPending`, `cancelPending`, `ensureIdentities` y `profileBody`, que no cambian de forma.
- **La pieza** (`account/ConfirmCode.vue`): sin pedir, «Para confirmarlo, te enviaremos un código a …»; pedido, el
  `CodeInput` de la A4a (diferido, el mismo trozo) con «Te hemos enviado un código de 6 cifras a …» («otro» al repetir), su
  «no» y «Pedir otro código» siempre a mano, con la espera del servidor bajo el código si es pronto (`#812`). La petición,
  pura en `account/confirm-code.js`; el estado (la acción, pedido, reenvíos, el código, su «no»), en `stores/confirm.js`:
  uno para el área, que se vacía al CAMBIAR de zona (`stores/account.js::sync`); pedir el de otra acción anula el anterior,
  como el servidor.
- **El botón**: el principal de su formulario dice «Enviarme el código» hasta pedirlo y después la acción, apagado hasta
  las seis cifras; la sexta la hace sola, como en la puerta (el `CodeInput`, `#861`). **SESSIONS**: «Cerrar las otras
  sesiones» (✱ el de la isla: «Cerrar sesión en los demás dispositivos» no cabía en el botón a 390, medido en la sonda) con
  el suyo; «Desvincular» de la fila conserva su nombre (es la fila), pide el suyo y lo escribe bajo la lista. **PRIVACY**: el código y después la pregunta de siempre (`ConfirmInline`): la sexta abre la pregunta, no
  borra. **PROFILE**: con el correo cambiado, la pieza bajo el correo (al de AHORA) y «Guardar cambios» lo usa; pendiente,
  el código del NUEVO en su aviso, «Confirmar el correo», «Pedir otro código» (`resendPending`) y «Cancelar cambio»;
  confirmado, se relee el contexto de cuenta.
- **Fuera**: `PASSWORD` (zona, icono, entrada del índice, textos, `changePassword`), `FORGOT` (zona, `forgot.js`, lo suyo
  de `stores/auth.js`, la siembra de `parentZoneFor`, textos), `NoPasswordHint` y `PasswordInput`; del montaje con sesión,
  `no_password`, `password`, `forgot` y los rótulos de contraseña de las tres zonas. `password` y `forgot` pedidos por su
  nombre llevan al índice y a la puerta (`zoneFor`, como `register`). Los stores mandan `code` (la API la retira la A5).
- **Textos**: los de la isla que el owner ya vio (`isla.mi_cuenta.codigo` y `.correo`), en las claves del cajón; la pista,
  el «no», «Pedir otro código» y su espera, los de `login.*`, que ya viajan.
- **Guardas**: `node --test` de `confirm-code.js`, `stores/confirm.js` y los stores re-apuntados; `SidebarMountTest` y
  `SidebarBundleBudgetTest` (lo que sale contra lo que entra); arnés `scripts/mutar-cajon-a4b.sh` y sonda
  `scripts/sonda-cajon-a4b.mjs`, con los códigos de Mailpit a 390 y 1280. ✱ Sin árbol congelado de la pieza: el contrato del
  DOM renderiza los pasos de la COMPRA (`scripts/render-sidebar.mjs`) y ninguna zona de cuenta ha estado nunca bajo él;
  meterlas es ampliar ese arnés, otra tarea. Las casillas ya están congeladas en la cara del código de la puerta.

**La A4b ✅** (01-10; el owner la vio en vivo: «Visto bueno. Buen trabajo»):
- **Lo nuevo**: `account/ConfirmCode.vue` (toma el correo del perfil si no se le da), `account/confirm-code.js`,
  `stores/confirm.js` (`request`, `again`, `act` —pide o usa—, `ask` —pide o pregunta, para borrar—, `showNewEmail`) y, en el
  perfil, `confirmPending` y `refresh`. Las tres zonas, reescritas sobre ellos (ninguna pasa de su techo de 40 líneas).
- **Pesos**: el motor, 301,79 → 298,46 KiB (techo 302 → 299); el montaje con sesión, 10.926 → 9.867 B (techo 11.000 →
  10.000); el anónimo no cambia (2.457 B).
- **Guardas**: 133 casos de `node --test` en lo tocado (1.636 en total, JS); arnés `mutar-cajon-a4b.sh` **36/36** (un
  mutante equivalente —añadía a la lista de privacidad claves que ya estaban— se cambió por el de los rótulos de sesiones);
  el de la A4a, re-anclado, **43/43**; `sonda-cajon-a4b.mjs` **37 puntos a 390 y a 1280**, dos corridas seguidas limpias:
  cerrar las otras con un SEGUNDO dispositivo que queda fuera (`/me` 401), desvincular una identidad sembrada, la pregunta
  de borrar sin borrar, borrar DE VERDAD una cuenta desechable, y el correo cambiado de punta a punta (devuelto por tinker).
  El punto «el texto cabe en el botón», con su control (con el texto largo, falla a 390).
- ⚠️ **Medido por la sonda**: (1) «Pedir otro código» del correo NUEVO sale a la primera —el servidor cuenta los reenvíos, no
  el código que salió con el cambio— y anula el anterior; el segundo, enseguida, espera. (2) El lector del buzón acepta lo
  llegado hasta 2 s antes de pedirlo: con dos códigos seguidos al mismo buzón hay que esperar a uno DISTINTO. (3) La zona
  pide las vinculadas una vez (`ensureIdentities`): una identidad sembrada a mitad no se ve sin recargar.

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
