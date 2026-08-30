# [SPEC] El tema por instalación — los MECANISMOS que al paquete de un cliente le faltan

> Estado: 🟦 **LOS CUATRO MECANISMOS EN `main`** (`#192` → `#196`) · Última actualización:
> 2026-08-27 · Decisiones: `#192` (tanda 1, color y superficie) · `#193` (2a, la forma) ·
> `#194` (2b pasos 1-2, el hero declara superficie) · `#195` (2b paso 3, la estructura del hero) ·
> `#196` (la elevación). **El número se fija al EMPUJAR**, mirando el remoto — `CONVENCIONES §10`.
>
> ❗❗ **Sigue 🟦 por UNA sola cosa, y es del owner: su pasada de NAVEGADOR.** Se ha acumulado
> cambio visual sin que nadie lo mire, y la suite comprueba que cada token vale lo que debe — **no
> que el resultado guste**:
>
> | | Qué falta mirar | Estado |
> |---|---|---|
> | `#192` | la tanda 1 no movía un píxel, y se verificó regla a regla | ✅ verificado |
> | `#193` | las **11 declaraciones de canto** que se mueven (§10.2) y la **tira del pie** | ✅ **el owner lo validó**: «la tira está ahí y está correcta, las esquinas redondeadas» |
> | `#194` | que el hero se viera **igual** tras declarar superficie | ✅ **el owner lo validó**: «el hero está como estaba antes» |
> | `#195` | **el hero entero**: pierde su CTA, gana un eslogan, es una tarjeta y **encoge al bajar** | ⬜ **nadie lo ha visto** |
> | `#196` | **19 elementos pierden su sombra** — cambia el aspecto de media web | ⬜ **nadie lo ha visto** |
>
> ⚠️ Y una razón concreta para no dar por buena la aritmética: **§11.4 es la prueba de que un
> barrido incompleto puede jurar que todo está bien.** Tres conversiones a medias pasaron la suite.
>
> ⚠️⚠️ **DOS SECCIONES DE ESTE DOCUMENTO ESTÁN CADUCADAS Y LA CORRECCIÓN VA ANTES QUE EL TEXTO:**
> · **§1.2** — la premisa «el sistema alterna dos superficies, nunca dos papeles seguidos»
>   **YA NO ES CIERTA**. La norma vigente del cliente es **papel continuo de arriba abajo**, y el
>   contraste lo dan las TARJETAS (su hallazgo `S-00`, severidad Alta, ya aplicado). Verificado en el
>   canvas: **cero** fondos a sangre en los 218 KB del mockup. Buena noticia: el mecanismo de la
>   tanda 1 no se pierde, **se usa más** — pasa de la sección a la tarjeta.
> · **§7** — el plan de tandas se rehízo: la 2 se parte en **2a** (hecha), **2b** (hecha), **2c**
>   (el menú → **`armazon-y-menu.md`, spec propia YA ESCRITA**) y **2d** (el movimiento).
>
> ⚠️ **Y antes de leer nada del canvas, comprueba que tu copia no está vieja.** La de
> `mockup_playjumppark/` estaba **caducada** al empezar la 2a —386 líneas de diff, y tocaban el
> hero—, y `Colores de Marca PJP` también. Una copia vieja se lee igual de bien que una fresca y no
> avisa. Receta de re-bajada en §1.
>
> ⚠️⚠️ **Empieza por §1.7 si vas a la 2b, y por §10 si quieres saber cómo quedó la forma.** §1.7 es
> la medida que cambia el tamaño de todo lo demás: el CSS del producto ya está tokenizado, así que
> **el 45 % de los usos de `var()` se invierten solos**.
>
> ⚠️ Esta spec es hermana de `landing-white-label.md` §4.5, **no la sustituye**: aquélla decidió que
> el tema son TRES mecanismos (valores→panel · ficheros→assets · dibujos→iconos) y ejecutó la tanda
> A. Ésta cubre lo que la tanda A dio por supuesto y **no existe**: que el producto sepa pintar una
> sección sobre fondo oscuro, que tenga dos grises, una escala de forma y fuentes por instalación.

---

## 1. Contexto y problema — MEDIDO contra el código, no supuesto

El owner ha cerrado el sistema visual del **segundo cliente** en su canvas de diseño. Al cruzarlo con
nuestro CSS aparecen **cinco huecos**, y ninguno es de paleta: son de mecanismo. Todas las cifras de
abajo salen de un instrumento con guarda de la guarda —caza sus cuatro ejemplos, rechaza tres
no-colores y avisa si deja de ver el corpus—, porque en este repo un inventario hecho con un `grep`
ingenuo ya ha salido mal **cuatro veces** (`DECISIONES #143` §8).

#### ⚠️ Cómo leer el canvas, y por qué no está en el repo

**No puede estar** (`DECISIONES #1`: este repo es el PRODUCTO, sin marca de nadie). Se baja a
`mockup_playjumppark/`, que está **gitignorada y excluida del `rsync --delete`** por el mismo motivo
que `public/css/client.css` — así que **en tu máquina puede no existir**. Para regenerarla, con el
MCP **`DesignSync`** (no vale `WebFetch`: da 403):

    method=list_files · projectId=8c37d2d2-7e9c-43a9-bc25-aacb6607f2ad
    method=get_file   · path=<cada ruta>   → {path, content, isBase64, truncated}

⚠️ **Tres avisos que cuestan una tarde si no se leen**:
1. **Los resultados llegan por DOS caminos** —los grandes persistidos a fichero, los pequeños
   inline— y hay que barrer los dos, o el inventario parece completo sin serlo.
2. **`truncated` importa**: el MCP corta los binarios a 256 KiB. Medido: `assets/logo.png` llega
   **inservible** (36 % de sus líneas, sin `IEND`) y las dos fotos de fachada, usables por ser JPEG
   progresivos. **No bloquea nada**: el logotipo del cliente **no es una imagen** (§1.6).
3. ❗❗ **10 de sus artboards están SIN MIGRAR** —llevan la paleta anterior (`#2FB6DE`, `#0E8FCB`,
   `#8DC63F`) y las fuentes anteriores (Anton, Space Grotesk)—, y entre ellos están precisamente los
   de logotipo, menú y hero. **Los mismos elementos están rehechos con el sistema vigente dentro de
   `Landing PJP Modos`**. De los sin migrar se saca la FORMA; el color, jamás.
4. ❗❗ **Y la copia local CADUCA sin avisar.** Medido el 2026-08-27 por la mañana: la copia de las
   07:30 ya no valía —`Landing PJP Modos` con **386 líneas de diff** (el hero gana una tira de
   colores; la sección de entradas pierde su fondo cian y sus goterones) y `Colores de Marca PJP`
   también movido—. **`list_files` + diff ANTES de implementar nada**, siempre.

| Normativos (sistema vigente) | Sin migrar (exploración) |
|---|---|
| `Colores de Marca PJP` · `Landing PJP Modos` · `Auditoría Landing PJP` · `Iconos PJP` · `Microanimaciones PJP` · `Precios PJP variantes` · `Zonas PJP variantes` · `Cabecera Seccion variantes` · 🆕 `Descubre-el-Parque` · 🆕 `Recorrido-Parque` | `Logotipo variantes` · `Menu PJP` · `Hero PJP variantes` · `Info PJP variantes` · `Boton Reservar variantes` · `App PJP` · `Elementos Fachada` · `Marquesina Castillo` · `Salta la Ciudad` · `Tag Lorca` |

🆕 **Los dos artboards nuevos (2026-08-27) son DOS VARIANTES DE LA MISMA SECCIÓN**, la «02 · El
parque» de la landing, las dos con el sistema vigente: `Descubre-el-Parque` es un mapa de 3 parcelas
con 20 chinchetas y ficha lateral; `Recorrido-Parque` es un carril de 4 paradas con scroll-snap.
❗ **Cuál se queda está SIN DECIDIR** (`[PENDIENTE: owner]`, preguntado el 2026-08-27) — no se
implementa ninguna hasta que lo diga, y en todo caso es tanda 3.
⚠️ **Y estrenan `#E6007E` (Magenta Chispa) como acento de «la plaza»**: está en `Colores de Marca`
pero **no aparecía ni una vez** en `Landing PJP Modos`. Es uno de los cuatro colores del sistema que
no tienen token en el producto (§ la nota de `ESTADO.md` sobre Lima Bote, Azul Muro y Magenta).

### 1.1 Lo que la tanda A dejó hecho, y por qué no basta

Hecho y funcionando: el tema **se inyecta desde BD** (`ThemeSettings::cssRootDeclarations()` en
`<style id="jj-theme">`), el acento de zona ya no viaja por el nombre de la clase, **el hueco del
paquete del cliente existe** (`public/css/client.css`, tres piezas aseveradas por
`ClientThemePackageTest`) y el dibujo del spinner es sustituible (`SpinnerTest`).

▶ **Lo que falta no son tokens: es que el producto no sabe hacer las cosas que el paquete necesita
pedirle.** Un cliente puede hoy cambiar el color de marca y la hoja entera le obedece; **no** puede
pedir una sección oscura, ni un segundo gris, ni su tipografía.

### 1.2 Los dos fondos de sección son un **MODO**, y no existe

> ### ❗❗ LEE ESTO ANTES QUE EL PÁRRAFO DE ABAJO — la premisa de esta sección CADUCÓ
>
> **`[DECIDIDO owner, 2026-08-27]`, y lo dice su propia auditoría, no nosotros.** El hallazgo
> **`S-00`** de `Auditoría Landing PJP` —severidad **Alta**, estado **Aplicado**— dice, literal:
>
> > «Regla nueva: el fondo de una sección nunca lleva color. […] Decisión de cliente en revisión,
> > y **pasa a ser norma del sistema por encima del orden 01–08**. Papel continuo de arriba abajo.
> > El contraste lo dan las tarjetas —tinta para tarifas y packs, blanca para normas y opiniones,
> > de color para la zona activa—. **Ni negro ni cian a sangre en ninguna sección.**»
>
> Los dos intentos de alternancia por fondo están marcados **Revertido**: `S-01` (entradas en cian
> pleno) y `S-02` (cumpleaños en tinta, «el muro negro dejaba una franja de 130 px encima del
> titular que no se sostenía»).
>
> ▶ **Verificado en el mockup, no creído**: en los 218 KB de `Landing PJP Modos` hay **cero**
> `calc(50% - 50vw)` y **cero** `width:100vw`. Antes había tres. Ninguna sección va a sangre.
>
> ▶ **Y esto NO deja inútil el mecanismo de la tanda 1 — lo hace más útil.** `[data-surface]` es un
> selector de atributo, no está atado a `.section`, así que vale igual para una **tarjeta** de tinta
> dentro de una sección de papel. Pasa de usarse dos veces (hero y cierre) a usarse en cada tarjeta
> oscura, que es justo lo que la norma nueva pide. Lo que cambia no es el mecanismo: es **dónde se
> cuelga el atributo**.
>
> ⚠️ **La regla «nunca dos papeles seguidos» ya NO existe.** Si la lees abajo, está muerta.

El sistema del cliente alterna dos superficies —**tinta** `#101418` y **papel** `#F4F4F1`— con la
regla «nunca dos papeles seguidos», más una excepción de color pleno una vez por página.

⚠️ **Medido: en `public/css/*.css` no hay ni un mecanismo de superficie.** Un barrido de
`--dark`, `--invert`, `.dark` y `section--` devuelve **0**. La única superficie oscura del producto
es el hero, y se resuelve con un sufijo ad-hoc `--onvideo` —**7 reglas** en `landing.css` y **30
apariciones** entre CSS y Blade (`ESTADO.md` decía 29: re-medido el 2026-08-27)—: una clase por
elemento, escrita a mano, que no es un mecanismo sino la excepción de un caso.

### 1.3 Un solo `--fg-mute`, y hacen falta dos — **demostrado con números**

Contraste WCAG calculado sobre los dos fondos del sistema:

| Gris | sobre TINTA `#101418` | sobre PAPEL `#F4F4F1` |
|---|---|---|
| **el nuestro** `--fg-mute: #6B675D` | **3,28 ✕ falla AA** | 5,12 AA ✓ |
| Humo `#626A72` (PJP: «texto 2.º en papel») | 3,37 ✕ | 4,98 AA ✓ |
| Humo Claro `#9AA1A8` (PJP: «texto 2.º en tinta») | 7,08 AA ✓ | **2,37 ✕** |

▶ **No es una preferencia de diseño: es aritmética.** Nuestro gris único incumple AA en cuanto una
sección se vuelve oscura, y **ningún gris puede pasar en los dos fondos** — cada uno de los dos del
sistema falla en el otro. Con **163 usos** de `var(--fg-mute)`, eso son 163 sitios que dejarían de
ser legibles sin que nada fallara ni avisara.
▶ Y no es hipotético: la auditoría del propio owner lo cazó **vivo** en su mockup (hallazgo C-03,
severidad Alta: Humo sobre tinta, **2,79**, «ilegible»).

### 1.4 Radios: dos escalas que coinciden en **2 de 6**

| Nuestra | 8 (`--r-sm`) | 16 (`--r`) | 28 (`--r-lg`) | 14 (`--r-btn`) | 999 (`--r-pill`) | — |
|---|---|---|---|---|---|---|
| **PJP** | **6** | **16** ✓ | **24** | **10** | **999** ✓ | **0** |

Medido: **219 declaraciones de `border-radius`, 116 de ellas literales** (53 %). Y el reparto de esos
116 es lo que decide el tamaño de la tanda — ⚠️ **la primera versión de esta sección decía que el
trabajo era «tokenizar los literales» y eso era falso**:

| | | Qué se puede hacer |
|---|---|---|
| **21** | Valen **exactamente** lo que ya vale un token (13 × `999px`, 3 × `8px`, 3 × `14px`, 2 × `16px`) | ✅ **Conversión limpia, cero píxeles de cambio** |
| **39** | `50%` — geometría de círculo | ✅ **Se quedan a propósito**: no son un escalón de ninguna escala |
| **56** | No pertenecen a ninguna escala (10 × `10px`, 6 × `5px`, 5 × `6px`, 5 × `12px`, 3 × `18px`, 3 × `3.5px`…) | ❗ Redondearlos **MUEVE PÍXELES** |

▶ **Y aquí hay una coincidencia que no es casualidad**: `10px` y `6px` —los dos huérfanos más
repetidos— **son escalones de la escala del cliente** (`0·6·10·16·24·999`), que nuestros cinco
tokens no tienen. O sea que el producto ya usa de facto una escala más rica que la que declara.
▶ **Conclusión honesta: no hay una escala de radios de facto, igual que no hay una de sombra.** Hay
cinco tokens y 56 valores sueltos. Redondear los 56 es la misma clase de decisión que la escala de
elevación, y por coherencia va **donde va aquélla: a la tanda 2** (§4.5). La tanda 1 se queda con
las **21 conversiones que no mueven nada**.

### 1.8 Los iconos: es una LISTA DE HUECOS, no un mecanismo

⚠️ **No hay mecanismo que construir, y está decidido desde antes**: los iconos son componentes Blade
**versionados**, y `landing-white-label.md` §4.6 ya aceptó el coste con todas las letras —«En contra,
y asumido: añadir un icono nuevo es un despliegue»—. El set curado es la decisión del owner, y
`SidebarIconParityTest` impide que cajón y landing dibujen cosas distintas.

Medido contra el set del cliente (35 claves frente a nuestros **21** glifos):

- **8 ya están cubiertos** por equivalencia (`check`, `cuenta`→`user`, `fecha`→`calendar`,
  `flecha-der`→`arrow-right`, `pin`, `entrada`→`ticket-tear-off`, `registro`→`clipboard-check`,
  `reserva`→`receipt`).
- **16 son UI genérica y por tanto PRODUCTO**: `aviso · buscar · cerrar · chat · compartir · correo ·
  descargar · filtrar · hora · info · mas · menos · menu · movil · play · subir`.
- **11 son del sector** (`altura · bote · cama · canasta · cumple · fiestas · pack · regalo ·
  saltador · taquilla · valoracion`) — genéricos para un parque, no marca de nadie.

▶ **Pero ninguno se dibuja «por si acaso».** Un glifo que no usa nadie es peso muerto y ensucia la
paridad. Se dibujan **cuando el armazón o una sección los pidan**, que es el mismo criterio que
acaba de aplicarse a las sombras. En la tanda 1 el trabajo de iconos es **cero**.

### 1.5 Sombras: hay color tokenizado, **no hay escala** — y esto CORRIGE a `ESTADO.md`

`ESTADO.md` dice «68 declaraciones `box-shadow`, 58 formas distintas y **CERO tokens**». La primera
mitad es exacta; la segunda induce a error. Medido:

- **68 declaraciones · 58 formas distintas · 60 de ellas YA usan `var()` por dentro** (el barrido de
  `#143` las pasó a `color-mix(… var(--fg) …)`), así que **el color de la sombra ya sigue al tema**.
- Lo que no existe es la **escala de elevación**: 0 tokens `--shadow-*`, y solo **8 formas se repiten
  ≥2 veces**. No hay escala de facto que extraer — hay que **decidirla**, y decidirla mueve píxeles.

▶ El sistema del cliente pide justo lo contrario de lo que tenemos: **sombra dura `5px 5px 0` o
ninguna**, y difusa solo en modales. Con 58 formas difusas, eso no es redefinir un token.
**`[PENDIENTE: owner]`** — ver §4.5.

### 1.6 Las fuentes: son **CINCO**, y el `<link>` está quemado en tres layouts

`landing-white-label.md` §4.5.5 decidió que tipografía y radios van en el paquete CSS del cliente.
`client.css` puede redefinir `--font-display` y `--font-body` (**82** y **149** usos), pero **no
puede cargar los ficheros**: el `<link>` a la familia vive en `components/layout.blade.php`,
`components/focused-layout.blade.php` y `errors/maintenance.blade.php`, con las tres familias del
producto escritas dentro.

▶ **Y son cinco, no cuatro.** El contrato del cliente declara cuatro (Bungee · Hanken Grotesk ·
Permanent Marker · JetBrains Mono), pero su **logotipo no es una imagen**: es un lockup compuesto en
CSS con **Lilita One** y seis capas de `-webkit-text-stroke`. Medido en su landing definitiva:
Bungee 40 usos · JetBrains Mono 37 · Hanken 11 · Lilita One 2 (el logo) · Permanent Marker 2 ·
**Space Grotesk se carga y no se usa**.

▶ ✅ **Y NO es un problema de CSP, aunque lo pareciera.** `SecurityHeaders` permite un único origen
de fuentes, `fonts.bunny.net`, y está aseverado (`SecurityHeadersTest`). **Comprobado una a una:
Bunny sirve las cinco familias** (HTTP 200 en Bungee, Hanken Grotesk, Permanent Marker, JetBrains
Mono y Lilita One). No hay que tocar la CSP ni abrir un tercer origen: hay que hacer **configurable
la lista de familias**.

### 1.7 ⚠️⚠️ El coste real, y por qué es mucho menor de lo que parece

| | Medido |
|---|---|
| Usos de `var()` en las dos hojas | **1.915** |
| …de los **7 tokens de superficie** (`--bg`, `--bg-soft`, `--bg-card`, `--fg`, `--fg-mute`, `--line`, `--line-strong`) | **866 — el 45 %** |
| Literales de blanco/negro que **no** seguirían a la superficie | **52, en 44 declaraciones** |

▶ **Ésa es la tesis de esta spec**: como `RawColourIsNotATokenTest` ya prohíbe que un literal repita
un token existente, re-escopar esos siete tokens sobre un envoltorio de sección **invierte 866 usos
sin tocarlos**. Lo que no invierte es una lista corta y enumerable — y no son 52 casos sueltos, son
**tres familias con rol distinto**:

| Familia | Qué es | Cuántos | Ejemplos | Qué necesita |
|---|---|---|---|---|
| **A · tarjeta sobre la superficie** | el blanco que **no** es `--bg` (crema) | **15** | `.polaroid` · `.bd-card` · `.events__photo` · `.invite-card` · `.guestform__summary` · `.rules-layout__media` | un token nuevo **`--sheet`** |
| **B · sobre el ACENTO** | sigue a la marca, no al fondo | **10** | `.cta-prime__ico` · `.cta-*:hover .cta-*__s` · `.addons__badge--included` · `.offw-badge` | **nada: se queda** (`--on-brand` ya lo cubre) |
| **C · tinte** | `color-mix(…, #fff)` | **11** | `.guestform__flash` · `.guestform__progress` · `.maint-banner` | que la base del mix siga a la superficie |
| D · máscara | `#000` en `mask` | 8 | `.gallery-marquee` · `.offw-rays` | **nada: no es un color visible** |
| E · negro suelto | sombras y `text-shadow` del hero | 7 | `.cta-prime--onvideo` · `.hero__chip--onvideo` | entra con la escala de sombra (§4.5) |

▶ **`DEUDA.md` ya lo había anticipado**, y esta spec cierra su condición: *«candidato natural a la
tanda B, con el segundo cliente delante, que es cuando se sabrá si un `--paper` hace falta de
verdad»*. Ya se sabe: **hace falta**, y el sistema del cliente lo confirma declarando Papel
`#F4F4F1` (sección) y Blanco `#FFFFFF` (tarjeta sobre papel) como **dos roles distintos**.

---

## 2. Objetivo

**Que el paquete de un cliente pueda expresar su sistema visual sin tocar el dominio ni el marcado
del producto.** Criterios, todos medibles:

1. **La superficie de una sección es un dato del marcado, no una clase por elemento**: los 866 usos
   de tokens de superficie se invierten al re-escopar, y las 29 apariciones de `--onvideo` dejan de
   ser el único camino.
2. **0 sitios donde el gris secundario incumpla AA** en cualquiera de las dos superficies.
3. **Los cinco tokens de radio los redefine el cliente y mueven todos los cantos**: los 116 literales
   que hoy no leen token bajan a los que sean geometría real (`50%`) o excepción declarada.
4. **Un cliente instala su tipografía sin tocar Blade**, y sin abrir un origen nuevo en la CSP.
5. **El producto se ve EXACTAMENTE igual que hoy**: cada token nuevo se define con el valor que ya
   rendía el literal que sustituye. Es la misma regla que la escala `--fs-*`/`--sp-*` del cajón
   —«cada escalón vale hoy exactamente lo que valía el literal que sustituye»— y la que hizo que el
   barrido de `#143` diera **0 píxeles de diferencia**.

### Fuera de alcance, explícitamente

- ⛔ **Los VALORES del cliente, en el producto.** `[DECIDIDO owner, 2026-08-27]`: mecanismos al
  producto, PJP como paquete. Ni un hex, ni una fuente, ni un asset de marca entra aquí.
  ⚠️ **Ojo a la asimetría, que se decidió expresamente**: en la tanda 2 el producto **sí adopta la
  ESTRUCTURA** del mockup —menú a pantalla completa, CTA fijo, pie con tira, hero con vídeo y
  eslogan—, **neutra en valores**. O sea: el producto **cambiará de aspecto** y seguirá sin marca de
  nadie. La línea no es «no cambiar», es «no llevarse la marca».
- ⛔ **Las secciones de la landing.** `[DECIDIDO owner]`: se quedan como están hasta el paso 3.
- ⛔ **El contenido y el copy.** Los datos siguen siendo los nuestros; el CMS es la tanda B de
  `landing-white-label.md`.
- ⛔ **Rediseñar el flujo del cajón.** Aquí el cajón solo se retiñe: consume los tokens y nada más.
  La optimización UI/UX del embudo es **spec propia** — toca `FUNNEL_TRANSITIONS`, el diff de árbol
  y el presupuesto de bundle.

---

## 3. Opciones consideradas

### A · Una hoja alternativa por modo (`site-dark.css`) — **DESCARTADA**

▶ **Por qué no.** Duplica el mantenimiento de 1.915 usos de `var()` y garantiza divergencia: es
exactamente la forma de defecto que `LedgerSingleSourceTest` existe para prohibir en el dinero y
que `ZoneAccentIsNotAClassNameTest` cerró en el color de zona. Además no resuelve nada que el
re-escopado no resuelva más barato.

### B · Re-escopar los tokens de superficie sobre un envoltorio — **ELEGIDA**

▶ **Por qué.** Lo sugiere la propia medida de §1.7: el 45 % de los usos ya leen por token, así que
el mecanismo es **redefinir siete variables en un ámbito**, no reescribir reglas. Y no lo hemos
inventado: **el owner lo diseñó así en su propio kit** — `Elementos Fachada.dc.html` usa
`[data-superficie="papel"]` / `[data-superficie="tinta"]` redefiniendo `--bg`, `--card`, `--txt`,
`--mute`, `--line` y `--tile`. Es la misma forma, con nuestros nombres.

### C · Una clase por elemento, como `--onvideo` — **DESCARTADA**

▶ **Por qué no.** Es lo que ya hay, y por eso hay 29 apariciones para **una sola** superficie
oscura. Escala multiplicando: N elementos × M superficies, escritos a mano y sin guarda.

---

## 4. Diseño elegido

### 4.1 La superficie es un ÁMBITO, no una hoja ni una clase por elemento

Un atributo en el envoltorio de la sección redefine los siete tokens de superficie; todo lo que
cuelga dentro los hereda. La marca (`--brand`, `--zone-*`, `--on-brand`), las fuentes y los radios
**no** se re-escopan: no dependen del fondo.

⚠️ **Medido: el gancho existe a medias.** `.section` está definida en `landing.css` y la usan **7
secciones de la home** — pero de las seis páginas de `pages/`, **ninguna** la usa (`services` tiene
dos `<section>` sin la clase; las otras cuatro no tienen ninguno). Así que el envoltorio hay que
darlo, no solo aprovecharlo. Es el mismo hueco que `ESTADO.md` ya anotó para `<x-page>`: «no hay
nada entre el armazón del sitio y el contenido».

### 4.2 Qué se re-escopa y qué no

| Se re-escopa (7) | No se re-escopa | Motivo |
|---|---|---|
| `--bg` · `--bg-soft` · `--bg-card` · `--fg` · `--fg-mute` · `--line` · `--line-strong` | `--brand` · `--brand-2` · `--zone-1` · `--zone-2` · `--on-brand` | la marca **no cambia con el fondo**: el sistema dice «CTA primario idéntico en ambos fondos» |
| | `--font-*` (262 usos) | tipografía, no superficie |
| | `--r-*` (103 usos) | forma, no superficie |
| | `--ok` · `--err` · `--warn` · `--attn` · `--refund` | semánticos; su variante por fondo se decide en §4.4 |

### 4.3 Las tres familias de literales, y qué gana cada una

- **A → `--sheet`** (15). Token nuevo, **definido hoy como `#FFFFFF`**, que es lo que ya rinden: cero
  píxeles de cambio. En tinta pasa a la superficie de tarjeta del sistema. Es lo que `DEUDA.md`
  pedía y ahora tiene sujeto.
- **B → se queda** (10). Sigue al acento, y `--on-brand` ya voltea a tinta sobre acento claro.
  Convertirlos **cambiaría píxeles**, que es justo lo que §2·5 prohíbe. Su ficha de `DEUDA.md` no se
  cierra aquí: se **reetiqueta** como decisión de producto, no como deuda de tokenización.
