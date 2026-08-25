# [SPEC] La landing white-label — qué es DATO, qué es PÁGINA y qué es TEMA

> Estado: **🟦 en revisión** (marco DECIDIDO por el owner el 2026-08-25; falta dimensionar la
> tanda C) · Última actualización: 2026-08-25 · Decisión asociada: `DECISIONES #136`.
>
> ⚠️⚠️ **Empieza por §1.** Cuatro de las cinco cosas que dolían se midieron contra el código y **una
> era falsa, otra era al revés de como se contaba y otra era peor de lo que parecía**. Diseñar sobre
> el enunciado en vez de sobre la medida habría construido lo que no hace falta.
>
> ⚠️ **La tanda C toca AFORO y PAY** (§4.4). No se implementa desde aquí: necesita su propio
> dimensionado, como enseñó `specs/desglose-dinero-cliente.md` §11 —donde tres tandas «bien
> dimensionadas» resultaron no estarlo y la que se anunciaba como «riesgo cero» tapaba cuatro
> defectos de dominio—.

---

## 1. Contexto y problema — MEDIDO, no supuesto

El owner enunció cinco problemas al preparar la landing del **segundo cliente** de JumpWeb. Cada uno
se comprobó contra el código y la BD de desarrollo antes de diseñar nada.

### 1.1 «El CMS está incompleto» → **PARCIALMENTE FALSO**

Se inventarió qué pide el mockup del cliente nuevo y qué existe ya en BD:

| Lo que el mockup pide | ¿Existe hoy? |
|---|---|
| `zonas`: edad, m², nº de juegos, claim, color | ✅ `zones` → `age_label`, `age_range`, `area_sqm`, `rides_count`, `subtitle`, `description`, `accent`, `color` |
| `zonaJuegos`: nombre, edad, badge, descripción | ✅ `attractions` → `name`, `description`, `image`, `age`, `badge` |
| `cumples`: desde, nº de niños, señal, qué incluye | ✅ `ticket_types` → `min_qty`/`max_qty`, `deposit_type`/`deposit_value`, `features`, `duration_min` |
| `normas`, `horario`, `especiales`, ofertas, FAQ | ✅ `venue_rules`, `opening_hours`, `special_dates`, `offers`, `faqs` |
| `statsParque` (los números del hero) | ❌ **no hay modelo** |
| `opiniones` (testimonios) | ❌ **no hay modelo** |

▶ **Faltan DOS modelos, no un CMS.** El resto del contenido que el cliente nuevo necesita ya tiene
casa. Es la corrección más importante de esta spec: el trabajo no es «construir el CMS», es
**enchufar la landing a lo que el CMS ya sabe**.

### 1.2 «Hay datos que se escriben en la landing y son editables en el CMS» → **ES AL REVÉS**

No es que se dupliquen: es que **el copy de la landing vive en el REPO**.

    lang/es|en|fr/landing.php  →  142 claves distintas
    home.blade.php             →  68 llamadas a `__()`, 0 lecturas de contenido de BD para texto

▶ Un cliente **no puede cambiar su propio titular sin un despliegue**. Ése es el defecto real, y es
justo el que bloquea una instalación por cliente.

### 1.3 «Hay datos de lógica del sistema de reservas en la landing» → **CIERTO, y es peor**

`landing_services.price_table` es una tabla de precios **tecleada a mano** en JSON, con importes
reales por tramo de grupo (30 / 75 / 100), por duración (120 / 180 min) y por entre semana / fin de
semana. Vive en paralelo al motor de tarifas.

▶ **Y se midió POR QUÉ se tecleó.** De los tres ejes de esa tabla, el catálogo ya expresa dos:

- **duración** → es un producto (`Jump · 2 horas`, `Jump · 3 horas`, con `duration_min`);
- **entre semana / finde** → es un `rate_type` (`RateResolver` lo resuelve por `weekdays` y por
  `special_dates`).

El que **no** sabe expresar es el **precio por tramo de tamaño de grupo**. `prices` es
`(priceable, rate_type) → amount_cents`: no hay eje de cantidad.

