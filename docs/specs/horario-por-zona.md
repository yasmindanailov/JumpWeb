# [SPEC] Horario POR ZONA — una zona puede operar fuera del horario del recinto

> Estado: 🟦 **EN EL ÁRBOL** (`#322`, 2026-09-01) — código, guardas y `VERIFY_CONC=1` hechos; queda el
> OJO del owner. Las tres decisiones del owner, tomadas (§7.1); la ejecución en §8.
> Caso que lo motiva: **excursiones de colegio** — vienen entre semana y por la mañana, que es
> cuando hay colegio, y el parque a esa hora puede estar cerrado.
> Tanda **A** de los descuentos por tramo (`P2`). La tanda **B** (precio por cantidad) es dinero y
> va en spec propia; ésta es AFORO.

---

## §0 · Antes de tocar

- **Es AFORO**: `SlotGenerator`, `SlotOffer` y `OrderCreator` están en el `CRITICAL_RE` → `VERIFY_CONC=1` y
  `purchase:verify-oversell`. En el árbol desde `#322`; queda el OJO del owner. Decisiones en §7.1, ejecución en §8.
- **Los consumidores del horario son TRES, no dos** (§4.4): `ProductAvailability::allowsStart()` lo consulta
  y por él pasan `SlotOffer`, `OrderCreator`, `OrderItemEditor` e `ItemRescheduleOffer`. Lo cazó una guarda, no
  una lectura: un `grep` del servicio deja fuera a quien pregunta por un tercero. Si retiras o añades un
  consumidor, lee §4.4 antes.
- **`OperatingSchedule::effectiveFor()` es LA CARA PÚBLICA** (landing, «Abierto ahora») y no se toca: la
  variante por zona es un método aparte, y la duplicación es deliberada.
- **Generar y podar leen la MISMA resolución**, o la poda cierra lo que el generador acaba de crear, con
  ventas dentro (`AFORO-04`).
- **Ignorar el cierre SIN declarar horas NO abre**: con el recinto cerrado no hay ventana que heredar, y el
  fallback histórico es «abierto sin restricción».
- **Lo que NO hay que construir**: `zones.max_per_slot` y `zones.max_guests_per_slot` ya existen y ya son por
  zona (§1.1); igual `min_qty`/`max_qty`, `duration_min` y la señal. El tope de grupos es configuración.
- **La excursión es un producto `pack`** (`[DECIDIDO owner]`): `min_qty` y el cupo de grupos solo funcionan
  siendo pack. La tanda B (precio por tramo) es DINERO y vive en `specs/precio-por-tramo.md`.
- El anexo del final conserva la fila del enrutador tal como estaba.

## 1. Contexto y problema

El cliente quiere vender **excursiones de colegio**: dos productos (2 h y 3 h), zona propia, grupos
de 30 a 100 personas. El owner preguntó literalmente: *«también debo añadir es fuera de horario y no
sé cómo lo haríamos, las franjas van con el horario normal ¿no?»*.

**Van con el horario normal, sí, y por eso hoy no se puede.** Medido:

1. **El horario es del RECINTO, no de la zona.** `OperatingSchedule::effectiveFor($fecha)` recibe
   **una fecha y nada más** — no hay parámetro de zona— y resuelve por prioridad
   `special_dates` → temporada → `opening_hours` semanal, devolviendo `{is_open, open, close}`.
2. **`SlotGenerator` descarta toda plantilla que no quepa en esa ventana**
   (`SlotGenerator::generate()`): si el día no está abierto **no genera NADA para ninguna zona**, y
   si la plantilla empieza antes de `open` o acaba después de `close`, la salta.
3. **Con los datos reales del cliente eso deja la excursión sin franjas**: el parque abre
   **10:00–21:00** y **el martes está cerrado**. Una excursión a las 9:00, o cualquier excursión un
   martes, hoy **no existe como franja** y por tanto no se puede vender.

