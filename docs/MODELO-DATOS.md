# MODELO-DATOS — Mapa actual de la base de datos

> Adaptado del proyecto origen (2026-08-12). Describe la BASE HEREDADA: el refactor
> (`00-REFACTOR.md`) puede haberla cambiado. Verifica contra el código antes de construir
> encima (CONVENCIONES §7).

> **Fuente:** REGENERADO desde el código real (`app/Domain/*/Models/*.php` + `database/migrations/`, 72
> migraciones). El `04-MODELO-DATOS.md` del origen estaba desfasado y NO se portó.

## 0. Convenciones transversales

- **i18n en columna:** todo texto traducible es JSON `{es,en,fr}` con cast `array` +
  trait `App\Domain\Platform\Concerns\HasTranslations` → se lee con `$model->tr('campo')`
  (fallback: `config('app.fallback_locale')` → primer valor).
- **Dinero:** SIEMPRE céntimos `unsignedInteger` (excepción: `order_adjustments.amount_cents`
  es SIGNED, admite créditos negativos). Moneda `char(3)` default `EUR`.
- **Fechas de negocio:** cast `date:Y-m-d` (sin hora) en `slots.date`, `special_dates.date`,
  `seasons.*_date` — comparaciones exactas e idempotencia de generadores.
- **Weekday:** `0=domingo..6=sábado` (convención Carbon `dayOfWeek`) en `opening_hours`,
  `slot_templates`, `rate_types.weekdays`.
- **Mass assignment:** la mayoría de modelos llevan `$guarded = []` (abierto; heredado).
  Tienen allowlist estos **7**: `Order`, `Payment`, `Setting`, `Role`, `Permission`, `AuditLog`
  y **`User`** — ⚠️ este último con el **atributo PHP `#[Fillable([...])]`**, no con la propiedad, así
  que el `grep -rl fillable` en minúscula NO lo ve (13 campos; `getGuarded()` sigue siendo `["*"]`).
  Rareza a vigilar al
  escribir código nuevo.
- **morphMap FORZADO** (Fase 2, 2026-08-12): `Relation::enforceMorphMap()` en
  `AppServiceProvider` con alias snake_case para los 36 modelos — las columnas polimórficas
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
| `max_per_slot`,`max_guests_per_slot`,`prep_blocks_cupo` | **override por zona del cupo de packs**; `null` = usa settings globales `packs.*` (resuelve `App\Domain\Booking\Services\PackAvailability`) |
| `image` | ruta relativa a `public/` nullable |
| `accent` | string default `jump` — **agrupación semántica, NO el color** (`DECISIONES #138`) · `color` char(7) hex nullable = el PRIMARIO de esta zona · `color_secondary` char(7) hex nullable = el acompañante; vacío ⇒ se usa el primario, **nunca el de otra zona** |
| `is_active` | la zona OPERA (vende) · `show_in_landing` = se muestra en la landing (flags desacoplados) |
| `position` | orden |

Relaciones: `hasMany Attraction` (ordenadas por `position`).

### `attractions` — atracción (Attraction)
`zone_id` FK cascade · `name`/`description`/`age`/`badge` JSON i18n · `image` · `position` ·
`is_active` · `is_special` (resalte visual, no implica pago) ·
`ticket_type_id` FK nullable `nullOnDelete` → vincula la atracción a un **complemento
vendible** (`TYPE_ADDON`): si `Attraction::complementIsPurchasable()` (addon activo+vendible,
con precio, enganchado como addon DE PAGO a ≥1 entrada vendible de la misma zona operativa),
la landing muestra precio + CTA. Versión batch sin N+1: `App\Domain\Content\Services\LandingComplementResolver`.
Desde Fase 2 (paso 1) la REGLA vive una sola vez, en el contrato de Booking
`App\Domain\Booking\Contracts\PublishableCatalog` (impl. `App\Domain\Booking\Services\PublishableCatalogReader`);
las dos formas de preguntarla —una atracción o toda la página— comparten la misma consulta.

