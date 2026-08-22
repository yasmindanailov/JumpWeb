# [SPEC] La AUTH dentro del cajón — y la retirada del modal de la cabecera

> Estado: diseño (🟦 **revisada** el 2026-08-23 — ver §7; falta el visto bueno final para pasar a ✅) ·
> Última actualización: 2026-08-23 · **A1–A4 EJECUTADOS** (§8; A4 recortado por dependencia) ·
> Verificado contra código: 2026-08-23 (modal, componentes Livewire de auth, rutas puerta,
> zonas del área, endpoints de `/api/v1/auth/*`, el payload del montaje y los tests que los cubren) ·
> Se invalida si: se retiran los componentes `Livewire\Auth\*`, cambia `Http\Sidebar\AccountDoor`
> o el modelo de zonas de `resources/js/sidebar/account/navigation.js`.
> Decisión asociada: `DECISIONES` **#122**.

Este es el **último trozo** de `DECISIONES #66` («la gestión del cliente vive en UN sitio»). Tras la
tanda 3 del área de cliente (`#120(u)`) quedaban dos sitios; al cerrar esta spec queda uno.

---

## 1. Contexto y problema

### 1.1 Dónde vive hoy la auth (medido el 2026-08-22)

| Pieza | Medida | Comando |
|---|---|---|
| Componentes Livewire de auth | **4 clases, 427 líneas** (`Login` 84 · `Register` 225 · `ForgotPassword` 46 · `ResetPassword` 72) | `wc -l app/Livewire/Auth/*.php` |
| Sus plantillas | **273 líneas** (login 54 · register 154 · forgot 37 · reset 28) | `wc -l resources/views/livewire/auth/*.blade.php` |
| El modal | el bloque `@guest` de `resources/views/components/layout.blade.php`, que monta los TRES | — |
| Quién lo abre | **5 puntos** fuera de los propios formularios: `site/nav` (escritorio y cajón móvil), `livewire/site/account-context` (×2) y `sections/AccountSection.vue` | `grep -rn '\$store.auth' resources/` |
| Sus puertas por ruta | **3**: `registro`, `login`, `password.request` → `HomeController` compone `authModal` | `grep -n authModal app/Http/Controllers/HomeController.php` |

### 1.2 Lo que el cajón YA sabe hacer, y lo único que le falta

El paso 5 del embudo **entra y da de alta** desde 4.4a·2 y 4.4b·1: `login.js`, `register.js`,
`stores/auth.js`, `steps/LoginForm.vue` y `steps/RegisterForm.vue`, contra
`POST /api/v1/auth/login` y `POST /api/v1/auth/register`. El anti-bot tiene su widget propio
desde 4.4b·2 (`#108`).

▶ **Del servidor no falta NADA**: `PasswordRecoveryController` publica
`POST /api/v1/auth/password/forgot` y `POST /api/v1/auth/password/reset` desde Fase 3 · paso 3c, y
las reglas —limitador por IP, rotación del `remember_token`, no-enumeración— viven en
`Identity\Services\PasswordRecovery`, que es **el mismo servicio** que consume el modal de hoy.

▶ Y `stores/auth.js` lo dejó anotado al nacer: «este es el dominio que el ÁREA DE CLIENTE va a
compartir». Lo único que el cajón no sabe hacer es **recuperar la contraseña**.

### 1.3 ⚠️ Tres cosas que este trabajo desata, y ninguna es «pintar»

1. **Mueren dos paridades de árbol** — pero **no enteras**. `SidebarLoginParityTest` y
   `SidebarRegisterParityTest` suman 14 casos y solo **6** comparan el cajón con el modo `embedded`
   de los componentes Livewire; los otros **8** tienen sujeto propio y sobreviven (§4.7.bis).
   `DECISIONES #112(f)` lo dejó dicho: el modo `embedded` «muere solo cuando el área de cliente
   rehaga la auth dentro del cajón», que es ahora. Comparar entre superficies **muere con la
   superficie** (`CONVENCIONES §3.quater`); afirmar del contrato, no.
2. **`INVARIANTES` SEC-06 CITA dos tests que se van.** Su columna de verificación nombra `LoginTest`
   (limitador solo-IP anti-spraying) y `ForgotPasswordTest` (no-enumeración), que son tests **del
   componente Livewire**. Re-apuntar esa cita va **en el mismo commit** que el borrado: es
   literalmente lo que `DECISIONES #121` acaba de pagar.
3. **Un HUECO medido, y hay que cerrarlo ANTES de borrar.** El prop `authModal` del layout no solo
   abre el modal: también decide el `<meta name="robots">`, así que hoy `/login`, `/registro` y
   `/recuperar-contrasena` se sirven **`noindex, nofollow`**. **Ningún test lo asevera** (`grep -rn
   noindex tests/` da un solo resultado, y es del 503 de mantenimiento). Retirar el prop sin cubrir
   esto entrega tres URLs de auth al índice de Google sin que nada avise — la familia de `#89`/`#93`:
   *un dato que viaja al cliente cuyo único test conduce la superficie vieja no está fijado por
   nadie*.

---

## 2. Objetivo

1. **Entrar, darse de alta y recuperar la contraseña se hacen dentro del cajón**, en la sección de
   cuenta, sin abrir ningún modal.
2. **El modal y sus tres componentes Livewire se retiran**, y con ellos el modo `embedded`.
3. **Las tres rutas sobreviven como PUERTA** —misma doctrina que `#120(u)`—, y no es opcional:
   `route('login')` es el destino al que Laravel redirige desde el middleware `auth`.
