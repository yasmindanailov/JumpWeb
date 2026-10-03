# Changelog — JumpWeb (el producto)

> Versionado semántico sobre el producto entero (`docs/decisiones/600-699.md`, `#613` y `#624`).
> **MAYOR**: una instancia tiene que ACTUAR para actualizar · **MENOR**: capacidad nueva sin acción ·
> **PARCHE**: arreglo. Cada versión lleva dos mitades: lo que tiene que saber una **instancia** y lo
> **interno**. Producción despliega solo etiquetas (guarda 8 de `scripts/deploy.sh`); staging despliega
> `main`. Una versión se corta con la skill `/release`.

## Sin publicar

### Para las instancias

- **MENOR: la confirmación de una reserva es el correo del diseño** (la R2b de `correos-rediseno.md` §4.3, `#915`, `#916`):
  unas entradas, un grupo o una fiesta, con su resguardo, el QR dentro, «Antes de venir» y el plazo; la fiesta, UN correo
  con la lista de invitados dentro (el formulario ya no sale aparte al pagar). ▶ **Lo que el parque PUEDE hacer**: en cada
  producto, «Antes de venir» (una línea por cosa: «Todos los profesores entran gratis»), y en sus complementos su aviso
  («Tenéis :n pares de calcetines…»). ⚠️ Lo editado en «Textos de los correos» para la confirmación VIEJA no pasa: sus
  textos son otros (`emails.reservado`, `emails.fiesta_reservada`, `emails.reserva`).
- **MENOR: la víspera, como su diseño** (la R2d, `#917`): unas entradas o un grupo reciben SIEMPRE «Mañana os esperamos»
  (con su QR, la hora y cómo llegar; «Hoy», dos horas antes, si se reservó para el mismo día); una fiesta, «Un repaso antes de
  mañana» solo si le queda algo. Solo a pedidos pagados. ⚠️ Lo editado del aviso de la víspera viejo no pasa (`emails.visit_eve`
  cambia; el 3 es `emails.manana`).

### Interno

- `MailReservation` compone lo de una reserva para los correos (del libro de cada reserva, `#916`); `ticket_types.before_visit`
  (migración). Arnés `scripts/mutar-correo-r2b.sh`.

## v2.0.0 · 2026-10-03

La versión GRANDE del programa «producto e instancias» (`#670`: no se despliega en piezas): la landing deja el producto
y pasa a la INSTANCIA, con la web nueva (Saltia) y la compra de la ISLA; el panel, en su dirección secreta y con
authenticator; se entra con un código al correo y sin contraseña; la fiesta del sistema nuevo, la analítica, las
encuestas, los correos rediseñados, la puerta y el SEO. 475 commits desde v1.2.0; el contrato de la API, de 1.2.0 a 1.63.0.

### Para las instancias

- **MAYOR: una instalación TIENE QUE ACTUAR** (`ENTORNOS.md` §6, las notas de la v2.0.0, en este orden):
  - **`.env`**: `PANEL_PATH` OBLIGATORIA (8–64 caracteres `[a-z0-9-]`, nunca `admin`; la guarda 10 para el despliegue sin
    ella, `#850`): `/admin` da 404 desde esta versión. Cambian con ella el favorito de las tablets de la puerta
    (`/<PANEL_PATH>/puerta/validar`) y la URI de retorno de la ficha de Google (`/<PANEL_PATH>/ficha-google/callback`).
  - **`.env`**: `INSTANCIA_RUTA`, la ruta ABSOLUTA al paquete de la instancia, FUERA del árbol del producto (`SEC-12`,
    `#647`); su `publico/` se copia a `public/` (`instancia/`, `images/attractions/`, `videos/`), que el despliegue no
    borra. Sin paquete, el producto sirve su anfitrión mínimo: no es la web de la instalación.
  - **`.env`**: fuera `GOOGLE_PLACES_API_KEY` (Places retirado, `#771`/`#772`; las reseñas se importan con
    `reviews:import`). Nuevas y opcionales, vacías = apagado: `POSTHOG_*`, `MATOMO_TOKEN_AUTH`, `META_CAPI_*`,
    `TIKTOK_EVENTS_ACCESS_TOKEN`.
  - **El kit de ilustración**: tras el despliegue, `kit:build --podar` (la guarda 7 sale en rojo hasta entonces, `#845`).
  - **Tras el `migrate`**: las contraseñas de los CLIENTES se borran (A5d, `#869`; sin vuelta: medir antes y después); y,
    si la instalación editó a mano su texto legal o su política de cookies, su guion por huella (los de PlayJump, en su
    `storage/app/`).
  - **Los administradores** configuran el authenticator en su primer inicio de sesión (`#851`); el panel recuerda UN día
    a todos (`#877`).
