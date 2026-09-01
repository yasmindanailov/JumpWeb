# Instalación de un cliente nuevo — checklist white-label

> Estado: vivo · Última actualización: 2026-08-12 ·
> Verificado contra código: 2026-08-12 (claves, guardas y comandos comprobados) ·
> Se invalida si: cambia el seed de arranque (Fase 1: semilla neutra) o el proveedor de pago.

La promesa del producto: **una instalación por cliente** (BD + dominio + `.env` propios,
`DECISIONES #2`) **sin tocar código**. Este es el runbook de instanciación; los
`[DECISION-PENDIENTE]` son huecos reales que la Fase 1 debe cerrar.

## 0 · Qué NO se toca (principio)
- Código de negocio: todo lo configurable va por panel/BD. El núcleo de dinero/aforo
  (`OrderCreator`, `RedsysReturnHandler`, `SlotGenerator`) está protegido por el gate
  (`VERIFY_CONC=1`, INVARIANTES §6).
- **NUNCA re-sembrar producción post-go-live**: `ProductionSeeder` aborta si hay pedidos
  (recrear `ticket_types` cascadearía a `order_items`/`tickets`).
- Secretos: `REDSYS_SECRET_KEY` va por `.env`/vault, jamás en BD ni repo (el fallback de BD
  existe pero no debe usarse en producción). ⚠️ El secret de Turnstile HOY solo funciona en
  BD (el código lo lee exclusivamente de `settings`) — contradicción abierta en la
  `[DECISION-PENDIENTE]` de §3. El contador `redsys_next_gateway_order` jamás se edita
  (resetearlo = errores SIS0051/0913).

## 1 · Infra (.env de producción)
Desde `.env.production.example`: `APP_KEY` nueva (`key:generate`, NO la de dev) ·
`APP_ENV=production` + `APP_DEBUG=false` · `APP_URL` con HTTPS (lo usan Redsys, signed URLs
y emails) · `DB_*` · `SESSION_SECURE_COOKIE=true` · **`QUEUE_CONNECTION=database`** (nunca
`sync`: los mails son `ShouldQueue`) · `MAIL_*` real + SPF/DKIM/DMARC · `REDSYS_SECRET_KEY`
en el vault (§6). Después: `migrate --force` · `db:seed` · **`app:create-admin`** (§5) · `config:cache`.
⚠️ **`npm run build` NO se corre en el servidor**: puede no haber node (staging no lo tiene,
`ENTORNOS.md` §4). Los assets se construyen en local y se suben ya compilados.
- Cron único que lo mueve todo (`routes/console.php`): `* * * * * php artisan schedule:run`
  → `orders:expire` (5 min) · poda RGPD de `CookieConsentLog` (diaria) ·
  `slots:generate-rolling` (03:00) · `queue:work --stop-when-empty` (worker por minuto;
  vigilar `failed_jobs`).
- `storage:link` NO se usa: las subidas del panel van al disco `uploads` =
  `public/uploads` directo (excluirlo del rsync `--delete`).
- ✅ **[DECIDIDO 2026-08-19, `DECISIONES #105`] El canal de despliegue es `scripts/deploy.sh`**
  —cerrado el `[DECISION-PENDIENTE]` que había aquí—. No es un port del `deploy-prod.sh` del origen:
  se escribió MIDIENDO la máquina. **DRY-RUN por defecto** (sin `--go` no toca nada), valida las seis
  guardas del `.env` remoto **sin subirlo jamás**, y comprueba la salud al terminar.
  ⚠️ **Está escrito para STAGING, que es 0 LIVE · 0 PRODUCCIÓN.** Instalar a un cliente real exige
  revisar las guardas 1 y 3 (Redsys en `live`, correo real): eso es una decisión, no una bandera.
  ⚠️ **El servidor necesita PHP ≥ 8.4.1**, que NO es lo que dice `composer.json` (`^8.3`): el suelo lo
  fija el LOCK. Compruébalo antes de contratar hosting con `composer check-platform-reqs`.

## 2 · BD y seed de arranque
`php artisan db:seed --class=Database\\Seeders\\ProductionSeeder --force` (solo arranque en
frío) siembra: settings · zonas (con cupos) · atracciones · tarifas `normal`/`special` ·
productos+precios+addons · FAQs · normas · landing services · páginas legales · horario
semanal · temporadas · festivos · plantillas de franja. **Las franjas NO**: después,
`php artisan slots:generate-rolling` (idempotente, horizonte = `sales.purchase_horizon_months`).
- ✅ Semilla NEUTRA desde Fase 1 (`DECISIONES #12.c`): `ProductionSeeder` siembra el negocio
  FICTICIO «SaltoPark» (mismo sector, catálogo realista) sin ningún dato identificativo de
  cliente; la jurisdicción legal es el setting `legal.jurisdiction` (token `:jurisdiction`,
  se publica como «[pendiente]» hasta configurarla).

## 3 · Settings (tabla `settings`, editables en `/admin/settings`)
Por grupos (fuentes: `Settings::MANAGED`, seeds):

