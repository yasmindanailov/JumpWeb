# [SPEC] El GUION de la portada — la fase 2 del diseño: qué se cuenta, en qué orden y con qué pieza

> Estado: ⬜ **BORRADOR para el owner** (2026-09-03) — siete decisiones marcadas `[PENDIENTE: owner]`
> (§4.7), tres de ellas se toman **viendo opciones renderizadas sobre la web real** (§6).
> Decisión asociada: `DECISIONES #431` (abre la fase; la aprobación del guion será otra).
> Carril: diseño / idioma visual, sub-banda `#430`–`#439`. Sistema de referencia: `design.md` (raíz)
> y `mockup_playjumppark/design-playjump.md`.
> ▶ Viene de `docs/specs/auditoria-diseno.md`: la auditoría dijo qué está roto; **esto dice qué se
> cuenta**. Son dos cosas, y el owner lo formuló así el 2026-09-02: *«¿con esto conseguimos
> diferenciación real? … el objetivo es que el usuario entienda la propuesta de valor sin
> complicaciones»*. La respuesta medida fue **no**: arreglar hallazgos no cambia lo que la portada dice.

## 1. Contexto y problema — medido

### 1.1 · El visitante trae cuatro preguntas (`[owner, 2026-09-02]`: «básicamente son las cuatro»)

1. **¿Puede saltar mi hijo, y cuánto cuesta el día que voy?** (una madre, móvil)
2. **¿Qué hay dentro?**
3. **Quiero un cumpleaños para N niños.**
4. **Quiero dos entradas ahora.** (jóvenes: «otro fitness»)

### 1.2 · Lo que hoy tarda la portada en contestar (sonda `audit-camino.mjs`, 2026-09-02)

| pregunta | dónde está la respuesta | 390 | 1280 |
|---|---|---|---|
| ¿cuánto cuesta? | el primer precio (`12,00 €`) | pantalla **2** | 2,3 |
| ¿puede saltar mi hijo? | «A partir de 8 años y 1,30 m» — **una viñeta dentro de la tarjeta de precio**, no una respuesta | 2,1 | 2,5 |
| ¿cumpleaños? | la banda empieza en la 3,2 y **su botón está en la 5,3**: entre medias, un pack con diez complementos y un menú | 3,2 → 5,3 | 3 → 4,6 |
| ¿qué es este sitio? (zonas y juegos) | pantalla **6,3** | 6,3 | 5,7 |
| dudas | pantalla 10 | 10,2 | 9,8 |

La portada mide **12,9 pantallas** en un teléfono (10.861 px) y **12,5** en un portátil. Lo primero
que pide en escritorio es «Registrarse» — antes de haber dicho para qué.

### 1.3 · La misma decisión, tres formas — y doce especies de cosa pulsable

«Elige zona» se pregunta tres veces con tres componentes distintos: `.zone-tab` (tarifas, y
`/servicios`), `.bd-tab` (cumpleaños) y `.zone-pick__tab` (juegos). Además de la familia `.btn` (30
usos), la portada emite ~12 clases pulsables más (`salta__btn`, `reserve__act`, `ride-card__cta`,
`cta-ghost`, `cta-med`, `bd-proc__arrow`, `slider-arrow`, `faq__q`, `rules-must__card`,
`rules-peek__item`, `bd-swatch`, `nav__burger`…).

### 1.4 · Tres de las doce URL son un trozo de la portada con título

`/entradas` = la portada con el cajón abierto · `/precios` = `nav` + `ticket-prices` + `footer` ·
`/cumpleanos` = `nav` + `events-section` + `footer`. La portada ya pinta esas dos secciones enteras
—incluido el paso a paso de cinco y el editor de invitación—, así que las interiores **no añaden
nada** (auditoría M3).

### 1.5 · La complejidad es real, y el owner la enumeró

Registro obligatorio para todos · descargo de responsabilidad · **los menores los firma el padre y los
asigna en su cuenta** · dos zonas por **edad y altura** · tarifa de lunes a jueves y otra de viernes,
fin de semana, vísperas y festivos · productos por duración (1 h, 2 h; y la hora extra que diseña el
otro carril, `specs/hora-extra.md`) · normas · complementos (calcetines obligatorios) · el cumpleaños
tiene un **post-formulario** tras reservar que pide los nombres de los niños. *«No es fácil explicar
todo.»*