- **C → la base del `color-mix`** (11). Hoy mezclan contra `#fff` a mano; pasan a mezclar contra el
  token de superficie, que en papel vale lo mismo.
- **D → nada** (8). `#000` en `mask` es un recorte, no un color.
- **E → la escala de sombra** (7). Son las sombras del hero sobre vídeo. Entran con §4.5.

### 4.4 Los dos grises

`--fg-mute` deja de ser un valor y pasa a ser **el gris de ESTA superficie**: en papel el oscuro, en
tinta el claro. Los 163 usos no cambian ni una línea — cambia lo que la variable vale en cada
ámbito. Es la misma indirección que ya existe para `--zone-1` por zona.
⚠️ **Y arregla un defecto que hoy es invisible**: el hero es oscuro y pinta `--fg-mute` encima.

### 4.5 Radios y sombras — **la asimetría importa**

- **Radios: en la tanda 1 solo lo que no mueve nada.** Los cinco tokens existentes mapean 1:1 con la
  escala del cliente (`--r-sm`→6 · `--r`→16 ✓ · `--r-lg`→24 · `--r-btn`→10 · `--r-pill`→999 ✓), así
  que **un cliente puede redefinirlos y le obedecen**. Lo que entra ahora son las **21** conversiones
  idénticas de §1.4 (`999px` → `var(--r-pill)` no mueve nada) más declarar los `50%` como geometría.
  ❗ Los **56 huérfanos van a la tanda 2** con la escala de sombra: redondearlos es decidir una
  escala, y decidirla mueve píxeles.
- ✅ **Sombras: `[DECIDIDO owner, 2026-08-27]` — FUERA de la tanda 1; se deciden con el armazón.**
  No hay escala que extraer —58 formas, solo 8 repetidas—, así que **crearla es decidirla, y
  decidirla mueve píxeles**: eso rompería la promesa de que los cimientos no mueven ninguno (§2·5).
  ▶ **Y hay un motivo mejor que el calendario**: la escala se decide **cuando el hero y el pie ya
  pidan sombras concretas**, que es cuando se ve el efecto real en vez de imaginarlo. Las 7 sombras
  del hero (familia E de §1.7) esperan ahí.
  ⚠️ **Lo que NO se descartó, y hay que recordar**: hasta que exista la escala, un cliente que
  redefina su tema **no mueve ninguna de las 58 sombras**. El color de la sombra sí le obedece
  (60 de 68 ya van por `var()`); su forma, no. Es una limitación conocida, no un olvido.

### 4.6 Las fuentes por instalación

La lista de familias deja de estar escrita en tres Blade y pasa a ser **un valor con default del
producto**, que una instalación sustituye. El origen sigue siendo `fonts.bunny.net` —único permitido
por la CSP y comprobado que sirve las cinco familias—, así que no se abre ningún tercero y
`SecurityHeadersTest` no cambia.
⚠️ **No vale resolverlo con `@import` desde `client.css`**: cargaría en serie sobre la ruta crítica,
que es exactamente lo que el `<link>` con `preconnect` evita hoy.

---

## 5. Impacto en invariantes

| Invariante | Impacto |
|---|---|
| **PERF-01** (`Setting::value()` memoiza por petición) | ⚠️ Si la lista de fuentes sale de `settings`, entra en el payload ya memoizado del composer global — **no** en una consulta suelta. |
| **PERF-02** (composer global con un `pluck` por request) | ⚠️ Lo mismo. Y la home tiene **presupuesto de consultas aseverado** (`HomePageTest`): el tema no puede empeorarlo. |
| **SEC-07** (ninguna URL externa editable llega a un `href` sin sanear) | ⚠️⚠️ **El punto más delicado de esta spec.** Si la familia tipográfica es editable, acaba **dentro de una URL de `fonts.bunny.net`**. Tiene que ir por allowlist o por saneado estricto, como `MapsEmbed` y `ThemeSettings::hex`. Un valor libre ahí es una inyección en el `<head>`. |
| PAY-*, AFORO-*, RGPD-* | **Ninguno.** No se toca dominio, ni dinero, ni aforo, ni PII. Esta spec **no entra en el `CRITICAL_RE`** y **no exige `VERIFY_CONC=1`**. |

---

## 6. Plan de verificación empírica

Sin esto no puede llegar a ✅ (`/dod`, `docs/CONVENCIONES.md` §3.bis).

1. **`SurfaceScopeTest`** (futuro) — prohíbe el MECANISMO, no persigue el síntoma: ninguna regla
   puede pintar un color de superficie que no venga de los siete tokens. Con **guarda de la guarda**:
   el escaneo tiene que ver el corpus y cazar sus propios ejemplos, o queda verde sin mirar nada.
2. **Contraste por ARITMÉTICA** (futuro) — el gris secundario de cada superficie pasa AA **sobre su
   propia superficie**, calculado en el test y no copiado de esta tabla. Cae si alguien iguala los
   dos grises.
3. **Los dos fondos, por MUTACIÓN** — retirar el re-escopado tiene que dejar en rojo la superficie
   oscura y **no** la clara.
4. **`--sheet` con control negativo** — un cliente que redefina `--sheet` mueve las tarjetas; y
   redefinir `--bg` **no** las mueve (son roles distintos: es la aserción que impide que vuelvan a
   confundirse).
5. **Píxel a píxel contra hoy** — con el tema por defecto, las páginas públicas se ven **idénticas**.
   Es el criterio §2·5 y el mismo con el que se validó `#143`. Se comprueba en navegador, que es la
   única red que ve esto (`docs/DEUDA.md`).
6. **Presupuesto de consultas de la home** — `HomePageTest` no empeora.
7. **Los presupuestos del cajón no se rompen** — `SidebarTokenBudgetTest` (suelo de tokenización y
   trinquete de colores crudos) y `SidebarDomContractTest`. ⚠️ Si se toca un módulo del cajón,
   `npm run build:ssr` **antes** de leer ningún resultado.

---

## 7. Las tandas, en el orden que fijó el owner

| | Tanda | Qué entra | Por qué va aquí |
|---|---|---|---|
| **1** ✅ | **Cimientos** — los dos fondos + `--sheet` + los dos grises + la escala de radios + las fuentes por instalación + el set de iconos | Solo tokens y CSS | Es lo que el armazón consume: hacerlo después obliga a rehacerlo. ▶ **Y su promesa es que NO mueve un píxel**, lo que la hace verificable de una sola forma y barata de revisar. |
| **2a** ✅ | **La FORMA + el pie** — la escala de canto, la ley del motivo cuadrado, el anillo de foco y la tira de marca | Tokens, CSS y 9 líneas de Blade | `[DECIDIDO owner, 2026-08-27]`. Mueve **20 px en 11 declaraciones**, todas enumeradas. Es lo que el hero consumirá: hacerlo después obliga a repasarlo. §10. |
| **2b** ⬜ | **El hero** — tarjeta con margen en vez de a sangre, coreografía de scroll, y **la escala de sombra** | Marcado + CSS + JS de scroll | ⚠️ Aquí el producto **sí cambia de aspecto de verdad**: es la que necesita ojo en navegador. La sombra entra aquí porque no hay escala extraíble sin coste (§10.3). |
| **2c** 🟦 | **El menú** → **spec propia: `armazon-y-menu.md`** — ✅ **la 2c·0 ya está en el árbol** | — | `[DECIDIDO owner, 2026-08-27]`. Sustituir la barra horizontal por logo + hamburguesa + menú a pantalla completa toca **12 vistas**, los dos desplegables con sus datos del CMS (`show_in_nav`), el botón de registro y la barra de móvil. No es «adoptar una estructura»: es cambiar la navegación del sitio. ▶ **Medido al escribir la spec** (y **corregido al ejecutar**: la primera cifra sumaba coincidencias, no reglas): son **194 reglas distintas · 733 declaraciones** de CSS, **31 aserciones** en 4 ficheros y **33 reglas MUERTAS, ya retiradas en la 2c·0**; el owner cerró **cuatro** decisiones y dejó **seis** pendientes, la primera de ellas el **artboard de móvil**. ⚠️ **Y una que vuelve aquí**: el hallazgo `M-05` del cliente —difusa fuera de modal en el CTA fijo y el botón de registro— **pone en cuestión dos consumidores de `--shadow-float`** (§13.3). |
| **2d** ⬜ | **El MOVIMIENTO** — 4 curvas y 7 duraciones como tokens | CSS | Sale de la 2a con su medida: **237 declaraciones, 48 duraciones distintas y 20 curvas** (el sistema declara 7 y 4). `200ms` sola tiene 110 usos. No mueve píxeles, mueve TIEMPO — y eso no se revisa con una captura, se revisa interactuando. |
| **3** ⬜ | **Las secciones**, pieza a pieza | Sección por sección | ⚠️ El owner subió el 2026-08-27 dos artboards nuevos, `Descubre-el-Parque` y `Recorrido-Parque`: son **dos variantes de la misma sección** («02 · El parque»), y **cuál se queda está sin decidir**. `Elementos Fachada` sigue **sin migrar**: de ahí se saca la FORMA, nunca el color. |

⚠️ **La frontera entre la 1 y la 2 es exactamente «¿mueve píxeles?»**, y no es cosmética: es lo que
permite revisar la 1 con un diff de captura y la 2 con criterio. Meter la escala de sombra en la 1
—que era la opción tentadora— habría borrado esa frontera y dejado las dos tandas sin forma de
verificarse.

⚠️ **La tanda 1 no se empieza sin el ✅ del owner y sin recortarla con él delante**, con número y
coste — como se hizo con el waiver. Y **commitear en local antes de cada mutación**
(`specs/desmontar-view-order.md` §9.1: es una regla pagada).

---

## 8. Revisión y decisión

- **Medido por el agente** el 2026-08-27 contra `public/css/*.css`, los layouts, la CSP y el canvas
  importado. El instrumento lleva guarda de la guarda y sus cifras son reproducibles.
- ⚠️ **Dos afirmaciones de la doc vigente se corrigen aquí, con su medida**: `ESTADO.md` decía que
  las sombras tienen «CERO tokens» (son **60 de 68** con `var()` por dentro; lo que no hay es
  escala), y se daba por hecho que la tipografía del cliente era un problema de CSP (**no lo es**:
  Bunny sirve las cinco familias).
- **Decidido por el owner** el 2026-08-27, en cuatro vueltas:
  1. **Mecanismos al producto, PJP como paquete** — la medida de éxito sigue siendo la de
     `landing-white-label.md` §6·6: cero migraciones y cero líneas de dominio.
  2. **El cajón solo se retiñe** (consume los tokens). Su rediseño de flujo es **spec propia**.
  3. **La escala de sombra sale de la tanda 1** y se decide con el armazón (§4.5).
  4. **En la tanda 2 el producto adopta la ESTRUCTURA del mockup, neutra en valores** (§2). Con
     esto la frontera entre las dos primeras tandas queda limpia: la 1 no mueve píxeles, la 2 sí.
- ✅ **Sin nada pendiente del owner para empezar la tanda 1.** Lo que falta es su ✅ a esta spec y
  recortar la tanda con él delante, con número y coste.
- **Entrada final**: `DECISIONES #N` al aprobarse.

---

## 9. Ejecución de la tanda 1 — lo que se hizo y lo que se midió

> Registro escrito **mientras se ejecutaba**, no después. Las cifras salen de instrumentos, y donde
> el instrumento falló se dice, porque esa es la parte que otro agente necesita para no repetirlo.

### 9.1 Las cinco unidades hechas

| | Unidad | Qué entró | Verificación |
|---|---|---|---|
| **1** | El ámbito `[data-surface="ink"｜"paper"]` y los 8 tokens re-escopados | `landing.css`: la paleta de tinta DERIVADA en `:root` + los dos bloques de ámbito | navegador: papel idéntico token a token · tinta invierte · **el anidado vuelve** |
| **2** | **`--sheet`**, el token de LA HOJA | 12 sustituciones; la familia A pasa de **15 a 5** | navegador: las 11 reglas rinden **el color exacto de antes** |
| **3** | Los dos grises | `--fg-mute` pasa a ser «el gris de ESTA superficie»; 163 usos sin tocar | **7,29 AAA** medido en tinta · 4,92 AA en papel |
| **4** | Los tintes de superficie | 6 mezclas pasan a `var(--sheet)` como base | navegador: los 6 rinden el color de antes |
| **5** | Radios | **21** conversiones idénticas (13×`999px`, 3×`8px`, 3×`14px`, 2×`16px`) | los tokens valen lo que valía el literal |
| **6** | **Las fuentes por instalación** | `config/theme.php` + `Content\Services\ThemeFonts`; los 3 layouts dejan de llevar la lista | `ThemeFontsTest`, **5 de 5 mutaciones muerden** |
| — | **`SurfaceScopeTest`** | 8 casos, **8 de 8 mutaciones muerden** | ver §9.3 |

▶ **La tipografía eran DOS mitades que no se hablaban**: `client.css` ya podía redefinir
`--font-display`, pero **el fichero no se descargaba nunca** —la lista estaba escrita a mano en
tres layouts—, así que el token cambiaba y el navegador caía a `system-ui`. Ahora `THEME_FONTS`
dice QUÉ SE DESCARGA y los tokens QUÉ SE USA.
▶ ⚠️ **El HOST no es configurable, y eso es la mitad del diseño de seguridad**: la CSP permite un
único origen y apuntar a otro **no da error** —lo bloquea en silencio y la web se queda sin
tipografía—. Lo variable es solo lo que se puede variar sin romper nada, y encima por allowlist,
porque acaba dentro de una URL en el `<head>` (`SEC-07`).

▶ **Y `--line`/`--line-strong` dejan de ser `rgba(20,19,15,α)` escrito a mano**: pasan a derivar de
`--fg`, así que **147 líneas y bordes** empiezan a seguir al tema. Verificado idéntico.
▶ **El trinquete del cajón bajó dos veces, y las dos avisó él**: `MAX_RAW_COLOURS` **5 → 4 → 3**.

### 9.2 ⚠️ Lo que la ejecución CORRIGIÓ del diseño

1. **`--paper` se llama `--sheet`.** «Papel» es el nombre de la SUPERFICIE clara; usarlo también
   para la tarjeta que va encima haría que el mismo nombre significara dos cosas a un palmo.
2. **`[data-surface="paper"]` nació duplicando los literales del `:root`** — el defecto exacto que
   esta capa existe para cerrar. Son alias (`--paper-*`), no copias.
3. **Los radios no eran «tokenizar 116 literales»**: solo **21** convierten sin mover un píxel. Los
   **56** huérfanos son una decisión de escala y van a la tanda 2 (§1.4).
4. **La escala de sombra sale de la tanda 1** (`[DECIDIDO owner]`), y con ella los 56 radios: la
   frontera entre las dos tandas es «¿mueve píxeles?».

### 9.3 ❗ Lo que costó, y es lo más útil de este registro

⚠️⚠️ **El instrumento falló DOS veces antes que el código, y las dos veces parecía lo contrario.**

- **La sonda de color dio 5 fallos que no existían.** Chrome devuelve `color(srgb 0.078 …)` en
  floats **0–1** para `color-mix`, y el normalizador asumía enteros **0–255**: redondeaba `0.078` a
  `0`. El CSS estaba bien desde el principio.
- **El diff de capturas no puede probar identidad de píxel en `/cumpleanos`.** Daba 31,7 dB y
  parecía un cambio real; con control por página se vio que **el original contra sí mismo da 28,6
  dB**, *peor*. La página no es determinista —un stepper de progreso avanza entre renders—. Se
  recortó la región, se miró, y era estado de la aplicación.
  ▶ **La lección**: un diff de captura sin **control por página** no mide nada. El instrumento que
  sí sirve aquí es comparar el **color computado regla a regla**, que es determinista.
- **La guarda de la guarda de `SurfaceScopeTest` nació ciega.** Aseveraba «el `:root` trae más de 20
  propiedades»; al romper el `:root` de `landing.css` la mutación **no la despertó**, porque
  `site.css` declara el suyo y el recuento seguía alto. Un umbral no distingue «leo poco» de «leo
  otra cosa»: hoy se asevera **por nombre** lo que tiene que estar.
- **El script de sustitución abortó tres veces** por anclas ambiguas, y una de ellas iba a convertir
  `.invite-field input` — que está en `ALLOWED_SELECTORS` porque lo captura `html2canvas` y exige
  estilos sólidos. **La invitación es un artefacto imprimible: es blanca siempre, no sigue a la
  superficie.** Sin el aborto, se habría roto la captura sin que ningún test se enterara.

### 9.4 Lo que falta

- **Nada de la tanda 1.** Lo siguiente es la **tanda 2** (el armazón), y con ella las dos decisiones
  de escala que se aparcaron a propósito: la de **sombra** y los **56 radios huérfanos**.
- ⚠️ **[CADUCADO — lee §11 antes que esta línea]** Desde `#194` el `.hero__stage` declara
  `data-surface="ink"`: el mecanismo **sí** tiene consumidor, y fue ahí donde se vio funcionar por
  primera vez. Lo que sigue describe el estado del día de la tanda 1.
- ⚠️ **El mecanismo aún no lo usa nadie**: ninguna sección declara superficie todavía. Es correcto
  —es el cimiento, y la tanda 2 es quien lo consume—, pero significa que **su red es la guarda y las
  sondas, no el ojo**. Cuando el armazón pinte la primera sección en tinta, esa es la pasada de
  navegador que falta.

---

## 10. Ejecución de la tanda 2a — la FORMA, el foco y el pie

> Registro escrito **mientras se ejecutaba**. Igual que §9, la parte útil no es lo que salió bien:
> es lo que costó y lo que la medida corrigió del plan.
>
> ⚠️⚠️ **Y lo primero de todo: esta tanda empezó descubriendo que la doc mentía sobre el canvas.**
> El `Landing PJP Modos` que había en `mockup_playjumppark/` estaba **caducado** —386 líneas de
> diff contra el remoto, y tocaban el hero— y `Colores de Marca PJP` también. La copia local se
> bajó el 2026-08-27 a las 07:30 y el owner siguió trabajando después. **Antes de implementar nada
> desde el canvas, `DesignSync · list_files` y diffear**: la carpeta local no avisa de que está
> vieja, y una copia caducada se lee igual de bien que una fresca.

### 10.1 Cómo se recortó, y con qué números

El owner recortó con los datos delante (regla de §7, la misma del waiver). Las cuatro unidades que
se midieron, y qué pasó con cada una:

| | Unidad | Medido | Decisión |
|---|---|---|---|
| **A** | El pie | 20 reglas CSS · 120 líneas Blade | ✅ **ENTRA** |
| **B** | Los patrones de forma | 219 radios (95 literales) · 68 sombras · 237 transiciones | ✅ **ENTRA la forma estática**; sombra y movimiento salen (§10.3, §10.4) |
| **C** | El hero | 64 reglas · 30 apariciones de `--onvideo` · JS de scroll con rAF | ⬜ **Tanda 2b** |
| **D** | El menú | 77 reglas · 261 líneas Blade · **12 vistas** | ⬜ **Spec propia** |

▶ **Y una decisión de producto que la medida forzó, sobre el PIE**: el mockup tiene una fila de
enlaces; el nuestro tiene **cuatro columnas** (marca, El parque, Información, Contacto) porque el
mockup es de UNA página y el producto sirve a seis. Adoptar su estructura habría **perdido**
teléfono, correo, redes, «Mi cuenta» y «Registro». Se adopta la **tira** y el ritmo de la fila
inferior; las cuatro columnas se conservan. Es la línea del owner —«los datos son los que tenemos
ahora»— aplicada a la estructura cuando la nuestra es más rica que la del mockup.

### 10.2 Lo que se hizo

| | Unidad | Qué entró | Píxeles |
|---|---|---|---|
| **1** | **La escala de canto, CERRADA** — `--r-xs: 5px` y `--r-md: 10px` nuevos; queda `5·8·10·14·16·28·999` | 25 declaraciones convertidas; los literales bajan de **95 a 70** y los huérfanos de **56 a 31** | **11 se mueven, 20 px** |
| **2** | **La ley del motivo cuadrado**, declarada y aseverada | 13 declaraciones salen de «deuda» y pasan a familia con ley | **0** |
| **3** | **El anillo de foco** — `--focus-w` / `--focus-color` / `--focus-outline` | 8 reglas dejan de escribirlo a mano; 3 excepciones declaradas | **0** |
| **4** | **La tira de marca del pie** — `--strip-1..5`, sin un solo hex | 20 reglas + 9 líneas de Blade | la tira es nueva; el filete de 1 px que sustituye, menos |
| — | **`ShapeScaleTest`** | 9 casos, **13 de 13 mutaciones muerden** | — |

▶ **Los dos escalones nuevos NO son de gusto.** Se eligieron minimizando el movimiento sobre los 23
cantos que estaban en literal, probando la rejilla entera de candidatos:

| Escala | Declaraciones movidas | Píxeles |
|---|---|---|
| la de antes, `8·14·16·28` | **23 de 23** | 53 |
| `+ --r-xs:5` | 19 de 23 | 36 |
| `+ --r-md:10` | 15 de 23 | 37 |
| **`+ los dos` ← elegida** | **11 de 23** | **20** |
| `+ un tercero en 12` | 6 de 23 | 10 |

El tercer escalón se descartó **a propósito**: con 8·10·12·14·16 los saltos son de 2 px y eso deja
de ser una escala para ser un continuo — una escala que no disciplina nada es una escala que el
siguiente rodea metiendo un literal. Coincide que `--r-md: 10px` es también el escalón del cliente,
pero **se eligió por la medida, no por copiarle**: si hubiera salido otro número, iría otro número.

▶ **Las 11 que se mueven, enumeradas** (ninguna otra):
`.bk-foot__pop` · `.guestform__child` · `.guestform__flash` · `.guestform__readonly` ·
`.orders__product` (12→10) · `.zone-intro__tag` · `.zone-photo-card__tag` (6→5) ·
`.plan-select__item-num` (4→5) · `.bd-pol__sticker` (7→8) · `.bd-card` (18→16) ·
**`.offw-card` (24→28, el único salto de más de 2 px)**.

### 10.3 ❗ La SOMBRA no entra, y el porqué es un número

`ESTADO.md` pedía «decidir la escala de elevación». Medida sobre las 68 declaraciones, **la escala
no se puede extraer sin coste**:

| Escalones | Declaraciones movidas | Píxeles de blur |
|---|---|---|
| 3 · `22·44·60` | 42 de 55 | 299 |
| 4 · `18·28·44·60` | 38 de 55 | 221 |
| 5 · `18·28·44·60·80` | 35 de 55 | 161 |
| 6 · `18·28·38·48·60·80` | 35 de 55 | 125 |

Ni con seis escalones baja de 35 movidas. Crear la escala **es decidirla**, y decidirla mueve mucho:
va con el hero (tanda 2b), que es cuando el armazón pide sombras concretas y se ve el efecto en vez
de imaginarlo. Es lo que §4.5 ya decía; ahora tiene el número.

⚠️⚠️ **Y un instrumento mío salió sesgado aquí, con la firma exacta del defecto que este repo ya ha
pagado cinco veces.** El primer barrido clasificaba las sombras con `(-?\d+)px` — que **no ve un `0`
sin unidad**. Y una sombra dura acaba justo en `… 0 <color>`: el clasificador la habría mandado a
«difusa» sin enterarse. Daba «0 duras». Corregido, y con guarda que caza sus propios ejemplos, da
**6**… que al mirarlas una a una son **anillos de foco `0 0 0 Npx`**, no elevación.

▶ La clasificación honesta de las 68 es: **55 de elevación · 9 anillos · 4 `none` · 0 duras**.
▶ Y la respuesta a lo que importaba —*¿existe en el producto la sombra dura proyectada de PJP,
`5px 5px 0`?*— **sigue siendo cero**, igual que decía el instrumento roto. **Dos medidas coinciden
y solo una puede explicar la diferencia: vale la que sabría encontrarla.** La primera acertó por
casualidad, y una medida que acierta por casualidad no es una medida.

### 10.4 ❗ El MOVIMIENTO tampoco entra, y también es un número

| | Producto | Sistema del cliente |
|---|---|---|
| Declaraciones `transition`/`animation` | **237** | — |
| Duraciones distintas | **48** | 7 (`120·180·240·320·420·620·900`) |
| Curvas distintas | **20** | 4 (Bote · Lona · Salida · Lineal) |
| Tokens que ya existen | 4 (`--dur-collapse`, `--dur-fade`, `--ease-panel`, `--ease-bounce`) | — |

`200ms` sola tiene **110 usos** y `ease` **216**: adoptar la escala del cliente tal cual cambiaría
el tempo de toda la web. Es una tanda propia (**2d**), y su red no es una captura: es interactuar.

### 10.5 ⚠️ Lo que la ejecución CORRIGIÓ del plan

1. **La tira CICLA, no interpola — y se escribió al revés primero.** El plan repartía cinco pasos de
   `color-mix` entre los dos colores de marca. Con la marca por defecto (naranja→amarillo, vecinos)
   quedaba bien. Con la del segundo cliente (cian→naranja, casi complementarios) la franja del medio
   salía **`#868D7D`, barro**. ▶ **Y no era cosa del espacio de color**: medido, `oklab` da croma
   mínimo **0,024** frente a **0,025** de `srgb`. Interpolar entre dos colores *arbitrarios* no es
   robusto, y **el producto no elige la marca de su cliente**. Ciclando, cada franja es un color de
   marca entero sea cual sea el par.
2. **Los 56 «radios huérfanos» eran DOS leyes mezcladas, no una lista.** 16 de ellos siguen
   `radio ≈ lado / 4` con **mediana exacta 4,00** (12 de 16 dentro de ±12 %): es la forma del bloque
   de espuma a cada tamaño. Forzarlos a un escalón habría roto una familia proporcional para
   «arreglar» algo que no estaba roto.
3. **`.cal__dot` no es un motivo, y lo dijo el test.** Con lado 12 y radio 4 su ratio es **3,00** y
   la aserción de la ley lo escupió. Al mirarlo es la **muestra de color de la leyenda del
   calendario**: un swatch. Va a la lista de dibujo, no a la de motivos.
4. **La tira se cazó a sí misma.** Se escribió con `border-radius: 2px` y `ShapeScaleTest` la marcó
   como canto en literal en su primera pasada. Va con `var(--r-pill)`: en una caja de 4 px de alto
   el navegador escala los radios y `999px` rinde **exactamente** los 2 px de antes.
5. **El `outline-offset` NO se tokeniza.** Hay 2, 3, 4 y −2 px, y cada uno responde a la forma de su
   caja. Un token único movería píxeles en cinco reglas a cambio de nada.

### 10.6 ❗ Lo que costó, y es lo más útil de este registro

⚠️⚠️ **El arnés de mutación dio «0 de 12 muerden» con el test funcionando perfectamente.**

Decidía si el test había caído con `grep -q "FAILED\|failed"` sobre la salida. En esta máquina
`grep` es **ugrep**, que interpreta ERE: ahí `\|` es un **pipe literal**, así que buscaba la cadena
`FAILED|failed` y no casaba jamás. Con el arnés arreglado —decidiendo por **código de salida**, que
no admite interpretación, y con un **control positivo** que exige que el test esté verde antes de
mutar— salen **13 de 13**.