### `ticket_types` — producto vendible unificado (TicketType) ⭐ tabla central del catálogo
Tres tipos (constantes de código, NO tabla): `entry` (entrada con franja),
`pack` (cumpleaños: cupo + extras), `addon` (complemento sin aforo ni franja).

| Grupo | Campos |
|---|---|
| Display | `name`,`description`,`period_label`,`features`,`conditions`,`badge` (JSON i18n) · `featured` · `position` · `is_active` · `icon` = clave del set de diseño que marca el producto (`DECISIONES #140`); `null` ⇒ el que le toca por su tipo. **No es un fichero**: lista curada, para que la paridad de dibujos entre superficies siga siendo comprobable |
| Venta | `type` (indexed, default `entry`) · `is_sellable` (default false) · `zone_id` (**uint indexado SIN FK real**, nullable = ambas zonas) · `duration_min` (null = ilimitada) · `tax_rate` decimal(5,2) · `wristband_color` · `seats_per_unit` (default 1; **en packs SIEMPRE 1**, normalizado por migración) |
| Ventana | `available_after_open_min`/`available_before_close_min` (offsets sobre apertura/cierre del día) · `min_advance_value` + `min_advance_unit` (`days` calendario / `hours` rodante; ver `meetsMinAdvance()`) · `prep_before_min`/`prep_after_min` (solo packs: montaje/limpieza) |
| Pack | `min_qty`/`max_qty` (invitados) · `deposit_type` (`none`\|`percent`\|`fixed`) + `deposit_value` (señal; calculador `depositCents()`) · `event_fields` JSON (esquema de campos del evento por pack: `{key,label i18n,type,required}`) · `guest_fields` JSON (esquema por-invitado; default 4 columnas `DEFAULT_GUEST_FIELDS`) |
| Familia por edad | `guest_age_family` (slug, indexado) · `guest_age_min`/`guest_age_max` (tinyint, **los dos extremos INCLUIDOS**; nulo = sin tope por ese lado). **Solo packs.** Es lo que conecta dos productos que son el mismo servicio en dos regímenes (KIDS/JUMP) — antes del 2026-08-29 **no había ninguna relación entre ellos** — y de ahí sale el veredicto de fiesta MIXTA. Vacío = el producto no distingue edades y la función está apagada. La edad la declara el post-form con un campo de tipo `age`, acotado en el saneo. `docs/specs/cumple-mixto.md` §9 |

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
  requiere tarta). Autoridad de servidor: `App\Domain\Booking\Services\AddonResolver`.

Unique `(product_id, addon_id)`.

### `rate_types` — tarifa por tipo de día (RateType)
`key` unique (`normal` | `special`; constantes) · `label` JSON i18n · `is_special` ·
`weekdays` JSON (días que la activan) · `priority` (mayor gana) · `is_active`.
Qué tarifa aplica a una fecha: `App\Domain\Booking\Services\RateResolver`. La tarifa `normal` es fallback
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

**Resolución del horario efectivo** (`App\Domain\Booking\Services\OperatingSchedule`):
`special_dates` (día concreto) → `seasons` (rango activo, la de inicio más temprano si solapan)
→ `opening_hours` (semanal).

### `slot_templates` — plantilla recurrente de franjas (SlotTemplate)
`zone_id` FK cascade · `weekday` · `start_time` · `duration_min` · `capacity` ·
`online_capacity` (resto = puerta) · `is_active` · unique `(zone_id, weekday, start_time)`.
Genera `slots` vía comando `slots:generate` (`App\Domain\Booking\Services\SlotGenerator`).

