# [SPEC] La HORA EXTRA — un complemento que OCUPA

> Estado: 🟦 **CÓDIGO COMPLETO — LAS CUATRO TANDAS EN EL ÁRBOL (2026-09-03, `#410`/`#411`; §8 es
> la ejecución). Suite 4.107 en verde · 28/28 mutaciones muerden · los SIETE escenarios de
> `purchase:verify-oversell` + `redsys:verify-concurrency` en verde sobre InnoDB · §6·4 verificado
> en NAVEGADOR (4/4). El producto REAL está dado de alta en localhost con 6 pedidos demo (§8.5) y
> la PRIMERA pasada del ojo del owner está hecha y aplicada — sus dos ajustes, en §8.5.**
> ❗ **EMPIEZA POR §8** (qué hay construido y sus trampas), después **§4.11** (la segunda revisión:
> el peor hueco era el TOPE por SUMA), **§4.10** y **§4.9**.
> Carril: producto/reservas. Autor: agente, 2026-09-02 · segunda revisión y ejecución 2026-09-03.
> ⚠️ **Toca AFORO**: `CartOccupants` · `AddonOccupancy` · `AddonResolver` están en el `CRITICAL_RE`.

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
⚠️⚠️ **Y el guard del modelo necesita CINTURÓN, exactamente por el límite que `#299` dejó escrito**
(los eventos de Eloquent no ven `Query\Builder::update()` ni SQL crudo): si una fila
`occupies = true, duration_min = null` entra por la puerta de atrás, `occupancyMap` interpreta la
duración nula como **«hasta el cierre»** — la hija ocuparía TODAS las franjas restantes del día,
que es el peor modo de fallo posible. El cinturón vive en el **punto único de composición** (§4.4·6):
un complemento que ocupa sin duración **no se ofrece ni se vende** (falla hacia invisible, la doctrina
de la casa), jamás «ocupa sin fin». Con caso propio en §6.
⚠️ **Y `seats_per_unit` es el hermano silencioso de `duration_min`**: vive en la MISMA sección que
`CatalogForm` esconde a los complementos (§4.9), así que se queda en su default de BD (1 — medido:
los 7 complementos reales lo tienen a 1). El diseño depende de ese 1 sin que ningún formulario lo
enseñe, y depender en silencio de un default es la familia de §4.9. El guard del modelo lo hace
explícito: `occupies_after_parent = true` exige **también** `seats_per_unit >= 1`.

### 4.2 · La cantidad son ENTRADAS, no horas

`quantity_mode = fixed`, y la cantidad es **cuántas de las entradas de la línea se quedan**. De ahí
salen dos cosas gratis:

- **El precio es por entrada** (`unit_price × quantity`), que es lo que el owner quiere, **sin
  inventar un segundo eje**. La pregunta «¿la hora extra escala con el grupo?» desaparece: escala
  porque la cantidad *son* las personas.
- ⚠️⚠️ **El tope por la cantidad del padre NO viene gratis, y decirlo fue un error de esta spec**
  (revisión adversarial, §4.9·B). `AddonResolver::effectiveQuantity()` **solo mira la cantidad de la
  línea en la rama `per_guest`**; en `fixed` los únicos techos son `max_qty` (nullable = **sin
  límite**, y las 28 filas reales de `product_addons` lo tienen a `null`) y las reglas de incluido.
  Medido: pedir 40 horas extra sobre una línea de 1 devuelve **40**. ▶ Pasa a ser trabajo explícito
  de §4.4·5.

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
5. ❗❗ **El TOPE por la cantidad del padre, que §4.2 daba por gratis y no existe — y tiene DOS
   mitades, no una** (la segunda la encontró la revisión de §4.11). La invariante real es
   **`Σ(cantidades de los complementos que OCUPAN de la línea) ≤ cantidad del padre`**, y
   `effectiveQuantity(pivot, qty, lineQuantity)` **no puede imponerla**: resuelve UN complemento cada
   vez y no ve a los hermanos. Con «1 hora extra» ×3 y «2 horas extra» ×3 sobre un padre de 4, cada
   uno pasa (3 ≤ 4) y se quedan **6 personas de 4** — se cobra un imposible físico y se ocupan plazas
   fantasma, sin que el aforo de zona lo pare si es holgado. ▶ El tope por-complemento va en
   `effectiveQuantity()` y **la SUMA va en `resolve()`**, que es el único sitio que ve todas las
   filas — y es la fuente ÚNICA que comparten el presupuesto (`CartPricer`) y el cobro
   (`OrderCreator`). Con eso, el borde 5 de §4.6 deja de ser una regla suelta del editor y pasa a ser
   **la otra mitad de la misma regla**.
   ⚠️ Y `per_guest` e `is_mandatory` se **prohíben** para un complemento que ocupa: el primero le
   impone la hora extra a todo el grupo (justo lo contrario del encargo) y el segundo la haría
   obligatoria, convirtiendo «no se ofrece la hora extra» en «no se puede vender el padre a esa hora»
   — `AFORO-02` por otra puerta.
6. ❗❗❗ **El EDITOR del panel es un punto de NACIMIENTO, no solo de mutación.** `OrderItemEditor::edit()`
   compone la fila hija **él mismo, en línea**, sin pasar por `AddonResolver`, y escribe
   `slot_id => null` y `seats => 0` como **literales** bajo el comentario «complementos (NEUTROS al
   aforo)». Un operador que añada «1 hora extra» desde «Gestionar → Complementos» crearía una
   ocupación que **el aforo no cuenta**, sin error y sin franja que imprimir.
   ⚠️ **Y el gemelo silencioso**: subir una hija de 1 a 3 escribe solo `quantity` y **deja `seats`
   quieto** → tres personas ocupando una plaza. *No hace falta concurrencia para romperlo, así que
   tampoco lo cazaría el verificador de §6·1.*
   ▶ La composición de la hija (franja siguiente + `seats`) vive en **UN sitio** que compartan
   `AddonResolver::resolve()` y `OrderItemEditor::edit()`, con la comprobación de aforo dentro del
   `withZoneDayLock()` que el editor **ya toma**.

### 4.5 · ❗❗ El riesgo de DISEÑO, que no es fontanería

**La hora se ofrece con la duración base.** El cliente elige las 12:00, añade la hora extra, y la
franja de las 14:00 puede estar llena o no existir. Si la oferta no se recalcula al cambiar el
complemento, **se ofrece algo que el checkout rechaza** — el primo hermano exacto de `AFORO-02`.

▶ **Lo que lo hace resoluble**: los complementos se eligen **en el mismo paso que la hora**, así que
la oferta puede recalcularse ahí sin reordenar el embudo.
⚠️ **La decisión del dominio manda igual**: la oferta es presentación; `OrderCreator` re-comprueba y
lockea. Recalcular evita el rechazo, no lo sustituye.

### 4.6 · ❗❗ LOS NUEVE BORDES, salidos de someter este diseño a presión

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
   padre. ⚠️ Y «la franja de la hija» es su franja de ENTRADA: una hija de 120 min abarca DOS — la
   comprobación es `availableFor(franjaHija, duración de la HIJA)`, que ya valida el tramo entero
   (`spanCoversDuration`), no una consulta a una franja suelta.
4. **Re-programar arrastra dos comprobaciones, no una.** `ItemRescheduleOffer` ofrece huecos para el
   padre; con hora extra, un hueco solo vale si **también** cabe la hija detrás. Ofrecer por el padre
   solo es la trampa de `AFORO-02` otra vez, por la puerta de la edición.
5. **Bajar la cantidad del padre por debajo de la hija.** 4 entradas con 3 horas extra → se bajan a 2:
   quedan 3 personas quedándose de 2 que hay. Se **rechaza** con su frase, o se recorta la hija; lo que
   no puede es aceptarse en silencio.
6. ❗❗❗ **La cesta cuenta contra sí misma, y son DOS derivaciones, no una.**
   `OrderCreator::otherOccupants()` (el cobro) tiene un **gemelo**: `AvailabilityReader::occupantsOf()`,
   que alimenta `SlotOffer::offerableTimes()` y con él la web, la API y `CartLineValidator`. Arreglar
   solo el del cobro deja la **oferta ciega**: el cliente vería libre una franja que el checkout le
   rechaza — `AFORO-02` otra vez, y justo sobre la pieza en la que §4.5 apoya su mitigación.
   ⚠️⚠️ **Y ninguna de las dos cuenta a los HERMANOS de la misma línea.** §4.2 propone dar de alta
   «2 horas extra» como producto aparte: dos complementos que ocupan, colgados de la MISMA línea,
   caen sobre la misma franja y **cada uno se valida contra un mapa que no incluye al otro** (no están
   en BD todavía, y los ocupantes provisionales solo traen OTRAS líneas). ▶ **La última plaza se vende
   dos veces en una sola petición, sin carrera ninguna y con el lock puesto.** Es el peor de los nueve.
7. ❗❗ **Un padre SIN duración no tiene «franja siguiente».** Medido: **2 de las 10 entradas**
   (`duration_min` nula = «ilimitada hasta el cierre») no tienen tramo que termine. La guarda de §4.1
   protege la duración nula del COMPLEMENTO, nunca la del PADRE: hay que prohibir el enganche.
8. ❗❗ **La rejilla NO es uniforme, y está medido sobre datos reales.** El borde 2 supone «un paso de
   rejilla», y `slot_templates` es `zona × día × hora`, cada fila con su duración: hay zonas a 60, otra
   a 180, y **la zona 4 tiene el mismo día franjas de 60 min y una de 840** (09:00–23:00, aforo 300)
   — rejillas SOLAPADAS, que `SlotAvailability::spannedSlots()` tolera a propósito. Con dos franjas
   abiertas a la vez, «la siguiente» no está definida por el producto: **se decide por zona y día**, no
   al configurar el catálogo. Eso corrige al propio borde 2.
   ▶ **Y la regla de selección tiene que ser DETERMINISTA y está escrita aquí** (la segunda revisión
   cazó que este borde corregía el DÓNDE sin decir el QUÉ): la franja de la hija es **la de la MISMA
   zona y día cuyo `start_time` == fin del tramo del padre** (`entry_start + duration_min` del padre,
   el mismo `spanEnd` que usa el aforo). Es única **por esquema** —`slots` lleva
   `UNIQUE(zone_id, date, start_time)` (verificado en la migración y en los índices reales)—, así
   que la búsqueda no puede devolver dos. **Si no existe** (el cierre, un hueco de rejilla, o el fin
   del padre cae DENTRO de una franja larga solapada sin que ninguna empiece ahí), la hora extra
   **no se ofrece ni se vende** para esa hora: la ausencia no se resuelve eligiendo una parecida,
   se declara invendible — fallar hacia invisible.
9. ❗❗❗ **La duración del padre es EDITABLE con ventas hechas, y eso descoloca a la hija.** Verificado:
   `CatalogResource::hasSales()` protege **solo el campo de zona** (`CatalogForm`); `duration_min` no
   lleva `disabled`. Subir una entrada de 120 a 180 alarga el tramo del padre **de todas las líneas ya
   vendidas** y **mete a la hija dentro del tramo de su propio padre**: la misma persona contada dos
   veces en la franja solapada, sin fallo y sin aviso. Es la familia del sello de `#288`, en el eje del
   aforo. ▶ Salida barata y con precedente: **bloquear `duration_min` cuando `hasSales()`**, igual que
   ya se hace con la zona.

⚠️ **Son de LECTURA o de ESCRITURA del aforo, y ninguno lo ve un test de SQLite** (`INVARIANTES
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

### 4.9 · ❗❗❗ EL BLOQUEO DE CONFIGURACIÓN: hoy el interruptor NO SE PUEDE ENCENDER

Ninguna de las seis lentes lo buscó —todas entran por la compra— y lo encontró el crítico de
completitud: **el diseño depende de un dato que el panel BORRA**.

Verificado en el código:
- `CreateCatalog::normalizeByType()` hace, para un complemento, `zone_id = null` y **`unset()` de
  `duration_min`**, bajo el comentario *«un complemento no consume aforo ni tiene zona/horario»*.
- `CatalogForm` esconde esos campos para complementos (`visible(type !== TYPE_ADDON)`).

▶ O sea que **la columna que §4.1 llama «la que ya existe» el panel la vacía al guardar**, y con la
guarda que la propia §4.1 propone —«ocupa y no dice cuánto» es imposible— una hora extra creada desde
el panel **la rechazaría el modelo**. *El diseño era coherente consigo mismo y aun así no se podía
poner en marcha: la puerta de entrada del dato estaba tapiada.*

▶ **Lo que hay que hacer, y es trabajo de la tanda, no fontanería**: el formulario del catálogo tiene
que ofrecer `duration_min` **también** para un complemento que declare `occupies_after_parent`, y
`normalizeByType()` dejar de borrarla en ese caso. La zona **sigue siendo nula a propósito**: la hija
no tiene zona propia, la hereda de la franja que ocupa (`occupancyMap` filtra por `entry.zone_id`, la
del SLOT, no la del producto).
⚠️⚠️ **Y son DOS páginas, no una** (segunda revisión): `normalizeByType()` vive solo en
`CreateCatalog`; **`EditCatalog` no tiene simétrico** para los campos de complemento — hoy da igual
porque el formulario esconde el campo y Filament no dehidrata lo oculto, pero la regla 12 de la casa
es no confiar en eso (es literalmente el patrón de su propio «2.bis»): la EDICIÓN necesita la misma
defensa contra un payload manipulado que meta o borre `duration_min`/`occupies_after_parent` donde
no toca. ⚠️ Y en la MISMA sección oculta vive **`seats_per_unit`** (`CatalogForm`, el bloque
`visible(type !== TYPE_ADDON)`): no se destapa — un complemento ocupa 1 plaza por unidad y punto —,
pero el guard de §4.1 lo exige `>= 1` para que la dependencia del default deje de ser silenciosa.

⚠️ **Lección**: *una revisión que solo entra por donde se vende no ve si el dato se puede introducir.*
Las seis lentes miraron compra, aforo, ciclo de vida, superficies, alternativas y huecos; ninguna
preguntó **quién enciende el interruptor**.

### 4.10 · Lo que la revisión adversarial CAMBIÓ

35 hallazgos crudos → 10 refutados a fondo → **3 defectos reales** (cinco supervivientes que se
reducen a tres: dos los encontraron **dos lentes independientes cada uno**, que es la mejor señal de
que no son ruido) **+ 2 condiciones de diseño** del crítico de completitud.

| | qué era | dónde vive ahora |
|---|---|---|
| **1** | el editor del panel **crea** hijas con `slot_id null` y `seats 0` cableados, y al subir la cantidad no toca `seats` | §4.4·6 |
| **2** | el «tope natural = la cantidad del padre» **no existe** en el código | §4.2 (retirado) y §4.4·5 |
| **3** | los ocupantes provisionales son **DOS** derivaciones, y los **hermanos** de la misma línea no los cuenta ninguna → se vende dos veces la última plaza **sin carrera** | §4.6·6 |
| **4** | el panel **borra** `duration_min` de un complemento: el interruptor no se puede encender | §4.9 |
| **5** | la duración del padre es **editable con ventas hechas** y descoloca a la hija | §4.6·9 |

⚠️ **Y lo que la revisión REFUTÓ, para que nadie lo vuelva a levantar**: que dar franja a la hija le
haría emitir QR propios (la mecánica de `TicketIssuer` es cierta, las consecuencias no) · que cambiar
el producto del padre dejaría la hija descolgada (hay dos defensas aguas arriba) · que la hija
heredaría el «ya terminó» de su padre · que el recálculo de §4.5 no tiene canal (lo tiene, §4.4·2) ·
y que ninguna superficie del cliente diría la hora (es determinista y se puede componer).

### 4.11 · ❗❗❗ La SEGUNDA revisión (2026-09-03, pre-obra): 4 huecos, 3 reglas escritas y 1 decisión

Antes de construir se verificó **cada afirmación medible de la spec contra el código y la BD real**
(todas exactas: los 7 complementos con `duration_min` nulo, 2/10 entradas ilimitadas, 0 hijas con
franja o plazas, 28/28 pivotes sin `max_qty`, los literales del editor, el `unset()` del panel) y se
barrió lo que la spec **no** miraba. Salieron cuatro huecos reales y tres reglas sin escribir:

| | qué era | dónde vive ahora |
|---|---|---|
| **1** | el tope del padre por-complemento no ve a los HERMANOS: `Σ(ocupantes de la línea) ≤ padre` no lo imponía nadie — 6 se quedan de 4 cobrando el imposible | §4.4·5 (la SUMA en `resolve()`) |
| **2** | el guard «ocupa sin duración» sin CINTURÓN: por la puerta de atrás (`Query\Builder::update()`, el límite de `#299`), duración nula = **«hasta el cierre»** — la hija ocuparía el resto del día | §4.1 (el cinturón en la composición) |
| **3** | `seats_per_unit` está en la MISMA sección oculta que `duration_min`: el diseño dependía en silencio del default 1 | §4.1 (el guard lo exige) + §4.9 |
| **4** | la regla de «la franja siguiente» no estaba ESCRITA (el borde 8 corregía el dónde, no el qué) | §4.6·8 (determinista, con el `UNIQUE` medido) |

▶ ⚠️⚠️ **REVISADA EL MISMO DÍA por `DECISIONES #415`, y la corrección va ANTES que el texto de
abajo.** El owner dio de alta la hora extra como producto de **viernes, findes, vísperas y festivos**,
que es justo el caso que este párrafo dejó anotado como pendiente. Hoy **un complemento se tarifica
por el día de la VISITA** en los SIETE puntos que lo hacen (eran tres en este texto: el censo dio
siete), así que «solo findes» se expresa poniéndole precio únicamente en la tarifa `special` y la
hora extra aparece o no según el día de la FIESTA, no según cuándo se abra la web. El límite que este
párrafo aceptaba **ya no existe**; lo que sigue vale como historia de por qué se aceptó.
▶ **`[DECIDIDO owner, 2026-09-03]` · el PRECIO de la hora extra NO varía por día (en principio).**
Lo que lo motiva: los complementos se tarifican a **`Carbon::today()`** —el día de la COMPRA— en los
TRES caminos (`OrderCreator::createPendingOrder()`, `CartPricer`, `CreateManualOrderPage`), mientras
el padre se tarifica por el día de la VISITA. Presupuesto y cobro coinciden (no hay bug de paridad),
pero un complemento que OCUPA una franja es un producto anclado a un día: si algún día el owner
quisiera suplemento de finde en la hora extra, cobraría el precio del día en que se compró. **Con
esta decisión el límite queda aceptado y escrito**; si algún día se quiere precio por día, el cambio
es tarificar los complementos al día de la línea — un cambio de conducta para TODOS los complementos,
con decisión propia. *No lo descubras en producción: ya está descubierto aquí.*

▶ **`TicketIssuer` NO emite tickets para las hijas, y es una decisión, no un accidente.** Su filtro
es `whereNotNull('slot_id')` **sin** `parent_item_id` — con la hija estrenando franja, emitiría
`seats` tickets nuevos. La primera revisión refutó las consecuencias (la credencial de puerta es el
carné del cliente; los tickets son casi vestigiales) y es verdad, pero **una conducta emergente y
muda es el enemigo**: un ticket es una ADMISIÓN y las admisiones son líneas de primer nivel — la
hora extra no es una entrada nueva, es la misma persona quedándose. `TicketIssuer` gana
`whereNull('parent_item_id')` (no-op hoy: ninguna hija tiene franja) **fijado con caso propio**.

▶ **`CRITICAL_RE` no cubre las piezas nuevas.** El servicio unificado de ocupantes (nuevo) y
`AddonResolver` (que pasa a llevar el tope — es aforo) tienen que entrar en el regex del hook
**y** en `CriticalPathGateTest`, que vigila que la lista cubra lo que debe. La tanda lo hace; esta
línea existe para que nadie lo dé por hecho.

▶ **Lo que la segunda revisión CONFIRMÓ a favor del diseño** (verificado, para no re-derivarlo):
cancelar/reembolsar la hija **libera aforo gratis** (`occupancyMap` excluye `cancelled_at`) · la
poda (`AFORO-04`) **protege la franja de la hija** por el mecanismo existente (`SlotGenerator` mira
`order_items.slot_id` y cierra en vez de borrar) · **D3 aguanta estructuralmente**: «Mis reservas»,
la puerta y el scope del dashboard filtran por `parent_item_id NULL` **y** `slot_id NOT NULL`, así
que la hija no se cuela como reserva propia · la **cascada padre→hijas ya existe bajo lock**
(`OrderItemCanceller`) · el **lock no necesita grano nuevo** (es zona×día y la hija comparte ambos
con el padre: lo que cambia es la VALIDACIÓN) · el **pedido manual pasa por `OrderCreator`** (un
solo camino de lock) · y el **canal del recálculo de §4.5 ya existe en el contrato**
(`POST /availability/{product}/times` lleva la cesta con complementos anidados) — con la regla
vigente de que «la línea que se configura no va en la cesta», que la tanda de oferta resuelve.

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
   lock, sobreventa). Es la prueba que decide si esto se puede desplegar. **Se escribe ANTES que la
   feature y se ve FALLAR** (D1, §7).