### 1.6 · Lo que dice el material del cliente sobre el orden (para no inventarlo)

Su diseñador propuso dos órdenes (`design-playjump.md` §9): el stack 01–08 de `Colores de Marca`
(**zonas → precios → cómo funciona · normas** → identidad local → cierre) y la landing montada
(entradas → zonas → cumpleaños → **antes de venir** → info → opiniones → cierre). Las dos tienen
**«cómo funciona / antes de venir» como sección propia**, que hoy no existe. ⚠️ `[owner]`: de esa
landing solo se toma como estructura la sección de reseñas; lo demás es sistema visual. Y su turno 4
de precios argumentó **«el día se elige al reservar, no al mirar»**, con el precio de finde impreso
para que «desde» no sea cebo (§10 del perfil).

### 1.7 · Lo que NO es el problema

El sistema visual (Bungee, pegatina, la tira, el juego del cierre): es distinto y está decidido. El
owner (2026-09-02): *«prefiero más que wow, que sea limpio y entendible; el juego del hero es el wow
que nos hace falta y ya está, si no saturamos»*.

## 2. Objetivo

**Que cada una de las cuatro preguntas tenga su respuesta en la primera o segunda pantalla del
teléfono, con la pieza que la contesta, y que el visitante llegue a comprar sin que le hayamos
explicado lo que no necesitaba aún.** Criterios medibles, con la misma sonda antes y después:

| criterio | hoy (390) | objetivo |
|---|---|---|
| ¿puede saltar mi hijo? (edad **y altura**, como respuesta y no como viñeta) | 2,1 | **≤ 1,5** |
| ¿cuánto cuesta el día que voy? | 2 (+ una suma) | **≤ 2, sin sumar** |
| ¿qué hay dentro? | 6,3 | **≤ 3** |
| ¿cumpleaños? → su botón | 5,3 | **≤ 3** |
| pantallas totales de la portada | 12,9 | **≤ 8** |
| componentes que preguntan «¿qué zona?» | 3 | **1** |
| especies pulsables además de `.btn` | ~12 | **≤ 5** |
| URLs que son un trozo de otra | 3 | **0** |
| y **cinco personas** con un móvil y las cuatro preguntas (§6.3) | — | 4 de 5 contestan cada una sin ayuda en < 30 s |

**Fuera de alcance**: el cajón SPA (aparcado) — las reglas que muerden dentro del embudo se **anotan**
como dependencia, no se tocan · el modelo de datos, salvo lo de §4.6 · el panel · el sistema visual y
el juego del cierre · la app.

## 3. Opciones consideradas

### 3.1 · La forma de la portada (macroestructura, `design.md` §2) — tres de categorías distintas

| | forma | qué sería aquí | a favor | en contra |
|---|---|---|---|---|
| **A** | **Conversational FAQ** («preguntas en grande, respuestas breves») | la portada **son las cuatro preguntas**, cada una con su respuesta-pieza (la marca de altura, la semana de precios, el plano, los dos packs) | es literalmente el objetivo; el titular ya es la pregunta de la madre; casa con «una palabra» solo si la pregunta cabe | cuatro bloques altos; el riesgo es que parezca una FAQ y no un parque |
| **B** | **Split Studio** (díptico alternado) | texto a un lado, la pieza al otro, alternando: Kids/Jump · L–J/finde · pack Kids/Jump | la marca es «dos»; la comparación es nativa; en móvil apila bien | menos jerarquía entre preguntas; puede volverse una lista de pares |
| **C** | **Map / Diagram** (un diagrama organiza la página) | **el plano del parque** ordena «qué hay dentro»: zonas donde están, juegos donde están; las demás preguntas cuelgan del plano | es de este sujeto y de ningún otro; contesta «¿qué es esto?» en la primera pantalla | necesita un plano (dato/arte del cliente); su diseñador ya lo exploró (`1a El plano`) |
| — | la actual (hero a pantalla completa + lista de secciones en tarjetas) | línea base | ya medida | es la forma por defecto de una IA, elegida por nadie |