### `slots` — franja concreta vendible (Slot)
`zone_id` FK cascade · `date` · `start_time`/`end_time` · `capacity` · `online_capacity` ·
`capacity_overridden` (ajuste manual: `slots:generate` no lo pisa) · `online_sales_open` ·
`seats_taken` (**MUERTA** — ver §6) · `status` `open|closed|full` ·
unique `(zone_id, date, start_time)`.
Predicado canónico de venta online: `scopeSellableOnline` (abierta + no cerrada; el filtro de
fecha lo pone cada llamador). El aforo real se cuenta por OCUPACIÓN desde los pedidos
(`App\Domain\Booking\Services\SlotAvailability`): una entrada ocupa plaza en cada franja que abarca su duración.

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
persistido), `OrderRefundFlags` y `GuardsItemRefunds` (Payments), `HasItemActionGuards` (Booking:
guardas `can{Edit,Cancel}Item`; la mitad `canRefundItem` se partió a Payments en Fase 2 paso 5) +
`*BlockedReason`). Métodos financieros clave: `financialSummary()`
(`App\Domain\Booking\Services\OrderFinancialSummary`), `executeFullRefund`, `executePartialRefund[Batch]`,
`recordEdit` (T1 del libro: el hecho de una gestión, con signo; sustituye a
`applyExtraDue`/`applyGateCredit`/`applyDepositRemainderCredit`/`recordReductionMarker`),
`birthValueCents` (la identidad de nacimiento I1), `pendingAtGateLines`,
`notifyCustomer()` (no notifica a clientes de agenda sin email).

### `order_items` — línea de pedido (OrderItem)
| Campo | Notas |
|---|---|
| `parent_item_id` | uint nullable, indexado, **SIN FK** (integridad en app): agrupa addons bajo su entrada/pack padre. Relaciones `parent()`/`children()` |
| `order_id`,`ticket_type_id` | FK cascade |
| `slot_id` | FK cascade **nullable** (addons no tienen franja) |
| `quantity` · `free_quantity` | `free_quantity` = unidades GRATIS (incluidas) HISTÓRICAS al crear el pedido; cobro real = `(quantity − free_quantity) × unit_price` → `chargedSubtotalCents()` |
| `unit_price` | céntimos, con la tarifa aplicada (histórico, no se recalcula) |
| `is_credit` | boolean default `false` (T4 de reservas mixtas, `specs/cumple-mixto.md` §24.2): la marca de **línea de CRÉDITO** — su subtotal RESTA. La columna no puede llevar el signo (`unit_price`/`quantity` son UNSIGNED, medido): lo pone `chargedSubtotalCents()`, en un solo sitio. Hoy la escribe únicamente `MixedPartySurcharge` (el −X € del descuento, una línea por reserva con su `extra_due` gemelo negativo) |
| `seats` | plazas que ocupa (`quantity × seats_per_unit`) |
| `event_data` | JSON respuestas del evento del pack (`{key: valor}`) |
| `guest_data` + `guest_form_completed_at` | JSON lista por-invitado (post-form); estado FORM OK/PENDIENTE se DERIVA de `guest_data` vs `quantity` (`guestFormStatus()`); el sello es auditoría. Enlace firmado sin sesión con caducidad (evento + 14 días) |
| `age_family_seal` | JSON nullable (2026-08-31, `specs/cumple-mixto.md` §21, `DECISIONES #284` D1): el **SELLO de condiciones** de una fiesta — la familia por edad, sus tramos y los precios de cada pack **para el día de la franja**, copiados al nacer (`OrderCreator`) y reescritos solo al cambiar de pack (sello nuevo) o de día (el mismo, re-preciado) por `AgeFamilySealer`. El veredicto de fiesta MIXTA deriva de esto y no del catálogo. Lleva `booked_type_id` + `priced_on` (el recibo: si no casan con la fila, el sello está CADUCADO y no gobierna nada). Sin PII: `anonymize()` no lo toca. `null` en entradas y complementos, y en reservas anteriores al sello (silencio, no «sin condiciones»). Se lee por `OrderItem::ageFamilySeal()` (VO `AgeFamilySeal`) |
| `cancelled_at` (index) + `cancelled_by` FK users `nullOnDelete` | **soft-cancel terminal** (no reversible); libera plaza y sale del cómputo financiero |

Estado operativo calculado (NO persistido): `active` / `finished` (al pasar `slot.end_time`,
`isFinishedInPractice()`) / `cancelled`. Scopes: `active`, `paidScheduledPrincipal`,
`slotDateBetween`.

