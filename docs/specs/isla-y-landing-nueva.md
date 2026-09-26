# [SPEC] La isla y la landing nueva — las páginas en la instancia y una segunda carcasa de compra en el producto

> Estado: ⬜ **borrador** · Última actualización: 2026-09-24 · Decisiones: `#681` (las páginas), `#682` (la
> isla), `#683` (las cuatro de §7), `#684` (promociones), `#697` (el sistema nuevo del 24-09 tarde) y `#699` (los
> datos de Kids y Jump), todas `[DECIDIDO owner]`; la spec se aprueba con la suya.
> Carril: **plataforma** (bandas 670–699 y 760–789). Fuente del diseño: el proyecto de Claude Design
> `33397ca2-c67c-4049-8b09-ade20425f32a`, «Saltia Design System» (nombre provisional; la marca es Play Jump
> Park), que entra SOLO por su zip, en `instancias/playjump/diseno/` (`#760`: sin `DesignSync`). Hermanas: `instancia-y-landing-fuera.md` (el menú
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
- **Estado**: §7 contestado (`#683`), promociones en `#684`, T0 hecha (§1.6). **T1 ✅** (§4.8), **T2 ✅** (§4.9:
  la isla en Vue, idéntica al diseño) y **T3 ✅** (§4.10, `#689`→`#698`): la isla compra con tarjeta hasta el banco,
  con «Entra» y Google, cumpleaños con señal y la hora que se llena; su sonda, `scripts/sonda-isla.mjs`; la
  secuencia, en `sidebar/usePurchaseFlow.js`. El sistema nuevo del 24-09 tarde, dentro (§4.11, `#697`). **T4 🟦**
  (§4.12) · **T5 🟦** (§4.13, `#773`): Mi cuenta. La fiesta, del SPA (`#765`).
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
  brief pero no página), el post-form, la invitación y el justificante (tienen brief propio). ▶ Estos tres llegan
  montados el 24-09 por la tarde (§4.11).

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
| **Precio de antes (tachado) y texto y fecha de la oferta** (1, 3, 8, isla) | Ninguna: la tabla `prices` ya guarda el precio rebajado y solo las vistas Blade de hoy reconstruyen el de antes (`WritesLandingValues::antes()`) | **NO VA** (`#699`): el precio tal cual; las promociones (`#684`), aparcadas |
| **Plazo de cambio y cancelación**: 24 h en entradas; en cumpleaños, **3 días naturales** (`#699`: mandan las condiciones; el cajón y el diseño decían 5) (2, 3, 7) | Solo como frase (`pay_policy` en `lang`, y la de las condiciones); **ninguna regla lo aplica**: cancela el personal | **FALTA** → un campo POR PRODUCTO (`#699`), con la T4 |
| **Los menores de 4 entran con un adulto desde 90 cm** (1, 3, 5, 7) | `height` de Kids solo trae el máximo (150 cm) | **FALTA** → un campo de la ZONA, junto a su edad y su altura (`#699`) |
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
| El plan destacado del selector | Selector de plan | **HAY** (`#699`) | Con la T4 | El `featured` del producto (un pack es un producto), su foto y su `badge` |

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
| T3 ✅ | La compra en la isla sobre el motor, con tarjeta; sonda de compra (§4.10; la sonda en staging, con el ensayo de la v2.0.0) | Producto |
| T4 | **Kids y Jump** (`#683`), un molde y dos páginas, con su calculadora y la isla viva en la página (§4.12: T4a→T4f); los 301, con la T6 | Instancia + producto |
| T5 | Mi cuenta en la isla (§4.13: T5a→T5f, `#773`) | Producto |
| T6 | El resto de páginas, las tres de la fiesta (las viste el SPA: `#765`, §4.11) y la lógica nueva que apruebe el owner | Los dos |

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
  `DesignSync`: los primeros 262.144 B del compilado tienen que coincidir con lo que entrega el MCP. ▶ Sustituido
  por `#760` (24-09 noche): sin contraste; el guion rechaza un zip más viejo que el puesto.
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
- **T3e·5 ✅** (`#696`) los cumpleaños, de la pantalla 0 a «¡Fiesta reservada!», con la señal:
  · **la pantalla 0 de una fiesta** (`compra/fiesta.js`, sin estado, y `compra/usePantallaCero.js`, que es la pantalla
    0 entera —entradas y fiestas— salida del orquestador): la EDAD elige el pack por su tramo (`guest_age_min`–`max` de
    cada ficha; el tramo abierto, «desde 8», ofrece cinco años más, la rejilla del diseño: lo único que no es un dato);
    los niños nacen en el mínimo del pack, con la pista «ajusta hasta N h antes» de `GET /config`
    (`guest_count_cutoff_hours`, contrato **1.25.0**; el motor ya la pedía al montarse: `configuracion`, sin otra
    petición); los días de todos los packs de la zona; sin día elegido (una fiesta no nace «para hoy»). Los menús, el
    grupo de elección que resuelve el servidor, ANTES de la hora (`oferta.js::cargarGrupos`: el endpoint de
    complementos los da sin día ni hora pero rechaza `null` en ellos). Del resto de complementos, nada (`#692`·4);
  · **la línea** lleva la edad en el campo `celebrant_age` de su pack (su clave, de la ficha) y el menú como elección;
    el total, «Hoy pagas 50 €» y el precio de cada menú llegan del servidor (`PAY-12`);
  · **«Pagar»** (`recibo.js`): el pack «por niño», el menú en el rótulo si es gratis o en su fila si cuesta, la nota
    «Hoy pagas 50 € de señal; el resto, X, el día de la fiesta» (lo que queda es el `gate_remainder_cents` de cada
    línea) y «Pagar 50 € con tarjeta». Al rehacer el recibo por un niño más, el menú elegido se conserva;
  · **«¡Fiesta reservada!»** con su tarea, que dice el SERVIDOR por reserva (`OrderItem`, contrato 1.25.0):
    `guest_form_url`, `guest_count_deadline` (el plazo de `GuestCountPolicy`, el mismo instante que publica el
    formulario) e `invitation_url` (la invitación, DENTRO del formulario, `#gf-invite`, solo si el producto la
    ofrece). Sin invitación, «Ahora, una cosa.» y un botón. Los botones salen a esas URLs (`pasos.js::destinoDeTarea`);
  · **el peso**: la compra, 123,46 KiB (techo 124) medida como DESCARGA, igual que sus pasos (37,66): diferir la
    pantalla de la fiesta hizo a Rollup sacar sus piezas a un trozo compartido y el fichero «bajó» 27 KiB sin que se
    descargara menos (ahorraba 1,6 a quien compra entradas a cambio de una petición más): va en la compra. El motor,
    294,70 (+0,28);
  · **la prueba**: 23 casos nuevos de `node --test` (`fiesta`, `linea`, `recibo`, `pasos`, `oferta` y `outcome`), el
    contrato del plazo y la invitación (`MeOrdersTest`, con la vista que tiene `#gf-invite`) y del corte
    (`PublicConfigTest`); una sonda en 390 y 1280, **21/21** —la pantalla 0 (edades 4–13, 8 niños y su pista, los dos
    menús), la edad que cambia de pack, el Menú 2 que cambia el total, la línea con `age` y el 108, el recibo y su
    señal, un niño más que conserva el menú, la pasarela con 5000 céntimos, la vuelta REAL del banco y la tarea que
    lleva al formulario de ESA reserva—; las de entradas y «Entra», 29/29 y 20/20; la traza del cajón, idéntica; el
    banco, 52/54 (las cinco situaciones de fiesta, idénticas; «entrar», la frase de `#695`).
  ⚠️ **En local ningún pack ofrece invitación**: la tarea de dos cosas solo la prueba `node --test`. **Del owner,
  en producción**: pasar el nombre del homenajeado de los dos packs a la fase «formulario de invitados» (`#692`·1).
  ⚠️ **Pendiente**: la vuelta del banco aterriza en la landing de hoy, sin las hojas de Saltia (letra y fondo del
  «Listo» sin sus tokens): lo arregla la página nueva (T4).
- **T3e·6 ✅** (`#698`) la hora que se llena al pagar, y la sonda versionada:
  · **la hora llena**: con la hora guardada al pagar (`#688`), el «no» (`line_sold_out`, `line_pack_sold_out` en una
    fiesta) llega en «Pagar», y la isla pasa a la pantalla del diseño para ese momento, `PjcPerdida` («Esa hora ya no
    está libre. No se ha cobrado nada. Estas sí:»), en la banda de «Pagar», sin flecha y con la línea debajo. Las
    horas se piden otra vez al servidor y salen las cuatro con sitio más cercanas (`vista.js::horasCercanas`, la
    `cercanas()` del diseño); «Elegir esta hora» rehace la línea (`usePagoCompra::rehacer`) y vuelve a «Pagar»; si
    esa también se llenó, otra vez. ⚠️ Sin ninguna libre ese día, a la pantalla 0 con el aviso del servidor: «Estas
    sí:» sin horas mentiría. El motor devuelve el «no» con su código (`pay.js::confirmError`, `confirmReservation`):
    solo añade, el cajón no lo lee (aviso al SPA en el buzón);
  · **la sonda**: `scripts/sonda-isla.mjs`, que sustituye a las desechables de `storage/app/`. Monta su propia página
    y la BORRA (guarda 9); llena en la BD la franja de la línea antes de pagar y la devuelve a su cupo exacto aunque
    se corte. Una entrada (la hora llena, las cercanas, la pasarela), los desenlaces por la vuelta REAL del banco
    (sin datos, el rechazo con su motivo y reintentar, «¡Reservado!» con el QR) y un cumpleaños con la señal de 5000
    céntimos hasta «¡Fiesta reservada!». **19/19 en 390 y 1280**; un mutante sin el código de la hora llena da
    rojo (código 1). Cazó el resumen que faltaba bajo la hora perdida y un 429 de la PROPIA sonda: el `throttle:6,1`
    del reintento tiene como clave `sha1(id)`, compartida con los demás `throttle` numéricos;
  · **de paso, la guarda 9** (`scripts/deploy.sh`): abortaba cualquier despliegue con `public/instancia/` en el
    disco —pregunta a git, y la exclusión del rsync no la salva—; entra en su lista blanca, con su caso en
    `DeployScriptGateTest` (rojo antes del arreglo);
  · **el peso**: la compra de la isla 130,90 KiB (techo 131); sus pasos 38,56 (techo 39).
  ▶ **Queda**: la sonda en STAGING, con el ensayo de la v2.0.0 (`#670`); «atrás» del navegador; las tareas de «Listo»
  por zona, con la T4.

### 4.11 El sistema nuevo del 24-09 (tarde): lo que cambió, lo medido y el censo de la fiesta (`#697`)

