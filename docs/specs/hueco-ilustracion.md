# [SPEC] El hueco de ILUSTRACIÓN por instalación

> Estado: 🟦 **EL MECANISMO ESTÁ EN EL ÁRBOL Y TIENE SU PRIMER CONSUMIDOR — U1 a U6 (2026-08-31).**
> ▶ La **tarjeta de zona** de la portada pide su dibujo con `zone-<slug>` (`[DECIDIDO owner]`), y es
> la que se eligió porque **resuelve sola**: `zones.slug` ya existe, así que no hizo falta declarar
> ninguna ranura. Con ella se cierra la deuda de `#257`.
> ▶ Verificado: **45 casos** entre `IllustrationKitTest` y `IllustrationHoleRenderTest` ·
> **24/24 mutaciones muerden** · los tres tratamientos, la fuga de `currentColor` y **el dibujo
> pintando en el marcado real** medidos en navegador con control · suite **3581 / 23.320** · Pint y
> `docs-check` verdes.
> ▶ **Las 12 poses siguen fuera** y no es un descuido: §14.2.
> ❗ **Y §14.3 es lo que hay que leer antes de medir nada de esto**: comparar capturas de la portada
> **no puede demostrar nada** —su suelo de ruido es del **64,45 %**— y la primera medición dio una
> conclusión falsa con un número real.
> ⏸️ Sigue 🟦 por el OJO del owner: falta ver la tarjeta de zona con un dibujo de verdad dentro.
>
> ❗❗ **`#287` corrige DOS cosas de este documento y van antes que el cuerpo — §16.**
> **(1) El componente emitía un `<svg>` VACÍO cuando el kit no traía ese dibujo**, así que la promesa
> de §7 —«no queda caja vacía»— era falsa en el caso NORMAL: medido en la portada, las zonas `cap` y
> `cap2` metían **dos cajas de 190×150**. El `@if` cubría «no hay kit», no «hay kit y falta la clave».
> **(2) Las clases de tratamiento se componían con `'ilu--'.$trato`**, y una clase armada por
> concatenación **es invisible para cualquier inventario de CSS**: `.ilu--plano` y `.ilu--contorno`
> salían huérfanas con el producto sano. Se escriben enteras.
> Última actualización: 2026-08-31 (`#287`) · Decisión asociada: **`DECISIONES #286` y `#287`**.
> ⚠️ Su número se elige **mirando el REMOTO**, no el local: ya colisionó una vez con otro agente.
> Origen: `elementos-fachada.md` §10·1 (`[DECIDIDO owner, 2026-08-30]`: «se construye el hueco de
> ilustración por instalación»), que además cierra la deuda de `#257`.
>
> ❗ **Este documento CORRIGE tres afirmaciones de `elementos-fachada.md`.** Van en §12 y hay que
> leerlas antes que aquel texto.
>
> ⚠️⚠️ **Método**: todo número de aquí está medido en **Chrome 151 headless real, con un CONTROL que
> debe salir distinto**. Las sondas viven en el scratchpad de la sesión. Donde algo NO está medido, lo
> dice. Este carril ha pagado cinco veces en una jornada que una sonda estática da cifras creíbles y
> falsas.

---

## 1. El mecanismo en una frase

**La instalación entrega UN fichero de dibujos (`client-kit.svg`, un sprite de `<symbol>`) y el
producto los pinta con `<use href="/img/client-kit.svg#nombre">`, poniendo él el color, el tamaño y
el tratamiento; sin ese fichero no se pinta nada.**

Es el **tercer** hueco por instalación —tras `client-logo.svg` (`#254`) y `client-favicon.svg`— y el
**primero para ILUSTRACIÓN y no para marca**.

---

## 2. Por qué `<use>` externo, y no cualquiera de los otros cuatro

Medido en Chrome 151, con control en cada celda:

| vehículo | ¿ejecuta el script del cliente? | ¿llama a casa? | ¿recolorea? | ¿los 3 tratamientos? |
|---|---|---|---|---|
| **en línea** (`InlineSvg`) | **SÍ** | **SÍ**, por dos vías | sí | sí |
| `<img>` | no | no | **no** | no |
| `background-image` | no | no | **no** | no |
| `mask-image` | no | no | sí (por alfa) | **2 de 3 — sin contorno** |
| **`<use>` externo** | **no** | **no** | **sí, por instancia** | **sí** |

▶ **`<use>` externo es el único que junta las dos cosas que este hueco necesita**: es **inerte** y da
**los tres tratamientos desde una sola geometría**.

### 2.1 · Lo que sostiene cada casilla

- **Funciona bajo la CSP REAL de producción** (`default-src 'self'; … img-src 'self' data:`,
  la declara `SecurityHeaders` en `app/Http/Middleware/SecurityHeaders.php`). Servido por el propio Laravel: `<use
  href="/_probe_kit.svg#blob-1" fill="#1AA9DE">` → centro `#1AA9DE`, cobertura 21,2 %.
  **Controles que salen distintos**: `href` a un fichero inexistente → blanco; cross-origin → blanco.
- **Cruzan las tres vías de color del producto** —atributo `fill`, `currentColor` y `var(--x)`
  declarada en el documento— **y cada instancia puede llevar un valor distinto** (dos `<use>` al
  MISMO símbolo salieron `#1AA9DE` y `#FF00FF`).
