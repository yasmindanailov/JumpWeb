# [AUDITORÍA] El panel, con un dedo — auditoría UI/UX del panel de administración

> Estado: ⬜ **INFORME ENTREGADO · nada ejecutado** · **D1, D2 y D3 decididas** (§9, 2026-09-03);
> **D4 pendiente del detalle del owner**, que es lo que ordenará el plan.
> ▶ **D2 y D3 convierten M4 y M3 en conducta querida, no en defectos: están en §9.bis y no se
> re-litigan.** D1 acota el alcance a **seis pantallas** (§9.ter).
> Fecha: **2026-09-03** · Encargo del owner: *«valorar el UI/UX del panel de admin. Objetivo: que
> cualquier persona intuitivamente pueda hacer la operativa del parque; quitar ruido y poner cada
> cosa en el momento adecuado y cada acción en su sitio. Normalmente se usa en tablet.»*
> Carril: **panel / UI-UX** (este ordenador, banda reservada **`#460`–`#469`**, a distancia de
> `#400`–`#413` y `#430`–`#452`).
> Doc funcional del panel: `docs/PANEL-ADMIN.md` (qué hace cada pantalla).
> Forma del panel y su historia: `docs/specs/panel-navegacion.md` (§1 el problema medido en
> agosto, §6 lo que quedaba abierto). **Esto no lo sustituye: lo continúa con el panel de hoy
> delante.**
> ⚠️ **No se ha tocado una línea de producto.** Todo lo de aquí es medición.

---

## 0. Lo que hay que saber en un minuto

- **Método**: Playwright + Chromium sobre el panel real con los datos locales (64 pedidos, 82
  reservas), **10 pantallas × 3 tamaños** (iPad horizontal 1080×810 —el dispositivo declarado—,
  iPad vertical 810×1080 y escritorio 1440×900 de control), más cinco sondas de estados de trabajo
  (el modal «Gestionar», la ficha de puerta con un cliente real, el calendario abierto, el buscador
  y el paso 1 de «Crear pedido»). Instrumentos y capturas fuera del repo, en `/root/e2e/audit/`
  del contenedor.
- **Veredicto: 4 críticos · 10 mayores · 11 menores** — y tras las decisiones del owner (§9), **dos
  de los mayores dejan de ser hallazgos** (M3 y M4: son conducta querida) y otros dos quedan fuera
  de alcance (§9.ter). El trabajo de agosto (`#223` el menú plano,
  `#224` el buscador, `#232` la puerta en kiosco, `#240`–`#242` «Crear pedido» en tablet) **se
  sostiene y se nota**: donde se pasó, la pantalla está limpia. Lo que queda es lo que **no** se
  pasó, y es justo la operativa del día.
- **El hallazgo número uno YA PASÓ en el parque** (`[OWNER, 2026-09-03]`): una admin vendió un
  cumpleaños como **diez entradas sueltas** en vez de un pack de 10 invitados. Medido con el
  catálogo real: **119,00 € en vez de 180,00 € (−61,00 €, −34 %)**, la sala de cumpleaños sin
  reservar, **60 min de ocupación en vez de 120** y **el formulario de invitados que nunca se
  pide** — sin él no hay edades, y sin edades no hay suplemento de fiesta mixta. La causa está
  medida: **un desplegable plano de 18 opciones con entradas y packs mezclados y ni una palabra que
  diga cuál es cuál**. Y **el error no se puede deshacer**: el panel solo deja cambiar un producto
  por otro del mismo tipo y la misma zona (C1).
- **La segunda**: en el modal «Gestionar», **«Cancelar producto» —que anula la reserva— es el botón
  más ancho y más saturado de los tres, y está a 12 px de «Guardar cambios»**. En un modal,
  «Cancelar» significa universalmente «descartar cambios». Con un dedo y un cliente delante, esto
  anula una fiesta (C2).
- **La tercera: la acción principal de una reserva es un icono gris de 40×40 sin rótulo**, uno de
  hasta seis idénticos en la misma fila — y otro de esos seis retira una credencial en el acto (C3).
- **La cuarta: en la puerta, con un cliente con menores declarados, el botón «Nueva búsqueda» cae
  fuera de pantalla** (ficha de 907 px en 810 de alto). Ese botón es lo que quita de la vista los
  datos del cliente anterior: la spec lo declaró **privacidad, no comodidad**, y tiene guarda que
  impide ocultarlo. Hoy hay que ir a buscarlo desplazando (C4).
- **Y sobre «un montón de accionadores y datos que no están en su sitio»** (`[OWNER]`), medido sobre
  un pedido con **UNA sola reserva**: **14 acciones registradas** en el código y **66 controles
  alcanzables** (15 al abrir · 4 más al desplegar · 47 dentro del modal «Gestionar»). Con todo
  abierto la ficha mide **2.347 px**, de los cuales la reserva —lo operativo— ocupa **572** y la
  administración **1.503**, casi el triple; debajo de la reserva quedan **1.583 px de columna
  vacía**. Y el mismo dato se repite: lo pagado sale **5 veces**, la misma fecha **6** (§5.bis).
