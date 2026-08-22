# [SPEC] El área de cliente dentro del cajón — tanda 1: SOLO LECTURA

> Estado: ✅ **APROBADO por el owner** (2026-08-22) → a implementar · Última actualización: **2026-08-22** ·
> Marco: `DECISIONES #66` · Terreno preparado: `DECISIONES #119` · Decisión asociada: `DECISIONES #120`.

Diseño previo a implementación (`CONVENCIONES §5`). Quien implemente trabaja CONTRA este documento.
Todo lo que aquí se marca **MEDIDO** se comprobó contra el código el 2026-08-22; lo demás es propuesta.

---

## 1. Contexto y problema

### 1.1 La gestión del cliente vive hoy en TRES sitios

| Superficie | Qué hace | Tecnología |
|---|---|---|
| El cajón (`sections/PurchaseSection.vue`) | comprar, y de paso **entrar y darse de alta** (paso 5) | Vue 3 + Pinia |
| El modal de la cabecera (`layout.blade.php`) | entrar, darse de alta, recuperar contraseña | Livewire |
| Las páginas `/mi-cuenta/…` | pedidos, perfil, contraseña, sesiones, privacidad, borrar cuenta | Blade + Livewire |

Y hay un cuarto actor que no es ninguno de los tres: **`account-context`**, el bloque de cuenta que se
pinta DENTRO del panel del cajón pero FUERA del motor Vue —es hermano del punto de montaje— y sigue
siendo Livewire. Sus dos botones (`Iniciar sesión`, `Mis reservas`) **navegan fuera** o abren el modal.

`DECISIONES #66` decidió que el destino es UNO: el cajón.

### 1.2 ⚠️ «El servidor ya está: hay que pintar, no abrir dominio» solo es cierto A MEDIAS

La frase se repite en `ESTADO.md`, en `00-REFACTOR.md` y en el propio `#66`. **MEDIDO** contra
`routes/api.php` el 2026-08-22, endpoint por endpoint:

| Lo que el área de cliente necesita | ¿Existe en `/api/v1`? |
|---|---|
| Mis próximas reservas | ✅ `GET /me/reservations` |
| Mis pedidos (paginado, todos los estados) | ✅ `GET /me/orders` |
| Quién soy | ✅ `GET /me` |
| Reintentar el pago de un pedido | ✅ `POST /orders/{code}/payment` + `can_be_retried` |
| Entrar / darse de alta / recuperar contraseña | ✅ `/auth/*` |
| **Cambiar el perfil** (nombre, email, teléfono, idioma) | ❌ **no existe** — hoy solo `App\Livewire\Account\UpdateProfile` |
| **Cambiar la contraseña** | ❌ **no existe** — `App\Livewire\Account\UpdatePassword` |
| **Cerrar sesión en otros dispositivos** | ❌ **no existe** — `App\Livewire\Account\LogoutOtherDevices` |
| **Borrar la cuenta** (art. 17) | ❌ **no existe** — `App\Livewire\Account\DeleteAccount` |
| **Exportar mis datos** (art. 20) | ❌ **no existe** — controlador web `account.export` |

▶ **Consecuencia para la planificación**: la mitad de LECTURA es pintar; la mitad de GESTIÓN es abrir
superficie de API nueva sobre dominio que ya existe. Son dos trabajos de tamaño distinto, y por eso
esta spec cubre **solo la primera**.

### 1.3 ⚠️ Y hay una diferencia de naturaleza con todo lo hecho en la Fase 4

El embudo fue una **transcripción**: existía un original (el sidebar Livewire) que pintaba en el MISMO
sitio, así que el contrato visual podía ser el ÁRBOL y el gate podía compararlos nodo a nodo
(`sidebar-spa.md` §4.2, `SidebarDomContractTest`).

**El área de cliente no tiene original que copiar**: `/mi-cuenta/pedidos` es una **página ancha**, y el
destino es un **panel estrecho**. Su marcado no puede ser el mismo, y forzar un diff de árbol contra
esa página sería un test que no se puede pasar sin destruir el diseño.

▶ **Eso cambia dónde está la red**, y es el punto más importante de esta spec:
> la paridad que aquí protege es **de DATOS y de REGLAS** (¿se enseña lo mismo, se ofrece lo mismo,
> se oculta lo mismo?), **no de árbol**. Y el resto lo cubre el NAVEGADOR
> (`VERIFICACION-E2E-CAJON.md` §5.bis), porque esto toca el orquestador.

---

## 2. Objetivo

**Que un cliente identificado vea sus reservas y sus pedidos sin salir del cajón**, con la misma
información y las mismas acciones que hoy le da `/mi-cuenta/pedidos`.

Criterios de éxito, todos medibles:

1. `GET /me/reservations` y `GET /me/orders` se pintan dentro del cajón, con paginación en pedidos.
2. **Paridad de datos con la página**: para el mismo pedido, el cajón enseña los mismos importes,
   estados, fechas, líneas, complementos, resto en puerta y avisos de post-form que
   `/mi-cuenta/pedidos`. Verificado por test, no por lectura.
3. **El reintento de pago funciona desde el cajón** para un pedido con `can_be_retried`.
4. `sections/AccountSection.vue` nace **bajo el techo de 40 líneas de código y con 0 llamadas a la
   API** (`SidebarComponentBudgetTest`) — sin excepción declarada. Si necesita una, el diseño está mal.
