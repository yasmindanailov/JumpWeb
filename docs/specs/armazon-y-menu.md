# [SPEC] El ARMAZÓN — la barra se retira y el menú pasa a pantalla completa (tanda 2c)

> Estado: 🟦 **APROBADA LA DIRECCIÓN, EN EJECUCIÓN** — el owner dio el ✅ el 2026-08-27
> (*«la landing como el mockup, como sea, hay que hacerlo»*) con **siete decisiones** tomadas
> (§5) y **cuatro pendientes**, ninguna de las cuales bloquea la primera tanda ·
> Última actualización: 2026-08-27 · Decisión asociada: **`DECISIONES #200`**. Hermana de `tema-por-instalacion.md`, de la que sale como **tanda 2c**
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

### 1.1 El tamaño real: 12 vistas, 194 reglas, 733 declaraciones

| | Medido (2026-08-27) |
|---|---|
| Vistas que incluyen el componente de nav | **12** |
| Vistas públicas que NO lo incluyen | **1** (el post-formulario de invitados, que usa el layout enfocado) |
| Reglas CSS **distintas** de las 7 familias del armazón | **194** |
| Declaraciones dentro de ellas | **733** |
| …de las cuales, dentro de un `@media` | **32** |
| Reglas de `.cta-prime`, **compartida con el hero y la barra de móvil** | **27** · 65 decl. |
| Aserciones de la suite que fijan nombres de clase del armazón | **31**, en **4** ficheros |

⚠️⚠️ **Esta tabla decía «232 reglas · 819 declaraciones» y era FALSO — se corrige aquí porque la
corrección enseña más que el número.** Aquella cifra sumaba **coincidencias, no reglas**: una
regla cuyo selector cita dos familias —`.nav-cta-med` es de `nav` y de `cta-med`— se contaba dos
veces, y `.cta-prime` se contaba como si fuera del armazón cuando la comparte con el hero. Al
contar reglas DISTINTAS son **194 · 733**, y **10** de ellas tocan más de una familia.
▶ **Es el mismo sesgo que §1.2 documenta al revés**: allí el instrumento contaba de menos por no
limpiar comentarios; aquí contaba de más por sumar por familia. **Un inventario que suma por
etiqueta no cuenta sujetos: cuenta etiquetas.**

Las siete familias propias, y dónde vive cada una:

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

▶ **Dos filas de esa tabla no son del armazón, y hay que decirlo antes de contar**:
`.cta-prime` lo comparte el hero (`#195`) —por eso queda FUERA de las 194— y `.lang-dd` vive en
el pie. Tocarlas desde aquí es entrar en terreno de otra pieza. Y los recuentos por familia
**no se suman entre sí**: 10 reglas caen en dos familias a la vez.

### 1.2 El 17 % de esa CSS está MUERTA — y el primer instrumento no lo vio

**11 clases** del armazón no aparecen en Blade, ni en el JS fuente, ni en el SSR, ni en el
bundle compilado: **33 reglas enteras · 121 declaraciones**, el **17 %** de las reglas del
armazón, más **1 selector muerto** dentro de una regla viva —`.plan-select__trigger:active`
convive con seis selectores que sí pintan, así que ahí se retira el selector, no la regla—.

⚠️ **La primera redacción decía «41 reglas · 131 declaraciones» y contaba de más**, por el mismo
sesgo que corrige §1.1: sumaba por clase, y tres reglas citan dos clases muertas cada una
(`.nav__scan` con `.nav__scan-ticket`, `.nav__user` con `.nav__user--mob`). El recuento por clase
de la tabla de abajo sigue siendo útil —dice de dónde viene cada trozo— pero **su suma no es el
número de reglas**.

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
confirmar así. Las 33 de aquí **sí** se pueden — y se comprobó además contra el HTML servido
de **16 páginas públicas**: cero apariciones de las once, y las clases vivas en las dieciséis.

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

**2 · `M-05` de la auditoría cae justo aquí — ✅ RESUELTO por el owner el 2026-08-27.**
Decía que el **CTA fijo** y el **botón de registro de la esquina** llevan sombras difusas fuera
de un modal, que el sistema **solo** admite la difusa en modales, y recomendaba *keyline*.
Estaba en **Pendiente** porque «son mobiliario de cabecera y quedan excluidos de esta pasada» —
o sea: entraba con esta tanda, no con aquélla.
▶ **`[DECIDIDO owner]`: keyline.** El mobiliario de cabecera **pierde la sombra** y se sostiene
por su borde. Detalle en §4.9.
⚠️ **Y eso toca `--shadow-float`**, el rol que `#196` creó el mismo día para «paneles, avisos y
la barra de móvil»: pierde **dos** de sus ocho usos y se queda con los seis que sí flotan sobre
contenido. **El rol no se retira** —sigue teniendo consumidores y sigue siendo redefinible por
el paquete— pero su recuento cambia, y `tema-por-instalacion.md` §13.3 hay que actualizarlo al
ejecutar, no antes.

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
5. **Las 33 reglas muertas salen**, y el trinquete que las deja fuera solo puede encoger.
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

