# [SPEC] Entrar con un código al correo — la contraseña del cliente se retira

> Estado: ⬜ borrador (para el owner, ANTES del código) · Última actualización: 2026-09-29 ·
> Decisiones: `#847` (el owner: código al correo y Google; fuera la contraseña) · la del diseño, al aprobarse ·
> Carril: plataforma (el servidor, el contrato y la isla); el cajón, del SPA por buzón.

## §0 · Antes de tocar

- **La regla**: el cliente demuestra que el correo es suyo con un código de un solo uso cada vez que entra, y con
  Google si lo prefiere. Entrar y darse de alta son EL MISMO paso (correo → código → si es nuevo, sus datos), y la
  respuesta al pedir un código es idéntica exista o no la cuenta. El personal del panel NO cambia (su contraseña, y el
  authenticator de los administradores, `#847`).
- **Empieza por** §1 (lo que hoy pide contraseña: sus rutas, 21 pantallas y 35 ficheros de pruebas) → §4 (el diseño) →
  §7 (lo que espera al owner).
- **Trampas**: (1) la cola sale por el cron CADA MINUTO en producción (`ENTORNOS.md` §6): un código encolado puede tardar
  60 s; se envía en la misma petición, tras la respuesta (§4.3). (2) La reconfirmación de las acciones sensibles
  (`SEGURIDAD.md` §3: borrar la cuenta, cambiar el correo, desvincular Google) hoy es la contraseña: pasa a un código
  (§4.4). (3) Las cuentas de Google llevan hoy una contraseña aleatoria (0 nulas de 53 en local). (4) Los limitadores
  son de DOMINIO (`SEC-06`): ningún controlador los reimplementa.
- **Estado**: ⬜ borrador; sin código. Tandas en §4.7.
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
- **Hoy el alta dice si un correo ya tiene cuenta** (`#31a`, decisión de conversión): con el código, entrar y darse de
  alta se funden y esa enumeración desaparece sin coste.

## 2. Objetivo

- Ningún cliente escribe una contraseña: entra con correo + código (o Google), en la isla, en el cajón y en la app.
- Las acciones sensibles se confirman con un código al correo de la cuenta.
- **Éxito**: el código llega en segundos (medido en staging con `sendmail` real); un código no se adivina (límites de
  §4.2 con su prueba y su mutación); la misma respuesta para un correo con y sin cuenta (prueba); las superficies de
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

## 4. Diseño elegido

### 4.1 El código
- 6 cifras, válido **10 minutos**, de un solo uso, **5 intentos** y muere. Uno vivo por (correo, propósito): pedir otro
  anula el anterior. Se guarda su HMAC (clave de la app + correo), nunca el código.
- Dos propósitos: `entrar` (entrar o darse de alta) y `confirmar` (una acción sensible, ya con sesión).
- Tabla `login_codes` (futuro): correo, propósito, huella, caduca, intentos, usado, IP, creado.

### 4.2 Los límites (`SEC-06`, de dominio)
- Pedir: 1 por minuto y 5 por hora por correo; por IP, un techo para el rociado. Verificar: los 5 intentos del código
  y un limitador por IP. Probabilidad de adivinar: 5/10⁶ por código y, con 5 códigos por hora, 2,5·10⁻⁵ por hora.
- La respuesta a «pedir» es la MISMA con cuenta y sin ella, y el correo sale en los dos casos (a un correo sin cuenta,
  «tu código para crear tu cuenta»). Rechazos y bloqueos, auditados sin PII (`SEGURIDAD.md` §8).

### 4.3 El envío
- En la misma petición, tras la respuesta (`afterResponse`), no por la cola: la cola espera al cron. ⚠️ Con LiteSpeed,
  si la respuesta no se suelta antes, el envío la retrasa lo que tarde `sendmail` (medir en staging).
- El correo, con el molde de `BrandedMailMessage`: el código grande, para cuánto vale y «si no lo has pedido tú,
  ignóralo». Sin enlace (§3).