5. El grafo del embudo (`FUNNEL_TRANSITIONS`) **no cambia ni una línea**.
6. La raíz (`Sidebar.vue`) sigue sin conocer `STEPS.` y sin hablar con la API.
7. **Volver de la cuenta a la compra no dispara ninguna petición nueva** y deja el embudo en el paso
   donde estaba, con la cesta intacta (§4.1). Verificado en navegador con la pestaña de red abierta.
8. **Ninguna de las 27 referencias a `route('account…')` queda rota** al final de la tanda 3 — los 8
   correos ya entregados siguen llevando a algún sitio útil (§4.8).

### Explícitamente FUERA de alcance

- Las **gestiones de cuenta** (perfil, contraseña, otras sesiones, borrado, export): §1.2, tanda 2.
- **La auth dentro de la sección de cuenta**: un invitado sigue yendo al modal de la cabecera (§4.6).
- **Retirar `/mi-cuenta/…` y el modal**: decidido por el owner que desaparecen (`#120`), pero es tanda 3
  y tiene condiciones de entrada propias (§4.8).
- **Traer `account-context` a Vue**: la última frontera. Aquí solo se le cablea la puerta (§4.6).
- **El post-form dentro del cajón**: se enlaza a la página web, como hoy (§4.7).

---

## 3. Opciones consideradas

### 3.1 El modelo de navegación

| Opción | Forma | Por qué |
|---|---|---|
| **A — Pestañas planas** | «Reservas ⟷ Pedidos», sin índice | Barato y suficiente HOY, con dos zonas. **Descartada**: el destino conocido son ~7 zonas (`#120` retira `/mi-cuenta` entera) y una barra de 7 pestañas en un panel estrecho no existe. Cambiar de modelo a mitad cuesta más que empezar bien. |
| **B — Índice + zonas** ✅ | una zona `HOME` con el menú de la cuenta, y cada apartado es una zona | Escala hasta las 7 sin rediseñar. Es además la forma que ya tiene `/mi-cuenta` (índice de tarjetas), así que el cliente no aprende nada nuevo. **ELEGIDA.** |
| C — Colgarlo del grafo del embudo | añadir pasos a `FUNNEL_TRANSITIONS` | **Descartada, y con guarda que lo impide** (`machine.test.js`, `#119`). Un área de cliente no es un embudo: sus pantallas se navegan libremente. Mezclar los dos modelos obliga a razonar sobre uno al tocar el otro. |

### 3.2 El detalle de un pedido

| Opción | Por qué |
|---|---|
| **Acordeón en la lista** ✅ | `GET /me/orders` **ya devuelve `items[]` completos** (MEDIDO en `openapi/v1.yaml`), así que el detalle no cuesta ninguna petición más. Es además lo que hace hoy la página. **ELEGIDA.** |
| Zona de detalle propia | Una zona más y una petición más para datos que ya viajaron. Descartada. |

### 3.3 Qué «modo» publica el cajón mientras se navega la cuenta

**MEDIDO**: `layout.blade.php` pinta `is-{modo}` en `.sidecart__panel`, y `public/css/site.css` colapsa
el bloque `.acct` en `is-booking` e `is-cart`. Dentro de la cuenta ese bloque es redundante —sus botones
llevan a donde ya estás—, así que debe colapsarse.

| Opción | Por qué |
|---|---|
| Reutilizar `cart` | Colapsa el bloque sin tocar CSS… **pero miente**: una señal que dice `cart` cuando el cliente está en su cuenta es exactamente el fallo de `#115` («una comprobación que mide una cosa y se lee como otra es peor que no tenerla»). **Descartada.** |
| **Modo nuevo `account`** ✅ | Una regla CSS más (`.sidecart__panel.is-account .acct`, junto a las dos que ya existen). **No toca `modeOf()`** —que es función del PASO DEL EMBUDO— así que `SidebarProgressParityTest`, que congela los once pasos, no se entera. **ELEGIDA.** |

### 3.4 ¿La zona activa cambia la URL?

Dentro del cajón no hay URL: el embudo tampoco la cambia. Con la retirada de `/mi-cuenta/…` (§4.8) eso
deja de ser inocuo, porque la ruta pasa a ser la única forma de **llegar** a una zona.

| Opción | Por qué |
|---|---|
| **No tocar la URL** ✅ (tanda 1) | Consistente con el embudo, que lleva toda la Fase 4 sin hacerlo. Las rutas de cuenta **sobreviven como puertas de entrada** (§4.8), así que el enlace por zona de primer nivel existe igual, y el «atrás» dentro del cajón lo da la pila. **ELEGIDA para la tanda 1.** |
| `history.pushState` por zona | El «atrás» del navegador volvería a la zona anterior y cada zona sería enlazable. **Pero introduce un router en el cajón** y crea una asimetría difícil de defender: ¿por qué la cuenta sí y la compra no? Se reevalúa en la **tanda 3**, cuando existan las siete zonas y la página haya muerto. |

---

## 4. Diseño elegido

### 4.1 Dos niveles de navegación, y no se mezclan

```
NIVEL 1 · SECCIÓN     purchase  ⟷  account          ← conmutación libre, CON MEMORIA
NIVEL 2 · dentro de cada sección:
            purchase → el EMBUDO      (grafo cerrado, `FUNNEL_TRANSITIONS`)
            account  → ZONAS LIBRES   (modelo propio, pila de retorno)
```