### 4.3 El botón de cuenta y su punto de aviso

`[DECIDIDO owner, 2026-08-27]`: **con sesión iniciada, el racimo enseña el icono de cuenta;
sin sesión, el icono de registro.** Los dos glifos existen en el set del cliente y son **pareja
declarada** por él mismo: uno etiqueta «acceder» y el otro «crear cuenta», misma cabeza y
mismos hombros con el signo de más separado abajo a la derecha.

`[DECIDIDO owner, 2026-08-27]` — **corrige la primera redacción de esta sección**: el punto es
**Amarillo Aviso cuando hay algo pendiente, y NO HAY PUNTO cuando no lo hay.**

▶ **Por qué esta versión es mejor que la primera** (naranja si hay aviso, verde si no), y queda
escrito porque la diferencia no es de gusto:

1. **Cabecera y panel dicen lo mismo con el mismo color.** El aviso «tienes un formulario
   pendiente» de dentro del panel **ya está en Amarillo Aviso** desde el hallazgo `C-05` del
   propio cliente. Dos avisos del mismo hecho en dos colores distintos es un sistema que se
   contradice a sí mismo a un clic de distancia.
2. **No gasta un color de estado en el reposo.** El verde declarado es *«éxito: reserva
   confirmada, pago correcto»*; usarlo para «no pasa nada» lo vacía. La **ausencia** de punto ya
   significa reposo, y no necesita tinta.
3. **Libera el naranja.** Es el color de la acción, y su norma es *«un único relleno naranja por
   pantalla»* — que en esta esquina lo lleva el botón que compra.
4. **Sale gratis en accesibilidad.** Con dos puntos que solo cambian de tono, quien no
   discrimina ese par ve «un punto» en los dos casos y el color no informa. Con punto / sin
   punto, la señal es de presencia, no de tono.

⚠️ **Lo que sigue siendo obligatorio pase lo que pase**: el **nombre accesible** del botón dice
en TEXTO si hay algo pendiente. Hoy lo dice, hay test que lo fija, y al pasar de chip con
saludo a icono no puede perderse — el texto visible que desaparece tiene que sobrevivir en el
nombre.

### 4.4 La coreografía: una regla, no dos

El racimo se comporta igual en las 12 vistas, con **una** regla y un caso particular que ya
existe:

⚠️⚠️ **LA PRIMERA FILA DE ESTA TABLA NO SE IMPLEMENTÓ ASÍ, y el motivo es una medida** (§8.4):
la regla «nace oculto bajo el hero» es la del mockup, **que tiene un hero a pantalla completa**.
El nuestro dejó de tenerlo en `#195` —es una tarjeta con `max-height: calc(100vh - 160px)`— y
aplicarla dejaría la portada sin logotipo y sin ☰ sobre una tarjeta que no llena la pantalla.
**En el árbol, el armazón está visible desde el primer píxel en las doce.** `[PENDIENTE: owner]`.

| Situación | Conducta |
|---|---|
| Página **con** hero | ~~nace oculto; aparece al terminar el hero~~ → **visible desde el primer píxel** (ver el aviso de arriba) |
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
| El cajón lateral de móvil | **`[PENDIENTE: owner]`** — no se retira hasta que haya artboard (§4.9) |
| Las 11 clases muertas (33 reglas · 121 decl. + 1 selector) | nada: ya no las pinta nadie. ✅ **HECHO en la 2c·0** |
| El chip «Hola, nombre» con texto | lo sustituye el icono de cuenta con su punto de aviso (§4.3) |
| La sombra difusa del mobiliario de cabecera | la sustituye el borde marcado (§4.8, `M-05`) |

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

### 4.8 La elevación del mobiliario: **keyline, no sombra**

`[DECIDIDO owner, 2026-08-27]`, aceptando la recomendación de su propia auditoría (`M-05`):
**el mobiliario de cabecera no lleva sombra difusa. Se sostiene por su borde.**

⚠️⚠️ **MEDIDO AL EJECUTAR, y corrige la tabla de abajo: nuestro mobiliario NO tenía sombra.**
Ninguna de las piezas —el CTA de comprar, el fantasma, el chip de cuenta, la hamburguesa—
declaraba `box-shadow`: se la llevó `#196` al retirarla de 19 elementos. La tabla siguiente
describe **el mobiliario del mockup**, no el nuestro, y se conserva porque es la razón del
hallazgo. ▶ **La decisión del owner no costaba nada: ya era el estado.** Lo que sí hacía falta
era lo contrario de lo que parecía —dar a las piezas con qué sostenerse al desaparecer el fondo
que las sujetaba—, y eso es §8.4.

