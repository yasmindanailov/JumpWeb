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

### `price_tiers` — precio por TRAMO DE CANTIDAD (PriceTier) · `#324`
| Campo | Tipo/Notas |
|---|---|
| `ticket_type_id` | FK cascade. **Solo productos principales**: un complemento no se vende por volumen (y preguntárselo costaba una consulta por complemento — ver `DECISIONES #324` §4) |
| `rate_type_id` | FK cascade — el tramo es POR TARIFA: el cuadro del cliente tiene precio distinto L-J y finde |
| `min_qty` | uint — desde cuántas unidades aplica, **inclusive** |
| `amount_cents` | uint — precio POR UNIDAD en ese tramo. **UNIFORME, no escalonado** (`[DECIDIDO owner]`): 70 personas a 13 € son 910 €, no 30×15 + 40×13 |
| único | `(ticket_type_id, rate_type_id, min_qty)` |

⚠️ **NO hay `max_qty`**: el tramo llega hasta que empieza el siguiente, así que **no puede haber
huecos ni solapes por construcción** — la familia de defectos que `#299` tuvo que cerrar con un
guardián de dominio para los tramos de EDAD.

⚠️⚠️ **Tabla propia y NO filas extra en `prices`, a propósito** (`specs/precio-por-tramo.md` §3):
media docena de agregados leen `prices` suponiendo **una fila por tarifa**, y meterle filas cambiaría
lo que miden sin que falle nada — `displayPriceCents()` haría `first()` sobre tres filas (no
determinista) y `priceVaries()`, que significa «varía según el DÍA», pasaría a ser cierto por variar
según la cantidad. Con tabla propia, **un producto sin tramos no tiene filas** y nada cambia.

⚠️ **Un producto NO puede tener tramos Y `guest_age_family`**, y lo impiden dos guardas (una en cada
modelo): el sello de `#288` congela el precio por edad al vender y un tramo lo movería después.

### `zones` — zona del recinto (Zone)
| Campo | Tipo/Notas |
|---|---|
| `slug` | unique (`jump`, `kids` en seed origen) |
| `name`,`description`,`age_range` | JSON i18n · `age_range` es el RÓTULO libre («+8 años»): la edad en números es del producto (`guest_age_min`/`max`, `#676`). `subtitle`, `age_label`, `area_sqm` y `rides_count` se borraron en `#669` |
| `height_min_cm`,`height_max_cm` | uint nullable (`#478`, `#676`): «a partir de» y «hasta»; `null` = sin restricción. Los redacta `ZoneHeightRule` |
| `escort_under_age_from_cm`,`escort_below_cm` | uint nullable (`#699`, `#761`): las reglas de «CON UN ADULTO» —por debajo de la edad, desde esa altura (Kids, 90); por debajo de esa altura (Jump, 130)—. `escort_below_cm` NO es `height_min_cm`: aquélla deja entrar acompañado, ésta deja fuera. Las redacta `ZoneEscortRule` |
| `max_per_slot`,`max_guests_per_slot`,`prep_blocks_cupo` | **override por zona del cupo de packs**; `null` = usa settings globales `packs.*` (resuelve `App\Domain\Booking\Services\PackAvailability`) |
| `opens_at`,`closes_at`,`ignores_venue_closure` | **override por zona del HORARIO** (`#322`, `specs/horario-por-zona.md`); `null` = hereda el del recinto — el MISMO contrato que la fila de arriba. Lo resuelve `OperatingSchedule::effectiveForZone()`, y por él pasan el generador de franjas, `ProductAvailability` (y con él la oferta, el checkout y las ediciones) y la re-programación. ⚠️ **La cara pública (`OperatingCalendar` → landing, «Abierto ahora», horarios publicados) sigue siendo el RECINTO**: si la zona se colara ahí, la web anunciaría horarios que el parque no tiene. ⚠️ `ignores_venue_closure` ignora el cierre ENTERO, también el de `special_dates` (`[DECIDIDO owner]`); cerrar un día concreto se hace cerrando esas franjas a mano, que el generador respeta para siempre |
| `image` | ruta relativa a `public/` nullable |
| `accent` | string default `jump` — **agrupación semántica, NO el color** (`DECISIONES #138`) · `color` char(7) hex nullable = el PRIMARIO de esta zona · `color_secondary` char(7) hex nullable = el acompañante; vacío ⇒ se usa el primario, **nunca el de otra zona** |
| `is_active` | la zona OPERA (vende) · `show_in_landing` = se muestra en la landing (flags desacoplados) |
| `position` | orden |

Relaciones: `hasMany Attraction` (ordenadas por `position`).

### `attractions` — atracción (Attraction)
`zone_id` FK cascade · `name`/`description`/`age`/`badge` JSON i18n · `image` (ruta de `public/`) · `video`
(2026-09-26, nullable: ruta dentro del disco `uploads`, la SUBE el panel; con él la web pone el «play» —`videoUrl()`,
`video_url` de `/attractions`—; `booted()` borra el fichero viejo al cambiarlo o borrar la fila) · `position` ·
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
`pack` (cumpleaños: cupo + extras), `addon` (complemento sin aforo ni franja — **salvo el que
declara `occupies_after_parent`**: la HORA EXTRA, cuya línea hija nace ocupando la franja
siguiente al tramo de su padre, `specs/hora-extra.md`).

