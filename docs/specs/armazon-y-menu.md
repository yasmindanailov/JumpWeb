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

> ✅ **ACTUALIZACIÓN 2026-08-28 — la decisión 7 está CUMPLIDA: el artboard de móvil YA EXISTE.**
> No llegó como artboard suelto sino **dentro de `Landing PJP Modos`**, en una pasada de móvil
> completa (428 líneas de diff contra la copia del 27 a las 17:53). Lo que trae:
> `--pjp-margen` / `--pjp-radio` / `--pjp-tope` / `--pjp-barra` como escala responsiva · `100svh`
> en lugar de `100vh` · `env(safe-area-inset-*)` arriba y abajo · los grids de tarjetas convertidos
> en **carruseles con `scroll-snap`** · el panel de reserva convertido en **hoja inferior con asa**
> por debajo de tamaño teléfono · y una **barra de acciones fija** con **3 enlaces de icono**
> (Zonas · Cumples · Llegar) **+ un CTA «Reservar» naranja**, con el CTA de escritorio oculto
> mientras esa barra existe.
> ▶ **La 2c·4b deja de estar bloqueada.**
> ⚠️⚠️ **Pero hay un choque que hay que resolver con el owner ANTES de escribir código**: nuestra
> barra inferior de móvil es el **CTA DOBLE** de `#205` (dos mitades con destinos distintos, una de
> ellas colapsada) y la del mockup es **3 iconos + 1 CTA**. No son la misma pieza y la nuestra la
> validó él hace un día. **Preguntar, no elegir.**
> ⚠️ Y el mockup **vuelve a poner CTA dentro del hero**: `[DECIDIDO owner, 2026-08-28]` **no se
> copia** — la decisión de `#195` sigue en pie.

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
| ~~2~~ | ~~**`T-02`**: ¿sobra el eslogan a rotulador del pie del menú?~~ ✅ **CERRADO** (`[DECIDIDO owner]`: «1:1 al mockup»). Se pinta, y no incumple la norma: el menú tapa el hero, nunca son co-visibles (§8.6) | — |
| ~~3~~ | ~~**El idioma**: ¿pie o menú?~~ ✅ **CERRADO** (`[DECIDIDO owner]`): solo en el menú, con `<noscript>` en el pie como suelo sin JS (§8.6) | — |
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
| **2c·4a** ✅ | **El CTA DOBLE de móvil** — HECHA el 2026-08-27, y con ella el idioma sale del pie y el menú gana su eslogan | Sí, en la barra de abajo |
| **2c·4b** ⬜ | **El MENÚ en móvil** — `[PENDIENTE: owner]`, sigue esperando el artboard | — |

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

### 8.6 Lo que la 2c·4a dejó hecho

**El CTA doble.** La barra de abajo deja de ser un botón: dos mitades, una expandida con su
subtítulo y otra colapsada a icono. Pulsar la colapsada la expande y colapsa a la otra; pulsar la
expandida **actúa**. Arranca con comprar expandido — **comprar cuesta un gesto y registrarse dos**,
y eso es la jerarquía, no un descuido.

⚠️⚠️ **Un botón que cambia de significado al pulsarlo es un botón que se pulsa por error**, y por
eso su nombre accesible **dice qué hace AHORA**: colapsada se llama «cambiar a…», no «registrarse».
Sin eso, un lector de pantalla anunciaría dos botones que dicen lo mismo y hacen cosas distintas, y
el segundo no llevaría a donde dice.

⚠️ **Sin JavaScript el doble paso no existe, a propósito.** Las dos mitades son `<a href>` de
verdad, así que sin JS cada una navega de **una sola pulsación** — y por eso el nombre accesible
**servido** es el de ACTUAR, que es lo que hacen sin JS; Alpine lo sustituye por el de «cambiar»
solo en la que quede colapsada. Al revés, quien navega sin JS leería «Cambiar a registrarse» en un
enlace que se registra. Hay guarda de las dos direcciones.

⚠️ **La mitad colapsada encoge por su CONTENIDO, no por una anchura animada**: transicionar
`flex-basis` o `width` entre `auto` y un número no es fiable; el texto sí se pliega, y el botón
sigue al plegado sin que nadie anime una caja. El texto **se queda en el árbol** —no `display:none`—
y el nombre lo fija el `aria-label`.

▶ **Y el reparto lo decide el CSS a partir de UNA clase.** El JS publica el modo y nada más: misma
regla que el hero (`#195`) y que el recorte del menú (`#201`). Por defecto —o sea, también sin
JS— la ancha es la de comprar.

**El selector de idioma sale del pie** (`[DECIDIDO owner]`): desde la 2c·1 vive en las cápsulas del
menú, y dos selectores del mismo idioma son dos sitios que mantener y uno que se queda atrás.
❗ **Y con él se iba el ÚNICO cambio de idioma que funcionaba sin JavaScript**, porque el menú lo
abre Alpine. El resto de la navegación sobrevive —las columnas del pie son anclas de verdad— pero
el idioma se quedaba sin ninguna. De ahí el `<noscript>` del pie: no lo ve nadie con JS y sin JS es
la única puerta. Es el mismo recurso que ya usaban el reintento de pago y el marco de
consentimiento.
▶ **Y al irse `.lang-dd--up` con él, cayó una de las SEIS excepciones de sombra direccional** que
declaró la elevación (`#196`). Quedan cinco. **La lista encogió sola**, que es justo lo que aquella
tanda prometió que pasaría.