| Pieza | Antes (mockup) | Con esta decisión |
|---|---|---|
| El CTA de comprar | difusa de 28 px | **borde marcado**, sin sombra |
| El botón de cuenta / registro | difusa de 26 px | **borde marcado**, sin sombra |
| El botón del menú | difusa de 28 px | **borde marcado**, sin sombra |
| El panel del menú a pantalla completa | — | **es una superficie completa, no una pieza elevada**: tampoco lleva |

▶ **Encaja con lo que `#196` ya decidió, no lo contradice**: aquella tanda quitó la sombra a
**19** elementos con el mismo argumento —*«una tarjeta quieta no está elevada, está apoyada»*—.
El mobiliario fijo de una esquina tampoco flota sobre nada: **está anclado**.

⚠️ **Consecuencia contable que hay que arrastrar al ejecutar**: `--shadow-float` pasa de **8** a
**6** usos. **No se retira** —le quedan los paneles, los avisos y la barra de móvil— y sigue
siendo redefinible por el paquete del cliente. Lo que hay que actualizar, y solo cuando el
código esté puesto, es el recuento de `tema-por-instalacion.md` §13.3.

⚠️ **Y hay que resistir la tentación de la sombra dura**: el remate de pegatina —borde de 2 px
más sombra dura de 5 px— es el más característico del mural, pero su propia norma dice **«un
solo elemento por pantalla lo lleva; en una rejilla, jamás»**, y aquí hay tres piezas en fila.
El hallazgo `F-02` de su auditoría retiró exactamente eso de una rejilla de tarjetas.

### 4.9 El móvil: lo que YA está decidido, y lo que espera al artboard

`[DECIDIDO owner, 2026-08-27]`: **en móvil manda la barra de compra de abajo.** El racimo
superior se queda con **el logotipo y la hamburguesa**, y nada más.

▶ **Tres razones, y ninguna es preferencia:**
1. **Un solo relleno de acción por pantalla**, que es la norma del propio cliente y el hallazgo
   `C-01` de su auditoría, de severidad **Alta**.
2. **El pulgar.** En móvil se compra desde abajo; la esquina superior derecha es el punto más
   lejano de la mano.
3. **Ya está construido y probado**: la barra inferior existe, sabe aparecer tras el hero, sabe
   aparecer antes en las páginas sin hero y sabe **esconderse al llegar al pie**, con el cajón
   abierto y con el banner de cookies. No se inventa nada.

⚠️ **Y con eso aparece un hueco que hay que resolver, no ignorar**: si en móvil el racimo pierde
el botón de registro —como hace el propio mockup por debajo de 620 px—, **desde un móvil no hay
forma de darse de alta sin abrir el menú**. La cápsula de «acceder» del menú pasa a ser el
único camino, y por tanto **deja de ser secundaria**: en móvil es la puerta de la cuenta.

✅ **`[DECIDIDO owner, 2026-08-27]` — y esto es la mitad de la 2c·4: la barra de abajo pasa a ser
el CTA DOBLE del mockup, no el sencillo de hoy.**

Sus palabras: *«el cta que ves en el mockup es un cta doble, al darle clic al icono de al lado se
transforma en otro cta, se despliega el otro cta, ese es el cta que tendremos en el móvil debajo,
no el actual»*.

Medido en `Landing PJP Modos`, que es de donde sale la mecánica:

| | Cómo se comporta |
|---|---|
| Estado | Uno de los dos está **expandido** (icono + título + subtítulo) y el otro **colapsado a solo icono**, 56 px |
| Anchos del expandido | **224 px** en escritorio · 182 px por debajo de 1100 · **138 px** en teléfono |
| Pulsar el **colapsado** | lo expande **y colapsa al otro**. No actúa |
| Pulsar el **expandido** | **actúa** — abre el cajón en su zona |
| Al cargar | *Reservar* expandido → **comprar sigue siendo UN solo gesto**; registrarse cuesta dos |
| Guiño | mientras nadie lo ha tocado, el colapsado hace un vaivén y un aro que se expande, ambos con parada |
| Transición | el ancho, con curva; los textos entran con opacidad y un desplazamiento corto |

⚠️ **Y esto reabre una decisión que la 2c·2 dio por cerrada**: `[DECIDIDO owner]` fue «en móvil
manda la barra de abajo; arriba solo logotipo y hamburguesa». El CTA doble **lleva dentro el
registro**, así que en móvil la puerta de la cuenta **vuelve a estar abajo** y ya no depende solo
de la cápsula «Acceder» del menú. Es mejor de lo que había, y hay que escribirlo porque §4.9 decía
lo contrario.