▶ **La lección, que es la misma de §9.3 con otra cara**: cuando un instrumento dice que *nada*
funciona, la primera hipótesis es el instrumento. Un arnés de mutación que nunca detecta el fallo es
peor que no tenerlo, porque **certifica**: habría firmado que 12 aserciones eran decorativas y
habría mandado a alguien a reescribir un test que estaba bien.

⚠️ **Y el script de conversión abortó en 9 de sus 25 anclas a la primera**, por un defecto propio:
localizaba reglas con `(?m)^([^{}/@\n][^{}]*?)\{` **sin blanquear los comentarios**, así que un
`/* … */` que terminara justo encima del selector se comía la cabecera y daba **cero** reglas donde
había una. Se blanquean **conservando la longitud**, para que los offsets sigan valiendo. El aborto
hizo su trabajo: no escribió nada hasta que las 25 cuadraron.

### 10.7 Lo que falta

- **Nada de la 2a.** Lo siguiente es la **2b, el hero**, y con ella la escala de sombra.
- ❗ **La pasada de NAVEGADOR del owner sobre las 11 declaraciones que se mueven** y sobre la tira
  del pie. La suite comprueba que el token vale lo que tiene que valer; **no comprueba que el
  resultado guste**, y estas once cambian de aspecto a propósito.
- ⚠️ **`[data-surface]` sigue sin usarlo nadie.** La 2a tampoco lo consume: la primera vez que se
  verá funcionar es en la 2b, cuando el hero declare `ink` y las tres excepciones del anillo de foco
  (`.skip-link`, `.hero__chip`, `.gf-fiche__head`) puedan retirarse.

---

## 11. Ejecución de la tanda 2b — el hero, en tres pasos (dos hechos)

> `[DECIDIDO owner, 2026-08-27]`: la 2b va **entera, coreografía incluida**, con el aviso delante
> de que su propia auditoría excluye el hero (§11.1). Se ejecutó por pasos, commiteando en local
> antes de cada mutación. **Los pasos 1 y 2 están hechos y verificados; el 3 se paró, y el porqué
> es §11.5 — no es cansancio, es una dependencia que nadie había visto.**

### 11.1 ❗ Lo primero: la auditoría del cliente EXCLUYE el hero

`Auditoría Landing PJP`, en su portada, dice literalmente:

> «Quedan fuera **por indicación expresa**: organización y efectos del **hero de cabecera**, del
> **bloque de cierre**, del **menú** y del **logotipo**. Sus colores, iconos y estilos sí se han
> revisado.»

Y su inventario del kit lo repite: `E4 · Hero → **Excluido** — organización y efectos excluidos por
indicación`; `M-03` deja las duraciones del hero «intactas por indicación»; `M-05` sigue
**Pendiente** justo por las sombras de su CTA fijo.

▶ **Consecuencia**: la FORMA y la COREOGRAFÍA del hero del mockup **no están validadas contra el
sistema del propio cliente**. Sus COLORES sí. Por eso el paso 2 —la superficie— es terreno firme, y
el 3 —forma y coreografía— es el único de toda la capa de tema que copia algo no normativo. Se hace
igualmente porque el owner lo decidió con el aviso delante; queda escrito para que nadie lo tome
por «lo que dice el sistema».

### 11.2 `--onvideo` no era una cosa mal hecha: eran TRES con el mismo nombre

La doc prometía «retirar las 30 apariciones». Medido con un resolutor de color con guarda, las 21
reglas se reparten así:

| | Qué es | Qué pasa al declarar `data-surface="ink"` |
|---|---|---|
| **11 decl.** | **clases MUERTAS** — 6 de las 20 clases `.hero*` no aparecen ni una vez en `resources/` | se van gratis (paso 1) |
| **14 decl.** | **superficie** (color) | el ámbito las cubre; **10 rinden idéntico solas**, 3 necesitan regla de ámbito, 1 estaba muerta |
| **~8 decl.** | **tamaño y sombra** — `font-size` del titular, `box-shadow`, `text-shadow` | el ámbito **nunca** iba a cubrirlas: no son superficie. **Se quedan** |

▶ Medido al terminar: de las **10 reglas `--onvideo` que quedan, ninguna declara un color**. Eso es
lo que asevera `SurfaceScopeTest::test_onvideo_never_paints_a_colour_again`.

### 11.3 Lo que se hizo, y cómo se verificó sin navegador

| | Paso | Qué entró | Verificación |
|---|---|---|---|
| **1** ✅ | **Limpieza** | 17 reglas de 6 clases muertas, 56 líneas de `landing.css` | control de entrada (0 usos en `resources/`) y de salida (0 referencias); abortaba si no localizaba 17 tramos |
| **2** ✅ | **La SUPERFICIE** | el stage declara `data-surface="ink"`; los `--onvideo` de color se van | **aritmética**: 14 declaraciones resueltas en los dos ámbitos contra el valor medido ANTES. **14/14 idénticas**, dentro y fuera del hero |
| **3** ⬜ | **Forma + coreografía** | — | **parado**: §11.5 |

▶ **Es la PRIMERA VEZ que el producto consume el mecanismo de la tanda 1.** Hasta ahora
`[data-surface]` existía y no lo usaba nadie.

▶ **Y hay una pieza conceptual que merece nombre**: tres declaraciones no las cubría el ámbito
porque van **sobre el BOTÓN**, y el botón **invierte respecto a la superficie** (dentro de tinta el
CTA es claro). Pasan a reglas de ámbito que leen los alias **`--paper-*`** — que la tanda 1 creó
justo «para poder VOLVER a papel desde dentro de tinta». Es el primer uso real de esos alias.

### 11.4 ❗❗ Lo que costó: la conversión estaba a MEDIAS en tres sitios, y la guarda nació ciega

Es la parte útil de este registro.

**El commit del paso 2 prometía «14 de 14 rinden exactamente lo mismo», y era cierto — para las 14
que el barrido miraba.** Fuera de esa lista había tres conversiones a medio hacer:

1. **El scrim tiene CUATRO paradas de degradado y se convirtieron DOS.** Las de abajo —las que dan
   legibilidad al titular y al CTA sobre el vídeo— se quedaron leyendo `--fg`, que dentro de tinta
   vale **claro**: pasaban de tinta al 65 % a **crema al 65 %**. La mitad inferior del hero se
   blanqueaba.
2. **`.hero__stage-content { color: var(--bg) }` seguía ahí.** El plan lo daba por retirado y no lo
   estaba: texto oscuro sobre hero oscuro.
3. **El telón del placeholder** —lo único que se ve si el vídeo no carga— quedaba en crema.

Las tres con la misma firma: **CSS válido, suite verde, página cargando, hero roto en silencio.**

⚠️⚠️ **Y la guarda que se escribió para cazarlo nació ciega, por partida doble:**

- **Su diccionario de tinta no pre-resolvía `:root`.** Ahí `--fg` → `var(--ink-fg)` → `var(--bg)` →
  `var(--ink-bg)` → `var(--fg)` es un **CICLO**: el resolutor devuelve `null`, la comprobación hace
  `continue` y la guarda pasa **sin mirar nada**. Dio verde ante las dos mutaciones que reproducían
  el fallo que la motivó. ▶ Y es **el mismo error corregido veinte minutos antes en el instrumento
  de Python**, repetido en PHP.
- **Su criterio era el equivocado.** Preguntaba «¿invierte entre ámbitos?», y eso no distingue nada:
  **todo lo que lee un token de superficie invierte** — ése es el mecanismo. El criterio que sirve
  es el **resultado**: dentro del hero el suelo es OSCURO y el texto CLARO, resuelto en el ámbito
  donde de verdad vive, y **filtrando la marca**, que no se re-escopa (una mancha de `--zone-1`
  sobre el hero es clara a propósito).

▶ **La guarda final asevera las CUATRO paradas del degradado POR NOMBRE**, no por umbral — que es
la lección que ese mismo fichero ya llevaba escrita desde `#192` y que aun así se volvió a saltar.
▶ **4 mutaciones, 4 muerden, y tres REPRODUCEN los fallos reales.** Una guarda que no se prueba
contra el fallo que la motivó no se sabe si sirve.

⚠️ El instrumento de medida se equivocó **cuatro** veces antes que el código, y las cuatro están
aquí porque son reutilizables: `\b` tras «hero» no casa en `hero__stage` —el siguiente carácter es
`_`, que es de palabra— y el primer barrido **no vio NI UNA regla del stage** · el lado «hoy» se
componía sobre el botón oscuro cuando `--onvideo` pinta sobre el claro · los alias `--paper-*` se
resolvían dentro de tinta cuando el navegador los resuelve en `:root` · y el emparejador comparaba
selectores enteros, así que no veía las bases declaradas en selectores agrupados.

### 11.5 ❗❗ Por qué el paso 3 se PARÓ: la coreografía toca el camino de COMPRA

No es un problema de pintura. Medido:

**`.hero__stage-bottom` no es solo el sitio donde vive el CTA: es el SENTINEL de otros dos.**
- `navCtaReveal` — el botón «Comprar entradas» del nav aparece en escritorio cuando ese elemento
  sale del viewport.
- `mobileBookBar` — la barra flotante de reserva en móvil, igual.

Los dos lo observan con `IntersectionObserver` (`resources/js/app.js`), y los dos están escritos
para que hero y CTA **nunca sean co-visibles**.

▶ **La coreografía pone el stage en `position: sticky`**, y el sentinel vive dentro. Un elemento
dentro de un sticky **no abandona el viewport mientras el sticky sigue pegado** — y además sube
mientras la caja encoge. O sea: **el momento en que aparecen los dos CTAs de compra deja de ser el
que alguien diseñó y pasa a depender de la coreografía.**

Eso no es adoptar una estructura: es cambiar cuándo se le ofrece comprar al visitante. **Necesita
una decisión, no una suposición.** Las opciones, con su coste:

| | Opción | Coste |
|---|---|---|
| **A** | El sentinel pasa a ser el propio `<header class="hero">`, que sí sale del viewport al acabar el recorrido | dos componentes Alpine y sus comentarios; el CTA aparece **más tarde** que hoy (~420 px) |
| **B** | Un sentinel propio, invisible, al final del `<header>`, fuera del sticky | igual de barato y desacopla el sentinel del CTA: hoy son el mismo elemento por casualidad |
| **C** | La coreografía sin `sticky` (solo encoge en su sitio) | pierde el efecto del mockup; el sentinel no se toca |

▶ **Recomendada la B**: es la única que deja el sentinel siendo *un sentinel* y no *el botón*, y la
que no cambia el momento de aparición.

### 11.6 Lo que falta

- **El paso 3** (forma + coreografía), con la decisión de §11.5 tomada.
- **La escala de sombra**, que entra con él (§10.3: no hay ninguna extraíble sin mover 35 de 55).
- ❗ **La pasada de NAVEGADOR del owner.** Los pasos 1 y 2 prometen «cero píxeles» y eso se ha
  verificado por aritmética, que es fuerte — pero **esta máquina no tiene navegador headless**, así
  que nadie ha MIRADO el hero todavía. Y §11.4 es la prueba de que un barrido incompleto puede
  jurar que todo está bien.
- ⚠️ **Las tres excepciones del anillo de foco siguen ahí.** `.skip-link` y `.hero__chip` pintan el
  anillo claro a mano porque su fondo oscuro no declara superficie. El del chip **ya podría
  retirarse** —el hero ya declara `ink`—; se dejó fuera de este paso a propósito, para no mezclar
  un cambio de foco con la conversión de superficie. Entra con el paso 3.


---

## 12. El paso 3 de la 2b — el hero adopta la estructura del mockup

> `[DECIDIDO owner, 2026-08-27]`, con sus palabras: **«en el mockup el CTA sale DESPUÉS del hero;
> en el hero no hay CTA, solo texto y el vídeo. Hazlo igual que el mockup, de manera profesional.
> Si valoras cambiar algo por profesionalidad y robustez lo haremos, pero el mockup es el que
> manda; después iteraremos.»**

### 12.1 La decisión resolvió sola la duda que bloqueaba el paso

§11.5 dejó el paso 3 parado por el **sentinel**: `.hero__stage-bottom` no era solo donde vivía el
CTA del hero, era el elemento que **dos comportamientos de compra** observan para saber cuándo
mostrar sus botones, y meter el hero en `sticky` habría cambiado ese momento.

▶ **Al decidir que el hero no lleva CTA, el problema se resuelve por arriba**: ese elemento
desaparece, así que el sentinel había que darlo de todas formas. Ahora es `.hero__sentinel` —
vacío, de altura cero, **fuera del `sticky`** y al final del `<header>`—. Hace **un** trabajo y se
puede mover sin tocar ningún botón.

⚠️ **La lección, que es de arquitectura y no de este hero**: que aquello funcionara era una
**coincidencia** — el sitio donde acababa el botón coincidía con el sitio donde queríamos que
aparecieran los otros dos. Una coincidencia sostiene hasta que algo se mueve, y no avisa.

### 12.2 Lo que entró

| | Qué | Detalle |
|---|---|---|
| **El hero pierde su CTA** | queda eslogan → titular → estado | comprar se ofrece en el nav y en la barra de móvil, **con el anclaje «desde X €» intacto** |
| **El eslogan** | `landing.hero.kicker` en es/en/fr | + **`--font-accent`**, el CUARTO rol tipográfico. Por defecto = `--font-display`, así que el producto no cambia de familia por declararlo |
| **El punto de «Abierto ahora»** | `--ok`, no el color de acción | es el hallazgo `C-04` del cliente aplicado: un estado no se pinta con el color de un botón |
| **La forma** | tarjeta con margen y `var(--r-lg)` | desaparece el `border-radius: 0` que lo anulaba a mano — el único canto del producto anulado así |
| **La coreografía** | `sticky` + encoge en 420 px de scroll | de pantalla completa a tarjeta centrada |

### 12.3 ⚠️ La decisión de arquitectura que merece defenderse: el JS no decide diseño

`heroChoreo` publica **una** custom property, `--hero-p` (0→1). **Los dos estados los define el
CSS** con `calc()`: margen, alto, ancho y hueco del nav.

▶ **Por qué así y no como el mockup**, que escribe estilos inline desde JS en cada frame: si los
números vivieran en el JavaScript, serían **la única parte de la capa de tema que un paquete de
cliente no podría cambiar**. Toda esta spec existe para que el tema sea configurable; una
excepción escondida en un `.js` la contradice.
▶ **Y se nota en el móvil**: ahí **solo cambian cuatro tokens** (`--hero-runway`, `--hero-gap-end`,
`--hero-top-end`, `--hero-h-end`). Ni una regla. Antes había un `height: 80vh` y un
`padding-top: 69px` que sobreescribían la estructura.

**Robustez, punto por punto:**
- **`prefers-reduced-motion`** → no se monta, y el CSS pone el recorrido a **0**: sin eso quedaría
  una pantalla de scroll vacío que nadie sabría por qué está ahí. Las dos mitades dicen lo mismo a
  propósito — si una falla, la otra sostiene el estado válido.
- **Sin JS** → idéntico, y sin ninguna rama que mantener.
- **`scroll` con `{ passive: true }`**, coalescido en un `requestAnimationFrame`, y **no escribe si
  el valor redondeado no cambia**: evita invalidar el estilo en cada frame al final del recorrido,
  que es donde más tiempo pasa el visitante.
- **`100svh` declarado DESPUÉS de `100vh`**: en móvil `vh` cuenta la barra del navegador y el hero
  se salía por abajo. Quien no entienda la unidad se queda con la primera línea, que sigue valiendo.

▶ **Verificado por aritmética en tres viewports** (no hay navegador headless en esta máquina):

| Viewport | de | a |
|---|---|---|
| 1440×900 | 1420×880 | 1240×660 |
| 1280×800 | 1260×780 | 1224×624 |
| 390×844 (móvil) | 370×824 | 366×520 |

En los tres encoge, y el estado final deja hueco al nav.

### 12.4 ❗❗ Lo que costó: el test re-apuntado NO fijaba nada, y las mutaciones lo demostraron

Al retirar el CTA del hero cayó `test_hero_uses_cta_prime_structure_with_subtitle`. Se auditó por
**sujeto** (`CONVENCIONES §3.quater`): el sujeto —el CTA del hero— se va, pero la regla que
protegía —«el visitante ve un botón de comprar con el precio anclado»— **sobrevive** en la barra de
móvil, que conserva la misma estructura. Es el tercer caso de la convención, el que «hay que buscar
activamente». Se re-apuntó.

⚠️⚠️ **Y el test re-apuntado pasaba dos mutaciones que debían matarlo:**
- `assertSee('cta-prime')` **casa con `cta-prime__ico`**, así que quitar la clase del botón lo
  dejaba verde.
- `assertSeeText('desde 7,90 €')` lo satisfacía **el CTA del NAV**, que dice exactamente el mismo
  texto con otra clave de idioma (`landing.nav.cta_buy_from` vs `landing.hero.cta_buy_from`).

▶ Acotado al botón real —recortando por **su cierre de etiqueta**, no por una ventana de
caracteres— **4 de 4 mutaciones muerden**.
▶ Es literalmente el aviso que §3.quater lleva escrito: *«si un dato viaja al cliente y su único
test conduce la superficie vieja, el contrato NO lo está fijando»*. Aquí el dato viajaba por dos
sitios y el test no distinguía cuál.

⚠️ Y una tercera mutación mal diseñada por mi parte, que vale la pena anotar: cambiar la clase a
`hero__kicker-NO` **no mata** un `assertStringContainsString('hero__kicker')`, porque sigue siendo
subcadena. Para probar una ausencia hay que **retirar el elemento**, no renombrarlo.

### 12.5 Lo que falta

- ❗ **La pasada de NAVEGADOR del owner**, y aquí pesa más que nunca: este paso **sí cambia el
  aspecto de la primera pantalla** y esta máquina no tiene navegador headless. Lo verificado es
  aritmética y estructura, no vista.
- **La escala de sombra**, que sigue sin decidirse (§10.3: ninguna extraíble sin mover 35 de 55).
  Ahora el hero ya pide sombras concretas, que era la condición para decidirla.
- **El menú** (tanda 2c, spec propia). El owner ya avisó de que «cambia totalmente» y de que
  guiará el comportamiento en móvil.
- ⚠️ **Las tres excepciones del anillo de foco**: la del chip del hero **ya se puede retirar** —el
  hero declara `ink`—; se dejó fuera a propósito para no mezclarla con este paso.
- ⚠️ **`--onvideo` sobrevive en 10 reglas y su nombre ya miente**: solo declara tamaño y sombra.
  Renombrarlo toca la especificidad de `.hero__title.hero__title--onvideo`, que existe para ganarle
  a `landing.css`. Ficha en `DEUDA.md`.

---

## 13. La ELEVACIÓN — tres roles, no una escala

> `[DECIDIDO owner, 2026-08-27]` tras ver la medida. Cierra lo que §10.3 dejó abierto: «la escala
> de sombra se decide cuando el hero pida sombras concretas». Ya las pide.

### 13.1 ⚠️⚠️ Esto CORRIGE la medición de §10.3

§10.3 concluyó «no hay escala extraíble» **mirando solo el difuminado**. Una sombra tiene cuatro
grados de libertad —desplazamiento, difuminado, expansión y opacidad—, y agrupar por uno solo es
como clasificar tipografías por el ancho de la «m».

Rehecha con los cuatro, agrupando por **cuánto se percibe** cada sombra (`y + blur/2 + spread`,
ponderado por opacidad) y con *k*-means exacto por programación dinámica, la conclusión **no
cambia: empeora**.

| | Medido |
|---|---|
| Sombras de elevación vivas | **53** |
| Formas distintas | **42** — casi cada una única |
| Mejor escala de 5 escalones | mueve **47 de 53**; 7 px de media, **25 px** en el peor |
| Reparto de esa escala | 22 · 14 · 10 · 6 · **1** — el último escalón lo usaba UNA regla |

▶ **Eso no es una escala con ruido: es que no hay ninguna.** Son 53 ajustes hechos uno a uno.

### 13.2 ❗ El dato que cambió la pregunta

Al ir a copiar el número de escalones del sistema del cliente apareció que **no tiene ninguno**.
`Colores de Marca PJP` declara **DOS** formas —«dura `5px 5px 0` **o ninguna**», más la difusa
reservada a modales— con la regla de uso: *«un solo elemento por pantalla la lleva; en una rejilla
de tarjetas, jamás»*.

▶ **Con 42 formas de un lado y 2 del otro, la pregunta no era «¿de cuántos escalones?». Era «¿para
qué sirve cada sombra?».**

### 13.3 Los tres roles

| Token | Qué es | Cuántas |
|---|---|---|
| **`--shadow-lift`** | se despega al pasar el ratón o al activarse | **17** |
| **`--shadow-float`** | flota sobre el contenido, sin velo: paneles, avisos, la barra de móvil | **8** |
| **`--shadow-modal`** | tapa la página, con velo detrás | **3** |
| — | **no lleva sombra** | **19** |

▶ **Una tarjeta quieta no está elevada: está apoyada.** Esas 19 son justo las que el sistema del
cliente dice que no deberían llevar sombra, y entre ellas está el `.hero__stage` — verificado: **el
hero del mockup no tiene `box-shadow`**.

▶ **Los valores salen de lo que el producto ya hacía**: cada token es la **mediana** de su grupo, o
sea una sombra que ya existía, no un promedio inventado que no usaba nadie.

### 13.4 ⚠️ Leen `--paper-fg`, no `--fg`, y no es un detalle

Dentro de `[data-surface="ink"]` el token `--fg` vale **CLARO**. Una sombra escrita con él **se
vuelve clara dentro del hero** — y una sombra clara no es una sombra. Es el mismo defecto que se
cazó tres veces en §11.4 con el scrim, el texto y el placeholder.

▶ Una sombra es **ausencia de luz**: es oscura en las dos superficies. `--paper-fg` es el alias que
no se mueve al entrar en tinta, y hay guarda propia (`test_the_shadow_roles_use_the_stable_ink_alias`)
cuya mutación muerde.

### 13.5 Las seis excepciones, y por qué no son deuda

De **53 formas propias a 6**. La lista **solo encoge**.

| Selector | Por qué |
|---|---|
| `.sidecart__panel` · `.mob-menu__panel` | **DIRECCIONALES**: `-20px 0 …`, entran desde el lado. Un token vertical las rompe |
| `.lang-dd--up .lang-dd__panel` | **DIRECCIONAL hacia arriba** (`0 -18px …`): el desplegable se abre hacia arriba |
| `.invite-card` | **ARTEFACTO IMPRIMIBLE**: se captura con `html2canvas` y su sombra es parte de la tarjeta que el visitante se descarga |
| `.ck-tgl::after` | el **pulgar de un interruptor**: 1 px de sombra lo hace parecer una pieza física, no elevación |
| `.offw-badge` | lee `--offw-accent`, color de marca del widget, no una sombra de elevación |

### 13.6 Lo que esto le da al cliente

Un paquete redefine **tres tokens** y **las 28 sombras del producto le obedecen**. El del segundo
cliente pondría:

```css
:root {
    --shadow-lift:  none;                       /* «dura o ninguna»: en hover se aplasta, no se eleva */
    --shadow-float: 5px 5px 0 var(--paper-fg);  /* la pegatina del mural */
    --shadow-modal: 0 24px 60px rgba(0,0,0,.45);/* la única difusa que su sistema admite */
}
```

⚠️ **Lo que sigue sin poder cambiar**: las seis excepciones. Es una limitación conocida y
enumerada, no un olvido — y cinco de las seis no son elevación, así que redefinirlas no tendría
sentido.

### 13.7 Lo que falta

- ❗ **La pasada de NAVEGADOR**, y aquí pesa mucho: **19 elementos pierden su sombra** y eso cambia
  el aspecto de media web. Lo verificado es que cada sombra sale de un rol, no que el resultado
  guste.
- El **menú** (tanda 2c) sigue siendo lo siguiente, con la guía del owner sobre el móvil.
  ▶ ✍️ **Su spec ya está escrita**: `armazon-y-menu.md` (2026-08-27), con las cuatro decisiones
  del owner tomadas y **seis pendientes**. ⚠️ **Y le devuelve una pregunta a esta sección**: el
  hallazgo `M-05` de la auditoría del cliente dice que el **CTA fijo** y el **botón de registro
  de la esquina** no pueden llevar sombra difusa —«la difusa solo existe en modales»— y
  recomienda keyline. Los dos son hoy consumidores de **`--shadow-float`**. Si el owner acepta
  la recomendación, el rol pierde dos de sus ocho usos y hay que volver a mirarlo. **No se
  toca hasta que lo diga**: la lista de excepciones y de roles **solo encoge**.


---

## 14. El PRIMER PAQUETE REAL — la verificación que faltaba, y lo que destapó

> `[DECIDIDO owner, 2026-08-28]`: «vamos a montar ya el tema del cliente 1:1 al mockup, sin tocar
> sidebar». Montado el mismo día. **El paquete NO está en el repo y no puede estarlo**
> (`DECISIONES #1`): vive en `public/css/client.css`, gitignorado y excluido del `rsync`, más dos
> ajustes en el panel y una línea en el `.env`. Aquí se registra **lo que el montaje enseñó**, que
> es lo único de esto que es del producto.

### 14.1 ✅ Los cuatro mecanismos COMPONEN, y hasta ahora era una promesa

Se construyeron por separado —color y superficie (`#192`), forma (`#193`), el hero (`#194`,
`#195`), la elevación (`#196`)— y **nunca se había comprobado que funcionaran juntos con un
paquete real**. Ahora sí: con el paquete puesto, **la suite entera pasa** (3.127, 3 saltados) y la
web se sirve con la marca del cliente sin tocar una línea del producto.

▶ **Cero migraciones, cero líneas de dominio, cero cambios en el marcado.** Es la medida de éxito
que arrastraban las cinco tandas, y se cumple.

### 14.2 ❗❗ El hallazgo que ninguna guarda podía dar: **el anillo de foco es INVISIBLE en papel**

El sistema del cliente declara Amarillo Aviso `#F5C400` como **único** color de foco, «3px, offset
2px, **en ambos fondos**». Su auditoría de contraste cubre **20 pares** y es buena — pero **no
incluye «Amarillo sobre Papel»**. Auditó el foco sobre TINTA (11,26 ✓) y nunca contra el fondo
claro, que es donde ese anillo va a vivir **casi siempre** en nuestro producto: desde el hallazgo
`S-00` la web es **papel continuo de arriba abajo**.

| Par | Ratio | WCAG exige | |
|---|---|---|---|
| Amarillo Aviso sobre **Tinta** | **11,26** | 3,0 (1.4.11) | ✓ auditado por el cliente |
| Amarillo Aviso sobre **Papel** | **1,49** | 3,0 (1.4.11) | ✗ **no está en su auditoría** |

▶ **No es marginal: es invisible.** Quien navegue con teclado no verá dónde está en la mayor parte
del sitio.

⚠️ **Y no lo puede cazar ninguna guarda nuestra**, y eso también es un hallazgo: `SurfaceScopeTest`
calcula contrastes **de la raíz del PRODUCTO**, no de la del paquete. Los guardas leen
`public/css/*.css` y el orden alfabético hace que `client.css` quede **antes** que `landing.css` y
`site.css`, así que el producto gana y el paquete **no se mide**. Funciona a favor —las guardas
vigilan el producto, que es su trabajo— pero significa que **un paquete puede incumplir AA sin que
nada avise**.

