# Rediseño desde el canvas de Claude Design

> **Estado:** ✅ **Fase 1 y Fase 2 CERRADAS: la portada entera, con sus ocho secciones** · 🟦 **Fase 3 EN CURSO: el armazón de las páginas COMPLETO (T3a·1 → T3a·3; la T3a·4 revertida por `#527`); de las páginas, `/atracciones` y `/cumpleanos` (`#528`); sigue `/precios`** (§5.5) · 🧩 **la Fase 4 (el SPA) la lleva otro ordenador desde `#530`** (`docs/CARRIL-SPA.md`)
> **Banda de decisiones:** 470–499 (la reapertura es `#469`), **agotada en `#499`** → la Fase 3 va en **520–549**
> **Fuente:** canvas `8c37d2d2-7e9c-43a9-bc25-aacb6607f2ad` · sistema **v1.32** · tokens **v1.10**
> ⚠️ Los tokens iban por **v1.9** el 2026-09-09 por la mañana y por **v1.10** por la tarde: esta
> fuente se mueve sola, así que **se relee antes de cada tanda, no una vez por carril** (`#474`).
> **Copia local:** `mockup_playjumppark_v2/` (gitignorada) · ⚠️ ver §1.2

---

## 1 · Por dónde se retoma

`#452` paró el carril de diseño el 2026-09-03 con las palabras del owner: *«el diseño lo voy a
delegar a claude design primero… cuando lo tenga, te aviso»*. **Ese aviso llegó el 2026-09-09** y
esto es lo que sale de leer su sistema entero y contrastarlo con el código, que era exactamente lo
que aquella decisión mandaba hacer primero.

### 1.1 · Las dos decisiones que gobiernan el carril

**`[DECIDIDO owner, 2026-09-09]` — se adopta el sistema del canvas ENTERO**, incluidas sus seis
cifras, que hoy chocan con el código (§3). Se cambian en la **Fase 1**, con sus guardas
actualizadas y la suite verde antes de tocar una sola sección. El motivo es de coste: cambiarlas
después obliga a rehacer lo construido encima.

**`[DECIDIDO owner, 2026-09-09]` — todo lo que sea del dueño se pregunta antes de construir.**
Cualquier cosa que el canvas marque como `[DECIDIDO owner]` o deje pendiente de él se le trae
delante, aunque sea pequeña. No se decide por criterio propio ni se deduce del canvas.

⚠️ **Y esto CADUCA el `[DECIDIDO owner, 2026-08-28]`** que decía *«del canvas SOLO se toma el
SISTEMA DE DISEÑO: colores, elementos, iconos, formas, menú, hero de cabecera y pie; el resto no se
toca — ahí hay muchas pruebas»*. Aquella frase describía un canvas de 26 artboards de exploración.
El de hoy tiene **45 vigentes y 18 archivados**, con la portada y las siete páginas **cerradas**.

### 1.2 · ⚠️ Dos trampas de la copia local, medidas

**`mockup_playjumppark/` (la vieja) NO está caducada: ES EL ARCHIVO.** Sus 21 artboards son
literalmente los que el canvas movió a `archivo/`, y el `CLAUDE.md` del propio canvas dice de esa
carpeta: *«es histórico: no se consulta para decidir ni se actualiza. Las piezas de ahí usan paleta
antigua (`#2FB6DE`, `#1B1B19`, `#8CC63F`, Anton, Lilita One) — no copiar valores de ahí»*.
▶ **No se lee para decidir nada.** La copia buena es `mockup_playjumppark_v2/`.

**`Portada PJP.dc.html` se baja TRUNCADO.** `DesignSync · get_file` corta a **256 KiB** y ese
fichero los pasa: llegó en **262.144 bytes exactos**. Es el único de los 45, y es justo el montaje
de la portada entera. ⚠️ El campo `truncated: true` sí viene en la respuesta —el decodificador lo
avisa—, pero **el fichero parece válido**: es HTML, abre bien y se corta por la mitad sin decirlo.
▶ Mientras no haya otra vía, la portada se lee de **sus artboards por sección**, que están
completos y son de donde el montaje se copió.

---

## 2 · ❗❗❗ El filtro que atraviesa todas las fases: qué es PRODUCTO y qué es PlayJump

`DECISIONES #1`: **este repo es el PRODUCTO, sin marca de ningún cliente.** El canvas es de
PlayJump. Así que **cada pieza que se copie tiene que pasar por esta pregunta antes**: *¿esto es un
mecanismo, o es de este cliente?*

| | Va al repo (mecanismo) | Va fuera (BD · `client.css` · paquete gitignorado) |
|---|---|---|
| Color | el **rol** (`--action`, `--interactive`, `--ok`, `--focus`) | el hex (`#F2711C`, `#1AA9DE`, `#A3C21C`) |
| Tipografía | los cuatro huecos (`--font-display/body/accent/mono`) | Bungee · Hanken Grotesk · Permanent Marker · JetBrains Mono |
| Iconos | el **set de UI** (los 65 glifos son genéricos) | poses, manchas, frisos → `client-kit.svg` |
| Secciones | la **estructura y el mecanismo** | textos, precios, fotos, horarios → BD |
| Movimiento | la escala de duraciones y curvas | — |
| Marca | el **hueco** (`client-logo.svg`, `client-favicon.svg`) | los ficheros |

⚠️⚠️ **La trampa está fichada desde `#257` y sigue abierta**: los **19 dibujos de parque** (5 «Del
parque» + 14 zonas) son del cliente y **no existe mecanismo para sustituir un dibujo por
instalación**; `favicon.svg` todavía conserva el naranja del PRIMER cliente. Si se monta la landing
de PlayJump tal cual, se clava su mural dentro de JumpWeb y **el segundo cliente deja de poder
existir sin que nada falle**.

▶ `hueco-ilustracion.md` (`#286`/`#287`) ya construyó el mecanismo para esto: `<use>` externo sobre
`client-kit.svg`, con gramática **cerrada** (`slot-*` los declara el producto, `zone-<slug>` sale de
`zones.slug`). Toda pieza gráfica del canvas entra **por ahí** o no entra.

⚠️⚠️ **Y el filtro corta en las DOS direcciones: el canvas nos cazó siete hexadecimales del PRIMER
cliente vivos en el producto** (`#474`). Los nombra la v1.10 de sus tokens al explicar por qué nacen
las superficies de aviso sobre papel: los cuatro avisos del sistema solo existían sobre tinta y el
cajón es papel de arriba abajo, así que el hueco lo rellenaban `#fbeaea`, `#e3b5b0`, `#8a2b22`,
`#a93226`, `#e7f6ec`, `#b45309` y `#92400e`. **Verificado aquí y están**: 7 usos en `site.css`, y
además en los **correos**, en la **hoja de sala** y en el **PDF del justificante** — o sea en
superficies que ni las guardas de la landing ni las del cajón miran. Ficha en `DEUDA.md`.

---

## 3 · Las seis cifras del sistema · contraste MEDIDO

Medidas en el código el 2026-09-09, no de memoria. Las seis se adoptan (§1.1).

| | Canvas v1.9 | Código hoy | Dónde vive · qué lo vigila |
|---|---|---|---|
| Ancho de columna | **1120** | `1176` | `public/css/site.css` (`:root`, `--col-max`) · `ColumnIsDeclaredOnceTest` |
| Objetivo táctil | **48** | `44` | `public/css/site.css` (`:root`, `--tap-min`) · `TouchTargetTest` |
| Escala de radios | **4** (`0·10·16·999`) | **7** (`5·8·10·14·16·28·999`) | `ShapeScaleTest::RADIUS_SCALE` |
| Aire entre secciones | **144 / 96** | `240 / 160` | `public/css/landing.css` (`.section`) (`padding:120px 0`) y su `@media (max-width: 768px)` · `#314` |
| Movimiento | **8 duraciones · 5 curvas** | **7 · 4** | `#196` §16 |
| Punto de corte | **1024**, uno solo | 1080 en el cajón | `#252` · el canvas es explícito: *«el 900 no existe en el sistema»* |

**Lo que NO es conflicto, y sorprende:** el papel **`#F4F4F1`** y la tinta **`#101418`** **ya están
en `public/css/client.css`** (líneas 26 y 30). El `#F4EFE3` que uno espera encontrar son
**comentarios rancios** de `public/css/site.css`. La paleta ya migró — y el canvas lo había
cazado por su cuenta: es su **grieta 05**.

### 3.1 · Y el canvas ha auditado NUESTRO código

`Auditoria Sistema SPA PJP` no es una propuesta: es una auditoría del cajón real, leída de
`resources/js/sidebar/`. **Nueve grietas.** Tres coinciden con lo medido aquí (02 táctil, 03 radios,
05 el comentario del papel). Las dos que faltaban:

- **Grieta 00 · la mayor.** El cuerpo del cajón es **13 px** en unas sesenta reglas y el suelo del
  sistema son **16**; las notas del dinero van a **11** y hay un tamaño de **9**. Subirlo crece el
  texto un 23 % y obliga a revisar el reflujo de **25 pantallas**. Es la única que el cliente nota
  en todas. **Decisión del owner, abierta.**
  ⚠️⚠️ **MEDIDA en `#474`, y es MÁS GRANDE FUERA del cajón que dentro** — el canvas no podía verlo
  porque solo auditó el cajón. Las declaraciones por debajo del suelo de 15 son **281 de 412**, y se
  reparten en **124 del cajón · 125 de la web pública · 32 del post-form y el justificante**. Su
  cifra sí queda confirmada al dígito: `--fs-13` tiene **59** usos.
  ⚠️ Y el reparto salió de cruzar cada selector con **las clases que el cajón emite de verdad**: el
  primer clasificador, por prefijo del nombre, **daba 226/55 y era falso** — lo dijo su control, que
  enseñó `.account__*`, `.bk-*` y `.cal__*` clasificados como públicos siendo del cajón.
- **Grieta 01 · la que más importa.** El botón que avanza la compra se pinta con
  **`var(--zone-1)`** — la paleta de una zona del parque, que llega desde los DATOS. O sea que el
  color del botón de comprar depende de una zona, y si se retiñe entre Kids y Jump el mismo botón
  cambia de color a mitad del embudo. Es justo lo que el mapa del naranja existe para evitar, y lo
  que `#436` empezó a arreglar con `--interactive`.
- **Grieta 04 · era nuestra y ya está resuelta en su lado.** Faltaba el rol de **reembolso** en los
  tokens; el canvas lo añadió en v1.9 (`semantico.tinta.reembolso`) con la regla escrita: *«un
  reembolso no es un color: es un signo y una fecha»* — va en TINTA, no en rojo, porque el rojo es
  avería y el verde es «reserva confirmada».

---

## 4 · Inventario Fase 0 · dato → pantalla

### 4.1 · Las páginas

De las **siete del inventario del canvas**, **seis existen** y **una es nueva**:

| Página | Estado | Ruta / vista |
|---|---|---|
| `/precios` | ✅ existe | `PricingController` · `pages/pricing.blade.php` |
| `/cumpleanos` | ✅ existe | `EventsController` · `pages/events.blade.php` |
| `/normas` | ✅ existe | `PageController@rules` · `pages/rules.blade.php` |
| `/servicios` | ✅ existe | `ServicesController` · `pages/services.blade.php` |
| `/contacto` | ✅ existe | `ContactController` · `pages/contact.blade.php` |
| `/atracciones` | ✅ **construida en la T2d** (`#481`) | `AttractionsController` · `pages/attractions.blade.php` |
| `/bar` | ⬜ **NUEVA** | — · ⚠️ la pide la pareja del bar de la sección 03, que el owner ha dejado FUERA por ahora (`#482`) |

⚠️ **Y hay una que existe y el canvas NO tiene en su inventario: `/entradas`** (la sirve `HomeController`, registrada en `routes/web.php`). Hay que decidir qué pasa con ella — el canvas es explícito en que *«un enlace
que no está en el inventario es relleno»*, así que o entra en el inventario o se retira. **Es del
owner.**

### 4.2 · Los tres huecos de datos, medidos

De los que no se ven hasta que estás montando:

**`park_rules` tiene CUATRO columnas** (`name`, `description`, `position`, `is_active`). El diseño
de `/normas` pide dos cosas que no tienen dónde guardarse: **el porqué de cada norma** (*«una norma
con motivo se cumple y una norma sola se discute en la puerta»*) y **agruparlas por momento**
—antes de venir · en la puerta · dentro—, que es *«el único orden que el visitante puede usar»*.
▶ Son dos columnas nuevas. Es dominio, no diseño.

**`attractions` no tiene `slug`** —ya fichado en `hueco-ilustracion.md`, que lo dejó fuera de su
versión— y por eso no puede tener icono por instalación. ✅ Lo que sí encaja: **no necesita altura
propia**, porque el canvas confirma que el 1,30 es **de la zona** (*«Ninguna altura por atracción»*).

**La edad de la zona es texto libre** (`zones.age_label` y `age_range`, los dos JSON). El canvas ya
lo cazó en `Arquitectura de Contenido` §07: *«hoy son texto libre y dicen 6 años donde tú fijaste 8.
Sin dato estructurado, el selector de zona no puede filtrar solo»*. ⚠️⚠️ **Y no es cosmético: de esa
edad sale el cobro del cumpleaños mixto** (`cumple-mixto.md`), que hoy se resuelve con
`guest_age_family` + `guest_age_min/max` en `ticket_types`. Hay **dos fuentes de edad** en el
sistema y dicen cosas distintas.