2. **CONTROL de que nada viejo se mueve**: un complemento con `occupies_after_parent = false` deja el aforo
   **idéntico** — medido antes y después sobre los mismos datos.
3. **El borde del día**: hora extra cuyo tramo se sale del cierre → no se ofrece **y** se rechaza.
4. **La trampa de §4.5, en navegador**: elegir una hora que cabe, añadir la extra, y comprobar que la
   oferta se recalcula **antes** de que el checkout la rechace.
5. **La edición**: mover el padre de día arrastra la hija; bajar su cantidad por debajo de la SUMA de
   sus hijas que ocupan se rechaza; cancelar el padre cancela la hija. ▶ **Y el camino del PANEL con
   caso propio** (§4.4·6): añadir una hora extra desde «Gestionar → Complementos» tiene que nacer con
   franja y plazas, y subirle la cantidad tiene que recalcular `seats` — hoy los dos escriben literales.
6. ❗❗ **El caso de los HERMANOS en el AFORO, que sobrevende SIN carrera** (§4.6·6): una línea con
   «1 hora extra» y «2 horas extra» a la vez, en una zona donde solo queda UNA plaza en la franja
   siguiente. Con el lock puesto y sin concurrencia, hoy se venderían las dos. Es un caso de suite, no
   del verificador.
7. ❗❗ **El TOPE por SUMA de hermanos** (§4.4·5, segunda revisión): «1 hora extra» ×3 y «2 horas
   extra» ×3 sobre un padre de 4 → **se rechaza** (6 > 4), aunque el aforo de zona sobre. Y el caso
   por-complemento: ×5 sobre un padre de 4 → se acota/rechaza. Presupuesto y cobro dicen lo mismo.
8. **El CINTURÓN del guard** (§4.1, segunda revisión): una fila `occupies = true, duration_min = null`
   metida por la puerta de atrás (`Query\Builder::update()`, esquivando los eventos del modelo)
   **ni se ofrece ni se vende** — y jamás ocupa «hasta el cierre».
9. **La oferta y el cobro dicen lo MISMO**: la misma cesta pasada por `AvailabilityReader::times()` y
   por `OrderCreator::createPendingOrder()` tiene que coincidir en qué horas ofrece y cuáles acepta
   (`AFORO-02`).
10. **El bloqueo de configuración** (§4.9): dar de alta una hora extra **desde el panel** y comprobar
    que su `duration_min` sobrevive al guardado — **y lo mismo al EDITARLA** (`EditCatalog` no tiene
    `normalizeByType` y la defensa regla-12 es de la tanda).
11. **`TicketIssuer` no emite para hijas** (§4.11): un pedido pagado con hora extra emite los tickets
    del PADRE y ninguno de la hija — fijado con caso, porque hoy es no-op y mañana no.
12. **Las superficies**: hoja de sala y puerta dicen cuántos se quedan y hasta cuándo.


## 7. Revisión y decisión — ✅ **LAS TRES, CERRADAS** (`[DECIDIDO owner, 2026-09-02]`)

### D1 · Se construye, y el ORDEN es parte de la decisión ✅

`[DECIDIDO owner]`: adelante, **empezando por unificar la derivación de ocupantes provisionales**.

▶ Una sola función compone los ocupantes —incluyendo las hijas que ocupan **y los hermanos de la
misma línea**— y la usan las DOS derivaciones: `OrderCreator::otherOccupants()` (el cobro) y
`AvailabilityReader::occupantsOf()` (la oferta). *Tres de los nueve bordes de §4.6 son el mismo
defecto visto desde sitios distintos: unificar primero convierte tres arreglos en uno y hace que la
feature nazca sobre una base que ya no miente.*

⚠️⚠️ **Condición innegociable, y es doctrina de la casa**: el escenario de `purchase:verify-oversell`
(§6·1) **se escribe ANTES que la feature y se ve FALLAR**. Un verde solo vale si el instrumento se ha
visto en rojo.

### D2 · Solo ENTRADAS ✅ — y la razón es mejor que «los packs son más arriesgados»

`[DECIDIDO owner]`: entradas primero. Y su aclaración sobre los packs —*«el complemento de hora extra
es para todos los invitados»*— **cambia el argumento y refuerza la decisión**:

- ⚠️⚠️ **No simplifica: agranda el hueco.** `PackAvailability::occupancyMaps()` filtra por
  `type = pack`, así que un complemento **nunca** cuenta en `max_guests_per_slot`, el tope que
  gobierna la sala. Con una persona son 1 invitado invisible; **con toda la fiesta, veinte.** Mismo
  defecto, veinte veces más caro.
- ❗ **Y sobre todo: si en un pack se quedan TODOS, eso ya no es un complemento que ocupa — es que la
  fiesta DURA MÁS.** O sea el mecanismo de la duración (la opción D de §3, que existe hoy y ya
  re-tarifica y re-comprueba aforo), no éste.

▶ **Entradas y packs quieren mecanismos distintos**, y por eso los packs no entran aquí:

| | qué es físicamente | mecanismo natural |
|---|---|---|
| **Entrada** | una parte del grupo se queda | el complemento que OCUPA (esta spec) |
| **Pack** | la fiesta entera dura más | duración: cambio de producto, o un pack más largo |

### D3 · Se ve como un complemento NORMAL ✅

`[DECIDIDO owner]`: *«quiero que se vea como un complemento normal»*. Nada de una línea propia con la
hora de salida.

▶ **Y sale barato, medido**: la hoja de sala ya imprime la **ventana horaria** (`slotWindow()`) y la
**duración** junto a la cantidad, y los complementos como `{cantidad} × {nombre}`. El operador lee
`12:00–14:00 · 4 · 2 h` y `1 × Hora extra`: **tiene todos los datos y le queda una resta.**

⚠️ **Su único coste, dicho para que nadie lo descubra en el mostrador**: el campo «duración» seguirá
diciendo **2 h** aunque una persona esté tres. No es falso para la línea —los cuatro compraron 2 h—
pero es incompleto. Si algún día estorba, el arreglo es una línea, no un rediseño.
⚠️ La puerta hereda la misma decisión: `GateProfile` ya transporta `addons` y se pinta como cualquier
otro.

▶ **Resuelto y sin coste** (no vuelvas a preguntarlo): el precio por persona sale de que la cantidad
SON entradas (§4.2), «solo para ciertos productos con límites» es el pivote de siempre, y dos entradas
del mismo pedido tienen su hora extra independiente porque los complementos cuelgan de cada línea.

## 8. Lo EJECUTADO (2026-09-03, `#410`/`#411` — commits `d36c59a6` · `08c124be` · `f2c23a6b` · `18c74c7e` + docs)

**Las cuatro tandas están EN EL ÁRBOL**, por el orden de D1, con la suite en verde en cada hito
(4.091 → 4.100 → 4.105 casos), **24/24 mutaciones mordiendo** (12 del núcleo + 8 del editor + 4 de
la oferta, cada tanda con CONTROL previo en verde y veredicto por código de salida) y **los SIETE
escenarios de `purchase:verify-oversell` + `redsys:verify-concurrency` en verde sobre InnoDB real**.

### 8.1 · El núcleo (commit `08c124be`)

- **Migración** `2026_09_03_100000` (`occupies_after_parent`, bool default false) + guard en
  `TicketType::booted()` (solo addon · duración > 0 · `seats_per_unit >= 1`, **en las dos
  direcciones** — la de encender el interruptor con un enganche prohibido incluida, la lección de
  `#324`) + guard del PIVOTE en `ProductAddon::booted()` (ni `per_guest`, ni obligatorio, ni pack;
  corre en `attach`/`updateExistingPivot` porque la relación usa `->using()`, y eso está fijado con
  caso).
- **§4.9 destapiado**: sección propia del catálogo para el complemento ocupante (interruptor +
  «cuánto ocupa»); `normalizeByType()` conserva la duración del ocupante; **`EditCatalog` gana el
  simétrico** (`normalizeAddonOccupancyOnEdit()`, regla 12) — y con ventas hechas ni el interruptor
  se apaga ni la duración se mueve (tampoco la del PADRE: el borde 9 quedó cerrado con el candado de
  la zona, form + defensa server con rastro).
- **`AddonOccupancy`** (regla del borde 8 + cinturón §4.1 + plazas) y **`CartOccupants`** (la
  derivación ÚNICA de D1, con etiquetas `(línea, complemento)` y exclusión exacta) — los dos en el
  `CRITICAL_RE` y en `CriticalPathGateTest`, junto a `AddonResolver` (lleva el tope por SUMA y las
  plazas de las filas ocupantes).
- **`OrderCreator`**: valida la hija BAJO el lock (la franja sale de las filas BLOQUEADAS, no de
  una consulta), con los HERMANOS dentro del recuento. **`AvailabilityReader`** delega en
  `CartOccupants` → la oferta y el cobro cuentan IGUAL.
- **El verificador `extra-hour` se vio FALLAR** con la validación desactivada — **5 asientos
  escritos en una franja de 1, SIN carrera** — y pasar con ella (1 ganador de 8). La condición de D1,
  cumplida y medida.
- `TicketIssuer` gana `whereNull('parent_item_id')` (§4.11, fijado con caso) y el contrato de la API
  los dos códigos nuevos (`line_addon_occupancy` · `line_addon_over_quantity`), con mensajes en
  es/en/fr y el mapa del cajón (`pay.js`).

### 8.2 · El editor del panel (commit `f2c23a6b`)

`changeSlot()` y `edit()` mueven a la FAMILIA entera o no mueven nada (`landOccupyingFamily()`, bajo
el mismo lock, con los hermanos como provisionales); la huella excluida es la familiar
(`excludeItemId` admite varios ids — el cambio de firma del borde 1); bajar el padre por debajo de
la suma se rechaza (`addon_stay_exceeds_quantity`); subir la hija recalcula `seats`; el `create` de
«Gestionar → Complementos» nace con franja y plazas; y `ItemRescheduleOffer` esconde las horas donde
la hija no cabe (con su CONTROL de que sin hijas no esconde nada). Claves de bloqueo nuevas en
es/zh_CN.

### 8.3 · La oferta del embudo (commit `18c74c7e`)

`POST /catalog/products/{id}/addons` usa la hora de la línea (el cajón YA la mandaba y **re-resuelve
al cambiar de hora** — `selectTime → refreshAddons`): un ocupante que no aterriza **no se ofrece** y
la `selection` lo suelta; el que aterriza sale capado (`max_quantity`/`can_increase`) por sus plazas
y por la SUMA. **Cero cambios de cliente.** ⚠️ **A sabiendas, y dicho en tres sitios** (reader, yaml,
aquí): la cota se calcula **sin la cesta** (el endpoint no la recibe) — un pelín optimista cuando la
propia cesta ocupa la franja siguiente, y ese borde lo cierra el checkout con su mensaje. ⚠️ **El
alta manual del panel tampoco decora su UI de complementos** (usa `viewModel` directo): el operador
que elija una hora extra imposible recibe el rechazo claro del dominio al guardar — con el cliente
delante, suficiente; si estorba, la palanca es hacer pasar esa pantalla por `AddonOffer`.

### 8.4 · Verificación empírica (el §6, caso a caso)

§6·1 verificador visto fallar/pasar · §6·2 control del neutro (aforo IDÉNTICO) · §6·3 último tramo
· §6·5 editor (9 casos) · §6·6 hermanos sin carrera · §6·7 la SUMA (6 de 4, con el borde exacto en
verde) · §6·8 cinturón (la fila por `Query\Builder::update()` ni se ofrece ni se vende ni ocupa
hasta el cierre, con control de que sana SÍ se ofrece) · §6·9 paridad oferta/cobro · §6·10 alta Y
edición del panel conservan el dato · §6·11 tickets solo de admisiones · **§6·4 en NAVEGADOR real**
(sonda `/root/e2e/extra-hour-probe.js`, 4/4: la fila aparece a las 10:00, desaparece a las 20:00 —
la última del día real—, reaparece al volver, y el stepper suma; siembra idempotente y limpiada,
capturas en `/root/e2e/extra-hour-capturas/`). §6·12: por D3, las superficies ya dicen lo que hay
que leer (la hoja imprime ventana+duración y `N × Hora extra`) — nada que construir, nada construido.

### 8.5 · El OJO del owner, primera pasada (2026-09-03, con la demo delante)

El producto REAL está dado de alta en localhost (guion idempotente `hora-extra-demo.php`, en el
`storage/app/e2e` local — gitignorado, como los puentes de las sondas):
«Hora extra» a 3,00 € (el salto exacto 2h→3h del catálogo) enganchado a «Jump · 2 horas», el cliente
`demo-hora-extra@jumpweb.test` con **6 pedidos** que cubren A–F de §4.8 (pagados por
`ManualOrderFulfiller`, la pendiente retiene aforo) y el intento G rechazado sobre datos reales.
El owner lo miró y pidió DOS ajustes, hechos el mismo día:

1. **El Resumen del día lista los COMPLEMENTOS de cada reserva** («+ 1 × Hora extra», bajo el
   producto): la hora extra es inventario operativo y la hoja con la que se abre el día no la decía.
   Solo los VIVOS — un complemento cancelado no es operativa (la hoja individual sí lo enseña
   tachado, porque allí cuadra dinero). ⚠️ La hoja individual (`ReservationSlip`) YA los listaba:
   el hueco era solo del resumen. ⚠️ La reserva PENDIENTE no sale en el resumen y no es un hueco:
   su regla canónica es «pedido pagado» (`paidScheduledPrincipal`, la misma del calendario).
2. **La nota de la fila dice que la cantidad son ENTRADAS**: sin elegir, «3,00 € por entrada que se
   queda»; elegida, **«Para N entrada/s que se queda/n · 3,00 €»** — la lectura que él pidió («1 hora
   extra para 2 entradas»). Compuesta en `AddonResolver::viewModel()` (fuente única: cajón, API y
   alta manual del panel la heredan sin una línea de cliente) y re-computada en cada clic.
   ⚠️⚠️ **Dos guardas del cajón dispararon y las dos tenían razón**: el grupo `tickets` viaja ENTERO
   al SPA y su `i18n.js` solo resuelve `singular|plural` — nada de sintaxis de rangos (`{1}…|[2,*]…`)
   en ese grupo—, y una clave con dos formas o la resuelve el cliente con `tc()` o entra en
   `PLURALISED_BY_SERVER` **con la comprobación de que el cliente no la nombra** (la exención se
   demuestra, no se concede).

Evidencia: 4/4 mutaciones nuevas muerden (28/28 en la feature) · suite 4.107 · la hoja real del
día demo verificada renderizando el blade (6 menciones de la hora extra, la cancelada fuera) · la
nota medida en navegador («Hora extra — Para 1 entrada que se queda · 3,00 €»).

### 8.6 · Trampas pagadas en la ejecución (para el siguiente)

- **Mi propia aritmética de test estaba mal, no el código**: esperé que un padre de 60 min a las
  10:00 restara plazas a las 11:00 — un tramo de 60 marca SOLO su franja. Los valores «fallidos»
  (49/48/50) eran los correctos; se corrigieron las aserciones, no el producto.
- **`AvailabilityReaderTest::test_a_pack_in_the_cart_does_not_eat_the_seats_of_an_entry` cambió de
  premisa y se reescribió** (el precedente de `#324`): afirmaba una independencia de pools que lo
  ALMACENADO desmiente — `occupancyMap` cuenta las líneas de pack (lo tenía medido el propio
  `evaluateMixed` del verificador, `#148`) y el cobro provisional también las contaba; solo la
  oferta no. La unificación resolvió hacia lo almacenado, con control de la premisa nueva.
- **Editar `pay.js` puso 35 casos del contrato de árbol en rojo**: el bundle SSR estaba RANCIO y la
  guarda lo dice por su nombre — `npm run build:ssr` y en verde. No era un defecto: era el
  instrumento protegiéndose de comparar código viejo.
- **La sonda de navegador esperó un `.cal` que ya no existe**: desde `#237`/`#239` la fecha es una
  TIRA (`daystrip`) con el calendario plegado — las sondas anteriores a esa tanda envejecieron y
  quien copie una de plantilla hereda el selector muerto. ⚠️ **Y volvió a pasar**: en el PANEL, `#464`
  retiró esa misma tira y el calendario plegado (hoy es `.cmo-cal`), así que la lección se cumple dos
  veces — *el selector de una sonda envejece en silencio*.

  ▶ **`#464` toca este documento por el fondo, y de paso corrige una CITA de otro**:
  `CreateManualOrderPage::timeMap()` ya pasa los ocupantes provisionales de su cesta, derivados por
  `CartOccupants::forCart()` —la misma derivación única de §7·D1, que ahora incluye también el CUPO de
  packs—. ⚠️⚠️ **Eso NO era lo que decía §8.3**, aunque `asistente-crear-pedido.md` §2.3 lo citara
  así: la deuda declarada ahí es la del ENDPOINT de complementos del embudo (`POST
  /catalog/products/{id}/addons`, que no recibe la cesta) y la UI de complementos del alta manual —
  **las dos siguen en pie**. Que el panel no le pasara la cesta a `offerableTimes()` no lo había
  declarado nadie: se midió en `#462`. *Una deuda parecida en la misma familia no es la misma
  deuda.*
- **El escenario nuevo del verificador necesita limpiar el complemento APARTE**: su zona es nula a
  propósito y el barrido por zona del `cleanup()` no lo ve — quedaría en la BD de desarrollo tras
  cada ejecución.

---

## 9 · ESTUDIO — mover la fecha y los complementos: lo que hoy NO pasa

> **Encargo del owner (2026-09-04)**: *«Aquí entra tema de dinero, y hay que respetar la misma
> lógica que hicimos con las reservas mixtas y los cambios de fecha. Si el cliente cambia de fecha a
> sabiendas de las condiciones de esa fecha hay que aplicar las condiciones de esa fecha, es algo
> voluntario; si una fecha no permite hora extra, la hora extra se le quita y se aplica su devolución
> como todo el sistema aplica a este tipo de cambios. Hay que estudiarlo a fondo.»*
>
> **Esto es un ESTUDIO, no una obra**: nada de lo de aquí está implementado. Termina en decisiones.

### 9.1 · El hueco, en una frase

Al mover la fecha de una reserva, **el padre se re-tarifica con el catálogo del día nuevo** (`PAY-18`)
y **el suplemento de fiesta mixta también** (`cumple-mixto.md` §12, con su porqué escrito: *«mover el
día es mover el importe… es un cambio del HECHO, no de la configuración, y por eso sí reconcilia»*).
**Los COMPLEMENTOS no**: conservan el precio del día viejo, y si el producto no se vende ese día,
sobreviven igual.

O sea que la hora extra no estrena un problema: **es incoherente con una regla que el sistema ya
tiene escrita para sus dos vecinos.**

