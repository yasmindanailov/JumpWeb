# MODELO-DATOS — Mapa actual de la base de datos

> Adaptado del proyecto origen (2026-08-12). Describe la BASE HEREDADA: el refactor
> (`00-REFACTOR.md`) puede haberla cambiado. Verifica contra el código antes de construir
> encima (CONVENCIONES §7).

> **Fuente:** REGENERADO desde el código real (`app/Models/*.php` + `database/migrations/`, 71
> migraciones). El `04-MODELO-DATOS.md` del origen estaba desfasado y NO se portó.

## 0. Convenciones transversales

- **i18n en columna:** todo texto traducible es JSON `{es,en,fr}` con cast `array` +
  trait `App\Models\Concerns\HasTranslations` → se lee con `$model->tr('campo')`
  (fallback: `config('app.fallback_locale')` → primer valor).
- **Dinero:** SIEMPRE céntimos `unsignedInteger` (excepción: `order_adjustments.amount_cents`
  es SIGNED, admite créditos negativos). Moneda `char(3)` default `EUR`.
- **Fechas de negocio:** cast `date:Y-m-d` (sin hora) en `slots.date`, `special_dates.date`,
  `seasons.*_date` — comparaciones exactas e idempotencia de generadores.
- **Weekday:** `0=domingo..6=sábado` (convención Carbon `dayOfWeek`) en `opening_hours`,
  `slot_templates`, `rate_types.weekdays`.
- **Mass assignment:** la mayoría de modelos llevan `$guarded = []` (abierto; heredado).
  Solo tienen allowlist `$fillable` estos 6: `Order`, `Payment`, `Setting`, `Role`,
  `Permission`, `AuditLog` (`grep -l fillable app/Models/*.php`). Rareza a vigilar al
  escribir código nuevo.
- **morphMap FORZADO** (Fase 2, 2026-08-12): `Relation::enforceMorphMap()` en
  `AppServiceProvider` con alias snake_case para los 30 modelos — las columnas polimórficas
  (`prices.priceable_type`, `payments.payable_type`, `audit_logs.target_type`) guardan
  ALIAS (`order`, `ticket_type`…), nunca FQCN; los datos legacy los convirtió la migración
  `convert_morph_types_to_aliases`. Renombrar/mover modelos ya NO rompe datos. Regla:
  modelo nuevo ⇒ alias en el mapa (lo exige `MorphMapTest`); comparaciones SIEMPRE con
  `getMorphClass()`, jamás `::class` contra esas columnas.
- Vocabulario: zonas, atracciones, packs de cumpleaños, waiver, puerta, pulsera
  (vocabulario del sector origen; su generalización se decide en `00-REFACTOR.md` Fase 1/2).

---

## 1. Dominio CATÁLOGO Y AFORO

### `zones` — zona del recinto (Zone)
| Campo | Tipo/Notas |
|---|---|
| `slug` | unique (`jump`, `kids` en seed origen) |
| `name`,`subtitle`,`description`,`age_label`,`age_range` | JSON i18n |
| `area_sqm`,`rides_count` | uint nullable (display) |
| `max_per_slot`,`max_guests_per_slot`,`prep_blocks_cupo` | **override por zona del cupo de packs**; `null` = usa settings globales `packs.*` (resuelve `App\Support\PackAvailability`) |
| `image` | ruta relativa a `public/` nullable |
| `accent` | string default `jump` (token de tema) · `color` char(7) hex nullable (panel/calendario) |
| `is_active` | la zona OPERA (vende) · `show_in_landing` = se muestra en la landing (flags desacoplados) |
| `position` | orden |

Relaciones: `hasMany Attraction` (ordenadas por `position`).

