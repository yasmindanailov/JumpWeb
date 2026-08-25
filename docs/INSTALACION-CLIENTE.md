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

**b) Estructura y detalle → `public/css/client.css`** (`DECISIONES #143`).
Hoja **OPCIONAL** de la instalación. El layout la carga **la última de las cuatro** —después de
`landing.css`, del tema inyectado y de `site.css`—, así que redefinir un token ahí gana en cascada:

```css
/* public/css/client.css — el paquete de tema de esta instalación */
:root {
    --bg: #0E0E10;  --bg-soft: #17171B;  --bg-card: #1D1D22;
    --fg: #F2F2F0;  --fg-mute: #9A9A94;
    --font-display: "Su Fuente", system-ui, sans-serif;
    --r: 4px;  --r-lg: 8px;   /* radios: aquí, no en el panel */
}
```

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
  `og-image.jpg` · vídeo del hero (+ póster) · `images/attractions/*.webp` (27 usados por
  el seed; 40 en disco — 4 sin referencia alguna, candidatos a borrar en Fase 1) ·
  `images/historia-seguridad.png`.
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