- **Y un patrón que atraviesa todo**: el panel **no sabe cuándo es la visita**. «Pedidos» ordena y
  filtra por fecha de COMPRA, el buscador global enseña «Creado el», y ni «Hoy» ni la hoja impresa
  del día dicen quién llega debiendo dinero, quién no ha firmado o quién trae menores.

---

## 1. Método, y las CINCO trampas de instrumento que se pagaron

El panel se midió sobre `main` (commit `16e3fca5`), con Docker arriba y la BD local. Una sola
sesión de navegador cambiando el viewport, porque **el login del panel tiene limitador** y varias
entradas seguidas hacen que la sonda empiece a medir la pantalla de login (la trampa que
`panel-navegacion.md` §8.6 ya había pagado).

Las cinco veces que el instrumento mintió, en orden de coste:

1. ⚠️⚠️ **La primera pasada midió un 404 durante tres tamaños.** La ficha de pedido se pidió como
   `/admin/orders/193` y `Order` se resuelve por **`code`**, no por id: lo que se midió fue la
   página de error **del sitio público**, con cifras perfectamente plausibles (1.009 px de alto, 34
   controles) y un solo síntoma raro — «0 controles dentro de la página»—. Lo zanjó mirar la
   captura. *Un número plausible no dice que estés midiendo el sujeto.*
2. ⚠️ **Dos instrumentos míos se contradijeron sobre si las tablas se salen**: uno dio
   `overflow = 0` y el otro columnas cortadas fuera del viewport. Ganó la captura, que enseña
   «Creado el» partida a la mitad. El primero elegía mal el contenedor que scrollea.
3. ⚠️ **El barrido genérico de controles no vio las 38 píldoras del calendario**, porque
   FullCalendar no usa `<button>` ni `<a>`: son `div` con listener. Hubo que medirlas aparte, y son
   **el hallazgo M2**. *Un inventario de controles que pregunta por etiquetas HTML no ve un widget
   que no las usa.*
4. ⚠️ Un `fill` sobre «el primer campo de búsqueda» escribió en el **buscador global de la barra
   superior** en vez de en el campo «Cliente» del formulario. De regalo dejó medido que los
   resultados del buscador rotulan cada pedido con «Creado el» (M1).
5. ⚠️ **`.fi-dropdown-panel` casa con TODOS los desplegables del documento, no con el abierto**: el
   inventario de §5.bis dio **9 entradas** en el menú «Acciones del pedido» —y son **3**—, porque se
   trajo también el selector de idioma («Español», «中文») y el menú del avatar («Ajustes»,
   «Salir»). Se recontó acotando al panel abierto. *Un selector de clase no distingue el menú que se
   acaba de abrir de los que ya estaban en el árbol.*

**Lo que NO se midió** y por tanto no se afirma: contraste de color, lector de pantalla, iPad
Safari real (esto es Chromium emulando el viewport: **no hay toque real ni gestos**), rendimiento,
impresión, el estado de primer arranque (parque vacío), las 19 pantallas de Ajustes una por una, y
las pantallas del empleado en navegador (su menú sí se midió, en un proceso propio por rol, por la
memoización de Filament que `panel-navegacion.md` §1 documenta).

---

## 2. Lo medido

### 2.1 Las diez pantallas, en el dispositivo declarado (iPad horizontal, 1080×810)

| Pantalla | Alto | ¿Cabe? | Controles (en la página) | < 44 px (en la página) | La tabla se sale |
|---|---:|---|---:|---:|---|
| **Puerta** (sin ficha) | 810 | **sí** | 3 (3) | **0 (0)** | — |
| **Crear pedido** (paso 1) | 810 | **sí** | 15 (4) | 11 (**0**) | — |
| **Ficha de pedido** | 810 | **sí** | 15 | **13** | — |
| **Hoy** (periodo «Hoy») | 810 | **sí** | 25 (14) | 15 (4) | **66 px** |
| **Pedidos** | 964 | +154 | 78 (67) | 18 (7) | «Creado el» cortada |
| **Catálogo** | 964 | +154 | **111 (100)** | 21 (10) | 3 columnas cortadas |
| **Clientes** | 1.012 | +202 | 78 (67) | 18 (7) | 2 columnas cortadas |
| **Calendario** (mes) | 1.084 | +274 | 23 (12) | 23 (12) + **38 píldoras de 19 px** | — |
| **Hoy** (periodo «Este mes») | 1.184 | +374 | 66 | 16 | **103 px** |
| **Ajustes** | 1.866 | 2,3 pantallas | 31 (20) | 11 (**0**) | — |
| **Configuración** | 2.883 | 3,6 pantallas | 36 (25) | **35 (24)** | — |

En **vertical** (810×1080) la ficha de pedido pasa a **1.305 px** (se sale 225): las dos columnas
se apilan. «Configuración» sube a 3.115. El resto mejora, porque el menú lateral se esconde y el
área útil crece de 760 a 810 px — **la tablet en vertical tiene más ancho útil que en horizontal**,
como ya midió `panel-navegacion.md` §9.2.