| Grupo | Campos |
|---|---|
| Display | `name`,`description`,`period_label`,`features`,`conditions`,`badge` (JSON i18n) · los REGALOS (`#589`) ya no son columna: son `promotions` de clase `gift` del producto (`#770`, `giftLines()`) · `featured` · `position` · `is_active` · `icon` = clave del set de diseño que marca el producto (`DECISIONES #140`); `null` ⇒ el que le toca por su tipo. **No es un fichero**: lista curada, para que la paridad de dibujos entre superficies siga siendo comprobable · `image` (varchar 255 nullable, 2026-09-19 `DECISIONES #645`) = la FOTO de la ficha, **ruta dentro del disco `uploads`** (`public/uploads`, gitignorado y excluido del `rsync --delete`): la sube la clienta desde el panel y no entra en el repo. ⚠️ **No se lee a pelo**: `TicketType::imageUrl()` la resuelve a URL absoluta, y la diferencia con `zones.image` —que es ruta a `public/`— vive ahí |
| Venta | `type` (indexed, default `entry`) · `is_sellable` (default false) · `zone_id` (**uint indexado SIN FK real**, nullable = ambas zonas) · `duration_min` (null = ilimitada; **bloqueada con ventas hechas** — `occupancyMap` la lee del producto para cada línea vendida, borde 9 de `specs/hora-extra.md`) · `occupies_after_parent` (bool, default false; **solo addons**: la HORA EXTRA — el guard de `TicketType::booted()` exige duración > 0 y `seats_per_unit >= 1`, y su cinturón vive en `AddonOccupancy`) · `tax_rate` decimal(5,2) · `wristband_color` · `seats_per_unit` (default 1; **en packs SIEMPRE 1**, normalizado por migración) |
| Ventana | `available_after_open_min`/`available_before_close_min` (offsets sobre apertura/cierre del día) · `min_advance_value` + `min_advance_unit` (`days` calendario / `hours` rodante; ver `meetsMinAdvance()`) · `prep_before_min`/`prep_after_min` (solo packs: montaje/limpieza) · `cancellation_cutoff_hours` (uint nullable, `#699`): hasta cuántas horas antes se cambia o se cancela; lo INFORMA, no lo aplica (cancela el personal). Lo redacta `CancellationCutoffRule`, que da también la fecha límite de cada reserva (`deadlineFor`, T5b) · `reservation_note` (JSON i18n nullable, 2026-09-26 `#775`; **solo addons**): el AVISO del complemento en Mi cuenta, con `:n` = la cantidad («Tenéis :n pares de calcetines…», `reservationNote()`); vacío = línea genérica |
| Pack | `min_qty`/`max_qty` (invitados) · `deposit_type` (`none`\|`percent`\|`fixed`) + `deposit_value` (señal; calculador `depositCents()`) · `deposit_refundable_in_time` (bool default `false`, 2026-09-26 `#775`): la instalación PROMETE devolver la señal si se cancela en plazo (Mi cuenta lo dice; solo con señal) · `event_fields` JSON (esquema de campos del evento por pack: `{key,label i18n,type,required}`) · `guest_fields` JSON (esquema por-invitado; default 4 columnas `DEFAULT_GUEST_FIELDS`) · `guardian_authorization` (`none`\|`optional`\|`required`, el interruptor del justificante de un menor invitado) · `guest_invitation` (bool default `false`, 2026-09-17 `DECISIONES #573`: ofrece **invitación digital**; incompatible con `required` — si el justificante hace falta siempre, no hay nada que preguntar) · `honoree_counts` (bool default `false`, 2026-09-26 `#747`: **quien cumple cuenta como uno de los niños**; la reserva lo sella en `order_items.honoree_row`, y su ficha 0 es la de quien cumple) |
| Familia por edad | `guest_age_family` (slug, indexado) · `guest_age_min`/`guest_age_max` (tinyint, **los dos extremos INCLUIDOS**; nulo = sin tope por ese lado). **La familia, solo packs**; la edad la declara también una ENTRADA desde `#761` («de 4 a 7 años»: se publica y no bloquea nada). Es lo que conecta dos productos que son el mismo servicio en dos regímenes (KIDS/JUMP) — antes del 2026-08-29 **no había ninguna relación entre ellos** — y de ahí sale el veredicto de fiesta MIXTA. Vacío = el producto no distingue edades y la función está apagada. La edad la declara el post-form con un campo de tipo `age`, acotado en el saneo. `docs/specs/cumple-mixto.md` §9 |

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
- `show_in_invitation` — bool default `false` (2026-09-17, `specs/celebracion-e-invitacion.md` §4.4 D12,
  `DECISIONES #573`): **qué complemento es «el menú»** que la invitación digital enseña. Es una casilla
  del ENGANCHE y no una deducción del grupo excluyente — deducirlo sería la trampa de los calcetines
  (`#485`), presentación usada como identidad. ⚠️ Como toda columna de este pivote, tiene que entrar en
  las **tres** listas blancas (`ADDON_PIVOT_COLUMNS`, `sanitizePivotData()` y el `fillForm()` de
  «Configurar») o se cae **sin avisar** al enganchar (`#413` §4.7·ter).
- `requires_addon_id` FK → `ticket_types` `nullOnDelete` — dependencia «requiere» (2.ª tarta
  requiere tarta). Autoridad de servidor: `App\Domain\Booking\Services\AddonResolver`.
- `stage` (`booking` por defecto | `postform`) — la **FASE de venta** (`#413`, T1): dice **cuándo se
  VENDE**, no dónde se ve. Un `postform` **no nace nunca con el pedido** —tampoco en el alta manual—,
  y de eso vive la propiedad de que quitarlo sea neutro en dinero. Lista cerrada en
  `ProductAddon::STAGES`; el default deja los enganches existentes idénticos.
- `postform_cutoff_hours` nullable — el plazo de corte **por complemento**, en horas antes del inicio
  de la franja («tapas 48», «cubo 2»). ⚠️ **`0` es un valor válido** («hasta que empiece») y distinto
  de `null`: nulable en el esquema porque un enganche `booking` no tiene plazo, y **obligatorio en
  `postform`** por guard, no por columna.

⚠️ Las nueve reglas de un enganche `postform` viven en **UN** sitio,
`ProductAddon::postFormProblem()`, que comparten el guard del modelo y el **cinturón** de
`AddonResolver::forStage()` — los eventos no ven `Query\Builder::update()` ni los tres seeders que
escriben este pivote.

Unique `(product_id, addon_id)`.

