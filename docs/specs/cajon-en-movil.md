# [SPEC] El CAJÓN en móvil — el embudo de compra a 390 px

> Estado: 🟦 **código de las unidades 1–3 en `main`; queda el OJO del owner** ·
> Última actualización: 2026-08-28 · Decisiones asociadas: `DECISIONES #237` (la medición y el
> aviso de cookies) y **`#239`** (el rediseño de los pasos de FECHA y HORA).
>
> ❗ **Encargo del owner (2026-08-28)**: «el SPA tiene que ser perfecto en móvil, que es el 90 %».
> Este documento es el sitio único de esa tanda: qué se midió, qué se decidió y qué queda.

---

## 1. Lo MEDIDO, que es de donde sale todo

Recorrido del embudo en **390×844** el 2026-08-28 (`#237`), antes de tocar una línea. No se repite:
los números de abajo son la línea base contra la que se compara cualquier cambio.

| Paso | Controles bajo 44 px | Y además |
|---|---|---|
| Catálogo | **1 de 16** | está bien: tarjetas de 350×108 |
| Fecha | **11 de 12** | celdas **43×43**, flechas del mes **32×32**, «Volver» **61×17** |
| Hora | **12 de 13** | chips **68×39**, y **~400 px** de pantalla vacía debajo |

Y dos hallazgos que no eran de tamaño:

1. ⚠️⚠️ **El aviso de cookies tapaba 296 px — el 45 % del cajón — y con ellos el botón que hace
   avanzar la compra**, en `/entradas`, donde el cajón **nace abierto**. Todo cliente nuevo en móvil
   se encontraba el paso de fecha a medias. **Arreglado en `#237`** (`z-index` por ROL: 140).
   ▶ **No lo veía ningún test porque ninguno mide DOS capas a la vez**: no faltaba un caso, faltaba
   una categoría de caso. La oclusión se mide con `elementFromPoint()`, **no restando rectángulos**.
2. El calendario pintaba **42 celdas** y **ningún chip de hora decía cómo estaba de lleno**.

### 1.1 ⚠️ Una premisa de `#237` que hubo que corregir antes de diseñar

`#237` escribió «un mes de 42 celdas donde solo **2** eran reservables», y de ahí dedujo que sobraban
días. **Medido de nuevo el 2026-08-28 contra `AvailabilityOffer`, es al revés**:

| | |
|---|---|
| Horizonte de compra | **6 meses** |
| Días reservables por producto | **182** (las 8 entradas y los 2 packs) |
| Horas por día | **11** (entradas) · **10** (packs) |
| Aforo por franja | 40 (Jump) · 25 (Kids) · 60 con tope de fiesta 20 (packs) |

O sea: **no faltaban días, sobraba rejilla**. Lo que se midió como «2 de 42» es cierto **solo del mes
en curso**, que abre casi entero en el pasado —a 28 de agosto quedaban 4 de 42; en septiembre serían
30 de 42—. ▶ **El defecto no es «hay poca oferta», es «el calendario abre en el mes que ya pasó y
obliga a leer una rejilla para elegir pasado mañana».** Y esa corrección cambia el diseño: la tira
resuelve el caso normal, pero **el calendario no se puede retirar**, porque una reserva de cumpleaños
se hace con meses de antelación y 182 chips no se recorren con el dedo.

---

## 2. Objetivo

- **Ningún control del embudo por debajo de 44×44** de objetivo táctil.
- **Elegir en vez de buscar**: lo que el cliente hace el 90 % de las veces —reservar para pronto— sin
  leer una rejilla.
- **Que una hora diga si se está acabando**, sin publicar el aforo.
- Fuera de alcance: el catálogo (ya cumple) y el desenlace del pago.

---

## 3. El PLAN, y en qué punto va

Acordado con el owner; el orden es suyo.

| # | Unidad | Estado |
|---|---|---|
| 1 | El aviso de cookies deja de tapar el cajón | ✅ `#237` |
| 2 | La FECHA pasa a una **tira de días reservables** + objetivos de 44 px | ✅ `#239` |
| 3 | La HORA pasa a **tira deslizable con ajuste** | ✅ `#239` |
| 4 | Cada hora dice **cómo está de llena**, solo cuando quedan pocas, con umbral en el panel | ✅ `#239` |
| 5 | Repasar toques y teclado en **carrito** e **identificación** | ⬜ **sin medir todavía** |
| 6 | Bajarle el ruido al **selector de menores** del embudo | ⬜ `menores-a-cargo.md` §12 |

