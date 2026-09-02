# [SPEC] La HORA EXTRA — un complemento que OCUPA

> Estado: ⬜ **BORRADOR — pendiente del ✅ del owner (§7).**
> Carril: producto/reservas. Autor: agente, 2026-09-02.
> ⚠️ **Toca AFORO.** Nada de esto se construye antes del ✅ (`CONVENCIONES §5`).

## 1. Contexto y problema

`[owner, 2026-09-02]`: *«la idea es no tener 4 productos tipo entrada 1 hora, entrada 2 horas… para no
saturar al cliente. La idea es tener dos y si alguien quiere más horas, puede añadirlas: es como que
el cliente crea su producto personalizado»*. Va **atada solo a ciertos productos y con límites**
—*«la hora extra está disponible solo para la de 2 horas […] se añade a la entrada con el máximo de
horas»*— y se vende y se gestiona **como un complemento cualquiera**.

### 1.1 · ❗❗❗ LA FRASE QUE DECIDE EL DISEÑO, y la primera lectura fue FALSA

*«La hora extra es de 1 entrada, no de las 4. La hora extra es para una de esas entradas.»*

▶ La primera versión de esta spec leyó «hora extra» como **«la reserva dura más»** y diseñó una
duración por LÍNEA: `order_items.duration_min` sellado y **~8 servicios de aforo re-apuntados**, 50
referencias en 14 ficheros. **Era el problema equivocado.**

▶ Lo que de verdad pasa: **4 personas de 10:00 a 12:00 y UNA de 12:00 a 13:00.** Eso no es más tiempo
con las mismas plazas: es **menos plazas, más tarde**. O sea **un ocupante nuevo**, no una duración
distinta. *Una hora extra no alarga la reserva: añade una segunda, más pequeña, pegada detrás.*

⚠️⚠️ **Y esa lectura correcta hace el diseño mucho más pequeño**, porque el sistema **ya sabe contar
ocupantes**. La lección de método: *cuando el diseño sale caro, sospecha de que estás modelando el
problema equivocado antes de aceptar el precio.*

### 1.2 · Lo MEDIDO (2026-09-02)

| Hecho | Medida | Dónde |
|---|---|---|
| **`occupancyMap` NO filtra por tipo** | cuenta **cualquier** `order_items` con `slot_id` en esa zona/día, con **sus propias `seats`** y el `duration_min` de **su propio** producto | `SlotAvailability::occupancyMap()` |
| La ocupación es plazas × franjas del tramo | marca cada `start` en `[entry_start, entry_start + duration)` con `+seats` | `SlotAvailability::occupancyMap()` |
| Hoy un complemento es **invisible al aforo** | las 5 líneas hijas reales: `slot_id = NULL`, `seats = 0`, producto con `duration_min = NULL` → el **INNER JOIN** contra `slots` las deja fuera | BD |
| …y tampoco se bloquea | el creador salta los complementos al lockear | `OrderCreator::createPendingOrder()` |
| Los ocupantes de la cesta salen **solo de las líneas de primer nivel** | `otherOccupants` recorre `$cart`, que son productos base | `OrderCreator::otherOccupants()` |
| El tramo multi-franja ya funciona y está lockeado | `spannedSlots` + `spanCoversDuration` | `SlotAvailability` |
| Los complementos se eligen **en el MISMO paso que la hora** | *«Paso 3 — HORA, cantidad, datos del pack y COMPLEMENTOS»* | `steps/TimeStep.vue` (docblock) |
| «Solo para ciertos productos, con límites» **ya existe** | pivote `ticket_types.addons` con `max_qty` (P9), obligatoriedad y dependencias | `TicketType::ADDON_PIVOT_COLUMNS` |
| Cada línea tiene **sus propios** complementos | cuelgan por `parent_item_id` | BD |

## 2. Objetivo

Que un complemento pueda **quedarse ocupando la franja siguiente** con las plazas que se le compren,
correctamente contado y **bloqueado**, sin duplicar el catálogo y **sin cambiar la conducta de ningún
complemento actual**.

## 3. Opciones consideradas

| | Qué es | Por qué NO (o sí) |
|---|---|---|
| **A · Un producto por duración** | «Entrada 1 h», «2 h», «3 h»… | ⛔ es literalmente lo que el owner rechaza |
| **D · Cambio de producto desde el panel** | subir la reserva de 2 h a 3 h con `OrderItemEditor` | ⛔ solo mostrador, y **mueve a TODA la línea**: no sabe decir «uno de los cuatro» |
| **C · Duración por LÍNEA** | `order_items.duration_min` sellado | ⛔ **modela el problema equivocado**: una línea tendría UNA duración y aquí conviven dos (3 personas 2 h, 1 persona 3 h). 50 referencias en 14 ficheros para no resolverlo |
| **B · El complemento es un OCUPANTE** ✅ | la línea hija recibe **su franja, sus plazas y su duración** | ✅ es lo que físicamente pasa, y **el mapa de ocupación ya lo cuenta sin tocarlo** |