| Grupo | Claves | Estado |
|---|---|---|
| `business` | `business.name` (required) · `legal_name` · `nif` · `address` · `city` · `domain` (vacío = host) · `legal.jurisdiction` (fuero de los textos legales) | **Imprescindible** (identidad fiscal → legales) |
| `contact` | `contact.email` · `phone` (CTA «Llamar») · `whatsapp` · `address.*` · `maps_embed_url` (saneada por `MapsEmbed`) | **Imprescindible** |
| `seo` | `seo.title.{es,en,fr}` · `og_image` (vacío → `public/og-image.jpg`) | **Imprescindible** para marca nueva |
| `theme` | `theme.brand` (hex; inválido → default) + `zones.color` por zona | **Imprescindible** para marca nueva |
| pagos | `redsys_merchant_code` · `redsys_terminal` · `redsys_merchant_name` · `redsys_merchant_url` · `redsys_environment` · `redsys_currency` (978) · `sales.order_prefix` (prefijo de códigos de pedido, default `R-`) | **Imprescindibles para live** (§6) |
| `packs` | `packs.max_per_slot` · `max_guests_per_slot` · `prep_blocks_cupo` (+ override por zona) | Decisión de negocio por cliente |
| resto | `sales.hold_minutes` (seed 15; fallback de código sin fila: 20) · `purchase_horizon_months` (6) · `payment.tax_rate` (21) · `incidents.alert_email` (→ `contact.email`) · `puerta.*` · `waiver.mode` (`externo` por defecto; `interno` exige publicar una versión firmable del texto desde el panel) · `waiver.retention_months` (vacío = sin poda; `[PENDIENTE: owner]`) · `display_timezone` · `maintenance.*` · `cookies.banner_enabled` · `registration.*` (waiver externo) · `social.*` · `landing.tagline/footer_rights` · `catalog.search_min_items` | Default sano. Fuente de verdad exhaustiva: `Settings::MANAGED` |
| **no tocar** | `redsys_next_gateway_order` (contador vivo) · `sales.manual_hold_minutes` (no expuesto) | — |

- ✅ **[RESUELTO 2026-08-20, `DECISIONES #109`]** `security.turnstile_*` están excluidas del panel
  **a propósito** (son secretos), y ya no hay que meterlas con un INSERT a mano: la vía es
  `php artisan app:set-setting security.turnstile_site_key '0x…' --group=security`. El comando nunca
  imprime el valor de un secreto —se ejecuta por SSH y su salida acaba en el log del despliegue—.
- Nota menor verificada: `ProductionSeeder` guarda `registration.url` con `group=business`
  y el panel la reescribe a `group=registration` (sin efecto: se lee por `key`).

## 4 · Tema y marca

El tema son **TRES mecanismos**, no uno (`specs/landing-white-label.md` §4.5), y cada cosa entra
por el suyo. Meterla por el que no es funciona a medias, que es peor que no funcionar.

**a) Valores → el PANEL (BD).** Color global: setting `theme.brand` (+ `theme.brand_secondary`)
→ `ThemeSettings` tematiza web, panel y emails, con el contraste WCAG calculado por luminancia.
Color/acento por zona: columnas `color` y `color_secondary` de `zones`, en el panel.
▶ Es lo único que cruza al **panel y a los correos**, donde el CSS del cliente no llega. Por eso el
tema en BD se queda en COLOR: tipografía y radios van en (b) (`[DECIDIDO owner]`, §4.5.5 de la spec).

**a.ter) El COLOR DE ACCIÓN → setting `theme.action`** (`DECISIONES #209`,
`specs/tema-por-instalacion.md` §15). El relleno del botón que hace avanzar la compra —reservar,
comprar, enviar—. **No es el color de marca**: la marca tiñe acentos y decoración y puede repetirse
por zona; éste es un ROL y hay **uno por pantalla**.
⚠️⚠️ **Dejarlo VACÍO es una respuesta, no una falta.** Vacío ⇒ el botón **sigue a la superficie**
(oscuro sobre claro, claro sobre oscuro), que es como se ha visto el producto siempre. Con un color
⇒ es **el mismo en los dos fondos**, que es lo que pide un sistema de marca con CTA propio.
▶ **Un solo dato**: el tono al pasar el cursor se deriva (×0,88; sobre un color casi negro, aclara)
y el color del rótulo lo elige el contraste, no el gusto.
⚠️ **No lo metas en `client.css`.** Ahí funcionaría en la web y **no llegaría al panel ni a los
correos**, que es exactamente el «funciona a medias» que este apartado avisa. Además el hover y el
color del texto los calcula el servidor: escribiéndolos a mano se pierden los dos.
⚠️ **Con algunos colores NO hay texto que alcance AA**: un relleno de luminancia ≈ 0,19 deja tinta y
blanco empatados en 4,31. El producto elige el mejor de los dos y **hoy no avisa** — está
`[PENDIENTE: owner]` (spec §15.8).

**a.bis) El LOGOTIPO → `public/img/client-logo.svg`** (`DECISIONES #206`).
Fichero **OPCIONAL** con las **mismas tres piezas** que la hoja de tema, y con dos parece que
funciona: **no se versiona**, se carga **si existe**, y `deploy.sh` lo **excluye del
`rsync --delete`** — sin esa exclusión el primer despliegue lo borra y la marca vuelve a ser texto,
en silencio.
▶ **El suelo es el nombre del sitio en la fuente de rótulo**, que es lo que el producto sabe pintar
sin saber nada del cliente.
⚠️ **El `alt` lleva el nombre del sitio, y no es opcional**: es el único enlace que TODA página
tiene, y un logotipo sin `alt` lo deja sin nombre accesible.
⚠️ **Un lockup hecho con CSS hay que exportarlo a SVG**: el hueco acepta un fichero, no una
composición de capas. Se pierde poder retocarlo desde la herramienta de diseño; es el precio de que
entre por el mismo sitio que el resto del paquete.

