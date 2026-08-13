# [SPEC] Fase 4 — el sidebar como SPA (Vue 3), primer consumidor real de la API v1

> Estado: 🟦 en revisión (**v3**: v2 tras revisión adversarial ×3, más §4.3.bis y §4.4 rediseñados
> hueco a hueco con revisión de coherencia) · Última actualización:
> 2026-08-13 · Decisión asociada: entrada nueva en `DECISIONES.md` al aprobarse.
> Alcance aprobado por el owner el 2026-08-13: **solo el cajón del sidebar**; `/mi-cuenta` sigue en
> Blade. Tema: **tokens + hoja de estilos por instalación**. Dependencias: Vue 3 + Pinia.
>
> **v2 — la v1 subestimaba el radio de la explosión.** Tres revisores (paridad funcional · tema y
> contrato visual · riesgo de implementación) la declararon INSUFICIENTE · SÓLIDO-CON-CAMBIOS ×2.
> Sus hallazgos están verificados uno a uno contra el código y reescritos aquí; §7 lista qué cambió.
> **La conclusión que lo reordena todo: el sidebar no es un nodo, es un nodo MÁS un bus de eventos
> que cruza la landing, un traspaso por sesión, una superficie de i18n y un presupuesto de bundle.**

## 1. Contexto y problema

`Livewire\Tickets\Purchase` son **1.859 líneas** y su vista **706**: una máquina de **11 pasos**
(catálogo → fecha → hora → cesta → identificación → pago → vuelta → reintento). Es la última
god-class viva y está declarada como deuda desde Fase 2.

⚠️ La doc vigente (`00-REFACTOR.md`, `DEUDA.md`) dice **2.049 líneas**. Son **1.859**, medidas el
2026-08-13. Se corrige en los dos documentos: una cifra inflada en la línea de apertura contamina la
confianza en todo lo demás.

Fase 3 dejó **toda la compra disponible por API**. Nadie la consume desde un cliente real, así que
Fase 4 es a la vez el producto y **la prueba del contrato**.

### 1.1 Lo medido el 2026-08-13 (no asumido)

| Hecho | Consecuencia |
|---|---|
| El tema son *custom properties* en `:root` inyectadas server-side sobre `public/css/landing.css` | Montada dentro del layout, la SPA **hereda el tema sin hacer nada** |
| ⚠️ De las **954 declaraciones** CSS del sidebar, **solo el 25% usa `var(--…)`**: 711 están quemadas (68 `font-size`, 50 `gap`, 48 `font-weight`, 43 `padding`, 16 `border-radius` **existiendo `--r`**) | **«Tokens en BD» son hoy UN color.** `ThemeSettings` emite 7 declaraciones. Una instalación puede recolorear; no puede cambiar densidad, escala tipográfica ni esquinas. §4.3 |
| ⚠️ De **292 selectores** que estilan el sidebar, **90 (31%) no se satisfacen emitiendo la clase correcta**: 89 son estructurales (descendencia, `+`, `:last-of-type`) y 37 dependen del **tipo de elemento** sin clase en el nodo (`.entry__stepper button`, `.purchase__total strong`, `.bk-cta svg`) | **El contrato NO son las clases: es el ÁRBOL.** Era la premisa rectora de la v1 y está falsada. §4.2 |
| ⚠️ **30 selectores viven en `landing.css`**, no en `site.css` (`btn--zone`, `btn--lg`, `btn--ghost`, `zone-tab` **solo** ahí) | La v1 decía «el sidebar se estila en `site.css`». Es incompleto |
| El store Alpine `$store.purchase` lo consumen **11 vistas** (nav, home, pricing, services, price-card, mobile-book-bar, events-section, 404, layout, account-context, app.js) | «Retirar el puente Alpine» no es una operación local del cajón |
| **Tres** vistas de landing hacen `window.Livewire.dispatch('show-packs'\|'show-entradas-zone')` hacia `Purchase` | En modo SPA **no fallan: no hacen nada**. La intención del deep-link se pierde en silencio |
| `is-{modo}` e `identifying` los escribe **solo** el `x-effect` de `purchase.blade.php`, y los consumen `layout.blade.php` y `account-context.blade.php` (**otro** componente Livewire) | En modo SPA nadie los escribe: el panel se queda en `is-catalog` y los botones de invitado siguen activos durante la identificación |
| `purchase.confirmed_code`/`failed_code`/`verifying_code` los escribe `HomeController`/`RedsysReturnController` y **solo `Purchase::mount()` los lee y los olvida** | Sin ese `mount()`, **el cajón se auto-abre en cada página** hasta que caduque la sesión, y la SPA no tiene forma de leerlos |
| `Auth\Login::authenticate()` hace `forget(['purchase.cart', …])` si cambia el titular (**anti-cesta-cruzada** en dispositivo compartido) | `localStorage` no pertenece a ninguna sesión: la cesta de Alice sobreviviría al login de Bob. §4.6 |
| Cada línea de cesta lleva `event_data`; en la instalación sembrada son **nombre del homenajeado (un menor), edad y «Notas (alergias…)»** | Persistirla en el navegador dejaría **dato de salud de un menor** (art. 9) fuera del alcance de `User::anonymize()`. §4.6 |
| `lang/{es,en,fr}/tickets.php` tienen **169 claves cada uno**, y existe un cuarto locale parcial (`zh_CN`, 11 claves) | Los textos del cajón salen hoy de `__()` en servidor. **La SPA no tiene de dónde sacarlos.** §4.5 |
| El JS público pesa hoy **15 kB** y el cajón es `lazy`; `admin/calendar.js` se carga **solo en el panel** | La analogía «como FullCalendar» de la v1 es falsa: el entry del sidebar cargaría en **todas** las páginas públicas. §4.7 |
| `Referrer-Policy: strict-origin-when-cross-origin` manda `Referer` completo en mismo-origen; CSP permite `'self'` y `connect-src 'self'` | La sesión *stateful* y el `fetch` a `/api/v1` funcionan. **Comprobado** |
| ⚠️ CSP quema `localhost:5173`; `.env` fija `VITE_PORT=5374`; **`.env.example` fija 5274** | **Tres valores distintos.** HMR bloqueado hoy, y un clon desde `.env.example` está igual de roto. Se deriva de la config, no se cambia un literal por otro |
| ⚠️ El `pre-push` corre docs-check + Pint + suite. **No corre `npm` en absoluto** | Un commit con un `@vite` nuevo pasa el gate con el manifest viejo → `ViteManifestNotFound` = **500 en toda la web pública** |