### 4.3 · Lo que SÍ está y encaja

- **`zones`** (23 columnas) trae ya `image`, `color`, `color_secondary`, `accent`,
  `show_in_landing`, `max_per_slot`, `max_guests_per_slot`, `opens_at`/`closes_at` e
  `ignores_venue_closure`. Cubre las tarjetas de zona de la sección 01.
- **`ticket_types`** (37 columnas) trae `features`, `badge`, `featured`, `icon`, `min_qty`/`max_qty`,
  `deposit_type`/`deposit_value`, `duration_min` y la familia de edades. Cubre Tarifas y Cumpleaños.
- **`opening_hours` + `seasons` + `special_dates`** cubren «Visítanos» y sus **cuatro estados** de
  «abierto ahora» (el canvas insiste: *«un dato con hora tiene cuatro estados, no tres»* — «hoy no
  abre» y «hoy ya ha cerrado» son hechos distintos).
- **`faqs`** cubre Dudas tal cual (el canvas usa `question`/`answer`/`position`/`is_active`).
- **`landing_services`** trae `specs` y `price_table`, que es donde el canvas propone que viva el
  reparto del reloj de la fiesta.
- **`settings`** trae `address.*`, `contact.*` (con `phone`, `whatsapp`, `email`, `instagram`,
  `tiktok`), `theme.*` (incluido `theme.action` y `theme.fonts`), `landing.*` y `seo.*`.

---

## 5 · Las cinco fases

| | Qué | Qué produce | Bloquea a |
|---|---|---|---|
| **0** | Base: Docker · las seis cifras · este inventario | esta spec | todas |
| **1** | ✅ **El sistema**: tokens (color · tipo · espacio · forma · elevación · movimiento) + los 65 iconos | `site.css` y `client.css` a v1.10, el set con su guarda | 2, 3, 4 |
| **2** | ✅ **El armazón + las 8 secciones**, móvil y escritorio | la portada entera, vestida con BD | 3 |
| **3** | **Las páginas**: 2 nuevas + 5 rehechas con el armazón de `Layout Paginas` | las siete del inventario | — |
| **4** | **El SPA**: las **16** grietas + las **6** paradas del canvas | el cajón | 5 |
| **5** | **Post-form y justificante digital** | lo que hoy es funcional y no está vestido | — |

### 5.1 · Las ocho secciones de la portada

Rótulo · titular · artboard. **Los rótulos NO llevan número** (`[DECIDIDO owner]` del canvas) y los
titulares son frases de **3 a 6 palabras**.

❗❗❗ **EL ORDEN DE ESTA TABLA ES EL ORDEN DE LA PORTADA, y desde `#495` es también el del código.**
Verificado contra **dos fuentes independientes** del canvas el 2026-09-10:

- **`Portada PJP`** (el entregable): sus ocho rótulos salen en este orden de documento, los ocho caen
  **antes del truncamiento** y —con control— **ningún `position: absolute` los recoloca**, así que el
  orden de documento es el orden visual.
- **`Marco Portada PJP`**, que lo lleva **en datos**: `01 Zonas · 03 Qué hay dentro · 05 Antes de
  venir · 07 Visítanos · 08 Dudas`. Las tres que faltan (02, 04, 06) son las que ese artboard excluye
  a propósito: *«Tarifas y Cumpleaños son sección y página, y aquí apuntan a la página»*.

⚠️⚠️ **Hay una TERCERA numeración en el canvas que dice otra cosa y NO cuenta**: `Landing PJP Modos`
(`01 Entradas · 02 Zonas · 03 Cumpleaños…`). `[owner]`: *«de esa maqueta solo sacaremos la sección de
reseñas»*. *Mirarla y creerle es la forma de reordenar mal la portada con una fuente del canvas en la
mano.* Y una cuarta en `Colores de Marca PJP`, que son las partes de una página en un ejercicio de
color y nombra piezas del ARCHIVO.

⚠️ **Reordenar secciones NO ROMPE NADA** —las guardas acotan por `id`, `--hero-air` cuelga de
`.hero + .section` y se muda solo—, así que el orden lo vigila **`HomeSectionOrderTest`** y nada más.

| Rótulo | Titular | Artboard |
|---|---|---|
| Para quién | Cada uno tiene su zona | `Zonas PJP` (4a) · ✅ `#478` |
| Cuánto | Una hora, dos o el día | `Precios PJP` (10a) · ✅ `#479` + `#480` |
| Qué hay dentro | Salta, trepa y déjate caer | `Juegos PJP` (6a) + `Escritorio PJP` (4b) · ✅ `#482` |
| Cumpleaños | El cumple, resuelto | `Cumpleanos PJP` (7b) + `Escritorio PJP` (5a) · ✅ `#483` |
| Antes de venir | Tu registro es este QR | `Antes de Venir PJP` (2a) + `Escritorio PJP` (3a) · ✅ `#485` |
| Reseñas | Lo dicen los que ya han venido | `Resenas PJP` (2a) + `Escritorio PJP` (5b) · ✅ `#490` + `#491` |
| Visítanos | Dónde estamos y cuándo abrimos | `Visitanos PJP` (7b) + `Escritorio PJP` (3b) · ✅ `#487` |
| Dudas | Lo que más nos preguntáis | `Dudas PJP` (1a) + `Escritorio PJP` (5c) · ✅ `#488` |

Marco: `Marco Portada PJP` (móvil) · `Escritorio PJP` (escritorio, turnos 1–6).

### 5.3 · Estado de la Fase 1

Cada tanda se cierra con suite verde antes de la siguiente.

| | Tanda | Dónde | Estado |
|---|---|---|---|
| T1a | Objetivo táctil 44 → **48** | producto | ✅ `#470` |
| T1b | Radios colapsados a **0 · 10 · 16 · 999** | paquete | ✅ `#470` |
| T1c | Aire entre secciones **144 / 96** | token nuevo + paquete | ✅ `#471` |
| T1d | Ancho de columna **1120** | hueco nuevo + paquete | ✅ `#472` |
| T1e | Movimiento | — | ✅ `#473` · **divergencia declarada**, sin código |
| T1f | Punto de corte **1024** | producto | ⏸️ `#473` · **aparcada hasta la Fase 2** |
| T1g | La escala tipográfica del canvas | producto | ✅ `#474` · declarada, **sin estrenar** |
| T1h | Los **65 iconos** | producto | ✅ `#475` · **63 de 65**; los 2 restantes, al kit |

✅ **T1h, el set de iconos** (`#475`). El canvas publica **65** y el producto tenía **50 alineados**
(los trajo `#257`). El cruce se hizo **por el código que cada componente cita en su docblock**, no
por el nombre del fichero: 50 alineados · 2 propios declarados · 2 de línea heredada que el artboard
sigue sin dibujar · 7 que no son del set. **Ninguno nuestro sobra.**

▶ **Los 19 que faltaban se parten por el filtro de §2**: **6 son de PlayJump** (los cinco «Del
parque» de `#257` más `calcetines`) y **13 son del producto**, que entran. ⚠️ **No son los mismos 19
de la ficha de `#257`** aunque el número coincida: los suyos eran 5 «Del parque» + **14 de zona** en
rejilla 64, que ni siquiera están en este set. *Dos cifras iguales no son la misma cifra.*

❗❗ **Y eso CADUCA la ficha de `#257`**, que decía que *«no existe mecanismo para que una instalación
sustituya el DIBUJO de un icono»*: **ya existe** desde `#286`/`#287` (`IllustrationKit`, `<use>`
externo sobre `client-kit.svg`, gramática cerrada). `[DECIDIDO owner]`: los seis salen por ahí, cada
uno el día que tenga pantalla.

▶ **Cinco de los trece estrenan en el acto** en `ProductIcon::CHOICES` —`cake`, `ice-bucket`,
`snacks`, `drink`, `clock-plus`—, que son complementos que el catálogo vende de verdad. ⚠️ `cake` no
retira a `ic-b1`, que también es una tarta: aquélla es la ilustración del cliente de origen y
retirarla degradaría en silencio todo producto que la tenga guardada (`#258`).

❗❗❗ **Defecto preexistente cazado al montarlo**: cuatro de las once opciones del selector de icono
del catálogo enseñaban su **clave de traducción en crudo** al operador (`…icon_option.ticket`, y
`gift`, `party`, `school-trip`). Vino con `#258` y **no lo miraba ninguna guarda** — las dos que hay
comprueban que el icono existe y que el cajón lo dibuja, y pasan en verde con eso puesto. *Que una
opción se pueda elegir y se pueda pintar no es que se pueda leer.*

⚠️ **El extractor de `#257` no se versionó**; ahora es `scripts/extraer-iconos.py`, **validado con
control** (se extrajo `ui/menu`, ya en el repo, y la geometría salió idéntica). Sus dos defectos
propios están escritos dentro, y el primero es la trampa de `#298`: `str.format` colapsa `{{` en `{`
y dejaba el comentario Blade **sin abrir**, o sea el docblock renderizado como texto visible.

⚠️⚠️ **Los ocho de sección quedan sin guarda de consumidor porque NO es medible**: un `grep` de
`<x-icons.NOMBRE>` da 51 «huérfanos» de 74 y es falso — los marcadores se sirven con
`<x-dynamic-component :component="'icons.'.$key">`, por clave. *Una guarda que no distingue un icono
muerto de uno servido por clave no es una red: es ruido con autoridad.*

✅ **T1g, la escala tipográfica** (`#474`). Entran los **diez niveles con nombre** —Display XL/L ·
Título · Subtítulo · Entradilla · Cuerpo · Cuerpo S · Botón · Etiqueta · Eslogan— en el `:root` de
`landing.css`, cada uno como un `clamp` entre su talla móvil y la de escritorio. **No sustituyen a
`--fs-9…22`: conviven** (`[DECIDIDO owner]`), y los `--fs-N` mueren superficie a superficie según se
viste cada una.

❗❗❗ **NACEN SIN CONSUMIDOR, y es lo que la medición obligó a decidir.** Se buscó una sustitución de
reflujo cero y **no existe ninguna**: ni una regla del producto coincide con su nivel en talla **y**
en papel a la vez. Las cuatro reglas de mono a 12 no son «Etiqueta» (un glifo de 6 px, un precio, un
número en círculo); los `--fs-15` son campos y botones; el eslogan está a 18 y 22 contra 24/30.
▶ Se distingue del `barra: 1400` que `#473` rechazó: **aquél no tendrá consumidor nunca; éstos lo
tienen en la Fase 2**. La excepción está nominada en `SidebarTokenBudgetTest::SIN_ESTRENAR` con un
trinquete que **solo la deja encoger** — estrenar un nivel sin sacarlo de la lista pone rojo.

⚠️⚠️ **Un `clamp` tiene TRES partes y cada una manda en un tramo de ancho distinto**, así que
`TypeScaleTest` mide en **cuatro** anchos (320 · 390 · 707 · 1920) y no en uno. Nació con dos huecos
simétricos que **encontró la mutación, no una relectura**: torcer el tramo interpolado salía verde
midiendo en los extremos (ahí capa el `clamp`), y cambiar los extremos no movía nada entre 390 y
1024 (ahí manda el tramo). *Una medida en un punto no vigila una función.*

▶ **Lo que la Fase 2 tiene que mover, ya medido**: el titular de sección va hoy a
`clamp(48px, 7vw, **108px**)` y su nivel es **Display L, 52** — otro diseño, no un ajuste · el botón
está a **14 con peso 600** contra 16 y 800 · los campos a 15 contra 16 · **35 reglas usan la mono a
10, 11 o 14** contra el 12 de Etiqueta · y **el cuerpo de texto no lo declara nadie**, hereda los 16
del navegador, contra el 16/17 del sistema.

✅ **T1e, resuelta midiendo** (`#473`): lo que parecía «7 duraciones contra 8 y 4 curvas contra 5»
eran **tres diferencias**, no dos escalas distintas — **siete de las ocho duraciones ya son
idénticas** (120 · 180 · 240 · 320 · 420 · 620 · 900) y dos de las cuatro curvas también, al dígito.
`[DECIDIDO owner]`: **la quinta curva NO entra** —`#262` sigue en pie— y con ella se queda nuestro
sobreimpulso **1.56** frente al **1.81** de su `lona`. La octava duración (`barra 1400`) tampoco:
**no tenemos ninguna barra indeterminada**, y un token sin consumidor es lo que `#287` prohíbe.

⏸️ **T1f, aparcada** (`#473`): al medirla dejó de ser un cambio de número. El canvas declara **tres**
puntos y el producto tiene **catorce**; los siete `1080` **no son un límite del sistema** sino siete
reflujos de componentes que comparten número, y lo único que el canvas nombra —la barra flotante—
**aparece hoy a ≤720**, así que moverla a 1024 cambia lo que ve una tableta. Toca `#232` y `#252`, y
se decide en la Fase 2 con el armazón delante.

---

## 5.bis · ❗ Pasos de despliegue acumulados

**`public/css/client.css` está gitignorado** (`DECISIONES #1`), así que **los valores de PlayJump no
viajan en ningún commit**. Esta lista es lo que hay que aplicar a mano en el `client.css` de
producción, y crece con cada tanda que toque el paquete. Es el mismo mecanismo de `#434` y `#436`.