▶ **Lo que queda de las unidades 2–4 es el OJO del owner** (§7).

---

## 4. Las tres decisiones del owner, con lo que costó cada una

### 4.1 El calendario **se queda**, detrás de «Ver más fechas» — `[DECIDIDO owner, 2026-08-28]`

Se le ofrecieron tres salidas con su coste: solo la tira · tira + calendario plegado · solo el
calendario arreglado. Eligió la segunda, **con la corrección de §1.1 delante**.

- La tira lleva **todos** los días reservables, agrupados por mes. No se acota a N días: recortarla
  en el cliente sería decidir ahí hasta cuándo se vende, y eso lo dice el servidor (`AFORO-02`).
- El calendario **nace plegado** y vuelve a plegarse con cada oferta nueva: un producto nuevo devuelve
  el paso a su forma por defecto, en vez de heredar la decisión que el cliente tomó para otra cosa.

### 4.2 La HORA es una **tira con ajuste** — `[DECIDIDO owner]`, y con una objeción medida encima

⚠️⚠️ **Se le llevó una objeción antes de construirlo, y la mantuvo. Queda escrito para que la
siguiente sesión sepa que fue una decisión y no un descuido.**

Medido: son **11 horas**; en 390 px el área útil del cajón son ~350, y los chips anteriores (68 px)
entraban a 4 por fila → **3 filas con las 11 VISIBLES a la vez**. Con chips de 44 px y el rótulo de
«casi llena» seguían entrando en ~184 px. **La tira enseña 4 de 11** (medido en navegador) y esconde
siete tras un gesto. El owner la eligió igualmente por coherencia con los carruseles con `scroll-snap`
del artboard de móvil del cliente.

▶ **Consecuencia asumida, y por eso la señal de «sigue» no es opcional**: en una lista corta el
desplazamiento resta. Lo que compensa es que **no se pueda dudar de que hay más**, y de ahí §5.2.

### 4.3 El aviso dice **«Casi llena», sin número** — `[DECIDIDO owner]`

Se le ofrecieron «Quedan 3» (número exacto), «Casi llena» (sin número) y una mezcla. Eligió la
segunda: **no publica el aforo restante**. El umbral lo pone el operador.

⚠️ **Lo que se pierde y se sabe**: «Casi llena» con 7 libres y con 1 se lee igual, así que el cliente
que quiere 4 entradas no sabe si le caben. El selector de cantidad sí se lo dirá en el paso siguiente
(`max_quantity`), que es donde se decide de verdad.

---

## 5. El diseño, pieza a pieza

### 5.1 De dónde sale cada cosa (y qué NO decide el cliente)

| Pieza | Dónde vive | Qué NO decide |
|---|---|---|
| La tira de días | `resources/js/sidebar/calendar.js::buildStrip()` | qué días se ofrecen (`SlotOffer`) |
| El estado del paso 2 | `resources/js/sidebar/stores/date.js` (`strip`, `calendarOpen`) | nada de negocio |
| El predicado del aviso | `resources/js/sidebar/offer.js::isAlmostFull()` | el umbral (lo publica el servidor) |
| El umbral | `App\Domain\Booking\Services\AvailabilitySettings` + `GET /api/v1/config` | el aforo (`AFORO-02`) |
| El ajuste en el panel | `booking.low_availability_max`, en «Aspecto y opciones de la web» | — |

⚠️ **`AvailabilitySettings::isLow()` y `offer.js::isAlmostFull()` son el MISMO predicado escrito dos
veces**, y por eso el contrato de `/config` publica el umbral **con su operador dentro**
(`available <= low_availability_max`). El día que «Crear pedido» del panel ofrezca horas, el operador
tiene que ver exactamente lo mismo que el cliente.

⚠️⚠️ **El aviso lee `available`, NO `max_quantity`.** En un pack no son el mismo número —medido:
`available = 60` plazas de la franja, `max_quantity = 20` invitados de esa fiesta—, así que con el
campo equivocado una franja vacía se anunciaría «casi llena» en cuanto la fiesta llegara a su tope.

⚠️ **`0` es una respuesta, no una falta**: desactiva el aviso. Por eso las dos implementaciones
comprueban el umbral **antes** de comparar, en vez de dejar que `available <= 0` lo resuelva solo — si
no, el operador que apaga el aviso lo vería aparecer justo donde más chirría. Y el respaldo del cajón
cuando `/config` no llega **también es 0**: inventar escasez que no se ha podido leer es peor que
callar.

### 5.2 Las dos tiras: qué dice que la lista sigue