**a.quater) El ICONO → `public/img/client-favicon.*` y compañía** (`DECISIONES #211` + **`#216`**).
Mismo patrón, con las **mismas tres** por cada fichero: no se versiona, se carga si existe, y
`deploy.sh` lo excluye del `--delete`. El suelo es `public/favicon.svg`, el icono del PRODUCTO.

⚠️⚠️ **CORRECCIÓN (2026-08-28, `#216`) — el párrafo de abajo decía «solo se sustituye el SVG» y
eso ya NO es cierto.** Aquel alcance dejaba **iOS y el «añadir a pantalla de inicio» de Android con
la «J» del producto**, porque los dos ignoran el SVG. Con el paquete del 2.º cliente entregado,
`[DECIDIDO owner, 2026-08-28]`: **entra el set completo**. El aviso de entonces —«ampliarlo es
añadir dos ficheros más al mismo patrón»— queda cumplido, y son cinco:

| Fichero | Quién lo lee |
|---|---|
| `client-favicon.svg` | los navegadores modernos |
| `client-favicon.ico` | los antiguos, y quien pide `/favicon.ico` a pelo |
| `client-apple-touch-icon.png` | **iOS**, que no tiene alternativa vectorial |
| `client-icon-192.png` · `client-icon-512.png` | **Android sin manifiesto**: Chrome elige el declarado más grande |
| `client-icon-512-maskable.png` | nadie, **todavía**: un maskable solo lo lee un manifiesto de aplicación web y este producto no sirve ninguno. Se acepta en el paquete para el día que exista. Ficha en `DEUDA.md` |

❗ **Cada pieza se comprueba POR SEPARADO, no en bloque.** Una instalación puede traer el SVG y no
el `.ico`: con una sola condición, faltar uno dejaría fuera a los demás.
▶ Cuando la instalación trae su SVG, **el PNG de 64 px sale del `<head>`**: un navegador que
entienda los dos preferiría el PNG por ser más específico en tamaño y volvería a enseñar la «J»
del producto teniendo el del cliente al lado.
▶ Cuando la instalación trae su SVG, **el PNG de 64 px sale del `<head>`**: un navegador que
entienda los dos lo preferiría por ser más específico en tamaño y volvería a enseñar la «J» del
producto teniendo el del cliente al lado.
⚠️ **El icono del producto lleva `#FF5B22` quemado** —el naranja del PRIMER cliente— y `favicon.ico`
está versionado con 0 bytes. Fichas en `DEUDA.md`.

**a.quinquies) ❗ QUÉ PEDIRLE AL DISEÑADOR, exactamente.**
Esta lista existe porque el logotipo del 2.º cliente **se intentó reconstruir y no salió idéntico**
(`#211`): su lockup son dos líneas con seis capas apiladas por palabra y una figura que no es una
silueta plana. **Lo exporta la herramienta de diseño; el producto solo abre el hueco.**

| Pieza | Fichero | Cómo tiene que venir |
|---|---|---|
| **Logotipo, sobre fondo claro** | `public/img/client-logo.svg` | **SVG con el texto convertido a CONTORNOS** · `viewBox` presente · fondo transparente |
| **Logotipo, sobre fondo oscuro** | `public/img/client-logo-ink.svg` | Igual, pero legible sobre tinta. ❗ **Hace falta**: al abrir el menú a pantalla completa el logo cae sobre superficie oscura. Un texto se adapta solo; **una imagen no**. ✅ **El hueco EXISTE desde `#216`**: se sirven las dos y elige el CSS por `[data-surface]`. ⚠️ Y con dos imágenes el nombre accesible se duplica o se pierde —`display: none` saca el `alt` del árbol—, así que con las dos van `aria-hidden` y el nombre lo pone un `sr-only` que está siempre |
| **Logotipo, respaldo raster** | `public/img/client-logo@4x.png` | PNG con transparencia, **≥ 216 px de alto** (4× de los 54 px a los que lo pinta la barra). Sirve para correos y para Open Graph, donde un SVG no vale |
| **Icono de pestaña** | `public/img/client-favicon.svg` | **SVG cuadrado** (`viewBox` cuadrado, p. ej. `0 0 64 64`) · **con su propio fondo**, no transparente · **legible a 16 px** |

⚠️⚠️ **Las tres reglas que no son opcionales, y el porqué de cada una:**
1. **Texto en contornos, nunca `<text>`.** El logotipo se sirve dentro de un `<img>`, y ahí **las
   fuentes externas no se cargan**: un SVG con `<text font-family="Lilita One">` sale con la fuente
   de sustitución en cualquier máquina que no la tenga instalada, sin fallar y sin avisar.
2. **Sin `<script>`, sin `<style>` externo y sin referencias a otros ficheros.** Un `<img>` no
   ejecuta JavaScript y no trae recursos externos: lo que no esté dentro del SVG, no se pinta.
3. **`viewBox` obligatorio.** Es lo que permite que la misma pieza sirva a 54 px en la barra y más
   grande en el pie. Sin él, el navegador usa el tamaño intrínseco y no escala.

⚠️ **Y el icono tiene decisiones de diseño abiertas**, no técnicas: su propio artboard dice que «la
figura completa aguanta de 48 px para arriba» y que por debajo hay que cambiarla por el troquel, y
deja **tres barras a elegir**. Eso lo decide el owner, no el producto.

