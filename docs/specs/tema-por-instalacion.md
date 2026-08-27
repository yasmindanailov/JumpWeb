# [SPEC] El tema por instalación — los MECANISMOS que al paquete de un cliente le faltan

> Estado: 🟦 **TANDAS 1 y 2a EN `main`** (`#192` · `#193`) · Última actualización: 2026-08-27 ·
> Decisiones asociadas: `DECISIONES #192` (tanda 1) y `#193` (tanda 2a). **El número se fija al
> EMPUJAR**, mirando el remoto — ver `docs/CONVENCIONES.md` §10.
>
> ❗ **Sigue 🟦 por una sola cosa, y es del owner: su pasada de NAVEGADOR** sobre las **11
> declaraciones que la 2a mueve a propósito** (§10.2, enumeradas) y sobre la **tira del pie**. La
> suite comprueba que cada token vale lo que tiene que valer; no comprueba que el resultado guste.
>
> ⚠️⚠️ **DOS SECCIONES DE ESTE DOCUMENTO ESTÁN CADUCADAS Y LA CORRECCIÓN VA ANTES QUE EL TEXTO:**
> · **§1.2** — la premisa «el sistema alterna dos superficies, nunca dos papeles seguidos»
>   **YA NO ES CIERTA**. La norma vigente del cliente es **papel continuo de arriba abajo**, y el
>   contraste lo dan las TARJETAS (su hallazgo `S-00`, severidad Alta, ya aplicado). Verificado en el
>   canvas: **cero** fondos a sangre en los 218 KB del mockup. Buena noticia: el mecanismo de la
>   tanda 1 no se pierde, **se usa más** — pasa de la sección a la tarjeta.
> · **§7** — el plan de tandas se rehízo: la 2 se parte en **2a** (hecha), **2b** (el hero), **2c**
>   (el menú, a spec propia) y **2d** (el movimiento).
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
| **2c** ⬜ | **El menú** → **spec propia** | — | `[DECIDIDO owner, 2026-08-27]`. Sustituir la barra horizontal por logo + hamburguesa + menú a pantalla completa toca **12 vistas**, los dos desplegables con sus datos del CMS (`show_in_nav`), el botón de registro y la barra de móvil. No es «adoptar una estructura»: es cambiar la navegación del sitio. |
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
