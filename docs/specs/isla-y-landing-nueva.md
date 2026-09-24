# [SPEC] La isla y la landing nueva — las páginas en la instancia y una segunda carcasa de compra en el producto

> Estado: ⬜ **borrador** · Última actualización: 2026-09-24 · Decisiones: `#681` (las páginas), `#682` (la
> isla), `#683` (las cuatro de §7) y `#684` (promociones), todas `[DECIDIDO owner]`; la spec se aprueba con la suya.
> Carril: **plataforma** (banda 670–699). Fuente del diseño: el proyecto de Claude Design
> `33397ca2-c67c-4049-8b09-ade20425f32a`, «Saltia Design System» (nombre provisional; la marca es Play Jump
> Park), leído con `DesignSync` (`list_files` / `get_file`). Hermanas: `instancia-y-landing-fuera.md` (el menú
> de hechos), `cajon-empaquetable.md` (el motor y su §4.9), `analitica.md` §4.4 (los experimentos).

## §0 · Antes de tocar

- **Regla que ordena todo**: dos piezas y dos dueños. Las **páginas** son de la instancia, en Blade, y solo
  reciben hechos (`#681`). La **isla** es del producto: una segunda carcasa sobre el motor del cajón, apagada
  por defecto (`#682`). De PlayJump no entra nada al producto: sus textos, fotos y valores de tokens son datos.
- **Empieza por** §1.3 (el censo de la isla) → §4.1 (motor y carcasa) → §4.3 (el contrato página↔isla).
- **Trampas, antes de tocar**:
  - ⚠️ **La web nueva cambia las URLs** (§1.5): sin 301 se pierde el SEO y no falla nada.
  - ⚠️ Los componentes del diseño son **React con datos de prueba** y calculan el total en el navegador. En
    producción el total sale de `/orders/quote` (§4.4).
  - ⚠️ `DesignSync` corta a **256 KiB**: el vídeo y el logotipo se toman del original, nunca de ahí.
  - ⚠️ El README del diseño se contradice (dice «`assets/` vacío» y trae logo y vídeo): mandan los ficheros.
  - ⚠️ El aviso de cookies pasa a la isla, y la T3 de la analítica (carril del SPA, `#735`) toca ese aviso:
    se avisa en el buzón antes.
  - Tras tocar un `.vue`, `npm run build:ssr` antes de la suite (`sidebar-spa.md` §0).
- **Estado**: §7 contestado (`#683`), promociones en `#684`, T0 hecha (§1.6). **T1 ✅** (§4.8) y **T2 ✅**
  (§4.9): la isla en Vue, 52 de 52 situaciones idénticas al diseño. **T3** (la compra, §4.10): T3a→T3d ✅;
  la secuencia de compra vive en `sidebar/usePurchaseFlow.js`. T3e en seis sub-tandas (`#692`): ·1→·4 ✅, la isla
  compra con tarjeta hasta el banco (`#694`), con «Entra» y Google, que vuelve a la compra (`#695`).
- **Invariantes**: `PAY-*` si entra Bizum (`VERIFY_CONC=1`), `SEC-12` (la ruta de las vistas), `SEC-01`,
  `RGPD-*` (consentimiento dentro de la isla, Apple como proveedor), `PERF-02` (la carcasa por instalación va en
  el arranque cacheado; la variante del A/B, en la sesión).

## 1. Contexto y problema — MEDIDO (2026-09-24)

### 1.1 Qué trae el diseño

Medido con `DesignSync list_files` y leyendo entero su `readme.md` (125 KB, 693 líneas) y el `Mapa`:

- **Fuentes**: el `Mapa` (18-09, compradores, puntos de contacto y el orden de los argumentos) y once briefs:
  base (v6), portada, cumpleaños, Kids, Jump, isla, compra, Mi cuenta, normas, visítanos y correos.
- **71 componentes React** (`.jsx` + `.d.ts` + `.prompt.md`): compra 6 · content 15 · core 6 · feedback 4 ·
  forms 10 · marketing 25 · navigation 5. Todos con variables CSS, sin CSS-in-JS ni dependencias.
- **31 secciones** montadas (`sections/*.card.html`) y **seis páginas** (`paginas/`): portada, Cumpleaños, Kids,
  Jump, la compra en la isla y Mi cuenta en la isla. Kids y Jump son **un solo molde** con dos textos.
- **Siete ficheros de tokens** (`tokens/`), 22 guías y un vídeo, un logotipo y cuatro fotos reales.
- **Decisiones de marca del owner (20-09)**: ambiente claro, cian de marca, **el naranja del logo como único
  color de acción**, sin color por zona y el héroe en tarjeta, nunca a sangre.
- **Lo que no está**: Colegios (el `Mapa` lo deja fuera), las páginas de Normas, Visítanos y legales (tienen
  brief pero no página), el post-form, la invitación y el justificante (tienen brief propio).

### 1.2 El SPA de hoy, medido

- La máquina (`machine.js`) tiene once pasos: `CATALOG`, `DATE`, `TIME`, `CART`, `IDENTIFY`, `VERIFY_EMAIL`,
  `PAY`, `REDIRECTING`, `CONFIRMED`, `DECLINED`, `VERIFYING`. Hay **20 stores**, cada uno con su test.
- `resources/js/sidebar/**`: **~10.600 líneas de lógica JS y ~12.900 de sus tests**, frente a **~8.100 de
  pantallas Vue** (51 ficheros). El motor no sabe de forma (`cajon-empaquetable.md` §4.9).
- **El alta dentro de la compra ya es «paga primero»**: sin correo de verificación y con la sesión abierta
  (`submitRegister` en `PurchaseSection.vue`). `VERIFY_EMAIL` solo sale ante el señuelo. Coincide con el diseño.
- `POST /auth/register` exige `name`, `email`, `phone` y `password`: la «cuenta normal con contraseña» del
  diseño ya existe.

### 1.3 El censo de la isla: sus quince situaciones contra el producto

El brief de la isla (`brief-isla-playjump`) define quince situaciones y quién manda cuando coinciden. La tabla
dice qué pide cada una y qué da hoy el producto. **HAY** = existe · **PARCIAL** = falta un trozo · **FALTA** =
lógica nueva · **DATO** = texto del cliente, no lógica.

| Nº | Situación | Lo que pide | Hoy en el producto | Veredicto |
|---|---|---|---|---|
| 1 | Aviso de cookies | El aviso, dentro de la isla | Aviso en `layout.blade.php` (`COOKIES.md`) | **MOVER** (y la T3 del SPA) |
| 2 | Llega a una página | El «desde» | `/prices` | **HAY** |
| 3 | Hoy | Horario y «quedan huecos» | `/schedule/now`; huecos por producto en `/availability/{product}/times` | **PARCIAL**: no hay «¿quedan huecos hoy?» agregado |
| 4 | La oferta acaba | La fecha real de fin | Solo `promo.percent`, sin fecha | **FALTA** |
| 5 | Una frase por sección | El texto que quita un miedo | — | **DATO** (la página lo declara) |
| 6 | Ha calculado | Total, señal y resto | `/orders/quote` | **HAY** |
| 7 | Día y hora elegidos | La selección | Stores `date`, `time`, `selection` | **HAY** |
| 8 | Se llena · avísame | Horas libres · aviso si se libera | `available` por hora; sin lista de espera | **PARCIAL**; el aviso, **FALTA** |
| 9 | Vuelve · enlace a la pareja | El cálculo guardado y compartible | — | **FALTA** (se puede hacer sin servidor) |
| 10 | Dentro de la compra | Los pasos | La máquina de once pasos | **HAY**; se reagrupan (§4.1) |
| 11 | A medias · pago fallido · hora perdida | La reserva guardada, la vuelta del banco, la retención | `Order.expires_at` (`PaymentSettings::holdMinutes()`), `RetryAdmission`, `/orders/{code}/payment-status` | **HAY**, salvo «Pagar con Bizum» (**FALTA**) |
| 12 | ¿Lo hablamos? | WhatsApp y sus señales | Contacto en `/site`; visitas en el libro de la analítica | **PARCIAL** (la señal de «tercera visita») |
| 13 | Tarea pendiente | Una tarea con su plazo real | Plazos del post-form y de menores en sus specs | **A MEDIR** (no hay lista de tareas como tal) |
| 14 | Reserva hoy | Mi QR | El carné QR (`identidad-qr-puerta.md`) | **HAY** |
| 15 | Páginas que no venden | El horario de hoy | `/schedule` | **HAY** |

**La compra y la cuenta**, fuera de la tabla: tarjeta, carrito de varias líneas («Añadir otra entrada»),
calcetines como complemento, la [Hora extra], Google, contraseña y recuperación: **HAY**. **Apple**: **FALTA**
(ninguna referencia en `app/`). **Bizum**: **FALTA** (ninguna en `app/` ni `config/`). El día de tarifa especial
en el calendario (el punto amarillo): `/schedule` trae las fechas especiales y `/prices` los días de la tarifa;
**A MEDIR** si basta con cruzarlos. Entrar «con correo o teléfono»: **A MEDIR**.