### 9.2 · Los cuatro casos, MEDIDOS sobre datos reales (2026-09-04)

| caso | hoy | ¿correcto? |
|---|---|---|
| **A** · el día nuevo **no vende** ese complemento | la edición pasa y la hija **sobrevive a 8,00 €** | ❌ el encargo pide retirarla con su devolución |
| **B** · el día nuevo lo vende **a otro precio** | conserva **8,00 €** donde ese día vale **3,00 €**; total 52,00 € cuando serían 39,00 € | ❌ la doctrina de §12 de mixtos dice re-tarificar |
| **C** · otro día del **mismo** tipo de tarifa | la familia se mueve entera (padre 11:00 → hija 13:00), precio intacto | ✓ |
| **D** · destino **sin franja siguiente** | **bloqueado con el motivo CORRECTO**: `addon_occupancy_at_destination` | ✓ **sin defecto** — ver la corrección de abajo |

> ⚠️⚠️ **CORRECCIÓN, y va delante del texto que corrige.** La primera versión de este estudio afirmó
> que el caso D bloqueaba con un motivo engañoso (`insufficient_capacity_at_save`, «falta aforo»,
> cuando lo que falta es la franja). **Era FALSO, y el error era del experimento**: se probó moviendo
> a la **última** franja del día, donde el padre —120 min desde las 20:00— tampoco cabe, así que
> falla el aforo del PADRE antes de llegar a mirar a la hija. Ese motivo es correcto ahí.
>
> Medido de nuevo separando los dos casos, con el padre cabiendo y la hija no (destino 19:00, el
> padre acaba a las 21:00 y no hay franja a esa hora):
>
> | destino | qué falla | motivo devuelto |
> |---|---|---|
> | 19:00 | el padre cabe, **la hija no tiene franja** | `addon_occupancy_at_destination` ✓ |
> | 20:00 | **el padre no cabe** (acabaría a las 22:00) | `insufficient_capacity_at_save` ✓ |
>
> Y el texto que ve el operador ya es exacto: *«la hora extra de esta reserva no cabe detrás del
> destino: la franja siguiente no existe, está cerrada o está completa»*. **`landOccupyingFamily`
> distingue los dos casos y propaga su motivo propio: aquí no hay nada que arreglar.**
>
> *La lección es de método y es la de siempre en este repo: un experimento que mezcla dos causas no
> prueba cuál de las dos actuó. El caso «sin franja siguiente» hay que montarlo donde el padre SÍ
> quepa, o se está midiendo otra cosa.*

### 9.3 · ⚠️ Lo que ya existe y NO hay que construir

**La retirada cuadra sola en el libro.** Medido sobre un pedido pagado de verdad, cancelando la línea
de la hora extra:

| | total | pagado | saldo | estado | coherente |
|---|---|---|---|---|---|
| antes | 52,00 | 52,00 | 0,00 | `settled` | ✓ |
| tras retirar la hija | **44,00** | 52,00 | **−8,00** | **`refund_at_park`** | ✓ |

Es decir: el saldo sale como **«a devolver en el parque»** y las identidades del libro siguen
cerrando, sin escribir un solo hecho a mano. Eso es exactamente *«se le quita y se aplica su
devolución como todo el sistema aplica a este tipo de cambios»* — y encaja con `#244` (nada se
devuelve online post-reserva) sin tocar `PAY-16`/`PAY-17`.

▶ También existe ya: **la familia se mueve entera** (`landOccupyingFamily`, `#410`) y **el bloqueo
cuando la hija no cabe** (caso D). Lo que falta es **el DISPARADOR**, no la maquinaria.

### 9.4 · ⚠️⚠️ La trampa que este estudio destapó, y cambia el alcance

`complementos-post-reserva.md` §1.3 sostiene su seguridad en que *«quitar una línea es NEUTRO en
dinero»*, porque una línea nacida DESPUÉS del pedido lleva un ajuste `edit` de su importe exacto y su
`birthValue()` vale 0.

**Una hora extra comprada CON el pedido NO cumple eso.** Medido: `birthValue = 8,00 €` y
`onlineAtBirth = 8,00 €` — **nació con el pedido y aportó dinero online**. Retirarla **debe dinero**,
y por eso produce el `refund_at_park` de arriba en vez de ser neutra.

*No es un problema: es la diferencia entre los dos mecanismos, y hay que tenerla delante al escribir
el disparador. Copiar la lógica del post-form aquí sería exactamente el error.*

### 9.5 · Lo que hay que decidir antes de construir (owner)

1. **¿Retirada automática o confirmación del operador?** El encargo dice «se le quita». Pero el
   operador está moviendo una fecha, no gestionando extras: retirar en silencio una línea de 8,00 €
   con devolución es un efecto que probablemente quiera ver antes de confirmar.
2. **¿Entra también el caso B (mismo producto, otro precio)?** La doctrina de mixtos dice que sí.
   Hoy no aplica —los 12 complementos tienen precio plano— pero la regla se escribe una vez.
3. **❗ ¿Aplica a TODOS los complementos o solo a los OCUPANTES?** Ésta es la que decide el tamaño:
   hoy los combos y los cubos **también** conservan su precio al mover la fecha. Acotarlo a la hora
   extra deja dos conductas distintas para el mismo tipo de fila; extenderlo a todos toca el dinero
   de **todos** los complementos y necesita su propio `VERIFY_CONC`.
4. ~~**El texto del caso D**~~ — **RETIRADA: no había defecto** (ver la corrección de §9.2). El
   motivo propio existe, se propaga y su texto es exacto.

### 9.6 · Las DOS escrituras, y no son simétricas (MEDIDO)

El libro no admite que se le cambie un importe sin contarle por qué. Medido sobre un pedido pagado
de verdad, con las identidades de `OrderBook` como juez:

| gesto | qué se escribe | total | saldo | estado | ¿cierra? |
|---|---|---|---|---|---|
| **retirar** la línea | `markCancelled()` y **NINGÚN** `recordEdit` | 52,00 → **44,00** | **−8,00** | `refund_at_park` | **sí ✓** |
| **re-tarificar** (8,00 → 3,00) | `unit_price` **+ `recordEdit(−Δ)`** | 52,00 → **47,00** | **−5,00** | `refund_at_park` | **sí ✓** |
| re-tarificar **sin** el hecho | solo `unit_price` | 52,00 → 47,00 | 0,00 | **`under_review`** | **NO ❌** |

⚠️⚠️ **La tercera fila es el modo de fallo, y es mudo**: el importe cambia, nada lanza, y el pedido
entero pasa a «en revisión» — o sea **el cliente se queda sin su desglose** (`#132`) por haber movido
una fecha.

▶ Es exactamente la asimetría que `complementos-post-reserva.md` §4.5.1 documenta para el post-form,
llegando desde el otro lado: **retirar no lleva hecho porque el libro ya emite su `−fila`; mover un
importe SÍ lo lleva, o `nac` se desplaza**. Que las dos tandas hayan llegado a la misma regla por
caminos distintos es la señal de que es la regla del subsistema, no un detalle de ninguna de las dos.

### 9.7 · Plan de obra propuesto (`[DECIDIDO owner, 2026-09-04]` las tres de §9.5)

> ⚠️⚠️ **CORREGIDO POR §9.8, y esa corrección va antes que este texto.** La revisión adversarial
> encontró que este plan (a) valida aterrizajes de hijas que iba a retirar, (b) retiraría los
> portadores de fiesta mixta en cada cambio de fecha y (c) pone en el post-commit una decisión que
> gobierna lo que hay que validar dentro del lock. **Lee §9.8 antes de construir desde aquí.**

**Alcance: TODOS los complementos**, no solo los ocupantes — *«no hay otro complemento condicionado
por la fecha hoy, pero lo hacemos para tenerlo hecho y tener una base profesional»*.
⚠️ **Y eso lo hace medible**: los 12 complementos del catálogo tienen precio PLANO, así que la
re-tarificación es **no-op para once de ellos** y la retirada solo alcanza hoy a la hora extra. El
criterio de éxito es que **ningún pedido existente cambie de importe**.

| pieza | qué hace |
|---|---|
| **`AddonDateReconciler::preview()`** | dado un ítem y una fecha destino, qué pasaría con cada hija: `keep` · `reprice(Δ)` · `withdraw(importe)`. **Lectura pura**, sin escribir |
| **`AddonDateReconciler::reconcile()`** | lo aplica, con las dos escrituras de §9.6 |
| **el modal del panel** | usa el `preview()` para AVISAR antes de confirmar (`[DECIDIDO owner]`): «esta reserva lleva una hora extra de 8,00 € que no se vende ese día: al mover se retirará y quedará a devolver en el parque» |
| **el post-commit del editor** | llama a `reconcile()` **junto al de fiesta mixta**, misma frontera transaccional (§4.3): fuera del lock de zona/día, en su propia transacción corta |

⚠️ **El `preview()` y el `reconcile()` tienen que dar lo MISMO**, o el operador confirma una cosa y se
aplica otra: son una sola derivación con dos caras, como `SlotOffer`/`OrderCreator` en la oferta y el
cobro. Guarda propia para esa paridad.

⚠️ **Entra en el `CRITICAL_RE`** (toca dinero de líneas vendidas) → `VERIFY_CONC=1` y su escenario:
dos operadores moviendo la misma reserva a la vez no pueden escribir dos veces el mismo ajuste.

⚠️ **Lo que NO cambia**: `#244` sigue en pie — nada se devuelve online. El saldo negativo se liquida
**en el parque**, que es lo que el libro ya hace solo (§9.3).

### 9.8 · REVISIÓN ADVERSARIAL del plan (2026-09-04) — tres cosas que el plan de §9.7 hacía MAL

> Encargo del owner: *«lanza un adversarial sobre el plan»*. Seis lentes contra el diseño de §9.7,
> **con medición en cada hallazgo**. Resultado: **tres correcciones al plan** (dos de ellas
> reproducidas), **un límite aceptado** y **tres confirmaciones** — que también son medida.

#### ❌ H1 · El ORDEN estaba invertido: bloquea el movimiento por una hija que iba a retirarse

El plan validaba el aterrizaje de TODAS las hijas y decidía después cuáles sobreviven. **Reproducido**:
una reserva del sábado con hora extra, movida a un martes —día en que la hora extra **no se vende**—
con la franja de aterrizaje sin plazas:

```
veredicto: ok=false · motivo='addon_occupancy_at_destination'
```

El operador **no puede mover la reserva por un aforo que nadie va a consumir**, porque esa hija se
iba a retirar de todos modos. ▶ **Corrección**: la supervivencia de cada hija se decide **ANTES** de
validar aterrizajes, y `landOccupyingFamily` recibe solo a las supervivientes.

#### ❌ H2 · La regla retiraría los PORTADORES de fiesta mixta, en CADA cambio de fecha

`«sin precio ese día ⇒ retirar»` es demasiado ancha. **Medido**: los dos portadores
(`Suplemento fiesta mixta`, `Descuento fiesta mixta`) tienen **cero precios en catálogo**, así que
`priceCents()` devuelve `null` **siempre, todos los días**.

| línea hija | precio un martes | qué haría el plan |
|---|---|---|
| Suplemento fiesta mixta | `NULL` | **retirarla** ❌ |
| Descuento fiesta mixta | `NULL` | **retirarla** ❌ |
| Hora extra · KIDS / JUMP | `NULL` | retirarla ✓ (es el objetivo) |
| los otros 10 complementos | tienen precio | nada ✓ |

⚠️⚠️ Y no es solo que sobre: **`MixedPartySurcharge::reconcile()` corre en el MISMO post-commit, justo
después**. Serían dos servicios peleando por la misma línea en una petición, con el dinero moviéndose
dos veces. El editor ya tiene escrito por qué esa línea es intocable: *«no es un complemento que el
operador gobierne: es el reflejo de una edad que declaró el cliente»*.
▶ **Corrección**: exclusión explícita de los portadores, con el predicado que ya existe
(`ProductAddon::isMixedPartyCarrier()`, la regla 7 de `postFormProblem`).

#### ❌ H3 · La frontera transaccional: el plan lo ponía TODO en el post-commit

§9.7 decía «el post-commit llama a `reconcile()`». Con H1 eso es imposible: **la decisión gobierna qué
se valida**, así que tiene que estar dentro del lock. Y la doctrina del editor (§4.3) ya fija el
reparto: *«cada paso abre su PROPIA transacción corta… la REST de un reembolso no puede ir dentro de
la txn de aforo, y por eso esta fase va después»*.

▶ **Corrección — el reconciliador se PARTE en dos**, que es como el editor ya trata todo lo demás:

| fase | qué va | dónde |
|---|---|---|
| decidir + mutar | qué hijas sobreviven · validar aterrizaje de ÉSAS · mover · `markCancelled` de las que caen | **DENTRO** del lock de zona/día |
| dinero | el `recordEdit(±Δ)` de las re-tarificadas | **POST-COMMIT**, transacción corta propia |

⚠️ La retirada cae del lado de la mutación **porque no lleva hecho** (§9.6): es coherente, no una
excepción.

#### ⚠️ L1 · Límite aceptado: el `preview()` corre SIN lock

El aviso al operador se calcula al abrir el modal; la decisión real, dentro del lock. Entre las dos
puede cambiar el catálogo, y entonces se aplicaría algo distinto de lo confirmado. La ventana es de
segundos y hace falta que alguien toque precios justo entonces.
▶ **No se cierra con más locks: se cierra DICIENDO lo que se hizo.** El desenlace y el audit reportan
lo APLICADO, no lo previsto — que es lo que el editor ya hace con `item_edit_context`.

#### ✓ Lo que la revisión CONFIRMÓ (resultados negativos, que también son medida)

1. **Las dos escrituras de §9.6 cierran también con SEÑAL.** Se midieron sobre una entrada sin señal;
   repetidas sobre un pack con señal de 50,00 € —donde el complemento tiene `onlineAtBirth = 0` porque
   se paga en el parque— las dos siguen cerrando (`pay_at_park`, coherente ✓). La regla no depende del
   régimen de cobro.
2. **El criterio de no-op se cumple en PRODUCCIÓN.** Medido sobre las **9** líneas hijas vivas reales:
   **0** difieren del catálogo y **0** se quedan sin precio. En local solo hay 1, así que la muestra
   que vale es la de producción.
3. **El caso D no tenía defecto** (ya corregido en §9.2): el motivo específico existe y se propaga.


### 9.9 · ✅ EJECUTADO (`DECISIONES #417`, 2026-09-04)

`Booking\Services\AddonDateReconciler` (+ `AddonDatePlan` · `AddonDateChange`), con el plan de §9.7
**corregido por §9.8**: `plan()` es lectura pura y gobierna qué aterrizajes se validan;
`applyMutations()` va DENTRO del lock; `applyMoney()` POST-COMMIT. Integrado en los **dos** caminos
públicos del editor (`changeSlot()` y `edit()`), con el aviso previo al operador en el calendario del
modal.

| lo pedido | dónde acabó |
|---|---|
| retirar lo que ese día no se vende | `applyMutations()`, sin hecho — el libro emite su `−fila` |
| su devolución | el saldo sale `refund_at_park`: se liquida en el parque (`#244`) |
| re-tarificar lo que cambia de precio | `unit_price` + `recordEdit(±Δ)` post-commit |
| aviso antes de confirmar | `partials/addon-date-notice.blade.php`, con el importe |

**11 casos · 7/7 mutaciones · `VERIFY_CONC` completo · verificado en el panel real.** Las cuatro
trampas de instrumento que costó están en `DECISIONES #417`; las tres correcciones al plan, en §9.8.

---

# 10 · LA HORA EXTRA EN UN PACK — reapertura de D2 ⬜ **DISEÑO, pendiente del owner** (`#421`, 2026-09-06)

> ❗❗❗ **ESTA SECCIÓN CORRIGE A §7·D2 Y VA ANTES QUE AQUEL TEXTO.** D2 cerró «solo entradas» con un
> argumento que sigue siendo **cierto** (un complemento colgado de un pack es invisible para
> `max_guests_per_slot`), pero su conclusión —«esto no se hace»— la reabre el owner el 2026-09-06:
> *«tenemos que añadir la hora extra también viable para producto tipo pack»*. Lo que sigue es el
> diseño, con el hueco reproducido y el coste medido. **Nada de esto está implementado.**

## 10.1 El hueco, REPRODUCIDO — y son DOS, no uno

Con las tres guardas neutralizadas una a una se llegó a vender el enganche prohibido. Fiesta de **20
niños de 15:00 a 17:00** con **1 hora extra**, sobre la rejilla real:

```
línea hija creada:  qty=1  seats=1  franja=17:00        ← (b)
cupo de sala 15:00 → fiestas=1 ninos=20
cupo de sala 17:00 → fiestas=0 ninos=0                  ← (a)  la sala está llena y nadie lo ve
aforo de asientos de la zona 17:00 → 1                  ← (b)  hay 20 personas dentro
freeGuestSlots(17:00) = 60 de 60
→ ACEPTA otra fiesta de 20 a las 17:00 (y admitiría 60 niños más)
```

▶ **(a) es el que D2 anunció**: `PackAvailability::occupancyMaps()` filtra por `type = pack`, así que
la hija —que es un `addon`— no cuenta **ni como fiesta ni como invitados**. Sobreventa de la sala.

▶ **(b) no estaba escrito y es igual de grave**: las plazas de la hija son `cantidad × seats_per_unit`
—**1**—, no los invitados de la fiesta. Aunque se arreglara (a) contando la hija en el pool de packs,
seguiría contando **una persona donde hay veinte**. *Un complemento ocupante mide «cuántos se quedan»,
y en una fiesta lo que se queda no es una cantidad que el cliente elige: es la fiesta entera.*

⚠️ Las **tres** guardas funcionan y ninguna sobra: `ProductAddon::booted()` (el enganche),
`TicketType::booted()` (el interruptor, en las dos direcciones) y `AddonResolver::resolve()` (la
autoridad del cobro). La del resolutor es la que corta la venta de verdad — comprobado: con las dos
primeras saltadas, el checkout todavía responde `unavailable`.

## 10.2 La pregunta que decide el diseño (y es de producto)

**¿Qué se vende exactamente cuando se vende «una hora más» en un cumpleaños?** Hay tres cosas
distintas detrás de la misma frase, y cada una es un mecanismo distinto:

| | qué pasa físicamente | qué debe consumir |
|---|---|---|
| **(1) La fiesta dura más** | la sala sigue ocupada por ese grupo | **cupo de sala** (1 fiesta + sus invitados) durante una hora más |
| **(2) Algunos invitados se quedan a saltar** | la sala se libera; los niños pasan a la zona de salto | plazas de **JUMP/KIDS**, no de sala |
| **(3) La fiesta entera se queda saltando** | la sala se libera; el grupo entero pasa a la zona de salto | plazas de JUMP/KIDS por el grupo entero |

✅ **`[DECIDIDO owner, 2026-09-06]`: es (1) — la fiesta sigue en su sala.** Coincide con la
aclaración que él mismo dio en D2 (*«el complemento de hora extra es para todos los invitados»*), y es
lo que este diseño resuelve. **(2) y (3) son otra feature**: hoy
`AddonOccupancy::childSlotAmong()` exige que la franja de la hija sea de **la misma zona del padre**,
así que ocupar otra zona no existe como mecanismo (`#151` decidió además que los pools son POR ZONA:
un cumpleaños no resta plazas de JUMP hoy). Si lo que se quiere es (2) o (3), **esto no vale y hay que
diseñar otra cosa**.

## 10.3 El diseño: la hora extra **EXTIENDE la ventana de la fiesta**, no añade un ocupante

▶ **El principio, y es la síntesis de D2 en vez de su contradicción:** D2 tenía razón en que *«si en un
pack se quedan todos, eso ya no es un complemento que ocupa — es que la fiesta DURA MÁS»*. Lo que se
reabre no es el análisis: es la conclusión de que entonces no puede venderse como complemento. **Se
vende como complemento (que es lo que el cliente entiende) y se modela como duración (que es lo que
el aforo necesita).**

