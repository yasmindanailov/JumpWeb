# [SPEC] Precio por TRAMO DE CANTIDAD — cuantos más vienen, menos cuesta cada uno

> Estado: 🟦 **DISEÑO CERRADO** (2026-09-01) — las tres decisiones del owner, tomadas (§7)
> Caso que lo motiva: **excursiones de colegio**. Tanda **B** de `P2`; la **A** (horario por zona) ya
> está en el árbol (`#322`, `specs/horario-por-zona.md`).
> ⚠️ **Esto es DINERO**: `CRITICAL_RE`, `PAY-16`/`PAY-17`, el libro del pedido y `VERIFY_CONC=1`.

---

## §0 · Antes de tocar

- **Es DINERO**: `CRITICAL_RE`, `PAY-16`/`PAY-17` (el libro), `VERIFY_CONC=1`. Las tres decisiones del
  owner están en §7 y el estado en §8; la excursión es un producto `pack` (sin eso ni `min_qty` ni el cupo).
- **La escala vive en UN sitio, `TicketType::tierPriceCents()`, y tiene SUELO en el mínimo contratable**:
  por debajo del mínimo manda el PRIMER tramo, no el precio base (`[DECIDIDO owner]`). Propiedad
  comprobable: vender por debajo del mínimo nunca sale más barato por cabeza que vender en el mínimo.
- **El suelo NO va dentro de `PriceTier::resolve()`**: ahí regalaría el descuento de volumen a toda ENTRADA
  comprada por debajo de su primer tramo (5 de un «10+ → 8 €» a 8 €), sin que nada falle. Hay caso de CONTROL.
- **SEIS sitios resuelven el precio de una línea** (presupuesto, checkout, calendario, pedido manual,
  ediciones y el sello): todos el mismo número. El pedido manual llamaba a `priceCents()` sin la cantidad.
- **No metas la cantidad en `prices`** (media docena de agregados suponen UNA fila por tarifa): tabla propia
  `price_tiers`, **sin `max_qty`**. Un COMPLEMENTO es una fila de `ticket_types` y un `instanceof` lo alcanza.
- Editar la cantidad re-tarifica SOLO con tramos · tramos y familia de edades son excluyentes · el «desde
  X €» es el más BARATO (`[DECIDIDO owner]`).
- **Mostrador (`#330`)**: `Booking\Contracts\CounterSale` dice QUIÉN vende y viaja hasta `OrderCreator` y
  `SlotOffer`; `null` = la web de siempre. La ANTELACIÓN MÍNIMA no ata al mostrador; el MÍNIMO del pack sí,
  hasta que el operador lo levanta con permiso (`orders.edit_item_below_minimum`) y rastro. No relaja aforo,
  máximo, ventana, estado de la franja ni el corte intra-día; `online_sales_open=false` tapa también en
  mostrador, y `SlotOffer::offerableTimes()` descarta la franja si el hueco no llega al mínimo **sin avisar**.
- ⏸️ El 2x1 y «la tercera más barata», aparcados (§1.1·4). Anexo al final con la fila del enrutador.

## 1. Contexto y problema

El cliente vende excursiones con este cuadro (`[owner, 2026-09-01]`), **por persona**:

| Personas | 2 h · L-J | 2 h · V/finde/festivo | 3 h · L-J | 3 h · V/finde/festivo |
|---|---|---|---|---|
| **30** | 15 € | 17 € | 18 € | 20 € |
| **70** | 13 € | 15 € | 16 € | 18 € |
| **100** | 12 € | 14 € | 15 € | 16 € |

**La mitad de este cuadro ya existe.** Las columnas de día son el mecanismo `RateType`
(`normal` / `special`), y el viernes entra en `special` **para todos los productos**
(`[DECIDIDO owner]`). **La dimensión nueva es la CANTIDAD**, y hoy no existe: el precio es función de
`(producto, tarifa-del-día)` y nada más.