### `promotions` — oferta con fecha o regalo (Promotion) · `#770`
`kind` (`offer` | `gift`) · `text` JSON i18n (una frase; una OFERTA sin texto en un idioma no sale en él, un regalo
cae al respaldo) · el OBJETIVO en dos FK anulables `cascadeOnDelete`: `zone_id` o `ticket_type_id` (las dos vacías =
toda la instalación; las dos llenas, lo impide el modelo) · `starts_on`/`ends_on` DATE del parque (`ends_on`
obligatoria en una oferta) · `is_active` · `position`. Vigente = activa y hoy (`DisplayTime::today()`) dentro de sus
fechas (`scopeCurrent`). ⚠️ **Es TEXTO, no dinero**: no toca ningún precio. Los regalos de un producto son las suyas de
clase `gift` (`TicketType::giftLines()`, relación `giftPromotions`): la columna `ticket_types.gifts` se copió aquí y se
retiró en la misma migración. Spec: `specs/promociones.md`.

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
| `attribution_channel` · `attribution_source` · `attribution_medium` · `attribution_campaign` + `attribution` json | **El sello de origen** (`specs/analitica.md` §4.1, `#678`): de dónde vino la compra, escrito en `creating` por `Booking\Observers\OrderAnalyticsObserver` desde `Platform\Services\Analytics\AttributionContext`, en el mismo INSERT. Las cuatro planas son la capa de CAMPAÑA (indexada `(attribution_source, created_at)`, se queda con el pedido); el json lleva `first_touch`/`last_touch`, entrada, dispositivo, consentimiento y —solo con `analytics`/`marketing` consentidos— `visitor_id`, `session_id` y `click_ids`, que `anonymize()` vacía. `channel` ∈ `web|app|panel|system`; `NULL` en todo = «anterior a la medición» |

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
| `addon_quantity_mode` | varchar(20) nullable (2026-09-08, `specs/hora-extra.md` §12, `DECISIONES #448`): el **SELLO DEL MODO** de una línea hija — la UNIDAD en la que se contó su `quantity` (`fixed` = bloques/unidades · `per_guest` = personas), copiada del enganche **al nacer** por las tres puertas que venden (`AddonResolver::resolve`, `OrderItemEditor::edit` vía `ItemEditPricing`, `PostFormAddons::write`). Sin él, `quantity` significaba una cosa u otra según dijera el catálogo de MAÑANA, y cambiar `product_addons.quantity_mode` **reinterpretaba lo ya vendido** en dinero **y en aforo**. Es el hermano de `unit_price`, que ya estaba a salvo por vivir aquí. ⚠️ `null` = **SILENCIO** («esta línea no declara su unidad; léela del catálogo, como antes»), y tiene dos poblaciones legítimas: los **portadores de fiesta mixta** —su producto sale de un `Setting` y no tiene enganche— y las líneas anteriores al mecanismo. **Nunca un default**: `fixed` afirmaría un modo que nadie midió, y por el lado que multiplica los minutos de sala. Se lee saneado por `ProductAddon::quantityUnit()`. **La T1 solo lo ESCRIBE**: las tres lecturas llegan en la T2 |
| `event_data` | JSON respuestas del evento del pack (`{key: valor}`) |
| `guest_data` + `guest_form_completed_at` | JSON lista por-invitado (post-form); estado FORM OK/PENDIENTE se DERIVA de `guest_data` vs `quantity` (`guestFormStatus()`); el sello es auditoría. Enlace firmado sin sesión con caducidad (evento + 14 días). ⚠️ Desde `#413` el sello **no se re-estampa si nada cambió**: `updated_at` es el token optimista de cinco puertas del operador |
| `guest_form_link_version` | La VERSIÓN del enlace firmado del post-form (`#413` D14). Viaja como `v` DENTRO de la firma y `AuthorizesGuestForm` la compara: subirla invalida en el acto los enlaces ya emitidos. Es la única palanca de revocación de esa credencial —`RGPD-06` no la alcanza, es HMAC y no hay fila que borrar—. Default 0, así que **ningún enlace vivo se rompió al desplegar**: los emitidos sin `v` se leen como 0 |
| `age_family_seal` | JSON nullable (2026-08-31, `specs/cumple-mixto.md` §21, `DECISIONES #284` D1): el **SELLO de condiciones** de una fiesta — la familia por edad, sus tramos y los precios de cada pack **para el día de la franja**, copiados al nacer (`OrderCreator`) y reescritos solo al cambiar de pack (sello nuevo) o de día (el mismo, re-preciado) por `AgeFamilySealer`. El veredicto de fiesta MIXTA deriva de esto y no del catálogo. Lleva `booked_type_id` + `priced_on` (el recibo: si no casan con la fila, el sello está CADUCADO y no gobierna nada). Sin PII: `anonymize()` no lo toca. `null` en entradas y complementos, y en reservas anteriores al sello (silencio, no «sin condiciones»). Se lee por `OrderItem::ageFamilySeal()` (VO `AgeFamilySeal`) |
| `honoree_row` | bool default `false` (2026-09-26, `fiesta-sistema-nuevo.md` §4.8, `[DECIDIDO owner]` `#747`): el **SELLO de quien cumple**, copiado de `ticket_types.honoree_counts` al nacer (`OrderCreator`) y nunca re-sellado. Con él, la ficha 0 de `guest_data` es la de quien cumple (nombre y edad en espejo con `party_invitations`), está clavada al compactar y ocupa su plaza en el suelo (`GuardianPlaces::takenIn`). Las reservas de antes, `false`: sus N fichas son de invitados. Se lee por `OrderItem::hasHonoreeRow()` |
| `cancelled_at` (index) + `cancelled_by` FK users `nullOnDelete` | **soft-cancel terminal** (no reversible); libera plaza y sale del cómputo financiero |
| `eve_notice_at` | timestamp nullable (2026-09-20, `specs/celebracion-e-invitacion.md` §4.9, `DECISIONES #717`): cuándo salió el **AVISO DE LA VÍSPERA** de esta reserva. La idempotencia es **por reserva** —un pedido con dos fiestas manda dos avisos— y el comando corre cada hora a partir de las 18:00 del parque, así que sin esta marca el titular recibiría el mismo correo seis veces. ⚠️⚠️ **Se escribe con `toBase()`**: el constructor de consultas de Eloquent SÍ toca `updated_at`, que es el token optimista del post-form, y marcar el aviso con él tumbaría la página abierta del cliente **por haberle mandado un correo**. ⚠️ **No son `party_invitations.reminded_at`/`reminded_count`**, que son del recordatorio que escribe el anfitrión (`#713`) y no envían nada. Sin PII: es una fecha de envío y muere con su fila |

