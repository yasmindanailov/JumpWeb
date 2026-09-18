# [SPEC] El cajón empaquetable — del layout del producto a un paquete con contrato (F4 del programa)

> Estado: ✅ **aprobada, EN EJECUCIÓN por tandas** (`DECISIONES #631` lo técnico y el principio, `#632` las tres
> opciones de producto, `#633` quién implementa); la lectura del SPA se pide en paralelo y ya no es condición ·
> Última actualización: 2026-09-18.
> Carril: **plataforma**, diseño E implementación (`[DECIDIDO owner]` `#633`: el SPA está con la invitación
> digital; se entra en sus ficheros avisando y por tandas T1–T5, `carriles/plataforma.md`).
> Origen: `specs/producto-e-instancias.md` §4.2. Hermana: `specs/token-bearer.md`.

## §0 · Antes de tocar

- **Regla que ordena todo**: el cajón sigue siendo un cajón SOBRE la landing, en el MISMO dominio que la API
  (cookie de sesión + CSRF, sin token). Cambia QUIÉN lo monta: del layout del producto a cualquier HTML que
  cargue el paquete. «Empaquetable» no es «página aparte» ni «otro dominio».
- **«Una línea del layout» era falso, medido (§1)**: le pedía a su página la carcasa de Blade, un store de
  Alpine que llega DENTRO de Livewire, un `data-boot` de 18,7 KB, una hoja de 549 KB, los tokens de OTRA hoja y
  ocho rutas-puerta. Las tandas lo deshacen una a una.
- **El riesgo silencioso es la hoja** (la T4): los `.vue` llevan CERO `<style>`; 81 bloques del cajón viven solo
  en `site.css` y **10 están definidos en las DOS hojas** (§1). Partirla puede mover el cajón sin que falle un
  test: manda la huella de maquetación (`#437`), no la suite.
- **Trampas**: tras tocar un `.vue`, `npm run build:ssr` antes de la suite; el contrato visual es el ÁRBOL
  (`specs/sidebar-spa.md` §4.2); `route('logout')` aparece UNA vez, en el suelo del hueco de cuenta
  (`specs/account-context-vue.md` §4.8); el chunk tiene techo.
- **La landing consume un MENÚ DE HECHOS opcional** (`[DECIDIDO owner]`, §4.6): el cajón no depende de que
  lea nada; lista blanca por `Resource`, jamás un volcado de `settings` (lleva secretos).
- **Estado**: por TANDAS — **T1 ✅** arranque · **T2 ✅** apertura · **T3a ✅** carcasa con dueño · **T3b ✅** el
  paquete se monta en una página ajena (`installCajon()`) → **T4 la hoja propia** → T5 la salida. Implementa
  plataforma (`#633`). ⚠️ **Dónde va cada cosa**: un rótulo nuevo, en `SidebarBoot`; abrir y cerrar, en
  `cajon/controller.js`; la carcasa, en `shell.js`; lo que solo usa una página ajena, en `standalone.js` (la
  entrada del producto tiene presupuesto). El motor NO nombra a Alpine ni conoce los pasos del embudo.
- **Empieza por** §1 (el censo) → §4.1 (la forma) → §4.2 (la apertura) → §4.5 (el arranque) → §4.6 (el menú).

## 1. Contexto y problema — MEDIDO (2026-09-18)

Lo que el cajón necesita HOY de la página que lo aloja (`resources/views/components/layout.blade.php`, 603 líneas):

