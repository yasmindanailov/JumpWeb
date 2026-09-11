# Mockup Play Jump Park — copia local del canvas de diseño

> ## ⚠️⚠️ DOS TRAMPAS DE LA DESCARGA, medidas el 2026-08-28 (léelas antes de usar `assets/`)
>
> **1. `DesignSync · get_file` TRUNCA los binarios a 192 KiB y NO falla.** Devuelve
> `truncated: true` y un PNG con **cabecera válida, dimensiones correctas y sin `IEND`**. En esta
> carpeta hay **tres** así, bajados en otra sesión: `assets/logo.png`, `assets/fachada-mural.jpg` y
> `assets/fachada-rotulo.jpg` — los tres pesan **196.608 bytes exactos**, que es el tope.
> Los otros cinco rasters (`personaje-pjp`, `saltador*`, `tag-lorca`) están **enteros**.
> ▶ **Un raster grande se genera del VECTOR**, que sí baja entero. Los SVG y los `.dc.html` no
> tienen este problema salvo que pasen de 256 KiB (`Landing PJP Modos` está a 13 KiB del tope).
>
> **2. Transcribir base64 desde el contexto del agente CORROMPE el fichero, en silencio.** Medido:
> un PNG de 6.900 B salió de **4.632 B**, sin `IEND` — y aun así con cabecera PNG válida y
> dimensiones correctas, o sea que un verificador que mire la cabecera lo da por bueno.
> ▶ **Lo que no se pueda extraer del disco, se genera. No se copia a mano.**
>
> ## 🎨 `marca/` — el paquete de marca, ENTREGADO (2026-08-28)
>
> El owner subió `marca/` (28 ficheros) con el logotipo y el icono. Es lo que `#211` y
> `INSTALACION-CLIENTE.md` §4.a.quinquies llevaban pidiendo. Lo normativo es
> **`Marca PJP entrega.dc.html`**, que dice qué es cada fichero.
>
> | | |
> |---|---|
> | Logotipo | `logo-pjp.svg` (con silueta, **el elegido**) · `logo-pjp-y.svg` (con Y) · `-blanco` para fondo oscuro · `-negro` para una tinta. **Letras en CONTORNOS**, 0 fuentes, 1527 × 561 |
> | Icono | `icono-pjp.svg` (maestro, 512) · `icono-pjp-maskable.svg` · `favicon.svg` (64) · `favicon.ico` (16/32/48) · PNG de 16 a 1024 |
> | Animado | `logo-animado.html` — el relevo de la Y. **No entra en el producto**: el hueco acepta un fichero, no una página |
>
> ▶ En disco hay una copia de los **cinco SVG que usa el producto** (`marca/`) y los PNG
> **rasterizados del vector** (`marca-png/`). ⚠️ Los PNG **no** son los del canvas: son
> equivalentes generados con Chromium desde el mismo vector, por la trampa 1.
> ⚠️ Los del canvas a 16 y 32 llevan un ajuste óptico propio («la figura sube al 90 % y gana
> contraste») que un reescalado no reproduce; para esos dos tamaños el producto usa `favicon.svg`,
> que ya viene ajustado a 85 %.