Estado operativo calculado (NO persistido): `active` / `finished` (al pasar `slot.end_time`,
`isFinishedInPractice()`) / `cancelled`. Scopes: `active`, `paidScheduledPrincipal`,
`slotDateBetween`.

### `party_invitations` (PartyInvitation) — Fase 6 · la INVITACIÓN DIGITAL de una reserva
Una fila = **una reserva de cumpleaños que se puede compartir**
(`specs/celebracion-e-invitacion.md` §4.4, `DECISIONES #573`). Cuelga de la RESERVA y no del pedido, como
el post-form y el justificante: un pedido puede llevar dos visitas en dos días, y a una fiesta se invita.
`order_item_id` **unique**, FK **cascade** · `token` char(12) **unique** · `theme`(16) · `honoree_name`(60) ·
`honoree_age` tinyint nullable · `host_line`(80) · `show_host_phone` · `reminded_at` + `reminded_count`
(el aviso de la víspera, idempotente por reserva) · timestamps.
⚠️ **El enlace es un token OPACO de 12 base62 (~71 bits)**, no una firma temporal de Laravel (200
caracteres, imposible de teclear y confundible con la credencial del anfitrión) ni una ruta legible como
`/i/lucia-8`, que sería adivinable y publicaría el nombre y la edad de un menor en la URL. **Se rota**
desde el panel: es la palanca para anular un enlace ya repartido a un grupo de clase.
⚠️ El teléfono del anfitrión **no se copia aquí**: sale del de su cuenta y `show_host_phone` solo dice si
se enseña. `honoree_name` y `host_line` son texto libre que se publica bajo el dominio del parque, así
que su escritor rechaza URLs y direcciones de correo (`SEC-07`).

### `invitation_replies` (InvitationReply, **Prunable**) — lo que contesta un padre
`party_invitation_id` FK **cascade** · `order_item_id` FK **cascade** (denormalizado: la puerta y la hoja
leen por LOTES de reservas del día, y la supresión borra por los ids de las reservas del titular) ·
`attending` · `child_name`(120) · `child_key`(255, `PersonNameKey`) · `data` JSON nullable (las columnas
del pack, saneadas con `sanitizeGuestData`) · `companion` (`with_adult`\|`alone`\|`unknown`) ·
`adopted_at` + `adopted_name_key` · `host_rejoined_at` (nullable, 2026-09-26 `#747`, F3c: «Al final viene» — el «no» que el anfitrión volvió a contar; la respuesta pasa a «sí» y esto dice que el «sí» lo puso él) · `dismissed_at` · timestamps · índice `(order_item_id, child_key)`.
⚠️⚠️ **Una respuesta NO escribe `guest_data`, y ésa es la decisión que ordena la feature**: se le PROPONE
al anfitrión sobre una ficha y solo pasa a `guest_data` cuando él la ADOPTA al guardar. Si escribiera
directamente, el siguiente guardado del anfitrión la borraría (`submitGuestForm()` sustituye la lista
entera), cada respuesta dejaría obsoleto el testigo `updated_at` de su página abierta, y una edad ajena
dispararía el suplemento de fiesta mixta: **un tercero movería dinero y aforo**.
⚠️⚠️ **El índice NO es único, y es una decisión de PRIVACIDAD** (V6): rechazar un nombre repetido con «ya
nos habéis contestado por Hugo» le confirmaría a cualquiera con el enlace que Hugo va a esa fiesta.
⚠️ **Prunable a los 14 días de la visita** (el mismo plazo con el que caduca el enlace del post-form,
`RGPD-03`), y **registrado en la lista explícita de `model:prune` de `routes/console.php`**: un Prunable
fuera de ella no se poda nunca.

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
Suma de `succeeded` por payment = total devuelto. · `intent` nullable (`#127(c)`:
`value_returned|compensation|paid_in_person`, POR QUÉ se devuelve) · `reason` string(200) nullable
(T4 del libro, `DECISIONES #316`, migración `2026_09_01_120000`): el MOTIVO que escribe el operador —
obligatorio con `compensation`, opcional con el resto; el dominio lo copia al `context.note` de la
fila `courtesy` y solo lo pinta el panel—. ⚠️ No confundir con `failure_reason`, que es del gateway:
la spec §6.4 dio `reason` por existente y no existía.

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
- `courtesy` — el «Descuento por cortesía»: dinero devuelto SIN que desapareciera producto, escrita
  al reembolsar (≤ 0, `context.refund_id`). Desde la T4 (`DECISIONES #316`) SOLO la escribe un
  reembolso con `intent = compensation`, como el exceso sobre lo debido, y lleva el MOTIVO del
  operador en `context.note` (copiado de `payment_refunds.reason`; interno, solo lo pinta el panel).
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
`user_id` FK cascade · `type` (`waiver|privacy|terms|marketing`) · `accepted_at` · **`revoked_at`** ·
`ip` · **`revoked_ip`** · `version` (versión del documento; `Consent::CURRENT_VERSION`, se sube al
cambiar textos → re-aceptación). Una fila por documento aceptado.
⚠️⚠️ **La RETIRADA sella la fila; NO la borra** (art. 7.3 + art. 5.2, `#344`): la fila sigue probando
que en su día se aceptó —que es lo que justifica los envíos hechos— y `revoked_at` dice cuándo dejó
de valer. Borrarla dejaría al parque sin poder demostrar lo primero.
⚠️ **Hoy solo `marketing` se retira**: privacidad y condiciones son la base contractual de la reserva
y el descargo es una prueba que se conserva. Lo escribe `AccountPrivacy::setMarketing()`, que es
idempotente en las dos direcciones.
⚠️ Y la IP de la retirada va **aparte** de la del alta: son dos actos, en dos momentos y puede que
desde dos sitios.

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
`user_id` FK **RESTRICT** (la prueba sobrevive al titular) · `subject_type`
(`holder|dependent|guest_minor`) + `subject_id` nullable, **FK RESTRICT a `dependents`** (`#198`: una
fila de menor con firma detrás no se borra ni por SQL) · **`subject_authorization_id`** nullable, FK
RESTRICT a `guardian_authorizations` (`#328`: ⚠️ **columna PROPIA y no `subject_id`** — esa FK es dura
y rechaza cualquier id que no sea de `dependents`, medido `1452`) · **`holder_name` · `holder_email`** (la identidad del firmante TAL Y COMO
ESTABA al firmar, `[DECIDIDO owner, 2026-08-26]`; es lo que sigue identificándole tras `anonymize()`)
· **`subject_name` · `subject_born_on`** (la del MENOR en cuyo nombre se firmó, `#198`; `null` en las
del titular) · **`signer_name` · `signer_email` · `signer_phone` · `signer_relationship`** (la identidad de QUIEN
FIRMA cuando no es el titular: en un justificante de menor invitado el `user_id` es el RESPONSABLE de
la reserva y quien acepta es un adulto sin cuenta, `#328`) · `legal_document_version_id` FK restrict ·
`document_hash` (copia del de la versión) · `accepted_at`
+ `accepted_tz` · `ip` · `user_agent`(512) · `channel` (`web|api|panel`) · `declared_by_user_id` FK
users nullOnDelete (alta presencial: firma DECLARADA por el operador) · `prev_hash` · `hash` unique ·
**`canonical_version`** (con qué esquema se calculó el hash: v1 sin identidad, v2 con la del titular, v3
con la del menor, **v4 con el sujeto invitado y quien firma**; cada fila se verifica con el suyo, así que
subirla NO invalida lo firmado —verificado sobre las 24 firmas reales al subir a v4—) · `created_at`. `hash` = sha256 del JSON canónico de
`WaiverSignature::HASHED_FIELDS_BY_VERSION[v]` en ese orden; `prev_hash` encadena POR (TITULAR, SUJETO)
(`#197`/`#198`: el titular tiene su cadena, cada menor a su cargo la suya y cada menor INVITADO la suya;
⚠️ **qué sujeto es cada fila lo dice `WaiverSignature::chainKey()`, ÚNICO sitio con esa regla** — hasta
`#328` estaba escrita a mano en tres y con un sujeto sin `subject_id` los tres se cruzaban), serializado con el
`lockForUpdate()` de su fila de `users` en `WaiverSigner` (único escritor); `waiver:verify-chain` mide
sobre MySQL que N firmas simultáneas del mismo sujeto dan UNA fila. **Sobrevive a `anonymize()`.** Poda
con DOS plazos por clase de sujeto: `waiver.retention_months` (titular, desde la firma) y
`waiver.dependent_retention_months` (**las DOS clases de menor**, a cargo e invitado, desde su 18.º cumpleaños, sobre `subject_born_on`); sin
valor → no se poda esa clase; podar una nunca rompe la cadena de la otra. Vía el mismo `model:prune` diario. Fuera de la poda solo
borran `PurgeCustomerData` (go-live) y el verificador, por `DB::table`.