### 1.4 Los tokens: dos vocabularios

El diseño trae primitivos (`--ink-*`, `--flare-*`, `--aqua-*`, `--volt-*`, `--berry-*`, `--sun-*`) y decenas de
alias semánticos (`--bg-*`, `--surface-*`, `--text-*`, `--action-*`, `--control-*`, `--notice-*`, `--band-*`,
`--scrim-*`), y redefine los de lectura bajo `[data-surface="ink"]`. El producto tiene su propia capa de tema
(`tema-por-instalacion.md`: seis mecanismos, `[data-surface]`, `theme.action` en el panel). **Choques ya
visibles**, medidos en §1.6.3: la columna, el mínimo táctil, la escala de radios, los **iconos** (Lucide
contra `IconSetAnatomyTest`, resuelto en `#683`) y las **fuentes** (Google Fonts por `@import`: se sirven desde
el propio dominio, porque cargarlas de Google envía la IP del visitante).

### 1.5 Las URLs cambian

La landing de hoy sirve nueve vistas (`instancias/playjump/web/`): portada, atracciones, bar, contacto,
cumpleaños, legal, normas, precios y servicios. El diseño ordena la web por comprador: portada, Cumpleaños,
Kids, Jump, Colegios, Normas y seguridad, Visítanos y las legales. **Rutas como `/precios`, `/atracciones` o
`/bar` desaparecen**: cada una necesita su 301 al sitio nuevo o se pierde lo que Google ya tiene indexado.

### 1.6 La T0, medida (2026-09-24): Kids y Jump, los tokens y las URLs

Medido contra la API en local (`curl` a `/api/v1/*` con la BD de desarrollo) y contra los briefs de Kids y
Jump y su `paginas/entradas/contenido.js`. Las cifras de los briefs «vendrán del panel»: esta tabla dice de
dónde. **HAY** = la API lo da · **FALTA** = no existe como dato · **INSTANCIA** = texto o material de PlayJump.

#### 1.6.1 Cada dato de Kids y Jump, con su fuente

| Dato (pieza) | Fuente | Veredicto |
|---|---|---|
| Precio de 1 h, 2 h e ilimitada por tarifa (1, 3) | `/prices`: `products[].prices[]` con `rate` normal/especial | **HAY**; la ilimitada solo trae tarifa normal, y eso ya dice «de lunes a jueves» |
| Qué días son tarifa especial (3) | `/prices.rates[].weekdays` y, día a día, `/availability/{product}/dates` (`rate_key`, `price_cents`) | **HAY**: el punto del calendario está servido |
| «Hoy, 1 hora cuesta…» (1, 6) | `/availability/{product}/dates` del día | **HAY** |
| «Quedan huecos esta tarde» (1, 6, isla) | `/availability/{product}/times` del día (`available`) | **HAY**, con una lectura por producto; sin agregado |
| Horario y festivos (6) | `/schedule` (`weekly`, `special_days`) y `/schedule/now` | **HAY** |
| Dirección, «Cómo llegar», teléfono (6, 8) | `/site` (`address.written`, `address.maps_url`, `contact.phone`) | **HAY**; WhatsApp usa el mismo número (no hay campo propio) |
| Nota y número de reseñas (1, 5, 8) | `/social-proof` | **HAY** (vacío en local); la **lista** de reseñas aún no se publica: **PARCIAL** |
| Las 23 atracciones: nombre, línea y foto (4) | `/attractions` (15 de Jump, 8 de Kids) | **HAY**; el orden, las destacadas y los vídeos son **INSTANCIA** |
| Edad y altura de cada zona (1, 5, 7) | `/catalog/zones` (`age_range`, `height`) | **HAY**, pero ver §1.6.2 |
| Duración del producto | `/catalog/products` (`duration_min`) | **HAY** |
| Calcetines a 2 € (3, 7) | `POST /catalog/products/{product}/addons` | **HAY** al resolver la selección |
| Total de la calculadora (3) | `/orders/quote` | **HAY** |
| **Precio de antes (tachado) y texto y fecha de la oferta** (1, 3, 8, isla) | Ninguna: la tabla `prices` ya guarda el precio rebajado y solo las vistas Blade de hoy reconstruyen el de antes (`WritesLandingValues::antes()`) | **FALTA** → las promociones (`#684`) |
| **Plazo de cambio y cancelación**: 24 h en entradas, 5 días en cumpleaños (2, 3, 7) | Solo como frase fija del producto (`pay_policy` en `lang`) | **FALTA** como dato; y hay que medir qué regla lo hace cumplir |
| **Los menores de 4 entran con un adulto desde 90 cm** (1, 3, 5, 7) | `height` de Kids solo trae el máximo (150 cm) | **FALTA** |
| JumpPoints (3) | Ningún ajuste lo enciende | **A MEDIR** con `lealtad-jumppoints.md` |
| «Mínimo 3 monitores», parking gratis, cafetería (5, 6) | — | **INSTANCIA** (texto de PlayJump) |
| Los textos de cada pieza, las dudas y el SEO (título, descripción, imagen) | — | **INSTANCIA**, con las cifras de dentro sacadas de sus hechos |

▶ **La regla de los textos**: las frases de los briefs son de la instancia, pero **cada cifra que llevan sale
de su hecho** («2 €» del complemento, «1,30 m» de la zona, «24 h» del plazo). Una cifra tecleada en una frase
se queda vieja la primera vez que alguien cambie el panel.

#### 1.6.2 Datos del panel que contradicen al diseño

- ⚠️ **Kids es «4 — 8 años» en el panel** (`age_range` de la zona y los `features` de sus tres productos: «De 4 a
  8 años»), y **de 4 a 7 en todos los briefs**. `[DECIDIDO owner]` 2026-09-24: **de 4 a 7**; un menor de 4 que
  mida más de 90 cm entra con un adulto, **el niño paga su entrada Kids y el adulto entra gratis** (lo que dice
  el brief: «Cuenta también a los menores de 4»). ✅ Corregido por el owner en producción y aquí en local
  (zona y los tres productos, en es/en/fr; el inglés ya decía «Ages 4 to 7»).
- Los productos ya llevan una etiqueta: `badge` = «−20 % online» en `/catalog/products`. Es el germen de las
  promociones (`#684`), igual que `gifts`.
- Los briefs dan **155 reseñas** en Kids y Jump y **148** en Cumpleaños y la portada: con la cifra de la API, las
  dos páginas dirán la misma.
- El README del diseño pone de ejemplo calcetines a «2,50 €» y los briefs a «2 €»: manda el complemento.

#### 1.6.3 Los tokens: los dos vocabularios, medidos

El producto declara **164** nombres entre `public/css/site.css` y `client.css`; Saltia, unos **200** en sus siete
ficheros. Por familias:

| Familia | Producto | Saltia | Lectura |
|---|---|---|---|
| Columna | `--col-max` 1176 por defecto; **1120** en el `client.css` de PlayJump | `--container` 1240 | Valor de la instancia: cabe sin tocar el producto |
| Táctil | `--tap-min` 48 | «nunca por debajo de 44»; controles de 40/48/56/64 | **Se queda 48**: es el suelo del producto y el de 40 se agranda |
| Radios | 4 valores distintos (10, 16 y píldora; `ShapeScaleTest`) | 7 (6, 10, 14, 20, 28, 36 y píldora) | **Choca**: la escala es del producto; la T1 decide si se amplía |
| Sombras | 3 roles (`lift`, `float`, `modal`) | una escala (`xs`→`lg`) más `island` y `cta` | La isla necesita `island`; el resto se mapea a los roles |
| Movimiento | 8 duraciones y 4 curvas con nombre propio (`--dur-entra`…) | 6 duraciones y 4 curvas (`--dur-island`, `--ease-spring`…) | Valores de la instancia sobre los roles del producto |
| Fuentes | 4 roles (`display`, `body`, `mono`, `accent`) | 3 (`display`, `ui`, `mono`) | Encajan; `accent` queda vacío |
| Color | superficie, tintes y semánticos (`--bg`, `--tint-*`, `--ok`…) | primitivos más alias (`--control-*`, `--notice-*`, `--text-*`, `--surface-glass*`, `--scrim-*`, `--band-*`) | Los alias de control y aviso son lo que la isla necesita sobre tinta: **roles nuevos del producto** |

▶ **La estrategia para la T1**: la isla (producto) se escribe contra **roles del producto**, y los que le
faltan (control, aviso, cristal, velo, carga) los declara el producto con nombre genérico y un valor neutro.
El `client.css` de PlayJump pone los primitivos de Saltia y asigna los roles. Las páginas de la instancia
pueden usar los alias de Saltia directamente, porque son su CSS.

#### 1.6.4 Las URLs: de las de hoy a las nuevas

El idioma no va en la URL (lo lleva `lang/{locale}`), así que hay un mapa, no tres. El sitemap de hoy sirve 11
URLs; `/atracciones`, `/bar` y `/entradas` existen fuera de él.

