# Glosario — lenguaje ubicuo del dominio

> Estado: vivo · Última actualización: 2026-08-12 ·
> Verificado contra código: 2026-08-12 (cada artefacto comprobado por grep) ·
> Se invalida si: se renombra un modelo/clase de `app/Support` o se ejecuta la
> generalización de vocabulario (Fase 2) sin actualizar la fila.

Mapeo término ↔ artefacto real. Columna **Gen.**: ✔ ya genérico · 🏷️ vocabulario del
sector origen a generalizar · ❓ decidir en Fase 2. Los términos del doc en español
también viven en el código («aforo», «franja», «puerta»): grep funciona en ambos idiomas.

## Catálogo y aforo

| Término | Código / BD | Definición | Gen. |
|---|---|---|---|
| zona | `Zone` (tabla `zones`: `max_per_slot`, `prep_blocks_cupo`, `accent`, `color`) | Área del recinto con aforo y franjas propios; toda entrada da acceso a una zona (`ticket_types.zone_id`, null = todas). | ✔ (slugs seed `jump`/`kids` 🏷️) |
| atracción | `Attraction` (+ `ticket_type_id` nullable → complemento comprable) | Elemento informativo de la landing dentro de una zona; si su complemento no es comprable, la card degrada a informativa. | ❓ |
| franja / slot | `Slot` (única `zone_id+date+start_time`; `STATUS_OPEN/CLOSED/FULL`) · `SlotTemplate` · `SlotGenerator` · `slots:generate-rolling` | Unidad de venta de aforo fecha+hora+zona, materializada desde plantillas semanales sobre el horario efectivo. | ✔ |
| aforo vs cupo online | `slots.capacity` (total) vs `slots.online_capacity` (vendible web; resto = puerta) vs `slots.seats_taken` (cache display: «la verdad = pedidos») · `SlotAvailability` | Lo disponible = mínimo de plazas libres a lo largo de TODO el tramo de la visita (pagados + pendientes vivos + cesta). Supuesto del sector origen: cupo online ≠ aforo físico. | ✔ |
| hold / retención | `orders.expires_at` · `PaymentSettings::holdMinutes()` (setting `sales.hold_minutes`) · `orders:expire` | Ventana en la que un `pending` retiene plaza mientras se paga; `expires_at = null` = pedido firme. | ✔ |
| horizonte de venta | `PaymentSettings::purchaseHorizonMonths()` (setting `sales.purchase_horizon_months`) | Meses hacia delante comprables; también horizonte del generador de franjas. | ✔ |
| horario | `OpeningHour` (semanal) · `Season` · `SpecialDate` · `OperatingSchedule::effectiveFor()` | Ventana efectiva del día; prioridad: día especial > temporada > semanal. Fuente única de generador, compra y panel. | ✔ (nombre «Park» 🏷️) |
| tarifa | `RateType` (`normal`/`special`; `normal` = fallback imborrable) · `RateResolver` | Tipo de precio del día: día especial > weekday > normal. | ✔ |
| precio | `Price` (matriz producto×tarifa, `amount_cents`) · `Money` | Céntimos, fuente única (sin `price_cents` en el producto). | ✔ |
| ventana de producto | `ProductAvailability` · `ticket_types.available_after_open_min`/`available_before_close_min` · `TicketType::meetsMinAdvance()` | Restricción horaria y antelación mínima de un producto dentro del horario del día. | ✔ |

## Productos y compra

