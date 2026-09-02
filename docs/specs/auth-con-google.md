# [SPEC] Entrar y registrarse con Google

> Estado: 🟦 **LA T1 Y LA T2 ESTÁN EN EL ÁRBOL** (2026-09-02, `DECISIONES #342` y `#343`) ·
> Abierta: 2026-09-02 · Autor: agente
> ▶ **Lo ejecutado, con lo que enseñó, está en §18 (T1) y §19 (T2) — y va ANTES que las tandas de
> §16.** Con la T2 el camino se cierra de punta a punta; quedan la **T3** (lo irreversible y el
> marketing) y el **OJO del owner** (guion en `VERIFICACION-E2E-CAJON.md` §5.octies).
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
| **T3 · Lo irreversible y el marketing** | Ticket de re-autenticación (esquema **nuevo**) para las **cuatro** superficies · **desvincular** · interruptor de marketing **+ su registro de retirada** y el endpoint | Cierra los huecos legales |
| **T4 · El OJO del owner** | Guion de navegador con los tres caminos y con P12 | |

⚠️ **T2→T3 es dependencia dura**: cada día que la T2 esté sin la T3 crea cuentas sin autoservicio
para las cuatro acciones (art. 12.2).

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
- **El OJO del owner**: guion en `VERIFICACION-E2E-CAJON.md` §5.octies. Necesita el cliente de OAuth
  de DESARROLLO — el de producción no sirve, y es correcto que no sirva.
- ❗ **UNA DESVIACIÓN DE LA LETRA DE LA Q6, dicha para que la decidas tú.** §7 dice que en modo
  `externo` o sin versión publicada **no hay pantalla** y se entra directo; aquí la pantalla se pinta
  igualmente, con teléfono y condiciones. El motivo: la Q6 se contestó dando por hecho que la
  pantalla existía **solo** para el descargo, y no es así — también recoge el **teléfono**, que tú
  llamaste *«imprescindible para las reservas»*, y la **aceptación de condiciones**, que es
  contractual. Entrar directo en esa instalación crearía cuentas sin ninguna de las dos, que es
  justo la consecuencia que §7 avisa.
  ▶ **Hoy no muerde**: `playjump.es` está en modo `interno` con versión publicada, así que la rama no
  se ejercita en ninguna instalación viva. Si prefieres la letra de la Q6, es un `if` en el retorno.