**El menú gana el eslogan a rotulador** (`[DECIDIDO owner]`: «la idea es 1:1 al mockup»), y sale de
la **misma clave** que el del hero: dos claves para el mismo copy son dos copys que se separan
solos.
⚠️ **`T-02` de su auditoría lo marcaba como repetido** —«máx. una vez por página»— y no lo
incumple: **el menú es `inset: 0` y tapa el hero entero**, así que los dos nunca están en pantalla a
la vez. La norma habla de por pantalla.

#### Lo que costó

⚠️ **Un apóstrofo tumbó la suite entera.** La clave francesa `Passer à l'inscription` se generó
dentro de comillas simples de PHP y el fichero dejó de parsear — 7 fallos y un error, ninguno
relacionado con lo que se estaba construyendo. `php -l` en los tres ficheros de idioma es el
control que faltaba, y ahora está en el guion.

⚠️ **Dos guardas ajenas se dispararon, y las dos tenían razón**:
· `AccountDoorWiringTest` **cuenta** los CTA de alta —dos, escritorio y cajón— precisamente para que
  quitarle el cableado a uno no pase en verde. Apareció un **tercero** y el número sube a 3, con su
  porqué escrito. Aflojar la aserción a un «contiene» habría sido tirar la guarda.
· La aserción del glifo de alta contaba **la página** y ahora el glifo sale **dos veces** —cabecera
  y barra—. Se acotó **a cada portador**: un recuento global daba verde con uno solo bien puesto.

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

---

## 6. La 2c·5 — el menú se puede CERRAR, y siempre ofrece comprar (`#211`, 2026-08-28)

> Las dos cosas las cazó **el owner mirando la pantalla**. Ninguna de las 31 aserciones del armazón
> las veía, y no por descuido: **todas comprobaban que el menú se ABRE**.

### 6.1 ❗❗ El menú no tenía salida con el ratón

Medido: `.nav__burger` hacía `@click="menuOpen = true"`. A secas. El menú es `inset: 0` y tapa la
página entera, así que las únicas salidas eran **`Escape`** y **pulsar un destino**. Quien usa el
ratón y no quiere ir a ninguno de los diez sitios, se quedaba dentro.

▶ **En el mockup del cliente el mismo botón ALTERNA** (`onClick={{ alternaMenu }}`) y sus dos rayas
rotan ±45° hasta formar un aspa, con un rótulo que pasa de «Menú» a «Cerrar».

Aquí se resuelve igual pero con **el SET de iconos** en vez de rotando rayas: `x-icons.close` ya
existe, y el dibujo es uno de los tres mecanismos del tema —una instalación tiene que poder
sustituirlo—. Los dos glifos se sirven siempre y los alterna el CSS por `.nav--over`, así que:
no hace falta JS para el dibujo, y el botón **no cambia de tamaño** al alternar (se apilan en la
misma celda de una rejilla; con `display:none` el círculo daba un salto de 2 px).

⚠️ **Lo único del mockup que NO se copia**: su `aria-label` dice «Abrir menú» **también estando
abierto**. Aquí alterna, y además el botón gana `aria-expanded` — que es lo único que tiene quien
no ve el dibujo. El `aria-label` estático se queda como suelo sin JavaScript.

### 6.2 ❗❗ Y con el menú abierto no había dónde comprar

El owner pidió «el CTA del menú». Medido antes de añadir nada: **el menú del mockup tampoco lleva
CTA propio** — usa el de la cabecera. La diferencia es que **el suyo está siempre visible y el
nuestro no**: en la portada `.nav-cta-med` nace oculto y lo destapa `navCtaReveal` al pasar el hero
(`#194`). Abriendo el menú desde arriba del todo, la pantalla entera se quedaba **sin un solo sitio
donde comprar**.

▶ El arreglo es una regla, sin estado nuevo ni JS: `body[data-has-hero] .nav--over .nav-cta-med`
revela el mismo botón, con el mismo destino y el mismo rol de acción. Con el paquete del cliente
puesto sale en **naranja** sobre la tinta del menú, que es exactamente lo que su sistema pide.

⚠️ **La guarda de esto nació DÉBIL y lo demostró la mutación.** Comprobaba
`assertStringContainsString('… .nav-cta-med')`, y un selector mal escrito —`.nav-cta-med-NO`—
**contiene** esa cadena: el test pasaba con el CSS roto. Hoy asevera el selector completo tras
partir la hoja en reglas, y además que declare `opacity: 1` y `pointer-events: auto`.
▶ Es la tercera vez en esta sesión que una subcadena hace pasar una aserción falsa (las otras dos:
`favicon.svg` dentro de `client-favicon.svg`, y `cta-prime` dentro de `cta-prime__ico` en `#195`).

### 6.3 Verificación