▶ **`[PENDIENTE: owner]`**, con tres salidas y su coste:
1. **Anillo de dos tonos** —amarillo con un filo de tinta— como hace GOV.UK. Pasa en las dos
   superficies y **conserva el amarillo en todas partes**. Es un cambio en el mecanismo de foco del
   producto, no en el paquete.
2. **Un color de foco por superficie**: amarillo sobre tinta, Azul Muro `#0A5C93` (6,43) sobre
   papel. Barato, y rompe su regla de «único color que no cambia».
3. **Dejarlo**: 1:1 con el mockup, con el anillo invisible en papel. Es una decisión, no un
   descuido, y quedaría escrita como tal.

### 14.3 ⚠️ Lo segundo, que su sistema SÍ sabe: el rojo sobre tinta da 4,10

Su propia tabla lo marca «GRANDE · solo ≥19px bold» y ofrece `#FF8A6B` (8,02) para texto pequeño en
oscuro. Nuestro `--err` es **un solo token** y no distingue tamaño, así que dentro de una superficie
de tinta un error en cuerpo pequeño se queda en 4,10. **No lo arregla el paquete**: haría falta un
`--err-on-ink`, o que la superficie de tinta re-escope `--err` como re-escopa los otros siete.

### 14.4 ❗❗ La suite del producto NO dejaba convivir con una instalación — y el primer arreglo fue peor

Tres cosas se rompieron al aparecer `client.css` en el disco, y las tres son del producto:

**1 · Una guarda exigía que la hoja NO existiera.**
`ClientThemePackageTest::test_without_a_client_sheet_no_link_is_emitted` aseveraba
`assertFileDoesNotExist` a secas, así que **la suite se ponía roja en cuanto alguien montaba un
paquete de cliente en su máquina** — justo lo que el mecanismo existe para permitir.

**2 · El primer arreglo lo empeoró, y lo cazó el gate.** Se le puso el `markTestSkipped` que sus
hermanos ya tenían… y **saltar mueve el contador de aserciones**: 17.977 en la máquina con paquete
frente a 17.991 en la que no lo tiene. El `pre-push` compara el número EXACTO contra `ESTADO.md`,
así que **una de las dos máquinas quedaba bloqueada siempre** — y con dos carriles trabajando a la
vez, eso es bloquear al otro agente por tener un tema instalado.
▶ **Un gate que depende de si el disco tiene el tema de un cliente no es un gate.**

**3 · Lo que sí lo arregla, y es de fondo.** Dos cambios:
· Los tres casos corren sobre un **`public/` propio** (`usePublicPath()` a un directorio temporal,
  con `build/` enlazado porque el layout resuelve ahí el manifiesto de Vite). «Sin hoja» pasa a ser
  un hecho **que decide el test**, no el disco de quien lo ejecuta — y **la hoja del cliente no se
  toca jamás**.
· **Las cinco guardas de CSS dejan de juzgar `client.css`.** Barrían `public/css/*.css` y ahí no
  vive solo el producto. Un paquete de tema **está hecho de literales**: juzgarlo con
  `RawColourIsNotATokenTest` sería prohibirle existir. Y una guarda que asevera **por hoja** movía
  el recuento según la máquina.
  ▶ Mismo criterio que `SidebarStyleWiringTest`, que **enumera** las hojas del producto en vez de
  barrer la carpeta. Lo que se pierde no es cobertura: es jurisdicción sobre algo que no es nuestro.

▶ **Verificado midiendo las dos veces**: con paquete y sin él, **3.127 tests y 17.990 aserciones,
idénticos**. Es la comprobación que faltaba, y no la habría dado nadie sin montar el paquete.

### 14.6 Las dos decisiones del owner sobre el paquete, y cómo salieron

**1 · El anillo de foco: UN COLOR POR SUPERFICIE** (`[DECIDIDO owner, 2026-08-28]`, de las tres
salidas de §14.2). Amarillo Aviso sobre tinta, **Azul Muro `#0A5C93` sobre papel**.

▶ **Y salió más barato de lo que parecía: NO hace falta tocar el producto.** Su `--focus-color`
vale `var(--fg)` precisamente para seguir a la superficie solo — **era el paquete el que lo estaba
rompiendo** al fijarlo a un literal. Se arregla declarándolo en los dos ámbitos, que es el
mecanismo de la tanda 1 haciendo su trabajo.

| El anillo, con la decisión aplicada | Ratio | Exige |
|---|---|---|
| sobre papel `#F4F4F1` | **6,43** | 3,0 ✓ |
| sobre tarjeta blanca | **7,08** | 3,0 ✓ |
| sobre nube `#E8E9E5` | **5,81** | 3,0 ✓ |
| sobre tinta `#101418` | **11,26** | 3,0 ✓ |
| sobre tarjeta de tinta `#1A1F25` | **10,09** | 3,0 ✓ |

⚠️ **Y de aquí sale un aviso que va en la receta de instalación**: fijar `--focus-color` a un
literal rompe la inversión automática, y **ninguna guarda del producto lo caza** —calculan sobre la
raíz del producto, no sobre la del paquete—. Si una marca trae color de foco propio, **tiene que
declararlo por superficie y comprobar los dos contrastes**.

**2 · El logotipo: un SVG por instalación** (`[DECIDIDO owner, 2026-08-28]`). Entra por
`public/img/client-logo.svg`, con **las mismas tres piezas** que la hoja de tema —no se versiona,
se carga si existe, `deploy.sh` lo excluye del `--delete`— y el **suelo** es el nombre en la fuente
de rótulo, que es lo que el producto sabe pintar sin saber nada del cliente.
⚠️ **El `alt` lleva el nombre del sitio y no es opcional**: es el único enlace que TODA página
tiene, y sin `alt` se queda sin nombre accesible. Hay guarda y su mutación muerde.
⚠️ **Coste aceptado por el owner**: su logotipo es un lockup de seis capas en CSS, así que hay que
**exportarlo a SVG** y se pierde poder retocarlo desde el canvas.
▶ De paso, **la marca se pintaba en DOS sitios** —el racimo y la cabecera del cajón— y ahora sale
de un solo componente.

### 14.5 Lo que el paquete NO puede cambiar, medido con él puesto

> ⚠️⚠️ **CORRECCIÓN, y va delante del texto que corrige (2026-08-28, `#209`).** El primer punto de
> esta lista —el relleno de acción— **ya está resuelto**: es la §15. Dos de sus cifras eran además
> imprecisas y se corrigen ahí con dos instrumentos independientes que coincidieron: no son «50
> reglas» sino **52**, y de ellas son acción **13**, no «unas». El segundo punto, el logotipo,
> **también dejó de ser cierto** con `#206`: existe el hueco de `public/img/client-logo.svg`.
> ▶ Lo único vigente de esta sección son **las cinco excepciones de sombra**.

- ❗ **El relleno de ACCIÓN.** El sistema del cliente pinta el CTA primario en **Naranja Salto**;
  el producto lo tiene atado a `background: var(--fg)` —tinta— en `.cta-prime`, `.cta-med` y
  `.btn`. **No hay token de acción**, así que el botón de comprar **no puede ser naranja** sin
  tocar el producto. Medido: **50 reglas** rellenan con `var(--fg)`, y no todas son acción —unas
  son superficie invertida—. Es el mismo problema que la elevación: hay que preguntar **«¿para qué
  sirve cada relleno de tinta?»** y sacar un rol, no una lista.
  ▶ **Es el QUINTO mecanismo del tema**, y hasta que exista el paquete no puede ser 1:1.
- **El logotipo.** El del cliente es un lockup de seis capas con dos degradados; el nuestro es
  texto. No hay mecanismo de logotipo y no se ha inventado uno aquí.
- **Las cinco excepciones de sombra** de §13.5, ya enumeradas.

---

## 15. EL RELLENO DE ACCIÓN — el QUINTO mecanismo (`#209`, 2026-08-28)

> ⚠️⚠️ **CORRECCIÓN (2026-08-28, `#213`), y va delante del texto que corrige: el rol tiene 12
> reglas, no 13.** `.cta-med` —el CTA del armazón— **salió del rol de ACCIÓN** por decisión del
> owner: su color no lo manda un rol sino una COREOGRAFÍA (tinta con el menú cerrado, **aviso**
> mientras el menú lo tapa), que es lo que hace el mockup del 2.º cliente. Su hermano `.cta-prime`
> —la barra de compra de móvil— sigue en el rol, y ahí el mockup también lo pinta de acción.
> ▶ **No es una incoherencia nuestra: es una diferencia real entre las dos piezas**, y el detalle
> —con las tres fuentes del cliente que se contradicen— está en `specs/armazon-y-menu.md` §7.
> ▶ Y las reglas COMPARTIDAS por `.cta-prime` y `.cta-med` (el glifo, el «occluder», el subtítulo)
> **hubo que partirlas**: era la mitad del trabajo que una conversión apresurada se deja.

> ⚠️ **Ojo con el número `#207` si lo ves en el código**: es el de la base heredada («Fase 7.7 ·
> Temporadas»), NO el de esta tanda. Aquí el número se fija al EMPUJAR (`CONVENCIONES §10`) y el
> carril A se llevó el `#207` y el `#208` mientras esto se escribía.

> **Estado: CÓDIGO COMPLETO y verificado en navegador.** Falta el ojo del owner, que ya tenía
> cinco tandas visuales pendientes y ahora son seis.
> ▶ **Si vienes a tocar un botón, lee §15.3 y §15.6 y nada más.**

Montar el paquete del 2.º cliente (`#206`) destapó lo único que su marca no podía pedirle al
producto: **su botón de comprar es naranja y el nuestro estaba atado a `background: var(--fg)`**.
No había token de acción, así que **ninguna instalación podía pintar el CTA de otro color** sin
tocar el producto. Era el último obstáculo para que la landing fuese 1:1 con el mockup.

### 15.1 La pregunta no era «¿qué botones son oscuros?»

Esa pregunta da una **lista**, y una lista envejece con el primer botón nuevo. La que sirve —la
misma que sacó los tres roles de sombra en `#196`— es **«¿para qué sirve cada relleno de tinta?»**.

Medido con **dos instrumentos independientes que coincidieron** (un parser de pila y un barrido por
cuerpo de regla; se cruzaron a propósito porque en este repo un inventario de CSS ya ha salido mal
cinco veces):

| | reglas | qué son |
|---|---|---|
| **ACCIÓN** | **13** | el control PRIMARIO que hace avanzar: comprar, reservar, enviar, confirmar |
| superficie invertida | 12 | un parche de tinta que hace de fondo: `.flash`, `.skip-link`, pegatinas, el avatar |
| hover que invierte | 15 | un botón fantasma o secundario que se rellena al pasar el cursor |
| estado seleccionado | 6 | pestaña activa, idioma abierto, paso hecho |
| decoración | 6 | manchas, viñetas, puntos de línea de tiempo |
| **total sólidos** | **52** | (+ 19 velos con `color-mix`, que son otra cosa) |

❗ **Las 39 que no son acción se quedan en `var(--fg)` A PROPÓSITO**, y no por prudencia: el propio
sistema del cliente lo dice —«**en claro el secundario es tinta, no cian**: cian sobre papel da 2,45
y el borde se come el texto»—. Pintar de acción un hover fantasma rompería además su prohibición
explícita: «**✕ TRES PRIMARIOS — un solo botón de relleno de acción por pantalla**».

### 15.2 Lo que el sistema del cliente declara, textualmente

Del artboard normativo `Colores de Marca PJP` (§05 ROLES y §06 BOTONES), leído del canvas fresco:

- **Naranja Salto `#F2711C`**, masa 36 % — rol: «**Acción. Reservar, comprar, enviar. Un único
  relleno naranja por pantalla.**» · `hover #D56319` · `press #B85615` · texto `#101418`.
- **CTA primario**: «**Idéntico en ambos fondos, siempre con texto tinta.**»
- **CTA secundario**: «Cambia de color según el fondo; el relleno nunca compite.»
- Anatomía: radio 10 px, alto mín. 48 px, peso 800, foco 3 px.

▶ La segunda línea es la que manda la arquitectura: **el color de acción NO sigue a la superficie**,
al revés que los ocho tokens de la tanda 1.

### 15.3 ❗ El conflicto real, y cómo caben las dos conductas en una sola línea

El producto **necesita** que su CTA siga a la superficie, y no es un capricho: `.cta-med` vive
dentro de `.nav`, que **pasa a `data-surface="ink"` al abrir el menú**. Hoy el botón se invierte
ahí; si dejara de hacerlo sería **un relleno oscuro sobre un menú oscuro**.

Y el cliente **necesita lo contrario**: el mismo naranja en los dos fondos.

Las dos caben porque **el fallback de `var()` se evalúa donde la propiedad se DECLARA** (el mismo
hecho de CSS que ya obligó a declarar los `--ink-*` en `:root`, §4.1):

```css
--action:          var(--action-brand, var(--fg));
--on-action:       var(--on-action-brand, var(--bg));
--action-hover:    var(--action-brand-hover, var(--zone-1));
--on-action-hover: var(--on-action-brand-hover, var(--on-brand));
```

Estas cuatro líneas se declaran **TRES veces**: en `:root`, en `[data-surface="ink"]` y en
`[data-surface="paper"]`. Con eso:

- **sin `--action-brand`** → en cada ámbito el fallback resuelve contra el `--fg` de ESA superficie
  → el botón **sigue a la superficie**, exactamente como el producto hacía antes de esta tanda;
- **con `--action-brand`** → gana en los tres a la vez → **idéntico en los dos fondos**.

▶ **Y `--action-brand` no se declara en ninguna hoja del producto.** Lo emite `ThemeSettings` en
`<style id="jj-theme">` y **solo si la instalación tiene color de acción**. Declararlo vacío no es
neutral: anularía el fallback y dejaría el botón primario **sin relleno**, con la página cargando
igual. Hay guarda con mutación para eso.

### 15.4 El dato vive en el PANEL, no en `client.css`

Campo nuevo `theme.action` (Ajustes → Aspecto de la web), hex o **vacío**. ⚠️ **Vacío es una
respuesta, no una falta**: significa «el botón se adapta al fondo», que es la conducta histórica.
Por eso `ThemeSettings::action()` es el único getter de la clase que devuelve `null` — todos los
demás tienen color por defecto, y ponerle uno aquí ataría **toda** instalación a un relleno fijo.

De un solo dato salen cuatro valores. El hover **se deriva**, y el número no es de gusto:

| par | factor medido |
|---|---|
| cliente `#F2711C` → `#D56319` (su hover) | **×0,88 en los tres canales** |
| cliente `#F2711C` → `#B85615` (su press) | ×0,76 |
| producto `--err` `#c0392b` → `#a93226` | **×0,88** |

❗ **El 0,88 acierta el hover del cliente al byte y también el que el producto ya tenía escrito.**
Derivarlo, en vez de pedir un segundo campo, deja **un solo dato que mantener** y da el valor
correcto. Sobre un color casi negro (luminancia < 0,08) se **aclara** en la misma proporción, para
que el estado siga existiendo.

### 15.5 ⚠️⚠️ Lo que costó: `onBrand()` NO vale para un botón, y lo dijo la guarda

La primera versión reutilizaba `ThemeSettings::onBrand()` para el texto sobre el relleno. **El test
salió rojo con el color del propio cliente**: sobre su hover `#D56319` elegía **blanco** y daba
**3,73**.

La causa es una preferencia estética escrita hace tiempo y perfectamente razonable en su sitio:
`onBrand()` **prefiere blanco** sobre el acento y solo cae a tinta por debajo de **3,0** — que es el
umbral de **texto GRANDE**. Un acento decorativo puede permitírselo; **el rótulo de un botón de
15–18 px, no**.

▶ `onAction()` no tiene preferencia: **elige el de más contraste de los dos**, que es literalmente
lo que hace la auditoría del propio cliente. Sobre `#D56319` elige tinta y da **4,99**.

⚠️ **Y hay un suelo aritmético que conviene saber**: con un relleno de luminancia ≈ 0,19, tinta y
blanco **empatan en 4,31** — no existe texto que pase AA sobre ese color. El helper devuelve el
mejor de los dos y la guarda fija ese suelo. **Avisar al operador de que su color no llega a AA
está `[PENDIENTE: owner]`** (§15.8).

### 15.6 Las TRES excepciones, y por qué no son deuda escondida

Enumeradas en `ActionFillTest::EXCEPTIONS`, y **la lista solo puede encoger**:

| regla | qué no se convirtió | por qué |
|---|---|---|
| `.btn:hover` | el `color` | pinta con `var(--fg)` donde las otras diez usan `--on-brand`. Es una **incoherencia previa del producto**: el mismo fondo recibe dos colores de texto en la misma hoja. Convertirla volvería el texto blanco al pasar el cursor — eso es diseño, no mecanismo. Ficha en `DEUDA.md` |
| `.price--feat .price__cta:hover` | los dos | ese CTA **invierte** al pasar el cursor en vez de oscurecerse: es otro patrón de hover, no el del rol |
| `.cta-prime:hover .cta-prime__ico` | el `background` | es un **velo del 10 %** sobre el relleno, no el relleno; funciona sobre cualquier color |

### 15.7 Cómo se verificó

- **Dos instrumentos** para el inventario, cruzados: coinciden en 52.
- **`ActionFillTest`** (11 casos): el conjunto de reglas de acción es *exactamente* el declarado ·
  ninguna conversión a medias · las piezas internas del CTA siguen al relleno · el conmutador no se
  emite sin dato ni con basura · el hover acierta `#D56319` · el texto pasa AA y **siempre es el
  mejor de los dos** (barrido por toda la escala de grises).
- **`SurfaceScopeTest`** gana el rol como constante propia (`ROLE_TOKENS`, separada de
  `SURFACE_TOKENS` porque **significan cosas distintas**) y un caso que exige la indirección en los
  tres ámbitos.
- **11 mutaciones, las 11 muerden**: un CTA que vuelve a tinta · una pegatina pintada de acción ·
  la conversión a medias · una pieza interna que se queda atrás · el fallback perdido · el
  conmutador declarado en el producto · emitido siempre · la preferencia estética de vuelta · el
  factor del hover movido · el aclarado de casi-negro retirado · un hex inválido emitido.
  ⚠️ La detección es por **código de salida**, nunca por `grep`: en esta shell `grep` es una función
  interpuesta y en `#195` un arnés dio «0 de 12» con el test perfecto por eso.
- **Sonda de navegador (Playwright), 12/12** — es la única prueba de que las dos conductas existen:
  con color de acción el botón da `rgb(242,113,28)` **dentro y fuera** del menú de tinta y su hover
  `rgb(213,99,25)`; borrando `--action-brand` en vivo, **claro dentro** (`rgb(244,244,241)`) y
  **tinta fuera** (`rgb(16,20,24)`).
  ▶ **El guion está escrito paso a paso en `VERIFICACION-E2E-CAJON.md` §5.duodecies**, con los
  valores esperados y lo que NO cubre. Es el que tiene que recorrer el owner.
  ⚠️ **Dos trampas del armazón que la sonda pagó**: el CTA del nav **nace oculto** (`navCtaReveal`)
  y la barra **se retira al bajar** (`nav--hidden`) — hay que bajar para destaparlo y volver a
  subir un poco para que esté en pantalla, o Playwright espera a un elemento invisible.

### 15.8 Lo que queda

- ❗ **El ojo del owner** en navegador. Con esta van **seis** tandas visuales sin mirar.
- `[PENDIENTE: owner]` **Avisar en el panel cuando el color elegido no alcance AA** (§15.5). Hoy el
  helper elige el mejor texto posible y calla. Opciones: no hacer nada · una ayuda que lo diga ·
  un aviso vivo bajo el campo con el número.
- **El PRESS no entra**, y no es un olvido: el producto **no tiene** estado de pulsado en color
  (sus `:active` solo escalan). El sistema del cliente sí lo declara (`#B85615`, ×0,76). Añadirlo
  cambiaría la conducta del producto en un estado que hoy no existe — es diseño, no mecanismo.
- ⚠️ **`[data-surface="ink"] .cta-prime__ico` y `.cta-prime__s` pueden estar muertas**: existían para
  cuando el CTA grande vivía dentro del hero, y el hero perdió su CTA en `#195`. No se retiran aquí
  —retirar código viejo tiene su propio protocolo (`CONVENCIONES §3.quater`)—; ficha en `DEUDA.md`.

---

## 16. EL MOVIMIENTO — el SEXTO mecanismo del tema (`#222`, tanda 2d, 2026-08-28)

> `[DECIDIDO owner, 2026-08-28]`, a pregunta simple: **toda la web**; y el **color sigue cambiando
> en hover** donde ya estaba validado.

### 16.1 Lo que había: 53 duraciones y una curva que nadie elegía

Medido antes de tocar nada. ⚠️ **Y la primera medida fue reconciliar la doc**, que decía «237
declaraciones, 48 duraciones, 20 curvas»: esos números contaban `transition` **+** `animation`, y
con ese mismo criterio hoy salen **239 · 53 · 20**. No había envejecido; estaba contando otra cosa
de la que yo empecé contando.

| | |
|---|---|
| declaraciones de movimiento | **239** |
| duraciones distintas | **53** |
| curvas distintas | **20** |
| ❗ **usos de curva que eran `ease`** | **200 de 220** |

▶ **Ese último número es toda la tanda.** `ease` es la curva por defecto del navegador: el **90 %
del movimiento de la web no lo decidía nadie**. No era una escala con ruido — **no había ninguna**,
igual que con las sombras en §13.

### 16.2 La escala no se extrae: se toma del cliente y se traduce a ROLES

`Microanimaciones PJP` declara **4 curvas y 7 duraciones**, cada una con su uso escrito. Se traducen
a roles del producto —los nombres describen para qué sirve el tiempo, no la metáfora de la marca—:

| Curva | Valor | Para qué |
|---|---|---|
| `--ease-entra` | `cubic-bezier(.34, 1.56, .64, 1)` | lo que **aparece**: se pasa de largo y vuelve |
| `--ease-cae` | `cubic-bezier(.2, 1.56, .25, 1)` | el rebote **grande**: uno por pantalla |
| `--ease-sale` | `cubic-bezier(.4, 0, .2, 1)` | cierres, foco y **todo el hover**: sin opinión |
| `--ease-bucle` | `linear` | solo esperas: un easing parece un fallo de red |

`--dur-toque` 120 · `--dur-sale` 180 · `--dur-estado` 240 · `--dur-entra` 320 · `--dur-cae` 420
(**el techo**) · `--dur-salto` 620 (la única excepción) · `--dur-espera` 900.

⚠️ **Tres principios ordenan la tabla, y explican que no sea simétrica:**
1. **Lo que entra rebota; lo que sale, no** — y sale en la **mitad** de tiempo. Un modal que rebota
   al cerrarse parece que ha fallado.
2. **El sobreimpulso se paga en píxeles**: es desplazamiento y escala, nunca opacidad ni color.
3. **Nada en bucle salvo las esperas.**

### 16.3 ⚠️⚠️ La conversión se hizo a MEDIAS la primera vez, otra vez

El primer filtro de «esto es un bucle ambiental, no lo toques» buscaba palabras —`float`, `scroll`,
`gallery`— **en los 260 caracteres anteriores**. Saltó **once declaraciones que sí había que
convertir**: `--shadow-float` mencionado en un comentario vecino salvó a `.lang-dd__panel`, y el
título «reveal on scroll» salvó a `.ride-card` y a tres `.reveal`.

▶ **Es exactamente lo de §11.4** —«el scrim tiene cuatro paradas y se convirtieron dos»— con otra
herramienta y dos tandas después. Se rehízo con un criterio **objetivo y leído en la propia
declaración**: lleva `infinite`, o está en una lista de **tres** animaciones de dibujo que son
largas a propósito (`tear-once`, `e2-fan`, `e5-deal`). *Un filtro que mira el contexto acierta hasta
que el contexto cambia.*

### 16.4 El escondite: las `custom properties`

`--cta-pair-swap: 0.46s` y `--cta-pair-in: 0.14s` vivían **dentro de una variable**, así que ningún
inventario de `transition` los veía. `MotionScaleTest` mira también ahí: **un literal metido en un
token sigue siendo un literal**.
▶ Entran en la escala (`--dur-cae` y `--dur-toque`), y con ellos **el spinner**: giraba a 1,4 s y
pasa a leer `--dur-espera` (900 ms) **conservando su token propio**, que es el punto por el que una
instalación ajusta su cargador desde el marcado (`sistemas/UI-SPINNER.md`).
▶ Y los cuatro tokens de `#42` —`--dur-collapse`, `--dur-fade`, `--ease-panel`, `--ease-bounce`, 36
usos— se re-apuntan y **desaparecen**: eran un parche, no un sistema.

### 16.5 Lo que queda FUERA, y por qué no es deuda

Los **bucles ambientales**: marquesinas, iconos que laten, badges que botan, el latido del CTA doble
(`--dur-invite`). No son tiempos de respuesta a un gesto, así que no compiten con los siete.
⚠️ El sistema del cliente los llamaría **ruido** —«banners que respiran, flechas que se balancean,
iconos que laten»— pero **retirarlos es una decisión de producto, no de mecanismo**, y el owner ya
dejó la tanda 3 fuera de alcance. Aquí solo se declaran.

### 16.6 Verificación

- **608 usos de token · CERO duraciones y CERO curvas literales fuera de la escala** · 25
  declaraciones ambientales, sin tocar.
- **`MotionScaleTest` nuevo (4 casos)** · **7 mutaciones, las 7 muerden** — incluidas «alguien
  esconde un literal dentro de una custom property» y «vuelve el `ease` heredado».
- Sonda de navegador: los once tokens resuelven, la coreografía del armazón sigue **exacta**
  (`--nav-p: 0.561` a scrollY = 120) y `prefers-reduced-motion` sigue apagando lo que apagaba.
- ❗ **Y lo que ninguna sonda puede medir: cómo se siente.** Esta tanda no se revisa con una
  captura, se revisa **interactuando**. Si algo va lento o brusco, el número está en un token.

---

## 17. LA COLUMNA — un sangrado en porcentaje mide al PADRE (`#238`, 2026-08-28)

`[DECIDIDO owner, 2026-08-28]`: **la tarjeta del hero del cierre va en la columna del mockup**
(1240 con sangrado propio → **1176 de contenido**), no en la del resto del producto.

**El síntoma que lo destapó fue el del owner, y era el estado en reposo del hero del cierre**: «no
tiene el width correcto, tiene demasiada altura y oculta el footer». Las tres cosas eran ciertas y
salían de **dos defectos distintos** que se sumaban.

### 17.1 ⚠️⚠️ El primero: `--wrap-gutter` dentro de una caja ACOTADA

```css
--wrap-gutter: max(40px, calc((100% - 1380px) / 2));
```

Ese `100%` es un **porcentaje**, así que se resuelve contra el **contenedor** del elemento, nunca
contra el elemento. En algo que ocupa todo el ancho —el `.nav`, el escenario del hero— significa
exactamente lo que dice: «40 px, o lo que haga falta para centrar una columna de 1380». Para eso
existe y ahí está bien usado.

