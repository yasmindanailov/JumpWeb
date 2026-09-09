# Rediseño desde el canvas de Claude Design

> **Estado:** 🟦 **Fase 1 CERRADA y verificada en navegador · Fase 2 en curso** (armazón + secciones 01 y 02; quedan SEIS)
> **Banda de decisiones:** 470–499 (la reapertura es `#469`)
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

De las **siete del inventario del canvas**, **cinco existen** y **dos son nuevas**:

| Página | Estado | Ruta / vista |
|---|---|---|
| `/precios` | ✅ existe | `PricingController` · `pages/pricing.blade.php` |
| `/cumpleanos` | ✅ existe | `EventsController` · `pages/events.blade.php` |
| `/normas` | ✅ existe | `PageController@rules` · `pages/rules.blade.php` |
| `/servicios` | ✅ existe | `ServicesController` · `pages/services.blade.php` |
| `/contacto` | ✅ existe | `ContactController` · `pages/contact.blade.php` |
| `/atracciones` | ⬜ **NUEVA** | — |
| `/bar` | ⬜ **NUEVA** | — |

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
| **2** | 🟦 **El armazón + las 8 secciones**, móvil y escritorio | la portada entera, vestida con BD | 3 |
| **3** | **Las páginas**: 2 nuevas + 5 rehechas con el armazón de `Layout Paginas` | las siete del inventario | — |
| **4** | **El SPA**: las 9 grietas + las 5 paradas del canvas | el cajón | 5 |
| **5** | **Post-form y justificante digital** | lo que hoy es funcional y no está vestido | — |

### 5.1 · Las ocho secciones de la portada

Rótulo · titular · artboard. **Los rótulos NO llevan número** (`[DECIDIDO owner]` del canvas) y los
titulares son frases de **3 a 6 palabras**:

| Rótulo | Titular | Artboard |
|---|---|---|
| Para quién | Cada uno tiene su zona | `Zonas PJP` (4a) |
| Cuánto | Una hora, dos o el día | `Precios PJP` (6a) |
| Qué hay dentro | Salta, trepa y déjate caer | `Juegos PJP` (6a) |
| Cumpleaños | El cumple, resuelto | `Cumpleanos PJP` (7b) |
| Antes de venir | Tu registro es este QR | `Antes de Venir PJP` (2a) |
| Reseñas | Lo dicen los que han venido | `Resenas PJP` (2a) |
| Visítanos | Dónde estamos y cuándo abrimos | `Visitanos PJP` (7b) |
| Dudas | Lo que más nos preguntáis | `Dudas PJP` (1a) |

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
| T2c (`#480`) | añadir **`--marker: #F5C400`** y **`--on-marker: #101418`** al paquete. Sin ellos el ahorro se queda en texto en negrita —conducta correcta, pero se pierde el resalte—. ⚠️ Y **DATO, desde el panel**: elegir el **icono** de cada complemento (`cake`, `ice-bucket`, `snacks`, `drink`, `clock-plus`, `socks`) — los catorce estaban en `NULL`, o sea todos con la entrada genérica; el mecanismo existe desde `#475` y aquí solo faltaba usarlo. |
| T2c (`#479`) | añadir **`--money`** al paquete: `#627411` en `:root` y en `[data-surface="paper"]`, `#A3C21C` en `[data-surface="ink"]`. Es el rol de CIFRA; **sin él los precios salen en tinta**, que es la conducta anterior — no se rompe nada, solo se pierde el color. ⚠️ Y **DATO, desde el panel**: el **orden** de las pestañas lo manda `zones.position` (el canvas ordena Kids · Jump y aquí sale Jump primero, la misma decisión pendiente de T2b), y **`ticket_types.featured` está a cero en las cinco entradas** — sin ninguna destacada, el carril abre por la primera y no hay tarjeta ancha ni chip. Es una elección suya, no un defecto. |

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
| T2d–T2i | Las **seis secciones** restantes (§5.1) | ⬜ |

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

1. **El vídeo o el póster del hero** (huecos `pjp-hero-poster` y `pjp-hero-esc`).
2. **Los nombres reales** de las 23 atracciones y **el nombre del bar** (hoy se llama «el bar» en
   todas las superficies, y es el titular de `/bar`).
3. **El aparcamiento**: 07 dice «en la calle, delante, y gratis»; la web publicada dice «gratis 2 h
   en el recinto, después 1 €/h». **Hay que decidir cuál es verdad.**
4. **Los festivos de Lorca y de la Región**, que son justo los que el visitante de fuera no sabe.
5. **El horario en conflicto**: publicado L–V 16:30 · S–D 11:00 contra el cerrado L–J 16:30 · V–D
   11:30.
6. **La grieta 00** (el cuerpo del cajón a 16).
7. **Qué pasa con `/entradas`** (§4.1).
8. **El contenido del «18 más»** de la sección 03.

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