### `tickets` — entrada emitida, una por admisión (Ticket)
`order_id`/`ticket_type_id`/`slot_id` FK cascade · `qr_token` unique (aleatorio impredecible) ·
`status` (hoy **solo** `purchased`, escrito por `App\Domain\Booking\Services\TicketIssuer`).
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

### `order_adjustments` — los HECHOS de dinero de una línea (OrderAdjustment)
Desde la T1 del libro (`specs/desglose-libro.md` §4.2, `DECISIONES #305`, 2026-09-01) cada fila es
un hecho y `type` es el ÚNICO discriminador. `order_id` FK cascade · `order_item_id` FK nullable
`nullOnDelete` (en la práctica siempre atado a línea) · `type`:
- `deposit_split` — el reparto de la SEÑAL al nacer: la parte del valor de la línea que NO se
  cobró online (`valor − señal`; lo escribe `OrderCreator`, ≥ 0, contexto nulo). No es un
  movimiento: de él sale lo que la línea aportó al cobro online sin consultar el catálogo.
- `edit` — el DELTA ENTERO de una gestión sobre la línea (cantidad · producto · fecha con
  re-tarifa · complemento · re-escala per-invitado), **con signo** (`amount_cents` es SIGNED
  desde 2026-06-06). Una fila por gestión y por línea afectada; el `context` lleva el diff.
- `mixed` — el gemelo de la línea de fiesta mixta (suplemento + / descuento −), reconciliado EN
  EL SITIO por `MixedPartySurcharge` (línea viva, no un apunte por guardado).
- `courtesy` — la compensación: dinero devuelto SIN que desapareciera producto, escrita al
  reembolsar (≤ 0, `context.refund_id`).
⚠️ Hasta la T1 los tipos eran `extra_due` / `deposit_remainder` (y un `collected_in_person`
reservado y nunca usado): una bajada se escribía en CASCADA de créditos con un marcador de 0 €,
y el importe se reconstruía al leer con la señal del catálogo vivo (el fantasma de la señal). La
migración `2026_09_01_000100_order_adjustments_become_movements` convirtió las filas.

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
| `waiver_pending_document_id` (FK `legal_document_versions`, RESTRICT) + `waiver_pending_channel` + `waiver_pending_ip` + `waiver_pending_user_agent` | la aceptación marcada en el ALTA, a la espera del correo verificado (`#179`, spec §7·5): al verificar, `SignPendingWaiverOnVerification` la convierte en firma **tras el commit** y **con la IP/UA del momento de marcar la casilla** (`#183`: `Verified` también lo emite el cobro) si el texto sigue vigente, y nulifica las cuatro; `anonymize()` también las nulifica |
| `privacy_accepted_at`,`terms_accepted_at`,`waiver_accepted_at` | sellos; la prueba detallada vive en `consents`. ⚠️ **Fase 6**: para el waiver en modo `interno` la PRUEBA vive en `waiver_signatures`; el sello es presentación (lo escribe `WaiverSigner`, lo nulifica `anonymize()`) |

`User::anonymize()` = supresión RGPD compatible con obligación fiscal (~4 años factura):
conserva la fila y los pedidos; pisa PII, borra consents, detach roles, vacía
`guest_data`/`event_data` de sus pedidos (PII de menores/salud), redacta payloads legacy de
`audit_logs`, nulifica ip/user_agent donde fue ACTOR, borra token de reset e invalida sesiones
DB. Email anonimizado = `deleted_{id}@deleted.local` (`isAnonymized()`).
⚠️ **Fase 6 · waiver: NO toca `waiver_signatures`** — conservación con tratamiento restringido y
plazo (art. 17.3.e + 18; `RGPD-01`, `specs/waiver-probatorio.md` §4.6, `WaiverRetentionTest`).
Permisos: `hasRole()`, `hasPermission()` (rol `admin` = super-admin, puede todo);
`canAccessPanel()` = admin|staff.

