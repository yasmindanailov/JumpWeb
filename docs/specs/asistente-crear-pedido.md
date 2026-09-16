# El asistente de «Crear pedido» — de tres pasos a siete, con menos toques

> Estado: 🟦 **LAS CUATRO TANDAS EN EL ÁRBOL** (§7 → §10) · Fecha: **2026-09-04**
> ✅ **El owner VALIDÓ el asistente en su navegador** (2026-09-04): *«el asistente lo he visto, todo
> ok, pero la parte de que al cliente le ha llegado un formulario o lo que sea… más profesional»* —
> su único reparo, ejecutado en `#467` (§10.3.bis). **Queda solo su SEGUNDA pasada sobre ese bloque
> ya rehecho**; los pasos 1→7 no hace falta volver a validarlos.
> Encargo del owner (2026-09-03, literal en §1) · Decisiones suyas en **§3**
> Carril: **panel / UI-UX** (este ordenador, banda **`#460`–`#469`**; `#460` la auditoría,
> `#461` el shell).
> Lo que motiva la tanda: `docs/specs/auditoria-panel-admin.md` **C1** — una admin vendió un
> cumpleaños como diez entradas sueltas, y la causa medida es el desplegable plano de esta pantalla.
> La FORMA de tablet que ya tiene (dos columnas, resumen pegajoso, chips de hora, tira de días)
> viene de `panel-navegacion.md` §9–§11 y **no se tira: se reparte en pasos**.

---

## §0 · Antes de tocar

- **Las cuatro tandas en el árbol** (`#462` · `#463` · `#464` · `#466`, más `#467` el bloque del desenlace);
  el owner validó los pasos 1→7 y queda su segunda pasada sobre ese bloque. Siete pasos: Cliente · Producto ·
  Cuándo · Datos · Extras · Carrito · Pago. Las decisiones del owner están en §3.
- **La regla del salto vive en UN sitio (`stepHasSomethingToAsk()`) y un paso sin nada que preguntar SE
  SALTA** (16 de 18 productos no tienen ni un campo). **El auto-avance se engancha al CAMBIO, jamás al ESTADO**
  (si no, volver atrás rebota). «Añadir al carrito» cuelga del ÚLTIMO paso con algo que preguntar, que es
  VARIABLE: por eso vive en la navegación. Navegar y pintar no son el mismo predicado (`stepIsSkipped()`).
- **`pickProduct()` y `pickDay()` son las ÚNICAS puertas y RE-VALIDAN en el servidor** (`AFORO-02`); los tests
  las empujan a ellas, no a `data.*`. «Más info» es de LECTURA: no elige. La tira de 14 días se RETIRÓ
  (`[DECIDIDO owner]`). Las ventajas se leen con `tr()` o salen en los tres idiomas.
- **La CESTA entra en la oferta del panel**: la cuenta subió entera a `CartOccupants::forCart()` + `packs()`,
  la derivación ÚNICA; la huella de la cesta va en la clave del memo. `$calMonth` es una propiedad PÚBLICA:
  validarla dentro de la acción daba sensación de defensa sin defender nada.
- **El desenlace no promete nada que no haya pasado**: hay clientes SIN correo y con ellos
  `ManualOrderFulfiller` no envía nada; el bloque usa el MISMO predicado (`filled($email)`) y la guarda compara
  con lo NOTIFICADO. `create()` vacía el carrito (es lo que impide cobrar dos veces); del desenlace no se
  navega. «Lo que recibe el cliente» es UN bloque con una fila por entregable y TRES estados.
- `assertSee` no ve un modal de Filament; una guarda de salto comprueba el MOVIMIENTO, no solo el predicado.
  Cinco guardas `CreateManualOrder*Test` con sus arneses de mutación. Anexo al final con la fila del enrutador.

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
| **T4** ✅ | **El desenlace**: pantalla de pedido creado con lo que de verdad pasó | Toca correos y post-form: es donde más fácil es prometer lo que no ocurrió |

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
- **Sí queda anotado**: en el paso del carrito hay controles bajo 44 px. ⚠️ **Esta frase decía «5, los
  de quitar línea y el paginador del resumen» y al medirlos en la T4 eran OTROS**: **cuatro**, y los
  cuatro eran **chips del indicador de pasos** —los de un paso sin elección escrita, que miden una
  línea—. El de quitar línea ya estaba bien. *Una cifra anotada de memoria envejece igual que un
  comentario.* ✅ Cerrado en la T4 (§10): **0** en las dos pantallas.

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

