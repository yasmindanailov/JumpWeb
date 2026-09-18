# [SPEC] El cajón empaquetable — del layout del producto a un paquete con contrato (F4 del programa)

> Estado: ⬜ **borrador INCOMPLETO a propósito**: §1–§3 y §4.1–§4.4 escritos; **§4.5 y §4.6 se iteran con el
> owner** (qué consume el cajón del servidor al arrancar y qué consume la landing independiente del panel y de
> la API) · Última actualización: 2026-09-18 · Decisión asociada: `#63x` al aprobarse.
> Carriles: diseño **plataforma**; implementación **SPA** (`resources/js/sidebar/**`, `public/css/site.css`) con
> plataforma en el anfitrión y la API. Origen: `specs/producto-e-instancias.md` §4.2. Hermana: `specs/token-bearer.md`.

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
- **Estado**: borrador. NO se escribe código hasta cerrar §4.5 y §4.6 con el owner y revisar §4.
- **Empieza por** §1 (el censo) → §4.1 (la forma del paquete) → §4.5 (lo abierto).

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

### 4.5 El arranque — ⏸ SE ITERA CON EL OWNER
Hoy Blade pinta las 9 claves del `data-boot` en cada página. Una landing a mano no puede. Hay que decidir de dónde
saca el cajón: los **rótulos** (16 KB por idioma), las **URLs** (legales, condiciones), **quién es el titular** y su
contexto, y el **desenlace de un pago pendiente** (vive en sesión y se consume una vez).

### 4.6 Lo que consume la landing independiente — ⏸ SE ITERA CON EL OWNER
Del panel y de la API. Base: el censo de §1 y `specs/producto-e-instancias.md` §4.3 y §4.4.

## 5. Impacto en invariantes
Pendiente de §4.5: si el arranque pasa a un endpoint, entran `RGPD-04` (`no-store` con titular), `PERF-02` (caché
de los rótulos) y `SEC-01`. `AFORO-*` y `PAY-*`: ninguno previsto; el desenlace del pago se LEE, no se decide.

## 6. Plan de verificación empírica
- `/sonda`: una página HTML estática servida en local que monta el cajón, lo abre por las tres vías y compra.
- Huella de maquetación 24/24 idéntica antes y después de partir la hoja (el instrumento de `#437`).
- `SidebarDomContractTest` y la suite del cajón sin tocar; el chunk bajo su techo; `SidebarMountTest` reescrito
  contra el paquete. Mutación: quitar un bloque compartido de la hoja del paquete tiene que poner rojo el trinquete de §4.3.

## 7. Revisión y decisión
Pendiente. Se completa §4.5 y §4.6 con el owner; después revisa el carril del SPA (es quien implementa).

## Anexo · fila del enrutador

`F4 · cajón empaquetable · login por token` → `docs/specs/cajon-empaquetable.md` §0 · `docs/specs/token-bearer.md` §0.