### `guardian_authorizations` (GuardianAuthorization, **Prunable**) — Fase 6 · el justificante de un menor INVITADO
Una AUTORIZACIÓN puntual (`specs/waiver-por-reserva.md` §4.2, `DECISIONES #328`): un menor que **no es
menor a cargo** de quien reservó, el adulto que responde por él y el pedido al que va.
`order_id` **sin relación Eloquent**, FK **RESTRICT** a `orders` (Booking no mira a Identity: la flecha
va al revés, el patrón de `dependent_assignments` — pero la política de borrado es la de una PRUEBA, no
la de una asignación) · `minor_name`(120) · `minor_surname`(120) · **`minor_key`**(255) ·
`minor_born_on` · `guardian_name`(120) · `guardian_surname`(120) · `guardian_relationship`(16, la lista
CERRADA `Dependent::RELATIONSHIPS`) · `guardian_email` · `guardian_phone` · `created_at` (**sin
`updated_at`: la fila no se edita**) · `invitation_reply_id` (FK nullable **`nullOnDelete`** a
`invitation_replies`, 2026-09-17 `DECISIONES #573`). **`unique (order_id, minor_key)`** = «un niño, un papel».
⚠️⚠️ **`invitation_reply_id` es `SET NULL` por diseño y NO entra en el hash de la firma**: las respuestas
se podan a los 14 días de la visita y esta prueba se conserva años — con `RESTRICT` la poda fallaría y
con `CASCADE` se llevaría la prueba por delante. Y meter una columna `SET NULL` dentro de un hash
verificable es lo que este repo ya pagó una vez (§10 de `waiver-por-reserva.md`: `verifyHash()` en
`false` sin que nadie tocara la fila). Sirve para que el firmador **no descuente plaza** por una firma
atada a un «sí»: esa plaza ya tiene dueño.
⚠️⚠️ **La unicidad NO va sobre los nombres crudos**: todas las tablas son `utf8mb4_unicode_ci`, donde
`'Perez' = 'Pérez'` y `'ana' = 'Ana'` dan **1**, y en SQLite —donde corre la suite— dan **0**. `minor_key`
se normaliza en PHP (`keyFor()`: minúsculas, sin tildes, espacios colapsados, con respaldo para
alfabetos no latinos) y es **determinista en los dos motores**.
⚠️ **Nace SIEMPRE con su firma, en la misma transacción y bajo el lock del responsable**
(`GuardianAuthorizationSigner`): sin eso, dos envíos simultáneos del mismo menor chocan contra el
`UNIQUE` con un 500 —visto: **7 de 8** procesos, `1062`— y un envío que falle al firmar dejaría PII de
un menor sin prueba detrás. `deleting` LANZA con una firma detrás; solo la retira `prunable()` cuando
se quedó huérfana, y **hay que registrarla en la lista EXPLÍCITA de `model:prune` de
`routes/console.php`, después de `WaiverSignature`** (la FK es RESTRICT).
⚠️ `PurgeCustomerData` la borra ANTES que los pedidos, **incluidas las de cuentas que conserva**: la
limpieza de go-live se lleva TODOS los pedidos y una prueba sin su pedido no prueba nada.

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
migración. ▶ **`#441`: cuatro columnas más, la ACEPTACIÓN RETENIDA de su exención** —
`waiver_pending_document_id` (FK **RESTRICT** a `legal_document_versions`), `waiver_pending_channel`
(8), `waiver_pending_ip` (45) y `waiver_pending_user_agent` (512), todas nullable—, hermanas exactas
de las de `users` (`#179` + S-1 de `#181`). ⚠️ **Viven aquí y no en `users` porque allí es UNA sola
ranura** y un titular puede tener N menores pendientes a la vez. Las llena `add()` cuando el titular
declara con el correo sin verificar, las convierte en firma `SignPendingWaiverOnVerification` al
verificar —descartándolas si el texto se republicó— y **las limpia `unlink()`**: una aceptación de un
menor retirado no puede sellarse después. **Y nada más**: la EDAD no existe
como columna, se deriva (`ageOn()`/`isMinor()`/`adultFrom()`, fecha contra fecha en el «hoy» del
parque) y la fila sobrevive a la mayoría de edad. Único escritor `Identity\Services\DependentRegistry`
(solo menores; tope `dependents.max_per_account` —vacío = 20— bajo el `lockForUpdate()` de la fila del
titular; y desde `#441` **el alta EXIGE la aceptación de la exención** donde el modo es `interno` y
hay versión publicada: sin ella la transacción se deshace y el menor no se crea). **Quitar es
desvincular si hay un waiver firmado detrás** (`removed_at`; `deleting` LANZA) y
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