⚠️ **Y no basta con tocar el generador.** `ItemRescheduleOffer` —la oferta de RE-PROGRAMAR un ítem
ya comprado— también pregunta al horario del recinto (`isOpenOn()`, dos veces). Si solo se arregla
la generación, las franjas existirían pero **mover una excursión a un martes se rechazaría**, y sin
que nada falle: es la conversión a medias que este proyecto ya ha pagado en `#196`, `#211` y `#238`.

### 1.1 Lo que NO es el problema (medido, para que nadie lo rehaga)

- **El tope de grupos por franja YA EXISTE y ya es por zona.** `zones.max_per_slot`,
  `zones.max_guests_per_slot` y `zones.prep_blocks_cupo` son columnas nulables que
  `PackAvailability::maxPartiesPerSlot()` consulta **antes** de caer al ajuste global
  (`packs.max_per_slot`). O sea que «1 excursión por franja, como mucho 2» es **configuración**.
  ▶ Lo destapó el owner preguntando «pero cumpleaños no tiene una opción así?». La tenía.
- **`min_qty` / `max_qty` existen por producto** → el mínimo de 30 y el máximo de 100 son dato.
- **`duration_min` existe** → los productos de 2 h y 3 h son dato.
- **`deposit_type` / `deposit_value` existen** → la señal y el resto en el parque son dato.
- **Las tarifas de finde/festivo existen** (`RateType` `normal` / `special` con `weekdays`) → meter
  el viernes en `special` es una línea de configuración (`[DECIDIDO owner]`: el viernes pasa a
  especial **para todos los productos**).

**Lo único que falta de mecanismo en esta tanda es el horario por zona.**

---

## 2. Objetivo

Que **una zona pueda declarar su propia ventana horaria y operar en días en que el recinto está
cerrado**, sin que eso cambie ni un píxel de lo que la web pública dice sobre los horarios del
parque, y sin tocar la aritmética de aforo.

**Fuera de alcance a propósito:** el precio por tramo de cantidad (tanda B), el aforo por plazas
(no cambia) y el producto de excursiones en sí (es dato).

---

## 3. Opciones consideradas

| | Opción | Coste | Por qué no / sí |
|---|---|---|---|
| **A** | **Ampliar el horario del recinto** para que cubra las excursiones | Cero código | ❌ **La web mentiría**: diría que el parque abre a las 9:00 y que el martes está abierto. `HeroStatus`, «Abierto ahora» y los horarios publicados salen del mismo `OperatingCalendar`. Un dato falso de cara al público para resolver un problema interno. |
| **B** | **Horario por zona**, nulable, con el recinto como suelo | Una migración + `OperatingSchedule` + 2 consumidores | ✅ **Elegida.** Es el patrón que la tabla `zones` ya usa TRES veces (`max_per_slot`, `max_guests_per_slot`, `prep_blocks_cupo`): `null` = hereda, un valor = manda la zona. No hay que inventar la forma, hay que copiarla. |
| **C** | **Las excursiones sin franjas**, con fecha y hora libres | Medio | ❌ Rompe `AFORO-01`/`AFORO-02`: el cupo, el lock anti-sobreventa y la oferta única web↔panel↔API cuelgan de que exista una franja. Su aforo tendría que vivir en otro sitio y sería una segunda verdad. |
| **D** | Tabla `zone_opening_hours` espejo de `opening_hours` (por zona **y** por día) | Alto | ❌ **Innecesario hoy y no lo pide nadie.** La granularidad por día de la semana **ya existe en `slot_templates`** (`zona + weekday + hora`), que es lo que el horario CLIPA. La zona solo necesita decir qué ventana la acota, no repetir el calendario entero. Se puede añadir después sin romper B. |

---

## 4. Diseño elegido

### 4.1 Las tres columnas nuevas en `zones` (todas NULABLES)