### `attractions` — atracción (Attraction)
`zone_id` FK cascade · `name`/`description`/`age`/`badge` JSON i18n · `image` · `position` ·
`is_active` · `is_special` (resalte visual, no implica pago) ·
`ticket_type_id` FK nullable `nullOnDelete` → vincula la atracción a un **complemento
vendible** (`TYPE_ADDON`): si `Attraction::complementIsPurchasable()` (addon activo+vendible,
con precio, enganchado como addon DE PAGO a ≥1 entrada vendible de la misma zona operativa),
la landing muestra precio + CTA. Versión batch sin N+1: `App\Support\LandingComplementResolver`.
Desde Fase 2 (paso 1) la REGLA vive una sola vez, en el contrato de Booking
`App\Domain\Booking\Contracts\PublishableCatalog` (impl. `App\Support\PublishableCatalogReader`);
las dos formas de preguntarla —una atracción o toda la página— comparten la misma consulta.

### `ticket_types` — producto vendible unificado (TicketType) ⭐ tabla central del catálogo
Tres tipos (constantes de código, NO tabla): `entry` (entrada con franja),
`pack` (cumpleaños: cupo + extras), `addon` (complemento sin aforo ni franja).

| Grupo | Campos |
|---|---|
| Display | `name`,`description`,`period_label`,`features`,`conditions`,`badge` (JSON i18n) · `featured` · `position` · `is_active` |
| Venta | `type` (indexed, default `entry`) · `is_sellable` (default false) · `zone_id` (**uint indexado SIN FK real**, nullable = ambas zonas) · `duration_min` (null = ilimitada) · `tax_rate` decimal(5,2) · `wristband_color` · `seats_per_unit` (default 1; **en packs SIEMPRE 1**, normalizado por migración) |
| Ventana | `available_after_open_min`/`available_before_close_min` (offsets sobre apertura/cierre del día) · `min_advance_value` + `min_advance_unit` (`days` calendario / `hours` rodante; ver `meetsMinAdvance()`) · `prep_before_min`/`prep_after_min` (solo packs: montaje/limpieza) |
| Pack | `min_qty`/`max_qty` (invitados) · `deposit_type` (`none`\|`percent`\|`fixed`) + `deposit_value` (señal; calculador `depositCents()`) · `event_fields` JSON (esquema de campos del evento por pack: `{key,label i18n,type,required}`) · `guest_fields` JSON (esquema por-invitado; default 4 columnas `DEFAULT_GUEST_FIELDS`) |

Sin `price_cents`: el precio vive SOLO en `prices` (fuente única).
Relaciones: `morphMany Price` · `belongsTo Zone` · `hasOne LandingService` ·
`belongsToMany self` vía pivote `product_addons` en 3 sabores: `addons()` (solo
vendibles/activos), `configurableAddons()` (todos, para panel), `addonOfProducts()` (inverso).
Scopes clave: `sellable`, `inOperationalZone`, `ofType`, `birthdaySurfacePacks`
(= packs SIN `landingService`; tener LandingService reclasifica el pack a /servicios).

### `product_addons` — pivote producto↔complemento (ProductAddon extends Pivot)
Ambas FKs → `ticket_types` (cascade): `product_id` (entrada/pack base), `addon_id` (complemento).
Tiene `id` autoincremental, **sin timestamps**. Config POR ENGANCHE (el mismo addon puede ser
gratis en un pack y de pago en una entrada):

- `is_included` + `included_quantity` — unidades gratis (histórico en `order_items.free_quantity`).
- `is_mandatory` — auto-inyectado por el servidor, no se puede quitar.
- `quantity_mode` — `fixed` | `per_guest` (la cantidad sigue al nº de invitados).
- `allow_extra` — permite unidades extra de pago sobre lo incluido (solo `fixed`).
- `choice_group` — grupo excluyente tipo radio (Menú A ⊻ Menú B); índice `(product_id, choice_group)`.
- `max_qty` nullable — tope por reserva (solo `fixed`; cap duro global 20 aparte).
- `requires_addon_id` FK → `ticket_types` `nullOnDelete` — dependencia «requiere» (2.ª tarta
  requiere tarta). Autoridad de servidor: `App\Support\AddonResolver`.

Unique `(product_id, addon_id)`.