### 1.1 Lo decidido por el owner, que acota el diseño

1. **Precio UNIFORME, no escalonado**: 70 niños a 13 € = **910 €**. No 30×15 + 40×13 = 970 €.
   Preguntado con los dos números delante.
2. **El tramo es un RANGO y a la vez el MÍNIMO FACTURABLE**, configurable por producto.
3. **Por debajo de 30 y por encima de 100 no se vende**: *«ya toca llamar y preguntar»*. El rango
   vendible online es **[30, 100]**, y eso ya lo expresan `ticket_types.min_qty`/`max_qty`.
4. ⏸️ **APARCADOS**: el 2x1 y «la tercera 10 € más barata». ⚠️ Medido: `order_items.free_quantity`
   YA existe y es el mecanismo natural del 2x1 (`chargedSubtotalCents()` = `(quantity − free) ×
   unit_price`), **pero hoy significa «incluido en el pack»** de cara al cliente
   (`OrderItem::addonBadgeKey()`), así que reusarlo exige separar *cuántas son gratis* de *por qué*.
5. **La excursión es un producto `pack`** (`#322` §7.1): sin eso, `min_qty` no se aplica y el cupo de
   grupos por franja no la cuenta.

### 1.2 ⚠️ El riesgo central, medido: **SEIS sitios resuelven el precio unitario**

No es un cálculo, son seis, y **todos tienen que dar el mismo número** o lo que se muestra deja de
ser lo que se cobra:

| Dónde | Qué resuelve |
|---|---|
| `CartPricer:90` | el **presupuesto** (lo que el cliente ve antes de pagar) |
| `OrderCreator:231` | el **checkout** (lo que se cobra) |
| `AvailabilityReader:55` | el precio del **catálogo/disponibilidad** |
| `CreateManualOrderPage:553,1450` | el **pedido manual** del panel |
| `ItemEditPricing:42,81` | las **ediciones** del panel |
| `AgeFamilySealer:73,109` | el **sello** de cumpleaños mixto |

▶ Por eso la resolución del tramo **no puede vivir en el que llama**: tiene que estar dentro de la
única función que ya comparten, y cada llamante limitarse a pasarle la cantidad.

---

## 2. Objetivo

Que el precio unitario de un producto pueda depender de **cuántas unidades se compran**, con los
tramos configurables desde el panel, sin que ningún producto que no los use cambie de conducta y sin
crear una segunda fuente de verdad del precio.

**Fuera de alcance:** el 2x1 y la N-ésima más barata (§1.1·4), los códigos promocionales (subsistema
distinto: un código no es un tramo) y el aforo.

---

## 3. Opciones consideradas

### A · Ampliar la tabla `prices` con `min_qty`

`prices` es hoy `(priceable, rate_type_id) → amount_cents`, con **único** en esa pareja. El tramo
sería la tercera dimensión de la MISMA clave, y las filas actuales quedarían como `min_qty = 0`.

Conceptualmente es lo correcto —*es* el precio de ese producto en esa tarifa para esa cantidad— y
repite el movimiento que funcionó en la tanda A: extender lo que ya existe en vez de inventar.

❌ **Y aun así se descarta, por lo que rompe en silencio.** Media docena de agregados leen `prices`
suponiendo **una fila por tarifa**, y meterle filas cambia lo que miden **sin que falle nada**:

- **`TicketType::displayPriceCents()`** hace `first()` sobre las filas de tarifa `normal`. Con tres
  tramos hay **tres** filas normales y el «desde X €» de la landing pasa a depender del **orden de la
  consulta**: no determinista.
- **`TicketType::priceVaries()`** compara `max` contra `min` de TODAS las filas y hoy significa
  **«el precio varía según el DÍA»** — es lo que decide que la web diga «desde X €». Con tramos sería
  cierto para un producto de precio fijo por día, y la landing mentiría por un motivo nuevo.