---

**a.sexies) ❗❗ SI EL LOGOTIPO LLEVA UNA PIEZA QUE SE ANIMA: cómo tiene que venir separada** (`#265`)

El logotipo del 2.º cliente tiene una **silueta que hace de letra**, y su mockup la hace SALTAR: el
lockup aparece entero, la figura entra desde abajo con su arco, y **la letra a la que sustituye se
desvanece** justo cuando ella aterriza. Eso último se llama **el relevo**, y hoy **no se puede hacer**.

▶ **Por qué**: el SVG exportado dibuja `#u1` = «**PLA**» y `#fig` = la silueta. La **Y tipográfica no
existe como pieza**, así que **desde que la página carga hasta que la figura aterriza** —466,7 ms de
espera **más** el vuelo de 1.000, o sea **1,47 s**— el logotipo se lee «PLA JUMPPARK», con un hueco
donde el mockup enseña su Y. ⚠️ Aquí decía «475 ms» y era una cifra que no salía de ningún número del
sistema (`#266`): la espera son 466,7 y el hueco dura todo el vuelo.
`[DECIDIDO owner, 2026-08-29]`: **el logo se vuelve a exportar con la Y**.

**Lo que tiene que traer el fichero**, exactamente:

| | |
|---|---|
| La pieza | La **Y** completa, con las **mismas capas** que las demás letras: los 26 pasos de extrusión, la capa de tinta, la de blanco, su color propio (`#1AA9DE` en el artboard) y los dos degradados (`sombraTexto` y `brilloTexto`) |
| Su `id` | `uy` — un **`<path id="uy">` dentro de `<defs>`**, hermano de `u1`/`u2`/`fig`, más sus `<use>` en el dibujo. ⚠️ **No un `<g id="uy">` con las capas dentro**: en este fichero los `id` son plantillas en `<defs>` y lo que se dibuja son `<use>` que las referencian (31 para `u1`, 31 para `u2`, 21 para `fig`). Pedir la estructura equivocada se descubre al integrar, no al recibir |
| Dónde | Exactamente donde iría la Y de «PLAY», o sea **debajo** de la silueta: las dos ocupan el mismo sitio y se relevan |
| Su origen | El mockup la escala desde `50% 88%` al retirarla. Si viene como grupo propio, el producto lo declara; no hay que hacer nada en el fichero |

⚠️⚠️ **Y la pieza tiene que quedar DENTRO del grupo que se anima, o no se moverá con él** (`#266`):
la animación cae sobre «el grupo que contiene los `use` de la figura», porque una animación CSS
sobre un elemento de `<defs>` **no alcanza al clon del `<use>`** — eso costó tres tandas.

❗❗❗ **Y SI VUELVES A EXPORTAR EL LOGOTIPO, HAY QUE PASARLE LOS DOS GUIONES** (`#275`):

```bash
php scripts/logo-sombra.php  public/img/client-logo.svg '#301002'
php scripts/logo-letra-a.php public/img/client-logo.svg public/img/client-logo-a.path
```

⚠️ **Esto SUSTITUYE al guion de `#274`** (`logo-contorno.php`, retirado): aquel adelgazaba las bandas
del contorno, que **ya eran las del mockup al dígito**, y de paso dejaba la extrusión asomando por
fuera del cian. Su premisa era falsa.

Los dos guiones son **idempotentes** y **abortan antes de escribir** si tocaran algo que no les
corresponde. Corrigen **tres cosas que la exportación de un lockup CSS a SVG traduce mal**:

| | qué pasa | por qué |
|---|---|---|
| La sombra de FUERA | la extrusión sale engordada media anchura de trazo por lado | **`text-shadow` NO arrastra el `-webkit-text-stroke`**: sus copias son el glifo desnudo |
| La sombra de DENTRO | el velo del borde inferior sale 3,4× más fuerte, y frío bajo las dos palabras | `background-clip: text` mide sobre la **caja de línea**; `objectBoundingBox` mide sobre la **tinta** — y el lockup usa **un velo por palabra** |
| La letra tapada | la letra bajo la silueta viene **mordida** por su contorno | la exportación **restó** la silueta del trazado de la palabra; se ve en cuanto la animación mueve la figura |

❗ **Lo que cerraría esto de verdad es el export**: pídele al diseñador que la exportación **no reste
la silueta de las letras** (que las palabras vayan completas y el dibujo encima) y que la profundidad
sea una copia del glifo **sin** el trazo. Mientras venga así, hay que pasar los guiones.
⚠️ `#301002` es el velo cálido de ESTE cliente y **no vive en el repo**: se le pasa al guion. Sin él
se aplica solo la corrección de fuerza, que es general.
⚠️ `public/img/client-logo-a.path` es la letra reconstruida, y es **una pieza más del paquete de
marca**: gitignorada, y `deploy.sh` la excluye del `--delete`. Cómo se deriva, en
`docs/specs/tema-por-instalacion.md` §27.3.
⚠️ **Y el logotipo NO viaja en el despliegue**: `deploy.sh` excluye todos los ficheros de marca, así
que tras afinarlo hay que **subirlo a mano** al servidor (o pasar allí los guiones).

⚠️ **El resto del logotipo NO cambia**: es el mismo fichero con una pieza más. Nada de rehacerlo —
está verificado byte a byte contra el PNG que exportó el owner.

⚠️ **Y no vale una Y en `<text>`**, por la regla 1 de arriba: sin la fuente instalada saldría otra.