⚠️ **«Con memoria» no es un detalle, y la forma de conseguirla se eligió MIDIENDO.**

**MEDIDO** en `sections/PurchaseSection.vue` el 2026-08-22: **un solo `ref` local** (`inFlight`), pero
un `onMounted` que dispara **cinco cargas** —catálogo, `/config`, estado de la pausa, restaurar la
cesta y leer el desenlace del pago— y un `onUnmounted(stopPolling)`.

| Forma | Qué pasa al volver de la cuenta |
|---|---|
| `v-if` a secas en la COMPRA | **remonta ⇒ repite las cinco cargas**, y `loadOutcome()` podría reabrir una pantalla de desenlace ya vista. **Descartada para la compra.** |
| `<KeepAlive>` + `v-if` | Vue **desactiva** en vez de desmontar. Era lo elegido en la v2 de esta spec, y **lo retiró una medición al implementar** (abajo). |

▶ **Lo elegido, con una forma distinta por sección y un motivo distinto para cada una:**

| Sección | Cómo se oculta | Por qué |
|---|---|---|
| **compra** | **`v-show`** | Es la sección por defecto y **su template `ref` sostiene el puente de `defineExpose`**. Desmontarla —o desactivarla con `KeepAlive`— **anula la ref**, y las dos señales que `index.js` invoca en cada apertura (`refreshBookingStatus`, `refreshIdentity`) se las comería el `?.` **en silencio**: la pausa dejaría de releerse y un cambio de titular no purgaría la cesta |
| **cuenta** | **`v-if`** a secas | Montarla siempre le regalaría a quien viene a comprar las peticiones de `/me/*` en cada apertura del cajón |

⚠️⚠️ **Por qué `<KeepAlive>` se cayó, MEDIDO el 2026-08-22 al implementar el paso 1.** Se puso para
que volver a la cuenta no repitiera su carga. Medido con y sin él: **cuesta 2,3 KiB de chunk** —él
solo hacía saltar `SidebarBundleBudgetTest`, cuyo techo advierte por escrito que el área de cliente
«tendrá que decidir su propio presupuesto, no colarse por el margen de esta»— **y no resuelve nada
que no resuelva mejor su store**: «pedir solo si no hay datos» es una regla explícita y probable con
`node --test`, no una caché del framework que además rompe las template refs.
▶ Sin él, el paso 1 **cabe en el presupuesto que ya existía** y no consume margen de nadie.
▶ Y la trampa que la v2 declaraba —`onUnmounted` no corre con `KeepAlive`— **deja de existir**: con
`v-show`, la sección de compra no se desmonta ni se desactiva nunca, así que `stopPolling` sigue
funcionando exactamente como el día que se escribió.

### 4.2 Las zonas de la cuenta (tanda 1)

| Zona | Qué pinta | De dónde | Su original |
|---|---|---|---|
| `HOME` | el índice: quién eres, tu próxima reserva y los accesos | `GET /me` + `GET /me/reservations` | `/mi-cuenta` + el bloque `.acct` |
| `ORDERS` | «Mis reservas»: historial paginado, con acordeón de detalle y reintento | `GET /me/orders` | `/mi-cuenta/pedidos` |

⚠️⚠️ **Son DOS zonas y no tres, y lo decidió una medición de vocabulario** (2026-08-22). La v2 de esta
spec proponía una zona `RESERVATIONS` («próximas reservas») separada de `ORDERS` («mis pedidos»).
Medido en `lang/`: **`account.orders.title` es literalmente «Mis reservas»** — la página
`/mi-cuenta/pedidos` **ya se llama así de cara al cliente**, y el vocabulario del cliente **no
distingue** pedido de reserva. Una zona «próximas reservas» aparte **no existe hoy en ninguna
superficie**: `GET /me/reservations` alimenta el bloque `.acct` (tu próxima reserva, el contador, el
aviso de formulario pendiente), no una pantalla.
▶ Crear esa zona habría sido **inventar producto** en una tanda cuyo criterio es la paridad. Los dos
endpoints que el owner pidió se usan igual: `/me/orders` en `ORDERS` y `/me/reservations` en el
índice, que es donde vive hoy esa información.
▶ Y el nombre técnico se queda en `orders`: renombrarlo a `reservations` lo confundiría con el
endpoint de próximas, que es otra cosa. El texto del cliente vive en `lang/`, que es su sitio.

**La navegación es LIBRE** (de cualquier zona a cualquier zona) y el «atrás» es una **PILA**, no un
grafo: es la diferencia formal con el embudo y la razón de que necesite modelo propio. La pila se vacía
al salir de la sección.

Las zonas de la tanda 2 —`PROFILE`, `PASSWORD`, `SESSIONS`, `PRIVACY`— **no se declaran todavía**:
declararlas vacías sería el mismo error que `#119` evitó a propósito. Y por eso el índice tiene **una
sola entrada** hoy: pintar cuatro filas que no llevan a ningún sitio es peor que no pintarlas.

⚠️ **La pila de retorno no admite repeticiones**, y no es una optimización: con una pila ingenua,
alternar entre el índice y las reservas quince veces deja quince entradas, y el cliente tendría que
pulsar «volver» quince veces para salir de un área que solo tiene dos pantallas. Al volver a una zona
ya visitada, la pila **se recorta hasta ella** —miga de pan— y queda acotada por el número de zonas.
Tiene caso propio, y verificado por mutación.