**Descartadas sin renderizar**: Bento (rejilla de bloques: convierte cuatro preguntas en ocho
tarjetas), Stat-Led (la portada no es un número), Long Document (la portada no se lee, se recorre).

### 3.2 · Las páginas interiores

**a)** la **versión LARGA** de su sección: `/precios` con todas las tarifas, temporadas, complementos
y el «cómo funciona el precio»; `/cumpleanos` con el proceso completo, el editor de invitación y el
post-formulario explicado — y la portada se queda con el **resumen** de cada una · **b)** retirarlas
y anclar (`/#precios`) · **c)** dejarlas como están (descartada: es M3).
⚠️ `/entradas` es otra cosa: la portada **con el cajón abierto**, que puede ser deliberado (una URL
para «comprar»); se decide aparte.

### 3.3 · El selector de zona (uno solo, en los tres sitios)

**a) La marca de altura**: la regla física de la entrada del parque —la barra de «¿llegas aquí?»—
como escala vertical con la línea de **1,30 m**; debajo Kids (4–7), encima Jump (8+). La regla
dibujada; la madre se autoselecciona y sabe por qué. Necesita §4.6.
**b) Dos tarjetas con dato**: las dos zonas a la vez, cada una con edad, altura, precio desde y qué
hay dentro; el clic es «reservar», no «ver». Es lo que hay ahora pero **con contenido** (la queja del
owner: «dos tarjetas con etiqueta es soso, no dice nada»).
**c) Pestañas**: lo actual, unificado en un componente. Descartada como forma final —esconde una
zona— pero es el suelo si a) y b) no caben en alguna sección.

### 3.4 · El precio por tipo de día

**a) La semana como tira**: `L M X J | V S D · vísperas y festivos` con su precio bajo cada tramo — la
regla dibujada; el dato existe (tarifas marcadas `is_special` + fechas especiales) · **b) dos precios
llanos** en la tarjeta («Lunes a jueves 12 € · Viernes, findes y festivos 14 €») · **c) «desde» + el
calendario decide**, con el precio de finde impreso pequeño (el turno 4 del diseñador del cliente, con
su aviso: *«“desde” sin más es cebo»*) · descartada: **d)** lo actual (`12,00 €` + etiqueta «+2,00 €
viernes, findes y festivos» — obliga a sumar).

### 3.5 · «Cómo funciona una visita»

**a)** una **sola** sección para toda la web —compra → regístrate y firma en dos minutos desde el
móvil → los menores a tu cuenta, firmas por ellos → tu QR en la puerta; en cumpleaños: *después,
los nombres*—, en la portada y enlazada desde el paso de pagar · **b)** repartida por secciones
(descartada: es lo que hay, y por eso el primer botón es «Registrarse» sin contexto).

## 4. Diseño elegido — el guion (propuesta; las decisiones, en §4.7)

### 4.1 · El principio: cada regla, donde muerde

| lo que hay que explicar | qué decide | dónde muerde | cómo se cuenta |
|---|---|---|---|
| Dos zonas por edad **y altura** | la primera decisión: ¿para quién? | al entrar | **un selector** que filtra el resto (§3.3) |
| Tarifa por tipo de día | cuánto me cuesta **el día que voy** | al mirar el precio | §3.4; nunca una suma |
| 1 h / 2 h (+ hora extra) | cuánto tiempo | después de zona y precio | como **cantidad de tiempo**, no lista de productos — la dirección de `specs/hora-extra.md` |
| Registro + descargo | nada de la compra: es **cómo funciona** | una vez como proceso; **al pagar** de verdad | §3.5. Hoy el primer botón es «Registrarse» antes de decir para qué |
| Menores: los firma el padre y los asigna a su cuenta | nada de la compra | en «cómo funciona» (promesa) y en la cuenta (hecho) | *«si vienen menores, los añades a tu cuenta y firmas por ellos: un minuto»* — el mecanismo existe (`specs/menores-a-cargo.md`, `specs/waiver-por-reserva.md`) |
| Post-formulario del cumpleaños | nada de la compra | tras reservar (existe: `docs/sistemas/POSTFORM-INVITADOS.md`) | *«después de reservar te pedimos los nombres de los niños»* — una frase en el pack, no diez complementos antes del botón |
| Calcetines obligatorios (+2 €) | un coste que no esperaba | junto al precio | una línea honesta al lado del precio |
| Las demás normas (conducta, tutor…) | casi nada antes de comprar | en la puerta y en `/normas` | la portada enseña **solo las que cambian una decisión** (altura, registro); el resto en su página |
| Complementos, menús, señal | qué añado | **al comprar**, en el cajón | fuera del discurso de la portada |