▶ **Los números del relevo, ya medidos del mockup**, para cuando la pieza llegue: la Y se va en
**560 ms** (con el tempo, 622), empezando **130 ms antes del aterrizaje**, con
`opacity 1 → .34 → 0`, `translate(0, 0 → 4 → 8 px)` y `scale(1 → .994 → .985)`, curva
`cubic-bezier(.35, .1, .6, 1)`. La silueta ya nace invisible durante la espera, así que **esa mitad
del relevo está hecha**.

**b) Estructura y detalle → `public/css/client.css`** (`DECISIONES #143`).
Hoja **OPCIONAL** de la instalación. El layout la carga **la última de las cuatro** —después de
`landing.css`, del tema inyectado y de `site.css`—, así que redefinir un token ahí gana en cascada:

```css
/* public/css/client.css — el paquete de tema de esta instalación */
:root {
    /* SUPERFICIE — la de tinta se deriva sola de estas dos (ver el aviso de abajo) */
    --bg: #F4F4F1;  --bg-soft: #E8E9E5;  --bg-card: #FFFFFF;
    --fg: #101418;  --fg-mute: #626A72;
    --sheet: #FFFFFF;              /* LA HOJA: la tarjeta que va ENCIMA de la superficie */

    /* TIPOGRAFÍA — el nombre visible; qué se DESCARGA va en THEME_FONTS (b.bis) */
    --font-display: "Su Fuente", system-ui, sans-serif;

    /* FORMA · la escala de canto ENTERA (siete escalones, `DECISIONES #193`).
       Redefinir uno mueve TODOS los cantos de ese rol; no hay literales sueltos que se queden
       quietos, porque `ShapeScaleTest` no los deja entrar. */
    --r-xs: 6px;   /* badges y tags dentro de una tarjeta */
    --r-sm: 6px;   /* items interiores (filas de desplegable) */
    --r-md: 10px;  /* controles, celdas y contenedores medianos */
    --r-btn: 10px; /* TODOS los botones */
    --r: 16px;     /* tarjetas y marcos */
    --r-lg: 24px;  /* tarjetas grandes y bloques destacados */
    --r-pill: 999px;

    /* FOCO · el anillo con el que se navega con teclado. El OFFSET no se toca: es encaje de
       cada componente. Y sigue al tema solo: dentro de una superficie oscura `--fg` ya vale
       claro, así que el anillo se invierte sin declarar nada. */
    --focus-w: 3px;
    --focus-color: #F5C400;

    /* ⚠️⚠️ **AVISO PAGADO (2026-08-28, `DECISIONES #206`): fijar `--focus-color` a un literal
       ROMPE la inversión automática.** El producto lo deja en `var(--fg)` justo para que siga a la
       superficie; en cuanto un paquete pone un color fijo, ese color tiene que valer en las DOS.
       Al montar el primer paquete real, el amarillo del cliente daba **1,49 sobre papel** —WCAG
       1.4.11 exige 3,0— y su propia auditoría de 20 pares no incluía ese par: el anillo quedaba
       invisible en casi toda la web.
       ▶ **Si tu marca tiene un color de foco propio, decláralo POR SUPERFICIE** y comprueba los dos
       contrastes. Ninguna guarda del producto lo hace por ti: calculan sobre la raíz del PRODUCTO,
       no sobre la de tu paquete.

           [data-surface="ink"]   { --focus-color: <el tuyo sobre oscuro>; }
           [data-surface="paper"] { --focus-color: <el tuyo sobre claro>;  }
    */

    /* LA TIRA del pie · cinco franjas. Por defecto CICLAN sobre los dos colores de marca;
       una instalación con cinco colores propios los pone aquí, uno a uno. */
    --strip-1: #1AA9DE;  --strip-2: #A3C21C;  --strip-3: #F5C400;
    --strip-4: #F2711C;  --strip-5: #D93E14;
}
```

⚠️ **La superficie OSCURA se deriva sola.** El producto calcula la paleta de tinta a partir de
`--fg` y `--bg`, así que redefiniendo esos dos ya tienes las dos superficies coherentes; si quieres
afinarla, redefine los `--ink-*`. **No la teclees entera**: `SurfaceScopeTest` exige que se derive,
justo para que cambiar la marca no deje media web con el color de otro cliente.

⚠️⚠️ **CORRECCIÓN (2026-08-27): la lista de abajo decía que el paquete no puede cambiar las
SOMBRAS, y desde `DECISIONES #196` SÍ PUEDE.** Aquella medición miró solo el difuminado; rehecha
con las cuatro dimensiones salió que **no había ninguna escala que extraer** —53 sombras, 42 formas
distintas— y que el propio sistema del cliente **tampoco tiene**: declara dos formas. La pregunta
pasó a ser «¿para qué sirve cada sombra?» y salieron **tres roles**:

```css
    /* ELEVACIÓN · tres roles, no una escala (`DECISIONES #196`). Redefinirlos mueve las 28
       sombras del producto. Leen `--paper-fg`, el alias que NO se invierte en superficie
       oscura: una sombra es ausencia de luz, y es oscura en las dos superficies. */
    --shadow-lift:  none;                        /* se despega al pasar el ratón */
    --shadow-float: 5px 5px 0 var(--paper-fg);   /* flota sobre el contenido */
    --shadow-modal: 0 24px 60px rgba(0,0,0,.45); /* tapa la página, con velo */