---

## 10. T4 · El desenlace (2026-09-04, `#466`)

`[owner]`: «después de crear el pedido, una pantalla nueva: pedido creado correctamente…». Hasta hoy
había **un *toast* y una redirección a la ficha del pedido**: el operador aterrizaba en 2.347 px de
administración, con el cliente delante y sin que nada le dijera qué hacer ahora.

### 10.1 · La regla de la pantalla: no prometer nada que no haya pasado

El mostrador la lee en voz alta, así que su afirmación más peligrosa es **«se le ha enviado»**.

⚠️⚠️ **Hay clientes SIN correo** —el alta de mostrador solo pide teléfono (`#263`)— y con ellos
`ManualOrderFulfiller` **no envía NADA**: ni la confirmación, ni el formulario de invitados, ni el
justificante; lo deja en el log y el enlace hay que entregarlo a mano. Una pantalla que lo diera por
enviado mandaría al operador a casa creyendo que el cliente tiene su enlace.

▶ Por eso el bloque del correo sale del **MISMO predicado** que usa el fulfiller (`filled($email)`) y
las listas, de las **MISMAS autoridades** que él consulta (`needsGuestForm()` por reserva y
`guardianReservations()`). Y la guarda central **no comprueba textos: compara lo que la pantalla dice
con lo que se ha NOTIFICADO de verdad** (`Notification::fake()`), así que si el fulfiller cambia a
quién le manda qué, la pantalla se pone roja en vez de empezar a mentir.

Qué enseña, en orden: el **código** (grande, es lo que se dicta por teléfono) · qué se ha reservado ·
**cobrado ahora + el método** y el **libro** del pedido —pintado por `reservation-financials`, el
pintor ÚNICO (`#311`): esta pantalla no compone ni un importe— · qué se ha enviado **o el aviso de
que no** · los **enlaces copiables** para entregar a mano · los menores que la asignación no pudo
colocar · y dos salidas: **«Crear otro pedido»** y «Ver el pedido».

### 10.2 · Lo que sustituyó a la redirección, y lo que había que reponer con ella

⚠️⚠️ **La redirección era también lo que impedía cobrar dos veces**: al terminar, la página dejaba de
existir. Sin ella el estado sigue vivo en el navegador, así que un doble clic —o un `wire:click`
repetido a mano— crearía **otro pedido idéntico**. Lo impide que `create()` **vacíe el carrito**: la
segunda llamada se encuentra la guarda de «carrito vacío». Hay caso propio que llama a `create()` dos
veces, y no confía en que el botón ya no esté.

⚠️ **Y del desenlace no se navega.** `next()`, `back()` y `goToStep()` se cierran mientras hay pedido
en pantalla: un «atrás» llevaría a un asistente con el carrito vacío y un pedido ya cobrado detrás —
ni el estado de antes ni el de después—. La única salida es **«Crear otro pedido»**, que limpia todo
**incluido el cliente**: en un mostrador el siguiente es de otra persona, y dejar al anterior puesto
es la forma más fácil de cobrarle a quien no era (el mismo motivo que «Nueva búsqueda» en la puerta).

### 10.3 · Los controles bajo 44 px, cerrados con su medida

- **El paso del carrito: 0** (eran **4**, no los 5 que §7.4 daba por memoria, y eran los **chips del
  indicador**, no los de quitar línea). ⚠️ El arreglo de `#464` salvaba a los chips de DOS líneas
  (45 px) y **dejaba en 36 los de UNA** —los pasos sin elección escrita—; el `min-height` va en el
  BOTÓN, que es quien decide el alto del chip por los márgenes negativos.
- **El desenlace: 0.** Los dos CTA salían a **40 px** con el `size="lg"` de Filament.
- **Y de paso, parte del M7 de la auditoría**: «Ver el desglose» medía **86×16** y es del pintor
  compartido del libro → **86×44** en las **cuatro** superficies del panel que lo pintan, sin tocar el
  cuerpo del texto.

### 10.3.bis · El pulido del OJO del owner (`#467`)

`[owner]`, tras probar el asistente entero: *«todo ok, pero la parte de que al cliente le ha llegado
un formulario o lo que sea… más profesional, mejor UI/UX»*.

El bloque eran **DOS**: «se le ha enviado por correo» arriba, con una lista suelta, y «enlaces para
entregar a mano» abajo — y el operador tenía que **emparejar de cabeza** cada correo con su enlace.