| Hoy | Nueva | Qué pasa |
|---|---|---|
| `/` | `/` | La portada nueva |
| `/cumpleanos`, `/normas` | Igual | La página nueva, en su sitio |
| Las cinco legales (`/privacidad`, `/condiciones`, `/waiver`, `/cookies`, `/aviso-legal`) | Igual | Sin cambio de URL |
| `/contacto`, `/bar` | `/visitanos` | 301: la dirección, el horario, el contacto y la cafetería viven ahí |
| `/precios`, `/entradas`, `/atracciones` | `/` | 301 **propuesto** a la portada, que reparte hacia Kids, Jump y Cumpleaños |
| `/servicios` | `/colegios` | 301 cuando exista Colegios; hasta entonces sigue su vista de hoy |
| — | `/kids`, `/jump` | Nuevas, y las primeras en la T4 |

▶ Los 301 «propuestos» son de SEO y se pueden afinar: `/precios` podría llevar a Kids o a Jump según lo que
Google tenga indexado, y eso se mira en Search Console antes de fijarlos.

## 2. Objetivo y criterios de éxito

**Objetivo**: la web de PlayJump con el sistema nuevo, en su instancia, y la isla como carcasa del producto que
PlayJump enciende.

1. **La isla, apagada, no cambia nada**: la suite pasa con la carcasa del cajón y con la de la isla, y la huella
   de maquetación del cajón da 0 en sus pantallas.
2. **Se compra por la isla**: una entrada y un cumpleaños con señal, de principio a fin, en local y en staging
   (`/sonda`, con la pasarela de pruebas).
3. **Rápida y accesible** (brief base, regla 11): el contenido principal en menos de 2,5 s en móvil, medido en
   staging, y contraste AA.
4. **Cero marca en el producto**: el criterio de `#639` («cero marca» en el código vivo) se sigue cumpliendo.
5. **Ninguna URL indexada se pierde**: cada ruta vieja responde 301 a la nueva, con test.

**Fuera**: el A/B (T5 de la analítica, carril del SPA, `#735`) · los correos (su brief es del carril de
correos) · el post-form, la invitación y el justificante (briefs propios, después) · Colegios · la app.

## 3. Opciones consideradas

### 3.1 Las páginas (`#681`)

| Opción | A favor | En contra |
|---|---|---|
| **A · Blade en la instancia** ✅ | Una tecnología, un despliegue, un gate. El mecanismo existe (`instancia::`, `SEC-12`). HTML del servidor para Google y el móvil. Mismo dominio. Un cambio del panel sale solo en minutos. | La web va atada a la versión del producto; la instancia necesita sus propias pruebas. |
| B · Astro estático | Despliegue independiente, CDN. | Producción no tiene Node: datos horneados, y con despliegues de noche un precio tarda horas. Se salta `SecurityHeaders`, `NoStoreWebResponses` y `ResolveVisitor`. Una segunda subida esquivando el `rsync --delete` y la guarda 9. |
| C · Nuxt o Next con servidor | Páginas del servidor con interactividad. | Exige Node en producción. |
| D · Otro proveedor para la web | Regeneración automática. | Otro proveedor y un proxy delante del dominio, que reabre `SEC-13`. |
| E · Constructor en el panel | El cliente edita solo. | Descartado en `#611`; webs genéricas. |

### 3.2 La isla (`#682`)

| Opción | A favor | En contra |
|---|---|---|
| **A · Segunda carcasa del producto, apagada por defecto** ✅ | La compra sigue bajo el gate; un solo motor; cada cliente elige; el riesgo se mide. | Dos carcasas que mantener (se acota compartiendo el contenido de los pasos, §4.1). |
| B · Solo en la instancia, el SPA oculto | Libertad total. | La compra fuera del gate y dos compras que mantener. |
| C · Isla para todos | Una sola carcasa. | Impone un diseño arriesgado a todos los clientes. |
| D · Reescribir en React | Los componentes del diseño casi tal cual. | Se tira el motor y sus tests. |

## 4. Diseño elegido

### 4.1 Motor y carcasa

- **El motor** son los stores, la máquina, `api.js`, `i18n.js` y las reglas (`buyer-due.js`, `admission.js`…).
  No cambia de dueño ni de forma.
- **La carcasa** es el contenedor y lo que solo él sabe: el cajón (`Shell.vue`, `shell.js`) o la isla, en
  `resources/js/isla/` (futuro). La elige un ajuste por instalación, `sidebar.shell` = `cajon` | `isla` (futuro),
  que viaja en `/sidebar/boot` porque es igual para todos los visitantes. La variante del A/B, que es por
  visitante, viaja en `/sidebar/session` (`analitica.md` §4.4).
- **Los pasos de la isla sobre la máquina**: «Cuándo y cuántos» = `DATE` + `TIME` + cantidades · «Tus datos»
  = `IDENTIFY` · «Pagar» = `CART` + `PAY` (el recibo es editable sin salir) · «Listo» y los desenlaces =
  `CONFIRMED`, `DECLINED`, `VERIFYING`. Hay que medir en la T3 si la máquina admite ese orden sin tocar sus
  transiciones.
- **Para acotar el coste de dos carcasas**, el contenido de cada paso se escribe una vez y lo montan las dos.
  `[DECIDIDO owner]` 2026-09-24 (`#683`): el cajón clásico **adopta los pasos nuevos** dentro de su lateral.

### 4.2 Las páginas en la instancia

- Vistas Blade y componentes Blade **de la instancia**: `VideoHero`, `ZoneCard`, `RateTable`… son presentación
  de PlayJump. No se copian componentes del producto (README de la plantilla).
- **La instancia declara sus páginas** en `instancias/playjump/config/paginas.php` (futuro), propuesta: slug,
  vista, hechos que consume y redirecciones 301 de las rutas viejas. El producto las sirve con un controlador
  genérico dentro del grupo `web` (cabeceras, `no-store`, visitante). Así «kids» o «jump» no son rutas del
  producto: son nombres de un cliente.
- **Los datos que recibe una vista** son los de los recursos públicos del menú, los mismos que sirve la API,
  resueltos en el servidor. Sube `InstanceViews::CONTRATO` (hoy 2).

### 4.3 El contrato entre la página y la isla

El prototipo encuentra los botones por su texto («la etiqueta es lo único estable»). En nuestras páginas el
marcado es nuestro, así que el contrato es **declarativo en el HTML** (el «kit declarativo» que `#632`·P2
aplazó; ahora tiene cliente):

- `data-isla-pagina` en el `<body>`: tipo, producto, acción y «desde» (situación 2).
- `data-isla-frase="…"` en cada sección que quita un miedo (situación 5).
- `data-isla-cta` en cada botón principal: con uno a la vista, la isla cede su acción (regla 1 del diseño).
- `data-isla-widget` donde va la calculadora de la página (§4.4).
- Un evento para abrir la isla desde la página (el `pj-island:open` del prototipo).

Los nombres son propuesta de esta spec, a fijar en la T2.

### 4.4 La calculadora de la página

El calendario, las horas, las cantidades y el resumen que el diseño pone dentro de cada página de producto
**son del producto**: componentes Vue del mismo paquete, montados en el `data-isla-widget`, que comparten store
con la isla. Así «Reservar para hoy» llega a la isla con el día elegido. **El total nunca se calcula en el
navegador**: sale de `/orders/quote`. El propio diseño da el motivo: con calcetines y el −20 %, la cuenta
ingenua da 73,60 € y la real 74,40 €.

### 4.5 La lógica nueva, que entra por la API antes que por la isla

| Pieza | Situación | Estado | Nota |
|---|---|---|---|
| Pieza | Situación | Estado | v2.0.0 (`#683`) | Nota |
|---|---|---|---|---|
| Promociones con fechas (la fecha de fin incluida) | 4 | FALTA | Spec propia (`#684`) | Etiquetas de texto e icono en producto, pack o complemento, y un aviso arriba |
| Bizum | 11 y Pagar | FALTA | **Entra** | Dinero: `INVARIANTES` §1, `REDSYS.md`, `VERIFY_CONC=1` |
| Entrar con Apple | Tus datos | FALTA | **Entra** | Un proveedor más junto a Google (`auth-con-google.md`) |
| Aviso de día liberado | 8 | FALTA | **Entra** | Es un corchete: puede faltar sin dejar hueco |
| Cálculo guardado y compartible | 9 | FALTA | Después | Sin servidor si el enlace lleva los parámetros |
| ¿Quedan huecos hoy? | 3 | PARCIAL | Con la T2 | Un agregado de lectura; `PERF-02` |
| Tareas con plazo por reserva | 13 | A MEDIR | Con la T5 | Los plazos existen repartidos por sus specs |

Lo que no entre se queda como corchete apagado, sin dejar hueco.

### 4.6 El tema

Los valores de Saltia son de PlayJump y van a su `tema/client.css`. Los **roles** que el producto no tenga los
declara el producto con nombre genérico, y el mapa rol a rol se hace en la T0. Las fuentes se sirven desde el
dominio (en `publico/` de la instancia). Los iconos, `[DECIDIDO owner]` 2026-09-24 (`#683`): **Lucide**,
metidos en el HTML al construir y solo los que se usan, en la isla y en las páginas; `IconSetAnatomyTest`
deja de decir «nada de otra librería» en la T1.