| # | Dependencia | Medida | Dónde |
|---|---|---|---|
| 1 | **Carcasa** pintada por Blade: `.sidecart` (telón, panel `role="dialog"`, cabecera con título traducido y cierre), el hueco `#sidecart-account` con su SUELO (el único `route('logout')` de la aplicación, con `@csrf`) y el hueco `#sidecart-spa` | ~440 líneas del layout | layout |
| 2 | **Apertura**: `Alpine.store('purchase')` con `open`, `openWith`, `openAccount`, `close`, `isOpen`, `mode`; trampa de foco (`a11yPanel`) y bloqueo de scroll | 23 usos en 9 vistas Blade (8 `openAccount`, 6 `open`, 3 `openWith`) | `resources/js/app.js` |
| 3 | **Alpine llega dentro de Livewire**: `app.js` no lo importa, usa el global que expone `@livewireScripts` | una landing ajena necesitaría el JS de Livewire solo para ABRIR el cajón | `app.js`, cabecera |
| 4 | **Arranque** `data-boot`, pintado por Blade en CADA página | 9 claves · 18.680 B de JSON (25.462 B escapado en un HTML de 300.949 B): `messages` 16.063 · `account` 2.609 · `urls` 320 · `auth` 181 · `ui` 26 · `outcome`, `orderCode`, `userId`, `accountContext` | layout |
| 5 | **Estado de entrada** en el `<body>`: `data-purchase-open` (puerta, `/entradas` o desenlace de pago pendiente en SESIÓN) y `data-account-zone` | `SidebarEntry`, `AccountDoor` | layout |
| 6 | **Hoja**: 0 `<style>` en los 51 `.vue`; 91 bloques BEM → 81 solo en `site.css`, 10 en `site.css` Y `landing.css` | `site.css` 548.988 B (62 % comentarios); ~44 % de los bytes de sus 1.598 reglas nombran un bloque del cajón | `public/css/` |
| 7 | **Tokens**: `site.css` lee 231 custom properties; `:root` se define en `landing.css` (123) y `site.css` (111); `client.css` las pisa por instalación | el cajón no tiene raíz de tokens propia | `public/css/` |
| 8 | **Guion**: `app.js` (90.616 B fuente → 23.642 B) trae el motor con `import()` en la primera apertura: `sidebar` 293.988 B + `calendar` 269.279 B | el `import()` ya existe: es la mitad del paquete | `public/build/assets/` |
| 9 | **Puertas**: 8 rutas sirven la portada solo para tener algo detrás del cajón: `/`, `/entradas`, `/login`, `/registro`, `/registro/google`, `/recuperar-contrasena`, `/mi-cuenta`, `/mi-cuenta/pedidos` (las dos últimas tras `auth`) | `route:list` filtrado por `HomeController` | `routes/web.php` |
| 10 | **Sesión**: meta `csrf-token`, cookie de sesión, mismo dominio | — | layout |

**Premisas del programa corregidas al medir**: `specs/producto-e-instancias.md` §4.2 dice «lo monta una línea del
layout» (son las diez filas de arriba), «nueve rutas» (son 8) y «32 bloques de sección del cajón» (son 91 bloques BEM).

**Lo que la landing lee hoy del panel y de la BD** (censo de `HomeController` y las 5 páginas — base de §4.6):
zonas y atracciones, tipos de entrada con precios y promo, packs de cumpleaños, dudas, bar, prueba social,
servicios, ofertas, identidad/contacto/horario (ajustes), fechas especiales, páginas legales y normas.
**Lo que la API pública ya sirve** (10 GET): `catalog/products`, `catalog/products/{product}`, `catalog/zones`,
`availability/{product}/dates`, `config`, `legal/waiver`, `booking/status`, y tres de flujo. `config` devuelve 5
claves, todas del cajón. **No hay** horario, legales por clave, normas, prueba social, identidad ni contacto.

## 2. Objetivo

1. Un HTML mínimo AJENO al producto (sin Blade, sin Livewire, sin Alpine) monta el cajón, lo abre en cualquier
   zona y completa una compra en local. Es el criterio de salida del programa para F4.
2. La huella de maquetación de las doce vistas a 1280 y 390 (el instrumento de `#437`) es **idéntica, 24 de 24**,
   antes y después de partir la hoja.
3. La landing del producto sigue funcionando sin cambios visibles: pasa a ser el PRIMER consumidor del paquete.
4. El producto gana un anfitrión mínimo para las puertas y los flujos con vista propia.

**Fuera**: sacar la landing del producto (F5), la API pública de lectura para la landing (F5, pero su alcance se
itera en §4.6), rediseñar el cajón, otro dominio o CORS.

## 3. Opciones consideradas