## 2. Objetivo

- **CE-1 — Paridad funcional ANTES de retirar nada**, comparable en vivo con feature-flag.
- **CE-2 — La forma del DOM no se rompe**: la SPA emite **el mismo árbol** (etiqueta + clases +
  anidamiento + `role`/`aria-*`/`disabled`) que la vista que sustituye, comprobado por **diff de DOM
  renderizado**, no por extracción de clases del código fuente (§4.2).
- **CE-3 — La tokenización del sidebar SUBE**: el porcentaje de declaraciones **TEMATIZABLES** con
  `var(--…)` no puede bajar y debe crecer. Medido sobre el total daría 25%, pero ese número incluye
  estructura (`display`, `flex-direction`) que no debe ser token: el honesto es **43% → 49% ya
  conseguido**, y lo vigila `SidebarTokenBudgetTest` (§4.3.bis).
- **CE-4 — Ninguna regla de negocio nace en el cliente** (`PAY-12`).
- **CE-5 — Los huecos de API se declaran y se cierran ANTES de necesitarlos.** ⚠️ Sustituye al
  «cero endpoints nuevos» de la v1, que era **falso**: son cinco endpoints y un sexto hueco que no
  es un endpoint (§4.4).
- **CE-6 — La máquina de estados tiene red automática.** ⚠️ Sustituye al «la suite no baja de
  cobertura» de la v1, que era **inalcanzable** sin tests JS (§4.8).
- **CE-7 — El peso del JS público tiene techo declarado y verificado** (§4.7).

**Fuera de alcance**: `/mi-cuenta` (decisión del owner) · la app móvil · **rediseño visual**.

## 3. Opciones consideradas

**(A) — CSS propio por componente. DESCARTADA**: incompatible con «una hoja por instalación», que
necesita asideros estables; y tirar `site.css` obliga a reescribir 3.183 líneas.

**(B) — Emitir el marcado actual, estilado por el CSS actual. ELEGIDA**, y la revisión la refuerza:
al medir que el 31% de los selectores son estructurales, cualquier opción que no replique el árbol
queda descartada con más fuerza todavía.

**(C) — Alpine (0 dependencias). DESCARTADA por el owner.** **(D) — Vue sin Pinia. DESCARTADA por el
owner**, que aportó el requisito que faltaba: tres dominios conviviendo en el cajón.

## 4. Diseño elegido

### 4.1 El sidebar es un nodo MÁS una costura

La v1 decía que la SPA sustituye «exactamente» el nodo `<livewire:tickets.purchase lazy />`. Es
cierto y es insuficiente. Alrededor de ese nodo hay **cuatro costuras** que hoy pasan por Livewire o
por el store de Alpine, y que ningún motor nuevo hereda solo:

1. **Intención de entrada**: tres vistas de landing despachan `show-packs` / `show-entradas-zone`.
2. **Modo y foco**: `is-{modo}` e `identifying`, escritos por el puente y leídos por el layout y por
   `account-context`.
3. **Desenlace del pago**: tres claves de sesión que solo `Purchase::mount()` consume y olvida.
4. **Sesión compartida**: `account-context` es Livewire y escucha `logged-in`; un login por API no
   emite ese evento.

**Decisión estructural**: las cuatro se migran a una **fachada agnóstica de motor** en el store
Alpine —que sobrevive; no es deuda, es el contrato entre la landing y el cajón— **mientras Livewire
sigue siendo el motor**. Eso permite demostrarlas con los tests que ya existen, sin un byte de Vue.
Es el paso 4.0a y es el cambio de orden de más valor de toda la revisión.