### 4.7 Las tandas (propuestas)

| Tanda | Qué | Dónde |
|---|---|---|
| T0 ✅ | Kids y Jump contra el menú de hechos, los tokens y las URLs (§1.6). Cumpleaños y la portada, con su tanda | Esta spec |
| T1 ✅ | El tema: la referencia, las fuentes, la hoja de tokens y los iconos (§4.8); los roles nuevos del producto van con la isla (T2) | Producto + instancia |
| T2 ✅ | La isla en Vue, idéntica al diseño en 26 situaciones (§4.9); los datos reales, con la T4 | Producto |
| T3 | La compra en la isla sobre el motor, con tarjeta; sonda de compra (§4.10: T3a→T3d ✅ → T3e) | Producto |
| T4 | **Kids y Jump** (`#683`), un molde y dos páginas, con su calculadora y sus 301 | Instancia + producto |
| T5 | Mi cuenta en la isla | Producto |
| T6 | El resto de páginas y la lógica nueva que apruebe el owner | Los dos |

Después, la v2.0.0 (`#670`): con la isla encendida para PlayJump, y el A/B cuando la T5 de la analítica exista.

### 4.8 La T1, en cuatro pasos — y cómo se demuestra «idéntico» (`#685`)

El owner lo pide «píxel por píxel, idéntico, sin falta». Eso no se juzga a ojo: se juzga con **`scripts/pixel.mjs`**,
que pinta la referencia (A) y lo nuestro (B) en el mismo Chromium, con la pieza asentada, y cuenta los píxeles
distintos. Idéntico = **0**. El arnés tiene control negativo: una B sin fuentes da 97.956 distintos y sale con 1.

- **T1a · la referencia, byte a byte** ✅ (24-09): `instancias/playjump/diseno/playjump-design-system/`, del zip
  de las 06:19:20 (398 ficheros, 72 MB; instancia `b0a3632`), puesta por `diseno/actualizar.py` con su
  `sha256sum -c`. Contrastada con el proyecto VIVO: `readme.md` idéntico y los 262.144 B que da `DesignSync` del
  compilado son el principio exacto de sus 753.287. Las fichas se pintan en Chromium sin un error.
  ⚠️ **`DesignSync` no sirve para esto**, medido:
  `_ds_bundle.js` (los 71 componentes compilados, que es lo que pinta cada ficha) llega cortado en 262.144 B,
  justo 256 KiB, y pierde el final, donde vive `ParkIsland`; el vídeo y el logotipo pasan del tope igual. La vía,
  `[DECIDIDO owner]` 24-09: **un zip descargado de Claude Design**, con los nombres y las rutas originales. El
  artifact «Design System» descartado: su migración renombra tres ficheros (`_ds_bundle.js` entre ellos) y
  rehace la hoja de tokens para su visor, que es justo lo que el owner vio «romperse» el 20-09. El espejo vive en
  la instancia (`instancias/playjump/diseno/`, futuro) con un manifiesto de sha256, y se contrasta con
  `DesignSync`: los primeros 262.144 B del compilado tienen que coincidir con lo que entrega el MCP.
- **T1b · las fuentes** ✅ (24-09). Los nueve `woff2` que Google entrega al `@import` del diseño (Archivo
  variable en peso y anchura, Figtree variable, DM Mono 400 y 500; 290 KB), servidos desde el propio dominio
  —la CSP ya admite `font-src 'self'`— y no desde `fonts.bunny.net` como `ThemeFonts`: Bunny sirve pesos
  sueltos y los héroes usan el eje de anchura. Los trae `tema/traer-fuentes.py` de la instancia (repetible:
  dos descargas, mismos sha256) y los deja en `publico/instancia/`. **Medido**: la muestra de las nueve caras
  (`tema/muestra-fuentes.html`) con las de Google y con las propias da **0 píxeles distintos** a 390×844 y
  1440×900, y **0 de 4.578.120** a densidad ×3.
- **T1c · la hoja** ✅ (24-09). `tema/construir-hoja.py` de la instancia escribe `publico/instancia/css/saltia.css`
  (30 KB): los siete ficheros de tokens TAL CUAL, en el orden de su `styles.css`, con las fuentes propias en lugar
  del `@import` de Google (el único cambio, y comprobado). **Medido**: las **82 páginas** del diseño que cargan
  `styles.css` (22 guías, 9 fichas de componentes, 31 secciones, 6 páginas montadas, 8 hojas de revisión y 6
  más) servidas con su hoja (A) y con ésta (B, `scripts/pixel-referencia.php` cambia solo el enlace) → **82 de
  82 con 0 píxeles distintos**; 77 a la primera y 5 al reintentar.
  ▶ **El layout limpio sale de la T1c**: un layout sin página que lo use sería código muerto. Nace con su
  primera página (T4) y lleva las hojas nuevas SOLO: nada de `landing.css`, `site.css` ni el `client.css` viejo.
  ⚠️ **El instrumento tuvo que madurar antes de fiarse de él**, y cada paso tiene su trampa escrita en
  `scripts/pixel.mjs`: comparada consigo misma, la referencia daba 11 páginas distintas, y ninguna por la
  hoja. Las causas, medidas: el reloj (páginas que dicen «hoy»; `--reloj`), el `fullPage` que agranda la
  ventana a mitad de la foto y cambia el estado de la isla, su re-medida a los 420 ms (quietud de un segundo),
  el `localStorage` compartido entre capturas (un contexto por captura), los `iframe` de las hojas de revisión
  sin asentar, y el reloj de la RED: la fuente de Google llega a los 215 ms y mueve medio píxel el borde de un
  botón (lo externo se sirve desde una caché en memoria).
- **T1d · los iconos** ✅ (24-09, `#686`): `lucide-static@0.544.0`, la versión exacta que carga el diseño,
  versionado en `resources/icons/lucide/` por `scripts/traer-lucide.py` (integridad `sha512` de npm comprobada;
  sin tocar `package.json`, que es compartido). `<x-lucide>` y `Lucide::svg()` repiten `Icon.jsx`: mismas
  sustituciones, solo en la primera aparición, mismo `<span>` y ni un espacio alrededor. **Medido** con
  `scripts/banco-lucide.php`: los 84 iconos que usa el diseño a 16, 20 y 24 px, más relleno, nombre, chip y
  color, contra el `Icon` del diseño → **0 píxeles distintos** en 1280 y 390; un trazo alterado da 99 y código 1.
  `LucideIconTest` (9 casos) con sus mutaciones en rojo. `IconSetAnatomyTest` sigue guardando el set ANTIGUO.

### 4.9 La T2, en cuatro pasos: la isla en reposo, idéntica y del producto

La fuente es `components/navigation/ParkIsland.jsx` de la referencia (968 líneas) y lo que usa: `Icon`, `Button`
(`quiet`), `Link`. Se porta **1:1 a Vue** —la misma tabla de prioridades, la misma medida y el mismo morfeo, el
mismo árbol y los mismos estilos en línea—, para que un cambio del diseño se traslade comparando fichero con
fichero. Lo que la isla hace en la compra (situación 10), en Mi QR (su `QrPass`) y en el pago fallido va con la
T3 y la T5, que es cuando tiene detrás el motor; el componente deja su sitio hecho.

- **T2a · el contrato**: la isla es del producto y **no puede nombrar la paleta de un cliente**. Medido
  (`grep` de sus `var()` y literales): usa seis primitivos de PlayJump (`--snow` 14 veces, `--volt-500` 10,
  `--ink-900` 4, `--flare-400` 3, `--flare-500` 2, `--aqua-400` 2) y siete colores de marca escritos a mano
  (la sombra y el velo en `rgb(9,46,74)`, el borde de alerta naranja, el aviso y el destacado en lima). Pasan a
  **roles `--isla-*`** que el producto declara con un valor neutro y que PlayJump asigna a sus valores exactos
  en su instancia. Lo que ya es rol en Saltia (`--action-bg`, `--text-muted`, `--r-pill`, `--dur-slow`…) se
  queda con su nombre, con respaldo en el producto. Los `@keyframes` del diseño se llaman `pj-*`: en el
  producto, `isla-*`, con la misma definición. Los textos van a `lang/{es,en,fr}`; el español, el del diseño.
- **T2b · el componente**, en `resources/js/isla/` (futuro): la lógica pura (qué situación manda) con sus
  pruebas de `node --test`, y las piezas en Vue.
- **T2c · el juez**: un banco con la isla del diseño (A) y la nuestra (B) con las MISMAS props, por situación
  (el «desde», las cuatro de «hoy», la frase de un miedo, la oferta, cediendo la acción, compacta, con el
  menú, el selector de plan, la ayuda, las cookies y un aviso), abajo a 390 y arriba a 1280. Idéntico = 0.
- **T2d · los datos**: de dónde sale cada prop en una página real (el «desde» de `/prices`, «hoy» de
  `/schedule/now` y `/availability/{product}/times`, la frase de `data-isla-frase`, la cesión de `data-isla-cta`).
  Se monta de verdad con la primera página (T4), que es su consumidor.