> ## ❗❗ ALCANCE — LÉELO ANTES QUE NADA (`[DECIDIDO owner, 2026-08-28]`)
>
> **De este canvas SOLO se toma el SISTEMA DE DISEÑO**: colores, elementos, iconos, formas, menú,
> hero de cabecera y pie. **El resto no se toca** hasta que el owner lo diga — sus palabras: «ahí
> hay muchas pruebas».
> ▶ Eso deja FUERA, explícitamente: las **tres** variantes de «02 · El parque»
> (`Descubre-el-Parque`, `Recorrido-Parque`, `Elige tu Zona`), los cuatro artboards de cumpleaños
> (`Cumple-Escaleta`, `Cumple-Frase`, `Cumple-Puertas`, `Cumpleaños PJP variantes`) y las secciones
> de la landing que aún no existen en el producto.
> ⚠️ **Y el CTA del hero NO se copia**: el mockup se lo ha devuelto, pero la decisión de `#195` —el
> hero sin CTA— **sigue en pie** (`[DECIDIDO owner, 2026-08-28]`).
>
> ## 📅 Estado de esta copia — 2026-08-28
>
> | | |
> |---|---|
> | En el canvas | **26** artboards |
> | En disco | **19** (`Landing PJP Modos` e `Icono PJP` refrescados hoy; el resto, del 27 a las 07:30) |
> | Sin bajar | `Cumple-Escaleta` · `Cumple-Frase` · `Cumple-Puertas` · `Cumpleaños PJP variantes` · `Descubre-el-Parque` · `Recorrido-Parque` · `Elige tu Zona` — **los siete están FUERA de alcance**, por eso no se han persistido |
> | Idéntico al remoto | `Colores de Marca PJP` (diffeado el 28 por la mañana: **0 líneas**) · **`Landing PJP Modos` re-diffeado el 28 a las 09:24: 0 líneas** — por primera vez la copia estaba fresca |
>
> ❗ **`Landing PJP Modos` había vuelto a caducar: 428 líneas de diff** contra la copia de las 17:53
> del 27. Lo que traía es **la pasada de MÓVIL completa** —`--pjp-margen`/`--pjp-radio`/`--pjp-tope`/
> `--pjp-barra`, `100svh`, `env(safe-area-inset-*)`, carruseles con `scroll-snap`, el panel de
> reserva convertido en **hoja inferior** con asa, y una **barra de acciones de móvil** (3 enlaces
> de icono + «Reservar» naranja)—.
> ▶ **Eso es el artboard que faltaba para la 2c·4b** (`specs/armazon-y-menu.md` §5·1): el menú en
> móvil ya no está bloqueado por falta de diseño.
> ▶ Y **estrena tres variantes de «El parque», no dos**: `Elige tu Zona` es una maqueta isométrica
> en SVG de todo el parque, y también es «02 · El parque». Sigue **sin decidir cuál se queda**.