- Y lo mismo por revisar en `CatalogReader`, `AvailabilityReader` y el formulario de precios del panel.

▶ *Añadir una dimensión a una tabla compartida cambia el significado de todos los agregados que ya se
calculan sobre ella.* Es el mismo patrón que `#238` (un `100%` que medía otra caja) y `#295` (un campo
que agrupaba y no identificaba).

### B · Tabla propia `price_tiers` — **ELEGIDA**

`(ticket_type_id, rate_type_id, min_qty) → amount_cents`. Un producto sin tramos **no tiene filas**,
así que:

- ✅ **Ningún agregado existente cambia de significado**: `prices` queda intacta y `displayPriceCents()`
  / `priceVaries()` siguen midiendo exactamente lo que medían.
- ✅ **Conducta nueva solo donde hay tramos declarados** — la misma propiedad que hizo segura la
  tanda A (`null` = hereda).
- ✅ La resolución es una pregunta con respuesta por defecto: *«¿hay tramo para (producto, tarifa,
  cantidad)? úsalo; si no, el precio de siempre»*.

### C · Un campo JSON de tramos en `ticket_types`

❌ No se puede consultar, no se puede validar por BD, no tiene unicidad y el panel tendría que
editarlo a mano. Los solapes de tramo serían invisibles hasta que alguien cobra de más — justo lo que
la T6 de cumpleaños mixto (`#299`) tuvo que arreglar para los tramos de EDAD, con guardián de solapes
en el dominio incluido.

---

## 4. Diseño elegido

### 4.1 La tabla

| Columna | Notas |
|---|---|
| `ticket_type_id` | FK, cascade |
| `rate_type_id` | FK — **el tramo es por tarifa**: el cuadro del cliente tiene precio distinto L-J y finde |
| `min_qty` | unsigned — desde cuántas unidades aplica |
| `amount_cents` | unsigned |
| único | `(ticket_type_id, rate_type_id, min_qty)` |

**No hay `max_qty`**: el tramo llega hasta que empieza el siguiente. Un `max` explícito permite huecos
(«31–69 sin precio») y solapes, que es una familia entera de defectos que no hace falta tener — la
lección de `#299`, donde los tramos de edad SÍ los permitían y hubo que construir un guardián.

### 4.2 La resolución, en el sitio que los seis ya comparten

`RateResolver::priceCents($priceable, $date)` gana la cantidad:
`priceCents($priceable, $date, int $quantity = 1)`.

Con el default a 1, **los seis llamantes siguen compilando y comportándose igual**, y se les va
pasando la cantidad uno a uno con su caso. El tramo aplicable es **el de mayor `min_qty` ≤ cantidad**;
si no hay ninguno, el precio de `prices`, que es la conducta de hoy.

⚠️ **`CartPricer` y `AvailabilityReader` NO llaman a `RateResolver`**: leen `priceCentsForRate()` de
la relación ya cargada, a propósito, para no consultar por línea (`CartPricer:86` lo documenta). Su
equivalente gana la cantidad igual, y **la paridad entre los dos caminos necesita su propio caso**:
son dos implementaciones de la misma regla y ésa es exactamente la forma en que un precio mostrado
deja de ser el cobrado.

### 4.3 ⚠️ El «desde X €» del catálogo, que es donde esto se nota primero

`displayPriceCents()` no tiene cantidad: la landing enseña un producto, no una compra. Con tramos, la
respuesta honesta es **el precio del tramo mínimo vendible** (`min_qty` del producto = 30 → 15 €),
porque es el que un cliente puede pagar de verdad; el más barato (12 €) sería un reclamo que solo
alcanzan los grupos de 100.

▶ **`[DECIDIDO owner]`: el MÁS BARATO** — «desde 12 €» (§7·2). El párrafo de arriba propone lo
contrario y **queda como el razonamiento descartado**, no como lo vigente.