- **`ArmazonContractTest` +3 casos**, **`ClientThemePackageTest` +3** (el hueco del icono).
- **10 mutaciones, las 10 muerden** (detección por código de salida, nunca por `grep`).
- **Sonda de navegador 15/15** (`VERIFICACION-E2E-CAJON.md` §5.quaterdecies): en la portada el CTA
  nace oculto · la hamburguesa dice `aria-expanded=false` y enseña rayas · al abrir dice `true`,
  enseña la X, su nombre pasa a «Cerrar menú» y **aparece el CTA en `rgb(242,113,28)`** · al
  volver a pulsarla el menú se cierra · `Escape` sigue cerrando.

---

## 7. La 2c·6 — el CTA del armazón, alineado al mockup (`#213`, 2026-08-28)

> `[DECIDIDO owner, 2026-08-28]`: **como el mockup**, y las cinco diferencias de forma.

### 7.1 ⚠️⚠️ Primero, una medida propia que salió MAL y hay que decirlo

En `#211` este documento afirmó que «el menú del mockup no lleva CTA propio», y el instrumento que
lo dijo **no imprimió ni un solo botón de esa región**: un barrido que no encuentra nada no
demuestra que no haya nada. Repetido con control positivo —el extractor tiene que ver el bucle
`enlacesMenu` del propio menú—, la conclusión **se confirma**: el contenedor del menú tiene **0
botones** y ningún «Reservar». Pero la conclusión era correcta por casualidad, no por la medida.

### 7.2 ❗❗ El CTA fijo del mockup NO es el color de acción

Medido en `estiloCtaFijo`:

```
background: menuAbierto ? '#F5C400' : '#101418'      ← AVISO abierto, TINTA cerrado
color:      menuAbierto ? '#101418' : '#F4F4F1'
```

El naranja de acción lo usa en el hero, en las zonas y en la barra de móvil. **Su CTA fijo, no.**
Y ahí hay **tres fuentes del cliente que se contradicen**:

| Fuente | Qué dice del botón «Reservar» de la cabecera |
|---|---|
| `Landing PJP Modos` (implementación) | tinta cerrado → **Amarillo Aviso** abierto |
| `Colores de Marca` §02 (rol del amarillo) | «Resalte tipo marcador y anillo de foco. **Nunca fondo de sección ni botón**» |
| `Colores de Marca` §14 (orden de página) | cabecera fija → dominante = **`cta.fill`** (Naranja Salto) |

`[DECIDIDO owner]`: **gana la implementación**. Las otras dos quedan anotadas en `DEUDA.md`.

▶ **Consecuencia de arquitectura, y no es menor**: `.cta-med` **sale del rol de ACCIÓN** que `#209`
le había dado. Su color no lo manda un rol sino una **coreografía**. Su hermano `.cta-prime` —la
barra de compra de móvil— sí sigue en el rol, y ahí el mockup también lo pinta de acción: es una
diferencia REAL entre las dos piezas, no una incoherencia nuestra. El rol pasa de 13 reglas a 12.

### 7.3 ⚠️⚠️ Y el anillo de foco NO podía quedarse como estaba

`--focus-color` sigue a la superficie, y en el paquete de este cliente vale **su mismo Amarillo
Aviso**. Un botón de aviso con un anillo de aviso deja el **foco de teclado invisible** — no falla,
no avisa, y solo lo nota quien no usa ratón. Dentro del menú el anillo pasa a tinta: **11,26** sobre
el amarillo. Es la misma clase de defecto que `#206` encontró en su paleta, y esta vez se vio venir.

### 7.4 Las cinco diferencias de forma

| | Antes | Ahora (mockup) |
|---|---|---|
| Rótulo | «Reservas aquí», fuente de TEXTO, peso 600, 13 px | **«Reservar», fuente de RÓTULO, MAYÚSCULAS, 17 px** |
| Flecha | `→` | **no lleva** (retirada del marcado y sus dos reglas) |
| Subtítulo | mono, mayúsculas, tracking `.10em`, color por token | **plano 11 px, `color: inherit`, opacidad .62** |
| Chip del icono | 30×22, `rgba(255,255,255,.10)` en crudo | **30×26**, velo por token (`--bg` al 14 %) |
| Chip dentro del menú | no cambiaba | **invierte**: velo oscuro al 16 % |

⚠️ **Lo del subtítulo no es estilo, es corrección**: el botón pinta de **tres** colores distintos
—tinta, marca al pasar el cursor y aviso dentro del menú—, así que *cualquier* token elegido para
su texto falla en dos de los tres. Heredar y bajar la opacidad es lo único que vale en los tres, y
es también lo que hace el mockup. De paso se va un `rgba(255,255,255,.72)` en crudo.

▶ **La fuente va por TOKEN** (`--font-display`), no por nombre: así una instalación la cambia con
`THEME_FONTS` y el rótulo la sigue. Verificado en navegador: rinde **Bungee**.

### 7.5 Verificación

- **`ArmazonContractTest` +2 casos** · `ActionFillTest` re-apuntado (`.cta-med` fuera del rol y sus
  piezas internas separadas de las de `.cta-prime`, que estaban en reglas COMPARTIDAS).
