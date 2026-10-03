# [SPEC] La otra zona — entradas de varias zonas en UNA compra de la isla

> Estado: ✅ aprobada por el owner (02-10: D1-A, D2-A, D3-B; D4-A por el mockup, `#767`) → a implementar ·
> Última actualización: 2026-10-02 · Decisiones asociadas: `#878`, `#882` · Carril: plataforma · Es la L2 de `#876`
> (`isla-y-landing-nueva.md` §4.28, punto 6).

## §0 · Antes de tocar

- **La regla**: un pedido de entradas de la isla puede llevar varias zonas, como ya dibuja el mockup —«¿Alguien va a la
  otra zona? Añádelo a la misma reserva» en la pantalla 0 y «Añadir otra entrada» en «Pagar»—, con UN día y UNA hora para
  todo el pedido (D1). El servidor ya lo admite ENTERO (varias líneas, `validate-line` con la cesta de contexto,
  `OrderCreator` bloquea todas las zonas): el trabajo es de la isla.
- **Empieza por** §1.3 (lo pintado y apagado) → §4.
- **Trampas**: (1) la cesta de la isla SUSTITUYE su línea porque el recibo no tenía «quitar» (`#692`, T3e·3): una
  línea más exige poder quitarla en «Pagar», y el mockup no lo trae (D2). (2) Cada línea se valida con las anteriores
  en `items` y la candidata FUERA (`cart.js::validateLine`), o compite consigo misma. (3) Los pesos, a ras de su techo:
  la compra 212,95 de 214 y los pasos bajo 57 (`SidebarBundleBudgetTest`, tras la K3, 03-10). (4) La hora llena
  (`#822`) y la vuelta del banco, con dos líneas.
- **Estado**: ✅ aprobada (`#878`). **K1 ✅ · K2 ✅** (§4.6) · **K2·b ✅** (`#882`, §4.7) · **K3 ✅** (03-10, §4.3: varias
  líneas, «Pagar» y «Añadir otra entrada»; visto bueno del owner); sigue K4. ➕ `#881`: los grupos de elección, su modelo
  en la K1 y su pregunta en la K2.
- **Invariantes**: `PAY-12`, `PAY-20`, `AFORO-01`, `AFORO-02`, sin el `CRITICAL_RE`; del servidor, solo un dato de
  lectura en la ficha (`stay_minutes`, contrato 1.63.0, `#882`); la compra entera con la pasarela de pruebas (§6).

## 1. Contexto — medido el 02-10

### 1.1 Lo que pide el owner (`#876`, punto 6)
«En la compra de entradas, “recomendamos también añadir entradas Y”: el cliente que compra entradas para el JUMP y
quiere para sus hijos KIDS, permitirle también añadir esas entradas en el mismo proceso, que no haga el proceso dos
veces; y ya salen con el mismo día y hora que el adulto, o de alguna manera que pueda cambiar la hora y el día, sin
sacarlo del flujo ni obligarle a hacer varias compras en vez de una con más productos.»

### 1.2 El servidor ya lo admite
- **Un pedido, varias líneas** (`COMPRA-PRODUCTOS.md` §3, «Pedido único»): entradas, packs y complementos en un solo
  `orders`; el cajón ya vendía cestas de varias líneas.
- **`POST /cart/validate-line`** (`CartLineController` → `Booking\Contracts\CartLineValidation`): la candidata en `line`
  y la cesta actual en `items` —lo que ya retiene plazas—; el tope, `OrderCreator::MAX_LINES_PER_CART` = 50 (`PAY-12`).
- **El aforo de un pedido de dos zonas**: `OrderCreator::lockSlots()` pide `ZoneDaySlotLock::acquire($zoneIds, $dates)`
  con TODAS las zonas y días de la cesta, en orden estable (`orderBy('id')`: sin interbloqueos, `AFORO-01`).
- **El precio**: el presupuesto (`/orders/quote`) suma las líneas en servidor (`PAY-12`, `PAY-20`).

