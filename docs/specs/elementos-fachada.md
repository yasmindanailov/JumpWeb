# [SPEC] «Elementos Fachada» — el kit de material gráfico del 2.º cliente

> Estado: ⬜ **BORRADOR — es una VALORACIÓN, no un plan de obra aprobado.**
> ⏸️ **PARADA a petición del owner (2026-08-30)**: va a **volver a subir el artboard** —esta
> valoración corre sobre la versión pegada el 2026-08-30, que puede no ser la definitiva— y a
> **decir qué elementos NO quiere**. Hasta las dos cosas, no se toca nada.
> ✅ **DECIDIDO** (`[DECIDIDO owner, 2026-08-30]`): (1) se construye el **hueco de ilustración por
> instalación** (§10·1); (2) **el grupo D · Siluetas se retira ENTERO y SIN EXCEPCIONES** —sus ocho
> piezas y la figura vieja del cristal—, así que **el kit queda en 32 de las 40**. Seis de sus ocho
> tratamientos sobreviven dentro de F/G; `D4` y `D6` **no**, y se van igual.
> ⚠️ **Coste declarado (§4.1): del kit no sale NINGUNA animación** — `D6` era la única pieza que
> contaba un movimiento. El movimiento lo sigue decidiendo `Microanimaciones PJP` (`#277`).
> Última actualización: 2026-08-30 · Decisión asociada: `DECISIONES #281`.
> Método: el mismo que `#277` con `Microanimaciones PJP` — **primero qué de esto ya está en el
> producto, después qué falta, qué cuesta y qué contradice**. Aquí no se implementa nada.

---

## 1. Contexto

`Elementos Fachada` es el kit de material gráfico que el cliente saca del **mural de la entrada de
su parque**: texturas, manchas de pintura, bandas, siluetas, poses y las piezas de página ya
montadas con todo ello. Es a la vez:

- **dentro del alcance del owner** — su lista de lo que sí se toma del canvas dice «colores,
  **elementos**, iconos, formas, menú, hero y pie» (`[DECIDIDO owner, 2026-08-28]`,
  `00-REFACTOR.md` fase 3); y
- **uno de los artboards SIN MIGRAR**, de los que la misma línea dice: **de ahí se saca la FORMA,
  nunca el color**.

Las dos cosas a la vez son la clave de esta valoración, y se confirman midiendo: **de los nueve
literales de color del artboard, cero coinciden con los que el cliente tiene hoy en `client.css`**.

| pieza | artboard | `client.css` vigente |
|---|---|---|
| lima | `#8CC63F` | `--strip-2: #A3C21C` · `--ok: #5FA82E` |
| papel | `#EFEDE7` | `--bg: #F4F4F1` |
| cian · amarillo · naranja · rojo | `#2FB6DE` · `#F5B301` · `#F26C1D` · `#E33B2E` | `--strip-1/3/4/5: #1AA9DE` · `#F5C400` · `#F2711C` · `#D93E14` |

### 1.1 · ⚠️⚠️ La copia local del canvas está CADUCADA, y la que llega pegada trae la codificación rota

Medido el 2026-08-30 contra `mockup_playjumppark/Elementos Fachada.dc.html` (2026-08-27, 07:34):

| | copia local | versión que entrega el owner |
|---|---|---|
| grupos | B · C · D · E | **A · B · C · D · F · G · E** |
| piezas | 20 (`B1`–`B6`, `C2`–`C3`, `D1`–**`D9`**, `E1`–`E3`) | **40** — A 4 · B 6 · C 2 · D 8 · F 11 · G 6 · E 3 (+`A1/A2/A3/A5`, +`F1`–`F11`, +`G1`–`G6`, y **`D9` ya no está**) |

