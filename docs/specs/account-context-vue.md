# [SPEC] El bloque de cuenta del cajón, a Vue — el último Livewire del layout

> Estado: ✅ **EJECUTADO** (v2, tras revisión adversarial ×3) · Última actualización: **2026-08-23** ·
> Decisión asociada: `DECISIONES #123`. Validado por el owner en navegador
> (`VERIFICACION-E2E-CAJON.md` §5.sexies).
> Ficha de origen: `DEUDA.md` («El bloque de cuenta del cajón es Livewire, no Vue»).

> ⚠️⚠️ **La v1 era INSUFICIENTE y conviene saber por qué antes de leer nada**: no por el diseño
> —los tres revisores lo confirmaron— sino porque **cuatro de sus afirmaciones no salían del
> código**, su inventario de guardas se dejaba la mitad, y **no vio el efecto colateral más grave
> del trabajo**. §8 lleva la lista de lo que cambió y quién lo encontró. La lección, otra vez:
> *verde no es funciona, y razonado no es medido.*

## 1. Contexto y problema

`livewire:site.account-context` es **el único componente Livewire que renderiza
`layout.blade.php`** desde que el modal de auth se retiró (`DECISIONES #122`, 2026-08-23). Vive como
**hermano** de `.sidecart__body` —que es donde monta Vue—, así que **el cajón SPA nunca lo ha
pintado**: es la frontera Livewire↔Vue, y es donde murieron las señales de `#118` (`is-{modo}` e
`identifying` llegaban muertas: panel congelado en `is-catalog` y botones de invitado ACTIVOS durante
la identificación).

Es Livewire desde 2026-06-14 por un motivo medido: el login embebido **no recarga la página**, y un
saludo renderizado en servidor para un invitado se quedaba en «Hola, saltador/a». Escucha
`logged-in` y se re-renderiza en vivo.

### 1.1 Lo que se midió antes de diseñar (2026-08-23)

| Hecho | Cómo se midió | Por qué condiciona el diseño |
|---|---|---|
| **El bloque está OCULTO en `booking`, `cart` y `account`** (`visibility: hidden` + colapso `1fr→0fr`) | `SidebarAccountVisibilityTest` + `public/css/site.css` §«(1) Mi cuenta» | Durante **todo** el carrito, la identificación y el pago **no se ve**. El repintado en vivo solo importa en un camino: entrar en el paso 5 y **volver al catálogo** sin recargar |
| ⚠️⚠️ **Con el bloque fuera, TODA página web pierde `no-store`** | A/B con `curl -s -D -` sobre la misma URL: con el componente, `max-age=0, must-revalidate, no-cache, no-store, private` + `Pragma`; con el hueco, **`no-cache, private`**. La causa está en `vendor`: `SupportDisablingBackButtonCache::boot()` es un **hook de componente** que enciende un flag, y un middleware global estampa la cabecera solo si se encendió | **Es el efecto colateral más grave del trabajo y no lo pone ningún middleware del proyecto** (§4.7) |
| ⚠️⚠️ **`route('logout')` aparece UNA sola vez en toda la aplicación**, y es este Blade | `git grep "route('logout')" -- ':!tests' ':!docs'` → 1 resultado | Retirarlo **deja el sitio sin ninguna salida de sesión** si el chunk no carga. Y desmiente la premisa de `#120(t)` (§4.8) |
| **`.acct` es hijo DIRECTO de `.sidecart__panel`**, hermano de `.sidecart__body` | En `layout.blade.php`, `<livewire:site.account-context />` va justo antes de `.sidecart__body`, y el hueco de Vue está **dentro** de ése | El componente Vue **no puede** ser un tercer hijo de la raíz. Hace falta `<Teleport>` |
| **El motor llega con `import()` en la primera apertura** | `app.js::bootSpaEngine()`, `SidebarBundleBudgetTest::test_the_sidebar_engine_is_a_separate_chunk_loaded_on_demand` | Un bloque pintado por Vue **no está** en el primer fotograma |
| **El entry de la landing tiene techo de 20 kB y Vue no puede viajar con él** | `LANDING_ENTRY_MAX_KB` · `test_vue_never_travels_with_the_landing` | Descarta de raíz «una app Vue ávida solo para el bloque» |
| **`acct--guest` no lo usa NADIE** | `git grep acct--guest` → **1 aparición** (el propio Blade), 0 reglas CSS, 0 JS, 0 tests | Modificador muerto: se retira, no se transcribe |
| **`.acct__tag` es `display: none` siempre** | La regla `.acct__tag` de `site.css` y el comentario que la precede, que admite que se conserva «porque `AccountContextTest` comprueba que su texto viaja en el HTML» | Elemento muerto sostenido por su propio test |
| **`GET /me/reservations` publica la próxima reserva y el contador; `GET /me/orders` publica los formularios pendientes ya RESUELTOS pero PAGINADO** | `openapi/v1.yaml` §`UpcomingReservation` y §`OrderItem` (`needs_guest_form`, `guest_form_url`, `guest_form_status`) · `ListMeta` | Es el motivo REAL del endpoint (§3.3), y no el que decía la v1 |
| **El bloque pesa ~2,2 kB de HTML en CADA página pública, y ~0,7 son andamiaje de Livewire** | `curl` sobre la home anónima. Dos medidas independientes: **2.255 B** / **2.242 B** (la diferencia es el `wire:id` aleatorio); andamiaje `wire:*` **716** / **679 B**; comentarios `[if BLOCK]` **57 B** en las dos | El neto de la migración es **negativo** (§4.11) |
| **`followAccountLink()` solo lo llaman DOS `@click` del propio bloque** | `git grep followAccountLink` → definido en `app.js`, invocado solo desde el Blade que se retira | El puente existe para «el motor aún no ha cargado». Un bloque que solo existe **con** el motor no lo necesita: se retira (§4.9) |
| ⚠️ **`SidebarIconParityTest` está CIEGO a 10 de los 32 `.vue`** | `glob('js/sidebar/**/*.vue')`: `**` **no es recursivo** en PHP. Reproducido: el gate ve 22, hay 32, y los 10 invisibles son **todas** las zonas del área de cliente, con 1 `<svg>` ya fuera de toda paridad | Es un hueco **vivo en `main`**, anterior a este trabajo. Hay que arreglarlo **antes** de escribir un `.vue` con iconos (§6.1 paso 0) |

### 1.2 Lo que esta migración NO compra, y hay que decirlo

**No ahorra `livewire.js`.** Alpine lo trae Livewire (`app.js` no lo importa: usa el global), y de
Alpine cuelga `$store.purchase` —que es lo que ABRE el cajón desde los once puntos de la landing— y
el cajón entero. Retirar el último componente Livewire **no permite retirar Livewire**: convierte
`@livewireScripts` en la **fuente ÚNICA** de un bundle que hoy tiene dos.

**Y no elimina «la frontera»: elimina UNA de las dos.** Lo que muere es Livewire↔Vue. La costura
**Vue↔Alpine sigue viva y en este trabajo gana un escritor**: el `watch` de `Sidebar.vue` escribe
`mode`/`identifying` en el store de Alpine, `app.js` conserva `openAccount()`/`bootSpaEngine()`/
`applyAccountZone()`, y §4.6 añade la escritura de `authChanged`. ⚠️ **Y ahora hay una dependencia
nueva que antes no existía**: la clase `is-{modo}` que produce ese puente —Alpine, sobre un elemento
del layout— es lo que **colapsa un bloque que ahora pinta Vue**. Vue → Pinia → Alpine → DOM → CSS.