### 1.3 La isla: lo dibujado, lo pintado y lo apagado
- **El mockup** (`instancias/playjump/diseno/…/paginas/compra/`): en la pantalla 0, tras la cantidad y los calcetines,
  el enlace «+ ¿Alguien va a la otra zona? Añádelo a la misma reserva»; al tocarlo, una tarjeta con la primera fila de
  la otra zona («1 hora»), su precio por persona, su cantidad y «Quitar»; las horas se calculan con la gente de las DOS
  líneas (`pantalla-0.jsx`). En «Pagar», cada línea con − / + (mínimo 1) y «Añadir otra entrada», solo en entradas
  (`pasos-1-2.jsx`); lleva a la pantalla 0 en modo «otra» con el día y la hora FIJOS, «¿Qué zona?», «Cuánto tiempo» y
  «Cuántos», y «Continuar» suma la línea —o la cantidad, si es la misma fila— y vuelve a «Pagar» (`compra.jsx`:
  `anadirOtra`, `continuarCuando`). Las frases de varias líneas, con « + » (`datos.js`: `linea`, `lineaListo`, `aMedias`).
- **La isla YA PINTA todo eso** (`PantallaCuando.vue`: la tarjeta, «Quitar» y el enlace; `PantallaPagar.vue`: «Añadir
  otra entrada»; sus textos en `lang/*/isla.php`: `compra.cuando.otra_zona`, `otra_titulo`, `quitar`, `compra.pagar.otra`),
  pero su LÓGICA lo apaga: `pantalla-cuando.js` da `otra: null` y `otraZona: false`, y `recibo.js`, `otraEntrada: false`.
- **La compra es de UNA línea**: `linea.js::meterLinea` vacía la cesta y mete la suya (`#692`); `compra.pedido` describe
  una fila; 15 sitios leen `lines[0]` o `compra.pedido` (`usePagoCompra.js`, `useSeccionCompra.js`, `recibo.js`).
- **Por qué se apagó** (T3e·3, `#694`, `isla-y-landing-nueva.md` §4.10): sin pantalla de cesta ni «quitar», lo que
  quedara de otra visita se compraría a ciegas. Se hizo de una línea y la otra zona quedó para después.
- **Datos en LOCAL** (producción lleva el catálogo del folleto 26-09, otro): Jump con 1 h y 2 h, Kids con 1 h, 2 h e
  ilimitada; las dos zonas con la misma rejilla (17, 18, 19 y 20 h un lunes) y sin horario propio. Kids admite por
  debajo de su edad desde 90 cm con un adulto; Jump, con un adulto por debajo de 1,30 m.
- **La analítica**: `line_added`/`line_removed` (`product`, `qty`) existen; nada dice que una línea vino de la otra zona.

## 2. Objetivo
- En una compra de ENTRADAS, añadir las de otra zona sin salir: UN pedido, UN pago y UN correo.
- Con el día y la hora del primero; cambiarlos, para todo el pedido y sin salir (D1).
- Una línea añadida se QUITA en «Pagar» (D2) y la primera queda intacta.
- **Nada de negocio quemado**: «la otra zona» son las zonas de entradas activas con oferta ese día, menos la de la
  primera línea; con dos, se nombra (D3); con tres o más, «¿Qué zona?»; con una sola, no sale nada.
- **Éxito, medido**: una compra Jump + Kids desde la pantalla 0 y otra desde «Pagar» llegan al banco de pruebas y
  crean UN pedido con DOS líneas, cada una en su zona y su franja (BD); el total del recibo es el del presupuesto;
  quitar la añadida deja la primera; los pesos, dentro de su techo o subidos con la medida delante.
- **Fuera**: horas distintas por línea (salvo D1-B); cumpleaños y excursiones (el mockup solo lo pone en entradas); la
  calculadora de la página (el mockup no lo pone ahí: desde ella, «Pagar»); el cajón (ya vende varias líneas);
  «Reservar otra vez» de un pedido de dos zonas (se mide en K4).