⚠️ **Y el fichero pegado llega con los acentos destrozados** (`diversiÃ³n`, `PUÃOS`, `MÃS ALTO`)
mientras **la copia local está en UTF-8 sano**: o sea que el daño lo trae el pegado, no el canvas.
Es literalmente la trampa que `tema-por-instalacion.md` §25.1 ya documentó, y la conducta es la
misma: **no se sobrescribe la copia local con un fichero pegado**. El marcado y los SVG son ASCII y
sí están íntegros, así que sirven para medir; la prosa, no.

▶ **`DesignSync` sigue sin autorización** (`/design-login` no corre en sesión no interactiva), como
en `#262` y `#279`. **Antes de implementar una sola pieza de aquí hay que refrescar la copia**, o se
volverá a construir contra material caducado.

### 1.2 · `D9` desapareció porque el cliente resolvió su propio hueco — y lo resolvió MEJOR de lo que pedía

`D9` se llamaba «Las poses que faltan» y decía, literalmente:

> «Todo lo de arriba es la misma pose transformada, y se nota cuando se repite. La riqueza real son
> poses nuevas, y eso es arte: **cuatro recortes en PNG** con fondo transparente… En cuanto lleguen
> entran en D1–D8 sin tocar nada más.»

▶ **Los grupos F y G son la respuesta a ese D9**, y llegaron **doce poses en SVG a un color plano**
(9 de adulto + 3 de niño) en vez de los cuatro PNG que pedía. ⚠️ **Eso cambia el veredicto técnico
entero**: un PNG no se recolorea, no escala, no se recorta con máscara y no hereda `currentColor`.
Un `<path>` sí las cuatro cosas — que es exactamente lo que el resto del kit le pide a la silueta.

---

## 2. Objetivo y alcance

**Objetivo**: saber qué de este kit es un MECANISMO que el producto puede tener, qué es ARTE de este
cliente, dónde iría cada cosa y qué cuesta — con evidencia, no con impresión.

**Fuera de alcance, explícitamente**:
- **El color.** Artboard sin migrar: se toma la forma y la técnica, los tonos salen de los roles.
- **El panel.** `#258` lo dejó fuera a propósito: es otro idioma y es herramienta de operador.
- **La fase 3 «Las secciones»**, que sigue ⏸️ parada por el owner. Esta spec no la reabre: valora el
  kit como material, no propone rehacer ninguna sección.
- **Implementar.** No se escribe una línea de producto hasta que el owner elija de §10.

---

## 3. Lo que YA está en el producto (la pregunta de `#277`)

Medido sobre `public/css/*.css` y `resources/views/`:

| pieza del artboard | ¿está? | dónde, y con qué exactitud |
|---|---|---|
| **A1 · Trama de puntos** | ✅ **al dígito** | `.menu__grain` (`site.css`) usa `radial-gradient(circle, … 1.4px, transparent 1.6px)` + `background-size: 20px 20px` — **los tres números del artboard**. Y su comentario ya dice lo mismo que él: *«la única textura que el sistema admite sobre tinta»*. Lo que no tiene: la perilla de densidad (`--trama`) ni uso fuera del menú |
| **A3 · Rayos** | ✅ la técnica | `.offw-rays` es un `repeating-conic-gradient`, pero vive dentro del estallido del widget de ofertas y es una animación, no una textura de tarjeta |
| **C2 · Tiras (continua)** | ✅ **y en el mismo orden** | `<x-site.brand-strip>` + `--strip-1..5`. **Los cinco colores coinciden en orden 5 de 5** (cian · lima · amarillo · naranja · rojo). Faltan sus otras dos variantes: **en cuñas** (`skewX(-12°)`) y **punteada** |
| **E1 · Sombra dura** | ✅ **es un rol** | `--shadow-float: 5px 5px 0 var(--paper-fg)` — el `5px 5px 0` de E1, leyendo el color del rol y no del literal (`#196`, `#265`) |
| **Las cuatro tipografías** | ✅ **4 de 4** | `--font-display` (Bungee) · `--font-body` (Hanken Grotesk) · `--font-accent` (Permanent Marker, el rotulador) · `--font-mono` (JetBrains Mono) |
| **Tramado de «hueco de foto»** | ✅ | `repeating-linear-gradient(135deg, …)`, 8 usos entre `landing.css` y `site.css` |
| **Máscara / troquel** | ✅ la técnica | `mask-image` y `clip-path` ya se usan |
| **Marquesina (base de C3)** | ✅ | `gallery-marquee` y `svc-marquee`. ⚠️ Pero **la de palabras de la portada la RETIRÓ `#252`** |
| **`mix-blend-mode: multiply`** | ❌ **cero usos** | Lo piden B2, D7, F4 y F5 |

