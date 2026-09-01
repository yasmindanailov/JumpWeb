# [SPEC] Sustituir el idioma visual HEREDADO por el de este cliente

> Estado: 🟦 **TANDAS A, T1, T2 y T3 EN EL ÁRBOL** (el sistema de etiquetas · el motivo del cliente
> antiguo fuera · las normas de la portada rehechas con la primera mancha del kit · la marquesina de
> `/servicios` sustituida por la cinta `C3` · **zonas y atracciones unificadas**).
> Última actualización: 2026-08-31 · Decisiones: **`#290`**, **`#292`**, **`#293`**, **`#295`**
> y **`#297`** (el molde editorial, diagnosticado; tres formas rechazadas y revertidas).
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

## 3.bis · T1 · las normas de la portada (⛔ REVERTIDA — `#300`)

> ⛔⛔ **ESTA TANDA YA NO ESTÁ EN EL PRODUCTO. LA CORRECCIÓN VA ANTES QUE EL TEXTO QUE CORRIGE.**
> `[DECIDIDO owner, 2026-08-31]` (`#300`): **revertir la T1 entera**, elegido con la consecuencia
> delante —se le enseñaron las tres opciones renderizadas y se le dijo que revertir devuelve arte de
> otro parque—. La portada vuelve a `.rules-layout` + `.rules-vslider` con el pliego de doce
> pictogramas del cliente ANTIGUO y **todas** las normas; se van las tres en texto, el CTA y la
> mancha, `IllustrationKit::SLOTS` queda **vacía** otra vez y el kit baja a **3 símbolos**.
>
> ▶ **No es un retroceso del carril: es fijar la base común desde la que se itera.** El diagnóstico
> del molde editorial (§3.quinquies) **sigue vivo** y su `[DECIDIDO owner]` sigue en pie. Lo que el
> owner hizo fue devolver al mismo punto de partida las dos secciones que se habían tocado, para
> rediseñar desde ahí en vez de encima de una forma a medias que no había aprobado.
> ⚠️ **La sección de normas de hoy NO es una forma aprobada**: es material heredado pendiente de
> rediseño, y así está anotado en la vista y en las dos hojas de estilo.
> ⚠️ **La otra mitad de `#292` NO se revirtió**: la retirada del registro fino de badges
> (`.tag--dato`) es una decisión independiente y sigue en pie — venía de `Landing PJP Modos`, que el
> owner desautorizó como fuente de la landing salvo para las reseñas.
>
> **Lo que sigue de aquí abajo es el registro de lo que se hizo y por qué, no del producto de hoy.**
> Se conserva porque su medición (dónde cabe una mancha, y por qué) sigue valiendo para la siguiente
> pieza que se coloque.

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
TRES**. ⚠️⚠️ **Corregido por `#300`**: con la T1 revertida esta mancha se retiró, así que la portada
gasta **UNA** —las poses de zona— y quedan **DOS** libres.

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

## 3.quater · T3 · zonas y atracciones, unificadas (⛔ REVERTIDA en su ESTRUCTURA — `#301`)

> ⛔⛔ **LA ESTRUCTURA UNIFICADA YA NO ESTÁ. LA CORRECCIÓN VA ANTES QUE EL TEXTO QUE CORRIGE.**
> `[DECIDIDO owner, 2026-08-31]` (`#301`): *«deja la sección de zonas como estaba antes, con su
> estructura de antes, 2 cards y debajo la sección de juegos»*. Vuelven **`#zones`** y **`#rides`**
> como dos secciones, con la barra de pestañas por zona, las flechas y la barra de progreso.
>
> ❗❗ **PERO EL ARREGLO DE §3.quater.1 SE QUEDA, y eso es lo que hay que saber antes de tocar nada
> aquí.** La identidad de la zona en el marcado sigue siendo **`slug`**; `accent` solo pone COLOR.
> Volver a la estructura **no obliga a volver al defecto**: verificado hoy contra datos reales,
> `kids`, `cap` y `cap2` siguen compartiendo `accent`, así que con `accent` como identidad las tres
> pestañas abrirían tres carruseles a la vez. Medido en Chrome tras la reversión: **uno**.
>
> ⚠️⚠️ **Y la lección que deja: un arreglo puede sobrevivir a la guarda que lo protegía.** El caso
> que cazaba esto vivía en `ZonesAndRidesUnifiedTest`, que vigilaba la estructura UNIFICADA — al
> revertirla el fichero se fue entero y **el arreglo se quedó desnudo con la suite en verde**. Tiene
> red propia desde `#301`: **`ZoneIdentityIsUniqueTest`** (3 casos, 3 mutaciones que muerden, una de
> ellas vigilando lo contrario: que la PALETA siga saliendo de `accent`).
> ▶ *Cuando retires una estructura, pregúntate qué arreglos ajenos viajaban en su guarda.*
>
> ⚠️ **Lo demás de §3.quater.3 se revierte CON SU SUJETO y no es una pérdida**: el anillo de foco,
> las tarjetas inertes con `cursor: pointer`, el encuadre 62 % → 31 % y el `trim` por bytes eran
> defectos **de la estructura unificada** — la tarjeta vuelve a ser un `<a>`, la foto vuelve a media
> rejilla y la etiqueta se compone otra vez en Blade sin `trim`.
>
> **Lo de aquí abajo es el registro de lo que se hizo y por qué, no del producto de hoy.**

`[DECIDIDO owner]`: *«las zonas hay que unificarlo con las atracciones»*.

**Antes**: dos secciones con dos cabeceras. Las tarjetas de zona, cada una con un CTA «Ver
atracciones» que **saltaba** a la otra sección, y allí una **barra de pestañas** por zona con todos
los carruseles en el DOM, mostrados con `x-show`.

**Ahora**: una sola sección. Un `<article class="zone-block">` por zona con su tarjeta y —**solo si
tiene atracciones**— su carrusel debajo. Se van: una cabecera, el salto, el CTA, y **una de las dos
barras de pestañas que tenía la portada** (la de entradas se queda; es de otra sección).

### 3.quater.1 · ❗ El defecto que esto destapó, y no era de presentación