Con esto, el recorrido de una madre cabe en **dos pantallas**: *¿para quién?* → precio de su día y
cuánto tiempo → *cómo funciona* → comprar.

### 4.2 · El inventario de páginas (D-G1)

| URL | para qué | qué contiene |
|---|---|---|
| `/` | contestar las cuatro preguntas y llevar a comprar | el guion de §4.3 |
| `/precios` | la versión larga de «¿cuánto?» **o** ancla (D-G2) | todas las tarifas y temporadas, la semana, complementos, «cómo funciona el precio», bonos si los hay |
| `/cumpleanos` | la versión larga de «un cumple» **o** ancla | los dos packs comparados, el proceso, la señal, el post-formulario explicado, la invitación |
| `/normas` | referencia | Long Document: las normas del panel, sin icono repetido (auditoría M6) |
| `/contacto` | llegar y preguntar | horario, mapa (sin tarjeta encima: M4), formulario |
| `/entradas` | comprar | la portada con el cajón; **decidir si sobrevive como URL** |
| legales, `/waiver` | leer una vez | como están |
| `/servicios` | — | fuera del sitemap hasta que exista (C1) |

### 4.3 · El orden de la portada por preguntas (D-G3, reabre `#314`)

Propuesta, para renderizar en las tres formas de §3.1:

1. **Hero** (como está: vídeo, «DIVERSIÓN ON», el par Reservar/Registrarse del armazón que nace bajo él).
2. **¿Para quién?** — el selector de zona con edad y altura (§3.3). Contesta la pregunta 1 y filtra 3 y 4.
3. **¿Cuánto, el día que voy?** — el precio por tipo de día (§3.4), la duración como cantidad, los calcetines al lado. Contesta la 1 y sirve a la 4.
4. **¿Qué hay dentro?** — las atracciones de la zona elegida (el carrusel de hoy, o el plano en C). Contesta la 2.
5. **Cumpleaños** — los dos packs **lado a lado** con lo que incluye cada uno, «desde», la señal, y la frase del post-formulario; el proceso y la invitación en `/cumpleanos`. Contesta la 3.
6. **Cómo funciona una visita** — la única secuencia numerada de la web (§3.5).
7. **Visítanos** — como está (`#307`): horario en vivo, dirección, teléfono.
8. **Dudas** — la FAQ (data del panel), sin el cian.
9. **Cierre** — el juego. El único momento de sorpresa.

Lo que sale de la portada: las cuatro normas en pegatina + adelanto (van a «cómo funciona» las dos
que deciden, y a `/normas` el resto); el paso a paso de cinco y el editor de invitación (a
`/cumpleanos`); los complementos del pack (al cajón).

### 4.4 · La pieza de cada respuesta (`design.md` §1 y §9: el control es el contenido)

- **Zona**: §3.3 (D-G4). Un componente, tres sitios.
- **Precio**: §3.4 (D-G5).
- **Packs**: dos columnas comparadas, filas por lo que incluye; el clic es reservar cada uno.
- **Cómo funciona**: cuatro pasos numerados (aquí la secuencia es real), cada uno con su verbo y su
  tiempo (*«dos minutos»*), y el quinto solo si hay cumpleaños.
- **Titulares**: siguen la regla de `#303` (una palabra) **o** son la pregunta (forma A): se decide con
  la forma.

### 4.5 · Lo que NO cambia

El armazón y el pie (`#200`→`#221`), el sistema visual, el hero y su imán (`#252`), la tarjeta pegatina,
el botón, el juego, la tira. El cajón, aparcado: las reglas que muerden dentro (complementos,
identificación, condiciones) se listan como **dependencias** en §5.