▶ **En un elemento que YA está acotado por su propio `max-width` significa otra cosa**: el sangrado
sigue creciendo con la ventana mientras la caja no puede. La columna se estrangula.

| ancho de ventana | sangrado | tarjeta del cierre | columna del menú |
|---|---|---|---|
| 1280 | 40 px | 1160 | 1160 |
| 1440 | 40 px | 1160 | 1160 |
| **1920** | **270 px** | **700** | **700** |
| **2560** | **590 px** | **160** | **60** |

⚠️⚠️ **Y el menú a pantalla completa tenía el MISMO defecto, sin que lo viera nadie.** `.menu__inner`
también está acotado a 1240 y también se sangraba con `--wrap-gutter`. Llevaba así desde que se
escribió (`#201`), pasó por cinco tandas de armazón, por el ojo del owner y por 31 aserciones —y
**ninguna sonda de este carril corrió por encima de 1280 px**, que es justo donde el defecto empieza.

▶ **La lección de método, y es la cuarta vez que este carril la paga**: *no basta con medir; hay que
medir donde el fallo puede aparecer.* Un banco de sondas con dos anchos fijos —1280 y 390— demuestra
lo que pasa en 1280 y en 390, y nada más. Los defectos de columna viven en los extremos.

**El arreglo** es un token nuevo con un contrato distinto:

```css
--col-gutter: 32px;                                    /* 24 bajo 1100 · 16 bajo 720 */
```

Es una **longitud fija** a propósito: dentro de una caja acotada, un porcentaje mide el padre y
miente. Los tres escalones son los del mockup (`--pjp-margen`) y los dos cortes —1100 y 720— ya los
usaba este producto para el logotipo y el par de CTA, así que no se estrena ningún punto de ruptura.

⚠️ **`--wrap-gutter` no se retira ni se toca**: es correcto donde está (`.nav`, `.hero__stage-content`)
y esos dos casos son justo para lo que se escribió. Lo que se prohíbe es **mezclarlo con un tope
propio**, y de eso se encarga `CappedContainerGutterTest`.

### 17.2 El segundo: el hueco del minijuego estaba reservado DOS veces

El lienzo de «Salta la ciudad» es `absolute` y va pegado al canto inferior de la tarjeta, así que su
sitio lo aparta el `padding-bottom` de `.reserve__box` —`clamp(122px, 14vw, 156px)`, que es lo que
hace el mockup—. Pero `.reserve__body` volvía a apartar **la tira entera más un respiro**:

```css
.reserve__body { padding-bottom: calc(clamp(24px, 5vw, 80px) + var(--salta-h)); }   /* retirado */
```

Resultado: **220 px de aire muerto a 1280** (tarjeta de **762** de alto contra los **542** del
mockup) y 146 en un teléfono. ▶ **Y eso es lo que tapaba el pie**: anclada, la tarjeta ocupaba 772 px
de una ventana de 900 y del pie quedaba una franja de 128. Con una sola reserva quedan 348.

⚠️ *Dos reservas del mismo hueco no se ven por separado: se ven **sumadas**, y no parecen un fallo —
parecen «este bloque es alto».* Por eso ninguna captura lo cazó en cuatro pasadas.

### 17.3 Y de paso, dos divergencias más del mismo bloque

- **El alto del lienzo es 150 px, no una talla adaptable.** Era `clamp(122px, 14vw, 156px)` —la misma
  expresión que el hueco, que es **otra cosa**: aquélla es el sitio reservado, ésta el dibujo—. En el
  mockup `altoJuego()` devuelve `150` mientras nadie juega. Consecuencia doble: en un teléfono la
  tira salía a 122, y **al abrirse la tarjeta el motor le escribe `height: 150px` y saltaba 28 px de
  golpe**. Además el alto del lienzo **ES el zoom** (`k = alto / 300`, `#233`): a 122 se jugaba a
  0,41 en vez de a 0,5.
- **En teléfono el titular tiene OTRA escala** (`#220` otra vez). El mockup cambia de fórmula por
  debajo de 620 px; con un solo `clamp` mandaba el suelo y el titular salía **un 17 % grande en
  reposo y un 43 % en el estado final** a 320 px (54 px contra 37,8). Ahora
  `clamp(28px, 9.8vw, 52px)` → `clamp(34px, 11.8vw, 72px)`, que son los números del mockup.

### 17.4 ❗ Lo que queda ABIERTO, y es del owner

**La tarjeta del cierre y el pie ya NO comparten columna**, y en el mockup sí:

| | mockup | nosotros |
|---|---|---|
| tarjeta del cierre | 1176 | **1176** ✅ |
| pie y secciones | 1176 | **1380** (`.wrap`, decisión anterior del producto) |

A 1280 el escalón es de 12 px por lado y no se ve; **a 1920 son 102**. Igualarlos significa bajar
**toda la columna del sitio** de 1380 a 1240/1176 —doce vistas, no solo el pie—, que es una decisión
de producto y no se toma desde aquí. ▶ La alternativa (bajar solo el pie) deja el escalón en las
otras once vistas, donde no hay tarjeta de cierre: sería mover el problema, no resolverlo.

### 17.5 Verificación

- Sonda de navegador en **siete anchos** (390 · 768 · 1280 · 1440 · 1600 · 1920 · 2560), reposo y
  estado final: tarjeta **1176 × 542** constante de 1280 para arriba, **720 × 419** a 768, **358 ×
  521** a 390; anclada, `100vw − 20` × `100svh − 20` con `top: 10` en los siete. **Cero desbordamiento
  horizontal.** Columna del menú: **1176 en los cuatro anchos anchos** (era 700 y 60).
- El minijuego, intacto y re-medido: lienzo **150 (k = 0,5)** en reposo y **358 (k = 1,193)** jugando
  —los números de `#233`—, los CTA a `opacity: 0` con `pointer-events: none` al empezar, **cero
  errores de JavaScript** en los tres anchos.
- **`CappedContainerGutterTest` nuevo (4 casos)** · **4 mutaciones, las 4 muerden**: devolver
  `--wrap-gutter` a `.reserve`, devolvérselo a `.menu__inner`, devolver la segunda reserva del hueco
  y quitar la reserva que sí tiene que existir.
- Suite **3359 / 22.125** · Pint ✓.

---

## 18. LA COLUMNA DEL SITIO — un solo número, tres expresiones (`#250`, 2026-08-28)

`[DECIDIDO owner, 2026-08-28]`: **la columna del sitio es la del mockup** — **1176 px de contenido**
con un sangrado de 32 · 24 · 16, o sea la caja de **1240** que dibujan **todas** sus secciones.
La pregunta que lo abrió fue suya: *«para hacer la landing al mockup ¿debemos cambiar toda la
estructura? más ancha la landing ¿no?»*. Las dos mitades tenían respuesta medida, y las dos al revés
de lo que parecía.

### 18.1 No es más ancha: es más ESTRECHA

| | columna de contenido |
|---|---|
| el producto hasta hoy (`.wrap`) | **1380** |
| el mockup (`max-width:1240` + `--pjp-margen:32`) | **1176** |

▶ **Y había una prueba interna de que la columna buena era la suya**: nuestro hero de cabecera ya
acababa en **1240** (`--hero-w-end`, el número del mockup) mientras las secciones iban a 1380. El
producto tenía **dos columnas contradictorias** y nadie lo había decidido: se coló pieza a pieza.

### 18.2 No es «cambiar la estructura»: el sitio ya pasaba por UNA columna

Medido antes de tocar nada, en las **12 vistas públicas** a dos anchos: `.wrap`, el pie y el `.nav`
devuelven **exactamente el mismo número en todas** (1380/270 a 1920 · 1200/40 a 1280). Todo lo demás
que declara `max-width` en las hojas —480, 640, 820, 920…— son **medidas tipográficas**, no columnas.

El cambio son **tres declaraciones y dos borrados**:

```css
--col-max: 1176px;                                             /* el único sitio con el número */
--wrap-gutter: max(var(--col-gutter), calc((100% - var(--col-max)) / 2));
.wrap { width: min(var(--col-max), 100% - var(--col-gutter) * 2); }
/* y se van los dos overrides de `.wrap` por @media: eran una TERCERA escala de sangrado
   (cortes en 768 y 420) compitiendo con `--col-gutter` (1100 y 720) */
```

### 18.3 ⚠️ Tres formas del mismo número, y por eso el token

El sitio necesita expresar la columna de tres maneras, y las tres tienen que moverse juntas:

| forma | quién | para qué |
|---|---|---|
| `width` | `.wrap` | secciones, pie y páginas de contenido |
| SANGRADO | `--wrap-gutter` | lo que va a sangre completa y debe alinearse: el `.nav`, el hero |
| caja EXTERIOR (`--col-max + 2×g`) | `--hero-w-end` · `.reserve` · `.menu__inner` | los que se sangran por dentro |

▶ *Tres expresiones del mismo número escritas a mano son tres columnas esperando a separarse*, y
este producto ya lo vivió. Lo vigila **`ColumnIsDeclaredOnceTest`**, con su suelo declarado: por
encima de **1000 px** un ancho ya no es una medida, es la columna (la medida más ancha del producto
son 920).

⚠️ **La trampa de esa guarda**: `@media (max-width: 1080px)` **es** un `max-width` con un literal de
cuatro cifras, y hay once en estas hojas. Un barrido que no separe el prelude de un at-rule del
cuerpo de una regla sale rojo con el producto sano — o lo ablandan hasta dejarlo ciego. El caso del
instrumento lo comprueba explícitamente.

### 18.4 El REPOSO del hero del cierre, medido contra el artboard: 27 de 28

`[DECIDIDO owner]`: «el estado normal del hero del footer, no full viewport, con las dimensiones
correctas al mockup». Se comparó **cada dimensión declarada** del `<section id="reservar">` contra la
computada en el navegador, a 1280 y a 390:

**Coinciden las 28 menos una**: sangrado y margen de la sección · los tres rellenos de la tarjeta ·
canto · sombra · alto del lienzo · sitio de la invitación · talla e interlínea del titular · grosor
del contorno · ancho, margen, talla e interlínea del párrafo · margen, hueco, alto, relleno, talla,
base flex y ancho máximo de los CTA · las tres medidas del tag.

⚠️ **Dos de las cuatro «divergencias» que dio el primer comparador eran del INSTRUMENTO o del
paquete del cliente, no del código** — y comprobarlo antes de tocar ahorró dos cambios equivocados:
- **el tag salía 108 contra 102,4**: va girado −7°, y `getBoundingClientRect()` devuelve la
  envolvente del giro. `102,4·cos7 + 53,5·sin7 = 108,2`. El ancho real es exacto.
- **canto de la tarjeta y sombra**: el comparador esperaba 24 y «ninguna», y salían 24 y ninguna —
  porque **`client.css` ya lo dice** (`--r-lg: 24px`, `--shadow-lift: none`). El paquete del cliente
  estaba haciendo su trabajo; el producto no tenía nada que arreglar.

❗ **La única divergencia real es el canto de los CTA: 10 contra 14**, y es una **contradicción
interna del cliente**. Su escala de forma declara seis escalones —0 · 6 · 10 · 16 · 24 · 999— y **14
no está en ella**; medido en su propio artboard, **12 de sus 19 botones usan 10 y solo 3 usan 14**,
los del cierre. Manda su escala declarada. Ficha en `DEUDA.md`: es la **quinta** contradicción de sus
fuentes, y cambiarla sería **una línea de su `client.css`**, no código del producto.

### 18.5 Verificación

- **12 vistas × 2 anchos**: las 24 a **1176**, con `.wrap`, el pie y el `.nav` alineados al píxel.
- **13 anchos × 6 vistas = 78 combinaciones** (360 → 2560): **cero desborde de página** y **cero
  errores de JavaScript**. ⚠️ El detector marcó 31 elementos «fuera de ventana» — y **el control con
  la columna anterior marcó los mismos 31**: son desbordes de diseño preexistentes (las marquesinas,
  la tira de zonas, la polaroid girada). *Sin el control se habrían reportado 31 roturas inventadas.*
- **El hero no se movió**: 1224 · 1240 · 1240 · 1240 · 366 antes y después, porque su ancho final ya
  era la caja exterior de esta columna.
- El menú, la tarjeta del cierre y el minijuego, re-medidos y sin cambio (150 / k = 0,5 en reposo;
  358 / k = 1,193 jugando).
- **`ColumnIsDeclaredOnceTest` nuevo (3 casos)** · **6 mutaciones, las 6 muerden**: devolver 1380 a
  `.wrap`, escribir 1240 a mano en la tarjeta o en el menú, clavar el número en el sangrado o en el
  hero, y cambiar la columna sin decidirlo.
- El lector de hojas se extrae a **`Tests\Support\ReadsSiteStylesheets`**: tres guardas lo
  necesitaban y cada una se lo escribía. Es la respuesta de `#233` otra vez — *cuando algo está en
  dos sitios, la salida no es retirarlo de uno, es que haya una definición*.
- Suite **3364 / 22.147** · Pint ✓ · docs-check ✓.

---

## 19. LA TRANSICIÓN DEL CIERRE — dos progresos con nombres parecidos (`#251`, 2026-08-28)

**La pregunta del owner fue «¿la transición es idéntica al mockup?»**, y era una pregunta que no se
podía contestar mirando: la geometría de un fotograma suelto **siempre parece correcta**. Se contestó
transcribiendo su `aplicaCierre` a la sonda y comparando **fotograma a fotograma**, alimentando su
fórmula con nuestros mismos datos de entrada (la caja natural, `vh`, `vw`, el máximo de scroll y el
pie).

### 19.1 Lo que ya era idéntico

Con **17 posiciones de scroll** dentro del recorrido y a dos anchos:

| | peor desvío |
|---|---|
| progreso | **0,0005** (es el redondeo a milésimas del propio publicador) |
| ancho de la tarjeta | **0,0 px** |
| alto de la tarjeta | **0,5 px** |

O sea que el crecimiento —el gesto— ya era el suyo: mismo recorrido, mismo `q`, misma curva
(`0,22·q + 0,78·smoothstep(q)`), mismos destinos (`100vw − 20`, `100svh − 20`, `top: 10`).

### 19.2 ⚠️⚠️ Lo que NO lo era: la retirada del armazón

El desvío estaba donde no se estaba mirando. **Dos causas encadenadas**, y ninguna la veía nada:

**1 · La curva era LINEAL y en el mockup es CÚBICA.** Él calcula `v = ne · (1 − salida)³`; aquí
estaba como `1 − salida`. Con la cúbica el armazón cae de golpe al principio y luego se apaga; con
la lineal se va repartiendo, y **se queda puesto medio recorrido de más**.

**2 · Y leía el progreso EQUIVOCADO** — la de verdad. La coreografía produce **dos** números:

| | qué es | qué mueve |
|---|---|---|
| `q` | el progreso **CRUDO** del scroll | la retirada del armazón · el umbral de «ya llena» · el arranque del minijuego |
| `e` | el mismo, **SUAVIZADO** | la geometría: ancho, alto, izquierda y la talla del titular |

En el mockup **solo `e` entra en los `lerp`**; todo lo demás va con `q`. Aquí `#229` usó
`--cierre-p` —el suavizado— también para la salida, porque los dos se llaman «el progreso». Y el
suavizado **va por detrás** del crudo justo en el tramo donde el armazón tiene que irse.

▶ **Medido, al 7 % del crecimiento**: el armazón del mockup valía **0,19** de opacidad y el nuestro
**0,82**. Con la cúbica pero leyendo el suavizado, **0,55**. Leyendo el crudo, **0,19**.
Peor desvío de la retirada en las 17 posiciones: **0,62 → 0,005**.

⚠️ *Dos progresos con nombres parecidos son dos progresos que alguien intercambiará.* Por eso ahora
se publican los dos, con nombre distinto, y hay una guarda —`CierreChoreographyTest`— que asevera
**las dos mitades**: que la retirada y los umbrales leen el crudo, y que la geometría lee el
suavizado. **6 mutaciones, las 6 muerden.**

⚠️ Y como efecto lateral, dos umbrales quedan donde el mockup los pone: `cierre--live` —lo que saca
al armazón del hit-testing— salta **exactamente cuando la retirada termina** (`q = 0,30`), y el
minijuego arranca en `q > 0,985` en vez de en `e > 0,985`, que caía en `q ≈ 0,955`.

### 19.3 La única divergencia que se deja a propósito

El mockup interpola el canto de la tarjeta de **26 a 24**; el nuestro es **24 constante**. Son 2 px
al principio del recorrido, y se queda así con motivo: **26 no está en la escala de forma declarada
por el propio cliente** (0 · 6 · 10 · 16 · 24 · 999), así que el constante 24 es *más* fiel a su
sistema que el `lerp` de su artboard. Es hermana de la del canto de los CTA (`DEUDA.md`).

### 19.4 ⚠️ Una guarda propia se puso roja con el producto sano

`ArmazonContractTest` aseveraba la retirada por el **nombre del token** (`var(--cierre-salida)`).
Al meter la cúbica en un token intermedio, la retirada seguía ahí —con otro nombre— y la guarda
falló. ▶ Se re-apunta **resolviendo la cadena de `var()`** hasta el final y exigiendo que la
opacidad dependa del progreso del cierre, se llame como se llame lo de en medio. Es la misma lección
que esa misma aserción ya había aprendido una capa más abajo con `--nav-p`, escrita en su propio
comentario: *aseverar el texto literal ata la guarda a una implementación.*

### 19.5 Verificación

- **17 posiciones × 2 anchos** contra la fórmula del artboard: progreso ≤ **0,0005** · ancho **0,0
  px** · alto ≤ **0,5 px** · retirada del armazón ≤ **0,005**. Solo el canto se aparta (2 px, a
  propósito).
- Minijuego intacto y re-medido tras mover su umbral: 150 / k = 0,5 en reposo, 358 / k = 1,193
  jugando, **cero errores de JavaScript** en tres anchos.
- **`CierreChoreographyTest` nuevo (5 casos)** · **6 mutaciones, las 6 muerden** — incluida la que
  reproduce el fallo real (devolver la retirada al progreso suavizado).
- Suite **3382 / 22.296** · JS **835** · Pint ✓ · docs-check ✓ · build ✓.
- ❗ **Lo que ninguna sonda puede medir: cómo se siente.** Como la tanda 2d, esto no se revisa con
  una captura — se revisa **bajando hasta el final de la portada** y mirando cuándo desaparece el
  armazón.

---

## 20. EL IMÁN DE LOS DOS PUNTOS ESTÁTICOS (`#252`, 2026-08-29)

`[DECIDIDO owner, 2026-08-29]`, y conviene citarlo entero porque el encargo trae su propio criterio:

> «La sección de hero header y hero footer tienen **un punto del vw donde es el estático**, donde se
> ve todo perfectamente… y a partir de ahí al hacer scroll hacia abajo empieza la transición hacia
> full viewport. Ese punto estático quiero que tenga **como un imán**, que al hacer scroll de alguna
> manera se pare ahí por un momento, para que no ocurra que el cliente hace scroll y **se pierde ese
> punto donde se ve todo en su sitio**. Que no se ejecute la transición de full viewport sin querer
> en el footer.»

### 20.1 Los dos puntos, y por qué son estados y no fotogramas

| | dónde está | qué se ve |
|---|---|---|
| cabecera | `y = --hero-runway` | el hero ya encogido a tarjeta, con logotipo, par de CTA, hamburguesa y tira de marca colocados |
| cierre | `y = maxY − recorrido del cierre` | la tarjeta del cierre en reposo y **el pie entero debajo**: tira, los enlaces, idioma y legales |

Cualquier punto intermedio de esos dos recorridos es un hero a medio encoger o una tarjeta a medio
crecer: no es un estado, es un fotograma. El imán existe para que el visitante **caiga siempre en un
estado**.

### 20.2 ⚠️⚠️ NO es el `freno` del mockup, y la diferencia es el encargo

El mockup tiene un mecanismo así (`vigilaFreno` + `encaja`), y de él se toma **todo el envoltorio**:
el retardo de **170 ms** desde el último evento, la curva `1 − (1 − p)³`, la duración
`clamp(280, |Δ|·1,1, 620)`, el **enfriamiento de 1 s** y que **cualquier gesto cancele**.

▶ **Lo que NO se copia es a dónde encaja.** El suyo va hacia el lado al que ibas, así que **bajando
por el cierre te lleva a pantalla completa** — justo lo que el owner pidió que no pasara. Aquí
bajando te lleva **primero al punto estático**, y solo un segundo empujón deliberado —pasado el
**45 %** del recorrido— completa la transición. Subiendo, siempre al punto estático: nunca se empuja
hacia abajo a quien sube.

### 20.3 ⚠️⚠️ El rumbo NO se puede sacar del último evento de scroll

La primera versión comparaba cada evento con el anterior. **Pasó los 16 casos unitarios y falló en el
navegador**, en un caso concreto: subiendo desde el fondo, el imán devolvía al visitante a pantalla
completa.

La causa, medida con una línea de tiempo de posiciones: **al llegar al final la portada crece 34 px**
—hay 55 imágenes, 51 perezosas y ninguna declara su proporción (ficha en `DEUDA.md`)— y el anclaje de
scroll del navegador compensa moviendo la posición. Eso llega como **un evento de scroll hacia
abajo**, y el detector se lo creyó.

▶ **El rumbo es el movimiento NETO desde la última parada**, con un mínimo de 24 px para creerse que
hay un gesto detrás. Un reflujo de 34 px dentro de un gesto de 200 ya no cambia el signo.
⚠️ *Un detector de dirección que cree cada evento está creyendo también a los que no ha hecho nadie.*

### 20.4 Dónde vive, y por qué

En `resources/js/ui/scroll-magnet.js`, partido en dos mitades como el cerrojo de scroll: **la
decisión es una función pura** (`destinoIman`, `rumbo`) y el instalador es el único que toca el DOM.
Así la decisión se ejercita con `node --test` —**19 casos**— en vez de con una sonda de navegador de
tres minutos. Mismo criterio que `nav-choreography.js`, y por el mismo motivo.

⚠️ **Cuesta 1,6 KiB a TODA página pública**, aunque solo la portada tenga heroes: el módulo se
instala siempre y sale por `null` en las once vistas restantes. El techo de peso sube de **23 a 26
KiB** a propósito, con margen del 6 % — no otro cable trampa.

### 20.5 El punto estático de la cabecera, ALINEADO

`[DECIDIDO owner]`: «el logo, el CTA y el icono del menú tienen que verse perfectamente en su sitio
encima del hero; actualmente el logo está demasiado abajo y está encima del hero».

⚠️ **Era cierto, y la causa es que el hueco se medía en `vh` y el racimo no cambia con la altura de
ventana.** Era `--hero-top-end: clamp(76px, 9vh, 104px)`: a 1080 px de alto daba 97 y el racimo acaba
en 86 —sobraba aire—; **a 900 daba 81 y el logotipo se metía 5 px dentro del hero**. Un hueco medido
en `vh` para tapar algo que mide siempre lo mismo acierta por tramos.

▶ Ahora se **deriva**: `--nav-pad-block + --nav-cluster-h + --hero-top-air`, donde el alto del racimo
es `max(--nav-logo-h, --nav-btn-h)`. Medido: **12 px de aire exactos en once ventanas** de 2560×1440
a 360×640. Devolver el `clamp` deja el racimo encima del hero en **4 de ellas**.

⚠️ **Y al derivarlo apareció un token FUERA DE ALCANCE**: la hamburguesa leía `var(--cta-pair-h,
54px)` y **no es descendiente de `.cta-pair`**, así que se quedaba en el valor de reserva y nunca
bajaba a 48 en teléfono —que es literalmente lo que prometía su propio comentario—. Es el mismo fallo
que `#233` documentó con `--cierre-runway`: *un token fuera de alcance no falla, rinde su reserva.*

### 20.6 ⚠️ Y una guarda propia nació CIEGA — la cuarta vez

La primera versión de la guarda del botón de menú aseveraba la cadena de `var()` **resuelta**, y al
mutar con el fallo real (devolver `var(--cta-pair-h, 54px)`) **pasaba en verde**: resolver la cadena
seguía llegando al token bueno, porque `--cta-pair-h` ahora lo lee. La guarda bendecía exactamente el
defecto que la motivaba.
▶ Lo que importaba no era de dónde venía el número, sino que el token estuviera **en alcance**. Ahora
asevera la lectura **directa** y que el token se declare en `:root`. **4 mutaciones, las 4 muerden.**

### 20.7 Verificación

- **Decisión**: 19 casos con `node --test`, incluidos los tres del rumbo escritos **desde el fallo
  real** que costó la sonda.
- **Navegador**: 8 casos × 2 anchos (1280×900 y 390×844), **16/16**, aseverando el **estado** al que
  se encaja (`--hero-p`, `--cierre-q`) y no el píxel — porque el documento se mueve (§20.3).
  Cubren los dos heroes en las dos direcciones, la retención bajo el umbral, el segundo empujón que
  sí completa, y **dos casos de que el imán NO toca nada** a media página y lejos del cierre.
- **Alineación**: 11 ventanas, 12 px de aire en todas.
- **Guardas nuevas**: `test_the_hero_gap_is_derived_from_the_cluster` y el botón de menú endurecido.
- Suite **3390 / 22.332** · JS **854** · Pint ✓ · docs-check ✓ · build ✓.
- ❗ **Un imán se juzga con la mano, no con una sonda**: hay que bajar por la portada y notar si
  retiene donde debe y si suelta cuando uno insiste.

---

## 21. LA DEMO DEL MINIJUEGO EN EL PUNTO ESTÁTICO (`#256`, 2026-08-29)

> `[DECIDIDO owner, 2026-08-29]`: «haz el hero del pie más largo» y «que la animación del juego esté
> activada en su punto estático». **Son un solo encargo**: lo segundo necesita sitio y lo primero es
> ese sitio.

### 21.1 La señal NO es el anclaje, aunque se llame igual que el punto estático

El mockup enciende su bucle del castillo **siempre que el lienzo se ve** (`vigilaVista` →
`casBucle`), y en cualquier fase que no sea `jugando`/`fin` lo corre en **modo demo**. `q > 0,985`
allí decide **solo si se puede jugar**. Nuestro motor tenía la demo y su interruptor de visibilidad
idénticos; lo que faltaba estaba arriba: **el trozo no se descargaba hasta `cierre:abierto`**.

⚠️⚠️ **El primer intento usó el ANCLAJE y falló donde el owner mira.** Publicar `cierre:anclado` como
hecho binario parecía la señal obvia —el punto estático *es* donde la tarjeta se ancla— y medido en
navegador **no lo es en todas partes**:

| | 1920 | 1440 | 1366 | 1280 | 768 | 390 |
|---|---|---|---|---|---|---|
| ¿anclada en el punto estático? | **no** | **no** | sí | sí | **no** | sí |

En las grandes la composición cabe con la sección todavía **20 px por debajo del tope**, así que
nunca se ancla ahí. ▶ *El nombre de un umbral no demuestra dónde cae.* La señal buena es la del
mockup —que el lienzo SE VEA— y se registra con un `IntersectionObserver` **de un solo disparo**.