▶ **Por ese único hueco se copió la tabla entera**, y ahora los precios de fin de semana viven en dos
sitios que pueden separarse sin que nada avise. Es exactamente la forma de defecto que
`LedgerSingleSourceTest` existe para prohibir en el dinero del cliente, sin guarda equivalente aquí.

### 1.4 «/servicios es una chapuza y no lee del sistema de reservas» → **CIERTO**

Medido sobre la BD de desarrollo:

    excursionescolegio   ticket_type = NINGUNO   price_table = escrita a mano
    teambuilding         ticket_type = NINGUNO   price_table = escrita a mano
    sesionadultos        ticket_type = NINGUNO   price_table = no

⚠️ **El código SÍ sabe leer del catálogo** —`LandingService::isPurchasable()` delega en
`TicketType::isSellablePackForLanding()`— pero **ninguno de los tres servicios tiene producto
vinculado**, así que esa rama no se ejecuta nunca. La página es 100 % editorial con precios a mano.

▶ **Y no es un olvido: es que el producto no existe en el dominio.** Los tres son *reservas de grupo
fuera del horario de apertura*, y eso el sistema no lo sabe modelar (§4.4).

### 1.5 «Cada cliente quiere otra organización y otro diseño» → **CIERTO, y medible**

Secciones de la landing actual frente a las del mockup del cliente nuevo:

    HOY      zones · rides · pricing · info · gallery · reserve
    MOCKUP   zonas · cumpleaños · normas · info · opiniones · reservar

▶ **Coinciden 3 de 6 — y son exactamente las tres alimentadas por el motor de reservas** (zonas,
horario/ubicación y el cajón). Las tres que cambian son las editoriales.

⚠️ Y el mockup **no es la landing actual con otros colores**: trae menú de velas, un minijuego de
castillo hinchable y otro hero. Cualquier diseño que asuma «mismo esqueleto, otra marca» es falso
antes de empezar.

---

## 2. Objetivo

**Que instalar un cliente nuevo sea configurar contenido y escribir una plantilla, no tocar el
dominio.**

Criterios de éxito, todos medibles:

1. **0 importes tecleados** fuera del motor de tarifas: `landing_services.price_table` deja de
   existir y su guarda lo impide (§6).
2. **0 claves de copy de la landing en `lang/`** para lo que un cliente querría cambiar: las 142
   claves se clasifican en «chrome de producto» (se quedan) y «voz del cliente» (bajan al CMS).
3. **Los tres servicios de `/servicios` son productos del catálogo**, con precio resuelto en vivo,
   igual que un pack de cumpleaños.
4. **El tema por instalación se inyecta de verdad**: hoy hay 28 variables CSS y 1.271 usos de
   `var()` en `site.css` y **cero** las alimenta desde BD.
5. **La landing del segundo cliente se monta sin migraciones nuevas** más allá de las de esta spec.

### Fuera de alcance, explícitamente

- ⛔ **Un maquetador visual** («el cliente arrastra secciones»). Descartado en §3, con su porqué.
- ⛔ **Rehacer el cajón.** El cajón es motor compartido: aquí solo se le da tema, no estructura.
- ⛔ **Tocar el desglose de dinero del cliente**, cerrado en `specs/desglose-dinero-cliente.md`.

---

## 3. Opciones consideradas

### A · Data-driven TODO, maquetado incluido (page builder) — **DESCARTADA**

El cliente elige secciones, orden y contenido desde el panel.

▶ **Por qué no.** Es con diferencia la más cara de construir y de mantener, y el resultado empeora:
nadie diseña arrastrando cajas. El mockup del cliente nuevo lo demuestra —minijuego, menú de velas,
hero propio—: un maquetador genérico no habría podido producirlo, así que el caso real que motiva
todo esto **no lo resuelve**. Acabaríamos manteniendo un gestor de contenidos peor que los que ya
existen, y encima con la landing como rehén.

### B · Solo cerrar los agujeros de hoy — **DESCARTADA**

Vincular los servicios a productos, matar la `price_table` y dejar el resto.