- **Los tres tratamientos desde un `<path>`**, medidos por cobertura de tinta: plano **18,9 %** ·
  contorno **8,8 %** (`fill:none` + `stroke`, `viewBox` ampliado) · troquel **78,8 %** (el inverso
  exacto del plano). Con la silueta real: 33,3 / 6,7 / 62,8 %. **Control vacío: 0,0 %.**
- **Es inerte**: ni `<script>`, ni `onload`, ni `<image href="http://…">`, ni un `new Image()` del
  script del cliente salieron. **Control que sí sale**: el mismo SVG **en línea** ejecutó y disparó
  **las dos** balizas, y la página pidió su propia baliza de control. *Sin ese control, «no salió
  ninguna petición» no distingue «está bloqueado» de «mi sonda no mira».*
- **Una sola descarga** por muchos `<use>` que lo referencien, y también con fragmentos distintos
  (`performance.getEntriesByType('resource')`: 4 `<use>` a tres `id` → **1 entrada**).

### 2.2 · ⚠️ Los cuatro límites de este vehículo, todos medidos

1. **El `<style>` interno del cliente GANA al `fill` del producto.** Un símbolo con
   `#sa{animation:…}` a `#00FF00` sale verde aunque el `<use>` diga `fill="#1AA9DE"`.
   ▶ **No es un agujero de seguridad, es una FUGA WHITE-LABEL** —el vehículo es inerte para el
   código— y por eso se cierra **al instalar**, no al servir (§5).
2. **El `stroke-width` propio del símbolo GANA al del `<use>`**: celda con símbolo a
   `stroke-width=8` + `<use stroke-width=20>` dio **21,4 % / 26 px**, idéntico al control sin
   normalizar, frente al **46,4 % / 48 px** del control normalizado.
3. **Un `fill` clavado en la geometría hace imposible recolorearla**, por cualquier vehículo: el
   atributo del original gana al heredado. ⚠️ **Los ficheros sueltos del canvas lo traen clavado
   (7 de 7 con `fill="#000"`) y el artboard NO (1 de 138, y vale `none`).** De ahí sale la regla de
   extracción de §9.
4. **No existe al `DOMContentLoaded`**: `getBBox()` del mismo `<use>` da `0x0` en DCL y `381x668` en
   `load`. La descarga midió 240–313 ms en loopback. Hay hueco de primer pintado que el SVG en
   línea no tiene.

### 2.3 · ❗ Un límite que NO está medido y no puedo cerrar aquí

**Solo hay Chrome en esta máquina.** `<use>` externo **no está medido en WebKit ni en Firefox**.

▶ Consecuencia de diseño, y es la razón de §3.2: **no se toca lo que hoy funciona.** Las dos manchas
del menú van por `mask-image`, que está soportado en todos los motores, y moverlas a `<use>` sin
medir los otros dos sería cambiar una pieza sana por una sin verificar.

---

## 3. Qué aporta la instalación y qué pone el producto

### 3.1 · La línea

| lo pone… | qué |
|---|---|
| **la instalación** | la **GEOMETRÍA**: un `<symbol>` por dibujo, sin color, sin grosor, sin estilo |
| **el producto** | **dónde** va, **cuánto** mide, **de qué color** (de sus tokens), **qué tratamiento** lleva y **qué pasa si falta** |

Es la misma línea que `--deco-blob-*` (`INSTALACION-CLIENTE.md` §4.d) y por el mismo motivo: este
repo es el PRODUCTO y no lleva el arte de ningún cliente.

### 3.2 · ⚠️ Lo que este hueco NO sustituye

**`--deco-blob-a/b` y `--deco-tag` se quedan exactamente como están.** No es una excepción
avergonzada, es **la segunda mitad de la regla**, y conviene escribirla para que la próxima pieza no
entre por el sitio equivocado:

| si el dibujo es… | entra por | ejemplo vivo |
|---|---|---|
| **FORMA de un color**, que el producto recolorea | `client-kit.svg` + `<use>` | las 12 poses |
| **FORMA de un color** en un hueco fijo ya resuelto | `mask-image` desde `client.css` | las 2 manchas del menú |
| **IMAGEN a color propio** | `background-image` a fichero | el tag de la ciudad (199.561 B, 19 colores) |

Motivos, medidos: una mancha **nunca lleva contorno**, su hueco ya funciona, y la máscara está
soportada en todos los motores (§2.3). Cambiarlas no gana nada y arriesga.

---

## 4. El contrato del paquete

### 4.1 · El fichero

```
public/img/client-kit.svg      ← lo entrega la instalación. NO se versiona.
```

```xml
<svg xmlns="http://www.w3.org/2000/svg">
  <symbol id="pose-1" viewBox="18 8 396 684"><title>Salto con puños</title>
    <path d="…"/>
  </symbol>
  <symbol id="zone-foam-pit" viewBox="0 0 64 64"><title>Foam pit</title>
    <path d="…"/>
  </symbol>
</svg>
```

**Tres reglas duras**, las mismas que ya se le piden al logotipo (`INSTALACION-CLIENTE.md`
§4.a.quinquies) más las dos que salen de la medición:

1. **Texto en contornos**, nada externo, `viewBox` obligatorio en cada `<symbol>`.
2. **Ni un atributo de presentación**: sin `fill`, sin `stroke`, sin `stroke-width`, sin `style=` —
   ni en el `<symbol>` ni en ningún descendiente. *Miden que ganan al producto (§2.2·1 y ·2).*
   ▶ **Sí se admiten `fill-rule` y `clip-rule`**: no son color, son geometría (una figura con hueco
   los necesita), y no compiten con nada que ponga el producto.
   ▶ ⚠️ **Y el grosor pretendido viaja como `data-stroke`, NO como `stroke-width`.** Es la única
   forma de que el dato del diseñador llegue sin ganarle al producto: un `stroke-width` vivo se
   impone al del `<use>` **en silencio** (§2.2·2), y un `data-*` es inerte. §6.2 lo aplica.
3. **Ni un `<style>`, `<script>`, `on*=`, `javascript:` ni referencia externa.**

### 4.2 · Los nombres los declara el PRODUCTO, no el paquete

`[DECIDIDO owner, 2026-08-31]`: **el paquete mapea por NOMBRE, sin panel y sin migración.**

▶ Y por eso la gramática tiene que ser **cerrada**: con sufijos libres **ninguna guarda de paridad
puede existir** y una clave retirada por el diseñador en su próximo export degradaría en silencio —
que es literalmente lo que `ProductIcon::CHOICES` (`app/Domain/Booking/Services/ProductIcon.php`) ya dejó
escrito: *«retirar una clave degradaría en silencio todo producto que la tenga guardada»*.

| familia | clave | de dónde sale |
|---|---|---|
| ranuras decorativas | `slot-<nombre>` | **el producto** declara la lista (`IlustracionSlot`) |
| marcador de zona | `zone-<slug>` | la columna `zones.slug`, que **ya existe** |

⚠️ **`attractions` y `park_rules` NO tienen `slug`** (medido: solo `id`, `name`, `position`…). Sin
identificador estable **no entran en esta versión**: derivar la clave de `name` haría que renombrar
una atracción le cambiara el dibujo en silencio. Ficha en `DEUDA`.

### 4.3 · Las tres piezas del despliegue

Como `client.css` (`#143`) y el logotipo (`#206`) — **y con dos parece que funciona**:

1. `.gitignore` → `/public/img/client-kit.svg`
2. `deploy.sh` → `--exclude='/public/img/client-kit.svg'` (sin ella el primer `rsync --delete` se
   lo lleva y la web se queda sin dibujos, **en silencio**)
3. Se carga **si existe**, con `@filemtime` haciendo de existencia y de cache-busting a la vez.

---

## 5. El saneado: `php artisan kit:build`

Toma el fichero que entrega el diseñador y escribe el que sirve el producto.

| qué hace | por qué, medido |
|---|---|
| **RECHAZA** el fichero entero si trae `<style>`, `<script>`, `on*=`, `javascript:` o referencia externa | todo-o-nada, la política que `#254` ya eligió; degrada hacia lo invisible |
| **RECHAZA** un `<symbol>` con `fill`, `stroke`, `stroke-width` o `style=` propios | §2.2·1 y ·2: ganan al producto y la fuga es **silenciosa** |
| **RECHAZA** un `<symbol>` sin `viewBox` o sin `<title>` | sin `viewBox` no hay tratamiento contorno ni troquel |
| **RECHAZA** una clave fuera de la gramática de §4.2 | sin gramática cerrada no hay guarda posible |
| **EXIGE** que cada `zone-<slug>` corresponda a una zona viva | una clave huérfana es un dibujo que nadie verá |

⚠️⚠️ **`kit:build` no basta y hay que decirlo**: si alguien copia un sprite a mano en el servidor,
nada lo revisa. Por eso el saneado se repite **al desplegar**, como GUARDA nombrada dentro del bloque
`check` de `scripts/deploy.sh:519-570` — el único instrumento del proyecto que ve el disco real.

---

## 6. Cómo se pide un dibujo

### 6.1 · Desde Blade

```blade
{{-- resources/views/components/site/ilu.blade.php --}}
{{-- **EL HUECO DE ILUSTRACIÓN** — el dibujo lo trae la instalación en `client-kit.svg`;
     aquí solo se decide cuál, de qué tamaño y con qué tratamiento.
     ⚠️ `@filemtime` hace las DOS cosas en una sola llamada a disco —existencia y cache-busting—:
     devuelve `false` si no está, así que no hace falta un `file_exists` aparte. Mismo recurso
     que `client.css` y que el logotipo.
     ⚠️⚠️ El `fill` va en el `<use>` y NO se hereda: medido, un símbolo con `fill="currentColor"`
     propio hace que el troquel pinte el 100 % de la caja. --}}
@props(['clave', 'trato' => 'plano'])

@php($kit = @filemtime(public_path('img/client-kit.svg')))

@if ($kit)
    <svg class="ilu ilu--{{ $trato }}" aria-hidden="true" focusable="false">
        <use href="{{ asset('img/client-kit.svg') }}?v={{ $kit }}#{{ $clave }}" />
    </svg>
@endif
```

```blade
<x-site.ilu clave="zone-{{ $zone->slug }}" />
<x-site.ilu clave="slot-vacio-sin-franjas" trato="contorno" />
```

### 6.2 · Desde CSS — el color y el grosor son del PRODUCTO