Lo que sí compra: una sola arquitectura en el cajón, el bloque dentro del modelo de tres capas
(módulo plano · store · componente) con su `node --test`, y tres piezas muertas menos.

## 2. Objetivo

**Criterios de éxito, medibles:**

1. ✅ **La clase Livewire del bloque y su Blade ya no existen** (estaban en `app/Livewire/Site/` y en
   `resources/views/livewire/site/`), y `git grep -c 'livewire:'` sobre el layout da **0** — medido.
   El servidor sirve **0 atributos `wire:`** en la página, y `livewire.js` sigue llegando.
2. El bloque lo pinta un componente Vue del motor, con su estado en un store propio y sus reglas en un
   módulo plano con `node --test`.
3. **Conducta idéntica, verificada en navegador**: invitado y con sesión, aviso de UNO y de VARIOS
   formularios pendientes, contador, colapso en los tres modos, bloqueo por `identifying`, y el
   repintado tras entrar en el paso 5 sin recargar.
4. **Las cabeceras de caché de una página web NO cambian**, y por primera vez las asevera alguien
   (§4.7).
5. **Cerrar sesión sigue siendo posible sin el motor Vue**, y sin salir del cajón (§4.8).
6. `livewire.js` sigue llegando, y su guarda pasa a discriminar: retirar `@livewireScripts` deja
   `SidebarMountTest::test_livewire_scripts_are_still_served` **en rojo** (hoy da verde, medido).
7. Los presupuestos quedan **bajados a lo medido** al cerrar, y la semilla tiene el suyo.

**Fuera de alcance a propósito:**

- **Rediseño visual del bloque.** El marcado que emite Vue es el que emite el Blade hoy, clase por
  clase.
- **Sacar nada del cajón.** `DECISIONES #66` manda: toda la gestión del cliente vive dentro. La
  propuesta de subir la salida de sesión al nav se hizo y **el owner la descartó** (§4.8).
- **Retirar Livewire del proyecto.** Sigue trayendo Alpine (§1.2).
- **La decisión aplazada de la URL por zona** (`specs/area-cliente.md` §3.4).
- **La concordancia de plural de `account.sidecart.upcoming_count`**: ficha propia en `DEUDA.md`.

## 3. Opciones consideradas

### 3.1 El servidor sigue pintando el bloque y Vue lo RELEVA — **descartada** (owner, 2026-08-23)

Blade estático (sin Livewire) para el primer fotograma; Vue lo sustituye al montar, con un contrato
de árbol que compara los dos marcados.

- **A favor**: cero parpadeo, el bloque existe sin JS, las guardas sobre el HTML servido sobreviven.
- **En contra, y es lo que la descartó**: el marcado vive en **dos sitios para siempre**, y el
  contrato de árbol no ve el texto, ni los `href`, ni el `method`/`action`, ni el interior de un
  `<svg>` (demostrado por mutación en `#113` y `#119(f)`). Es «migrar a Vue» conservando lo migrado.

### 3.2 Un segundo `createApp` sobre el hueco — descartada (técnica)

- **En contra**: dos `app.config` y dos árboles para no ganar nada que un `<Teleport>` no dé, y
  `defineExpose` de la raíz dejaría de alcanzar el bloque.

### 3.3 Repintar tras el login SIN endpoint nuevo — descartada, **y por un motivo distinto del que decía la v1**

Repintar con `POST /auth/login` (perfil), `GET /me/reservations` (próxima + contador) y derivar los
formularios pendientes de `GET /me/orders`.

⚠️ **La v1 decía que eso obligaría a repetir en JS una regla del dominio. Es FALSO, y el código lo
enseña dos veces**: `Http\Resources\Api\V1\OrderItemResource` publica `needs_guest_form`,
`guest_form_status` y `guest_form_url` **ya resueltos por el dominio**, y
`resources/js/sidebar/account/orders.js::guestFormOf()` **ya los consume** sin decidir nada. La
doctrina de `modulos-dominio.md` no se estaba invocando bien.

**Los motivos reales, que sí resisten:**
- **`GET /me/orders` PAGINA** (`ListMeta` con `current_page`/`last_page`, y el propio cajón pinta
  `account.orders.pagination`). Contar formularios pendientes sobre *una página* es contar mal, y
  `upcoming_count` no sale de ahí en absoluto.
- Serían **dos o tres peticiones** —perfil + reservas + pedidos— donde hace falta **una**, y
  justo en el instante en que el cliente está a mitad de una compra.
- **Fase 6 lo quiere igual**: la pantalla de inicio de una app nativa pide exactamente este agregado.

### 3.4 ELEGIDA: el motor pinta el bloque, el servidor le pasa los datos, y la API los publica

## 4. Diseño elegido

### 4.1 El servidor emite el HUECO, no el bloque

```blade
<livewire:site.account-context />                            {{-- hoy --}}
<div id="sidecart-account" class="acct acct--pending"></div>  {{-- después --}}
```

⚠️ **El hueco lleva ya la clase `.acct`, y no es cosmética**: `.acct` es el *flex item* del panel y
lleva su `padding`, su `border-bottom` y su `background`; además es la **rejilla de una fila** que se
colapsa. Un envoltorio duplicaría la caja. *(La v1 decía que el CSS la selecciona «como hijo»:
`.sidecart__panel.is-cart .acct` es un selector **descendiente**. La conclusión no cambia; el motivo
sí.)*

⚠️ **El hueco NO va vacío: lleva el suelo servido** (§4.8) — el formulario de salir y nada más. Nace
**colapsado**, así que no se ve mientras las cosas funcionan.

⚠️⚠️ **Se escribe `.acct.acct--pending`, con DOS clases, y es obligatorio.** `site.css` tiene **dos**
bloques `.acct { }` —uno con la rejilla y la transición, otro más abajo con `padding`,
`border-bottom` y `background`—. Una regla `.acct--pending` a secas tiene la **misma especificidad**
(0,1,0) y la decide el orden de fuente: declarada junto al idioma que copia, el segundo bloque le
devuelve el padding y el borde y el resultado es **una franja crema con un botón dentro** que ningún
test de esta suite puede ver. Con `.acct.acct--pending` (0,2,0) gana a los dos, esté donde esté.

⚠️⚠️ **Y lleva `visibility: hidden`, igual que el colapso por modo, porque el hueco YA NO está
vacío.** Colapsar solo con la rejilla y la opacidad dejaría el botón de salir **invisible pero
TABULABLE** dentro de la trampa de foco del panel —y `a11yPanel.focusFirst()` hace `querySelector`
**sin filtro de visibilidad**, así que además se llevaría el foco al abrir—. Es exactamente lo que
`SidebarAccountVisibilityTest::test_hiding_it_also_takes_it_out_of_the_focus_trap` existe para
impedir en el otro colapso: *ocultar sin sacar del foco no es ocultar*. **Ese caso hay que
duplicarlo para `--pending`.**

⚠️ **Y no promete deslizar para todo el mundo**: los dos bloques `@media (prefers-reduced-motion:
reduce)` de `site.css` ponen `transition: none` sobre `.acct`. Con esa preferencia el bloque aparece
de golpe, que es lo correcto — pero `V20` tiene que recorrer las dos preferencias, y el andamio de
navegador declara `reduce` por defecto en headless (`VERIFICACION-E2E-CAJON.md` §5.bis).

### 4.2 El motor lo pinta desde la RAÍZ, con `<Teleport>`

```vue
<Teleport to="#sidecart-account"><AccountPanel v-bind="props" /></Teleport>
```