```

▶ Quedan **cinco** excepciones que el paquete no alcanza —tres direccionales, un artefacto
imprimible y el pulgar de un interruptor—, enumeradas en `specs/tema-por-instalacion.md` §13.5, y
**la lista solo encoge**: era de seis hasta que el selector de idioma salió del pie (`#205`).

✅ **d) EL TEMPO — y esto CORRIGE al párrafo que había aquí** (`DECISIONES #222`, tanda 2d).
Decía «lo que el paquete TODAVÍA no puede cambiar: el tempo, 237 declaraciones con 48 duraciones y
20 curvas, y 4 tokens que cubren una parte mínima». **Ya no es cierto**: el movimiento es un
sistema, y un paquete lo retempla entero redefiniendo **once tokens**.

```css
    /* CURVAS · cuatro, con contrato. Redefinirlas mueve TODO el movimiento del producto. */
    --ease-entra: cubic-bezier(.34, 1.56, .64, 1); /* lo que APARECE: se pasa de largo y vuelve */
    --ease-cae:   cubic-bezier(.2, 1.56, .25, 1);  /* el rebote GRANDE: uno por pantalla */
    --ease-sale:  cubic-bezier(.4, 0, .2, 1);      /* cierres, foco y TODO el hover */
    --ease-bucle: linear;                          /* solo esperas */

    /* DURACIONES · siete, cada una con su uso. */
    --dur-toque:  120ms;  /* hover de icono y de enlace */
    --dur-sale:   180ms;  /* botones, cierres, salidas: la mitad de su entrada */
    --dur-estado: 240ms;  /* cambio de estado dentro de un componente */
    --dur-entra:  320ms;  /* entrada simple de una tarjeta */
    --dur-cae:    420ms;  /* cascada, sello, confirmación. EL TECHO */
    --dur-salto:  620ms;  /* la única excepción: el salto del hero */
    --dur-espera: 900ms;  /* ciclo de espera (el spinner lo lee) */
```

⚠️ **Tres principios ordenan la tabla, y explican por qué no es simétrica**: lo que entra rebota y
lo que sale no —y sale en la **mitad** de tiempo—; el sobreimpulso se paga en **píxeles**, nunca en
opacidad ni color; y **nada en bucle salvo las esperas**.
⚠️ **Lo que queda fuera son los bucles AMBIENTALES** —marquesinas, iconos que laten, el latido del
CTA doble (`--dur-invite`)—: no son tiempos de respuesta, así que no compiten con los siete.
`MotionScaleTest` los reconoce por llevar `infinite`, no por su nombre.

⚠️ **Y un aviso sobre la tira**: si tu marca solo tiene dos colores, **no la redefinas** — el
default cicla sobre `--zone-1` y `--zone-2` y siempre da colores enteros. Repartir cinco pasos
interpolados entre dos colores parece mejor idea y **no lo es**: con un par casi complementario la
franja del medio sale gris sucio, y cambiar de espacio de color no lo arregla (medido: `oklab` da
croma 0,024 frente a 0,025 de `srgb`).

**b.bis) La TIPOGRAFÍA son DOS mitades, y con una sola no se ve nada.**
- **Qué se DESCARGA** → `THEME_FONTS` en el `.env` (lo lee `config/theme.php`), en el formato de la
  URL de Bunny: `slug-en-minusculas:pesos|otro-slug:pesos`.
- **Qué se USA** → los tokens `--font-display` / `--font-body` / `--font-mono` en `client.css`, con
  el **nombre visible** de la familia.

```dotenv
THEME_FONTS="bungee:400|hanken-grotesk:400,500,600,700,800|jetbrains-mono:400,500,700"
```

⚠️ **El HOST no se configura** y es a propósito: la CSP permite un único origen de fuentes
(`fonts.bunny.net`), y apuntar a otro **no da error** — la CSP lo bloquea en silencio y la web se
queda con `system-ui`. Bunny sirve el mismo catálogo que Google Fonts, así que casi cualquier
familia libre está disponible sin abrir un origen nuevo.
⚠️ **Un valor inválido no rompe la web: se sirve la del producto**, y también en silencio. Si tu
fuente no aparece, el slug está mal escrito (minúsculas y guiones, pesos en centenas).

⚠️ **Con eso se retiñe la web entera**, incluidas las 145 sombras, bordes y velos que hasta el
2026-08-25 estaban escritos a mano en el color del primer cliente (`#143`). Lo vigila
`RawColourIsNotATokenTest`: un literal nuevo que repita un token existente pone la suite en rojo.

⚠️⚠️ **Tres cosas que hay que saber de esta hoja, y las tres muerden:**
- **NO se versiona** (`.gitignore`): este repo es el PRODUCTO y no lleva la marca de nadie.
- **NO viaja por `deploy.sh`**: se copia a mano al servidor **una vez**. El `rsync --delete` la
  excluye a propósito —igual que `public/uploads/`—; **sin esa exclusión el primer despliegue la
  borraría y la web volvería al tema del producto en silencio**. Lo asevera `ClientThemePackageTest`.
- **El orden importa más que el contenido**: si alguien la mueve por delante de `site.css`, carga
  perfectamente y **no pinta nada**. Ese síntoma es indistinguible de un fichero que no carga.