### `roles` / `permissions` / pivotes
`roles(name unique, label)` — seed: `admin`, `customer`, `staff`.
`permissions(name unique, label)` — sembrados por `PermissionSeeder` (catálogo:
`App\Domain\Identity\Services\PermissionCatalog`); algunas migraciones insertan permisos idempotentes
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
(`App\Domain\Identity\Services\CookieConsent::POLICY_VERSION`) · `ip` · `user_agent`(512) · `accepted_at`
(index). Poda automática > 24 meses (`model:prune` en `routes/console.php`).

### `legal_document_versions` (LegalDocumentVersion, **INMUTABLE**) — Fase 6 · waiver
Una fila por (`slug`, `locale`, `version`) —unique—: `title` · `body` JSON `[{h,p}]` **ya interpolado**
(lo que se ENSEÑÓ a quien firmó) · `body_hash` (sha256 canónico de título + secciones) ·
`published_by` FK users nullOnDelete · `published_at` · `created_at` (sin `updated_at`). Publicar
CREA fila (`Identity\Services\LegalDocumentPublisher`; acción «Publicar versión firmable» de la página
`waiver` en el panel); `updating`/`deleting` LANZAN (`ImmutableRecordException`). Vive en Identity
porque es «lo que el titular aceptó» e Identity no puede mirar a Content. Un texto con
`[PENDIENTE…]` no se publica. Lectura: `LegalDocuments::current(slug, locale)` (respaldo → `es`).

### `waiver_signatures` (WaiverSignature, **append-only + Prunable**) — Fase 6 · waiver
`user_id` FK **RESTRICT** (la prueba sobrevive al titular) · `subject_type` (`holder|dependent`) +
`subject_id` nullable, **FK RESTRICT a `dependents`** (`#198`: una fila de menor con firma detrás no se
borra ni por SQL) · **`holder_name` · `holder_email`** (la identidad del firmante TAL Y COMO
ESTABA al firmar, `[DECIDIDO owner, 2026-08-26]`; es lo que sigue identificándole tras `anonymize()`)
· **`subject_name` · `subject_born_on`** (la del MENOR en cuyo nombre se firmó, `#198`; `null` en las
del titular) · `legal_document_version_id` FK restrict · `document_hash` (copia del de la versión) · `accepted_at`
+ `accepted_tz` · `ip` · `user_agent`(512) · `channel` (`web|api|panel`) · `declared_by_user_id` FK
users nullOnDelete (alta presencial: firma DECLARADA por el operador) · `prev_hash` · `hash` unique ·
**`canonical_version`** (con qué esquema se calculó el hash: v1 sin identidad, v2 con la del titular, v3
con la del menor; cada fila se verifica con el suyo) · `created_at`. `hash` = sha256 del JSON canónico de
`WaiverSignature::HASHED_FIELDS_BY_VERSION[v]` en ese orden; `prev_hash` encadena POR (TITULAR, SUJETO)
(`#197`/`#198`: el titular tiene su cadena y cada menor a su cargo la suya), serializado con el
`lockForUpdate()` de su fila de `users` en `WaiverSigner` (único escritor); `waiver:verify-chain` mide
sobre MySQL que N firmas simultáneas del mismo sujeto dan UNA fila. **Sobrevive a `anonymize()`.** Poda
con DOS plazos por clase de sujeto: `waiver.retention_months` (titular, desde la firma) y
`waiver.dependent_retention_months` (menor, desde su 18.º cumpleaños, sobre `subject_born_on`); sin
valor → no se poda esa clase; podar una nunca rompe la cadena de la otra. Vía el mismo `model:prune` diario. Fuera de la poda solo
borran `PurgeCustomerData` (go-live) y el verificador, por `DB::table`.

