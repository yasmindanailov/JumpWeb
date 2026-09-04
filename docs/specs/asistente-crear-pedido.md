# El asistente de «Crear pedido» — de tres pasos a siete, con menos toques

> Estado: 🟦 **DISEÑO CERRADO · T1, T2 y T3 EN EL ÁRBOL** (§7, §8 y §9) · queda la **T4** · Fecha: **2026-09-04**
> Encargo del owner (2026-09-03, literal en §1) · Decisiones suyas en **§3**
> Carril: **panel / UI-UX** (este ordenador, banda **`#460`–`#469`**; `#460` la auditoría,
> `#461` el shell).
> Lo que motiva la tanda: `docs/specs/auditoria-panel-admin.md` **C1** — una admin vendió un
> cumpleaños como diez entradas sueltas, y la causa medida es el desplegable plano de esta pantalla.
> La FORMA de tablet que ya tiene (dos columnas, resumen pegajoso, chips de hora, tira de días)
> viene de `panel-navegacion.md` §9–§11 y **no se tira: se reparte en pasos**.

---

## 1. El encargo

Del owner, sobre la pantalla de crear pedido:

> «Vamos a hacer más pasos. Primero se elige el cliente, ese paso está bien, solo haremos que en
> cuanto se elija un cliente o se registre un cliente manualmente, automáticamente seguimos a la
> siguiente pantalla. Después, en una pantalla sola, se muestran todos los productos, sin los
> complementos, pero en cards, con icono y un CTA de "más info" que abre un modal con la descripción
> y demás datos públicos. Al seleccionar producto, automáticamente al siguiente paso. Fecha: la
> selecciona de un calendario grande, bien visible, que se vean las plazas disponibles del producto
> en concreto. Después de elegir fecha, en la misma pantalla se elige la hora. Al elegir la hora,
> siguiente pantalla automáticamente: campos a rellenar; al terminar, siguiente y va a los
> extras/complementos; después "añadir al carrito", ese botón full width. Después de añadir al
> carrito, quiero que el carrito ocupe toda la pantalla, una columna, y el botón de "Ir a pagar" en
> vez de "siguiente", en grande. Otro CTA de "añadir más productos" al lado, y al darle se vuelve
> otra vez la página con dos columnas. La vista de pagar vuelve a dos columnas y ya sería como está,
> pero el botón de cobrar y crear pedido más grande. Después de crear el pedido, una pantalla nueva:
> pedido creado correctamente…»

Y el marco: **«usamos Filament, no quiero chapuzas ni deuda, ni huecos»**.

---

## 2. Lo medido que condiciona el diseño

Tres cosas, todas medidas antes de diseñar. **Las tres cambian el encargo**, y por eso están aquí
antes que el plan.

### 2.1 · Dos de cada tres pasos estarían VACÍOS

De los **18 productos vendibles**, **16 no tienen ni un campo que rellenar** y **7 no tienen ni
campos ni complementos**. Entre los 12 productos REALES del catálogo (fuera los de sonda):

| Producto | Campos | Complementos |
|---|---:|---:|
| Jump · 1/2/3 h · Ilimitada (4) | 0 | 3–4 |
| Kids · 1/2/3 h · Ilimitada (4) | 0 | 2 |
| **Cumpleaños Jump / Kids** (2) | **5** | 4 |
| Excursión 2 h / 3 h (2) | 0 | **0** |

▶ Con el asistente tal cual, **vender una entrada obliga a pasar por una pantalla en blanco** y una
excursión por dos. Eso es MÁS fricción, no menos, que es justo lo contrario del encargo.

**Por eso: un paso que no tiene nada que preguntar se SALTA** — y el indicador de pasos lo enseña
saltado, nunca en silencio. `[el owner delegó esta mejora: «si valoras algo mejor, menos fricción y
que el usuario lo entienda, lo acepto»]`.

### 2.2 · El calendario con plazas por día cuesta 709 consultas y 11,4 s