**De dónde**: `Saltia Design System (1).zip` (entradas de las 17:43:20–22, bajado a las 19:43; sha256 `f1d9855e…`), puesto con
`diseno/actualizar.py` de la instancia: **468 ficheros, 70 nuevos, 50 cambiados y ninguno retirado**. Sin contraste
con el proyecto vivo, y desde ahora nunca (`#760`): la cuenta de Claude Design del owner no es la de desarrollo. El
zip es la única fuente; el guion rechaza uno más viejo que el puesto (por la hora de sus entradas) y no toca nada
con el mismo.

**Qué trae**: la familia `components/invitados/` (`InviteCard`, `ThemePicker`, `RsvpBar`, `AuthForm`, `GuestRow`,
`GuestComposer`, `PlacesMeter`, `AddonCard`) y `forms/SaveBar`; las páginas montadas de la **lista de invitados**, la
**invitación** con su recibo y la **autorización**, `mi-cuenta-reglas`, la plantilla de correo y quince correos; los
briefs de app, autorización, Colegios, invitación y lista. En lo ya montado: Mi cuenta rehecha (entrar sin sesión,
ajustes, «una tarea cada vez», la cuenta nueva, la visita de hoy ya terminada), **el selector de plan rehecho**
(destacado configurable con foto y etiqueta, [Hoy] en lima, filas con el «desde» grande, la garantía al pie), la
hora extra fuera de los extras y **un plazo por extra** (tarta 48 h; bebidas y combos, el mismo día), 155 reseñas en
toda la web y «Colegios» en el menú. El README del diseño lista sus «Por confirmar con el cliente» (dos plazos en la
misma fiesta, si quien cumple cuenta en el número, el asunto del correo 2…): se leen al empezar cada página.

**Lo medido sobre lo construido**, con los tres bancos contra la referencia nueva:

| Qué | Antes del port | Causa | Después |
|---|---|---|---|
| La hoja `saltia.css` | — | Solo AÑADE: `--surface-glass-ink-float`, `--shadow-island-float`, las animaciones de la invitación, `[tabindex="-1"]` sin anillo | Reconstruida |
| Isla | 50/52 | `selector-plan`: el diseño lo rehízo | **54/54** (+`selector-plan-portada`, el de la portada entero) |
| Piezas | 17/18 | `campo-foco`: `Field` pone `boxShadow: none` al campo (el foco global pintaba un segundo anillo) | **18/18** |
| Compra | 52/54 | `entrar`, la frase de `#695`: sin cambio | 52/54 |

El port: `piezas/SelectorPlan.vue` (los cuatro trozos del JSX en un fichero), el rol `--isla-destacado-fondo-hover`
(neutro en el producto; lima en PlayJump), `shield-check` en `ui/iconos.js`, y los textos `panel.plan_desde` y
`pieza.opcional` en es/en/fr. **El peso**: la compra de la isla, que se descarga con la carcasa, pasa de 123,96 a
**129,66 KiB** (techo 130 en `SidebarBundleBudgetTest`): +5.838 B medidos compilando con el selector de antes y el
de ahora; son sus estilos en línea, como en el JSX. **Nacen con su consumidor**, sin portar aún: `TaskCard` en fila y con `overline` (Mi
cuenta, T5), `QuantityStepper` con `name` (sin JavaScript: la lista), `AddonList` con plazos (Cumpleaños) y el
`boxShadow` de `Textarea`. `--shadow-island-float` vale lo mismo que `--isla-sombra`: nada que cambiar.
⚠️ **El plan destacado es un DATO** (`featured`, `badge`, `image`, `focus` y `plans.footer`): `#699`, el `featured`
que ya tiene el producto, con su foto y su `badge`; llega con la T4. `offer`, apagado (sin promociones).

**El censo de la fiesta** (medido buscando en el código el 24-09; se confirma al empezar su tanda). La lógica es del
SPA (`celebracion-e-invitacion.md`, `waiver-por-reserva.md`, `complementos-post-reserva.md`), y los briefs lo dicen:
«la mecánica viene decidida del sistema». **HAY**: la lista con las respuestas que el anfitrión ADOPTA al guardar, el
borrador en el móvil, «La reserva ha cambiado», la hoja en blanco, el menor sin rellenar (`#706`), los TRES temas
(`PartyInvitation::theme`: confeti, fiesta, sereno), el nombre de quien cumple, el `.ics`, el recordatorio y el corte
por complemento (`TicketType::postform_cutoff_hours`). **FALTA**, lógica que trae el diseño: «Pegar una lista»,
combos y cubos «para N adultos» con «¿Cuántos adultos se quedan?», la tarta por raciones (la grande), «Contestar por
otro hijo» con las respuestas 24 h en el móvil, «Crear mi QR» desde el recibo, la casilla «Avísame de fechas»
(consentimiento comercial: `RGPD-*`), el QR de la fiesta en la puerta para firmar allí y «Ver el parque» (propuesta
del propio diseño). Cada una se le lleva al owner y se habla con el SPA antes de empezarla.
⚠️ Las tres páginas van **sin isla ni menú** (así las dibuja el diseño), y encaja con la regla del SPA (`#739`: *el
invitado no es un visitante*, sin banner, driver ni píxeles en ellas).
▶ Contestado por el SPA el 24-09: el aviso de cookies dentro de la isla pinta su tarjeta sobre el MISMO almacén
(`ui/cookie-consent.js`, `createCookiesStore`) y dispara `cookies-updated`; y `lint:js` con `isla/`, «hazlo tú».

▶▶ **El traspaso al SPA (`#765`, 25-09)**: las tres páginas de la fiesta las viste el carril del SPA, en el otro
ordenador y en paralelo con la web pública. Lo que necesita para empezar, medido aquí:
- **Qué, en el diseño** (`instancias/playjump/diseno/playjump-design-system/`): `paginas/lista-invitados.card.html`
  (código en `paginas/lista-invitados/`: `datos.js`, `estado.jsx`, `zonas-1-2.jsx`, `zonas-3-5.jsx`),
  `paginas/invitacion.card.html` (`invitacion/datos.js`, `vistas.jsx`, `invitacion.css`, que comparte con la
  autorización) y `paginas/autorizacion.card.html` (`autorizacion/datos.js`); los componentes de
  `components/invitados/` y `forms/SaveBar`. Se leen ANTES, en `readme.md` del diseño, las secciones «La lista de
  invitados», «Complementos en la lista de invitados», «La invitación, con su tema», «La invitación y su recibo» y
  «La autorización», con sus «Por confirmar» y «Por aprobar», y la lista general «Por confirmar con el cliente».
- **La máquina**: el repo de la instancia (`github.com/yasmindanailov/instancia-playjump`) clonado AL LADO del
  producto, en `../instancias/playjump` (es lo que monta `compose.yaml`: `../instancias:/var/www/instancias`), con
  `INSTANCIA_RUTA` en `.env` y `cp -r ../instancias/playjump/publico/instancia public/`. La copia del diseño se
  comprueba con `cd diseno && sha256sum -c --quiet playjump-design-system.sha256`. ⚠️ El zip entra SOLO por el
  ordenador de plataforma (`diseno/actualizar.py`) y llega con un `git pull` del repo de la instancia: nunca se
  pone a mano en el otro.
- **El método**: ⚠️ **`#768` (owner, 25-09): se construye sin banco por tanda y la identidad se verifica UNA vez,
  al final de cada página y ligera** (piezas en reposo a 390 y 1280, sin estados ni reintentos salvo lo que falle).
  La herramienta es el banco A/B con `scripts/pixel.mjs` (`--rehacer --reloj`) → 0 píxeles: A = la ficha del
  diseño con sus datos de prueba; B = nuestra página con los MISMOS datos. Los modelos: `scripts/banco-entradas.php`
  (JSX con Babel contra Blade) y `scripts/banco-isla.php` (contra Vue). Controles (mutantes), solo en dinero, aforo o
  seguridad. Las trampas del juez, en `TESTING.md` §2.octies.
- **Dónde viven, recomendación técnica a medir en su T0** (`#630`): en el PRODUCTO, como la isla (`#682`), porque
  son funciones de toda instalación con fiestas y «la mecánica viene decidida del sistema». La estructura va en el
  producto con roles genéricos de valor neutro, y los valores de PlayJump van en una hoja de la instancia cargada tras
  `saltia.css`, igual que `publico/instancia/css/isla.css` con los `--isla-*`. ⚠️ Hoy una vista del producto NO
  carga hojas de la instancia (por eso la isla sale neutra en las páginas viejas). Ese mecanismo es del paquete de
  instancia, de este carril, y se propone en el buzón antes de tocarlo. En principio queda descartado hacerlas vistas
  de la instancia sobre el contrato de datos (`#681`): la lógica de la lista (el borrador, pegar, las plazas) acabaría
  en la instancia.
- **Reglas que siguen en pie**: el suelo sin JavaScript del brief (un `<form>` de verdad y Guardar como su botón de
  enviar; lo demás es mejora) · `#739` (sin banner, driver ni píxeles; el hecho lo deja el controlador) · la firma y
  su prueba (`waiver-probatorio.md`, `RGPD-*`) · ⚠️ la zona 3 de la lista, «siempre empuja a llenar» (25-09), sube
  el número: eso ocupa AFORO y cobra en el parque, así que es `INVARIANTES` §1–§2 y `VERIFY_CONC=1` si se toca.
- **El orden recomendado**: primero se viste lo que HAY (las tres páginas con la lógica de hoy, a 0 píxeles en sus
  estados); después, lo que FALTA (el censo de arriba y lo que el diseño añadió el 25-09: quien cumple primero, la
  lista que empieza solo con él, «Al final viene» y el número que empuja a llenar), una pieza cada vez y con la
  decisión del owner delante. El censo se re-mide al empezar, contra el último zip.
- ▶ **Al día (25-09, tarde)**: (a) **el zip del 25-09** (instancia `52f6fac`) NO toca la fiesta —ni las tres páginas,
  ni `components/invitados/`, ni `SaveBar`: medido fichero a fichero—; trae Colegios y Visítanos (esta, incompleta)
  y cambia piezas de Kids, Jump y Cumpleaños. (b) ⚠️⚠️ **`#767` (owner): el diseño se toma SOLO del mockup** (el zip,
  sus fichas, su código y su `readme.md`). Los briefs de la lista, la invitación y la autorización, y el Mapa, están
  en la instancia (`docs/estrategia/`, `feb6d1a`) como REFERENCIA para algunos casos: nunca deciden cómo se ve algo
  ni corrigen al mockup.
  (c) **`#766`**, contestadas por el owner: quien cumple es UNO del número (ocupa aforo y se cobra; el diseño ya lo
  dibuja así), el asunto de «Fiesta reservada» va SIN nombre, y hay UN solo plazo para la lista y el número, el que
  ya existe (`packs.guest_count_cutoff_hours`, 24 h), con el suyo en cada extra.

