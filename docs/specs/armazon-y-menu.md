# [SPEC] El ARMAZÓN — la barra se retira y el menú pasa a pantalla completa (tanda 2c)

> Estado: ⬜ **BORRADOR** · Última actualización: 2026-08-27 · Decisión asociada:
> `DECISIONES #N` al aprobarse (**el número se fija al EMPUJAR**, mirando el remoto —
> `CONVENCIONES §10`). Hermana de `tema-por-instalacion.md`, de la que sale como **tanda 2c**
> (§7 de aquélla: *«no es adoptar una estructura: es cambiar la navegación del sitio»*).
>
> ▶ **EMPIEZA POR §1.6 y §1.7.** §1.6 es lo que el mockup **no contesta** y sin lo cual no se
> puede escribir una línea; §1.7 son **tres defectos que se importan solos** si se copia el
> mockup tal cual, y uno de ellos choca con un token que acabamos de crear (`#196`).
>
> ⚠️ **La pieza que el owner más quiere revisar —el móvil— es la única sin NINGÚN test**
> (§1.3, medido). Reescribir a ojo lo único que no tiene red es cómo se cuelan las regresiones
> que la suite no puede ver.
>
> ❗ **El móvil está `[PENDIENTE: owner]` por decisión suya**: lo guía él con un artboard
> (§5). Nada de móvil se implementa hasta que exista (tanda 2c·4).

---

## 1. Contexto y problema — MEDIDO contra el código, no supuesto

Hoy la navegación del producto es una **barra fija translúcida** en lo alto de todas las
páginas públicas. El sistema visual del segundo cliente **no tiene barra**: tiene dos racimos
de mobiliario flotando en las esquinas y un **menú a pantalla completa** que se abre con un
recorte circular. Cambiar de uno a otro no es repintar: es cambiar por dónde se navega el
sitio.

Todas las cifras de abajo salen de un instrumento que **parte el CSS en reglas** recorriéndolo
carácter a carácter con estado de comillas y profundidad de llaves —no por líneas—, con guarda
de la guarda: seis controles que tiene que pasar antes de emitir una sola cifra. En este repo
un inventario hecho con un `grep` ingenuo ya ha salido mal **cinco** veces
(`DECISIONES #143`, `#193`, `#196`).

### 1.1 El tamaño real: 12 vistas, 232 reglas, 819 declaraciones

| | Medido (2026-08-27) |
|---|---|
| Vistas que incluyen el componente de nav | **12** |
| Vistas públicas que NO lo incluyen | **1** (el post-formulario de invitados, que usa el layout enfocado) |
| Reglas CSS de las 8 familias del armazón | **232** |
| Declaraciones dentro de ellas | **819** |
| …de las cuales, dentro de un `@media` | **35** |
| Aserciones de la suite que fijan nombres de clase del armazón | **31**, en **4** ficheros |

Las ocho familias, y dónde vive cada una:

| Familia | Reglas | Decl. | Qué es |
|---|---|---|---|
| `.nav*` | 81 | 300 | la barra, la marca, los disparadores de desplegable, el chip de cuenta, la hamburguesa |
| `.plan-select*` | 29 | 127 | el panel de los dos desplegables temáticos |
| `.cta-prime*` | 27 | 65 | el CTA grande — **compartido con el hero y con la barra de móvil**, no es solo del armazón |
| `.mob-menu*` | 26 | 110 | el cajón lateral de móvil |
| `.cta-med*` | 22 | 59 | el CTA relleno del header (comprar) |
| `.cta-ghost*` | 19 | 60 | el CTA fantasma del header (registro) |
| `.lang-dd*` | 15 | 69 | el selector de idioma — **vive en el PIE**, no en el nav |
| `.book-bar*` | 13 | 29 | la barra flotante inferior de móvil |

▶ **Dos de las ocho no son del armazón y hay que decirlo antes de contar**: `.cta-prime` lo
comparte el hero (`#195`) y `.lang-dd` vive en el pie. Tocarlas desde aquí es entrar en
terreno de otra pieza.

### 1.2 El 18 % de esa CSS está MUERTA — y el primer instrumento no lo vio

**11 clases** del armazón no aparecen en Blade, ni en el JS fuente, ni en el SSR, ni en el
bundle compilado: **41 reglas · 131 declaraciones**, el **17,7 %** de las reglas del armazón.

