# [SPEC] Entrar y registrarse con Google

> Estado: 🟦 **LAS TRES TANDAS DE CÓDIGO ESTÁN EN EL ÁRBOL** (2026-09-02, `DECISIONES #342`, `#343`
> y `#344`) **y la T4 —el OJO del owner— YA SE HA HECHO: lo que sacó está en §21** · Abierta:
> 2026-09-02 · Autor: agente
> ▶ **Lo ejecutado, con lo que enseñó, está en §18 (T1), §19 (T2), §20 (T3) y §21 (el pulido que
> salió del ojo del owner) — y va ANTES que las tandas de §16.** Queda, como requisito de salida de
> la Q7, **la política de privacidad**.
> Las decisiones `[DECIDIDO owner]` se tomaron en la conversación de diseño del 2026-09-02 y se citan
> con la frase del owner cuando la hay.
>
> ⚠️ **Esta spec ha pasado una revisión adversarial de cinco lentes (2026-09-02) y la revisión
> encontró OCHO bloqueantes, incluidos varios en la propia sección de «hechos medidos».** Todo lo
> corregido lleva la marca ✱ y dice qué decía antes. **Léelo: la versión anterior afirmaba cosas
> falsas con seguridad.**
>
> ▶ **Convención de esta spec**: `[MEDIDO]` = abierto y comprobado contra el código · `[LEÍDO]` = lo
> dice un documento y **no** lo he verificado · `[RAZONADO]` = deducción, no observación.

---

## 1 · El problema, y por qué no es «poner un botón»

**El encargo del owner**: *«Quiero 0 fricción para el cliente a la hora de registrarse. Que el
cliente pueda registrarse con Google, o vincular una cuenta registrada con Google y poder ingresar
tanto con Google como con su cuenta.»*

El alta de hoy pide **cuatro campos** (nombre, correo, teléfono, contraseña) y **cuatro casillas**
—privacidad, condiciones, exención y marketing— ✱ *(antes decía «tres»: el marketing también está)*,
pasa por Turnstile y después **manda salir de la web a abrir el buzón**.

Ese último paso no es teórico: el primer día de operación real **se atascó el correo saliente** y
hubo clientes sin poder verificarse (`ESTADO.md`, incidentes del 2026-09-01). ✱ **Corrección**: la
causa fue el límite por hora del hosting, que afecta a **todo** el correo, no solo a la verificación.
Lo que Google elimina entero es **el síntoma para quien entra por ahí**, no el modo de fallo.
*(Antes esta spec decía «el 100 % de ese fallo». Era retórica mal calibrada.)*

Google entrega **el correo ya verificado**, el nombre y un identificador estable (`sub`). Desaparecen
**la contraseña y el correo de verificación**. Lo que Google **no** da: teléfono y las aceptaciones.

---

## 2 · Objetivo

1. **Un solo gesto para quien ya tiene cuenta.**
2. **Una sola pantalla para quien no la tiene.**
3. **Vinculación**: quien se registró con contraseña entra con Google y sigue entrando con su contraseña.
4. **Que nadie llegue al parque sin la exención firmada por él mismo.**

⚠️ El punto 4 **manda sobre el 1**. Ver §4.

---

## 3 · Lo medido

⚠️ ✱ **La cabecera anterior decía «todo se comprobó contra el código, no se leyó en la doc», y esa
frase era FALSA**: al menos una celda (§3.8) era un dato leído en `ENTORNOS.md` presentado como
medido. Cada afirmación lleva ahora su marca.

### 3.1 · Qué exige el alta que Google no puede dar `[MEDIDO]`

`Api\V1\AuthRegistrationController::rules()` (58-89) declara **doce** reglas ✱ *(antes esta tabla
listaba ocho y se dejaba fuera justo la que más importa)*:

| Campo | Regla | ¿Lo da Google? |
|---|---|---|
| `name` | `required` | **Sí** |
| `email` | `required, email:rfc` | **Sí, y verificado** |
| `phone` | `required` | No |
| `password` | `PasswordPolicy::rules()` | No |
| `accept_privacy` | `accepted` | No |
| `accept_terms` | `accepted` | No |
| `accept_waiver` | `accepted` **solo si `isInternal()` Y hay versión publicada** (`:113-117`) | No |
| **`waiver_document_id`** | `required_if_accepted:accept_waiver` (`:87`) | **No, y es del SERVIDOR** |
| `marketing` | opcional | No |
| `context` · `website` · `turnstile_token` | del flujo/anti-bot | — |

⚠️ **`waiver_document_id` no es un detalle**: el servidor **solo emite la aceptación con el
identificador que él mismo sirvió** (`:83-87`). La pantalla de §7 tiene que **servirlo y devolverlo**.

⚠️ **El modo por defecto del waiver es `externo`, no `interno`** `[MEDIDO]`:
`WaiverSettings` (50-62) cae a `MODE_EXTERNAL` sin fila válida — el estado de una instalación recién
montada. Consecuencia en §7.

### 3.2 · El esquema `[MEDIDO]` — *confirmado por la revisión*

| | |
|---|---|
| `users.email` | `varchar(255)` **NULL permitido** + índice **UNIQUE** (`#263`) |
| `users.password` | **NOT NULL** |
| `users.phone` | NULL permitido |
| `users.email_verified_at` | NULL permitido, y **NO es `fillable`** — se escribe con `forceFill` (`CustomerRegistrar:97-100`) |

▶ **Ahorra una migración**: la cuenta de Google lleva contraseña **aleatoria inservible**, como ya
hace el alta de mostrador. **`password` no se toca.**

### 3.3 · La exención: la condición es la COLUMNA, no lo que Google diga `[MEDIDO]`

`WaiverSigner` **89-93** ✱ *(antes decía 89-91: el `if` cierra en la 93)*:

```php
$needsVerifiedEmail = $request->declaredBy === null
    && $request->subjectType !== WaiverSignature::SUBJECT_GUEST_MINOR;
if ($needsVerifiedEmail && $locked->email_verified_at === null) {
    throw new WaiverEmailUnverifiedException;
}
```

⚠️⚠️ **Lee `$locked->email_verified_at`, la COLUMNA de la fila bloqueada — no la afirmación de
Google.** De ahí sale el bloqueante B2 (§5.3): si el alta no escribe esa columna, **la transacción se
cae en el caso normal**.

✱ Y el método exige **tres cosas más** que la versión anterior no citaba: cuenta no anonimizada
(`:64`), slug correcto (`:52`) y **la versión re-comprobada BAJO EL LOCK** (`:98-100`,
`WaiverDocumentStaleException`) — ésta muerde en §5.3.

▶ El aviso de la cuenta ya hace lo correcto para el estado resultante `[MEDIDO]`: sin firma y **sin**
aceptación retenida, `account/waiver.js` devuelve «pendiente» **y ofrece firmar**, y ahí el 409
`waiver_email_unverified` no puede dispararse.

### 3.4 · Las acciones que exigen contraseña son CUATRO `[MEDIDO]`

✱ *(antes esta sección medía UNA y sacaba de ella una conclusión demasiado ancha)*

| Superficie | Dónde |
|---|---|
| Cambiar contraseña | `MeCredentialsController:42` |
| **Cerrar las demás sesiones** | `MeCredentialsController:62` |
| **Cambiar el correo** | `MeProfileController:43` (y `AccountProfile:102-104` la verifica) |
| Borrar/anonimizar | `MePrivacyController` → `AccountPrivacy::anonymize()` |

Una cuenta nacida con Google no puede hacer **ninguna de las cuatro** — incluida *cerrar las demás
sesiones*, que es la palanca de «me han entrado».

✱ **Y la conclusión anterior era exagerada.** Decía «no podría ejercer su derecho de supresión». La
revisión midió que el panel anonimiza **sin contraseña** (`ViewUser:395`) y queda el canal escrito.
Lo que se rompe es el **autoservicio**: es un obstáculo al **art. 12.2**, no una negación del art. 17.
▶ La dependencia T2→T3 **se sostiene igual** con ese argumento más honesto.

### 3.5 · El marketing no se puede retirar `[MEDIDO]` — *confirmado por cuatro vías*

Ninguna ruta actualiza `marketing_opt_in`, `AccountProfile` no lo toca, y sus consumidores son
`UserResource`, el export, la ficha del panel y `anonymize()`. Se escribe una vez en el alta.
⇒ Se da con un clic y **no se puede retirar**; el art. 7.3 exige lo contrario. Preexistente. §9.

⚠️ ✱ **Y falta la mitad del problema**: `consents` **no tiene columna de revocación** `[MEDIDO]`
(migración `2026_05_23_000005`: `type`, `accepted_at`, `ip`, `version` y nada más). Un booleano que
se apaga **no deja constancia de cuándo se retiró**, y `GET /me/consents` seguiría enseñando
«marketing, aceptado el …» encima de un interruptor apagado.

### 3.6 · La versión de los consentimientos no la lee nadie `[MEDIDO]` — *confirmado*

`Consent::CURRENT_VERSION` se escribe y **ningún camino la lee** para re-preguntar. El mecanismo que
su docblock promete **no existe**. No es de esta feature: ficha en `DEUDA.md`.

### 3.7 · Las condiciones se aceptan SOLO en el alta `[MEDIDO]` — *confirmado*

### 3.8 · Dónde viven hoy los secretos por instalación

✱ **Esta sección tenía TRES afirmaciones falsas y son las que más pesaban en mi recomendación.**

| Antes decía | La verdad |
|---|---|
| «`REDSYS_SECRET_KEY`, **y solo ése**» en el `.env` | **Falso** `[MEDIDO]`: `config/theme.php` lee `THEME_FONTS` del `.env` (clave `theme.fonts`), y **no es un secreto** — es configuración por instalación que debe sobrevivir a `config:cache` |
| Turnstile se escribe «con `app:set-setting **--force**`» | **Falso** `[MEDIDO]`: `SetSetting::PROTECTED_KEYS` (45-49) son **tres claves y las tres de Redsys**; Turnstile no exige `--force` |
| «el `post-deploy.sh` **ya ejecuta** `app:set-setting` para Turnstile» | **No verificado** `[LEÍDO]`: `find . -name "*post-deploy*"` y `grep -rn "set-setting" scripts/` están **vacíos** — el guion no está en el repo. La única fuente es `ENTORNOS.md:387` |
| Turnstile es «el gemelo exacto» porque «la mitad pública tiene que llegar al navegador» | **Media verdad**: con el flujo servidor-a-servidor de §6.4 el `client_id` **no viaja al navegador**; al cliente le basta un booleano. La analogía se sostiene por el aprovisionamiento, no por esto |

⚠️⚠️ **Y hay un argumento EN CONTRA de `settings` que yo no vi y está escrito en el propio repo**
`[MEDIDO]`: `SetSetting::PROTECTED_KEYS` dice de la clave de Redsys *«vive en el vault/`.env`, no en BD:
escribirla aquí la deja en una tabla que se vuelca en cada backup»*, y `Filament\Pages\Settings:44-45`
declara que los secretos «viven en el vault/`.env`».

⇒ **El precedente de Turnstile no es doctrina: es una desviación tolerada.** La intuición de
`ESTADO.md` —mandar las claves al `.env`— tiene detrás el razonamiento escrito del proyecto.
**Retiro mi recomendación anterior**: pasa a ser la pregunta Q3 con los dos argumentos delante (§15).

### 3.9 · El contrato obliga en las dos direcciones `[MEDIDO]` — *confirmado*

`ApiContractTest::test_every_registered_route_is_declared_in_the_contract` y su inverso.
⇒ **Las rutas de OAuth van en `web`** (§6.4).

### 3.10 · Lo que no existe todavía `[MEDIDO]`

- Ni Socialite ni ningún proveedor externo en `composer.json`.
- ✱ `POST /auth/tokens` **se menciona en la PROSA del contrato** (líneas 26 y 2259) pero **no está
  declarado como operación** — si lo estuviera, la guarda de §3.9 estaría en rojo. *(Antes decía
  «documentado en el contrato», lo que sugería una operación declarada.)* La conclusión no cambia:
  **no hay app nativa**.
- Ninguna columna, tabla ni concepto de proveedor de identidad.

---

## 4 · La decisión que manda: **hay pantalla intermedia**

`[DECIDIDO owner, 2026-09-02]`:

> *«No quiero el caso de que el usuario entre con Google y nadie acepte el descargo de
> responsabilidad y todo sea vía tablet en persona. Me preocupa más la operativa en el parque físico
> que online. […] Ese será el 90 % de los casos. Prefiero hacer la pantalla intermedia y se pide COMO
> MÍNIMO el waiver 100 %.»*

▶ **La lección**: *la fricción no desaparece, se muda al empleado.* Un toque ahorrado online son
treinta segundos de un empleado por persona en hora punta — y cambia una firma **del cliente** por
una **declarada por el operador**.

⚠️ Y `#336` se construyó para **convertir una aceptación que ya existe**. Sin aceptación, el operador
no confirma: **acredita**. Eso convierte la excepción en el caso normal.

⇒ Se descarta el diseño de cero pantallas, y con él mover las condiciones al checkout y pedir el
teléfono en la reserva. **El checkout no se toca** (§13).

---

## 5 · Las tres puertas

### 5.1 · Entrar (vínculo existente) — cero pantallas

Se busca por `(provider, provider_id)`. ✱ **Y se comprueba `isAnonymized()`**: sin eso, el camino del
`sub` no tiene la defensa que el del correo sí tiene (hueco H4 de la revisión).

### 5.2 · Vincular una cuenta existente — automática, **con tres contrapesos**

`[DECIDIDO owner]`: **automática**.

**La premisa sigue en pie** `[MEDIDO]`: `PasswordRecovery::requestLink()` (41-53) **no** mira
`email_verified_at`, así que quien controla el buzón ya podía apoderarse de la cuenta con un reset.

⚠️⚠️ ✱ **Pero la conclusión que saqué era FALSA.** Escribí que vincular «no abre ninguna puerta que
no estuviera abierta». La revisión midió cuatro diferencias:

| | Reset de contraseña | Vínculo con Google (como estaba diseñado) |
|---|---|---|
| **Duración** | Token de 60 min, un solo uso (`config/auth.php`, `passwords.users.expire`) | **No caduca nunca** |
| **Expulsión** | `PasswordRecovery:91` llama a `revokeAllAccess()`: la víctima expulsa al atacante | **El vínculo sobrevivía** |
| **Reversibilidad** | — | **No se podía deshacer** (desvincular exige contraseña, y no la tiene) |
| **Detección** | La contraseña cambia: se nota | **Silencioso** |

⚠️⚠️ **Y hay un ataque que el reset NO permite y el diseño anterior sí** `[MEDIDO]`: el atacante se
registra **primero** con el correo de la víctima. `SelfSignup` crea esa cuenta **sin verificar** y es
plenamente usable, porque `/mi-cuenta*` solo exige `auth` y no `verified` (grupo del área en `routes/web.php`, con
el porqué de `#332`). Cuando la víctima entra con Google, la única guarda mira el `email_verified`
**de Google** y nunca el `email_verified_at` **de la cuenta destino** ⇒ **la víctima aterriza dentro
de la cuenta del atacante**, con los pedidos y los menores de otro.

**Los tres contrapesos, que ahora son parte del diseño:**

1. ✅ **`[DECIDIDO owner, 2026-09-02]` (Q2): si la cuenta destino NO está verificada, se vincula y
   se EXPULSA a quien la tuviera.** Es decir: se promueve a verificada **y** se llama a
   `revokeAllAccess()` **y** se invalida la contraseña existente.
   ▶ **Por qué así y no rechazando** (que era la respuesta literal del owner, revisada con el dato
   delante): `users.email` es **UNIQUE** `[MEDIDO]`, así que «tratarlo como alta nueva» no tiene
   dónde aterrizar — no caben dos cuentas con ese correo. Y rechazar tiene un coste medido:
   **6 de 48 cuentas de producción están sin verificar (12,5 %)**, o sea que uno de cada ocho
   clientes chocaría con un muro en su primer intento.
   ▶ **Por qué es seguro**: quien conociera esa contraseña queda fuera, y para recuperarla necesita
   el buzón — que no controla. Nadie conserva acceso a una cuenta que no ha demostrado.
   ⚠️ **Residuo asumido, dicho**: si el ocupante hubiera dejado datos dentro, el recién llegado los
   vería. En la práctica esa cuenta está vacía —dejar reservas exige pagarlas— pero **no es
   imposible**, y por eso se dice aquí en vez de descubrirlo el día que pase.
   ⚠️ Decirle a esa persona que existía una cuenta con su correo **no viola `SEC-06`**: solo llega
   ahí quien acaba de demostrar que el buzón es suyo, así que no hay enumeración posible.
2. ✅ **`[DECIDIDO owner, 2026-09-02]` (Q3): aviso por correo al titular en CADA vinculación.** Es la
   única forma de que se entere, y es la doctrina que el producto ya aplica en el cambio de correo (`AccountProfile:135-141`: *«al VIEJO,
   para que el dueño se entere si esto no lo ha pedido él»*) y en `SelfSignup::handleExisting()`.
3. **Desvincular sube a la T3** — ✅ `[DECIDIDO owner, 2026-09-02]` (Q4). Antes era «cuando alguien
   lo pida»; con un vínculo irreversible eso deja al titular sin salida, y es justo la salida que
   necesita quien recibe el aviso del contrapeso 2 y no lo ha pedido.

⚠️ **Y la guarda dura sigue siendo `email_verified` de Google**: si viene `false` —ocurre en algunos
dominios de Workspace—, **no se vincula ni se crea nada**. Nunca se degrada a «el correo coincide».

⚠️ La contraseña no se toca: «entrar con las dos» sale solo.

### 5.3 · Registrarse — una pantalla

Retorno de Google → **no se crea nada** → pantalla (§7) → al enviarla, **en una transacción**:

1. `User` con contraseña aleatoria inservible, `locale`, rol **`customer`**;
2. ✱ **`email_verified_at = now()` con `forceFill`** (no es `fillable`) — ver B2 abajo;
3. `privacy_accepted_at`, `terms_accepted_at` y **una fila de `Consent` por tipo** — ✱ *(la versión
   anterior decía «cuenta, identidad y firma» y se dejaba fuera todo lo que `SelfSignup:210-236`
   escribe: sin esto los clientes de Google saldrían distintos en el panel y en el export)*;
4. la fila de `user_identities`;
5. la **firma** de la exención con el `waiver_document_id` que sirvió la pantalla.

✅ **`[DECIDIDO owner, 2026-09-02]` (Q1): SÍ se escribe `email_verified_at`.** Google acredita el
buzón, y su afirmación es exactamente «esta persona controla esta dirección».

⚠️⚠️ **B2 · Escribir `email_verified_at` es una DECISIÓN, no un detalle.** Es lo que sostiene la
recuperación de contraseña, y `#336` decidió expresamente **no** dejársela tocar al operador de
puerta («acredita a la PERSONA, nunca al BUZÓN»). Aquí quien acredita es **Google**, no un empleado,
y su afirmación es exactamente «esta persona controla este buzón». **Aun así es del owner: Q1 (§15).**
Sin esa decisión, la transacción **no puede firmar la exención** y esta tanda no arranca.

⚠️ **La carrera del texto legal**: `WaiverSigner:98-100` re-comprueba la versión **bajo el lock**. Si
el texto se republica entre pintar la pantalla y enviarla, lanza `WaiverDocumentStaleException` ⇒ se
**re-pinta la pantalla con el texto nuevo, sin perder lo tecleado**. No es un 500.

⚠️ **Doble envío y concurrencia**: el alta se serializa y la violación de unicidad se **captura y se
traduce**, no se deja subir como 500 — el patrón que `AccountProfile:123-133` ya usa.

⚠️ No crear la cuenta hasta enviar la pantalla evita el estado «existes y no puedes hacer nada».

---

## 6 · El mecanismo

### 6.1 · La clave es el `sub`, nunca el correo — *confirmado por la revisión*

El correo se guarda como **copia** (`email_at_link`), como prueba de con qué dirección se vinculó.

### 6.2 · `user_identities`, tabla propia

`[DECIDIDO owner]`: *«como lo valores más profesional y robusto»*.

`user_id` · `provider` · `provider_id` (el `sub`) · **UNIQUE(provider, provider_id)** ·
`email_at_link` · `linked_at` · `linked_via` (`signup`·`login`·`account`).

Razones que la revisión confirma: guarda **rastro** (prueba si alguien reclama) y deja la puerta
abierta a **Apple**. ✱ **La tercera razón que di —«no toca el censo»— era un espejismo**: ver §11.

### 6.3 · ✱ La RAÍZ DE CONFIANZA — sección NUEVA, y era el bloqueante nº 1

⚠️⚠️ **La versión anterior de esta spec no decía en ninguna de sus 494 líneas cómo se comprueba que
lo que dice Google es verdad.** Solo hablaba del `state`. Quien implementara la forma ingenua
—descodificar el `id_token` sin verificar nada— permitiría **fabricar un token que afirme cualquier
correo con `email_verified: true`**, y con §5.2 eso es entrar en la cuenta que se quiera.

1. **Flujo**: *Authorization Code* con intercambio **servidor-a-servidor**. El navegador nunca
   maneja un token. Si en algún momento se aceptara un `id_token` del cliente, hay que **verificar
   firma contra las claves de Google, `aud` = nuestro `client_id`, `iss` y expiración** — y decirlo
   aquí.
2. **`state`**: aleatorio, en sesión, comparado al volver, **de un solo uso**. Sin `state` válido el
   retorno **no hace nada**.
3. **Custodia entre las dos peticiones**: el perfil (`sub`, correo, `email_verified`, nombre) vive
   **en la sesión del servidor**, de un solo uso y **con caducidad explícita**. **Jamás en campos del
   formulario**: si viajaran ahí, cualquiera crearía una cuenta con la identidad verificada de otro
   — y a esa cuenta se le firma una exención probatoria. Es la doctrina que
   `AuthRegistrationController:83-87` ya escribe para el `waiver_document_id`: *«el servidor solo
   emite la aceptación con el identificador que él sirvió»*.
4. El envío de la pantalla **re-lee el perfil de la sesión y lo consume**.

### 6.4 · Rutas en `web`, no en la API — *confirmado*

Dos rutas de navegador. No tocan el contrato ni sus dos guardas.
⚠️ **El destino de vuelta va en sesión y se valida contra el MISMO host** (`SEC-08`).

### 6.5 · ✱ Lo que el retorno tiene que hacer y yo no había escrito

La versión anterior decía «encaja en el modelo que ya existe». Es cierto pero **insuficiente**: el
único login que existe hace **dos cosas más** que el retorno de Google también debe hacer `[MEDIDO]`:

- **`session()->regenerate()`** (`AuthSessionController:76`) — cierra la fijación de sesión. Aquí
  importa **doble**, porque el `state` de §6.3 vive en esa misma sesión.
- **`SidebarEntry::clear()` si el titular anterior era otro** (`:96`), con el daño escrito en su
  comentario: en un dispositivo compartido, «Bob se encontraría el cajón abierto con el *pago
  denegado* de Alice y su código de pedido». Ese servicio tiene **un único llamador** hoy.

### 6.6 · ✱ Las cuentas de EQUIPO se rechazan por defecto

⚠️⚠️ `[MEDIDO]`: `AdminPanelProvider` **no declara `authGuard`**, así que Filament usa el guard
`web` — **la sesión que abriría el retorno de Google es la misma que autentica `/admin`**, sin pasar
por `/admin/login`. Y `canAccessPanel()` recorre `User::PANEL_ROLES` (admin, staff, puerta).

✅ **`[DECIDIDO owner, 2026-09-02]` (Q5): el equipo SÍ puede vincular y entrar con su Google.**
El párrafo de arriba se conserva porque describe **la consecuencia que hay que aceptar a sabiendas**:
la sesión que abre el retorno de Google **es la del panel**, sin pasar por `/admin/login`.

▶ **Y es defendible por la misma equivalencia que el resto del diseño**: quien comprometa el Gmail de
un empleado ya podía pedir un reset de contraseña y entrar igual. Google **no abre una puerta nueva**
—y encima aporta su propio 2FA, que el panel no tiene (`DEUDA.md`: «2FA de admin inexistente»)—.

⚠️⚠️ **Lo que esto convierte en OBLIGATORIO es el aviso de la Q3**: para una cuenta de equipo, una
vinculación silenciosa sería la única señal de que alguien ha entrado por una puerta nueva. Con el
correo, el empleado se entera.
⚠️ Y sube el valor de la ficha de `DEUDA.md` sobre el 2FA del panel: hoy la defensa del panel es una
contraseña, y a partir de aquí también una cuenta de Google.

---

## 7 · La pantalla de completar

> ❗❗❗ **CORRECCIÓN, Y VA ANTES QUE LA TABLA (T8·c, `#350`, 2026-09-02).** Esta pantalla se quedó en
> **DOS cosas: el nombre y el descargo.** El **teléfono** y las **condiciones** los pide el checkout
> desde `#349`, que es el momento del contrato; el marketing nunca estuvo aquí. La tabla de abajo
> describe la T2 tal y como se ejecutó y se conserva porque explica de dónde salió cada campo — pero
> **lo vigente es §21.4.3**.
> ▶ Y con ello la Q6 queda cerrada: la pantalla **se sigue pintando aunque no haya descargo**, por el
> NOMBRE (`[DECIDIDO owner]`, §19.6).

| | | |
|---|---|---|
| Nombre | relleno por Google, editable | Evita el «Ana G.» que a veces devuelve |
| Correo | fijo, mostrado | Es la identidad verificada |
| **Teléfono** | **obligatorio** | `[owner]` *«imprescindible para las reservas»* |
| **Exención** ☐ | **obligatoria — solo en modo `interno` y con versión publicada** | ✅ Q6 abajo |
| **Condiciones** ☐ | **obligatoria** | Aceptación contractual |
| Privacidad | **enlace visible**, no casilla | §7.1 |
| ~~Marketing~~ | ✅ **NO va** (`[DECIDIDO owner]` Q9) | Solo el interruptor de la cuenta (§9). *«Ya valoraremos el marketing cuando toque pensar todo el sistema»* |

✱ **La pantalla sirve y devuelve `waiver_document_id`** (§3.1).
✱ *(La versión anterior citaba `RegisterForm.vue:76` como evidencia de que las condiciones son
aceptación contractual. **La cita estaba mal**: ese docblock es de `acceptWaiver`, no de
`acceptTerms` — y es además la evidencia de que la casilla del waiver solo existe con texto
publicado.)*

✅ **`[DECIDIDO owner, 2026-09-02]` (Q6): en modo `externo` o `desactivado`, o sin versión publicada,
NO HAY PANTALLA — entran directamente.** Sin exención que pedir, §4 se queda sin sujeto y la pantalla
pierde su razón de ser.

⚠️⚠️ **La consecuencia, dicha para que nadie la descubra en producción**: esas instalaciones crean
cuentas **sin teléfono y sin aceptación de condiciones registrada**, porque las dos viajaban en esa
pantalla. Es un estado que el producto ya admite —`CustomerRegistrar` crea cuentas sin teléfono y sin
`terms_accepted_at`— pero por una puerta distinta.
▶ **Hoy no muerde**: `playjump.es` está en modo `interno` con versión publicada (medido: 54 firmas),
así que la pantalla se pinta siempre. Muerde el día que exista una instalación con waiver externo.
▶ **La salida, si algún día se quiere**: pintar la pantalla con teléfono y condiciones aunque no haya
exención. Queda escrito, no construido.

### 7.1 · Por qué la privacidad deja de ser casilla