| Tanda | Qué aplicar |
|---|---|
| T1b (`#470`) | `--r-xs` y `--r-sm`: `6px` → **`10px`** · `--r-lg`: `24px` → **`16px`** |
| T1c (`#471`) | añadir `--sec-air: 144px` y `--sec-air-mobile: 96px` — **los dos o ninguno**, lo vigila `RhythmScaleTest` |
| T1d (`#472`) | añadir `--col-max: 1120px` |
| T2b (`#478`) | **DATO, desde el panel** — Zonas: poner la **altura** de cada zona (Kids «máxima 130», Jump «mínima 130») y **quitar la altura del texto libre** de su edad, o saldrá dos veces («+8 años · +1,30 m desde 1,30 m», medido). ⚠️ Y decidir dos cosas suyas: el rótulo de la tarifa especial es «Viernes, findes y festivos» y en el sello queda largo (el mockup escribe «finde»), y el **orden** de las tarjetas lo manda `zones.position` —hoy sale Jump primero y el canvas ordena Kids · Jump—. |
| T2c (`#480`) | añadir **`--marker: #F5C400`** y **`--on-marker-brand: #101418`** al paquete (⚠️ **`--on-marker-brand` y no `--on-marker`** desde `#523`: con éste la superficie de tinta pisa la tinta del paquete y el sello del precio sale con texto claro sobre amarillo, 1,49 : 1). Sin ellos el ahorro se queda en texto en negrita —conducta correcta, pero se pierde el resalte—. ⚠️ Y **DATO, desde el panel**: elegir el **icono** de cada complemento (`cake`, `ice-bucket`, `snacks`, `drink`, `clock-plus`, `socks`) — los catorce estaban en `NULL`, o sea todos con la entrada genérica; el mecanismo existe desde `#475` y aquí solo faltaba usarlo. |
| T2e (`#483`) | **`--shadow-float-hover: 2px 2px 0 var(--paper-fg)`** y **`--shadow-float-press: 0 0 0 var(--paper-fg)`** en el paquete. ❗❗ **No es una mejora, es un ARREGLO**: `#478` escribió el hover de la pegatina con esos dos tokens y el paquete no los declaraba, así que la tarjeta reposaba con la sombra DURA del cliente y al pasar el ratón saltaba a la DIFUSA del producto (medido). Sin ellos, la sección 04 **y la 01** siguen con ese defecto. Lo vigila `ClientThemePackageTest`: si el paquete declara `--shadow-float`, tiene que declarar los tres. ⚠️ Y **DATO, desde el panel**: revisar los `features` de los dos packs — la sección enseña los **tres primeros** y hoy dos de los cinco repiten lo que la tarjeta ya dice (la duración y la edad). |
| T2d (`#482`) | **DATO, desde el panel** — el ORDEN de las atracciones decide **cuáles cinco** salen en la portada: la 1.ª de la primera zona va grande, la 1.ª y la 2.ª de la segunda se leen, y la 2.ª y 3.ª de la primera se velan. Con el orden de hoy sale «Saltos libres» grande, «Piscina de bolas» y «Toboganes» con nombre. **Cero código**: se cambia reordenando. ⚠️ Y arrastra la decisión pendiente de T2b/T2c —el orden de las ZONAS—, que aquí decide **qué zona lidera el mosaico**. |
| T2g (`#487`) | añadir **`--ok-ink: #447921`** y **`--attn-ink: #8A6E00`** al paquete (las variantes 800 de su propia paleta). ❗ **Sin `--ok-ink` el estado «Abierto ahora» se pinta con `--ok`, que en este paquete da 2,4 sobre blanco** — lo vigila `ClientThemePackageTest`, así que el gate lo caza. Sin `--attn-ink` el «Abre hoy» pierde el color y se queda en tinta: se ve, no se rompe. ⚠️ Y **DATO, desde el panel**: el horario en conflicto —el panel dice L–V 16:30 · S–D 11:00 y el canvas L–J 16:30 · V–D 11:30— y las **fechas especiales**, que hoy son cero: sin ellas no hay ni aviso ni pliegue, que es la conducta correcta. |
| T2f (`#485`) | **Ninguno de CSS**: la sección se viste entera con roles ya declarados (`--attn`, `--ok`/`--on-ok`, `--interactive`, `--line`, `--paper-fg`, `--paper-bg-card`, los tres radios). ⚠️ Y **DATO, desde el panel**: la línea «¿viene un niño que no es de tu familia?» **solo sale si algún producto ofrece el justificante** (`ticket_types.guardian_authorization` distinto de `none`). Medido en local: **0 de N**, así que hoy no se pinta —y eso es la conducta correcta, no un defecto. Si el parque quiere ofrecerlo, se marca en el producto. |
| T2i·a (`#490`) | **Ninguno de CSS**: la sección se viste con roles ya declarados (`--bg-card`, `--line`, `--bg-soft`, `--attn-ink`, `--interactive`, `--fg-mute`). ▶ **DATO, desde el panel**: escribir **tres opiniones propias** en «Ajustes → Opiniones propias». ⚠️ **Sin ninguna, la sección no se pinta** —conducta correcta— y hoy es **lo único** que la sección puede enseñar: Google tiene una sola reseña, por debajo del umbral de 10. ⚠️ Y **en la consola de Google**: poner el **tope de 50 peticiones/día**, añadir la **IP del servidor** a la restricción de la clave (la que hay es la conexión del owner) y **rotar la clave**, que se pegó en un chat. |
| T2i·b (`#491`) | **`.env` de producción**: `GOOGLE_PLACES_API_KEY=` con la clave de Places API (New). ▶ **DATO, desde el panel**: el ajuste `social.google_place_id` (`app:set-setting social.google_place_id <ChIJ…> --force`). ⚠️ **Y en la consola de Google, lo que el owner aplazó a sabiendas**: **rotar la clave** (se pegó en un chat), poner el **tope de peticiones/día** y **añadir la IP del servidor** a la restricción — sin la IP, en producción todas las llamadas fallan y la sección cae al respaldo propio sin avisar. ❗ **El scheduler no corre en staging** (`#115`): allí `social-proof:refresh` se dispara a mano. |
| T2h (`#488`) | **Ninguno de CSS**: la sección se viste con roles ya declarados (`--bg-card`, `--line`, `--bg-soft`, `--interactive`, `--r-lg`, `--r-pill`). ▶ **DATO, desde el panel** — las cinco dudas publicadas: **retirar la de la EDAD** (contradice a la sección 01, que dice 4–7 y +8 desde `zones`) · **reescribir la del APARCAMIENTO** con «en la calle, delante, y gratis» (`[DECIDIDO owner]`; ⚠️ es el único sitio de la web que lo publica) · **reescribir «¿Hace falta reservar?»**, que hoy dice «no hace falta» contra toda la página · y **quitar el «automáticamente»** de la de cancelar, que promete un canal que `#244` no da. ⚠️ El seeder ya trae las cinco; en producción **no se siembra**, así que se editan desde el panel. ⚠️ Y **quedan dos decisiones del owner**: si grupos lleva al correo o a `/servicios`, y qué otras dudas oyen en el mostrador. |
| T2c (`#479`) | añadir **`--money`** al paquete: `#627411` en `:root` y en `[data-surface="paper"]`, `#A3C21C` en `[data-surface="ink"]`. Es el rol de CIFRA; **sin él los precios salen en tinta**, que es la conducta anterior — no se rompe nada, solo se pierde el color. ⚠️ Y **DATO, desde el panel**: el **orden** de las pestañas lo manda `zones.position` (el canvas ordena Kids · Jump y aquí sale Jump primero, la misma decisión pendiente de T2b), y **`ticket_types.featured` está a cero en las cinco entradas** — sin ninguna destacada, el carril abre por la primera y no hay tarjeta ancha ni chip. Es una elección suya, no un defecto. |
| T3a·2 (`#522`) | añadir **`--ink-fg-body: #C9CDD1`** al paquete (Papel 200, el gris de CUERPO sobre tinta del canvas). Sin él el cuerpo del pie sale con la mezcla de reserva del producto (`--fg` de tinta al 82 %): se lee, pero no es su valor. ⚠️ Y **DATO, desde el panel**: la **ciudad** (Ajustes → Contacto) — sin ella el colofón dice solo «Nombre · © año», que es la conducta correcta, no un defecto. |
| `#523` | **Ninguno de CSS nuevo**, y dos comprobaciones: (1) si el paquete de producción ya lleva el marcador, **renombrar** `--on-marker` → `--on-marker-brand` (fila T2c); (2) comprobar que `theme.brand` de producción sigue en `#1AA9DE` — en local se había quedado un valor de TEST (`#0A0B0C`) escrito a mano por otra sesión. ⚠️ Y **en la consola de Google**: la restricción de IP de la clave de Places rechaza hoy (403) la IP de la máquina de desarrollo. |

⚠️ Y arrastra las **cinco líneas** que ya venían pendientes de `auditoria-diseno.md` (`#434` dos,
`#436` tres): comprobar que están puestas antes de dar por buena una verificación visual en
producción.

---

## 5.ter · ✅ Verificación de navegador — HECHA (`#476`, 2026-09-09)

**La sonda vive ahora EN EL REPO**: `scripts/sonda-geometria.mjs`, con su receta de instalación y sus
cuatro trampas dentro. La anterior estaba fuera y se perdió, que es el mismo error del extractor de
iconos de `#257`.

▶ **Las cuatro cifras de la Fase 1, confirmadas renderizadas**: `--tap-min` **48** · `--col-max`
**1120** (con `.wrap` midiendo 1120 en escritorio y 358 en móvil) · `--sec-air` **144** con
`.section` a 72/72 · `--sec-air-mobile` **96** con 48/48. **Desborde horizontal 0** en las 24
mediciones (12 vistas × 2 anchos).

❗❗❗ **Y encontró lo que la suite no podía ver**: `.btn`, la familia única de botones, **no
declaraba mínimo táctil** — su alto salía de padding más línea y daba exactamente **44**, el
objetivo VIEJO, así que cumplía por casualidad aritmética. Al subir el token a 48 se quedaron cortos
todos los botones de la web **sin que nada fallara**. Cerrado en `#476`: los controles de PÁGINA
bajo 48 pasan de 11/13/8/2 por vista a **0**, salvo el enlace en línea que WCAG exime.

⚠️ **Lo que queda y por qué**: el **logotipo del armazón** (125,3×46) se deja para la Fase 2 —su alto
entra en el cálculo del racimo de `#252` y la Fase 2 rehace el armazón entero— y los **54 cortos del
CAJÓN** son de la Fase 4 (la ficha que `#407` abrió midiendo sus campos a 42).

⚠️ **La sonda solo llega a lo PÚBLICO**: deja fuera el paso de datos del cajón, el post-form y el
justificante, que exigen sesión o enlace firmado. Es el agujero de alcance que `#407` ya fichó.

### 5.ter.1 · 📜 Lo que decía antes de hacerse

`TouchTargetTest` **no mide píxeles** —lo dice su propio docblock— así que subir el táctil a 48
puede solapar dos áreas sin que la suite se entere. Lo mismo valdrá para la columna y el aire.

▶ **Una sola pasada al final de la Fase 1**, con toda la geometría dentro, en vez de una por tanda.
⚠️ La sonda vivía en `/root/e2e/tap44.mjs`, **fuera del repo y ya no existe**, y hay que
reconstruirla leyendo antes `VERIFICACION-E2E-CAJON.md` §5.duovicies: **salió mal dos veces con
cifras plausibles** (áreas negativas por recortar en coordenadas de viewport; altos de 58 donde son
44 por no aplicar el `transform` del pseudo-elemento). El contenedor tiene node v24 pero **ningún
navegador instalado**.

---

### 5.4 · Estado de la Fase 2

| | Tanda | Estado |
|---|---|---|
| T2a | **El armazón**: menú en dos grupos · eslogan en el cierre · el alto del par confirmado | ✅ `#477` |
| T2b | **01 · Para quién**: dos tarjetas de zona · la altura pasa a DATO · 03 se separa | ✅ `#478` |
| T2c | **02 · Cuánto**: carril con foco · el nombre manda · la tarifa especial, entera · chapa de zona, ahorro y complementos fuera | ✅ `#479` + `#480` |
| T2d | **03 · Qué hay dentro**: el mosaico de cinco, y con él la página `/atracciones` | ✅ `#481` + `#482` |
| T2e | **04 · Cumpleaños**: los dos packs se comparan · el reloj no reparte · el bloque de complementos pasa a molde compartido | ✅ `#483` + `#484` |
| T2f | **05 · Antes de venir**: el registro ES el QR · la sección de NORMAS se retira · el código es de ejemplo | ✅ `#485` + `#486` |
| T2g | **07 · Visítanos**: cuatro estados · la entradilla se deriva · sin teléfono, sin aparcamiento y sin la promesa de festivos | ✅ `#487` |
| T2h | **08 · Dudas**: el acordeón del sistema · todas cerradas · cero salida · con el panel vacío la sección desaparece | ✅ `#488` |
| T2i·a | **06 · Reseñas**, mitad `a`: las opiniones PROPIAS · el contrato `SocialProof` · el recurso del panel | ✅ `#490` |
| T2i·b | **06 · Reseñas**, mitad `b`: Google (Places, caché corta, comando programado, atribución) | ✅ `#491` |
| T2i·c | **06 · Reseñas**: la ATRIBUCIÓN de Google — el logotipo oficial, el perfil del autor y el aviso de traducción | ✅ `#494` |

✅ **T2i·c · La atribución de Google** (`#494`). El owner pidió *«más veracidad con los logos de
Google, como el widget oficial»* y **resultó ser un requisito INCUMPLIDO**: leída la política de
Places el 2026-09-10, faltaban el logotipo obligatorio, el enlace al perfil del autor, el aviso de
traducción y la distinción visual entre contenido de Google y contenido propio.

