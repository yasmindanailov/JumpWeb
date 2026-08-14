# Compra de productos: catálogo unificado + flujo (estilo ROLLER)

> Adaptado del proyecto origen (2026-08-12). Describe la BASE HEREDADA: el refactor
> (`00-REFACTOR.md`) puede haberla cambiado. Verifica contra el código antes de construir
> encima (CONVENCIONES §7).

> **Diseño definitivo del catálogo unificado y del flujo de compra; referencia activa del código.**
> En el origen nació como propuesta y se implementó por capas hasta producción. ⚠️ Algunos marcadores
> de estado de este doc quedaron desfasados en el origen: la base heredada tiene el flujo COMPLETO
> (capas 1–4), incluidos add-ons (`AddonResolver`) y pago Redsys real (`RedsysReturnHandler`
> idempotente) — ver `00-REFACTOR.md` §Visión. Las referencias `#N` apuntan al registro de
> decisiones del proyecto origen (histórico, no portado); el razonamiento relevante está resumido aquí.
>
> Vocabulario del sector origen (parque, zonas, cumpleaños, packs, puerta…); su generalización
> se decide en `00-REFACTOR.md` Fase 1/2.

## 1. Modelo de compra (referencia: ROLLER)
Flujo inspirado en la plataforma ROLLER, estándar del sector de ocio:
1. **Elegir el producto** (una entrada concreta, o un **pack** de cumpleaños).
2. **Elegir fecha y hora**; en la hora se ven las **plazas disponibles** y se elige la **cantidad**
   (topada al aforo de esa franja, con el **precio del día** ya visible).
3. **Carrito** acumulativo: se ve lo elegido y se puede **volver atrás** para añadir más productos.
   (No se edita la cantidad en el carrito: se quita la línea y se rehace.)
4. **Complementos** (add-ons: calcetines, candado, tarta, monitor…) antes de pagar.
5. **Pago** + confirmación.

> **Clave de robustez:** la **cantidad se elige en el paso de fecha/hora**, no antes. Así el aforo
> y el precio del día ya se conocen → sin «precio que cambia» ni recortes por sorpresa.

## 2. Principio: «todo es un producto vendible»
Un único **catálogo de productos**, cada uno con un **tipo**:

| Tipo | Qué es | Consume aforo (franja) | Emite ticket | Ejemplos (sector origen) |
|---|---|---|---|---|
| **entry** (entrada) | admisión por zona + duración | **Sí** | Sí | Jump 1H/2H/3H, Kids ilimitada |
| **pack** | conjunto de admisiones con mín/máx + extras incluidos | **Sí** (según plazas) | Sí (una por plaza) | Cumpleaños 10 niños |
| **addon** (complemento) | objeto/servicio añadido | **No** | Normalmente no | Calcetines, candado, tarta, monitor |

Esto **unifica entradas y eventos/cumpleaños**: el cumpleaños no es un sistema aparte sino un
**producto `type=pack`**. Más coherente, *data-driven* y *white-label* (cada instalación define
sus productos sin tocar código).

## 3. Modelo de datos
`prices` es **polimórfica** (entradas + packs). Decisiones estructurales adoptadas (A–E del origen):
- **Catálogo unificado (A)** — `ticket_types` es el catálogo de **productos** con campo
  **`type`** (`entry` / `pack` / `addon`, def. `entry`). La antigua tabla `event_packages` fue
  **absorbida** como `type=pack` (comparte zona, duración, features, mín/máx, precio en `prices`).
- **Packs** — `min_qty` / `max_qty` y extras incluidos.
- **Complementos (E)** — productos `type=addon`; a qué productos aplican vía relación
  producto↔addon. No consumen aforo. En la base heredada la resolución vive en **`AddonResolver`**.
- **Pedido único (B)** — `order_items` referencia el producto y, para `entry`/`pack`, el `slot_id`;
  para `addon`, sin slot. → un **único pedido** (`orders`) lleva entradas + packs + complementos.
- **Cumpleaños dentro de `orders` (C)** — el pago de todo va por `orders` + `payments`
  (polimórfico). Los **datos del evento** (homenajeado, nº invitados, sala, notas) se guardan como
  metadatos del pedido/línea de pack; `event_bookings` quedó absorbida.
