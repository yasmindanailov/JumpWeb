# El asistente de «Crear pedido» — de tres pasos a siete, con menos toques

> Estado: 🟦 **DISEÑO CERRADO · T1 EN EL ÁRBOL** (§7) · quedan T2, T3 y T4 · Fecha: **2026-09-04**
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

Hoy es un borde declarado (`hora-extra.md` §8.3). **El asistente nuevo lo convierte en el camino
normal**: con «añadir más productos», el operador mete 20 entradas a las 17:00, vuelve, y el
calendario le dice que quedan 40. ▶ **Se corrige en la T3**, que es la que toca esa pantalla.

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
| **T3** | **Cuándo**: cantidad arriba, calendario grande, horas con plazas — **y la cesta a `SlotOffer`** | Lleva la única corrección de DOMINIO del encargo (§2.3) y no debe viajar con cambios de presentación |
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