| Clase | Reglas | Decl. |
|---|---|---|
| `.plan-select__trigger` | 6 | 25 |
| `.nav__scan` | 8 | 22 |
| `.nav__scan-ticket` | 10 | 21 |
| `.nav__logout` | 3 | 14 |
| `.nav__login` | 3 | 13 |
| `.plan-select__item-num` | 2 | 12 |
| `.nav__brand-slogan` | 2 | 10 |
| `.nav__user` | 2 | 6 |
| `.nav__reserve` | 2 | 4 |
| `.nav__user--mob` | 2 | 3 |
| `.nav__dot` | 1 | 1 |

❗❗ **Y la primera pasada del instrumento dijo 9 clases, no 11.** `.nav__scan` y `.nav__reserve`
«vivían» dentro de un **comentario de Blade** que explica que fueron sustituidos. Un corpus que
no distingue código de comentario **declara viva la deuda que el comentario está enterrando**.
▶ El instrumento pasó a limpiar comentarios de Blade, de bloque y de línea, y lleva un control
positivo que muerde exactamente ese caso (`nav__scan` **no** puede estar en el corpus limpio).
▶ **Es la sexta vez que un instrumento de inventario de este repo nace ciego.** La regla que ya
está escrita —«un `grep` que no encuentra no demuestra que no exista»— tiene ahora su simétrica:
**un `grep` que SÍ encuentra tampoco demuestra que exista**.

⚠️ **Aquí un `grep` sí es concluyente, y en el cajón no lo sería**: el armazón es Blade SSR y
sus clases se escriben enteras. El cajón construye las suyas por concatenación en Vue, y por eso
`DEUDA.md` tiene abierta una ficha de ~50 reglas que **parecen** muertas y no se pueden
confirmar así. Las 41 de aquí **sí** se pueden.

### 1.3 ❗ La pieza que más importa es la ÚNICA sin red

**Ningún test —ni PHP ni JS— toca el cajón móvil.** Medido: cero apariciones de `mob-menu`,
`mobileOpen`, `trapMobile`, `nav__burger` o de las claves de idioma del menú en `tests/` y en
los ficheros `*.test.js`. Su única mención en toda la suite es una **entrada de excepción** en
`ShapeScaleTest`, que lo declara sombra direccional.

Sin red quedan: **26 reglas · 110 declaraciones** de CSS, ~50 líneas de marcado, el **trap de
foco** (Tab cíclico), el **bloqueo de scroll** del fondo, la devolución del foco al cerrar y las
cuatro vías de cierre (✕ / telón / Escape / clic en un enlace).

▶ **Consecuencia de método, no de gusto**: la primera tanda de este trabajo **no cambia nada**,
construye la red (§8, tanda 2c·0). «Corrección antes que presentación» es principio del
proyecto, y aquí es además lo único que hace revisable el resto.

### 1.4 El armazón del mockup no es una barra: son dos racimos flotantes

Leído de `Landing PJP Modos` (artboard **normativo**, re-bajado y diffeado el 2026-08-27 a las
17:53 — ver §1.9):

- **Arriba a la izquierda**, fijo: el **logotipo**. No es una imagen: es un lockup compuesto en
  CSS con seis capas de trazo.
- **Arriba a la derecha**, fijo: un racimo de tres piezas en fila —
  **[ Reservar ] [ Registrarse ] [ ☰ Menú ]** — de 54 px de alto, canto de 10 px.
  - Los **dos CTA se turnan**: el activo mide 224 px y enseña título + subtítulo; el otro
    colapsa a **56 px**, solo icono. Pulsar el colapsado lo expande y colapsa al otro; pulsar el
    expandido **actúa**. Arranca con *Reservar* expandido → **comprar sigue siendo un solo
    gesto**; registrarse cuesta dos.
  - El de registro lleva un **guiño** mientras nadie lo ha tocado: un vaivén y un aro que se
    expande, ambos con parada.
  - La hamburguesa son **dos rayas** (19 px y 12 px) que se cruzan en aspa al abrir, con una
    etiqueta de texto al lado que dice **«Menú» / «Cerrar»**.
- **Los dos racimos aparecen y se retiran juntos**: nacen ocultos, aparecen al terminar el hero
  de cabecera, **se esconden al bajar y vuelven al subir**, y se retiran del todo al llegar al
  bloque de cierre. Con el menú o el cajón abiertos, **no se esconden**.