⚠️ **Lo que el CTA doble cuesta, y hay que decirlo antes de construirlo**: un botón que **cambia
de significado al pulsarlo** es la clase de control que se pulsa por error. El mockup lo compensa
con el guiño y con que el estado inicial sea el de comprar. En el producto habrá además que
resolverlo en accesibilidad: el nombre accesible de cada mitad tiene que decir **qué hace ahora**
—«Reservar entradas» frente a «Mostrar registro»—, o un lector de pantalla anunciará dos botones
que dicen lo mismo y hacen cosas distintas.

⬜ **Lo que SIGUE esperando al artboard** (`[PENDIENTE: owner]`): cómo se ve el **menú a pantalla
completa** en un móvil sin ficha lateral y sin subtítulos —o sea, con **diez títulos grandes y
nada más**—, dónde caen las cápsulas secundarias y qué tamaño toma el logotipo. **El CTA doble ya
no está bloqueado; el menú de móvil, sí.**

### 4.10 Lo que NO se toca

- **El cajón de compra y su bloque de cuenta.** El armazón solo lo **abre**, como hoy.
- **El pie**, incluido el selector de idioma y su tira de marca (`#193`).
- **El hero**, que acaba de cambiar en `#195` y cuya revisión visual sigue pendiente.
- **Los textos.** El copy que ya existe se reutiliza; el que falte sale de las claves de idioma
  en los tres idiomas, no del mockup, cuyos datos el owner declaró no fidedignos.

---

## 5. Lo decidido y lo que sigue pendiente

**Decidido por el owner el 2026-08-27**, en dos vueltas y con la medida delante:

| # | Decisión | Dónde vive |
|---|---|---|
| 1 | **Armazón flotante en las 12 vistas** | §3, opción C |
| 2 | **Lista de menú PLANA** con los destinos que ya tenemos, y la BD sigue al mando | §4.2 |
| 3 | **Icono de cuenta con sesión, icono de registro sin ella** | §4.3 |
| 4 | **El punto es Amarillo Aviso cuando hay algo pendiente, y no hay punto cuando no lo hay** — corrige la primera redacción, que era naranja/verde | §4.3 |
| 5 | **`M-05` aceptado: el mobiliario de cabecera pasa a keyline** y pierde la sombra difusa | §4.8 |
| 6 | **En móvil manda la barra de compra de abajo**; arriba solo logotipo y hamburguesa | §4.9 |
| 7 | **El artboard de MÓVIL lo sube el owner**, y hasta entonces la 2c·4 espera | §4.9 |

▶ **Dos de las siete corrigen a este documento**, y se dejan escritas como corrección y no como
texto nuevo: la 4 sustituye al punto naranja/verde y la 5 cierra un `[PENDIENTE]` que esta misma
spec había abierto.

**Sigue pendiente del owner** — cada uno bloquea lo que dice su fila, y ninguno bloquea la
primera tanda:

| # | Qué falta | Qué bloquea |
|---|---|---|
| 1 | **El artboard de MÓVIL** | la tanda **2c·4** entera. Nada más |
| 2 | **`T-02`**: ¿sobra el eslogan a rotulador del pie del menú? | un detalle de 2c·1. ▶ **Por defecto NO se pinta**: su norma es una vez por página, el hero ya lo gasta desde `#195`, y su propio informe dice que el del menú sobra. Ponerlo después cuesta una línea; quitarlo, una revisión |
| 3 | **El idioma**: ¿se queda solo en el pie, o entra también en las cápsulas del menú? | una cápsula de 2c·1 |
| ~~4~~ | ~~**El glifo del botón de registro** cuando el parque tiene trámite externo~~ ✅ **CERRADO por construcción en la 2c·3** (§8.5): el icono sigue al DESTINO — portapapeles para el trámite externo del parque, `user-plus` para crear cuenta. Si el owner prefiere otra cosa, es un componente | — |

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
| **2c·0** ✅ | **La red y la limpieza** — HECHA el 2026-08-27: `ArmazonContractTest` (10 casos) fija la conducta del armazón y **estrena red para el cajón móvil**, `ArmazonCssHasNoOrphansTest` deja fuera las 33 reglas muertas y las mantiene fuera. **15 mutaciones, las 15 muerden**, con control positivo | **No**, y está medido: cero apariciones de las once clases en el HTML servido de 16 páginas |
| **2c·1** ✅ | **El menú a pantalla completa** — HECHA el 2026-08-27: sustituye a los dos desplegables, la barra pierde sus enlaces y la hamburguesa aparece en todos los anchos. El cajón de móvil **sigue intacto** por debajo de 1080 px, esperando el artboard | **Sí, y mucho.** Es la primera pantalla de las 12 vistas |
| **2c·2** ✅ | **Los dos racimos** — HECHA el 2026-08-27: la barra se disuelve (sin fondo, sin desenfoque, sin línea), los dos racimos flotan en las esquinas, entra la coreografía en las 12 vistas y **el salto al contenido pasa de 1 de 12 a 12 de 12** | **Sí, en toda la web** |
| **2c·3** ✅ | **La cuenta** — HECHA el 2026-08-27: icono redondo en todos los anchos, **punto en Amarillo Aviso y nada cuando no hay aviso**, tres glifos al set y el nombre accesible **en texto** | Sí, en la esquina |
| **2c·4** | **MÓVIL** — `[PENDIENTE: owner]`, no se empieza sin artboard. Ya llega con dos cosas decididas: **manda la barra de abajo** y **arriba solo logotipo y hamburguesa** (§4.9) | — |