▶▶ **El zip del 25-09 en lo construido** (instancia `52f6fac`). El `git diff` dio 11 componentes cambiados; los bancos
(la última vez con el método de `#685`) dijeron cuáles mueven lo que pintamos: **antes del port**, Kids y Jump 95/170
(fallaban las dudas, la calculadora, «dónde y cuándo» y el pie), la isla 48/48 (el juez se CAYÓ: una petición a la CDN
en vuelo con el contexto cerrado; arreglado en `pixel.mjs` con `unrouteAll` antes de cerrar), las piezas 25/25 y la
compra 52/54 («entrar», `#695`: sin cambio). **Portado**: en la instancia `accordion` (la pista baja bajo la pregunta),
`opening-hours` (las horas marcadas solas en mono dentro de la frase, el rango sin partirse —la expresión del diseño,
más en/fr y el espacio duro—, la línea de hoy con su parte viva en su renglón, filas `closed`), `park-location`
(`<address>`, el mapa como atajo a la ruta fuera del teclado, el parking antes del botón y partible, «Cómo llegar» en
otra pestaña) y la nota partible de `proof-list`; en la isla, el contador que envuelve, la **situación 15** (páginas de
apoyo, `hoy.apoyo` en es/en/fr) y el cálculo con alerta (`situacion.test.js` +2). **Después del port**: la isla 60/60
(con tres situaciones nuevas en `tema/isla-situaciones.json`) y Kids y Jump 65/65 hasta cortar la pasada por `#768`
(la calculadora y las dudas, de vuelta a 0); «dónde y cuándo» y el pie, a la verificación final. **Sin portar, nacen
con su consumidor**: `QuantityStepper editable`, `ShareRow download`, `RateTable sm`, `CTABand` sin botón y con
`link`, la oferta de `ProofList`, `OpeningHours` `lg`/`specialNote`/`exceptions`, `VideoHero today`, `StepsPanel`,
`DocPreview` y `NextDays` (Colegios, Visítanos, la portada).

### 4.12 La T4: Kids y Jump — el censo (MEDIDO 24-09) y el plan

**Lo que pide el diseño** (`paginas/kids.card.html`, `jump.card.html` y `paginas/entradas/`): UN molde y dos textos
(`contenido.js`, 25 KB: cada texto del brief; cada cifra, un hecho). Ocho piezas y el pie, con la isla VIVA encima:
1-2 cabecera y garantías (`VideoHero`, `DayRates`, `RatingSummary`, `ReassuranceStrip`) · 3 precio y reserva
(`RateTable` y la CALCULADORA: cinco preguntas, `AvailabilityCalendar` de mes, `PriceSummary` con «Reservar y
pagar», `ShareRow`, `InfoCallout`, `Accordion`) · 4 la zona (`ClipTile`, `ClipList`, `ClipViewer`) · 5 tranquilidad
(`ProofList`, `ReviewPanel`) · 6 dónde y cuándo (`ParkLocation`, `OpeningHours`, `BookingCTA`) · 7 dudas (`Accordion`)
· 8 cierre (`CTABand`) · pie (`SiteFooter`). Un solo destino: todo botón lleva a la pieza 3, y «Reservar para hoy»
llega con hoy elegido. Cada pieza tiene su ficha suelta en `sections/{kids,jump}-*.card.html` (14): contra ellas se
juzga «idéntico», con los datos del diseño.

**Lo que hay, medido**:

| Pieza del trabajo | Hoy | Veredicto |
|---|---|---|
| Los hechos de la página (precios, días especiales, horas libres, horario, dirección, nota, atracciones, zona, calcetines, total) | El menú de hechos (§1.6.1) | **HAY** |
| El plazo de cambio y cancelación; los 90 cm con adulto | Frases sueltas; `zones.height_min_cm`/`height_max_cm` sin la excepción | **FALTA** (`#699`: columna por producto y de la zona) |
| El precio de antes, la oferta, JumpPoints, [Jump Club], [Bono] | Promociones aparcadas (`#684`, `#699`); JumpPoints con el código SIN empezar | **APAGADOS**: corchetes sin hueco |
| Las tres reseñas reales de la pieza 5 | El producto las TIENE (`Content\Contracts\SocialProof::testimonials()`: Business Profile, Places y el panel, con su permiso, `#592`, y la línea de la Ómnibus, `selection()`); la portada de hoy las pinta; `/social-proof` publica la nota y el número, **no la lista** | **PARCIAL**: publicar la lista, con la T4a |
| El material (vídeos y fotos de cada zona) | Las 23 atracciones tienen su FOTO en el panel (`/attractions`, medido 23/23); faltan los vídeos de las 4 destacadas de cada zona, la cabecera de cada zona (el clip del parque no vale: mezcla zonas) y la foto de la pieza 5 (hoy, una provisional del panel: `#764`) | **PARCIAL** (encargo del owner) |
| Servir `/kids` y `/jump` | Las nueve vistas de hoy tienen cada una su controlador; `instancia/config/` está vacío | **FALTA**: la declaración de páginas y el controlador genérico (§4.2), `InstanceViews::CONTRATO` 3 |
| El layout limpio (solo las hojas de Saltia) | Ninguno: nace con su primera página (§4.8, T1c) | **FALTA** |
| Las ~22 piezas de presentación en Blade | `<x-lucide>` (T1d); nada más | **FALTA** (~120 KB de JSX de origen) |
| La calculadora | La pantalla 0 de la isla (`usePantallaCero`, `vista.js`) y cuatro piezas Vue (`TarjetasOpcion`, `ContadorCantidad`, `SelectorHoras`, `ResumenPrecio`) | **PARCIAL**: falta el calendario de mes y el montaje en la página |
| **La isla EN REPOSO en una página real** | Solo existe en el banco: en una página se monta la de la COMPRA, al abrirla (`SeccionCompra`, `v-if="abierta"`) | **FALTA**: montarla al cargar, darle los hechos (hoy, huecos, ayuda, cuenta, cookies) y que se transforme en la compra |
| El contrato página↔isla (§4.3) | No se fijó en la T2: la isla recibe `page` como prop | **FALTA** |
| «Hoy» de la isla | `/schedule/now` da solo `open_now` y `closes_at`; la apertura sale de `/schedule` y los huecos, de las horas del día del producto | **HAY** componiendo; el agregado de TODA la instalación, con la portada |
| Los textos en inglés y francés | El diseño da solo español | **FALTA** (decisión del owner) |
| Los 301 (§1.6.4) | Llevan a la portada nueva, que es de la T6 | **Con la T6**: nada se despliega antes de la v2.0.0 (`#670`) |

**El plan, en seis sub-tandas** (`[DECIDIDO owner]` `#761`, en este orden; cada una con su prueba y su medida):
- ▶ **La T4a, afinada al medirla (24-09)**. La regla de altura estaba escrita TRES veces y no decían lo mismo: la zona
  (Jump «desde 1,30 m», Kids «4 — 7» y «hasta 1,50»), `/normas` (`park_rules`: Kids «de 4 a 8»; Jump «entre 1,10 y
  1,30, con un adulto») y el diseño (Jump «con menos de 1,30 m, con un adulto»). Manda el diseño (`#761`·4). Por
  eso la zona gana dos reglas explícitas de «con un adulto» —`escort_under_age_from_cm` (por debajo de la edad
  mínima, desde esa altura: Kids, 90) y `escort_below_cm` (por debajo de esa altura: Jump, 130)—, y el producto,
  `cancellation_cutoff_hours` con su frase («hasta 24 h antes», «hasta 3 días antes»). ⚠️ La edad en NÚMEROS ya
  existe y es del PRODUCTO (`guest_age_min`/`max`, `#676`, publicada para escribir «de 4 a 7 años»), pero el panel
  solo la dejaba editar en los packs: se abre a las entradas; la zona no la duplica. Todo NULO por defecto: los
  valores los pone el panel. `age_range` (el rótulo libre de la zona) sigue igual. En tres pasos: **·1 ✅** la zona y el producto (contrato 1.26.0) · **·2** la lista de reseñas
  en `/social-proof` (el permiso depende de la cookie del visitante y la API es pública: se mide antes) · **·3** el
  paso de pagar del cajón leyendo el plazo (fichero del SPA, avisado antes).
  ▶ **·1, hecho (24-09 noche)**: migración `2026_09_24_180000` (tres columnas nulas); `ZoneEscortRule` y
  `CancellationCutoffRule` redactan (metros en el decimal del idioma; el plazo en horas por debajo de 48 y en días
  justos a partir de ahí; `0` = «hasta la hora reservada»); `/catalog/zones` gana `escort {under_age_from_cm,
  below_cm, written}` y `/catalog/products` (y su ficha) `cancellation {cutoff_hours, written}`, los dos bloques
  ausentes enteros si no hay dato; el panel expone las dos reglas en la sección de altura de la zona, el plazo en la
  operativa del producto y la edad de una ENTRADA (mismas columnas que el pack, nunca visibles a la vez; la
  normalización pasa a ser por TIPO: la entrada conserva la edad y pierde la familia, el complemento pierde las
  tres). **Medido**: 10 casos nuevos y 1 reescrito (API, contrato, panel; la suite, de 5.960 a 5.970), 6 mutantes —5 muertos a la primera; el que
  sobrevivía (la normalización de la zona, que el campo numérico de Filament ya hacía) muerto con un caso que
  conduce la puerta del servidor—; en vivo, `curl` a la API en es/en/fr con los valores de PlayJump puestos en la BD
  local («con menos de 1,30 m, con un adulto», «under 1.30 m…», «hasta 3 días antes»…) y la landing de hoy en 200,
  ya sin «desde 1,30 m» en Jump. ⚠️ De paso, `MODELO-DATOS.md` listaba en `zones` cuatro columnas borradas en `#669`
  y no tenía las de altura: al día.
  ▶ **·2 y ·3 son del SPA, medido al llegar**: publicar las reseñas en `/social-proof` es la **T2·9** de
  `google-business-profile.md` (su carril; con la selección de la Ómnibus y una decisión del owner sobre caras y
  fotos, `#616`), y el paso de pagar es suyo. Los dos, pedidos en su buzón (24-09 noche). La pieza 5 se construye
  en la T4c contra el banco y se conecta cuando esté la T2·9.