```css
.ilu { width: 100%; height: auto; }
.ilu--plano use    { fill: var(--paper-fg); }
.ilu--contorno use { fill: none; stroke: var(--paper-fg); }
```

⚠️ **Leen `--paper-fg` y no `--fg`**, por lo mismo que las sombras de `#196`: dentro del hero `--fg`
vale CLARO.

⚠️ **El grosor del contorno lo declara el SÍMBOLO, validado contra su `viewBox`** — no una constante
del producto. Medido: la rejilla del artboard tiene piezas a `stroke-width` 8 **y** 12, y una pose de
`viewBox` 396 necesita 4,1 donde una constante derivada daría **49,5**. Una constante única es falsa.

⚠️⚠️ **Pero llega como `data-stroke`, no como `stroke-width`** (§4.1·2), y esos son dos mundos: un
`stroke-width` vivo dentro del símbolo **gana al del `<use>` en silencio** y deja inerte al producto;
un `data-*` no pinta nada por sí solo. El producto lo lee y lo aplica **donde sí manda**:

```blade
<use href="…#{{ $clave }}"
     @if ($grosor) stroke-width="{{ $grosor }}" @endif />
```

▶ Si el símbolo no declara `data-stroke`, el contorno usa el grosor del CSS. **Vacío es una
respuesta, no una falta** — el mismo criterio que `--action-brand` en `#209`.

---

## 7. Qué se ve sin paquete

`[DECIDIDO owner, 2026-08-31]`: **nada.** El producto **no dibuja un juego genérico propio.**

▶ Y es la decisión segura, no la perezosa: un suelo de ilustraciones sería **arte versionado del
producto**, que es exactamente por donde `#257` metió 43 dibujos del artboard de un cliente en el set
del producto, y por donde `public/favicon.svg` conserva `#FF5B22`, el naranja del **primer** cliente
(1 ocurrencia, confesada en `.gitignore:49-51`).

⚠️⚠️ **El modo de fallo se elige y es la INVISIBILIDAD**, no un hueco roto: el componente no emite
ni el `<svg>`, así que no queda caja vacía ni rectángulo de color.

❗ **Y son DOS preguntas, no una** (`#287`, §16.1): *¿hay kit?* y *¿trae ESTE dibujo?*. Con solo la
primera, una instalación con más zonas que dibujos metía un `<svg>` sin `viewBox` —o sea **150 px de
caja vacía**— por cada clave ausente. **Ese caso es el normal**: `kit:build` no exige un dibujo por
zona, y hace bien. Es la misma política que la
máscara transparente de `site.css:5223`, cuyo comentario ya avisa de que *un valor por defecto que
falla hacia «visible» es peor que no tener valor por defecto*.

---

## 8. Los tres tratamientos, desde una geometría

| tratamiento | cómo | ⚠️ |
|---|---|---|
| **plano** | `fill: var(--paper-fg)` sobre el `<use>` | — |
| **contorno** | `fill:none` + `stroke`, `viewBox` ampliado por el propio símbolo | el grosor lo declara el símbolo (§6.2) |
| **troquel** | `<mask>` con rect blanco + el MISMO `<use>` en negro | **el negro va EXPLÍCITO en el `<use>`** |

### 8.1 · ⚠️⚠️ La fuga de `currentColor` afecta a LOS TRES, no solo al troquel

Medido en Chrome sobre el CSS real, con un símbolo hostil que declara `fill="currentColor"` —que es
**como exporta el artboard**— y con el `color` del documento en magenta, para que la fuga no se pueda
confundir con nada:

| tratamiento | símbolo limpio | hostil **sin** defensa | hostil **con** defensa |
|---|---|---|---|
| plano | 35,6 % | 35,6 % · **35,6 % de píxeles ajenos** | 35,6 % · 0,0 % ✓ |
| contorno | **4,1 %** | **37,9 %** — *sale MACIZO* | **4,1 %** ✓ |
| troquel | 64,4 % | **100,0 % de la caja** | 64,4 % ✓ |

**Controles de la misma pasada**: celda vacía **0,0 %** · plano + troquel = **100,0 %** exactos, o sea
complementarios. Los tres vuelven al valor **idéntico** del símbolo limpio.

▶ **La defensa es fijar `color` en el propio `<use>`**, para que un `fill="currentColor"` aterrice
donde el producto quiere en vez de donde caiga: `var(--paper-fg)` en plano, **`transparent`** en
contorno —un relleno invisible que deja el trazo al CSS— y `#000` dentro de la máscara.

▶ **Y hacen falta DOS defensas, no una**: `kit:build` rechaza el `fill` propio (§5) **y** el marcado
no depende de heredar. Con solo la primera, un sprite copiado a mano en el servidor pinta el
rectángulo — y ése es justo el caso que `kit:build` no puede ver.

⚠️⚠️ **Y una trampa de instrumento que costó una medición entera**: la primera pasada dio el troquel
por BUENO. `color` valía la tinta oscura del producto, que como máscara se lee **casi igual que el
negro**. *La sonda no estaba probando el defecto: probaba un caso donde el defecto no se nota.* Si
vuelves a medir esto, el `color` del documento tiene que ser uno que no se parezca ni al negro ni a
la tinta.

---

## 9. La regla de extracción

**El material se saca del ARTBOARD, no de los ficheros sueltos del canvas.** Medido:

| origen | `fill` clavado | ¿recolorea? |
|---|---|---|
| `Elementos Fachada.dc.html` | **1 de 138** paths, y vale `none` | **sí** |
| `mockup_playjumppark/assets/*.svg` | **7 de 7** con `fill="#000"` | **no** |

⚠️ Para las manchas de hoy da igual (van por máscara, que solo lee el alfa). Para todo lo que entre
por `<use>` es la diferencia entre funcionar y no.

▶ Y las composiciones se montan con `<use>`, **nunca repitiendo el `<path>`**: medido sobre el
artboard nuevo, `F1` aporta **9 geometrías** y `G1` otras **3**, y `F2`–`F11` **casi no añaden
ninguna** — son la misma pose recompuesta. `F8` tiene **14 paths y 9 distintas**.

---

## 10. Coste, medido

| concepto | cifra |
|---|---|
| bytes de **HTML** | **~180 B por instancia** (plano 181 · contorno 176 · troquel 327), contra los **3.840 B** de una figura en línea |
| el fichero | **~61 KB crudos** para 37 dibujos; **una sola descarga**, compartida por todas las instancias |
| `client.css` | **no engorda**: la geometría no entra en la hoja (un `data:` URI cuesta **+34,5 %** frente al fichero) |

⚠️⚠️ **La ventaja de caché de este diseño HOY NO EXISTE, y es un hallazgo del producto, no de la
spec.** Medido: `curl -sI /img/client-tag.svg` devuelve `Content-Type` y `Content-Length` **y nada
más** — sin `Cache-Control`, sin `ETag`, sin `Last-Modified`, sin compresión. `public/.htaccess` son
**740 B con 0 directivas** de caché o compresión. Mientras eso siga así, «una sola descarga
cacheable» es una suposición sobre el hosting. **Ficha propia en `DEUDA`: afecta a todo el sitio, no
a esto.**

⚠️ **Y una corrección de método que vale para todo el carril**: el «18,6× menos» que
`elementos-fachada.md` §6 atribuye a `<use>` es una cifra **CRUDA**. Comprimido son **1,4×** (90.925
→ 2.506 B repitiendo el path; 4.849 → 1.856 B con `<use>`). La ganancia real de `<use>` **no es la
red**: es el tamaño de DOM, el parseo, y poder recolorear cada instancia.

---

## 11. La guarda, y las mutaciones que tiene que sobrevivir

`IluminacionHuecoTest` — y **no se sabe si sirve hasta que se muta con el fallo real que la motivó**.

| # | asevera | mutación que DEBE ponerla roja |
|---|---|---|
| 1 | `.gitignore` contiene `client-kit.svg` | quitar la línea |
| 2 | `deploy.sh` lo excluye del `--delete` | quitar el `--exclude` |
| 3 | sin fichero, el componente **no emite `<svg>`** | cambiar el `@if` por un `@else` que pinte una caja |
| 4 | `kit:build` **rechaza** un `<symbol>` con `fill` propio | aceptar `fill` |
| 5 | `kit:build` **rechaza** un `stroke-width` propio | aseverar solo `fill` **(éste es el que nació ciego en la propuesta original: sus 12 mutaciones salían verdes con el defecto puesto)** |
| 6 | `kit:build` rechaza `fill` en un **DESCENDIENTE**, no solo en la raíz | mover el `fill` a un `<path>` interior |
| 7 | `kit:build` rechaza `<style>` interno | aceptarlo |
| 8 | el troquel pinta **< 60 %** de su caja con un símbolo que declara `fill="currentColor"` | quitar el negro explícito del `<use>` y dejar que herede |
| 9 | el grosor del contorno sale del **símbolo**, con un caso por cada rejilla (24, 64 **y las poses**) | fijar una constante |
| 10 | toda clave `zone-*` corresponde a una zona viva | dejar una huérfana |
| 11 | toda clave está en la gramática cerrada de §4.2 | admitir un sufijo libre |
| 12 | el CSS lee `--paper-fg` **resolviendo la cadena de `var()`** | renombrar el token |

⚠️⚠️ **El caso 9 es el que este proyecto falla siempre**: la guarda de la propuesta original aseveraba
las dos rejillas que su autor había medido **y nunca las poses** — *vigilar el reposo en vez del
disparador*. Y **los casos 5 y 6** son el mismo defecto en dos alturas: aseverar `fill` y creer que
cubre la presentación entera.

### 11.1 · Lo que la implementación añadió a esta lista

Salieron de escribir el código y de medir, no de leer la tabla:

| # | asevera | mutación que la pone roja |
|---|---|---|
| 13 | los **tres** tratamientos fijan `color`, no solo el troquel | quitar `color` de `.ilu--plano use` o de `.ilu--contorno use` (§8.1) |
| 14 | el fondo de la máscara fija su blanco | quitarle el `style="fill:#fff"` |
| 15 | el `id` de la máscara **se deriva de la clave** | cambiarlo por `Str::random(8)` |
| 16 | sin `data-stroke` el componente **no escribe grosor** | escribir uno por defecto — sería una constante disfrazada, y pisaría al CSS |
| 17 | el grosor solo se lee para el CONTORNO | leerlo también en plano o troquel, donde no pinta nada |
| 18 | un tratamiento desconocido **falla a la cara** | caer en `plano` por defecto, que lo escondería |