Medido sobre `SlotOffer` con un producto real: `offerableDates()` resuelve **177 días con 5
consultas**, pero `offerableTimes()` va **día a día** — **26,3 consultas y 421 ms por día**, o sea
**709 consultas y 11,4 segundos** para pintar las plazas de un mes. Y **no existe ninguna vía
agregada por rango**: ni `AvailabilityReader` ni `SlotOffer` ni `PackAvailability` tienen un método
que resuelva un intervalo de una vez.

▶ `[DECIDIDO owner, 2026-09-04]`: **el calendario resalta los DÍAS RESERVABLES y las plazas se ven
al elegir el día, con las horas** — que es lo que ya sabe hacer y cuesta 5 consultas. El semáforo
por día queda fuera: sería una consulta agregada nueva **sobre aforo**, o sea tanda propia con su
verificador de concurrencia.

### 2.3 · El panel NO le pasa la cesta a `SlotOffer`, y la web SÍ

Medido: web y panel dan **exactamente lo mismo** —mismo producto, **177 días y 11 horas idénticos**,
mismos números de plazas—, así que **la lógica de disponibilidad no diverge**. Lo que diverge es un
argumento: `AvailabilityController` le pasa los **ocupantes de la cesta** («las horas van por POST y
llevan la cesta») y `CreateManualOrderPage::timeMap()` llama a `offerableTimes()` **sin ellos**.

**El asistente nuevo lo convierte en el camino normal**: con «añadir más productos», el operador mete
20 entradas a las 17:00, vuelve, y el calendario le dice que quedan 40. ▶ **CORREGIDO en la T3**
(§9).

⚠️ **Y una cita de esta misma sección era inexacta**: decía que el borde estaba «declarado en
`hora-extra.md` §8.3», y lo que aquel §8.3 declara es otra cosa de la misma familia —que el ENDPOINT
de complementos del embudo no recibe la cesta, y que la UI de complementos del alta manual no
decora—. **Las dos siguen en pie.** Que el panel no le pasara la cesta a `offerableTimes()` no lo
había declarado nadie: se midió aquí. *Una deuda parecida en la misma familia no es la misma deuda.*

---

## 3. Las decisiones del owner (2026-09-04)

Las tres se preguntaron con el número y el coste de cada salida delante.

| | La pregunta | `[DECIDIDO owner]` |
|---|---|---|
| **D1** | ¿Dónde va la CANTIDAD? El encargo no la menciona y hoy vive con el producto | **Arriba de la pantalla de fecha.** Una pantalla menos, y las horas dicen la verdad PARA ESA CANTIDAD: el orden se lee solo —cuántos → qué día → qué hora— y el auto-avance sigue en la hora, que es lo último |
| **D2** | El calendario con plazas: ¿hasta dónde? | **Días reservables + plazas al elegir el día.** Cero trabajo de dominio y cero riesgo sobre aforo (§2.2) |
| **D3** | ¿Se renombra el post-form a «Parte de celebración»? | **No: se queda «Formulario de reserva».** La pantalla de éxito usa el nombre que el cliente verá luego en su correo y en su cuenta; renombrarlo eran ~57 claves en 7 ficheros × 4 idiomas, y dos nombres para lo mismo era la incoherencia a evitar |

---

## 4. El mapa de pasos

```
1 Cliente        siempre     → auto-avanza al elegir o registrar
2 Producto       siempre     → auto-avanza al elegir
3 Cuándo         siempre     → cantidad + día + hora; auto-avanza al elegir HORA
4 Datos          CONDICIONAL → campos del evento · menores a cargo · justificante
5 Extras         CONDICIONAL → complementos
                              ⇒ el último paso con algo que preguntar lleva «Añadir al carrito»
6 Carrito        siempre     → una columna, «Ir a pagar» + «Añadir más productos» (vuelve al 2)
7 Pago           siempre     → dos columnas, «Cobrar y crear pedido»
  Hecho                      → pantalla propia de desenlace (T4)
```

**Qué hace que un paso «tenga algo que preguntar»** (la regla vive en UN sitio):