▶ **Conclusión de esta sección: el vocabulario de base ya está.** Lo que este artboard trae nuevo no
es la técnica — es el **repertorio de formas** y las **composiciones**.

---

## 4. El inventario, pieza a pieza

**Veredicto**: 🟩 mecanismo genérico · 🟨 mecanismo con arte del cliente dentro · 🟥 arte del cliente.

### A · Texturas de tarjeta
| | pieza | veredicto | nota |
|---|---|---|---|
| A1 | Trama de puntos | 🟩 | **ya está**; falta generalizarla fuera del menú y darle densidad |
| A2 | Trama que se apaga | 🟩 | A1 + máscara de degradado. Barato |
| A3 | Rayos | 🟩 | la técnica está; falta la variante estática |
| A5 | Niebla de spray | 🟩 | tres `radial-gradient` superpuestos. Barato |
| — | *A4 no existe* | | el artboard salta de A3 a A5 |

### B · Manchas (seis formas fijas)
| | pieza | veredicto | nota |
|---|---|---|---|
| B1 | Set de 6 manchas | 🟥 | **arte del cliente.** Medido: 6 geometrías distintas, reusadas 3–4 veces |
| B2 | Manchas en capas | 🟨 | necesita `mix-blend-mode: multiply`, que hoy no se usa |
| B3 | Titular sobre mancha | 🟨 | la composición es genérica; la mancha no |
| B4 | Foto dentro de mancha | 🟨 | máscara — la técnica ya está |
| B5 | Precio y promo | 🟨 | se solapa con la chapa «destacado» que ya existe |
| B6 | Mancha de esquina | 🟨 | «marca la categoría por color sin etiqueta» — encaja con `.ride-card` |

### C · Bandas y cintas
| | pieza | veredicto | nota |
|---|---|---|---|
| C2 | Tiras | 🟩 | la **continua ya está**; faltan cuñas y punteada |
| C3 | Cinta del eslogan | 🟩 | ⚠️ **choca con `#252`**, que retiró la marquesina de palabras de la portada |
| — | *C1 no existe* | | el grupo se titula «GOTEOS Y BANDAS» y **no tiene ningún goteo** |

### ❌ D · Tratamientos de silueta — **EL GRUPO ENTERO SE RETIRA** (`[DECIDIDO owner, 2026-08-30]`)

*«Todo el grupo D · Siluetas nada, porque no me gusta.»* Son las **ocho** piezas `D1`–`D8`, y con
ellas se va **la figura vieja**: la pose única del cristal, `assets/silueta-saltador.svg`.

▶ **Y su propio artboard ya había escrito el porqué.** La tarjeta `D9` que él mismo retiró decía:
*«Todo lo de arriba es la misma pose transformada, **y se nota cuando se repite**»*. El grupo D es
**una sola pose** repetida ocho veces; los grupos F y G son las **doce** que la sustituyen. Esta
decisión no contradice al artboard: **lo completa**.

⚠️ **Y NO toca el logotipo.** Medido: `public/img/client-logo.svg` **no contiene** el path de esa
figura (0 coincidencias, 14 `<path>`, 57.783 B). El saltador del logotipo es otro dibujo, y sigue
como lo dejó `#275` (`[DECIDIDO owner]`: «idéntico»).