⚠️ **El orden importa y no es negociable**: si el racimo (2c·2) entrara antes que el menú
(2c·1), habría un commit con la barra retirada y sin nada que la sustituya. Y si la limpieza
(2c·0) entrara después, se estarían migrando 33 reglas que no pinta nadie.

⚠️ **Commitear en local ANTES de cada mutación.** Es regla pagada del carril del panel, y aquí
se muta CSS compartido con el cajón.

### 8.1 ❗ Lo que costó la 2c·0, y es lo más útil de este registro

La tanda salió como se planeó —cero píxeles, red nueva, 33 reglas fuera— pero **cuatro de los
cinco instrumentos que se usaron nacieron rotos, y las cuatro veces parecía lo contrario**. Se
escriben aquí porque el próximo que toque esto va a escribir instrumentos parecidos.

| # | El instrumento decía | Lo que pasaba de verdad |
|---|---|---|
| 1 | **9** clases muertas | Eran **11**: dos «vivían» dentro de un **comentario de Blade** que explicaba que ya no se usan (§1.2) |
| 2 | **1.032 reglas** en una hoja de 194 | El localizador **no avanzaba por delante de los comentarios**, así que cada bloque `/* … */` contaba como un selector |
| 3 | **232 reglas · 819 declaraciones** de armazón | Sumaba **coincidencias, no reglas**: una regla que cita dos familias contaba dos veces (§1.1). Son **194 · 733** |
| 4 | **15 de 15 mutaciones muerden** | Cierto, pero **no lo había demostrado**: el arnés restauraba el fuente con `copy2`, que conserva el **mtime original** — más viejo que la vista que Blade compiló durante la mutación—, así que **el fuente volvía a estar sano y la aplicación seguía sirviendo la versión mutada**. Una mutación podía apuntarse el tanto que había ganado el residuo de la anterior |

▶ **El patrón es siempre el mismo, y ya tiene nombre en este repo**: cuando dos medidas del mismo
corpus no coinciden, la que sobra **no es la que da más: es la que no puede explicar la
diferencia**. Aquí las cuatro se cerraron explicando la diferencia con un número.

▶ **Y la regla que este trabajo añade**, porque es la simétrica de una que ya estaba escrita:
«un `grep` que no encuentra no demuestra que no exista» → **un `grep` que SÍ encuentra tampoco
demuestra que exista.** Un comentario que dice «esto ya no se usa» mantiene viva, para un
inventario ingenuo, exactamente la deuda que está enterrando.

▶ **Lo cuarto tiene consecuencia operativa para cualquier arnés de este repo**: hay que hacer
`view:clear` **antes de cada medición**, no al final. Sin eso, un arnés de mutación de Blade
mide el residuo de la mutación anterior.

⚠️ **Y una cifra que asustó y era correcta**: la suite sube **15 tests y solo 1 aserción**. Los
guardas de CSS aseveran **por regla**, así que retirar 33 reglas les quita **86** aserciones
(4.025 → 3.939, medido volviendo a poner el CSS y corriendo otra vez) y los tests nuevos aportan
**87**. `17 694 − 86 + 87 = 17 695`. **Un contador que se mueve menos de lo esperado no es un
contador roto hasta que no puedes explicar la diferencia.**

### 8.5 Lo que la 2c·3 dejó hecho

**El saludo visible se retira.** Sin barra detrás, el racimo son piezas del mismo tamaño y un
botón que cambia de ancho con el nombre de cada visitante —«Hola, Marta» frente a «Hola,
Wilhelmina»— desalinea la esquina entera. El icono pasa a redondo en **todos** los anchos (su
versión circular vivía en un `@media` de móvil y se promueve a regla base).

