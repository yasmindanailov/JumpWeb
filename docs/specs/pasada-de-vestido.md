# [SPEC] La pasada de vestido — dónde se ubican los elementos de diseño

> **Estado**: 🟦 EN EJECUCIÓN · alcance, vara y familias DECIDIDOS (2026-09-12, §3.bis).
> Lo medido va en §3.bis y **caduca la D1 de §3**.
> **Decisión**: `DECISIONES #497` · **Carril**: diseño, Fase 2 (`specs/rediseno-desde-canvas.md`).
> **Depende de**: `elementos-fachada.md` (el kit, 32 piezas) · `hueco-ilustracion.md` (el mecanismo).

---

## 0. En una línea

El canvas **ya tenía previsto** este trabajo, lo llama **«la pasada de vestido»** y dice cómo se
hace: **iconos, imagen, movimiento y las piezas de fachada, sobre todas las secciones a la vez y con
presupuesto — no una a una.** Esta spec recoge lo medido, lo decidido y lo que falta por decidir.

---

## 1. ❗❗❗ El canvas ya decidió el CÓMO, y lo repite en siete artboards

No es una interpretación: son sus palabras, y coinciden entre sí.

| Artboard | Lo que dice |
|---|---|
| `Marco Portada PJP` | *«Lo siguiente es la pasada de vestido: **iconos, imagen y movimiento** sobre las ocho secciones **a la vez**»* |
| `Marco Portada PJP` | *«la pasada de vestido (…) **no una a una** · y **el censo pieza × pantalla del kit de fachada, que depende de la anterior**»* |
| `Escritorio PJP` | *«la pasada de vestido sobre las ocho secciones a la vez: **iconos, imagen y las piezas de fachada**»* |
| `Visitanos PJP` | *«la sección se queda sin ninguna pieza gráfica del kit: eso se decide entero en la pasada de vestido, sobre todas las secciones a la vez **y con presupuesto**, no metiendo una mancha aquí por rellenar»* |
| `Resenas PJP` | *«el resto entra en la pasada de vestido, que se hace **de una vez sobre todas las secciones**»* |
| `Sistema PJP` | *«Falta el escritorio y la pasada de vestido»* |

❗❗ **Esto explica algo que parecía una pérdida y era un aplazamiento.** Cada sección que el rediseño
rehízo fue perdiendo su decoración —`slot-tarifas` en `#479`, las dos de normas en `#485`,
`slot-zonas` en `#496`— y se leyó como limpieza. **No lo era: su colocación estaba aplazada a esta
pasada.** El canvas lo dice con todas las letras en `Visitanos PJP`.

▶ **Y la pasada son CUATRO ejes, no solo las manchas**: iconos · imagen · movimiento · piezas de
fachada. Quien la retome no puede reducirla al kit.

---

## 2. Lo medido (2026-09-10)

### 2.1 · Qué decoración hay HOY

Medido **en navegador y por COMPORTAMIENTO** —inerte + pinta una forma + ≥60 px, que descarta
iconos—, no por nombre de clase:

| Pieza | Dónde | Caja | Opacidad |
|---|---|---|---|
| `menu__blob--a` | menú | 520×520 | 0,17 |
| `menu__blob--b` | menú | 440×440 | 0,13 |
| `.grain` | menú | 1440×1000 | 0,20 |
| `.grain` | cierre | 1120×660 | 0,20 |
| `reserve__tag` | cierre | 114×69 | 1 |

▶ **En el cuerpo de las ocho secciones: CERO** (desde `#496`).

⚠️⚠️ **Trampa pagada**: la primera sonda buscaba por NOMBRE (`mancha`, `deco`, `splash`, `silueta`)
y **se dejó fuera `.menu__blob`**. *Buscar por nombre supone conocer los nombres.*