⚠️ **Corolario que la v1 decía mal**: el paso final retira el **puente `$wire.step` ↔ store**, no
Alpine. Alpine lo trae Livewire y lo usan el panel de accesibilidad, las cookies, el nav y las
ofertas.

### 4.2 El contrato visual es el ÁRBOL, no las clases

Medido: **90 de 292 selectores** no se satisfacen emitiendo la clase correcta. Ejemplos que romperían
con un contrato de clases en verde:

- `.bk-paybreakdown + .bk-foot { border-top: 0 }` — un wrapper de Vue entre ambos devuelve un doble
  borde en la pantalla de pago, en silencio.
- `.sidecart__body > .purchase > .purchase__scroll` sostiene el **scroll y el footer sticky** por una
  cadena `flex` de hijos directos: un nodo raíz de montaje en medio la colapsa.
- `.entry__stepper button`, `.purchase__total strong`, `.bk-cta svg`: emitir `<div>` en vez de
  `<button>` pierde el estilo con el contrato de clases cumplido al 100%.

**Por eso el contrato se construye desde el DOM RENDERIZADO de los dos motores**, no extrayendo
`class="…"` del código fuente. La v1 proponía lo segundo y no es falsable: hay clases construidas por
concatenación (`cal__day--{{ $cell['type'] }}`, `addons__badge--{{ $opt['badge'] }}`) cuyos nombres
reales **sí** están estilados y que un extractor pierde; `@class([...])` cuyas claves de array
produce como falsos positivos; y `:class` de Alpine que devuelve el texto de la expresión. Además la
**carcasa del cajón** (`sidecart*`, `is-open`) vive en `layout.blade.php`, fuera de su alcance.

`SidebarDomContractTest` (futuro): renderiza los 11 pasos con cada motor sobre el mismo fixture,
normaliza (etiqueta, clases ordenadas, `role`, `aria-*`, `disabled`, lista blanca de `data-*`/`id`) y
**diferencia el árbol**. Al retirar el Livewire, el árbol normalizado se congela como manifiesto.

### 4.3 Tokenizar `site.css` es parte de Fase 4, no de Fase 5

Medido: **el 75% de las declaraciones del sidebar están quemadas** y no existe escala de espaciado,
sombra, tamaño/peso tipográfico ni duración. Con eso, «una hoja por instalación» solo permite
recolorear — que es justo lo que el owner **no** ha pedido.

**Fase 4 es el único momento en que alguien recorre esos 225 bloques nodo a nodo.** Hacerlo en Fase 5,
con el marcado ya en Vue, obliga a una **segunda verificación completa de paridad visual** — el mismo
coste con el que §3 descartó la opción (A). Por eso sube aquí, como paso propio y **antes** de
transcribir.

✅ **[DECIDIDO] 2026-08-13 — el owner**: la tokenización entra en **Fase 4** (paso 4.0c), antes de
transcribir. El argumento que decidió: verificar la paridad visual una vez en lugar de dos, y que
hasta entonces «una hoja por instalación» solo permitiría recolorear — que no es lo que se pidió.

#### 4.3.bis Qué hay que tokenizar EXACTAMENTE (medido el 2026-08-13)

El «75% quemado» de §1.1 es cierto y **engañoso si se toma como plan de trabajo**: incluye
propiedades de ESTRUCTURA (`display` ×75, `flex-direction` ×28, `align-items` ×26, `cursor` ×21…)
que no deben ser tokens — un `display: flex` no se tematiza. Medido sobre los 270 bloques del
sidebar (165 clases, `site.css` + `landing.css`): 1.084 declaraciones, 280 con `var(--)`, **804
quemadas, de las cuales solo 388 son TEMATIZABLES**. Ese 388 es el trabajo real.

| Propiedad | Usos | Valores distintos | Lectura |
|---|---|---|---|
| `font-size` | 74 | 17 | 13/14/12/11px cubren **50 de 74** → escala de ~6 pasos y una cola corta |
| `font-weight` | 51 | **5** | 700/600/800 cubren **49 de 51**. Es el más barato de todos |
| `gap` | 52 | 13 | valores de 2 a 16px → una escala de espaciado los absorbe |
| `padding` | 47 | — | misma escala que `gap` |
| `transition` | 28 | 22 | casi todos únicos: **NO se tokeniza la declaración**, sí la duración y las 2-3 curvas |
| `border-radius` | 18 | 9 | ⚠️ `--r`/`--r-sm`/`--r-lg` **ya existen** y no se usan; falta un `--r-pill` (999px ×6) |
| `line-height` | 18 | 8 | escala corta |
| `letter-spacing` | 11 | 8 | casi todo único: **no merece escala**, se deja |