**La identidad de la zona en el marcado era `accent`, que AGRUPA y no identifica.** Medido: `cap` y
`cap2` comparten el `accent` de `kids`, así que **tres carruseles emitían el mismo `x-ref` y el mismo
`data-zone`**. Pulsar «Zona KIDS» abría **tres a la vez** —8 tarjetas y dos vacíos, 568 px de alto— y
las flechas movían uno cualquiera, porque `$refs` resuelve a UNO.

▶ **Y la misma raíz ya se había arreglado A MEDIAS**: `ThemeColorTest` tiene desde `#230` un caso
llamado *«dos zonas que comparten acento ya no comparten color»* — se corrigió el COLOR y la
identidad se quedó en `accent`. *Cuando un campo demuestra que no identifica, hay que mirar todo lo
que lo usa para identificar, no solo el sitio donde dolió.*

▶ La sección de **precios**, al lado, ya lo hacía bien: identifica por `slug` con estado local.

### 3.quater.2 · Menos JavaScript, y no por gusto

Se retiran del componente `landing`: `zone`, `setZone`, `goToRides`, `applyZoneAccent`,
`scrollSlider`, `updateProgress`, `progressLeft`, `progressWidth`. Entra `zoneSlider`, **uno por
carrusel**, con su propio progreso y su propio `$refs.slider` — el estado compartido era justo lo que
hacía posible el defecto. La **paleta va inline en el bloque**, ya compuesta por el servidor
(`ThemeSettings::zoneStyle()`); antes la aplicaba el JS a `#rides` entero.

### 3.quater.3 · ⚠️⚠️ La revisión adversarial, y lo que encontró en MI trabajo

Se pasaron **seis lentes independientes** sobre el diff (Alpine, código muerto, accesibilidad,
guardas, datos/white-label, CSS) y cada hallazgo fue **refutado por un verificador aparte**:
**40 hallazgos → 13 confirmados, 27 descartados.** Lo que sobrevivió, y es casi todo mío:

| gravedad | hallazgo |
|---|---|
| **alta ×3** | **la guarda que re-apunté quedó VACÍA** — §3.quater.4 |
| media | el carrusel enfocable **sin anillo de foco** — lo introduje con el `tabindex` |
| media | las tarjetas, ya inertes, conservaban `cursor: pointer` y el levantamiento al hover |
| media | la foto de la tarjeta: **de 62 % a 31 % de alto visible** al pasar a columna completa |
| media | **la retirada de CSS fue A MEDIAS**: tres reglas base fuera y sus `@media` dentro |
| media | **cuatro claves de idioma × tres idiomas** sin consumidor, y una describía las pestañas |
| baja | paleta declarada dos veces · dos comentarios caducados · `.slider-nav__counter` huérfana |
| baja | `trim($s, ' ·')` recorta **BYTES**: parte un `¡` y deja mojibake |

⚠️ **Lo descartado también vale**: la jerarquía de encabezados, los nombres de las flechas y el
destino del ancla `#rides` fueron señalados y **no sobrevivieron a la refutación**. Sin ese segundo
paso habría «arreglado» tres cosas que no estaban rotas.

### 3.quater.4 · ❗❗ La guarda que re-apunté quedó VACÍA, y lo firmaron TRES lentes

`ThemeColorTest` aseveraba `data-color="#111111"` en el slider. Al retirar ese atributo la re-apunté
a `--zone-1:#111111`… **sobre la página entera**. Y esa cadena **la emite además la sección de
entradas** (`ticket-prices` compone el MISMO `zoneStyle()` para las mismas zonas). Medido: en `/`
aparece **3 veces y solo UNA es el bloque de zona**.

▶ Consecuencia: **borrando el `style` del bloque —o la sección de zonas entera— el test seguía en
VERDE**. El vehículo viejo era único en toda la página; la subcadena nueva no.
▶ Arreglo: se extrae el atributo `style` **del elemento** `.zone-block` y se comprueba ahí. La
mutación de borrar ese `style` ahora tumba dos casos.
▶ *Acota al elemento antes de creerte un test verde* (`tema-por-instalacion.md` §12.4), y **una
guarda re-apuntada a otro vehículo no puede quedar más débil que la que sustituye**.

### 3.quater.5 · La guarda, y dos casos que nacieron pasando en VACÍO

`ZonesAndRidesUnifiedTest` (8 casos, **6 mutaciones muerden**): un carrusel por zona con atracciones
y ni uno más · dos zonas con el mismo acento no comparten carrusel · cada carrusel se recorre con
teclado y tiene nombre · el carrusel enfocable está en la lista del anillo de foco · las tarjetas
inertes no fingen que se pulsan · la etiqueta de edad ni vacía ni partida.

⚠️⚠️ **Dos de esos casos nacieron pasando en vacío y lo demostró la mutación**: en la BD de test solo
hay tres zonas, la única sin atracciones no se muestra en la landing y las dos que se muestran tienen
datos de edad — así que «una zona sin atracciones no emite carrusel» y «la etiqueta no sale vacía»
salían verdes **hiciera lo que hiciera la vista**. Los dos crean ahora su sujeto.
▶ *Un caso sin sujeto no vigila nada, y no se nota hasta que se muta.*

⚠️ **Y una aserción mía también estaba mal**: la del mojibake buscaba `\xA1Desde`, y esa secuencia
**aparece dentro del UTF-8 correcto** (`¡` son `C2 A1`). El defecto es un `A1` **sin su `C2` delante**.

---

## 3.quinquies · ⛔ El MOLDE EDITORIAL: diagnosticado, y tres formas RECHAZADAS (`#297`)

`[owner]`: *«la estructura se parece a la página web del antiguo cliente. La manera en que se
exponen los textos, cuándo va cada texto, cada dato»*. **Tenía razón y se puede contar.**

### 3.quinquies.1 · El molde, medido

Las SIETE secciones de la portada usan el mismo compás: `ETIQUETA` → titular **partido en dos
mitades con coma** (la segunda en color) → párrafo de intro → contenido. Y los titulares repiten la
misma fórmula retórica: «Un parque, dos zonas.» · «Tarifas claras, sin sorpresas.» · «Saltar seguro,
saltar feliz.» ▶ *Después de la segunda sección eso ya no se lee como voz de marca: se lee como
plantilla.* Tercer patrón: **el dato siempre llega el último**.