⚠️ **El 18 se asevera por MENSAJE y no por clase**: Blade envuelve lo que lance una plantilla en su
`ViewException`, así que fijar `InvalidArgumentException` pone el caso rojo con la conducta correcta.

---

## 12. ❗ Contradicciones con `docs/specs/elementos-fachada.md`

Van aquí porque **la corrección se lee antes que el texto que corrige**.

1. **§10·1 dice que el hueco «no está diseñado». Ya existía, dos veces.** `--deco-blob-a/b` (máscara
   recoloreable) y `--deco-tag` (imagen a color), documentados en `INSTALACION-CLIENTE.md` §4.d y
   §4.e desde `#228`. Lo que faltaba era **generalizarlo**, no inventarlo.
2. **§4 clasifica `B1` como 🟥 arte del cliente ausente. Dos de las seis manchas YA VIAJAN
   instaladas.** Verificado byte a byte: `--deco-blob-a` **es** `splash-1.svg` (1.304 B) y
   `--deco-blob-b` **es** `splash-4.svg` (1.493 B), mismo sha1 normalizado y mismo `viewBox`. Este
   artboard ya alimentó al producto una vez.
3. **§6 y §9 temen un coste de bytes que el vehículo real no tiene.** El «18,6× menos» es crudo
   (**1,4× comprimido**) y los «64 KB del logotipo» son 57.780 B crudos pero **12.955 gzip**. El
   marcado por instancia son **~180 B**, no 3.840.
4. **`#257` dice que los 19 dibujos de parque «no tienen hoy ninguna pantalla que las pinte».**
   Verificado en su propia entrada, que lo escribe así: *«son del cliente y además no tienen hoy
   ninguna pantalla que las pinte»*. O sea que el marcador de zona **no desbloquea nada
   roto**: habilita pantallas futuras. La demanda **con consumidor hoy** son las poses y las manchas.
5. **§7 dice que los estados vacíos «hoy no tienen ninguno». Existen, y se pintan.** Medido:
   `tickets.no_dates` en `DateStep.vue` (con `v-if="! strip.length"`), `CatalogStep.vue`
   (`v-if="! hasItems"`), y en `lang/es/account.php` los de «Mis pedidos» (*«Todavía no tienes ningún
   pedido»*), «Mis reservas», el historial y los menores. Lo que un dibujo añadiría es **decoración
   de un estado que ya funciona**, no tapar un agujero — y de ahí sale la decisión de §15·3.
   ⚠️ **Y al mirarlo apareció un defecto que no se buscaba**: `CatalogStep` usa `no_dates` —*«no hay
   días disponibles»*— cuando **el día ya está elegido** y lo que falta son entradas. Ficha en `DEUDA`.

---

## 13. Lo que este mecanismo NO cubre (`DEUDA`)

1. **`attractions` (23) y `park_rules` (5) no tienen `slug`** → no pueden tener marcador sin una
   migración. Fuera de esta versión (§4.2).
2. **`<use>` externo sin medir en WebKit ni Firefox** (§2.3). Bloquea cualquier propuesta futura de
   mover las manchas del menú a este vehículo.
3. **El servidor no manda ni una cabecera de caché** (§10). Afecta a todo el sitio.
4. **`site.css:2421` escribe `aspect-ratio: 377 / 197`**, que es **literalmente el `viewBox` de
   `client-tag.svg`** (verificado: `0 0 377 197`). Es geometría del 2.º cliente dentro del CSS
   versionado del producto. Se cierra con un token del paquete (`--deco-tag-ratio`, defecto `1/1`).
5. **`public/favicon.svg` conserva `#FF5B22`**, el naranja del primer cliente, y `public/images`
   lleva **41 ficheros / 7,1 MB** de fotos suyas versionadas.
6. **El hueco de primer pintado** del `<use>` externo (§2.2·4) no está medido en red lenta.

---

## 14. Plan por unidades

| # | unidad | criterio de aceptación | estado |
|---|---|---|---|
| **U1** | El contrato y las tres piezas de despliegue | `.gitignore` + `deploy.sh` + casos 1 y 2 de §11 en verde, y **vistos fallar** al quitar cada línea | ✅ **hecha** |
| **U2** | `php artisan kit:build` con los cinco rechazos de §5 | casos 4–7 y 11, **cada uno con su mutación mordiendo** | ✅ **hecha** |
| **U3** | `<x-site.ilu>` y el CSS de los tres tratamientos | casos 3, 8, 9 y 12. El **8 se mide en píxeles, no en marcado** | ✅ **hecha** |
| **U4** | La gramática cerrada y el mapeo `zone-<slug>` | caso 10, con una zona huérfana de verdad | ✅ **absorbida en U2** (`IllustrationKit::keyProblems`) |
| **U5** | La guarda en `deploy.sh` | ve el disco real y no rompe el gate en una máquina sin paquete | ✅ **hecha** — `GUARDA 7`, con `kit:build --check` |
| **U6** | El PRIMER consumidor: la ilustración de la tarjeta de zona | el dibujo **pinta** en la página real, medido con instrumento estable, y sin kit la tarjeta queda idéntica | ✅ **hecha** (`[DECIDIDO owner, 2026-08-31]`) |
| **U7** | Sacar las 12 poses del artboard con `<use>` (§9) | 12 símbolos, **cero** atributos de presentación, `kit:build` los acepta | ⛔ **BLOQUEADA — ver §14.2** |