**Hecho el 24-09 (`#687`)**: T2a, T2b y T2c ✅; la T2d va con la T4.
- **El contrato**: 13 roles `--isla-*` y los respaldos de lo que ya era rol, todo en `:where()` (pesa cero) en
  `resources/js/isla/isla.css`; los valores de PlayJump, en `publico/instancia/css/isla.css` de su instancia;
  los movimientos, `isla-swap`, `isla-pulse` e `isla-fade-in`. Los textos, en `lang/{es,en,fr}/isla.php` (47
  claves; el inglés y el francés, propuestos y **a revisar por el owner**); las horas llegan con `:hora`, que el
  diseño escribía a mano («a partir de las 16:30»).
- **El componente**: `resources/js/isla/` — la lógica pura en `situacion.js` (17 pruebas de `node --test`), la
  isla en `IslaFlotante.vue` y sus piezas en `piezas/` y `ui/` (el `Icon`, el `Button` con sus variantes de rol y
  el `Link`). ⚠️ Dos cosas del port que muerden en silencio: React pone `px` a los números de los estilos y Vue
  no (un `width: 46` sería una declaración inválida, ignorada), y la plantilla de Vue convierte un salto de
  línea entre un texto y un elemento en un espacio que JSX no deja. `IslaTextosTest` (mismas claves en los tres
  idiomas, ninguna clave pedida que no exista, ningún marcador perdido; tres mutaciones en rojo). ESLint limpio.
- **`CE-6` sin excepción** (el techo de 40 líneas de código por `.vue` de `SidebarComponentBudgetTest`): el port
  de una pieza tenía 285 y el botón 72. Ahora `IslaFlotante.vue` solo pinta (el JSX del diseño, y la plantilla
  quedó idéntica byte a byte); el estado y los efectos, en `useIsla.js`, que llama a `useColocacion.js`,
  `usePaneles.js`, `useMorfeo.js` y `useAviso.js` en el orden del port (los `onMounted` y los `watch` corren
  en el orden de registro); sus estilos, en `forma.js` (6 pruebas); sus props, en `props.js`; los del botón y
  el enlace, en `ui/estilos.js`. El banco, tras el cambio: 52/52 a 0 píxeles y los 52 a la primera; control
  negativo (1px de relleno en `forma.js`): 11.885 píxeles y código 1.
- **El juez**: `scripts/banco-isla.php` escribe, por situación, la isla del diseño (A) y la nuestra (B, compilada
  aparte por `scripts/banco-isla/vite.config.mjs`) en el mismo marco, sobre una foto del parque; las situaciones
  viven en la instancia (`tema/isla-situaciones.json`). **26 situaciones × abajo 390 y arriba 1280 = 52 pares,
  52 con 0 píxeles distintos** (50 a la primera, 2 al reintentar): el «desde» de cinco páginas, las cuatro de
  «hoy», la acción cedida, dos miedos, la oferta, compacta, cookies, aviso, calculado y su resumen abierto,
  elegido, a medias, tarea, pago fallido, reserva de hoy, el menú sin y con sesión, la ayuda y el selector de
  plan. Control negativo: un solo rol cambiado (`--isla-vivo`) da 60 píxeles y código 1.

**`public/instancia/`** es desde la T1b el sitio del material NUEVO de una instalación (fuentes, hojas y medios
de sus páginas): se copia desde `publico/instancia/` del paquete, lo ignora git y lo excluye el `rsync`
(`DeployScriptGateTest` lo exige; mutado: sin la exclusión, rojo). Una carpeta para todo lo que venga, en vez
de una exclusión por fichero como el paquete de tema.

### 4.10 La T3: la compra en la isla — el censo (MEDIDO 24-09) y el plan

**La fuente**: el brief de la compra y su diseño, `paginas/compra/*.jsx` montado en `isla-compra.card.html` (un
banco que hace la compra entera con datos de prueba y la pasarela simulada). ⚠️ **El diseño corrige al brief**,
por decisión del owner del 23-09 escrita en su `datos.js`: se entra con **contraseña, Google o Apple** (sin
código al teléfono), **sin registro exprés** (los adultos se registran desde casa o en el mostrador) y en Listo,
de la cuenta, solo «Tu cuenta ya está creada con tu correo». Manda el diseño.

| Pieza de la compra | Lo que pide | El motor hoy | Veredicto |
|---|---|---|---|
| Pantalla 0 · cuándo y cuántos | Día, horas, tiempo, cantidad, calcetines, [Hora extra], la otra zona | `/availability/{product}/dates` y `/times`, los productos de la zona (`/catalog`), sus complementos, `cart.js::addLine` | **HAY**: las filas de «tiempo» son los productos de la zona |
| Tus datos, sin sesión | Nombre, correo, teléfono, contraseña, la casilla del descargo, Google, «¿Ya has venido? Entra» | `POST /auth/register` (con `accept_waiver`: la aceptación en espera, firmada al pagar), `POST /auth/login`, Google | **HAY**; Apple **FALTA** (corchete apagado) |
| «Esta cuenta ya existe» | Al teclear el correo | El alta lo dice **al enviar** (`#31a`, 3 por hora y correo) | **Al enviar** (`#688`) |
| Tus datos, con sesión | «Hola, Ana»; el teléfono si falta en cumpleaños; la casilla si nunca firmó | `/me`, `buyer-due.js` (`phone`), `account/waiver.js` | **HAY** |
| Pagar · el recibo | Cantidades y calcetines sin salir; «Añadir otra entrada» | La cesta y `/orders/quote` | **HAY** |
| Pagar · «Tu hora queda guardada hasta las 18:42» | La retención ANTES de pagar | La retención nace con el pedido, en `POST /orders`, que en el mismo acto abre el cobro (`AFORO-10`, `#37`) | **Al pagar** (`#688`): la línea no sale en «Pagar» |
| «Esa hora ya no está libre. Estas sí» | Al continuar | El aforo se comprueba en `POST /orders` (`line_sold_out`); las cercanas, de `/times` | **HAY, al pagar** (`#688`) |
| Pagar con tarjeta · saliendo al banco | El formulario a la pasarela | `pay.js::runConfirm` y el paso `REDIRECTING` | **HAY** |
| Bizum · Apple Pay y Google Pay | Botón y marcas | — | **FALTA** (v2.0.0, `#683`): corchete apagado, sin hueco |
| Pago no completado | Con el motivo del banco; «Pagar con nuestra ayuda» y «Escribirnos» | `DECLINED` con `declined_reason`; el contacto de `/site` | **HAY** |
| Verificando | | `VERIFYING` y su sondeo | **HAY** |
| Hora perdida al volver | Y las horas cercanas | Pedido `expired` (`pollVerdict`, `order_not_retryable`) | **PARCIAL**: las cercanas son pantalla nueva sobre `/times` |
| Listo | La línea, el QR, «Guardar en el móvil», las tareas | `CONFIRMED`; el carné (`GET /me/card/png`, los bytes del correo) y su descarga; menores a cargo, post-form, invitación | **HAY**; el QR real es el del servidor, `QrPass` pinta su marco |
| La dirección de cada paso | `#compra/datos`…, y «atrás» del navegador | — | Carcasa, sin servidor |

**La máquina no cambia**, y se prueba en la T3e: «cuándo y cuántos» es `DATE`/`TIME`; «Continuar» mete la
línea (`TIME → CART`); «Tus datos» es `IDENTIFY` (o se salta con sesión: `CART → PAY`); «Pagar» es `PAY` con la
cesta editable; «atrás» es `PAY → CART → IDENTIFY`. Todas esas transiciones ya existen.

**La secuencia vivía en `PurchaseSection.vue`** (1.391 líneas, 464 de guion con su excepción de `CE-6`):
elegir, añadir, admitir, alta y acceso, confirmar, desenlace, sondeo y reintento. La isla necesita LA MISMA, y
copiarla serían dos sitios donde recordar cada ⚠️ de esa secuencia. **Se extrajo a un módulo del motor** que
usan las dos carcasas, sin cambiar la conducta del cajón (sus pruebas, su contrato de árbol y su sonda lo
vigilan) y encogiendo su excepción. Es un fichero del carril del SPA: se avisó en su buzón antes de tocarlo.

**Las tandas de la T3**:
- **T3a ✅** el censo y el plan (esto).
- **T3b ✅** las 14 piezas del sistema que usa la compra, en Vue e idénticas: `Field`, `Checkbox`, `InfoCallout`,
  `TimeSlotPicker`, `DayStrip`, `OptionCards`, `QuantityStepper`, `PriceSummary`, `SocialSignIn`,
  `OutcomeHeader`, `QrPass`, `TaskCard`, `Skeleton` y `BounceLoader`, más los iconos del `Button` y del
  `Link`. Un banco con cada pieza en sus estados, sobre claro y sobre tinta → 0 píxeles.
- **T3c ✅** el tamaño «Compra» de la isla y sus pantallas, idénticos a `isla-compra.card.html` recorriendo su
  banco con clics y con sus mismos datos. Las pantallas reciben todo por props: no conocen el motor.