⚠️ **El grupo F lo referencia**: su cabecera dice «cualquier tratamiento del grupo D sirve para
cualquiera de ellas». Con D fuera, esa frase apunta a los tratamientos **tal como F y G los traen
ya montados** — no hay que ir a buscar nada al grupo retirado.

▶ **Seis de los ocho tratamientos NO se pierden: viven ya dentro de F y G**, con las poses nuevas.

| tratamiento de D | ¿sobrevive? |
|---|---|
| D1 silueta plana | ✅ **F1 · F6** |
| D2 friso | ✅ **F2** (y **G3** friso familiar) |
| D3 troquel | ✅ **F7 · G6** |
| D5 contorno | ✅ **F6 · G6** |
| D7 pareja espejada | ✅ **F4** (con mancha detrás) |
| D8 campo de siluetas | ✅ **F8** enjambre |
| **D4 pegatina sobre mancha** | ❌ **SE VA, sin sustituto** (`[DECIDIDO owner]`) |
| **D6 eco de salto** | ❌ **SE VA, sin sustituto** (`[DECIDIDO owner]`) — ⚠️ ver §4.1 |

#### 4.1 · ⚠️ Lo que cuesta que `D6` se vaya, dicho para que nadie lo redescubra

`D6` («eco de salto»: tres copias de la misma pose subiendo y desvaneciendo, opacidad 100/45/20) era
**la única pieza del kit que CUENTA UN MOVIMIENTO**. Todo lo demás son texturas, manchas, bandas,
poses y composiciones **quietas**.

▶ Consecuencia, sin dramatizar: **de `Elementos Fachada` no sale ninguna animación.** El movimiento
del sitio lo seguirá decidiendo `Microanimaciones PJP` (`#277`), que es el artboard que existe para
eso. `[DECIDIDO owner, 2026-08-30]`, preguntado con la consecuencia delante.

### F · Poses (9) · G · Poses de niño (3)
| | pieza | veredicto | nota |
|---|---|---|---|
| F1 / G1 | Inventario de poses | 🟥 | **arte del cliente**: 12 geometrías, y son la materia prima de todo lo demás |
| F2 | Friso mixto | 🟩 | una pose distinta por figura |
| F3 | Arco de rebote | 🟩 | cuatro momentos sobre una parábola punteada |
| F4 | Pareja en mancha | 🟨 | necesita `mix-blend-mode: multiply` |
| F5 | Racimo de tres | 🟩 | |
| F6 | Plano y contorno | 🟩 | **salen del MISMO path**: `fill:none` + `stroke`, `viewBox` expandido |
| F7 / G6 | Troquel | 🟩 | **sale del MISMO path**: rect exterior + `fill-rule="evenodd"` |
| F8 | Enjambre | 🟩 | fondo de tarjeta vacía / pantalla sin datos |
| F9 | Titular con pose | 🟩 | |
| F10 / G5 | **Iconos de zona** | 🟨 | ▶ **cae justo encima de una deuda abierta**: ver §5 |
| F11 | Cinta de poses | 🟩 | C3 con poses en los huecos |
| G2 | Escala niño/adulto | 🟩 | regla dura: 62 %. **Verificado en su marcado: 73/118 = 61,9 %** ✓ |
| G3 / G4 | Friso familiar · Trío | 🟩 | G4 declara alturas 100/85/73; medido 100/84,6/73,1 ✓ |

### E · Aplicado
| | pieza | veredicto | nota |
|---|---|---|---|
| E1 | Botones | 🟩 | **ya es `.btn` + `--shadow-float`.** ⚠️ Y aquí está el choque de `#277`: su norma de hover es *pegatina que se aplasta y el color no cambia nunca*, y hoy `.btn` sube y cambia el fondo |
| E2 | Etiquetas | 🟩 | cápsulas llenas / punteadas / tinta. Ya hay chips |
| E3 | Tarjeta de zona | 🟨 | es `.ride-card` + B6 + una silueta apoyada en el borde |