⚠️⚠️ **El caso extremo: horarios y ubicación tiene CINCO encabezados para CUATRO líneas de dato** —
«Visítanos», «Horarios & ubicación», «Horarios», «Fechas especiales», «Ubicación»—, o sea que la
sección **dice lo mismo dos veces**. 803 px en móvil, **280 de ellos el marcador del mapa**, y 31
palabras en total. Y a 390 px, `zones` ocupa **3.604 px** de los 14.831 de la portada.

### 3.quinquies.2 · Lo DECIDIDO, que sigue en pie

`[DECIDIDO owner]`: **se rompe el molde y cada sección adopta la forma de lo que ES.** La que se
explica sola no lleva párrafo; la que es una lista abre con la lista; la que es visual abre con la
imagen. **Esto no se ha retirado**: lo rechazado son las tres formas concretas, no el criterio.

### 3.quinquies.3 · ⛔ Las tres formas rechazadas — no volver a proponerlas

Se montaron en la web real, conmutables por `?forma=a|b|c`, para poder juzgarlas con sus vecinas.
`[owner]`: *«déjalo como estaba, no quiero ninguna de esas opciones»*. **Todo revertido.**

| | forma | móvil | escritorio |
|---|---|---|---|
| **A** | el estado manda: abre «Abierto ahora», parte la sección en tiempo y lugar | 750 (−53) | 895 (**+105**) |
| **B** | la respuesta primero: la dirección ES el botón, el horario al final | 781 (−22) | 926 (**+136**) |
| **C** | el sitio manda: la dirección es el titular, el estado una línea | **673 (−130)** | **757 (−33)** |

Las tres pasaban de 5 encabezados a 1. ⚠️ **A y B crecían en escritorio** porque hoy el horario y el
mapa van en dos columnas y las formas apilaban todo en una: **quien retome esto tiene que resolver
el escritorio, no solo el móvil.**

### 3.quinquies.4 · ⚠️ Lo que NO hay que volver a medir

Cuatro propuestas independientes coincidieron en **retirar**: la etiqueta, el titular partido, la
rejilla de dos tarjetas, los dos `h3` internos, el `h4` de fechas especiales y la caja blanca posada
sobre el mapa. Y en **mover**: el estado en vivo entra en la sección y sube; la fecha especial se
pega al estado (*una excepción invalida la frase de arriba, no es un apéndice*); «Cómo llegar» pasa
por encima del mapa; y el teléfono entra, que hoy la sección no lo tiene aunque el dato ya viaja.

### 3.quinquies.5 · Dos hallazgos técnicos que sobreviven al rechazo

1. **`HeroStatus` calcula la ventana de hoy y no la devuelve.** «Hasta las 21:00» es un campo más en
   un array que ya existe. ▶ **Y no se deduce de `weeklyRows()`**: su `is_today` se apaga cuando hoy
   lo gobierna una temporada o una fecha especial — se acierta casi siempre y se falla los días raros.
2. **«Parking gratis 2h» está en el código, no en el panel** (`landing.info.parking`).
   `[DECIDIDO owner]`: **se retira**, pendiente de aplicar cuando se rehaga la sección.

---

## 3.sexies · T4 · zonas y juegos: fuera el selector duplicado (`#302`)

`[DECIDIDO owner, 2026-08-31]`: *«quitar las tarjetas para seleccionar la zona, porque en las
atracciones ya hay un toggle»* · *«las cards de las zonas y las cifras, fuera»* · *«las cards de las
atracciones, solamente el título y el tag, sin texto descriptivo ni la edad»* · el carrusel **a
nuestro estilo** · el material de fachada, a mi valoración.

### 3.sexies.1 · El diagnóstico, que es suyo: DOS selectores para la misma elección

Las tarjetas de zona tenían un CTA «Ver atracciones» que **saltaba** a la otra sección, donde una
barra de pestañas hacía **exactamente la misma elección**. Medido, el de arriba costaba **1.011 px
en escritorio y 1.831 en móvil**.

▶ Queda **una sección y un selector**, y el selector dice quién es cada zona: **dibujo del kit +
nombre + edad**.

⚠️⚠️ **Y queda UNA cabecera, que eso no se pidió pero lo exige lo que sí se pidió.** Con las tarjetas
y las cifras fuera, la de zonas presentaba el vacío y la de atracciones venía detrás con el mismo
molde. Sobrevive la de ZONAS porque **su párrafo acaba en «Elige el tuyo»**, que es lo que hace el
toggle de debajo; la de atracciones solo explicaba la interfaz, que es el texto que §3.quinquies
señala como sobrante.

⚠️ **Las dos anclas sobreviven y hay seis enlaces que dependen**: `#zones` es la sección, `#rides`
envuelve selector + carriles. Y **tiene que envolver a los dos**: `applyZoneAccent()` tiñe ese
contenedor, así que con el ancla solo en el carrusel las pestañas perderían el color de su zona.

### 3.sexies.2 · El material de fachada, y por qué ése

1. **Iconos de zona (`F10`/`G5`)**: **ya viajan instalados** y **no chocan con la regla de `#286`**
   —un icono por zona es **identidad, no decoración**: se repite porque los datos se repiten—.
2. **UNA mancha** (`B1·02`, ranura `slot-zonas`) detrás del titular, elegida **midiendo las seis**:
   la más ANCHA de las libres (relación **1,19**, la forma que pide ir detrás de una palabra) y de
   las más ligeras (**17,3 %**). `B1·01` y `B1·04` ya viajan como `--deco-blob-a/b`.
3. **Nada por tarjeta de atracción**: el CSS ya lleva la lápida del intento anterior —una mancha por
   tarjeta de precio, rechazada por el owner: *«demasiado ruido con varias tarjetas»*—.

⚠️ **La posición se MIDIÓ**: su nota manda «detrás de la primera palabra, nunca detrás de todo el
bloque». Con `top: -14%` pisaba el párrafo **701 px²**; barrido de seis → **`-20%` da 0 px² sobre el
párrafo conservando 32.162 px² detrás del titular**.

### 3.sexies.3 · El carrusel, a nuestro estilo

**La siguiente tarjeta queda CORTADA por el borde**, que es la afordancia medida en
`cajon-en-movil.md` §5.2 —lo que dice «hay más» es la pieza partida por el canto, no un degradado—.
Antes cabían **3 exactas** y parecía una rejilla quieta. ⚠️ **No va a sangre completa a propósito**:
dentro de `.wrap` el `100%` de `--wrap-gutter` mide el CONTENEDOR (la trampa de `#238`).
Las flechas bajan al pie, con `--shadow-nav-*` (el cuarto rol) y **ocultas con puntero grueso**.