### 4.4 Editar la cantidad RE-TARIFICA la línea — pero solo con tramos

⚠️⚠️ **CORRECCIÓN: este apartado daba por hecho que la cantidad re-tarifica, y HOY NO LO HACE.**
`ItemEditPricing::computeEditPricing()` documenta lo contrario, y es una regla decidida
(`#127(d)`): *«una subida de cantidad sin mover el día sigue conservando la tarifa histórica del
ítem»*. Se escribió cuando el precio no dependía de la cantidad, y protege al cliente de que le
re-tarifiquen una reserva ya pagada.

▶ **La salida es una EXCEPCIÓN ESTRECHA, no cambiar la regla**: se re-tarifica **solo si el producto
declara tramos**. En un producto cuyo precio está *declarado como función de la cantidad*, conservar
la tarifa vieja contradice al propio producto — un colegio que pasa de 70 a 100 niños seguiría
pagando el tramo de 70 y **no recibiría el descuento que su propia tabla le promete**. Sin tramos, la
conducta es exactamente la de siempre, y eso es lo que hace segura la excepción.

⚠️ Y hay que decirlo en el panel: el operador que sube 70 → 100 tiene que ver que baja el precio
**por unidad**, no solo que hay más gente. El libro (`#315`) ya sabe expresarlo con `recordEdit(±Δ)`;
la **frase** es nueva.

---

## 5. Impacto en invariantes

| Invariante | Impacto |
|---|---|
| **`PAY-16`/`PAY-17`** (identidades del libro) | **Hay que verificarlas con el precio por tramo**: el total facturado sigue siendo `Σ (cantidad × unitario)`, pero el unitario ya no es función solo del día. La identidad no cambia; lo que cambia es de dónde sale un factor. |
| **`PAY-18`** (la fecha es un producto) | **Se amplía**: la CANTIDAD también re-tarifica. Hay que escribirlo. |
| **`PAY-19`** (el sello) | **A comprobar**: `AgeFamilySealer` resuelve precios por miembro de familia; con tramos, ¿la cantidad del sello es la del grupo o la del tramo de edad? **Pregunta abierta y es la más peligrosa de la tanda.** |
| **`AFORO-*`** | Ninguno: el precio no toca aforo. |
| `CRITICAL_RE` | `OrderCreator` está dentro → **`VERIFY_CONC=1` con los seis escenarios**. |

---

## 6. Plan de verificación empírica

1. **Migración sin conducta nueva**: sin filas en `price_tiers`, la suite entera pasa sin tocar un test.
2. **El cuadro del cliente, entero**: los 12 precios (2 h/3 h × L-J/finde × 30/70/100) sobre datos
   reales, comprobando **910 €** para 70 niños un lunes y no 970 €.
3. **Paridad presupuesto ↔ checkout**: `CartPricer` y `OrderCreator` dan el MISMO total para la misma
   cesta, con y sin tramos. Es la guarda que impide la segunda fuente de verdad.
4. **Paridad con los otros cuatro**: pedido manual, edición, catálogo y sello.
5. **La frontera**: 29 se rechaza, 30 entra a 15 €, 69 a 15 €, 70 a 13 €, 100 a 12 €, 101 se rechaza.
6. **Un producto SIN tramos no cambia de precio** en ninguno de los seis caminos.
7. **`VERIFY_CONC=1`** con los seis escenarios.

⚠️ Cada guarda se muta con el fallo REAL que la motiva.

---

## 7. Las tres decisiones del owner — TOMADAS (`[DECIDIDO owner, 2026-09-01]`)

1. **Los tramos se declaran POR PRODUCTO.** Aunque el cuadro tenga los mismos cortes en 2 h y 3 h, se
   teclean dos veces: es lo que ya hace `prices` y deja que un producto futuro tenga otros cortes.