❗❗❗ **EL LOGOTIPO OBLIGATORIO NO ES LA «G» QUE YA TENÍAMOS.** `google.svg` es la marca de Google
Sign-In (`#345`); la atribución de Places pide el logotipo de **Google Maps**, otro asset con otras
reglas (alto 16–19 px, espacio libre 10/10/10/5, prohibido modificarlo). Entran **dos variantes** del
paquete oficial y **el color se elige cambiando de FICHERO, jamás recoloreando**.

❗❗❗ **Y NO PUEDE IR EN LA CABECERA DE LA SECCIÓN**: la chapa y las opiniones no vienen de la misma
fuente y **el caso frecuente es el CRUCE** —chapa de Google sobre opiniones propias—. Arriba marcaría
como suyas unas opiniones que escribió el parque. `[DECIDIDO owner]`: **chapa + cabecera de la
reseña**, cada trozo con su atribución en su contenedor.

❗❗ **«Verificado por Google» es lo único de las tres ideas del owner que NO se puede hacer**: su
documentación dice *«Reviews aren't verified by Google»*. Entra en su lugar **esa misma frase**, que
la política pide publicar.

⚠️⚠️ **Un defecto que ninguna medida de geometría veía**: la cabecera que se les puso a los assets
citaba tokens CSS con sus dos guiones, y **XML lo prohíbe dentro de un comentario**. HTTP 200, marcado
correcto, **caja de 98×18** —los atributos del `<img>` reservan el hueco— y el logotipo **invisible**.
Lo delató `naturalWidth`. *Que el fichero llegue y mida bien no es que se pinte.*

⚠️ **En inglés y en francés Google traduce las DOS reseñas del parque** y no se decía. La señal es
que los códigos de idioma **difieran**: `originalText` viene siempre, traducida o no.

▶ **Tres retoques del owner con la sección delante**: **fuera las flechas** del carril —se recorre
con los puntos, y ⚠️⚠️ **no costó accesibilidad porque cada punto ya era un `<button>` con su
nombre**; la guarda vigila las dos mitades—, **«Ver en Google» anclado a la derecha** (⚠️ con
`margin-left: auto` y **no** `space-between`: sin «Ver más» la fila tiene un solo hijo y volvería a la
izquierda) y **los dos logotipos se quedan** — pidió quitar «el de debajo del titular», al aclarar
dijo «debajo de las estrellas sí, déjalo», se le enseñaron los dos con su posición y respondió
«ninguno». ▶ *Preguntar costó un minuto; quitar el equivocado habría costado la vuelta entera.*

▶ Suite **4.632** · **29/29 mutaciones** · verificado en navegador (390 y 1440, ES y EN) y **con
JavaScript desactivado**.

✅ **T2i·b · Google** (`#491`). La chapa del 4,8 con las estrellas recortadas **caja a caja**, las
reseñas con su atribución, la caché corta y el comando `social-proof:refresh` cada hora.

❗❗❗ **LA PORTADA NO LLAMA A GOOGLE, y eso es una clase entera**: `GoogleSocialProof` lee de la caché
y **nunca llama**; quien llama es el comando. Lo vigila un caso que **prohíbe el mecanismo** —el
cliente HTTP falseado para explotar si alguien lo llama— **con su guarda-de-la-guarda**.

❗❗❗ **LA CIFRA NO NECESITA CONSENTIMIENTO Y LAS RESEÑAS SÍ**, y eso resuelve la ambigüedad que la
spec tenía escrita sin argumentar: la cifra la trae **nuestro servidor** —el visitante no habla con
Google—, no lleva autor ni foto y no es dato personal; la reseña obliga a su avatar, que **sí** es
una petición del visitante. ▶ De ahí sale lo que el owner quería.

⚠️⚠️ **Un defecto que solo vio la CAPTURA**: la entradilla estaba atada a la CHAPA, y la chapa y las
opiniones **no vienen de la misma fuente** — el caso más frecuente es el cruce. La sección decía «no
las elegimos nosotros» **sobre una opinión propia**. *Texto correcto, sitio correcto, afirmación
falsa: ninguna aserción de marcado lo veía.*

✅ **T2i·a · «Reseñas», las opiniones propias** (`#490`). Cabecera común · **una opinión a la vez**
con flechas y puntos · **sin la chapa del 4,8**, porque esa cifra solo existe si viene de Google.

❗❗❗ **LA VERIFICACIÓN CONTRA GOOGLE ESTÁ HECHA (§6·5) Y CAMBIA LA SECCIÓN.** HTTP 200 con la clave
del owner: es el parque —«Play Jump Park · Ctra. de Granada, 30813 Lorca»— y el campo `reviews` trae
la atribución completa, con el avatar en `lh3.googleusercontent.com`, que es donde §3.3 lo midió. ▶
**Pero el parque tiene UNA reseña**: la chapa diría «5,0 · 1 reseña», el carril tendría un elemento y
una segunda reseña de 1 estrella publicaría un 3,0 al día siguiente. `[DECIDIDO owner]`: **umbral de
10**.

❗❗❗ **«QUE LA CHAPA SE VEA SIEMPRE» NO ES IMPLEMENTABLE, Y NO POR DISEÑO**: R2 prohíbe almacenar la
valoración más allá de una caché corta, así que **no se puede congelar el 4,8** —y además un número
congelado deja de ser verdad—. ▶ Lo que el owner quería se consigue por la vía buena: **refrescar
cada hora** deja la chapa puesta prácticamente siempre y sale gratis (~720 llamadas/mes).

❗❗ **EL ARTBOARD TIENE LA OPINIÓN APAGADA EN MÓVIL** (`conCarrusel`, sexta vez que una pieza está
tras un interruptor), y eso dejaba la sección **sin nada que enseñar** sin Google — lo contrario de
§3.3. `[DECIDIDO owner]`: **sin chapa, el carril se enciende en móvil**; el recorte apagó la opinión
*porque la chapa ya cargaba la sección*, y sin chapa ese motivo desaparece.

⚠️ **La vista lee el CONTRATO, no el modelo**: el día que entre Google no se toca el marcado, se
sustituye el binding por el decorador. ⚠️ `published_at` es una FECHA y la frase se deriva. ⚠️ El
texto **no se recorta**: una opinión propia no tiene «la entera» adonde mandar. ⚠️ **No se siembran
opiniones de ejemplo**: el seeder alimenta el arranque en frío de producción.

▶ **Medido**: **509 px** en móvil y **399** en escritorio; portada **12,65** y **11,46** pantallas,
desborde 0. Comparador **33 y 6 idénticas, 0 sin explicar**.

✅ **T2h · «Dudas»** (`#488`). Cabecera común · **una tarjeta blanca** con una fila por duda ·
**todas cerradas** al cargar · papel de arriba abajo · y **cero salida** al final.

❗❗❗ **ENTRE LAS DOS OPCIONES DEL ARTBOARD MANDA 1a, y por tres razones medidas**: 1b cuesta **78 px
más teniendo una duda menos** dentro del acordeón, pone la reserva como lo primero que se lee de la
sección —que no es la duda más frecuente, solo la más contradictoria— y **no tiene escritorio
dibujado**, así que la superficie dependería del ancho de la ventana (la lección de `#485`).

❗❗ **«Todas cerradas» NO es una divergencia con el sistema, aunque el artboard la llame así**: la
tabla de reglas del componente 06 ya dice *«la primera abierta al cargar en una FAQ de PÁGINA, todas
cerradas en la PORTADA»*. ▶ *Cuando dos piezas del canvas parecen contradecirse, se abre la que
manda antes de declarar una divergencia.*

❗❗❗ **CON EL PANEL VACÍO LA SECCIÓN ENTERA NO SE PINTA** —ni rótulo, ni titular, ni caja, **ni el
`FAQPage`**—, que es regla dura del sistema. ⚠️⚠️ **Y alcanza a más secciones**: medido, `#events` ya
la cumple pero **01, 02 y 03 no** —en 03 el mosaico está guardado y la cabecera no, así que con cero
atracciones se pintan rótulo, titular, una entradilla que dice «0» y 144 px de aire debajo de nada—.
Ficha en `DEUDA.md`: toca tres secciones cerradas y sus guardas.

❗❗❗ **LAS DUDAS SON DATO, Y AHÍ ESTABAN LOS HALLAZGOS** (`[DECIDIDO owner]` las tres):
**se cae la edad** —decía «Kids de 1 a 12 · Jump desde 6» contra el «4 — 7» y «+8» que la sección 01
publica desde `zones`: **la portada se contradecía a sí misma**—; **el aparcamiento SE QUEDA**, con el
dato de la calle, porque ⚠️⚠️ **el canvas lo quitaba «porque lo contesta 07» y aquí eso es falso**
(`#487` decidió no escribirlo, y medido hay **cero apariciones en todo el repo**: esta duda es el
único sitio que lo publica); y **el clima NO sube a 03**, cuyo artboard está cerrado sin él.
⚠️⚠️ **Y una respuesta prometía algo que el producto no hace**: «te devolvemos la diferencia
**automáticamente**», con `#244` en pie —el saldo se liquida en el parque—. El canvas ya retiraba el
adverbio; se adopta sabiendo por qué. ⚠️ `seedFaqs()` **no podaba y su docblock decía que sí**.

⚠️ **El signo son DOS iconos del set y ninguno gira** (un aspa significa cerrar, no plegar), y con el
pulsable en **64 px por el ancho entero** `data-tap` se retira: `faq__q` sale del censo de
`TouchTargetTest` **porque desapareció su motivo**, no su sujeto.

⚠️⚠️ **La rejilla de escritorio es de DOCE pistas**: `span 4` da **352** y `span 8` da **736**, al
dígito lo que el artboard midió. Con `1fr 2fr` salen 362,67 y 725,33 — se parece y no es su número.

⚠️ **Una guarda se puso roja con el producto sano**: `InteractionColourIsNotAZoneTest` buscaba
`.faq__item.open .faq__q` como clave exacta y los dos estados pasaron a compartir regla. Re-apuntada
**por parte de selector**, que es más fuerte, no más débil (`#295`).

⚠️⚠️ **Y una trampa ya escrita, pagada otra vez**: la captura de ELEMENTO enseñaba un «+» huérfano
que **no existe** —`elementFromPoint` devuelve la sección— porque cose los `fixed`, y el cajón cerrado
lleva su botón de cantidad. Es la de `#303`: **se mide con captura de VENTANA**.

▶ **Medido**: la sección baja de **735 a 623 px** en móvil y de **715 a 490** en escritorio; la
portada, de **12,18 a 12,04** y de **11,26 a 11,01** pantallas. Comparador **31 y 13 idénticas, 0 sin
explicar**; las dos declaradas son el ritmo de cabecera (28, que es el de las ocho) y el color de
Línea, que va por el rol `--line`. Desborde **0** y ningún control nuevo bajo 48.

⚠️ **Dos cosas del owner siguen pendientes y son DATO**: si la duda de grupos lleva **al correo o a
`/servicios`**, y **qué otras dudas oyen en el mostrador**.

✅ **T2g · «Visítanos»** (`#487`). Cabecera común · **la única tarjeta de la sección** con el estado
en vivo y la tabla · el mapa tras el bloqueo previo · y la dirección **siempre fuera del marco**.

❗❗❗ **CUATRO ESTADOS DONDE HABÍA DOS.** Regla dura del sistema: *«hoy no abre» y «hoy ya ha cerrado»
son hechos distintos*. Medido: `HeroStatus` solo distinguía dos, así que un jueves ya cerrado y un
lunes de cierre **decían lo mismo con la tabla de horarios justo debajo**. Entran `face`, `title` y
`line` **sin tocar `status`**, que lo leen el chip del hero y el del menú — piezas del armazón.

❗❗ **El día se resalta SOLO mientras su horario está vigente**, y son DOS condiciones: `is_today`
—que ya se apagaba con una temporada o una fecha especial (`#307`)— **y** que el estado sea `open` o
`later`.

❗❗❗ **TRES COSAS DEL ARTBOARD NO SE ESCRIBEN, `[DECIDIDO owner]`**: el **aparcamiento** (el dato no
tiene campo en el panel y hay dos versiones en conflicto; `#297` ya lo había decidido una vez), **«los
festivos, como el finde»** (una promesa que el producto no puede saber) y **el teléfono con «Cómo
llegar»** (el canvas cierra la sección con «cero enlaces y cero botones»). ▶ Esto **revierte parte de
`#307`**: aquella tanda rompía el molde editorial heredado, ésta adopta el artboard.

❗❗ **Y la ENTRADILLA se deriva por la misma regla.** El canvas escribe «Abrimos todos los días…»,
cierto aquí y **falso en cualquier instalación que cierre un día**: `ScheduleDisplay::weeklyLede()`
dice cuántos horarios hay y cuáles. ⚠️ Cuenta los grupos **abiertos**: tres filas con un sábado
cerrado son **dos** horarios.

❗❗ **Nacen `--ok-ink` y `--attn-ink`**, y no son decoración: el estado es texto verde sobre tarjeta
blanca y el verde del paquete da **2,4**. ⚠️ **Se declaran, no se derivan** (`#434`), y sus defectos
caen del lado seguro — `--ok-ink` en `--ok`, `--attn-ink` en `--fg`, que pierde el color y nunca la
lectura. Guarda en `ClientThemePackageTest`, del molde de `#484`.