### 3.sexies.4 · ⚠️ Dos defectos PREEXISTENTES arreglados de paso

1. `.ride-card` tenía `cursor: pointer` y hover que levantaba, **siendo un `<article>` sin enlace**.
   El mismo defecto que `#295` cazó en las tarjetas de zona.
2. `landing` arrancaba con `zone: 'jump'`, **el slug del primer cliente en el producto**: otra
   instalación arrancaría sin pestaña activa y con los carriles ocultos, sin fallar nada.

### 3.sexies.5 · ❗ Lo que cuesta, y una guarda que faltaba desde `#257`

**Se pierden de la portada las dos fotos de zona, los dos subtítulos y las métricas por zona**, y con
ellas **`zones.image` se queda sin ningún consumidor** (ficha en `DEUDA.md`).

▶ **Guarda nueva**: `test_every_declared_slot_is_painted_by_a_screen`. «Una ranura vive lo que vive su
consumidor» estaba escrito en tres sitios y **no lo imponía nadie**.

⚠️⚠️ **Y una mutación no mordió por el fallo de siempre: el caso nació SIN SUJETO.** Pedir el dibujo
por `accent` salía verde porque en la BD de test `accent == slug` para `jump` y `kids`. Se rehízo con
una zona gemela. ▶ De paso destapó que **dependía del kit REAL, gitignorado**: habría pasado aquí y
fallado en un clon limpio.

---

## 3.septies · T5 · el titular a una línea y la tarjeta como pegatina (`#303`)

`[DECIDIDO owner, 2026-08-31]`: *«añade un CTA a las cards»* · *«las cards me parecen demasiado
simples/limpias»* · *«todos los titulares solo 1 línea»* · *«el eyebrow de los titulares lo vamos a
quitar»*.

**❗ Es la primera pieza del molde de §3.quinquies que se EJECUTA**, y coincide con lo que aquella
medición ya había establecido: se retiran la etiqueta y el titular partido.

### 3.septies.1 · El CTA abrió una pregunta que no se podía contestar solo

**No existe página de detalle de atracción** (verificado en `routes/`). `[DECIDIDO owner]`: el clic
lleva a **reservar la ZONA**, que es lo que ya hacía el botón de la única comprable y lo coherente
con el modelo —el parque vende por zona—. ⚠️ La tarjeta **no** se envuelve en un botón: metería el
precio y el badge dentro del nombre accesible.

### 3.septies.2 · La tarjeta es una PEGATINA, y los rasgos son suyos

Su `E1`: *«sombra dura de 5px, borde de tinta y un punto de color a la derecha»*.
⚠️⚠️ **CORRECCIÓN: `E1` se titula «Botones» y describe BOTONES.** La tarjeta tiene su propia entrada,
`E3 · Tarjeta de zona`, y pide otra cosa (`.ride-card` + mancha de esquina + silueta). Aplicarle la
regla de los botones fue **extrapolación mía, no una cita** — y presentarla como cita le quitó al
owner la oportunidad de discutirla. Sobreviven la sombra y el borde; el punto se retiró.
⚠️ **La sombra entra como ROL (`--shadow-float`), no como valor**: con este paquete vale
`5px 5px 0`, sin paquete es la difusa del producto. Escribir el número habría clavado su sistema
dentro del producto. ⚠️ Y **no se levanta al hover**: responde la SOMBRA, que es como se comporta una
pegatina; un `translateY` volvería a prometer el clic que `#302` quitó.

### 3.septies.3 · Los titulares: dos caminos MEDIDOS y una elección con la consecuencia delante

Con el suelo del `clamp` en 48 px, a 390 px solo caben ~10 caracteres: «Tarifas claras.» pedía
**40 px** y «Un parque, dos zonas.» **29**. Caminos: (a) una palabra, que cabe ya · (b) conservar la
voz **bajando el suelo del `clamp`**, el arreglo exacto de `#220`. **Eligió (a)**, sabiendo que *son
casi los eyebrows que se retiran* — lo cual **resuelve del todo la redundancia medida** (4 de 7
etiquetas repetían una palabra del titular) a cambio de voz.

⚠️⚠️ **PUSE UN PUNTO EN COLOR Y EL OWNER LO RETIRÓ, y el registro vale.** Al acortar, el titular
perdía su mitad de color, así que puse el punto final en el acento —y otro a la derecha del nombre
de cada tarjeta—. `[DECIDIDO owner]`: **fuera todos**. Al preguntarme por qué estaban salió lo que
había que decir: **4 de los 7 titulares ya acababan en punto y yo se lo añadí a los otros 3**, y el
color fue invención mía para un efecto colateral de mi propio cambio. Quedan **sin punto y en
tinta**; el color de sección vive en el selector, los CTA y la mancha.

### 3.septies.3.bis · ⚠️⚠️ La mancha se cayó sobre el párrafo, y el `top` era la causa

Su `top` era un **porcentaje de la CABECERA**. Al acortar los titulares, la cabecera pasó de **264 a
153 px**: el mismo `-20%` valió la mitad y la mancha bajó **11.016 px² encima del párrafo**, que es
justo lo que su nota prohíbe. *Un ajuste sobrevive a la razón que lo justificaba si nadie lo revisa
al cambiar lo que hay alrededor.*
▶ Se ancla al **bloque del titular** (que la guarda mantiene en una línea) y **se re-dimensiona**:
con 250 px de mancha sobre un titular de 79, **mover el `top` no cambiaba el número**. Barrido de
seis → `-84%` con `min(22%, 165px)`: **9.381 px² sobre el titular, 0 sobre el párrafo**.

⚠️ **Y el aire entre el hero y la primera sección se restauró**: 32 px MEDIDOS —lo que ocupaba la
etiqueta (16 de alto + 16 de margen), igual a 1280 y a 390—, como `--hero-air` y **solo en
`.hero + .section`**, para no separar las otras seis.

### 3.septies.4 · ❗ Dos titulares que NO se tocan, y se dice