- **El menú es una capa a pantalla completa** que se abre con un **recorte circular** que crece
  desde el centro de la hamburguesa (0,72 s), sobre fondo tinta con trama de puntos y dos
  manchas de acento.
  - **Izquierda**: la lista de destinos, numerados `01…`, en la tipografía de display a tamaño
    enorme, con subtítulo y flecha. Entran escalonados (90 ms + 55 ms por posición). La lista
    **desplaza** si no cabe, con un velo de desvanecido abajo que solo aparece si queda algo.
  - **Derecha** (320 px): una **ficha** que cambia al pasar el ratón o al enfocar un destino —
    foto, etiqueta, titular y frase—, más el estado **«Abierto ahora · horario»** con un punto
    verde que late, el teléfono y el enlace de cómo llegar.
  - **Abajo**: una fila de **cápsulas** secundarias (acceder, idioma, redes, opiniones) y el
    eslogan a rotulador.
  - El racimo de CTA **sigue visible y por encima** con el menú abierto, y **cambia a amarillo**.

### 1.5 ⚠️ La auditoría del cliente EXCLUYE el menú y el logotipo

`Auditoría Landing PJP` lo dice en su entradilla: *«Quedan fuera por indicación expresa:
organización y efectos del hero de cabecera, del bloque de cierre, **del menú** y **del
logotipo**. Sus colores, iconos y estilos sí se han revisado.»*

▶ **Es exactamente la trampa de `#195`** (`tema-por-instalacion.md` §11.1, donde el hero estaba
excluido igual). Lo que se ve en el mockup del menú **no ha pasado el filtro del propio sistema
del cliente**: su color sí, su forma y su coreografía **no**. Copiarlo no es «seguir la norma»,
es seguir un borrador que la norma no ha mirado.

### 1.6 ❗❗ Lo que el mockup NO contesta

El mockup es **una** página: la landing. El producto tiene **12** vistas con armazón. Cinco
huecos, y ninguno se resuelve mirando el canvas:

| # | Hueco | Por qué importa |
|---|---|---|
| 1 | **Las 11 vistas sin hero** | La coreografía del racimo cuelga del final del hero de cabecera y del bloque de cierre. En `/precios`, `/cumpleanos`, un texto legal o un 404 **no hay ninguno de los dos** |
| 2 | **La cuenta con sesión** | En el mockup, con sesión **no hay nada en la cabecera**: saludo, avatar y aviso de formulario viven dentro del panel de reservas. Nuestro chip de cabecera lleva el **único aviso visible** de que hay un formulario pendiente |
| 3 | **El idioma** | El mockup lo pone como una cápsula del menú. En el producto vive en el **pie**, con su propio patrón y su excepción de sombra declarada |
| 4 | **El salto al contenido** | Existe en **1** de las 12 vistas (§1.8) |
| 5 | **El móvil** | El mockup colapsa piezas por ancho, pero **no diseña** la pantalla de móvil: al estrecharse pierde la ficha lateral, el botón de registro y la etiqueta del menú, y no propone nada en su lugar |

▶ Los cuatro primeros los cierra §5 con las respuestas del owner. **El quinto sigue abierto por
decisión suya** y bloquea la tanda 2c·4.

### 1.7 ❗ Tres defectos que se importan SOLOS si se copia el mockup tal cual

**1 · El menú cerrado deja sus enlaces en el orden de tabulación.**
Se oculta con un recorte circular de radio cero y `pointer-events:none`. Medido en el fichero:
**cero** `inert`, **cero** `aria-hidden` y **cero** `visibility` sobre el contenedor del menú.
Un recorte no saca nada del foco: quien navega con teclado tabularía por **una decena de
enlaces invisibles**.
▶ Nuestro cajón actual **ya resolvió esto** y dejó escrito el porqué —se oculta con
`visibility`, y **no** con `aria-hidden`, que dejaba foco fantasma—. **La forma se adopta; este
mecanismo no.** Es exactamente la regla de `tema-por-instalacion.md` §1: de lo que no está
migrado se saca la forma, nunca el resto.

**2 · `M-05` de la auditoría cae justo aquí, y choca con un token que acabamos de crear.**
Dice, textualmente, que el **CTA fijo** y el **botón de registro de la esquina** llevan sombras
difusas fuera de un modal, que el sistema **solo** admite la difusa en modales, y recomienda
pasarlas a *keyline*. Está en **Pendiente** porque «son mobiliario de cabecera y quedan
excluidos de esta pasada» — o sea: **entra con esta tanda, no con aquélla**.
⚠️ Y toca `--shadow-float`, el rol que `#196` creó hace unas horas precisamente para «paneles,
avisos y la barra de móvil». Si el mobiliario de cabecera pasa a keyline, `--shadow-float`
pierde dos de sus consumidores y hay que decidir si el rol sigue teniendo sentido o si el
paquete del cliente lo pone a `none` (que es justo lo que el ejemplo de §13.6 de aquella spec
ya proponía).

**3 · `T-02`: el rotulador aparece dos veces.** El eslogan a mano está en el hero y en el pie
del menú; la norma del cliente es **una vez por página**. Su propio informe dice «sobra el del
menú, pero el menú queda excluido. Decisión tuya».