⚠️⚠️ **DOS DEFECTOS QUE SOLO VIO LA CAPTURA**: `aspect-ratio` + `overflow: hidden` en el CONTENEDOR
recortaba el bloqueo previo del mapa y se comía **el enlace a la política de cookies** —la proporción
es del iframe—; y la dirección llevaba `visit__addr`, la clase de `/contacto`, así que se vestía con
otra página **y dejaba muertas las reglas de ésta**.

⚠️ **Y la trampa 3 del comparador, con control**: `getComputedStyle` **trunca `border-width` a un
entero** —`1.5px` sale `1px`—, y da igual el `deviceScaleFactor`.

▶ **Medido**: **895 px** en móvil (contra 756) y **746** en escritorio (contra 617). Portada **12,18**
y **11,26 pantallas**, desborde 0. Comparador: **21 y 13 idénticas, 0 sin explicar**.

⚠️ **Un dato en conflicto que sigue siendo del owner**: el panel dice **L–V 16:30 · S–D 11:00** y el
canvas **L–J 16:30 · V–D 11:30**. Es DATO y el código agrupa bien: se cambia desde el panel.

✅ **T2f · «Antes de venir»** (`#485`). Cabecera común · un **bloque de tinta** con el código dentro
de un móvil y, al lado, lo que ese código lleva · la excepción de los calcetines en papel · y dos
salidas sin relleno de acción.

❗❗❗ **NO ES UNA SECCIÓN NUEVA: SUSTITUYE A LA DE NORMAS** (`#309`), que decía las dos mismas cosas
—registrarse y traer calcetines— más un asomo de cuatro normas. Y con eso **cierra la ficha de
`DEUDA.md` que `#480` abrió**: `/normas` vuelve a tener entrada desde la portada.

▶ **Cuatro decisiones del owner** (preguntadas antes de escribir una línea, protocolo de `#469`):
el código es **de ejemplo y no lleva a ningún sitio** · las **cuatro normas se van** y queda el
enlace · la sección va **donde la pone el canvas** —tras las de producto y antes de Visítanos y
Dudas, o sea un puesto por delante de donde vivía la de normas— · y los nombres son **los del
PRODUCTO**: «Mi QR» y «Crear mi cuenta», no «Mi Play Jump QR» / «Crear Mi Play Jump», que son marca
del cliente (`DECISIONES #1`).

❗❗❗ **POR CUARTA VEZ, EL ACTA DESCRIBE UNA PIEZA QUE EL ARTBOARD TIENE APAGADA.** Aquí es la chapa
de «lo que ve el empleado»: vive tras `conChapaEmpleado`, **apagado desde el recorte del 7 sep** (la
sección pasa de 1.180 a 726 px por su propia medición). En **escritorio vuelve**, y el motivo es
aritmético y suyo: ahí va en la columna de al lado y no cuesta alto.

❗❗❗ **LA SUPERFICIE NO PUEDE DEPENDER DEL ANCHO DE LA VENTANA, y eso decidió la composición.**
`[data-surface]` no solo cambia tokens: **PINTA** (`background: var(--bg)`, la lección de `#484`). Los
dos artboards discrepan —el móvil (7 sep) pone la sección en papel y el de escritorio (8 sep) en un
bloque de tinta— y entre ellos manda **el más nuevo**, que además trae la idea que ordena la sección:
*«la sección ES el código: en una página de papel, un bloque de tinta es lo más importante de la
pantalla»*. Lo que sí se respeta del móvil es su **recorte**, que el canvas declara expresamente
aparte: *«el interruptor de móvil no se toca: son dos superficies y dos decisiones»*.

⚠️ **Y el argumento del presupuesto aquí es MÁS FLOJO que en el canvas, medido antes de aceptarlo**:
la sección que sustituye pesaba **1.108 px** en móvil, así que traer también las tres filas dejaría
la portada casi igual (−88 px) en vez de −283. Queda escrito para que el owner pueda revertirlo con
el número delante, que es lo que hizo en `#483` con el reloj.

❗❗❗ **EL CÓDIGO ES UN DIBUJO Y NO PUEDE SER OTRA COSA** (`SampleQrCode`, al lado del generador de
verdad para que nadie lo «arregle»). La propiedad no es que se parezca a un QR: es que **no se pueda
decodificar**, y es **estructural** — la banda de la información de formato queda vacía, así que
ningún lector llega siquiera a leer datos. ⚠️ Esa banda **no estaba reservada y la añadió una
guarda**: el generador del artboard solo reserva las esquinas, y con el ruido cayendo dentro de la
columna 8 «no se puede decodificar» era **incidental** en vez de estructural.

⚠️⚠️ **DOS GUARDAS NACIERON DEMASIADO ESTRECHAS Y LO DIJO EL ARNÉS, no una relectura.** Una aseveraba
`'<section id="rules"'` y **una mutación que devolvía el ancla en un `<span>` pasó en verde** — *la
propiedad dice que ese destino ya no existe, no que no exista una sección con ese nombre*. La otra
comprobaba que la sección no pide dibujos **instalando un kit sin esas claves**, así que medía una
ausencia que el propio arnés causaba (`<x-site.ilu>` no emite nada cuando la clave falta: el modo de
fallo invisible de `#287`). ▶ Y una **mutación era DÉBIL por precedencia**: `false && A || B` es `B`
en PHP. **13/13 muerden** tras corregir las tres (`scripts/mutar-antes-de-venir.sh`).

⚠️ **El precio de los calcetines NO se escribe, y no es un olvido**: el producto **no sabe cuál de sus
complementos son «los calcetines»**, y averiguarlo por su icono sería usar un campo de PRESENTACIÓN
como identidad — el defecto de `accent` que `#295` y `#301` pagaron dos veces. Y no hace falta: la
cifra ya se publica en esta misma página, en el carril de complementos de la 02 (medido: «+2 € cada
uno»).

⚠️ **La línea del niño invitado es DATO**: se pinta si y solo si algún producto ofrece el justificante
(`ticket_types.guardian_authorization`). Hoy ninguno lo hace, así que no sale — *prometer un enlace
que el catálogo no emite sería el ancla muerta que `#482` fichó*.

⚠️⚠️ **Y una trampa de la familia de botones, encontrada por la captura y no por la suite**: `.btn`
**no declara `justify-content`**, así que en cuanto recibe un ancho su rótulo se queda a la
izquierda. Medido: las **seis** reglas del repo que dan ancho a un `.btn` ya lo declaran a mano, o
sea que la convención existe y no está escrita. Ficha en `DEUDA.md` con su salida.

▶ **Medido**: la sección pesa **824 px** en móvil (contra los 1.108 de la de normas) y **1.099** en
escritorio (contra 736 — la diferencia es la cabecera común, que la vieja no tenía). La portada baja
a **12,01 pantallas** en móvil y sube a **11,12** en escritorio. Desborde horizontal **0** en las 13
vistas, y la sonda del repo no ve ningún control táctil nuevo bajo 48.

❗❗❗ **`#486` · EL OJO DEL OWNER, y una de sus dos cosas NO ERA DE ESTA SECCIÓN.** *«La barra fina
de debajo del CTA»* era la **costura entre secciones** —`.section + .section { border-top }`, que
pintaba las siete de la portada—: se retira, porque el sistema separa secciones **solo con aire**
(«uniforme de arriba abajo») y ninguno de los cinco artboards dibuja un divisor. Medido: **solo
existía en la portada**. ⚠️ Se notaba ahí porque el pie de la 05 ya lleva su propia raya, así que
salían dos líneas seguidas. ⚠️⚠️ **Y la guarda invertida nació «RISKY»**: aseveraba dentro de un
bucle que, sin regla que recorrer, dejó de vigilar sin ponerse roja — la trampa de `#482`.

▶ **El botón: `[DECIDIDO owner]` espera a la Fase 4.** El sistema lo declara 16/800 con borde de
1,5 px en Azul Muro y el nuestro es 14/600 en tinta; el arreglo llega a **45 usos y 11 ficheros del
cajón**. ⚠️⚠️ **El ejemplo que la hoja de componentes usa para ese botón es «Cómo llegar»**, que en
nuestra web es ese mismo `.btn--ghost`: no es el botón de una sección, es EL secundario del sistema.

▶ **La revisión se hizo VALOR A VALOR** (`scripts/comparar-seccion.mjs`, versionado porque quedan
tres secciones): **22 idénticas en móvil y 23 en escritorio, 0 sin explicar**. Encontró la chapa del
aviso a 20 donde el artboard escribe 24. ⚠️ Sus dos trampas van dentro, y la segunda acusaba al
producto sano: **un `clamp()` devuelve `18.0001px` donde el artboard escribe 18**.

⚠️ **Divergencia declarada**: el titular de escritorio parte con «QR» solo en la segunda línea —una
línea mediría **675 px** y el tope de la cabecera común son **608** (16ch, `#479`)—. No se toca
porque ese tope es de las ocho secciones y cambiarlo mueve las cuatro ya aprobadas.

❗❗❗ **`#484` · EL OJO DEL OWNER SOBRE LA T2e, y las dos cosas que señaló eran DEFECTOS.**

▶ **«Un recuadro sin border radius detrás de la card de Jump»**: era `[data-surface]` **pintando el
contenedor**. Esa regla no solo declara la superficie —`background: var(--bg)`—, así que con el
atributo en el `<li>` ése se volvía un rectángulo de tinta pura **con radio 0** y la misma caja que
la tarjeta. Enumerando las capas con el navegador salió en una línea. *Declarar una superficie no es
solo cambiar tokens: es pintar.* ⚠️ Y antes de eso la pegatina de tinta **no se leía**: keyline,
relleno y sombra eran casi el mismo color (contraste **1,19**), así que lo único visible era el
escalón de la esquina — el keyline pasa a seguir a SU superficie, divergencia declarada con el
artboard, **que tiene el mismo problema**.

▶ **«Quitamos la foto»**: y el problema no era cómo estaba puesta. La imagen que la instalación tiene
en `zones.image` para cumpleaños es **el comedor vacío**, y el propio artboard lo tenía pendiente del
dueño («foto del cumple montado»). `[DECIDIDO owner]` sobre tres opciones **renderizadas**: se quita
y la sección abre con `.sec-head`, la cabecera común de las ocho. La cabecera pasa de **549 a 73 px**
y la portada baja a **10,72 pantallas** en escritorio.

⚠️ Y en la misma captura se vio un defecto de dinero: «Señal de **50 € €**». *Una cadena con la
unidad dentro pide el número, no el importe escrito.*

✅ **T2e · «Cumpleaños»** (`#483`). Cabecera **sobre foto** —la única sección que la lleva—, el
**reloj de las dos horas**, **dos tarjetas de pack** que se comparan al lado y el **bloque de
complementos** con el molde de las tarifas.

❗❗❗ **POR TERCERA VEZ, EL ACTA DESCRIBE PIEZAS QUE EL ARTBOARD TIENE APAGADAS**: aquí el reloj y el
aviso INFO salieron con el recorte del 7 sep (la sección pasa de 1.605 a **1.153 px** medidos), igual
que la chapa de zona de `#480` y la del «18 más» de `#482`. ⚠️ Y el artboard **se movió durante la
tanda**: apareció un **turno 8** con el bloque «Tu fiesta, tu manera», ofrecido como propuesta.

▶ **Cuatro decisiones del owner**: el **paso a paso sale** de la portada (615 px) · las tarjetas
**navegan** a `/cumpleanos`, no venden · el turno 8 **entra con el molde de las entradas** · y el
**reloj entra en las dos superficies**, contra el artboard.

▶ **El bloque de complementos pasa a `<x-site.addons-rail>`**, compartido por 02 y 04 — *«es el mismo
formato y diseño que los complementos de las entradas»*. La extracción se verificó **byte a byte**
sobre el bloque renderizado de tarifas.

❗❗ **La edad que se publica es la del PACK**, no la de su zona: `guest_age_min/max` dice 4–7 y 8+, y
es el campo que **cobra el suplemento mixto**. Cierra la divergencia que el canvas dejó abierta.

❗❗❗ **DOS DEFECTOS REPRODUCIDOS EN NAVEGADOR, con la suite en verde.** (1) La tarjeta oscura no
declaraba su **superficie**: *pintarle el fondo no le cambia los tokens*, y su viñeta salía tinta
sobre tinta (contraste ≈ 1,1). (2) **El hover de la pegatina estaba mal desde `#478` en toda la
portada**: el paquete no declaraba `--shadow-float-hover` / `-press`, así que la tarjeta reposaba con
la sombra dura del cliente y saltaba a la difusa del producto. *Un comentario que describe un
mecanismo no lo implementa.* Arreglado, **también para la sección 01**, con guarda vista morder.

⚠️⚠️ **Y un localizador de guarda que acotaba mal, cazado de rebote**: `RateRailSectionTest::panel()`
recortaba «hasta el siguiente panel» y para el último se llevaba el resto del documento — contó 6
fichas donde su panel pinta 2. Es la lección de `#314` por otra puerta.

⚠️ **Una medición propia salió FALSA**: la primera consulta dio los packs a 0,00 € (leí `amount` en
vez de `amount_cents`). *Se cazó comprobándola por otra vía*, no releyéndola.

▶ **Medido**: la sección baja de **2.489 a 1.795 px** en móvil y la portada de **13,43 a 12,65
pantallas**. Packs 544×377 en la misma fila y a la misma altura, reloj a dos columnas, foto 21:9.

