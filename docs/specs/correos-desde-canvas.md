# Los correos, desde el canvas de Claude Design

> **Estado:** 🟦 **T1 (vestido) · T2 (remitente) · T3+T4 (el MOLDE) · T5 (LA BANDEJA: 63 líneas de adelanto y los 21 asuntos) EN EL ÁRBOL** · queda el correo de la fiesta mixta, y el OJO del owner en un cliente de correo real
> **Banda de decisiones:** **500–519** (`[DECIDIDO owner, 2026-09-10]`; la del carril de diseño de la web pública es 470–499 y va por `#489`)
> **Fuente:** canvas `8c37d2d2-7e9c-43a9-bc25-aacb6607f2ad` · artboard `Correos PJP` (turno 1a) · `doc/correos.md` · `doc/reglas.md` · tokens **v1.10**
> ⚠️ Esa fuente **se mueve sola** (`rediseno-desde-canvas.md` §1: v1.9 por la mañana y v1.10 por la tarde del 09-09): **se relee antes de cada tanda, no una vez por carril.**
> **Hermana:** `docs/specs/rediseno-desde-canvas.md` — el mismo canvas, la web pública. **Los correos NO están en ninguna de sus cinco fases**, y por eso son carril propio.

---

## 1 · Por qué este carril y no el SPA

El owner pidió el **SPA**. Al medir el terreno salió que el CSS del cajón vive dentro de
`public/css/site.css` —la misma hoja que el otro agente está moviendo para la web pública—, así que
se contrastaron las cuatro superficies que podían ir en paralelo sin pisarse:

| superficie | ¿carga `site.css`/`landing.css`? | ¿artboard? | ¿existe en código? |
|---|---|---|---|
| **Correos** | **NO** — tema propio (`vendor/mail/html/themes/brand.css`) | ✅ `Correos PJP` | ✅ 23 |
| Panel admin | NO — `theme.css` de Filament | ⚠️ solo **uno** (`Puerta Panel PJP`) | ✅ |
| Post-form + justificante | **SÍ, los dos** (`focused-layout.blade.php`, sus dos `<link>`) | ✅ | ✅ |
| Invitación digital | — | ✅ dos | ❌ **no existe**: cero rutas, cero modelos |

`[DECIDIDO owner, 2026-09-10]`: **los correos.** Es la única con aislamiento real de CSS, tiene
artboard, no tiene dueño en el plan del otro carril y se verifica con Mailpit sin sesión ni enlace
firmado.

---

## 2 · ❗❗❗ El filtro producto / PlayJump, aplicado a un correo

`DECISIONES #1`: este repo es el PRODUCTO, sin marca de ningún cliente. En la web ese filtro lo
resuelven los tokens: el **rol** va al repo y el **hex** al paquete de la instalación. **En un correo
ese mecanismo no existe, y es estructural**, no un descuido:

- Los clientes de correo no resuelven `var(--…)` de forma fiable.
- Laravel **inlinea** el tema sobre cada etiqueta antes de enviar (`CssToInlineStyles`).
- ▶ Por tanto **`public/css/client.css` NO llega al correo**. Lo que un cliente redefina o colapse
  ahí, el correo lo ignora. Medido: **0 usos de `var(`** en `brand.css`.

### 2.1 · Lo que SÍ es data-driven ya, y por otra puerta

Blade sí ejecuta PHP, así que las piezas que viven en plantilla pueden leer la BD — y **ya lo hacen**:

| pieza | dónde | qué lee |
|---|---|---|
| Logotipo y nombre del negocio | `header.blade.php` | `business.name` + `img/client-logo@4x.png` (`#325`) |
| Color del botón principal | `button.blade.php` | `ThemeSettings::brand()`, inyectado inline (gana al inlinear) |

### 2.2 · La conclusión, y es la decisión de doctrina de este carril

**Lo que queda escrito en `brand.css` no es marca: son SUPERFICIES y FORMA.** Y las que había —crema
`#ECE5D2`, tarjeta `#FBF7EC`, tinta `#14130F`, gris cálido `#6B675D`— eran las del **PRIMER cliente**.

> ▶ Cambiarlas a los neutros del sistema **no clava a un cliente nuevo: quita al viejo.** Por eso
> entran como **default del PRODUCTO**.

⚠️ **Lo que esto NO cierra**: si algún día un cliente necesita SUS superficies en el correo, hace
falta un hueco que hoy no existe. Ficha en `DEUDA.md`. El comentario de `brand.css` lo declaraba
como «personalización futura» desde `#102` — y apuntaba a un «07-PANEL-ADMIN» que **ya no existe** (hoy es `docs/PANEL-ADMIN.md`).

---

## 3 · El contraste canvas ↔ código, medido

Las cifras del canvas se verificaron **antes** de leer su documento (se midió el tema por separado y
luego se abrió `doc/correos.md`), así que la coincidencia es independiente:

| | canvas | código | |
|---|---|---|---|
| Nº de correos | 23 (21 cliente + 2 parque) | 21 `app/Notifications/` + 2 `app/Mail/` | ✓ |
| Fondo · tarjeta · texto · gris · acento | `#ECE5D2` `#FBF7EC` `#14130F` `#6B675D` `#FF5B22` | idénticos | ✓ |
| Fuentes | Space Grotesk / Bricolage Grotesque | idénticas | ✓ |
| Línea de adelanto | «no existe en ninguno» | **0 ocurrencias** de preheader | ✓ |

### 3.1 · ⚠️ TRES cosas que el canvas no tenía bien o no podía ver

1. **«Los veintitrés se anuncian con ¡Hola!» es impreciso.** Medido: hay **dos** saludos —`'Hola,'`
   ×11 y `'¡Hola!'` ×8 sobre 19 claves—. El hallazgo aguanta (ninguno dice nada, y sin línea de
   adelanto el gestor coge el saludo); **la cifra no**.
2. **Las líneas de adelanto no son 23: son 69.** El diccionario existe en `es`, `en` y `fr` (169
   entradas cada uno). Cada frase se escribe tres veces. Eso **triplica** el coste de la decisión.