El RGPD no pide que se «acepte» una política de privacidad: pide **informar** (art. 13), y la base
legal de una reserva es el **contrato** (art. 6.1.b). `[DECIDIDO owner]`: *«nos ahorramos un
checkbox»*.

⚠️ ✱ **Pero quitar la casilla no puede dejar un hueco**: `SelfSignup:210-236` escribe
`privacy_accepted_at` **y una fila de `Consent`**, que es la prueba del art. 5.2. El alta con Google
**registra igualmente la INFORMACIÓN** (fecha, versión del documento servido, IP). Lo que desaparece
es la casilla, no el rastro.

⚠️ El aviso legal (LSSI art. 10) no es una tercera cosa que aceptar.

⚠️⚠️ ✱ **Y falta un trabajo que ninguna tanda planificaba**: la política de privacidad **no menciona
el login con Google** `[MEDIDO]` (los nueve aciertos de «google» en `LegalContent` son Maps y redes).
Si el cumplimiento del art. 13 descansa en «el enlace es visible ahí», el documento tiene que
describir el tratamiento y el origen de los datos (art. 14). El texto vive **en la BD de cada
instalación** ⇒ en `playjump.es` es un paso **manual**. Q7 (§15).

### 7.2 · Anti-bot

**No lleva Turnstile**: quien llega ya pasó por Google. El limitador por IP sí se mantiene sobre la
ruta de salida.

---

## 8 · ✱ Las acciones irreversibles — reescrita entera

`[DECIDIDO owner]`: **re-autenticación con Google**.

Son **cuatro** superficies (§3.4), no una. La regla no es «tener contraseña» sino **demostrar que
sigues siendo tú**.

⚠️⚠️ **El mecanismo que había escrito («la re-autenticación se haya hecho en esta petición») NO ES
CONSTRUIBLE** `[MEDIDO]`: `DELETE /me` es un XHR JSON cuyo cuerpo es `PasswordConfirmation`, con
`required: [current_password]` y `additionalProperties: false` (`openapi/v1.yaml:2659-2668`). Dentro
de esa petición **no cabe un viaje de ida y vuelta a Google**.

⚠️⚠️ **Y aflojar ese esquema tocaría a otro endpoint**: lo comparten `DELETE /me` (`:1312`) y
`POST /me/sessions/revoke-others` (`:1508`). Es literalmente la lección de `#329` con el
`EmailRequest` compartido.

▶ **El mecanismo real**: un **ticket de re-autenticación de un solo uso y con caducidad**, emitido
por el retorno de Google y consumido por la acción. Esquema **nuevo** (`Reauthentication`) en el
contrato — **nunca** aflojando `PasswordConfirmation`.

⚠️ **Desvincular sigue exigiendo contraseña** (`[DECIDIDO owner]`): es lo que impide quedarse fuera.
▶ Y una cuenta de Google gana contraseña con «he olvidado mi contraseña», sin mecanismo nuevo.

---

## 9 · El marketing

| Dónde | Qué |
|---|---|
| **Mi cuenta → Privacidad** | **Interruptor**, en los dos sentidos. Pieza nueva, obligatoria por el art. 7.3 |
| Pantalla de completar | Casilla opcional (Q2) |
| Alta con contraseña | Sin cambios |

⚠️ **No va junto a las condiciones en una pantalla de pago** (art. 7.4: no empaquetado).

⚠️⚠️ ✱ **Y el interruptor solo no cierra el 7.3**: `consents` **no tiene columna de revocación**
(§3.5), así que apagar un booleano no deja constancia de **cuándo** se retiró, y `GET /me/consents`
seguiría mostrando «aceptado el …» encima del interruptor apagado. ⇒ La T3 incluye **fila de retirada
(o `revoked_at`) + el endpoint en el contrato**.

---

## 10 · Configuración por instalación

`auth.google_client_id` y `auth.google_client_secret`. **Nunca en el repo.**

- `GoogleAuth::enabled()` exige **las DOS** claves — la lección de `PublicConfigResource:76-83`.
- Sin claves, el hueco **falla hacia invisible**, como el logotipo, el icono, el kit y la foto del menú.
- ✅ **`[DECIDIDO owner, 2026-09-02]` (Q10): en `settings`, como Turnstile**, con `app:set-setting`.
  ⚠️⚠️ **Y queda escrito el argumento que se descarta al elegirlo**, para que nadie lo redescubra
  como si fuera nuevo: `SetSetting::PROTECTED_KEYS` dice de la clave de Redsys que ponerla en esa
  tabla «la deja en una tabla que se vuelca en cada backup», y `Filament\Pages\Settings` declara que
  los secretos «viven en el vault/`.env`». El owner asume ese coste a cambio del aprovisionamiento
  sin tocar el `.env`. ▶ **Se añaden a `PROTECTED_KEYS`** (exigir `--force`), que hoy solo tiene las
  tres de Redsys: es el mecanismo que el repo ya usa para decir «esto cuesta caro tocarlo a ciegas».

▶ **Consola de Google**: pantalla de consentimiento **externa**, ámbitos **solo `openid`, `email`,
`profile`**, y el origen y la URI de redirección de la instalación.
⚠️ ✱ El ámbito `profile` trae además `picture`, `given_name` y `locale`: **se descartan
explícitamente** (art. 5.1.c, minimización).

### 10.1 · Lo que hay que dejar preparado en Google Cloud Console

**Una instalación = un proyecto de Google = un ID de cliente.** Esto es del cliente, no del producto.

| | |
|---|---|
| Tipo de aplicación | **Aplicación web** |
| Orígenes autorizados de JavaScript | ⚠️ **No hacen falta.** Solo los usa el botón JS de Google / One Tap, y este diseño redirige desde el SERVIDOR (§6.4). Dejarlos puestos no molesta |
| **URI de redireccionamiento** | ⚠️⚠️ **`https://playjump.es/auth/google/callback`** — la RUTA COMPLETA |

⚠️⚠️ **El error que se comete aquí y falla en el primer intento**: poner el origen a secas
(`https://playjump.es`). Google exige que la URI de redirección **coincida EXACTAMENTE** con la que
la aplicación envía; con el origen pelado, el primer inicio de sesión devuelve
`Error 400: redirect_uri_mismatch` y no hay nada que depurar en nuestro lado.

▶ **Segundo cliente para desarrollo**, en el mismo proyecto y llamado por ejemplo `..._dev`, con
`http://localhost:8081/auth/google/callback`. Google admite `http://localhost` con puerto.
⚠️ **Separado del de producción a propósito**: meter localhost en el cliente de producción obliga a
tener el secreto de producción en la máquina de desarrollo.

**Pantalla de consentimiento**

- **Externa** (usuarios fuera de la organización).
- **Ámbitos: solo `openid`, `email` y `profile`.** Cualquier ámbito sensible dispara una verificación
  de semanas, y no necesitamos ninguno.
- ⚠️ **No subas logotipo todavía**: subir el logo a la pantalla de consentimiento dispara la
  verificación de marca de Google. Se puede añadir después, cuando la feature esté viva.
- ⚠️⚠️ **Y hay que PUBLICARLA**: en estado «Testing» solo pueden entrar los usuarios de prueba que se
  añadan a mano. Con estos tres ámbitos, pasar a producción **no requiere verificación de Google**.

**Las dos claves**

`CLIENT_ID` y `CLIENT_SECRET` van a `settings` con `app:set-setting` (§10).
⚠️ **El secreto no se pega en un chat, ni en el repo, ni en un documento.** Va del navegador del
owner al servidor, y nada más.

---

## 11 · Impacto en invariantes

| Invariante | Impacto |
|---|---|
| **`RGPD-01`** | ⚠️⚠️ ✱ **El `cascadeOnDelete` de §6.2 NO SE DISPARA NUNCA**: `anonymize()` **no borra la fila de `users`** (`INVARIANTES.md:73`, la FK de pedidos es RESTRICT). Purgar tiene que ser **explícito y por BORRADO**, no redacción: si se redacta, el `UNIQUE(provider, provider_id)` deja el `sub` ocupado y **esa persona no podría volver a registrarse con su Google nunca más**. Con caso propio |
| **`RGPD-06`** | ✱ **La versión anterior decía «NO desvincula» sin distinguir, y citaba mal el carné.** `INVARIANTES.md:74`: el carné **CAE** en `revokeAllAccess()` y **sobrevive** en `revokeOtherAccess()`. El vínculo sigue el mismo criterio: **cae** con el reset y la anonimización (que es la palanca de «me han entrado»), **sobrevive** al cambio voluntario de contraseña |
| **Art. 20 (export)** | ⚠️ ✱ `AccountPrivacy::exportFor()` **enumera claves a mano** (122-158) y no hay censo que obligue: `user_identities` **quedaría fuera en silencio**. Y `AccountExport` es `additionalProperties:false` con todo en `required` (`openapi/v1.yaml:2735-2746`) ⇒ **toca el contrato** |
| **`SEC-06`** | No se afloja. Camino nuevo, no relajación de los existentes |
| **`SEC-04`** | La re-autenticación de §8 es este principio aplicado a la identidad |
| **`SEC-08`** | Destino de vuelta validado por host |
| **`PAY-*` / `AFORO-*`** | Ninguno: el checkout no se toca |

⚠️ **`CRITICAL_RE`**: `WaiverSigner` está en la lista; la feature **lo invoca y no lo modifica**.

---

## 12 · Los peligros

| # | Peligro | Cierre |
|---|---|---|
| **P11** ✱ | **Token de Google no verificado** → cualquiera afirma cualquier correo | §6.3, con caso de mutación |
| **P12** ✱ | **El atacante registró antes la cuenta** con el correo de la víctima, sin verificar, y conserva la contraseña | §5.2 contrapeso 1: se vincula, se promueve, **`revokeAllAccess()` y la contraseña se invalida** — el ocupante queda fuera y no puede recuperarla sin el buzón |
| P1 | `email_verified=false` y vinculamos | Guarda dura, con caso propio |
| P2 | Clave por correo en vez de `sub` | §6.1 |
| P3 | Cuenta anonimizada vinculable | ✱ `isAnonymized()` **en los DOS caminos**, también el del `sub` (§5.1) |
| P4 | `state` ausente o reutilizado | Un solo uso |
| P5 | Open-redirect | Mismo host |
| P6 | Cuenta de Google sin autoservicio para lo irreversible | §8 |
| P7 | El vínculo sobrevive a la anonimización | §11, **borrado explícito** |
| P8 | Botón sin que el servidor pueda verificar nada | §10 |
| **P13** ✱ | **Doble envío, dos pestañas, `state` consumido, cancelación en Google** | §5.3 y §6.3 |
| P9 | Cuenta de equipo vinculada | ⚠️ **CADUCADO: el owner decidió que SÍ pueden** (Q5, §6.6). Esta fila decía «rechazada por defecto» y era el texto de la revisión, ANTERIOR a esa respuesta. Lo que lo hace defendible es el aviso por correo de la Q3, que pasa a obligatorio |
| P10 | Cuenta de Google compartida | El `UNIQUE` lo resuelve; se eleva al owner porque aquí una cuenta lleva menores y firmas |

---

## 13 · Lo que NO se construye

- **El checkout no se toca** (§4). Mover las condiciones al momento del contrato sigue siendo una
  mejora real, pero es **decisión independiente**: ficha en `DEUDA.md`.
- El mecanismo de re-preguntar consentimientos al subir de versión (§3.6): ficha.
- PKCE / app nativa (§3.10). El `sub` y la tabla valen igual el día que exista.
- Otros proveedores.
- ⚠️ ✱ **La FUSIÓN de cuentas** (hueco H1): una persona con cuenta de mostrador **sin correo**
  (`CustomerRegistrar:88`, el colegio que reserva por teléfono) no se encuentra ni por `sub` ni por
  correo ⇒ se le crea una **segunda cuenta**, y sus pedidos, su carné, sus menores y su firma se
  quedan en la primera. **No hay fusión en el producto y esta spec no la construye**: queda dicho con
  su consecuencia, y la salida operativa es que el operador vincule desde el panel (futuro).

---

## 14 · Plan de verificación empírica

1. Navegador real, los tres caminos.
2. **La exención queda FIRMADA** al completar el alta, comprobado en `waiver_signatures` y en la puerta.
3. **`email_verified=false`** forzado en un doble: no se crea ni se vincula nada.
4. ✱ **P12**: cuenta creada por un tercero sin verificar + entrada con Google ⇒ el recién llegado
   entra, y **la contraseña anterior deja de servir y las sesiones anteriores mueren** (las tres
   cosas aseveradas, no solo la primera).
5. **Art. 12.2 de punta a punta**: cuenta de Google → borrarla con el ticket de re-autenticación →
   `user_identities` **borrada**.
6. Sin claves: el botón no aparece en las doce vistas y la ruta responde 404.
7. **Mutación** de cada guarda nueva, con control.

---

## 15 · Preguntas para el owner

1. **✱ ¿Damos por verificado el correo al entrar con Google?** Es lo que permite firmar la exención
   en el acto, y es lo contrario de lo que decidiste en `#336` — con la diferencia de que aquí quien
   acredita es Google y no un empleado. **Sin esta respuesta la T2 no arranca.**
2. ✅ **RESUELTA** (Q2): se vincula, se promueve y **se expulsa al ocupante**. Ver §5.2.
3. ✅ **RESUELTA** (Q3): sí, en cada vinculación.
4. **✱ ¿Desvincular sube a la T3?** Hoy un vínculo no deseado no se puede quitar salvo borrando la cuenta.
5. ✅ **RESUELTA** (Q5): **sí pueden**. Ver §6.6 y lo que eso hace obligatorio.
6. ✅ **RESUELTA** (Q6): no hay pantalla, entran directamente. Con su consecuencia escrita en §7.
7. ✅ **RESUELTA** (Q7): se actualiza **cuando la feature esté terminada**, no antes. ⚠️ Queda como
   requisito de salida: **no se anuncia el botón a clientes reales sin ese texto**, porque el
   cumplimiento del art. 13 descansa en que el documento enlazado describa el tratamiento.