✅ **T2d · «Qué hay dentro»** (`#481` + `#482`). Rótulo · titular · entradilla que **abre con la
cifra** · **mosaico de cinco** —tres con nombre sobre banda de tinta al 82 %, dos **veladas**— y
**una sola puerta**. Y con ella nace **`/atracciones`**, la página de las 23, adelantada de la Fase 3
porque sin destino la puerta sería el ancla muerta que la regla del canvas prohíbe.

❗❗❗ **DOS CORRECCIONES A LO QUE EL ACTA DABA POR CERRADO, y las dos salen de releer el artboard el
día de la tanda.** La **chapa del «18 más» NO entra** —vive detrás de un interruptor **apagado**
desde el recorte de presupuesto del 7 sep, igual que la chapa de zona de `#480`—, y en su lugar van
la cifra en la entradilla y la puerta. Y el **titular es «Salta, trepa y déjate caer»**: `Juegos PJP`
sigue escribiendo «El parque», pero `doc/voz.md` declara que **los artboards de sección son el
registro de sus turnos** y que el entregable es `Portada PJP`, donde se aplicó el 9 sep.
▶ *Cuando dos fuentes del canvas se contradicen, la pregunta no es cuál gusta más: es cuál es fuente
para qué.*

❗❗ **EL REPARTO DE LAS CINCO NO ES «LAS PRIMERAS», y el artboard escribe por qué**: de las tres que
se leen, **una es de la primera zona y dos de la segunda** —*«antes se nombraban dos de Jump y una de
Kids, así que la madre de un niño de 4 años veía un solo juego de su zona con nombre»*—. Con el orden
global, en esta instalación **las cinco saldrían de Jump**. `[DECIDIDO owner]`: lo manda
`zones.position` + `attractions.position`, **sin campo nuevo**; medido, reproduce el mosaico del
mockup con los datos de hoy.

❗❗ **EL VELO SON DOS CELDAS O NINGUNA.** El artboard descarta su propia 4a por esto: *«el degradado
es una BANDA que disuelve el borde inferior de la sección; con solo dos de tres veladas deja de decir
"la sección se acaba" y dice "estas fotos están borrosas"»*. Medido: cubren **358 de 358** en móvil y
**1120 de 1120** en escritorio. ▶ De ahí la degradación: con **cinco atracciones o menos** el velo
desaparece.

❗❗❗ **`#481` · EL COLOR DE UNA ZONA APRENDE A SER TEXTO, y lo obligó una guarda con la suite en
verde.** La cifra grande de `/atracciones` se pinta con el color de la zona, que llega **del panel**;
medido, sobre papel el lima da **1,85** y el cian **2,45**, contra el 3,0 que es el suelo de
cualquier texto. La primera implementación mezclaba con un **porcentaje fijo** y pasaba con los
colores de esta instalación… pero el `#C6FF3A` que el **producto** trae por defecto para Kids se
quedaba en **3,14**. ▶ Sale `ThemeSettings::zoneInk()` (`--zone-ink`), que **oscurece por pasos**
hasta pasar el umbral; el paso es el **0,88** que ya usaba `actionHover()` (`#209`), y por eso
reproduce las variantes oscuras del propio sistema: cuatro pasos sobre el Lima Bote dan **`#627411`,
exactamente el Lima 800 que el artboard escribe a mano**.

⚠️⚠️ **La zona de llegada viaja en `?zona=` y NO en el hash**, contra lo que el artboard escribe: un
hash **no llega al servidor**, así que una pestaña elegida por ancla solo funciona con JavaScript.
**El precedente roto está al lado y medido**: `#478` enlazó a `/precios#zona-<slug>` y esa página
emite **cero** `id="zona-…"`.

⚠️⚠️ **LA RETIRADA ES LA MITAD DE LA TANDA Y CUESTA TRES COSAS**, las tres fichadas en `DEUDA.md` y
las tres del owner: el panel puede **vincular un complemento** a una atracción y ya no lo publica
nadie (medido: 0 de 23, así que la cifra de `#302` caducó) · los **dibujos `zone-<slug>` del kit** se
quedan sin pantalla · y el sitio **ya no sabe abrir el cajón posicionado en una zona** (⚠️ y al
medirlo salió su gemelo: el botón de tarifa dice «Comprar 1 hora en **Jump**» y abre sin intención,
lo que viene de `#479`).

⚠️ **Lo que sí se resolvió dentro**: `attractions.badge` se quedaba sin pantalla, y la ficha de
`/atracciones` declara **dos** chips —el canvas dejó el segundo vacío porque no tenía dato—. El
distintivo ocupa ese hueco.

❗❗ **TREINTA GUARDAS EN ROJO Y LAS TREINTA CON RAZÓN.** Se re-apuntaron las que se mudaron
(`ZoneIdentityIsUniqueTest` —**segunda vez** que pierde el sujeto—, `ThemeColorTest`,
`CmsLandingFlowTest`), `SidebarSeamTest` pasó a **censo**, entró `RideMosaicSectionTest` con 7 casos,
y se retiraron con su nota las que perdieron el sujeto. ⚠️⚠️ Y una salió **«risky»**: un caso que
solo asevera dentro de un bucle **deja de vigilar en cuanto el bucle se vacía, y lo hace en verde**.

▶ **El guion de poda VIAJA EN EL REPO** (`scripts/podar-css-huerfano.py`), por la lección de
`#475`: las cinco secciones que le quedan a la Fase 2 van a retirar CSS igual, y reescribirlo es
volver a pagar sus trampas. ⚠️⚠️ **Y dos de ellas, las dos con la hoja cuadrando de llaves**: esta
hoja tiene **llaves y nombres de clase dentro de comentarios** (un analizador que no los enmascara
abre reglas donde no las hay y salva reglas que debían irse — con la máscara aparecieron seis más), y
la primera versión iba a **borrar el foco de teclado de casillas y radios**, porque
`.ride-card:focus-visible` era uno de los seis selectores de esa regla.

✅ **T2c · «Cuánto»** (`#479`). Rótulo · titular · entradilla que vende con una cifra del catálogo ·
pestañas del sistema · **carril con foco** en móvil y **rejilla de tres pistas con foco** en
escritorio. Medido en escritorio: **352×397** contra los **351×398** que el canvas escribió.

❗❗❗ **EL ARTBOARD CAMBIÓ A MITAD DE LA TANDA, y eso vuelve a probar la regla de la cabecera de esta
spec**: se empezó sobre `Precios PJP` 6a y el owner aprobó **10a** mientras se construía —el nombre
del producto pasa de etiqueta mono a **rótulo**, la zona se va al botón, la cifra baja de 44 a 38 y
la tarjeta sube de 262 a 352—. *Esta fuente se mueve sola: se relee antes de cada tanda, y conviene
volver a mirarla antes de dar una sección por cerrada.*

❗❗❗ **La regla del nombre NO se pudo copiar tal cual, y ahí está el filtro de §2 en acción.** El
mockup parte `{nombre} · {matiz}` («1 hora · 60 min») y el catálogo de esta instalación escribe
`{ZONA} · {nombre}` («Jump · 1 hora»): su `split` daba nombre «Jump» y matiz «1 hora», o sea justo lo
contrario. ▶ Se retira el prefijo **cuando es exactamente el nombre de la zona** —una comprobación,
no una adivinanza— y solo después se aplica su regla. Con eso funciona sobre los dos formatos y una
instalación que no meta la zona en el nombre no nota nada.

❗❗ **LOS DÍAS SE DERIVAN DE `rate_types.weekdays`, y una entrada sin tarifa especial dice «solo».**
Lo segundo tiene motivo medido: `RateResolver::priceCents()` devuelve **`null`** un sábado para una
entrada sin precio especial — ese día **no se vende**, no es que cueste lo mismo.

❗❗❗ **EL ARTBOARD SE MOVIÓ CINCO VECES MÁS DENTRO DE LA MISMA TANDA** (turnos 11→16, más el 5a de
`Cumpleanos Pagina PJP`), y por eso la T2c tiene dos decisiones: `#479` y `#480`. *Esta fuente no se
relee antes de cada tanda: se relee antes de cada tanda **y mientras dura**.*
▶ Lo que trajo `#480`: **chapa de zona** por tarjeta (15b), **el ahorro con marcador amarillo**
(14a+16a), **la zona fuera del botón**, y **los complementos fuera de la tarjeta**, en un carril
debajo (5a).

❗❗ **EL AHORRO SE DERIVA DE `duration_min`, y sin duración NO se escribe** (`[DECIDIDO owner]`). El
artboard lo calcula desde el ÍNDICE de la tarjeta y su propia nota lo marcaba como decisión del
dueño. Medido: «Todo el día» contra tres sueltas ahorraría 6,00 € y **contra dos sale a −2,00 €** —
o sea que la cifra depende enteramente del supuesto, y por eso no se supone.

❗❗ **NACE `--marker`**, el rol de resalte, porque los dos candidatos eran peores: `--warn` lleva un
hexadecimal del PRIMER cliente y `--strip-3` cae en un color de zona. Transparente por defecto.

❗❗ **Y el KEYLINE del botón es una VARIANTE de la familia** (`.btn--keyline`), no un borde escrito a
mano — lo cazó el owner. **No va a todos**: el sistema declara «Completo» (sin borde) como defecto y
acota la pegatina a hero y cierre.

❗❗❗ **EL CHIP ES EL MARCADOR DE LA QUE LIDERA, y se corrigió DENTRO de la tanda.** Se había atado
a `ticket_types.badge`, que es un campo **independiente** de `featured`: reproducido con los datos de
esta instalación, «Kids · Ilimitada» llevaba el chip **sin ser destacada** mientras ninguna entrada
lo era — o sea, un marcador de líder en una zona sin líder. Hoy el chip, el ancho y el foco salen del
**mismo sitio**, y el TEXTO lo sigue escribiendo el panel: la señal es del diseño y la palabra es del
dueño. ⚠️ El `badge` de una tarjeta que no lidera **baja al MATIZ** en vez de perderse (precedencia:
si el nombre trae matiz propio, manda el del nombre), y **dos destacadas en una zona resuelven a una**
por índice.

⚠️⚠️ **Y ahí apareció un caso que MENTÍA sin fallar.** Leía los modelos **antes** del reset masivo,
así que reponer `featured => true` no ensuciaba el atributo y **Eloquent no emitía la escritura**: el
escenario decía marcar dos destacadas y marcaba una. *Un `update()` que repone el valor que el modelo
ya tiene en memoria no escribe nada, y si la fila cambió por detrás el caso monta un mundo que no
existe.* Lo cazó el arnés de mutación, no una relectura.

⚠️⚠️ **El ancho de 352 del artboard NO cierra con su propio asoma a 390 px** (16 + 352 + 12 + los
10 px que desplaza la escala del 94 % = **390,6**): a la anchura de referencia del sistema se veía
UNA tarjeta y nada detrás, y eso rompe una regla suya —en 02 la entradilla dejó de decir «arrastra si
quieres más» *porque la señal la da la tarjeta que asoma*—. ▶ `min(352px, calc(100vw - 58px))`:
desde ~414 px mide los 352 dibujados y por debajo encoge lo justo. Medido en seis anchos, asoma
22 · 20 · 23 px y desborde **0**.

⚠️⚠️ **Dos defectos de REPO que no venían en el encargo.** `.rides__title` pedía `font-weight: 800` y
`font-stretch: 75%` sobre Bungee, **que trae una sola cara**: el navegador falsificaba negrita y
condensada en el titular de cuatro secciones (verificado con `[...document.fonts]`). Son **77 reglas**
en todo el repo; aquí se arreglan las cinco cabeceras. Y **`SectionHeadlineTest` se había quedado sin
sujeto**: vigila `class="eyebrow"` exacta, y `#478` reintrodujo el rótulo como `zones__eyebrow`.

⚠️ **`Booking` no puede mirar a `Content`** y lo dijo `ModuleBoundariesTest`: los complementos los
resuelve la vista, no el presentador de dominio. Por eso la tarjeta lleva el `ticket` entero.

❗ **Queda un agujero de navegación**: el CTA retirado era el **único** enlace a `#rules` de toda la
web. El ancla se queda pero hoy no se llega a Normas navegando (ficha en `DEUDA.md`, dos salidas).

✅ **T2b · «Para quién»** (`#478`). Rótulo · titular · **la regla del parque en una frase** · dos
tarjetas donde **la tarjeta entera es el enlace**, con el sello de precio girado. `[DECIDIDO owner]`:
se **separa** 01 de 03 —la tarjeta *navega* a la tarifa y las pestañas *eligen* tarifa, así que no
vuelve el defecto de `#295`—, la **altura pasa a dos columnas** de `zones` (nullable: sin dato no se
pinta regla) y **manda la BD** en las edades.

❗❗❗ **Hallazgo: el canvas está equivocado sobre las edades, y su propia advertencia también.** Dice
Kids **2–6** / Jump **7+** y avisa de un conflicto con un catálogo que, según él, usa «1–6 / 7–99».
**Medido**: `zones.age_range` dice **4–7** y **+8**, y `ticket_types.guest_age_min/max` —el que
**cobra** el suplemento mixto— dice **lo mismo**. Los dos datos del producto coinciden; el que
diverge es el mockup, con una tercera cifra que no es la de nadie.

⚠️ **Las tarjetas alternan SUPERFICIE, no color de zona**: pintarlas con la paleta de la zona sería
la **grieta 01** que el propio canvas nos reportó.