3. **Hay un cuarto idioma sin correos**: `lang/zh_CN/` tiene `admin.php` y `tickets.php` y **no
   `emails.php`**. Un cliente en chino recibe los correos en el idioma de respaldo. Preexistente,
   ficha en `DEUDA.md`.

### 3.2 · ❗❗ Y una afirmación suya que es FALSA: «un archivo de estilos y tres plantillas»

Medido al ejecutar la T1: **son cinco plantillas, no tres**, y las dos que faltaban son justo las que
pintan el dinero y el producto.

| fichero | valores de la paleta vieja |
|---|---|
| `vendor/mail/html/themes/brand.css` | 41 |
| `vendor/mail/html/footer.blade.php` | 6 |
| `emails/partials/book.blade.php` | **26** ← el LIBRO del pedido |
| `emails/partials/product-card.blade.php` | **17** ← la tarjeta de producto |

Vestir solo el tema habría dejado el correo a medias —marco nuevo, libro y producto viejos—, que es
peor que no tocarlo.

---

## 4 · Lo que decide el owner

`[DECIDIDO owner, 2026-09-10]`, sobre las cinco preguntas de la sección G del artboard:

| # | qué | decidido |
|---|---|---|
| 1 | **Los botones** | **El mapa del naranja también aquí**: naranja solo en los DOS que venden (reintentar el pago · volver a reservar), tinta en los otros diecinueve |
| 2 | **Las líneas de adelanto** | **Se escriben las 69** (23 × 3 idiomas) y el owner las corrige |
| 3 | **El correo de la fiesta mixta** | **Turno propio**, fuera del vestido: es texto sobre dinero |
| 4 | **El remitente** | **Que salga del PANEL, no del `.env`** — mecanismo nuevo |
| 5 | **La fuga de marca** (hallazgo nuevo, §5) | **Entra con el vestido** |

Y una **asunción declarada** del agente, no vetada: los neutros del canvas entran como default del
PRODUCTO (§2.2).

---

## 5 · ❗❗❗ El hallazgo que el canvas no tenía: la marca del PRODUCTO en la bandeja del cliente

Un correo tiene **cuatro** superficies de marca. Medidas el 2026-09-10 con `business.name =
SaltoPark` y `app.name = JumpWeb`:

| superficie | de dónde salía | decía |
|---|---|---|
| Cabecera | `business.name` con fallback | **SaltoPark** ✅ |
| Remitente | `MAIL_FROM_NAME` = `${APP_NAME}` | **JumpWeb** ❌ |
| Firma («Un saludo, …») | el defecto de Laravel, `config('app.name')` | **JumpWeb** ❌ |
| Copyright | `vendor/mail/html/message.blade.php` (el pie) | **JumpWeb** ❌ |

> El mismo correo decía **SaltoPark arriba y JumpWeb tres veces**, en **20 de los 21** que lee una
> persona (solo `GuardianAuthorizationSigned` pone `->salutation()` propia). Es `DECISIONES #1` del
> revés, y **ninguna guarda lo miraba**.

### 5.1 · El defecto de fondo: dos formas de leer el nombre que no son equivalentes

Nueve copias del patrón, en dos variantes:

- `Setting::value('business.name', config('app.name'))` — `value()` resuelve con `?? $default`, así
  que **solo cae al defecto si la clave NO EXISTE**. Con la fila creada y el valor en blanco devuelve
  `''`: **seis correos firmaban con el nombre vacío.**
- `Setting::value('business.name') ?: config('app.name')` — ésta sí cae con el vacío.

Un operador que borre el campo en el panel producía dos conductas distintas según qué pantalla lo
leyera, y ninguna fallaba. ▶ Nace **`Setting::businessName()`**, un solo lector, y **las nueve
copias se migran** (la regla del canvas: *«al arreglar una interacción se arregla su FAMILIA»*).

---

## 6 · Plan de obra

| tanda | qué | estado |
|---|---|---|
| **T1** | **El vestido** — los 16 valores del tema + las cinco plantillas + el modo oscuro + la fuga de marca | ✅ **en el árbol** (§7) |
| **T2** | **El remitente desde el panel** (decisión 4) | ✅ **en el árbol** (§8) |
| **T3** | **El molde** — cabecera en tinta, chapa, resguardo, aviso, pie con dirección | ✅ **en los 21** (§9 · §10) |
| **T4** | **El mapa del naranja** — el botón de tinta y los dos que venden | ✅ **hecho dentro de la T3** (§9) |
| **T5** | **La BANDEJA** — las líneas de adelanto + los asuntos con el dato delante. ⚠️ Son **63**, no 69: los dos internos no pueden llevarla (§13.1) | ✅ **en el árbol** (§13) |
| ~~T6~~ | ~~El molde en los 19 restantes~~ | ✅ **hecho** (`#504`, §10) |
| — | **El correo de la fiesta mixta**, rehecho | turno propio |

⚠️ La T4 y la T3 tocan las mismas plantillas: si se hacen seguidas, se regenera una sola vez.

---

## 7 · T1 · El vestido — EJECUTADA (2026-09-10)

**Qué cambia**, y todo con la cuenta cuadrada antes de escribir (la regla del canvas: *«una
sustitución en bloque se cuadra antes de escribir»*):

- **41 sustituciones** en `brand.css` + **48** en las otras tres plantillas = **89**, con control de
  supervivientes en las dos direcciones.
- El tema vivo queda en **13 colores y todos son del sistema v1.10; cero ajenos** (verificado sin
  comentarios: los valores viejos solo sobreviven donde el propio comentario explica el cambio).
- **Ninguna webfont viaja**: las cinco familias del sistema salen del correo y entra el sustituto
  declarado (Arial/Helvetica), que es lo que el destinatario recibe de verdad.
- **Radios a la escala de cuatro** (`0 · 10 · 16 · 999`): tarjeta 14 → 16, botón 14 → 10, aviso 4 →
  10, libro y tarjeta de producto 12 → 16, el punto de marca 2 → 0.
- **Fuera la sombra** de la tarjeta y **fuera el filete de acento** del aviso (cromo de documentos).
- **El modo oscuro**, que es el hallazgo estrella del canvas: **cero valores nuevos**, sale entero de
  `semantico.tinta`.