⚠️ Aquí sí vale un `IntersectionObserver` y el motor explica por qué él no lo usa: su lienzo se pega
y CRECE, y un observador sobre algo que cambia de tamaño da entradas y salidas espurias. Eso importa
cuando la respuesta se consulta sesenta veces por segundo; **aquí la pregunta se hace una vez y se
cierra**.

⚠️ **Los dos umbrales no se tocan**: la VISTA enciende la animación, `cierre:abierto` la hace
jugable. Si la vista cambiara la fase, en el punto estático **el espacio dejaría de desplazar la
página** (`_onTecla` solo se aparta con `fase === 'off'`). Verificado: sigue desplazando.

### 21.2 La reserva de la tira vuelve a ser constante, y eso NO contradice a `#253`

`#253` la hizo interpolar (24 px en reposo → la tira entera abierta) con el criterio «lo que solo
existe abierto, se reserva abierto». Era correcto entonces: **el juego no existía en reposo**. Con la
demo corriendo desde el punto estático la premisa se invierte. El criterio no cambia; cambia el hecho.

▶ El hueco es `--salta-hueco`. El HUECO (122–156) y el DIBUJO (`--salta-h`, 150) son dos números del
mockup que **no coinciden** y tienen que poder moverse por separado.
⚠️ Con `prefers-reduced-motion` baja a **24 px**: ahí la coreografía ni se monta, el motor nunca se
carga y reservar 156 px sería una franja vacía permanente.

### 21.3 «Más largo» = exactamente lo que pide el juego

Medido antes de proponer nada: **la tarjeta y el pie ya llenaban la ventana exacta** (a 1920,
757 + 293 = 1050 de 1080, **0 px libres**). Con la medida delante, el owner eligió **«solo lo que
pida el juego»** entre cuatro salidas.

| tarjeta en reposo | 1920 | 1440 | 1366 | 1280 | 768 | 390 |
|---|---|---|---|---|---|---|
| antes | 757 | 578 | 448 | 483 | — | 449 |
| después | **757** | **578** | **555** | **548** | **661** | **521** |
| tapa del pie | 0 | 0 | 86 | 44 | 0 | 53 |

▶ A 1920 y 1440 no se mueve nada: manda el `min-height` de `#254`. Donde crece es donde era corta, y
ahí tapa la parte alta del pie — que es lo que hace el mockup.

### 21.4 El fallo que el cambio destapa: el ancho del lienzo estaba cacheado

⚠️⚠️ Montándose con la tarjeta **ya a pantalla completa**, el motor medía el ancho definitivo y la
caché nunca fallaba. Arrancando en el punto estático mide **1176** —la columna— y luego la tarjeta
crece hasta la ventana: el búfer se queda en 1176 y el navegador lo estira. **A 1920, 62 % de más.**
▶ `ResizeObserver` sobre el lienzo, **no** `clientWidth` por fotograma: esa lectura fuerza el cálculo
de estilo dentro del bucle, que es justo lo que la caché evitaba.
⚠️ El `const` va **con el resto del estado**, no junto a su `observe()`: `mide()` lo lee, y una
`const` por debajo de su lector es la trampa de `auth-en-cajon.md` §8.ter.

### 21.5 Qué lo vigila

`SaltaJuegoTest`: 3 casos nuevos —la demo arranca al VER el lienzo (y alguien LLAMA al registro), la
tira tiene sitio en reposo, la fase no se toca ahí— y el de `prefers-reduced-motion` endurecido con
la **puerta nueva de descarga**, porque *lo que un gate declara que no mira es un hueco con nombre*.
**8 mutaciones, las 8 muerden.**
⚠️ **Dos no mordían y era el ARNÉS**: el escape de `\$` dentro de comillas dobles de bash llevó una
mutación a **otra línea** (la de `carga()`, no la de `_observa`) y otra no casaba por el cierre de
llaves. *Cuando una mutación no muerde, la primera hipótesis es la mutación.*

---

## 22. EL SET DE ICONOS — el tercer mecanismo, ya con anatomía (`#257`, 2026-08-29)

> `[DECIDIDO owner, 2026-08-29]`, a dos preguntas con la medida delante:
> **(1)** el set se parte **por el corte que hace el propio artboard** —los genéricos al PRODUCTO,
> los de parque al paquete del cliente—; **(2)** en esta tanda se dibujan **los de UI**, y las 14
> zonas después.

### 22.1 Por qué «todo al paquete del cliente» no era una opción

Hoy **no existe** forma de que un cliente sustituya el DIBUJO de un icono. Medido: `client.css`
alcanza al color, a la forma y a los dibujos que viajan por CSS (`--deco-tag`, `--deco-blob-*`, el
spinner, que son pseudo-elementos y máscaras), pero los `<x-icons.*>` son **componentes Blade con el
SVG en línea** y una hoja de estilos no puede tocar su geometría.

▶ Eso convierte «sustituir iconos por instalación» en un **séptimo mecanismo por construir**, y es lo
que deja fuera de esta tanda a los 19 dibujos de parque (5 «Del parque» + 14 zonas). No se pierden:
salen del artboard en minutos cuando el mecanismo exista.

### 22.2 La anatomía, que ahora es ejecutable

| | regla del artboard |
|---|---|
| §01 | lienzo **24**, área viva 20, margen 2, masa mínima **3** |
| §05 | **24 es la talla de trabajo** (20 solo en chips mono · 32 en cabecera · 48 con pegatina) |
| §06 | ✕ trazo por debajo de 3 · ✕ duotono dentro del glifo · ✕ pegatina por debajo de 40 |

Lo vigila **`IconSetAnatomyTest`**: `currentColor` **sin excepciones**, rejilla 24 salvo los
marcadores de catálogo, y nada de línea fina. **5 mutaciones, las 5 muerden.**

⚠️⚠️ **El matiz sale de que el artboard SE SALTA SU PROPIA REGLA.** `ui/check` declara
`stroke-width="1.4"` y no es una línea fina: es una **masa** con un trazo hilo que le redondea las
juntas. La prohibición es para el icono que se dibuja *con* el trazo, así que la condición se mira
**solo donde hay `fill="none"`**. Sin ese matiz la guarda habría nacido roja con el set del cliente
puesto tal cual. Lleva **control explícito**: si `check` deja de ser masa con hilo, el caso lo dice.

### 22.3 La geometría se COPIA, y los dos que faltan se derivan por espejo

Extractor del artboard → generador → componentes. **Ni una coordenada tecleada**: transcribir un
asset desde el contexto ya corrompió un fichero en silencio en este repo (`armazon-y-menu.md` §9.7).

`arrow-left` y `login` **el artboard no los dibuja** —dibuja su par— y se derivan con
`transform="translate(24 0) scale(-1 1)"`, no recalculando trayectos con arcos a mano. El módulo de
la escala es 1, así que el grosor no cambia.

### 22.4 Un nombre de icono es una HIPÓTESIS sobre su dibujo

Los 43 se renderizaron en una hoja de contacto **antes de cablear nada**, y ahí salieron dos mapeos
mal que el nombre invitaba a hacer:

- **`ui/taquilla` es un CANDADO** —en un parque, «taquilla» es donde dejas las cosas—, así que va a
  `lock`; y `cta/seguridad`, un escudo con check, va a `shield`.
- **`pag/pedido` es una BOLSA**, no un recibo. Lo que dibuja nuestro `receipt` es **`pag/factura`**.

⚠️ De regalo: el menú enseñaba **`devices`** —una pantalla y un portátil— **junto al número de
teléfono**. Ahora es `phone`.

### 22.5 El radio, dicho

- **55 componentes** (eran 26) · 14 sustituciones · **14 copias del cajón** actualizadas en 6
  ficheros, encontradas una a una por `SidebarIconParityTest`.
- ⚠️ **El cajón cambia de aspecto con la landing y eso es el MECANISMO, no un daño colateral**
  (`landing-white-label.md` §4.5.3): el set es compartido para que las dos superficies no diverjan.
  Sus iconos pasan de trazo 1.7 a masa y **pesan más a la vista** — necesita el OJO del owner.
- **Tres se quedan en el idioma anterior** (`devices`, `cookie`, `chevron-down`) porque el artboard
  no los dibuja; redibujarlos a ojo es lo que en `#211` salió con un 29 % de píxeles distintos. Lista
  de excepción declarada, y **solo encoge**.

### 22.6 Dos fallos de instrumento, y son el mismo

⚠️⚠️ **`width` sin frontera de palabra casa dentro de `stroke-width`**, y pasó **dos veces en una
hora**: el extractor dejó `stroke-` a medias, y **`SidebarDrawerPolishTest` se puso ROJO con el
producto sano** al llegar el primer icono del set que pinta con trazo. Los dos, corregidos con
`(?<![-\w])`. Es la misma familia que las cuatro guardas ciegas de esta semana.

---

## 23. LA AUDITORÍA DEL SET, EJECUTADA (`#258`, 2026-08-29)

> El owner pidió revisar «todos los iconos, los que tenemos y los del mockup», y después «procede con
> todos». Seis bloques, los seis hechos. El set pasa de **55 a 61**.

### 23.1 Lo que la medición encontró

**34 `<svg>` dibujados fuera del set**: 20 en la web pública, 13 en el panel, 1 widget. De los 20:

| | |
|---|---|
| **14** ya tenían equivalente en el set | FAQ · minijuego · los 5 de la invitación · los 4 de ofertas · los 3 de invitados |
| **5** eran sujetos que el artboard NO dibuja | chevron (×2) · ojo · ojo tachado · disquete |
| **1** es decorativo | el sello de 100×100 de la tarjeta de cumpleaños |

Y **4 dibujos «propios» del cajón** declarados en `DRAWER_OWN`, los cuatro por la misma razón: no
había componente. **Hoy la lista está VACÍA**, y que lo esté es lo que hace fuerte a la guarda: con
ella llena, un huérfano se «arregla» añadiéndolo ahí.

### 23.2 ⚠️⚠️ La guarda de paridad llevaba CIEGA desde que se escribió un comentario

`SidebarIconParityTest` limpiaba los comentarios de **HTML** antes de buscar —con su motivo escrito—
pero no los de **JavaScript**. El docblock de `PasswordInput.vue` cita «dos `<svg>` dentro»: el
escáner arrancaba en la CITA, el `(.*?)` llegaba hasta el primer `</svg>` real y **se tragaba el icono
de en medio**. Sin fallar, porque lo tragado lleva `<template>` dentro y la geometría salía vacía.

▶ **Tres dibujos ciegos**, no uno: los dos ojos y **dos flechas de `CartStep` y `PayStep`** que
seguían en el idioma anterior en el carrito y en la pantalla de pagar.

⚠️⚠️ **Y la primera guarda contra eso NO mordía**: anclar en «este fichero da DOS dibujos» falla
porque con el escáner descarrilado **también da dos**. Lo que distingue el caso: **una cita es un
`<svg>` pelado, y todo icono declara su `viewBox`**.

### 23.3 Los cuatro ESTADOS como pegatina

`.state-badge` — círculo, keyline de tinta de 2, sombra dura, mínimo 40. Relleno por tokens
SEMÁNTICOS (`--ok`/`--err`/`--attn`), así que sale con la paleta del cliente **sin una línea suya**;
sombra por ROL (`--shadow-float`), difusa en el producto y `5px 5px 0` en su paquete.

| estado | antes | ahora |
|---|---|---|
| Éxito | el confeti a 56 px | pegatina verde con `check` |
| Error | **nada** | pegatina roja con `close` |
| Sin plazas / pausa | **nada** | pegatina amarilla con `warning` |
| Cargando | el spinner del producto | **se queda** (ya es sustituible, y el del artboard nace para girar) |

⚠️ Tinta `--paper-fg`, no `--fg`: la pegatina puede caer sobre el hero. Contraste medido con el
paquete del cliente: **6,28 · 4,10 · 11,26 · 6,85** — los cuatro sobre el 3:1 de WCAG para un objeto
gráfico.

### 23.4 Dibujar lo que el artboard no tiene NO contradice a `#211`

Se dibujan tres (`chevron-down`, `eye`, `eye-off`). En `#211` lo que no salió fue un **logotipo**:
seis capas por palabra, identidad de marca. Esto son **glifos mecánicos** cuya forma la determina casi
entera la anatomía. ⚠️ Y el disquete de «guardar» **se retira sin sustituto**: §06 dice «máximo un
icono por fila de texto», y ese botón ya dice Guardar.

### 23.5 Los de producto, y la trampa del extractor

`ProductIcon::CHOICES` pasa de 6 a 11. ⚠️⚠️ **Se AÑADEN por una razón de DATOS**: `forProduct()` trata
una clave desconocida como ausente, así que retirar una ilustración **degradaría en silencio** todo
producto que la tuviera guardada.

Tres salen del LOTE 2, que el cliente **nunca cerró**; la variante la elige su propio texto.
⚠️⚠️ **El extractor emparejó etiqueta y descripción por POSICIÓN**, y en las filas con columna
«ACTUAL» eso desplaza todo un puesto: `booking` salió con el dibujo de ACTUAL —lo que hay hoy— y
quedó **idéntico a `calendar`**. *Se vio porque el resultado era sospechosamente igual a otro icono,
no porque fallara nada.*

---

## 24. EL CARGADOR Y EL MARCADOR DE PRODUCTO (`#259`, 2026-08-29)

### 24.1 «Tres botes» — el cargador deja de ser la marca de un cliente

`[DECIDIDO owner]`. El dibujo de `spinner.css` §B era, con sus palabras, «un punto que salta sobre un
bloque de espuma, **el mismo vocabulario que su logo**»: un cargador con marca ajena dentro servido a
toda instalación. Tres puntos no dicen de quién es la web.

Números del artboard: **punto 11 · hueco 9 · salto 7 · ciclo 900 ms · desfase 120 ms**, aquí en
proporción al tamaño — `3·0,216 + 2·0,176 = 1`, o sea que los tres puntos y sus dos huecos llenan la
caja exacta y los cinco tallajes de §A siguen funcionando.

⚠️ **TRES piezas, no dos**: los pseudo-elementos son los extremos y **el del medio lo pinta el fondo
del elemento**. Hacen falta tres cajas porque hacen falta tres FASES, y un `transform` sobre el
elemento arrastraría a sus pseudo-elementos.
⚠️ El fotograma quieto sale de su propia regla: «los puntos **se apagan al 40 %**».
⚠️⚠️ **`closest-side` no es un detalle**: en un `radial-gradient` las paradas se miden sobre el RAYO,
y el rayo por defecto llega a la ESQUINA (`0,707 × lado`). Un `50%` ahí dibuja **29 % menos**. Se vio
en una captura, no en una medida — los tres puntos tenían el mismo `background-size`.

### 24.2 El marcador de producto entra en el CONTRATO

⚠️⚠️ **El catálogo del cajón elegía su dibujo con `v-if="item.is_pack"`** —el patrón que `#140`
retiró de la cesta y del resumen— y la guarda que lo prohíbe **miraba una lista de dos ficheros
escrita a mano** en la que `CatalogStep.vue` no estaba. La pantalla más visible del cajón, sin mirar.

▶ La clave viaja ahora en `CatalogProduct.icon`, resuelta por el dominio, publicada en
`GET /api/v1/catalog/products` y declarada en `openapi/v1.yaml`. El descubrimiento de la guarda pasa
a ser automático, con dos anclas.
⚠️ Defectos nuevos (`[DECIDIDO owner]`): entrada → `ticket`, pack → `gift`. Mueve el icono de todos
los productos sin elección propia; las seis ilustraciones **siguen ofrecidas**.
⚠️⚠️ **`.icon` no es un contenedor neutro**: declara `fill: none; stroke: currentColor;
stroke-width: 1.6` sobre cada primitiva, o sea **dibuja a línea**. Habría convertido en un hilo
cualquiera de los glifos de masa. Lo que daba de layout vive ahora en `.prod-ico`.

### 24.3 Lo que queda medido y sin tocar

**115 controles por debajo de 44 px en móvil** (63 enlaces, 25 botones, 1 casilla; solo cinco llevan
icono). `[DECIDIDO owner]`: **tanda propia** — toca el pie, la FAQ, las cookies y el cierre.

---

## 25. EL INTERRUPTOR DEL TITULAR ES YA EL `6d` DEL CANVAS (`#262`, 2026-08-29)

`[DECIDIDO owner]`: «tenemos ya el icono, vamos a usar el **6d** … **tráelo idéntico**».

### 25.1 · Qué trajo el LOTE 6 (y qué NO está en la copia local)

⚠️⚠️ **`mockup_playjumppark/Iconos PJP.dc.html` es la copia del 27-08 y NO tiene el LOTE 6.**
`DesignSync` sigue sin autorización —`/design-consent` devolvió **403**, que su propio mensaje
atribuye a la sesión de claude.ai— y el fichero llegó **pegado en el chat**. No se sobrescribió la
copia local porque el pegado trae **la codificación rota en los acentos**, y meter mojibake en el
activo lo corrompe. ▶ Para refrescarla de verdad: `/login` y después `/design-login`, o exportar el
fichero al directorio.

La sección `00 / TOGGLE ON · LOTE 6` trae cuatro piezas:

| id | qué es | veredicto del artboard |
|---|---|---|
| `6a` | pista maciza con el bulbo TROQUELADO | **el elegido** como `ui/toggle-on` |
| `6b` | pista hueca, bulbo macizo | *«sobra — invierte la lógica de relleno del set entero»* |
| `6c` | la pareja on/off completa | el off obligatorio si el on se dibuja con masa |
| `6d` | **la versión ANIMADA**, «El salto, no el deslizamiento» | lo que pide el owner |

⚠️ `6d` **no es un glifo**: es una caja con borde y transición, no un `<svg>`. El propio artboard lo
avisa en `6a` — *«como icono, no como control real: el interruptor de la interfaz se construye con
caja y transición, no con un glifo»*. Por eso no entra en `resources/views/components/icons/` ni lo
ve `SidebarIconParityTest`: vive en `site.css` como `.hero__switch-sw`.
▶ **`6a`, `6b` y `6c` siguen sin traerse.** Solo harían falta si alguna pantalla necesita el
interruptor como icono estático dentro de una fila de texto, y hoy ninguna lo hace.

### 25.2 · «Idéntico» se construyó, no se copió

> ⚠️ **Son DIEZ, no ocho**: la 2.ª vuelta del canvas (§25.8) añadió el rótulo de dentro con **5,6**
> de sangrado y **8,5** de cuerpo. Los dos vuelven a ser múltiplos exactos de 1/16.

Las diez medidas del artboard —**44 · 26,4 · 3,6 · 2 · 15,2 · 16,4 · 19 · −2,4 · 5,6 · 8,5**— son
**todas múltiplos exactos de 1/16**. Escritas en `em` sobre una sola unidad `--sw-u` salen idénticas *y*
escalan con el titular: mover `--sw-u` mueve la pieza entera sin tocar una proporción.

Medido en navegador (pista de 62,33 px → factor 44/62,33 = 0,7059): el bulbo va a **19,00**, se
asienta en **16,40** y sale a **−2,40**. Los tres números del artboard al dígito.
⚠️ El único desvío es el **borde**: `calc()` pide 5,0996 px y el navegador **encaja los bordes en
píxeles enteros**, así que pinta 5 (0,080221 de ratio contra 0,081818). Son 0,1 px y no es la
fórmula: es cómo se pintan los bordes.

### 25.3 · ⚠️⚠️ La curva NO es la suya, y por qué es la decisión correcta

| | curva | rebasamiento de pico |
|---|---|---|
| artboard `6d` | `cubic-bezier(.34, 1.4, .5, 1)` | 1,00 px |
| nuestro `--ease-entra` | `cubic-bezier(.34, 1.56, .64, 1)` | 1,86 px |

Invertidas por Newton y comparadas punto a punto: **0,86 px de separación máxima a la talla del
artboard, 0,50 px a la del hero**.

▶ **Estrenar una quinta curva por medio píxel deshace la tanda 2d** —§16: de 20 curvas a 4, con el
90 % del movimiento de la web sin decidir por nadie—. Y `--ease-entra` está DEFINIDA como *«lo que
APARECE: se pasa de largo y vuelve»*, que es literalmente la frase con la que el artboard describe
`6d`. ⚠️ Lo decisivo: **el rebote de `6d` no vive en la curva, vive en los FOTOGRAMAS** (19 → 16,4),
y ésos están copiados exactos. La curva solo interpola entre ellos.

### 25.4 · La duración sí entra, como AMBIENTAL

3,4 s no cabe en la escala (techo 620 ms) porque no responde a un gesto. Entra como **`--dur-switch`**
en el bloque de ambientales, junto a `--dur-invite`, y en `TOKENS_AMBIENTALES` de `MotionScaleTest`.
Es el mecanismo el que importa: una instalación lo calma cambiando un token, no un `@keyframes`.

### 25.5 · ⚠️ Colores: roles, no los literales del artboard

Su tarjeta pinta la pista de **Lima Bote `#A3C21C`** porque es una pieza suelta sin texto al lado —su
propia §02 exige *«currentColor, siempre»*—.

- **encendido → `--ok`**. Lo fijó `#254` con `ActionFillTest` delante: *acción* es el control que
  hace AVANZAR, *encendido* es un ESTADO. Lima Bote **sí tiene token** (`--strip-2`) pero su rol es
  la tira del pie; usarlo aquí sería un rol prestado. `[PENDIENTE: owner]`: si prefiere el lima, es
  una línea.
- **apagado → `--fg-mute`**, no su `#5E666D`. Ese gris vive sobre una tarjeta `#1A1F25`; sobre el
  vídeo del hero desaparecería. En tinta resuelve a `#9AA1A8`, que es el gris del propio cliente.
- **bulbo encendido → `--paper-fg`**, la TINTA — **no `--fg`, que dentro del hero vale CLARO** (§13).
  Resuelve a `#101418`: el literal exacto del artboard, por el camino correcto.

### 25.6 · Sin movimiento se queda ENCENDIDO

Con `prefers-reduced-motion` los dos bucles se retiran y la pieza se congela en ON. Verificado:
pista `#5FA82E`, bulbo `#101418`, `translateX` 23,23 y **cero animaciones vivas**.
⚠️ Congelarlo en OFF, al lado de un rótulo que dice «ON», sería un defecto **que no ve nadie** —casi
nadie navega con esa preferencia—, y es justo el tipo de fallo mudo que este carril lleva cazando.

### 25.7 · Sin guarda nueva, a propósito

El set de escala ya lo cubre por tres lados: `MotionScaleTest` (el token y la curva),
`ShapeScaleTest` (`--r-pill`, sin literales de canto) y `RawColourIsNotATokenTest` (cero literales
de color). Lo que quedaría por vigilar —las proporciones del `calc()`— se rompería **a la vista, en
la portada**, y `#251`/§19 ya dejó escrito que aseverar el texto literal de una declaración ata la
guarda a una implementación. ▶ La verificación es la **medición en navegador** de §25.2 y §25.6, que
es más fuerte que una aserción de texto y está registrada con sus números.

### 25.8 · La 2.ª vuelta: el «ON» dentro de la pista (`[DECIDIDO owner]`, mismo día)

El canvas volvió a publicar el `6d` con la pista en `position: relative` y un `<span>` dentro con el
rótulo y **bucle propio**. Nota del autor: *«entra con el relleno y solo vive en el tamaño grande: a
20 px es una mancha y se cae»*. El owner pidió aplicarlo y **retirar el rótulo que iba fuera**.

▶ **Los tiempos del rótulo no son los del bulbo**: entra al **40 %**, después de que la pista se
llene al 38 %, y se va al **88 %**, antes de que se vacíe. Nunca se le ve sobre la pista apagada.

#### 25.8.1 · ⚠️⚠️ Una custom property en `em` no es una longitud

El sangrado escrito como `calc(var(--sw-u) * 0.35)` —la misma forma que usan la pista y el bulbo—
**salió a 1,84 en vez de 5,6**. `--sw-u` vale `0.62em` y una custom property **se sustituye como
texto**: el `em` lo resuelve el elemento que la usa. Este rótulo se cambia su propio `font-size`, así
que ahí `--sw-u` vale un tercio. (El `font-size` sí salió bien: en esa declaración el `em` se
resuelve contra el PADRE.)

▶ Regla: **si un descendiente cambia su `font-size`, no puede medirse con un token en `em`.** El
sangrado va en `em` del propio rótulo: 5,6/8,5 = **0,658824**.
⚠️ **Ninguna guarda de tokens ni ninguna captura habrían visto esto**: el rótulo seguía dentro de la
pista, con su color y su tipo, 3,8 px corrido. Lo cazó medir `left` y **escalarlo a los 44 del
artboard** — el mismo instrumento de §25.2.

#### 25.8.2 · La familia se HEREDA, y ahí el cliente se contradice

El artboard escribe `'Bungee'`; aquí no se escribe familia ninguna y se hereda del titular, que lee
`--font-display`. Escribirla clavaría la tipografía de **una** instalación en el producto.
⚠️ Su norma dice «Bungee solo en mayúsculas y **nunca por debajo de 20 px**» y su artboard lo pone a
**8,5**. Se sigue al artboard, que es el dibujo que él firma, y queda anotado: **ninguna guarda
vigila esa regla hoy** (`DEUDA`).

#### 25.8.3 · El nombre accesible se sirve aparte

El dibujo entero va `aria-hidden` —rótulo incluido— y la palabra se repite en `.sr-only`: dentro del
interruptor **el rótulo parpadea cada 3,4 s**, y un nombre accesible que parpadea no lo es.
Verificado recorriendo el `<h1>` y saltando los subárboles ocultos: **«DIVERSIÓN ON»** en los dos
modos de movimiento.

### 25.9 · La 3.ª vuelta: a la altura de las MAYÚSCULAS (`[DECIDIDO owner]`)

«Grande al tamaño del texto, misma altura».

▶ **«La altura del texto» no es el `font-size`: es la tinta.** El cuerpo incluye el hueco de
ascendentes y descendentes que unas mayúsculas no usan; alinear contra él deja el interruptor más
alto que las letras. Medido con `TextMetrics` sobre la «D» —sin la tilde de la Ó, que falsea el
ascenso—: **82 px sobre 107,5, o sea 0,7626**. De ahí, `--sw-u = 0,7626/1,65 = 0,4622em`, y el
envoltorio pasa a `1em` para escribirlo todo contra el titular sin escala intermedia.

⚠️⚠️ **Con CONTROL, porque medir un respaldo da un número plausible y equivocado**: la misma «D» en
la genérica mide 64,7 de ancho contra 80 y 78 de ascenso contra 80 → la fuente cargada es la buena.
⚠️ Y el ancho del titular en el DOM (643,6) **no coincide** con el del lienzo (668) sin que eso
delate otra fuente: es el `letter-spacing` negativo, que `TextMetrics` no aplica. *Un control que no
cuadra hay que explicarlo antes de tirar la medida* — es el mismo error que §10.6 documentó al
revés.

▶ **Alineación** — ⚠️⚠️ **esta frase era FALSA y va corregida en §25.9.1; no la sigas.** Decía que
un `inline-flex` sin texto dentro sintetiza su línea base en el borde inferior. No lo hace.