▶ **Por qué no.** Arregla §1.3 y §1.4 y **no toca §1.2**, que es lo que bloquea al cliente nuevo: su
copy seguiría en el repo. Descartada como alcance, **conservada como orden**: es la tanda que más
riesgo quita y por eso va delante (§7).

### C · Dominio data-driven + plantilla por cliente — **ELEGIDA** (owner, 2026-08-25)

Todo lo que la landing **enseña** sale del dominio o del CMS; la **composición** de la página es un
paquete por cliente, en código.

▶ **Por qué.** Es lo que la medida de §1.5 sugiere sola: la mitad estable de la página es la que
cuelga del motor de reservas, y la mitad volátil es editorial. Da la línea sin inventarla.

---

## 4. Diseño elegido

### 4.1 La línea: **data-driven el DATO, no la PÁGINA**

| | Regla | Consecuencia práctica |
|---|---|---|
| **Dato** | Todo importe, fecha, aforo, capacidad, nombre de producto y texto editable sale del dominio o del CMS | 0 cifras en Blade, 0 precios en `lang/`, 0 tablas tecleadas |
| **Página** | Qué secciones hay, en qué orden y con qué maquetación es un **paquete de tema del cliente** | Cliente nuevo = plantilla nueva, **no** modelo de datos nuevo |

⚠️ **El paquete del cliente puede tocar el CAJÓN también.** No es «landing en código, cajón en
tokens»: si un cliente quiere un detalle de diseño compartido entre las dos superficies, se añade
una vez al sistema de iconos y lo usan las dos (§4.5.3).

### 4.2 Los dos modelos que faltan

Los únicos del inventario de §1.1 sin casa. Ambos son contenido puro, sin reglas:

- **`park_stats`** — los números del hero (`num`, `label`, `position`, `is_active`, i18n).
- **`testimonials`** — opiniones (`text`, `author`, `meta`, `position`, `is_active`, i18n).

⚠️ **No se modelan como «bloques genéricos»** con un `type` y un `payload` JSON. Un bloque genérico
es un maquetador con otro nombre: pierde validación, pierde el formulario específico del panel y
pierde la posibilidad de que un test asevere su forma. Dos tablas pequeñas y explícitas envejecen
mejor.

### 4.3 El copy baja de `lang/` al CMS — pero **NO todo**

Las 142 claves de `lang/*/landing.php` se parten en dos por un criterio único:

| Clase | Qué es | Dónde vive | Ejemplos |
|---|---|---|---|
| **Voz del cliente** | Lo que un parque distinto diría distinto | **CMS**, i18n por fila | titular del hero, claims de sección, intro, textos de las tarjetas |
| **Chrome de producto** | Lo que significa lo mismo en cualquier instalación | **`lang/`**, como hoy | «Anterior», «Siguiente», «Reservar», «Ver más», etiquetas de accesibilidad |

⚠️ **La prueba para decidir es una pregunta, no el gusto**: *¿un cliente distinto querría cambiar
esto y su cambio seguiría siendo correcto?* Si la respuesta es no, es chrome. Bajar el chrome al CMS
multiplica el trabajo de instalar un cliente sin darle ninguna libertad útil, y es el error más
frecuente en productos white-label.

### 4.4 ⚠️⚠️ `/servicios`: de fila editorial a **PRODUCTO REAL** — TANDA C

**Decisión del owner (2026-08-25):** *«debemos ampliar el sistema de reservas para que ese tipo de
servicios estén en el sistema realmente, no hardcodeados en esa página»*.

#### Qué son estos servicios, en términos de dominio

Leídos de la BD, los tres son la **misma forma de producto**:

    Excursiones de colegio   Kids + Jump      2 o 3 horas   fuera de apertura
    Empresas (team building) parque completo  mín. 30 pers. fuera de apertura
    Excursión para mayores   parque completo  22:00–01:00   mín. 30 pers.

▶ Es una **reserva de grupo privada fuera del horario de apertura**, con precio **por persona y por
tramo de tamaño de grupo**. No es un pack de cumpleaños ni una entrada suelta.