8. ✅ **RESUELTA** (Q8, 2026-09-02): la pantalla va **en el CAJÓN**. Se le ofreció una tercera
   opción que la spec no había valorado —una página servida por el servidor, como
   `/autorizacion/{pedido}`, con coste CERO de bundle— y eligió el cajón. El presupuesto se mide **al
   empezar la T2**, y el techo o la carga diferida se deciden con la cifra real delante.
9. ✅ **RESUELTA** (Q9): **solo el interruptor** de la cuenta.
10. **Q3 · `settings` o `.env`** — ✱ ya sin recomendación mía: §3.8 y §10.
11. **Texto del botón**: «Continuar con Google».

---

## 16 · Las tandas

| | Qué | |
|---|---|---|
| **T1 · El mecanismo** ✅ | **HECHA — ver §18.** Migración `user_identities` · `GoogleAuth` · **la raíz de confianza de §6.3** · las rutas web con `state` · el servicio de vinculación · **la purga por BORRADO con su caso** · **`user_identities` en el export del art. 20 + su cambio de contrato** · la acción `identities.linked` en `AuditLog::ACTIONS`. ⚠️ **Esta celda decía «rechazo de `PANEL_ROLES`» y estaba CADUCADA**: es el texto de la revisión, anterior a la Q5 — el owner decidió que el equipo **sí** puede vincular (§6.6), y así se ha construido. ⚠️ `identities.unlinked` **no se declara todavía**: su emisor es la T3, y una acción catalogada sin emisor es vocabulario muerto | La prueba primero |
| **T2 · El alta** ✅ | **HECHA — ver §19.** La pantalla en el CAJÓN (`[DECIDIDO owner]` Q8), el alta que nace de ella con todo lo de §5.3, y el botón en las tres superficies de auth. ⚠️ El aviso de vinculación por correo **entró con la T1**: nace con el código que vincula, y un vínculo silencioso aunque fuera un día es el defecto que el contrapeso 2 existe para no tener. ⚠️ El presupuesto **se midió antes de escribir** y decidió la forma: la pantalla va en carga DIFERIDA. ⚠️ El icono tetracolor **no entra**, como esta celda anticipaba: el rótulo nombra la marca y hay ficha en `DEUDA.md`. ▶ La CSP no se toca: no se usa One Tap | El caso del 90 % |
| **T3 · Lo irreversible y el marketing** ✅ | **HECHA — ver §20.** ⚠️⚠️ **El ticket de re-autenticación NO se construye** (`[DECIDIDO owner, 2026-09-02]`, con las dos opciones y su coste delante): quien entró con Google **ya controla su buzón verificado**, así que crea su contraseña con «he olvidado mi contraseña» —un paso, no un muro— y el producto **se lo dice en las cuatro pantallas**. Es la misma salida que esta spec ya aceptaba para desvincular, y evita un segundo camino para autorizar lo irreversible. · **Desvincular**, con la contraseña y el limitador compartido · **interruptor de marketing + `revoked_at`/`revoked_ip`** y su endpoint | Cierra los huecos legales |
| **T4 · El OJO del owner** | Guion de navegador con los tres caminos y con P12 | |

⚠️ **T2→T3 era dependencia dura** y quedó cerrada el mismo día: sin la T3, cada cuenta creada con
Google se quedaba sin autoservicio para las cuatro acciones (art. 12.2).

✱ **Menores anotados por la revisión, uno por línea**: el camino de Google no sella `last_login_at`
ni escribe `Log::info('auth.login')`, que tienen dos consumidores (panel y export) · el correo de
Google se normaliza con `Str::lower(trim())` como las dos puertas existentes, o el caso pasa verde en
SQLite y se comporta distinto en MySQL · firmar en el acto multiplica firmas de un texto que
`waiver-probatorio.md` declara **borrador**, y `retentionMonths()` devuelve `null`, o sea que **no se
poda ninguna**.

---

## 17 · Revisión y decisión

- [x] Revisión adversarial de cinco lentes (2026-09-02) — **8 bloqueantes, aplicados en esta versión**
- [x] Q1 · el correo se da por verificado
- [x] Q2 · cuenta sin verificar: se vincula, se promueve y **se expulsa al ocupante**
- [x] Q3 · aviso por correo en cada vinculación
- [x] Q4 · desvincular sube a la T3
- [x] Q5 · el equipo **sí** puede vincular
- [x] Q6 · sin waiver que pedir, **no hay pantalla**
- [x] Q7 · la política se actualiza **al terminar**, y es requisito de salida
- [x] Q9 · marketing **solo** en el interruptor de la cuenta
- [x] Q10 · las claves en `settings`
- [x] Q8 · la pantalla va **en el cajón** (2026-09-02); el presupuesto se mide al empezar la T2
- [x] Q11 · el botón dice **«Continuar con Google»** (es/en/fr), que era el valor por defecto de la spec
- [x] Q12 · **no se construye el ticket de re-autenticación**: la contraseña por correo, con el aviso en pantalla (2026-09-02)
- [ ] ✅ final del owner a la spec, y su OJO en navegador (T4)

▶ **La T1 está EN EL ÁRBOL** (`#342`, §18). Lo abierto es de la T2 en adelante.

---

## 18 · La T1, EJECUTADA (2026-09-02, `DECISIONES #342`)

**Lee esto antes que §16**: aquí está lo que se construyó, lo que el diseño no había previsto y las
decisiones que hubo que tomar sobre la marcha. Sin claves configuradas, **las dos rutas responden
404**, así que ninguna instalación cambia de conducta hasta que alguien las escriba.

### 18.1 · Las piezas

| Pieza | Qué es |
|---|---|
| `user_identities` (migración) | Una fila = «esta cuenta es también este `sub` de este proveedor». **Dos** claves únicas: por `(provider, provider_id)` y por `(user_id, provider)` |
| `Identity\Models\UserIdentity` | El modelo. Sin `updated_at`: la fila no se edita, y `linked_at` es su `CREATED_AT` |
| `Identity\Services\GoogleAuth` | Las dos claves por instalación, con memo estático y su purga en `TestCase` |
| `Identity\Services\GoogleOAuth` | **La raíz de confianza**: URL de autorización, canje servidor-a-servidor y comprobación del `id_token` |
| `Identity\Contracts\SocialProfile` | Lo que el proveedor afirma, **ya comprobado**. Que exista uno significa que la afirmación es de fiar |
| `Identity\Services\SocialLogin` | Las tres puertas de §5. Hermano de `PasswordLogin`: autentica, sella y registra; no toca la sesión |
| `Http\Auth\GoogleAuthSession` | La custodia entre las dos peticiones: reto de un solo uso y perfil en espera, los dos con caducidad |
| `Http\Controllers\Auth\GoogleAuthController` | Las dos rutas de navegador, el destino de vuelta y los efectos de sesión de §6.5 |
| `Notifications\SocialIdentityLinked` | El aviso de la Q3, con dos textos: vinculación normal y **cuenta tomada** |

### 18.2 · Cuatro decisiones que la spec no tomaba

1. **`UNIQUE(user_id, provider)`: una cuenta, una llave por proveedor.** No es seguridad —para
   vincular hay que demostrar el buzón— sino significado: dos llaves sobre la misma cuenta dejan
   «desvincular» (T3) sin sujeto y el rastro sin dueño. El segundo intento se rechaza **con su
   motivo** (`google-provider-conflict`), nunca en silencio.
2. **`prompt=select_account`.** Sin él, un dispositivo compartido entra con la última cuenta de
   Google que quedó abierta **sin preguntar** — que es el modo de fallo que `SidebarEntry::clear()`
   existe para limitar. Que la persona vea con qué cuenta entra es parte del diseño.
3. **`access_type=online`**: no pedimos `refresh_token` porque no llamamos a ninguna API de Google.
   El `access_token` del canje se ignora: guardarlo sería custodiar una credencial que no hace falta.
4. **El aviso por correo entra en la T1 y no en la T2**, aunque §16 lo listara allí: nace con el
   código que vincula. Un vínculo que se crea sin avisar, aunque sea un día, es el defecto que el
   contrapeso 2 existe para no tener.

### 18.3 · Y una que CORRIGE al código que ya existía: `RGPD-06`

**El vínculo CAE con `revokeAllAccess()` y SOBREVIVE a `revokeOtherAccess()`** — el criterio exacto
del carné, que §11 ya había escrito y la primera versión de la T1 no implementó (dejaba el vínculo
fuera de la revocación «porque no es una credencial»).

⚠️⚠️ **Lo que lo decide no es si es una credencial, sino para qué sirve `revokeAllAccess()`**: es la
palanca de «me han entrado». Un vínculo plantado por quien te tomó la cuenta sería una puerta trasera
que **el restablecimiento de contraseña no cerraría**. Un cambio voluntario de contraseña no lo toca,
por la misma razón que no toca el carné: no es una defensa, es mantenimiento.

⚠️ **Eso obliga a un ORDEN dentro de la toma de una cuenta sin verificar** (P12): expulsar **antes**
de escribir el vínculo. Al revés —que es como estaba— la expulsión se lleva por delante la llave
recién dada, y **nada falla**: la persona entra y su cuenta queda sin vincular. Hay caso para el
orden, y su mutación muerde.

▶ **Consecuencia conocida y aceptada**: tras un reset, la siguiente entrada con Google **vuelve a
vincular sola** (la cuenta está verificada) y manda su aviso. Es ruido, no un bloqueo.

### 18.4 · Lo que enseñó la mutación (21 mutaciones, 20 muerden)

1. ⚠️⚠️ **El caso del `state` reutilizado no probaba nada.** Cerraba la sesión entre los dos retornos,
   así que el reto desaparecía por el `logout` y no por consumirse: quitar el `forget()` lo dejaba
   verde. Los dos retornos van ahora seguidos, sobre la misma sesión.
2. ⚠️⚠️ **Las dos caducidades se medían con `time()`, que es invisible para `travel()` y para la
   auditoría del reloj de la suite.** No es que el caso fallara: es que **no se podía escribir**, y
   por eso no existía. Pasan a `now()` y entran sus dos casos. *Una guarda de caducidad que no se
   puede hacer caducar en un test no está probada.*
3. ▶ **`session()->regenerate()` no muerde y se conserva.** `SessionGuard::login()` ya llama a
   `migrate(true)`, así que la propiedad está garantizada dos veces y el caso no distingue cuál la
   sostiene. Queda dicho en el propio test para que nadie lo lea como una guarda ciega — y la llamada
   se queda porque es lo que hace el único login que ya existía.
4. ⚠️ **El arnés restaura con `git checkout` y se llevó cambios sin commitear.** La regla de `#181`
   —commitea antes de mutar— vale también para lo que se escribe **después** de empezar a mutar.

### 18.5 · Dos trampas que el repo ya conocía y volvieron a morder

- **Pint convierte un `{@see}` en `use`** (`#320`): un modelo acabó importando un servicio por una
  cita en su docblock. La salida es reescribir la cita **en prosa y quitar el import**, no solo lo
  primero.
- **El export del art. 20 enumera claves a mano y no hay censo que obligue**, así que un dato nuevo
  se queda fuera **en silencio**. Lo que lo caza es el CONTRATO (`additionalProperties: false` con
  todo en `required`): por eso `ExportedIdentity` y su clave entran en el mismo commit, y el caso
  valida la respuesta contra él —sin `assertValidResponse` sería un test de texto—.

### 18.6 · Lo que la T1 deja abierto a propósito

- **La pantalla de §7 no existe**, así que un visitante sin cuenta sale a `/registro` con un aviso.
  Es el único punto del controlador que la T2 cambia (`pendingRegistration()`).
- **`identities.unlinked` no está catalogada**: su emisor es la T3.
- **Nadie enseña el botón todavía** (T2), y sin claves las rutas son 404 — o sea que esto no es
  alcanzable por un visitante hasta que las dos cosas ocurran.
- ⚠️ **Un titular ya identificado que pase por `/auth/google` cambia de cuenta si su Google resuelve
  a otra**: es la conducta normal de «entrar con Google», no la de «vincular la mía», que llega en la
  T3 con su intención explícita (`linked_via = account`).

---

## 19 · La T2, EJECUTADA (2026-09-02, `DECISIONES #343`)

Con ella el camino se cierra: quien no tiene cuenta vuelve de Google, completa lo que Google no da y
entra **ya firmado**.

### 19.1 · Las piezas

| Pieza | Qué es |
|---|---|
| `Identity\Services\GoogleSignup` | El alta desde un `SocialProfile`: cuenta, rol, sellos, `consents`, vínculo y **la firma del descargo** |
| `Api\V1\GoogleSignupController` | `GET auth/google/pending` (qué pintar) y `POST auth/google/complete` (el alta) |
| `account/google.js` | El módulo plano: qué se manda, los tres desenlaces y **la secuencia de la pantalla** |
| `account/zones/GoogleSignupZone.vue` | La pantalla, en **carga diferida** |
| `steps/GoogleButton.vue` | El botón, en las dos zonas de auth y en el paso 5 del embudo |
| Ruta `registro.google` + `AccountDoor` | La PUERTA: sirve la home y el cajón abre su zona, como `/registro` |

### 19.2 · Q8, resuelta con la cifra delante

`[DECIDIDO owner]`: **en el cajón**. Se le ofreció la tercera opción que la spec no valoró —una página
servida por el servidor, coste cero de bundle— y eligió el cajón.

Medido antes de escribir, que es lo que §16 pedía: **44 B de holgura** en el chunk y **~6,2 KiB** de
pantalla. Así que la elección real era subir el techo para todos o cobrárselo a quien la usa. Se
cobra a quien la usa, con **dos podas medidas**: carga diferida (**−1,88 KiB**) y el estado fuera del
store global (**−1,32 KiB**). El techo sube 263 → **267**, y el del payload anónimo 2.750 → **2.800**,
que son los 44 B del rótulo del botón.