**Verificado en el runtime de Vue**: `TeleportImpl.process` inserta dos nodos de **texto vacío** como
anclas y monta dentro — **anexa, no vacía** el destino (a diferencia de `app.mount()`, que sí limpia
su contenedor: por eso desaparece solo el velo de carga). Texto vacío no genera caja, así que la
rejilla de `.acct` sigue teniendo **un solo ítem** y el colapso `1fr→0fr` se conserva. Los comentarios
`teleport start/end` quedan dentro de `#sidecart-spa` y **no** crean *flex items*, así que la cadena
de hijos directos del panel (`area-cliente.md` §4.9) queda intacta.

⚠️ **Va en la raíz por una razón que la v1 no daba bien**: no es que una sección «no pueda pintar
fuera de sí misma» —un `<Teleport>` funciona desde cualquier componente— sino que **`AccountSection`
monta con `v-if`** y el bloque tiene que existir con esa sección apagada.

⚠️ **No rompe `test_the_root_routes_sections_and_does_not_paint_screens`**: cero `STEPS.`, cero
llamadas a la API, y ese caso **prohíbe expresamente** que la raíz tome una excepción de tamaño.
⚠️⚠️ **La holgura es de SIETE líneas, no de veinticuatro**: `Sidebar.vue` no tiene «16 líneas» —ese
número es del comentario de `EXCEPTIONS`, fechado antes de que subiera el puente de
`mode`/`identifying`—. Medido con el algoritmo del propio gate: **33 de 40**. El `<Teleport>` cuesta
1 (el `import`); retirar `--pending` y el store, alguna más. **Si no cabe, no se sube el techo de la
raíz: se busca a quién le tocaba.**

⚠️ **Y el bloque NO se parte en dos componentes «por el techo», porque el techo no lo ve.**
`SidebarComponentBudgetTest::codeLines()` cuenta **solo dentro de `<script>`** —su propia
guarda-de-la-guarda lo fija—, y lo que crece aquí es plantilla: medido, `CatalogStep.vue` son 166
líneas totales y **17** de presupuesto. Un `AccountPanel.vue` único cabe de sobra. **Se parte o no
por legibilidad, y se dice cuál lleva `class="acct__inner"`** (§4.10). *Corolario incómodo que este
trabajo no arregla: **nada presupuesta la plantilla**.*

### 4.3 Los datos: una FORMA, dos caminos

Nace `stores/accountContext.js`, alimentado por dos caminos que publican la misma forma:

| Camino | Cuándo | Qué lleva |
|---|---|---|
| **Semilla** — `accountContext` en el `data-boot` | En cada carga **con sesión** | ⚠️ **Solo lo que el bloque PINTA** (§4.11): saludo, contador, próxima reserva, `pending_forms_count`, y el par `product_name`+`url` **únicamente cuando el contador es 1**, que es el único caso en que el Blade lo pinta |
| **Refresco** — `GET /api/v1/me/account-context` | Solo cuando el embudo consigue sesión sin recargar | La lista **completa** de pendientes: es un contrato de API y Fase 6 la querrá |

⚠️⚠️ **La forma la compone UN solo sitio**: `App\Http\Resources\Api\V1\AccountContextResource`
**(futuro)** —**ése** es el namespace del repo, no `Http\Api\Resources`—, usado por el endpoint y por
el `data-boot`. Sin eso hay dos formas del mismo dato y una envejece sin que nadie lo note.

⚠️ **Sería el PRIMER Resource que se usa fuera de una respuesta HTTP** (medido:
`git grep 'Resource(' -- resources/views` → 0). Ningún gate lo prohíbe —`ApiBoundariesTest` escanea
solo los controladores de API y `ModuleBoundariesTest` exime `Http` como capa de entrega— pero hay
que decidir dos cosas por escrito: que se invoca con `->resolve(request())`, y **qué manda cuando
choquen**. Manda `openapi/v1.yaml`, que es el contrato; por eso la **poda es de la semilla** y no del
Resource, y por eso la semilla y el endpoint pueden diferir en la lista de pendientes sin que eso sea
«dos formas»: es la misma forma, podada en un camino y declarada así.

⚠️ **Y hay un dueño duplicado que hay que resolver**: `stores/reservations.js` ya publica `next` y
`upcoming` desde `GET /me/reservations`, y lo consume `AccountHomeZone.vue`. Con `accountContext`
habría **dos cachés del mismo hecho** refrescadas por caminos distintos, y tras entrar en el paso 5
una se actualizaría y la otra no. **Decisión: el índice del área sigue con `stores/reservations.js`
—lo pide al montar su zona, cuando el bloque ya está colapsado por el modo `account` y no se ve— y
`session-gained.js` invalida los DOS.** Se escribe en el docblock de ambos stores.

### 4.4 El endpoint nuevo — `GET /api/v1/me/account-context`

Publica lo que hoy solo sabe la web, desde `Identity\Services\CustomerAccountContext`, que ya orquesta
el contrato `Booking\Contracts\CustomerReservations`. **No reimplementa ninguna regla.** El
controlador va en `app/Http/Controllers/Api/V1/`, con `MeReservationsController` de precedente exacto.

```json
{
  "first_name": "Ana",
  "upcoming_count": 2,
  "next_reservation": { "date": "2026-09-05", "date_label": "Sáb. 5 sep.",
                        "time_window": "10:00–12:00", "product_name": "Pack cumple" },
  "pending_forms": [ { "product_name": "Pack cumple", "url": "https://…/reserva/12/datos-invitados" } ],
  "pending_forms_count": 1
}
```

⚠️⚠️ **`url` va SIN FIRMAR, y es deliberado.** *(La v1 escribía `?signature=…` en este mismo ejemplo,
y era falso.)* `CustomerAccountContext::build()` compone `route('reservation.guests', …)`, una ruta
web plana; quien autoriza es `Http\Concerns\AuthorizesGuestForm`, que acepta **firma O titularidad de
sesión**, con su escalada 403→410→404. Es exactamente el mismo campo que `OrderItemResource` ya
publica como `guest_form_url`.
▶ **PROHIBIDO publicar aquí `OrderItem::guestFormSignedUrl()` ni `guestFormApiUrls()`.** Serían
credenciales portadoras sin sesión, válidas ~evento+14 días, que abren y **reescriben** nombres y
alergias de menores (art. 9) — y por §4.3 acabarían además sembradas en el HTML de cada página. El
paso 1 lleva una mutación que lo fija: sustituirla por una firmada tiene que dar rojo.

⚠️ **`next_reservation.date` NO lo sabe el servicio.** `CustomerAccountContext::formatReservation()`
devuelve exactamente `dateLabel`, `timeWindow` y `productName` — la fecha cruda se pierde, y su
`@return` lo declara. **Decisión: `AccountContextResource` anida `UpcomingReservationResource`, y para
eso `CustomerAccountContext` publica el DTO `UpcomingReservation` en vez del array ya formateado.**
Es un cambio con **tres consumidores** (`nav.blade.php`, el bloque y `ModuleContractsTest`) y por eso
va en su propio paso (§6.1 paso 1a), no de propina. La alternativa —omitir `date`— dejaría **dos
formas de reserva próxima** en la misma API el día que nace.

⚠️ **`date_label` viaja compuesto por el servidor** y no se recompone en el cliente: medido el
2026-08-22, `Intl.DateTimeFormat` no reproduce «Sáb. 5 sep.» en español. Fuente única:
`Platform\Services\DisplayTime::dayLabel()`.