### `dependents` (Dependent, **Prunable**) — Fase 6 · menores a cargo
Las PERSONAS A CARGO que un titular declara (`specs/menores-a-cargo.md` §4.1–§4.5, `DECISIONES #191`):
`user_id` FK **RESTRICT** (la fila sobrevive a la cuenta mientras haya una firma detrás; la limpieza de
go-live la borra explícitamente antes que `users`) · `name` (120; el nombre de pila, que **sí** enseña
la puerta desde `#236`) · **`surname` (120, NULLABLE)** y **`relationship` (32, NULLABLE)** desde `#236`
· `born_on` (date) · `removed_at` nullable · timestamps. ⚠️ **Los dos nuevos son nulables a propósito**:
las fichas anteriores a `#236` no los tienen y no hay de dónde sacarlos — inventar un valor por defecto
sería meter un dato falso en una tabla que alimenta una FIRMA legal. Se exigen en el ALTA NUEVA (la
validación de `POST /me/dependents`), no en el esquema. ⚠️ `relationship` es una cadena corta y **no un
enum de BD**: el catálogo vive en `Dependent::RELATIONSHIPS` y añadir una opción no puede pedir una
migración. **Y nada más**: la EDAD no existe
como columna, se deriva (`ageOn()`/`isMinor()`/`adultFrom()`, fecha contra fecha en el «hoy» del
parque) y la fila sobrevive a la mayoría de edad. Único escritor `Identity\Services\DependentRegistry`
(solo menores; tope `dependents.max_per_account` —vacío = 20— bajo el `lockForUpdate()` de la fila del
titular). **Quitar es desvincular si hay un waiver firmado detrás** (`removed_at`; `deleting` LANZA) y
borrar de verdad si no. `anonymize()`: con firma → desvincula; sin ella → borra. Poda (`model:prune`,
detrás de `waiver_signatures`): las desvinculadas que ya no tienen ninguna firma. Desde la tanda 2
(`#198`) `waiver_signatures.subject_id` es FK **RESTRICT** a esta tabla, y la firma en nombre de un menor
lleva copiados su `name` y `born_on` (esquema canónico v3) — desde `#236` en `subject_name` va el
**nombre COMPLETO**; ⚠️ se cambió el VALOR y no el conjunto de campos a propósito, porque `computeHash()`
los cubre y las firmas anteriores tienen que conservar su hash. Desde la tanda 4 (`#202`) «referencia» =
firma O entrada asignada, y el predicado se escribe UNA vez (`Dependent::referenced()`/`unreferenced()`),
compartido por `hasReferences()` y `prunable()`.

### `dependent_assignments` (DependentAssignment) — Fase 6 · menores a cargo, tanda 4
Qué ENTRADAS de un pedido son para qué menores (`specs/menores-a-cargo.md` §4.6–§4.10, §9.9.3 D4;
`DECISIONES #202`). La posee IDENTITY y referencia el ítem por su id ENTERO: `dependent_id` FK
**RESTRICT** a `dependents` (un menor con entradas asignadas se DESVINCULA, no se borra) ·
`order_item_id` `unsignedBigInteger` FK **CASCADE** desde `order_items` (una asignación sin su línea no
significa nada; así la purga de go-live y los verificadores no la conocen) · `created_at` (sin
`updated_at`: quitar y poner son filas distintas) · único `(order_item_id, dependent_id)`. **Sin
posición**: es un CONJUNTO acotado por la cantidad de la línea, exigido al escribir y derivado al leer
(si la cantidad baja desde el panel se enseñan las primeras `quantity`). Sin relación Eloquent hacia
`OrderItem` a propósito (`ModuleBoundariesTest`): Identity lee las líneas por
`Booking\Contracts\CheckoutLines`. Único escritor `Identity\Services\DependentAssigner` (dos fases:
`check()` antes del dinero → 422 por campo; `assign()` tras el `allow`, bajo el `lockForUpdate()` de la
fila del titular, idempotente por el único). `anonymize()` la borra (como vacía `guest_data`). Sale por
`GET /orders/{code}/event-data` (`dependents[]`) y por el export (`ExportedOrderItem.dependents`).