⚠️ **La vela sola NO bastaba, y se vio en navegador.** El degradado va de transparente a `--bg`, y
`--bg` (#F4EFE3) y `--bg-card` (#FBF7EC) **casi coinciden**: el chip que asoma se desvanecía sobre un
fondo casi idéntico y no se leía. Lo que no se puede confundir es **un chip cortado por el borde de la
pantalla**, así que las dos tiras salen **a sangre** —margen negativo de 20 px, que es el relleno de
`.purchase__scroll`, devuelto por dentro como `padding-inline`— y el primer chip **sigue alineado con
el título**.

▶ **La vela se queda igualmente, y se apaga sola sin una línea de JS ni `animation-timeline`**: es un
degradado hacia el color del fondo, así que cuando debajo hay un chip lo desvanece y cuando la lista
se acabó cae sobre el mismo color que lo pinta y **es invisible**. La señal se regula con el contenido.

⚠️ **El separador de mes lleva `scroll-snap-align` aunque no se pueda pulsar, y sin eso NACE
CORTADO.** Medido: con el ajuste solo en los días, el navegador colocaba el primer chip contra el
borde del contenido y dejaba «Ago» en `x = −5`. **Una parada de ajuste no es «un sitio donde se
pulsa», es «un sitio donde la tira puede quedarse quieta»**, y el principio de un mes lo es.

⚠️ **El ajuste es `proximity`, no `mandatory`**: con 182 chips un ajuste obligatorio pelea con el
impulso largo del deslizamiento.

### 5.3 Volver al paso con un día ya elegido

`DateStep.vue` coloca la tira en el día elegido al montar. ⚠️ **Mueve `scrollLeft` y NO llama a
`scrollIntoView()`**, que desplaza también a los ANCESTROS: el cajón entero saltaría a media pantalla
por colocar un chip. Es la misma clase de efecto colateral que el `preventScroll` del foco de la
pantalla de puerta (`#234`·6). Y `offsetLeft` se mide contra el `offsetParent`, así que
`.daystrip__track` lleva `position: relative` **en la hoja**: sin eso el cálculo daría un número
plausible y equivocado.

### 5.4 El «Volver» de la banda de progreso

Medido 61×17. ⚠️ **El área táctil crece SIN mover el diseño**: un `min-height: 44px` engordaría la
banda, que se pinta en **todos** los pasos del embudo. Una capa transparente (`::before` absoluto con
sangrados negativos) da 45 px de alto y no ocupa una fila más. Verificado en navegador: el clic 10 px
por encima del rótulo ya es suyo.

---

## 6. Impacto en invariantes

**Ninguno se toca.** Lo relevante es lo que este trabajo **no** puede hacer:

- `AFORO-02` — la oferta la decide `SlotOffer`. La tira solo coloca días que el servidor ofrece, y el
  aviso solo lee un número que el servidor publica. Subir el umbral a 100 no vende una plaza de más.
- `CE-4` — ninguna regla nueva nace en el cliente: el reparto por meses y el nombre del día son
  presentación, y el predicado del aviso es el espejo declarado de su original en PHP.
- `PERF-02` — el chunk del cajón sube **2,86 KiB medidos** (252,27 → 255,13; techo 253 → 256) y sigue
  cargándose bajo demanda: no toca la ruta de más tráfico del sitio.

---

## 7. Verificación

### 7.1 Lo que hay (2026-08-28)

| Red | Qué cubre |
|---|---|
| `calendar.test.js` (+9 casos) | la tira: solo días ofrecidos, agrupación, el año del rótulo, el huso |
| `offer.test.js` (+5 casos) | el predicado del aviso, el `0`, `available` frente a `max_quantity` |
| `stores/date.test.js` · `stores/time.test.js` (+8) | el estado: el calendario plegado, el umbral saneado |
| `AvailabilitySettingsTest` (9 casos) | el lector defensivo y el predicado en PHP |
| `PublicConfigTest` | el contrato: el umbral viaja, y el `0` viaja como `0` |
| `SettingsPageTest` (+2) | el ajuste se guarda desde el panel, y el `0` también |
| `SidebarDomContractTest` (+2 casos) | el árbol de la tira, **del calendario desplegado** y del aviso |

⚠️ **Los dos casos nuevos del contrato de árbol no son un extra.** Desde el rediseño el paso 2 abre
con la tira, así que los casos que ya había **dejaron de emitir una sola celda de la rejilla**: sin un
caso que la despliegue, `.cal__grid`, `.cal__day`, las flechas y la leyenda salían del gate sin que
nada avisara. Lo mismo con el aviso: con el umbral por defecto y 40 plazas libres no aparece. ▶ **Es
la lección que el propio fichero ya tenía escrita para `aria-current`: hay que llevar el estado a
donde el árbol existe.**

**Mutación** (10 de 10 muerden): parsear la cadena de fecha en vez de construirla en local · quitar el
año del rótulo · marcar todos los días como elegidos · caerse la puerta del `0` · leer `max_quantity`
en vez de `available` · usar `<` en vez de `<=` · y las cuatro de PHP.

⚠️⚠️ **Una guarda nació LAXA y lo dijo la mutación**: el caso «en un pack el aviso mira la franja, no
la fiesta» usaba `available = 60, max_quantity = 20` con umbral 8 — **los dos por encima**, así que
intercambiar el campo pasaba en verde. Se rehízo con 60/6, que los pone a lados distintos del umbral.
▶ *Una guarda no se sabe si sirve hasta que se muta con el fallo REAL que la motivó.*

### 7.2 En navegador (headless, 390×844) — **22/22**

Guion en `VERIFICACION-E2E-CAJON.md` §5.novodecies. Lo que dice:

| | Antes (`#237`) | Ahora |
|---|---|---|
| Chips de día reservable | — | **182**, con separador por mes (`Ago … Feb 2027`) |
| Celda del calendario | 43×43 | **45×45** |
| Flecha de mes | 32×32 | **44×44** |
| Chip de hora | 68×39 | **72×44** |
| Controles bajo 44 px | 11 de 12 · 12 de 13 | **0 y 0** |
| Horas visibles sin deslizar | 11 de 11 | **4 de 11** (§4.2) |
| Desborde horizontal de la página | — | **0 px** |

Y el camino completo del aviso —panel → `/config` → store → chip— visto con el umbral a 100: **11 de
11 horas con «Casi llena»**, y a 8 (el defecto, con 40 plazas libres) **ninguna**, que es lo correcto.

⚠️⚠️ **DOS instrumentos propios salieron mal antes de acertar, y los dos daban un número creíble.**
Medir la CAJA PINTADA daba «Volver» como defecto (61×17) cuando su área táctil son 45 px —llamaba
defecto a la solución—; y medirlo con `elementFromPoint()` daba **178 defectos** en la tira, porque
los chips fuera del carril están fuera del VIEWPORT y ahí no hay nada que golpear. Lo que vale es la
geometría: la caja **más el pseudo-elemento que amplía el área**. ▶ *Tercera vez en la semana que el
sospechoso correcto es el instrumento.*

### 7.3 ❗ Lo que falta: el OJO del owner

Un navegador headless mide, no valida (`CONVENCIONES §3.bis`). Lo que hay que mirar con el dedo:

1. **Deslizar las dos tiras** en un teléfono de verdad: que el ajuste no pelee con el impulso y que el
   chip cortado por el borde se lea como «hay más».
2. **La hora**: ver 4 de 11 y decir si compensa (§4.2 lo deja abierto a propósito).
3. **«Ver más fechas»** → el calendario, y volver.
4. **«Casi llena»**: poner el umbral en Ajustes a un número alto y comprobar el rótulo; después
   dejarlo donde quiera.

### 7.4 ⚠️ Lo medido que sigue sin resolver, y es del owner

**El hueco vertical.** Medido a 390×844, con el área desplazable del cajón en 558 px:

| Paso | Contenido | Vacío |
|---|---|---|
| Fecha | 192 px | **366 px** (66 %) |
| Hora, antes de elegir | 106 px | **452 px** (81 %) |
| Hora, ya elegida | 208 px | 350 px |

⚠️ **La tira EMPEORÓ ese hueco unos 50 px** respecto a los ~400 que midió `#237`: donde había tres
filas de chips ahora hay una. Es la contrapartida directa de §4.2 y no se ha rellenado con nada,
porque inventar contenido para tapar un hueco es una decisión de producto, no de implementación.
▶ `[PENDIENTE: owner]`.

---

## 8. Lo siguiente

- **Unidad 5**: carrito e identificación, **sin medir todavía**. Se mide igual que §1 antes de tocar.
- **Unidad 6**: el ruido del selector de menores (`menores-a-cargo.md` §12). ⚠️ Medir cuánto alto se
  lleva del paso **antes** de tocarlo; sin ese número «más sutil» es una opinión.
- Y lo que este documento **no** cubre: `/admin/crear-pedido` en tablet, que es otro encargo y vive en
  `panel-navegacion.md` §6·U7.