- **8 mutaciones, las 8 muerden** — incluidas «el foco se queda amarillo sobre amarillo» y «el CTA
  vuelve al rol de acción».
- **Sonda de navegador 14/14** (`VERIFICACION-E2E-CAJON.md` §5.quindecies).
  ⚠️ **Y una trampa nueva del instrumento**: Chromium devuelve un `color-mix()` resuelto como
  `color(srgb r g b / a)` con los canales en **0–1**, no como `rgba()`. La primera comprobación del
  velo buscaba `rgba(` y dio ROJO con el CSS correcto.

---

## 8. La 2c·7 — el CTA es un PAR, y se descubre solo (`#214`, 2026-08-28)

> `[DECIDIDO owner, 2026-08-28]`, describiéndolo él: «el icono de al lado del CTA —registrarse, o
> iniciar sesión, o mi cuenta según la situación— al darle clic **se desplaza con una transición y
> se convierte en más ancho**, y el botón de reservar **se vuelve solo un icono**. Ese CTA es
> "doble". Y tiene una animación invitando a darle clic para hacer esa transición».

### 8.1 La mecánica, medida en el mockup

| | |
|---|---|
| Estado | `ctaModo` ∈ `reserva`\|`registro` (arranca en **reserva**) + `ctaTocado` |
| Anchos | expandido **224 px** (182 en ≤1100, 138 en teléfono) · colapsado **56 px** |
| 1.er clic en la colapsada | **la expande** y colapsa la otra; marca `ctaTocado` |
| 2.º clic (ya expandida) | **actúa**: reservar → paso 1 · registro → paso 5 |
| Invitación | mientras `reserva && !tocado`: la otra mitad **asoma** (`translateX` −7/−3 px) y un **aro** late (`opacity 0→.5→0`, `scale .7→1.45`), ciclo de **4,6 s** |

▶ **Ya lo teníamos en móvil** (`#205`) y **no en la cabecera**, que eran dos botones sueltos
siempre expandidos. La 2c·7 lleva la mecánica al racimo y **une los dos estados**.

### 8.2 ❗ El estado sube a un STORE, porque son la misma decisión

`mode` vivía dentro de `mobileBookBar`. Ahora vive en `$store.ctaPair` (`mode` + `touched`) y lo
leen los dos. No coexisten en pantalla, pero **quien expande «mi cuenta» en escritorio y estrecha
la ventana tiene que encontrarse la barra igual**: con dos copias se separan solas.

⚠️ **El store publica ESTADO y nada más.** Los anchos, la transición y el apagado de la invitación
los decide el CSS con **dos clases**. Si los anchos vivieran en el JS serían la única parte del tema
que un cliente no puede tocar — misma regla que el hero (`#195`), el menú (`#201`) y la barra
(`#205`).

⚠️ **Se colapsa el CUERPO, no el botón.** Con un `width` fijo el icono se descentraría durante la
transición y el objetivo táctil dejaría de ser el mismo elemento; con `max-width` sobre el texto el
botón encoge hasta su icono **por su propio padding** y nunca baja del mínimo táctil.

### 8.3 Tres cosas que la 2c·7 arregla de paso

1. ❗ **La rama con sesión era la ÚNICA del par sin suelo sin JavaScript**: un `<button>` que no
   hacía nada. Pasa a `<a href="/mi-cuenta">`, que es una PUERTA — el clic central, «abrir en
   pestaña nueva» y un navegador sin JS acaban en la misma pantalla por el camino largo.
2. **El rótulo de la cuenta vuelve, pero FIJO** («Mi cuenta»). `#204` retiró el saludo visible
   porque «Hola, Marta» y «Hola, Wilhelmina» cambiaban el ancho con cada visitante; un rótulo fijo
   no tiene ese problema, y el saludo sigue en el nombre accesible. ⚠️ El visible es **prefijo** del
   accesible: «label in name» (WCAG 2.5.3), o quien dicta por voz «pulsa Mi cuenta» no activa nada.
3. **La flecha del botón fantasma se va** con sus tres reglas de CSS: su marcado desapareció y
   `ArmazonCssHasNoOrphansTest` la cazó en el mismo corte.

### 8.4 ⚠️⚠️ Y la invitación se apaga por DOS motivos, no uno

- **Al tocar el par**, sea cual sea la mitad. Una animación que sigue llamando la atención después
  de que le han hecho caso deja de ser una invitación y pasa a ser ruido.
- **Con `prefers-reduced-motion`**, y sin sustituto: el asomo y el aro son decoración en bucle, así
  que aquí «reducir» es «no hacerlo», no «hacerlo más despacio». Hay gente a la que el movimiento
  repetido le produce náuseas.

▶ La duración entra como token propio (`--dur-invite: 4.6s`): es de otra familia que
`--dur-collapse`/`--dur-fade` —éstas responden a un gesto, ésta es un latido ambiental— y sin token
una instalación tendría que reescribir dos `@keyframes` para invitar más o menos a menudo.

### 8.5 Verificación