### 10.3.1 El eje: un interruptor hermano, no un modo del que ya hay

`ticket_types.extends_parent_stay` (bool, default false), **excluyente** con
`occupies_after_parent`. Los dos describen «prolongar la estancia», pero son productos distintos
porque **su unidad es distinta**:

| | `occupies_after_parent` (existe) | `extends_parent_stay` (nuevo) |
|---|---|---|
| cuelga de | entradas — **nunca** de un pack | packs — **solo** de un pack |
| qué crea | línea hija **con franja propia y plazas** | línea hija **sin franja y sin plazas** |
| la cantidad son | **personas** que se quedan | **bloques de tiempo** (`duration_min` cada uno) |
| el precio es por | persona | bloque |
| qué consume | plazas de la franja siguiente | la ventana de la fiesta, **alargada** |

⚠️⚠️ **Por eso no es «el mismo complemento con dos comportamientos».** Reinterpretar «Hora extra ·
KIDS» según el tipo del padre haría que **su precio cambiara de unidad sin que nada lo diga** —€/persona
colgado de una entrada, €/hora colgado de un pack— y `prices` es una tabla sola. Son **dos productos de
catálogo**, cada uno enganchado donde le toca. Las guardas quedan simétricas y comprobables:
ocupante ⇒ jamás de un pack · extensor ⇒ **solo** de un pack · nunca los dos a la vez · ni `per_guest`
ni obligatorio (mismos motivos de §4.4·5) · `duration_min > 0` (el cinturón de §4.1).

### 10.3.2 La duración con la que una reserva OCUPA es un HECHO de la línea

**`order_items.extra_minutes`** (unsigned, default 0), escrito por el mismo sitio que escribe `seats` y
con la misma naturaleza: `seats` **ya** es un derivado materializado en la línea (`cantidad ×
seats_per_unit`) que los mapas de ocupación leen sin recalcular. `extra_minutes` es su hermano, y la
duración efectiva de la línea es `ticket_types.duration_min + order_items.extra_minutes`.

▶ **Por qué materializar y no derivar**, con el número delante: los dos mapas de ocupación son **SQL
puro por rendimiento** (`#465` los dejó en 18 y 30 ms), y una hija extensora **no tiene `slot_id`**, así
que el `join slots on slots.id = order_items.slot_id` no la trae. Derivarla exigiría **una consulta más
por llamada**, y `offerableTimes()` llama a `availableGuestsFor()` **una vez por franja** —con la
rejilla de `#420` son 18 el sábado—. Materializado, el cambio en las dos consultas es un `+` en el
`SELECT`: **coste cero**.

⚠️ El riesgo del dato duplicado se acota como se acota el de `seats`: **un solo escritor** (el mismo
punto que hoy compone las filas de complemento) y **una guarda de identidad**
(`extra_minutes == Σ hijas extensoras × su duración`). El precedente en contra —`slots.seats_taken`,
que se desincronizó y hoy está muerta— **no aplica**: aquélla la movían terceros (cualquier compra de
cualquiera), y ésta sólo cambia cuando cambia su propia línea.

⚠️ `duration_min = null` (ilimitada) **no se puede extender**: lo ilimitado ya llega al cierre. Guarda
en el resolutor.

### 10.3.3 Dónde entra, exactamente

| Pieza | Qué cambia |
|---|---|
| `PackAvailability::occupancyMaps()` | la ventana del padre suma `extra_minutes` → **1 fiesta, los mismos invitados, más rato**. Ni doble conteo ni fiesta fantasma |
| `PackAvailability::spannedSlots()` / `availableGuestsFor()` | la fiesta debe **caber entera** con su extensión (rejilla + cupo) |
| `SlotAvailability::occupancyMap()` | idem para el aforo de asientos de la zona: el padre ocupa hasta su fin real |
| `CartOccupants` | los provisionales de la cesta emiten la ventana **ya extendida** (la derivación sigue siendo ÚNICA) |
| `AddonResolver::resolve()` | el extensor no lleva `slot_id` ni `seats`; aporta `extra_minutes`. Tope por **suma**, como el ocupante |
| `OrderCreator` | valida **bajo el lock** que la ventana extendida cabe y tiene cupo |
| `AddonOfferReader` | un extensor se ofrece **solo si la ventana extendida cabe** — el patrón que ya usa el ocupante con su franja hija |
| `OrderItemEditor` / `ItemRescheduleOffer` / `AddonDateReconciler` | mover día/hora o cambiar cantidad recalcula la ventana; mover el día ya retira lo que ese día no se vende |
| `OrderItem::displayTimeWindow()` | suma `extra_minutes` → «15:00 – 18:00». **Arregla de paso** que hoy la hoja de sala diría 15:00–17:00 con la hora extra vendida |

❗❗ **`isFinishedInPractice()` es el borde que hay que decidir, no descubrir**: lee `slot.end_time`,
que con franjas de 60 ya declara terminada una fiesta de 2 h **una hora antes** (ficha viva en
`DEUDA.md`, y `complementos-post-reserva.md` §4.9 midió que además parsea hora de pared como UTC). Con
la extensión el desfase crece a dos horas o más, y de ese predicado cuelgan el post-form en solo
lectura, el cierre de los extras y la ventana de dinero del suplemento mixto. **La extensión no crea el
defecto, lo agranda**: o se arregla con la duración efectiva en la misma tanda, o se dice que no.

## 10.4 Lo que cuesta en CAPACIDAD — y lo que eso significa para el precio

Medido con el llenado voraz sobre la rejilla de `#420`, modelando la hora extra como lo que es (la
fiesta dura más):

| duración de la fiesta | horas ofrecidas (mar/sáb) | capacidad (mar) | capacidad (sáb) |
|---|---|---|---|
| 2 h — hoy | 7 / 18 | 6 fiestas · 120 niños | 15 fiestas · 300 niños |
| 3 h — todas con 1 extra | 5 / 16 | **3 · 60** | **9 · 180** |
| 4 h — todas con 2 extras | 3 / 14 | 3 · 60 | 6 · 120 |

⚠️⚠️ **La hora extra no es margen: canibaliza sitio de otra fiesta.** En el caso extremo el sábado
pierde **6 fiestas de 15** y el martes **la mitad**. Es el dato que debería fijar el precio: una hora
extra que desplaza media fiesta tiene que costar más que un complemento simbólico. **No es una objeción
al diseño —el owner puede quererlo igual—, es un número que debe estar sobre la mesa al ponerle precio.**

## 10.5 Los bordes, con su respuesta

1. **El cliente elige la hora ANTES que los extras** (los dos embudos: web y asistente del panel). Una
   fiesta de las 18:30 puede admitir 2 h y no 3. ▶ El extensor **no se ofrece** si su ventana no cabe
   —mismo patrón que el ocupante, que ya se esconde cuando su franja siguiente no existe— y el checkout
   lo re-valida bajo lock.
2. **El tope**: `max_qty` del pivote (cuántos bloques), la **rejilla** (el cierre del día) y el **cupo**.
   Como en `#410`, el tope se comprueba por **SUMA** de las filas extensoras, no fila a fila.
3. **`prep_after_min`** se apila **después** de la extensión, no en medio: la limpieza empieza cuando la
   fiesta acaba de verdad. Hoy vale 0 (`[DECIDIDO owner, 2026-09-06]`, `#420`), así que no muerde.
4. **Mover el día** con hora extra: `AddonDateReconciler` (`#417`) ya retira lo que no se vende ese día
   y re-tarifica lo que cambia de precio; la ventana se recalcula con el resto.
5. **Un ticket es una ADMISIÓN**: `TicketIssuer` salta las hijas; una extensión no emite entrada.
6. **`postform` queda FUERA de esta versión**: vender la hora extra *después* de reservar mueve **aforo**
   post-reserva, que es una categoría que hoy no existe (los extras de `#413` no ocupan a propósito). Se
   puede hacer, pero es otra tanda y otra decisión.

## 10.6 Lo que hay que decidir antes de construir — es del owner

1. ✅ **`[DECIDIDO owner, 2026-09-06]`: es (1) de §10.2 — la fiesta sigue en su sala.** Este diseño
   vale tal cual. (2) y (3) habrían necesitado ocupación **cross-zona**, que no existe.
2. ⬜ **¿Cuánto cuesta?** Con el coste de oportunidad de §10.4 delante: media fiesta el sábado. Es un
   dato de catálogo: **no bloquea el código**, sí el despliegue.
3. ⬜ **¿Se puede comprar más de una hora extra?** (`max_qty` del enganche.) Configuración; el código
   soporta N por construcción, con el tope por SUMA.
4. ✅ **`[DECIDIDO owner, 2026-09-06]`: sí, `isFinishedInPractice()` se arregla en la misma tanda** —ver
   §10.7·T2bis, que es **tanda propia y no un apéndice**, por el motivo de abajo.
5. ⬜ **¿La hora extra de sala se ofrece también en el mostrador** (`CounterSale`) sin las cotas que ata
   la venta online, o con ellas? Suelo propuesto mientras no se decida: **con ellas** — el aforo no lo
   relaja nadie (`AFORO-01`), y lo que `#330` desató del mostrador fue la antelación mínima, no el cupo.

## 10.7 Las tandas propuestas

> ⚠️⚠️ **ESTE PLAN ESTÁ CORREGIDO POR LA REVISIÓN ADVERSARIAL DE §10.8.** La versión anterior tenía
> **T1 y T2 separadas** (ventana de sobreventa) y una **T2bis que empeoraba producción**. Lee §10.8
> antes que esta tabla.

| # | Qué | Por qué en este orden |
|---|---|---|
| **T1+T2** | El eje (`extends_parent_stay`, migración, guardas en las dos direcciones), `extra_minutes`, los **dos** mapas de ocupación, `CartOccupants` y `OrderCreator` bajo lock | ❗❗❗ **NO se pueden separar** (A2 de §10.8): en cuanto `AddonResolver` deja de rechazar extensores hay venta, y hasta que los mapas sepan contarla **cada venta es una sobreventa**. Si se quiere partir, la mitad de arriba tiene que dejar el extensor **invendible por construcción** |
| T3 | La oferta (`AddonOfferReader` **y `viewModel`**), el editor (**la familia extensora**), el tope propio y el reconciliador de fechas | ya con el aforo diciendo la verdad. ⚠️ Los tres primeros son hallazgos A3/A4/A5/A6: el plan viejo decía «`AddonOfferReader`» y no decía que **su filtro está escrito sobre `occupiesAfterParent()`**, que un extensor atraviesa sin que nadie lo mire |
| T4 | Las superficies: ventana mostrada, hoja de sala, puerta, correos | lo último, como en `#410` |
| **T5** | **`isFinishedInPractice()`: los DOS defectos a la vez** (decisión 4) | ⚠️⚠️ **la última y suelta, no «T2bis»**: no es de esta feature —gobierna TODAS las reservas y toca DINERO (`OrderBook`)— y **arreglar uno solo empeora producción** (A1). Va al final para que ningún número de las tandas anteriores se mezcle con el suyo |

▶ **Verificación exigida** (no negociable, `INVARIANTES §6`): `PackAvailability`, `SlotAvailability`,
`CartOccupants`, `AddonResolver` y `OrderCreator` están **todos** en el `CRITICAL_RE`, así que cada
tanda va con `VERIFY_CONC=1` y **los siete escenarios** de `purchase:verify-oversell`. Y hace falta un
**octavo escenario**: la última plaza de sala disputada entre una fiesta nueva y **la extensión de la
fiesta anterior** — el equivalente de `extra-hour` para el pool de packs, visto FALLAR sin la
validación antes de darlo por bueno.


## 10.8 · La revisión ADVERSARIAL del diseño y de las tandas (`#423`, 2026-09-06)

Nueve lentes sobre §10.3–§10.7 **antes de escribir una línea**. Lo que sigue son los hallazgos
**reproducidos o confirmados en el código**, no sospechas; los descartados van con su motivo, para que
nadie los vuelva a levantar.

### Confirmados

**A1 · CRÍTICO — la T2bis, tal como estaba escrita, EMPEORABA producción.** `isFinishedInPractice()`
tiene **DOS** defectos, no uno, y **van en direcciones opuestas**: usa `slot.end_time` (adelanta ~1 h
en una fiesta de 2 h) y parsea la hora de pared del parque **como UTC** (atrasa 1–2 h; medido en
`complementos-post-reserva.md` §4.9). Hoy **se compensan por accidente**. Medido sobre una fiesta de
2 h que empieza a las 15:00 y acaba de verdad a las 17:00 (hora del parque):

| | se declara terminada a las |
|---|---|
| hoy | **18:00** (1 h tarde) |
| arreglando **solo** la duración — el plan viejo | **19:00** (2 h tarde) — **peor** |
| arreglando duración **y** huso | **17:00** — exacto |

▶ **Se arreglan los dos a la vez o ninguno.** Y no es una tanda pequeña: son **9 ficheros / 12
llamadas** (la lista viva es la ficha de `DEUDA.md`), entre ellas **`OrderBook`** —de su `$finished`
cuelga la liquidación «Liquidado / Devuelto en el parque», que es **DINERO**— y
`AuthorizableReservationsReader`. Pasa a ser **T5, la última y suelta**.

**A2 · CRÍTICO — T1 y T2 separadas abren una ventana de sobreventa.** La T1 del plan viejo incluía
`AddonResolver`, que es **la autoridad del cobro**: en cuanto deja de rechazar extensores, se puede
vender uno — y hasta que la T2 enseñe a los mapas a contarlo, **cada venta es exactamente el hueco de
§10.1**, que es lo que esta feature viene a cerrar. Se fusionan; si alguien las parte, la primera mitad
tiene que dejar el extensor **invendible por construcción**, no por olvido.

**A3 · MAYOR — un extensor atraviesa el filtro de la oferta sin que nadie lo mire.**
`AddonOfferReader` retira de la oferta a los ocupantes que no aterrizan, y su filtro es
`! $addon->occupiesAfterParent() || tiene landing`. Un extensor devuelve **`false`** en ese predicado,
así que **pasa siempre** — se ofrecería aunque su ventana no quepa, y el checkout lo rechazaría. Es el
primo de `AFORO-02` y **justo lo que §4.5 existe para evitar**.

**A4 · MAYOR — falta la rama SIMÉTRICA en el modelo de vista.** `AddonResolver::viewModel()` oculta un
ocupante cuando el padre es un pack (`|| $isPack`). El extensor necesita la contraria —ocultarse cuando
el padre **no** es un pack—, o un enganche torcido por la puerta de atrás se ofrecería.

**A5 · MAYOR — el editor no revalidaría aforo al añadir una hora extra a una fiesta ya vendida.** La
familia que aterriza bajo el lock (`landOccupyingFamily`) se compone filtrando por
`occupiesAfterParent()`, así que **un extensor no entra en la lista**: añadirlo desde el panel alargaría
la fiesta **sin comprobar** que la franja siguiente está libre. Sobreventa desde el mostrador, que es
justo donde nadie la ve.

**A6 · MAYOR — el tope del resolutor no significa nada para el extensor.** Hoy es
`Σ cantidad ≤ lineQuantity` («no se quedan más de los que entran»), y en un extensor la cantidad son
**bloques de tiempo**: con 20 invitados dejaría pedir **20 horas**. Necesita tope propio (la rejilla y
el cupo lo cortarían, pero *fallar hacia el rechazo no es tener un tope*), y **`max_qty` del enganche
pasa a OBLIGATORIO** para extensores — la misma regla que `#413` impuso a los `postform`.

**A7 · MENOR (heredado) — bajar de 2 h a 1 h está BLOQUEADO.**
`addon_partial_reduce_unsupported`: la reducción parcial de un complemento no existe sin reembolso.
Quitar la extensión entera sí. El operador tendrá que quitar y volver a añadir —y eso **re-tarifica**.
Es una limitación de **dinero**, no de aforo (bajar libera sala), pero en un cumpleaños «déjalo en una
hora» es una petición normal: **decirlo antes de que lo descubra el mostrador**.

**A8 · MENOR — `AddonOfferReader` no está en el `CRITICAL_RE`** y con el extensor pasa a decidir sobre
ocupación de sala. Ya hoy decide sobre ocupantes; con esto, entra.

**A9 · MENOR — el octavo escenario de sobreventa que proponía §10.7 es insuficiente.** No basta con dos
compras: el caso que muerde es el **CRUCE** —el panel **añadiendo la extensión** a una fiesta mientras
la web compra la franja siguiente—, que es `panel-edit` × `extra-hour` sobre el pool de packs. Y hay que
**verlo FALLAR** sin la validación antes de darlo por bueno, como se hizo con `extra-hour` en `#410`.

### Descartados, con su motivo

- ~~«una hija sin `slot_id` romperá las superficies»~~ — **no**: casi todo el código filtra
  `whereNull('parent_item_id')` (censadas 28 apariciones), y `TicketIssuer` además exige
  `whereNotNull('slot_id')`. La hija extensora es invisible donde debe serlo.
- ~~«cancelar una hija desincroniza `extra_minutes`»~~ — **no se puede**: `item_is_addon` bloquea tanto
  la cancelación (`HasItemActionGuards`) como el reembolso (`GuardsItemRefunds`) de una línea hija. El
  único camino es el editor con `q = 0`, que es precisamente el punto de sincronización previsto.
- ~~«cancelar el padre deja la extensión contando»~~ — **no**: los dos mapas excluyen
  `cancelled_at` y `extra_minutes` vive en la línea del padre, que sale del recuento con él.
- ~~«`duration_min` NULL + `extra_minutes` da un número raro»~~ — **no**: en SQL `NULL + n = NULL`, que
  aquí significa «hasta el cierre», que es lo correcto; y la guarda prohíbe extender lo ilimitado.
- ~~«la rejilla de `#420` complica esto»~~ — al revés: con inicios cada 30 min hay **más** franjas donde
  aterrizar una fiesta alargada (medido: un pack de 3 h pasa de 2 a 5 horas de inicio en diario).


## 10.9 · Lo EJECUTADO — la T1+T2, en el árbol (`#424`, 2026-09-06)

**El camino de COMPRA, completo**: se puede vender una hora extra de sala y los dos mapas de aforo la
cuentan. Suite **4.347 verde** · **14/14 mutaciones** (`scripts/mutar-hora-extra-pack.sh`) · **los
OCHO escenarios** de `purchase:verify-oversell` sobre InnoDB, incluido el nuevo visto FALLAR sin la
corrección.

### Lo que entra

- **Migración** `2026_09_06_100000`: `ticket_types.extends_parent_stay` + `order_items.extra_minutes`
  (bool false / int 0 → **con los valores por defecto no cambia la conducta de nada**).
- **Guards en las dos direcciones**: `TicketType::booted()` (solo addon · duración > 0 · excluyente
  con `occupies_after_parent` · y el interruptor no se enciende sobre un enganche prohibido) y
  `ProductAddon::booted()` (**solo packs** · ni por-invitado, ni obligatorio, ni **incluido**, ni
  `postform` · **`max_qty` OBLIGATORIO**).
- **`AddonOccupancy`**: `sellableStayExtension()` (el cinturón) y `extraMinutes()` (cantidad × bloque).
- **`AddonResolver`**: el extensor no entra en `$occupyingQuantity` (esa suma es de personas), no
  lleva franja ni plazas, y `resolve()` devuelve `extra_minutes`.
- **Los dos mapas** suman `duration_min + extra_minutes` en el `SELECT`; `PackAvailability` acepta
  `$extraMinutes` para la fiesta que se evalúa (`stayMinutes()`), y `CartOccupants` emite la ventana
  ya extendida — **tres sitios que suman lo mismo porque son la misma regla**.
- **`OrderCreator`**: una SEGUNDA pregunta bajo el mismo lock (`stay_extension_line`), porque la de
  arriba mide si la fiesta cabe y ésta si cabe **alargada**. Y escribe el hecho en el padre.
- **A3/A4/A6 de §10.8** cerrados: `AddonOfferReader` no ofrece lo que no cabe y capa el `max` a los
  bloques que caben; `viewModel()` gana su rama simétrica; el tope es el `max_qty` obligatorio.
