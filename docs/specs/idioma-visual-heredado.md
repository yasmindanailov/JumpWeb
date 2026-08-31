# [SPEC] Sustituir el idioma visual HEREDADO por el de este cliente

> Estado: 🟦 **TANDAS A, T1 y T2 EN EL ÁRBOL** (el sistema de etiquetas · el motivo del cliente
> antiguo fuera · las normas de la portada rehechas con la primera mancha del kit · la marquesina de
> `/servicios` sustituida por la cinta `C3`).
> Última actualización: 2026-08-31 · Decisiones: **`#290`**, **`#292`** y **`#293`**.
>
> ❗❗❗ **CORRECCIÓN DE FUENTE, Y VA ANTES QUE TODO LO DEMÁS** (`[owner, 2026-08-31]`):
> **`Landing PJP Modos` NO guía esta reestructuración.** *«La landing mockup NO, no te guíes de ella,
> de ahí solo sacaremos la sección de reseñas.»*
> ▶ Eso **corrige lo que dice `tema-por-instalacion.md` §1** —que esa maqueta «lleva el sistema
> vigente»—: sigue valiendo para COLOR y para el sistema visual, pero **no para la estructura de la
> landing**, que la decidimos nosotros con el branding y `Elementos Fachada`.
> ▶ Consecuencia inmediata: el registro fino de badges que salía de ella **se retiró el mismo día**
> (§3.2), y el sistema se queda con **una sola voz**, la del mural.
> ⚠️ Su número se elige **mirando el REMOTO**, no el local: ya colisionó una vez con otro agente.
>
> ❗❗❗ **EL ENCARGO, EN PALABRAS DEL OWNER** (2026-08-31), y no es el que se venía haciendo:
> *«Más bien de "añadir cosas nuevas" de diseño, quiero "cambiar las que tengo"… La página web
> actual, las secciones, su orden, los elementos de diseño que tienen son del cliente antiguo, y
> necesito algo nuevo. Ya tenemos colores, formas, botones, radio, etc., pero faltan este tipo de
> elementos como los que tenemos en Elementos Fachada… Pondremos nuevos elementos de manera sutil,
> pero primero hay que cambiar lo que tenemos.»*
>
> ▶ **Esto CAMBIA el marco de `#286`.** Aquella regla —*la decoración va en la PANTALLA, no en el
> componente que se repite*— **sigue viva y no se toca**, pero es para DECORACIÓN AÑADIDA. Un badge,
> un separador o una cinta son **componentes funcionales**: su forma es suya y se repiten porque los
> datos se repiten. Confundir las dos cosas paraliza este carril entero.

---

## 1. El inventario, medido

Lo que hay hoy en la landing con idioma del cliente **antiguo**, y qué dice el nuevo para cada pieza.

| pieza | dónde | qué es hoy | idioma nuevo | estado |
|---|---|---|---|---|
| **`.jj-block`** — cuadrado «foam» girado 22° | portada, separando las cifras de zonas | **las iniciales del cliente antiguo dan nombre a la clase**; 6 variantes y **solo 1 usada** | su artboard no tiene ese motivo: separa con **tira `C2`** | ✅ **tanda A** |
| Badge de edad de zona | tarjetas de zona (×2 variantes) | pastilla blanca rellena, `--r-xs`, girada −2° | **DATO** (mono fino) | ✅ **tanda A** |
| Badge de atracción («XL») | `ride-card`, **sobre foto** | pastilla rellena del color de zona, girada −5° | **SEÑAL punteada** | ✅ **tanda A** |
| Badge de destacado («Top») | `price-card` | pastilla de tinta, girada **+6°** | **SEÑAL tinta** | ✅ **tanda A** |
| Chip de suplemento | `price-card` | pastilla gris rellena | **DATO** | ✅ **tanda A** |
| Etiqueta sobre foto | `/servicios` | pastilla de tinta, girada +5° | **SEÑAL punteada** | ✅ **tanda A** |
| **Marquesina de palabras** | `/servicios` | títulos en bucle, tipografía neutra | **`C3` cinta**: banda de tinta a sangre, girada, con punto de color | ✅ **T2** |
| Cubos 1-2-3 (`.bd-proc__cube`) | «Cómo se reserva» del cumple | cuadrados redondeados de 44 px | por decidir | ⬜ |
| Nota de calcetines | portada, bajo precios | tarjeta con icono de línea | el icono ya es del set nuevo (`#257`); la caja no | ⬜ |
| Pliego de pictogramas | `/normas` | **imagen subida** del cliente antiguo | es CONTENIDO, no diseño: lo cambia el panel | ⬜ |
| Marquesina de polaroids | portada, galería | fotos giradas con marco blanco | por decidir | ⬜ |
| Badge de producto destacado | **SPA**, catálogo | — | va con el **cambio de presentación del catálogo**, que el owner quiere iterar aparte | ⬜ |