- **T3d ✅** la secuencia compartida, extraída de `PurchaseSection.vue` a `sidebar/usePurchaseFlow.js`.
- **T3e** la isla compra de verdad con tarjeta: el cableado, la prueba del orden de la máquina y `/sonda` en
  local con la pasarela de pruebas. Su plan, medido y en seis sub-tandas, abajo (`#692`).

**T3b hecha el 24-09 (`#689`)**:
- **Las piezas**, en `resources/js/isla/ui/`: `CampoSistema`, `CasillaSistema`, `SelectorHoras`,
  `TarjetasOpcion`, `ContadorCantidad` (con `ControlesCantidad`), `ResumenPrecio`, `AvisoDestacado`,
  `TiraDias`, `AccesoSocial`, `CabeceraDesenlace`, `PaseQr`, `TarjetaTarea`, `EsqueletoCarga` y `CargaRebote`;
  el botón gana su bola de carga y el registro de iconos pasa a 32. Lo que decide, en `ui/piezas.js` y
  `ui/qr-muestra.js` (11 pruebas). **Su API es la de Vue**: lo que en React era un elemento en una prop es una
  ranura, y `value`/`checked` es `v-model`; la tabla de equivalencias es `scripts/banco-piezas/entrada.js`, y el
  juez la comprueba. Sin el `icon` por opción de `OptionCards` (ninguna pantalla lo usa). El QR real es la imagen
  del carné (`src`); el de muestra del diseño, solo sin ella.
- **Del producto, no de PlayJump**: 11 roles nuevos (`--isla-obligatorio`, `-franja-peligro`, `-especial`,
  `-error-fondo`, `-fiesta-1…4`, `-qr-fondo`, `-qr-tinta`, `-qr-sombra`) y el respaldo de todo lo que las piezas
  llaman por su función (letra, forma, movimiento, controles, avisos, carga), sobre claro y sobre tinta: con
  `isla.css` solo, la isla se ve entera y neutra. **Medido**: ningún nombre que comparte con las hojas del
  producto queda sin declarar en ellas, así que cargarla no cambia nada fuera. Los textos que las piezas
  escribían a mano, al grupo `pieza` de `lang/*/isla.php` (16 claves; en/fr a revisar).
- **El juez**: `scripts/banco-piezas.php` pinta cada pieza en sus estados, en claro y en tinta, con los casos
  de `tema/piezas-compra.json` de la instancia (los de las pantallas de la compra): **16 páginas y 2 con clics
  (el foco y ver la contraseña) = 18 pares, 18 con 0 píxeles distintos** (17 a la primera; el otro, una primera
  captura anómala del lado A, cuyo hash al repetir es el de B). Controles negativos: 1px de relleno en la casilla
  da 27.997 y un rol cambiado (`--isla-especial`) 432, los dos con código 1. La T2 sigue en 52 de 52.
- ⚠️ `defineProps` no puede nombrar una constante del propio `<script setup>` (se eleva fuera de él): va en un
  módulo y se importa.

**T3c hecha el 24-09 (`#690`)**:
- **El tamaño «Compra»** de la isla (`piezas/CompraIsla.vue`): la raíz fija sobre la página (con aire en
  escritorio, a pantalla completa en móvil), la caja como diálogo, el medidor a 600px, la página quieta detrás,
  el foco al titular de cada paso con su anuncio y de vuelta a quien abrió, y Escape como la X
  (`useCompraCapa.js`); el botón de la acción gana su bola de carga.
- **Las pantallas**, en `resources/js/isla/compra/`: la pantalla 0 (`PantallaCuando`, `PantallaCuandoFiesta`),
  `PantallaDatos`, `PantallaEntrar`, `PantallaDescargo`, `PantallaPagar` (con `JuntoPagar`), `PantallaSaliendo`,
  `PantallaFallido` (con `JuntoFallido`), `PantallaVerificando`, `PantallaPerdida` y `PantallaListo`, sobre
  `PasoCompra`, `PreguntaCompra`, `DatoFijo` y `CantidadCompra`. **Pintan y avisan**: todo llega hecho —de
  dinero, ni una cuenta (`PAY-12`)—; los textos fijos, del grupo `compra` de `lang/*/isla.php` (el literal del
  diseño; en/fr a revisar) y lo que depende del parque (zonas, precios, preguntas del widget, plazos de las
  condiciones, sus calcetines), como dato. El recuadro rayado de la [Hora extra] y el texto del descargo son
  ranuras: el diseño deja un hueco y la compra real pone el control y el documento.
- **El juez**: `scripts/banco-compra.php`, 27 estados —los del §5 del brief (cada pantalla, con y sin sesión,
  errores, cuenta existente, hora llena, Instagram, entradas y cumpleaños, los cuatro desenlaces) y la pantalla
  0— abajo a 390 y arriba a 1280: **54 pares, 54 con 0 píxeles, todos a la primera**. A pinta con la vista del
  hook del diseño transcrita (`vista-diseno.jsx`) y B con el adaptador (`entrada.js`), que hace con los datos
  de prueba lo que la T3e hará con el motor; los dos parten del mismo estado (`estado.js`).
- **La trampa 8 del juez, medida**: con el MISMO DOM y el mismo estilo calculado —el HTML de las dos islas,
  reinsertado en la misma página, da 0— React y Vue dejaban de 4 a 1.007 píxeles de antialias distinto,
  estables en cada lado. `--rehacer` rehace las cajas antes de la foto (oculta y muestra el `body`, devuelve foco
  y desplazamientos). Opción y no defecto: en las hojas de revisión con `iframe` estropeaba dos páginas.
  **Regresión con el juez nuevo**: la isla (52/52) y las piezas (18/18), con `--rehacer`, todas a la primera; las
  82 páginas (sin él), los 84 iconos y las fuentes, a 0. ⚠️ Dos hojas de revisión («Así es la zona», «Dudas»)
  necesitan más intentos (`--reintentos 8`: cuadran al 3.º y al 4.º) porque su propia referencia no pinta igual
  dos veces (medido A contra A: 1.330 píxeles; su isla mide su ancho al llegar las fuentes). Controles negativos
  de la compra: un texto del `lang` cambiado da 923 píxeles y 1px de separación da 10, los dos con código 1.
- ⚠️ Vue deja un espacio entre `</template>` y un `{{ … }}` en la línea siguiente (no es texto entre dos
  elementos, así que no se quita): se escriben pegados.

**T3d hecha el 24-09 (`#691`)**:
- **La mudanza**: `usePurchaseFlow(props)` (`resources/js/sidebar/usePurchaseFlow.js`) lleva la secuencia entera
  —el montaje, la cesta, el día y la hora, la admisión, el alta y el acceso, el cobro, el sondeo y el reintento—
  **línea a línea**: comparadas las líneas de código de antes y de después, solo cambian las rutas de los `import`
  y faltan dos símbolos que nadie usaba (`minQuantityFor`, `tp`). Los stores van arriba y los observadores se
  registran en el mismo orden. En `PurchaseSection.vue` queda lo que es solo del cajón (la banda, el aviso de
  pausa, el pie y su reparto, «Volver», la puerta a la cuenta, el `ref` de la raíz) y su plantilla, idéntica byte
  a byte: **464 → 89 líneas de guion y 2 → 0 llamadas a la API** (`CE-6`).
- **Las guardas, reapuntadas**: `SidebarSignupContextTest` mira el alta en el módulo; `SidebarSetupBindingsTest`
  escanea además el cuerpo de todo `export function use…` (una muestra en su guarda de la guarda, y una mutación
  en el módulo real —el store declarado debajo de su `watch`— la pone en rojo con su línea). ESLint pierde dos errores
  congelados (10 → 8, podados en el mismo commit).
- **El peso**: el chunk del cajón sube **+1.256 B** (288,86 → 290,09 KiB): son los nombres que el composable
  devuelve y la sección desestructura. La poda obvia (la sección toma sus stores) ahorra 0,20 y no baja del
  techo; el techo pasa a 291 con su medida.
- **La prueba de que no cambia una conducta**, en navegador: `scripts/sonda-embudo.mjs` recorre la compra entera
  en el cajón —del catálogo al pago, la recarga con cesta, entrar en el paso 5, la salida al banco, la vuelta sin
  datos con un sondeo, el rechazo con su motivo y el reintento— y anota cada pantalla y cada petición. Con el
  build de antes y con el de después: **13 pasos, 39 peticiones, trazas idénticas**. Control negativo: sin la
  petición de los campos del pack al restaurar, el diff la señala.
- ⚠️ **Heredado, no arreglado** (se mudó tal cual): `loadOutcome()` lee `props.locale`, que la sección no
  declara, así que la hora de retención del paso 10 sale siempre con formato `es`. Está en el buzón del SPA.
- ⚠️ En local, un 500 al abrir el cajón con «Deadlock found» en el log es la caché en BD (`CACHE_STORE=database`)
  con el limitador escribiendo desde tres peticiones en paralelo: dos corridas de la sonda de seis. Se vuelve a
  correr; producción usa Redis (`#137`).