4. **El paso 5 del embudo gana «he olvidado mi contraseña»** con vuelta a la compra (owner,
   2026-08-22): hoy quien compra y no recuerda su contraseña tiene que **salir del cajón**, y la
   cesta que estaba viendo se le queda detrás.
5. **Cero regresión**: SEC-06 sigue verificada, las tres URLs siguen `noindex`, y `account-context`
   sigue funcionando para el invitado.

### Explícitamente FUERA de alcance

- **`/restablecer-contrasena/{token}` y `/email/verificar*` siguen siendo PÁGINAS.** Se llega a ellas
  desde un correo, con un token en la URL, y **el cajón no es direccionable**
  (`specs/area-cliente.md` §3.4). `Livewire\Auth\ResetPassword` y su plantilla **no se tocan**.
- **`account-context` sigue en Livewire.** Es la otra ficha de `DEUDA.md` y no entra aquí: lo que
  cambia son sus dos `@click`, no su motor.
- **Emisión de tokens Bearer** (`POST auth/tokens`): Fase 6, `DECISIONES #29`.
- **El «modo sin JavaScript»**: no existe hoy y esta spec no lo inventa. El `href` de cada puerta es
  la degradación, igual que en `#117`.

---

## 3. Opciones consideradas

### 3.1 ¿Sección nueva o zonas de la sección de cuenta?

- **(a) Una tercera sección `auth`**, hermana de la compra y de la cuenta. Descartada: duplicaría
  `Shell.vue`, el botón «Volver» y el título, y necesitaría su propio store de navegación con su
  propia pila — para tres pantallas que son del **mismo dominio** que las otras seis.
- **(b) Tres ZONAS más de la sección de cuenta.** ✅ **ELEGIDA.** La pila de retorno ya existe y
  regala `login ↔ registro ↔ recuperar` sin escribir navegación nueva; añadir una zona es **una línea
  en `ZONES` y su rótulo**, que es exactamente lo que `specs/area-cliente.md` §4.2 prometió y lo que
  las cinco zonas de las tandas 1 y 2 ya demostraron.
  ⚠️ Su consecuencia hay que decirla: **la sección de cuenta deja de ser «solo con sesión»**.

### 3.2 ¿Una zona con pestañas, o dos zonas?

- **(a) Una zona `SIGN_IN` con dos pestañas**, como el paso 5 del embudo. Descartada: obliga a que la
  puerta traiga **dos señales** (zona + pestaña) en vez de una, y a que `AccountDoor` deje de ser un
  mapa `ruta → zona`.
- **(b) `LOGIN` y `REGISTER` como zonas distintas**, y las pestañas navegan entre ellas con
  `go(zona)`. ✅ **ELEGIDA.** El mapa ruta→zona sigue siendo uno y plano, y la regla de miga de pan de
  la pila impide que alternar entre pestañas haga crecer la historia (`navigation.js`, `go()`).

### 3.3 ¿Dónde aterriza quien entra desde la cabecera? — **[DECIDIDO] 2026-08-22, owner**

**El índice de Mi cuenta** (zona `HOME`). Descartadas: volver al embudo (deja al cliente en un
catálogo que no pidió si la cesta está vacía) y cerrar el cajón sin más (desaprovecha que el cajón ya
sabe enseñar la cuenta).

⚠️⚠️ **Y el CÓMO no es libre: lo fija una medida.** Los textos del área **viajan en el HTML solo con
sesión** —el montaje los envuelve en `auth()->check()`, igual que `locales`—, y `Sidebar.vue` los
recibe como props estáticas del `data-boot` de **esa** carga de página. Quien consigue sesión DENTRO
del cajón no recarga, así que aterrizaría en el índice con **el título y las seis entradas en
blanco**: `i18n.js::t()` devuelve `''` cuando falta la clave, que es exactamente el fallo que
`account/navigation.js` documenta («se pintaría con el título en blanco y nada avisaría»).

▶ **[DECIDIDO] 2026-08-23, owner: al conseguir sesión, el cajón NAVEGA a `route('account')`.** Esa
ruta ya es una puerta: la página se recarga **con sesión**, el payload trae los textos y el cajón
**nace abierto en el índice**. El cliente ve lo que §3.3 promete, con **cero API nueva y cero bytes**
en las demás páginas, y por un camino ya probado.
▶ Descartada: mandar el grupo `account` a todo el mundo. Cuesta **~660 B en el HTML de cada página
pública y para cada visitante anónimo** —para pintar rótulos que casi ninguno verá— y va contra la
poda que el presupuesto del montaje existe para vigilar, hoy a **92 B** de su techo.
⚠️ **La recarga no es una regresión**: es lo que hace hoy el modal (`redirectIntended('/')`) y lo que
ya hace el cajón al cerrarse tras un login embebido (`authChanged`). La cesta sobrevive: vive en
`localStorage` con su dueño dentro.

⚠️ **Si el alta NO consiguió sesión** —verificación por correo, que es el caso normal del alta suelta
(§4.3)— **no se navega a ninguna parte**: la pantalla es «revisa tu correo», que no necesita ningún
texto del grupo `account`.

### 3.4 ¿El embudo ofrece «he olvidado mi contraseña»? — **[DECIDIDO] 2026-08-22, owner**

**Sí, con vuelta a la compra.** Hoy no lo ofrece: en el Blade era una rama `@unless ($embedded)`, así
que el cliente que está comprando **no tiene salida** sin abandonar el cajón. El enlace lleva a la
zona `FORGOT` y «volver» devuelve al paso 5 **con la cesta intacta**.

---

## 4. Diseño elegido

### 4.1 Tres zonas más — y la guarda de alcanzabilidad cambia de FORMA