- ▶ **La T4b, afinada al medirla**: el controlador genérico INVOCA los controladores de hechos que la página declara
  (`/prices`, `/schedule`, `/site`, `/catalog/*`…) y le pasa a la vista **el mismo JSON que la API** —sin copiar
  lógica: si la API cambia, la página lo ve—. El layout limpio necesita el MISMO estado del `<body>` que el de hoy
  (consentimiento, analítica, píxeles): se saca tal cual a un componente compartido, avisado antes al SPA.
  ▶ **T4b·1, hecha (24-09 noche)**: `Http\Instancia\InstancePages` lee `instancias/playjump/config/paginas.php` (el del paquete) DESDE la raíz
  que `InstanceViews` validó (`SEC-12`: el fichero se ejecuta), valida cada página (slug, vista que exista, hechos de
  la lista blanca `PageFacts::HECHOS`, sitemap) y registra su ruta AL FINAL de `routes/web.php` —dentro del grupo
  `web`—, descartando la que pise el PRIMER segmento de una ruta del producto (`precios`, `api`, `admin`); lo que no
  cuadra se queda fuera con aviso en el log y lo demás sigue en pie. `InstancePageController` le pasa a la vista
  `pagina` y `hechos` (`PageFacts` invoca los controladores de la API: el mismo JSON). La instancia gana su espacio de
  TEXTOS (`instancia::…`, su `lang/`). El sitemap publica sus páginas. `components/pagina.blade.php`, el layout
  limpio: solo las hojas que la página declara. ⚠️ `InstanceViews::CONTRATO` NO sube: es aditivo (un paquete sin
  páginas sigue igual) y el aviso compara por igualdad; se declara `CONTRATO_DE_PAGINA`. **Medido**: 5 casos con un
  paquete de prueba (el mismo JSON que la API, colisiones, hechos fuera de lista, sitemap, sin declaración) y 3
  mutantes muertos (sin colisiones, sin lista blanca, sin textos); en local, `/kids` y `/jump` de PlayJump en 200 con
  `no-store` y su CSP, en es/en/fr y en el sitemap. ⚠️ El caso registraba las rutas FUERA del grupo `web` y salía sin
  CSP: el arranque las registra dentro, y el caso ahora también.
  ▶ **·4 ✅ (25-09, con el visto bueno del SPA)**: el estado del `<body>` —consentimiento, analítica y píxeles— sale TAL
  CUAL de `components/layout.blade.php` a `components/site/body-state.blade.php`, que los dos layouts incluyen dentro
  de su etiqueta; la fiesta NO (`#739`). Las guardas del SPA (`CookieGateBlockingTest`, `PixelsTest`…) siguen en verde
  sin tocarlas, y `InstancePagesTest` gana la guarda de igualdad: el `<body>` de una página nueva, atributo a atributo,
  el de la portada (con un píxel configurado); control: sin la inclusión, cae. En vivo, `/kids` lo sirve. Lo que LO LEE
  (el aviso de cookies dentro de la isla y los cargadores), con la T4e.
- ▶▶ **La T4c, diseñada al medirla (24-09, `#762`)**. Medido en los 20 componentes que usan las piezas (~107 KB de
  JSX con estilo en línea): **cuatro deciden su forma MIDIÉNDOSE** (`VideoHero` apila por debajo de 720 px con OTRO
  DOM; `ProofList`, `ReviewPanel` y `ParkLocation`, con `ResizeObserver`), **seis abren y cierran con estado**
  (`Accordion`, `ClipList`, `ClipTile`, `ClipViewer`, `ShareRow`, `CTABand`) y el `hover`/`press` de botones y
  enlaces es estado de React. De ahí la forma:
  - **·0 no lleva código**: Laravel ya resuelve `<x-instancia::nombre>` contra `web/components/` del espacio de
    vistas que registra `InstanceViews` (medido con un paquete de prueba: `<x-paqx::pieza>` y `<x-paqx::grupo.hija>`).
    Registrar otro espacio (`componentes/`, lo que proponía esta spec) daría lo mismo con un mecanismo más y otra ruta
    que validar (`SEC-12`). Entra la GUARDA, porque es promesa hacia la instancia (`InstancePagesTest`).
  - **Tres capas, todas de la instancia**: (1) **componentes** en `web/components/` = los del diseño (su nombre en
    kebab, sus props y su DOM); (2) **piezas** en `web/entradas/`, que componen desde `$z`, un arreglo con la forma
    EXACTA de `PJ_ENTRADAS[zona]` de `contenido.js`; (3) **el modelo** del molde, que saca `$z` de `$hechos` y
    `lang/*/paginas.php` (cada cifra de su hecho, `:marcadores` en los textos). Así lo que se VE se juzga con los
    datos del diseño, y las cifras se prueban aparte contra `contenido.js`.
  - **Estilo por CLASES** (`publico/instancia/css/entradas.css`, prefijo `pj-`), no en línea: un `:hover` no puede con
    un `style`, y el de la página (`a:hover`) pesa más que una clase sola. Lo que se mide, con `@container` sobre UN
    DOM (nunca dos `<h1>`); lo que abre, con `<details>` donde el DOM lo admita y, si no, JS mínimo de la instancia.
  - **El banco** (`scripts/banco-entradas.php`): A = la pieza React del diseño (su `.jsx` con Babel, `_ds_bundle.js`,
    `contenido.js`, `styles.css`) dentro del marco de la PÁGINA (`paginas/kids.card.html`: `.sec`, `.wrap`) y no el de
    las fichas de `sections/`, que llevan la isla (T4e) y los interruptores; B = la pieza Blade con el MISMO `$z`, la
    hoja de Saltia y `entradas.css`. Los dos con los corchetes como en nuestra página: sin oferta, precio de antes,
    JumpPoints, [Jump Club] ni [Bono] (`#699`); y sin las tres reseñas hasta la T2·9 del SPA. `pixel.mjs --rehacer
    --reloj` a 390 y 1280, por zona → **0**. Sin material, las fotos del panel (`#761`·3).
  - **Sub-tandas**: ·0 la guarda · ·1 el banco y las piezas 1-2 · ·2 la 8 y el pie · ·3 la 7 · ·4 la 6 · ·5 la 5 · ·6
    la 4 (los clips) · ·7 la 3 con el hueco de la calculadora (T4d) · ·8 el modelo: `/kids` y `/jump` enteras en local
    con los datos reales, en es/en/fr.
  - ▶ **·0 y ·1, hechas (24-09 noche)**. ·0: el caso de `InstancePagesTest` y su mutante (`prependLocation`, muerto).
    ·1: seis componentes (`button`, `link`, `day-rates`, `rating-summary`, `reassurance-strip`, `video-hero`), la pieza
    `web/entradas/piezas-1-2.blade.php`, `entradas.css`, el logotipo y `lang/*/piezas.php` (instancia `649fd66`); el
    banco, `scripts/banco-entradas.php`. **Medido: 16 de 16 pares a 0** (dos zonas, 390 apilada y 1280 superpuesta, en
    reposo y con el puntero sobre el botón, «Ver precios» y las reseñas). El juez aprendió a juzgar un `hover`
    (`pixel.mjs`: `pasar` y `movimiento`, porque con el movimiento reducido el tema anula el levantamiento). Controles:
    sin el botón a todo el ancho al apilar, 7.873 píxeles solo a 390; sin el `hover` del primario, caen solo sus 4.
    ⚠️ Al escribir la hoja: con `:hover` en la regla BASE, esa regla pesaba más que las de talla y variante, y un
    botón `xl` volvía al `gap` de 9 px al pasar; se declaran con `:hover` solo el color y el subrayado.
  - ▶ **·2, hecha (24-09 noche)**: `booking-cta`, `cta-band`, `opening-hours` y `site-footer`; las piezas
    `pieza-8.blade.php` (el cierre) y `pie.blade.php` (instancia `566ee64`). **Medido: 64 de 64 pares a 0** (las tres
    piezas, dos zonas, dos anchos, en reposo y con el puntero sobre cada botón y enlace). Dos trampas, las dos pagadas:
    (1) **los enlaces van DENTRO de frases** («Llámanos o <enlace> al <enlace>.»): un salto de línea al final del
    componente se pinta como espacio —su control da 806 y 4.596 píxeles en reposo—, así que `link` y `button` van sin
    nada alrededor, como `<x-lucide>`; y la frase se escapa y los enlaces se cosen en sus marcadores. (2) **El
    subrayado que aparece al pasar lleva el grosor `auto`**, medido en el diseño vivo: React reescribe
    `style.textDecoration` y ese atajo borra el 1,5 px (24 `hover` daban 60–179 píxeles hasta copiarlo).
  - ▶ **·3, hecha (25-09)**: `section-header` y `accordion` —el MISMO DOM del diseño: `<h3><button aria-expanded>` y el
    panel que anima `grid-template-rows`; se descartó `<details>`, que quita el encabezado y la animación—, la pieza
    `pieza-7.blade.php` y el primer JS de la instancia, `publico/instancia/js/entradas.js` (abre y cierra: `aria-expanded`
    y `data-abierta`). El banco gana ESTADOS por pieza (`clics` y `pasar`): abrir otra duda, cerrar la primera y pasar
    por «aquí» y por la puerta al cumpleaños. **Medido: 84 de 84 pares a 0**. Lo aprendido: `a:text-is()` de Playwright
    casa con el elemento MÁS PEQUEÑO (el `<span>` de dentro) y el puntero esperaba 30 s; el enlace llevaba un espacio al
    final («aquí »: el de entre dos directivas de Blade, que no se pinta en un `inline-flex` pero es otro texto), fuera
    con un eco vacío; y con el movimiento sin reducir, el `hover` de una flecha dejaba al DISEÑO distinto de sí mismo
    en el 1.er intento, así que el movimiento solo se juzga en los botones (su levantamiento es un token): el lote cuadra
    sin reintentar y `--reintentos 2` queda de red. Control: si el clic no cambia `data-abierta`, caen los 16 estados
    con interacción y los 4 de reposo siguen en 0 (instancia `e6b2028`).
  - ▶ **·4, hecha (25-09)**: `park-location` (en cristal sobre el mapa o, por debajo de 560 px, debajo y en tinta: el
    diseño se mide, aquí `@container`), `media-frame` en su forma de imagen y `pieza-6.blade.php` (instancia `b1e7728`).
    ❗ **El MAPA es material que no existe**: el diseño pinta su hueco de «Mapa pendiente» y la web nunca (`#761`·3),
    así que sin `mapa` va solo el panel, en tinta. Para juzgar la forma CON mapa, el `ParkLocation` del diseño recibe la
    misma imagen que el nuestro, envuelto en su espacio antes de montar (`antes` en el banco). **Medido: 12 de 12 a 0**
    a la primera; control: sin el `grid` de la forma estrecha, caen justo los 6 pares de 390. ▶ Del owner: la
    captura estática del mapa (con el pin y la salida de la carretera de Granada, como pide el diseño).
  - ▶ **·5, hecha (25-09)**: `proof-list` (los cuidados y su premio; con foto, al lado y estirada o, bajo 760 px,
    encima) y `review-panel` (se apila bajo 820 px), los dos con `@container`, y `pieza-5.blade.php` (instancia
    `b359232`). Tal como irá HOY: **sin las tres reseñas** (la T2·9 del SPA) **ni la foto que cuida** (material que falta),
    sin hueco; y la forma CON foto, construida y juzgada ya con una imagen que hace de foto. El A, el diseño con esos
    datos vaciados antes de montar. **Medido: 16 de 16 a 0** a la primera; control: sin la forma estrecha del panel,
    caen justo los 8 pares de 390.
  - ▶ **·6, hecha (25-09)**: `clip-tile` y `clip-list` en su forma de FOTO (sin vídeos, que son material que falta,
    y sin el triángulo de «play», que el propio diseño quita sin vídeo), `clip-viewer` —un ESQUELETO servido y oculto,
    con los clips en su JSON, que abre y rellena `entradas.js`: el foco a «Cerrar» a los 30 ms y de vuelta al cerrar, el
    `body` bloqueado, las flechas y las marcas, el teclado, el dedo, el fondo y la acción— y `pieza-4.blade.php`
    (instancia `8671b11`, con la ·7). En el banco, cada atracción recibe la misma foto a los dos lados (el diseño solo trae
    una por zona). **Medido: 26 de 26 a 0** (reposo, el `hover` de tarjeta y fila, el visor abierto desde una tarjeta y
    desde la lista, el segundo plano por su marca y, a 1280, por la flecha); la CONDUCTA, con `scripts/sonda-visor.mjs`:
    7 de 7 pasos iguales. Dos diferencias deliberadas, informadas y no juzgadas: tras usar una marca el diseño devuelve
    el foco a «Cerrar» (efecto de las dependencias de su `useEffect`; copiarlo haría que un segundo Intro sobre
    «siguiente» cerrase el visor) y la acción lleva al ancla ella misma (en el diseño lo hace la página). Controles:
    las marcas mal encendidas tumban los 10 estados del visor; sin Esc, la sonda cae en su paso.
  - ▶ **·7, hecha (25-09)**: la pieza 3 en su forma **SIN la calculadora** (`widget={false}`, un estado que el propio
    diseño trae: la tabla en su columna y, al lado, «Cómo funciona» con los pasos y el botón), mejor que un hueco;
    la calculadora es la T4d y entra en esta misma retícula. `rate-table`, `step-list` (su camino) y **`dinero`, la
    única mano que escribe importes** (`#677`): `Number::currency()` de Laravel con la regla del diseño —sin decimales
    si es entero—, medido con los mismos bytes que el diseño en español («8 €», «14,40 €», con U+00A0). **Medido: 8 de
    8 a 0**; control: con dos decimales siempre («8,00 €»), caen los 8. ⚠️ Su botón va a `#reservar`, como en el
    diseño: abrir la compra desde ahí es de la T4e.
  - ▶ **·8, el modelo, con tres respuestas del owner** (`#763`, 25-09): el precio de los calcetines sale de un HECHO
    (ya existía: la ficha de cada producto publica `addons[].price_cents`); las edades de 14 y 16 son texto de
    PlayJump (el sistema no aplica esos umbrales); los días de cada tarifa, con la etiqueta del panel. **·8a ✅**:
    `PageFacts` gana `product_details` —las fichas del catálogo, una por producto y en su orden, con el mismo JSON que
    `GET /catalog/products/{id}`— sin API nueva ni contrato (`InstancePagesTest`, su mutante —la lista en vez de las
    fichas— muere). **·8b ✅** (25-09): `web/entradas/modelo.php` de la instancia —lo `require` el molde por el buscador
    de vistas del espacio `instancia`, la misma raíz validada— saca `$z` de los hechos y de `lang/<idioma>/paginas.php`,
    donde cada cifra es un MARCADOR: las de la zona sin prefijo y las de las dos con `kids_`/`jump_` (cada página nombra
    a la otra). Una mano por cosa: `dinero`, y en el modelo las alturas («90 cm», «1,30 m»), las duraciones, las horas y
    los días. Reglas medidas, sin nombres ni ids: los calcetines son el único complemento de TODAS las entradas de la
    zona; su pack de cumpleaños, el de la zona de cumpleaños con su misma edad mínima; las atracciones, en el orden del
    panel. `/kids` y `/jump` enteras en local, en es/en/fr (seis en 200, cero marcadores sueltos, registro limpio).
    **Medido** con `scripts/modelo-entradas.php`: con los hechos reales y las cifras del brief, 140 (Kids) y 129 (Jump)
    campos IGUALES al brief y solo las diferencias decididas (días de la tarifa especial, «Toca una y mírala» sin vídeos,
    atracciones del panel, «quedan huecos» de la T4e); y 13 hechos movidos mueven cada uno sus textos —control: una edad
    tecleada en un texto la caza la prueba 2 y no la 1—. De paso, un defecto del PRODUCTO: el plazo y «con un adulto»
    se escribían con espacio normal entre cifra y unidad («24 h»); ahora duro, como pide el diseño, con su guarda en
    `CatalogTest`. Dos trampas del traductor: un `false` suelto en un fichero de textos devuelve la CLAVE (verdadera), y
    un `null` dentro de un arreglo pasa por `strtr()` (obsoleto). ▶ Del owner: escribir «De lunes a jueves» en la etiqueta
    de la tarifa normal (hoy «Lunes a jueves»), ordenar las atracciones como el brief en el panel, y revisar en/fr.
  - ▶ **La foto de la pieza 5, PROVISIONAL** (`#764`, el owner, 25-09; corrige `#761`·3 solo aquí): la de una atracción
    del panel que enseña cómo se cuida —Kids, el circuito de obstáculos (el suelo acolchado); Jump, los cascos junto al
    rocódromo (Circuito High)—, elegida por el modelo (material de PlayJump, no un hecho), con su texto alternativo en
    es/en/fr y el encuadre mirado a 1280 y a 390. La forma con foto ya era 16 de 16 a 0 (re-juzgada: igual); el modelo
    contra el brief, igual (la foto no va en `$z`: `p5.foto` del diseño es una nota). Se cambia al llegar la del brief.