### 1.8 El salto al contenido existe en 1 de 12 vistas

`skip-link` aparece **solo** en la home. Las otras once repiten el mismo bloque de navegación y
no ofrecen forma de saltarlo. Es un incumplimiento de accesibilidad que **ya existe** y que esta
tanda es el sitio natural de cerrar, porque convierte el armazón en una pieza compartida por
las doce.

### 1.9 ⚠️ La copia del canvas estaba caducada — otra vez

`Landing PJP Modos` en disco era de las 12:18 y el remoto de las 17:53 traía **210 líneas de
diff**: un bloque de ritmo decorativo por sección (una silueta o una mancha por sección, nunca
dos familias juntas) y un **sistema nuevo de animación al scroll** para seis secciones, con su
respeto explícito a `prefers-reduced-motion` y hoja de estilo propia.
▶ **Ninguna de las 210 líneas toca el menú ni el racimo**: la lectura de §1.4 es la vigente.
▶ `Iconos PJP` se re-bajó y es **idéntico** al de las 07:34.
▶ La copia local ya está refrescada. **Vuelve a diffear antes de implementar**: en una sola
jornada esta carpeta ha caducado **dos** veces.

---

## 2. Objetivo

**Que la navegación del producto sea la del sistema del cliente —mobiliario flotante y menú a
pantalla completa— sin llevarse su marca, sin perder ninguna capacidad que hoy existe y sin
que ninguna de las 12 vistas se quede sin forma de navegar en ningún paso intermedio.**

Criterios, todos medibles:

1. **Las 12 vistas comparten UN armazón.** Cero vistas con barra y cero vistas sin salto al
   contenido: de 1 de 12 a 12 de 12.
2. **Cero destinos perdidos.** Los ~10 que hoy ofrecen los dos desplegables y los dos atajos
   siguen alcanzables, y los que salen de la BD **siguen saliendo de la BD**: dar de alta un
   servicio con su marca de «sale en el menú» lo mete en la lista sin tocar código.
3. **El armazón cerrado no aporta ningún elemento al orden de tabulación**, y abierto atrapa el
   foco. Verificable sin navegador.
4. **Cero consultas nuevas por petición**: la lista sigue leyéndose del payload memoizado del
   composer global (`PERF-02`).
5. **Las 41 reglas muertas salen**, y el trinquete que las deja fuera solo puede encoger.
6. **Cada tanda deja el sitio navegable**: en ningún commit intermedio hay una vista sin menú.

### Fuera de alcance, explícitamente

- ⛔ **Los VALORES del cliente.** Igual que en las tandas 1, 2a y 2b: mecanismos al producto,
  la marca al paquete. Ni un hex, ni una fuente, ni un asset.
- ⛔ **El MÓVIL**, hasta que exista el artboard del owner (§5). Se declara el hueco, no se
  rellena.
- ⛔ **El logotipo.** El del cliente es un lockup de seis capas y está **sin migrar** en su
  propio artboard. Aquí el armazón solo reserva **el hueco y su caja**; qué se pinta dentro es
  del paquete de tema.
- ⛔ **El selector de idioma**, que sigue en el pie. Moverlo al menú es una decisión aparte
  (§5, hueco 3) y arrastra su excepción de sombra direccional.
- ⛔ **Las secciones de la landing** (tanda 3) y **el movimiento** (tanda 2d, con sus 237
  declaraciones y 48 duraciones ya medidas).
- ⛔ **El rediseño del embudo del cajón**, que es spec propia.

---

## 3. Opciones consideradas

### A · Barra en todas, con la piel nueva — **DESCARTADA por el owner**
Conservar la barra fija y darle los cantos, las sombras y el menú a pantalla completa. Es la
más barata y la de menor riesgo: no toca la coreografía ni las 11 vistas sin hero.
▶ **Descartada** el 2026-08-27: deja el producto a medio camino y no es lo que el sistema del
cliente describe. La barra translúcida con desenfoque **no existe** en su sistema.

### B · Armazón flotante solo en la landing — **DESCARTADA por el owner**
La portada estrena el mobiliario flotante; las otras once conservan la barra.
▶ **Descartada**: deja **dos armazones vivos** —dos formas de navegar el mismo sitio y dos
mantenimientos— y multiplica por dos la superficie que hay que revisar en cada cambio
posterior. La razón por la que el mockup solo dibuja la landing es que solo tiene landing, no
que la landing sea especial.