### 2.2 El armazón, que sale en las 26 pantallas

| Control | Medida | ¿44 px? |
|---|---|---|
| **«Crear pedido»** (el CTA principal del panel) | 127 × **32** | no |
| **Menú del usuario** (única puerta a «Ajustes» y a «Salir») | **32 × 32** | no |
| Buscador global | 222 × **36** | no |
| Ítems del menú lateral (Hoy · Calendario · Pedidos · Clientes) | 273 × **40** | no |
| Cambiar idioma | 36 × 36 | no |
| Contraer barra lateral | 36 × 36 | no |

`panel-navegacion.md` §9.4 ya lo dejó anotado como deuda («los 11–12 controles bajo 44 px son del
ARMAZÓN, afectan a todas las pantallas»). Aquí quedan con nombre y número.

### 2.3 Los estados de trabajo

**Modal «Gestionar producto»** (el que edita una reserva) — 696 px de ancho, contenido de 414 px
con **126 px de scroll interno**, **27 campos**, **47 controles y 19 por debajo de 44 px**: el
botón de cerrar (36×36), las tres pestañas (36 de alto), las flechas de mes (**26×26**), **las diez
franjas horarias (331×36)** y los tres botones del pie (36 de alto).

Los tres botones del pie, medidos:

| Botón | Caja | Fondo | Separación con el anterior |
|---|---|---|---:|
| **Guardar cambios** | 138 × 36 | `oklch(0.754 0.150 37.6)` — naranja claro | — |
| **Cancelar producto** | **173 × 36** | `oklch(0.577 **0.245** 27.3)` — rojo, texto blanco | **12 px** |
| Reembolsar | 129 × 36 | `oklch(0.750 0.183 55.9)` — naranja | 12 px |

**Ficha de puerta** con un cliente real (3 menores declarados): **907 px de alto en 810 de
pantalla**. 4 controles, ninguno por debajo de 44 px. `panel-navegacion.md` §8.6 midió 810 («cabe»)
tras retirar las tarjetas de QR y visita: **la ficha ha vuelto a crecer**, y lo que se sale es el
pie.

**Calendario, vista mes**: **38 píldoras, todas de 19 px de alto** y 88 de ancho, **6 con el texto
truncado**. El día con más eventos tiene **12**, y su celda mide **300 px** frente a **75** la más
vacía. En vista **semana**: 11 eventos de 43–87 px, 7 por debajo de 44 y **7 de 11 truncados**.

**Ficha del cliente** (`/admin/users/…`): 1.617 px, **61 controles**.

**Buscador global** («probe card»): dos grupos —**«Pedidos»** primero y **«Usuarios»** después— y
cada pedido rotulado con «Cliente: … / **Creado el:** …».

---

## 3. La operativa del parque, tal como se hace hoy

Los ocho gestos del día (los de `PANEL-ADMIN.md` §2), con el camino medido. «Toques» cuenta
pulsaciones, sin contar lo que se teclea.

| # | El gesto | Camino de hoy | Toques | Estado |
|---|---|---|---:|---|
| O1 | **¿Qué hay hoy?** | «Hoy» es la pantalla de arranque | 0 | ⚠️ le faltan 5 datos (M-U3) y se corta una columna |
| O2 | **Llega alguien a la puerta** | *empleado*: «Puerta» en su menú · *admin*: avatar → Ajustes → Puerta | 1 / **3** | ⚠️ crítico C4 |
| O3 | **Reserva por teléfono** | «Crear pedido» arriba → 3 pasos | 1 + pasos | ⚠️ **crítico C1**: el paso 2 deja vender un pack como entradas |
| O4 | **Cambiar hora o cantidad** | Pedidos → buscar → fila → **icono lápiz** → pestaña → día → hora → Guardar | ≥ 6 | ⚠️ críticos C2 y C3 |
| O5 | **Cobrar el saldo en el parque** | *no existe* | — | ⚠️ mayor M4 |
| O6 | **¿Queda sitio el sábado a las 17:00?** | *no hay pantalla*: hay que abrir el modal «Gestionar» de otra reserva, o empezar un pedido y llegar al paso 2 | ≥ 4 | ⚠️ mayor M3 |
| O7 | **Imprimir la hoja de una fiesta** | ficha del pedido → **icono impresora** → elegir variante | 3 | ⚠️ el icono no lleva rótulo (C3) |
| O8 | **Corregir las edades de los invitados** | ficha → **icono lápiz** → pestaña «Invitados» | 4 | ⚠️ C3 |

---

## 4. Los cuatro críticos

### C1 · Un cumpleaños se vendió como diez entradas — y la fricción está medida

**El caso es real** (`[OWNER, 2026-09-03]`): *«una de las admin hizo la reserva de cumpleaños como
una entrada: reservó 10 entradas en vez de hacer una reserva de cumpleaños pack de 10
invitados»*. No es una hipótesis de usabilidad: es un fallo de operación que ya ocurrió, y el
panel lo aceptó sin decir nada.