**Y una buena noticia que corrige el pronóstico**: los colores CRUDOS del sidebar son solo **10 usos
y 8 valores**, no un mar. Seis son variantes alfa de `rgba(20, 19, 15, …)`, que es exactamente
`--fg` (`#14130F`): se convierten en `color-mix(in srgb, var(--fg) X%, transparent)` sin inventar
nada. El agujero real del white-label **no es el color: son las escalas de tipografía y espaciado**,
que hoy no existen en absoluto.

⚠️ Corolario para el orden de trabajo: `font-weight` y `border-radius` son casi gratis (5 y 9
valores, con tokens ya existentes en el segundo) y se hacen primero; `font-size` y el espaciado son
el grueso; `transition` y `letter-spacing` se dejan fuera salvo la duración. Y CE-3 debe medirse
sobre las **tematizables**, no sobre el total, o premia tokenizar un `display`.

Además, dos cosas que hay que dejar hechas o Fase 5 se encarece:
- **el punto de carga** de la hoja por instalación, decidido contra la cascada: `@vite(...)` va
  **después** de `site.css`, así que cualquier CSS que emita un entry de Vite ya le gana;
- **el aviso de que el tema viaja hoy en un `<style>` inline**, que solo funciona porque la CSP lleva
  `style-src 'unsafe-inline'`. La Fase 9 se ha comprometido a quitarlo: ese día muere el white-label
  si no se ha movido a una ruta servida.

### 4.4 Los huecos de API — v3, tras diseñarlos uno a uno contra el código

> **v3 (2026-08-13)**: cinco agentes diseñaron un hueco cada uno midiendo contra el Livewire actual,
> y un sexto revisó la coherencia del conjunto (**SÓLIDO-CON-CAMBIOS**). Lo que sigue sustituye a la
> tabla de la v2. Dos afirmaciones de la v2 eran **falsas** y están corregidas abajo; y **hay un
> SEXTO hueco** que ninguno de los cinco cubría.

#### 4.4.0 Dos correcciones de hecho (verificadas antes de escribirlas)

1. ⚠️ **Los CTA del aviso de pausa NO son una cascada.** La v2 decía «tres CTA en cascada (`tel:` →
   WhatsApp → `/contacto`)». Medido: el teléfono y el WhatsApp se pintan **a la vez**, en dos `@if`
   independientes, y `/contacto` aparece **solo si faltan los dos**. Implementar la cascada habría
   sido una regresión funcional silenciosa.
2. ⚠️ **La resolución de complementos NO depende de la fecha de la línea.** Los **cuatro** llamantes
   de producción pasan `Carbon::today()` —`Purchase::addonViewModel()`, `CreateManualOrderPage`,
   `OrderCreator` y `CartPricer::resolveAddons()`, este último con el comentario explícito de que
   «los complementos no tienen fecha propia»—. Depende del PRODUCTO, del ESTADO DE LA SELECCIÓN y de
   la CANTIDAD. Aceptar una fecha en el endpoint habría inventado una divergencia que hoy no existe.

#### 4.4.1 La superficie que se añade

| # | Endpoint | Auth | Qué resuelve |
|---|---|---|---|
| 1 ✅ | `GET /me/reservation-eligibility` | sesión/Bearer | **HECHO 2026-08-13.** El aviso temprano de `mayReserve()`, que **no consume ficha**. Sin parámetros: es media defensa anti-oráculo. Nace `Http\Api\AdmissionCodeMap` (el código público NO es la constante del dominio) y `admitReservation`/`admitPaymentRetry` quedan **prohibidos fuera de `app/Domain`** por `CheckoutSequenceTest` |
| 2 | `GET /config` | público | Los cuatro ajustes de instalación: bloque de registro externo, umbral del buscador, *sitekey* de Turnstile y tope de líneas |
| 3 | `GET /booking/status` | público | La pausa de reservas y su aviso (título, mensaje y contactos), **traducidos** |
| 4 | `GET /orders/{code}` **ampliado** + `GET /orders/{code}/event-data` | sesión/Bearer | El resumen del paso 6. Lo que no es PII amplía el esquema existente; **las respuestas del pack van en endpoint aparte** |
| 5 | `POST /catalog/products/{product}/addons` | público | Los complementos RESUELTOS |

**Por qué `/config` y `/booking/status` están separados**, aunque los dos sean públicos y se pidan
en el mismo momento: `/config` es **estático por despliegue** y `/booking/status` es **estado que la
dueña cambia con clientes navegando**. Un payload inyectado en el montaje sirve para lo primero y
miente para lo segundo — una landing puede quedarse abierta horas. Y hay un motivo de principio:
la app de Fase 6 no tiene punto de montaje, así que un aviso que solo exista en Blade **no puede
enseñarlo nunca**.

⚠️ **Los tres «menores» tienen UNA casa: `/config`.** Dos diseños los proponían a la vez en el
payload de montaje y en el endpoint. Se resuelve a favor del endpoint (API-first, y tiene test de
contrato). El payload de montaje se queda solo con la i18n (§4.5).

#### 4.4.2 El SEXTO hueco, que ninguno de los cinco vio