### C · Armazón flotante en las 12 — **ELEGIDA** `[DECIDIDO owner, 2026-08-27]`
Un componente único, dos racimos, un menú. Las vistas sin hero reciben el armazón **desde el
primer píxel** en vez de esperar a un hero que no existe — que es exactamente lo que el
producto ya hace hoy con el CTA de compra.
▶ **Es la más cara y la única coherente.** Cambia el aspecto de toda la web, no de la portada.

---

## 4. Diseño elegido

### 4.1 El armazón es UN componente con dos racimos y tres estados

Un solo componente Blade sustituye al actual, y las 12 vistas lo siguen incluyendo con la misma
etiqueta: **el cambio no toca las vistas**, que es lo que hace la tanda revisable.

Dentro, tres piezas independientes:

| Pieza | Qué lleva | Dónde |
|---|---|---|
| **Racimo de marca** | el hueco del logotipo, con su caja y su enlace a la portada | fijo, arriba-inicio |
| **Racimo de acción** | comprar · cuenta o registro · abrir el menú | fijo, arriba-fin |
| **Capa de menú** | la lista, la ficha y las cápsulas | a pantalla completa |

▶ **Ninguna de las tres declara color a mano.** Todas consumen los tokens de las tandas 1, 2a y
la elevación: la superficie por ámbito, la escala de canto, el anillo de foco y los tres roles
de sombra. La capa del menú es **tinta**, así que declara superficie y hereda los siete tokens
re-escopados — es el segundo consumidor del mecanismo de la tanda 1, después del hero.

### 4.2 La lista del menú es PLANA, y sigue mandándola la BD

`[DECIDIDO owner, 2026-08-27]`: **lista plana con los destinos que ya tenemos.**

Hoy son cuatro grupos de origen distinto: los cuatro del parque (traducidos, fijos), los
servicios que la BD marca para el menú (variables), dos fijos de servicios y dos atajos. Se
funden en **una sola secuencia numerada**, en el orden que ya tienen, y **el origen de cada
tramo no cambia**: el composer global sigue entregando la lista de servicios memoizada, y dar
de alta uno nuevo con su marca lo mete en el menú sin tocar código. Es la línea del proyecto:
*data-driven el DATO, no la PÁGINA*.

⚠️ **Lo que la lista plana cuesta, y hay que saberlo antes**: la lista del mockup tiene **5**
destinos con la tipografía de display a tamaño enorme; la nuestra tendrá **~10** y puede crecer
desde el panel. El mockup **ya trae la solución** —la columna desplaza y un velo abajo avisa de
que queda más—, pero eso significa que **con 10 destinos parte de la navegación queda bajo el
pliegue**. La numeración `01…10` y el velo son lo que lo hacen legible; no son adorno.

⚠️ **Y una consecuencia de producto**: hoy los subtítulos de cada destino se ven al desplegar.
En el mockup **se ocultan por debajo de cierto ancho**. Con la lista plana, en pantallas
medianas quedan **solo los títulos**: los subtítulos dejan de ser información garantizada y
pasan a ser un refuerzo.

### 4.3 El botón de cuenta y su punto de estado

`[DECIDIDO owner, 2026-08-27]`: **con sesión iniciada, el racimo enseña el icono de cuenta con
un punto: naranja si hay notificación, verde si no hay nada.** Sin sesión, el icono de
registro. Los dos glifos existen en el set del cliente y son **pareja declarada** por él mismo:
uno etiqueta «acceder» y el otro «crear cuenta», misma cabeza y mismos hombros con el signo de
más separado abajo a la derecha.

▶ **Esto es MÁS de lo que hay hoy, no menos**: hoy el punto solo se pinta cuando hay formulario
pendiente. Con la regla nueva el punto **está siempre** y su color dice el estado.

⚠️⚠️ **Tres objeciones medidas, y las tres se resuelven con una línea cada una.** Se construye
lo decidido; esto se anota para que la decisión sea informada, no para revisarla:

1. **El color no puede ser el único portador.** Naranja y verde se distinguen solo por tono: a
   quien no discrimina ese par le queda «un punto» en los dos casos. El nombre accesible del
   botón **ya** lo dice hoy en texto, y debe seguir diciéndolo en los dos estados — eso es
   obligatorio y no es negociable. Si además hay que distinguirlos **a la vista**, hace falta
   algo más que el tono (forma, glifo o tamaño).
2. **El naranja es el color de la acción en el sistema del cliente**, y su propia auditoría
   levantó dos hallazgos de severidad **Alta** y **Media** por usarlo como relleno en cinco
   sitios y por pintar un estado con él: *«un único relleno naranja por pantalla»*. El punto de
   la cabecera sería un relleno naranja más, compitiendo con el botón que compra.