### 4.6 · Datos que hacen falta (son del owner y del panel)

- **`zones.min_age` · `zones.min_height_cm`** (futuro): hoy la edad y la altura son **texto libre**
  (`zones.age_range` = «+6 años · 1,30 m», y dice 6 donde el owner fijó 8). Sin dato estructurado, la
  marca de altura (§3.3·a) y el filtro por edad no pueden ser data-driven. `[PENDIENTE: owner]` D-G6.
- El tipo de día ya es dato (`is_special` + fechas especiales); la duración es `ticket_types.duration_min`;
  la FAQ, las normas y las atracciones son del panel (`Faq`, `VenueRule`, `Attraction`, con `age` en
  19 de 23).
- El texto de los pasos de «cómo funciona» es copy del producto (i18n), con los datos de la instalación
  interpolados (teléfono, tiempo).

### 4.7 · Decisiones — `[PENDIENTE: owner]`

| id | decisión | opciones | cómo se decide |
|---|---|---|---|
| **D-G1** | inventario de páginas (§4.2) | versión larga · ancla · `/entradas` sobrevive o no | leyendo §4.2 |
| **D-G2** | la **forma de la portada** (§3.1) | A Conversational FAQ · B Split Studio · C Map | **tres renders sobre la web real**, 1440 y 390 |
| **D-G3** | el **orden** (§4.3), que reabre `#314` | el propuesto · otro | con los renders y la tabla de pantallas |
| **D-G4** | el **selector de zona** (§3.3) | marca de altura · dos tarjetas con dato · pestañas | renderizado en los tres sitios |
| **D-G5** | el **precio por día** (§3.4) | semana · dos precios · «desde» + calendario | renderizado en la tarjeta |
| **D-G6** | los **dos campos** de zona (§4.6) | sí (migración + panel) · no (texto) | leyendo §4.6 |
| **D-G7** | qué **normas** entran en la portada | solo las que deciden · más | leyendo §4.1 |

## 5. Impacto en invariantes

- `PAY-*`, `AFORO-*`, `RGPD-*`, `SEC-*`: **ninguno** — es presentación de datos que ya existen; nada
  cambia lo que se cobra, se reserva o se guarda.
- `PERF-02` (bytes): el kit de fachada y un posible plano entran con presupuesto (`design.md` §10);
  el HTML de `GET /` ya lleva el logotipo al 42 % (`#275`).
- **Decisiones que se reabren**: `#314` (el orden de secciones) y, si D-G4 no es «dos tarjetas», la
  estructura de `#301` («2 cards y debajo la sección de juegos»). Se reabren **con medida delante**,
  no por gusto — y las decide el owner.
- **Dependencias hacia el cajón** (aparcado, se anotan): el precio del día y la duración los resuelve
  el paso de fecha; los complementos y el menú, el paso de complementos; la identificación y las
  condiciones, el paso 5 (`#349`). Lo que la portada **promete** en «cómo funciona» tiene que ser lo
  que el cajón **pide**: hay que leer los dos juntos al redactar los pasos.
- **Otro carril**: `specs/hora-extra.md` decide cómo se presenta el tiempo (una cantidad, no
  productos). El paso 3 de §4.3 tiene que casar con ello; se coordina por `ESTADO.md`.

## 6. Plan de verificación empírica

### 6.1 · Antes y después, con el mismo instrumento
`storage/app/audit-camino.mjs` (gitignorado; la tabla de §1.2 es su salida): pantalla de cada
respuesta a 390 y 1280, pantallas totales, CTAs del hero, alto de cada sección. Se corre sobre cada
render de D-G2 **y** sobre la implementación final. Los objetivos de §2 son el criterio.

### 6.2 · Guardas que nacen con la implementación
- Un **solo componente** de selector de zona (`ZoneSelectorIsOneComponentTest`, futuro): las tres
  clases de hoy no vuelven a aparecer en Blade.
- El **orden de secciones** de la portada, aseverado por elemento (re-apunte de la guarda de `#314`).
- Ninguna etiqueta de sección salvo la secuencia de «cómo funciona» (`SectionHeadlineTest`).
- Los cuatro objetivos de §2 que son estáticos (especies pulsables, URLs duplicadas) como tests.
- Las guardas pendientes de `design.md` §13 que toquen lo que cambie aquí.