### `user_identities` (UserIdentity) — entrar y registrarse con Google
La identidad EXTERNA de una cuenta (`specs/auth-con-google.md` §6.2; `DECISIONES #342`): `user_id` FK
**CASCADE** · `provider` (32; hoy solo `google`) · `provider_id` (191, el `sub` de OpenID Connect) ·
`email_at_link` (255, nullable: **copia probatoria** de con qué dirección se vinculó, nunca la forma
de buscar) · `linked_via` (`signup` · `login` · `account`) · `linked_at` (**sin `updated_at`**: la
fila no se edita; desvincular es borrarla). **DOS únicos**: `(provider, provider_id)` —un `sub`
apunta como mucho a una cuenta— y `(user_id, provider)` —una cuenta tiene como mucho una llave por
proveedor, para que «desvincular» tenga sujeto—.
⚠️ **No es una credencial** (con la fila no se entra a ninguna parte: entrar exige que Google lo
afirme en una petición servidor-a-servidor) **y aun así cae en `User::revokeAllAccess()`**, igual que
el carné y por la misma razón: es la palanca de «me han entrado», y un vínculo plantado por quien te
tomó la cuenta sobreviviría al reset. `revokeOtherAccess()` NO la toca.
⚠️⚠️ **En `anonymize()` se BORRA, no se redacta**: una fila redactada dejaría el `sub` ocupado en su
único y esa persona **no podría volver a registrarse con su Google nunca más**. Y el `cascadeOnDelete`
de la FK no cubre ese caso: la supresión no borra la fila de `users`.

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

### `analytics_sessions` · `analytics_events` (AnalyticsSession, AnalyticsEvent — **MassPrunable**) — el LIBRO DE EVENTOS · `#678`

`specs/analitica.md` §4.1. Viven en `Platform` como `audit_logs`: todos los módulos escriben con escalares y el libro no
mira a nadie. **Sin claves foráneas** a propósito (la poda es por `model:prune` a los 25 meses, eventos antes que
sesiones; `user_id` lo vacía `anonymize()` por tabla, porque un `nullOnDelete` no se dispara nunca, `RGPD-01`).
- `analytics_sessions`: `visitor_id` (la cookie propia de 13 meses, `Visitor::COOKIE`), `user_id` **nullable y solo con la
  categoría `analytics`** (régimen identificado; sin ella la fila es estadística anónima del editor: la exención AEPD 2024),
  `started_at`/`last_seen_at` (30 min de inactividad cierran la sesión), `surface`, `entry_route` (patrón, sin query),
  `referrer_host`, `utm_*`, `ref`, `click_ids` json (solo con `marketing`), `device`, `locale`, `consent` json (la foto),
  `is_bot`, `is_internal`. Índices `(visitor_id, last_seen_at)` y `(last_seen_at)`. **Sin IP ni user agent.**
- `analytics_events`: `event_id` (ULID del cliente, **único con `visitor_id`**: reenviar un lote no duplica), `session_id`,
  `visitor_id`, `user_id`, `name` (clave de `Platform\Services\Analytics\Contract`; los de servidor solo los escribe el
  `Recorder`), `route` (patrón), `props` json (≤ 2 KB, lista blanca por evento, sin PII), `occurred_at` (reloj del cliente,
  acotado a ±5 min), `received_at` (la verdad temporal), `order_id`/`payment_id`/`refund_id`. Índices `(received_at)`,
  `(name, received_at, session_id)`, `(session_id)`, `(order_id)`.

### `experiments` (Experiment) — los experimentos · `#678` T5a, `#737`

`specs/analitica.md` §4.4. Configuración del producto en `Platform`, como `settings`: **sin asignaciones guardadas**
(se calculan con `hash(key | sujeto)` en cada petición) y sin PII. `key` string(48) **unique** (`[a-z][a-z0-9_-]{0,47}`;
viaja al cliente y al libro en `experiment_exposed`) · `name` · `variants` json, lista ORDENADA de `{key, weight}` (pesos
enteros positivos, no porcentajes; el orden es el reparto: cambiarlo rebaraja, así que un experimento vivo se cierra y
se abre otro) · `active` bool · `started_at`/`ended_at` nullable (vivo = activo y dentro de la ventana) · timestamps.
Guardar o borrar una fila olvida la caché de 60 s de los vivos.