| Tema | Elegida | Descartada y por qué |
|---|---|---|
| Forma | Guion + punto de montaje + hoja propia + contrato de atributos y eventos | `<iframe>`: rompe el foco, el scroll del fondo, el retorno de Redsys y el autocompletado. Web component con Shadow DOM: aísla la hoja gratis, pero `client.css` dejaría de alcanzar al cajón y el tema por instalación es principio del producto |
| Quién pinta la carcasa | El paquete (Vue), con el suelo de logout dentro | Que cada landing copie 440 líneas de Blade: es justo lo que una landing a mano no puede mantener |
| Apertura | API propia del paquete en `window` + atributos `data-` declarativos + eventos DOM | Seguir con el store de Alpine: obliga a la landing a cargar Livewire. Alpine queda como ADAPTADOR en la landing del producto |
| Partir la hoja | Extracción MECÁNICA con guion, informe en seco y huella antes/después (el método de `#437`) | A mano: 1.598 reglas. Reescribir el CSS del cajón: cambia el cajón, que es lo que F4 promete no hacer |
| Los 10 bloques compartidos | Se MIDE cuál de las dos definiciones gana hoy en el cajón y esa viaja en la hoja del paquete | Dejarlos en la landing: el cajón en una landing ajena saldría sin botones |

## 4. Diseño elegido

### 4.1 La forma del paquete
Tres piezas servidas por el PRODUCTO desde rutas estables (no con hash, o con un manifiesto público): el **cargador**
(pocos KB: registra la API de apertura y trae el motor con `import()` en la primera apertura, como hoy), la **hoja
del cajón** y el **motor** (los chunks de hoy). La landing escribe dos líneas: la hoja y el cargador.

### 4.2 Contrato de incrustación
- **Montaje**: el cargador crea la carcasa al final de `<body>` si no existe `[data-jw-cajon]`.
- **Abrir**: `window.JumpWeb.cajon.open()`, `.openWith({product, date})`, `.openAccount(zone)`, `.close()`; y
  declarativo, `data-jw-open`, `data-jw-open-account="orders"`, `data-jw-open-product="<slug>"`: sin JS en la landing.
- **Eventos** en `document`: `jw:cajon:open`, `jw:cajon:close`, `jw:cajon:purchased` (con el código del pedido) —
  para que la landing mida o reaccione sin tocar el motor.
- **Idioma**: `<html lang>`; **tema**: `client.css` cargada DESPUÉS de la hoja del cajón.
- La landing del producto conserva `$store.purchase` como ADAPTADOR sobre esa API: sus 23 usos no se tocan en F4.

**Medido para la T2 (2026-09-18), antes de tocar nada**: el MOTOR (`resources/js/sidebar/**`) toca Alpine en solo
DOS sitios reales — `Sidebar.vue` (publica `mode` e `identifying` en `Alpine.store('purchase')`) y
`account/session-gained.js` (marca `authChanged`); el resto de menciones son comentarios. Todo lo demás vive en
`resources/js/app.js`: el store `purchase` (~240 líneas) es a la vez la API de apertura, el arranque perezoso del
motor (`bootSpaEngine()` con su `import()`), la costura de intención, la zona de cuenta que se CONSUME y el
cierre que recarga si hubo login. **Diseño de la tanda**: (1) un controlador SIN framework (`resources/js/cajon/`
(futuro)) dueño de ese estado y esa lógica, con `subscribe()` y los eventos `jw:cajon:*`, expuesto en
`window.JumpWeb.cajon`; (2) el store de Alpine queda de espejo — mismos nombres, delega y refleja, para que
`:class="$store.purchase.isOpen && 'is-open'"` y `'is-' + $store.purchase.mode` del layout sigan reaccionando;
(3) el motor escribe en el controlador, no en Alpine; (4) `data-jw-*` por delegación de clic; (5) el cerrojo de
scroll (`ui/scroll-lock.js`, ya sin framework) se instancia UNA vez y lo comparten los dos. ⚠️ Tras la T2 una
página sin Alpine tiene API pero aún no ve el panel: la clase `is-open` la pone la carcasa Blade, que es la T3.
Se verifica con `npm run test:js`, `SidebarDomContractTest` y la sonda del cajón por las tres vías de apertura.