| Término | Código / BD | Definición | Gen. |
|---|---|---|---|
| entrada | `TicketType::TYPE_ENTRY` | Admisión por zona + duración (`duration_min`, null = ilimitada) que consume aforo. | ✔ |
| pack / cumpleaños | `TicketType::TYPE_PACK` (`min_qty`/`max_qty`, `prep_*_min`, `event_fields`, `guest_fields`) · `PackAvailability` (pool PROPIO, settings `packs.*` con override por zona) | Producto de evento con invitados, señal opcional, extras y buffers de montaje/limpieza. | mecanismo ✔ · copys 🏷️ |
| complemento / addon | `TicketType::TYPE_ADDON` · pivote `ProductAddon` (`is_included`, `quantity_mode`, `choice_group`, `requires_addon_id`) · `AddonResolver` (autoridad de servidor) | Producto accesorio sin aforo enganchado a un base; config POR ENGANCHE (gratis aquí, de pago allá). | ✔ |
| carrito / cesta | `Cart::sanitize()` (contrato único; sesión: solo IDs/cantidades) | Estructura de sesión saneada; precio y aforo se validan SIEMPRE en servidor. | ✔ |
| pedido | `Order` (estados `PENDING/PAID/CANCELLED/EXPIRED` + `refunded` LEGADO deprecated, solo datos históricos; el reembolso vigente es dimensión `refunded_at`, no estado) · `OrderItem` (estado operativo calculado) | Cesta confirmada: nace `pending` con retención y pasa a `paid` con Redsys, que emite tickets. Código público con prefijo configurable (`sales.order_prefix`, default `R-`). | ✔ |
| pedido manual | `ManualOrderFulfiller` + página `CreateManualOrderPage` | Alta back-office (efectivo/datáfono), pedido firme sin pasarela, reutiliza `OrderCreator`+`TicketIssuer`. | ✔ |
| ajuste / movimiento | `OrderAdjustment` (`deposit_split`, `edit`, `mixed`, `courtesy` — T1 del libro, `specs/desglose-libro.md` §4.2) | Un HECHO de dinero de una línea: el reparto de la señal al nacer, el delta entero de una gestión (con signo), la línea viva de fiesta mixta o una cortesía al reembolsar. Ortogonal al reembolso (`payment_refunds`). | ✔ |
| señal / depósito | `ticket_types.deposit_type/value` · `TicketType::depositCents()` · reparto = `OrderAdjustment` `deposit_split` | Pago parcial online por producto (% o fijo); el resto se cobra en puerta. Detalle: `sistemas/DEPOSITO.md`. | ✔ |
| pago | `Payment` (morph `payable`; `superseded` = intento descartado) · driver `Redsys` + `RedsysReturnHandler` | Intento de cobro vía pasarela; jamás se guardan tarjetas. | ✔ |
| reembolso | `PaymentRefund` (por `payment_id`; `order_item_id` en parciales) + `orders.refunded_at`/`refund_amount_cents` | Evento de devolución independiente del estado del pedido. | ✔ |
| ticket / QR | `Ticket` (`qr_token`; solo `STATUS_PURCHASED` — el ciclo redeemed/void se retiró) · `TicketIssuer` (idempotente) · `QrCode` | Admisión individual emitida al pagar; un ticket por plaza de ítems CON franja. | ✔ |
| oferta de franjas | `SlotOffer` | Fuente ÚNICA de fechas/horas ofrecibles, idéntica para web y panel (invariante AFORO-02). | ✔ |

## Personas y operación