### `google_business_connections` (GoogleBusinessConnection) — la ficha de Google del parque · `#720`
**Fila ÚNICA**: `singleton` bool con índice **UNIQUE** (invariante de BD, no convención: una
instalación es un parque y un parque es una ficha). `status` (enum `GoogleBusinessStatus`, default
`ready_to_connect`) · `status_changed_at` · `refresh_token` text **cast `encrypted`** ·
`token_fingerprint` char(64) (sha256 del token, para **comparar-y-escribir**: un worker con el token
viejo no pisa una reconexión) · `location_name` (`locations/{id}`), **`account_name`**
(`accounts/{id}`, `#726`), `location_title`, `place_id`, `maps_uri`, `new_review_uri` ·
`connected_by_user_id` nullable `nullOnDelete` · `connected_at`.
⚠️⚠️ **`account_name` NO es redundante**: son DOS APIs que nombran la misma ficha de dos formas
(medido contra la doc oficial el 20-09). `locations.list` (v1) devuelve `locations/{id}` **sin
cuenta**; `reviews.list` (v4, y otro host) exige `accounts/{id}/locations/{id}`. Sin esta columna **no
se puede pedir ni una reseña**. El `parent` lo compone `reviewsParent()`; `null` = elegida antes de
`#726`.
⚠️ **NO vive en `settings`**: `Setting::value()` lee la tabla entera y la memoriza por proceso, así
que el token se pasearía por toda petición que consulte cualquier ajuste.
⚠️ `$hidden` = token y huella (la pantalla es Livewire y serializa al snapshot del navegador).
⚠️ El token se lee por `readToken()`, que descifra a mano y convierte `DecryptException` en
«caducada»; **`hasStoredToken()` mira el atributo crudo** para distinguir «no hay token» de «hay y no
se puede leer». Los dos usan `getAttributes()`, **nunca `getRawOriginal()`**.
⚠️ **Quién conectó es FK del esquema SIN relación de Eloquent**: Platform no depende de ningún módulo
(`ModuleBoundariesTest`: `'Platform' => []`). Lo resuelve la capa de entrega.
De los siete estados, `unconfigured` y `ready_to_connect` **no se guardan**: los deriva
`GoogleBusinessConnectionState` de las credenciales y del token.

---

## 4. Dominio CMS / CONTENIDO

| Tabla | Modelo | Campos clave |
|---|---|---|
| `faqs` | Faq | `question`/`answer` JSON i18n · `position` · `is_active` |
| `park_rules` | VenueRule | `name`/`description` JSON i18n · `position` · `is_active` |
| `pages` | Page | `slug` unique · `title`/`body` JSON i18n · `is_active`. Constantes: `REVIEWED_LEGAL_SLUGS` (sin aviso de borrador) y `PROTECTED_ACTIVE_SLUGS` (`privacidad`,`condiciones`,`waiver`,`cookies`,`aviso-legal` — NO desactivables: enlazadas desde footer/sitemap/banner) |
| `landing_services` | LandingService | Sección editorial de `/servicios` + item del nav. `slug` unique (= anchor) · `accent_word`/`title`/`body`/`zone_label`/`specs`/`nav_subtitle` JSON i18n · `price_table` JSON (tabla de tarifas **SOLO INFORMATIVA**, no toca el flujo de compra) · `image` · `position` · `is_active` · `show_in_nav`. **Sus packs van en `landing_service_products`** (`#588`: `landing_service_id` + `ticket_type_id` **UNIQUE**, cascada en los dos lados — un servicio vende varios packs, un pack está en un servicio como mucho; sin packs = sección solo-contacto; la existencia del enlace SACA al pack de la superficie cumpleaños). Scopes `active`/`ordered`; `isPurchasable()`/`purchasableProducts()` delegan en `TicketType::isSellablePackForLanding()`; la tabla de precios de un servicio con packs sale de sus tramos (`GroupRateTables`) y `price_table` queda para el que no vende online |
| `offers` | Offer | Oferta promocional INFORMATIVA (widget flotante de la landing; sin dinero). `title` JSON i18n · `image` (disco `uploads` = `public/uploads`, servido SIN symlink; hooks `updating`/`deleted` borran el fichero huérfano) · `position` · `is_active` |
| `testimonials` | Testimonial | Las OPINIONES del panel (`#490`): `origin` (`own` escrita aquí · `google` COPIADA de la ficha del parque, `#771`) · `source_ref` UNIQUE (id de la reseña en Google: reimportar actualiza) · `source_url` (la ficha, «Ver en Google») · `author` · `author_meta` · `avatar` y `photos` JSON (rutas del disco `uploads`, `resenas/<hash>.ext`, DESCARGADAS al importar: nada se pide a Google; se borran cuando ninguna fila las usa) · `rating` · `published_at` (el día que decía «hace N») · `text` JSON i18n · `reply` · `tags` JSON (páginas en que sale: «kids»…) · `position` · `is_active`. La cascada `SocialProof` solo lee las `own`; las copiadas salen por `/reviews` |
| `bar_images` | BarImage | Las imágenes de `/bar` (`#536`). `kind` (`menu` = una cara de la CARTA · `venue` = la foto del local) · `image` (disco `uploads`, mismos hooks de limpieza que `offers`) · `alt` JSON i18n **obligatorio en el formulario** —la carta se publica como IMAGEN, así que es lo único que encuentra un lector de pantalla— · `width`/`height` nullable **medidos al subir** (`getimagesizefromstring`, para que la página no salte) · `position` · `is_active`. De `venue` se publica la PRIMERA activa por orden: no hay unicidad en el esquema para no obligar a borrar la vieja antes de subir la nueva |