**Validar una línea ANTES de meterla en la cesta.** Hoy lo hace `Purchase::addToCart()` en el
servidor; con la cesta en `localStorage` (§4.6) **no queda ningún ida y vuelta al añadir**. Lo que
se le escaparía al cliente:

- **La fusión de líneas**: `findCartIndex()` fusiona cantidades **solo** si el producto no es pack
  y no lleva complementos. Es una regla de composición que cambia cantidad, señal y ocupación.
- **`missingRequiredEventFields()`** con la semántica de `sanitizeAnswerValue()`: para un campo
  `number` aplica `preg_replace('/\D+/','')`. **Consecuencia medida**: la EDAD del menor contestada
  «cinco» el servidor la ve **vacía** y cualquier validación ingenua en Vue la ve **contestada**.
  Hoy el cliente lo descubre en el paso 3, con el campo resaltado y los nombres de lo que falta; con
  la SPA lo descubriría **cinco pasos después**, ya identificado, con un 422 que **ni siquiera
  nombra los campos** (`line_event_required` viaja con el contexto de producto/fecha, nada más).
- El tope de cesta (`>` en servidor, `>=` en la UI) y el re-tope de cantidad.

**Es el único hallazgo de toda la revisión que, si se ignora, se descubre en producción y no en la
suite.** Salidas: un endpoint de validación de línea, o transcribir `missingRequiredEventFields` a
`machine.js` con test —incluido el caso `number`— y **firmarlo por escrito**. ⚠️ Decisión pendiente.

#### 4.4.3 El presupuesto de PETICIONES, que es lo que se nota

Arranque: de 2 a **4** peticiones públicas y paralelizables. Asumible.

⚠️ **El problema es el paso 3.** Con el diseño inicial del hueco 5 —resolver complementos y
presupuestar en dos llamadas—, cada clic en un complemento cuesta **2 peticiones**, en la pantalla
con más clics del embudo: una configuración típica son 8–12 clics → **16–24 peticiones**. Y la mitad
recorren el N+1 de `AddonResolver` que `DEUDA.md` ya mide. Además `throttle:api` es **60/min
compartido** con `availability/*`, `catalog/*` y `orders/quote`: 30 clics agotan el suelo y dejan al
cliente sin poder consultar horas — y detrás de un NAT el cubo es por IP.

**Decisión**: `POST /catalog/products/{product}/addons` devuelve **también** el dinero de la línea,
**delegando en el contrato `CartPricing`** —la misma implementación que sirve `orders/quote`, no una
segunda aritmética—. Baja el paso 3 a **1 petición por clic** y elimina de raíz que el cliente tenga
que traducir `choices` → `addons[]`. `PAY-12` exige una sola fuente de CÁLCULO, no una sola URL.

⚠️ Y hay un fallo silencioso que esa decisión también cierra: `CartPricer::resolveAddons()` tiene un
`catch (Throwable) { return []; }`, así que una selección que el resolutor rechace **tarifica la
línea sin complementos** — el pie mostraría un total sin ellos mientras las filas muestran sus
importes, sin error visible.

#### 4.4.4 Lo que hay que resolver antes de escribir código

- **`ApiContractTest` se pondría rojo el primer día**: exige que `required` sea **exactamente** las
  propiedades y **en el mismo orden**. Los esquemas de petición con campos opcionales necesitan su
  entrada en `OPTIONAL_BY_DESIGN`, y los campos nuevos de `OrderItem` van **a la cola** de
  `properties` y de `required`.
- **`Vary` y `Cache-Control` se deciden para la SUPERFICIE pública, no por endpoint.** Los dos
  diseños públicos proponían políticas distintas, y uno afirmaba que basta `Accept-Language`: es
  **falso**, `ApiLocale::resolveLocale()` mira la **sesión primero**.
- **Quién manda sobre el bit de pausa**: lo publican dos endpoints (uno público, uno autenticado) y
  pueden discrepar. Regla: el aviso lo pinta siempre `booking/status`; la elegibilidad solo dispara
  un refresco.
- **Un nombre que mentiría**: `Order.needs_guest_form` colisionaría con el `needs_guest_form` que ya
  existe en `OrderItem`, y **el agregado no es el agregado** (`Order::guestFormItems()` descarta las
  líneas canceladas antes de preguntar).

#### 4.4.5 Orden de implementación (del más seguro al más arriesgado)

`GET /me/reservation-eligibility` → `GET /config` → `GET /booking/status` → resumen del paso 6 →
**complementos resueltos, el último**. El de complementos es el único que **cambia la semántica de
un método del dominio** consumido por dos superficies vivas (la compra pública y el pedido manual
del panel), el único que puede hacer que la SPA se note lenta, y el que arrastra la decisión del
§4.4.3. El de elegibilidad va primero porque su guarda —prohibir `admitReservation` fuera del
dominio— es la que impide que los cuatro siguientes consuman ficha por navegar.

<details>
<summary>Tabla de la v2 (conservada: describe el porqué de cada hueco)</summary>