### 14.1.bis · ✅ U8 · el saneo de `#287`

| # | unidad | criterio de aceptación | estado |
|---|---|---|---|
| **U8** | Los dos defectos de §16 | `has()` con su caso y sus tres mutaciones · las tres clases de tratamiento escritas enteras · `FacadeCssHasNoOrphansTest` en verde con la lista de excepciones **vacía** | ✅ **hecha** (`#287`) |

### 14.2 · ⛔ Las 12 POSES siguen bloqueadas, y el bloqueo es una CONSECUENCIA, no un descuido

`[DECIDIDO owner, 2026-08-31]`: el primer consumidor es **la tarjeta de zona**, y por eso U6 se pudo
hacer: su clave es `zone-<slug>`, que **resuelve sola** porque `zones.slug` ya existe. No hizo falta
declarar ninguna ranura.

▶ **Las 12 poses no**: serían ranuras decorativas, y la lista de ranuras **nace vacía** (`§15·3`),
así que `kit:build` **rechaza toda clave `slot-*`** — correctamente, y hay caso que lo asevera.
Sacarlas ahora produciría arte que no puede pedir nadie, que es lo que `#257` hizo con sus 19 dibujos.

▶ **Lo que las desbloquea es elegir su pantalla**, igual que se eligió la de la tarjeta de zona: la
ranura y su consumidor entran en el mismo cambio.

### 14.3 · ⚠️⚠️ Lo que enseñó medir en la PÁGINA REAL, y son dos defectos y una trampa

Los tres aparecieron **después** de que la suite estuviera verde y las sondas estáticas dieran bien.

1. **El envoltorio no copiaba el `viewBox` del símbolo.** Un `<svg>` sin `viewBox` no tiene relación
   de aspecto, así que `height: auto` cae a los **150 px** por defecto de un elemento reemplazado:
   medido, la caja daba **638×150** donde tocaban ~190 de ancho. ▶ **No lo vio ninguna sonda anterior
   porque todas fijaban las dos dimensiones**; el defecto solo aparece cuando el consumidor deja una
   en `auto`, que es el caso normal. Ahora el componente lo copia, y hay caso y mutación.
2. **`.ilu { width: 100% }` pisaba al consumidor.** `.ilu` vive en `site.css` y los consumidores en
   `landing.css`, que se carga ANTES: misma especificidad → **gana el último**. El dibujo salía a
   todo el ancho de la tarjeta. ▶ *Gana el último, no el más específico* (`#277`), en otra hoja. La
   regla base ya no declara `width`: **el tamaño es colocación, y la colocación es del consumidor.**
3. ⚠️⚠️ **Y la trampa, que es la más cara: comparar capturas de la PORTADA no puede demostrar nada.**
   La primera medición dio «90,27 % de píxeles distintos → el dibujo pinta». Al medirle el **suelo de
   ruido** —dos capturas del MISMO estado— salió **64,45 %**: vídeo, imágenes perezosas y animaciones.
   *El número era real y la conclusión, basura.* ▶ El instrumento bueno extrae del servidor **el
   marcado que emite de verdad** y lo pinta en una página quieta con el CSS real: suelo de ruido
   **0,00 %**, y el dibujo da **3,04 %** en una región de **169×171 px**, que cuadra con la geometría
   (el path ocupa el 90 % de su `viewBox`, y el 90 % de 190 px son 171).
   ⚠️ Y de camino, una tercera: la sonda cargaba la tarjeta desde **otro puerto**, así que el `<use>`
   era **cross-origin** y no resolvía — con el producto sano. `asset()` emite URL absoluta.

---

## 15. Lo que decide el owner

1. ✅ **RESUELTA** (`2026-08-31`): el mapeo es **por nombre desde el paquete**, sin panel y sin
   migración. ⚠️ Coste declarado: el operador no puede cambiar un dibujo sin tocar el fichero.
2. ✅ **RESUELTA** (`2026-08-31`): **no hay suelo del producto**. Sin paquete no se pinta nada.
3. ✅ **RESUELTA por recomendación técnica** (`2026-08-31`): **la lista de ranuras decorativas nace
   VACÍA.** El mecanismo arranca solo con `zone-<slug>`; ranuras `slot-*`, **cero**.
   ▶ **El motivo es medido, no de gusto**: `elementos-fachada.md` §7 justificaba la lista diciendo que
   los estados vacíos «hoy no tienen ninguno», y **existen y se pintan** (§12·5). No hay agujero que
   tapar. Y `#257` ya enseñó el coste de hacerlo al revés: 19 dibujos declarados *«sin ninguna
   pantalla que los pinte»*, aparcados desde entonces.
   ▶ **La regla, para quien retome esto: una ranura nace en el MISMO cambio que su consumidor.** Es
   la disciplina de «las listas de excepción solo encogen» aplicada al revés — ésta solo crece, y solo
   con quien la use. Una gramática cerrada llena de claves muertas es peor que una vacía: el paquete
   las rellena, no las ve nadie, y **ninguna guarda puede distinguirlo**.