- **La fuga de marca**: firma, copyright y `<title>` pasan a `Setting::businessName()`.

### 7.1 · ❗❗ Lo que enseñó la ejecución, con su medida

1. **Una regla que no se puede inlinear no puede vivir en el tema.** El modo oscuro se escribió
   primero en `brand.css` y **el HTML enviado salió sin una sola `@media (prefers-color-scheme)`**:
   Laravel pasa el tema por `CssToInlineStyles`, que pega cada regla a su etiqueta y **descarta lo
   que no puede inlinear**. Vive ahora en el `<style>` de `layout.blade.php`, que es el único sitio
   del correo donde una media query sobrevive (medido: sus dos `@media` de ancho sí llegan). Y **todo
   lleva `!important`** porque el tema ya se ha inlineado y gana. Sin eso no pinta nada y **no falla**.
2. **El botón de los 23 correos incumplía AA, y por el helper equivocado.** `onBrand()` prefiere
   blanco y decide con **3,0** —el umbral de texto GRANDE—; el rótulo mide **15 px con peso 700**, así
   que le toca **4,5**. Sobre el naranja del producto (`#FF5B22`) eso elegía blanco y daba **3,10**.
   ▶ El producto **ya tenía el helper correcto** —`onAction()`, que elige el que más contraste dé— y su
   propio docblock describía este defecto: lo había encontrado la guarda del relleno de acción **en la
   web**. Aquí nadie lo había mirado. *Un helper corregido no corrige a quien sigue llamando al viejo.*
   Medido con `onAction()`: tinta, **5,96** sobre `#FF5B22` y **6,30** sobre el Naranja Salto.
3. **«Una devolución no es un color: es un signo y una fecha»** (regla dura del canvas, su grieta 04)
   **tenía consumidor aquí**: el libro pintaba «a pagar» en ámbar 700, «a devolver» en ámbar 800 y «en
   revisión» en rojo 800 **de Tailwind**. Pasan a tinta, tinta y Rojo 800 del sistema. Los dos lados de
   los ternarios de color quedaron idénticos — y eso **es** la regla, así que se retiran y se escribe.
   ⚠️ **Lo que NO se tocó**: `settled` y `expired` siguen en Humo, que es el rol de lo inerte.
4. **La sustitución de fuentes se descuadró en el orden**: el stack de texto está CONTENIDO en el de
   titular, así que hacerlo primero se comió su parte y dejó `'Bricolage Grotesque',` colgando ×3. **El
   más largo va primero.** Lo cazó el control de supervivientes, no la lectura.
5. **Dos ediciones encadenadas dejaron un comentario cerrado dos veces** (`*/` en medio de la prosa),
   o sea PHP roto. Lo cazó releer el fichero, no el renderizado — que aún no se había corrido.
6. ⚠️ **Los comentarios rancios se limpian en la misma pasada.** La «grieta 05» que el canvas nos cazó
   era exactamente eso (un comentario afirmando un papel que ya no existía): ocho comentarios de
   `brand.css` describían la paleta vieja y habrían quedado igual de falsos.

### 7.1.bis · ❗❗❗ CORRECCIÓN — el modo oscuro dejaba el LIBRO INVISIBLE (`#502`)

Que la `@media` llegue al HTML **no es que el correo se vea**. El primer bloque seleccionaba por
ETIQUETA y CLASE (`body, p, .table td, h1…`) y los partials pintan su color inline sobre `<td>`,
`<div>` y `<span>` **sin clase**. Medido sobre el correo real:

| | antes | después |
|---|---|---|
| Elementos de texto sin cubrir | **19** | **1** (el punto de marca, que no tiene texto) |
| De ellos, invisibles (`1,12 : 1`) | **11** — los diez `<td>` del desglose de dinero y el título del producto | **0** |

▶ **El criterio de selección cambia: el texto se mapea POR SU COLOR.** La relación que de verdad
existe no es «este elemento es un párrafo», es *«este texto es TINTA»* y *«este texto es HUMO»* — y
eso es lo que dice el atributo. Tres pares: `#101418` → `#C9CDD1` (10,37) · `#626A72` → `#9AA1A8`
(6,35) · `#C83912` → `#FF8A6B` (7,18).

⚠️ **Y la suite pasaba en verde con el defecto puesto**, porque el único caso que renderizaba un
correo usaba una confirmación **sin líneas** — y sin líneas no hay libro ni tarjeta de producto: el
defecto no tenía sujeto.

**La guarda mordió a la primera con dos defectos que no buscaba**: los **dos correos internos** no se
habían vestido (`#b00020` de Material Design y un `#888` suelto), y el **producto CANCELADO** estaba
a **2,98** antes y a **2,61** después de mi cambio, los dos por debajo de cualquier umbral. Pasa a
Humo (5,49 · 6,35). ▶ *Lo que dice «cancelado» es el tachado y el rótulo, no un gris ilegible.*

### 7.1.ter · En qué se diferencia del mockup, medido

La pregunta es del owner y la respuesta es **un solo color**:

| lo que pide el canvas | en el correo enviado |
|---|---|
| Fondo `#F4F4F1` · tarjeta `#FFFFFF` · texto `#101418` · texto 2º `#626A72` | ✅ idénticos |
| Fuentes Arial/Helvetica · radios 10/16 · sin sombra · `light dark` | ✅ idénticos |
| Acento **`#F2711C`** (Naranja Salto) | ⚠️ sale **`#FF5B22`** |

▶ **Y eso no es una limitación: es el mecanismo funcionando.** El acento es lo único que DEBE venir
de la instalación, y lo inyecta `ThemeSettings::brand()`. En local **no hay paquete de cliente**, así
que devuelve el default del producto. En cuanto la instalación declare su color de marca en el panel,
coincide. ⚠️ El tema declara `#F2711C` como suelo, pero el inline gana — a propósito.

### 7.2 · La guarda

**`MailThemeTest`, reescrita de raíz — y su lección es la de este proyecto.** Cementaba `Space
Grotesk`, `border-radius: 14px` y `color: #FFFFFF` como «la identidad visual», y los tres eran del
tema de origen. *Una guarda que asevera literales protege la implementación, no la regla: al cambiar
el sistema se pone roja con el producto sano.*