⚠️ **`ZoneCards` vive en Booking**, y lo dijo `ModuleBoundariesTest`: `Content` solo puede ver
`Booking\Contracts`. No se tocó el grafo, se movió la clase.

⚠️⚠️ **Tres defectos que solo vieron la suite y el navegador**: `$especial['rate']` sobre `null`
**lanza** (31 casos en rojo, invisible en local porque aquí sí hay tarifa de finde) · el sello salía
sin rótulo porque `rate_types` tiene `label` y no `name` · y la altura salía **duplicada** porque el
texto libre ya la llevaba.

❗❗❗ **Y LA LECCIÓN QUE DEJA LA TANDA, que vale para las siete secciones que quedan: «idéntico al
mockup» NO se comprueba mirando.** La primera versión tenía la estructura y le faltaba el artboard
entero —foto, sello girado, eje de altura, frontera y velo—, y el owner lo vio en un vistazo. Se
construyó **`scripts/comparar-con-mockup.mjs`**, que renderiza el marcado del propio artboard y
compara pieza por pieza: dio **40 divergencias en móvil y 29 en escritorio** donde el ojo veía
«parecido». ▶ **Úsalo antes de dar una sección por buena.**

⚠️⚠️ **Lo que encontró y no se ve leyendo el código**: la **opacidad del velo cambia por zona** (22 %
el cian, 24 % el lima — compensación óptica, hoy derivada de la luminancia) · el nombre iba en peso
**700** y Bungee tiene uno solo, así que el navegador lo **sintetizaba** · el **sangrado del eje
estaba en el cuerpo** y arrastraba al velo y a la línea, que en escritorio empezaban en 90 en vez de
en 2 · el bloque teñido **crecía** y dejaba **68 px de color vacío** bajo el texto · y el **hueco de
la vecina es ASIMÉTRICO** en el artboard (60 arriba con 8 de margen, 56 abajo), sin lo cual **la
chapa del 1,30 tapa el rótulo «altura · 1,90 m»**.

❗❗ **Y TRES trampas del comparador, ya declaradas dentro de él**: el puntero virtual arranca en
(0,0) y deja una tarjeta en HOVER · el mismo ROL vive en soportes distintos en los dos marcados ·
y el ancho del sello lo manda el DATO, no el diseño. Con las tres, el informe baja a **4
divergencias por superficie**, todas decisiones declaradas.

▶ **El armazón coincidía con el marco aprobado del canvas en SIETE de diez** —hero y armazón como un
mecanismo, el par doble, el logo botando una vez, idioma y teléfono en menú y pie—, así que la tanda
fue pequeña: dos cambios y una confirmación.

❗ **Lo único del armazón que queda pendiente**: el **logotipo mide 46 y el táctil es 48**. Se deja a
propósito porque su alto entra en el cálculo aritmético del racimo (`#252`) y **la Fase 2 lo toca al
rehacer el armazón**; tocarlo suelto es descuadrar un cálculo que se va a rehacer.

⚠️ **Divergencia detectada y NO resuelta**: el canvas dice que *«el teléfono y el WhatsApp salen del
cierre»* y hoy el cierre tiene un `tel:` en su segundo CTA (`.reserve__act--alt`). No se tocó porque
no estaba entre lo preguntado — **es decisión del owner** y va con la sección del cierre.

### 5.5 · Estado de la Fase 3 · las páginas

La Fase 3 empieza por el **ARMAZÓN** —lo que comparten las siete páginas— y no por una página:
construir una encima del armazón viejo obliga a rehacerla (el mismo razonamiento que hizo de `#477`
la primera tanda de la Fase 2). Fuente: `Layout Paginas PJP` (1a móvil · 1b escritorio · 1c el menú
de escritorio) y `doc/paginas.md` del canvas.

| | Tanda | Estado |
|---|---|---|
| T3a·1 | **El armazón · los DESTINOS** del menú (y del pie): el inventario de páginas; en la portada, sus secciones | ✅ `#521` |
| T3a·2 | **El armazón · el PIE del marco**: sobre tinta · idioma visible · filas del marco · colofón — y las VELAS del pie y del menú, que no se apagaban nunca | ✅ `#522` |
| T3a·3 | **El armazón · la CABECERA de página**: rótulo con la ruta · Display L · entradilla · aire 96/144 | ✅ `#525` — las interiores sencillas y las pantallas de servicio; `/cumpleanos` y `/servicios` en su T3b · revisada por el owner: OK |
| T3a·4 | **El armazón · el CIERRE en las interiores**: la tarjeta de la portada sin juego ni eslogan, solo «Reservar» | ↩️ `#526` construida y **revertida por `#527`** (`[DECIDIDO owner]`): las interiores acaban en el pie de `#522`, sin tarjeta |
| T3b·1 | **`/atracciones`** | ✅ `#481` (adelantada en la Fase 2: la puerta de la sección 03 la necesitaba) |
| T3b·2 | **`/cumpleanos`**: el reloj, los packs comparados con contador, qué comen, el carril, «Igual en los dos», «Después de reservar» y el cierre | ✅ `#528` — revisada por el owner en vivo: OK · el vídeo del hero, cambiado en la misma jornada (`#529`) |
| T3b·3 | **`/precios`**: sin pestaña de zona · la semana dibujada · una tabla por zona con las dos columnas de precio entero · la hora extra como fila · los festivos · «Lo que se añade» | ✅ `#531` |
| T3b… | **Las páginas** que quedan, en el orden del Layout: `/normas` · `/servicios` · `/bar` · `/contacto` | ⬜ |

#### 5.5.1 · El contraste del Layout con el código, y lo que el owner decidió NO adoptar

Medido con la sonda de navegador el 2026-09-11 (8 vistas × 390 y 1280) antes de tocar nada:

| Pieza | Canvas | Código | `[DECIDIDO owner, 2026-09-11]` |
|---|---|---|---|
| Cabecera de las interiores | barra blanca **fija** 60/72 con filete de tinta de 4 px (token `componente.cabecera`) | racimo flotante sin fondo (`#201`) | ❌ **racimo flotante, como hoy** |
| Menú en escritorio | panel de 520 a la derecha, velo al 72 %, sin foto | pantalla completa con columna de foto (`#228`/`#341`) | ❌ **pantalla completa, como hoy** |
| Destinos del menú y del pie | el inventario de páginas | zonas, atajos y servicios del panel | ✅ el inventario |
| Rótulo del grupo | «Páginas» | «Otras páginas» | ✅ «Páginas» |
| Idioma en el pie | visible, ES · EN · FR | solo `<noscript>` (`#253`) | ✅ visible en TODAS — revierte `#253` |
| Colofón | «Nombre · Ciudad · © año» | lema + coletilla | ✅ el del canvas |
| Cierre en las interiores | la tarjeta sin juego ni eslogan | ninguno | ✅ solo «Reservar» |
| El interruptor «Sale en el menú» | — | queda sin efecto | ✅ se retira del panel |

⚠️⚠️ **Los dos «no» están DECIDIDOS, no pendientes**: quien abra el Layout mañana no debe
«terminar» la barra blanca ni el panel de 520. Tampoco se toca la **tira en cuña** del pie: el
Layout la dibuja fina de 4 px y el owner ya la había decidido en cuña de 22 contra el artboard.

⚠️ **Tres diferencias del armazón que NO son de esta tanda y siguen abiertas en el marco**, porque
el propio canvas las deja como conflictos del owner (marco `1b`): el rótulo del par va en **Bungee**
y el sistema dice *«Bungee nunca en un botón»* · el CTA dentro del menú es **amarillo** y el sistema
dice *«el amarillo nunca es botón»* · la barra de móvil del canvas es **naranja** y la nuestra es
**tinta** (`#225`). Se le traen al owner cuando toque el par, no antes.

✅ **T3a·1 · los destinos** (`#521`). `Content\Services\SiteDestinations` es la fuente ÚNICA de
destinos del menú y del pie: las páginas del inventario en el orden del Layout con la **ruta escrita**
debajo, sin las que estén en mantenimiento y sin `/bar` (su ruta no existe); y, **solo en la
portada**, sus cinco secciones con el rótulo que la propia sección pinta.

❗❗❗ **El defecto que cierra**: en una INTERIOR el grupo «En esta página» listaba las secciones **de
la portada** —en `/precios`: JUMP, KIDS, Atracciones y Ubicación, las cuatro llevando fuera—. Ya no
se pinta, y la página en curso sale en la lista marcada.

⚠️⚠️ **Retirar el interruptor del panel tenía una trampa**: la normalización lo forzaba a `true`
cuando no llegaba, así que quitar solo el campo habría reescrito el dato de todo servicio editado.
⚠️⚠️ **Y la guarda del inventario nació comparándose consigo misma** (lo esperado salía de la misma
constante que se muta): 2 de 9 mutaciones sobrevivían. Hoy el inventario va escrito en el caso.
Arnés `scripts/mutar-destinos.py`: **9/9**.

⚠️ **En local `/servicios` está en mantenimiento, así que el menú no la ofrece**: no es un fallo, es
la regla nueva funcionando. En producción sale en cuanto el panel la abra.

✅ **T3a·2 · el pie del marco** (`#522`). Una banda de **tinta a sangre completa** (`data-surface="ink"`
en el `<footer>` y la columna dentro) con la tira en cuña, los **destinos** de `SiteDestinations` más
«Mi cuenta» (y, solo en la portada, sus secciones), el **contacto**, el **idioma ES · EN · FR a la vista
en todas las páginas** —enlaces con `hreflang`, `lang`, el nombre nativo como nombre accesible y el
vigente marcado— y el **colofón «Nombre · Ciudad · © año»**, que sin ciudad no deja un «·» colgando.
Salen del pie el lema, la coletilla, las redes y el registro externo, y ninguno se pierde: el lema
sigue siendo el `<title>` de la portada cuando no hay «Título web», la coletilla el pie del post-form,
las redes siguen en el menú y en el `sameAs`, y el registro lo ofrece el par del armazón. Las ayudas
del panel de lema y coletilla ya no prometen el pie.

❗❗ **En escritorio contacto e idioma COMPARTEN FILA con lo legal** (`[DECIDIDO owner]`, se aparta del
Layout, que los dibuja en dos filas): con dos filas el pie crecía y el punto estático del cierre de la
portada dejaba de caber — medido a 1440×900, **45 px** de tarjeta sobre el pie; con una, **0**. Solape
con la fila única, en px: 390×844 **67** · 430×932 0 · 1280×800 **55** · 1280×900 0 · 1366×768 **94** ·
1440×900 0 · 1536×864 0 · 1920×1080 0. ▶ **Medido después con el pie anterior** (`#523`, servido en paralelo desde un árbol de
trabajo de la víspera): esas tres ventanas **ya pisaban, y más** —78 · 93 · 135 px, y 64 a 1536×864—;
el pie nuevo las redujo.

❗❗❗ **LAS VELAS DEL PIE Y DEL MENÚ NO SE HABÍAN APAGADO NUNCA**, y es el hallazgo de la tanda: colgaban
de `scroll(nearest …)` sobre el `::after` del ENVOLTORIO, que busca el contenedor de scroll ANTECESOR
—el documento en el pie, el propio `.menu` en el menú— y no la fila o la lista, que son su hermana y
su descendiente. Estaban siempre a la vista, y por eso nadie lo vio; las guardas del menú comprobaban
que la vela EXISTE. Hoy el carril declara su eje con nombre (`scroll-timeline-name`), el envoltorio lo
sube (`timeline-scope`) —la receta que el carril de complementos tenía desde `#498`— y
`ui/rail-sails.js` publica si el carril desborda (`data-rail-scroll`): sin desbordamiento la línea de
tiempo está inactiva y la vela se pintaría sobre el último destino sin nada detrás. Medido en
navegador: pie a 390 → **1** al inicio y **0** al final, **0** a 1280 (cabe); menú a 390×844 → **1** al
inicio y **0** al final, **0** a 1024×1366 (cabe), con la línea colgando ya de `.menu__col-list`.
⚠️ `rail-sails.js` vuelve a medir al llegar las fuentes y al acabar las transiciones: las filas del
menú entran con un desplazamiento que cuenta como contenido mientras dura (173 px de desborde a mitad
de la entrada, 158 al terminar) y eso no cambia el tamaño de la lista, así que ningún observador de
tamaño se entera.

⚠️ Nace **`--fg-body`**, el gris de CUERPO por superficie (en tinta, Papel 200 del paquete: paso de
despliegue en §5.bis). Guardas: `FooterFrameTest` (7) y la vela del menú en `ArmazonContractTest`;
arnés `scripts/mutar-pie.py`: **18/18** (14 del pie y 4 de la vela del menú). ⚠️ **El eje de la lista
del menú va en SU regla** (`.menu__col-list`), no en una segunda con el mismo selector: `MenuGroupsTest`
lee la primera que aparece en la hoja, y con dos perdió el `overflow-y` — lo cazó la suite completa.

✅ **`#523` · lo que corrigió el ojo del owner** (detalle en `DECISIONES #523`). `[DECIDIDO owner]` **el pie
de la PORTADA va sobre papel** —la tarjeta de tinta del cierre sobre un pie de tinta se fundía, y a
pantalla completa la banda rellenaba su marco— y las interiores siguen en tinta; la tira del pie se
retira con el progreso crudo del cierre para no asomar por ese marco. `[DECIDIDO owner]` **el par
arranca siempre con «Reservar» abierto** y el registro plegado invitando (revierte `#326`, corrección en
`armazon-y-menu.md` §13). ❗❗ **El «contraste roto» era un dato de test en la base de desarrollo**:
`theme.brand = #0A0B0C`, escrito a mano por otra sesión y sin devolver; con él «SALTAR» y las chapas se
pintaban casi negras sobre tinta. Devuelto a `#1AA9DE`. ⚠️ Y dos defectos de verdad: el sello de
precio salía claro sobre amarillo en la tarjeta de tinta (el paquete declara ahora su tinta en
`--on-marker-brand`) y «Configuración de cookies» bajaba a 3,7 sobre papel por una `opacity`.