### `google_business_reviews` · `google_business_review_summaries` — las reseñas de la ficha · `#727`
**La T2·1 de `specs/google-business-profile.md` §4.3.** Lo que Business Profile permite guardar es
*«limited amounts of Content»* hasta **30 días**: aquí solo caben las **candidatas** (con texto, con el
mínimo de estrellas, no ocultas, una docena) y el **resumen**. No es un espejo de la ficha.
`google_business_reviews`: `review_name` **unique** (el nombre de recurso de Google, por el que §4.3·3
deduplica) · `author_name`/`author_photo_path`/`anonymous` (el `null` de una anónima entra ANTES del
INSERT) · `star_rating` tinyint 1–5 (Google lo manda como texto, `FIVE`…; se traduce al entrar) ·
`comment` + `text_ambiguous` (se publica el ORIGINAL; el analizador de la traducción mezclada **falla
cerrado**) · `reply_comment`/`reply_at` (la respuesta del parque) · `photos` JSON (**rutas** nuestras;
las rellena la T2·4) · `review_created_at`/`review_updated_at` · `fetched_at` **index**.
`google_business_review_summaries`: **fila única** (`singleton` UNIQUE, como la conexión) ·
`average_rating` decimal(2,1) nullable · `total_review_count` · `maps_uri`/`new_review_uri` ·
`fetched_at`.
⚠️⚠️ **EL PLAZO SE APLICA AL LEER, NO AL PURGAR**: `scopeWithinRetention()` filtra por `fetched_at` a
**29 días** y la purga (`Prunable` en `model:prune`) corta a **30**. Son dos números distintos a
propósito: la purga es un comando que puede no haber corrido, así que la garantía está en la consulta.
⚠️ **Segundo plazo, más corto, sobre el dato PERSONAL**: pasados **3 días** sin una pasada que
confirme la reseña, `publishableAuthor()`/`publishablePhotoPath()` devuelven `null` y la reseña sale
anónima. El texto sobrevive al autor: caduca la certeza de que sigue publicada, no la opinión. Se mide
contra el `fetched_at` de LA FILA, así que una reseña que deja de venir envejece sola.
⚠️⚠️ **Una imagen es una RUTA nuestra o `null`, jamás un host de terceros**: guarda en `saving()` que
**lanza** `ForeignImageUrlException` (§4.3·9). Caza también la URL sin esquema (`//host/…`) y la
incrustada (`data:`). Sin ella, la portada volvería a pedirle la cara del autor a Google y la sección
volvería a depender del consentimiento de `maps` (`RGPD-05`, `SEC-01`) **sin romper nada visible**.
⚠️ **`Prunable` y NO `MassPrunable`**: la T2·4 cuelga de `pruning()` el borrado de los ficheros, y
borrar por consulta los dejaría huérfanos en el disco.
⚠️ **Ni una FK y ninguna relación de Eloquent, a propósito** (§4.3·11: prohibido cruzar las reseñas
con clientes o pedidos). Hay guarda con control negativo.
⚠️ **La media y el total NO se componen con las candidatas**: vienen de Google contados. Las tarjetas
se filtran por estrellas; la cifra, nunca (§4.3·10 y la Ómnibus 2019/2161).
⚠️ El resumen repite `maps_uri`/`new_review_uri`, que ya están en `google_business_connections`, para
que la portada **no toque la fila del token cifrado**.

### `google_business_review_suppressions` — lo que no se vuelve a publicar · `#731`
**La T2·5 de `specs/google-business-profile.md` §4.3·7**, que exigió la revisión de privacidad.
`review_hash` **char(64) UNIQUE** (`sha256` del nombre de recurso de la reseña) · `reason`
(`GoogleReviewSuppressionReason`: `author_request` · `minor` · `health_or_third_party` · `other`) ·
timestamps.
⚠️⚠️ **Es la ÚNICA tabla de la feature que NO caduca**, y por eso guarda **un hash y nada más**: en
la única tabla que no se limpia sola, cada columna de más es un dato de un tercero guardado para
siempre. Sin nombre, sin texto, sin foto, sin identificador legible.
⚠️⚠️ **Ocultar son DOS cosas**: borrar la fila —que se lleva sus ficheros (T2·4)— **y** apuntar el
hash. La reseña **sigue publicada en Google** y la pasada de mañana la traería; sin el apunte,
«ocultar» sería «ocultar hasta las 04:40». El apunte lo lee `GoogleReviewFilter::withSuppressed()`.
⚠️ **El motivo es TASADO y no texto libre**: un campo libre en una tabla que no caduca acaba con el
nombre de alguien dentro.
⚠️ **Sin FK a `users`**, a diferencia de la conexión: quién y cuándo los guarda `audit_logs`
(`google_business.review_hidden` / `.review_unhidden`), **con solo el hash y el motivo** — ese
registro sobrevive a la reseña que lo causó.
⚠️ **No toca la media ni el total** (§4.3·7): son de Google, contados sobre TODAS.
▶ **`RGPD-01`**: una petición de supresión de un cliente se comprueba también contra las reseñas, y
el procedimiento es éste.

### `settings` — clave-valor white-label (Setting)
`key` unique · `value` text · `group` (default `general`). Lectura vía
`Setting::value($key, $default)` con **memo estático por petición** (invalidar con
`Setting::flushMemo()`; los tests lo llaman en `setUp` porque el estático sobrevive al
rollback de `RefreshDatabase`). ~29 claves en uso: `business.*`, `contact.*`,
`redsys_*` (incl. contador `redsys_next_gateway_order`), `sales.hold_minutes`,
`sales.purchase_horizon_months`, `packs.max_per_slot`/`max_guests_per_slot`/`prep_blocks_cupo`
(globales, overrideables por zona), `theme.brand`, `payment.tax_rate`, `maintenance.*`,
`cookies.banner_enabled`, `security.turnstile_*`, `registration.*`, `reservations.*`,
`display_timezone`, `incidents.alert_email`, `reviews.min_stars` (`#728`: el mínimo de estrellas de
las tarjetas de la portada, por defecto **4**; se **acota a 1–5 al leerlo** —un 0 apagaría el filtro
sin decirlo y un 7 vaciaría la sección— y **no toca la media ni el total**, que vienen de Google
contados sobre TODAS), `puerta.waiver_check_enabled` (heredado de #216; hoy
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
