# Handoff — Sistema de diseño Play Jump Park

Estado a 8 sep 2026 · sistema v1.32. Para seguir en otro chat: **lee este archivo y `CLAUDE.md`**, y abre
`Sistema PJP.dc.html`, que es el índice vivo con el estado de cada pieza.

## Dónde estamos

**Cuatro bloques cerrados.** La portada está entera en móvil y en escritorio; lo que queda no es
diseño de estructura, es **vestido** (iconos, imagen, movimiento) y después el SPA.

- **El sistema de diseño**: siete documentos, cerrados.
- **La portada**: las ocho secciones **y el marco**, aprobados. Mide **9,86 pantallas** contra un
  techo de 11 (el 8 anterior se corrigió: se contradecía con «una pantalla por pregunta»).
- **Las páginas interiores**: **las siete del inventario, cerradas**, más el armazón que las viste
  (`Layout Paginas PJP.dc.html`).
- **El escritorio de la portada: CERRADO** (turnos 1 a 6 de `Escritorio PJP.dc.html`). Cabecera
  única, retícula y 01 · 02 Tarifas sin carril, con la pestaña del sistema · 05 y 07 a dos columnas ·
  03 a tres · 04, 06 y 08 · el hero a sangre con sus dos estados y el pie en fila. Lo siguiente es la
  **pasada de vestido**.

- **La portada montada, en móvil: `Portada PJP.dc.html`** (8 sep). Un solo archivo con las ocho
  secciones, el marco y el pie, cada pieza **copiada de su opción aprobada**. Medido: **10,23
  pantallas** con el hero entero y **9,95** en cuanto se minimiza, contra un techo de 11.

Lo que queda es el **escritorio sobre lo montado**, la **pasada de vestido** y el SPA.

| Documento | Estado | Qué fija |
|---|---|---|
| `tokens-pjp.js` | cerrado · v1.7 | Todos los valores. Ningún documento teclea un hex: lo lee de aquí |
| `Sistema PJP.dc.html` | cerrado | Índice, las diez capas con su estado, gobierno y registro de cambios |
| `Colores de Marca PJP.dc.html` | cerrado | Fundamentos, color, tipografía, prohibiciones, orden de página, la prueba |
| `Espacio y Reticula PJP.dc.html` | cerrado | Escala, ritmo, retícula, medidas fijas, forma y radios, elevación |
| `Componentes PJP.dc.html` | cerrado | Botones, campos, **pestañas (con token desde v1.7)**, modal, **hoja de ficha**, avisos, FAQ, nav, tabla, carrusel, **carril con foco**, los cuatro controles del cajón, tabla de guardas |
| `Voz PJP.dc.html` | cerrado | Tono, esto sí/esto no, formato de cifras, puntuación, tres idiomas, vocabulario, textos de interfaz |
| `Iconos PJP.dc.html` | cerrado | 65 glifos, anatomía, tamaño óptico medido, matriz de confusión, dos piezas fuera de norma |
| `Elementos Fachada.dc.html` | cerrado salvo censo | 30 piezas, presupuesto medido a 390, contrato del sprite |
| `Microanimaciones PJP.dc.html` | cerrado | Ocho tiempos, cuatro curvas, hover, los tres bucles, prohibiciones y movimiento reducido |

Hojas de trabajo que se conservan porque guardan el rastro de una decisión, no porque se
mantengan: `Iconos PJP eleccion`, `Iconos PJP pack`, `Iconos PJP identidad`, `Iconos PJP taller`.

`archivo/` es histórico: 18 piezas (exploraciones «variantes», paleta antigua, secciones
sustituidas, la auditoría v0.9). No se consulta para decidir ni se actualiza.

## Lo que queda, por orden

0. **El escritorio, sobre el archivo montado.** El móvil ya es una página (`Portada PJP.dc.html`);
   el escritorio sigue siendo **diez dibujos sueltos** en `Escritorio PJP` (1c · 2b · 3a · 3b · 4b ·
   5a · 5b · 5c · 6a · 6b). Hay que traerlos al mismo marcado con un salto real en 1024, y **copiando
   su marcado**, no de memoria. Mientras sean dos archivos, cada decisión de vestido se paga dos veces.
1. **La pasada de vestido**, ya sobre el archivo montado y sobre las ocho secciones a la vez: iconos, imagen y
   movimiento. Ahí se cuadra el movimiento de cada sección: hoy `Landing PJP Modos` usa **80 ms y
   trece curvas propias** que no están en tokens, más **16 hex y 9 radios** fuera de sistema, casi
   todos en el menú. Es la última bolsa de valores inventados viva. **Y arranca por el único cabo
   suelto que dejó el escritorio**: con el hero a pantalla entera no asoma nada, así que la única
   señal de que la página sigue es el movimiento — y con `prefers-reduced-motion` no queda ninguna.
2. **El SPA de reservas**, después de la landing.
3. **El censo pieza × pantalla** del kit de fachada. Depende de 1: la lista de piezas la decide
   quién las pinta. Regla de gobierno ya escrita: *ninguna pieza sin pantalla, ninguna pantalla
   con una pieza que no esté en el kit*.
4. **Exportar `client-kit.svg`** con las claves del contrato (ver más abajo).
5. **El justificante digital** — no es un componente, es una pantalla pública suelta más un
   estado en seis superficies. **05 ya lo promete en una línea** (y como oferta, porque en entradas es
   opcional), así que la pantalla tiene que existir antes de que la portada salga. Y falta decidir dónde
   viven las **excursiones**, donde sí es obligatorio: ver el aviso al final de «05 Antes de venir». Entra con el bloque del SPA, junto al paso de menores (que tiene
   tres caras según `ticket_types.guardian_authorization`: `none`, `optional`, `required`).

## Lo que falta del dueño · lista única (8 sep 2026)

Está repartido por las fichas de cada pieza; aquí junto para no perderlo. **Nada de esto es
diseño**: son datos que bloquean piezas ya dibujadas.

**Conflictos abiertos** — el mismo dato dicho de dos maneras, y hay que elegir:

| Qué | Una versión | La otra | Dónde duele |
|---|---|---|---|
| **Horario** | L–J 16:30–21:30 · V–D 11:30–21:30 (cerrado en 07) | L–V 16:30 · S–D 11:00 (web publicada) | 07 en vivo, y el «abierto ahora» |
| **Aparcamiento** | «En la calle, delante, y gratis» (07) | «Gratis 2 h en el recinto, después 1 €/h» (web) | 07 y la duda que se cayó de 08 |
| **Edades** | Kids 2–6 · Jump desde 7 (**aprobado**) | 4–7/8+ en tarifas y 1–12/6+ en dudas, publicadas | Hay que corregir la web, no el diseño |
| **Precios de cumpleaños** | 14,95 · 15,95, especiales 16,95 · 19,95, señal 50 (**resuelto**: es el catálogo, ya aplicado en 04 y en `/cumpleanos`) | 11 y 15 € con señal de 30, que publicaba la portada | Estaba en 04; corregido el 8 sep |

**Datos que faltan**, por lo que bloquean:

- **El bar**: su **nombre real** —hoy se llama «el bar» en todas las superficies y es el titular de
  su página— y **la carta** plato a plato con precios. Además: ¿se puede entrar solo al bar, sin
  entrada?, y los **alérgenos**, que con comida son obligación legal.
- **Excursiones**: los **tramos de descuento** y sus precios, **cuánto dura**, **mínimo y máximo**,
  el precio de la **merienda** y **qué cursos** pueden venir.
- **06 Reseñas**: nada. El enlace de 24 px **ya está arreglado** (sep 2026).
- **07 Visítanos**: los **festivos de Lorca y de la Región**, que son los que el visitante de fuera
  no sabe (los festivos enteros viven en `/precios`). La **dirección** ya está puesta —la publicada,
  Ctra. de Granada, 30813 Lorca— y solo falta el desglose exacto en `address.line1/line2`.
- **03 Qué hay dentro**: los **nombres reales de las 23 atracciones** y el contenido del «18 más».
  La deuda del recorte **ya está pagada en escritorio** (`4b`): la cifra va en la entradilla y hay
  una puerta a `/atracciones` bajo el mosaico. ⚠ **En móvil sigue pendiente**: allí esa página solo
  tiene entrada desde la tarjeta de Zonas.
- **/normas**: si la **Zona Kids** tiene norma propia, y **qué falta de dentro** — comida de fuera,
  móviles, joyas, gafas, embarazadas, lesiones.
- **/contacto**: las **horas concretas** de atención («horario laboral» es vago para quien escribe
  un domingo) y si el **WhatsApp** es el mismo número que el móvil. El **fijo está inventado**
  (968 47 12 30) a petición suya.
- **08 Dudas**: si «interior y climatizado» **sube a 03**, y **qué otras dudas** oyen en el
  mostrador.
- **Marco**: las **dos cruces del menú** (Tarifas y Cumpleaños son sección *y* página) y el **alto
  del par en teléfono** (la spec pide 48, el guion midió 56).
- **/cumpleanos**: si la **edad del pack** (4–7) conviviendo con la de la zona (2–6) es correcta —un
  niño de 3 salta pero no puede tener su cumple ahí— y si la **hora extra de sala** aparece también
  en la portada.

## Paquete de entrega a desarrollo (8 sep 2026)

`design_handoff_portada/` — `README.md` autosuficiente (un desarrollador que no estuvo en la
conversación puede implementarla desde ahí solo), `Portada PJP.dc.html`, `tokens-pjp.js`, este
registro, las reglas del proyecto, y `marca/`, `assets/` y las cinco fotos del mosaico.

El README dice lo que hay que decir: que el HTML es **referencia de diseño y no código de
producción**, que el runtime de huecos **no se porta**, el punto de corte, las dos superficies, el
recorrido, el par doble, el interruptor, los tres movimientos, el contrato de datos con los precios
reales, las doce reglas duras, los tokens, las medidas del DOM y las **cinco cosas pendientes del
dueño**. Y el aviso que costó un defecto: **un valor por defecto del panel es lo que se publica**.

## Vestido · los tres movimientos, puestos (8 sep 2026)

De los **12** de `Microanimaciones` la portada usaba **1**. Ahora usa **3**, que son los tres que el
dueño pidió, y los tres **copiados literales** con sus duraciones y curvas de `tokens-pjp.js`:

| Movimiento | Dónde | Duración · curva | Comprobación |
|---|---|---|---|
| `pjpbote` | el logotipo, al llegar el armazón | 900 · una pasada | `iterations: 1`, no se repite |
| `pjpsello` | los 4 sellos de precio (01 y 04) | 420 · **`lona`** | 1 animado · 1 saltado |
| `pjpentra` | las teselas del mosaico de 03 | 320 · **`bote`**, cascada 90 | 0 · 90 · 180 · 270 · 360 |

**La regla del sello se cumple sola.** La curva `lona` está marcada **«máx. uno por pantalla»** y en
escritorio los dos sellos de 01 van a la par: de los que entran a la vez **anima el primero y el
resto se dan por vistos sin animar**. Verificado en escritorio: 2 en pantalla, **1 animado, 1
saltado**. No hace falta una política por superficie.

⚠ **Y aquí se perdió el turno: el `IntersectionObserver` no vale en este archivo.** Lo cablé en
`componentDidMount`, pero **el punto de corte cambia el estado DESPUÉS del montaje**, así que el
observador se quedaba mirando los nodos de la rama móvil, que ya no existen — en escritorio no
animaba nada. Y el arreglo obvio tampoco: la guarda por `prevState` en `componentDidUpdate` **no
recibe ese argumento en esta firma**, así que `prevEstado &&` la saltaba **en silencio**. Los dos
movimientos cuelgan ahora del **mismo manejador de scroll que el recorrido**, que es el que sé que
corre y que sobrevive al cambio de rama sin cablear nada.

*Un guard con `&&` sobre un argumento que no llega no falla: no hace nada, que es peor.*

⚠ Y dos veces di por roto lo que funcionaba **por culpa de la sonda**: el regex ` (\d+)ms both` no
casa con lo que devuelve `style.animation`, que es `0ms 1 normal both running pjpEntra`. *Cuando la
medición dice cero, primero se duda de la medición.*

**Lo que NO se ha añadido, y por qué.** De los 48 iconos se usan 5, y ahí se queda: ya no hay
ninguna marca muda que sustituir —las viñetas de 04 eran la última—, así que los 43 restantes
entrarían de adorno. Y del color, nada: el dueño cerró ese tema, y además **verde y rojo son
colores de estado** (éxito y error) y su cero es correcto en una página sin formulario, igual que
las cero superficies de Azul Muro, cuyo papel escrito es «enlaces, botón fantasma y anillo de foco».

## Vestido · los movimientos y las poses (8 sep 2026)

**El bote del logo, hecho.** `pjpbote` copiado **literal de `Microanimaciones`** —sube 7 con la
curva de salida y **cae con `caida`**, que es la única del sistema que acelera— y en **una sola
pasada**, porque *«el logo bota una vez, sin bucle»*; el documento lo demuestra en `900ms infinite`
y el infinite no viaja. ⚠ El `pjpBote` que arrastré de los helmets de las secciones era **otra
cosa** (una entrada con aplastado) y **no lo usaba nadie** (×0): sustituido por el del sistema.

**Bota cuando LLEGA**, no al cargar: se dispara al cruzar la mitad del recorrido, que es donde
aparece el armazón — en «Entrada» no hay logo, así que no hay nada que botar. Verificado:
`iterations: 1`, `both`, y **no se repite** al volver a cruzar. Y la guarda de
`prefers-reduced-motion` va **en el JS**: la animación la pone el script, así que la regla del
helmet —que apaga `[data-invita]`, `[data-bucle]` y `[data-entra]`— no la alcanza.

⚠ **Los otros dos movimientos necesitan una decisión, no código.** Los dos están especificados al
detalle y sus valores cuadran con los tokens:

| Movimiento | Keyframe | Duración · curva | Desplazamiento |
|---|---|---|---|
| `pjpsello` | de `-34px rotate(-16deg) scale(1.35)` a `0 rotate(-6deg) scale(1)` | 420 · **`lona`** | `desplazamiento.sello = 34` ✓ |
| `pjpentra` | de `14px` y opacidad 0 a su sitio | 320 · **`bote`** | `desplazamiento.entrada = 14` ✓ |

Los dos piden **detección de entrada en pantalla** (un `IntersectionObserver`, que retiré del
recorrido a propósito porque el scroll ya lo resolvía). Y el sello arrastra una restricción del
token que **es una decisión de diseño**: la curva `lona` está marcada **«máx. uno por pantalla»**, y
en escritorio los **dos sellos de 01 comparten pantalla** (las tarjetas van a la par en 496+496).
O solo anima uno de los dos, o 01 no anima en escritorio y el sello se reserva para 04. **No se
elige por defecto.**

⚠⚠ **Me equivoqué al decir que las poses no se podían vestir.** Escribí que faltaba el material
porque `client-kit.svg` no está en el proyecto — y es cierto que no está—, pero **el sprite es solo
el envío a producción**: `Elementos Fachada` dibuja sus **104 piezas en línea**, con sus paths y sus
rellenos. El material estaba delante. *Un contrato de entrega no es la ausencia de la pieza.*

**Puestas las dos que el contrato declara para la portada** —las claves son cerradas y solo dos son
de aquí; `slot-normas-*` son de `/normas` y `slot-vacio-sin-franjas` del flujo de reserva—:

**`slot-zonas` · mancha B1 detrás del titular de Zonas.** Copiada de su marcado, 229 × 229, rotada
**−6°** (dentro del rango de las manchas). ⚠ **No va en su color**: el kit la da en Azul Muro y el
titular es tinta — **tinta sobre Azul Muro da 2,61** y no pasa. Va en **Nube**, que es la palabra
del sistema para «relleno inerte», y con eso el titular queda en **15,17**. Una mancha decorativa
detrás de texto es exactamente eso: inerte.

⚠ Y un fallo de pintura **que tuve que arreglar tres veces**: un absoluto con `z-index:0` pinta en
el paso 8 del algoritmo, o sea **por encima** del contenido en flujo sin posicionar (pasos 4 y 7),
así que la mancha salía encima del texto. La cura es `position:relative` en los tres hermanos de
cada cabecera — y ahí estuvo el error: lo apliqué **con un regex** que pedía `font-family` seguido
de `font-size`, y el titular de escritorio lleva `font-weight:400` **intercalado**, así que no casó.
Mi propio log dijo «4» donde debían ser 6 y no lo comprobé. Luego la comprobación *también* falló,
por el regex contrario. Verificado al final **en el DOM**, que es lo único que vale: los seis en
`relative`, tres por rama.

*Un contador que no cuadra con lo esperado es el defecto, no un detalle del log.* Y *una regex no
verifica una regex: eso lo hace el DOM.*

**`slot-tarifas` · friso, pies en la misma línea.** ⚠ El contrato pide **tres figuras del grupo G**
y **son dos**: contadas las poses, **G1 se repite en G3, G4 y G5** (mismo path, distinto relleno) y
**G2 en G6**. El grupo G tiene **dos poses distintas**, y la regla del friso dice *«nunca repitas
pose en la misma fila»*. Así que van **dos**, con los rellenos del kit —cian y tinta— y los pies
alineados (verificado: los dos a 1.632). **Tres es imposible sin sacar una pose de otro grupo, y eso
sí sería inventar.**

**Medido en reposo**: 01 **1.288** (+32) · 02 **816** (+148) · portada en **10,77 pantallas** con
**191 px** de margen bajo el techo de 11. ⚠ Y una nota de método: la primera medición dio 10,50
porque la tomé con la **página desplazada** y el hero minimizado —320 px menos—. *Una cifra de alto
se mide en reposo o se dice en qué estado se tomó.*

## Vestido · icono de producto, y un defecto de contenido que salió al mirarlo (8 sep 2026)

**Icono de producto en 04.** La lista de «qué incluye» llevaba una **viñeta cuadrada de 8×8** por
línea, que no dice nada. Ahora cada línea lleva el icono del sistema que **nombra la cosa**:
`ui/reserva` para la zona reservada, `ui/tapas` para la comida y bebida, `ui/calcetines` para los
calcetines. A **20 px** y con el color que ya tenía la viñeta —lima 800 sobre el pack blanco, lima
500 sobre el de tinta—, así que no entra ningún color nuevo. Seis en móvil y tres por pack en
escritorio; medido: 20×20, todos pintados, **cero ids duplicados** y **cero viñetas** restantes.

Dos detalles de oficio: el `<mask>` de `ui/calcetines` trae un **id** dentro, así que en móvil cada
instancia lleva el suyo y en escritorio —donde vive dentro del `sc-for` de los dos packs— **el id
sale del dato** (`k.maskCalc`). Dos ids iguales en un documento no son válidos. Y la lista de
escritorio pasa de `sc-for` a tres filas literales, porque **un icono es marcado y no cabe en un
hueco**; lo único que sigue siendo dato es el nombre de la zona.

**Considerado y descartado**: poner icono en los complementos de 02 (`+ Calcetines`, `+ Hora
extra`). El `+` dice «añadible», que es información; un icono lo sustituiría por menos.

⚠⚠ **Y el hallazgo gordo, que apareció en la captura y no en el código: 04 publicaba los precios
de ejemplo.** Mostraba **11 €**, **13 € en tarifa especial** y **señal 30 €**, cuando el catálogo
—y `CLAUDE.md`, que lo fija y advierte expresamente de que «la página publicaba otros»— son
**14,95 · 15,95**, especiales **16,95 · 19,95** y señal **50 €**.

**La causa es del montaje, no del diseño.** La lógica de 04 tenía los valores reales en sus
`??`; lo que publicaba eran los **defaults del panel**, que copié tal cual de `Cumpleanos PJP` —
donde el panel guardaba los valores de ejemplo del documento de exploración— y **el panel gana
sobre el fallback**. Corregidos los cinco. Verificado en el DOM: 14,95 · 16,95 · 15,95 · 19,95 ·
señal 50 €.

**Auditados los 32 ajustes, panel contra lógica**, para no dejar más de este tipo: **6 choques**,
5 eran los precios y quedan corregidos. El sexto se declara y **no se toca**:

⚠ **`control` · panel «Puntos» · lógica «Puntos y flechas».** Con «Puntos», 06 en móvil sale con
**5 puntos y 0 flechas**, mientras el escritorio —transcrito de `5b`— lleva **flechas + puntos**.
Las dos superficies no dicen lo mismo, y `CLAUDE.md` describe 06 como *«una opinión a la vez,
flechas + puntos»*. Pero «Puntos» es un valor que el dueño tiene **guardado en el panel**, así que
la decisión es suya: o vuelve a «Puntos y flechas» en las dos, o se declara que en móvil el
carrusel va solo con puntos.

*Un default de panel no es un comentario: es lo que se publica.* Y un valor de ejemplo heredado del
documento de exploración no se ve leyendo la lógica, porque la lógica tiene el bueno.

## Los tres cabos que quedaban abiertos, cerrados (8 sep 2026)

**1 · El radio 14 del aro del par → 16.** Estaba fuera de la escala cerrada de cuatro
(0 · 10 · 16 · 999). El 14 era el concéntrico *geométrico* de un 10 con 4 de aire, pero el sistema
ya resolvió este caso en la dirección contraria y **eligió la escala sobre la geometría**:
*«Tarjeta 16 con 8 de aire → foto a 10, nunca un 8 inventado»*. Hacia fuera, el escalón
inmediatamente superior de 10 es **16**. Cuatro aros corregidos. Medido: **cero radios fuera de la
escala** en toda la página (18 × 16 · 24 × 999 · 22 × 10).

**2 · El titular de 06 → 6 palabras.** *«Lo dicen los que ya han venido»* eran 7 contra el 3–6 de
`Voz PJP`. Fuera el «ya», que no añade nada que no diga ya «han venido». Los ocho, contados:
**5 · 6 · 5 · 3 · 5 · 6 · 5 · 5** — ninguno fuera de rango.

**3 · El anillo de foco del mueble flotante entra hacia dentro.** Era el último defecto declarado:
*«un aro por fuera se dibuja sobre el FONDO, no sobre el botón»*, y en una pieza que flota el fondo
es lo que pase por detrás — **2,62 y 1,49** medidos—. En esta familia el anillo pasa a
`outline-offset:-5px`, o sea **sobre la superficie opaca que la pieza ya trae**. Y su color es
**tinta**, que es el único que aguanta las dos superficies de la familia: **16,6** sobre el blanco
de los muebles y, sobre el naranja de comprar, **el mismo par que ya usa su propia etiqueta**.
Divergencia declarada contra `componente.foco`, que da un color por superficie de *página* y no
contempla un mueble que viaja por encima de varias. Marcado con `data-mueble` en los tres muebles
fijos (cabecera, racimo y barra); verificado: tinta 3 px a −5 sobre blanco y sobre naranja.

**Y dos cosas que he decidido NO tocar**, aunque las vi al revisar la gramática:

- **«Cumpleaños / El cumple, resuelto»** y **«Dudas / Lo que más nos preguntáis»** repiten palabra
  o sinónimo entre rótulo y titular. Miré los ocho pares por si el problema era «nombres contra
  preguntas» y **no lo es**: lo que importa es que rótulo y titular no digan lo mismo, y los otros
  seis pares son complementarios. En estos dos el titular sí añade algo —*resuelto* es la promesa,
  *lo que más* es la frecuencia—, así que es un eco léxico, no una redundancia de sentido.
  **Es copy aprobado y no se churnea por gusto propio**: queda anotado para el dueño.
- **Pasar los cuatro rótulos-nombre a pregunta** (Cumpleaños · Reseñas · Visítanos · Dudas).
  Probado sobre el papel y **se descarta**: «Dónde y cuándo» choca con el titular de 07 y «Lo que
  preguntáis» con el de 08. La mezcla de eje y tema en los rótulos no es un defecto; forzarla a
  pregunta sí crea uno.

## Los rótulos pierden el número y los titulares se igualan (8 sep 2026)

**Dos decisiones del dueño, aplicadas en las dos superficies.**

**1 · Fuera la numeración.** Los ocho rótulos pasan de `01 · Para quién` a **`Para quién`**. 18
sustituciones en los rótulos visibles y una más en la señal de scroll del hero, que también la
llevaba. Y el **menú pierde su columna de números** —la de 24 px con `{{ s.n }}`— junto con el
campo `n` del array: *un número que solo existe en el menú no ordena nada*. Comprobado: **cero**
`0N ·` visibles en la página; los que quedan están dentro del `<script>`, en comentarios.

**2 · Los tres sustantivos pasan a frase.** El criterio no es de gusto: **el rótulo ya dice el eje
de la pregunta**, así que el titular no lo repite — dice la promesa. Las ocho quedan así:

| Rótulo | Titular |
|---|---|
| Para quién | **Cada uno tiene su zona** |
| Cuánto | **Una hora, dos o el día** |
| Qué hay dentro | **Salta, trepa y déjate caer** |
| Cumpleaños | El cumple, resuelto |
| Antes de venir | Tu registro es este QR |
| Reseñas | **Lo dicen los que han venido** |
| Visítanos | Dónde estamos y cuándo abrimos |
| Dudas | Lo que más nos preguntáis |

Descartado para 02: **«Lo que cuesta tu día»**, que es lo que dibujaba el marco `1c`. Con el rótulo
en «Cuánto» sería decir *cuánto* dos veces.

⚠ **Y un fallo de método propio, del que sale la regla.** Escribí primero «Una hora, dos o el día
**entero**» —**7 palabras**— porque **inferí** la longitud de los cinco titulares que ya había
(«3 a 7») en vez de abrir el documento que la gobierna. `Voz PJP`, línea 37, la tiene escrita entre
las cuatro reglas de tono: **«Titular de 3 a 6 palabras»**, y CLAUDE.md nombra ese documento como
la autoridad de «longitud de titulares». Corregido a **«Una hora, dos o el día»** (6), que además
enumera los tres productos que la sección vende de verdad. *La longitud de un titular no se deduce
de los titulares que hay: se lee de `Voz PJP`.*