### `customer_cards` (CustomerCard) — Fase 6 · subsistema A (carné QR)
El CARNÉ QR del titular (`specs/identidad-qr-puerta.md` §4.1, §4.4, §4.5, §9.2 A·1; `DECISIONES
#208`): `user_id` FK **CASCADE** · `token_hash` (sha256, **único**: por él se BUSCA; sobrevive a una
rotación de `APP_KEY`) · `token` (text, cast `encrypted`: para REPINTAR el QR; con la clave rotada
`CustomerCard::plainToken()` devuelve `null` en vez de lanzar, §8.1; `token` y `token_hash` están
**ocultos** a la serialización) · `issued_at` · `revoked_at` nullable · `revoked_reason` (`rotated` ·
`revoked` · `anonymized`) · timestamps · índice `(user_id, revoked_at)`. **Uno ACTIVO por titular**, lo
garantiza `Identity\Services\CustomerCards` bajo el `lockForUpdate()` de su fila (emitir y rotar; el
viejo muere en el acto). Formato: `JW` + 17 de Crockford Base32 + 1 de control = 20 caracteres
(`2⁸⁵`), `CardToken`. **Es una credencial y entra en `User::revokeAllAccess()`** (`RGPD-06`
ampliada; `anonymize()` la revoca con motivo `anonymized`; `revokeOtherAccess()` NO la toca a
propósito). Escanearla NO autentica: es una búsqueda desde la sesión del empleado. La tabla está en
`AccessRevocationTest::CREDENTIAL_TABLES` (solo `User.php` puede nombrarla).

### `customer_visits` (CustomerVisit) — Fase 6 · subsistema A (la visita acreditada)
El HECHO OBSERVABLE que JumpPoints no tenía (`identidad-qr-puerta.md` §8.3; `lealtad-jumppoints.md`
§8.1): `user_id` FK **CASCADE** · `visited_on` (date) · `registered_by` FK users nullOnDelete ·
`created_at` (sin `updated_at`) · **único `(user_id, visited_on)`**. Lo escribe la pantalla de puerta con
un acto EXPLÍCITO del empleado («registrar visita»), nunca al abrir la ficha; el único hace la
idempotencia por construcción y `Identity\Services\GateVisits::register()` audita
`puerta.visit_registered` SOLO cuando escribe.

### `audit_logs` (AuditLog — inmutable, append-only)
`user_id` nullable `nullOnDelete` (null = sistema) · `action` index (`dominio.verbo`) ·
`target_type/target_id` nullableMorphs · `payload` JSON (SOLO si no hay dato personal) ·
`payload_hash` sha256 char(64) (siempre; única huella cuando el payload lleva PII) · `ip` ·
`user_agent` · **solo `created_at`** (useCurrent, index; sin updated_at ni soft delete) ·
index `(action, created_at)`. Crear SOLO vía `App\Domain\Platform\Services\AuditLogger::log()`.
`AuditLog::CRITICAL_ACTIONS` = incidencias que destaca la página del panel
(`payments.duplicate_capture`, `payments.overbooked_capture`, `orders.refund_failed`…).

---

## 4. Dominio CMS / CONTENIDO

| Tabla | Modelo | Campos clave |
|---|---|---|
| `faqs` | Faq | `question`/`answer` JSON i18n · `position` · `is_active` |
| `park_rules` | VenueRule | `name`/`description` JSON i18n · `position` · `is_active` |
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
`display_timezone`, `incidents.alert_email`, `puerta.waiver_check_enabled` (heredado de #216; hoy
espejo que escribe el panel y respaldo de `WaiverSettings::mode()`), `waiver.mode`
(`externo|interno|desactivado`, Fase 6) y `waiver.retention_months` (vacío → sin poda).

---

## 5. PLATAFORMA (framework)

`sessions` (driver database; `user_id` indexado — la anonimización borra las del titular),
`password_reset_tokens`, `cache`/`cache_locks`, `jobs`/`job_batches`/`failed_jobs`.

### `personal_access_tokens` — tokens Bearer de la API (Sanctum, Fase 3 · paso 0)

Migración PUBLICADA al repo, no cargada desde el paquete: se comprobó que Sanctum solo declara
`publishesMigrations()` (sin `loadMigrationsFrom`), así que sin publicarla la tabla no existiría —
y además el esquema de un producto que se instala en cada cliente debe estar versionado aquí.