⚠️ **La consulta necesita SUELO TEMPORAL.** `CustomerReservationsReader::pendingGuestFormsFor()`
filtra por `user_id + PAID + pack` y hace el corte de «ya celebrado» **en PHP**, sobre **todo el
histórico** materializado — a diferencia de `upcomingFor()`, que acota por SQL. Hoy se paga una vez
por render (singleton memoizado); publicado como endpoint pasa a ser repetible bajo `throttle:api`
(60/min por identidad, compartido). **Se le pone el mismo suelo que `upcomingFor()`**, que arregla
además el coste que ya se paga hoy en cada página. Si no cupiera, `throttle` propio y decir por qué.

⚠️ **Va bajo `auth:sanctum` sin `verified`**, coherente con `/me/reservations` y `/me/orders`: el alta
*pay-first* abre sesión sin correo verificado y ese cliente también tiene bloque de cuenta. **Se
declara en el contrato**, en vez de dejarlo implícito, porque `SEGURIDAD.md` regla 6 dice lo contrario
para el área privada de la web.

⚠️ **`no-store` es automático** (`NoStoreWhenAuthenticated` en el grupo `api`), y hay precedente
probado en seis endpoints. **Lo que NO es automático es la otra superficie: §4.7.**

⚠️ **Y una promesa de la v1 que era falsa**: esto **no** deja listo el post-form para Fase 6. `url` es
una ruta **web** y `AuthorizesGuestForm` la resuelve por sesión; un cliente nativo por **Bearer**
recibiría **403**. Para eso existe `guestFormApiUrls()`, y será un campo aparte cuando toque.

### 4.5 Defensivo, como el servicio

`CustomerAccountContext` envuelve su carga en un `try` y devuelve un contexto vacío seguro: una
cortesía de UI no tumba una página. El endpoint y el store heredan esa conducta, y el contrato lo
declara. En el cliente, un fallo del refresco **no se anuncia** — mismo criterio que
`stores/reservations.js::ensure()`.

### 4.6 La costura con el embudo: `logged-in` MUERE

Hoy, al conseguir sesión en el paso 5, `PurchaseSection::notifyLoggedIn()` despacha `logged-in` por el
bus de **Livewire**, y lo escuchan dos: el componente (se repinta) y `app.js` (pone `authChanged`, que
hace que cerrar el cajón recargue).

Después lo hace un **módulo plano**, `account/session-gained.js` **(futuro)**, con el `window` por
parámetro (`CE-6`, patrón de `account/after-auth.js`): refresca el contexto de cuenta, invalida
`stores/reservations.js` (§4.3) y marca `authChanged`. `enterWith()` lo llama y no decide nada.

⚠️ **Se retiran el listener de `livewire:init` de `app.js` y el `#[On('logged-in')]`.** Medido: tras
esto **nadie emite ni escucha `logged-in`**.

⚠️ **`ENGINE_MUST_KNOW` lleva `'logged-in'` como centinela y muere con el evento.** Sustituirlo por
`/me/account-context` a secas **no basta**: esa cadena sigue en el bundle mientras el store se importe,
aunque `enterWith()` no llame nunca al refresco — la trampa medida con `turnstile_token` (6→4
ocurrencias, caso verde con el token sin mandarse).

⚠️⚠️ **Pero la v1 concluía de ahí que «ninguna guarda estática puede cubrirlo», y eso era FALSO.** El
instrumento existe en este repo y nació del mismo fallo: `SidebarIntentWiringTest` escanea **el
fuente** de `resources/js/**` excluyendo los `*.test.*` y exige que un símbolo tenga **consumidor de
producción** — su docblock dice «un consumidor que solo existe en un test no es un consumidor» y cita
`#117`. `SidebarEmitWiringTest` y `SidebarImportWiringTest` son la misma familia.

▶ La red queda en tres capas, y cada una dice lo que puede decir:
- **`SidebarImportWiringTest`/hermano (fuente)** → «alguien en producción, fuera del propio módulo y
  de sus tests, llama a `sessionGained`». **Esta es la que faltaba.**
- **`'/me/account-context'` en `ENGINE_MUST_KNOW` (bundle)** → «el motor sabe pedirlo».
- **`session-gained.test.js` (`node --test`)** → «cuando se le pide, hace las tres cosas».
- Y **el navegador (`V21`)** cierra: que el efecto se ve.

### 4.7 ⚠️⚠️ EL EFECTO COLATERAL GRAVE: las cabeceras de caché de TODO el sitio

**El `no-store` que hoy llevan todas las páginas del layout no lo pone ningún middleware del
proyecto.** Lo pone Livewire: `SupportDisablingBackButtonCache::boot()` es un **hook de componente**
—se ejecuta cuando un componente arranca— y enciende un flag estático que un middleware **global**
(empujado por el propio paquete) usa para estampar `no-store`. El único componente que queda en el
layout es éste.

Medido, A/B sobre la misma URL:

```
con el componente → Cache-Control: max-age=0, must-revalidate, no-cache, no-store, private + Pragma
con el hueco      → Cache-Control: no-cache, private
```

**Por qué es una regresión de `RGPD-04` y no un detalle de rendimiento:**
1. `no-cache` **no** es `no-store`: los bytes siguen pudiendo persistir en la caché **en disco**, y
   `no-store` es lo único que inhabilita el **bfcache** — sin él, cerrar sesión y pulsar «Atrás»
   restaura la página anterior con su árbol y su `data-boot` intactos. Lo dice el docblock de
   `app/Http/Middleware/NoStore.php`, sobre dispositivo compartido.
2. **La página autenticada lleva PII pase lo que pase**: `nav.blade.php` pinta el nombre de pila y el
   puntito de formulario pendiente, y **el nav no se migra**. La cabecera se iría; la PII se queda.
3. Y la semilla (§4.3) **añade** PII a esa misma respuesta.
4. **Nadie lo vería caer**: medido, las 12 aserciones de `no-store` de la suite son de la API, los dos
   PDF, el post-form y el export. **Ninguna mira una página web del layout.**

▶ **Diseño: un gemelo web de `NoStoreWhenAuthenticated` en el grupo `web`**, que reproduce
**exactamente** las cabeceras de hoy, con su caso y su mutación. Va **incondicional**, como hoy: hacer
cacheable en disco el HTML anónimo —que lleva el `<meta name="csrf-token">` de *esa* sesión— es una
decisión de rendimiento con efectos de 419 que merece medirse aparte, no de propina.

### 4.8 ⚠️⚠️ Cerrar sesión: el SUELO va dentro del hueco (decisión del owner, 2026-08-23)

**Medido: `route('logout')` aparece UNA vez en toda la aplicación**, en el Blade que se retira. La
rama con sesión de `nav.blade.php` es un `<button>` que solo abre el cajón: sin `href`, sin salida.
Si el chunk no carga, el cliente se queda **sin ninguna forma de cerrar sesión**.

⚠️ **Y desmiente una premisa escrita**: `ESTADO.md` y `DECISIONES #120(t)` dicen que «Cerrar sesión»
no hace falta en el índice del área porque «ya existe **dos veces** fuera —en el nav y en el bloque».
Existe **una**. Hay que corregir las dos citas; la decisión en sí no cambia.

⚠️⚠️ **La opción de subirla al nav se PROPUSO y el owner la DESCARTÓ**, y es la decisión correcta:
`DECISIONES #66` dice que **toda la gestión del cliente vive dentro del cajón**, y sacar la salida de
sesión a la cabecera habría reabierto el tercer sitio que `#120(u)` y `#122` acababan de cerrar. Se
anota porque la opción no elegida es información.