### 4.4 Los flujos
- **Entrar o darse de alta** (isla, cajón): correo → «Te hemos enviado un código» → el código. Con cuenta, dentro. Sin
  ella, se crea al verificar, con lo que el alta pide hoy (nombre, aceptación de privacidad y términos; el descargo, donde
  toca) en «Tus datos». Un código verificado **verifica el correo** (`email_verified_at`): sobra el enlace de verificación
  para las cuentas nuevas.
- **La app** (`#630`): `POST /auth/tokens` con correo + código en vez de contraseña; la rotación, igual.
- **Acciones sensibles**: `current_password` → un código `confirmar` al correo de la cuenta (borrar la cuenta, cambiar el
  correo, desvincular Google, cerrar las demás sesiones).
- **Cambio de correo**: el nuevo se verifica con un código a ESE correo.
- **Google**: sin cambios; su alta sigue completándose donde hoy.
- **Sesión**: se regenera al entrar; «recuérdame» por defecto en el dispositivo (cada entrada cuesta un correo) — §7.

### 4.5 Lo que se retira (tanda de retirada, §3.quater)
`POST /auth/login` con contraseña, `/auth/password/{forgot,reset}`, `PUT /me/password`, las páginas web de recuperar y
restablecer, «Cambiar la contraseña» de Mi cuenta y del cajón, `PasswordPolicy` para clientes (el panel la conserva), y
las pruebas que solo probaban eso. El contrato cambia de forma: su versión, al implementarlo (mirar `info.version`).

### 4.6 Las contraseñas que ya existen
Las de los clientes dejan de servir en cualquier superficie de cliente. Propuesta (§7): **borrarlas** en las cuentas sin
rol de panel (minimización, RGPD art. 5.1.c: un hash que no se usa solo sirve a quien robe la base). El personal conserva
la suya.

### 4.7 Las tandas
**A1** el código en el servidor (tabla, servicio de dominio, límites, correo, `request`/`verify`, sesión y token) ·
**A2** las acciones sensibles con `confirmar` · **A3** la isla (Entra, «Tus datos», Mi cuenta) · **A4** el cajón (SPA,
por buzón) · **A5** la retirada y las contraseñas de §4.6 · **A6** staging: la latencia real del correo, antes de la v2.0.0.

## 5. Impacto en invariantes

`SEC-06` se amplía: los dos limitadores cubren también pedir y verificar el código. `RGPD-01`: la purga borra los códigos
del titular. `RGPD-06`: sin cambio de forma (entrar con código crea la misma sesión o el mismo token). `SEGURIDAD.md` §1
(contraseñas) pasa a ser solo del panel y §3 (reconfirmar) se reescribe con el código. El cambio de invariantes es del
owner (`CONVENCIONES` §9): va con la aprobación de esta spec.

## 6. Plan de verificación

Pruebas de dominio del código (caduca, un solo uso, 5 intentos, uno vivo, la misma respuesta con y sin cuenta, el HMAC)
con su arnés de mutación; pruebas de API por endpoint; la suite entera tras la retirada; sonda de la isla (entrar,
darse de alta en la compra, borrar la cuenta con código) con el correo leído de Mailpit; y en staging, la latencia del
correo real. El ojo del owner en las pantallas nuevas.

## 7. Revisión y decisión — lo que espera al owner

1. **Correo nuevo = alta en el mismo paso** (§4.4): ¿vale que quien escribe un correo sin cuenta la cree al poner el
   código, sin pantalla de «Crea tu cuenta» aparte?
2. **Las contraseñas de hoy** (§4.6): ¿se borran las de los clientes (recomendado) o se dejan sin uso?
3. **La sesión**: ¿el dispositivo queda recordado (recomendado: hasta cerrar sesión, 90 días sin uso) o se pide código
   cada vez que caduca la sesión de 2 horas?
4. **¿Un enlace además del código** en el correo (§3, B)? Recomendado: no, solo el código.