> Importado el **2026-08-27** desde el proyecto de Claude Design del owner
> (`projectId = 8c37d2d2-7e9c-43a9-bc25-aacb6607f2ad`) con el MCP **`DesignSync`**.
> **Es material de REFERENCIA, no código del producto.** Aquí no se edita nada: el original vive
> en el canvas y esta carpeta se vuelve a bajar cuando el owner lo cambie.
>
> ## ❗❗ ESTA CARPETA CADUCA SIN AVISAR — y ya pasó
>
> **Medido el 2026-08-27**: la copia bajada esa misma mañana a las 07:30 **ya no valía a las 11:00**.
> `Landing PJP Modos` traía **386 líneas de diff** contra el remoto —el hero gana una tira de cinco
> colores, la sección de entradas pierde su fondo cian y sus dos goterones— y `Colores de Marca PJP`
> también se había movido. El owner sigue trabajando en el canvas entre sesiones.
>
> ▶ **Antes de implementar NADA desde aquí:** `DesignSync · method=list_files` y diffear contra lo
> que tengas en disco. **Una copia vieja se lee exactamente igual de bien que una fresca**, no falla
> y no avisa — y el trabajo hecho desde ella parece correcto hasta que alguien mira el canvas.
>
> ▶ **Estado de esta copia:** `Landing PJP Modos` refrescado el **2026-08-27 a las 17:53**;
> `Iconos PJP` **diffeado esa misma tarde y es IDÉNTICO** al de las 07:34; `Colores de Marca PJP`
> es de las 11:00. Los demás `.dc.html` son de las **07:30** y no se han diffeado.
>
> ⚠️⚠️ **Y volvió a caducar el mismo día, por segunda vez.** La copia de las 12:18 traía **210
> líneas de diff** contra el remoto de las 17:53: un bloque de **ritmo decorativo por sección**
> (una silueta o una mancha por sección, y nunca dos familias juntas; niño en Kids/familia/
> cumpleaños, adulto en acción y normas) y un **sistema nuevo de animación al scroll** —seis
> secciones que entran desde abajo y se van hacia arriba pegadas al scroll, con hoja de estilo
> propia y respeto explícito a `prefers-reduced-motion`—. **Ninguna de las 210 toca el menú ni
> el mobiliario de la cabecera**, que es lo que se estaba leyendo para `specs/armazon-y-menu.md`.
> ▶ La moraleja no cambia, se refuerza: **diffear no es un trámite de una vez al día**.
>
> ## 🆕 Dos artboards nuevos, y son DOS VARIANTES DE LO MISMO
>
> `Descubre-el-Parque.dc.html` y `Recorrido-Parque.dc.html` aparecieron el 2026-08-27. **No están en
> disco** (se leyeron con `get_file` pero no se persistieron: bájalos si los necesitas). Los dos son
> la **misma sección**, la «02 · El parque» de la landing, en dos formas:
>
> | Artboard | Qué es |
> |---|---|
> | `Descubre-el-Parque` | Mapa de **3 parcelas** con **20 chinchetas** numeradas, ficha lateral que cambia al pulsar, contador de «vistos» y resumen de precios por zona |
> | `Recorrido-Parque` | Carril de **4 paradas** con `scroll-snap` (entrada → Kids → Jump → plaza), tarjetas de 520 px con foto, chips, precio y CTA; la última en tinta |
>
> ❗ **Cuál se queda está SIN DECIDIR** (preguntado al owner el 2026-08-27; respondió «aún no lo he
> decidido»). No se implementa ninguna hasta que lo diga, y en todo caso es **tanda 3** del tema.
> ⚠️ **Estrenan `#E6007E` (Magenta Chispa)** como acento de «la plaza»: está en `Colores de Marca`
> pero **no aparecía ni una vez** en `Landing PJP Modos`.
>
> ## ❗ La norma de superficies CAMBIÓ, y contradice a la doc anterior
>
> El hallazgo **`S-00`** de `Auditoría Landing PJP` —severidad **Alta**, estado **Aplicado**— dice:
> «el fondo de una sección **nunca** lleva color… papel continuo de arriba abajo… el contraste lo dan
> **las tarjetas**… ni negro ni cian a sangre en ninguna sección», y **pasa a ser norma por encima
> del orden de página 01–08**. Los dos intentos de alternancia por fondo están **Revertidos**
> (`S-01` entradas en cian, `S-02` cumpleaños en tinta).
> ▶ **Verificado, no creído**: **cero** `calc(50% - 50vw)` y **cero** `width:100vw` en los 218 KB de
> `Landing PJP Modos`. Antes había tres.
>
> ⚠️ Este repo es el **PRODUCTO** y no lleva la marca de ningún cliente (`DECISIONES #1`). Esta
> carpeta es la marca del **segundo cliente**: mírala para diseñar los mecanismos, no la copies
> dentro de `public/css/` ni de `resources/views/`.

## ❗ Lo primero: NO todos los artboards llevan el sistema de color vigente

**Medido**, contando ocurrencias de la paleta nueva (`#101418` · `#F4F4F1` · `#1AA9DE` · `#F2711C`
· `#A3C21C` · `#0A5C93` · `#F5C400` · `#5FA82E` · `#D93E14` · `#9AA1A8` · `#1A1F25`) frente a la
anterior (`#101113` · `#ECF3F7` · `#2FB6DE` · `#0E8FCB` · `#8DC63F` · `#EFEDE7` · `#141517` …):