▶ **El suelo vive DENTRO del hueco**: el servidor pinta ahí el `<form method="POST">` con `@csrf` y
**nada más** —sin datos, sin PII, sin contador—, y el motor lo sustituye por el bloque completo al
montar.

⚠️ **Y no se ve nunca mientras las cosas funcionan**, porque el hueco nace colapsado (§4.1):

| Camino | Qué pasa |
|---|---|
| Normal | Hueco colapsado e invisible → el motor lo **vacía y lo expande** → el bloque entra deslizando. Sin franja vacía |
| **El chunk falla** | El `catch` que ya existe en `bootSpaEngine()` **solo expande** → queda a la vista el «Cerrar sesión» servido, dentro del cajón |
| Sin JS en absoluto | Nada, porque el cajón no abre. **Igual que hoy**: `.sidecart` va con `x-cloak` y solo la abre Alpine, así que el bloque tampoco es alcanzable hoy sin JS. **No es regresión** |

⚠️⚠️ **El hueco tiene UN dueño, y eso no es estilo**: dos escritores de la misma clase es la receta de
que uno llegue tarde —lo pagó `body.no-scroll` con seis escritores y `accountZone` con dos—. Nace
`sidebar/account/host.js` **(futuro)**, módulo plano con el **DOM por parámetro** (patrón de
`account/privacy.js`, probable con `node --test`), con exactamente dos salidas:
`takeOver(host)` —vacía y expande, la llama `index.js::mount()` **antes** de teletransportar, porque
`<Teleport>` **anexa y no vacía**— y `reveal(host)` —solo expande, la llama el `catch`—.

⚠️⚠️ **El 419 se arregla igual, y no dependía del nav.** `AuthSessionController::login()` llama a
`session()->regenerate()`, que **rota el `_token`**. Hoy no se nota porque Livewire re-renderiza el
`@csrf` en servidor; un `<form>` pintado por Vue leyendo el `<meta name="csrf-token">` de la página
quedaría **caducado** tras entrar en el paso 5 → **419**. Por eso el botón del bloque **no pinta
formulario**: llama a `POST /api/v1/auth/logout` por `sidebar/api.js`, que toma el token de la
**cookie** y ya reintenta el 419, y después navega.
▶ El `<form>` del suelo **sí** lleva `@csrf`, y no tiene el problema: lo sirve el servidor en cada
carga de página, y solo se usa cuando no hay motor — es decir, cuando no ha habido ningún login sin
recargar.

⚠️ **Eso quita el `<form>` del marcado del BLOQUE, y el CSS lo nota**: `.acct__logout-form` aporta
`flex: 1` al botón primario. Sin el envoltorio hay que llevar ese `flex` al botón. **Ningún diff de
árbol vería la diferencia**: es una regla de CSS, no un atributo de contrato. Va con su medida en
navegador.

### 4.9 Lo que se retira de paso, con su medida

| Qué | Por qué | Prueba de que está muerto |
|---|---|---|
| `acct--guest` | Ninguna regla CSS, ningún JS, ningún test | `git grep` → 1 aparición: la que lo emite |
| `.acct__tag` (marcado ×2, regla CSS y `account.sidecart.tag` ×3 idiomas) | `display: none` incondicional. Su comentario admite que se conserva **por su propio test** | La regla y el comentario que la precede, en `site.css` |
| El evento `logged-in` | Su único oyente real se va con el componente | §4.6 |
| **`$store.purchase.followAccountLink()`** y su guarda | Existe para la ventana «el motor aún no ha cargado», que **deja de existir**: el bloque solo se pinta con el motor montado | `git grep` → definido en `app.js`, **dos llamantes, los dos en el Blade que se retira**. De regalo, el entry de la landing adelgaza |

⚠️ **`openAccount()` NO se retira**: lo usan los CTA del nav, donde el cajón está cerrado y el motor
puede no existir. Medido: `openAccount($event,'register')` sale **dos veces** de `nav.blade.php`, y ese
recuento lo vigila `AccountDoorWiringTest` **sin tocarse**.

⚠️ **El aviso de UN formulario pendiente se simplifica**: hoy llama al puente con `zone = null` para
decir «deja navegar». En Vue es un `<a href>` **sin manejador**.

### 4.10 La red, y lo que NO la da

⚠️⚠️ **El contrato de árbol NO cubre nada de esto.** `scripts/render-sidebar.mjs` **no importa la
raíz** —no puede: lee `window.Alpine` y el idioma del documento— así que un `<Teleport>` colgado de
ella no lo ve ningún diff. Y aunque lo viera: `href`, `method`, `action`, el texto y el interior de un
`<svg>` **no son atributos de contrato**.

| Capa | Qué la cubre |
|---|---|
| Reglas (inicial del avatar, destino del aviso con uno vs varios, contador) | `account/panel.js` **(futuro)** + su `node --test` |
| Estado (semilla, refresco, sin sesión) | `stores/accountContext.test.js` **(futuro)** |
| Los textos | Aserción sobre el `data-boot` REAL: un rótulo que falte se pinta **vacío** y `i18n.js` no avisa |
| **`identifying`** | ⚠️ **Hoy su ÚNICO guardián de toda la suite muere con el Blade** (§4.11). Hay que **re-hospedarlo contra el `.vue`**, contando las dos ocurrencias como hace hoy |
| Los dos iconos | ⚠️ `SidebarIconParityTest`, **después de arreglar su `glob`** (§6.1 paso 0) |
| El cableado `enterWith → sessionGained` | Guarda de FUENTE, familia `SidebarIntentWiringTest` (§4.6) |
| El colapso `1fr→0fr` | `SidebarAccountVisibilityTest`, re-apuntado **al componente que lleve `class="acct__inner"`** — y hay que decir cuál, o queda inerte (`#65`) |
| **La conducta** | **El NAVEGADOR**: `V20`–`V22` |

### 4.11 Las guardas que cambian de sujeto — **medido, no razonado**

⚠️⚠️ **La v1 decía «verificado caso a caso» y se dejaba cuatro.** Dos revisores independientes
pusieron el hueco y corrieron la suite: **7 rojos**, y es **cota inferior** (no borraron el Blade, ni
la clase PHP, ni tocaron el JS).

| Fichero | Qué le pasa |
|---|---|
| `Site\AccountContextTest` | Sujeto muerto (3 casos `Livewire::test()`). El 4.º es **mixto**: su mitad del tag muere; **la del puente `:class="'is-' + $store.purchase.mode"` es guardián único del layout y se muda** |
| **`Site\CustomerAccountContextTest`** | ⚠️⚠️ **El más importante, y no estaba en la v1.** Sus ~18 casos prueban el SERVICIO y **sobreviven**; dos aseveran sobre el HTML y mueren de sujeto. **Uno de esos dos es el ÚNICO guardián de `identifying` de toda la suite** (cuenta 2 y 2 ocurrencias de las dos expresiones, que solo emite este Blade). `identifying` llegando muerto **es literalmente `#118`** |
| **`Account\AccountAccessTest`** | ❌ no estaba. **Sujeto MIXTO**: mezcla nav (`nav__acct-greet`, `nav__acct-icon`, `nav__acct-dot` — sobreviven) con bloque. Hay que **partirlo** |
| **`Site\Detalles216Test`** | ❌ no estaba. Ancla en `tickets.my_reservations`, que solo emite el bloque |
| `Architecture\AccountDoorWiringTest` | **Cuatro** casos rojos: dos leen el Blade y dos buscan en el HTML cadenas que **solo salen de aquí**. `…the_alpine_bridge_exists…` **muere con el puente** (§4.9). ⚠️ El recuento de los dos `openAccount(…,'register')` es del **nav**: no se toca |
| `Architecture\SidebarAccountVisibilityTest` | Un caso lee la ruta del Blade → al `.vue`. Los otros dos leen el CSS y **no se tocan** |
| `Architecture\SidebarBundleBudgetTest` | `ENGINE_MUST_KNOW`: `'logged-in'` → `'/me/account-context'` (§4.6) |
| `Sidebar\SidebarMountTest` | Gana el puente del modo y la aserción de la semilla. ⚠️ **Y sus DOS `assertSame` de claves exactas se rompen** al entrar `sidecart`/`nav` en el payload anónimo: es ruidoso, no silencioso, pero tiene que estar escrito |