| Columna | Tipo | Significado | Excursiones |
|---|---|---|---|
| `opens_at` | `time` nullable | Apertura propia de la zona. `null` = hereda la del recinto. | `08:00` |
| `closes_at` | `time` nullable | Cierre propio. `null` = hereda. | `15:00` |
| `ignores_venue_closure` | `boolean` default `false` | Si la zona opera los días en que el recinto está cerrado, **sea cual sea el motivo**. | `true` |

▶ **`null` = hereda** es el contrato, idéntico al de `max_per_slot`. Una instalación que no toque
nada se comporta **exactamente igual que hoy**: es una migración sin conducta nueva por defecto.

### 4.2 El cierre del recinto: la zona lo ignora ENTERO, y lo que eso cuesta

`[DECIDIDO owner, 2026-09-01]`: **la excursión puede caer en festivo.** Preguntado con la
consecuencia delante y contestado «sí».

⚠️ **Este apartado decía lo contrario y la decisión lo revierte, pero el hallazgo que lo motivó sigue
siendo cierto y conviene no perderlo**: `is_open === false` sale de **dos sitios distintos** —el
descanso semanal (`opening_hours.is_closed`, el martes del cliente) y una excepción de día
(`special_dates.is_closed`: festivo, Navidad, un cierre por obras)—. El borrador proponía que la
zona pudiera saltarse el primero y **nunca** el segundo, para que un parque cerrado por inspección no
vendiera una excursión.

▶ **El owner decide que la zona ignora los dos.** Por eso el interruptor pasa a llamarse
`ignores_venue_closure`: nombra lo que hace de verdad. La distinción semanal/excepción **no se
implementa** — implementarla y no usarla sería un mecanismo sin lector.

**⚠️ La consecuencia, dicha en alto: un cierre por obras NO cerrará la zona de excursiones solo.**
Marcar el día como cerrado en `special_dates` seguirá generando franjas de excursión.

**La salida existe y no hay que construirla**: el operador cierra esas franjas a mano desde el panel,
y `SlotGenerator` **respeta el cierre manual para siempre** (`online_sales_open`/`status` no se tocan
al regenerar — está en su docblock y es conducta ya probada). O sea que el escape es de una pasada y
sobrevive a la regeneración rodante.

▶ Queda anotado como lo que es: **una decisión, no un descuido**. Si algún día molesta, la salida
limpia son excepciones de día POR ZONA, que es la opción D de §3 y entra sin romper esto.

### 4.3 La resolución, en un solo sitio

`OperatingSchedule` gana **un método nuevo** y el existente **no se toca**:

```
effectiveFor($date)                → LA CARA PÚBLICA. Sin cambios. Es lo que ve la web.
effectiveForZone($date, ?Zone)     → la misma respuesta, con el override de la zona aplicado.
```

`effectiveForZone()` parte del resultado del recinto y aplica, en este orden:

1. Si el recinto está cerrado y la zona **no** declara `ignores_venue_closure` → cerrado.
2. La ventana: `zone->opens_at ?? recinto.open` y `zone->closes_at ?? recinto.close`, **cada extremo
   por separado** (una zona puede abrir antes y cerrar a la vez que el parque).
3. ⚠️ **Con el recinto cerrado, el recinto no aporta ventana** (`open`/`close` valen `null`): una
   zona que ignora el cierre y **no** declara la suya queda «abierta sin restricción», que es el
   fallback histórico de `effectiveFor()` y aquí sería un fallo silencioso — generaría todas sus
   plantillas del día. Es un caso REAL en cuanto alguien marque `ignores_venue_closure` sin poner
   horas, así que la resolución lo trata explícitamente y su guarda lo cubre.

⚠️ **`effectiveFor()` NO delega en `effectiveForZone($date, null)` por comodidad.** Son dos
preguntas distintas —«¿qué dice el parque?» y «¿qué puede hacer esta zona?»— y fundirlas es cómo un
día alguien pasa una zona por el camino público. La duplicación aquí es deliberada y va comentada.

### 4.4 Los consumidores que cambian — y la afirmación de esta sección que resultó FALSA