3. **Su sistema ya tiene color para este mensaje exacto.** El aviso «tienes un formulario
   pendiente» del panel de reservas **ya se corrigió** a Amarillo Aviso con el glifo de aviso
   —hallazgo `C-05`, Aplicado—. Un punto amarillo en la cabecera diría lo mismo que el aviso de
   dentro; el verde declarado es «éxito: reserva confirmada, pago correcto», no «reposo».

▶ **`[PENDIENTE: owner]`** — una sola pregunta, y se responde en un token: *¿el punto de aviso
se queda naranja, o pasa al amarillo que su propio sistema ya asigna a este mensaje?* Mientras
no la conteste, se implementa **naranja/verde** tal como lo pidió.

### 4.4 La coreografía: una regla, no dos

El racimo se comporta igual en las 12 vistas, con **una** regla y un caso particular que ya
existe:

| Situación | Conducta |
|---|---|
| Página **con** hero | nace oculto; aparece al terminar el hero; se retira ante el bloque de cierre |
| Página **sin** hero | **visible desde el primer píxel** |
| Bajando | se retira |
| Subiendo | vuelve |
| Menú o cajón abiertos | **no se retira nunca**, y el racimo queda por encima de la capa del menú |

▶ **Esa distinción ya está construida y probada**: es exactamente la que el producto usa hoy
para el CTA de compra —con hero, espera al centinela; sin hero, visible por defecto— y para la
barra de móvil. **No se inventa una coreografía: se generaliza la que hay del CTA al racimo
entero**, que es lo que abarata esta tanda.

⚠️ **El centinela del hero hace ya DOS trabajos** y `DEUDA.md` tiene ficha abierta por ello.
Esta tanda le añade un tercer consumidor: o se le da nombre propio, o la ficha crece.

### 4.5 El menú: la forma del mockup, el mecanismo de ocultación del producto

Se adopta: el recorte circular desde el centro de la hamburguesa, el fondo tinta con trama, la
lista numerada con entrada escalonada, el velo de desplazamiento, la ficha que sigue al foco,
las cápsulas secundarias y la hamburguesa que se cruza en aspa.

**No** se adopta su forma de ocultarse. El menú cerrado sale del orden de tabulación y de las
tecnologías de apoyo por el mecanismo que el producto ya tiene, y conserva:

- **Escape** cierra · el telón cierra · el botón cierra · pulsar un destino cierra.
- **Trap de foco** con Tab cíclico mientras está abierto.
- **Bloqueo del scroll del fondo** por el dueño único que ya existe, y no tocando el `body` a
  mano — hay un test de arquitectura que lo vigila.
- **Devolución del foco** al botón que lo abrió.
- El primer foco al entrar, dentro del panel.

⚠️ **Y el recorte circular es movimiento grande**: con `prefers-reduced-motion` el menú aparece
y desaparece **sin recorrido**, solo con opacidad corta. El contrato del cliente lo dice igual
—desaparecen desplazamientos y escalas, se mantienen las opacidades— y su auditoría ya levantó
un hallazgo **Alto** por ignorarlo en cuatro animaciones.

### 4.6 Lo que se retira, y lo que hay que decidir antes de retirarlo

| Se retira | Qué pasa con lo que hacía |
|---|---|
| La barra fija translúcida | la sustituyen los dos racimos |
| Los dos paneles desplegables | sus destinos pasan a la lista plana |
| El cajón lateral de móvil | **`[PENDIENTE: owner]`** — no se retira hasta que haya artboard |
| Las 11 clases muertas (41 reglas · 131 decl.) | nada: ya no las pinta nadie |
| El chip «Hola, nombre» con texto | lo sustituye el icono de cuenta con su punto (§4.3) |

⚠️ **El saludo visible desaparece**, y hoy es el **nombre accesible** del botón. Al pasar a
icono, ese nombre tiene que seguir existiendo en texto — hay un test que lo fija y una regla de
accesibilidad detrás (el texto visible no puede desaparecer del nombre sin sustituto).

### 4.7 Iconos: se usan los nombres del set, no dibujos nuevos

El set del cliente declara **48** nombres de glifo (30 del set de la landing, 17 del área de
cliente y el de registro, que entró después). El producto tiene **21** componentes de icono.

Esta tanda necesita seis: menú, cerrar, cuenta, registro, entrada y flecha. **Cinco de los seis
ya existen** en el producto con otro nombre; el sexto —el glifo de «cerrar» del set, que es un
aspa de dos rectángulos girados— hoy se dibuja **en línea dentro del marcado**, dos veces.
▶ Sacarlos al set de iconos es lo que los hace sustituibles por instalación, que es el tercero
de los tres mecanismos del tema: *valores→panel · ficheros→assets · **dibujos→el set de
iconos***. Hay guarda de un solo origen para el icono por producto; esta tanda la extiende al
armazón.