Vigila ahora **propiedades**: que no vuelva la paleta de origen · que ninguna webfont viaje · que
todo radio esté en la escala de cuatro · que el modo oscuro llegue al HTML enviado · que las cuatro
superficies de marca digan el NEGOCIO · que el rótulo del botón **cumpla AA con la aritmética, no con
un hex escrito** · y el caso que faltaba: **el nombre del negocio existente pero VACÍO**.
**11 casos, 41 aserciones** — incluida la que censa la propiedad `color` en las TRECE fuentes del correo y exige que cada una tenga par oscuro o excepción con su motivo.

### 7.3 · Verificación

- Suite **4.591 ✓ · 28.747 aserciones** (6 skipped) · Pint **1.222 ficheros** ✓
- **Contraste calculado en los dos modos: 9 de 9 pares cumplen AA.** Oscuro: cuerpo 10,37 · titulares
  15,05 · texto 2º 6,35 y 7,08 · enlace 6,14. Claro: 18,50 · 5,49 · 4,98.
- Tres correos reales enviados a Mailpit (`:8028`) y leídos: confirmación, fiesta mixta y datos de
  invitados. Las tres superficies visibles dicen «SaltoPark»; el asunto también.
- ⚠️ **No se ha visto en un cliente de correo real.** El modo oscuro está verificado por aritmética y
  por presencia en el HTML, no por captura: no hay Playwright en el contenedor. **Falta el ojo del
  owner en Gmail y en Outlook.**

### 7.4 · ⚠️ Dos rojos que NO eran de esta tanda

Los dos salieron del `git pull` de **74 commits** que abrió la sesión, y los dos son la trampa ya
fichada de «un rojo que no es de nadie»:

- `SidebarDomContractTest` — el bundle SSR era del 05-09 y `pay.js` y `ProductIcon.vue` venían del
  pull. `npm run build:ssr`.
- `DudasSectionTest` — el bundle de la landing traía el `faqOpen: 0` viejo; el `-1` es de `#488`.
  `npm run build`.

---

## 8 · T2 · El remitente desde el panel — EJECUTADA (2026-09-10)

`[DECIDIDO owner]`: **que salga del PANEL, no del `.env`.** Antes: `config/mail.php` resuelve
`mail.from` con `env(...)`, **nadie lo sobreescribe** en todo el repo, y en local valía literalmente
`JumpWeb <hello@example.com>`. ▶ *Este hueco no falla hacia invisible: falla hacia ridículo.* Los
correos salen, y salen mal.

### 8.1 · Las tres piezas

| pieza | qué hace |
|---|---|
| `Setting::mailFromAddress()` | La dirección, con **doble caída**: el ajuste → la de `config` → `null`. Valida con `FILTER_VALIDATE_EMAIL` antes de aceptar |
| `Platform\Listeners\ApplyBusinessSender` | Escucha `MessageSending` y sustituye el remitente **por defecto** |
| El campo `mail.from_address` | En Ajustes → Contacto, con `->email()`, aviso de SPF/DKIM y un **placeholder con el valor efectivo** |

**El nombre NO necesita ajuste nuevo**: es `business.name`, que ya existía y ya es el nombre del
negocio. Reutilizar en vez de crear.

### 8.2 · Las cuatro decisiones de diseño, con su porqué

1. **Un LISTENER, no `Mail::alwaysFrom()`.** Éste, en `boot()`, obliga a consultar `settings` **en
   cada petición**, incluidas las que no envían nada. `MessageSending` solo se dispara cuando hay un
   correo de verdad, y también desde el worker de la cola, que es por donde salen casi todos.
2. **Solo se sustituye el remitente POR DEFECTO.** Cuando el evento se dispara, Laravel ya ha puesto
   el de `config`; se compara y solo se cambia si es exactamente ése. ⚠️ Hoy **ningún correo define
   `from` propio** (medido), así que no cambia nada — existe para el día que alguien lo ponga, que
   es justo cuando dejaría de ser evidente que se lo estaban pisando.
3. **Con VARIOS `From` no se toca nada**, porque `from()` **reemplaza**: mirar solo el primero y
   sustituirlo se llevaría a los demás **en silencio**.
4. **Degrada siempre hacia «no tocar».** *Un ajuste mal puesto no puede impedir que un correo salga.*
   El panel valida, pero un valor metido por consola se salta esa puerta.

⚠️ **Y el `Reply-To` no se toca.** `ContactMessageMail` pone el correo de quien escribe: si el
mecanismo lo pisara, el parque respondería al mensaje de un cliente **y le llegaría a sí mismo**.

### 8.3 · La guarda y su arnés

**`tests/Feature/Mail/OutgoingSenderTest.php`**, 8 casos · 26 aserciones · **8/8 mutaciones muerden**
(`scripts/mutar-correos-t2.py`).

⚠️⚠️ **`Mail::fake()` NO SIRVE AQUÍ**, y es la trampa de este fichero: intercepta antes de construir
el mensaje, así que **`MessageSending` no se dispara** y el caso saldría VERDE con el mecanismo
desconectado. La suite envía con el transporte `array`, que sí recorre el camino entero.

⚠️ **El arnés NO usa `git checkout`**, a diferencia de los demás del repo: aquéllos exigen el árbol
commiteado y un descuido se lleva el trabajo sin commitear (`#181`, pagado otra vez en `#317`). Éste
copia y restaura, así que corre con el árbol sucio. Va en Python porque las mutaciones son
expresiones sobre texto y pasarlas por la línea de órdenes convierte cada comilla en un problema.

▶ **Y el arnés pidió un caso que el diseño no tenía**: la mutación «deja de rendirse cuando hay
varios `From`» **sobrevivía**, o sea que esa rama no la vigilaba nadie. El caso se escribió después.

### 8.4 · Verificación

- Suite **4.590 ✓ · 28.738 aserciones** (+8 casos) · Pint 1.222 ficheros ✓
- **Demostrado en vivo en Mailpit**: antes `SaltoPark <hello@example.com>`, después
  `SaltoPark <reservas@playjump.es>`. ⚠️ Nótese que **el nombre ya era el del negocio antes de poner
  el ajuste**: lo pone el mecanismo siempre, y lo que el ajuste añade es la dirección.