`resources/js/sidebar/account/navigation.js` gana `ZONES.LOGIN`, `ZONES.REGISTER` y `ZONES.FORGOT`
con sus rótulos (`account.login.title`, `account.register.title`, `account.forgot.title`, que **ya
existen en `lang/`**).

⚠️⚠️ **`HOME_ENTRIES` NO las lista, y eso rompe la guarda que existe hoy.** `navigation.test.js`
exige que *toda* zona esté en `HOME_ENTRIES` porque «dentro del cajón no hay URL, así que el índice es
la única puerta». **Con esta spec deja de ser cierto**: a las tres zonas de auth se llega por RUTA y
entre sí, y ninguna puede estar en el índice —el índice lo ve quien ya tiene sesión—.

▶ La guarda no se relaja: **cambia de forma**. Se declara `GUEST_ZONES` (futuro) como segunda puerta
declarada, y el caso pasa a exigir que **toda zona esté en `HOME_ENTRIES` o en `GUEST_ZONES`**, más
un caso nuevo: **toda zona de `GUEST_ZONES` tiene una ruta que la abre** en
`Http\Sidebar\AccountDoor`. Sin ese segundo caso, «alcanzable» se convertiría en una lista de
excepciones donde meter cualquier cosa, que es justo lo contrario de lo que la guarda hace.

### 4.2 El módulo plano `forgot.js` y el estado

- `resources/js/sidebar/forgot.js` (futuro), espejo de `login.js`: compone la petición y **traduce el
  desenlace**. Su regla central es la anti-enumeración de SEC-06: **la API responde `202` exista o no
  la cuenta**, así que la pantalla «revisa tu correo» es **la misma en todos los casos**; solo el
  `429` es distinto, y su texto es `auth.throttle` con `retry_after` — el **mismo** que ya usa el
  login, del grupo `auth` que el montaje ya inyecta.
  ⚠️ **`PasswordRecoveryController` es indistinguible a propósito** (`CONVENCIONES §3.quater`,
  trampa 4): mutar una sola rama de esa propiedad da un mutante equivalente por diseño. Su caso se
  escribe cruzando **las dos** respuestas.
- `stores/auth.js` gana el estado del tercer formulario (`forgotSent`, `forgotError`) y la acción que
  lo envía, reutilizando `form.email`. ⚠️ **`reset()` tiene que limpiar también lo nuevo**: es el
  método que garantiza que la contraseña —y ahora el correo— no sobrevivan a un cambio de pantalla en
  una tablet compartida.

### 4.3 Los componentes

| Fichero | Qué pinta |
|---|---|
| `resources/js/sidebar/account/zones/LoginZone.vue` (futuro) | Pestañas + `steps/LoginForm.vue` + enlace a `FORGOT` |
| `resources/js/sidebar/account/zones/RegisterZone.vue` (futuro) | Pestañas + `steps/RegisterForm.vue` |
| `resources/js/sidebar/account/zones/ForgotZone.vue` (futuro) | Un campo, o la pantalla «revisa tu correo» |

⚠️ **Los dos formularios se REUTILIZAN, no se copian**: `steps/LoginForm.vue` y
`steps/RegisterForm.vue` son los del paso 5 y ya están probados contra el servidor.
`LoginForm` gana **un enlace opcional** a «recuperar», que el paso 5 también emite (§3.4).

⚠️⚠️ **Pero reutilizar el alta «tal cual» convertiría el alta suelta en PAY-FIRST, y eso es un
cambio de política que nadie pidió.** `register.js::runRegister()` manda **`context: 'purchase'`
quemado**, y el servidor decide con ese campo: `AuthRegistrationController::register` no envía
verificación cuando el alta viene de la compra (`notifyByEmail: ! $inPurchase`) e **inicia sesión**.
Con eso, darse de alta desde la cabecera **no mandaría el correo de verificación** y el «revisa tu
correo» de §3.3 solo aparecería cuando actuara el señuelo.
▶ **`context` pasa a ser PARÁMETRO** y lo fija quien invoca: el paso 5 manda `purchase`, la zona
`REGISTER` manda `standalone` —el contexto que hoy usa el modal—.

> ✅ **HECHO en A3 (2026-08-23).** El default quedó en **`standalone`**, y el criterio es de seguridad:
> quien olvide pasarlo en el embudo verá al cliente parado en «revisa tu correo» —visible, y se
> arregla—, mientras que el default contrario habría saltado la verificación de correo **en silencio**.
> Es además el mismo que aplica el servidor cuando el campo no viaja.
> ▶ **Y abrió un eslabón que ningún test veía**: que el EMBUDO declare `purchase`. El módulo tiene su
> caso del default y el store el suyo del paso a través, pero el de arriba no lo miraba nadie — la
> familia de `#117`/`#118`. Lo cierra `SidebarSignupContextTest`, medido mutando los dos extremos.
> ▶ Verificado además **sobre el chunk construido**: `runRegister` defaultea a la constante de
> `standalone` y la llamada del embudo lleva la de `purchase`. El fuente puede mentir sobre lo que se
> sirve; el artefacto que descarga el navegador, no.

⚠️ **El techo de componentes es 40 líneas de código** (`SidebarComponentBudgetTest`).
`AccountSection.vue` está hoy en **21** y las tres zonas le suman tres `import` → **24**. Cabe. Si
algo aprieta, la pregunta es qué sobra ahí, no cuánto subir el techo (`#120(r)`).

### 4.4 Las puertas: un solo mapa ruta→zona