⚠️ **Y «volver» sin historia SALE a la compra**, en vez de no hacer nada. Un «volver» que a veces no
responde acaba con el cliente cerrando el cajón, que es lo que le hace perder de vista la cesta. La
decisión vive en el store y no en el módulo plano: aquél sabe de zonas, no de secciones.

### 4.3 Dónde vive cada cosa (las tres capas de `#119`)

```
resources/js/sidebar/
  Sidebar.vue                              ← enruta SECCIONES (hoy monta una sola)
  account/navigation.js       (futuro)     ← ZONES, la pila de retorno, el modo publicado
  account/orders.js           (futuro)     ← de `GET /me/orders` a filas pintables
  account/reservations.js     (futuro)     ← de `GET /me/reservations` a filas pintables
  stores/account.js           (futuro)     ← zona activa · páginas cargadas · en vuelo · error
  sections/AccountSection.vue (futuro)     ← enruta zonas y PINTA. Nada más
  account/zones/*.vue         (futuro)     ← una por zona, bajo el techo de 40 líneas
```

⚠️ **Las peticiones las hace el STORE, no el componente.** No es estilo: `SidebarComponentBudgetTest`
prohíbe que un componente hable con la API, y el motivo está medido —un componente se compara por su
árbol, y un árbol no dice a quién se le preguntó (el fallo de 4.3·1)—. Con las llamadas en el store,
`AccountSection.vue` cumple el techo sin excepción declarada, que es el criterio 4 del §2.

### 4.4 El contrato de datos, campo a campo

**MEDIDO** en `openapi/v1.yaml`: `Order` publica `code`, `status` (efectivo, con `expired` calculado),
`total_cents`, `online_amount_cents`, `pending_at_gate_cents`, `refund{}`, `can_be_retried`,
`created_at`, `paid_at`, `expires_at`, `items[]` y `guest_form_pending`. Cada `OrderItem` lleva además
`is_pack`, `paid_online_cents`, `gate_remainder_cents` y `shows_deposit_note` — **las tres condiciones
del aviso de señal ya compuestas por el servidor**, precisamente para que cuatro superficies no las
recompongan cada una a su manera.

▶ **Regla que se hereda de ahí**: el cajón **no recompone** nada que el servidor ya publique resuelto.
Si aparece un aviso que la API no publica, la salida es publicarlo, no calcularlo en el cliente.

⚠️ **Y una regla RGPD que se hereda de la cesta** (`stores/cart.js`): **nada de `/me/*` se persiste en
`localStorage`**. La cesta ya excluye a propósito las respuestas del evento (nombres y alergias de
menores, art. 9); un historial de pedidos cacheado en el navegador sería la misma fuga por otra puerta.
El área de cliente vive en memoria y se repide al abrir.

### 4.5 Las señales hacia fuera

La raíz sigue sin decidir nada (`#119`). Lo que cambia es **quién** las escribe:

| Señal | Sección `purchase` | Sección `account` |
|---|---|---|
| `mode` | como hoy, `modeOf(step)` | **`'account'`** (§3.3) |
| `identifying` | como hoy | **siempre `false`** — en la cuenta no se está identificando nadie |

### 4.6 La puerta de entrada (y por qué NO trae la auth)

**MEDIDO**: los dos botones de `account-context` hacen hoy cosas distintas según la sesión —con sesión,
`Mis reservas` navega a `route('account.orders')`; sin ella, los dos abren el modal de login—.

▶ **Con sesión** → el botón abre el cajón en la sección `account`, zona `RESERVATIONS`. Se cablea por el
store de Alpine (`$store.purchase`), que es el canal que la landing ya usa para la costura de intención;
**`account-context` sigue siendo Livewire y no se migra aquí**.
▶ **Sin sesión** → **no cambia nada**: sigue abriendo el modal de la cabecera. Traer la auth a la sección
de cuenta es tanda 2, y hacerlo aquí metería el trabajo entero por la puerta de atrás.

⚠️ **La sesión puede caducar con el cajón abierto.** La API responde 401; la zona enseña un aviso y un
botón que abre el modal de login —la puerta de auth de hoy—. Sin inventar auth y sin dejar una pantalla
en blanco, que es lo que pasa si nadie trata ese caso.

### 4.7 El post-form de invitados

**MEDIDO**: `GET /me/orders` publica `needs_guest_form` y `guest_form_status`, **pero no la URL**. El
helper existe (`OrderItem::guestFormApiUrls()`) y solo lo usa `GuestFormResource`. La ruta web
(`reservation.guests`) sí acepta al **dueño autenticado sin firma**.

▶ Tanda 1 **enlaza a la página web**, igual que hace hoy el bloque de cuenta: paridad exacta, cero API
nueva. ⚠️ **La URL la compone el SERVIDOR** y viaja en el boot del cajón (donde ya viaja `urls`):
componer rutas de Laravel en JavaScript es quemar el enrutador en el cliente.

### 4.8 La retirada de `/mi-cuenta/…` (tanda 3): mueren las VISTAS, viven las RUTAS

El owner decidió que **desaparecen** (`#120(c)`). No aquí: retirar una superficie exige que la que la
sustituye la cubra ENTERA, y la mitad de gestión ni siquiera tiene API (§1.2).