**✅ T2 HECHA (2026-09-18)** — los ~240 miembros del store se mudaron TAL CUAL, con sus comentarios, a
`resources/js/cajon/controller.js` (`createCajonController({ scrollLock })`); `app.js` instancia el cerrojo y el
controlador UNA vez al cargar el módulo, publica `window.JumpWeb.cajon` y, en `alpine:init`, registra ese mismo
objeto como `$store.purchase` y **reasigna `window.JumpWeb.cajon` al PROXY reactivo**. Atributos
(`cajon/declarative.js`, un oyente delegado): `data-jw-open`, `data-jw-open="packs"`, `data-jw-open-zone`,
`data-jw-open-product`, `data-jw-open-account`; conservan el `href` y dejan pasar el clic con modificador o central
(`#117`). Eventos: `jw:cajon:open` y `jw:cajon:close` (con `reloading`); **`jw:cajon:purchased` queda para la T3**,
que es cuando el motor tendrá a quién decírselo. El motor escribe por `sidebar/host-bridge.js`. **Lo que enseñó
el código**: (1) el fallo temido es MUDO y solo lo ve un navegador — con `JumpWeb.cajon` en el objeto crudo,
`open()` pone `isOpen` a `true`, nada falla y la carcasa no se mueve: la sonda se vio en ROJO con esa línea quitada
y hay un centinela estático en `SidebarSeamTest`; (2) cuatro guardas leían el store como TEXTO de `app.js`
(`AccountDoorWiringTest`, `SidebarSeamTest`, `SidebarMountTest`, `ScrollLockOwnerTest`): re-apuntadas al fichero
nuevo y MUTADAS allí; (3) ESLint solo miraba `sidebar/`: su alcance crece a `resources/js/cajon`, que viaja en la
entrada de todas las páginas. **Medido**: `scripts/sonda-cajon-apertura.mjs` **17/17** en Chromium a 390 px
(los abridores REALES de la landing por las tres vías, la API, los atributos sin navegar al `href`, los eventos,
el modo del motor en la clase del panel y `/entradas` naciendo abierto); `npm run test:js` 1016 (20 nuevos);
`scripts/mutar-cajon-apertura.sh` **13/13**; la entrada de la landing, 24,5 kB de un techo de 26.