- **Datos**: el producto declara campos de evento · **o** el titular tiene menores asignables ese
  día · **o** el producto ofrece justificante de menor invitado.
- **Extras**: el producto tiene complementos ofrecibles.
- Los demás, siempre.

⚠️ **El auto-avance se engancha al CAMBIO, nunca al estado.** Si mirase el estado, volver atrás a
«Cuándo» con la hora ya puesta rebotaría hacia adelante y el operador no podría corregir nada.

⚠️ **«Añadir al carrito» no puede vivir dentro del formulario de un paso fijo**: el último paso de
la línea es variable (Extras si los hay; si no, Datos; si no, Cuándo). Vive en la navegación, que es
la que sabe en qué paso está.

---

## 5. Las cuatro tandas

| | Qué | Por qué separada |
|---|---|---|
| **T1** | **La estructura**: siete pasos, auto-avance, saltar los vacíos, el CTA correcto en cada paso, el carrito como paso propio a una columna | Es un cambio de CONDUCTA y se puede medir en toques. Sin ella, lo demás no tiene dónde vivir |
| **T2** | **El producto en cards** con icono, agrupadas por tipo, y «más info» con los datos públicos | Es lo que cierra **C1** de la auditoría: hoy nada distingue una entrada de un pack |
| **T3** ✅ | **Cuándo**: cantidad arriba, calendario grande, horas con plazas — **y la cesta a `SlotOffer`** | Lleva la única corrección de DOMINIO del encargo (§2.3) y no debe viajar con cambios de presentación |
| **T4** | **El desenlace**: pantalla de pedido creado con lo que de verdad pasó | Toca correos y post-form: es donde más fácil es prometer lo que no ocurrió |

---

## 6. Lo que NO se hace, y por qué

- **No se toca `ManualOrderFulfiller` ni `OrderCreator`.** El asistente reparte preguntas; no cambia
  cómo se crea ni cómo se cobra un pedido. La única corrección de dominio es pasar la cesta a la
  OFERTA (§2.3), que es lectura.
- **No se renombra el post-form** (D3).
- **No hay semáforo de plazas por día** (D2).
- **No se toca el Wizard de Filament**: esta página tiene stepper PROPIO desde el principio, y es lo
  que permite bloquear el avance y decidir el CTA por paso — control que el Wizard nativo no da.

---

## 7. T1 · Lo ejecutado (2026-09-04, `#462`)

Los siete pasos, la navegación que los salta y el CTA correcto en cada uno. **No entra nada de
presentación**: ni tarjetas de producto, ni calendario grande, ni pantalla de éxito.

### 7.1 · Qué hay ahora en código

- **Siete constantes** y `LINE_STEPS`, la lista de los que componen una línea.
- **`stepHasSomethingToAsk()`** — la regla del salto, en UN sitio.
- **`isLastLineStep()`** — de él cuelga «Añadir al carrito», y es VARIABLE.
- **`stepAfter()` / `stepBefore()`** — la navegación salta lo vacío en las dos direcciones.
- **`advanceAfterChoice()`** — el auto-avance, enganchado al CAMBIO en tres sitios: el select de
  cliente, el alta en mostrador (`selectCustomer()`) y `pickTime()`.
- **`goToStep()`** — el indicador lleva hacia atrás, nunca hacia adelante ni a un paso vacío.
- **`stepChoices()`** — lo elegido bajo cada rótulo.
- **`addMoreProducts()`** y el salto a `STEP_CART` al añadir una línea.
- La **navegación sale a su propio partial** (`manual-order-nav.blade.php`): el paso del carrito la
  pinta a una columna y los demás dentro de la columna pegajosa.

### 7.2 · Lo medido en navegador (iPad horizontal, 1080×810)

Recorrido completo con un cliente y un producto reales:

| | |
|---|---|
| Toques de vacío a «carrito con una línea» | **7** |
| Paso tras elegir cliente (2 toques) | **Producto**, con «Sonda Cliente · su correo» escrito |
| Paso tras elegir producto (4 toques) | **Cuándo**, con «Jump · 2 horas» escrito |
| Paso tras elegir la hora (6 toques) | **Extras** — «Datos» **saltado y dicho** |
| CTA del último paso de la línea | «Añadir al carrito», **174 × 44** |
| Tras añadir | **Carrito, UNA columna y sin `aside`**; «Añadir más productos» 217 × 44 · «Ir a pagar» 128 × 44 |
| Alto del contenido | **810 px de 810 en los cinco pasos** — cabe entero, sin desplazar |

### 7.3 · Los tres tropiezos, y qué enseñan

1. ⚠️⚠️ **El indicador afirmaba lo que no sabía.** En el paso 1, «Datos» y «Extras» ya decían «sin
   nada que rellenar» —**sin que hubiera producto elegido**—, porque `stepHasSomethingToAsk()`
   responde `false` con `selectedProduct()` a `null`. Se separó del predicado de navegación:
   **`stepIsSkipped()` exige que haya producto**. *Lo vio la sonda de navegador, no un test.*
2. ⚠️⚠️ **Dos mutaciones no mordieron y el hueco era real**: las guardas comprobaban el PREDICADO
   del salto y no el MOVIMIENTO, así que se podía quitar el salto de `next()`/`back()` con la suite
   en verde. Hizo falta un tercer sujeto —**una entrada CON complemento**, que deja «Datos» vacío y
   «Extras» lleno— para que el salto del medio fuera observable.
3. ⚠️ **Un caso pasaba por el motivo equivocado**: el fixture tenía UNA franja de 60 min y el pack
   dura 120, así que `SlotOffer` no lo ofrecía y `pickTime()` salía sin elegir nada — «no avanza»
   era cierto, pero por otra razón. Tres franjas seguidas y una aserción de que la hora **se eligió
   de verdad**.

### 7.4 · Deuda que esta tanda NO deja, y la que sí

- **No deja huérfanos**: la vista del selector de idioma no aplica aquí, pero sí se retiró el import
  de `Alignment`, cuyo único uso se fue con el botón que salió del formulario.
- **Sí queda anotado**: en el paso del carrito hay **5 controles bajo 44 px** (los de quitar línea y
  el paginador del resumen). Es presentación del carrito y le toca a la **T4**.

**Verificación**: `CreateManualOrderStepsTest`, **13 casos** con **9 mutaciones y las 9 muerden**
(control verde antes de mutar, con copias de seguridad y no `git checkout`) · los 86 casos que ya
existían de esta pantalla, **re-apuntados por sujeto** y en verde · sonda de navegador con el
recorrido entero · Pint · docs-check · **suite 4.252 verde**.

---

## 8. T2 · El producto en tarjetas (2026-09-04, `#462`)

**Es la tanda que cierra el crítico C1 de la auditoría**, el que ya costó dinero: un desplegable
plano de 18 opciones cuyo rótulo era `«{zona} · {nombre}»` y **no decía de qué tipo era nada**.

### 8.1 · Qué hay ahora

- **`productCards()`** — los productos vendibles **agrupados por tipo**, entradas primero, con lo
  que DISTINGUE: icono, zona, duración, precio y —solo en los packs— **el rango de invitados**.
- **`pickProduct()`** — la **ÚNICA** puerta. Valida en el servidor (`AFORO-02`: un `wire:click` se
  puede llamar con cualquier id), hace los mismos olvidos que hacía el `afterStateUpdated` del
  `Select` que ya no existe, y avanza.
- **`productInfoAction()` / `productInfoFields()`** — «Más info» con lo que el cliente ve: tipo,
  zona, duración, rango de invitados, descripción y ventajas. **De lectura: abrirlo no elige nada**,
  o el operador no podría comparar dos productos sin comprometerse con el primero.
- El partial `manual-order-products.blade.php` y su CSS.

⚠️ **`productOptions()` murió con el `Select`; `productLabel()` NO**, porque tiene otro consumidor:
es el rótulo con el que la línea aparece en el carrito.