## 3. Opciones — lo que decide el owner
`[DECIDIDO owner, 2026-10-02]` (`#878`): **D1-A**, **D2-A** y **D3-B**, las recomendadas. **D4-A** no se le preguntó: lo
decide el mockup (`#767`).

- **D1 · El día y la hora.** **A (recomendada)**: uno para todo el pedido, como el mockup. La pantalla 0 ofrece solo
  las horas en las que caben TODAS las líneas; en «Añadir otra entrada» van fijos, y si la otra zona no cabe a esa hora
  la isla lo dice y propone las cercanas en las que caben todos (cambia la de todos, sin salir). **B**: cada línea con
  su día y su hora; más libre, pero el recibo, «¡Reservado!», Mi QR, la isla tras la compra y el correo dirían dos horas,
  para el caso raro de una familia que no viene junta.
- **D2 · Quitar en «Pagar»** (el mockup: mínimo 1 y sin quitar). **A (recomendada)**: «Quitar» en cada línea añadida,
  el enlace del sistema como en la pantalla 0; la primera no se quita (es el pedido). **B**: el − llega a 0 y la quita.
- **D3 · El texto.** **A**: el del mockup, «¿Alguien va a la otra zona? Añádelo a la misma reserva». **B
  (recomendada)**: con dos zonas, nombrarla con su nombre del panel —«¿Alguien va a Kids? Añádelo a la misma reserva»—,
  más cerca del «recomendamos añadir entradas Kids» del owner; con tres o más, el de A.
- **D4 · Dónde.** **A (recomendada)**: en los dos sitios del mockup. **B**: solo en «Pagar».
- **Del agente** (técnico, `#630`): el modelo de la compra (§4.1), los calcetines con dos líneas (§4.2) y la medida
  (§4.5: una prop nueva en el contrato, del SPA).

## 4. Diseño (D1-A, D2-A, D3-B y D4-A, `#878`)

### 4.1 El pedido de la isla, con varias líneas
- `compra.pedido` gana `otras: [{ fila, n, guardian }]`, con el `dia` y la `hora` del pedido. `lineaDe` pasa a dar las
  líneas del pedido y `meterLinea` a meterlas TODAS: la primera con la cesta vacía de contexto y cada una de las
  siguientes con las anteriores en `items`, en orden; si una dice que no, la cesta vuelve a lo que era (todo o nada) y
  el aviso nombra la zona que no cabe. Después, guardar y pedir el presupuesto, como hoy.
- Los 15 sitios de `lines[0]`/`compra.pedido` leen las líneas por su producto, no por su posición.

### 4.2 La pantalla 0 («la otra zona»)
- `pantalla-cuando.js`: `otraZona` sale de las zonas de entradas con oferta ese día; `otra` es la tarjeta (el nombre de
  la zona, su primera fila, el precio por persona, la cantidad y «Quitar»).
- Las horas: las que caben para TODAS las líneas (la oferta de cada producto, `/availability/{product}/times`, cruzada
  por hora con la cantidad de cada uno).
- Los calcetines siguen siendo UNA cantidad del pedido (el mockup) y viajan en la primera línea mientras su tope lo
  admita; si no, se reparten. Se mide en K1 con el resolutor de complementos antes de elegir.

### 4.3 «Pagar» («Añadir otra entrada» y quitar)
- `recibo.js`: `otraEntrada` en entradas con otra zona; una fila por línea del presupuesto, con su − / +; «Quitar» en
  las añadidas (D2-A).
- «Añadir otra entrada» abre la pantalla 0 en modo «otra» (día y hora fijos; «¿Qué zona?» solo con tres o más; «Cuánto
  tiempo»; «Cuántos») y «Continuar» suma la línea —o la cantidad, si es la misma fila— y vuelve a «Pagar».