- El valor de demo **se retiró de la BD local**: el campo queda vacío, con su placeholder diciendo
  qué sale hoy. La dirección es del owner.

### 8.5 · ❗ Paso de despliegue

**Poner el remitente en Ajustes → Contacto**, en cada instalación. Sin él los correos salen con la
dirección del `.env` del servidor. ⚠️ Y tiene que ser **una dirección del dominio que firma con
SPF/DKIM**, o acaban en spam — eso el código no lo puede saber, y por eso el aviso vive en el campo.

---

## 9 · T3 · EL MOLDE — EJECUTADA en los cuatro correos dibujados (2026-09-10, `#503`)

### 9.1 · ❗❗❗ Por qué hizo falta: el vestido no acerca la ESTRUCTURA

El owner miró los correos vestidos y dijo que **no se parecían al mockup**: *«faltan badges, cards,
elementos, los botones son diferentes, el mockup no usa emojis»*. Tenía razón, y el fallo era doble:

- **De fondo**: la T1 verificó el vestido —16 valores— y dio por bueno el resultado **sin medir la
  estructura**. Es exactamente lo que el canvas advierte: *«copiar una pieza del sistema es copiar
  sus ESTADOS, no solo sus colores»*.
- **De comunicación**: se le presentaron los correos como resultado **sin decirle que el molde
  seguía siendo el viejo**. Un correo con los colores del canvas y la estructura de antes no se
  parece al mockup, y eso tenía que haberlo dicho el agente antes de que él lo viera.

⚠️ **Y lo que estaba mirando ni siquiera era eso**: los tres correos vestidos que se le habían dejado
en Mailpit **los borró la propia sonda** —el `DELETE` que limpia el buzón antes de demostrar el
remitente—, así que abrió dos `Mail::raw('x')` de prueba. *Una sonda que limpia el buzón se lleva por
delante lo que el owner tenía que mirar.*

### 9.2 · El inventario, pieza a pieza: faltaban SIETE de doce

| pieza del mockup | antes | ahora |
|---|---|---|
| **CABECERA en tinta** | ❌ | ✅ |
| **CHAPA / badge** | ❌ | ✅ píldora de estado, cuatro tonos |
| **RESGUARDO** (cuándo · qué · dónde · pedido) | ❌ | ✅ |
| **AVISO** con título y punto | ❌ | ✅ |
| **Dirección y teléfono en el pie** | ❌ | ✅ |
| **Botón** | naranja en los 21 | ✅ tinta; naranja solo en los DOS que venden |
| **Emojis** | 🎂 | ✅ ninguno |
| Titular | fuera de la caja, 28 px | ✅ dentro, 26 |
| Tarjeta duplicando el resguardo | sí | ✅ retirada donde hay resguardo |
| Logotipo · tarjeta blanca · cuerpo | ✅ | ✅ |
| **«LAS CUENTAS» (3 líneas)** | libro entero | ⚠️ **divergencia declarada** (§9.5) |

⚠️ Una fila del inventario salió **falso positivo** —«cabecera en tinta ✅»— porque el patrón cazaba
`#101418` como color de TEXTO: la única aparición como fondo estaba dentro del bloque de modo oscuro.
*Se comprobó antes de escribirlo.*

### 9.3 · Lo construido

| pieza | qué |
|---|---|
| `vendor/mail/html/hero.blade.php` | chapa (4 tonos) + titular + resguardo, en caja de tinta. **Con TABLAS**: en un correo `flex` no existe |
| `vendor/mail/html/notice.blade.php` | el aviso con su punto. Sustituye al filete de acento del `.panel` de Laravel |
| `Booking\Services\EmailSlip` | el compositor ÚNICO del resguardo, con rótulos compartidos en `emails.slip` |
| `footer.blade.php` | dirección y teléfono, del panel, cada línea solo si su dato existe |
| `button.blade.php` | tinta por defecto; `accion` para los dos que venden. 56 de alto, 16/800 |
| `product-card` | pierde el emoji, y desaparece de los correos que ya tienen resguardo |

### 9.4 · ❗❗ Tres cosas que enseñó construirlo

1. **Todo componente de correo nace por partida doble.** El `hero` renderizaba perfecto y **el envío
   reventaba** con «View not found»: Laravel compone también la versión en texto plano y busca cada
   componente en su carpeta. `->render()` solo produce el HTML, **así que un componente sin gemelo
   pasa todos los casos que renderizan**. Guarda nueva.
2. **Los comentarios CSS viajan en cada correo y los de Blade no.** Los del `<style>` del molde
   pesaban **3.172 bytes, el 11 % del HTML**, en cada envío. Pasan a `{{-- --}}`.
3. ⚠️ **Un `*/` dentro de un docblock lo CIERRA.** Escribir la ruta comodín de los ficheros de idioma
   en un comentario dejó `EmailSlip.php` sin compilar, con un «unexpected token» que señalaba al
   comentario y **un render que devolvía el HTML anterior sin avisar**.

### 9.5 · `[DECIDIDO owner]` · El libro del pedido se queda ENTERO

El artboard resume el dinero a tres líneas. El libro es una feature con invariantes propias
(`#305`→`#317`) donde cada gestión es una línea con su fecha; resumirlo perdería el historial justo
en el caso en que se reclama —un cumpleaños con cambios—. **Es la única divergencia declarada con el
mockup.**

### 9.6 · ⚠️ Cuatro casos ajenos perdieron su sujeto, y ninguno se re-apunta más débil

Tres cementaban el emoji y la tarjeta; pasan a aseverar **el dato** —que el correo diga qué se
compró— y **ganan** la aserción de que no hay emojis. El cuarto aseveraba `introLines` y el texto de
los extras se mudó a su aviso: pasa a aseverar sobre el **HTML renderizado**.
▶ *Un caso que asevera el CONTENEDOR se rompe cuando el texto cambia de sitio; uno que asevera lo que
el cliente LEE, no.* ⚠️ Y uno dio falso positivo por SUBCADENA («product-card» aparece en un
comentario del `<style>`): se acota a `class="product-card"`.