| Término | Código / BD | Definición | Gen. |
|---|---|---|---|
| invitado / post-form | `ticket_types.guest_fields` (esquema) · `order_items.guest_data` · `GuestFormController` (URL firmada, caduca evento+14 días) | Formulario POR RESERVA post-compra con datos de cada asistente (menores: RGPD art. 9). Detalle: `sistemas/POSTFORM-INVITADOS.md`. | mecanismo ✔ · copys 🏷️ |
| fiesta mixta / familia por edad / sello de condiciones | `ticket_types.guest_age_family` + `guest_age_min/max` (la familia y el tramo) · `order_items.age_family_seal` (el SELLO: familia, tramos y precios con los que se vendió; VO `AgeFamilySeal`/`SealedRegime`, escritor `AgeFamilySealer`) · `GuestAgeMixReader` → `GuestAgeMix` (el veredicto, derivado del sello) · `MixedPartySurcharge` (el suplemento, una LÍNEA hija + `extra_due`; y desde la T4 el **descuento**, su ESPEJO: línea `is_credit` + `extra_due` negativo, acotado al dinero de puerta — el exceso es el «a tu favor», `inFavourCents`/`in_favour_hint`) | Una fiesta cuyos invitados declaran edades de otro tramo de la misma familia. El suplemento —y el descuento— salen de las condiciones SELLADAS al nacer la reserva, no del catálogo vivo: pack nuevo → sello nuevo; día nuevo → el mismo sello re-preciado (`PAY-19`). Detalle: `specs/cumple-mixto.md` §18, §21, §20 y §24. | ✔ |
| waiver | `users.waiver_accepted_at` (sello) · Page slug `waiver` (el borrador editable) · **Fase 6**: `legal_document_versions` (versión publicada, inmutable) · `waiver_signatures` (la PRUEBA: append-only, cadena de hashes por titular) · settings `waiver.mode` (`externo`·`interno`·`desactivado`; hereda `puerta.waiver_check_enabled`) y `waiver.retention_months` · `registration.*` (sistema EXTERNO) | Exención de responsabilidad. En modo `externo` se gestiona FUERA y aquí solo se consulta el sello; en `interno` se firma aquí y queda registro probatorio que sobrevive a la baja (`specs/waiver-probatorio.md`). | mecanismo ✔ · copys 🏷️ |
| puerta | `Livewire/Admin/Puerta/ValidarRegistro` (permiso `registrations.validate`; rate-limit auditado) | Operativa presencial: buscar por email/teléfono y devolver SOLO el estado del waiver (privacy-by-design). | 🏷️ |
| pulsera | `ticket_types.wristband_color` (columna informativa, poblada por seeders, SIN lógica de canje) · `zones.color` se usa como color de pulsera simbólico en panel y hoja de reserva (`ReservationSlip`) | Control de acceso físico del sector origen (fabricante externo); el sistema solo informa el color — el canje vive fuera. | 🏷️ |
| incidencia | `AuditLog::CRITICAL_ACTIONS` (`duplicate_capture`, `overbooked_capture`, `refund_failed`…) · `IncidentSettings::alertEmail()` | Acción crítica registrada append-only, destacada en el panel y avisada por email best-effort. | ✔ |
| auditoría | `AuditLog` (append-only, sin `updated_at`) · `AuditLogger::logSensitive()` (sha256, sin PII) | Registro inmutable de acciones sensibles del panel. | ✔ |
| consentimiento | `Consent` (legales versionados) · `CookieConsentLog` · `CookieConsent` | Rastro RGPD de aceptación; se re-pide al subir versión del texto. | ✔ |

## Plataforma y presentación

| Término | Código / BD | Definición | Gen. |
|---|---|---|---|
| instalación | Sin artefacto — decisión `DECISIONES #2` (sin `tenant_id` en ninguna tabla) | Unidad white-label: un cliente = una BD + un dominio + un `.env`. | ✔ |
| ajustes / settings | `Setting` (key/value/`group`; memo por petición) · página Filament `Settings` (permiso `settings.manage`) | Configuración data-driven de toda la instalación, leída con helpers defensivos. | ✔ |
| tema | `ThemeSettings` (setting `theme.brand`; `zones.color` por zona; `cssRootDeclarations()`) | Color de marca + color por zona tematizando web, panel y emails; validación hex defensiva. | mecanismo ✔ · tokens `--jump-1`/`--kids-1` 🏷️ |
| sidebar | `Livewire/Tickets/Purchase` (wizard; muere con la SPA en Fase 4) | Flujo de compra/reserva embebido sobre la página actual sin navegar. | ✔ |
| landing service | `LandingService` (`ticket_type_id` nullable; `isPurchasable()` delega en el pack EN VIVO) | Sección editorial de `/servicios`; su existencia reclasifica el pack fuera de Cumpleaños. Detalle: `sistemas/SERVICIOS-CMS.md`. | ✔ |
| oferta | `Offer` (i18n + imagen `uploads`; `scopeActive`) | Promoción INFORMATIVA (sin dinero) del widget flotante. Detalle: `sistemas/OFERTAS-WIDGET.md`. | ✔ |
| mantenimiento | `MaintenanceSettings` (site/página/reservas; fail-safe opt-in) | Apagado selectivo; un setting roto nunca tira la web. | ✔ |
| zona horaria de display | `DisplayTime` (setting `display_timezone`; BD en UTC) | TZ de presentación; NUNCA usar `Carbon::today()` crudo en venta (invariante AFORO-09). | ✔ |
| sala / mesa | `Room` (tabla `rooms`; sin lógica de aforo conectada) | Recurso físico para packs, previsto y SIN uso — ver `DEUDA.md`. | ❓ |
| normas | `VenueRule` (CMS i18n) | Lista editable de normas del recinto en la landing. | ✔ (nombre 🏷️) |