### 6.3 · Cinco personas, cuatro preguntas, un móvil
Protocolo: cinco personas del público (madres, un joven), su móvil, la portada nueva. Se les hace
cada pregunta en voz alta («¿puede saltar tu hijo de 6 años, y cuánto os costaría el sábado?») y se
mide si la contestan **sin ayuda en menos de 30 s** y dónde han mirado. 4 de 5 por pregunta es el
listón. Es la única verificación que convierte «entendible» en un dato; el owner las trae.

### 6.5 · El protocolo de la 2c — los renders (2026-09-03)

Las tres formas se montan **sobre la web y los datos reales**, no como maqueta, en rutas que **solo
existen en local** (`routes/prototipos.php`, cargado desde `routes/web.php` bajo
`app()->environment('local')`; en testing y producción no existen):

| URL | qué |
|---|---|
| `/_diseno/portada/a` | Conversational FAQ |
| `/_diseno/portada/b` | Split Studio |
| `/_diseno/portada/c` | Map / Diagram |
| `/_diseno/piezas` | las tres variantes del selector de zona (D-G4) y del precio (D-G5), y los packs y «cómo funciona» |

Parámetros en las tres portadas: `?zona=altura|tarjetas|pestanas` · `?precio=semana|dos|desde`.

**Qué es real y qué es provisional**: zonas, atracciones, entradas, precios por tarifa, packs, normas,
FAQ, horario, armazón, hero y cierre son **los del producto** (el controlador
`Prototipos\PortadaController` pide sus datos a `HomeController` y no duplica consultas). **Provisional
y marcado en pantalla**: la edad y la altura de cada zona se **parsean del texto** `zones.age_range`
(D-G6), y los textos de «cómo funciona» y de las preguntas son copia del prototipo, sin i18n. La hoja
`public/prototipos/guion.css` vive **fuera** de `public/css/` (las guardas del sistema barren esa
carpeta) y aun así **solo usa tokens**. Los prototipos se **retiran con la decisión D-G2**.

**Cómo se comparan**: la sonda `storage/app/audit-guion.mjs` mide las tres formas con el criterio de
§2 (pantalla de cada respuesta a 390 y 1280, pantallas totales, desbordamiento, dos líneas por
`Range`, contraste efectivo) y captura a 1440 y 390. Los números van en la hoja de decisión junto a
las capturas. `?orden=cumple` (en A y B) adelanta el cumpleaños a antes de los juegos, para **medir**
D-G3 en vez de estimarla.

**La hoja de decisión** (presentación; el registro es este fichero): «Tres portadas» —
https://claude.ai/code/artifact/0ef89d7e-ab11-4776-8650-daef4e3a9253 — con las capturas a 1440 y 390
de la actual y de las tres formas, la tabla de §6.6 y las decisiones D-G2..D-G7.

### 6.6 · Lo que los renders MIDIERON (2026-09-03, sonda `audit-guion.mjs`, Chromium)

Pantalla en la que aparece la respuesta, a **390 px** (portátil 1440 entre paréntesis):

| | actual | **A** FAQ | **B** díptico | **C** esquema | A · cumple antes | B · cumple antes | objetivo §2 |
|---|---|---|---|---|---|---|---|
| ¿puede saltar mi hijo? (edad **y** altura) | 2,1 (2,3) | **1,6** (1,8) | **1,6** (1,8) | **1,6** (1,8) | 1,6 | 1,6 | ≤ 1,5 |
| ¿cuánto, el día que voy? | 2 (2,1) + una suma | 2,6 (2,6) sin sumar | 2,7 (2,3) | 2,5 (3,3) | 2,6 | 2,7 | ≤ 2, sin sumar |
| ¿qué hay dentro? | **6,9** (5,9) | **3,2** (3,3) | 3,3 (3) | 3,2 (4) | 5 | 5,1 | ≤ 3 |
| ¿cumpleaños? (su botón) | 5,3 (4,2) | 4,9 (5,1) | 5,1 (4,9) | 4,9 (5,9) | **3,9** (3,8) | **4** (3,6) | ≤ 3 |
| cómo funciona una visita | 8,8 (8) | 6 (5,7) | 6,3 (5,3) | 6 (6,4) | 6 | 6,3 | — |
| pantallas totales | **12,9** (11,5) | 10,7 (9,7) | 11 (9,5) | 10,7 (10,4) | 10,7 | 11 | ≤ 8 |
| selectores de zona | **3** | **1** | **1** | **1** | 1 | 1 | 1 |
| desbordamiento · pulsables a dos líneas | 0 · 0 | 0 · 0 | 0 · 0 | 0 · 0 | 0 · 0 | 0 · 0 | 0 · 0 |
| fallos de contraste (de las piezas NUEVAS) | 3–4 (C2, C3) | **0** | **0** | **0** | 0 | 0 | 0 |