2. **El «desde X €» de la web es el MÁS BARATO** — «desde 12 €», el del tramo de 100.
   ⚠️ **Esto REVIERTE la recomendación de §4.3**, que proponía el tramo mínimo vendible (15 €) para
   que nadie viera 12 y pagara 15. El owner elige el más barato, y es defendible: «desde» señala
   variabilidad y es lo que anuncia el precio real más bajo que existe. **Queda escrito para que
   nadie lo «corrija» de vuelta creyendo que es un descuido.**
3. **Un producto NO puede tener tramos de cantidad Y familia de edades a la vez**, y lo impide una
   guarda. Hoy no se cruzan (una excursión no es una fiesta mixta) y cerrar la puerta ahora cuesta
   una guarda; abrirla mal cuesta un cobro erróneo. ▶ Con eso, la pregunta «¿qué cantidad se sella?»
   **deja de existir** en vez de contestarse a medias.

---

## 8. Estado

🟦 **Diseño cerrado; pasa a ejecución.**

## Anexo · La fila del enrutador, mudada el 2026-09-16

> Lo que decía la fila **«Excursiones de colegio · horario de una ZONA distinto del recinto · fuera de apertura · descuentos por tramo de cantidad»** de `CLAUDE.md` cuando el enrutador bajó a una línea por fila
> (`DECISIONES #619`). Se conserva **verbatim** porque es historia de trampas medidas: léelo
> después del §0 y no lo reescribas. Documentos que la fila citaba: `docs/specs/precio-por-tramo.md` · `docs/specs/horario-por-zona.md` · `docs/DECISIONES.md`.