✅ **T3a·3 · la cabecera de página** (`#525`). Un componente, `<x-site.page-head>`: rótulo en Etiqueta con
la **ruta escrita** —la deriva `SiteDestinations::writtenPath()`, la misma función que escribe la ruta
bajo cada destino del menú—, titular en Display L y entradilla **opcional** (sin ella no se pinta el
párrafo). `[DECIDIDO owner, 2026-09-11]` **la llevan ya las interiores sencillas** —`/precios`, `/normas`,
`/contacto`, `/atracciones` y las legales— y las **pantallas de servicio** (mantenimiento, pago,
contraseña, verificar el correo), que pasan su rótulo a mano: no son destinos, y la de contraseña lleva un
TOKEN en la URL. **`/cumpleanos` y `/servicios` la reciben al rehacerse (T3b)**: su cabecera va dentro de
un bloque —la banda con foto, el hero con índice— que esa tanda sustituye. Y `[DECIDIDO owner]` **las
entradillas dicen solo lo que la página enseña**: `/precios` «Todas las tarifas, con sus días.» (la del
canvas sin «y sus complementos», que la página no pinta; la anterior afirmaba que las entradas se
compraban «en taquilla o por teléfono» al lado de un botón que las reserva online), `/normas` la del canvas
y las legales sin entradilla.

❗❗❗ **«Una página no estrena tipografía» es ya verdad en el CSS**: `.page__eyebrow`, `.page__title` y
`.page__lede` comparten la declaración tipográfica de `.sec-head__*`. El titular propio de `site.css`
—40/80 px con `font-weight: 800` y `font-stretch: 75%` sobre Bungee, que trae UNA cara— **falsificaba**
negrita y condensada en `/normas`, `/contacto`, las legales y cuatro pantallas de servicio, y se retira.
Con él se van `.rides__head`/`.rides__title`, cuyo último consumidor era `/precios`, y los cuatro
compuestos `.page--rides .page__…` con los que la estrenó `/atracciones`.

❗❗ **LA DECORACIÓN VIVE EN EL CONJUNTO rótulo + titular (`.page__lockup`), que la RECORTA, con la
entradilla fuera.** Lo destapó la CAPTURA, no la suite: la trama de `/normas` cayó debajo de la
entradilla nueva, y —medido contra la captura de partida— **en móvil el abanico de `/precios` ya caía
detrás de las tres líneas de la suya ANTES de esta tanda**. Las dos piezas tienen escrita la regla
«detrás del titular y nunca detrás de un párrafo»; contra la cabecera entera dependían de lo largo que
fuera el texto, contra el conjunto no pueden alcanzarlo. ⚠️ El recorte va solo con decoración
(`.page__lockup--deco`): un titular de Bungee a `line-height: .95` puede sacar una coma por debajo de su
caja. ⚠️ El abanico se ve ahora como una BANDA a la altura del rótulo y el titular (en escritorio antes
bajaba hasta la entradilla, sin pisarla): es el precio de la regla, y lo mira el owner.

▶ **El hueco de arriba se DERIVA del racimo** (`--nav-pad-block` + la más alta de sus piezas + 28/44),
no de `vh` (era `clamp(108px, 14vh, 156px)`): a 390 el rótulo cae en **88 px, justo donde lo pone el
canvas** (su barra de 60 + 28), aunque aquí el racimo flota sin barra. El de abajo es el **aire entre
secciones** (96/144 en esta instalación). Medido antes → después: el titular de `/normas`, `/contacto` y
las legales **40 → 34 px** en móvil y **77 → 52** en escritorio, peso **800 → 400**; `/atracciones` pasa
de dos líneas a una en escritorio (el tope de 20 caracteres de 1b); cero desbordes horizontales.

Guarda `PageHeadTest` (5 casos) + `scripts/mutar-cabecera.py` (**18/18**). ⚠️⚠️ **La guarda NACIÓ LAXA y
lo dijo el arnés**: sacar `.page__lede` de la regla tipográfica compartida **sobrevivía**, porque la
entradilla también comparte con la de sección la regla de su MARGEN y el caso aceptaba cualquiera. Hoy
exige compartir la que declara `font-size`. *«Comparte una regla» no es «comparte la declaración».*

↩️ **T3a·4 · REVERTIDA por `#527` el mismo día** (`[DECIDIDO owner, 2026-09-11]`): visto en vivo, *«el footer
déjalo como estaba»* — la tarjeta encima del pie hacía de la banda de tinta un bloque que en un teléfono llena
la pantalla. Se deshizo con `git revert` del commit de código; **el pie de `#522` se queda** y **las páginas
interiores acaban en él, sin tarjeta de cierre**. Es el TERCER «no» al Layout, con la barra blanca y el panel de
520: no se «termina». Lo de abajo se conserva como registro de lo construido y medido.
⚠️ *Una decisión visual sobre algo que ocupa más de una pantalla se enseña desplazándose, no en un fotograma*:
se eligió sobre capturas de ventana y se deshizo al verlo entero.

📜 **T3a·4 · el cierre en las interiores, tal como se construyó** (`#526`). La tarjeta de la portada **sin el juego, sin el eslogan
y con un solo botón**, y `[DECIDIDO owner, 2026-09-11]` **dentro de la banda de tinta del pie** —la opción A,
elegida sobre dos renderizadas: la B la ponía sobre el papel antes del pie, como en la portada—. La llevan
las seis páginas del inventario que existen; ni las legales ni las pantallas de servicio. El texto es el de
la portada (`[DECIDIDO owner]`: el teléfono sale justo debajo, en el pie).

▶ **El cuerpo es COMPARTIDO con la portada** (`<x-site.closing-body>`): titular, texto y la regla de a dónde
lleva «Reservar» con la venta online cerrada. ⚠️ La CAJA de la portada no se toca —lleva los manejadores
del juego en el propio elemento y `SaltaJuegoTest` los exige ahí—. ▶ Lo que la portada hace y aquí no
significa nada se retira: la altura en reposo («el hueco que deja el pie», que publica su coreografía) →
**la mide el contenido**; el hueco del minijuego → **el de la chapa**, derivado de su geometría; la sombra de
elevación, invisible sobre tinta → **el filete de la superficie**. ▶ **La barra de móvil se retira sola**: se
aparta al entrar `.foot`, y el cierre ya es `.foot`; en la portada sigue por encima a propósito (`#253`).

❗❗ **El defecto que vio la sonda**: en escritorio la tarjeta medía **610 px de 1120** —desde 1024 el pie es
`flex` con salto y solo lo que está en su lista de «fila entera» ocupa la fila—. ⚠️ Y **la suite completa cazó
lo que la tanda dirigida no**: `FooterFrameTest` lee `.foot__colophon { flex: …` tal cual, así que la tarjeta
va delante del colofón en esa lista. Guarda `PageClosingTest` (5 casos) + `scripts/mutar-cierre.py` (**13/13**).

✅ **T3b·2 · `/cumpleanos`** (`#528`, detalle en `DECISIONES #528`). La espina del artboard: **el reloj** (el
mismo componente que la portada, `x-site.party-clock`), **los packs comparados** con un contador de niños,
**qué comen**, el **carril** sin el menú, **«Igual en los dos»**, **«Después de reservar»** y el cierre con la
tarjeta de **edades mezcladas** y el aviso. ❗❗❗ *«Una comparativa solo compara lo que DIFIERE»*:
`BirthdayComparison` mira cada hecho en todos los packs y lo manda a «Igual» o a una fila; **el total se
calcula en el SERVIDOR para cada número de niños** con el precio que cobra la cesta (tramos incluidos). ▶ Lo
que no entra, por decisión del owner: **las dos fotos** (hasta que las mande), el paso a paso, el editor de
invitaciones (con `html2canvas`) y la ficha de ejemplo. ⚠️ Quedan sin pantalla `zones.image` de cumpleaños y
`<x-site.ilu>` (`DEUDA.md`). Guarda `BirthdayPageTest` (14) + `scripts/mutar-cumple.py` (**18/18**).

▶ **EL ARMAZÓN QUEDA COMPLETO con T3a·1 → T3a·3** (la T3a·4 se retiró por decisión del owner), y de las páginas
están `/atracciones` y `/cumpleanos`. Lo siguiente, en el orden del Layout: `/precios` · `/normas` ·
`/servicios` · `/bar` · `/contacto`. ▶ **La Fase 4 (el SPA) la lleva desde `#530` otro agente en otro
ordenador**, con banda **550–579** y su arranque en **`docs/CARRIL-SPA.md`**. Dos
preguntas del owner esperan a su página: **`/bar`** (no existe; el canvas la condiciona a que el bar tenga carta,
`#482`) y **`/entradas`** (existe sin estar en el inventario: entra o se retira, §4.1).

### 5.2 · Las cuatro excepciones del owner

El owner marcó cuatro piezas donde **no** quiere copia literal: **el racimo**, **el hero de
cabecera**, **el hero del cierre** y **quizá el mega menú**. Las cuatro están cerradas y dibujadas
en el canvas, así que en la Fase 2 se le enseñan **opciones renderizadas** y elige él.

⚠️ Y hay dos decisiones nuestras que el canvas puede estar contradiciendo sin que ninguna guarda se
entere: **`#195`** (el hero sin CTA — reabierto ya por `#216`) y **`#216`** (el armazón nace bajo el
hero). El canvas coincide con `#216`: *«en «Entrada» no hay logo, ni menú, ni barra, y por eso el
hero lleva las dos puertas enteras»*.

---

## 6 · Lo que el canvas debe al owner

Su propia lista, sin filtrar — **nada de esto bloquea el código** porque todo es data-driven y se
sembrará, pero sí bloquea publicar:

1. ~~**El vídeo o el póster del hero** (huecos `pjp-hero-poster` y `pjp-hero-esc`).~~ ✅ **CERRADA en `#529`**: el
   owner subió su vídeo y ya es el del hero (H.264 720p, sin audio, 2,27 MB; el póster es su primer fotograma).
   Siguen pendientes **las dos fotos de `/cumpleanos`** (la zona montada y la mesa, `#528`).
2. **Los nombres reales** de las 23 atracciones y **el nombre del bar** (hoy se llama «el bar» en
   todas las superficies, y es el titular de `/bar`).
3. **El aparcamiento**: 07 dice «en la calle, delante, y gratis»; la web publicada dice «gratis 2 h
   en el recinto, después 1 €/h». **Hay que decidir cuál es verdad.**
4. **Los festivos de Lorca y de la Región**, que son justo los que el visitante de fuera no sabe.
5. **El horario en conflicto**: publicado L–V 16:30 · S–D 11:00 contra el cerrado L–J 16:30 · V–D
   11:30.
6. **La grieta 00** (el cuerpo del cajón a 16).
7. **Qué pasa con `/entradas`** (§4.1).
8. ~~**El contenido del «18 más»** de la sección 03.~~ ✅ **CADUCADA en la T2d** (`#482`): la chapa
   del «18 más» **no entra** —el propio artboard la tiene detrás de un interruptor apagado desde el
   recorte del 7 sep—, así que no hay texto que escribir. Lo que la sustituye —la cifra en la
   entradilla y la puerta— es **dato**, y sale solo.
   ⚠️ Y de paso caduca media línea del punto 2: **los nombres reales de las 23 atracciones NO
   faltan**. El canvas los pedía porque los suyos eran de relleno; medido en la BD, las 23 tienen
   nombre, descripción, edad y **su foto en disco**. Lo que sigue faltando de ese punto es **el
   nombre del bar**.
9. **Si el BAR entra en la sección 03** (`#482`, `[DECIDIDO owner]`: **no todavía**) y, con él,
   cuándo se construye `/bar`.
10. **Las tres pérdidas que deja la retirada del carrusel** (`#482`, fichas en `DEUDA.md`): el
    complemento por atracción que el panel puede vincular y ya nadie publica, los dibujos
    `zone-<slug>` del kit sin pantalla, y que el sitio ya no abra el cajón posicionado en una zona.

---

## 7 · Trampas ya pagadas que aplican aquí

De las que este proyecto ya tiene fichadas y van a volver a aparecer:

- **`#266`**: una animación CSS sobre un elemento de `<defs>` **no pinta nada**, y dentro de un SVG
  los `px` de un `transform` son unidades del `viewBox`. El canvas trae mucho SVG animado.
- **`#254`**: el logotipo se sirve **en línea** para poder animarlo, y eso cambia el modelo de
  amenaza — dentro de un `<img>` un SVG es inerte y en línea no.
- **`#238`**: `--wrap-gutter` solo sirve en un elemento **a sangre completa**; dentro de una caja
  acotada estrangula la columna y **no se ve a 1280 ni a 390**.
- **`#314`**: a igual especificidad gana la última regla del fichero — las reglas de móvil escritas
  antes de su base **no hacen nada**.
- **El canvas avisa de la suya**: *«una cifra en una nota se mide en el DOM o no se escribe»* y
  *«copiar una pieza del sistema es copiar sus ESTADOS, no solo sus colores»*.
