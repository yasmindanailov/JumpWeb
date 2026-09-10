# Estado del proyecto — foto viva

> ❗❗❗ **POR DÓNDE SE RETOMA — LEE ESTO Y NADA MÁS DE ESTE BLOQUE.** 🎨 **EL CARRIL DE DISEÑO ESTÁ ABIERTO Y ES LO VIVO** (2026-09-10, `#469`→`#487`). ▶ **EN UNA LÍNEA: la Fase 1 está CERRADA y la Fase 2 va por su SÉPTIMA tanda — quedan DOS secciones, y una está bloqueada por el owner.**
>
> ▶ **LA T2g ESTÁ EN EL ÁRBOL: la sección 07 «Visítanos»** (`#487`). Hoy la sección es: cabecera
común, **una sola tarjeta blanca** con el estado en vivo y la tabla del horario, el mapa tras el
bloqueo previo, y la dirección **siempre fuera del marco**. Suite **4.564** · **13/13 mutaciones** ·
comparador **21 y 13 valores idénticos, 0 divergencias sin explicar**.
>
> ▶ **Se saltó la 06 «Reseñas» a propósito**: por orden del canvas tocaba, pero **está bloqueada por
el owner** —`specs/google-reviews.md` espera su ✅ y **tres datos** (`place_id`, clave de API y techo
de gasto)—. Quedan **06** y **08 «Dudas»**.
>
> ❗❗❗ **CUATRO ESTADOS DONDE HABÍA DOS, y es lo que más valía de la tanda.** Regla dura del sistema:
*«hoy no abre» y «hoy ya ha cerrado» son hechos distintos*. Medido: `HeroStatus` solo distinguía dos,
así que **un jueves ya cerrado y un lunes de cierre decían lo mismo** con la tabla de horarios justo
debajo. ⚠️ Entran `face`, `title` y `line` **sin tocar `status`**, que lo leen el chip del hero y el
del menú: son piezas del ARMAZÓN y moverlas desde una tanda de sección es lo que `#479` evitó.
>
> ❗❗ **Y el día se resalta SOLO mientras su horario está vigente**, que son DOS condiciones: `is_today`
—ya se apagaba con una temporada o una fecha especial (`#307`)— **y** que el estado sea «abierto» o
«abre hoy». Con el parque cerrado, un resaltado dice «esto es lo que rige ahora» sobre horas que ya
pasaron.
>
> ❗❗❗ **TRES DECISIONES DEL OWNER, y las tres son la misma regla: el producto no afirma lo que no
puede saber.** El **aparcamiento** no entra (el dato no tiene campo en el panel y hay dos versiones en
conflicto; `#297` ya lo había decidido una vez) · **«los festivos, como el finde»** no se escribe ·
y **el teléfono con «Cómo llegar»** salen, como el canvas. ⚠️ El teléfono sigue en el menú y en el
pie; lo que sí se pierde es la puerta a Google Maps **cuando el mapa carga**, y **vuelve con el mapa
bloqueado**. ▶ Esto **revierte parte de `#307`**, a sabiendas.
>
> ❗❗ **Y LA ENTRADILLA SE DERIVA POR ESA MISMA REGLA.** El canvas escribe «Abrimos todos los días…»,
que es cierto aquí y **falso en cualquier instalación que cierre un día**: `weeklyLede()` dice
cuántos horarios hay y cuáles. ⚠️ Cuenta los grupos **ABIERTOS** — tres filas con un sábado cerrado
son **dos** horarios, no tres.
>
> ⚠️⚠️ **DOS DEFECTOS QUE SOLO VIO LA CAPTURA, con la suite en verde**: `aspect-ratio: 16/9` +
`overflow: hidden` **en el contenedor** recortaba el bloqueo previo del mapa y **se comía el enlace a
la política de cookies** —la proporción es del **iframe**, no de la caja—; y la dirección llevaba
`visit__addr`, que es **la clase de `/contacto`**, así que se vestía con otra página **y dejaba
muertas las reglas de ésta**.
>
> ⚠️ **Y la trampa que casi se paga otra vez**: `.visit-card`, `.visit-card__ico`, `.visit__addr` y
`.visit__actions` **las usa `/contacto`** — la de `#485` con `.rule*`. Se conservan y la sección
**estrena las suyas**.
>
> ▶ **PASOS DE DESPLIEGUE (la lista viva está en la §5.bis de la spec)**: **`--ok-ink: #447921`** y
**`--attn-ink: #8A6E00`** en el `client.css` de producción. ❗ Sin el primero el «Abierto ahora» se
pinta con `--ok`, que en ese paquete da **2,4** sobre blanco — pero **el gate lo caza**
(`ClientThemePackageTest`). Y **DATO, desde el panel**: el horario en conflicto (el panel dice **L–V
16:30 · S–D 11:00** y el canvas **L–J 16:30 · V–D 11:30**) y las **fechas especiales**, que hoy son
cero: sin ellas no hay ni aviso ni pliegue, y eso es la conducta correcta.
>
> ▶ **LO SIGUIENTE: la T2h.** Si el owner desbloquea Reseñas, va esa; si no, **08 «Dudas»**
(`Dudas PJP` 1a + `Escritorio PJP` 5c), que es data-driven sobre `faqs` y **arrastra cuatro
decisiones suyas** (el aparcamiento, si grupos lleva al correo o a `/servicios`, si «interior y
climatizado» sube a 03, y qué otras dudas oyen en el mostrador). ❗ **Pregúntaselas ANTES de
escribir**, que es el protocolo del carril (`#469`). ⚠️ **Relee su artboard antes Y durante**: en esta tanda las
dos copias salieron idénticas al canvas —la primera vez en el carril—, pero en tres de las cuatro
anteriores el artboard se había movido.
>
> 📜 **LO QUE DECÍA ESTE SITIO ANTES DE LA T2g** (la T2f, `#485`/`#486`).
>
> ▶ **LA T2f ESTÁ EN EL ÁRBOL: la sección 05 «Antes de venir»** (`#485`). Hoy la sección es:
cabecera común `.sec-head` —rótulo, titular «Tu registro es este QR» y la entradilla del
entregable—, un **bloque de tinta** con el código dentro de un móvil y, al lado, **lo que ese código
lleva** (tus reservas · tu firma · tus hijos), la excepción de los calcetines en papel, y **dos
salidas sin relleno de acción**: «Ver todas las normas» y «Crear mi cuenta». Suite **4.556** ·
**13/13 mutaciones** · desborde 0 en las 13 vistas.
>
> ❗❗❗ **NO ES UNA SECCIÓN NUEVA: SUSTITUYE A LA DE NORMAS** (`#309`), que decía las dos mismas cosas
—registrarse y traer calcetines— más un asomo de cuatro normas. Con eso **se cierra la ficha de
`DEUDA.md` que `#480` abrió**: `/normas` vuelve a tener entrada desde la portada. ▶ **Las cuatro
decisiones del owner**, preguntadas antes de escribir una línea: el código es **de ejemplo y no lleva
a ningún sitio** · las **cuatro normas se van** y queda el enlace · la sección va **donde la pone el
canvas** (un puesto por delante, antes de Visítanos) · y los nombres son **los del PRODUCTO** —«Mi
QR» y «Crear mi cuenta»—, porque la cuenta ya llama así a ese código y el mismo objeto no puede
llamarse de dos maneras.
>
> ❗❗❗ **LO QUE `#485` DEJA Y VALE PARA LAS TRES QUE QUEDAN.**
> **(1) La SUPERFICIE no puede depender del ancho de la ventana.** `[data-surface]` no solo cambia
tokens: **PINTA** (`background: var(--bg)`, la lección de `#484`). Los dos artboards de esta sección
discrepan —el de móvil (7 sep) la pone en papel y el de escritorio (8 sep) en un bloque de tinta— y
entre dos fuentes del canvas que se contradicen **manda la más nueva**, que además trae la idea que
ordena la sección: *«la sección ES el código»*.
> **(2) Y por CUARTA vez, el acta describe una pieza que el artboard tiene APAGADA**: aquí la chapa
de «lo que ve el empleado», tras `conChapaEmpleado` desde el recorte del 7 sep. En escritorio vuelve,
y el motivo es aritmético y suyo: allí va en la columna de al lado y no cuesta alto.
>
> ⚠️⚠️ **DOS GUARDAS MÍAS NACIERON DEMASIADO ESTRECHAS Y LO DIJO EL ARNÉS, no una relectura.** Una
aseveraba `'<section id="rules"'` y **una mutación que devolvía el ancla en un `<span>` pasó en
verde** —*la propiedad dice que ese destino ya no existe, no que no exista una sección con ese
nombre*—; la otra comprobaba que la sección no pide dibujos **instalando un kit sin esas claves**, o
sea midiendo una ausencia que el propio arnés causaba. ▶ Y una **mutación era DÉBIL por precedencia**:
`false && A || B` es `B` en PHP.
>
> ❗❗ **EL CÓDIGO ES UN DIBUJO Y NO PUEDE SER OTRA COSA** (`SampleQrCode`, **al lado del generador de
verdad a propósito**): lo que hay que impedir no es que alguien lo rompa, es que alguien lo
«ARREGLE» cambiándolo por `QrCode::svg()`. La propiedad no es que se parezca a un QR: es que **no se
pueda decodificar**, y es **estructural** —la banda de la información de formato queda vacía—. ⚠️ Esa
banda **no estaba reservada y la añadió una guarda**: con el ruido cayendo dentro, «no se puede
decodificar» era incidental en vez de estructural.
>
> ⚠️ **Dos cosas que NO se escriben, con su motivo**: el **precio de los calcetines** —el producto no
sabe cuál de sus complementos son «los calcetines», y averiguarlo por su icono sería usar un campo de
PRESENTACIÓN como identidad (el defecto de `accent` de `#295`/`#301`); además la cifra ya se publica
en el carril de la 02— y la **línea del niño invitado**, que es DATO: sale si y solo si algún producto
ofrece el justificante, y hoy ninguno.
>
> ⚠️⚠️ **Y una trampa de la familia de botones que encontró la CAPTURA, no la suite**: `.btn` **no
declara `justify-content`**, así que en cuanto recibe un ancho su rótulo se va a la izquierda. Las
**seis** reglas del repo que dan ancho a un `.btn` ya lo declaran a mano: la convención existe y no
estaba escrita. Ficha en `DEUDA.md`.
>
> ▶ **PASO DE DESPLIEGUE: ninguno de CSS** —la sección se viste entera con roles ya declarados—. Solo
**DATO**: si el parque quiere ofrecer el justificante del niño invitado, se marca en el producto
(`ticket_types.guardian_authorization`). La lista viva está en la §5.bis de la spec.
>
> ❗❗❗ **`#486` · EL OJO DEL OWNER SOBRE LA T2f, y una de las dos cosas NO ERA DE ESTA SECCIÓN.**
> ▶ **«La barra fina de debajo del CTA»** era la **costura entre secciones**: `.section + .section
{ border-top }`, herencia del diseño anterior que pintaba las **siete** costuras de la portada. Se
retira, y no es gusto —el sistema separa secciones **solo con aire**, «uniforme de arriba abajo», y
ninguno de los cinco artboards dibuja un divisor—. ⚠️ Medido antes: **solo existía en la portada**
(ninguna otra página usa `.section`). ⚠️ Se notaba justo ahí porque el pie de la 05 ya lleva su
propia raya, así que salían **dos líneas seguidas**.
> ⚠️⚠️ **Y la guarda invertida que se escribió para eso nació «RISKY» con el producto sano**:
aseveraba dentro de un `foreach` que, sin regla que recorrer, dejó de vigilar **sin ponerse roja** —
la trampa que `#482` dejó escrita hace dos días. Reescrita con una aserción que corre siempre.
> ▶ **El botón: tenía razón, y no es un botón de esta sección.** El sistema lo declara **16/800 con
borde de 1,5 px en Azul Muro**; el nuestro es 14/600 con borde de tinta. ⚠️⚠️ **El ejemplo que la
hoja de componentes usa para ese botón es literalmente «Cómo llegar»**, que en nuestra web es el
mismo `.btn--ghost`: es **EL** botón secundario del sistema, no el de una pantalla. Y **todos los
botones de la portada están a 14/600**, también los ya aprobados. `[DECIDIDO owner]`: *«si es la fase
4 vale, lo dejamos por ahora»* — llega a 45 usos y 11 ficheros del cajón, así que **espera a su
tanda**, con las cuatro cifras ya medidas en `DEUDA.md`.
> ▶ **La revisión se hizo valor a valor, no mirando** (la lección de `#478`): **22 idénticas en móvil
y 23 en escritorio, 0 divergencias sin explicar**. Encontró una que sí era mía —la chapa del aviso a
20 donde el artboard escribe **24**— y el comparador queda versionado en
**`scripts/comparar-seccion.mjs`**, con sus dos trampas dentro (el banner de cookies se cuela en la
captura; **un `clamp()` da `18.0001px` y comparar cadenas acusa al producto sano**).
>
> ▶ **LO SIGUIENTE: la T2g.** Por orden del canvas tocaría **06 «Reseñas»** (`Resenas PJP` 2a), pero
**está BLOQUEADA por el owner**: `specs/google-reviews.md` sigue esperando su ✅ y **tres datos**
(`place_id`, clave de API y techo de gasto). ▶ Así que la siguiente construible es **07 «Visítanos»**
(`Visitanos PJP` 7b + `Escritorio PJP` 3b), cuyo dato ya está en la BD — y ojo, arrastra dos
pendientes suyos: la **dirección exacta** y los **festivos de Lorca**. ⚠️ **Relee su artboard antes Y
durante**, y **no te creas el acta sin abrirlo**: van cuatro veces.
>
> 📜 **LO QUE DECÍA ESTE SITIO ANTES DE LA T2f** (la T2e, `#483`/`#484`).
>
> ▶ **LA T2e ESTÁ EN EL ÁRBOL: la sección 04 «Cumpleaños»** (`#483` la construyó, **`#484` la
corrigió con el ojo del owner**). Hoy la sección es: **cabecera común `.sec-head`** —rótulo, titular
«El cumple, resuelto» y entradilla que vende con la cifra del catálogo—, el **reloj de las dos
horas**, **dos tarjetas de pack** que se comparan al lado y **navegan a `/cumpleanos`**, la nota de
tarifa especial, la línea de edades mezcladas, y el **bloque de complementos con el molde de las
entradas**. Suite **4.555** · desborde 0 en las 13 vistas. Medido: la sección baja de **2.489 a
1.620 px** en móvil y la portada de **13,43 a 12,40 pantallas** (10,72 en escritorio).
>
> ❗❗❗ **LO QUE `#484` DEJA Y VALE PARA TODO EL CARRIL, no solo para esta sección.** El owner
señaló dos cosas mirando la sección y **las dos eran defectos con causa**:
> **(1)** «un recuadro sin border radius detrás de la card de Jump» era **`[data-surface]` PINTANDO
el contenedor** —esa regla no solo declara la superficie, hace `background: var(--bg)`—, así que con
el atributo en el `<li>` ése se volvía un rectángulo de tinta pura con **radio 0** y la misma caja
que la tarjeta. *Declarar una superficie no es solo cambiar tokens: es pintar.* ▶ **El atributo va en
la TARJETA, nunca en su contenedor.**
> **(2)** «quitamos la imagen»: el problema **no era cómo estaba puesta** —estaba a sangre y en
21:9—, sino que `zones.image` para cumpleaños es **el comedor vacío**, sin tarta y sin niños. El
propio artboard lo tenía pendiente del dueño («foto del cumple montado»). `[DECIDIDO owner]` sobre
**tres opciones renderizadas**: se retira, con todo su CSS. ⚠️ **No queda mecanismo dormido**: volver
a la cabecera sobre foto será una decisión, no un efecto lateral de subir una imagen al panel.
>
> ⚠️⚠️ **Y dos defectos más que solo vio el NAVEGADOR, con la suite en verde**: la pegatina de tinta
**no se leía** —keyline, relleno y sombra casi el mismo color, contraste **1,19**, así que lo único
visible era el escalón de la esquina; hoy el keyline sigue a SU superficie, y es divergencia
declarada con el artboard, **que tiene el mismo problema**— y **el hover de la pegatina estaba mal
desde `#478` en TODA la portada**: el paquete no declaraba `--shadow-float-hover` / `-press`, así que
la tarjeta reposaba con la sombra dura del cliente y **saltaba a la difusa del producto**. *Un
comentario que describe un mecanismo no lo implementa.* Arreglado **también para la sección 01**.
>
> ❗❗❗ **Y LA LECCIÓN QUE SE REPITE EN LAS CUATRO TANDAS: el acta del canvas describe piezas que su
propio artboard tiene APAGADAS** —la chapa de zona (`#480`), la del «18 más» (`#482`), el reloj y el
aviso INFO (`#483`)—. *Esa fuente se relee antes de cada tanda, **mientras dura**, y sin creerse el
acta.* ⚠️ En esta tanda el artboard **se movió a mitad**: apareció un turno 8 («Tu fiesta, tu
manera») como propuesta abierta, que el owner aceptó **con el molde de las entradas**.
>
> ▶ **Las cuatro decisiones del owner en la T2e**: el **paso a paso SALE** de la portada (615 px,
sigue entero en `/cumpleanos`) · las tarjetas **NAVEGAN**, no venden —*«en la landing va la promesa,
no la lista»*— · el turno 8 **entra con el molde de las entradas** (de ahí sale
`<x-site.addons-rail>`, compartido por 02 y 04; la extracción se verificó **byte a byte**) · y el
**reloj entra en las DOS superficies**, contra el artboard. ❗❗ **La edad que se publica es la del
PACK** (4–7 y 8+), no la de su zona: es el campo que **cobra el suplemento mixto**. ❗❗ **El reloj NO
reparte**: un diagrama de tramos promete horario aunque la letra diga lo contrario.
>
> ⚠️ **Dos trampas de instrumento pagadas aquí**: `RateRailSectionTest::panel()` recortaba «hasta el
siguiente panel» y para el ÚLTIMO se llevaba el resto del documento —contó 6 fichas donde pinta 2, la
lección de `#314` por otra puerta—; y una medición mía dio los packs a **0,00 €** por leer `amount`
en vez de `amount_cents`. *La cacé comprobándola por otra vía, no releyéndola.*
>
> ▶ **PASO DE DESPLIEGUE, y es un ARREGLO, no una mejora**: `--shadow-float-hover: 2px 2px 0
var(--paper-fg)` y `--shadow-float-press: 0 0 0 var(--paper-fg)` en el `client.css` de producción.
**Sin ellos las secciones 01 y 04 conservan el defecto de la sombra.** La lista viva de despliegue
está en la §5.bis de la spec.
>
> ▶ **LO SIGUIENTE: la T2f, sección 05 «Antes de venir»** (`Antes de Venir PJP` 2a + `Escritorio PJP`
3a). ⚠️ **Relee su artboard antes Y durante**, y **no te creas el acta sin abrirlo**.

>
> ▶ **LA T2d ESTÁ EN EL ÁRBOL, y son DOS decisiones: `#481` (la página `/atracciones`) y `#482` (el mosaico de la portada).** La sección 03 pasa de **23 atracciones en carrusel** a un **mosaico de cinco** —tres con nombre, dos veladas— con **una sola puerta**, y las 23 se mudan a **`/atracciones`**, que se adelanta de la Fase 3 porque sin destino la puerta sería el ancla muerta que la regla del canvas prohíbe. Suite **4.545** · **0 risky** · sonda de navegador en las 13 vistas con desborde **0**. ❗❗❗ **LO QUE NO PUEDES NO SABER**: **el acta del canvas daba por cerradas DOS cosas que su propio artboard ya no dice** — la **chapa del «18 más» está APAGADA** desde el recorte del 7 sep (igual que la chapa de zona de `#480`) y el **titular es «Salta, trepa y déjate caer»**, no «El parque», porque `doc/voz.md` declara que **los artboards de sección son el registro de sus turnos** y el entregable es `Portada PJP`. *Cuando dos fuentes del canvas se contradicen, la pregunta no es cuál gusta más: es cuál es fuente para qué.* ❗❗ **EL REPARTO DE LAS CINCO no es «las primeras»**: una de la primera zona y **dos de la segunda**, porque —lo escribe el artboard— *«la madre de un niño de 4 años veía un solo juego de su zona con nombre»*; con el orden global, aquí **las cinco saldrían de Jump**. Lo manda `zones.position` + `attractions.position`, **sin campo nuevo**, y reproduce el mosaico del mockup con los datos de hoy. ❗❗ **EL VELO SON DOS CELDAS O NINGUNA**: es una BANDA que dice «la sección se acaba», y con una sola dice «estas fotos están borrosas» — por eso con **cinco atracciones o menos** desaparece. ❗❗❗ **`#481`: EL COLOR DE UNA ZONA APRENDE A SER TEXTO, y lo obligó una GUARDA con la suite en verde.** La cifra grande de `/atracciones` se pinta con el color de la zona, que llega del panel, y sobre papel el lima da **1,85** y el cian **2,45**. La primera versión mezclaba con un **porcentaje fijo** y pasaba con los colores de ESTA instalación… pero el `#C6FF3A` que el **producto** trae por defecto para Kids se quedaba en **3,14**. ▶ Nace `ThemeSettings::zoneInk()` (`--zone-ink`), que **oscurece por pasos** con el mismo **0,88** de `actionHover()` (`#209`): cuatro pasos sobre el Lima Bote dan **`#627411`, exactamente el Lima 800 que el artboard escribe a mano**. ⚠️⚠️ **La zona de llegada va en `?zona=` y NO en el hash** —un hash no llega al servidor—, y el precedente roto está al lado y medido: `#478` enlazó a `/precios#zona-<slug>` y esa página emite **cero** `id="zona-…"` (ficha en `DEUDA.md`). ⚠️⚠️ **LA RETIRADA ES LA MITAD DE LA TANDA Y CUESTA TRES COSAS, las tres del OWNER y fichadas**: el panel puede **vincular un complemento** a una atracción y ya no lo publica nadie (medido **0 de 23**: la cifra de `#302`, «1 de 23, la Tirolina», caducó) · los **dibujos `zone-<slug>` del kit** se quedan sin pantalla · y el sitio **ya no sabe abrir el cajón posicionado en una zona** —⚠️ y al medirlo salió su gemelo: el botón de tarifa dice «Comprar 1 hora en Jump» y abre **sin intención**, lo que viene de `#479`—. ⚠️ **Lo que SÍ se resolvió dentro**: `attractions.badge` se quedaba sin pantalla y ocupa el **segundo chip** que el artboard de `/atracciones` dejó vacío por falta de dato. ❗❗ **TREINTA GUARDAS EN ROJO Y LAS TREINTA CON RAZÓN** — re-apuntadas (`ZoneIdentityIsUniqueTest`, **segunda vez** que pierde el sujeto), convertida en CENSO (`SidebarSeamTest`), sustituida por red propia (`RideMosaicSectionTest`, 7 casos) o retiradas con su nota. ⚠️⚠️ Y una salió **«risky»**: *un caso que solo asevera dentro de un bucle deja de vigilar en cuanto el bucle se vacía, y lo hace en verde.* ⚠️⚠️ **Y dos trampas del guion de PODA de CSS, las dos con la hoja cuadrando de llaves**: esta hoja tiene **llaves y nombres de clase DENTRO de comentarios** —sin enmascararlos el analizador abre reglas donde no las hay y salva reglas que debían irse; con la máscara aparecieron **seis más**— y la primera versión iba a **borrar el foco de teclado de casillas y radios**, porque `.ride-card:focus-visible` era uno de los seis selectores de esa regla. ▶ **PASO DE DESPLIEGUE de esta tanda: ninguno de código.** Es **DATO**: el ORDEN de las atracciones en el panel decide **cuáles cinco** salen en la portada. ▶ **LO SIGUIENTE: la T2e, sección 04 «Cumpleaños»** (`Cumpleanos PJP` 7b + `Escritorio PJP` 5a). ⚠️ **Relee su artboard antes de empezar Y mientras dura**: en la sesión anterior el de tarifas se movió seis veces.
>
> ▶ **LA T2c ESTÁ EN EL ÁRBOL, y son DOS decisiones: `#479` y `#480`.** Carril con foco en móvil, rejilla de tres pistas con foco en escritorio, **la cabecera de sección unificada en las CINCO** que compartían clase, **chapa de zona** por tarjeta, **el ahorro con marcador amarillo** y **los complementos fuera de la tarjeta**, en su propio carril. Suite **4.540** · **26/26 mutaciones** · medido en escritorio **352×397** contra los **351×398** del canvas. ❗❗❗ **LO QUE NO PUEDES NO SABER**: **el artboard cambió SEIS veces a mitad de la tanda** (6a → 10a → 11 a 16, más el 5a de `Cumpleanos Pagina PJP`) — *esta fuente no se relee antes de cada tanda: se relee antes **y mientras dura***. ❗❗ **El AHORRO se deriva de `duration_min` y sin duración NO se escribe** (`[DECIDIDO owner]`, contra el supuesto del artboard, que compara por índice): medido, «Todo el día» contra tres ahorraría 6,00 € y **contra dos sale a −2,00 €**. ❗❗ **Nace `--marker`** porque los dos candidatos eran peores (`--warn` lleva un hex del PRIMER cliente; `--strip-3` cae en color de zona), transparente por defecto. ❗❗ **El KEYLINE del botón es una VARIANTE de la familia** (`.btn--keyline`) y **no va a todos**: el sistema declara «Completo» sin borde como defecto y acota la pegatina a hero y cierre — lo cazó el owner cuando estaba escrito a mano. ⚠️ Los complementos: dedup **por ID** y bloque **dentro del panel de cada zona**; el carril lleva **foco de teclado** o la segunda ficha no se alcanza sin ratón; el «+» es honesto aquí y la unidad sale del PIVOTE («cada uno» para `fixed`, nunca «por persona»). ⚠️ **Los iconos de complemento NO necesitaban código** —el mecanismo existe desde `#475`—: era DATO, y los catorce estaban en `NULL`. ▶ Y lo anterior, de `#479`: **el artboard ya había cambiado A MITAD** —se empezó con `Precios PJP` 6a y el owner aprobó **10a** mientras se construía—, así que *esta fuente no solo se relee antes de cada tanda: conviene volver a mirarla antes de dar una sección por cerrada*. Con 10a el **nombre del producto manda** (pasa de etiqueta mono a rótulo), la cifra baja de 44 a 38 y la tarjeta sube de 262 a **352**. ⚠️ *Y ojo con lo que se lee de `#479` sobre la zona: allí iba en el BOTÓN, y `#480` la sacó de ahí y la puso en la CHAPA — la afirmación de aquella decisión está CADUCADA dentro de la misma sesión.* ⚠️⚠️ **Su regla del nombre NO se pudo copiar tal cual**: el mockup parte `{nombre} · {matiz}` y este catálogo escribe `{ZONA} · {nombre}`, así que su `split` daba nombre «Jump» y matiz «1 hora» — se retira el prefijo **cuando es exactamente el nombre de la zona** (una comprobación, no una adivinanza) y solo después se parte. ⚠️⚠️ **Y su ancho de 352 NO cierra con su propio asoma**: 16 + 352 + 12 + los 10 px que desplaza la escala del 94 % = **390,6**, o sea que a la anchura de referencia del sistema se veía UNA tarjeta y nada detrás — y eso rompe una regla suya, porque en 02 la entradilla dejó de decir «arrastra si quieres más» *precisamente porque la señal la da la tarjeta que asoma*. Queda en `min(352px, calc(100vw - 58px))`, medido en seis anchos. ❗❗❗ **EL CHIP ES EL MARCADOR DE LA QUE LIDERA, y esto se corrigió DENTRO de la tanda** tras verlo renderizado: se había atado a `badge`, que es un campo independiente de `featured`, y con los datos reales «Kids · Ilimitada» llevaba el chip **sin ser destacada** mientras ninguna lo era — un marcador de líder en una zona sin líder. Hoy el chip, el ancho y el foco salen del **mismo sitio** y el texto lo sigue escribiendo el panel; el `badge` de una tarjeta que no lidera **baja al MATIZ** en vez de perderse, y dos destacadas resuelven a una por índice. ⚠️⚠️ **Y ahí salió un caso que MENTÍA sin fallar**: leía los modelos ANTES del reset masivo, así que reponer `featured => true` no ensuciaba nada y Eloquent **no emitía la escritura** — el escenario decía marcar dos y marcaba una. *Lo cazó el arnés, no una relectura.* ⚠️ **La CHAPA de zona se construyó y se RETIRÓ** (`[owner]`: «ese card negro lo quitamos»): el propio artboard la tiene detrás de un interruptor apagado desde el recorte del 7 sep. ⚠️ **La tarifa especial deja de publicarse como RECARGO en TODA la web** (regla dura del canvas), lo que alcanza también a la banda de cumpleaños y a `/precios`; los días se escriben **una vez por sección**. ❗❗ **DOS DEFECTOS DE REPO destapados de paso**: `.rides__title` pedía peso 800 y `font-stretch: 75%` sobre **Bungee, que trae una sola cara** —el navegador falsificaba las dos en el titular de cuatro secciones; son **77 reglas** en todo el repo, ficha en `DEUDA.md`— y **`SectionHeadlineTest` se había quedado sin sujeto** (vigila `class="eyebrow"` exacta y `#478` reintrodujo el rótulo como `zones__eyebrow`). ❗ **Y queda un agujero de navegación**: el CTA retirado era el **único** enlace a `#rules` de toda la web, así que hoy **no se llega a Normas navegando** — ficha con dos salidas, y un caso invertido que se pondrá rojo cuando alguien la reenlace. ▶ **HAY TRES PASOS DE DESPLIEGUE de esta sesión** (la lista viva, en la §5.bis de la spec): **(1)** `--money: #627411` en `:root` y en `[data-surface="paper"]`, `#A3C21C` en `[data-surface="ink"]` — sin él los precios salen en tinta, que es la conducta anterior; **(2)** `--marker: #F5C400` y `--on-marker: #101418` — sin ellos el ahorro se queda en negrita sin resalte; **(3)** **DATO, desde el panel**: elegir el ICONO de cada complemento (`socks`, `clock-plus`, `cake`, `snacks`, `ice-bucket`) —los catorce estaban en `NULL`, o sea todos con la entrada genérica—, marcar `featured` en la entrada que lidere cada zona (hoy ninguna, así que no hay tarjeta ancha ni chip) y revisar el ORDEN de las zonas (el canvas ordena Kids · Jump y aquí sale Jump primero). ⚠️ Los dos primeros son del `client.css`, que está **gitignorado**: no viajan en ningún commit. El owner trajo su sistema de Claude Design terminado y `#452` mandaba, al volver, **leerlo entero y contrastarlo con el código antes de proponer nada**. Hecho. ▶ **TODO lo del carril vive en `docs/specs/rediseno-desde-canvas.md`** —plan, inventario, conflictos medidos y estado por tanda—; **empieza por ahí y por su §2**, que es el filtro que atraviesa las cinco fases: el canvas es de PlayJump y **este repo es el PRODUCTO**, así que cada pieza pasa por «¿mecanismo o cliente?» antes de copiarse. Banda del carril: **470–499**.
>
> ▶ **LA T2b, la SECCIÓN 01 «Para quién»** (`#478`; el ARMAZÓN es `#477`, el bloque de abajo). ⬜ *Cuando se escribió esto quedaban siete secciones; con la T2c en el árbol quedan **SEIS*** (spec §5.1). ❗❗❗ **LO QUE NO PUEDES NO SABER DE LA T2b**: `[DECIDIDO owner]` se **separa** 01 «Para quién» de 03 «Qué hay dentro» —la tarjeta de zona *navega* a la tarifa y las pestañas *eligen* tarifa, así que **no vuelve el defecto de `#295`**—, la **ALTURA pasa a DOS columnas de `zones`** (nullable, y `null` es «esta zona no restringe»: sin dato no se pinta regla) y **manda la BD en las edades**. ⚠️⚠️ **Y el canvas está EQUIVOCADO sobre las edades, con su propia advertencia también mal**: dice Kids 2–6 / Jump 7+ y avisa de un catálogo «1–6 / 7–99»; **medido**, `zones` y `ticket_types` —el que **cobra** el mixto— dicen **los dos 4–7 y +8**. No se ha tocado un céntimo. ⚠️ **Las tarjetas alternan SUPERFICIE, no color de zona**: pintarlas con la paleta sería la **grieta 01** que el propio canvas nos reportó. ⚠️ **`ZoneCards` vive en Booking** porque `Content` solo ve `Booking\Contracts` — no se tocó el grafo, se movió la clase. ⚠️⚠️ **Tres defectos que la suite y el navegador cazaron y el código no delataba**: `$especial['rate']` sobre `null` **lanza** (31 rojos; invisible en local porque aquí sí hay tarifa de finde) · el sello salía **sin rótulo** porque `rate_types` tiene `label` y no `name` · y la altura salía **duplicada** al llevarla ya el texto libre. ▶ **Hay paso de despliegue de DATO** (spec §5.bis): poner la altura de cada zona y quitarla de su texto de edad.
>
> ▶ **EL PRÓXIMO PASO, NOMBRADO: la T2d es la sección 03 · «Qué hay dentro»** (titular «Salta, trepa y déjate caer», artboard **`Juegos PJP` 6a**). Su acta está en `doc/portada.md` del canvas: mosaico de cinco fotos —las dos últimas con velo a papel, sin nombre y sin enlace—, chapa de tinta con el «18 más» y las dos puertas DENTRO, hoja de ficha al pulsar, un número por frase y **cero naranja**. ❗ **Y trae DOS cosas del owner sin contestar**: el contenido del «18 más» y **el BAR**, que el canvas movió de la sección 07 a ésta y *«falta que el dueño lo apruebe»*. ⚠️ **Antes de empezar, RELEE su artboard y sus tokens**: en esta sesión el de tarifas se movió **seis veces mientras se construía**.
>
> ⚠️ **Y el método que esta sesión deja probado, en tres líneas**: (1) preguntar TODAS las decisiones del owner antes de escribir —salieron nueve—; (2) **medir en navegador antes de dar nada por bueno** (la sonda cazó el asoma que la aritmética del artboard no cierra, y la captura cazó una clave de idioma en crudo que no rompía nada); (3) **el arnés de mutación encuentra lo que una relectura no**: aquí destapó dos casos que pasaban en verde sin montar el escenario que decían montar.
>
> ❗❗❗ **LA LECCIÓN DE LA T2b, Y VALE PARA LAS SEIS SECCIONES QUE QUEDAN: «idéntico al mockup» NO SE COMPRUEBA MIRANDO.** La primera versión tenía la estructura y le faltaba el artboard entero —foto, sello girado, eje de altura, frontera y velo—, y el owner lo vio en un vistazo. ▶ **Usa `scripts/comparar-con-mockup.mjs` ANTES de dar una sección por buena**: renderiza el marcado del propio artboard y compara pieza por pieza. Dio **40 divergencias en móvil y 29 en escritorio** donde el ojo veía «parecido», y las bajó a **4 por superficie**. ⚠️⚠️ **Lo que encontró y no se ve leyendo el código**: la **opacidad del velo cambia por zona** (22 % el cian, 24 % el lima; hoy se deriva de la luminancia) · el nombre iba en peso **700** y Bungee tiene uno solo, así que el navegador lo **sintetizaba** · el **sangrado del eje estaba en el cuerpo** y arrastraba al velo y a la línea · el bloque teñido **crecía** y dejaba **68 px de color vacío** bajo el texto · y el **hueco de la vecina es ASIMÉTRICO** en el artboard, sin lo cual la chapa del 1,30 **tapa el rótulo del eje**. ❗ **Tres trampas del comparador van declaradas dentro de él** (el puntero en (0,0) deja una tarjeta en hover · el mismo rol vive en soportes distintos · el ancho del sello lo manda el dato). ⚠️ **Y `ShapeScaleTest` cazó que me llevé una LLAVE de más** al rehacer la media query: el CSS con balance −1, leyéndose a medias sin fallar — la trampa de `#293`.
>
> ▶ **La T2a, el ARMAZÓN** (`#477`). El contraste contra el marco aprobado del canvas dio **siete de diez coincidencias**, así que la tanda fue pequeña: el menú pasa a **dos grupos** («En esta página» · «Otras páginas», sustituyendo la lista plana de `#211`), el **eslogan a rotulador entra en el cierre**, y el **alto del par se confirma en 56** sin tocar código —cerrando un pendiente del propio canvas—. ❗❗❗ **El grupo del menú se DEDUCE de la URL y no es un campo**: con eso se disuelven **las dos cruces** que el canvas dejaba pendientes del owner. ⚠️⚠️ **El hallazgo lo vio el NAVEGADOR con la suite en verde**: el eslogan del cierre se pintaba **como un párrafo cualquiera girado 2°** —`.reserve p` le ganaba por especificidad e imponía 16,5 px y gris—. ⚠️ **TRES trampas de Blade en una tanda, todas ya escritas en el repo**, y la tercera fue **el propio comentario que avisaba de las otras dos** (`#307`, tercera vez): *en un comentario Blade las directivas se describen con palabras, nunca con su arroba*. ▶ **Lo siguiente eran las OCHO SECCIONES** (spec §5.1); van dos, quedan SEIS. ❗ **Queda una divergencia sin resolver y es del owner**: el canvas dice que *«el teléfono y el WhatsApp salen del cierre»* y hoy el cierre lleva un `tel:` en su segundo CTA.
>
> ▶ **DÓNDE ESTÁ EXACTAMENTE: LA FASE 1 ESTÁ CERRADA.** Sus **seis tandas con código, todas verdes**: táctil **44→48** en el producto · radios colapsados a **0·10·16·999** en el paquete · aire de sección **240/160 → 144/96** con token nuevo · columna **1176 → 1120** con hueco nuevo por instalación · la **escala tipográfica** (`#474`), los diez niveles con nombre · y el **set de iconos** (`#475`), que cierra en **63 de 65**. Dos más se cerraron **sin código** (`#473`): el movimiento se queda en **cuatro curvas** —`#262` sigue en pie— y el **punto de corte se APARCA hasta la Fase 2**. ▶ **LO SIGUIENTE ES LA FASE 2**: el armazón y las ocho secciones de la portada, móvil y escritorio.
>
> ❗❗❗ **LO ÚNICO QUE NO PUEDES NO SABER DE LA T1g** (`#474`, spec §5.3): **los diez niveles NACEN SIN CONSUMIDOR y está decidido**. Se buscó una sustitución de reflujo cero y **no existe ninguna** — ni una regla del producto coincide con su nivel en talla **y** en papel a la vez (las cuatro reglas de mono a 12 no son «Etiqueta»: son un glifo de 6 px, un precio y un número en círculo; los `--fs-15` son campos y botones). ⚠️⚠️ Eso choca con `SidebarTokenBudgetTest`, que tiene `#287` codificado, y **la salida NO fue debilitarla**: hay una lista nominada (`SIN_ESTRENAR`) con un **trinquete que solo la deja encoger** — el día que estrenes un nivel y no lo saques de ahí, se pone rojo. ▶ **Y la Fase 2 tiene ya medido lo que le toca mover**: el titular de sección va a `clamp(48px, 7vw, **108px**)` y su nivel es **Display L, 52** —otro diseño, no un ajuste—, el botón está a 14/600 contra 16/800, y **el cuerpo de texto no lo declara nadie**: hereda los 16 del navegador.
>
> ⚠️⚠️ **TRES COSAS QUE NO PUEDES NO SABER ANTES DE TOCAR NADA DE ESTO.** **(1)** El paquete del cliente **`public/css/client.css` está GITIGNORADO**, así que **los valores de PlayJump NO viajan en ningún commit**: son **paso de despliegue** y la lista viva está en la **§5.bis** de la spec. Si miras la web y no ves el cambio, mira si el paquete local lo tiene. **(2)** `Tests\Support\ReadsSiteStylesheets::siteSheets()` **EXCLUYE `client.css` a propósito** —lo dice su docblock—, así que **para aseverar algo del paquete NO se usa el trait**: se lee el fichero con `file_get_contents`. Dos guardas escritas hoy vigilaban una cadena vacía por creer lo contrario, y **lo cazó una mutación, no una relectura**. **(3)** ✅ **YA HAY SONDA DE NAVEGADOR Y VIAJA EN EL REPO** (`#476`): `scripts/sonda-geometria.mjs`, con su receta de instalación dentro (Chromium en el contenedor + el puente `8081→80` + `playwright-core` con `--no-save`). **Las cuatro cifras de la Fase 1 están confirmadas renderizadas** y el desborde horizontal es **0** en las 24 mediciones. ⚠️⚠️ **Y encontró lo que la suite no podía ver**: `.btn` **no declaraba mínimo táctil** —su alto era padding + línea y daba exactamente **44**, el objetivo viejo—, así que al subir el token a 48 **todos los botones de la web se quedaron cortos sin que nada fallara**. ❗ **La sonda salió mal TRES veces antes de valer, con cifras plausibles** (639 · 390 · un falso positivo del aire), y lo que la hizo creíble fue **su propio CONTROL**: corrida a 44 reproduce el «1 exento» que `#264` dejó escrito. *Si tu sonda no reproduce un hallazgo conocido, no midas con ella.* **(4)** **Los tokens del canvas van por v1.10, no v1.9** — cambiaron de versión en el mismo día: **se releen antes de cada tanda**, no una vez por carril.
>
> ❗❗❗ **LO QUE NO PUEDES NO SABER DE LA T1h** (`#475`, spec §5.3): el set cierra en **63 de 65** y **los dos que faltan NO se copian** — son de PlayJump (los cinco «Del parque» de `#257` más `calcetines`, seis en total contando lo ya fichado) y `[DECIDIDO owner]` salen por el **kit de instalación**, cada uno el día que tenga pantalla. ⚠️⚠️ **Eso CADUCA la ficha de `#257`** que decía que *«no existe mecanismo para sustituir el DIBUJO de un icono»*: existe desde `#286`. ⚠️ **Y sus 19 no eran los míos aunque el número coincidiera** (los suyos eran 5 + **14 de zona** en rejilla 64): *dos cifras iguales no son la misma cifra.* ▶ **Defecto preexistente cerrado de paso**: cuatro de las once opciones del selector de icono del catálogo enseñaban **la clave de traducción en crudo** al operador desde `#258`. ⚠️ **El extractor está ahora versionado** (`scripts/extraer-iconos.py`) porque el de `#257` no lo estaba, y **se validó con control** antes de usarlo. ⚠️⚠️ **Los ocho iconos de sección quedan SIN guarda de consumidor y no es descuido: no es medible** — un `grep` da 51 huérfanos de 74 y es FALSO, porque se sirven por `<x-dynamic-component>` con la clave.
>
> ⚠️ **El chunk del cajón sube a 279** (medido 278,58; **+2,10 KiB** por cinco dibujos, aislado con dos builds). **No se podó antes de subir y está dicho por qué**: lo que entra es geometría, o sea el consumidor mismo de la tanda.
>
> ⚠️⚠️ **TRAMPA NUEVA DEL ARNÉS, pagada aquí**: **restaurar el fuente no basta cuando se muta un componente de Vue.** `SidebarDomContractTest` renderiza el **bundle**, así que al terminar el arnés el árbol estaba limpio y **35 casos salieron rojos** contra un bundle construido con la última mutación dentro. Parece defecto del producto y es el instrumento. `scripts/mutar-set-iconos.sh` ya reconstruye los dos bundles al terminar; **si escribes un arnés que toque algo compilado, cópialo**.
>
> ▶ **El protocolo de este carril (`#469`) es preguntar TODAS las decisiones del owner antes de construir**, aunque sean pequeñas. En la T1g fueron cuatro —y la cuarta salió de que medir dejó **sin sujeto** a la respuesta de la tercera— y en la T1h, dos.
>
> ▶ **Y hay DOS cosas del owner ya identificadas y sin contestar** (spec §6, ocho en total): la **grieta 00** —el cuerpo del cajón a **13 px** contra el suelo de 16— y qué pasa con **`/entradas`**, que existe en el producto y **no está en el inventario de páginas del canvas**. ⚠️⚠️ **La grieta 00 ya está MEDIDA y es MÁS GRANDE FUERA del cajón que dentro** (`#474`): son **281 de 412** declaraciones por debajo de 15 px —**124 del cajón · 125 de la web pública · 32 del post-form y el justificante**—, y el canvas no podía verlo porque solo auditó el cajón. Su cifra sí queda confirmada al dígito: `--fs-13` tiene **59** usos.
>
> ⚠️ **La copia local del canvas es `mockup_playjumppark_v2/`.** La vieja, `mockup_playjumppark/`, **NO está caducada: ES EL ARCHIVO** —son los artboards que el canvas movió a `archivo/`, con paleta antigua que su propia cabecera prohíbe copiar—. Y **`Portada PJP.dc.html` se baja TRUNCADO** (256 KiB exactos): parece válido y se corta por la mitad, así que la portada se lee de sus artboards por sección. `DesignSync` quedó **autorizado en esta máquina**, que era deuda desde `#262`.
>
> ▶ **Lo de la jornada anterior sigue cerrado y en producción, y NADA de esto lo toca.** 🚀 **TODO LO DE LA HORA EXTRA ESTÁ EN PRODUCCIÓN, VERIFICADO, Y CONFIGURADO POR EL OWNER** (2026-09-08, commit `e76d6f2a`, sexto despliegue). Cerraron `#443` (la hora extra se cobra por invitado), `#444` (el cliente mueve sus invitados), **`#448` (el SELLO DEL MODO, cuatro tandas)** y **`#449`** (los por-invitado siguen a los invitados desde el post-form). ▶ **El owner YA puso los dos enganches en «Se cobra por invitado»**, y se verificó en producción que **las dos reservas vendidas del 21/09 no se movieron** —1 × 5,00 € y 1 × 4,00 €, sello `fixed`, 60 minutos— cuando antes ese mismo clic habría hecho **+35,00 €**, **+56,00 €** y **900 minutos de sala**. El owner vio la pastilla «Vendido con otra unidad» en la ficha real: era la única pieza sin red automática. ❗❗❗ **LO ÚNICO PENDIENTE ES DEL OWNER Y ES EL PRECIO** (medido el 08-09 tras configurar): las dos horas extra **solo tienen tarifa `normal`** (5,00 € JUMP · 4,00 € KIDS), así que **NO SE OFRECEN viernes, sábado, domingo ni fechas especiales** —justo cuando hay cumpleaños—; falta la tarifa `special` (viene del 06-09, `#443`, no del despliegue). ⚠️⚠️ **Y ese número era el precio de la HORA, no el de por invitado**: con «por invitado», una fiesta de 15 pagaría **15 × 5,00 € = 75,00 €**. Es decisión suya, pero **no está ajustada a la unidad nueva** — no se toca sin él. ▶ **NADA de esto bloquea código.** ⚠️ **Pero ojo: `main` YA NO ES producción.** Lo fue hasta el 08-09 (`e76d6f2a`); desde el 09-09 lleva encima las cuatro tandas del carril de diseño (`#469`→`#473`), que **no están desplegadas**. Producción sigue en `e76d6f2a`. El tamaño de la suite vigente está en la línea «Suite **N en verde**» de este documento, que es su única copia — **y la lee el `pre-push`**, así que una cifra desfasada bloquea el push.

> ✅ **EL SELLO DEL MODO — CERRADO DE PUNTA A PUNTA** (`#448`/`#449`; diseño y ejecución en `specs/hora-extra.md` §12, con §12.20 = la revisión adversarial). ▶ **La regla que deja, y vale para cualquier feature futura**: *la unidad con la que se contó una cantidad viaja en la LÍNEA, no en el catálogo* — es el hermano de `unit_price`, que ya estaba a salvo por vivir ahí. ⚠️ **Si añades un lector del modo**, `SoldLineUnitHasOneSourceTest` te obligará a declararlo en una de sus dos listas: OFERTA puede crecer (lee el catálogo de hoy), LÍNEA VENDIDA tiene que ser CERO (pasa por `AddonResolver::soldQuantityUnit()`). ▶ **Lo que queda abierto son SEIS fichas de `DEUDA.md`**, ninguna alcanzable con el catálogo de hoy; la más útil es el **N+1 de la pastilla de divergencia** (hoy cuesta 0 porque casi nada está sellado, y crece con cada venta nueva). ⚠️⚠️ **Y una que NO es de esta feature y muerde a todo el repo: 13 de los 22 arneses de mutación pueden dejar el árbol MUTADO si el proceso muere** (`trap … EXIT` no corre con SIGKILL). Pasó **tres veces hoy**, y las tres lo cazó **volver a correr la suite ENTERA antes de commitear**, no el arnés. `scripts/mutar-sello-modo.sh` ya está endurecido y sirve de MOLDE: copia en ruta fija, reparación al arrancar, `mutar()` que ABORTA si el fichero no tiene copia, e integridad al terminar.

> ✅ **QUÉ SE CONSTRUYÓ, EN UNA LÍNEA CADA UNO.** **`#443`**: la regla vive en UN sitio (`AddonOccupancy::blocksFor()`) y dice que los **BLOQUES** salen del enganche, no de la cantidad — por invitado, la cantidad son PERSONAS y el bloque es **1**. Sin migración y sin tocar el núcleo del dinero. Verificado en caliente tras desplegar: con el modo real (`fixed`) y 12 invitados calcula **720 min**; con `per_guest`, **60** — *ésa era la confusión, y lo que hoy la tapa en producción es el `max_qty = 1`, no el modelo*. **`#444`**: el orden del guardado es **testigo → cantidad → RE-LEER la reserva → fichas → extras**, con el MISMO `ZoneDaySlotLock` que la compra como primera sentencia y el dinero POST-COMMIT. ⚠️⚠️ **Es la PRIMERA puerta por la que el cliente mueve AFORO** —justo lo que `complementos-post-reserva.md` §4.3·5 evitó a propósito—; escenario `guest-count` de `purchase:verify-oversell` **visto FALLAR**: 96 invitados donde caben 30. ⚠️ **El SUELO son DOS motivos y no se funden** (el mínimo del pack se resuelve llamando al parque; «ya has asignado más plazas», quitando a alguien de la lista), y con eso se cierra la ficha de la hoja de sala que imprimía **plazas negativas**. ⚠️ `Booking` no puede mirar a `Identity`: lo ya asignado llega por `ReservationPlacesTaken`, con el binding en el composition root — **comprobado en producción que resuelve a `GuardianPlaces`**, que es lo único que no ve un `class_exists`.

> ▶ **Y UNA TERCERA, PEQUEÑA, QUE SALIÓ DEL PROPIO GATE: `#445`.** Un rojo de `QrLogoTest` llegó diciendo solo «null no es string», y buscando por qué llegó **mudo** apareció que `QrLogo` promete en su docblock un `Log::warning` por cada `null` y **dos de sus seis salidas no avisaban de nada** — el fichero temporal que no se puede escribir y el proceso que no se puede lanzar, *justo las dos que dispara la presión de procesos*. Un tercer aviso **afirmaba algo falso** («no hay rasterizador» con `rsvg-convert` instalado y el tope de 2,0 s agotado). Regla que queda: **una causa, una línea**. ⚠️⚠️ **Si mutas esa clase, la mutación obvia es DÉBIL**: sustituir un `warnOnce()` por una llamada inexistente sale VERDE porque la clase **se traga todo `Throwable` a propósito**; la que vale es borrar la línea (3/3).

> ✅ **EL INTERMITENTE LARGO ESTÁ CERRADO, Y LO CERRÓ EL INSTRUMENTO** (`#447`). `PackStayExtensionTest` llevaba **cuatro rojos en dos métodos** sin causa atribuida; en cuanto el aserto imprimió `$outcome->reason`, la cuarta aparición dijo **`stale_item_version`** y el diagnóstico fue directo: **era del ARNÉS**, no del producto — el helper `edit()` le pasaba al editor el testigo del **PEDIDO** cuando éste lo compara contra el de la **RESERVA**, y `updated_at` tiene precisión de SEGUNDO, así que coincidían solo con la máquina libre. Reproducido con control (`travel(2)->seconds()` antes del `save()`: 3 rojos) y verificado a 2 s y 90 s. ⚠️⚠️ **La lección**: *un intermitente sin instrumento se investiga; con instrumento, se lee* — y **no todo rojo bajo carga es un problema de carga**. ▶ **QUEDA UNO, y ése sí sigue sin causa**: `QrLogoTest`, el caso del rasterizador REAL: `rsvg-convert` tarda **34–125 ms** en reposo y **571 ms** en el peor de 40 muestras con la suite completa encima, contra un tope de **2,0 s** — o sea que el tope es **candidato, NO causa reproducida**. ▶ **Tiene ya el INSTRUMENTO puesto**: el aserto dice qué eslabón cedió y cuánto tardó, así que la próxima aparición se lee en una pasada en vez de reproducirse. Ficha en `DEUDA.md` con las cifras.
>
> **A.00 · LA JORNADA ANTERIOR (2026-09-07).** 🚀 **SE DESPLEGÓ Y QUEDÓ VERIFICADO EN PRODUCCIÓN** (`#440` + `#441`, commit `b442168`; copia de la BD previa en `~/backups/playjump2_main-20260907-182903.sql.gz`, 318 K, gzip íntegro): el mostrador ya pide el teléfono que falta y **declarar un menor y aceptar su exención son un solo gesto**. ▶ **LO QUE QUEDA ES DEL OWNER, y son TRES cosas**: **(1)** su **✅ por navegador** de las dos features en `playjump.es` —el paso 1 del pedido manual con un cliente sin teléfono, y el alta de un menor con su casilla—; **(2)** las **tres pasadas de la rejilla de media hora** que siguen pendientes desde el 06-09 (`PANEL-ADMIN.md` §4.1); **(3)** confirmar que las dos líneas del cron del panel siguen activas (`#115`). ▶ ✅ **CERRADO POR `#444` Y DESPLEGADO EL 08-09 (`#446`) — se conserva el DIAGNÓSTICO, no el estado:** el cliente NO podía añadir invitados desde el post-form. Reproducido: manda 12 fichas para una línea de 10 y **se guardan 10, las dos de más se descartan en silencio** (`sanitizeGuestData()` recorta a `quantity` y la vista pinta exactamente `quantity` fichas, sin botón de añadir). ⚠️ **Lo que se resolvió en `#413` fueron los COMPLEMENTOS, no los invitados.** ⚠️⚠️ **Y no hay ni una frase que le diga qué hacer**: el post-form tiene «Llámanos» para CINCO situaciones (las tres de edad sin producto, el extra fuera de plazo y el que no se pudo cambiar) y ninguna para ésta. ▶ El camino existe pero pasa por el operador (subir la cantidad desde «Gestionar producto», que re-tarifica por `PAY-18` y revalida aforo). **`[owner, 2026-09-07]`: se iteró el diseño y se construyó el mismo día**; ⚠️ ojo, la versión cara **es DINERO y AFORO** y choca con `#244` («cualquier gestión de dinero post-reserva ya cobrada se hace en las instalaciones»), así que es spec propia, no una pantalla.
>
> **A.0 · LA SESIÓN DEL 2026-09-07 — EL TELÉFONO EN EL MOSTRADOR Y LA EXENCIÓN AL DECLARAR UN MENOR**
>    ▶ **Dos encargos del owner, los dos cerrados en código y desplegados** (`#440`, `#441`; banda
>    `#440`–`#449`). Suite **4.392** *(en su día; hoy son 4.414 con `#443`)* · **11/11 y 19/19 mutaciones** · los TRES escenarios de
>    `waiver:verify-chain` sobre InnoDB, **con el nuevo visto FALLAR**.
>
>    ⛔ **`#440` · CONSEGUIR EL TELÉFONO DE GOOGLE SE DESCARTA, y §3 de su spec conserva la medición**
>    para que nadie lo relitigue: el `id_token` **no trae ningún claim de teléfono**, el ámbito que lo
>    daría (`user.phonenumbers.read`) es **SENSIBLE** —verificación con vídeo, y mientras tanto
>    pantalla de «app no verificada» y tope de 100 usuarios— y **el dato puede venir VACÍO**.
>    ⚠️⚠️ **Además el mecanismo que se había escrito NO era implementable**: hay UNA sola ida a Google
>    y login y registro **comparten botón** (`#350`), el ámbito es una **constante** que
>    `authorizationUrl()` no recibe, y alta-vs-entrada **solo se sabe tras el canje**.
>    ▶ **Lo que se construyó es el MOSTRADOR**: al elegir un cliente sin teléfono el paso 1 **retiene**
>    y lo pide, y lo escrito se guarda **en la cuenta**. ⚠️⚠️ **Las puertas son CUATRO y lo midió la
>    revisión**: `addLineToCart()` es público y **no mira el paso del cliente**, así que desde el paso 1
>    metía la línea en el carrito **y movía `step` él mismo**. ⚠️ **`create()` AVISA Y COBRA, NO
>    RECHAZA** (`[DECIDIDO owner]`, con caso propio): sería lo primero en la historia del panel capaz de
>    tumbar una venta de mostrador por un teléfono. ▶ **Medido en producción: 75 de 229 clientes sin
>    teléfono.** ⚠️ **No cierra el hueco de `EditUser`** —el panel sigue sin poder editar la ficha de un
>    cliente—: ficha en `DEUDA.md`.
>
>    ❗❗❗ **`#441` · LA EXENCIÓN SE ACEPTA AL DECLARAR AL MENOR — y la revisión adversarial evitó un
>    daño real.** La primera versión del diseño proponía RELAJAR la regla del correo verificado
>    (`#179`) y la revisión **reprodujo el escenario**: un tercero abre cuenta con el correo de otra
>    persona, declara **20 menores REALES** y los firma; cuando la víctima reclama su cuenta la defensa
>    de `#342`/`#347` funciona **y le entrega los 20 con sus firmas intactas**, y **no puede
>    deshacerlo** (con firma detrás `remove()` solo desvincula y `anonymize()` conserva, art. 17.3.e).
>    **CONTROL: hoy, sin firma, se borran de verdad.** ▶ `[DECIDIDO owner]` se hace con la **ACEPTACIÓN
>    RETENIDA**: si el correo está verificado se firma en el acto y si no **se retiene en la fila del
>    menor** y se sella al verificar. **`#179` NO se relaja: se REUTILIZA**, y hay caso de CONTROL que
>    lo fija.
>    ▶ **Medido en producción ANTES de desplegar**: modo **`interno`**, v1 publicada, **243 menores
>    activos, 94 SIN firma, de 60 titulares** — el encargo tenía sujeto real y grande. Esos 60 verán el
>    aviso nuevo del índice; **nadie más entrará ya sin firmar**.
>    ✅ **Los dos plazos YA ESTÁN PUESTOS en producción** (`waiver.retention_months = 60` y
>    `waiver.dependent_retention_months = 60`, `[DECIDIDO owner]`, art. 1964 CC). Eran **requisito de
>    salida**: sin ellos `prunable()` devuelve literalmente `where 1 = 0` y cada menor declarado
>    quedaba **sin fecha de caducidad**. ⚠️⚠️ **Y se comprobó que activarlos NO borra nada**: con los
>    plazos puestos, la poda borraría **0 firmas de 345 y 0 menores de 243** — activar un mecanismo que
>    llevaba dormido desde `#160` sin mirar qué se llevaría por delante habría sido pérdida de datos.
>    ⚠️ **`DependentRegistry` entró en el `CRITICAL_RE`**: tocarlo exige ya `VERIFY_CONC=1`.
>    ▶ **Y su lock sostenía un invariante que nadie había medido desde `#191`**: sin él, doce altas
>    simultáneas dejan **31 menores con un tope de 20** (visto fallar; la suite es ciega porque SQLite
>    no implementa locks).
>
>    ▶ **VERIFICADO EN PRODUCCIÓN tras desplegar** (solo lectura, sobre datos reales): el aviso
>    ENCIENDE con un titular al que le falta una firma y está APAGADO en tres titulares con todos sus
>    menores firmados (control); `CheckoutDuties::pendingFor()` responde `true` para un cliente sin
>    teléfono; `/` y `/up` en 200; y el chunk del cajón se sirve por HTTP **conteniendo `accept_waiver`**
>    —o sea, la casilla nueva llegó de verdad—.
>    ⚠️⚠️ **La primera sonda del control dio un FALSO POSITIVO y era del INSTRUMENTO**: cogía al titular
>    del primer menor FIRMADO, que resultó tener OTROS sin firmar, así que el `true` era correcto. *Si
>    tu instrumento acusa a lo que ya estaba bien, el defecto es del instrumento* — el control de verdad
>    exige un titular con TODOS sus menores firmados, y hay que buscarlo por SQL agregado.
>
> **A. LA SESIÓN DEL 2026-09-06 — REJILLA DE MEDIA HORA · HORA EXTRA DE PACK · DESPLIEGUE**
>    ▶ **Resumen en tres líneas, por si no lees el resto**: (1) la rejilla de media hora está lista y
>    **la aplica el owner en el panel de PRODUCCIÓN**; (2) la **hora extra de un pack** se construyó
>    entera (5 tandas, `#421`→`#427`) y está **desplegada y configurada** con sus precios; (3) el
>    **tercer despliegue** se hizo y se verificó (`#431`). **Nada quedó a medias.**
>
> **A.1 · LA REJILLA DE MEDIA HORA — 🟦 CÓDIGO Y DOC LISTOS; LA APLICA EL OWNER DESDE EL PANEL**
>    (`#420`, 2026-09-06, `[DECIDIDO owner]`; banda nueva `#420`–`#429` para este carril, que agotó la
>    `#400`–`#419`.) ▶ **El encargo, literal**: *«el objetivo son ambas cosas»* (dar opciones de horario
>    y no desperdiciar horario) y *«entradas y packs tienen que poder elegir 15:30 · 16:00 · 16:30»*.
>    ▶ **Por qué no existía 15:30**: las franjas se materializan una a una desde `slot_templates` y
>    **no hay ningún «paso» implícito**; las 231 plantillas estaban todas en punto. Es CONFIGURACIÓN.
>    ⚠️⚠️ **Y de paso apareció horario que se tiraba a diario**: el recinto abre a las **16:30** L–V y
>    cierra a las **21:30** los siete días, pero la rejilla empezaba a las 17:00 y moría a las 21:00.
>    ❗❗❗ **LA REGLA DURA: intervalo 30, duración 60.** `slot.end_time` es lo que lee
>    `OrderItem::isFinishedInPractice()` para dar una reserva por TERMINADA — con franjas de 30 min una
>    entrada de 1 h comprada a las 15:30 se declararía terminada a las 16:00, y de ahí cuelgan el
>    post-form, los extras, el suplemento mixto y «Mis reservas». La rejilla queda **solapada**, y el
>    aforo lo aguanta porque **cuenta PRESENCIA** (`AFORO-12`).
>    ▶ **Lo que le toca al owner** (se lo di paso a paso en la sesión): Ajustes → Horarios y aforo →
>    Plantillas de franja → «Generar plantillas», **tres pasadas, una por zona**: los 7 días,
>    `10:00`→**`21:30`**, duración **60**, cada **30**, el aforo que YA tiene cada zona (20 · 20 · 200),
>    «reemplazar» **APAGADO** y «regenerar» sólo en la **última**. ⚠️ El aforo de las franjas nuevas es
>    el MISMO de las viejas, **nunca la mitad**: cada franja declara cuánta gente cabe A LA VEZ.
>    ▶ Medido: entradas **10→20** horas el sábado y **4→9** el martes; packs **9→18** y **3→7**; franjas
>    3.557→**7.115**; `offerableTimes` de un sábado 20→**64 ms** (pack 40→94); `slots:generate-rolling`
>    ~**9 s**. ⚠️ **La capacidad de cumpleaños NO sube** (el techo es `max_guests_per_slot = 60`, medido
>    idéntico: 6 fiestas/120 niños en diario y 15/300 el sábado); lo que gana dinero es la ENTRADA —los
>    huecos de una venta parcial, verificado vendiendo 12 a las 15:00 y las 8 restantes a las 15:30—.
>    ⚠️⚠️ **Corolario que sorprende y es correcto**: las plazas de una franja son el **MÍNIMO del rato
>    que dura la visita**, no «su» aforo. Lo escribí al revés en la guarda y **el código tenía razón**.
>    ▶ Guarda nueva **`OverlappingSlotGridTest`** (11 casos) + `scripts/mutar-rejilla-solapada.sh`
>    (**8/8 muerden**); una mutación no mordía y señalaba los dos casos que faltaban (la DIRECCIÓN del
>    tiempo: en rejilla solapada, un derrame hacia atrás se confunde con el solape legítimo).
>    ⛔ **La HORA EXTRA no se puede colgar de un pack y no es prudencia** (`hora-extra.md` §7·D2,
>    verificado ejecutándolo en las dos direcciones): un complemento nunca cuenta en
>    `max_guests_per_slot`, así que con la fiesta entera serían veinte invitados invisibles.
>    ▶ ⬜ **Y EL OWNER LO REABRIÓ EL MISMO DÍA** (`#421`): *«tenemos que añadir la hora extra también
>    viable para producto tipo pack»*. **El diseño está escrito en `hora-extra.md` §10 y NO hay una
>    línea de código**; quedan **cinco decisiones suyas** (§10.6). ▶ El hueco está **REPRODUCIDO** —con
>    las tres guardas saltadas, una fiesta de 20 con hora extra deja el cupo de sala en `fiestas=0
>    ninos=0` y **acepta otra fiesta encima**— y **son DOS defectos**: el conocido (la hija es un
>    `addon` y `occupancyMaps` filtra `type = pack`) y uno que no estaba escrito (**la hija nace con
>    `seats = 1`**, así que diría una persona donde hay veinte). ▶ **El diseño: se vende como
>    complemento y se modela como DURACIÓN** —`extends_parent_stay` como interruptor HERMANO de
>    `occupies_after_parent` (no un modo: la unidad es distinta, personas vs bloques, y `prices` es una
>    tabla sola) + **`order_items.extra_minutes` materializado**, hermano de `seats`—. ⚠️⚠️ **Cuesta
>    CAPACIDAD y eso debe fijar su precio**: con todas las fiestas comprando 1 hora extra, el sábado
>    pasa de **15 fiestas a 9** y el martes de **6 a 3**, medido. ❗ **La decisión 1 manda sobre el
>    resto**: «una hora más» son TRES cosas y **solo «la fiesta sigue en su sala» encaja** con este
>    diseño; que los niños se queden SALTANDO ocupa otra zona, y eso no existe como mecanismo hoy.
>    ▶ ✅ **LAS DOS DECISIONES QUE BLOQUEABAN, CONTESTADAS** (`#422`, `[DECIDIDO owner, 2026-09-06]`):
>    **es «la fiesta sigue en su sala»** —así que el diseño de §10 vale tal cual— y
>    **`isFinishedInPractice()` se arregla**, pero en **tanda PROPIA (T2bis)**: ese predicado gobierna
>    TODAS las reservas, así que tocarlo **cambia la conducta de fiestas que ya existen** (una de 2 h
>    deja de darse por terminada una hora antes, y con ella el post-form, el cierre de extras y la
>    ventana de dinero del suplemento mixto) — mezclarlo con la extensión haría imposible saber cuál de
>    las dos movió un número. ▶ **Quedan tres decisiones y ninguna bloquea el código**: el precio, el
>    `max_qty` del enganche y si el mostrador lleva las mismas cotas (suelo propuesto: sí).
>    ▶ ❗❗❗ **REVISADO DE FORMA ADVERSARIAL ANTES DE CONSTRUIR** (`#423`, §10.8): nueve lentes, **9
>    hallazgos confirmados y 5 descartados**, y **el plan quedó reescrito**. ⚠️⚠️ **El peor era MÍO**:
>    la «T2bis» que el owner aprobó **habría empeorado producción** — `isFinishedInPractice()` tiene
>    DOS defectos en direcciones OPUESTAS (la franja adelanta ~1 h, el huso atrasa 1–2 h) que **hoy se
>    compensan**, así que arreglar solo la duración lleva el desfase de **18:00 a 19:00** cuando la
>    fiesta acaba a las 17:00. Se arreglan **los dos o ninguno**, y va **al final (T5), suelta**,
>    porque toca 9 ficheros y DINERO (`OrderBook`). ⚠️⚠️ **Y T1+T2 NO se pueden separar**: `AddonResolver`
>    es la autoridad del cobro, así que en cuanto deja de rechazar extensores hay venta, y hasta que
>    los mapas sepan contarla **cada venta es el hueco que la feature viene a cerrar**.
>    ▶ **Los cuatro mayores son el MISMO error de lectura**: el mecanismo está escrito sobre
>    `occupiesAfterParent()` y un extensor devuelve **`false`** ahí, así que atraviesa el filtro de la
>    oferta (A3), el modelo de vista (A4), la familia que aterriza bajo el lock del editor —**añadir
>    una hora extra a una fiesta vendida no revalidaría aforo**, A5— y el tope (A6, que para bloques de
>    tiempo no significa nada: con 20 invitados dejaría pedir 20 horas).
>    ▶ ✅ **T1+T2 HECHAS Y EN EL ÁRBOL** (`#424`, §10.9): **el camino de COMPRA, completo** — se vende
>    una hora extra de sala y los dos mapas de aforo la cuentan. Suite **4.347** · **14/14
>    mutaciones** · **los OCHO escenarios** de sobreventa sobre InnoDB. ⚠️⚠️ **El editor del panel
>    RECHAZA tocar un extensor** (`addon_stay_extension_unsupported`) hasta la T3: es el hallazgo A5
>    cerrado con una PUERTA en vez de con un olvido — sin ella, añadir una hora extra a una fiesta
>    vendida la alargaría sin mirar si la sala está libre después, y eso no falla: sobrevende.
>    ⚠️⚠️ **TRES guardas del repo cazaron lo que faltaba** (el código de API sin `ApiErrorCode`, el
>    cajón sin traducción y **el bundle SSR rancio** al tocar `pay.js`), y **una aserción mía pasaba
>    EN VACÍO** —buscaba `id` donde el DTO publica `productId`— y la delató su propio CONTROL.
>    ❗❗❗ **Y el octavo escenario NACIÓ INÚTIL**: repartir los workers entre las dos horas hacía que
>    el veredicto dependiera de quién ganara la carrera (verde **4 de 4** con el defecto puesto,
>    porque el comprador con extensión hace más trabajo y llega tarde al lock). Rediseñado a medir
>    una **AUSENCIA** —la primera hora sembrada ya alargada, los 12 pujan por la segunda y nadie debe
>    ganar—, con su guarda del instrumento haciendo de CONTROL.
>    ▶ ✅ **T3 HECHA TAMBIÉN** (`#425`, §10.10): el panel ya añade, sube y quita una hora extra sobre
>    una fiesta vendida, y la mueve de día u hora. **Una derivación ÚNICA** (`resultingStayMinutes()`)
>    da a la vez el cupo que se revalida y el hecho que se escribe, en la misma sentencia. ⚠️⚠️ **El
>    ORDEN es la propiedad**: el plan de fechas se adelanta a la validación del aforo — la lección de
>    `#417` aplicada a la extensión, o una fiesta no se podría mover a un día en el que su hora extra
>    ni se vende. ⚠️⚠️ **Un caso pasaba con el arreglo REVERTIDO**: la oferta cuenta la huella propia
>    a propósito (`#173`), así que una hora DENTRO del tramo actual sale excluida igual — la candidata
>    tiene que caer FUERA y chocar solo por la ventana alargada. Al rehacerlo salió lo que faltaba:
>    la lista de HORAS del modal pasa por **otra vía** (`displayAvailableFor`) y solo una estaba
>    arreglada. Suite **4.351** · **18/18 mutaciones** · los OCHO escenarios en verde.
>    ▶ ✅ **T4 y T5 HECHAS: LAS CINCO TANDAS ESTÁN EN EL ÁRBOL** (`#426`, §10.11). **T4**: la ventana
>    que se lee sale de la duración EFECTIVA —hoja de sala, resumen del día, puerta, correos y «Mis
>    reservas» por fuente única, y el calendario del panel aparte, que pintaba la del PRODUCTO—.
>    **T5**: `isFinishedInPractice()` compara **inicio + duración efectiva en hora del parque**, y
>    con ella **cierra la ficha ALTA de `DEUDA.md`** abierta desde `#413`. ⚠️⚠️ **Un test cementaba
>    la premisa vieja y se REESCRIBIÓ** (viajaba a «las 10:30 UTC» creyendo que la franja de las
>    10:00 era UTC: son las 12:30 del parque, hora y media después de terminar la visita).
>    Suite **4.353** · **22/22 mutaciones** · los OCHO escenarios + `postform:verify-concurrency` y
>    `mixed-party:verify-concurrency`, que cuelgan del predicado tocado.
>    ▶ **QUEDA SOLO LO QUE NO BLOQUEA EL CÓDIGO** (§10.6): el precio de la hora extra (con el coste de
>    oportunidad de §10.4 delante: si todas la compran, el sábado pasa de 15 fiestas a 9), el
>    `max_qty` del enganche y si el mostrador lleva las mismas cotas. **El mecanismo está completo**:
>    lo que falta es configurar el complemento en el panel y el OJO del owner.
>    ⚠️⚠️ **Y la puerta del PANEL estuvo a punto de quedarse fuera**: el interruptor existía en el
>    dominio y **no en el formulario del catálogo**, y el saneo del alta borra `duration_min` de
>    todo complemento salvo el que la necesita —conocía solo al ocupante—. *El defecto que `#410`
>    arregló para el hermano, esperando a repetirse.* Hoy el toggle está, excluyente y con su
>    candado con ventas.
>    ▶ ✅ **CONFIGURACIÓN ACORDADA Y MONTADA EN LOCAL** (`#427`): hora extra de sala **JUMP 5 €
>    diario / 8 € festivo** y **KIDS 3 € / 5 €**; la **Tarta** pasa al post-form (corte 48 h) y los
>    **Calcetines** se quedan en la reserva. ❗❗ **Son DOS complementos y es estructural**: el
>    precio vive en el PRODUCTO y el pivote no tiene columna de precio — fusionarlos iguala los
>    dos packs sin que falle nada. ⚠️ **Menú 1 y Menú 2 no pueden ir al post-form** (grupo
>    excluyente + incluido: se auto-inyectaría un cargo que nadie pidió). ▶ **Falta replicarlo en
>    PRODUCCIÓN por el panel**. ✅ `[owner]`: la hora extra de ENTRADA se queda en **KIDS y JUMP**,
>    solo en el producto de 2 h y solo con tarifa especial — no se retira nada.
>    ⚠️⚠️ **Y al revisar el catálogo salieron DOS defectos** (`#428`): **«Kids · Ilimitada» se
>    OFRECE los fines de semana y el checkout la RECHAZA** —no tiene precio en `special` y
>    `passesOffer()` no mira el precio, a diferencia de la oferta de complementos— (ficha ALTA en
>    `DEUDA.md`, con dos salidas y la primera es del owner); y el escenario `stay-extension` de
>    `#424` **dejaba 12 «Hora extra Probe» huérfanos** porque la limpieza que ya existía miraba
>    solo la clave `addon` y mi escenario usa `extender`. Arreglado y verificado: 0 huérfanos.
>    ▶ ✅ **EL PRIMERO, ARREGLADO** (`#429`, `[DECIDIDO owner]`: «la kids ilimitada no se vende fin
>    de semana»): **un producto sin precio para la tarifa del día deja de ofrecerse**, como ya
>    hacía la oferta de complementos desde `#410`. ⚠️⚠️ **Había un caso que afirmaba lo contrario**
>    —«un día se ofrece porque tiene franjas, no porque tenga precio»— y **su propio argumento lo
>    desmiente**: el día sin precio ERA seleccionable, así que el cliente avanzaba y el «no
>    disponible» le llegaba al pagar, que es lo que ese razonamiento quería evitar. Reescrito con
>    su premisa nueva. ⚠️ **El coste lo delató `ApiOverheadTest`** (40 consultas de presupuesto 22)
>    antes de llegar a ninguna pantalla: hoy las tarifas van en LOTE y **el catálogo normal ni
>    entra en la rama**, así que `offerableDates` vuelve a los **18 ms** de `#465`. 4/4 mutaciones.
>    ▶ 🚀 **EL TERCER DESPLIEGUE — el PLAN** (`#430`, `ENTORNOS.md` §6; **ejecutado**, ver abajo):
>    79 commits, **cuatro migraciones puramente ADITIVAS** (nada vendido cambia de conducta).
>    ❗❗❗ **El despliegue NO configura el catálogo, y eso se midió en la máquina**: en producción
>    **no existe ninguna hora extra** y los siete complementos de comida quedarían **todos en «al
>    reservar»** tras migrar. Hay un script idempotente preparado (fuera del repo) que lo deja
>    todo, probado dos veces en local. ⚠️ El `client.css` de producción **seguía sin las cinco
>    líneas** de `#434`/`#436` —medido **antes** de desplegar; ya están puestas, ver abajo—.
>    ▶ Orden previsto: copia de BD → `deploy.sh` → catálogo → CSS → verificación. La compra online
>    sigue CERRADA, que es la ventana para verificar sin prisa.
>    ▶ ✅ **DESPLEGADO Y VERIFICADO EL 2026-09-06** (`#431`, commit `612989a`): copia de BD primero
>    (255 KB, 50 tablas) · las 4 migraciones · **6.760 franjas regeneradas con 0 CERRADAS**
>    (ninguna reserva tocada) · 4 complementos creados y 14 enganches a post-form · las 5 líneas
>    del `client.css`. ⚠️⚠️ **El `client.css` se comparó ANTES de subirlo**: difería exactamente
>    en esas 5 líneas, sin cambios propios del owner. Verificado en la máquina: hora extra KIDS
>    3/5 € y JUMP 5/8 €, «Kids · Ilimitada» 0 horas el sábado, las cuatro URL a 200 y **0 errores**
>    en el log. ▶ **Queda del owner**: las tres pasadas de la rejilla de media hora en el panel de
>    PRODUCCIÓN (`PANEL-ADMIN.md` §4.1) — es configuración y no viaja en el despliegue.
>
> **0. EL PANEL DE ADMIN — 🟦 EN CURSO. El ASISTENTE de «Crear pedido» está COMPLETO en código
>    (T1→T4); lo siguiente son los tres críticos que quedan de la auditoría: C2, C3 y C4.**
>    ▶ **El encargo del owner** (2026-09-03/04, literal): *«que cualquier persona intuitivamente pueda
>    hacer la operativa del parque, hay que quitar ruido y poner cada cosa en el momento adecuado y
>    cada acción en su sitio»*, con el marco *«usamos Filament, no quiero chapuzas ni deuda, ni huecos;
>    sé profesional y riguroso»*. ⚠️ **El panel se usa normalmente en TABLET** (iPad horizontal,
>    1080×810): es el tamaño con el que se mide, no el escritorio.
>    ▶ **Lo hecho, en el árbol** — ocho commits, `origin/main` al día: **`#460`** la AUDITORÍA
>    (`docs/specs/auditoria-panel-admin.md`) · **`#461`** el SHELL · **`#462`** la T1 del asistente
>    (7 pasos, y el vacío se salta) · **`#463`** la T2 (producto en tarjetas agrupadas → cierra **C1**)
>    · **`#464`** la T3 (calendario grande + la cesta a la oferta) · **`#465`** el rendimiento de la
>    oferta (714 → 95 ms el paso; 421 → 45 el calendario del CLIENTE) · **`#466`** la **T4** (el
>    DESENLACE) · **`#467`** su pulido con el ojo del owner.
>    ▶ ✅ **T4 HECHA** (`specs/asistente-crear-pedido.md` §10): una pantalla propia al terminar, en vez
>    del *toast* + redirección a la ficha. ❗❗❗ **Su regla es no prometer nada que no haya pasado**:
>    **hay clientes SIN correo** y con ellos no se envía **nada** —ni confirmación, ni formulario de
>    invitados, ni justificante—, así que la pantalla lo dice y entrega los enlaces copiables. La
>    guarda **compara lo que dice con lo que se ha NOTIFICADO de verdad**, no con un texto.
>    ⚠️⚠️ **La redirección era también lo que impedía cobrar dos veces**: hoy lo impide que `create()`
>    vacíe el carrito, con caso propio. ⚠️ Del desenlace **no se navega** (la única salida es «Crear
>    otro pedido», que limpia todo **incluido el cliente**).
>    ▶ ✅ **Y PULIDA con el ojo del owner** (`#467`, §10.3.bis): el bloque de entrega eran DOS —«se le
>    ha enviado» y «enlaces para entregar a mano»— y el operador tenía que emparejarlos de cabeza;
>    ahora es **UNO**, «Lo que recibe el cliente», con una FILA por entregable (qué es · de qué
>    reserva · **estado en pastilla** · su enlace copiable debajo). ⚠️⚠️ **Los estados son TRES**
>    —«Enviado a …», «Entrégalo tú» y **«No enviado»**—: la confirmación no tiene enlace, así que
>    pedir que se «entregue» era mandar a hacer algo que no existe. **Lo cazó el OJO, no la sonda.**
>    ▶ **Controles bajo 44 px: 0** en el carrito y en el desenlace — y de paso **«Ver el desglose»
>    pasa de 86×16 a 86×44 en las CUATRO superficies del panel que pintan el libro** (parte del M7),
>    y la fila de copiar un enlace (input 38 · botón 36) también sube a 44 en todas.
>    ▶ ❗❗❗ **`#465` — SI TOCAS `SlotOffer`, LEE `INVARIANTES AFORO-02` ANTES.** Salió de una ficha de
>    deuda que la T3 dejó anotada: el paso costaba **714 ms** y **no era del panel** —la web y la API
>    del cajón llaman a lo mismo, así que cada cliente pagaba ~420 ms al abrir su calendario—. Hoy:
>    paso **94,6 ms**, read-model público **44,8**. ⚠️⚠️ «Fuente única» **ya no es «un solo
>    recorrido»**: las FECHAS leen filas crudas del horizonte y las HORAS cargan **solo el día que se
>    pregunta**. Lo que las mantiene siendo la misma fuente son la CONSULTA compartida, el PREDICADO
>    compartido y **`SlotOfferPathParityTest`** (un día se ofrece **si y solo si** sus horas no están
>    vacías). ⚠️⚠️ **`clampToHorizon()` NO se toca**: acotar por día retira el techo de venta, y sin
>    él un día a dos años vista se vendería sin que nada fallara.
>    ▶ ❗❗❗ **LO SIGUIENTE: los tres CRÍTICOS que siguen abiertos** (`auditoria-panel-admin.md` §4):
>    **C2** —en «Gestionar», «Cancelar producto» (el que ANULA la reserva) es el botón más ancho y más
>    saturado del pie y está a 12 px de «Guardar cambios», y en un modal «Cancelar» significa
>    universalmente *descartar*— · **C3** —la acción principal de una reserva es un icono gris de
>    40×40 sin rótulo, uno de hasta SEIS iguales, y otro de los seis **retira una credencial en el
>    acto**; en tablet no hay hover que los distinga— · **C4** —en la puerta, «Nueva búsqueda» se sale
>    de pantalla con un cliente con menores (907 px en 810), y ese botón es PRIVACIDAD—.
>    ▶ **EMPIEZA POR** `docs/specs/auditoria-panel-admin.md` §4 (los críticos con su medida) y §7/§9.bis
>    (lo que NO hay que tocar y lo que falla **a propósito**: D2 y D3 del owner). **D4 sigue pendiente
>    de su detalle.**
>    ⚠️⚠️ **AL FUSIONAR LOS DOS CARRILES SALIÓ UN ROJO QUE NO ERA DE NADIE, Y SE ARREGLÓ** (`#468`):
>    `UsedTokenIsDeclaredTest` (del carril de complementos, `#414`) consideraba declarado lo que
>    hubiera en `glob(public/css/*.css)` — y ahí vive **`client.css`, que está GITIGNORADO**. Con el
>    paquete del cliente puesto salía verde; sin él, **rojo por `--deco-tag` sin que nadie tocara
>    nada**. Es la trampa de `#302` («pasa en tu máquina y falla en un clon limpio»). Hoy los tokens
>    del PAQUETE están enumerados aparte (`DECLARED_BY_INSTALLATION`), con su disciplina propia
>    medida **contra las hojas versionadas** y no contra el glob. ⚠️ Su uso **sin fallback es
>    deliberado**: el hueco de ilustración no tiene suelo del producto (`hueco-ilustracion.md`).
>    ✅ **EL OJO DEL OWNER SOBRE EL ASISTENTE YA ESTÁ DADO** (2026-09-04, literal): *«el asistente lo
>    he visto, todo ok, pero la parte de que al cliente le ha llegado un formulario o lo que sea…
>    más profesional, mejor UI/UX»*. Ése fue el único reparo y es lo que se ejecutó en **`#467`**.
>    ▶ **Lo que queda de su mirada**: la SEGUNDA pasada sobre ese bloque ya rehecho —«Lo que recibe el
>    cliente»— y sobre el desenlace en su tablet. **No hace falta volver a validar los pasos 1→7.**
>
> **1. COMPLEMENTOS QUE SE VENDEN DESPUÉS DE RESERVAR — ✅ CÓDIGO COMPLETO: LAS CUATRO TANDAS
>    (T0→T3) EN EL ÁRBOL. Solo queda el OJO del owner.**
>    `docs/specs/complementos-post-reserva.md` (`#413`). `[owner]`: *«el cliente reserva un cumpleaños,
>    va a su form post reserva y puede añadir ahí complementos tipo cubo de refrescos, tapas…»*.
>    ▶ **EMPIEZA POR §9.4** (la T3 y sus dos defectos de navegador); §8 es la revisión y §1.3 y §4.5.1
>    el modelo de dinero. **El mecanismo de fondo ya existía** —el panel añade complementos a una
>    reserva pagada y el LIBRO lo pinta con saldo «A pagar en el parque»; el post-form ya movía dinero
>    con el suplemento mixto—: lo que esta tanda construyó es el eje **`product_addons.stage`** y la
>    puerta del cliente.
>    ▶ ❗❗❗ **LA REVISIÓN (6 lentes) ENCONTRÓ TRES AFIRMACIONES MÍAS FALSAS Y DOS BLOQUEANTES.**
>    Falsas: que el editor del panel ignora una bajada de complemento (**la BLOQUEA**, y el bloqueo
>    sostiene el modelo de dinero) · que «no hay inversión de locks posible» (**hay cuatro caminos que
>    la invierten y el interbloqueo se REPRODUJO**, `SQLSTATE[40001]`) · y que `general` tiene
>    semántica *nullable* en el `PUT` (**ausente BORRA**, medido con petición firmada real).
>    ⚠️⚠️ **El cambio de diseño que trajo (D9): la propiedad «quitar es neutro» es un hecho de la
>    LÍNEA (`birthValue() === 0`), no del eje** — el eje es configuración mutable, y pasar «Tarta» a
>    `postform` habría puesto en manos del cliente líneas ya cobradas, **con el libro cerrando en
>    verde**. ⚠️ Segundo bloqueante: **tres listas blancas del panel** entre el formulario y la fila,
>    y las tres callan al olvidarse (la peor **revierte la fase al tocar otro campo**).
>    ▶ ✅ **SPEC APROBADA: no queda NADA pendiente del owner** (2026-09-03). Las tres últimas, con los
>    casos delante: **el enlace se podrá ROTAR** (D14 — hoy no se puede revocar y con extras dentro
>    escribe dinero; entra `guest_form_link_version` en la firma y un botón como el del carné QR) ·
>    **las superficies de demanda usan los textos que ya existen** (D15, sin envío nuevo; el
>    recordatorio comercial queda fuera) · y **entran en la T0 los tres preexistentes de esa
>    superficie** (el `PUT` que borra `general`, la escalada 403→404 de la web y el sello que invalida
>    el token del operador); la IP de auditoría se queda como ficha.
>    ▶ **PLAN DE OBRA en §9: T0** la superficie saneada · **T1** el eje y su panel · **T2** el dominio
>    y la concurrencia (dos verificadores, uno de ellos CRUZADO que hoy no existe) · **T3** las
>    superficies del cliente. La T0 va primero porque la T3 construye encima de ella.
>    ▶ ✅ **T0 EJECUTADA (2026-09-03, commit `055d4781`, spec §9.1)**: suite **4.156 ✓ · 26.328**
>    (+19 casos) · **13/13 mutaciones** (`scripts/mutar-postform-t0.sh`) · verificado con `curl` fuera
>    de la suite. ⚠️⚠️ **El defecto era MÁS ancho que lo que la revisión encontró**: las lentes
>    midieron `general` y al construirlo se midió que **`guests` era peor** —un `PUT` parcial borraba
>    los nombres, las edades y las alergias de los OCHO niños de una reserva real, con un 200—.
>    ⚠️ El arnés cazó **una regla sin red** (12/13 a la primera: la defensa del cuerpo malformado
>    estaba escrita y sin caso). ⚠️ **La suite entera pasaba con los cuatro defectos puestos.**
>    ▶ ✅ **T1 EJECUTADA (2026-09-03, commit `2503adc7`, spec §9.2)**: el eje `product_addons.stage`
>    y su panel. Suite **4.178 ✓ · 26.382** (+23 casos) · **16/16 mutaciones**
>    (`scripts/mutar-postform-t1.sh`) · verificado por HTTP: al pasar «Tarta» a venta posterior el
>    embudo ofrece **3 en vez de 4**, y la landing y la ficha igual. Los DOS bloqueantes de la
>    revisión quedan cerrados. ⚠️ Tres trampas pagadas: **un método llamado como una columna hace
>    que Eloquent lo tome por una relación** (105 casos en rojo), **el `+` de arrays conserva el
>    operando izquierdo** (tres casos del proveedor ignorados en silencio) y **las baselines del
>    grafo de módulos solo encogen**. ⚠️ El arnés volvió a cazar una guarda que faltaba (15/16).
>    ▶ ✅ **T2 EJECUTADA (2026-09-03, commit `cb497127`, spec §9.3)**: `PostFormAddons` con las tres
>    escrituras asimétricas —**bajar SÍ escribe su hecho y retirar NO**—, la puerta por el HECHO de la
>    fila, el orden de locks de D11, el token optimista y la re-validación bajo lock. **13/13
>    mutaciones**. ▶ **Dos verificadores nuevos** (`postform:verify-concurrency`): `addons` (12
>    guardados → UNA línea) y **`cross`, el primero del repo que cruza DOS actores distintos** —los
>    demás forkean N copias del mismo—, con su control medido: **6 de 12 interbloqueos** con el orden
>    invertido y **0 de 12** con el de D11. ⚠️ Desviación dicha: es comando propio y no un escenario de
>    `mixed-party`, porque el cruzado no tiene nada que ver con la fiesta mixta.
>    ▶ ✅ **T3 EJECUTADA (2026-09-03, spec §9.4)**: las superficies del cliente. Contrato
>    (`PostFormAddon`, `can_add_extras`, `extras_invite`, `guest_form_stale`) → API → **la página con
>    su suelo sin JS** (`<input type=number>`; el stepper solo decora) → **el correo agrupado por
>    ventana** con voz propia y el bloque del libro → las **tres superficies de DEMANDA** de D15 (el
>    correo del post-form los nombra, el rótulo del cajón los nombra y el aviso de la cuenta **deja de
>    morir** al completar las fichas) → el **limitador por RESERVA** que `SEC-06` pedía. Suite
>    **4.228 ✓ · 26.648** · **951 casos de `node --test`** · **15/15 mutaciones**
>    (`scripts/mutar-postform-t3.sh`) · **sonda de navegador 13/13** con capturas.
>    ▶ ❗❗❗ **Y el navegador encontró lo que 4.200 casos no**: el testigo optimista **se invalidaba a
>    sí mismo** —`submitGuestForm()` mueve `updated_at` en la misma petición—, así que **un guardado
>    normal, nombres y extras a la vez, NO COMPRABA NADA** y decía «la reserva ha cambiado mientras
>    tenías esta página abierta». En la suite salía verde porque `updated_at` tiene **precisión de
>    segundo**. La regla: *nuestra propia escritura no es un tercero*. ⚠️ Y el presupuesto de consultas
>    destapó un **N+1 preexistente** en `OrderItemResource` (una consulta por tarjeta en «Mis
>    pedidos»), que salía rojo **también con el control**.
>    ▶ **LO SIGUIENTE, y cómo se retoma sin esta conversación:**
>    (a) **El OJO del owner.** El escenario está sembrado en local como pedido **`R-PFT3E2E`** (una
>    fiesta dentro de 3 h con DOS extras: «Cubo de refrescos» a 48 h —ya **fuera** de plazo— y «Tabla de
>    tapas» a 2 h —**dentro**—), del cliente `probe-card@jumpweb.test`. Se rehace con
>    `php artisan tinker --execute="require '/root/e2e/pf-t3-seed.php';"` (idempotente, borra y crea el
>    suyo) y se recorre con `/root/e2e/pf-t3.js`. ⚠️ **El enlace hay que firmarlo con el host del
>    navegador**: `URL::forceRootUrl('http://localhost:8081')` antes de `guestFormSignedUrl()`, y el
>    puente `8081→80` (`/root/e2e/bridge.js`) tiene que estar arriba. Capturas de la última pasada en
>    `/root/e2e/pf-t3-capturas`.
>    (b) **El PRODUCTO es DATO y en producción la feature está dormida por construcción**: se activa
>    desde el panel, catálogo → complemento → engancharlo al pack con **«se vende después de reservar»**,
>    su **plazo** y su **tope** (los dos obligatorios). Sin ese enganche no cambia una sola pantalla.
>    (c) **Una elección menor del owner**, anotada sin tocarla: un extra **cerrado que nunca se pidió**
>    hoy se pinta igual, con su «ya no se puede cambiar» y su 0.
>    ⚠️ **Si tocas el subsistema**: `scripts/mutar-postform-t{0,1,2,3}.sh` son sus cuatro arneses (13 ·
>    16 · 13 · 15) y **exigen el árbol commiteado**; `PostFormAddons` está en el `CRITICAL_RE`, así que
>    el push pedirá `VERIFY_CONC=1` con los **dos** escenarios de `postform:verify-concurrency`.
>    ▶ **Cierre de este carril: 2026-09-03 por la tarde**, con la T3 y su doc (los dos commits de
>    `#413` sobre el sello del carril de diseño, integrados por rebase sin conflictos). Árbol limpio,
>    nada aparcado en `wip/`, hook activo. **Evidencia**: suite **4228 · 26.648** (1 skipped) · JS
>    **951** · Pint ✓ · docs-check ✓ · `build` + `build:ssr` ✓ · **15/15 mutaciones** · los **dos**
>    verificadores de concurrencia sobre InnoDB con 12 procesos · **auditoría del reloj en verde en
>    las diez fronteras** (se corrió porque esta tanda añade fixtures con calendario) · sonda de
>    navegador 13/13.
>    ⚠️ **Seis defectos PREEXISTENTES destapados, con ficha en `DEUDA.md`** — entre ellos que la
>    escalada 403→410→404 **no se cumple en la web** (afecta también al justificante) y que el `PUT`
>    del post-form sin `general` **borra** las respuestas generales.
>
> **2. LA HORA EXTRA Y LOS COMPLEMENTOS — ✅ CÓDIGO COMPLETO Y DATOS EN LOCAL; queda el ✅ del owner
>    y el alta en PRODUCCIÓN** (`#410`/`#411`, `#415`, `#417`, `#418`). **EMPIEZA por
>    `docs/specs/hora-extra.md` §9**, que es lo último y lo que más cambia: el cambio de fecha.
>    ▶ ❗❗❗ **`#415` SI TOCAS EL PRECIO DE UN COMPLEMENTO**: se tarifica por el día de la **VISITA**,
>    no por `Carbon::today()`, en los **SIETE** puntos que lo hacen (la spec nombraba tres; el censo
>    dio siete, y dos son del post-form). Eso es lo que permite que la hora extra sea de FIN DE SEMANA
>    —precio solo en la tarifa `special`—, que es como el owner la dio de alta. ⚠️ **No movió un
>    céntimo**: cero de los doce complementos varían por día, verificado también en PRODUCCIÓN sobre
>    las 9 líneas hijas vivas reales. ⚠️⚠️ **Rompió un verificador y eso fue un hallazgo**: el fixture
>    de `postform:verify-concurrency` daba precio solo en `normal` y siembra a hoy+10, así que 3 de
>    cada 7 ejecuciones caían en finde y cantaba «✗ FALLA» con el producto sano.
>    ▶ ❗❗❗ **`#417` SI TOCAS EL EDITOR O UNA LÍNEA HIJA**: mover el día **retira** los complementos
>    que ese día no se venden (con su devolución EN EL PARQUE) y **re-tarifica** los que cambian de
>    precio, con **aviso al operador antes de confirmar**. Las dos escrituras son ASIMÉTRICAS y está
>    medido: retirar **no** lleva hecho, re-tarificar **sí** —sin él el pedido pasa a «en revisión» y
>    el cliente se queda sin desglose (`#132`)—. ⚠️⚠️ **La revisión adversarial encontró TRES cosas
>    mal en el plan, dos reproducidas** (§9.8): el ORDEN invertido bloqueaba el movimiento por el
>    aforo de una hija que se iba a retirar igualmente, y la regla **retiraba los PORTADORES de fiesta
>    mixta en cada cambio de fecha**. `AddonDateReconciler` entra en el `CRITICAL_RE`.
>    ▶ **Los DATOS, en local**: «Hora extra · KIDS» 5,00 € y «Hora extra · JUMP» 8,00 €, 60 min, con
>    precio **solo en `special`** — medido un jueves: se ofrecen viernes y sábado y **no** martes ni
>    miércoles. Y los seis complementos de restauración en venta posterior, en sus **12 enganches**
>    (cuelgan de los DOS packs, no de uno), 48 h de plazo y tope 5. Los guiones están en el
>    scratchpad de la sesión; son idempotentes y tienen modo en seco.
>    ▶ ⚠️ **La demo de `#411` YA NO EXISTE**: la BD local se rehízo (por eso las tres migraciones
>    estaban sin aplicar y la web daba 500 al arrancar). Si el owner quiere mirar la demo de la hora
>    extra, hay que **volver a sembrarla**.
>    ▶ **`#418` · lo que NO era mío, revisado antes de desplegarlo** (`[DECIDIDO owner]`): la T3 de
>    `#413` y `#414`. **Las dos pasan** —los cuatro gestos del cliente por HTTP real con el libro
>    cerrando, cuatro ataques a la superficie pública defendidos (999 → capado a 5), sin JS, y 6/6 en
>    su arnés—. ⚠️ Y destapó un hueco **mío**: el cruce entre las dos features (re-tarificar una línea
>    `postform`, con `birthValue = 0`) no lo cubría ninguna. Cierra, y ahora con caso propio.
>    ▶ ❗❗ **DEL CIERRE (`#419`): `audit-clock` cazó que `AddonDateReconcilerTest` era una BOMBA DE
>    RELOJ** — sus doce casos en rojo a fin de mes y a fin de año, verdes el 05/06/07 de septiembre,
>    **con el producto sano**: usa fechas absolutas y no anclaba el reloj, así que al pasar del 12 de
>    septiembre las reservas caían en el pasado. Arreglado anclando el reloj en `setUp()` a un lunes
>    anterior; verificado con `TEST_CLOCK` en cuatro instantes que antes lo tumbaban (12/12 en todos).
>    ⚠️ **Su fichero hermano `AddonPricingDateTest` NO caía** —usa las mismas fechas pero viaja en el
>    tiempo en todos sus casos—: *la diferencia no era el cuidado, era que uno tenía una razón para
>    viajar y el otro no.* **Tercera vez de esta familia** (`#412`, `#414`, `#419`): corre el reloj al
>    cerrar, no está en el pre-push.
>    ▶ ⚠️ Del cierre anterior: `audit-clock` cazó una MONEDA AL AIRE que no era del reloj
>    (`mt_rand` contra el `UNIQUE` de `orders.code`), arreglada con contadores deterministas (`#412`).
>    ▶ **EVIDENCIA DEL CIERRE, toda sobre el árbol YA FUSIONADO con el carril del panel**: suite
>    **4.312 ✓ · 26.994 aserciones** · JS **951/951** · Pint 1.183 ficheros · docs-check ·
>    **`audit-clock` completo: 12/12 fronteras verdes**, incluidas fin de mes y fin de año, que son
>    justo las dos que tumbaban a `#419` · y los **once verificadores de concurrencia** re-corridos
>    porque `#465` toca aforo (los SIETE de `purchase:verify-oversell` + Redsys + `postform` `addons`
>    y `cross` + `mixed-party` `charge` y `credit`), todos con **código de salida 0**.

> **2.bis ~~EL DESPLIEGUE ESTÁ DECIDIDO Y ESPERA~~ ✅ HECHO EL 2026-09-06** (`#431`, commit `612989a`;
>    lo que sigue es el análisis PREVIO, que se conserva porque su medición sigue valiendo). La
>    condición del owner —*«haré deploy cuando se termine el UI/UX del panel admin»*— se cumplió, y
>    el paquete final llevó **79 commits y CUATRO migraciones** (una más: `extends_parent_stay` +
>    `extra_minutes`). ▶ **La evidencia y los pasos que el script NO hace, en el punto A y en
>    `ENTORNOS.md` §6.**
>    ▶ **Qué iría**, medido **después de fusionar el carril del panel** (`#464`–`#466`): **61 commits**
>    desde el último despliegue (`64ff3b6`, 02-09) · **114 ficheros** fuera de `docs/`, `scripts/` y
>    `tests/` (55 de ellos en `app/`) · **3 migraciones nuevas, ninguna existente modificada**
>    (`occupies_after_parent`, `guest_form_link_version`, `stage`).
>    ▶ ⚠️ **Ese paquete lleva DOS carriles, no uno**, y el del panel entra con un cambio en el núcleo
>    del AFORO: `#465` reescribe `SlotOffer` en dos vías (la pesada, que hidrata, y una ligera para
>    las fechas) para bajar el calendario del cliente de ~420 a ~45 ms. **Revisado antes de empujarlo**
>    —las dos vías comparten consulta (`offeredSlotQuery`, con el *scope*, no un `WHERE` copiado) y
>    predicado (`passesOffer`); `start_time` no tiene *cast*, así que las dos leen el mismo valor;
>    `toBase()` sí aplica los *scopes*; y `clampToHorizon()` repone exactamente el techo de venta que
>    antes ponía el `whereBetween`— y **los SIETE escenarios de `purchase:verify-oversell` + Redsys +
>    los dos de post-form + los dos de fiesta mixta se volvieron a correr sobre el árbol YA FUSIONADO**,
>    todos en verde por código de salida. Su red propia es `SlotOfferPathParityTest`, que es la que
>    impide que las dos vías se separen.
>    ▶ **Las features nuevas llegan DORMIDAS**: `occupies_after_parent` nace `false` y `stage` nace
>    `booking`, así que las tres migraciones son aditivas con defaults neutros y **no reescriben una
>    sola fila**. Se activan con DATO, no con código.
>    ▶ ⚠️ **Lo único que un visitante NOTARÍA son las tandas C→F de la auditoría de diseño**
>    (`#434`→`#437`): **1.428 líneas** de `site.css` y `landing.css`. `[DECIDIDO owner]`: **capturas
>    de `playjump.es` ANTES y DESPUÉS** para compararlas — es la única forma de ver si algo se rompe
>    con el paquete del cliente puesto, que en local no existe.
>    ▶ ❗❗ **TRES pasos que `deploy.sh` NO hace**: (1) **copia de la BD** —corre `migrate --force` sin
>    backup; la vez anterior se hizo a mano con `mysqldump --single-transaction`—; (2) **las cinco
>    líneas del `client.css` de producción** (`--on-ok`, `--on-err` y las tres de `--interactive`),
>    que el rsync no lleva y sin las cuales las tandas C y D quedan a medias **con la suite en verde
>    aquí**; (3) el **alta de los productos**, si se quieren activar.
>    ▶ **Estado de producción, LEÍDO el 2026-09-04** (solo lectura, con `jumpweb-prod`): tarifas
>    idénticas a local · **CERO complementos con precio en una sola tarifa** (el paso crítico de
>    `#415` está limpio) · **30 fechas especiales ya cargadas, vísperas incluidas** (no hay que darlas
>    de alta) · los productos padre con los **mismos ids** (#101, #104) · las tres migraciones
>    pendientes · `sales.online_enabled = '0'`, o sea que **la compra online sigue cerrada**: aunque
>    se dé de alta la hora extra, solo se podrá vender por mostrador y post-form.

> **3. EL PRODUCTO DE EXCURSIONES EN PRODUCCIÓN, que lo corre el OWNER.** Guion idempotente con
>    dry-run en `~/excursiones-produccion.php`, **fuera del repo** (`#325`). Este agente **no tiene
>    acceso a producción** (medido: solo hay llave de staging; `playjump2@…` da `Permission denied`).
>    ✅ **Y el despliegue YA ESTÁ HECHO** (el carril de Google auth lo subió esta misma noche), así que
>    `ticket_types.guardian_authorization` ya existe en producción: **el guion aplicará `required` a la
>    primera**, sin segunda pasada.
>    ▶ ❗ **Con ese despliegue corrió también la migración `2026_09_02_120000`**, la del centinela que
>    `#406` arregló por la mañana. Si por lo que sea quedó a medias, **ahora se puede reintentar y se
>    repara** (con el código de antes habría quedado registrada como hecha y la tabla sin el `UNIQUE`
>    ni la FK, en silencio). Comprobación de LECTURA, diez segundos:
>    `Schema::getIndexes('guardian_authorizations')` tiene que traer
>    `guardian_authorizations_order_item_id_minor_key_unique`.
>
> **4. Lo que sigue esperando el OJO del owner**: el justificante (cinco escenarios `PRUEBA-J1`…`J5`
>    sembrados, guion en `VERIFICACION-E2E-CAJON.md` §5.septies — ⚠️ **ese escenario ENVEJECE**: se
>    sembró para «hoy» el 01/09) · el libro del pedido (V18–V23) · los TPV · su `client-menu.webp`.
>
> **5. EL CARRIL DE DISEÑO — ✅ REABIERTO EL 2026-09-09** (`#469`; lo que sigue es de cuando paró y
>    explica el estado del código de entonces). **El owner avisó con su base**, así que la condición
>    de `#452` se cumplió y el carril vivo es `docs/specs/rediseno-desde-canvas.md`.
>    Lo que decía `#452` (2026-09-03, `[DECIDIDO owner]`): el owner delega el
>    diseño a **Claude Design**, itera allí y avisará con la base; hasta entonces este carril **no
>    construye nada**. Al parar se revirtieron las tandas A y B del cajón (`#450`/`#451`, en un commit
>    nuevo: el cajón está como antes), se archivaron `design.md`, el guion de la portada y la auditoría
>    del cajón en `docs/archivo/` y se retiraron los prototipos `/_diseno/…`. **Lo que queda en código**:
>    las tandas C→F de la auditoría de la web pública (`docs/specs/auditoria-diseno.md`, `#434`→`#437`,
>    guardas vistas morder); su G, parada. ⚠️⚠️ **Paso de despliegue del owner: cinco líneas en el
>    `client.css` de producción** (la lista, en «DOS COSAS DEL OWNER»). Su bloque «CARRIL DISEÑO ·
>    PARADO» está bajo «POR DÓNDE RETOMAR», más abajo. ⚠️ **El reparto de bandas que decía esta línea
>    está CADUCADO**: desde `#469` el carril de diseño numera en **470–499** (la vieja `#450`–`#459` se
>    agotó), y el de producto sigue en la suya. **Léelo antes de elegir número o de tocar
>    `resources/views/components/site/**`.** ▶ **Cierre de este carril: 2026-09-03 por la tarde**, en
>    `34f8fa6` (el revert + el archivo, integrado sobre la T1 de complementos sin conflictos) y el commit
>    de cierre que sigue; árbol limpio, nada aparcado en `wip/`, hook activo. Lo único que hay que saber
>    para retomarlo cabe en una frase: **hasta que el owner traiga su base de Claude Design, aquí no se
>    diseña nada.**
>
> ⚠️⚠️ **NUMERACIÓN POR BANDAS** (`#404`, `CONVENCIONES §10.6`): **este ordenador numera desde `#400`**
> y el portátil sigue en `#34x`. Mirar el remoto antes de empujar **se probó y no basta** — trece
> colisiones, todas al cerrar. Los huecos entre bandas son deliberados y `docs-check` no valida
> continuidad.
> ▶ **La `#400`–`#419` (producto/reservas) se AGOTÓ en `#419`.** Su sucesora, **`#420`–`#429`**, se
> reservó aquí el 2026-09-06 antes de usarla — y **también se agotó, en `#429`**. Los dos últimos de la
> sesión (`#430` el plan de despliegue, `#431` su ejecución y `#432` el cierre) se tomaron del **hueco siguiente**, así
> que **`#432`–`#439` queda RESERVADA para este carril** y quien retome numera ahí. ⚠️ **Reserva la
> banda ANTES de usarla**, no después: es la regla de `#404` y esta sesión la estiró dos números.
> ▶ **Último usado: `#449`** (sub-banda `#440`–`#449`, la de este carril: **AGOTADA**). ▶ **SUCESORA RESERVADA AQUÍ ANTES DE GASTAR EL ÚLTIMO, que es la regla de `#404`: `#470`–`#489`** para el carril de producto/reservas — se salta la `#450`–`#469` porque esas dos están vivas (diseño y panel) y los huecos entre bandas son deliberados. Quien retome numera desde **`#470`**. Otras sub-bandas vivas: `#450`–`#459` (diseño, último `#452`) y
> `#460`–`#469` (panel, último `#468`).
>
> ⚠️ **El pre-push puede caer por un timeout del renderizador SSR** si la máquina está cargada. Está
> diagnosticado y mitigado (tope 60 → 300 s, medido: un render tarda ~140 ms), y el arreglo de fondo
> —reutilizar UN proceso en vez de ~38— está fichado en `DEUDA.md`. Si vuelve a caer, **mira el log
> que el hook preserva y dice por su nombre** antes de reintentar.
> ▶ ⚠️⚠️ **Y `audit-clock.sh` es donde más fácil salta, porque encadena ~12 pases de la suite**: el
> 2026-09-06 dio **✗ en «cruza medianoche UTC»** y **era el timeout, no el reloj** — esa pasada tardó
> **2 h 34 min** (contra 1:40 normal) y el `ProcessTimedOutException` cayó en
> `SidebarDomContractTest`. Comprobado: **el mismo test con el mismo reloj congelado pasa en 12,8 s**
> sin carga, y **la suite ENTERA en esa frontera da 4.358 verdes**. *Cuando el instrumento acusa a un
> test que no toca el calendario, sospecha del instrumento antes que del test* — y la prueba barata es
> re-correr ESE pase solo.


🟦 **EL PANEL DE ADMIN · LA AUDITORÍA Y SUS TRES PRIMERAS TANDAS** (2026-09-03/04, `#460`→`#463`;
commits `4faeb850` · `1cb2976c` · `65134311` · `6e7b0085`).

**Lo que hizo la sesión, en una línea cada cosa**
- **`#460` · La AUDITORÍA, y está EN EL REPO** (`docs/specs/auditoria-panel-admin.md`). 10 pantallas
  × 3 tamaños con Chromium sobre el panel real + 5 sondas de estados de trabajo: **4 críticos · 10
  mayores · 11 menores**. ⚠️ **No se publicó como artefacto**: la primera auditoría de diseño se
  publicó así y **se perdió con su URL** (`#430`).
- **`#461` · El SHELL**, los cinco puntos que dictó el owner: logotipo por tema con «Administración»
  debajo · buscador centrado que dice qué encuentra · el idioma fuera del topbar, al menú del avatar
  · «Crear pedido» a escala de acción (127×32 → **152×44**) · menú lateral **siempre plegado**, sin
  flecha, icono grande y rótulo debajo. **Cero vistas de Filament sobrescritas.**
- **`#462` · T1 del asistente**: de 3 pasos a **7**, con auto-avance en cliente/producto/hora y **el
  salto de todo paso que no pregunta nada** (medido: 16 de 18 productos no tienen ni un campo).
- **`#463` · T2 del asistente**: el producto en **tarjetas agrupadas**, que cierra el crítico **C1**.

**Lo que MÁS importa que sepas**
- ⚠️⚠️ **La auditoría no le sirvió al owner como plan de obra** —*«nada de eso, te lo digo yo»*— y
  eso **no la invalida**: sigue siendo el mapa medido de qué está mal y de **§7, lo que NO hay que
  tocar**. Lo que el owner rechazó fue MI orden de trabajo, no las mediciones. *Un informe se
  entrega para que decida quien decide.*
- ⚠️⚠️ **C1 no era un fallo de aviso, era el ELEGIDOR**: un desplegable plano de 18 opciones donde
  nada distinguía entrada de pack. Ya costó **61,00 €** y una sala sin reservar, **y no se puede
  deshacer** (editar producto solo ofrece del mismo tipo y zona). Lo cierra el **agrupado**, no la
  tarjeta. **El deshacer sigue abierto.**
- ⚠️⚠️ **Corregí una premisa del owner con datos**: web y panel **no divergen** en disponibilidad
  (los dos son `SlotOffer`; 177 días y 11 horas idénticos). La asimetría real es otra —**el panel no
  le pasa la cesta y la web sí**— y es lo que va en la T3.
- ⚠️ **Tres cosas las vio la SONDA DE NAVEGADOR y ningún test**: el indicador afirmando «sin nada que
  rellenar» antes de haber elegido producto, las ventajas del modal **en los tres idiomas a la vez**
  (por saltarme `tr()`), y el alto real de cada paso. *La suite mide conducta; el navegador mide lo
  que el operador ve.*
- ⚠️ **Repetí una nota CADUCADA de `CLAUDE.md`** («falta el hueco del logo sobre tinta»):
  `client-logo-ink.svg` existe desde `#216`. *Una nota de doc no es una medición.*
- ⚠️ Las sondas viven en **`/root/e2e/` DENTRO del contenedor**, fuera del repo a propósito
  (`deploy.sh` no excluye carpetas nuevas de la raíz), y el serve escucha en **`:80`**, no en 8081.

**Deuda que esta sesión deja dicha, no escondida**
- `ticket_types.conditions` es **columna muerta** (cero lectores, cero escritores, el catálogo no la
  edita) — ficha en `DEUDA.md` con las dos salidas; es del owner.
- **5 controles bajo 44 px en el paso del carrito**, medidos: entran en la **T4**.
- **D4 de la auditoría sigue pendiente** del detalle del owner.

🟦 **LA HORA EXTRA · CÓDIGO COMPLETO — LAS CUATRO TANDAS EN EL ÁRBOL** (2026-09-03, `#410`/`#411`;
commits `d36c59a6` · `08c124be` · `f2c23a6b` · `18c74c7e` + docs. **La ejecución está en
`docs/specs/hora-extra.md` §8, que es por donde se empieza**).
▶ **Lo que queda**: el **OJO del owner** (el guion §6·4 corrió en headless: 4/4) y **dar de alta el
PRODUCTO, que es DATO** — catálogo → complemento nuevo → «Ocupa la franja siguiente» + «Cuánto
ocupa» (60) + precio + engancharlo a la entrada larga desde su pestaña de complementos. Sin ese
alta, la feature está dormida por construcción (interruptor `false` por defecto, 0 filas tocadas).
▶ **Antes de la obra, una SEGUNDA revisión** (`#410`, §4.11 de la spec): 4 huecos nuevos —el peor,
que el tope del padre es por **SUMA de hermanos** y `effectiveQuantity()` no puede verla—, 3 reglas
escritas (la franja de la hija es determinista por el `UNIQUE` de `slots`) y el `[DECIDIDO owner]`
de que **el precio de la hora extra NO varía por día** (los complementos se tarifican a HOY en los
tres caminos; límite aceptado y escrito, no descubierto en producción).
▶ **La condición de D1, cumplida y medida**: el escenario `extra-hour` del verificador se vio
FALLAR con la validación de la hija desactivada —**5 asientos escritos en una franja de 1, SIN
carrera**— y pasar con ella (1 de 8 sobre InnoDB). 24/24 mutaciones muerden (12 núcleo · 8 editor ·
4 oferta, con control previo y por código de salida); los SIETE escenarios + Redsys en verde.
▶ **Dos verdades incómodas que la obra destapó, resueltas hacia lo ALMACENADO**: la oferta y el
cobro contaban DISTINTO los packs como ocupantes de plazas (un test cementaba la premisa falsa y se
reescribió, el precedente del `SlotOfferTest` de `#324`) — hoy la derivación es UNA
(`CartOccupants`) y la usan los dos lados.
▶ ⚠️ Deuda deliberada, dicha: la cota de la oferta va SIN la cesta y la UI de complementos del alta
manual no decora (§8.3 de la spec, con el porqué y la palanca).

*(Lo de abajo es la spec APROBADA previa a la obra — historia de cómo se llegó.)*
🟦 (2026-09-02, `docs/specs/hora-extra.md`; las tres decisiones cerradas en §7).
`[owner]`: *«no tener 4 productos tipo entrada 1 hora, entrada 2 horas… la idea es tener dos y si
alguien quiere más horas, puede añadirlas»*, atada solo a ciertos productos y con límites.
▶ ❗❗❗ **LA CORRECCIÓN DEL OWNER TIRÓ EL PRIMER DISEÑO Y DEJÓ UNO MUCHO MÁS PEQUEÑO**: yo leí «hora
extra» como *«la reserva dura más»* y él precisó *«la hora extra es de 1 entrada, no de las 4»*. O sea
**4 personas de 10 a 12 y UNA de 12 a 13**: no es más tiempo con las mismas plazas, es **menos plazas
más tarde** — un OCUPANTE nuevo, no una duración distinta.
▶ La lectura falsa pedía **duración por LÍNEA** (50 referencias en 14 ficheros, ~8 servicios de aforo);
la correcta cabe en lo que ya existe, porque **`occupancyMap` no filtra por tipo**: cuenta cualquier
línea con franja, usando sus plazas y la duración de su producto. *Cuando el diseño sale caro,
sospecha de que estás modelando el problema equivocado antes de aceptar el precio.*
▶ **Y resuelve solo el precio**: si la cantidad son ENTRADAS que se quedan, el precio es por persona
por construcción y el tope físico sale gratis (no se pueden quedar 5 de 4).
▶ **Lo que hay que construir son DOS cosas**: el **LOCK** (hoy los complementos se saltan) y
**recalcular la oferta al añadirla**, o se ofrece lo que el checkout rechaza (`AFORO-02`).
▶ ❗❗❗ **REVISADA DE FORMA ADVERSARIAL** (2026-09-02): 35 hallazgos → 10 refutados a fondo → **3
defectos reales y 2 bloqueos de diseño**, y los tres defectos los encontraron **dos lentes
independientes cada uno**. §4.10 los lista.
▶ **El peor no es un defecto: es que el diseño NO SE PODÍA PONER EN MARCHA.** `normalizeByType()` hace
`unset(duration_min)` para todo complemento y el formulario esconde el campo, así que **el panel borra
el dato del que depende el interruptor** — y la propia guarda de la spec lo habría rechazado. *Una
revisión que solo entra por donde se vende no ve si el dato se puede introducir.*
▶ **Y el que más dinero cuesta sobrevende SIN carrera**: los ocupantes provisionales son DOS
derivaciones y **ninguna cuenta a los hermanos de la misma línea** — dos complementos que ocupan sobre
la misma línea venden dos veces la última plaza **con el lock puesto**.
▶ ✅ **LAS TRES DECISIONES, CERRADAS** (`[DECIDIDO owner, 2026-09-02]`), y la spec pasa a 🟦 APROBADA:
**D1** se construye, **empezando por unificar la derivación de ocupantes** (tres de los nueve bordes
son el mismo defecto visto desde sitios distintos), y **el escenario del verificador se escribe ANTES
y se ve fallar**. **D2** solo entradas — y su aclaración *«en un pack la hora extra es para todos los
invitados»* **no simplifica: agranda el hueco** (con toda la fiesta son **veinte** invitados invisibles
para `max_guests_per_slot`, no uno) y sobre todo revela que **en un pack eso ya no es un complemento
que ocupa: es que la fiesta DURA MÁS**, o sea otro mecanismo. **D3** se ve como un complemento normal,
y sale barato: la hoja ya imprime ventana horaria y duración, así que al operador le queda una resta.
⚠️ Su único coste, anotado: el campo «duración» dirá 2 h aunque una persona esté tres.

🟦 **EXCURSIONES DE COLEGIO · EL PRODUCTO, ENSAYADO Y VERIFICADO EN STAGING — FALTA TU MANO EN
PRODUCCIÓN** (2026-09-02, `DECISIONES #409`). `[DECIDIDO owner]`: **señal 100,00 € fijos** (hasta hoy
era un número de agente) y justificante en **`required`**.
▶ **No necesita despliegue**: `#322` y `#324` ya están en producción y esto es **DATO**. El guion vive
en `~/excursiones-produccion.php`, **fuera del repo** (`#325`), y es **idempotente con dry-run**:
```
ssh jumpweb-prod 'cd ~/public_html && php artisan tinker' < ~/excursiones-produccion.php                  # plan
ssh jumpweb-prod 'cd ~/public_html && EXCURSIONES_GO=1 php artisan tinker' < ~/excursiones-produccion.php # escribe
ssh jumpweb-prod 'cd ~/public_html && php artisan slots:generate-rolling'                                 # franjas
```
▶ ❗❗ **LA ACTIVACIÓN (`required`) DEPENDE DEL DESPLIEGUE**: `ticket_types.guardian_authorization` es
de `#400` y **ni staging ni producción la tienen**. El guion lo detecta, crea el producto igual y lo
dice; al desplegar, **se repite el mismo guion** y entonces sí la aplica.
▶ ⚠️ **Este agente NO tiene acceso a producción** (medido: `playjump2@51.68.7.199 → Permission denied`;
solo hay llave de staging). Los tres comandos los corres tú.
▶ **Verificado en staging**: 24 acciones, tercera pasada **0 por hacer**, 130 franjas, y el precio por
tramo medido — 30–69 a 15,00 €/persona · 70–99 a 13,00 · 100 a 12,00 (+2,00 en finde).
▶ ⚠️ **Para tu ojo, no es defecto**: **99 personas cuestan 1.287 € y 100 cuestan 1.200 €**.

✅ **AVISO DEL CARRIL DE GOOGLE, LEÍDO Y ACTUADO** (2026-09-02, `DECISIONES #408`; retirado según
`CONVENCIONES §10.4`). Nos avisaron por segunda vez de la misma trampa —`User::factory()` cuyo nombre
se PINTA, enfrentado a una aserción por subcadena— y de que podía haber más. **La había: una cuarta**,
en `GuardianAuthorizationScreenTest`, donde la pantalla **prellena el nombre de quien tiene sesión** y
el caso aseveraba `assertDontSee('Nora')` sobre un firmante de factoría.
▶ **Reproducida a voluntad** (`'name' => 'Nora Colisión'` → rojo con el producto sano) y fijada.
▶ **Barrido completo del subsistema**: las 41 aserciones negativas de los 17 ficheros del justificante,
una a una. Las demás están sanas — `GuestMinorIsolationTest` ya asevera por nombre COMPLETO y con
control, y el `'Ana'` de `GuestMinorAuthorizationTest` va contra un payload de auditoría que solo
contiene `subject_type` y un id, donde ningún nombre de factoría puede entrar. *Sobre-arreglar también
es un defecto: se deja dicho lo que se miró y por qué no se tocó.*

✅ **EL JUSTIFICANTE, VERIFICADO EN TRES CAPAS ANTES DE TOCAR PRODUCCIÓN** (2026-09-02,
`DECISIONES #407`). Servidor **41 ✓/0 ✗** sobre los cinco escenarios sembrados · navegador: hoja en
blanco con los 5 nombres ajenos ausentes, firma → «registrada», segundo progenitor → «ya tiene su
autorización» sin decir quién, fecha de adulto rechazada (**8 ✓/0 ✗**) · API: dos reservas con su
enlace y sus plazas, y `link: null` sin plazas libres.
▶ **Un defecto, arreglado**: los campos de la pantalla pública medían **42** y el estándar propio es
44. Se escapó porque el barrido de `#264` recorre *«las siete vistas PÚBLICAS»* y esta pantalla
**exige un enlace firmado** — igual que el paso de datos del cajón y el post-form, que compartían la
misma regla. **12 controles bajo 44 → 4**, y los cuatro justificados (dos son el honeypot, uno tiene
su área en la etiqueta —44×310 medidos— y el otro es el enlace en línea que WCAG exime).
▶ ⚠️ **Cinco errores de instrumento pagados y escritos** en la entrada: `networkidle` cuelga con
Turnstile, `document.fonts.size` no dice nada del FOIT, una aserción por subcadena, una espera que
pasaba de largo porque `!t` es cierto mientras el campo no existe, y un clic sin desplazar.
▶ **El escenario sembrado se restauró byte a byte** — pero OJO, **envejece**: `PRUEBA-PUERTA` se
sembró para «hoy» el 01/09 y ya no sale en la puerta. Anotado en el guion.

✅ **EL JUSTIFICANTE, REVISADO DE FORMA ADVERSARIAL — Y TRES ARREGLOS, UNO DE ELLOS INMINENTE**
(2026-09-02, `DECISIONES #406`). Seis lentes independientes + una pasada que REFUTA cada hallazgo:
**29 crudos → 25 únicos → 10 refutados a fondo → 7 sobreviven**, y la refutación **corrigió la
severidad de tres** (dos «altas» eran bajas). *Sin esa pasada habría dos alarmas de seguridad falsas.*
▶ ❗❗❗ **ARREGLADO ANTES DE QUE LLEGARA A PRODUCCIÓN**: la migración `2026_09_02_120000` se declaraba
«ya migrada» **en el paso 4 de 5**, y el paso 5 es el que crea el `UNIQUE`, la FK y el `NOT NULL`. En
MySQL cada `ALTER` hace commit implícito (`supportsSchemaTransactions` → **false**, medido), así que
un corte dejaba la tabla **sin la última red de «un niño, un papel»**, con la migración marcada como
hecha y **sin que nada avisara**. Producción no la ha corrido todavía: **el próximo despliegue sí**.
▶ Verificado sobre **MySQL real** en BD desechable, con el estado intermedio fabricado y **CONTROL**:
el código viejo corre en **5,17 ms**, dice `DONE` y deja la tabla rota; el nuevo **repara en 382 ms**
y es no-op (7 ms) si ya está.
▶ **El nombre del menor sale del ASUNTO** del correo de copia (va a un buzón tecleado que nadie
verifica; el asunto se replica en previsualizaciones, logs de correo y rebotes, donde el adjunto no
llega). Se queda en el cuerpo.
▶ **`GuardianTwoReservationsTest`**: los SIETE ficheros del subsistema creaban pedidos de UNA línea,
así que **el cambio entero de `#401` se podía revertir en verde**. Tres mutaciones, las tres muerden.
▶ **Cinco fichas nuevas en `DEUDA.md`**, con su reproducción — la más jugosa: el cupo lo imponen DOS
escritores sin lock compartido, y eso es decisión de producto tuya.
▶ ⚠️ **Y el carril de Google nos arregló de paso un test INTERMITENTE nuestro** (aviso suyo en
`ESTADO`, leído y **retirado** según `CONVENCIONES §10.4`): `GuardianAuthorizationScreenTest` creaba
al titular con `User::factory()` y aseveraba **por subcadena** que en la hoja del padre no aparecen
«Luis», «Carlos», «Elena» ni «Nora» — con un responsable llamado *«Luis Blanco Díaz»* se pone rojo.
**Es nuestra propia lección de `#337`** —*un nombre aleatorio contra una aserción por subcadena es
una moneda al aire disfrazada de test*— reaparecida en otro fichero. Lo arreglaron con nombre fijado
y control. *Que una lección esté escrita en una entrada no la aplica en los ficheros que no se
tocaron ese día.*

⚠️⚠️ **UN VERIFICADOR DE DINERO LLEVABA FALLANDO 3 DE CADA 7 DÍAS CON EL PRODUCTO SANO** (2026-09-02,
`DECISIONES #405`). `mixed-party:verify-concurrency` siembra su franja a `+30 días` y solo ponía
precio en la tarifa `normal`; en esta BD `special` (prioridad 10) cubre viernes, sábado y domingo, y
esos días el dominio **se abstiene con razón** (`#288`) → cero líneas y rojo.
▶ **Lo delató el propio instrumento**: decía «la línea se duplicó» sobre una tabla que ponía **0
líneas y 0,00 €**. *Cuando el mensaje contradice a sus cifras, el roto es el instrumento.*
▶ Arreglado en tres sitios (precio en todas las tarifas · **guarda del instrumento** antes de
forkear · veredicto que separa duplicación, ausencia e importe) y **visto FALLAR sin el lock: 12
líneas y 84,00 €**. Preexistente, verificado en la base `#341`: no lo causó la fusión.

🚀 **EN PRODUCCIÓN: GOOGLE AUTH, EL JUSTIFICANTE Y LAS EXCURSIONES** (2026-09-02, `DECISIONES #353`
y `#354`). `playjump.es` corre el commit `64ff3b6`, con **69 clientes y 6 pedidos reales** detrás.
▶ ❗❗ **CORRIGE A ESTE MISMO DOCUMENTO**: `ESTADO` afirmaba —con «medido» delante— que este agente
**no tiene acceso a producción**. Lo tiene. *Una medición heredada no es una medición.*
▶ **Las 4 migraciones que faltaban** aplicadas (justificante, `user_identities`, el justificante por
reserva, revocación de consentimientos). ⚠️ Una de ellas llevaba el defecto que `#406` arregló —se
declaraba migrada en el paso 4 de 5— y producción **no la había corrido**: el arreglo llegó a tiempo.
▶ **Copia de la BD antes de tocar** (134 KB, 49 tablas, volcado íntegro). ⚠️ `deploy.sh` **no la
hace**: ficha en `DEUDA.md`.
▶ **Condiciones v1 publicada**, y la regla de gracia medida sobre clientes REALES: **63 de 69
indultados**, 6 pasarán por la casilla (los de mostrador, que nunca aceptaron nada).
▶ **Excursiones creadas**: zona propia 08:00–15:00 que ignora el cierre, 1 grupo y 100 plazas por
franja, **130 franjas**, dos packs con señal de 100 € y justificante `required`. **24 acciones, las
mismas que el ensayo de staging.** ⚠️ El guion **no era idempotente para los productos** —busca por
nombre con tilde y el JSON los guarda escapados— y una repetición los habría DUPLICADO; no llegó a
pasar y se verificó contando.
▶ **30 fechas especiales** (festivos y vísperas 2026-2027 → tarifa especial). Pascua 2027 la calculó
`easter_date()`, no una cabeza. Verificado con control: martes 08/12 → 14,00 € · martes 15/12 → 12,00.
▶ **Los legales, que eran requisito de salida de Google**: la privacidad ya describe el acceso con
Google en los tres idiomas, y **el francés estaba PUBLICADO con cinco `[À COMPLÉTER]`** —reescrito
desde el español—. ⚠️ Y `#350` había dejado desfasado el texto de marketing sin que nadie lo viera.
▶ ❗❗ **LO QUE MÁS VALE SABER, porque el owner lo encontró antes que yo**: las claves de Google **NO
van en el `.env`, van en `settings`** (`[DECIDIDO owner]` Q10). Puso las suyas en el `.env` y el botón
no salía. ⚠️ **Y la comprobación que yo había dado por buena era la equivocada** (`grep GOOGLE_CLIENT
.env`): acertó por casualidad. Lo que vale es `GoogleAuth::enabled()`.
▶ **Verificado en producción con navegador, 12/12**: el botón se pinta en `/login` y `/registro`, su
«G» carga, el separador está y va encima del formulario; y `/auth/google` redirige con los **seis
parámetros exactos**. ⚠️ Dos falsos negativos de esa sonda, los dos creíbles: exigir el `href`
relativo cuando el servidor lo compone absoluto, y leer `naturalWidth` sin esperar a la imagen.
▶ ⚠️ **La compra online sigue CERRADA** (`sales.online_enabled = 0`, decisión del owner hasta tener
Redsys de producción): las excursiones están creadas pero **no son comprables** todavía.

▶ ❗❗ **LO QUE ESTA SESIÓN DEJÓ EN TU BD LOCAL, y NO coincide con producción** (para que nadie lo
tome por el estado del producto):
· **`sales.online_enabled = 1`** en local, para poder recorrer el embudo. En producción sigue en **0**.
· **La v1 de «Condiciones» publicada** en local con el texto REAL del CMS. ⚠️ La publiqué antes sin
  querer con un texto de relleno en una sonda de medición; se retiró y se republicó bien. *Publicar es
  irreversible: no lo hagas desde una sonda.*
· **Cuenta de sonda `sonda.sesion@jumpweb.test`** (contraseña `una-clave-de-sonda-2026`), **sin
  teléfono y sin condiciones aceptadas**, que es el estado en el que el paso de pagar enseña los dos
  campos. Es local; bórrala cuando estorbe.
▶ **Sondas montadas** (gitignoradas, en `storage/app/` y `~/e2e` del contenedor):
`sonda-google-encima.mjs` (el botón encima del formulario, con control), `prod-boton.mjs` (el botón en
PRODUCCIÓN, 12/12) y `sesiones.mjs` (el ritmo vertical de «Cuentas vinculadas»). ⚠️ Para conducir el
cajón hay que **clicar por DOM** (`element.click()`), no con el puntero de Playwright: el armazón
intercepta el hit-test y el timeout parece defecto del producto.
▶ **Arneses versionados**: `scripts/mutar-alta-sin-casillas.sh` (6/6) y `scripts/mutar-google-encima.sh`
(8/8), los dos con puerta de VERDE y **comprobación de que la mutación se aplicó** — que es la que
cazó que el primero mentía.

⛔ **ONE TAP NO SE CONSTRUYE** (`[DECIDIDO owner, 2026-09-02]`, `DECISIONES #354`): *«con esto es
suficiente»*. **Con eso el carril de Google auth queda CERRADO.**
▶ Se evita la pieza cara —verificar la firma RS256 de un `id_token` que llega del CLIENTE, que `#342`
llamó *«la mitad que nadie debe añadir sola»*—, la dependencia nueva (`firebase/php-jwt`, cuya
decisión queda SIN EFECTO), tres directivas de CSP y una categoría de cookies propia.
⚠️ **Consecuencia que no se puede perder: `prompt=select_account` SIGUE haciendo falta.** El único
argumento para quitarlo era que el chip de One Tap enseñaba el nombre antes de pulsar. Sin One Tap,
ese argumento desaparece.

✅ **EL HERO DEL CIERRE SE JUEGA TOCANDO DONDE SEA** (2026-09-02, `DECISIONES #352`). Medido a
390×844 con el cierre abierto: la tarjeta ocupa **de 10 a 834** y el lienzo empezaba en **684**, o sea
que en una pantalla entera solo respondían los **150 px de abajo**.
▶ ❗❗ **Lo delicado es que `#253` decidió lo contrario y su motivo sigue vivo**: entonces te quedabas
*«bloqueado en el juego»* en el móvil porque un arrastre para desplazar arrancaba una partida. **Lo
que cambia es DÓNDE se toca, no QUÉ cuenta como toque**: con el dedo el arranque se decide al
levantar, y solo si no se movió más de 12 px ni duró más de 700 ms. La mitad que de verdad te
encerraba —cancelar el gesto de desplazar— no se toca.
▶ ⚠️ Y los botones se activan solos: tocar «Reservar» ya no abriría además una partida.
▶ Guarda con **5/5 mutaciones** y sonda de navegador con control en las dos direcciones.

⚠️⚠️ **MENOS FRICCIÓN EN EL ALTA, Y UNA DE LAS DOS ES SEGURIDAD** (2026-09-02, `DECISIONES #351`).
▶ **La contraseña ya NO se comprueba contra el corpus de filtraciones** (`[DECIDIDO owner]`: *«hay
mucha fricción»*). Se te ofreció la vía intermedia —rechazar solo las MUY comunes— y elegiste
retirarla entera. **El coste está asumido y queda escrito: `12345678` es hoy válida**, con 69 cuentas
reales detrás. Lo que sostiene la defensa son los limitadores, la reconfirmación de contraseña de las
cuatro acciones irreversibles y `RGPD-06`. ⚠️ La guarda **no se borró: cambió de signo**, para que
quien la reponga sepa que revierte una decisión.
▶ **El CTA doble ya dice que también inicia sesión**, debajo del rótulo. ⚠️ Va en el subtítulo porque
está MEDIDO: «Entrar o registrarse» se sale **58 px** del botón del nav, que tiene 224 fijos.
⚠️ Ese subtítulo existía desde `#227` pero **solo se pintaba en la barra de móvil**, y al quitarle esa
condición no se puso rojo ni un test: ahora hay guarda, verificada por mutación.
▶ ⚠️ **Y de paso, una ficha de deuda que NO es mía**: a 1080 px ese botón desborda 5 px, medido con
control (idéntico con el subtítulo y sin él).

✅ **GOOGLE AUTH · LA T8, CERRADA ENTERA — Y EL HUECO QUE QUITAR LA CASILLA NO CERRABA** (2026-09-02,
`DECISIONES #350`, spec §21.4.3 y §21.4.4). Con la T8·c y la T8·d, **los SIETE puntos de tu ojo (§21)
están hechos**. Banda `#34x`: usados `#345`→`#354`; el siguiente libre es **`#355`**.
▶ ❗❗❗ **LO QUE IMPORTA DE ESTA TANDA NO ES LA CASILLA, ES LO QUE HABÍA DEBAJO.** Medido ANTES de
tocar nada, con la v1 de «Condiciones» publicada: **una cuenta recién creada salía como si ya las
hubiera aceptado**, así que **el checkout no se las pedía a ningún cliente nuevo** — la T8·b quedaba
desactivada por su propia alta. La causa: el alta escribía su fila de condiciones con una FECHA por
versión, y la regla de gracia de `#348` —la que indulta a los 19 clientes que ya aceptaron— la daba
por buena. *La regla escrita para indultar a los viejos indultaba a todos los futuros.*
▶ Por eso las dos altas **dejan de escribir esa fila**; ahora hay **un solo escritor** de la
aceptación de condiciones en todo el producto, y las dos quedan alineadas con el alta de mostrador,
que ya era así. Caso nuevo **con su control**: la cuenta nueva sí las debe y la gracia sigue viva.
▶ **Tus tres decisiones del día**: (1) la pantalla de Google **se sigue pintando aunque no haya
descargo, por el NOMBRE** —Google devuelve a veces «Ana G.» y ese nombre viaja a la reserva y a la
firma—, lo que **cierra la desviación de la Q6** que llevaba abierta desde `#343`; (2) el texto de
privacidad **deja de decir «acepto»** (sin casilla, afirmaba algo que la pantalla no recoge); (3) para
la T9, **entra `firebase/php-jwt`**.
▶ ❗❗ **UN DEFECTO QUE SOLO VIO LA CAPTURA, y esta tanda lo convertía en carga**: el enlace a la
política de privacidad se pintaba **del mismo color exacto que el párrafo y sin subrayado** — texto
plano con una zona pulsable invisible. Venía de `#343`, pero al quitar la casilla ése pasa a ser **el
único sitio donde las dos altas te enseñan la política**. Arreglado, remedido y con guarda.
▶ **El botón de Google ya va encima del formulario con su «o»** — dentro de los dos formularios, no
colgado de la pantalla: *«encima del formulario» no es «encima del título»*.
▶ ⚠️ **El techo del chunk BAJA por primera vez** (277 → 275, medido 274,50): un trinquete que solo
sube deja de vigilar en cuanto alguien retira código.
▶ **Y un tercer punto tuyo del mismo día, arreglado**: el aire de «Cuentas vinculadas» (0 px al título
y 3 al botón, medidos). ⚠️ **La causa no estaba en esa pantalla, estaba en el botón** —llevaba un
`margin-top` de TRES píxeles que viajaba con él a los cuatro sitios donde se pinta—: *el aire de una
pieza lo decide quien la coloca, no ella*. Queda en 20 y 12.
▶ ❗ **Y una cosa que te costó una vuelta y ahora está escrita** (`VERIFICACION-E2E-CAJON.md` §0.bis):
lo que te dejaba clavado en «elige hora y cantidad» **no era el mantenimiento**, era
`sales.online_enabled = 0` — el interruptor de `#325`—, que hace que `POST /cart/validate-line`
responda 503. Son DOS interruptores distintos y los dos son DATO. ⚠️ La confusión tiene causa: el
texto del aviso de pausa dice «Estamos en mantenimiento» y **viaja siempre** en `GET /booking/status`,
también con las reservas abiertas. **En tu local ya está abierto**, y la v1 de «Condiciones» publicada
con el texto REAL del CMS.
▶ **Verificado**: suite **4.064 · 25.919** · Pint · docs-check · **6/6 y 8/8 mutaciones** en dos
arneses versionados · **sonda de navegador 18/18** con control y capturas de las dos pantallas.
▶ ⚠️ **Para el otro carril**: he tocado `openapi/v1.yaml`, `lang/*/account.php`, `lang/*/validation.php`
y `resources/views/components/layout.blade.php`. Todo empujado; `git pull --rebase` antes de vuestro
próximo push. **No toca `WaiverSigner` ni la aceptación retenida**: la casilla del descargo se queda
donde estaba, y es la única que sobrevive en el alta.

❗❗❗ **▶ CARRIL DE GOOGLE AUTH · SESIÓN CERRADA (2026-09-02, 12:00 → 20:10) — EL PULIDO DEL OJO DEL
OWNER, CINCO TANDAS EN EL ÁRBOL Y TRES POR HACER.** Banda `#34x`: **usados `#345`→`#349`**
(`#400`+ es del justificante; `CONVENCIONES §10.6`).
⚠️ **CADUCADO, y la corrección va delante**: no quedan tandas. La T8 se cerró con `#350`, el pulido
con `#351`, la puesta en producción con `#353` y **One Tap se descartó** (`#354`). Banda `#34x`:
**siguiente libre `#355`**.

⚠️ **HALLAZGO SUELTO, ni mío ni de esta tanda: `DECISIONES.md` tiene un `#217` DUPLICADO.** Dos
entradas distintas del 2026-08-28 (líneas ~11.528 y ~11.814: el ojo del owner sobre la 2c·8, y el
pulido tras la prueba en staging). Es una de las colisiones que la decisión de bandas (`#404`) nació
para evitar, anterior a ella. **`docs-check` no lo ve** —su check 6 solo exige que un número citado
EXISTA— y por eso lleva ahí cinco días.
▶ **No lo renumero en un cierre y a ciegas**, que es justo lo que `#404` avisa que sale caro: hay que
mirar quién cita cada uno y desambiguar las citas, no sustituir a lo bruto. Queda dicho para que se
haga a propósito. *Encontrarlo fue gratis: `grep -oE '^## #[0-9]+' docs/DECISIONES.md | sort | uniq -d`.*

## ▶ POR DÓNDE RETOMAR (lee esto primero)

═══════════ CARRIL DISEÑO · ✅ REABIERTO EL 2026-09-09 (`#469`) — este bloque es HISTÓRICO ═══════════
▶ ❗❗❗ **YA NO SE RETOMA POR AQUÍ: el carril vivo está en `docs/specs/rediseno-desde-canvas.md`**
y su estado, arriba del todo de este fichero. El owner avisó el 2026-09-09 con su sistema de Claude
Design terminado (v1.32, tokens v1.9) y se hizo lo que `#452` mandaba: leerlo entero y contrastarlo
con el código antes de proponer nada. **Lo de abajo queda como registro de lo que se archivó
entonces** — sigue siendo cierto de aquel momento y explica por qué `docs/archivo/` existe.
▶ Lo que dijo `#452` en su día:
*«el diseño lo voy a delegar a claude design primero, voy a iterar ahí, aquí vamos a dejarlo… cuando lo
tenga, te aviso y empezaremos a trabajar sobre una base profesional y robusta, con todo claro»*. Cuando
vuelva traerá su sistema hecho en Claude Design; lo primero entonces es **leerlo entero y contrastarlo
con lo que ya vive en código** (los tokens de `site.css`/`landing.css`, las guardas de
`tests/Feature/Theme/**`, el paquete `client.css` de la instalación) ANTES de proponer nada, y la
regla de la casa sigue: opciones renderizadas, decisiones suyas, guarda por regla.
▶ **Lo que se hizo al parar**: la tanda C del cajón, a medias, **descartada sin commit**; `#450` y `#451`
(las tandas A y B del cajón) **revertidos en un commit nuevo, sin reescribir historia** — el cajón está
como en `1b8db76`, manifiesto congelado incluido, bundle y SSR reconstruidos, `DrawerControlsTest`
retirado, `DEUDA.md` e `INSTALACION-CLIENTE.md` sin lo que aquellas tandas añadieron; el
`design.md` de la raíz, el guion de la portada y la auditoría del cajón **archivados en
`docs/archivo/`** (`design-producto-2026-09-03.md` · `guion-de-la-portada.md` · `auditoria-cajon.md`)
con su 📜 (no se mantienen); los prototipos (el fichero de rutas `prototipos.php` y su `require`,
`Prototipos\PortadaController`, `resources/views/prototipos/`, `public/prototipos/`,
`scripts/prototipo-b/`) y la exclusión de `ArmazonContractTest` **retirados**; el ajuste
`sidebar.engine` que la sonda había puesto en `spa` **borrado** de `settings` (el motor vuelve a su
defecto) y el `client.css` local sin `--ok-text/--err-text`.
▶ **Lo que SIGUE en código y vigilado**: las tandas C→F de la auditoría de la web pública
(`#434`→`#437`, `specs/auditoria-diseno.md` §11–§14), cuya G queda parada con el carril. ⚠️ Sigue el
paso de despliegue de «DOS COSAS DEL OWNER» (las cinco líneas del `client.css` de producción).
⚠️ `#438`–`#451` en `DECISIONES.md` son historia: **sus medidas valen, su ejecución ya no está**.
**Lo de abajo, hasta el REPARTO, es el registro de lo archivado — no hace falta leerlo para trabajar.**

═══ 📜 CARRIL DISEÑO · LA AUDITORÍA DE LA WEB PÚBLICA, EN EL REPO (2026-09-02, `#430`) — superado por el bloque de arriba ═══
▶ **`docs/specs/auditoria-diseno.md`** es el informe —**3 críticos · 10 mayores · 6 menores**— y
**sustituye a «Un solo idioma»** (01-09), que se publicó como artefacto y **no es recuperable** (medido:
no está en la cuenta del owner con `scope: all`, ni en el repo, ni en local). El artefacto de lectura
es «Costuras a la vista» (https://claude.ai/code/artifact/4d0ce068-150e-4f33-86eb-96ba071c9f40);
**el registro es el fichero**. ▶ **Lo que sigue: las seis decisiones del
owner (§7) y, con ellas, la tanda C (§8: lo roto — el mapa de `/contacto` tapado al 100 %, el foco de
los campos, el acordeón con tope, los cuatro contrastes)**. **No se tocó ni una línea de producto.**
▶ ❗❗ **FASE 2 ARRANCADA (2026-09-03, `#431`)** — el owner preguntó si arreglar la auditoría daba
«diferenciación real y una UX que explique la propuesta»; medido, **no** (la portada tarda 6,3
pantallas en decir qué es el parque). Hechos: **`design.md` (raíz) = el sistema del PRODUCTO** (roles y
mecanismos; familias de estructura `[PENDIENTE: owner]`) · **`mockup_playjumppark/design-playjump.md` =
el perfil de PlayJump** escrito desde su canvas entero (sus tablas viven en el JS de los artboards: su
contraste marca «Cian sobre Papel 2,45 ✕ NUNCA» y «Blanco sobre Verde 2,95 ✕ NUNCA» —los dos pares
que la web sirve— y su tabla de roles pone el enlace en papel en Azul Muro) · **`docs/specs/
guion-de-la-portada.md` ⬜** = el guion (cuatro preguntas · «cada regla donde muerde» · orden que
reabre `#314` con medida · siete decisiones D-G1..7 · objetivos medibles con `storage/app/
audit-camino.mjs`). ▶ ✅ **2c HECHA (2026-09-03)**: las tres formas están **montadas sobre la web y los datos reales** en
rutas **solo de local** (`/_diseno/portada/{a,b,c}` · `/_diseno/piezas` · `?zona=` · `?precio=` ·
`?orden=cumple`; el fichero de rutas `prototipos.php` —retirado en `#452`— bajo `app()->environment('local')`, controlador
`Prototipos\PortadaController` que reutiliza los datos de `HomeController`; hoja
`public/prototipos/guion.css` FUERA de `public/css/`, solo tokens) y **medidas** (guion §6.6): las tres
bajan «qué hay dentro» de la pantalla 6,9 a la 3,2 y la edad de 2,1 a 1,6, con UN selector y cero
contrastes nuevos; **ninguna baja el cumpleaños de la 3 ni el total de 10,7 pantallas** — eso es
D-G3/D-G7 (contenido), no forma. Hoja de decisión: «Tres portadas»
(https://claude.ai/code/artifact/0ef89d7e-ab11-4776-8650-daef4e3a9253).
▶ ❗❗❗ **EL OWNER YA DECIDIÓ AL VERLAS (`#432`, spec §6.7) — POR DÓNDE SE RETOMA ESTE CARRIL:**
**A (FAQ) DESCARTADA · la organización aprobada · B es la forma de trabajo** (C se rompe en móvil).
**Lo siguiente, dicho por él: «haremos B en ARTEFACTO primero, una prueba, para verla con las
decisiones de ahora tomadas»** — o sea, NO tocar la portada real todavía: montar B como artefacto
(HTML autocontenido, con los datos reales copiados y el CSS del sistema), **MÓVIL PRIMERO** (diseñar
a 390; aceptar a 320·360·375·390·414), con: **(1)** tarjetas de zona con **la medida en metros
dentro** y una línea de dato que **no se parta** (sin mayúsculas ni tracking en la línea de dato, o
edad/altura en una fila y precio en otra); **(2)** **slides** con `scroll-snap` y el siguiente
asomando cortado en precios, packs, cómo funciona y visítanos — **nunca en las dos zonas ni en las
dudas**; **(3)** la **capa de imagen** dentro del presupuesto del cliente (`design-playjump.md`
§8–§9): icono de producto en cada precio (`ticket_types.icon`), foto + dibujo de zona en su tarjeta,
friso o pose por sección, **el sello girado en el precio**, nada bajo un párrafo, nada en bucle;
**(4)** el orden zona → precio → juegos → cumple, con **D-G3 abierta** (cumple antes de juegos:
medido 4,9 → 3,9). Objetivo medible en móvil: ≤ 8,7 pantallas estimadas por slides, y las cuatro
respuestas de la spec §2. Cuando el owner dé el ✅ al artefacto, se construye en la portada real con
guardas (línea de dato, un selector, presupuesto de fachada) y las mismas medidas. Los prototipos
de local (`/_diseno/…`) siguen en el árbol hasta entonces. ⚠️ D-G6 es DATO del owner:
edad y altura de zona son texto libre (`zones.age_range`).
▶ ✅ **B EN ARTEFACTO — HECHO (2026-09-03, `#433`, spec §6.8)**: la portada en la forma B, **móvil primero**
(diseñada a 390, aceptada a 320·360·375·390·414 y 1280 de control) con los DATOS REALES de la instalación,
las fotos reales, el kit y el logotipo en línea y los tokens del sistema con los valores del paquete —
https://claude.ai/code/artifact/56f75f11-fb65-4e81-96d2-e9fd8fdc39c2 (el registro es la spec). Lleva las
decisiones de `#432`: metros dentro de la tarjeta de zona con cada dato en su línea; slides con el siguiente
asomando en precios (Kids, 3), pasos y visítanos, y **dos a la vez** donde la elección es entre dos (zonas,
los dos billetes de Jump, los dos packs con filas alineadas); la capa de imagen dentro del presupuesto del
cliente (dibujo y foto de zona, sello girado «desde X €», friso y manchas del kit, pose del cumple); un
relleno de acción por pantalla; y **un conmutador para D-G3** («cumple antes de juegos»). **Medido**: a 390
**8,5 pantallas** (2c: 11 · objetivo ≤ 8), edad 1,6 · precio 2,5 · juegos 3,2 · cumple 4,8 (**3,5 con cumple
antes**), y en los seis anchos **cero** desbordamientos, **cero** líneas de dato partidas, **cero** pulsables a
dos líneas, **cero** áreas táctiles < 44 y **cero** fallos de contraste medibles. Lo que separa 8,5 de ≤ 8 es el
hero (`#252`) y el aire entre secciones (`#314`), no la forma. ⚠️ **Cuatro trampas de instrumento** en §6.8
(la peor: la copia local sin `<meta viewport>` maquetada a 980 px; y un fallo de cascada que la sonda no vio
y la captura sí). **No reproduce** el imán del hero, el vídeo, el minijuego, el menú ni el cajón, a propósito.
Fuente, constructor, sonda y volcado en **`scripts/prototipo-b/`**; la copia servida (gitignorada) en
`http://localhost:8081/storage/prototipo-b/index.html`. ▶ El OJO del owner en el móvil sigue pendiente;
con su ✅ y D-G3 · D-G6 · D-G7, B en la portada real con guardas y las mismas medidas.
▶ ✅ **TANDA C DE LA AUDITORÍA — HECHA (2026-09-03, `#434`, `auditoria-diseno.md` §11)**: el owner pidió
seguir con los puntos de la auditoría y dejar la organización para después. Lo roto: `/contacto` con la
dirección DEBAJO del mapa como pegatina y sin «Parking» (`[DECIDIDO owner]` D4), el anillo de foco en
los campos, el acordeón sin tope (`grid-template-rows`) y los cuatro contrastes — «O inicia sesión»
2,61 → 5,49 (**no era el gris de otra superficie, era `opacity: .62`**: el informe tenía la causa mal),
«Incluido» 2,95 → 6,28 con los tokens nuevos `--on-ok/--on-err/--on-warn`, «¡Felicidades!» 4,11 → 5,13;
suelo de 10 px con guarda. Cuatro guardas nuevas, las cuatro vistas morder. ⚠️⚠️ **PASO DE DESPLIEGUE
DEL OWNER**: dos líneas en el `client.css` de producción (`--on-ok: #101418; --on-err: #FFFFFF;`), que
no viajan por rsync — sin ellas «Incluido» sigue en 2,95 allí. `[DECIDIDO owner]` también **D5** (fuera
los cinco hovers que saltan; el logotipo se queda) y **D6** (banderitas quietas; spinner y calcetines
pausados fuera de vista).
▶ ✅ **TANDAS E Y H — HECHAS (2026-09-03, `#435`, `auditoria-diseno.md` §12)**: **19** reglas que saltaban
al pasar (el informe contaba 5), las 14 públicas responden con color, borde o sombra y las 5 del cajón
quedan enumeradas como excepción que solo encoge; `transition: all` fuera; el foco y el hover ya no
desplazan relleno y la barra de progreso anima `transform`; once literales de color a rol (y
`--offw-accent` pierde el naranja del primer cliente); **m3 NO procede** (`.eyebrow` tiene cinco
consumidores fuera de las doce vistas). Banderitas y estrella quietas, spinner pausado con el cajón
cerrado, calcetines solo mientras se ven: la portada baja de **13 bucles a 5** (los aceptados en `#279`),
`/cumpleanos` de 17 a 2. Guardas `HoverDoesNotJumpTest` y `MotionBudgetTest` (cada `infinite` con su
motivo), 4/4 mutaciones muerden. ⚠️ **El hook rechazó el push de la C** por `SidebarTokenBudgetTest`
(trinquete de colores crudos del cajón, 3 → **0** por tres literales de C y E): baja aquí — tras un
segundo rechazo por escribir un 1 sin leer la cifra (la puerta era `grep -q passed`, que casó con «15
passed»: la puerta de un arnés es el código de salida).
▶ ✅ **TANDA D — HECHA (2026-09-03, `#436`, `auditoria-diseno.md` §13)**: las tres opciones del color de
interacción se renderizaron sobre la FAQ, el menú y el banner reales
(https://claude.ai/code/artifact/1dac07ee-d17a-4484-9ea6-f651d834cab1) y **el owner eligió C, el par de su
sistema** (Azul Muro en papel 6,43 · cian en tinta 6,85). Nace `--interactive` (tinta por defecto,
re-declarado en las dos superficies; el paquete lo fija por superficie), doce reglas públicas dejan
`--zone-1`, el toggle de cookies pasa a `--ok` y el foco de la ficha al anillo del token; lo que identifica
una zona, los rellenos de marca y el cajón quedan enumerados en `InteractionColourIsNotAZoneTest`. Medido
después: FAQ abierta **2,45 → 6,43**. ⚠️⚠️ **PASO DE DESPLIEGUE del owner**: tres líneas más en el
`client.css` de producción (`--interactive` en `:root` y en las dos superficies; están en
`INSTALACION-CLIENTE.md`).
▶ ✅ **TANDA F — HECHA (2026-09-03, `#437`, `auditoria-diseno.md` §14)**: **662 literales al token del
mismo píxel** (233 `font-size`, 429 de espacio) con `scripts/escala-a-tokens.py`; quedan 163 huecos cuyo
escalón no está en la escala. **«Cero reflujo» medido**: la huella de maquetación de las doce vistas a
1280 y 390 es idéntica en geometría en las 24 (la única cadena distinta es cómo Chrome reporta un
`margin: auto`). Guarda `ScaleTokensAreUsedTest`, 2/2 mutaciones muerden. ▶ **Con esto la auditoría
tiene ejecutadas C, E, H, D y F; queda la G** (interiores M3, normas M6, m5, m6: D2 y D3), que se valora
con la organización y las secciones, y C1 (`/servicios`), que es producto.
▶ **LO SIGUIENTE, dicho por el owner al cerrar (2026-09-03)**: las páginas fuera de la landing se
trabajan APARTE porque él tiene que decidir qué texto y qué información va en cada una; y la
organización la está pensando **ampliada a todas las páginas** (landing, servicios, cumpleaños,
entradas, contacto, el parque…), valorando qué debería ir en cada una **y con ello el white-label y el
data-driven de la BD**. Preguntó si el sistema visual (colores, sombras, píldoras, tags, elementos)
estaba implementado y listo: **sí** —está en `design.md` con guarda por regla— y las interiores se
montan con las piezas que ya existen. ▶ **Lo primero que hará este carril cuando el owner lo abra**:
un CENSO medido por página, en tres columnas —qué información muestra hoy · de dónde sale cada dato
(panel, tabla, `lang/`, quemado en la vista) · qué falta para que sea del panel—, apoyado en
`MAPA-PAGINAS.md`, `MODELO-DATOS.md`, `SERVICIOS-CMS.md` y el inventario del guion §4.2; el
precedente de no hacerlo es «Parking gratis 2h» viviendo en el código (`#434`). Sobre esa foto decide
él la organización; con ella se cierran la G de la auditoría (D2, D3, m5, m6) y D-G3 · D-G6 · D-G7 del
guion, y la portada B pasa a la web real. ⚠️ m6 (pulsables a dos líneas: anchura y tracking, nunca
acortar textos del panel) se puede hacer sin esperar a nada. `design.md` §13 dice qué guardas quedan:
la sonda de contraste sobre el HTML renderizado como test de la suite, y el selector de zona único.
⚠️ `DesignSync` sigue sin autorización en sesión no interactiva; copia del canvas del 27/28/31-08.
⚠️ **Lo que la auditoría vio fuera de su alcance**: `resources/views/vendor/mail/html/themes/brand.css`
lleva `#FF5B22` (el naranja del PRIMER cliente) quemado **cinco veces** — fuga white-label en los
correos, ficha en `DEUDA.md`.

❗❗ **REPARTO VIGENTE EN ESTA MÁQUINA (2026-09-02) — DOS CARRILES SOBRE EL MISMO CLON.** Sigue la
regla de `CONVENCIONES §10` y la del 01-09: nadie corre `stash`/`checkout --`/`reset`/`clean`, y
`git add` **solo de lo propio**.
  · **PRODUCTO / RESERVAS** → **carril de la HORA EXTRA CERRADO** (`#410`→`#412`, código completo);
    desde el 2026-09-03 este carril lleva **COMPLEMENTOS DE VENTA POSTERIOR**
    (`specs/complementos-post-reserva.md`, `#413`) → `app/Domain/Booking/**` ·
    `app/Filament/**` (catálogo y pedidos) · migraciones · `openapi/v1.yaml` ·
    `resources/views/reservation/**` · `lang/*/{tickets,guestform,admin}.php` ·
    `tests/Feature/{Booking,Admin,Api}/**`. **Numera en la secuencia natural, desde `#413`.**
    ▶ ⚠️ **AVISO AL CARRIL DE DISEÑO (2026-09-03), y es una incursión declarada**: la sección de
    extras del post-form necesita CSS, y las **96 reglas `.gf-*` viven en `public/css/site.css`**,
    que es tuyo. Tocaré **solo el bloque `.gf-*`** (y sólo cuando la spec esté aprobada), sin entrar
    en nada de la landing ni del armazón. `git pull --rebase` antes de cada push por los dos lados.
    **No toco `resources/views/components/site/**` ni `lang/*/landing.php`.**
  · **PANEL DE ADMIN · UI/UX** (2026-09-03/04) → la auditoría del panel y lo que sale de ella
    (`specs/auditoria-panel-admin.md` · `specs/asistente-crear-pedido.md`) → `app/Filament/**` ·
    `app/Providers/Filament/AdminPanelProvider.php` · `resources/views/filament/**` ·
    `resources/css/filament/admin/theme.css` · `lang/{es,zh_CN}/admin.php` ·
    `lang/vendor/filament-panels/**` · `tests/Feature/Admin/**`.
    **Numera en la sub-banda `#460`–`#469` — último usado: `#468`** (2026-09-05), reservada A
    DISTANCIA igual que la de diseño; si se agota, la siguiente se reserva aquí ANTES de usarla.
    ⚠️ **La T3 tocará `SlotOffer` / `CartOccupants`, que son del carril de PRODUCTO y están en el
    `CRITICAL_RE`**: es una incursión declarada, con `VERIFY_CONC=1` y `git pull --rebase` antes de
    empujar. **No toco nada de la landing ni del armazón público.**
  · **DISEÑO / IDIOMA VISUAL** (este) → ✅ **REABIERTO el 2026-09-09 (`#469`)** con el sistema del
    owner ya entregado; lo gobierna `docs/specs/rediseno-desde-canvas.md` y su ámbito CRECE con
    `tests/Feature/Architecture/{ShapeScale,Rhythm,TouchTarget,ColumnIsDeclaredOnce}Test.php` y el
    paquete `public/css/client.css`, que **no viaja en el commit**. El ámbito heredado sigue siendo
    `public/css/{site,landing}.css` · `resources/views/{home.blade.php,pages/**,components/site/**}` ·
    `lang/*/landing.php` · `tests/Feature/{Landing,Theme}/**` ·
    `docs/specs/{auditoria-diseno,idioma-visual-heredado}.md` · `docs/archivo/**`. Los prototipos y
    `design.md` ya no existen en el árbol (archivados/retirados por `#452`).
    **Numera en la sub-banda `#450`–`#459` — último usado: `#452`** (2026-09-03; la `#430`–`#439` se
    agotó en `#439`), reservada A DISTANCIA de la secuencia natural para que el otro carril no tenga
    que mirar nada antes de empujar; si se agota, la siguiente se reserva aquí ANTES de usarla.
  ▶ ✅ **Aviso del carril de diseño sobre `ValidarRegistroTest`: LEÍDO Y ACTUADO** (2026-09-03,
    retirado según `CONVENCIONES §10.4`). El limitador de la puerta se mide con reloj de pared y cae
    con tres suites en la misma máquina: **ficha propia en `DEUDA.md`** con la reproducción y la
    salida (`travelTo()`, `SUITE-03`, sin perder la auditoría de `SEC-05`).
  ▶ ✅ **Aviso del carril de diseño sobre `components/site/**`: LEÍDO.** No voy a tocar esa carpeta
    ni `lang/*/landing.php`. Lo único compartido que tocaré es el bloque `.gf-*` de
    `public/css/site.css`, declarado arriba.

**✅ EL CARRIL DE GOOGLE AUTH ESTÁ CERRADO Y EN PRODUCCIÓN** (`#353`, `#354`). La T8 entera, los
siete puntos del ojo del owner, y **One Tap descartado por decisión suya**. No queda nada de este
carril por construir; lo de abajo es historia y contexto.

▶ **Lo único vivo son fichas de `DEUDA.md`**, ninguna bloqueante: `deploy.sh` no hace copia de la BD,
`/servicios` da 503 estando en el sitemap, `robots.txt` no declara el sitemap, y la víspera de Jueves
Santo salió de aplicar la regla al pie de la letra (el owner decide si se poda).

📜 *Lo que fue la T9 y ya no se hace, conservado porque explica por qué:*

1. ~~**T9 — One Tap**~~ (`[owner]`: viable **gateando el chip tras el banner de cookies** — quien rechaza ve
   el botón de siempre, quien acepta ve «Continuar como …»). ⚠️⚠️ **Su pieza cara es una sola**: ahí el
   `id_token` llega **del CLIENTE** y no del canje servidor-a-servidor, así que hay que **verificar su
   firma** contra las claves de Google. `#342` avisa de que ésa es *«la mitad que nadie debe añadir
   sola»*. **Medido: no hay atajo** —One Tap no puede devolver un CÓDIGO para reutilizar el canje—.
   ▶ ✅ **CONTESTADA** (`[DECIDIDO owner, 2026-09-02]`): **entra una librería, `firebase/php-jwt`** —
   dependencia nueva (`CONVENCIONES §9.3`), y el motivo es que ésta es justo la pieza donde escribir a
   mano sale caro: **un error ahí no falla, deja entrar**. Es además la que Google documenta para PHP.
   ⚠️ Al traerla: fijar versión, medir qué superficie se usa de verdad y dejarlo dicho.
   ▶ Lo demás es rutina: CSP (tres directivas) y una **categoría de cookies propia** con su texto en el
   banner. ⚠️ **No vale meterla en `social`**, que además `#309` dejó sin consumidor.
   ⚠️ **Si One Tap entra, `prompt=select_account` deja de hacer falta**: el chip enseña el nombre antes
   de pulsar, que es justo la garantía por la que `#342` lo puso.

## ▶ DOS COSAS DEL OWNER, Y UNA ES UN PASO DE DESPLIEGUE

- ⚠️⚠️ **`client.css` de PRODUCCIÓN (carril de diseño, `#434` y `#436`)**: añadir a mano las dos líneas
  `--on-ok: #101418;` y `--on-err: #FFFFFF;` en el `:root` del paquete instalado, **y las tres de
  `--interactive`** (`#0A5C93` en `:root` y en `[data-surface="paper"]`, `#1AA9DE` en
  `[data-surface="ink"]`) — el paquete no viaja por rsync. Sin ellas, «Incluido» sigue en 2,95 y la FAQ
  abierta cae al defecto del producto (tinta) allí, con la suite en verde aquí. El fichero local ya las
  lleva; la receta completa está en `INSTALACION-CLIENTE.md` §4.a.ter.
- ⚠️⚠️ **Hay que PUBLICAR la v1 de «Condiciones»** en cada instalación (panel → Páginas legales →
  Condiciones → «Publicar versión»). **Hasta que se publique no se pide nada y la venta sigue** (el
  hueco falla hacia invisible), así que no bloquea el despliegue — pero sin ella la T8 no hace nada.
  ❗ **Publícala con el texto tal y como está**: la regla de gracia de `#348` da por aceptada la v1 a
  quien ya aceptó, y editar el texto ANTES de publicar le atribuiría a 19 clientes un texto que nunca
  vieron. Si hay que retocarlo: se publica primero y se edita después.
- La **política de privacidad** (Q7) y el **cliente de OAuth de DESARROLLO** siguen pendientes, como
  desde `#344`.

## ▶ LO QUE ESTA SESIÓN TOCÓ

`GoogleButton.vue` · `account/zones/{PrivacyZone,SessionsZone}.vue` · `steps/PayStep.vue` ·
`sections/PurchaseSection.vue` · `stores/accountContext.js` · `{buyer-due,pay,account/privacy}.js` ·
`Identity\Services\{TermsAcceptance,CheckoutDuties,SocialLogin,CustomerAccountContext,LegalDocuments}` ·
`Auth\GoogleAuthController` · `Api\V1\OrdersController` · `Http\Auth\GoogleAuthSession` ·
`Filament\Resources\{Users,Pages}\*` · `public/{css/site.css,images/providers/google.svg}` ·
`lang/*/{account,tickets,admin}.php` · `openapi/v1.yaml` · `routes/web.php`.

▶ **Instrumentos que dejo montados** (gitignorados, como todas las sondas anteriores). El de medir el
paso de pagar vale la pena conocerlo antes de la T8·c: **`storage/app/paydue-render.sh <variante>`**
renderiza el paso 8 con el bundle SSR y lo mete en una página con el CSS real, y
`storage/app/sonda-paydue2.mjs` mide su ritmo vertical. Las cuatro variantes son `ambos`,
`solo-telefono`, `solo-condiciones` y `nada`. ⚠️ **Es MUCHO más fiable que recorrer el embudo**, que se
rompe por cualquier cambio del catálogo — lo intenté primero y lo tiré.
▶ Y dos arneses de mutación que sí van versionados: `scripts/mutar-boton-google.sh` (9/9) y
`scripts/mutar-tarjeta-consentimientos.sh` (10/10), los dos con puerta de VERDE antes de mutar y
veredicto por código de salida.
▶ ✅ **T5 EN EL ÁRBOL** (`DECISIONES #345`, spec §21.1): **el botón oficial de Google** —su «G»
descargada de `gstatic.com`, no redibujada: **0 px distintos de 226.560** al rasterizar contra el
original, con control en 15.855— y el **copy** de «Completa tu registro», que prometía *«poder
reservar a tu nombre»* en una pantalla que solo crea la cuenta.
⚠️⚠️ **La guarda nueva (`GoogleButtonBrandingTest`) existe porque este botón NO LO VEÍA NADIE**, y
está medido: el manifiesto congelado de `SidebarDomContractTest` tiene **cero** ocurrencias de
«google» —sus fixtures no pasan `urls.google` y el componente es un `v-if="href"`—. Y lo que persigue
no es que se rompa: es que alguien lo **«arregle»** devolviéndolo al color de acción del cliente, que
no rompe nada, no lo ve ninguna captura e incumple las directrices de Google. 9/9 mutaciones muerden.
▶ ✅ **T6 EN EL ÁRBOL** (`DECISIONES #346`, spec §21.2): **el marketing es un interruptor** y la
tarjeta de consentimientos ya no desborda el cajón. ⚠️⚠️ **El defecto del owner era un `nowrap` que
dejó de ser cierto cuando alguien alargó lo que envolvía**: `.account__consent-meta` lo llevaba desde
que decía «fecha · versión», y `#344` le añadió «retirado el …» al final → **418 px de línea en un
carril de 380**. Reproducido con control (**0 · 0 · 82 px**) y remedido en **0** en los tres estados.
⚠️ **El documento NO desbordaba**: la barra es del carril del cajón, así que una sonda que mire
`document.documentElement` sale limpia con la barra a la vista. ▶ De regalo, la captura destapó que
la tarjeta acumulaba **cinco filas del mismo consentimiento** (una por vuelta del interruptor): ahora
colapsa a la última de cada tipo — **sin ocultar prueba**, que sigue en la BD y en el export del
art. 20. ⚠️ Chunk **273 → 274**: la poda se intentó, ahorró 0,06 KiB y **se revirtió** porque no
evitaba la subida.
▶ ✅ **T7 EN EL ÁRBOL** (`DECISIONES #347`, spec §21.3): **vincular Google desde la cuenta** —
desvincular ya estaba desde `#344`— y el **vínculo visible en el panel de admin** (sí/no, desde
cuándo, y el correo del vínculo solo si difiere; **nunca el `sub`**).
⚠️⚠️ **Lo que faltaba era la INTENCIÓN, no una comprobación más**: hasta hoy un titular identificado
que pasara por `/auth/google` **cambiaba de cuenta** si su Google resolvía a otra (§18.6 lo avisaba).
La cuarta puerta se define por lo que NO hace: no autentica, no promueve a verificado y no expulsa.
⚠️ **La intención y QUIÉN la pidió viajan en el reto del servidor**: entre la ida y la vuelta caben un
`logout` y un `login` con otra cuenta, y sin esa segunda anotación el vínculo aterrizaría en la cuenta
equivocada **sin que nada fallara**.
⚠️⚠️ **Trampa del arnés, medida: `Http::fake()` ACUMULA stubs y gana el primero que casa** — un caso
que recorre el flujo dos veces recibía el token del PRIMER reto y salía `google-failed`. *Parecía un
defecto del producto y era el instrumento.*
▶ 🟦 **T8·a EN EL ÁRBOL** (`DECISIONES #348`, spec §21.4.1): **las condiciones ya se publican por
versiones**, como el descargo, y `TermsAcceptance` responde si un titular tiene que aceptarlas.
❗❗❗ **EL HALLAZGO QUE REENCUADRA LA TANDA ES LEGAL, y lo medí antes de escribir nada: el embudo NO
enseña las condiciones en ningún sitio** —cero enlaces en los ocho pasos del cajón, y ningún correo
las enlaza—. Hoy solo salen en la casilla del alta, así que **quien ya tiene cuenta compra sin que se
le muestren nunca** (LCGC art. 5 · TRLGDCU art. 97). *Moverlas al checkout no relaja nada: cierra un
hueco que existe hoy.* ⚠️ Lo que SÍ está bien: «Pagar con tarjeta» cumple el art. 98.2.
⚠️⚠️ **La regla de gracia (`[DECIDIDO owner]`: a quien ya las aceptó no se le vuelve a pedir) se hace
con una REGLA, no reescribiendo su fila**: cambiarle la versión de `2026-05-23` a `v1·es` dejaría el
registro afirmando que aceptó un documento que no existía cuando firmó. *Una prueba no se edita para
que la consulta salga más corta.* Y **muere en la v2**.
⚠️ **PASO MANUAL AL DESPLEGAR**: publicar la v1 de `condiciones` desde el panel en cada instalación.
Sin publicar, no se pide nada y la venta sigue (el hueco falla hacia invisible).
▶ ✅ **T8·b EN EL ÁRBOL** (`#349`): el checkout pide condiciones y teléfono, con los dos verificadores
de concurrencia corridos sobre MySQL real. ⚠️⚠️ **Dos guardas de arquitectura obligaron a hacerlo
mejor**: `ApiBoundariesTest` cazó una escritura de dominio en el controlador (nació `CheckoutDuties`,
y al escribirlo se vio que «¿qué le falta a esta cuenta?» se respondía en DOS sitios) y
`SidebarComponentBudgetTest` obligó a sacar la decisión a un módulo plano en vez de subir el techo.
⚠️ **Y la CAPTURA cazó dos defectos que la suite no ve**: el aviso y la línea legal decían casi lo
mismo a 30 px, y las dos peticiones se separaban demasiado poco. ▶ **Queda la T8·c** (las dos altas pierden las casillas + el contrato) y **la T8·d** (el
botón de Google encima del formulario, `[DECIDIDO owner]`). ▶ **Y detrás, la T9: One Tap**
—`[owner]`, viable gateando el chip tras el banner de cookies—, cuya pieza cara es **verificar la
firma del `id_token`**, porque ahí el token llega del CLIENTE y no del canje.
▶ ❗❗ **PARA EL CARRIL DEL JUSTIFICANTE — TRES AVISOS, ACTUALIZADOS AL CERRAR**:
**(1)** He tocado **`openapi/v1.yaml`**, **`lang/*/{account,admin}.php`** y **`lang/*/tickets.php`**, de
los ocho ficheros compartidos. Todo empujado; `git pull --rebase` antes de vuestro próximo push.
**(2)** **`Api\V1\OrdersController` ha cambiado** (`#349`): antes del dinero comprueba lo que el
comprador debe. Está en el `CRITICAL_RE`, así que si volvéis a tocarlo os pedirá `VERIFY_CONC=1` — yo
corrí `purchase:verify-oversell` y `redsys:verify-concurrency` con 16 procesos y pasan.
**(3)** ⚠️ **La T8·c todavía NO está hecha**: el alta sigue pidiendo privacidad, condiciones y
marketing, y ahí vive también la casilla del descargo. Cuando la haga tocaré `RegisterForm.vue` y
`GoogleSignupZone.vue` — **no toca `WaiverSigner` ni la aceptación retenida**, el descargo se queda
donde está. Si aseveráis sobre el marcado de esas dos pantallas, decidlo aquí.

⚠️⚠️ **LOS DOS CARRILES ESTÁN FUSIONADOS Y LA NUMERACIÓN, CORREGIDA** (2026-09-02,
`DECISIONES #404`). Al arrancar, `main` local y `origin/main` habían divergido — **9 commits del
carril de Google auth** (el otro equipo, ya empujados) contra **4 del justificante** (aquí, que el
agente anterior dejó sin push) — con la **quinta colisión de numeración**: los dos habían escrito
`#342` y `#343`.
▶ `[DECIDIDO owner]`: renumera quien llega después al remoto. **El justificante pasa a `#400`
(activación) · `#401` (cuelga de la RESERVA) · `#402` (el sobre del store) · `#403` (compartir el
enlace)**; **Google conserva `#342` y `#343`**. 76 citas reescritas con ámbito por LÍNEA en los ocho
ficheros compartidos, y huella del otro carril verificada idéntica antes y después.
▶ ❗❗ **LO QUE HAY QUE SABER SI SUBES UN PRESUPUESTO ESTANDO LOS DOS CARRILES VIVOS**: los dos
subieron el techo del chunk del cajón midiendo **su rama sola desde la misma base**, y al fusionar
**ninguno de los TRES valía** (267 y 269 suyos, 268 de aquí, contra **272,71 KiB** medidos).
Techo a **273**, con 0,29 KiB — la holgura más estrecha que ha tenido. Y el del payload del montaje
igual: **9.967 B** medidos contra los 9.900 que su rama dejó puestos → **10.000**.
▶ **Suite conjunta: 4.003 · 25.611** verde, tras reconstruir los dos bundles (la guarda de SSR rancio
mordió con 35 rojos citando ficheros de los dos carriles).

🟦 **GOOGLE AUTH · LAS TRES TANDAS DE CÓDIGO, EN EL ÁRBOL** (2026-09-02, `DECISIONES #342`, `#343`
y `#344`; spec §18, §19 y §20). Entrar, registrarse y **los tres derechos que no se podían ejercer**.
▶ ❗❗ **DOS DE LOS TRES HUECOS QUE CIERRA LA T3 NO ERAN DE GOOGLE: llevaban vivos desde el primer día
para todo el mundo.** El peor: **el consentimiento de marketing no se podía retirar** —se daba con un
clic en el alta y ninguna ruta lo actualizaba— y el art. 7.3 exige que retirarlo sea *tan fácil como
darlo*. Ya se puede, y **queda constancia**: la fila no se borra, se sella con `revoked_at`.
▶ **TU DECISIÓN DEL DÍA**: sobre las cuatro acciones que exigen contraseña elegiste **la contraseña
por correo con el aviso en pantalla**, no el ticket de re-autenticación que proponía la spec. Está
implementado así y §8 queda corregida. *Lo que faltaba no era un camino nuevo de autenticación: era
decírselo donde se topa con la pared.*
▶ **Y entra DESVINCULAR**, que valoré que no podía esperar: sin ello, el aviso por correo de cada
vinculación **no servía de nada** —la única salida de un vínculo no pedido era borrar la cuenta—.
▶ ⚠️ Dos presupuestos suben (chunk 267→269, payload con sesión 9.650→9.900) y los dos **después de
podar**, con la poda medida y escrita.
▶ ✅ **VERIFICADO EN LA WEB LOCAL, no solo en la suite** (con claves de prueba y quitándolas después):
la ida sale a `accounts.google.com` con **los seis parámetros exactos** —`redirect_uri` con la RUTA
COMPLETA, `scope` los tres mínimos, `prompt=select_account`, `access_type=online`, `state` y `nonce`
de 64 hex—; `/registro/google` abre el cajón en `google-signup`; los textos de esa pantalla salen
**solo en su puerta** (0 en la home, 1 en la puerta); y **sin claves el botón desaparece y la ruta da
404**.
▶ ⚠️⚠️ **UNA TRAMPA DE MEDICIÓN, Y LA PRIMERA VERSIÓN DE ESTA LÍNEA CAYÓ EN ELLA** (corregido el
2026-09-02 midiéndolo en staging): sobre el HTML servido, **`grep "Continuar con Google"` da 1
siempre** —es el RÓTULO dentro del payload, que viaja para todos— y **`grep auth__google` da 0
siempre**, con claves y sin ellas. *Ninguno de los dos dice si el botón se pinta*, porque **el cajón
NO se renderiza en el servidor**: la página solo trae el punto de montaje (`sidecart-spa`) y Vue lo
dibuja en el navegador (medido: `auth__form` → 0 en una página con el cajón).
▶ **Lo que SÍ lo dice**: que `urls.google` viaje en el `data-boot` —su presencia ES el interruptor— y,
para el pintado de verdad, un navegador. Comprobado en staging: con claves, `urls.google` aparece y
`/auth/google` redirige a Google con `redirect_uri=https://jumpweb.sites.aelium.app/auth/google/callback`.
*Un indicador que vale lo mismo en los dos estados no es un indicador.*
▶ ✅ **DESPLEGADO A STAGING Y VERIFICADO** (2026-09-02, `https://jumpweb.sites.aelium.app`): las dos
migraciones aplicadas —`user_identities` y `consents.revoked_at`—, waiver en modo **`interno`** (o
sea que la pantalla SÍ pedirá el descargo), y **sin claves la ruta da 404 y el botón no se pinta**.
**A producción NO se ha subido nada.**
⚠️ **Y una precisión sobre el descargo, porque la primera versión de esta línea decía «con la v1
publicada» y eso no existe**: no hay tabla de versiones ni paso de publicación —la versión canónica es
una **constante de clase** (`WaiverSignature::CANONICAL_VERSION`) y el único interruptor es el ajuste
`waiver.mode`—. Medido el 2026-09-02 en los dos entornos: `mode=interno · enabled=true`. *No busques
un paso de publicación: no lo hay.*
▶ ❗❗ **Y una corrección de dato que te ahorra un error en Google Cloud**: **`jumpweb.staging.aelium.app`
NO EXISTE** —medido: no resuelve en DNS—. El host de staging es **`jumpweb.sites.aelium.app`**, que es
el que llevan `deploy.sh` y el `~/.ssh/config`. Registrar el otro en la consola no habría servido de
nada, y el síntoma habría sido `redirect_uri_mismatch` sin nada que depurar de nuestro lado.
▶ ✅ **LAS CLAVES DE DESARROLLO YA ESTÁN PUESTAS** (2026-09-02) **en staging Y en local**, del cliente
`playjumppark_dev` que el owner creó — **el de producción quedó limpio de URIs de prueba**, que era el
punto: su secreto no sale de producción. Verificado en los dos: `urls.google` viaja en el montaje y
`/auth/google` redirige a Google con el `redirect_uri` de cada host.
⚠️ **La pantalla de consentimiento está en modo *Testing***, así que solo entran las cuentas listadas
como usuarios de prueba en la consola. El síntoma, si falta la tuya, es «acceso bloqueado» **después**
del botón.
⚠️ **En producción NO hay claves puestas** y no debe haberlas hasta que esté la política de privacidad
(Q7): sin ellas la ruta da 404 y el botón no se pinta, así que producción sigue igual que antes.
▶ **QUEDA SOLO TU OJO** y **la política de privacidad**: es requisito de salida de tu Q7 — no se
anuncia el botón a clientes reales sin que el documento describa el tratamiento (art. 13/14). El
texto vive en la BD, así que en `playjump.es` es un paso manual desde el panel.

📜 **GOOGLE AUTH · LA T2** (la entrada de arriba la CONTINÚA: la T3 ya está hecha, así que su «queda
la T3» del final ya no aplica; lo demás sigue vigente) (2026-09-02,
`DECISIONES #343`, spec §19). Quien no tiene cuenta vuelve de Google, completa **teléfono, condiciones
y descargo** en el cajón, y entra **ya firmado** — que es exactamente lo que la decisión de §4 buscaba:
que nadie llegue al parque sin haberlo aceptado él mismo.
▶ **La Q8 la contestaste tú con la cifra delante**: la pantalla va **en el cajón**, no en una página
propia. Lo que costaba se midió ANTES de escribir: **44 B de holgura** en el chunk y **~6,2 KiB** de
pantalla, así que se paga con **dos podas medidas** —carga diferida (−1,88 KiB) y el estado fuera del
store global (−1,32 KiB)— y el techo sube 263 → **267**. **Los ~380 B de sus textos no viajan en
ninguna página**: es la primera poda por RUTA del montaje.
▶ ❗ **HAY UNA DESVIACIÓN DE LA LETRA DE TU Q6 Y ES TUYA DECIDIRLA** (spec §19.6): dijiste que sin
descargo que pedir **no hay pantalla**; la hay, porque esa pantalla recoge además el **teléfono**
—«imprescindible para las reservas», dijiste— y la **aceptación de condiciones**. Hoy no muerde:
`playjump.es` está en modo `interno` con versión publicada.
▶ ⚠️ **El botón NO lleva el logotipo tetracolor de Google** y es una decisión, no un olvido: el set de
iconos exige `currentColor` y rejilla 24, y un glifo con colores dentro rompe la anatomía que hace que
la web se vea de un solo idioma. Dice «Continuar con Google». Ficha en `DEUDA.md`.
▶ **QUEDA LA T3** —las CUATRO acciones que hoy exigen contraseña (una cuenta de Google no puede
ninguna: art. 12.2), **desvincular**, y el interruptor de marketing con su registro de retirada— y
**TU OJO**: guion en `VERIFICACION-E2E-CAJON.md` **§5.google**, con el caso P12 montado paso a paso.
▶ ❗ **Y para eso hace falta el cliente de OAuth de DESARROLLO** (`localhost:8081`): el que me pasaste
es el de PRODUCCIÓN y **no sirve para probar aquí** —Google exige coincidencia exacta de la URI—.

📜 **GOOGLE AUTH · LA T1** (continuada por las dos entradas de arriba: su «queda la T2 y la T3» ya no
aplica; **lo que sí sigue vigente es todo lo demás**, y sobre todo el aviso de la raíz de confianza)
(2026-09-02, `DECISIONES #342`, `docs/specs/auth-con-google.md` §18). Encargo del owner: *«0 fricción para el
cliente a la hora de registrarse»*. ▶ **Sin las dos claves configuradas, `/auth/google` y su retorno
responden 404 y no hay botón**: ninguna instalación cambia de conducta hasta que alguien las escriba,
así que esto se puede desplegar sin estrenar nada.
▶ ❗❗❗ **LO QUE HAY QUE SABER SI TOCAS ESTO**: lo que hace creíble lo que Google afirma **no es el
token, es el CANAL** — canje servidor-a-servidor autenticado con nuestro secreto, y **ninguna rama
lee un `id_token` de la petición** (hay caso que manda uno fabricado en la URL de vuelta y se entra
como dice el CANJE). El día que se acepte uno del cliente (One Tap, app nativa), **ese camino
necesita además verificar la FIRMA**: es la mitad que nadie debe añadir sola.
▶ ⚠️⚠️ **Corrige al código que ya existía**: el vínculo **CAE en `revokeAllAccess()`** y sobrevive a
`revokeOtherAccess()` — el criterio del carné (`RGPD-06`). No porque sea una credencial, sino porque
aquélla es la palanca de «me han entrado» y un vínculo plantado por quien te tomó la cuenta
sobreviviría al reset. **Y eso obliga a un ORDEN**: en la toma de una cuenta sin verificar se EXPULSA
antes de escribir el vínculo, o la expulsión se lleva la llave recién dada **sin que falle nada**.
▶ ⚠️ **Dos hallazgos de la MUTACIÓN** (21, muerden 20): el caso del `state` reutilizado **no probaba
nada** (cerraba la sesión entre los dos retornos, así que el reto moría por el `logout`), y las dos
caducidades se medían con `time()`, **invisible para `travel()` y para la auditoría del reloj** — o
sea que su caso no se podía escribir, y por eso no existía. *Una guarda de caducidad que no se puede
hacer caducar en un test no está probada.*
▶ ⚠️ **La spec se contradecía sobre el EQUIPO** y lo resolvió el owner: **sí pueden vincular** (Q5);
§16 y el peligro P9 decían lo contrario y eran texto anterior a esa respuesta — quedan marcados como
caducados en la spec.
▶ **QUEDA**: la **T2** (la pantalla que completa el alta, **en el cajón** por `[DECIDIDO owner]` Q8 —
mide el presupuesto del bundle ANTES de escribir), la **T3** (las CUATRO acciones que hoy exigen
contraseña, desvincular, y el interruptor de marketing con su registro de retirada) y **tu OJO**.
▶ ❗ **Y hace falta el cliente de OAuth de DESARROLLO**: el que me pasaste es el de **PRODUCCIÓN**
(sus URIs son `playjump.es` y `www.playjump.es`, sin localhost), así que **no sirve para probar en
esta máquina** y su secreto no debe vivir aquí. Está fuera del repo, en
`~/secretos-jumpweb/google-oauth-PRODUCCION.json`, y `client_secret_*.json` ya está gitignorado.

📜 **GOOGLE AUTH · LA ENTRADA DEL DISEÑO** (superada por la de arriba: la T1 ya está en el árbol) (2026-09-02,
`docs/specs/auth-con-google.md`). Encargo del owner: *«0 fricción para el cliente a la hora de
registrarse»*. **La T1 está desbloqueada**: nueve de once preguntas cerradas.
▶ ❗❗❗ **LA DECISIÓN QUE MANDA (§4) ES DEL OWNER Y VA CONTRA MI RECOMENDACIÓN INICIAL**: yo diseñé
cero pantallas y él pidió **pantalla intermedia con la exención al 100 %**, porque *«los clientes que
van al parque inician sesión con Google, no aceptan nada, y van directos a la tablet: el empleado
acepta su descargo»*. ▶ **La lección: la fricción no desaparece, se muda al empleado** — y sin
aceptación previa el operador de `#336` deja de **confirmar** para **acreditar**, que es menos prueba.
▶ ⚠️⚠️ **LA REVISIÓN ADVERSARIAL (cinco lentes) ENCONTRÓ OCHO BLOQUEANTES, Y VARIOS ESTABAN EN MI
PROPIA SECCIÓN DE «HECHOS MEDIDOS»** — que abría afirmando que todo se había comprobado contra el
código. Las marcas **✱** de la spec dicen qué decía antes cada cosa. **Léelas antes que el texto.**
▶ Los dos peores, los dos de seguridad y los dos míos: **(1)** la spec no decía en 494 líneas **cómo
se comprueba que lo que dice Google es verdad** — la forma ingenua permite fabricar un token que
afirme cualquier correo; **(2)** la vinculación automática **dejaba heredar la cuenta que un tercero
hubiera creado con tu correo**: `SelfSignup` crea cuentas sin verificar, `/mi-cuenta` solo exige
sesión, y mi guarda miraba el `email_verified` **de Google** y nunca el de la cuenta DESTINO.
▶ **Lo que decidió el owner sobre eso** (Q2): se vincula, se promueve **y se expulsa al ocupante**
(`revokeAllAccess()` + contraseña invalidada). Rechazar era su respuesta literal, pero `users.email`
es UNIQUE —no cabe una segunda cuenta— y **6 de 48 cuentas de producción están sin verificar**: uno
de cada ocho clientes chocaría con un muro.
▶ ⚠️ **`AdminPanelProvider` no declara `authGuard`**, o sea que la sesión de Google **es la del
panel**: dejar esa pregunta «abierta» era contestarla que sí. El owner decidió que el equipo **sí**
puede vincular, y eso hace **obligatorio** el aviso por correo de la Q3.
▶ ⚠️ **Retiré mi propia corrección a este documento**: dije que las claves iban a `settings` y no al
`.env` «como Turnstile», y resultó que el repo escribe que un secreto en `settings` **se vuelca en
cada backup**. El owner eligió `settings` igualmente, con ese coste asumido y escrito.
▶ **Destapa DOS incumplimientos PREEXISTENTES** que la T3 cierra: las acciones que exigen contraseña
son **CUATRO** y una cuenta de Google no puede ninguna (art. 12.2), y el consentimiento de marketing
**no se puede retirar** ni deja constancia — `consents` no tiene columna de revocación (art. 7.3).
▶ **QUEDA DEL OWNER**: su ✅ final, dejar el cliente de OAuth listo (**§10.1**, y ojo a la URI de
redirección: la ruta completa `/auth/google/callback`, no el origen), y las dos preguntas de la T2
(presupuesto del cajón y texto del botón).

✅ **LA COLUMNA DEL MENÚ YA NO ESTÁ VACÍA — Y SUS ZONAS SALEN DE LA BD** (2026-09-02,
`DECISIONES #341`, `[DECIDIDO owner]`: «las que sean, que no esté vacío»). Medido: con `/servicios`
en mantenimiento **ninguno** de los siete destinos traía foto, así que la vista previa caía al fondo
rayado en todos.
▶ ❗❗ **Y midiendo apareció algo más gordo que las imágenes**: los ítems «fijos» del menú decían
**«Zona Kids»**, **«Zona Jump»**, **«Trampolines, foam, tirolinas»** y **«Murcia · cómo llegar»**,
escritos a mano en los ficheros de idioma del **PRODUCTO**. Una instalación con otras zonas veía en
su menú **las de otro parque**: la misma fuga que `#302` y `#325`.
▶ **Dos caminos y hacen falta los dos**: las ZONAS salen de la BD (`navZones()`, mismo criterio que
las pestañas de la portada) — con eso **`zones.image` recupera un consumidor**, que lo había perdido
en `#302` —; y un **RESPALDO por instalación** (`public/img/client-menu.webp`, el CUARTO hueco tras
logotipo, icono y kit) para Entradas, Cumpleaños, Atracciones y Ubicación, que **no tienen ninguna
imagen suya en el modelo** y asociarles una habría deshecho lo anterior.
▶ ⚠️ **Sin fichero no se pinta nada** (el hueco falla hacia invisible) y **la foto propia gana al
respaldo**, con caso para las dos cosas. `INSTALACION-CLIENTE.md` §4.f.
▶ ⚠️⚠️ **Una guarda existente FIJABA la fuga**: `ArmazonContractTest` aseveraba los literales «Zona
Kids»/«Zona Jump». Re-apuntada a la BD y **no más débil que la que sustituye** (`#295`): ahora fija
las zonas **en el orden que manda su `position`**, que antes no lo miraba nadie.
▶ ⚠️ **Dos trampas pagadas**: `lang/*/landing.php` dentro de un docblock **lo CIERRA** (el `*/` del
comodín) y tumbó la home con un 500 que señalaba a otra línea; y **lo que `@js()` emite no es JSON**
—comillas como `"` y barras con dos capas de escapado—, así que el localizador del test devolvía
`null` y **todos los casos habrían aseverado sobre una lista vacía**: lo cazó su control.
▶ **QUEDA TU OJO, y el fichero es TUYO**: para probarlo en local se instaló `pjp-149.webp` como
respaldo — elige la foto que quieras y súbela como `client-menu.webp`.

✅ **EL CAJÓN RELEE SU CONTEXTO AL VOLVER A LA PESTAÑA** (2026-09-02, `DECISIONES #340`; cierra la
ficha de `DEUDA.md` que abrió el owner el día del lanzamiento). Verificar el correo en otra pestaña y
volver ya no deja el aviso viejo. ▶ **Lo que faltaba no era el mecanismo: era el disparador.**
`accountContext.refresh()` ya existía y ya estaba endurecido (guarda de concurrencia, 401 que vacía);
solo lo llamaban el login sin recarga y la zona de privacidad.
▶ Tres decisiones, ninguna de estilo: **solo con sesión** (sin esa puerta, cada visitante anónimo
sondearía `/me/account-context` en cada cambio de pestaña) · **`visibilitychange` Y `focus`**, porque
ninguno cubre solo todos los casos · **intervalo mínimo de 10 s**, porque alternar de pestaña es
barato y el endpoint no.
▶ ⚠️ **Se comprobó ANTES de escribir nada que refrescar REPINTA**: el aviso es un `computed` sobre el
store. Sin eso habría sido `#333` otra vez.
▶ ⚠️⚠️ **El presupuesto del cajón mordió por 50 BYTES** y se podó en vez de subir el techo: el
`stop()` que devolvía el módulo **no tenía consumidor** (el motor no se desmonta) — la regla de `#287`.
Al retirarlo apareció una rama sin cubrir (`focus` con la pestaña aún oculta), añadida y mutada.
▶ **Queda tu OJO**: verificar el correo en otra pestaña y volver a la primera.

❗❗❗ **UN SOLO NOMBRE: «DESCARGO DE RESPONSABILIDAD» EN TODA LA INTERFAZ** (2026-09-02,
`DECISIONES #339`, `[DECIDIDO owner]`). No eran dos formas, eran **CINCO** —«exención de
responsabilidad (waiver)» · «Descargo de responsabilidad (waiver)» · «Desc**a**rga de
responsabilidad» · «waiver» a pelo · un enlace legal del pie titulado «Waiver»— y **tres le llegaban
al cliente**. 91 cadenas reescritas (61 es · 27 en · 2 fr), medidas sobre los VALORES de `lang/`: un
`grep` crudo daba 108 en español porque contaba las CLAVES.
▶ **Cada idioma con su término**: es «descargo de responsabilidad» · en «liability waiver» (ahí
«waiver» ES la palabra natural) · fr «décharge de responsabilité».
▶ ⚠️⚠️ **EL CÓDIGO NO SE RENOMBRA Y ESO ES LA DECISIÓN, NO UN ATAJO**: `waiver` es el vocabulario del
dominio —tabla, servicios, slug, claves de i18n y sobre todo los **códigos de error de la API, que son
CONTRATO**—, y `WaiverSigner` está en el `CRITICAL_RE`. El mapeo vive en **`GLOSARIO.md`**, que es
para lo que existe.
▶ ⚠️ **La trampa que descartó el `sed`**: «exención» es femenino y «descargo» masculino — un
reemplazo ciego deja «la descargo firmada». Se reescribieron con la concordancia a mano.
▶ **Guarda `WaiverWordingIsOneTermTest`** (valores, nunca claves), con **tres mutaciones**. ⚠️⚠️ La
segunda **no mordió y el débil era el instrumento**: muté dos ficheros de francés y el término vive en
cuatro. *Antes de aflojar una guarda, comprueba si tu mutación era completa.*
▶ ❗ **EL TÍTULO DEL DOCUMENTO PUBLICADO NO SE TOCA TODAVÍA, Y ESTÁ DECIDIDO CON EL DATO DELANTE**:
`body_hash` incluye el título, así que corregirlo obliga a publicar versión nueva y deja `outdated`
las firmas hechas — medido en producción: **54 firmas · 48 clientes · 6 aceptaciones retenidas**. Y al
medirlo apareció lo que cambió la decisión: **los cuerpos EN y FR son el texto español literal** (11
secciones idénticas; solo los títulos están traducidos), así que la traducción pendiente exigirá su
propia republicación. `[DECIDIDO owner]`: **una sola vez, con la traducción**. Se hace **desde el
panel** (`EditPage` ya publica versiones legales): editar la página `waiver` y pulsar publicar.
▶ ⚠️ **Riesgo anotado, no tarea**: hoy un cliente extranjero firma un documento titulado «Liability
release» cuyo contenido está en español.
▶ ✅ **DESPLEGADO Y VERIFICADO EN LA WEB REAL** (`a393bd2`, acotado igual que `#338`): el dry-run dijo
que solo cambiaban **los 11 ficheros de idioma**, y `curl https://playjump.es/` confirma el pie legal
en «Descargo de responsabilidad». ⚠️ Quedan **3** apariciones de «waiver» en el HTML de la portada y
**son correctas**: es la CLAVE `accept_waiver` dentro del payload del cajón, cuyo valor ya dice
«He leído y acepto el descargo de responsabilidad». *Un `grep` de «waiver» sobre el HTML no distingue
la clave del texto: hay que mirar qué es cada una.*

❗❗❗ **PRODUCCIÓN · EL GESTO DE LA PUERTA DABA 500, Y ERAN TRES DEFECTOS EN UNA LÍNEA** (2026-09-02,
`DECISIONES #338`). Lo encontró el owner probando la pantalla. `ValidarRegistro::declareWaiver()`
recomponía la ficha con `GateProfile::for($customer)` —**un argumento de tres**—, y detrás había otros
dos que el 500 tapaba: la ficha perdía sus tres claves de PRESENTACIÓN (`via`, `expires_at`,
`ttl_minutes`; **eso no falla, se pinta vacío**) y seguía diciendo que había aceptación RETENIDA tras
firmarla, porque el servicio limpia esas columnas sobre una fila que **bloquea aparte** y el modelo del
componente se queda obsoleto.
▶ ❗ **DATO OPERATIVO: la firma se escribe ANTES del 500.** Toda exención declarada en la puerta antes
de este arreglo **está firmada de verdad** aunque el operador viera un error — se comprueban en
`audit_logs` con `action = 'puerta.waiver_declared'`.
▶ **Por qué no lo vio la suite**: `grep -rn "declareWaiver" tests/` no devolvía **nada**. Los siete
casos de `DeclareWaiverAtGateTest` conducen el SERVICIO; ninguno pasaba por el componente.
*Que el dominio haga lo correcto no es que la pantalla sepa pedírselo* — el gemelo de `#333` y `#263`.
▶ Arreglado con fuente única (`composeProfile()`), dos guardas nacidas rojas y **mutación con control**.
De paso se retira `TmpProbeTest.php`, sonda de `#217` que llevaba cinco días en la suite sin probar nada.
▶ ❗❗❗ **DESPLEGADO EL 2026-09-02, PERO ACOTADO — Y PRODUCCIÓN CORRE UN COMMIT QUE NO ESTÁ EN `main`:**
**`8af8d52`** = `9d01dae` (lo que ya corría) **+ solo este arreglo**, desde la rama `deploy/fix-338`.
`[DECIDIDO owner]` con el alcance delante: desplegar `main` habría subido además **la T3 entera de
`#337`** (seis superficies, 100 entradas en el `rsync`) a un parque con clientes reales **antes de que
su autor la verifique en navegador**. Un 500 en la puerta no espera; una feature sin revisar sí.
▶ **Comprobado con el dry-run, no supuesto**: de las 95 entradas solo **DOS** cambiaban de contenido
—`ValidarRegistro.php` y un CSS recién compilado—; el resto eran diferencias de marca de tiempo. Sin
migraciones de por medio (`Nothing to migrate`), así que el esquema no diverge.
▶ **LO QUE ESTO OBLIGA**: el siguiente `deploy.sh` desde `main` sube la T3 igualmente. **Quien
despliegue después tiene que saber que va a estrenar `#337` en producción** — no es un despliegue de
rutina. La rama `deploy/fix-338` se puede borrar en cuanto eso ocurra.

▶ ❗❗ **LO MEDIDO PARA QUIEN SIGA CON LA PUERTA O LA HOJA DE SALA** (2026-09-02, no lo repitas):
  1. **Los MENORES en la puerta: la ficha de `DEUDA.md` parte de una premisa FALSA.** Dice que «un
     titular con menores declarados tiene una aceptación retenida por cada uno»: **no existe tal cosa**
     — `dependents` no tiene ninguna columna `waiver_pending_*`; la aceptación retenida vive **solo** en
     `users`. Y su escape («ésas siguen pidiendo la tablet») **tampoco funciona**: la tablet es el mismo
     `POST /me/dependents/{id}/waiver` desde la misma cuenta, y devuelve el mismo 409.
     ▶ **Tres sondas sobre la BD real, con control**: titular sin verificar firmando por su menor →
     `WaiverEmailUnverifiedException`; **control** con el correo verificado → firma; **control** sin
     verificar pero con `declaredBy` = operador → **firma**. O sea que **el dominio YA lo permite**
     (`WaiverSigner:89-90`): lo que falta no es mecanismo, es la superficie y la decisión de producto.
     ▶ **El hueco real, dicho con precisión**: un padre sin el correo verificado **no puede ni aceptar**
     por sus hijos. Desde `#336` su propia exención sí se cierra en la puerta → **pasa él y no pasan sus
     hijos, con él delante**. `[owner]`: *«el cliente no le pasa la tablet, solo le avisa; ya la ha
     leído y aceptado — lo único que no podemos verificar es que sea una persona»*.
  2. **Los MENÚS en la hoja impresa: el dato ya existe y está bien guardado.** El contenido de cada menú
     vive en `ticket_types.features` (i18n, y es donde `#327` dijo que debía ir); la hoja imprime solo
     `{cantidad} × {nombre}` (`reservation-slip.blade.php`, el bloque de complementos) porque
     `ReservationSlip::addons()` no lo devuelve. El patrón de lectura ya está en dos sitios
     (`AddonResolver`, `LandingAddonPresenter`): un tercero pide subirlo a `TicketType`.
  3. **El «MONITOR» no existe en ninguna parte** — ni producto del catálogo (los 18 medidos: hay Tarta,
     Menús, Combos, Cubos, Calcetines; **no hay Monitor**), ni campo de `event_fields`/`guest_fields`, ni
     columna. `[DECIDIDO owner, 2026-09-02]`: **es un HUECO EN BLANCO en la hoja para escribirlo a
     mano**, el mismo criterio que las filas de niños sin datos. Ni BD ni panel.

✅ **CARRIL P3 · EL JUSTIFICANTE DE UN MENOR INVITADO («waiver offshore») — SPEC + T1 + T2 + T3 +
LA ACTIVACIÓN (T5→T8) EN EL ÁRBOL** (2026-09-01, `DECISIONES #328`, `#335`, `#337` y **`#400`**).
❗❗❗ **`#400` — EL OWNER PROBÓ LO CONSTRUIDO Y NO HABÍA PUERTA POR LA QUE ENTRAR**: *«En el panel del
cliente no me sale nada del enlace. Ni de los que han firmado o no.»* El enlace tenía **tres
consumidores en todo el repo y ninguno lo OFRECÍA**, y el peor era un **huevo y una gallina**: el
botón «Copiar enlace» vivía DENTRO de una sección `visible(countFor > 0)`, así que solo aparecía
cuando ya había un justificante firmado — y para que hubiera uno hacía falta el enlace. *Una condición
de visibilidad escrita para lo que se LEE acabó escondiendo lo que se HACE.*
▶ **La activación la decide ahora el PRODUCTO** (`[DECIDIDO owner]`, data-driven): `none` ·
`optional` (casilla en el paso de la hora, junto al selector de menores a cargo) · `required` (la
excursión de colegio: sin casilla, y **lo marca el SERVIDOR** — si saliera del navegador se compraría
sin justificantes quitando un `input` del DOM). Al pagar sale un correo con el enlace, y el panel gana
la sección **siempre visible**, un icono por línea y «Enviárselo al cliente».
⚠️⚠️ **«Se ofrece» NO es «se permite»**: quien tenga el enlace de un pedido pagado puede firmar
SIEMPRE, marcado o no — el caso 2 del owner es *«un cliente que no sabía que se necesita
justificante»*, y cerrar esa puerta lo mataría.
⚠️⚠️ **El «caso 3» (justificante sin reserva) NO se construye porque la premisa no se cumple**:
`CreateManualOrderPage` **exige cliente**, así que toda venta de mostrador ya produce un pedido con
responsable. Reabrirlo costaría `order_id` nullable, **la puerta no lo encontraría** (compone desde
las reservas de HOY) y la caducidad se quedaría sin ancla.
⚠️ **«¿50 justificantes y 40 entradas?» medido con rollback**: no se borra ninguno (son firmas) y
**nadie lo decía**. Ahora el panel y la hoja pintan «N justificantes · M plazas» con aviso, y el
mensaje que mentía al padre está corregido. **El TOPE no se toca** (`SEC-04`).
⚠️⚠️ **La trampa que casi lo entierra**: `Cart::sanitize()` es una LISTA BLANCA y el campo se habría
caído ahí **en silencio** camino de `OrderCreator`. Tiene caso propio, y hay una segunda costura igual
en el pedido manual.
❗❗❗ **`#401` (2026-09-02) — Y PROBÁNDOLO ENCONTRÓ EL FALLO DE FONDO: el justificante colgaba del
PEDIDO y **un pedido puede tener dos visitas**. Su frase: *«1 justificante es por reserva no por
pedido, creo que ahí tenemos el fallo»*. Medido sobre `R-LUKFD2` (excursión el 07/09 + entrada el
03/09): la hoja del padre decía **«Días de la visita 03/09 · 07/09»**, llegaba **un solo correo**, la
capacidad sumaba las dos líneas (**81 plazas**) y «un niño, un papel» impedía autorizar al mismo niño
para dos visitas del mismo pedido. **Cuatro síntomas, una raíz.**
▶ `order_id` → **`order_item_id`**, ruta `/autorizacion/{reservation}`, contrato
`AuthorizableReservation(s)` con **producto, día y hora**, **un correo por reserva marcada**, y el
enlace **en «Mis reservas»** — que es donde el owner lo buscó (*«sigo sin ver el enlace para copiar en
mis reservas»*).
⚠️⚠️ **Las PLAZAS LIBRES** (`GuardianPlaces`) = cantidad − menores a cargo asignados − firmados. **No
contradice a §4.10**: aquélla prohíbe inventar «3 de 100»; una plaza asignada a un menor a cargo **ya
tiene dueño**. Los adultos no se restan (cota superior a propósito).
⚠️ **El embudo, más sutil** (`[DECIDIDO owner]`): «¿Quiénes vienen?» es un `<details>` **nativo**
plegado, y la casilla **no se puede marcar sin plazas libres** — el caso que él encontró comprando una
entrada y asignándosela a su hija.
⚠️ **Una premisa suya NO se cumplía**: la excursión que creía «obligatorio» tenía
`guardian_authorization = none`. *Antes de arreglar un síntoma, comprobar que la configuración que se
le supone existe de verdad.*
▶ **CINCO ESCENARIOS SEMBRADOS en su cuenta** (`PRUEBA-J1`…`J5`, guion en `VERIFICACION-E2E-CAJON.md`
§5.septies bloque 8) y **queda su OJO**. También sigue pendiente la casilla en el paso de CESTA.
❗❗❗ **`#402` — Y MIRÁNDOLO ENCONTRÓ DOS MÁS, los dos MUDOS**: una clave de idioma pintada en crudo
(`admin.orders.guest_minors.assigned`, que se usó sin declararse y **solo se pinta con menores a cargo
asignados**, un caso que ninguna guarda montaba) y, la gorda, **que el enlace NUNCA llegó a pintarse**:
`api.js` devuelve el SOBRE (`{data:{…}}`) y el store lo guardaba tal cual, así que el panel leía
`.reservations` sobre un objeto que solo tiene `data`. **Medido: `200` en la pestaña de red y
`paneles: 0` en el DOM.**
▶ *Un 200 no dice que el dato haya llegado a donde se lee.* Las DOS veces que el owner dijo «no me
sale nada» diagnostiqué una causa real que **no era la única**; la que faltaba solo se ve abriendo el
navegador y contando nodos. Guardas nuevas: tres de `node --test` sobre el store y **una general** —
que ningún identificador de grupo de idioma aparezca en el HTML de la ficha—, **vista morder**.
⚠️ De la sonda salió además la tercera condición del enlace: **sin plazas libres tampoco se ofrece**.
❗❗ **`#403` — y una tercera pasada del OJO del owner**: el botón «Completa el formulario *producto*»
**se salía** con un nombre largo (`.btn` es `white-space: nowrap` y el botón es de ancho completo;
medido: cabía con **0 px de margen** y un nombre largo pedía 418 en 308). `[DECIDIDO owner]`: **fuera
el nombre del producto** —está tres líneas más arriba— y el botón pasa a `white-space: normal`, que
cierra la CLASE y no solo el caso. Y entra el gesto de **compartir o copiar** el enlace: en un teléfono
abre la hoja del sistema (WhatsApp) y en un escritorio copia.
⚠️⚠️ **Cerrar la hoja de compartir NO es un fallo** (`AbortError`) y **no se copia** lo que alguien
decidió no mandar. ⚠️ El dibujo es la geometría de `<x-icons.share>` **copiada, no inventada**:
`SidebarIconParityTest` paró el primer intento porque el cajón tiene `DRAWER_OWN` **vacía**.
⚠️ Se pinta a **24**, su talla de trabajo: a 18 los puntos se comían los conectores y el owner lo vio
roto. *Un icono de rejilla 24 no se escala: se pinta a 24 y se le da aire con el relleno del botón.* ▶ **La T3 pone las SEIS superficies**:
puerta, hoja de sala, ficha del pedido, PDF, correo de copia con el PDF adjunto, y la cuenta del
responsable en el cajón. ▶ **LA T4 YA TIENE GUION ESCRITO Y EL ESCENARIO SEMBRADO**:
`VERIFICACION-E2E-CAJON.md` **§5.septies** — seis bloques con sus casillas, los dos pedidos de prueba
(`PRUEBA-WAIVER` para el formulario/panel/cuenta y `PRUEBA-PUERTA` para la puerta), el cliente
`colegio-prueba@jumpweb.test` / `prueba1234` y el comando de limpieza. **Queda solo el OJO del
owner.**
⚠️⚠️ **LA AUDITORÍA DEL RELOJ CAZÓ DIEZ ROJOS DE ESTA TANDA AL CERRAR, y no los veía nadie**:
`GuestMinorSurfacesTest` siembra una franja de HOY que acaba a las 23:00, y **cerca de medianoche esa
visita ya ha pasado** —el dominio se niega a autorizar sobre una visita terminada—. Verdes a
cualquier hora normal, **rojos a las 21:59:30 de Madrid y a las 23:59:30 UTC**. Arreglado congelando
el reloj (`FROZEN_NOW`), **con control**: sin congelar, 10 de 11 rojos; con él, 11 verdes en las dos
fronteras. ▶ **Y un ONCEAVO rojo que NO era del reloj**: `GuestMinorIsolationTest` aseveraba que la
cadena `'Carlos'` no está en el HTML del panel, contra un HTML que lleva un nombre de
`User::factory()` — y en uno de los diez pases la factoría sacó uno **con «Carlos» dentro**. *Un
nombre aleatorio enfrentado a una aserción por SUBCADENA es una moneda al aire disfrazada de test.*
Nombre fijado y aserción por nombre COMPLETO. ✅ **La auditoría acabó verde en las diez fronteras.** ▶ *`audit-clock.sh` NO está en el pre-push: si no se corre al cerrar, esto se va a `main`.*
⚠️⚠️ **Y la trampa que casi lo entierra**: la auditoría se lanzó con `| tail -8`, que **cortó la tabla
de fronteras** y dejó solo dos filas verdes bajo un veredicto ✗ — y el `exit 0` que se leyó era **el
de `tail`**. *Un filtro de salida puede esconder justo la evidencia que buscas.*

⚠️⚠️ **Tres cosas de ese entorno, MEDIDAS, que hay que saber antes de probar**: Turnstile está activo
**con las claves de PRUEBA de Cloudflare** (`1x00…`, las que siempre pasan) así que **no bloquea en
local** —verificado en navegador: el widget produce token y la firma entra—; la cola es `sync`, así
que el correo sale al instante a Mailpit (`:8028`) **con el PDF adjunto**; y **hacen falta DOS
pedidos** porque la puerta solo enseña las reservas de HOY y el formulario se CIERRA cuando la visita
ya pasó. ⚠️ **Su decisión de fondo**: `GuardianRoster` tiene **DOS formas y no una con un filtro**, así
que «el responsable no ve a los otros padres» lo impone el TIPO, no la disciplina de cada plantilla.
⚠️⚠️ **Tres guardas de arquitectura cazaron tres defectos míos** —un componente hablando con la API
(`CE-6`, regla que yo mismo había citado), clases de CSS sin regla y la lista de claves del montaje—
y **los dos presupuestos del cajón se podaron ANTES de subirlos** (chunk 262 → 263 KiB, textos
9.200 → 9.400 B), con la nota de que esos bytes los paga cada página con sesión para una feature
rara. Spec: **`docs/specs/waiver-por-reserva.md`**
(revisada de forma adversarial, nueve `[DECIDIDO owner]`). **T1 (el dominio) y T2 (la pantalla
pública) ejecutadas y verificadas en navegador real**; quedan **T3** (cuenta del responsable,
`ViewOrder`, hoja de sala, puerta, PDF y el correo de copia) y **T4** (el OJO del owner).
▶ ❗❗ **EL DEFECTO DE LA T2, por si alguien toca un anti-bot**: copié el silencio de `/contacto` y la
pantalla **decía «Listo» sin escribir nada** cuando Turnstile no producía token —lo destapó la sonda
de NAVEGADOR, no la suite—. Un padre creía tener firmada la autorización de su hijo y se enteraba en
la puerta. **Ahora el honeypot calla y Turnstile lo DICE**, y la asimetría tiene caso propio.
▶ ⚠️ **Hasta la T3, quien firma NO recibe copia** (§4.15 la promete y su PDF es de esa tanda) y el
responsable no tiene por dónde repartir el enlace salvo generándolo a mano.
▶ **Si trabajas en otra máquina, no toques `WaiverSignature`, `WaiverSigner`, `WaiverChain`,
`GuardianAuthorization`, `waiver_signatures`, `guardian_authorizations` ni `PurgeCustomerData` sin
avisar aquí.** El resto del repo (landing, panel, libro, mixtos) está libre.
▶ ❗❗ **La revisión encontró DOS BLOQUEANTES y una afirmación del diseño que era FALSA** («no cambia
una línea del mecanismo»): la clave de sujeto estaba **cableada a `subject_id` en TRES sitios** y con
`NULL` los tres se cruzaban — la idempotencia de `WaiverSigner` devolvía la firma de OTRO menor (**el
segundo padre se quedaba sin justificante, mudo**) y `WaiverChain` declaraba ROTA una cadena sana. Hoy
la clave vive en UN sitio (`WaiverSignature::chainKey()`), y las dos guardas se escribieron **viéndolas
fallar**.
▶ ❗ **Y una FUGA cerrada**: con el responsable en `user_id`, `GET /me/waiver` devolvía el nombre del
hijo de otra familia **y servía su PDF**. `User::waiverSignatures()` es ahora **fail-closed**.
▶ **TRES fichas de `DEUDA.md` que NO son de esta feature**: (1) `declared_by_user_id` es `nullOnDelete`
**y está dentro del hash** → borrar al operador pone `verifyHash()` en `false` sobre una firma que
nadie tocó (Media, medido dos veces); (2) **`subject_name` era `varchar(120)` con el firmador cortando
a 255** → `1406` en MySQL y **verde en SQLite**, vivo desde `#198` (la migración lo sube a 255);
(3) la IP/UA de un tercero bajo el `target` del titular en `audit_logs` (Baja).
▶ ❗ **LO QUE ES DEL OWNER**: el ✅ a la spec y **el plazo de conservación** — medido, hoy
`waiver.retention_months` y `dependent_retention_months` valen `NULL`, así que **no se poda nada**, y
aquí lo que no se podaría son datos de menores de terceros.

> Documento CORTO (carga obligatoria al arrancar). Solo «dónde estamos / qué sigue».
> **El «qué pasó» de cada paso vive en `00-REFACTOR.md` (tracker) y `DECISIONES.md` (el porqué):
> aquí solo se enlaza.** Última actualización: **2026-09-01 (noche) — LA PRIMERA JORNADA DE
> OPERACIÓN REAL del 2.º cliente, con 19 clientes dentro. `#329`→`#334` y `#336`, todo DESPLEGADO
> y verificado en `https://playjump.es` (commit `9d01dae`).**
>
> ❗❗❗ **POR DÓNDE SE RETOMA — LEE ESTO Y NADA MÁS DE ESTE BLOQUE.**
>   1. ✅ **GOOGLE AUTH YA TIENE SPEC, DISEÑADA Y REVISADA: `docs/specs/auth-con-google.md`**
>      (2026-09-02). **Lo siguiente es la T1, y está DESBLOQUEADA** — nueve de las once preguntas
>      cerradas con `[DECIDIDO owner]`. **Empieza por su §4** (la decisión que manda) y **por las
>      marcas ✱**, que corrigen afirmaciones que la primera versión daba por medidas y eran falsas.
>      ⚠️⚠️ **La revisión adversarial encontró OCHO bloqueantes y DOS eran agujeros de seguridad**:
>      la raíz de confianza no estaba escrita (cómo se valida lo que Google afirma y dónde vive el
>      perfil entre las dos peticiones), y la vinculación automática **dejaba heredar la cuenta que
>      un tercero había creado con tu correo**. Los dos están cerrados en §6.3 y §5.2.
>      ⚠️ **`AdminPanelProvider` no declara `authGuard`**: la sesión de Google **es** la del panel.
>      El owner decidió que el equipo SÍ puede vincular, y eso hace **obligatorio** el aviso por
>      correo (§6.6).
>      ⚠️ **`ESTADO.md` decía `.env` para las claves; van a `settings`** (`[DECIDIDO owner]` Q10),
>      con el argumento descartado escrito al lado: un secreto ahí se vuelca en cada backup.
>      ▶ **§10.1 es lo que el owner tiene que dejar listo en Google Cloud Console**, incluida la
>      trampa que falla en el primer intento: la URI de redirección es
>      **`https://playjump.es/auth/google/callback`**, la ruta completa, no el origen a secas.
>      ▶ **Destapa dos incumplimientos PREEXISTENTES** que la T3 cierra: una cuenta de Google no
>      podría usar **ninguna** de las cuatro acciones que exigen contraseña (art. 12.2), y el
>      consentimiento de marketing **no se puede retirar** ni deja constancia (art. 7.3).
>   2. **EL PRODUCTO DE EXCURSIONES NO EXISTE EN PRODUCCIÓN**, y era la tarea con la que se abrió la
>      sesión. El MECANISMO sí está desplegado (`#322` horario por zona · `#324` precio por tramo),
>      pero **la BD del cliente no tiene ni la zona ni los productos** — medido: `zones.slug =
>      'excursiones'` no existe y hay **CERO** productos con tramos. Es **DATO, no código**: la zona
>      con su horario propio y su tope de un grupo por franja; los dos productos como **`pack`**
>      (2 h y 3 h, mín. 30, máx. 100); sus tramos 30→15/17 · 70→13/15 · 100→12/14; y la señal.
>      ▶ **El viernes YA está en la tarifa `special`** (`weekdays = [5,6,0]`): eso no hay que tocarlo.
>   3. Lo del owner que sigue abierto: **monitor y menús servidos** en la hoja impresa —diagnosticado
>      el 2026-09-02, ver el bloque de `#338`: los menús ya tienen su contenido en `ticket_types.features`
>      y el «monitor» **no existe en ninguna parte**, así que va como hueco en blanco `[DECIDIDO owner]`—,
>      los **menores** en la declaración de puerta (con las tres sondas ya medidas, ver `DEUDA.md`),
>      y el OJO sobre los TPV. ✅ **Las imágenes del menú (`#341`) y el refresco del contexto (`#340`)
>      YA ESTÁN**; de las primeras queda que subas tu foto como `client-menu.webp`.
>
> ⚠️⚠️ **CUATRO COLISIONES DE NUMERACIÓN EN UNA JORNADA, y la regla actual NO BASTA.** Se numeraba
> mirando el remoto al ABRIR la tanda; las cuatro se produjeron al CERRARLA, con el otro agente
> empujando entre medias. La peor dejó **46 citas de código apuntando a una decisión ajena**, porque
> una renumeración anterior tocó el documento y olvidó los comentarios.
> ▶ **La regla completa: mirar el remoto al numerar Y VOLVER A MIRARLO AL CERRAR**, y si hay que
> renumerar, hacerlo con **mapa explícito** — nunca un desplazamiento mecánico, que arrastra las citas
> del otro agente. Sus carriles y los de aquí **no se han solapado en un solo fichero PHP**.
>
> ⚠️ **DOS INCIDENTES DE PRODUCCIÓN de la jornada, los dos cerrados:**
>   · **El correo saliente se atascó** (17 mensajes, clientes sin poder verificarse). **No era la
>     aplicación** —cola de Laravel vacía y sin fallos—: era el **límite por hora y por sitio de
>     Enhance**. Lo levantó el owner y la cola drenó entera. ▶ Si vuelve a pasar, el primer sitio a
>     mirar es `mailq` en el servidor, no el código.
>   · **Los dos packs de cumpleaños no tenían esquemas de campos** (`event_fields` y `guest_fields`
>     vacíos desde que se crearon a mano), así que dos fiestas ya pagadas no pidieron el parte y el
>     suplemento de fiesta mixta estaba INERTE — la edad se localiza por el TIPO de campo, y los
>     valores por defecto del producto no traen ninguno. Corregido en producción con la edad
>     OBLIGATORIA. ⚠️ Las dos reservas ya pagadas quedaron protegidas por su **sello sin familia**: no
>     se les puede mover el dinero, que es justo para lo que el sello existe.
>
> ⚠️ **DISTANCIA SANA** (sigue vigente, `#325`): la instalación es un cliente aparte del producto —
> lo suyo vive en su BD, su `.env` y ficheros gitignorados; al repo solo entran mecanismos.
>
> Documento CORTO (carga obligatoria al arrancar). Solo «dónde estamos / qué sigue».
> **El «qué pasó» de cada paso vive en `00-REFACTOR.md` (tracker) y `DECISIONES.md` (el porqué):
> aquí solo se enlaza.** Última actualización: **2026-09-01 (noche) — CINCO PUNTOS DE PRODUCCIÓN
> CERRADOS (`#329` → `#334` y `#336`)**:
>   0000. ⚠️⚠️ **`#333` — UN TEXTO QUE NO LLEGA NO FALLA, SE QUEDA MUDO.** El prop `account` **ES YA**
>      el grupo `account`, así que la clave se pide `verify.resend`, **NO** `account.verify.resend`;
>      `t()` devuelve `''` sin avisar y el aviso salió vacío y el botón sin rótulo. ❗ **Mi
>      comprobación de `#332` miró el PAYLOAD y no el RENDER** — *que el texto llegue no es que se
>      pinte*. Guarda nueva `SidebarTranslationKeysExistTest`, contra el payload REAL (caza también
>      que la poda del montaje deje una clave fuera) y nacida roja con los dos fallos.
>   000. **El alta abre el SPA en la cuenta, con el aviso completo dentro** (`#332`, tres correcciones
>      del owner probándolo en navegador): `verified` **salió** de las rutas del área —con él puesto,
>      registrarse rebotaba a `/email/verificar`, fuera del cajón—; el aviso es un **bloque** (mensaje
>      + botón + reenvíos restantes + límite, y **debajo** la exención); y **de «Mi cuenta» ya se puede
>      salir** (el botón del bloque `.acct` se colapsa ahí dentro: altura 0, medido en V4).
>   0''. …y los cuatro de antes:
>   00. **El alta suelta ENTRA a la cuenta** (`#331`, `[DECIDIDO owner]`) y ahí le espera **UN solo
>      aviso con los dos estados**: verifica tu correo (+ «tu exención quedará firmada cuando lo
>      hagas» si la aceptó), con el botón de reenviar. ❗ El motivo no es estético: **sin sesión no
>      hay QR, y el QR identifica en la puerta**. ⚠️ Entrar no es verificar. ▶ **LO SIGUIENTE DE ESTE
>      CARRIL: que la PUERTA cierre la firma** — y la corrección que hay que respetar al construirlo
>      es que **el operador acredita a la PERSONA, no al BUZÓN** (marcar el correo verificado sería un
>      vector de robo de cuenta vía recuperación de contraseña). Después, **Google auth**, que vacía el
>      caso para la mayoría.
>   0'. …y los tres de antes:
>   0. **La antelación mínima ya no ata al mostrador** (`#330`, `[DECIDIDO owner]`): es una regla del
>      AUTOSERVICIO, para quien compra sin nadie que juzgue el caso. Sin interruptor ni permiso, al
>      revés que el mínimo del pack. ▶ Nace **`Booking\Contracts\CounterSale`**, que dice QUIÉN vende
>      y viaja de la página a `OrderCreator` **y** `SlotOffer`: ya iban dos excepciones de la misma
>      familia en una tanda. ⚠️ Se imponía en DOS sitios y el segundo es **el suelo del calendario del
>      panel**: sin él, el operador elegía la hora y no llegaba al día.
>   … y los dos primeros (`#329`), los dos «el producto lo sabía y no lo decía»:
>   1. **El operador ya puede vender un pack por debajo de su mínimo AL CREAR** un pedido manual (el
>      gemelo de D7, que solo cubría la edición). ❗ El mínimo se imponía en **CUATRO** sitios y el
>      cuarto —`SlotOffer`, que descarta la franja entera si el hueco no llega al mínimo— **no avisa
>      al fallar**: habría funcionado en la franja vacía y fallado en la compartida. Precio por debajo
>      de la escala: **el primer tramo** (`[DECIDIDO owner]`). Y de paso, un defecto de dinero
>      PREEXISTENTE: la previsualización del panel presupuestaba **sin la cantidad** (140,00 € de
>      desfase en una línea de 70).
>   2. **El aviso de la exención dejó de mentir.** Entre aceptar la casilla en el alta y verificar el
>      correo, el cajón decía «tienes pendiente la exención» con un botón que solo podía devolver 409.
>      Ahora dice la verdad y **ofrece reenviar la verificación** (`POST /me/email/resend`).
>      ⛔ **NO se cerró el área a los no verificados** (`[owner]`: sin entrar no hay QR, y sin QR no
>      hay identificación en la puerta) — ficha en `DEUDA.md` con la salida propuesta: **que la PUERTA
>      firme**, como tercer suceso junto al enlace y el pago.
> ▶ **Lo que sigue pendiente del owner de esta tanda**: (a) decidir si se construye lo de «la puerta
> firma»; (b) su ✅ en navegador sobre el interruptor del pedido manual. ▶ **Y sigue en pie lo de
> antes**: imágenes en la columna derecha del menú, el refresco del contexto al volver a la pestaña,
> y el OJO sobre los TPV.
> ⚠️⚠️ **TERCERA colisión de numeración con el otro agente, y la peor de las tres**: estas cinco
> entradas nacieron como `#327` (en el código) y `#328` (en el documento), y **las dos eran suyas**.
> Renumeradas a `#329`→`#333` con mapa explícito: 82 referencias en código y 15 en el documento.
> ▶ *No basta con mirar el remoto al ABRIR la tanda: hay que volver a mirarlo al CERRARLA.*
> Suite **4564 en verde** (28.609 aserciones, 1 skipped a propósito, **0 risky**), medida el
> **2026-09-10** sobre el árbol con la **T2g** del carril de diseño (`#487`). ⚠️ **+9 en NETO, y el
> neto esconde el movimiento**: la T2f entra y sale en CERO —12 casos nuevos de
> `BeforeVisitSectionTest` contra los 12 de `RulesSectionTest` (7) y `CardAnatomyTest` (5), que
> perdieron su sujeto—, y los +9 salen de TRES sitios, contados uno a uno: la guarda del divisor
> entre secciones (`#486`, **+1**), la reescritura de `VisitSectionTest` (`#487`, de **8 a 15**) y la
> guarda del par del verde en `ClientThemePackageTest` (`#487`, **+1**).
> ⚠️⚠️ **Y esta cifra la cazó el pre-push, no yo**: se dio por buena una nota que decía que esta
> línea «ya no existía», y la conclusión salió de un `grep` cortado con `head -3` — *un listado
> truncado no dice que algo no exista, dice que no lo has visto* (`#281`, otra vez). El gate hizo su
> trabajo.
> Antes, con la **T2d** (`#481`+`#482`). ⚠️ El neto es
> **+5** y esconde mucho movimiento: entran los **9** de `AttractionsPageTest` y los **7** de
> `RideMosaicSectionTest`, y salen los **4** de `AttractionComplementLandingTest`, los **4** del
> selector y la tarjeta de `ZonesSectionTest` y los **2** del dibujo de zona — todos con su sujeto.
> Antes, medida el **2026-09-09** sobre el árbol con la **Fase 1 completa** del carril de diseño (`#469`→`#475`, **+14**: la guarda de
> escalones inventados de `ShapeScaleTest`, los tres casos de `RhythmScaleTest`, el suelo táctil
> que un paquete no puede bajar, los **siete** de `TypeScaleTest`, el trinquete que obliga a
> `SidebarTokenBudgetTest::SIN_ESTRENAR` a encoger y el rótulo de toda opción de icono ofrecida). ⚠️⚠️ **En un CLON LIMPIO el total NO cambia pero los saltados sí**:
> medido escondiendo el paquete, **TRES casos se saltan** —los que aseveran algo de `client.css`, que
> está gitignorado— así que ahí `Skipped` vale **4** y no 1. El total se queda en 4.486 porque
> PHPUnit cuenta los saltados. Antes de esto la suite
> estaba en **4481** (27.440), medida el 2026-09-08 sobre el
> árbol con **`#444`** —el cliente cambiando sus invitados desde el post-form (**+29**: 18 de
> `GuestCountTest` y 11 de `GuestCountSurfacesTest`)— encima de **`#443`**
> —la hora extra cobrada POR INVITADO (**+22**: 19 casos nuevos de
> `StayExtensionPerGuestTest` y 3 reescritos o añadidos en `StayExtensionGuardsTest`)—, que iban sobre los
> dos carriles de esa misma jornada —**`#440` el teléfono en el mostrador (+10)** y **`#441`
> la exención al declarar un menor (+24 PHP y +9 JS)**—, que iban encima de lo del 06-09, que sigue:
> el mismo recuento de antes era **4358** (27.111) sobre el
> árbol con **LOS DOS CARRILES FUSIONADOS**: las siete tandas del panel (`#461` +12 · `#462` +13 ·
> `#463` +10 · `#464` +13 · `#465` +8 · `#466` +6 · `#467`, y **siete casos re-apuntados por sujeto**
> al retirarse la tira de días y el *toast* del desenlace) **más las del carril de complementos**
> (`#415` +6 · `#417` +12 · `#418` +1), **`#468` +1** (la guarda de tokens, que dependía de un
> fichero gitignorado), **`#420` +11** (`OverlappingSlotGridTest`, la rejilla solapada) **y `#424`→`#426` +32**
> (la hora extra de un pack: `PackStayExtensionTest` 18 + `StayExtensionGuardsTest` 13 + 1 de `OrderItemStatusTest`).
> **JS 951** (`node --test`).
> ⚠️⚠️ **Con dos carriles vivos esta cifra CADUCA al fusionar, y el hook lo dice antes que nadie**:
> si el push sale rechazado por aquí, no es un fallo — es que el otro carril trajo casos. Se remide
> y se escribe la de la suite REAL, nunca la que uno midió antes de integrar.
> ⚠️ **El `pre-push` compara este número con la suite real y RECHAZA el push si no cuadra** — es la
> única copia a propósito, no la dupliques en otro documento. Antes: 4228 el 2026-09-03 (árbol
> fusionado tras `#452`, con las cuatro tandas T0–T3 de `#413` dentro).
> ▶ **Reloj auditado el 2026-09-04**: `scripts/audit-clock.sh` en verde en **12 de 12** fronteras,
> incluidos los dos pases que cruzan medianoche (Madrid y UTC) a mitad de suite
> Antes: **2026-09-01 (cierre de la tarde) — 🚀 LA WEB DEL 2.º
> CLIENTE ESTÁ EN PRODUCCIÓN (`https://playjump.es`, `#325`/`#326`): su bloque está en el CARRIL 4
> (la PORTADA), que es el más reciente; antes, el 6.º (EXCURSIONES DE COLEGIO, `#322`/`#324`) bajo el
> contador vivo y el 5.º (PANEL: rol de puerta, `#320`).** ▶ **Y el carril 6 CERRÓ sus tres tandas**
> (`#322` horario por zona · `#324` precio por tramo · `#327` el cajón + el producto creado): **lo
> siguiente de ESE encargo es P3, la autorización de los padres a un tercero, que no tiene spec y
> lleva su bloqueo estructural ya medido en su carril.** ❗ **POR DÓNDE SE RETOMA** (`[DECIDIDO
> owner]` al cerrar): (1) **imágenes en la columna derecha del menú** — «las que sean, que no esté
> vacío» (`zones.image` no tiene consumidor desde `#302`: candidatas naturales); (2) **refrescar el
> contexto de cuenta del cajón al volver a la pestaña** (`DEUDA.md`, Media) — toca el SPA aparcado
> con permiso del owner para ese cambio; (3) el OJO del owner sobre los TPV. ⚠️ **DISTANCIA SANA**:
> la instalación es un cliente aparte del producto — lo suyo vive en su BD, su `.env` y ficheros
> gitignorados; al repo solo entran mecanismos (`#325`, adenda de cierre).
> **LIBRO DEL PEDIDO**: spec ✅ del owner (`#305`), **T1 (`#306`), T2 (`#308`), T3·1 (`#310`), T3·2
> (`#311`), T3·3 (`#312`), T3·4 (`#315`, LA RETIRADA del modelo de dos ejes) Y LA T4 (`#317`, EL
> MOTIVO MANDA en el reembolso · liquidación simétrica · «Descuento por cortesía») EN EL ÁRBOL, y
> el libro del panel PLEGADO detrás de un CTA (`#318`): CÓDIGO COMPLETO; queda el OJO del owner
> (V18–V23) y sus vetos a D-T4·6/D-T4·7** — carril 3, abajo.
> **MIXTOS**: T1→T5 en el árbol (`#288`/`#289`/`#294`/`#296`/`#298` con sus 5 adendas) y **T6 EL
> GUARDIÁN DE SOLAPES EN EL ÁRBOL (`#299`): el plan de `#284` queda SIN tandas pendientes** —
> siguen fuera por diseño la fase 3 de §20.2 y el AFORO (owner); la ficha del fantasma de la señal
> en `DEUDA.md` **quedó RETIRADA por la T1 del libro** (`#306`); queda el OJO del owner sobre T5+T6.
> **IDIOMA VISUAL / LANDING** — el carril **más movido de la jornada**: en el árbol la tanda A, la
> T2 (`#293`), T4 (`#302`), T5 (`#303`), **T6 «Visítanos» en tarjetas (`#307`)**, **T7 las cinco
> secciones (`#309`)**, **los assets del parque real (`#313`)** y **T8 el orden y el ritmo (`#314`)**.
>
> ❗❗❗ **POR DÓNDE SE RETOMA ESTE CARRIL — LEE ESTO Y NADA MÁS.** La portada ya NO es la base
> heredada: hoy sirve **entradas → cumpleaños → el parque → ubicación → normas → dudas**, con las
> fotos y el vídeo REALES, aire uniforme (240 escritorio / 160 móvil) y todas las secciones tocadas
> salvo dudas y el cierre. **Lo que queda es del OWNER, no una tanda que empezar:**
>   1. **Las CINCO fotos con duda** (Tirolina · Basket Jump · Barredora · Castillo de bloques ·
>      Circuito High) y **las NUEVE sin asignar**. Hoja de revisión publicada como artefacto en la
>      sesión del 01-09; el mapeo vive en `scripts/`+`DECISIONES #313`. Cambiar una es **sustituir un
>      fichero**: ni código, ni BD, ni migración. ⚠️ Y hay **26 huecos para 35 fotos** — varias libres
>      son cosas que el parque TIENE sin dar de alta (arenero, correpasillos, cubo de Rubik, aro):
>      crearlas es DATO y hacen falta nombre y edad.
>   2. **El `sticky` de normas tiene 99 px de recorrido** y a 1280×900 la sección cabe entera, así que
>      no se engancha. **Las dos cosas que pidió se estorban** (columna pegajosa vs. tope de 3/4
>      normas): la palanca es subir el tope.
>   3. **`legal.jurisdiction` no se puede rellenar desde el panel** (sale `[pendiente]` en aviso legal
>      y condiciones) y **falta su VALOR**, que es suyo. Ficha en `DEUDA.md`.
>   4. **Las franjas no cubren el horizonte**: el sábado 05-09 ofrece CERO horas. Es AFORO, **NO se
>      tocó**, y la ficha lleva el comando.
>   5. La **categoría de cookies `social` ya no gatea nada** desde que se retiró «En directo»: vuelve
>      con las reseñas o se retira entera **con su frase del banner**, que es texto legal.
> ▶ **Lo que sigue vivo como criterio**: el diagnóstico del molde de `#297` (cada sección adopta la
> forma de lo que ES) y `FacadeDecorationIsPerScreenTest` (ninguna pieza decorativa dentro de un
> bucle). ⚠️ **El presupuesto de decoración de `#292` está REVISADO a sabiendas** por `#309`: la
> portada gasta CUATRO piezas, no tres.
> ▶ **Los DATOS REALES viven solo en la BD local** (`#304` catálogo · `#307` legales/contacto) y **no
> viajan en el commit**; los assets sí (`#313`). ⚠️ **`client-kit.svg` está gitignorado**: quien clone
> tiene que rehacerlo con `python3 scripts/kit-fachada.py`, que necesita `mockup_playjumppark/`.
> ❗ **SI ENTRAS NUEVO A MIXTOS: `specs/cumple-mixto.md` §18 (visión) → §21 (sello) → §22 (completo son
> dos preguntas) → §23 (la T3) → §20 + §24 (la T4: el descuento espejo) → §25 (la T5: las
> palabras) → **§26 (la T6: el guardián en el dominio)**. Diseño fino antes de código y preguntas
> numeradas al owner: es el método que las seis tandas han seguido. ▶ **La retoma de este carril
> ya NO es una tanda**: es el OJO del owner sobre T5+T6, y las piezas aparcadas por diseño (la
> fase 3 de §20.2 · el AFORO; el fantasma de la señal ya lo cerró la T1 del libro, `#306`).**
> ⚠️ **Entorno, 2026-08-31**: el `php artisan serve` del contenedor amaneció muerto (SIGTERM 14:29) y a
> las 16:3x el demonio de Docker Desktop dejó de responder (500 en su API; se recuperó reiniciándolo
> desde Windows). Ninguna de las dos es del repo; si el `curl` del arranque da `000`, mira primero
> el contenedor y después el demonio. ⚠️⚠️ **Dos agentes sobre `main` en la misma jornada**:
> el número de decisión se elige mirando el REMOTO (`git fetch` antes de numerar) — hoy chocó DOS
> veces (el carril del tema iba por 281 con el remoto en 285; el de mixtos escribió `#286` con el
> remoto ya en 287 y pasó a `#288` al integrar). Los dos carriles NO se solapan en código.
>
> ▶ **CONTADOR VIVO** (la única copia; el hook lee la PRIMERA de estas líneas del fichero):
> ✅ **AUDITORÍA DEL RELOJ EN VERDE EN LAS DIEZ FRONTERAS** (2026-09-02, tras arreglar los ONCE rojos
> que encontró; ver el bloque del justificante más abajo). ⚠️ `audit-clock.sh` **no está en el
> pre-push**: si no se corre al cerrar, un test que solo falla ciertas noches se va a `main`.
> ⚠️ **RE-MEDIDA tras rebasar encima los DOS arreglos del reloj de la T3** (con `npm run build` +
> `build:ssr` delante, porque `#340` toca Vue): **sale el MISMO número**, que es lo que había que
> comprobar — los dos arreglan FIXTURES y no añaden casos. *Coincidir no se supone: se mide.*
> ⚠️⚠️ **Y RE-MEDIDA otra vez al fusionar `#406` (justificante) con `#347` (Google), porque NINGUNO
> de los dos números valía**: el justificante dejó **4023 · 25.709** y Google **4034 · 25.755**, los
> dos medidos **desde la misma base de 4019**. Es la lección de `#404` en pequeño: *dos ramas que
> mueven el mismo contador no se fusionan eligiendo un número*.
> ⚠️ **Y aquí la aritmética NO cierra, a diferencia de la de `#404`, y se dice en vez de forzar el
> número**: 4019 + 4 + 15 = **4038** y el gate mide **4039 · 25.772**. Un test y dos aserciones de
> más, o sea que uno de los dos números declarados se tomó sobre una base que ya no era 4019. *Que la
> suma no cuadre es justo el motivo por el que el gate mide en vez de creerse la resta.*
> Recuento anterior a `#452`: **4156** (26.328 aserciones, 1 skipped a propósito), medido el 2026-09-03
> sobre el árbol CONJUNTO **de los dos carriles ya fusionados** (`#404`). ⚠️ **+8 y +51 los pone la T8
> de Google** (`#350`): las dos guardas nuevas del botón y del aviso de privacidad, más los casos que
> sustituyen a los de las casillas retiradas. Antes: las cuatro tandas del
> justificante (`#400`→`#403`) sobre las TRES de Google auth (`#342`, `#343`, `#344`), la columna del menú
> (`#341`, +8), el refresco al volver a la pestaña (`#340`) y el vocabulario del descargo (`#339`),
> encima del arreglo del 500 de la puerta (`#338`) y de la **T3** del justificante (`#337`) con sus
> arreglos del reloj.
> ⚠️ `#340` **no suma tests PHP**: los suyos son 12 de `node --test`
> (891 → **903**), y el presupuesto del cajón quedó en verde **sin subir el techo**.
> ⚠️ El neto de `#338` es **+1** (dos guardas y fuera `TmpProbeTest`) y el de `#339` **+3**.
> ⚠️⚠️ **La primera medición de `#339` dio 41.012 aserciones y era un DEFECTO de la guarda nueva**, no
> una mejora: aseveraba dentro del bucle, así que metía ~16.000 aserciones por un solo caso **y moría
> en la primera violación** en vez de listarlas. Acumula y asevera una vez: 10.
> Antes, 3901 / 25.023 (`#338`) · 3900 / 25.015 (`#337` sobre `#336`).
> ⚠️⚠️ **Medida DESPUÉS del rebase y con `npm run build` + `build:ssr` delante, NUNCA sumada**: por
> separado daban 3893 / 24.999 (la T3 sola) y 3884 / 24.932 (el árbol anterior), y ninguna es la
> buena. Antes: 3884 / 24.932 (`#336` sobre `#335`) · 3877 / 24.916 (la T2 sola).
> ⚠️ El techo del chunk del cajón va por **263** (`#337`: 262 → 263, podado antes de subirlo), y el
> de los textos del montaje por **9.400 B** (9.200 → 9.400). Los dos, atribuidos midiendo cada
> subida a su rama.
> ▶ Los DOS verificadores de concurrencia, en verde sobre InnoDB real.
> ⚠️⚠️ **TERCERA colisión de numeración con el otro agente, y la peor**: mis entradas nacieron
> como `#327` (código) y `#328` (documento) y las dos eran suyas. **Renumeradas a `#329`→`#333`**,
> 82 referencias en código y 15 en el documento, con mapa explícito para no tocar las suyas.
> ▶ *La regla ya no basta con mirar el remoto al ABRIR: hay que volver a mirarlo al CERRAR.*
> ✅ **AUDITORÍA DEL RELOJ pasada al cerrar** (`scripts/audit-clock.sh`, 10 fronteras): verde en todas
> **tras arreglar un rojo diferido que encontró**. ⚠️ `VisitSectionTest::test_estando_abierto…` fallaba
> a las **23:59:30 de Madrid**: abría el parque hasta las 23:59:00 y decía en su comentario que así no
> dependía del reloj — **no era cierto**, y treinta segundos al final del día no son holgura. Ahora
> congela el reloj en una constante documentada, que es lo que `TESTING.md` §2 pide. **Preexistente,
> no de esta tanda**: se comprobó reproduciéndolo contra el árbol anterior a la sesión.
> ⚠️ **Se mide tras CADA rebase, nunca se suma.** Con dos agentes en `main` el número solo vale medido
> sobre el árbol conjunto: por separado daban cifras distintas y ninguna era la buena.
> - Antes, 3823 / 24.730 (`#328`) · 3796 / 24.599 (`#327`) · 3789 / 24.564 (`#324`, el precio por tramo) · 3779 / 24.532 (`#322` con su panel sobre la
>   T10) · 3756 / 24.534 (`#320` sobre `#319`).
> ⚠️ **No se suma, se mide** — y ⚠️⚠️ **tras un rebase que toque Vue hay que
> `npm run build:ssr` ANTES de leer la suite**: sin eso salieron 35 rojos en
> `SidebarDomContractTest` que no eran de ningún cambio.
> - Antes, 3728 / 24.295 (`#315`, la T3·4 sobre el árbol conjunto), 3760 / 24.946 (`#314`, landing) y 3760 / 24.940 (`#313` y `#312`: la T3·4 retiró los
>   dos tests de servicio del modelo viejo, −50, y sumó 18), 3758 / 24.958 (`#311`).
>
> ═══════════ ❗❗❗ CARRIL 6 · EXCURSIONES DE COLEGIO (2026-09-01, noche) — POR DÓNDE SE RETOMA ═══════════
> **LAS TRES TANDAS ESTÁN EN EL ÁRBOL Y EL PRODUCTO CREADO: `#322` (horario por zona), `#324` (precio
> por tramo), `#327` (el cajón) y los datos.** El cliente vende excursiones de colegio (2 h y 3 h,
> 30–100 personas, zona propia) que vienen **entre semana por la mañana**. Specs:
> `horario-por-zona.md` y `precio-por-tramo.md`.
>
> ✅ **P3 YA TIENE SPEC — Y RESULTÓ NO SER DE ESTE CARRIL**: `docs/specs/waiver-por-reserva.md`
> (2026-09-01, siete `[DECIDIDO owner]`, cuatro tandas, **cero código**). ❗ **Su §1.2 corrige lo que
> decían estas líneas**: no es una feature de excursiones de colegio, es del **WAIVER** — vale para
> cualquier reserva con un menor que no es menor a cargo de quien reserva (el caso del owner: *«el
> amigo de su hijo»*). Lo de abajo se conserva porque **sigue siendo cierto y la spec lo usa**, con
> DOS correcciones que van DELANTE del texto:
>   - ⚠️⚠️ **El bloqueo (a) SE DISUELVE, no se salva**: el `user_id` de la firma es **el que reserva**
>     —el RESPONSABLE, que es lo que el owner decidió—, así que la columna sigue `NOT NULL`, la cadena
>     sigue agrupando igual y **no hay migración destructiva** sobre una tabla con firmas en producción.
>   - ⚠️⚠️ **Y aparece un SEGUNDO bloqueo que nadie había medido, que es el que de verdad decide el
>     diseño**: `waiver_signatures.subject_id` tiene **FK dura a `dependents.id`**, así que un tercer
>     tipo de sujeto **no puede reutilizar esa columna** — y quitarla sería regresar un endurecimiento.
>     Va **columna propia**.
>
> ❗❗❗ **LO QUE ESTA SESIÓN DABA POR PENDIENTE DE P3** (`[owner, 2026-09-01]`, antes de la spec). Lo que
> ya estaba decidido y medido, y que quien lo retome NO tiene que volver a averiguar:
>   - **Se ancla al PEDIDO** (`[DECIDIDO owner]`), y el modelo mental es del propio owner: *«el
>     papelito que el profesor reparte para que lo firme el padre»*. Los 100 niños de un colegio **NO
>     son menores a cargo del tutor**: son nombres en una hoja firmada atada a ESA excursión. Eso
>     descarta meterlos en `dependents` (les daría permanencia, tope por cuenta y los datos de 100
>     menores viviendo en la cuenta del tutor).
>   - ⚠️⚠️ **BLOQUEO ESTRUCTURAL MEDIDO**: `waiver_signatures.user_id` es **NOT NULL** con
>     `restrictOnDelete`, y la cadena de hashes se agrupa por `(user_id, sujeto)` — **hoy no cabe
>     físicamente la firma de un padre sin cuenta**, que es justo el caso que el owner quiere.
>   - ⚠️ **RGPD**: el owner dijo «si el padre quiere anonimizar, no sé qué hacemos». La respuesta es la
>     del art. 17.3.e (conservar lo necesario para defender reclamaciones) hasta que venza
>     `waiver.retention_months` — que sigue **`[PENDIENTE: owner]`** y hará falta igual. ⚠️ Y **la firma
>     del padre NO puede morir con la anonimización del tutor**: el parque se quedaría sin la prueba de
>     una excursión de 100 niños que ya ocurrió, y además esos datos no son del tutor para borrarlos.
>   - ⚠️ **`attractions` y `park_rules` no tienen `slug`** y **no existe hoy «plaza nominal»**: una
>     línea de 100 entradas es un NÚMERO, no 100 personas. Saber «faltan 37 firmas de 100» exige
>     construir ese concepto.
>
> ❗❗ **LO QUE NO HUBO QUE CONSTRUIR, Y LO DESTAPÓ EL OWNER PREGUNTANDO** («pero cumpleaños no tiene una
> opción así? x cumpleaños por franja?»): **`zones.max_per_slot` y `max_guests_per_slot` ya existían, ya
> eran por zona y ya corrían bajo lock**. El tope «1 excursión por franja» es CONFIGURACIÓN. Y lo mismo
> `min_qty`/`max_qty` (mínimo 30 / máximo 100), `duration_min` (2 h y 3 h), `deposit_type` (la señal) y
> las tarifas de finde. **De toda la petición, lo único que faltaba de mecanismo era el horario.**
> ▶ *Preguntar «¿esto no lo tenemos ya?» antes de diseñar valió media tanda.*
>
> ❗❗ **LO QUE ES DEL OWNER Y NO SE HA HECHO** (revisar antes de tocar nada de este carril):
>   1. **El OJO del owner** sobre las tres tandas. Ya vio la A.
>   2. ⚠️⚠️ **LA SEÑAL DE LOS DOS PRODUCTOS (100 € fijos) ES UN NÚMERO INVENTADO POR EL AGENTE.** El
>      owner dijo «señal x € y el resto en el parque» y no dio el importe; se puso un marcador para
>      poder probar el flujo entero. **Hay que confirmarlo o cambiarlo** en el campo «Señal» de cada
>      producto.
>   3. ❗❗ **TODO LO DE LA TANDA C ES DATO LOCAL Y *NO* VIAJA EN EL COMMIT.** Con la web ya en
>      producción (`playjump.es`), la zona `excursiones`, los dos productos, sus 12 tramos, las
>      plantillas de franja y el cambio del viernes **existen solo en la BD de desarrollo**. Para que
>      el cliente los tenga hay que **crearlos desde el panel de producción** (todo es configurable;
>      no hace falta código). El guion que los creó aquí está en la transcripción de `#324`/`#327`.
>   4. ⚠️ **El VIERNES pasó a tarifa especial para TODO el catálogo** (`[DECIDIDO owner]`; antes eran
>      solo sábado y domingo). Se comprobó ANTES de aplicarlo que los 20 productos activos tienen
>      precio especial, así que ninguno se quedó sin poder venderse — pero **todo el catálogo es más
>      caro los viernes desde ahora**. Se revierte desde Ajustes → Tarifas.
>   5. ⚠️⚠️ **CORRECCIÓN DE UNA AFIRMACIÓN DE ESTA MISMA SESIÓN**: el agente dijo repetidamente que «el
>      parque cierra los martes» y **es FALSO** — abre los SIETE días, 10:00–21:00. Lo que se midió
>      como martes cerrado era **una fecha especial concreta (2026-09-01)**, generalizada desde una
>      sola medición. Para este cliente, lo que hace el trabajo de la tanda A es **la ventana de
>      08:00** (el parque abre a las 10:00), no el día cerrado.
>
> ✅ **LA TANDA B ESTÁ EN EL ÁRBOL (`#324`) Y CORREGIDA EN EL CAJÓN (`#327`)**: el paso 3 pintaba el
> precio del CALENDARIO —que no conoce la cantidad— así que con tramos enseñaba siempre el más barato
> («12 € por niño» con 30, con 69 y con 70) mientras cobraba lo correcto. Lo vio el owner probándolo.
> ▶ **La respuesta ya venía en el payload**: el endpoint de complementos, que el cajón llama en CADA
> cambio de cantidad, tarifica la línea con `CartPricing` y publica `line.unit_price_cents`. Ni
> petición nueva ni resolver el tramo en JS. Y la cantidad **se escribe** (con mínimo 30, el `+` pedía
> treinta pulsaciones). Verificado en navegador REAL: 30→15 € · 69→15 € · 70→13 € · 100→12 € · 500→100.
> ▶ **Lo demás de la tanda B (`#324`)**: `price_tiers` propia, precio UNIFORME, panel incluido y
> la puerta cerrada al cruce con el sello de edades (guarda en las DOS direcciones). ⚠️ Dos hallazgos
> que valen para todo el repo: **añadir una dimensión a una tabla compartida cambia el significado de
> los agregados que la leen** (por eso NO se metió `min_qty` en `prices`), y **un complemento es una
> fila de `ticket_types`**, así que un `instanceof` los alcanza — cada uno pagaba una consulta por unos
> tramos que no puede tener, y lo cazó el presupuesto de la API, no una lectura.
> ▶ **QUEDA LA TANDA C, que es DATO y no código** (abajo), y el OJO del owner.
>
> ▶ **Lo que la tanda B decidió** (por si hay que retomarlo): Es DINERO (`CRITICAL_RE`,
> `PAY-16`/`PAY-17`, el libro del pedido) y va en spec propia. Lo decidido y medido para arrancarla:
>   - **Precio UNIFORME, no escalonado** (`[DECIDIDO owner]`, con los dos números delante): 70 niños a
>     13 € = **910 €**, no 30×15 + 40×13 = 970 €.
>   - **Tramos del cliente** (2 h / 3 h × L-J / finde-festivo): 30 → 15/17 y 18/20 · 70 → 13/15 y 16/18
>     · 100 → 12/14 y 15/16. **Es un RANGO y a la vez el MÍNIMO FACTURABLE**, configurable por producto.
>   - **Por debajo de 30 y por encima de 100 NO se vende**: «ya toca llamar y preguntar».
>   - ⚠️ **No existe HOY nada de descuentos**: `Content\Offer` es marketing («sin lógica de dinero»).
>     El precio es hoy función de `(producto, tarifa-del-día)` — la cantidad **no es una dimensión**.
>   - ⚠️ `order_items.unit_price` es **UNSIGNED**; el precedente de línea negativa es `is_credit` (T4).
>   - ⏸️ **APARCADOS por el owner**: el 2x1 y «la tercera 10 € más barata». ⚠️ Medido: `free_quantity`
>     YA existe y es el mecanismo natural del 2x1, pero hoy significa «incluido en el pack» de cara al
>     cliente (`addonBadgeKey()`), así que reusarlo exige separar *cuántas gratis* de *por qué*.
>
> ▶ **Y LA TANDA C es DATO, sin código**: crear la zona `excursiones` (con `opens_at`,
> `ignores_venue_closure` y `max_per_slot`), los dos productos como **`pack`** (`[DECIDIDO owner]`:
> `min_qty` y el cupo de grupos SOLO funcionan siendo pack) y meter **el viernes en la tarifa
> `special`** — que hoy son solo sábado y domingo, y el owner decidió que pasa a especial **para todos
> los productos**, no solo excursiones.
>
> ⚠️ **QUEDA EL OJO DEL OWNER** sobre la tanda A (crear la zona en el panel y ver que el martes ofrece
> franjas de excursión y ninguna de jump/kids).
>
> ❗❗ **DOS TRAMPAS QUE PAGÓ ESTA TANDA:**
>   1. **Mi propia spec afirmaba, medido y MAL, que los consumidores del horario eran DOS.** Son TRES:
>      `ProductAvailability::allowsStart()` lo consulta, y por él pasan `SlotOffer` (oferta pública),
>      `OrderCreator` (checkout), `OrderItemEditor` e `ItemRescheduleOffer`. **Lo cazó una GUARDA, no
>      una lectura**: el test se escribió, se ejecutó y falló con el producto ya «arreglado».
>      ▶ *Un `grep` del servicio da los consumidores DIRECTOS y deja fuera a quien pregunta por un tercero.*
>   2. ⚠️ **`git checkout <fichero>` para deshacer una mutación, con el trabajo sin commitear, se lleva
>      el trabajo** (la regla de `#181`, pagada otra vez en pequeño). El arnés hace `git stash` sobre un
>      commit de guardado.
>
> ═══════════ ❗❗❗ CARRIL 5 · PANEL: LA EXENCIÓN DEL MENOR Y EL ROL DE PUERTA (2026-09-01, noche) ═══════════
> **EN EL ÁRBOL: `#320`.** Dos encargos de presentación del owner que resultaron ser uno solo.
>
> **1 · La exención de un menor solo se ROTULA cuando es una excepción** (`menores-a-cargo.md` §13).
> Se retira «exención ✓» de las tres superficies del operador (ficha de pedido, ficha de titular,
> puerta) y **siguen pintándose `outdated` y `missing`**. ⚠️ La premisa «es obligatorio» está medida
> y es cierta **solo en el instante de asignar**: una firma vigente CADUCA SOLA al publicar versión
> nueva, y la regla del asignador **solo aplica en modo `interno`**. Regla en UN sitio
> (`WaiverStatus::minorStateIsNoteworthy()`), que de paso mató una derivación **por triplicado** que
> ya existía. ⚠️ **La exención del ADULTO en la puerta NO se tocó** (ahí el estado es lo que la
> puerta decide) ni el CAJÓN (ahí el cliente firma).
>
> **2 · Nace el rol `puerta`** (`panel-navegacion.md` §12), con solo dos permisos, que entra por el
> mismo login y **no navega el panel** (`RestrictsPuertaRole`). **`staff` NO se tocó.** Y al ADMIN se
> le retira «Puerta» del menú, que baja a «Ajustes → Sistema».
> ⚠️⚠️ **Se descartó un diseño ya implementado** —recortarle diez permisos a `staff` y sacarlo del
> panel—: rompía 116 tests, pero el motivo fue que revisaba el `[DECIDIDO owner]` Q1·a de `#294` y
> **dejaba la matriz de 22 permisos SIN SUJETO** (admin se salta todo por `Gate::before`).
>
> ❗❗ **LAS TRES TRAMPAS QUE PAGÓ ESTA TANDA, por si tocas algo de aquí:**
>   1. **Retirar un ítem del menú lo borra TAMBIÉN del buscador global** (saca sus pantallas de la
>      navegación) **y ninguna guarda lo ve** si la pantalla vive fuera del shell de Filament.
>   2. **Un docblock puede crear una flecha de arquitectura**: Pint convirtió un `{@see}` en `use` y
>      metió Identity→HTTP. Reescribir la cita en prosa NO basta; hay que quitar el import.
>   3. **`RequiresStaffOrAdmin` pasaba a MENTIR** con el tercer rol → `RequiresPanelRole`, leyendo
>      `User::PANEL_ROLES` en vez de una segunda copia a mano.
>
> ▶ **QUEDA: el OJO del owner** en navegador (panel y puerta) — es lo único que lo separa de ✅.
> ▶ **LO SIGUIENTE ACORDADO con el owner** (orden suyo): **P2, la spec de DESCUENTOS**, y después
> **P3, la spec de AUTORIZACIÓN DE UN PADRE A UN TERCERO**. Las dos son spec ANTES que código.
>   - **P2** son DOS mecanismos, no uno: **promos con CÓDIGO promocional** (% sobre una entrada o
>     sobre la segunda) y **tramos por cantidad** para excursiones. ⚠️ El owner aparcó el 2x1 y «la
>     tercera 10 € más barata». ⚠️ **Dato suyo que manda**: *el tramo también acota cuánto se puede
>     vender, ni más ni menos* — no es solo precio, es validación de línea. ⚠️ El AFORO va aparte
>     (las excursiones tendrán su propia zona). ⚠️ Medido: **no existe HOY nada de descuentos**
>     (`Content\Offer` es marketing, «sin lógica de dinero»); `unit_price` es UNSIGNED y el
>     precedente de línea negativa es `is_credit` (T4 del libro); `free_quantity` YA existe y es el
>     mecanismo natural del 2x1, pero su semántica actual dice «incluido en el pack».
>   - **P3**: `[owner]` la exención se ancla **al PEDIDO** y el modelo mental es *«el papelito que el
>     profesor reparte para que lo firme el padre»* — los 100 niños **NO son menores a cargo del
>     tutor**. ⚠️⚠️ **Bloqueo estructural MEDIDO**: `waiver_signatures.user_id` es **NOT NULL** con
>     `restrictOnDelete` y la cadena de hashes se agrupa por `(user_id, sujeto)` — **hoy no cabe la
>     firma de un padre sin cuenta**. ⚠️ El owner dijo «si el padre quiere anonimizar, no sé qué
>     hacemos»: la respuesta es la del art. 17.3.e (conservar para defender reclamaciones) hasta
>     `waiver.retention_months`, que sigue **`[PENDIENTE: owner]`** y hará falta igual.
>
> ═══════════ ❗❗❗ CARRIL 4 · LA PORTADA (sesión del 2026-09-01, tarde) — POR DÓNDE SE RETOMA ═══════════
> ❗❗❗❗ **2026-09-01 (tarde) · LANZAMIENTO: https://playjump.es ESTÁ EN PRODUCCIÓN (`#325`).**
> Enhance (el mismo panel que staging; `ENTORNOS.md` §6 tiene la máquina medida). Salió con la
> **compra online CERRADA y el catálogo visible** (`sales.online_enabled=0` → API 503 + CTA `tel:`),
> `/servicios` en mantenimiento y fuera del menú/pie, menú sin números, **descarga de responsabilidad
> v1 PUBLICADA** (es/en/fr; EN/FR con el texto en español, traducción pendiente), legales sin
> `[PENDIENTE]`, Turnstile activo, `admin@playjump.es` + `tpv1..4@playjump.es` (rol `puerta`), datos
> limpios (solo catálogo/config del local). ❗ **LO QUE ES DEL OWNER y sigue abierto**: (1) el
> document root del panel → `public_html/public` (hoy funciona por un puente `.htaccess`); (2) las
> DOS líneas de cron en el panel (scheduler + `queue:work --stop-when-empty`: **sin la segunda no
> salen los correos del registro**); (3) añadir el usuario `playjump2_main` a la BD (la app corre con
> el usuario del panel, que Enhance rota). ⚠️ **El commit de lanzamiento (`350e0a0`) NO incluye el
> `#324` del otro carril** (precio por tramo): producción lleva el árbol anterior al rebase; el
> siguiente `deploy.sh` lo subirá. ▶ **Segundo despliegue (tarde, `684074a`)**: entra el `#324`, el
> LOGOTIPO en correos/panel/pestaña del panel (`#325`) y **`#326`: el CTA doble arranca con la
> CUENTA sin sesión y con COMPRAR con sesión** (`<body data-cta-mode>` → `$store.ctaPair`; `:class`
> en sintaxis de objeto). Verificado: el registro de prueba del owner terminó de punta a punta
> (correo verificado por el cron del panel, 1 firma). `THEME_FONTS` faltaba en el `.env` de
> producción (fuentes del producto): corregido; las franjas se generaron a mano (3120) porque el
> despliegue las generó con la BD aún vacía. ▶ **Tercer despliegue (`1b128bb`+`045419c`)**: el CTA
> por sesión en producción y la invitación sobre la mitad plegada (subida en caliente por `scp`, sin
> parada). El «pendiente de exención» que vio el owner era estado del cajón sin refrescar (el
> servidor daba `signed=true`): ficha en `DEUDA.md`. El modo mantenimiento de las 15:22 lo activó él.
> ▶ Después de abrir: imágenes del menú (`[DECIDIDO owner]`, cualquiera), refresco del contexto,
> traducción del documento, correos, Redsys real. ⚠️ Trampas pagadas (todas en `#325`): document root, `settings` no vacía
> tras migrar (→ `REPLACE INTO`), `roles` vacía con `create-admin` callado, `psysh --execute` +
> `require`.
>
> ❗❗❗ **2026-09-01 (noche, 2.ª parte) · T10 EN EL ÁRBOL (`#323`): LA TARJETA ES PEGATINA Y HAY DOS
> NIVELES.** Tanda B de la auditoría, `[DECIDIDO owner]` **opción A** con las dos pieles renderizadas
> sobre `/precios` real (hoja-artefacto «La piel de la tarjeta», 1440 y 390, fuentes verificadas):
> `.price` y `.rule` (`/normas`) pasan a la receta de la pegatina, **fuera sus hovers** (levitaban
> con `--shadow-lift` y borde CIAN), la destacada conserva el contorno, el icono de `/normas` pasa al
> hueco de `#319`. Nace la regla **pegatina = lo que se elige; sin sombra = apoyo** y la vigila
> `CardSkinTest`. Medido: geometría idéntica antes/después (cero reflujo). ⚠️ **Trampa pagada**: la
> primera hoja de opciones salió con la fuente de RESPALDO (webfonts externas) y se descartó — la
> sonda espera `fonts.check()` de Bungee/Hanken antes de capturar. ⚠️⚠️ **Numeración**: el `#322`
> lo tomó aforo en mitad de la tanda, y su commit **renumeró mi `#321` a `#322` en la fila de
> `CLAUDE.md` y en este carril** (en `DECISIONES` seguía bien): corregido al integrar. *El número se
> COPIA del `DECISIONES.md` remoto, no se deduce.* ▶ **Lo que sigue de la auditoría** (por orden del
> informe): T3 el cian sin rol (FAQ, `/normas`) · T4 arquitectura (interiores que duplican portada) ·
> islas heredadas (widget de ofertas, bucles `jj-*`) · la escala `--fs-*` esquivada · la capa de
> sorpresa (F). Y tres flecos del owner en la spec §4.1: `.bd-pack` sobre magenta, el glifo «!», y
> el hover de `.ride-card`.
>
> ❗❗❗ **2026-09-01 (noche) · LA AUDITORÍA DE DISEÑO Y SU PRIMERA TANDA (T9) EN EL ÁRBOL (`#321`).**
> El owner pidió auditar el diseño con la skill `hallmark` (instalada en `~/.claude/skills/hallmark`;
> el `npx` de Windows falla con EPERM, se instala clonando el repo) SIN workflow —⚠️ tercera vez que
> lo pide: solitario por defecto—. El informe vive como ARTEFACTO «Un solo idioma» (3 críticos ·
> 8 mayores · 6 menores, con temas T1–T7 y plan de tandas A–F): la tesis medida es que el «ruido»
> NO viene de la decoración (la portada cumple su presupuesto) sino de DOS GENERACIONES del sistema
> conviviendo. ▶ **T9 = tanda A del informe, HECHA** (`#321`, spec §3.undecies): un solo botón,
> acción `--action` en toda la web `[DECIDIDO owner]`, hover sin salto, `bd-btn` absorbida, bloque
> muerto `.invite-*` retirado (4 excepciones de guardas encogen), toggles con guion de diccionario.
> Guarda `SingleButtonFamilyTest` (5/5 mutaciones muerden). ⚠️ `btn--zone` SIGUE en CSS a propósito:
> el cajón Vue la emite y el SPA está aparcado. ▶ **LO QUE SIGUE de la auditoría** (flujo acordado:
> informe → owner decide → implementar; opciones RENDERIZADAS donde haya gusto): **tanda B** (la
> tarjeta: ¿pegatina también en precio/normas? — el owner espera ver las dos opciones sobre página
> real) · T3 el cian sin rol (FAQ, /normas) · T4 arquitectura (interiores que duplican portada;
> /entradas ES la portada con cajón) · islas heredadas (widget de ofertas con paleta del 1.er
> cliente y fallback `#FF5B22`, bucles `jj-*` vs presupuesto `#279`) · escala `--fs-*` esquivada
> por ~277 px literales. ▶ El PÚBLICO y la dirección (madres/familias/jóvenes; «sorpresa en los
> momentos, calma en el camino del dinero»; 6 referencias Framer del owner) están en el informe y
> en la memoria del agente. ⚠️ El `#320` se lo llevó el carril del panel en mitad de la tanda: el
> número se fijó tras `git fetch`, la regla de siempre.
>
> **EN EL ÁRBOL (tarde): `#319`, los ICONOS DE TARJETA y el tope de dos líneas.** Encargo del owner con el
> patrón de ficha de Google Store: «Visítanos» estrena icono de CATEGORÍA (reloj y pin) y el botón
> de teléfono el de la ACCIÓN; la tarjeta de tarifa pinta **el marcador que el panel YA elige**
> (`ticket_types.icon`, `#259` — ese campo tenía **un solo consumidor, el cajón**); y las
> descripciones quedan en dos líneas por **los dos mecanismos**: el copy reescrito y el corte como
> red. Guarda nueva: `CardAnatomyTest` (5 casos, 7 mutaciones).
>
> ❗❗ **LO QUE ES DEL OWNER Y NO SE TOCÓ:**
>   1. ⚠️⚠️ **UN SEXTO SITIO CON LA EDAD VIEJA**: la norma «Zona Jump» del panel dice **«Entrada
>      desde los 6 años y 1,30 m»** y contradice lo fijado (**JUMP es 8+**). Vive en `venue_rules`
>      —otra tabla— y por eso el barrido de `zones`/`ticket_types` no la alcanzó. **Es dato del panel
>      y texto de acceso: se corrige desde el panel, no desde aquí.**
>   2. **El OJO del owner** sobre las tarjetas en su navegador: un headless mide, no valida.
>   3. ⚠️ La corrección de edades de la sesión (KIDS 4-7 · JUMP 8+, sin altura en Kids) **vive solo
>      en la BD local y NO viaja en el commit**: en producción las tres entradas Kids siguen
>      anunciando «De 4 a 7 años» con la zona descrita como «de 1 a 12».
>
> ⛔ **EL SPA NO SE TOCA** (`[DECIDIDO owner, 2026-09-01]`: «el SPA lo dejamos por ahora»). Se
> construyó y se **REVIRTIÓ ENTERA** la E1a del catálogo del cajón —portada por tipo, zona ordenando
> dentro, pegatina, y los tres campos que el servidor publica y el cajón tira (`zone`,
> `price_varies`, `period_label`)—. **El trabajo está guardado en la etiqueta local
> `wip/catalogo-cajon-e1a`** (no empujada) y su diseño, medido y con las diez decisiones del owner,
> **se perdió con el revert**: si se retoma, se rehace desde la etiqueta.
>
> ❗❗ **Y LO QUE SE INTENTÓ Y EL OWNER RECHAZÓ, para que nadie lo reproponga**: pintar la EDAD en las
> 23 tarjetas de atracción. El dato existe (`attractions.age`, **19 de 23** la declaran) y **no lo
> enseña ninguna pantalla**, pero lo cazó la guarda de `#302` y `[DECIDIDO owner]` fue respetarla.
> ▶ Consecuencia asumida: **esas 23 tarjetas no reciben icono**, porque sin dato detrás un icono
> repetido 23 veces es decoración dentro de un bucle (`#286`).
>
> ❗❗ **REPARTO — DOS AGENTES, Y COMPARTEN CLON.** Medido el 2026-09-01: **ocho sesiones con el mismo
> `cwd`** (`~/proyectos/JumpWeb`), un solo worktree. Eso rompe `CONVENCIONES §8` («dos agentes = dos
> clones»): **no hay rebase que proteja, se comparte el árbol de trabajo**, y un `git stash`,
> `git checkout --`, `git reset` o `git clean` de cualquiera **se lleva el trabajo sin commitear del
> otro**. Mientras siga así: nadie corre esos cuatro, y cada uno hace `git add` **solo de sus
> ficheros**, nunca `git add -A`.
>   · **PANEL ADMIN** → `app/Filament/**` · `resources/css/filament/**` · `lang/*/admin.php` ·
>     `tests/Feature/Admin/**` · `docs/{PANEL-ADMIN,specs/panel-navegacion}.md`
>   · **PORTADA / LANDING** → `resources/views/{home.blade.php,components/site/**}` ·
>     `public/css/landing.css` · `lang/*/landing.php` · `tests/Feature/Landing/**`
> ⚠️⚠️ **El bloque «REPARTO VIGENTE» de más abajo (línea ~1900) está CADUCADO** —describe los
> carriles A/B/C del 26–28 de agosto y dice «el último usado es `#214`» con el remoto en **`#319`**—:
> se conserva por sus lecciones, **no se usa para elegir tarea ni número**. El número se fija al
> EMPUJAR mirando `origin/main` en el mismo comando.
>
> ═══════════ CARRIL 3 · EL LIBRO DEL PEDIDO (spec ✅ · T1 → T3·4 EN EL ÁRBOL: código COMPLETO · queda el OJO del owner) ═══════════
> ❗❗❗ **2026-09-01 (13:00 → 13:30, misma sesión) · EL LIBRO DEL PANEL VA PLEGADO DETRÁS DE UN CTA Y EL
> ATAJO «VER HISTORIAL COMPLETO» BAJO EL LIBRO SE RETIRA** (`DECISIONES #318`, `[DECIDIDO owner]` con
> la T4 delante: *«de un vistazo todo claro; le dan al CTA y se muestra todo con detalle»*). Suite: el
> CONTADOR de arriba · Pint · docs-check · **4/4 mutaciones con control** (quitar un `x-show` · devolver
> el atajo, vistas por las guardas O y Q · meter el Total en el pliegue) · **sonda en el panel real
> 17/17** (`LB-CORTESIA`, un solo login por el limitador; capturas plegado/abierto). ▶ Un solo partial
> (`reservation-financials`) en sus tres sitios: plegado enseña Total · Pagado · saldo; «Ver el
> desglose» / «Cerrar el desglose» (Alpine, `aria-expanded`) abre movimientos y pagos con la nota del
> motivo; el HTML lleva SIEMPRE todas las líneas (guarda M) — ⚠️ `x-data` va ANTES de `data-book`
> porque la guarda cuenta `data-book>`. Revierte D-T3·1 y la adenda 4 de la T5 (`hasHistoryToExplain()`
> murió con su consumidor; guardas O y Q re-apuntadas). ⚠️ **Asunción explícita**: «Ver historial
> completo» aparecía en TRES sitios; se retiraron los dos bajo el desglose y se conservó la puerta de la
> tarjeta «Detalles» — si también sobra, es una línea. ⚠️ Trampa de sonda: `/admin/login` casa con
> `/\/admin(\/|$)/`; la señal de haber entrado es SALIR del login. Cierra la pregunta de presentación
> (plegar «Pagos y devoluciones»): se pliega todo. **V23 para el OJO del owner**: el pliegue en la
> tarjeta de cada reserva y en el modal del calendario.
> ❗❗❗ **2026-09-01 (mañana, 5.ª sesión: 10:10 → 12:40, hora de Madrid) · T4 · EL MOTIVO MANDA EN EL REEMBOLSO,
> LA LIQUIDACIÓN SIMÉTRICA Y «DESCUENTO POR CORTESÍA» — EN EL ÁRBOL** (`DECISIONES #317`;
> `specs/desglose-libro.md` **§6.4 diseño · §6.4.1 lo ejecutado**; `INVARIANTES` `PAY-17`; `MODELO-DATOS`
> §2; `api-v1.md` punto 97; `VERIFICACION-E2E-CAJON.md` §5.sexies «La T4 sobre los mismos pedidos»).
> Suite: el CONTADOR de arriba · Pint · docs-check · **11/12 mutaciones con arnés de CONTROL** (la que
> no muerde es a sabiendas: segunda capa) · **sonda headless 53/53** sobre los 11 `LB-*` · migración
> corrida en local · build N/A (`pl-6` e `italic` ya estaban en el tema compilado; `public/build` no se
> versiona) · `audit-clock` NO corrido a propósito (los fixtures nuevos son relativos, como sus vecinos,
> o fijos en 2000-01-01; ninguno asevera un día concreto). Sin `VERIFY_CONC`: ningún fichero del
> `CRITICAL_RE` cambió.
> ▶ `value_returned` capado a lo debido (modal: `maxValue`, opción deshabilitada, la Σ de remanentes
> tiene que caber; dominio: `exceeds_owed` bajo lock y ANTES de la pasarela, sin dejar ni la fila
> `pending`) · `compensation` con motivo obligatorio (`payment_refunds.reason`, 5–200;
> `compensation_without_note`) y la cortesía SOLO su exceso, con el motivo en `context.note` y en
> `Movement.note` (INTERNO: el panel lo pinta bajo la línea, `LedgerResource` no lo transcribe) · sin
> intención, sin cortesía · D9 bis: `liquidado` con signo, «Devuelto en el parque»
> (`tickets.journal.gate_refund`) · «Descuento por cortesía» en cuatro idiomas · los dos modales con
> TRES motivos y la frase del exceso en vivo; el de línea mide lo debido en SU RESERVA ·
> `RefundIntentGovernsTest` (13) · `OrderBookTest` +2 · `CourtesyMovementTest` +1 · 29 reembolsos de
> tests ganan su motivo.
> ⚠️⚠️ **DOS afirmaciones de §6.4 resultaron FALSAS al ejecutarla** (corrección delante del texto):
> `payment_refunds.reason` NO existía (era `failure_reason`, del gateway → migración
> `2026_09_01_120000`; **docs-check: 93 migraciones**) y «tras la visita, solo compensación» contaba
> el dinero DOS veces → **D-T4·6: «lo debido» es lo debido EN DINERO** (sin lo inferido) y **D-T4·7**
> (el total ofrece «lo debido» solo si cubre el pago entero). **Las dos son VETABLES por el owner.**
> ⚠️⚠️ El arnés de mutación dio **12/12 y mentía** (buscaba «OK (»; el runner de un fichero imprime
> «Tests: N passed»): rehecho por código de salida con pasada de control → 11/12. ⚠️
> `validationMessages()` de Filament solo admite un array (el `Closure` va en el VALOR): el `TypeError`
> tumbó la página del pedido entera y la suite se fue al timeout sin enseñarlo.
> ▶ **RETOMAR (siguiente sesión del carril 3): el OJO del owner** — V18–V21 de §5.sexies (panel, hoja,
> puerta, correos) + **V22**: el modal «Reembolsar» de `LB-BAJADA` (ofrece «lo debido» hasta 19,80 y
> dice que el libro lo da por devuelto en recepción), el de `LB-ORDEN` sin bajada (la opción
> deshabilitada), `LB-CORTESIA` en la ficha (el motivo bajo la línea). **Preguntas al owner**: (1)
> D-T4·6 — ¿lo debido en dinero (hoy) o la lectura literal de §6.4 (tras la visita, solo compensación;
> coste: el dinero contado dos veces)? (2) plegar «Pagos y devoluciones» con un solo cobro. Aparcados
> con ficha (`DEUDA.md`): cancelación (producción) · «Regularizar» · el aviso al bajar tras una cortesía.
> ▶ **Para el agente de la LANDING** (§10·4, retíralo al leerlo): la sesión del libro cerró a las
> 12:40 con todo empujado; no toqué nada de `resources/css`, `resources/views/site`, `resources/js/site`
> ni tus specs. `docs/README.md` dice ahora **93 migraciones** (una nueva del libro): si tu
> `docs-check` local se queja del contador tras el rebase, es eso.
> ❗❗❗ **2026-09-01 (noche, 4.ª sesión) · T3·4 · EL MODELO DE DOS EJES SE RETIRA; EL LIBRO ES EL ÚNICO COMPOSITOR**
> (`DECISIONES #315` — los carriles de assets y landing tomaron `#313` y `#314` entre medias —, `specs/desglose-libro.md`
> **§6.3.6 diseño fino y §6.3.7 lo ejecutado**; `INVARIANTES` `PAY-16`/`PAY-17` REESCRITAS como las
> identidades I1·I3 / I2·I4 del libro, `PAY-10`/`PAY-19` sin las notas transitorias;
> `desglose-dinero-cliente.md` a 📜 HISTÓRICO; `DEPOSITO.md` §11 al libro). Suite: el CONTADOR de
> arriba · Pint · docs-check · **mutaciones de §6.3.7, todas muerden** · **los cuatro verificadores
> sobre MySQL verdes** (`redsys` · `purchase` · `mixed-party` charge y credit) · `audit-clock` sobre
> los tests tocados · `VERIFY_CONC=1`. ▶ **Mueren** `OrderLedger`, `OrderFinancialSummary`,
> `ReservationFinancials`, `GateBuckets`, `OrderAdjustment::breakdownLabel()`, 21 métodos de
> `Order` (§4.7), el puente y `ledger-bridge.json`, los dos tests de servicio, `tickets.ledger.*` y
> las claves del cargo de puerta (es/en/fr) + dos de `admin.*` (es/zh) · **nace `LineFacts`**
> (`charged · depositSplit · editDelta · courtesy` → `birthValue` · `onlineAtBirth` · `onlineNow`,
> SIN cascada) · `MovementLabel::mixed` hereda la frase del suplemento/descuento · **la cortesía
> mide lo debido con el LIBRO** (`OrderBook::owedToCustomerCents`, reserva o pedido según el
> ámbito) y **el reembolso total se prorratea entre RESERVAS por `onlineAtBirth`** (D-T3·24) ·
> `onlineDueCents` = Σ `onlineNow` de las líneas vivas (D-T3·23) · 25 tests re-apuntados,
> `LineFactsTest` nuevo, `OrderFinancialInvariantsTest` = las identidades del libro en 16
> escenarios (+ la prorrata entre dos reservas) · `git grep` del modelo viejo en `app/` → **0**.
> ⚠️⚠️ **Dos tests aseveraban `aCobrarPuerta` tras una bajada desde el panel y leían el CUBO**: el
> libro netea la bajada contra el suplemento en UN saldo — lo que protegen es la línea ESCRITA.
> ⚠️ **Un fixture con `recordEdit(+400)` sin subir la fila hace nacer la línea en 6,00** (nac = fila
> − delta): se legaliza la fila. ⚠️ **Y `ledger_note` quedó en TRES frases** (`under_review` ·
> `expired` · `pending_payment`): el resto eran del modelo viejo y nadie las leía.
> ▶ **RETOMAR (siguiente sesión): la T3·4b — el OJO del owner** sobre las nueve superficies con el
> modelo viejo fuera (cajón «Mis pedidos», panel: bloque + tarjeta + calendario, hoja PDF, puerta,
> los cinco correos, post-form), con el guion headless de `VERIFICACION-E2E-CAJON.md` §5.sexies
> recorrido (11 pedidos, 54 ✓). **En la BD local viven ONCE pedidos `LB-*` del cliente
> `probe-card@jumpweb.test`** —cuatro del sistema, cuatro de demostración y TRES que enseñan los
> huecos (`LB-ORDEN` Total −9,90 con el libro cerrando · `LB-PUERTA` liquidación inferida ·
> `LB-REVISION` «no cuadra» sin acción)— para que el owner los mire en el panel y en el cajón; se
> borran con `Order::where('code','like','LB-%')`.
> ❗❗❗ **EL OWNER YA LOS LEYÓ y decidió (`DECISIONES #316`, 2026-09-01 por la mañana): la T4 del
> libro está DISEÑADA en `specs/desglose-libro.md` §6.4 y APROBADA (*«SÍ, PROCEDEREMOS así»*,
> D-T4·1 confirmado: el motivo es INTERNO). RETOMAR = EJECUTAR LA T4 tal como §6.4 la escribe**
> — el MOTIVO manda en el reembolso («devolver lo debido» no puede exceder lo debido; «compensación»
> con motivo obligatorio y la cortesía es el exceso; con `value_returned` NUNCA hay cortesía: cierra
> `LB-ORDEN`) · la liquidación en el parque SIMÉTRICA (un «a devolver» con la visita pasada se da por
> devuelto en recepción; la inferencia cede ante un reembolso posterior) · la línea se llama
> «Descuento por cortesía» · cancelación: se verá en producción · «Regularizar»: aparcado (fichas en
> `DEUDA.md`). ⚠️ Sigue ABIERTA una pregunta de presentación: plegar «Pagos y devoluciones» cuando
> solo hay un cobro. ▶ **Al retomar: hacer la T4** —los dos modales de `ViewOrder` (importe capado a
> lo debido, opción deshabilitada con 0, `Textarea` de motivo obligatorio con «compensación», la
> frase en vivo del exceso), el tope por motivo en `Order::executePartialRefund`/`executeFullRefund`
> bajo lock (`exceeds_owed`), la cortesía SOLO con `compensation` y con el motivo en
> `payment_refunds.reason` + `context.note`, D9 bis en `OrderBook::reservation()` («Devuelto en el
> parque», `tickets.journal.gate_refund`), `Movement.note` interno (NO viaja por la API), la etiqueta
> «Descuento por cortesía» en cuatro idiomas, `PAY-17`— con las SEIS guardas y mutaciones de §6.4;
> después re-sembrar `LB-ORDEN` (tiene que ser IMPOSIBLE) y `LB-BAJADA` con la visita pasada, pasar
> la sonda §5.sexies y borrar las tres sondas viejas (`T4-PRB01`, `R-IBX8B1`, `R-D3AN8Q`). `Order.php`
> no está en el `CRITICAL_RE`: sin `VERIFY_CONC` salvo que se toque `MixedPartySurcharge`/
> `OrderItemEditor`. Numera la decisión mirando el remoto: hoy chocó DOS veces. Y dos cosas que NO
> son del libro pero quedaron escritas: la pregunta white-label de las fotos (`#313`) y el AFORO del
> horizonte de franjas (`#307`).
> ❗❗❗ **2026-09-01 (noche, 3.ª sesión) · T3·3 · LOS CORREOS PINTAN EL LIBRO; CAE EL TOPE DEL DESCUENTO**
> (`DECISIONES #312`, `specs/desglose-libro.md` **§6.3.4 diseño fino y §6.3.5 lo ejecutado**;
> `cumple-mixto.md` §20 con la corrección; `INVARIANTES` `PAY-16`/`PAY-17`/`PAY-19` con la nota).
> Suite: el CONTADOR de arriba · Pint · docs-check · **6 mutaciones (una pasó en verde por la
> tarjeta de producto → acotada a la fila `<tr data-book-total>`)** · **los cuatro verificadores
> sobre MySQL verdes** (`mixed-party` ×2 · `redsys` · `purchase`) · `audit-clock` sobre los tests
> tocados · `VERIFY_CONC=1`. ▶ `MixedPartySurcharge::applyCredit` **sin tope**: el descuento se
> escribe ENTERO y lo que la puerta no absorbe es saldo «a devolver en el parque» — mueren
> `gateCoverageCents`, `inFavourCents`, `OrderLedger::inFavourHint` (a `null` hasta la T3·4) y el
> «a tu favor» en seis superficies y cinco claves · `EmailBookBlock` + `emails.partials.book`: los
> cinco correos de dinero pintan el libro AL ENVIAR (guarda P: el reenvío tras una edición dice el
> saldo nuevo) · `OrderItemModified` solo con `changes`; `OrderItemEditor::creditReduction` retirado
> (la bajada es `recordEdit(−Δ)`; las dos cifras del OUTCOME siguen para la notificación del
> operador) · B/C del tope INVERTIDOS al libro y fuera del puente (`ledger-bridge.json` en 13) ·
> `LedgerSingleSourceTest` sin lista de excepciones. ⚠️⚠️ **El modelo viejo deja de cerrar para un
> crédito sin cobertura A PROPÓSITO**: no restaures el `min()`. ⚠️ `jumpParty` facturaba «total =
> la parte online» (ilegal para I1): legalizado. ⚠️ Trampa: un corte por índice de `"    }\n"` casó
> dentro de una llave más sangrada → dos `}` → la suite en paralelo lo enseña como un fatal del
> `ExceptionHandler` sin línea; `php -l` lo dice.
> ▶ (La T3·4 que aquí se dejaba anotada está HECHA: el bloque de arriba.)
> ❗❗❗ **2026-09-01 (tarde-noche, 3.ª sesión) · T3·2 · EL PANEL, LA HOJA Y LA PUERTA PINTAN EL LIBRO**
> (`DECISIONES #311`, `specs/desglose-libro.md` **§6.3.2 diseño fino y §6.3.3 lo ejecutado**;
> `identidad-qr-puerta.md` A·3 corregido). Suite: el CONTADOR de arriba · Pint · docs-check · build
> (el tema del panel: `opacity-70` no estaba compilado) · **6 mutaciones, todas muerden a la
> primera** (M ×2 · L · N/Q · O · P) · presupuestos de la puerta intactos · `audit-clock` sobre los
> ficheros tocados. ▶ **UN PINTOR** (`reservation-financials.blade.php`, recibe un `OrderBook`)
> para el bloque «Totales del pedido», la tarjeta de cada reserva y el modal del calendario —
> `order-totals` solo pone el aviso `!is_consistent` y el atajo al historial— · las dos tablas con
> Total/Pagado del libro · `ViewOrder` sugiere `owedToCustomerCents()` · la hoja con precios =
> líneas de producto + libro + UNA caja de saldo por clase · la puerta con `paidCents` +
> `balanceKind` + `balanceCents` y la tarjeta por CLASE («a devolver» con la misma alerta que «a
> cobrar»; «en revisión» nunca dice «nada pendiente») · claves `admin.orders.book.*` (es · zh_CN) y
> las viejas retiradas · `OrderBook` gana `hasHistoryToExplain()`, `owedToCustomerCents()`,
> `paymentMethod()`. Guarda M nueva (`BookSurfacesParityTest`: cuatro superficies = la lista de la
> API, ni una línea de más ni de menos) y guarda L (`LedgerSingleSourceTest`: nadie lee el modelo
> viejo; `STILL_ON_THE_OLD_MODEL` = `OrderConfirmation`, solo encoge). ⚠️⚠️ **DIEZ ficheros de tests
> tenían fixtures que el libro rechaza** («pagados» sin `Payment`, totales ≠ Σ líneas, ajustes de
> puerta sin cambio de valor, devoluciones sin fila): legalizados todos, ninguna identidad
> excepcionada. ⚠️ El «a tu favor» sigue en `items-list`, hoja y puerta hasta la T3·3 (el tope).
> ⚠️ Sin `VERIFY_CONC`: ningún fichero del `CRITICAL_RE` cambió.
> ❗❗❗ **2026-09-01 (tarde, 3.ª sesión) · T3·1 · EL CONTRATO, LA API Y EL CAJÓN PINTAN EL LIBRO**
> (`DECISIONES #310` — el carril de la landing tomó `#309` entre medias —, `specs/desglose-libro.md`
> §6.3 el diseño fino de la T3 en CUATRO sub-tandas y **§6.3.1 lo ejecutado**; `specs/api-v1.md`
> §10.octodecies, puntos 94–96). Suite: el CONTADOR de arriba · Pint · docs-check · build + build:ssr
> · **6 mutaciones (4 PHP + 2 JS): 5 mordieron a la primera y la de `shows_deposit_note` sin la
> clase del saldo pasó en VERDE → dos casos nuevos, ahora muerde** · el puente de la T2 sigue
> idéntico. ▶ `openapi/v1.yaml` → `Ledger` del libro (incompatible a propósito: 0 LIVE y el único
> consumidor cambia en el mismo commit) · `LedgerResource` TRANSCRIBE `OrderBook` · `OrderResource`
> / `OrderItemResource` por pedido y por reserva (`shows_deposit_note` = cobrado ∧ `has_deposit` ∧
> `pay_at_park`) · el cajón (`orders.js` + `PurchaseCard.vue`) pinta movimientos · Total ·
> liquidaciones · Pagado · saldo por clase, y con `is_consistent=false` Total, cobros y la frase
> (`#132`) · `outcome.js` saca la confirmación del libro · `ledger.no_cuadra` en el log (por pedido)
> · una línea fantasma a 0 € no genera movimiento. ⚠️⚠️ **Cuatro fixtures ILEGALES legalizados**:
> tres «pagados» SIN `Payment` (`OrderSummaryFieldsTest` ×2, `SidebarDomContractTest`) —con I2
> responden «en revisión»— y uno sin líneas facturando 1.000. ⚠️ Con DOS reservas, cada línea de
> valor lleva delante el nombre de la suya; dos cancelaciones del mismo segundo las ordena el
> desempate del libro (localiza por etiqueta). ⚠️ **Trampa de la sesión**: restaurar una mutación de
> JS con `git checkout` deja la fuente MÁS NUEVA que el bundle SSR y `assertBundleIsNotStale` pone
> 35 rojos en la suite completa — no es un fallo, es la guarda: `npm run build && npm run build:ssr`
> antes de la suite final. ⚠️ El reporter de `node --test` resume con `ℹ pass/fail`: una mutación
> grep-eada con `# pass` sale MUDA. ⚠️ La cabecera de `#309` (landing) venía como «## #309 — …» y
> docs-check exige «## #N ·»: normalizada, como con `#307`. `audit-clock` ✓ 10/10 fronteras sobre los
> 7 tests con calendario de la tanda. ⚠️ Sin `VERIFY_CONC`: ningún fichero del `CRITICAL_RE` cambió.
> ❗❗❗ **2026-09-01 (madrugada, 2.ª sesión) · T2 · EL LIBRO EN EL DOMINIO, EN EL ÁRBOL**
> (`DECISIONES #308` — el carril de la landing tomó `#307` mientras corría esta sesión —,
> `specs/desglose-libro.md` §6·T2 y **§6.2 lo ejecutado**). Suite de esta tanda sola: 3741 /
> 24.673 (la cifra VIVA es el CONTADOR de arriba) · Pint · docs-check · **7
> mutaciones muerden** · `audit-clock` sobre los tres tests con calendario · **puente IDÉNTICO en
> los 15 escenarios** (pedido y reserva, los del tope incluidos) · **corpus local 37/40** (2 en
> revisión en los DOS modelos, y `R-REM7YW`, donde el modelo viejo se contradice entre pedido y
> reserva y el libro dice lo mismo en los dos niveles). ▶ `Booking\Services\OrderBook` (+
> `Movement` · `Settlement` · `Balance` · `MovementLabel`): por pedido y por reserva, lectura pura,
> sin catálogo, sin consultas, sin nombrar `Payments\Models` (`Order::collectedPaymentFacts()` /
> `refundFacts()` traducen en la costura); I1–I4 en ejecución; 17 etiquetas `tickets.journal.*`
> en es/en/fr/zh_CN; guardas G–K. **Ninguna superficie ni el contrato se tocaron: `OrderLedger`
> sigue siendo la pantalla.** ⚠️⚠️ **La T1 escribía una CORTESÍA FALSA con «también cancelar»**
> (el flujo real del panel): lo debido se medía ANTES de aplicar la cancelación que viaja con el
> reembolso → 40,00 € enteros como «Compensación» sobre un pedido cancelado. Lo cazó la guarda del
> libro; corregido en `executeFullRefund` y `executePartialRefund` (+ 2 casos con puente).
> ⚠️ Dos fixtures más legalizados (reembolso sin su cortesía → flujo real) · `has_deposit` por
> reserva era CATÁLOGO en el modelo viejo (el libro: hecho, D-T2·1) · el arnés de mutación nació
> ciego por el color ANSI delante de «Tests:». ⚠️ Sin `VERIFY_CONC`: ningún fichero del
> `CRITICAL_RE` cambió (lo de `Order` es lectura y el orden de dos pasos bajo un lock ya tomado).
> ❗❗❗ **2026-09-01 (madrugada) · T1 · LOS HECHOS, EN EL ÁRBOL** (`DECISIONES #306`,
> `specs/desglose-libro.md` §6·T1 y **§6.1 lo ejecutado**).
> - Antes, suite 3716 (24.131 aserciones,
> 1 skipped a propósito) — medida por el `pre-push` sobre el árbol CONJUNTO tras
> integrar `#300`–`#304` de la landing; el carril del libro por sí solo daba 3711 / 24.214) · Pint ·
> docs-check · `audit-clock` **verde en las 12 fronteras** · foto puente de `78265ec` **idéntica en 15/15 escenarios** (lo que se pinta no se
> movió un céntimo) · migración corrida sobre la BD local (36 filas → 43 en cuatro tipos, cero
> tipos viejos) · **el fantasma de la señal, cerrado**: `T4-PRB01` pasa de «20,00 € pendiente /
> 30,00 € cobrados» a **0,00 / 10,00** en la card, y su total fabricado queda «en revisión» (I1).
> ▶ `order_adjustments.type` es el ÚNICO discriminador (`deposit_split` · `edit` · `mixed` ·
> `courtesy`; la columna `kind` de la spec no hizo falta); `Order::recordEdit` escribe cada gestión
> como UNA fila con su delta entero (muere la cascada y el marcador de 0 €); la cortesía se
> escribe al reembolsar; `GateBuckets` replica la cascada EN LECTURA y **muere en la T3**.
> ⚠️ Tres fixtures resultaron ILEGALES (un `Order.total` = la parte online; un pack cancelado
> «pagado» que ningún cobro incluía; una bajada 3→1 escrita como −12,00): se legalizaron.
> ⚠️ Dos repairs legacy retirados y sus migraciones neutralizadas.
> ▶ ~~RETOMAR: la T2~~ **HECHA** (bloque de arriba, `#308`): se retoma por la **T3**.
> ▶ **Para el agente de la LANDING** (§10·4, retíralo al leerlo): la cabecera de tu entrada `#307`
> iba como «## #307 — … (fecha)» y `docs-check` resuelve las citas con `^## #N ·`, así que cualquier
> línea que cite `#307` junto a la palabra de las decisiones rompía el gate; al rebasar el libro
> encima la puse en el formato canónico «## #307 · 2026-09-01 · …» sin tocar el contenido.
> ❗❗❗ **2026-09-01 (noche) · EL DESGLOSE PASA A SER UN LIBRO — `DECISIONES #305`,
> `specs/desglose-libro.md`.** El owner: el balance de dos ejes «exige razonar»; quiere cada gestión
> como una línea + o − con su fecha, un Total y un SALDO que se liquida EN EL PARQUE (`[DECIDIDO
> owner]`: **nada se cobra ni se devuelve online post-reserva** —rectificó su primer planteamiento—;
> sin regímenes señal/sin señal; el descuento mixto entra en el saldo, así que cae el tope de la T4;
> el reembolso manual del panel se queda como línea «Devuelto»). ▶ **Medido antes de diseñar**: un
> prototipo de lectura con las fórmulas de la spec §4.1 coincide con `OrderLedger` en **19/22**
> pedidos locales (total, saldo, cobro y nacimiento); los 3 restantes son datos SUCIOS que las
> identidades nuevas marcan (`R-IBX8B1`/`R-D3AN8Q` pagados sin pago; `T4-PRB01` fabricado con
> `Order.total` = la señal). ⚠️⚠️ **Tres hechos faltan hoy y son la raíz del fantasma de la señal**
> (spec §1.3): el marcador de 0 € de la bajada online, el resto de una bajada parcialmente cubierta,
> y la compensación derivada al leer. ▶ Plan §6: **T1** hechos (sin cambiar lo que se pinta) ·
> **T2** el libro en el dominio con el modelo viejo de ORÁCULO (guarda puente) · **T3** las nueve
> superficies + el tope + la retirada entera (§4.7). Cada tanda con `VERIFY_CONC=1`.
> **El owner dio el ✅ en la misma sesión y la T1 se ejecutó a continuación (bloque de arriba).**
> ⚠️ Los prototipos de medición viven en el scratchpad de la sesión, no en el repo.
>
> ═══════════ CARRIL 1 · RESERVAS MIXTAS (T1–T6 hechas: el plan de `#284`, COMPLETO) ═══════════
> ❗❗❗ **2026-09-01 · T6 · EL GUARDIÁN DE SOLAPES EN EL DOMINIO, EN EL ÁRBOL** (`#299`,
> `specs/cumple-mixto.md` **§26** diseño fino · **§26.5 ejecución**).
> - Antes, suite 3709 (24.037 aserciones, 1 skipped) — cifra del árbol CONJUNTO
> tras rebasar el carril de la landing (`#300`→`#304`) encima; la de esta tanda sola era 3704 /
> 24.120. ⚠️ **Las aserciones BAJAN aunque los tests suban**: el otro carril retiró guardas cuyo
> sujeto desapareció. · **`audit-clock` verde en
> las 12 fronteras al cierre** (incluida la del 05-09, la fecha que habría volteado los tests
> viejos de `MePrivacyTest` que la D8 pasó a relativas) · **2/2 mutaciones muerden** (sin el hook de
> `saving`: 4 rojos; sin el dirty-check: cae el solape preexistente) · el verificador mixto en
> verde en sus DOS escenarios con el guardián activo. ▶ El hueco **G** de `#284`: el guardián
> vivía SOLO en el form del catálogo — ahora `TicketType::overlappingAgeSibling()` es la verdad
> ÚNICA (nulos como 0/255) y el `saving` del MODELO revienta el solape
> (`OverlappingAgeRangeException`, con el hermano dentro) y el invertido; el form DELEGA y
> conserva su aviso. ⚠️ Valida SOLO al tocar los TÉRMINOS del tramo (un solape metido por la
> puerta de atrás sigue editable en lo demás; tocar sus tramos exige sanearlo). ⚠️ Límite honesto:
> los eventos de Eloquent no ven `Query\Builder::update()` ni SQL crudo — quedan los cinturones
> (lector por menor edad; el sellador no frena ventas). ⚠️⚠️ **Dos fixtures ILEGALES legalizados,
> no excepcionados** (el tri-familia de la T5 y el de cobertura de la T4 creaban solapes
> TRANSITORIOS): *un fixture que necesita un estado que el dominio prohíbe prueba un mundo que no
> existe*. ⚠️ Y un grep por clave i18n dijo «el form no tiene tests» — mentira del instrumento:
> `CatalogGuestAgeFamilyTest` asevera por CONDUCTA (10 casos). Con el sello, un solape ya no
> mueve dinero: la tanda cierra la frase «por construcción es imposible». **Queda el OJO del
> owner sobre T5+T6.**
> ❗❗❗ **2026-08-31 (noche, 2.ª sesión) · T5 · LAS PALABRAS, EN EL ÁRBOL** (`#298`,
> `specs/cumple-mixto.md` **§25** diseño fino · **§25.10 ejecución** · §25.9 las TRES decisiones
> del owner: «Liquidado en el parque» · la puerta de D8 en las TRES vías · el correo del manual
> entra). Suite de entonces: 3698 (24.112 aserciones; el delta sobre el cierre de la T5 son las
> guardas N, O, P y Q de las adendas — la VIVA, arriba en el bloque de la T6) · JS **878** ·
> **10/10 mutaciones muerden, vistas en rojo una a una** · sonda `/root/e2e/t5.js` 10/10 ✓
> (4 capturas, las dos críticas miradas). ▶ **D9**: las TRES claves que decían «Pagado en el
> parque» dicen «Liquidado…» (es/en/fr/zh_CN); `paid_desk` intacta (cobro REGISTRADO);
> `TYPE_COLLECTED_IN_PERSON` sigue declarada y sin uso — el registro real es feature aparte.
> ▶ **D8**: la supresión con reserva POR CELEBRAR se bloquea en las TRES vías (409
> `account_has_upcoming_reservations`; el cajón sin tocar NI UNA LÍNEA; el panel audita el
> bloqueo) — el criterio es `upcomingItems()` (el de la pantalla del titular), NO el complemento
> de `terminated()` (una línea sin franja bloquearía el art. 17 PARA SIEMPRE); `RGPD-01` ampliada
> y la ficha del «techo tras anonimizar» CONSTRUIDA. ⚠️⚠️ **Tres tests de `MePrivacyTest`
> borraban una cuenta con reserva pagada FUTURA** (fecha `2026-09-05` clavada: desde el 05-09
> habrían pasado solos) → fechas RELATIVAS. ▶ **Correos**: las sobre-promesas eran DOS —
> `reduction_pending_refund` reescrita (sin canal ni correo prometidos; el puntero «Mis reservas»
> también había caducado) y los correos de reembolso ganan el MODO (`when_manual`: «se te ha
> devuelto en el parque; este correo es tu justificante»); `order_item_modified.refunded`
> RETIRADA (muerta, con promesa de tarjeta dentro). ▶ **Panel**: ⚠️⚠️ **una afirmación de §18.5
> resultó FALSA al medirla** («el 0,00 € se resuelve solo con la T4» — no se resolvió): filtro de
> PRESENTACIÓN `visibleUpgrades()` (⚠️ `upgrades` es pieza de CARGA: `creditTargets()` necesita
> la dirección barata; el mismo precio conserva su línea) · lo ESCRITO primero y las condiciones
> etiquetadas con clave propia (`conditions_line`; `line` la comparte la hoja) · la cadena por
> LADOS (el desfase del cargo ya no se lo traga el portador del descuento ausente) · `frozen` en
> neutro · primeras aserciones de `drift`/`net`/`missing_credit_carrier` (eran CERO). ▶ **Y el
> «+-4,00 €» que cazó el OJO del owner en mitad de la tanda**: el `+` clavado de las ↳ de los DOS
> partials del panel (escondido tras el «Ver más» plegado, donde la sonda de la T4 no miró) →
> signo consciente. ⚠️ Trampas pagadas (§25.10): el `git checkout` que se llevó trabajo sin
> commitear · la guarda J imposible con el portador vivo (EL PROPIO CARGO cuenta como cobertura)
> · la memo estática de `Setting::value` sin `flushMemo()` · el doble de `ModuleContractsTest`
> crasheando paratest al crecer el contrato. ⚠️ Residuos asumidos: filas de audit del bloqueo en
> `probe-card` (el rastro que D8 promete) y capturas en el contenedor. ▶ **Adenda de la misma
> noche** (pregunta del owner, §25.10 «sonda B»): pedido `T5-PRB01` fabricado a petición suya para
> mapear los saldos a favor — la única ↳ negativa posible es el descuento mixto (las bajadas
> absorbidas se NETEAN); ⚠️⚠️ destapó que «Pendiente de devolución» salía −30,00 en el bloque y
> 30,00 en la card de la MISMA pantalla → `[DECIDIDO owner]` la card gana el «−» (guarda N, vista
> en rojo); el correo nuevo de la reducción, verificado en vivo en Mailpit. ▶ Y su SEGUNDO
> hallazgo: el historial decía «Cambió: cantidad» SIN los números (la forma que `#145` dejó fuera,
> con `from/to_quantity` guardados desde siempre) → `[DECIDIDO owner]` entra «Cantidad: 4 → 2»
> (guarda O, vista en rojo y mirada en vivo). ▶ Y su TERCER hallazgo, con la hoja en papel: el
> bloque mixto con importes salía en la hoja SIN precios — no era despiste (la T3 lo decidió «a
> sabiendas», §23.2, por su hueco E), pero visto en papel `[DECIDIDO owner]` lo REVISA: **la
> operativa lleva los HECHOS y ni un euro; los importes, solo en «Con precios y desglose»**
> (guarda P vista en rojo; la corrección escrita DENTRO de §23.2). ▶ Y la adenda 4 (01-09, sobre
> `T4-PRB01` que él mismo había editado): su objeción de diseño ACEPTADA — el «Ver historial
> completo» sale también junto al desglose cuando hay consecuencias que explicar (guarda Q con
> control) — y ⚠️⚠️ su «Pendiente de devolución −20,00» era un **FANTASMA medido** (la
> reconstrucción usa la señal VIVA del catálogo; el pedido nació con otra): divergencia
> pedido-vs-card en vivo → **ficha nueva en `DEUDA.md`** (el hermano de la señal del caso espejo;
> el boceto del arreglo, dentro). ⚠️ Dos trampas pagadas en §25.10: el extractor de bloques PHP de
> Blade contra el primer `@php(` del fichero, y que UN botón de Filament emite `mountAction`
> cuatro veces (la aguja es `wire:click=`). ▶ Adenda 5 (01-09): «al historial le falta» era la
> PÁGINA 1 de 3 — todo estaba registrado; el default sube a 10/página (`[DECIDIDO owner]`) y el
> NACIMIENTO del pedido online queda FUERA del historial a propósito (`[DECIDIDO owner]`,
> registrado para que nadie lo reabra: solo lo escriben los manuales). `T5-PRB01` queda en la
> BD local (2 plazas de la franja real del 17-09). **Queda el OJO del owner;
> lo siguiente es la T6** (el guardián de solapes fuera del formulario; la fase 3 de §20.2
> despierta a §16; el AFORO sigue aparcado por el owner).
> ❗❗❗ **2026-08-31 (noche) · T4 · EL −X €, EN EL ÁRBOL** (`#296`, `specs/cumple-mixto.md` §20
> diseño de producto · §24 diseño fino · **§24.10 ejecución**).
> Suite de entonces: 3679 (24.017 aserciones, 1 skipped a propósito; árbol CONJUNTO sobre `#297` — la VIVA, arriba en la T6) ·
> JS **878** · **7/7 mutaciones muerden** · guardián de invariantes **11 → 15 escenarios** (A/B/C
> con la línea a MANO antes del reconciliador; la identidad D cazó el `continue` en rojo, como §24.1
> predijo) · verificadores: aforo SEIS + redsys + **el mixto en sus DOS escenarios** (`credit` visto
> FALLAR sin el lock: 12 líneas y −84,00 €) · sonda `/root/e2e/t4.js` (postform, panel, puerta con
> la aritmética en vivo —46,00 = 50 − 4—, cajón con el «a tu favor», hoja PDF; capturas en
> `/root/e2e/t4-capturas/`). ▶ El ESPEJO: línea `is_credit` + `extra_due` gemelo negativo, tope
> `min(derivado, cobertura de puerta)` con los cargos de la pasada contando como cobertura; el
> exceso NO se escribe — es el «a tu favor», hasta «Mis pedidos» y la API (`in_favour_hint`, patrón
> `L6`; `[DECIDIDO owner]` con el coste delante). ⚠️ La asimetría del silencio: el cargo crece en
> silencio (`#268`), el crédito NO se mueve sin veredicto que gobierne. ⚠️ El `context` del crédito
> JAMÁS lleva `changes.*` (la trampa §16.5.bis, aseverada). ⚠️ Las frases de `#246` («no se
> descuenta solo») CADUCARON en las 4 superficies + correo. ⚠️ Portador PROPIO «Descuento fiesta
> mixta» (migración 91; `mixed_party.credit_product_id`). ⚠️ Sondas `T4-PRB01/02` y 2 correos en
> Mailpit quedan en local, asumidos. **Queda el OJO del owner**; ~~lo siguiente es la T5~~
> **HECHA la misma noche, 2.ª sesión (`#298`, bloque de arriba)**.
> ❗❗❗ **2026-08-31 (noche) · T3 · EL PARQUE DECIDE, EN EL ÁRBOL** (`#294`,
> `specs/cumple-mixto.md` §23 diseño · **§23.10 ejecución**).
> Suite de entonces: 3664 tests / 23.753 aserciones (conjunto sobre `#295`; la VIVA, arriba) ·
> **12/12 mutaciones muerden** (incluida la del DISPARADOR: el modal sin llamar al writer) ·
> los TRES verificadores sobre MySQL (mixto · aforo en sus SEIS escenarios · redsys) · sonda
> headless sobre `T0-PRB01` (guion `/root/e2e/t3.js`, 4 capturas + el PDF en
> `/root/e2e/t3-capturas/`, **miradas**). ▶ **E**: hoja de sala y tarjeta de puerta imprimen lo
> ESCRITO del suplemento (nunca el veredicto; del veredicto solo el caso barato y las edades sin
> producto). ⚠️⚠️ La guarda de presupuesto cazó un **N+1 preexistente de la puerta** con cualquier
> `extra_due` (las etiquetas caminaban `adjustment->orderItem->ticketType` por fila): eager
> anidados en el reader, techo de `GateProfileTest` 25 → 28 a cambio de lotes constantes.
> ▶ **F**: pestaña «Invitados» del modal Gestionar por la MISMA puerta que el cliente
> (`OrderItemGuestDataWriter` → `submitGuestForm(guests, null, 'panel', $by)`): `via: panel`,
> actor operador, correo con la voz del parque; el writer es control negativo del gate. ▶ **D7**:
> bajar del mínimo con `orders.edit_item_below_minimum` re-exigido en el editor + interruptor,
> audit `below_pack_minimum` solo al usarse; máximo y `>= 1` siguen; la web sigue exigiendo el
> mínimo. ⚠️ Los dos permisos nuevos en `staff` (`[DECIDIDO owner]` Q1·a). ⚠️ **La presencia de la
> pestaña NO se asevera con `assertSee`** (modal = `wire:partial`, la trampa del waiver §9.7): por
> CONDUCTA — el form solo dehidrata campos declarados. ⚠️ Dos usuarios de test sobre el MISMO rol
> se roban los permisos con `sync()`: rol propio por empleado en el fichero nuevo. ⚠️ La sonda usó
> `puerta.window_days=30` TEMPORAL (restaurado: fila borrada) y dejó filas de audit del escaneo en
> `T0-PRB01`, asumidas. **Queda el OJO del owner** (capturas listas). ~~Lo siguiente era la T4~~
> **HECHA la misma noche (`#296`, bloque de arriba)**. ▶ ~~LA RETOMA REAL ES LA T5~~ **HECHA en la
> 2.ª sesión de la noche con su §25 escrito ANTES del código y las tres preguntas numeradas
> (`#298`, bloque de arriba): la retoma real pasa a ser la T6.**
> ❗❗❗ **2026-08-31 (tarde-noche) · T2 · UNA EDAD SIN PRODUCTO + EL DINERO SOLO AL GUARDAR
> COMPLETO, EN EL ÁRBOL** (`#289`, `specs/cumple-mixto.md` §22 diseño · §22.9 ejecución).
> Suite de entonces: 3639 tests / 23.658 aserciones — cifra del árbol conjunto de aquel cierre,
> tras rebasar `#290` (el sistema de etiquetas) encima de la T1/T2 de mixtos (la VIVA, arriba: es
> la única copia que lee el gate) · **7/7 mutaciones muerden** ·> `mixed-party:verify-concurrency` en verde · sonda headless (2 capturas en `/root/e2e/t2-capturas/`).
> ▶ «Completo» son DOS preguntas (dinero: todas las edades declaradas · formulario: además ninguna
> edad sin producto). ⚠️⚠️ **La puerta vive en `reconcile()` y vale también para el panel**
> (`[DECIDIDO owner]`): con una edad en blanco no se escribe NADA — hasta hoy crecía (`#268`). ⚠️
> Tres textos por instalación en Ajustes → Web → «Fiestas por edad» (`:phone`; cadena pedido → app →
> es → en → fr). ⚠️ **Hueco de contrato preexistente cazado**: `GuestFormResource` daba `general`
> vacío como lista `[]` (el contrato dice objeto). ▶ ~~Lo siguiente es la T3~~ **HECHA la misma
> noche (`#294`, bloque de arriba)**.
> ❗❗❗ **2026-08-31 (tarde) · T1 · EL SELLO ESTÁ EN EL ÁRBOL** (`#288`).
> Antes de la T2 la suite estaba en 3616 (23.514 aserciones; **tras rebasar sobre `#287`**: sus 3605
> + los 11 de la T1, medidos) · **JS 877** (sin tocar) ·
> **13/13 mutaciones muerden** · verificadores sobre MySQL con control negativo · sonda del hueco A
> en navegador (3 capturas en
> `/root/e2e/t1-capturas/` del contenedor, fuera del repo). **Si entras nuevo: `specs/cumple-mixto.md`
> §18 (la visión) y §21 (el sello: diseño en §21.1–§21.12, ejecución en §21.13); lo siguiente es la
> T2.** ▶ El owner aprobó el diseño con tres decisiones (§21.8): retirar `#283` y dejar la frase ·
> **al mover de DÍA se conservan los tramos y solo el precio sigue al día** (su objeción corrigió mi
> recomendación) · un hermano creado después no existe para lo vendido. ⚠️⚠️ **Dos huecos
> PREEXISTENTES cazados de paso**: un día sin tarifa CANCELABA el suplemento, y una fiesta mixta con
> cargo **no podía cambiar de pack** desde el panel (`orphan_addons`). ⚠️ `nextRequest()` ya no hace
> falta (lector sin estado). ⚠️ El `php artisan serve` del contenedor estaba MUERTO al arrancar
> (SIGTERM a las 14:29 local; `docker compose restart laravel.test` lo devuelve): si el `curl` del
> arranque da `000`, es eso. ⚠️ Local: 6 reservas vivas re-selladas a mano (§21.12); `T0-PRB01`
> vuelve a estar como la dejó el T0 (8,00 €) y **el precio de Jump está restaurado a 15,00 €**.
> ⚠️ La sonda dejó, a sabiendas, **2 correos en Mailpit** para `probe-card@jumpweb.test` (el cargo
> pasa de 8 a 12 y vuelve) y dos filas de `audit_logs` en `T0-PRB01`: es la lección del T0 —el correo
> no es transaccional— y aquí se asumió en vez de fingir el aviso. ⚠️ **Dentro del contenedor el
> `serve` escucha en `:80`, no en `:8081`** (el 8081 es el mapeo del host): un guion headless con
> `localhost:8081` da `ERR_CONNECTION_REFUSED`, y un enlace firmado vale solo para el host con el que
> se firmó (`URL::forceRootUrl('http://localhost')` antes de `signedRoute`). `t1.js` lee `BASE` del
> entorno por eso.
>
> ═══════════ CARRIL 2 · TEMA / FACHADA (`#286` · `#287`) ═══════════
>
> ❗❗ **2026-09-01 · LA PORTADA SE REORDENA Y EL RITMO SE IGUALA** (`#314`,
> `specs/idioma-visual-heredado.md` §3.decies). `[DECIDIDO owner]`, tres cosas en un mensaje.
> ▶ **Orden**: entradas → cumpleaños → el parque → ubicación → normas → dudas. En código es mover
> UN bloque (`#zones` detrás de cumpleaños); los anclas y `--hero-air` se mudan solos.
> ▶ **Aire**: `.section` de 96 a **120 px** (80 en móvil). Pero el arreglo real era otro: **la banda
> de cumpleaños no es un `.section`**, llevaba relleno inferior **CERO** y tenía la mitad de aire que
> las demás — y con cumpleaños en segunda posición ese salto quedaba donde más se ve. ⚠️ `.bd-page`
> tiene **DOS** hijos en la portada (`bd-sec1` y el «paso a paso»), no uno: manda el segundo.
> Queda **240 px uniforme en escritorio y 160 en móvil**, medido de los rellenos computados.
> ⚠️⚠️ **Y las reglas de móvil no hacían NADA por estar mal colocadas**: se escribieron en el
> `@media` de 768 que vive ~100 líneas ANTES de las bases, y a igual especificidad gana la última
> del fichero. *El síntoma era el peor: el CSS se lee correcto y el número no se mueve.*
> ▶ **Las 35 fotos, subidas**; las 9 que faltaban como `pjp-NNN.webp`. ⚠️ **Las 26 asignadas NO se
> renombraron**: sus nombres describen la ATRACCIÓN, no al cliente, y pasarlas a `pjp-NNN` metería
> la numeración de este parque en el producto y tocaría el seeder y cuatro tests para nada.
> ❗ **Hay 26 huecos para 35 fotos**: nueve quedan servidas y sin asignar, y varias son cosas que el
> parque TIENE sin dar de alta (arenero, correpasillos, cubo de Rubik, aro) — serían atracciones
> nuevas, o sea DATO. **Hoja de revisión publicada como artefacto para el owner.**
> ❗❗ **UNA GUARDA DEPENDÍA DEL ORDEN Y FALLÓ CON EL PRODUCTO SANO**: `ZonesSectionTest::seccion()`
> recortaba «desde `id="zones"` hasta `id="pricing"`», así que al adelantar tarifas se comió media
> portada y contó cuatro `<h2>`. *Un localizador que depende de qué sección viene después no acota
> una sección, acota un tramo de página.* Re-apuntada al ELEMENTO y más fuerte.
> ⚠️⚠️ **TRES veces midió mal el instrumento, y las tres con cifras creíbles**: la sonda de aire
> contaba **la caja de un contenedor** como tinta (38 px donde hay 240); antes metía a `.bd-page` y a
> su hijo en la misma lista y el aire salía NEGATIVO; y la captura de página completa enseña las
> fotos del carrusel como trama — son 23 `loading="lazy"` en un carril horizontal y solo cargan las
> 3 visibles. *Comprobado desplazándose de verdad antes de «arreglar» nada.*
>
> ❗❗ **2026-09-01 · EL VÍDEO DEL HERO Y LAS FOTOS SON YA LAS DEL PARQUE REAL** (`#313`,
> receta y presupuesto en `INSTALACION-CLIENTE.md` §4.c). 26 fotos + vídeo; `public/images` pasa de
> **8,0 MB / 40 ficheros a 5,2 / 26** y **deja de tener material del PRIMER cliente**.
> ▶ Presupuesto MEDIDO, no a ojo: fotos **WebP 1600 px `-quality 82`** (100–340 KB) y vídeo
> **H.264 720p a 25 fps, `-crf 32` con `-an`** (2,22 MB para 12,88 s = **173 kB/s**, contra los 186
> del anterior). ⚠️ El `-an` no es un detalle: el hero va `muted` y el AAC del original era peso
> muerto. ⚠️ **Ni bajar de 50 a 25 fps**: para un fondo en bucle son el doble de datos sin ganancia,
> y 25 es división exacta de 50 (sin tirón). ⚠️ El póster es el **primer fotograma del vídeo ya
> codificado**, o se ve un salto al arrancar. ▶ Se instalaron DOS vídeos en la sesión; queda
> `parageminiomni.mp4`, que es el que pidió el owner.
> ▶ **Y la CORTINA del hero se aligera** (`[DECIDIDO owner]`: «quita el velo un poco»): de
> **28/40/82 a 18/28/64**. A la altura del titular el alfa baja de 40,1 % a 28,1 % y su peor
> contraste de **8,27 a 7,00** (WCAG pide 3,0 para texto grande). ⚠️ Esa holgura es de ESTE vídeo,
> que es oscuro: con uno claro hay que volver a medir.
> ⚠️⚠️ **La primera medición del velo fue FALSA pareciendo buena**: `video.currentTime = t` desde la
> página lo devolvía a 0 —el vídeo es `autoplay muted loop`—, así que las cinco variantes se
> midieron contra el MISMO fotograma; daban números distintos entre sí e IDÉNTICOS en los siete
> instantes, y lo delató el hash del recorte. *Un valor constante donde debería variar no es un
> resultado: es un instrumento parado.* Se rehízo fuera del navegador, componiendo a mano sobre
> fotogramas sacados con `ffmpeg`.
> ⚠️⚠️ **Los NOMBRES de fichero no se tocan**: las rutas viven en BD, en el seeder y en cuatro tests,
> así que renombrar convertiría un cambio de CONTENIDO en uno de contrato. Cero cambios en la suite.
> ❗ **CINCO fotos están asignadas con DUDA y hace falta el ojo del owner** —`Tirolina`,
> `Basket Jump`, `Barredora`, `Castillo de bloques` y `Circuito High`—: los ficheros de origen se
> llaman todos `header-NNN.png`, o sea que **el nombre no dice qué hay dentro**, y el mapeo se hizo
> mirando. Corregir una es sustituir un fichero, no tocar código.
> ⚠️ Se fueron **14 fotos sin consumidor** (las de la galería que retiró `#309`) y
> `images/historia-seguridad.png`, lo que **CIERRA** esa ficha de `DEUDA.md`. ⚠️ Sigue abierta la
> pregunta white-label: las fotos optimizadas siguen VERSIONADAS y ahora son las del 2.º cliente —
> gitignorarlas dejaría un clon limpio con las fotos rotas, porque `attractions.image` es una ruta
> de BD y no un hueco que falle hacia invisible.
>
> ❗❗❗ **2026-09-01 · CINCO SECCIONES DE LA PORTADA, EN UN ENCARGO** (`#309`,
> `specs/idioma-visual-heredado.md` **§3.nonies**). Suite **3731 verde** (24.224 aserciones, 1
> skipped) · Pint ✓ · docs-check ✓ · `kit:build --check` servible con **7 símbolos** · **3/3
> mutaciones muerden** · Chrome real 1280 y 390: 0 desborde, 0 errores de consola.
>
> ▶ **Zonas**: silueta 44 → **104 px** asomando por encima de la tarjeta; splash más visible.
> ▶ **Tarifas**: friso de tres figuras, fuera la nota de calcetines (`/precios` la conserva, por
> prop) y CTA que baja a `#rules`.
> ▶ **Normas**: fuera la lámina del cliente antiguo; izquierda **pegajosa** con dos requisitos en
> pegatina + mancha grande y su CTA; derecha, **cuatro** normas y enlace a `/normas`.
> ▶ **«En directo»**: retirada con su CSS y su enlace del pie.
> ▶ **Cumpleaños**: fuera el «foam»; toggles al ancho EXACTO de la tarjeta de precio, con icono de
> producto; contorno de la pose sobre la banda de color.
>
> ❗❗ **ESTO REVISA A SABIENDAS EL PRESUPUESTO DE `#292`** («una pieza por sección, tres en la
> portada»): ahora son **cuatro**, y normas lleva dos. Anotado en `IllustrationKit::SLOTS`. Lo que
> NO cambia es lo que vigila `FacadeDecorationIsPerScreenTest`: ninguna pieza dentro de un bucle.
>
> ⚠️⚠️ **TRES DEFECTOS MÍOS que cazó la medición**: (1) **agrandar el splash rompió el criterio de
> `#303`** —9.936 px² sobre el párrafo—; barrido de CINCO combinaciones, ninguna crece sin caer
> sobre el texto → queda el tamaño de `#303` con la opacidad al doble: **0 px²**. *Se ve más porque
> pinta más, no porque ocupe más.* (2) **normas no colapsaba en móvil** y el `overflow: hidden`
> **cortaba titular y botón** a 390 px — **lo vio la captura, no la suite**: ninguna guarda mira
> anchos. (3) la tarjeta de requisito **ofrecía el alta a quien ya tenía sesión**, y lo cazó una
> guarda del nav.
> ⚠️ **Y DOS PREEXISTENTES**: la tarjeta de pack **se salía 11 px de su columna** a 390 (apareció al
> derivar el ancho de los toggles), y retirar la sección vieja **se llevó `.rules-grid`/`.rule`, que
> los usa `/normas`** — lo cazó buscar consumidores por FICHERO y por clase EXACTA.
>
> ❗❗ **LO QUE QUEDA DICHO Y ES DEL OWNER**: el **`sticky` tiene 99 px de recorrido** y a 1280×900 la
> sección cabe entera, así que no se engancha — **las dos cosas pedidas se estorban** (columna
> pegajosa vs. tope de 3/4 normas) y la palanca es el tope. Y la **categoría de cookies `social` ya
> no gatea nada** (ajuste, servicio y frase del banner sin consumidor), a sabiendas: el hueco es el
> de las reseñas. Las dos, con sus salidas, en `DEUDA.md`.
> ⚠️ Trampas: una sonda dio **20.306 px² de «friso sobre titular» y era FALSO** (medía la caja del
> `<div>` de 920 px, no la tinta), y una mutación no mordió porque **la mala era la mutación** —el
> bucle del pie lo manda la lista de RÓTULOS, no la de URLs—.
>
> ❗❗❗ **2026-09-01 · «VISÍTANOS» EN TARJETAS + LOS DATOS REALES DEL CLIENTE** (`#307`,
> `specs/idioma-visual-heredado.md` **§3.octies**). Suite del árbol conjunto **3727 verde** (24.200
> aserciones, 1 skipped); esta tanda sola daba 3720 / 24.106 · Pint ✓ · docs-check ✓ · **5/5 mutaciones muerden** · Chrome real 1280 y 390 con puntero
> grueso: **0 desborde, 0 restos de demo, 0 errores de consola en las 9 rutas públicas**.
>
> ▶ **LOS DATOS DE PLAY JUMP PARK, COMPLETOS** (14 ajustes; **solo en la BD local**, como `#304`).
> ❗ **El producto separa DOS direcciones y hay que respetarlo**: `business.address` es el domicilio
> SOCIAL (Ceutí) y solo alimenta los textos legales; `address.line1/2` es el PARQUE (Lorca) y alimenta
> landing, mapa y el `PostalAddress` de schema.org — por eso `business.city` es **Lorca**.
> ⚠️ **`business.domain` se deja VACÍO a propósito**: el servicio cae al host de la petición, que en
> producción ES el dominio real; fijarlo sería inventarlo desde el email.
> ⚠️⚠️ **El enlace de Maps del owner es de COMPARTIR, no de INSERCIÓN**: la URL de inserción se
> construyó y **se verificó CON CONTROL** (misma URL con ficha falsa → sin ficha y sin chincheta).
> *Un mapa que se pinta no demuestra que sea TU mapa; lo demuestra que el control no lo pinte.*
> ⚠️ Y el primer intento **midió su propio error** (la Embed API exige `<iframe>` y las dos URLs
> decían lo mismo): *cuando el control y el sujeto dan idéntico, no estás midiendo*.
>
> ▶ **«Visítanos» pasa a TRES TARJETAS y UN encabezado** (`[DECIDIDO owner]`: «todo en cards, lo
> siento más organizado y limpio»). Era el caso EXTREMO del molde de `#297` — 4 encabezados para 4
> líneas de dato — y llevaba **tres formas rechazadas** más dos tandas revertidas.
> ❗ **No se propuso una cuarta forma suelta**: se montaron DOS en la web real (`?visitanos=a|b`), se
> midieron y se le enseñaron; las descartó y pidió tarjetas. *La respuesta llegó en un mensaje en vez
> de en una tanda revertida.*
> ❗ **La tarjeta es la PEGATINA de `#303`**, con los valores de `.ride-card` — no un cuarto
> tratamiento. ⚠️ **Sin `:hover`** (no llevan a ninguna parte: afordancia sin consumidor, `#295`) y
> **sin títulos dentro** (ponerlos sería rehacer el molde desde dentro).
> ▶ Entra **`closes_at`** —`HeroStatus` lo calculaba y lo tiraba— y ⚠️⚠️ **no se deduce de
> `weeklyRows()`**: su `is_today` se apaga cuando manda una temporada o una fecha especial (medido en
> vivo: las dos filas en `false`). Sale **«Parking gratis 2h»** y se retira el marcado heredado **con
> su CSS y sus dos `@media`**; ⚠️ `.map-card`/`.map-pin` se quedan porque las usa `/contacto`
> (comprobado por clase EXACTA: un `grep "hours"` casa con `visit__hours`).
> ⚠️ **En móvil CRECE 45 px y se dice**: tres pegatinas cuestan ~100 px de chrome; se recuperaron 52
> (un `margin-bottom` sumándose al `gap`, y el mapa apilado 240→200). Escritorio **679 → 659**.
>
> ▶ **TRES DEFECTOS que los datos reales destaparon.** (1) El pie servía **dos enlaces muertos**
> (`href="#"` a Instagram y TikTok) y un `tel:` a un placeholder: era **el único de los tres
> consumidores** que no comprobaba el centinela. **Arreglado + `FooterContactLinksTest`, 2/2
> mutaciones en rojo.** (2) **`legal.jurisdiction` se lee y no se puede escribir** — sale en aviso
> legal y condiciones y **no está en el panel**; ficha en `DEUDA.md`, y falta el valor, que es del
> owner. (3) Dos `special_dates` de demo decían CERRADO hoy → quitadas y franjas regeneradas
> (`[DECIDIDO owner]`), **y eso destapó algo mayor**: 486 franjas del 24-06 al 02-10 pero **solo 15
> días con más de 5**, así que **no hay disponibilidad más allá de unos días** (el sábado 05-09
> ofrece CERO horas). **NO se tocó**: es AFORO y no estaba en el encargo; ficha con el comando.
> ⚠️ **Una hipótesis mía salió FALSA al medirla**: deduje que se vendían franjas rancias con el parque
> cerrado y el dominio ofrece 17:00–20:00. *El filtro existía; lo que no existe es cobertura.*
> ⚠️⚠️ **Dos instrumentos propios dieron cifras creíbles y falsas**: el área táctil (39 px — medía la
> caja del `<a>` y no el pseudo de `[data-tap]`; lo delató que acusaba también a la forma ya
> verificada en `#264`; la efectiva es **105×44 y 130×44**) y el sondeo del mapa fuera de un iframe.
> ⚠️ Y **Blade compila las directivas dentro de un comentario**: citar `@php` en prosa reventó la
> plantilla con un error señalando el final del fichero — y volvió a caer el comentario escrito para
> advertirlo.
>
> ▶ ✅ **T2 hecha — la cinta `C3` sustituye a la marquesina de `/servicios`** (`#293`): banda de
> tinta a sangre, girada −2,4°, con punto de color entre títulos y bucle lento con token propio.
> ⚠️ **De su `C3` se toma la FORMA, no el contenido ni la FUENTE**: los títulos siguen saliendo del
> panel, y el rotulador **no se usa** porque su propio paquete lo reserva al eslogan y su auditoría
> `T-02` lo limita a una por página — `/servicios` ya gasta la suya en el menú.
> ❗ **El motivo «foam» del cliente antiguo estaba TAMBIÉN ahí**, copiado como geometría en vez de
> con la clase: *un motivo copiado a mano no aparece buscando su nombre*. Segundo rastro retirado.
> ⚠️⚠️ **Dos números de esa pieza parecen adorno y son geometría** —el ancho al 120 % y el alto del
> envoltorio—: sin el segundo la cinta **se recorta a sí misma** (5 elementos, 22 px medidos).
> ⚠️⚠️ **Y dos trampas de instrumento**: la sonda contaba ítems ya invisibles (6 falsos en móvil), y
> el cero solo vale con CONTROL — que en móvil **no muerde**, así que ahí el alto extra es aire.
>
> ▶ ⛔ **T3 — ESTRUCTURA REVERTIDA por `#301`** (`[DECIDIDO owner]`: «deja la sección de zonas como
> estaba antes, 2 cards y debajo la sección de juegos»). Vuelven `#zones` y `#rides` como DOS
> secciones, con la barra de pestañas por zona, las flechas y la barra de progreso.
> ❗❗❗ **PERO EL ARREGLO DE IDENTIDAD SE QUEDA, y es lo primero que hay que saber si tocas esto**:
> la zona se identifica por **`slug`** y `accent` solo pone COLOR. Volver a la estructura **no
> obliga a volver al defecto** — medido en Chrome tras revertir: **un solo carrusel abierto**, y
> pulsar «Zona KIDS» abre exactamente uno.
> ⚠️⚠️ **Y la lección de la jornada: un arreglo puede SOBREVIVIR a la guarda que lo protegía.** El
> caso que lo cazaba vivía en `ZonesAndRidesUnifiedTest`, que vigilaba la estructura unificada: al
> revertirla el fichero se fue entero y **el arreglo se quedó desnudo con la suite en verde**. Red
> nueva: **`ZoneIdentityIsUniqueTest`** (3 casos · 3 mutaciones que muerden · una vigila lo
> CONTRARIO, que la paleta siga saliendo de `accent`, para que nadie «termine el trabajo» moviendo
> también el color y rompa el agrupador).
> ⚠️ **Lo demás de la revisión adversarial se revierte CON SU SUJETO**: anillo de foco, tarjetas
> inertes con `cursor:pointer`, encuadre 62 %→31 % y el `trim` por bytes eran defectos **de la
> estructura unificada** y hoy no aplican.
> ⚠️ **Un valor del primer cliente que NO vuelve**: `landing` arrancaba con `zone: 'jump'` (slug del
> primer cliente en el producto). Nace vacía y la primera zona la dice el DOM.
> ⚠️ **La portada pinta 4 tarjetas, no 2, y es DATO**: `cap` y `cap2` tienen `show_in_landing = 1`,
> 0 atracciones, el mismo nombre y el mismo acento que `kids`. Huelen a restos de prueba; se quitan
> **desde el panel**, no desde código.
>
> ▶ **Lo que la T3 hizo, como registro** (`#295`): un bloque por zona con su carrusel
> debajo, y **solo si tiene atracciones**. Se iban una cabecera, el salto, el CTA y **una de las dos
> barras de pestañas** de la portada.
> ❗❗ **Destapó un defecto que NO era de presentación**: la identidad de la zona era `accent`, que
> **agrupa y no identifica** — `cap` y `cap2` comparten el de `kids`—, así que **tres carruseles
> compartían `x-ref` y `data-zone`**: pulsar «Zona KIDS» abría tres a la vez. Ahora manda `slug`.
> ⚠️ **La misma raíz estaba arreglada A MEDIAS desde `#230`** (se corrigió el color, no la identidad):
> *cuando un campo demuestra que no identifica, hay que mirar todo lo que lo usa para identificar.*
> ⚠️⚠️ **REVISIÓN ADVERSARIAL de seis lentes: 40 hallazgos, 13 confirmados, 27 descartados** — y casi
> todo lo confirmado era MÍO y ninguna suite lo veía. Lo peor: **la guarda que re-apunté quedó VACÍA**
> (aseveraba una subcadena que emite también la sección de entradas; borrando la sección de zonas
> entera seguía verde). ▶ *Acota al elemento antes de creerte un test verde.*
> ⚠️ También: el carrusel enfocable **sin anillo de foco** (lo introduje con el `tabindex`), las
> tarjetas inertes fingiendo que se pulsan, la foto de **62 % a 31 %** de encuadre visible, la
> retirada de CSS **a medias** y `trim($s, ' ·')` **recortando BYTES**.
> ⚠️⚠️ **Y dos casos de mi guarda nueva nacieron pasando EN VACÍO**: la BD de test no tenía sujeto
> para ellos. *Un caso sin sujeto no vigila nada, y no se nota hasta que se muta.*
> ▶ **Lo DESCARTADO importa**: encabezados, nombres de flechas y el ancla se señalaron y **no
> sobrevivieron a la refutación** — sin ese paso habría «arreglado» tres cosas sanas.
>
> ❗❗❗ **POR DÓNDE SE RETOMA EL CARRIL DEL TEMA — LEE ESTO Y NADA MÁS** (`#297`, cierre del
> 2026-08-31 noche; ⚠️ **la base de partida la cambió `#300`, unos párrafos más abajo: la T1 está
> REVERTIDA y la portada arranca con normas y horarios las dos en su forma heredada**).
> **La sesión terminó SIN código nuevo: todo lo que se construyó se REVIRTIÓ**
> (`[owner]`: «déjalo como estaba, no quiero ninguna de esas opciones, en el siguiente chat
> iteraremos sobre cómo se hará»). El árbol está en el estado de `#295`.
>
> ▶ **Lo que queda vivo es el DIAGNÓSTICO, y es del owner**: *«la estructura se parece a la página
> del antiguo cliente: la manera en que se exponen los textos, cuándo va cada texto, cada dato»*.
> **Medido**: las SIETE secciones usan el mismo molde —`ETIQUETA` → titular partido en dos con coma
> → párrafo → contenido— y **el dato siempre llega el último**. El caso extremo es horarios y
> ubicación: **cinco encabezados para cuatro líneas de dato**, 803 px en móvil (280 son el marcador
> del mapa) y 31 palabras. A 390 px, `zones` ocupa **3.604** de los 14.831 de la portada.
> ▶ **`[DECIDIDO owner]` y SIGUE EN PIE**: se rompe el molde y **cada sección adopta la forma de lo
> que ES**. Lo rechazado son las tres formas concretas, **no el criterio**.
> ⛔ **NO vuelvas a proponer** las tres de `#297` (A · el estado manda · B · la respuesta primero ·
> C · el sitio manda): están medidas, montadas, vistas y descartadas. Su ficha, con los altos y el
> porqué, está en `specs/idioma-visual-heredado.md` §3.quinquies.
> ⚠️ **Y las tres fallaban en lo mismo**: A y B **crecían en escritorio** (+105 y +136 px) porque hoy
> el horario y el mapa van en dos columnas y ellas apilaban todo en una. *Quien retome esto tiene
> que resolver el escritorio, no solo el móvil.*
> ▶ **Lo que NO hay que volver a medir** (cuatro propuestas independientes coincidieron): se retiran
> la etiqueta, el titular partido, la rejilla de dos tarjetas, los dos `h3` internos, el `h4` de
> fechas especiales y la caja blanca sobre el mapa; y suben el estado en vivo, la fecha especial
> pegada a él, «Cómo llegar» por encima del mapa y el teléfono.
> ⚠️ **Dos hallazgos técnicos que sobreviven**: `HeroStatus` **calcula la ventana de hoy y no la
> devuelve** (y **no se deduce de `weeklyRows()`**: su `is_today` se apaga cuando manda una
> temporada o una fecha especial), y **«Parking gratis 2h» está en el código, no en el panel** —
> `[DECIDIDO owner]`: se retira cuando se rehaga la sección.
> ⚠️ **Presupuesto de dibujo de la portada**: tres colocaciones; **hoy gasta UNA** (las poses de
> zona) y **quedan DOS libres** — la mancha de normas se fue con la T1 en `#300`.
>
> ❗❗❗ **2026-09-01 · LOS DATOS REALES DE PLAY JUMP PARK, Y TRES CAMPOS QUE NO LEE NADIE**
> (`#304`). **Sesión de DATOS dictados por el owner**, no de código de producto — salvo un defecto
> del cajón que salió por el camino (abajo).
>
> ❗❗ **LOS DATOS VIVEN SOLO EN LA BD LOCAL Y NO VIAJAN EN EL COMMIT.** Este repo es el PRODUCTO sin
> marca de cliente (`DECISIONES #1`): meter el catálogo real de un parque en un seeder lo clava
> dentro de JumpWeb. ▶ **Si tu BD no los tiene, no está rota**: el volcado completo (17 productos con
> precios, ventajas, mínimos, señal y tramos de edad) quedó en el scratchpad de esa sesión,
> `pjp/catalogo.json`. La suite NO depende de esto —corre en SQLite en memoria, verificado—.
>
> ▶ **Qué hay configurado**: 5 entradas + 2 packs + 9 complementos · Kids 1h 8/10, 2h 12/15,
> Ilimitada 18/— · Jump 1h 12/14, 2h 18/22 · packs 14,95/16,95 y 15,95/19,95, 120 min, 8–20 niños,
> **señal 50 €** · zonas **Jump +8 años/+1,30 m** y **Kids 4–7 años/+1 m** · horario **L-V
> 16:30-21:30, Sáb y Dom 11:00-21:30** · antelación mínima 1 día (entradas) y 4 (packs) · cupo **20
> por franja** y **5 packs por franja**.
> ⚠️ **`[DECIDIDO owner]`: el VIERNES es precio ESPECIAL** («es víspera de sábado»): el tramo pasó de
> `[0,6]` a `[5,6,0]`. Verificada la rejilla de 7 días × 7 productos.
> ⚠️ **Las zonas `cap` y `cap2` se BORRARON** (`[owner]`: «no existen»). La portada vuelve a DOS
> pestañas.
>
> ❗❗❗ **LA LECCIÓN DE MÉTODO, Y ES LA QUE MÁS VALE: TRES CAMPOS PARECEN RELLENABLES Y NO LOS LEE
> NADIE.** (1) **`description` de un COMPLEMENTO** —ni API, ni cajón, ni web: el contenido de los
> menús se escribió ahí y no se veía; va en `features`—; (2) **`conditions`**, cero consumidores;
> (3) **`badge` de un COMPLEMENTO**, que `AddonResolver` **DERIVA** (`included`/`free`) y el cajón
> traduce como CLAVE, así que un texto libre saldría crudo. ⚠️ **En un producto el `badge` SÍ es
> texto libre y sí se pinta**: la asimetría es el detalle que hay que saber.
> ▶ *Antes de escribir en un campo, mide quién lo lee.*
>
> ⚠️⚠️ **Y UN PRECIO SE ESCRIBIÓ EN FILAS NUEVAS SIN QUE NADA FALLARA**: `prices.priceable_type` usa
> el **alias de morph** (`ticket_type`), no la clase. Escribir `get_class()` creó 4 filas duplicadas
> y dejó las viejas mandando — **la web habría seguido con el precio anterior y el cambio parecería
> aplicado**. Se cazó comprobando el resultado **por el MODELO**, no por la consulta recién escrita.
>
> ⚠️⚠️ **«QUITARLO TODO» TUVO TRES EXCEPCIONES QUE LA INSTRUCCIÓN NO PODÍA CONOCER**: los dos
> **portadores de dinero de fiestas mixtas** (`mixed_party.*_product_id`) no son catálogo y no se
> tocan; `order_items.ticket_type_id` es **`ON DELETE CASCADE`**, así que retirar un producto se
> lleva EN SILENCIO sus líneas y deja pedidos pagados sin contenido (por eso se limpiaron antes los
> 26 pedidos de prueba, preguntando); y el catálogo se recreó **desde cero**. Cero huérfanos después.
>
> ❗ **TRES COSAS QUE EL OWNER PIDIÓ Y EL SISTEMA NO SABE HACER** (fichas en `DEUDA.md`):
> **«mostrar sin vender» no existe para un complemento** (`addons()` filtra `is_sellable` **y**
> `is_active` → apagarlo lo hace INVISIBLE; para una ATRACCIÓN sí existe) · el **máximo de
> antelación es UNO GLOBAL**, no por producto (`[DECIDIDO owner]`: se queda en 6 meses) · el **tope
> de packs es por FRANJA, no por DÍA** (`[DECIDIDO owner]`: 5 por franja).
> ▶ **Y LA HORA EXTRA NO SE CONSTRUYE TODAVÍA** (`[owner]`: «hay que iterar cómo lo haremos para que
> sea profesional»): **alarga la reserva a 180 min**, así que toca AFORO —hoy ningún complemento
> mueve la duración— y son **CUATRO productos distintos** (4 € y 5 € en cumples, 5 € y 8 € en
> entradas), uno por producto padre. Su ficha lista las cuatro preguntas a resolver antes de tocar
> código.
>
> ⚠️ **Un defecto REAL del cajón, encontrado por el OWNER usándolo**: el botón «Más info» de cada
> complemento **no tenía `@click`** y la lista de ventajas se pintaba **sin condición de estado** —
> salía siempre abierta y el botón era decoración—. Arreglado y con guarda
> (`DrawerDisclosureIsWiredTest`, 3 mutaciones). ⚠️ **Ninguna guarda podía verlo**: la de clases
> pregunta si hay REGLA de CSS, y el contrato de árbol compara ESTRUCTURA — *nadie preguntaba si el
> control hace lo que su rótulo promete*. ⚠️ **Y el comentario de `addon-chip.blade.php` AFIRMABA que
> funcionaba** («el mismo patrón que el sidebar… + toggle Alpine»): la landing sí, el cajón nunca.
> ⚠️⚠️ **La guarda nueva se cazó A SÍ MISMA** al citar el marcado roto en su propio comentario: caso
> rojo con el producto sano. Ahora quita los comentarios antes de escanear. *Todo escáner de marcado
> tiene que decidir a propósito qué hace con la prosa* — y le pasó lo mismo a `guard-bash.sh`, que
> **bloqueó el `cat` de esta misma entrada** porque el texto nombraba una orden destructiva.
>
> ⚠️ **Sigue abierto y es del owner**: si los **combos son excluyentes entre sí** (hoy no lo son:
> se pueden sumar los tres) y si los **calcetines** van solo en cumpleaños o en todo (hoy en las 5
> entradas y los 2 packs, porque la web dice que son obligatorios para saltar).
>
> ❗❗❗ **2026-08-31 (noche) · EL TITULAR A UNA LÍNEA Y LA TARJETA COMO PEGATINA** (`#303`,
> `[DECIDIDO owner]`, `specs/idioma-visual-heredado.md` §3.septies).
> ▶ **PRIMERA PIEZA DEL MOLDE DE `#297` EJECUTADA**: fuera la etiqueta de toda vista pública y
> titulares de UNA palabra **sin punto y en tinta** (**Dos zonas · Tarifas · Cumpleaños ·
> Visítanos · Normas · En directo · Dudas**, ×3 idiomas).
> ⚠️⚠️ **Elegido sobre DOS caminos MEDIDOS**: con el suelo del `clamp` en 48 px, a 390 px solo caben
> ~10 caracteres («Tarifas claras.» pedía 40, «Un parque, dos zonas.» 29). O una palabra, o **bajar
> el suelo** (el arreglo exacto de `#220`). Eligió una palabra **con la consecuencia delante**: son
> casi los eyebrows que se retiran, lo cual resuelve del todo la redundancia medida (4 de 7).
> ⚠️⚠️ **PUSE UN PUNTO EN COLOR —en los titulares y en las tarjetas— Y EL OWNER LO RETIRÓ.** Al
> preguntarme por qué estaba salió lo que había que decir: **4 de los 7 titulares ya acababan en
> punto y yo se lo añadí a los otros 3**, y el color fue invención mía para un efecto colateral de
> mi propio cambio. ⚠️ Y la pegatina de la tarjeta cita `E1`, **que se titula «Botones»**: la tarjeta
> es `E3` y pide otra cosa. *Extrapolación presentada como cita.* Sobreviven sombra y borde.
> ⚠️⚠️ **Y la mancha se había CAÍDO sobre el párrafo** (11.016 px²): su `top` era un porcentaje de la
> CABECERA, que encogió de 264 a 153 px al acortar los titulares. Anclada al bloque del TITULAR y
> re-dimensionada (`-84%` · `min(22%,165px)`) → **0 px² sobre el párrafo**, 9.381 sobre el titular.
> ⚠️⚠️ **El aire hero→sección: empezó como COMPENSACIÓN y acabó siendo DECISIÓN.** Nació en 32 px
> medidos (lo que ocupaba la etiqueta) y **seguía viéndose corto con razón**: antes lo primero bajo
> el hero era una etiqueta de 16 px y ahora es el titular, **71 px de tinta maciza** —mismo hueco,
> mucha más presión visual—. `[DECIDIDO owner]` sobre tres opciones renderizadas: **`--hero-air:
> 64px`** (160 escritorio · 128 móvil), solo en `.hero + .section`. **Ya no es «lo de la etiqueta»**.
> ▶ **CTA en TODAS las tarjetas**: ⚠️ **no existe página de detalle de atracción** (verificado), así
> que `[DECIDIDO owner]` lleva a **reservar la ZONA** —lo que ya hacía la comprable, y el parque
> vende por zona—. La tarjeta pasa a **PEGATINA**: borde de tinta, sombra dura y punto de color, que
> sale de su `E1`. ⚠️ **La sombra entra como ROL (`--shadow-float`), no como valor**.
> ❗ **DOS titulares NO se tocan, y se dice en vez de «arreglarlos»**: el del cierre («VAMOS A /
> SALTAR», coreografía medida de `#252` contra el alto de ventana → **excepción DECLARADA** en la
> guarda, pendiente del owner) y **los nombres de servicio de `/servicios`, que los escribe el
> panel** — *un titular data-driven no puede tener regla de longitud*.
> ⚠️ **`.blink` de `/servicios` se apaga** (una palabra no tiene nada que destacar), pero **el
> mecanismo NO es código muerto**: se le añade un caso que lo ejercita — antes solo estaba cubierta
> la rama con acento.
> ⚠️⚠️ **DOS TRAMPAS DE INSTRUMENTO, las dos con número creíble**: (1) **una captura de ELEMENTO más
> alto que la ventana COSE los `fixed`** —enseñaba las flechas del carrusel, que miden **0×0 con
> `display:none`** en ese mismo contexto—, se rehízo con captura de VENTANA; (2) un contador **por
> subcadena** dio **115 tarjetas donde hay 23** (`class="ride-card` casa con `ride-card__viz`…).
> ▶ Guarda nueva **`SectionHeadlineTest`** (sin etiqueta · sin `<br />`, con la excepción y su
> comprobación de que sigue teniendo sujeto) y dos guardas re-apuntadas sin quedar más débiles.
> **Verificación**: suite **3680 verde** · Pint ✓ (1051) · docs-check ✓ · build ✓ · **4/4 mutaciones
> muerden** · Chrome real 1280 y 390 con puntero grueso, seis rutas: **cero titulares de sección en
> dos líneas** salvo los dos declarados. **Queda el OJO del owner.**
>
> ❗❗❗ **2026-08-31 (noche) · ZONAS Y JUEGOS: FUERA EL SELECTOR DUPLICADO** (`#302`,
> `[DECIDIDO owner]`, `specs/idioma-visual-heredado.md` §3.sexies).
> ▶ **El diagnóstico es suyo y se pudo medir: había DOS selectores de zona en la misma página.** Las
> tarjetas tenían un CTA que SALTABA a la sección de atracciones, donde una barra de pestañas hacía
> la misma elección. El de arriba costaba **1.011 px en escritorio y 1.831 en móvil**.
> ▶ **Queda UNA sección y UN selector**, y el toggle dice ahora quién es cada zona: **dibujo del kit
> + nombre + EDAD**. Fuera las tarjetas y la tira de cifras; la tarjeta de atracción se queda en
> **foto + título + tag**.
> ⚠️⚠️ **Y queda UNA cabecera, que no se pidió pero lo exige lo que sí**: sobrevive la de ZONAS
> porque su párrafo acaba en «Elige el tuyo», que es lo que hace el toggle de debajo.
> ⚠️ **Las dos anclas sobreviven** (6 enlaces dependen), y `#rides` **envuelve selector + carriles**:
> `applyZoneAccent()` tiñe ese contenedor, así que si solo envolviera el carrusel las pestañas
> perderían el color de su zona.
> ▶ **Fachada**: iconos de zona (**ya instalados**, y son IDENTIDAD, no decoración: por eso no chocan
> con la regla de `#286`) + **UNA mancha** `B1·02` en ranura nueva `slot-zonas`, elegida **midiendo
> las seis** (la más ancha de las libres, 1,19) y colocada **midiendo** (`top: -20%` → **0 px² sobre
> el párrafo**, 32.162 detrás del titular). **Nada por tarjeta**: el CSS ya lleva la lápida del
> intento rechazado por ruido.
> ▶ **Carrusel a nuestro estilo**: la siguiente tarjeta **cortada por el borde** (la afordancia
> medida en `cajon-en-movil` §5.2 — antes cabían 3 EXACTAS y parecía una rejilla), flechas al pie con
> `--shadow-nav-*` y ocultas con puntero grueso.
> ⚠️ **Dos defectos PREEXISTENTES arreglados**: `.ride-card` tenía `cursor:pointer` **siendo un
> `<article>` sin enlace**, y `landing` arrancaba con `zone: 'jump'` —el slug del primer cliente
> escrito en el producto—.
> ⚠️⚠️ **Se fue MUCHO con su sujeto**: 55 reglas y 288 líneas de CSS **con sus `@media`** (la trampa
> de `#295`), `<x-site.zone-metrics>`, `goToRides()`, **7 claves × 3 idiomas** y 4 variables de vista.
> ⚠️⚠️ **Y SIETE guardas se quedaron sin sujeto**: dos se re-apuntan (**más fuertes**), dos listas
> encogen y tres se retiran con lápida. ❗ **Guarda NUEVA que faltaba desde `#257`**:
> `test_every_declared_slot_is_painted_by_a_screen` — «una ranura vive lo que vive su consumidor»
> estaba escrito en tres sitios y **no lo imponía nadie**.
> ⚠️⚠️ **Una mutación NO mordió por el fallo de siempre: el caso nació SIN SUJETO** (en la BD de test
> `accent == slug` para jump y kids). Rehecho con zona gemela; y destapó que **dependía del kit REAL,
> que está gitignorado** — habría pasado aquí y fallado en un clon limpio.
> ❗ **LO QUE CUESTA**: se pierden las 2 fotos de zona, los 2 subtítulos y las métricas por zona, y
> **`zones.image` se queda sin consumidor** (ficha en `DEUDA.md`, con tres salidas y es del owner).
> ⚠️ La portada pinta **4** pestañas y no 2 porque `cap`/`cap2` están marcadas para la landing: es
> DATO, se quita desde el panel.
> **Verificación**: suite **3675 verde** · Pint ✓ (1050) · docs-check ✓ · build ✓ · `kit:build`
> servible con **4 símbolos** · **6/6 mutaciones muerden** · Chrome real 1280 y 390 **con puntero
> grueso emulado**: sección **2.779 → 1.452** (escritorio) y **3.531 → 1.130** (móvil), tarjeta
> **380×497 → 475×713**, mancha 0 px² sobre párrafo, 0 errores de consola, 0 desborde, ningún control
> < 44 px. **Capturas en `/root/e2e/zonas-capturas/`. Queda el OJO del owner.**
> ⚠️ **Dos lecturas de la sonda fueron ARTEFACTOS**: flechas «visibles» en móvil (headless reporta
> `pointer: fine` sin `hasTouch`/`isMobile`) y un «lado menor 0 px» que era medir flechas OCULTAS.
> ⚠️⚠️ **TERCERA colisión de numeración de la jornada**: el otro agente publicó `#298` mientras se
> trabajaba, y las tres entradas de este carril se renumeraron a `#300`/`#301`/`#302` (41 referencias
> en 17 ficheros). **Mirar el remoto otra vez antes de empujar.**
>
> ❗❗❗ **2026-08-31 (noche) · LA T1 SE REVIERTE ENTERA — LA PORTADA VUELVE A SU BASE COMÚN**
> (`#300`, `[DECIDIDO owner]`, elegido **con la consecuencia delante**: se le enseñaron las tres
> opciones renderizadas y se le dijo que revertir devuelve arte de otro parque).
> ▶ La sección de normas vuelve a `.rules-layout` + `.rules-vslider`: el pliego de doce pictogramas
> del cliente ANTIGUO y **todas** las normas. Se van las tres en texto, el CTA y la mancha;
> `IllustrationKit::SLOTS` queda **vacía** (3.ª vez) y el kit baja de 4 a **3 símbolos**.
> ▶ **Y horarios/ubicación NO se tocó porque ya estaba como estaba, y se MIDIÓ antes de creerlo**:
> byte a byte idéntica a antes del carril, sin restos del `?forma=a|b|c` de `#297`. *Cuando alguien
> pide deshacer algo, lo primero es comprobar si ya está deshecho.*
> ⚠️⚠️ **Esto NO retira el diagnóstico ni su `[DECIDIDO owner]`**: el molde se sigue rompiendo. Lo
> que hizo el owner fue poner las dos secciones tocadas en el MISMO punto de partida para rediseñar
> desde ahí, en vez de encima de una forma a medias que no había aprobado. **La sección de normas de
> hoy no es una forma aprobada** — es material heredado, y así está anotado en la vista y en las dos
> hojas de estilo para que el siguiente agente no lea el commit al revés.
> ⚠️ **La otra mitad de `#292` NO se revirtió**: la retirada del registro fino de badges
> (`.tag--dato`) es decisión independiente y sigue en pie —venía de `Landing PJP Modos`, que el
> owner desautorizó como fuente salvo para las reseñas—. `#292` mezclaba tres cosas en un commit.
> ⚠️ **`client-kit.svg` está GITIGNORADO**: la reconstrucción sin `slot-normas` **no viaja en el
> commit**, así que quien despliegue o clone tiene que rehacer el kit.
> ⚠️ **Se retira el caso que fijaba el tope de tres** y el otro vuelve a su contrato original: la
> suite baja de 3679 a **3678** y es correcto. *Una guarda de un contrato retirado no protege nada.*
> **Verificación**: suite **3678 verde** (23.999 aserciones, 1 skipped) · Pint ✓ (1049) · docs-check
> ✓ · `kit:build --check` servible con 3 símbolos · y comprobado **en la página servida**, no solo en
> verde: `rules-layout` + `historia-seguridad.png` presentes, **5 normas**, `rules-lite`/`slot-normas`
> /el CTA ausentes.
>
> ❗❗❗ **`Landing PJP Modos` YA NO GUÍA LA ESTRUCTURA DE LA LANDING** (`#292`, `[owner]`: «de esa
> maqueta solo sacaremos la sección de reseñas»). ▶ **Corrige a `tema-por-instalacion.md` §1**: aquel
> artboard sigue valiendo para color y sistema visual, **no para decidir qué secciones hay**. La
> reestructuración la diseñamos nosotros con el branding y `Elementos Fachada`.
> ▶ **Y eso tumbó una decisión de la misma jornada**: el registro fino de badges venía de esa maqueta
> y se retiró — `[DECIDIDO owner]` «todo al registro del mural». Una sola voz.
> ▶ ⛔ **El BAR / zona de Ocio NO entra** (`[DECIDIDO owner]`): era lo único que tocaba el modelo de
> datos. **Zonas y atracciones sí se unifican**, que es presentación.
> ▶ ⛔ **T1 — REVERTIDA ENTERA por `#300`** (`[DECIDIDO owner]`). Lo que hizo y ya NO está: fuera el
> pliego de pictogramas del cliente antiguo y el carrusel con todas; quedaban **tres** normas en
> texto y el CTA a `/normas`, con el tope declarado en la VISTA y guarda con mutación. **Hoy la
> sección está otra vez como el cliente antiguo la dejó**, a la espera del rediseño del molde.
> ▶ ⛔ **Y con ella se fue la PRIMERA ranura decorativa** (`slot-normas`, mancha `B1·03`).
> **Presupuesto: una pieza por sección y la portada no pasa de TRES** — hoy gasta **UNA**.
> ⚠️⚠️ **Dónde va la mancha lo decidió MEDIR**: el primer sitio caía sobre un párrafo (13.755 px²) y
> bajarla no servía porque *ahí no había hueco* — la columna estaba llena. *Cuando mover una pieza no
> cambia el número, el problema no es la posición.*
> ▶ **Lo siguiente**: la marquesina de `/servicios` → cinta `C3`; luego zonas+atracciones, cumpleaños
> y «cómo se reserva», horarios, FAQ, la galería (que sale y deja sitio a las reseñas) y las páginas
> individuales. ⛔ **Las reseñas siguen bloqueadas**: `google-reviews.md` espera tres datos del owner.
>
> ❗❗❗ **EL CARRIL DEL TEMA CAMBIA DE ENCARGO** (`#290`, y es lo primero que hay que saber): el
> owner no quiere que se añada decoración, quiere que se **CAMBIE lo heredado** — *«las secciones y
> los elementos de diseño que tienen son del cliente antiguo… primero hay que cambiar lo que
> tenemos»*. Spec propia con el inventario y las tandas: **`specs/idioma-visual-heredado.md`**.
> ▶ Eso **ACOTA la regla de `#286` sin anularla**: aquélla es para DECORACIÓN AÑADIDA. Un badge, un
> separador o una cinta son **componentes funcionales** y se repiten porque los datos se repiten;
> confundir las dos cosas paraliza el carril.
> ▶ **Tanda A hecha**: el **sistema de etiquetas** (no había ninguno — cinco formas, cinco paddings,
> cinco tallas y **cuatro rotaciones** para la misma función) y **`.jj-block` fuera**, el «foam» del
> cliente antiguo, con sus iniciales en el nombre de la clase.
> ⚠️⚠️ **Sus DOS artboards visten los badges distinto y entran los DOS** (`[DECIDIDO owner]`, elegido
> sobre las tres renderizadas): **SEÑAL** → `E2` del mural · **DATO** → el mono fino de su landing.
> ⚠️ **Quedan**: la marquesina de `/servicios` → cinta `C3` · los cubos 1-2-3 del cumple · la nota de
> calcetines · la galería de polaroids · y **el SPA**, que el owner quiere iterar con **cambio de
> presentación del catálogo**, no solo de traje.
> ⚠️⚠️ **Lección de método de la jornada**: un script de edición murió en su primera aserción y las
> dos ediciones de CSS siguientes **no se aplicaron**. La suite salió VERDE y la pieza no estaba en la
> página. *Verde no es «el cambio está puesto»*; lo destapó la captura.
>
> ❗❗❗ **POR DÓNDE SE RETOMA EL TEMA, HOY** (esto sustituye al punto 1-5 de abajo en lo que cambia):
> **1 · `[DECIDIDO owner, 2026-08-31]`: NO se coloca material nuevo** hasta que él vuelva a mirar el
> canvas. La sesión de `#287` fue de SANEO, y lo que hay puesto está medido y con trinquete.
> **2 · Qué se ve hoy**: trama en el menú, en la tarjeta del cierre y —**una sola**— en la cabecera de
> `/normas`; niebla en `/contacto`; rayos quietos en `/precios`; las dos tiras en cuña; y las poses de
> zona en las tarjetas de la portada. **`/servicios` sigue sin material propio** (medido: su única
> trama es la del menú y su única tira, la del pie) — es el candidato natural cuando se retome.
> **3 · Lo retirado y por qué**: `.brand-dots` (C2 punteada) y `.grain--zona` **nacieron sin
> consumidor** y se van; sus números quedan en el comentario de `site.css` y vuelven con su pantalla.
> **4 · Las 9 poses y las 6 manchas siguen fuera**, y `IllustrationKit::SLOTS` sigue **VACÍA**: una
> ranura nace con su consumidor en el mismo cambio. Las 12 poses extraídas y saneadas viven en el
> scratchpad de `#286` (`poses.json`), no en el repo.
> **5 · Ahora hay DOS trinquetes** y conviene saberlo antes de escribir CSS de fachada:
> `FacadeCssHasNoOrphansTest` (ninguna regla sin pantalla que la pinte) y
> `FacadeDecorationIsPerScreenTest` (**ninguna textura dentro de un `@foreach`**, ningún dibujo del
> kit con clave literal dentro de un bucle). Si añades material, entra con su consumidor o la suite
> se pone roja — que es exactamente lo que se busca.
> **6 · Falta el OJO del owner** en: la cabecera de `/normas` con una sola trama, la tira de cabecera
> que aparece al encoger el hero, y las poses de zona en móvil. Y **subir a staging** todo lo del tema.
>
> ⚠️⚠️ **DOS TRAMPAS DE INSTRUMENTO DE `#287`, y las dos dan un «no muerde» falso:**
> · **`python3 -c "…"` entre comillas dobles**: bash expande `$file` dentro del patrón de búsqueda y
>   **la mutación no llega a aplicarse**. Se comprueba que el ancla existe antes de concluir nada.
> · **Mutar UNA mitad de una defensa de DOS no muerde**: el corpus se protege dos veces (solo recorre
>   `public/build` *y* además excluye `public/css`), y solo las dos mutaciones a la vez ponen la
>   guarda roja. *Una mutación que no muerde puede ser una mutación DÉBIL.*
>
> ⚠️ **Para medir el sitio en navegador hace falta un puente**: `asset()` emite URL ABSOLUTA con el
> puerto del host, así que desde el contenedor `localhost:8081` no resuelve y el `<use>` externo del
> kit sale cross-origin **con el producto sano**. Guion: `/root/e2e/proxy8081.mjs` (8081 → 80) y el
> utillaje común en `/root/e2e/fach.mjs`, que además inyecta la cookie de consentimiento para que el
> banner no tape media pantalla.
>
> ⚠️⚠️ **DOS AGENTES SOBRE `main` EL MISMO DÍA.** `#282`–`#285` son de reservas mixtas y `#286` del
> tema; no se solapan en código, pero el número de decisión **se elige mirando el REMOTO**: en esta
> jornada el local iba por 281 y el remoto por 285.
>
> ❗❗❗ **POR DÓNDE SE RETOMA EL TEMA** (lo único que hay que leer para seguir con esto):
> **1 · EL MECANISMO ESTÁ COMPLETO Y PROBADO** — `specs/hueco-ilustracion.md`. La instalación entrega
> **un** sprite (`client-kit.svg`) y el producto lo pinta con `<use>`, poniendo él color, tamaño y
> tratamiento. `php artisan kit:build` valida, `GUARDA 7` de `deploy.sh` mira el fichero REAL, y hay
> **46 casos con 24 mutaciones que muerden**.
> **2 · ❗ LA REGLA QUE MANDA A PARTIR DE AHORA, y sale de TRES rechazos del owner en una tarde**:
> *la decoración va en la **PANTALLA**, no en el componente que se repite*. Se retiraron cuatro tiras
> de marca (su landing usa **dos**), una mancha por tarjeta de precio y un friso de cinco figuras.
> Lo que SÍ sobrevivió comparte tres cosas: **una por pantalla · integrada en su superficie · a baja
> opacidad**. Su propio artboard lo dice dos veces: rayos «uno por página», mancha del precio «máximo
> una por pantalla». **No propongas material repetido por componente.**
> **3 · Qué se ve hoy en la web**: trama de puntos generalizada (menú, cierre y `/normas`), niebla en
> `/contacto`, rayos quietos en `/precios`, las dos tiras de borde **en cuña**, y **las poses de zona**
> en las tarjetas de la portada (`zone-jump` → P1 · `zone-kids` → K1 · `zone-cumpleanos` → P5, el
> reparto que el propio artboard escribe en `F10`).
> **4 · Lo que NO está colocado y por qué**: las **9 poses y las 6 manchas restantes** están extraídas
> y saneadas, pero **la lista de ranuras (`IllustrationKit::SLOTS`) está VACÍA a propósito** — una
> ranura nace **con su consumidor en el mismo cambio**, o vuelve a pasar lo de `#257`. Para colocar
> algo: se declara la ranura, se añade el dibujo al kit y se pinta, todo junto.
> **5 · Falta el OJO del owner** en: la tira de cabecera que aparece al encoger el hero, y las poses
> de zona en móvil. Y **subir a staging** todo lo del tema.
>
> ⚠️⚠️ **TRES TRAMPAS DE INSTRUMENTO DE ESTA JORNADA, y la primera cuesta una medición entera:**
> · **Comparar capturas de la PORTADA no puede demostrar nada**: su suelo de ruido es del **64,45 %**
>   (vídeo, imágenes perezosas, animaciones). Dije «90 % distintos → el dibujo pinta» y era basura.
>   El instrumento bueno **extrae del servidor el marcado real** y lo pinta en una página quieta:
>   suelo **0,00 %**. Está en el scratchpad como `ilu/tarjeta.py` y `ilu/foto.py`.
> · **`asset()` emite URL ABSOLUTA**, así que una sonda en otro puerto hace el `<use>` **cross-origin**
>   y no resuelve — con el producto sano.
> · **Una sonda que no reproduce la condición del defecto no lo ve**: la del troquel dio «bueno»
>   porque `color` valía tinta oscura, que como máscara se lee casi igual que el negro.
>
> ⚠️ **Y `elementos-fachada.md` lleva CINCO correcciones propias en su cabecera** (§12 de la spec
> nueva): el hueco YA existía dos veces, dos manchas YA viajaban instaladas (byte a byte), el «18,6×»
> es cifra cruda (**1,4× comprimido**), los estados vacíos SÍ existen, y el canvas ya no está caducado.
> ❗❗❗ **2026-08-31 · LA VISIÓN DE RESERVAS MIXTAS, CERRADA CON EL OWNER** (`#284`). **Si vas a
> construir algo de mixtos, lee `specs/cumple-mixto.md` §18 y nada más**: es la foto completa —nueve
> decisiones, siete huecos medidos y el plan por tandas—. Sesión SIN código.
> ▶ **La regla que lo ordena todo, en sus palabras**: «el cliente compra con unas condiciones y las
> mantenemos; ya las siguientes reservas empiezan con las nuevas». Solo cambia de condiciones **lo
> que cambia de producto**.
> ❗❗ **EL SELLO VIVE EN LA RESERVA, NO EN EL PRODUCTO** (D1): cada reserva guarda al nacer una copia
> de los tramos y precios de su familia, así que **el precio viejo solo existe dentro de las reservas
> que lo llevan** y **no hace falta histórico de precios**. La corrección es del owner: yo estaba a
> punto de plantear un histórico.
> ❗❗ **Y destapó un hueco MAYOR que el caso espejo**: el **PRIMER** cargo se calcula con el catálogo
> del día en que el cliente rellena el formulario, no con el del día en que compró. Medido: se
> reserva con 5,00 € de diferencia por cabeza y se le cobran **10,00 €**. No hace falta tocar
> tramos, basta subir un precio — y el formulario **siempre** se rellena más tarde.
> ⚠️⚠️ **`PAY-18` NO era un choque con la visión: es la misma regla** («la fecha es un producto»), y
> lo aclaró él. Queda intacta.
> ⚠️ **Una edad sin producto es un estado CONOCIDO, no una incógnita** (D6), y eso **corrige a
> `#268`**: hoy congela el dinero —medido, un bebé de 0 años impide que el cargo baje— y solo una
> edad que FALTA debería hacerlo.
> ⚠️ **`0 LIVE · 0 PRODUCCIÓN`**: no hay nada que rellenar al desplegar el sello. Retiré una pregunta
> mía que el propio `ENTORNOS.md` ya contestaba.
> ▶ **El −X € SE HACE** (revierte `#246`/`#248`) pero **se diseña con Fable antes de tocar nada**:
> informe autocontenido en **§19**, con la aritmética verificada de los tres casos y la trampa medida
> que hunde el más común (un pack sin señal perdía el descuento en silencio).
> ▶ **Plan en §18.5, seis tandas.** Empieza por el **ojo del owner en navegador** (T0) y sigue por el
> **SELLO** (T1), que desbloquea la mitad de lo demás. Las que tocan dinero de verdad son T3 y T4.
> ❗❗ **2026-08-31 (tarde) · EL −X € DISEÑADO Y CERRADO CON FABLE** (`#285`, sin código). **El
> diseño vigente es `specs/cumple-mixto.md` §20 y SUSTITUYE a §16**, que pasa a ser la pieza de la
> fase 3 con su corrección delante.
> ▶ **El espejo acotado a puerta**: línea hija con subtotal negativo + `extra_due` negativo del
> mismo importe — trazado sobre el código real que `collected = max(0, −8−(−8)) = 0`, así que **el
> clamp nunca muerde** y los DOS elementos de riesgo ALTO de §16 (relajar el clamp, el marcador)
> no hacen falta: existían solo para el caso «pagado 100 % online», que con el tope no se escribe.
> ▶ **La hoja de ruta de cobro del owner, en TRES fases** (§20.2): hoy señal+parque → el descuento
> cabe siempre; pack online+gestiones en parque → el exceso es «a tu favor, se te devuelve en el
> parque» (circuito VERIFICADO: `MODE_MANUAL` + motivo obligatorio + canal `compensado`, identidades
> cerradas); todo online → feature propia de cobro/reembolso online post-reserva, **donde despierta
> §16** (ojo: hoy el +X tampoco puede cobrarse online — esa mitad no existe para nadie).
> ❗❗ **La regla que simplifica todo es del OWNER**: el dinero —las dos direcciones— solo se mueve al
> guardar el formulario **COMPLETO**; un guardado incompleto congela y lo dice. ⚠️ **Cambia `#268`
> para los cargos** (hoy crecen con parciales): el cambio de disparador va en la **T2**.
> ▶ **COMPLETO no se bloquea** (alergias · «editable hasta el día del evento» ya prometido · la
> ventana la cierra el fin de la fiesta). Máquina de estados en §20.7.
> ▶ Los números: el descuento automático SÍ mueve totales (en la puerta pedirán 82, no 90); el
> exceso NO los mueve hasta liquidarse. T5 gana una nota: el email de reducción promete «procesaremos
> la devolución», que con liquidación en parque promete de más.
> ▶ **2026-08-31 (tarde) · T0 EJECUTADO EN HEADLESS — 14 capturas, y el mecanismo que faltaba,
> verificado** (sin tocar código de producto). Carpeta: **`storage/app/t0-capturas/`** (gitignorada);
> guion `t0.js` + pedidos sonda `T0-PRB01`/`T0-PRB02` y admin `e2e-panel-admin@jumpweb.test`
> documentados en la fila T0 de `specs/cumple-mixto.md` §18.5.
> ❗ **Lo que el guion PROBÓ funcionalmente**: el aviso del catálogo (`#283`) en el ciclo REAL de
> Livewire — primera vez que alguien lo ve: avisa con los números (captura 02), re-guardar lo aplica
> (03), números distintos re-avisan (04), y el catálogo quedó restaurado al dígito. Su guarda de
> suite conducía el método por reflexión, no el formulario: esta era la verificación que faltaba.
> ▶ Verificadas mirando la imagen (no por el nombre del fichero): la frase del cliente compuesta
> desde lo ESCRITO («4,00 € más por invitado», 05) · el congelado con una edad borrada (09, 8,00 €
> intactos con 7/8) · la línea de DESFASE del panel («escrito 8,00 € y hoy correspondería 18,00 €»,
> 10) · el HUÉRFANO con su frase y el dinero vivo (12) · «Complementos actuales» VACÍO en Gestionar
> con la línea del suplemento existiendo (11b) · y el correo «pasa de 12,00 € a 8,00 €» (08).
> ⚠️⚠️ **Lección de instrumento, pagada**: las sondas de los días 29–31 filtraron CORREOS reales a
> Mailpit — `QUEUE_CONNECTION=sync` envía en el acto y el mail no es transaccional, así que el
> rollback de la BD no lo recoge. El owner recibió dos correos con importes de una sonda (30,00 €)
> que la base nunca tuvo. *Toda sonda que pueda notificar lleva `Notification::fake()` o asume que
> ensucia Mailpit.*
> ⚠️ Dos confirmaciones VISUALES de huecos ya documentados (no defectos nuevos): el congelado es hoy
> SILENCIOSO (el mensaje «no se actualizará hasta completar» llega con la T2/§20.6) y el huérfano
> pierde la pastilla MIXTA mientras el cargo vive (lo cierra la T1, §18.4).
> ▶ **Queda SOLO el ojo del owner sobre las 14 imágenes**: textos y claridad, lo que una sonda no
> juzga.
> # ❗ SI ENTRAS NUEVO (2026-08-31, cierre): RESERVAS MIXTAS — LA VISIÓN CERRADA, EL PLAN EN MARCHA
>
> **`git fetch` antes de nada.** La sesión del 31 cerró TODO el diseño de reservas mixtas con el
> owner y ejecutó el T0. **Tu punto de entrada es UNO: `specs/cumple-mixto.md` §18** (la visión, las
> nueve decisiones D1–D9, los huecos medidos y el plan §18.5). El −X € está diseñado en **§20**
> (`#285`, sustituye a §16). No queda NINGUNA decisión de producto abierta en mixtos.
> ▶ ~~**LO SIGUIENTE ES LA T1 — el SELLO**~~ ✅ **HECHA el 2026-08-31 por la tarde (`#288`)**: ver el
> bloque de arriba y `specs/cumple-mixto.md` §21.13.
> ▶ ~~**LO SIGUIENTE ES LA T2**~~ ✅ **HECHA el 2026-08-31 (tarde-noche, `#289`)**: §22 y §22.9.
> ▶ **LO SIGUIENTE ES LA T3 — el parque decide** (§18.5, E·F·D7). ❗ **SU DISEÑO FINO YA ESTÁ
> ESCRITO en `specs/cumple-mixto.md` §23 y el owner PARÓ antes del código** («todavía no»: quiere
> revisarlo). Q1 (§23.8) ya decidida: los dos permisos nuevos (`orders.edit_guest_data` ·
> `orders.edit_item_below_minimum`) entran en `staff`. **Nada de la T3 está en el árbol.** Cuando dé
> el «adelante»: E (lo escrito en hoja de sala y puerta, coste cero en consultas) · F (pestaña
> «Invitados» por la MISMA puerta que el cliente, `via = panel`, actor operador, writer como control
> negativo del gate) · D7 (interruptor con permiso y rastro en `OrderItemEditor`, 🔒 `CRITICAL_RE`).
> Orden en §23.6, guardas en §23.7.
> ⚠️ Orden del resto: T4 (el −X €, diseño cerrado en §20; el crédito sale de los precios SELLADOS y
> el disparador ya es «solo completo») · T5 (las palabras: D9, D8, el email de la devolución — el
> «hoy:» se disolvió con la T1) · T6 (solapes fuera del form; con el sello ya no mueve dinero).
> ▶ **T0**: 14 capturas verificadas en `storage/app/t0-capturas/` (gitignoradas); el owner ya cazó
> el primer hallazgo. Pedidos sonda `T0-PRB01`/`T0-PRB02` y `e2e-panel-admin@jumpweb.test` viven en
> la BD local para futuras sondas; guion `t0.js` en `/root/e2e` del contenedor.
> ⚠️⚠️ **Si sondeas con notificaciones: `Notification::fake()` SIEMPRE** — `QUEUE_CONNECTION=sync`
> envía en el acto y el rollback de la BD no recoge un correo ya enviado (el owner recibió dos con
> importes que la base nunca tuvo).
>
>
>
>
>
> ❗❗❗ **POR DÓNDE SE RETOMA** (lo único que hay que leer para seguir):
> **1 · La poda de bucles está MEDIDA y NO hecha** (`[DECIDIDO owner]`: «nada todavía, solo el
> informe»). El mapa, en `#279` y en `DEUDA.md`, **remedido tras `#280`**: `/precios` llega a **9** y
> **8 son un solo icono**; la invitación del CTA corre en **las doce vistas**. Orden por
> rentabilidad, en la ficha — y `#280` deja el **patrón** de cómo se saca una pieza de la lista sin
> dejarla mal parada.
> **2 · `Elementos Fachada`: VALORADO (`#281`) y ⏸️ PARADO.** ✅ **`[DECIDIDO owner]`: el GRUPO D ·
> Siluetas se retira ENTERO** —*«no me gusta»*— **y las otras 32 piezas entran**. Con D se va la
> figura vieja del cristal. ▶ **Su propio `D9` ya decía el porqué** («la misma pose transformada, y
> se nota cuando se repite»), y **seis de los ocho tratamientos sobreviven dentro de F/G** con las
> poses nuevas. ⚠️ **No toca el logotipo**: medido, `client-logo.svg` no contiene ese path.
> ✅ **Y `D4` y `D6` se van también** (los dos únicos sin gemelo): el grupo desaparece **sin
> excepciones** y el kit queda en **32 de las 40**. ⚠️⚠️ **Coste declarado**: `D6` era la **única
> pieza del kit que contaba un movimiento**, así que **de este artboard no sale ninguna animación** —
> el movimiento lo sigue decidiendo `Microanimaciones PJP` (`#277`).
> ⚠️ El artboard se volvió a subir **estructuralmente idéntico** y **otra vez con la codificación
> rota**: la copia local sigue caducada y sin sobrescribir. ✅ **Y lo otro decidido:
> `[DECIDIDO owner]` se construye el HUECO DE ILUSTRACIÓN POR INSTALACIÓN**, que cierra la deuda de
> `#257` y sería el tercer hueco por instalación tras el logotipo y el icono — el **primero para
> ilustración y no para marca**. ⚠️ **Su diseño no está hecho**: es lo primero que hay que
> especificar, y de él depende todo el material que es arte de este cliente (6 manchas + 12 poses).
>
> ❗❗❗ **EL ORDEN PARA RETOMAR `#281`, y no es «empezar a implementar»** (medido, no opinado):
> **A · Lo que NO depende de nada y se puede hacer YA** — las piezas 🟩 de puro mecanismo:
> generalizar la trama `A1` (que **ya está escrita** en `.menu__grain`: solo hay que sacarla del menú
> y darle densidad), `A2`, `A5`, y las **dos variantes de tira que faltan** (`C2` en cuñas y
> punteada, sobre `--strip-1..5`, que ya coincide en orden 5 de 5). No tocan el canvas ni el hueco.
> **B · Refrescar el canvas.** Bloquea todo lo que toque un dibujo: la copia local está caducada y un
> pegado llega roto. `/design-login` en sesión interactiva, o el `.dc.html` en `mockup_playjumppark/`.
> **C · DISEÑAR el hueco de ilustración** (spec, no código). ⚠️⚠️ **Y ésta es la razón de que vaya
> ANTES de extraer nada**: medido, **hoy NO existe mecanismo genérico** — cada hueco es una ruta a
> mano (`img/client-logo.svg`, `client-logo-ink.svg`, `client-favicon.svg`, cada una con su
> `@filemtime` + `asset()` en su Blade). **Dieciocho dibujos no pueden ser dieciocho rutas
> copiadas**, y extraer las poses antes de conocer la forma destino es extraerlas dos veces.
> **D · Solo entonces, sacar el material**: las **6 manchas ya son ficheros** (`splash-1..6.svg`,
> 7.979 B, mismo orden que `B1`); las **12 poses NO** —viven en línea dentro del artboard—. Con
> `<use>`, nunca repitiendo el `<path>` (`#266`).
> **E · Y al final, montar piezas.**
> **3 · Falta el OJO del owner** en: el logotipo (`#275`), la cascada de franjas del cajón (`#277`),
> el desenlace (`#278`) y **el interruptor que ya para** (`#280`). Y **subir a staging** lo de
> `#277`/`#278`/`#280` — lo de `#275`/`#276` ya está.
>
> ▶ **`#281` — EL KIT DEL MURAL, VALORADO (no implementado).** Método de `#277`: primero qué ya
> está. ▶ **El vocabulario de base YA ESTABA**: la trama A1 **es `.menu__grain` al dígito** (1,4 ·
> 1,6 · 20×20), la tira C2 coincide **en orden 5 de 5** con `--strip-1..5`, la sombra dura de E1
> **es** `--shadow-float`, y las cuatro tipografías son los cuatro tokens. **Lo nuevo no es la
> técnica: es el repertorio de formas.** Lo único que falta de mecanismo es `mix-blend-mode`, hoy
> con cero usos.
> ⚠️⚠️ **La copia local del canvas está CADUCADA** (sin los grupos A, F y G) **y el fichero pegado
> trae la codificación rota mientras la local está en UTF-8 sano**: el daño lo trae el pegado, no el
> canvas — no se sobrescribe. `DesignSync` sigue sin autorización.
> ▶ **Su `D9` desapareció porque el cliente resolvió su propio hueco, y MEJOR de lo que pedía**:
> pedía 4 recortes PNG y entregó **12 poses en SVG a un color plano**, que sí se recolorean, escalan
> y recortan.
> ▶ **Una geometría, doce composiciones**: plano, contorno y troquel salen del **mismo** `<path>`
> (verificado), y las composiciones van con `<use>` — medido, `D8` pesa **100.374 B para 24
> figuras** y con `<use>` son ~5,4 KB, **18,6× menos**.
> ⚠️ **Siete contradicciones medidas del artboard**, entre ellas que titula «Cuatro reglas» y son
> cinco, y que su **F8 rompe su propia regla 05** (14 copias, 9 poses, 5 repetidas).
>
> ▶ **`#280` — EL INTERRUPTOR DEL TITULAR PARA, Y PARA ENCENDIDO.** `[DECIDIDO owner]`: tres ciclos
> —en `--switch-cycles`, de la instalación— y vuelve a saltar cuando el hero regresa al viewport.
> ⚠️⚠️ **El defecto que avisaba `#279` era peor de lo previsto, y solo lo dijo el navegador**:
> acotando iteraciones la pieza no quedaba «apagada», quedaba **apagada con la palabra ON al 100 %
> encima** —el rótulo no tenía `opacity` propia—. El estado encendido entero vivía **solo dentro del
> bloque de `prefers-reduced-motion`**: *el reposo de una pieza no se escribe dentro de una excepción
> de accesibilidad; si solo existe allí, fuera no existe*, y no lo ve nadie porque quien revisa con
> movimiento reducido la ve perfecta.
> ▶ Dos mitades: **el reposo sube a la regla base** (el bloque de accesibilidad se queda solo con
> `animation: none`) y **el corte cae DENTRO del tramo encendido** —`calc(var(--switch-cycles) - 1 +
> 0.6)`—, donde el valor es constante y coincide con el reposo. Medido: **0 px de salto**.
> ⚠️⚠️ **El rearranque no puede ser WAAPI**: una animación terminada con `fill: none` **desaparece de
> `getAnimations()`** (medido, 1 → 0). Se descarta y se recrea desde `ui/hero-switch.js`, que **no
> decide nada de diseño**. Sin JS la pieza sigue completa: salta al cargar y descansa encendida.
> ▶ **Presupuesto de `#279`**: `/` pasa de **5 a 2** bucles —ya cumple el techo de dos de su
> artboard— y `/entradas` de **6 a 4**; las declaraciones en bucle, de 24 a 21.
> ⚠️ **Y corrige a `#279`: el interruptor eran 3 bucles, no 2** — contar animaciones en un instante
> subestima una pieza cuyo ciclo apaga una de sus partes.
>
> ▶ **Lo cerrado hoy**: `#275` el logotipo por fin idéntico (tres defectos, todos en la EXPORTACIÓN) ·
> `#276` el eslogan pegado al titular y el CTA de móvil · `#277` las microanimaciones valoradas + U2 y
> U3 · `#278` el desenlace en dos piezas y sin confeti · `#279` el presupuesto de movimiento, medido.
> ⚠️⚠️ **La lección de la jornada, que se repitió CINCO veces**: una sonda estática propia da un número
> creíble y falso, y **solo un CONTROL en navegador lo desmonta**. Pasó con el reborde del logotipo,
> con el hueco del eslogan, con los bucles sin proteger (dije 10, luego 6, eran **0**), con la
> compensación del titular y con el «22,17 px» que eran 7,3. *Cuando un número propio decida un
> cambio, medirlo en el navegador y con un control que deba salir distinto de cero.*
>
> ▶ **`#278` — EL DESENLACE SON DOS PIEZAS.** `[DECIDIDO owner]`: «quitamos el confeti, tampoco vamos
> a saturar al cliente». Ya marcaba lo mismo dos veces —desde `#258` está la pegatina de éxito, y el
> artboard de estados prohíbe que conviva con otra—, y con el sello serían TRES donde el techo son dos.
> ▶ Queda: **la pegatina** entra creciendo y **el código de la reserva se SELLA**, secuenciados (el
> sello espera `--dur-estado`, porque «nunca se solapan»). El sello es la única rotación animada del
> sistema. ⚠️ **Va NEUTRO y es desviación decidida**: su artboard lo estampa en amarillo porque allí
> es la única pieza; aquí comparte pantalla con una pegatina verde y dos rellenos saturados harían un
> semáforo. Volver al amarillo es una línea si el owner lo prefiere al verlo.
> ⚠️⚠️ **Retirar el confeti habría dejado CIEGA una guarda ajena sin ponerla roja**: `SidebarMountTest`
> usaba `celebrate()` como DELIMITADOR, y sin él el recorte salía vacío — una cadena vacía no contiene
> nada, así que su aserción pasaba vigilando la nada.
> ⚠️⚠️ **Y destapó un hueco de `#277`**: la hora COMPLETA no la cubría nadie porque **ninguna fixture
> tenía una franja llena**. *Un atributo solo está cubierto por el caso que lo hace aparecer.*
>
> ▶ Anterior: **2026-08-30 (tarde) — `#277`: las microanimaciones del cliente, valoradas.**
>
> ▶ **`#277` — SU ARTBOARD DE MOVIMIENTO: LO QUE YA ESTABA Y LO QUE NO.** El owner entrega
> `Microanimaciones PJP` y pide valorarlo antes de implementarlo. **Las cuatro curvas y las siete
> duraciones ya eran idénticas** (`#222` tomó este mismo artboard), y su regla «entra rebotando, sale
> limpio» se cumplía sin que nadie la escribiera.
> ▶ **U2 · movimiento reducido**: su norma dice «quitar el recorrido pero **mantener el fundido**», y
> nosotros hacíamos `transition: none`. Medido con control: el rótulo del CTA doble aparecía **de
> golpe** y el bloque de cuenta perdía el fundido **y su `visibility` diferido** —que es lo que lo
> saca del orden de tabulación—. ⚠️ Había **un segundo bloque de movimiento reducido para el mismo
> elemento 200 líneas más abajo** que lo volvía a matar: *gana el último, no el más específico.*
> ▶ **U3 · la cascada de franjas**, con sus números al dígito (cae de 32 px, LONA, 420 ms, aplasta a
> 1,12/0,76, desfase 90 ms verificado en el cajón real). ⚠️⚠️ **Y destapó que `sellable` llevaba desde
> siempre en el contrato y el cajón no lo leía**: `SlotOffer` marca las franjas llenas a propósito
> —«se muestran deshabilitadas, no se ocultan»— y el paso 3 las pintaba clicables. **El panel sí lo
> respeta**; el cajón del cliente no.
> ⚠️⚠️ **Y un `opacity: 0.62` nació MUERTO**: la cascada acaba en `opacity: 1` con `both` y **fija su
> último fotograma**, que gana a la regla CSS. Mecanismo de `#265`, ahora sobre la opacidad.
> ❗ **Lo que queda del artboard, en `DEUDA.md`**: los **22 bucles decorativos** que su norma prohíbe
> —y que **contradicen su propio artboard `6d`** y dos `[DECIDIDO owner]`—, el **hover pegatina** (que
> toca el mecanismo de color de acción de `#209`) y el sello + check del desenlace.
> ⚠️⚠️ **Cinco sondas estáticas mías dieron números falsos sobre lo mismo**: dije 10 bucles sin
> proteger, luego 6, y **son cero**. Lo zanjó el navegador.
>
> ▶ Anterior: **2026-08-30 (mediodía) — `#276`: el eslogan pegado al titular y el CTA de móvil.**
>
> ▶ **`#276` — DOS AJUSTES PEQUEÑOS DEL OWNER, DOS NÚMEROS ESCRITOS A MANO.**
> **El eslogan** se centraba sobre el titular porque el bloque del hero va centrado: ahora comparte
> caja con él (`.hero__headline`) y se pega a su filo IZQUIERDO. ⚠️⚠️ **`margin: 0` no deja el hueco
> en cero** —quedaban 7,3 px en escritorio y 3,0 en móvil, y los ponen los DOS textos—, así que cada
> uno paga su parte **en su propio `em`**: es lo único que escala, porque a 1440 el titular mide 5,5×
> el eslogan y a 390 solo 2,9×. El tope lo pone el CHOQUE (va girado −2,2°): barrido en siete
> ventanas, **−0,18 em** deja el mínimo en 0,2 px y −0,22 ya solapa.
> **El CTA de móvil** sube 9 px, y de ahí colgaban **dos números a mano**: el `92px` con que el
> lanzador de ofertas se aparta y el `84px` que el hero reserva por abajo. Subir la barra sin
> tocarlos la habría metido bajo el lanzador **sin que fallara nada**. Ahora los dos derivan de
> `--book-bar-block`.
> ⚠️⚠️ **Tres sondas propias dieron números creíbles y falsos**: medir el hueco por FILAS daba 22,17
> px donde eran 7,3 (se saltaba la altura de mayúscula) · el «mínimo» sobre un tercio del ancho
> escondía el solape del resto · y la guarda del envoltorio **nació laxa**.
>
> ❗❗❗ **`#275` — LOS TRES DEFECTOS DEL LOGOTIPO ESTABAN EN LA EXPORTACIÓN, NO EN NUESTRO CSS.**
> Quinta sesión sobre el mismo dibujo (`#253` · `#263` · `#267` · `#273` · `#274`), y la que la
> cierra. `[DECIDIDO owner]`: «lo quiero IDÉNTICO».
> ▶ **1 · `text-shadow` NO arrastra el `-webkit-text-stroke`.** Su sombra son copias del glifo
> **desnudo**; el export les puso a los 26 `<use>` de extrusión el trazo de la capa de color, o sea
> **3,25 px más gordas por lado**. El faldón azul bajo las letras medía **7,1 px contra sus 4,0**, y
> lo tenían **1121 de 1121** columnas frente a 852 de 1137 en el suyo. Ahora: **4,13**.
> ▶ **2 · El velo del borde inferior salía 3,4× más fuerte y en el tono equivocado.**
> `background-clip: text` mide sobre la **caja de línea**; `objectBoundingBox`, sobre la **tinta**.
> No son la misma caja, así que sus paradas copiadas literalmente ponen al pie de las letras el valor
> de arranque: **α 0,377 contra 0,112**. Y el lockup usa **un velo por palabra** —frío bajo la fría,
> marrón bajo la cálida— mientras el export dejó el frío para las dos: un velo azul sobre amarillos
> **desatura**, que es el «pierde color vivo» del owner. Corregido: **6 de 8 muestras idénticas**.
> ▶ **3 · La silueta venía RESTADA de las letras.** A la «A» le faltaba el **16,8 %** del área.
> Invisible en reposo —el saltador ocupa el hueco— y a la vista durante toda la animación, que es
> donde el owner la cazó. Reconstruida desde **Lilita One** con la afín recuperada del propio trazado
> usando P y L: control de consistencia **0,001 %**, y la L —validación independiente— cae con
> **0,52 %** de área.
> ⚠️⚠️ **La misma geometría vive en DOS sitios** (`#u1` y el `<path>` del relleno de color): arreglar
> solo uno deja la parte restituida **en BLANCO** y no falla nada.
> ❗ **`#274` queda REVERTIDO**: sus bandas al 65 % no eran el defecto —las del export ya eran las del
> mockup al dígito— y el factor **creó** un anillo marino de 1,14 px por fuera del cian, en el 100 %
> de las filas. `scripts/logo-contorno.php` se retira; entran `logo-sombra.php` y `logo-letra-a.php`,
> **idempotentes** los dos.
> ❗❗ **La lección de método**: cuatro tandas midieron el DIBUJO —bandas, densidad, halo, geometría— y
> las cuatro dieron «equivalente». Lo que faltaba era medir el **MECANISMO**: qué dibuja realmente
> `text-shadow`. *Cuando cinco mediciones del resultado dicen que no hay defecto y el ojo dice que sí,
> lo que hay que medir es la herramienta que lo produce, no el resultado otra vez.*
> ⚠️⚠️ **Siete trampas de instrumento**, todas con números creíbles: el subrayado por defecto del
> `<a>` de su lockup (inflaba su caja de 60,75 a 65,5 px) · las `figcaption` dentro del recorte · el
> antialias clasificado por vecino más próximo · **dos SVG en un documento comparten `id`**, así que
> el arreglo salía idéntico al original · un `<g>` recortado con expresión regular que dejó el SVG mal
> formado · medir una opacidad por un canal con **denominador 10** · y cambiar el color del velo a
> media medición. **Las cazó tener siempre un CONTROL.**
> ⚠️ **Falta el OJO del owner en navegador** y **subir el asset a staging**: el logotipo NO viaja en el
> despliegue (`deploy.sh` excluye la marca).
>
> ▶ Anterior: **2026-08-29 (noche) — carril C: el ÁREA TÁCTIL de
> 44 (`#264`), la vuelta del owner sobre el CTA flotante y el logotipo (`#265`) y, tras una revisión
> adversarial de esa tanda, **`#266`: el salto del logotipo NUNCA se había visto**.
> ❗❗❗ **2026-08-29 (tarde) · carril A — LA REVISIÓN ADVERSARIAL DEL CUMPLEAÑOS MIXTO Y SUS CINCO
> TANDAS DE ARREGLO** (`#249` · `#268`→`#272`). **Si tocas el suplemento, empieza por
> `specs/cumple-mixto.md` §17.**
> El subsistema (`#243`→`#248`) aterrizó en una jornada sin que nadie lo revisara: ~1.300 líneas que
> escriben dinero sobre pedidos ya pagados. La revisión encontró **seis defectos y son UNO**: *el
> importe no se guardaba, se recalculaba entero desde el catálogo vigente en cada disparo — y cuando
> no había con qué calcular, se escribía CERO en vez de dejarlo quieto.*
> ❗❗ **El peor lo dispara el CLIENTE**: vaciar sus casillas de edad y guardar **borraba su propio
> cargo**. Mentir con la edad —que el owner ya dio por inevitable— obliga a inventarse un número
> creíble; borrarla no. Con él caían `RGPD-01` (anonimizar borraba la deuda) y cualquier tramo tocado
> en el catálogo.
> ⚠️⚠️ **Y la guarda que existía para el defecto del precio pasaba en VERDE con el defecto puesto**:
> aseveraba el importe justo después de subir la tarifa, **sin volver a guardar**, y el defecto vivía
> en el guardado siguiente. *Una guarda que no ejercita el disparador no vigila la regla, vigila el
> reposo.*
> ▶ **Lo que AGUANTÓ, verificado aparte y no por sus propios tests**: `PAY-16`/`PAY-17` cierran
> (13.590 → 15.090 de valor, +1.500 a puerta, online intacto), el AFORO no se mueve (20 → 20) y por
> **DOS** mecanismos —`slot_id = null` ya excluye la fila **aunque el portador fuera un pack**, lo que
> matiza a §12.3—, idempotencia, reversibilidad al dígito, rastro sin sesión y cascada al cancelar.
> ▶ **Los cinco arreglos**: una ausencia no es una corrección (`#273`) · el operador ve el cargo
> huérfano (`#269`) · **el RECIBO**, con el `[DECIDIDO owner]` de los **14,00 € y no 24,00 €**
> (`#270`) · **no se construye el perdón** `[DECIDIDO owner]` —el argumento decisivo es suyo, de
> `#244`: «cualquier gestión de dinero post-reserva ya cobrada se hace en las instalaciones»— y el
> panel deja de ofrecer un gesto que deshacía **en el mismo clic**, con dos correos contradictorios
> (`#271`) · la pieza entra en el `CRITICAL_RE` y la regla en **`PAY-19`** (`#272`).
> ❗❗ **Lo que NO cierra está en `DEUDA.md`, y el 2026-08-30 se RE-MIDIÓ y salió peor de lo escrito**:
> el ejemplo del caso espejo («Kids a 1–12 → 40,00 €») **no reproduce con los precios reales** —venía
> de una sonda que había forzado Kids por encima de Jump—, y al re-medirlo apareció **un agujero en
> `#270`/`PAY-19` que no estaba documentado: un tramo ENSANCHADO destruye un cargo ya escrito**
> (40,00 € → 0,00 €), porque el veredicto sigue COMPLETO y la abstención de `#268` solo mira los
> incompletos. **El recibo congeló el precio, no a quién se le aplica.** Cerrarlo exige sellar el régimen **en la reserva** —columna + migración— y es
> decisión del owner. Con él va su gemelo: la etiqueta MIXTA puede **contradecir** al cargo, y ahora
> de forma permanente. **Se cambió un fallo de dinero por uno de coherencia**, que es mejor negocio,
> pero hay que saberlo.
> ⚠️ **Falta el OJO del owner**: nada de esto se ha visto en navegador.
>
>
> ❗❗❗ **`#274` — DOS NÚMEROS NUESTROS PUESTOS ENCIMA DE LOS SUYOS**, y los dos los vio el owner.
> ▶ **El CONTORNO del logotipo**: su lockup apila cuatro `-webkit-text-stroke` decrecientes y la
> profundidad la da un `text-shadow` de **6 pasos**; el nuestro son `<use>` con `stroke-width` y
> **26 pasos**. ⚠️⚠️ **La aritmética decía que no había defecto** —las bandas visibles calculan
> idénticas (0,90 · 1,45 · 0,90 px) y el stroke base sale 6,49 contra 6,50— **y al ponerlos uno
> encima del otro el nuestro es visiblemente más gordo**. Se resolvió como `#273`: **enseñando
> opciones**; eligió el **65 %**. ⚠️ Va por GUION (`scripts/logo-contorno.php`) porque **modifica el
> asset del cliente**: cuando el owner lo re-exporte, hay que volver a pasarlo. Y **no viaja en el
> despliegue** — `deploy.sh` excluye los ficheros de marca.
> ▶ **El HERO de móvil**: `--hero-h-end: min(72vh, **520px**)` en el `@media` de 768 — un segundo
> tope que **no sale del mockup** (el suyo es `Math.min(vh * 0.78, 660)` para TODAS las ventanas).
> **Muerde en toda ventana de más de 722 px**, así que el hero se clavaba en 520 y el hueco crecía
> 1:1 con el teléfono: **252 px vacíos a 390×844 (29,9 %) y 340 a 390×932 (36,5 %)**. Retirado →
> **658 px (78 %) y 114 de hueco (13,5 %)**. Escritorio intacto.
> ⚠️⚠️ Y `--hero-h-end` era **la única de las tres expresiones de ventana del hero sin el par
> `vh` → `svh`** que sus hermanas declaran desde `#252` — justo la que fija el punto estático.
> ⚠️ **Cuatro trampas de instrumento** en la medición del contorno, todas por comparar cosas no
> comparables — entre ellas **escalar el `font-size` sin caer en que `-webkit-text-stroke` son px
> ABSOLUTOS y no escalan**.
> ⚠️⚠️ **Y una del arnés**: los 24 refutadores del hero cayeron por cuota, y el script los devolvió
> como «refutados» con `0/0`. *Cero votos a favor no es refutado: es no haber podido votar.* Se
> verificó a mano en cinco ventanas.
>
> ❗❗ **`#273` — EL LOGOTIPO NO LLEVA SOMBRA CSS, y eso cierra CUATRO vueltas.** `[DECIDIDO
> owner]`, elegido **mirando una tira de cinco niveles**. Este SVG **ya trae su relieve horneado**
> —26 pasos de extrusión por palabra, frente a los **6** del `text-shadow` del mockup— y cualquier
> `drop-shadow` encima se le suma. Su lockup sí necesita el filtro porque su extrusión es fina.
> ⚠️⚠️ **La medición de `#267` era CORRECTA y la conclusión NO.** Densidad total y halo daban 4-5 %
> de diferencia entre su lockup y el nuestro; pero **lo que el owner juzga no es un agregado**, es
> los dos dibujos uno al lado del otro. *Una métrica agregada puede decir «equivalente» sobre dos
> cosas que el ojo separa al instante.*
> ▶ Eso **devuelve la razón a `#253`**, que `#267` dio por refutado: su diagnóstico —«copiar un
> filtro no es copiar un resultado si el sujeto es otro»— era bueno; falló al **rebajar en vez de
> retirar**.
> ❗❗ **Y la lección de método, que es la que vale**: a la cuarta vuelta la respuesta no era otra
> medición, era **enseñar opciones y dejar elegir**. Tres tandas gastadas ajustando un número que
> sólo su ojo podía fijar. *Cuando alguien dice varias veces que algo se ve mal y cada corrección
> falla, lo que falta no es precisión: es la pregunta.*
> ⚠️ Y **descartar lo trivial primero**: al preguntarle dónde miraba salió que **staging sirve el
> filtro de `#263`** y no ha visto nada de hoy. No era el caso, pero pudo serlo.
> ⚠️ Si un cliente quiere sombra, la pone su `client.css`: es decisión de marca. Sin ella el
> logotipo sigue legible sobre tinta (contorno blanco de 14,4 unidades), verificado en el menú.
>
> ❗❗ **`#267` — LA SOMBRA DEL LOGOTIPO: TRES VUELTAS DEL OWNER, DOS AJUSTES A OJO, NINGUNA
> COMPARACIÓN.** ⚠️ **Corregida por `#273`.** `#253` bajó la tinta de 45 a 30 razonando que «copiar un filtro no es copiar un
> resultado si el sujeto es otro»; `#263` corrigió el radio y la dejó en 18 comparando **cuatro
> variantes NUESTRAS entre sí**. El original no entró en ninguna de las dos.
> ▶ **Medido por fin, con su lockup delante** (que entregó en HTML, así que por primera vez se puede
> renderizar el suyo al lado del nuestro): densidad de sombra **A su lockup 1.193.218 · B nuestro SVG
> con SU filtro 1.252.968 · C lo que teníamos 645.997**. B está a un **5 %** de A; **C era la mitad**.
> ▶ Eso **refuta el razonamiento de `#253`**: el mismo filtro sobre nuestro sujeto SÍ da su sombra.
> Quedan sus números: `0 6px 16px` al **45 %** y `0 1px 0` al **25 %**.
> ⚠️ **Lo que se copia son los NÚMEROS, no el color**: sigue leyendo `--paper-fg`, o dentro del menú
> de tinta la sombra se volvería luz. Verificado en papel, portada y menú.
> ⚠️ Y de paso se comparó la GEOMETRÍA: la figura sale a **0,181** del ancho de JUMPPARK contra su
> 0,177, y colocada en 0,198 contra 0,192 — **2-3 %**. Lo que se veía distinto era la sombra.
> ⚠️⚠️ *Cuando el owner dice tres veces que algo se ve distinto, lo que falta no es otro ajuste: es
> la comparación que nadie ha hecho.*
>
> ❗❗❗ **`#266` — TRES TANDAS MIDIENDO QUE LA ANIMACIÓN EXISTE, NINGUNA QUE EL DIBUJO SE MUEVA.**
> El salto se declaraba sobre `#fig`, que vive dentro de `<defs>` y **no se pinta**: lo que se pinta
> son los 21 `<use>` que lo referencian, y **una animación CSS sobre el original no alcanza al clon
> del `<use>`**. `#254` lo introdujo, `#263` lo dio por arreglado en once vistas y `#265` le puso la
> física del mockup — y el logotipo **no se movió ni un píxel** en ninguna de las tres.
> ▶ **El CONTROL es lo que lo zanjó, y es lo que faltaba las tres veces**: `style.transform` en
> línea sobre `#fig` repinta **773 px**; la misma transformación por `@keyframes`, **cero**.
> ⚠️⚠️ **`#263` ya había escrito la lección —«que la pieza llegue no es que se mueva»— y la aplicó
> un nivel por encima.** Ésta es la de abajo: *que una animación exista y compute no es que el
> dibujo se mueva.* Y **un cero sin control no distingue «no se mueve» de «no lo estoy mirando
> bien»**.
> ❗❗ **Y la amplitud estaba 7,5 VECES CORTA por la misma clase de error**: los desplazamientos
> venían copiados del mockup en píxeles, y **dentro de un SVG los `px` son unidades del `viewBox`**
> (factor 0,1249 aquí). La silueta entraba desde **11,24 px** donde el mockup la trae desde 90.
> ▶ La unidad correcta es el **porcentaje** con `transform-box: fill-box`, que se mide contra la
> figura — y eso es **más white-label que el propio mockup**, que ata su salto a una silueta de 30 px.
> ⚠️⚠️ **Y el «0,000 px de desviación» de `#265` era una cifra ADIMENSIONAL disfrazada de píxeles**:
> su comparador normalizaba la escala fuera, así que validaba la FORMA de la curva y era ciego al
> TAMAÑO. Estaba escrito en **seis sitios**; todos corregidos.
> ⚠️ **Cuatro guardas nacieron ciegas o laxas y la revisión las cazó**: tres leían el CSS **crudo**
> (un `@keyframes` comentado las satisfacía), el conteo de curvas no miraba **en qué fotograma**
> están, el asentamiento no veía un `animation-fill-mode` suelto, y la de `fill-box` buscaba la
> declaración en toda la hoja —donde hay **14**— y no mordía al quitarla del logotipo.
> ⚠️⚠️ **Y una regla del repo incumplida DOS VECES en la misma sesión**: *commitear en local ANTES
> de mutar*. Dos `git checkout` para revertir una mutación se llevaron todo el trabajo sin commitear
> del fichero.
> ▶ **De dónde salió todo esto: de una revisión adversarial del propio trabajo antes de empujarlo**
> —cinco revisores sobre el diff y tres refutadores por hallazgo—. El hallazgo que lo cambió todo
> salió **3/3** y con un control que yo no había hecho.
>
> ❗❗❗ **`#265` — EL SALTO DEL LOGOTIPO NO TENÍA SU FÍSICA, Y LOS FOTOGRAMAS SÍ ERAN LOS SUYOS.**
> `brand-hop` copiaba al dígito los ocho fotogramas del mockup y aplicaba **una sola curva a todos**
> —`--ease-cae`, que tiene overshoot (1.56)—: un salto cuyas posiciones ya describen dos rebotes,
> **rebotando además dentro de cada tramo**. El mockup declara **siete curvas, una por tramo**,
> porque eso no es estilo, es **gravedad**: sube desacelerando y cae acelerando.
> ⚠️⚠️ **Es el hallazgo de `#262` por el otro lado** —allí *el rebote no vivía en la curva, vivía en
> los fotogramas*—, y la lección completa es que **hay que saber cuál de las dos lleva el movimiento
> antes de tocar ninguna**. Faltaban además el **asentamiento** del lockup (se hunde 2 px al recibir
> al saltador) y el **tempo**: sus números de diseño son los nuestros (420 y 900) pero su código los
> divide por `0.9`, así que su coreografía va un **11 % más lenta** de lo que sus números dicen.
> ▶ **Medido contra su fórmula reimplementada, 21 muestras: 0,000 px de desviación.**
> ⚠️⚠️ **Y el `fill: both` del asentamiento MATABA EL HOVER del logotipo**: `both` implica
> `forwards`, la animación deja el `transform` fijado y **gana siempre a la cascada**. No fallaba
> nada. ⚠️ **La sonda dijo primero que estaba bien**: preguntaba «¿tiene transform?» y la respuesta
> era `matrix(1, 0, 0, 1, 0, 0)` — que **no es «no hay transform», es la identidad**.
> ▶ **El ASSET está bien y se comprobó ANTES**: `client-logo.svg` es **byte a byte** el que exportó
> el owner. «Idéntico al mockup» no podía ser el fichero.
> ❗ **Lo que queda es del owner: el RELEVO DE LA Y necesita una pieza que el SVG no trae.** Su
> `#u1` dibuja «PLA» y la Y la hace la silueta, así que durante **475 ms** el logotipo se lee «PLA
> JUMPPARK». `[DECIDIDO owner]`: **lo exporta con la Y**; qué tiene que traer el fichero está en
> `INSTALACION-CLIENTE.md` §4.a.sexies. **Eso CORRIGE a `#263`**, que lo declaró imposible: el hecho
> era cierto, la conclusión no.
> ❗❗ **Y el CTA flotante de móvil: 48 → 56, y su sombra era el ROL EQUIVOCADO.** Declaraba
> `--shadow-float`, que con el paquete de este cliente vale **`5px 5px 0`** —dura, de tinta pura,
> desplazada abajo y a la derecha, en un botón a 10 px del borde—. ⚠️ **Su propio comentario decía
> la respuesta**: «el cuarto rol de elevación (`#217`)» **es la familia `--shadow-nav-*`**. El
> arreglo no fue poner otra sombra: fue **retirar ésta**, que pisaba la que el componente ya traía.
> ⚠️ El alto sale de un token propio (`--book-bar-h`) porque arriba el racimo baja a 48 al compartir
> fila con el logotipo, y abajo no comparte con nadie.
> ⚠️⚠️ **Y una regla del repo que esta sesión pagó por no seguir**: *commitear en local ANTES de
> mutar*. Un `git checkout` de la mutación se llevó el CSS entero de la vuelta — y **la primera que
> avisó fue la aserción nueva** de que una coreografía declarada exista de verdad.
>
> ❗❗❗ **`#264` — EL OBJETIVO TÁCTIL DE 44 LLEGA A LA LANDING.** Lo que `#259` §6 dejó medido y sin
> tocar. **37 → 1** control bajo 44 en las siete vistas públicas a 390 px; el que queda es el enlace
> **en línea** del texto de cookies, que **WCAG exime** (2.5.5 y 2.5.8) y tiene caso propio para que
> la excepción no parezca descuido.
> ▶ **Dos decisiones del owner, tomadas con los números delante**: el objetivo crece **al dedo y no
> a la vista** donde el dibujo está 1:1 con el mockup —un pseudo centrado (`[data-tap]`) bajo
> `(pointer: coarse)`—, y **el bloque legal del pie pasa a TIRA que se desliza**, el patrón de
> `#252`. Crecerlo todo subía la FAQ 96 px y el pie 54.
> ⚠️⚠️ **EL MECANISMO YA EXISTÍA Y ENCOGÍA**: el «Lote 9» tenía el mismo pseudo centrado para cuatro
> controles del cajón, con `width: var(--tap-min)` **a secas** — un cuadrado de 44 sobre un control
> de 60 le quita 8 px por lado, **y el control sigue funcionando**. Lo cazó la guarda de unicidad
> del token, no la memoria. Corregido al `max(100%, …)`, que es lo único que un mínimo debe hacer.
> ⚠️⚠️ **Y EL ALTO DE LAS DOS TIRAS DEL PIE TAMBIÉN NECESITA LA PUERTA DE PUNTERO**, y eso solo lo
> dijo medir en las dos ventanas: sin ella el pie encogía 30 px en teléfono —lo buscado— y **crecía
> 20 en escritorio**, donde el mockup lo fija. *Un objetivo táctil que engorda la pantalla donde no
> hay dedos es un cambio de diseño con otro nombre.* Con la puerta, escritorio es **idéntico**.
> ⚠️⚠️ **LA SONDA MINTIÓ DOS VECES, LAS DOS CON NÚMEROS CREÍBLES**: recortando el área contra los
> ancestros **en coordenadas de viewport** con el control fuera de la parte visible de su carril
> (áreas **negativas**, y un negativo pasa el filtro de «menor que 44»), y luego calculando el
> rectángulo del pseudo **sin aplicar su `transform`** (altos de **58** donde son 44). *Un
> pseudo-elemento no está donde dicen su `top` y su `left`: está donde lo deja su matriz.*
> ⚠️ Y **115 · 51 · 37 son tres cifras del MISMO defecto**: instancias, sonda a medias, e
> instrumento sano. **La que vale es la del instrumento que ha demostrado estarlo.**
> ⚠️ Guarda `TouchTargetTest`: 9 casos, **8 mutaciones y las 8 muerden** — y la de mudar el área a
> `::after` muerde en tres, porque ahí `.ck-tgl::after` dibuja el pomo del interruptor de cookies.
>
> ▶ **NUMERACIÓN, para el otro carril**: esta sesión toma **`#264`–`#274`**. El carril A venía en
> `#243`–`#248`. **Mirar el remoto al elegir número no basta: hay que volver a mirarlo al PUBLICAR.**
>
> Antes: **2026-08-29 (13:30) — carril A:
> CUMPLEAÑOS MIXTO, cinco tandas (`#243`→`#247`) y el descuento aparcado con su diseño (`#248`); y
> carril C, el interruptor del titular (`#254`→`#262`)**.
> Antes: 2026-08-28 — carril A (la FORMA del panel y los menores: `#223`, `#224`, `#232`, `#234`,
> `#236`, `#237`) y carril C (el mockup 1:1: `#225`→`#235` y **`#238`, la COLUMNA**). ▶ Y la noche
> del 28, **el CAJÓN EN MÓVIL (`#239`)**.
> ▶ **2026-08-29 · carril C: `#252` — el IMÁN de los dos puntos estáticos, el pie a UNA fila y fuera
> la marquesina. Y `#253` — ocho puntos de la portada, DOS de ellos fallos.**
>
> ❗❗❗ **`#263` — EL LOGOTIPO NO SALTABA EN ONCE DE LAS DOCE VISTAS.** La regla la disparaba
> `body.nav--live`, y **esa clase la pone el componente del HERO, que solo existe en la portada**.
> Medido en `/servicios`: logotipo pintado a 70 px, `#fig` en el árbol y `getAnimations()` a **cero**.
> ⚠️⚠️ **El comentario del propio CSS afirmaba lo contrario** —«en las once vistas sin hero salta una
> vez y se queda»—: una suposición escrita como hecho, desde `#254`. ⚠️⚠️ Y **ninguna guarda lo vio
> con una mirando al lado**: `InlineBrandLogoTest` comprobaba que `id="fig"` llega al documento, y
> eso pasaba en verde con la animación muerta. ▶ *Que la pieza llegue no es que se mueva.* Guarda
> nueva, mutada con el fallo real y con el de al lado: las dos muerden.
> ▶ **El ASSET está bien y no hay que rehacerlo**: 27 pasos de extrusión por palabra, 16 en la
> silueta y los dos degradados — la receta exacta del mockup pasada a contornos.
> ⚠️ **La sombra: bajar el RADIO fue la mitad equivocada del ajuste de `#253`.** Son dos dimensiones
> independientes —**el radio hace la suavidad, la opacidad hace la densidad**— y bajar 16→7 dejó un
> borde duro. Comparadas cuatro combinaciones en navegador: queda **5 · 18 · 18 %**, la suavidad del
> mockup sin su densidad.
> ❗❗❗ **`#262` — EL INTERRUPTOR DEL TITULAR YA NO ES DIBUJO PROPIO: ES EL `6d` DEL CANVAS.**
> `[DECIDIDO owner]`: «tenemos ya el icono… **tráelo idéntico**». El canvas trajo un **LOTE 6** con
> cuatro variantes; `6d` es la ANIMADA — «El salto, no el deslizamiento». Sustituye al interruptor
> dibujado a mano de `#254`/`#261`, **así que la mitad de esas dos entradas que describe la cápsula
> con relieve `inset` está CADUCADA** (la otra mitad, la del CTA, sigue vigente).
> ⚠️⚠️ **«Idéntico» se CONSTRUYÓ, no se copió**: sus **diez** medidas son múltiplos exactos de
> **1/16**, así que en `em` sobre una sola unidad salen 1:1 *y* escalan. Medido en navegador: el
> bulbo va a **19,00**, asienta en **16,40**, sale a **−2,40** y el rótulo a **5,60 / 8,50** — todos
> al dígito.
> ⚠️⚠️ **Y de ahí salió la lección de la sesión: un token en `em` NO es una longitud.** Una custom
> property se sustituye como TEXTO y el `em` lo resuelve **el elemento que la usa**; el rótulo se
> cambia su propio `font-size`, así que ahí `--sw-u` valía un tercio y el sangrado salió a **1,84 en
> vez de 5,6**. **No falla nada, solo queda mal puesto** — y no lo ve ninguna guarda de tokens ni
> ninguna captura: lo cazó medir `left` y escalarlo a los 44 del artboard.
> ❗❗ **4.ª vuelta: pegado al texto y A LA DERECHA TAMBIÉN EN EL MÓVIL** («si hace falta el texto
> que se haga más pequeño»). Fuera el `margin-inline`, y el titular gana un tope
> `--hero-t-fit: calc((100vw - 52px) / 7.3)` dentro de un `min()`. ⚠️ **Sin media query, porque el
> tope es INERTE donde sobra sitio** (a 768 da 98 y el titular pide 64,5): un punto de ruptura menos
> es uno que no envejece. Medido: **un renglón en ocho anchos, 320 → 1280, y cero desbordes**, con
> el cuerpo intacto de 600 para arriba. ⚠️ La primera cuenta dijo que a 480 sobraba sitio y aun así
> envolvía: **faltaba el ESPACIO**, que un `Range` no cuenta cuando cae en fin de línea. *Un ancho
> medido sobre texto ya envuelto no es el que ese texto necesitaría en una línea.* ⚠️⚠️ **El 7,3
> depende de la PALABRA**, y `hero.l1` lo pone la instalación — con un rótulo más largo el
> interruptor volvería a caerse (`DEUDA`).
> ❗❗ **Y en la 3.ª vuelta el interruptor sube a la ALTURA DE LAS MAYÚSCULAS del titular** («grande
> al tamaño del texto, misma altura»). ⚠️⚠️ **Lo cazó el OJO del owner —«está más abajo»— con mi
> verificación diciendo que estaba bien**: había medido que las ALTURAS coinciden y **nunca comparé
> las POSICIONES**. *Medir la dimensión correcta de la cosa equivocada da un verde perfecto.*
> ⚠️⚠️ Detrás había **dos suposiciones falsas sobre la línea base**: un `inline-flex` **NO** la
> sintetiza en su borde inferior —**la toma de su primer ítem**, aquí el bulbo, centrado— y pasarlo
> a `inline-block` tampoco basta, porque el flex de dentro **la sigue propagando**. Lo zanja
> `overflow` ≠ `visible`, que es regla explícita de CSS: 19 px → **+2,6 / −2,0**. **Si alguien quita
> ese `overflow` por limpieza, la pieza se cae 19 px y no falla nada.**
> ⚠️⚠️ Y destapó que la talla era **la altura de mayúscula de BUNGEE metida a mano**: ahora sale de
> la unidad **`cap`**, así que sigue sola a la fuente de cada instalación. Va en `@supports` porque
> un valor inválido dentro de una custom property **no cae al anterior** — la cascada de respaldo no
> funciona con `var()`.
> ⚠️ El rótulo «ON» va **DENTRO** de la pista (2.ª vuelta del canvas el mismo día) con bucle propio,
> y **el nombre accesible se sirve aparte en `.sr-only`**: uno que parpadea cada 3,4 s no lo es.
> Verificado, sigue leyéndose «DIVERSIÓN ON». ⚠️ **La familia se HEREDA** (`--font-display`), no se
> escribe — y ahí el cliente se contradice: su norma dice «Bungee nunca por debajo de 20 px» y su
> artboard lo pone a **8,5** (`DEUDA`: ninguna guarda vigila esa regla).
> ⚠️⚠️ **La curva NO es la suya y está medido por qué**: se separan **0,50 px a la talla del hero**,
> y estrenar una quinta curva por medio píxel deshace la tanda 2d. **El rebote de `6d` no vive en la
> curva: vive en los FOTOGRAMAS.** ⚠️ La duración (3,4 s) SÍ es suya y entra como AMBIENTAL
> (`--dur-switch`), porque el techo de la escala son 620 ms y esto no responde a un gesto.
> ⚠️ **Colores por ROL**: su pista es Lima Bote, la nuestra `--ok`. ⚠️ Sin movimiento se congela en
> **ON**, no en OFF. ⚠️ **La copia local del canvas sigue sin el LOTE 6**: `DesignSync` sin
> autorización, `/design-consent` da 403 y el fichero llegó pegado con la codificación rota.
>
> ❗ **`#261` — EL INTERRUPTOR SE VA EN LÍNEA A LA DERECHA, y las dos mitades del CTA por fin miden
> igual.** `[DECIDIDO owner]` con las tres colocaciones dibujadas: el titular pasa a UNA línea
> —«DIVERSIÓN [ON]»— y el interruptor **se queda dentro del `<h1>`**, así que la frase se sigue
> leyendo entera. Su talla la manda **una sola línea** (`font-size: 0.34em`): al ir todo en `em`,
> encoge pista, pomo, aire y rótulo **manteniendo las proporciones** (378×155 → 121×53).
> ⚠️⚠️ **La alineación vertical se MIDIÓ, no se puso a ojo**: un `<span>` en línea se apoya en la
> línea base y ahí una cápsula queda colgando bajo una palabra en versalitas. Con un `Range` sobre
> el nodo de texto, **desfase 16,3 px → −0,1**. Una captura no da ese número.
> ⚠️ **Las dos mitades del CTA no medían igual**: 41×41 con glifo de 30 en comprar y **30×26 con 24**
> en la cuenta — `#260` derivó el chip solo en `.cta-med`. Y la mitad colapsada estaba a **19/27**.
> ⚠️⚠️ **Centrar no se puede con `justify-content`**: el rótulo sigue en el árbol (se desvanece, no
> desaparece, porque el relevo lo anima), así que la línea flex mide más que el botón y centrar
> empuja el icono FUERA. Se centra con el relleno — y **hay que restar los bordes**: con
> `box-sizing: border-box` el fantasma quedaba 2 px descentrado por su borde de 1 px. Medido
> después: **17,4 / 17,5** en las dos mitades y en los dos estados.
>
> ❗ **`#260` — DOS DETALLES DEL HERO, y los dos son el mismo fallo**: un número escrito suelto que
> dejó de tener sentido cuando lo de al lado cambió de tamaño.
> ⚠️ **El interruptor parecía un punto porque el pomo medía el 49 % de su pista** (75 sobre 152). Al
> **77 %** y en claro sobre el verde ya lee como interruptor. ⚠️⚠️ Su relieve va con sombras
> `inset` y **no con un rol de elevación**: el paquete del cliente declara `--shadow-lift: none` y
> `--shadow-float: 5px 5px 0`, así que un rol **borraría** el relieve o **sacaría el pomo fuera de
> la pista**. Un interruptor no flota: se hunde en su ranura.
> ⚠️ **El icono del CTA no crecía con su botón**: en el hero el botón va a 74 px y el glifo se
> quedaba en 22, dentro de un chip fijo de 30×26 — **dos literales**. Ahora salen de
> `--cta-pair-h` con las proporciones que el armazón ya tenía (0,5556 y 0,7333): armazón 30/22, el
> glifo **no se mueve**; hero 41/30. ⚠️ La talla del `<svg>` la manda el CSS: con `:width` en el
> Blade había dos fuentes **y ganaba la del marcado**.
>
> ❗❗ **`#259` — EL CARGADOR ES «TRES BOTES», Y EL CATÁLOGO DEJA DE DEDUCIR SU DIBUJO.**
> `[DECIDIDO owner]`. ▶ **1:1 verificado: 47 de 47 idénticos** byte a byte tras normalizar (43 del
> set + 4 del LOTE 2), comparados con un guion contra el artboard.
> ❗ **El spinner deja de ser la marca de un cliente**: su dibujo era «un punto que salta sobre un
> bloque de espuma, **el mismo vocabulario que su logo**», servido a toda instalación. Ahora son
> tres puntos con los números del artboard (11 · 9 · 7 · 900 ms · desfase 120), en proporción al
> tamaño. ⚠️ **Son TRES piezas y no dos**: los pseudo-elementos son los extremos y **el del medio lo
> pinta el fondo del elemento**, porque hacen falta tres FASES y un `transform` en el elemento
> arrastraría a sus pseudos. ⚠️ Reposo con movimiento reducido **al 40 % de opacidad**, que lo manda
> él.
> ⚠️⚠️ **Y un fallo que solo se vio en una CAPTURA**: el punto del medio salía **29 % más pequeño**.
> En un `radial-gradient` las paradas se miden sobre el RAYO, y por defecto llega a la ESQUINA
> (`0,707 × lado`). Es `closest-side`. *Ninguna medida lo habría dicho: los tres tenían el mismo
> `background-size`.*
> ⚠️⚠️ **`ProductIconSingleSourceTest` miraba una lista de DOS ficheros escrita a mano**, y
> `CatalogStep.vue` —la pantalla más visible del cajón— no estaba: seguía repartiendo el catálogo en
> dos dibujos con `v-if="item.is_pack"`, el patrón que esa guarda existe para prohibir desde `#140`.
> Descubrimiento **automático** ahora, con dos anclas. *Una lista que hay que acordarse de ampliar es
> una lista que envejece.*
> ▶ Para arreglarlo, la clave del marcador **viaja en el contrato**: `CatalogProduct.icon`, resuelto
> por el dominio, publicado en `GET /api/v1/catalog/products` y declarado en `openapi/v1.yaml`.
> ⚠️ **Los valores por defecto cambian** (`[DECIDIDO owner]`): entrada → `ticket`, pack → `gift`.
> Mueve el icono de TODOS los productos que no hayan elegido uno. Las seis ilustraciones siguen
> ofrecidas: se vuelve a la tarta con un clic.
> ⚠️⚠️ **`.icon` NO es un contenedor neutro**: declara `fill: none; stroke-width: 1.6` sobre cada
> `path` — **dibuja a línea**— y habría convertido en un hilo los glifos de masa. Los marcadores
> nuevos van sin ella.
> ▶ **El «área táctil 44» está MEDIDA y no tocada: 115 controles bajo 44 px en móvil** (63 enlaces,
> 25 botones, 1 casilla; solo cinco llevan icono). `[DECIDIDO owner]`: **tanda propia**.
>
> ❗❗ **`#258` — LA AUDITORÍA DEL SET, EJECUTADA ENTERA, y una guarda que llevaba CIEGA.**
> Seis bloques, los seis hechos. El set pasa de **55 a 61** y `DRAWER_OWN` **se queda VACÍA** —los
> cuatro dibujos propios del cajón existían porque no había componente, y ahora lo hay—.
> ⚠️⚠️ **`SidebarIconParityTest` se saltaba tres dibujos y no lo sabía nadie.** Limpiaba los
> comentarios de HTML antes de buscar pero **no los de JavaScript**: el docblock de
> `PasswordInput.vue` cita «dos `<svg>` dentro», el escáner arrancaba en la CITA y **se tragaba el
> icono de en medio** — sin fallar, porque lo tragado lleva `<template>` y la geometría salía vacía.
> ▶ Al arreglarlo aparecieron **tres** ciegos: los dos ojos y **dos flechas del carrito y de pagar**
> que seguían en el idioma anterior. Es `DECISIONES #113` otra vez, dentro del fichero que existe
> para impedirlo.
> ⚠️⚠️ **Y la primera guarda contra esa ceguera NO mordía**: anclar en «este fichero da DOS dibujos»
> falla porque con el escáner descarrilado **también da dos**. Lo que distingue el caso es que **una
> cita es un `<svg>` pelado y todo icono declara su `viewBox`**.
> ❗ **Los cuatro ESTADOS ya son PEGATINA** (§04 del artboard): antes el éxito enseñaba el confeti y
> **el rechazo y la pausa no enseñaban nada**. Círculo, keyline de tinta, sombra dura; el relleno
> entra por los tokens semánticos y la sombra por rol, así que sale con la paleta del cliente **sin
> una línea suya**. Contraste medido: 6,28 · 4,10 · 11,26 · 6,85.
> ⚠️ **El confeti se retira de la confirmación** («la pegatina nunca convive con otra en la misma
> pantalla»); la celebración sigue: `celebrate()` lanza el confeti a pantalla completa.
> ⚠️ **Se dibujan TRES glifos que el artboard no tiene** (`chevron-down`, `eye`, `eye-off`) — medido:
> el mockup **no usa ni un chevron** en 32 dibujos—. No contradice a `#211`: allí lo que no salió fue
> un LOGOTIPO; esto son glifos mecánicos que la anatomía determina casi entera. **Y el disquete de
> «guardar» se retira sin sustituto.**
> ⚠️ **`ProductIcon::CHOICES` pasa de 6 a 11, AÑADIENDO**: retirar una ilustración degradaría en
> silencio todo producto que la tuviera guardada en `ticket_types.icon`.
> ⚠️⚠️ **Y el extractor del artboard emparejó por POSICIÓN**: en las filas con columna «ACTUAL» eso
> desplaza un puesto, y `booking` salió **idéntico a `calendar`**. Se vio porque el resultado era
> sospechosamente igual a otro icono, no porque fallara nada.
> ▶ **Fuera a propósito**: el PANEL (13 `<svg>` + los Heroicons de Filament) es otro idioma y es
> herramienta de operador; y el regalo del widget de ofertas **está animado**.
>
> ❗❗ **`#257` — EL SET DE ICONOS DEL ARTBOARD ENTRA EN EL PRODUCTO: 26 componentes → 55.**
> ⚠️ *(La primera cifra publicada fue 57 y estaba mal: sumaba los 43 del artboard a los 26 de antes
> sin descontar los **16 que SOBRESCRIBEN** un fichero existente. Una suma no es una medida.)*
> `[DECIDIDO owner]` a dos preguntas: el set se parte **por el corte del propio artboard** (los
> genéricos al PRODUCTO, los de parque al paquete del cliente) y en esta tanda se dibujan **los de
> UI**; las 14 zonas, después.
> ⚠️⚠️ **No era «añadir iconos»: era cambiar el IDIOMA DE DIBUJO del producto.** Medido antes:
> nuestros 26 iban **17 de trazo y 9 de masa, sobre SIETE lienzos distintos**; el set del artboard
> es masa sobre rejilla 24, `currentColor` y masa mínima 3.
> ⚠️⚠️ **Y lo que decidió la respuesta**: hoy **no existe** forma de que un cliente sustituya el
> DIBUJO de un icono — `client.css` alcanza al color, la forma y lo que viaja por CSS, pero los
> `<x-icons.*>` llevan el SVG en línea. «Todo al paquete del cliente» habría bloqueado la tanda.
> ⚠️ **La geometría se COPIA con un guion, no se transcribe** (`armazon-y-menu.md` §9.7), y los dos
> que el artboard no dibuja —`arrow-left`, `login`— se derivan **por espejo con `transform`**.
> ❗ **Un nombre de icono es una HIPÓTESIS sobre su dibujo**: se renderizaron los 43 en una hoja de
> contacto antes de cablear, y ahí salieron dos mapeos mal —**`ui/taquilla` es un CANDADO** y
> **`pag/pedido` una BOLSA**—. De regalo, el menú enseñaba `devices` **junto al teléfono**.
> ⚠️⚠️ **`SidebarIconParityTest` hizo su trabajo**: cazó **14 copias del cajón** que se quedaron
> viejas, en 6 ficheros. ▶ **El cajón cambia de aspecto con la landing y eso es el MECANISMO**
> (`landing-white-label.md` §4.5.3): sus iconos pasan de trazo 1.7 a masa y **pesan más a la vista**
> — **necesita el OJO del owner**.
> ⚠️⚠️ **Dos fallos de INSTRUMENTO, y son el mismo**: `width` sin frontera de palabra casa dentro de
> `stroke-width`. Le pasó al extractor **y** a `SidebarDrawerPolishTest`, que **se puso ROJO con el
> producto sano** al llegar el primer icono del set que pinta con trazo. Los dos, con `(?<![-\w])`.
> ⚠️ **La guarda nueva (`IconSetAnatomyTest`) tiene su matiz porque EL ARTBOARD SE SALTA SU REGLA**:
> `ui/check` lleva trazo 1.4 y es masa con un hilo que redondea juntas, no una línea fina. La regla
> se mira **solo donde hay `fill="none"`**, con control explícito. 5 mutaciones, las 5 muerden.
> ▶ Se quedan en el idioma anterior **tres** (`devices`, `cookie`, `chevron-down`): el artboard no
> los dibuja y redibujarlos a ojo es lo de `#211` (29 % de píxeles distintos). `chevron-down` además
> **no lo usa nadie** — ficha en `DEUDA`.
>
> ❗❗ **`#256` — LA DEMO DEL MINIJUEGO YA CORRE EN EL PUNTO ESTÁTICO, y la señal NO era la que
> parecía.** `[DECIDIDO owner]`: «el hero del pie más largo» + «que la animación del juego esté
> activada en su punto estático» son **un solo encargo**: lo segundo necesita sitio, lo primero es
> ese sitio. El mockup ya lo hacía —su bucle corre en modo **demo** siempre que el lienzo se ve, y
> `q > 0,985` allí solo decide si se puede JUGAR—; a nosotros el trozo de 12 kB no se descargaba
> hasta ese umbral, así que en reposo la tira estaba **en blanco**.
> ⚠️⚠️ **El primer intento usó el ANCLAJE y falló justo en las pantallas grandes**: medido, en el
> punto estático la tarjeta está anclada a 1366, 1280 y 390 px pero **no a 1440 ni a 1920** (ahí la
> composición cabe con la sección todavía 20 px por debajo del tope). *El nombre de un umbral no
> demuestra dónde cae.* La señal buena es la del mockup —**que el lienzo SE VEA**— con un
> `IntersectionObserver` de un solo disparo.
> ⚠️ **Los dos umbrales no se tocan**: la VISTA enciende la ANIMACIÓN, `cierre:abierto` la hace
> JUGABLE. Si la vista cambiara la fase, en el punto estático **el espacio dejaría de desplazar la
> página**. Verificado en navegador: sigue desplazando.
> ⚠️ **La reserva de la tira vuelve a ser constante y eso NO contradice a `#253`**: su criterio —«lo
> que solo existe abierto, se reserva abierto»— no cambia; cambia el hecho, porque ahora la tira SÍ
> existe en reposo. Con `prefers-reduced-motion` el hueco baja a 24 px, que es donde el motor nunca
> se carga.
> ⚠️⚠️ **Y el cambio DESTAPÓ un fallo dormido**: el motor **cacheaba el ancho del lienzo**. Montado
> a pantalla completa medía el definitivo; arrancando en el punto estático mide **1176** y luego la
> tarjeta crece — **62 % de estiramiento a 1920**. `ResizeObserver`, no `clientWidth` por fotograma.
> ⚠️ La tarjeta en reposo crece **solo donde el hueco no cabía**: 1366 448→**555**, 1280 483→**548**,
> 390 449→**521**; a 1920 y 1440 **no se mueve** (manda el `min-height` de `#254`). El coste dicho:
> tapa 86 · 44 · 53 px de la parte alta del pie en esas tres.
> ⚠️ **8 mutaciones, las 8 muerden — pero DOS no mordían y era el ARNÉS**: el escape de `\$` en
> comillas dobles de bash llevó una mutación a otra línea. *Cuando una mutación no muerde, la
> primera hipótesis es la mutación.*
>
> ❗❗ **`#254` — EL LOGOTIPO SE SIRVE EN LÍNEA, y eso cambia el modelo de amenaza.** Para poder
> animarlo (`[DECIDIDO owner]`) deja de ir en un `<img>`: **dentro de un `<img>` un SVG es inerte y
> en línea NO**. El fichero lo pone el operador con el paquete del cliente, pero eso es una
> suposición, no una defensa. ▶ **`InlineSvg` es lista blanca y todo o nada**: con un `<script>`, un
> `on*=`, un `javascript:`, un `<foreignObject>` o una referencia externa **no se sirve el logotipo**
> — la plantilla cae a su suelo de texto. `InlineBrandLogoTest`, 13 casos.
> ⚠️ Coste aceptado con el número delante: **~64 KB de marcado (~15 comprimidos) en las doce vistas**.
> ⚠️ Y al incrustarlo aparecieron **dos nombres accesibles anidados** (el SVG trae el suyo): el
> dibujo pasa a `aria-hidden` y el nombre lo pone el envoltorio.
> ⚠️ **La tarjeta del cierre ocupa ahora el hueco que le deja el pie** (`min-height` contra
> `--foot-h`, que publica la coreografía): 757 px a 1920, y la composición cabe con **20 px de
> margen en 8 de 9 ventanas**. Y **el armazón se retira en cuanto la tarjeta se ancla**.
> ⚠️ **El hero se queda con UN CTA**: el par del armazón, debajo del titular y más grande. Los dos
> botones propios de `#253` duraron una tanda — eran una TERCERA pieza de compra en la misma
> pantalla.
> ⚠️⚠️ **Y el interruptor «ON» del titular usa `--ok`, no el rol de acción, porque lo dijo una
> guarda**: `ActionFillTest` rechazó `--action` con su propio argumento. La respuesta ya estaba en
> la misma hoja —el chip de «Abierto ahora» usa `--ok` porque es un ESTADO, no un botón—.
> ▶ **2026-08-29 · carril A: `#243` — CUMPLEAÑOS MIXTO, tanda 1.** El sistema **no tenía ninguna
> conexión** entre un cumple KIDS y uno JUMP (lo preguntó el owner y era cierto: `ticket_types` no
> tiene ninguna columna que agrupe productos). `[DECIDIDO owner]` la conexión es **FAMILIA + TRAMO DE
> EDAD**, que vale para dos regímenes o para cinco.
>
> ❗❗ **SI VAS A TOCAR DINERO EN UNA RESERVA YA PAGADA, LEE ESTO PRIMERO** (`specs/cumple-mixto.md`
> §8.3, medido sobre el pedido real `R-BEEL3E` en transacción revertida): **un `OrderAdjustment` de
> tipo `extra_due` suelto NO COBRA — MUEVE dinero ya pagado.** Añadir 6,00 € deja el valor igual,
> baja «pagado online» a **−6,00 €** y sube «a cobrar en el parque» +6,00. `PAY-16`/`PAY-17` cierran
> **porque** cada `extra_due` que escribe el editor va acompañado de una subida de
> `unit_price`/`quantity`; los dos canales REPARTEN el valor de la línea, no lo amplían. La forma que
> sí cobra es una **LÍNEA** (medido: valor +6,00 · online +0 · puerta +6,00), que es lo que ya hace
> el panel al añadir un complemento.
> ⚠️ **Y el atajo que parecía gratis cobra diez veces de más**: cambiar el producto de la reserva
> (KIDS → JUMP) ya existe —y su frontera es la ZONA— pero cobra la diferencia por TODOS los
> invitados: 30,00 € donde el encargo pide 3,00 €.
> ⚠️ **La línea de diseño**: el veredicto se **DERIVA** siempre (el post-form es editable hasta el
> evento, así que la etiqueta va y viene sola) y el dinero lo **ESCRIBE el operador** — ninguna
> acción del cliente escribe dinero sobre un pedido pagado.
> ⚠️ **Queda declarado fuera** (§11.3): la ACCIÓN de aplicar el suplemento, el **AFORO** —`[DECIDIDO
> owner]`: en el parque real son zonas distintas y el niño mayor pasa a JUMP—, el resto de
> superficies de la etiqueta y la API.
> ⚠️ **Dos lecciones de guarda**: una nació CIEGA (`assertFalse(mixed)` lo cumplen dos mundos, «es de
> KIDS» y «no lo cubre ningún pack») y **el test de render cazó un 500 en el post-form de todo
> cliente** que ningún test de dominio veía.
>
> ▶ **Y `#244`, la TANDA 2: el suplemento se COBRA y se recalcula SOLO** `[DECIDIDO owner]`. No hay
> aprobación del operador — el importe sigue a las edades declaradas, en las dos direcciones.
> ❗❗ **La regla que hay que llevarse: se reconcilia cuando cambia el HECHO** (edades, cantidad,
> producto, fecha de la reserva) **y NUNCA cuando cambia la CONFIGURACIÓN** (precios del catálogo,
> tramos de edad): lo escrito es lo que se le comunicó al cliente, y el desfase **se enseña** en la
> ficha del pedido en vez de aplicarse.
> ▶ **Lo que hizo seguro el modelo automático ya estaba en el código y nadie lo había escrito**: la
> ventana en la que el cliente puede mover el importe se cierra EXACTAMENTE cuando el dinero se da
> por cobrado — las dos cosas cuelgan de `OrderItem::isFinishedInPractice()`.
> ⚠️⚠️ **SI TOCAS ESTO: el producto que lleva el suplemento NO puede ser el pack de destino.**
> Medido: `PackAvailability` cuenta toda fila cuyo producto sea de tipo `pack` en esa zona y día
> **sin mirar si es una línea HIJA**, así que consumiría una fiesta del cupo y sus plazas en silencio
> (`AFORO-01`). Es un COMPLEMENTO —sin zona, `seats=0`, `slot_id=null`— que crea la migración, con
> guarda ROJA en la ficha si falta: un cobro que se apaga sin que nadie se entere era el modo de
> fallo a evitar.
> ⚠️ **Toca `OrderItemEditor`, que está en el `CRITICAL_RE`**: sin reconciliar desde el panel, bajar
> los invitados de 10 a 8 dejaría la línea cobrando por dos niños que ya no están. Va POST-COMMIT y
> fuera del lock de zona/día.
> ⚠️ Lo que NO se cierra y es de la casa: el cliente puede bajar la edad la víspera. `[owner]`: «puede
> mentir con este sistema o sin él, eso es trabajo en persona». Queda TRAZADO en `audit_logs` y la
> hoja de sala ya imprime la edad declarada.
>
> ⚠️⚠️ **La verificación cazó DOS fallos, y el segundo no era mío**: (1) la migración pedía
> `max(position) + 1`, que en una base VACÍA vale **1** — y `LandingContentSeeder` identifica sus
> productos por **`position`**, así que sobrescribía el portador con «Jump · 1 hora» y la instalación
> se quedaba sin él en silencio (posición fija **0** desde ahora: 900 tampoco valía, porque
> `CreateCatalog` usa `max(position) + 1` y empujaba a 901 todo producto nuevo); (2)
> `validateAddonEdits` accedía a
> `$newPivots[$id]?->…` y **`?->` no protege de una clave AUSENTE**: editar un pedido con un
> complemento **despublicado después de venderse** moría con «Undefined array key». Preexistente,
> arreglado en los tres accesos y con guarda propia.
> ❗ **Verificador nuevo: `mixed-party:verify-concurrency`** (`TESTING.md`), hermano de
> `purchase:verify-oversell`. **Visto fallar** sin el lock: **12 líneas y 84,00 €** donde debía haber
> 7,00 €. No entra en el `CRITICAL_RE` a propósito — ese gate impone dos comandos que no ejercitan
> el post-form.
>
> ▶ **Y `#245`, la TANDA 3: la etiqueta va PEGADA AL NOMBRE** (idea del owner). Un solo compositor,
> `OrderItem::displayProductName()`, y la cogen de ahí la hoja de sala, el resumen del día, la
> puerta, el calendario, los correos, «Mis pedidos», «Mis reservas» y `OrderItemResource`.
> ⚠️ **El sitio único es la RESERVA, no el producto**: `TicketType` lo comparten todas las fiestas y
> no puede saber si ESTA es mixta. Es la misma doctrina que `displayTimeWindow()`.
> ⚠️ La ficha del panel sigue con el nombre CRUDO porque pinta la pastilla aparte, y el dato suelto
> (`isMixedParty()`) existe para quien pueda darle estilo o **filtrar** — una etiqueta metida solo
> dentro de la cadena deja de ser un dato.
> ⚠️⚠️ **El precio de que todos lo cojan de ahí**: `isMixedParty()` necesita `ticketType` y `slot`
> cargadas, o son **dos consultas por fila** y quien pinte la lista no se entera. Lo fija
> `MixedPartyLabelSurfacesTest` comparando el nº de consultas con 2 y con 6 reservas — y quitarle el
> `slot` al resumen del día lo pone en rojo.
> ⚠️ Un caso de ese fichero nació CIEGO (una rama de escape «si la ruta no da 200…» lo hacía pasar
> sin mirar el calendario). *Una rama alternativa dentro de un test es una forma de no probar nada.*
>
> ▶ **Y `#246`, lo que el owner encontró PROBÁNDOLO en un pedido real**: reservó el pack CARO y dos
> invitados corresponden al barato, así que no hay cargo — y **el aviso no salía**, porque leía solo
> lo escrito. El cliente veía la etiqueta «MIXTA» y ninguna línea que la explicara.
> ▶ Ahora el aviso sale **siempre que la fiesta sea mixta**, con la aritmética («2 invitados
> corresponden a Kids, 11,00 € por invitado, en vez de Jump, 15,00 €»), y en la dirección barata
> avisa de que saldría X € más barata `[DECIDIDO owner]`: **se avisa, no se descuenta**.
> ⚠️⚠️ **Esa cifra NO va por el desglose de dinero**: «Pendiente de devolución» es deuda REAL del
> parque y `PAY-16`/`PAY-17` cuadran sobre ella. Un informativo ahí lo descuadra y el cliente lee una
> deuda que no existe. El veredicto publica ahora DOS cifras: la que se cobra (suelo en 0) y la que
> costaría menos (informativa).
> ⚠️ No se descuenta solo porque crearía **un incentivo para mentir a la baja** que hoy no existe.
> ⚠️ De paso apareció una **cita rota en `DECISIONES`**: un «`#246`» que no existía y que en realidad
> era `#147`. *Un número de tres cifras mal tecleado apunta a otra década del proyecto y nadie lo
> nota* — lo destapó ir a usar ese número.
>
> ▶ **Y `#247`: el régimen en el recuadro de cada niño** (encargo del owner) más dos correcciones.
> La pastilla sale del MISMO recorrido que el veredicto —`guestRegimes()` y `for()` comparten
> `walk()`—, porque una copia de la regla haría que una ficha dijera «Kids» y el total otra cosa.
> ⚠️ **Dice el PACK y no la ZONA** aunque el encargo hablara de zona: en la demo los dos packs de
> cumpleaños comparten `zone_id = 4` y el nombre de la zona no distinguiría nada.
> ⚠️⚠️ **Y la línea del desglose de puerta decía «Suplemento por 1 invitad_os_»** —sin `trans_choice`,
> en un desglose de DINERO— **y no nombraba el pack**. Corregido: el nombre se GUARDA en el `context`
> del cargo, no se resuelve al leer. ▶ El desglose es **uno solo** (`Order::pendingAtGateLines()`) y
> lo leen cliente (ledger de la API), operador (partial `reservation-financials`) y hoja de sala.
>
> ⏸️ **Y `#248`: el DESCUENTO del caso barato queda APARCADO** `[DECIDIDO owner]` — se queda el aviso
> a operador y cliente, **sin tocar las invariantes del dinero**; se retoma en producción según las
> circunstancias. ▶ **Su diseño está escrito y verificado** (`specs/cumple-mixto.md` §16): las cinco
> formas obvias que NO valen, la aritmética de los tres casos y la cota de que ningún canal queda
> negativo. ⚠️⚠️ **Y un supuesto propio que se midió y era FALSO**: el respaldo de
> `itemOriginalOnlineCents()` devuelve lo cobrado AHORA, así que un pack SIN SEÑAL habría perdido el
> descuento en silencio. Si algún día se implementa, se empieza por ahí.
>
> ❗ **POR DÓNDE SIGUE EL CARRIL A** (cumpleaños mixto), en orden:
>   1. **El OJO del owner** sobre lo último: el rótulo del régimen en el recuadro de cada niño, el
>      aviso del caso barato en el post-form y la línea del desglose con el nombre del pack.
>      ⚠️ **La instalación local ya está configurada** —familia `cumple`, tramos 1–6 y 7–99, campo de
>      edad en los dos packs y precios 11,00/15,00 €—: eso es config de la demo, **no del producto**.
>      Si se siembra de cero, hay que volver a ponerlo.
>   2. **La pastilla en el cajón** — cosmética: el cliente ya lee la etiqueta dentro del nombre, falta
>      darle estilo propio. Ojo al techo del payload de montaje (83 B de holgura sobre 9.100).
>   3. **El AFORO** — aparcado por el owner. En el parque real KIDS y JUMP son zonas distintas y el
>      niño mayor pasa a JUMP: una fiesta mixta reparte críos entre dos pools que hoy nadie cuenta.
>      Toca `AFORO-01`/`AFORO-05`: **spec propia, no se empieza desde la de mixto**.
>   4. **El descuento del caso barato** (`#248`) — aparcado con su diseño verificado en §16.
> ⚠️ **Rango de numeración**: el carril A ha consumido hasta `#248`; le quedan `#249` y hay que
> reservar rango nuevo antes de la siguiente tanda (el carril C sigue por `#250`+).
>
> ❗❗ **`#253` — EL CAJÓN SE ABRÍA DETRÁS DEL JUEGO, y con el scroll ya bloqueado.** En un teléfono,
> al pulsar «Reservar» tras la partida del cierre: el cajón se abría de verdad —y su cerrojo con
> él— pero la tarjeta estaba en `z-index: 210` y el cajón en 160. Un superpuesto invisible que
> además congela la página. ▶ La tarjeta baja a **88**: es PÁGINA, no un superpuesto. **88 y no 90
> para no empatar con `.book-bar`** — un empate lo decide el orden del documento.
> ⚠️⚠️ **Si tocas el scroll o una capa, la escala por rol está en `LayerOrderTest`**, y ahora
> también vigila esta tarjeta.
> ⚠️ **El punto estático del cierre no cabía**: necesitaba 921 px y solo entraba por encima de
> ~1500 de ventana. No era el imán, era física. Cabe en **8 de 9 ventanas** tras quitar el selector
> de idioma del pie y dejar de reservar en reposo una tira de juego **que en reposo está vacía**.
> ⚠️ **El armazón nunca se retiró al bajar en la portada**: la lógica era correcta y **la regla del
> hero pisaba a la de `landing.css`**. Ahora la dirección es un TERCER FACTOR del mismo producto.
> ⚠️⚠️ **`.lang-dd--up` llevaba CINCO DÍAS sin existir**: `#205` la retiró, `#233` volvió a pedirla
> y nadie la restauró, así que el prop `:up` no hacía nada. Guarda nueva y general: **ningún
> modificador que un componente emite puede quedarse sin regla**. ❗ **Nació ciega TRES veces**
> (mutación incompleta · el nombre vivo en un comentario · el modificador es la CLAVE del array).
> ⚠️ **La sombra del logotipo**: nuestro CSS era **idéntico** al del mockup. Lo que cambia es el
> sujeto — su lockup son glifos, el nuestro un SVG con el relieve horneado. *Copiar un filtro no es
> copiar un resultado si el sujeto es otro.* No hay nada que pedirle al diseñador.
> ❗ **Lo único pendiente del owner: la animación del logotipo** (el relevo de la Y). No se puede con
> un `<img>`; el asset tiene las piezas (`id="fig"`), así que la salida es **servirlo en línea** —
> ~64 KB de marcado (~15 comprimidos) en las doce vistas. Es coste real y la decisión es suya.
>
> ❗❗ **`#252` — LA PORTADA TIENE DOS PUNTOS ESTÁTICOS Y AHORA EL SCROLL ENCAJA EN ELLOS.**
> `[DECIDIDO owner]`. Arriba, el hero encogido con el racimo colocado; abajo, la tarjeta del cierre
> en reposo con **el pie entero visible**. Al parar el scroll dentro de sus recorridos, la página se
> coloca en el estado más cercano en la dirección del gesto. ▶ **No es el `freno` del mockup**: el
> suyo bajando por el cierre te lleva a pantalla completa, y aquí bajando te **retiene en el punto
> estático** hasta que insistes (45 % del recorrido). Vive en `ui/scroll-magnet.js`, mitad pura y
> mitad DOM, con **19 casos** de `node --test`.
> ⚠️⚠️ **Si tocas cualquier detector de dirección de scroll, lee esto**: el rumbo NO se puede sacar
> del último evento. La portada **crece 34 px al llegar al final** —55 imágenes, 51 perezosas,
> **ninguna declara proporción**— y el anclaje de scroll compensa: eso llega como un evento hacia
> abajo. Pasó los 16 casos unitarios y falló en el navegador. **El rumbo es el movimiento NETO desde
> la última parada.** Ficha del CLS en `DEUDA.md`.
> ⚠️ **Y el hueco que el hero deja al racimo ya no se estima: se CALCULA.** Iba en `vh` y el racimo
> no cambia con la altura de ventana — a 900 px de alto el logotipo se metía 5 px dentro del hero.
> Ahora sale de `--nav-pad-block + max(--nav-logo-h, --nav-btn-h) + --hero-top-air`: **12 px de aire
> exactos en once ventanas**. De paso apareció que la hamburguesa leía un token **fuera de su
> alcance** (`--cta-pair-h`, declarado dentro de `.cta-pair`) y **nunca bajaba a 48 en teléfono**.
> ⚠️ **La marquesina de palabras de la portada está RETIRADA, no apagada** (componente, CSS,
> `@keyframes`, claves de idioma y `.jj-block--xl`). Siguen la de `/servicios` y la de la galería.
>
> ❗❗ **RANGO DE NUMERACIÓN RESERVADO POR CARRIL, para no repetir las TRES colisiones del día 28**:
> el **carril A** toma **`#239`–`#249`** y el **carril C** sigue por **`#250`** en adelante. Mirar el
> remoto al ELEGIR número **no basta** —hay que volver a mirarlo al PUBLICAR—, y una renumeración se
> hace siempre sobre la lista de ficheros del propio diff (`git status`), nunca con un `grep` del
> árbol: un `sed` global llegó a corromper cinco referencias del otro carril.
>
> ❗❗ **`#251` — LA TRANSICIÓN DEL CIERRE NO ERA IDÉNTICA, y el desvío estaba donde nadie miraba.**
> El crecimiento de la tarjeta SÍ lo era (17 posiciones × 2 anchos contra la fórmula del artboard:
> ancho 0,0 px, alto ≤ 0,5). Lo que no: **la retirada del armazón**, por dos causas encadenadas —la
> curva era LINEAL y el mockup la hace CÚBICA, y además leía el progreso EQUIVOCADO—.
> ▶ **La coreografía publica DOS progresos y no son intercambiables**: `--cierre-q` (CRUDO) manda la
> retirada, el umbral de «ya llena» y el arranque del minijuego; `--cierre-p` (SUAVIZADO) manda la
> geometría. En el mockup **solo el suavizado entra en los `lerp`**. Medido al 7 % del crecimiento:
> su armazón valía **0,19 de opacidad y el nuestro 0,82**. ⚠️ *Dos progresos con nombres parecidos
> son dos progresos que alguien intercambiará* — lo vigila `CierreChoreographyTest` (6 mutaciones).
> ⚠️ **Y una guarda PROPIA se puso roja con el producto sano** por aseverar el NOMBRE de un token:
> re-apuntada a resolver la cadena de `var()`. *Aseverar el texto literal ata la guarda a una
> implementación.*
>
> ❗❗ **`#250` — LA COLUMNA DEL SITIO ES AHORA LA DEL MOCKUP: 1176 px, no 1380.** `[DECIDIDO
> owner]`. Afecta a las **doce vistas** y a cualquiera que escriba CSS, así que va delante.
> ▶ **El ancho se escribe UNA vez, en `--col-max`**, y de ahí salen las tres formas en las que el
> sitio expresa la misma columna: `width` (`.wrap`) · SANGRADO (`--wrap-gutter`, para lo que va a
> sangre completa) · caja EXTERIOR (`--hero-w-end`, `.reserve`, `.menu__inner`). Lo vigila
> `ColumnIsDeclaredOnceTest`, con sus 6 mutaciones. **No escribas un ancho de columna a mano.**
> ⚠️ **La landing no se ensancha: se ESTRECHA.** Y había una prueba interna de que la columna buena
> era la del mockup: el hero de cabecera ya acababa en 1240 (su número) mientras las secciones iban
> a 1380 — **dos columnas contradictorias que nadie había decidido**.
> ⚠️ **Y el reposo del hero del cierre está medido contra el artboard: 27 de 28 dimensiones
> idénticas.** La única real es el canto de sus CTA (10 contra 14) y es una **contradicción del
> cliente consigo mismo** —14 no está en su escala— : ficha en `DEUDA.md`, se arregla en su
> `client.css` si él quiere.
> ⚠️⚠️ **Dos de las cuatro divergencias que dio el comparador NO eran del código**: el tag va girado
> −7° y `getBoundingClientRect()` mide la envolvente del giro, y el canto y la sombra ya los ponía
> `client.css`. *Comprobarlo ahorró dos cambios equivocados.*
>
> ❗❗ **`#238` — LA TARJETA DEL CIERRE MEDÍA 160 px A 2560, Y EL MENÚ 60. Afecta a cualquiera que
> escriba CSS**, así que va delante de todo lo demás. `--wrap-gutter` vale
> `max(40px, calc((100% - 1380px) / 2))` y ese `100%` es un **porcentaje: mide el CONTENEDOR, no el
> elemento**. En algo a sangre completa (el `.nav`, el escenario del hero) significa lo que parece;
> **en un elemento que ya tiene su propio `max-width` el sangrado crece con la ventana mientras la
> caja no puede** y la columna se estrangula. Dos víctimas: la tarjeta del hero del cierre —el fallo
> que vio el owner— y **`.menu__inner`, que llevaba así desde `#201` sin que lo viera nadie**.
> ▶ **Un contenedor acotado se sangra con `--col-gutter`** (longitud fija: 32 · 24 bajo 1100 · 16
> bajo 720). `--wrap-gutter` sigue siendo correcto donde está. Lo vigila
> `CappedContainerGutterTest`, con sus 4 mutaciones.
> ⚠️⚠️ **Y la lección de método, que es la CUARTA vez que se paga en este carril: no basta con
> medir; hay que medir DONDE el fallo puede aparecer.** Todas las sondas de armazón y de tema
> corrieron a **1280 y 390**, que son justo los dos anchos donde este defecto no existe.
>
> ❗❗ **ATENCIÓN: desde el 2026-08-27 hay TRES CARRILES sobre `main` (no dos).** El B cerró ese día a
> las 07:30 con todo empujado y verde; el A cerró a las 18:40 con las TANDAS 1, 2 y 3 de «menores a
> cargo» empujadas (`#191` · `#198` · `#199`) y **su siguiente sesión hace la TANDA 4, la asignación en
> el embudo** (`[DECIDIDO owner]`; el mapa de arranque está en su fila); y **nace el carril C, el
> TEMA**, que cerró y empujó su tanda 1 esa misma tarde (`#192`,
> `specs/tema-por-instalacion.md`). Los tres tienen su fila abajo. Antes de planificar nada,
> `git fetch`. El reparto vigente es el bloque de aquí abajo — **es el único**: hasta el
> 2026-08-26 había también un resumen en esta cabecera que se quedó atrás y **contradecía al de
> abajo** (decía que el agente A estaba en panel/dinero cuando lleva dos días en el waiver). Se
> retiró: dos repartos son un reparto que no se puede creer.
>
> ❗❗ **EL CARRIL C VUELVE A ABRIR (2026-08-28, tarde): el mockup 1:1** (`#225` → `#230`, seis
> cortes). `[DECIDIDO owner]`: «lo quiero idéntico 1:1 — hero, menú, transiciones, animaciones, y lo
> mismo en el footer y el hero del footer». **Ninguno toca el panel, el cajón, el dominio ni sus
> tests**: armazón, hero, menú, cierre y el composer compartido.
>
> ⚠️⚠️ **DOS HALLAZGOS QUE AFECTAN A TODO EL MUNDO, no solo al tema:**
> 1. **`html, body { overflow-x: hidden }` rompía TODOS los `position: sticky` de la web** (`#226`).
>    `hidden` convierte al elemento en contenedor de scroll. Medido: el hero de la portada **nunca se
>    pegó** y dejaba **569 px de banda vacía** antes del contenido, desde `#195`. Arreglado con
>    `overflow-x: clip` y guardado por `StickySurvivesTheRootOverflowTest`.
> 2. **Un selector más ancho que su intención** (`#227`): once reglas decían
>    `[data-surface="ink"] .cta-med` cuando su propio comentario decía «dentro del MENÚ». No se
>    notaba porque el único `.cta-med` en tinta era el del nav; al llegar un segundo caso, el botón
>    de comprar salió **amarillo aviso** sin que nadie lo decidiera.
>
> ⚠️ **Y una colisión de numeración entre agentes**: se eligieron `#223`–`#227` mirando el remoto
> (que estaba en `#222`), y el carril A publicó `#223`/`#224` mientras tanto. Renumerados a
> `#225`–`#229` **antes** de fusionar — después del merge, una sustitución global habría corrompido
> las entradas del otro carril. **Mirar el remoto al elegir número no basta: hay que volver a
> mirarlo al publicar.**
>
> ⚠️⚠️ **El OJO del owner corrigió el cierre DOS veces** (`#233` y `#235`), y las dos por la misma
> causa: se reconstruyó el bloque **de memoria** en vez de sacarlo entero del artboard. En `#235` se
> copió el `<section id="reservar">` completo —6.981 bytes— y aparecieron el tag de la ciudad, el
> rol de acción en el CTA, el desvanecido de los CTA al jugar y que el ESPACIO arranca la partida.
> ▶ **Si el encargo es «1:1», leer el bloque entero es más barato que reconstruirlo dos veces.**
>
> ⚠️⚠️ **El OJO del owner corrigió TRES cosas del cierre que ningún test veía** (`#233`): el zoom
> del juego iba **2,3× más lejos** (la altura del lienzo ES el zoom, y el mockup la cambia con la
> fase), la transición usaba un **pegajoso** cuando el mockup fija la tarjeta y la hace crecer
> **mientras el pie pasa por detrás** —`#229` eligió el mecanismo por limpieza y cambió el gesto—,
> y el pie había perdido el selector de idioma. ▶ **La limpieza arquitectónica es un criterio para
> elegir entre implementaciones que dan el MISMO resultado; cuando cambia el resultado, deja de
> serlo.**
>
> ✅ **El minijuego está HECHO** (`#231`): «Salta la ciudad», corredor infinito sobre las almenas,
> con su física, su generación procedural y su récord. **Se carga en un trozo aparte** (12 kB) para
> no cobrárselo a quien entra en la portada; el techo de peso de la landing sube de 20 a 22 kB, y el
> trozo del juego estrena el suyo. Verificado en navegador: 42 m jugados, cero errores de JS.
>
> ❗ **Lo que queda del encargo del owner**: los **iconos** del canvas (47 UI + 3 cargadores + 14
> zonas, con la decisión previa de si entran en el PRODUCTO o en el paquete de tema), el contenido
> real del cliente y la subida a staging. Y el **OJO del owner** sobre todo lo de esta tanda: un
> navegador headless mide, no valida (`CONVENCIONES §3.bis`).

> ❗❗ **AVISO AL CARRIL A (2026-08-28, 15:30) — el carril C cerró y tocó CSS a lo ancho.**
> Ocho cortes empujados (`46e5f93` → `a300aba`). **Ninguno toca el panel, el cajón
> (`resources/js/sidebar/**`), el dominio ni tus tests**: son el armazón, el hero, la marca y el
> tema. ⚠️ **Pero la tanda 2d hizo 570 sustituciones en `public/css/site.css` y `landing.css`**, así
> que una rama vieja sobre esas dos hojas dará conflictos grandes: `git pull --rebase` **antes** de
> tocarlas. Ficheros del carril C en esta sesión: `public/css/{landing,site,spinner}.css` ·
> `resources/js/app.js` · `resources/js/ui/nav-choreography.js` ·
> `resources/views/components/site/{nav,brand,favicon}.blade.php` · `resources/views/home.blade.php`
> · `lang/*/landing.php` · `.gitignore` · `scripts/deploy.sh` · y sus guardas en
> `tests/Feature/{Site,Architecture,Theme}/**`, `HomePageTest`, `PublicPagesTest`, `SeoTest`.
> ▶ **`ShapeScaleTest` cambió** (entra un cuarto rol de sombra) y **`MotionScaleTest` es nuevo**:
> si añades CSS, las dos te pedirán tokens en vez de literales.
>
> ❗❗ **REPARTO VIGENTE — LÉELO ANTES DE ELEGIR TAREA.** (reescrito el 2026-08-26 por la tarde, por
> indicación del owner: los dos carriles cambian de trabajo, no de máquina)
> · **Agente A (la máquina de los 24 + 9 pedidos, la del waiver) → SESIÓN EN CURSO desde el 2026-08-27
>   a las 22:20 (hora de Madrid; ⚠️ el contenedor va en UTC, 2 h menos): el PANEL de menores (D14) y
>   después el SUBSISTEMA A (carné QR + puerta).** `[DECIDIDO owner]` **`#208`**: primero el panel,
>   carné de 20 caracteres, spec de la puerta APROBADA. ✅ **EL PANEL ESTÁ EN EL ÁRBOL** (tanda 5,
>   `specs/menores-a-cargo.md` **§9.10.4**, `8ab0f5c`: +45 tests, 4/4 mutaciones, sonda de concurrencia
>   con y sin lock, headless 13/13 con capturas); queda el OJO del owner. ✅ **Y LA PUERTA TAMBIÉN
>   (subsistema A, `specs/identidad-qr-puerta.md` §9.4, 2026-08-28 madrugada)**: el carné en
>   `customer_cards` dentro de `revokeAllAccess()`, la visita en `customer_visits`, la ficha compuesta
>   por `GateProfile` (23 consultas constantes, sin campo para el nombre de un menor), la pantalla con el
>   carné por el MISMO input + dos limitadores + caducidad EN SERVIDOR + «Registrar visita», el PNG en el
>   correo y `GET|POST /me/card`. **5/5 mutaciones · headless 15/15 con capturas.** Queda el OJO del
>   owner (pantalla, correo en Gmail/Outlook, **lector real**). ⚠️ `docs-check` de aquel día: **36
>   modelos y 85 migraciones** (cifra HISTÓRICA; se escribe con «y» y no con «·» a propósito, porque
>   `docs-check` verifica toda aparición de «N modelos ·» contra el árbol de HOY y una nota del pasado
>   lo pondría rojo sin que nada esté mal). **SESIÓN CERRADA el 2026-08-28 a las 06:20 (hora de Madrid; el contenedor va en UTC,
>   2 h menos; el código se cerró a las 00:40 y el último corte es solo doc)** con todo empujado y el
>   gate en verde: suite **3213 / 20.935** · Pint ✓ · docs-check ✓ · build ✓ ·
>   `audit-clock` NO corrido a propósito (los fixtures nuevos van con `travelTo` fijo o con las
>   mismas fechas relativas que sus vecinos; ninguno afirma una edad o un día concreto sin fijar el
>   reloj). ▶ ✅ **SESIÓN del 2026-08-28 por la mañana (06:23 → 07:50, hora de Madrid; carril A;
>   `DECISIONES #210`) — el OJO del owner en localhost, ANTES de retomar la lista de abajo.** Reportó
>   «pulso iniciar sesión y no sale nada» y «el carrito no tiene CTA para volver». Medido y arreglado:
>   **(1)** el «no» del login (401 y 429) era **INVISIBLE en el ÁREA DE CLIENTE desde el 2026-08-23**:
>   `const auth = useAuthStore()` sombreaba la prop `auth` en `AccountSection.vue` (en `<script setup>`
>   la constante gana en la plantilla y Vue no avisa; ninguna guarda podía verlo) → `authStore`, y
>   `AccountPanel.vue` con la misma trampa benigna → `accountStore`. ❗ **El owner sigue sin poder entrar
>   porque su contraseña NO casa** (a las 06:12 el log tiene 5 `auth.login_failed` + 8 `lockout`; la
>   cuenta la creó él por la web el 26, sin verificar): ahora el cajón **se lo dice**; recuperar por
>   «¿Olvidaste tu contraseña?» → Mailpit `:8028`, y `/mi-cuenta` le pedirá verificar el correo.
>   **(2)** el carrito tiene **«Volver»** (`bk-back`, al catálogo con la cesta intacta, también con la
>   cesta vacía; 3 claves del manifiesto +3 nodos; chunk 242,64 / techo 243). **(3)** De regalo: el
>   `watch` de menores de `PurchaseSection.vue` seguía **ENCIMA de su `const`** (un TDZ que la spec
>   §9.9.8·4 daba por arreglado — `ReferenceError` en cada montaje y un `GET /me/dependents → 401` por
>   visitante anónimo) → debajo, con la corrección DELANTE del texto en la spec. Guarda nueva
>   **`SidebarSetupBindingsTest`** (props sombreadas —también por `import`— + `watch` antes de su
>   `const`; **3/3 mutaciones muerden**; endurecida en la misma sesión por una revisión adversarial de
>   19 agentes con 15 hallazgos confirmados, todos aplicados), headless **9/9 + 9/9**
>   (`VERIFICACION-E2E-CAJON.md` **§5.terdecies**), spec
>   `auth-en-cajon.md` **§8.ter**, `AccountDoorWiringTest` re-apuntado al nombre nuevo. Cierre con el
>   gate en verde: suite **3216 / 20.942** · JS 773 · Pint ✓ · docs-check ✓ · build ✓.
>   `[PENDIENTE: owner]`: si el área de cliente gana casos de contrato de árbol (hoy cero) o ESLint
>   entra en el gate (`DEUDA.md`). ⚠️ Para el carril C: el `.form__error` del cajón se computa en
>   TINTA, no en `--err` (legible; lo decide el tema). ▶ ✅ **Y EN LA MISMA SESIÓN (07:20 → 07:50), LA
>   TANDA DE LAS DOS SUPERFICIES DEL CARNÉ (`DECISIONES #212`, spec `identidad-qr-puerta.md` §9.6)**,
>   elegida por el owner a pregunta simple: **(1)** `GET /me/card/png` —los MISMOS bytes que el adjunto
>   del correo; el QR lo dibuja el SERVIDOR porque el chunk estaba a 0,36 KiB del techo— y `png_url` en
>   el contrato · **(2)** la zona **«Mi carné»** del cajón (tercera del índice, icono `qr` nuevo en el
>   sistema de diseño; imagen con `?v=issued_at`, token en grupos de 4 para dictarlo, «Descargar (PNG)»,
>   «Renovar carné» con confirmación) · **(3)** **«Rotar carné QR»** en `ViewUser` con el patrón de
>   defensa de la ficha (el `cards.rotated` lleva al OPERADOR de actor). Y dos respuestas más del owner:
>   **el panel NO declara menores** (`[DECIDIDO]`, cierra el `[PENDIENTE]` de §9.5) y **JumpPoints
>   espera su repaso**: el resumen de una página está en `specs/lealtad-jumppoints.md` **§9** y NO se
>   diseña la ejecución hasta su ✅. Medido: +8 tests PHP (`MeCardTest` 7, `RotateCardActionTest` 6) ·
>   JS 773 → 790 · headless **14/14** (`VERIFICACION-E2E-CAJON.md` §5.quindecies) · chunk 246,29
>   (techo 243 → 247, por feature) · payload con sesión 8.472 (techo 7.800 → 8.550, por feature) ·
>   suite **3224 / 21.012** sobre el árbol del carril A y **3230 / 21.049 sobre el árbol CONJUNTO** tras
>   rebasar sobre el `#211` del carril C (empujado a las 07:18 mientras corría el gate; tres conflictos de
>   «ambos añaden al final», resueltos con los dos bloques). ✅ **DESPLEGADO EN STAGING a las 07:53**
>   (`577cf4f`, `ENTORNOS.md` §4: volcado previo de la BD, 4 migraciones, salud 7/7, rutas del carné en 401
>   JSON, chunk idéntico al local). ▶ ✅ **Y a las 08:05, `#215`**: el owner vio que «Ir al carrito» del
>   pie **no hacía nada** — era mudo desde 4.3·2: la máquina no tenía la arista `CATALOG → CART` y `go()`
>   rechaza en silencio. Arista + caso + **guarda nueva en `foot.test.js`** (todo CTA del pie tiene que ser
>   una transición que la máquina admita; la mutación da 2 rojos), sondeo 4/4, JS 790 → 792. Redesplegado.
>   ▶ ✅ **Y EL PULIDO DE LOS OCHO PUNTOS DEL OWNER (`DECISIONES #217`, specs `identidad-qr-puerta.md`
>   §9.7/§9.7.1 y `menores-a-cargo.md` §9.11)**, tras su prueba en staging. **Medido antes de diseñar**
>   (seis lectores), construido por **cuatro implementadores en paralelo con ficheros disjuntos**, y
>   revisado después. Lo que entró: el **icono de la instalación dentro del QR** con su margen (7 módulos
>   tapados, el único valor con dos escalones antes del precipicio; verificado con **dos decodificadores**
>   a dos tamaños) · **«Mi cuenta» en tarjetas** de 2 columnas · **«Mi QR» junto al nombre** (y fuera de
>   ahí la próxima reserva) · el **QR presentado como credencial** con aviso permanente y **confirmación
>   dentro del cajón** al renovar · **«Menores a cargo»** con alta desplegable y **paginación de 6 que
>   solo aparece si hace falta** (tope real medido: 20 por defecto, 100 configurable) · el **bloque de
>   asignar menores** rediseñado, con la fila no marcable **visiblemente** deshabilitada y su motivo
>   aparte · los **menores en la ficha del cliente del panel** · la **pantalla de puerta** entera · y la
>   palabra **«QR»** en cliente, correo y panel. ⚠️⚠️ **Dos hallazgos que nadie buscaba**: la casilla del
>   menor **no estaba rota** (staging en modo interno + exención del menor sin firmar, `#202`·2) y la
>   **paleta de la puerta SÍ lo estaba** (los grises y el color de marca computaban vacío: texto negro
>   puro, borde negro sólido, y las 99 variantes de modo oscuro inertes). **64 mutaciones**, suite
>   **3299 / 21.542**, JS **813**, chunk 250,67/251, sondeos 38 ✓ y 15/15. Guion para el ojo del owner:
>   `VERIFICACION-E2E-CAJON.md` **§5.octodecies**. ⚠️ **Tres decisiones del agente reversibles y baratas**:
>   el modo oscuro de la puerta (8 líneas), 6 menores por página (una constante) y el margen del icono
>   comido de dentro (otra). ▶ **Lo siguiente**: el OJO del owner sobre §5.octodecies —y en especial el
>   **lector real** con el PNG descargado— y después **JumpPoints**, cuyo resumen de una página sigue
>   esperando su ✅ en `lealtad-jumppoints.md` §9.
>   ▶ ✅ **Y la REVISIÓN ADVERSARIAL del pulido, con sus arreglos** (`#217` addendum): 23 hallazgos
>   confirmados, **9 arreglados** (los de conducta y seguridad) y 14 en `DEUDA.md` con su reproducción.
>   Los cuatro que valieron la revisión: **el cuerpo del semáforo de la puerta no se pintaba** (el
>   componente no imprime su slot por defecto: el empleado veía un icono y ni una palabra), **«Mi QR»
>   salía mudo** para quien gana la sesión sin recargar —el camino normal de una primera compra—, el
>   **margen del QR estaba medido con un token que no es un carné** (re-medido con 40 reales: 7 módulos
>   0 fallos, 9 módulos 10 de 40; la conducta enviada era la correcta, el número escrito no), y **la
>   guarda del icono transparente pasaba en verde con el cuadrado negro puesto**. Más: el QR del titular
>   anterior se quedaba en pantalla al cambiar de cuenta, el foco saltaba al `<body>` al cerrar dos
>   formularios, el motivo de la casilla deshabilitada dejó de anunciarse a un lector de pantalla, y la
>   puerta decidía a quién acreditar la visita leyendo estado que el navegador puede reescribir
>   (`#[Locked]`). Suite **3303 / 21.554** · JS 813 · chunk 251,02 (techo 252). ⚠️ **DECISIÓN DEL OWNER
>   SOBRE EL MÉTODO (2026-08-28)**: la revisión costó ~2,8 M de tokens de agentes y **se paró ahí**; los
>   arreglos los escribió el orquestador a mano. ▶ **De aquí en adelante: sin subagentes por defecto**,
>   tests acotados con `--filter` y la suite entera UNA vez antes de subir; si una tarea justifica
>   paralelizar, se propone con su coste y decide el owner.
>   ▶▶ **SESIÓN CERRADA el 2026-08-28 a las 15:50 (hora de Madrid)** con todo empujado (`8d27187`),
>   el gate en verde (suite **3315 / 21.713** · JS 813 · Pint ✓ · docs-check ✓ · build ✓) y
>   **desplegado en staging**. `audit-clock` NO corrido: esta tanda no añadió ni tocó fixtures con
>   calendario (comprobado sobre el diff del commit).
>   ▶ ❗ **POR DÓNDE RETOMA LA SIGUIENTE SESIÓN DE ESTE CARRIL** (`[DECIDIDO owner, 2026-08-28]`):
>   **otros puntos sobre el PANEL DE ADMIN**, que el owner dirá al arrancar. Antes de tocar nada:
>   `/arranque-sesion`, y para el panel la fila de `CLAUDE.md` «Panel admin / puerta / operación
>   diaria» (`docs/PANEL-ADMIN.md`) — y si toca la ficha del cliente o los pedidos,
>   `specs/desmontar-view-order.md` (⚠️ `ViewOrder` está DESMONTADO: la orquestación NO vuelve a la
>   página) y `specs/menores-a-cargo.md` §9.10/§9.11.
>   ▶ ✅ **SESIÓN DEL 2026-08-28 POR LA TARDE (carril A) — LA FORMA DEL PANEL, TANDA 1 (`DECISIONES #223`,
>   `specs/panel-navegacion.md`).** El owner pidió «simplificar el panel, mejor UI/UX, empezando por el
>   MENÚ y la organización de cada acción: hay mucho jaleo, y ajustes que no hacen falta en el día a día».
>   **Medido antes de proponer nada**: el admin veía **24 entradas en 6 grupos, todos desplegados**, y
>   solo **4** eran del día a día según `PANEL-ADMIN.md` §2 — el **83 % del menú era puesta en marcha**;
>   cero búsqueda global; «Pedidos» ordenando por fecha de COMPRA y no de visita; «Usuarios» mezclando 23
>   clientes con 5 del equipo; «Calendario» y «Crear pedido» **duplicados** (menú + barra superior); y
>   **un solo fichero de test en todo el repo miraba la navegación**. ⚠️⚠️ **La PRIMERA medición fue FALSA
>   y casi arranca el diseño torcido**: volcar la navegación de dos roles en el MISMO proceso dijo que el
>   empleado veía las 24 del admin —que sería un agujero de seguridad—. **Filament MEMOIZA la navegación**;
>   en procesos separados el empleado veía 5 y el gateo estaba bien. *Cuando un instrumento dice que algo
>   está roto de par en par, la primera hipótesis es el instrumento.* ❗ **El owner CORRIGIÓ al agente y para
>   bien**: se le propuso un menú de 10 entradas con 4 grupos plegables (clusters) y contestó «**no quiero
>   toggles, el menú PLANO**; ajustes, catálogos, programación, contenido web y sistema que salgan solo
>   desde el icono del usuario, escondido». Con su corrección el menú queda en **5**, no en 10. **Lo que
>   entró**: menú plano **Hoy · Calendario · Pedidos · Clientes · Puerta** sin grupos · las **19** restantes
>   a **`/admin/ajustes`** en tarjetas por área **con una línea de qué hace cada una** (el problema real no
>   era que «Temporadas» se llame mal, sino que nadie sabía para qué era) · la entrada **dentro del menú del
>   avatar** · «Calendario» retirado de la barra superior (único duplicado; «Crear pedido» se queda porque
>   es una ACCIÓN, no un sitio) · «Usuarios» partido en **dos pestañas de la MISMA pantalla**, «Clientes»
>   (menú) y «Equipo» (Ajustes), cortadas por `User::PANEL_ROLES`, la misma lista que decide
>   `canAccessPanel()`. **Medido después**: admin **24 → 5**; empleado **5 → 4** (no ve «Clientes»: exige
>   `users.manage`, que su rol no tiene — es decisión de permisos, no omisión) y **no ve «Ajustes» en
>   absoluto**. ⚠️ **Ocultar NO es autorizar**: las 19 conservan intacto su `canViewAny()`/`canAccess()`,
>   verificados uno por uno. ⚠️⚠️ **El riesgo real de esconder es dejar una pantalla HUÉRFANA** —fuera del
>   menú y fuera de Ajustes, inalcanzable salvo tecleando la URL, sin que nada avise—: por eso
>   `AdminSettingsHub::areas()` es fuente única y **`AdminNavigationTest` exige que toda pantalla registrada
>   esté en el menú, en `areas()` o en `OUTSIDE_HUB`**. ⚠️⚠️ **Las utilidades de color de Tailwind habrían
>   dejado el ARO DE FOCO invisible**: `hover:border-primary-500` y `focus-visible:ring-primary-600` compilan,
>   pero `--color-primary-500/600` **no están declaradas** (solo la 400) — el mismo fallo que dejó la puerta
>   en blanco y negro en `#217`. El estilo pasó a `theme.css` con `var(--primary-*)`, que además hace que el
>   color de marca por instalación mande. ⚠️ **Un test se volvió VACÍO sin ponerse rojo** (`RateTypeResourceTest`:
>   su `assertFalse(shouldRegisterNavigation())` pasaba por falta de permiso y ahora pasa para cualquiera) y
>   ⚠️ **un `sed` de renumeración se comió 21 ficheros ajenos** (cookies, landing, `app.js`), detectado
>   comparando fichero a fichero y revertido con `git checkout --`. **Verificación**: 12 casos nuevos ·
>   **3 mutaciones, las 3 muerden** · suite del panel **1162 / 5125** · headless con capturas a 1440 y 390 px.
>   ▶ ✅ **Y LA TANDA 2, EL BUSCADOR (`#224`, spec §7)**, pedida por el owner en la misma sesión
>   («buscador total del panel, sobre clientes, pedidos y demás»). Es la otra mitad del menú plano:
>   al esconder 19 pantallas, **escribir sustituye a mirar el menú**. Entran **14 recursos**
>   buscables —pedidos por código y por nombre/correo del titular; clientes por nombre, correo y
>   teléfono; y los doce de configuración— **más una categoría que Filament NO trae: las
>   PANTALLAS**, sacadas de las MISMAS fuentes que las pintan (la navegación + `visibleAreas()`),
>   buscables **por su descripción** («precio» → Tarifas, «festivo» → Fechas especiales) y **sin
>   tildes** («catalogo» → «Catálogo»). Atajo `CTRL+K` / `⌘K`. ▶ `[DECIDIDO owner]`: **el empleado
>   busca PEDIDOS, no clientes** —se sostiene sin código nuevo, y comprobar a una persona sigue
>   siendo la pantalla de Puerta, que es la que lleva límite y auditoría (`SEC-05`)—. ⚠️ **Al
>   buscador de clientes NO se le puso ese tratamiento a propósito**: allí busca un rol BAJO y el
>   límite frena una enumeración; aquí busca un ADMIN, que ya puede paginar la lista entera. **Si
>   algún día se le abre al empleado, eso cambia.** ⚠️⚠️ **De regalo, un defecto VIVO que apareció
>   midiendo**: **buscar «jump» en el Catálogo del panel no encontraba «Jump · 1 hora»** —MySQL
>   extrae un valor JSON con colación `utf8mb4_bin` y el `LIKE` distingue mayúsculas; medido 0 vs
>   5—, y estaba así en `CatalogTable` y `RateTypeTable` desde que se escribieron, sin ningún test
>   que lo viera. Arreglados los dos. ⚠️⚠️ **Y la palanca de Filament para eso NO es portable**:
>   genera `lower(json_extract(...))` en MySQL pero `lower(tabla.name->es)` en SQLite, que es donde
>   corre la suite → panel bien, suite roja. Se resuelve con `wrap()` de la gramática + `LOWER()` a
>   mano. ❗ **Un test en SQLite NO demuestra la conducta en MySQL** (su `LIKE` ya ignora
>   mayúsculas, comprobado mutándolo): por eso hay DOS comprobaciones, la de conducta y otra que
>   asevera la CONSULTA. ⚠️ **PHP 8.4+ prohíbe redeclarar una propiedad de un trait con otro valor
>   inicial** (error FATAL al cargar la clase). ⚠️⚠️ **Y la guarda más importante pareció CIEGA al
>   mutarla y no lo era**: la defensa tiene DOS capas —`canViewAny()` abre la búsqueda del recurso,
>   `canView()` da la URL de cada resultado, y **Filament descarta el resultado sin URL**—, así que
>   la mutación era demasiado débil. ⚠️ El atajo se anunciaba «META+K» en Windows/Linux; con
>   `mod+k` sale ⌘+K en Mac y CTRL+K en el resto (verificado con los tres user-agents), y lo vio el
>   sondeo headless, no un test. **11 casos nuevos · 4 mutaciones, las 4 muerden · suite del panel
>   1174 / 5155 · conducta comprobada a mano contra MySQL · capturas.**
>   ▶ ✅ **Y LA TANDA 3, LA PUERTA EN TABLET (`#232`, spec §8)**. `[DECIDIDO owner]` a pregunta
>   simple: **la tablet de la puerta va FIJA en un soporte y en HORIZONTAL**, y **tiene tablet
>   propia** (el resto del panel se usa en ordenador). Eso la convierte en un KIOSCO. **Medido
>   antes**: con una ficha abierta el contenido medía **1.298 px contra 1.080 de pantalla** y la
>   columna se quedaba en **768 px** de 1.080; el CSS del panel **no tenía ni una regla entre 640 y
>   1280 px**, el rango exacto de una tablet. ⚠️ **Medir el caso PEOR y creerlo típico habría
>   torcido el diseño**: los 1.298 son un cliente con la exención de versión anterior; por caso real
>   «no registrado» **810** (cabía), «falta firmar» **896**, «versión anterior» **1.115** — faltaban
>   ~90 px, no 300. ⚠️⚠️ **Y la primera idea salió PEOR y lo dijo la medición**: pasar la rejilla de
>   3 a **4 columnas** estrechó las tarjetas a 242 px, su texto envolvió y **crecieron a lo alto**
>   (Exención 187 → 226): la rejilla bajó 18 px y el total **subió 3**. Vuelta a tres. **Lo que
>   entró, todo CSS**: ancho 48rem → **80rem** desde 64rem · rejilla de 3 columnas · cabecera en una
>   línea · **nombre a 2,5 rem** · **44 px de mínimo táctil FUERA de todo `@media`** · y el
>   **buscador pegado arriba**, que es la decisión de uso (en un kiosco la acción más repetida es
>   «el siguiente», y el lector de QR escribe en ese campo). ▶ **Todo CSS es deliberado**: el velo de
>   privacidad es un `blur()` con un `.gate-veil` absoluto encima, y cualquier `display: contents` o
>   contenedor de scroll nuevo se lo lleva por delante. ⚠️ **«Nueva búsqueda» NO se ocultó** pese a
>   ser el candidato obvio a recortar: **quita de la pantalla la ficha del cliente anterior**, o sea
>   que es privacidad y no comodidad — con guarda que impide ocultarlo. **Resultado**: iPad
>   horizontal se pasa 64 px, Air 40, Pro y vertical **caben**, y en las cuatro **se ve «Registrar
>   visita» sin desplazar**; **cero controles bajo 44 px**. `GateKioskTest` (4 casos) · **4
>   mutaciones, las 4 muerden** · 79 casos de puerta verdes · headless en cinco anchos con capturas.
>   ▶ **Fuera de esta tanda a propósito** (ficha en `DEUDA`): el **calendario** (8 reservas a 36 px,
>   5 botones a 32–36) y las **tablas** («Pedidos» se sale 97 px en vertical y 163 en horizontal),
>   porque el owner usa el resto del panel en ordenador. ⚠️ La palanca existe y **no se usa en
>   ninguna tabla**: `Split`/`Stack` de Filament.
>   ▶ ✅ **Y EL PULIDO DE LA PUERTA, con el owner delante (`#234`, spec §8.6)**: ocho puntos suyos.
>   **(1)** fuera el buscador pegado —▶ **no es marcha atrás: el (2) elimina el problema que
>   resolvía**— · **(2)** tras cada búsqueda válida el campo **se vacía y conserva el foco**, para que
>   entre dos clientes no haya ningún gesto (el lector de QR es un teclado); ⚠️ con entrada INVÁLIDA
>   no se vacía, que ahí hay que corregir · **(3)** fuera la tarjeta del **QR** · **(4)** fuera la de
>   **VISITA** hasta que exista JumpPoints — ❗ **era el ÚNICO sitio que registraba visitas, así que
>   `customer_visits` DEJA DE CRECER**; la maquinaria sigue entera y probada, falta el botón
>   (`lealtad-jumppoints.md` **§9.bis** con las tres preguntas que deja abiertas, y ficha en `DEUDA`)
>   · **(5)** las dos columnas **arrancan a la misma altura** (medido: las dos en 464 px) ·
>   **(6)** el cursor va al campo **al entrar y al volver desde otro programa** — ⚠️ un caso que
>   `autofocus` NO cubre, porque volver del TPV no recarga la página; con `preventScroll` para no
>   arrastrar a quien estaba leyendo la ficha · **(7)** el **hueco grande entre las tarjetas de la
>   izquierda NO era un margen**: con `grid` las dos columnas comparten la altura de fila, «Hoy»
>   (98 px) vivía en la de «Exención» (187) y dejaba **89 px en blanco**; ahora son **dos pilas
>   independientes** y todos los huecos miden **16 px** · **(8)** «0 menores a cargo declarados
>   (respuesta válida…)» estaba escrito para quien lee la spec → **«Sin menores declarados.»**
>   ⚠️⚠️ **Cuatro trampas**: busqué los tests en `tests/Feature/Puerta/` **y me dejé
>   `tests/Feature/Admin/Puerta/`** (cuatro casos saltaron al correr la suite, re-apuntados por
>   sujeto) · `assertDontSee('data-gate-visit')` falla aunque la tarjeta esté bien quitada porque
>   `data-gate-visit-badge` **contiene esa subcadena** (cuarta vez en el repo) · ⚠️⚠️ **una sonda dijo
>   que la búsqueda ya no abría NINGUNA ficha y el código estaba bien**: mis propios sondeos habían
>   agotado el limitador de búsquedas tecleadas por hora — **tercera vez en la jornada que el
>   sospechoso correcto es el instrumento**, y llegué a escribir en el código que había «medido» otra
>   causa · y un script dejó un `</div>` huérfano al final del fichero, que cazó contar aperturas y
>   cierres, no la vista. **`GateKioskTest` sube a 7 casos · 83 de puerta en verde · sondeos con
>   capturas.** Alto por caso: **810** (cabe) · **816** · **1.018**.
>   ▶ ✅ **Y LOS DOS ENCARGOS DE MENORES (`#236`, `specs/menores-a-cargo.md` §10 y §11)**.
>   **(1)** al declarar un menor se piden también **APELLIDOS** (campo aparte) y **RELACIÓN** con el
>   titular —lista fija traducida: padre · madre · tutor/a legal · abuelo/a · otra—, que es lo que
>   sostiene que ese adulto pueda firmar la exención en su nombre. ⚠️ **Las dos columnas son NULABLES
>   y no se rellenan a la fuerza**: las fichas anteriores no las tienen y **inventar un valor sería
>   meter un dato falso en una tabla que alimenta una FIRMA legal**; la obligatoriedad vive en la
>   validación del ALTA. **(2)** ⚠️⚠️ **la PUERTA enseña ahora el NOMBRE del menor, y eso REVIERTE una
>   decisión de privacidad escrita en CINCO sitios** (DTO, servicio, componente, vista y un test hecho
>   para bloquearla). El motivo es operativo y la versión anterior no lo resolvía: con tres niños y
>   una firma que falta, **«7 años ✗» no dice a cuál**. ▶ **No era una invariante**, así que era
>   reversible con el ✅ del owner —se le avisó antes de tocar nada—, y **no es «abrir la mano»: los
>   APELLIDOS siguen fuera y es estructural** (`GateProfileData` no tiene campo). En el PANEL sí va el
>   nombre completo. ⚠️ **La firma del waiver guarda el nombre COMPLETO cambiando el VALOR y no el
>   conjunto de campos**: `computeHash()` los cubre, así que tocar la forma obligaría a subir
>   `CANONICAL_VERSION` y las firmas antiguas conservan su hash. **Cuatro guardas re-apuntadas por
>   SUJETO, ninguna borrada** (las dos de la puerta ahora exigen que no lleguen los APELLIDOS; la de
>   columnas incluye las dos nuevas y asevera que `age` sigue sin existir; la del payload, clave a
>   clave). **Coste por FEATURE**: chunk 251,02 → **252,27** (techo 253) y payload 8.615 → **9.017**
>   (techo 9.100) — casi todo el desplegable de relación, y **no hay poda que lo pague**. Verificado en
>   navegador: la puerta pinta «Lior · 9 años · sin exención» y **el apellido no está en el HTML**; el
>   alta del cajón enseña los cuatro campos y las cinco opciones. ⚠️ **Tercera colisión de numeración
>   del día**: nació como `#235`, que el carril C ya había usado; renumerada con la lista sacada del
>   PROPIO diff (`git status`), no de un grep del árbol.
>   ▶ ✅ **Y ARRANCA EL CAJÓN EN MÓVIL con una MEDICIÓN, no con código (`#237`)**. Encargo del owner:
>   «el SPA tiene que ser perfecto en móvil, que es el 90 %». Recorrido el embudo en 390×844.
>   ⚠️⚠️ **El hallazgo que nadie buscaba: el aviso de cookies tapaba 296 px — el 45 % del cajón — y
>   con ellos el botón que hace avanzar la compra**, en `/entradas`, donde el cajón NACE ABIERTO. O
>   sea que **todo cliente nuevo en móvil** se encontraba el paso de fecha a medias. **No lo veía
>   ningún test porque ninguno mide DOS capas a la vez**: no faltaba un caso, faltaba una categoría.
>   La causa: su `z-index: 1000` **no salía de ninguna escala** (nada más pasa de 210). Ahora ocupa un
>   sitio por ROL, **140**: encima de la página, debajo de lo que el cliente abre a propósito. ▶ **El
>   consentimiento no se pierde**: verificado en navegador que con el cajón cerrado vuelve a mandar y
>   «Aceptar» funciona. ⚠️ **Trampa de mi propia sonda**: decía que el solape seguía igual tras el
>   arreglo — medía RECTÁNGULOS, y lo que cambia es quién PINTA encima (`elementFromPoint()`).
>   ▶ **Lo medido y aún sin tocar, que es el trabajo siguiente**: catálogo **bien** (1 de 16 controles
>   bajo 44 px); **fecha 11 de 12** (celdas 43×43, flechas 32×32, «Volver» 61×17) y pinta **un mes de
>   42 celdas donde solo 2 eran reservables** —se busca en vez de elegir—; **hora 12 de 13** (chips
>   68×39) con **~400 px de pantalla vacía** y **ningún chip dice cómo está de lleno**.
>   `[DECIDIDO owner]`: la hora será **tira deslizable con ajuste** (no un deslizador continuo: con
>   once horas hay que pasar por todas y el dedo tapa lo que eliges) y la disponibilidad se enseña
>   **solo cuando quedan pocas**, con umbral configurable. `LayerOrderTest` (2 casos, 2 mutaciones y
>   las 2 muerden). ⚠️ **El carril C CERRÓ**, así que `resources/js/**` y `public/css/site.css` ya no
>   se disputan.
>   ▶▶ ❗❗ **CIERRE DE ESTA SESIÓN (2026-08-28, 21:30 hora de Madrid) — POR DÓNDE RETOMA LA SIGUIENTE.**
>   Todo empujado y con el gate en verde: suite **3357 / 22.093** · JS **813** · Pint ✓ · docs-check ✓ ·
>   build ✓. `audit-clock` **NO corrido a propósito**: la sesión no añadió ninguna fecha nueva ni
>   ninguna afirmación de edad sin reloj congelado —los fixtures tocados conservan sus fechas fijas y
>   su `travelTo`— (comprobado sobre el diff de los seis commits).
>
>   ▶ **LO SIGUIENTE ES EL CAJÓN EN MÓVIL, y ya está medido** (`#237`, y la medición vale: no hay que
>   repetirla). El orden acordado con el owner:
>     **1.** ~~El aviso de cookies deja de tapar el cajón~~ **HECHO** (`#237`).
>     **2.** **La FECHA pasa a una tira de días RESERVABLES** + objetivos de 44 px. Medido: 11 de 12
>        controles por debajo de 44 (celdas 43×43, flechas 32×32, «Volver» 61×17) y el calendario
>        pinta **un mes de 42 celdas donde solo 2 eran reservables** — el cliente BUSCA en vez de
>        elegir, y eso pesa más que el tamaño. El mes completo puede quedar tras un «ver más fechas».
>     **3.** **La HORA pasa a tira deslizable con AJUSTE** (`scroll-snap`), `[DECIDIDO owner]`. ⚠️ **NO
>        un deslizador continuo**: con once horas hay que pasar por todas y el dedo tapa el valor que
>        se elige. Medido: chips de **68×39** y **~400 px de pantalla vacía** debajo.
>     **4.** **Cada hora dice cómo está de llena, pero SOLO cuando quedan pocas** (`[DECIDIDO owner]`),
>        con el **umbral configurable desde el panel** — es un dato de negocio. Hoy todos los chips
>        son idénticos y el cliente no sabe que las 11:00 están casi llenas.
>     **5.** Repasar toques y teclado en **carrito e identificación**, que NO se han medido aún.
>
>   ▶ **DOS PUNTOS NUEVOS DEL OWNER, apuntados hoy y sin empezar:**
>     · ⚠️⚠️ **«Crear pedido» del panel, para TABLET** (`specs/panel-navegacion.md` §6·U7). **Esto
>       CORRIGE la premisa de `#232`**: allí el owner dijo «la puerta tiene tablet propia, el resto del
>       panel se usa en ORDENADOR» y por eso el calendario y las tablas quedaron fuera. **Ha cambiado:
>       el GERENTE creará las reservas desde la tablet.** `/admin/crear-pedido` necesita su pasada —y
>       sobre todo de **presentación**: hoy son 1.362 líneas de formulario pensadas para un ratón—.
>       Con ella vuelve a la mesa parte de U6 (quien crea un pedido mira el calendario).
>     · **El selector de menores del embudo hace demasiado ruido** (`specs/menores-a-cargo.md` §12):
>       «es demasiado llamativo, hay que hacerlo más sutil». No es un fallo —funciona y está probado—:
>       es peso visual. ⚠️ **Antes de tocarlo, medir** cuánto alto se lleva del paso, como en `#237`;
>       sin ese número «más sutil» es una opinión. ❗ **Lo que no se puede perder al bajarle el ruido**:
>       la exención firmada es CONDICIÓN para asignar, la fila no marcable tiene que seguir
>       **visiblemente** deshabilitada con su motivo, y ese motivo se anuncia a un lector de pantalla.
>
>   ▶▶ ✅ **SESIÓN DEL 2026-08-28 (noche, carril A) — LAS UNIDADES 2, 3 y 4 DEL CAJÓN EN MÓVIL
>   (`DECISIONES #239`, spec propia `specs/cajon-en-movil.md`).**
>   ⚠️⚠️ **Lo PRIMERO fue corregir una premisa de la medición anterior, y cambió el diseño.** `#237`
>   escribió «un mes de 42 celdas donde solo 2 eran reservables» y de ahí dedujo que sobraban días.
>   Medido contra `AvailabilityOffer` antes de tocar nada: **182 días reservables** por producto
>   (horizonte 6 meses, el parque abre a diario), **11 horas** al día (10 en packs) y aforos 40 · 25 ·
>   60. ▶ **No faltaban días: sobraba rejilla.** El «2 de 42» solo vale del mes en curso, que abre casi
>   entero en el pasado. Y por eso **el calendario NO se retira** —una reserva de cumpleaños se hace
>   con meses de antelación y 182 chips no se recorren con el dedo—: queda plegado tras «Ver más
>   fechas» (`[DECIDIDO owner]`).
>   **Lo que entró**: la **tira de días reservables** (`calendar.js::buildStrip()`, agrupa por mes, con
>   el año en el rótulo solo cuando cambia), el **calendario plegable** que vuelve a cerrarse con cada
>   oferta nueva, la **tira de horas** con ajuste, el aviso **«Casi llena» sin número**
>   (`[DECIDIDO owner]`) con umbral configurable desde Ajustes (`booking.low_availability_max`,
>   `AvailabilitySettings` + `low_availability_max` en `GET /config` **con su operador escrito**), y
>   los **objetivos de 44 px**: celdas 43→**45** (aritmética, no gusto: baja el relleno del calendario
>   de 12 a 8 y el hueco de 4 a 3), flechas 32→**44**, chips de hora 68×39→**72×44** y el «Volver» de
>   la banda con **45 px de área** sin engordar una banda que se pinta en todos los pasos.
>   ⚠️⚠️ **La HORA lleva una OBJECIÓN MEDIDA que el owner mantuvo, y está escrita** (spec §4.2): son 11
>   horas y **cabían las 11 a la vez** en tres filas de 350 px; la tira enseña **4 de 11**. La eligió
>   por coherencia con los carruseles del artboard de móvil del cliente. Queda por escrito para que la
>   siguiente sesión sepa que fue una decisión, no un descuido.
>   ⚠️⚠️ **La vela sola NO bastaba y lo dijo el navegador**: el degradado va hacia `--bg` (#F4EFE3) y
>   los chips son `--bg-card` (#FBF7EC) —**casi el mismo color**—, así que lo que dice «hay más» es el
>   chip **cortado por el borde de la pantalla**: las dos tiras salen **a sangre**. ⚠️ Y el **separador
>   de mes NACÍA CORTADO** (`x = −5`) sin su `scroll-snap-align`: *una parada de ajuste no es «un sitio
>   donde se pulsa», es «un sitio donde la tira puede quedarse quieta»*.
>   ⚠️⚠️ **Una guarda nació LAXA y lo dijo la mutación**: el caso «el aviso mira `available`, no
>   `max_quantity`» usaba 60/20 con umbral 8 —**los dos por encima**—, así que intercambiar el campo
>   pasaba en verde. Rehecho con 60/6.
>   ⚠️⚠️ **Y DOS instrumentos propios salieron mal antes de acertar**: medir la caja pintada daba
>   «Volver» como defecto (61×17) cuando su área táctil son 45 —llamaba defecto a la solución— y
>   `elementFromPoint()` daba **178 defectos** en la tira porque los chips fuera del carril están fuera
>   del viewport. **Tercera vez en la semana que el sospechoso correcto es el instrumento.**
>   **El contrato de árbol gana DOS casos** —el calendario desplegado y el aviso—: desde el rediseño
>   los casos que había **dejaron de emitir una sola celda de la rejilla**, así que `.cal__grid`,
>   `.cal__day`, las flechas y la leyenda salían del gate sin que nada avisara. Manifiesto regenerado a
>   propósito (4 cambiadas, 2 nuevas).
>   **Verificación**: suite **3374 / 22.251** · JS 813 → **835** · **10 mutaciones, las 10 muerden** ·
>   headless **22/22** a 390×844 y 2/2 con el umbral alto (`VERIFICACION-E2E-CAJON.md`
>   **§5.novodecies**) · chunk 252,27 → **255,13 KiB** (techo 253 → 256, medido construyendo con y sin)
>   · Pint ✓ · docs-check ✓ · build ✓. `audit-clock` **NO corrido**: la tanda no añade fixtures con
>   calendario ni afirma ninguna fecha sin reloj fijo.
>   ❗ **LO QUE QUEDA DE ESTA TANDA**: **(1)** el **OJO del owner** sobre §5.novodecies —y en especial
>   deslizar las dos tiras con el dedo—; **(2)** el **hueco vertical**, medido y sin resolver a
>   propósito: **366 px vacíos en fecha (66 %)** y **452 en hora (81 %)**, y la tira lo **empeoró unos
>   50 px** respecto a los ~400 de `#237` —donde había tres filas de chips ahora hay una—. Rellenarlo es
>   decisión de producto (`[PENDIENTE: owner]`); **(3)** la **unidad 5**, carrito e identificación, que
>   sigue **SIN MEDIR**.
>   ⚠️ **Para el carril C**: esta tanda tocó `public/css/site.css` en el bloque del CAJÓN (líneas
>   ~1150–1500: `.timestrip`, `.daystrip`, `.cal*`, `.bk-back`), `resources/js/sidebar/**`,
>   `scripts/render-sidebar.mjs`, `lang/*/tickets.php`, `lang/{es,zh_CN}/admin.php`, `openapi/v1.yaml`
>   y `app/{Domain/Booking/Services,Filament/Pages,Http/Resources}`. **NO toca** `landing.css`,
>   `home.blade.php`, el armazón ni el menú.
>
>   ▶▶ ✅ **Y EL SEGUNDO ENCARGO DE LA NOCHE: «CREAR PEDIDO» EN TABLET** (`DECISIONES #240`,
>   `specs/panel-navegacion.md` §9, la U7 que quedaba abierta). `[DECIDIDO owner]` sobre tres opciones
>   con su coste, y con las capturas de la pantalla actual delante: **dos columnas + controles táctiles**.
>   ⚠️⚠️ **La primera medición dijo «cabe» y era FALSA porque midió solo el paso 1.** El denso es el 2:
>   con un producto elegido medía **1.292 px en una pantalla de 810** —se pasaba 482— y con él quedaban
>   **fuera de pantalla el RESUMEN del pedido (a 1.100 px) y el botón de avanzar**, las dos cosas que
>   hay que ver con un cliente delante. Todo en **una columna de 648 px** dentro de un lienzo apaisado
>   de 1080, y **~16 toques** para el pedido más simple.
>   **Lo que entró**: dos columnas con el **resumen PEGAJOSO** y la navegación dentro de él; la **hora
>   en chips** (`ToggleButtons`, el nativo de Filament, que conserva `disableOptionWhen`); una **tira de
>   14 días rápidos** para la fecha **conservando el calendario** debajo como «Otra fecha»; y **44 px**
>   en todo lo que se toca. **Resultado: paso 1 762→554, paso 2 vacío 1.026→778 (cabe), paso 2 con
>   producto 1.292→1.182, y de 4/8/15 controles bajo 44 a CERO en los tres.**
>   ⚠️ **El corte son 50rem y cubre las dos orientaciones aunque no sea evidente cuál es más ancha**: en
>   apaisado el menú se lleva 250 px y quedan **760**; en vertical el menú se esconde y quedan **810**.
>   ⚠️⚠️ **La trampa que podía haber quedado dentro**: ahora hay **DOS puertas para elegir día** y la
>   hora y los menores dependen de la FECHA — una regla escrita dos veces diverge. Las dos terminan en
>   `onDateChosen()`, y **dentro de un `afterStateUpdated` escribir en `$this->data` a mano SE PIERDE**
>   (el formulario re-sincroniza después), así que la regla recibe el `$set` de Filament por una puerta
>   y escribe el estado de la página por la otra. Lo dijo la guarda, no el ojo.
>   ⚠️⚠️ **TRES instrumentos propios salieron mal, y uno llegó hasta la guarda**: la sonda midió la
>   pantalla de **LOGIN** cuatro veces (`waitForURL('**/admin/**')` casa con `/admin/login`); la
>   comprobación del pegajoso pedía que **no se moviera** cuando un `sticky` sí se mueve hasta su tope;
>   y la guarda de la navegación **pasaba en verde con la mutación puesta** porque `cmo-nav-fuera`
>   contiene `cmo-nav` — **quinta vez que la subcadena engaña a una aserción aquí**. Y un cuarto: el
>   caso de las dos puertas acusaba al calendario con el código bien, porque **un campo oculto no tiene
>   `afterStateUpdated`** y el arnés dejaba el paso en 1.
>   **Verificación**: `CreateManualOrderTabletTest` (7 casos) · **6 mutaciones, las 6 muerden** · 50
>   casos de «crear pedido» y 77 del panel en verde · headless **11/11** de conducta + medición en las
>   cuatro tablets, con capturas.
>   ❗ **Queda**: el **OJO del owner** con la tablet en la mano; el **armazón del panel** (barra, menú y
>   buscador siguen bajo 44 px en TODAS las pantallas — ficha nueva en `DEUDA.md`, y agrandarlo cambia
>   el aspecto en ordenador, así que se decide con él); y **U6**, el calendario y las tablas, que
>   vuelven a la mesa ahora que la tablet es un dispositivo de trabajo.
>
>   ▶▶ ✅ **Y LA VUELTA DEL OWNER SOBRE LAS DOS PANTALLAS NUEVAS** (`DECISIONES #241`,
>   `specs/cajon-en-movil.md` §7.bis y `specs/panel-navegacion.md` §10). Seis puntos suyos tras verlas
>   en su navegador; ninguno es un fallo de conducta, los seis son **UX a medias**.
>   **(1)** ⚠️⚠️ **Con RATÓN no se podía deslizar ninguna tira** —«sí o sí hay que desplegar el
>   calendario»—, y era cierto: la barra va oculta a propósito. Entran flechas en las TRES tiras (fecha
>   y hora del cajón, días del panel), **solo donde hay ratón** (`hover: hover` **y** `pointer: fine`
>   juntas — la primera sola la cumple un táctil con lápiz) y **solo si llevan a algún sitio**.
>   ⚠️⚠️ **Las del cajón NACIERON MUERTAS y lo cazó la primera sonda**: el cableado se enganchaba en
>   `onMounted` y el carril vive dentro de un `v-if` que espera la oferta, así que **al montar el
>   componente el nodo no existe**. Medido: 11.535 px de recorrido en un carril de 440 y la flecha
>   oculta por su propio `v-show`. Ahora observa el NODO. ▶ *Un composable que asume que su elemento
>   existe al montar falla justo en los componentes que esperan datos, que son casi todos.*
>   **(2)** **Las plazas de una franja pesaban lo mismo que la hora** en el panel. La causa era el
>   control: `ToggleButtons` tiene la etiqueta en **texto plano** y no admite `allowHtml`. Pasa a
>   partial propio (hora 15 px en negrita, plazas 11 px en gris). ❗ **Cambiar el control no cambia la
>   regla**: una franja llena se sigue enseñando **deshabilitada, no escondida**, y `pickTime()` la
>   rechaza en el SERVIDOR. ⚠️ De regalo, `timeOptions()` quedó huérfano y se retiró con el protocolo
>   de `CONVENCIONES §3.quater` — su test vigila la **paridad web↔panel**, así que **se re-apuntó**.
>   **(3)** **«Otra fecha» pasa a un CTA «Abrir calendario»** con el calendario amplio plegado detrás.
>   **(4)** **Las fichas de hora del cajón, al tamaño de las de día** (76×76, `[DECIDIDO owner]` a
>   pregunta simple): la hora arriba y «Casi llena» debajo en segundo plano — la misma corrección que
>   el punto 2, del otro lado del producto. **Medido: se siguen viendo 4 de 11.**
>   **(5)** **El resumen del pedido dice de QUIÉN es** —con el MISMO texto que el buscador de clientes,
>   no una segunda redacción— y **(6)** arranca a la altura de su vecina: ⚠️ **el desfase no era del
>   armazón, era un `mt-6`** del propio partial, de cuando el resumen iba debajo del formulario.
>   **Medido: las dos cards en 229 px.** La columna sube a **19rem** (con 20 el formulario se quedaba
>   en 304 px de contenido; con 17 se le apretaba el correo al titular).
>   ⚠️⚠️ **Y DOS instrumentos volvieron a mentir, uno dentro de una mutación**: la mutación «las franjas
>   llenas se esconden» **no mordía** porque el ancla del `sed` aparecía **dos veces** y mutó el método
>   MUERTO —*una mutación que no muerde puede estar mutando otra cosa*, y de paso destapó el huérfano—;
>   y la sonda contó **la flecha** (32×32) como defecto táctil cuando solo existe con ratón. Y una
>   tercera en una guarda: `assertStringNotContainsString('onMounted', …)` salía en rojo con el código
>   correcto **porque el docblock explica por qué no se usa** — se miran las líneas de CÓDIGO.
>   **Verificación**: suite **3397 / 22.365** · JS **843** · **12 mutaciones, las 12 muerden** ·
>   headless **11/11** (cajón: escritorio Y móvil táctil, `VERIFICACION-E2E-CAJON.md` §5.vicies) y
>   **16/16** (panel) · chunk 255,13 → **256,67 KiB** (techo 256 → **257**, deja 0,33) · Pint ✓.
>   ❗ **Queda el OJO del owner**: con el ratón, deslizar las dos tiras del cajón; con el dedo, que NO
>   aparezca ninguna flecha; y en la tablet, montar un pedido de cabo a rabo.
>
>   ▶▶ ✅ **Y LA SEGUNDA VUELTA DEL OWNER (`DECISIONES #242`)** — `specs/cajon-en-movil.md` §7.ter y
>   `specs/panel-navegacion.md` §11. Cinco puntos suyos, y **uno era un BUG de conducta**.
>   ⚠️⚠️ **«MI CUENTA» LLEVABA AL CARRITO.** «Que el cliente vaya al enlace que lo lleva, no lo
>   redirecciones a otro sitio en el SPA». **Reproducido con el código de antes y el de después**, con
>   sesión y una línea en la cesta: antes `is-cart` / **«Tu carrito»**, ahora `is-account` /
>   **«Mi cuenta»**. La causa: el chip de cuenta de la cabecera lleva a `route('account')` y su clic
>   llamaba a `$store.purchase.open()`, que abre el cajón **en la sección por defecto, la compra**. Sin
>   cesta no se notaba —salía el catálogo—; con cesta, `restoreCart()` remataba. ▶ **La regla queda en
>   una guarda**: *interceptar un clic puede cambiar el CÓMO, nunca el DÓNDE. Un `href` es una promesa.*
>   ⚠️ **Dos trampas antes de poder reproducirlo**: el racimo **nace bajo el hero** (`#216`), así que un
>   clic forzado con `pointer-events: none` **no dispara nada**; y `.sidecart__panel` lleva su clase de
>   modo **también con el cajón cerrado**.
>   **Los otros cuatro**: **(a)** el selector de menores apaga las filas que no caben en vez de pintar
>   «No caben más» —lo que **CORRIGE un comentario que el propio componente tenía escrito**, con la
>   corrección delante del texto; las dos clases de fila apagada siguen distinguiéndose porque la que
>   no se puede marcar NUNCA conserva su **motivo**—; **(b)** el menor asignado lleva **«1 entrada
>   asignada»** en segundo plano; **(c)** fuera **«exención firmada»** —«es obvio: no podemos asignar
>   menores sin firmar»—, retirado con `CONVENCIONES §3.quater` y **sin borrar ninguno de sus tres
>   tests**, que tenían sujeto propio; **(d)** en el panel, el **«Atrás» pasa a solo icono** (con su
>   `aria-label`, que si no queda MUDO) y el **método de cobro a dos TARJETAS con icono** de 154×96
>   —❗ **cambia el control, no la regla**: `ToggleButtons` nativo conserva las claves de
>   `ManualOrderFulfiller`, el `required` y el arranque en efectivo—.
>   ⚠️⚠️ **CUATRO instrumentos mintieron, dos dentro de una guarda**: la guarda de los enlaces de cuenta
>   **pasaba en verde con el defecto puesto** porque el comentario que explica el arreglo vive DENTRO
>   del `<a>` —**tercera vez en la jornada** que una aserción caza la prosa—; una mutación no mordía y
>   **la mala era la mutación**, no la guarda (reintroducía la línea sin su rótulo → la guarda pasó a
>   prohibir la ESTRUCTURA); el aro de la tarjeta se colgó de un **`aria-pressed` que `ToggleButtons`
>   no emite**; y **`flex-direction` sobre `.fi-btn` era una declaración INERTE** —es `display: grid`—
>   **que `getComputedStyle` devolvía igual**. ▶ *Un valor computado dice lo que vale la propiedad, no
>   si esa propiedad manda.*
>   **Verificación**: suite **3402 / 22.394** · JS 843 · **11 mutaciones, las 11 muerden** · headless
>   **16/16** (menores a 390 px), **8/8** (tarjetas de cobro) y la reproducción del bug en los dos
>   sentidos (`VERIFICACION-E2E-CAJON.md` **§5.unvicies**) · Pint ✓ · docs-check ✓ · build ✓.
>
>   ▶ **Y queda APUNTADO, sin empezar, el CUMPLEAÑOS MIXTO** (`specs/cumple-mixto.md`, ⬜ borrador):
>   un cumple KIDS con un invitado por encima de la edad del pack pasa a **MIXTO** —etiqueta «MIXTA»
>   para cliente y operador y la diferencia de precio por persona en los dos desgloses—.
>   ⚠️⚠️ **Medido: TRES premisas del encargo no se cumplen hoy** — el post-formulario **no pide la
>   edad** (`guest_fields` son `name·allergy·notes·special_menu`), los dos packs **cuestan lo mismo**
>   (15/18 €) y **comparten zona** (la 4, mismas franjas y aforo), lo que contradice «KIDS o JUMP lo
>   condiciona la hora de cada zona»: **hay que comprobarlo contra la instalación REAL**, porque si
>   allí son zonas distintas esto toca **AFORO** y deja de ser una etiqueta con recargo.
>   ❗ **El primer paso no es código: son las SEIS preguntas de §5**, y la del **cobro** no se puede
>   elegir por defecto —es dinero **después** de un pedido pagado (`PAY-04`)—.
>
>   ▶▶ ❗❗ **CIERRE DE ESTA SESIÓN (2026-08-29, 01:40 hora de Madrid; el contenedor va en UTC, 2 h
>   menos) — POR DÓNDE RETOMA LA SIGUIENTE.** Todo empujado (`f1ef0b2`) y con el gate en verde: suite
>   **3403 / 22.406** en el corte de este carril y **3405 / 22.434 sobre el árbol CONJUNTO** tras
>   rebasar encima del `#253` del carril C · JS **862** · Pint ✓ · docs-check ✓ · build ✓ ·
>   **`audit-clock` CORRIDO y verde en las 12 fronteras** (las 10 fijas más las dos que cruzan
>   medianoche a mitad de pase). ⚠️ **Se corrió porque esta sesión SÍ añadió fixtures con calendario**:
>   `CreateManualOrderTabletTest` siembra 20 días de franjas **relativas a hoy** y asevera que la tira
>   enseña 14; sin la auditoría, un fallo por el día de la semana o por un cambio de mes se habría
>   descubierto días después y en el carril equivocado.
>   ⚠️ **La auditoría se corrió sobre el corte de 3403**, antes del rebase: cubre los fixtures de esta
>   sesión, no los dos casos que trajo `#253`.
>
>   **Lo que entró, en cuatro decisiones**: `#239` (la FECHA como tira de días reservables + la HORA
>   como tira con ajuste + el aviso «Casi llena» con umbral en el panel) · `#240` («Crear pedido» en
>   tablet: dos columnas con el resumen pegajoso, chips de hora, tira de 14 días y 44 px en todo) ·
>   `#241` (la vuelta del owner: **flechas de ratón** en las tres tiras y las fichas de hora al tamaño
>   de las de día) · `#242` (su segunda vuelta: el selector de menores, el «Atrás» de solo icono, las
>   tarjetas de cobro **y un BUG — «Mi cuenta» abría el carrito**).
>
>   ▶ ❗ **LO SIGUIENTE, y está acordado**: el owner dijo «vamos a continuar» sin nombrar la tarea, así
>   que **pregúntale al arrancar**. Lo que hay sobre la mesa, en el orden en que lo dejó él:
>     **1.** **Unidad 5 del cajón en móvil** — carrito e identificación, **SIN MEDIR todavía**. Se mide
>        igual que `#237` (`specs/cajon-en-movil.md` §1) antes de tocar nada.
>     **2.** **El hueco vertical del cajón**, medido y sin resolver a propósito: **366 px vacíos en
>        fecha (66 %)** y **452 en hora (81 %)**, y la tira lo empeoró ~50. Rellenarlo es decisión de
>        producto, no de implementación (`cajon-en-movil.md` §7.4, `[PENDIENTE: owner]`).
>     **3.** **El CUMPLEAÑOS MIXTO** (`specs/cumple-mixto.md`, ⬜): **el primer paso no es código, son
>        las seis preguntas de su §5** — y tres premisas del encargo **no se cumplen hoy**.
>     **4.** **U6 del panel** (`panel-navegacion.md` §6): el calendario y las tablas en tablet, que
>        vuelven a la mesa ahora que la tablet es un dispositivo de trabajo.
>     **5.** El **armazón del panel** sigue bajo 44 px en TODAS las pantallas (ficha en `DEUDA.md`):
>        ⚠️ agrandarlo cambia el aspecto en ORDENADOR, así que se decide con él antes de tocarlo.
>
>   ▶ **Lo que espera al OJO del owner de esta sesión** (nada bloquea): con el **ratón**, deslizar las
>   dos tiras del cajón; con el **dedo**, que NO aparezca ninguna flecha; **tres menores y una entrada**
>   para ver el marcado/desmarcado; **«Mi cuenta» con algo en el carrito**; y en la **tablet**, montar
>   un pedido de cabo a rabo. Guiones: `VERIFICACION-E2E-CAJON.md` **§5.novodecies**, **§5.vicies** y
>   **§5.unvicies**.
>
>   ⚠️ **Método que funcionó y conviene repetir**: los **rangos de numeración por carril** (A tomó
>   `#239`–`#249`, C siguió por `#250`) **quitaron de raíz** las tres colisiones del día anterior — el
>   otro agente los respetó sin hablar con nadie. ▶ **De ese rango quedan libres `#243`–`#249`**: la
>   siguiente sesión del carril A empieza ahí, y si se agota, **reserva el siguiente rango en esta
>   misma línea antes de usarlo**. Se rebasó sobre su trabajo **tres veces** y todos los
>   conflictos fueron de doc «ambos añaden al final/principio»: se conservan LOS DOS bloques.
>
>   ⚠️⚠️ **La lección más cara de la jornada, y se repitió NUEVE veces**: *cuando algo parece roto, el
>   primer sospechoso es el instrumento.* Sondas que medían la pantalla de login, aserciones que cazaban
>   su propio comentario (**tres veces**), una mutación que mutaba un método muerto, un `sticky` medido
>   al revés, `elementFromPoint()` sobre nodos fuera del viewport, un `aria-pressed` que no existe y un
>   `flex-direction` **inerte que `getComputedStyle` devolvía igual**. ▶ **Ninguno era un fallo del
>   producto**, y los nueve costaron tiempo. Antes de creerte un rojo —o un verde—, comprueba que el
>   instrumento mide lo que dice medir.
>
>   ▶ **Lo que sigue esperando al OWNER** (no bloquea a nadie): su ojo sobre el **menú plano**, el
>   **buscador** y la **puerta en tablet** —las tres en `main`, ninguna vista en su navegador—; el
>   repaso de los **rótulos** de las 19 pantallas de Ajustes; y el ✅ a **JumpPoints**, que además
>   ahora arrastra una decisión nueva: **`customer_visits` dejó de crecer** al retirar la tarjeta de
>   visita (`lealtad-jumppoints.md` §9.bis).
>
>   ⚠️ **Y una lección de método de la jornada, que costó tres veces**: con dos carriles sobre `main`,
>   los números de `DECISIONES.md` **chocaron tres veces** (`#225`, `#233`, `#235`). Si se vuelve a
>   trabajar en paralelo, **repartir un rango por carril** lo quita de raíz. Y la renumeración se hace
>   SIEMPRE sobre la lista de ficheros del propio diff (`git status`), nunca con un `grep` del árbol:
>   un `sed` global llegó a corromper cinco referencias del otro carril.
>   ▶ ❗ **LO QUE QUEDA DE ESTA TANDA**: **(1)** el **OJO del owner** sobre el menú, «Ajustes» y las pestañas —
>   es un cambio de UI/UX y la suite no puede decir si «se entiende» · **(2)** repasar con él **los rótulos y
>   las 19 descripciones** (`[DECIDIDO owner]`: las propone el agente, las revisa él) · **(3)** la pantalla
>   **«Hoy»**, que es la mitad que queda del «todo está separado» (la otra, el buscador, ya está:
>   `#224`). ❗ **«Hoy» YA EXISTE** —es el Escritorio renombrado, con las reservas del día— y lo que
>   le falta son CINCO columnas, medido: si firmó la exención · si trae menores · si ya entró hoy
>   (la visita de la puerta, que nadie lee) · **si llega debiendo dinero** (los ajustes de señal y
>   extras que se cobran en persona) · y el teléfono. **No es una pantalla nueva** (`specs/panel-navegacion.md` §6·U3).
>   ⚠️ **Para el carril C**: esta tanda tocó `resources/css/filament/admin/theme.css` (+120 líneas al final),
>   `lang/{es,zh_CN}/admin.php`, `app/Filament/**` y `app/Domain/Identity/Models/User.php`. **NO toca**
>   `public/css/*`, `resources/js/**`, `home.blade.php` ni el cajón.
>   ▶ **Lo que queda pendiente y NO bloquea**: **(1)** el OJO del owner sobre §5.octodecies en staging
>   —y en particular el **lector real** con el PNG descargado, que ninguna suite mide—; **(2)** los
>   **14 hallazgos menores** de la revisión, en `DEUDA.md` con su reproducción (ninguno rompe hoy;
>   los tres de guardas que miden de menos son de una línea cada uno); **(3)** **JumpPoints**, que
>   sigue esperando el ✅ del owner al resumen de una página de `specs/lealtad-jumppoints.md` §9 —y
>   sus seis decisiones—; **(4)** dos decisiones del agente que el owner puede revertir barato: el
>   **modo oscuro** de la pantalla de puerta (8 líneas) y los **6 menores por página** (una constante).
>   ▶ **Y a las 09:35, las ENTRADAS en staging** (`ENTORNOS.md` §4): el `ProductionSeeder` las deja no
>   vendibles y sin franjas; ahora las 4 entradas están a la venta con las plantillas de JUMP/KIDS de local
>   (154, 60 min, 10–20 h) y 3.875 franjas. **El owner va a hacer la prueba de cabo a rabo en staging**
>   (cliente + admin), activará él la exención INTERNA, y después dirá qué pulir; JumpPoints, después.
>   ⚠️ El push de esa doc cayó por un test **flaky por construcción** (`assertStringNotContainsString('Vera',
>   …)` sobre un JSON con nombres de Faker, y Faker es_ES tiene «Vera»): centinelas renombrados a `Lior`/`Vilma`
>   en los siete tests de la familia, regla escrita en `TESTING.md` «Datos de prueba». ▶ **Para el OJO del owner en staging**: «Mi carné» con su cuenta de
>   CLIENTE `yasmindanailov@gmail.com` (verificada) y «Rotar carné QR» con la de ADMIN `yasi09265@gmail.com`
>   sobre esa ficha; el correo de staging va al LOG (`MAIL_MAILER=log`), así que el adjunto del correo NO
>   se ve allí — la zona «Mi carné» sí. ⚠️ **Para el carril C**: esta tanda tocó `resources/js/sidebar/**`
>   (`AccountSection.vue`, `ZoneIcon.vue`, `navigation.js`, zona y store nuevos) y añadió
>   `resources/views/components/icons/qr.blade.php`: `git pull --rebase` antes de empujar. ▶ ❗ **POR
>   DÓNDE RETOMA la siguiente sesión de ESTE carril**: **(0)** el owner: su OJO sobre «Mi carné» (móvil y
>   escritorio, y el LECTOR real con un PNG descargado), la acción del panel, y el ✅ o los cambios a
>   JumpPoints (§9 de su spec) · **(1)** con el ✅: el DISEÑO DE EJECUCIÓN de JumpPoints (§8 antes que el
>   cuerpo; el canje entra en el `CRITICAL_RE`; la caducidad al final por el cron `#115`) · **(2)** sin
>   él: la lista de retoma de abajo sigue vigente (el ojo sobre menores/puerta, guion §9.5).
>   ⚠️ **Tras el cierre, el owner abrió el panel y NO VIO NADA de menores** (ni en un pedido,
>   ni al crear uno) y no pudo probar el QR: **es la condición de diseño, no un fallo** —solo aparece
>   con un cliente que tenga menores declarados DESDE SU CUENTA en la web, y el carné nace con el
>   correo de confirmación—. **El guion de prueba paso a paso está en `identidad-qr-puerta.md`
>   §9.5** (léelo antes de tocar nada), y deja una pregunta de producto **`[PENDIENTE: owner]`: ¿el
>   PANEL debe poder declarar menores de un cliente?** (hoy no, a propósito; no se empieza sin su ✅).
>   ▶ ❗ **POR DÓNDE RETOMA la siguiente sesión de ESTE carril**: **(0)** el guion de §9.5 con el
>   owner delante, y su respuesta a la pregunta · **(1)** nada de agente
>   está a medias — lo primero es el OJO del owner sobre el panel (`menores-a-cargo.md` §9.10.4 «lo
>   que queda») y la puerta (`identidad-qr-puerta.md` §9.4 «lo que queda»: `/admin/puerta/validar`
>   con un cliente con carné y menores; el correo de confirmación en Gmail/Outlook/móvil; y el
>   **lector real del recinto**, que ninguna suite mide) · **(2)** las dos fichas de `DEUDA.md` del
>   carné (zona «Mi carné» del cajón —⚠️ `resources/js/sidebar/**` es del carril C hoy— y «Rotar
>   carné» en `ViewUser`), cuando el owner las pida · **(3)** el subsistema **D · JumpPoints**
>   (`specs/lealtad-jumppoints.md`, §8 va ANTES que el cuerpo): ya tiene su hecho observable,
>   `customer_visits`, y el canje entra en el `CRITICAL_RE` — spec-first, exige el ✅ del owner.
>   **Ficheros de este carril**:
>   `app/Domain/Identity/**` (`DependentAssigner`, `WaiverStatus`), `app/Filament/Resources/Orders/**`,
>   `app/Filament/Pages/CreateManualOrderPage.php`, `resources/views/filament/orders/items-list.blade.php`,
>   `resources/views/filament/pages/partials/manual-order-cart.blade.php`, `lang/es/admin.php`
>   (`orders.dependents.*`), `app/Domain/Platform/Models/AuditLog.php` (una acción), y después
>   `app/Livewire/Admin/Puerta/**`, `resources/views/livewire/admin/puerta/**`, `layouts/puerta.blade.php`.
>   ⚠️ **NO toca** `layout.blade.php`, `public/css/*`, `nav`/`menu`, `app.js` ni `resources/js/sidebar/**`.
>   La sesión anterior de este carril CERRÓ el 2026-08-27 a las 22:11 con la TANDA 4 de «menores a
>   cargo» TERMINADA EN CÓDIGO (U0 · U1 · U2 + el arreglo visual del selector), todo empujado y con el
>   gate en verde (`167bbc2`, `748030a`). ▶ `[DECIDIDO owner, 2026-08-27 noche]` (`#207`): esta sesión
>   es el SUBSISTEMA A de la Fase 6 —el carné QR y la PANTALLA DE PUERTA (`specs/identidad-qr-puerta.md`)—
>   y el PANEL de menores a cargo (D14 de `menores-a-cargo.md` §9.9.3). El MAPA DE ARRANQUE está más
>   abajo, tras el orden de trabajo (sus preguntas (5) ya están contestadas en `#208`). La tanda 4 es de este
>   carril y su DISEÑO DE EJECUCIÓN está en el árbol (`specs/menores-a-cargo.md` **§9.9**, `#202`). Se escribió
>   MIDIENDO antes (seis lectores + crítico: 296 hechos, 14 afirmaciones de la spec falsas o
>   imprecisas) y el owner decidió las cuatro ambigüedades a pregunta simple: **(1)** quien se
>   identifica en el paso 5 vuelve al CARRITO con aviso si tiene menores y entradas sin asignar ·
>   **(2)** ❗ **la exención firmada es CONDICIÓN para asignar** (el servidor lo exige) · **(3)** la
>   purga de la cesta se arregla AHORA como unidad 0 · **(4)** el panel **NO entra** (el owner lo
>   RECTIFICÓ la misma noche: «tendrá su propia sesión; ahora solamente la gestión de menores»).
>   ✅ **U0 HECHA y empujada**: el defecto medido en headless —**la cesta del PROPIO titular se PURGABA
>   cuando el cajón nace abierto** (`/entradas`, `/mi-cuenta`, `/login`, `/registro`,
>   `/recuperar-contrasena`; 2/2 purga, 4/4 se conservaba desde la home; causa: `props.userId` llegaba
>   del HTML y no la leía nadie)— está cerrado: el dueño se siembra en `index.js` antes de montar, guarda
>   estructural con 2 mutaciones que muerden, sonda re-corrida 10/10 (spec §9.9.6; ficha de `DEUDA.md`
>   retirada). ✅ **U1 HECHA y empujada (madrugada del 28)**: el SERVIDOR entero (spec **§9.9.7**):
>   `dependent_assignments` + `DependentAssignment` · `Dependent::referenced()` (UN predicado para
>   `remove()`/`anonymize()`/poda) · `Booking\Contracts\CheckoutLines` + reader + doble · `DependentAssigner`
>   (`check()` ANTES del dinero → 422 por campo; `assign()` tras el `allow` bajo el lock del titular,
>   idempotente, sin lanzar) · `CartLine.dependent_ids` en el contrato y en `CartPayload` (Booking no lo
>   ve) · `OrdersController::store()` · `event-data` con `dependents[]` · `anonymize()` y el export ·
>   `api.dependents.*` ×3 · la paridad mínima de `cart.js`. **9 mutaciones, las 9 muerden · 6 escenarios
>   + Redsys sobre MySQL ✓ · sonda HTTP de 10 pasos ✓.** ✅ **U2 HECHA y empujada (noche del 27)**: el
>   CAJÓN entero (spec **§9.9.8**): `assignment.js` (reglas del selector, reconciliación, puerta 2 y el
>   422 por campo, con `node --test`) · `DependentPicker.vue` en los pasos 3 y 4 con clases que ya existen
>   · `cart.js` con `dependent_ids` en sus cuatro listas y `toCheckoutItems()` · «Para:» en el resumen,
>   el paso 6 y la tarjeta de «Mis reservas» (nombre solo por `event-data`) · rótulos ×3 **en
>   `tickets.dependents`** · manifiesto +2 · techos re-medidos. **Guion §5.undecies 19/19 por las DOS
>   puertas · sonda de 16 `assign()` simultáneos → 1 fila.** ⚠️⚠️ **El guion cazó DOS defectos que las
>   136 guardas del cajón daban por buenos** (§9.9.8·4 y ·5): el cajón nacido abierto con sesión no
>   pedía los menores (y el primer arreglo nació con un TDZ que Vue traga en silencio), y los rótulos del
>   embudo estaban en `account`, que viaja SOLO con sesión: quien se identificaba en el paso 5 volvía al
>   carrito con el selector EN BLANCO. ⚠️ **Y un TERCERO lo vio el OWNER tras el push**: el checkbox del
>   selector a 400 px y el nombre fuera del cajón — `.eventfields input { width:100% }` pisaba a `.check
>   input` (la clase «que existía» era la de los campos de texto del pack). Arreglado sin CSS nuevo
>   (`.addons`/`.addons__intro`), re-medido 16×16 en los dos pasos y anchos, capturas revisadas
>   (spec §9.9.8·7). ▶ **Orden de trabajo**: ~~U0~~ → ~~U1~~ → ~~U2~~ → **U4 (el ojo
>   del owner: guion §5.undecies en su navegador)**. ⚠️ **U2 NO tocó `layout.blade.php`** (se daba por
>   tocado): el aviso de abajo al carril C queda RETIRADO.
>   ▶▶ **MAPA DE ARRANQUE de la siguiente sesión (subsistema A + panel D14), en este orden:**
>   **(1)** `/arranque-sesion` y este bloque. **(2)** Lee `specs/identidad-qr-puerta.md` ENTERA
>   —empieza por §8 (la revisión adversarial) y luego §4—: está **REVISADA y es sólida**, pero sigue
>   🟦 **pendiente del ✅ del owner** (§7) y deja DOS cosas por decidir: **§8.2 la entropía del carné**
>   (`2⁵⁰` en sha256 sin sal: decidirla o justificarla) y **§8.1 rotar `APP_KEY` LANZA, no degrada**.
>   ❗ **§8.3 amplía el alcance**: la pantalla es donde se ACREDITA LA VISITA y de ahí salen los
>   JumpPoints (`[DECIDIDO owner]`): idempotencia y auditoría desde el primer commit, y **no puede colgar
>   de «se abrió la ficha»**. **(3)** Lee `menores-a-cargo.md` §9.9.3 **D14** (el panel: la ficha del
>   pedido enseña «Para: Lucas (9 años · exención ✓)» por `DependentAssigner::forOrderItems()`; la acción
>   «Asignar menores» fija el CONJUNTO con las reglas de D3 y el mismo lock; el alta manual escribe
>   DESPUÉS de `ManualOrderFulfiller::fulfill()`, fuera de su transacción) y lo que la spec de menores
>   exige de la PUERTA: **edad y estado de la exención, JAMÁS el nombre** (§4·48, §6·231, guarda con
>   mutación obligatoria; `identidad-qr-puerta.md` §4.6 fila 3 y §6·2). **(4)** Lo que YA existe y se
>   reutiliza sin tocar: `Dependent` (`isMinorOn()`, `referenced()`), `WaiverStatus::forDependent()`,
>   `DependentAssigner::{check,assign,forOrderItems}` (D3, bajo el lock del titular), la cadena de
>   hashes por (titular, sujeto), `DependentSettings`. ⚠️ El panel es Filament (`app/Filament/**`):
>   `ViewOrder` está DESMONTADO (`desmontar-view-order.md`) — las acciones de línea viven en la
>   familia de «Gestionar»; no vuelvas a meter orquestación en la página. **(5) La PRIMERA pregunta
>   simple al owner**, con el número delante: **¿en qué orden?** Propuesta del agente: **primero el
>   PANEL (D14)** —diseño hecho, cero decisiones pendientes, desbloquea el mostrador, ~1 tanda— y
>   **después la PUERTA**, que es spec-first: exige su ✅ de §7, las dos decisiones de §8 y la
>   guarda «nunca el nombre» antes de la primera línea. **(6)** Sigue pendiente del owner, y no
>   bloquea el arranque: su OJO sobre §5.decies y §5.undecies, y los DOS valores de retención en meses.
>   ▶ **Para el agente del C (el tema/armazón)**: la tanda 4 **no ha tocado ni tocará**
>   `resources/views/components/layout.blade.php`, `public/css/*`, `nav.blade.php`, `menu.blade.php`
>   ni `app.js` (la siembra del dueño de la cesta vive en `resources/js/sidebar/index.js`). El selector
>   del embudo se compone con clases que ya existen (`addons`/`addons__intro`, `form__hint`, `form__checks`, `check` — ⚠️ NO `eventfields`: su `input { width:100% }` rompía el checkbox, §9.9.8·7): **cero CSS nuevo**,
>   como la zona de menores. Si tu 2c·2 toca `layout.blade.php`, `git pull --rebase` antes de empujar.
>   (El aviso al carril B que hubo aquí se retira: el owner rectificó y esta tanda NO toca `app/Filament/**`.)
>   Lo anterior de esta fila sigue siendo cierto y se conserva como historia: cierre de la sesión del
>   27 a las 18:40 con las TANDAS 1, 2 y 3 EMPUJADAS (`#191` · `#198` · `#199`;
>   `specs/menores-a-cargo.md` §9.1/§9.7/§9.8) **y la revisión de las cinco decisiones del owner hecha
>   (`#197`)**. Cierre sobre el árbol final: suite 3062 / 17.694 · JS 724 · Pint ✓ · docs-check ✓ ·
>   build ✓ · `audit-clock` ✓ 12/12 fronteras.
>   ▶ ❗ **POR DÓNDE RETOMA la siguiente sesión de ESTE carril: la TANDA 4 — la asignación de entradas a
>   un menor EN EL EMBUDO** (`[DECIDIDO owner, 2026-08-27]`: «seguimos la tanda 4 en el siguiente chat»).
>   Por dónde: `/arranque-sesion` → `docs/INVARIANTES.md` §1 (PAY-04, PAY-12) y §2 (AFORO-01, AFORO-10)
>   → la spec **§4.6, §4.7, §4.8 con §8.1/§8.2, §4.9, §4.10** y **§9.4** → `specs/checkout-orquestado.md`.
>   Lo que hay que saber ANTES de escribir: **(1)** la asignación la posee Identity con el ítem por id
>   ENTERO (Booking no puede mirar a Identity; `ModuleBoundariesTest`) · **(2)** dos puertas, CERO pasos
>   nuevos en `machine.js` (con sesión en el paso 3 al elegir cantidad; sin ella en el paso 5) ·
>   **(3)** el hueco viaja en la LISTA BLANCA de `cart.js::save()` **manteniendo `v: 1`** (subir
>   `STORAGE_VERSION` purga todas las cestas vivas) y `reconcile` deja la línea sin asignar si el menor
>   se retiró · **(4)** el servidor re-valida la pertenencia de cada id (anti-IDOR, la guarda más
>   importante de la spec, con su mutación) · **(5)** se escribe DESPUÉS de que `OrderCreator` devuelva
>   y FUERA de la transacción de los locks (post-commit, idempotente); si falla, el pedido sigue en pie ·
>   **(6)** toca `OrderCreator`/`CheckoutOrchestrator` → `CRITICAL_RE`: los CINCO escenarios de
>   `purchase:verify-oversell` y `VERIFY_CONC=1` · **(7)** solo entradas, no packs · **(8)** el chunk
>   del cajón tiene 0,59 KiB: se mide y se sube por feature (`#197`·2). Primera unidad recomendada: la
>   tabla + el contrato de escritura post-commit + la re-validación, SIN tocar el cajón; el cajón
>   después, medido. ⚠️ **Antes de la tanda 4 conviene el OJO del owner sobre la zona del cajón**
>   (guion §5.decies), porque la tanda 4 la usa.
>   **Lo que fue esta sesión — TANDA 1** (`#191`, spec **§9**): el núcleo en
>   Identity + la API, **sin firmas de menor todavía** — `dependents` + `DependentRegistry` (solo
>   menores; tope de servidor bajo el lock de la fila del titular) + `GET|POST|DELETE /me/dependents`
>   contra el contrato + `anonymize()`/export/purga de go-live/poda + el tope en Ajustes → «Puerta».
>   34 casos, 7/7 mutaciones muerden, 14 comprobaciones HTTP sobre MySQL con Bearer, BD local migrada.
>   ▶ ✅ **`[DECIDIDO owner, 2026-08-27]` (`#197`) — las cinco decisiones de la spec §9.5, tomadas:**
>   (1) **cadena por (titular, sujeto)** · (2) la zona del cajón se construye, se MIDE y el techo sube
>   por FEATURE · (3) la firma de un menor se conserva N meses tras su 18.º cumpleaños, ajuste propio
>   `waiver.dependent_retention_months` · (4) declarar NO exige correo verificado · (5) a un adulto no
>   se le declara. ▶ ✅ **TANDA 2 EMPUJADA (misma sesión, noche): la FIRMA DEL MENOR** (`#198`, spec
>   **§9.7**): cadena por (titular, sujeto) en `WaiverSigner` —`CRITICAL_RE`: `waiver:verify-chain`
>   REHECHO (mide la idempotencia bajo el lock), 8/16 PASA y **visto FALLAR sin el lock**, `VERIFY_CONC=1`—,
>   la FK `subject_id → dependents` RESTRICT (medida bloqueando un `DELETE` crudo), la identidad del
>   menor copiada en la firma (v3), `POST /me/dependents/{id}/waiver`, `Dependent.waiver`, el PDF y el
>   panel nombrando al menor, la retención del menor desde los 18 en Ajustes. +15 casos, 5/5 mutaciones,
>   sonda HTTP sobre MySQL. NUC-3 CERRADA.
>   ▶ ✅ **TANDA 3 EMPUJADA (misma sesión, noche): la ZONA DEL CAJÓN** (`#199`, spec **§9.8**):
>   `DependentsZone` + `DependentCard` + su store y su módulo plano, la entrada del índice con el icono
>   `users` (nuevo, copiado byte a byte), 17 rótulos ES/EN/FR solo con sesión. **Cero CSS nuevo** y
>   ≤ 40 líneas por componente. **Medido y subido por FEATURE** (`#197`·2): chunk 226,34 → 234,41 KiB
>   (techo 235, quedan 0,59) · payload con sesión 6.668 → 7.602 B (techo 7.700). JS 702 → 724.
>   **Guion en headless 20/20** (`VERIFICACION-E2E-CAJON.md` §5.decies; ⚠️ publica versiones `[E2E-DEP]`
>   en la BD local: v10 y v11 ya existen).
>   ▶ ❗ **POR DÓNDE SIGUE este carril: la TANDA 4 — la asignación en el embudo** (spec §4.7–§4.10 y
>   §9.4): la tabla de Identity con el ítem por id entero, las dos puertas sin paso nuevo, el hueco en
>   la lista blanca de `cart.js::save()` sobre `v: 1` (§8.1/§8.2), la re-validación en servidor y la
>   escritura post-commit fuera del lock. **Toca el checkout**: `OrderCreator`/`CheckoutOrchestrator`
>   están en el `CRITICAL_RE`. **Del owner**: su ✅ en navegador de la zona (guion §5.decies) y los DOS
>   valores de retención en meses (titular y menor), criterio jurídico.
>   **Ficheros de este trabajo** (además de los del carril, abajo): `database/migrations/*dependents*` ·
>   `app/Domain/Identity/{Models/Dependent,Services/DependentRegistry,Services/DependentSettings,Exceptions/Dependent*,Contracts/DependentRemoval}.php`
>   · `app/Http/Controllers/Api/V1/MeDependentsController.php` · `app/Http/Resources/Api/V1/DependentResource.php`
>   · `tests/Feature/Dependents/**` · `tests/Feature/Api/V1/MeDependentsTest.php` ·
>   `tests/Feature/Admin/Settings/DependentsCapSettingTest.php` · `openapi/v1.yaml`.
>   ⚠️ **Y tocó SEIS ficheros COMPARTIDOS, en un punto cada uno** (ya en `origin/main`):
>   `app/Providers/AppServiceProvider.php` (una línea del morphMap) · `app/Domain/Platform/Models/AuditLog.php`
>   (dos acciones) · `app/Filament/Pages/Settings.php` (un campo en «Puerta» + su clave en `MANAGED`) ·
>   `app/Console/Commands/PurgeCustomerData.php` (una línea antes de `users`) · `routes/console.php`
>   (`Dependent` en el `model:prune`) · `lang/{es,en,fr}/api.php` + `lang/es/admin.php` + `validation.php`.
>   ▶ **Para el agente del B**: nada pendiente de ti; si tocas alguno de esos seis, `git pull --rebase`
>   antes.
>   ✅ Lo anterior de este carril: el waiver, de agente, TERMINADO** (`#169` revisión adversarial del subsistema
>   + guion en headless · la **tanda 4** que esa revisión exigía: `#171` anti-bot · `#174` servidor ·
>   `#175` cajón · `#178` casilla del alta manual + casilla OBLIGATORIA en interno · `#179` correo
>   verificado para firmar · `#180` texto del PDF · **`#183` la revisión de la propia tanda, aplicada**
>   —24 confirmados, 0 refutados— con el guion §5.nonies **reescrito con la conducta definitiva y
>   re-recorrido en headless: 111/111 ✓**). El «qué pasó» vive en `00-REFACTOR.md` (Fase 6), en cada
>   decisión y en la spec §9.11/§9.12. Al cerrar, además: el único «PHPUnit notice» de la suite,
>   identificado y retirado, y el `pre-push` corregido para leer también «OK (N tests, M assertions)»
>   (commit `e5df6dd`: sin eso el gate se quedaba ciego con la suite verde).
>   ❗ **Lo que queda del waiver es del OWNER**: su ✅ en navegador (guion §5.nonies, **en local y con las
>   claves de PRUEBA de Turnstile en Ajustes**, receta en §5.bis — el defecto del anti-bot está
>   arreglado desde `#171`), el **texto definitivo** (spec §8.1: publicar la v1 real es irreversible) y
>   el **plazo de retención** (§4.6). Hasta eso, Fase 6 sigue 🟦.
>   ▶ ✅ **`[DECIDIDO owner, 2026-08-27]`: LA SIGUIENTE SESIÓN DE ESTE CARRIL HACE «MENORES A CARGO»**
>   (`specs/menores-a-cargo.md`, C). Por dónde: `/arranque-sesion` → `docs/INVARIANTES.md` §2 (AFORO)
>   → la spec entera **empezando por §8.1 y §8.2** (el mecanismo es la lista blanca de
>   `cart.js::save()`, y subir `STORAGE_VERSION` purga TODAS las cestas vivas) → §4.6 (Booking NO
>   mira a Identity; la asignación la posee Identity) → §4.7 (al elegir cantidad no hay sesión) →
>   hereda **NUC-3** de `DEUDA.md` (la poda con firmas de menor). Es spec-first: la spec está revisada
>   y sin código; la primera tanda se recorta con el owner delante, con número y coste, como el waiver.
>   Ficheros del carril: los de siempre del waiver y el cajón (`resources/js/sidebar/` ·
>   `resources/css/` · `storage/ssr/` · `lang/*/account.php` · `tests/Feature/Sidebar/` ·
>   `tests/Feature/Waiver/` · `app/Domain/Identity/**` · `app/Http/**/Api/V1/**` · `routes/api.php`)
>   más `docs/specs/waiver-probatorio.md` · `docs/specs/menores-a-cargo.md` ·
>   `docs/VERIFICACION-E2E-CAJON.md`.
>   ⚠️ **La BD local de esta máquina**: `waiver.mode = interno`, **v1→v9** publicadas en es/en/fr (las
>   de prueba llevan marcadores `[E2E-…]` en el texto), firmas de cuentas `e2e-waiver-*@jumpweb.test` y
>   `probe-*@jumpweb.test`, y **el anti-bot ENCENDIDO con las claves de prueba de Cloudflare** en
>   `settings` (`security.turnstile_site_key`/`_secret` = `1x000…AA`): es justo lo que necesita el ojo
>   del owner en `/registro`. La migración de `#183` está aplicada aquí; en el portátil, `migrate`
>   tras el pull.
>   ⚠️ **El techo del chunk del cajón** cedió por una FEATURE el 26/08 (221,5 → 226,5 KiB) y otra vez el
>   27 por la noche (226,5 → **235**, la zona de menores, `#199`; medido **234,41 KiB: quedan 0,59**), las
>   dos por decisión del owner. Lo siguiente que entre en el cajón —y
>   «menores a cargo» entra— lo mide `SidebarBundleBudgetTest` y casi seguro obliga a podar o a
>   decidir con el owner ANTES de escribir el componente.
>   ⚠️ Medido: ni `routes/api.php` ni `MeController` están en el `CRITICAL_RE` (son controles
>   NEGATIVOS en `CriticalPathGateTest`); **`WaiverSigner` SÍ** (`#174`): tocarlo exige
>   `waiver:verify-chain` y `VERIFY_CONC=1`.
>
> · **Agente B (el portátil) → SESIÓN CERRADA el 2026-08-27 a las 07:30. ✅ Nada a medias: la
>   extracción 4b ENTERA empujada y verde en seis tandas** (`#182` plan · `#184` A · `#185` B ·
>   `#186` C0 · `#187` C · `#188` D+E · `#189` F+G) **— el desmontaje de `ViewOrder`, de agente,
>   TERMINÓ**: 2.355 líneas y 50 métodos (de 5.280 y 98), sin ninguna orquestación de dinero/aforo;
>   todo vive en `Booking\Services\{OrderItemEditor,ItemEditPricing,OrderItemEventDataWriter,
>   OrderItemCanceller,OrderItemRefunder,ZoneDaySlotLock}` con `ItemActionOutcome`/`ItemRefundRequest`
>   como contratos. **74 mutaciones, 74 muerden; 15 tests nuevos son reglas que la página no podía
>   alcanzar.** Detalle sub-paso a sub-paso y cifras: spec **§9.6.1**.
>   ❗ **Lo que queda es del OWNER: la pasada de NAVEGADOR por las 10 acciones del panel** (spec
>   §6·5) — la suite no ve un formulario de Filament que deje de montarse. Hasta ese ✅ el desmontaje
>   sigue 🟦. ⚠️ En el portátil el panel de un pedido necesita las migraciones del waiver aplicadas
>   (hechas el 26/08 en A0); tras un pull, `migrate:status`.
>   ▶ **Siguiente trabajo de agente en este carril**: ninguno pendiente del desmontaje. Lo que hay
>   abierto está en «Lo que NO depende de nosotros» y en `DEUDA.md`; o lo que el owner diga.
>   Ficheros del carril B, para el reparto: `app/Filament/Resources/Orders/**` (⚠️ `#174` del waiver
>   entró en `Schemas/OrderInfolist.php`: es del A) · `app/Domain/Booking/Services/{OrderItemEditor,
>   ItemEditPricing,OrderItemEventDataWriter,OrderItemCanceller,OrderItemRefunder,ZoneDaySlotLock}.php`
>   · `app/Domain/Booking/Contracts/{ItemActionOutcome,ItemRefundRequest}.php` ·
>   `app/Console/Commands/VerifyPurchaseConcurrency.php` · `tests/Feature/Admin/Orders/**` ·
>   `tests/Feature/Architecture/{CriticalPathGateTest,OrderItemEditorSingleLockPointTest}.php` ·
>   `docs/specs/desmontar-view-order.md`.
>   ▶ **Para el agente del A**: `#174` entró en `app/Filament/Resources/Orders/Schemas/OrderInfolist.php`
>   (directorio del B). No choca con la 4b —no toco `Schemas/`—; sigue limitándote a ese fichero ahí.
>   Las 3 migraciones del waiver estaban PENDIENTES en el portátil (aplicadas en A0): no es un choque,
>   es esta máquina, que no había pasado por ellas.
>   Estado al retomar: el desmontaje de `ViewOrder` estaba **a UN paso del final**: paso 0 (`#170`)
>   · extracción 1 (`#172`, calendario→trait) · 2 (`#173`, presentación→trait) · 3 (`#176`, la
>   oferta de re-programación al DOMINIO con las tres reglas del owner y `VERIFY_CONC` en verde) ·
>   el instrumento de la 4 (`#177`, `--scenario=panel-edit`, **visto fallar**: 2 donde cabía 1).
>   `ViewOrder` en **3.907 líneas** (de 5.280, −26 %) y ya no compone ninguna oferta.
>   ▶ ❗ **POR DÓNDE RETOMA la siguiente sesión de ESTE carril (el portátil): la extracción 4b** —
>   mover la orquestación del dinero. **El handoff está sub-paso a sub-paso en la spec §9.5**
>   (orden A→H por riesgo creciente: puros → event_data → cambio de franja con el instrumento
>   corriendo → cancelación → refund batch → `executeItemEdit` el último → fronteras que no cambian
>   → `CRITICAL_RE` + verificadores + navegador del owner). Empezar por §4.3 (el mapa transaccional
>   REAL) y §9.5; commitear en local ANTES de cada mutación (§9.1).
>   ✅ **`[DECIDIDO owner]` (`#181`): con la 4b el desmontaje TERMINA** — las ~2.200 líneas de
>   composición Filament que queden NO se parten (§4.4 cerrada).
>   ❗ **La revisión adversarial del WAIVER NO es de este carril**: la lleva el A (fila de arriba),
>   junto con el guion headless — no se empieza dos veces (`CONVENCIONES §10·7`).
>   Ficheros del carril B: `app/Filament/Resources/Orders/**` (`ViewOrder.php` y lo que se extraiga
>   de él) · los ficheros de test que conducen `ViewOrder` (spec §1.5) ·
>   `docs/specs/desmontar-view-order.md` · `docs/DEUDA.md` (su ficha) · `docs/DECISIONES.md`
>   (número al empujar). ⚠️ El paso 3 entra en el `CRITICAL_RE`
>   (`SlotOffer`/`SlotAvailability`/`PackAvailability`): `VERIFY_CONC=1`.
>   ▶ Del cierre anterior de este carril sigue vigente: al cerrar una tanda que toque fixtures con
>   calendario, `bash scripts/audit-clock.sh` (está en `/cierre-sesion`; NO en el `pre-push`) — la
>   primera pasada cazó un fixture que iba a tumbar el gate de los DOS agentes seis días después.
> · 🆕 **Agente C (el TEMA) → SESIÓN DEL 2026-08-28 CERRADA con CUATRO tandas empujadas y verdes**:
>   `#209` el quinto mecanismo (el relleno de ACCIÓN) · `#211` el menú se puede CERRAR y siempre
>   ofrece comprar, más el hueco del ICONO de instalación · `#213` el CTA cambia de rol dentro del
>   menú (tinta fuera, AVISO dentro) · `#214` el CTA es un PAR con invitación.
>   ▶ **Ficheros de este carril, actualizados**: `public/css/{site,landing}.css` ·
>   `resources/views/components/site/{nav,menu,mobile-book-bar,favicon}.blade.php` ·
>   `resources/views/components/{layout,focused-layout}.blade.php` · `resources/js/app.js` (el store
>   `ctaPair`) · `app/Domain/Content/Services/ThemeSettings.php` · `tests/Feature/Theme/**` ·
>   `tests/Feature/Site/ArmazonContractTest.php` · `.gitignore` · `scripts/deploy.sh`.
>   ⚠️ **Compartidos con el carril A, tocados en un punto cada uno**: `app/Filament/Pages/Settings.php`
>   (un campo + su clave del `MANAGED`), `lang/es/admin.php` (dos claves), `lang/{es,en,fr}/landing.php`
>   (el rótulo del CTA), `tests/Feature/HomePageTest.php`, `tests/Feature/Sidebar/SidebarMountTest.php`
>   y `tests/Feature/Site/CustomerAccountContextTest.php` (tres tests RE-APUNTADOS, ninguno retirado).
>   **Si los tocas, `git pull --rebase` antes.**
>   ⚠️ **Esta máquina es una INSTALACIÓN**: `public/css/client.css`, `theme.brand`/`theme.brand_secondary`/
>   **`theme.action = #F2711C`** en la BD local y `THEME_FONTS` en el `.env`. En otra máquina nada de eso
>   existe y la web se ve con el tema del producto: **es lo correcto, no un fallo**.
>   Antes, en la misma jornada: el QUINTO mecanismo, el relleno de ACCIÓN, EMPUJADO**
>   (`#209`, `specs/tema-por-instalacion.md` **§15**). Con él **la capa de tema tiene sus CINCO
>   mecanismos** y la landing ya puede ser 1:1 con el mockup en color. El botón de comprar de esta
>   máquina **ya sale en el naranja del cliente**, dentro y fuera del menú de tinta.
>   ▶ **Ficheros de esta tanda**: `public/css/{landing,site}.css` ·
>   `app/Domain/Content/Services/ThemeSettings.php` · `app/Filament/Pages/Settings.php` ⚠️ (compartido
>   con el carril A: **un campo y una clave del `MANAGED`**) · `lang/es/admin.php` ⚠️ (compartido:
>   **dos claves**, junto a `theme_brand_secondary`) · `tests/Feature/Theme/ActionFillTest.php` (nuevo)
>   · `tests/Feature/Architecture/SurfaceScopeTest.php`.
>   ▶ **Para el carril A**: si tocas `Settings.php` o `lang/es/admin.php`, `git pull --rebase` antes.
>   Esta tanda **no toca** `resources/js/**`, `app/Domain/Identity/**` ni `app/Filament/Resources/**`.
>   ⚠️ **Y esta máquina tiene ahora `theme.action = #F2711C` en su BD local** (es una INSTALACIÓN,
>   como ya lo era por `client.css` y `THEME_FONTS`). En otra máquina el ajuste no existe y el botón
>   se ve en tinta: **es lo correcto**, no un fallo.
>   Antes: **tandas 1, 2a, 2b y la ELEVACIÓN, empujadas el 2026-08-27 (`#192`, `#193`, `#194`, `#195`).**
>   Hay un **TERCER carril**, por encargo del owner: la capa de tema
>   (`specs/tema-por-instalacion.md`). La tanda 1 —las dos superficies como ámbito, `--sheet`, los dos
>   grises, los tintes, 21 radios y las fuentes por instalación— está hecha, verificada y en `main`.
>   La **2a** —la escala de canto, la ley del motivo cuadrado, el anillo de foco y la tira del pie—
>   también (§10 de la spec).
>   ▶ **Ficheros de este carril**: `public/css/*.css` · `resources/views/components/layout.blade.php`
>   y `focused-layout.blade.php` · `resources/views/components/site/footer.blade.php` ·
>   `resources/views/errors/maintenance.blade.php` ·
>   `config/theme.php` · `app/Domain/Content/Services/ThemeFonts.php` ·
>   `tests/Feature/Architecture/{SurfaceScopeTest,ShapeScaleTest}.php` · `tests/Feature/Theme/**` ·
>   `docs/specs/tema-por-instalacion.md` · `docs/INSTALACION-CLIENTE.md`.
>
>   ❗❗ **LO MÁS IMPORTANTE QUE ESTE CARRIL APRENDIÓ EL 27, y afecta a cualquiera que toque el canvas:**
>   **la copia local de `mockup_playjumppark/` CADUCA sin avisar.** La del 27 a las 07:30 ya no valía
>   a las 11:00: `Landing PJP Modos` con **386 líneas de diff** (el hero gana una tira de colores; la
>   sección de entradas pierde su fondo cian y sus goterones) y `Colores de Marca PJP` también movido.
>   ▶ **`DesignSync · list_files` + diff ANTES de implementar nada desde ahí.** Una copia vieja se lee
>   igual de bien que una fresca.
>
>   ❗❗ **Y la premisa de la spec del tema §1.2 CADUCÓ**: la regla «el sistema alterna dos superficies,
>   nunca dos papeles seguidos» **ya no existe**. El hallazgo `S-00` del owner —severidad Alta,
>   Aplicado— la sustituye por **papel continuo de arriba abajo, y el contraste lo dan las TARJETAS**.
>   Verificado en el canvas: **cero** fondos a sangre en los 218 KB del mockup (antes había tres).
>   ▶ **Esto NO tira la tanda 1: la hace más útil.** `[data-surface]` es un selector de atributo, no
>   está atado a `.section`, así que sirve igual para una tarjeta oscura dentro de una sección clara.
>
>   ⚠️ **Y una que los otros dos carriles tienen que saber**: la escala de radios GANÓ dos escalones
>   (`--r-xs: 5px`, `--r-md: 10px`) y `ShapeScaleTest` **prohíbe escribir un canto en literal**. Si tu
>   tanda mete un `border-radius: 12px` a mano, el gate muerde. Usa un token, o justifica en qué
>   familia cae (motivo cuadrado / dibujo) — **las dos listas solo encogen**.
>
> ❗❗❗ **PARA LOS TRES CARRILES, Y ES LO MÁS IMPORTANTE DE ESTA SESIÓN: `docs-check.sh` PUEDE DARTE
> UN FALSO VERDE.** Medido el 2026-08-27: el gate daba **✓** en la shell del agente y **✗** con el
> `grep` real, y así se coló en `origin/main` una doc rota desde el **26/08** —37 citas
> `fichero.php:línea` que `CONVENCIONES §4` prohíbe—. La causa: **en la shell de trabajo `grep` es
> una FUNCIÓN de bash** interpuesta por el harness, no `/usr/bin/grep` (`declare -F grep` lo
> confirma). El `pre-push` **sí** usa el binario, así que el gate acaba mordiendo — pero te muerde
> cuando ya has hecho el trabajo, y por deuda que puede no ser tuya.
> ▶ **La regla, hasta que se arregle**: `docs-check` **solo cuenta con el binario real** —
> `env -i PATH=/usr/bin:/bin bash scripts/docs-check.sh` —, o déjaselo al `pre-push`.
> ▶ **Las 37 citas ya están convertidas a símbolo** (`#193`, con permiso del owner: son ficheros del
> carril A). ⏳ **Lo que sigue abierto es que el gate pueda mentir**, y tiene ficha **Alta** en
> `DEUDA.md`. ▶ Es el **quinto** instrumento ciego de este repo y **el primero que era el propio
> GATE**: los otros cuatro daban un inventario incompleto; éste daba **permiso**.
>
>   ❗❗ **TRES cosas que los otros carriles necesitan saber, porque tocan terreno compartido:**
>   1. ⚠️ **`SidebarTokenBudgetTest::MAX_RAW_COLOURS` bajó de 5 a 3.** El cajón comparte `site.css`,
>      así que si tu tanda mete un color crudo ahí, el trinquete muerde antes que antes. Los alfa
>      salen con `color-mix(in srgb, var(--fg) X%, transparent)`, y el blanco de una tarjeta ya tiene
>      token: **`--sheet`**.
>   2. ⚠️ **`--line`/`--line-strong` ya NO son `rgba(20,19,15,α)`**: derivan de `--fg`. Si copias una
>      línea de otro sitio, cópiala con `var()`, no con el literal — `RawColourIsNotATokenTest` lo caza.
>   3. ⚠️ **Existe `[data-surface="ink"|"paper"]`** y re-escopa siete tokens. **Todavía no lo usa
>      ninguna sección** (eso es la tanda 2), pero si tu componente pinta un color de superficie a
>      mano en vez de por token, dejará de seguir a su sección el día que la haya.
>
>   ⚠️ **Y una que despista**: `mockup_playjumppark/` (el canvas del 2.º cliente) está **gitignorada y
>   excluida del `rsync`** (`DECISIONES #1`), así que **en tu máquina no existe** aunque la doc la
>   cite. La receta para regenerarla con el MCP `DesignSync` está en la **§1 de la spec del tema**.
>   ❗❗ **El armazón está COMPLETO salvo el MENÚ EN MÓVIL**, que espera el artboard del owner. El
>   **CTA doble** de la barra inferior ya está (`#205`), y con él el idioma salió del pie —con
>   `<noscript>` de suelo— y el menú ganó su eslogan. Detalle de escritorio: 2c·0 (`#200`), 2c·1 (`#201`), 2c·2 (`#203`) y
>   2c·3 (`#204`) en el árbol y verdes — la red, 66 reglas de CSS fuera, **el menú a pantalla
>   completa**, **la barra DISUELTA en dos racimos**, el salto al contenido en las 12 y la cuenta en
>   icono con su punto de aviso. ▶ **Lo que queda de la 2c es la 2c·4, el MÓVIL, y la bloquea el
>   ARTBOARD DEL OWNER**: hasta que exista, el cajón lateral sigue intacto por debajo de 1080 px.
>   ▶ **Siguiente de agente en este carril si el owner no sube el artboard**: la tanda **2d, el
>   MOVIMIENTO** (237 declaraciones, 48 duraciones, 20 curvas; el sistema declara 7 y 4).
>   ▶ **Contestación al carril A (2026-08-27, noche)**: recibido el aviso de la tanda 4. **La 2c·2 NO
>   ha tocado `layout.blade.php`** —ni lo tocará la 2c·3—, así que no hay choque. Lo que sí ha
>   cambiado el carril C y conviene que sepas: `nav.blade.php` y `menu.blade.php` (el armazón entero),
>   `public/css/{site,landing}.css`, `resources/js/app.js` (en el componente `landing`, **`mobileOpen`
>   se llama ahora `menuOpen` y `trapMobile`, `trapMenu`**) y **las 12 vistas públicas, que ganan
>   `id="main"`**. Si tu U2 toca alguna de esas, `git pull --rebase` antes.
>   ▶ Contexto de aquella spec, que **YA ESTABA ESCRITA**:
>   **`specs/armazon-y-menu.md`** (2026-08-27 por la tarde), con **cuatro decisiones del owner
>   tomadas** y **seis pendientes**. `[DECIDIDO owner]`: la barra fija se retira en las **12** vistas
>   y la sustituyen **dos racimos flotantes + un menú a pantalla completa**; la lista del menú es
>   **PLANA** y la sigue mandando la BD; con sesión, icono de cuenta con **punto naranja/verde**;
>   **el MÓVIL lo guía el owner con un artboard** y hasta que exista no se empieza la 2c·4.
>   ▶ Medido: **194 reglas distintas · 733 declaraciones** de CSS en 7 familias, **31 aserciones**
>   en 4 ficheros, y **33 reglas (el 17 %) MUERTAS**. ⚠️ **La primera cifra publicada —«232 · 819»,
>   «41 muertas»— era FALSA y se corrigió al ejecutar**: sumaba COINCIDENCIAS, no reglas (una regla
>   que cita dos familias contaba dos veces). ✅ **La tanda 2c·0 está HECHA**: no cambia un píxel —
>   construye la red que el cajón móvil no tenía y retira las 33 reglas muertas. ⚠️ **Y el menú del mockup
>   trae tres defectos que se importan solos** (spec §1.7): deja sus enlaces en el orden de
>   tabulación estando cerrado, y el hallazgo `M-05` del propio cliente **choca con `--shadow-float`,
>   que `#196` creó ese mismo día**. ⚠️ **La auditoría del cliente EXCLUYE el menú y el logotipo**:
>   es la misma trampa que con el hero. Necesita el OJO del owner, no solo el gate.
> ▶ Protocolo de los carriles: **`CONVENCIONES §10`**.
> ⚠️⚠️ **El número de `DECISIONES.md` se elige mirando el REMOTO, y NO BASTA con mirarlo al empezar.**
> Ha colisionado **NUEVE** veces en dos días: `#142` duplicado · `#148` (el agente A renumeró al
> fusionar) · los del agente B, que fueron `#149`/`#150` → `#152`/`#153` → `#154` → **`#156`/`#157`**
> porque el agente A empujó **seis veces** mientras se escribían · `#158`, tomado mientras se escribía
> esa tanda · y el 2026-08-26 otra vez: `#163` se lo llevó la tanda 3a del waiver y el agente B tuvo
> que renumerar a `#164` **con el número ya escrito dentro de dos ficheros**.
> ❗ **La regla, corregida por el precio pagado**: el número **no se fija al escribir, se fija al
> EMPUJAR** — se vuelve a mirar el remoto justo antes del push. **Corolario: empujar PRONTO.**
> ⚠️ **Y la DÉCIMA, el 26/08 por la tarde, con la regla aplicada**: el carril A miró el remoto (`#167`),
> escribió `#168`, y en los veinte minutos hasta el push el portátil empujó SU `#168`. El rebase chocó
> en `ESTADO.md` y `DECISIONES.md` y hubo que renumerar a `#169` en ocho ficheros. ▶ Lo que ahorra
> tiempo: renumerar **solo las líneas AÑADIDAS** (`git diff HEAD` filtra las del otro) con un script de
> diez líneas, y **no confiar en «El último usado»** del fichero: mirar `origin/main` en el mismo
> comando que empuja.
> ❗❗ **Y el 2026-08-25 el precio dejó de ser solo el número**: los DOS agentes arreglaron **el mismo
> defecto** (el desglose EN/FR en crudo) **en paralelo, sin saberlo**. Se salvó la mitad que no
> coincidía —la guarda— y se tiró el resto. **Antes de abrir una ficha de `DEUDA.md`, mira si el otro
> la tiene abierta**: el reparto por carriles no basta cuando una ficha cae en la frontera.
> El último usado es **`#214`**.
> ⚠️ **Y el `#207` volvió a demostrar la regla el 2026-08-28**: el carril C escribió su tanda con
> `#207` en cinco sitios y, al ir a empujar, el carril A ya se había llevado **`#207` Y `#208`**.
> Renumerar costó cinco ediciones. ▶ **Y además `#207` está OCUPADO por la base heredada** («Fase
> 7.7 · Temporadas», seis ficheros): al buscarlo salen dos cosas distintas con el mismo número.
>
> ❗ **LO PRIMERO que es de DINERO: los CUATRO defectos del cambio de precio (`#146`) están
> CERRADOS** (`#149`, `#150`) y el pack CON señal quedó MEDIDO. La peor ficha derivada —el pedido
> CANCELADO sin vía de reembolso— está **CERRADA** (`#152`, owner: la línea se abre para
> cancelados con deuda), y «¿devolver en el parque?» **DECIDIDO** (modo manual, sin canal nuevo).
> Quedan TRES fichas menores en `DEUDA.md`. Punto **0.bis**.
>
> ✅ **Lo segundo quedó DECIDIDO por el owner** (`#151`): el consumo que `#148` midió —una fiesta
> consume plazas de su franja— **es CORRECTO**. La regla: **la independencia de cupos se hace POR
> ZONA** (cumpleaños en la suya; el futuro «excursiones de colegio» tendrá la suya). Detalle en
> el punto **1.bis** de «Lo que está ABIERTO».
>
> ▶ **Landing y TEMA — son dos cosas distintas y esta línea las mezclaba.**
> · **El TEMA ya no está parado**: `specs/tema-por-instalacion.md`, **tanda 1 en `main`** (`#192`).
>   El sistema visual del 2.º cliente está **cerrado y auditado** por el owner; lo que él sigue
>   terminando (27/08 por la noche) es el diseño **de las secciones** y el mockup del layout de una
>   página nueva — o sea, lo que necesita la tanda **3**, no la 2.
> · **El CONTENIDO sí sigue parado**: tanda B de `landing-white-label.md` (`testimonials`, el copy al
>   CMS). Su tanda A está cerrada (`#138`→`#143`).
> ⚠️ **«Lo medido del mockup viejo sobre COLOR está caducado» sigue siendo cierto, pero se quedó
> corto**: medido el 27/08, son **10 de los 18 artboards** los que están sin migrar —entre ellos los
> de logotipo, menú y hero—, y **los mismos elementos están rehechos con el sistema vigente dentro de
> `Landing PJP Modos`**. De los sin migrar se saca la FORMA, nunca el color (spec del tema §1).
> Redis ya no bloquea nada (`#137`) y el desglose de dinero está CERRADO (`#127`→`#134`) — **cerrado
> el DESGLOSE, no el cambio de precio: eso es `#146`**.
>
> ⚠️ **Y este documento ADELGAZÓ el 2026-08-25, de 824 líneas a menos de la mitad.** Se retiró el
> índice de la Fase 4 —83 líneas que duplicaban el tracker de una fase CERRADA—, se movió el mapa del
> cajón a `specs/sidebar-spa.md` §8 y se borró el histórico de deltas de tests, que ya vive en cada
> `DECISIONES`. `CONVENCIONES` dice que esta foto **resume** el tracker y nunca lo contradice: con 824
> líneas eso era imposible de sostener, y de hecho **había contradicciones vivas** —afirmaba que la
> Fase 5 seguía bloqueada por falta de Redis horas después de haberlo activado y verificado—.

## ▶ Dónde estamos

**Fase 0 ✅ · 1 ✅ · 2 ✅ · 3 (API v1) ✅ · 4 (sidebar SPA) ✅ · 6 🟦 (el waiver: CÓDIGO COMPLETO, revisado
dos veces y con su guion recorrido en headless con la conducta definitiva —`#183`—; espera SOLO al owner ·
**menores a cargo: tanda 1 en el árbol, `#191`**; la 2 espera cinco decisiones del owner, spec §9.5)** — el detalle paso a paso de cada una está en `00-REFACTOR.md`, que es el tracker. Aquí
solo la foto.

🟩 **CERRADO y sin nada pendiente:** el cajón SPA como motor único con `Purchase.php` retirado
(`#111`, `#112`) · el área de cliente entera, incluidas la auth y `account-context` (`#66`, `#120`,
`#122`, `#123`) · «Mis reservas» por reserva (`#126`) · **el desglose de dinero del cliente**, las tres
tandas y los cuatro defectos de lectura (`#127`→`#134`, `specs/desglose-dinero-cliente.md`).
⚠️ **No se resume aquí lo que pasó en cada uno**: está en el tracker y en su decisión. Repetirlo en la
foto viva es crear una segunda verdad que envejece sola — ya pasó con la revisión de staging y con el
contador de tests JS.

🟦 **EN CURSO: la landing white-label** (`specs/landing-white-label.md`, `#136`). Nace del mockup del
**segundo cliente**. La línea: **data-driven el DATO, no la PÁGINA**.
▶ ✅ **Tanda A CERRADA** (`#138`→`#143`): el acento de zona ya no viaja por el nombre de la clase, la
paleta del primer cliente sale del producto, el icono por producto ya no es un booleano, **los 144
literales que repetían un token existente pasan a `var()`/`color-mix`**, el **spinner es sustituible
por instalación** y —lo que faltaba para que todo lo anterior sirviera— **existe el hueco por donde
entra el paquete de tema del cliente**.
⚠️ **Lo que la tanda A enseñó y no se puede no saber**: tres afirmaciones de la propia spec eran
falsas y las tres se creyeron hasta medirlas. La última (`#143` §8): **el «76 colores en crudo» salió
de un `grep` línea a línea que no veía ni los `rgba()` ni los valores multilínea — y el primer
instrumento que se escribió para corregirlo tenía EL MISMO defecto.** Eran 234, y en las 12 que
faltaban estaban las dos fugas de marca del hero.
▶ 🟦 **Tanda B EN CURSO (1 de 4)**: el «0 m²» hecho (`#144`); `park_stats` **descartada con su medida**
(`[DECIDIDO owner]`), y quedan `testimonials`, el copy y la landing del 2º cliente.
⏸️ **Y B está PARADA a la espera del DISEÑO**: el owner rehace el sistema visual. Ver «Próximo paso».
▶ **C** (servicios como producto real) sin empezar. ⚠️ **Toca AFORO y PAY y necesita spec propia.**
⏸️ **APARCADO por el owner**: zonas y cupos se quedan como están hasta ver cómo se comportan las
reservas de packs distintos con gente real (`#139`).

✅ **Redis: requisito DURO y solo para CACHÉ** (`#137`), activado y verificado en staging. Sus dos
obligaciones —el pase de Redsys fuera de la caché y Redis en el stack local y en la suite— están
**hechas**. Detalle de la máquina en `ENTORNOS.md` §4.

✅ **STAGING SIRVE `7776370`** desde el 2026-08-25 (cierre del agente A) — trae la línea entera del
cambio de precio (`#149`→`#155`: el importe elegido en «Reembolsar», la reconstrucción por precio
original, cancelados con deuda reembolsables, etiquetas EN/FR y el email de la bajada) más el
verificador de aforo de 5 escenarios del agente B. **Sin migraciones nuevas** («Nothing to
migrate» — las tres de la ola anterior ya estaban).
Auto-verificado por el script: `/up` y `/` en 200, guarda del `robots.txt`,
`redsys_environment = 'test'`, 0 migraciones pendientes, 0 `failed_jobs`, 0 jobs varados, 5 tareas
registradas y 1.420 franjas. Canal: `scripts/deploy.sh`, dry-run por defecto.
⚠️ **Lo de esta ola es PANEL y EMAIL**: no hay nada nuevo que ver en la web pública de staging; el
modal nuevo de «Reembolsar» se prueba con login de admin (y la BD de staging tiene sus 6 pedidos
de siempre, no las sondas locales).
▶ El histórico del salto anterior (`6437c48`, con `#145` verificado sobre `R-S9XDYB`) queda en
`DECISIONES` y en el tracker.
⚠️ **Lo que hay que recordar del canal**: los assets se construyen AQUÍ y se suben compilados —en
staging no hay node/npm— y el `.env` **nunca viaja**: se lee y se valida.
⚠️ **El único aviso del despliegue**: el script no ve ningún demonio cron, así que el crontab instalado
puede no ejecutarse nunca. Se comprueba en el panel del hosting (`#115`). No es nuevo.
❗ **LA REGLA QUE ESTA LÍNEA PAGÓ DOS VECES: la revisión NO se copia, se MIDE.** Llegó a decir «no hay
diferencia de código» con 68 ficheros de diferencia. Antes de creerte lo de arriba:
`git log --oneline e551851..HEAD` y
`git diff --stat e551851..HEAD -- . ':(exclude)docs' ':(exclude)*.md'`, sustituyendo `e551851` por lo
que sirva staging de verdad.

- **El único «PHPUnit notice» de la suite está IDENTIFICADO y RETIRADO** (cierre del carril A, 2026-08-27):
  `RequiresStaffOrAdminTest::test_can_access_panel_mirrors_middleware` hacía `createMock(Panel::class)`
  sin expectativas y PHPUnit 12 lo avisa; es `createStub`. Se encontró bisecando por carpetas con
  `artisan test --parallel <ruta>` (⚠️ con una ruta el resumen es el de PHPUnit, «OK (N tests…)», no
  «Tests: N») y se confirmó con `vendor/bin/phpunit --display-all-issues` (`--display-notices` NO enseña
  los notices de PHPUnit: son `--display-phpunit-notices`). El carril B ya no tiene que hacerlo en A0.
  ⚠️ **Y retirarlo dejó CIEGO al `pre-push`**: PHPUnit resume «Tests: N, Assertions: M…» solo cuando hay
  issues y «OK (N tests, M assertions)» cuando no; el hook solo entendía la primera forma y llevaba
  meses leyendo el contador gracias al notice. Desde este cierre lee las dos (fail-closed intacto).
- **JS 877.** ⚠️ El contador de la suite es el de arriba y **es el único**: el `pre-push` lee la
  PRIMERA línea «Suite **N en verde**» de este documento, así que una segunda copia aquí abajo no es
  redundancia, es una mentira que el gate no ve. ▶ Lo que puso el carril del tema el 2026-08-31:
  **+17 casos** —la guarda de huérfanas de fachada (6), la de decoración por pantalla (5), el caso
  del kit sin ese dibujo (1) y `TagSystemTest` (5, el trinquete contra la erosión de las etiquetas)—.
  ▶ Antes, `#286` sobre el árbol CONJUNTO, tras rebasar el hueco de ilustración encima de
  `#282`–`#285` del carril de mixtos: **3593 · 23.394**, con **+46 casos** en dos ficheros:
  `IllustrationKitTest` (el contrato del kit: seguridad, atributos de presentación en raíz **y en
  descendientes**, gramática cerrada, las tres piezas de despliegue) e `IllustrationHoleRenderTest`
  (lo que se pinta: sin paquete no se emite nada, el troquel fija `fill` **y** `color`, el grosor
  sale del símbolo y no de una constante, y el CSS lee un token que RESUELVE).
  **24 mutaciones y las 24 muerden**, con control verde antes y después de cada pasada.
  ⚠️⚠️ **Una guarda mía nació CIEGA y lo dijo la mutación, no la lectura**: pasaba en verde con
  `color: transparent` borrado, porque **el comentario de esa misma regla CSS** contiene ese texto y
  la aserción casaba con la prosa. Desde entonces `block()` retira los comentarios antes de aseverar.
  ⚠️ Y **otra reventó con el producto SANO** al vaciarse la lista de ranuras: aseveraba `SLOTS[0]` y
  «la lista no está vacía». *Una guarda atada a cuántas cosas hay hoy vigila el inventario, no la
  regla.*
- Suite **3547 en verde** (23.247 aserciones, 1 skipped a propósito) · **JS 877**, medida el
  2026-08-31 tras `#283` (el AVISO antes de mover un tramo). ▶ **+7 casos**: el impacto en las dos
  direcciones, la fiesta ya celebrada que no cuenta, el cambio que no mueve a nadie y no
  interrumpe, retirar la familia, y la confirmación en dos guardados con su firma.
  **5 mutaciones y las 5 muerden.** ⚠️ Una guarda **nació ciega** y lo dijo la mutación.
- Suite **3540 en verde** (23.231 aserciones, 1 skipped a propósito) · **JS 877**, medida el
  2026-08-30 **sobre el árbol CONJUNTO**, tras rebasar `#282` (carril A: cada esquema de campos
  declara SUS tipos) encima de `#281` del carril C. ▶ **+4 casos de mi lado**: tres de conducta —la
  EDAD se descarta en los datos del evento por las DOS puertas de saneo y se conserva en los del
  invitado— y **uno que ata la lista del dominio al `enum` del contrato de la API, por los dos
  lados**. **4 mutaciones y las 4 muerden.**
  ✅ **Y el contador ya NO depende de la máquina**: el carril C arregló los dos casos de
  `InlineBrandLogoTest` que lo hacían oscilar (gracias — aviso retirado de arriba, §10·4).
- Suite **3536 en verde** (23.225 aserciones, 1 skipped a propósito) · **JS 877**, medida el
  2026-08-30 tras `#280`. ▶ **+5 casos PHP y +13 JS**: `HeroSwitchRestsOnTest`, que vigila el
  MECANISMO del interruptor —lee los `@keyframes` reales, calcula dónde cae el corte y comprueba que
  ahí el valor es **constante** y **coincide con el reposo declarado**—, y `hero-switch.test.js`.
  **14 mutaciones y las 14 muerden** (8 en el CSS, 6 en el JS).
  ⚠️ La guarda **nació ROJA con el producto sano**: buscaba `selector … {` con un `[^{]*` en medio,
  así que ante una lista `a,\n b,\n c { … }` se tragaba las tres y devolvía **una**.
- Antes, tras `#278`: suite **3531** (23.179 aserciones) · **JS 864**. ▶ **+2 casos**: el desenlace
  (dos piezas, sin confeti) y **la hora COMPLETA en el contrato de árbol** — que `#277` había dejado
  sin cubrir porque **ninguna fixture tenía una franja llena**. **4 mutaciones y las 4 muerden**, y
  una es que el confeti no puede volver.
  ⚠️ La baseline de `PurchaseSection.vue` **BAJA** a 425 y se aprieta en el mismo commit.
- Antes, tras `#277`: suite **3529 en verde** (23.116 aserciones) · **JS 864**. ▶ **+1 caso** (`the_slot_cascade_keeps_its_contract`) y **+2 en JS**
  (`offer.test.js`: la franja no vendible, y que **la ausencia del campo no es «completa»**) —
  **4 mutaciones y las 4 muerden**, y la del desfase literal muerde además la guarda de duraciones.
- Antes, tras `#276`: suite **3528 en verde** (23.073 aserciones) · **JS 862**. ▶ **+1 caso** (`what_the_floating_bar_takes_is_declared_once`) y una
  aserción nueva en `HomePageTest` (el eslogan y el titular comparten caja) — **4 mutaciones y las 4
  muerden**. ⚠️ Una de esas guardas **nació LAXA**: con `.*?` no mordía al colar un elemento entre
  los dos textos, porque el comodín perezoso retrocede hasta el `</span>` del intruso.
- Antes, tras `#275`: suite **3527 en verde** (23.053 aserciones). ▶ **+10 casos**: `BrandLogoRepairTest`, las dos reparaciones del logotipo
  exportado (la extrusión sin trazo · el velo de dentro y su tono por palabra · la letra restituida en
  sus DOS apariciones · que rehúse media reparación · idempotencia de los dos guiones · y las tres
  piezas del patrón de marca para la letra) — **6 mutaciones y las 6 muerden**.
  ⚠️ Y una séptima **NO muerde**, por un motivo que hay que saber: quitar solo el `exit(1)` de la
  comprobación del relleno deja la guarda verde **porque el guion tiene DOS capas de defensa**.
  Retirando el bloque entero, muerde. *Una mutación que no muerde puede ser una mutación DÉBIL.*
- Antes, tras `#274`: suite **3517 en verde** (23.020 aserciones, 1 skipped). ▶ **+1 caso**: que el
  alto de reposo del hero sea **una sola fórmula con su par `svh`** — 2 mutaciones, las 2 muerden.
- Antes, tras estabilizar el contador: suite **3516 en verde** (23.019 aserciones, 1 skipped). ▶ **El contador vuelve a ser ESTABLE entre máquinas**, que es
  lo que el carril A pidió al ver que el gate hacía ping-pong: los **dos** casos de
  `InlineBrandLogoTest` que dependían del paquete de marca ya no dependen.
  · el de la anatomía del SVG vigila ahora **la lista que `INSTALACION-CLIENTE.md` EXIGE** al
    paquete, no el fichero de un cliente — *un caso que solo corre donde hay un fichero privado no
    es una guarda del producto, es una guarda de una instalación*;
  · el del logotipo en línea **corre siempre y comprueba las DOS conductas** con el mismo número de
    aserciones: con paquete, que se sirve en línea con su nombre accesible; sin él, que la plantilla
    cae a su **suelo de texto** — la otra mitad del contrato, que no vigilaba nadie.
  ▶ Verificado moviendo el logotipo de sitio: **59 aserciones con paquete y sin él**.
  ⚠️ *Un `markTestSkipped` no es gratis cuando el gate cuenta aserciones.*
- Antes (la foto del carril A, con el contador aún dependiente de la máquina):
  ⚠️⚠️ **el contador de aserciones DEPENDÍA DE LA MÁQUINA y por eso el gate hacía ping-pong.** `InlineBrandLogoTest` tiene **dos** casos que solo corren donde está instalado
- Antes, tras `#267`: suite **3501 en verde** (22.978 aserciones), medida el
  2026-08-30 **sobre el árbol CONJUNTO**, tras rebasar `#273` (el logotipo sin sombra CSS) encima de
  las cinco tandas del carril A (`#249` · `#268`→`#272`). ▶ De mi lado, mismo número de casos que
  `#267` y **−4 aserciones**: la guarda de la sombra cambia de sujeto —ya no asevera cinco números del mockup,
  asevera que el logotipo no lleve `drop-shadow`, con control positivo— y `ArmazonContractTest`
  pierde la exigencia CONTRARIA, que es la que puso la suite en rojo al aplicar la decisión.
  ⚠️ **Ese rojo es la señal de que la decisión estaba bien tomada**: dos guardas no pueden exigir lo
  contrario la una de la otra.
- Antes, tras las cinco tandas del carril A: suite **3516 en verde** (23.001 aserciones, **3 skipped**) · **JS 862**, medida el 2026-08-29 por
  la tarde **sobre el árbol CONJUNTO**, tras la revisión adversarial del cumpleaños mixto y sus cinco
  tandas (`#249` · `#268`→`#272`) rebasadas sobre el `#267` del carril C.
  ▶ **+15 casos**: 14 en `MixedPartySurchargeTest` —las cuatro formas de ausencia, el recibo, el
  «14,00 € y no 24,00 €», los dos controles de que el día y el pack SÍ re-tarifican, la línea que el
  operador no gobierna y el correo que deja de atribuirle lo que no hizo— y 1 en `MixedPartyBadgeTest`
  (el cargo huérfano se sigue explicando). **11 mutaciones y las 11 muerden.**
  ⚠️ **El tercer `skipped` es NUEVO y no es una regresión**: `InlineBrandLogoTest` tiene dos casos que
  solo corren donde está instalado el paquete de marca del cliente, y esta máquina no lo tiene. El
  segundo de ellos **erraba** en vez de saltarse —dejando `main` sin poder empujarse desde cualquier
  clon sin ese paquete— y se le puso la misma guarda que ya tenía su hermano.
  ⚠️⚠️ Y una lección de guarda que vale para todo este carril: **`GuestAgeMixReader` va en `scoped`**,
  así que un caso que cambia el catálogo y vuelve a guardar leería los valores memoizados y **nacería
  ciego**. Lo resuelve el helper `nextRequest()`, que simula el corte entre dos peticiones.
- Antes, tras `#267` (la sombra del logotipo): suite **3501 en verde** (22.978 aserciones). ▶ **+1 caso**: que el filtro conserve los números
  del mockup, con su mutación —volver a la sombra tenue de `#263` la pone roja—.
- Antes, tras `#266`: suite **3500 en verde** (22.972 aserciones, 1 skipped a propósito) · **JS 862**. ▶ **+2 casos** en `InlineBrandLogoTest`: que la animación caiga sobre algo
  que **se pinte** (no un `id` de `<defs>`) y que la **amplitud sea proporcional a la figura**.
  **7 mutaciones, las 7 muerden** — la última tras acotar una guarda que nació laxa.
- Antes, tras `#265`: suite **3498 en verde** (22.946 aserciones, 1 skipped a propósito) · **JS 862**, medida el
  2026-08-29 por la noche tras `#265` (el CTA flotante y la física del salto del logotipo).
  ▶ **+3 casos**: dos en `InlineBrandLogoTest` —la coreografía declara la curva de cada tramo, y el
  asentamiento **no puede fijar el `transform`** o mata el hover— y uno en `MotionScaleTest`, que
  comprueba que las coreografías declaradas **existen de verdad**. **7 mutaciones, las 7 muerden.**
  ⚠️ El caso de `MotionScaleTest` no es decorativo: fue lo primero que avisó cuando un `git
  checkout` se llevó el CSS de la sesión.
- Antes, tras `#264`: suite **3495 en verde** (22.920 aserciones, 1 skipped a propósito) · **JS
  862**, medida el 2026-08-29 por la tarde (el área táctil de 44 en la landing). ▶ **+9 casos**:
  `TouchTargetTest`, que fija el mecanismo —token único, área centrada que **nunca encoge**, puerta
  de puntero grueso, `::before` porque `::after` dibuja el pomo del interruptor de cookies— y el
  marcado: **ningún control conocido pierde su marcador**, y el enlace en línea NO lo gana.
  **8 mutaciones, las 8 muerden.**
  ⚠️ **Lo que esta guarda NO puede hacer, y va escrito en su cabecera**: no mide píxeles. Que un
  control lleve el marcador no demuestra que su área acabe midiendo 44 —puede recortarla un ancestro
  con `overflow`—. Eso lo dice la sonda de `VERIFICACION-E2E-CAJON.md` §5.duovicies.
- Antes, tras `#263`: suite **3486 en verde** (22.805 aserciones, 1 skipped a propósito) · **JS
  862**, medida el 2026-08-29 sobre el árbol CONJUNTO: `#243`→`#248` del carril A (cumpleaños MIXTO: +60 casos, +176
  aserciones) sobre `#263` del carril C.
  ⚠️⚠️ **ESTE NÚMERO DEPENDE DE LA MÁQUINA, y el hook lo exige exacto.** El carril A midió aquí
  **22.801 / 2 skipped** y el C **22.805 / 1**: la diferencia es que `InlineBrandLogoTest` se salta
  su último caso —el que sirve el logotipo del cliente— **cuando `public/img/client-logo.svg` no
  está instalado**, y ese fichero es del paquete de marca, gitignorado. ▶ Si el contador te sale
  distinto y tu suite está verde, **no es un fallo: es que tienes o no tienes el paquete puesto**.
  Pon el tuyo y sigue.
  ⚠️⚠️ **Y una lección del cierre**: la suite del árbol PROPIO dio verde y la del CONJUNTO 34 fallos
  — el rebase trae las FUENTES Vue del otro carril pero no su compilado, y `SidebarDomContractTest`
  renderiza el bundle. Su propio mensaje lo dice; `npm run build:ssr` lo arregla. **Con dos carriles
  sobre `main`, medir solo el árbol propio no dice nada del que se empuja.**
  ▶ Antes, tras `#262` (el interruptor del titular pasa a ser el `6d` del canvas, con el rótulo
  ya dentro de la pista): **3426 / 22.625**. ▶ **Mismo
  número de casos y +34 aserciones**: no entra ningún test nuevo — la escala de movimiento gana un
  token ambiental (`--dur-switch`) y `MotionScaleTest` lo recorre en sus cuatro casos, que están
  escritos sobre la lista y no sobre una cuenta fija.
  ⚠️ **`#262` no trae guarda propia, y es una decisión**: el token y la curva los cubre
  `MotionScaleTest`, el canto `ShapeScaleTest` y los colores `RawColourIsNotATokenTest`; lo único que
  quedaría —las proporciones del `calc()`— **se rompería a la vista en la portada**, y la
  verificación registrada es la MEDICIÓN en navegador (`tema-por-instalacion.md` §25.2 y §25.6),
  que es más fuerte que aseverar el texto de una declaración (la lección de `#251`).
- Antes, tras `#261` (el interruptor en línea y el par del CTA): **3425 / 22.568**.
  ⚠️ El techo del chunk del cajón sube **257 → 260**: +0,04 por los dibujos que cambian de idioma y
  **+2,60 por las cinco ramas nuevas** de `ProductIcon.vue`, que es capacidad, no engorde.
  ⚠️ El manifiesto del cajón se regeneró a propósito (6 entradas: las tres pantallas de desenlace).
- Suite **3425 en verde** (22.531 aserciones, 1 skipped a propósito) · **JS 862**, medida el
  2026-08-29 tras `#257` (el set de iconos del artboard). ▶ **+4 casos**: `IconSetAnatomyTest`, que
  hace ejecutable la anatomía del set —`currentColor` sin excepciones, rejilla 24, nada de línea
  fina **donde el trazo dibuja la forma**—. **5 mutaciones, las 5 muerden**, y lleva control
  explícito del matiz (`check` es masa con hilo, no línea fina).
  ⚠️ Y una guarda vecina se corrigió: `SidebarDrawerPolishTest` medía `width` sin frontera de
  palabra y **casaba dentro de `stroke-width`**.
- Suite **3421 en verde** (22.515 aserciones, 1 skipped a propósito) · **JS 862**, medida el
  2026-08-29 tras `#256` (la demo del minijuego en el punto estático). ▶ **+2 casos** en
  `SaltaJuegoTest`: que la demo arranque al VER el lienzo —y que alguien LLAME al registro— y que la
  tira tenga su hueco también en reposo. El de `prefers-reduced-motion` gana la **puerta nueva de
  descarga**, porque *lo que un gate declara que no mira es un hueco con nombre*.
  **8 mutaciones, las 8 muerden** — ⚠️ y **dos no mordían por culpa del ARNÉS**, no de las guardas.
- Suite **3419 en verde** (22.495 aserciones, 1 skipped a propósito) · **JS 862**, medida el
  2026-08-29 al CERRAR la sesión del carril C (`#250` → `#255`). ▶ **+1 caso**: el simétrico del
  test del reloj (`#255`), la franja de hoy **todavía en curso** — sin él, el caso arreglado se
  cumpliría con una implementación que diera `true` para cualquier fecha de hoy.
  ✅ **Y `audit-clock.sh` corrido y VERDE en las nueve fronteras**, las dos medianoches incluidas.
  Se corre porque esta tanda tocó un fixture con calendario; no está en el `pre-push`.
- Suite **3418 en verde** (22.494 aserciones, 1 skipped a propósito) · **JS 862**, medida el
  2026-08-29 tras `#254` (el cierre a la altura del hueco, el hero con un solo CTA, el interruptor
  del titular y el logotipo en línea, carril C). ▶ **+13 casos**: `InlineBrandLogoTest`, ocho de
  ellos por proveedor con un peligro cada uno más el control positivo.
- Suite **3405 en verde** (22.434 aserciones, 1 skipped a propósito) · **JS 862**, medida el
  2026-08-29 **sobre el árbol CONJUNTO**, tras rebasar `#253` (los ocho puntos de la portada, carril
  C) encima del `#242` del carril A. ▶ **+2 casos en este corte**: la capa de la tarjeta del cierre
  (`LayerOrderTest`) y los modificadores huérfanos (`ArmazonCssHasNoOrphansTest`).
  ⚠️ La guarda de los huérfanos **nació ciega tres veces** y solo la tercera mutación dio con el
  motivo: el modificador es la CLAVE del array, no el valor.
- Suite **3403 en verde** (22.406 aserciones, 1 skipped a propósito) · **JS 862**, medida el
  2026-08-29 de madrugada **sobre el árbol CONJUNTO**, tras rebasar la **segunda vuelta del owner**
  (`#242`, carril A) encima del `#252` del carril C. ▶ **+5 casos**: la guarda de que **un enlace
  a la cuenta abre la CUENTA** (`AccountDoorWiringTest`), dos del selector de menores y dos del panel
  —el «Atrás» de solo icono que no puede quedar MUDO y las tarjetas de cobro—. **11 mutaciones y las
  11 muerden**, dos de ellas tras corregir guardas que pasaban en verde con el defecto puesto.
- Suite **3398 en verde** (22.385 aserciones, 1 skipped a propósito) · **JS 862**, medida el
  2026-08-29 **sobre el árbol CONJUNTO**, tras rebasar
  `#252` (el imán, el pie a una fila y la marquesina fuera, carril C) encima del `#241` del carril A.
  ▶ **+1 caso PHP y +19 de JS en este corte**: `test_the_hero_gap_is_derived_from_the_cluster` y la
  red del imán (`ui/scroll-magnet.test.js`), con **4 mutaciones PHP y las 4 mordiendo**. ⚠️ **El
  techo de peso de la landing sube de 23 a 26 KiB a propósito** —medido 24,5; el imán son ~1,6 y se
  los cobra toda página pública— con margen del 6 %, no otro cable trampa.
- Suite **3397 en verde** (22.365 aserciones, 1 skipped a propósito), medida el 2026-08-28 por la
  noche tras la **vuelta del owner sobre las dos pantallas nuevas** (`#241`). ▶ **+13 casos**:
  `strip.js` con 8 en `node --test` (la aritmética del recorrido de una tira), 2 más en
  `SidebarDrawerPolishTest` (las flechas nacen apagadas · el cableado observa el NODO) y 6 en
  `CreateManualOrderTabletTest` (una franja llena se enseña deshabilitada y **el servidor la rechaza**,
  la hora pesa más que las plazas, el calendario nace plegado, el resumen nombra al cliente con el
  mismo texto que el buscador). **12 mutaciones y las 12 muerden.** JS **813 → 843**.
- Suite **3389 en verde** (22.320 aserciones, 1 skipped a propósito) · **JS 835**, medida el
  2026-08-28 por la noche **sobre el árbol CONJUNTO**, tras rebasar `#251` (la transición del cierre,
  carril C) encima del `#240` del carril A. ▶ **+5 casos en este corte**:
  `CierreChoreographyTest` —el progreso crudo y el suavizado no se confunden—, **6 mutaciones y las
  6 muerden**. ⚠️ **Los tests de JS los corre el runner de NODE** (`npm run test:js`), no vitest:
  `npx vitest run` dio «58 ficheros fallan» y era el instrumento.
- Suite **3384 en verde** (22.292 aserciones, 1 skipped a propósito), medida el 2026-08-28 por
  la noche **sobre el árbol CONJUNTO**, tras rebasar **«Crear pedido» en tablet** (`#240`) encima del
  `#250` del carril C. ▶ **+7 casos**: `CreateManualOrderTabletTest`, que fija las decisiones de forma
  —el corte de dos columnas, la columna pegajosa, la navegación DENTRO de ella y los siete selectores
  táctiles que salieron de MEDIR— y la conducta de las **dos puertas** para elegir día. **6
  mutaciones y las 6 muerden**, la última tras corregir una guarda que pasaba en verde por comparar
  una SUBCADENA.
- Suite **3377 en verde** (22.276 aserciones, 1 skipped a propósito), medida el 2026-08-28 por la
  noche **sobre el árbol CONJUNTO**, tras rebasar `#250` (la COLUMNA, carril C) encima del `#239` del
  carril A. ▶ **+3 casos en este corte**: `ColumnIsDeclaredOnceTest` —el ancho de la columna se
  escribe una vez y las tres formas de expresarla lo leen—, **6 mutaciones y las 6 muerden**.
  ⚠️ **Y una trampa del rebase que costó 34 rojos y no era del cambio**: el bundle SSR quedó RANCIO
  con las fuentes del otro carril, y lo dijo su propia guarda. `npm run build && npm run build:ssr`
  antes de la suite, o el `pre-push`, que ya lo hace.
- Suite **3374 en verde** (22.251 aserciones, 1 skipped a propósito), medida el 2026-08-28 por la
  noche (hora de Madrid) tras las unidades 2–4 del **cajón en móvil** (`#239`). ▶ **+13 casos y +121
  aserciones**: `AvailabilitySettingsTest` (9, el lector defensivo y el predicado del aviso —con dos
  casos solo para el `0`, que significa «no avisar» y no «avisar cuando no queden plazas»), dos del
  contrato de árbol —**el calendario desplegado y el aviso «Casi llena»**, que sin un caso que los
  haga aparecer salían del gate sin que nada avisara— y dos del panel. **JS: 813 → 835.**
- Suite **3361 en verde** (22.130 aserciones, 1 skipped a propósito), medida el 2026-08-28 por la
  noche (hora de Madrid) **sobre el árbol CONJUNTO**, tras rebasar `#238` (la COLUMNA, carril C)
  encima del `#237` del carril A. ▶ **+4 casos y +37 aserciones en este corte**:
  `CappedContainerGutterTest`, la guarda de que un contenedor con `max-width` propio no se sangra
  con `--wrap-gutter` — **4 mutaciones y las 4 muerden**, una por cada mitad de los dos defectos
  que motivaron la guarda.
- Suite **3357 en verde** (22.093 aserciones, 1 skipped a propósito), medida el 2026-08-28 a las
  20:00 (hora de Madrid) **sobre el árbol CONJUNTO de los dos carriles**: el A con la FORMA del
  panel (`#223` menú plano + «Ajustes», `#224` el buscador, `#232` la puerta en tablet, `#234` su
  pulido y **`#236` los menores**, que suman **30 casos nuevos** —`AdminNavigationTest`,
  `AdminGlobalSearchTest` y `GateKioskTest`, ninguno de los tres existía— más cuatro guardas
  re-apuntadas por sujeto en `#236`) y el C con el mockup 1:1 (`#225`→`#235`). JS **813**.
  ⚠️⚠️ **Los dos carriles han chocado TRES VECES en el número de decisión en una tarde**: `#225` (el
  del panel pasó a `#232`), `#233` (pasó a `#234`) y `#235` (pasó a `#236`). Con dos carriles
  apendando al mismo registro numerado esto **se va a repetir**: si se vuelve a trabajar en paralelo,
  conviene repartir un rango por carril. La renumeración se hace **siempre sobre la lista de ficheros
  del PROPIO diff** (`git status`), nunca con un `grep` del árbol — que es lo que llegó a corromper
  cinco referencias del otro carril en `#232`.
  2026-08-28 a las 19:00 (hora de Madrid) **sobre el árbol CONJUNTO**: el carril A (`#223` menú plano +
  «Ajustes», `#224` el buscador y `#232` la puerta en tablet, que suman **27 casos** —`AdminNavigationTest`,
  `AdminGlobalSearchTest` y `GateKioskTest`, ninguno de los tres existía—) rebasado sobre el carril C
  (`#225`→`#231`, el mockup 1:1 y el minijuego del pie). JS **813** por parte del carril A, que no toca JS.
  ⚠️ **Los dos carriles usaron el número `#225`**: el del panel se renumeró a **`#232`** al fusionar,
  y la corrección se hizo sobre una lista EXPLÍCITA de ficheros — un `sed` global sobre el árbol llegó a
  corromper cinco referencias del carril C, incluida la línea que avisa de este mismo riesgo.
  ⚠️ **La medición anterior, 3315 / 21.713 a las 15:35, fue la del árbol CONJUNTO** —el pulido `#217`
  con los nueve arreglos de su revisión, rebasado sobre los ocho commits del carril C (hasta `#222`)—,
  y su lección sigue valiendo:
  ⚠️ **La fusión no fue limpia y lo cazó la suite, no el rebase**: el carril C había estrenado
  `MotionScaleTest` (la escala de MOVIMIENTO de su tanda 2d) y mis clases nuevas del cajón llevaban las
  duraciones y las curvas escritas a mano (`0.15s ease`, `0.22s`, `0.18s`). Adaptadas a los roles de su
  escala (`--dur-toque`/`--dur-sale` con `--ease-sale`: el hover no lleva rebote, lo dice su tabla).
  ▶ Es el tercer aviso del mismo tipo en la jornada: **dos carriles sobre el mismo árbol se cruzan en
  las GUARDAS, no en el código** — el rebase da verde y la suite conjunta es la que habla.
- Suite **3253 en verde** (21.386 aserciones), medida el 2026-08-28 por la tarde por el carril C
  tras **`#222`** (**la tanda 2d: el MOVIMIENTO**, el SEXTO mecanismo del tema y el último que
  faltaba). ▶ Medido antes: **239 declaraciones, 53 duraciones, 20 curvas** — y **200 de los 220
  usos de curva eran `ease`**, o sea que el **90 % del movimiento de la web no lo decidía nadie**.
  Después: **608 usos de token, CERO literales fuera de la escala**. ⚠️⚠️ **Y la conversión se hizo
  a MEDIAS la primera vez, otra vez**: mi filtro de «esto es ambiente» buscaba palabras en el
  CONTEXTO y saltó ONCE declaraciones —`--shadow-float` en un comentario vecino salvó a
  `.lang-dd__panel`—. *Un filtro que mira el contexto acierta hasta que el contexto cambia.*
  ⚠️ El escondite de los literales eran las **custom properties** (`--cta-pair-swap: 0.46s`): ningún
  inventario de `transition` las veía.
- Suite **3249 en verde** (21.353 aserciones), medida el 2026-08-28 por la tarde por el carril C
  tras **`#221`** (**la 2c·4b: el menú a pantalla completa manda también en móvil**, y con eso el
  ARMAZÓN queda completo en los doce anchos). ▶ Medido forzando el menú por debajo del corte —la
  única forma de saber por qué estaba apagado—: a 390 px un ítem medía **671 px dentro de un menú
  de 390**, y a 1024 el primero salía en `y = −26`. ⚠️⚠️ **Donde el mockup usa `nowrap` nosotros no
  podemos**: sus destinos son cortos y fijos, **los nuestros los manda la BD**. Y entra la **vela**
  que dice que la lista sigue (a 390 px, cinco de diez destinos quedan fuera con la barra de scroll
  oculta), apagada con `animation-timeline: scroll()` **sin una línea de JS**.
  ⚠️ Una comprobación propia dio un **falso negativo** —dijo que el último destino no era
  alcanzable midiendo su posición sin desplazar—: *preguntar «¿está a la vista?» no es preguntar
  «¿se puede llegar?»*.
- Suite **3246 en verde** (21.320 aserciones), medida el 2026-08-28 por la tarde por el carril C
  tras **`#220`** (el HERO EN TELÉFONO: el titular no cabía y la culpa era del **suelo** de un
  `clamp` — `clamp(63px, 13vw, 96px)` nunca baja de 63 y el hueco daba para 49). ▶ Con él, tres
  divergencias más de la misma pieza: el corte estaba en 720 y el del mockup en 620, un
  `text-align: center` que el mockup no tiene, y **el titular no encogía con el hero**. Medido en
  seis anchos: coincide **exactamente** con la fórmula del mockup en los seis.
  ⚠️ **La guarda nació ROJA con el código correcto**: buscaba el `clamp` prohibido en el fichero
  crudo y la cadena está en el COMENTARIO que explica por qué se retiró. *Un `grep` que encuentra
  no demuestra que exista.*
- Suite **3245 en verde** (21.314 aserciones), medida el 2026-08-28 por la tarde por el carril C
  tras `#217` y **`#218`** (el logotipo a su tamaño REAL: el `height:54px` del mockup es la caja que
  lo envuelve, no el dibujo — son **70**). ⚠️ **Hubo que llegar a la TERCERA medida**: las dos
  primeras discrepaban (1,17 y 1,29) y ninguna estaba rota — medían la CAJA y la caja de LÍNEA, no
  la tinta. **Cuando dos medidas no cuadran, casi siempre están midiendo cosas distintas.**
  Medido tinta contra tinta: mockup 189 × 68, nosotros 190 × 67, y el halo de la sombra idéntico
  (18 · 19 · 20 · 20)
- Suite **3245 en verde** (21.314 aserciones), medida el 2026-08-28 por la tarde por el carril C
  tras `#217` (**las tres piezas que el OJO del owner vio distintas**: las sombras del racimo, la
  forma del botón de menú y el logotipo). ▶ **`ArmazonContractTest` +3 casos**, **1 re-apuntado**
  (el del aspa: exigía dos glifos del SET de iconos y ahora exige que **las dos rayas giren**) y
  **`ShapeScaleTest` amplía su lista de ROLES**, no la de excepciones. **12 mutaciones, las 12
  muerden.** ⚠️ **`ShapeScaleTest` funcionó exactamente como debía**: las cinco sombras nuevas la
  pusieron en rojo obligando a decidir qué eran — y la respuesta fue «un CUARTO rol», no «una
  excepción». La lista de excepciones solo encoge; la de roles no.
  ⚠️ **Y una guarda propia nació DEMASIADO GRUESA**: prohibía la clave `.nav__brand` entera, y esa
  regla lleva también el **layout** del hueco, así que salió roja con el código correcto. Se acota
  a lo que protege: que no PINTE.
- Suite **3242 en verde** (21.229 aserciones), medida el 2026-08-28 a mediodía por el carril C
- Suite **3241 en verde** (21.227 aserciones), medida el 2026-08-28 a mediodía por el carril C
  **sobre el árbol CONJUNTO** —tras rebasar encima del `#215` del carril A— con `--parallel` en
  **47 s**, tras `#216` (**el armazón nace bajo el hero, el hero recupera sus dos botones, el
  CTA doble se alinea en sus ocho medidas y entra el paquete de MARCA del 2.º cliente**).
  ▶ **+6 casos en `ArmazonContractTest`** y **SEIS tests re-apuntados, ninguno retirado**: el del
  menú abierto (ahora mira el racimo ENTERO, porque con el armazón oculto también se juega la X de
  cerrar), el de `navCtaReveal` (ahora asevera **el hecho de producto** —el hero ofrece la compra—
  en vez del nombre de un componente de JavaScript), el del rótulo alterno (ahora exige que NO
  vuelva), `ActionFillTest` (declara `.hero__act--buy`) y **dos que aseveraban por SUBCADENA y por
  eso salían rojos con el código correcto**: `SeoTest` prohibía `alt=""` en TODO el HTML —y WAI-ARIA
  lo **exige** en una imagen decorativa— y `PublicPagesTest` buscaba `data-has-hero` en todo el
  documento en vez de en el atributo del `<body>`, así que un `<style>` que nombra el selector lo
  ponía en rojo. **Las dos quedan ACOTADAS a su sujeto, no aflojadas.** Cuarta vez que esta casa
  paga la misma lección.
  ▶ **15 mutaciones, las 15 muerden** · sonda de navegador con la coreografía **contrastada contra
  la aritmética del mockup** (0,5617 calculado / **0,561 medido**) y los anchos 224/56 exactos.
  ⚠️ **Y una CUARTA trampa, que la sonda NO vio y sí la captura**: los dos botones del hero salían
  **apilados**. La sonda medía existencia, color y tamaño —los dos bien—; lo que fallaba era
  **dónde**. `width: 100%` junto al `max-width`, una declaración que leyendo el CSS parece
  redundante. **Medir no es mirar.**
  ⚠️ **Y 32 fallos que NO eran de la tanda**: `SidebarDomContractTest` con el bundle SSR **rancio**
  tras traer el `machine.js` del carril A. Lo dice el propio test; se arregla con `npm run build:ssr`
  (el `pre-push` lo hace solo). **Un test que compara un bundle viejo da verde con el código roto.**
- Suite **3235 en verde** (21.140 aserciones), medida el 2026-08-28 por el carril C tras `#214`
  (**el CTA es un PAR**: uno ancho, el otro reducido a su icono, y una invitación que se apaga
  cuando le hacen caso). ▶ **+3 casos en `ArmazonContractTest`** y **CUATRO tests re-apuntados, ninguno
  retirado**: los dos que contaban `nav-cta-ghost` para saber si se ofrecía el alta (ahora por
  DESTINO, porque esa clase ya no distingue nada), el del nombre accesible (ahora exige que el
  visible sea PREFIJO) y **la guarda del `mode` del cajón, que contaba `this.mode =` en TODO
  `app.js`** y casaba con el `mode` del store nuevo. **9 mutaciones, las 9 muerden · sonda de
  navegador 12/12.**
  ⚠️⚠️ **DOS de esas guardas nacieron LAXAS y solo lo demostró la mutación**: una miraba si
  `$store.ctaPair` aparecía «en algún sitio» del atributo —y pasaba con los anchos leyendo una
  variable local, porque la invitación, en el MISMO atributo, sí usaba el store—; la otra aceptaba
  cualquier `animation:` bajo `--invita`, y el ARO la cumplía. **Media invitación es la que no se
  ve.** Antes:
- Suite **3232 en verde** (21.070 aserciones), medida el 2026-08-28 por el carril C **sobre el árbol
  CONJUNTO** (su `#213` rebasado encima del `#212` del carril A, el carné QR). ⚠️ **Se MIDIÓ, no se
  sumó**: en su propio árbol el carril C daba 3.224 · 21.000, evidencia ANTERIOR a la fusión. Tras `#213` (el
  CTA del armazón alineado al mockup). ▶ **+2 casos y +21 aserciones** en `ArmazonContractTest`: que
  el CTA **cambie de rol dentro del menú** (tinta → aviso) y que su **anillo de foco deje de ser el
  amarillo del propio botón** —si no, foco invisible—; y que su forma sea la del mockup (fuente de
  rótulo en mayúsculas, sin flecha, subtítulo que HEREDA el color). `ActionFillTest` re-apuntado:
  `.cta-med` sale del rol de acción, que pasa de 13 reglas a 12. **8 mutaciones, las 8 muerden ·
  sonda de navegador 14/14.** ⚠️ Trampa nueva: Chromium devuelve `color-mix()` como
  `color(srgb r g b / a)` con canales 0–1, no como `rgba()` — la primera comprobación del velo dio
  ROJO con el CSS correcto. Antes:
- Suite **3230 en verde** (21.049 aserciones, `--parallel` **~70 s**), medida el 2026-08-28 a las 07:46 (hora de
  Madrid) por el carril A **sobre el árbol CONJUNTO** —`#210` + el `#211` del carril C + `#212`—, tras rebasar
  el `#212` encima del `#211`. ⚠️ **Se MIDIÓ, no se sumó** (coincide con 3222 + 8 y 20.979 + 70, y eso es
  una comprobación, no la fuente). Conflictos de la fusión: los tres ficheros a los que ambos carriles
  añaden al final (`DECISIONES`, este ledger, `VERIFICACION-E2E-CAJON`) — resueltos conservando los dos
  bloques; y el guion del carné pasó de `§5.quaterdecies` (que el carril C ya había usado) a `§5.quindecies`.
- Suite **3222 en verde** (20.979 aserciones), medida el 2026-08-28 por el carril C **sobre el árbol
  CONJUNTO** (su `#211` rebasado encima del `#210` del carril A). ⚠️ **Se MIDIÓ, no se sumó**: en su
  propio árbol el carril C daba 3.150 · 18.486, y esa evidencia es ANTERIOR a la fusión.
  ▶ **+6 casos y +37 aserciones**: `ArmazonContractTest` (la hamburguesa ALTERNA y dice en
  qué estado está · enseña una X sacada del set de iconos · con el menú abierto SIEMPRE hay botón de
  comprar) y `ClientThemePackageTest` (el hueco del ICONO de instalación, sus tres piezas, y que las
  DOS plantillas lo usen). **10 mutaciones, las 10 muerden · sonda de navegador 15/15.**
  ⚠️ **Una de esas guardas nació DÉBIL y lo demostró la mutación**: aseveraba por SUBCADENA y
  `.nav-cta-med-NO` contiene `.nav-cta-med`, así que pasaba con el CSS roto. Es la **tercera** vez
  en dos días (las otras: `favicon.svg` dentro de `client-favicon.svg`, y `cta-prime` dentro de
  `cta-prime__ico` en `#195`). **Antes de creerte un test verde, acota al elemento.** Antes:

- Suite **3224 en verde** (21.012 aserciones, `--parallel` **~70 s**), medida el 2026-08-28 a las 07:40 (hora de
  Madrid) por el carril A **sobre su propio árbol, ANTES de fusionar con el `#211` del carril C**, tras
  **`#212`** (las dos superficies del carné): **+8 tests y +70 aserciones** — `MeCardTest` +2 (la imagen:
  mismos bytes que el correo; 404 con la clave rotada) y `RotateCardActionTest` (6). JS 773 → 790.
- Suite **3216 en verde** (20.942 aserciones, `--parallel` **~71 s**), medida el 2026-08-28 a las 07:10 (hora de
  Madrid), tras **`#210`** (el OJO del owner en localhost): **+3 tests y +7 aserciones** — `SidebarSetupBindingsTest`
  (3 casos: props sombreadas, `watch` antes de su `const`, la guarda de la guarda). ⚠️ El hook `pre-push` contrasta
  ESTA línea —la primera del ledger— con lo que la suite acaba de dar: el primer push de `#210` cayó justo por eso.
- Suite **3213 en verde** (20.935 aserciones, `--parallel` **~80 s**), medida el 2026-08-28 de madrugada
  por el carril A tras **A3+A4** del subsistema A (`specs/identidad-qr-puerta.md` §9.4): **+16 tests** —
  la pantalla (`ValidarRegistroProfileTest`: el carné por el input, quién ve la ficha, los dos
  limitadores, caducidad en servidor, visita idempotente, zh_CN, nunca el nombre de un menor; 3/3
  mutaciones), `GET|POST /me/card` contra el contrato (`MeCardTest`) y el PNG en el correo
  (`OrderConfirmationCardTest`, con la clave rotada el correo sale sin adjunto). Antes:
- Suite **3197 en verde** (20.803 aserciones), tras **A2** del subsistema A (`specs/identidad-qr-puerta.md` §9.3): **+7 tests** — la
  ficha compuesta (`GateProfileTest`: hoy frente a la ventana configurable, el dinero por
  `OrderLedger::forReservation()`, los menores como edad + exención SIN nombre, los estados de
  waiver/carné/visita, presupuesto CONSTANTE de 23 consultas medido con dos fixtures de la misma forma) y
  el doble del contrato `GateReservations` en `ModuleContractsTest`. Y el control del carné pasa a
  **módulo 31** con un test EXHAUSTIVO (17×31 sustituciones): el «mod 32» caía 1 de ~8 veces. Antes:
- Suite **3190 en verde** (20.750 aserciones), tras **A1** del subsistema A (`#208`, `specs/identidad-qr-puerta.md` §9.3): **+13 tests**
  — el carné (`CustomerCardTest`: forma y control con 500 emisiones, emisión única, rotación que mata
  el viejo, `revokeAllAccess()`/`anonymize()` revocan —2/2 mutaciones muerden—, `plainToken()` con
  `APP_KEY` rotada) y la visita (`GateVisitsTest`: idempotente por día, auditada solo al escribir).
  `docs-check` pasaba entonces a **36 modelos y 85 migraciones** (cifra de aquel día). Antes:
- Suite **3177 en verde** (18.680 aserciones), tras **P4** de la tanda 5 de menores: **+5 tests y +33 aserciones**
  — el alta manual (`CreateManualOrderDependentsTest`: el selector por línea de entrada con motivos, la
  línea guarda solo ids asignables y rechaza más menores que unidades, `check()` ANTES de cobrar —un
  rechazo no crea ni cobra nada— y `assign()` DESPUÉS de `fulfill()`, con el fallo que deja el pedido en
  pie y avisa). Antes:
- Suite **3172 en verde** (18.647 aserciones), tras **P3** de la tanda 5 de menores: **+12 tests y +109
  aserciones** — la acción «Asignar menores» de la línea (`AssignDependentsActionTest`: quién ve el icono,
  qué enseña el modal —leído por el SCHEMA montado, porque el HTML del modal no forma parte del render
  del componente en el test—, pone y quita con auditoría del operador, mismo conjunto = sin cambios,
  `too_many`, y las cuatro capas de defensa: permiso, IDOR por `not_in_order`, cancelado, pack). Antes:
- Suite **3160 en verde** (18.538 aserciones), medida por el carril A **sobre el árbol CONJUNTO** (el
  `#209` del carril C rebasado con P1+P2 de la tanda 5 de menores): el número se mide sobre el árbol
  conjunto, no se suma. ▶ **+16 tests y +89 aserciones** (carril A, tanda 5 · P1+P2): el
  `sync()`/`candidates()` del mostrador (8 casos, 4/4 mutaciones muerden), `WaiverStatus::forDependents()`
  con paridad (3) y el «Para:» de la ficha del pedido (5).
- Suite **3144 en verde** (18.449 aserciones, `--parallel` **~87 s**), medida el 2026-08-28 por el
  carril C **sobre el árbol ya rebasado encima de `origin/main`** (con `#207`/`#208` del carril A
  dentro, pero SIN P1+P2), tras `npm run build` + `build:ssr`.
  ▶ **+12 tests y +401 aserciones en este corte** (carril C, el **QUINTO mecanismo del tema** — el
  relleno de ACCIÓN, `#209`, spec §15): `ActionFillTest` (11 casos: el conjunto de reglas de acción
  es exactamente el declarado · ninguna conversión a medias · las piezas internas del CTA siguen al
  relleno · el conmutador no se emite sin dato ni con basura · el hover acierta el `#D56319` del
  cliente · el texto pasa AA y **siempre es el mejor de los dos**, barriendo toda la escala de
  grises) y **+1 en `SurfaceScopeTest`** (la indirección del rol en los tres ámbitos, y que el
  producto NO declare el conmutador). **11 mutaciones, las 11 muerden.** Sonda de navegador 12/12.
  ⚠️ **Dos trampas pagadas**: la primera versión reutilizaba `onBrand()` y daba **3,73** sobre el
  hover del propio cliente —su umbral es 3,0, el de texto GRANDE, y un rótulo de botón no lo es—; y
  el arnés de mutación detecta por **código de salida**, nunca por `grep` (en esta shell `grep` es
  una función interpuesta y en `#195` eso dio «0 de 12» con el test perfecto). Antes:
- Suite **3132 en verde** (18.048 aserciones, `--parallel` **~70 s** medidos el 2026-08-27 por la noche
  sobre el árbol FUSIONADO de los dos carriles (tras `#206`), en la máquina del carril A) ·
  ⚠️ **Este número lo verificó el `pre-push` sobre el árbol FUSIONADO** (2026-08-28): el carril C
  cerró midiendo **3.130 · 18.002** en su árbol y el A empujó su U2 mientras tanto, así que la
  evidencia del commit de cierre del C es **anterior a la fusión** y no coincide con ésta. **La
  cifra buena es ésta**, que es la que corrió con los dos trabajos dentro. Es la segunda vez en dos
  días que dos carriles cortan a la vez: el número **se mide sobre el árbol conjunto, no se suma**.
  ▶ **Corte de arreglo visual, sin tests nuevos** (carril A, menores a cargo · tanda 4, spec §9.9.8·7): el
  checkbox del selector se veía descuadrado y el nombre fuera del cajón —lo vio el owner, no ninguna guarda—;
  causa `.eventfields input { width:100% }` por descendencia; arreglo sin CSS nuevo; manifiesto 2 claves
  regeneradas. ⚠️ **Lección**: `SidebarStyleWiringTest` comprueba que cada clase TENGA regla, no que las
  reglas de sus ancestros no SOBREN — «cero CSS nuevo» no es «cero CSS que alcanza». Antes:
  ▶ **+2 tests PHP y +39 JS en el corte anterior** (carril A, menores a cargo · tanda 4 · **U2**, el
  cajón — spec §9.9.8): dos casos del contrato de árbol (el paso 3 con una ENTRADA y dos menores, uno
  deshabilitado con motivo; el carrito con la línea asignada y el aviso de la puerta 2; manifiesto +2
  claves, 0 cambios) y `npm run test:js` **734 → 773** (`assignment.test.js`, `line-problems.test.js`,
  stores de cesta/selección/menores, `admission`, `pay`, `outcome`). Las 136 guardas del cajón con los
  techos RE-MEDIDOS: chunk 234,70 → **242,19 KiB** (techo 235 → 243, por FEATURE), payload con sesión
  7.602 → **7.747 B** (techo 7.700 → 7.800; ⚠️ estuvo en 8.300 provisional mientras los rótulos del
  embudo viajaban en `account`), `PurchaseSection.vue` 432 → **428**. **Guion §5.undecies 19/19** por
  las dos puertas · **sonda de 16 `assign()` simultáneos → 1 fila, 1 auditoría** (§9.9.5, que U1 no
  midió). ⚠️ **Dos trampas pagadas**: un `watch` con `immediate` por ENCIMA de la `const` que lee —Vue
  traga el `ReferenceError` del getter y llama al callback con `undefined`, que `!== null`: la carga
  saltó una vez, también sin sesión, y nunca más; lo delató el `pageerror` del diagnóstico—; y un
  `ensure()` que devolvía en seco a la segunda llamada mientras la primera estaba en vuelo (hoy devuelve
  la misma promesa). Antes:
  ▶ **+3 tests PHP en el corte anterior** (carril C, el paquete del 2.º cliente · las dos decisiones
  del owner): el hueco del **LOGOTIPO de la instalación** con sus tres piezas —no se versiona, se
  carga si existe, `deploy.sh` lo excluye del `--delete`— y su `alt`, que es el nombre accesible del
  único enlace que toda página tiene. **5 mutaciones, las 5 muerden.** ⚠️ Y el **anillo de foco por
  superficie** no costó código de producto: su `--focus-color` ya valía `var(--fg)` para seguir a la
  superficie, y **era el paquete el que lo rompía** al fijarlo a un literal. Antes:
  ▶ **−1 aserción, y es un ARREGLO de fondo** (carril C, el paquete del 2.º
  cliente): las cinco guardas de CSS **dejan de juzgar `public/css/client.css`**, que es la hoja de
  una INSTALACIÓN y no del producto. Una de ellas aseveraba **por hoja**, así que el recuento
  cambiaba según si la máquina tenía o no un paquete instalado — y el `pre-push` compara el número
  exacto: **bloqueaba a una de las dos máquinas siempre**. ⚠️ **Un gate que depende de si el disco
  tiene el tema de un cliente no es un gate.** ▶ Verificado midiendo las dos veces: **3.127 y
  17.990 con paquete y sin él, idénticos**. Antes:
  ▶ **+6 tests PHP en el último corte** (carril C, armazón · tanda **2c·4a**, el CTA doble de
  móvil): que la barra sea un par con destinos distintos, que la mitad colapsada **diga qué hace
  AHORA** y no a dónde lleva, que **las dos funcionen sin JavaScript** con una sola pulsación y con
  el nombre servido correcto, que el reparto por defecto sea comprar, que el idioma tenga **un solo
  sitio y suelo sin JS**, y que el eslogan del menú salga de la clave del hero. **9 mutaciones, las
  9 muerden.**
  ⚠️ **Este contador se fusionó a mano el 2026-08-28**: los dos carriles cortaron a la vez y ninguna
  de las dos cifras previas —3.121 del A, 3.096 del C— valía para el árbol conjunto. **Se volvió a
  MEDIR, no a sumar** — y menos mal: sumar habría dado 3.127 tests (acierta) y **17.991 aserciones
  solo por casualidad**, porque la fusión también movió aserciones dentro de guardas que cuentan por
  regla.
  ⚠️ **Y la trampa del carril A mordió al carril C en cuanto fusionó**: `npm run build` sin
  `build:ssr` desfasa el renderizador SSR y tumba **30 casos** del contrato de árbol. Estaba escrita
  dos párrafos más abajo, se leyó **después** de pagarla. El hook los encadena a propósito. Antes:
  ▶ Y el corte del carril A, con su medida propia (3.121 · 17.936 en su árbol):
  ▶ **+30 tests PHP y +1 JS** (carril A, menores a cargo · tanda 4 · **U1**, el
  servidor — spec §9.9.7): `DependentAssignerTest` (17), `OrdersDependentAssignmentTest` (8),
  +1 `DependentRegistryTest`, +3 `DependentPrivacyTest`, +1 `ModuleContractsTest` (el doble de
  `CheckoutLines` con el orden invertido); `npm run test:js` **733 → 734** (`sanitizeLine` con
  `dependent_ids`). **9 mutaciones, las 9 muerden** (anti-IDOR · firma · minoría en la visita ·
  idempotencia · correlación · `anonymize()` · referencia · el `check()` del controlador · `event-data`).
  Los SEIS escenarios de `purchase:verify-oversell` y `redsys:verify-concurrency` con 16 procesos ✓ (no
  ejercitan la asignación: control de no-regresión por el `CRITICAL_RE`). Sonda HTTP de 10 pasos ✓.
  Chunk 234,43 → **234,70 KiB** (techo 235). `docs-check` **~34 modelos · ~82 migraciones** (entonces).
  ⚠️ **Dos trampas de test pagadas**: el tercer argumento de `assertDatabaseHas` es la CONEXIÓN, no un
  mensaje («Database connection [mensaje] not configured»); y `assertJsonPath` no resuelve claves con
  puntos (`items.0.dependent_ids.1`) — se lee `json('error.fields')` y se compara la clave literal. Antes:
  ▶ **+1 test PHP y +1 JS en el corte anterior** (carril A, menores a cargo · tanda 4 · **U0**, la purga
  de la cesta): `SidebarMountTest::test_the_engine_seeds_the_cart_owner_from_the_boot_before_mounting`
  —guarda ESTRUCTURAL sobre `index.js`, porque la suite no arranca el motor— con **2 mutaciones, las 2
  muerden** (sin siembra · siembra después de montar), y el caso «sembrar el dueño ANTES de restaurar»
  en `stores/cart.test.js`: `npm run test:js` **732 → 733**. Chunk 234,41 → **234,43 KiB** (techo 235).
  ⚠️ **La primera versión de la guarda salió ROJA con el fuente correcto**: `strpos` casó `app.mount(el)`
  con una mención en un comentario anterior a la llamada — limpia comentarios antes de buscar (la
  lección de `#200`). ⚠️ **Y 30 casos del contrato de árbol salieron rojos por correr `npm run build`
  sin `build:ssr`**: el renderizador SSR quedó desfasado; el hook los encadena a propósito. ⚠️ **Y el
  push chocó DOS veces con el carril C** (`#203`, `#204`) mientras el gate corría (~4 min cada vez): el
  precio de «empujar pronto» con dos carriles a la vez, pagado esta noche. Antes:
  ▶ **+4 tests PHP en el corte anterior** (carril C, armazón · tanda **2c·3**, la cuenta): que el
  botón conserve su nombre accesible **en texto** —era el saludo visible, y al quedarse en icono
  solo vive en el `aria-label`—, que el texto visible no vuelva sin ser prefijo del nombre, que el
  punto use el token de aviso, que el glifo del alta siga al DESTINO (trámite externo vs crear
  cuenta) y que el armazón **no dibuje glifos en línea**. **8 mutaciones, las 8 muerden.** Antes:
  ▶ **+4 tests PHP** (carril C, armazón · tanda **2c·2**, los dos racimos):
  el salto al contenido en las 12 y aterrizando, que **todos** los `<main>` lleven el ancla —no solo
  el primero, que es el defecto que se cazó—, que la barra se haya disuelto de verdad (sin fondo,
  sin desenfoque, sin línea, y con los clics renunciados en el contenedor y recuperados en los
  racimos) y el cableado de la coreografía. **+8 tests JS**: `nav-choreography.test.js`, porque la
  lógica del scroll salió de `app.js`, que no lo cubre ningún test. `npm run test:js` **724 → 732**.
  **12 mutaciones, las 12 muerden.** Antes:
  ▶ **+5 tests PHP** (carril C, armazón · tanda **2c·1**, el menú a pantalla completa): **+7** en `ArmazonContractTest` —el overlay accesible del menú, que declare superficie

  de tinta, que todo enlace lo cierre, que los números sean decoración, el orden del parque, el
  orden de los servicios y que la barra ya NO lleve destinos— y **−2 en `HomePageTest`**, que **no
  se retiraron: se MUDARON**. Su sujeto —los dos desplegables— murió, pero lo que comprobaban de
  verdad seguía vivo y nadie más lo fijaba: las etiquetas, las anclas y **el ORDEN**.
  **16 mutaciones, las 16 muerden**, y la que más importa es la del defecto del mockup: quitarle al
  menú el `visibility:hidden` de cerrado. `npm run test:js` 724/724. Antes:
  ▶ **+15 tests PHP** (carril C, armazón · tanda **2c·0**): `ArmazonContractTest`
  (10, la red de conducta del armazón — **y el cajón móvil ESTRENA test: no lo tocaba ninguno**) y
  `ArmazonCssHasNoOrphansTest` (5, el trinquete de CSS sin consumidor). **15 mutaciones, las 15
  muerden**, con control positivo.
  ⚠️⚠️ **Y las aserciones suben SOLO 1 con 15 tests nuevos: 17.694 → 17.695. No es un error, y
  cuadra a la aserción.** Los tres guardas de CSS aseveran **por regla**, así que al retirar las 33
  reglas muertas pierden **86** aserciones (4.025 → 3.939, medido stasheando el cambio y volviendo a
  correr); los tests nuevos aportan **87**. `17 694 − 86 + 87 = 17 695`. ▶ **Un contador que se mueve
  menos de lo que esperabas no es un contador roto hasta que no puedes explicar la diferencia.**
  ⚠️ **Lo que enseñó el arnés de mutación de esta tanda, y afecta a cualquiera que escriba uno**:
  `shutil.copy2` restaura el fuente **con su mtime original**, que queda más viejo que la vista que
  Blade compiló durante la mutación → **el fuente vuelve a estar sano y la aplicación sigue sirviendo
  la versión mutada**. Dos tests salieron rojos por mutaciones ya revertidas, y —peor— una mutación
  puede apuntarse un tanto que ha ganado el residuo de la anterior. **El arnés hace `view:clear` antes
  de cada medición**; sin eso su 15/15 no valía. Antes:
  ▶ **+0 tests PHP y +3 aserciones, y +22 JS** (`#199`, menores a cargo · tanda 3,
  el cajón): `npm run test:js` **702 → 724** (el módulo plano `account/dependents.js` 9, el store 13);
  las tres aserciones son la poda del subgrupo `dependents` en `SidebarMountTest`. Chunk del cajón
  **234,41 KiB (techo 235)**, payload con sesión **7.602 B (techo 7.700)**, los dos subidos por FEATURE
  con su párrafo. Antes:
  ▶ **+15 tests PHP** (`#198`, menores a cargo · tanda 2, la firma del menor):
  `MeDependentWaiverTest` (9, contra el contrato), la cadena por sujeto y ajeno/retirado/adulto en
  `WaiverSignatureChainTest` (+2), la retención del menor desde los 18 y **NUC-3 como guarda** en
  `WaiverRetentionTest` (+2), el PDF y el registro del panel con el nombre (+2). **5 mutaciones, las 5
  muerden · `waiver:verify-chain` 8/16 PASA y visto FALLAR sin el lock.** Antes:
  ▶ **+9 tests PHP** (`#193`, capa de tema · tanda **2a**): `ShapeScaleTest`.
  **13 mutaciones, las 13 muerden** — pero solo después de arreglar el arnés. ⚠️⚠️ **El arnés de
  mutación dio «0 de 12 muerden» con el test funcionando perfectamente**: decidía con
  `grep -q "FAILED\|failed"` y aquí `grep` es **ugrep en ERE**, donde `\|` es un pipe LITERAL — buscaba
  la cadena `FAILED|failed` y no casaba jamás. Se decide por **código de salida** y lleva **control
  positivo** (el test tiene que estar verde antes de mutar). ▶ **Cuando un instrumento dice que NADA
  funciona, la primera hipótesis es el instrumento**: un arnés que nunca detecta el fallo no es
  inofensivo, **certifica** — habría firmado que 12 aserciones eran decorativas.
  ▶ Antes, **+14** (`#192`, tanda 1): `SurfaceScopeTest` (8) y
  `ThemeFontsTest` (6). **13 mutaciones, las 13 muerden.** ⚠️ Y una de ellas destapó que **la guarda de
  la guarda había nacido ciega**: aseveraba un umbral de recuento sobre `:root` y no detectaba que el
  parser se quedara sin la mitad del corpus, porque `site.css` declara el suyo. Se asevera por NOMBRE.
  ⚠️ El trinquete `SidebarTokenBudgetTest::MAX_RAW_COLOURS` bajó **5 → 4 → 3**, avisando él las dos
  veces. Antes:
  ▶ **+34 tests PHP** (`#191`, menores a cargo · tanda 1): `DependentRegistryTest`
  (15: edad derivada y jamás persistida, el 18.º cumpleaños cruzando UTC↔Madrid, solo menores, tope de
  servidor desde el ajuste, quitar = desvincular/borrar, anti-IDOR), `DependentPrivacyTest` (5:
  `anonymize()`, export, poda, purga de go-live), `MeDependentsTest` (11, contra el contrato) y
  `DependentsCapSettingTest` (3, el tope en Ajustes). **7 mutaciones, las 7 muerden.** Antes:
  ▶ **+5 tests PHP** (`#189`, F+G de la 4b): la huella propia excluida para el PACK
  en `edit()`, «con crédito NO hay marcador», los `event_data` en el mismo guardado que una edición
  con dinero, y la guarda de arquitectura del punto único de lock (2 casos). Antes:
  ▶ **+3 tests PHP** (`#188`, D+E de la 4b): los permisos re-exigidos en
  `OrderItemCanceller`/`OrderItemRefunder` (inalcanzables desde la página) y las tres guardas de E
  con su razón estructurada (selección vacía, ítem ajeno, principal bloqueado) — cinco mutaciones
  que salían verdes. Antes:
  ▶ **+3 tests PHP** (`#187`, C de la 4b): la huella propia excluida al mover una
  ENTRADA que se solapa a sí misma, el destino LLENO rechazado bajo el lock con su audit, y el
  rechazo anidado de `event_data` auditado sin deshacer el cambio de franja — tres mutaciones que
  salían verdes. Antes:
  ▶ **+0 tests, +6 aserciones** (`#186`, C0 de la 4b: `CriticalPathGateTest` vigila
  cuatro ficheros más — dos críticos, dos controles negativos). Antes:
  ▶ **+3 tests PHP en el último corte** (`#185`, sub-paso B de la 4b): tres reglas de
  `OrderItemEventDataWriter` que la página NO alcanza (obligatorios ausentes —Filament valida
  antes—, permiso re-exigido en el servicio, «sin cambios» sin `save`) probadas DIRECTAMENTE; sus
  mutaciones salían verdes y ahora muerden. Antes:
  ▶ **+1 test PHP** (`#184`, sub-paso A de la 4b): la regla de bloqueo
  per-invitado/grupo de los complementos (`addon_locked` por BLOQUEO, no por mínimo) no tenía test
  y la mutación salía verde; ahora lo tiene, con control negativo. Antes:
  ▶ **+9 tests PHP y +1 JS** (`#183`, la revisión de la tanda 4 aplicada): la firma
  pendiente lleva la UA de la aceptación y espera al commit; `pending` en el contrato; vigencia dentro
  del lock; NFD y blanco tras `[`; idiomas publicables; cuenta existente declarada; rama negativa del
  PDF; el cambio de correo firma la pendiente; el 422 de `accept_waiver` relee. **10 mutaciones, las 10
  muerden** · **guion completo en headless: **111/111 ✓, 0 desviaciones**** ·
  ▶ **+1 test PHP en el corte anterior** (`#180`, el texto del PDF): la comprobación del PDF se llama
  «interna» y dice su alcance, el PDF de mostrador dice de quién son los datos y la IP, y
  `WaiverChain` cruza cada firma con su versión (una versión alterada por debajo rompe la cadena).
  **3 mutaciones, las 3 muerden** ·
  ▶ **+4 tests PHP en el corte anterior** (`#179`, correo verificado para firmar): la firma del alta
  se aplaza a la verificación (y con el canal del alta), la pendiente caducada se descarta, sin
  verificar no se firma ni desde la cuenta (409), y la declarada en mostrador sí. **5 mutaciones, las
  5 muerden**; `waiver:verify-chain` 8/16 con la guarda ·
  ▶ **+6 tests PHP y +1 JS en el corte anterior** (`#178`, decisiones 4b/4c del waiver): la casilla del
  alta obligatoria en interno (422 sobre `accept_waiver`; opcional en externo e interno sin versión)
  y la casilla del alta manual (con ella firma declarada; sin ella nada, también sin email); JS
  **700 → 701**. **4 mutaciones, las 4 muerden**. ⚠️ El navegador cazó un **500 al abrir el modal**
  del alta manual con la suite en verde (`wire:partial`): ahora el texto del modal se prueba directo ·
  ▶ **+0 tests y +1 aserción en el corte anterior** (`#177`, el instrumento de la extracción 4): el
  inventario de `OversellVerifierCoversEveryQuotaTest` conoce el escenario `panel-edit` ·
  ▶ **+2 tests PHP en el corte anterior** (`#176`, extracción 3 del desmontaje): el del ancla del parque
  que CRUZA la frontera UTC↔Madrid (el caso que `AFORO-09` no tenía) y el de «horas sin aforo se
  ocultan», cuya regla existía desde el origen y su mutación salía VERDE — 4 mutaciones del
  servicio, las 4 muerden ·
  ▶ **+12 tests y +46 aserciones en el corte anterior** (`#174`, unidad 2 de la tanda 4 del waiver):
  el canal por guard (sesión + `Bearer basura` sigue siendo `web`; token real → `api`; alta sin
  sesión → `api`), la aceptación idempotente por versión (una firma, un consentimiento, ninguna
  auditoría de más), los tres marcadores de borrador + el aviso de palabras, el badge del pedido por
  `WaiverStatus` (sello sin registro, versión anterior, modo desactivado) y los throttles con prefijo.
  **7 mutaciones, las 7 muerden.** ⚠️ Tres tests y `waiver:verify-chain` construían la cadena
  re-firmando la misma versión: se corrigieron (versiones nuevas / un menor por proceso) ·
  ▶ **+1 test en el corte anterior** (`#172`, extracción 1 del desmontaje): `calendarGoToItemMonth`
  gana el test que no tenía ANTES de mudarse al Concern (mutación vista morder), y retirar el
  `use ManagesItemCalendar;` tumba 14 tests — la red cubre la extracción entera ·
  ▶ **+0 tests y +3 aserciones en el corte anterior** (`#170`, paso 0 del desmontaje de `ViewOrder`):
  los 3 tests por reflexión sobre métodos MUERTOS se sustituyeron 1:1 por 3 sobre la fuente viva
  del calendario, que aseveran más (**4 mutaciones, las 4 muerden**; spec §9.1) ·
  ▶ **+1 test en el corte anterior** (2026-08-26, tras `#166`, sin número: un fix con su guarda):
  `SeededSettingsAreSaveableTest` — lo que siembra `db:seed` tiene que poder guardarse desde Ajustes;
  el owner lo pilló en navegador (`DEUDA.md` · Baja: «Guardar» mudo por un `#` sembrado) ·
  ▶ **+0 tests PHP y +5 JS en el corte de la unidad 3 del waiver** (`#175`, el cajón): `npm run
  test:js` **695 → 700** (el store del waiver: id enseñado, relectura, `reread`; el store de auth: el
  422 del alta); **5 mutaciones, las 5 muerden**; chunk del cajón **226,21 KiB y el techo sube a
  226,5 por CORRECCIÓN** (ledger en `SidebarBundleBudgetTest`) ·
  ▶ **+0 tests PHP y +5 JS en el corte del 26/08 por la noche** (`#171`, el anti-bot del alta suelta):
  `npm run test:js` **690 → 695**; chunk del cajón 225,72 → 225,85 KiB (corrección, techo intacto) ·
  ▶ **+0 tests y +1 aserción en el corte anterior** (`#166`, la 3b del waiver): lo nuevo es JS —
  `npm run test:js` **671 → 690** (+19: módulo 6 · store 9 · `register.js` 4)— y la aserción es la
  lista exacta de `register` en `SidebarMountTest` ·
  ▶ **+25 en el corte anterior** (`#163`): `LegalWaiverTest` (5), `MeWaiverTest` (14: estado por modo,
  aceptar solo lo servido, `409` caducado / no interno, canal por autenticación, el PDF propio con
  IDOR y auditoría, y que el export NO lleva el registro probatorio), `AuthRegistrationTest` (+5: la
  casilla opt-in, el rechazo ANTES de crear la cuenta) y `MeAccountContextTest` (+1). **5 mutaciones,
  las 5 muerden** (spec §9.8). ⚠️ Y los avisos del alta se movieron a `api.register.*` porque en
  `account.register.*` sacaban de su techo a los DOS presupuestos del montaje del cajón.
  ▶ Antes, **+25** (`#161`): `WaiverProofPdfTest` (14: permiso propio, IDOR, auditoría,
  `no-store`, idioma del texto firmado, **el PDF no cambia al editar la página ni al publicar otra
  versión**, determinismo, la identidad copiada sobrevive a `anonymize()`, tres idiomas distintos),
  `WaiverProofActionTest` (6) y `PresentialWaiverDeclarationTest` (5). **5 mutaciones, las 5
  muerden** (spec §9.6). ⚠️ Y una trampa del arnés medida: el modal de Filament es un `wire:partial`
  y `assertSee` no lo ve tras `mountAction` (`TESTING.md`).
  ▶ Antes, **+47** (`#160`): los seis ficheros de `tests/Feature/Waiver/` — inmutabilidad
  de versiones, cadena de firmas (con la serialización canónica FIJADA como literal), retención tras
  `anonymize()` y poda con el reloj congelado, los tres modos, la puerta en interno y la acción de
  publicar. **5 mutaciones, las 5 muerden**; el verificador de cadena sobre MySQL, visto fallar sin el
  lock (spec §9.3).
  ▶ Antes, **+3** (`#159`): `AnonymizeCoversEveryUserColumnTest`, el **censo** de las 18
  columnas de `users`. Convierte en guarda la última frase de `RGPD-01` —«cualquier PII nueva debe
  añadirse aquí»—, que hasta hoy era una petición: **una columna nueva pone la suite en rojo hasta
  que alguien la declare**. Es simétrico (una conservada que empiece a purgarse cae igual) y lleva
  su guarda-de-la-guarda. **2 mutaciones, las 2 muerden**; `User.php` restaurado y comprobado por md5.
  ▶ Antes, **+4** (`#157`): `ClientMoneyLabelsAreTranslatedTest`, la **segunda** guarda
  del EN/FR. No repite a la de `#154`: añade la guarda-de-la-guarda, la prohibición del **mecanismo**
  (el helper compartido no puede volver a citar `admin.*`), el barrido ancho de todas las claves
  `tickets.*` del dominio con suelo declarado, y —lo que la separa— que **los tres idiomas digan cosas
  DISTINTAS**. ❗ **Medido**: con `lang/fr` relleno de castellano, la guarda de `#154` **pasa con 23
  verdes** y ésta cae. **2 mutaciones, las 2 muerden.**
  ⚠️ **Y las dos nacieron del MISMO defecto arreglado dos veces en paralelo** por los dos agentes sin
  saberlo (`#157`): se tiró el arreglo duplicado y se quedó lo que no coincidía.
  ▶ Antes, **+3** (`#155`): el email de una bajada cuenta el dinero — e2e con la
  línea renderizada, la variante absorbida y la guarda de idiomas. **3 mutaciones muerden.**
  ▶ Antes, **+1** (`#154`): las etiquetas del cargo de puerta que lee el CLIENTE
  viven en `tickets.*` con sus TRES idiomas — guarda `Lang::has(..., false)` + composición bajo
  `en`. **2 mutaciones muerden.**
  ▶ Antes, **+2** (`#153`): el modal del reembolso de PEDIDO nombra el importe
  exacto y, sin «también cancelar», señala la vía de los parciales. **2 mutaciones muerden.**
  ▶ Antes, **+2** (`#152`): el e2e del pedido CANCELADO con deuda reembolsado por
  línea hasta dejar el «pendiente de devolverte» a CERO, el candado del cancelado sin deuda y
  los dos banners. **2 mutaciones, las 2 muerden.**
  ▶ Antes, **+10**: `ItemPriceChangeReconstructionTest` (`#150`) — los CUATRO caminos de
  `#146` (bajar cantidad · bajar precio · las dos · cancelar tras bajada) más la cadena de ediciones,
  el pedido cancelado sin vía, las etiquetas y los toasts. **6 mutaciones, las 6 muerden.**
  ▶ Antes, **+8**: `RefundItemCustomAmountTest` (`#149`) — el escenario del owner de punta a punta
  (40 € → día de 30 € por el CALENDARIO → devolver exactamente 10) más las guardas del importe
  elegido en las TRES capas (form, handler, dominio bajo lock). **5 mutaciones, las 5 muerden.**
  ▶ Antes, **+3**: `PackConsumesEntrySeatsTest` (`#148`), que fija que **una fiesta SÍ consume
  asientos de entrada** (y una entrada NO consume cupo de fiestas), y **+4**:
  `OversellVerifierCoversEveryQuotaTest` (`#147`), la guarda de que el verificador de sobreventa
  **no encoja**. ⚠️ **Ninguno cubre la carrera**: eso exige MySQL y `pcntl_fork`, y vive en comando.
  ▶ Antes, **+14** con las guardas del registro legible de un pedido (`#145`).
  **724 tests JS** (`node --test`) · Pint limpio (935 ficheros) · `docs-check` verde ·
  ⚠️ **Los dos números de esta línea llevaban retraso y se re-MIDIERON el 2026-08-27**, no se
  dedujeron sumando: JS decía 671 (son **702**) y Pint 895 (son **931**). El contador de JS no
  tiene guarda —el de PHP sí— y por eso deriva: mídelo con `npm run test:js`, no lo estimes. ·
  `composer audit` y `npm audit` en **0** · `npm run build` y `build:ssr` OK.
  ⚠️ Sale con **1 `PHPUnit Notice`** que **NO es de ningún trabajo reciente**: viene de antes y es del
  runner (ver `TESTING.md`). No lo persigas creyéndolo nuevo.
  ✅ **El contador de PHP tiene GUARDA**: el `pre-push` compara lo que acaba de dar la suite con lo que
  declara esta línea y **corta si no cuadran** (`#116`). Antes derivó tres veces en un solo día.
  ⚠️ **El de JS NO la tiene**, y por eso llegó a llevar **22 cierres de retraso**: si dudas, mídelo con
  `npm run test:js` en vez de sumar deltas.
  ⚠️ **Éste es el ÚNICO sitio donde vive el contador**: duplicarlo en otro documento crea una copia que
  no guarda nadie.
  ⚠️ **Y puede BAJAR a propósito**: `/mi-cuenta/…` se llevó 63 casos y el modal de auth 42, ninguno por
  descuido —se midió por mutación cuáles cazaba también la API antes de borrar—. **Un contador que solo
  puede subir acaba premiando al test que no se retira.**
  ▶ El histórico de qué aportó cada corte vive en su entrada de `DECISIONES`, no aquí.

- ⚠️ **La suite NO está auditada contra la FECHA, y ya mordió DOS veces** (`DECISIONES #64`, `#97`):
  tres casos amanecieron rojos sin que nadie tocara nada, y el **2026-08-16 a las 00:02 de Madrid** el
  `pre-push` cayó con **1 fallo** en el cruce de medianoche; el reintento salió verde.
  ⚠️ **Y no se supo cuál era**: la salida del gate no se capturó y se perdió. **Si el `pre-push` cae,
  vuelca su salida a fichero antes de reintentar** — un rojo transitorio sin nombre no se puede
  arreglar. Están arreglados congelando el reloj, pero **nadie ha
  barrido el resto**. Si te encuentras un rojo que no viene de tu cambio, **guarda el árbol y prueba en
  el commit anterior antes de tocar nada** — es lo que separó el diagnóstico en minutos de una sesión
  perdida. Ficha en `DEUDA.md`.
- **El gate son SEIS pasos** —docs-check · Pint · `npm run build` · `npm run build:ssr` ·
  `npm run test:js` · suite—, y `PrePushGateTest` los vigila uno a uno, incluido que el build vaya
  ANTES que la suite (se añadió tras un fallo real: un manifest a 0 bytes tumbó la web entera).
- ⚠️ **`SidebarDomContractTest` compara contra un ARTEFACTO** (`storage/ssr/render-sidebar.js`). Tiene
  guarda contra bundle rancio (`#69`) porque un bundle viejo daba **verde falso**; ha saltado **tres
  veces en tres días** —la última tumbando sus 30 casos de golpe (`#78`)—. Si tocas un módulo del cajón,
  o lo mutas y lo restauras, `npm run build:ssr` **antes** de leer ningún resultado.
- **Auditorías de dependencias = verificación de CIERRE, no de instalación** (`#25`): el árbol npm pasó
  de 0 a 5 avisos en unas horas sin que el lock cambiara. Correrlas en cada cierre.
- **Los dos verificadores de concurrencia: VERDES sobre MySQL real** (2026-08-14, 8+8 workers).
  **La lista viva de lo que exige `VERIFY_CONC=1` es el `CRITICAL_RE` de `.githooks/pre-push`** — no se
  copia aquí para que no envejezca, y `CriticalPathGateTest` vigila que siga cubriendo lo que debe.
  ✅ **Y desde `#147` `purchase:verify-oversell` cubre los TRES aforos, no uno**: `--scenario=entry`
  (asientos) · `pack` (cupo de FIESTAS) · `pack-guests` (cupo de INVITADOS). Hasta el 2026-08-25 solo
  existía el primero, y el aforo de packs **no lo probaba nadie**. Medido sobre MySQL con 8 y 16
  procesos: **los tres aguantan**.
  ❗❗ **Y su verde vale porque el instrumento se vio FALLAR**: retirando el `lockForUpdate()` de
  `OrderCreator::lockSlots`, el mismo comando cazó **8 fiestas donde cabía 1** y **48 invitados donde
  caben 10**. Un verificador que nunca se ha visto fallar no ha demostrado que pueda.
  ⚠️ **Lo que sigue SIN medir y no se da por hecho**: el tramo multi-franja bajo concurrencia, la
  cesta MIXTA entrada+pack, y `prep_blocks_cupo` **activo** (el valor por defecto en producción).
  ✅ **Y desde `#141` los dos CONTADORES de aforo disparan el gate**: `SlotAvailability` y
  `PackAvailability` llevaban fuera desde el principio — el gate vigilaba a quien LLAMA y no a quien
  CUENTA. Verificado por mutación, y con `ProductAvailability` como control negativo declarado.
  ✅ **Y desde la tanda 3 el gate ya no deja fuera ninguna superficie de dinero** (`#120(u)`): el
  reintento web —el último que quedaba sin cubrir— se retiró con la página que lo servía, porque el
  cajón reintenta por `POST /api/v1/orders/{code}/payment`, que sí entra por el `CRITICAL_RE`. (La
  compra la había dejado antes `#112`, al retirar `Livewire\Tickets\Purchase`.)
- **Fase 2 dejó tres cosas que se usan al tocar código hoy** (el resto, en `specs/modulos-dominio.md`):
  `php scripts/module-deps.php [Clase…]` mide las dependencias INVISIBLES · *recibir* una entidad de
  otro módulo es costura de BD, *consultar* sus datos o *repetir* sus reglas exige contrato · las
  baselines del arch-test **solo encogen**.
- ✅ **El segundo objeto-dios está DESMONTADO** (`DECISIONES #119`, 2026-08-22). `Sidebar.vue` pasó de
  **614 líneas y 11 llamadas a la API** a **16 y 0**: el embudo vive en `sections/PurchaseSection.vue`
  y el estado, en `stores/` —la reorganización creó **nueve** y el área de cliente ha ido añadiendo
  los suyos, uno por dominio—. Lo siguen guardando `SidebarComponentBudgetTest`
  (techo por componente + excepción declarada que solo encoge) y una guarda de que **la raíz no vuelve
  a pintar pantallas**. ⚠️ **Las cifras vivas están en su `EXCEPTIONS`**, no aquí: copiarlas a este
  documento es drift en espera, y ya pasó una vez.
- ⚠️ **NOTA DE DESPLIEGUE permanente**: las migraciones corren **ANTES** de servir tráfico (el morphMap
  de Fase 2 es requisito) y hay que **drenar la cola + `queue:restart`** (los payloads serializados
  llevaban los FQCN viejos).

## ▶ Decisión de producto VIGENTE que enmarca todo lo demás

⚠️ **El cajón es el ÁREA DE CLIENTE, no el embudo de compra** (`DECISIONES #66`, owner). Toda la
gestión del cliente vive dentro del cajón: sus entradas y reservas, y las gestiones de cuenta.
✅ **De los tres sitios en que estaba repartido, quedan DOS**: las páginas `/mi-cuenta/…` se retiraron
(`#120(u)`) y el **modal de auth de la cabecera** sigue siendo la puerta de entrada de quien no tiene
sesión. Ése es el último trozo, y su ficha está en `DEUDA.md`.

- **El orden es dependencia, no preferencia**: **4.7** → **Turnstile** → **área de cliente**.
  ✅ **Turnstile ya no ata nada** (4.4b·2, 2026-08-20): el cajón monta su propio widget y la delegación
  en el modal de la cabecera **está retirada**. ⚠️ Pero el modal **no se retira aquí ni en `4.7·2b·3`**:
  vive en `layout.blade.php`, no en `purchase.blade.php`, y sigue siendo la puerta de auth de la web
  fuera del cajón. Retirarlo es trabajo del área de cliente.
- ✅ **El terreno ya está preparado** (`DECISIONES #119`, 2026-08-22), y lo que hay que saber es dónde
  NO meter la cuenta:
  · el grafo del embudo es `FUNNEL_TRANSITIONS` y está **cerrado con guarda**: colgar ahí una pantalla
    de cuenta pone el test en rojo. Un área de cliente **no es un embudo** — sus pantallas se navegan
    libremente— así que va con su propio modelo, no con el de la compra;
  · el embudo es una **sección** (`sections/PurchaseSection.vue`) y la raíz son 16 líneas que solo
    enrutan: **la cuenta entra al lado, no dentro**;
  · el estado de cada dominio ya tiene su store, así que una sección nueva pide el suyo y no necesita
    que la raíz le pase nada por props.
  ✅ **Y el modelo de navegación está DISEÑADO, VALIDADO por el owner y CONSTRUIDO** (`#120(d)`,
  `specs/area-cliente.md` §3.1): índice + zonas libres con pila de retorno, con su propio modelo y sin
  tocar el grafo del embudo. Añadir una zona es **una línea** en `ZONES` más su rótulo.
- ✅ **El servidor está COMPLETO para las cinco gestiones** (`#120(a)` lo midió endpoint por endpoint,
  y por eso el área fue en tandas): **leer** —`/auth/*`, `/me`, `/me/orders`, `/me/reservations`,
  `/me/reservation-eligibility` y el post-form por firma, desde Fase 3— y **gestionar**: contraseña,
  sesiones, perfil (`PUT /me/password`, `POST /me/sessions/revoke-others`, `PATCH /me` y los dos del
  correo pendiente) y, desde el paso 8, los **dos derechos RGPD** (`DELETE /me` y `GET /me/export`).
  ⚠️ **`DELETE /me` NO borra la fila**: llama a `anonymize()` (`RGPD-01`). El pedido y su historia
  contable se conservan sin PII, porque la FK es `RESTRICT` y la factura tiene que seguir vinculada.
  ⚠️ **`GET /me/export` es el cuerpo con más PII del producto** —lleva `event_data` en claro: nombre
  y alergias de un menor, art. 9— y por eso `RGPD-04` exige `no-store`, que en `/api/v1` va por
  defecto en toda respuesta autenticada.
- ✅ **`account-context` YA ES VUE** (`#123`, 2026-08-23): el bloque lo pinta
  `sidebar/account/AccountPanel.vue`, teletransportado al hueco que emite el layout, y la frontera
  Livewire↔Vue del cajón **desaparece**. ⚠️ **Pero Livewire NO se puede retirar**: sigue trayendo
  Alpine, así que `@livewireScripts` se queda — lo que cambia es que ahora es la **fuente única**, y
  su guarda por fin discrimina (medido: retirarla la pone roja; hasta hoy no).
- ✅ **La puerta de entrada de quien no tiene sesión es el CAJÓN** desde `#122` (2026-08-23): el modal
  de la cabecera se retiró y las tres pantallas de auth son zonas de la sección de cuenta.

## ▶ Próximo paso

# ❗❗❗ SI ENTRAS NUEVO (2026-08-30, cierre · carril C): LA MARCA Y EL MOVIMIENTO — `#275` → `#279`

**`git fetch` antes de nada**, y **vuelve a mirar el remoto al PUBLICAR**: hay otro agente en el
repo. Esta jornada tomó **`#275`–`#279`**.

## ▶ Lo que hay que hacer, en orden

| # | qué | dónde está el detalle |
|---|---|---|
| **1** | **El interruptor del titular debe PARAR tras unos ciclos** (`[DECIDIDO owner]`). ⚠️⚠️ **NO es cambiar `infinite` por un número**: el estado ON de reposo vive **solo dentro del bloque de `prefers-reduced-motion`**, así que acotar iteraciones lo deja **apagado** junto a un titular que dice «DIVERSIÓN» — el defecto de `#254`. Hay que **promover ese estado fuera del bloque** primero, y ojo: `forwards` tampoco vale, porque `heroSwitchTrack` acaba en `transparent` | `DECISIONES #279` · ficha en `DEUDA.md` |
| **2** | **La poda de bucles: medida y NO hecha** (`[DECIDIDO owner]`: «nada todavía, solo el informe»). `/precios` llega a **9** y **8 son un solo icono** (el de calcetines: seis puntos escalonados que **no se pueden fusionar sin perder la secuencia**); la invitación del CTA corre en **las doce vistas**, así que el techo de dos está gastado siempre | `DECISIONES #279` · ficha en `DEUDA.md` con el orden por rentabilidad |
| **3** | **Falta el OJO del owner** en el logotipo (`#275`), la cascada de franjas del cajón (`#277`) y el desenlace (`#278`) | — |
| **4** | **Subir a staging** lo de `#277` y `#278` (lo de `#275`/`#276` ya está subido, asset de marca incluido) | `scripts/deploy.sh --go`; la marca va aparte |
| **5** | Del artboard de movimiento quedan **el hover pegatina** (⚠️ toca el mecanismo de color de acción de `#209`, que llega al panel y a los correos: **preguntar antes**) | `specs/tema-por-instalacion.md` §29 · `DEUDA.md` |

## ▶ Qué hizo esta jornada

| | |
|---|---|
| `#275` | **El logotipo, por fin idéntico**: los TRES defectos estaban en la **exportación**, no en el CSS — `text-shadow` no arrastra el trazo · el velo interior salía 3,4× más fuerte y en tono frío · y **la silueta venía restada de las letras** (a la «A» le faltaba el 16,8 %) |
| `#276` | El **eslogan pegado** a la esquina superior izquierda del titular (⚠️ `margin: 0` no deja el hueco en cero: lo ponen los dos textos, cada uno en su `em`) y el **CTA de móvil** 9 px arriba — de él colgaban **dos números escritos a mano** |
| `#277` | Las **microanimaciones valoradas**: el vocabulario ya estaba. **U2** (movimiento reducido conserva el fundido) y **U3** (la cascada de franjas) — que destapó que **`sellable` estaba en el contrato y el cajón no lo leía** |
| `#278` | El **desenlace en dos piezas y sin confeti**: la pegatina entra y **el código se sella**. Retirarlo habría dejado **ciega una guarda ajena** que lo usaba de delimitador |
| `#279` | El **presupuesto de movimiento por pantalla, medido** con control |

⚠️⚠️ **La lección de la jornada, y se repitió CINCO veces**: una sonda estática propia da un número
creíble y falso, y **solo un CONTROL en navegador lo desmonta**. Pasó con el reborde del logotipo, con
el hueco del eslogan, con los bucles «sin proteger» (dije 10, luego 6, eran **0**), con la
compensación del titular y con el «22,17 px» que eran 7,3. *Cuando un número propio decida un cambio,
medirlo en el navegador y con un control que deba salir distinto de cero.*

---

# ❗ ANTERIOR (2026-08-29 noche → 2026-08-30 · carril C): EL ÁREA TÁCTIL, EL SALTO QUE NUNCA SE VIO, LA SOMBRA Y EL HERO — `#264` · `#265` · `#266` · `#267` · `#273` · `#274`

**`git fetch` antes de nada.** ⚠️⚠️ **Hay OTRO agente en este repo, trabajando en el PANEL ADMIN.**
La coordinación va por la doc: esta sesión toma **`#264`–`#274`**; el carril A venía en
`#243`–`#248`. **Mirar el remoto al elegir número no basta: hay que volver a mirarlo al PUBLICAR**,
y correr la suite sobre el árbol COMBINADO, que no lo hace nadie más.
⚠️ Esta tanda toca `public/css/site.css`, `public/css/landing.css` y ocho vistas Blade **públicas**;
del panel no toca nada (`resources/css/filament/admin/theme.css` sigue intacto).

## ▶ Qué hizo esta sesión

| | |
|---|---|
| `#264` | El **objetivo táctil de 44** llega a la landing: **37 → 1** control por debajo. El que queda es el enlace **en línea** del texto de cookies, que WCAG exime |
| `#265` | Vuelta del owner: el **CTA flotante** crece a 56 y pierde una sombra que era el **rol equivocado**; y el **salto del logotipo** recupera su física — 7 curvas, el asentamiento y el tempo |
| `#266` | Revisión adversarial de `#265`: **el salto NUNCA se vio** (animación sobre un elemento de `<defs>`) y su **amplitud estaba 7,5× corta** (los `px` de un SVG son unidades del `viewBox`) |
| `#267` | **La sombra del logotipo, medida por fin contra su lockup**: la nuestra tenía la mitad de densidad. Quedan los números del mockup |
| `#273` | Y el owner sigue viéndola excesiva: **fuera la sombra CSS**. La métrica de `#267` medía un agregado, y el ojo compara dibujos |
| `#274` | El **contorno del logotipo** al 65 % por guion, y el **hero de móvil**: un tope de 520 px que el mockup no tiene |
| — | El **contador de aserciones** vuelve a ser estable entre máquinas: mis dos casos dependían del paquete de marca, gitignorado |

## ❗❗ LO QUE MÁS IMPORTA QUE SEPAS

1. ❗❗ **SI VAS A AMPLIAR UN ÁREA TÁCTIL: el mecanismo son DOS y hay que saber cuál toca.**
   `[data-tap]` (final de `site.css`) amplía sin mover el dibujo y **solo con puntero grueso**; el
   bloque del **«Lote 9»** (arriba, mismo fichero) hace lo mismo para cuatro controles del cajón y
   **corre en todos los punteros a propósito**. El token `--tap-min` es de los dos y se declara UNA
   vez — hay guarda.
2. ⚠️⚠️ **El del Lote 9 ENCOGÍA y llevaba así desde entonces**: `width: var(--tap-min)` a secas pone
   el área en 44 aunque el control mida 60, o sea le quita 8 px por lado **sin que nada falle**.
   Ahora los dos usan `max(100%, …)`. *Un mínimo solo puede ampliar.*
3. ❗ **Dentro de un carril con scroll el área invisible NO SIRVE**: un eje no visible obliga al otro
   a `auto` y el pseudo se recorta **sin que nada avise**. Por eso las dos tiras del pie crecen de
   verdad, y por eso el bloque legal pasó a tira.
4. ⚠️⚠️ **Si mides áreas táctiles en navegador, lee `VERIFICACION-E2E-CAJON.md` §5.duovicies ANTES
   de creerte un número.** Esa sonda salió mal dos veces con cifras plausibles: recortando en
   coordenadas de viewport (áreas **negativas**) y sin aplicar el `transform` del pseudo (**58**
   donde son 44). Y **dos controles en capas distintas se solapan siempre**.
5. ⚠️ **Un barrido por FRACCIONES del alto no compara con el de antes si la página cambió de alto.**
   El pie encogió 30 px y aparecieron cuatro «solapes nuevos» que llevaban ahí desde siempre.
6. ❗❗ **SI TOCAS UNA ANIMACIÓN DE PERSONAJE** (`#265`): el movimiento puede vivir en los FOTOGRAMAS
   o en la CURVA, y hay que saber cuál antes de tocar ninguno. El salto del logotipo tenía sus ocho
   fotogramas exactos y **una sola curva con overshoot aplicada a todos**: rebotaba dentro de cada
   tramo. Ahora declara **siete**, una por tramo, y `MotionScaleTest` las admite **solo dentro de un
   `@keyframes` declarado como coreografía**.
7. ⚠️⚠️ **`fill: both` en una animación de `transform` MATA cualquier `:hover` que use `transform`**
   —deja el último fotograma fijado y una animación gana a la cascada—, y no falla nada. ⚠️ Y para
   comprobarlo: **`matrix(1, 0, 0, 1, 0, 0)` no es «no hay transform», es la identidad**.
8. ⚠️ **Comprueba que el asset es el que crees ANTES de buscar el defecto en el dibujo.** «El
   logotipo no es idéntico al mockup» se acotó en un `md5`: el fichero era byte a byte el suyo.

## ▶ POR DÓNDE SIGUE

0. ✅ **STAGING DESPLEGADO Y VERIFICADO — 2.ª vez el 2026-08-30, commit `1eb4828`**, ya con `#274`.
   ▶ Verificado en el navegador CONTRA STAGING, no en local: hero `min(78svh, 660px)` con
   **520 / 658 / 660 px** y huecos de **75 / 114 / 200** en SE · 14 · 15 Pro Max; logotipo con las
   capas afinadas (**33,8 · 24,44 · 9,36**) y `filter: none`.
   ❗❗ **EL LOGOTIPO SE SUBIÓ A MANO, y hay que saberlo**: `deploy.sh` excluye TODOS los ficheros de
   marca, así que el asset afinado por `#274` **no viaja en el despliegue**. Se subió por `scp` tras
   dejar copia en el servidor (`public/img/client-logo.svg.bak-antes-274`) y se comprobó por `md5`
   que el remoto es byte a byte el local. **Cada vez que se afine el logotipo hay que repetirlo.**
   ▶ Antes, el 1.er despliegue del día: commit `8443a19` — sirve ya los dos carriles:
   `#264` (área táctil), `#265`/`#266` (CTA flotante y el salto), `#273` (la sombra) y las seis
   tandas del cumpleaños mixto. **Sin migraciones nuevas** («Nothing to migrate»); 3.845 franjas
   generadas; `/up` y `/` en 200; `robots.txt` repuesto; 0 `failed_jobs`; 0 jobs varados.
   ▶ **Verificado midiendo el CSS SERVIDO, no suponiéndolo**: el logotipo con `filter: none`, el
   salto sobre `g:has(> use[href="#fig"])` con `dur 1000 / delay 466,7 / 8 tramos con curva`, la
   amplitud en `translateY(300%)`, `[data-tap]::before` y `--book-bar-h: 56px`. El CTA de móvil
   mide **298×56** con la sombra de mobiliario flotante.
   ⚠️ **El paquete de marca NO viajó** —el `rsync` lo excluye a propósito— y el que estaba instalado
   a mano sigue sirviéndose: el logotipo llega en línea. Si algún día se levanta un servidor nuevo,
   hay que reinstalarlo (`INSTALACION-CLIENTE.md`).
   ⚠️ El `--delete` retiró `\.env.bak-20260829-123102`, la copia que dejó `#217` en el servidor. El
   `.env` real ni viaja ni se toca.
   ⚠️ **Sigue el aviso conocido**: el script no ve ningún demonio cron, así que el crontab instalado
   puede no ejecutarse nunca. Se comprueba en el panel del hosting (`#115`). No es nuevo.
1. ❗ **El OJO del owner** sobre: el pie en un teléfono de verdad (los dos carriles se deslizan, la
   vela dice que siguen), la FAQ (se abre pulsando 8 px por encima del texto), que **en el ordenador
   el pie no ha cambiado nada**, el **salto del logotipo** recargando `/servicios` —que hasta `#266`
   no se había visto NUNCA— y su hover después, y el **CTA flotante** en un teléfono.
2. ❗❗ **BLOQUEADO POR EL OWNER, y es una exportación**: el **relevo de la Y** del logotipo. El SVG
   dibuja «PLA» y la Y la hace la silueta, así que el logotipo se lee incompleto desde que carga
   hasta que la figura aterriza (**1,47 s**: 466,7 de espera + 1.000 de vuelo).
   `[DECIDIDO owner]`: lo exporta con la Y. **Qué tiene que traer el fichero está escrito al detalle
   en `INSTALACION-CLIENTE.md` §4.a.sexies** —`<path id="uy">` en `<defs>` más sus `<use>`, y dentro
   del grupo que se anima—, con los números del relevo ya medidos: cuando llegue, es una tanda corta.
   ⚠️ **Él entregó en su lugar el lockup entero en HTML** (texto vivo en Lilita One, con el relevo
   hecho). Adoptarlo es otra decisión, no un atajo: mete la marca en el MARCADO del producto (contra
   el white-label), su `<link>` es de Google Fonts —usamos Bunny por RGPD— y retira `InlineSvg` con
   sus guardas. ▶ A favor: `lilita-one:400` **ya viaja** en el `<link>` de todas las vistas, así que
   no costaría descarga. **Sin decidir.**
3. ❗ **Siguen abiertas las dos preguntas del owner de `#262`**, las dos a una línea de código: el
   **color de la pista** del interruptor (hoy `--ok` verde, el artboard usa Lima Bote) y el residuo
   de 3,6–4,7 px entre la pista y las mayúsculas.
4. **Microanimaciones** (`Microanimaciones PJP.dc.html`) — el plan del owner. ⚠️ **Medido: la mitad
   ya está hecha** (las cuatro curvas y las siete duraciones entraron en la tanda 2d, `#222`, y los
   tres bucles en `#259`). Lo que queda son **tres piezas de tamaño muy distinto**:
   · **las cuatro animaciones que «sí cuentan algo»** —cargando→confirmado, el sello de reserva
     («PLAZA 12 cae de −34 px con −16° y aterriza en −6°»), la cascada de franjas con color que
     informa y el salto—: tocan el CAJÓN, no la landing;
   · **las cuatro PROHIBICIONES** (no rebotar al salir, no animar texto, no animar el color de
     marca, no cascada en cada scroll): son reglas, y se hacen ejecutables como guardas;
   · ❗ **el MOVIMIENTO REDUCIDO, que es un defecto YA PRESENTE**: su norma dice que con
     `prefers-reduced-motion` desaparecen desplazamientos y escalas **pero se mantienen los fundidos
     de opacidad de 120 ms**, porque «quitar también el fundido deja la interfaz saltando de estado
     sin avisar». Medido: de nuestros **29** bloques, **20 apagan con `none`** — o sea que
     contradicen su norma y le saltan los estados a quien activa esa preferencia.
5. **Elementos fachada** (`Elementos Fachada.dc.html`), después.
6. ❗ **Las dos preguntas del owner de `#262`**, a una línea de código cada una: el **color de la
   pista** del interruptor (hoy `--ok` verde, su artboard usa Lima Bote) y el residuo de 3,6–4,7 px
   entre la pista y las mayúsculas.
7. ⬜ **Lo que sigue sin caber**: la composición del cierre en 390×667. `#264` le devolvió **30 px**
   al recortar el pie, así que la ficha de `DEUDA` está **menos apretada pero abierta**.

---

# ❗ SI ENTRAS NUEVO (2026-08-29, tarde · carril C): EL SET DE ICONOS, EL INTERRUPTOR `6d` Y EL LOGOTIPO — `#256` → `#263`, **Y STAGING YA VA CON LA MARCA DEL CLIENTE**

**`git fetch` antes de nada.** ⚠️⚠️ **Con dos agentes sobre `main`, mirar el remoto al ELEGIR
número no basta: hay que volver a mirarlo al PUBLICAR.** Esta sesión usó **`#256`–`#263`**; el
carril A publicó **`#243`–`#248`** (cumple mixto) **después** de mi push, y el rebase entró limpio.
Antes de desplegar, mi `main` estaba **un commit por detrás** y el dry-run del deploy avisó de que
borraría los ficheros del otro agente — **no era un daño, era mi árbol viejo**. ▶ **Haz `git pull`
antes de cualquier despliegue** y corre la suite sobre el árbol COMBINADO: nadie más lo hace.

## ▶ Qué hizo esta sesión, en una línea cada cosa

| | |
|---|---|
| `#256` | La **demo del minijuego** corre ya en el punto estático. La señal es que el **lienzo SE VEA**, no el anclaje |
| `#257` | El **set de iconos del artboard** entra en el producto: 26 componentes → 55, con la anatomía hecha ejecutable |
| `#258` | **Auditoría del set ejecutada**: 61 componentes, `DRAWER_OWN` vacía, los cuatro ESTADOS ya son **pegatina** |
| `#259` | El cargador es **«Tres botes»** y el marcador de producto viaja **en el contrato**, no se deduce de `is_pack` |
| `#260`/`#261` | Dos vueltas del owner sobre el hero: el icono del CTA sale de la altura del botón, y el interruptor se va **en línea a la derecha** |
| `#262` | **El interruptor es ya el `6d` del canvas**, en cuatro vueltas: idéntico · rótulo dentro · a la altura de las mayúsculas · pegado y a la derecha también en móvil |
| `#263` | **El logotipo no saltaba en once de las doce vistas**, y su sombra era dura por el ajuste anterior |

## ❗❗ LO QUE MÁS IMPORTA QUE SEPAS

1. **STAGING VA YA CON LA MARCA DEL CLIENTE** (2026-08-29, `https://jumpweb.sites.aelium.app`).
   Desplegado el commit `8cdaaa6` —los dos carriles juntos— y **encima instalado a mano el paquete
   de Play Jump Park**, que el `rsync` excluye a propósito: los **10 ficheros** (`client.css`,
   logotipo, favicon, iconos PWA, tag), `THEME_FONTS` en el `.env` remoto, y en BD
   `theme.action = #F2711C`, `theme.brand = #1AA9DE`, `business.name` y los tres `seo.title.*`.
   ⚠️ **Si vuelves a desplegar, el paquete NO viaja**: el `rsync --delete` no lo borra (está
   excluido), pero un servidor nuevo se queda sin él. La receta está en `INSTALACION-CLIENTE.md`.
   ⚠️ **Los datos LEGALES siguen siendo de demo a propósito**: `business.legal_name` («SaltoPark
   S.L.»), `business.nif` (`B-12345678`), `business.address` («Villaparque») y `business.domain`.
   **No se inventaron**: son los que imprimen facturas, y meter un NIF falso ahí es un dato falso en
   un documento fiscal. **Hay que pedírselos al owner.**
   ⚠️ Copia del `.env` remoto antes de tocarlo en `.env.bak-20260829-123102`.

2. ⚠️⚠️ **La lección de método de la sesión: medir la dimensión correcta de la cosa equivocada da un
   verde perfecto.** El interruptor «estaba bien» según mi verificación —las ALTURAS coincidían con
   ±1,2 px en seis anchos— y el owner lo vio **17 px más abajo**: nunca comparé las POSICIONES. El
   instrumento que faltaba es una **sonda de línea base** (un `inline-block` vacío de alto 0 como
   primer hijo, cuyo borde inferior se apoya en ella). Está en `#262` §9.3.

3. ⚠️⚠️ **Dos trampas de CSS que no fallan, solo salen mal, y ninguna guarda las ve:**
   · **Un token en `em` NO es una longitud** — se sustituye como texto y el `em` lo resuelve *el
   elemento que lo usa*; un descendiente que cambia su `font-size` lo ve valer otra cosa (el rótulo
   salió a 1,84 en vez de 5,6). · **Un `inline-flex` toma su línea base de su PRIMER ÍTEM**, no de
   su borde inferior, y un `inline-block` con flex dentro **sigue propagándola**: lo zanja
   `overflow` ≠ `visible`. Las dos, en `tema-por-instalacion.md` §25.8.1 y §25.9.1.

4. ❗ **`#263`: que la pieza llegue no es que se mueva.** El logotipo llevaba desde `#254` sin
   animarse en once vistas, con una guarda mirando al lado que comprobaba que `id="fig"` llega al
   documento — y eso pasaba en verde con la animación muerta. Guarda nueva y mutada con el fallo
   real. ⚠️ **El asset del cliente está BIEN y no hay que rehacerlo**: 27 pasos de extrusión por
   palabra, 16 en la silueta, los dos degradados; es la receta del mockup en contornos.

5. ⚠️ **El canvas SIGUE sin poder bajarse solo**: `DesignSync` sin autorización y `/design-consent`
   devuelve **403**. El LOTE 6 llegó **pegado en el chat**, con la codificación rota en los acentos,
   así que **`mockup_playjumppark/Iconos PJP.dc.html` sigue siendo la copia del 27** y no tiene el
   `6d`. Para refrescarla: `/login` y luego `/design-login`, o exportar el fichero al directorio.

## ▶ POR DÓNDE SIGUE

1. ❗ **Dos preguntas abiertas del owner, las dos a una línea de código:**
   · **el COLOR de la pista del interruptor** — hoy `--ok` (verde), el artboard usa **Lima Bote**
   (`#A3C21C`, que tiene token `--strip-2` pero con el rol de la tira del pie);
   · **el residuo de 3,6–4,7 px** entre la pista y las mayúsculas, que viene de que `1cap` da la
   altura DECLARADA por la fuente y la de Bungee es un 5,6 % menor que la que pinta.
2. ~~**La tanda del ÁREA TÁCTIL 44**~~ ✅ **HECHA** (`#264`, 2026-08-29 tarde) — y de paso quedó
   claro que aquellos «115» eran instancias: los controles distintos eran **37**.
3. **Microanimaciones** (`Microanimaciones PJP.dc.html`) y después **elementos fachada**
   (`Elementos Fachada.dc.html`), que es el plan que el owner dio al abrir la sesión.
4. **El OJO del owner** sobre lo de esta sesión, ahora ya en staging con su marca puesta.


# ❗ SI ENTRAS NUEVO (2026-08-29 · carril C): LA PORTADA ESTÁ COMO EL OWNER LA PIDIÓ — `#250` → `#255`

**`git fetch` antes de nada.** ⚠️ **Rangos de numeración repartidos**: el carril A toma `#239`–`#249`
y el C sigue por `#250`. Esta sesión usó **`#250` a `#255`**; la siguiente del carril C empieza en
`#256`. **Mirar el remoto al elegir número no basta: hay que volver a mirarlo al PUBLICAR.**

## ▶ Qué hizo esta sesión, en una línea cada cosa

| | |
|---|---|
| `#250` | **La COLUMNA del sitio es la del mockup**: 1176 px, no 1380. El ancho se escribe **una vez** en `--col-max` y de ahí salen sus tres formas. ⚠️ No se ensanchó: se **estrechó** |
| `#251` | **La transición del cierre no era idéntica**: la retirada del armazón leía el progreso SUAVIZADO y es del CRUDO, y la curva era lineal donde el mockup la hace **cúbica** |
| `#252` | **El IMÁN de los dos puntos estáticos**, el pie a **una fila deslizante** y **fuera la marquesina** de palabras |
| `#253` | **Ocho puntos, y dos eran fallos**: el cajón se abría **detrás del juego** (z-index 210 sobre 160) y el armazón **nunca se retiraba al bajar** en la portada |
| `#254` | **El cierre ocupa el hueco que le deja el pie**, el hero se queda con **UN** CTA, «**Diversión ON**» con interruptor, y **el logotipo salta** — servido en línea |
| `#255` | Un test **rojo un minuto al día**, cazado por el reloj al cerrar |

## ❗❗ LO QUE MÁS IMPORTA QUE SEPAS

1. ❗❗ **SI ESCRIBES CSS**: `--wrap-gutter` es solo para elementos **a sangre completa** (su `100%`
   mide el contenedor); un contenedor con `max-width` propio usa **`--col-gutter`**. Y **el ancho de
   la columna no se escribe a mano**: sale de `--col-max`. Dos guardas lo vigilan.
2. ❗❗ **SI TOCAS LA MARCA**: el logotipo se sirve **EN LÍNEA** para poder animarlo, y eso **cambia
   el modelo de amenaza** — dentro de un `<img>` un SVG es inerte y en línea **no**. Todo pasa por
   `InlineSvg`, lista blanca y **todo o nada**. Nunca lo saltes «porque el fichero es nuestro».
3. ❗❗ **SI TOCAS UNA CAPA**: la escala por rol la fija `LayerOrderTest` —página hasta 110 · cookies
   140 · lo que el cliente ABRE 150-160 · avisos 200+—. Un `z-index` «muy arriba» es cómo el cajón
   acabó abriéndose detrás del juego con el scroll bloqueado.
4. ⚠️⚠️ **Cinco guardas nacieron CIEGAS en cuatro días**, y una de ellas por **tres motivos
   distintos** en la misma sesión (mutación incompleta · el nombre vivo dentro de su propio
   comentario · el modificador siendo la CLAVE del array). *Una guarda que nunca ha estado roja no
   ha demostrado nada, y una que se muta una sola vez tampoco.*
5. ⚠️⚠️ **Los instrumentos mintieron cuatro veces**: un comparador midiendo la envolvente de un
   elemento **girado** · un barrido de roturas **sin pasada de control** (marcó 31, las mismas 31
   que antes del cambio) · `npx vitest` cuando este repo corre el JS con el runner de **Node** · y
   un detector de dirección de scroll que se creía un evento de **reflujo**. Antes de creerte que
   algo está roto, comprueba el instrumento.
6. ⚠️ **La portada crece mientras se lee** —55 imágenes, 51 perezosas, **ninguna declara
   proporción**—, así que **una medida en píxeles sobre ella nace caducada**: asevera el ESTADO
   (`--hero-p`, `--cierre-q`), no la posición. Ficha en `DEUDA.md`.

## ▶ POR DÓNDE SIGUE

0. ❗ **El OJO del owner.** Lo que más conviene mirar, porque una sonda no lo puede juzgar: **el
   tacto del imán** (¿retiene donde debe, suelta cuando insistes?), **el salto del logotipo** y **el
   punto estático del cierre** en su propia pantalla.
1. ⬜ **Lo que sigue sin caber**: la composición del cierre **no entra en 390×667** (un iPhone SE).
   El contenido ya mide más que el hueco, así que las salidas son de PRODUCTO —menos enlaces en el
   pie, o un CTA en vez de dos en el cierre— y se deciden mirando. Ficha en `DEUDA.md`.
2. ⬜ **Dos fichas nuevas de `DEUDA.md`**, ninguna urgente: el **peso del logotipo en línea** (~64 KB
   en las doce vistas, con dos salidas propuestas) y las **imágenes sin proporción declarada**.
3. ⬜ Lo de antes sigue igual: los **iconos** del canvas (con la decisión previa de si entran en el
   producto o en el paquete de tema) y el **contenido real del cliente**.
4. ⚠️ **Y el owner tiene que quitar `client-logo-ink.svg` de su `marca/`**: se retiró de la
   instalación local para que el menú enseñe el logotipo en color, pero volverá en el próximo
   despliegue si sigue en el paquete.

---

# ❗ SI ENTRAS NUEVO (2026-08-28, noche · carril C): la COLUMNA DEL SITIO ES LA DEL MOCKUP — `#250`

**`git fetch` antes de nada.** ⚠️ **Hay rangos de numeración repartidos** (los pactó el carril A en
la cabecera): A toma `#239`–`#249` y C sigue por `#250`. Esta tanda es la primera del rango nuevo, y
se renumeró **dos veces** antes de fusionar — de `#239` a `#240` y de ahí a `#250`.

## ▶ El encargo

El owner, después de `#238`: «para hacer la landing al mockup ¿debemos cambiar toda la estructura?
más ancha la landing ¿no?» → se midió, se simuló y se le enseñó con capturas; respondió **«procede
así, idéntico al mockup. Y el estado normal del hero del footer, no full viewport, con las
dimensiones correctas al mockup»**.

| | |
|---|---|
| `#250` · la columna | ❗❗ **La columna del sitio pasa de 1380 a 1176**, la del mockup. ⚠️ **No se ensancha: se ESTRECHA.** Y había prueba interna de que la suya era la buena: **el hero ya acababa en 1240** (su número) mientras las secciones iban a 1380 — dos columnas contradictorias que nadie decidió |
| `#250` · el token | El ancho se escribe **UNA vez** (`--col-max`) y de ahí salen sus **tres formas**: `width` (`.wrap`) · SANGRADO (`--wrap-gutter`) · caja EXTERIOR (`--hero-w-end`, `.reserve`, `.menu__inner`). Guarda: `ColumnIsDeclaredOnceTest`, 6 mutaciones |
| `#250` · el reposo | El hero del cierre, comparado dimensión a dimensión con el artboard: **27 de 28 idénticas** a 1280 y a 390 |

## ❗❗ Lo que MÁS importa que sepas

1. ❗❗ **No escribas un ancho de columna a mano.** Derívalo de `--col-max`. Por encima de 1000 px un
   ancho ya no es una medida tipográfica: es la columna, y la guarda te lo dirá.
2. ⚠️⚠️ **Dos de las cuatro divergencias que dio el comparador NO eran del código**, y comprobarlo
   ahorró dos cambios equivocados: el tag va **girado −7°** y `getBoundingClientRect()` devuelve la
   envolvente del giro; y el canto y la sombra de la tarjeta ya los ponía **`client.css`**.
   ▶ *Cuando un comparador dice que algo no cuadra, la primera hipótesis sigue siendo el comparador.*
3. ⚠️⚠️ **Un barrido de roturas sin CONTROL inventa roturas.** El detector marcó 31 elementos «fuera
   de ventana» con la columna nueva… y **los mismos 31 con la vieja**: marquesinas, tira de zonas y
   una polaroid girada, todas a propósito. Sin la pasada de control habría reportado 31 fallos.
4. ⚠️ **El rebase dejó el bundle SSR RANCIO y salieron 34 rojos que no eran del cambio.** Lo dijo la
   guarda que el otro carril construyó para eso. `npm run build && npm run build:ssr`.
5. ❗ **Queda UNA divergencia real con el artboard y es del cliente consigo mismo**: el canto de los
   CTA del cierre, 10 contra 14. **14 no está en su escala declarada** y 12 de sus 19 botones usan
   10. Quinta contradicción de sus fuentes; ficha en `DEUDA.md`, se arregla en su `client.css`.

## ▶ POR DÓNDE SIGUE

0. ❗ **El OJO del owner sobre la columna nueva**: es un cambio de aire en las **doce vistas**, y una
   captura no lo valida. Lo que más conviene mirar es `/precios`, `/servicios` y `/cumpleanos`, que
   son las de rejillas más densas.
1. ⬜ Lo de antes sigue igual: los **iconos** del canvas, el **contenido real** del cliente, y el ojo
   del owner sobre las tandas visuales anteriores.

---

# ❗ SI ENTRAS NUEVO (2026-08-28, tarde-noche · carril C): la COLUMNA — `#238`

**`git fetch` antes de nada.** ⚠️ El carril A publicó `#237` mientras esta sesión trabajaba; el
número se eligió **mirando el remoto al PUBLICAR**, no solo al empezar, y por eso ésta es `#238`.

## ▶ El encargo y lo que salió

`[DECIDIDO owner]`: seguir con el hero del cierre, «el estado normal y a full vw está roto; en estado
normal no tiene el width correcto, tiene demasiada altura y oculta el footer. **1:1 al mockup**».
Las tres cosas eran ciertas y salían de **dos defectos que se sumaban** — ninguno visible a 1280 px.

| | |
|---|---|
| `#238` · la columna | ❗❗ **`--wrap-gutter` dentro de una caja ACOTADA mide al PADRE.** La tarjeta del cierre medía **700 px a 1920** y **160 a 2560**; **`.menu__inner` tenía el mismo fallo desde `#201`** y a 2560 su columna eran **60 px**. Entra `--col-gutter` (longitud fija) y `CappedContainerGutterTest` con 4 mutaciones |
| `#238` · el alto | El hueco del minijuego estaba **reservado dos veces** (`.reserve__box` y `.reserve__body`): **220 px de aire muerto** a 1280 — 762 de alto contra los 542 del mockup — y por eso la tarjeta anclada dejaba **128 px** de pie visible en vez de 348 |
| `#238` · el lienzo | Medía `clamp(122px, 14vw, 156px)` en vez de los **150** fijos del mockup: en un teléfono salía a 122 y **saltaba a 150 al abrirse**, y como el alto ES el zoom, se jugaba a **k = 0,41** en vez de 0,5 |
| `#238` · el titular | En teléfono el mockup usa **otra escala** bajo 620 px; con un solo `clamp` mandaba el suelo y a 320 px salía **un 43 % grande**. Es `#220` otra vez, en el bloque de al lado |

## ❗❗ Lo que MÁS importa que sepas

1. ❗❗ **Si escribes CSS: `--wrap-gutter` es SOLO para elementos a sangre completa.** Su `100%` es un
   porcentaje y mide el contenedor. Un contenedor con `max-width` propio se sangra con
   **`--col-gutter`**. La guarda te lo dirá, pero mejor saberlo antes.
2. ⚠️⚠️ **Medir no basta: hay que medir DONDE el fallo puede aparecer.** Las sondas de armazón y de
   tema corrieron siempre a **1280 y 390** — los dos anchos donde este defecto no existe. El menú
   pasó por cinco tandas, por el ojo del owner y por 31 aserciones con la columna rota.
   ▶ **Cuarta vez que este carril paga una lección de método sobre sus propios instrumentos.**
3. ⚠️ **Dos reservas del mismo hueco no se ven por separado: se ven sumadas**, y no parecen un fallo
   — parecen «este bloque es alto». Cuatro pasadas de captura no lo cazaron.
4. `[DECIDIDO owner]` **la tarjeta va en la columna del MOCKUP** (1240 → **1176**), no en la del pie.
   Conserva el gesto de `#229`: el cierre **nace más estrecho que la tarjeta del hero** y crece hasta
   comérselo todo.

## ▶ POR DÓNDE SIGUE

0. ❗ **El OJO del owner**, y ahora hay algo NUEVO que mirar además del cierre: **el menú a pantalla
   completa en una pantalla ancha** — su columna acaba de pasar de 700 px a 1176 a 1920.
1. ❗ **Una decisión suya, con los números en `specs/tema-por-instalacion.md` §17.4**: en el mockup la
   tarjeta del cierre y el pie **comparten columna**; aquí el pie sigue en `.wrap` (1380) y a 1920 el
   escalón es de **102 px por lado**. Igualarlos = bajar **toda la columna del sitio** a 1240/1176,
   doce vistas. No se toma desde una tanda de cierre.
2. ⬜ Lo de antes sigue igual: los **iconos** del canvas, el **contenido real** del cliente y el ojo
   del owner sobre las nueve tandas visuales anteriores.

---

# ❗ SI ENTRAS NUEVO (2026-08-28, CIERRE del carril C): el TEMA tiene sus SEIS mecanismos, el ARMAZÓN está completo en los doce anchos, y NO QUEDA NADA DE AGENTE EN ESTE CARRIL

**`git fetch` antes de nada y lee las tres filas de la cabecera antes de elegir tarea.**

## ▶ La sesión del 2026-08-28 (09:00 → 15:30), en una línea cada cosa

| | |
|---|---|
| `#216` | El **armazón NACE BAJO EL HERO** como el mockup — y por eso **el hero recupera sus dos botones**, reabriendo `#195`. El **CTA doble** se alinea en sus OCHO medidas. Y entra el **paquete de MARCA** del 2.º cliente: los huecos pasan de 2 a 9 |
| `#217` | Las **TRES piezas que el ojo del owner vio y la sonda no**: las sombras del racimo (entra un CUARTO rol), la forma del botón de menú (rect + etiqueta + dos rayas que ROTAN) y el logotipo, que **flota** sin pastilla |
| `#218` | El logotipo **no medía lo que el mockup**: su `height:54px` es la CAJA que lo envuelve, no el dibujo. Son **70** |
| `#219` | El **arnés de mutación envenenó la caché de vistas** de Blade y la web sirvió el fallo con el fichero correcto en disco. Suite verde, `pre-push` verde |
| `#220` | El **titular del hero no cabía en un teléfono**, y la culpa era del **suelo de un `clamp`** |
| `#221` | La **2c·4b**: el menú a pantalla completa manda también en móvil. **El armazón queda completo en los doce anchos** |
| `#222` | La **2d, el MOVIMIENTO**: el SEXTO mecanismo. 4 curvas y 7 duraciones; **cero literales fuera de la escala** |

## ❗❗ LO QUE MÁS IMPORTA QUE SEPAS

1. ❗❗ **ESTE CARRIL NO TIENE NADA PENDIENTE DE AGENTE.** El tema está completo (seis mecanismos),
   el armazón está completo (doce anchos) y la marca del 2.º cliente está instalada. **Todo lo que
   queda es del owner** — está en la fila «C · tema» de la cabecera.
2. ⚠️⚠️ **`#195` ESTÁ REABIERTO** (`[DECIDIDO owner]`): el hero vuelve a llevar sus dos botones.
   No es un cambio de gusto: **es lo que hace posible que el armazón se oculte**, porque el mockup
   puede permitirse esconder su cabecera solo porque su hero ofrece la acción. Las dos mitades no
   se pueden separar.
3. ⚠️ **Si tocas `public/css/site.css` o `landing.css`, ábrelas antes de escribir**: la 2d hizo
   **570 sustituciones** en las dos. Cualquier rama vieja sobre esas hojas dará conflictos grandes.
4. ⚠️⚠️ **TRES lecciones de método que esta sesión pagó, y las tres se repiten:**
   · **Medir no es mirar.** Tres defectos los cazó el OJO del owner con la suite verde y las
   mutaciones mordiendo: el CTA doble desalineado, los botones del hero apilados y el logotipo
   pequeño. La sonda medía existencia, color y tamaño — nunca **dónde**.
   · **Una caché rancia da verde con el código roto.** Pasó dos veces: el bundle SSR (`#216`) y la
   vista compilada de Blade (`#219`). Tras correr un arnés, **mira la página**.
   · **La conversión a medias vuelve.** `#196` la documentó, y `#222` la repitió con otra
   herramienta: un filtro que mira el CONTEXTO acierta hasta que el contexto cambia.
5. ⚠️ **El cajón lateral de móvil (`.mob-menu`) está APAGADO, no retirado**: ~200 líneas de CSS y un
   bloque de blade que **ningún test cubre**. Ficha en `DEUDA.md`; retirarlo pide su propia pasada
   con `CONVENCIONES §3.quater`.

## ▶ POR DÓNDE SIGUE ESTE CARRIL

**Por el owner.** Sin su ojo, lo único honesto que queda de agente es deuda declarada, y ninguna
urge:
- retirar el cajón lateral apagado (`DEUDA`), con su auditoría de tests;
- servir un `manifest.webmanifest` para que el icono *maskable* del cliente sirva de algo (`DEUDA`);
- las cuatro contradicciones de las fuentes del cliente, todas anotadas y **todas decididas** a
  favor de lo que él validó.

---

# ❗ SI ENTRABAS NUEVO A MEDIODÍA (2026-08-28, carril C): el ARMAZÓN NACE BAJO EL HERO y el paquete de MARCA del 2.º cliente ESTÁ INSTALADO

**`git fetch` antes de nada.** ⚠️ El carril A empujó `#215` mientras esta sesión trabajaba; el
número se eligió **mirando el remoto** y esta tanda es **`#216`**.

## ▶ Lo que hizo la sesión del 2026-08-28 (09:00 → 12:00), en una línea cada cosa

| | |
|---|---|
| `#216` · marca | **El paquete de MARCA del 2.º cliente está entregado e instalado.** El owner subió `marca/` al canvas; los huecos del producto pasan de **2 a 9** (logotipo sobre tinta, respaldo raster, `.ico`, apple-touch y los PNG de Android) |
| `#216` · armazón | **El armazón NACE BAJO EL HERO** (`[DECIDIDO owner]`, como el mockup): con el hero a pantalla completa **no hay logo, ni CTA, ni hamburguesa** |
| `#216` · hero | **Y por eso el hero recupera sus dos botones**, reabriendo `#195`: el mockup puede ocultar su cabecera **porque su hero ofrece la acción** |
| `#216` · CTA | Las **ocho** diferencias medidas del CTA doble, alineadas: orden, anchos fijos 224/56, alturas iguales, el colapso, el retardo del rótulo, la sombra, el hover y el color dentro del menú |
| `#222` · la 2d | ✅ **El MOVIMIENTO es un sistema: 4 curvas y 7 duraciones**, y con él **el tema tiene sus SEIS mecanismos**. Un paquete retempla el tacto de la web entera con once tokens. ❗ **No se revisa con una captura: se revisa interactuando** |
| `#221` · la 2c·4b | ✅ **El menú a pantalla completa manda también en móvil, y con eso el ARMAZÓN está completo en los doce anchos.** El cajón lateral queda APAGADO (ficha en `DEUDA`), no retirado |
| `#220` · el hero móvil | ❗ **El titular del hero no cabía en un teléfono**: un `clamp` no es una talla adaptable, es una talla con dos topes y **el suelo manda**. Con él, tres divergencias más: el corte en 720 en vez de 620, un centrado que el mockup no tiene, y que **el titular no encogía con el hero** |
| `#219` · el arnés | ❗❗ **El arnés de mutación ENVENENÓ la caché de vistas de Blade**: restaurar con `shutil.move` conserva el mtime, así que el compilado con la mutación se cree más nuevo que el fuente y **gana para siempre**. La web servía el fallo con el fichero correcto en disco, la suite verde y el `pre-push` verde — **solo lo vio el ojo del owner en una captura**. Arreglado con `os.utime` en los cinco arneses; la trampa entra en `CONVENCIONES §3.quater`, que pasa de 4 a 6 |
| `#218` · el logo | ❗ **El logotipo no medía lo que el mockup**: su `height:54px` está en el `<a>` que ENVUELVE el lockup y el dibujo desborda por sus contornos (54 declarados, **68 reales**). A 70 px la tinta coincide al 1,5 % |
| `#217` · el ojo | ❗ **Las TRES piezas que el owner vio distintas y la sonda no**: las **sombras** del racimo (entra un CUARTO rol, el mobiliario flotante), la **forma del botón de menú** (era un círculo; ahora el rectángulo del mockup con etiqueta y dos rayas que ROTAN) y el **logotipo** (fuera la pastilla, 26 → 54 px y `drop-shadow` que sigue la SILUETA) |

## ❗❗ Lo que MÁS importa que sepas antes de tocar nada

1. ⚠️⚠️ **`#195` está REABIERTO por decisión del owner.** Hasta la mañana del 28 la postura era «el
   mockup ha devuelto el CTA al hero y nosotros no lo copiamos». Por la tarde cambia, y el motivo
   es que **las dos mitades no se pueden separar**: ocultar el armazón sin devolver los botones
   deja la primera pantalla sin comprar y sin navegación.
2. ❗ **Ya NO hace falta pedirle el logotipo al owner**: está entregado. Lo que sigue esperando de
   él es **su OJO en navegador** — ahora son **nueve** tandas visuales sin mirar.
3. ⚠️⚠️ **`DesignSync · get_file` TRUNCA los binarios a 192 KiB y no falla**, y **transcribir base64
   desde el contexto corrompe el fichero en silencio** (medido: 4.632 B de 6.900, con cabecera y
   dimensiones válidas). Los rasters se **generan del vector**. Detalle en
   `mockup_playjumppark/README.md`, que es donde se mantiene.
4. ✅ **El logotipo ya está a 54 px** (`#217`): lo pidió el owner al verlo. Y con él se fueron la
   pastilla de detrás —acotada al suelo de TEXTO, que sí la necesita— y la sombra de caja, que
   pasa a `drop-shadow` porque una marca recortada necesita que su sombra siga la **silueta**.
5. ❗❗ **Y hay una trampa que ni la sonda ni la suite ven: la CACHÉ RANCIA.** `#219`: el arnés de
   mutación dejó una vista compilada con el fallo dentro y Blade la dio por buena porque el fuente
   restaurado tenía fecha ANTERIOR. **Verde en todo, roto en la web.** Misma familia que el bundle
   SSR rancio de `#216`. ▶ Tras correr un arnés, **mira la página**, no solo el rojo/verde.
6. ⚠️⚠️ **La sonda no ve la FORMA.** Las tres cosas de `#217` las cazó el ojo del owner con la
   suite en verde y 15 mutaciones mordiendo: la sonda medía existencia, color, tamaño y estado —y
   todo estaba bien—. Es la tercera vez en dos días. **Medir no es mirar.**

---

# ❗ SI ENTRAS NUEVO (2026-08-28, cierre anterior del carril C): el TEMA tiene sus CINCO mecanismos y el ARMAZÓN pasó por el OJO del owner

**`git fetch` antes de nada y lee las tres filas de la cabecera antes de elegir tarea.**

## ▶ STAGING desplegado el 2026-08-28 — commit `3bda1a9`

`https://jumpweb.sites.aelium.app` · **9/9 verificaciones en verde**: `/up` 200 · `/` 200 ·
`robots.txt` con `Disallow: /` · 5 tareas del scheduler · **0 migraciones pendientes** (aplicó la
`add_surname_and_relationship_to_dependents` del carril A) · 0 `failed_jobs` · 0 jobs varados.
▶ Comprobado además con navegador contra el servidor REAL: la coreografía del cierre llega a
`--cierre-p: 1` con la tarjeta en 1260×880 anclada, y **cero errores de JavaScript**.

⚠️⚠️ **STAGING NO TIENE EL PAQUETE DE TEMA DEL CLIENTE, y eso es correcto.** Medido: `client.css`,
`client-logo.svg`, `client-logo-ink.svg`, `client-favicon.svg` y `client-tag.svg` dan **404**. Están
gitignorados y excluidos del `rsync --delete` a propósito (`DECISIONES #1`: este repo es el PRODUCTO
y no lleva la marca de nadie).

▶ **Consecuencia práctica, y hay que decirla antes de que alguien la lea como un fallo: staging
enseña el PRODUCTO en crudo, no Play Jump Park.** Sin fuentes de rótulo, con el naranja del producto
en vez del cian del cliente, sin tag de la ciudad y sin las manchas del menú.

▶ **Y eso es una VALIDACIÓN, no un problema**: los seis mecanismos white-label **degradan bien**.
Nada se rompe ni deja una caja vacía — el tag no pinta (`background-image: none`), las manchas del
menú no pintan (máscara transparente por defecto, que es justo por lo que se eligió ese default en
`#228`) y el minijuego se dibuja con la paleta del producto porque **lee tokens, no hex**.

❗ **Para que el owner vea el DISEÑO en staging hay que instalar el paquete allí**: son cinco
ficheros y el procedimiento está en `INSTALACION-CLIENTE.md` §4. **No se hizo en esta sesión**: subir
la marca de un cliente a un servidor es una decisión suya, no de maquetación.

---

## ▶ La sesión del carril C del 2026-08-28 (tarde): **el mockup 1:1** — `#225` → `#235`

`[DECIDIDO owner]`: «lo quiero idéntico 1:1 — hero, menú, transiciones, animaciones, y lo mismo en
el footer y el hero del footer». **Todo empujado y verde.**

| | |
|---|---|
| `#225` | La **barra de móvil ES el mismo botón** que el racimo de la cabecera. Medido antes: **100 px de alto contra 54**, chip 64×42 contra 30×26 y **las dos mitades naranjas**. `.cta-prime` retirado entero ⇒ **el rol de ACCIÓN se queda sin ningún CTA de armazón** |
| `#226` | La **tira de marca** sube al hero y **VIAJA** (filo superior → subrayado, 28 px bajo la tarjeta). El hero se **vacía** (`[DECIDIDO owner]`) ⚠️⚠️ y aparece que **`html, body { overflow-x: hidden }` rompía TODOS los `sticky`**: el hero **nunca se pegó** desde `#195` y dejaba **569 px de banda vacía** |
| `#227` | El **CTA de la primera pantalla**, con relevo al armazón (se apaga cuando el otro se enciende, atado al mismo token) ⚠️ destapa **once reglas** que pintaban el CTA de amarillo dentro del hero |
| `#228` | El **menú a dos columnas** con vista previa del destino señalado, y las manchas de marca. El subtítulo **se muda** a la tarjeta |
| `#229` · `#233` | El **hero del CIERRE**. `#229` lo hizo con un pegajoso y **era el gesto equivocado**; `#233` lo rehace con `position: fixed` y el recorrido **después del `</footer>`**: la tarjeta crece **mientras el pie pasa por detrás** |
| `#230` | El **estado de apertura había desaparecido de la web entera** — lo vio la suite completa, no las tandas acotadas |
| `#231` | El **minijuego** «Salta la ciudad», en **trozo aparte de 12 kB** (`import()` dinámico) |
| `#235` | El cierre **1:1 de verdad**: tag de la ciudad, rol de acción en el CTA, los CTA **se apartan al jugar**, el **espacio arranca** la partida, y el **pie a una fila** |

### ❗❗ Lo que MÁS importa que sepas de esta tanda

1. **DOS hallazgos que afectan a TODOS los carriles, no solo al tema:**
   - `overflow-x: hidden` en `html, body` convierte al elemento en **contenedor de scroll** y rompe
     cualquier `position: sticky`. Arreglado con `clip` y guardado por
     `StickySurvivesTheRootOverflowTest`. **Si añades un sticky en cualquier vista, ya funciona.**
   - **Un selector más ancho que su intención no falla el día que se escribe: falla el día que
     alguien añade el segundo caso que casa.** Once reglas decían `[data-surface="ink"] .cta-med`
     cuando su propio comentario decía «dentro del menú».
2. ⚠️⚠️ **Cuando el cambio es de ARMAZÓN —algo que se pinta en las doce vistas— el radio de las
   guardas afectadas NO se adivina por el nombre del fichero.** Pasó dos veces: el estado de
   apertura lo cazó `HeroStatusTest` y el eslogan del pie lo cazó un test de **Ajustes**. Las tandas
   acotadas iban verdes las dos veces. ▶ **Empuja a mitad de sesión**, no solo al final.
3. ⚠️ **Si el encargo es «1:1», saca el bloque ENTERO del artboard.** `#229` y `#231` lo
   reconstruyeron de memoria y el owner tuvo que corregir el cierre **dos veces** (`#233`, `#235`).
   Leerlo entero son 7 KB y cuesta una lectura.
4. ⚠️ **Culpar al instrumento es la SEGUNDA hipótesis, no la primera.** En `#235` diagnostiqué dos
   veces el parser de una guarda y la causa era **un `</div>` de más** en mi propio marcado.
5. ⚠️⚠️ **La numeración de `DECISIONES.md` colisionó DOS veces** con el otro carril en una sola
   sesión. **Mirar el remoto al ELEGIR número no basta: hay que volver a mirarlo al PUBLICAR**, y
   renumerar **antes** de fusionar (después, una sustitución global corrompe las entradas del otro).

### ▶ POR DÓNDE SIGUE ESTE CARRIL

0. ❗ **El OJO del owner**, que es lo único que falta de todo lo anterior. Un navegador headless
   mide, no valida (`CONVENCIONES §3.bis`). Lo más importante de mirar: **el tacto del salto** del
   minijuego y **el relevo del CTA** al bajar por la portada.
1. ⬜ **Los ICONOS del canvas** — el encargo pendiente más grande. `Iconos PJP.dc.html` declara
   **47 iconos UI + 3 cargadores + 14 zonas (7 familias) + 4 estados**, en LOTES de exploración con
   variantes marcadas «ACTUAL» / «Mi apuesta». ⚠️ **Antes de empezar hay una decisión del owner sin
   tomar: ¿el set entra en el PRODUCTO o en el paquete de tema del cliente?** El repo no lleva marca
   de nadie (`#1`), y eso cambia dónde aterrizan los 47. ⚠️ Y hay que respetar
   `SidebarIconParityTest`, que obliga a que la geometría del cajón sea la del sistema de diseño.
2. ⬜ **El contenido real del cliente** (productos, precios, textos). Necesita datos del owner.
3. ⚠️ **Dos cosas abiertas del cierre, dichas al owner y sin respuesta suya:**
   - El **pie queda tapado** por la tarjeta de cierre en cuanto se ancla — es lo que hace el mockup
     (`z-index: 210`), pero deja una franja estrecha para leer sus 14 enlaces. Si molesta, se
     retrasa el anclaje.
   - El titular del cierre usa **nuestro copy** (`VAMOS / A SALTAR`) con la **estructura** del
     mockup. Si lo quiere literal (`¿NOS VEMOS / EN EL AIRE HOY?`) son tres claves de idioma.

---

## ▶ Lo que hizo esta sesión del carril C, en una línea cada cosa

| | |
|---|---|
| `#209` | **El QUINTO mecanismo del tema: el relleno de ACCIÓN** (`tema-por-instalacion.md` §15). El botón de comprar deja de estar atado a `var(--fg)` y es un **ROL** de cuatro tokens con **un solo dato en el panel** (`theme.action`) |
| `#211` | ❗❗ **El menú no se podía CERRAR con el ratón** y **abrirlo desde la portada dejaba la pantalla sin botón de comprar**. Y el **hueco del ICONO de instalación** (`client-favicon.svg`) |
| `#213` | El CTA **cambia de ROL dentro del menú**: tinta fuera, **AVISO** dentro, como el mockup. `.cta-med` **sale** del rol de acción |
| `#214` | El racimo es un **PAR**: uno ancho, el otro reducido a su icono; 1.er clic expande, 2.º actúa, con **invitación** que se apaga al tocarlo |

## ❗❗ Lo que MÁS importa que sepas antes de tocar nada

1. **`[DECIDIDO owner, 2026-08-28]` — del canvas SOLO se toma el SISTEMA DE DISEÑO**: colores,
   elementos, iconos, formas, menú, hero de cabecera y pie. **El resto del canvas «son pruebas» y no
   se toca** hasta que él lo diga. Eso deja fuera las **tres** variantes de «El parque» y los cuatro
   artboards de cumpleaños.
2. ⚠️ **`#195` sigue en pie**: el mockup ha devuelto el CTA al hero y **nosotros no lo copiamos**
   (`[DECIDIDO owner, 2026-08-28]`).
3. ❗ **El LOGOTIPO y el ICONO los exporta el owner desde Claude Design** y los entrega como
   ficheros. Se intentó reconstruir el lockup en SVG y **no salió idéntico** (29 % de píxeles
   distintos): son **6 capas por palabra** más una figura que no es silueta plana. ▶ **La lista
   exacta de formatos y las tres reglas que no son opcionales están en `INSTALACION-CLIENTE.md`
   §4.a.quinquies.** El hueco ya existe; falta **la variante sobre TINTA**, cuyo hueco NO está hecho.
4. ⚠️⚠️ **Tres fuentes del cliente se contradicen** sobre el color de su CTA fijo (implementación
   dice aviso · su norma dice «el amarillo nunca es fondo de botón» · su tabla de orden dice color
   de acción). Se sigue la implementación **por decisión suya**; ficha en `DEUDA.md`.

## ▶ POR DÓNDE SIGUE ESTE CARRIL

0. ❗ **Lo primero, el OJO del owner**, que ya son NUEVE tandas visuales sin mirar y la última
   —`#216`— **cambia cómo se ve la primera pantalla de la portada**: sin logo, sin CTA y sin
   hamburguesa mientras el hero llena la pantalla, con los dos botones dentro del hero.
1. ✅ **La tanda 2d está HECHA** (`#222`): el movimiento es un sistema de 4 curvas y 7 duraciones,
   y **cero literales fuera de la escala**. ❗ Lo único que queda de ella es **tu ojo**: no se
   revisa con una captura, se revisa interactuando.
2. ✅ **La 2c·4b está HECHA** (`#221`): el menú manda en los doce anchos y la barra inferior se
   queda como el owner la validó. Lo único que deja detrás es el cajón lateral apagado, con ficha.
   ▶ El texto de abajo se conserva porque explica de dónde venía. **La 2c·4b, el menú en MÓVIL** — ✅ **ya no la bloquea el artboard** (llegó el 28 dentro de
   `Landing PJP Modos`, con la pasada de móvil completa). ⚠️ **Pero su barra inferior son 3 iconos +
   1 CTA y la nuestra es el CTA doble que el owner validó el día antes**: eso se le pregunta antes
   de rehacerla, no se elige.
3. **La tanda 3, las secciones** — ⏸️ fuera de alcance por decisión del owner (punto 1 de arriba).

## ⚠️ Y una lección de método que esta sesión pagó CUATRO veces

**Una aserción por SUBCADENA pasa en falso, y una guarda laxa nace ciega.** Los cuatro casos, todos
cazados por el arnés de mutación y no por la suite: `.nav-cta-med-NO` contiene `.nav-cta-med` ·
`client-favicon.svg` contiene `favicon.svg` · buscar `$store.ctaPair` «en algún sitio» del atributo
pasaba con los anchos leyendo una variable local · y un `animation:` genérico bajo `--invita` lo
cumplía el ARO, así que retirar el asomo dejaba la guarda verde.
▶ **Acota al elemento, y muta la guarda con el fallo REAL que la motivó.**
▶ Y lo mismo con los instrumentos de medida: en esta sesión mintieron **cinco** —un inventario de
color que inventaba hexes, un extractor que no imprimía ni un botón y concluía «no hay ninguno»,
`readFileSync(x).buffer` devolviendo el pool de Node, `opentype.js` emitiendo `NaN` en una glifo, y
Chromium devolviendo `color-mix()` como `color(srgb …)` en canales 0–1—. **Cuando un instrumento
dice que algo no existe, la primera hipótesis es el instrumento.**
❗❗ **Y tres cosas que cambian el mapa para quien entre ahora:**
1. **`[DECIDIDO owner, 2026-08-28]` — del canvas SOLO se toma el SISTEMA DE DISEÑO**: colores,
   elementos, iconos, formas, menú, hero de cabecera y pie. **El resto del canvas son pruebas y no
   se toca** hasta que él lo diga. Eso deja fuera las tres variantes de «El parque» y los cuatro
   artboards de cumpleaños.
2. ✅ **El artboard del menú en MÓVIL YA EXISTE** (llegó dentro de `Landing PJP Modos` el 28): la
   **2c·4b deja de estar bloqueada**. ⚠️ Su barra inferior son **3 iconos + 1 CTA** y la nuestra es
   el **CTA doble** de `#205`: contrástalo con el owner antes de rehacerla.
3. ⚠️ **`#195` sigue en pie**: el mockup ha devuelto el CTA al hero y **nosotros no lo copiamos**
   (`[DECIDIDO owner, 2026-08-28]`).

▶ Lo anterior de este bloque se conserva porque sigue siendo cierto:

El **carril C (el TEMA)** cerró el 2026-08-28 de madrugada con **seis tandas empujadas y verdes**:
la spec del armazón y su red (`#200`), el **menú a pantalla completa** (`#201`), la **barra disuelta
en dos racimos** (`#203`), la **cuenta en icono** (`#204`), el **CTA doble de móvil** (`#205`) y
**el primer PAQUETE DE TEMA REAL de un cliente** (`#206`).

❗❗ **Lo más importante que dejó, y afecta a cualquiera que toque CSS o guardas:**
**montar un paquete de cliente destapó TRES defectos del producto que solo aparecen con una
instalación encima** (`specs/tema-por-instalacion.md` §14.4). El peor no fue el original sino **el
primer arreglo**: hacer que un test se saltara movía el **contador de aserciones**, y el `pre-push`
compara el número EXACTO contra `ESTADO.md` — así que **bloqueaba a una de las dos máquinas
siempre**. Con dos carriles a la vez, eso es bloquear al otro agente por tener un tema instalado.
▶ **Un gate que depende de si el disco tiene el tema de un cliente no es un gate.**
▶ Arreglado de fondo: los casos del paquete corren sobre un `public/` propio y **las cinco guardas
de CSS excluyen `client.css`** — un paquete de tema **está hecho de literales** y juzgarlo con las
reglas del producto sería prohibirle existir. Verificado midiendo con paquete y sin él: **idéntico**.

⚠️ **Y esta máquina es ahora una INSTALACIÓN**: tiene `public/css/client.css` (gitignorado), los
ajustes `theme.brand`/`theme.brand_secondary` en la BD local y `THEME_FONTS` en el `.env`. **En otra
máquina nada de eso existe** y la web se ve con el tema del producto: es lo correcto. Para
regenerarlo, la receta es `INSTALACION-CLIENTE.md` §4 y la fuente el artboard `Colores de Marca PJP`
del canvas.

Del owner sigue esperando: **su ojo en navegador** (ahora son CINCO tandas visuales sin mirar), el
**artboard del menú en móvil**, el **SVG del logotipo**, el texto del waiver y los plazos de
retención.

| Carril | Qué espera, exactamente |
|---|---|
| **A · menores + puerta** | **SESIÓN del 2026-08-27 noche → 28 madrugada**: `[DECIDIDO owner]` `#208`. ✅ **El PANEL de menores (tanda 5, `specs/menores-a-cargo.md` §9.10.4) EN EL ÁRBOL** (`8ab0f5c`): «Para:» en la ficha, «Asignar menores» en la línea, el alta manual con selector; +45 tests, sonda de concurrencia, headless 13/13. ✅ **El SUBSISTEMA A (`specs/identidad-qr-puerta.md` §9.4) EN EL ÁRBOL**: carné QR de 20 caracteres, visita acreditada, ficha compuesta, pantalla con caducidad en servidor, correo con PNG, `GET|POST /me/card`; 5/5 mutaciones, headless 15/15. **Ambos 🟦 por el OJO del owner** (§9.10.4 y §9.4 «lo que queda»: el panel, la puerta, el correo en Gmail/Outlook y **el lector real del recinto**), más los guiones §5.decies/§5.undecies y los DOS valores de retención en meses. ▶ Lo siguiente de agente, cuando el owner lo pida: la zona «Mi carné» del cajón y la rotación desde el panel (`DEUDA.md`), y **D · JumpPoints**, que ya tiene su hecho observable (`customer_visits`) |
| **B · panel/dinero** | Nada de agente. La pasada de NAVEGADOR del owner por las 10 acciones (`specs/desmontar-view-order.md` §6·5) |
| **C · tema** | ❗❗ **CARRIL CERRADO EL 2026-08-28 A LAS 15:30. NO QUEDA NADA DE AGENTE**: el tema tiene sus **SEIS mecanismos** (`#222` cierra con el MOVIMIENTO), el armazón está **completo en los doce anchos** (`#221`) y la marca del 2.º cliente está instalada (`#216`). ⚠️ **Si tocas `public/css/site.css` o `landing.css`, ábrelas antes de escribir**: la 2d hizo **570 sustituciones** en las dos. ✅ **(1) EL LOGOTIPO Y EL ICONO YA ESTÁN** (`#216`): los subiste a `marca/` en el canvas y están instalados —logotipo con silueta, su variante **sobre TINTA** (el hueco se abrió en esta tanda) y el set de icono **completo**, así que iOS y Android dejan de enseñar la «J» del producto—. ❗ **LO QUE ESPERA DE TI AHORA:** **(1)** La **pasada de NAVEGADOR: son NUEVE tandas visuales sin mirar** — `#195` (el hero pierde su CTA), `#196` (19 elementos pierden su sombra), `#201` (menú a pantalla completa), `#203` (la barra disuelta), `#205` (CTA doble de móvil), `#209` (el botón de comprar en naranja), `#211` (el menú ya se cierra y siempre ofrece comprar) y `#213`+`#214` (el CTA es un PAR y se vuelve AVISO dentro del menú). Los guiones para recorrerlas: `VERIFICACION-E2E-CAJON.md` **§5.duodecies · §5.quindecies · §5.sexdecies**. **(2)** **La barra inferior de móvil**: el mockup pide **3 iconos + 1 CTA** y la nuestra es el **CTA doble** que validaste el 27 — hay que elegir antes de hacer la 2c·4b. **(3)** ⚠️ **El logotipo se pinta a 26 px de alto y tu mockup lo pinta a 54** — medido, NO cambiado: no estaba en el encargo. Es una línea cuando lo digas. **(4)** ⚠️ **La sombra de tu mobiliario: tercera contradicción de tus fuentes** —tu mockup la pinta difusa y tu `M-05` dice que las difusas son solo para modal—; se sigue el sistema y sale dura. Ficha en `DEUDA.md`. **(5)** **Cuál de las TRES variantes de «El parque»** (`Descubre-el-Parque` · `Recorrido-Parque` · `Elige tu Zona`). ▶ `[PENDIENTE: owner]` menor: avisar en el panel cuando el color de acción no alcance AA (`tema-por-instalacion.md` §15.8). ▶ Ya validó `#193` y `#194` |

⚠️ **«La landing sigue bloqueada» dejó de ser cierto y esta sección lo decía**: la capa de tema ya
tiene las tandas **1, 2a, 2b y la ELEVACIÓN** en `main` (`#192`→`#196`) y la **2c escrita**. Lo que
sigue parado es el CONTENIDO (la tanda B de `landing-white-label.md`: `testimonials` y el copy al
CMS), no el tema.

❗❗ **EL PAQUETE DEL 2.º CLIENTE ESTÁ MONTADO EN ESTA MÁQUINA** (`#206`, 2026-08-28) y **no está en
el repo ni puede estarlo**: `public/css/client.css` (gitignorado), los ajustes `theme.brand` /
`theme.brand_secondary` en la BD local, y `THEME_FONTS` en el `.env`. En otra máquina **no existe**:
para regenerarlo, la receta es `INSTALACION-CLIENTE.md` §4 y la fuente el artboard
`Colores de Marca PJP` del canvas.
▶ ✅ **Y con él quedó demostrado que los cuatro mecanismos del tema COMPONEN**: la web se sirve con
la marca del cliente y la suite entera pasa, con cero migraciones y cero líneas de dominio.
❗ **Pero destapó que la landing NO puede ser 1:1 todavía**: el CTA primario del cliente es naranja
y el del producto está atado a `background: var(--fg)`. **No hay token de acción** — es el QUINTO
mecanismo del tema (`specs/tema-por-instalacion.md` §14.5).
❗❗ **Y un hallazgo de accesibilidad que es del CLIENTE**: su anillo de foco (Amarillo Aviso) da
**1,49 sobre papel** cuando WCAG exige 3,0. Su auditoría de 20 pares **no incluye ese par**. Está
`[PENDIENTE: owner]` con tres salidas en §14.2.

✅ ❗ **EL QUINTO MECANISMO ESTÁ HECHO Y EN EL ÁRBOL** (`#209`, 2026-08-28,
`specs/tema-por-instalacion.md` **§15**). Este bloque describía la tarea; se conserva abajo como
contexto, **con dos cifras corregidas al ejecutar**.

· **El rol de ACCIÓN existe**: `--action` / `--on-action` / `--action-hover` / `--on-action-hover`,
  declarados en `:root` **y en las dos superficies**, con `var(--action-brand, …)` dentro. Esa
  indirección es todo el mecanismo: **sin color de acción el botón sigue a la superficie** (la
  conducta de siempre: el CTA del nav se invierte al abrir el menú) y **con él es idéntico en los
  dos fondos**, que es lo que exige el sistema del cliente.
· **El dato es del PANEL**: `theme.action` (Ajustes → Aspecto de la web). ⚠️ **Vacío es una
  respuesta, no una falta.** De un solo dato salen los cuatro valores: el hover se **deriva ×0,88**
  y el rótulo lo elige el contraste.
· ⚠️ **Las cifras del párrafo de abajo eran imprecisas**: no son 50 reglas sino **52**, medidas con
  **dos instrumentos independientes que coincidieron**, y de ellas **13 son acción** (11 sujetos +
  2 guardas de deshabilitado). Las 39 restantes se quedan en tinta **a propósito**.
· ⚠️⚠️ **Lo que costó, y lo dijo la guarda con el color del propio cliente**: reutilizar
  `onBrand()` para el texto sobre el relleno daba **3,73** sobre su hover `#D56319` —falla AA—
  porque `onBrand()` prefiere blanco y su umbral es **3,0**, el de texto GRANDE. Un rótulo de botón
  no es texto grande. `onAction()` elige el de más contraste: 4,99.
· **Verificado**: suite verde · 11 mutaciones, las 11 muerden · **sonda de navegador 12/12**
  (con color: `rgb(242,113,28)` dentro y fuera del menú de tinta; sin él: claro dentro, tinta
  fuera). ▶ **Falta el OJO DEL OWNER: con ésta son SEIS tandas visuales sin mirar.**

▶ ❗ **POR DÓNDE RETOMA EL CARRIL C** — y hay una novedad que cambia el orden:
1. ❗❗ **La 2c·4b (el menú en MÓVIL) YA NO ESTÁ BLOQUEADA.** El 2026-08-28 el canvas trajo la
   **pasada de móvil completa** dentro de `Landing PJP Modos` (428 líneas de diff): `--pjp-margen`/
   `--pjp-radio`/`--pjp-tope`/`--pjp-barra`, `100svh`, `env(safe-area-inset-*)`, carruseles con
   `scroll-snap`, el panel de reserva como **hoja inferior** con asa, y una **barra de acciones de
   móvil** (3 enlaces de icono + «Reservar» naranja). **Ese era el artboard que faltaba.**
   ⚠️ Nuestro `#205` ya hizo un **CTA doble** en esa barra; el mockup ahora pide 3 iconos + 1 CTA:
   **hay que contrastarlo con el owner antes de rehacerlo.**
2. **La tanda 2d, el MOVIMIENTO** (237 declaraciones, 48 duraciones, 20 curvas; el sistema del
   cliente declara 7 y 4). No depende de nadie.
▶ **Lo que SÍ depende del owner**: el **SVG del logotipo**, **cuál variante de «El parque»** —y
ahora son **TRES**, no dos: `Elige tu Zona` es una maqueta isométrica en SVG y también es «02 · El
parque»— y la **tanda 3**, donde **opiniones** y **el minijuego del castillo** no existen y
**`D9`/`B4` las bloquea el ARTE, no el código**.
❗❗ **ALCANCE VIGENTE DEL CANVAS** (`[DECIDIDO owner, 2026-08-28]`): **solo el SISTEMA DE DISEÑO**
—colores, elementos, iconos, formas, menú, hero de cabecera y pie—; el resto del canvas «son
pruebas» y **no se toca** hasta que él lo diga. ▶ Y **el hero NO recupera su CTA** aunque el mockup
se lo haya devuelto: la decisión de `#195` sigue en pie (`[DECIDIDO owner, 2026-08-28]`).

▶ Contexto de la tarea, tal y como se planteó (con las cifras ya corregidas arriba):
▶ **Y de la LANDING (tanda 3), lo medido el 2026-08-27 contra el artboard normativo**: la única
sección del mockup que **no existe** es **OPINIONES** (3 tarjetas `texto`/`nombre`/`meta` + cápsula
de valoración = el `testimonials` parado en `#158`); **el minijuego del castillo** tampoco existe y
en el mockup vive dentro del CTA final; tenemos **galería y FAQ**, que el mockup **no tiene**; y sus
juegos van **dentro de Zonas**, no en una sección «Atracciones» aparte. Del **kit de fachada** solo
hay **2 de 24 piezas** (la trama de puntos y la tira del pie) — y dos de las que faltan (`D9` las
poses, `B4` foto dentro de mancha) **las bloquea el ARTE, no el código**. Del set de iconos, **25 de
48** componentes.
⚠️⚠️ **La 2c·2 corrigió DOS VECES a su propia spec y las correcciones van delante del texto que
corrigen**: `M-05` **ya estaba cumplido** —nuestro mobiliario perdió la sombra en `#196`, así que lo
que hacía falta era lo contrario: darle a las piezas con qué sostenerse— y la coreografía «nace
oculto bajo el hero» **no se implementó**, porque el hero dejó de ser a sangre en `#195` y aplicarla
dejaría la portada sin logotipo y sin ☰.
▶ **Lo que el owner ya decidió** (con la medida delante): armazón flotante en las **12** vistas ·
lista de menú **PLANA** con los destinos que ya hay, y la BD sigue al mando · con sesión, **icono de
cuenta con punto naranja/verde** · **el móvil lo guía él con un artboard**.
▶ **Lo medido** (instrumento con guarda de la guarda, spec §1): **194 reglas distintas · 733
declaraciones** de CSS en 7 familias · **31 aserciones** en 4 ficheros · **33 reglas (17 %) MUERTAS**
· **12** vistas · el salto al contenido existe en **1 de 12**.
⚠️ **La primera cifra publicada era FALSA y se corrigió al ejecutar**: «232 · 819» y «41 muertas»
sumaban COINCIDENCIAS, no reglas —una regla que cita dos familias contaba dos veces, y `.cta-prime`
se contaba como del armazón cuando la comparte con el hero—. **Un inventario que suma por etiqueta
cuenta etiquetas, no sujetos.**
❗ **Y el hallazgo de método de esta tanda**: el instrumento dijo **9** clases muertas en vez de 11
porque dos «vivían» **dentro de un comentario de Blade** que explicaba que ya no se usan. La regla
escrita —«un `grep` que no encuentra no demuestra que no exista»— **tiene simétrica**: un `grep` que
SÍ encuentra tampoco demuestra que exista. El corpus ahora limpia comentarios y lleva el control
positivo que muerde ese caso.
❗❗ **Tres cosas del mockup NO se copian** (spec §1.7): su menú cerrado **deja los enlaces en el
orden de tabulación** (medido: cero `inert`, cero `aria-hidden`, cero `visibility`) — nuestro cajón
ya resolvió eso y el porqué está escrito; el hallazgo **`M-05`** del propio cliente —difusa fuera de
modal en el CTA fijo y el botón de registro— **choca con `--shadow-float`, creado el mismo día en
`#196`**; y el eslogan a rotulador sale dos veces (`T-02`).
⚠️ **La auditoría del cliente EXCLUYE el menú y el logotipo** por indicación suya: sus colores están
revisados, su forma y su coreografía **no**. Es la misma trampa que con el hero.

✅ **La capa de tema tiene ya sus CUATRO mecanismos** (`#192` → `#196`): color y superficie · forma
(cantos, motivo, foco, tira) · el hero · y ahora la **ELEVACIÓN**.
❗❗ **La elevación son TRES ROLES, no una escala, y eso CORRIGE la medición de `#193`.** Aquella
miró solo el difuminado; con las cuatro dimensiones, el producto tenía **53 sombras y 42 formas
distintas** y la mejor escala de cinco escalones movía **47 de 53**. No era una escala con ruido:
**no había ninguna**. ▶ Y al ir a copiar el número de escalones del cliente apareció que **él
tampoco tiene**: declara DOS formas. Con 42 de un lado y 2 del otro, la pregunta era **«¿para qué
sirve cada sombra?»** — y salen tres respuestas: `lift`, `float`, `modal`. **19 pierden la sombra**:
una tarjeta quieta no está elevada, está apoyada. De **53 formas propias a 6**, todas justificadas.
⚠️ **Los tres leen `--paper-fg`, no `--fg`**: dentro del hero `--fg` vale CLARO y la sombra se
volvería clara. Es el mismo defecto que `#194` cazó tres veces.

❗❗ **LO QUE ESPERA AL OWNER, y ahora es lo único que bloquea**: la **pasada de NAVEGADOR**. Se ha
acumulado mucho cambio visual sin que nadie lo mire: el hero entero (`#195`: pierde su CTA, gana un
eslogan, es una tarjeta y encoge al bajar) y **19 elementos que pierden su sombra** (`#196`), que
cambia el aspecto de media web. Lo verificado es aritmética y estructura — **no vista**.
▶ Y de `#193` sigue pendiente su ✅ a las 11 declaraciones de canto que se movieron.

⚠️ Sigue abierto en `DEUDA.md`: **`--onvideo` sobrevive en 10 reglas y su nombre ya miente** (solo
declara tamaño y sombra) · **la excepción del anillo de foco del chip ya se puede retirar** (el hero
declara `ink`) · y **~50 reglas de CSS que PARECEN muertas y no se pueden confirmar con un `grep`**:
el cajón construye sus clases por concatenación en Vue, así que `cal__day--normal` no aparece ni en
`resources/` ni en el bundle minificado **y sin embargo está viva** (sale en el manifiesto DOM).
Auditarlas necesita su propia pasada, con el rigor de `CONVENCIONES §3.quater`.



✅ **El mecanismo de la tanda 1 YA lo usa alguien**, desde `#194`: el `.hero__stage` declara
`data-surface="ink"` y es el primer —y por ahora único— consumidor. Este párrafo decía lo contrario
y se corrige aquí.
⚠️ **Pero las tres excepciones del anillo de foco NO se retiraron.** Este documento daba por hecho
que caerían «ese mismo día» y no fue así: se dejaron **a propósito**, para no mezclar un cambio de
foco con la conversión de superficie. ▶ **La del chip del hero ya se puede retirar** —su fondo sí
declara superficie—; las de `.skip-link` y `.gf-fiche__head`, no. Ficha en `DEUDA.md`.

**Y en el carril de calidad no queda trabajo de valor alto — está medido, no supuesto.** El reloj está
cerrado (`#162`, `#164`), `RGPD-01` corregida (`#159`) y la siguiente rebanada del gate documental se
midió y da **cero** (`#164`: las 35 citas de la columna «Dónde vive» resuelven). Así que **antes de
inventarte una tarea, mira la lista de «Lo que NO depende de nosotros»**: casi todo lo que queda lo
desbloquea el owner.

▶ **Las dos cosas que eran trabajo de agente YA TIENEN CARRIL** (reparto de la cabecera):
1. ✅ **El waiver, de agente, está TERMINADO** → **carril A, 2026-08-26** (`#169` → `#183`): revisión
   adversarial del subsistema (spec §10), **la tanda 4 que exigía** (§9.11: `#171` anti-bot · `#174`
   servidor · `#175` cajón · `#178` casilla del alta manual + casilla OBLIGATORIA en interno · `#179`
   correo verificado para firmar · `#180` texto del PDF) **y la revisión adversarial de la propia
   tanda, aplicada** (§9.12, `#183`: 24 confirmados, 0 refutados; lo peor, `Verified` emitido por el
   COBRO dentro de su transacción). El guion `VERIFICACION-E2E-CAJON.md` §5.nonies está **reescrito con
   la conducta definitiva y recorrido en headless: 111/111 ✓**. **Lo que queda es del owner y solo
   del owner**: su ✅ en navegador (guion §5.nonies, en local, con las claves de PRUEBA de Turnstile
   en Ajustes —receta en §5.bis—), el **texto definitivo** (spec §8.1: publicar la v1 real es
   irreversible) y el **plazo de retención** (§4.6). ▶ ✅ **`[DECIDIDO owner, 2026-08-27]`: la
   SIGUIENTE SESIÓN de este carril hace «menores a cargo»** (`specs/menores-a-cargo.md`, C; hereda
   NUC-3 de `DEUDA.md`) — spec revisada, sin código: leer antes `docs/INVARIANTES.md` §2 (AFORO) y, en
   la spec, sus secciones 8.1 y 8.2 —la lista blanca de `cart.js::save()` y el `STORAGE_VERSION`—. El
   detalle del arranque está en la fila A de la cabecera. ▶ ✅ **HECHO por la tarde: la tanda 1 está
   EMPUJADA** (`#191`, spec §9: la entidad, el registro con tope, la API, RGPD y el ajuste; sin firmas
   de menor). **Lo siguiente son las cinco decisiones de §9.5**, no código.
   ⚠️ En la máquina del carril A hay **v1→v9 publicadas** localmente por las dos pasadas del guion y
   los sondeos (y cuentas `e2e-waiver-*@jumpweb.test` / `probe-*@jumpweb.test`); en la del portátil,
   no. Tras un pull en el portátil: **`migrate`** (`#183` añade una migración).
2. ✅ **El desmontaje de `ViewOrder`, de agente, está TERMINADO** → **carril B, 2026-08-27**
   (`#165` spec · `#167`/`#168` revisión · `#170`→`#177` paso 0 a instrumento · **`#182`→`#189` la
   extracción 4b entera en una noche**): `ViewOrder` en **2.355 líneas y 50 métodos** (de 5.280 y
   98), sin orquestación de dinero ni de aforo — seis servicios y dos contratos en
   `app/Domain/Booking/`, la receta anti-sobreventa UNA vez (`ZoneDaySlotLock`, compartida con la
   compra), 74/74 mutaciones, 6/6 escenarios + Redsys, y **15 tests nuevos de reglas que la página
   no podía alcanzar**. `[DECIDIDO owner]` (`#181`): no se parte más. **Lo que queda es del owner y
   solo del owner**: la pasada de NAVEGADOR por las 10 acciones del panel (spec §6·5). ▶ Siguiente
   trabajo de agente en este carril: ninguno del desmontaje; ver `DEUDA.md` o lo que el owner diga.

▶ **La línea de panel/dinero está CERRADA y DESPLEGADA** (`#149`→`#155`, staging en `7776370`): no
hay siguiente paso de agente ahí. Lo único pendiente es HUMANO: el owner prueba el modal nuevo de
«Reembolsar» en navegador con sus 9 pedidos-sonda (decidió conservarlos para eso).

# ❗ LO SIGUIENTE: **`testimonials`** — lo único de la landing que NO está bloqueado

⏸️ **[DECIDIDO owner, 2026-08-25 tarde] APLAZADO hasta que la landing esté terminada** (`#158`): el
owner no puede visualizarlo ahora, y una sección que no se puede ver no se puede validar (cuarta
condición del DoD, `CONVENCIONES §3.bis`). Ya no hay nada en marcha —los dos carriles cerraron el
27/08— y lo siguiente decidido es «menores a cargo» (reparto de arriba). Lo que sigue de este apartado describe el trabajo
tal y como quedó MEDIDO, para cuando toque.

⏸️ **Por qué no es «seguir con la tanda B»**: el owner está **rehaciendo el sistema visual** en Claude
Design y ha dicho que **los datos y textos del mockup NO son fidedignos** —«lo que hay que llevarse es
la estructura, las formas, los botones, los colores y los layouts; los datos son los que tenemos
ahora»—. Maquetar ahora es trabajo que se tira.

**`testimonials` sí se puede hacer entero hoy**, y hace falta decida lo que decida el owner sobre las
reseñas: es el **respaldo** de `specs/google-reviews.md` (§4.4.bis).
✅ **[DECIDIDO owner, 2026-08-25]: va DETRÁS del contrato `Content\Contracts\SocialProof`** que diseña
`google-reviews.md` §4.1, no como clon liso de `faqs`. Cuesta una interfaz más y evita retrofitear la
vista el día que Google se encienda — que es la rama que, por definición, solo se ejecuta cuando algo
va mal.
⚠️ **Medido el 2026-08-25**: `testimonial` no aparece en **ningún** fichero de `app/`, `database/`,
`resources/`, `routes/`, `lang/`, `config/` ni `tests/`. Es construcción desde cero.
⚠️ **Y roza `lang/es/admin.php`, que es del agente A**: el recurso necesita sus claves, y su bloque de
pedidos vive en la misma zona del fichero. **`git pull --rebase` antes de empujar** — esta sesión ya
pagó ese peaje seis veces con los números de `DECISIONES`, y una séptima con un arreglo duplicado.
▶ Tabla + modelo + recurso de panel
+ sección, siguiendo el patrón exacto de `faqs` (permiso `content.manage`, grupo «Contenido», campos
i18n en JSON con `HasTranslations`). Campos medidos del mockup: `texto` · `nombre` · `meta` +
valoración.
⚠️ **Su ayuda en el panel NO puede decir «por si Google falla»**: por `google-reviews.md` §3.3, es lo
que ve **todo visitante que no acepta cookies de terceros**, cada día. Si se documenta como plan de
emergencia, el parque lo dejará vacío creyendo que nunca se usa.

### El orden acordado con el owner para cuando el diseño esté listo

1. **Cimientos visuales** — la paleta del cliente + los patrones de forma que faltan. Van ANTES que el
   armazón porque el nav y el footer los consumen: hacerlos después obliga a rehacerlos.
2. **El armazón** — nav/menú, logo y footer, **con NUESTROS elementos**. Es lo que se ve en todas las
   páginas y fija los patrones de botón que luego reutilizan las secciones.
3. **`<x-page>` + una página de ejemplo** — pedido explícitamente por el owner («las dos cosas»).
   Medido: las 6 páginas re-maquetan su cabecera a mano (`page__head` ×4, `page__title` ×4,
   `page__body` ×3, `page__back` ×3). No hay nada entre «el armazón del sitio» y «el contenido».
4. **Sección por sección**, con los datos reales de ahora.

⚠️ **Y el minijuego del castillo ENTRA** (`[DECIDIDO owner]`), idéntico, pero se acepta hacerlo más
eficiente. Medido en el mockup: **52 `setState` en el bucle de animación** —re-renderiza el árbol 60
veces por segundo, y ahí está el coste, no en las 105 llamadas de canvas—, **~66 KB de JS** que hoy
pagaría todo visitante, y **`tabindex` 0 · `role` 0 · `aria-label` 0**. Las tres cosas se arreglan sin
mover un píxel: estado fuera del ciclo de render, chunk con carga diferida y accesibilidad.

▶ **Después**: el copy al CMS (que gana esperando a la landing) y luego la tanda **C**.

### ❗ El sistema de color NUEVO, ya leído y medido — y lo que cambia

Vive en el **canvas de Claude Design del owner, NO en el repo**. Para leerlo:

    DesignSync · method=list_files · projectId=8c37d2d2-7e9c-43a9-bc25-aacb6607f2ad
    DesignSync · method=get_file  · path="Colores de Marca PJP.dc.html"

⚠️ **No sirve WebFetch** (da 403) ni `Artifact action:read` (no es un artifact publicado): **solo el
MCP `DesignSync`**. Los `.jpg` de `assets/` vienen en base64 y **truncados a 256 KiB**, pero un JPEG
parcial se decodifica y se ve.
▶ El canvas tiene **14 artboards**: además del de color, `Logotipo variantes`, `Menu PJP`,
`Boton Reservar variantes`, `Hero PJP variantes`, `Info PJP variantes`, `Landing PJP Modos`,
`Elementos Fachada`, `App PJP`, `Marquesina Castillo`, `Salta la Ciudad`, `Tag Lorca`.
▶ Y hay un artifact aparte, **«Landing page parque trampolines»**, con el mockup completo de la
landing — **pero lleva la paleta VIEJA** (ver el aviso de abajo).

⚠️⚠️ **Lo primero: la paleta que se midió del mockup de la landing está CADUCADA.** Su propia tabla de
migración lo dice —`#2FB6DE` → `#1AA9DE`, «se iba de claro y perdía 0,4 de contraste sobre tinta»—.

**No es una paleta: es un sistema con 15 secciones**, derivado de la fachada real con la masa
cromática medida (46 % azules, 36 % naranjas, 13 % verdes), con **8 colores de núcleo** (cada uno con
`hover`, `press` y su variante oscura), **9 neutros**, roles por elemento, **auditoría WCAG de 20
pares** y prohibiciones explícitas.

▶ **La buena noticia: encaja casi 1:1 con los tokens que ya existen** — Tinta`#101418`→`--fg`,
Papel`#F4F4F1`→`--bg`, Cian`#1AA9DE`→`--brand`, Verde Salta→`--ok`, Rojo Goteo→`--err`,
Amarillo Aviso→`--attn`. **Cuatro cosas no tienen token**: Lima Bote (precios y cifras), Azul Muro
(el único azul legible sobre claro), Magenta Chispa y los `hover`/`press` de cada color.

❗❗ **Y DOS cosas que no son «otra paleta», son otra ARQUITECTURA:**
1. **«La marca es oscura por naturaleza: el color vive sobre negro.»** El sistema alterna **dos
   fondos por sección** —tinta y papel— con la regla «nunca dos papeles seguidos». Nuestro CSS asume
   fondo claro. **Eso no se resuelve redefiniendo tokens: es un MODO**, y hay un artboard llamado
   precisamente «Landing PJP Modos».
2. **«Texto secundario: dos grises distintos — el claro falla en papel.»** Nosotros tenemos **un
   solo** `--fg-mute`. Con un único gris sobre los dos fondos, uno de los dos incumple AA. El sistema
   ya lo trae medido: Humo `#626A72` (4,98 en papel) y Humo Claro `#9AA1A8` (7,08 en tinta).

▶ Y del sistema de FORMA: sombra **dura** `5px 5px 0` «o ninguna», borde 2px solo en la pieza
protagonista, y escala de radios `0 · 6 · 10 · 16 · 24 · 999` (la nuestra es otra).
⚠️ Medido en el producto: **68 declaraciones `box-shadow`, 58 formas distintas y CERO tokens**, y solo
el 26 % se repite. **No hay escala de elevación de facto**: habría que decidirla, no extraerla.
⚠️⚠️ **C necesita SPEC PROPIA antes de una línea de código**: toca `AFORO-01/02/03` y las identidades
de `PAY`, exige `VERIFY_CONC=1` y **no se puede verificar ni en SQLite ni en staging** (MariaDB).

⚠️⚠️ **Antes de tocar el tema, lee la spec §4.5.1 y §4.5.2 — LAS DOS LLEVAN UNA CORRECCIÓN.** La
primera afirmaba «cero variables se inyectan desde BD» (falso: el tema sí se inyecta desde
`theme.brand`). La segunda, «cambiar el dibujo del spinner es sustituir un fichero; no hay que
construir nada» (falso: eran dos pseudo-elementos y un `@keyframes`, y la doc del propio sistema decía
que esa hoja no se modifica).
❗ **La lección que esta semana se ha pagado CUATRO veces, y la última en el mismo trabajo que la
escribió**: un `grep` que no encuentra no demuestra que no exista, y **un instrumento que no ve una
parte del corpus da un inventario que parece completo y no lo es**. Cuando dos medidas del mismo
corpus no coinciden, la que sobra **no es la que da más: es la que no puede explicar la diferencia**.

---

### Lo que la tanda A dejó montado, y hay que saber ANTES de tocar CSS

- 🆕 **`public/css/client.css` es el paquete de tema de la instalación** (`#143`). Se carga **el
  último**, **no se versiona** y **`deploy.sh` lo excluye del `rsync --delete`**. ⚠️ **Las tres cosas
  son el mecanismo**, no tres detalles: por delante de `site.css` carga y no pinta nada; sin la
  exclusión, el primer despliegue lo borra **en silencio**. `ClientThemePackageTest`.
- 🆕 **Un literal que repita un token existente ya no puede entrar**: `RawColourIsNotATokenTest`
  compara **por VALOR RGB, no por nombre de token** — que es el hueco por el que dos acentos del
  primer cliente sobrevivieron a `#139` escritos en decimal dentro de un degradado.
  ⚠️ Su lista de excepciones (`ALLOWED_SELECTORS`) **solo encoge**, y hay un caso que tumba una
  entrada que se quede sin sujeto.
- 🆕 **`spinner.css` tiene dos mitades y la frontera es un marcador de máquina** (`>>> SPINNER:… >>>`).
  §A contrato, §B dibujo. Meter geometría en §A pone `SpinnerTest` en rojo, y con razón: es lo que
  hace sustituible el dibujo.
- ⚠️ **Blanco y negro NO se tokenizaron, y es decisión del owner**: el blanco de papel no es `--bg`
  (crema) y el blanco sobre acento no es `--on-brand` (sobre un acento claro es tinta oscura).
  Convertirlos **cambia píxeles**. Ficha con los tres grupos en `DEUDA.md`.
- 🐛 **Y queda un defecto de coherencia de una línea**: `.addons-mini__badge` pinta `color: var(--ok)`
  sobre un fondo verde de otra familia. Cambiar `--ok` mueve el texto y deja el fondo quieto. Está
  en `DEUDA.md` porque arreglarlo cambia píxeles: es decisión de producto, no refactor.

## ▶ Lo que está ABIERTO y no es de la tanda A

✅ **0.bis · Los CUATRO defectos del cambio de precio están CERRADOS — y lo que queda son fichas
con nombre** (`DECISIONES #146` → `#149` → `#150`, 2026-08-25. Todo medido ejecutando, arreglado y
verificado en vivo sobre MySQL. La nota del cierre del agente B «D5 informado, no verificado»
queda superada: manda `git log`, y ahora el arreglo entero está en el árbol.)

⚠️⚠️ **El tronco, para la historia**: `PAY-18` (`#131`) hizo que **mover la fecha re-tarifique**, y
**SEIS sitios** estaban escritos sobre la premisa vieja («el valor solo baja si baja la cantidad»).
`#145` arregló el filtro del contexto · `#149` D5 (el importe elegido en «Reembolsar», con el
«pendiente de devolución» sugerido delante) · `#150` D4 (la reconstrucción calcula
`cantidad_original × precio_original`), D3 (el marcador se dispara con cualquier cambio
reconstruible), D2 (toast y pies dicen la causa verdadera) y el sexto («+N producto» exige un
`quantity_change` real). **El callejón del dinero atrapado está cerrado**: bajada → cancelar la
RESERVA → la línea devuelve TODO (verificado en vivo, `R-VLRYUV`: 40,00 fuera, pendiente 0).
✅ **Y el pack CON señal quedó MEDIDO** (`R-DWFRDP`): la cascada absorbe la bajada contra el resto
de la señal, cero reembolsos necesarios, identidades cerrando — lo que `#146` leyó es lo que pasa.

✅ **Las CUATRO fichas derivadas quedaron CERRADAS el mismo día (`#152`–`#155`):**

| | Ficha | Estado |
|---|---|---|
| 1 | ~~Un pedido CANCELADO no tenía vía de reembolso~~ — ✅ **CERRADA** (`#152`, owner): la LÍNEA se abre para cancelados con deuda (topes intactos; el TOTAL sigue vetado a propósito) y el banner dice cuánto se debe y por dónde | ✅ hecha |
| 2 | ~~El reembolso a nivel PEDIDO regalaba sin avisar~~ — ✅ **CERRADA** (`#153`, owner): sin campo (los parciales van por línea); el modal nombra el importe exacto y avisa de la vía de los parciales al desactivar «también cancelar» | ✅ hecha |
| 3 | ~~El cliente EN/FR veía claves en crudo~~ — ✅ **CERRADA** (`#154`): las etiquetas viven en `tickets.*` (ES/EN/FR) con guarda `Lang::has` sin respaldo | ✅ hecha |
| 4 | ~~El email de una BAJADA no mencionaba el dinero~~ — ✅ **CERRADA** (`#155`): cuenta la deuda que aflora Y lo absorbido en puerta, en tres idiomas con guarda | ✅ hecha |

✅ Y «¿devolver en el parque?» quedó **DECIDIDO** (`#152`): no se construye canal nuevo — devolver en mano se registra con el modo «manual» («ya devuelto fuera»), que ya existía y desde `#149` acepta importe exacto.

🟦 **0 · La VISIÓN DE PRODUCTO de la app está DISEÑADA, REVISADA y EN EJECUCIÓN** (`DECISIONES
#142`, revisión en **`#156`**, 2026-08-25). Cuatro subsistemas en Fase 6, ordenados por
**dependencia**: waiver probatorio → menores a cargo → carné QR y pantalla de puerta → JumpPoints.
✅ **El waiver arrancó el 2026-08-25 por la tarde y su tanda 1 —el núcleo— está EMPUJADA** (`#160`,
`specs/waiver-probatorio.md` **§9**): versiones inmutables, firmas encadenadas por titular (verificadas
bajo concurrencia sobre MySQL, y el verificador visto fallar sin el lock), los tres modos, la prueba que
sobrevive a `anonymize()` y la acción de publicar — **sin publicar ninguna versión** (§8.1 es ahora un
mecanismo: un `[PENDIENTE]` no se publica). ✅ **Y la tanda 2 —el panel— también** (`#161`, 2026-08-26):
la identidad del firmante viaja EN la firma (`[DECIDIDO owner]`), permiso propio `waiver.view`, el
registro en la ficha como acción auditada, el PDF del snapshot en el idioma firmado y el alta
presencial declarada. ✅ **Y la 3a —el cliente por API— también** (`#163`): `GET /legal/waiver`,
`GET|POST /me/waiver` (aceptar SOLO el texto que el servidor sirvió), el PDF propio, la casilla del
alta y `waiver` en el contexto de cuenta. ✅ **Y la 3b —el cajón— también** (`#166`, 2026-08-26): la
casilla del alta (opt-in, y **solo si hay documento servido**), la tarjeta de Privacidad con firmar /
re-firmar y los PDF, y el aviso del índice; el store RE-LEE ante `409 waiver_document_stale`. Los
textos del montaje se **podaron antes de subir** (−508 B) y el chunk subió su techo **por decisión del
owner**. **El código del waiver está COMPLETO**, ✅ **el guion §5.nonies está recorrido en headless
y el subsistema REVISADO de forma adversarial** (`#169`, 2026-08-26: spec §9.10 y §10); **el código
acotado que exigió esa revisión está HECHO** (tanda 4, `#171`→`#180`) **y la revisión de la propia
tanda, aplicada** (`#183`, spec §9.12; guion re-recorrido con la conducta definitiva, **111/111 ✓, 0 desviaciones**).
Sigue 🟦 solo por lo humano —el ojo del owner en navegador, el texto definitivo y la retención—: las
decisiones de §7 están tomadas y ejecutadas. Las dos altas que dejó la revisión —el alta manual que
«declaraba» sin declarar y el alta suelta sin anti-bot— están arregladas (`#178`, `#171`). **No toca la landing**. Detalle
en el tracker; las cuatro specs, en `docs/specs/` y en la tabla de enrutado de `CLAUDE.md`.
▶ **C (menores a cargo) arrancó el 2026-08-27 por la tarde: tanda 1 EMPUJADA** (`#191`, spec §9) —
la entidad, el registro con tope de servidor, la API contra el contrato y el RGPD, sin firmas de menor
todavía—; **la tanda 2 espera NUC-3** y las otras cuatro decisiones de §9.5.

✅ **La revisión adversarial que `CONVENCIONES` §5 exigía está HECHA** (`#156`): cada spec tiene su
**§8** con los hallazgos, y **ninguna hay que rehacerla**. De todas sus afirmaciones verificables
sobre el código, **ninguna resultó falsa** — lo que aquí no es lo normal (`#143` encontró tres falsas
en una sola spec).
❗❗ **Pero destapó DOS bloqueantes, y el peor no es de ingeniería:**
1. **El texto del waiver es literalmente un borrador** —lo dice él mismo, en los tres idiomas— y
   **publicar una versión es irreversible por diseño**. La maquinaria se puede construir; publicar la
   v1, no. Es un `[PENDIENTE: owner]` NUEVO y anterior al del plazo de conservación.
2. **JumpPoints descansaba sobre un hecho que el sistema no podía observar**: nadie sabe si un
   cliente vino (`tickets` tiene las columnas del ciclo y **cero escritores**). ✅ **Resuelto por el
   owner**: los puntos tienen **FUENTES configurables** — visita acreditada en la pantalla de puerta
   + compra pagada. Eso convierte el orden `A → D` en **dependencia dura**.
   ⚠️⚠️ **Y lo que no se puede perder**: la fuente «compra» **reabre el agujero de ingresos** si sus
   puntos se abren al instante. **Lo configurable es CUÁNTOS puntos da cada fuente, no CUÁNDO se
   abren** — un ajuste que permita «compra → disponible ya» lo reabre desde un formulario, sin que
   nada falle y sin que nadie lo revise.
⚠️ **Tres huecos de mecanismo que se deciden ANTES de la primera línea** (todos en `#156`): la cadena
de hashes del waiver **no tiene punto de serialización** —se bifurca en silencio bajo concurrencia, y
una cadena bifurcada no prueba nada—; el registro de firma va en **tabla propia**, no ampliando
`consents` (`cascadeOnDelete`); y el **alta presencial** también escribe consentimientos, así que
produce una firma **declarada por el operador** (`[DECIDIDO owner]`), que el PDF tiene que decir con
todas las letras.
⚠️ **Dos cosas tienen consecuencias fuera de su alcance**: el waiver **modifica `RGPD-01`** —⚠️ **y
`RGPD-01` no contiene hoy la frase que hay que modificar**: hay que añadirle primero lo que el código
ya hace y la invariante calla— y el carné QR **entra en `User::revokeAllAccess()`** desde el primer
commit, que es el modo de fallo exacto que `RGPD-06` existe para impedir.
❗ **Ninguna de las cuatro está aprobada todavía**: siguen 🟦 esperando el **✅ del owner**.

✅ **1 · El aforo, VERIFICADO bajo concurrencia — los CINCO caminos** (2026-08-25, `#147` + `#148`).
Era el mayor riesgo abierto: `purchase:verify-oversell` solo sembraba **entradas**, y los cumpleaños
se cuentan por otro camino entero (`PackAvailability`, pool propio y dos topes) que **no ejercitaba
ningún verificador**. Hoy son cinco escenarios —`entry` · `pack` · `pack-guests` · `pack-prep`
(tramo multi-franja **con montaje y limpieza ACTIVOS**, que es la configuración de producción) ·
`mixed` (los dos pools a la vez)— y **los cinco pasan** sobre MySQL con 8 y 16 procesos.
❗❗ **El verde vale porque el instrumento se vio FALLAR.** Con el `lockForUpdate()` retirado, el
mismo comando cazó **8 fiestas donde cabía 1**, **48 invitados donde caben 10** y **4 entradas + 4
fiestas donde cabía 1 de cada**. `OrderCreator` restaurado y comprobado por md5 y `git status`.
▶ Lo guarda `OversellVerifierCoversEveryQuotaTest`: si alguien retira un escenario, quita un contador
o mueve la guarda del instrumento a después del fork, la suite cae.
▶ **El detalle, las dos lecciones de método y lo que sigue sin medir están en `#147` y `#148`.**

✅ **1.bis · [DECIDIDO owner, `#151`] El consumo medido en `#148` es CORRECTO: la independencia de
cupos se hace POR ZONA.** Una fiesta de 8 en una franja de 10 deja 2 plazas de entrada — y eso es
el contador diciendo la verdad física: **dentro de una zona, `seats` cuenta ocupación real, sea del
producto que sea**. Un producto que necesite plazas propias se lleva a SU zona (así está hoy:
cumpleaños en `cumpleanos`, entradas en `jump`/`kids`; y así irá el siguiente — excursiones de
colegio → zona propia). `occupancyMap()` **no se filtra por tipo**, y `PackConsumesEntrySeatsTest`
pasa de «fijar sin juzgar» a **guarda de la regla decidida**.
▶ **Regla de instalación (white-label)**: productos que comparten zona comparten sitio físico;
independencia ⟹ zona propia. Es lo que hay que saber al configurar los aforos del 2º cliente.

✅ Y antes, el 2026-08-25 (`#141`): los dos contadores de aforo **ya disparan el gate** del
`pre-push`, con su control negativo y verificado por mutación.

❗ **2 · Pendiente del OWNER: un `Ds_Response=0900` REAL de Redsys.** Exige un pago de prueba con
tarjeta en el sandbox desde el navegador (staging) y después `redsys:verify-sandbox --gateway-order=…`.
Todo lo demás de la cadena está verificado con sus credenciales. ⚠️ Una medición anterior dio el
sandbox por inalcanzable y **era un error de medida**: se probó el 443 y Redsys sirve el suyo en el
**25443**.

⚠️ **3 · El `redis.conf` de staging sigue de fábrica**, aplazado a propósito por el owner: sin techo de
memoria y **deja de aceptar escrituras si falla un volcado**. Contenido acordado y riesgo medido en su
ficha de `DEUDA.md`; aplicarlo exige reiniciar el contenedor PHP desde el panel.

---

## ▶ El estado de la BD de desarrollo, antes de mirar nada

❗❗ **«LA» BD de desarrollo no existe: hay DOS, una por máquina, y NO comparten datos.** Medido el
2026-08-25 al fusionar las dos líneas de trabajo (`#150`): **el corpus documentado abajo vive SOLO
en la máquina del agente B** (26 pedidos de `cliente.demo`). En la máquina del agente A hay **24
pedidos de `admin@jumpweb.test` con CERO solapamiento** con los códigos que cita la spec —ni uno—,
más los 9 de las sondas `#149`/`#150`. Es también la razón de que esa máquina llevara TRES
migraciones sin aplicar (`#149`): el trabajo de corpus nunca pasó por ella. **Todo lo que este
apartado dice del corpus aplica a UNA máquina; antes de fiarte de nada, cuenta en la tuya.**

- **El corpus se construyó con 25 pedidos**, uno por acción accionable, por los **flujos REALES**
  (`OrderCreator` → vuelta de Redsys FIRMADA → acciones del panel por Livewire). Titular:
  `cliente.demo@jumpweb.test`. Los 58 anteriores **se borraron** el 2026-08-24 y no hay copia.
  ⚠️ **MEDIDO el 2026-08-25 al cerrar: hay 26, no 25.** Los 26 son de `cliente.demo` y **ninguno se
  creó ese día** (0 pedidos del 25/08, 0 de usuarios `@deleted.local`), así que **no vienen de los
  verificadores de concurrencia**, que limpian lo que crean. El desfase es anterior y **no se ha
  determinado su origen**: puede ser un pedido de prueba de otra sesión o que el índice de §22 esté
  incompleto. Se anota como medida, no como explicación. **Antes de fiarte del índice, cuenta.**
- **Los 25 cuadran**, así que el aviso de «desglose que no cierra» (`#132`) **no se puede ver en
  pantalla con estos datos**: para verlo hay que romper uno a mano.
- **El índice de los 25, con su código y su acción, está en `specs/desglose-dinero-cliente.md` §22.**
  ⚠️ Y §22.3 recoge cuatro trampas para conducir el panel desde un test — la peor: el cambio de FECHA
  lo mueve el CALENDARIO y no el formulario, y con `slot_date` solo la acción **no da error y no cambia
  nada**.
- La sonda que los creó **no está en el repo** a propósito (instrumento de medida, no guarda). Su
  receta sí, en §22.3.
- ⚠️ Dos productos llevan icono propio desde `#140` (tirolina → confeti, calcetines → calcetines); el
  resto usa el de su tipo.
- 🆕 **Además viven 9 pedidos de las SONDAS `#149`/`#150`** (los 6 escenarios del owner, la
  verificación de D5 y las de D4/pack-señal: `R-P4NA2I` `R-DKKV3J` `R-REM7YW` `R-ITHNOJ` `R-MOTEHE`
  `R-8STAH6` `R-VLRYUV` `R-ZDRAYL` `R-DWFRDP`), con su zona, productos y usuarios `sonda146*`.
  ✅ **[DECIDIDO owner, cierre 2026-08-25]: SE QUEDAN — los usa para probar el panel** (el modal
  nuevo de «Reembolsar» incluido). **NO limpiar.** La sonda de limpieza queda en `storage/app/`
  (sonda146-clean, vía tinker; no versionada) para cuando ÉL diga.
  ⚠️ Los de `#149` retratan defectos que ENTONCES estaban abiertos (dinero regalado/atrapado): no
  son corpus, no cuadran como él. Los de `#150` (`R-VLRYUV`, `R-ZDRAYL`, `R-DWFRDP`) retratan el
  comportamiento ARREGLADO.
- ⚠️ **La BD local llevaba TRES migraciones sin aplicar** (`payment_refunds.intent`,
  `zones.color_secondary`, `ticket_types.icon`) — el panel de reembolsos ni podía escribir. Aplicadas
  el 2026-08-25 (`#149`). **Tras un pull: `migrate:status` antes de depurar nada raro del panel.**

---

### El estado del cajón, para lo que venga

🟩 **EL CAJÓN ESTÁ COMPLETO, PULIDO Y VERIFICADO EN NAVEGADOR.** El bloque de cuenta es Vue (`#123`),
el layout no renderiza **ningún** componente Livewire, los nueve retoques que el owner pidió están
hechos (`#124`) y el guion de navegador que quedaba **se recorrió el 2026-08-23** (`#125`). Las cuatro
specs del cajón están ✅ EJECUTADAS.

⚠️⚠️ **LO QUE ESE TRABAJO ENCONTRÓ, y condiciona lo que venga. Léelo antes de tocar el cajón:**
- **El `no-store` de TODAS las páginas web lo ponía un accidente de Livewire** —un hook de componente
  encendía el flag que usaba un middleware global del paquete—, así que retirar el último componente
  lo habría borrado del sitio entero **con la suite en verde**: ninguna de sus 12 aserciones miraba
  una página del layout. Hoy lo pone `NoStoreWebResponses` (global, con puerta para `/api/v1`).
- **`route('logout')` aparece UNA sola vez en toda la aplicación**, y vive como **suelo servido dentro
  del hueco** del bloque: colapsado e invisible mientras todo va bien, a la vista si el motor no
  llega. Desmiente la premisa escrita de `#120(t)`, ya corregida.
- **Ocho clases se emitían sin una sola regla** —entre ellas la tarjeta de «Mis reservas»—, porque la
  transcripción a Vue **inventó nombres** en vez de reutilizar los de las páginas retiradas. Ahora lo
  vigila `SidebarStyleWiringTest`, que además dejó **seis huecos del EMBUDO declarados con nombre**:
  `cart__pending`, `catalog__per`, los tres de complementos y el motivo del pago denegado. **Están sin
  arreglar a propósito** —son pantallas ya validadas— y son el candidato natural a un pulido del
  embudo.
- **`SidebarIconParityTest` estaba ciego a 10 de los 32 `.vue`** (`**` no es recursivo en `glob()`).

⚠️ **Antes de añadir NADA al cajón, mira su presupuesto.** Es la holgura más estrecha de todo el
ledger, y **la cifra viva NO se copia aquí**: vive en `SidebarBundleBudgetTest::SIDEBAR_CHUNK_MAX_KB`
con su ledger al lado —ya envejeció una vez en esta tabla—. El techo subió dos veces el 2026-08-23
(`#125` y `#126`), las dos con su medición y su párrafo, y las dos por **corrección**, no por features.
⚠️ **El 2026-08-26 cedió por una FEATURE** (`#166`, el waiver en el cajón: **+4,94 KiB**, 221,5 → 226)
**y lo decidió el owner**, no el agente: se le pusieron delante el número y las tres salidas —subir,
partir en un chunk aparte, aparcar— y eligió subir. **La regla sigue siendo ésa**: el agente no sube
este techo por una feature; **pregunta**, con la medida y el coste de cada alternativa. Quedan 0,28 KiB.

🟩 **«MIS RESERVAS» SE LISTA POR RESERVA** (`DECISIONES #126`, `specs/mis-reservas-por-reserva.md` ✅):
una tarjeta por reserva con la referencia de su pedido, las vivas de la más próxima a la más lejana, el
historial en su propia zona tras un CTA y atenuado, **5 por página ordenadas en el SERVIDOR**
(`GET /api/v1/me/reservations/{scope}`).
⚠️⚠️ **Lo que no se puede no saber antes de tocarlo**: los dos ámbitos son **los dos lados de UN
predicado** (`where`/`whereNot` sobre la misma expresión), **no dos consultas**. Si alguien las separa,
una reserva puede **no salir en ninguna de las dos pantallas** — y eso no falla, no avisa y no se ve:
una lista a la que le falta una fila se lee perfectamente. Lo sostiene `Sales\CustomerReservationsPageTest`,
que asevera la PROPIEDAD y no una lista de casos.
⚠️ **El ledger NO viaja con la tarjeta**: es del pedido y se repetiría tantas veces como reservas tenga.
Se pide con `GET /orders/{code}` al desplegar «Ver pedido».

⚠️ Y sigue abierta la decisión aplazada de `specs/area-cliente.md` §3.4: **si la zona activa cambia la
URL**. Hoy el «atrás» del navegador no hace nada dentro del cajón y una zona no se puede enlazar.

✅ **EL GUION DE NAVEGADOR ESTÁ AL DÍA** (2026-08-23, `#125`): `V17`, `V18` y `V23` —los tres que
`#122` y `#124` dejaron sin recorrer— **están HECHOS y en 60/60** tras arreglar los dos fallos reales
que destaparon. El qué pasó, en el tracker; el guion, en `VERIFICACION-E2E-CAJON.md` §5.septies.
⚠️ **Lo único que queda pide un DISPOSITIVO**: `V23·3` en **móvil real** (el scroll que arrastraba la
página) y `V20·6` con «reducir movimiento». Los dos necesitan staging — que **no lleva `#123`–`#126`**.

### Dónde está hoy «Mi cuenta»

🟩 **ENTERA en el cajón.** `/mi-cuenta` y `/mi-cuenta/pedidos` ya no pintan nada: sirven la home y el
cajón se abre solo en su zona (`Http\Sidebar\AccountDoor`).

| Zona del cajón | Qué cubre |
|---|---|
| `ORDERS` | «Mis reservas»: historial, ledger financiero completo, reintento y **las respuestas del pack bajo demanda** |
| `PROFILE` · `PASSWORD` · `SESSIONS` | Tus datos con el ciclo del correo pendiente · contraseña · cerrar las demás sesiones |
| `PRIVACY` | Consentimientos · descargar mis datos (art. 20) · borrar la cuenta (art. 17) |
| `HOME` | El índice, con su próxima reserva |

⚠️ **«Cerrar sesión» NO está en el índice, y es una decisión** (`#120(t)`, owner): sigue vigente.
⚠️⚠️ **Pero su premisa era FALSA y se corrigió el 2026-08-23**: decía «ya existe dos veces fuera, en el
nav y en el bloque `.acct`». Medido: **existe UNA**, la del bloque — `route('logout')` sale una sola
vez en toda la aplicación, y con sesión el nav es un botón que solo abre el cajón. La decisión no
cambia; el riesgo sí, y lo resuelve `specs/account-context-vue.md` §4.8.

⚠️ **Lo único que sobrevive de la web**: `GET /mi-cuenta/exportar`, que es una **DESCARGA** y no una
vista. Sirve el MISMO documento que `GET /api/v1/me/export`, y hay un test que los compara campo a
campo — es lo que impide que vuelvan a divergir.

### Lo hecho, en una línea por tanda

- 🟩 **Tanda 1 (leer)**: cinco pasos, `V4`–`V7`. `#120(g)`–`(m)`.
- 🟩 **Tanda 2 (gestionar)**: contraseña y sesiones, perfil, y los dos derechos RGPD. `V8`–`V10`.
  `#120(n)`–`(s)`. ▶ **Su efecto de fondo**: de los **cuatro** sitios de la web que reconfirmaban
  contraseña **sin techo**, no queda ninguno — y no se escribió una línea de limitador en la web.
- 🟩 **Tanda 3 (retirar)**: primero se publicó lo que solo sabía la página —el desglose financiero y
  los consentimientos— y **después** se borró. `V11`–`V13`. `#120(t)`, `#120(u)`.
  ▶ **La auditoría fue la mitad del trabajo**: encontró **dos huecos reales** que nadie vigilaba —el
  reintento de la API sin techo comprobado y las líneas fantasma que solo la API publicaba— y ambos
  se cerraron antes de borrar nada.

### Cinco cosas que condicionan lo que toques aquí

| | |
|---|---|
| **Las PUERTAS** | `/mi-cuenta` y `/mi-cuenta/pedidos` abren el cajón en su zona. ⚠️ La zona se aplica en `bootSpaEngine()` **y no en `open()`**: el cajón que llega por una puerta **nace abierto**. Es el camino que ya dejó un hueco vacío en `#59(b)` y volvió a morder en `#120(u)` |
| ⚠️ **La red** | **NO es el diff de árbol** (`#120(e)`): es paridad de DATOS contra la API + navegador. `render-sidebar.mjs` no importa la raíz |
| ⚠️ **La cadena flex** | `.sidecart__body` → `#sidecart-spa` → `.purchase` → `.purchase__scroll` son **hijos DIRECTOS**: un envoltorio router la parte y **ningún test lo ve** (`specs/area-cliente.md` §4.9) |
| ⚠️ **Los presupuestos** | ⚠️⚠️ **NO se copian aquí los números: viven en su test y esta tabla ya envejeció una vez.** Decía 190/4.800 cuando el código llevaba un día en **199 KiB** y **5.720 B** —la sesión de la auth los subió y nadie refrescó esta fila (medido el 2026-08-23)—. Los VIVOS son `SidebarBundleBudgetTest::SIDEBAR_CHUNK_MAX_KB` y los dos techos de `SidebarMountTest` (anónimo y con sesión), cada uno con su ledger al lado. Lo siguiente que entre los sube **a propósito, con su medida y su párrafo**, y al cerrar **baja a lo medido** |
| ⚠️ **El techo de componentes** | 40 líneas por `.vue`. Ya obligó al rediseño correcto una vez (`#120(r)`): si vuelve a apretar, la pregunta es qué sobra ahí, no cuánto subirlo |

⚠️ **Y una regla de trabajo que esta fase dejó pagada con tres fallos**: en un refactor o una feature
del ORQUESTADOR, **el contrato de árbol no es red** —`render-sidebar.mjs` no importa la raíz y su
comentario dice por qué—. La red es el NAVEGADOR. Receta del andamio, con sus trampas medidas, en
`VERIFICACION-E2E-CAJON.md` §5.bis y §5.quater.

### ❗ Bloqueado, y lo desbloquea el owner

❗❗ **EL SCHEDULER NO CORRE EN STAGING** (`DECISIONES #115`). El crontab está instalado y correcto y
`schedule:run` funciona a mano, pero **no hay demonio cron en el contenedor del sitio**. Medido: 6
avisos con 24 h en `jobs` y `attempts = 0`, y un pedido 24 h sin caducar que `orders:expire` caducó al
instante al lanzarlo a mano.
⚠️ **RE-CONFIRMADO en los DOS despliegues del 2026-08-25** (y antes el 2026-08-23): el propio
`deploy.sh` reinstaló el crontab
—«1 entrada, sin duplicados»— y su verificación de salud volvió a avisar de que **no se ve ningún
demonio cron**. Las cinco tareas están REGISTRADAS en la app y no hay jobs varados, así que lo único
que falta es quien las dispare.
▶ **La entrada exacta que hay que poner en el panel de Enhance, y cómo comprobar que funciona, están
en `ENTORNOS.md` §4.** Mientras tanto se dispara a mano:
`ssh jumpweb-staging "cd ~/public_html && php artisan schedule:run"`.
⚠️ Obliga a matizar `#110`: sus cuatro caminos siguen valiendo —ninguno depende del cron— pero **allí
nunca se ha ejercitado la caducidad de pedidos ni el envío diferido de correo**.

▶ Y luego, `scripts/provision.sh` (`#102(f)`), que necesita un token nuevo del panel: el que se usó
para medir lo retiró el owner.

⚠️ **SIETE trampas MEDIDAS que condicionan lo que venga.** No se explican aquí —cada una tiene su
sitio y duplicarlas es lo que envejece esta foto—; se nombran para que no te pillen:
- **Un `assertSee` de un texto del grupo `tickets` contra una página completa NO PRUEBA NADA**: el
  montaje del cajón lo lleva entero en cada página. Receta y porqué: `TESTING.md` **§2.ter**.
- **Lo que un gate declara que NO mira es un hueco con nombre** — así se sirvieron 20 iconos vacíos:
  `TESTING.md` **§2.quater** y `DECISIONES #113`. ⚠️ **Y a veces el gate ni lo declara**: el contador
  de `SidebarComponentBudgetTest` miraba `api.get|post` y no los tres verbos que llegaron después
  (`#120(s)`). Al añadir una pieza, relee qué mide su guarda — no si sigue verde.
- **Un campo que NUNCA lleva valor se lee como un dato y no lo es**: el export publicaba
  `tickets[].code` con una columna que no existe, desde el commit fundacional (`#120(s)`).
- **Una comprobación que mide una cosa y se lee como otra es PEOR que no tenerla**: dos señales de
  salud en verde con el scheduler muerto (`#115`).
- **Que las piezas se llamen no significa que el valor LLEGUE**, y que los dos extremos estén probados
  no significa que el medio esté cableado: `#117`, `#118` y `#119(f)` son tres fallos vivos distintos
  de la misma familia, todos encontrados en un navegador y ninguno por la suite.
- ✅ **El modo `embedded` de `auth.login`/`auth.register` MURIÓ el 2026-08-23** (`#122`), como `#112(f)`
  anticipó: era la referencia de dos paridades de árbol y las dos se fueron con él. De sus 14 casos,
  **8 no comparaban superficies** y están mudados a `SidebarMountTest`, `Api\V1\AuthRegistrationTest`
  y `SidebarAntiBotTest` — uno de ellos llevaba dentro el techo del payload del montaje.

### Lo que NO depende de nosotros

- ✅ **Servidor de PRUEBAS**: `jumpweb.sites.aelium.app` (`#76`), **desplegado y sirviendo** (`#106`).
  **0 LIVE · 0 PRODUCCIÓN.** Las cuatro cosas que estaban atascadas por falta de URL pública
  —Turnstile, S2S, 3DS y móvil— **están verificadas** (`#110`).
  ⚠️ **Dos diferencias con local que siguen condicionando el trabajo** (`ENTORNOS.md` §4): la BD es
  **MariaDB 11.4, no MySQL 8.4** —«verificado en staging» **NO** equivale a «verificado en MySQL», y
  ninguna conclusión sobre concurrencia sale de ahí— y **no hay node/npm**, así que los assets se
  construyen fuera y se suben compilados.
  ⚠️ **El bucle de trabajo sigue siendo LOCAL**; staging se toca EN BLOQUE y con guion escrito
  (`VERIFICACION-E2E-CAJON.md` §5.ter).
- **Pendiente del owner** (❗), por gravedad:
  1. ❗❗ **ACTIVAR LAS TAREAS PROGRAMADAS** del sitio en el panel de Enhance — sin cron no hay envío de
     correo ni caducidad de pedidos (`#115`). La entrada exacta, en `ENTORNOS.md` §4.
  2. Un **token nuevo de la API del panel** para `scripts/provision.sh` (el de medir se retiró).
  3. ❗ **Una pasada por el SANDBOX de Redsys para el reembolso REST de punta a punta**: que
     `Redsys::executeRefund()` hable de verdad con la pasarela y su respuesta se parsee bien. La
     auditoría del desglose lo dobló a propósito —una auditoría de dinero no hace llamadas externas—
     y **local no lo puede probar**. Herramienta canónica: `php artisan redsys:verify-sandbox`
     (`PAY-08`). Es el único hueco de esa auditoría que no se cerró.
  3. 2FA del panel · backlog de producto de Fase 6.
  ✅ Resueltos: el acceso SSH del 2º puesto (2026-08-21) · **las dos comprobaciones de navegador que
  cerraban `4.7`** (2026-08-22) · **la spec del área de cliente**, validada el 2026-08-22 —modelo de
  navegación y modo `account`, `#120(d)`— · y las **cuatro decisiones de producto** que el área pidió
  sobre la marcha: publicar la entrada de verdad en el export (`#120(s)`), no llevar «Cerrar sesión»
  al índice, publicar los consentimientos y enseñar las respuestas del pack bajo demanda (`#120(t)`,
  `#120(u)`).

## ▶ Hasta dónde llega hoy el motor SPA, dicho sin optimismo

El cajón **recorre el embudo entero y vuelve**: catálogo → día → hora →
cantidad → complementos → carrito → identificarse (entrar o **crear cuenta** dentro del cajón) → pagar
→ auto-POST firmado a Redsys → y los **tres desenlaces** (reserva creada con su resumen, rechazo con su
motivo y reintento, y el sondeo cada 5 s del terminal *data-less*). Con las reservas pausadas sustituye
el flujo por el aviso de mantenimiento. La cesta sobrevive a la recarga.

⚠️ **Residual de la pausa**: el estado se relee al cargar la página, en cada apertura del cajón y al
pulsar «Ir a pagar». Un cajón ABIERTO y quieto no se entera del interruptor hasta cerrarlo, reabrirlo o
intentar pagar.

## ▶ El MAPA del cajón SPA — **movido**

Vive en `docs/specs/sidebar-spa.md` §8 desde el 2026-08-25. Un mapa de ficheros es referencia para
quien toca el cajón, no «dónde estamos»: en la foto viva solo engordaba la carga obligatoria de cada
arranque.

## ▶ Lo que NO hay que reimplementar (el terreno del dinero está entero)

- **Precio** → `Booking\Contracts\CartPricing`. `CartPricerTest` compara sus importes con el pedido
  REAL: es el espejo verificado de `OrderCreator`.
- **Admisión** → `Booking\Contracts\ReservationAdmission`: pausa, tope de pendientes, frecuencia y la
  extensión atómica del hold. `POST orders` llama a `admitReservation()`, que CONSUME ficha; el
  reintento, a `admitPaymentRetry()`.
- **Creación** → `OrderCreator` (`AFORO-01`: el lock con `zone_id` literal es la PRIMERA sentencia de la
  transacción; no metas ningún SELECT antes).
- **Ida del pago** → `Booking\Contracts\PaymentInitiation` (`open()`/`reopen()`), implementado por
  `Payments\Services\PaymentInitiator`. Lanza `PaymentInitiationException`, que vive en
  `Payments\Contracts` porque es lo que lanza el puerto.
- **LA SECUENCIA** → `Booking\Contracts\ReservationCheckout` sobre `CheckoutOrchestrator` (`#37`). **Es
  el sitio ÚNICO donde vive el orden**: admitir consumiendo ficha → crear con la ventana de retención
  (`AFORO-10`) → abrir el cobro sobre el pedido persistido → soltarlo **solo** si era el primer intento.
  ⚠️ **No lo reescribas en una superficie nueva**: pide `start()`/`retry()` y traduce el resultado
  (`CheckoutSequenceTest` lo prohíbe ejecutablemente fuera de `app/Domain`).
  ⚠️ **No envuelvas la secuencia en una transacción**: el rastro de incidencia haría rollback (`PAY-05`)
  y el lock de franjas quedaría sostenido durante la firma (`AFORO-01`).
- **Oferta de fechas/horas** → `Booking\Contracts\AvailabilityOffer` sobre `SlotOffer` (`AFORO-02`), con
  la cesta descontada. Publica DOS números: `available` para MOSTRAR y `max_quantity` para ACOTAR el
  selector — en un pack **no coinciden**.
- **Desenlace del pago** → `GET orders/{code}/payment-status`, con dos ejes (`order_status` ·
  `payment_status`) y el motivo del rechazo como código y como texto.
- **Errores de negocio** → `Http\Api\ReservationErrorMap`. Añadir un código es evolutivo; **partir uno
  existente rompe a todo cliente ramificado sobre él**.
- **La cesta que viaja por la API** → `Http\Api\CartPayload`, una sola forma para los tres endpoints.

⚠️ **Tres trampas de la API que la SPA pisa** (las **87** medidas están en `specs/api-v1.md` §10):
`Origin`/`Referer` hacen falta en TODAS las peticiones stateful, no solo en el login (§10.sexies 28) ·
la disponibilidad LLEVA la cesta y publica dos números (§10.nonies 46) · **la firma cubre la URL
EXACTA**, así que las URLs de API se firman aparte (§10.duodecies 64).

**Pendiente que hereda Fase 6** (`#35`): un cliente NATIVO averigua el desenlace del pago **solo
sondeando** `payment-status`, y eso exige `redsys_merchant_url` configurada — sin ella y con terminal
data-less, el pedido caducaría con la tarjeta ya cobrada (`PAY-02`).

## ▶ Índice de la Fase 4 — **retirado**

⚠️ Eran 83 líneas que **duplicaban `00-REFACTOR.md`**, y la Fase 4 está CERRADA. Verificado antes de
borrar: los once pasos (`4.0a` … `4.7`) están en el tracker, cada uno con más detalle del que había
aquí. Una foto viva que repite el tracker es una segunda verdad esperando a divergir —y `CONVENCIONES`
dice que ESTADO **resume** el tracker y nunca lo contradice—.

▶ Para el detalle paso a paso: `docs/00-REFACTOR.md`, sección **Fase 4**.