## 4. Diseño elegido — B

### 4.1 · Qué es una «hora extra», en datos

Un complemento **normal** en todo —cuelga del pivote, tiene precio, se edita desde el panel— con
**dos datos**, y son dos a propósito:

| dato | qué dice | de dónde sale |
|---|---|---|
| `ticket_types.occupies_after_parent` (`bool`, default `false`) | **el interruptor**: «esta línea ocupa aforo detrás de su padre» | **columna nueva** |
| `ticket_types.duration_min` | **cuánto** ocupa (60, 120…) | **la columna que YA existe**, y **la que el aforo YA lee** |

❗❗ **La primera versión de esta spec inventó un `extends_duration_min` y era un campo HUÉRFANO**:
`SlotAvailability::occupancyMap()` lee **`ticket_types.duration_min`** (`select` de la consulta), así
que la longitud tiene que vivir ahí o **no la lee nadie**. *Un campo nuevo que duplica lo que otro ya
significa acaba divergiendo, y el que decide el aforo no será el que se enseña* — es la misma lección
que `#329` con los tramos de precio.
▶ **Medido y por eso es seguro reusarla**: los **7** complementos que existen hoy tienen
`duration_min` **nula**, así que la columna está libre y ningún dato actual cambia de significado.

⚠️⚠️ **Y el interruptor es EXPLÍCITO, no derivado.** La alternativa —«ocupa si tiene `duration_min`»—
sería una regla implícita: alguien pone una duración a la camiseta para pintarla en la ficha y **empieza
a comerse aforo en silencio**. *Consumir plazas sin que nadie lo haya pedido es exactamente el fallo
que ninguna guarda ve.* Con `false` por defecto, **los siete complementos actuales siguen igual por
construcción** y la feature se apaga sola sobre los datos que ya existen.
⚠️ Guarda: `occupies_after_parent = true` con `duration_min` nula es una configuración **imposible** —
declara que ocupa y no dice cuánto. Se rechaza en el modelo, como `#299` hizo con los tramos solapados.

### 4.2 · La cantidad son ENTRADAS, no horas

`quantity_mode = fixed`, y la cantidad es **cuántas de las entradas de la línea se quedan**. De ahí
salen dos cosas gratis:

- **El precio es por entrada** (`unit_price × quantity`), que es lo que el owner quiere, **sin
  inventar un segundo eje**. La pregunta «¿la hora extra escala con el grupo?» desaparece: escala
  porque la cantidad *son* las personas.
- **El tope natural es la cantidad de la línea padre**: no se pueden quedar 5 de 4 entradas. El
  `max_qty` del pivote sigue valiendo como techo comercial, y este otro es el **físico**.

▶ **¿Y dos horas para una persona?** Otro complemento dado de alta («2 horas extra»,
`duration_min = 120` y el interruptor puesto). Es data-driven y es el owner quien decide cuáles ofrece.

### 4.3 · Lo que la línea hija guarda al nacer

| campo | valor | por qué |
|---|---|---|
| `slot_id` | **la franja siguiente al tramo del padre** | es dónde ocupa. ⚠️ **Lo elige el SERVIDOR**, nunca el navegador |
| `seats` | `quantity × seats_per_unit` | las plazas que de verdad se quedan (hoy los complementos guardan 0) |
| `parent_item_id` | la línea padre | como cualquier complemento |

▶ Con eso, **`occupancyMap` la cuenta sin tocar una línea de su código**: tiene franja, plazas y un
producto con duración. Es la pieza que hace este diseño barato.

### 4.4 · Lo que SÍ hay que cambiar

1. **El lock.** `OrderCreator` salta los complementos (dentro de `createPendingOrder()`) y construye los ocupantes de la
   cesta solo con las líneas de primer nivel (`otherOccupants()`). Las horas extra tienen
   que entrar como **ocupantes propios** y **sus franjas tienen que quedar dentro del lock**.
   ❗ Es la parte que toca `AFORO-01` y la que exige `VERIFY_CONC=1`.
2. **La franja de destino tiene que existir y admitir.** Si el tramo del padre acaba a la hora de
   cierre, o la franja siguiente está llena o cerrada, **la hora extra no se puede ofrecer ni vender**.
3. **La edición.** Mover el padre de hora o de día **mueve la hija**; cancelarlo la cancela; bajar la
   cantidad del padre por debajo de la hija tiene que rechazarse o recortarla (`OrderItemEditor`).