- **Al hacerla (03-10, K3; lo técnico, del agente, `#630`)**: (1) el borrador lleva una LISTA de líneas añadidas
  (`otras`, no una `otra`): cada una es una tarjeta de la pantalla 0 (§4.7) y volver atrás no pierde nada —el mockup
  rehacía el pedido desde la pantalla 0 y perdía lo añadido en «Pagar»—; un borrador guardado con la forma de antes (la
  vuelta de Google o del banco) se lee igual. (2) «¿Qué zona?» con DOS zonas o más, no solo con tres: lo dicho al owner en
  `#882` —«las mezclas dentro de una zona (un Jump de 1 h y otro de 2 h) las cubre la K3»—; de partida, la otra zona (el
  mockup) y su tiempo parecido al de la primera línea (§4.7). (3) Su pie, el del pedido de ahora (el mockup); el precio de
  la nueva, en sus opciones. (4) Si no cabe a esa hora, lo dice con el nombre de su zona; proponer las cercanas para
  todas es la K4. (5) De partida, el tiempo parecido entre los que AÚN NO están en la reserva (quien pulsa quiere algo
  nuevo); uno que ya está se puede elegir y suma gente a su línea («Ya está en la reserva: se suma a ella»). En las
  tarjetas de la pantalla 0, en cambio, el tiempo que ya está se ve apagado («Ya está en la reserva»): allí no se suma.

**K3, hecha (03-10; visto bueno del owner: «Está perfecto»).** El borrador con su LISTA (`intencion.js::conOtras`, que lee también la forma
de antes); `otra-zona.js` con una tarjeta por línea —también de la zona del pedido—, lo que falta con su índice
(`pjc-q-otra-<i>`, `pjc-q-otra-tiempo-<i>`; `falta.js` por prefijo), el porqué de una hora con la zona de la que no cabe y
los tramos si ALGUNA estancia no coincide; `usePantallaCero`, lo de cada fila en mapas (`horasOtras`, `fichasOtras`…) y las
acciones con su índice. «Pagar» (`recibo.js`): las filas por PRODUCTO (`l<id>`), cada línea con su − / +, «Quitar» en las
añadidas (D2-A) y «Añadir otra entrada» en entradas; `usePagoCompra` cambia y quita cualquiera (`cambioDe`, `quitarDe`), el
aviso nombra su zona (`linea.js::avisoDeLinea`) y el borrador lo sigue (`borradorDeOtras`). «Añadir otra entrada»:
`otra-entrada.js` (puro) y `useOtraEntrada.js` (su ficha, su oferta a esa hora, los días que falten), «Continuar» rehace el
pedido entero (`linea.js::conNuevaLinea`: otra línea o más gente en la suya). **Medido**: JS 1.807 (`otra-entrada.test.js`
nuevo; cuatro mutaciones muerden: sin «Quitar», sin sumar a la suya, de partida lo que ya está y el tiempo ya en la reserva
sin apagar), las guardas (la compra 204,81 → 212,95, techo 214); una sonda desechable a 390 y 1280, 14/14 —«Quitar» solo
en la añadida, los totales = el presupuesto del servidor pedido aparte (29,60 €, 40,80 €, 47,20 €), de partida Jump 1 h, el
mismo tiempo suma, la flecha vuelve sin cambiar nada y la pantalla 0 recuerda lo de «Pagar»—; K2·b 19/19, M1 12/12, M2
13/13, M3 11/11, `sonda-conversion` 22/22 y `sonda-isla` 26/26.

### 4.4 La hora llena y la vuelta del banco
- `#822` con varias líneas: las horas cercanas son las que caben para TODAS.
- La vuelta del banco y la compra a medias (`marcarSalida`) guardan el pedido con sus `otras`; «¡Reservado!», la isla
  tras la compra y Mi cuenta dicen las líneas con « + », como el mockup.