**c) Ficheros y dibujos → assets.**
- Assets de `public/` a sustituir: `favicon.svg/.ico/-64.png` · `apple-touch-icon.png` ·
  `og-image.jpg` · vídeo del hero (+ póster) · `images/attractions/*.webp`.
  ✅ **HECHO para el 2.º cliente el 2026-09-01** (`#313`): 26 fotos y el vídeo del hero son ya los
  de Play Jump Park, y las 14 sin referencia se borraron con la sección «En directo» que las
  usaba. `images/historia-seguridad.png` **ya no existe**: era la lámina del cliente ANTIGUO en la
  sección de normas, que `#309` rehízo.
- ❗ **El presupuesto de estos assets está MEDIDO, no es a ojo** — quien instale otro cliente lo
  reproduce con `ffmpeg`, que es lo único que hace falta:
  - **Fotos**: WebP, **1600 px de ancho**, `-quality 82` → 100–340 KB según lo detallada que sea
    la escena (media ~200 KB, 5,2 MB las 26). Es el presupuesto que ya tenían las que sustituyen.
  - **Vídeo del hero**: H.264 720p, **25 fps**, **`-crf 32` y `-an`** → 2,22 MB para 12,88 s
    (**173 kB/s**; el presupuesto de referencia son 186).
    ⚠️ **El `-an` no es un detalle**: el hero va `muted`, así que la pista de audio del original es
    peso muerto que nadie oye. ⚠️ **Ni bajar los fps**: la fuente venía a **50 fps**, que para un
    fondo en bucle es el doble de datos sin ganancia — 25 es división exacta y no produce tirón.
  - **Póster**: el **PRIMER fotograma del vídeo ya codificado**, no una foto aparte. Si es otra
    imagen, se ve un salto en cuanto el vídeo arranca.
- ⚠️⚠️ **Los nombres de fichero NO se cambian al sustituir las fotos.** Las rutas viven en tres
  sitios —`attractions.image`/`zones.image` en BD, `LandingContentSeeder` y cuatro tests—, así que
  renombrar convierte un cambio de CONTENIDO en un cambio de contrato. Se sustituye el fichero.
  ▶ Efecto lateral asumido: sobreviven las erratas del import original (`kids_tobganes`,
  `jump_atina_bal`, `kids_campo_futrbol`, `jump_equilibrio_`). No las ve ningún visitante.
- **El dibujo del SPINNER** se sustituye desde `client.css`, redefiniendo solo la mitad §B de
  `spinner.css`. Receta con ejemplo completo y las cuatro reglas que respetar:
  `sistemas/UI-SPINNER.md` **§3.bis**.
- **Los iconos de producto** son un set CURADO (`[DECIDIDO owner]`, spec §4.6): `ticket_types.icon`
  guarda la clave de un icono del sistema de diseño y el panel lo ofrece con vista previa. Un set
  propio va en el paquete del cliente; **no hay subida libre de SVG** (un SVG es código ejecutable).

- Marca en código: RESUELTO en Fase 1 — panel, wordmark de emails, PDFs y título de puerta
  leen `business.name` (BD) con fallback al nombre de producto; tema mail = `brand.css`.
  Los tokens estáticos de `public/css/*.css` (paleta/tipografías por defecto) siguen siendo
  el design system base del producto — y desde `#143` son de verdad el único sitio donde vive
  cada color, que es lo que hace que (b) sirva para algo.

### 4.d · Las MANCHAS decorativas del menú (`#228`, 2026-08-28)

El menú a pantalla completa lleva **dos siluetas grandes** en las esquinas opuestas, muy apagadas.
Son el «ritmo decorativo» que el sistema del 2.º cliente pide en cada pantalla grande.

▶ **El producto pone el hueco; el paquete pone la forma.** Aquí se decide dónde va cada mancha,
cuánto mide, cuánto se ve y de qué color —de los tokens de marca—; la instalación aporta la
silueta, y opcionalmente afina el color.

| Token | Qué es | Si falta |
|---|---|---|
| `--deco-blob-a` | La forma de arriba a la derecha (520 px, opacidad 0,17) | **No se pinta nada** |
| `--deco-blob-b` | La forma de abajo a la izquierda (440 px, opacidad 0,13) | **No se pinta nada** |
| `--deco-blob-a-color` | Su color | `var(--zone-1)` (marca) |
| `--deco-blob-b-color` | Su color | `var(--zone-2)` (marca secundaria) |

⚠️⚠️ **El default de la FORMA es una máscara transparente, no `none`.** Con `mask-image: none` el
elemento se pinta entero, así que una instalación sin manchas se encontraría **dos rectángulos de
color de marca** dentro de su menú. Un valor por defecto que falla hacia «visible» es peor que no
tener valor por defecto.

⚠️ **El color por defecto es de MARCA y puede no ser el del sistema del cliente.** Medido en Play
Jump Park: su mockup pinta la mancha de abajo con su **acento 2** (Naranja Salto `#F2711C`), que en
su sistema **no es** `--zone-2` —ése es el Lima Bote—. Por eso el color es un token aparte y no se
deriva a la fuerza: el producto acierta para una instalación cualquiera y quien tenga un sistema
con acentos propios lo afina sin tocar una sola regla.

Receta (en `public/css/client.css`, que no se versiona y no se despliega con el producto):

```css
:root {
    --deco-blob-a: url("data:image/svg+xml,…");   /* la silueta, en línea */
    --deco-blob-b: url("data:image/svg+xml,…");
    --deco-blob-a-color: #1AA9DE;
    --deco-blob-b-color: #F2711C;
}
```