**✅ T3a HECHA (2026-09-18) · la carcasa con un solo dueño**. El marcado sigue pintándolo Blade, pero **ya no
lleva ni un atributo de Alpine**: `x-data="a11yPanel(…)"`, `x-cloak`, dos `:class`, tres `@click` y dos
`@keydown` se retiran, y su dueño es `resources/js/cajon/shell.js`, que ADOPTA `.sidecart` y le pone `is-open`,
la clase `is-{modo}`, el cierre por telón, × y Escape, y la trampa de foco. `a11yPanel` se retira de `app.js`
(su único consumidor era esta carcasa). El controlador gana `announce('mode')` y deja de anunciar un cierre
cuando el cajón ya estaba cerrado (Escape llegaba siempre). Cerrada, la carcasa se oculta por CSS
(`visibility: hidden`), no por `x-cloak`: sin JS no se ve, que es lo que debe pasar. **Tres cosas que enseñó el
navegador y ningún test de Node habría visto**: (1) la trampa de foco heredada filtraba por `offsetParent`, que
NO ve `visibility: hidden` — recién montado el motor, el foco se escapaba del diálogo al banner de cookies en la
primera pulsación de Tab; ahora «visible» es «se puede enfocar» y 46 pulsaciones no salen del panel; (2) el
`a11yPanel` viejo QUERÍA enfocar al nacer abierto y su comprobación (`this.$data.$evaluate`) no existía, así que
nunca corría: se preguntó en vez de arrastrarlo y **`[DECIDIDO owner]` que SÍ entre** (`#634`) — el anillo sobre
la × al cargar esas páginas es el único píxel que se mueve; (3) las capturas hay que tomarlas
con el panel ASENTADO (dos fotogramas con la misma caja) y cuidando el limitador de la API: sin eso, dos
corridas del mismo código daban imágenes distintas y un 429 pintó «No hay días disponibles» en una. **Medido**:
`/entradas` (el cajón que nace abierto) **idéntica píxel a píxel** antes y después, con control de dos corridas;
sonda **23/23**; `npm run test:js` 1027; `scripts/mutar-cajon-apertura.sh` **20/20**.
**✅ T3b HECHA (2026-09-18) · el paquete se monta en una página que no es del producto.** `installCajon()`
(`cajon/index.js`) es la ÚNICA llamada: controlador, `window.JumpWeb.cajon`, atributos, carcasa y el arranque
del cajón que nace abierto —que vivía suelto en `app.js`, o sea solo para el producto, y ahora es
`controller.start()`—. Cuando no hay `data-boot` ni marcado, `bootSpaEngine()` trae con `import()` el módulo
`cajon/standalone.js`, pide el arranque a las dos lecturas de la T1 (`?lang=` de `<html lang>`) y CONSTRUYE la
carcasa nodo a nodo. El arranque gana dos claves para poder construirla: `account.close` (el nombre accesible
de la ×) y `urls.logout` (a dónde POSTea el suelo). Tercer evento del contrato: **`jw:cajon:purchased`**, con
el código del pedido y una sola vez por pedido.
**Lo que enseñaron las guardas de la casa, y cambió el diseño**: (1) `SidebarBundleBudgetTest` — meter el
camino ajeno en la entrada subía a 27,3 kB lo que descarga TODA página pública del producto, para una rama que
sus páginas nunca ejecutan: por eso `standalone.js` es un módulo aparte y diferido (entrada 25,3 kB de 26);
(2) `SidebarComponentBudgetTest` — la raíz del cajón NO puede conocer los pasos del embudo, así que la regla
del anuncio vive en `section.js` (`publishedPurchase`) con sus dos hermanas y el store deriva el hecho
(`confirmed`); (3) el presupuesto del montaje anónimo obligó a justificar `account.close` clave a clave.
**Y ESLint cazó un defecto mío**: al reordenar `bootSpaEngine()`, `host` quedó fuera del alcance de su `catch`
— un fallo del chunk habría lanzado un `ReferenceError` DENTRO del manejador de errores y el velo habría girado
para siempre, que es justo lo que ese bloque existe para impedir.
**El SUELO de logout de una carcasa construida se levanta ENTERO o no se levanta**: hacen falta titular, URL y
un `<meta name="csrf-token">` en la página. Una landing de instancia que lo quiera publica el meta — una línea,
y va en el contrato de instancia (F5). Sin él no hay suelo, que es mejor que un botón que no cierra sesión.
**Medido**: `scripts/sonda-cajon-apertura.mjs` **31/31** en Chromium, con una sección nueva que sirve una
página AJENA del mismo origen (sin carcasa, sin `data-boot` y sin Alpine) y la ve construir la carcasa, montar
el motor, pintar el catálogo y cerrar; `npm run test:js` 1049; `scripts/mutar-cajon-apertura.sh` **34/34**.
▶ **Queda para la T4**: la hoja propia. La carcasa construida se viste hoy con `site.css`, así que una landing
ajena tiene que cargar las hojas y las fuentes de la instalación —medido: sin la hoja de fuentes cambia la
métrica del texto y un botón del bloque de cuenta se sale del panel—.

### 4.3 La hoja y los tokens
La hoja del cajón lleva SU raíz de tokens (los que lee, medidos con el guion, no los 231) bajo un selector propio,
con `client.css` pisándolos igual que hoy. `site.css` se queda con la landing. Trinquete nuevo: un test que falle
si un `.vue` del cajón emite una clase que no esté en la hoja del paquete.

### 4.4 El anfitrión mínimo
Una vista del producto, vestida por el tema, para las 7 puertas que no son la portada y para los flujos con vista
propia (verificar correo, restablecer contraseña, errores, mantenimiento): respaldo y banco de pruebas. En F4 las
puertas SIGUEN sirviendo la portada; el anfitrión entra en F5, cuando la portada se va. `login` no es opcional: es
el destino del middleware `auth`.

### 4.5 El arranque — `[DECIDIDO]` 2026-09-18 (`#631`): dos lecturas, una pública y una privada
Hoy Blade pinta las 9 claves del `data-boot` en cada página; una landing a mano no puede. El cargador las pide:
- **Pública y cacheable** — `GET /api/v1/sidebar/boot?lang=`, por idioma: `messages`, `ui`, `account`, `auth` y
  `urls` (18,3 de los 18,7 KB). `Cache-Control: public, max-age=300` + `ETag`; cambia solo al desplegar.