▶ Ahora es **UNO: «Lo que recibe el cliente»**, y cada cosa que el cliente tiene que recibir es una
FILA — qué es · de qué reserva y cuándo · **su estado en pastilla** · y su enlace copiable debajo—.

⚠️⚠️ **Los estados son TRES y no dos, y lo cazó el ojo, no la sonda**: «Enviado a …» (verde),
«Entrégalo tú» (ámbar) y **«No enviado»**. La confirmación del pedido **no tiene enlace**, así que
pedirle al operador que la «entregue» sería mandarle a hacer algo que no existe.

⚠️ El aviso de «sin correo» sube a **banda ámbar dentro de la sección**, delante de la lista: es lo
que hay que ver antes de despedir al cliente, no una nota al pie.

⚠️ **La pista del partial de copiar pasa a ser opcional** (`hint`): en el modal de la ficha es todo el
contenido y orienta; repetida por fila era ruido. Y el partial gana diana táctil —input 38 → 44,
botón 36 → 44—, lo que también arregla las otras superficies que lo incluyen.

**Medido en navegador**, con un pack que trae formulario de invitados **y** justificante: las tres
filas correctas en los dos casos, **0 controles bajo 44 px** y sin desbordamiento; el alto pasa a
986 px (con correo) y 1.065 (sin) sobre 810 — el código y el dinero quedan sobre la línea de flotación
y los enlaces piden un deslizamiento, que es su sitio.

### 10.4 · Lo que enseñó el arnés (9/13 la primera vez, **15/15** al final)

1. ⚠️⚠️ **El caso «sin correo» no tenía SUJETO**: compraba una entrada, así que «formularios
   enviados» y «justificantes enviados» valían 0 **con y sin la regla** y las dos mutaciones pasaban
   en verde. Hoy compra exactamente lo mismo que el caso con correo — *lo que distingue los dos casos
   tiene que ser SOLO el correo*.
2. ⚠️⚠️ **El código del pedido se aseveraba sobre la página entera** y la URL de la ficha
   (`/admin/orders/R-XXXX`) **lo lleva dentro**: la guarda pasaba con el hueco del código vacío.
   Acotada al elemento. Es la lección de `#295`/`#303`, otra vez.
3. ⚠️ **Una mutación EQUIVALENTE, dicha en el arnés**: filtrar las reservas por `needsGuestForm()` da
   lo mismo que no filtrarlas **recién creado el pedido** (todas están pendientes). El filtro se
   conserva porque es literalmente la condición del fulfiller, y el arnés explica por qué no la muta.

**Verificación**: `CreateManualOrderDoneTest` (6 casos) · **15/15 mutaciones**
(`scripts/mutar-asistente-t4.sh`) · el caso de los menores re-apuntado por sujeto (era un *toast*) ·
la nueva superficie registrada en `LedgerSingleSourceTest` · sonda de navegador (0 controles bajo 44
en carrito y desenlace, 810 px de 810, sin desbordamiento) · Pint · docs-check · **suite 4.290 verde**.

## Anexo · La fila del enrutador, mudada el 2026-09-16

> Lo que decía la fila **«El ASISTENTE de «Crear pedido» · los pasos · el auto-avance · el carrito · la pantalla de éxito»** de `CLAUDE.md` cuando el enrutador bajó a una línea por fila
> (`DECISIONES #619`). Se conserva **verbatim** porque es historia de trampas medidas: léelo
> después del §0 y no lo reescribas. Documentos que la fila citaba: `docs/specs/asistente-crear-pedido.md` · `docs/DEUDA.md` · `docs/specs/auditoria-panel-admin.md`.