⚠️ **Los ~380 B de la pantalla no viajan en ninguna página**: es la primera poda por RUTA del montaje,
y se sostiene porque a esa zona **no se llega de ninguna otra forma**.

### 19.3 · Lo que sostiene la seguridad de la pantalla

1. ⚠️⚠️ **Ni el `sub` ni el correo viajan en la petición.** Los pone el servidor desde la sesión. Hay
   caso que manda un `email` en el cuerpo y comprueba que la cuenta nace igualmente con el de la
   sesión — porque el silencio sería la vulnerabilidad.
2. **El envío CONSUME el perfil**: dos pestañas o un doble clic no crean dos cuentas.
3. **Antes de crear se vuelve a RESOLVER la identidad** con el mismo servicio que el retorno: si el
   correo se registró mientras tanto, se ENTRA en vez de estrellarse contra el `UNIQUE`.
4. La firma del descargo va **fuera** de la transacción del alta: `WaiverSigner` abre la suya y
   bloquea la fila del titular, y anidarla dejaría ese lock tomado durante todo el alta. Si fallara,
   la cuenta es válida y el aviso de «te falta firmar» ya existe.

### 19.4 · Dos guardas ENSANCHADAS (no exceptuadas) y una mutación que enseñó algo

- **`SidebarTranslationKeysExistTest` medía contra UNA página.** Con la primera poda por RUTA eso
  daba un **falso positivo** —declararía «mudas» unas claves que llegan— y un falso positivo empuja a
  mandar bytes a todas las páginas para callarlo. Ahora recorre también las PUERTAS, como invitado
  (con sesión, una puerta de invitado abre el índice).
- **`SidebarStyleWiringTest`** cazó `.auth__google` sin regla: la clase se escribió antes que su CSS.
- ⚠️⚠️ **Y un caso pasaba en verde por el motivo equivocado**: «sin aceptar el descargo no hay cuenta»
  aseveraba solo el 422, que con la regla de la casilla relajada seguía saliendo por la regla del
  identificador del texto. Ahora asevera el CAMPO. *Un test que solo mira el código de estado no dice
  qué guarda funciona.*

### 19.5 · El botón, y lo que NO lleva

**Sin logotipo tetracolor de Google.** El set de iconos exige `currentColor` y rejilla 24
(`IconSetAnatomyTest`), y un glifo con colores tecleados dentro rompe la anatomía que hace que la web
se vea de un solo idioma — la celda de §16 ya lo anticipaba. La marca queda en el rótulo, y hay ficha
en `DEUDA.md` por si el owner quiere el botón oficial, que exige tratar su logo como **asset**.

⚠️ El botón se pinta **solo si la instalación ofrece Google**: la ida viaja en el montaje únicamente
con las dos claves configuradas, y su ausencia ES el interruptor. Con caso en las dos direcciones.

### 19.6 · Lo que la T2 deja abierto

- **La T3 entera**, y sigue siendo dependencia dura: cada día que esto esté sin ella crea cuentas sin
  autoservicio para las cuatro acciones que exigen contraseña (art. 12.2).
- **El OJO del owner**: guion en `VERIFICACION-E2E-CAJON.md` §5.google. Necesita el cliente de OAuth
  de DESARROLLO — el de producción no sirve, y es correcto que no sirva.
- ✅ **LA DESVIACIÓN DE LA LETRA DE LA Q6, RESUELTA POR EL OWNER** (`[DECIDIDO owner, 2026-09-02]`,
  con la T8·c delante; el texto de abajo se conserva porque explica cómo llegó aquí).
  ▶ **La razón vieja YA NO EXISTE**: la T8·c se llevó de esa pantalla el teléfono y las condiciones,
  así que en una instalación sin descargo se quedaría con **un solo campo**. La pregunta volvía a ser
  la de la Q6.
  ▶ **Se sigue pintando, y ahora POR EL NOMBRE**: Google devuelve a veces «Ana G.», y ese nombre viaja
  a la reserva y a la firma del descargo — ésta es la única ocasión de corregirlo antes de que se use.
  La alternativa costaba construir una rama de alta directa que **ninguna instalación viva ejercita**,
  y código nuevo sin uso real es peor red que una pantalla probada. Ver §21.4.3.
- 📜 *Lo que decía antes, para que se entienda la decisión:* §7 dice que en modo `externo` o sin
  versión publicada **no hay pantalla** y se entra directo; la T2 la pintaba igualmente, con teléfono
  y condiciones. El motivo era que la Q6 se contestó dando por hecho que la pantalla existía **solo**
  para el descargo, y no era así.

---

## 20 · La T3, EJECUTADA (2026-09-02, `DECISIONES #344`)

Cierra los **tres huecos legales**, y dos de ellos **no eran de las cuentas de Google**: llevaban
vivos desde el primer día para todo el mundo.

### 20.1 · El art. 7.3, que era el más grave

`consents` gana `revoked_at` y `revoked_ip`; `PUT /me/marketing` da y retira; el interruptor vive
dentro de la tarjeta de consentimientos del cajón.

⚠️⚠️ **La retirada SELLA la fila, no la borra**: la fila sigue probando que en su día se aceptó —lo
que justifica los envíos hechos— y el sello dice cuándo dejó de valer. Borrarla dejaría al parque sin
poder demostrar lo primero (art. 7.1).
⚠️ **Sin `current_password`**, y es la ley: el art. 7.3 exige que retirar sea *tan fácil como dar*.
Hay caso que lo fija para que nadie lo «endurezca» creyendo que mejora la seguridad.
⚠️ **Idempotente en las dos direcciones**, y la mitad que importa es ENCENDER: sin guarda, el segundo
clic escribe una segunda prueba del mismo consentimiento.

### 20.2 · Las cuatro acciones que exigen contraseña — la Q12 cambia §8

`[DECIDIDO owner, 2026-09-02]`, elegido sobre el ticket de §8 con las dos opciones y su coste
delante: **la contraseña por correo, con el aviso en pantalla**.

▶ **Lo que lo sostiene**: quien entró con Google ya controla su buzón verificado, así que «he
olvidado mi contraseña» es un paso y no un muro — y es exactamente la salida que §8 ya aceptaba para
desvincular. *Lo que faltaba no era un camino nuevo de autenticación: era decírselo donde se topa con
la pared.*
▶ **Lo que se evita**, dicho para que no se lea como un atajo: un SEGUNDO camino para autorizar lo
irreversible, con su estado en sesión y su esquema en el contrato.

⚠️ **El aviso se pinta SIEMPRE** (`account/NoPasswordHint.vue`, en las cuatro pantallas): el servidor
no puede distinguir un hash aleatorio de uno elegido, así que detectarlo exigiría una columna nueva
mantenida en los siete sitios que escriben contraseñas. Para quien sí la tiene, la frase sigue siendo
verdad. La alternativa medida queda en `DEUDA.md`.

⇒ **§8 queda corregida por esto**: el «ticket de re-autenticación de un solo uso» que aquella sección
describe **no existe** y no se va a construir salvo que el owner lo reabra.

### 20.3 · Desvincular

`GET /me/identities` y `DELETE /me/identities/{provider}`, con la contraseña y **compartiendo el
limitador** del cambio de contraseña.

⚠️⚠️ **Sin esto, el aviso de vinculación de §5.2 no servía de nada**: el vínculo se crea solo, no
caduca y se avisa por correo — y ese aviso solo vale si quien lo recibe puede deshacerlo. La única
salida de un vínculo no pedido era borrar la cuenta.
⚠️ **La lista no publica el `sub`**: la pantalla necesita saber con qué cuenta se entra y desde
cuándo. El identificador viaja en el export del art. 20, que es un acto explícito del titular.
⚠️ **Idempotente**, y con caso: desvincular con la contraseña equivocada **deja el vínculo intacto**.
Un endpoint que borrara primero y comprobara después dejaría a cualquiera con una sesión robada
quitarle al titular su forma de entrar.

### 20.4 · Los dos presupuestos, subidos DESPUÉS de podar

| | Antes | Ahora | Poda que se hizo antes |
|---|---|---|---|
| Chunk del cajón | 267 | **269** (medido 268,26) | El aviso es UN componente en las cuatro pantallas, no cuatro copias; el interruptor entró en una tarjeta que ya existía |
| Payload con sesión | 9.650 | **9.900** (medido 9.826) | El botón del aviso reutiliza `forgot.title`, que ya viajaba; el bloque de vínculos no tiene rótulo de «no hay ninguno» |

### 20.5 · Lo que queda del todo

- **El OJO del owner** (`VERIFICACION-E2E-CAJON.md` §5.google), que necesita el cliente de OAuth de
  DESARROLLO.
- **La política de privacidad** (Q7), y es **requisito de salida**: no se anuncia el botón a clientes
  reales sin que el documento describa el tratamiento y el origen de los datos (art. 13/14). El texto
  vive en la BD de cada instalación, así que en `playjump.es` es un paso manual.
- La desviación de la letra de la Q6 (§19.6), pendiente de tu palabra.

---

## 21 · El PULIDO que salió del ojo del owner (2026-09-02)

**La T4 de §16 era «el OJO del owner». Se ha hecho, y esto es lo que sacó.** Son siete puntos, y la
mitad no son de Google: son huecos del producto que solo se ven cuando alguien recorre el camino
entero con ojos de cliente.

⚠️⚠️ **Dos de ellos REABREN decisiones escritas, y eso es deliberado del owner, no un descuido de
quien lo ejecuta.** Están marcados abajo con su decisión anterior delante, para que nadie los lea
como una incoherencia y los «arregle» de vuelta.

| # | Qué pidió | Dónde estaba | Tanda |
|---|---|---|---|
| 1 | El botón **oficial** de Google, con su marca | Ficha ABIERTA en `DEUDA.md` desde `#343` | **T5** ✅ `#345` |
| 2 | El copy de «Completa tu registro» no habla de reservar | Defecto nuestro, sin ficha | **T5** ✅ `#345` |
| 3 | El **interruptor de marketing** es un checkbox, y al pulsarlo aparece un scroll horizontal | Defecto de `#344`, sin ficha | **T6** ✅ `#346` |
| 4 | **Vincular** Google desde la cuenta (desvincular ya está) | `UserIdentity::VIA_ACCOUNT` declarado y sin emisor; §18.6 lo avisa | **T7** ✅ `#347` |
| 5 | El **panel de admin** dice si el cliente entra con Google | No estaba en la spec | **T7** ✅ `#347` |
| 6 | Las **condiciones** y el **teléfono** se piden en el checkout, no en el alta | ⚠️ **§4 lo DESCARTÓ y §13 lo dejó como ficha** «decisión independiente» | **T8** ✅ `#348` · `#349` · `#350` |
| 7 | La **privacidad** no lleva casilla en ninguna de las dos altas | Hecho en la de Google (§7.1); **pendiente en el alta con contraseña** | **T8·c** ✅ `#350` |

▶ **Los siete puntos están cerrados.** Lo que queda del carril es la **T9 (One Tap)**, que no salió
del ojo del owner sino de §13, y sus dos requisitos de salida: la **política de privacidad** (Q7) y el
**✅ del owner en navegador**.

### 21.1 · T5 — el botón oficial y el copy (`DECISIONES #345`)

`[DECIDIDO owner, 2026-09-02]`, elegido sobre tres variantes renderizadas: **la CLARA, en píldora**.

▶ **Lo que cambia**: el botón dejaba de ser suyo. Era `.btn--zone` —el color de marca de la
INSTALACIÓN— con «Google» como única pista, que es admisible pero no es su botón; la ficha de
`DEUDA.md` (`#343`) ya decía que la salida era tratar su logotipo como **asset por proveedor**.
Ahora lleva su «G» a cuatro colores, su blanco, su borde y sus medidas.

**Los valores son suyos y están tomados de su guía** (`developers.google.com/identity/branding-guidelines`,
leída el 2026-09-02): fondo `#FFFFFF` · borde `#747775` de 1 px por dentro · texto `#1F1F1F` a 14/20
en peso Medium · 12 px antes del logotipo, 10 después · forma rectangular **o píldora**, las dos
admitidas · el rótulo, una de sus tres cadenas («Continuar con Google»), localizada.

⚠️⚠️ **Van en px ABSOLUTOS y no en `--sp-*`/`--fs-*`, y no es descuido.** Esas escalas son TEMA: un
cliente que quiera el cajón más aireado cambia `--sp-unit` y se mueve todo a la vez. El botón de un
tercero no puede moverse con eso, porque entonces retocar el tema **deja de cumplir su guía sin que
nada falle**. Lo único nuestro es el suelo táctil (`--tap-min`, 44 px, por encima de sus 40): crecer
está permitido, encoger no.

⚠️ **`--r-pill` sobre un botón contradice la prosa de la escala de forma** («ONLY decorative passive
labels»). Es la excepción de esta pieza y está dicha en el CSS: el canto lo manda Google. Se usa el
TOKEN igualmente para no dejar un literal que `ShapeScaleTest` tuviera que perdonar.

#### El logotipo es un FICHERO, y las tres razones se refuerzan

1. Es la salida que `DEUDA.md` ya había escrito: **asset por proveedor**, como `client-logo.svg`.
2. **En línea no cabe.** Como `<svg>` dentro del cajón sería un *dibujo inventado* para
   `SidebarIconParityTest`, cuya `DRAWER_OWN` está **vacía a propósito y solo encoge**; y como
   componente `<x-icons.*>` rompería `IconSetAnatomyTest`, que exige `currentColor` y rejilla 24.
   Las dos guardas tienen razón: por eso la pieza vive **fuera** de las dos.
3. Dentro de un `<img>` un SVG es **inerte** (`#254`) y **no hay forma de recolorearlo desde el CSS**
   — la única defensa real contra que alguien lo «adapte al tema».

⚠️ **Y va con `:src` enlazado a una constante, no con `src` literal**: con `src="/images/…"` Vite lo
trata como un import a resolver desde la raíz y **el build SSR falla** (`UNRESOLVED_IMPORT`, medido).
Un fichero de `public/` no pasa por el empaquetador.