### 9.7 · Verificación

- Suite **4.593 ✓ · 28.752 aserciones** · Pint 1.223 · `MailThemeTest` **12 casos**
- Los **cuatro** correos enviados y leídos en Mailpit
- ⚠️ **Sigue sin verse en un cliente de correo real**: falta el ojo del owner en Gmail y Outlook

---

## 10 · El molde en los VEINTIUNO (2026-09-10, `#504`)

### 10.1 · El censo primero, y cambió el plan

Los 23 **no son homogéneos**, y sin medirlo el trabajo habría sido «poner resguardo a los 21» —con
ocho saliendo con una caja vacía—:

| grupo | cuántos | qué lleva |
|---|---|---|
| **A · con reserva** (`Order`/`OrderItem`) | **9** | cabecera **con resguardo** |
| **B · de cuenta** | **8** | cabecera **sin resguardo**: ahí no hay reserva que resumir |
| **C · internos al parque** | **2** | ⚠️ **fuera del molde, con ficha**: vista propia, no `MailMessage` |

### 10.2 · ❗❗❗ El molde deja de ser una convención y pasa a ser un TIPO

`Notifications\Support\BrandedMailMessage`, con `->hero()` y `->notice()` encadenables.

- ⚠️ Se intentó primero por **macro** y **`MailMessage` no es `Macroable`** (comprobado en el
  framework). Encadenable importa: sin ello cada notificación tendría que romper su
  `return (new MailMessage)->…` en dos.
- ⚠️⚠️ **La chapa y el titular se derivan del GRUPO del diccionario**, no se pasan sueltos:
  `emails.order_cancelled` da `.badge` y `.headline`. *Con dos parámetros se pueden desparejar;
  con una convención, no.*
- ▶ `hero()` **anula el saludo**, además de haberlo retirado de los 21: un `->greeting()` escrito
  después devolvería dos aperturas al correo.

**Un quinto tono, y sale de una regla dura**: `neutro`, para las dos DEVOLUCIONES. *«Una devolución
no es un color: es un signo y una fecha»*. Su chapa no se tiñe. Los cinco medidos: **7,08 · 9,39 ·
7,18 · 5,59 · 16,79**.

### 10.3 · La sustitución fue de UNA línea por correo, y eso fue la medida previa

Los 21 tenían `->greeting(__('X.greeting'))`, así que `->hero('X', 'tono', $resguardo)` entra
exactamente en su sitio. **34 claves nuevas en tres idiomas (51 inserciones)**, escritas derivando de
los asuntos que ya existían para que el owner las corrija **sobre algo, no sobre un hueco**.

### 10.4 · ❗❗ La verificación no es la guarda estática: son los 21 RENDERIZADOS

`MailMoldTest` lee las fuentes —exhaustivo por definición, y por eso vigila también que ninguno
vuelva a componer `viewData['hero']` a mano ni a usar `new MailMessage`—. Pero *que un correo DECLARE
la cabecera no es que la pinte*: se montó el fixture de cada uno y se renderizaron.

> **21 de 21 con `class="hero"`, y ninguno conserva el saludo.**

Los tres últimos —firma del justificante, extras del post-form e identidad social— necesitaron
fixtures propios y **se construyeron en vez de darlos por buenos**.

⚠️ Y el mapa del naranja es ejecutable: la guarda vigila **las dos direcciones** —que ningún tercero
se apunte y que ninguno de los dos se caiga—, porque apagar el mapa entero es el defecto simétrico y
se ve igual de poco.

### 10.5 · Verificación

- Suite **4.598 ✓ · 28.758 aserciones** · Pint 1.225 · `MailMoldTest` 5 casos
- **21 de 21 renderizados** · nueve correos leídos en Mailpit
- ⚠️ **Sigue sin verse en un cliente de correo real**

---

## 11 · El AVISO era ilegible en oscuro, y la guarda estaba hecha a medias (`#505`)

**Lo vio el owner**: *«revisa los contrastes de las cards claras, no se lee el texto»*. Medido:

> **`#C9CDD1` sobre `#D5EAEE` → 1,28 : 1.** El aviso lleva un tinte CLARO fijo y el mapa oscuro
> subía su texto a Papel 200: texto claro sobre fondo claro, la caja entera ilegible.

### 11.1 · ❗❗❗ La causa de fondo: el mapa miraba UN SOLO LADO

`#502` construyó el mapa por color y su guarda exigía que **todo color de TEXTO tuviera par**. Pero
**el contraste lo hacen los dos lados**: un fondo sin par oscuro deja el texto invertido encima de
él. La guarda pasaba en verde con la caja ilegible. ▶ *Un mapa de color que solo mira el texto está
hecho a medias, y su guarda hereda el agujero.*

El arreglo son **los cuatro pares que el sistema ya tenía** (`tintePapel` ↔ `tinte`), sin un valor
nuevo. Medido después: **9,45 · 9,04 · 9,66 · 10,37**.

### 11.2 · ⚠️ Y al escribir la guarda buena salió un segundo defecto: DOS mecanismos

Las superficies principales se invertían **por CLASE** y todo lo demás **por COLOR**. La guarda solo
sabía leer el segundo, así que daba por cubiertos fondos que sí lo estaban — **y habría dejado pasar
los que no**. Hoy el mapa entero va por color: *el color claro dice cuál es su par oscuro, sea texto,
fondo o borde.*

### 11.3 · La guarda mide el RESULTADO, no la presencia

`test_every_text_reads_in_both_modes` recorre el HTML con una **pila de fondos** —el fondo de un
texto es el de su ancestro más cercano que declare uno—, aplica el mapa a los dos lados y exige AA en
los dos modos. ⚠️ **La pila es lo que la hace servir**: medir contra el fondo de la página es
exactamente cómo un texto ilegible pasa por bueno.

### 11.4 · Verificación

- Suite **4.599 ✓ · 28.760** · `MailThemeTest` 13 casos
- Auditoría **externa e independiente del test**, cinco correos con los cinco tonos:
  **176 textos medidos con su fondo efectivo · 0 por debajo de AA en los dos modos**

---

