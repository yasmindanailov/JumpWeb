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
en el vault (§6). Después: `migrate --force` · `config:cache` · `npm run build`.
- Cron único que lo mueve todo (`routes/console.php`): `* * * * * php artisan schedule:run`
  → `orders:expire` (5 min) · poda RGPD de `CookieConsentLog` (diaria) ·
  `slots:generate-rolling` (03:00) · `queue:work --stop-when-empty` (worker por minuto;
  vigilar `failed_jobs`).
- `storage:link` NO se usa: las subidas del panel van al disco `uploads` =
  `public/uploads` directo (excluirlo del rsync `--delete`).
- `[DECISION-PENDIENTE]` El script de deploy del origen (`deploy-prod.sh`, runbook
  `10-DESPLIEGUE.md`) no se portó: JumpWeb aún no tiene canal de deploy propio.

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
| resto | `sales.hold_minutes` (seed 15; fallback de código sin fila: 20) · `purchase_horizon_months` (6) · `payment.tax_rate` (21) · `incidents.alert_email` (→ `contact.email`) · `puerta.*` · `display_timezone` · `maintenance.*` · `cookies.banner_enabled` · `registration.*` (waiver externo) · `social.*` · `landing.tagline/footer_rights` · `catalog.search_min_items` | Default sano. Fuente de verdad exhaustiva: `Settings::MANAGED` |
| **no tocar** | `redsys_next_gateway_order` (contador vivo) · `sales.manual_hold_minutes` (no expuesto) | — |

- `[DECISION-PENDIENTE]` `security.turnstile_*`: excluidas del panel («viven en el vault»)
  pero `.env.production.example` dice lo contrario; hoy la única vía es INSERT a mano.
- Nota menor verificada: `ProductionSeeder` guarda `registration.url` con `group=business`
  y el panel la reescribe a `group=registration` (sin efecto: se lee por `key`).

## 4 · Tema y marca
- Color global: setting `theme.brand` → `ThemeSettings` tematiza web, panel y emails
  (contraste WCAG automático). Color/acento por zona: columnas de `zones` en el panel.
- Assets de `public/` a sustituir: `favicon.svg/.ico/-64.png` · `apple-touch-icon.png` ·
  `og-image.jpg` · vídeo del hero (+ póster) · `images/attractions/*.webp` (27 usados por
  el seed; 40 en disco — 4 sin referencia alguna, candidatos a borrar en Fase 1) ·
  `images/historia-seguridad.png`.
- Marca en código: RESUELTO en Fase 1 — panel, wordmark de emails, PDFs y título de puerta
  leen `business.name` (BD) con fallback al nombre de producto; tema mail = `brand.css`.
  Los tokens estáticos de `public/css/*.css` (paleta/tipografías por defecto) siguen siendo
  el design system base del producto.

## 5 · Auth y primer admin
- `RoleSeeder` (admin/customer/staff) + `PermissionSeeder` (22 permisos; staff = 11 de
  operativa). El admin no lleva permisos: `Gate::before` le concede todo.
- `[DECISION-PENDIENTE]` **Ningún seeder crea el admin real de producción** (los usuarios
  `@…test` solo se crean fuera de producción). Mecanismo canónico por definir: ¿comando
  `app:make-admin` o tinker documentado?

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
- `/admin` con el admin real; `schedule:list` = 4 tareas; tabla `jobs` se vacía en ~1 min;
  `failed_jobs` vacía; compra sandbox completa (Redsys test → email de confirmación → QR).