- **`docs/specs/asistente-crear-pedido.md`**
- 🟦 **LAS CUATRO TANDAS EN EL ÁRBOL** (`#462` · `#463` · `#464` · **`#466`**, 2026-09-04; queda el OJO del owner) — de tres pasos a **siete**: Cliente · Producto · Cuándo · Datos · Extras · Carrito · Pago.
- ❗❗❗ **SI TOCAS LOS PASOS**: la regla del salto vive en **UN** sitio (`stepHasSomethingToAsk()`) y **un paso sin nada que preguntar SE SALTA** — medido: de 18 productos vendibles **16 no tienen ni un campo que rellenar**, así que sin el salto vender una entrada obligaba a pasar por una pantalla en blanco.
- ⚠️⚠️ **El auto-avance se engancha al CAMBIO, jamás al ESTADO**: si mirase el estado, volver atrás a «Cuándo» con la hora puesta rebotaría hacia adelante y el operador quedaría atrapado sin poder corregir.
- ⚠️⚠️ **«Añadir al carrito» cuelga del ÚLTIMO paso con algo que preguntar, que es VARIABLE** (Extras si los hay; si no Datos; si no Cuándo) — por eso vive en la navegación (`manual-order-nav.blade.php`) y no dentro del formulario de un paso fijo.
- ⚠️⚠️ **El predicado de NAVEGAR y el de PINTAR no son el mismo**: `stepIsSkipped()` exige que haya producto, porque el indicador decía «sin nada que rellenar» en el paso 1 sin que hubiera nada elegido — *afirmar lo que aún no se sabe*, y lo vio la sonda, no un test.
- ⚠️ **`STEP_PRODUCTS` ya no existe.**
- ▶ **Tres decisiones del owner** (§3): **la CANTIDAD va arriba de la pantalla de fecha** (así las horas dicen la verdad para esa cantidad) · **el calendario resalta días reservables y las plazas se ven con las horas** —pintarlas por día cuesta **709 consultas y 11,4 s** y NO hay vía agregada por rango: el semáforo sería tanda propia sobre AFORO— · **el post-form NO se renombra**.
- ✅ **§2.3 CERRADA por `#464`** (era la única corrección de DOMINIO del encargo): web y panel no divergían —los dos son `SlotOffer`— pero **el panel no le pasaba la cesta**; con «añadir más productos» eso pasó de borde a camino normal.
- ⚠️ **Si escribes una guarda de salto, comprueba el MOVIMIENTO y no solo el predicado**: dos mutaciones no mordieron por eso, y hizo falta un tercer sujeto (una entrada CON complemento) para hacerlo observable. Guarda: `CreateManualOrderStepsTest` (13 casos · 9/9 mutaciones) —
- ❗❗❗ **`#463` SI TOCAS EL ELEGIDOR DE PRODUCTO** (§8): murió el `Select` plano de 18 opciones —el crítico **C1**, el que ya costó **61,00 €** y una sala sin reservar— y **lo que cierra el agujero es el AGRUPADO**, no la tarjeta: se elige dentro de «ENTRADAS» o dentro de «PACKS Y CELEBRACIONES», y el **rango de invitados**, que solo pintan los packs, es la marca inconfundible de un producto de grupo.
- ⚠️⚠️ **`pickProduct()` es la ÚNICA puerta y RE-VALIDA en el servidor** (un `wire:click` se puede llamar con cualquier id, `AFORO-02`) y hace los mismos olvidos que hacía el `afterStateUpdated` del `Select` —hora, menores, campos, complementos—; los tests empujan `pickProduct`, no `data.sel_product_id`.
- ⚠️ **«Más info» es de LECTURA: abrirlo NO elige el producto**, o el operador no podría comparar dos candidatos sin comprometerse con el primero.
- ⚠️⚠️ **Las ventajas se leen con `tr()`**: `features` es traducible y recorrer el mapa a mano las sacó **en los tres idiomas a la vez** —lo vio la sonda, no un test—.
- ⚠️ **`productOptions()` murió con el `Select`; `productLabel()` NO** (es el rótulo de la línea en el carrito).
- ⚠️ **`assertSee` no ve un modal de Filament** (`wire:partial`, la trampa de `#161`): se asevera por conducta sobre `productInfoFields()`.
- ⚠️ **`ticket_types.conditions` NO se pinta**: cero consumidores y el catálogo no la edita (ficha en `DEUDA.md`). Guarda: `CreateManualOrderProductCardsTest` (11 casos · 9/9 mutaciones) —
- ❗❗❗ **`#464` SI TOCAS LA FECHA, LAS HORAS O `CartOccupants`** (§9): manda **un calendario grande siempre visible** y `[DECIDIDO owner]` **la tira de 14 días SE RETIRA** —eran dos puertas a la misma pregunta y costaba 90 px—, lo que **CORRIGE a `auditoria-panel-admin.md` §7**, que la daba por buena cuando el calendario vivía plegado tras un CTA (medido: **popover de 259×248 px, celdas de 29×28**, dos toques).
- ⚠️ **`pickDay()` es la ÚNICA puerta** y con ella muere `onDateChosen()`, que existía porque había dos escritores; el servidor **re-valida** el día (`AFORO-02`) y las flechas saltan al mes **OFRECIBLE**, no al de al lado.
- ⚠️⚠️ **La CESTA entra en la oferta del panel**: `timeMap()` pasa los ocupantes provisionales y —medido en navegador— con 7 entradas en el carrito la segunda línea ofrece **33 plazas donde antes decía 40**. **La cuenta NO se escribe en el panel**: subió entera a **`CartOccupants::forCart()` + `packs()`**, que sigue siendo la derivación ÚNICA (con el saneado y el filtro de «qué producto retiene aforo» DENTRO, porque son parte de la respuesta); `OrderCreator` no cambia y su `otherPackOccupants()` se queda.
- ⚠️ **La huella de la cesta va en la clave del memo** —contar líneas no vale—, la lección de `#329` por la otra puerta.
- ⚠️⚠️ **Si escribes una guarda de esta rejilla, mira la PANTALLA y no solo el modelo de vista**: quitar el `@disabled` o la marca del día elegido pasaba en VERDE —el servidor sigue rechazando, así que el operador pulsa y no pasa nada—.
- ⚠️⚠️ **`$calMonth` es una propiedad PÚBLICA**: validar el mes dentro de `goToMonth()` no mordía porque el LECTOR ya lo descarta, y el navegador puede escribirla sin pasar por la acción — la defensa de fuera **daba sensación de defensa sin defender nada**.
- ⚠️ **La inicial del día de la semana no distingue martes de miércoles** (`L M M J V S D`): el rótulo es la abreviatura del idioma y la guarda vigila la PROPIEDAD, no las letras.
- ⚠️ **Las franjas se traen a la vista al elegir día** (evento `cmo-day-chosen`): con el calendario delante caen fuera de una tablet de 810 px.
- ▶ Guardas: `CreateManualOrderCalendarTest` (7) · `CreateManualOrderCartAvailabilityTest` (7) · **14/14 mutaciones** (`scripts/mutar-asistente-t3.sh`) · los SIETE escenarios de `purchase:verify-oversell` —
- ❗❗❗ **`#466` SI TOCAS EL DESENLACE O LO QUE SE LE ENVÍA AL CLIENTE** (§10): la pantalla de «pedido creado» **no puede prometer nada que no haya pasado** — **hay clientes SIN correo** (el alta de mostrador solo pide teléfono, `#263`) y con ellos `ManualOrderFulfiller` **no envía NADA**: ni confirmación, ni formulario de invitados, ni justificante. El bloque del correo usa el **MISMO predicado** que el fulfiller (`filled($email)`) y las listas, sus **MISMAS autoridades**; la guarda **compara con lo que se ha NOTIFICADO de verdad**, no con un texto.
- ⚠️⚠️ **La redirección a la ficha era también lo que impedía COBRAR DOS VECES**: hoy lo impide que `create()` **vacíe el carrito**, con caso que llama a `create()` dos veces.
- ⚠️ **Del desenlace no se navega** (`next`/`back`/`goToStep` cerrados); la única salida es «Crear otro pedido», que limpia todo **incluido el cliente**.
- ⚠️ El dinero lo pinta `reservation-financials`, el pintor ÚNICO (`#311`): aquí no se compone ni un importe, y la superficie está registrada en `LedgerSingleSourceTest`.
- ⚠️ **Los controles bajo 44 px del carrito eran CUATRO y eran los chips del indicador**, no los cinco «de quitar línea» que la spec daba de memoria: el `min-height` va en el BOTÓN del chip, que es quien decide su alto.
- ⚠️⚠️ **`#467` (el OJO del owner): «Lo que recibe el cliente» es UN bloque con una FILA por entregable** —qué es · de qué reserva · estado en pastilla · enlace copiable debajo—, no dos listas que el operador tenía que emparejar de cabeza; y **los estados son TRES**: «Enviado a …», «Entrégalo tú» y **«No enviado»** —la confirmación no tiene enlace, así que pedir que se «entregue» era mandar a hacer algo que no existe—.
- ⚠️ La pista del partial de copiar es opcional (`hint`) y su fila ganó diana táctil (38/36 → 44), lo que arregla también las otras superficies que lo incluyen.
- ▶ Guarda: `CreateManualOrderDoneTest` (6 casos · **15/15** mutaciones, `scripts/mutar-asistente-t4.sh`)