- **`ArmazonContractTest` +3 casos**, y **cuatro tests existentes RE-APUNTADOS** —no retirados—
  porque su sujeto seguía vivo: los que contaban `nav-cta-ghost` para saber si se ofrecía el alta
  (ahora se comprueba **por el destino**), el del nombre accesible (ahora exige que el visible sea
  **prefijo**) y la guarda del `mode` del cajón, que contaba `this.mode =` en TODO `app.js` y
  casaba con el `mode` del store nuevo — **acotada al store `purchase`, que es de quien habla**.
- **9 mutaciones, las 9 muerden.** ⚠️ **Dos de mis guardas nacieron LAXAS y lo demostró el arnés**:
  una miraba si la cadena `$store.ctaPair` aparecía «en algún sitio» del atributo —y pasaba con el
  reparto de anchos leyendo una variable local, porque la invitación sí usaba el store—; la otra
  aceptaba cualquier `animation:` bajo `--invita`, y el **aro** la cumplía, así que retirar el
  asomo la dejaba verde. **Media invitación es la que no se ve.**
- **Sonda de navegador 12/12** (`VERIFICACION-E2E-CAJON.md` §5.sexdecies).
  ⚠️ Y una trampa del propio guion: se aseveró el intercambio con un umbral de «60 px más», y la
  mitad de la cuenta expandida mide MENOS que la de comprar (su rótulo es una palabra). **Se
  asevera el hecho —cada una cruza a la posición de la otra—, no un número inventado.**

---

## 9. La 2c·8 — el armazón NACE BAJO EL HERO y el CTA es el del mockup (`#216`, 2026-08-28)

> `[DECIDIDO owner, 2026-08-28]`, cuatro respuestas a pregunta simple:
> **(1)** se copia el mockup ENTERO —el hero recupera sus dos botones y el armazón se oculta—;
> **(2)** el logotipo es el de la **silueta**; **(3)** el set de icono entra **completo**;
> **(4)** del CTA doble se alinean **las ocho** diferencias medidas.

### 9.1 ❗❗ Lo primero: esto REABRE `#195`, y no es un capricho

`#195` retiró el CTA del hero **y el mockup se lo ha devuelto**. Hasta esta tanda la postura era
«no lo copiamos» (`[DECIDIDO owner, 2026-08-28]`, mañana). La tarde del mismo día cambia, y el
motivo es que **las dos mitades no se pueden separar**:

| Si haces… | …pasa esto |
|---|---|
| solo ocultar el armazón bajo el hero | la primera pantalla se queda **sin logo, sin menú y sin comprar**. Es el agujero de `#211`, por otra puerta |
| solo devolver los botones al hero | el armazón sigue visible desde el primer píxel y **no se parece al mockup** |
| las dos | el mockup, y sin agujero: el hero ofrece la compra mientras el armazón no está |

▶ **El mockup puede ocultar su cabecera porque su hero ofrece la acción.** Ésa es la relación de
causa que faltaba en `§4.4`, donde este documento declaró la coreografía «no sostenible».

### 9.2 La coreografía, medida en `aplicaFlotantes`

Aritmética del mockup, tal cual:

```
bruto  = clamp(scrollY / RUNWAY, 0, 1)          RUNWAY = 420  (el nuestro también, ya)
nb     = clamp((bruto − 0,18) / 0,44, 0, 1)
ne     = 1 − (1 − nb)³                          ← la misma curva de salida del hero
v      = ne · (1 − salida)³ · dir
```

- `opacity: v` · `transform: translateY(−16 + 16·v)` · `pointer-events: v > 0,85`
- `transition: opacity .25s ease, transform .35s cubic-bezier(.2,.9,.2,1)`
- **`salida`** sale de su *hero de cierre* (el pie a pantalla completa). **Nosotros no lo tenemos**,
  así que ese factor no se implementa: sería un mecanismo entero por copiar un término.
- **`dir`** es la retirada al bajar, que ya teníamos (`navHidden`, 2c·2). Lo único que faltaba es
  su línea `if (y <= finHero) ocultoDir = false`.

✅ **Verificado con la aritmética, no de vista**: a `scrollY = 120` la fórmula da
`1 − (1 − (120/420 − 0,18)/0,44)³ = 0,5617`; el navegador midió **`--nav-p: 0.561`**.

### 9.3 Dónde vive cada cosa, y por qué

| Pieza | Dónde | Por qué ahí |
|---|---|---|
| el progreso (`--nav-p`) | `heroChoreo`, en `app.js` | **sale del MISMO recorrido que el hero**. Con dos señales —un observador y el scroll— el armazón podía entrar antes o después que el hero según el navegador |
| el retardo y la ventana | tokens de CSS (`--nav-reveal-start`, `--nav-reveal-span`) | son TIEMPO, y el tiempo es tema. Un paquete puede hacer que entre antes, después o de golpe |
| «ya se puede pulsar» | clase `.nav--live` | `opacity: 0` **no** deja de recibir clics. El JS publica el hecho binario; qué significa lo decide el CSS |
| el suelo sin JS | `<noscript><style>` en el componente | si Alpine no arranca, nadie publica `--nav-p` y la portada se queda **sin navegación**. No es una degradación: es un sitio roto |