**Por qué se cuela, medido sobre el catálogo real.** El paso 2 de «Crear pedido» ofrece **un solo
desplegable plano de 18 opciones** con entradas y packs **mezclados**, y su rótulo es
`«{zona} · {nombre}»` — **nada dice de qué tipo es cada cosa**:

```
JUMP · Jump · 1 hora            ← entrada        JUMP · Cumpleaños E2E extras   ← PACK
JUMP · Jump · 2 horas           ← entrada        Cumpleaños · Cumpleaños Jump   ← PACK
KIDS · Kids · 1 hora            ← entrada        Excursiones · Excursión 2 h    ← PACK
…                                                SONDA146 · S146 Pack BAJA      ← PACK
```

- **La única señal es el NOMBRE del producto**, que lo escribe el cliente desde el panel. Y ni
  siquiera el prefijo de zona sirve de pista: **«JUMP · Cumpleaños E2E extras» es un pack** con
  prefijo de zona de entradas, y **«JUMP · Jump · 1 hora»** repite la palabra dos veces.
- **La cantidad significa cosas distintas** —unidades en una entrada, invitados en un pack— y el
  campo cambia de rótulo, pero **después** de haber elegido mal.
- **No hay agrupación, ni icono, ni badge, ni orden por tipo.** Las 18 van seguidas por `position`.

⚠️⚠️ **Y el panel SÍ sabe el tipo: lo enseña donde no hace falta y lo esconde donde decide una
venta.** «Catálogo» —una pantalla de configuración, que se abre cuatro veces al año— tiene columna
**«Tipo»** *y* filtro por tipo. La pantalla que cobra no tiene ninguna de las dos cosas. *El dato
existe, está a mano y no se usa en el único sitio donde equivocarse cuesta 61 €.*

**Lo que cuesta el error, con los precios de hoy para 10 personas el sáb. 19/09:**

| | Lo que se vendió | Lo que era |
|---|---|---|
| Producto | 10 × «Jump · 1 hora» | 1 × «Cumpleaños Jump», 10 invitados |
| **Dinero** | **119,00 €** (11,90 €/u) | **180,00 €** (18,00 €/u) |
| Duración reservada | **60 min** | **120 min** |
| Zona ocupada | **JUMP** | **Cumpleaños** |
| Formulario de invitados | **no lo pide** | 5 campos (de ahí salen las EDADES) |
| Mínimo / cupo de grupos | ninguno | mín. 8 · máx. 20 |
| Complementos enganchados | 3 | 4 (la tarta) |

▶ **61,00 € menos en una sola reserva (−34 %)**, la sala de cumpleaños **sin reservar**, el grupo
ocupando el doble de tiempo del que pagó —**aforo mal contado**— y **el suplemento de fiesta mixta
que nunca se calcula**, porque sin post-form no hay edades.

⚠️⚠️ **Y el error NO se puede deshacer desde el pedido.** El desplegable de «Editar producto» del
modal «Gestionar» solo ofrece productos **del mismo tipo y de la misma zona**: una entrada solo
puede cambiarse por otra entrada de JUMP. **Convertir diez entradas en un pack de cumpleaños es
imposible desde la ficha**: hay que cancelar y reembolsar las diez líneas y volver a crear el
pedido — pasando por el botón rojo de C2.

*El panel no distingue lo que para el parque son dos negocios distintos, y luego no deja
arreglarlo.*

### C2 · En «Gestionar», el botón que anula pesa más que el que guarda, y está a 12 px

Medido arriba: **«Cancelar producto» es el más ancho de los tres (173 px frente a 138) y el más
saturado** (croma 0,245 frente a 0,150), en rojo con texto blanco, mientras «Guardar cambios» va en
naranja claro. Están **a 12 px**, en la misma fila, los dos de 36 px de alto — en una pantalla que
se usa con el dedo.

Y hay un segundo filo, que es de idioma: **en un modal, «Cancelar» significa «descartar lo que
estaba haciendo»**. Aquí significa **anular la reserva**. El modal no tiene un botón de descartar
(se cierra con la ✕ de 36×36 arriba a la derecha), así que el único botón que *parece* «salir sin
hacer nada» es el que borra la fiesta.

▶ No es una hipótesis sobre el diseño: `#173` movió Cancelar y Reembolsar **desde la fila de la
reserva hasta el pie de este modal** a propósito. La decisión fue buena para el orden; el resultado
es que las tres acciones de naturaleza distinta comparten fila y jerarquía.

### C3 · La acción principal de una reserva es un icono gris sin rótulo, uno de hasta seis iguales

La fila de acciones de cada reserva puede tener **hasta seis** iconos, **todos `color="gray"`,
`size="lg"`, todos sin texto**: ver en el calendario · imprimir la hoja · copiar el enlace del
formulario de invitados · **rotar ese enlace** · enlace del justificante de un menor · **gestionar**
(el lápiz). En el pedido medido salen **cinco, de 40×40** — por debajo del mínimo táctil.

Dos consecuencias medidas:

- **La acción más usada (editar la reserva) no se distingue de las accesorias.** Es la puerta a la
  fecha, la hora, la cantidad, el producto, los complementos y las edades de los invitados.