▶ **Medido en seis anchos** (1920 · 1440 · 1280 · 1024 · 768 · 390): la pista y las mayúsculas
coinciden dentro de **±1,2 px**, y el rótulo sigue en **8,50 a la escala del artboard en los seis** —
que es la prueba de que agrandar la pieza es cambiar **una** línea. Un renglón en todos salvo 390.
Cero desbordes.

▶ **Efecto secundario que CORRIGE a §25.8.2**: a esta talla el rótulo va a **26,4 px a 1280** y
**30,4 a 1920**, así que la norma «Bungee nunca por debajo de 20 px» **ya se cumple por encima de
~1000 px**. Solo se incumple por debajo (21,1 · 15,8 · 12,0), que es justo donde el artboard dice
que el rótulo «se cae». La ficha de `DEUDA` queda acotada a decidir ese suelo.

#### 25.9.1 · ⚠️⚠️ La LÍNEA BASE: dos suposiciones falsas y un instrumento que faltaba

Lo cazó el ojo del owner —«está más abajo»— y **mi verificación decía que estaba bien**: había medido
que las ALTURAS coinciden (±1,2 px en seis anchos) y **nunca comparé las POSICIONES**. *Medir la
dimensión correcta de la cosa equivocada da un verde perfecto.*

▶ El instrumento que faltaba es una **sonda de línea base**: un `inline-block` vacío de alto 0 como
primer hijo del titular, cuyo borde inferior se apoya exactamente en la línea base. Restándole el
ascenso de `TextMetrics` sale la caja de mayúsculas, y ya se puede comparar con el rect de la pista.

| suposición | realidad | desfase |
|---|---|---|
| «un `inline-flex` sin texto sintetiza su baseline en el borde inferior» | **un contenedor flex toma su línea base de su PRIMER ÍTEM** — aquí el bulbo, centrado | +17,4 px a 1280 (0,161em, sistemático) |
| «con `inline-block` ya sí» | el contenedor flex de dentro **sigue propagando** su línea base | +19 px |
| — | **`inline-block` + `overflow` ≠ `visible` → línea base en el borde inferior del margen** (regla explícita de CSS) | **+2,6 / −2,0** |

⚠️ Ese `overflow: hidden` **no recorta nada** —la caja del envoltorio es la de la pista y el bulbo
estirado se queda dentro de su borde (40,9 de 44)—, pero **si alguien lo quita por «limpieza» la
pieza se cae 19 px y no falla nada**.

#### 25.9.2 · La unidad `cap`: la talla deja de ser la de Bungee

`0.4622em` era **la altura de mayúscula de Bungee metida a mano en el producto**: otra instalación
con otro `--font-display` habría tenido el interruptor descuadrado con su propio titular sin que
fallara nada. La unidad **`cap` es la altura de mayúscula de la fuente que haya**, así que
`--sw-u: calc(1cap / 1.65)` hace que la pieza siga sola a la tipografía de cada cliente.

⚠️ **Va en `@supports`, no como segunda declaración**: un valor inválido dentro de una custom
property **no cae al anterior** —se propaga como inválido y colapsa la pista—, así que la cascada de
respaldo **no funciona con `var()`**. Es la hermana de la trampa de §25.8.1: *una custom property no
se comporta como una propiedad.*

⚠️ **Residuo medido: 3,6–4,7 px.** `1cap` da la altura que la fuente DECLARA, y la de Bungee es un
5,6 % menor que la que pinta (77,4 contra 82 a 1280). No hay unidad CSS para la tinta; cerrarlo
exigiría volver al número de Bungee. Se deja y se anota.

### 25.10 · La 4.ª vuelta: pegado al texto y a la derecha TAMBIÉN en el móvil

`[DECIDIDO owner]`: «pega el toggle más al texto, quita el `margin-inline`. Y en el móvil también lo
quiero a la derecha, no debajo; si hace falta el texto que se haga más pequeño».

▶ **Por qué se caía**: por debajo de ~600 px el renglón no da para la palabra más el interruptor, y
la ÚNICA oportunidad de corte que hay es el espacio entre los dos. Basta con que quepan.

⚠️ **La primera cuenta dijo que a 480 sobraba sitio y aun así envolvía.** Faltaba **el espacio**: un
`Range` sobre el nodo de texto **no lo cuenta cuando cae en fin de línea**, porque ahí cuelga. *Un
ancho medido sobre texto YA ENVUELTO no es el ancho que ese texto necesitaría en una línea.*

▶ El renglón necesita **7,3 × el cuerpo** (5,99 palabra · 0,25 espacio · 1,20 interruptor) y el
ancho disponible por debajo de 600 es **la ventana menos 52 px**, igual en 320/360/390/414/480. De
ahí `--hero-t-fit: calc((100vw - 52px) / 7.3)`, metido en un `min()` con el tamaño que ya tenía.

▶ **Sin media query, porque el tope es inerte donde sobra sitio**: a 768 da 98 contra 64,5 que pide
el titular; a 1280, 168 contra 107,5. Un punto de ruptura menos es un punto de ruptura que no
envejece. Medido: **un renglón en ocho anchos (320 → 1280) y cero desbordes**, con el cuerpo intacto
de 600 para arriba.

⚠️ **La coreografía de `#220` sigue viva con menos recorrido en teléfono**: a 390 el titular encogía
de 54 a 44 al bajar y ahora va de 46,3 a 44. En el punto estático el cuerpo es el de siempre.

⚠️⚠️ **El 7,3 depende de la PALABRA**, y `hero.l1` lo pone la instalación —hoy «DIVERSIÓN» (es),
«FUN» (en), «DU FUN» (fr); el castellano es el peor—. Con un rótulo más largo el interruptor volvería
a caerse. Medir texto desde CSS no se puede. **Ficha en `DEUDA`.**

---

## 26. EL OBJETIVO TÁCTIL MÍNIMO — 44 AL DEDO, SIN MOVER EL DIBUJO (`#264`, 2026-08-29)

`#259` §6 dejó esto medido y sin tocar: **«115 controles por debajo de 44 px en móvil»**, con el
`[DECIDIDO owner]` de que era **tanda propia**. Ésta es esa tanda.

### 26.1 · La cifra de partida era de INSTANCIAS, y el trabajo se hace sobre CONTROLES

Aquellos 115 contaban apariciones: los 14 destinos del pie salen en las siete vistas, así que un
solo enlace mal medido se cuenta siete veces. Contando **controles distintos** —misma clase, mismo
rótulo, misma medida— son **37**, medidos con la sonda ya sana (§26.6) sobre las siete vistas
públicas renderizables a 390 px, y ésa es la lista sobre la que se trabaja:

⚠️ **Tres cifras para el mismo defecto, y las tres se han escrito en este repo: 115 · 51 · 37.** La
primera contaba instancias; la segunda salió de una sonda que aún no filtraba visibilidad ni
aplicaba el `transform` del pseudo; la tercera es la buena. **La que vale es siempre la del
instrumento que ha demostrado estar sano**, y por eso §26.6 va antes que cualquier número.

| Zona | Controles | Medida |
|---|---|---|
| Pie · destinos | 14 enlaces | 37–139 **× 40** |
| Pie · bloque legal | 5 enlaces + el botón de cookies | 47,9–86,5 **× 14** |
| FAQ | 6 acordeones | 358 **× 28** |
| Cookies | «Configurar» · «Más información» · «Leer la política» · 2 interruptores | 17 · 18 · 16 · 42×**24** |
| Menú | 3 cápsulas + el disparador de idioma + 3 opciones del desplegable | ×38 y ×37 |
| Cumpleaños | 2 pestañas · 2 muestras · el enlace de la invitación | ×41 · ×34 · 325×**17** |
| Servicios | 3 saltos · 2 pestañas de zona | ×36 · ×37 |
| Páginas de contenido | «Volver al inicio» | 108,7 **× 21** |
| Landing | «Cómo llegar» · «Reservar» · el salto al contenido | ×39 · ×42 · ×38 |

⚠️ **Ninguna cifra cambia el diagnóstico anterior; lo que cambia es la unidad.** Se deja escrito
porque la siguiente sesión que lea «115» y encuentre 51 va a pensar que algo se perdió.

### 26.2 · Las dos decisiones del owner

▶ **1. Donde el dibujo está ajustado 1:1 con el mockup, el objetivo crece AL DEDO Y NO A LA VISTA.**
`[DECIDIDO owner, 2026-08-29]`. Un pseudo-elemento absoluto centrado lleva el área a 44 sin mover
un píxel; donde crecer no daña —las cápsulas del menú, «Reservar», el desplegable de idioma— se
crece de verdad con `min-height`, que se lee mucho mejor en el CSS. La alternativa —crecerlo todo—
subía la FAQ 96 px y el pie 54, o sea deshacía a mano parte de `#250`→`#263`.

▶ **2. El bloque legal del pie pasa a TIRA QUE SE DESLIZA**, el patrón que `#252` ya dio a los
destinos. `[DECIDIDO owner, 2026-08-29]`. No había una tercera salida: a 390 px esos seis eslabones
envuelven en **tres renglones de 14 px separados 18**, y llevarlos a 44 apilados hacía **crecer el
pie 54 px**. En una tira miden 44 y el pie **encoge 30** —el bloque legal baja 34 y los destinos
suben 4—, altura que recupera el punto estático del cierre, que hoy no cabe en un iPhone SE (ficha
abierta en `DEUDA`). Medido: pie **365 → 335** a 390 px, una fila, deslizante, con desborde 0.

⚠️⚠️ **Y EL ALTO TÁCTIL DE LAS DOS TIRAS TUVO QUE ACOTARSE A `(pointer: coarse)`, que es lo que
enseñó medir en las dos ventanas.** Puesto sin puerta hacía justo lo que se buscaba en teléfono y
**crecía el pie 20 px en escritorio**, donde el mockup lo fija a 1176 — el bloque legal pasaba de 14
a 44 sin que nadie tocara una pantalla. *Un objetivo táctil que engorda la pantalla donde no hay
dedos no es un objetivo táctil: es un cambio de diseño con otro nombre.* Con la puerta, escritorio
vuelve a ser **idéntico**: pie 287, destinos 40, fila inferior 53, legal 14.

### 26.3 · El mecanismo

```css
@media (pointer: coarse) {
    [data-tap] { position: relative; }
    [data-tap]::before {
        content: ""; position: absolute; top: 50%; left: 50%;
        width: max(100%, var(--tap-min)); height: max(100%, var(--tap-min));
        transform: translate(-50%, -50%);
    }
}
```

⚠️⚠️ **`max(100%, …)` no es una floritura: sin él la regla ENCOGE.** Un área de 44 centrada sobre un
control que ya mide 60 le quita 8 px por lado, y eso no lo ve nadie —el control sigue funcionando,
solo deja de responder por el borde—. Un mínimo solo puede ampliar.

⚠️⚠️ **Va bajo `(pointer: coarse)` a propósito.** Con ratón, un área 30 px más alta que su enlace
dispararía el `:hover` desde lejos y el cursor cambiaría a mano sobre el vacío. Es `pointer` y no
`any-pointer` por lo mismo: en un portátil táctil manda el ratón.

⚠️ **Es `::before` y no `::after` porque `.ck-tgl::after` dibuja el pomo** del interruptor de
cookies. Medido antes de elegir: de las 19 familias, `::after` estaba ocupado en una y `::before` en
ninguna. Si alguien lo mueve a `::after` «porque da igual», ese pomo desaparece y **el interruptor
sigue funcionando**, así que no lo vería ningún test de conducta. Hay guarda.

⚠️ **El marcador va en el MARCADO (`data-tap`), no en una lista de selectores dentro del CSS.** Una
lista habría que escribirla dos veces —una para `position: relative` y otra para el pseudo— y dos
copias que hay que mantener a la vez es de donde salen la mitad de las fichas de este documento.
En el marcado, además, lo puede vigilar una guarda de árbol.

### 26.4 · ❗❗ El mecanismo YA EXISTÍA, y tenía un defecto

Esta tanda declaró `--tap-min: 44px`… y **la guarda que acababa de escribirse la puso roja: el token
ya estaba** en `site.css`, del **«Lote 9»**, sirviendo a cuatro controles del cajón
(`.entry__stepper button`, `.cal__nav`, `.cart__remove`, `.bk-foot__info-btn`) con la misma idea de
pseudo-elemento centrado.

▶ **Y escribía `width: var(--tap-min)` a secas, o sea que encogía** el área de cualquiera de esos
cuatro que ya midiera más de 44. Corregido al `max()` en su sitio, que solo puede ampliar.
▶ **La puerta de puntero NO se le ha puesto**, y es deliberado: retirar área con ratón en el cajón
es una regresión que nadie ha pedido — son controles de icono pequeños y el ratón agradece el
margen. Se queda como estaba, documentado, y los dos bloques se citan el uno al otro.

⚠️ *Un mecanismo nuevo que resulta ser el segundo lo caza una guarda de unicidad, no la memoria.*

### 26.5 · Las tres excepciones, y por qué son de norma

1. **El enlace EN LÍNEA del texto de cookies** («Más información») se queda a 18 px. Un objetivo de
   44 de alto sobre una línea de 18 se come el renglón de arriba y el de abajo: texto que se lee y
   se selecciona, no se pulsa. **WCAG exime justamente a los enlaces en línea** dentro de un bloque
   de texto (2.5.5 y 2.5.8, «inline»). Tiene caso propio, para que la excepción no parezca descuido.
2. **Las opciones del desplegable de idioma** no llevan el área invisible y crecen de verdad: van
   **pegadas (hueco 0)**, así que un objetivo centrado sobre una caja de 37 se metería 3,5 px dentro
   de la vecina. *Un área táctil que se solapa con la de al lado no amplía nada: mueve el destino
   del dedo.* Es la única familia donde eso está medido y no supuesto.
3. **Las dos tiras del pie** tampoco pueden llevarlo: son carriles con `overflow-x: auto`, y **un
   eje no visible obliga al otro a `auto`** — el área se recortaría sin que nada fallara. En una
   tira el objetivo solo puede ser alto de verdad.

▶ Dos márgenes se suben tres píxeles por lo mismo: `.cookie__config` (13 → 16) y `.cookie__policy`
(14 → 17), porque el área crece 13,5 y 14 hacia arriba y habría tocado la del vecino.

### 26.6 · ⚠️⚠️ La sonda salió ROTA, y sus números eran creíbles a medias

La primera versión midió el área efectiva interceptándola con **todos** los ancestros que recortan,
en coordenadas de viewport. Devolvió anchos de **−652**, altos de **−511** y **29 solapes**. Ninguno
era del producto:

- **Recortar contra un ancestro con el control desplazado fuera de la parte visible de su carril da
  un área NEGATIVA** — y un negativo pasa el filtro de «menor que 44» como si fuera un defecto. Los
  chips del pie que aún no se han deslizado a la vista salían todos como rotos.
- **Dos controles en CAPAS distintas se solapan siempre**: con el menú abierto, la FAQ sigue detrás
  y sus cajas comparten coordenadas. «`faq__q` ↔ `menu__chip`» no es un defecto, es un menú.

▶ Lo que lo zanja es `checkVisibility()` + `elementFromPoint(centro)` **como FILTRO**, con la
geometría siguiendo de MEDIDA. No es lo mismo que el error de `#239`, donde `elementFromPoint` se
usó *como medida* y dio 178 falsos: ahí la pregunta era «cuánto mide» y aquí es «está en pantalla y
por delante».

⚠️⚠️ **Y la MISMA sonda mintió una SEGUNDA vez, ya con esos dos arreglos puestos**: calculaba el
rectángulo del pseudo-elemento **sin aplicar su `transform`**, así que un `translate(-50%, -50%)` se
perdía y el área salía desplazada media caja. Daba altos de **58** donde son 44 y siete solapes que
no existían. *Un pseudo-elemento no está donde dicen su `top` y su `left`: está donde lo deja su
matriz.* ▶ **Dos mentiras de un mismo instrumento en una tarde, y las dos con números creíbles.**

⚠️ **Y la guarda también estrenó su trampa**: `Dom\HTMLDocument` devuelve los nombres de etiqueta en
**MAYÚSCULAS** y XPath distingue el caso, así que `//p` y `//a` dan **cero** con el documento entero
delante — sin error y sin aviso. El caso del enlace en línea salió «rojo por vacío». Se consulta por
`//*` y por clase, y para bajar a una etiqueta, `getElementsByTagName()`.

### 26.7 · Lo verificado

| | Antes | Ahora |
|---|---|---|
| Controles distintos bajo 44 (7 vistas, 390 px) | **37** | **1** — el enlace en línea exento |
| `[data-tap]` recortados por un ancestro | — | **0** |
| Solapes entre áreas | 4 | 11, **todos con ganador inequívoco** |
| Pie a 390 px | 365 | **335** |
| Bloque legal a 390 px | 78, tres renglones | **44**, una tira que se desliza |
| Pie a 1280 px | 287 · 40 · 53 · 14 | **idéntico** |
| Desborde horizontal | — | **0 px** en las siete vistas |

▶ **Los 11 solapes se midieron uno a uno preguntando quién gana en el centro de la región**, y en
nueve gana la pieza flotante que se pinta encima —la barra de móvil, el panel del idioma—, que es
exactamente su trabajo. **Ninguno es dos objetivos de la misma capa peleándose por el dedo.**
⚠️ De los siete nuevos, **cuatro no los causa la tanda**: el barrido recorre la página por
fracciones de su alto, y el pie encogió 30 px, así que las mismas fracciones caen en otro sitio.
*Un barrido relativo no compara con el de antes salvo que la página mida lo mismo.*

▶ **En escritorio solo se mueven las cuatro familias que crecen a propósito**, y está medido:
`.price__cta` 42 → 44 · `.menu__chip` 38 → 44 · `.lang-dd__panel a` 37 → 44 · `.skip-link` 38 → 44
(con foco de teclado, que es su único estado visible). `.faq__q` (29), `.zone-tab` (37) y las dos
tiras del pie se quedan exactamente como estaban.

▶ **Guarda**: `TouchTargetTest`, 9 casos · **8 mutaciones y las 8 muerden**, cada una en su caso —y
la de mudar el área a `::after` muerde en tres, que es lo correcto: rompe el mínimo, el centrado y
el pomo del interruptor de cookies a la vez.

---

## 27. EL LOGOTIPO, POR FIN IDÉNTICO — dos defectos de EXPORTACIÓN (`#275`, 2026-08-30)

> **`[DECIDIDO owner]`**: «lo quiero IDÉNTICO». Quinta sesión sobre el mismo dibujo, y la que la
> cierra. Los dos defectos estaban **en la exportación del lockup a SVG**, no en nuestro CSS — que
> es donde las cuatro tandas anteriores buscaron.

### 27.1 · Lo primero: qué se revirtió, y por qué

`#274` adelgazó al **65 %** el `stroke-width` de las capas de COLOR de las palabras y dejó la
extrusión a su grosor original. Medido ahora con dos instrumentos independientes:

| a la talla de la web (70 px) | su lockup | export del owner | lo que servía `#274` |
|---|---|---|---|
| banda cian | 1,00 px | **1,00** | 0,625 |
| banda tinta | 1,50 px | **1,50** | 1,00 |
| banda blanca | 0,875 px | **0,875** | 0,625 |
| **azul marino por FUERA del cian** | 0 | 0 | **1,00 px, en el 100 % de las filas** |

▶ **Las bandas del export del owner ya eran las suyas, al dígito.** El 65 % las estropeó *y* creó
un **anillo azul marino por fuera del cian** —al encoger la capa de color sin encoger la extrusión,
la silueta de la profundidad pasó a ser más ancha que la letra— que su lockup no tiene. Ése era el
«borde exterior azul demasiado grueso» que el owner volvió a ver.
⚠️ Y tenía **una segunda cara**: sobre la superficie de TINTA del menú, el anillo exterior pasó de
cian (**6,85:1** de contraste sobre `#101418`) a marino (**1,41–2,54:1**). Se sustituyó un borde de
alto contraste por uno casi invisible sin que nada avisara.
⚠️ Y una **tercera**: el guion no tocaba `#fig`, así que dentro del mismo lockup convivían letras al
65 % y una figura —que **es** la Y— al 100 %.

▶ `scripts/logo-contorno.php` **se retira**. Su docblock declaraba una invariante falsa y su propia
guarda contaba que **no** había tocado la extrusión, que era justo el defecto.

### 27.2 · Defecto 1 — `text-shadow` NO arrastra el `-webkit-text-stroke`

Con las bandas ya idénticas, el owner seguía viendo diferencia: *«nosotros tenemos un borde de unos
5 píxeles exterior… el logo de la landing lo tiene a unos 2 o 3»*. La comparación que faltaba era
del **experimento**, no de otra medición del dibujo:

| en navegador, Lilita One a 36 px | ancho |
|---|---|
| glifo **con** `-webkit-text-stroke: 6.5px` | **20,63 px** |
| su **`text-shadow`** | **14,13 px** |
| el mismo glifo **sin** trazo, su sombra | **14,13 px** ← el control |

▶ **La sombra del mockup son copias del GLIFO DESNUDO.** La exportación a SVG les puso a los 26
`<use>` de extrusión el mismo `stroke-width` que a la capa de color, así que cada copia salía
**3,25 px más gorda por lado**. Medido a la talla de la web, el faldón azul bajo las letras:

| | mediana | p90 | columnas con faldón |
|---|---|---|---|
| su lockup | **4,00 px** | 6,125 | 852 / 1137 |
| export del owner | 7,125 | 9,75 | **1121 / 1121** |
| `#274` (65 %) | 8,25 | 13,75 | 1121 / 1121 |
| **corregido** | **4,125 px** | **6,125** | 882 / 1123 |

⚠️ Fíjate en la última columna: con la sombra engordada **todas** las columnas tienen faldón, porque
asoma siempre. Es la señal que distingue «más sombra» de «sombra mal construida».
▶ De paso, el bulto total del lockup pasa de **189,6 × 65,9** a **186,4 × 62,6** contra sus
**188,4 × 62,1**: del 6 % de desvío en alto al **0,8 %**.
▶ Guion: `scripts/logo-sombra.php`. **`#fig` NO entra, y no es una omisión**: su profundidad en el
mockup es un `drop-shadow` de una `<img>`, y **un `drop-shadow` sí sigue el alfa completo del dibujo,
contorno incluido**. Ahí la extrusión con trazo es lo correcto. *Dos mecanismos que parecen el mismo
y no lo son.*

### 27.3 · Defecto 2 — la silueta venía RESTADA de las letras

En el lockup, «PLA» es TEXTO y el saltador una `<img>` **encima**. En la exportación, **la silueta se
restó del trazado de la palabra**: la **A** viene mordida por su contorno, le falta el **16,8 %** del
área. En reposo no se ve —el dibujo ocupa el hueco—, pero `brand-hop` arranca en `opacity: 0` y hace
volar la silueta, así que durante la espera y todo el vuelo el logotipo enseña una A mutilada.
▶ Lo cazó el ojo del owner: *«se queda el contorno en la A de la silueta, no se va la A perfecta»*.

**Cómo se reconstruye** (`scripts/logo-letra-a.php`, y esto es lo que hay que saber para rehacerlo):
se toma la A de **Lilita One** —la misma fuente del lockup— y se coloca con la transformación afín
recuperada **del propio trazado**, usando las letras **P y L**, que están intactas: se igualan
centroides y covarianzas de área.

    M = [[0.191294, -0.000132], [-0.016508, 0.192554]]     t = (164.47, 314.508)

⚠️ **Con su control, que es lo que lo hace fiable**: la consistencia del ajuste (`|u|` contra `|w|`)
sale a **0,001 %**, y la **L —cuya FORMA no se usó para ajustar, así que es validación
independiente— cae encima con 0,52 % de área y 0,31 unidades de caja**: 0,04 px a la talla de la web.
⚠️ Una afín mapea Béziers a Béziers de forma **exacta**, así que la letra no se aplana: se
transforman sus puntos de control. El trazado pasa de 2.746 a 446 bytes.
⚠️⚠️ **La misma geometría vive en DOS sitios**: en `#u1` —que alimenta contorno, tinta y blanco por
`<use>`— y en el `<path>` del **relleno de color** de la letra, que es copia literal. Arreglar solo
el primero **deja la parte restituida en BLANCO** y no falla nada. Se descubrió mirando la captura,
con el arreglo ya dado por bueno.
▶ **La letra es del CLIENTE, no del producto** (`DECISIONES #1`): vive en
`public/img/client-logo-a.path`, con las **tres piezas** del patrón de marca (gitignorada, se pasa al
guion, `deploy.sh` la excluye del `--delete`).
▶ **Lo que de verdad cierra esto es el export**: `INSTALACION-CLIENTE.md` §4.a.septies lo exige.

### 27.4 · Defecto 3 — el velo de DENTRO salía 3,4× más fuerte, y en el tono equivocado

Con las bandas y la sombra exterior ya idénticas, el owner señaló lo último: *«desde abajo hay como
una sombra muy ligera, casi no la aprecio; en el nuestro esa sombra inferior interior es más fuerte y
hace que el logo pierda color vivo»*.

▶ **Causa 1, la CAJA.** El lockup pinta ese velo con `background-clip: text`, así que **el degradado
se mide sobre la CAJA DE LÍNEA**; la exportación lo tradujo a un `<linearGradient>` con
`objectBoundingBox`, que se mide sobre la **TINTA**. No son la misma caja —la de línea baja hasta el
hueco de los descendentes—, así que sus paradas, copiadas literalmente, ponen al pie de las letras un
valor que en el suyo **ya había decaído**.

Medido en el borde inferior de la «J» (α del velo, canal R contra el color del velo):

| distancia | 0,25 | 0,50 | 1,00 | 1,50 | 2,00 | 3,00 | 4,00 | 6,00 px |
|---|---|---|---|---|---|---|---|---|
| **su lockup** | 0,112 | 0,107 | 0,096 | 0,086 | 0,076 | 0,051 | 0,025 | 0,000 |
| el export | **0,377** | 0,360 | 0,326 | 0,293 | 0,259 | 0,192 | 0,155 | 0,092 |
| **corregido** | 0,117 | **0,107** | **0,096** | **0,086** | 0,071 | **0,051** | **0,025** | **0,000** |

**Seis de las ocho muestras salen idénticas**; las otras dos se separan 0,005, que es un escalón de
cuantización de la sonda. Las paradas pasan de `.5 / .18 / 0` en `0 / 15 % / 33 %` a **`.14 / 0` en
`0 / 20 %`** — dos paradas, porque el tramo que queda del suyo es RECTO.

▶ **Causa 2, el TONO.** El lockup usa **un velo distinto por palabra** —`rgba(6,28,44,…)` bajo la
palabra fría y `rgba(48,16,2,…)`, un marrón, bajo la cálida— y la exportación dejó **uno solo, el
frío, para las dos**. Un velo azul sobre amarillos y naranjas no los oscurece: los **desatura**. Eso
es literalmente el «pierde color vivo».
⚠️ **El color cálido no vive en el producto**: se le pasa al guion por argumento, porque es un dato
de la marca (`DECISIONES #1`). Sin él, el guion aplica solo la corrección de fuerza, que sí es general.