❗ **Lo único que no podía perderse es que el nombre accesible siguiera EN TEXTO.** El saludo
visible **era** el nombre accesible del botón; al quedarse en icono, ese nombre solo existe en el
`aria-label`. Hay guarda propia y su mutación muerde, y una segunda que impide que el texto
visible vuelva sin ser prefijo del nombre —«label in name», WCAG 2.5.3—.

**El punto pasa a Amarillo Aviso**, que es el mismo token con el que el panel pinta «tienes un
formulario pendiente»: cabecera y panel dicen lo mismo con el mismo color.
⚠️ **Y aquí sí es el significado del token, no decoración.** El CSS del producto tiene escrita
una prohibición justo al lado —no usar `--ok`/`--err`/`--attn` para pintar la tira de marca,
porque «un color semántico usado como decoración deja de significar lo que significa», con el
hallazgo `C-04` del propio cliente detrás—. Este uso es el contrario: un aviso pintado con el
color de aviso.
▶ **Y «sin punto cuando no hay aviso» ya era el estado**: el punto siempre se renderizó bajo
condición. Lo que cambia es el color, y que ahora hay guarda de las dos cosas.

**Tres glifos al set de iconos** —«dibujos → el set» es uno de los tres mecanismos del tema, y un
`<svg>` suelto en el marcado **no lo puede sustituir un cliente**—:
· `menu` y `close`, **traslado exacto** desde el marcado del armazón: mismas coordenadas, mismo
  grosor, mismo remate. Cero píxeles.
· `user-plus`, nuevo, y **es la pareja declarada de `user`** por el propio sistema del cliente:
  «misma cabeza y mismos hombros con el más separado abajo a la derecha».

⚠️ **Y con él se cierra el pendiente 4 de §5, por construcción**: el botón de la esquina sirve a
DOS destinos según la instalación. Si el parque tiene su propio **trámite de registro de acceso**
(una URL externa), eso es un formulario y conserva el portapapeles; si no lo tiene, el botón
**crea una cuenta** y lleva `user-plus`. **El icono sigue al destino, no a la posición del
botón.** Guarda propia que monta las dos configuraciones.

⚠️ **Los dos galones del selector de idioma se quedan dibujados en línea, a propósito.** El set ya
tiene un galón, pero con otro trazo y otra caja: unificarlos **cambiaría el aspecto del PIE**, que
no es de esta tanda. Están declarados como excepción en la guarda, y **la lista solo encoge**.

▶ Un test de `CustomerAccountContextTest` perdió su sujeto (`nav__acct-greet`) y **no se retiró**:
se sustituyó por la capacidad que sí importa —que el nombre accesible siga en texto—, y se dejó
escrito dónde vive ahora la versión acotada al elemento.

### 8.4 Lo que la 2c·2 dejó hecho, y las dos veces que la spec se corrigió a sí misma

**La barra se disuelve.** `.nav` deja de tener fondo, desenfoque y línea inferior y pasa a ser el
**contenedor** de dos racimos pinneados a las esquinas. Sigue llamándose `nav` porque es la
navegación del sitio: **lo que desaparece es la barra, no la navegación**.

⚠️ **Y lo que no se ve es lo que más duele**: el contenedor renuncia a los clics
(`pointer-events: none`) y los racimos los recuperan (`auto`). Sin eso, la franja vacía entre los
dos **se traga los clics de todo el ancho de la pantalla** en sus primeros píxeles. No falla, no
avisa, y solo lo nota quien intenta pulsar algo que está justo debajo. Hay guarda y su mutación
muerde.

**El salto al contenido pasa de 1 de 12 a 12 de 12.** Vive en el componente, así que no se puede
olvidar en la próxima página pública que se cree, y las once vistas que no lo tenían ganan su
ancla.

**La coreografía**: el armazón se retira al bajar y vuelve al subir, nunca con un overlay abierto,
y nunca en la primera pantalla.

#### ❗ Dos veces que esta spec se corrigió a sí misma al ejecutarla

**1 · `M-05` ya estaba cumplido, y la spec decía lo contrario.** §4.8 daba por hecho que el
mobiliario llevaba sombra difusa y había que quitársela. Medido: **nuestras piezas no tienen
`box-shadow` ninguna** — se la llevó `#196` al retirarla de 19 elementos. Así que la decisión del
owner no costaba nada: ya era el estado. Lo que sí hacía falta era lo contrario de lo que parecía:
**dar a las piezas con qué sostenerse** al desaparecer el fondo que las sujetaba.
▶ De ahí las dos únicas cosas que cambian de aspecto por sí mismas:
· **el CTA fantasma deja de ser transparente** y pasa a superficie de tarjeta. Con la barra
  disuelta, transparente dejaba de ser un estilo y pasaba a ser un accidente: el botón quedaba
  ilegible en cuanto el scroll traía una tarjeta por debajo. **La jerarquía se conserva por PESO
  DE RELLENO** —tinta el principal, tarjeta el secundario—, que es como la declara el sistema del
  cliente, y no por ausencia de fondo.