### `rate_types` — tarifa por tipo de día (RateType)
`key` unique (`normal` | `special`; constantes) · `label` JSON i18n · `is_special` ·
`weekdays` JSON (días que la activan) · `priority` (mayor gana) · `is_active`.
Qué tarifa aplica a una fecha: `App\Support\RateResolver`. La tarifa `normal` es fallback
**imborrable** (`isFallback()`, `deleteBlockedReason()`: `fallback_normal` | `has_prices` |
`referenced_by_special_dates` — OJO: `prices.rate_type_id` es cascade, borrar tarifa borraría precios).

### `prices` — matriz precio producto × tarifa (Price)
`priceable_type/priceable_id` (morph; en la práctica solo `TicketType`) · `rate_type_id` FK
cascade · `amount_cents` · `currency` · unique `(priceable, rate_type_id)`.
`deposit_cents` **fue eliminada** (columna muerta que nunca se usó; la señal vive en
`ticket_types.deposit_type/value`).

### `special_dates` — excepción de calendario (SpecialDate)
`date` unique · `is_closed` · `open_time`/`close_time` · `rate_type_id` FK `nullOnDelete` ·
`note` JSON i18n. Prioridad máxima en horario y tarifa.

### `seasons` — temporada con horario propio (Season)
`name` **string plano NO i18n** (etiqueta del operador) · `start_date`/`end_date` ·
`open_time`/`close_time` · `is_active` · scope `activeOn($date)`. Una temporada activa abre
TODOS los días de su rango.

### `opening_hours` — horario semanal base (OpeningHour)
`weekday` unique · `open_time`/`close_time` · `is_closed`.

**Resolución del horario efectivo** (`App\Support\ParkSchedule`):
`special_dates` (día concreto) → `seasons` (rango activo, la de inicio más temprano si solapan)
→ `opening_hours` (semanal).

### `slot_templates` — plantilla recurrente de franjas (SlotTemplate)
`zone_id` FK cascade · `weekday` · `start_time` · `duration_min` · `capacity` ·
`online_capacity` (resto = puerta) · `is_active` · unique `(zone_id, weekday, start_time)`.
Genera `slots` vía comando `slots:generate` (`App\Support\SlotGenerator`).

### `slots` — franja concreta vendible (Slot)
`zone_id` FK cascade · `date` · `start_time`/`end_time` · `capacity` · `online_capacity` ·
`capacity_overridden` (ajuste manual: `slots:generate` no lo pisa) · `online_sales_open` ·
`seats_taken` (**MUERTA** — ver §6) · `status` `open|closed|full` ·
unique `(zone_id, date, start_time)`.
Predicado canónico de venta online: `scopeSellableOnline` (abierta + no cerrada; el filtro de
fecha lo pone cada llamador). El aforo real se cuenta por OCUPACIÓN desde los pedidos
(`App\Support\SlotAvailability`): una entrada ocupa plaza en cada franja que abarca su duración.

### `rooms` — mesas/salas para packs (Room) — **estructura sin uso** (ver §6)
`name`/`note` JSON i18n · `capacity` nullable · `position` · `is_active`.

---

## 2. Dominio PEDIDOS Y PAGOS