#### Lo verificado, con control

- **El dibujo es el suyo, no una reconstrucción.** Se descargó de
  `gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg` —el asset que Google distribuye con
  FirebaseUI— y se conserva su nota de copyright. Al normalizarlo (quitar los dos `<g transform>` de
  Sketch, que **se anulan exactamente**, y el rect del artboard) se rasterizaron original y resultado
  a 472×480 con `rsvg-convert`: **0 píxeles distintos de 226.560**, mismo sha1 del PNG. **CONTROL**:
  borrando un `<path>` salen **15.855**.
  ⚠️⚠️ **El rect del artboard había que quitarlo, no era limpieza cosmética**: no declara `fill` y
  solo era invisible porque heredaba el `fill="none"` del grupo que lo envolvía. Aplanando los grupos
  habría pasado a pintar un **cuadrado negro** encima del logotipo — la trampa del troquel de
  `hueco-ilustracion.md` §8, entrando por otra puerta.
- **Sonda de navegador** sobre `/login` y `/registro` (Chromium, 420×900, `document.fonts.ready`
  comprobado con familias > 0 para no caer en la captura sin letras de `#335`): fondo, texto, borde,
  radio, 14/20/500, logotipo a 18×18 con `object-fit: contain` y hueco de 10 px — **todo coincide con
  su guía**, y el alto sale 44. **CONTROL de imagen rota**: una ruta inventada da `naturalWidth = 0`,
  o sea que el instrumento sabe decir que no cargó — que es lo que hace creíble el `118` del bueno.
- **La tercera superficie (paso 5 del embudo) comparte componente y regla**, y que ningún contenedor
  la pise se comprobó estáticamente: no hay ninguna regla de ancestro que alcance a `.auth__google`,
  y la única `… .btn` del embudo es `.purchase__maint-ctas`, que es otra pantalla.
- **9/9 mutaciones muerden** (`scripts/mutar-boton-google.sh`, con puerta de VERDE antes de mutar y
  veredicto por código de salida): redibujar, recolorear, `currentColor`, un `on…=` dentro, volver a
  `btn--zone`, `alt="Google"`, quitar la marca, «armonizar» el fondo con `--action` y el hover.

#### La guarda, y por qué hacía falta una nueva

`GoogleButtonBrandingTest`. ⚠️⚠️ **Existe porque NINGUNA otra guarda veía este botón, y está
medido**: el manifiesto congelado de `SidebarDomContractTest` tiene **cero** ocurrencias de «google»
—sus fixtures no pasan `urls.google` y el componente es un `v-if="href"`—, así que todo el marcado y
toda la piel estaban fuera de cobertura.

⚠️⚠️ **El modo de fallo que persigue no es que se rompa: es que alguien lo ARREGLE.** El impulso
natural de cualquiera que mire esta web —y de cualquier guarda de coherencia visual— es devolver este
botón al idioma de la casa. Eso no rompe nada, no lo enseña ninguna captura y **incumple las
directrices de Google**.

#### El copy

⚠️ La pantalla decía *«Solo nos falta esto para poder reservar a tu nombre»* y **aquí no se reserva
nada**: crea la cuenta, que es lo que dice su propio botón de envío. Corregido en es/en/fr a *«Google
ya nos ha confirmado quién eres. Solo falta esto para crear tu cuenta»*, que **sigue siendo verdad
después de la T8**, cuando esa pantalla se quede solo con el descargo.

### 21.2 · T6 — el interruptor de marketing, y el defecto que lo destapó (`DECISIONES #346`)

El owner: *«el checkbox al darle clic añade un texto horizontal que hace que el SPA sea más ancho y
se genera un scrollbar horizontal»*. Son **tres cosas** y solo una era la que se veía.

#### 1 · El desborde, reproducido con control antes de tocar nada

**Medido en navegador a 420 px de ancho**: 0 px de desborde antes de pulsar · 0 al ENCENDER ·
**82 px al APAGAR**, en `.account__grid`, con 62 px en `.purchase__scroll`, que es el carril del
cajón y el que enseña la barra.

⚠️⚠️ **El `documento` NO desbordaba** (`scrollWidth − clientWidth = 0`): el cajón es un panel con su
propio scroll, así que una sonda que mire `document.documentElement` habría salido limpia con la
barra a la vista. La sonda sube por la cadena de ancestros desde la fila culpable buscando el primero
que desborda — *y esa decisión de instrumento es la que hizo visible el defecto*.

**El mecanismo**: `.account__consent-meta` llevaba `white-space: nowrap` desde que su contenido era
«fecha · versión», donde describía algo cierto —nada de eso se puede partir sin quedar mal—. `#344`
le añadió al final «retirado el 02/09/2026» y la línea pasó a medir **418 px dentro de un carril de
380**, sin que fallara nada.

▶ ***El `nowrap` no estaba mal: dejó de ser cierto cuando alguien alargó lo que envolvía.*** Por eso
la corrección no es quitarlo, es **moverlo de la LÍNEA al TROZO**: `consentRows()` devuelve una lista
de trozos, cada uno indivisible, y entre ellos se salta de línea. El «·» lo dibuja la hoja entre
hermanos, así que un trozo ausente (una versión vacía) no deja un separador colgando.

#### 2 · Es un INTERRUPTOR, y la diferencia no es estética

`[DECIDIDO owner]`. Una casilla es una elección que se **envía** con un formulario; esto se guarda
**al soltarlo**, sin botón. `role="switch"` es lo que se lo dice al lector de pantalla: con
`checkbox` anuncia «casilla, no marcada» y quien no ve **espera un “Guardar” que no existe**.

⚠️ **El control es el propio `<input>` con `appearance: none`**, no una pista pintada al lado de un
input escondido: así el anillo de foco cae sobre la caja real, que es la que `landing.css` ya cubre
en su lista blanca CERRADA (`input[type="checkbox"]:focus-visible`). Un input a 0×0 dibujaría el
anillo **sobre nada** — la trampa de `#295`: *hacer algo enfocable no es hacerlo accesible*.

⚠️ **Encendido es `--ok`, no `--action`** (`#254`): acción es el control que hace AVANZAR; encendido
es un ESTADO. Medido en navegador: `rgb(95,168,46)`, que es el `--ok` del paquete del cliente.

#### 3 · Y la captura destapó lo que no se había pedido: CINCO filas del mismo consentimiento

Cada vuelta del interruptor escribe una fila nueva —es un hecho nuevo y `#344` lo dejó así a
propósito—, así que tras cinco vueltas la tarjeta decía **cinco veces «Comunicaciones comerciales»**,
cuatro tachadas. Es justo lo que el owner pedía evitar: *«cuidado de no añadir datos en ese recuadro»*.

▶ **La lista colapsa a la ÚLTIMA fila de cada tipo.** ⚠️⚠️ **Esto no oculta ninguna prueba**: el
rastro completo sigue en la BD (art. 5.2 / 7.1) y viaja entero en el documento de portabilidad
(art. 20), que se descarga **desde esta misma tarjeta**. Se colapsa la lectura, no la prueba.
⚠️ La «última» sale del ORDEN QUE MANDA EL SERVIDOR (`accepted_at DESC, id DESC`, que
`MePrivacyController::consents` ya publicaba): aquí no se comparan fechas ya localizadas como texto.
▶ Y una fila retirada **se lee retirada** (tachada y en gris), que es el consumidor que le faltaba a
la bandera `revoked` que `#344` publicó y no usaba nadie.

#### Lo verificado

- **Navegador, con control**: 82 px de desborde → **0 px** en los tres estados.
- ⚠️⚠️ **Una trampa de instrumento pagada**: la primera medición del interruptor decía que el dibujo
  iba **un paso por detrás del estado** (`checked = true` con la pista apagada). Era la sonda leyendo
  **dentro** de la transición de 180 ms, que además se reinicia cuando la lista se recarga tras el
  `PUT`. En reposo y preguntando por `getAnimations()` —**cero animaciones vivas**— los dos estados
  leen correctos. *Un valor leído en mitad de una transición es el del estado anterior, y parece un
  defecto del producto.*
- **10/10 mutaciones muerden** (`scripts/mutar-tarjeta-consentimientos.sh`), empezando por el defecto
  real del owner: devolver el `nowrap` a la línea.
- Guardas: `ConsentCardTest` (el CSS y el marcado) + `privacy.test.js` (los trozos y el colapso).
  ⚠️ **La primera versión de un caso salió ROJA con el producto sano**: aseveraba «no hay ningún
  `<label class="check">` en la pantalla» y en esa misma zona vive la casilla del DESCARGO, que sí es
  una casilla. Acotado al control de marketing. *Una guarda que acusa a lo que está bien está mal
  escrita.*

#### El presupuesto: se intentó podar, se midió, y no sirvió

Chunk **273 → 274** (medido **273,14**). ⚠️⚠️ **La poda se hizo primero y se descartó con la cifra
delante**: pasar los trozos de la meta y el rótulo del interruptor a selectores por tipo de elemento
ahorró **0,06 KiB** y seguía por encima del techo, así que su único efecto habría sido dejar el
marcado menos explícito **sin evitar la subida**. Se revirtió.
▶ ***Una poda que no evita subir el techo no es una poda: es solo peor código.***

### 21.3 · T7 — vincular desde la cuenta, y el vínculo en el panel (`DECISIONES #347`)

El owner: *«nos falta el sincronizar o desvincular la cuenta de Google en el panel de usuario, ¿no?»*
— **Desvincular ya estaba** (`#344`, §20.3). **Vincular no existía**, y su ausencia tenía consecuencia
escrita: §18.6 avisaba de que un titular identificado que pasara por `/auth/google` **cambiaba de
cuenta** si su Google resolvía a otra. Es la conducta correcta de «entrar con Google» y la equivocada
para «vincular la mía»: **lo que faltaba era la INTENCIÓN, no una comprobación más**.

#### La cuarta puerta

`SocialLogin::linkToAccount()`, aparte de `enter()` porque **no es entrar**. Lo que se vigila no es
que vincule: es lo que **NO** hace.

| No hace | Por qué |
|---|---|
| **No autentica** | Si el `sub` resolviera a otro titular y le abriéramos su sesión, «vincular» sería un cambio de cuenta encubierto — y en un dispositivo compartido, entrar en la cuenta de otro |
| **No promueve a verificado** | La toma de `enter()` (P12) existe porque allí la única prueba es el CORREO. Aquí el titular ya está dentro; tocar `email_verified_at` afirmaría algo sobre un buzón que nadie ha comprobado (`#336`: se acredita a la PERSONA, nunca al BUZÓN) |
| **No expulsa ni invalida la contraseña** | Lo mismo: la expulsión es la mitad de la toma, y aquí no hay nada que tomar |

⚠️ **El correo de Google puede ser OTRO y se admite**: la clave es el `sub` (§6.1), y aquí el titular
se ha identificado él mismo — prueba más fuerte que la coincidencia de correo en la que se apoya §5.2.
⚠️ **La guarda dura de `email_verified` SÍ se conserva** aunque aquí el correo no identifique a nadie:
`email_at_link` se guarda como prueba de con qué dirección se vinculó, y guardar como prueba una
dirección que el proveedor no da por buena es guardar **una prueba falsa**.

#### Los dos rechazos son ESPEJOS y no se pueden confundir

- `provider_conflict` — *tu cuenta ya tiene otra llave*. Salida: desvincular la tuya.
- `provider_taken` (**nuevo**) — *esa llave ya abre otra cuenta*. Desde aquí no hay nada que hacer.

Dar la salida equivocada manda a la persona a buscar un botón que no le sirve.

#### La intención viaja en el RETO, no en la URL

Ruta aparte (`/auth/google/vincular`) con middleware `auth`, y el reto anota `intent` **y quién la
pidió**. ⚠️⚠️ **La comprobación del titular es la que evita el defecto silencioso**: entre la ida y la
vuelta caben un `logout` y un `login` con otra cuenta —en un dispositivo compartido es lo normal— y
sin ella el vínculo aterrizaría en la cuenta equivocada **sin que nada fallara**. Hay caso.

⚠️ Un reto viejo sin la clave `intent` —de una sesión abierta antes del despliegue— cae a ENTRAR, que
es la conducta de siempre.

#### Sin contraseña, y la asimetría con desvincular es deliberada

Desvincular sí la exige porque puede dejarte **FUERA**; vincular no. Contra el escenario que importa
—una sesión robada que planta su Google como puerta trasera— hay dos defensas que **ya existen** y
son las de §5.2: el **aviso por correo** de cada vinculación (detección) y que `revokeAllAccess()`
**se lleve las identidades** (`RGPD-06`), o sea que «he olvidado mi contraseña» cierra la puerta.
▶ **Lo que NO cierra, dicho**: un cambio VOLUNTARIO de contraseña no retira el vínculo, igual que no
retira el carné. Es idéntico al vínculo automático de §5.2 — este camino **no añade una clase de
riesgo nueva**, solo otra forma de llegar a la misma.

#### Dónde vive el botón, y el huevo-y-gallina que se evitó

En la zona de Sesiones, junto a la lista de vinculadas. ⚠️ **El bloque se pinta ahora también sin
ninguna vinculada**: antes salía solo con alguna, y con el botón dentro eso habría sido **el
huevo-y-gallina de `#400`** —el botón de una acción escondido tras una condición escrita para lo que
se LEE— repetido en otro subsistema. Se ofrece **solo si no hay ya una de Google**:
`UNIQUE(user_id, provider)` es una cuenta, una llave (`#342`), así que ofrecerlo con una puesta sería
ofrecer un camino que solo puede acabar en un «no».

#### El panel de admin

Una entrada en la ficha del cliente: **sí/no + desde cuándo**, y el correo con el que se vinculó
**solo si es distinto** del de la cuenta — ahí está el valor, porque desde esta tanda pueden diferir.
⚠️⚠️ **No publica el `sub`**, que es la misma línea de `#344`: el identificador viaja solo en el
export del art. 20.