### 4.5 Textos y medida
- Clave nueva en es/en/fr para D3-B («¿Alguien va a :zona? Añádelo a la misma reserva»); el resto ya existe.
- `line_added` de la línea añadida con una prop que diga de dónde vino (`otra_zona` | `otra_entrada`): es del contrato
  de eventos, del SPA; se le pide por el buzón antes de K2.

### 4.6 Las tandas
**K1** el modelo y la cesta, sin pantalla nueva (pruebas de nodo) → **K2** la pantalla 0 (y su tarjeta completa, la
**K2·b** de `#882`, §4.7) → **K3** «Pagar» y quitar →
**K4** la hora llena, la vuelta del banco y la compra entera en la sonda. Cada una, al ojo del owner en vivo.
➕ `#881` (`[DECIDIDO owner]`, 02-10): en K1 y K2, los GRUPOS DE ELECCIÓN que la M1 de `#880` no pinta —uno en una entrada o
un segundo en un pack—: la elección de cada grupo en el borrador y en la línea, y su pregunta con `TarjetasOpcion`, como el
menú del pack (`fiesta.js::menusDe`, hoy solo el primer grupo).

**K1, hecha (02-10 noche; sin pantalla: nada se ve todavía).** `linea.js`: `pedidoDe` con `otras` (sin la fila del pedido
—se funde en ella— ni repetidas), `lineasDe` (la suya primero; las otras con el mismo día y hora y lo que el servidor resolvió
de cada una), `resolverOtras` (`POST /catalog/products/{id}/addons` de cada una, en paralelo; sin respuesta, `null`: nada a
medias), `meterLineas` (en ORDEN, cada una con las anteriores de contexto; TODO O NADA, y dice qué `fila` no cupo) y
`conLaCesta` (las cantidades por PRODUCTO). `meterLinea` mete el pedido entero; «Continuar», la vuelta de Google y el
rehacer de «Pagar» leen por producto (fuera los `lines[0]`). Los grupos (`#881`): `elecciones` en el borrador
(`{ [grupo]: producto }`), `complementos.js::eleccionesDelBorrador` (el menú de una fiesta en el primero; los demás —en una
entrada, todos— por su clave), `cambiar('eleccion')`, y se limpian al cambiar de fila o de pack. **Medido**: `linea.test.js`
(17) y `complementos.test.js` (1.749 de JS); dos mutaciones vistas morder —sin devolver la cesta (cae el todo o nada) y con la
cesta vacía de contexto (caen el orden y la fusión)—; `SidebarBundleBudgetTest` (la compra 193,11 → 195,08, a 196);
`sonda-conversion` 22/22 y la sonda desechable de la M1, 12/12, de punta a punta (una línea, como antes).

**K2, hecha (02-10 noche; vista por el owner).** `otra-zona.js` (puro): las otras zonas de entradas con su primera fila,
las que se venden ese día, la línea nueva (su primera fila, una persona: el mockup), sus horas con la cesta (`AFORO-02`) y
lo que pinta la pantalla —el enlace que la NOMBRA con una sola zona (D3-B; con varias, el genérico; sin ninguna, nada), la
tarjeta (su fila, su precio de ese día por persona, su gente y «Quitar»), si no se vende ese día (`bloquea`: la tarjeta lo
dice en rojo y lo que falta es ella, M2, `pjc-q-otra`), su parte del resumen con « + » y las horas en las que NO cabe
(apagadas con su porqué; cada línea en su zona, no la gente sumada del mockup)—. `usePantallaCero`: sus días con los de la
zona, su ficha (el justificante) y sus horas al añadirla, la hora que deja de caber se vacía, y el PRESUPUESTO de todas las
líneas (`POST /orders/quote`, sin tocar la cesta: el total de la pantalla 0 es el suyo, `PAY-12`; mientras llega, sin total).
Solo en ENTRADAS. Al continuar, la que no cabe se dice con el nombre de su zona; la pantalla de la hora llena sigue siendo de
la del pedido (las cercanas para todas, K4). Los grupos de `#881`: `gruposComoFilas` y su pregunta en la lista
(`ComplementosCompra`, forma `grupo`, con `TarjetasOpcion`); ningún producto local los tiene: solo por sus pruebas. ⚠️ Un
defecto que la sonda cazó al primer toque: la pantalla emitía `otra`/`quitarOtra` y la compra solo escucha `cambiar`.
**Medido**: `otra-zona.test.js` (11), `vista.test.js`, `complementos.test.js` y `falta.test.js` (1.766 de JS); las guardas
(la compra 195,08 → 199,61, a 201; los pasos 55,15 → 56,01, a 57); una sonda desechable a 390 y 1280, 9/9: el enlace que
nombra a JUMP un sábado, su tarjeta (11,20 € por entrada), el resumen con « + », el total = el presupuesto del servidor pedido
aparte (19,20 €), más gente → 30,40 €, «Continuar» con las DOS líneas en la cesta a la misma hora, y «Quitar»; las sondas de la
M1, M2 y M3 y `sonda-conversion` 22/22, sin cambios.