| Sistema NUEVO — se implementa DESDE AQUÍ | Pre-migración — exploración, **no** es la fuente |
|---|---|
| `Landing PJP Modos.dc.html` ← **la landing completa** | `Logotipo variantes.dc.html` |
| `Colores de Marca PJP.dc.html` ← **la norma** | `Menu PJP.dc.html` |
| `Auditoría Landing PJP.dc.html` | `Hero PJP variantes.dc.html` |
| `Iconos PJP.dc.html` | `Info PJP variantes.dc.html` |
| `Microanimaciones PJP.dc.html` | `Boton Reservar variantes.dc.html` |
| `Precios PJP variantes.dc.html` | `App PJP.dc.html` |
| `Zonas PJP variantes.dc.html` | `Elementos Fachada.dc.html` |
| `Cabecera Seccion variantes.dc.html` | `Marquesina Castillo.dc.html` · `Salta la Ciudad.dc.html` · `Tag Lorca.dc.html` |

▶ **La trampa concreta**: el menú, el logo y el hero que el owner llama «oficiales» tienen artboard
propio **con la paleta ANTERIOR**, y a la vez están **rehechos con la paleta nueva dentro de
`Landing PJP Modos`**. Implementar desde `Menu PJP.dc.html` o `Hero PJP variantes.dc.html` mete
`#2FB6DE`, `#0E8FCB` y Anton en el producto — colores y fuente que el propio sistema declara
caducados en su tabla de migración (§11 de `Colores de Marca`).
▶ Las 8 ocurrencias «viejas» de `Colores de Marca` **son su tabla de migración**: dice de qué a qué.

## Qué es normativo

1. **`Colores de Marca PJP.dc.html`** — 15 secciones: 8 colores de núcleo con `hover`/`press`/oscuro,
   9 neutros, ley 60/30/10, roles por elemento **en los dos fondos**, botones, texto resaltado,
   fondos, auditoría WCAG de 20 pares, prohibiciones, tabla de migración, escala tipográfica de 10
   niveles, radios `0·6·10·16·24·999`, sombras, y el orden de página 01–08.
2. **`Microanimaciones PJP.dc.html`** — 4 curvas, 7 duraciones (120/180/240/320/420/620/900),
   11 microanimaciones, máx. 2 a la vez, y el contrato de `prefers-reduced-motion`.
3. **`Iconos PJP.dc.html`** — el set de glifos.
4. **`Landing PJP Modos.dc.html`** — la landing montada: logo+menú, hero, entradas, zonas,
   cumpleaños, antes de venir, info, opiniones, cierre, pie **y el sidebar de reservas**.
5. **`Auditoría Landing PJP.dc.html`** — el contraste de (4) contra (1)(2)(3): **29 hallazgos**,
   21 aplicados, 5 esperando decisión del owner. Léelo antes que la landing: dice qué de lo que ves
   ya está corregido y qué no.

## ⚠️ El asset del saltador («la Y») lleva la paleta ANTERIOR

Medido el 2026-08-28: `assets/saltador-y-verde.png` —el que usa el lockup de `Landing PJP Modos`,
que es artboard **normativo**— tiene como color dominante **`#2FB6DE`** (19 % de sus píxeles
opacos), y ése es **el cian de la paleta pre-migración**, la que el propio sistema declara caducada
en su tabla de §11. No es una silueta plana: lleva ese cian, negro `#1B1B19`, blanco y un degradado
verde-cian.
▶ Es decir: **un artboard normativo referencia un asset sin migrar.** Al reproducir el logotipo hay
que sacar de ahí la FORMA y decidir el color con el sistema vigente, que es la misma regla que la
spec del tema aplica a los diez artboards sin migrar.
⚠️ Y el artboard `Icono PJP` dice que la figura sale de **`assets/saltador-y.png`** (sin «-verde»),
que es otro fichero: antes de usar ninguno, comprobar cuál está migrado.

## El logo NO es una imagen

Medido: `Landing PJP Modos` **no referencia `assets/logo.png` ni una vez**. El logotipo es un
lockup compuesto en CSS con **Lilita One** + 6 capas de `-webkit-text-stroke`, una pila de
`text-shadow` que hace el bisel, relleno por letra y dos degradados con `background-clip:text`
— y ya en la paleta nueva (`#0A5C93` · `#1AA9DE` · `#101418`).