- **Privada, `no-store`** — `GET /api/v1/sidebar/session?lang=`: `userId`, `accountContext`, y `outcome` +
  `orderCode` **consumidos al leerlos** (`SidebarEntry::consume()` ya es de un solo uso). Se pide al ABRIR, no al
  cargar la página: una landing estática no gasta una petición por visita.
- «¿Abrir al cargar?» (`data-purchase-open`, `data-account-zone`): en las puertas lo sabe la RUTA, y el anfitrión
  lo declara con atributos; tras volver de Redsys se aterriza en una ruta del producto, que ya lo sabe.
**Descartado**: un cargador que el producto sirve con todo incrustado por petición — no se cachea, y lleva estado
de sesión dentro de un guion, que es lo que `RGPD-04` pide no hacer.

**✅ T1 HECHA (2026-09-18)** — `Http\Sidebar\SidebarBoot` es el modelo de lectura (`shared()`, `personal()` y
`forCurrentRequest()`, que las funde en el orden de claves del layout); el layout baja de 603 a 290 líneas y
PINTA, no compone. Las rutas se llaman **`GET /api/v1/sidebar/boot` y `/sidebar/session`** (~~`cajon/…`~~: la
API está en inglés y el código lo llama `Sidebar` en todas partes). Contrato **1.2.0**. Lo que enseñó el código:
(1) la mitad pública solo es cacheable si el idioma viaja EN LA URL (`?lang=`, obligatorio): `ApiLocale` lo saca
de la sesión o de `Accept-Language`, y una caché habría servido francés a quien pidió español; (2) el subgrupo
`google` viaja en el layout solo en su puerta y en la API siempre que la instalación ofrezca Google (no sabe en qué
página está quien pregunta); (3) un grupo vacío de PHP es `[]` en JSON y uno lleno `{}`: `session` los devuelve
siempre como diccionario; (4) `accountContext` va declarado abierto, no con `$ref` (`#27`: el validador no admite
`allOf` + `nullable`). **Medido**: el `data-boot` de siete contextos (anónimo es/en/fr, `/entradas`, la puerta de
Google, y con sesión en dos rutas) **idéntico byte a byte** antes y después; `curl`: 200 `public, max-age=300` +
`ETag` → 304 con `If-None-Match`; `session` `no-store`. `SidebarBootTest` 9 casos, el central «las dos lecturas
reconstruyen lo que pinta el layout» (anónimo y con sesión); `scripts/mutar-cajon-arranque.sh` **8/8**.

### 4.6 Lo que consume la landing independiente — EL MENÚ DE HECHOS (`#631`; tres opciones de producto abiertas)
**Principio `[DECIDIDO owner]` 2026-09-18**: *todo lo que la landing pueda consumir es OPCIONAL*; cada cliente
diseña la suya y usa lo que quiera, o nada. Consecuencias de diseño:
1. **El cajón no depende de que la landing consuma nada**: lee lo suyo (§4.5). Lo único que el producto
   GARANTIZA es lo que pasa dentro del cajón; el precio que manda es el del checkout.
2. **Criterio de entrada al menú**: un dato entra si el negocio lo OPERA desde el panel (lo usan el cajón, los
   correos, la puerta o la factura) y equivocarlo en la web perjudica al cliente. Lo que solo se ENSEÑA
   (titulares, dudas, galería, testimonios a mano) es de la instancia y no vive en el panel.
3. **Un solo modelo de lectura, dos transportes**: cada recurso público es un servicio de lectura de dominio +
   su `Resource`. Por HTTP es la API; en la vía B (la instancia como vistas que renderiza el producto) la vista
   recibe ESE MISMO payload. Pasar a vía A es cambiar el transporte, no los datos.

**El menú** (todo GET, público, por idioma; «hoy» = ya existe):
| Recurso | Contenido | Hoy |
|---|---|---|
| `site` | identidad (nombre comercial y fiscal), contacto, dirección y coordenadas, redes, idiomas, moneda, zona horaria — `[DECIDIDO owner]`: van por API | no |
| `hours` | horario por temporada, fechas especiales y festivos, «abierto ahora» | no |
| `catalog/products`, `catalog/zones` | nombre, chapa, ventajas, regalos, «desde», periodo, zona — **falta** `slug`, `description` y el precio de antes | sí, parcial |
| `prices` | la rejilla de tarifas por tipo de día (lo que pinta `/precios`) | no |
| `rules`, `legal/{clave}`, `policies` | normas, los cinco documentos legales, políticas (cancelación, menores) | solo `legal/waiver` |
| `social-proof` | valoración, recuento, reseñas sin avatares (`RGPD-05`) | no |
| `booking/status` | ventas abiertas o en pausa, con su mensaje | sí |