⚠️⚠️ **Y «desaparecer» hay que decirlo con precisión, porque medido en crudo rompería cosas ya
entregadas.** MEDIDO el 2026-08-22:

| Quién apunta a `account`/`account.orders` | Cuántos | Por qué importa |
|---|---|---|
| **Notificaciones por correo** | **8** (`OrderConfirmation`, `OrderPaymentDeclined`, `OrderCancelled`, `OrderRefunded`, `OrderItemCancelled`, `OrderItemRefunded`, `OrderItemModified`, y `route('login')` en dos más) | ⚠️ **Están en buzones de clientes.** Un correo enviado no se puede editar: borrar la ruta lo convierte en un 404 para siempre |
| **Redirecciones del servidor** con `->with('status', …)` | **11** (verificación de email ×3, cambio de email ×3, perfil ×3, contraseña, otras sesiones) | Cada una aterriza con un **mensaje flash** que la página pinta. Sin página, el mensaje no tiene dónde salir |
| Referencias totales en `app/` + `resources/views/` | **27** | |

▶ **La forma correcta, y el proyecto ya la tiene probada**: la **VISTA** muere, la **RUTA** sobrevive
como **puerta de entrada** que abre el cajón en su zona. Es exactamente lo que hace `/entradas` desde
la Fase 5.2 —sirve la home con `data-purchase-open="1"` y el cajón se abre solo—, así que no hay que
inventar mecanismo: hay que reutilizarlo.

▶ **Y el mensaje flash tiene ya su dueño**: `Http\Sidebar\SidebarEntry` resuelve **este mismo
problema** para el desenlace del pago —el servidor deja un estado que se **consume una sola vez** y el
cajón abre donde toca—. La zona de cuenta necesita lo mismo, y duplicar el mecanismo sería crear un
segundo sitio donde mirar. ⚠️ Con su trampa ya pagada en 4.0a: **si no se consume, el cajón se reabre
en cada página** hasta que caduque la sesión.

⚠️ **La lección de `#111` aplica literalmente**: *independizar el contrato ANTES de borrar*. Antes de
retirar la página hay que capturar de ella lo que aún no está capturado (sus textos y sus reglas de
visibilidad), o el borrado se lleva por delante la única referencia que existía.

Condiciones para abrir la tanda 3, todas verificables:
1. ⏳ **`AccountPageCaptureTest::GAPS` VACÍA.** Ya no es una nota que alguien tiene que acordarse de
   leer: es un test que **enumera exactamente qué se perdería** al borrar la página, y que **solo
   puede encoger**. Medido el 2026-08-22, faltan **cuatro** cosas por publicar:
   · `Order::depositRemainderPendingByProduct()` — el resto de la señal **por producto** («Resto de la
     señal de Cumple Jump: +31,00 €»). La API publica el agregado, no de qué se compone;
   · `Order::pendingAtGateLines()` — el desglose de «a cobrar en el parque» con su **etiqueta**;
   · `OrderFinancialSummary::totalFinalNeto()` — el «Total» tras los cambios, que **no es**
     `total_cents` en cuanto hay una cancelación: `total` es inmutable;
   · `OrderFinancialSummary::pendienteDevolucion()` — lo que se le debe al cliente y **aún no ha
     salido**, distinto de `refund`, que es lo ya devuelto.
   ⚠️ **`event_data` NO está en esa lista y no lo estará**: queda fuera **a propósito** (nombres y
   alergias de menores, art. 9) y se pide aparte con `GET orders/{code}/event-data`. Publicarlo en la
   lista paginada sería una regresión de privacidad, no un avance. El test lo declara aparte para que
   nadie lo confunda con un hueco.
2. las siete zonas pintadas y con paridad de datos;
3. los cinco endpoints de gestión abiertos y probados;
4. `EmailChangeController` y el export RGPD con destino decidido (llegan desde un **correo**: no pueden
   depender de que el cajón esté abierto);
5. recorrido en navegador de las siete zonas (`VERIFICACION-E2E-CAJON.md` §5.bis).

### 4.9 ⚠️ La cadena flex del panel: un requisito estructural que NINGÚN test puede ver

**MEDIDO** en `public/css/site.css`: `.sidecart__body` → `#sidecart-spa` → `.purchase` →
`.purchase__scroll` es una cadena de **hijos DIRECTOS** (`flex: 1; min-height: 0` en cada eslabón). Es
lo que produce «el contenido scrollea y el pie queda anclado al fondo». El propio CSS lleva escrito el
aviso: *«un `display:block` en medio la parte»*, y *«el diff de árbol NO puede ver esto: todos sus
casos anclan DENTRO de `.purchase`»*.

▶ Dos consecuencias vinculantes para el enrutado de secciones:
1. **Nada de envolver las secciones en un `<div>` router.** El enrutado se hace con `v-show`/`v-if`
   sobre las secciones mismas; un envoltorio de conveniencia rompería el anclaje del pie **sin que
   falte una sola clase**.
2. **La sección de cuenta necesita su propio eslabón** (`flex: 1; min-height: 0` + un scroll interno),
   o el panel se rompe igual. ⚠️ **Punto abierto a resolver con el CSS delante**: reutilizar `Shell.vue`
   —que emite `class="purchase"` y `data-engine="spa"`, semántica de compra— o darle a la cuenta su
   propio armazón con la misma cadena. Se decide midiendo qué estilos arrastra `.purchase`, no por
   simetría estética.