- **Contrato**: `ApiErrorCode::LineStayExtension` (`line_stay_extension`) + `openapi/v1.yaml` + los
  mensajes en es/en/fr + el mapa del cajón. **Código propio y no `line_pack_sold_out`**: el remedio
  es otro — quitar la hora extra CONSERVA la reserva.

### La decisión de alcance que evita deuda entre tandas

⚠️⚠️ **El editor del panel RECHAZA tocar un extensor** (`addon_stay_extension_unsupported`) hasta que
la T3 revalide el cupo alargado bajo el lock. Es el hallazgo **A5** cerrado con una puerta explícita
en vez de con un olvido: sin ella, añadir una hora extra a una fiesta vendida la alargaría **sin
mirar si la sala está libre después**, y eso no falla — sobrevende.

### Lo que enseñó la ejecución

⚠️⚠️ **Tres guardas del repo cazaron lo que faltaba, y ninguna era mía**: `ReservationErrorMapTest`
(un código de reserva sin `ApiErrorCode` deja al cliente sin saber qué hacer), `SidebarPayParityTest`
(el cajón no sabía traducirlo) y `SidebarDomContractTest` (**el bundle SSR quedó rancio** al tocar
`pay.js`, y ese test compara el bundle: sin la guarda habría medido código viejo y salido verde).

⚠️⚠️ **Una aserción mía pasaba EN VACÍO**: buscaba la fila de la oferta por `id` y el DTO publica
`productId`, así que «la hora extra no se ofrece» habría pasado igual con la oferta rota del todo.
**Lo delató su CONTROL** —el caso gemelo que exige que sí se ofrezca cuando cabe—, que es exactamente
para lo que está.

⚠️⚠️ **La rama simétrica de `viewModel()` (A4) no mordía**, y el motivo es información: por la vía con
fecha y hora la tapa el filtro de A3. Solo defiende el modo **sin `date`/`time`** —que el contrato
declara— y el enganche imposible metido por `Query\Builder`. Su caso tiene las dos mitades.

❗❗❗ **Y el octavo escenario de concurrencia NACIÓ INÚTIL.** La primera versión repartía los 12
workers entre las dos horas: los pares compraban la primera **con** extensión y los impares la
segunda. Con el defecto puesto salió **verde 4 de 4** — el comprador con extensión hace más trabajo
(resolver el complemento y su precio) y **llegaba siempre tarde al lock**, así que ganaba el otro y
nunca había dos. *Un escenario cuyo veredicto depende de quién gane la carrera no es un escenario: es
una moneda.* ▶ Rediseñado: la primera hora se siembra **ya vendida y alargada**, los 12 pujan por la
segunda y **nadie debe ganar** (`expected_winners = 0`, el primero del verificador que mide una
AUSENCIA). Su guarda del instrumento es a la vez su **control**: la franja vende **antes** de alargar
la fiesta y cierra **después** — y con el cupo ciego a `extra_minutes` esa guarda aborta diciendo que
la segunda hora «sigue ofreciendo 20».

### Lo que queda tras la T1+T2

**T4** (superficies: ventana mostrada, hoja de sala, puerta, correos) y **T5**
(`isFinishedInPractice()`, **los dos defectos a la vez**). Y las tres decisiones de §10.6 que no
bloquean el código: el precio, el `max_qty` del enganche y las cotas del mostrador.

## 10.10 · Lo EJECUTADO — la T3: el editor y la re-programación (`#425`, 2026-09-06)

**La puerta que la T1+T2 dejó cerrada, abierta con su red**: el panel ya puede añadir, subir y quitar
una hora extra sobre una fiesta vendida, y mover esa fiesta de día u hora. Suite **4.351** ·
**18/18 mutaciones** · los OCHO escenarios en verde.

### Lo que entra

- **`OrderItemEditor::resultingStayMinutes()`** — la derivación ÚNICA de «cuántos minutos quedará
  alargada la fiesta DESPUÉS de este guardado»: hijas extensoras vivas (con su cantidad editada si
  sube) + las que se añaden aquí − las que el día nuevo no vende. La usan los **dos** caminos
  (`edit()` y `changeSlot()`), y de ella salen las dos cosas que la edición tiene que decir igual que
  la compra: **el cupo que se revalida** y **el hecho que se escribe**, en la misma sentencia.
- **El ORDEN**, que es la propiedad: el plan de fechas (`AddonDateReconciler::plan`) se adelanta a la
  validación del aforo del padre en los dos caminos. ⚠️⚠️ Es la lección de `#417` §9.8·H1 aplicada a
  la extensión — *los minutos que una extensión retirada iba a ocupar no pueden contar contra el cupo
  que se pide*, o una fiesta no se podría mover a un día en el que su hora extra ni siquiera se vende.
  El plan es lectura pura, así que adelantarlo no cambia nada más.
- **`ItemRescheduleOffer`** ofrece con la ventana alargada, en sus **DOS** vías
  (`slotMeetsItemRequirements` para los días y `displayAvailableFor` para las horas).

### Lo que enseñó la ejecución

⚠️⚠️ **El primer caso de la re-programación pasaba con el arreglo REVERTIDO**, y el motivo es fino: la
oferta **cuenta la huella propia a propósito** (`#173`), así que cualquier hora **dentro del tramo
actual** de la fiesta sale excluida con extensión o sin ella. Para que el caso discrimine, la hora
candidata tiene que caer **fuera** de ese tramo y chocar solo por la ventana alargada. *Un caso que
mide donde el defecto no puede aparecer no mide nada* — la cuarta vez que este proyecto paga esa
lección (`#238`).

⚠️ Y ese mismo caso destapó que **tocar `slotMeetsItemRequirements` no bastaba**: `times()` —la lista
de HORAS, que es la que usa el modal— pasa por `displayAvailableFor`, otra vía. Las dos hacían la
misma pregunta y solo una estaba arreglada.


## 10.11 · Lo EJECUTADO — T4 y T5: lo que se LEE y cuándo termina la fiesta (`#426`, 2026-09-06)

Con esto **las cinco tandas de §10 están en el árbol**. Suite **4.353** · **22/22 mutaciones** · los
OCHO escenarios de sobreventa + `postform:verify-concurrency` y `mixed-party:verify-concurrency`, que
cuelgan del predicado que la T5 mueve.

### T4 · La ventana que se lee

`OrderItem::displayTimeWindow()` pasa a la **duración efectiva**, y con ella todas las superficies que
la consumen —hoja de sala, resumen del día, puerta, correos, «Mis reservas»—, porque es fuente única.
El **calendario del panel** se arregla aparte: pintaba el bloque con `ticket_types.duration_min` y
ahora usa la de la reserva. ⚠️ Decir «15:00–17:00» de una fiesta que acaba a las 18:00 no es un
detalle de estilo: es la sala dada por libre una hora antes de tiempo.

### T5 · Cuándo termina una fiesta — los DOS defectos a la vez

`isFinishedInPractice()` compara ahora **inicio + duración efectiva, en la zona operativa del parque**
(`AFORO-09`), en vez de el fin de la FRANJA parseado como UTC.

❗❗❗ **Los dos iban en direcciones opuestas y se compensaban por accidente**, que es lo que hacía
peligroso arreglar uno solo (medido en §10.8·A1: 18:00 → 19:00 en vez de 17:00). Con los dos, el
predicado dice la verdad.

⚠️⚠️ **Un test cementaba la premisa vieja y se REESCRIBIÓ**: `test_item_with_today_slot_still_running`
viajaba a las 10:30 «UTC» dando por hecho que la franja de las 10:00 era UTC. Son las 12:30 del
parque, o sea hora y media DESPUÉS de que la visita terminara — el caso afirmaba lo contrario de lo
que quería afirmar. Hoy viaja a las 08:30 UTC (10:30 del parque) y hay un caso nuevo que fija la otra
mitad: **el fin sale de la duración, no de la franja**, con un pack de 2 h en una franja de 1 h y su
hora extra encima.

▶ **Cierra la ficha ALTA de `DEUDA.md`** («declara terminada una reserva 1–2 horas tarde»), que estaba
abierta desde `#413`. Con ella se mueven a la vez el `readonly` del post-form, el `item_finished` del
panel, la ventana de dinero del suplemento mixto, el `$finished` del libro y «Mis reservas» — y por eso
se corrieron también los verificadores de esos dos subsistemas.

### T4·bis · La puerta del PANEL, que estuvo a punto de quedarse fuera

⚠️⚠️ **El interruptor existía en el dominio y NO en el formulario del catálogo**, así que el owner no
habría podido crear el complemento — el mecanismo entero habría quedado inalcanzable. Peor: el saneo
del alta (`CreateCatalog::normalizeByType`) **borra `duration_min` de todo complemento** salvo el que
la necesita, y solo conocía al ocupante. *Es exactamente el defecto que `#410` arregló para el
hermano —«el panel borraba el dato del que depende el interruptor»— esperando a repetirse.*

▶ Entra el toggle **excluyente** (cada uno se esconde cuando el otro está puesto: la combinación no
existe y el dominio la rechaza, así que ofrecerla solo produciría un error al guardar), su duración
con rótulo propio («cuánto **alarga** cada bloque»), y el **candado con ventas hechas** — que aquí
tiene un motivo distinto al del ocupante: los minutos ya están materializados en cada línea, así que
el aforo de lo vendido no se re-interpreta; **lo que se rompe es la EDICIÓN**, porque el editor
reconoce a sus hijas por este interruptor y apagarlo acortaría la fiesta en el siguiente guardado.

## 10.12 · La CONFIGURACIÓN acordada (`#427`, 2026-09-06)

`[DECIDIDO owner]`, montada y verificada en local sobre el catálogo real:

| complemento | lun–jue | festivos · finde · vísperas |
|---|---|---|
| Hora extra de sala · **JUMP** | 5,00 € | 8,00 € |
| Hora extra de sala · **KIDS** | 3,00 € | 5,00 € |

❗❗ **DOS productos y no uno, y es estructural**: el precio vive en `prices` **del producto** y el
pivote **no tiene columna de precio**, así que un mismo complemento no puede costar distinto en cada
pack. Mismo motivo por el que las horas extra de ENTRADA ya están separadas por zona. *Fusionarlas
«para simplificar» iguala los dos precios sin que falle nada.*

▶ **Diario vs festivo no es un ajuste del complemento**: sale de tener precio en las **dos** tarifas.
Y su contrario es el interruptor de calendario que gobierna las entradas — «Hora extra · JUMP» de la
entrada de 2 h tiene precio **solo en `special`**, así que de lunes a jueves **ni se ofrece**. Los
festivos y vísperas sueltos se declaran con `special_dates.rate_type_id` (verificado).

▶ **Tarta → post-form** (corte 48 h, máx 5). **Calcetines → se quedan en la reserva.** ⚠️ **Menú 1 y
Menú 2 NO pueden ir al post-form**: son grupo excluyente y uno va incluido, así que allí se
auto-inyectaría un cargo que nadie pidió.

⚠️ `max_qty = 1` por defecto; con cualquier máximo se pinta con **contador** y no con interruptor.

### Lo que queda de §10

Solo las tres decisiones de §10.6 que **no bloquean el código**: el precio de la hora extra (con el
coste de oportunidad de §10.4 delante), el `max_qty` del enganche y si el mostrador lleva las mismas
cotas (suelo propuesto: sí). El mecanismo está completo y verificado.

---

# 11 · LA HORA EXTRA COBRADA POR INVITADO — reapertura de §10.3.1 ⬜ **DISEÑO** (`#443`, 2026-09-07)

> ❗❗❗ **ESTA SECCIÓN CORRIGE A §10.3.1 Y VA ANTES QUE AQUELLA TABLA.** §10.3.1 declaró que la
> cantidad de un extensor **son bloques de tiempo** y prohibió `per_guest` sobre él. La prohibición
> era correcta *con la derivación que existía*; el owner reabre el caso el 2026-09-07:
> *«permitir que la hora extra de cumpleaños pueda cobrarse por invitado»*, y con la pregunta de
> §10.2 delante: **`[DECIDIDO owner, 2026-09-07]` es la opción A — «la hora extra para todos los
> invitados, pero se cobra por invitado»**.
>
> ▶ **Lo que cambia no es el guard: es de dónde salen los MINUTOS.**

## 11.1 · Lo MEDIDO antes de diseñar (producción, 2026-09-07, solo lectura)

| | |
|---|---|
| Fiestas vivas | **16**, todas pagadas, ninguna cancelada |
| Tamaño | min **8** · max **20** · media **12,9** · mediana **13** · 2 ya en el techo |
| Horas extra de sala vendidas | **2**: 8 invitados → 5,00 € · 15 invitados → 4,00 € |
| Lo cobrado por ellas | **9,00 €**. Por invitado al mismo precio habría sido **100,00 €** (11×) |
| Enganches | `max_qty = 1` · `quantity_mode = fixed` · `stage = booking` · `duration_min = 60` |

❗❗ **Y la hora extra de sala NO se vende viernes, sábado ni domingo, y eso es DECISIÓN, no defecto.**
Medido de punta a punta: `Hora extra de sala · JUMP` y `· KIDS` tienen precio **solo en la tarifa
`normal`**, y la tarifa `special` cubre `[5,6,0]`. Un complemento sin precio para la tarifa del día
**no se ofrece** ({@see AddonResolver::viewModel}, el `continue` de «sin precio ≠ 0 € explícito»), así
que la oferta real del Pack JUMP un sábado son Calcetines y el grupo de menús, sin hora extra. El
rastro dice quién y cuándo: `catalog.prices_updated` del **2026-09-06 15:21**, `{"2":{"from":500,"to":null}}`
y `{"2":{"from":800,"to":null}}`. ▶ `[DECIDIDO owner, 2026-09-07]`: **se queda así.** La hora extra de
sala es un producto de entre semana. ⚠️ **`ENTORNOS.md` §6 registra lo contrario** («KIDS 3/5 € y JUMP
5/8 € por tarifa»): esa nota caducó esa misma tarde y se corrige con esta tanda. **No lo "arregles"
devolviendo el precio especial.**

## 11.2 · Por qué hoy está prohibido — y por qué la prohibición era CORRECTA

`per_guest` sobre un extensor está bloqueado en **tres** sitios, y ninguno sobra:
`TicketType::booted()`, `ProductAddon::booted()` y el cinturón de `AddonResolver::resolve()`.

El motivo, con el número delante: `AddonOccupancy::extraMinutes()` es `cantidad × duration_min`, y
`AddonResolver::effectiveQuantity()` devuelve **el nº de invitados** en modo `per_guest`. Una fiesta
de 15 alargaría la sala **900 minutos**. ⚠️ Y el tope no lo frenaría: `effectiveQuantity()` **sale por
`per_guest` ANTES de aplicar `max_qty`**, así que el `max_qty` que §10.5·2 hizo obligatorio para un
extensor no se mira siquiera.

▶ **La raíz: `per_guest` dice hoy DOS cosas a la vez** —*cuántas unidades hay* y *cuánto se cobra*—
y para un extensor sólo la segunda tiene sentido. La primera la contesta el producto: **una hora es
una hora, la compren 8 invitados o 20.**

## 11.3 · Un defecto PREEXISTENTE que esto cierra por construcción (REPRODUCIDO)

El formulario del catálogo **ofrece hoy «por invitado» sobre una hora extra de sala**: sus `options()`
sólo esconden el modo cuando `occupiesAfterParent()` o la fase es `postform`, y un extensor no es ni
lo uno ni lo otro. Al guardar, el dominio contesta con una `InvalidArgumentException` **sin capturar**
— o sea una pantalla de error de Filament, no un aviso.

```
pivote 106→316: mode=fixed max_qty=1
guardar con quantity_mode=per_guest  →  InvalidArgumentException
   «Un complemento que EXTIENDE la estancia no puede ser por-invitado…»
```

*La cara amable del guard del extensor nunca se escribió; la del ocupante sí.* Con esta tanda la
combinación pasa a ser **legal**, así que el defecto se va con su causa — pero queda escrito porque
la lección no es el bug, es que **un guard sin cara amable en el formulario es una pantalla de error
esperando a que alguien elija la opción que el propio formulario ofrece**.

## 11.4 · Opciones consideradas

| | Modelo | Por qué NO / SÍ |
|---|---|---|
| **D1** | `quantity` sigue siendo **bloques** y `unit_price` nace ya multiplicado (`precio × invitados`) | ⛔ **Descartada.** El aforo no se toca, pero `unit_price` deja de ser «lo que cuesta UNA unidad» y pasa a depender de la cantidad de OTRA línea. Todas las superficies imprimen `cantidad × unit_price`, así que el cliente y el mostrador leerían **«1 unidad · 60,00 €»** y el hecho «por invitado» sería invisible. Y re-preciar al cambiar los invitados exigiría **recuperar la base dividiendo** `unit_price / invitados_viejos`: exacto por construcción, pero es un precio histórico derivado de un divisor que se mueve — justo lo que este proyecto no hace con el dinero |
| **D2** | `quantity` = **bloques × invitados** | ⛔ **Descartada.** `extra_minutes` volvería a salir de la cantidad y habría que **dividir dentro del camino del aforo** para recuperar los bloques. Un aforo que depende de una división cuyo divisor vive en otra fila es el defecto (b) de §10.1 por otra puerta |
| **D3** | **Se reutiliza `quantity_mode = per_guest` tal cual, y los MINUTOS dejan de salir de la cantidad** | ✅ **Elegida.** Ver §11.5 |

## 11.5 · Diseño elegido — D3: los minutos salen del BLOQUE, no de la cantidad

**Una sola regla nueva, en una sola función:**

> Los **bloques** que compra una cantidad son `1` si el enganche es por-invitado, y la cantidad si no.
> Los minutos son `bloques × duration_min`.

Con eso, un extensor `per_guest` encaja sin inventar nada:

| pieza | qué pasa | por qué encaja |
|---|---|---|
| `quantity` de la hija | **= los invitados** (`effectiveQuantity` ya lo hace) | el cliente no elige un número: la hora extra es para toda la fiesta (opción A) |
| `unit_price` | **= el precio por invitado**, histórico | `PAY-19` intacto: lo que se vendió ayer no lo reescribe el catálogo de mañana |
| `chargedSubtotalCents()` | `invitados × precio` | **el núcleo del dinero no se toca**: ni el libro, ni `LineFacts`, ni las cuatro identidades |
| `extra_minutes` del padre | **= 1 bloque**, siempre | el aforo no cambia de magnitud: la sala se ocupa una hora, la compren 8 o 20 |
| `seats` de la hija | **0**, como todo extensor | el defecto (b) de §10.1 sigue cerrado: las plazas son del padre |
| re-preciar al cambiar los invitados | **el `perGuestRescales` que YA existe** en `OrderItemEditor` | mecanismo cero: recalcula cantidad, escribe `recordEdit(±Δ)` y su contexto |
| el control en la web | **casilla**, no contador (`can_toggle` ya lo decide para `per_guest`) | «una hora más para la fiesta» es un sí/no, no una cantidad |
| el tope | el del **pack** (`max_qty` = 20 invitados) | ya no hace falta `max_qty` en el enganche: la cantidad no la elige nadie |

### 11.5.1 · La firma cambia, y el parámetro es OBLIGATORIO a propósito

```
AddonOccupancy::blocksFor(ProductAddon $pivot, int $quantity): int
AddonOccupancy::maxBlocks(ProductAddon $pivot): int
AddonOccupancy::minutesForBlocks(TicketType $addon, int $blocks): int
AddonOccupancy::extraMinutes(TicketType $addon, ProductAddon $pivot, int $quantity): int
```

⚠️⚠️ **Sin valor por defecto.** Son **cinco** los sitios que derivan minutos (`AddonResolver::resolve`,
`CartOccupants::effectiveDurationOf`, `AddonOfferReader` en su barrido de bloques y
`OrderItemEditor::resultingStayMinutes` **dos veces**), y con un `?ProductAddon $pivot = null` el que
se olvide **multiplica por los invitados en silencio**. Es la lección de `#329` —«un parámetro con
valor por defecto no avisa de que hacía falta», 140,00 € de desfase— aplicada a algo que no es dinero
sino **aforo**. Con el parámetro obligatorio, PHP encuentra los cinco.