#### Lo verificado

- **12 casos** en `GoogleAccountLinkTest`, que conducen el flujo de verdad (piden la ida, leen el
  `state` del redirect y vuelven con él) + **4** del panel + **1** del montaje en sus tres direcciones.
- **Navegador**, de punta a punta: la sección se pinta sin vinculadas, el botón lleva a
  `/auth/google/vincular` y **la ida sale a Google** con `redirect_uri` completo, `state` de 64 hex y
  `prompt=select_account`.
- ⚠️⚠️ **Una trampa del ARNÉS, medida**: `Http::fake()` **acumula** stubs y gana el primero que casa,
  así que un caso que recorre el flujo dos veces —el de idempotencia, o cualquiera con su control—
  recibía en el segundo canje el token del PRIMER reto, con su `nonce` viejo, y salía `google-failed`.
  *Parecía un defecto del producto y era el instrumento.* El stub se registra una vez y lee el token
  del reto en curso.
- ⚠️ **Un caso propio salió ROJO con el producto sano**: hacía `logout()` en medio y dejaba anónima la
  comprobación que necesitaba sesión.

#### Presupuesto

Payload con sesión **10.000 → 10.050** (medido **10.104** con dos rótulos, **10.012** tras podar).
⚠️ **La poda está medida (−92 B)**: se retiró el `link_intro` que explicaba para qué sirve vincular —
bajo el título «Cuentas vinculadas», el rótulo del botón ya lo dice. Los **12 B** que quedaban por
encima **no se pagan acortando el rótulo a algo peor**.

#### Y un test intermitente del OTRO carril, arreglado por segunda vez

`GuardianAuthorizationScreenTest::test_a_signed_in_parent_can_pick_one_of_their_own_minors` creaba al
firmante con `User::factory()` —`fake()->name()` en español— y aseveraba **por subcadena** que la
página no dice «Marcos». Es **la misma lección de `#337`, tercera aparición**, y la segunda en este
mismo fichero (la primera la arregló el carril de Google el mismo día, en `responsible()`).
▶ **Reproducido a voluntad** inyectando *«Marcos Colisión»*, arreglado fijando el nombre y
**verificado con control**: volviendo a inyectarlo, el caso se pone rojo otra vez.

### 21.4 · T8 — las condiciones al momento del contrato (en curso)

`[DECIDIDO owner, 2026-09-02]`, tres decisiones tomadas con la cifra y el coste delante:

1. **Aplica a las DOS altas**, no solo a la de Google: dejar el alta con contraseña con cuatro
   casillas y la de Google con una serían **dos posturas legales distintas para el mismo producto**.
2. **Se piden UNA vez, y otra vez cuando el texto cambia de versión** — *«igual que el waiver»*.
3. **A quien ya las aceptó no se le vuelve a pedir** al estrenar el versionado.

⚠️ **El descargo NO baja al checkout** (`[DECIDIDO owner]`, sobre la opción de «cero pantallas»):
§4 sigue en pie, y la pantalla de Google se queda con el nombre y **una** casilla.

#### El hallazgo que reencuadra la tanda, y es LEGAL

⚠️⚠️ **Medido antes de escribir nada: el embudo NO enseña las condiciones en ningún sitio.** Cero
enlaces a `legal.condiciones` en los ocho pasos del cajón, y **ningún correo las enlaza tampoco**.
Hoy solo aparecen en la casilla del alta ⇒ **un cliente que ya tiene cuenta compra sin que se le
muestren nunca.**

Eso es justo lo que la **LCGC (Ley 7/1998, art. 5)** pide evitar para que unas condiciones generales
queden incorporadas al contrato, y lo que el **TRLGDCU (RDL 1/2007, art. 97)** exige como información
precontractual. ▶ ***Mover la aceptación al checkout no relaja nada: cierra un hueco que existe hoy.***

⚠️ **Lo que SÍ está bien y no se toca**: el botón que cierra la compra dice «Pagar con tarjeta», que
cumple el art. 98.2 (la obligación de pago tiene que ser inequívoca en el propio botón).

#### 21.4.1 · T8·a — las condiciones, publicables por versión (`DECISIONES #348`)

**No se construyó maquinaria: se abrió la que había.** `LegalDocumentPublisher::publish($slug, …)` ya
era genérico y la acción del panel estaba limitada a `slug === 'waiver'`. Ahora la lista vive en
`LegalDocuments::PUBLISHABLE` y es **CERRADA**: publicar es irreversible, así que ofrecer el botón en
cualquier página del CMS sería regalar un acto sin vuelta atrás a quien solo quería corregir una
errata.

⚠️ **La privacidad NO entra, y no es un olvido**: el RGPD no pide que se «acepte» una política —el
art. 13 pide **informar**—, así que no hay ninguna aceptación que fechar contra una versión. Es el
CONTROL del caso que vigila la lista.

⚠️ Los textos de la acción se mudan a `admin.legal.publish.*` y dejan de decir «firmable»: el
descargo se FIRMA y las condiciones se ACEPTAN, y el único verbo que comparten es **publicar**.

**`Identity\Services\TermsAcceptance`** responde las dos preguntas —¿tiene que aceptar? y regístralo—
sobre la versión **publicada**, nunca sobre `Consent::CURRENT_VERSION`, que es una constante escrita a
mano y —medido— **no la lee nadie**: con ella, alguien edita el texto en el panel, se olvida de
subirla, y el producto afirma que el cliente aceptó un texto que nunca vio.

⚠️⚠️ **La regla de gracia va en UN sitio y se hace con una REGLA, no reescribiendo la fila.** Cambiar
el consentimiento viejo de `2026-05-23` a `v1·es` dejaría el registro afirmando que aceptó un
documento que **no existía cuando firmó**. *Una prueba no se edita para que la consulta salga más
corta.* ▶ Y **muere en la v2**: solo empata con la primera versión, así que al publicar una segunda
todo el mundo vuelve a pasar por la casilla.

⚠️ **El supuesto que asume esa decisión, dicho para que no se descubra tarde**: que el texto de la v1
sea el MISMO que aceptaron. Lo es mientras nadie edite `condiciones` entre hoy y su primera
publicación. **Si hay que retocarlo, se publica ANTES y se edita después.**

⚠️ **Sin ninguna versión publicada esto NO pide nada y la venta sigue**: el hueco falla hacia
invisible, como las claves de Google o el kit del cliente. Una instalación recién montada no puede
quedarse sin poder vender porque nadie haya pulsado «Publicar». Con caso, y con su CONTROL.

▶ **Un caso existente cambió de nombre porque su nombre pasó a ser mentira**:
`test_the_action_is_visible_only_on_the_waiver_page`. Sigue comprobando que la privacidad queda
fuera —que es lo que le da valor— y ahora cubre también las condiciones.

⚠️ **Paso manual al desplegar**: en cada instalación hay que **publicar la v1 de `condiciones`** desde
el panel. Hasta entonces no se pide nada, que es la conducta segura.

#### 21.4.2 · T8·b — el checkout pide lo que falta (`DECISIONES #349`)

Segunda mitad, y **va antes que la T8·c a propósito**: primero el checkout PIDE y solo después el alta
deja de pedir. Al revés habría una ventana en la que nadie acepta nada.

**Dos cosas, y ninguna es de la cesta**: las condiciones en su versión vigente y el teléfono si la
cuenta no lo tiene. ⚠️ Por eso **no viven en `CartPayload::rules()` ni en `CartRequest`**: ese esquema
lo comparte el **presupuesto**, y meterlas allí obligaría a aceptar las condiciones para ver un precio
— la lección de `#329` con el `EmailRequest` compartido, pagada antes de que doliera. `POST /orders`
estrena `CreateOrderRequest`, que repite `items` a sabiendas (`allOf` no vale con
`additionalProperties: false`: cada subesquema valida por su cuenta).

⚠️⚠️ **La autoridad es el SERVIDOR.** El cajón sabe qué pintar porque el contexto de cuenta le da una
PISTA, pero si la decisión viviera en la casilla se compraría sin aceptar nada **quitando un `input`
del DOM** — el defecto que `#400` documenta para el justificante.

#### La pista, y por qué el 422 también enciende el campo

`account-context` gana `terms_pending`, `terms_updated` y `phone_missing`. Va ahí porque es el mismo
tipo de hecho que `waiver.pending` —qué le debe esta cuenta antes de contratar— y porque **el servidor
lo siembra al pintar la página**: el embudo lo tiene sin una petición más.

⚠️⚠️ **Pero la pista se sembró al CARGAR.** Si mientras el cliente llenaba el carrito alguien publicó
una versión nueva, decía `false` y el servidor responde 422: sin una segunda voz, el error apuntaría a
**una casilla que no está en pantalla**. Por eso cada campo se enciende por dos caminos —la pista y el
«no» del servidor— y eso vive en `buyer-due.js`, con su `node --test`.

⚠️ **`terms_pending` y `terms_updated` son dos hechos**: aquél decide si se PIDE y éste qué se DICE.
`[owner]`: *«se pide de nuevo diciendo que las condiciones se han actualizado»* — y decírselo a quien
nunca las aceptó sería contarle una historia que no es la suya. **«Actualizadas» solo lo dice la
pista, nunca el error**: el 422 sabe que faltan, no por qué.

#### Un «no» de éstos NO devuelve al carrito

Es la única excepción del desenlace: hasta hoy **cualquier** 422 al confirmar mandaba al paso 4. El
campo de éstos está en la pantalla de PAGAR, y mandar al cliente dos pantallas atrás para arreglar
algo que se teclea aquí lo deja sin la corrección a la vista. Se distingue **por el CAMPO y no por el
código**: los dos 422 del checkout comparten `validation_failed`.

#### La UI: ni una pieza nueva

Reutiliza `.eventfields` (el patrón del embudo para campos extra) y `.check` (la casilla legal de
siempre). Una tarjeta propia habría sido un cuarto tratamiento para lo mismo. La separación copia la
receta de `.purchase__foot` —regla fina + 18/16— porque la relación es la misma.

⚠️ **No hay botón muerto**, y la regla estaba escrita en `foot.js`: los campos obligatorios *«se
validan AL PULSAR, con un aviso que dice qué falta, en vez de con un botón muerto que no lo
explica»*.

⚠️⚠️ **Y la CAPTURA destapó dos cosas que la suite no podía ver**, medidas en las cuatro variantes:
 1. **El aviso de arriba y la línea legal de abajo decían casi lo mismo a 30 px** («léelas y
    acéptalas» / «al reservar las aceptas»). Con casilla, el enlace va DENTRO de ella y esa línea
    sobra: se pinta **solo cuando no hay casilla**. La obligación se cumple igual — el enlace está
    siempre en la pantalla, y donde de verdad hay que leerlo.
 2. **Las dos peticiones se separaban 14 px** y sus dos textos de apoyo —los dos en gris y del mismo
    cuerpo— se leían como un párrafo soso. Van a **18**, que es el mismo aire con el que el bloque se
    separa del resumen: se repite el número que ya dice «aquí empieza otra cosa».
 ▶ Y un `:first-child { margin-top: 0 }`, porque cuando solo faltan las condiciones esos 18 se
 sumaban al aire del bloque y dejaban el control flotando a 34 px de su regla.

**Medido en las cuatro variantes** (ambos · solo teléfono · solo condiciones · nada): 32/18 de ritmo,
**0 px de desborde** y —lo que importa legalmente— **con «nada» pendiente el enlace sigue ahí**.

#### Lo que las guardas de arquitectura obligaron a hacer mejor

⚠️⚠️ **`ApiBoundariesTest` puso en rojo el `save()` del teléfono en el controlador**, y con razón: es
una escritura de dominio en la capa HTTP. Nació `Identity\Services\CheckoutDuties` — y al escribirlo
se vio que la pregunta *«¿qué le falta a esta cuenta?»* **se estaba respondiendo en dos sitios**, cada
uno con su `trim($user->phone) === ''`. Ahora es una, y la usan el contexto y el controlador.

⚠️ **`SidebarComponentBudgetTest` puso en rojo la sección**, y la respuesta **no fue subir el techo**:
la decisión se fue a `buyer-due.js`. Medido, la extracción bajó de 448 a 444 líneas; el techo sube a
444 con eso escrito. *Subir un techo después de extraer no es lo mismo que subirlo en vez de extraer.*

**Presupuestos**: chunk **274 → 277** (medido 276,03; ⚠️ se puso primero en 276 con una medición tomada ANTES del último retoque de la plantilla y salió rojo en el push por **30 bytes** — *un techo medido antes del último cambio no describe el árbol que se empuja*) — dos KiB de marcado y cableado para una
pantalla entera. El payload no se mueve.

**Verificación**: suite **4056 · 25.868** · 8 casos de API (incluido el CONTROL de que una cuenta con
teléfono no se lo puede pisar desde aquí) · `buyer-due.test.js` + los dos de `pay.js` con su control ·
**`purchase:verify-oversell` y `redsys:verify-concurrency` con 16 procesos sobre MySQL real** (toca
`OrdersController`, que está en el `CRITICAL_RE`) · cuatro variantes medidas en navegador.

#### 21.4.3 · T8·c — las dos altas dejan de pedir (`DECISIONES #350`)

`[DECIDIDO owner, 2026-09-02]`: **aplica a las DOS altas**, la de contraseña y la de Google. Dejar
una con cuatro casillas y otra con una serían **dos posturas legales distintas para el mismo
producto**.

**Cada casilla se va por un motivo distinto, y no son intercambiables**:

| Casilla | Por qué se va | A dónde va |
|---|---|---|
| Privacidad | El art. 13 pide **informar**, no que se acepte; la base legal de una reserva es el contrato (art. 6.1.b) | A un aviso con enlace visible. **El rastro se queda**: `privacy_accepted_at` y su fila de `consents` |
| Condiciones | Se aceptan en el **momento del contrato** (LCGC art. 5 · TRLGDCU art. 97) | Al checkout, que es lo que la T8·b construyó |
| Marketing | El art. 7.3 exige que retirarlo sea **tan fácil como darlo**, y una casilla del alta no da eso | Al interruptor de «Mi cuenta → Privacidad» (T3) |