`Http\Sidebar\AccountDoor::ZONE_BY_ROUTE` se extiende con `registro → register`, `login → login` y
`password.request → forgot`. **No nace una clase nueva**: un segundo mapa sería un segundo sitio
donde equivocarse, y `AccountDoorWiringTest` ya cruza este con `navigation.js`.

▶ Con eso, `isDoor()` pasa a ser cierto en las tres, el layout emite `data-account-zone` y
**`data-purchase-open="1"`**, y el cajón nace abierto en la zona pedida. Es el mecanismo de
`/entradas`, sin una línea nueva de mecanismo.

⚠️ **`HomeController::authModal` se retira**, y con él el prop `authModal` del layout (lo pasan
**cuatro** vistas; la quinta es el propio layout, que lo declara y lo consume). **Su efecto en el
`<meta robots>` NO se puede perder** (§1.3, punto 3): las tres rutas pasan a declarar `noindex` por el
prop `$noindex` que el layout ya tiene, **con test propio escrito ANTES del borrado**.

### 4.5 Los cinco puntos que abrían el modal

Los cinco pasan al patrón ya medido de `app.js::followAccountLink()`: **`<a href>` con `@click` que
solo se traga el clic si el motor está**. El `href` es la puerta por ruta, así que la degradación no
hay que inventarla — ya existe y lleva al mismo sitio.

| Punto | Hoy | Después |
|---|---|---|
| `site/nav` (escritorio) | `$store.auth.open('register')` | `href` a `registro` + zona `register` |
| `site/nav` (cajón móvil) | ídem | ídem |
| `account-context` (×2, invitado) | `$store.auth.open('login')` | `href` a `login` + zona `login` |
| `sections/AccountSection.vue` (401) | `window.Alpine.store('auth').open('login')` | `store.go(ZONES.LOGIN)` — ya está dentro |

⚠️⚠️ **Y hay un camino NUEVO que hoy no existe y es donde este trabajo se puede romper en silencio**:
un clic **con el motor ya montado**. `bootSpaEngine()` aplica `accountZone` **solo en el primer
arranque** —así lo exige `AccountDoorWiringTest`, y es el fallo que `#59(b)` y `#120(u)` pagaron dos
veces—, de modo que `followAccountLink()` tiene que llevar la zona por su cuenta. Ya lo hace
(`spaHandle.showAccount(zone)`); lo que falta es **abrir el cajón** cuando está cerrado, que es el
caso del botón de la cabecera. Se resuelve con un `openAccount(zone)` (futuro) en el store de Alpine
que **abre y aplica la zona por un único punto de consumo**, compartido con el de la puerta: dos
sitios que consuman la misma señal es la receta de que uno se olvide.

### 4.6 Qué pasa al conseguir sesión fuera del embudo

Lo que el embudo hace en `enterWith()` **no se reimplementa**; se reutiliza lo que ya es común:

1. **avisar a Livewire** (`logged-in`) → `account-context` repinta «Hola, nombre», y `app.js` marca
   `authChanged` para recargar al cerrar el cajón;
2. **aplicar la identidad a la cesta** con la respuesta del propio login (`POST auth/login` devuelve
   el perfil con la forma de `GET /me` justo para esto);
3. **ir a la zona `HOME`** (§3.3) — y **no** continuar ningún checkout: fuera del embudo no hay
   secuencia que continuar, y llamar a `continueAfterIdentification()` aquí consumiría admisión sin
   que nadie esté comprando. ⚠️ **Esa es la diferencia de fondo entre las dos entradas**, y es de
   dinero: por eso se dice aquí y no se deja al criterio de quien implemente.

### 4.7 Lo que se RETIRA

| Qué | Nota |
|---|---|
| El bloque `@guest` del modal en `layout.blade.php` | Con él, `a11yPanel('$store.auth.modal')` |
| `Livewire\Auth\Login`, `Register`, `ForgotPassword` + sus 3 plantillas | **`ResetPassword` NO** |
| El modo `embedded` | Muere con los componentes (`#112(f)`) |
| `$store.auth` de `app.js` | Con `data-auth-modal`, la llave `auth` del bloqueo de scroll, `clearModalUi()`, el evento `auth-modal-closed` y el flag `completed` |
| `HomeController::authModal` y el prop del layout | ⚠️ El `noindex` se conserva por otra vía (§4.4) |
| `SidebarLoginParityTest`, `SidebarRegisterParityTest` | ⚠️ **Solo 6 de sus 14 casos** — ver §4.7.bis |
| `Livewire\Concerns\ResetsOnModalClose` | Muere **sin condición**: sus usuarios son exactamente los TRES que se retiran (`ResetPassword` **no** lo usa — verificado) |

⚠️ **Tres guardas más se ponen en ROJO con el borrado, y van en su MISMO commit** (medido el
2026-08-23; ninguna estaba en el inventario inicial de esta spec):

| Guarda | Por qué cae |
|---|---|
| `ScrollLockOwnerTest::test_every_overlay_asks_with_its_own_key` | Itera `['sidecart', 'auth', 'nav', 'offers']`: la llave `auth` desaparece con el modal |
| `SpinnerTest` | Hace `Livewire::test(App\Livewire\Auth\Login::class)` — el spinner del botón de entrar. Su sujeto (el componente `x-ui.spinner`) sobrevive: **se re-apunta a otra superficie que lo use** |
| `SidebarEntry::clear()` | ⚠️ **Se queda sin su ÚNICO llamante**, que es `Livewire\Auth\Login::login`. Es la defensa de que a Bob no le aparezca el «pago denegado» de Alice al entrar en un dispositivo compartido. **Decisión explícita, no descuido**: o el login del cajón la reproduce, o se retira con su caso — y lo primero es lo correcto, porque el motivo por el que existe no se ha ido. (`purchase.cart`/`purchase.user_id` sí pueden morir: el cajón resuelve la cesta cruzada por su lado, con `cart.js::decideOwnership()`) |