**Estándar que fija el diseño** (técnico, decidido): (a) **lista blanca por `Resource`, jamás un volcado de
ajustes** — medido: la tabla `settings` mezcla `business`, `contact` y `address` con `redsys_secret_key`; una
guarda impedirá que un recurso público lea una clave fuera de su lista; (b) **caché**: hoy la API pública
responde `no-cache, private`; el menú irá con `public, max-age` corto + `ETag`, invalidado al guardar en el panel
(`PERF-02`); (c) limitador propio para lectura (hoy 60/min por IP: una landing renderizada en servidor lo agota);
(d) dinero en céntimos + moneda, fechas ISO con la zona de la instalación; (e) `was_price_cents` lo alimenta hoy
la promo `#628` y mañana el sistema de ofertas: misma forma; (f) `site` basta para componer el `LocalBusiness`.
**`[DECIDIDO owner]`**: el widget flotante de ofertas (imágenes y texto) se RETIRA; «oferta» pasa a ser un HECHO de precio.

**Tres opciones de PRODUCTO — `[DECIDIDO owner]` 2026-09-18 (`#632`)**:
- **P1 · la ficha de producto gana descripción e imagen**: cada producto y cada zona, en el panel, una
  descripción traducible y UNA imagen opcional. La landing los usa si quiere; la app (F6) vende con ellos sin
  empaquetar material por cliente. *Descartado*: solo texto; nada nuevo.
- **P2 · API ahora, kit después**: en F5 la API con su contrato; el kit declarativo (atributos que el cargador
  rellena solo) se construye cuando una segunda instancia lo pida, con su uso delante. *Descartado*: solo API para
  siempre; kit desde F5 (diseñado sin quien lo valide); componentes listos (devuelve presentación al producto).
- **P3 · las atracciones SALEN del panel**: medido, son presentación (nombre, descripción, imagen, chapa y una
  etiqueta de edad en texto; sin aforo ni venta). Las restricciones que importen viven en Normas, que va por API;
  se retira con ellas el complemento por atracción (0 de 23 en uso). *Descartado*: hechos mínimos; dejarlas.
El alcance fino de cada recurso se escribe en la spec de F5; aquí queda el principio, la forma y estas tres.

## 5. Impacto en invariantes
`RGPD-04`: `cajon/session` es `no-store` (lleva titular). `PERF-02`: `cajon/boot` y el menú van con caché pública
y `ETag`. `SEC-01`: rutas nuevas dentro del grupo `api`. `RGPD-05`: prueba social sin avatares. Guarda NUEVA: ningún
recurso público lee un ajuste fuera de su lista blanca. `AFORO-*` y `PAY-*`: ninguno; el desenlace del pago se LEE.

## 6. Plan de verificación empírica
- `/sonda`: una página HTML estática servida en local que monta el cajón, lo abre por las tres vías y compra.
- Huella de maquetación 24/24 idéntica antes y después de partir la hoja (el instrumento de `#437`).
- `SidebarDomContractTest` y la suite del cajón sin tocar; el chunk bajo su techo; `SidebarMountTest` reescrito
  contra el paquete. Mutación: quitar un bloque compartido de la hoja del paquete tiene que poner rojo el trinquete de §4.3.

## 7. Revisión y decisión
**2026-09-18, con el owner** (`#631`): lo técnico, por el estándar profesional (§4.5 y el estándar de §4.6);
suyo y decidido: todo lo consumible es OPCIONAL, identidad y contacto van por API, el widget de ofertas se retira,
y el menú crece (ficha de producto). **P1–P3 decididas ese mismo día (`#632`)**, las tres por la recomendada.
Falta que revise el carril del SPA (es quien implementa) para pasar a ✅.

## Anexo · fila del enrutador

`F4 · cajón empaquetable · login por token` → `docs/specs/cajon-empaquetable.md` §0 · `docs/specs/token-bearer.md` §0.