- **Una de las seis retira una credencial en el acto** (rotar el enlace del formulario invalida el
  que ya circuló). Está pintada exactamente igual que las otras cinco, a 12 px de distancia.

⚠️ **Y en tablet no hay hover**: el `aria-label` que las distingue no se puede leer con el dedo.

### C4 · En la puerta, «Nueva búsqueda» se sale de la pantalla en cuanto el cliente trae menores

**907 px de contenido en 810 de pantalla.** El botón que se pierde por abajo no es decorativo:
`panel-navegacion.md` §8.3 lo dejó escrito con todas las letras —*«además de vaciar el campo, quita
de la pantalla la ficha del cliente anterior… es privacidad, no comodidad, y hay guarda que impide
ocultarlo»*—. Hoy sigue existiendo y **hay que desplazarse para alcanzarlo**, en una tablet fija en
un soporte, con el siguiente de la cola delante.

La causa es aritmética: la tarjeta «Menores a cargo» crece con cada menor (3 menores = 3 filas de
44 px + cabecera). La medición de §8.6 se hizo con una ficha sin menores.

---

## 5. Los diez mayores

**M1 · El panel no sabe cuándo es la visita.** «Pedidos» tiene 6 columnas —Código · Cliente ·
Estado · Total · Pagado · **Creado el**—, ordena por `created_at desc` y filtra por estado y por
reembolso. **Ni columna, ni orden, ni filtro por fecha de visita.** El buscador global repite el
patrón: cada resultado dice «Creado el». `panel-navegacion.md` §1 ya lo midió como problema el
2026-08-28 (««Pedidos» ordenaba por fecha de compra, no de visita»); sigue igual. Para un parque,
el pedido es un objeto contable y **la reserva es el objeto operativo**.

**M2 · El calendario del mes no contesta nada.** 38 píldoras de **19 px**, 6 truncadas; para saber
qué hay un día hay que acertar con el dedo en una de ellas. Y la rejilla salta: 300 px la celda del
día con 12 reservas contra 75 la vacía. La vista semana mejora el alto (43–87) pero **trunca 7 de
11**.

**M3 · Ninguna pantalla contesta «¿cuánto aforo queda?».** El calendario pinta **productos
vendidos**, no ocupación, y su propia documentación dice que **el día no es clicable**. «Franjas»
(en Ajustes) enseña la **capacidad**, no lo ocupado. La única forma de ver plazas libres por hora
es abrir el modal «Gestionar» de una reserva existente, o empezar un pedido y llegar al paso 2.
⚠️ `PANEL-ADMIN.md` §2.1 describe justo lo contrario —«Clic en día/franja → detalle: quién viene,
**ocupación, plazas libres**»—: **el doc funcional promete una pantalla que no existe.**

**M4 · El panel dice cuánto cobrar en el parque y no deja registrarlo.** La ficha de puerta pinta
«Pendiente de cobrar en puerta: 44,00 €» y no hay ninguna acción de cobro. La liquidación la
**infiere** el libro por el paso del tiempo (se fecha al fin de la franja del principal), así que
un pedido queda «liquidado» tanto si se cobró como si no. La constante del cobro presencial está
declarada y **sin un solo uso**. ▶ Es coherente con `#244` (nada se cobra online después de
reservar) y `cumple-mixto.md` lo dejó anotado —«el registro real del cobro es feature aparte»—:
esto no es un defecto sorpresa, es **un hueco conocido que la operativa nota todos los días**.

**M5 · En tablet se pierde la última columna de todas las tablas.** Medido a 1080×810: «Hoy» se
sale 66 px con 2 filas y **103 con 10** (se pierde **«Formulario»**, que es justo el dato
operativo); «Pedidos» pierde «Creado el»; «Clientes» pierde «Email verificado» y «Alta»;
«Catálogo» pierde «Activo», «Vendible» y «Orden». `panel-navegacion.md` §6·U6 ya señaló la palanca
—los componentes `Split`/`Stack` de Filament— y sigue **sin usarse en ninguna tabla**.

**M6 · El armazón entero está bajo el mínimo táctil** (§2.2), incluidos los dos controles que más
se pulsan: «Crear pedido» (32 px de alto) y el avatar (32×32), que es **la única puerta a Ajustes y
a Salir**.

**M7 · La ficha del pedido repite el bloque de dinero y aprieta todo lo demás.** Con una sola
reserva, «TOTALES DEL PEDIDO» y «TOTALES DEL PRODUCTO» enseñan **las mismas tres cifras** (Total
77,00 € · Pagado 88,00 € · A devolver en el parque −11,00 €) **con un «Ver el desglose» cada uno**,
a 215 px de distancia en la misma pantalla. Y **13 de sus 15 controles miden menos de 44 px**: el
enlace al cliente (132×20), su teléfono (79×17), «Ver el desglose» (86×16), «Ver más» (47×16), el
icono de copiar el código (24×24), «Acciones del pedido» (188×36) y los cinco iconos de la reserva.