**Los ocho, contados**: 5 · 6 · 5 · 3 · 5 · 6 · 5 · 5 — ninguno fuera de rango.

✅ ~~06 incumple el 3–6~~ **corregido** (ver el bloque de los tres cabos). Era heredado, no de este turno: *«Lo dicen los que ya han venido»* son **7
palabras**. Es contenido aprobado, así que no se toca sin el dueño — pero ahora que la gramática de
los ocho se ha igualado a propósito, **queda declarado como divergencia**. El arreglo mínimo sería
quitar el «ya»: *«Lo dicen los que han venido»* (6), que no cambia el sentido.

**Medido**: los ocho titulares en **dos líneas**, ninguno desborda su columna (−16 en los ocho, que
es el relleno). La portada sube a **10,64 pantallas** con **307 px** de margen — los tres que eran
de una palabra ahora ocupan dos líneas.

⚠ **Y queda un cabo del mismo cambio**: al quitar el número, los rótulos son ahora una mezcla de
**preguntas** (Para quién · Cuánto · Qué hay dentro) y **nombres** (Cumpleaños · Reseñas ·
Visítanos · Dudas). Es la misma incoherencia de gramática que acabamos de arreglar en los
titulares, movida un nivel arriba. No se toca sin decisión del dueño: los cuatro nombres son
contenido aprobado.

⚠ **Y una nota que ahora miente**: `CLAUDE.md` fija la *«Numeración de la portada: el hero no lleva
rótulo numerado, así que los rótulos visibles corren uno por debajo de las nueve preguntas»*. Esa
regla queda **retirada** y hay que borrarla de ahí — es el archivo de instrucciones del dueño, así
que no lo he tocado yo.

## La cadencia · un momento a sangre en 03, en móvil (8 sep 2026)

**El diagnóstico, medido — y medido EN MÓVIL**: las ocho secciones tienen `piezasAntes = 0`, o sea
que las ocho abren exactamente igual, rótulo mono 12 y titular Bungee, ocho veces seguidas.

**Lo hecho: 03 llega al borde, EN MÓVIL.** El mosaico deja de ser una rejilla de 358 dentro de la
columna y pasa a **390 a sangre**, con el radio en **0** —un valor de la escala cerrada de cuatro—
por la misma razón por la que el hero de escritorio fue a sangre: un 16 contra el borde de la
pantalla no dice nada y cuesta una esquina de papel que tampoco. Es la regla del sistema tal cual:
*«ancho de contenido 1120, centrado; **el fondo a sangre**»*. **La cabecera se queda en la
columna**: la regla de «una sola cabecera para las ocho secciones» no se toca.

**En escritorio 03 NO sangra, y es deliberado.** Su mosaico es marcado propio, transcrito de `4b`,
y se queda dentro de la columna con su **radio 16**. Sangrar allí obligaría a sacarlo del contenedor
de 1208, y entonces a 1920 la foto grande —que ocupa dos de las tres pistas— mediría **1.280 × 853**:
más de una pantalla entera para una sola foto, con el techo de 11 contándose también allí.

**Y la cadencia de escritorio se considera resuelta por sus propios repartos**, no por un sangrado:
01 lleva la escala compartida más dos tarjetas de 496, 02 pistas de 352, 03 tres columnas, 04 · 05 y
07 dos de 544, 06 va en 4+8 y 08 en 5+7. En móvil el problema era que **todo tenía la misma forma**
—una columna de 358 de arriba abajo—, así que la apertura era toda la historia; en escritorio las
formas ya son distintas y las aperturas idénticas se leen como consistencia, no como monotonía.

⚠ *Una cifra medida en una superficie no describe la otra.* El `piezasAntes = 0` es de la rama
móvil, y esta nota lo decía sin cualificar — el error recurrente del proyecto, esta vez en la nota
y no en el diseño.

**Comprobado lo que la propia nota del proyecto exige**: la fila de las dos veladas **sigue siendo
una fila entera** (`veladasEnFilaPropia: true`), así que el degradado sigue diciendo «la sección se
acaba» y no «estas fotos están borrosas». Geometría: la grande **390 × 260** (3:2) y las cuatro
cuadradas de **189**, todas a radio 0. Coste **+53 px** (03 de 1.165 a 1.218); la portada queda en
**10,52 pantallas** con **404 px** de margen.

⚠ **Retiro la segunda mitad de mi propia propuesta.** En la revisión escribí «que **dos de las ocho
abran distinto**», y al releer el sistema **choca con una regla escrita**: *«Una sola cabecera para
las ocho secciones, y su nivel es Display L»*. Y las dos salidas que se me ocurrían tampoco valen:
poner la cabecera sobre una banda de foto es el patrón de `5a`, pero el dueño quitó la foto de 04;
y poner una sección sobre tinta lo prohíbe *«el color nunca es fondo de sección: tinta solo en
hero, cierre y pie»*. **El único lever de cadencia que el sistema permite es el sangrado**, y ya
está usado una vez — usarlo dos lo convertiría otra vez en patrón. *Una propuesta de revisión no
es una decisión: hay que confrontarla con las reglas antes de ejecutarla.*

⚠ Y un fallo de método propio: al recortar el marcado dejé un **`>` huérfano** dentro de la
rejilla, que se pintaba como texto entre la entradilla y el mosaico. Un `slice` contado a mano se
queda corto por un carácter y el navegador no se queja: lo vio la captura, no el código.

## El cabo suelto del escritorio, cerrado (8 sep 2026)

Era el único defecto declarado y abierto del proyecto: con el hero de escritorio a **pantalla
entera** no asoma nada, así que la única señal de que la página sigue era el movimiento.

**La señal no puede costar la pantalla entera** —eso ya lo decidió el dueño, con el coste
aceptado—, así que en vez de dejar asomar el rótulo de 01, **el rótulo se trae dentro del hero**:
un enlace mono de 12 con la flecha y el texto **«↓ 01 · Para quién»**, al pie de la columna de
1120. Nombra el destino, usa una cadena que **ya existe** —no hay copy nuevo— y cuesta **0 px** de
alto. Va en **Cian PJP**, que es el color de enlace sobre tinta según los tokens (6,85), y mide
**48** de alto.

⚠ **Y una corrección sobre la marcha**: puesta como hermana del par, la señal **repetía el rótulo
de 01** doce píxeles más abajo. Se ha metido **dentro del envoltorio del par**, que ya se apaga en
el primer medio recorrido: hace su trabajo en la primera pantalla y se retira. Verificado en
cuatro posiciones (0 · 120 · 168 · 300): opacidad 1 → 0,52 → 0, y **en ninguna comparte pantalla
con el rótulo que nombra**.

**No va en móvil**, y eso es lo correcto: allí el hero mide 620 de 844 y ya asoman 224 px —el
filete, el rótulo y el titular de 01—, así que el defecto no existe. Con `prefers-reduced-motion`
la señal está igual en la primera pantalla, que es cuando se necesita: es un enlace estático, no
una animación. Interruptor: `conSenalScroll`.

## El «ON» del hero es un interruptor (8 sep 2026)

El dueño lo pidió así: en «DIVERSIÓN ON», el **ON es un toggle que se enciende**. Y **la pieza ya
existía en el sistema**: `Iconos PJP` tiene los tres keyframes —`pjptogglepista`,
`pjptogglebulbo`, `pjptoggletexto`— con toda la mecánica escrita. ⚠ **Y no las usaba nadie**:
estaban huérfanas, o sea diseñada y sin construir. No se ha inventado nada; se ha construido lo
que había, con tres correcciones declaradas.

**Lo que dicen los keyframes del sistema, y se respeta:** la pista pasa de transparente con borde
`#5E666D` a **Lima Bote `#A3C21C`** relleno; el bulbo va de `#5E666D` a **tinta `#101418`**, con
**sobreimpulso** (recorre 19 y se asienta en 16,4 → el pico es 1,159 × el recorrido); y el texto
entra desfasado, después de que el bulbo se haya movido.

**Tres divergencias, y por qué:**

1. **Una sola pasada, no un bucle.** El original vuelve al estado apagado en el 100 % porque es la
   demo de un icono. En el hero eso sería un interruptor parpadeando —y el sistema ya decidió que
   «el logo bota **una vez**, sin bucle»—. Se queda encendido.
2. ⚠ **El keyframe del sistema incumple su propia regla de squash.** Pone `scaleX(1.14)` sin
   compensar, y `Microanimaciones` exige **sx · sy = 1,00** («un aplastado que adelgaza no
   conserva volumen»). Construido con `scaleX(1.14) scaleY(.877)` — 1,14 × 0,877 = 1,00. *El
   keyframe hay que corregirlo en `Iconos PJP` también.*
3. **La curva es `salida`, no `bote`.** El sobreimpulso ya está escrito en los porcentajes del
   keyframe; añadirle además la curva de rebote lo doblaría. Duración **420** (`rebote`, el techo
   general para una confirmación) y arranque a **360** (la cascada declarada: 90 · 180 · 270 · 360).
   El 620 no vale: está reservado al salto del hero, que es el otro mecanismo de esta misma pieza.

**El estado en reposo es ENCENDIDO.** Las cotas base del elemento son las del final, y la animación
solo es la *llegada*: así con `prefers-reduced-motion` —donde el helmet mata `[data-entra]`— el
hero se pinta con el interruptor ya puesto, y nunca queda un «OFF» que nadie va a encender. Y el
titular sigue siendo texto: el `<h1>` lee **«Diversión ON»**, con el ON dentro de la pastilla.

⚠ **Cuarta corrección, y esta la pagué yo.** Lo construí con el ON en `.58em` **del titular**, o
sea derivado: y el recorrido interpola el titular de 42 a 34, así que el ON caía a **19,72 px** —y
Bungee tiene suelo escrito en **20**—. No era un transitorio: a partir de los 261 px de scroll el
hero se queda minimizado, así que ese 19,72 era el estado de reposo durante **nueve de las diez
pantallas**. Y de paso metía **cuatro tamaños nuevos** (44,08 · 30,16 · 24,36 · 19,72) en el mismo
turno en que la escala se había cerrado.

**Arreglado rebasando la pieza al propio ON**: el ON es un valor de la escala por superficie
—**Título, 26 en móvil y 36 en escritorio**— y la geometría de la pastilla va en `em` **del ON**,
no del titular. Las proporciones son las mismas ya verificadas, divididas por .58. Con eso el
interruptor **deja de encogerse con la minimización del hero**, que además se defiende mejor: *un
interruptor es un objeto gráfico, no tipografía que interpola.*

*Un tamaño derivado no está en la escala aunque su base lo esté.* Es el único texto del archivo
cuyo tamaño no era un literal, y por eso se escapó al cierre de la escala.

**Medido después**: la pastilla es **105 × 46** constante en los cinco puntos del recorrido
(0 · 168 · 261 · 600 · 3000), con el ON en 26 fijo, el bulbo a 3 px del borde derecho y 11 px de
hueco, sin solapes. La escala vuelve a **18 tamaños** —los mismos de antes del interruptor— y los
cinco que quedan fuera son las excepciones ya declaradas. Alto del hero **636**, los nueve aires a
**96** y la portada en **10,46 pantallas**: el interruptor no cuesta ni un píxel.

## El escritorio, montado en el mismo archivo · el marco (8 sep 2026)

Un solo archivo y **un solo punto de corte, 1024** —el del sistema, `componente.barra.retiraEn` →
`reticula.puntos.escritorio`; el 900 no existe—. El mecanismo **no es una hoja de estilo**: es
`matchMedia` en el estado y ramas `<sc-if movil>` / `<sc-if escritorio>` para lo que cambia de
estructura. Lo que solo cambia de medida se escribe en el nodo, como el recorrido.

**El recorrido es el mismo** —420 px, ventana 0,18–0,62— y solo cambian los extremos. Verificado en
cinco posiciones:

| scroll | hero | titular | racimo | relleno del par |
|---|---|---|---|---|
| 0 | 768 | 76 | 0 | naranja |
| 76 | 767 | 75,9 | 0 | naranja |
| 168 | 564 | 64 | 0,5 | a medio camino |
| 261 | 360 | 52 | 1 | **tinta** |

O sea: **el par se transforma, no viaja** —el relleno interpola de naranja a tinta en la misma
ventana que el alto y el titular— y el par del hero se apaga (1 → 0) mientras el racimo entra. El
mapa del naranja queda intacto: vive en el hero, que es donde se compra en la primera pantalla, y
el racimo es cromo. En escritorio **no hay barra**: se retira en 1024 y su sitio lo toma el racimo.

**Dos divergencias declaradas** contra los dibujos de `Escritorio PJP`:

1. **El racimo va fijo.** En `6a` vive DENTRO del hero minimizado, y montado así el escritorio se
   queda sin navegación en cuanto el hero sale de pantalla — 8 de las 10 pantallas. Va fijo, como
   el marco del móvil, y por eso el logotipo es el **de color** (por detrás pasa tinta y papel).
2. **La tira de cinco colores no va bajo el hero.** En `6a` aparece ahí, pero es el separador del
   documento de exploración: la tira es del **pie**, y ahí sigue. Misma decisión que en móvil.

**Un ajuste nuevo, `vista`** (Automática · Móvil · Escritorio): fuerza la rama para poder ver el
escritorio en un panel estrecho. El corte real sigue siendo 1024.

**Las ocho secciones, el cierre y el pie transcritos** (8 sep). La columna de escritorio es
**1208 = 1120 de contenido + 44 de margen**, que es exactamente lo que dibujan las tarjetas de
`Escritorio PJP` (`.dv-card--w` mide 1208 con 44 de relleno). Los **nueve aires a 144 exactos**,
medidos junta a junta; cierre→pie se queda en **44**, que es la composición interna del marco
—igual que los 40 del móvil—. **0 controles bajo 48.**

| Sección | Origen | Reparto |
|---|---|---|
| 01 Zonas | `1c` | 64 + 496 + 496 · la escala FUERA y compartida |
| 02 Tarifas | `2b` | pistas de 352 · las tres enteras, sin velo |
| 03 El parque | `4b` | tres columnas · la grande a dos pistas, las veladas en franja |
| 04 Cumpleaños | `5a` | 544 + 544 · **sin la foto de cabecera** |
| 05 Antes de venir | `3a` | 544 + 544 · la chapa del empleado AL LADO |
| 06 Reseñas | `5b` | 4 + 8 · vuelve la opinión al lado de la nota |
| 07 Visítanos | `3b` | 544 + 544 · «abierto ahora» a Título 36 |
| 08 Dudas | `5c` | **5 + 7** (ver abajo) |
| Cierre | `6b` | centrado, el juego debajo a lo ancho |
| Pie | `6b` | en fila, dos filas rotuladas, sin degradado |

**Una sola fuente por dato.** No se ha fusionado la lógica de `Escritorio PJP`: sus 35 KB
duplicaban los datos que la portada ya tiene (`datos02`, `mezcla`, `filas`, `festivos`, `dudas`,
las opiniones, los precios de 04). En su lugar cada dibujo se transcribió **renombrando sus huecos
a los valores que ya existen** — 07 no necesitó ni una línea de lógica nueva—. Lo único añadido es
lo que es **reparto y no dato**: `pistasEsc`, `mosEsc`, `packsEsc` y las tres filas de `EMPLEADO`.

**Tres defectos encontrados al medir, no al mirar:**

1. ⚠ **El dibujo `5c` nunca cupo.** «PREGUNTÁIS» en Bungee 52 mide **353** y la pista de cuatro
   columnas es **352** a 1120 —y **291** a 1024—. Por 1 px, que es justo lo que no se ve dibujando.
   Divergencia declarada: cabecera en **5 columnas** (448) y acordeón en **7** (640 ≈ 80 caracteres,
   muy por debajo de los 140 que motivaron el reparto). El nivel Display L 52 no se toca.
2. ⚠ **Las pistas fijas de 352 de `2b` desbordan por debajo de 1208**: 3 × 352 + 2 × 32 = 1120 pide
   la columna entera. Pasan a `minmax(0,1fr)`, que da exactamente 352 a 1120 y aguanta desde 1024.
3. ⚠ **El aire no se pone, se mide.** Con 120 de relleno los nueve aires daban **176** (120 + los 24
   propios de la sección + los 32 del anterior). Corregidos a 88, y a 120/112 en las dos junturas
   que tocan piezas sin relleno inferior.

**Dos interruptores resueltos como lo que son.** `conChapaEmpleado` y `conFoto` no son lo mismo:
el primero es un recorte del **presupuesto de móvil**, así que en escritorio la chapa va **siempre**
—medido, la sección da 722 con la chapa al lado contra 726 en móvil sin ella—; el segundo es una
**decisión de diseño** del dueño, así que 04 va sin foto **en las dos** superficies.

Y del cromo de los documentos, fuera: el filete de acento a la izquierda de `1c`, la caja «con la
chapa apagada» de `3a` y los rótulos de spec. Son explicaciones del sistema, no producto.

⚠ **Lo medido es a 924 de ancho**, que es el panel de revisión: por debajo del 1208 de diseño. Los
altos a 1208 serán menores (menos reflujo), así que **hay que volver a medirlos en una pantalla de
verdad** antes de escribir una cifra de pantallas de escritorio.

## Revisión de la portada móvil (8 sep 2026)

Auditoría medida sobre el DOM, no mirada. **Arreglado en el momento** (defectos del montaje, no
decisiones): el acordeón de 08 se pintaba en **Arial** —los `<button>` no heredan la fuente y el
acordeón no la declaraba; entró un `button,input,select,textarea{font-family:inherit}` en el reset—;
**dos rótulos de spec** que se colaron como contenido («vídeo · 4 a 6 planos · sin sonido» en el hero,
«lienzo · 150 · el castillo corre en demo» en el cierre); y el precio de los calcetines **partiéndose
entre el 2 y el €**.

**Pendiente, por gravedad:**

1. **El hero está vacío.** 636 px de la única pantalla que ve todo el mundo, con el texto del hueco
   a la vista. Ninguna otra mejora compite con esto.
2. ✅ **El pie ya no pide scroll lateral** (resuelto 8 sep). Tenía **701 px ocultos de 1.059** en la
   fila de destinos y **370 de 728** en la legal: dos tercios de los enlaces no existían en 390.
   ⚠ **Y anuncié que lo resolvía la pasada de escritorio, y era falso**: el pie en fila es el de
   escritorio; el del móvil seguía intacto. La causa no era el ancho sino `flex-wrap:nowrap` +
   `overflow-x:auto` con `white-space:nowrap` en cada enlace — nada podía envolver—. Ahora
   envuelven, y los **dos velos de desvanecido** se han ido: quitar el scroll y dejar el
   desvanecido promete un contenido lateral que ya no existe.
   Y de paso: el pie mezclaba **8 páginas con 5 secciones de esta misma página** en una lista
   plana y sin rótulo. En escritorio van en dos grupos rotulados porque hay sitio; en móvil, una
   sola lista convierte «Zonas» en un enlace que te devuelve arriba de lo que acabas de leer. Se
   queda el grupo que lleva a algún sitio, **con su URL de verdad** —y con eso mueren 13 `#pie`—.
   Medido: 4 filas → **2**, y el pie de 573 a **477**.
3. ~~Las cinco fotos de 03 no sobreviven al handoff.~~ ❌ **Falso, y el fallo fue mío**: leí la URL
   que `getComputedStyle` **resuelve** (`https://<sandbox>/uploads/header-125.png`) y la tomé por el
   origen. En el marcado son `uploads/header-125.png` y los cinco archivos están en el proyecto
   —comprobado—, así que sobreviven perfectamente: una ruta relativa a un archivo del repo es
   exactamente lo que un desarrollador quiere. *Un valor computado no es el valor escrito.*
   Lo que sí queda: van como `background-image` en divs, no como `image-slot` con nombre, así que
   el dueño no puede arrastrar un reemplazo encima. Si hace falta, se convierten en huecos.
4. ✅ **La escala de tipografía, cerrada** (8 sep). Eran **20 tamaños** distintos; quedan **18**, y
   los cinco que estaban a la deriva se han llevado a la escala de tokens:

   | Antes | Ahora | Dónde | Por qué |
   |---|---|---|---|
   | **44** | **34** | el precio de 02 | Era **más grande que el titular del hero** (42), que es Display XL y tiene límite de uno por página: una cifra grande es Display L. Y así coincide con el 52 del escritorio. |
   | 28 | 26 | la edad de los packs de 04 | Título móvil |
   | 24 | 26 | los nombres de zona de 01 y el titular de la ficha de 03 | Título móvil |
   | 15,5 | 15 | las filas de día de 07 (las dos superficies) | Cuerpo S |
   | 13 | 15 | la chapa de calcetines de 02 y el nombre del QR de 05 | Cuerpo S, el suelo de la escala de texto |
   | 13 | 12 | el contador de 06 | Etiqueta mono |

   **Los cinco que se quedan fuera tienen razón escrita**, y por eso no se tocan: **20** en Bungee
   («Bungee nunca bajo 20») ×8 · **10,5** en mono (el mínimo escrito es 10) ×16 · **24** en las ★
   (glifo ajustado a su caja de 24) y en Permanent Marker (`guino` no tiene nivel en la escala) ·
   **19** y **13** en los glifos `+` e `i` dentro de sus círculos de 32 y 24.

   Y dos detalles del mismo repaso: **las pestañas de 02 pasan a «Kids · Jump»** —manda la edad, y
   así dejan de contradecir a 01, que ya las ordenaba al revés en las dos superficies— y el suelo
   de la escala de altura dice **«0 m»** y no «0,00», que era formato de hoja de cálculo.

   ✅ Cerrados también el **radio 14** y el **anillo de foco del mueble flotante** (ver arriba).

**Sano**: 8 tarjetas blancas (una por sección) · 9 de 11 sombras duras (las dos difusas son el
mobiliario flotante, exento) · naranja en 4 sitios exactos, todos de comprar · 0 controles bajo 48.

**Lo que la hace parecer generada, y no es el color:** la **cadencia**. Las ocho secciones se
presentan igual —rótulo mono, titular Bungee, entradilla, tarjeta blanca— ocho veces, y entre el
hero y el cierre **no hay un solo momento a sangre**: todo es una columna de 358 con 16 de margen.
✅ Hecho: **el momento a sangre en 03** (ver arriba). ❌ **Retirado** «que dos de las ocho abran
distinto»: choca con «una sola cabecera para las ocho secciones». ✅ Hecha también la **gramática de los titulares** (ver arriba). Abierto —hoy hay tres sustantivos («Zonas»,
«Tarifas», «El parque») y cinco frases—. Detalles: 02 ordena **«Jump · Kids»** y 01 «Kids · Jump»
(manda la edad), y la escala de altura rotula el suelo como **«0,00»**, que es formato de hoja de
cálculo.

## El cian · análisis cerrado sin cambio (8 sep 2026)