### 2.2 · Qué pide el canvas por artboard

    Portada PJP         mancha 0 · silueta 0 · trama 0 · friso 0    ← el entregable
    Zonas PJP           0 en todo
    Precios PJP         «cero superficies nuevas y cero manchas»
    Juegos PJP          «la foto de apertura es la mancha grande: aquí no entra ninguna otra»
    Visitanos PJP       «sin ninguna pieza gráfica del kit: se decide en la pasada de vestido»
    Escritorio PJP      la trama de puntos y el sello del CIERRE  ← lo único que sí coloca
    Elementos Fachada   mancha 39 · silueta 9 · trama 26 · friso 6 · pose 40  ← el REPERTORIO

⚠️ **«Mancha» tiene dos sentidos en su vocabulario** y hay que separarlos al leerlo: la pieza del kit
y *un área de color saturado* («la única mancha de color de la tarjeta es el Amarillo»). Los
recuentos altos de `Precios PJP` y `Visitanos PJP` son casi todos del segundo sentido.

### 2.3 · El presupuesto real

**El movimiento CUMPLE**, y esto corrige un dato que llegué a dar mal:

| Página | elementos con `infinite` | **corriendo** | techo del cliente |
|---|---|---|---|
| `/` | 28 | **2** | 2 ✅ |
| `/precios` | 11 | **1** | 2 ✅ |
| `/atracciones` | 3 | **1** | 2 ✅ |

⚠️⚠️ **24 de esos 28 están PAUSADOS**: los calcetines fuera de pantalla y el spinner con el cajón
cerrado — el mecanismo que `#435` construyó justo para esto. *Contar elementos con la propiedad
declarada no es contar bucles corriendo*, y sobre esa confusión se llegó a proponer «pagar una deuda
de movimiento» que **no existe**. `#279` y `#435` habían medido bien.

**El marcado sí está cargado**:

    HTML de GET /            275 KB
      · el logotipo en línea  112,9 KB   (41 %)   ← confirma la medición de #275
      · las 8 secciones        92,8 KB   (34 %)

❗ **El logotipo pesa MÁS que las ocho secciones juntas.** Ése es el marco del techo: el kit entra por
la misma puerta que ya consume el 41 % del documento.

---

## 3. ✅ Lo DECIDIDO por el owner (2026-09-10)

### D1 · **La pasada se hace AHORA, y solo sobre la PORTADA**

De las siete páginas solo `/atracciones` está rehecha desde el canvas; las otras seis cambian en la
Fase 3.

⚠️ **Es una desviación deliberada del «todas a la vez» del canvas**, y queda escrita para que no se
lea como un descuido: la portada está cerrada, es lo que más se ve, y las páginas heredarán el
criterio que esta pasada fije. ▶ **Coste asumido**: el reparto se decide sin ver seis de las siete
páginas, y puede haber una segunda pasada al llegar la Fase 3.

### D2 · **Techo de BYTES fijado antes de repartir, y `<use>` obligatorio**

Ninguna pieza se coloca hasta que haya un techo medido, y toda composición se monta con `<use>` sobre
una geometría única.

▶ La spec del kit ya lo midió: repetir el `<path>` cuesta **18,6×** más que `<defs>` + `<use>`.
⚠️ **Con dos trampas ya pagadas en `#266`**: una animación CSS sobre un elemento de `<defs>` **no
pinta nada** —lo que se dibuja son los `<use>`—, y **los `px` de un `transform` dentro de un SVG son
unidades del `viewBox`**, no píxeles.

---

## 3.bis · ✅ Lo DECIDIDO por el owner (2026-09-12) — y lo que MIDIÓ la sesión

> Esta sección va **antes** que §4 y §5: reencuadra el trabajo y **caduca la D1**.

### 3.bis.0 · El diagnóstico: la portada tiene **1,1 % de color**

Medido con `scripts/sonda-color.mjs` (muestreo con `elementFromPoint`, que resuelve la pila de
capas como la ve el navegador — un `background` declarado no dice qué se ve encima):

