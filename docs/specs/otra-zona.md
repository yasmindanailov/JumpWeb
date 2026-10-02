# [SPEC] La otra zona — entradas de varias zonas en UNA compra de la isla

> Estado: ✅ aprobada por el owner (02-10: D1-A, D2-A, D3-B; D4-A por el mockup, `#767`) → a implementar ·
> Última actualización: 2026-10-02 · Decisión asociada: `#878` · Carril: plataforma · Es la L2 de `#876`
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
  la compra 190,66 de 191 y los pasos 55,15 de 56 (`SidebarBundleBudgetTest`, tras la M1 de `#880`, 02-10). (4) La hora
  llena (`#822`) y la vuelta del banco, con dos líneas.
- **Estado**: ✅ aprobada (`#878`), sin código; sigue K1 (§4.6). ➕ `#881`: con ella, los grupos de elección que la isla no
  pinta —uno en una ENTRADA o un segundo en un pack— (`isla-y-landing-nueva.md` §4.29, M1).
- **Invariantes**: `PAY-12`, `PAY-20`, `AFORO-01`, `AFORO-02`, sin tocar el servidor ni el `CRITICAL_RE`; la compra
  entera con la pasarela de pruebas y la BD, sí (§6).

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

### 4.4 La hora llena y la vuelta del banco
- `#822` con varias líneas: las horas cercanas son las que caben para TODAS.
- La vuelta del banco y la compra a medias (`marcarSalida`) guardan el pedido con sus `otras`; «¡Reservado!», la isla
  tras la compra y Mi cuenta dicen las líneas con « + », como el mockup.

### 4.5 Textos y medida
- Clave nueva en es/en/fr para D3-B («¿Alguien va a :zona? Añádelo a la misma reserva»); el resto ya existe.
- `line_added` de la línea añadida con una prop que diga de dónde vino (`otra_zona` | `otra_entrada`): es del contrato
  de eventos, del SPA; se le pide por el buzón antes de K2.

### 4.6 Las tandas
**K1** el modelo y la cesta, sin pantalla nueva (pruebas de nodo) → **K2** la pantalla 0 → **K3** «Pagar» y quitar →
**K4** la hora llena, la vuelta del banco y la compra entera en la sonda. Cada una, al ojo del owner en vivo.
➕ `#881` (`[DECIDIDO owner]`, 02-10): en K1 y K2, los GRUPOS DE ELECCIÓN que la M1 de `#880` no pinta —uno en una entrada o
un segundo en un pack—: la elección de cada grupo en el borrador y en la línea, y su pregunta con `TarjetasOpcion`, como el
menú del pack (`fiesta.js::menusDe`, hoy solo el primer grupo).

## 5. Impacto en invariantes
- `PAY-12` y `PAY-20`: el recibo pinta el presupuesto del servidor y no suma nada.
- `AFORO-01` y `AFORO-02`: el servidor no cambia; la oferta sale de `SlotOffer` por producto y el bloqueo de las dos
  zonas ya existe. Si K1 necesitara tocar `CartLineValidator` u `OrderCreator` (están en el `CRITICAL_RE`), se para, se
  avisa y se corre con `VERIFY_CONC=1`.
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