##### ❗❗❗ El hueco que esto cierra, y por qué quitar la casilla NO bastaba

⚠️⚠️ **Medido ANTES de tocar una línea, con la v1 de `condiciones` publicada: una cuenta recién
creada salía con `TermsAcceptance::pendingFor() === false`.** O sea que **el checkout no le pedía las
condiciones a ningún cliente nuevo** y la T8·b quedaba desactivada por su propia alta.

El mecanismo: la **regla de gracia** de §21.4.1 da por aceptada la primera versión a quien tenga una
aceptación anterior al versionado, y eso lo detecta porque `numberOf()` devuelve `null` para una
etiqueta que no es `vN·xx`. El alta escribía su fila con `version = Consent::CURRENT_VERSION`, que es
**una fecha** (`2026-05-23`). *La regla escrita para indultar a diecinueve clientes viejos indultaba a
todos los futuros.*

▶ Por eso la T8·c no es marcado: **`SelfSignup` y `GoogleSignup` dejan de escribir la fila `terms` y
de sellar `terms_accepted_at`**. Con eso, `Consent::TYPE_TERMS` tiene **un solo escritor** en todo el
producto —`TermsAcceptance::accept()`—, que es donde debía estar desde el principio.

⚠️ **Y las dos altas quedan alineadas con la de mostrador**, que ya era así: `CustomerRegistrar`
escribe privacidad y nunca condiciones (verificado). Lo que se corrigió no fue una decisión nueva:
fue una divergencia entre puertas.

⚠️ **Caso con su CONTROL** (`AuthRegistrationTest`): la cuenta nueva SÍ las debe **y** la gracia sigue
viva para quien aceptó antes del versionado. Sin ese control, el caso pasaría igual habiendo roto el
indulto de `#348` — que es una decisión del owner, no un efecto colateral.

##### El alta con Google se queda en dos cosas

**El nombre y el descargo.** Pierde también el **teléfono**, que pide el checkout: la cuenta nace sin
él, y `CheckoutDuties` lo reclama antes de crear el primer pedido.

⚠️⚠️ **Eso REENCUADRA la desviación de la Q6 que §19.6 dejó abierta**, y el owner la ha resuelto.
Aquella pantalla se seguía pintando sin descargo que firmar **porque también recogía teléfono y
condiciones**; hoy esas dos ya no están, así que la razón vieja ha desaparecido.
`[DECIDIDO owner, 2026-09-02]`: **se sigue pintando, por el NOMBRE** — Google devuelve a veces «Ana
G.» y ese nombre viaja a la reserva y a la firma del descargo, así que ésta es la única ocasión de
corregirlo antes de que se use. ▶ Y la alternativa costaba construir una rama de alta directa que
**ninguna instalación viva ejercita**: código nuevo sin uso real es peor red que una pantalla probada.

##### El texto de privacidad deja de decir «acepto»

`[DECIDIDO owner, 2026-09-02]`. Sin casilla, «He leído y acepto la política de privacidad» **afirma una
aceptación que la pantalla no recoge** — y un texto que dice «acepto» sin control que pulsar no crea
consentimiento: solo despista. Pasa a *«Al crear tu cuenta tratamos tus datos según nuestra política
de privacidad»* (es/en/fr), que es lo que el art. 13 pide.

⚠️ **La clave se renombra a `register.privacy_notice`**, y eso no es cosmético: una clave llamada
`accept_privacy` con un texto que no dice «acepto» invita a que el siguiente le devuelva el verbo por
parecer incoherente. El nombre tiene que decir qué es.

##### ❗❗ Un defecto que solo vio la CAPTURA, y que la T8·c convertía en carga

⚠️⚠️ **El `<a>` de ese aviso se pintaba en `rgb(98,106,114)` — el mismo color EXACTO que el párrafo —,
sin subrayado y con peso 400.** Texto plano, con una zona pulsable invisible de 336×32. Medido en
navegador a 420 px.

▶ **Venía de `#343`** (el aviso ya se pintaba así en la pantalla de Google) y nadie lo vio porque allí
era un aviso más. **La T8·c lo convierte en el único sitio donde las dos altas enseñan la política**, y
el argumento de §7.1 —*el RGPD pide informar, y el enlace va visible*— se apoya entero en que se vea.
*Un enlace que no se distingue no informa.*

▶ Arreglado con los valores que `.check span a` ya usaba —no se inventa un cuarto tratamiento para un
enlace legal que hasta ayer vivía dentro de una casilla— y **remedido**: `rgb(26,169,222)` con
subrayado, contra el `rgb(98,106,114)` del párrafo. Guarda: **`PrivacyNoticeIsVisibleTest`**.

##### El contrato público cambia

- **`RegisterRequest`**: fuera `accept_privacy`, `accept_terms` y `marketing`, de `required` y de
  `properties`. `required` queda en `[name, email, phone, password]`.
- **`GoogleSignupRequest`**: fuera `phone` y `accept_terms`. `required` queda en `[name]`.

⚠️ Los dos son `additionalProperties: false`, así que **mandarlos hoy es un 422 por ESQUEMA**, no por
lógica — un «no» que no habla de ningún campo de la pantalla. Por eso `register.js` y `account/google.js`
dejan de ponerlos en el cuerpo, con caso que lo fija por `in` (lo que se prohíbe es que la CLAVE exista).

##### El manifiesto congelado, regenerado a propósito

Dos entradas y solo dos (`git diff`: 2 líneas):
- **el árbol del alta** — salen tres `label.check` (dos con `<a>` y la `.check--opt` del marketing),
  entra un `p.form__hint` con el enlace;
- **el banner de errores** — **cuatro** `<li>` en vez de seis, porque quedan cuatro campos
  obligatorios. ⚠️ Su precondición pide «más de tres» y hoy son exactamente cuatro: si alguien quita
  otro obligatorio, ese caso se pondrá rojo **por su precondición**, que es lo correcto — con tres
  avisos deja de probar lo que dice probar.

##### La pantalla, vista

Comprobada en navegador a 420 px interceptando `GET auth/google/pending` (lo que se mira es el
PINTADO; el canje ya tiene sus casos de servidor): **correo fijo → nombre editable con «Ana G.» →
aviso de privacidad con su enlace → casilla del descargo → botón**. Dos campos donde había cuatro,
**0 px de desborde**, y el copy de `#345` —*«Solo falta esto para crear tu cuenta»*— sigue siendo
verdad, que era la condición con la que se escribió.

⚠️ **Y una trampa de instrumento pagada aquí mismo**: la primera pasada dijo «no hay casilla del
descargo» **con el producto sano**. La casilla llega por una SEGUNDA petición (`GET /legal/waiver`) y
la sonda esperaba solo al formulario. *Esperar a que aparezca el contenedor no es esperar a que
aparezca lo que se quiere medir* — y el falso negativo era creíble, porque «sin descargo» es un estado
real del producto. Se espera a la casilla, con tope explícito para que la ausencia real siga siendo
detectable.

##### Verificación

Suite **4.057 · 25.866** · `node --test` de `register.js` y `account/google.js` (43) ·
**`scripts/mutar-alta-sin-casillas.sh`: 6/6 muerden**, incluidas las dos direcciones (devolver la fila
`terms` a cada alta **y** romper la regla de gracia).

⚠️⚠️ **Y una trampa del propio arnés, cazada por una guarda de dos líneas**: la primera versión
interpolaba los patrones dentro del `python3 -c`, y el `$` de una variable PHP (`$now`) llegaba
escapado a medias — el patrón no casaba, el fichero no cambiaba y **el veredicto habría dicho «no
muerde» sobre una mutación que nunca se aplicó**. Los patrones viajan por `argv`, y el arnés comprueba
que el fichero CAMBIÓ antes de creerse un veredicto. *Un «3 de 6» con tres mutaciones que no se
aplicaron es una cifra creíble y falsa.*

#### 21.4.4 · T8·d — el botón de Google, encima (`DECISIONES #350`)

`[DECIDIDO owner, 2026-09-02]`, sobre las dos opciones: **encima del formulario, con un separador
«o»**. Iba debajo desde `#343`, con su motivo escrito en `LoginZone.vue` («quien ya tiene contraseña la
teclea, y quien no, lee hasta abajo»); **revertirlo es deliberado** y el comentario viejo se retiró
para que nadie lo lea como la razón vigente.

⚠️⚠️ **Va DENTRO de `LoginForm.vue` y `RegisterForm.vue`, no colgado de sus padres, y no es un detalle
de implementación**: *«encima del formulario» no es «encima de la pantalla»*. La cabecera
(`.auth__head`) vive dentro de esos dos componentes, así que dejarlo en el padre lo habría puesto
encima del TÍTULO. Los tres consumidores —el paso 5 del embudo y las dos zonas de la cuenta— pasan
ahora `:google-url` y no colocan nada.

⚠️⚠️ **El separador vive dentro del `v-if` del botón**, en `GoogleButton.vue`, y ésa es la propiedad
que importa: escribiéndolo en los dos formularios haría falta repetir la condición `href` en cada uno,
y el día que alguien tocara una y no la otra, **una instalación sin claves de Google se quedaría con
una raya y un «o» separando el formulario de nada** — visible solo en la instalación que no lo usa.

⚠️ **Las dos rayas son pseudo-elementos** (`::before`/`::after`): dos `<span>` vacíos añadirían al
árbol nodos que no dicen nada y que un lector de pantalla tendría que saltarse.

⚠️ **`.auth__or` SÍ usa la escala del tema** (`--sp-*`, `--fs-13`, `--line`), al revés que el botón:
lo que la guía de Google congela es su botón, no lo que le pongamos al lado. Un cliente que airee el
cajón con `--sp-unit` mueve este separador, y hace bien.

##### La guarda, y por qué hacía falta otra

**`GoogleSignInPlacementTest`**. ⚠️⚠️ **`#345` midió que el manifiesto congelado de
`SidebarDomContractTest` tiene CERO ocurrencias de «google»** —sus fixtures no pasan la URL y el
componente es un `v-if="href"`—, así que **mover esta pieza de sitio no ponía en rojo absolutamente
nada**. `GoogleButtonBrandingTest` cubre su marca y su piel; su COLOCACIÓN no la cubría nadie.

Fija las **dos** fronteras y no una: «después de la cabecera» lo cumple también un botón al final del
fichero, y «antes del formulario» lo cumpliría uno encima del título. La decisión es que esté **en
medio**. Y fija que el «o» no pueda pintarse solo.

⚠️ **Lo que un test de fichero NO puede ver es el orden EN PANTALLA**: un `order:` de flex o un
`position` lo cambiarían sin tocar una línea del `<template>`. Eso lo mide
`storage/app/sonda-google-encima.mjs` en navegador, con su CONTROL (la comparación inversa tiene que
salir falsa) y con la comprobación de `document.fonts.size > 0` para no caer en la captura sin letras
de `#335`.

##### Presupuesto: el techo BAJA, y es la primera vez

Chunk medido **274,47 KiB** contra los 276,03 de `#349`: **−1,56 KiB**. La T8·c retira tres casillas
con su marcado, sus `v-model` en dos consumidores y tres campos del cuerpo; la T8·d suma el separador
y el cableado en los dos formularios.

⚠️⚠️ **El techo baja a 275 a propósito, en vez de dejarlo en 277.** Un trinquete que solo sube deja de
vigilar en cuanto alguien retira código: con 277 quedarían **2,53 KiB** de margen regalado, o sea que
las dos próximas subidas entrarían sin que nadie las decidiera. *Un presupuesto que no se ajusta
cuando el gasto baja no es un presupuesto, es un techo histórico.*

##### Verificación

Suite **4.064 · 25.919** · **sonda de navegador 18/18** en `/login` y `/registro` a 420 px (cabecera →
botón → «o» → formulario, con las tres distancias medidas y el área táctil en 44) · capturas de las
dos pantallas · **`scripts/mutar-google-encima.sh`: 8/8 muerden** (devolverlo abajo, subirlo encima del
título, quitarle el separador, huerfanar el «o», devolver la privacidad a casilla y las tres formas de
apagar el enlace legal).

#### 21.4.5 · El aire del bloque de cuentas vinculadas (`DECISIONES #350`)

**Lo vio el owner probando el producto**: *«falta margen entre lo de vincular con Google y la sección
de arriba»*. Medido en navegador antes de tocar nada, con sesión y sin vínculos: **0 px** entre el
botón «Cerrar sesión en los demás dispositivos» y el título «Cuentas vinculadas», y **3 px** entre ese
título y el botón de Google.

⚠️⚠️ **La causa no estaba en «Sesiones»: estaba en el botón.** `.auth__google` llevaba
`margin-top: var(--sp-3)` —**tres píxeles**, que no separan de nada— y ese margen **viajaba con él a
los cuatro sitios donde se pinta**. En los dos formularios de auth colaba porque el aire real lo daba
`.auth__head`; aquí no había nadie que lo diera. *El aire de una pieza lo decide quien la coloca, no
ella*: el margen se retira y cada sitio declara el suyo.

⚠️ **Y «Sesiones» es la única sección del área de cuenta que NO puede ser una tarjeta**: el bloque de
cuentas vinculadas vive **dentro del mismo formulario** que cerrar sesión en otros dispositivos,
porque reutiliza su campo de contraseña (§21.3). Como no es tarjeta, la rejilla no lo separa — así que
su aire lo declara él, en `.account__linked`. **20 y 12**, que son los dos números que el cajón ya
usaba (`.form__checks` cierra con 20 y separa sus controles con 12).

**Medido después**: 0 → **20 px** y 3 → **12 px**; y las dos pantallas de auth siguen bien (el botón
pasa de 21 a 18 px bajo la cabecera, que es el margen de `.auth__head`), remedidas con la sonda de la
T8·d — **18/18**.