- **T4a · los datos** (producto): las dos columnas de `#699` con su campo en el panel, sus hechos en
  `/catalog/products` y `/catalog/zones` (contrato 1.26.0), sus pruebas y su mutante; y la LISTA de reseñas en
  `/social-proof`, con la misma regla de permiso y la misma línea legal que la portada de hoy. El texto del paso de
  pagar del cajón (`tickets.pay_policy`, 5 días) pasa a leer el dato: fichero del SPA, avisado ANTES.
- **T4b · la ruta y el layout** (producto + instancia): `config/paginas.php` (futuro) de la instancia (slug, vista, hechos,
  título, descripción e imagen para Google y WhatsApp), el controlador genérico en el grupo `web`, el layout limpio
  y el sitemap; `CONTRATO` 3. Se demuestra con `/kids` y `/jump` sirviendo un esqueleto y las guardas de siempre
  (`no-store`, visitante, CSP) en verde.
- **T4c · las piezas** (instancia): las ~22 piezas y el molde en Blade, con sus textos en `lang` de la instancia y
  cada cifra de su hecho. Se juzga con un banco de la página: cada ficha de `sections/` contra la nuestra con los
  mismos datos → 0 píxeles, como en la T1→T3. Lo interactivo (el visor de clips, el acordeón) en JS mínimo de la
  instancia.