⚠️ **`navCtaReveal` se RETIRA.** Ocultaba solo el botón de comprar con un `IntersectionObserver`
sobre `.hero__sentinel`; su trabajo lo hace ahora la coreografía entera. Mantener los dos era tener
**dos mecanismos ocultando el mismo botón con señales distintas**.
▶ Con él se va `body.nav-cta-revealed`, que **ninguna regla de CSS leía**: se ponía y se quitaba
«para que otros elementos pudieran reaccionar» y nadie reaccionó nunca.
▶ El `.hero__sentinel` **no** se retira: lo sigue observando `mobileBookBar`.

### 9.4 Las ocho del CTA doble, medidas una a una

| | Antes (medido en navegador) | Mockup | Ahora |
|---|---|---|---|
| Orden | cuenta, luego comprar | **comprar, luego cuenta** | alineado |
| Ancho | 167 / 70 px, por contenido | **224 / 56** (182 · 138) | fijo, por token |
| Alto | 53 y **42** — desiguales | **54 las dos** (48 en teléfono) | igual |
| Colapso | `max-width` sobre el TEXTO, .34s | **`width` sobre el BOTÓN, .46s** | como el mockup |
| Texto | aparece y desaparece | **entra desplazado, con .14s de retardo** | como el mockup |
| Sombra | ninguna | difusa | **`--shadow-float`**, por ROL |
| Hover | `translateY(-2px) scale(1.02)` | solo la sombra | ya no salta |
| Fantasma en el menú | se teñía de tinta | **se queda blanco** | lee los alias `--paper-*` |

⚠️⚠️ **`§8.2` razonó lo CONTRARIO del `width` fijo y hay que decirlo**: «con un `width` fijo el
icono se descentraría durante la transición». Era correcto **para nuestro layout**, no para el del
mockup: con `justify-content: flex-start` y un `padding` IZQUIERDO fijo, la posición del icono no
depende del ancho. Y la cuenta cuadra exacta: **13 + 30 + 13 = 56**, que es el ancho colapsado.

⚠️ **El radio baja a `--r-md` (10 px) y el aro se queda en `--r-btn` (14)**: en el mockup el
mobiliario flotante usa 10 y los botones de contenido 14, y con `inset: -4px` los dos cantos quedan
**concéntricos**. Con los dos a 14 el aro se pegaba al botón por las esquinas.

⚠️ **La sombra entra por ROL y ahí hay OTRA contradicción del cliente**: su mockup pinta una difusa
(`0 10px 28px`) y su propio hallazgo `M-05` dice que las difusas son solo para modal — que es lo
que `#196` implementó y lo que su `client.css` declara (`5px 5px 0`). Se sigue **el sistema**, que
es lo que deja al paquete decidir. Ficha en `DEUDA.md`.

### 9.5 Dos huérfanos que la medición destapó

1. **`cta-med__t--mobile`**: un rótulo alterno «controlado por @media». Medido: su única regla era
   `display: none` y **ninguna media query la levantaba**. Nunca se vio. Se va, y con él el caso que
   aseveraba su presencia en el HTML.
2. **El bloque móvil de la Capa D**: tres reglas del mecanismo retirado —el colapso del fantasma
   «para ceder espacio al filled emergente», su `max-width` y un padding compacto que peleaba con la
   altura fija—. Por debajo de 720 px el racimo pasa a ser **solo la hamburguesa**, como el mockup
   (que lo hace a 620; el corte se queda en el nuestro porque es donde entra `.book-bar`, y moverlo
   dejaría 100 px de ancho sin CTA en ningún sitio).

### 9.6 La MARCA: el paquete del 2.º cliente, entregado

El owner entregó `marca/` en el canvas (28 ficheros). Lo que el producto tenía era hueco para
**dos** cosas; ahora son **nueve**:

| Hueco | Fichero del canvas | Estado |
|---|---|---|
| `client-logo.svg` | `logo-pjp.svg` (con silueta) | ya existía |
| `client-favicon.svg` | `favicon.svg` | ya existía |
| `client-logo-ink.svg` | `logo-pjp-blanco.svg` | **hueco NUEVO** |
| `client-logo@4x.png` | rasterizado del vector | **hueco NUEVO** |
| `client-favicon.ico` | 16/32/48 en un contenedor | **hueco NUEVO** |
| `client-apple-touch-icon.png` | 180 | **hueco NUEVO** |
| `client-icon-192.png` · `-512.png` | del vector maestro | **huecos NUEVOS** |
| `client-icon-512-maskable.png` | del vector maskable | **hueco NUEVO**, sin declarar: hace falta un manifiesto |

⚠️⚠️ **El logotipo sobre TINTA no es un adorno**: desde `#201` el menú es una superficie oscura a
pantalla completa y el armazón entra dentro. Un logotipo de TEXTO se adapta solo (hereda `--fg`);
**una imagen no**. Se sirven las dos y elige el CSS por `[data-surface]`.
▶ **Y con dos imágenes el nombre accesible se duplica o se pierde**: `display: none` saca el `alt`
del árbol de accesibilidad, así que la que quedara visible en tinta estaría muda. Con las dos, las
dos van `aria-hidden` y el nombre lo pone un `sr-only` que **está siempre**. Con una sola se
conserva el `alt` de siempre.

