# [SPEC] El GUION de la portada — la fase 2 del diseño: qué se cuenta, en qué orden y con qué pieza

> 📜 **ARCHIVADO el 2026-09-03 (`DECISIONES #452`, `[DECIDIDO owner]`)**: el diseño se delega a
> **Claude Design**; la fase 2 se para aquí. Los prototipos de `/_diseno/…` y `scripts/prototipo-b/`
> se retiraron del árbol y el artefacto B queda solo como referencia. **No se mantiene**: sus medidas
> (línea base de la portada, las tres formas, B a cinco anchos) siguen valiendo como dato cuando el
> owner vuelva con su sistema; sus decisiones D-G1..7 se retoman desde cero sobre esa base.

> Estado (al archivar): 🟦 **LA 2c MEDIDA Y B EN ARTEFACTO** (2026-09-03, `#431` → `#433`) — la organización
> aprobada y A descartada por el owner (§6.7); **B, móvil primero y con datos reales, publicada como
> artefacto para su OJO (§6.8)**. Quedan D-G3 · D-G6 · D-G7 y su ✅ antes de tocar la portada real.
> ▶ **§6.9: el owner la vio en su móvil (2026-09-03, `#438`) — le gusta, y pide más juego, el cumpleaños
> en slide, otra «Visita», otro «Visítanos» y las reseñas de Google; la barra flotante, medida.**
> Las siete decisiones de §4.7 nacieron `[PENDIENTE: owner]` y se toman **viendo opciones renderizadas
> sobre la web real** (§6).
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
existen en local** (el fichero de rutas `prototipos.php` —retirado en `#452`—, cargado desde `routes/web.php` bajo
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

### 6.7 · Lo que el owner dijo al ver los renders (2026-09-03) — decisiones y dos correcciones

`[owner, 2026-09-03]`, textual: *«Me gusta la idea de la organización, la landing tipo FAQ no me
convence, pero para mí es muy importante que la UI/UX en el móvil esté perfecta, y el texto se rompe
en el móvil por ejemplo con las zonas; la idea de la medida, los metros en las cards de zonas, me
gusta. Lo de los precios también me gusta, pero en el móvil lo quiero perfecto, y valoro hacerlo todo
lo posible tipo slide, en vez de deslizar que hagan slide lateral en ciertas secciones. Y parece que
me faltan elementos tipo iconos o imágenes, es demasiado solo texto, y no hay algo de "juego", un
sticker o algo.»*

- **D-G2 · `[DECIDIDO owner]`: la forma A (Conversational FAQ) queda DESCARTADA.** La organización
  (el guion) se aprueba. Queda **B** (díptico) como forma de trabajo — C se rompe en móvil, que es la
  prioridad declarada — pendiente de que el owner lo confirme al ver la B **en móvil, terminada**.
- **D-G4 · orientación**: las **tarjetas de zona con la medida en metros** (la marca de altura dentro
  de la tarjeta, no como pieza aparte). Se renderiza así en la siguiente pasada.
- **D-G5 · orientación**: el precio como está (dos precios llanos / la semana), pero **perfecto en
  móvil**.
- **REQUISITO NUEVO, y manda sobre todo lo demás: el MÓVIL primero y perfecto.** Cada pieza se
  diseña a 390 antes que a 1440, y la aceptación es a 320 · 360 · 375 · 390 · 414.
- **REQUISITO NUEVO: «slide» lateral donde lo que hay es una lista.** Precedente del propio cliente:
  la pasada de móvil de `Landing PJP Modos` (28-08) usa **carruseles con `scroll-snap`** y el panel
  de reserva como hoja inferior (`mockup_playjumppark/README.md`). Regla de diseño: **una elección
  entre dos nunca va en slide** (las dos zonas se ven a la vez); una lista de tres o más sí, con el
  siguiente elemento **asomando cortado por el borde** (la pista de `#239` §5.2), nunca con puntos.
- **REQUISITO NUEVO: la capa de imagen, iconos y «juego».** Los renders son tipografía a propósito
  (primero la estructura, después el vestido — el orden de la skill): el material existe y entra en
  la siguiente pasada dentro del presupuesto del propio cliente (`design-playjump.md` §8–§9: una
  mancha grande por pantalla, un sello de precio por página, nunca pintura bajo un párrafo, ninguna
  pieza en bucle) — el icono de producto en cada precio (`ticket_types.icon`, que la tarjeta actual
  ya pinta y el render perdió), la foto de zona en su tarjeta, el dibujo de zona como identidad, un
  friso o una pose por sección, el sello girado en el precio, los estados vacíos con enjambre.
- ⚠️⚠️ **CORRECCIÓN AL INSTRUMENTO: el detector de «dos líneas» excluía `.pg-zbtn` por ser un
  control de dos filas por diseño, y esa exclusión escondió justo lo que el owner vio** — la línea de
  datos de la tarjeta de zona («4–7 AÑOS · SIN ALTURA MÍNIMA · DESDE 8 €», mono, mayúsculas, `.1em`)
  **se parte en dos y tres líneas a 390**. *Un control puede tener dos filas por diseño y aun así una
  fila que no debe partirse.* La sonda pasa a medir **cada línea de dato por separado**. Salidas de
  diseño, para renderizar: menos datos por línea (edad y altura en una, el precio en otra) · sin
  mayúsculas ni tracking en la línea de dato (el `.16em` en mayúsculas es ~1,5× más ancho que el
  cuerpo) · o dos filas deliberadas.
- ⚠️ **CORRECCIÓN AL ALCANCE DE LA MEDIDA**: el objetivo de ≤ 8 pantallas a 390 se acerca por los
  slides, no por la forma — estimado sobre las alturas medidas (packs apilados 1,6 → ~0,9 · visita
  1,0 → ~0,6 · cómo funciona 1,1 → ~0,6 · precios 0,9 → ~0,7): **~8,7 pantallas**. Es una estimación
  y se mide al construirlo.

### 6.8 · B EN ARTEFACTO — la prueba con las decisiones de §6.7 tomadas (2026-09-03, `#433`)

**Qué es**: la portada en la forma B, **diseñada a 390 y aceptada a 320 · 360 · 375 · 390 · 414** (y
1280 de control), como HTML autocontenido con los DATOS REALES de la instalación —zonas, entradas y sus
dos precios, packs, FAQ, horario, dirección, volcados por el mismo `HomeController` que pinta la
portada—, las fotos reales recortadas y comprimidas, el kit de fachada y el logotipo del cliente en
línea, y el CSS del sistema (`design.md`) con los valores del paquete. **No toca la portada real.**
- Artefacto: https://claude.ai/code/artifact/56f75f11-fb65-4e81-96d2-e9fd8fdc39c2 — presentación; **el
  registro es este fichero**.
- Fuente e instrumentos, en el repo: `scripts/prototipo-b/index.src.html` (el fuente, con marcadores
  `{{img:…}}` y `{{svg:…}}`) · `build.php` (GD: recorte 4:5 a 480×600, webp q58–66; deja la copia servida
  en `storage/app/public/prototipo-b/index.html` → `http://localhost:8081/storage/prototipo-b/index.html`)
  · `medir.mjs` (la sonda, desde `/root/e2e` del contenedor) · `dump-portada.php` (el volcado de datos).

**Lo que lleva, y de dónde sale cada decisión**:

| pieza | forma | decisión |
|---|---|---|
| Zonas | DOS tarjetas pegatina a la vez: foto 4:5 + dibujo de zona del kit + nombre + edad + **la medida en metros como regla dentro** + «desde X €» + juegos y m², **cada dato en SU línea**, sin mayúsculas ni tracking; la elegida se pinta del color de zona | §6.7 D-G4 · «una elección entre dos nunca va en slide» |
| Precios | la semana como regla (V·S·D·fest en tinta) + un BILLETE por duración con el icono de producto, precio L–J grande y el de V·S·D·fest debajo; Jump (2) a dos columnas, Kids (3) en slide con el siguiente asomando; calcetines y **UN** CTA de acción por panel; el **sello girado** «desde X €» (amarillo, pegatina, −7°) | D-G5 · `#432` (2) y (3) · `design.md` §9 |
| Juegos | el carril de atracciones de la zona elegida: foto 4:5, edad en punteada sobre la foto, distintivo en tinta | `#302` |
| Cumpleaños | foto pegatina con la pose de cumpleaños en contorno + los dos packs **lado a lado con las filas alineadas** (`subgrid`: edad · por niño L–J · V·S·D·fest · invitados · dura · señal · incluye · CTA fantasma) + la promesa del post-formulario | `design.md` §9 «dos opciones se comparan» |
| Visita | los cuatro pasos como tarjetas de APOYO en slide (2×2 en escritorio), con las dos manchas de normas del kit | §3.5 · `#432` (2) |
| Visítanos | tres pegatinas en slide (horario · dónde · mapa sin iframe: el anfitrión del artefacto no admite marcos externos) | `#307` |
| Dudas | `<details>` nativo, sin tope de alto, la interacción en TINTA | auditoría M8 · D1 por defecto |
| Cierre | la tarjeta de tinta con VAMOS / A / SALTAR, **sin el minijuego** (dicho en pantalla) | `#252`, no se toca |
| Armazón | logotipo + hamburguesa arriba; **el par de CTA en la barra de abajo** en el rol de acción, como la web real a 390 (medido: arriba el par mide 0×0 y abajo 56) | `#205` · `#217` |
| Selector | UNO (las dos tarjetas); los chips de Precios y Juegos son el MISMO estado | D-G4 «un componente, tres sitios» |
| D-G3 | el sello del prototipo lleva un conmutador «cumple antes de juegos» que reordena las secciones | para verlo, no estimarlo |

**Lo que MIDIÓ** (`medir.mjs`, Chromium 151, las cuatro familias cargadas antes de medir; pantalla en la
que aparece cada respuesta; «rotas» = líneas de dato partidas, medidas una a una por `Range`):

| ancho | pant. | edad | precio | juegos | cumple (botón) | cumple ANTES (packs · botón) | funciona | visítanos | dudas | rotas · 2ln · tap<44 · contraste |
|---|---|---|---|---|---|---|---|---|---|---|
| 320×568 | 12,4 | 1,9 | 3,4 | 4,4 | 6,8 | 4,9 · 5,6 | 7,7 | 8,9 | 9,7 | 0 · 0 · 0 · 0/182 |
| 360×740 | 9,5 | 1,6 | 2,7 | 3,5 | 5,3 | 3,9 · 4,4 | 6,0 | 6,8 | 7,5 | 0 · 0 · 0 · 0/182 |
| 375×812 | 8,8 | 1,6 | 2,5 | 3,2 | 4,9 | 3,6 · 4,1 | 5,6 | 6,3 | 6,9 | 0 · 0 · 0 · 0/182 |
| **390×844** | **8,5** | **1,6** | **2,5** | **3,2** | **4,8** | **3,5 · 4,0** | 5,4 | 6,1 | 6,7 | 0 · 0 · 0 · 0/182 |
| 414×896 | 8,1 | 1,5 | 2,3 | 3,0 | 4,6 | 3,4 · 3,8 | 5,2 | 5,8 | 6,4 | 0 · 0 · 0 · 0/182 |
| 1280×800 | 9,9 | 1,7 | 2,6 | 3,4 | 5,6 | 3,7 · 4,2 | 6,1 | 7,2 | 7,8 | 0 · 0 · 0 · 0/183 |
| objetivo §2 (390) | ≤ 8 | ≤ 1,5 | ≤ 2 | ≤ 3 | ≤ 3 | — | — | — | — | 0 |

Contra la 2c (§6.6, B a 390): pantallas **11 → 8,5**, precio 2,7 → 2,5, juegos 3,3 → 3,2, cumple 5,1 → 4,8
(3,5 con el orden cambiado). Lo que enseñan los números:
1. **Los slides bajan el largo casi hasta el objetivo** (8,5 contra ≤ 8; §6.7 estimó ~8,7). Lo que queda es
   el hero (0,94 pantallas, `#252`) y el aire de 160 px entre ocho secciones (1,5 pantallas, `#314`):
   llegar a ≤ 8 es tocar una de esas dos decisiones, no la forma.
2. **El precio a 2,5 y no a ≤ 2** es el coste de contestar «¿para quién?» antes que «¿cuánto?» (§6.6·2):
   las dos tarjetas de zona miden 0,94 pantallas a 390.
3. **El cumpleaños solo llega a ≤ 3 si va antes que los juegos y se mide su TARJETA (3,5), no su botón
   (4,0)**: la comparación de packs mide 1,36 pantallas y el botón está al final de las columnas. D-G3
   sigue siendo del owner, y ahora se ve con el conmutador.
4. **320 px es el peor caso a propósito** (568 de alto): 12,4 pantallas — y aun así cero líneas partidas.
5. **18 textos (Jump) y 11 (Kids) van SOBRE FOTO** (la edad en punteada y los distintivos del carril): la
   sonda no puede medir su fondo efectivo y los cuenta aparte, no como verdes. Es el trato que la web
   real ya da a `.ride-card__badge`; si el owner los ve flojos, la salida es un velo bajo la etiqueta.

**Lo que NO reproduce (a propósito, y dicho en la página)**: el imán y la coreografía del hero (`#252`; se
enseña el punto estático), el vídeo (la foto de la zona Jump en su lugar — el póster del vídeo es la
cafetería), el minijuego, el menú a pantalla completa, el cajón, el banner de cookies, la i18n y el par
de la cabecera que asoma y late.

**Trampas pagadas — cuatro, todas con cifras creíbles**:
1. **La copia servida no llevaba `<meta viewport>`** y el contexto móvil de Playwright la maquetaba a
   980 px: «3,3 pantallas» a 414 y líneas rotas a 202 px que no existían. El anfitrión del artefacto pone
   la suya; la copia local, no. Se declara en el fuente.
2. **Una línea de dato con un hijo en bloque** («desde 12 €» + «por persona · L–J») contaba dos filas por
   diseño: se marca cada línea por separado, nunca el envoltorio.
3. **Un fallo de CASCADA**: `.pair` iba después que `.nav__pair` con la misma especificidad, así que el par
   de arriba se pintaba también en móvil y echaba la hamburguesa fuera de pantalla — y `overflow-x: clip`
   lo escondía (desbordamiento medido: 0). **Lo cazó la captura, no la sonda.**
4. **El detector de «dos líneas» por cubos de 4 px** partía «Jump 8+» (Bungee y Hanken en la misma línea,
   tops distintos): se agrupa por solape vertical.

**Lo que sigue**: el OJO del owner en el móvil (el artefacto, y el conmutador para D-G3). Con su ✅, B se
construye en la portada real con las guardas de §6.2 más las de esta prueba —línea de dato sin partir de
320 a 414 · un selector · presupuesto de fachada · un relleno de acción por pantalla— y las mismas medidas;
los prototipos de local (`/_diseno/…`) se retiran entonces.

### 6.9 · La segunda vuelta del owner sobre B, en su móvil (2026-09-03) — y la barra flotante, medida

`[owner, 2026-09-03]`, textual: *«Me gusta la portada B, pero me falta un poco más de juego, algo
entretenido, algún elemento más de diseño del brand, animaciones, que la sombra salte al darle clic tal
vez, la card de cumpleaños que se haga slide para ver entre las cards, así son más anchas. La visita 1234
es muy sosa, nada de diferenciación o originalidad. Visítanos tampoco, y faltaría una sección de reseñas
de Google.»* Y el orden: *«Lanzaremos auditoría Hallmark sobre el cajón SPA, y después valoraremos
organización con tu propuesta B si me gusta; si no, te digo yo la idea.»*

**Lo primero que preguntó fue por la barra** (*«¿el botón sticky, float, es correcto?»*). Medido con
`storage/app/bar-390.mjs` a 390 × 844 (control: un botón inyectado del color de acción que la sonda
tiene que contar), la web real contra el prototipo:

| | web real (`.book-bar`, `#205`/`#265`) | prototipo B (`.bar`) |
|---|---|---|
| alto del CTA · de la barra | 56 · 85 (10 + 56 + 19 + safe-area) | 56 · 85, idéntica |
| reserva bajo la página | `--book-bar-block` | `padding-bottom: 85px` en `body` |
| ¿recibe el clic? (`elementFromPoint`) | sí | sí |
| ¿tapa el último texto del pie? | no (0 px) | no (32 px de aire) |
| oculta desde | 900 px | 900 px |
| cuándo aparece | **al encoger el hero** (nace bajo él, `#216`) | desde el primer píxel (el prototipo es el punto estático) |
| rellenos de acción a la vista, máximo | **2** (en 3 de 27 medias pantallas) | **3** (en **8 de 18**: la barra + el CTA de sección + un chip) |
| el fantasma «Registrarse» | abre el alta | lleva a `#visita` |

▶ **Respuesta**: la barra es la de la web (`#205`, `#217`, `#265`) y es correcta como mecanismo. **Lo que
no es correcto es lo que hay debajo de ella**: en B cada sección lleva su CTA relleno y la barra se le
suma, así que la regla «un relleno de acción por pantalla» (`design.md` §3) falla en casi la mitad del
recorrido — en la web real casi nunca. Salidas, para D-G3: los CTA de sección **fantasma** mientras la
barra está a la vista (la barra ES el CTA de compra; la sección solo necesita decir «reservar Jump»), o
la barra se **retira** cuando un CTA de sección entra en pantalla. Y el fantasma de la barra tiene que
abrir el alta, como en la web.

**Lo que cada petición toca, y de dónde sale la respuesta** (orientación; se decide viendo opciones
renderizadas sobre B, como todo en esta fase):

1. **Juego · brand · animaciones**: B fue tipografía a propósito (§6.7: «primero la estructura, después
   el vestido»). El vestido está inventariado: el kit (`design-playjump.md` §8: poses, frisos, manchas,
   tiras) con su presupuesto (una mancha grande por pantalla, nada bajo un párrafo, ninguna pose
   repetida) y **las once microanimaciones del cliente** (§6: el *bote* al entrar una tarjeta, la *lona*
   una vez por pantalla, el sello que gira, la cascada de franjas; máx. dos a la vez; ningún bucle).
2. **«Que la sombra salte al clic»**: es el estado PULSADO de su sistema — *la pegatina se aplasta*
   (`design-playjump.md` §5: translate 3 · sombra 5→2; activo translate 5 · sombra 0 · 180 ms). Es la
   D5 de la auditoría, que el owner dejó en «responde con color» para los BOTONES; aquí la pide para
   las **pegatinas**. Se enseña sobre una tarjeta real y decide si entra (`HoverDoesNotJumpTest` mira
   `:hover`, no `:active`: la pisada no la prohíbe).
3. **El cumpleaños en slide, tarjetas más anchas**: choca con la regla de §6.7 —*«una elección entre
   dos nunca va en slide»*— y con «dos opciones se comparan» (`design.md` §9). Es suya: se renderizan
   las dos (lado a lado con filas alineadas, como hoy · slide con la segunda asomando y las filas
   alineadas dentro de cada tarjeta) y elige con la consecuencia delante: en slide no se comparan de un
   vistazo.
4. **«Visita 1·2·3·4», muy sosa**: cuatro tarjetas de apoyo con icono, título, párrafo y tiempo
   (`scripts/prototipo-b/index.src.html:617-656`) — es la forma-plantilla de los pasos. Opciones a
   renderizar: (a) el **camino** — una sola pieza horizontal con las cuatro poses del kit (`F`, el
   friso) y el paso escrito bajo cada una, el tiempo como sello; (b) la **entrada troquelada** — los
   cuatro pasos como un billete que se arranca (la forma que su diseñador exploró para precios,
   `design-playjump.md` §10, 3b «la tira»); (c) **«Antes de venir»** del cliente (§9: *«Llega y salta:
   tres minutos de lectura y te ahorras la cola»*): un solo bloque de texto con tres cosas y un sello.
5. **«Visítanos», tampoco**: tres pegatinas iguales (horario · dónde · mapa). Opciones: el **mapa como
   pieza** con el horario en vivo encima (chip «Abierto ahora», `#307`) y el teléfono como único botón;
   o el **plano de llegada** dibujado (identidad local, «Salta la Ciudad», `#256`) con los datos al lado.
6. **Reseñas de Google**: `specs/google-reviews.md` 🟦 — §3 decidida (Google fuente de verdad, reseñas
   propias de respaldo; **el snapshot de Google está PROHIBIDO**, la caché corta no) y **pendiente de su
   ✅ y de tres datos suyos: `place_id`, clave de API y techo de gasto**. La forma es la que él señaló
   como lo único estructural de `Landing PJP Modos`: «**Lo dicen ellos**» + «**4,8 sobre 5** · 320
   reseñas en Google» (el marcador amarillo) + tarjetas en slide con `scroll-snap` (estrellas en píldora
   de tinta, texto, nombre, meta en mono). ⚠️ Sin `place_id` y clave la sección **solo puede enseñar
   el respaldo**; y la valoración agregada es el único número que el dominio no sabe calcular (§1.1).

**Lo que sigue**: con la auditoría del cajón entregada (`auditoria-cajon.md`, `#438`), se renderizan
sobre el prototipo B las opciones de 2–6 y el owner elige, o dicta la idea.

## 7. Revisión y decisión

- 2026-09-03 · borrador del agente a partir de la auditoría (`#430`), las cuatro preguntas del owner,
  el material del cliente (`design-playjump.md` §9–§10) y la medición de §1.2.
- 2026-09-03 · la 2c medida (§6.6) · el owner decide (§6.7, `#432`) · **B en artefacto** (§6.8, `#433`).
- 2026-09-03 · el owner ve B en su móvil: segunda vuelta y la barra medida (§6.9, `#438`).
- Pendiente: revisión adversarial (¿qué pregunta del visitante falta? ¿qué regla muerde en un sitio
  que no he visto?), D-G3 · D-G6 · D-G7, y la entrada de aprobación en `DECISIONES.md`.
- **Siguiente paso concreto**: el OJO del owner sobre el artefacto en su móvil; con su ✅ y las tres
  decisiones que quedan, B en la portada real (§6.8, «lo que sigue»).