El dueño preguntó por **Tiffany** (#0ABAB5) como principal. Descartado, y no por contraste —da
**2,19** sobre papel y **7,67** sobre tinta, las mismas reglas que el cian—: por **tres costes de
marca**. El **logotipo es cian** (`logo-pjp.svg` lleva #1AA9DE, #4FC0EA, #3AB8E5 y #26AEE1 dentro).
La **paleta no es una elección, es un registro**: los tokens fijan la masa del cian en **46 %**
medida en el mural, contra el 13 % de lima. Y la **distancia de tono** es de 44° (cian 231, Tiffany
~187): dos azules a 44° se leen como error, así que Tiffany tendría que *sustituir*, arrastrando
logotipo y mural.

⚠ **Y una corrección de método propia**: sostuve que «el cian está a 0 %» con una regex mal escrita
(buscaba `26, 174, 222`; el cian es `26, 169, 222`). Medido bien: **cian 1,96 %** de área (4
superficies, 3 textos), lima 1,73 %, naranja 1,22 %, amarillo 0,78 %. O sea **el cian ya es el color
con más superficie de la portada**; lo que pasa es que la página entera solo tiene **5,7 % de
color**. El 60/30/10 no está roto en el reparto: está roto en la cantidad.

Se exploraron tres rutas con archivos reales y se midió el cian de cada una: **A** «el cian como
voz» (1,96 % de superficie pero 15 textos en cian sobre la tinta que ya existe, que es el 35,8 % de
la página; coste cero), **B** «relleno grande» (11,79 %, las dos tarjetas de zona; ensucia el
territorio lima de Jump) y **C** «el cian ES Kids» (10,64 %, Jump intacto; la única que le da un
oficio al color y completa la regla que ya existía a medias). **Recomendación: C, con A encima si se
quiere el titular del hero en cian.** Decisión del dueño: **por ahora no se cambia.** Los archivos
de las tres variantes se han borrado; con estos números se rehacen en una pasada.

Defecto que las tres compartían y quedó aprendido: **el enlace en Azul Muro sobre cian da 2,62.**
Sobre una superficie teñida solo aguanta la tinta.

## La portada montada · móvil (8 sep 2026)

Archivo: `Portada PJP.dc.html`. **No es un dibujo nuevo**: cada sección es el marcado de su opción
aprobada, copiado tal cual — 01 `4a` · 02 `6a` · 03 `6a` · 04 `7b` · 05 `2a` · 06 `2a` · 07 `7b` ·
08 `1a` — y el marco son `1a` (hero), `1c` (barra), `1d` (menú) y `1e` (cierre y pie). La lógica de
cada archivo viaja con su sección; lo único escrito de cero es el recorrido.

**Lo medido en el DOM** (390×844, sonda propia, no aritmética):

| Qué | Valor |
|---|---|
| Alto con el hero entero | 9.304 px · **8.827 sin el pie = 10,46 pantallas contadas** |
| Alto con el hero minimizado | 8.927 px = **10,58 pantallas** |
| Techo del sistema | 9.284 px = 11 pantallas → **entra, con 457 px de margen** |

⚠ **Corrección del sujeto de la medida.** Las cifras de pantallas se venían midiendo sobre
`scrollHeight`, o sea **con el pie dentro**, y el techo no cuenta el pie: su razón escrita es
«una pantalla por pregunta, nueve preguntas más hero y cierre son once de suelo». El pie no es
ninguna de las once. Medido bien: **8.827 px sin el pie = 10,46 pantallas contadas**, con 457 de
margen. Con el pie el documento mide 9.304. Es el mismo error que el proyecto ya tiene apuntado
—medir sobre lo que el catálogo tiene y no sobre lo que la pantalla enseña—, esta vez cometido
con el techo: las cifras anteriores (9,86 · 10,23 · 10,45 · 10,86) también llevaban el pie dentro,
aunque entraban de todos modos.
| Secciones | hero 636 · 01 1.252 · 02 670 · 03 1.161 · 04 1.504 · 05 664 · 06 778 · 07 929 · 08 574 · cierre 504 · pie 335 |
| Aire entre secciones | **96 en las nueve junturas**, tinta a tinta |
| Controles bajo 48 px | **0** |

**El techo, resuelto (8 sep).** El dueño encendió las cinco piezas que la ruta 1b había apagado y
la portada se fue a **12,08 pantallas**, 911 px por encima de su propio techo. Coste medido de cada
una: chapa de zona **+307** · chapa del «18 más» **+253** · reloj de la fiesta + aviso INFO
**+404** · chapa del empleado **+370**.

Eligió apagar **la chapa de zona y el «18 más»** y **respetar las 11**. Las dos daban 560 y hacían
falta 911, así que se apagó también **la chapa del empleado**, que es la única de las cuatro que no
se pierde al hacerlo: en escritorio va **al lado** y no cuesta nada —722 con chapa contra 726 en
móvil sin ella—. Se quedan encendidos **el reloj de la fiesta y el aviso INFO** (04 es la sección
más alta de la portada, 1.504, y es donde el dueño quiere el detalle). Nada de esto se borró:
los tres siguen tras su interruptor.

⚠ **Y las junturas se rompieron dos veces más en el mismo turno**: al encender las piezas
(tarifas→parque 100, cumpleaños→antes 120) y al volver a apagarlas (tarifas→parque 92). Van
**cuatro**. Un interruptor no cambia solo su pieza: cambia el borde de contenido de su sección,
y ese borde es la mitad de una juntura. *Cada vez que se toca un interruptor, se remiden las dos
junturas de su sección.*

**El recorrido es uno solo** (420 px, ventana 0,18 → 0,62), verificado punto por punto:

| scroll | hero | titular | cabecera | par del hero | barra |
|---|---|---|---|---|---|
| 0 | 620 | 42 | 0 | 1 | fuera |
| 80 | 619 | 42 | 0 | 0,95 | fuera |
| 120 | 500 | 39 | 0,3 | 0,52 | fuera |
| 168 (t = 0,5) | 405 | 36,6 | 0,67 | **0** | **dentro** |
| 261 (0,62) | 300 | 34 | 1 | 0 | dentro |

⚠ **El par se transforma, no viaja** (`Escritorio` `6a`), y eso hay que **escribirlo**: dejar que el
del hero «scrollee y desaparezca» no basta. Medido antes de arreglarlo: una ventana de ~130 px de
scroll (y ≈ 168 → 290) con **dos rellenos naranjas en la misma pantalla**, contra la regla dura de
uno por pantalla. Ahora el del hero se apaga en el primer medio recorrido y la barra entra en el
segundo: son complementarios por construcción, no por suerte, y verificado en ocho posiciones.

Y la barra **se retira sobre Tarifas y sobre el cierre** (verificado a 2.000 y a 8.300): en los dos
sitios ya hay un relleno naranja en pantalla.

**Tres decisiones de montaje, declaradas** (lo que un dibujo dentro de una tarjeta de 390 no podía
contestar):

1. **La cabecera fija no es una barra: son dos piezas que flotan** — el logo y el icono del menú,
   sin fondo detrás (decisión del dueño, 8 sep; lo monté como barra de tinta y estaba mal). Es lo
   que ya decía la spec del marco: *«la barra del móvil sin fondo, con superficie opaca y sombra
   por pieza»*. El **logotipo va suelto, solo con sombra**, como en la landing publicada: es
   `marca/logo-pjp.svg` —la versión **de color**— a 46 de alto (125×46) con dos `drop-shadow`.
   La de color es la que resuelve el problema de fondo: por detrás pasa **tinta** (el hero) y
   **papel** (todo lo demás), y el logo blanco de `1a` habría desaparecido sobre papel mientras
   el negro se pierde sobre el hero. *Le puse una chapa blanca y sobraba: el logotipo ya trae su
   propio contraste.* El **icono del menú** sí conserva su superficie —es un control de 48×48, y
   el glifo es `ui/menu` de `Iconos PJP`: **tres barras**, la última corta. Estaba dibujado con
   dos barras inventadas. ⚠ Queda en pie el cabo suelto que el proyecto ya
   tenía apuntado: el **anillo de foco de un mueble flotante** se dibuja sobre lo que pase por
   detrás, así que aquí es el de papel (`#0A5C93`) y sobre el hero no llega a 3,0. Sigue
   pendiente de decidir si en esta familia el aro entra hacia dentro.
2. **La hoja de ficha de 03 pasa a estar anclada a la ventana.** Estaba en `absolute` dentro de la
   tarjeta y en una página de 8.632 px caía al fondo del documento — **medido: top 5.609 con la
   ventana en 540**, o sea invisible. Ahora es `fixed`, 390 de ancho, con velo a pantalla completa,
   y cierra con el velo y con Escape.
3. **La tira de cuatro colores bajo el hero no entra**: la dibujaba el estado de demostración de
   `1a` para separar el hero de una sección de pega. El eslogan tampoco: vive en el menú y en el cierre.

**Lo que el montaje NO trae**, y es a propósito: el escritorio, el vestido y el cajón. «Reservar» y
«Registrarse» no abren nada (decisión del dueño, sep 2026); los dos pares y el CTA del cierre están
con su aspecto correcto y sin destino.

**Para desarrollo**: los **55 ajustes** del panel son el contrato de datos —los precios, los totales
de atracciones, la nota y el recuento de Google, el horario, las dudas del panel, el consentimiento
de cookies— más los **siete interruptores** de las piezas que el presupuesto recortó
(`conChapaZona`, `conChapa18`, `conRelojFiesta`, `conAvisoInfo`, `conChapaEmpleado`, `conCarrusel`)
y `conBarEn03`, que es la pareja del bar pendiente de tu ✅.

⚠ **Un aviso de método**: el iframe de revisión está oculto (`document.hidden`), así que ahí **no
llegan los eventos de scroll, no corre `requestAnimationFrame` y las transiciones no avanzan**.
Tres veces pareció un defecto del diseño y era del instrumento: el recorrido «una pantalla tarde»
y el par doble «que no se abría» —medía 254 a los 520 ms porque la transición no había arrancado—.
**Un estado final se mide con las transiciones apagadas** (`*{transition:none!important}`) y el
scroll despachando el evento a mano; si no, se están midiendo fotogramas que el navegador no ha
pintado.

## Zonas · aprobada (sep 2026)

Archivo: `Zonas PJP.dc.html`. Cinco turnos de exploración; **la aprobada es `4a`**. Las demás se
conservan porque cada una guarda el rastro de una decisión, no porque se mantengan.

**Qué es 4a.** Cabecera de sección (`01 · Para quién` / «Zonas» / *«Manda la edad. Si no cuadra,
manda la altura.»*) y dos tarjetas-enlace, Kids y Jump. Cada tarjeta: foto 16:9 en hueco
arrastrable, sello de precio girado −6°, **escala vertical de altura con el eje nombrado**
(«altura» + «1,90 m» arriba, los demás números desnudos), la línea de 1,30 cruzando la tarjeta con
su chapa a la izquierda —sobre la espina, que es de quien es el número—, **banda de color =
territorio de la zona** y fila «Ver la zona X →». Medido a 390×844: **1,27 pantallas**,
tarjeta-enlace de **356×490**, cero desbordes.

- **Las tarjetas son la respuesta, no un selector.** No filtran la página. De aquí sale la regla
  nueva del Sistema de Contenido: *un control vive donde se ve su efecto*. Precios y juegos llevan
  su propia pestaña Kids/Jump donde toque, con el cambio delante de los ojos.
- **La tarjeta entera es el enlace.** `<a>` y no `<button>`: admite hijos de bloque y es
  navegación, no acción. Se aplasta como una pegatina: baja 3 con sombra 5→2 al pasar, baja 5 con
  sombra 0 al pulsar. En la maqueta los enlaces son **inertes** (`preventDefault`) porque las
  páginas de zona no existen; sin eso, tocar el hueco de foto metía un hash muerto en la URL.
- **Ningún relleno de acción en la sección**: el relleno es la barra fija. Aquí todo es fantasma.
- **La foto no descodifica la regla.** Contesta «¿es divertido?», no «¿puede entrar?». Se queda —la
  sección tiene que apetecer— pero el que explica es el titular, y el eje nombrado.
- **Rechazado: silueta medida contra la regla.** La idea es buena y el kit la respalda a medias
  («la pose se apoya, no flota»), pero (1) de las doce poses solo existen dos como archivo en
  `kit-inline.json`, las dos de adulto; (2) **todas las poses del kit son de salto, y a alguien que
  salta no se le puede medir** —cabeza en el aire por encima de su altura de pie, o sea eje que
  miente—; y (3) el 62% de G choca con el 1,30 de 1,90, que es 68%. Para hacerlo bien hay que
  **añadir al kit una pose de medición**: de pie, talón en el 0,00, sin rotación, adulto y niño,
  trazada del mural. Mientras no exista, no se monta.
- **Rechazado: escala lateral compartida por las dos tarjetas en móvil.** Cuesta 56px y se los
  quita a la foto, no al texto (264 → 260), así que el precio no es el ancho: es que una escala
  compartida **promete que la posición de la tarjeta es su tramo** y el contenido no puede
  cumplirlo; **fija el orden** de las tarjetas para siempre; **no se ve entera** (700–900px contra
  844 de viewport); y **acopla el tick a la copia** —una línea más de texto y se descuadra.
- **Escritorio: decidido y pendiente.** A partir de **900px** (donde el sistema ya retira la barra
  fija) las dos tarjetas pasan a **dos columnas de la misma altura** y la escala sale fuera,
  **vertical y compartida**: solo ahí se ven las dos a la vez y el 1,30 puede alinearse con una
  frontera real. No se hace sección a sección: es una pasada de página, porque a esa anchura
  cambian a la vez la columna de 1120, el aire de 240 y la barra.
- **Territorio por superficie (turno `5a`), vivo como alternativa.** Ninguno de los ocho colores de
  marca está libre para «territorio»: naranja es acción, amarillo es el sello, lima son las cifras,
  verde es éxito, magenta son los cumples. Así que 5a distingue las zonas con **superficie**: Kids
  en blanco, Jump en `Tinta 800` —que es literalmente «tarjetas y superficies» en tokens—, con lo
  que el territorio **no gasta ni un punto del 60/30/10**. Obliga a dos cosas que 5a ya cumple: el
  enlace cambia de color por superficie (cian 6,14 en tinta / Azul Muro 7,08 en papel) y **el
  anillo de foco también** (amarillo 10,09 en la oscura). Si algún día hay que distinguir zonas sin
  repetir la escala, el camino está documentado y medido.

## Reglas nuevas en el sistema · v1.9

- **Componentes · carrusel.** Cuatro condiciones y hacen falta las cuatro (tres o más piezas ·
  homogéneas · no comparativas · nada crítico al fondo) y **cinco trampas ya pagadas** en el
  producto: identidad compartida, enfocable sin anillo, flechas de 0×0, carga diferida en
  horizontal, y una parada sin ajuste que nace cortada. Con el precio del patrón dicho con la
  medida del propio producto: en el cajón **las once horas cabían y la tira enseña cuatro**.
  De paso, el especimen de la página **incumplía su propio titular**: las diapositivas medían el
  100% del ancho, así que la siguiente no asomaba. Ahora 86%.
- **Sistema de Contenido · control.** *Vive donde se ve su efecto.* Un control cuya consecuencia
  ocurre fuera de pantalla no se percibe. Y la nota de dónde se torció: «un selector, tres sitios»
  era un problema de **tres componentes distintos** preguntando lo mismo, no una petición de estado
  global que filtre la página.

## Decisiones del owner · movimiento (sep 2026)

- **Manda el documento, no el token viejo**: la escala pasa a los ocho tiempos que Microanimaciones
  demuestra (120 · 180 · 240 · 320 · 420 · 620 · 900 · 1400) y a sus cuatro curvas. De la tabla
  anterior solo sobrevivían dos tiempos y una curva.
- **La curva `entrada` (`.4,0,1,1`) era un error viejo**: aceleraba hasta el final, justo lo
  contrario de una lona. Sustituida por `lona` (`.2,1.56,.25,1`).
- **Las reglas duras (radios, escala de espacio) aplican al producto**, no al cromo de los
  documentos del sistema. Por eso en Microanimaciones se corrigieron el tile (20 → 16) y las
  franjas (8 → 10), que son especímenes, y se dejó el cromo como estaba.
- **Tinte ámbar al token** (`#2A2413` / `#4A4020`): ya lo usaban Microanimaciones e Iconos.
- **Sombra de hover al token** (`duraHover`, `2px 2px 0`): la pegatina baja 3 y la sombra pasa de
  5 a 2, así la suma sigue siendo 5 y el keyline no se mueve.
- **Fuera la animación de «pulso»**: estaba escrita, en `rgba()`, y sin aplicar a nada.

### Tercera pasada: los huecos de demostración

- **Faltaba el especimen de salida.** El primer principio es «entra rebotando, sale limpio» y en la
  página no se cerraba nada: los cinco usos de 180 eran hovers. Hay un modal que entra en 320 con
  bote y se va en 180 con salida — y se desmonta 180 ms después de pulsar, para que la salida exista.
- **El desfase de los botes (120 y 240) no salía de ningún token.** Bajan a 90 y 180. El
  `desfase: 90` es ahora el único desfase del sistema, en cascada y como fase de bucle.
- **`caida` solo aparecía dentro de animaciones compuestas.** Ahora se demuestra sola: la misma
  bola, la misma distancia y el mismo tiempo, con salida y con caída al lado. Es la demostración de
  por qué la curva existe.

### Segunda pasada: la auditoría numérica

- **Las dos curvas de rebote eran la misma.** Declaraban 8% y 16% de sobreimpulso; medidas, las
  dos daban **9.8%** (compartían el punto de control 1.56). `lona` pasa a `.2,1.81,.25,1` = **+18%**,
  que es 1.85× el bote. Ahora la jerarquía «la lona es el rebote grande» es verdad.
- **El pico ya no se declara: se mide** sobre el propio bezier, como los ratios de contraste.
  `movimiento.sobreimpulso` desaparece de los tokens por eso.
- **Vuelve una quinta curva, `caida` (`.4,0,1,1`).** Es la que retiramos por tener mal nombre, y al
  quitarla el sistema se quedó **sin nada que acelerase**: en una marca cuya física es caer sobre
  una lona, todo frenaba. Los tres botes ahora suben frenando y **caen acelerando**.
- **El movimiento reducido no estaba cumplido.** La media query solo apagaba `@keyframes`, y había
  seis hovers con `transform` en transiciones en línea que seguían moviendo masa. Se apagan con
  `[data-mueve]`, conservando el cambio de color y de sombra.
- **La barra con movimiento reducido mentía**: se quedaba al 38% por la izquierda, o sea «38% hecho».
  Ahora se llena y se atenúa, igual que con el interruptor.
- **Squash y stretch conservan volumen** (1.15/0.87 y 0.90/1.11, producto 1.00). El aterrizaje de la
  cascada adelgazaba el objeto un 15%.
- **240 y 320 no tenían especimen** en el documento que existe para demostrarlos. Ahora sí: entrada
  de tarjeta y cambio de pestaña.
- **Reglas que la página incumplía**: «máximo dos a la vez» pasa a «dos focos» (la cascada mueve
  diez piezas y es un aviso), y el techo de 420 se dice **por elemento** — la cascada suma 780 de reloj.

## Decisiones del owner (sesión anterior)

- **Una sola capa**: se documenta PJP, no roles de producto + paquete.
- **Superficies**: papel continuo de arriba abajo; tinta solo en cabecera/hero y cierre/pie.
- **Foco**: por superficie — amarillo `#F5C400` en tinta (11,26), Azul Muro `#0A5C93` en papel
  (6,43). El amarillo sobre papel da 1,49: invisible.
- **Área táctil**: 48px, sin excepciones, tampoco en móvil.
- **Contenido 1120**, centrado, fondo a sangre. Se diseña a **390**.
- **Aire entre secciones 240 / 160**, uniforme.
- **Escala de espacio con valores fijos** (no multiplicativa).
- **Radios: cuatro** — `0 · 10 · 16 · 999`. Concéntricos: interior = escalón inmediato inferior.
- **Tres tamaños de botón** que nunca bajan de 48: cambia el relleno, no la altura. Campos a 52.
- **Los controles del cajón se documentan ahora**, no al diseñar el SPA.
- **Guardas por componente**: sí, una línea por pieza.
- **Iconos**: masa sólida en rejilla 24, usada a 20 · 24 · 32. Caja de tinta 20 de 24, centrada.
- **De la hoja de identidad solo se adoptan dos piezas**: la entrada con troquel y el calcetín
  antideslizante de perfil. **La firma no se aplica al set entero.**
- **Calcetín en tres tallas**: 24 simplificado, 56 con detalle, 96 con «PJP» calado en el puño.
- **Kit de fachada**: fuera el grupo D entero. Presupuesto **1 mancha grande + 2 pequeñas**,
  trama **solo en tarjetas vacías**, **12 poses libres** elegidas por composición, cero pintura
  debajo de un párrafo. Sprite al final.

## El contrato del sprite (las claves las declara el producto)

Van en `client-kit.svg`: `slot-zonas` · `slot-tarifas` · `slot-normas-registro` ·
`slot-normas-calcetines` · `slot-vacio-sin-franjas` · una `zone-<slug>` por zona.
**No van**: `--deco-blob-a`, `--deco-blob-b` y `--deco-tag`, que ya viajan instaladas por CSS.
Reglas del fichero: nada de `fill` clavado · sin `<style>` interno · sin texto ni referencias
externas · `viewBox` en cada símbolo · composiciones con `<g transform>`, **nunca** con `<use>`
interno. Una ranura sin pantalla se cae (precedente: `slot-cumple`).

⚠ **Dos ranuras están en el filo, y lo decide la pasada de vestido.** `slot-normas-registro` y
`slot-normas-calcetines` se declararon para la sección de normas, que es **05 Antes de venir** — y 05
quedó cerrada **sin una sola pieza del kit**: su única imagen es el QR, que no es del mural. Por la regla
de gobierno («ninguna pieza sin pantalla»), o la pasada de vestido les da sitio en 05, o se caen como
`slot-cumple`. No se decide ahora: se decide con el censo, que es el punto 3 de «Lo que queda».

## Cómo trabajar aquí (método, y está pagado con errores de esta sesión)

- **Los valores se leen de `tokens-pjp.js`**; los ratios de contraste se **calculan**, no se
  teclean. Un número escrito a mano en un documento que presume de medir envejece en silencio.
- **Un documento que explica valores tiene que leerlos, no repetirlos.** Microanimaciones los
  tecleaba: acabó con siete duraciones y cuatro curvas de las que solo dos y una coincidían con
  el token. Ahora las dos tablas salen de `movimiento` y el trazo de cada curva se dibuja con sus
  propios puntos de control. Lo que no puede leer el token es el bloque de `@keyframes` — una
  regla CSS no importa un módulo — y eso está dicho en la propia página: si la tabla cambia y la
  demostración de al lado no, ahí está la deriva.
- **Al retirar un valor del token, hay que buscar quién lo usaba.** Cambiar la escala dejó
  huérfanos 23 `.15s`, dos `.22s`, el spinner a `.7s` y una curva de carrusel en Componentes.
- **Un barrido mira todas las formas del problema.** En esta sesión se escaparon tres veces:
  hex escrito sin `#`, colores en `rgba()`, y tamaños en unidades distintas de `px`.
- **Si una sustitución no encuentra nada, no es que estuviera bien: es que buscaba mal.**
  Localiza por el TEXTO del nodo, no por su `style` completo, y vuelve a medir después.
- **Un instrumento que devuelve cero se comprueba antes de creerle.** `getBBox()` en un entorno
  que no renderiza devolvió 0 y escribió `scale(Infinity)` en 61 glifos.
- **Aplicar no es verificar.** Toda pasada mecánica termina midiendo el resultado.
- **Dos grises y no son intercambiables**: `#626A72` solo sobre papel, `#9AA1A8` solo sobre tinta.
  Es el fallo que más veces ha aparecido.
- **Una regla en un documento y su contraria en otro**: al retirar algo, hay que buscar quién lo
  citaba. Retirar el grupo D dejó seis referencias huérfanas en Colores de Marca.

## Método · lo que costó esta sesión (Zonas)

- **Un audit que lee el fuente no ve lo que rellena el navegador.** Los ocho pulsables llevaban
  `padding: 1px 6px` de la hoja del navegador: fuera de la escala y **ausente del código**. Las
  auditorías de espacio se hacen sobre **estilos computados**, no sobre el archivo.
- **`scrollWidth` miente con hijos rotados.** El sello a −5° daba 3px de «desborde» inexistente:
  la fila cabía exacta (176 + 8 + 88 = 272 = `clientWidth`). Para saber si algo cabe se suma
  `offsetWidth` —maquetación—, no la caja girada; y para saber si se recorta, se compara con el
  `overflow:hidden` más cercano (había 15px de holgura).
- **`:focus-visible` no se activa con foco programático en enlaces** (en botones sí). Una medición
  ingenua da un falso «correcto». Para medir el anillo: **clonar las reglas con una clase de
  prueba** y leer el computado.
- **Dos reglas de foco con la misma especificidad**: la genérica iba después y ganaba, dejando
  Azul Muro sobre la tarjeta oscura → **2,34 de contraste**, el fallo simétrico al del amarillo
  sobre papel (1,49). Se arregla **excluyendo** (`:not([data-superficie="tinta"])`), no solo
  reordenando: el orden se rompe en cuanto alguien añade una regla.
- **Un comentario que dice «esto no hace falta» caduca.** El bloque de `prefers-reduced-motion` se
  había retirado con la nota «medido: no hay nada que se mueva». Cuando las tarjetas empezaron a
  aplastarse, la nota seguía ahí y la regla no: ocho sujetos moviéndose sin respetar la preferencia.
  Una premisa medida se escribe con su fecha de caducidad, o no se escribe.
- **Un `<em>` dentro de una etiqueta en `display:flex`** se convierte en ítem propio y parte la
  frase en tres trozos separados. Todo el texto de una etiqueta flex va en **un solo** `<span>`.
- **React serializa los estilos con espacios y los colores en `rgb()`.** Buscar nodos por su
  `style` literal (`width:56px`, `#1AA9DE`) falla en el DOM: normaliza espacios y no busques hex.
- **Una caja de `<div>` no es su texto.** El solape sello ↔ texto salía positivo por caja y
  negativo de verdad: los divs son de ancho completo. Para colisiones reales, `Range` +
  `getClientRects()`.

## 02 Tarifas · cerrada (sep 2026)

Archivo: `Precios PJP.dc.html`. Seis turnos; **la aprobada es `6a`**. Los precios ya **no son
inventados**: salen del catálogo real de playjump.es (4 sep 2026).

**Los cinco productos.** Jump 1 h 12 € / 14 € en especial, y 2 h 18 € / 22 €. Kids 1 h 8 € / 10 €,
2 h 12 € / 15 €, y **todo el día 18 €, que solo existe de lunes a jueves**. Calcetines 2 €,
hora extra 3 € y solo en las de 2 h.

**Las siete decisiones que la cierran:**

1. **Carril con foco**, no rejilla ni pestañas: la enfocada a tamaño natural y las vecinas
   veladas. Ya es pieza del sistema (ver abajo).
2. **La tarifa especial da su precio entero** — «10 € en tarifa especial» —, nunca «+2 €». La web
   actual obliga a sumar y ése fue el fallo que más pesó en la auditoría.
3. **Qué es «tarifa especial» se explica al pulsar una «i»**, en globo, no en una etiqueta que
   repite «viernes, findes, festivos y vísperas» en cada tarjeta: viernes, fines de semana,
   festivos y vísperas.
4. **Los complementos van dentro de cada tarjeta**, con su unidad, porque cada producto puede
   tener los suyos.
5. **Los calcetines se dicen dos veces y a propósito**: complemento en la tarjeta, y **norma con
   su salida** en la chapa de zona («tráelos de casa o 2 € aquí, y te los quedas»). Es lo que
   convierte un coste inesperado en un aviso que se agradece.
6. **La chapa de zona cita a Zonas, no la repite**: nombre, edad, altura y **la misma regla de
   1,30 m al 68,4 %** que la sección 01, en miniatura. Y la línea de juegos **es** el enlace a la
   sección 03 — hacia delante, nunca de vuelta a Zonas.
7. **La de 1 hora manda** (el «desde», el precio que no asusta), aunque la más vendida sea la de
   2 h. Es un ajuste, no código: se cambia sin tocar el diseño.

⚠ **Edades sin cerrar.** El diseño usa las de Zonas aprobado (Kids 2–6 · Jump desde 7), pero la
web dice 4–7 y 8+ en tarifas y 1–12 y 6+ en dudas. **Hay que decidir cuál manda.**

⚠ **Los CTA dicen «Comprar»** aunque hoy la web mande llamar. Si las entradas siguen sin venderse
online, los tres pasan a «Llamar».

## 03 Qué hay dentro · cerrada (sep 2026)

Archivo: `Juegos PJP.dc.html`. Seis turnos; **la aprobada es `6a`**. La sección no es un
catálogo: es **la prueba de que el precio vale la pena**.

**La forma.** Mosaico de cinco fotos —la primera a lo ancho, cuatro cuadradas— y **las dos últimas
desvaneciéndose en el papel**: velo a `#F4F4F1` y no a blanco (contra el fondo, el blanco deja
canto), sin nombre, sin enlace y ocultas al lector de pantalla. Son textura que dice «hay más»,
no contenido a medio leer. Cierra una **chapa de tinta** con el «y N más» y **las dos puertas
dentro, nunca duplicadas fuera**.

**Las cuatro decisiones que la cierran:**

1. **Aquí no hay pestañas de zona.** Filtrar enseñaría medio parque, y la sección existe para lo
   contrario. La zona pasa a ser **dato de cada tarjeta**. Con esto el selector se queda donde
   hace falta: se elige en 01 y se puede cambiar en 02, donde se compara dinero.
2. **Un número por frase.** El total es promesa y va arriba; los desgloses son respuesta y viven
   en las dos puertas. Nada de repetir la misma cifra en cuatro sitios.
3. **Reparto justo**: la grande es de Jump y las dos nombradas de Kids. Antes se nombraban dos de
   Jump y una de Kids, y la madre de un niño de 4 años veía un solo juego de su zona.
4. **Se pulsa y abre hoja de ficha** (foto grande, zona y edad). Aprobado, con la recomendación
   escrita de **quitarlo de la portada**: cada toque de la portada debería acercar a comprar y
   éste devuelve al mismo sitio con la foto más grande. Su lugar natural es la página de las 23.

**Contrato de datos** — la sección no lleva ningún número escrito a mano. El panel da tres campos
y el resto se calcula: `atraccionesJump` (15) · `atraccionesKids` (8) · `ejemplosResto` (frase; si
va vacía, la línea desaparece). De ahí salen el «y N más» y los rótulos de las dos puertas; si
algún día se enseñan todas, **el «y N más» se retira solo**.

⚠ **Los cinco nombres son inventados** (Saltos libres, Piscina de bolas, Tobogán Cañón, Aro 360,
Carrera de lonas) y la frase de ejemplos también. Son datos de panel, no copia: se sustituyen sin
tocar el diseño.

## Numeración de la portada · regla

El hero **no lleva rótulo numerado**, así que los rótulos visibles corren uno por debajo de las
nueve preguntas del sistema de contenido: **01 Zonas · 02 Tarifas · 03 Qué hay dentro ·
04 Cumpleaños · 05 Antes de venir · 06 Reseñas · 07 Visítanos · 08 Dudas**. La lista «01 · La madre … 04 · El curioso» de `Arquitectura de Contenido` numera
**públicos**, no secciones: no se usa para rotular. (Este error ya se cometió una vez: Juegos
llegó a estar rotulada 04.)

## El mapa del naranja · cerrado

El naranja **solo significa comprar**, y por eso solo aparece en tres sitios: la **barra
flotante**, la **tarjeta enfocada de Tarifas** y **«Pagar»** en el cajón. La barra **se retira
mientras Tarifas está a la vista**, para que nunca haya dos rellenos a la vez: gana la acción
concreta sobre la genérica. Zonas, Qué hay dentro, Cumpleaños, Visita, Visítanos y Dudas van
**sin naranja**.

## Piezas nuevas del sistema · v1.11 y v1.12

- **Carril con foco** (`Componentes`, junto al carrusel; `tokens-pjp.js` v1.4 `componente.carril`).
  262 de ancho, 302 la destacada, 12 de hueco, 64 asomando. El velo es **cambio de superficie
  (Nube + 94 % de escala), nunca opacidad** — una cifra a media opacidad da 2,4 de contraste.
  **Controles a 52 y no a 48**, porque la escala los encoge a 48,9. El foco **se mide sobre los
  hijos**, no con un paso fijo. Y el manejador de teclado va **en el control, no en la tarjeta**:
  en el contenedor no llega, y quien tabula ve su botón en gris inerte.
  **Excepción declarada** (decisión del dueño): Jump tiene **dos** entradas y va en carril igual,
  contra la condición 01 del carrusel. No se «corrige» a rejilla.
- **Hoja de ficha** (`Componentes`, pieza 04). La hoja inferior, pero **para mirar y no para
  decidir**: papel en vez de tinta porque la foto manda, **sin botones de acción**, sombra difusa
  **hacia arriba** (`0 -18px 48px rgba(16,20,24,.34)`; la dura no vale: no está apoyada, está
  subiendo), asa de 44×4, radio 16 solo arriba, foto interior a 10. Con su regla de **cuándo no**:
  si no añade nada que la tarjeta ya diga, no se pone.
- **Dos contradicciones viejas corregidas** al documentarla: la pieza 04 decía «radio 24», que no
  existe en la escala cerrada (0 · 10 · 16 · 999) — ahora dice 16, que es lo que el código usaba;
  y el velo de la hoja de ficha se unifica en `.72`, el de la pieza, en vez de un `.62` propio.

## 04 Cumpleaños · cerrada (sep 2026)

Archivo: `Cumpleanos PJP.dc.html`. Siete turnos; **la aprobada es `7b`**. Es la sección que más
dinero deja y la única que hoy se reserva online de verdad. Su trabajo no es enseñar dos packs: es
que nadie la confunda con una entrada de 2 horas.

**La forma, de arriba abajo.** Cabecera (`04 · Cumpleaños` / **«El cumple, resuelto»** / *«Vuestra
zona reservada y la comida de los niños puesta. Solo hay que elegir la edad.»*) · **reloj de la
fiesta** en chapa de tinta · dos **tarjetas-enlace apiladas**, Kids en blanco y Jump en `Tinta 800`,
cada una con su chapa de nombre real, la edad en Bungee, tres viñetas de lo que incluye, **sello de
precio girado −6°** con el precio de lunes a jueves, la línea «X € en tarifa especial», un pie mono
con niños y señal, y la fila «Ver el cumple X →» · debajo, los **días de la tarifa especial una vez
para la sección**, la edad mezclada en **una línea**, el **aviso «i»** de cierre y «Reservar
cumpleaños» en relleno de tinta. **Cero naranja.**

**Las nueve decisiones que la cierran:**

1. **El valor antes del número.** La primera comparación que hace un padre es «entrada 2 h contra
   cumple», y el precio no la gana solo: lo que la gana es la zona reservada, el monitor, la comida
   y la bebida. Van arriba y en grande; el precio, después. *(Y con los números de prueba el cumple
   sale más barato por niño que la entrada de 2 h, así que decir el tiempo AYUDA a la comparación.)*
2. **El reloj de la fiesta, no la duración.** Cuando un producto dura un rato, el dato que se compra
   no es cuánto ocupa: es cómo se reparte. Tres tramos —saltos, mesa y comida, tarta y fotos— con
   filete lima como eje de tiempo, y el monitor dicho ahí («de principio a fin. Vosotros,
   sentados»). Contesta **«¿y cuándo comen?»**, que es la pregunta de verdad. Vive **fuera** de las
   tarjetas porque es igual en las dos.
3. **La tarifa especial también existe en los packs**, y se dice **igual que en Tarifas**: los dos
   precios **enteros**, «X € en tarifa especial», nunca un «+2 €». Los días —viernes, findes,
   festivos y vísperas— se escriben **una vez por sección**, no una por tarjeta. Ya es regla dura
   en `CLAUDE.md`.
4. **El nombre real del producto en la chapa**: «Pack Cumpleaños Kids» y «Pack Cumpleaños Jump», el
   mismo que verá en el cajón y en el correo. Una palabra por cosa.
5. **El titular dice la palabra.** Se rompe a propósito la norma que salió de Juegos («rótulo y
   título no repiten palabra»): la sección contesta *«¿puedo hacer aquí el cumple?»* y eso no puede
   vivir en un rótulo de 11 px. Que se encuentre pesa más que no repetir una palabra.
6. **La edad mezclada, una línea y sin cifras**: «¿Y si vienen niños de las dos edades? Se ajusta
   niño por niño en recepción». El número se le da en el formulario, cuando ya hay edades sobre la
   mesa — que es donde el sistema de contenido pone ese dato (profundidad 0).
7. **El sello girado sustituye a la cifra, no se suma.** No saturaba por ser sello: saturaba por ser
   un **segundo precio**. Puesto donde estaba la tarifa, ocupando su sitio, funciona. Y solo dice
   «por niño»: los días van enteros al lado (la regla de voz que cerró Precios — «V · S · D · fest»
   es jerga de tabla).
8. **El cierre es el aviso INFO de Componentes**, en su versión callada: filete, círculo amarillo con
   troquel y «Personalizamos cada cumple». Informa y **no pide nada** — no es pulsable, así que no
   necesita 48 px ni tooltip.
9. **Sin magenta.** Se probó como pastilla y se retiró: el magenta no es uno de los cuatro tonos de
   aviso del sistema y no se inventa un quinto. La sección queda en neutro + cian + lima.

**Lo descartado, con su motivo** (los turnos se conservan): la chapa «igual en los dos» —elegante,
pero el dueño quiere que cada tarjeta se explique sola— · el «no es una entrada» como chapa
separada, absorbido por las viñetas · la **ficha de invitado ya rellena** (turno 6a: Lucía, 7 años,
sin gluten, «le da miedo el foam»), que demuestra la personalización mejor que cualquier adjetivo
pero pesa demasiado para la portada — **guardarla para `/cumpleanos` y para el propio formulario** ·
el aviso INFO con caja de tinte cian (7a), por no meter una segunda superficie oscura bajo el reloj.

**Contrato de datos** — la sección no lleva ningún número escrito a mano: `precioKids` (11) ·
`precioKidsEsp` (13) · `precioJump` (15) · `precioJumpEsp` (18) · `senal` (30) · `duracion`
(«2 horas») · `minNinos` (8) · `maxNinos` (20) · `minSalto` / `minMesa` / `minTarta` (60 · 45 · 15) ·
`extrasAdultos` (interruptor) · `conFoto`. El reloj formatea solo («1 h», «45 min», «1 h 30»).

**Qué es dato y qué no** — la sección no lleva ningún número escrito a mano, así que al
implementarla en la web todo lo numérico sale del panel y **no se toca el diseño**:

| Dato de la maqueta | De dónde sale en el producto |
|---|---|
| Precio por niño, normal y especial | `prices (priceable, rate_type_id)` · `rate_types` normal/special |
| Tramos de edad de cada pack | `ticket_types.guest_age_family` + `guest_age_min`/`guest_age_max` |
| Mínimo y máximo de niños | `ticket_types.min_qty` / `max_qty` |
| Señal | `ticket_types.deposit_type` / `deposit_value` |
| Cuánto dura | `ticket_types.duration_min` |
| Qué incluye | `ticket_types.features` |
| Nombre del pack | `ticket_types.name` (ya está puesto: «Pack Cumpleaños Kids/Jump») |
| Foto de apertura | imagen de la sección editorial (`landing_services.image`) |

⚠ **Tres cosas NO son dato y hay que resolverlas aparte** — si se dan por data-driven, se
descubren en producción:

1. **El reparto del reloj** (saltos · mesa y comida · tarta y fotos) **no tiene campo**: el producto
   guarda la duración total, no cómo se parte. O se escribe como contenido editorial —el sitio
   natural es `landing_services.specs`, que ya es JSON traducible— o se queda quemado en la
   plantilla. Mientras no exista, los minutos de la maqueta (60 · 45 · 15) son de ejemplo, y la
   única regla es que **la suma cuadre con `duration_min`**.
2. **«La zona reservada para vosotros»** es una **promesa operativa**, no un dato: nada en el
   sistema dice si la zona de cumpleaños es exclusiva mientras dura la fiesta. Si no lo es, la frase
   se cambia.
3. **Los extras para adultos del formulario** no están construidos: el eje que los hace posibles
   (`product_addons.stage`) está **diseñado y sin escribir una línea de código**. El interruptor
   `extrasAdultos` los apaga; encenderlos sin la feature promete algo que el cliente no puede elegir.

⚠ **Y una que sí es dato pero está en conflicto**: las **edades**. La maqueta usa las de Zonas
(Kids 2–6 · Jump desde 7), el catálogo de pruebas trae 1–6 y 7–99, y la web actual dice otra cosa
en tarifas y otra en dudas. **Es un valor, no un diseño — pero hay que decidir cuál manda** antes de
cargarlo, porque de esos tramos sale el cobro del cumpleaños mixto.

### Lo que dice el producto sobre los cumpleaños (leído en `docs/`, para la página larga)

No se usa en la portada, pero es lo que hay que respetar cuando se monte `/cumpleanos` y el
formulario:

- **Los dos packs se enlazan por familia + tramo de edad**, extremos incluidos, y los tramos de una
  familia **no pueden solaparse**. De ahí salen los cumpleaños **mixtos**.
- **Reserva mixta**: si una edad declarada cae en el tramo del otro pack, la reserva se marca MIXTA
  y se ajusta **por niño** —+X € o −X €, o solo un aviso si cuestan lo mismo—. **Nunca se cobra
  online**: toda gestión de dinero posterior a la reserva se hace en el parque. Cada reserva guarda
  al nacer una copia de sus condiciones («el sello»), así que un cambio de catálogo **no mueve una
  fiesta ya vendida**.
- **Una edad sin tramo no es un hueco: es que no hay producto para ella.** El formulario no se puede
  cerrar, se le explica y se le pide que llame.
- **El formulario post-reserva** («Formulario de reserva») pide por niño **nombre, edad, alergia,
  observaciones y menú especial**, más un bloque general con el **nº aproximado de adultos**. Llega
  por correo con enlace firmado, es **editable hasta el día del evento** y luego queda en solo
  lectura. El operador puede reenviarlo, copiar el enlace o rellenarlo desde el panel.
- ⚠ **CORREGIDO (sep 2026): la hora extra SÍ existe en cumpleaños.** El catálogo real la vende como
  **«hora extra de sala»**, +4,00 € en Kids y +5,00 € en Jump. Lo que había escrito aquí —que era
  solo para entradas— era un error mío, y estuvo en pie hasta que el dueño pasó los dos bloques.
- Mínimo 8 y máximo 20 invitados, **señal fija de 30 €** y el resto en el parque; complementos del
  catálogo: calcetines, taquilla, tarta y monitor. ⚠ El dueño ha dicho que **monitor y calcetines
  van incluidos**, así que el catálogo real no coincide con la instalación de pruebas.

## 05 Antes de venir · cerrada (sep 2026)

Archivo: `Antes de Venir PJP.dc.html`. Dos turnos; **la aprobada es `2a`**. El turno 1 (`1a` y `1b`, los
cuatro pasos numerados) **se conserva en el archivo como histórico**: guarda el rastro de por qué la
secuencia se abandonó, y `1a` es el sitio al que volver si algún día la portada quiere su lista numerada. Es la sección con el riesgo
inverso al de Cumpleaños: puesta antes de tiempo es una barrera, y puesta aquí —cuando ya quiere venir— es
ayuda. El dueño contestó nueve preguntas antes de dibujar: cuatro pasos numerados, ayuda primero y norma en
pequeño, el justificante en una línea, un adulto dentro hasta los 14, el «¿hace falta reservar?» a Dudas, el
código dibujado y un enlace fino de cierre.

**La forma, de arriba abajo.** Cabecera (`05 · Antes de venir` / **«Tu registro es este QR»** / *«Sin firmar
el descargo de responsabilidad no se entra. Se hace una vez, en el móvil.»*) · la **tarjeta del QR** en papel,
con el icono de marca calado en el centro y su nombre **«Mi Play Jump QR»** debajo, fuera del recuadro, más
dos líneas: «Uno solo y siempre el mismo» / «Lo tienes en tu cuenta y en el correo de cada reserva» ·
**chapa de tinta** rotulada «Al escanearlo, el empleado ve», con tres filas de eje cian —cada una con su
**momento** en mono, su título en Bungee y el **✓ verde** a la derecha—: *Tu reserva* (al elegir la hora),
*Tu firma* (antes de pagar; «una sola vez, y no se vuelve a pedir. Tampoco en la puerta»), *Tus hijos*
(cuando quieras; «hasta los 14 tienes que estar en el parque con ellos») · el aviso amarillo de los
calcetines como **«lo único que no cabe en el código»** · el justificante en una línea y como oferta ·
«Ver todas las normas →» · y «Registrarse» en botón fantasma. **Cero naranja y cero relleno de acción.**

**Las seis decisiones que la cierran:**

1. **El recipiente no es un paso.** Nació como cuatro pasos numerados —lo que `Arquitectura de Contenido`
   prometía como «la única secuencia numerada de la web»— y el cuarto, «enseña tu QR», **era el sobre de los
   otros tres**. Puesto delante, la sección deja de ser una lista y pasa a ser un objeto con tres cosas
   dentro. ⚠ Consecuencia: 05 **ya no es una secuencia numerada**; es un inventario, que es lo que es. La
   promesa de Arquitectura de Contenido se queda sin casa, y está bien.
2. **El titular dice REGISTRO.** Es la palabra que trae en la cabeza quien llega mandado («me han dicho que
   me registre aquí»), que es el visitante 05 de Arquitectura de Contenido y el objetivo nº 1 del dueño. No
   aparecía en ningún sitio de la sección. Es la lección de Cumpleaños otra vez: que se encuentre pesa más
   que no repetir una palabra.
3. **El momento en vez del número.** Los `01 · 02 · 03` contradecían el ✓ de al lado, ordenaban algo que no
   tiene orden —reserva y firma pasan de una vez al pagar, los hijos cuando quieras— y chocaban con el
   `05 ·` del rótulo, misma etiqueta mono. En su hueco: **al elegir la hora · antes de pagar · cuando
   quieras**. Y el tercero **no** dice «antes de venir», que sería lo natural: repetiría el rótulo palabra
   por palabra, y además los tres son antes de venir.
4. **La pantalla del empleado, enseñada al cliente.** Demuestra en vez de mandar: el ✓ dice «ya está hecho»
   en vez de «hazlo». Y desmitifica el escaneo — se ven tres cosas, no tu vida. ⚠ Es lo único de la sección
   que puede malinterpretarse: quien entra por primera vez no tiene nada hecho. Se aceptó porque la cabecera
   de la chapa lo enmarca. Alternativa escrita y no usada: «En la puerta, con eso ya lo sabemos todo».
5. **Los calcetines se ganan el sitio con una frase.** «Lo único que no cabe en el código» los convierte de
   nota suelta en la excepción del objeto. Se dicen igual que en 02: «tráelos de casa o 2 € aquí, y te los
   quedas».
6. **El justificante ofrece, no exige** —«¿Viene un niño que no es de tu familia? Puedes mandar un enlace a
   sus padres para que firmen ellos, sin crear cuenta»— porque en entradas es **opcional**. Obligatorio solo
   en excursiones; hoy no en cumpleaños.

**Lo que el dueño corrigió del producto, y cambió el diseño:**

- **El QR es UNO y es de la cuenta**, no uno por reserva. Con él el empleado ve reservas, menores asignados
  y si ha firmado. La primera versión decía «te llega en el correo de la reserva», que confundía el sobre
  con la cosa.
- **Identificarse ocurre ANTES de pagar**, no «en el mismo paso de pagar».
- **Dos caminos, no uno**: online (reserva → cuenta → paga → correo) y en el parque (llega, se identifica
  con su QR, paga la entrada y entra). Los cumpleaños se reservan online **o los mete el empleado desde el
  panel**, y el correo sale igual.
- **Un adulto dentro hasta los 14 años.** Dato del dueño; no estaba en ningún documento del sistema.

**Reglas nuevas que salieron a los documentos** (v1.15 del índice): el recipiente no es un paso · un número
que ordena lo que no tiene orden miente · una sombra dura sin girar se lee como pulsable —el sello se salva
por su −6°, y rotar solo vale para manchas, cinta y sello— · y los ratios se calculan también cuando no son
de contraste.

**Contrato de datos** — `edadAdulto` (14) · `precioCalcetines` (2) · `terminoRegistro` (enum, «el descargo
de responsabilidad») · `conAmigo` (interruptor). Ningún número escrito a mano.

⚠ **Pendiente del dueño:** probar el código **con el lector del recinto** —tamaño impreso, zona de silencio y
distribución de teclado del *keyboard-wedge*, que es lo que exige `identidad-qr-puerta.md` §6 y que la suite
no mide—; y decidir si entra la media línea **«el mismo QR vale si compras la entrada allí mismo»**, que hace
el QR incondicional pero roza el «¿hace falta reservar?» que él mandó a Dudas.

⚠ **Y un público sin casa: las EXCURSIONES.** Ahí el justificante **sí es obligatorio**, y un profesor no es
ni el cliente ni el acompañante — no está contado en ninguna parte de la portada. No es una línea, es una
pieza: probablemente su propia página. `waiver-por-reserva.md` lo tiene construido y verificado.

## Método · lo que costó esta sesión (05 y el vocabulario)

- ⚠⚠ **La prosa del turno describió DOS VECES un diseño que ya no existía.** Al quitar los números, se
  actualizaron las filas y el `dv-meta`, y el texto del turno siguió diciendo «los números siguen ahí»;
  al cambiar el eje de lima a cian, el mismo texto y la etiqueta de la opción seguían diciendo «en verde»
  y «pegatina». ▶ **En este proyecto el texto del turno es el registro de la decisión**, no un adorno: es
  lo primero que lee el dueño para elegir. Un cambio de diseño se cierra tocando **cuatro sitios**:
  el marcado, el `dv-meta`, la etiqueta de la opción y la entradilla del turno. Si solo se tocan dos,
  el archivo discute consigo mismo.
- ⚠⚠ **Un ratio TECLEADO comparó una proporción lineal con un umbral de área**, y de ahí salió un
  «puede no escanear» falso que llegó al dueño: el icono mide 38 sobre 112 px de anchura (34 %), pero el
  presupuesto de corrección del nivel H se cuenta en **módulos** — 9×9 de 625 = **13 %**, holgado. ▶ La
  regla del proyecto («los ratios se calculan, nunca se teclean») **no era solo para el contraste**, y
  ahora está escrita en `CLAUDE.md` sin ese apellido.
- **Una regla general escrita en una opción, incumplida en la de al lado.** El `dv-meta` de 2a justificaba
  quitar la sombra dura con un argumento de sistema; 2b, en el mismo turno, la seguía llevando. Es el fallo
  que `HANDOFF` ya tenía fichado («una regla en un documento y su contraria en otro»), esta vez **dentro
  del mismo archivo**. ▶ Al escribir una regla en la nota de una opción, hay que barrer el turno entero.
- **Una respuesta del formulario caduca cuando la idea cambia.** El dueño pidió «cuatro pasos numerados» y
  el turno 2 se cargó la secuencia; mantener los números por respeto a la respuesta vieja fue lo que metió
  la contradicción con el ✓. ▶ Cuando el diseño cambia de forma, sus respuestas se **releen**, no se
  arrastran — y se le dice.
- **El dueño corrigió tres hechos del producto que la primera versión daba por sabidos** (el QR es uno y es
  de la cuenta · identificarse ocurre antes de pagar · el justificante es opcional en entradas). Los tres
  estaban en `docs/` y los tres se habían leído. ▶ Leer la spec no es entender la operativa: lo que hace
  el empleado en el mostrador no está escrito en ninguna spec, y eso se pregunta.
- **Una palabra prohibida por el sistema puede ser la palabra que el dueño quiere.** `Voz PJP` prohibía
  «descargo»; él lo usa y lo quiere. ▶ No se aplica en silencio ni se ignora en silencio: se nombra el
  conflicto, se decide, y **se corrige el documento** — que es lo que hizo la v1.14.
- **Un nombre nuevo se comprueba contra los nombres que ya existen.** «Justificante digital Jump» chocaba
  con **Jump la zona**, y «Mi Play Jump» para el QR chocaba con la cuenta. Un nombre bonito que colisiona
  crea un malentendido que no se desaprende.

## Vocabulario público · cerrado (sep 2026)

Decisión del dueño, y la tabla que manda está en `Voz PJP`. El motivo, con sus palabras: *«quiero usar
términos siempre los mismos y fácil de entender»*.

| En pantalla | Qué es | La trampa que evita |
|---|---|---|
| **El descargo de responsabilidad** | El texto que se lee y se acepta con una casilla, para uno mismo y para sus menores | Se dice **entero**. Y **sustituye la prohibición** que `Voz PJP` tenía sobre esta palabra |
| **Justificante digital** | El permiso que firman los padres de un menor que no es de la familia | **Sin apellido «Jump»**: Jump es una ZONA, y «Justificante digital Jump» se lee como el de esa zona |
| **Mi Play Jump** | La zona de cuenta: reservas, menores y QR | No es el QR. Dos cosas no pueden compartir nombre |
| **Mi Play Jump QR** | El código único de la cuenta, y **es el registro** del cliente | Se dice entero. No es «el carné» ni «el código de la reserva» |
| **Su día especial** | Lo que se rellena tras reservar un cumpleaños | Entonces **no** se le llama «formulario» en ningún sitio: ni en el correo, ni en el panel, ni al hablar |

⚠ Sin resolver: **«Pack»** sigue siendo el producto de cumpleaños («Pack Cumpleaños Kids/Jump», que es lo que
dice el catálogo). Conviven bien si «Su día especial» es lo de DESPUÉS; si el dueño quería que fuera el
producto, choca con «Pack» y hay que elegir uno.

## 06 Reseñas · cerrada (sep 2026)

Archivo: `Resenas PJP.dc.html`. Dos turnos; **la aprobada es `2a`**. Las reseñas se traen de Google en
vivo (Places API) y la spec del producto está leída y respetada: `docs/specs/google-reviews.md`. El dueño
contestó nueve preguntas antes de dibujar. Su trabajo, según el sistema de contenido, es la pregunta 07
—**«¿me fío?»**— y ahí las reseñas **confirman una decisión ya tomada**: no venden, y por eso la sección es
la más corta de la portada (**747,3 px = 0,89 pantallas** a 390×844).

**La forma, de arriba abajo.** Cabecera (`06 · Reseñas` / **«Lo dicen los que ya han venido»** / *«No las
elegimos nosotros: son las que Google pone primero.»*) · **chapa de tinta** con el 4,8 en Bungee 52 Lima,
las cinco estrellas y «320 opiniones» en texto · **una opinión a la vez** en tarjeta blanca: foto, nombre
enlazado a su perfil, antigüedad en mono, estrellas enteras en Amarillo 800, texto tapado a 4 líneas y
enlace a la reseña · **flechas de 48 + cinco puntos de 44×48**, con ← y → en el teclado · cierre «Ver las
320 opiniones en Google →». **Cero naranja.**

**Las siete decisiones que la cierran:**

1. **El límite de Google es el argumento, no la disculpa.** No se puede elegir qué reseña sale ni retirar
   una injusta (R1 de la spec). En vez de esconderlo, la entradilla lo dice: *«no las elegimos nosotros»*.
   Es lo que hace creíble un 4,8 — y es gratis, porque es verdad.
2. **La nota media solo existe si viene de Google.** Inventarla a mano sería atribuirle un número que no ha
   dado. Sin consentimiento, no hay cifra.
3. **Un decimal no se dibuja con cajas enteras** (regla nueva). El recorte va **por caja** —la quinta al
   80 % de la suya, 19,2 px de 24— y la pista vacía en Tinta 500 (3,49) para que se vea. Costó tres
   arreglos: la fila recortada escondía 3 px, el recorte por caja ya recortaba bien, y la pista en
   Tinta 600 daba 1,65 y las cinco se leían llenas.
4. **El titular dice quiénes son «ellos».** «Lo dicen los que ya han venido» — seis palabras, el techo de
   Voz. No son opiniones de internet: son de gente que estuvo. (Se parte en dos líneas a 32 px: 65,3 de alto.)
5. **El recuento sale del mono** (regla nueva). «320 opiniones» es el segundo argumento de la sección y
   estaba en 11 px: pasa a Hanken 700 a 15. La mono es para antetítulos, horarios y códigos.
6. **El enlace no promete lo que no esconde** (regla nueva): «Leer entera en Google» solo cuando el texto
   se corta; «Ver en Google» cuando no. Antes los cinco decían «leer» y cuatro no escondían nada.
7. **La antigüedad en vez de la fecha**: «hace 2 meses». Lo que hace creíble una reseña es que sea
   reciente. Formato nuevo en `Voz PJP`; en francés se queda mes y año, que allí es obligatorio.

**Lo descartado, con su motivo** (los turnos se conservan): el **logotipo de Google** en la chapa —estuvo
puesto, con su hueco de 56×18 y la variante de fondo oscuro, y lo retiró el dueño— · **1b**, la tira
igualitaria con las cinco asomando, que no destaca ninguna (coherente con R1) pero enseña una opinión de
2 estrellas al mismo tamaño que las buenas · **2b**, el dato antes del titular: medido, solo ahorra 9,5 px
y la sección arrancaría sin titular, rompiendo el ritmo de las otras cinco · y el sello **«verificado»**,
que no existe: Google no comprueba que quien escribe haya venido, así que ponerlo sería decir algo que la
fuente no dice.

**Contrato de datos** — `nota` (4,8) · `total` (320) · `cuantas` (máx. 5, es el techo de Places) ·
`control` (puntos / puntos y flechas / flechas y contador) · `fuente` (con consentimiento o sin nada).
Ningún número escrito a mano. Las cinco reseñas del ejemplo son **inventadas**, y una tiene **2 estrellas
a propósito**: es lo que significa que Google elija. Fíjate en lo que dice —los calcetines—, que es justo
lo que 02 y 05 ya cuentan por delante.

⚠⚠ **La sección DESAPARECE para un porcentaje real del tráfico, y es decisión suya.** El dueño contestó
que quien no acepte cookies vea opiniones escritas por él, y también que **no piensa escribirlas**. Sin
foto del autor no se puede enseñar ninguna reseña de Google —es obligatoria y vive en su servidor—, así
que a ese visitante no le queda nada. La maqueta lo enseña con el interruptor «Qué se está viendo».
**Tres opiniones dictadas una vez lo arreglan.**

⚠ **Atribución incompleta desde que se retiró el logotipo.** La del **autor** está entera —foto, nombre y
enlace al perfil en cada tarjeta—, que es la que exige la política de reseñas. La de **Google como fuente**
se queda en palabras: la entradilla y el enlace de cierre. Si al encenderlo hace falta la marca, vuelve
arriba de la chapa y es un hueco de 56×18.

⚠ **Una palabra sin decidir: «reseñas» o «opiniones».** El rótulo dice Reseñas y el cuerpo dice opiniones,
y `Voz PJP` manda una palabra por cosa. Google en español dice «reseñas». Hay que elegir una.

⚠ **Del lado del producto**, y son de la spec: el refresco va por **comando programado** y el scheduler
**no corre en staging** (`DECISIONES #115`), así que allí se dispara a mano · Redis está en `allkeys-lru`,
o sea que la caché corta **se evapora cuando le toque** y «no hay datos» es el caso normal · hay que
**ampliar `img-src`** en la CSP para los avatares, que relaja la política del sitio entero · y falta la
verificación en el **sandbox de Google con el `place_id` real**, que es lo único que queda para encenderlo:
el dueño ya tiene ficha verificada, `place_id`, clave de API y techo de gasto.

## Método · lo que costó esta sesión (06 Reseñas)

- ⚠⚠ **Dos respuestas del dueño se contradecían y no lo vi al leerlas.** Pidió que sin cookies se vieran
  las opiniones propias y, en la pregunta siguiente, que no piensa escribirlas. Lo detecté al dibujar el
  estado vacío, no al recibir el formulario. Cuando dos respuestas se cruzan, el sitio de decirlo es la
  respuesta al formulario, no el turno siguiente.
- **La misma estrella se rehízo tres veces, y solo la tercera medía lo que decía.** Primero recorté la
  **fila** de cinco al 96 %: el corte caía en 83,3 px cuando cuatro miden 69,4 y cinco 86,7 — 3 px
  escondidos, un 4,8 dibujado como un 5,0. Luego recorté **por caja** (19,2 de 24), que sí funciona, pero
  dejé la pista vacía en Tinta 600: **1,65** de contraste, o sea cinco estrellas llenas otra vez. La
  lección: un ratio no es solo cosa del texto — **el hueco de un dibujo también tiene que verse**.
- **Un atributo que el componente ignora no es una medida.** El hueco del logotipo llevaba `width`/`height`
  y el componente se pone al 100 % con proporción 3:2: se comió los 316 px de la chapa y la estiró a 305 de
  alto. Las medidas de un componente ajeno se ponen donde él las lee, y se **miden** después.
- ⚠ **La prosa volvió a describir un diseño que ya no existía** — el mismo error que costó 05. Al volver a
  las cinco estrellas quedó viva la frase que decía que la nota **no** llevaba estrella, doce palabras antes
  de anunciar que sí. Cuando el dueño hace volver una decisión, la prosa se reescribe entera, no se parchea.
- **La cifra del ahorro hay que medirla antes de usarla como argumento.** Vendí 2b como «unos 50 px menos»;
  medido, eran **9,5**. Una opción que reordena la sección se defiende por el orden, no por un alto inventado.
- **La ñ del nombre visible no la limita el nombre del archivo.** «Reseñas PJP» en la columna de nombres con
  el enlace al archivo ASCII, igual que ya hacía la fila de Cumpleaños.

## 07 Visítanos · cerrada (sep 2026)

Archivo: `Visitanos PJP.dc.html`. Siete turnos; **la aprobada es `7b`**. Contesta la pregunta 08 del
sistema de contenido, *«¿está abierto y cómo llego?»*. **No es el cierre**: el bloque de tinta con el
eslogan y el último «Reservar» es otra pieza y sigue pendiente — la primera pregunta del dueño esta
sesión fue justo esa, y la respuesta es que son dos.

**La forma, de arriba abajo.** Antetítulo `07 · Visítanos` · título **«Dónde estamos y cuándo
abrimos»** · entradilla *«Dos horarios: entre semana y de viernes a domingo»* · la **tarjeta del
horario** en blanco, que es el dato de la sección: punto de estado + `Hoy, domingo` en mono, el
estado en **Bungee 34** y su línea (`Hasta las 21:30` / `Abre a las 11:30 y cierra a las 21:30` /
`Mañana abre a las 16:30`), la tabla de **dos filas**, la línea *«Los festivos, como el finde»*, el
**aviso del festivo próximo** cuando queda ≤21 días, y el botón fantasma **«Ver los festivos del
año» / «+»** que despliega la lista **aquí mismo** · el **mapa** en 16:9 · la **dirección** en dos
líneas · *«El mapa lo pone Google»* · el **aparcamiento** · y nada más.

**Con el mapa cargado la sección no tiene ni un enlace ni un botón de salida** (solo el toggle de los
festivos, que no navega). Mide **1,03 pantallas a 390** — empezó en 1,77.

### El horario real (dueño, sep 2026)

**Lunes a jueves 16:30–21:30 · viernes, sábado y domingo 11:30–21:30 · festivos como el finde ·
ningún día de cierre.** Cierran todos a la misma hora: lo único que cambia es cuándo abren, y por
eso la tabla son **dos filas** y no siete. ⚠ Esto **desmiente dos fuentes**: la spec de excursiones
(`docs/specs/`) dice 10:00–21:00 con el **martes cerrado**, y la maqueta vieja decía L–J 16:00–21:00
con el viernes hasta las 23:00. Ninguna de las dos vale.

El estado se calcula con la hora del navegador contra esa misma tabla — **ningún número está
escrito dos veces en el archivo**, todos salen de `FILAS`. Cuatro estados: `abierto` · `luego`
(abre hoy) · `yacerrado` (ya hemos cerrado) · `cerrado` (hoy no abre). En producción son
`is_open` y `closes_at`, el campo que entró porque `HeroStatus` calculaba la ventana del día y la
tiraba.

### Las siete decisiones

1. **El «abierto ahora» es el dato de la sección**, en grande y en papel (Verde 800 sobre blanco,
   5,25 — el 500 daría 2,4 y no se puede usar como texto). La opción `1a` lo metía en chapa de tinta
   como 04, 05 y 06; se descartó para que **la única mancha oscura de la portada antes del cierre
   sea el cierre**.
2. **El mapa es de Google y va detrás del consentimiento** (categoría `maps`,
   `address.maps_embed_url`). Con permiso, el mapa; sin permiso, el aviso con la dirección, el botón
   «Cargar el mapa» (`consent-frame`) y **el enlace a Google Maps, que solo existe en ese estado**:
   abrir otra pestaña no pone cookies, y con el mapa puesto el enlace duplicaba lo que el propio
   widget ya ofrece.
3. **La dirección y el aparcamiento van siempre fuera del iframe.** Segundo modo de fallo que el
   consentimiento no cubre: un bloqueador tumba el iframe con las cookies aceptadas y entonces no
   salta ningún aviso, sale un hueco.
4. **Fuera el teléfono y el WhatsApp** (decisión del dueño): preguntar vive en el cierre. Con ellos
   se va la decisión que traían —los dos a la vez y nunca en cascada, porque un `v-else-if` esconde
   el WhatsApp de toda instalación que tenga teléfono—, **que hay que sostener allí**.
   ⚠ **Corregido en sep 2026**: el dueño los sacó también **del cierre**, así que preguntar ya no vive
   ahí — en toda la portada el teléfono queda en el **menú** y en el **pie**, y esa decisión de
   «nunca en cascada» hay que sostenerla en el pie.
5. **Fuera el bar**, a 03. Ver la nota de `CLAUDE.md`; en 07 quedó como recuadro y como fila y las
   dos se descartaron.
6. **Fuera «el horario, día a día»**: apuntaba a una página que el sistema de contenido dice que no
   va a existir. La semana entera se ve aquí, que es lo que la sección venía a hacer.
7. **Los festivos: la excepción, no el calendario.** El dueño propuso listarlos o enlazar a una
   página; las dos se descartaron (la página, por el punto 6; la lista de entrada, porque son doce
   fechas muertas 360 días al año). Lo que se construyó: **el festivo que viene** cuando queda ≤21
   días —dentro de la tarjeta, pegado a la regla que corrige— y **la lista plegada** (56 px cerrada,
   306,5 abierta) para quien planifica en diciembre. Sale de `special_dates`, que el panel ya tiene.
   Con los seis nacionales del ejemplo la ventana cubre **~93 días, un cuarto del año**: si con los
   de Lorca pasa de medio año, deja de ser excepción y hay que replantear la pieza.

### La tabla de horarios usa el componente, no un dibujo nuevo

`Componentes PJP` · 08 la tiene declarada: día **15,5 a 700** en Tinta, horas en **mono 15**, y
«en móvil se rompe en filas, nunca desplazamiento horizontal». Estaba redibujada a mano (16/400 y
mono 14) y se cuadró. Consecuencia: **hoy se marca solo con superficie** (Nube, y únicamente
mientras su horario está vigente), no con negrita — que además es la regla del velo de Tarifas.
**Divergencia declarada**: las filas van con 4 de aire y esquina 10 en vez de los separadores de
Papel 200 del componente, porque la fila de hoy es una pastilla y una pastilla con separadores se
ensucia. **Falta la columna «Nota»** del componente: los festivos van en una línea bajo la tabla.

### Lo que se rompió de la Arquitectura de Contenido (y ya está reescrito)

Su ficha decía *«los DOS horarios … el mapa como pieza y **el teléfono como único botón**. Aquí **la
línea del bar** y el parking»*. Dos de esas tres cosas se cayeron por decisión del dueño, así que la
ficha se ha reescrito. **Acertó en los dos horarios**: el horario real es exactamente eso.
⚠ La misma Arquitectura le da al futuro `/contacto` *«mapa sin nada encima, horario, parking,
teléfono y formulario»*, que es **casi esta sección otra vez**: al montar esa página hay que decidir
qué no se repite.

## Método · lo que costó esta sesión (07 Visítanos)

- ⚠⚠ **Las cifras de las notas se quedaron viejas cuatro veces.** Cada recorte —quitar «Preguntar»,
  el bar, el mapa a 16:9, el horario real de cuatro filas a dos— cambiaba el alto, y la prosa seguía
  diciendo el número anterior (1,77 → 1,25 → 1,09 → 1,03). El dueño lee esas cifras como verdad.
  **Al cambiar el diseño, se vuelve a medir en el DOM y se corrigen TODAS las notas que citan el
  número**, no solo la del turno nuevo.
- ⚠ **Un porcentaje inventado.** «El 90 % de las visitas no ven el aviso» no se calculó: eran ~75 %.
  La regla del proyecto ya lo decía —los ratios se calculan— y aquí se pagó otra vez.
- **La prosa afirmó dos veces algo que ya no se pintaba**: «un solo enlace, el del bar» cuando el bar
  ya estaba en 03 (cero enlaces), y «hoy se marca con superficie y peso» cuando la negrita ya no
  estaba. Al borrar un elemento hay que releer las notas que lo describían.
- **Dos frases mías se colaron como si fueran dato**: «sin reserva para entrar a saltar» (que choca
  con el aforo por franja y con que «¿hace falta reservar?» vive en Dudas) y «festivos y vísperas,
  horario de finde» (que nadie había decidido). En el dato más grande de una sección, una frase sin
  campo detrás es la que más caro sale.
- **Un agrupamiento mintió**: «finde 11:00 — 23:00» con un domingo que cerraba a las 21:00.
- **Los tres CTA no fallaban por ser tres**, fallaban cada uno por un motivo distinto: uno prometía
  una página inexistente, otro duplicaba una tarjeta que ya era enlace, y el tercero era el único
  legítimo. Contar controles no es auditar controles.

## 08 Dudas · cerrada (sep 2026)

Archivo: `Dudas PJP.dc.html`. Un turno; **la aprobada es `1a`**. Contesta la novena pregunta del
sistema de contenido, *«¿y lo que me queda?»*, y es **el desagüe honesto de lo que no merece
sección**. Es la última sección de papel antes del cierre, lleva el rótulo **08** y es la más
corta de la portada: **472 px = 0,56 pantallas** a 390×844, medido en el DOM (06 medía 0,89).

**La forma.** Cabecera (`08 · Dudas` / **«Lo que más nos preguntáis»** / *«Las que llegan por
teléfono, contestadas aquí.»*) y el **acordeón de `Componentes` 06** con cuatro preguntas: pregunta
18/700 en Tinta, respuesta 16 en Humo (5,49 sobre blanco), pulsable de 64 de alto y el «+» en Azul
Muro sobre Papel (6,43). Nada más: **cero naranja, cero relleno de acción, ninguna superficie de
tinta y ninguna salida al final**.

**Las seis decisiones que la cierran:**

1. **De las seis dudas publicadas, solo cuatro son dudas.** Se caen *edad* —la contestan 01, 02 y
   04— y *parking* —lo contesta 07—, y las dos se caían igual por un segundo motivo: **aquí estaban
   escritas con otro dato** («1 a 12 años» contra el 2–6 de Zonas; «gratis 2 h y luego 1 €/h» contra
   «en la calle, delante, y gratis» de 07). Repetirlas era contradecir a la propia página dos
   secciones más abajo.
2. **«¿Hace falta reservar?» deja de desmentir a la página.** Publicado dice «ven directo, no hace
   falta» mientras la barra, las cinco tarifas y el cierre dicen «Reservar» y el producto tiene
   aforo por franja. La respuesta que aguanta las tres cosas: *«Puedes venir directo y sacar la
   entrada en recepción. Reservando entras a tu hora, y los findes es la única forma de tenerla
   guardada.»*
3. **«¿Y si llueve?» se queda aunque no sea una duda.** Es un **argumento** —«interior, climatizado
   y a 22 °C todo el año»— y hoy no está en ninguna otra pieza de la portada. Recomendación escrita
   y **no aplicada**: que ese dato suba también a 03, que es donde se cuenta qué hay dentro.
4. **Sin salida al final.** El cierre está pegado debajo con el teléfono y el pie con el correo, y
   «preguntar vive en el cierre» es decisión del dueño en 07. Una FAQ que repite el canal gasta un
   control para llevar 200 px más abajo.
5. **Papel de arriba abajo, sin chapa de tinta.** Consecuencia directa de 07 («que la única mancha
   oscura antes del cierre sea el cierre»): 04, 05 y 06 tienen la suya porque tienen un dato que la
   merece; Dudas no tiene dato.
6. **Divergencia declarada con `Componentes` 06: aquí van todas cerradas.** El componente manda «la
   primera abierta al cargar» porque «una lista toda cerrada parece un menú apagado», y eso es
   verdad en una FAQ de página. Con cuatro filas en la portada, **el índice entero ES la respuesta**
   a «¿está mi duda?», y una abierta empuja las otras tres fuera del pulgar. El interruptor «Al
   cargar» enseña las dos.

**Lo descartado, con su motivo:** `1b`, que saca la duda de la reserva del acordeón y la contesta a
la vista, en Nube y sin borde —blanco + borde + radio 16 ya significa puerta en esta portada—. La
idea es buena (es la hermana de «el recipiente no es un paso» de 05: la respuesta que la propia
página provoca no se esconde detrás de un «+»), pero **cuesta 78 px medidos**: la línea a la vista
mide 131 y la fila que se ahorra solo devuelve 65. Y se paga otra cosa: la reserva pasa a ser lo
primero que se lee, y no es la duda más frecuente, solo la más contradictoria.

**Contrato de datos** — la sección **no escribe ni una pregunta**: son `faqs` del panel
(`question`/`answer` en JSON i18n, `position`, `is_active`). Props de la maqueta: `panel` (con las
cuatro dudas / vacío) · `arranque` · `correo`. Dos consecuencias de que el contenido sea del panel:
**sin filas activas la sección entera no se pinta** —ni rótulo, ni titular, ni caja vacía, igual
que 06 sin consentimiento—, y **cada duda que se añada suma 65 px** (64 de fila y 1 de filete), así
que el alto de la sección lo decide el panel, no el diseño.

⚠ **Pendiente del dueño, y son cinco:** las **cuatro versiones de la edad** (Zonas 2–6/7+, Dudas
1–12/6+, tarifas 4–7/8+, catálogo 1–6/7–99), que sigue bloqueando también 02 y 04 · cuál de los
**dos aparcamientos** es verdad · si «¿Hacéis precio para grupos?» lleva al **correo** o a la
**página de grupos** cuyo nombre está sin decidir · si «interior y climatizado» **sube a 03** · y
**qué otras dudas oyen de verdad en el mostrador** (acompañar sin saltar, traer tarta, taquillas,
tarjeta, cambiar de día).

### Reglas nuevas que salieron a los documentos (v1.18)

- **Una duda que ya se contesta arriba no baja a Dudas** — y si además está escrita con otro dato,
  el conflicto se resuelve antes de bajarla, no se publica dos veces.
- **Dudas ↔ Normas, el criterio que faltaba**: *Normas es lo que hay que cumplir; Dudas es lo que se
  pregunta en voz alta.* Con eso, calcetines, altura y registro no bajan aquí: viven arriba y en su
  página.
- **Un desagüe no puede desmentir a la página.** Una respuesta que contradice la acción principal
  no es información honesta: es una fuga, y encima al final del recorrido.
- **«Sin tope de alto» es la respuesta, no la lista.** Lo que `Arquitectura de Contenido` pedía es
  que la respuesta no se recorte —el defecto M8 del producto, `max-height: 240px`—, no que la
  sección enseñe N preguntas sin medir. Son dos cosas y se confundían en una frase.
- **Una lista de 3+ no siempre va en carril.** La FAQ cumple tres condiciones y falla la cuarta
  —«nada crítico al fondo»: la última duda decide tanto como la primera—, así que acordeón. Antes de
  aplicar la regla del carril hay que contar las cuatro.
- **Una sección data-driven con cero filas desaparece.** Ya estaba en 06 para el consentimiento;
  con Dudas se generaliza a cualquier sección cuyo contenido lo ponga el panel.

## /contacto · cerrada (sep 2026) · **el inventario de páginas está completo**

Archivo: `Contacto PJP.dc.html`, turno 1 (`1a` móvil · `1b` escritorio). **Aprobada.** Con ella
quedan cerradas **las siete páginas** del inventario.

**Era la página con más riesgo**: una de las tres URL que el propio sistema acusa de poder ser «un
trozo de la portada con otro título». Se salva por una distinción que ninguna otra superficie tiene:
**el horario de atención no es el horario de apertura**. 07 contesta «¿está abierto y cómo llego?»
con su horario en vivo y su mapa; ésta contesta **«quiero preguntar algo, ¿por dónde y cuándo me
contestan?»**.

**Por eso 07 NO se toca** —el dueño propuso simplificarla y expandirla aquí, y se argumentó en
contra: su horario en vivo no puede vivir en otra página, y 07 ya está en 1,07 pantallas después de
tres recortes—. Aquí no hay mapa, ni horario de apertura, ni aparcamiento: la **dirección va como
una línea escrita** —el dato crítico siempre fuera del iframe, la regla que cerró 07— con un enlace
a la sección.

**La forma.** Cabecera · **los tres canales** (fijo, móvil/WhatsApp, correo), cada uno con para qué
sirve · el **formulario** —nombre, correo, teléfono opcional, motivo, mensaje, consentimiento— ·
la chapa **«quizá ya está contestado»** con tres atajos reales del inventario, porque un contacto
que se puede evitar es un correo que no hay que contestar · y la dirección. En escritorio,
**736 + resto**: el formulario se queda con las ocho columnas y los campos **se emparejan de dos en
dos**, porque un campo de nombre a 672 px de ancho es un campo mal hecho.

**Los campos están copiados del marcado de `Componentes` §02**, no redibujados: etiqueta encima y
nunca dentro, alto 52, radio 10, borde Línea y foco con el borde a Azul Muro. El botón es la
**variante secundaria de §01 para papel** —relleno de tinta, «nunca cian»— con su relleno 15/28 y su
pulsado a Tinta 800: el naranja se queda fuera porque **enviar no es comprar**.

**Una extensión declarada**: el sistema **no define campo multilínea**, así que el mensaje reusa
borde, radio y foco del campo de una línea y añade solo el relleno vertical.

⚠ **Lo que costó** —y es el patrón de siempre—: dije «copiado del marcado» y la copia **derivó en
cuatro puntos**: los seis campos perdieron el foco del componente, el `select` estrenó un relleno
inventado de 12, el botón perdió transición y pulsado, y la nota llamó **divergencia** a lo que el
componente ya documenta. Copiar de verdad es copiar los estados, no solo los colores. Y al medir
salió un incumplimiento más: el **enlace legal iba dentro de la frase** del consentimiento, con 19
px de alto contra el mínimo de 48 — ahora es un control propio, el mismo arreglo que costó el correo
de `/servicios`.

⚠ **Pendiente del dueño:** el **fijo está inventado** (968 47 12 30) a su petición —el móvil y el
correo son los reales— · **«horario laboral» es vago** para quien escribe un domingo: con las horas
concretas la frase pasa a decir un hecho · y si el **WhatsApp es el mismo número** que el móvil, y
quién lo lee.

## /bar · cerrada (sep 2026)

Archivo: `Bar PJP.dc.html`, turno 1 (`1a` móvil · `1b` escritorio). **Aprobada.** Deja de estar
**condicionada** en el inventario: el dueño confirmó que hay bebida y comida —tapas, tortilla,
saladitos, pizzas—, o sea **carta con precios**, que es dato editable y llena una pantalla.

**La página tiene un contrato ya firmado, y es lo que la define**: la tarjeta del bar en 03 dice
«mesas con el parque a la vista y **carta corta**. Puedes comer sin saltar». De ahí las tres
decisiones: **trae la carta** —lo único que la tarjeta promete y no cabe en ella, y por eso es
página—; **es corta**, dos grupos y no cuatro, porque si se estira a veinte platos **la tarjeta de
03 pasa a mentir**: «carta corta» es una promesa, no un adjetivo; y **no repite la puerta**
(«¿y yo qué hago mientras?» se contesta en 03) ni el horario, que es de 07.

**De las tres pruebas del sistema de contenido pasa dos y falla la tercera** —nadie busca «el bar de
Play Jump» por su nombre—, y eso queda **escrito en la propia nota** en vez de disimulado: se
sostiene porque su puerta está en 03, que sí se visita.

**Dos decisiones de sistema que merecen nombre:**

- **El precio va en mono, no en Bungee**, al contrario que en `/precios`. Aquí el sujeto es el plato;
  allí el precio **es** la página. Misma tabla (`Componentes` §08), distinto protagonista.
- **Cero naranja y ningún relleno**: en el bar no se compra por la web, se pide en la barra. El único
  naranja de la pantalla lo trae la barra del armazón.

En escritorio, reparto **544 + resto**: foto y chapa a la izquierda, carta entera a la derecha, y la
chapa sube debajo de la foto en vez de cerrar la página, porque ahí acompaña a lo que describe.

⚠ **Lo que costó, y es el mismo error tres veces**: la nota de `1b` publicó **tres afirmaciones
falsas seguidas** sobre su propio alto — «cabe en una pantalla» (mide 1.100), luego «818 px» hasta
el fondo de la carta (son **886**: sumé los altos y **olvidé los tres márgenes**, el fallo que este
handoff ya tiene fichado con los 2.404 estimados contra 2.583 medidos) y «justo debajo del pliegue»
para un enlace que está a 1.030. Corregido con las cifras medidas —**886** la carta, **958** con la
cabecera fija del armazón, **1.100** la página— y con la regla escrita en la nota: **una cifra en
una nota se mide en el DOM o no se escribe.**

⚠ **Pendiente del dueño, y el primero bloquea el titular:** el **nombre real del bar** —ya estaba
pendiente de 03— · **la carta** plato a plato con sus precios · si **se puede entrar solo al bar**
sin sacar entrada, que es la pregunta de un vecino y no está contestada en ningún documento · y los
**alérgenos**, que con comida son obligación legal.

## /servicios · cerrada (sep 2026)

Archivo: `Servicios PJP.dc.html`, turno 1 (`1a` móvil · `1b` escritorio). **Aprobada.** Arregla un
**enlace roto ya publicado** —la respuesta de Dudas sobre grupos manda a «la página de Servicios»—
y le da casa a **un público que no la tenía**: las excursiones de colegio.

**Su forma la decide el dueño**: hay precios, es **reservable online** y el catálogo **crece** (hoy
cumpleaños y excursiones, mañana más). Así que es una **lista**, no una página a medida, y el
detalle vive dentro **solo de los servicios que no tienen página propia**: cumpleaños remata en su
enlace, excursiones se explica aquí entera. El tercer servicio entra sin rediseñar nada.

**Lo que trae de nuevo, y decide si una excursión sale: las firmas antes del autobús.** En una
excursión el **justificante digital es obligatorio** y el profesor **no puede firmar por ellos** —
son 50 familias. Tres momentos, en chapa de tinta con eje cian: al reservar te damos **un enlace**
para repartir · cada familia firma el suyo **sin crear cuenta** · el día de la excursión **ves quién
falta**. Es la pieza de 05 con otro protagonista, y estaba construida en el producto
(`waiver-por-reserva.md`) sin que ninguna superficie la contara.

**El descuento por cantidad** va como tabla de tramos cuadrada a `Componentes` §08, con **un
contador de niños** (15 a 120, de cinco en cinco) que **resalta solo el tramo que le toca** —marcado
con superficie, papel contra blanco, nunca con Nube—. Es «nunca una suma» aplicada por tercera vez:
el profesor tiene un número, no un tramo. **Ni un precio inventado**: aquí va la forma y el panel
pone las cifras.

**«Vuestro horario, no el nuestro»**: en horario lectivo se abre para ellos aunque el parque esté
cerrado al público. Contesta lo que la página no contestaba —un colegio va por la mañana y el parque
abre a las 16:30— y sostiene lo que Dudas ya promete.

Es la **única de las cinco páginas con relleno naranja**, y es correcto: aquí se compra, así que la
barra flotante se retira mientras el botón está a la vista.

⚠ **Dos errores que costó, los dos de descuido**: dejé **asteriscos de markdown** en la entradilla, y
**olvidé cargar `image-slot.js`** en el helmet — sin el componente, el hueco de foto se veía como un
rectángulo negro sin texto ni zona de arrastre. Y **dupliqué un dato**: añadí «Cuándo se puede
venir» diciendo lo mismo que «Vuestro horario, no el nuestro», que ya estaba.

⚠ **Pendiente del dueño:** los **tramos de descuento** y sus precios · **cuánto dura** una excursión ·
el **mínimo y el máximo** de niños · el precio de la **merienda** · y **qué cursos pueden venir** —un
colegio de infantil no cabe en Jump y la zona Kids es de 2 a 6.

## /normas · cerrada (sep 2026)

Archivo: `Normas PJP.dc.html`, turno 1 (`1a` móvil · `1b` escritorio). **Aprobada.** Escrita sobre
la `/normas` publicada, leída el 7 sep, más las tres prioridades del dueño: registro, los hijos y
los calcetines.

**El descargo NO se mete aquí.** Ya tiene su URL —`/waiver`, que en el pie se llama «Descargo de
responsabilidad», el nombre del vocabulario— y son dos trabajos distintos: esta página se **lee**
para saber cómo comportarse, y el descargo se **firma** y hay que reproducirlo entero. Lo que hace
la página es decir que el registro incluye aprobarlo y llevar al texto, en la **única chapa de
tinta** de la página: es lo que separa «lo que hay que cumplir» de «lo que has firmado».

**La forma.** Cabecera · **la altura dibujada** —el eje aprobado de Zonas, con las dos fronteras que
la web declara, 1,30 y 1 m, y tres franjas proporcionales— · las normas **agrupadas por momento**
(en casa · al llegar · dentro), sin icono por norma —lo pide la Arquitectura— y **sin numerar**, que
es la lección de 05 · la chapa del descargo · y la fecha de actualización, que sale del panel.

**Cada norma lleva su por qué** cuando lo tiene («sin calcetines resbalas en la lona, y es la lesión
más tonta del parque»): una norma con motivo se cumple, una norma sola se discute en la puerta.

**Lo que se cayó de la publicada, con su motivo:** «Zona Kids · consulta las condiciones del
centro», que no dice nada · «Información», que no es una norma sino «pregunta al staff», y baja a
una línea al final · y el orden de bloques, que mezclaba lo que decide si entras con lo que pasa
dentro. **Y entró una que no estaba escrita en ningún sitio: los calcetines.**

**Un dato real que la publicada tenía y nosotros no**: si tiene la edad de Jump pero mide **entre 1
y 1,30 m**, entra acompañado por su tutor. Eso tapa el hueco que `DEUDA.md` señalaba —el niño de 8
años que no llega a 1,30— y es la segunda frontera del eje.

**Dos reglas nuevas, las dos del eje:**

- **Sobre una banda de color el gris secundario deja de funcionar** (medido: 4,43). En una superficie
  teñida solo aguanta la tinta.
- **Una franja proporcional no lleva el texto que le apetezca**: la de 1 a 1,30 es el **15,8 %** del
  eje por definición, así que su altura la decide la aritmética y no el copy. Los matices salen de la
  banda y se ponen debajo.

⚠ **Pendiente del dueño:** las **edades** —su propia web dice tres cosas distintas: el menú «JUMP +8
· KIDS 4–7», esta página «Jump desde 6 años y 1,30» y el catálogo otra; aquí está escrito lo
aprobado— · si la **Zona Kids** tiene norma propia · y **qué falta de dentro**: comida de fuera,
móviles, joyas, gafas, embarazadas, lesiones.

## /precios · cerrada (sep 2026)

Archivo: `Precios Pagina PJP.dc.html`, turno 1 (`1a` móvil · `1b` escritorio). Las cinco tarifas
reales, qué es la tarifa especial, los festivos enteros, los complementos y una línea a
`/cumpleanos`. Móvil 2,4 pantallas.

**Las cuatro decisiones:**

1. **Aquí NO hay pestaña de zona**, y va contra lo que se hizo en `/atracciones`. El motivo es la
   prueba que le dio página: *«la buscan por su nombre o le mandan el enlace»* — quien abre un
   enlace que le han mandado **no ha elegido zona**, así que esconderle media tabla es lo contrario
   de lo que la página viene a hacer. En atracciones la zona **venía elegida**; aquí no.
2. **La semana dibujada**, que la Arquitectura pedía para las tarifas y **nunca se había
   construido**: siete casillas, L–J en blanco y V–D en tinta, con «+ festivos y vísperas» colgando
   del grupo oscuro. El especial se marca con **superficie, no con color** —la regla del velo de
   Tarifas—, y con eso «¿cuánto me cuesta el sábado?» se contesta sin leer.
3. **El destacado es DATO del producto**, no énfasis escrito: el panel marca cuál se destaca y con
   qué etiqueta. Y la etiqueta dice un **hecho** —«Sin límite de tiempo»— en vez de un eslogan.
4. **Los festivos completos viven aquí**, no en 07, que solo enseña el que viene: es la diferencia
   entre capa 2 y capa 3. Salen de `special_dates`, el mismo campo.

**Cuadrada a `Componentes` §08** (primera columna 15,5/700, nota 14, cabecera mono 10,5 con .14em,
cifras en mono 15) con **una divergencia declarada**: el precio principal se queda en Bungee 20/26
sobre Lima 800, porque es el par que cerró 02 — en una página de precios el precio es el sujeto, no
un dato de tabla. El especial sí baja a mono, así que cada fila tiene un solo protagonista.

⚠ **Dos errores de sistema que costó, y el primero es conceptual**: marqué la fila destacada con
**superficie Nube**, cuyo rol escrito es «relleno inerte, deshabilitado» — decía lo contrario de
destacado, y además tiraba el precio a **4,28** de contraste. Ahora destaca solo la chapa amarilla,
que es el «resalte tipo marcador» del sistema. Y **Bungee a 17** en las letras de la semana, contra
el suelo de 20.

⚠ **Pendiente del dueño:** los **festivos de Lorca y de la Región**, que son los que el visitante de
fuera no sabe.

## /cumpleanos · cerrada (sep 2026)

Archivo: `Cumpleanos Pagina PJP.dc.html`, turno 1 (`1a`). **Aprobada.** Es la página que la
Arquitectura llama «donde está casi toda la complejidad», y la primera que se dibuja con
**contenido real del dueño**: pasó los dos bloques de catálogo enteros.

**El orden lo eligió él**: el **reloj de la fiesta** primero —qué pasa ese día— y los packs después.
De arriba abajo: cabecera · **foto de la zona montada** (16:9) · el reloj · los **dos packs en filas
alineadas** · una **foto de la mesa** y los **dos menús** completos · **«Igual en los dos»** ·
el **mixto** · **«Su día especial»** explicado campo a campo con la ficha de Lucía · y el aviso
«Personalizamos cada cumple». 5,44 pantallas, y no le aplica el techo de 11, que es de la portada.

**Los datos reales, y las cuatro cosas que corrigen a los documentos:**

| | Kids | Jump |
|---|---|---|
| Por niño | 14,95 € | 15,95 € |
| En tarifa especial | **16,95 €** | **19,95 €** |
| Edad del pack | De 4 a 7 años | A partir de 8 años |
| Hora extra de sala | +4,00 € | +5,00 € |

Igual en los dos: acceso exclusivo a la zona · monitor toda la sesión · mesa reservada · comida y
bebida · calcetines incluidos · de 8 a 20 niños · 120 minutos · **señal de 50 €**.

1. ⚠ **La señal es 50 €, no 30**, y los precios no son los de la maqueta de 04 (11/13/15/18): eso
   hay que cargarlo en el panel y **la maqueta de 04 sigue con los de prueba**.
2. ⚠ **La hora extra SÍ existe en cumpleaños** —«hora extra de sala», +4,00 € y +5,00 €—. El
   handoff decía que no y era **error mío**: queda corregido aquí.
3. ✅ **«Acceso exclusivo a la zona» cierra la promesa operativa** que 04 dejó abierta: la zona sí es
   exclusiva mientras dura la fiesta.
4. ⚠ **La edad del PACK no es la de la ZONA**: 4–7 contra 2–6. Pueden ser las dos verdad, pero
   entonces un niño de 3 salta en Kids y **no puede tener su cumple ahí**. **Pendiente del dueño.**

**Tres reglas nuevas, y las tres se pagaron aquí:**

- **El recargo no es plano** —+2,00 € en Kids y +4,00 € en Jump—, lo que convierte el «+X €» del
  catálogo en algo que el cliente tendría que **recordar**. Es el argumento definitivo de la regla
  de Precios: el especial se dice **entero**, y en el archivo solo viven el precio y su recargo.
- **Una comparativa solo compara lo que difiere.** La tabla tenía siete filas y cuatro eran
  idénticas en las dos columnas —incluido un «qué incluye» de cuatro cosas repetido al lado—. Bajó
  a **cuatro filas**, y las ocho coincidencias subieron a un bloque **«Igual en los dos»** con su ✓.
  Poner en dos columnas un texto idéntico no es comparar: es repetir.
- **Un filete de acento a la izquierda no es del vocabulario del producto.** Es cromo de los
  documentos del sistema, y `CLAUDE.md` lo tiene fichado como recurso a evitar. La tarjeta del mixto
  pasa a tarjeta blanca con borde Línea y radio 16.

**Y dos piezas que aterrizan aquí desde la portada**: el **reloj** y el **aviso INFO**, copiados del
marcado de 04 —el recorte del presupuesto los mandó a esta página—, más la **ficha de invitado ya
rellena**, que se descartó de 04 por sitio con la nota escrita de guardarla para `/cumpleanos`.

**El total, y «nunca una suma»** (añadido tras la primera aprobación): la página daba 14,95 € por
niño y dejaba la multiplicación al cliente, que es justo lo que la regla prohíbe. Ahora hay **un
solo control** —cuántos niños, de 8 a 20— y la tabla gana dos filas con el **total de cada pack**,
normal y especial: no hay que elegir zona ni día para verlo. Con él entran dos frases que faltaban:
**de la señal de 50 € se descuenta el resto**, y **si vienen menos niños se ajusta en el parque**.
El total va en Hanken 800 (22 / 26) y no en Bungee, para que cada fila siga teniendo **un solo
protagonista**: el precio por niño manda, el total es su consecuencia.

**Los complementos post-reserva** (los extras de adultos) van como **promesa sin precio y sin
lista**: es capa 4, y además el eje que los hace posibles (`product_addons.stage`) está diseñado y
**sin construir**, así que un precio ahí prometería algo que el cliente no puede elegir.

⚠ **Pendiente del dueño:** la edad del pack contra la de la zona · si la **hora extra** aparece
también en la portada · y cargar en el panel los precios reales, que hoy solo están en esta página.

## /atracciones · cerrada (sep 2026)

Archivo: `Atracciones PJP.dc.html`, turno 1. **Aprobada la `1a`** (con pestaña de zona), con su
escritorio en `1c`. Es la **primera página interior** y estaba prometida tres veces sin existir: la
tarjeta-enlace de Zonas apunta aquí, las dos puertas del «18 más» de 03 apuntaban aquí —y se cayeron
con el recorte del presupuesto— y el inventario ya había decidido **una página, no dos**, con la zona
en la URL, porque la pregunta es una: «¿qué hay en mi zona?».

**La forma.** Cabecera de página (`/atracciones` / **«Todo lo que hay dentro»** / la entradilla con
el total) · **pestaña Kids/Jump** copiada de `Componentes` §03 —carril sobre Nube, botones sin borde
a 15/800, activa en blanco con texto tinta— · la **cifra de la zona** en Display L al lado del
control · rejilla de **dos columnas** con foto cuadrada de **172** (el mismo tamaño del mosaico de
03), nombre en Hanken 800 a 17 —Bungee no baja de 20 y a 172 se partiría en tres líneas— y el chip
de edad en `chipDato` con **el color de zona al 14 %** · y un cierre de sección con la regla de
Zonas y el enlace a la otra zona. En escritorio: **cuatro columnas de 256** —(1120 − 3 × 32) / 4,
exacto—, foto a 256, nombre a 19, y el carril de pestañas **se queda en 352** en vez de estirarse a
1120: un control de dos opciones a lo ancho de la pantalla es un blanco enorme para dos palabras.

**Por qué `1a` y no `1b`** (que enseñaba las 23 agrupadas y sin control): la tarjeta de Zonas
promete **«Ver la zona Kids →»**, en singular, así que quien llega desde ahí espera lo suyo y no las
dos listas. `1b` se conserva y tiene su argumento —en capa 3 «quien llega aquí ha querido llegar»,
así que desplazarse es gratis— y su hallazgo, que sí se aplicó a `1a`: **la edad es de la zona, no
de la atracción**, así que se dice una vez y no 23 veces.

**Contrato de datos** — todo del panel (`attractions`: nombre, zona, posición). Las cifras son
reales: **8 en Kids y 15 en Jump = las 23** publicadas. El lado de la foto y los repartos se
**calculan**, no se teclean.

⚠ **Pendiente del dueño:** los **nombres reales de las 23** (los del archivo son de relleno, y ya
estaban pendientes de 03) · si alguna atracción tiene **su propia altura mínima** —las que se
dibujaron primero eran **invento mío**, incluido un 1,40 que no existe en ningún documento, y están
retiradas: el único dato de altura del sistema es el 1,30, y es de la zona— · y **las fotos**: el
interruptor enseña las dos rutas, las 23 o ninguna en la ficha.

⚠ **Y una deuda del recorte**: la chapa que llevaba las dos puertas salió de 03, así que hoy esta
página **solo tiene entrada desde la tarjeta de Zonas**. Hay que devolverle su puerta.

## El armazón de las páginas · aprobado (sep 2026)

Archivo: `Layout Paginas PJP.dc.html`, turno 1 (`1a` móvil · `1b` escritorio · `1c` el menú de
escritorio). **Aprobado por el dueño.** Es lo que visten las **siete** páginas del inventario
—tarifas, cumpleaños, atracciones, el bar, normas, servicios y contacto— y se decide una vez, no
página a página. Con él se cierran los tres agujeros que dejó el marco.

1. **Una página no tiene hero: tiene cabecera de página.** La misma de las ocho secciones —rótulo
   mono 12, Display L 34/52, entradilla 18/21— con **la ruta en el rótulo**, que es lo que el menú
   ya escribe debajo de cada destino. El Display XL es uno por página y en una interior no hay nada
   que lo merezca. La cabecera fija cabe en su token sin divergencia: **60** en móvil (48 del botón
   + 6 arriba y abajo) y **72** en escritorio (el par de 54 con 9), con el filete de **4 px** —el
   token da el grosor y no el color: se lee como el keyline de la marca, o sea tinta.
2. **El cierre es la tarjeta del cierre de la portada, copiada**: radio 16 con su keyline, trama de
   puntos, chapa de Lorca a −6°, titular en **tres partes apiladas** en Display L y párrafo
   centrado. Se le quitan dos cosas: el **juego** —«el único momento de sorpresa»: repetido siete
   veces deja de serlo, así que quitarlo protege la regla en vez de incumplirla— y el **eslogan**,
   que por la letra de la regla *sí cabría* (una página interior es otra superficie) y sale por
   criterio: un guiño siete veces no es un guiño. ⚠ Y una condición de implementación: el
   «Reservar» del cierre es relleno naranja, así que **la barra tiene que retirarse cuando el
   cierre entra en pantalla**, igual que se retira en Tarifas.
3. **El pie es el del marco, copiado entero**: la tira de cinco colores que lo abre, los destinos a
   15/600 en tira deslizante, la fila de contacto en mono 15, la fila legal con «Configuración de
   cookies» y el colofón. Dos diferencias declaradas: el **idioma no es condicional** —en una
   interior el pie es su único sitio que funciona sin JavaScript— y en escritorio **deja de
   deslizarse**, porque a 1120 los destinos caben en filas.
4. **El menú de escritorio** (`1c`), que no existía: **panel de 520 a la derecha** con el velo de
   tinta al 72 %, no pantalla completa — tapar 1920 px para siete enlaces no se sostiene. Bungee
   baja de 30 a **22** (el 30 era para el pulgar a pantalla completa) y el grupo «Esta página»
   desaparece hasta que una página tenga secciones a las que bajar. El eslogan **sí** entra aquí,
   porque el menú es otra superficie.

**Y una regla de método que este archivo pagó cinco veces**: *una pieza que ya existe se COPIA de su
marcado, no se redibuja de memoria.* El pie falló tres veces (le faltaban la tira, el colofón, los
pesos, y una vez llegó sin la hoja de su propia clase `.pjp-tira`, así que en móvil recortaba cinco
enlaces contra la tarjeta), el logotipo estaba **tipografiado a mano** cuando `marca/logo-pjp-negro.svg`
existía, y el cierre llegó sin trama ni chapa. Ninguno era un problema de diseño: era no abrir el
archivo de al lado.

## La pasada de escritorio · arrancada (sep 2026)

Archivo: `Escritorio PJP.dc.html`, turno 1. **No se hace sección a sección**: a partir de 1024 px
cambian a la vez la columna de 1120, el aire de 240 y la barra. Lo que el turno 1 deja cerrado, y
son las cuatro decisiones del dueño:

1. **Una sola cabecera para las ocho secciones, y el nivel es Display L** (34 en móvil, 52 en
   escritorio). Salió de medir, no de mirar: el titular valía **34 en tres secciones, 32 en tres,
   30 en una y 26 en otra** — cuatro valores para la misma cosa —, el rótulo 11 (la escala solo
   tiene 12) y la entradilla 16, que es *cuerpo*. La única que cumplía el token era 07, y lo
   cumplía **al nivel equivocado**. Display L ya existía, su uso escrito era «cabecera de bloque»
   y su valor de móvil es exactamente el 34 que ya usaban 01, 02 y 03. **Aplicado**: las ocho
   secciones, con rótulo 12, titular 34 (lh .95, ls −.01em), entradilla 18, y los dos aires del
   ritmo — título→entradilla 16 y cabecera→contenido 28. ⚠ La barrida tocó **todos los turnos** de
   cada archivo, no solo la opción aprobada, para que un archivo no hable dos idiomas tipográficos —
   y se hizo **por la relación estructural** (rótulo numerado + el titular que va detrás), no por el
   texto del titular: el primer intento buscó el titular por su texto y dejó **cabeceras mestizas**
   (rótulo nuevo, titular viejo) en los turnos con otro titular, y se saltó la rama `sinFoto` de 04.
   ⚠ Y trajo una **divergencia declarada en 07**: su «abierto ahora» va en Bungee 34 y ahora comparte
   nivel con el titular. No se ha aplanado nada —el dato era 1,31× el titular porque el titular
   estaba a 26— y en móvil no hay nivel legal por encima de 34: el XL es uno por página y lo gasta
   el hero. Al dato lo distinguen el color y la tarjeta.
   Y `Título` (36/26) no se retira: pasa a ser lo que ya era en la práctica, el titular de un
   bloque **dentro** de una sección.
2. **El escritorio empieza en 1024, y el 900 desaparece.** La barra flotante se retiraba a 900 y
   ese número **no existía en los tokens** (los saltos son 390 · 768 · 1024), así que entre 900 y
   1024 no había barra y todavía no había retícula de escritorio: el pulgar perdía el único sitio
   donde se compra. Ahora es `componente.barra.retiraEn`, que **lee** `reticula.puntos.escritorio`
   en vez de repetir un número.
3. **El territorio de Jump pasa a lima al 24 %.** Al leer el marcado de 4a apareció que su velo era
   **naranja al 24 %**, contra el mapa del naranja («solo significa comprar», y Zonas va sin
   naranja). Aplicado en `Zonas PJP` 4a y en el dibujo de escritorio. El naranja que queda en ese
   archivo está en turnos históricos.
4. **Las edades: Kids 2–6 · Jump desde 7**, las de Zonas. Con eso se desbloquean 02, 04 y 08, y el
   conflicto de las cuatro versiones se cierra. ⚠ Queda cambiar los textos publicados en la web,
   que dicen otra cosa en tarifas (4–7 / 8+) y en dudas (1–12 / desde 6), y el catálogo de pruebas
   (1–6 / 7–99), del que sale el cobro del cumpleaños mixto.

**La retícula**: 1120 son 12 columnas de **64** con canal de **32**, exactos. Repartos limpios:
544 + 544, 352 + 736 y **64 + 496 + 496**, que es el de Zonas — la escala de altura ocupa una
columna entera. En el archivo ese reparto **no está escrito**: se deriva de la retícula y lo leen
los tres sitios que lo citan.

**01 Zonas, dibujada** (`1c`): la tarjeta de 4a **copiada de su marcado**, ensanchada a 496, con la
escala fuera —una sola, vertical y compartida— y el 1,30 como frontera real, la línea de puntos y
la chapa centradas en 335 igual que el borde de las franjas. Tres cambios declarados: el velo deja
de teñir el texto y pasa a **franja de alto proporcional** (en móvil no hay sitio para medir un
tramo), se retiran «encima, Jump» y «debajo, Kids» —existían porque cada tarjeta llevaba su propia
escala—, y tres tamaños escalan al nivel de escritorio.

### El marco en escritorio · turno 6, **aprobado** (8 sep 2026)

✅ Decidido: hero **a pantalla entera** · **el par doble** en el hero · **tinta** al minimizarse · pie
**en fila**. Dos consecuencias escritas: el par **se transforma, no viaja** (el relleno pasa de
naranja a tinta en la misma ventana 0,18–0,62 que interpola alto y titular, y el mapa del naranja se
queda intacto), y ⚠ con el hero a pantalla entera **no asoma nada**: la única señal de que la página
sigue es el movimiento, y con `prefers-reduced-motion` **no queda ninguna** — único cabo suelto, para
la pasada de vestido.

Los dos agujeros que quedaban. Con esto **la portada está entera en escritorio**.

**El hero (`6a`) · a sangre, y el alto no es «una pantalla».** Tres cambios sobre 1a, los tres
declarados: pasa a **sangre** (en móvil es tarjeta de radio 16 con 16 de margen y ahí el radio se ve;
a 1920 es el **0,8 %** del ancho y los márgenes dejan franjas de papel mudas — y la regla del sistema
es contenido a 1120 con **fondo a sangre**), el titular sube de 42 a **76** (el valor de escritorio de
Display XL **en los tokens**, no un número nuevo) y el contenido con la chapa del vídeo entran en la
**columna de 1120** — pegada al borde, a 1920 la chapa queda en la otra esquina del mundo. El eslogan
no está: vive en el cierre y el menú.

- **El alto es `min(84svh, 900)`**, y es el único cálculo de la opción: en móvil el hero mide **620 de
  844** y deja asomar **224 px** de la sección siguiente —filete, rótulo y titular de 01—, que es la
  **única señal de que la página sigue**. A pantalla entera esa señal desaparece. En un portátil de
  768 útiles da **645** y asoman **123**, que dan para el filete, el rótulo y el **titular entero**
  (40 + 16 + 8 + 49 = 113 medidos); a 1080 topa en 900 y asoman 180.

**El cierre y el pie (`6b`).** El cierre pasa a **dos columnas** con el juego al lado: en móvil corre
en una tira de 150 y aquí tiene **544 × 230** apaisados —un castillo que corre necesita carrera—. El
teléfono y el WhatsApp **no están**, y no es olvido: es la decisión de septiembre (preguntar vive en
el menú y el pie); 1e aún los dibuja porque es anterior. Medido: cierre **424** y pie **588**, tres
columnas en **3 + 5 + 4**, 26 pulsables y ninguno por debajo de 48.

- **El pie deja de deslizarse, y el degradado se va con el scroll.** En móvil las dos tiras llevan un
  desvanecido de 40 px que dice «hay más a la derecha»; quitar el scroll y dejar el degradado sigue
  prometiendo algo que ya no existe. **Es el mismo fallo que el velo del mosaico** —un efecto que solo
  significa algo mientras dura la condición que lo justifica— así que los dos están **fuera**, no
  desactivados.
- **Los trece destinos se reparten en los dos grupos que ya tiene decididos el menú**: ocho páginas y
  cinco secciones (sección ↓ · página →). No hay taxonomía nueva. En móvil van los trece en una tira
  sin distinguir, y en escritorio eso sería una fila de 13 o dos columnas mudas.
- El **idioma no es condicional**: menú y pie, y el pie es su único sitio sin JavaScript.
- Pendiente del dueño: **nada del dibujo**. ⚠ Queda el cabo suelto de `prefers-reduced-motion` (sin
  asomo y sin animación, cero señal de que la página sigue), que es de la pasada de vestido.

**Las tres preguntas del dueño (8 sep), y las tres tenían razón:**

1. ⚠⚠ **Faltaba el segundo estado del hero.** `6a` dibujaba solo la entrada y hablaba del recorrido
   **en la nota** — hablar de una animación sin dibujarla. Ahora están los dos estados y lo que
   interpola el mismo recorrido de 420 px: **645 → 360** de alto y **76 → 52** de titular, que es la
   misma pareja de niveles que en móvil (620→300 y 42→34). Al minimizarse aparece **el racimo entero**
   de 1b y el vídeo **sigue detrás** con su degradado: es la misma superficie, más baja. La chapa del
   vídeo se va, porque en 360 el racimo ocupa esa esquina.
2. **El pie va en fila, y es lo fiel**: en móvil es una tira horizontal —las columnas eran una forma
   **nueva**, inventada para escritorio—. Los trece no caben en una sola fila, y esta cifra la había
   estimado mal: medidos suman **1.154** contra 1.120 (se pasan por **34 px**, no por 173), y con los
   rótulos 1.278. Van en **dos filas rotuladas**, una por grupo. El pie **baja de 588 a 421**.
3. ⚠⚠ **El par doble en el hero trae una decisión que no es de dibujo.** Si el par del hero es el par
   doble, al minimizarse la pieza **viaja** al racimo, y eso es lo que hace creíble el «un mecanismo,
   no dos». Pero el par del hero es **naranja** —en la primera pantalla no hay barra flotante, así que
   el hero es el único sitio donde puede vivir el naranja de comprar— y el del racimo es **tinta**: o
   el racimo **hereda naranja permanente**, y el mapa del naranja gana una entrada que hoy no tiene, o
   el par **cambia de color a mitad del recorrido** y entonces no es la misma pieza. Los dos
   interruptores están puestos.

**Y tres correcciones más del dueño sobre el cierre (8 sep), las tres fallos míos:**

1. **El cierre va centrado.** La fuente (1e) lo tiene centrado —eje, párrafo y CTA— y mis dos columnas
   rompían su simetría sin declararlo: es **la única sección de composición simétrica** de la portada,
   y por algo, porque es la que cierra. Centrado, el juego va **debajo a lo ancho** y sale ganando:
   **1.036 × 230** medidos contra los 544 de la columna — que era el argumento con el que defendí las
   columnas, y se cumple mejor así. Cuesta **187 px** de alto (el cierre pasa de 424 a **611**) y ese
   es el precio, escrito.
2. **El eslogan no tenía su fuente.** `Permanent Marker` **no estaba en el helmet** de
   `Escritorio PJP` —solo Bungee, Hanken y Mono—, así que el navegador lo pintaba con otra. Añadida
   (verificado con `document.fonts.check`). **Es un fallo que no se ve leyendo el código**: el
   `font-family` era correcto y la fuente no estaba cargada — al copiar una pieza hay que copiar
   también **sus fuentes**, no solo su marcado.
3. **El juego también tiene transición, y me la había dejado** igual que el segundo estado del hero:
   en móvil el lienzo pasa de **150 a 358** al jugar; aquí de **230 a 520**, y jugando **se retira su
   propio botón** (el espacio arranca la partida, no el ratón).

- ⚠ Cuarto fallo, encontrado al medir: el contenedor del cierre iba en `flex` **sin dirección**, así
  que el juego se quedaba en fila con el texto midiendo 744 en vez de bajar a lo ancho.

### 04, 06 y 08 en escritorio · turno 5, **aprobado** (8 sep 2026)

✅ Las tres aprobadas, y las dos correcciones de móvil **ya aplicadas**:

- **`Cumpleanos PJP`** publica los precios reales del catálogo —**14,95 · 15,95**, especiales
  **16,95 · 19,95**, señal **50 €**— y el formateador escribe **coma decimal**, que en español es la
  que va. Antes la portada decía 11 y 15 y la página los reales: el mismo pack a dos precios.
- **`Resenas PJP`** ya no enlaza el nombre del autor: era un pulsable de **24 px** contra el mínimo de
  48 y estaba en los **cuatro** sitios del archivo. Eso no era una decisión — la regla no admite
  excepciones. La opinión queda con **una sola salida**, la de Google.

La tabla del turno 1 las despachaba con «solo se ensanchan». **Ninguna lo hace sin decidir algo.**

**04 (`5a`) · la foto no puede seguir en 16:9.** A 1120 son **630 px** de alto solo para el rótulo y
el titular —tres cuartos de pantalla antes de la primera palabra—; pasa a **21:9** y da **480**. El
recorte de una foto no es un valor del sistema: lo deja la retícula, igual que en el mosaico de 03.
Los dos packs se enfrentan en 544 + 544, a `stretch` y con la fila de salida en `margin-top:auto`
—sin eso una línea más en un pack descuadra la salida del otro, el fallo que ya se pagó en Zonas—.
Medido: los dos a **543 × 402** y sección **1.152** contra 1.196 en móvil: apenas baja, porque el
banner se lleva 480 de los 1.152.

- ⚠⚠ **Y salió algo que no es de escritorio: la portada y la página publican precios distintos del
  mismo pack.** 04 lleva **11 y 15 €**, especiales **13 y 18** y señal **30**, que eran valores de
  ejemplo; `/cumpleanos` lleva los reales del catálogo —**14,95 y 15,95**, especiales **16,95 y
  19,95**, señal **50**— desde que se cerró con contenido real. El dibujo usa los reales. **Hay que
  actualizar los valores por defecto de `Cumpleanos PJP`**: publicar la misma cifra de dos maneras es
  peor que no publicarla.

**06 (`5b`) · otra vez el plan describía una pieza apagada.** «La chapa al lado de la opinión», y la
opinión la quitó el recorte (280 px). Mismo caso que 05 y misma respuesta: **al lado no cuesta
alto**, así que vuelve — sin ella la chapa se quedaba sola ocupando 1120 px de ancho para decir un
número. Reparto **4 + 8** con tope de 65ch en el texto. Medido: chapa **352 × 163**, opinión
**736 × 281**, sección **503** contra 726 en móvil — **la opinión vuelve y la sección pierde 223 px**.

- ⚠ **Dos divergencias declaradas, y las dos son de la fuente** (no fallos del escritorio):
  `Resenas PJP` **enlaza el nombre del autor** a `#perfil` con **24 px** de alto, contra la regla dura
  de 48 sin excepciones — aquí es **texto**, y con eso la opinión pasa de **dos** enlaces de salida a
  **uno**. Lo segundo tampoco es regla heredada: es decisión de esta pasada.
- ⚠⚠ **Y queda un defecto real en una sección cerrada**: ese pulsable de 24 px está **publicado en
  móvil**, en `Resenas PJP` —opciones 1a, 2a y 2b (líneas 87, 180, 282, más una variante a 15px en
  378)—. Hay que decidir allí si el nombre **deja de ser enlace** o **sube a 48**; el escritorio no
  lo arregla.
- ⚠ **Fallo propio corregido en el sitio**: la respuesta del acordeón de `5c` iba a `line-height:
  1.55` y el componente 06 la tiene a **1.5** — corregido, porque la nota dice que la respuesta
  conserva los valores del componente.

**Y un fallo de método en `5a`, corregido: la primera pasada no era el marcado de 7b y la nota decía
que sí.** Redibujada de memoria, cambiaba **siete valores** de una sección cerrada sin declararlo: el
punto de Kids de lima oscura (`#627411`) a cian, los tres incluidos de **tinta plena a gris
secundario** en las dos tarjetas —y son lo que vende el pack, no un detalle—, el «en tarifa especial»
igual, el enlace de Jump a un `#7FD4F5` que no existe en la fuente, y los dos filetes de grosor.
Restaurados y medidos en el DOM. Es exactamente lo que persigue la regla del proyecto: **una pieza que
ya existe se copia de su marcado, no se redibuja de memoria** — y su corolario, que **al describir la
copia hay que enumerar lo que cambia, no lo que uno cree que cambia**.

**08 (`5c`) · la única que de verdad solo se ensancha, y aun así no se estira.** A 1120 la respuesta
más larga daría líneas de **140 caracteres** contra el máximo de 70 del sistema: cabecera en cuatro
columnas y acordeón en **ocho** (736 → unos 95, y el tope de 70ch los corta donde toca). La pregunta
se queda en 18/700 y la respuesta en 16 porque son valores del componente 06 —copiar una pieza es
copiar sus valores, igual que la tabla de horarios de 07—; lo único que crece es el relleno, a
18/24. Medido: sección **335** contra 472 en móvil, con las cuatro preguntas a 68 de alto.

- Pendiente del dueño: **la foto de 04 a 21:9**, que **vuelva la opinión** en 06 y —lo que no es de
  escritorio— **los precios de 04**, que hay que igualar a los del catálogo.

### 03 Qué hay dentro en escritorio · turno 4 · **aprobada la `4b`** (8 sep 2026)

✅ **Aprobado**: entran **la cifra y la puerta**, la ficha es **modal centrado** y el titular es **«El
parque»** — el «Dentro» del turno 7 de `Juegos PJP` **ya está retirado** y su nota lo declara.
Y la geometría es la de **`4b`**.

⚠⚠ **Por qué se cayó `4a`: el velo.** El degradado es una **banda** que disuelve el borde inferior de
la sección en el papel —«hay más»—, así que las veladas tienen que ocupar **la fila entera**. En
móvil la ocupan; en `4a` la última fila tiene tres celdas y solo se velan dos, con lo que el
degradado deja de decir «la sección se acaba» y dice «estas dos fotos están borrosas». **No tiene
arreglo dentro de `4a`**: con la grande a dos columnas solo cabe un nombre más arriba y el tercero
cae por fuerza en la fila de las veladas. La única geometría con los tres nombres arriba y la fila
de abajo entera velada es `4b`. Lo vio el dueño mirándolo, no la aritmética: **un efecto que depende
de ocupar una fila entera se rompe al cambiar el número de columnas**, y eso hay que comprobarlo en
cada reflujo.

**El mosaico a tres pistas.** La grande ocupa dos columnas y mantiene su 3:2 (**736 × 490**) y la
segunda **se estira a 352 × 490** para cerrar la fila: una cuadrada de 352 dejaría **138 px de hueco**
debajo. Las otras tres van cuadradas a 352 y las dos últimas siguen velándose hacia el papel —sin
nombre y sin enlace, que es la condición con la que entró el velo—. El ratio de una celda de mosaico
no es un valor del sistema: lo deja la retícula. Medido: mosaico **873** de alto y sección **1.385**
con la pareja del bar dentro, contra 1.395 en móvil — **es la única sección que no se acorta al
ensanchar**, y tiene sentido: aquí el contenido son las fotos y al ensanchar las fotos crecen.

- **Lo que sube de nivel**: el nombre de la grande de Bungee 20 a **36** (Título — en móvil iba a 20
  porque la celda medía 356 y 20 es el suelo de Bungee), los nombres pequeños a **19** en Hanken 800
  (el valor que ya usa `/atracciones` en escritorio), la zona a **12** y el relleno de la banda de
  10/12 a 16/20. La pregunta de la pareja, de 22 a **26** (Subtítulo).
- **La hoja de ficha se convierte en modal centrado de 560** (`componente.modal.anchoMax`), radio 16
  en las cuatro esquinas, sombra de modal y **sin el asa** — el asa promete arrastre y un modal
  centrado no se arrastra. Se mantiene lo que la pieza es: papel, sin botones, cierre de 48, velo
  .72, Escape y clic fuera. ⚠ Divergencia declarada con Componentes 06, que solo describe la forma
  de móvil.
- ⚠⚠ **Dibujarlo dejó ver la deuda del recorte.** Con la chapa del «18 más» apagada, la sección
  enseña cinco fotos y **no dice en ningún sitio que hay 23**, y `/atracciones` se quedó **sin
  puerta** (su única entrada es la tarjeta de Zonas). Y aquí la chapa **no puede volver como en 05**:
  allí había una columna vacía al lado y volvía gratis; el mosaico ocupa el ancho entero, así que
  costaría otra vez sus 253 px. Lo que entra en su lugar es lo que la deuda ya pedía: **la cifra en
  la entradilla** —un número por frase, como fija la sección— y **una sola puerta** bajo el mosaico.
  Los dos números son **dato** (`atraccionesJump` + `atraccionesKids`), no texto escrito.
- ⚠ **Dos titulares para la misma sección**: 6a dice **«El parque»** a propósito —«Dentro» repite la
  palabra del rótulo— y el turno 7 de Juegos (el del bar) volvió a escribir «Dentro». Una de las dos
  hay que retirarla; el dibujo usa «El parque».
- Pendiente del dueño: nada de esta sección. Lo que falta sigue siendo **dato**: los nombres reales
  de las atracciones, el contenido del «18 más» y el nombre real del bar.

**`4b` · la geometría aprobada, y sale de mirar las fotos que hay: todas son apaisadas.** En `4a` la
segunda se
estira a 352 × 490 para cerrar la fila, y eso no es un encuadre sino **un recorte vertical de una
foto horizontal**: de una piscina de bolas se ve una franja central y se pierde lo que la vende,
que es cuánta hay. En `4b` la columna de la derecha **se parte en dos** —352 × 229 cada una, 3:2,
**la misma proporción que la grande**— y las dos veladas pasan a **franja** (736 en 16:5 y 352
estirada), que es lo que ya son: textura que dice «hay más». Todo fluido, sin un solo alto escrito:
las dos de la derecha van con `flex:1` dentro de la fila. Medido: mosaico **751** contra 873 y
sección **1.263** contra 1.385 — **122 px menos** y tres nombres igual de legibles. A 1024:
612 × 408 + dos de 290 × 188 + franjas de 191, sección 1.143, sin desbordes.

### 05 y 07 en escritorio · turno 3, **aprobado** (7 sep 2026)

Las dos son «el objeto y lo que lleva dentro», y las dos traen un hallazgo al leer su marcado.

**05 (`3a`) · el plan describía una pieza apagada.** ✅ Aprobado: **la chapa vuelve en escritorio**.
⚠⚠ El turno 1 escribió «el QR a un lado, lo que ve
el empleado al otro» y **esa chapa la apagó el presupuesto** (428 px) *después*: la segunda columna
estaba vacía y el plan hablaba de algo que ya no sale. Y el recorte **no aplica aquí**: el techo de 11
pantallas se mide a 390 × 844 y en escritorio la chapa **no ocupa alto**, va al lado. Vuelve, con
interruptor propio (`chapaEmpleado`, encendido) y **sin tocar** el de móvil (`conChapaEmpleado`,
apagado): son dos superficies y dos decisiones. Medido: columnas de **374** y **425**, sección
**722 px** — o sea que la chapa vuelve y la sección **mide lo mismo que en móvil sin ella** (726).
El aviso de los calcetines baja a la columna del objeto, que es de lo que habla, y con eso las dos
columnas se igualan (antes 252 contra 425).

**07 (`3b`) · una divergencia que se arregla sola al haber sitio.** El «abierto ahora» compartía
nivel con el titular —declarado en la ficha de 07— porque en móvil no hay nada legal por encima de
34: el 42 es Display XL y lo gasta el hero. En escritorio el titular es 52, así que el dato pasa a
**Título 36**, que es literalmente «titular de un bloque dentro de una sección»: **crece 2 px
respecto al móvil y recupera la jerarquía**. Lo demás se copia entero —los cuatro estados, el día
resaltado solo mientras su horario está vigente, las dos filas sin agrupar «finde», el aviso del
festivo fuera del pliegue— y el día a 15,5/700 con las horas en mono 15 **no se toca**, porque son
del componente 08. Medido: horario **365**, mapa con dirección **468**, sección **633** px contra
**906** en móvil — 273 menos sin quitar nada.

- **La dirección del dibujo es la publicada** —**Ctra. de Granada, 30813 Lorca, Murcia**— y ✅ el
  dueño la aprobó: **ya está aplicada en `Visitanos PJP`** y su nota lo declara. La de ejemplo que
  había viva ahí («Avenida de Europa, 14 · Polígono Saprelorca») está retirada; queda pendiente solo
  el desglose exacto en `line1`/`line2`.
- **La regla que cerró 07 se mantiene en las dos ramas**: dirección y aparcamiento **siempre fuera
  del iframe**, con mapa y sin mapa — un bloqueador tumba el widget con las cookies aceptadas y
  entonces no salta ningún aviso, sale un hueco.
- **A 1024** (columna 936) las dos columnas dan 451 y nada desborda: 771 y 630 de alto.

### 02 Tarifas en escritorio · turno 2, **aprobada la `2b`** (7 sep 2026)

⚠⚠ **La nota con la que se planificó esta sección estaba mal, y el número lo desmiente: el carril NO
se sale de la columna.** Los 1.358 px eran los **cinco productos del catálogo** puestos en fila
(5 × 262 + 4 × 12), y la sección enseña **una zona a la vez** — lo cerró su pestaña, y antes lo cerró
Zonas con «un control vive donde se ve su efecto». Sumando el marcado de 6a: Kids son 302 + 262 + 262
con dos huecos de 12 = **850**; Jump son **576**. Las dos caben en 1.120 y sobra sitio. Así que en
escritorio no pasa que el carril se salga: **el carril deja de ser carril**. Nada hace scroll, y se
quedan sin sujeto el `scroll-snap`, los **64 de asoma** y el foco que ponía el arrastre.

| | Qué hace | Medido en el DOM |
|---|---|---|
| `2a` | El mecanismo de móvil sin el gesto: una viva y las otras en Nube al 94 %, foco por cursor y por Tab, el naranja viaja | enfocada 351 × 398 · velada 330 × 374 · su botón **49**, o sea que el 52 sigue teniendo motivo · sección **651** |
| `2b` **(aprobada)** | Las tres enteras: el velo decía «las otras están medio fuera de cuadro» y aquí no lo están. Un relleno naranja en la destacada y dos fantasmas de papel; el CTA vuelve a **56**, el botón L | tres de 351 × 402 · sección **655** (móvil: 616) |

**Lo que cambia al ensanchar sale del token, no de la mano**: la pista pasa de 262/302 a la **columna
de cuatro** (4 × 64 + 3 × 32 = **352**) · relleno de tarjeta **14 → 24** (`componente.tarjeta.pad`) y
aire interior **10 → 12**, que en móvil estaban los dos fuera de la escala · los mono de 11 y 10,5
suben a **12** (Etiqueta) · la cifra **44 → 52**, y no es un valor nuevo: `displayL.uso` dice
«cabecera de sección y de bloque, **y cifra grande**» · día y tarifa especial **15 → 17** (Cuerpo) ·
y entran **estados de cursor que en móvil no existían**: naranja a `#D56319`, pulsado `#B85615`, y el
fantasma se rellena en Azul Muro con texto blanco.

- **La fila tiene tres pistas siempre**, las de la zona con más productos: así la tarjeta no cambia
  de tamaño al cambiar de pestaña y Jump deja la tercera vacía. Al cambiar de zona **el foco vuelve
  a la destacada**, o Jump —que tiene dos— se queda sin ninguna enfocada, o sea sin el único naranja
  de la pantalla.
- **A 1024 la columna da 936 y la pista 290**: nada desborda y la tarjeta no gana ni una línea, así
  que la fila aguanta de 1024 a 1920 **sin un salto intermedio**.
- **El racimo no obliga a nada**: su «Reservar» es relleno de **tinta** (Marco 1b), no naranja. Por
  eso en escritorio no hay nada que retirar mientras Tarifas está a la vista —que es lo que hacía la
  barra del móvil— y el mapa del naranja se cumple con el CTA de la tarjeta.
- ✅ **Hallazgo de paso, y resuelto: la pestaña de 02 no era la del sistema.** Componentes 03 y
  `/atracciones` usan pista en Nube con la activa en blanco (y a **352** en escritorio); 02 dibujaba
  **dos cápsulas** con la activa en tinta maciza. Decisión del dueño: **manda el sistema**. Aplicado
  en los **trece** sitios de `Precios PJP` —no solo en 6a, porque el archivo entero hablaba el
  idioma viejo— y el valor entra en `componente.pestana` (**tokens v1.7**), que es lo que faltaba:
  la pieza estaba dibujada en Componentes y **no tenía token**, así que no había de dónde leerla.
  La inactiva es Humo sobre Nube y da **4,50** calculado — justo el umbral, o sea que ese gris no se
  aclara ni un paso. `Componentes` 03 gana la línea del ancho de escritorio, que nunca decía nada.
- **Faltaba el bloque de `prefers-reduced-motion` en el archivo**, y desde el turno 1: las tarjetas
  de Zonas se aplastan al pulsarlas y nadie respetaba la preferencia. Puesto, con el mismo texto que
  Zonas — la regla del proyecto es que una premisa medida caduca, y ésta caducó al dibujar.

**Método · lo que costó (y es regla nueva).** ⚠⚠ **Una cifra se mide sobre lo que la pantalla
ENSEÑA, no sobre lo que el catálogo tiene.** El 1.358 era aritmética correcta del sujeto equivocado
—los cinco productos del catálogo— y con él se planificó la sección entera («se sale a sangre, con
la enfocada centrada»), se escribió en la tabla del turno 1 y se copió **dos veces** a este archivo.
La pestaña llevaba dos turnos diciendo que en pantalla hay una zona. El coste: un turno planificado
al revés. Y el corolario: **al corregir el número hay que borrar todo lo que colgaba de él** — la
fila de la tabla de `1b`, el `dv-next` del turno 1 y los dos sitios de este archivo, hechos.

### ⚠⚠ El presupuesto de pantalla · medido pieza a pieza, y el techo se contradice consigo mismo

Archivo: `Presupuesto Portada PJP.dc.html`, turno 1. **Ruta `1b` aprobada y APLICADA (7 sep).**
Resultado medido: la portada pasa de **12,92 a 9,86 pantallas**, con el techo nuevo en 11 — o sea
1,14 de margen. Salió mejor que lo prometido (10,07) porque quitar un bloque se lleva también sus
márgenes: los siete recortes valían 2.404 estimados y **2.583 medidos**.

| Sección | antes | después |
|---|---|---|
| 02 Tarifas | 947 | **616** |
| 03 Qué hay dentro | 1.052 | **775** |
| 04 Cumpleaños | 1.605 | **1.153** |
| 05 Antes de venir | 1.180 | **726** |
| 06 Reseñas | 756 | **439** |
| 01, 07 y 08 | — | sin tocar |

Lo aplicado, pieza a pieza: **el aire** de 160 a 96 en móvil (y 240 → 144 en escritorio, derivado
para mantener la proporción 1,5×) · **el hero** con techo de 1 pantalla (844) en vez de 1,36 ·
**02** fuera la chapa de zona · **03** fuera la chapa del «18 más» · **04** fuera el reloj y el
aviso INFO · **05** fuera la chapa del empleado · **06** fuera la opinión y sus controles.

⚠ **Ninguna pieza se ha borrado**: cada una vive detrás de un interruptor apagado por defecto
(`conChapaZona`, `conChapa18`, `conRelojFiesta`, `conAvisoInfo`, `conChapaEmpleado`,
`conCarrusel`), así que el dueño puede volver a verla y la decisión es reversible sin rehacer nada.
Y la nota de cada sección tocada **declara el recorte**, porque su prosa sigue describiendo la pieza.

⚠ **Lo que hay que rehacer en consecuencia**, y no es diseño de esta pasada: el «y N más» de 03 y
las dos puertas a `/atracciones` se cayeron con su chapa — la cifra tiene que subir al titular o a
la entradilla, y las dos puertas hay que colocarlas —; y el reloj de la fiesta y el aviso INFO
tienen que aparecer en `/cumpleanos`, que es a donde se han mandado.

### Cómo se llegó aquí (el turno 1, que sigue siendo el registro)

Desglosadas las ocho secciones bloque a bloque (`1a` lo dibuja), sale la cuenta entera: las ocho
suman **8.145 px**, el aire entre ellas 1.120, el hero 1.148 y el cierre 488 → **10.901 px = 12,92
pantallas** contra un techo de **8**. Y sale también el hallazgo que cambia la pregunta:

⚠ **Dos reglas del mismo documento no pueden ser ciertas a la vez.** El Sistema de Contenido pone
«≤ 8 pantallas de portada» y, tres tarjetas más allá, «una pregunta contestada por pantalla; si
hacen falta dos, son dos pantallas» — y las preguntas son **nueve**. Nueve, más el hero y el
cierre, son **once como suelo**, antes de dibujar. El 8 no se midió sobre esta portada: viene del
prototipo que dio 8,5, y ese prototipo **no tenía reseñas, ni dudas, ni el bar, ni el registro**:
cuatro de las nueve preguntas nacieron después del techo. Además, **las ocho secciones solas son
9,65 pantallas**, así que el 8 es inalcanzable aunque el hero, el aire y el cierre valieran cero.

**Las dos rutas, con su aritmética medida:**

| | Qué hace | Aterriza en |
|---|---|---|
| `1b` **(recomendada)** | Techo a **11** —una pantalla por pregunta— y siete recortes medidos: aire 160→96 (448), hero a 1,00 (304), la chapa de zona de 02 (307), la chapa del «18 más» de 03 (253), el reloj y el aviso INFO de 04 (384), la chapa del empleado de 05 (428) y el carrusel de 06 (280) | **10,07** de 11 · ninguna respuesta se pierde |
| `1c` | Mantener el **8**: los recortes de `1b` más **06 entera fuera** (756) y **05 reducida a una línea** (1.120) | **7,84** de 8 · dos respuestas dejan de contestarse arriba y **reabre dos secciones cerradas** |

Ninguna de las dos toca las fotos de Zonas (364) ni el mapa de 07 (292), que eran las candidatas
siguientes y no hacen falta. Y los recortes de `1b` no borran nada: se van repeticiones —la chapa de
zona de Tarifas repite la edad, la altura y el 1,30 que Zonas cuenta 1.195 px antes— y material que
ya tiene página esperándolo (el reloj y el aviso son de `/cumpleanos`).

⚠ Si se elige `1b` hay que **corregir el Sistema de Contenido** (su tarjeta del «≤ 8») y escribir
por qué, o dentro de dos meses alguien lo lee y cree que la portada está mal.

### La línea base, para no volver a medir a ojo

Con la cabecera aplicada, medidas **las ocho con la misma sonda** (hasta ahora cada sección se midió
con la suya):

| Sección | px a 390 | pantallas |
|---|---|---|
| 01 Zonas | 1.195 | 1,42 |
| 02 Tarifas | 947 | 1,12 |
| 03 Qué hay dentro | 1.052 | 1,25 |
| 04 Cumpleaños | 1.605 | 1,90 |
| 05 Antes de venir | 1.180 | 1,40 |
| 06 Reseñas | 756 | 0,90 |
| 07 Visítanos | 906 | 1,07 |
| 08 Dudas | 504 | 0,60 |
| **suma** | **8.145** | **9,65** |

Sumando el aire de sección (7 × 160), el hero (1.148) y el cierre (488), la portada da **10.901 px
= 12,9 pantallas** — exactamente lo que medía la portada vieja, y el presupuesto del Sistema de
Contenido es **≤ 8**. Tres avisos sobre esta cifra, para no usarla mal: la sonda mide la tarjeta de
la maqueta, que trae su propio relleno arriba y abajo; el aire real entre secciones puede no ser
160 en todas; y el hero es el número ya publicado (1,36 pantallas), no medido hoy. Aun con margen
de error, **la conclusión no cambia: sobran cuatro pantallas**. Los tres sospechosos son 04
(1,90 — es la sección más grande y la que más dinero deja), 01 (1,42) y 05 (1,40). Es una decisión
del dueño, no un recorte de maquetación, y hay que tomarla antes de vestir: **vestir ocho secciones
para luego cortar dos es trabajo tirado.**

**Lo que queda de la pasada**, por orden: **02 Tarifas** (hecha en el turno 2, y sin sangre: ver
abajo) · **05** y **07** en dos columnas (hechas en el turno 3) · **03** el
mosaico a tres · **04**, **06** y **08** solo se ensanchan (08 con la cabecera en cuatro columnas y
el acordeón en ocho, porque estirarlo daría líneas de 140 caracteres) · y los tres agujeros del
marco: **el menú de escritorio no existe**, el hero a 1920 y el pie, que deja de deslizarse.

- **Una pieza que ya existe se copia de su marcado, no se redibuja de memoria** — y **con sus
  fuentes**: el eslogan del cierre tenía el `font-family` correcto y `Permanent Marker` no estaba en
  el helmet del archivo nuevo, así que el navegador lo pintaba con otra. Un fallo que **no se ve
  leyendo el código**; se ve mirando la pantalla o preguntando al DOM.

### Método · lo que costó esta pasada

- ⚠⚠ **Dibujé la tarjeta de Zonas desde su DESCRIPCIÓN y no desde su marcado.** Se perdieron el
  borde de pegatina, el sello rectangular de tres líneas —y con él la tarifa especial—, la línea de
  juegos, el velo de territorio, los dos destinos distintos y el manejador inerte. La regla del
  proyecto ya lo decía para lo publicado («antes de razonar sobre lo que el cliente ya tiene,
  verlo»); vale igual para lo aprobado en este mismo repositorio.
- ⚠ **Una sonda que busca por color no ve las dos ramas de un `sc-if`.** La primera medición dio 32
  para 04 porque buscaba el rótulo por el gris de PAPEL, y 04 es la única sección con cabecera
  **sobre foto**: su rama por defecto pinta 30 con el gris de tinta. Publicar «tres valores» cuando
  eran cuatro debilitaba el argumento con un número mal medido.
- **Un número escrito dos veces envejece a la primera.** Al ensanchar la escala de 56 a 64 se
  corrigieron el dibujo y dos notas, y la tabla se quedó publicando el reparto viejo. Se arregló en
  la causa: el reparto se deriva.
- **Los ticks colocaban el borde de su caja donde iba su centro**, así que el 1,90 caía 8 px por
  debajo del tope del eje y el 0 se salía 13 por abajo. Y la franja vive **dentro** del borde de 2
  de la pegatina mientras el eje va fuera: 2 px de desfase que solo se ven midiendo.
- **`align-items: flex-start` no garantiza dos columnas iguales.** Coincidían por casualidad de
  copia; con una línea más de texto se descuadraban 25 px. `stretch` no rompe el eje porque el eje
  tiene alto propio.

## El marco de la portada · **aprobado** (sep 2026)

Aprobado por el dueño con **seis cambios**, aplicados sobre las mismas piezas del turno 1 y
registrados en el **turno 2** (`2a`) del archivo:

1. **El eslogan pasa al cierre y al menú**, y **sale del hero**. La regla dura decía «una vez por
   página» y esto son dos, así que pasa a **una vez por superficie** — el menú tapa la página
   entera; es el mismo cambio que ya se hizo con el contrato del par.
2. **El logo mantiene animación, pero sin bucle**: bote de entrada una vez (420 ms, curva de lona,
   squash que conserva volumen) y nada más. Se apaga con `prefers-reduced-motion`. De paso, el
   bucle de 4600 ms sigue siendo el único tiempo fuera de la escala.
3. **La barra del móvil pierde el fondo** — que era ya lo escrito: «sin fondo» es el contenedor, no
   las piezas. Al quitarlo, cada mitad se queda con su **superficie opaca** (el fantasma pasa de
   transparente a blanco macizo: sobre el contenido que pasa por detrás su azul da 2,62) y con la
   **sombra del cuarto rol** — naranja `0 10px 28px ·,24`, papel `0 10px 26px ·,18`.
4. **El par de CTA sale del menú.** El menú es una lista de destinos y el par no es un destino; al
   cerrarlo la barra sigue debajo, y «Registrarse» no se pierde —entero en el hero, plegado en la
   barra—. Interruptor `parEnMenu` para ver lo que había.
5. **El idioma entra en el menú y se queda también en el pie** (decisión del dueño, sep 2026). En el
   pie funcionan **sin JavaScript** y los ve Google; el menú necesita JS para abrirse.
6. **El teléfono y el WhatsApp salen del cierre**, a contacto y al pie. Consecuencia: **el argumento
   de 07 caduca** — preguntar ya no vive en el cierre, sino en el menú y en el pie.

Y los tres que él planteó como pregunta: el juego **no tapa los CTA** porque el orden ya es titular
→ botón → juego (el juego es el regalo, no la salida); el **crecimiento del cierre a pantalla
completa** ya está — es el tercer factor del mecanismo del hero, medido en 0,19 sobre el progreso
crudo, y por eso `armazon-y-menu.md` §9.2 sigue caducada.

### Sigue pendiente del dueño

Solo dos: las **dos cruces del menú** (Tarifas y Cumpleaños son sección y página) y el **alto del
par en teléfono** (48 o 56, dos documentos no coinciden). El eslogan, el idioma, el teléfono y el
nombre de la página de grupos **ya están decididos**.

**La página de grupos se llama `/servicios`** (decisión del dueño, sep 2026): «lo que tiene ahora».
Con eso queda arreglado el enlace roto que ya está publicado en la respuesta de Dudas sobre grupos
—mandaba a «la página de Servicios», que no existía— y **sin tocar la respuesta**. ⚠ El precio, dicho:
el rótulo no dice «grupos» ni «colegios», que es lo que se busca, y es un cajón que se llena de
cualquier cosa. En el menú y el pie entra como **Servicios**.

## El marco de la portada · montado, pendiente de aprobar (sep 2026)

Archivo: `Marco Portada PJP.dc.html`, turno 1, seis piezas: `1a` hero · `1b` racimo de escritorio ·
`1c` barra doble del móvil · `1d` menú a pantalla completa **en móvil** · `1e` cierre y pie ·
`1f` la tabla de dónde va cada cosa. Todo a 390.

**No se diseñó de cero: tres de las cuatro piezas ya estaban decididas y construidas** con firma del
dueño y con medidas (menú 2c·1, racimo 2c·2, par de CTA 2c·7 y 2c·8, cierre con imán y minijuego
`#231`, `#235`, `#252`, `#256`). El trabajo fue pasarlas por el sistema. Lo único que nunca se
había diseñado es **el menú en móvil**.

### Lo que queda fijado

- **El hero y el armazón son UN mecanismo.** Mismo recorrido de 420 px, ventana `0,18 → 0,62`
  (`nb = (bruto − 0,18) / 0,44`), cúbica de salida `ne = 1 − (1 − nb)³`, opacidad `v`,
  `translateY(−16 + 16·v)` y pulsable solo con `v > 0,85` — `opacity: 0` sigue recibiendo clics.
  Verificado por aritmética: a `scrollY 120` la fórmula da 0,5617 y el navegador midió 0,561.
- ⚠ **`armazon-y-menu.md` §9.2 está caducada.** Dice que el tercer factor —`(1 − salida)³`, el
  crecimiento del hero del cierre— «no se implementa porque no lo tenemos». `#251` lo implementó y
  lo midió: al 7 % del crecimiento el armazón valía 0,82 lineal, 0,55 con la cúbica sobre el
  progreso suavizado y **0,19** sobre el crudo, que es el del mockup. Hay que corregir esa línea.
- **En «Entrada» no hay logo, ni menú, ni barra**, y eso es lo que **obliga** al hero a llevar sus
  dos puertas: decisión del dueño del 28 ago («el CTA y el logo se ocultan a pantalla completa»),
  más `#216`, que reabrió `#195`. Las dos mitades no se separan.
- **El par de CTA se mantiene doble.** Decidido dos veces: el 27 ago con palabras del dueño y el 28
  contra su propio artboard de móvil, que pedía «3 iconos + Reservar». Expandido 224 (182 en ≤1100,
  138 en teléfono) · plegado 56 (13+30+13) · alto 54, 56 en la barra. **El par es UNO**: cabecera y
  barra comparten estado.
- **Rótulos**: «Reservar» con su precio debajo, y «Registrarse» con «o inicia sesión». «Entrar o
  registrarse» en el rótulo se sale 58 px de los 224. Y **Hanken 800 a 16, no Bungee 17**: «Bungee
  nunca en un botón» es regla dura.
- **El par abre Reservar, no la cuenta.** Estaba en la cuenta por la frase del lanzamiento («que se
  registren rápido y ya»), pero en el parque se lleva al cliente directo a la dirección del
  registro, así que **el visitante del mandado no pasa por la portada** — y la Arquitectura ya tenía
  fichado el defecto contrario: «al que viene a mirar le pides el trámite». El registro se queda
  plegado en 56 y **entero en el hero**, que es donde la Arquitectura dice que la landing le pone la
  puerta.
- **En el hero el par NO pliega.** El plegado existe para compartir sitio con el contenido para
  siempre; el hero no comparte. Plegarlo borraba la palabra «Registrarse» de la portada entera y
  dejaba como única vía un icono sin texto que además no lleva al registro: alterna el par. Las dos
  puertas enteras caben — 94 + 112 + 10 = 216 de los 316.
- **El mobiliario flotante no lleva fondo, pero cada pieza trae su superficie opaca.** «Sin fondo»
  es el contenedor: la barra se disolvió en 2c·2 y eso arregló el salto al contenido en 12 de 12
  vistas. Transparente no puede ser —«nada de texto de color sobre foto sin capa de tinta debajo»—,
  así que sobre el vídeo la mitad de la cuenta va en **blanco macizo**, no en su fantasma `#0A5C93`,
  que sobre el velo del hero da **2,62**.
- **El contrato «papel siempre» pasa a ser por superficie.** Se escribió en `#216`/`#217` cuando el
  par vivía solo en el racimo; con montajes dentro de tinta (hero, menú) el mueble lee los alias de
  su superficie. Un mueble, cuatro montajes, dos superficies.
- **El menú en móvil**: dos grupos, «Esta página» (baja a una sección, flecha abajo) y «Páginas»
  (te saca de aquí, flecha derecha, con la URL debajo). Bungee 30, los rótulos **envuelven** —los
  destinos los pone el panel y hay «Excursiones de colegio»—, vela abajo porque la lista no cabe
  (33 % oculto), y cerrado sale del orden de tabulación con `visibility`, nunca con un recorte ni
  con `aria-hidden`. Los números van **01 · 03 · 05 · 07 · 08**, salteados: no numeran la lista,
  son el rótulo que esa sección lleva escrito en la portada.
- **El cierre**: titular en **tres partes apiladas** y en Display L —el XL es uno por página y lo
  gasta el hero—; el juego arranca su demo cuando el lienzo **se ve**, no cuando la tarjeta se
  ancla; el espacio arranca la partida y tocar el lienzo **no**. Con «reducir movimiento» el hueco
  baja a 24 y el motor ni se descarga. Mide 488 a 390.
- **El pie**: una fila que se desliza (los 13 destinos piden 1.266 y la columna da 356), su fila de
  contacto **fuera de todo condicional** —teléfono, correo e idioma— y la fila legal con
  **«Configuración de cookies»**, que no es trámite: el mapa de 07 y las reseñas de 06 viven detrás
  del consentimiento, así que quien dijo no no tenía forma de cambiar de opinión.
- **El idioma vive en el pie** — tres enlaces que funcionan sin JavaScript — **y desde sep 2026
  también en el menú**, por decisión del dueño. En el pie se queda por el motivo original: sin JS y
  para Google.

### El hero · las cuatro condiciones del vídeo (siguen en pie)

Mide **1,36 pantallas en móvil** y lleva un vídeo de 10 s del parque. **Se queda** —es el mejor
activo: demuestra en diez segundos lo que 23 tarjetas no—, con cuatro condiciones del sistema:
sin sonido y con capa de tinta bajo el titular; **el póster, la mejor imagen del parque** (hoy es
la cafetería, y es lo primero que ve quien tiene mala red); **4 a 6 planos, no quince** (el corte
muy rápido va contra el «limpio y entendible», y a partir de tres destellos por segundo es un
problema de accesibilidad); y **con «reducir movimiento» activado se queda el póster**. La coreografía y el
par ya están decididos arriba; **el eslogan sigue abierto**.

### Deudas que esto abre en los documentos del sistema

1. **Entra un CUARTO rol de elevación: el mobiliario flotante** (`--shadow-nav-*`). Lo abrió `#217`
   —el mockup del dueño las pintaba difusas y él resolvió la contradicción a favor del mockup—, y
   ahí se escribió que **M-05 deja de cubrir a este mueble «porque el propio cliente lo dibuja
   así»**. Valores puestos: relleno de tinta `0 10px 28px ·,24` → `0 18px 40px ·,32`; papel
   `0 10px 26px ·,18` → `0 16px 36px ·,26`; menú `0 10px 28px ·,16`, y su cursor levanta el botón
   en vez de tocar la sombra. **`Espacio y Retícula` §06 sigue titulando «Nada flota, salvo el
   modal» y listando dos niveles: son cuatro.** Y `tokens-pjp.js` solo tiene `dura`, `duraS`,
   `duraHover`, `modal` y `nada`.
2. **El anillo de foco no vale para el mueble flotante.** Va por fuera (`outline-offset: 2px`), así
   que se dibuja sobre lo que haya detrás: el de papel sobre tinta da **2,62** y el de tinta sobre
   papel **1,49**. Propuesta: para esta familia y solo esta, el anillo entra hacia dentro
   (`outline-offset: -3px`). **Toca una regla dura, así que lo decide el dueño.**
3. **4600 ms no está en la escala de ocho tiempos.** Es el bucle de la invitación del par (asomo +
   aro). O entra en tokens como noveno tiempo, o ese bucle no es de sistema.
4. **El inventario de páginas pasa de seis a ocho.** `1f` pasó los candidatos por las tres pruebas
   del Sistema de Contenido: **las 23 actividades** (1·1·1) y **las excursiones de grupo** (1·1·1)
   las pasan. Si se aprueban hay que escribirlas en `Arquitectura de Contenido` §05 y en la tabla
   del `Sistema de Contenido`, no dejarlas viviendo en el menú.
5. **Dos secciones cerradas tienen enlaces que no apuntan a nada**, y las dos quieren el mismo
   destino: la tarjeta-enlace de **Zonas 4a** y las dos puertas del «18 más» de **03**
   (`#atracciones-jump`, `#atracciones-kids`). Propuesta: **una** página `/atracciones` con la zona
   en la URL — la pregunta es una («¿qué hay en mi zona?») y la zona ya viene elegida.
6. **«La página del parque» no existe: es la portada.** Y las 23 actividades **no van en la
   portada**: 03 demuestra con cinco fotos y la cifra, y 23 fichas con foto son unas cuatro
   pantallas de las **ocho** que el sistema da a toda la portada.

### Pendiente del dueño

- **El eslogan a rotulador.** Tres candidatos para una plaza: la Arquitectura dice que su sitio es
  el **cierre** («el único momento de sorpresa de la página, y el sitio del eslogan») y playjump.es
  lo tiene en el **hero** y en el **menú**. Es una vez por página. El interruptor las enseña.
- **El nombre de la página de grupos**: `/grupos` dice lo que es; `/servicios` es lo que la web ya
  promete en Dudas, pero es un cajón que se llena de cualquier cosa.
- **Las dos cruces del menú**: Tarifas y Cumpleaños son sección **y** página (aquí apuntan a la
  página, «la versión larga»), y Visítanos apunta a la **sección** —su sitio ES su sección, en
  vivo— con `/contacto` aparte.
- **El bar sigue condicionado**: su veredicto de página se apoya en «carta, precios y unas 50
  mesas». Si es «hay cafetería» sin carta, pierde la primera y la tercera prueba y vuelve a ser una
  tarjeta de 03.
- **El alto del par en teléfono**: la spec viva pide 48 y el guion archivado midió 56. Los dos pasan
  el mínimo; hay que zanjarlo en un sitio.

### Datos reales leídos de playjump.es (sep 2026)

- **La dirección que 07 tenía pendiente**: **Ctra. de Granada, 30813 Lorca, Murcia**. Teléfono
  **+34 641 99 57 14**. Correo **info@playjump.es** — el único canal escrito, y no estaba en
  ninguna pieza.
- **Las 23 actividades**: 15 en Jump y 8 en Kids, que es exactamente el «5 + 18 más» de 03.
- ⚠ **El horario publicado NO coincide con el cerrado.** La web dice «lunes a viernes 16:30–21:30 ·
  sábado a domingo 11:00–21:30»; lo cerrado es «L–J 16:30–21:30 · V–D 11:30–21:30». Difieren en el
  viernes y en la hora de apertura del finde. Hay que decidir cuál manda.
- ⚠ **Enlace roto ya publicado**: la respuesta de Dudas sobre grupos manda a «la **página de
  Servicios**», que no existe ni en las doce URL ni en el inventario — «Servicios» es solo un
  rótulo del menú con Cumpleaños debajo.
- ⚠ **El cierre publicado tiene dos botones con el mismo destino**: «Reservas aquí» y «Llamar» van
  los dos a `tel:`. Y el par publicado dice **«Llamar»** en vez de «Reservar» porque las reservas
  están en mantenimiento: es un estado de hoy, no el diseño.

### Método · lo que costó esta sesión (el marco)

- ⚠⚠ **Discutí una pieza publicada sin haberla mirado.** Argumenté que el par doble no cabía en el
  hero móvil «con datos»; estaba en producción, en tres sitios. **Antes de razonar sobre lo que el
  cliente ya tiene, verlo.**
- ⚠⚠ **Publiqué cinco cifras diciendo «lo calculo»** y las cinco estaban mal (fila 316 y no 346,
  152 y no 167, 94/112/178 y no 103/127/186): el cálculo usó el relleno de la tarjeta y no el del
  bloque de vídeo. **Y luego reciclé el hueco del hero (250) para describir tres montajes que no
  había medido** — son 224, 284 y 290.
- ⚠⚠ **Las notas contradijeron a la pieza seis veces seguidas**, siempre igual: cambiar el diseño y
  dejar el párrafo que defendía el anterior. Cada vez que se retira una decisión hay que **borrar
  su argumento**, no solo su dibujo.
- ⚠ **Un `replaceText` que cortó hasta el primer `</sc-if>`** dejó 2.506 caracteres de par viejo
  mal anidados. No se veía con el valor por defecto, pero a un clic del panel el hero pintaba
  «Registrarse» dos veces y **dos rellenos naranjas**. **Sustituir un bloque por índices exige
  comprobar los cierres, y probar los dos valores del interruptor.**
- ⚠ **Inventé tres enlaces** —«Excursiones de colegio» en el menú, «Trabaja con nosotros» y
  «Tarjeta regalo» en el pie— justo después de escribir que un enlace fuera del inventario es
  relleno. **El inventario es la fuente también para el menú y el pie.**
- **Me equivoqué al aplicar M-05 al racimo.** Dije «borde y no sombra difusa»; ese mueble ya estaba
  exento por decisión del dueño desde `#217`. **Antes de invocar una regla, comprobar si la pieza
  tiene su excepción escrita.**

## La pasada de vestido · iconos, imagen y movimiento

**No se hace sección a sección: se hace sobre todas a la vez y al final**, o acaban con dos
idiomas distintos. Orden profesional: primero estructura y datos, después vestido con presupuesto,
después verificación (contraste, táctil, `prefers-reduced-motion`). Lo que entra, según los
documentos ya cerrados —no hay debate creativo, es aplicar lo escrito—:

- **Iconos**: uno de producto en la etiqueta de cada billete (reloj para 1 h y 2 h, sol para todo
  el día); el catálogo real ya guarda un icono por producto y el diseño lo perdió. Nada más: un
  icono que solo acompaña a una palabra es ruido.
- **Imagen**: una pose del kit al borde del carril, en troquel. Una por sección y sin repetir.
- **Movimiento**: cascada de entrada de las tarjetas (90 ms de desfase), aplastado de la pegatina
  al pulsar y bote del foco. Máximo dos a la vez y ningún bucle.

## Contexto del producto (carpeta `docs/`, si está montada)

PJP es la **segunda instalación** de **JumpWeb**, un producto white-label. Las reservas ocurren
en un **cajón SPA** (Vue 3 + Pinia) montado dentro del layout, cuyo **contrato visual es el árbol
del DOM**, no las clases: ahí se pueden decidir tokens y estados, no inventar marcado. El tema
del cliente viaja en `public/css/client.css` + `THEME_FONTS` + los ficheros de marca, y **no se
versiona ni se despliega**: se sube a mano y se excluye del borrado del `rsync`.
El carril de diseño del producto está **parado** esperando esta base.