| Hueco | Qué falta hoy | Por qué no vale reimplementarlo en el cliente |
|---|---|---|
| **Complementos resueltos** | La API publica la CONFIGURACIÓN (`included`, `mandatory`, `per_guest`, `choice_group`, `requires_addon_id`…). `AddonResolver::viewModel()` devuelve además la partición en grupos, los tres textos de `note`, el `badge`, el `available` tras podar **en cadena** las dependencias, los topes, y la regla de que **un complemento de pago sin tarifa ese día no se ofrece** | Reimplementar la cadena de dependencias en Vue es exactamente lo que CE-4 prohíbe |
| **Admisión sin consumir** | `Purchase::proceed()` usa `mayReserve()`, que **solo consulta**, para avisar antes de llevar al pago. La API solo tiene `POST /orders`, que **crea y retiene aforo** | Sin esto se pierde el aviso temprano, o se gasta ficha por navegar — y eso rompe la segunda compra del mismo minuto, que tiene test |
| **Reservas en pausa** | Hoy el cajón **sustituye el flujo entero** en 6 pasos con título y mensaje editables por idioma más tres CTA en cascada (`tel:` → WhatsApp → `/contacto`). La API solo devuelve 409 **después** de intentar crear | El contenido es *data-driven* y no viaja por ningún endpoint |
| **Resumen del paso 6** | El esquema `OrderItem` no publica `is_pack`, los pares etiqueta/valor de `event_data`, ni el desglose de señal por línea | Sin ellos desaparece «Señal X € · resto en el parque», que se puso porque etiquetar el agregado engañaba en cestas mixtas |
| **Bloque de registro externo** | `registrationPrompt()` compone URL saneada + etiqueta + descripción desde `Setting` | *Data-driven*; ningún endpoint lo sirve |

Menores, del mismo tipo: el umbral del buscador (`CatalogSettings::searchMinItems()`), la *sitekey*
de Turnstile y `MAX_LINES_PER_CART`.

</details>

**Decisión**: se cierran en un paso propio de API (4.0b), **antes** de que la SPA los necesite, con
su esquema en `openapi/v1.yaml` y sus tests de contrato. Añadir es evolutivo; improvisarlos a mitad
de la transcripción es como se rompe un contrato público.

### 4.5 i18n: 169 claves × 3 locales, y hoy no hay canal

Los textos del cajón salen de `__('tickets.*')` en servidor, y las fechas de `Carbon->locale(...)`.
La API solo devuelve traducido el `message` del sobre de error.

**Decisión**: el servidor inyecta en el punto de montaje un **payload JSON** con el grupo `tickets`
del locale activo. Cero dependencias, cero endpoints, las traducciones siguen en `lang/`.
⚠️ Las fechas las formatea hoy Carbon en servidor; si la SPA usa `Intl`, **el resultado visible puede
diferir** (`bk-context`, cabeceras del calendario). Se compara explícitamente en la paridad.

### 4.6 La cesta: dónde vive, qué NO se guarda y de quién es

Hoy vive en la **sesión del servidor**, y de ahí salen **tres** propiedades que la paridad obliga a
conservar — la v1 solo vio dos:

1. sobrevive a un login (la sesión conserva sus datos al regenerarse);
2. sobrevive a irse a leer el correo de verificación y volver;
3. ⚠️ **se BORRA si cambia el titular** (`Auth\Login::authenticate()`), que es la defensa
   anti-cesta-cruzada en un dispositivo compartido — el caso de la tablet del parque.

**Decisión: `localStorage`, sin `event_data`, y CON el propietario dentro.**

✅ **[DECIDIDO] 2026-08-13 — el owner**: no se persiste `event_data`. En la instalación sembrada son
nombre de un menor, su edad y sus alergias; dejarlos en el navegador los pone fuera del alcance de
`User::anonymize()` (`RGPD-01`). Al restaurar, las líneas de pack piden esos campos otra vez. Es la
**única desviación consciente de la paridad** de toda la fase; se anota para que nadie la «arregle».

⚠️ **Añadido en v2**: la cesta persistida guarda el id del titular que la creó, y el store `auth`
la purga en cuanto la identidad cambia. Sin esto, mover la cesta a `localStorage` **destruye** la
defensa (3), que es una regresión de seguridad que la v1 no vio.

Y se conservan los dos saneados que hoy protegen la cesta y que con `localStorage` hacen **más**
falta que con una sesión (que caduca; `localStorage` no): descartar líneas de un formato anterior, y
**podar las líneas cuyo producto dejó de ser vendible** — sin lo segundo quedan líneas fantasma que
cuentan en el badge, no se pintan y rompen el checkout.

### 4.7 Peso del JS y política de montaje

La landing pública sirve hoy **15 kB** de JS propio y el contenido del cajón es `lazy`. Montar Vue +
Pinia + 11 pasos en el bundle de todas las páginas públicas es un orden de magnitud más, y **durante
la convivencia del flag se enviarían los dos motores**.