⚠️ **`.jj-block` sobrevive en UN sitio y es a propósito**: `BookingProgress.vue` lo usa dentro del
cajón (`.bk-context .jj-block`). El SPA es otra tanda —el owner lo separó— y renombrar la clase ahora
tocaría el contrato de **ÁRBOL** del cajón (`tests/Fixtures/sidebar-dom-manifest.json`).

---

## 2. ❗ La contradicción que hay que conocer antes de vestir nada

**Dos artboards del cliente visten los badges de forma distinta, y las dos formas son suyas.**

| | `Elementos Fachada` · E2 (el kit del mural) | `Landing PJP Modos` (su portada definitiva) |
|---|---|---|
| condición | `border: 2px dashed` tinta · `padding 6/13` | `border: 1.5px solid currentColor` · `padding 5/12` |
| tipografía | cuerpo **13 px / 700** | **mono 10,5 px / 400**, `letter-spacing .1em` |
| zona | cápsula llena + **silueta mini 14×24** | cápsula llena, mono |
| badge de atracción | punteada | mono **9,5 px sobre gris**, `padding 3/8` |

▶ **Se resolvió DOS veces el mismo día, y la segunda manda.**
1. Primero entraron **las dos**, repartidas por lo que el badge HACE (SEÑAL → E2 · DATO → su landing).
2. ⚠️⚠️ **Y luego el owner retiró la fuente**: *«de esa maqueta solo sacaremos la sección de
   reseñas»*. Sin fuente normativa, el argumento del registro fino se cae — `[DECIDIDO owner]`:
   **«todo al registro del mural»**. Queda **UNA voz**: `E2`.

| papel | variante | dónde |
|---|---|---|
| **condición** | `.tag--punteada` — «punteadas para condiciones», su nota literal | edad de zona · suplemento · «XL» sobre foto |
| **destacado** | `.tag--tinta` | «Top» de la tarjeta de precio |

⚠️ **Coste declarado, y él lo eligió con la consecuencia delante**: la edad y el suplemento pesan más
en la página. A cambio, el sitio habla con una sola voz.

▶ **Lo que sigue valiendo del primer intento** es el método, no el resultado: *una contradicción entre
dos fuentes no se resuelve eligiendo la que más gusta, se resuelve preguntando cuál es fuente para
qué* — y aquí la respuesta la dio el owner retirando una.

### 2.1 · Por qué la punteada es la de «sobre foto» y no la rellena

Medido en la hoja comparativa y confirmado en la página: sobre una imagen, **la punteada es la única
de las tres que se lee sin poner un rectángulo de color delante**, porque el trazo discontinuo se
distingue de cualquier textura. Y encaja con la regla del propio mural: *la pintura no tapa*.
▶ Consecuencia declarada: el badge de atracción **deja de ir relleno del color de zona**. El color de
zona sigue mandando en la tarjeta; lo que se retira es el parche encima de la foto.

---

## 3. Tanda A · el sistema de etiquetas (hecha)

### 3.1 · Lo que había: **cinco formas para la misma función**

Medido antes de tocar: radios `--r-xs`, `--r-pill` y `--r-md` · paddings **3/8 · 6/12 · 7/13 · 7/14 ·
8/14** · tallas **10 · 10,5 · 11 · 12,5 · 13** · y **cuatro rotaciones** (−2°, −5°, +5°, +6°).

▶ Es el hallazgo de `#196` con las sombras, otra vez: **no era una escala con ruido, es que no había
ninguna.**