### 9.7 ⚠️⚠️ Tres trampas que costaron tiempo, y las tres son del INSTRUMENTO

1. **`DesignSync · get_file` TRUNCA los binarios a 192 KiB y no falla**: devuelve `truncated: true`
   y un PNG con cabecera válida, dimensiones correctas y sin `IEND`. Así están **tres** ficheros de
   `mockup_playjumppark/assets/` bajados en otra sesión (`logo.png`, `fachada-mural.jpg`,
   `fachada-rotulo.jpg`); los otros cinco están enteros. ▶ **Los rasters grandes se generan del
   VECTOR**, que sí baja entero.
2. ❗ **Transcribir base64 desde el contexto CORROMPE el fichero sin avisar.** Medido: un PNG de
   6.900 B salió de 4.632 B, sin `IEND`… **y con cabecera PNG válida y dimensiones correctas**. Un
   verificador que mire solo la cabecera lo da por bueno. ▶ Los ficheros que no se puedan extraer
   del disco se **generan**, no se copian a mano.
3. **Mi propio verificador de PNG dio «ROTO» en once ficheros recién generados por Chromium.** El
   fallo era la comprobación (`raw[-8:]` en vez de `raw[-12:]`, y escapes comidos por las comillas
   de bash). ▶ **Cuando un instrumento dice que NADA funciona, la primera hipótesis es el
   instrumento** — y esa misma medida mala me hizo escribir antes que «los 8 rasters locales están
   rotos» cuando son **3**.

### 9.7.bis ⚠️ Y una CUARTA, que la sonda no vio y sí la captura

Los dos botones del hero salían **APILADOS**, no en fila. La causa no es el `flex-wrap`: el
contenido del hero es un flex de **columna con `align-items: flex-start`**, así que la fila se
encogía a su contenido mínimo —327 px, el ancho del botón más ancho— y el segundo envolvía debajo.
Se arregla con `width: 100%` junto al `max-width: 640px`, y **esa declaración parece redundante
leyendo el CSS**: es justo lo que alguien retira en una limpieza. Por eso tiene guarda.

▶ **La lección de método**: la sonda medía existencia, color y tamaño de cada botón —y los dos
estaban bien—. Lo que estaba mal era **dónde**, y eso solo se ve mirando. Medido: antes 327 px de
ancho con los dos a `x = 50`; después 640 px, con `x = 50` y `x = 396`.

### 9.8 Verificación

- **`ArmazonContractTest` +7 casos** · **6 tests RE-APUNTADOS, no retirados** (su sujeto sigue
  vivo): el del menú abierto pasa a mirar el racimo entero —ahora se juega también la X de cerrar—,
  el de `navCtaReveal` pasa a aseverar **el hecho de producto** (el hero ofrece la compra) en vez
  del nombre de un componente, el del rótulo alterno pasa a exigir que NO vuelva, y `ActionFillTest`
  declara `.hero__act--buy` como acción nueva.
- **Sonda de navegador**: la coreografía verificada contra la aritmética del mockup, los anchos
  224/56 exactos, el intercambio, el aviso dentro del menú y **el fantasma blanco**.
- **Arnés de mutación**: **quince** mutaciones, cada una con el fallo REAL de su guarda — las 15
  muerden. Incluye las dos guardas que hubo que **acotar** (no aflojar) porque aseveraban por
  subcadena, y la del `width` del hero.

---

## 10. La 2c·9 — las tres piezas que el OJO del owner vio distintas (`#217`, 2026-08-28)

> `[DECIDIDO owner, 2026-08-28]`, mirando la 2c·8 contra su mockup: «las sombras son diferentes
> para el botón y el icono, la forma del menú es diferente, y el logo está float sin ningún
> background detrás y es más grande».
>
> ▶ **Las tres eran ciertas y ninguna la veía la sonda.** La sonda medía existencia, color, tamaño
> y estado —todo correcto— porque la 2c·8 alineó el CTA doble y **no miró las otras dos piezas del
> racimo ni el logotipo**. Es la misma lección de §9.7.bis, otra vez: *medir no es mirar*.

### 10.1 Las sombras: entra un CUARTO rol

`#216` dejó el racimo con `--shadow-float` («flota sobre el contenido»), y con el paquete del 2.º
cliente eso rinde su sombra **dura** (`5px 5px 0`). Su mockup las pinta difusas. Era la ficha de
`DEUDA.md` sobre la tercera contradicción de sus fuentes, y **el owner la resuelve: gana el
mockup**, como ya pasó con el color del CTA.

Medido en `Landing PJP Modos`:

| Pieza | Reposo | Al pasar el cursor |
|---|---|---|
| Reservar (relleno de tinta) | `0 10px 28px rgba(16,20,24,.24)` | `0 18px 40px …,.32` |
| Registro (fantasma) | `0 10px 26px rgba(16,20,24,.18)` | `0 16px 36px …,.26` |
| Menú | `0 10px 28px rgba(16,20,24,.16)` | **no toca la sombra: LEVANTA el botón** |