| | escritorio 1440 | móvil 390 |
|---|---|---|
| **color vivo** | **1,1 %** | 2,5 % |
| tinta | 16,3 % | 13,9 % |
| medio (vídeo + fotos) | 20,7 % | 18,1 % |
| papel | 61,9 % | 65,5 % |

⚠️ **Cinco de las diez zonas de la portada no tienen NI UN PÍXEL de color** —«Qué hay dentro»,
«Antes de venir», «Reseñas», «Visítanos» y «Dudas»— y las tres últimas son **100 % papel**: ni
color, ni foto, ni tinta. La página arranca con energía y se apaga entera desde la mitad.

⚠️⚠️ **Y las SEIS páginas interiores están a 0,0 %.** `/precios` —la que vende— es **98 % papel**
con **cuatro muestras** de color en toda la página.

### 3.bis.1 · ❗❗❗ El reparto está INVERTIDO respecto al sistema del propio cliente

`tokens-pjp.js` declara `limite.proporcion: '60/30/10'` (neutro / **cian identidad** / naranja
acción) y la **masa cromática medida en el mural** del parque. Contra los 643 puntos de color de
toda la portada:

| color | rol que le da el sistema | masa en el mural | ocupa hoy |
|---|---|---|---|
| Amarillo Aviso | *«resalte tipo marcador… nunca fondo ni botón»* | 3 % | **54,9 %** |
| Naranja Salto | acción, comprar | 36 % | 28,5 % |
| **Cian PJP** | **identidad** | **46 %** | **9,0 %** → 0,10 % de la página |
| Verde Salta | éxito | 13 % | 7,2 % |

▶ **El color más accesorio del sistema domina la portada, y el color que ES la identidad ocupa
una milésima** — 300× por debajo del 30 % que el propio sistema pide.

❗❗ **Consecuencia que cambia el encargo**: para meter color **no hay que romper ninguna regla del
canvas, hay que CUMPLIRLAS**. Las reglas duras siguen intactas y son las que acotan el cómo:
`fondosColor: 0` · *«el color nunca es fondo de sección; entra en tarjetas, datos y botones»* ·
naranja solo para comprar · magenta máx. 2 % · los cinco claros no pueden ser texto sobre papel.

### 3.bis.2 · El censo de candidatas

`scripts/censo-candidatos.mjs`, por COMPORTAMIENTO sobre las siete páginas: **407 piezas** de las
cuatro familias donde el sistema deja entrar color, **282 en neutro**. Las que se repiten en las
**siete**: `btn` (20) · `cta-med` y `cta-ghost` (8 cada uno) · `user-plus-ico` (8) · `nav__burger`
(7) · `foot__contact` y `foot__legal-wrap` (7). ▶ **El armazón entero está en neutro**, y es la
pieza que toca las siete páginas de una vez.

⚠️⚠️ **La primera versión del censo buscaba por NOMBRE de clase** (`.icon`, `[class*="card"]`) y
daba **22 iconos donde el set tiene 63**: aquí los iconos se emiten con clase semántica propia
(`arrow-ico`, `faq__sign-i`, `chev`). Es la trampa que §2.1 ya había pagado con `.menu__blob` —
*buscar por nombre supone conocer los nombres*— y se volvió a pagar.

### 3.bis.3 · Las tres decisiones

- ✅ **D1 CADUCA · la pasada se hace sobre LAS SIETE PÁGINAS** (`[DECIDIDO owner]`). El motivo que
  acotaba a la portada está escrito en §3 —*«de las siete solo `/atracciones` está rehecha»*— y
  **dejó de ser cierto el 2026-09-12** (`#536`): las siete están construidas. Vuelve el «todas a la
  vez» que el canvas repite en siete artboards.
- ✅ **D3+ · la vara es el `60/30/10` del sistema** (`[DECIDIDO owner]`): el cian sube a identidad
  visible en tarjetas, datos, iconos y subrayados, **sin tocar `fondosColor: 0`**. El fondo de
  sección sigue siendo papel y el naranja sigue significando solo comprar.