#### Las tres extensiones que exige, y por qué cada una es la mínima

1. **Precio por TRAMO DE CANTIDAD.** `prices` es hoy `(priceable, rate_type) → amount_cents`. Hace
   falta un eje más: `min_qty` por fila de precio, resolviendo «el tramo de mayor `min_qty` que no
   supere la cantidad pedida». Es aditivo: una fila sin tramo se comporta exactamente como hoy.
   ⚠️ **Radio de impacto medido: 18 ficheros** tocan la resolución de precio. Por eso el tramo se
   consulta **solo** donde se cobra de verdad (`Booking\Services\CartPricer`) y donde se resuelve la
   tarifa (`Booking\Services\RateResolver`); las demás superficies siguen leyendo el precio base y
   rotulan «desde». Cambiar los 18 sitios a la vez sería la clase de tanda mal dimensionada que
   `specs/desglose-dinero-cliente.md` §11 documenta.
2. **Franjas FUERA del horario de apertura.** Las franjas se generan de `opening_hours` +
   `slot_templates`, así que hoy **no existe** una franja a las 22:00 si el parque cierra a las 21:00.
   ⚠️ Esto es lo que hace la tanda cara y lo que la mete en terreno de `AFORO`: una reserva privada
   **ocupa la zona entera** y tiene que impedir cualquier otra venta en ese tramo.
3. **No es autoservicio, pero sí es un pedido.** Se reserva con petición y confirmación del
   operador. Se apoya en lo que ya existe (`ManualOrderFulfiller` crea pedidos firmes sin hold,
   `AFORO-10`), no en un flujo nuevo de checkout.

#### ⚠️ Y ENTRA UNA SEGUNDA MITAD, planteada por el owner el 2026-08-25 (`DECISIONES #139`)

El modelo de catálogo que quiere: **entrada por zona, con el cupo de la zona**, y **pack con cupo
PROPIO** —y no solo de cumpleaños: excursión de colegio, comunión…—, con la landing anunciando **un**
pack destacado y el resto en `/servicios`, **alternable**.

⚠️⚠️ **Medido, y cambia el diagnóstico: la mayor parte ya está construida.**

| Lo que pide | Estado medido |
|---|---|
| Pack con cupo propio | ✅ `packs.max_per_slot` / `packs.max_guests_per_slot`, con **override por zona** |
| Alternar landing ↔ `/servicios` | ✅ existe… **por AUSENCIA**: `birthdaySurfacePacks()` filtra `whereDoesntHave('landingService')`, así que **crear una ficha de servicio mueve el pack de sitio**. Nadie lo adivinaría |
| Packs que no sean de cumpleaños | ⚠️ el MODELO aguanta N tipos; lo casado con «cumpleaños» es el **vocabulario y la superficie** (scope, sección, textos) |
| Identidad visual del pack | ⚠️ va con la ZONA — que es dónde se celebra, no lo que el pack ES comercialmente |

▶ **No hace falta un cambio de arquitectura: hace falta hacer EXPLÍCITO lo que ya es implícito** —la
conmutación por ausencia de `LandingService`— y **desacoplar el vocabulario**. Es trabajo de esta
tanda C, y **se diseña en su spec propia**, no aquí.

#### Y entonces `/servicios` deja de tener datos propios

`landing_services` se queda con lo **editorial** (`title`, `body`, `image`, `accent_word`, `slug`) y
pierde `price_table` y los `specs` que repiten catálogo (duración, tamaño de grupo, horario): esos
pasan a leerse del `ticket_type` vinculado, que a partir de aquí **es obligatorio**.

⚠️ **`price_table` no se «deja de usar»: se BORRA**, con su columna. Una columna muerta que aún
contiene precios reales es una invitación a que alguien la vuelva a pintar.

### 4.5 El TEMA son **TRES mecanismos**, no uno

Fue el error de encuadre de la primera propuesta, y lo corrigió el owner: «tokens o código» es un
binario falso.

#### 4.5.1 Valores → **tokens desde BD**