- **El contrato de la API sube de 1.2.0 a 1.63.0** y solo AÑADE (rutas y campos): la web y la app siguen siendo
  clientes iguales. La sesión de la web entra con un código de un solo uso (`#853`→`#857`).
- **El paquete de tema** (`client.css`): declarar los tokens en `:root, .sidecart` deja de ser una recomendación —el cajón
  vive dentro de la página de la instancia— (`INSTALACION-CLIENTE.md` §4).

### Interno

- **La landing fuera** (F5, `#677`, `#681`) y **la isla** (`#682`): la web nueva en la instancia con la compra en Vue
  contra `/api/v1`; su primera pantalla, la conversión y las páginas (`#820`→`#846`), Mi cuenta (T5, `#774`→`#779`), el
  zip tercero y el sexto (`#780`→`#787`, `#866`→`#875`), la otra zona en un mismo pedido (L2, `#878`, `#881`, `#882`) y la
  lista del owner del 02-10 (`#876`→`#880`).
- **Promociones** (`#770`), **las reseñas copiadas** (`#771`, `#774`) y **el SEO** (`#862`).
- **El panel a salvo** (`#850`, `#851`, `#877`) y **el acceso con código** sin contraseña (`#853`→`#857`, `#869`, `#870`).
- **Lo legal**: `/cookies` (`#858`→`#860`), los textos (`#863`→`#865`) y los datos de los invitados a los 14 días (`#863`).
- **Del carril del SPA**: la fiesta del sistema nuevo y la invitación, la analítica (`#678`, `#735`) y sus encuestas
  anónimas (`#754`), «Avísame de fechas» (`#750`), los correos rediseñados y su registro (`#794`→`#797`), la puerta nueva,
  el cumpleaños mixto y la hora extra.

## v1.2.0 · 2026-09-19

La versión del **cajón empaquetable** (F4 del programa, `#630`→`#638`): el cajón deja de necesitar la landing
del producto y pasa a montarse, abrirse y **vender** en cualquier página HTML del mismo dominio, con dos
líneas. Entra también el **emisor de tokens Bearer** —la puerta de la app móvil—, ESLint en el gate, y del
carril del SPA la invitación digital completa, todavía apagada.

### Para las instancias

- **Nada que hacer para actualizar.** Sin tocar nada, la web es la de v1.1.0: mismos píxeles —comprobado con
  la huella de maquetación, 34 pantallas— y mismo comportamiento. Sin migraciones, sin claves nuevas de
  `.env`, sin ajustes obligatorios.
- **El contrato de la API sube de 1.0.0 a 1.2.0**, y solo AÑADE: `/auth/tokens` y `/auth/tokens/rotate` (login
  por token para la app), `/sidebar/boot` y `/sidebar/session` (el arranque del cajón) y las tres de la
  invitación. Ninguna ruta existente cambia de forma. La web sigue hablando por cookie de sesión.
- **Dos rutas nuevas que sirve el producto y hoy no usa nadie**: `/css/cajon.css` (la hoja del cajón) y
  `/cajon/paquete.js` (su cargador). Son el paquete con el que una instalación podrá servir SU propia landing
  sin copiar nada del producto (F5). Mientras la landing sea la del producto, no se cargan.
- **Recomendado, no obligatorio** (`docs/INSTALACION-CLIENTE.md` §4): en `client.css`, declarar el bloque de
  tokens en `:root, .sidecart` en vez de solo `:root`. Hoy no cambia nada; el día que la landing sea de la
  instalación, es lo que hace que su tema llegue también al cajón.