⚠️⚠️ **CORRECCIÓN, y va antes que el texto que corrige.** Este apartado decía «los DOS consumidores
que cambian, y por qué son exactamente dos», y afirmaba que **«`SlotOffer` y el checkout no consultan
el horario»**. **Es falso: lo consultan, transitivamente.**

**Son TRES, y el tercero es el que importa.** `ProductAvailability::allowsStart()` resuelve
`effectiveFor($fecha)` para exigir que la franja empiece dentro de la ventana del día, y por ahí
pasan **cuatro** superficies: `SlotOffer` (la oferta pública, `AFORO-02`), `OrderCreator` (el
checkout), `OrderItemEditor` (las ediciones del panel) e `ItemRescheduleOffer`.

▶ **Lo destapó una GUARDA, no una lectura.** El caso de re-programación se escribió, se ejecutó y
falló con el producto «arreglado»: las franjas se generaban y la oferta seguía sin admitirlas.
▶ La lección: **un `grep` de `OperatingSchedule` no encuentra a quien pregunta a través de un
tercero.** Buscar por el nombre del servicio da los consumidores DIRECTOS y deja fuera la cadena.

| Consumidor | Qué pregunta hoy | Qué pasa a preguntar |
|---|---|---|
| **`ProductAvailability::allowsStart()`** | `effectiveFor($fecha)` | `effectiveForZone($fecha, $product->zone)` — **el punto de estrangulamiento**: con esto, sus cuatro consumidores quedan cubiertos sin tocarlos. Parchearlos uno a uno habría dejado tres caminos con el horario viejo. |
| `SlotGenerator::generate()` | `effectiveFor($día)` una vez por día, FUERA del bucle | `effectiveForZone($día, $zona)` dentro del bucle, memoizado por `(zona, día)`. Que se resolviera fuera y con `is_open` a false se saltara el bucle entero era exactamente lo que dejaba a las excursiones sin ni una franja. |
| `ItemRescheduleOffer` | `isOpenOn($fecha)` (×2) | La variante por zona. Es un filtro de DÍA, distinto del de franja de `ProductAvailability`: hacen falta los dos, y tienen que coincidir o el calendario ofrece un día cuyas horas salen vacías. |

**Y los que NO cambian, a propósito:**

- **`OperatingCalendar`** (`windowFor`, `weeklyOpenings`, `activeSeasons`, `upcomingSpecialDays`) —
  es lo que consume Content para la landing, «Abierto ahora» y los horarios publicados. **Sigue
  siendo el recinto.** Es la mitad importante del diseño: si la zona se colara aquí, la web diría
  que el parque abre a las 8:00.
- **`SlotOffer`, `OrderCreator` y `OrderItemEditor`** — no se tocan, pero **no porque no pregunten**:
  porque preguntan por `ProductAvailability` y heredan el arreglo. Es lo contrario de lo que decía
  este apartado.
- **Las pantallas del panel** (`WeeklySchedule`, `SpecialDates`, `Seasons`) — configuran el recinto.

### 4.5 ⚠️ La poda, que es donde esto se rompe en silencio

`SlotGenerator::pruneDay()` **neutraliza las franjas que ya no caben en el horario**. Si la
generación usa la ventana de la zona y la poda usa la del recinto, el generador crearía la franja de
las 9:00 y la poda **la cerraría en la misma pasada** — y con ventas dentro la cerraría en vez de
borrarla (`AFORO-04`), dejando una excursión vendida en una franja cerrada.

▶ **Las dos mitades leen la MISMA resolución.** El `$wanted` que la poda recibe se construye dentro
del mismo bucle que genera, así que basta con que la ventana sea la de la zona **en el sitio donde
ya se calcula**. Es una línea, y es la línea que hay que mutar para probar que la guarda sirve.

---

## 5. Impacto en invariantes