1. **«VAMOS A / SALTAR»** del cierre: no es un par etiqueta+titular, son TRES partes apiladas, y es
   la coreografía que `#252`/`#253`/`#254` midieron contra el alto de ventana. **Excepción declarada
   en la guarda, pendiente del owner.**
2. **Los nombres de servicio de `/servicios`**: los escribe el operador desde el panel. *Un titular
   data-driven no puede tener una regla de longitud* — acortarlo sería truncar el texto de un
   cliente, y encoger el tipo hasta que quepa cualquier nombre es rendir el diseño al dato más largo.

### 3.septies.5 · ⚠️⚠️ Dos trampas de instrumento, las dos con número creíble

1. **Una captura de ELEMENTO más alto que la ventana COSE los elementos `fixed`**: enseñaba las
   flechas del carrusel —ocultas con puntero grueso— y el CTA flotante en medio de una tarjeta.
   Medido en el mismo contexto: **0×0 y `display: none`**. *La captura mentía y la medición no.*
2. **Un contador por subcadena dio 115 tarjetas donde hay 23** (`class="ride-card` casa con
   `ride-card__viz`, `__img`…) y acusó al producto de un defecto suyo.

---

## 3.octies · T6 · «Visítanos» en TARJETAS (`#307`)

`[DECIDIDO owner, 2026-09-01]`: *«quiero un diseño de cards, ¿cómo se llama? todo en cards, en la
medida de lo posible. lo siento más organizado y limpio»*. Es la respuesta a la tanda **B′** de §4,
y cierra el caso EXTREMO del molde editorial que §3.quinquies diagnosticó.

### 3.octies.1 · Cómo se llegó aquí, y por qué NO se propuso otra forma suelta

Esta sección llevaba **tres formas rechazadas** (`#297`) y dos tandas revertidas en el mismo carril
(`#300`, `#301`). Antes de construir nada se montaron **dos** propuestas nuevas en la web real,
conmutables por `?visitanos=a|b`, y se le enseñaron **medidas**:

| | encabezados | escritorio | móvil |
|---|---|---|---|
| heredada | 4 | 679 | 752 |
| **a** · los datos abren | 1 | 659 | 697 |
| **b** · el mapa abre | 1 | **658** (era 698 hasta bajar la banda de 260 a 220) | 697 |

Su eje era **quién abre la sección**, el único que A/B/C de `#297` dejaron sin tocar. Las descartó
las dos y pidió **tarjetas**. ▶ *El valor de esa vuelta no fue acertar: fue que la respuesta llegó
en un mensaje en vez de en una tanda revertida* — el patrón que `queja-visual-repetida` ya describía.

### 3.octies.2 · La forma

**Tres tarjetas y un solo encabezado** (el `<h2>` de la sección):

1. **CUÁNDO** — el estado en vivo (`● Abierto ahora · hasta las 21:30`), la excepción **pegada** a
   él y, tras un filete, el calendario semanal.
2. **DÓNDE** — la dirección, «Cómo llegar» y el **teléfono**, que la sección no tenía.
3. **EL MAPA** — la misma tarjeta sin relleno, para que la imagen llegue al borde.

Escritorio: las dos primeras apiladas en una columna de 380 px, el mapa a su derecha. Móvil: las
tres apiladas, en el mismo punto de ruptura en que se partía `.info__grid` (1080 px).

❗ **La tarjeta es la PEGATINA de `#303`, no una tarjeta nueva**: borde de tinta (`--paper-fg`),
radio `--r-lg`, relleno `--sp-16` y sombra dura por **ROL** `--shadow-float` — los mismos valores
que `.ride-card`. Inventar aquí un cuarto tratamiento de tarjeta habría sido repetir el hallazgo de
`#196` con las sombras y el de la tanda A con los badges: *el sistema no muere porque alguien lo
rompa, muere porque alguien añade «una variante más».*

⚠️ **Sin `:hover`, y es una decisión.** `.ride-card` responde al puntero porque lleva un CTA dentro;
éstas no llevan a ninguna parte. Dar respuesta de puntero a una tarjeta inerte es exactamente el
defecto que `#295` encontró (`cursor: pointer` sobre un `<article>` sin enlace). Hay aserción.

⚠️ **Ninguna tarjeta lleva título dentro.** §3.quinquies.4 dio por decidido retirar los dos `h3`
internos y el `h4` de fechas especiales, y meterlos «porque una tarjeta necesita cabecera» sería
reconstruir el molde desde dentro. Un punto verde junto a «Abierto ahora» no necesita que nadie lo
presente.

### 3.octies.3 · Lo que entra, lo que sale y lo que costó

▶ **Entra `closes_at`**, el hallazgo técnico de §3.quinquies.5·1: `HeroStatus` calculaba la ventana
del día y **la tiraba**. Es un campo AÑADIDO al array (`null` salvo con el parque abierto), así que
ningún consumidor cambia de conducta.
⚠️⚠️ **No se deduce de `weeklyRows()`** y por eso viaja aquí: su `is_today` se APAGA cuando el día
lo gobierna una temporada o una fecha especial — *se acertaría casi siempre y se fallaría los días
raros, que son justo los días en que el visitante necesita el dato*. Medido en vivo: con una fecha
especial activa, las dos filas semanales tenían `is_today = false`.

▶ **Sale «Parking gratis 2h»** (`[DECIDIDO owner]` de §3.quinquies.5·2): era un dato de negocio
escrito en el código y no en el panel. Hay aserción contra el TEXTO renderizado, no contra la clave
—la clave sigue en `lang/*`, retirarla es otra decisión—.

▶ **Se va el marcado heredado CON SU CSS**: `.info__grid`, `.info-card`, `.info-card h3` y `.hours`
(con sus **dos** reglas de `@media`, que es donde `#295` se dejó tres detrás).
⚠️ **`.map-card` y `.map-pin` NO se van**: las usa `/contacto`. Se comprobó **por clase exacta y por
fichero**, no por subcadena — `grep "hours"` casa con `visit__hours` y habría dicho que el
componente nuevo consume la regla vieja.

### 3.octies.4 · Medido

| | heredada | tarjetas |
|---|---|---|
| encabezados | 4 | **1** |
| escritorio (1280) | 679 | **659** |
| móvil (390) | 653 | **698** |
| teléfono | no | **sí** |