· **la marca gana su propia caja.** El mockup resuelve esto con un `drop-shadow` sobre su
  logotipo de seis capas; la nuestra es TEXTO y esa vía está cerrada por el propio `M-05`.

**2 · La coreografía de §4.4 NO se implementa como estaba escrita, y el motivo es una medida.**
Decía «en una página con hero nace oculto y aparece al terminar el hero». Esa es la regla del
mockup, **y el mockup tiene un hero a pantalla completa**. El nuestro dejó de tenerlo en `#195`:
es una TARJETA con `max-height: calc(100vh - 160px)` dentro de un `padding` de 96 px. Aplicarla
tal cual dejaría la portada **sin logotipo y sin ☰** sobre una tarjeta que no llena la pantalla —
o sea, el sitio sin ninguna navegación visible en su primera pantalla.
▶ Se implementa lo que sí se sostiene: **visible desde el primer píxel en las doce**, más la
retirada al bajar. **`[PENDIENTE: owner]`**: si al mirarlo prefiere la del mockup, es una línea —
pero entonces hay que decidir qué ve alguien que entra a la portada.

⚠️ **Y una tercera regla que sí se conserva íntegra**: con un overlay abierto el armazón **no se
retira nunca**. La hamburguesa es la forma de cerrar el menú; si se fuera con el scroll del propio
menú, el visitante se quedaría dentro sin salida visible. Escape seguiría funcionando, pero eso no
es una salida que nadie vea.

#### Lo que costó

❗ **Un `<main>` de más, y el test lo cazó.** `pages/events` tiene **dos** `<main>` en ramas
excluyentes; el parche automático puso el ancla en el primero y **la página sirve el segundo**. El
salto al contenido apuntaba a un ancla inexistente en la página que se ve — no falla, no avisa, y
solo lo nota quien navega con teclado. ▶ Hay guarda propia que ahora mira **todos** los `<main>`,
no el primero: **un `grep` que mira «el primer X» da un inventario que parece completo.**

⚠️ **El arnés de mutación volvió a mentir, y de la forma más tonta.** Dos ediciones automáticas
sobre él **fallaron en silencio** y corrió la lista de la tanda anterior: 16 de 16 en verde,
válidas, pero **no eran las mutaciones de esta tanda**. Se reescribió entero y **verifica ahora
que cada ancla casa exactamente una vez antes de empezar** — una edición fallida convierte un
arnés en un teatro que siempre da 100 %.

⚠️ **La lógica del scroll salió de `app.js`.** El fichero que registra los componentes de Alpine
**no lo cubre ningún test**, y una regla con tres casos de borde metida ahí es una regla que nadie
puede ejercitar. Vive en `resources/js/ui/nav-choreography.js` con **8 casos propios**, y devuelve
`null` —«no me consta»— cuando no hay intención: si la referencia avanzara con cada píxel, un
arrastre lento nunca acumularía delta suficiente y la coreografía **no se dispararía jamás**.

### 8.3 Lo que la 2c·1 dejó hecho, y lo que costó

**El menú.** Un overlay a pantalla completa que declara `data-surface="ink"` —**segundo
consumidor del mecanismo de la tanda 1**, después del hero— con la lista PLANA numerada, la
entrada escalonada, el recorte circular desde la hamburguesa y la fila de cápsulas. **25 reglas
· 112 declaraciones**, y ni un color, canto o sombra escrito a mano.

**La barra pierde sus enlaces** y la hamburguesa pasa a estar en todos los anchos: es la única
puerta a la navegación. Con el menú abierto la barra **sube por encima** y declara tinta, porque
la hamburguesa es la forma de cerrarlo y no puede quedar debajo.

**Los dos anchos, mientras tanto.** Por encima de 1080 px manda el menú; por debajo, el cajón de
siempre —intacto hasta que el owner suba el artboard—. Se conmutan con `display:none`, **no con
`visibility`**, y la diferencia importa: `display:none` saca del foco, `visibility` no siempre; el
panel que no toca no puede aportar ni un tabulador.