⚠️ **Los 36 casos de `tests/Feature/Auth/{Login,Register,ForgotPassword,DuplicateEmailEdgeCase}Test`
NO se borran: se CLASIFICAN** por su sujeto (`CONVENCIONES §3.quater`) y **mutando, no leyendo**. La
mayoría afirman del DOMINIO —limitadores, no-enumeración, regeneración de sesión, anti-cesta-cruzada,
señuelo del alta— usando el componente como **intermediario**: ésos se re-apuntan a la API
(`AuthSessionTest`, `AuthRegistrationTest`, `PasswordRecoveryTest`) o al servicio
(`PasswordLoginTest`), y **hay que volver a mutarlos después de re-apuntarlos** (`#65`: un caso
re-apuntado puede quedarse inerte sin que nadie lo note).

### 4.7.bis ⚠️ Las dos «paridades» NO son 14 casos de paridad: son 6

Esta spec dijo primero que las dos paridades morían enteras. **Es falso, y medirlo lo destapó**
(2026-08-22, `grep -n 'public function test_' tests/Feature/Sidebar/SidebarLoginParityTest.php
tests/Feature/Sidebar/SidebarRegisterParityTest.php`). Clasificados por SUJETO
(`CONVENCIONES §3.quater`):

| Sujeto | Casos | Qué pasa |
|---|---|---|
| **Comparar el cajón con el modal** (credenciales, limitador, validación, rechazos de negocio, el mensaje de la API que a propósito NO se pinta) | **5** | **MUEREN** con la superficie |
| **El PAYLOAD del montaje** (que lleve cada texto que el paso pinta, que siga PODADO, la poda clave a clave del cliente con sesión, los dos textos legales con su enlace, los rótulos del alta) | **5** | **SE MUDAN.** Su sujeto es el `data-boot` del cajón, que sobrevive intacto → `SidebarMountTest` |
| **El bit del anti-bot** (`GET /config` publica la clave pública ⟺ el anti-bot está activo) | **1** | **SE MUDA**: cruza la API con `signupRequiresCaptcha` en Node y **ni siquiera monta Livewire** |
| **El señuelo indistinguible** | **1** | **SE MUDA**: verificado — es 100 % API (`POST /api/v1/auth/register` dos veces), sin una línea de Livewire. Su sujeto es SEC-06 |
| **El alta embebida abre sesión y no manda correo** | **1** | **MUERE SIN PÉRDIDA** — y eso se MIDIÓ, no se supuso: su mitad de API ya la cubre `AuthRegistrationTest::test_a_purchase_signup_opens_a_session_and_skips_the_verification_email`, y la suelta, el caso de al lado. Medir para no encontrar nada sigue siendo medir (`#94`) |

> ✅ **A2 EJECUTADO el 2026-08-23.** Destinos reales, que resultaron ser **tres y no uno**: los cinco
> del payload a `SidebarMountTest` (que ya era «el payload del montaje» y tenía el helper); el señuelo
> a `Api\V1\AuthRegistrationTest`, porque es 100 % API y su sujeto es SEC-06; y el del anti-bot a un
> **`SidebarAntiBotTest`** nuevo, con nombre que dice qué vigila.
> ▶ **Re-mutados en su nuevo sitio**, que es lo que `#65` exige: podar `account.login`, meter una clave
> de más, quitar un rótulo del alta y dejar los textos legales sin interpolar tumban cada uno **su**
> caso y solo el suyo. El del señuelo se midió **haciendo que las dos respuestas difieran** —mutar una
> rama de una propiedad de indistinguibilidad da un mutante equivalente por diseño (§3.quater, trampa
> 4)— y además saltó el contrato OpenAPI.
> ▶ **Y apareció una cita rancia**, que es la huella que `#121` enseñó a buscar: `layout.blade.php`
> decía «lo vigila `SidebarLoginParityTest::test_the_mount_payload_stays_pruned`» y
> `VERIFICACION-E2E-CAJON.md` daba la anti-enumeración del alta por cubierta en la paridad. Las dos
> corregidas en el mismo commit.

⚠️⚠️ **Y uno de esos cinco es el que más duele perder**: el techo del payload del montaje —**4.708 B
medidos, techo 4.800**— vive dentro de `SidebarLoginParityTest`, no en un fichero de presupuestos.
Borrar el fichero se habría llevado **el único guardián de lo que viaja en el HTML de TODAS las
páginas públicas**, y nada habría fallado. Es exactamente el cuño de `#112`: una guarda en verde que
deja de medir sin que nadie lo note.
▶ Los ocho casos que sobreviven se mudan a un fichero cuyo nombre diga su sujeto —`SidebarMountTest`,
el montaje del cajón, no la paridad con un motor que ya no existe— y se vuelven a **mutar después de
mudarlos** (`#65`: un caso re-apuntado se queda inerte sin que nadie lo note).

⚠️⚠️ **Y la mudanza va PRIMERO, no al final — hay una dependencia invertida.**
`test_the_mount_payload_stays_pruned` asevera `assertSame(['login', 'register'],
array_keys($boot['account']))` **para el visitante anónimo**. En cuanto §4.2 añada los textos de
recuperar al montaje, ese caso se pone **rojo** — y estaría en rojo desde el primer paso de
implementación hasta el último, que es exactamente la situación en la que un rojo deja de significar
algo. La mudanza es el **paso A2**, antes de tocar el payload.