⚠️⚠️ **AUDITORÍA OBLIGATORIA DE `TESTING §2.ter`, que la v1 no hizo.** Esa sección ordena: *al añadir
una clave al `data-boot`, audita quién asevera ese texto contra una página*. Medido: `'Iniciar
sesión'`, `'Cerrar sesión'`, `'Tienes el…'`, `'Tienes pendiente un formulario'`,
`account.sidecart.guest_hello` y `tickets.my_reservations` **se aseveran hoy con `assertSee` contra
`/` y `/mi-cuenta`**. Al pasar al `data-boot` caen en rojo — y **el arreglo «natural» (dejar
`assertSee`, que atraviesa el atributo) los convierte en VERDE FALSO permanente**, que es el hueco de
`#112(e)`. **Cada uno va nombrado en el paso que lo mueve, con su destino, y queda PROHIBIDO por
escrito resolverlo con `assertSee`.**

⚠️ **Al re-apuntar un caso hay que volver a mutarlo** (`#65`).

### 4.12 Presupuestos

| Presupuesto | Techo hoy | Medido | Holgura |
|---|---|---|---|
| Chunk del motor | **199 KiB** | **198,77** (medido al cerrar el paso 3, construyendo) | **0,23 KiB** |
| Textos, montaje anónimo | **2.688 B** | 2.594 | **94 B** |
| Textos, montaje con sesión | **5.720 B** | 5.631 | **89 B** |
| **La SEMILLA** | ❌ **ninguno** | — | — |

**Lo que entra en los textos, medido en los tres idiomas:**

| Rótulos | es | en | fr |
|---|---|---|---|
| Cara de INVITADO (`nav.login`, `sidecart.guest_hello`, `sidecart.guest_sub`) | **135 B** | 112 | 141 |
| Lo que añade la cara CON SESIÓN (6 rótulos) | **+303 B** | +270 | +300 |

✅ `tickets.my_reservations` **ya viaja** en `messages`; `account.sidecart.upcoming_count` **ya viaja**
con sesión; `account.sidecart.tag` **no entra** (se retira).
▶ Los dos techos se rebasan: anónimo **2.729** (por 41) y con sesión **6.069**.

⚠️ **Y el ledger del chunk iba 0,47 KiB optimista**: su última entrada dice **198,3** y el build real
da **198,77**. No es un fallo del presupuesto —sigue verde— pero sí de la cifra desde la que 4b va a
medir su salto, así que se corrige aquí antes de usarla. **Quedan 0,23 KiB**, o sea que el techo
sube en 4b sí o sí, con su medida y su párrafo.

⚠️⚠️ **Y hay un cuarto presupuesto que NO EXISTE: la semilla.** Los dos techos vivos aseveran sobre
`strlen(json_encode([$boot['account'], $boot['auth']]))`; `accountContext` es clave de **primer
nivel** y **no lo mide nadie**. Con `pending_forms` sin cota, un cliente con ocho packs pendientes
arrastraría ocho nombres y ocho URLs en el HTML de **cada** página — **más PII de la que el bloque
pinta**. De ahí la poda de §4.3 y un techo propio, medido **con N pendientes, no con cero**.

⚠️ **Las subidas van en el MISMO commit que los rótulos**, no al final: si no, el paso que los
introduce deja `main` en rojo y el `pre-push` bloquea. **La bajada a lo medido va al cierre.**

⚠️ **El neto es lo que hace legible la subida**: el bloque deja de viajar como marcado —**≈2,2 kB**
menos por página pública— frente a **+135 B** de JSON para un invitado. **≈−2 kB en la ruta de más
tráfico del sitio**, la que `PERF-02` protege. Si al medir no saliera negativo, la pregunta es qué
está de más.

## 5. Impacto en invariantes

| Invariante | Impacto |
|---|---|
| ⚠️⚠️ **`RGPD-04`** | **DOS superficies, no una.** (a) el endpoint: cubierto por el default del grupo `api`, con precedente probado; (b) **la página web, que HOY pierde `no-store` con este trabajo** y encima gana PII con la semilla → §4.7 lo repone con guarda propia. **Sin eso, este trabajo abre un hueco en cada página del sitio.** |
| **`RGPD-03`** (el enlace del post-form CADUCA) | **Se respeta, pero no por lo que decía la v1.** Lo que se publica es la ruta web **sin firmar**; quien protege es `AuthorizesGuestForm` (sesión del titular, con escalada 403→410→404). El enlace firmado del correo no se toca — y §4.4 **prohíbe** publicarlo aquí |
| **`RGPD-01`** (anonimización) | **No filtra**: `User::anonymize()` revoca todas las credenciales, así que un anonimizado no puede autenticarse ni alcanzar el endpoint. Verificado |
| **`PERF-02`** | **Sin cambio**: la rama de invitado no invoca el servicio, y con sesión ya lo invocaba el nav (singleton memoizado). ⚠️ Asimetría de fondo que este trabajo no arregla: **no existe presupuesto de consultas para la home autenticada** |
| **`SEC-06`** | **No aplica**: endpoint autenticado, identidad del guard, sin identificador en la petición → sin IDOR ni enumeración |
| **`SUITE-03`** (tiempo determinista) | ⚠️ **Muerde en tres sitios, no en uno**: `next_reservation`, `pendingGuestFormsFor()` (filtra por «ya celebrado») y `upcomingFor()`. Y `date_label` depende además de **locale y timezone de display**: congelar el reloj **no basta**, hay que fijar el idioma |
| **`VERIFY_CONC`** | **No aplica**: el `CRITICAL_RE` del `pre-push` no alcanza un controlador de cuenta. Comprobar contra el hook, que es la lista viva |

Ninguna invariante **cambia**. Si alguna tuviera que cambiar, es del owner (`CONVENCIONES §9.1-2`).

## 6. Plan de verificación empírica

### 6.0 ▶ DÓNDE VA LA EJECUCIÓN (2026-08-23)