⚠️ **Y el BRILLO superior se midió también, y NO diverge** (α 0,278 el suyo contra 0,263 el nuestro,
y los dos se apagan a la misma distancia): no se toca. *Que dos degradados compartan mecanismo no
quiere decir que los dos estén mal.*
⚠️⚠️ **La primera sonda del brillo era basura y parecía un resultado**: medía α por el canal R contra
un velo blanco, o sea con denominador 10, y devolvía valores cuantizados a saltos de 0,1. Se rehízo
por el canal B (denominador 255).

### 27.5 · Verificación

Cadena reproducible desde el export intacto del owner:
`logo-pjp.svg` → `logo-sombra.php #301002` → `logo-letra-a.php` = el fichero instalado, **byte a
byte**, y los dos guiones son **idempotentes** (el de `#274` multiplicaba: al segundo pase dejaba los trazos
al 42 %).

Por píxel, con controles: **en reposo el logotipo no cambia (0,05 %)**; con la silueta oculta sí
(0,49 %), que es la A completándose; ocultar la silueta cambia algo (1,97 %) y el mismo fichero
contra sí mismo da 0.

▶ **Guarda**: `BrandLogoRepairTest`, 10 casos · **6 mutaciones muerden**. Y una cuarta **no**, por un
motivo que hay que saber: retirar solo el `exit(1)` de la comprobación del relleno deja la guarda en
verde **porque el guion tiene DOS capas de defensa** (la comprobación del relleno y el «tienen que
sustituirse las DOS apariciones»). Al retirar el bloque entero, muerde. *Una mutación que no muerde
puede ser una mutación DÉBIL* — la lección de `panel-navegacion.md` §7.4·2, otra vez.
⚠️ Los casos **no leen el logotipo instalado**, y es deliberado: está gitignorado y un caso que solo
corre donde hay paquete de marca desestabiliza el contador de aserciones del `pre-push`.

### 27.6 · Las trampas de instrumento de esta tanda (siete, y todas parecían resultados)

1. **El lockup del owner es un `<a>`**, y sin reset de enlaces el navegador le pinta su **subrayado
   azul** por defecto. Salía como una barra cruzando la palabra: contaminaba cualquier diff y **le
   inflaba la caja de tinta de 60,75 a 65,5 px** — un número que llegué a reportar.
2. **Las `figcaption` dentro del recorte** contaban como tinta: caja de 88 px donde son 70.
3. **Clasificar colores por vecino más próximo** mete el antialias en la clase equivocada: una
   mezcla papel↔cian cae en `#4FC0EA`, que es un color de LETRA. Umbrales estrictos, y el antialias
   como clase propia que se descarta.
4. **Dos SVG en el mismo documento comparten `id`**: los `<use>` del segundo resolvían contra los
   `defs` del primero, así que el arreglo y el original salían idénticos (0 % de diferencia) y
   parecía que la reparación no hacía nada. Se renderizan por separado.
5. **Recortar un `<g>` con una expresión regular** dejó el SVG con 3 `<g>` y 4 `</g>`: el navegador
   se lo tragó sin quejarse y devolvió otro 0 % perfectamente creíble.
6. **Medir α por un canal MAL CONDICIONADO**: la opacidad de un velo blanco sobre amarillo puro
   calculada por el canal R tiene denominador **10**, así que salía cuantizada a saltos de 0,1 y
   parecía un perfil. Por el canal B el denominador es 255.
7. **Y el denominador cambiado a media medición**: al comparar la variante corregida seguí usando el
   color del velo VIEJO, así que su α salía un 23 % baja y el ajuste parecía quedarse corto.

❗ Las siete daban números plausibles. **La que las cazó todas fue tener siempre un CONTROL** —el
mismo espécimen contra sí mismo, y una diferencia que DEBE salir distinta de cero—.

### 27.7 · Lo que esta tanda deja abierto

- El logotipo se incrusta **DOS veces por página**: `nav.blade.php` monta `<x-site.brand>` en el
  racimo y otra vez en la cabecera del cajón (`.mob-menu`). Son 128 KB, el **42 %** del HTML de
  `GET /`, y la segunda copia no se pinta nunca — la regla `.mob-menu` de `site.css` la apaga con un
  `display: none` **incondicional**. Ficha en `DEUDA.md`.
- La variante sobre TINTA es **inerte** en la rama del SVG en línea: las reglas de
  `.nav__brand-logo--ink` / `--paper` solo conocen esos dos modificadores y lo que se sirve lleva
  `--inline`. Ficha en `DEUDA.md`.
- El escalón de teléfono (`--nav-logo-h`) corta en **≤ 719 px** y el del mockup en **< 620**: entre esos dos anchos
  nuestro logotipo mide 46 px donde el suyo mide 70. La proporción sí es fiel (46/70 = 0,657 contra
  su `scale(.66)`). Ficha en `DEUDA.md`.

---

## 28. EL ESLOGAN PEGADO AL TITULAR Y EL CTA DE MÓVIL (`#276`, 2026-08-30)

> Dos ajustes pequeños del owner **sobre nuestra landing, no sobre el mockup**, y los dos destapan
> un número escrito a mano.

### 28.1 · El eslogan se pega al filo del TITULAR, no al del hero

`[DECIDIDO owner]`: «ponlo pegado al texto DIVERSIÓN en la esquina superior izquierda» · «que no
haya margen entre los dos».

El bloque del hero va **centrado** (`#253`), así que el eslogan se centraba sobre el titular y
arrancaba por la mitad de la palabra. ▶ **Alinearlo a la izquierda del CONTENEDOR no sirve**: eso lo
manda al filo del hero. El filo que importa es el del **titular**, y para conocerlo hay que compartir
caja — entra `.hero__headline`, que se ajusta al titular y alinea el eslogan dentro.

⚠️⚠️ **`margin: 0` no deja el hueco en cero.** Medido: **7,3 px** sobre la «D» en escritorio y **3,0**
en el teléfono. Lo ponen los DOS textos:

| quién | qué aporta |
|---|---|
| el ESLOGAN | el aire que su caja de línea reserva **por debajo de su tinta** |
| el TITULAR | la distancia de su caja a la **altura de mayúscula** (con `line-height: 0.82` la tinta se le sale de la caja, así que resta poco, pero no es cero) |

▶ **Cada uno paga su parte en su PROPIO `em`**, que es lo único que escala solo: los dos cuerpos
cambian con la ventana en proporciones distintas (a 1440 el titular mide **5,5×** el eslogan; a 390,
**2,9×**), así que un valor fijo que cerrase escritorio se pasaría en el teléfono.

▶ **El tope no lo pone el gusto, lo pone el CHOQUE.** El eslogan va girado −2,2° con el origen abajo
a la izquierda, o sea que su tinta más baja cae justo sobre la «D». Barrido en siete ventanas
(1920 · 1440 · 1280 · 1024 · 430 · 390 · 390 corto):

| compensación | hueco mínimo peor |
|---|---|
| sin tocar | 3,0 px |
| −0,10 em | 1,0 |
| **−0,18 em** (elegida) | **0,2** |
| −0,22 em | solapan |

Verificado en el producto: mínimo **0,17–1,33 px** y **cero columnas solapadas** en las cuatro
ventanas de control.

⚠️⚠️ **La doble clase del selector no es cosmética**: la regla base del titular declara `margin: 0`
con la misma especificidad y **va después** en la hoja, así que con un solo `.hero__title--onvideo`
la corrección se aplicaba **a medias** —cerraba la parte del eslogan y no la del titular— y nada
fallaba.

### 28.2 · El CTA flotante de móvil, y los dos números que colgaban de él

`[DECIDIDO owner]`, con el CSS ya probado en su inspector: el relleno inferior de `.book-bar` sube de
10 a **19 px**.

⚠️⚠️ **No es un número suelto.** Dos piezas se apoyaban en él y las dos estaban escritas a mano:

| pieza | llevaba | qué era |
|---|---|---|
| el lanzador del widget de ofertas, cuando se aparta | `92px` | `10 + 56 + 10` de la barra **+ 16** de su reposo |
| la reserva del hero por abajo, para que el bloque centrado no caiga detrás | `84px` | un redondeo de los **76** que la barra medía entonces |

▶ Subir la barra sin tocarlos la habría metido **por debajo del lanzador sin que fallara nada**: son
dos elementos `fixed` que no se conocen y ninguna captura los enseña juntos. Es la familia de `#252`
—*un token fuera de alcance no falla, rinde su reserva*—.
▶ El aire pasa a `--book-bar-pad-top` / `--book-bar-pad-bottom`, lo que la barra ocupa a
**`--book-bar-block`**, y los dos consumidores lo **derivan**.

### 28.3 · Tres sondas propias que dieron números creíbles y falsos

1. **Medir el hueco por FILAS** daba **22,17 px** donde eran 7,3: buscaba la primera tinta del
   titular *por debajo* del eslogan y **se saltaba la altura de mayúscula**. Con ese número se
   calculó una compensación que solapaba 15 px.
2. **El «mínimo» calculado sobre un tercio del ancho** escondía el solape del resto. El hueco no es
   uniforme —varía de 7,3 a 81,3 px a lo largo del eslogan—, así que la única métrica que vale es
   **el mínimo por COLUMNA en todo el ancho**.
3. **La primera guarda del envoltorio NO mordía** al colar un elemento entre el eslogan y el titular:
   un `.*?` perezoso **retrocede hasta el `</span>` del intruso**. Acotada a `[^<]*`.

▶ **Guardas**: `HomePageTest` (el envoltorio, con su orden) y `ArmazonContractTest`
(`what_the_floating_bar_takes_is_declared_once`) · **4 mutaciones y las 4 muerden**.

---

## 29. LAS MICROANIMACIONES DEL CLIENTE — qué de su artboard ya estaba (`#277`, 2026-08-30)

> El owner entrega `Microanimaciones PJP` y pide **valorarlo antes de implementarlo**. Conviene
> empezar por lo que NO hay que hacer.

### 29.1 · El vocabulario ya estaba — nada que hacer

**Las cuatro curvas y las siete duraciones son idénticas**: `#222` ya tomó este mismo artboard.
Y su primera regla —«entra rebotando, sale limpio»— **se cumple sin que nadie la escribiera**: cero
transiciones de salida con curva de sobreimpulso, medido.

### 29.2 · Lo que diverge, medido

| | el artboard | el producto | ¿se hace? |
|---|---|---|---|
| **bucles** | «los únicos permitidos son los tres cargadores» | **24 declaraciones**, solo 2 son cargador | ⏸️ ficha en `DEUDA.md` |
| **hover** | pegatina que se aplasta; el color **no cambia nunca** | `.btn` sube y cambia el fondo a `--action-hover` | ⏸️ toca `#209` |
| **movimiento reducido** | quitar recorrido, **mantener el fundido** | 12 de 31 bloques hacen `transition: none` | ✅ §29.3 |
| **cascada de franjas** | cae, aplasta, desfase 90 | no existía | ✅ §29.4 |
| **sello + check** | el desenlace de la reserva | no existía | ⏸️ pendiente |

⚠️⚠️ **Sus dos artboards se contradicen.** Éste prohíbe los bucles decorativos —«banners que
respiran, iconos que laten»— y el interruptor del titular, que son **tres bucles de 3,4 s**, salió del
artboard **`6d` del propio cliente** (`#262`). Y el aro que invita al CTA es un `[DECIDIDO owner]`
(`#205`). Podar es revertir dos decisiones suyas.
⚠️ **Y el artboard se contradice a sí mismo**: su curva «Salida» dice servir «todo el hover», pero su
propia tarjeta de iconos usa Bote a 120 ms. Es la **sexta** contradicción del cliente consigo mismo.

### 29.3 · U2 · Sin recorrido no es sin fundido

Su norma es explícita: «desaparecen los desplazamientos y las escalas, **pero se mantienen los
cambios de opacidad de 120 ms** — quitar también el fundido deja la interfaz saltando de estado sin
avisar». Medido en navegador **con control** (comparando contra `no-preference`):

| | antes | ahora |
|---|---|---|
| el rótulo del CTA doble al intercambiarse | `none / 0s` → aparecía de golpe | `opacity / 0.12s` |
| el bloque de cuenta del cajón | `none / 0s` | `opacity, visibility / 0.12s` |

⚠️ Ese `visibility` **no es movimiento**: es lo que saca el bloque del orden de tabulación DESPUÉS de
ocultarse. Con `none` desaparecía del árbol de accesibilidad de golpe.
⚠️⚠️ Y había **un segundo bloque de movimiento reducido para el mismo elemento 200 líneas más abajo**
que lo volvía a matar. Tras corregir el primero, `.acct` seguía computando `none`. *Dos bloques para
el mismo elemento no se resuelven con especificidad: gana el último.*

### 29.4 · U3 · La cascada de franjas — y el dato que nadie leía

⚠️⚠️ **`sellable` llevaba desde siempre en el contrato y el cajón no lo leía.** `SlotOffer` marca las
franjas llenas con `sellable: false` **a propósito** —su docblock dice «se muestran deshabilitadas, no
se ocultan»— y el paso 3 las pintaba como un chip normal y clicable: sólo al pulsarlo aparecía
«agotado» abajo, en el contador de cantidad.
▶ **El PANEL sí lo respeta** (`manual-order-times.blade.php` → `@disabled(! $slot['sellable'])`): el
mismo dato, honrado en la superficie del operador e ignorado en la del cliente. *Un contrato cumplido
en una superficie y no en la otra no lo caza ningún test del contrato.*
▶ La regla pasa a `offer.js::isSoldOut()`, junto a `isAlmostFull()`, para que el componente solo
pinte (`CE-4`). ⚠️ **Se compara con `=== false`, no por veracidad**: una respuesta sin el campo tiene
que seguir vendiendo, o el paso entero se queda mudo y nadie compra.

**La coreografía, con sus números** — cae de **32 px** con la curva LONA en **420 ms**, aplasta a
**1,12 / 0,76** al aterrizar, rebota a 0,98 / 1,05 y asienta; el origen es su **base** (`50% 100%`),
que es lo que hace que el aplastado se lea como peso. Verificado en el cajón real: `chip-cae`, 0,42 s,
desfase **0,09 / 0,18 / 0,27 / 0,36 s**.
⚠️ **La franja completa cae igual que las demás**, regla suya explícita: «negarle el rebote sería
castigarla dos veces». Se le quita la venta, no la presencia.
⚠️ **El desfase se DERIVA, no se escribe.** 90 ms **no está en la escala de siete duraciones del
propio artboard**, y un literal dentro de una `custom property` no lo ve ningún inventario de
`transition` (la lección de `#222` §16.4). Se expresa como fracción del techo, igual que
`--jj-desfase` («120 ms sobre 900»).
⚠️ **El índice va topado a 7**: con 11 horas el último chip ya espera 900 ms; un día de treinta
esperaría casi tres segundos. Los que quedan fuera del carril no se ven entrar.
⚠️⚠️ **Y un `opacity: 0.62` nació MUERTO**: la cascada termina en `opacity: 1` y corre con `both`, así
que **fija su último fotograma y gana a la regla CSS** —una animación gana siempre—. Medido: computaba
1. Es el mecanismo de `#265`, ahora sobre la opacidad. Retirarlo es además **más fiel**: la norma pide
rojo tachado, no atenuar.

### 29.5 · Lo que enseñó el instrumento

⚠️⚠️ **Cinco sondas estáticas propias dieron números creíbles y falsos, todas sobre lo mismo.** Dije
que **10** bucles seguían corriendo con movimiento reducido; luego **6**; y **son cero** — una regla
general (`.icon *`) los apaga a todos y ningún `grep` de selectores la veía, porque el selector de la
regla no contiene el del icono. **Lo zanjó el navegador con `getAnimations()`.**
⚠️ Y la suite lo dijo antes que yo: los 34 fallos de `SidebarDomContractTest` **no eran del cambio**,
sino de no haber corrido `build:ssr` — ese test trae su propia guarda de «bundle SSR rancio», que es
exactamente la clase de guarda que evita perseguir un fantasma.

▶ **Guardas**: `MotionScaleTest::test_the_slot_cascade_keeps_its_contract` (4 mutaciones, las 4
muerden — y la del literal muerde además la guarda de duraciones a mano) y dos casos nuevos en
`offer.test.js` (la franja no vendible; y que **la ausencia del campo no es «completa»**).

---

## 30. EL DESENLACE: DOS PIEZAS Y SIN CONFETI (`#278`, 2026-08-30)

> `[DECIDIDO owner]`: «quitamos el confeti, tampoco vamos a saturar al cliente» · «quiero
> originalidad y diferenciación **sutil y elegante, sin saturar**».

### 30.1 · El confeti ya marcaba lo mismo dos veces

Desde `#258` el desenlace enseña la **pegatina de éxito**, y el artboard de estados escribe que «la
pegatina de estado **nunca convive con otra** en la misma pantalla». El de movimiento pone el techo en
**dos** elementos animándose a la vez. Con confeti, pegatina y sello eran **tres**, y dos decían lo
mismo. ▶ Fuera `celebrate()` y fuera el `watch` que lo disparaba.

⚠️⚠️ **Retirarlo habría dejado CIEGA una guarda ajena sin ponerla roja.** `SidebarMountTest` usaba
`celebrate()` como **delimitador** para recortar `close()`: sin él `mb_strpos` da `false`, el recorte
sale vacío y `assertStringNotContainsString` pasa **vigilando la nada**. Es la trampa de
`panel-navegacion.md` §5·3. Ahora se delimita con el cierre del método **y se asevera que el corte
existe** — que es lo que convierte un delimitador en una guarda.

### 30.2 · Lo que celebra ahora

| pieza | de dónde salen sus números |
|---|---|
| **la pegatina** entra creciendo | `0.3 → 1.16 → 1`, curva LONA, 420 ms |
| **el código se SELLA** | cae de −34 px girado −16° a escala 1,35 y asienta en −6° |

En el artboard el sello estampa «PLAZA 12» —la cosa conseguida—; aquí la cosa conseguida es el
**localizador**, que es lo que el cliente se lleva.
▶ **Se secuencian, no se solapan**: el sello espera `--dur-estado`. Es la misma regla que su artboard
aplica a la espera y el check —«nunca se solapan»—, y con las dos a la vez el ojo no sabe cuál mirar.
⚠️ El sello es **la única rotación animada del sistema**, y lo dice su norma: con dos dejaría de leerse
como un gesto.

⚠️⚠️ **El sello va NEUTRO, y es una desviación decidida.** Su artboard lo estampa en **amarillo**
porque allí es la única pieza de la pantalla; aquí comparte sitio con una pegatina verde, y dos
rellenos saturados seguidos convierten el desenlace en un semáforo — justo lo que el owner pidió
evitar. Y su amarillo es además el rol de **AVISO** del producto: un localizador no avisa de nada.
▶ Se conserva lo que lo hace un sello —**keyline duro, sombra dura y giro**—; el color lo pone la
pegatina, que es la que dice «ha salido bien». *Volver al amarillo es una línea, si el owner lo
prefiere al verlo.*
⚠️ `display: inline-block` no es cosmético: sobre un `<strong>` en línea, `transform` **no aplica**.
⚠️ Movimiento reducido: su norma es literal —«el sello **aparece sin caer**»—, así que se conserva el
fundido **y el giro de reposo**, que no es movimiento sino la forma de la pieza.

### 30.3 · Lo que enseñó el contrato de árbol

Añadir una clase al `<strong>` puso rojo `SidebarDomContractTest`. El «lado Livewire» es hoy el
**manifiesto congelado** —Livewire se fue en `#111`— y regenerarlo **acepta cualquier deriva**: la
única guarda que queda es decir POR QUÉ en el commit. Se comprobó antes que la única deriva era la
intencionada (3 árboles del desenlace) y se regeneró.

⚠️⚠️ **Y destapó un hueco de cobertura de `#277`**: el chip de hora COMPLETA **no lo cubría nadie**.
`disabled` **sí** es atributo de contrato para el normalizador… pero **ninguna fixture tenía una franja
llena**, así que el nodo nunca aparecía. *Un atributo solo está cubierto por el caso que lo hace
aparecer* — la misma lección que el `aria-current` del día elegido y que el umbral de `/config`.
Entra `test_a_full_hour_is_offered_disabled`, con `capacity: 0`.
⚠️ Y la baseline de `PurchaseSection.vue` **BAJA** de 429 a 425 —el `watch` del confeti se va entero—
y se aprieta en el mismo commit: una baseline que no aprieta regala el crecimiento siguiente.

▶ **Guarda**: `MotionScaleTest::test_the_outcome_is_two_pieces_and_no_confetti`, **4 mutaciones y las
4 muerden** — y una de ellas es que **el confeti no puede volver**: no basta con haberlo borrado, hay
que impedir que alguien lo reintroduzca sin enterarse de que su sitio ya está ocupado.

---

## 31. EL INTERRUPTOR PARA, Y PARA ENCENDIDO (`#280`, 2026-08-30)

`[DECIDIDO owner]`: **«que se pare tras unos ciclos»** —tres— **y que vuelva a arrancar cuando se
vuelve a lo alto de la página**. Es la unidad que `#279` dejó decidida y sin hacer, y **corrige a
§25**, que montó la pieza como bucle sin fin porque así la enseña el artboard `6d`.

### 31.1 · El defecto era peor que lo previsto, y hay que medirlo antes de creérselo

`#279` avisó de que esto no era cambiar `infinite` por un número. Medido en navegador sobre el CSS
de entonces, acotando las iteraciones:

| | pista | bulbo | rótulo |
|---|---|---|---|
| **acotado a 2 ciclos** | transparente + borde `--fg-mute` | gris, `translateX(0)` | **`opacity: 1`** |
| control (`prefers-reduced-motion`) | `--ok` | `--paper-fg`, `translateX(48,09)` | `opacity: 1` |

⚠️⚠️ **No quedaba «apagado»: quedaba apagado con la palabra ON encendida encima.** El rótulo no
tenía `opacity` propia, así que su valor inicial ya era 1 — y eso, que en la pieza en marcha no se
nota, es lo que convierte el defecto en una contradicción a la vista.

▶ **El estado encendido entero vivía dentro del `@media (prefers-reduced-motion: reduce)`.** Ésa es
la lección general de esta unidad: *el reposo de una pieza no se escribe dentro de una excepción de
accesibilidad; si solo existe allí, fuera no existe* — y no lo ve nadie, porque quien revisa con
movimiento reducido ve la pieza perfecta.

### 31.2 · Por qué el corte es 0,6 y no un número entero de ciclos

El ciclo de `6d` va *apagado → salto → encendido → vuelta a apagado*, así que un número entero de
iteraciones termina en el fotograma **apagado**; y al soltar la animación —`fill` es `none`— la
pieza volvería a su regla base de golpe. La salida es **truncar la última iteración dentro del
tramo encendido**, donde el valor animado es constante y **es el mismo que el reposo**: no hay salto
porque no hay diferencia.

```css
--switch-cycles: 3;                                        /* :root — es de la INSTALACIÓN */
--switch-runs: calc(var(--switch-cycles) - 1 + 0.6);       /* .hero__switch — dónde para */
```

▶ El tramo encendido es **38–82 %** en la pista y el bulbo y **40–80 %** en el rótulo: 0,6 es el
centro del más estrecho, con 20 puntos de margen a cada lado.
⚠️ **La otra salida era rotar los fotogramas** para que el ciclo empezara y acabara encendido. Se
descartó: la geometría y los tiempos de `6d` están al dígito desde `#262` —es lo que hace la pieza
comparable con el artboard— y girar la fase los dejaría irreconocibles.
⚠️ **`forwards` tampoco valía**, como avisó `#279`: fija el último fotograma, que es el apagado.

▶ **Medido**: primer paint apagado (no hay destello del reposo), 9 % del ciclo apagado —el control
de que de verdad se mueve— y el fotograma del corte idéntico al reposo en las tres piezas:
**0 px de salto**.

### 31.3 · El rearranque no puede ser Web Animations API

⚠️⚠️ **Una animación CSS terminada con `fill: none` deja de ser «relevante» y desaparece de
`getAnimations()`**: medido, la lista pasa de **1 a 0** en cuanto para. No hay objeto al que pedirle
`play()`, así que la vía obvia no existe. Lo que funciona es obligar al motor de estilo a
**descartarla y recrearla**: `animation-name` a `none`, una lectura de disposición en medio, y de
vuelta a la cascada. ⚠️ La lectura de en medio no es superstición: sin ella el navegador agrupa las
dos escrituras del mismo fotograma, ve el valor final y concluye que nada ha cambiado.

▶ Vive en `resources/js/ui/hero-switch.js` y **no decide nada de diseño**: ni duración, ni curva, ni
ciclos. Eso está en `--dur-switch`, `--ease-entra` y `--switch-cycles`, que es donde una instalación
puede tocarlo (mismo criterio que §12.3).
⚠️ **Hay que haber SALIDO del todo del viewport para volver a entrar.** Con umbral 0 basta un
temblor de un píxel —o el imán de §20, o el crecimiento del documento al cargar las imágenes
perezosas— para que lluevan entradas «visible»: rearrancar con cada una devolvería el tic que se
acaba de quitar.
⚠️ **Con movimiento reducido es un no-op a propósito**, sin una segunda puerta de `matchMedia`: el
`@media` deja las tres piezas en `animation: none`, así que quitar y devolver el `animation-name`
devuelve `none`. Una comprobación aquí sería una segunda fuente para la misma regla, y habría que
mantenerla sincronizada.
▶ **Sin JavaScript la pieza sigue completa**: la animación está declarada en la regla base, salta
sus ciclos al cargar y descansa encendida. Lo único que se pierde es la repetición.

### 31.4 · Lo que le hace al presupuesto de `#279`

Medido en la misma pasada, reproduciendo el estado anterior por inyección de `infinite`:

| vista | pico de bucles antes | ahora |
|---|---|---|
| `/` | 5 | **2** — cumple el techo de dos de su artboard |
| `/entradas` | 6 | **4** |

Y en el conjunto de las hojas, las declaraciones en bucle bajan de **24 a 21**.

⚠️ **Corrige a `#279`: el interruptor no eran 2 bucles, eran 3.** Su tabla contó lo que la sonda veía
en un instante, y el rótulo pasa por `opacity: 0` en parte del ciclo, donde la sonda —con razón— no
lo cuenta como visible. *Contar animaciones en un instante subestima una pieza cuyo ciclo apaga una
de sus partes*: hay que unir las muestras de varias paradas.

### 31.5 · La guarda

**`HeroSwitchRestsOnTest`** vigila el **mecanismo**, no los números: lee los `@keyframes` reales,
calcula dónde cae el corte a partir de `--switch-runs` y comprueba que ahí el valor es **constante**
y **coincide con el reposo declarado**. Además: que ninguna de las tres es `infinite`, que las tres
leen `--switch-runs`, que `--switch-cycles` está en `:root`, que el bloque de movimiento reducido
**solo** dice `animation: none` —y que cubre exactamente las tres piezas, porque un bloque vacío es
el otro extremo del mismo fallo— y que el ciclo no mueve ninguna propiedad que la guarda no compare.
**8 mutaciones, las 8 muerden**; en JS, **6 más y las 6 muerden**.

⚠️ **La guarda nació ROJA con el producto sano**, y la razón vale para cualquier lector de CSS:
buscaba `selector … {` con un `[^{]*` en medio, así que ante una lista `a,\n b,\n c { … }` se
tragaba las tres y devolvía **una**. Se lee la regla entera y se parte la lista por comas.