### 4.8 Lo que NO se retira, y por qué

- **`ResetPassword` y su página**: token en la URL, se llega desde un correo. El cajón no tiene URL.
- **Las tres rutas**: `route('login')` es el destino del middleware `auth` de Laravel; borrarla rompe
  `/mi-cuenta`, `/mi-cuenta/pedidos` y toda ruta autenticada.
- **El CSS `.modal*`**: lo usan otros superpuestos. Se comprueba antes de tocar nada; **no** entra en
  esta spec.
- **`account-context`**: solo cambian sus dos `@click`.

### 4.9 Los presupuestos, que van a subir

Los dos están **al borde a propósito** desde el cierre de la tanda 3: chunk **189,5 KiB** (techo 190)
y payload del montaje **4.708 B** (techo 4.800). Tres zonas, un módulo plano y el grupo
`account.forgot` los pasan seguro. **Se suben con su medida y su motivo en el mismo commit**, que es
la regla que este proyecto ya aplica en `SidebarBundleBudgetTest`; nunca «por si acaso».

### 4.10 ⚠️ Cinco conductas que hoy tienen dueño y se quedarían sin él

Ninguna de las cinco la ve un test de árbol ni la nombra una invariante. Todas salieron de la revisión
adversarial del 2026-08-23 y todas están **verificadas en el código**.

1. **La defensa de la TABLET COMPARTIDA se queda huérfana.** Hoy `$store.auth.close()` llama a
   `clearModalUi()` —vacía los inputs y borra los errores **en el acto**— y el flag `completed` fuerza
   un `location.reload()` tras un alta o una recuperación, para que el siguiente cliente no vea el
   correo del anterior. En el cajón, **nadie llama a `reset()` al cerrar ni al salir de zona**:
   `app.js::close()` solo recarga si hubo login. La contraseña de Alice se quedaría en el campo para
   Bob. ▶ **`reset()` cuelga del cierre del cajón y del cambio de zona**, y su caso se escribe: tres de
   los 36 (`test_closing_modal_resets_*`) se re-apuntan justo aquí.
2. **Falta la SALIDA de «revisa tu correo».** El modal ofrece **reenviar** (`Register::resend()`, con
   su contador de reenvíos y los dos cooldowns del servicio) y un enlace «¿ya tienes cuenta?».
   `VerifyStep.vue` no tiene ninguno de los dos, y su comentario dice «no lleva salida, y eso es
   fiel» — fiel al alta **embebida**, no a la suelta, que es la que esta spec estrena. Sin ellos, un
   alta cuyo correo no llega queda **sin salida**: la página `/email/verificar` exige sesión y el alta
   suelta no la abre. El servidor ya lo cubre (`POST /api/v1/auth/email/resend`); el hueco es de
   pantalla.
3. ~~**La pila de retorno se queda con zonas de invitado dentro.**~~ ✅ **DISUELTO por §3.3, no
   arreglado** (2026-08-23). El riesgo era real —`navigation.js::go()` solo recorta cuando la zona ya
   está en la pila, así que `ORDERS → LOGIN → HOME` dejaba un «volver» que enseñaba un formulario de
   login a quien acababa de entrar—, pero **aterrizar es navegar**: la página se recarga, el cajón
   monta de cero y la pila nace en `[HOME]`. No hay nada que reiniciar.
   ▶ Se deja escrito, y no borrado, porque **es la clase de conducta que vuelve** el día que alguien
   sustituya la navegación por un cambio de estado en caliente. Entonces el problema reaparece entero.
4. **`openAccount(zone)` tiene una carrera.** `bootSpaEngine()` sale antes por
   `if (this.spaHandle || this.spaLoading)` y aplica la zona **después** del `await import()`: con el
   cajón cerrado y el motor a medio cargar, un `open()` seguido de `showAccount()` llamaría sobre
   `spaHandle === null` y el clic **no fallaría y no haría nada** (`#117`). ▶ El punto único **cuelga
   de la promesa**, como ya hace `open()` con `refreshStatus`/`refreshIdentity`.
5. **`publishedIdentifying` deja de describir lo que se ve.** Devuelve siempre `false` en la sección
   de cuenta, y su propio comentario lo justifica diciendo «la señal describe lo que el cliente está
   VIENDO» — con la zona `LOGIN` eso deja de ser cierto, y el `watch` de la raíz **ni siquiera observa
   la zona**. ⚠️ **No abre hueco real** (verificado: el CSS deja `.sidecart__panel.is-account .acct`
   en `visibility: hidden`, así que los botones de invitado no son enfocables), pero **la defensa pasa
   a ser el CSS y eso hay que decirlo**, o hacer que la señal mire la zona. Un comentario que promete
   más de lo que la señal hace es la familia de `#115`.

---

## 5. Impacto en invariantes

| ID | Qué pasa |
|---|---|
| **SEC-06** | **La regla no cambia** —los dos limitadores del login, el mensaje genérico del reset y el throttle siguen en `Identity\Services\PasswordLogin` y `PasswordRecovery`, que **no se tocan**—. Lo que cambia es **dónde se verifica**: su columna deja de citar `LoginTest`/`ForgotPasswordTest` y cita los tests de la API. **Va en el mismo commit que el borrado** (`#121`). |
| **RGPD-01 / RGPD-06** | Ninguno. La auth no crea PII nueva ni toca la purga. |
| **SUITE-01** | Ninguno: el anti-bot del cajón ya está fake-ado desde 4.4b·2. |
| **PERF-02** | Ninguno: las tres puertas sirven la home, que ya está bajo presupuesto de queries. |