| Paso | Estado |
|---|---|
| **0 · el `glob` de la paridad de iconos** | ✅ **HECHO**. El gate ve 32 de 32; nace `test_the_parity_actually_looks_at_every_component_of_the_drawer`. **Mutado**: con el `glob` anterior cae **solo** la guarda nueva —las otras tres siguen verdes—, que es exactamente el problema |
| **`no-store` de la web** (era «4a») | ✅ **HECHO y ADELANTADO**. Nace `NoStoreWebResponses` (GLOBAL, con la puerta de `/api/v1`) y `NoStoreWebResponsesTest` con 5 casos. **Tabla de verdad de 5 combinaciones medida** y escrita en el fichero. ⚠️ Se adelantó porque **no depende de la migración**: protege `main` aunque esto no llegue nunca |
| **1a · el DTO sin formatear** | ✅ **HECHO**. `CustomerAccountContext` publica `UpcomingReservation`; los tres consumidores adaptados. ⚠️ **Y destapó un hueco**: la sub-línea de la próxima reserva se aseveraba por su prefijo y su producto, **con `:date` en medio sin mirar por nadie** — la parte que este paso mueve al Blade. Cubierta y **mutada** (etiqueta vacía → rojo; fecha cruda → rojo) |
| **1b · el endpoint** | ✅ **HECHO**. `GET /me/account-context` + `AccountContextResource` + contrato + `MeAccountContextTest` (10 casos) + el **suelo temporal** de `pendingGuestFormsFor()`. **Mutado ×3**: URL firmada → rojo · recomponer `next_reservation` a mano → rojo · titular desde la petición → rojo. Verificado con `curl` contra el servidor sobre un cliente real. ⚠️ **Dos hallazgos**: la trampa de `#27` (`$ref` + `nullable` no valida) **también muerde aquí**, así que el esquema va inline con su guarda de no-divergencia; y el pack **sin franja** y el **coste del histórico** no los aseveraba nadie |
| **2 · la semilla** | ✅ **HECHO**. Nace `Http\Sidebar\AccountContextSeed`, por el **mismo Resource** que el endpoint; el `data-boot` la lleva siempre, con `null` para el anónimo (**24 B** medidos). Podada **por cardinalidad y nunca por campo**. Cuatro guardas nuevas en `SidebarMountTest`, **mutadas ×4**: sin poda → rojo · podar por campo → rojo · componer a mano → rojo · campo nuevo → rojo. Medido además: **0 consultas de más** en la home con sesión (41 con y sin), y **320 B** con tres packs pendientes. ⚠️ **Corrección al plan**: los RÓTULOS no entran aquí, entran en 4b — un texto que viaja y no lo pinta nadie es exactamente lo que el presupuesto del montaje existe para cazar |
| **3 · el módulo plano y el store** | ✅ **HECHO**. `account/panel.js` (18 casos) y `stores/accountContext.js` (8), `node --test`. **Mutados ×5**: inicial sin respaldo · con varios pendientes coger el primero · recomponer la fecha · un 401 que no vacía · un 500 que sí borra → los cinco rojos. Medido: **0 ocurrencias en el chunk** (nadie los importa aún, Rollup los poda), así que su coste se paga en 4b |
| **4b · EL RELEVO** | ✅ **HECHO**. El hueco con su **suelo servido**, `ui/account-host.js` (dueño único, 5 casos), `account/AccountPanel.vue`, `account/sign-out.js` (5 casos), el `<Teleport>` de la raíz, `accountStore.openZone()` compartido, los rótulos **incondicionales** y los **dos techos de texto subidos con su medida** (2.688→3.200 y 5.720→6.272), el Blade y la clase Livewire **RETIRADOS**, y **las 8 guardas re-apuntadas o partidas**. Chunk **198,77 → 207,02 KiB** (+8,25), techo a 208. Medido en el servidor: **0 `wire:`**, `livewire.js` sigue llegando, y la home anónima adelgaza **−1.412 B**. ✅ **La predicción de §4.6 se cumple**: retirar `@livewireScripts` ahora deja `test_livewire_scripts_are_still_served` en **ROJO** (antes daba verde) |
| **4c · LA COSTURA** | ✅ **HECHO**. El evento `logged-in` **MUERTO** —medido: nadie lo emite ni lo escucha—; nace `account/session-gained.js` (4 casos) con las tres cosas que hay que hacer; `stores/reservations.js` gana `invalidate()`; se retiran el listener de `livewire:init` y **`followAccountLink()`**, que se quedó con cero llamantes al irse el Blade. El centinela del bundle pasa de `'logged-in'` a `'/me/account-context'`, y **nace la guarda de FUENTE** que §4.6 declaró necesaria: `SidebarIntentWiringTest::…announces_the_gained_session`. **Mutado ×3**, cada uno cazado por la capa que le toca: nadie llama → guarda PHP · no invalida → `node --test` · el aviso cuelga del refresco → `node --test`. ⚠️ El gate del componente hizo su trabajo: `PurchaseSection` subió a **436** y se **bajó a 432** antes de cerrar, moviendo la resolución de stores al módulo |
| **5 · EL BARRIDO** | ✅ **HECHO**. `.acct__tag` retirada —regla CSS y la clave en los tres idiomas—; los cuatro presupuestos **medidos al cerrar** (chunk 207,21/208 · entry 15,74/20 · textos 3.102/3.200 y 6.160/6.272 · semilla 325 B); `composer audit` y `npm audit` en **0**; y la tabla de verdad de `test_livewire_scripts_are_still_served` **re-medida**: hasta hoy no discriminaba y desde hoy sí |

▶ **Cierre: 2669 verdes** (15.213 aserciones) y **624 tests JS**. Los seis pasos del gate, en verde. El contador de PHP
**baja 2** en 4b y es a propósito: se retiran los 4 casos de `Site\AccountContextTest` —sujeto
muerto— y 1 de `Detalles216Test`, y entran 3 nuevos; lo que era guardián único se mudó **antes** de
borrar, y está enumerado en §4.11.

### 6.1 Los pasos, por DEPENDENCIA

| # | Paso | Por qué va aquí | Cierra con |
|---|---|---|---|
| **0** | **Arreglar el `glob` de `SidebarIconParityTest`** (a `RecursiveIteratorIterator`, como los otros cuatro gates) | Hoy está ciego a 10 de 32 `.vue` — **hueco vivo, anterior a este trabajo**. Escribir un `.vue` con iconos antes de arreglarlo es meterlos en el punto ciego: `#113` otra vez | El gate ve 32; el `<svg>` que hoy queda fuera entra bajo paridad |
| **1a** | **`CustomerAccountContext` publica el DTO `UpcomingReservation`** en vez del array formateado | Sin la fecha cruda el endpoint no puede tener la forma de la API (§4.4). Toca **tres consumidores** y merece su diff | Suite verde con los tres consumidores adaptados |
| **1b** | **El endpoint** + `AccountContextResource` + OpenAPI + contrato + suelo temporal | **No toca la web**: se verifica con `curl` antes de mover un píxel | Contrato validado sobre la respuesta REAL + mutación (quitar el scoping por guard; **sustituir la URL por una firmada**) + `curl` |
| **2** | **La semilla** en el `data-boot`, por el mismo Resource, **podada por cardinalidad**, con su presupuesto propio | Una forma antes de que exista un lector. ⚠️ **Los RÓTULOS no van aquí**: un texto que viaja en cada página y no lo pinta nadie es lo que `test_the_mount_payload_stays_pruned` existe para cazar. Van en 4b, con su componente y con la subida de los dos techos **en el mismo commit** | Aserción sobre el `data-boot` real con N pendientes · juego de claves exacto · 0 consultas de más |
| **3** | **El módulo plano + el store** (`account/panel.js`, `stores/accountContext.js`) con `node --test` | Las reglas antes que el marcado (`CE-6`) | `node --test` + mutación de cada regla |
| **4a** | **El gemelo web de `no-store`** (§4.7), con su caso y su mutación | ⚠️ **ANTES del relevo**: la cabecera se pierde en el instante en que el último Livewire deja de renderizarse, así que ponerla después deja una ventana sin ella | `curl` A/B de cabeceras + mutación (quitar el middleware → rojo) |
| **4b** | **EL RELEVO**: el hueco **con su suelo servido** (§4.8), `account/host.js`, `AccountPanel.vue`, **los rótulos y la subida de los dos techos de texto**, el Blade y el Livewire RETIRADOS, y **las 8 guardas de §4.11** re-apuntadas o partidas | Es un nudo real: sin flag y con un solo motor, un paso a medias pinta el bloque dos veces o ninguna. Y el suelo no puede ir antes: vive **dentro** del hueco, que nace aquí | Suite verde + **`V20`** + el caso de foco de `--pending` + **cada guarda re-apuntada RE-MUTADA** |
| **4c** | **La costura**: `logged-in` muere, nace `session-gained.js` con su guarda de FUENTE, se retira `followAccountLink()` | ⚠️ **Se puede separar del nudo y conviene**: el evento puede vivir un commit más sin que nada quede a medias, y así cada guarda se muta con el diff pequeño delante (`#65`) | `node --test` + guarda de fuente + **`V21`** |
| **5** | **El barrido**: mutación de `@livewireScripts`, `acct__tag` y `acct--guest` fuera, presupuestos **bajados a lo medido**, `ESTADO.md` y `#120(t)` corregidos | Lo que solo se puede medir cuando lo de arriba está | Mutación de la directiva (**tiene que dar ROJO**) + ledger + `docs-check` |