⚠️ `resultingStayMinutes()` es el único que hoy **no tiene el pivote a mano**: sus hijas salen de
`liveStayExtendingChildren()`. Lo resuelve `addonPivotsFor($item)`, que ya existe y ya es «la misma
autoridad que la compra pública».

### 11.5.2 · Lo que se RELAJA, y lo que NO

**Se relaja** (los tres guards del extensor dejan de rechazar `per_guest`):

- `TicketType::booted()`, `ProductAddon::booted()` y el cinturón de `AddonResolver::resolve()`.
- El `max_qty` **obligatorio** deja de exigirse **cuando el enganche es `per_guest`**: `#423`·A6 lo
  impuso porque «no se quedan más de los que entran» no significa nada para bloques de tiempo — con
  la cantidad atada a los invitados, el tope es el `max_qty` **del pack** y el del enganche no lo
  mira nadie (`effectiveQuantity` sale antes). **Exigir un número que nadie lee es peor que no
  exigirlo: parece una defensa.**

**NO se relaja nada más, y cada una sigue teniendo su motivo:**

- **`is_included` sigue prohibido**: un incluido se auto-inyecta y **toda fiesta nacería alargada**.
- **`is_mandatory` sigue prohibido**: mismo motivo por otra puerta.
- **`stage = postform` sigue prohibido**: vender aforo después de reservar exige el lock y la
  revalidación que esa fase no tiene (§10.5·6).
- **Colgar de algo que no sea un pack sigue prohibido** (§10.3.1).
- **`duration_min > 0` sigue obligatorio**: sin duración alargaría cero y se habría vendido una hora
  extra que no ocupa nada.

### 11.5.3 · La cara amable del panel, que faltaba

El `Select` de modo esconde hoy `per_guest` para el ocupante y para `postform`. Con esta tanda:

- para un **extensor** el modo se ofrece (es el caso de uso nuevo);
- `max_qty` deja de ser obligatorio en un extensor por-invitado y se **oculta**, porque no lo lee nadie;
- y el rótulo del modo dice lo que significa **aquí**: no «una unidad por invitado» sino
  **«se cobra por invitado»** — la misma casilla, dos lecturas, y el catálogo tiene que decir la
  correcta o el operador configura a ciegas.

### 11.5.4 · Lo que ve el cliente

`AddonResolver::viewModel()` no tiene hoy rama para el extensor: cae en `$note = $priceStr` («5,00 €»),
que con precio por invitado sería **una mentira de 60,00 €**. Gana su rama, hermana de la del ocupante
(§8.6): con la fila elegida dice **para cuántos**, y sin elegir dice el precio unitario.

```
sin elegir:  «4,00 € por invitado»
elegida:     «Una hora más para los 15 invitados · 60,00 €»
```

⚠️ Se compone **en el dominio y no en el cliente**: la heredan el cajón, la API y el alta manual del
panel sin una línea suya, exactamente como la nota del ocupante.

## 11.6 · Lo que este diseño NO cambia (y por eso es barato)

- **Ni una migración.** No hay columna nueva: el eje ya existe (`product_addons.quantity_mode`).
- **Ni una línea del libro.** `chargedSubtotalCents()` es `(cantidad − gratis) × unit_price` y sigue
  siéndolo; `LineFacts`, `OrderBook` y las cuatro identidades no se enteran.
- **Ni un cambio de magnitud en el aforo.** Los dos mapas suman `duration_min + extra_minutes` y
  `extra_minutes` sigue valiendo un bloque.
- **Nada de lo ya vendido se mueve.** Las dos horas extra vividas en producción tienen
  `quantity_mode = fixed`; el modo es del ENGANCHE y sus líneas guardan su `unit_price` histórico.
  ⚠️ **Esto es el criterio de éxito 4 de §11.7 y hay caso de CONTROL**: cambiar el modo en el
  catálogo **no puede** re-precias una fiesta vendida.

## 11.7 · Criterios de éxito medibles

1. Un extensor `per_guest` sobre un pack **se guarda** desde el panel (hoy: pantalla de error).
2. Comprado en una fiesta de N invitados, la línea nace con `quantity = N`, `unit_price` = el precio
   por invitado, `seats = 0`, y el pedido cobra `N × precio`.
3. `order_items.extra_minutes` del padre vale **`duration_min`**, no `N × duration_min` — verificado
   con N = 15 y con el control de un extensor `fixed`, que sigue valiendo `cantidad × duration_min`.
4. **Cambiar el modo del enganche NO mueve una fiesta ya vendida** (control con las dos horas extra
   reales de producción replicadas en fixture).
5. Subir los invitados de la fiesta **re-precia la hora extra** y escribe su `recordEdit(+Δ)`;
   bajarlos, el simétrico.
6. Los **ocho** escenarios de `purchase:verify-oversell` siguen verdes, y `stay-extension` —el que
   mide una AUSENCIA— sigue midiendo cero ganadores **con el extensor en modo por-invitado**.
7. Un extensor `per_guest` **sigue sin poder** ser incluido, obligatorio, `postform` ni colgar de una
   entrada: cuatro casos, uno por guard.

**Fuera de alcance, a propósito:**

- **Cobrar más de un bloque por invitado.** Con `per_guest` la cantidad son personas, así que «dos
  horas extra» exige un producto propio de `duration_min = 120` — que es cómo el catálogo ya resuelve
  que KIDS y JUMP cuesten distinto (§10.12: el pivote no tiene columna de precio). Se dice aquí para
  que nadie lo descubra al configurarlo.
- **El precio.** `[DECIDIDO owner, 2026-09-07]`: «lo que configure el dueño». Es catálogo, no código.
- **La venta de fin de semana.** Ver §11.1: se queda sin precio especial a propósito.

## 11.8 · Impacto en invariantes

| ID | Qué le pasa |
|---|---|
| `AFORO-01` / `AFORO-05` | **Sin cambio**: el lock y su receta no se tocan. La derivación de minutos cambia de fórmula, no de sitio |
| `AFORO-12` | **Sin cambio**: la rejilla solapada y el conteo por PRESENCIA siguen igual |
| `PAY-19` | **Se refuerza**: el precio por invitado vive en `unit_price` de la línea, histórico, y el modo del enganche no reescribe lo vendido |
| `PAY-16` / `PAY-17` | **Sin cambio**: no se toca `chargedSubtotalCents()` ni ningún compositor del libro |
| `SUITE-…` | `AddonOccupancy`, `AddonResolver`, `CartOccupants`, `OrderItemEditor`, `PackAvailability` y `OrderCreator` están en el `CRITICAL_RE`: la tanda va con `VERIFY_CONC=1` y los ocho escenarios |

## 11.9 · Plan de verificación empírica

- **Casos nuevos** en `tests/Feature/Sales/PackStayExtensionTest.php` y
  `tests/Feature/Sales/StayExtensionGuardsTest.php` (los siete criterios de §11.7).
- **Guarda de identidad**: `extra_minutes` del padre == Σ (bloques de sus hijas extensoras vivas ×
  su `duration_min`) — con el caso por-invitado y su CONTROL fijo.
- **Mutación**: guion propio (`scripts/mutar-hora-extra-por-invitado.sh`), con al menos
  (a) `blocks()` devolviendo la cantidad en modo por-invitado, (b) el guard relajado de más
  (`is_included`), (c) `viewModel` sin su rama nueva y (d) el `max_qty` obligatorio restaurado.
- **Concurrencia**: `php artisan purchase:verify-oversell` — los ocho escenarios de siempre **más un
  NOVENO**, `stay-extension-per-guest`. ⚠️⚠️ **No es una variante del octavo: mide otra cosa y por otro
  camino.** Allí la extensión se SIEMBRA y nadie debe ganar; aquí los 12 COMPRAN la misma sala con su
  hora extra en modo por-invitado y **uno solo debe ganar** — y si los minutos volvieran a salir de la
  cantidad, cada compra pediría `8 × 60 = 480` min, la ventana no cabría en la rejilla y ganaría CERO.
  *Un escenario que distingue «uno gana» de «no gana nadie» mide la derivación a través del checkout y
  bajo el lock, que es donde la suite es ciega.*
- **Navegador**: la casilla de la web y el modal del panel, con el importe delante.

## 11.10 · Revisión y decisión

Diseño del agente sobre encargo del owner (2026-09-07). `[DECIDIDO owner]`: la opción A de §10.2 con
cobro por invitado · la hora extra **no se vende el fin de semana** · el precio lo pone el dueño.
Entrada final en `DECISIONES.md` **`#443`** al aprobarse.

## 11.11 · La revisión ADVERSARIAL del diseño (2026-09-07, antes de escribir código)

Seis lentes sobre §11.5, con el código delante. **Un bloqueante, dos correcciones de diseño, dos
confirmaciones y un descarte.**

### ❌ A1 · BLOQUEANTE — cambiar el MODO re-preciaría en silencio una fiesta ya vendida

`OrderItemEditor` re-escala los complementos por-invitado leyendo **el pivote VIVO**
(`$pivotByAddonId->get(...)->pivot->isPerGuest()`). Con el enganche cambiado de `fixed` a
`per_guest`, la hija vendida como **1 bloque a 5,00 €** pasaría, en la primera edición de cantidad de
esa fiesta, a **N unidades a 5,00 €** — con su `recordEdit(+Δ)` y su correo. En las dos horas extra
reales de producción eso son **+35,00 €** y **+56,00 €** que nadie vendió.

▶ Es `PAY-19` roto por la puerta de la configuración: *lo que se compró ayer no lo reescribe el
catálogo de mañana*. ⚠️⚠️ **Y no lo trae esta feature: existe hoy** para cualquier complemento normal
que alguien pase de `fixed` a `per_guest`. Lo que hace esta feature es darle un sujeto caro.

▶ **Cierre: el `quantity_mode` de un enganche se BLOQUEA mientras tenga líneas vivas en reservas que
todavía se pueden editar** — que son exactamente las que el re-escalado puede alcanzar (`item_finished`
bloquea el editor, así que una fiesta pasada ya no se re-escala). Misma forma y mismo sitio que el
candado de `extends_parent_stay` de §10.11·T4·bis, y se suelta solo cuando esas fiestas pasan.
⚠️ **La salida para el owner, si lo quiere YA, es la que `#427` ya estableció: un producto nuevo** —
que además es lo natural, porque el precio cambia de unidad. ▶ Editar el PRECIO sigue siendo seguro:
`unit_price` es histórico en la línea.

### ❌ A2 · La oferta itera BLOQUES y los pasa donde se espera una CANTIDAD

`AddonOfferReader::stayExtensionCaps()` recorre `for ($blocks = 1; $blocks <= $ceiling; $blocks++)` y
llama a `extraMinutes($addon, $blocks)`. Con la regla nueva, un extensor por-invitado devolvería **los
mismos minutos en cada vuelta** y `$fits` treparía hasta el `max_qty` del enganche ofreciendo bloques
que nadie puede comprar.

▶ **Cierre: la unidad va en el NOMBRE**, no en el comentario —

```
AddonOccupancy::blocksFor(ProductAddon $pivot, int $quantity): int
AddonOccupancy::maxBlocks(ProductAddon $pivot): int
AddonOccupancy::minutesForBlocks(TicketType $addon, int $blocks): int
AddonOccupancy::extraMinutes(TicketType $addon, ProductAddon $pivot, int $quantity): int
```

La oferta usa `maxBlocks()` + `minutesForBlocks()` (que es lo que tiene: bloques); los otros cuatro
llamantes usan `extraMinutes()` (que es lo que tienen: una cantidad). *Una función que recibe «un
número» y no dice de qué es el número es la forma de este defecto.*

### ❌ A3 · El rótulo de la casilla del cajón dice lo que NO es

La SPA ya pinta un por-invitado opcional como **casilla** (`can_toggle` → `addons__perguest-toggle`),
así que no hace falta ni un componente nuevo. Pero su rótulo es `tickets.addon_per_guest_add` =
**«Añadir · uno por invitado»**, y una hora extra no es «uno por invitado»: **es una hora para todos**.

▶ **Cierre**: el rótulo pasa a ser un VERBO neutro y **el hecho lo dice la nota, que la compone el
dominio** (`addon_per_unit` = «:price/invitado» ya lo hace para el caso normal). Así no hay campo
nuevo en el contrato, ni una segunda copia de la regla en el cliente. Es el criterio de `#410`: el
texto se compone donde se sabe, y el cajón, la API y el alta manual lo heredan.

⚠️⚠️ **Y esto obliga a mirarlo en NAVEGADOR, no es opcional**: medido en producción, **hay CERO
enganches `per_guest`**, así que ese camino de la casilla —el que esta feature estrena— no lo ha
ejercitado nunca ningún dato real.

### ✓ A4 · Confirmado: el editor ya no rechaza al extensor

`addon_stay_extension_unsupported` **no existe en el árbol**: la puerta que la T1+T2 cerró la abrió la
T3 (`#425`). No hay nada que reabrir.

### ✓ A5 · Confirmado: los mapas de aforo NO ven la cantidad de una hija extensora

`PackAvailability::occupancyMaps()` y `SlotAvailability::occupancyMap()` cruzan por
`join slots on slots.id = order_items.slot_id`, y un extensor nace **sin franja**. Verificado además
por censo: los **cinco** únicos sitios que leen la cantidad de una hija extensora son los cinco
llamantes de `extraMinutes`. ▶ Por eso cambiar el significado de esa cantidad es seguro: **solo hay
una derivación que la mira**.

### ✗ A6 · Descartado — `validateAddonEdits()` sí protege a una hija bloqueada

La hipótesis era que un edit forjado podría mover la cantidad de una hija por-invitado desde el panel.
**Es falsa**: el validador lee `childAddonMeta()` y devuelve **`addon_locked`** si la fila está
bloqueada y la cantidad cambia. *Un resultado negativo también es una medida.*

### Lo que la revisión añade al plan

- El **candado del modo** con líneas vivas (A1) entra en la misma tanda: sin él, la feature abre un
  agujero de dinero mayor que el que cierra.
- `max_qty` del enganche se **oculta** en un extensor por-invitado (§11.5.3): dejar un campo que nadie
  lee da sensación de defensa sin defender nada — la lección de `#464` sobre `$calMonth`.

## 11.12 · Lo EJECUTADO (2026-09-07, `#443`)

**El diseño de §11.5 entero, sin migración y sin tocar el núcleo del dinero.**

### Lo que entra

| Pieza | Qué cambia |
|---|---|
| `AddonOccupancy` | **la regla nueva, sola**: `blocksFor()` · `maxBlocks()` · `minutesForBlocks()` · `extraMinutes()` con el **pivote obligatorio** |
| `AddonResolver` | el cinturón deja de rechazar `per_guest` (siguen incluido, obligatorio, `postform` y no-pack) · la nota de la oferta gana su rama |
| `AddonOfferReader` | el barrido de bloques usa `maxBlocks()` + `minutesForBlocks()`: **la unidad va en el nombre** |
| `CartOccupants` · `OrderItemEditor` | pasan el pivote; el editor lo resuelve con `addonPivotsFor()` y prefiere el del producto NUEVO |
| `ProductAddon` | `per_guest` fuera de la lista prohibida · `max_qty` deja de exigirse en por-invitado · **el candado del modo** (`hasEditableSoldLines()`) |
| `TicketType` | `per_guest` fuera de la consulta de enganches en conflicto |
| Panel | el modo se ofrece en un extensor con **rótulo propio** («Se cobra por invitado»), su ayuda propia y el candado con su explicación |
| i18n | `addon_stay_per_guest_selected` (es/en/fr) y `addon_per_guest_add` pasa a **verbo neutro** |

### Lo verificado

- **`StayExtensionPerGuestTest`, 19 casos** — el cobro, las plazas y la franja de la hija, el bloque
  único frente al control de cantidad fija, el re-precio al subir y al bajar, el candado con sus
  **tres** controles, los dos cinturones y lo que ve el cliente.
- **`scripts/mutar-hora-extra-por-invitado.sh` · 12/12 muerden.**
- **`scripts/mutar-hora-extra-pack.sh` · 22/22 muerden** tras actualizarlo a la firma nueva: la red de
  §10 sigue entera.
- **Un NOVENO escenario de concurrencia**, `stay-extension-per-guest`, **visto FALLAR sin la
  derivación**: con `blocksFor()` devolviendo la cantidad, **0 de 12** compras salen adelante (la
  ventana de 480 min no cabe en la rejilla); con ella, **1 de 12** y once `sold_out`. Registrado en el
  inventario de `OversellVerifierCoversEveryQuotaTest`, que exige que cada escenario diga qué aforo
  cubre.

### ❗❗ Lo que enseñó la ejecución

⚠️⚠️ **La primera vuelta de mutación dio 8/12, y los cuatro fallos eran DOS cosas distintas** — que es
justo por lo que se mutan las guardas en vez de contarlas:

- **DOS mutaciones DÉBILES** (el instrumento, no la red). «La oferta vuelve a preguntar por cantidad»
  no mordía porque el fixture tenía `max_qty = null`, y ahí el techo viejo y el nuevo **valen lo
  mismo**; «el candado ignora las canceladas» no mordía porque el caso cancelaba la reserva **y** su
  hija, y el filtro hermano la tapaba. Se re-apuntaron los casos —`max_qty = 3` y cancelar **solo la
  reserva**— y las dos muerden.
- **DOS HUECOS reales.** El **cinturón del cobro** no tenía red: existe exactamente para lo que los
  guards de Eloquent no ven, así que **ningún caso que pase por Eloquent puede ejercitarlo** — hay
  ahora uno que tuerce la fila con `DB::table()->update()`. Y **encender el interruptor sobre un
  enganche INCLUIDO** no estaba cubierto: el caso que existía solo probaba la dirección «cuelga de una
  entrada».

*Una mutación que no muerde dice una de dos cosas, y hay que averiguar cuál antes de tocar nada: o la
red tiene un hueco, o la mutación no distingue nada en ese fixture.*

### Lo que queda de §11

Solo lo que no bloquea el código y es del owner: **configurar el complemento** (poner el modo «Se
cobra por invitado» y el precio por invitado) — con el candado de §11.11·A1 delante, porque las dos
horas extra vendidas en producción son de fiestas del **21/09** y hasta que pasen el modo de ese
enganche está bloqueado. ▶ **La salida, si lo quiere antes: un producto nuevo** (`#427`).

⚠️⚠️ **CADUCADO POR §12**: el owner intentó hacer exactamente eso el 2026-09-08 y descubrió lo que
este diseño no vio — **esa ventana no se abre nunca**. Lo que sigue es la salida definitiva.

---

# 12 · EL SELLO DEL MODO de un complemento (`#448`, 2026-09-08)

> 🟦 **SPEC APROBADA Y LA T1 EN EL ÁRBOL** (`#448`, 2026-09-08; ejecución en **§12.16**). Las cinco
> decisiones de §12.12 están tomadas (`[DECIDIDO owner, 2026-09-08]`) y producción está medida el
> mismo día, **solo lectura**. ▶ **T1 en §12.16 y T2 en §12.17. Quedan la T3 (el candado
> re-apuntado) y la T4 (doc + OJO del owner).**

## 12.1 · El síntoma, y por qué la ventana NO se abre nunca

El owner intentó poner la hora extra de sala en modo «se cobra por invitado» y el panel lo rechazó.
**No es un fallo: es el candado de `#443`** (§11.11·A1). Pero al mirarlo dijo lo que el diseño no
había visto:

> *«esa ventana no se abre nunca»* — con venta continua siempre hay una fiesta viva por delante.