## 12 · Deuda que esta tanda deja dicha

1. **El paquete de una instalación no llega al correo** (§2). Si un cliente necesita sus superficies,
   hace falta un hueco que no existe. Declarado desde `#102` y sin construir.
2. **`ThemeSettings` conserva dos literales del primer cliente**: `brand()` devuelve `#FF5B22` y
   `onAction()`/`onBrand()` devuelven `#14130F`. Por eso el rótulo del botón del correo sale en
   `#14130F` mientras el resto del correo va en `#101418` — **dos negros**, invisibles a la vista pero
   incoherentes. Tocarlos mueve el botón de TODA la web (`#209`, `ActionFillTest`): no se hace desde
   este carril.
3. **`lang/zh_CN/` no tiene `emails.php`** (§3.1·3).
4. **`.panel` no lo usa ningún correo** (medido: cero `->panel()`), así que las cuatro superficies de
   aviso de v1.10 **no se declararon**: una pieza nace con su consumidor.
5. **El remitente sigue en el `.env`** y en local vale literalmente `hello@example.com`. Es la T2.

---

## 13 · T5 · La BANDEJA — EJECUTADA (2026-09-10, `#506`)

Lo que se lee en la lista de un cliente de correo son **dos cosas juntas** —el asunto y la línea de
adelanto— y hasta esta tanda **ninguna de las dos hacía su trabajo**. Medido antes de tocar nada:

| | antes | después |
|---|---|---|
| Líneas de adelanto | **0 de 63** (21 correos × 3 idiomas) | **63 de 63** |
| Longitud media del asunto | 44 caracteres | **33** |
| Asuntos que pasan del corte de 35 de un móvil | **18 de 21** | **7** |
| Asuntos cuyo dato cabe en ese corte | **1 de 13** | **9 de 14** |

⚠️ **El corte de ~35 caracteres es una premisa del canvas, no una medición nuestra**: desde aquí no
hay forma de medir lo que recorta cada cliente de correo. Se adopta como criterio declarado.

### 13.1 · ❗❗ La cuenta del canvas —y la de esta spec— eran 69, y son **63**

`doc/correos.md` habla de 23 líneas y §3.1·2 de este documento corrigió a 69 (23 × 3). Al
construirlo salió la tercera cifra, y es la buena: **los dos correos internos al parque no pueden
llevarla**, y no por decisión sino **por construcción** — `ContactMessageMail` y
`PaymentIncidentMail` usan **vistas HTML sueltas** (`emails/contact.blade.php`,
`emails/payment-incident.blade.php`), no el `layout` de `vendor/mail`, que es donde vive el
mecanismo. Quedan fuera con el resto del molde (§10.1, grupo C).

### 13.2 · ❗❗❗ La línea va en el `layout` y ANTES de la cabecera

Lo primero que lee un gestor de correo es **lo primero del DOCUMENTO**, y encima del cuerpo va la
cabecera, cuyo logotipo lleva `alt="{nombre del negocio}"`. Puesta en el cuerpo —que es donde la
pondría cualquiera— la bandeja leería «SaltoPark» y **luego** la frase.

- **Se deriva del grupo del diccionario**, como `.badge` y `.headline` (§10.2): los 21 ya llaman a
  `hero()`, así que entra en los veintiuno **sin tocar una notificación** y no se puede olvidar en
  uno.
- **Sólo si la clave existe.** `__()` devuelve la clave cuando no la encuentra, así que sin esa
  puerta un grupo sin escribir anunciaría el correo con el texto `emails.order_cancelled.preheader`.
  ▶ Sin clave, el correo sale **sin** línea: falla hacia invisible, no hacia feo.
- **Seis declaraciones para esconder un `<div>`**, y no es cinturón y tirantes: ningún cliente de
  correo las respeta todas y basta con que respete una. Va **inline**, porque `CssToInlineStyles`
  descarta lo que no puede pegar a una etiqueta —la misma regla que obliga al modo oscuro a vivir en
  el `<style>` de `layout`—.
- **El relleno no es adorno.** Sin él el gestor pinta la línea y **sigue leyendo el cuerpo detrás**.
  Medido: **83 caracteres de línea + 168 de relleno invisible** antes de que asome el cuerpo; Gmail
  previsualiza ~100.
- **No viaja en la parte de texto plano**, a propósito: allí no hay bandeja a la que adelantarse y
  repetiría la primera frase. Se declara en `text/message.blade.php` **para ignorarla**, que es la
  forma de dejar escrito que es deliberado.

### 13.3 · La línea de adelanto **no lleva datos variables**, y es una regla

Dos motivos, y el segundo es el que duele: el asunto ya lleva el dato delante, así que repetirlo
desperdicia la única frase que puede **completarlo**; y `hero()` la resuelve sin reemplazos, de modo
que un `:code` escrito ahí **saldría literal en la bandeja de un cliente sin que nada fallara**.

### 13.4 · Los asuntos · `[DECIDIDO owner, 2026-09-10]`

Dos decisiones, las dos con su medición delante:

1. **El nombre del parque sale del asunto.** Cinco de los 21 lo repetían y gastaban ~13 caracteres
   del corte; desde `#501` **el remitente lo dice, en la misma línea de la bandeja**.
2. **La fecha entra sólo si el pedido tiene una.** `Order::singleVisitDate()` devuelve `null` cuando
   hay reservas en días distintos —`#401` lo cazó en un pedido REAL: «Días de la visita: 03/09 ·
   07/09»— y entonces se usa la redacción sin fecha. ▶ *En la bandeja no hay cuerpo debajo que
   matice un día que no es el único.* Es la misma pareja de claves que el titular ya tenía
   (`headline` / `headline_no_date`), y la regla que ya aplican `EmailSlip` y la entradilla de `#487`:
   **el dato se publica cuando es cierto, y si no la frase encoge**.

⚠️ **Esa derivación gobierna también el TITULAR**, que hasta hoy afirmaba «Nos vemos el sábado 4» en
un pedido de dos días. Una sola fuente para los dos, o pueden contradecirse.