**M8 · El supuesto que retiró «Puerta» del menú del admin no se cumple.** `#320` la bajó a Ajustes
con este argumento del owner: *«es innecesario, él puede ver todos los detalles de cualquier cliente
directamente con el buscador»*. Medido: la ficha del cliente del panel (1.617 px, 61 controles)
enseña datos de cuenta, consentimientos, **menores a cargo con el estado de SU descargo** y la
tabla de pedidos — **pero no dice si el titular firmó el suyo** (eso vive detrás de la acción
«Registro del descargo»), ni si tiene reserva hoy, ni lo que llega debiendo. Es decir: **la ficha
del cliente dice si firmaron los niños y no si firmó el adulto**, y no contesta ninguna de las tres
preguntas de la puerta. Al admin le cuestan 3 toques lo que el empleado tiene en 1.

**M9 · Sobre un pedido con UNA reserva hay 66 controles y el dato operativo pesa un tercio que el
administrativo.** Es el «montón de accionadores y de datos» del owner, medido: §5.bis lo enumera.

**M10 · A 1.080 px el párrafo del historial se maqueta en una columna de 58 px con 17 líneas.**
Medido en los tres anchos: **a 1.440 y 1.920 no ocurre**, así que es un defecto **exclusivo de la
tablet** —el dispositivo de trabajo— dentro de una sección que además está plegada por defecto, que
es por lo que no lo ha visto nadie. El bloque «En banco del cliente (Redsys)» se parte igual, en
tres líneas, aunque sin llegar al umbral.

---

## 5.bis · Los 66 controles de un pedido, y dónde está cada dato

El owner lo planteó así: *«hay un montón de accionadores sobre un pedido, sobre una reserva, un
montón de datos, que no están en su sitio»*. Medido sobre `T0-PRB01` —**un pedido con UNA sola
reserva**— en iPad horizontal.

### 5.bis.1 · Los accionadores, por nivel

**En el código hay 14 acciones registradas** sobre la ficha (`cancel` · `refund` · `resendEmail` ·
`manageItem` · `cancelItem` · `refundItem` · `cancelFromManage` · `refundFromManage` ·
`copyGuestFormLink` · `rotateGuestFormLink` · `copyGuardianLink` · `sendGuardianLink` ·
`assignDependents` · `viewOrderHistory`). En pantalla se reparten así:

| Nivel | Dónde | Cuántos | Cuáles |
|---|---|---:|---|
| **1** | Cabecera | 3 | «Pedidos» (miga, 55×20) · **copiar el código (24×24, sin texto)** · «Acciones del pedido» (188×36) |
| **1** | Resumen | 3 | el nombre del cliente (132×20) · el teléfono (79×17) · «Ver el desglose» (86×16) |
| **1** | Plegados | 2 | «Detalles» (336×56) · «Pagos» (336×56) |
| **1** | **La reserva** | 7 | «Ver más» (47×16) · «Ver el desglose» (86×16) · **cinco iconos de 40×40 sin una sola letra** |
| **2** | Menú «Acciones del pedido» | 3 | Cancelar pedido · Reembolsar · Reenviar email |
| **2** | Icono de impresora | 2 | Hoja de sala (sin precios) · Con precios y desglose |
| **2** | Al abrir «Detalles» | 2 | el **email** del cliente · «Ver historial completo» |
| **2** | Al abrir «Pagos» | 2 | «Abrir portal Redsys (sandbox)» · copiar el nº de pedido Redsys |
| **3** | Modal «Gestionar» | **47** | 3 pestañas · 27 campos · 10 chips de hora · Guardar · **Cancelar producto** · Reembolsar |

**15 al abrir · 19 con todo desplegado · 66 en total.** Y **seis de los quince del nivel 1 no llevan
texto**: el de copiar el código y los cinco de la reserva.

⚠️ **Las dos acciones que un mostrador usa cada día están en niveles distintos y ninguna se
anuncia**: editar la reserva es el 5.º icono sin rótulo (nivel 1) y cancelarla vive **dentro** de ese
modal (nivel 3), pegada a «Guardar».

### 5.bis.2 · Los datos, y dónde viven

Con «Detalles» y «Pagos» abiertos y el libro desplegado, la ficha mide **2.347 px**:

| Bloque | Alto | Qué contiene | ¿Es operativo? |
|---|---:|---|---|
| **Productos del pedido** (la reserva) | **572** | producto, día, hora, invitados, duración, estado del formulario, complementos, totales | **sí** |
| Resumen | 572 | nombre, teléfono, y el libro del pedido | mitad |
| Detalles | **986** | creado el · pagado el · **email** · idioma · **descargo** · historial | administrativo |
| Pagos | **517** | texto de reconciliación · portal Redsys · nº de pedido del banco · confirmación | administrativo |

▶ **La reserva ocupa 572 px y la administración 1.503**, casi el triple. Y **debajo de la reserva
quedan 1.583 px de columna derecha vacía** mientras la izquierda sigue bajando.

**Tres datos están donde no se buscan:**