**Decisión**: entry cargado con `import()` dinámico **en la primera apertura del cajón** —el
precedente correcto ya existe en el repo con `html2canvas`— y **techo de kB declarado y verificado
en CI** (CE-7). Hoy no existe ningún gate de tamaño de bundle.

⚠️ La v1 invocaba `PERF-02` para esto y **está mal atribuido**: `PERF-02` es el presupuesto de
CONSULTAS de la home, no de bundles. El riesgo real que sí toca `PERF-02` es otro: si la raíz Vue
monta con avidez y pide `catalog/*` en cada carga de landing, añade una petición por visita en la
ruta de más tráfico. Por eso se monta al abrir, no al cargar, y se comprueba bajo el flag.

### 4.8 Red automática para la máquina de estados (CE-6)

La v1 declaraba que no habría tests de componentes Vue y se conformaba con los tests de endpoints.
No sirve: **el servidor no es lo que se mueve**. Hoy la máquina está cubierta por seis ficheros de
test de Livewire; transcribirla sin red es una pérdida neta de cobertura.

**Decisión, con cero dependencias nuevas**: la máquina de estados se escribe como un **módulo JS
plano, sin un solo `import` de Vue** (`resources/js/sidebar/machine.js`, futuro): transiciones,
intención pendiente, restauración de cesta y el ramificado sobre los **doce `error.code`** de
`POST /orders`. Se prueba con el runner integrado de Node (`node --test`), que ya está disponible
porque Vite 8 exige Node 20+. Los componentes quedan sin test unitario **a propósito**: son marcado,
y de eso responde el diff de DOM de §4.2.

### 4.9 Convivencia: el feature-flag, y lo que NO compra

Setting `sidebar.engine` (`livewire` por defecto · `spa`), editable en el panel.

⚠️ Tres avisos que la v1 no daba:
- la bifurcación **no es solo el `@livewire` del layout**: alcanza a la fachada de intención, al
  `@vite`, al payload de i18n y al aviso de pausa;
- **`@livewireStyles`/`@livewireScripts` se quedan en los dos modos**: los modales de auth y
  `account-context` son Livewire, y **Alpine lo trae Livewire**;
- **el flag hace que el significado de la suite dependa de una fila de BD**: hay tests que afirman
  marcado del cajón desde `GET /`. Se fija `sidebar.engine` explícitamente en el fixture y se
  **añaden** casos espejo para SPA, en vez de mutar los existentes.

### 4.10 El corte en pasos (reordenado por la revisión)

| Paso | Qué entra | Por qué aquí |
|---|---|---|
| **4.0a** | **La costura, aún en Livewire y sin Vue**: fachada de intención (§4.1), consumo de los códigos de Redsys fuera de `mount()`, contrato escrito de `mode`/`identifying`, `sidebar.engine` fijado en el fixture, `alpinejs` fuera de `package.json` (está declarado y nadie lo importa) | Todo demostrable con los tests que YA existen. Los cimientos reales están del lado Livewire |
| **4.0b** | **Los cinco huecos de API** (§4.4) con su OpenAPI y sus tests | Antes de que la SPA los necesite; improvisarlos a mitad rompe el contrato |
| **4.0c** | **Tokenizar `site.css`** (§4.3): escalas de espaciado, tipografía, sombras y duración; los 711 literales pasan a `var(--…)` | Único momento en que se recorre ese CSS nodo a nodo sin pagar una doble verificación visual |
| **4.1** | Cimientos SPA: dependencias, entry con `import()` diferido + techo de kB, montaje, cliente HTTP, payload i18n, `SidebarDomContractTest`, puerto de Vite **derivado** de la config, `npm run build` en el hook, `node --test` | Sin negocio |
| **4.2** | Pasos 1–3: catálogo, calendario, hora+cantidad **y complementos** | ⚠️ Los complementos son del paso **3**, no del 4 — la v1 los ponía mal, y su footer ya es dinero |
| **4.3** | Paso 4: cesta y presupuesto | |
| **4.4a** | Identificación de un usuario **ya autenticado** | |
| **4.4b** | Registro embebido + restauración de cesta | ⚠️ **El paso más peligroso**, y no el de pagar: es el único que cruza la frontera Livewire↔Vue en los dos sentidos (modal de auth y `account-context` son Livewire), y no tiene guardián en servidor |
| **4.5** | Pasos 8–9: confirmar y auto-POST | Transcripción recta sobre invariantes ya guardados |
| **4.6** | Pasos 6, 10, 11 **y la vuelta de Redsys entera** (§4.1, costura 3) | Entre 4.5 y 4.6, quien pague con el flag activo vuelve a un cajón mudo: **no se despliega el flag entre ambos** |
| **4.7** | Retirada: `Purchase.php`, el puente `$wire.step`↔store, el flag; congelar el manifiesto de DOM | La paridad se cierra **al final de cada paso**, no toda aquí |

## 5. Impacto en invariantes