4. **Las superficies.** Hoja de sala, puerta y calendario tienen que decir **cuántos se quedan y hasta
   cuándo**: un operador que lea «4 entradas» y tenga 1 niño a las 12:15 no sabrá qué hacer.

### 4.5 · ❗❗ El riesgo de DISEÑO, que no es fontanería

**La hora se ofrece con la duración base.** El cliente elige las 12:00, añade la hora extra, y la
franja de las 14:00 puede estar llena o no existir. Si la oferta no se recalcula al cambiar el
complemento, **se ofrece algo que el checkout rechaza** — el primo hermano exacto de `AFORO-02`.

▶ **Lo que lo hace resoluble**: los complementos se eligen **en el mismo paso que la hora**, así que
la oferta puede recalcularse ahí sin reordenar el embudo.
⚠️ **La decisión del dominio manda igual**: la oferta es presentación; `OrderCreator` re-comprueba y
lockea. Recalcular evita el rechazo, no lo sustituye.

### 4.6 · ❗❗ LOS SEIS BORDES, salidos de someter este diseño a presión

No son «casos raros»: son los sitios por donde una feature de aforo se rompe **sin fallar**.

1. **`excludeItemId` es UN solo id, y la hija se quedaría contando.** Al editar la línea padre, el
   aforo excluye al padre del recuento (`SlotAvailability::availableFor()`) pero **no a su hora extra**:
   la edición **competiría contra su propio complemento** y vería la franja más llena de lo que está.
   ▶ Al excluir un padre hay que excluir **su descendencia**. Es un cambio de firma, no un parche.
2. **La extensión tiene que caer en el BORDE de una franja.** La rejilla es discreta y `occupancyMap`
   marca por `start_time`: si el padre dura 90 min sobre una rejilla de 60, su fin cae **a mitad** de
   una franja y «la siguiente» es ambigua — o se solapa con el padre o deja un hueco.
   ▶ Regla: si `duración del padre` no es múltiplo del paso de la rejilla, **la hora extra no se
   ofrece** en ese producto. Se comprueba **al configurar**, no al vender.
3. **Sin franja siguiente no hay hora extra.** El último tramo del día, o una franja cerrada
   (`online_sales_open = false`, `status = closed`), o llena: **no se ofrece y se rechaza**. Los tres
   estados ya los distingue `availableFor()`; hay que consultarlos para la franja de la HIJA, no la del
   padre.
4. **Re-programar arrastra dos comprobaciones, no una.** `ItemRescheduleOffer` ofrece huecos para el
   padre; con hora extra, un hueco solo vale si **también** cabe la hija detrás. Ofrecer por el padre
   solo es la trampa de `AFORO-02` otra vez, por la puerta de la edición.
5. **Bajar la cantidad del padre por debajo de la hija.** 4 entradas con 3 horas extra → se bajan a 2:
   quedan 3 personas quedándose de 2 que hay. Se **rechaza** con su frase, o se recorta la hija; lo que
   no puede es aceptarse en silencio.
6. **La cesta cuenta contra sí misma.** `OrderCreator::otherOccupants()` construye los ocupantes
   provisionales de las OTRAS líneas del carrito: las horas extra de esas líneas tienen que entrar
   ahí, o dos líneas del mismo pedido se venderán la misma plaza de la franja siguiente.

⚠️ **Los seis son de LECTURA o de ESCRITURA del aforo, y ninguno lo ve un test de SQLite** (`INVARIANTES
§6`): el 1, el 4 y el 6 se prueban con casos; el 3 y el 5 con casos; **el 2 se previene al configurar**.
La carrera del punto 6 es la que exige el escenario nuevo del verificador (§6·1).

### 4.7 · Lo que NO se toca

- Ningún complemento actual (§4.1).
- `seats_per_unit`, `max_qty`, dependencias, obligatoriedad: se usan **tal cual**.
- El catálogo no crece: dos entradas y los complementos que el owner quiera colgar de la larga.

### 4.8 · Casos de uso, con los números delante

Rejilla de 60 min, entrada de 2 h, complemento «1 hora extra» a 5 €, aforo de la zona 20/franja.

**Caso 1 · El normal.** Familia de 4, entrada a las 12:00. Se queda **uno**.
```
línea padre   Entrada 2 h ×4   slot 12:00   seats 4   → ocupa 12:00 y 13:00
línea hija    Hora extra  ×1   slot 14:00   seats 1   → ocupa 14:00
cobro         4 × entrada + 1 × 5 €
aforo         12:00 −4 · 13:00 −4 · 14:00 −1
```
▶ Lo que el operador tiene que leer: **«4 entradas · 1 se queda hasta las 15:00»** (§7·D3).