▶ **Ninguna invariante se relaja**, así que esto no es un caso de `CONVENCIONES §9.1`. Repasadas §3
(RGPD-01…06) y §4 (SEC-01…11) enteras en la revisión del 2026-08-23.

⚠️ **Lo que el repaso sí destapó**: **ninguna invariante cubre la defensa de la tablet compartida**
(§4.10·1). Hoy vive solo en tres de los 36 casos que se van, y por eso puede desaparecer sin que
`INVARIANTES.md` se entere. No se propone crear una invariante nueva —eso es del owner
(`CONVENCIONES §9.1`)—, pero **sí queda dicho aquí** para que la próxima sesión no la pierda por no
saber que estaba sola.

---

## 6. Plan de verificación empírica

⚠️⚠️ **La red de este trabajo NO es el diff de árbol.** `scripts/render-sidebar.mjs` **no importa la
raíz del cajón** y esto toca el orquestador y la costura con la landing: la red es el **NAVEGADOR**
(`VERIFICACION-E2E-CAJON.md` §5.quater, el protocolo con sus casos `V1`…`V8`; §5.bis es el andamio
automático que lo prepara).

⚠️ **Corrección de una afirmación que esta spec traía y era FALSA**: el árbol de login y alta **no se
queda sin gate**. `SidebarDomContractTest` tiene tres casos suyos —el paso de identificación, el
formulario de alta y su banner de error— y desde 4.7·2b·3 **ancla en `$vue` contra el manifiesto
congelado**, no en Livewire (`DECISIONES #111`), así que sobreviven a la retirada.
▶ La consecuencia real es **la contraria** y hay que decirla: el enlace de «recuperar» en `LoginForm`
y las tres zonas nuevas **cambian ese árbol**, así que el manifiesto se regenera con
`MANIFEST_REFRESH=1` y **el commit tiene que justificar por qué** — es la condición que el propio
test exige para no convertir el refresco en una goma de borrar.

| | Qué se prueba | Con qué |
|---|---|---|
| V1 ✅ | Las reglas de recuperar contraseña, incluidas **las dos respuestas indistinguibles** | `forgot.test.js`, `node --test` — **12 casos, hechos el 2026-08-23**. La no-enumeración se prueba **cruzando las dos respuestas** (§3.quater, trampa 4) y se midió con una mutación que hace depender el resultado del correo: la caza **solo** ese caso |
| V2 ✅ | Las tres zonas existen, tienen rótulo y **son alcanzables** por su puerta | `navigation.test.js`, guarda nueva (§4.1): toda zona está en el índice **o** en `GUEST_ZONES`, las dos listas **no se solapan**, y las de invitado tienen rótulo propio. **Medido** declarando una zona huérfana: la cazan dos casos |
| V3 | El mapa ruta→zona cubre las tres rutas y **cuadra con `navigation.js`** | `AccountDoorWiringTest` (casos nuevos) |
| V4 ✅ | **Las tres URLs se sirven `noindex, nofollow`** y ninguna está en el sitemap | ✅ **HECHO el 2026-08-22**, contra el código de hoy: `SeoTest::test_the_auth_doors_are_never_indexable` + `…_are_not_advertised_in_the_sitemap`. **Medido por MUTACIÓN, las dos direcciones**: quitar `$authModal` del `<meta robots>` tumba el caso en su aserción, y forzar `noindex` en TODA la web tumba el **control negativo** —sin él, un layout que no distinguiera pasaría con matrícula—. El ancla es única (un solo `name="robots"` en el layout) y la restauración se comprobó por CONTENIDO y con `git status` limpio (`CONVENCIONES §3.quater`, trampas 1 y 2) |
| V5 | Los cinco puntos llevan su `href` **y** su cableado, y el invitado los RECIBE en el HTML | `AccountDoorWiringTest` — ⚠️ su caso «un invitado no recibe cableado» **se INVIERTE**, no se borra |
| V6 | SEC-06 sigue verificada desde la API | `AuthSessionTest`, `PasswordRecoveryTest`, `AuthRegistrationTest` |
| V7 | Los 36 casos clasificados, **medidos por mutación** antes y después de re-apuntar | `CONVENCIONES §3.quater` |
| V8 | **NAVEGADOR**: entrar desde la cabecera → índice **con sus rótulos** (§3.3) · alta suelta → **el correo de verificación SALE** (§4.3) y su pantalla ofrece reenvío (§4.10·2) · recuperar desde el paso 5 → volver **con la cesta intacta** · sesión caducada en `ORDERS` → login → tras entrar, **«volver» no enseña el login** (§4.10·3) · el clic **con el motor a medio cargar** (§4.10·4) · cerrar el cajón **no deja la contraseña en el campo** (§4.10·1) | `VERIFICACION-E2E-CAJON.md` §5.quater |
| V9 | Los dos presupuestos, re-medidos y bajados a lo medido al cerrar | `SidebarBundleBudgetTest` (chunk, techo **190 KiB**) y `SidebarMountTest::test_the_mount_payload_of_a_signed_in_customer_is_pruned_key_by_key` (payload, techo **4.800 B**) — ✅ ya mudado ahí en A2 |

---

## 7. Revisión y decisión

- **Owner (2026-08-22)**: decidió §3.3 (aterrizaje en el índice de Mi cuenta) y §3.4 (el embudo gana
  «he olvidado mi contraseña», con vuelta a la compra).
- **Owner (2026-08-23)**: decidió el MECANISMO del aterrizaje (§3.3: navegar a la puerta en vez de
  mandar los textos a todo el mundo) y cerrar de paso el `noindex` de las dos páginas de auth que se
  quedaban fuera.