- **T4d · la calculadora** (producto): la pieza 3 en Vue sobre el motor y la lógica de la pantalla 0, el calendario
  de mes, «Reservar y pagar» a «Tus datos» de la isla con la línea ya en la cesta, y «Reservar para hoy» con hoy
  elegido. El total, siempre de `/orders/quote`. Banco de sus estados → 0 px.
  ▶ **Afinada al medirla (25-09)**. HAY la lógica (`usePantallaCero` sobre el motor: el borrador, los días de TODAS
  las filas, las horas con la cesta —`AFORO-02`—, la hora que ya no cabe se vacía) y cuatro piezas; FALTAN el
  calendario de mes (`AvailabilityCalendar`, 9 KB de JSX), la VISTA del widget (`PrecioEntradas`: cinco preguntas,
  `PriceSummary`, `ShareRow`), sus 12 textos (de la instancia) y el montaje. **Medido**: la página no carga hoy nada
  del producto; el motor llega por `import()` al abrir (219 KB, 60 KB comprimido; Vue y Pinia, 31; la compra de la
  isla, 36), y una página que no es del producto lo arranca con `cajon/paquete.js` (F4·T5) y `/sidebar/boot`.
  ⚠️ **Precisión**: el total no se pide a `/orders/quote` (la CESTA, en «Pagar»): es la `line` que resuelve el servidor
  (`/catalog/products/{id}/addons`), la misma de la pantalla 0 y «del mismo cálculo que `orders/quote`» (su contrato)
  —`unit_price_cents` y `subtotal_cents` de las entradas, el `subtotal_cents` de cada complemento y `total_cents`; sin
  sumas en el cliente, `PAY-12`—. Los días, de `/availability/{id}/dates` (`price_cents`, `rate_key`): libre y especial; el resto,
  cerrado; la ilimitada ya no se vende en día especial (dato), sin regla en el cliente. Decidido (técnico, `#630`):
  (a) **producto** (`resources/js/isla/calculadora/`) sobre `usePantallaCero`: una sola fuente de aforo y dinero; la
  página da el sitio, la zona y sus textos. (b) **Antes de montar, el hueco de carga del diseño** (`Skeleton`) en el
  sitio de las preguntas y del total: el motor se pide al acercarse la pieza 3 y en el acto si se llega a `#precio`; se
  mide el cambio en vivo. ⚠️ Corregido en la ·2 (se había escrito «la forma SIN calculadora»): sin JS la compra entera
  no funciona —el cajón es JS—, así que esa forma dejaría un botón muerto y, al montar, movería la tabla de sitio.
  (c) Lo quieto sigue en Blade (la tabla, el aviso de grupo y las dudas); Vue pinta preguntas y resumen, dentro de la
  misma retícula. (d) «Reservar y pagar»: la línea a la cesta como «Continuar» de la pantalla 0 y la compra de la isla
  en «Tus datos». (e) «Reservar para hoy» y «Hoy, 1 hora cuesta…», con la T4e (el owner, 25-09).
  **Sub-tandas**: ·1 el calendario (su vista pura y sus estados a 0 px) · ·2 la vista del widget contra
  `PrecioEntradas` con los datos del diseño (vacío, el ejemplo, día sin hora, la ilimitada, hora apagada; 390 y
  1280) · ·3 sobre el motor (`node --test`: el dinero del servidor, la hora que no cabe) · ·4 el montaje y «Reservar
  y pagar» (guardas; aviso al SPA si se toca `sidebar/`) · ·5 la sonda en vivo.
  - ▶ **·1 ✅ (25-09)**: `ui/CalendarioMes.vue`, el `AvailabilityCalendar` del diseño en Vue con su estilo en línea
    (como las demás piezas de la isla): el mes con la semana en lunes, «hoy» con la palabra, el punto de la tarifa
    especial, los completos tachados, la leyenda, el `hover` de un día libre y los meses con sus topes. Lo que decide,
    en `ui/piezas.js` (`celdasDelMes`, `estadoDia`, `mesDesplazado`, `vistaCalendario`; 4 casos de `node --test`: el
    año que cambia, el mes que empieza en lunes, el completo que no se pulsa, los nombres accesibles) y sus estilos en
    `ui/estilos.js`: el guardián `CE-6` lo tumbó con 62 líneas de código en el componente (techo 40) y se sacó, sin
    excepción. Los nombres del mes y de los días, de `Intl` (en español,
    los del diseño, medido); los rótulos, `pieza.calendario` de `lang/*/isla.php` (en/fr a revisar; «aujourd’hui» es
    largo para su casilla: a mirar con la T4d·2). Sin «Avísame si se libera»: nadie lo usa aún. **Medido**, en el
    banco de piezas (5 casos de la instancia —el vacío de la página, el ejemplo con completos, hoy especial elegido, dos
    meses, sin leyenda— y dos variantes, el puntero encima y «Mes siguiente»): **3 de 3 a 0 a la primera**, y el banco
    entero 21 de 21; control: 1 px más de alto por día, los 3 caen.
  - ▶ **·2 ✅ (25-09)**: la VISTA del widget, en `resources/js/isla/calculadora/` —`CalculadoraEntradas` (las preguntas
    donde se monta y el total, con `Teleport`, en el lado que la página declara), `CalculadoraPreguntas` (las cinco, con
    sus ecos y pistas), `CalculadoraResumen` (el recibo, «Reservar y pagar» apagado hasta tener día y hora, la línea
    de la cuenta y compartir) y `PreguntaCalculadora`; sus estilos, en `calculadora/estilos.js`—. PINTAN: todo llega
    en `v` y suben `cambiar(campo, valor)` y `reservar`. Dos piezas nuevas de la isla: `EtiquetaSistema` (`Tag`) y
    `FilaCompartir` (`ShareRow`; solo el icono de WhatsApp está en `iconos.js`: la compra va a 129,66 de 130 KiB, y los
    de correo y copiar entran con su consumidor). En la instancia, la forma CON calculadora de la pieza 3 (la tabla
    encima; los sitios `data-jw-calculadora` y `data-jw-calculadora-lado`; el aviso de grupo, `info-callout` nuevo,
    y las dudas, sin el hueco de los festivos) y `.pj-ic` en `entradas.css`. ⚠️ La cifra en columna del diseño
    (`.pj-num` de `tokens/base.css`) es `tabular-nums`, no la mono: la calculadora la lleva en línea. **El banco**:
    pieza `calculadora` de `banco-entradas.php` —A, `PrecioEntradas` como lo monta la página del diseño; B, la pieza
    Blade con la vista montada y la lógica del diseño TRANSCRITA (`scripts/banco-calculadora/diseno.js`, sus sesiones
    inventadas incluidas)—, con siete estados pulsados igual a los dos lados (reposo, día, hora, todo elegido, dos
    horas, la ilimitada que vacía el día especial, la ilimitada con hora; los dos últimos, solo Kids). **Medido: 24 de
    24 a 0 a la primera**; el banco entero de la página, 170 de 170; el de piezas, 25 de 25 (el `Tag` y el `ShareRow`
    con su `hover`); control: 1 px más de separación en cada pregunta, los 24 caen. Sin juzgar: el «Enlace copiado»
    (dura 2,6 s y el juez asienta más).
  - ▶ **·3 ✅ (25-09)**: la vista DE VERDAD —de los hechos, del motor y de los textos—, en dos mitades. **La página**:
    los textos del widget pasan a `lang/<idioma>/paginas.php` de la instancia (preguntas, «niño», cuántos, calcetines,
    la cuenta, el botón, compartir, las dudas; el grupo `comun.calculadora` para lo que el diseño deja escrito en su
    pieza y NOMBRA a PlayJump: «Pares de calcetines», «Un par para cada niño», las cantidades en letra, el mensaje), con
    cada cifra de su hecho; los días de tarifa especial de las dudas, del PANEL (`#763`: su etiqueta, y «V–D» de sus
    días de la semana); y el modelo arma lo que la pieza le dará al producto (`calculadora`: filas con su «desde» en
    céntimos, su pista y su línea, las dos columnas, los textos y el cierre de cada día). **El producto**:
    `calculadora/vista.js` (pura; lo genérico en el grupo `calculadora` de `lang/<idioma>/isla.php`) —el dinero, SOLO
    del servidor: el precio del día, la `line` y el `charged_cents` de los calcetines, que el endpoint de complementos da
    aun sin día ni hora—; el botón se enciende con la línea del servidor (`listo`), no con tener día y hora. **Medido**:
    `scripts/modelo-entradas.php` gana su parte 3 —la vista del producto con lo que da la página (el modelo REAL, los
    hechos del brief) contra la del diseño, estado a estado (`scripts/calculadora-contra-diseno.mjs`)—: **11 de 11
    (Kids) y 9 de 9 (Jump)** iguales; la parte 1, 162 y 153 campos iguales al brief (los textos del widget ya se
    comparan); la parte 2, 16 hechos movidos (tres nuevos) que mueven sus textos. `node --test`: 8 casos de la vista
    (el dinero que no se cuenta, la línea que aún no llega, la hora que no cabe, el cierre de cada día); mutante —el
    total como «n × precio»— muerto. Controles: una edad tecleada en una nota, la cae la parte 2; el fin de la sesión
    mal contado, 10 fallos en la parte 3. El banco, 24 de 24 tras el cambio del botón. En en/fr, sin marcadores
    sueltos y con las etiquetas del panel traducidas. **Distinto por decisión** (principio de `#763`): el recibo nombra
    el complemento como el panel, «Calcetines antideslizantes», como lo dirá «Pagar»; ▶ del owner, si lo quiere más
    corto, el nombre del producto en el panel. ⚠️ Lo del MOTOR (el borrador sin día de fábrica, vaciar el día al elegir
    una fila que no se vende ese día, pedir el cargo de los calcetines) va con el montaje, en la ·4.
  - ▶ **·4, diseñada al medirla (25-09)**. **Medido**: el layout limpio (`components/pagina.blade.php`) no carga JS del
    producto; la cesta se PERSISTE (`localStorage`, `stores/cart.js`), así que dos apps de Vue con su Pinia leen la
    misma; `cajon.openWith(intent)` → `machine.queueIntent` (no filtra el tipo) → la compra de la isla la toma
    (`useSeccionCompra::applyIntent` → `borradorDeIntencion`): **una intención nueva no toca ningún fichero del SPA**;
    la carcasa, del ajuste `sidebar.shell` (en local, `isla`: dato). **Decidido** (técnico): (1) la calculadora es su
    PROPIA app —Vue, Pinia y los stores de la OFERTA del motor (`catalog`, `time`, `selection`, `cart`) con las
    peticiones de `compra/oferta.js`—, sin `usePurchaseFlow` (el alta y el cobro no son suyos): la oferta con la misma
    fuente que la compra y un trozo pequeño; (2) su entrada (`isla/calculadora/montar.js`) la pide la página por su
    NOMBRE con el cargador del cajón (`scripts` de `<x-pagina>`: `cajon`, `calculadora`; otro nombre no carga nada), y
    **se PINTA al cargar y PIDE al acercarse** (corregido al medir: la pieza 3 empieza a 1,03 pantallas en 1280 y a
    1,22 en 390, así que «montar una pantalla antes» era montar al llegar): la vista sale entera con lo que da la página
    —sus filas con su «desde», sus textos y sus calcetines, del hecho—, sin hueco ni salto, y los días, la ficha y las
    horas esperan a que la pieza esté a 300 px, al primer toque o a nada si se llega a `#precio`; (3) «Reservar y
    pagar» = `JumpWeb.cajon.openWith({ type: 'linea', id, date, time, quantity, addons, continuar: true })`: la compra
    de la isla sitúa su pantalla 0 con esa selección y sigue sola a «Tus datos» (la línea se valida y entra en la cesta
    por su camino de siempre); (4) el borrador nace SIN día (`inicio="vacio"`), y elegir una fila que no se vende ese
    día vacía día y hora. (5) **La cesta se lee con su TITULAR**: el layout da el `auth()->id()` del arranque del motor
    (leerla con otro la PURGA, `cart.js::decideOwnership`), y la calculadora nunca la guarda.
  - ▶ **·4 ✅ (25-09)**: `calculadora/useCalculadora.js` (la oferta en cola, el cargo de los calcetines sin hora
    —`calculadora/cargo.js`—, `pendiente` que apaga el botón mientras hay respuesta en camino) y `montar.js`; en la
    compra de la isla, la intención `linea` (`oferta.js::borradorDeIntencion`) y seguir sola a «Tus datos»
    (`useSeccionCompra::empezar`, FUERA de la cola: dentro se esperaría a sí misma); `cajon.openWith` cuenta su
    producto; `<x-pagina>` gana `scripts` y `#jw-calculadora-motor` (`InstancePagesTest`: los nombres y el MISMO titular que el
    arranque; mutante —el titular siempre `null`— muerto); la instancia pide las dos entradas y le da la pieza. **El peso, medido** (`SidebarBundleBudgetTest`): la calculadora descarga
    **169,37 KiB (57,54 comprimidos)** —100,2 compartidos con el motor, que quien compra después ya no baja— y su
    guarda nueva exige que no traiga el motor ni la compra (control: importar el motor la tumba). ⚠️ Lo que SOLO usa
    la calculadora viajaba con la compra desde `piezas.js`, `estilos.js` y `oferta.js` (la compra, 136,48 KiB): se
    mudó a `ui/calendario.js`, `ui/compartir.js` y `calculadora/cargo.js` (131,89). Construyendo SIN la entrada: el
    motor, 295,28 (igual que antes: ni un byte de código) y la compra, 131,18 (+0,28, la intención); con ella, la
    costura de los trozos compartidos: el motor 296,12 y la compra 131,89 —techos a 297 y 133, con su medida en el
    test—. **En vivo** (`scripts/sonda-calculadora.mjs`, Kids y Jump, 1280 y 390): **8 de 8 en cada recorrido** —al
    llegar ya está PINTADA sin haber pedido nada; al bajar pide los días de sus filas; día y hora: el total que se pinta
    es el `total_cents` del servidor; con calcetines, su línea y el total del servidor; «Reservar y pagar» abre la
    compra en «Tus datos» con ESA línea en la cesta; consola limpia; ninguna otra respuesta de error—. Todo el
    recorrido cuesta **23 peticiones** (Kids; 21 Jump) contra un suelo de 60 por minuto y por IP: dos recorridos
    seguidos lo agotan (429: la trampa, en `TESTING.md` §2.octies). ⚠️ Los dos 401 de la consola son de la ADMISIÓN
    del motor (`admission.js::runCheckout`: `/me` y `/me/reservation-eligibility` sin sesión), esperados. ⚠️ **Para la
    T4e**: la compra vuelve a pedir 6 cosas que la calculadora ya tenía (los días, la ficha, las horas, la línea: otra
    app, otra Pinia); y sin respuesta de la API el calendario sale entero CERRADO —un aviso o un reintento—.