| | Medido |
|---|---|
| Armazón antes de la 2c·1 (tras la 2c·0) | **161 reglas · 612 declaraciones** |
| Armazón ahora | **154 reglas · 586 declaraciones** |
| Reglas que salen | **34** — las 33 de `plan-select*`, `nav__dd*` y `nav__links*`, que murieron con los desplegables, más la de la hamburguesa que ya no necesita `@media` |
| Reglas que entran | **27** — 25 del menú, la barra por encima y el corte del cajón |
| Cuadre | `161 − 34 + 27 = 154` ✓ |
| Destinos en el menú | **10**, con «Cumpleaños» deduplicado y Kids/Jump conservados pese a compartir ancla |

▶ **Sumando las dos tandas**: el armazón pasa de **194 a 154 reglas**. 33 se retiraron **por
muertas** (2c·0) y 33 más **con la feature que las usaba** (2c·1) — son dos cosas distintas y no
se suman como si fueran lo mismo.

**Lo que costó:**

⚠️ **Dos tests de `HomePageTest` se cayeron, y NO se retiraron: se mudaron.** Su sujeto —los dos
desplegables— murió, pero lo que comprobaban de verdad seguía vivo y **nadie más lo fijaba**: las
etiquetas de los destinos, sus anclas y **su ORDEN**. Están ahora en `ArmazonContractTest`,
apuntando al menú y **acotados al elemento** (los originales aseveraban sobre la página entera,
donde «Zona Kids» lo pinta también la sección de zonas). Es `CONVENCIONES §3.quater` aplicado:
clasificar por sujeto, no borrar lo que estorba.

⚠️ **`ShapeScaleTest` perdió una excepción sin sujeto** (`.plan-select__panel a`) y lo cazó su
propia guarda, que es para lo que está. La lista solo encoge.

⚠️ **La sonda de at-rules del trinquete se quedó sin sujeto**: era `.nav__links`, la única clase
del armazón que solo existía dentro de un `@media`, y esta tanda la retiró. Se re-apuntó a
`.book-bar-visible`, que hoy es la única. ▶ **Una sonda por NOMBRE avisa cuando se queda sin
sujeto; una por umbral se habría quedado verde sin comprobar nada.**

⚠️ **Y una reconciliación que no cuadraba a la primera**: al medir el antes y el después salieron
`161 − 33 + 25 = 153` frente a **154** medidas. La causa era **mía y ya conocida**: había cambiado
la lista de familias entre las dos medidas. Con una sola definición y un diff **de selectores**,
las tres reglas que faltaban aparecen con nombre —la barra por encima, el corte del cajón y la
hamburguesa que sale del `@media`— y `161 − 34 + 27 = 154`. **Un cuadre que falla por uno es un
cuadre que falla.**

### 8.2 Lo que la 2c·0 dejó hecho

- **`ArmazonContractTest`** (10 casos): fija **capacidades, no píxeles** — las 12 vistas que
  sirven el armazón, que ningún destino se ofrezca solo en la barra o solo en el cajón, que un
  servicio del CMS llegue a los dos y desaparezca de los dos, que el cajón sea un overlay
  accesible, que **todo enlace del cajón lo cierre** y que el racimo cambie con la sesión.
  ▶ **El cajón móvil estrena red**: no lo tocaba ningún test, ni PHP ni JS.
- **`ArmazonCssHasNoOrphansTest`** (5 casos): el trinquete. Su lista de excepciones nace **vacía**
  y solo puede encoger.
- **33 reglas y 121 declaraciones fuera**, más 1 selector muerto de una regla viva, más los
  comentarios y el `@keyframes` que se quedaron sin sujeto y los tres `@media` que quedaron
  vacíos.
- **Cero píxeles, medido**: ninguna de las once clases aparece en el HTML servido de **16
  páginas** públicas, y las clases vivas aparecen en las dieciséis.

---

## 9. Revisión y decisión

- **Medido por el agente** el 2026-08-27 contra el CSS servido, las 12 vistas, la suite, el JS
  del armazón y el canvas re-bajado ese mismo día a las 17:53. El instrumento de inventario
  lleva guarda de la guarda y **se corrigió a media medición** (§1.2): sus cifras son las de la
  versión corregida.
- **Siete decisiones del owner** el 2026-08-27, en dos vueltas y con número y coste delante
  (§5). **Dos de ellas corrigen a este documento**, y ése es el resultado que se buscaba al
  escribir las objeciones en vez de callarlas: el punto de aviso pasa de naranja/verde a
  amarillo-o-nada, y `M-05` se cierra en keyline.
- ✅ **El owner aprueba la dirección**: *«la landing como el mockup, como sea, hay que
  hacerlo»*. Se empieza por la tanda **2c·0**, que no cambia un píxel.
- **Cuatro cosas siguen pendientes de él** y ninguna bloquea la primera tanda; la primera —el
  artboard de móvil— la anunció él mismo.
- **Entrada final**: **`DECISIONES #200`** — la spec, las siete decisiones y la ejecución de la
  tanda 2c·0.