### 4.7 La tarjeta, una entrada completa (K2·b, `#882`, `[DECIDIDO owner]` 02-10 noche)
Visto K2, el owner preguntó si la entrada añadida depende del tiempo o de la hora extra de la del pedido (no: su primera
fila —1 hora— para una persona, sin complementos) y pidió «el diseño profesional, para conversión y máxima claridad».
Propuesto y aprobado («lo hacemos así»):
- **La tarjeta**: su ZONA; para quién (las edades de su fila, del panel); «¿Cuánto tiempo?» con los de su zona y su precio
  de ese día; «¿Cuántos?»; sus complementos —la hora extra de su tiempo— (la regla de la M1, `#880`), NUNCA marcados; y
  «Quitar». Con las piezas de la pantalla (sin diseño nuevo): sus preguntas, h3 bajo su nombre.
- **El tiempo de partida**: el MISMO que el de la entrada del pedido o, si su zona no lo tiene, el más largo sin pasarse
  (una ilimitada no tiene tope); si todos se pasan, el primero. Por `duration_min`, no por el nombre. Es la decisión que
  el cliente ya tomó, con su precio a la vista: no una venta escondida. Si después cambia el del pedido, el suyo NO se
  mueve solo; si al cambiar de día su tiempo no se vende y otro de su zona sí, toma el parecido.
- **Los calcetines**: una sola pregunta para todo el pedido (el mismo producto en las dos zonas).
- **El resumen**: si los grupos salen a horas distintas, cada uno dice su tramo («11:00–13:00», sin partirse al final de
  una línea); una ilimitada no tiene hora de salida. La estancia: la de su fila más lo que la alarga lo ELEGIDO y
  disponible a esa hora.
- **Fuera**: la lista de todas las entradas con su − / + (otro diseño, el del canvas) y las mezclas dentro de una zona
  (la K3, «Añadir otra entrada»).