⚠️ **Y un desajuste de vocabulario que conviene no arrastrar**: el botón fantasma de hoy lleva
un **portapapeles**, porque nació para el «registro de acceso» del parque —un trámite externo,
configurable por instalación— y **no** para «crear cuenta». El botón sirve hoy a los dos casos
según haya o no URL externa configurada. Con el glifo de persona-y-más, el caso del trámite
externo queda etiquetado como si fuera un alta de cuenta. **`[PENDIENTE: owner]`**: o dos
glifos, o un solo significado.

### 4.8 Lo que NO se toca

- **El cajón de compra y su bloque de cuenta.** El armazón solo lo **abre**, como hoy.
- **El pie**, incluido el selector de idioma y su tira de marca (`#193`).
- **El hero**, que acaba de cambiar en `#195` y cuya revisión visual sigue pendiente.
- **Los textos.** El copy que ya existe se reutiliza; el que falte sale de las claves de idioma
  en los tres idiomas, no del mockup, cuyos datos el owner declaró no fidedignos.

---

## 5. Lo decidido y lo que sigue pendiente

**Decidido por el owner el 2026-08-27**, con la medida delante:

1. **Armazón flotante en las 12 vistas** (opción C de §3).
2. **Lista plana con los destinos que ya tenemos**, con la BD siguiendo al mando.
3. **Con sesión, icono de cuenta con punto**: naranja si hay notificación, verde si no.
   Sin sesión, icono de registro. Los dos glifos del set del cliente.
4. **El móvil lo guía el owner con un artboard.**

**Pendiente del owner** — cada uno bloquea lo que dice su fila:

| # | Qué falta | Qué bloquea |
|---|---|---|
| 1 | **El artboard de MÓVIL** | la tanda **2c·4** entera |
| 2 | **`M-05`**: ¿el mobiliario de cabecera pasa a keyline, o conserva la sombra difusa? | el acabado de 2c·2, y de rebote el sentido del rol `--shadow-float` de `#196` |
| 3 | **`T-02`**: ¿sobra el eslogan a rotulador del pie del menú? | un detalle de 2c·1; su propio informe dice que sobra |
| 4 | **El color del punto de aviso**: naranja, o el amarillo que su sistema ya asigna a este mensaje (§4.3) | nada — se implementa naranja mientras no diga |
| 5 | **El idioma**: ¿se queda en el pie o sube a las cápsulas del menú? | una cápsula de 2c·1 |
| 6 | **El glifo del botón de registro** cuando el parque tiene trámite externo (§4.7) | un icono de 2c·3 |

⚠️ **Y lo que NO es de este carril y sigue esperando**: la **pasada de navegador** de `#195` y
`#196` — el hero entero y 19 elementos que perdieron su sombra. Esta tanda **cambia el mismo
terreno**: si se apila encima sin haber mirado lo anterior, cuando algo se vea raro no habrá
forma de saber cuál de las tres tandas lo hizo.

---

## 6. Impacto en invariantes

| Invariante | Qué le toca |
|---|---|
| `PERF-02` | La lista del menú se sirve del **payload memoizado del composer global**. La lista plana no puede añadir ni una consulta: se compone del mismo dato que ya viaja |
| `SEC-07` | **Ninguna URL externa editable llega a un enlace sin pasar por el saneador.** Toca directo: la URL de registro del parque va en el racimo, y la cápsula de redes del menú —si entra— es otra URL editable |
| `SEC-08` | Solo si el idioma sube al menú (pendiente 5): el cambio de idioma no vuelve a un `Referer` de otro host |
| `SUITE-04` | Ninguno: aquí no hay concurrencia. **No hace falta `VERIFY_CONC`** — el armazón no está en el camino crítico de dinero ni de aforo |
| PAY · AFORO · RGPD | **Ninguno.** El armazón no orquesta dinero, no reserva plazas y no trata datos personales más allá del nombre que ya se muestra |

▶ **Cero migraciones y cero líneas de dominio**, que es la medida de éxito que arrastran las
tandas anteriores.

---

## 7. Plan de verificación empírica

La suite comprueba que **cada regla vale lo que debe**, no que el resultado guste. Las dos
cosas hacen falta y son distintas.

**Lo que se puede fijar sin navegador** (y por tanto es obligatorio):