### 6.2 Comandos

```bash
docker compose exec -u sail laravel.test php artisan test --parallel
docker compose exec -u sail laravel.test ./vendor/bin/pint
docker compose exec -u sail laravel.test npm run test:js
docker compose exec -u sail laravel.test npm run build        # los presupuestos se miden sobre el build REAL
docker compose exec -u sail laravel.test npm run build:ssr    # ANTES de leer ningún resultado del cajón
bash scripts/docs-check.sh
```

### 6.3 Lo que solo puede decir el navegador

⚠️ **`V19` ya existe y está validado** (`#122`): los guiones nuevos son **`V20`, `V21` y `V22`**. Y el
`V19` vivo ya cubre de reojo «el bloque se repinta tras entrar», así que `V21` lo **profundiza**, no
lo duplica.

- **`V20` — el bloque, sus dos caras y su colapso**: invitado y con sesión · avanzar a día/hora y ver
  que **se colapsa deslizando** · volver al catálogo · entrar en «Mi cuenta» y ver que también se
  colapsa · **no se puede tabular a sus botones colapsado** · y **las dos preferencias de movimiento**
  (`reduce` no desliza, y es lo correcto).
- **`V21` — el repintado sin recarga, y el logout después**: como invitado, cesta → paso 5 → entrar →
  **volver al catálogo sin recargar**: saludo con el nombre, próxima reserva y, con una reserva con
  formulario pendiente, **el aviso**. Y **acto seguido, cerrar sesión** — el caso que el `_token`
  rotado convertía en 419 (§4.8).
- **`V22` — el cajón que NACE ABIERTO** (`/mi-cuenta`, `/login`, la vuelta de la pasarela). ⚠️ Es el
  camino que ya dejó un hueco vacío **dos veces** (`#59(b)`, `#120(u)`) y el único donde
  `--pending` y el modo `is-account` compiten en el mismo tick: hay que mirar que el bloque **no
  entre deslizando para colapsar acto seguido**.
- Y el caso feo: **cortar la red antes de la primera apertura**. El hueco queda colapsado y mudo, la
  consola dice por qué, y **cerrar sesión sigue funcionando desde el nav** (§4.8).

## 7. Un resultado negativo, y por qué dejó de serlo

`a11yPanel.focusFirst()` hace `querySelector` **sin filtro de visibilidad**, así que se midió si un
hueco vacío podía romper la trampa de foco. **Con el hueco vacío, no**: el primer focusable de
`.sidecart` es el botón de cerrar de la cabecera, que precede a `.acct`, y `trap()` filtra por
`offsetParent`.

⚠️⚠️ **Pero la decisión del owner de §4.8 lo convirtió en un riesgo real**: el hueco **ya no está
vacío**, lleva un `<button type="submit">`. Sin `visibility: hidden` en `--pending`, ese botón sería
tabulable —y el primero de la lista, así que `focusFirst()` se llevaría el foco al abrir el cajón—
estando colapsado e invisible. De ahí la regla de §4.1 y su caso.

▶ Se conserva escrito el camino entero, con su resultado negativo incluido, porque **es el ejemplo de
que una medición sigue valiendo cuando cambia la premisa: lo que la invalidó no fue un error de
medida, fue una decisión posterior** (`CONVENCIONES §3.quater`).

## 8. Revisión y decisión

| Fecha | Quién | Qué |
|---|---|---|
| 2026-08-23 | Owner | Elige `account-context` a Vue frente a Fase 5 |
| 2026-08-23 | Owner | **Solo Vue** (§3.1 descartada) · **endpoint nuevo** (§3.3 descartada) |
| 2026-08-23 | **Revisión adversarial ×3** | Veredictos: **sólida-con-cambios · sólida-con-cambios · INSUFICIENTE** |
| 2026-08-23 | Owner | Tras medirse que la salida de sesión existe **una sola vez** y está en el bloque: **descarta subirla al nav** (`#66`: todo vive en el cajón) y el suelo va **dentro del hueco** (§4.8) |
| 2026-08-23 | Owner | ✅ **Validado en navegador**: `V20`–`V22` y el caso sin motor, recorridos y correctos |
| 2026-08-23 | — | ✅ `DECISIONES #123` escrita; la ficha de `DEUDA.md` queda **cerrada** |
| 2026-08-23 | Owner | Pide **nueve retoques** tras validar. Cuatro resultaron ser fallos reales — `DECISIONES #124`, guion `V23` |

### 8.1 Lo que la revisión cambió (y sin lo cual el diseño era inaplicable)

1. ⚠️⚠️ **El `no-store` de todo el sitio** (§4.7). **Nadie lo había visto**, ni el autor. Sin esto el
   trabajo **abre** un hueco de `RGPD-04` en cada página, y ninguna aserción de la suite lo vería.
2. ⚠️⚠️ **La única salida de sesión de la aplicación estaba dentro del bloque** (§4.8) — y desmiente
   la premisa escrita de `#120(t)`. Su arreglo, además, **cambió una medición previa de signo**: el
   hueco dejó de estar vacío y con ello reapareció el riesgo de foco que §7 había descartado.
3. **El inventario de guardas se dejaba cuatro ficheros**, uno de ellos el **guardián único de
   `identifying`** (§4.11). Medido ejecutando la suite, no leyendo.
4. **La auditoría de `TESTING §2.ter` no se había hecho**, y su arreglo «natural» produce verde falso
   permanente (§4.11).
5. **Cuatro afirmaciones del autor eran falsas y salían de su cabeza, no del código**: el
   `?signature=` del ejemplo · el motivo para descartar §3.3 · «ninguna guarda estática puede
   cubrirlo», cuando el instrumento existe en el repo · y `Sidebar.vue` «16 líneas», que son **33**.
6. **`next_reservation.date` no lo sabe el servicio** (§4.4), así que la «forma única» no cerraba.
7. **La semilla no caía bajo ningún presupuesto** y publicaba más PII de la que el bloque pinta
   (§4.12).
8. **`.acct--pending` no funcionaba** por especificidad, y el síntoma —una franja vacía— es invisible
   para la suite (§4.1).
9. **El techo de 40 líneas no mira la plantilla**, así que la razón para partir el componente era
   falsa (§4.2).
10. Y un hueco **ajeno a este trabajo**: `SidebarIconParityTest` **ciego a 10 de 32 `.vue`** (§6.1
    paso 0).