Y es literal. El candado (`ProductAddon::hasEditableSoldLines()`) exige
que **ninguna** línea viva de ese enganche cuelgue de una fiesta sin celebrar. Medido en producción
el 2026-09-08 llamando al método real: **bloquea 9 de 29 enganches**, no los 2 que la §11.12 daba
por supuestos. Y entre ellos están los menús, que **toda fiesta vendida lleva** porque
`AddonResolver::groupDefault()` siempre elige un miembro del grupo excluyente: ese conjunto se
rellena con cada venta.

▶ **La comparación que lo explica entero, y es del owner**: *el PRECIO ya está sellado, y lo está
porque vive en la línea* (`order_items.unit_price`), así que tocar el catálogo no mueve lo vendido;
**el MODO no se guarda en ninguna parte de la línea, y por eso se escapa**.

## 12.2 · Lo MEDIDO en producción (2026-09-08, solo lectura, `playjump.es`)

Ejecutado por SSH con `php artisan tinker` **por stdin** —no se subió ni un fichero al servidor y no
se escribió ni una fila—, llamando al código real de la app en vez de reimplementar sus reglas.

| medida | valor |
|---|---|
| enganches (`product_addons`) | **29** — 25 `fixed`, **4 `per_guest`** |
| enganches que el candado bloquea HOY | **9 de 29** |
| líneas hijas vivas | **27**, todas en pedidos `paid` |
| hijas con `quantity > 1` · `free_quantity > 0` · `seats > 0` | 6 · 18 · **0** |
| padres con `extra_minutes > 0` | **2** (las dos fiestas del 21/09) |
| productos con `extends_parent_stay` | **2** (`#121` JUMP, `#122` KIDS), **1 hija viva cada uno** |
| padres con ≥2 hijas extensoras | **0** |
| huérfanas vivas | **1** — y es un PORTADOR de fiesta mixta (§12.8) |
| filas `audit_logs` `catalog.addon*` | **4**, todas del 2026-09-06, usuario 33, sobre `108` (Menú 2) y `110` (Calcetines) |

❗❗ **Y esto CADUCA una afirmación de §11.11·A3**: decía «medido en producción, hay **CERO**
enganches `per_guest`». **Hay cuatro** — Menú 2 en los dos packs y Calcetines en los dos packs.
*La medición del 07 fue correcta para lo que miró; lo que no vio es que el cambio ya se había hecho
el 06.*

### El daño de cambiar HOY los dos enganches que el owner quiere