⚠️ Van **en línea como `data:`** a propósito: un fichero suelto sería una cuarta pieza que
sincronizar en el despliegue (`.gitignore` + orden de carga + exclusión del `rsync --delete`), y
estas siluetas pesan poco más de 1 KB cada una. Las mismas tres reglas del logotipo aplican al
dibujo: sin texto, sin nada externo y con `viewBox` (§4.a.quinquies).

### 4.f · La FOTO de respaldo del menú (`#341`, 2026-09-02)

`public/img/client-menu.webp`

La columna derecha del menú a pantalla completa enseña una **vista previa** del destino que el ratón
señala: su foto, su nombre y su subtítulo. Esta imagen es la que se usa cuando ese destino **no tiene
foto propia**.

▶ **Quién tiene foto propia y quién no**, medido: las **zonas** la traen de `zones.image`, que se
edita en el panel (Zonas → la zona → Imagen); los **servicios** del CMS, de la suya. **Entradas,
Cumpleaños, Atracciones y Ubicación no tienen ninguna imagen que sea suya en el modelo**, y
asociarles una a la fuerza habría sido escribir el catálogo de un cliente dentro del producto — la
fuga que esta misma tanda cerró en el menú. De ahí el respaldo.

| | |
|---|---|
| Fichero | `public/img/client-menu.webp` |
| Formato | WebP (es una foto; el resto del sitio ya sirve WebP) |
| Proporción | La tarjeta la recorta con `object-fit`, así que **no hay medida obligatoria**; una foto apaisada de ~1200 px de ancho va sobrada |
| **Si falta** | **No se pinta nada**: los destinos sin foto propia caen al fondo rayado, que es el suelo del producto y lo que el mockup usa donde aún no hay foto |

Se sube por `scp` como el resto del paquete, y **lleva sus tres piezas** igual que el logotipo, el
icono y el kit: entrada en `.gitignore`, exclusión en el `rsync --delete` de `deploy.sh`, y esta
ficha. Sin las dos primeras, el primer despliegue se lo lleva **en silencio**.

⚠️ La URL lleva `?v=` con la marca de tiempo del fichero: **sustituirlo en el servidor se ve al
instante**, sin esperar a que caduque la caché del navegador. Si cambias la foto y no la ves, mira
antes si de verdad se subió — no es la caché.

---

## 5 · Auth y primer admin
- `RoleSeeder` (admin/customer/staff) + `PermissionSeeder` (22 permisos; staff = 11 de
  operativa). El admin no lleva permisos: `Gate::before` le concede todo.
- ✅ **[DECIDIDO 2026-08-19, `DECISIONES #104`] El mecanismo canónico es el comando
  `app:create-admin`** — cerrado el `[DECISION-PENDIENTE]` que había aquí. Sigue siendo cierto que
  **ningún seeder crea el admin real** (`ProductionSeeder` no crea usuarios; los `@…test` solo salen
  `if (! isProduction())`), y por eso hace falta un paso explícito:
  ```bash
  php artisan app:create-admin --email=jefa@cliente.tld --name="Nombre Apellido"
  ```
  · **La contraseña se GENERA y se imprime UNA vez** (no se pasa por `--password`: quedaría en `ps`,
    en el historial y en el log del despliegue). Anótala en el vault en ese momento.
  · **Idempotente y asimétrico a propósito**: re-ejecutarlo **repara el rol** si falta, pero **NO
    toca la contraseña** —rotarla en cada redespliegue echaría al owner de su propio panel—. Para
    rotarla: `--reset-password`.
  · **Aborta** si el rol no existe (⇒ los seeders no han corrido) o si se le pide un rol que no abre
    el panel. Sale con código **1**, así que `deploy.sh` puede encadenarlo con `set -e`.
  · `--role=staff` crea una cuenta de puerta con el mismo mecanismo.
  ⚠️ **`make:filament-user` NO sirve**: `canAccessPanel()` exige `hasRole('admin'|'staff')` y ese rol
  vive en la pivote `role_user`, que ese comando no toca — crearía una cuenta que no entra.

## 6 · Pagos (Redsys) — go-live
1. Rellenar en el panel el FUC real, terminal, nombre y URL del comercio.
2. `REDSYS_SECRET_KEY` (32 chars) SOLO en `.env`/vault. Cadena de fallback de
   `Redsys::config()`: config → setting → clave sandbox pública.
3. Pasar `redsys_environment` a `live`: la guarda del panel lo RECHAZA sin
   merchant_code+terminal o si la clave efectiva sigue siendo la sandbox / no mide 32.
4. Tras `config:cache`, smoke: la clave efectiva NO es la sandbox (racional en
   `RedsysSecretKeyConfigTest`).

## 7 · Verificación de instalación viva
- `curl -fsS https://DOMINIO/up` → 200 · `migrate:status` todo Ran ·
  `slots:generate-rolling` → N > 0.
- Públicas 200 en es/en/fr: `/`, `/precios`, `/cumpleanos`, `/servicios`, `/normas`,
  `/contacto`, `/entradas` + las 5 legales (`/privacidad`, `/condiciones`, `/cookies`,
  `/aviso-legal`, `/waiver`).
- `/admin` con el admin creado por `app:create-admin` (§5); `schedule:list` = **5** tareas (la 5.ª es `sanctum:prune-expired`,
  añadida en Fase 3 · paso 0); tabla `jobs` se vacía en ~1 min;
  `failed_jobs` vacía; compra sandbox completa (Redsys test → email de confirmación → QR).