1. **Red del armazón actual, ANTES de tocarlo** (tanda 2c·0): que las 12 vistas lo pintan, que
   los ~10 destinos salen, que el de la BD aparece y desaparece con su marca, que sin sesión
   está el registro y con sesión el chip, y que el cajón móvil trae su panel, su botón de
   cierre, su telón y sus secciones. **Es la primera vez que el cajón móvil tendrá test.**
2. **Trinquete de clases muertas**: una guarda que falle si vuelve a entrar CSS del armazón que
   no pinta nadie. Su lista **solo encoge**.
3. **Cero destinos perdidos**: comparación por conjunto entre los destinos de antes y los de
   después, no por número.
4. **El armazón cerrado no aporta focos**: se comprueba en el marcado servido.
5. **Salto al contenido en las 12**, no en 1.
6. **Cero literales de color, cero cantos en literal**: los trinquetes que ya existen —el que
   compara por valor RGB y el de la escala de canto— cubren esto solos. Si una regla nueva
   escribe un canto a mano, el gate muerde.
7. **Las sombras salen de los tres roles**, y leen el alias estable de tinta: dentro del menú,
   que es superficie tinta, una sombra escrita con el token que se re-escopa **se volvería
   clara**. Es el defecto que ya se cazó tres veces en `#194` y `#196`.
8. **Presupuesto del cajón**: el armazón comparte hoja con el cajón, así que el trinquete de
   colores crudos y el de presupuesto de bundle aplican.

**Lo que NO se puede fijar sin navegador**, y por tanto va al owner:
el recorte circular, el escalonado de la lista, el guiño del botón de registro, la retirada al
bajar y **cómo se lee una lista de diez títulos enormes con velo de desplazamiento**.

**Mutación obligatoria**: cada guarda nueva se muta con el fallo REAL que la motiva antes de
darla por buena. La lección de `#194` —una guarda escrita para cazar un defecto **nació ciega**
y pasó sin mirar nada— y la de `#193` —un arnés que decía «0 de 12 muerden» con el test
perfecto— son de este mismo carril y de esta misma semana.

⚠️ **Y una trampa concreta ya pagada** (`#195`): comprobar que un nombre de clase «está» en el
HTML **no fija nada**, porque casa también con sus derivados y con el mismo texto pintado en
otro sitio. **Acotar al elemento antes de creerse un test verde.**

---

## 8. Las tandas

Cada una deja el sitio navegable y se puede mirar en navegador por separado.

| | Tanda | Qué entra | Mueve píxeles |
|---|---|---|---|
| **2c·0** | **La red y la limpieza** — los tests que fijan la conducta actual del armazón y del cajón móvil + salida de las 41 reglas muertas + el trinquete que las deja fuera | **No.** Es la única verificable con un diff de captura, y por eso va primera |
| **2c·1** | **El menú a pantalla completa** — sustituye a los dos desplegables y al cajón móvil en escritorio; la barra se queda pero pierde sus enlaces y gana la hamburguesa en todos los anchos | Sí, y mucho |
| **2c·2** | **Los dos racimos** — la barra se disuelve; logotipo y acción flotan; entra la coreografía generalizada a las 12 vistas | Sí, en toda la web |
| **2c·3** | **La cuenta** — icono con punto de estado, los glifos al set de iconos, el nombre accesible en texto | Sí, en la esquina |
| **2c·4** | **MÓVIL** — `[PENDIENTE: owner]`, no se empieza sin artboard | — |

⚠️ **El orden importa y no es negociable**: si el racimo (2c·2) entrara antes que el menú
(2c·1), habría un commit con la barra retirada y sin nada que la sustituya. Y si la limpieza
(2c·0) entrara después, se estarían migrando 41 reglas que no pinta nadie.

⚠️ **Commitear en local ANTES de cada mutación.** Es regla pagada del carril del panel, y aquí
se muta CSS compartido con el cajón.

---

## 9. Revisión y decisión

- **Medido por el agente** el 2026-08-27 contra el CSS servido, las 12 vistas, la suite, el JS
  del armazón y el canvas re-bajado ese mismo día a las 17:53. El instrumento de inventario
  lleva guarda de la guarda y **se corrigió a media medición** (§1.2): sus cifras son las de la
  versión corregida.
- **Cuatro decisiones del owner** el 2026-08-27, con número y coste delante (§5).
- **Seis cosas siguen pendientes de él**, y la primera —el artboard de móvil— la anunció él
  mismo.
- ⬜ **Falta su ✅ a esta spec** y recortar la tanda 2c·0 con él delante, como se hizo con el
  waiver y con la capa de tema.
- **Entrada final**: `DECISIONES #N` al aprobarse.