❗❗ **CORRECCIÓN (2026-08-25, `DECISIONES #138`): este apartado afirmaba «cero variables se inyectan
desde BD» y ERA FALSO.** El tema **sí** se inyecta —`<style id="jj-theme">:root{…}</style>` en el
layout, alimentado por `ThemeSettings::cssRootDeclarations()` desde el ajuste `theme.brand`, con el
contraste calculado por luminancia y consumido también por el panel Filament, los correos y los
avatares—.
▶ **El error fue de MEDICIÓN**: el `grep` buscaba `style="--`, `--c-` y `setProperty`, y la forma real
no casa con ninguno. **Un `grep` que no encuentra no demuestra que no exista** — la misma lección que
`#135` y `#137`. Queda escrito porque es el tipo de afirmación que un agente siguiente daría por buena.

**Lo que SÍ faltaba, ya con nombres**: el acento de zona viajaba por el nombre de la clase y solo
existían reglas para `jump` y `kids` (§4.5.4), el color secundario no tenía casa en BD, y la tipografía
y los radios no se tematizan — y **no deben**, por la decisión del owner de §4.5.5.

⚠️ Quedan **58 colores en crudo** en `site.css` y **18** en `public/css/landing.css`: el inventario
de cuáles suben a variable es parte de la tanda, no un pulido posterior.

#### 4.5.4 ✅ EJECUTADO — el acento de zona sale del nombre de la clase (`#138`)

Medido: el color viajaba por **dos caminos** y divergía. La tarjeta usaba `zones.color` —el suyo— y la
pestaña una regla `.zone-tab--{accent}` que leía el color de **la primera zona con ese acento**. Con
`cap` (`accent=kids`, `color=#FF5B22`) la tarjeta salía naranja y la pestaña lima.
▶ Las **diez** clases acopladas desaparecen; cada superficie pinta el color en línea con
`ThemeSettings::zoneStyle()`. Y **quita CSS**: `.zone-tab.active` ya era genérica.
▶ Con ello entran `zones.color_secondary`, el ajuste `theme.brand_secondary` y el token semántico
`--attn` —tres sitios usaban el amarillo de Jump para significar «atención»—.

#### 4.5.5 [DECIDIDO owner] Tipografía y radios NO van al panel

Van en el **paquete CSS del cliente**, al instalar. El tema en BD se queda en **color**, que es lo que
el operador retoca de verdad y **lo único que tiene que cruzar al panel y a los correos**, donde el CSS
del cliente no llega. Menos mandos que mantener, y un cliente con una fuente de marca con licencia
propia cabe sin pedirle permiso a una lista curada.

#### 4.5.2 Ficheros → **assets por instalación**

Logo, imágenes y **el dibujo del spinner**. Aquí la costura también está hecha y conviene decirlo:
`public/css/spinner.css` es **un fichero de 123 líneas, una clase (`.jj-spinner`) y 17 ficheros que
la referencian**, y ya expone tres tokens (`--jj-spinner-size`, `--jj-spinner-color`,
`--jj-spinner-speed`).

▶ Cambiar color o velocidad **ya es un token**; cambiar el dibujo es sustituir un fichero. No hay que
construir nada: hay que usarlo.

#### 4.5.3 Dibujos compartidos → **el sistema de iconos, que YA EXISTE y ya está guardado**

`resources/views/components/icons/` tiene **22 iconos**, y los usan **las dos superficies**: la
landing (`home.blade.php`, `components/site/nav.blade.php`, `components/site/mobile-book-bar.blade.php`)
y el cajón (**14 de sus 38 `.vue`** llevan SVG inline).

⚠️⚠️ **Y `SidebarIconParityTest` obliga a que no se separen**: cada geometría que emite el cajón tiene
que ser, byte a byte tras normalizar, la de un `<x-icons.*>` del sistema de diseño, o estar declarada
como propia con su motivo. Nació de que el cajón se sirvió una vez con **20 `<svg>` vacíos** sin que
ningún gate se enterara.