**T3e, el plan (24-09, MEDIDO; decisiones del owner en `#692`)**. Lo que el motor ya da, contra lo que pide el
guion del diseño (`paginas/compra/compra.jsx`, `usePjcCompra`):
- **Catálogo**: las «filas de tiempo» de una zona son sus productos (Kids 100/101/102, Jump 103/104); los
  calcetines, su complemento 110; los cumpleaños, dos packs de la zona `cumpleanos` (Kids 4–7, Jump 8+; 8 a 20
  niños; señal 50 €) con el menú como grupo de elección. La edad es su campo `celebrant_age`.
- **La máquina admite el orden**: pantalla 0 → `TIME → CART` (validar y añadir la línea); «Tus datos» →
  `IDENTIFY`, o nada con sesión; «Pagar» → `PAY`; la vuelta de «Pagar» a «Tus datos» son dos saltos que existen
  (`PAY → CART → IDENTIFY`). Pero **la pantalla 0 es un formulario, no un embudo**: el tiempo (el producto)
  cambia después de elegir la hora, y `selectProduct()` borra día y hora. La isla lleva su borrador y pide la
  oferta a los stores (`dateStore`, `timeStore`, `selectionStore`) sin pasar por esos pasos.
- **El alta con un correo que ya existe** responde 422 con el literal `account.register.already_exists` en
  `fields.email`, sin código legible: la isla lo reconoce comparando con el literal del grupo `account` que ya
  viaja en el arranque. **Entrar solo admite correo** (`LoginRequest`): «correo o teléfono» del diseño, a
  preguntar al llegar a «Entra».
- **El teléfono lo exige el servidor en toda compra** si la cuenta no lo tiene (`CheckoutDuties`) y **las
  condiciones**, con casilla, si no están aceptadas (`#349`). Tras `#692`: el teléfono en «Tus datos» siempre
  que falte; las condiciones, aceptadas al pulsar pagar en la isla.
- ⚠️ **Hallazgos al medir `#692`·1, arreglados en la T3e·1**: `celebrantNameFieldKey()` solo miraba la fase de
  reserva (el prerrelleno de la invitación se quedaba sin nombre), la hoja del día ponía el primer campo CON
  VALOR —la edad— en la columna «Homenajeado», y el «formulario completo» no contaba los datos generales
  obligatorios. Con el nombre movido de fase, los tres fallaban sin avisar.

**La arquitectura**. Un solo motor, y la carcasa se elige por instalación (§4.1):
- **Superficie por apertura**: el controlador del paquete (`cajon/controller.js`) sabe la carcasa (`shell` del
  arranque). Con `isla`, **la compra abre la isla** y **la cuenta abre el lateral** hasta la T5; los eventos
  `jw:cajon:*` dicen la superficie y la carcasa del lateral solo se pinta abierta para la suya.
- **Una sola app de Vue y un solo Pinia**: la raíz del cajón monta `PurchaseSection` o la compra de la isla
  (asíncrona, en su trozo, teletransportada a `<body>`), con el mismo puente (`ref="purchase"`): la pausa, el
  titular y el anti-bot de la cuenta siguen saliendo de ahí.
- **La compra de la isla**: `IslaCompra.vue` pinta; `compra/useCompraIsla.js` lleva el estado de la isla (su
  paso, el borrador de la pantalla 0, el formulario de datos) sobre `usePurchaseFlow()`; y `compra/vista.js`,
  módulo PLANO con su `node --test`, traduce todo eso a lo que el banco de la T3c ya pinta (`ck` y las props de
  cada pantalla). Es el adaptador de `scripts/banco-compra/entrada.js`, pero con el motor en vez del diseño.
- **Los textos de las preguntas** («¿Cuántos niños vienen?»…) son de la página (T4); hasta entonces, los del
  producto con las cifras de los datos.

**Las sub-tandas**:
- **T3e·1** ✅ los lectores del homenajeado y el «formulario completo» (arriba).
- **T3e·2** la carcasa elegible: el ajuste `sidebar.shell` (panel, `cajon` por defecto), el arranque, la
  superficie por apertura y la raíz. Con `cajon`, nada cambia: la sonda del embudo da la MISMA traza.
  **·2a ✅** el ajuste (`ShellSettings`, «Ajustes → Aspecto y opciones de la web»: «Dónde se hace la compra»)
  y el arranque: `shell` en la mitad compartida (API y `data-boot`, la última clave) y los rótulos de la isla
  (`isla`) SOLO con la isla — contrato **1.22.0**. ⚠️ **Corrige al plan**: el literal `already_exists` NO viaja
  en el arranque (se podó en `#166`), y el contrato manda programar contra `code`, no contra un texto: el «ya
  existe» del alta tendrá su código (T3e·3). ⚠️ **Y el sitio de la prueba**: las hojas de Saltia definen tokens
  en `:root` que chocarían con los de la landing de hoy, así que la isla NO se enciende sobre ella; se prueba
  en una página temporal que carga el paquete como lo hará una página nueva, y sale de verdad con la T4.
  **·2b ✅** (`#693`) la superficie por apertura y la raíz, y la PANTALLA 0 de las entradas sobre el motor:
  · el controlador (`cajon/controller.js`) lee la carcasa del arranque y abre cada cosa en su superficie; la
    carcasa del lateral (`shell.js`) solo se pinta para la suya; `sidebar/carcasa.js` es la regla, con su test;
  · la raíz (`Sidebar.vue`) monta `isla/SeccionCompra.vue` —asíncrona, en su trozo— con el mismo `ref`; sus props
    y las de la raíz son una lista (`sidebar/props.js`); con la isla, la intención espera en la máquina y la toma
    su compra; si la cuenta pide la compra desde el lateral, se abre la isla (`compra/useSuperficie.js`);
  · la pantalla 0 (`compra/useSeccionCompra.js` + `compra/vista.js` + `compra/oferta.js`, 17 casos de `node --test`):
    los días de TODAS las filas de la zona, cada fila con su precio del día y el «menos que dos de 1 hora», las
    horas con la cesta, la cantidad y el complemento por cantidad (los calcetines, por su forma y no por su
    nombre) con los límites de los datos, y el total de la línea del servidor. «Continuar», apagado hasta la ·3.
  **Lo que cazó el navegador y ninguna prueba**: la fusión del arranque de una página ajena (`mergeBoot`) se comía
  `shell` y la isla no se encendía nunca; mientras llegaban los días, las filas decían «No se vende este día»; y
  dos cambios seguidos podían pintar el total VIEJO (ahora, en cola). ⚠️ Rollup sacó a un trozo común lo que
  comparten el motor y la isla: el fichero del motor bajó a 211 KiB sin que el cajón descargara menos, así que el
  presupuesto mide ahora la DESCARGA (292; la isla, 94). ▶ Anotado para el SPA: la raíz deja un `locales=""`
  suelto en el DOM de la sección de compra (lo pasa con `v-bind` y ella no lo declara), de antes de esto.
- **T3e·3 ✅** (`#694`) la isla compra con tarjeta, de la pantalla 0 al banco y sus desenlaces:
  · **el orden, sobre la máquina**: «Continuar» mete la línea (`→ CART`) y admite (`checkout()`: `IDENTIFY` o `PAY`);
    «Tus datos» identifica (`→ PAY`); «Pagar» crea el pedido y sale (`→ REDIRECTING`); volver a la pantalla 0
    quita la línea (`→ CATALOG`). Los pasos del cobro los manda la máquina (`compra/pasos.js::pasoDelMotor`),
    porque llegan también de fuera: la vuelta del banco recarga la página y aterriza en su desenlace;
  · **la cesta de la isla es SU pedido** (`compra/linea.js`): meter la línea la SUSTITUYE, validada con la cesta
    vacía como contexto —no hay pantalla de cesta ni «quitar», y lo que quedara se compraría a ciegas—, y el
    recibo de «Pagar» la REHACE al cambiar gente o pares: complementos resueltos, `validate-line` y presupuesto
    (3 peticiones por clic; pasar por la máquina eran 7). La cantidad cambia al pulsar y el dinero, al llegar;
  · **«Tus datos»** (`compra/useDatosCompra.js` + `datos.js`): el alta pay-first del motor; si la cuenta ya existe,
    su contraseña allí mismo —el 422 del alta lleva su código, `error.params.signup` (`already_registered` ·
    `pending_verification` · `bot_check_failed`), contrato **1.24.0**—; el olvido; con sesión, «Hola» y solo lo que
    falta (el teléfono, `#692`·3, y la casilla a quien nunca firmó: `POST /me/waiver`). En el navegador se mira que
    cada campo ESTÉ; el formato lo juzga el servidor, y su mensaje va bajo su campo. El anti-bot, si está activo;
  · **«Pagar»** (`usePagoCompra.js` + `recibo.js`): el recibo del presupuesto (`PAY-12`: aquí no se suma nada),
    `accept_terms` siempre (`#692`·2), sin «tu hora queda guardada» (`#688`); junto a la acción, la pasarela y las
    condiciones (en otra pestaña); sin Bizum ni marcas (`#683`, datos de la instalación: T4);
  · **los desenlaces**: saliendo al banco (el formulario firmado tal cual, `FormularioPasarela.vue`), verificando,
    el pago no completado (su motivo, su hora, reintentar con tarjeta, «Escribirnos») y «¡Reservado!» (la línea del
    pedido, el QR del CARNÉ, el correo, «Ir a Mi QR» y «Hacer otra reserva»);
  · **el peso**: las pantallas de después de la pantalla 0 van en su trozo (`compra/pasos-diferidos.js`, 36,92 KiB)
    que la compra pide al montarse; la compra, 110,35 KiB; el motor, 293,84 (+0,45 del chunk común);
  · **la prueba**: 38 casos nuevos de `node --test` (los cuatro módulos puros y el código del alta), 3 aserciones del
    contrato del alta; banco de la T3c **54/54**; una sonda en local de
    la compra entera, en 390 y en 1280, **29/29**: los errores, «ya existe», la contraseña mala, el recibo, volver
    dos pasos y seguir, la pasarela con su firma, y los desenlaces por la vuelta REAL del banco (la portada del
    producto nace con la isla abierta).
  **Lo que cazó el navegador y ninguna prueba**: la vuelta del banco montaba el motor sin intención, y la isla
  EMPEZABA otra compra: borraba «¡Reservado!» a quien acababa de pagar (`pasos.js::empiezaOtra`, con su prueba). Y
  si el estado del pago no llega (la red, el limitador), el pago no completado decía «hasta las . Motivo:»: ahora
  va sin hora y sin motivo, como el cajón. El «no» de un reintento se DICE (el cajón lo calla: deuda de la web).
  ⚠️ **Pendiente, a su sub-tanda**: «Entra» y Google (T3e·4: hasta entonces, sin sus botones); las horas cercanas
  cuando la hora se llena al pagar (hoy, el aviso del servidor en el paso: T3e·6); las tareas de «Listo» que
  dependen de la zona (adultos, calcetines) y las marcas de pago (T4, datos de la página); «atrás» del navegador.
