# [SPEC] El tema por instalación — los MECANISMOS que al paquete de un cliente le faltan

> Estado: 🟦 **TANDA 1 COMPLETA — las 6 unidades hechas y verificadas** · Última
> actualización: 2026-08-27 · Decisión asociada: `DECISIONES #N` al aprobarse (**el número se fija
> al EMPUJAR**, mirando el remoto — ver `docs/CONVENCIONES.md` §10).
>
> ⚠️ **Lo que está hecho vive en el árbol y NO está commiteado.** El registro unidad por unidad,
> con lo que se midió y lo que se corrigió sobre la marcha, está en **§9**.
> ❗ **Al commitear hay que subir el contador de la suite en `ESTADO.md`**: 2984 → **2998** (8 de
> `SurfaceScopeTest` + 6 de `ThemeFontsTest`). El `pre-push` compara ese número con lo que da la suite y corta si
> no cuadran.
>
> ⚠️⚠️ **Empieza por §1.7.** Es la medida que cambia el tamaño de todo lo demás: el CSS del producto
> ya está tokenizado, así que **el 45 % de los usos de `var()` se invierten solos**. El trabajo no
> son 1.900 reglas, son **52 literales en 44 declaraciones** y siete definiciones de token.
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
3. ❗❗ **10 de sus 18 artboards están SIN MIGRAR** —llevan la paleta anterior (`#2FB6DE`, `#0E8FCB`,
   `#8DC63F`) y las fuentes anteriores (Anton, Space Grotesk)—, y entre ellos están precisamente los
   de logotipo, menú y hero. **Los mismos elementos están rehechos con el sistema vigente dentro de
   `Landing PJP Modos`**. De los sin migrar se saca la FORMA; el color, jamás.

| Normativos (sistema vigente) | Sin migrar (exploración) |
|---|---|
| `Colores de Marca PJP` · `Landing PJP Modos` · `Auditoría Landing PJP` · `Iconos PJP` · `Microanimaciones PJP` · `Precios PJP variantes` · `Zonas PJP variantes` · `Cabecera Seccion variantes` | `Logotipo variantes` · `Menu PJP` · `Hero PJP variantes` · `Info PJP variantes` · `Boton Reservar variantes` · `App PJP` · `Elementos Fachada` · `Marquesina Castillo` · `Salta la Ciudad` · `Tag Lorca` |

### 1.1 Lo que la tanda A dejó hecho, y por qué no basta

Hecho y funcionando: el tema **se inyecta desde BD** (`ThemeSettings::cssRootDeclarations()` en
`<style id="jj-theme">`), el acento de zona ya no viaja por el nombre de la clase, **el hueco del
paquete del cliente existe** (`public/css/client.css`, tres piezas aseveradas por
`ClientThemePackageTest`) y el dibujo del spinner es sustituible (`SpinnerTest`).

▶ **Lo que falta no son tokens: es que el producto no sabe hacer las cosas que el paquete necesita
pedirle.** Un cliente puede hoy cambiar el color de marca y la hoja entera le obedece; **no** puede
pedir una sección oscura, ni un segundo gris, ni su tipografía.

### 1.2 Los dos fondos de sección son un **MODO**, y no existe

El sistema del cliente alterna dos superficies —**tinta** `#101418` y **papel** `#F4F4F1`— con la
regla «nunca dos papeles seguidos», más una excepción de color pleno una vez por página.

⚠️ **Medido: en `public/css/*.css` no hay ni un mecanismo de superficie.** Un barrido de
`--dark`, `--invert`, `.dark` y `section--` devuelve **0**. La única superficie oscura del producto
es el hero, y se resuelve con un sufijo ad-hoc `--onvideo` —**7 reglas** en `landing.css` y **29
apariciones** entre CSS y Blade—: una clase por elemento, escrita a mano, que no es un mecanismo
sino la excepción de un caso.

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
| **1** | **Cimientos** — los dos fondos + `--sheet` + los dos grises + la escala de radios + las fuentes por instalación + el set de iconos | Solo tokens y CSS | Es lo que el armazón consume: hacerlo después obliga a rehacerlo. ▶ **Y su promesa es que NO mueve un píxel**, lo que la hace verificable de una sola forma y barata de revisar. |
| **2** | **El armazón** — hero, menú, hero footer y pie · **+ la escala de sombra** | Marcado + CSS del armazón | `[DECIDIDO owner]`: el producto adopta la **ESTRUCTURA**, neutra en valores. **Las secciones NO se tocan.** ⚠️ Aquí el producto **sí cambia de aspecto**: es la tanda que necesita ojo en navegador. |
| **3** | **Las secciones**, pieza a pieza desde `Elementos Fachada` | Sección por sección | ⚠️ Ese artboard está **sin migrar**: de ahí se saca la FORMA, nunca el color. |

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