| ID | Qué exige |
|---|---|
| `RGPD-01` / §3 | La cesta con `event_data` no se persiste en el navegador (§4.6) |
| **Seguridad de sesión** | La cesta persistida lleva su propietario y se purga al cambiar de identidad (§4.6). Sin esto se pierde una defensa que hoy existe |
| `PAY-12` | La SPA **pinta** importes; no los suma |
| `AFORO-02` | La oferta lleva la cesta, y el selector se acota con `max_quantity`, no con `available` |
| `SEC-06` | Los limitadores viven en la API; la SPA no puede suavizar un 429 reintentando sola |
| `PERF-02` | **No cubre bundles.** Lo que sí exige: no añadir consultas por carga de landing (§4.7) |
| `RGPD-04` | Las respuestas autenticadas van `no-store`; la SPA no las cachea |

## 6. Plan de verificación empírica

- **`SidebarDomContractTest`**: diff de árbol normalizado entre los dos motores, los 11 pasos.
- **Presupuesto de tokenización** (CE-3): el % de `var(--)` en las reglas del sidebar no baja.
- **`node --test`** sobre la máquina de estados (CE-6): transiciones, intención pendiente,
  restauración de cesta, purga por cambio de titular, y los doce códigos de negocio.
- **Techo de kB** del JS público (CE-7) verificado en el hook.
- **Guion de paridad escrito**, cerrado al final de CADA paso: los 11 pasos y sus caminos raros —
  pausa, tope de pendientes, frecuencia, agotado, fuera de horario, denegado, vuelta *data-less*,
  carrera notificación-antes-que-navegador, pedido caducado durante el sondeo, `NOT_RETRYABLE`,
  sesión perdida entre pasos, anti-enumeración del alta, fusión de líneas, deep-links, cesta cruzada,
  líneas fantasma.
- **Accesibilidad**: `role="dialog"`, `aria-live`, `role="status"`/`alert`, `aria-current`,
  `aria-expanded`, `<fieldset>/<legend>` con radios reales, foco al cambiar de paso, y `no-scroll`
  del `<body>` con **un solo dueño** declarado.
- **Extremo a extremo real** con la pasarela en sandbox, en los dos motores, comparando el pedido en BD.
- **Gates**: suite, Pint, `docs-check`, `npm run build` (**hay que añadirlo al hook: hoy no está**),
  `composer audit` y `npm audit` en 0.

## 7. Revisión y decisión

**2026-08-13 — owner**: alcance = solo el cajón · tema = tokens + hoja por instalación ·
dependencias = Vue 3 + Pinia + `@vitejs/plugin-vue` · la cesta se persiste sin `event_data`.

**2026-08-13 — revisión adversarial ×3** (paridad funcional · tema y contrato visual · riesgo de
implementación). Veredictos: **INSUFICIENTE · SÓLIDO-CON-CAMBIOS ×2**. Todos los hallazgos se
verificaron contra el código antes de incorporarlos; los que cambian el diseño:

1. **CE-5 era falso**: cinco huecos de API, dos bloqueantes (complementos resueltos y admisión sin
   consumir). → §4.4 y paso 4.0b.
2. **La vuelta de Redsys no tenía camino hacia la SPA**, y sin `Purchase::mount()` nadie olvida las
   tres claves de sesión → el cajón se auto-abriría en cada página. → §4.1 y paso 4.0a.
3. **El contrato son las clases** era falso: 90 de 292 selectores son estructurales o dependen del
   tipo de elemento. → §4.2, contrato por diff de DOM.
4. **`SidebarClassContractTest` no era falsable** (concatenación, `@class`, `:class`, y la carcasa
   del cajón fuera de alcance). → sustituido.
5. **`localStorage` destruía la defensa anti-cesta-cruzada** que hoy existe. → §4.6.
6. **La tokenización sube a Fase 4**: con el 75% de las declaraciones quemadas, «una hoja por
   instalación» solo permitiría recolorear. → §4.3 y paso 4.0c.
7. **El sidebar es un nodo + un bus de eventos**: 11 vistas usan el store, 3 despachan eventos que en
   modo SPA fallarían en silencio. → §4.1.
8. **i18n no tenía canal** (169 claves × 3 locales, más un cuarto parcial). → §4.5.
9. **CE-6 era inalcanzable**; la red más barata con 0 dependencias es la máquina como módulo JS plano
   probado con `node --test`. → §4.8.
10. **El peso del bundle no estaba gateado** y la analogía con FullCalendar era falsa. → §4.7, CE-7.
11. **Correcciones de hecho**: 1.859 líneas y no 2.049 (**la doc también lo dice mal**) · el «puente
    Alpine» son ~97 líneas del store, no 852 · `PERF-02` mal atribuido · el `pre-push` **no corre
    npm** · tres valores distintos del puerto de Vite · los complementos son del paso 3.

**2026-08-13 — owner**: la tokenización de `site.css` sube a Fase 4 (paso 4.0c, §4.3).

**Revisión de esta v2**: pendiente. **Entrada final**: `DECISIONES.md` al aprobarse.