**K2·b, hecha (02-10 noche; visto bueno del owner el 03-10: «continúa con rigor»; «¿Cuántos venís?» en la tarjeta, como
arriba, a su revisión de textos del final).** Servidor: la ficha de cada complemento dice cuánto ALARGA la
estancia —`stay_minutes` y `stay_per_unit` en `CatalogAddon`, contrato **1.63.0** (la 1.62.0 es del SPA, `#914`)—, con las reglas del dominio
(`AddonOccupancy::sellableOccupant()`, `sellableStayExtension()` y `blocksFor()`, sin copiarlas; `CatalogReader` no está en
el `CRITICAL_RE`). Isla: `otra-zona.js` (`filaParecida`, `otraNueva` con la duración del pedido, `otraDelDia`, `edadesDe`,
`finDe`, la tarjeta y los tramos), `complementos.js::estancia`, `pantalla-cuando.js` (`opcionesDeTiempo`, la misma para la
pantalla 0 y la tarjeta; `rangoHorario`), `linea.js` (la otra línea lleva sus complementos y sus grupos;
`resolverOtrasConOferta`), `usePantallaCero` (su ficha y sus sueltos al cambiar de fila; `recotizar`: lo de la otra no
re-resuelve la del pedido) y `TarjetaOtraZona.vue`. Si solo SU TIEMPO no se vende ese día, lo que falta es su «¿Cuánto
tiempo?» (`pjc-q-otra-tiempo`, M2). **Medido**: `CatalogTest` (la prueba nueva; dos mutaciones muerden), JS 1.784 (cuatro
mutaciones muerden), las guardas (la compra 199,61 → 204,81, techo 206); una sonda desechable a 390 y 1280, sus 16
comprobaciones funcionales en verde —Kids 2 h → Jump 2 h y la ilimitada → 2 h; su hora extra apagada hasta la hora y nunca
marcada; h3; ningún id repetido; los tramos; el total = el presupuesto del servidor pedido aparte (37,60 €); a 1 hora, su
hora extra fuera; la cesta con la hora extra en la línea de Jump; el pedido a ilimitada no la mueve—; la de su consola, roja
solo por las 429 de la propia sonda (66 peticiones en ~40 s, límite 60/min por IP; añadirla cuesta 3 y cambiar su tiempo
con hora, 5). Sin datos locales para «solo su tiempo no se vende» (Jump vende sus dos tiempos los mismos días): solo por
pruebas. M1 12/12, M2 13/13, M3 11/11, `sonda-conversion` 22/22 y `sonda-isla` 26/26 (su comprobación de «Elegir esta
hora», al día con la M2 de `#881`: viva, con lo que falta encima).

## 5. Impacto en invariantes
- `PAY-12` y `PAY-20`: el recibo pinta el presupuesto del servidor y no suma nada.
- `AFORO-01` y `AFORO-02`: el servidor no cambia; la oferta sale de `SlotOffer` por producto y el bloqueo de las dos
  zonas ya existe. Si K1 necesitara tocar `CartLineValidator` u `OrderCreator` (están en el `CRITICAL_RE`), se para, se
  avisa y se corre con `VERIFY_CONC=1`. La K2·b (`#882`) solo AÑADE un dato de lectura a la ficha (`stay_minutes`): el
  aforo no lo lee; si la hora extra cabe lo sigue diciendo el servidor a esa hora.
- RGPD: ninguno nuevo; los menores de las entradas Kids, en «Quién firma el descargo» (`#875`).

## 6. Plan de verificación empírica
- **Pruebas de nodo**: las líneas del pedido y meterlas (orden, contexto, todo o nada, fundir la misma fila); la pantalla
  0 (con y sin otra zona; las horas para todos); el recibo (varias filas, «Quitar», el total del presupuesto);
  `IslaTextosTest` para los textos.
- **El servidor, sin cambios**: una prueba de caracterización de un pedido de dos zonas por la API si `OrderCreatorTest`
  no la tiene ya (se mira en K1).
- **En navegador**: `sonda-isla.mjs` con Jump + Kids desde la pantalla 0 y desde «Pagar» hasta el banco de pruebas, a
  390 y 1280: el pedido con dos líneas en su zona y su franja (BD) y «¡Reservado!» con las dos.
- **Arnés**: el todo o nada, el contexto de la validación y «Quitar», cada uno con su mutante.
- **Pesos**: `SidebarBundleBudgetTest` con la base construida antes.

## 7. Revisión y decisión
- 02-10: borrador del agente, medido. El mismo día, el owner elige D1-A, D2-A y D3-B (D4-A, del mockup): ✅ y `#878`.
  Sigue K1.
- 02-10 noche: vista la K2, el owner aprueba la tarjeta COMPLETA (§4.7, `#882`): la K2·b, antes de la K3.