### 3.2 · Lo que hay ahora

```
.tag                → canto de la escala (--r-pill) + el giro por PERILLA (--tag-tilt, defecto 0)
.tag--senal         → E2: gap 7 · padding 7/14 · cuerpo 13/700
  .tag--punteada    → + border 2px dashed currentColor · padding 6/13 (1 px menos: compensa el borde)
  .tag--tinta       → + fondo de tinta
.tag--dato          → landing: border 1.5px solid currentColor · mono 10,5/400 · padding 5/12
```

⚠️ **`--zona`, `--norma` y `--estado` de E2 NO se declaran**, y no es un olvido: hoy no tienen
consumidor, y `FacadeCssHasNoOrphansTest` pone la suite roja con una regla sin pantalla. **Entran con
su pantalla.** El sitio natural de `--zona` (cápsula llena con silueta mini, que es el ejemplo
cabecera de E2) son las **pestañas de zona**; queda propuesto, no hecho.

⚠️ **Sin `text-transform` en el registro SEÑAL**: el texto de estos badges lo escribe el PANEL
(`tr('badge')`), y forzar mayúsculas aquí decidiría por el operador. **Consecuencia visible**: donde
el panel tiene «Top», ahora se lee «Top» y no «TOP». En el registro DATO sí van en mayúsculas, porque
así lo escribe él y porque un dato en mono se lee como ficha técnica.

### 3.3 · El giro: **uno solo**

`[DECIDIDO owner]`: **−2°** en todas, y solo en las que se pegan **encima** de algo. Una etiqueta en
el flujo del texto no se gira. Medido en su landing: sus giros son −2°/−3° y son pocos.

### 3.4 · El separador: la punteada, **como token y no como clase**

`[DECIDIDO owner]`: fuera el «foam», y se separa como él — con la tira punteada de `C2`.

⚠️⚠️ **La primera versión era un `<span class="brand-dots">` por hueco, dentro del `@foreach`, y
`FacadeDecorationIsPerScreenTest` la puso ROJA con razón**: una textura repetida por fila es lo que
el owner rechazó tres veces en `#286`. ▶ **Un separador es PUNTUACIÓN, y la puntuación la pinta el
CSS** (`.zone-stats__item + .zone-stats__item::before`). El patrón entra como token `--dots-tile`, así
que sigue disponible para la tira completa el día que alguien la necesite **sin quedarse huérfano hoy**.

### 3.5 · La guarda, y lo que vigila de verdad

`TagSystemTest` (5 casos, **5 mutaciones muerden**). El caso que importa es
`test_no_consumer_redeclares_the_shape`: **un sistema de etiquetas no se rompe de golpe**, se rompe
cuando la siguiente tarjeta necesita «un pelín más de padding» y se lo escribe en su regla. A los seis
meses hay cinco formas otra vez — que es el estado del que se viene.

▶ Ya cazó una: el `<b>` del chip de suplemento seguía con mono **600 a 12,5 px** dentro de una
cápsula que ya es mono de 10,5. Dos tallas dentro de la misma etiqueta, y no lo veía nadie.

---

## 3.bis · T1 · las normas de la portada (hecha)

`[DECIDIDO owner]`: *«las normas irán sin imagen, solo será texto, un texto simple y un CTA a la
página de normas… en la landing, lo más importante»*.

**Lo que había**: una rejilla de dos columnas con el **pliego de doce pictogramas del cliente
ANTIGUO** (`images/historia-seguridad.png`) y, al lado, un **carrusel vertical con TODAS las normas**
—`max-height: 620px` y scroll propio dentro de una página que ya hace scroll—. Dos formas de decir
lo mismo, una con arte de otro parque, y ninguna pensada para un teléfono.

**Lo que hay**: eyebrow + titular + una frase + **tres normas** numeradas en texto plano + el CTA a
`/normas`. Se retiran `.rules-layout`, `.rules-layout__media` y `.rules-vslider`.

▶ **El tope de tres se declara en la VISTA, no en el panel**, y la distinción importa: el operador
decide **qué** normas hay y en qué orden; **cuántas caben en la portada es diseño**. Lo fija
`CmsLandingFlowTest` con dos casos —el de la norma nueva, que ahora sale en `/normas` y **no** en la
portada, y uno que asevera exactamente tres— y **la mutación de subir el tope a ocho los pone rojos**.

