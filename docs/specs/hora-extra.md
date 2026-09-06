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

| # | Qué | Por qué en este orden |
|---|---|---|
| T1 | El eje y sus guardas (`extends_parent_stay`, migración, guards en las dos direcciones, `AddonResolver`) | sin él no hay nada que enganchar, y las guardas son lo que impide que el hueco de §10.1 entre por la puerta de atrás |
| T2 | `extra_minutes` + los **dos** mapas de ocupación + `CartOccupants` + `OrderCreator` bajo lock | el núcleo de aforo; **no se toca ninguna superficie hasta que esto cierre** |
| **T2bis** | **`isFinishedInPractice()` pasa a la duración EFECTIVA** (decisión 4, cerrada) | ⚠️⚠️ **tanda PROPIA, y el motivo es que no es de esta feature**: ese predicado gobierna hoy TODAS las reservas, así que arreglarlo **cambia la conducta de fiestas que ya existen** —una de 2 h deja de darse por terminada una hora antes—, y con ella el post-form editable, el cierre de los extras y la **ventana de dinero** del suplemento mixto. Mezclarlo con la extensión haría imposible saber cuál de las dos cosas movió un número |
| T3 | La oferta (`AddonOfferReader`), el editor y el reconciliador de fechas | ya con el aforo diciendo la verdad |
| T4 | Las superficies: ventana mostrada, hoja de sala, puerta, correos | lo último, como en `#410` |

▶ **Verificación exigida** (no negociable, `INVARIANTES §6`): `PackAvailability`, `SlotAvailability`,
`CartOccupants`, `AddonResolver` y `OrderCreator` están **todos** en el `CRITICAL_RE`, así que cada
tanda va con `VERIFY_CONC=1` y **los siete escenarios** de `purchase:verify-oversell`. Y hace falta un
**octavo escenario**: la última plaza de sala disputada entre una fiesta nueva y **la extensión de la
fiesta anterior** — el equivalente de `extra-hour` para el pool de packs, visto FALLAR sin la
validación antes de darlo por bueno.