---

## 5. ❗ La línea que parte el kit en dos, y por qué importa

**Todo lo 🟥 y la mitad de lo 🟨 es ARTE DE ESTE CLIENTE**: las 6 manchas y las 12 poses salen de
*su* mural. El producto es white-label y una instalación sin mural no tiene nada que poner ahí.

▶ **Y eso no es una objeción nueva: es una deuda YA ABIERTA.** `DECISIONES #257` la dejó escrita con
estas palabras: *«Los 19 de parque (5 «Del parque» + 14 zonas) NO están: son del cliente y **no
existe mecanismo** para sustituir un dibujo por instalación»*.

⚠️⚠️ **F10 y G5 son exactamente esos iconos de zona.** O sea que este artboard **no crea el
problema: aterriza justo encima del que ya teníamos anotado, y con el material para resolverlo**.
La pregunta que abre no es «¿metemos manchas?», es **«¿construimos por fin el hueco por instalación
para los dibujos?»** — el que hoy tienen el logotipo (`client-logo.svg`) y el icono
(`client-favicon.svg`) y no tiene ninguna ilustración.

▶ Sin ese hueco, meter manchas y poses en el producto **clava el mural de este parque dentro de
JumpWeb**, que es justo lo que `INSTALACION-CLIENTE.md` §4 existe para impedir.

---

## 6. Cómo se implementaría: **una geometría, doce composiciones**

Es el hallazgo técnico de la valoración, y está medido.

▶ **De un solo `<path>` por pose salen los tres tratamientos**, sin un segundo asset:

| tratamiento | cómo | verificado |
|---|---|---|
| **plano** | el path con `fill` | D1 / F1 |
| **contorno** | el **mismo** path con `fill:none` + `stroke` y el `viewBox` expandido | F6: `152 → 157,1` y `194,3 → 199,4`, o sea **+5,1 = el `stroke-width` de 4,1 más margen**. G6 idéntico |
| **troquel** | el **mismo** path + un rect exterior y `fill-rule="evenodd"` | F7 (`0 0 247.9 247.9`) y G6 |

⚠️ **Y con el grupo D retirado esto deja de ser una ventaja y pasa a ser LA REGLA**: en D, plano y
contorno eran **dos geometrías distintas** (medido: 2.524 B y 3.746 B, con arranques distintos);
en F/G **cada pose es un solo `<path>`** del que salen los tres tratamientos. Doce ficheros, y no
veinticuatro.

▶ **Y las composiciones se montan con `<use>`, no repitiendo el path.** El artboard lo repite porque
es un canvas: medido sobre el grupo D —**la silueta aparece 44 veces** (13 planas + 31 de contorno)
y el bloque `D8` pesa **100.374 B para 24 figuras, 4.182 B cada una**—, y con `<defs>` + `<use>` esas
24 serían ~2,5 KB de geometría + ~120 B por copia ≈ **5,4 KB: 18,6× menos**.
⚠️ **Esa medida es de piezas que ya no entran**, pero el impuesto es idéntico y quien lo hereda es
**`F8`, con sus 14 copias**: esa cifra **no está medida**, y hay que medirla contra el fichero bueno
antes de presupuestar nada.

▶ **Y la medición destapó una noticia buena**: las seis manchas **ya son ficheros sueltos en el
canvas** — `splash-1.svg` … `splash-6.svg`, **1.162–1.493 B cada una, 7.979 B las seis**, en el mismo
orden que `B1` (01→`M156.1`, 02→`M179.5`, 03→`M145.3`, 04→`M136.0`, 05→`M169.9`, 06→`M179.0`). No hay
que extraerlas del artboard: **ya vienen en la forma que el hueco de ilustración por instalación
necesita**.