**Caso 2 · Se quedan tres de los cuatro.** Complemento ×3 → **15 €** y `seats 3` a las 14:00. El precio
escala con las personas **porque la cantidad SON personas**, sin campo nuevo y sin segundo eje.

**Caso 3 · Dos entradas en el mismo pedido, cada una con lo suyo.** Ya funciona hoy: los complementos
cuelgan de **cada línea** (`parent_item_id`). Dos padres, dos hijas, dos franjas, independientes.

**Caso 4 · No cabe, y hay que decirlo ANTES.** Misma compra, pero a las 14:00 quedan **0** plazas.
▶ La hora extra **no se ofrece**; si llega igual (pestaña vieja, `POST` forjado), el dominio la rechaza
bajo el lock. Es el borde 3 de §4.6 y el motivo de §4.5.

**Caso 5 · El último tramo del día.** Entrada 20:00–22:00 y el parque cierra a las 22:00: **no hay
franja siguiente**, así que no se ofrece a esa hora. *La hora extra no es una propiedad del producto:
es una propiedad del producto EN ESA FRANJA.*

**Caso 6 · La carrera, que es lo que obliga al verificador.** Dos clientes se disputan la última plaza
de las 14:00: uno la quiere como entrada normal y **el otro como hora extra**. Sin el lock cubriendo la
franja de la HIJA, **ganan los dos**. Es el escenario nuevo de §6·1, con su control negativo.

**Caso 7 · El operador edita.** Baja la línea de 4 a 2 con 3 horas extra vendidas → **se rechaza**
(borde 5). Mueve el pedido del martes al jueves → hay que comprobar **las dos** franjas, no solo la del
padre (borde 4).

**Caso 8 · El que NO debe cambiar nada.** Un pedido con una camiseta de complemento: interruptor a
`false` → `slot_id NULL`, `seats 0`, invisible al aforo. **Idéntico a hoy**, y es el caso de CONTROL
de §6·2.

## 5. Impacto en invariantes

| Invariante | Impacto |
|---|---|
| **`AFORO-01`** | ❗ **Directo**: las franjas de la hora extra entran en el lock. `CRITICAL_RE` + `VERIFY_CONC=1` |
| **`AFORO-02`** | ❗ **Directo** (§4.5) |
| **`AFORO-05`** | la edición desde el panel mueve/cancela la hija con el padre |
| `PAY-12` | el precio se pinta, no se suma en el cliente: sin cambio |
| `PAY-16`/`PAY-17` | la hija es una línea con precio como cualquier complemento: sin cambio |

## 6. Plan de verificación empírica

1. **Escenario nuevo en `purchase:verify-oversell`**: N compras concurrentes en las que **la última
   plaza en disputa es la de la franja que solo ocupa la hora extra**, con **control negativo** (sin el
   lock, sobreventa). Es la prueba que decide si esto se puede desplegar.
2. **CONTROL de que nada viejo se mueve**: un complemento con `occupies_after_parent = false` deja el aforo
   **idéntico** — medido antes y después sobre los mismos datos.
3. **El borde del día**: hora extra cuyo tramo se sale del cierre → no se ofrece **y** se rechaza.
4. **La trampa de §4.5, en navegador**: elegir una hora que cabe, añadir la extra, y comprobar que la
   oferta se recalcula **antes** de que el checkout la rechace.
5. **La edición**: mover el padre de día arrastra la hija; bajar su cantidad por debajo de la hija se
   rechaza; cancelar el padre cancela la hija.
6. **Las superficies**: hoja de sala y puerta dicen cuántos se quedan y hasta cuándo.

## 7. Revisión y decisión

**`[PENDIENTE: owner]` D1 — el ✅ a esta spec.** Sin él no se escribe código (`CONVENCIONES §5`).

**`[PENDIENTE: owner]` D2 — ¿solo entradas, o también packs y excursiones?** El mecanismo no
distingue, pero un pack arrastra `prep_after_min` (limpieza) y su **cupo de grupos por franja**
(`max_per_slot`): una hora extra en un pack podría comerse el grupo de la franja siguiente. Si entra,
se dice aquí y se le añade su escenario al verificador.

**`[PENDIENTE: owner]` D3 — ¿qué ve el operador?** La hoja de sala y la puerta tienen que decir
«**1 de 4 se queda hasta las 13:00**». Falta decidir la forma; sin ella, la feature es correcta en la
base de datos e ilegible en el mostrador.

▶ **Resuelto y sin coste**: el precio por persona (§4.2, sale de la cantidad), «solo para ciertos
productos con límites» (pivote de siempre) y que dos entradas del mismo pedido tengan su hora extra
independiente (los complementos cuelgan de cada línea).