4. ✅ **RESUELTA por recomendación técnica** (`2026-08-31`): **la fuga de `site.css:2421` se cierra,
   pero DESPUÉS de este mecanismo y en su propia unidad.**
   ⚠️⚠️ **Y con una trampa que hay que decir**: hoy Play Jump Park funciona *porque* el `377 / 197`
   está quemado. Sacarlo a `--deco-tag-ratio` con defecto neutro **deja su tag mal proporcionado**
   hasta que su `client.css` declare el valor → **el cambio de producto y el de paquete se despliegan
   juntos**. Hacerlo después, además, es aplicar un patrón ya establecido en vez de inventar uno
   suelto. Ficha en `DEUDA`.
5. ✅ **RESUELTA por recomendación técnica** (`2026-08-31`): **las cabeceras de caché SÍ, pero no en
   esta spec y con un orden que importa** — (1.º) `?v={filemtime}` a los **7 assets que no lo llevan**,
   (2.º) `mod_expires` + `mod_deflate`. Al revés se hornea contenido viejo en la marca de un cliente:
   **dos de esos siete son assets DE CLIENTE que cambian al reinstalar**. Ficha en `DEUDA`.
   ⚠️ Mientras no esté hecho, **la ventaja de caché que §10 declara no existe** y hay que leerla como
   una condición, no como un hecho.
6. ⬜ **LA QUE DESBLOQUEA TODO LO DEMÁS: ¿cuál es la PRIMERA pantalla que lleva ilustración?**
   El mecanismo está terminado y **sin contenido, a propósito** (§14.2). Para que entre el primer
   dibujo hace falta elegir dónde va, porque la ranura y su consumidor entran en el mismo cambio.
   ▶ Candidatos que `elementos-fachada.md` §7 propone, con lo que sé de cada uno **medido**:
   · **los estados vacíos** (`F8` enjambre) — ⚠️ **ya existen y se pintan**, así que el dibujo sería
     decoración de algo que funciona, no tapar un agujero;
   · **la tarjeta de zona** (`.ride-card`) — es el único sitio donde la clave `zone-<slug>` ya
     resuelve sola, sin declarar ninguna ranura, porque `zones.slug` existe;
   · **la cabecera de `/precios`** (`F3` arco de rebote) — ⚠️ esa vista está a **9 bucles** contra el
     techo de 2 de su propio artboard (`#279`), así que cualquier cosa que se mueva compite con eso.
   ▶ **Mi recomendación**: la **tarjeta de zona**. Es la única que no exige declarar ranura nueva, la
   que tiene identificador estable, y la que convierte la deuda de `#257` en algo que sí se ve.

---

## 16. ❗ Lo que `#287` corrigió de este documento

Salió de medir en navegador lo que la primera pasada había dejado puesto, **con la suite verde**.

### 16.1 · La promesa de §7 era falsa en el caso normal

`<x-site.ilu>` preguntaba por el FICHERO (`IllustrationKit::version()`) y no por la CLAVE. Con el kit
instalado y el `<symbol>` ausente:

- el `<use>` no resuelve nada —correcto—, pero **el `<svg>` se emite igual**;
- sin `viewBox` no hay proporción intrínseca, así que `height: auto` cae a los **150 px** por defecto
  de un elemento reemplazado.

▶ **Medido en `/`**: las zonas `cap` y `cap2` metían **dos cajas de 190×150**. Hoy no se veían porque
las dos colocaciones son `position: absolute`; en cuanto una ranura futura ponga el dibujo en flujo,
son 150 px de agujero.

▶ **Y es el caso NORMAL**: la gramática admite un `zone-<slug>` por cada zona viva, pero la
instalación dibuja las que quiere y `kit:build` **no exige** una por zona. Toda instalación con más
zonas que dibujos tenía esto.

▶ Arreglo: `IllustrationKit::has()`, gratis porque `meta()` ya está memoizado por `filemtime`.
**Tres mutaciones muerden**, y la tercera es el CONTROL: con `has()` devolviendo `false` siempre se
caen seis casos, o sea que «no emite nada» no puede confundirse con haberlo roto todo.

### 16.2 · Una clase compuesta no la ve ningún inventario

Las tres clases de tratamiento se armaban con `'ilu--'.$trato`. Resultado: `.ilu--plano` y
`.ilu--contorno` salen **huérfanas con el producto sano** —`.ilu--troquel` no, porque esa rama sí la
escribía entera—.

▶ **La salida no fue una excepción en la guarda: fue escribir las tres enteras** (`match` sin
`default`, que además conserva el fallo ruidoso ante un tratamiento desconocido). Es el mismo motivo
por el que `DEUDA.md` arrastra ~50 reglas del cajón que *parecen* muertas y que ningún `grep` puede
confirmar: **el cajón compone sus clases**. Aquí no hacía falta.

### 16.3 · El trinquete

`FacadeCssHasNoOrphansTest` (6 casos) vigila que ninguna clase de fachada —`grain`, `spray`, `rays`,
`brand-strip`, `brand-dots`, `ilu`— se declare sin que alguna pantalla la pinte, con la lista de
excepciones **vacía**. Su hermana `FacadeDecorationIsPerScreenTest` vigila lo otro: que ninguna
textura viva dentro de un `@foreach`, y que **ningún dibujo del kit se pida con clave LITERAL dentro
de un bucle** — la clave variable (`zone-{{ $zone->slug }}`) queda fuera a propósito, porque eso es un
marcador y no una textura repetida.