| enganche | hoy | con `per_guest`, tras la 1.ª edición | delta |
|---|---|---|---|
| `#60` · Hora extra sala **JUMP** (hija #42, padre 8 invitados) | 1 × 5,00 € = **5,00 €** | 8 × 5,00 € = **40,00 €** | **+35,00 €** |
| `#61` · Hora extra sala **KIDS** (hija #32, padre 15 invitados) | 1 × 4,00 € = **4,00 €** | 15 × 4,00 € = **60,00 €** | **+56,00 €** |

Son exactamente las dos cifras que §11.11·A1 predijo, **confirmadas sobre los datos reales de hoy**.

## 12.3 · La raíz — y las TRES correcciones al diagnóstico heredado

> **`order_items.quantity` de una línea hija significa dos cosas distintas —BLOQUES o PERSONAS— y
> quién lo decide es una columna de la CONFIGURACIÓN, no un hecho de la línea.**

**(a) NO es solo dinero: la otra mitad es AFORO, y no estaba escrita en ningún sitio.** El docblock
del candado, el mensaje del panel y §11.11·A1 hablan **solo de re-precio**. Pero el modo también
gobierna `AddonOccupancy::blocksFor()` (`isPerGuest() ? 1 : max(0,$quantity)`) →
`AddonOccupancy::extraMinutes()` → `OrderItemEditor::resultingStayMinutes()` → el
**`order_items.extra_minutes` del PADRE**. Con la cantidad re-escalada a 15 y un bloque de 60 min,
la fiesta pediría **900 minutos de sala**: quince horas.

**(b) El disparador es MÁS ancho que «editar la cantidad».** El re-escalado de dinero sí exige
`if ($newQty !== $oldQty)` (en `OrderItemEditor::edit()`). El de aforo **no**: `OrderItemEditor::changeSlot()` llama a `resultingStayMinutes()` y escribe `extra_minutes` en el mismo `forceFill`. **Mover el día de una fiesta, sin tocar nada más, la re-alarga con el modo de hoy.**
⚠️ Y hay ironía en ese mismo `forceFill`: la clave de al lado re-precia correctamente el sello de
EDADES (`OrderItemEditor::sealUpdateFor()`). *La doctrina que esta sección pide ya está aplicada
justo al lado de la línea que la ignora.*

**(c) La configuración de un complemento vive en DOS tablas, no en una.** `SHOW COLUMNS` da 14
columnas en `product_addons`, y `extends_parent_stay`, `occupies_after_parent`, `duration_min` y
`seats_per_unit` **no están ahí**: son de `ticket_types`. **Sellar el enganche no las alcanza** —y
por eso `#448` no cierra la deuda de §12.15·3.

## 12.4 · El censo: qué configuración reinterpreta lo VENDIDO

De las 11 columnas de configuración del enganche (`TicketType::ADDON_PIVOT_COLUMNS`):

| columna | ¿toca una línea VENDIDA? | qué hace | ¿avisa? |
|---|---|---|---|
| **`quantity_mode`** | **SÍ — DINERO y AFORO** | `effectiveQuantity()` (`AddonResolver::effectiveQuantity()`) re-escala y cobra; `blocksFor()` (`AddonOccupancy::blocksFor()`) multiplica los minutos de sala | **NO. Silencio total** |
| `is_included` + `included_quantity` | SÍ — DINERO | `freeUnits()` (`AddonResolver::freeUnits()`) es todo-o-nada en `per_guest` | NO. **Sin candado hoy** |
| `choice_group` | SÍ — puede **CANCELAR** la línea | el mapa sale del pivote vivo y cancela al hermano de grupo | NO |
| `max_qty` | SÍ — **recorta** en post-form | `PostFormAddons::apply()` capa la cantidad deseada: un reenvío sin cambios reduce la línea | NO |
| `is_mandatory` · `allow_extra` · `requires_addon_id` | SÍ — **BLOQUEAN** la edición | `addon_locked` · `addon_no_extra` · `addon_requires_missing` | **SÍ, en pantalla** |
| `stage` | NO — tiene respuesta propia | su frontera es un HECHO: `LineFacts::birthValue() === 0` (`#413` D9) | — |
| `postform_cutoff_hours` | NO — mueve una ventana FUTURA | derecho comunicado, no unidad de medida | — |
| `position` | **NO. La única inerte** | `orderBy` y desempate, solo en ventas nuevas | — |

❗❗❗ **LA LÍNEA DIVISORIA, Y ES LA QUE DECIDE EL ALCANCE:**

> **`quantity_mode` es la ÚNICA columna que cambia lo que un número YA GUARDADO *significa*. Las
> demás cambian lo que te dejan *hacer a continuación* — y ésas fallan RUIDOSAMENTE, con un motivo
> en pantalla que detiene el guardado.**

Reinterpretar en silencio es un defecto de dinero y de aforo. Bloquear es de usabilidad. **Son dos
problemas y no se arreglan con el mismo mecanismo** — por eso `is_included` queda fuera (§12.15·1).

## 12.5 · El diseño — `order_items.addon_quantity_mode`

Una columna escalar, **no un JSON**. El precedente `age_family_seal` (`#288`) congela *precios que
el catálogo mueve solos*; aquí solo hay **una unidad de medida**, y un documento invita a meter
dentro cosas que no cambian de significado.

```
$table->string('addon_quantity_mode', 20)->nullable()->after('free_quantity');
```

- **`varchar(20)`**, como `product_addons.stage` — no `string` a secas, que es lo que tiene
  `quantity_mode` (`varchar(255)`, sin lista cerrada).
- ⚠️⚠️ **NULLABLE y SIN DEFAULT, y no es preferencia**: `null` tiene que significar **silencio**, y
  hay dos poblaciones legítimas — los **portadores de fiesta mixta** (§12.8) y las líneas anteriores
  al mecanismo. Un `default('fixed')` **afirmaría un modo que nadie midió**.
- Sin índice. `down()` = `dropColumn`, como los dos precedentes de esta misma tabla.
- **NOT NULL descartado a sabiendas**: hay ficheros de `tests/` que fabrican hijas a mano saltándose
  las cinco puertas. La compensación es que los casos del sello compren **por la puerta real**, que
  es como se probó `age_family_seal`.

**El vocabulario va en lista cerrada**, hermano de `saleStage()`:

```
ProductAddon::MODES = [MODE_FIXED, MODE_PER_GUEST];
ProductAddon::quantityUnit(): string   // sanea lo desconocido a MODE_FIXED
```

⚠️ **`quantityUnit()` y no `quantityMode()`**: un método homónimo de una columna hace que Eloquent
lo tome por relación. ⚠️ Hoy `isPerGuest()` compara la cadena cruda (`:65-67`), así que un valor
torcido por `Query\Builder::update()` se lee como `fixed` **en silencio** — y `fixed` es justo el
lado que multiplica los minutos de sala.

## 12.6 · Quién ESCRIBE — CINCO puertas, y solo tres sellan

Censo verificado (`children()->create` sobre `app/`). **Son cinco, no tres**: `ESTADO.md` se
quedaba corto.

| puerta | dónde | qué |
|---|---|---|
| 1 · la venta | `AddonResolver::resolve()` → persiste `OrderCreator::create()` | una clave más en el array. Cubre web, API y **mostrador** (`ManualOrderFulfiller` → `OrderCreator`). Ya dentro del lock de zona/día |
| 2 · el editor | `OrderItemEditor::edit()` | **el valor NO sale de `$offeredAddons`: ver §12.6.1** |
| 3 · el post-form | `PostFormAddons::write()` | siempre `fixed` (`per_guest` está prohibido en esa fase). Se sella igual: su caso es de **CONTROL** |
| 4 y 5 · fiesta mixta | `MixedPartySurcharge::apply()` y `::applyCredit()` | **NO se sellan, y va escrito junto al `create()`** |

### 12.6.1 · La puerta 2: el sello sale de `ItemEditPricing`, no de `$offeredAddons`

En una sola llamada a `edit()` hay **tres lecturas distintas** de `product_addons`, y solo una está
dentro de la transacción. Sellar desde `$offeredAddons` ataría el sello a una lectura *autocommit*
separada de la que define la cantidad por trabajo de validación y por una espera de lock de hasta
50 s.

▶ **`ItemEditPricing::computeAddonPricing()` devuelve `add_quantity_modes[$typeId]` desde el MISMO
`$pivot`** que ya alimenta `add_quantities` y `add_free_quantities`
— cero consultas nuevas, y el sello queda atado **por construcción** a la cantidad que
describe.
⚠️ Sus **dos salidas tempranas** (`invalid_product` y `addon_unavailable_on_date`)
enumeran el contrato a mano: la clave tiene que ir en las dos, y hay guarda.
⚠️ `ItemEditPricing` **sigue fuera del `CRITICAL_RE`**: devuelve un valor, no escribe.

## 12.7 · Quién LEE — TRES sitios, y ninguno más

1. **DINERO** — `OrderItemEditor::edit()` (el bloque `addon_per_guest_rescale`), con su
   `recordEdit(±Δ, 'addon_per_guest_rescale')`.
2. **AFORO** — `OrderItemEditor::resultingStayMinutes()` → `AddonOccupancy::extraMinutes()` → `blocksFor()`,
   materializado en el `extra_minutes` del padre.
3. **PERMISO** — `OrderItemEditor::childAddonMeta()`, que gobierna `addon_locked` y el modal «Gestionar».

▶ **El lector vive en `AddonResolver::soldQuantityUnit(OrderItem $child): ?string`**, y **no** en un
método de `OrderItem`: `AddonResolver` está en el `CRITICAL_RE` y `OrderItem` **no**. La única
decisión que traduce el sello a conducta —y que gobierna dinero y aforo a la vez— tiene que estar
gateada.

⚠️ **Todo lo demás es OFERTA y debe seguir leyendo el catálogo de hoy**: `AddonResolver::resolve()`,
`CartOccupants`, `AddonOfferReader`, `CreateManualOrderPage`, el catálogo público. **Un sello que
gobernara la oferta congelaría el escaparate.**

## 12.8 · Qué pasa al faltar el sello — TRES estados, no dos

| estado | conducta | por qué |
|---|---|---|
| **ausente** (`null`) | **el pivote VIVO — exactamente la conducta de hoy** | Nunca un `?? MODE_FIXED`: **no hay lado seguro único** —para AFORO lo conservador es tratarla como bloques; para DINERO es lo contrario—, así que cualquier default es un defecto en una de las dos mitades. ▶ **De aquí sale la regla que ata la tanda: mientras existan hijas vivas sin sello, el candado no se puede retirar** |
| **sello == pivote vivo** | idéntico a hoy, sin ramas nuevas | el caso normal mientras nadie cambie un modo |
| **sello ≠ pivote vivo** | **manda el sello para el MODO, y los topes e inclusiones del pivote NO se aplican a esa línea**: el máximo cae a su cantidad actual (se puede bajar o quitar, no subir) y el re-escalado conserva su propia proporción de gratis | ver abajo |

❗❗ **Por qué la tercera fila es obligatoria.** Dejar el tope en el pivote vivo crearía un estado que
**hoy no existe en ninguna configuración**: al pasar un enganche a `per_guest` el panel **borra el
tope** (`AddonsRelationManager`, «el tope solo aplica a cantidad fija»), así que una hija sellada
`fixed` bajo un enganche `per_guest` saldría **editable y sin techo**. Hoy o está acotada por
`max_qty` o está congelada por `locked`.
▶ `max_qty` **no es una regla independiente: es la mitad del modo** — el propio panel la borra al
cambiarlo. Partirlos entre sello y catálogo produce una configuración que el dominio nunca acepta
escribir.

▶ **Y la divergencia, al ser detectable, se PINTA**: nota en la línea ↳ de la ficha del pedido, con
el molde de `$mixStale`/`$mixOrphaned` de `items-list.blade.php`. **Al cliente no llega nada**:
`OrderItemAddon` (`openapi/v1.yaml`) es `additionalProperties: false` y no publica el modo. **El
contrato no se toca.**

⚠️ **Los portadores de fiesta mixta quedan `null` a propósito, y va escrito junto a su `create()`**:
su producto sale de un `Setting` y no tiene fila en `product_addons`, así que `null` significa aquí
«no la gobierna ningún enganche». **Sin ese comentario, el siguiente lo toma por olvido.** Medido:
la única huérfana viva de producción (línea #31, «Descuento fiesta mixta» bajo una fiesta ya
celebrada) es exactamente este caso — *el dato confirma el diseño en vez de contradecirlo*.

## 12.9 · El RELLENO, con su conjetura DECLARADA

**El modo NO es recuperable de la fila.** Medido: `free_quantity == quantity` no distingue nada (un
`fixed` incluido da la misma firma que un `per_guest` incluido); `seats` es **0 en las 27 hijas
vivas**; `extra_minutes` vive en el PADRE y es una **suma** sobre la familia. **No hay derivador**:
la única fuente posible es el pivote de hoy.

❗❗❗ **Y por eso el relleno NO puede ser general.** Rellenar todo desde el pivote vivo convertiría un
error de configuración **hoy autocorregible** (los tres lectores leen el catálogo, así que arreglar
el enganche arregla las líneas) en un **hecho inmutable**. Y sobre la línea de §12.10 escribiría
`per_guest`, que es **falso**.

▶ **Se rellena SOLO donde la conjetura es demostrable: los EXTENSORES** (`#121` y `#122`, **una hija
viva cada uno: dos líneas**). Su prueba no es el candado —que nació el 08— sino el **rastro**: las
4 filas de `audit_logs` tocan `108` y `110`, **nunca `121` ni `122`**. A las horas extra de sala
**nadie les ha cambiado el modo jamás**, así que `fixed` es un hecho copiado, no adivinado.
⚠️ **Con su límite dicho**: los eventos de Eloquent no ven `Query\Builder::update()`, y
`ProductionSeeder` escribe el pivote exactamente por esa puerta — el rastro cubre el panel, no el
seeder.

▶ **Todo lo demás se queda en `null` = silencio**, que es la conducta de hoy: **cero regresión**, y
el error de configuración sigue siendo corregible desde el panel.

**Forma de la migración**: ALTER + relleno en el mismo `up()`, autocontenido (sin clases de la app),
idempotente, y **en PHP con `chunkById`, no en un `UPDATE … JOIN`** — no porque MySQL lo prohíba,
sino porque **la suite corre en SQLite** y esa forma no es portable.
⚠️ El JOIN va contra `product_addons` **en crudo**, no contra `TicketType::addons()`, que filtra
`is_sellable`+`is_active` y dejaría fuera un complemento despublicado cuya fila de pivote sigue ahí.
**Evidencia de despliegue: dos números** — filas selladas, y **cuántas hijas vivas quedan sin sello**
(que es exactamente el conjunto que el candado sigue protegiendo).

## 12.10 · La línea de `R-BOMAZH` — la corrección de dato

❗❗❗ **EL DAÑO QUE ESTA SECCIÓN PREVIENE YA OCURRIÓ, y el rastro lo fecha entero.**

| | |
|---|---|
| línea hija **#5** · «Menú 2» | creada **2026-09-01 16:18**, `qty = 1` a 2,00 € |
| enganche **#37** (Pack Jump → Menú 2) | pasado a `per_guest` el **2026-09-06 10:49**, usuario 33 |
| el candado de `#443` | desplegado el **2026-09-08** |

Pedido **`R-BOMAZH`** (273,15 €, `paid`), Pack Cumpleaños Jump de **17 invitados**, fiesta el
**21/09 a las 17:00**, sin celebrar. Esa línea se vendió como *un menú extra* y hoy vive bajo un
enganche que dice *uno por invitado*: **en la primera edición de cantidad pasaría a 17 × 2,00 € =
34,00 €, o sea +32,00 € que nadie vendió**.

⚠️ **Es la ÚNICA**, y hay control: el barrido de las 27 hijas vivas da **1 descuadre**. Las otras
seis hijas `per_guest` nacieron *después* del cambio de modo y están coherentes.

▶ `[DECIDIDO owner, 2026-09-08]`: **se le escribe `fixed`** — que es lo que de verdad se vendió, y
las fechas lo demuestran. Va **en la migración de relleno**, nominada y con su motivo, no como un
`UPDATE` suelto a mano.
⚠️ **Y se declara como lo que es: una corrección de dato de un cliente real, con nombre y fecha**,
no un efecto colateral del relleno.

## 12.11 · El candado: se RE-APUNTA, no se retira

`hasEditableSoldLines()` pasa de **«¿hay líneas vivas editables?»** a **«¿hay líneas vivas editables
SIN SELLO?»**. Ese conjunto **solo encoge**, porque toda venta nueva nace sellada:

- **las dos horas extra de sala se desbloquean el día del despliegue** (sus dos líneas quedan
  selladas por el relleno);
- el resto se desbloquea **solo**, cuando pasen las fiestas vendidas antes del despliegue — días,
  no «nunca», y ya no vuelve a cerrarse jamás;
- **no hay ni un instante sin defensa**: el candado muere por vaciamiento, no por decreto.

**En el MISMO commit que toca el guard, `ProductAddon` entra en el gate.** Verificado ejecutando el
`CRITICAL_RE` real: hoy `app/Domain/Booking/Models/ProductAddon.php` **sale libre**, y tampoco
figura en las listas de `CriticalPathGateTest` — no está en ninguna. Van **los dos sitios a la vez**
(el propio test declara, medido por mutación, que añadirlo solo al regex deja el gate sin red).
Cierra la ficha de `DEUDA.md` sobre este fichero.

### Lo que el sello cierra y el candado nunca cerró

1. `updateExistingPivot` desde «Configurar» — lo único que el candado ve.
2. **Detach + Attach**: el guard sale por su primera línea si `! $pivot->exists`, y `DetachAction`
   no consulta ventas.
3. **Seeder / SQL crudo**: los eventos de Eloquent no ven `Query\Builder::update()`.
4. **Despublicar el complemento**: `addons()` filtra `is_sellable`+`is_active`, así que la hija se
   queda sin pivote y cae al fallback de bloques.
5. ⚠️⚠️ **Y un punto ciego propio, medido**: el candado filtra `cancelled_at` pero **no mira el
   estado del PEDIDO**, mientras el editor sí lo exige (`HasItemActionGuards::editItemBlockedReason()`,
   `order_not_operational`). **Un carrito abandonado en `pending` cierra el candado sobre una línea
   que el editor jamás podrá tocar** — y `ExpireOrders` solo escribe `orders.status`, así que esas
   hijas conservan `cancelled_at` nulo para siempre. Hoy no muerde en producción (las 27 hijas vivas
   están en pedidos `paid`), pero el docblock que afirma que alcanza «exactamente a las líneas que
   el re-escalado puede tocar, ni una más» **es falso** y se corrige.

▶ **De paso se corrige un comentario falso**: el comentario de `OrderItemEditor::resultingStayMinutes()` declara inalcanzable
la rama sin pivote «porque el guardado ya está bloqueado por `orphan_addons`». `orphan_addons` solo
se devuelve **dentro de `if ($productChanged)`**: cualquier edición que no cambie de producto llega
ahí. *Un comentario que declara cerrado un camino abierto es peor que no tenerlo.*

## 12.12 · Las decisiones del owner (`[DECIDIDO owner, 2026-09-08]`)

| # | decisión | tomada | consecuencia asumida |
|---|---|---|---|
| **D1** | **Se sellan TODOS los complementos**, no solo los extensores | sí | hacerlo solo para extensores sale **más caro**: obliga a mantener los dos caminos en los tres lectores y devuelve a `null` dos significados incompatibles. Coste marginal de sellarlos todos: **cero líneas** |
| **D2** | **El relleno cubre SOLO los extensores** (2 líneas) | sí | lo demás queda `null` = conducta de hoy. Se renuncia a sellar el corpus a cambio de **no fabricar ni un hecho falso** |
| **D3** | **La línea de `R-BOMAZH` se sella como `fixed`** | sí | corrección de dato de un cliente real, nominada en la migración. Cierra los +32,00 € latentes |
| **D4** | **El candado se RE-APUNTA, no se retira** | sí | las dos horas extra se desbloquean el día del despliegue; el resto, solo. Sin ventana sin defensa |
| **D5** | **Spec antes del código** (esta sección) | sí | hay una conjetura de relleno y una corrección de dato de un cliente: las dos quedan escritas **antes** de tocarlas |

▶ **El puente, si hiciera falta antes**: crear **un complemento nuevo** ya en `per_guest` y
engancharlo al pack. Verificado sobre los datos de hoy: **no toca ninguna línea vendida**, y el
`unique(product_id, addon_id)` permite tener los dos colgando. ⚠️ **No desenganchar el viejo**: el
detach afloja el tope de las líneas vivas y las manda al fallback de bloques. **No sustituye a la
tanda.**

## 12.13 · Impacto en invariantes

- **`PAY-19` se AMPLÍA**: hoy dice que una fiesta conserva las condiciones con las que se vendió y lo
  ejerce sobre `age_family_seal`. Pasa a cubrir también **la unidad de cantidad de cada complemento**
  (`order_items.addon_quantity_mode`), con las mismas dos reglas derivadas: **producto nuevo → sello
  nuevo** (otros enganches) y **día nuevo → el MISMO sello** (la fecha re-precia, `PAY-18`, pero no
  cambia la unidad).
- **`AFORO-01`/`AFORO-06`**: la mitad de aforo de §12.3(a) queda dentro de la regla; los minutos del
  padre pasan a derivarse de un hecho, no del catálogo.
- **`SEC-04`**: el candado re-apuntado sigue siendo **de dominio**, no de formulario.

## 12.14 · Plan por tandas

| tanda | qué | `CRITICAL_RE` | verificadores |
|---|---|---|---|
| ✅ **T1** | **HECHA** (§12.16). **El hecho, sin leerlo.** Migración (columna + relleno de extensores + la línea de `R-BOMAZH`) · `MODES` + `quantityUnit()` · escritura en las tres puertas · `add_quantity_modes` en `ItemEditPricing` con sus dos salidas · el comentario del `null` en los dos portadores. **Ninguna lectura cambia: la suite sale verde sin tocar un caso** | **SÍ** (`AddonResolver`, `OrderCreator`, `OrderItemEditor`, `PostFormAddons`, `MixedPartySurcharge`) | `VERIFY_CONC=1` · `purchase:verify-oversell` (`stay-extension`, `guest-count`, `panel-edit`, `extra-hour`) · `postform:verify-concurrency` (`addons`, `cross`) · `mixed-party:verify-concurrency` |
| ✅ **T2** | **HECHA** (§12.17). **Las tres lecturas + la regla de divergencia.** `AddonResolver::soldQuantityUnit()` · las tres sustituciones (el re-escalado de `edit()`, `resultingStayMinutes()` y `childAddonMeta()`) · retirada del fallback y reescritura de su comentario falso · **guarda de lista cerrada** de llamantes de `isPerGuest()`, en dos listas declaradas (OFERTA, que puede crecer; LÍNEA VENDIDA, que tiene que ser **cero**), con el molde de `LedgerSingleSourceTest` | **SÍ** (`AddonOccupancy`, `AddonResolver`, `OrderItemEditor`) | ídem |
| **T3** | **El candado re-apuntado** + `ProductAddon` en el hook **y** en `CriticalPathGateTest` + la nota de divergencia en `items-list.blade.php` con sus claves `es`/`zh_CN` | **SÍ** (`ProductAddon` entra aquí) | ídem |
| **T4** | **Doc + OJO del owner**: `PAY-19` · `MODELO-DATOS.md` · `GLOSARIO.md` · la fila de `CLAUDE.md` · `DECISIONES #448` · `DEUDA` (cerrar la de `ProductAddon`, abrir las de §12.15) | no | — |
| **arnés** | `scripts/mutar-sello-modo.sh`. **Deben morder**: `resolve()` deja de sellar · el re-escalado vuelve al pivote · `resultingStayMinutes` vuelve al pivote (**900 vs 60**) · `childAddonMeta` vuelve al pivote · el saneo devuelve `per_guest` ante lo desconocido · el sello se lee de `$offeredAddons` en vez de `ItemEditPricing` · la puerta 2 no sella. **Dos CONTROLES declarados**: el post-form sella siempre `fixed`, y los portadores quedan `null` —esta segunda **no muerde por conducta** y hay que decir que es una aserción de intención | | |

⚠️⚠️ **El caso que hoy no siembra ni la suite ni el verificador**: una hija sellada `fixed` bajo un
enganche `per_guest`. Es el **único sujeto** de la regla de divergencia de §12.8, y hay que
fabricarlo a mano. **Una guarda que no lo siembre nace ciega.**

## 12.15 · Lo que NO entra — deuda declarada

1. **`is_included` es el mismo daño por otra columna y hoy NO tiene candado ninguno.** Queda fuera a
   propósito: meterlo obliga a decidir qué pasa cuando el parque legítimamente deja de incluir algo,
   que es una decisión de negocio distinta. **Ficha propia.**
2. **`max_qty` recorta en el post-form** (`PostFormAddons::apply()` capa la cantidad deseada del cliente
   contra el tope vivo): bajar el tope hace que un reenvío sin cambios **reduzca** la línea y mueva
   dinero. El arreglo no es sellar el tope: es que **un techo no pueda REDUCIR una línea ya
   vendida** — la regla R1 del propio reconciliador aplicada al techo. **Ficha propia.**
3. **`liveStayExtendingChildren()` reconoce a las extensoras por el catálogo VIVO**
   (`OrderItemEditor::liveStayExtendingChildren()`) mientras su hermana `liveOccupyingChildren()` lo hace por HECHOS de
   la fila y su docblock invoca «la doctrina del sello». **La doctrina está aplicada a la mitad** — y
   por eso `extends_parent_stay` necesita un cerrojo permanente que no se abre nunca. Lo mismo vale
   para `duration_min` y `seats_per_unit`, que además **viven en `ticket_types`** (§12.3·c) y este
   sello no los alcanza. **Es la siguiente pared. Ficha propia.**
4. ⚠️⚠️ **`GuestCountAdjuster` NO re-escala las hijas por-invitado, y `invitados-en-post-form.md`
   afirma dos veces que sí.** Verificado: el fichero no menciona `isPerGuest`, ni `AddonResolver`, ni
   `free_quantity`; su única escritura toca el PADRE. **Hoy no muerde porque ningún extensor es
   `per_guest` — y se activa EXACTAMENTE con el cambio que el owner quiere hacer**: un cliente que
   suba de 15 a 20 invitados desde el post-form se quedaría con la hora extra cobrada a 15, y el
   desfase lo absorbería el siguiente `recordEdit` del operador como si fuera suyo. `GuestCountTest`
   no tiene ni un caso con complemento hijo. **Ficha propia, y conviene cerrarla ANTES de configurar
   el modo nuevo.**

## 12.16 · Lo EJECUTADO — la T1 (`#448`, 2026-09-08)

**El hecho se escribe y no lo lee nadie todavía**, que es exactamente el contrato de esta tanda:
**la suite entera salió verde sin tocar un solo caso existente** (4.445 → 4.460, los 15 nuevos).

| pieza | dónde |
|---|---|
| la columna + los dos rellenos | `2026_09_08_120000_add_addon_quantity_mode_to_order_items.php` |
| el vocabulario | `ProductAddon::MODES` + `ProductAddon::quantityUnit()`; `isPerGuest()` delega |
| puerta 1 · la venta | `AddonResolver::resolve()` (cubre web, API y mostrador) |
| puerta 2 · el editor | `ItemEditPricing::computeAddonPricing()` → `OrderItemEditor::edit()` |
| puerta 3 · el post-form | `PostFormAddons::write()` |
| los dos silencios | `MixedPartySurcharge::apply()` y `::applyCredit()`, con el motivo escrito junto al `create()` |
| la red | `AddonQuantityModeSealTest` (10) · `AddonQuantityModeBackfillTest` (6) · +1 en `PostFormAddonsTest` · +1 aserción en `MixedPartySurchargeTest` |
| el arnés | `scripts/mutar-sello-modo.sh` — **14/14 muerden** |

**Verificación empírica**: suite **4.462** · mutación **14/14** · y los **ocho** verificadores de
concurrencia sobre InnoDB real (`purchase:verify-oversell` en `stay-extension`, `guest-count`,
`panel-edit` y `extra-hour`; `postform:verify-concurrency` en `addons` y `cross`;
`mixed-party:verify-concurrency` en `charge` y `credit`).

### Lo que enseñó la ejecución

⚠️⚠️ **Una mutación no mordió, y el hueco era del CASO, no del código.** «El relleno A sella todo
complemento, no solo los extensores» pasaba en VERDE porque el control usaba **solo** una tarta: sin
ningún extensor en el escenario, `backfillStayExtensions()` sale por su `return` temprano y nunca
llega a la línea mutada. *Un control sin el sujeto de la regla no controla nada* — es la lección de
§11.12 por otra puerta. El caso pasó a tener los dos complementos conviviendo, y muerde.

⚠️⚠️ **El verificador `extra-hour` se paró con su GUARDA DE INSTRUMENTO en vez de dar un falso
verde**: la BD MySQL de desarrollo no tenía la columna (la suite corre en SQLite y crea el esquema
desde cero, así que la migración no se había aplicado allí). Dijo «la compra con hora extra no se
pudo crear ni una vez» con el `SQLSTATE` entero. *Sin esa guarda, el escenario habría contado cero
compras con éxito y algún día alguien lo habría leído como «no sobrevende».*

⚠️ **Un fixture pedía un estado que el dominio prohíbe**: un extensor sin `max_qty`. Se **legalizó el
fixture**, no se excepcionó el guard — un caso que necesita una configuración imposible prueba un
mundo que no existe (la lección de `#299`).

⚠️ **La firma de `OrderItemEditor::edit()` tiene once parámetros y no se adivina**: el helper del
caso se copió del que `#447` ya había dejado correcto, incluida la relectura del testigo optimista
—que es de la RESERVA y no del PEDIDO, con precisión de SEGUNDO—. Y su aserto **imprime el motivo**:
un `assertFalse` mudo aquí costó cuatro rojos intermitentes.

▶ **Lo que el arnés NO puede mutar, y se dice**: que el sello del editor salga de `ItemEditPricing` y
no de `$offeredAddons` (§12.6.1). Con el catálogo quieto las dos lecturas devuelven el mismo pivote,
así que la diferencia solo aparece bajo una carrera y **no hay mutación mecánica que la distinga**.
Lo sostiene la revisión, no un caso.

### El CENSO DE PUERTAS, y lo que encontró

Cerrado antes de empezar la T2 a propósito: si faltara una puerta por sellar, la T2 leería un sello
que a veces no existe, y eso cae por el camino `null` → «lee el catálogo». *El agujero se volvería
invisible justo cuando empieza a mover dinero y aforo.*

▶ **Creación: CINCO, las declaradas** (barrido de `children()->create|createMany|save` y
`'parent_item_id' =>` sobre `app/`, `database/` y `routes/`; las dos de `VerifyPurchaseConcurrency`
son `parent_item_id => null`, o sea líneas PADRE). **Mutación de la cantidad de una hija: TRES**, las
tres en clases que ya sellan — y mover la cantidad **no cambia la unidad**, así que el sello
sobrevive correcto por construcción. **Escritores del sello: TRES**, exactamente los previstos.

❗❗❗ **Pero el censo encontró un hueco REAL, y era de la T1: al CAMBIAR DE PRODUCTO no se
re-sellaba.** Una hija cuyo complemento cuelga **también** del producto nuevo **sobrevive** al cambio
—`orphanAddonsForNewProduct()` solo bloquea las que no cuelgan— y desde ese instante la gobierna
**otra fila de `product_addons`**, independiente y con su propio `quantity_mode`. El re-escalado ya
lo asumía (lee `$newType->addons()`), así que el sello se quedaba describiendo un enganche que ya no
la gobernaba.

▶ Es `PAY-19` con el precedente al lado: **`sealUpdateFor()` re-sella `age_family_seal` en ese mismo
punto** («producto nuevo → sello nuevo»), y el sello del modo no tenía equivalente. Corregido dentro
del lock y desde el mismo pivote que gobierna el re-escalado, **fuera del `if ($newQty !== $oldQty)`**
porque cambiar de producto conservando la cantidad es un camino normal.

⚠️⚠️ **Y el CONTROL de ese arreglo nació faltando, lo dijo el arnés**: la mutación «re-sella aunque
el producto NO cambie» **no mordía**, porque no había caso que probara la propiedad central de toda
la feature — que una edición corriente **no pisa** el sello con el modo de hoy. Sin él, «re-sellar
siempre» habría pasado en verde, y eso es exactamente el daño que el sello existe para evitar.
*Una mutación que no muerde señala un hueco en la red tan a menudo como un fallo del instrumento.*

❗❗❗ **Y LA TANDA DEJÓ UN HALLAZGO QUE NO ES DE ESTA FEATURE: EL ARNÉS PUEDE DEJAR EL ÁRBOL MUTADO.**
El molde de la casa —`mktemp -d` + `trap … EXIT`— **no restaura si el proceso muere sin ejecutar el
trap**. Pasó de verdad aquí: una ejecución quedó a medias y `PostFormAddons` e `ItemEditPricing` se
quedaron **con la mutación puesta en el árbol de trabajo**, con la copia buena en un temporal que ya
nadie sabía encontrar. Lo cazó la suite completa con **1 fallo de 4.460**, y solo porque se volvió a
correr entera antes de commitear.

▶ `mutar-sello-modo.sh` queda **endurecido y sirve de molde**: copia en **ruta fija y gitignorada**
(`storage/app/mutaciones/<arnés>/`), **reparación al ARRANCAR** —si encuentra una copia huérfana,
restaura y lo dice—, `trap` también en `INT`/`TERM`, y **comprobación final de integridad** que sale
con código ≠ 0 si algún fichero quedó distinto del original. ⚠️ **La reparación se verificó con el
fallo REAL puesto** (corte simulado → avisa y repara), que es la regla de la casa: *una guarda no se
sabe si sirve hasta que se ejerce con el defecto que la motivó*.
⚠️ **Los otros 13 arneses con el mismo patrón siguen expuestos** (medido: 14 de 22): ficha en
`DEUDA.md`, y es tanda propia porque toca ficheros de otros carriles.

## 12.17 · Lo EJECUTADO — la T2: las TRES lecturas (`#448`, 2026-09-08)

**La tanda que cambia la conducta.** Los tres únicos sitios que preguntaban al catálogo por la unidad
de una línea YA VENDIDA se lo preguntan a la línea.

| pieza | dónde |
|---|---|
| el lector, y la regla en UN sitio | `AddonResolver::soldQuantityUnit()` + `wasSoldPerGuest()` |
| DINERO | el re-escalado de `OrderItemEditor::edit()` |
| AFORO | `OrderItemEditor::resultingStayMinutes()` → `AddonOccupancy::minutesForUnit()` |
| PERMISO + divergencia | `OrderItemEditor::childAddonMeta()` |
| la guarda de censo | `SoldLineUnitHasOneSourceTest` (dos listas con reglas distintas) |
| la red | `AddonQuantityModeReadsTest` (6 casos, cada uno con su control) |

**Verificación**: suite **4.470** · mutación **20/20** · `panel-edit`, `extra-hour`, `stay-extension`
y `guest-count` sobre InnoDB.

▶ **El patrón que ordena la tanda: la REGLA es una; de dónde sale el dato, no.** Por eso cada
derivación gana una variante que recibe la unidad ya resuelta —`blocksForUnit`, `minutesForUnit`,
`effectiveQuantityForUnit`, `freeUnitsForUnit`— y la de siempre delega pasándole el pivote. Sin ese
corte habría que elegir UN origen para los dos usos, y **cualquiera de los dos elegidos es un
defecto**: con el catálogo, cambiarlo re-alarga fiestas vendidas; con el sello, el escaparate se
congela y un cliente ve para siempre las condiciones con las que compró otro.

### ❗❗❗ Lo que enseñó la ejecución

⚠️⚠️ **Decidir CON el sello y calcular CON el catálogo es peor que no sellar, y lo cazó su propio
caso.** La primera versión preguntaba al sello *si* la línea sigue a los invitados y luego pedía la
cantidad a `effectiveQuantity($pivot, 0, …)`: con el enganche ya en `fixed` y cantidad pedida 0, eso
devuelve **0** — la línea se quedaba vacía. *Una regla partida entre dos fuentes no es media regla:
es un defecto nuevo.* De ahí salen `effectiveQuantityForUnit` y `freeUnitsForUnit`.

⚠️⚠️ **La guarda de censo nació IMPRECISA y lo demostró acusando a tres ficheros sanos**: buscaba
`isPerGuest()` y `quantityUnit()` juntos, pero son preguntas distintas — el primero **decide** sobre
una línea, el segundo **copia la unidad** para sellarla. Van en dos listas con reglas propias: OFERTA
puede crecer; SELLADORES es cerrada. *Buscar un nombre no es buscar un uso.*

⚠️ **El comentario falso de `resultingStayMinutes` está corregido**: declaraba inalcanzable la rama
sin pivote «porque el guardado ya está bloqueado por `orphan_addons`», y `orphan_addons` solo se
devuelve **dentro de `if ($productChanged)`**. Cualquier edición que no cambie de producto llegaba
ahí. *Un comentario que declara cerrado un camino abierto es peor que no tenerlo*, porque el
siguiente borra la rama por muerta.

▶ **El caso que da sentido a la tanda, con su control**: una hora extra vendida por invitados a 15
niños son **60 minutos** de sala; con el enganche volteado a `fixed` y sin sello, la misma línea pide
**900**. Los dos casos están escritos, y el segundo es el que demuestra que lo arregla el sello y no
otra cosa.