**Lo que enseñan los números, dicho antes de que nadie elija**:
1. **Lo que las tres formas ganan por igual** viene del GUION, no de la forma: un solo selector,
   edad y altura en la primera pantalla tras el hero, «qué hay dentro» de la 6,9 a la 3,2, cero
   contrastes nuevos por debajo de AA. La forma cambia la voz y el ritmo, no estas cifras.
2. **El precio se retrasa media pantalla** (2 → 2,5–2,7) porque la zona va primero. Es el coste de
   contestar «¿para quién?» antes que «¿cuánto?», y es el orden que el guion propone (D-G3).
3. **El cumpleaños no llega a la 3 en ninguna**: con el orden propuesto queda en 4,9–5,1; adelantado
   a antes de los juegos, en **3,9–4,0**. Para bajar más hay que **acortar** lo que va delante (el
   selector + los precios miden ~2,4 pantallas a 390) o subirlo a la segunda posición. Es D-G3.
4. **El largo total no baja de 10,7 pantallas** con las seis respuestas + hero + visita + dudas +
   cierre. El hero mide **1,36 pantallas** a 390 (el recorrido del imán, `#252`), el cierre 0,6 más su
   pista, y cada sección lleva 160 px de aire (`#314`). Llegar a ≤ 8 exige **quitar o comprimir**
   (visita y dudas son 2,2 pantallas juntas; los juegos 1,7): es una decisión de contenido, no de
   forma, y va a D-G3/D-G7.
5. **C pierde en escritorio y se rompe en móvil**: a 1280 el esquema empuja precio y juegos ~0,9
   pantallas más abajo que A y B, y a 390 el SVG escala hasta dejar los nombres de las atracciones
   ilegibles (~4 px). Si se eligiera, en móvil tendría que **colapsar a lista**, que es volver a A/B.
6. **A rompe a sabiendas `#303`** (titulares a una palabra en Bungee): sus títulos son la pregunta,
   en Hanken 800. Es la voz del visitante en vez de la del rótulo; el owner decide si ese cambio de
   voz compensa. **B es la que más suena a PlayJump** (Bungee a una palabra + díptico) y la
   comparación —dos zonas, dos precios, dos packs— le es nativa.
7. **Dos líneas: 0 en las tres**, medido con el detector corregido (solo nodos de texto; los
   controles de dos filas por diseño, excluidos). El primer pase daba 21 falsos por contar un icono
   `<svg>` en línea como segunda línea — cuarta trampa del instrumento en este carril.

### 6.4 · Lo que la skill mide al final
`hallmark` · slop test sobre la portada nueva, con las excepciones declaradas de la auditoría §6.

## 7. Revisión y decisión

- 2026-09-03 · borrador del agente a partir de la auditoría (`#430`), las cuatro preguntas del owner,
  el material del cliente (`design-playjump.md` §9–§10) y la medición de §1.2.
- Pendiente: revisión adversarial (¿qué pregunta del visitante falta? ¿qué regla muerde en un sitio
  que no he visto?), las siete decisiones de §4.7, y la entrada de aprobación en `DECISIONES.md`.
- **Siguiente paso concreto**: la fase 2c — los tres renders de D-G2 con las piezas de D-G4/D-G5
  dentro, sobre la web real, para que el owner elija viendo.