- **Aforo** — `entry` y `pack` consumen plazas en `slots` (un pack de 10 = 10 plazas en su tramo);
  `addon` no toca aforo. Ver §7 para el aforo separado por tipo.
- **Stock de complementos** — v1 ilimitado; stock por día (p. ej. tartas) quedó como extensión futura.

## 4. Capas de construcción (todas en la base heredada)
1. **Capa 1 — Entradas individuales** con el orden producto → fecha → hora + cantidad (topada al
   aforo, precio del día) → carrito. Componente `App\Livewire\Tickets\Purchase`, catálogo
   «desde X€», carrito acumulativo (líneas) en sesión con **saneo** de cestas incompatibles.
2. **Capa 2 — Packs** (cumpleaños como producto: mín/máx, datos del evento, aforo por cupo §7).
3. **Capa 3 — Complementos** (add-ons condicionados por producto), paso previo al checkout.
   *(El doc origen no llegó a marcarla; el código heredado la tiene: `AddonResolver`.)*
4. **Capa 4 — Identificación + pedido + pago + email.** Reserva con bloqueo + pedido `pending`
   (`OrderCreator`, con locks anti-sobreventa); **identificación embebida** en el sidebar
   (login/registro reutilizados); **verificación de email con reserva PROVISIONAL** que retiene la
   plaza y se confirma al verificar; confirmación con resumen + «Mis pedidos». Decisión del origen
   en su momento: **sin QR** → email de confirmación con **código de pedido** (verificar en código
   el mecanismo vigente de canje en puerta). El pago Redsys real está implementado
   (`RedsysReturnHandler` idempotente); el diseño del origen lo introdujo como placeholder primero.

## 5. Valores de negocio (por instalación, white-label)
- **Packs reales** (qué incluye cada uno, mín/máx, precio por tipo de día, señal).
- **Complementos reales** y a qué productos aplican, precio y stock.
- Son **valores** que cada instalación configura en el panel; la **estructura** es data-driven y
  no depende de ellos.

## 6. Invariantes que este diseño conserva
El **aforo por ocupación** a lo largo de la visita (`SlotAvailability`), los **precios y totales
calculados SIEMPRE en servidor** (nunca se confía en el cliente), la **matriz de tarifas**
(`rate_types`/`prices`), los **pagos polimórficos** (Redsys) y el feedback de carga (spinner).
Ver `INVARIANTES.md`.

## 7. Horarios, disponibilidad por producto y aforo de packs (configurable en el panel)
Estructura data-driven: todo se edita en el panel; los valores (horarios, offsets, preparación,
cupos) son configuración por instalación. Racional: un cumpleaños necesita preparación y se
atiende distinto (mesa propia) que una entrada individual.

### 7.1 Horario del negocio por tipo de día
Horario distinto según **día normal / fin de semana / festivo o víspera**. Las franjas a la venta
solo existen **dentro** del horario de cada día.
- **`opening_hours`** (recurrente): `weekday` (0–6) · `open_time` · `close_time` · `is_closed`.
- **`special_dates`** (excepciones): `date` · `is_closed` · `open_time` · `close_time` ·
  `rate_type_id` (festivos/vísperas con su horario **y** su tarifa).
- **Horario efectivo de un día** = `special_dates` si existe; si no, el `opening_hours` de su
  weekday. Servicio: **`OperatingSchedule`**. `GenerateSlots` lo respeta: salta días cerrados y clipa
  las franjas a `[apertura, cierre]`.

### 7.2 Ventana de disponibilidad por producto (entradas **y** packs)
Cada producto define **en qué parte del horario** se puede comprar/entrar, con **offsets relativos
a apertura/cierre** (no horas absolutas → se adaptan solos a cada día). Servicio:
**`ProductAvailability`** (defaults 0 = sin restricción).
- `available_after_open_min` — disponible a partir de *X* min tras la apertura (def. 0).
- `available_before_close_min` — disponible hasta *X* min antes del cierre (def. 0).
- **Packs** añaden **preparación**: `prep_before_min` (montaje) · `prep_after_min` (limpieza).
  Ejemplo: `prep_before_min = 60` reserva la hora previa para montaje → esa franja no admite otro
  cumpleaños. El bloqueo de franjas vecinas por preparación es **configurable**
  (`packs.prep_blocks_cupo`).