⚠️ **En móvil CRECE 45 px y se dice**: tres pegatinas cuestan ~100 px de chrome (borde + relleno ×3).
Se recuperaron 52 quitando un `margin-bottom` que se sumaba al `gap` de la rejilla —*un margen
heredado dentro de un contenedor con `gap` se paga dos veces*— y bajando el mapa apilado de 240 a
200. Los 45 restantes son el precio de la forma que el owner eligió, no un descuido.
▶ Contra lo que él vio al empezar la sesión (752 px, con una fecha especial de DEMO que decía
«cerrado») son **54 menos**.

Verificación: suite verde · Pint ✓ · docs-check ✓ · Chrome real 1280 y 390 con puntero grueso ·
**0 px de desborde y 0 errores de consola en las 9 rutas públicas** · área táctil efectiva **105×44
y 130×44** (medida con la receta de `VERIFICACION-E2E-CAJON.md` §5.duovicies).
⚠️⚠️ **La primera cifra de área fue FALSA y plausible**: mi sonda leía la caja del `<a>` (39 px) y no
el pseudo-elemento de `[data-tap]` con su `transform`. Lo delató que la forma heredada —verificada
en `#264`— daba el mismo 39. *Si tu instrumento acusa también a lo que ya estaba bien, el defecto es
del instrumento.*

### 3.octies.5 · Dos trampas pagadas

1. **Blade compila las directivas AUNQUE ESTÉN DENTRO DE UN COMENTARIO.** Citar `@php` o `@if` en
   prosa dentro de `{{-- --}}` abre un bloque que se traga media plantilla; el error sale como
   «unexpected endif» **al final del fichero**, lejos de la causa. Es el mismo escalón que `#298`
   pagó en `items-list`. ▶ *Y volvió a caer en él el comentario escrito para advertirlo.*
2. **`ArmazonCssHasNoOrphansTest` puso la suite en rojo con razón**: el componente emitía
   `visit__grp--now/--hours/--place` y `visit-card--when/--where` sin una sola regla de CSS. La
   guarda de `#253` («ningún modificador que un componente emite puede quedarse sin regla») hizo
   exactamente su trabajo. Se retiraron los modificadores, no la guarda.

---

## 3.nonies · T7 · el encargo de las CINCO secciones (`#309`)

`[DECIDIDO owner, 2026-09-01]`, en un solo mensaje: siluetas más grandes en zonas, el splash más
visible, friso en tarifas, la sección de normas rehecha con dos requisitos pegajosos, «En directo»
fuera, y los toggles de cumpleaños al ancho de la tarjeta de precio con estilo de fachada.

### 3.nonies.1 · ❗ Lo primero: esto REVISA el presupuesto de decoración

`#292` fijó **«una pieza de dibujo por sección, TRES en toda la portada»**, y la regla salió de tres
rechazos suyos en una tarde. Este encargo pide material de fachada en **tarifas**, en **las dos
tarjetas de normas** y en **cumpleaños**: con la mancha de zonas que ya había, la portada pasa de
**una** pieza a **cuatro**, y una sección lleva **dos**.

▶ **Es una revisión a sabiendas, no un descuido**, y está anotada en `IllustrationKit::SLOTS`. Lo
que **NO cambia** es la regla que vigila `FacadeDecorationIsPerScreenTest`: ninguna pieza decorativa
dentro de un bucle. Esa es la que evitaba el defecto real —una mancha por tarjeta de precio, cinco
copias de la trama en `/normas`— y sigue en pie.

### 3.nonies.2 · El kit crece de 4 a 7 símbolos

Extraídos del artboard **con un guion**, no transcritos (la regla de `#257`).

| clave | qué | de dónde |
|---|---|---|
| `slot-tarifas` | tres figuras, pies en la misma línea | el **friso familiar `G3`**, con las cajas del propio artboard (96×104 · 51×88 · 89×76, la tercera espejada, −8 de solape) |
| `slot-normas-registro` | mancha | `B1`, la 1.ª de las seis |
| `slot-normas-calcetines` | mancha | `B1`, la 3.ª |

⚠️ **Las manchas libres eran TRES de seis**: la 5.ª y la 2.ª ya viajan instaladas como
`--deco-blob-a/b` (verificado byte a byte en `#286`) y la 6.ª es `slot-zonas`.
⚠️⚠️ **`slot-tarifas` se compone con `<g transform>`, NO con `<use href="#pose">` internos.** Un
`<use>` interno dentro de un `<symbol>` que a su vez se referencia por `<use>` **externo** no
resuelve igual en todos los motores, y aquí solo hay Chrome para medirlo (`hueco-ilustracion.md`
§2.3). Un `transform` no tiene esa duda.
⚠️ **Y una cuarta mancha se generó y se RETIRÓ**: `slot-cumple` se quedaba sin pantalla en cuanto la
banda de cumpleaños pasó a usar el **contorno de `zone-cumpleanos`** —que ya viajaba—, y una ranura
sin consumidor la tumba `test_every_declared_slot_is_painted_by_a_screen`.

### 3.nonies.3 · Zonas: el tamaño no era la palanca

La silueta de cada pestaña pasa de **44 a 104 px** con margen superior negativo: asoma por encima
del borde de la tarjeta, que es lo que se pidió. ⚠️ **`overflow: visible` no habría bastado** —un
`<button>` ya lo es—: lo que hacía falta era **sitio**, y ése lo da el aire propio de `.zone-pick`.

⚠️⚠️ **La mancha sube de opacidad y NO de tamaño, y eso es un arreglo de algo que rompí.** El primer
intento la subió también a 230 px y **rompió el criterio medido de `#303`**: 9.936 px² de mancha
sobre el párrafo, justo lo que su propia nota prohíbe. Barrido de **cinco** combinaciones: ninguna
crece sin caer sobre el texto, y desplazarla a la izquierda lo empeora (2.540 px²) **cubriendo
además menos titular** (6.795 contra 9.381). ▶ Queda en `0,30` de opacidad y el tamaño de `#303`:
**0 px² sobre el párrafo** en los dos anchos. *Se ve el doble porque pinta el doble, no porque ocupe
más.*

### 3.nonies.4 · Normas: dos requisitos pegajosos y un asomo

Fuera el pliego de doce pictogramas del cliente ANTIGUO. Izquierda: **Registro obligatorio** y
**Calcetines antideslizantes**, cada uno con su mancha en grande y su CTA. Derecha: **cuatro**
normas y un enlace a `/normas`.