- **T4e · la isla en la página** (producto): montada al cargar en su propio trozo (el motor, 295 KiB, sigue
  llegando al pulsar), el contrato de §4.3 fijado, sus hechos reales, cede su botón con uno de la página a la vista,
  y se transforma en la compra. Techo de peso con su prueba; aviso al SPA (su T5c y el consentimiento).
  - ▶ **·1 ✅ (25-09)**: la transcripción de `paginas/entradas/pagina.jsx` (el mockup, `#767`). **Producto**: la entrada
    `isla/pagina/montar.js` (nombre `isla` en `scripts` de `<x-pagina>`, que emite `#jw-isla-pagina`: lo de la página
    más la sesión, la política de cookies y los textos de la isla sin la compra ni la calculadora); lo que decide, puro,
    en `pagina.js` (los huecos, la geometría, las props; 8 casos) y lo que cambia, en `usePaginaIsla.js`. **El contrato
    §4.3, fijado**: el SITIO `[data-jw-isla]`, primer hijo de la columna de la página y montado con `display: contents`
    (la isla va `sticky`, arriba en escritorio y con `order` abajo en móvil; montada al final del `<body>` quedaba al pie,
    medido), `data-isla-cta` en los primarios (cede su botón con uno a la vista, fuera de su franja) y `data-hoy-linea`
    de la pieza 6 (calla [Hoy] si ya lo dice). **Hoy**: el estado (antes, abierto o cerrado con la hora de mañana) lo
    da el modelo de la página; «Quedan huecos» sale de UNA petición tras cargar (`POST /availability/{id}/times`, la
    primera fila). ⚠️ Se midió y DESCARTÓ resolverlo en el servidor: `ProductCatalog::product()` no memoriza, y el hecho
    hacía 176 consultas y 160–180 ms por visita (más que todos los hechos de la página): **deuda**, que también paga
    `product_details`. **La calculadora ↔ la isla**, por eventos del documento: `jw:calculadora` (lo que falta y lo
    elegido, solo tras tocarla: `vista.isla`, su caso) y `jw:calculadora:hoy` («Reservar para hoy» elige hoy). **Las
    cookies**: el MISMO almacén (`ui/cookie-consent.js`) sobre el `<body>` de la T4b·4, con `consent_shown`; ⚠️ el
    mockup NO dibuja la segunda capa (categorías una a una): «Configurar» lleva a `/cookies` hasta que la dibuje (del
    owner). **Peso**: la entrada, 153,69 KiB (51,1 comprimidos), techo 156; con la calculadora y el cajón, el JS de Kids
    pasa de 62,6 a 78,3 KiB comprimidos (idea para después: pedir la calculadora al acercarse). **En vivo** (390 y
    1280): arriba «Abierto hasta las 21:30. Quedan huecos.» y cede ante la cabecera; con la pieza 6 a la vista, calla
    [Hoy]; «Reservar para hoy» baja al precio, la calculadora elige hoy y la isla pasa a «Elige la hora»; consola limpia.
    Sigue ·2: la transformación en la compra y el menú (Mi QR, Mi cuenta, Entrar), comprobados en vivo.
  - ▶ **·2 ✅ (25-09)**: **la transformación**: con la compra abierta (`jw:cajon:open`) la isla en reposo se aparta y la
    de la compra ocupa su sitio; al cerrarla (`close`) vuelve. **El menú**: «Entrar o crear cuenta», Mi QR y Mi cuenta
    abren su zona (`login`, `card`, `home`) en el LATERAL con `cajon.openAccount` —el mismo camino que el menú de
    siempre— hasta la T5; su caso en `pagina.test.js`. ⚠️ **Medido y arreglado**: el lateral salía SIN estilo al pie de
    la página, porque `<x-pagina>` ponía el cargador del paquete sin su hoja (`#636`: son dos líneas): ahora, con
    `cajon`, `css/cajon.css`, y la huella de estilo de `/kids` con y sin ella da **0 elementos distintos** de 1.029 (390)
    y 1.026 (1280), la isla incluida; su guarda, en `InstancePagesTest`. **En vivo** (1280): aceptar las cookies; el menú
    → «Entrar» abre el lateral y la isla se aparta; al cerrar, vuelve; día y hora → la isla dice «Sáb 26 · 11:00 · 1 niño
    · 8 €» sin botón (el de la calculadora se ve); «Reservar y pagar» → una sola isla, la de la compra, en «Paso 1 de 2 ·
    Tus datos»; consola limpia. ⚠️ **El lateral de la cuenta se ve con el tema NEUTRO del producto** (los botones negros
    del producto, no Saltia): lo arregla la T5 (Mi cuenta en la isla), que el owner decide si entra en la v2.0.0.
  - ▶ **·3 ✅ (25-09, pedidos del owner)**: (a) **«Hoy, 1 hora cuesta…»** —la cabecera, «dónde y cuándo», el cierre y
    la isla lo decían con `huecos` fijo a `false`—: el hecho `availability_today` (las horas de hoy de cada ENTRADA, el
    mismo JSON que `POST /availability/{id}/times`) lo resuelve en el servidor llamando al servicio —15–22 ms; por el
    controlador eran 160–180 por `ProductCatalog::product()` sin memorizar— y el modelo decide los huecos de la zona; la
    isla los recibe en `today.slots` y se retira su petición tras cargar. Con huecos sale como el mockup; sin ellos (de
    noche), el «desde». Su caso, en `InstancePagesTest` (el mismo JSON, solo entradas, con horas de verdad). (b) **El
    mapa de GOOGLE** en «dónde y cuándo» en vez de la captura: `park-location` gana `embed` (el hecho
    `address.maps_embed_url`, ahora SANEADO por `MapsEmbed::clean`, su caso en `SiteFactsTest`), en el mismo marco y
    con el panel encima; es de la categoría `maps` —consentido, el iframe desde el servidor; si no, un hueco con «Cargar
    el mapa» que se lo pide a la isla (`jw:cookies:conceder`) y carga al confirmarlo el servidor (`cookies-updated`)—.
    En vivo a 1280 y 390: hueco → consentimiento 200 → el mapa, sin recargar; consola limpia.
  - ▶ **·4 ✅ (25-09, encargo del owner: «dibújala tú con el sistema»)**: **la SEGUNDA CAPA de las cookies**, que el
    mockup no dibuja, dentro de la isla: el panel «Tus cookies» (`piezas/PreferenciasCookies.vue`) con las mismas piezas
    que la primera capa —los dos botones tranquilos al MISMO nivel, «Aceptar todo» y «Rechazar todo» (AEPD), y sus
    enlaces— y cada finalidad con el **interruptor del sistema** (`ui/InterruptorSistema.vue`, el `Switch` del mockup,
    portado con su forma sobre tinta: roles de la isla); la regla del `Switch` decide el resto: se aplica AL MOMENTO, sin
    «Guardar», con el aviso «Guardado» de la isla, y si el servidor no confirma el interruptor vuelve a como estaba. Los
    textos de cada finalidad, los LEGALES de siempre (`cookies.panel`). La abren «Configurar» del aviso, «Cookies» del
    menú y «Configurar cookies» del pie —estos dos, TAMBIÉN ya decidido: retirar el consentimiento tiene que ser tan fácil
    como darlo; el mockup solo lo abría con el aviso pendiente—; con ella abierta, la primera capa se esconde. En vivo a
    1280 y 390: el panel con sus cuatro finalidades apagadas, encender «Mapa y reseñas» → POST 200, «Guardado» y el mapa
    de «dónde y cuándo» cargado; menú y pie → «Tus cookies»; consola limpia. `pagina.test.js` +2. El panel va
    DIFERIDO (su trozo, 4,74 KiB, llega al abrirlo): estático, la isla en reposo y la compra pasaban sus techos.
  - ▶ **·5 🟦 (26-09, el owner: «falta el botón de play en las atracciones»)**: el triángulo va SOLO sobre un vídeo de
    verdad (`ClipTile.jsx`), así que el play llega con el DATO: `attractions.video` (ruta del disco `uploads`), su
    subida en el panel («Vídeo», MP4/WebM hasta 12 MB, el tope de Livewire), `video_url` en `/attractions` (contrato
    **1.30.0**) y, en la instancia, la tarjeta con el círculo de cristal y su triángulo, la miniatura de la lista con el
    suyo y el visor que lo reproduce (con sonido o, si el navegador no deja, en silencio; un toque pausa; pasar o cerrar
    lo para). En vivo a 1280 y 390 con una muestra en «Piscina de bolas» (Kids, LOCAL). ⚠️ La sonda usa Chromium sin
    H.264: prueba con WebM; los navegadores de verdad reproducen el MP4 que pide el panel. Falta el MATERIAL: los 4
    vídeos verticales de 4–6 s del brief, que sube el owner.
  - ▶ **·6 🟦 (26-09, `#771`)**: **las VOCES de la pieza 5**: el panel de la nota pinta las tres opiniones que el parque
    etiqueta con la zona (`/reviews`), con `review-card` (el `ReviewCard` del mockup, 1:1: cara o iniciales, estrellas
    amarillas, texto entero, fotos y «Ver en Google» abajo; firmadas «Nombre I.», fecha en relativo); estrecho, en fila
    que se desliza. Visto a 1280 y 390 con tres muestras LOCALES (borradas). Falta el DATO: copiar e importar las de
    la ficha (`google-reviews.md` §9) y etiquetarlas por página. ▶ **26-09**: con las REALES (18 publicadas); y, a
    petición del owner, **a una altura** (el texto a 5 líneas si sobra, con «Ver más» que abre solo esa tarjeta) y
    **las fotos en un visor propio** (`photo-viewer`, el lenguaje del de las atracciones). Lo que el mockup no dibuja:
    decidido por el owner, con el sistema.