### 8.2 · Lo medido en navegador (iPad horizontal)

| | |
|---|---|
| Tarjetas | **18**, en dos grupos: «ENTRADAS» (10) · «PACKS Y CELEBRACIONES» (8) |
| Lo que enseña una entrada | `Jump · 1 hora · JUMP · 60 min · desde 9,90 €` |
| Lo que enseña un pack | `Cumpleaños Jump · Cumpleaños · 120 min · **8–20 invitados** · desde 15,00 €` |
| Controles bajo 44 px | **0** · desbordamiento horizontal **0** |
| «Más info» | Tipo · Zona · Duración · Ventajas — y **el paso sigue siendo «Producto»** al cerrarlo |
| Alto del paso | **1.722 px** con 18 productos: se desplaza, y es lo esperado en un elegidor |

### 8.3 · Los tres tropiezos

1. ⚠️⚠️ **Las ventajas salían en LOS TRES IDIOMAS**: «Access to the Jump zone · Acceso a la zona
   Jump · Accès à la zone Jump». `features` es **traducible** y `tr()` es su puente; leerlo en crudo
   devuelve el mapa de idiomas entero y recorrerlo a mano saca uno de cada. *Inventar un recorrido
   donde ya hay un puente es cómo se cuela un idioma equivocado sin que nada falle.* Lo vio la sonda.
2. ⚠️⚠️ **Dos mutaciones no mordieron, y por motivos distintos**: la del icono porque **el fixture no
   tenía icono elegido** —sin sujeto, «leer el marcador» y «deducirlo del tipo» dan lo mismo—, y la
   de los menores porque **nunca se aplicó** (un `sed` con `\n` no casa entre líneas). *Una mutación
   que no muerde puede ser una guarda ciega o un arnés roto, y hay que distinguirlo.*
3. ⚠️ **`assertSee` no ve el modal de Filament** (es un `wire:partial`): la trampa de `#161`, pagada
   otra vez. La guarda pasa a aseverar por CONDUCTA sobre `productInfoFields()`.

### 8.4 · Lo que NO entra, y por qué

- **`ticket_types.conditions` no se enseña.** Medido: esa columna **no la lee nadie y el catálogo no
  la edita** —cero consumidores en el repo—. Pintarla aquí la convertiría en el único sitio donde
  aparece un texto que el operador no puede rellenar desde ninguna pantalla. Ficha en `DEUDA.md`.
- **El panel no adopta el set de iconos del cliente**, y esto no lo contradice: se pinta el
  **marcador del producto** (`ticket_types.icon`), que el propio panel ya deja elegir en el catálogo
  y que la web pinta en su tarjeta de precio. Le da al operador la razón para rellenarlo — medido en
  `#259`: **2 claves usadas de 11**.

**Verificación**: `CreateManualOrderProductCardsTest`, **10 casos** con **9 mutaciones y las 9
muerden** · los 99 casos previos de esta pantalla en verde tras un re-apuntado mecánico (la puerta
pasa de `set('data.sel_product_id')` a `call('pickProduct')`, que es la que usa el operador) · sonda
de navegador · Pint · docs-check · **suite 4.263 verde**.

---

## 9. T3 · El calendario, y la cesta a la oferta (2026-09-04, `#464`)

La tanda tiene dos mitades de naturaleza distinta y por eso viajan juntas pero se leen aparte: la
**pantalla** (§9.1) y la **única corrección de dominio** del encargo (§9.2).

### 9.1 · La pantalla: un calendario grande, y una sola puerta a la fecha

`[owner]`: «la fecha la selecciona de un calendario grande, bien visible». **Medido antes de tocar
nada**, el calendario del panel era **un popover de 259×248 px con 30 celdas de 29×28** —bajo el
mínimo táctil de 44 en los dos ejes— **detrás de un CTA**, o sea a dos toques. Lo que se veía sin
tocar nada era la tira de 14 días.

