# [SPEC] El cajón empaquetable — del layout del producto a un paquete con contrato (F4 del programa)

> Estado: ✅ **aprobada, EN EJECUCIÓN por tandas** (`DECISIONES #631` lo técnico y el principio, `#632` las tres
> opciones de producto, `#633` quién implementa); la lectura del SPA se pide en paralelo y ya no es condición ·
> Última actualización: 2026-09-18.
> Carril: **plataforma**, diseño E implementación (`[DECIDIDO owner]` `#633`: el SPA está con la invitación
> digital; se entra en sus ficheros avisando y por tandas T1–T5, `carriles/plataforma.md`).
> Origen: `specs/producto-e-instancias.md` §4.2. Hermana: `specs/token-bearer.md`.

## §0 · Antes de tocar

- **Regla que ordena todo**: el cajón sigue siendo un cajón SOBRE la landing, en el MISMO dominio que la API
  (cookie de sesión + CSRF, sin token). Lo que cambia es QUIÉN lo monta: deja de ser el layout del producto y
  pasa a ser cualquier HTML que cargue el paquete. «Empaquetable» no es «página aparte» ni «otro dominio».
- **«Una línea del layout» era falso, medido (§1)**: hoy el cajón necesita de su página SEIS cosas — la carcasa
  que pinta Blade, un store de Alpine que llega DENTRO de Livewire, un `data-boot` de 18,7 KB que pinta Blade,
  una hoja de 549 KB compartida con la landing, los tokens de `:root` de OTRA hoja, y ocho rutas-puerta.
- **El riesgo silencioso es la hoja**: los `.vue` llevan CERO `<style>`; 81 bloques del cajón viven solo en
  `site.css` y **10 están definidos en las DOS hojas** (`btn`, `form`, `price`, `tabset`, `auth`, `catalog`,
  `addons`, `gifts`, `active`, `jj-block`). Partirla puede mover el cajón sin que falle un test: manda la huella
  de maquetación (`#437`), no la suite.
- **Trampas que aplican**: tras tocar un `.vue`, `npm run build:ssr` antes de la suite; el contrato visual es el
  ÁRBOL (`specs/sidebar-spa.md` §4.2); `route('logout')` aparece UNA vez y es el suelo del hueco de cuenta
  (`specs/account-context-vue.md` §4.8); el chunk tiene techo (285).
- **La landing consume un MENÚ DE HECHOS y todo es opcional** (`[DECIDIDO owner]`, §4.6): el cajón no depende
  de que la landing lea nada; lista blanca por `Resource`, jamás un volcado de `settings` (lleva secretos).
- **Estado**: aprobada y en ejecución por TANDAS (T1 arranque con un compositor → T2 apertura sin Alpine → T3
  carcasa en el paquete → T4 hoja propia → T5 página ajena): cada una verde y empujada. Implementa plataforma (`#633`).
- **Empieza por** §1 (el censo) → §4.1 (la forma del paquete) → §4.5 (el arranque) → §4.6 (el menú).

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
- La landing del producto conserva `$store.purchase` como adaptador de tres líneas sobre esa API: sus 23 usos no se tocan en F4.

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
- **Pública y cacheable** — `GET /api/v1/cajon/boot` (futuro), por idioma: `messages`, `ui`, `account`, `auth` y
  `urls` (18,3 de los 18,7 KB). `Cache-Control: public` + `ETag` + `Vary: Accept-Language`; cambia solo al desplegar.
- **Privada, `no-store`** — `GET /api/v1/cajon/session` (futuro): `userId`, `accountContext`, y `outcome` +
  `orderCode` **consumidos al leerlos** (`SidebarEntry::consume()` ya es de un solo uso). Se pide al ABRIR, no al
  cargar la página: una landing estática no gasta una petición por visita.
- «¿Abrir al cargar?» (`data-purchase-open`, `data-account-zone`): en las puertas lo sabe la RUTA, y el anfitrión
  lo declara con atributos; tras volver de Redsys se aterriza en una ruta del producto, que ya lo sabe.
**Descartado**: un cargador que el producto sirve con todo incrustado por petición — no se cachea, y lleva estado
de sesión dentro de un guion, que es lo que `RGPD-04` pide no hacer.

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