▶ **Conclusión para el owner:** «quiero un detalle de diseño compartido entre cajón y landing» **ya
se puede hacer hoy**, se hace una sola vez y hay un test que impide que las dos copias diverjan.

### 4.6 El icono por producto — el hueco de verdad del tema

Hoy el icono de un producto lo decide un **booleano**: `components/icons/product.blade.php` pinta la
tarta si es pack y el ticket si no. Y `ticket_types` **no tiene ninguna columna de icono ni imagen**
(solo `wristband_color`). Ni el cliente puede cambiarlos ni «Tirolina aérea» puede tener el suyo.

▶ **[DECIDIDO owner, 2026-08-25] Set CURADO, no subida libre.** `ticket_types.icon` (futuro) guarda
la **clave** de un icono del sistema de diseño y el panel lo ofrece con vista previa. Cada cliente
puede llevar su propio set en su paquete de tema.

- **A favor:** el catálogo nunca se ve descuidado (trazos y tamaños coherentes) y **la paridad
  cajón↔landing sigue pudiendo comprobar el dibujo**, que con SVG subidos sería imposible.
- **En contra, y asumido:** añadir un icono nuevo es un despliegue.
- ⚠️ Y se evita el coste callado de la subida libre: un SVG es **código ejecutable** y habría que
  sanearlo antes de servirlo.

---

## 5. Impacto en invariantes

| Invariante | Impacto |
|---|---|
| **AFORO-01** (lock de franjas con `zone_id` literal) | ⚠️⚠️ **TANDA C.** Una reserva privada ocupa la zona entera: el bloqueo tiene que cubrir todas las franjas de la zona y el tramo, y **no puede reintroducir subconsultas antes de `lockSlots()`**. Es el invariante más sutil del sistema y el que exige `VERIFY_CONC=1`. |
| **AFORO-02** (fuente única de oferta) | ⚠️ **TANDA C.** Las franjas fuera de apertura tienen que ofrecerse por `SlotOffer` como todo lo demás. Una segunda vía de oferta para los grupos sería exactamente la divergencia que este invariante cierra. |
| **AFORO-03** (las franjas se regeneran solas) | ⚠️ **TANDA C.** Si las franjas privadas se generan aparte, hay que decidir si el `slots:generate-rolling` las mantiene o si nacen a demanda. |
| **PAY-16 / PAY-17** (las dos identidades del desglose) | ⚠️ **TANDA C.** Un producto con precio por tramo sigue teniendo que cerrar las dos identidades. La cascada del panel (cambiar cantidad re-tarifica) ya existe desde `PAY-18`. |
| **PERF-01** (`Setting::value()` memoiza por petición) | Tandas A y B: el tema y el copy del CMS se leen en cada página. Entran en el payload memoizado del composer global, no en consultas sueltas. ⚠️ La home tiene presupuesto de consultas aseverado. |
| RGPD-*, SEC-* | Ninguno: no hay PII nueva. ⚠️ Salvo que se eligiera la **subida libre de SVG**, descartada en §4.6 precisamente por eso. |

---

## 6. Plan de verificación empírica

Sin esto no puede llegar a ✅ (`/dod` §3.bis).

1. **`ServicePricesSingleSourceTest`** (futuro) — hermana de `LedgerSingleSourceTest`: **prohíbe el
   mecanismo**, no persigue el síntoma. Ninguna superficie de contenido puede llevar un importe.
   Cae si vuelve a existir una columna de precios en `landing_services` o un `€` tecleado en la
   plantilla de servicios. ⚠️ **Con su guarda-de-la-guarda**: un `grep` mal escrito queda verde para
   siempre sin mirar nada, y en este repo ya pasó.
2. **Paridad precio catálogo ↔ `/servicios`** (futuro) — el precio que la página enseña es, campo a
   campo, el que resuelve `RateResolver` para esa fecha y esa cantidad. Es la aserción que hace
   imposible que vuelvan a separarse.
3. **Los tramos, por MUTACIÓN** — quitar el eje de cantidad tiene que dejar en rojo la resolución del
   precio de grupo y **no** la de un producto sin tramos (que es el 100 % del catálogo de hoy).