▶ **Son CINCO tokens, no uno**, y no es proliferación: el mockup declara **tres pesos** —el relleno
de tinta pesa más y proyecta más— y **dos alturas**. Van al sistema (`landing.css`), leen
`--paper-fg` como los otros tres roles, y una instalación los redefine.
⚠️ **`M-05` del cliente sigue valiendo para el resto**: lo que cambia es que el mobiliario flotante
deja de estar cubierto por esa norma, porque **el propio cliente lo dibuja así**.

### 10.2 El botón de menú: la forma del mockup, y lo que cuesta

| | Antes | Ahora |
|---|---|---|
| Forma | **círculo** de 44 px | rect de `--r-md`, alto del racimo (54 · 48), padding `0 18px` |
| Dibujo | dos iconos del set, cruzados por opacidad | **dos rayas de 19 y 12 px que ROTAN** hasta formar la X |
| Texto | ninguno | etiqueta **«MENÚ» / «CERRAR»** en mono, oculta bajo 620 px |
| Fondo | tarjeta | tarjeta; **abierto, papel macizo** y sin keyline visible |
| Sombra | ninguna | `--shadow-nav` |
| Hover | invertía y `scale(1.08)` | `translateY(-2px)` |

⚠️⚠️ **Esto CUESTA algo concreto: el hueco de icono por instalación se pierde EN ESTA PIEZA.**
`#211` eligió `x-icons.menu`/`close` justamente porque el dibujo es uno de los tres mecanismos del
tema. Dos rayas de CSS no salen de ningún set. El owner lo decidió con el coste delante; ficha en
`DEUDA.md`. ▶ **A cambio se gana el giro**: la X no aparece, **se forma**.

⚠️ **Los colores salen de los alias `--paper-*`.** Con el menú abierto el armazón está dentro de una
superficie de tinta, donde `--bg` vale oscuro: `background: var(--bg)` habría pintado el botón de
negro sobre negro. Es la misma corrección que el fantasma del par en `#216`.

⚠️ **Y el objetivo táctil**: aquí había un `@media` que forzaba 44×44 «para no encoger en móvil»
(Lote 9), y con la forma nueva eso lo devolvía a **cuadrado**. Se retira: el alto es 48 en teléfono
y el ancho lo da el padding, así que pasa de sobra.

### 10.3 El logotipo: flota, y su sombra sigue la SILUETA

El logotipo llevaba una **pastilla** —fondo de tarjeta, keyline y padding— con este razonamiento
escrito al lado: «el mockup lo resuelve con un `drop-shadow` sobre su logotipo, pero **aquí la
nuestra es TEXTO** y esa vía está cerrada por `M-05`».

▶ **El razonamiento era correcto cuando la marca era texto.** Desde `#216` una instalación sirve su
LOGOTIPO, que es una imagen: la vía del mockup se abre.

⚠️ **La pastilla no se borra: se acota a quien la necesita.** Sigue sosteniendo el suelo del
producto —el nombre en la fuente de rótulo, que sin nada detrás queda ilegible en cuanto pasa una
tarjeta por debajo— y desaparece cuando hay logotipo. Lo decide `:has(.nav__brand-row)`, o sea **el
CSS y no el servidor**: el hueco lo rellena un componente y la barra no sabe qué le van a meter. Si
el navegador no entiende `:has()`, se queda la pastilla — el estado seguro, no el roto.

⚠️⚠️ **`drop-shadow`, no `box-shadow`, y ésa es toda la diferencia**: `box-shadow` proyecta la
CAJA —un rectángulo, aunque el dibujo tenga forma— y `drop-shadow` sigue el **alfa** de la imagen.
Es lo único que deja flotar una marca recortada sin caja detrás. Son **dos**, como el mockup: una
difusa que la despega y **un contorno de 1 px** para cuando cae sobre algo claro.

▶ **Y sube de 26 px a 54**, que es lo que mide en el mockup. A 26 px un lockup de DOS LÍNEAS —que
es lo que es el del 2.º cliente— deja cada palabra en 13 px y deja de leerse.

### 10.4 Verificación

- **`ArmazonContractTest` +3 casos** y **1 re-apuntado**: el del aspa, que exigía dos glifos del set
  y ahora exige que **las dos rayas giren** (con una sola girando el aspa sale con un palo torcido,
  y aseverar «hay una regla bajo `.nav--over`» lo dejaría pasar).
- ⚠️ **Y una guarda propia nació DEMASIADO GRUESA**: prohibía la clave `.nav__brand` entera, y esa
  regla también lleva el **layout** del hueco. Salió roja con el código correcto. Se acota a lo que
  de verdad protege: que no PINTE (`background`, `border`).
- **11 mutaciones, las 11 muerden** — incluidas «vuelve la pastilla para todos», «el suelo de texto
  se queda sin pastilla», «el logotipo se sostiene con `box-shadow`» y «solo una raya gira».
- Medido en navegador: logotipo **147 × 54 sin fondo ni borde** con el `drop-shadow` doble · botón
  de menú **103 × 54, radio 10, sombra `.16`**, y **121 × 54 en papel macizo** con el menú abierto ·
  las tres sombras del racimo en sus valores (`.24` / `.18` / `.16`).