1. **El EMAIL del cliente vive plegado dentro de «Detalles»**, a ~1.000 px de scroll, mientras el
   nombre y el teléfono están arriba. Para escribirle hay que abrir una sección que se llama
   «Detalles».
2. **El DESCARGO del titular también** («Descargo: No firmado»). Es el dato que la puerta pregunta,
   y **no está en la ficha del cliente** (M8): está aquí, escondido.
3. **«Pagos» repite lo que el libro ya dice arriba** —«Pagado online · 31/08/2026 · +88,00 €»— en
   lenguaje de pasarela, y añade el enlace al portal del banco.

**Y el mismo número se repite en la misma pantalla**: «88,00 €» **5 veces**, la palabra «Pagado»
**5**, la fecha «31/08/2026» **6**, «Total» **2**, «−11,00 €» **2**, «A devolver en el parque» **2**.
Con una sola reserva, «TOTALES DEL PEDIDO» y «TOTALES DEL PRODUCTO» son **la misma cifra dos veces**,
cada una con su «Ver el desglose».

---

## 6. Los once menores

| | Hallazgo | Medido |
|---|---|---|
| m1 | **Dos badges de estado en la cabecera del pedido** («Completado» y «Activa») y su única explicación es un `title` — que en tablet no existe | dos dimensiones distintas: estado del pedido y estado respecto al evento |
| m2 | **Registro mixto: el panel tutea y Filament trata de usted**, desde la primera pantalla: **«Entre a su cuenta»** | nuestras cadenas: 16 «elige», 11 «marca», 6 «Revisa»… y **un solo** «revise». El vendor: **67 ficheros de idioma es** sin sobrescribir (no hay `lang/vendor/filament`) |
| m3 | **Vocabulario partido en las URL** | **18 rutas en inglés** (`orders`, `users`, `catalog`, `zones`, `slots`, `seasons`, `special-dates`, `rate-types`, `park-rules`, `landing-services`, `faqs`, `offers`, `pages`, `roles`, `settings`, `slot-templates`, `attractions`, `maintenance`) contra **6 en español** (`ajustes`, `calendario`, `crear-pedido`, `horario`, `incidencias`, `puerta/validar`) |
| m4 | **El buscador llama «Usuarios» a lo que el menú llama «Clientes»** | dos nombres para la misma cosa en la misma pantalla |
| m5 | **«Imprimir resumen del día» sigue diciendo «del día»** con el periodo en «Este mes» y con el calendario en vista mes | sale igual en las dos pantallas |
| m6 | **El 4.º sitio del menú no es el mismo para admin y empleado** | admin: Hoy · Calendario · Pedidos · **Clientes** · empleado: Hoy · Calendario · Pedidos · **Puerta** (medido en un proceso por rol) |
| m7 | **El paso 1 de «Crear pedido» desperdicia ~500 px de 810** | 1 campo, 4 controles, y la columna del resumen vacía |
| m8 | **La hoja impresa del día no lleva lo que hace falta para abrir el día** | lleva hora, producto, cliente, teléfono, cantidad, homenajeado y complementos; **no** el saldo a cobrar, ni la exención, ni si falta el formulario |
| m9 | **La pantalla de puerta no registra nada**: el método que acredita la visita existe y **no tiene botón** | `#234`, `[DECIDIDO owner]` hasta que exista JumpPoints — la tabla de visitas no crece |
| m10 | **«Configuración» son 2.883 px** (3,6 pantallas) con 4 pestañas de primer nivel, 13 secciones, **70 campos y 35 controles bajo 44 px** | es puntual, pero es donde se pone en marcha el parque |
| m11 | **El texto del aviso de la puerta se repite literalmente** en la banda de resultado y en la tarjeta «Descargo» | *«Pásale la tablet al cliente para que firme el descargo antes de saltar»*, dos veces en 300 px |

---

## 7. Lo que está bien y **no** hay que tocar

- **El menú plano.** Cuatro sitios, sin grupos, sin plegables. Medido por rol en procesos
  separados: admin 4 · empleado 4. La decisión D4 de `#223` sigue siendo la mejor del panel.
- **Las veinte tarjetas de Ajustes con su línea de qué hacen.** El punto U2 de
  `panel-navegacion.md` §6 («repasar los rótulos y las 19 descripciones») **está resuelto**: las
  descripciones existen y dicen lo que hay dentro («Tarifas: tipos de precio y a qué días se aplica
  cada uno»).
- **La pantalla de puerta en reposo**: 3 controles, cero por debajo de 44, cabe entera. Es el mejor
  kiosco del panel.
- **«Crear pedido», su FORMA**: tres pasos, resumen pegajoso, la hora en chips, la tira de días,
  cero controles bajo 44 en el paso 1. Es la única pantalla del panel diseñada para un dedo, y se
  nota. ⚠️ **Pero su primer campo es C1**: la forma está resuelta y **el contenido del desplegable de
  producto no** — 18 opciones planas que no dicen si son entradas o packs. *Lo que falló no fue la
  pasada de tablet: fue que nadie miró qué había dentro del `Select`.*
- **El libro del pedido desplegado**: cada movimiento con su fecha y su signo, el total, lo pagado
  y el saldo. Es legible tal cual.