⚠️⚠️ **Y ahí hay una trampa ya pagada**: `#266` demostró que **una animación CSS sobre un elemento
de `<defs>` NO PINTA NADA** —lo que se dibuja son los `<use>`—, y que **dentro de un SVG los `px` de
un `transform` son unidades del `viewBox`, no píxeles**. Cualquier friso o eco animado de aquí
tropieza con las dos.

⚠️ Y el sitio ya sabe lo que cuesta incrustar SVG en línea: `#254` mide **~64 KB por copia** del
logotipo, y `#275` que **son el 42 % del HTML de `GET /`**. Un kit de figuras entra por la misma
puerta y hay que presupuestarlo antes, no después.

---

## 7. Dónde iría cada cosa (propuesta, no decisión)

| pieza | dónde, en NUESTRAS vistas |
|---|---|
| **A1 trama** (generalizada) | las superficies `data-surface="ink"`: el menú (ya), la tarjeta del cierre, el pie |
| **A2 trama que se apaga** | tarjetas con mucho texto: `/normas`, `/servicios` |
| **A3 rayos estáticos** | detrás de un precio en `/precios`. Su regla: **uno por página** |
| **A5 niebla de spray** | el paso 5 del cajón (identificación) y `/contacto`: formularios largos |
| **B5 precio y promo** | `/precios` y `/entradas` — se solapa con la chapa «destacado» que ya corre ahí |
| **B6 mancha de esquina** | `.ride-card` de la portada: «marca la categoría por color sin etiqueta» |
| **C2 cuñas / punteada** | separador entre las 7 secciones de la portada; punteada, en listas de `/normas` |
| **F2 friso mixto** | remate inferior de la tarjeta del cierre y del pie |
| **F8 enjambre** | **estados vacíos**, que hoy no tienen ninguno: cajón sin franjas, «Mis pedidos» sin pedidos, `/entradas` sin catálogo |
| **F3 arco de rebote** | cabecera de `/precios` o del cierre |
| **F10 / G5 iconos de zona** | **la deuda de `#257`**: los 19 dibujos de parque que faltan |
| **E2 etiquetas** | los chips que ya existen: aforo, edad, condiciones |
| **E3 tarjeta de zona** | `.ride-card`, que ya es esa tarjeta sin el material |

---

## 8. ⚠️ Lo que el artboard se contradice a sí mismo (todo medido)

> ⚠️ **Son SEIS, no siete**: el punto 6 era un error de medición MÍO y va tachado abajo. Y dos de
> las seis (4 y 5) son del grupo D, que ya está retirado.

Van aquí porque **cada una es una decisión que alguien va a tener que tomar**, no para llevarle la
contraria al cliente. Es el mismo patrón que `#213` y `#277` documentaron con sus otras fuentes.

1. **«Cuatro reglas para no pasarse»… y son CINCO.** La 05 («una pose no se repite dos veces en la
   misma pantalla; hay nueve: úsalas») entró con el grupo F y **el título no se actualizó**.
2. **La regla 05 la rompe su propia pieza F8.** Medido: **14 copias, 9 poses distintas → 5 se
   repiten**; y el rótulo de F8 dice «catorce copias pequeñas de **poses distintas**». Hay que
   decidir si la regla vale también para los fondos decorativos o solo para las figuras con peso.
3. **El grupo C se titula «GOTEOS Y BANDAS» y no tiene ningún goteo** — `C1` está retirado, aunque
   `assets/goteo-a.svg` y `goteo-b.svg` siguen en el canvas.
4. **D6 dice «desfase de 14 px»** y su marcado usa **28 y 34**; ningún número del bloque vale 14.
5. **D5 dice «keyline de 13»** y su marcado usa **13 y 15**.
6. ⚠️⚠️ ~~**D4 apunta a un `assets/saltador.png` que no existe**~~ — **ERA FALSO, y el fallo fue
   MÍO**: `saltador.png` existe y pesa **98.508 B**. Lo que falló fue el instrumento — un `head`
   truncó el listado del directorio a diez líneas y `saltador.png` era la undécima. *Un listado
   truncado no dice que algo no exista: dice que no lo has visto.* Octava trampa de instrumento de
   este carril, y la más barata de todas.