---

## 5. Impacto en invariantes

| Invariante | Impacto |
|---|---|
| **RGPD-04** (`no-store` con PII) | ✅ **ninguno**: toda respuesta autenticada de `/api/v1` lo lleva por defecto (Fase 3 · paso 0). |
| **RGPD-01/02** (purga, sin PII en logs) | ninguno: esta tanda no escribe. |
| **RGPD-03** (el post-form caduca y da 410 si el titular está anonimizado) | ninguno: se enlaza a la ruta web, que ya lo aplica (§4.7). |
| **PAY-\*** | ninguno en la lectura. ⚠️ El **reintento** entra por `POST /orders/{code}/payment`, que ya es la superficie probada; **no se reimplementa la secuencia** (`ReservationCheckout`, `CheckoutSequenceTest` lo prohíbe ejecutablemente). |
| **AFORO-\*** | ninguno: no se crea ni se retiene aforo. |
| **SEC-\*** | ninguno nuevo. ⚠️ `Origin`/`Referer` son obligatorios en TODA petición stateful (`api-v1.md` §10.sexies 28) — ya lo hace `api.js`. |
| **PERF-02** (presupuesto de queries de la home) | ninguno: la sección se monta con el cajón, que solo se monta al ABRIR. |

Ningún invariante cambia → no hace falta decisión de owner por esta vía (`CONVENCIONES §9.1-2`).
Las decisiones de PRODUCTO de esta spec (alcance y retirada de `/mi-cuenta`) van en `DECISIONES #120`.

---

## 6. Plan de verificación empírica

Cuatro capas, de más barata a más cara. **Ninguna sustituye a la siguiente.**

1. **Módulos planos con `node --test`** (`account/navigation.js`, `orders.js`, `reservations.js`):
   la pila de retorno, la paginación, el agrupado de líneas y el aviso de señal. Es donde vive la
   lógica, así que es donde se prueba (`CE-6`).
2. **Paridad de DATOS — y contra QUIÉN se compara es la decisión importante.**

   ⚠️⚠️ **La v1 de esta spec proponía comparar contra `/mi-cuenta/pedidos`, y estaba MAL.** Esa página
   está condenada (§4.8): una red alimentada por ella **caduca el día que se borre**, y peor, es
   literalmente el error que `DECISIONES #67` corrigió en 4.7·2b·2·B — *alimentar el gate desde el
   motor que se va* hace que el gate nunca ejercite el camino real. No se repite.

   ▶ Son **dos tests con propósitos distintos**, y solo uno es permanente:

   | Test | Compara | Vida |
   |---|---|---|
   | **`SidebarAccountParityTest`** (permanente) | el módulo plano ejecutado en Node **contra la respuesta REAL de `GET /me/orders`** (`OrderResource`/`OrderItemResource`), que es la fuente que sobrevive a la retirada | **para siempre** |
   | **`AccountPageCaptureTest`** (temporal, **con caducidad declarada en su cabecera**) | lo que la **página Blade** pinta hoy contra lo que la **API publica** — para descubrir lo que la página sabe y la API todavía no | **muere con la página**, en la tanda 3 |

   ⚠️ El segundo es la aplicación literal de `#111` —*independizar el contrato ANTES de borrar*— y su
   valor es de una sola vez: **encontrar los huecos**. Ya hay uno medido (§4.7, la URL del post-form).
   Un test temporal sin fecha de caducidad escrita se queda diez años; la lleva en su primera línea.

   ⚠️ **Los dos se verifican por MUTACIÓN**: cambiar un campo tiene que ponerlos rojos. Un caso que no
   se ha visto fallar no es una red (`#65`).
3. **Guardas de arquitectura** (ya existen, no hay que escribirlas — hay que **no romperlas**):
   `SidebarComponentBudgetTest` (techo de 40 y 0 llamadas a la API; la raíz sin `STEPS.`),
   `machine.test.js` (el grafo del embudo sigue cerrado).
4. **Navegador** (`VERIFICACION-E2E-CAJON.md` §5.bis), **obligatorio**: esto toca el orquestador, y
   `scripts/render-sidebar.mjs` **no importa la raíz** — el contrato de árbol no la ejerce. Es la
   lección de `#119(g)` y los tres fallos vivos que ninguna suite vio. Recorrido mínimo:
   compra → cuenta → compra (¿sobrevivió el paso y la cesta?) · reservas · pedidos con acordeón ·
   reintento de un pedido `pending` · 401 con la sesión caducada · el bloque `.acct` colapsado.

⚠️ **Lo que esta spec NO promete, dicho a propósito**: no hay diff de árbol para las zonas de cuenta
(§1.3). Si alguien lo añade después contra `/mi-cuenta/pedidos`, estará comparando un panel con una
página y el test no se podrá pasar sin romper el diseño.

⚠️ **Y lo que NINGUNA de las cuatro capas cubre, que por eso va escrito**: la **cadena flex del panel**
(§4.9). No hay test que la vea —lo dice el propio CSS— y se rompe sin que falte una clase. Entra en el
recorrido de navegador como comprobación explícita: *el contenido de la cuenta scrollea y el pie
sigue anclado*.

---

## 7. Revisión y decisión