- **T3e·4 ✅** (`#695`) «Entra» y Google:
  · **`[DECIDIDO owner]`**: «Entra» pide solo CORREO («Escribe tu correo.», `type="email"`): el acceso del producto no
    admite teléfono. El botón de Google es el del sistema CON la «G» oficial (sus normas de marca; `#345`). Apple,
    apagado (`#683`): quien monta `AccesoSocial` lo decide (`apple`), y sin ningún botón la pieza no deja hueco;
  · **«Entra»** (`useDatosCompra.js`): correo y contraseña con el acceso del motor; al entrar, de vuelta a «Tus datos»
    con «Hola» (el diseño); el error, en su única línea (`datos.js::errorDeEntrar`); el olvido desde «Entra» vuelve
    a «Entra», y desde «Esta cuenta ya existe», a «Tus datos» (`solo`);
  · **Google con vuelta a la compra**: la ida es la redirección del servidor (`urls.google`), con `next` = la misma
    página + `?compra=reanudar` (`sidebar/reanudar.js`). Con ese parámetro el layout la sirve con la compra abierta,
    como `/entradas` (`Http\Sidebar\PurchaseResume`), y la apertura se cuenta como `resume`; dónde estaba la compra
    va en la PESTAÑA (`sessionStorage`: sin datos personales, caduca a los 30 min) y la isla, al montarse en la
    vuelta, la toma y sigue en «Tus datos» —con sesión si entró, como invitado si canceló—, y limpia la barra. La
    cuenta NUEVA completa su alta en `/registro/google` (el lateral) y vuelve a esa vuelta (`after-auth.js`,
    `reanudar`). ⚠️ La marca solo se toma en la vuelta: el motor también se monta en `/registro/google`;
  · **el peso**: mirar la vuelta en el navegador costaba 0,57 KiB en la entrada de TODA página pública; decidirla en
    el servidor, 0,07 (el motivo `resume`). El motor, +0,58 (leer la marca: `sidebar/marca-compra.js`, la otra mitad
    va con la isla): techo 295. La isla, 113,65 (techo 114); sus pasos, 37,66 (38);
  · **la prueba**: `node --test` de la marca y la vuelta (`reanudar.test.js`), el motivo `resume`, el aterrizaje y
    «Entra»; `PurchaseResumeTest` (el parámetro abre y marca; sin él o sin venta online, no; y el nombre del parámetro
    es el mismo en el navegador y en el servidor), con su mutante del layout en rojo; una sonda en 390 y 1280,
    **20/20** —«Entra» (apagado, credenciales malas, olvido y volver, entrar → «Hola» → «Pagar»), el olvido desde «ya
    existe», la ida a Google con su `next`, la vuelta CON cuenta (nace abierta, «Hola», la línea, la barra limpia,
    `resume`, → «Pagar»), la vuelta SIN cuenta y el parámetro sin marca (pantalla 0)—; la compra entera, 29/29; el
    banco, 52/54 —solo «entrar», 571 píxeles: la frase decidida— y **54/54 con el literal del diseño** (control).
  ⚠️ **Pendiente**: el aviso de Google rechazado o cancelado lo pinta hoy el layout (`session('status')`); la
  landing nueva (T4) tiene que pintarlo también. Sincronizar el diseño con las dos decisiones (texto y «G»).
- **T3e·5** los cumpleaños: la edad elige el pack de su familia, niños, día, hora, menú y la señal.
- **T3e·6** la sonda de la isla: una entrada y un cumpleaños con señal hasta la pasarela, en local, y los
  desenlaces con la vuelta sin datos y el rechazo (como `scripts/sonda-embudo.mjs`); después, en staging.

## 5. Impacto en invariantes

- `PAY-*`: solo si entra Bizum; entonces `VERIFY_CONC=1` y la lista del `CRITICAL_RE`.
- `SEC-12`: las vistas de la instancia siguen fuera del árbol; lo que la instancia declara (§4.2) es
  configuración, no código.
- `SEC-01`: toda ruta nueva de la API, dentro del grupo `api`.
- `RGPD-*`: el consentimiento dentro de la isla (`COOKIES.md`); Apple como proveedor de identidad; las fuentes
  desde el propio dominio.
- `PERF-02`: la carcasa en el arranque cacheado; nada por visitante en él.
- `AFORO-*`: ninguno; el agregado de «¿quedan huecos hoy?» es de lectura.

## 6. Plan de verificación empírica

- La suite con las dos carcasas; la huella de maquetación del cajón a 0 con la isla apagada.
- `/sonda`: compra de una entrada y de un cumpleaños con señal por la isla, en local y en staging.
- Un arnés de mutación sobre la tabla de prioridades de la isla (cambiar el orden de dos situaciones tiene que
  poner rojo un test).
- El mapa de 301 con su test: cada URL del sitemap de hoy responde 301 a su sitio nuevo.
- Contraste AA y tiempo de carga en móvil, medidos en staging.

## 7. Revisión y decisión

**Decidido por el owner el 24-09**: `#681` (Blade en la instancia) y `#682` (la isla, segunda carcasa, apagada
por defecto, hecha en este carril).

**Contestadas por el owner el 24-09** (`#683`), las cuatro con la recomendación delante:

1. **La primera página (T4)**: **Kids y Jump**. Un molde da dos páginas y su compra no lleva señal: es el
   camino del dinero más corto para estrenar la cadena entera.
2. **Los iconos**: **Lucide**, metido en el HTML al construir (§4.6).
3. **Los pasos del cajón clásico**: **adopta los pasos nuevos** (§4.1).
4. **La lógica nueva en la v2.0.0**: **Bizum, Apple y el aviso de día liberado** (§4.5). En lugar de la fecha
   de fin de oferta, el owner abrió un **sistema de promociones** (`#684`), con spec propia.

**El aviso de arriba**, `[DECIDIDO owner]` 2026-09-24 (`#684`): es para **noticias y avisos generales** (un
cierre, un horario especial), y **cada oferta va en la página de su producto**, como pide la regla 5 del brief
base. **Por iterar con el owner**: el modelo de las promociones (dónde va cada etiqueta en la landing).

**Para la T3, contestadas por el owner el 24-09** (`#688`, §4.10), las dos con la recomendación delante:

5. **La hora guardada en «Pagar»**: `[DECIDIDO owner]` **se guarda al pagar, como hoy**. La línea «Tu hora queda
   guardada hasta…» sale donde es verdad (saliendo al banco, pago no completado, «Sigue con tu reserva») y no en
   «Pagar». Guardarla al continuar obligaba a crear el pedido al salir de «Tus datos» y a soltarlo y rehacerlo
   con cada cambio del recibo.
6. **Cuándo dice «Esta cuenta ya existe»**: `[DECIDIDO owner]` **al pulsar «Continuar al pago»**, con el aspecto
   del diseño: es la respuesta del alta de `#31a`, con su límite de 3 por hora. Decirlo al teclear exigía un
   servicio que respondiera si un correo tiene cuenta, sin ese límite.

**Revisión**: la spec la revisa el owner; la revisión adversarial se le pide con el coste delante (regla 9).

## Anexo · fila del enrutador

`| La isla · la landing nueva (Saltia) · la carcasa de compra | docs/specs/isla-y-landing-nueva.md §0 |`