⚠️ **Dos formatos de la misma fecha, y es deliberado**: `dayLabel()` en el asunto —«Sáb. 4 oct.», se
lee como un DATO y **lleva el mes**, que en una bandeja hace falta— y `dayInSentence()` en el
titular —«sábado 4», que va dentro de una oración—. Lo dicen sus propios docblocks.

⚠️ La fecha entra en **dos** de los 21, no en todos: la confirmación y los datos de invitados, que
son donde el CUÁNDO es lo que se busca. En una cancelación o una devolución lo que se busca es qué
pasó y de qué pedido; meterle fecha infla el asunto sin ganar nada.

### 13.5 · ❗❗❗ DOS defectos vivos encontrados al medir, los dos visibles para el cliente

**(1) `#504` dejó la cabecera del correo de identidad social diciendo el nombre de su clave.**
`badge` y `headline` estaban anidadas **dentro de `providers`** —que es el mapa proveedor→nombre—,
así que `__('account.social_link_mail.badge')` no las encontraba. Renderizado antes del arreglo:

> la chapa del correo decía literalmente **«account.social_link_mail.badge»**, en los tres idiomas.

▶ Y `MailMoldTest` estaba en verde, porque comprueba que el correo **declara** `class="hero"`.
*Declarar una pieza no es que diga algo* — la tercera vez en este carril que la guarda mide
presencia y no resultado (`#502`, `#505`, y ésta).

**(2) `#500` arregló las cuatro superficies de marca en HTML y dejó la parte de TEXTO PLANO firmando
con el nombre del PRODUCTO.** Medido sobre el correo enviado: **2 ocurrencias** de «JumpWeb» —el
copyright y la cabecera— con el negocio llamándose otra cosa. Es la trampa ya fichada de esta casa,
por la otra puerta: **todo componente de correo nace por partida doble**, y `text/message.blade.php`
sí usa el slot del header que su gemelo HTML ignora. Hoy **0 ocurrencias**.

### 13.6 · La guarda y su arnés

**`MailInboxLineTest`** (9 casos) censa la familia **desde la fuente** —`->hero('<grupo>')`—, no de
una lista escrita a mano que envejecería en silencio. Cubre lo que ninguna guarda anterior veía:

- las cuatro piezas de bandeja existen **en los tres idiomas** (habría cazado `#504`);
- **todo `:placeholder` del asunto lo pasa su notificación**, buscándolo **dentro** de la llamada
  `__()` con los paréntesis balanceados. ⚠️ Acotar es lo que lo hace servir: mirar «¿aparece
  `'code' =>` en el fichero?» pasa en verde, porque esa notificación sí lo pasa… en otra línea y a
  otra clave;
- la línea va antes de la cabecera **con control** de que la cabecera existe, no lleva datos, cabe en
  la previsualización, no se cuela en el texto plano, y **un correo sin línea escrita sale sin línea**.

**`scripts/mutar-bandeja.py` · 9/9 mutaciones mueren**, dos de ellas reproduciendo los defectos
reales de §13.5.

⚠️⚠️ **El arnés destapó DOS agujeros de la propia guarda, y ninguno se veía leyéndola**:

1. **`Lang::has($clave, $locale)` cae al idioma de RESPALDO por defecto.** Una clave que faltara en
   español pero estuviera en inglés **pasaba en verde**, y el correo saldría en el idioma
   equivocado sin fallar. Es el tercer parámetro, `false`.
2. **La puerta de «sólo si la clave existe» no tenía SUJETO**: los 21 la tienen, así que quitarla no
   cambiaba nada. Nació con su caso —un grupo que no existe— y ahora la mutación muerde.

⚠️ **Y el arnés nació roto**, con la trampa de siempre: escrito en `bash` con heredocs anidados, el
escapado de `$` se perdía y **cinco mutaciones «sobrevivieron» sin haberse aplicado nunca**. Está
reescrito en Python y **cada mutación verifica que el fichero cambió** antes de correr un solo caso.
*Cuando un instrumento dice que nada funciona, la primera hipótesis es el instrumento.*

### 13.7 · Un caso ajeno cambió de premisa y se REESCRIBIÓ

`GuestFormTest` aseveraba que el asunto del post-form **nombra el producto**, y ahora nombra el día.
No es una relajación y es un discriminante mejor: **dos cumpleaños del mismo cliente comparten
producto y no comparten día**, así que en la bandeja el nombre no separaba y la fecha sí. Y queda
**más fuerte** que antes: asevera las dos ramas, con franja y sin ella.

### 13.8 · Verificación

- Suite **4.634 ✓ · 28.969 aserciones** · Pint 1.245 · `MailInboxLineTest` 9 casos
- **21 de 21 renderizados**: línea de adelanto presente, **antes de la cabecera** en los 21, y
  **cero placeholders crudos** en los asuntos
- **7 correos enviados y leídos en Mailpit**: lo que se previsualiza bajo el asunto es la línea de
  adelanto, no el saludo
- ⚠️ **Sigue sin verse en un cliente de correo real** (Gmail, Outlook): no hay Playwright en el
  contenedor. Es el mismo pendiente que dejaron `#500`→`#505`.

---

## 14 · Deuda que deja la T5

1. **Los dos correos internos al parque no llevan línea de adelanto** (§13.1), y es estructural:
   usan vistas HTML sueltas, no el `layout` de `vendor/mail`. Darles una obliga a repetir el
   mecanismo en dos sitios; los lee un operador en una bandeja con pocos correos.
2. ⚠️ **`EmailSlip::forOrder()` sigue componiéndose sobre la PRIMERA reserva viva**, así que en un
   pedido de dos días el resguardo dice un día. Está declarado en su docblock desde `#503` y **no
   se tocó a propósito**: eso es CUERPO —va junto al desglose, que lista todas las reservas— y la
   T5 acota a la BANDEJA, donde no hay nada que matice. `Order::singleVisitDate()` ya existe para
   el día que se quiera cerrar.
3. **El corte de ~35 caracteres es una premisa del canvas**, no una medición nuestra: desde aquí no
   se puede medir lo que recorta cada cliente de correo.
4. **Sigue sin verse en Gmail ni en Outlook** — y ahí es donde de verdad se comprueba si la línea
   de adelanto se lee y si el relleno tapa el cuerpo.