- **2026-08-22 · v1** — Redactada tras medir el terreno (rutas de API, guardas, CSS de modos, contrato
  OpenAPI). Alcance «solo lectura» y destino de `/mi-cuenta` los fijó el **owner**.
- **2026-08-22 · v2** — Revisión crítica a petición del owner («¿es esta la mejor manera?»). **Cuatro
  puntos no aguantaron la medición** y cambiaron: la conmutación de sección (§4.1), la paridad de datos (dejó de alimentarse de la página condenada, §6.2), la retirada (muere la
  vista, **vive la ruta**, §4.8) y la **cadena flex del panel**, que no estaba y ningún test puede ver
  (§4.9). El detalle, en `DECISIONES #120(f)`.
- **2026-08-22 · ✅ APROBADA por el owner**: modelo de navegación (§3.1 opción B), modo `account` (§3.3)
  y **no tocar la URL en la tanda 1** (§3.4). Su entrada es `DECISIONES #120`.

## 8. Orden de implementación (por DEPENDENCIA, no por pantalla)

Es la regla que ha ordenado la Fase 4 entera. Cada paso deja `main` verde y verificable.

| # | Paso | Su red |
|---|---|---|
| **1** | ✅ **HECHO 2026-08-22 · El nivel SECCIÓN**: `section.js` + `stores/section.js`, la raíz enruta y publica las señales, el modo `account` con su regla CSS y el armazón `AccountSection.vue` sobre `Shell`. **Sin zonas** | 12 casos de `node --test` · guardas de arquitectura · **navegador**: `VERIFICACION-E2E-CAJON.md` **V4** |
| **2** | ✅ **HECHO 2026-08-22 · La NAVEGACIÓN de zonas**: `account/navigation.js` (zonas, pila de retorno, rótulos) + `stores/account.js`, la sección enruta zonas y nacen `AccountHomeZone` y `OrdersZone`. **Sin datos** | 26 casos de `node --test`, **verificados por mutación** · **navegador**: **V5**, 7/7 |
| **3a** | ✅ **HECHO 2026-08-22 · El CONTRATO**: las etiquetas de presentación que el cliente **no puede** componer (`date_label`, `created_label`, `refunded_label`) y la URL del post-form, con su fuente única `DisplayTime::dayLabel()` | `MeOrdersTest`/`MeReservationsTest` con VALOR · `ApiContractTest` · `DayLabelSingleSourceTest`, **mutado** |
| **3b** | ✅ **HECHO 2026-08-22 · Los DATOS en el cliente**: `account/orders.js`, `stores/orders.js` y `stores/reservations.js`; las dos zonas pintan | 38 casos de `node --test` **mutados** · **`SidebarAccountParityTest`** contra la respuesta REAL, mutado · **navegador V6, 2/2** |
| **4** | ✅ **HECHO 2026-08-22 · La PUERTA**: «Mis reservas» abre la sección en vez de navegar, **conservando el `href`** para cuando el motor aún no ha cargado | `AccountDoorWiringTest` (6 casos, **4 mutaciones**) · **navegador V7, 2/2**, con el chunk del motor cortado |
| **5** | ✅ **HECHO 2026-08-22 · Captura de huecos**: `AccountPageCaptureTest`, temporal y **con su caducidad en la primera línea**. Inventaría la página, clasifica en publicado / hueco / fuera-a-propósito y **congela el contrato de `Order`** | 9 casos, **4 mutaciones** |

⚠️ **El plan pasó de seis pasos a cinco el 2026-08-22, al ejecutarlo.** La v1 separaba «el modelo de
navegación» (2) de «las zonas» (4), y **eso violaba una regla del proyecto**: *extraer sin que el
consumidor lo use es copiar, no extraer* (`DECISIONES #40`). Un módulo de navegación que nadie enruta
no se puede verificar en el navegador, que es justamente la red que esta spec declara. El paso 2 se
entrega con **sus dos zonas pintadas y vacías**, y el 3 las llena.

⚠️⚠️ **El paso 3 se partió en dos al medir, y el motivo cambia el alcance declarado en §1.2.** «Hay
que pintar, no abrir dominio» valía para los DATOS, pero no para su PRESENTACIÓN: medido el
2026-08-22, la API publicaba las fechas **crudas** y el cliente **no puede** componer sus etiquetas —
`Intl` no reproduce en español lo que Carbon compone («Sáb 5 sept» frente a «Sáb. 5 sep.») y la fecha
del pedido lleva la **zona horaria de la instalación**, un ajuste del panel que el navegador no conoce—.
▶ **3a publica las etiquetas**, que es lo que la propia §4.4 ya exigía («el cajón no recompone nada que
el servidor pueda publicar resuelto») y lo que `DEUDA.md` tenía declarado como forma de cerrar la
divergencia de fechas. No es abrir dominio: son campos de presentación sobre datos que ya existían.

⚠️ **El paso 1 va primero aunque no enseñe nada**, y es deliberado: es el único que toca el
orquestador, y la lección de `#119(g)` es que ahí la única red es el navegador. Mezclarlo con las
pantallas haría que un fallo de conmutación y uno de pintado llegaran juntos y sin poder separarlos.


---

## 9. TANDA 2 — las gestiones de cuenta

> Estado: ✅ **diseño**, 2026-08-22. Alcance fijado por `DECISIONES #120(a)–(b)`.

### 9.1 Qué falta, y por qué NO es «pintar»