7. **La regla 02** («la pintura nunca va debajo de un párrafo») y **A5** («formularios largos y
   paneles laterales») se rozan: hay que decidir si una niebla difusa cuenta como «pintura».

▶ **Lo que NO se contradice y conviene decir**: la cabecera del artboard —*«nada de esto pinta el
fondo de una sección: el fondo es papel y el material vive dentro de las tarjetas»*— **confirma el
hallazgo `S-00`** que `tema-por-instalacion.md` §1.2 ya había incorporado. Dos fuentes suyas
independientes dicen lo mismo: eso sube la confianza en esa corrección.

---

## 9. Coste, en lo que este proyecto mide

- **Bytes.** El presupuesto de marcado ya está tenso: el logotipo son **~64 KB × 2 copias = el 42 %
  del HTML de `GET /`** (`#275`), y hay una ficha de deuda abierta por la segunda copia. Un kit de
  figuras **no puede entrar en línea sin presupuesto y sin `<use>`**.
- **El techo de movimiento.** `#279` dejó `/precios` en **9 bucles** contra el techo de **dos** que
  escribe el propio cliente. Cualquier pieza animada de aquí **compite con eso**.
- **Guardas.** Todo lo que entre necesita las que este carril ya tiene: `RawColourIsNotATokenTest`
  (ni un literal del artboard), `ShapeScaleTest`, `MotionScaleTest`, `IconSetAnatomyTest`.
- **Lo que NO cuesta**: A2, A5, C2-cuñas, C2-punteada, D6 y los estados vacíos son CSS sobre
  mecanismos que ya existen.

---

## 10. ❗ Lo que decide el owner (nada de esto se toca antes)

1. ✅ **El hueco de ilustración por instalación SE CONSTRUYE** (`[DECIDIDO owner, 2026-08-30]`),
   **y cierra de paso la deuda de `#257`**. Es la decisión que manda: sin él, todo lo 🟥 y la mitad
   de lo 🟨 clavaría el mural de este parque dentro del producto. ▶ Con ella, el producto gana su
   **tercer** hueco por instalación —tras `client-logo.svg` (`#254`) y `client-favicon.svg`— y es el
   primero para ILUSTRACIÓN, no para marca. **Su diseño no está hecho**: es lo primero que hay que
   especificar cuando se retome.
2. **Refrescar el canvas** (§1.1): esta copia está caducada y el pegado viene con la codificación
   rota. ¿`/design-login` en una sesión interactiva, o exportación tuya?
3. **Por dónde empezar.** El orden por rentabilidad que sale de §3 y §9 es: **(a)** generalizar la
   trama A1, que ya está escrita; **(b)** las dos variantes de tira que faltan; **(c)** los estados
   vacíos con D8/F8, que hoy no tienen nada; **(d)** los iconos de zona F10/G5, que cierran `#257`.
   Lo demás va detrás del punto 1.
4. **C3 · la cinta del eslogan** choca con `#252`, que la retiró de la portada por espacio. ¿Vuelve,
   y a costa de qué?
5. **Las contradicciones de §8**, en especial la 2: ¿la regla de «no repetir pose» vale también para
   los fondos decorativos, o solo para las figuras con peso? Su propio `F8` la rompe.
6. ✅ **RESUELTA**: `D4` y `D6` **se van también** (`[DECIDIDO owner, 2026-08-30]`, preguntado con la
   consecuencia delante). El grupo D desaparece **sin excepciones** y el kit se queda en **32 piezas
   de las 40**. ⚠️ Lo que eso cuesta está en §4.1: **del kit no sale ninguna animación**.