▶ **CSS escrito de móvil hacia arriba**, que es lo que el owner pidió: la regla base es la del
teléfono y el escritorio es la excepción. Al revés, el teléfono acaba siendo lo que sobra de una
rejilla pensada para 1280.

### 3.bis.1 · La primera mancha, y cómo se decidió dónde va

Es la **primera ranura decorativa del producto** (`slot-normas`) y nace con su consumidor en el mismo
cambio, que es la regla del carril. El dibujo es la mancha **`B1·03`** del artboard — la de «lengüetas
largas», que **su propia nota manda a esquinas y bordes**; `01` y `04` no se podían usar porque **ya
viajan instaladas** como `--deco-blob-a/b`.

⚠️⚠️ **Dónde va lo decidió MEDIR, y el primer sitio estaba mal.** Arriba a la derecha caía sobre el
párrafo de la primera norma —**13.755 px², medido en navegador**— y su regla 02 lo prohíbe: «la
pintura nunca va debajo de un párrafo».
▶ **Y bajarla no servía**: un barrido de cinco posiciones apenas movió el número. El motivo salió al
mirar las cajas: la columna izquierda **está llena** —el párrafo de entrada acaba a **107 px** del
final de la sección—, así que ahí no hay hueco que ganar. *Cuando mover una pieza no cambia el
número, el problema no es la posición: es que no hay sitio.*
▶ El hueco real está **detrás del titular**, que es tinta maciza y no un párrafo — y es literalmente
lo que describe su `B3`: «la mancha detrás de la primera palabra». Medido ahí: **0 px² bajo párrafo**
en las dos anchuras, y asoma por el borde, que es lo que su nota pide para esta familia.

⚠️ **Presupuesto declarado**: **una pieza de dibujo por sección, y la portada entera no pasa de
TRES**. Hoy gasta dos (las poses de zona y esta mancha); queda una.

---

## 3.ter · T2 · la cinta `C3` de `/servicios` (hecha)

`[DECIDIDO owner]`: quitar la marquesina de palabras. La sustituye la banda de su `C3`: **tinta, a
sangre completa, girada, con un punto de color entre títulos y moviéndose despacio**.

### 3.ter.1 · Qué se toma de él y qué NO, con el motivo

| | decisión |
|---|---|
| **la FORMA** | suya, al dígito: `width: 120%` · `margin-left: -10%` · `rotate(-2.4deg)` · `padding 14px 0` · `gap 26px` · `padding-left 24px` · rótulo 26 px · puntos de **12 px** |
| **el CONTENIDO** | **NUESTRO**: los títulos siguen saliendo del panel. Su cinta lleva un eslogan fijo escrito en el mockup; el sitio es data-driven |
| **la FUENTE** | **NO se toma la de rotulador**, aunque su `C3` la use. Su propio paquete dice de `--font-accent`: *«Guiño: **el eslogan, nada más**»*, y su auditoría **`T-02`** limita Permanent Marker a **una por página**. Medido: `/servicios` ya gasta la suya en el eslogan del menú |
| **el COLOR** | roles, no literales: `--paper-fg` para la tinta, `--paper-bg` para el rótulo, `--strip-3` para el rótulo alterno y `--strip-2`/`--strip-1` para los puntos |

⚠️ **Contradicción suya, anotada y no corregida en silencio**: su nota dice «girada **2°**» y su
marcado usa **−2,4°**. Se toma el marcado, que es lo que se ve.

### 3.ter.2 · ❗ El motivo «foam» del cliente antiguo estaba TAMBIÉN aquí

El separador de la marquesina era `13px / radio 3,5 / girado 22°` — **el mismo `.jj-block`, copiado
como geometría en vez de con la clase**. Por eso sobrevivió al barrido de la tanda A, que buscaba el
nombre. *Un motivo copiado a mano no aparece buscando su nombre.* Lo cazó `ShapeScaleTest` al quedarse
su excepción sin sujeto.

### 3.ter.3 · ⚠️⚠️ Dos números que parecen adorno y son geometría