### `orders` — pedido (Order; ~1900 líneas, corazón financiero)
| Campo | Notas |
|---|---|
| `user_id` | FK **RESTRICT** (RGPD/fiscal: usuario con pedidos no se borra, se ANONIMIZA) |
| `code` | unique, legible (`OrderCreator`: prefijo del setting `sales.order_prefix` (default `R-`, DECISIONES #12) + 6 random). **Route model binding por `code`** (`getRouteKeyName`), anti-enumeración |
| `status` | `pending` \| `paid` \| `cancelled` \| `expired` (+ `refunded` **legacy**, ya no se emite) |
| `subtotal`,`tax`,`total` | céntimos, calculados en servidor |
| `expires_at` | retención de plaza durante el pago; index `(status, expires_at)` (job `orders:expire`) |
| `paid_at` | — |
| `refunded_at` + `refund_amount_cents` | **dimensión de reembolso ORTOGONAL al status**: `paid`+refund (devolvieron dinero, servicio sigue), `cancelled` sin refund, etc. Trait `OrderRefundFlags` |

Relaciones: `hasMany OrderItem/Ticket/OrderAdjustment` · `morphMany Payment` · `belongsTo User`.
Traits: `OrderOperativeStatus` (estado operativo CALCULADO `active|in_progress|finished`, no
persistido), `OrderRefundFlags`, `HasItemActionGuards` (guardas `can{Edit,Cancel,Refund}Item` +
`*BlockedReason`). Métodos financieros clave: `financialSummary()`
(`App\Support\OrderFinancialSummary`), `executeFullRefund`, `executePartialRefund[Batch]`,
`applyExtraDue`/`applyGateCredit`/`applyDepositRemainderCredit`, `pendingAtGateLines`,
`notifyCustomer()` (no notifica a clientes de agenda sin email).

### `order_items` — línea de pedido (OrderItem)
| Campo | Notas |
|---|---|
| `parent_item_id` | uint nullable, indexado, **SIN FK** (integridad en app): agrupa addons bajo su entrada/pack padre. Relaciones `parent()`/`children()` |
| `order_id`,`ticket_type_id` | FK cascade |
| `slot_id` | FK cascade **nullable** (addons no tienen franja) |
| `quantity` · `free_quantity` | `free_quantity` = unidades GRATIS (incluidas) HISTÓRICAS al crear el pedido; cobro real = `(quantity − free_quantity) × unit_price` → `chargedSubtotalCents()` |
| `unit_price` | céntimos, con la tarifa aplicada (histórico, no se recalcula) |
| `seats` | plazas que ocupa (`quantity × seats_per_unit`) |
| `event_data` | JSON respuestas del evento del pack (`{key: valor}`) |
| `guest_data` + `guest_form_completed_at` | JSON lista por-invitado (post-form); estado FORM OK/PENDIENTE se DERIVA de `guest_data` vs `quantity` (`guestFormStatus()`); el sello es auditoría. Enlace firmado sin sesión con caducidad (evento + 14 días) |
| `cancelled_at` (index) + `cancelled_by` FK users `nullOnDelete` | **soft-cancel terminal** (no reversible); libera plaza y sale del cómputo financiero |

Estado operativo calculado (NO persistido): `active` / `finished` (al pasar `slot.end_time`,
`isFinishedInPractice()`) / `cancelled`. Scopes: `active`, `paidScheduledPrincipal`,
`slotDateBetween`.

### `tickets` — entrada emitida, una por admisión (Ticket)
`order_id`/`ticket_type_id`/`slot_id` FK cascade · `qr_token` unique (aleatorio impredecible) ·
`status` (hoy **solo** `purchased`, escrito por `App\Support\TicketIssuer`).
El ciclo `prepared/redeemed/void` y sus columnas (`prepared_at/by`, `redeemed_at/by`) **se
retiraron** (sistema «preparado» eliminado); la constante `STATUS_PURCHASED` es la única viva.

### `payments` — cobro pasarela (Payment)
Morph `payable` (en la práctica solo `Order`) · `provider` default `redsys` · `amount` ·
`currency` · `status` `pending|authorized|paid|failed|refunded|superseded` (`superseded` =
intento descartado al reintentar; NO terminal, un retorno tardío genera incidencia de cobro
duplicado) · `transaction_id` index · `gateway_order` char(12) unique nullable (=
`Ds_Merchant_Order` Redsys: 4 primeros numéricos, único por comercio+terminal PARA SIEMPRE;
generado con contador atómico en `settings.redsys_next_gateway_order` bajo `lockForUpdate`) ·
`auth_code` · `raw_response` JSON (sin datos de tarjeta) · `paid_at`.
`dsResponse()` extrae `Ds_Response` del raw. `hasMany PaymentRefund`.

### `payment_refunds` — un intento de devolución por fila (PaymentRefund)
`payment_id` FK **restrict** · `order_item_id` FK nullable `nullOnDelete` (refund parcial
por línea) · `amount_cents` · `currency` · `status` `pending|succeeded|failed` · `mode`
`rest|manual` (manual = hecho en el portal de la pasarela y registrado a posteriori;
`gateway_response_code='MANUAL'`) · `gateway_order` (reusa el del Payment original —
idempotencia REST; NO unique aquí) · `requested_by` FK restrict · `requested_at`/`processed_at`
· `raw_response` JSON · `failure_reason` (`transport_error_check_portal|gateway_denied|unknown`)
+ `failure_message` · index `(payment_id, status)`. Éxito Redsys = `Ds_Response 0900`.
Suma de `succeeded` por payment = total devuelto.

### `order_adjustments` — dinero fuera de pasarela (OrderAdjustment)
Dimensión ORTOGONAL a `payment_refunds`: aquí dinero cliente→negocio cobrado EN PERSONA.
`order_id` FK cascade · `order_item_id` FK nullable `nullOnDelete` · `type`:
- `extra_due` — ediciones del pedido que SUBEN importe (se cobra en puerta). Admite filas
  **negativas** (créditos que netean subidas previas) → `amount_cents` es **SIGNED** (solo
  se alteró en MySQL; SQLite ya era dinámico).
- `deposit_remainder` — resto de la señal (`valor_base − señal`), conocido desde la creación;
  NO se suma a `extra_due` (buckets separados en el desglose).
- `collected_in_person` — reservado v2, sin uso.

`currency` · `reason` · `context` JSON (diff estructurado del cambio; `breakdownLabel()` lo
pinta) · `applied_by` FK restrict · índices `(order_id,type)`, `(order_item_id,type)`.

---

## 3. Dominio IDENTIDAD, ROLES Y RGPD

### `users` (User — FilamentUser, MustVerifyEmail, HasLocalePreference)
| Campo | Notas |
|---|---|
| `email` | unique **NULLABLE** (clientes de agenda dados de alta por el panel con solo teléfono; varios NULL conviven en el unique de MySQL). Alta sin email DEBE persistir `NULL`, nunca `''` (`CustomerRegistrar` normaliza) |
| `pending_email` (unique) + `pending_email_sent_at` | cambio de email seguro: el viejo vive hasta confirmar el nuevo (anti-takeover) |
| `phone` | nullable en BD, obligatorio en registro web (validación form) |
| `locale` (default `es`) | idioma del CLIENTE (web + emails) · `panel_locale` nullable = idioma del panel admin, **separado** (soporta `es`/`zh_CN`) |
| `last_login_at`, `marketing_opt_in` | — |
| `privacy_accepted_at`,`terms_accepted_at`,`waiver_accepted_at` | sellos; la prueba detallada vive en `consents` |

`User::anonymize()` = supresión RGPD compatible con obligación fiscal (~4 años factura):
conserva la fila y los pedidos; pisa PII, borra consents, detach roles, vacía
`guest_data`/`event_data` de sus pedidos (PII de menores/salud), redacta payloads legacy de
`audit_logs`, nulifica ip/user_agent donde fue ACTOR, borra token de reset e invalida sesiones
DB. Email anonimizado = `deleted_{id}@deleted.local` (`isAnonymized()`).
Permisos: `hasRole()`, `hasPermission()` (rol `admin` = super-admin, puede todo);
`canAccessPanel()` = admin|staff.

### `roles` / `permissions` / pivotes
`roles(name unique, label)` — seed: `admin`, `customer`, `staff`.
`permissions(name unique, label)` — sembrados por `PermissionSeeder` (catálogo:
`App\Support\PermissionCatalog`); algunas migraciones insertan permisos idempotentes
(`orders.edit_event_data`, `orders.edit_item`, `orders.cancel_item`, `orders.refund_item`).
`role_user(user_id, role_id)` PK compuesta, cascade. `permission_role(permission_id, role_id)`
PK compuesta, cascade. N:M estándar sin paquete externo.

### `consents` (Consent)
`user_id` FK cascade · `type` (`waiver|privacy|terms|marketing`) · `accepted_at` · `ip` ·
`version` (versión del documento; `Consent::CURRENT_VERSION`, se sube al cambiar textos →
re-aceptación). Una fila por documento aceptado.

### `cookie_consent_logs` (CookieConsentLog, **Prunable**)
Prueba del consentimiento de cookies (sujeto puede ser ANÓNIMO): `user_id` nullable
`nullOnDelete` · `categories` JSON (`{"maps":bool,"social":bool}`) · `version`
(`App\Support\CookieConsent::POLICY_VERSION`) · `ip` · `user_agent`(512) · `accepted_at`
(index). Poda automática > 24 meses (`model:prune` en `routes/console.php`).

### `audit_logs` (AuditLog — inmutable, append-only)
`user_id` nullable `nullOnDelete` (null = sistema) · `action` index (`dominio.verbo`) ·
`target_type/target_id` nullableMorphs · `payload` JSON (SOLO si no hay dato personal) ·
`payload_hash` sha256 char(64) (siempre; única huella cuando el payload lleva PII) · `ip` ·
`user_agent` · **solo `created_at`** (useCurrent, index; sin updated_at ni soft delete) ·
index `(action, created_at)`. Crear SOLO vía `App\Support\AuditLogger::log()`.
`AuditLog::CRITICAL_ACTIONS` = incidencias que destaca la página del panel
(`payments.duplicate_capture`, `payments.overbooked_capture`, `orders.refund_failed`…).

---

## 4. Dominio CMS / CONTENIDO

| Tabla | Modelo | Campos clave |
|---|---|---|
| `faqs` | Faq | `question`/`answer` JSON i18n · `position` · `is_active` |
| `park_rules` | ParkRule | `name`/`description` JSON i18n · `position` · `is_active` |
| `pages` | Page | `slug` unique · `title`/`body` JSON i18n · `is_active`. Constantes: `REVIEWED_LEGAL_SLUGS` (sin aviso de borrador) y `PROTECTED_ACTIVE_SLUGS` (`privacidad`,`condiciones`,`waiver`,`cookies`,`aviso-legal` — NO desactivables: enlazadas desde footer/sitemap/banner) |
| `landing_services` | LandingService | Sección editorial de `/servicios` + item del nav. `slug` unique (= anchor) · `accent_word`/`title`/`body`/`zone_label`/`specs`/`nav_subtitle` JSON i18n · `price_table` JSON (tabla de tarifas **SOLO INFORMATIVA**, no toca el flujo de compra) · `image` · `ticket_type_id` FK nullable **UNIQUE** `nullOnDelete` (relación 1:1 con un pack; NULL = sección solo-contacto; su existencia SACA al pack de la superficie cumpleaños) · `position` · `is_active` · `show_in_nav`. Scopes `active`/`inNav`/`ordered`; `isPurchasable()` delega en `TicketType::isSellablePackForLanding()` |
| `offers` | Offer | Oferta promocional INFORMATIVA (widget flotante de la landing; sin dinero). `title` JSON i18n · `image` (disco `uploads` = `public/uploads`, servido SIN symlink; hooks `updating`/`deleted` borran el fichero huérfano) · `position` · `is_active` |

### `settings` — clave-valor white-label (Setting)
`key` unique · `value` text · `group` (default `general`). Lectura vía
`Setting::value($key, $default)` con **memo estático por petición** (invalidar con
`Setting::flushMemo()`; los tests lo llaman en `setUp` porque el estático sobrevive al
rollback de `RefreshDatabase`). ~29 claves en uso: `business.*`, `contact.*`,
`redsys_*` (incl. contador `redsys_next_gateway_order`), `sales.hold_minutes`,
`sales.purchase_horizon_months`, `packs.max_per_slot`/`max_guests_per_slot`/`prep_blocks_cupo`
(globales, overrideables por zona), `theme.brand`, `payment.tax_rate`, `maintenance.*`,
`cookies.banner_enabled`, `security.turnstile_*`, `registration.*`, `reservations.*`,
`display_timezone`, `incidents.alert_email`, `puerta.waiver_check_enabled`.

---

## 5. PLATAFORMA (framework)

`sessions` (driver database; `user_id` indexado — la anonimización borra las del titular),
`password_reset_tokens`, `cache`/`cache_locks`, `jobs`/`job_batches`/`failed_jobs`.

---

## 6. Rarezas heredadas / deuda conocida (candidatas a `00-REFACTOR.md`)

1. **`slots.seats_taken` está MUERTA.** Cache de display que nunca se mantiene; la ocupación
   viva se calcula desde pedidos (`SlotAvailability`). El propio `SlotResource` lo advierte
   («NUNCA seats_taken»). Candidata a drop.
2. **`rooms` sin uso.** Solo modelo + seed (`SalesSeeder`); la «Capa 2» de aforo por mesas
   nunca se implementó (el cupo de packs fue por settings `packs.*` + override de zona).
   Estructura muerta.
3. **`tickets` con ciclo amputado.** Solo `status='purchased'`; el flujo
   prepared/redeemed/void y sus columnas se eliminaron. El QR existe pero no hay canje digital.
4. **`Order::STATUS_REFUNDED` es legacy.** No se emite desde que el reembolso es dimensión
   ortogonal (`refunded_at`/`refund_amount_cents`); la constante sobrevive por datos antiguos.
5. **Prefijo de código de pedido**: configurable por instalación desde Fase 1
   (`sales.order_prefix`, default `R-`, `DECISIONES #12`; `PaymentSettings::orderPrefix()`).
   Los códigos ya emitidos no se reescriben; los comentarios forenses del código conservan
   referencias `JJ-XXXXXX` a pedidos reales del origen (a propósito).
6. **FKs ausentes a propósito:** `ticket_types.zone_id` (índice sin constraint; limitación
   histórica de ALTER en SQLite) y `order_items.parent_item_id` (integridad en app).
7. **morphMap forzado desde Fase 2** (ver §0): alias estables en los morphs; deuda retirada.
8. **`$guarded = []`** en la mayoría de modelos (ver §0); solo 6 modelos con `$fillable`.
9. **Migraciones con lógica de datos del origen:** backfills/repairs quirúrgicos
   (`ServicePriceTableBackfill`, `SpecialRateLabelBackfill`, `LegacyAddonAdjustmentRepair`,
   `LegacyGateAdjustmentReconciliation`) y seeds idempotentes de zonas (`color`, `image` con
   slugs `jump`/`kids`). No-op en instalación limpia, pero son contenido/decisiones del
   sector origen incrustadas en `database/migrations/`.
10. **`event_packages` ya no existe** (absorbida por `ticket_types.type='pack'`);
    `contact_messages` tampoco (el formulario de contacto solo envía email). Si algún doc
    antiguo las menciona, está desfasado.
11. **`seasons.name` no es i18n** (string plano): inconsistente con el resto del CMS, es una
    etiqueta interna del operador.

## 7. Servicios que gobiernan cada tabla (mapa rápido)

| Sistema | Autoridad |
|---|---|
| Tarifa por fecha | `App\Support\RateResolver` (special_dates → weekdays de rate_types → fallback `normal`) |
| Horario efectivo | `App\Support\ParkSchedule` (special_dates → seasons → opening_hours) |
| Generación de franjas | `App\Support\SlotGenerator` (comando `slots:generate`; respeta `capacity_overridden`) |
| Aforo entradas | `App\Support\SlotAvailability` (ocupación por pedidos, no `seats_taken`) |
| Cupo packs | `App\Support\PackAvailability` (override zona → settings `packs.*`) |
| Disponibilidad producto | `App\Support\ProductAvailability` |
| Creación de pedido | `App\Support\OrderCreator` (precio en servidor, retención `expires_at`, código único) |
| Complementos | `App\Support\AddonResolver` (incluidos/obligatorios/grupos/requires, a punto fijo) |
| Pago/retorno | `App\Support\Redsys*` + `RedsysReturnHandler` (firma, idempotencia, incidencias) |
| Emisión de entradas | `App\Support\TicketIssuer` (al pasar a `paid`) |
| Auditoría | `App\Support\AuditLogger::log()` |