**MEDIDO** (`#120(a)`): las cinco gestiones **no tienen ningún endpoint**. Y al mirarlas de cerca, el
trabajo tampoco es «abrir cinco rutas»: la lógica vive **dentro de los componentes Livewire**.

| Gestión | Dónde vive hoy | Qué contiene, medido |
|---|---|---|
| Perfil | `Livewire\Account\UpdateProfile` | validación con **doble `unique`** (`email` y `pending_email`), el patrón **`pending_email`** entero —pedir, confirmar, cancelar, reenviar con cooldown—, **dos** notificaciones (al buzón nuevo y aviso al viejo, con el nuevo enmascarado), el manejo de la carrera de UNIQUE y el idioma de la sesión |
| Contraseña | `Livewire\Account\UpdatePassword` | `current_password` + `Password::min(8)->uncompromised()` + `logoutOtherDevices` + `revokeOtherAccess()` |
| Otras sesiones | `Livewire\Account\LogoutOtherDevices` | `current_password` + `logoutOtherDevices` + `revokeOtherAccess()` |
| Borrar cuenta | `Livewire\Account\DeleteAccount` | `current_password` + **`User::anonymize()`** (`RGPD-01`) + cierre de sesión |
| Exportar datos | `Http\Controllers\Account\AccountController::export` | composición del JSON de portabilidad (art. 20) |

▶ **Exponerlas por API sin extraerlas sería DUPLICARLAS**, y eso convierte cada regla en dos sitios que
divergen. Es exactamente lo que Fase 3 evitó con el login: `Identity\Services\PasswordLogin` nació
para que la API y Livewire compartieran los limitadores de `SEC-06`, y el componente se quedó en
**56 líneas** que solo traducen a su interfaz. **Ese es el patrón, y tiene precedente literal.**

### 9.2 La forma: el servicio devuelve un RESULTADO, no lanza

`PasswordLogin::attempt()` devuelve un `LoginResult` y **el componente decide** cómo enseñarlo
—`ValidationException` en Livewire, sobre de error en la API—. Se mantiene: un servicio de dominio que
lanzara excepciones de validación de Laravel estaría decidiendo por sus dos consumidores.

### 9.3 Los endpoints

| Método y ruta | Qué hace | Re-auth |
|---|---|---|
| `PATCH /me` | perfil (nombre, teléfono, idioma) y **solicitar** cambio de email | solo si cambia el email |
| `DELETE /me/pending-email` | cancelar el cambio pedido | no |
| `POST /me/pending-email/resend` | reenviar la confirmación (con su cooldown) | no |
| `PUT /me/password` | cambiar la contraseña | **sí** |
| `POST /me/sessions/revoke-others` | cerrar sesión en los demás dispositivos | **sí** |
| `DELETE /me` | borrar la cuenta (art. 17) | **sí** |
| `GET /me/export` | portabilidad (art. 20) | no |

⚠️ **`PATCH` y no `PUT` para el perfil**: `PUT` significa «reemplaza el recurso entero», y aquí se
envían los campos que el cliente edita. Y **`DELETE /me` no borra la fila**: llama a `anonymize()`,
que es la purga central (`RGPD-01`) — el pedido y su historia contable se conservan sin PII.

### 9.4 ⚠️ La decisión de seguridad, y es una MEJORA sobre la web

**MEDIDO el 2026-08-22**: la web **no limita** los intentos de `current_password` en ninguna de las
cuatro gestiones que lo piden. El endpoint de Livewire no lleva `throttle` propio y las rutas de
`/mi-cuenta` tampoco. Con una sesión secuestrada, un atacante puede **probar contraseñas sin techo**
antes de cambiarla o borrar la cuenta.

▶ **La API se abre CON limitador**, y la regla del proyecto —«los mismos límites que ya aplica la web,
no una copia con otros números»— no se rompe: esa regla existe para no inventar números distintos en
superficies equivalentes, **no para propagar un hueco**. La doctrina que manda aquí es `SEC-06`:
anti-fuerza bruta en auth, y una re-autenticación **es** auth.
⚠️ Y queda anotado en `DEUDA.md` que **la web sigue sin él**: cerrar solo un lado deja el otro abierto.

### 9.5 El corte, por dependencia y por riesgo

| # | Paso | Por qué va ahí |
|---|---|---|
| **6a** | ✅ **HECHO 2026-08-22 · Contraseña + otras sesiones, en el SERVIDOR**: `AccountCredentials` + `CredentialChangeResult`, los dos componentes Livewire consumiéndolo, `PUT /me/password` y `POST /me/sessions/revoke-others` con su contrato | `MeCredentialsTest` (10 casos, **4 mutaciones**) · `ApiContractTest` |
| **6b** | Las dos pantallas en el cajón: zonas `PASSWORD` y `SESSIONS` | `node --test` + navegador |
| **7** | **El perfil** | El más grande con diferencia: el ciclo de `pending_email` entero, con sus dos notificaciones y su cooldown |
| **8** | **Los dos derechos RGPD**: borrado y export | Irreversible uno y con PII el otro. Van juntos y **al final**, cuando el patrón ya esté rodado |

⚠️ **Y dentro de cada paso, el orden que enseñó el paso 3**: primero el **dominio y el contrato**
(`openapi/v1.yaml` manda sobre el código y rechaza lo que no declare), después el cliente.