- **La invitación digital de cumpleaños entra HASTA LA T5 y sigue APAGADA**: sus dos interruptores los
  enciende el panel. Hasta entonces el embudo es exactamente el de v1.1.0.
  ❗ **CORREGIDO el 2026-09-23** (`#670`): esta línea decía «entra ENTERA» y era **falsa**. Medido contra el
  git: la etiqueta se cortó el 19-09 a las 09:52 y **la T6 (`#708`→`#713`), la T7 (`#714`→`#717`) y `#718`
  entraron después**. Con esta versión en producción, encender los interruptores daría **media feature**
  —la página pública del padre, sin el aterrizaje del anfitrión y sin los tres correos—. Se deja escrito
  aquí y no se borra: la frase original era la que alguien se iba a creer antes de un despliegue.
- La promo de `#628` (precio anterior tachado y recuadro de oferta) sigue como está: son filas de `settings`, y
  esta versión no las toca.

### Interno

- **F4 · el cajón empaquetable, cerrada** (`specs/cajon-empaquetable.md`, `#631`→`#637`). Cinco tandas: el
  arranque sale del layout a un modelo de lectura con dos transportes (`SidebarBoot`, contrato 1.2.0); la
  apertura pierde Alpine (`cajon/controller.js`, `window.JumpWeb.cajon`, `data-jw-*`, eventos `jw:cajon:*`);
  la carcasa gana un dueño sin framework (`cajon/shell.js`); el paquete se monta donde no hay producto
  (`installCajon()` + `cajon/standalone.js`, que CONSTRUYE la carcasa pidiendo el arranque a la API); y la
  hoja propia se GENERA desde las tres del producto (`scripts/hoja-del-cajon.py`, sin tocar `site.css`).
  Criterio de salida medido: una página ajena abre el cajón y COMPRA —`POST /api/v1/orders` 201 y salto
  firmado a la pasarela—, sonda 42/42.
- **El emisor de tokens Bearer** (`#630`, `specs/token-bearer.md`): `ApiTokenIssuer`, ability `api-v1` exigida
  en toda ruta autenticada, tope de 10 por usuario, rotación. Con él se cerró la Fase 3 (API v1).
- **Dos fallos de cascada cazados ANTES de etiquetar**, los dos silenciosos: el tema del panel scopeado al
  cajón le ganaba al tema de la instalación (`#637`, medido con el `client.css` real: `--on-brand` del cliente
  pisado por el del panel), y `deploy.sh` habría subido un andamio local de `public/` que git no veía
  (`#638`, guarda 9 nueva). Cada uno con su guarda y su mutación.
- **ESLint del cajón en el gate** (`#629`, línea base nativa de 12 con trinquete) — cazó un defecto vivo el
  primer día. Larastan sigue en nivel 5.
- Del **carril del SPA** (`carriles/spa.md`): la invitación digital T4·5→T5·5 (`#577`, `#578`, `#701`→`#706`),
  el `addBtn` de «Menores a cargo» (`#707`) y el oráculo de pertenencia de la lista completa (`#520`).
- Instrumentos nuevos que quedan para el siguiente: `scripts/huella-maquetacion.mjs` (la huella cubre ahora el
  cajón, y deja fuera el `<head>`, que no se pinta), `scripts/hoja-del-cajon.py` y tres arneses de mutación
  (`mutar-hoja-del-cajon.sh` 10/10, `mutar-paquete-del-cajon.sh` 5/5, `mutar-token-bearer.sh` 14/14).

## v1.1.0 · 2026-09-18

Tres cosas: el precio de antes tachado y el recuadro de una oferta en la sección de tarifas, encendidos
por dos ajustes de la instalación (`#628`, chapuza declarada hasta el sistema de ofertas); la piel del
justificante del menor invitado con el arreglo de su barra de firmar, que estaba rota en producción desde
el octavo despliegue (`#572`, T3 del carril del SPA); y los cimientos de la invitación digital de
cumpleaños, apagados (`#573`→`#576`, T4·1–T4·4).

### Para las instancias