❗❗ **LO QUE HAY QUE SABER ANTES DE TOCARLO: el `sticky` tiene 99 px de recorrido y en pantallas de
900 px no llega a engancharse**, porque la sección entera (830 px) cabe en la ventana. Medido en
tres tamaños. **Las dos cosas que se pidieron se estorban**: la columna pegajosa solo se nota si la
derecha es mucho más alta, y el tope de 3/4 normas la deja corta. La palanca es el tope, y es del
owner.

⚠️ **El ancla `#rules` nace con su consumidor** (el CTA de tarifas). Un ancla a una sección que no
existe **no falla** —lleva a la home—, que es exactamente cómo el enlace a `#gallery` sobrevivió a
su sección durante esta misma tanda. Hay guarda con las dos mitades.
⚠️ **Los CTA reutilizan el mecanismo del producto**: registro → las MISMAS tres ramas que
`<x-site.cta-pair>` (externo · con sesión · sin sesión), calcetines → el cajón de compra, que es
donde se ofrecen como complemento. La primera versión ofrecía el alta **también a quien ya tenía
sesión** y lo cazó una guarda del nav.
⚠️ **Faltaba la media query y el defecto se vio en la CAPTURA, no en la suite**: sin colapsar, a 390
px la sección seguía en dos columnas y el `overflow: hidden` **cortaba el titular y el botón** de
las dos tarjetas. Ninguna guarda mira anchos.

### 3.nonies.5 · «En directo» fuera, y lo que deja detrás

Se van la sección, su CSS (`.gallery-marquee*`, `.polaroid*`) y su enlace del pie.
⚠️⚠️ **La categoría de cookies `social` se queda sin gatear NADA**, y con ella el ajuste
`social.feed_embed_url`, el servicio `SocialEmbed` y la línea del banner que promete «contenido de
redes sociales». **No se desmonta**: el hueco es el que van a ocupar las reseñas
(`specs/google-reviews.md`) y rehacer la fontanería sería churn. Ficha en `DEUDA.md` con las dos
salidas. ▶ De los tres casos del gate social, dos se retiran con lápida y
`test_categories_are_independent` **se re-apunta por el otro lado** —consentir redes no carga el
mapa—: misma propiedad, con el sujeto que sí existe.

### 3.nonies.6 · Cumpleaños: fuera el «foam», y el ancho se DERIVA

Los cuatro cuadrados girados eran el motivo del cliente antiguo **copiado como geometría**, que es
por lo que `#293` avisó de que *un motivo copiado a mano no aparece buscando su nombre*.

▶ En su sitio, el **contorno** de la pose de cumpleaños. El tratamiento no es gusto: lo manda `F6`
del artboard —«sobre papel, silueta plana; **sobre foto o color saturado, el contorno**»—, y esta
banda es color saturado. ⚠️ El primer intento la sacaba por el canto (26 px fuera, 597 de alto) y con
`overflow: hidden` lo que se veía era medio contorno: **no se leía como figura, se leía como un
garabato**.

▶ **Los toggles miden lo mismo que la tarjeta de precio, y el ancho se DERIVA de la rejilla**:
`(100% − gap) · 1.05/2`, con el `gap` en un token que leen los dos. Medido: **desfase 0 en ancho y 0
en borde derecho**, en escritorio y en móvil. *Un `420px` a ojo habría cuadrado a un ancho y se
habría despegado en todos los demás, sin que nada fallara.*
⚠️ **Y eso destapó un defecto PREEXISTENTE**: a 390 px la pista medía 310 y la tarjeta **321** — se
salía 11 px de su propia columna, escondida dentro del relleno de la banda. `min-width: 0` en las
celdas.
⚠️ El icono del toggle es el del **PRODUCTO** (`iconKey()`, `#259`), no uno elegido aquí: hoy los dos
packs traen `party` y por tanto el mismo dibujo, y eso es **dato** — el parque lo cambia en el panel.

### 3.nonies.7 · Dos trampas y una regla que casi rompo

1. ⚠️⚠️ **Retirar la sección de normas de la portada se llevó por delante `.rules-grid` y `.rule`, que
   los usa `/normas`** — la página a la que lleva el CTA nuevo. Lo cazó buscar consumidores **por
   fichero y por clase exacta**, no por «esto ya no lo usa la home». Restaurados.
2. ⚠️ **Una sonda dio 20.306 px² de «friso sobre titular» y era FALSO**: medía la caja del `<div>` de
   920 px que envuelve al titular, no su tinta. La captura lo desmintió — el friso no toca ninguna
   letra. *Un solape de cajas no es un solape visual cuando una de las cajas es un contenedor.*
3. ⚠️ **Una mutación no mordió y la mala era la mutación**: devolver la URL `/#gallery` al pie no
   pinta nada, porque el bucle lo manda la lista de RÓTULOS. Con el rótulo, muerde.

---

## 3.decies · T8 · el orden de la portada y el ritmo entre secciones (`#314`)

`[DECIDIDO owner, 2026-09-01]`: *«la sección de entradas la ponemos la primera, después cumpleaños,
después el parque, después ubicación, normas y dudas»* y *«deja un espacio sano entre cada sección,
que haya aire»*.

### 3.decies.1 · El orden

**hero → tarifas → cumpleaños → zonas → visítanos → normas → dudas → cierre.** En el marcado es
mover UN bloque: `#zones` deja de ir primero y pasa detrás de cumpleaños.
▶ **Lo que NO hubo que tocar, y conviene saber por qué**: los anclas (`#zones`, `#rides`,
`#pricing`, `#info`, `#rules`) no dependen de la posición, y `--hero-air` cuelga de
`.hero + .section`, así que el aire extra de la primera sección se muda solo.

### 3.decies.2 · El aire: el número no era el problema

`.section` sube de 96 a **120 px** (80 en móvil). Pero lo que se veía mal no era la escala: era que
**la banda de cumpleaños no es un `.section`**. Llevaba `64px 0 0` —relleno inferior **CERO**— y a su
alrededor había la mitad de aire que entre las demás. Medido antes de tocar nada, tinta a tinta:
**96 y 110 px alrededor de cumpleaños contra 193 en el resto**. Con cumpleaños en segunda posición,
ese salto pasó a estar donde más se nota.