---

## 8. Lo que la doc promete y el código no tiene

Tres cosas de `PANEL-ADMIN.md` que hoy no existen. No son hallazgos de UI: son **huecos entre el
doc funcional y el producto**, y quien lea el doc para aprender la operativa va a buscarlas.

1. **§2.1 · «Clic en día/franja → detalle: quién viene, ocupación, plazas libres.»** El día no es
   clicable y la ocupación no se pinta (M3).
2. **§2.6 · «Preparación de entradas (tablero interno)»** con los estados Comprada → Preparada →
   Canjeada y las acciones «Marcar preparada» / «Marcar canjeada». **No existe**: una reserva solo
   tiene `active`, `finished` y `cancelled`.
3. **§2.4 · «marcar la entrada como canjeada (no reutilizable)».** El ticket tiene **un solo
   estado** (`purchased`) y **un solo escritor** (el emisor). Nada lo canjea.

---

## 9. Las decisiones del owner

Las cuatro se le preguntaron con el número y el coste de cada salida delante. **Tres están
tomadas** (2026-09-03) y **cambian la clasificación de dos hallazgos**, que pasan a §9.bis.

| | La pregunta | `[DECIDIDO owner, 2026-09-03]` |
|---|---|---|
| **D1** | **¿La tablet es el dispositivo de TODO el panel, o solo del día a día?** | **Solo el día a día: seis pantallas** — Hoy · Calendario · Pedidos · ficha de pedido · Crear pedido · Puerta. Ajustes y las 19 de puesta en marcha **se quedan para ordenador** |
| **D2** | **¿El panel registra el cobro en el parque?** | **No: se queda informativo.** El parque apunta el cobro fuera del sistema y el libro sigue liquidando por el paso del tiempo |
| **D3** | **¿Pantalla que conteste «¿cuánto queda el sábado a las 17:00?»** | **No: se queda como está.** La respuesta vive dentro del flujo de venta (paso 2 de «Crear pedido»), que es donde se necesita para vender |
| **D4** | **¿Quién opera de verdad?** | **El admin** — y el owner añade que el encargo real es *«hacerlo mucho más fácil: hay puntos de fricción y una persona podría no entenderlo»*. ⏸️ **Pendiente de su detalle**, que es lo que ordenará el plan |

### 9.bis · Lo que falla A PROPÓSITO, y por tanto NO es un hallazgo

Mismo trato que `auditoria-diseno.md` §6 les da a las puertas de Hallmark: se separan para que nadie
las re-litigue leyendo la lista de defectos.

- **M4 · El saldo del parque es informativo y nadie lo marca como cobrado** → **D2**. El síntoma
  medido sigue siendo cierto (un pedido queda «liquidado» tanto si se cobró como si no); lo que
  cambia es que **es la conducta querida**, coherente con `#244`. Se queda escrito porque la
  siguiente sesión lo va a volver a encontrar.
- **M3 · Ninguna pantalla contesta «¿cuánto aforo queda?»** → **D3**. ⚠️ **Pero queda una deuda
  documental abierta**: `PANEL-ADMIN.md` §2.1 sigue prometiendo «Clic en día/franja → detalle:
  quién viene, **ocupación, plazas libres**». Con D3, esa frase **describe algo que no se va a
  construir** y hay que retirarla del doc funcional (§8·1).

### 9.ter · Lo que D1 deja fuera de alcance

- **m10** («Configuración»: 2.883 px, 70 campos, 35 controles bajo 44 px) — es pantalla de
  ordenador.
- **M5 se acota**: siguen dentro «Hoy» (pierde la columna **«Formulario»**), «Pedidos» (pierde
  «Creado el») y «Clientes» (pierde dos); **sale «Catálogo»** (sus tres columnas cortadas son
  pantalla de ordenador).
- Las 19 pantallas de puesta en marcha, tal como estaban.

---

## 10. Dónde está la evidencia

Fuera del repo, en el contenedor `laravel.test`: **diez sondas** en `/root/e2e/` —`panel-audit.js`
(las 10 pantallas × 3 tamaños), `probe-order.js` (el mapa vertical de la ficha), `probe-ops.js`
(modal, «Hoy», calendario, puerta), `probe-cal.js` (las píldoras y las tablas), `probe-flow.js`
(«Crear pedido»), `probe-fix.js` (repone lo que se midió sobre el 404), `probe-search.js` (buscador
y ficha del cliente), `probe-btn.js` (los tres botones del modal), `probe-ficha.js` (la ficha con
todo abierto y las repeticiones) y `probe-actuadores.js` (el inventario de §5.bis)—, con sus JSON y
**más de 40 capturas** en `/root/e2e/audit/`. Se dejan ahí a propósito: `deploy.sh` no excluye
carpetas nuevas de la raíz del repo.

Las cifras del catálogo (las 18 opciones del desplegable, los precios de las dos formas de vender
lo mismo, la duración, el mínimo y los complementos de cada producto) salen de `tinker` sobre la BD
local, no de la lectura del código.