- ▶▶ **LA VERIFICACIÓN FINAL de Kids y Jump (25-09, `#768`): 40 de 40 a 0 píxeles** contra el mockup del 25-09 —cada
  pieza en reposo a 390 y 1280, sin estados, ~5 min—. Cazó un defecto del port del zip: el margen del icono del parking
  iba al `svg` y `<x-lucide>` pinta una `<span>` que lo envuelve (instancia `d1114ad`). La isla, 60/60 en su banco; la
  compra, 52/54 («entrar», `#695`).
- **T4f · la sonda de las dos páginas** (`scripts/sonda-isla.mjs` crece): llegar, la isla en reposo, calcular,
  «Reservar y pagar», la pasarela; en 390 y 1280.

### 4.13 La T5: Mi cuenta en la isla — el censo (MEDIDO 26-09) y el plan (`#773`)

**Lo que pide el diseño** (`paginas/mi-cuenta.card.html`, `mi-cuenta-reglas.card.html` y `paginas/mi-cuenta/`: el
hook `usePmcCuenta` en `cuenta.jsx`, los bloques en `bloques.jsx` y `bloques-2.jsx`, las dos pantallas en
`pantallas.jsx` y los textos, TODOS aprobados el 24-09, en `datos.js`; en `readme.md`, sus siete secciones «Mi
cuenta · …»). Mi cuenta vive en la **capa grande** de la isla, la de la compra: el mismo contenedor, la misma flecha,
la misma X y la acción abajo. **Siete bloques, en orden**: arriba «Hola, Ana», la próxima en una línea y el chip
«Siguiente: …» · Tu QR (compacto; grande si la reserva es hoy) · Tu próxima reserva (`BookingCard`, «Ver el pago»,
«Cambiar o cancelar») · Antes de venir (una tarea cada vez) · Otras reservas, con el historial plegado · Reservar otra
vez · Quién viene contigo · Ajustes plegados (`Accordion`) y «Cerrar sesión». **Sus vistas**: Tu QR, Tu reserva,
Cambiar o cancelar, Añadir a tus hijos y su «Guardado», Contraseña, Correo, Borrar tu cuenta y, sin sesión, Entrar, el
olvido y Crea tu cuenta; los huecos de otros briefs (el formulario, los extras, las autorizaciones) son las páginas de
la fiesta (`#765`). **Sus reglas**: una tarea cada vez · la flecha solo si hay algo detrás, y vuelve al mismo punto ·
una acción principal por vista y lo destructivo nunca en naranja · los avisos se quedan hasta salir de la vista · cada
bloque protegido · sin conexión se avisa y se reintenta · todo sobre tinta. Se abre desde el menú, Mi QR, `/mi-cuenta`
y el enlace de un correo (`#mi-cuenta/<bloque>`).

**Lo que hay, medido** (la API `/me/*` en local, con `Sanctum::actingAs` sobre dos cuentas con pedidos; el motor):

| Pieza | Hoy en el producto | Veredicto |
|---|---|---|
| La capa y abrirla en su bloque | Con la isla, la cuenta abre el LATERAL (`carcasa.js::superficieDe`, «hasta la T5»). Las puertas de `AccountDoor`: `/mi-cuenta` y `/mi-cuenta/pedidos` (ahí aterrizan 8 notificaciones y 11 redirecciones del servidor) y las de invitado (`login`, `register`, `forgot`, `google-signup`) | **HAY** el camino (`cajon.openAccount` → `showAccount(zona)`); **FALTA** la sección de cuenta de la isla, como la de compra en la T3e·2 |
| El motor de la cuenta | 13 zonas del cajón sobre stores con su `node --test` (`card`, `reservations`, `orders`, `dependents`, `profile`, `credentials`, `privacy`, `waiver`, `accountContext`, `auth`) | **HAY**: la isla los usa SIN tocarlos, como la compra los suyos |
| «Hola, Ana» y la próxima en una línea | `/me/account-context` (`first_name`, `next_reservation`) y `/me/reservations/upcoming` | **HAY** |
| Tu QR: verlo, dictarlo, guardarlo, renovarlo | `/me/card` (20 caracteres; en grupos de cuatro para dictar), `/me/card/png` y `POST /me/card/rotate` | **HAY**. El QR es la PNG del SERVIDOR dentro del marco del pase (`identidad-qr-puerta.md` §0), nunca uno dibujado en el navegador |
| Tu próxima reserva y la abierta | `/me/reservations/{upcoming,past}`: día, franja, cantidad, complementos (los calcetines), estado, el libro (`ledger`: total, señal, resto en el parque, devoluciones) y `order.code` | **HAY** |
| «Puedes cambiar o cancelar hasta…», dentro o fuera | El plazo es POR PRODUCTO (`cancellation_cutoff_hours`, T4a·1) y la reserva no lo trae | **PARCIAL**: la fecha límite en la reserva (API aditiva) |
| Cambiar o cancelar | Lo hace el personal (`CancellationCutoffRule`: se informa, no se aplica); el teléfono, en `/site` | **HAY**: el mensaje escrito a WhatsApp y «Llamar» |
| Antes de venir | Por reserva: `needs_guest_form`, `guest_form_status`/`_url`, `guest_count_deadline`, `invitation_url`, `can_add_extras`; las autorizaciones, en `/orders/{code}/guest-minors`; los menores sin firma, en `account-context.waiver` | **PARCIAL**: «6 de 10 confirmados» no se publica y no hay lista de tareas con su plazo (§4.5, «A MEDIR con la T5»): se mide en su tanda |
| Otras reservas, el historial, la visita de hoy ya terminada | Los dos ámbitos de `mis-reservas-por-reserva.md`, con el corte de `isFinishedInPractice()` | **HAY** |
| Reservar otra vez | La reserva trae el nombre, no el producto; la compra de la isla acepta la intención `linea` (T4d) | **PARCIAL**: el producto en la reserva (API aditiva) |
| Quién viene contigo y Añadir a tus hijos | `/me/dependents` con la firma en el mismo gesto (`#441`). El alta exigía **apellidos** (`#236`) y la relación sale de un catálogo de **cinco**; el diseño pide nombre, fecha y cuatro tarjetas | **HAY**, con dos choques: (a) y (b) de abajo |
| Tus datos, el correo, el idioma | `PATCH /me` y el correo nuevo con su enlace (`pending_email`) | **HAY** |
| Contraseña (cambiar o crear), vincular Google, otros dispositivos | `PUT /me/password`, `/auth/google/vincular` (`#347`), `/me/identities`, `POST /me/sessions/revoke-others` | **HAY**; Apple, **FALTA** (`#683`: v2.0.0, en su tanda) |
| Novedades, el descargo, mis datos, borrar | `PUT /me/marketing`; `/me/waiver` (versión, fecha, PDF); `GET /me/export`, una DESCARGA; `DELETE /me`, que exige la CONTRASEÑA actual. Y dos que el diseño no dibuja: analítica y encuestas (`PUT /me/analytics`, `/me/surveys`) | **HAY** |
| Recibos | `/me/orders` con su libro | **HAY** el desglose |
| Entrar, el olvido, Crea tu cuenta | La compra de la isla los pinta (`PantallaEntrar`, `#695`) sobre `auth` y `register`, con la casilla del descargo | **HAY** |
| Completar el alta que vuelve de Google | `/registro/google`, zona `google-signup` del lateral | **HAY** en el lateral; el diseño no la dibuja |
| [Apple Wallet] · [Google Wallet] · [Descargar el recibo] · [Tu vale de 1 hora] | Nada detrás (el vale es de JumpPoints, SIN empezar) | **APAGADOS** (`#773`·c): corchetes sin hueco |

**Lo que decide la verdad y no el diseño** (`#630`): el QR es el del servidor · «Descargar mis datos» descarga, así
que su aviso es el de descargado (`descargado`) y no «Te enviamos tus datos al correo» · borrar la cuenta pide la
contraseña actual además de la casilla (lo exige el servidor) · y los interruptores de analítica y encuestas se quedan
en «Privacidad» junto a «Novedades»: retirar un consentimiento tiene que ser tan fácil como darlo (la regla de «Tus
cookies», T4e·4).

**Contestadas por el owner el 26-09** (`#773`, `[DECIDIDO owner]`): (a) los apellidos del menor dejan de ser
obligatorios —el formulario de la isla no los pide; la firma guarda el nombre que se declaró—; (b) la relación, las
**cinco** del producto en las tarjetas del sistema; (c) Wallet y el recibo descargable, **apagados** en la v2.0.0; (d)
lo que el mockup no dibuja (completar el alta de Google, la contraseña al borrar, los dos interruptores), **con las
piezas del sistema**, enseñado en vivo antes de cerrar.

**El plan, en seis sub-tandas** (cada una con su prueba y su medida; la identidad con el mockup, una vez y al final,
`#768`):
- **T5a · la capa y sus puertas**: con la isla, la cuenta deja de ir al lateral (`superficieDe`) y la raíz del motor
  monta `isla/SeccionCuenta.vue` (su trozo, diferido) en el sitio de `AccountSection`; lo que decide —la vista, el
  origen, la flecha, el mapa de las zonas de `AccountDoor` y el ancla del correo— en un módulo puro con su `node
  --test`. Dentro: «Hola», la próxima en una línea, Tu QR (compacto y su vista) y, sin sesión, Entrar, el olvido y
  Crea tu cuenta. Aviso al SPA (su raíz; sus stores, sin tocarlos).
- **T5b · las reservas**: la próxima y la abierta, «Ver el pago», Otras y el historial, Cambiar o cancelar; en la API,
  la fecha límite de cambio y el producto de cada reserva (aditivo: el siguiente menor del contrato).
- **T5c · Antes de venir**: el censo de las tareas y sus plazos, lo que haya que publicar, el chip y el punto de la isla.
- **T5d · Quién viene contigo y Añadir a tus hijos**, con (a) y (b): `DependentRegistry` es del `CRITICAL_RE`
  (`VERIFY_CONC=1`) y el formulario del cajón es del SPA (aviso).
- **T5e · Ajustes y Cerrar sesión**, con (d).
- **T5f · lo que queda**: Reservar otra vez, la bienvenida de la cuenta nueva, sin conexión, los bloques protegidos,
  Mi QR del menú, el alta de Google (d), la verificación final y la sonda (`scripts/sonda-isla.mjs` crece).

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