▶ Consecuencia: **son CINCO familias, no cuatro.** El contrato de `Colores de Marca` §12 declara
Bungee · Hanken Grotesk · Permanent Marker · JetBrains Mono; el logo añade **Lilita One**.
Medido en `Landing PJP Modos`: Hanken 11 usos · JetBrains Mono 37 · Lilita One 2 (el logo)
· Permanent Marker 2 · Bungee 40 vía `var(--pjp-display)` · **Space Grotesk: se carga y NO se usa**.
▶ Las cinco las sirve **`fonts.bunny.net`** (comprobado, HTTP 200 una a una), que es el único host
que permite la CSP del producto (`SecurityHeaders`). **No hace falta tocar la CSP.**

## ⚠️ Tres ficheros llegaron CORTADOS por el MCP (tope de 256 KiB)

| Fichero | Estado medido |
|---|---|
| `assets/logo.png` | ❌ **INSERVIBLE**: 1501×671, sin `IEND`, `IDAT` roto — solo 243 de 671 líneas descomprimen (36 %) y **ffmpeg lo rechaza entero**. No lo usa la landing (ver arriba); sí `Logotipo variantes` y `Salta la Ciudad` |
| `assets/fachada-mural.jpg` | ⚠️ usable: JPEG **progresivo** sin `EOI`, decodifica 1536×1024 con artefactos. Sirve de foto de referencia |
| `assets/fachada-rotulo.jpg` | ⚠️ usable: ídem, 2000×1126 |

Los otros 18 assets están **completos** (`IEND` presente / SVG bien formados).
▶ Si hiciera falta el `logo.png` de verdad, **no se puede arreglar desde aquí**: lo exporta el owner
del canvas y lo deja en `assets/`.

## Cómo mirarlo

Los `.dc.html` son artboards del canvas: `support.js` inyecta en tiempo de ejecución
**React 18, ReactDOM y Babel standalone desde `unpkg.com`**, y las fuentes desde Google Fonts.
▶ **Necesitan internet**; offline no pintan.
▶ Y por eso mismo **nada de esto se sirve nunca desde el producto**: la CSP no permite `unpkg.com`,
y con razón.

## Cómo volver a bajarla

La fuente de verdad es el canvas, no esta carpeta. Para regenerarla, con el MCP `DesignSync`:

1. `method=list_files · projectId=8c37d2d2-7e9c-43a9-bc25-aacb6607f2ad` → la lista de rutas.
2. `method=get_file · path=<cada ruta>` → el sobre JSON
   `{path, content, contentType, isBase64, truncated}`.
3. Escribir `content` en `mockup_playjumppark/<path>`, decodificando base64 si `isBase64`.

⚠️ **Los resultados llegan por DOS caminos y hay que barrer los dos**: los grandes los persiste el
harness en `tool-results/*.txt`; los pequeños viajan inline y solo quedan en el JSONL de la sesión.
Barrer solo uno da un inventario que parece completo y no lo es.
⚠️ **Y `truncated` hay que imprimirlo siempre**: el MCP corta los binarios a 256 KiB, un JPEG
progresivo cortado se ve igualmente, y un aviso que nadie lee es un fichero incompleto que nadie
sabe que lo está.

## Referencias

- Una rota, y es del mockup, no de la importación: `Landing PJP Modos` enlaza **`invitacion.html`**
  desde el CTA «Crea la invitación de su cumple», y esa página **no existe en el canvas**.
  ▶ En el producto eso ya existe: el diseñador de invitaciones (`.invite-card`, capturado con
  `html2canvas`). Es un enlace de maqueta, no un activo que falte.
- `uploads/lorca_grafiti_Corona.png` se trajo aunque no estaba en la lista: lo necesita
  `Tag Lorca.dc.html` para su comparativa PNG↔SVG.
- El resto de `uploads/` y todo `screenshots/` **no se importó**: no lo referencia ningún artboard.