`tokenable` es un morph, así que guarda el **alias** `user` y no el FQCN (morphMap forzado, §0):
mover o renombrar `User` no rompe los tokens. `token` es un hash SHA-256, nunca el token en claro.
`expires_at` lo rige `config('sanctum.expiration')` (30 días por defecto) y la poda semanal de
`sanctum:prune-expired` (`routes/console.php`).

⚠️ **Sin emisor todavía**, y **NO llega en Fase 3**: `POST auth/tokens` se aplazó a **Fase 6** por
decisión del owner (`DECISIONES #29a`, 2026-08-13; en `api-v1.md` aparece tachado y `route:list` no
tiene ninguna ruta de tokens). Hasta entonces la tabla está vacía en toda instalación.
✅ **La revocación, en cambio, YA EXISTE** (esto también decía lo contrario): `User::revokeAllAccess()`
purga sesiones **y** hace `$this->tokens()->delete()`, y `anonymize()` lo invoca — el hueco que la
revisión del spec destapó está cerrado.

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
8. **`$guarded = []`** en la mayoría de modelos (ver §0); **7** con allowlist (`User` vía atributo `#[Fillable]`).
9. **Migraciones con lógica de datos del origen:** backfills/repairs quirúrgicos
   (`ServicePriceTableBackfill`, `SpecialRateLabelBackfill`) y seeds idempotentes de zonas
   (`color`, `image` con slugs `jump`/`kids`). No-op en instalación limpia, pero son
   contenido/decisiones del sector origen incrustadas en `database/migrations/`.
   ⚠️ Los dos repairs de ajustes del origen (`LegacyAddonAdjustmentRepair`,
   `LegacyGateAdjustmentReconciliation`) **se RETIRARON en la T1 del libro**
   (`specs/desglose-libro.md` §4.7, 2026-09-01): reparaban una cascada de créditos que ya no
   existe y no tenían más llamador que sus dos migraciones de 2026-06-06, que quedan
   neutralizadas (filas del historial de `migrations`, sin efecto).
   ▶ Y la migración de HECHOS del libro (`2026_09_01_000100_order_adjustments_become_movements`)
   es la única con lógica de datos PROPIA del producto: convierte `order_adjustments` a los
   cuatro tipos de §2 y es idempotente (su test la corre dos veces).
10. **`event_packages` ya no existe** (absorbida por `ticket_types.type='pack'`);
    `contact_messages` tampoco (el formulario de contacto solo envía email). Si algún doc
    antiguo las menciona, está desfasado.
11. **`seasons.name` no es i18n** (string plano): inconsistente con el resto del CMS, es una
    etiqueta interna del operador.

## 7. Servicios que gobiernan cada tabla (mapa rápido)

| Sistema | Autoridad |
|---|---|
| Tarifa por fecha | `App\Domain\Booking\Services\RateResolver` (special_dates → weekdays de rate_types → fallback `normal`) |
| Horario efectivo | `App\Domain\Booking\Services\OperatingSchedule` (special_dates → seasons → opening_hours) |
| Generación de franjas | `App\Domain\Booking\Services\SlotGenerator` (comando `slots:generate`; respeta `capacity_overridden`) |
| Aforo entradas | `App\Domain\Booking\Services\SlotAvailability` (ocupación por pedidos, no `seats_taken`) |
| Cupo packs | `App\Domain\Booking\Services\PackAvailability` (override zona → settings `packs.*`) |
| Disponibilidad producto | `App\Domain\Booking\Services\ProductAvailability` |
| Creación de pedido | `App\Domain\Booking\Services\OrderCreator` (precio en servidor, retención `expires_at`, código único) |
| Complementos | `App\Domain\Booking\Services\AddonResolver` (incluidos/obligatorios/grupos/requires, a punto fijo) |
| Pago/retorno | `App\Domain\Payments\Services\Redsys*` + `RedsysReturnHandler` (firma, idempotencia, incidencias) |
| Emisión de entradas | `App\Domain\Booking\Services\TicketIssuer` (al pasar a `paid`) |
| Auditoría | `App\Domain\Platform\Services\AuditLogger::log()` |