⚠️ **Y `.bd-page` tiene DOS hijos en la portada**, no uno: `bd-sec1` (la banda) y `bd-sec3` (el «paso
a paso»). Quien manda en el aire de SALIDA del bloque es el segundo — asumir que era el primero fue
un error que costó una vuelta.

▶ Queda **240 px uniformes en escritorio y 160 en móvil** en todas las fronteras.

⚠️⚠️ **Las reglas de móvil no hacían NADA, y el CSS se leía correcto.** Se escribieron en el `@media
(max-width: 768px)` que vive ~100 líneas ANTES de las bases de `.bd-sec1`/`.bd-sec3`: a igual
especificidad gana la última regla del fichero, así que la base pisaba al media query. *Es el fallo
de cascada más caro de diagnosticar, porque no hay nada que leer que parezca mal.* Van ahora
inmediatamente detrás de sus bases.

### 3.decies.3 · Una guarda que dependía del ORDEN

❗ `ZonesSectionTest::seccion()` recortaba la sección **«desde `id="zones"` hasta `id="pricing"`»**.
Al adelantar tarifas, el recorte se comió el resto de la portada y el caso del encabezado único
contó **cuatro `<h2>`**: la guarda **falló con el producto sano**.

▶ *Un localizador que depende de qué sección viene DESPUÉS no está acotando una sección: está
acotando un tramo de página.* Re-apuntada al ELEMENTO (`<section id="zones">…</section>`), que es lo
único que no cambia al reordenar — y queda más fuerte que la que sustituye.

### 3.decies.4 · Tres trampas de instrumento en una sola tanda

Las tres dieron cifras creíbles, y por eso están escritas:

1. **La sonda de aire contaba la CAJA de un contenedor como tinta.** `section.bd-sec3` es el último
   descendiente y su caja llega al borde, así que «el aire después de cumpleaños» salía **38 px**
   donde hay **240**. *Un contenedor no es tinta.*
2. **Su primera versión metía a `.bd-page` y a su hijo en la misma lista de secciones**, con lo que
   el aire entre ellos salía **negativo**. *Un contenedor y su contenido no son dos secciones.*
3. **La captura de página completa enseña las fotos del carrusel como TRAMA** y parece que no
   cargan. No es un defecto: son **23 `loading="lazy"` en un carril horizontal** y solo cargan las
   **3** visibles. Comprobado desplazándose de verdad antes de tocar nada.

### 3.decies.5 · Las 35 fotos

Las nueve que faltaban entran como `pjp-NNN.webp`. **Las 26 ya asignadas NO se renombran**: sus
nombres describen la ATRACCIÓN (`jump_saltos_libres`), no al cliente, y pasarlas a `pjp-NNN` metería
la numeración de este parque dentro del producto —peor para white-label— y tocaría el seeder y
cuatro tests sin ganar nada.
⚠️ **26 huecos para 35 fotos**: nueve quedan servidas y sin asignar. Varias son cosas que el parque
tiene sin dar de alta como atracción (arenero de bebés, correpasillos, cubo de Rubik, aro luminoso):
crearlas es DATO, y hacen falta nombre y edad del owner.

---

## 4. Lo que queda, y en qué orden

> ⚠️ **La base de partida cambió el 2026-08-31 (`#300`)**: el owner revirtió la T1 entera y dejó
> horarios/ubicación y normas **las dos en su forma heredada**, para rediseñar desde el mismo punto
> en vez de encima de una tanda a medias. **Las dos vuelven a la lista de pendientes**, y la portada
> recupera **dos** de las tres colocaciones de dibujo.

| # | tanda | qué |
|---|---|---|
| **A′** | **Normas de la portada, otra vez** | Revertida por `#300`. Su decisión original —«sin imagen, texto simple y un CTA»— **no la retiró el owner**: lo que rechazó fue la ejecución. Se rehace dentro del rediseño del molde, no suelta |
| **B′** | ~~**Horarios y ubicación**~~ **(hecha, `#307` → §3.octies)** | Era el caso extremo del molde: 4 encabezados para 4 líneas de dato. `[DECIDIDO owner]` **tarjetas**, tras descartar también las dos formas nuevas de §3.octies.1. Queda en 1 encabezado, con el teléfono dentro y sin «Parking gratis 2h» |
| **C′** | **Zonas y atracciones, otra vez** | Revertida por `#301`. La unificación **no la retiró el owner como criterio**: pidió volver a la base para rediseñar desde ahí. ⚠️ **Al rehacerla, la identidad es `slug`** — el defecto ya está cerrado y con guarda (`ZoneIdentityIsUniqueTest`) |
| **B** | ~~La marquesina de `/servicios` → **cinta `C3`**~~ **(hecha, `#293`)** | `[DECIDIDO owner]` quitar la de palabras. ⚠️ **Choca con `#252`**, que retiró la marquesina de la portada por espacio: la cinta vuelve, pero en `/servicios`, no en la portada |
| **C** | Los cubos 1-2-3 del cumple · la nota de calcetines | forma por decidir |
| **D** | La galería de polaroids | ¿sigue el lenguaje polaroid o pasa a cinta con poses (`F11`)? |
| **E** | **El SPA**: catálogo y badge de destacado | el owner quiere **cambiar la presentación**, no solo vestirla: es su propia spec |
| **F** | ~~**Iconos de tarjeta y el tope de dos líneas**~~ **(hecha, `#319`)** | `[DECIDIDO owner]` con el patrón de ficha de Google Store. Iconos **con dato detrás**: categoría en «Visítanos», acción en el botón de teléfono, y en la tarifa **el marcador que el panel ya elige** (`ticket_types.icon`). El tope son **dos mecanismos** —copy reescrito + corte como red—, con asimetría deliberada: cortar solo es seguro donde el texto tiene segunda casa (`/normas`). ⛔ **Las 23 tarjetas de atracción NO reciben icono**: se probó con la EDAD —dato real, 19 de 23— y lo cazó la guarda de `#302`; `[DECIDIDO owner]` respetarla, y sin dato detrás un icono repetido 23 veces es decoración en un bucle (`#286`). ⚠️ De paso salió un defecto **preexistente** (medido con control): la unidad del precio vivía dentro de `.price__num` —80 px, `line-height 0.85`— y se partía en dos con 68 px de hueco. |

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