- **`docs/specs/precio-por-tramo.md`**
- 🟦 **TANDA B EN EL ÁRBOL** (`#324`) —
- ❗❗❗ **`#330` SI TOCAS UNA REGLA DE VENTA DEL PANEL**: existe **`Booking\Contracts\CounterSale`**, que dice QUIÉN vende y viaja desde la página hasta `OrderCreator` **y** `SlotOffer` para que la OFERTA y el COBRO respondan lo mismo; `null` = la venta de siempre, así que **la web queda intacta por construcción**.
- ⚠️⚠️ **La ANTELACIÓN MÍNIMA no ata al mostrador** (`[DECIDIDO owner]`, sin interruptor ni permiso: es una regla para quien compra SOLO), y **el MÍNIMO del pack sí ata** hasta que el operador lo levanta con permiso y rastro — *dos excepciones parecidas con naturaleza distinta, y por eso el objeto tiene dos campos y no uno*.
- ⚠️ **Lo que NO relaja ninguna**: aforo, máximo, `>= 1`, ventana de horario, estado de la franja y **el corte intra-día** (una franja de hoy cuya hora ya pasó *no es antelación, es el pasado*).
- ▶ Anotado sin cambiar: `online_sales_open=false` sigue tapando la franja también en mostrador.
- ⚠️ **`SlotOfferTest::test_panel_calendar_blocks_lead_time_window_via_min_date` cambió de premisa y se reescribió**: afirmaba lo contrario. —
- ❗❗❗ **`#329` SI TOCAS EL PRECIO O EL MÍNIMO DE UN PACK**: la regla de tramos vive ahora en **UN** sitio, `TicketType::tierPriceCents()` (antes en dos: `RateResolver::priceCents()` y `priceCentsForRate()`, los dos caminos por los que se tarifica de verdad), y **la escala tiene SUELO: el mínimo CONTRATABLE del producto**. `[DECIDIDO owner]`: por debajo del mínimo manda el **PRIMER tramo**, no el precio base — la propiedad comprobable es que *vender por debajo del mínimo nunca sale más barato por cabeza que vender justo en el mínimo*.
- ⚠️⚠️ **El suelo NO va dentro de `PriceTier::resolve()`**: allí sería «si ninguno cubre, coge el más pequeño», y eso **regala el descuento de volumen a toda ENTRADA comprada por debajo de su primer tramo** (5 unidades de un «10+ → 8 €» pasarían de 10 a 8 €) — nada falla, solo se ingresa menos. Hay caso de CONTROL y la mutación lo pone rojo.
- ⚠️ **El operador puede vender por debajo del mínimo al CREAR** (permiso `orders.edit_item_below_minimum`, el mismo de D7): el mínimo se impone en **CUATRO** sitios y el cuarto —`SlotOffer::offerableTimes()`, que descarta la franja entera si el hueco no llega al mínimo— **no avisa al fallar**.
- ⚠️ Defecto de dinero preexistente arreglado: la previsualización del pedido manual llamaba a `priceCents()` **sin la cantidad** (140,00 € de desfase en una línea de 70) — *un parámetro con valor por defecto no avisa de que hacía falta*. —
- ❗❗ **SI VAS A TOCAR EL PRECIO DE UNA LÍNEA**: son **SEIS** los sitios que lo resuelven (presupuesto, checkout, calendario, pedido manual, ediciones y el sello) y todos tienen que dar el mismo número.
- ⚠️⚠️ **NO metas la cantidad en la tabla `prices`**: media docena de agregados la leen suponiendo UNA fila por tarifa y cambiarían de significado **sin que falle nada** (`displayPriceCents()` haría `first()` sobre tres filas; `priceVaries()`, que significa «varía según el DÍA», pasaría a ser cierto por la cantidad). Tabla propia `price_tiers`, **sin `max_qty`** (el tramo llega hasta el siguiente: ni huecos ni solapes por construcción).
- ⚠️⚠️ **Un COMPLEMENTO es una fila de `ticket_types`**, así que un `instanceof TicketType` los alcanza: cada uno pagaba una consulta por unos tramos que no puede tener (10 → 16 en la ficha) y lo cazó el presupuesto de la API.
- ⚠️ **Editar la cantidad re-tarifica SOLO con tramos** (excepción estrecha a `#127(d)`).
- ⚠️ **Tramos y familia de edades son excluyentes**, con guarda en las dos direcciones.
- ⚠️ El «desde X €» es el **más BARATO** (`[DECIDIDO owner]`, revierte la recomendación de la spec: no lo «corrijas»).
- ⏸️ El 2x1 y «la tercera más barata», APARCADOS · **`docs/specs/horario-por-zona.md`**
- 🟦 **TANDA A EN EL ÁRBOL** (`#322`) —
- ❗❗ **Si retiras o añades un consumidor del HORARIO, lee §4.4 ANTES**: la spec afirmaba, medido y MAL, que eran DOS y son **TRES** — `ProductAvailability::allowsStart()` lo consulta y por él pasan `SlotOffer`, `OrderCreator`, `OrderItemEditor` e `ItemRescheduleOffer`; **lo cazó una guarda, no una lectura**, y *un `grep` del servicio deja fuera a quien pregunta por un tercero*.
- ⚠️ **`effectiveFor()` es LA CARA PÚBLICA** (landing, «Abierto ahora») y no se toca: la variante por zona es un método aparte, y la duplicación es deliberada.
- ⚠️ **Generar y podar leen la MISMA resolución** o la poda cierra lo que el generador acaba de crear, con ventas dentro (`AFORO-04`).
- ⚠️ **Ignorar el cierre SIN declarar horas NO abre** (con el recinto cerrado no hay ventana que heredar y el fallback histórico es «abierto sin restricción»).
- ▶ **Lo que NO hay que construir: `zones.max_per_slot`/`max_guests_per_slot` YA existen y ya son por zona** (el tope de grupos es configuración), igual que `min_qty`/`max_qty`, `duration_min` y la señal.
- ▶ **Tanda B (precio por tramo) pendiente y es DINERO**; la excursión es un producto **`pack`** (`[DECIDIDO owner]`: `min_qty` y el cupo de grupos SOLO funcionan siendo pack) · `docs/DECISIONES.md` #322