1. **`width: 120%` + `margin-left: -10%`.** Una banda del ancho exacto de la ventana, al girarse,
   deja **dos cuñas de papel** en las esquinas. Sus dos números existen para eso.
2. **El alto del envoltorio.** Sin él la cinta **se recorta a sí misma**: medido, **5 elementos
   cortados y el peor a 22 px** en escritorio, con el rótulo de los extremos partido por el canto.
   El valor sale de `½ · ancho · sen(giro)` → con el interior al 120 %, **2,51vw** por lado.
   ▶ **Y el recorte no puede ser `overflow-x` en `html`/`body`**: `#226` midió que eso rompe **todos
   los `sticky`** del sitio.

### 3.ter.4 · ⚠️⚠️ Dos trampas de instrumento, y las dos daban un número creíble

1. **La primera sonda contaba como «cortados» ítems que ya estaban fuera de la ventana** por el
   recorte horizontal de la marquesina: daba 6 cortados en móvil con la pieza sana. Acotada a lo que
   de verdad se ve: **0**. Es el mismo error que `cajon-en-movil.md` §7.2 dejó escrito.
2. **Y el cero solo vale con CONTROL**: quitando el alto, la sonda pasa a **5 cortados a 22 px** en
   escritorio. ⚠️ En móvil el control **no muerde** —ahí el alto extra es aire, no necesidad—, y eso
   también está dicho.

### 3.ter.5 · La guarda, y una laxitud que cazó la mutación

`BrandBandTest` (4 casos, **6 mutaciones muerden**). ⚠️⚠️ **Una aserción nació LAXA**: el ancho del
interior se comprobaba con `/width:\s*1[0-9]{2}%/`, y ese patrón **acepta `100%`** —justo el valor
que rompe la pieza—, así que la mutación pasaba en verde. Ahora se lee el número y se compara.
*Un patrón que describe la FORMA del valor no dice nada sobre el valor.*

⚠️ **El bucle lee `--dur-cinta`, un token AMBIENTAL declarado** (`MotionScaleTest`): la marquesina que
sustituye llevaba `30s` escritos a mano, así que una instalación no podía calmarla.

⚠️ **Presupuesto de movimiento**: `/servicios` sigue en **6 bucles** — la cinta sustituye a la
marquesina, no suma. El techo de 2 de su artboard sigue siendo la deuda que `#279` dejó medida.

---

## 4. Lo que queda, y en qué orden

| # | tanda | qué |
|---|---|---|
| **B** | La marquesina de `/servicios` → **cinta `C3`** | `[DECIDIDO owner]` quitar la de palabras. ⚠️ **Choca con `#252`**, que retiró la marquesina de la portada por espacio: la cinta vuelve, pero en `/servicios`, no en la portada |
| **C** | Los cubos 1-2-3 del cumple · la nota de calcetines | forma por decidir |
| **D** | La galería de polaroids | ¿sigue el lenguaje polaroid o pasa a cinta con poses (`F11`)? |
| **E** | **El SPA**: catálogo y badge de destacado | el owner quiere **cambiar la presentación**, no solo vestirla: es su propia spec |

### 4.0 · ⛔ Lo que NO se hace

**La zona de Ocio / el bar NO entra** (`[DECIDIDO owner, 2026-08-31]`, preguntado con las tres formas
posibles delante). Era el único punto del encargo que podía tocar el modelo de datos —una `Zone` trae
aforo, franjas y productos, y un bar no se reserva—, y queda descartado. **Zonas y atracciones sí se
unifican**, que es presentación y no modelo.

### 4.1 · Decisiones abiertas

1. **Las pestañas de zona**: ¿entran como `--zona` con la silueta mini del kit? Es el ejemplo
   cabecera de E2 y el único sitio donde esa variante tiene sentido hoy.
2. **«Top» en caja normal** en vez de «TOP»: es consecuencia de respetar lo que escribe el panel.
   Si se prefiere en mayúsculas, es una línea — pero entonces el panel deja de mandar.
3. **El pliego de pictogramas de `/normas`** es una imagen del cliente antiguo: no se arregla con
   CSS, lo sustituye el operador desde el panel.