| Invariante | Impacto | Por qué |
|---|---|---|
| **`AFORO-01`** (lock de franjas) | **Ninguno** | No cambia cómo se bloquea ni qué se cuenta: cambia **qué franjas existen**. El lock sigue siendo por `zone_id` literal, resuelto fuera de la transacción. |
| **`AFORO-02`** (oferta única) | **Ninguno** | `SlotOffer` no consulta el horario — verificado. Ofrece lo que existe. La paridad web↔panel↔API se conserva sola. |
| **`AFORO-03`** (regeneración rodante) | **Sí** | `generateRollingHorizon()` llama a `generate()`, así que hereda la resolución por zona. Hay que probarlo, no suponerlo. |
| **`AFORO-04`** (la poda re-verifica bajo lock) | **⚠️ EL MÁS EXPUESTO** | Ver §4.5. Una divergencia entre generar y podar cierra franjas con ventas dentro. |
| **`AFORO-08`** (ventanas clampadas a `[00:00, 24:00)`) | **Sí** | Una zona que abre a las 8:00 con montaje/limpieza puede empujar la aritmética a horas más tempranas. El clamp existe y hay que verificar que sigue. |
| **`AFORO-09`** (`DisplayTime`) | **Ninguno** | No se introduce ninguna fecha nueva. |
| **PAY-\*** | **Ninguno** | Esta tanda no toca dinero. El precio por tramo es la tanda B. |

**`CRITICAL_RE`**: `SlotGenerator` está **dentro** del gate del `pre-push`. ▶ Esta tanda exige
`VERIFY_CONC=1` y los **dos** verificadores, con los **seis** escenarios de `purchase:verify-oversell`
(`AFORO-01` avisa: *«un cupo correcto no dice nada de los demás»*).

---

## 6. Plan de verificación empírica

1. **Migración sin conducta nueva**: con las tres columnas a `null` en todas las zonas, la suite
   entera pasa sin tocar un solo test. Es la prueba de que `null = hereda` no miente.
2. **El caso del cliente, medido**: zona `excursiones` con `opens_at=08:00`,
   `ignores_weekly_closure=true`; se generan franjas **un martes a las 9:00** y **cero** para `jump`,
   `kids` y `cumpleanos` ese mismo martes.
3. **La web no se entera**: `OperatingCalendar::windowFor()` sigue diciendo *cerrado* ese martes, y
   `HeroStatus` sigue diciendo lo mismo que antes de la migración. Con CONTROL: se comprueba que la
   sonda detecta el cambio si se le pasa la zona a propósito.
4. **La poda no se come lo que el generador crea** (§4.5): dos pasadas seguidas de
   `generate(prune: true)` dejan el mismo número de franjas. Mutación: hacer que la poda use el
   horario del recinto → tiene que ponerse ROJO.
5. **Re-programar a un día cerrado del recinto** sí se ofrece para la zona de excursiones y **no**
   para las demás. Mutación: dejar `ItemRescheduleOffer` con `isOpenOn()` → ROJO.
6. **Una zona que ignora el cierre y NO declara horas no queda «abierta sin restricción»** (§4.3·3):
   es el fallo silencioso de este diseño y su guarda es la que hay que ver morir con la mutación.
7. **`VERIFY_CONC=1`** con los dos verificadores y los seis escenarios.

⚠️ **Cada guarda se muta con el fallo REAL que la motiva**, no con uno cualquiera: la lección de
`#196` («una guarda no se sabe si sirve hasta que se muta con el fallo que la motivó»).

---

## 7. Revisión y decisión

### 7.1 Las tres decisiones del owner — TOMADAS (`[DECIDIDO owner, 2026-09-01]`)

1. **La excursión SÍ puede caer en festivo.** La zona ignora el cierre del recinto entero, sea cual
   sea el motivo (§4.2, con su consecuencia declarada y su salida por cierre manual de franja).
2. **La zona SÍ necesita ventana propia** — *«correcto, podrían venir más temprano»*. Las tres
   columnas entran; la tanda no se reduce.
