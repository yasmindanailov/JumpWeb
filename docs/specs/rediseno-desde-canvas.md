# Rediseño desde el canvas de Claude Design

> **Estado:** 🟦 Fase 0 · inventario hecho, código NO empezado
> **Banda de decisiones:** 470–499 (la reapertura es `#469`)
> **Fuente:** canvas `8c37d2d2-7e9c-43a9-bc25-aacb6607f2ad` · sistema **v1.32** · tokens **v1.9**
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
| **1** | **El sistema**: tokens (color · tipo · espacio · forma · elevación · movimiento) + los 65 iconos | `site.css` y `client.css` a v1.9, el set con su guarda | 2, 3, 4 |
| **2** | **El armazón + las 8 secciones**, móvil y escritorio | la portada entera, vestida con BD | 3 |
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
| T1e | Movimiento: **+1 duración, +1 curva** y reconciliar `--ease-cae` | producto | ⬜ |
| T1f | Punto de corte **1024** | producto | ⬜ |
| T1g | La escala tipográfica del canvas | producto + paquete | ⬜ |
| T1h | Los **65 iconos** | producto | ⬜ |

⚠️ **T1e trae una decisión del owner**: el canvas declara **cinco** curvas y nosotros cuatro, y
`#262` decidió expresamente **no estrenar una quinta** —midió que la del artboard y `--ease-entra`
se separan **0,50 px** a la talla del hero y que hacerlo deshacía la tanda 2d—. El canvas ha
formalizado esa quinta (`caída`, la única que acelera) en su fichero de tokens, y además su `lona`
tiene sobreimpulso **1.81** donde nuestra `--ease-cae` tiene **1.56**. Se pregunta antes de tocar.

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

⚠️ Y arrastra las **cinco líneas** que ya venían pendientes de `auditoria-diseno.md` (`#434` dos,
`#436` tres): comprobar que están puestas antes de dar por buena una verificación visual en
producción.

---

## 5.ter · ⏳ Verificación de navegador, pendiente

`TouchTargetTest` **no mide píxeles** —lo dice su propio docblock— así que subir el táctil a 48
puede solapar dos áreas sin que la suite se entere. Lo mismo valdrá para la columna y el aire.

▶ **Una sola pasada al final de la Fase 1**, con toda la geometría dentro, en vez de una por tanda.
⚠️ La sonda vivía en `/root/e2e/tap44.mjs`, **fuera del repo y ya no existe**, y hay que
reconstruirla leyendo antes `VERIFICACION-E2E-CAJON.md` §5.duovicies: **salió mal dos veces con
cifras plausibles** (áreas negativas por recortar en coordenadas de viewport; altos de 58 donde son
44 por no aplicar el `transform` del pseudo-elemento). El contenedor tiene node v24 pero **ningún
navegador instalado**.

---

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