▶ **`[DECIDIDO owner, 2026-09-04]`: la tira se RETIRA, manda el calendario.** Se preguntó con el
coste de las dos salidas delante: con el calendario desplegado, tira y rejilla son **dos puertas a la
misma pregunta**, «hoy» sigue estando a un toque en las dos, y la tira cuesta **90 px** de una
pantalla que ya no cabía. ⚠️ Eso **corrige a `auditoria-panel-admin.md` §7**, que daba la tira por
buena — lo era cuando el calendario vivía escondido.

Qué hay ahora en código:

- **`calendarMonth()`** compone la rejilla en el SERVIDOR: semanas de 7, lunes primero, los días de
  otro mes como HUECOS (no números atenuados: dos grises que significan cosas distintas se pulsan
  igual de mal), y cada día con `offerable`, `selected` y `today`.
- **`pickDay()`** es la ÚNICA puerta a la fecha y **re-valida en el servidor** (`AFORO-02`). Con ella
  muere `onDateChosen()`: existía porque había DOS escritores que podían divergir.
- **`goToMonth()` + `offerableMonths()`**: las flechas saltan al mes **ofrecible** anterior/siguiente
  —no al de al lado—, y no se pintan si no llevan a ninguna parte.
- Se retiran la tira, el CTA «Abrir calendario», el `DatePicker` y sus topes (`minOfferableDate`,
  `maxOfferableDate`, `disabledOfferDates`), con su CSS y sus cinco claves de idioma.
- **El bloque de franjas se trae a la vista al elegir día** (evento `cmo-day-chosen`): con el
  calendario delante, las horas caen fuera de una tablet de 810 px y el operador elegía día sin ver
  pasar nada. Medido: `scrollY` pasa a 208 y las franjas quedan enteras dentro de la ventana.

**Medido en navegador (iPad horizontal, 1080×810):** calendario **544×371**, celda **72×48**, flecha
de mes **44×44**, **30 días** con 4 apagados, **cero** controles bajo 44 px en el paso y **cero**
desbordamiento horizontal. ⚠️ Los dos que había bajo 44 eran del **indicador de pasos** de la T1
(204×29 y 98×29): el botón se come ahora el relleno del chip en vez de crecer por su cuenta, así que
la diana es el chip entero y el indicador solo gana **4 px por fila**.

### 9.2 · La cesta a la oferta, sin inventar una segunda derivación

`timeMap()` pasa a `SlotOffer::offerableTimes()` los ocupantes provisionales de su propia cesta.
**Medido en navegador de punta a punta**: con 7 entradas de las 10:00 ya en el carrito, la segunda
línea ofrece **33 plazas donde antes seguía diciendo 40**.

⚠️⚠️ **La cuenta no se escribe en el panel.** Sube entera a `CartOccupants` —la derivación única de
`hora-extra.md` §7·D1— que gana dos piezas:

- **`packs()`**: el cupo de fiestas, que vivía dentro de `AvailabilityReader::occupantsOf()`. Es OTRO
  pool (#82) y por eso es otra lista, no otra forma de contar lo mismo.
- **`forCart()`**: la cesta EN BRUTO → los dos grupos. **El saneado y el filtro de «qué producto
  retiene aforo» van dentro a propósito**: son parte de la respuesta, y una copia que filtrara
  distinto ofrecería horas que el cobro rechaza.

`AvailabilityReader` la pide en vez de tenerla. `OrderCreator` no cambia: ya trae sus productos
resueltos de la transacción y llama a `entries()` directamente, y su `otherPackOccupants()` —que
excluye por índice la línea en validación— **se queda donde está**: unificarlo es tocar el camino del
COBRO y no es lo que esta tanda arregla. El número de derivaciones no sube: la oferta pasa a tener
UNA, compartida por web, API y panel.

⚠️ **La huella de la cesta entra en la clave del memo** de `timeMap()`. Contar líneas no vale —quitar
una y añadir otra deja el mismo número y otra ocupación—, y un memo que no ve entrar un dato sirve
«el número de antes», que aquí son plazas que ya no están libres. Es la lección de `#329` por la otra
puerta.

### 9.3 · Lo que el arnés y las sondas enseñaron

1. ⚠️⚠️ **Dos guardas miraban el modelo de vista y no la PANTALLA.** Quitar el `@disabled` del
   calendario o la marca del día elegido **pasaba en verde**: el servidor seguiría rechazando el día,
   así que no habría daño en los datos — el operador pulsaría y no pasaría nada, que es la peor clase
   de defecto porque es silencioso. *Que el servidor sepa la respuesta no es que la pantalla la
   enseñe.* Entró un caso que asevera sobre el HTML renderizado, acotado al botón del día.
2. ⚠️⚠️ **Una comprobación sobraba y el arnés lo dijo**: validar el mes dentro de `goToMonth()` no
   mordía, porque el LECTOR ya descarta un mes sin oferta. Y no es una redundancia inocente:
   `$calMonth` es una propiedad **pública** de Livewire, o sea que el navegador puede escribirla sin
   pasar por la acción — la defensa de fuera daba sensación de defensa **sin defender nada**. Se
   retiró, y la guarda ahora escribe la propiedad a pelo.
3. ⚠️ **Una mutación mató la línea equivocada**: `$this->data['sel_time'] = null;` está escrita en
   TRES sitios, y `replace(…, 1)` muta la primera. Al acotarla con su contexto se vio que **el
   reinicio tras añadir al carrito no tenía guarda** —el caso se llamaba «…and resets selection» y
   solo aseveraba el producto—. Hoy asevera los seis campos.
4. ⚠️ **El filtro del arnés decide qué puede morder**: la mutación anterior seguía sin morder porque
   el fichero con su guarda no entraba en el `--filter`. *Un arnés no mide lo que no corre.*
5. ⚠️ **La inicial del día de la semana no distingue martes de miércoles** en español (`L M M J V S
   D`). Lo cazó la guarda al aseverar la fila; el rótulo pasa a ser la abreviatura del idioma, y la
   guarda vigila la PROPIEDAD —siete rótulos distintos— y no las letras, que dependen del idioma.
6. ▶ **Coste medido del paso** (catálogo real): `calendarMonth()` **8 consultas / 343 ms** y
   `timeChips()` **39 / 371 ms**. El grueso no son las consultas —la cruda son 9,9 ms—: es
   `SlotOffer::offeredSlots()` hidratando las ~1.900 franjas del horizonte para responder por un mes.
   ✅ **ARREGLADO al día siguiente en `#465`** (`INVARIANTES` `AFORO-02`): el paso queda en **94,6 ms**
   y el calendario del CLIENTE en la web, que llamaba a lo mismo, en **44,8**.
7. ▶ **Y de paso, un N+1 del elegidor de producto**: pintar las 18 tarjetas costaba **54 consultas**
   —tres por producto, `prices`, `price_tiers` y la `rate_type` de cada precio— y con la precarga son
   **5**. Guarda nueva que asevera la PROPIEDAD (mismo coste con 2 productos que con 12) en vez de un
   techo, porque un techo se queda viejo en cuanto alguien añade una relación legítima.

**Verificación**: `CreateManualOrderCalendarTest` (7 casos) · `CreateManualOrderCartAvailabilityTest`
(7 casos) · **14/14 mutaciones muerden** (`scripts/mutar-asistente-t3.sh`, con control verde y
restaurando por copia de seguridad) · los 147 casos previos de esta pantalla y de `SlotOffer` en
verde tras re-apuntar seis por SUJETO · **los siete escenarios de `purchase:verify-oversell` sobre
InnoDB** (`CartOccupants` está en el `CRITICAL_RE`) · sonda de navegador con el recorrido entero ·
Pint · docs-check · **suite 4.275 verde**.

### 9.4 · Lo que queda de esta pantalla

- **El OJO del owner** sobre el paso «Cuándo» (y el resto del asistente).
- **La T4**: la pantalla de desenlace, y con ella los **5 controles bajo 44 px** del paso del carrito
  (§7.4).