4. **`VERIFY_CONC=1`** sobre `purchase:verify-oversell` con una reserva privada en juego: una zona
   ocupada por un grupo **no** puede vender ni una entrada en ese tramo. Es la comprobación que
   decide si la tanda C está bien o no, y **no vale hacerla en SQLite ni en staging** (MariaDB).
5. **Presupuesto de consultas de la home** — el copy y el tema desde BD no pueden empeorar el
   presupuesto ya aseverado en `HomePageTest`.
6. **Medida de cierre, contra el caso real**: montar la landing del segundo cliente y contar cuántas
   migraciones y cuántas líneas de dominio hicieron falta. **El objetivo es cero de las dos.** Si no
   es cero, la línea de §4.1 está mal puesta y hay que corregirla aquí antes que en el código.

---

## 7. Las tandas, dimensionadas por RIESGO y no por pantalla

| | Tanda | Toca | Por qué va aquí |
|---|---|---|---|
| **A** | **El tema, de verdad** — inyección de tokens desde BD, inventario de los 76 hex crudos, spinner rebrandeable, `ticket_types.icon` con set curado | Presentación | Es lo más barato, no toca dominio y es lo primero que el owner ve. Y deja el terreno para que la plantilla del cliente nuevo tenga con qué pintar. |
| **B** | **El contenido** — los dos modelos que faltan, el copy que baja de `lang/` al CMS, y la landing del segundo cliente como **primer paquete de tema** | Contenido + plantillas | Es lo que desbloquea al cliente nuevo. Su medida de éxito es la de §6·6. |
| **C** | **⚠️ Los servicios como PRODUCTO REAL** — tramos de cantidad, franjas fuera de apertura, `/servicios` leyendo del catálogo, borrar `price_table` | **AFORO + PAY** | Va **última** y **necesita su propia spec**: es la única que entra en el núcleo de dinero y aforo, y la única que exige `VERIFY_CONC=1`. |

⚠️⚠️ **La tanda C no se empieza desde este documento.** Aquí queda su encuadre y su porqué; su
diseño —cómo se modela una franja privada, qué pasa con el aforo de la zona, cómo se pide y se
confirma— es una spec propia. Anunciarla como «tres extensiones aditivas» sería repetir el error de
dimensionado que ya está documentado en el repo.

---

## 8. Revisión y decisión

- **Medido por el agente** el 2026-08-25 contra el código y la BD de desarrollo: los cinco puntos de
  §1, el radio de impacto de precios (18 ficheros), el inventario de iconos (22, en 2 superficies) y
  el estado del tema (28 variables, 1.271 `var()`, 0 inyecciones, 76 hex crudos).
- **Re-verificado cifra a cifra** contra el código tras escribirla, a petición del owner: **10 de las
  12 cifras salieron exactas**; se corrigió que los consumidores del spinner son **17 y no 15** (la
  primera cuenta leyó una lista truncada por `head` como si fuera el total). Y todas las clases,
  métodos y tests que cita existen —`CartPricer`, `RateResolver`, `SlotOffer`, `SlotGenerator`,
  `ManualOrderFulfiller`, `SidebarIconParityTest`, `LedgerSingleSourceTest`, el presupuesto de
  consultas de `HomePageTest` y `purchase:verify-oversell`—.
  ⚠️ **Los tres tokens del spinner sí eran tres**: el `grep` que parecía desmentirlo contaba también
  las líneas del comentario que los documenta. Una cifra que «falla» hay que mirarla antes de
  corregirla, o se arregla lo que estaba bien.
- **Decidido por el owner** el 2026-08-25: opción **C** de §3, e **icono de producto por set curado**
  (§4.6). Y el encargo explícito de que los servicios pasen al sistema de reservas de verdad (§4.4).
- **Corregido por el owner**: el tema no es un binario. De ahí los tres mecanismos de §4.5 — y el
  hallazgo de que el compartido cajón↔landing que se temía imposible **ya existe y ya tiene guarda**.
- **Entrada final**: `DECISIONES #136`.