### 7.3 Aforo SEPARADO por tipo (entradas ≠ packs)
- **Entradas** → plazas por zona y franja (`slots.online_capacity`), por **ocupación** a lo largo
  de la visita. Servicio: **`SlotAvailability`** (descuenta además los ocupantes provisionales de
  la cesta → no se sobrevende desde la cesta).
- **Packs** → aforo propio por **CUPO configurable por franja**, NO por mesas (#82 origen). Dos
  topes: `packs.max_per_slot` (nº de eventos) **y** `packs.max_guests_per_slot` (nº de plazas
  totales). **Pool separado** del de entradas: un pack no resta plazas de las zonas de entrada ni
  viceversa. Servicio: **`PackAvailability`** (gemelo de `SlotAvailability`), bajo `lockForUpdate`
  en `OrderCreator`. Los packs viven en una **zona operativa propia** (`is_active=false`, oculta de
  la landing) con sus franjas; el `online_capacity` de esas franjas se ignora (manda el cupo).
  - *Se descartó modelar mesas+sillas+personal:* combinatorio y frágil (mesas que se juntan,
    monitores/camareros variables por tamaño). La tabla **`rooms`** (+ modelo `Room`) queda como
    estructura para una posible asignación de mesas en el panel; el aforo NO depende de ella.
  - El **monitor con disponibilidad** se modela como **`addon`**, no como parte del aforo del pack.
- **Cobro de packs** (#83 origen): **por plaza × invitados + señal configurable** en el catálogo
  (`deposit_type`/`deposit_value`); por defecto pago total.

### 7.4 Cómo influye en la compra (servidor autoritativo)
Al elegir **fecha → hora** de un producto, el servidor ofrece **solo** las franjas que: (a) caen
en el **horario efectivo del día**, (b) están dentro de la **ventana del producto**, y (c) tienen
**aforo del tipo correcto** (plazas para entradas; cupo para packs), **descontando la
preparación**. La cantidad se topa a ese aforo. Nunca se confía en el cliente. En la base heredada
la oferta de franjas está consolidada en **`SlotOffer`** como fuente única (ver `00-REFACTOR.md`
§Visión; posterior a este diseño).

⚠️ **Y quién decide si una línea ENTRA en la cesta ya no es la pantalla** (Fase 4 · paso 4.0b·6,
`DECISIONES #40`): es `Booking\Contracts\CartLineValidation`. Reúne lo que estaba repartido en el
cuerpo de `Purchase::addToCart()` —producto elegible, franja **ofrecida** (no solo con aforo),
cantidad contra el mínimo, tope de líneas (`PAY-12`) y campos obligatorios del pack— y responde
además dos cosas que ninguna validación contesta: **con qué cantidad entraría** tras el re-tope y
**si se funde** con una línea que ya estaba. La compra web lo consume y `POST /api/v1/cart/validate-line`
lo publica, porque un cliente con la cesta en el navegador no hace ningún viaje al añadir.
La fusión sigue siendo solo entre entradas del mismo producto, día y hora **sin complementos**.

### 7.5 Piezas y ubicación (estado en la base heredada)
| Pieza | Detalle |
|---|---|
| `special_dates` | Horario+tarifa por fecha; `GenerateSlots` la usa vía `OperatingSchedule` (pisa al semanal) |
| `opening_hours` | Tabla + modelo + servicio `OperatingSchedule`; CRUD en el panel |
| Ventana por producto | Columnas `available_*`/`prep_*` en el catálogo + servicio `ProductAvailability` |
| `rooms` | Estructura para asignación opcional de mesas; el aforo no la usa |
| Aforo de packs | `PackAvailability`: cupo por franja (nº eventos + nº plazas), pool propio, `lockForUpdate` |
| Campos de pack | `ticket_types`: `min_qty`/`max_qty` + `deposit_type`/`deposit_value` |
| Catálogo unificado | `ticket_types.type` (`entry`/`pack`/`addon`) |

## 8. UX del catálogo del sidebar — organización por TIPO (acordeones + buscador progresivo)
Sustituyó a un toggle Entradas/Packs (#226 origen): el segmented control robaba atención y el
catálogo es **pequeño por diseño** (techo realista ~10–15 ítems) → acordeón, no buscador/tags.

**Modelo TIPO vs ZONA (clave del diseño).** Se agrupa por **TIPO de producto**, no por zona:
- **«Entradas»** (`type=entry`) y **«Servicios»** (`type=pack`). «Servicio» es el concepto de cara
  al cliente del tipo `pack` (cumpleaños, empresas, colegios… todos `type=pack`).
- **La ZONA es un constructo OPERATIVO, no una categoría de catálogo:** condiciona la
  **disponibilidad** (los `slots` son por zona) y el **modelo de aforo** (entradas =
  `SlotAvailability`; packs = cupo `PackAvailability` con bloques de montaje/limpieza). En el
  origen, dos packs de cumpleaños distintos **compartían la misma zona operativa** (el matiz del
  nombre era el área física, no su `zone_id`) → agrupar por zona sería incoherente (un servicio
  «empresa» es `type=pack` pero no «cumpleaños»). Las entradas ya llevan su zona en el nombre
  («Jump · 1 hora») → **lista plana, sin subcabeceras de zona**.

**Implementación.**
- **Qué se vende lo decide el DOMINIO desde Fase 3 · paso 1b** (`DECISIONES #27`): el contrato
  `App\Domain\Booking\Contracts\ProductCatalog` (implementado por `Services\CatalogReader`) define
  el conjunto —`sellable()` + `inOperationalZone()`, tipos `entry`+`pack`, orden por `position`— y
  los campos de cada ficha. Lo consumen la web y `GET /api/v1/catalog/products`: **una sola
  definición**, verificada con un doble en `ModuleContractsTest`.
- `App\Livewire\Tickets\Purchase`: `render()` pide el catálogo al contrato UNA vez y lo parte por
  tipo en memoria; `catalogSection()` ya solo ADAPTA a la vista lo que el dominio da —une
  `features` con « · » y precomputa los dos artefactos de esta interfaz: la cadena `search` del
  buscador progresivo y el `zone_anchor` del deep-link—; `catalogTypes()` (entry+pack, sobre los
  modelos) valida la selección; `showPacks()` → paso 1 + `dispatch('catalog-open-services')`.
- `purchase.blade.php` paso 1: 2 secciones-acordeón **Alpine** (inline `x-data`, colapso CSS grid
  `0fr→1fr`), **buscador progresivo** (solo si total > umbral; umbral configurable desde el panel
  vía el helper defensivo `App\Domain\Booking\Services\CatalogSettings::searchMinItems()`, default 12), filtrado
  por `data-search` client-side. **Blade fino sin `@php`** (gotcha PCRE del fichero). a11y:
  `aria-expanded`/`aria-controls`/`aria-labelledby`, `prefers-reduced-motion`.
- **Coherencia landing⟺catálogo:** los controladores de landing filtran los packs con el MISMO
  predicado que el catálogo (`sellable()->inOperationalZone()`) → un CTA con deep-link
  (`show-packs`) nunca anuncia un pack que el flujo no puede vender.
- i18n es/en/fr: `section_entries`/`section_services`/`catalog_search`/`catalog_search_none`.

**Futuro (no implementado):** sub-categorizar dentro de «Servicios» (Cumpleaños vs Empresas)
pediría un campo «categoría de servicio» en el pack (NO la zona); hoy = lista plana + buscador
progresivo.

## Relacionados
`MODELO-DATOS.md` (tablas) · `FLUJOS.md` (flujo de compra end-to-end) · `INVARIANTES.md`
(dinero/aforo) · `00-REFACTOR.md` (Fase 2 modulariza este dominio en **Catalog&Booking**; la
Fase 4 sustituye el componente Livewire `Purchase` por la SPA).