3. **La excursión es un producto `pack`.** Y no es un truco: medido, `min_qty` **solo se aplica a
   los packs** (`CartLineValidator::minimumQuantity()` devuelve 1 para una entrada) y
   `PackAvailability` **solo cuenta filas de producto `pack`**, así que el mínimo de 30 y el tope de
   grupos por franja **solo funcionan siendo pack**. Trae además el bloque propio (no se funde con
   otras líneas) y el **post-formulario de invitados**, que es donde P3 colgará las autorizaciones de
   los padres. El docblock de `PackAvailability` ya lo anticipaba en un `[DECIDIDO owner]` de `#151`:
   *«cumpleaños en la suya hoy; excursiones de colegio con la suya mañana»*.

### 7.2 Lo que queda anotado para después

- La granularidad por día de la semana dentro de una zona (opción D) **no se construye**. Si algún
  día hace falta, entra sin romper esto.
- La tanda **B** (precio por tramo de cantidad, uniforme: los 70 a 13 €) va en spec propia y **es
  dinero**: `CRITICAL_RE`, `PAY-16`/`PAY-17` y el libro del pedido.

### 7.3 Estado

🟦 **EN EL ÁRBOL** (`#322`). Lo que falta para ✅ es el ojo del owner en navegador.

---

## 8. La ejecución (`#322`)

**Lo que quedó**: migración con las tres columnas · `OperatingSchedule::effectiveForZone()` /
`isOpenOnForZone()` como métodos NUEVOS (§4.3) · `SlotGenerator` resolviendo por zona **dentro** del
bucle · `ItemRescheduleOffer` por zona en sus dos preguntas · `ProductAvailability::allowsStart()` por
zona, que es el punto de estrangulamiento de §4.4 · `ZoneOwnScheduleTest` con 10 casos.

**Verificación**: suite verde (3.766 / 24.557) · Pint ✓ · docs-check ✓ · **4 mutaciones, las 4
muerden** (la del generador tumba 4 casos) con pasada de CONTROL · `VERIFY_CONC=1` con
`purchase:verify-oversell` en los **seis** escenarios y `redsys:verify-concurrency`, sobre InnoDB real.

### 8.1 ⚠️ Lo que la ejecución enseñó

1. **La afirmación de §4.4 era FALSA y la cazó una GUARDA, no una lectura** — ver la corrección al
   principio de esa sección. El test de re-programación se escribió, se ejecutó y **falló con el
   producto ya «arreglado»**.
2. **`git checkout` para deshacer una mutación, con el trabajo sin commitear, se lleva el trabajo.**
   Es la regla de `#181` en pequeño, pagada otra vez. El arnés definitivo hace `git stash` sobre un
   commit de guardado.
3. **Lo que NO hubo que construir lo destapó el owner preguntando**: `zones.max_per_slot` ya existía
   y ya era por zona, así que el tope de grupos por franja es configuración. Esta tanda iba a ser el
   doble de grande.
4. ⚠️⚠️ **La tanda estuvo a punto de entregarse SIN PANEL.** Las tres columnas se crearon, el dominio
   las leía y el formulario de zonas **no las exponía**: solo se podían tocar por SQL. Eso rompe el
   primer principio del proyecto —«todo configurable desde el panel»— y una feature que el cliente no
   puede activar **no está hecha**. Lo detectó la pregunta «¿cómo lo reviso en el navegador?».
   ▶ Guarda: `ZoneResourceTest::test_create_with_per_zone_schedule_override` + su gemela de herencia.
5. ⚠️⚠️ **Y al ponerlo en el panel apareció un defecto de BORDE**: `OperatingSchedule` compara estas
   horas con las del recinto **como CADENAS**, y `opening_hours` las guarda con segundos. El
   `TimePicker` con `seconds(false)` escribía `'15:00'`, y `'15:00:00' > '15:00'` es **verdadero**
   (misma cabecera, más larga), así que **una franja que acababa exactamente a la hora de cierre
   quedaba fuera**. Se normaliza a `H:i:s` en el MODELO —no en el campo— para que ninguna superficie
   pueda reintroducir el formato corto. Guarda con su mutación.