- ✅ **D4 · entra el kit por el hueco por instalación Y los 19 iconos de zona** (`[DECIDIDO owner]`),
  que cierra la deuda que `#257` dejó escrita.

### 3.bis.4 · Un hallazgo lateral: **`/entradas` no es una página**

La sonda dio «100 % papel» con todas sus filas a 0,0 — una incoherencia interna que delató el
defecto del instrumento *y* el hecho: **`/entradas` sirve la portada y abre el CAJÓN encima**
(capa `fixed` que tapa el 100 %). Es una PUERTA, como las `/mi-cuenta/…` de `#120`, no una página
de contenido. ▶ Eso contesta la pregunta abierta del `ESTADO` («qué pasa con `/entradas`, que
existe y no está en el inventario del canvas»): **no es de este carril, es del cajón**.

---

## 4. ❗ Lo que FALTA por decidir

### D3 · La regla de DENSIDAD: ¿cuántas piezas por pantalla?

Hoy rige la heredada de `#292` —*«una pieza de dibujo por sección, tres en toda la portada»*—, pero
**es del carril anterior** y nadie la ha contrastado con el canvas.

▶ Lo que el canvas sí dice, y confirman dos fuentes suyas: *«nada de esto pinta el fondo de una
sección: el fondo es papel y **el material vive dentro de las tarjetas**»*.

### D4 · Qué FAMILIAS entran, de las 32 piezas

Se parten por una línea que ya existe (`elementos-fachada.md` §5) y **no es estética, es
white-label**:

| Familia | Naturaleza | Puerta |
|---|---|---|
| Tramas · tiras · cuñas · frisos | **mecanismo** | el producto, directamente |
| 6 manchas + 12 poses | **arte de PlayJump** | solo por el hueco por instalación (`#286`) |
| Iconos de zona `F10`/`G5` | los 19 dibujos que faltan | cierran la deuda de `#257` |

⚠️ Meter manchas o poses fuera del hueco **clava el mural de este parque dentro de JumpWeb**, que es
lo que `DECISIONES #1` y `INSTALACION-CLIENTE.md` §4 existen para impedir.

### D5 · Las contradicciones del artboard que siguen abiertas

De `elementos-fachada.md` §8, quedan dos sin resolver:

1. **La regla 05** dice «una pose no se repite dos veces en la misma pantalla» y **su propia `F8` la
   rompe** (14 copias, 9 poses, 5 repetidas). ¿Vale la regla también para los fondos decorativos, o
   solo para las figuras con peso?
2. **La regla 02** («la pintura nunca va debajo de un párrafo») y **`A5`** («niebla en formularios
   largos») se rozan: ¿una niebla difusa cuenta como «pintura»?

### D6 · `C3`, la cinta del eslogan

Choca con `#252`, que la retiró de la portada por espacio. ¿Vuelve, y a costa de qué?

### D7 · Refrescar el canvas

La copia local es del **9–10 sep** y **le falta `Layout Paginas PJP`**, el armazón de páginas que el
`ESTADO` da por aprobado. No bloquea la D1 —la portada sí está completa— pero **sí bloquea la Fase 3**.

---

## 5. Plan, cuando D3 y D4 estén decididas

1. **Medir la base**: el HTML de las ocho secciones hoy (hecho: 92,8 KB) y fijar el techo del kit.
2. **Los cuatro ejes, en este orden**: iconos → imagen → piezas de fachada → movimiento. El
   movimiento va el último porque es lo único que ya cumple y no conviene tocarlo a ciegas.
3. **El censo pieza × pantalla**, que el propio canvas pone después de la pasada.
4. **Guarda de presupuesto**: que el techo de bytes no se pueda pasar sin decidirlo.

⚠️ **Lo que no se hace**: colocar una pieza «para rellenar» una sección. El canvas lo prohíbe con esas
palabras y es la razón de que este documento exista.