- **Revisión adversarial (2026-08-23)**, contra el código y en solo lectura. **Cambió la spec de
  fondo**, y esto es lo que encontró que no estaba:
  - **dos BLOQUEANTES**: los textos del área viajan solo con sesión, así que §3.3 pintaba un índice
    en blanco (§3.3, resuelto); y reutilizar el alta «tal cual» convertía el alta suelta en
    **pay-first**, porque `context` está quemado (§4.3, resuelto);
  - **una dependencia invertida** en el orden: mudar los casos del payload tenía que ir **antes** de
    tocar el payload, no después (§4.7.bis y §8);
  - **una afirmación FALSA** de esta spec: el árbol de login y alta **sí** conserva gate, y lo que
    hace falta es regenerar el manifiesto con su justificación (§6);
  - **cinco conductas sin dueño** que ningún test de árbol ve (§4.10) y **tres guardas** que el
    borrado pone en rojo (§4.7);
  - y confirmó lo que ya estaba bien: que del servidor **no falta nada** —comparados los tests de
    API uno a uno, **ninguna regla de dominio vive solo en los tests Livewire**—, que los 5 puntos y
    las 3 puertas son exhaustivos, y que A1 está bien resuelto de origen a fin.
- ⚠️ Lo único que la revisión **no** pudo hacer es ejecutar la suite (entorno de solo lectura): las
  mutaciones que menciona son las que hay que hacer, no medidas por ella. Las de A1 sí están medidas.
- La entrada está en `DECISIONES.md` **#122** (2026-08-23), con las tres decisiones de producto, el
  hueco del `noindex` y los dos bloqueantes de la revisión.

---

## 8. Orden de implementación (por DEPENDENCIA, no por pantalla)

| Paso | Qué | Por qué va aquí |
|---|---|---|
> ⚠️ **Reordenado tras la revisión del 2026-08-23.** El orden anterior dejaba
> `test_the_mount_payload_stays_pruned` **en rojo desde el segundo paso hasta el penúltimo**, que es
> la situación en la que un rojo deja de significar nada.

| Paso | Qué | Por qué va aquí |
|---|---|---|
| **A1** ✅ | **Cerrar el hueco del `noindex`** con su test, contra el código de HOY | El único que había que hacer **antes** de tocar nada: después del borrado ya no habría qué comparar (§1.3·3). **Hecho y medido por mutación el 2026-08-23**, ampliado a las cinco superficies de auth |
| **A2** ✅ | **Mudar los 8 casos que sobreviven** de las dos paridades, y **re-mutarlos** allí | ⚠️ **PRIMERO**, y es la corrección de fondo del orden: uno de ellos asevera que el payload del invitado son exactamente `login` y `register`, así que **A3 lo pondría en rojo** (§4.7.bis). **Hecho el 2026-08-23**: 7 mudados a tres destinos, 1 retirado tras medir que ya estaba cubierto, y los 6 que quedan en las paridades siguen montando Livewire |
| **A3** ✅ | `forgot.js`, **`context` como parámetro** (§4.3) y el estado en `stores/auth.js`, con `node --test` | Reglas antes que pantallas, como las ocho zonas anteriores. **Hecho el 2026-08-23**: 12 casos de `forgot.js`, 2 del contexto en `register.js`, 7 del store y **una guarda de cableado nueva** (`SidebarSignupContextTest`) para el eslabón que ningún test veía — que el embudo declare `purchase`. Techo del chunk **190 → 191** (medido 190,5, +1,0) |
| **A4** ✅ | **`LOGIN` y `FORGOT`**, la guarda de alcanzabilidad en su forma nueva, `account.forgot` en el payload y **el aterrizaje** | Ya hay a dónde ir. ⚠️ **Recortado sobre la marcha, y por DEPENDENCIA**: la zona de alta necesita su pantalla de «revisa tu correo» con reenvío, que arrastra el subgrupo `account.verify` y otro endpoint. Meterla aquí habría dejado media pantalla construida — ver A5. **Hecho el 2026-08-23** |
| **A5** | **`REGISTER`** con su «revisa tu correo» **con reenvío y escape** (§4.10·2), y las pestañas | Va junto porque va junto: sin la pantalla de después, el alta suelta —que NO abre sesión— no lleva a ninguna parte |
| **A6** | Las puertas: `AccountDoor` + `openAccount()` + los cuatro puntos que quedan | Ya hay a dónde llegar. ⚠️ **Aquí viven DOS fallos silenciosos**: el clic con el motor ya montado (§4.5) y la carrera con el motor a medio cargar (§4.10·4) |
| **A7** | El paso 5 gana el enlace de recuperar, con vuelta | Toca el embudo, que es el camino del dinero: va **después**, solo, y con el manifiesto regenerado y justificado (§6) |
| **A8** | **Auditar los 36 casos**, mutando | Antes de borrar, nunca después |
| **A9** | **Retirar**: modal, tres componentes, `$store.auth`, `authModal`, el trait — y **en el MISMO commit** SEC-06, `ScrollLockOwnerTest`, `SpinnerTest` y la decisión sobre `SidebarEntry::clear()` | Cuando ya no queda nadie que dependa. Las cuatro caen a la vez: separarlas deja la suite roja sin dueño |
| **A10** | Presupuestos re-medidos y **bajados a lo medido**, doc (`MAPA-PAGINAS`, `SEGURIDAD`, `FLUJOS`, `DEUDA`, `sistemas/UI-SPINNER`) y **NAVEGADOR** | El cierre, con evidencia |