- **Nada que hacer para actualizar.** Sin los ajustes, la web es la de v1.0.0 con el justificante vestido.
- **Una migración nueva** (`create_party_invitations`: dos tablas de la invitación digital) que el
  despliegue corre solo; los dos interruptores de la invitación nacen APAGADOS y nada cambia en el embudo
  hasta que el panel los encienda (T5/T6, todavía sin construir).
- Para enseñar una rebaja YA aplicada a los precios del catálogo: `promo.percent` (por ejemplo `20`)
  en la tabla `settings` (grupo `promo`, sin pantalla en el panel) → la card de la portada y `/precios`
  tachan el precio de antes (`precio × 100 / (100 − pct)`). Y `promo.banner.{es,en,fr}` → el recuadro
  con el copy encima del carril. Se apaga borrando las filas; **al subir los precios, borrarlas el
  mismo día**, o el tachado mentiría.
- El contrato de la API no cambia (`info.version` sigue en 1.0.0); el cajón no pinta el «antes».

### Interno

- `Setting::promoPercent()`, `WritesLandingValues::antes()`, `RateCards` y `RateTable` con el tercer
  argumento, `site.promo_banner` en el payload compartido, `rate-rail.blade.php`, `pages/pricing.blade.php`,
  `landing.css` (`.rate-card__was`, `.rate-table__was`, `.rates__promo`), `landing.rates.was` en tres idiomas y
  cuatro casos en `RateRailSectionTest` (mutación vista: 3 en rojo con el porcentaje a 0).
- La spec del mecanismo queda aparcada en `docs/archivo/promo-precio-anterior.md`.
- Del carril del SPA (`carriles/spa.md`): T3, la piel del justificante (`GuardianSkinTest`, 10 casos) y la
  barra de firmar a su sitio; T4·1–T4·4, las tablas, `PartyInvitations` con su lock de una fila (16 padres →
  entra 1, medido en InnoDB), `show_in_invitation` en el pivote de complementos (`ProductAddon.php`, del
  `CRITICAL_RE`: sus pushes fueron con `VERIFY_CONC=1`), el cuarto sumando del suelo de plazas y la
  excepción del firmador. Sin cambios en el contrato OpenAPI.

## v1.0.0 · 2026-09-17

La línea base: lo que corre en producción desde el 2026-09-16 (`1272cb93`, octavo despliegue). Es la
primera etiqueta de versión del repo; los ocho despliegues anteriores se identificaron por hash
(`docs/ENTORNOS.md` §6).

### Para las instancias

- **Nada que hacer**: v1.0.0 describe lo que ya está instalado. Es el punto desde el que se mide todo
  lo que una instancia tenga que tocar a partir de ahora.
- El paquete de una instancia son **cuatro piezas** fuera del repo (`docs/INSTALACION-CLIENTE.md`):
  `public/css/client.css` · `public/img/client-*` · el kit de ilustración · la variable `THEME_FONTS`
  del `.env`. Ninguna viaja por `rsync`: se suben aparte y sobreviven al `--delete`.
- `client.css` en v1.0.0 incluye los cuatro tokens que pidió el formulario de celebración
  (`--err-ink`, `--done`, `--on-done`, `--done-ink`, `#569`–`#571`). Una instancia que no los defina
  hereda los del producto.
- El contrato de la API es `openapi/v1.yaml`, `info.version` **1.0.0**. Sube en MENOR al añadir; una
  ruptura sería `/api/v2`.
- Desde esta versión el servidor dice qué corre: `storage/app/version` (`versión hash fecha`), escrito
  por el despliegue a partir del primero que siga a esta etiqueta.

### Interno

- Todo el historial hasta `1272cb93` (921 commits): la base heredada y endurecida (`#1`), los módulos
  de dominio, la API v1 y el checkout orquestado, el cajón en Vue 3 + Pinia, el panel Filament, la web
  rediseñada desde el canvas con Redsys en `live` (`#594`), los correos, Google auth, y el formulario
  post-reserva vestido con el arreglo del número de invitados (`#569`–`#571`).
- El tracker de fases es `docs/00-REFACTOR.md`; el porqué de cada pieza, `docs/DECISIONES.md` por número.
