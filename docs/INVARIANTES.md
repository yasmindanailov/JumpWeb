# INVARIANTES de no-regresión — endurecimiento heredado

> Adaptado del proyecto origen (2026-08-12). Describe la BASE HEREDADA: el refactor
> (`00-REFACTOR.md`) puede haberla cambiado. Verifica contra el código antes de construir
> encima (CONVENCIONES §Verificación).

Destilado de las auditorías por sistema del origen (6 sistemas, hallazgos verificados
adversarialmente y reproducidos con tinker/curl/fork) + el endurecimiento pre-go-live.
**Cada invariante costó un bug real o cerró uno latente: NINGÚN agente debe deshacerlos.**
Formato: enunciado imperativo · dónde vive · cómo se verifica. Las referencias `fichero:línea`
son de la fecha de auditoría del origen y pueden haber derivado — la clase/método es lo estable.

Vocabulario: «franjas» (slots), «packs/cumpleaños», «puerta», «waiver», «zonas» = vocabulario
del sector origen; su generalización se decide en `00-REFACTOR.md` Fase 1/2. El código heredado
aún se llama así.

---

## 1 · Dinero / pagos (Redsys)

| Invariante (NO deshacer) | Código | Verificación |
|---|---|---|
| **`RedsysReturnHandler` es el ÚNICO autorizado a pasar una `Order` a `paid`.** Verifica la firma con `hash_equals` ANTES de tocar BD; canarios `Ds_Amount`/`Ds_Currency` contra el importe esperado; idempotencia por-`Payment` de PAID y FAILED. | `app/Services/.../RedsysReturnHandler.php` | `RedsysReturnHandlerTest`; concurrencia real: `php artisan redsys:verify-concurrency --workers=16` (fork `pcntl` sobre MySQL → 1 cobro, 1 ticket, 1 `authorized` + N-1 `idempotent_paid`) |
| **Guarda `$canFulfil = status===PENDING && !isExpiredInPractice()`:** una notificación autorizada sobre una Order **no viva** (PAID / CANCELLED / REFUNDED / expirada en la práctica) captura el pago como INCIDENCIA — **sin** emitir tickets, **sin** reenviar confirmación, **sin** re-transicionar estado (una CANCELLED sigue CANCELLED). Evita doble cobro con tickets duplicados y resurrección de pedidos cancelados (sobreventa de plaza liberada). | `RedsysReturnHandler` (guarda tras el lock de la Order); `Order::isExpiredInPractice()` | Tests con DOS Payments sobre la misma Order (`RedsysReturnHandlerTest`) + `redsys:verify-concurrency` |
| **`TicketIssuer` es idempotente:** no-op si la Order ya tiene tickets (cinturón del anterior). | `TicketIssuer` | Cubierto por los tests de doble-Payment |
| **Al reintentar un pago, los Payments pending previos pasan a `STATUS_SUPERSEDED`** (estado terminal propio, no `failed`). La extensión de `expires_at` del reintento es un **UPDATE atómico condicionado** (no check+save separados). | `Payment::STATUS_SUPERSEDED`; `RetryPaymentController` y `Purchase::retryPayment` | Tests del reintento |
| **Las incidencias de cobro son VISIBLES, no solo log:** `duplicate_or_dead_capture` y `overbooked_capture` se registran en `audit_logs` vía `AuditLogger::logSystem` (**sin PII**, sin IP/UA del cliente) + email al operador (`PaymentIncidentMail` → setting `incidents.alert_email`, fallback al email de contacto) + página «Incidencias» del panel (`AuditLogResource`, solo lectura, permiso `audit.view`). | `RedsysReturnHandler` + `AuditLogger::logSystem` + `AuditLogResource` | Tests de incidencias |
| **La clave secreta de Redsys vive en `config/services.php` (`redsys.secret_key` = `env(...)`), NUNCA `env()` en código de runtime** — `env()` devuelve `null` tras `config:cache` y caería a la clave sandbox pública (apagón de cobros en despliegue). `Redsys::config()` lee `config(...)`. | `config/services.php` + `Redsys::config()` | Smoke-test de firma POST-`config:cache` en el runbook de deploy |
| **Guarda de go-live:** el panel bloquea `redsys_environment=live` si la clave secreta efectiva (`Redsys::config()['secret_key']`) no mide **32 chars** o sigue siendo `Redsys::SANDBOX_SECRET_KEY`. La clave real va al vault del hosting, nunca a BD ni repo. | Guarda en el save de la página Settings | `SettingsPageTest` (2 bloqueos + 1 permite con clave válida) |
| **La respuesta REST de devolución exige firma:** `Ds_Signature` ausente o inválida → malformed (el `!== ''` que cortocircuitaba la verificación se eliminó). | `Redsys::executeRefund` | Tests de refund + `php artisan redsys:verify-sandbox` (firma `HMAC_SHA512_V2` contra sandbox real, control A/B de clave) |
| **La capacidad de reembolso cuenta también los reembolsos PENDING** (un total en vuelo reserva capacidad → un parcial concurrente no puede devolver más de lo cobrado) **y hay tope per-item** (`itemRefundableRemainderCents`) bajo lock. | `Order::refundableCapacityCents()` / `itemRefundableRemainderCents()` | `DepositRefundCoherenceTest` y tests de refund total-pending-vs-parcial |
| **Señal (depósito) gateada por `hasDeposit()`:** valor 0 → se cobra el total (nunca `DS_MERCHANT_AMOUNT=0`); backstop `amount>0` en la ida a Redsys; el panel exige `deposit_value>=1` si el tipo ≠ none. Un complemento cobrado íntegro en puerta bajo un principal con señal tiene online original = **0** en `itemOriginalOnlineCents` (no el `depositCents` del addon → sin «pendiente de devolución» fantasma ni techo de reembolso inflado). | `TicketType::depositCents()`/`hasDeposit()`; `OrderCreator`; `Order::itemOriginalOnlineCents` | `DepositRefundCoherenceTest` |
| **`orders:expire` es un UPDATE atómico CAS** (`WHERE status=PENDING AND expires_at<now()`); solo notifica si afectó 1 fila. Nunca pisa a EXPIRED una Order que el handler acaba de marcar PAID. | `ExpireOrders` | Test que reproduce la carrera expire-vs-captura |
| **El precio se calcula SIEMPRE en servidor** y el cap de líneas del carrito es invariante de servidor (`cart_too_large` en `OrderCreator`), no solo de UI. `#[Locked]` en la cesta Livewire se descartó a propósito: la cesta es client-syncable y `OrderCreator` re-valida cada línea. | `OrderCreator` | Tests de `OrderCreator` |
| **Backstop intra-día en el checkout:** `OrderCreator` rechaza una franja de HOY cuya hora ya pasó (`too_late_line`), factorizado en `SlotOffer::passesIntradayFloor()` — oferta y checkout usan la MISMA fuente y no pueden divergir. Ídem la antelación mínima: enforce único en `TicketType::meetsMinAdvance()` consumido por `SlotOffer` y `OrderCreator`. | `SlotOffer::passesIntradayFloor()`; `TicketType::meetsMinAdvance()` | `SlotOfferTest` + `OrderCreatorTest` |
| **Emails en COLA:** todas las notificaciones/mailables (18 en el origen) son `ShouldQueue` — el SMTP no bloquea al cliente; el worker corre vía el `schedule:run` del cron (`queue:work --stop-when-empty`, sin servicio nuevo); **`after_commit=true`** (nada se encola antes del commit de la txn). Cualquier Mailable/Notification NUEVA debe ser `ShouldQueue`. | Notificaciones/Mailables + config de cola | Suite (Mail/Notification fakes) |
| **Rutas `/pago/redsys/*` con `throttle:120,1`.** | `routes/web.php` | Test de rutas |

---

## 2 · Aforo / concurrencia

| Invariante (NO deshacer) | Código | Verificación |
|---|---|---|
| **⚠️ EL MÁS SUTIL — lock de franjas con `zone_id` LITERAL, resuelto FUERA de la transacción, como PRIMERA sentencia de la txn.** Bajo REPEATABLE READ, cualquier SELECT previo (incluida una **subconsulta** dentro del propio `FOR UPDATE`) fija el snapshot ANTES del lock → el recuento de aforo lee datos pre-commit del rival → **SOBREVENTA REAL** (reproducida con fork en el origen: N compras de la última plaza, todas con éxito). El fix «lock primero» con subconsulta fue INSUFICIENTE; solo el literal es correcto. No reintroducir subconsultas ni SELECTs antes de `lockSlots()`. | `OrderCreator::lockSlots()` (primera sentencia de la txn; zonas resueltas antes de abrirla) | `php artisan purchase:verify-oversell --workers=16` (fork real sobre MySQL → 1 compra + N-1 `sold_out`, asientos == aforo) + regresión por query log. **Correr tras tocar `OrderCreator::lockSlots` o el aforo.** |
| **Fuente ÚNICA de oferta web↔panel:** fechas/horas ofrecibles salen SOLO de `SlotOffer` (consumido por la compra pública y por el pedido manual del panel) → no pueden divergir (el panel aplicaba antes su propia aritmética y ofrecía días sin franja u horas cerradas en vivo). | `app/Support/SlotOffer.php` | `SlotOfferTest` (paridad web↔panel) |
| **Las franjas se regeneran solas:** scheduler diario de `slots:generate-rolling` (`withoutOverlapping`) — sin él el calendario «se agota». El seed solo crea ~2 semanas; el comando puebla el horizonte en deploy y el cron lo mantiene. | `routes/console.php` + `SlotGenerator::generateRollingHorizon()` | `SlotGeneratorTest` |
| **La poda de franjas re-verifica dependientes DENTRO de la txn bajo `lockForUpdate`** (si apareció una venta en vuelo → cerrar la franja, no borrarla; `order_items.slot_id` es cascade y el hard-delete destruiría la venta). | `SlotGenerator::pruneDay` | Test TOCTOU de poda |
| **Las ediciones de ítem del panel bloquean toda la zona/día** (`lockZoneDaySlots()`, mismo alcance que `OrderCreator::lockSlots`), no solo la franja destino — dos ediciones con tramos solapados no sobrellenan una franja intermedia. | `ViewOrder` → `lockZoneDaySlots()` | Tests de edición concurrente |
| **Re-agendar un pack pasa `excludeItemId`** a la revalidación de cupo (no se cuenta a sí mismo → sin auto-bloqueo, paridad entre las dos rutas del modal Gestionar). | `ViewOrder::executeItemSlotChange` | `ManageItemSlotChangeTest` |
| **`seats_per_unit` forzado a 1 para packs** (guard en create/edit del catálogo + migración normalizadora) — el checkout valida el cupo en unidades y lo consume en plazas; con `spu>1` sobrevendería. | Guardado del catálogo | `CatalogCreateTest` |
| **Las ventanas de disponibilidad clampan a `[00:00:00, 24:00:00)`** cuando la aritmética cruza medianoche (sin fail-open de cupo ni fail-closed de venta). | `PackAvailability::window()` y `SlotAvailability::spanEnd()` | `PackAvailabilityTest` |
| **«Hoy operativo» usa `DisplayTime::today()/now()`** (timezone de display), NUNCA `Carbon::today()` crudo en el subsistema de venta, y NUNCA cambiar `config('app.timezone')` global (la BD persiste en UTC). | `DisplayTime` | Tests de cortes de medianoche |
| **El pedido pendiente nace auto-liberable:** el hold se pasa a `createPendingOrder` (no en un `save` posterior) — un crash no deja retención de aforo perpetua. | `Purchase::confirmReservation` → `createPendingOrder` | Tests de creación |

---

## 3 · RGPD / PII

Contexto heredado: los pedidos guardan datos de **menores** (nombres + **alergias** = dato de
salud, art. 9 RGPD) en `order_items.guest_data`/`event_data`.

| Invariante (NO deshacer) | Código | Verificación |
|---|---|---|
| **`User::anonymize()` es la purga CENTRAL y completa (art. 17).** En su txn: (1) nulifica `guest_data`/`event_data` de TODOS los `order_items` del titular (incl. cancelados; conserva importes/fechas); (2) purga las `sessions` activas (ambas vías: self-service y panel); (3) borra el token de `password_reset_tokens` con el email original; (4) **redacta** los payloads legacy de `audit_logs` del titular (`event_data_updated`, `orders.email_resent`); (5) nulifica **IP/user-agent** de sus filas de `audit_logs`. Cualquier PII nueva que se persista debe añadirse aquí. | `User::anonymize()` | `PrivacyTest` |
| **`audit_logs` NUNCA lleva PII en claro:** email del actor en sha256 (`AuditLogger::logSensitive`); los diffs de `event_data` registran solo las **CLAVES** cambiadas, jamás valores (nombre del menor); el payload de reenvío de email no incluye el email; `logSystem` omite IP/UA del cliente. | `AuditLogger`; `ViewOrder` (eventDataDiffKeys) | `OrderItemUpdateEventDataTest`, `OrderAdminActionsTest`, `PrivacyTest` |
| **El enlace del post-form de invitados CADUCA** (`temporarySignedRoute`, expiración = fecha del evento + 14 días, fuente única `Order::guestFormLinkExpiresAt`) y **devuelve 410 si el titular está anonimizado** (GET y POST — la supresión cierra el canal de re-introducción de PII). La URL firmada no lleva PII. | `GuestFormRequest` (notificación); `GuestFormController::authorizeAccess` | `GuestFormTest` |
| **`Cache-Control: no-store` en toda superficie NO-Livewire con PII:** los 2 PDF operativos (hoja de reserva, resumen del día — llevan alergias de menores), la página pública del post-form (middleware `NoStore`), el export RGPD de «Mi cuenta» y el feed JSON del calendario del panel (alias `no-store` en ruta). Las páginas Livewire/Filament NO lo necesitan: Livewire ya estampa `no-store` globalmente (verificado empíricamente en el origen — no añadirlo redundante, pero no confiar en él para controladores planos). | Middleware `NoStore` + alias en `routes/web.php` | Asserts de cabecera en `PrivacyTest`, `CalendarEventsTest` y tests de los PDF |
| **El consentimiento de cookies es atómico:** `decided=true` y el evento que inyecta el `src` de los iframes de terceros solo se disparan si el POST de persistencia devolvió `res.ok` (la fila-prueba del art. 7.1 existe ⇔ el iframe cargó). El bloqueo previo server-side (iframe con `data-src`, no `src`, hasta consentir) se mantiene. | `resources/js/app.js` (`cookies.persist()`); `CookieConsentController` | `CookieGateBlockingTest` (bloqueo previo); el camino JS se verificó por revisión + build |

---

## 4 · Seguridad web

| Invariante (NO deshacer) | Código | Verificación |
|---|---|---|
| **`SecurityHeaders` cubre TAMBIÉN el panel admin** (está en el stack del `AdminPanelProvider`, no solo en el grupo `web`) — sin él, la superficie que gestiona PII/reembolsos/roles queda sin cabeceras ni CSP. | `AdminPanelProvider` (middleware) + `bootstrap/app.php` | Test de cabeceras sobre `/admin/login` |
| **El kill-switch de mantenimiento NUNCA bloquea el login del panel:** `EnsureSiteAvailable::isExcluded()` excluye el endpoint Livewire por **NOMBRE de ruta** (`*livewire.update` — el path lleva hash aleatorio; el form de login de Filament POSTea ahí, no a `/admin/*`). Sin esto: auto-lockout del admin con mantenimiento ON, recuperable solo por BD/CLI. No reabre la web pública (sus GET ya dan 503). | `EnsureSiteAvailable::isExcluded()` | `SiteMaintenanceTest` — el test hace el **POST real** al endpoint Livewire (un GET enmascararía la regresión) |
| **El pivote de permisos re-filtra SERVER-SIDE al persistir** (`array_intersect` con `PermissionCatalog::assignable()`, por grupo) — `access.manage` y nombres inventados nunca se cuelan aunque el form se manipule. Anti-lockout del último admin bajo `DB::transaction`+`lockForUpdate`. | `InteractsWithRoleForm::extractPermissionMatrix` | Tests de roles |
| **Cada acción sensible re-autoriza en el MOMENTO de ejecutarla, no solo en `mount()`** (la revocación en vivo surte efecto): p. ej. la puerta re-valida el permiso en `search()`/`clear()`; la rama «también cancelar» del reembolso re-exige `orders.cancel` en el handler. | `ValidarRegistro::authorizeAccess()`; `ViewOrder` (refund) | `ValidarRegistroTest`, `OrderAdminActionsTest` |
| **El rechazo por rate-limit de la búsqueda en puerta SE AUDITA** (1.ª vez por ventana, sin PII) — una enumeración masiva deja rastro. | `ValidarRegistro::search()` | `ValidarRegistroTest::test_rate_limit_rejection_is_audited_once_per_window` |
| **Anti-bot/anti-enumeración en auth:** login con 2.º `RateLimiter` SOLO por IP (además de la clave `email\|ip`, que sola no frena stuffing distribuido); reset con mensaje genérico único (no distingue email inexistente de token inválido) + rate-limit por IP; `/contacto` con `throttle:5,1` + Turnstile (no-op sin claves). | `Login`, `ResetPassword`, `ContactController` | Tests de auth |
| **Ninguna URL externa editable llega a un `href`/`content` sin `safeExternalUrl`** (maps, redes sociales, registration_url, og_image) — defensa de render uniforme, no depender solo del `->url()` del form. Los embeds pasan por allowlist de host (`MapsEmbed`/`SocialEmbed::clean`); el color de tema por `ThemeSettings::hex` (`/^#[0-9a-fA-F]{6}$/` — es el sink de máximo valor: se pinta en `<style>:root{}`). El render público escapa con `{{ }}`; los `{!! !!}` existentes son i18n del desarrollador pre-escapados — no añadir ninguno nuevo con contenido CMS. | `AppServiceProvider::safeExternalUrl`; `MapsEmbed`/`SocialEmbed`; `ThemeSettings::hex` | `SeoTest`, `Detalles216Test` y tests de embeds |
| **`lang.switch` no hace `back()` ciego:** solo redirige al `Referer` si es del MISMO host (o ruta relativa); si no, a la home (open-redirect por Referer reproducido en el origen). Los tests deben aseverar el DESTINO del redirect, no solo que redirige. | Ruta `lang.switch` en `routes/web.php` | `LocaleDetectionTest` |
| **Las páginas legales no pueden desactivarse desde el panel** (`Page::PROTECTED_ACTIVE_SLUGS`; el form fuerza `is_active=true`) — sitemap/footer/enlaces de consentimiento del registro nunca apuntan a un 404. | `Page::PROTECTED_ACTIVE_SLUGS` + `InteractsWithPageForm` | `PageResourceTest` |
| **Allowlists `$fillable` en los modelos de dinero/autorización** (Order, Payment, Role, Permission, Setting) + `preventSilentlyDiscardingAttributes` en no-prod (la suite verde con el guard activo prueba que los allowlists están completos). No volver a `$guarded=[]` en estos modelos. | Modelos citados + `AppServiceProvider` | La propia suite con el guard estricto |
| **Los secretos NO son legibles ni editables desde el panel** (la página Settings los excluye). | Página Settings | Tests de Settings |

---

## 5 · Rendimiento

| Invariante (NO deshacer) | Código | Verificación |
|---|---|---|
| **`Setting::value()` memoiza POR PETICIÓN** (`self::$memo`, invalidado en `saved`/`deleted`): las múltiples lecturas de un request (CSP, tema, flags) colapsan en 1 query. ⚠️ Acarrea el invariante de suite: `flushMemo()` en `TestCase::setUp()` (ver §6). | `app/Models/Setting.php` | Suite + presupuesto de queries (abajo) |
| **El composer global de vistas memoiza su payload por petición y lee `settings` con UN `pluck` por request.** Antecedente: `View::composer('*')` corría ~79 veces por render de la home reconstruyendo ~20 `Setting::value()` cada vez → **~1.900 queries / ~4 s de BD por CADA GET anónimo** de la ruta de mayor tráfico y sin throttle (DoS por amplificación, reproducido). Con el memo: ~50 queries. | `AppServiceProvider::sharedViewData()/buildSharedViewData()` (memo en `request()->attributes` — ámbito por petición, sin fugas entre tests) | **Test-presupuesto** `HomePageTest::test_anonymous_home_get_stays_within_query_budget` (< 120 queries totales, < 50 lecturas de `settings`) — si un cambio lo rompe, ES una regresión de este invariante |
| **Los guards `Schema::hasTable` del arranque solo se consultan FUERA de producción** (−4 queries a `information_schema` por request en el hot path). | `AppServiceProvider::tableExists()` | Suite |
| **Sin N+1 en el listado de servicios:** `isSellablePackForLanding()` reutiliza la relación `prices` ya eager-loaded (0 queries); solo cae al `EXISTS` puntual si no está cargada. | `TicketType::hasPositivePrice()` | Suite |
| **La caché del «desde X €» (`cta.min_price_cents`) se invalida SIEMPRE que cambie la comprabilidad**, no solo el precio: toggle `is_active`/`is_sellable`, borrado del producto, cambio de `zones.is_active`. Y su query (como las de cards de la landing) aplica `->inOperationalZone()` — mismo predicado que el catálogo de compra: la landing nunca anuncia/oferta lo que el checkout no vende. | `EditCatalog::afterSave`, `deleteProductAction`, `EditZone::afterSave`; `ctaMinPriceCents()`; `HomeController`/`PricingController` | `HomePageTest`, `CatalogEditTest`, `ZoneResourceTest` |
| **Los scripts de Vite/Livewire son módulos ES (`type="module"`):** cualquier optimizador proxy/CDN que reescriba scripts (p. ej. Rocket Loader) los mutila. Mantener esos reescritores desactivados en cualquier despliegue. | — (gotcha de infraestructura) | Comparar el HTML del origen vs el servido por el proxy |
| **Anti-patrón documentado:** en el origen, un «tarda 10 s la 1.ª carga» se demostró **stall DNS del lado cliente** (origen caliente, CrUX aprobado). No añadir keep-warm de OPcache ni tuning especulativo de servidor sin medir primero (curl con desglose DNS/TTFB, origen directo vs proxy). | — | Metodología: medir antes de tocar |

---

## 6 · Suite de tests

| Invariante (NO deshacer) | Código | Verificación |
|---|---|---|
| **`Http::preventStrayRequests()` activo en `setUp()`:** ningún test puede hacer red real. Toda salida externa se simula: Redsys → `Http::fake([Redsys::REST_URL_TEST => ...])`; Turnstile → sin claves o fake; HIBP → sustituir `UncompromisedVerifier` en el contenedor. | `tests/TestCase.php` | La suite completa pasa con la guarda activa |
| **`Setting::flushMemo()` en `TestCase::setUp()`:** el rollback de `RefreshDatabase` NO dispara eventos `saved`/`deleted`, así que sin el flush el memo estático de `Setting` contamina los tests siguientes del mismo proceso (en el origen: 31 fallos fantasma que no eran de lógica). Si se añade OTRO memo estático, necesita su flush aquí. | `tests/TestCase.php::setUp()` | Suite completa verde en secuencial Y paralelo |
| **Tiempo determinista:** `travel()`/`travelTo()`, nunca `sleep()`. | Tests | — |
| **La suite corre en SQLite `:memory:` → NO reproduce los locks de InnoDB.** Las carreras de concurrencia (doble-cobro, sobreventa) NO se ejercitan en CI: existen comandos on-demand dev-only con `pcntl_fork` sobre MySQL. **Obligatorio correrlos tras tocar el código que cubren:** `redsys:verify-concurrency` (tras tocar `RedsysReturnHandler`/locks de pago) · `purchase:verify-oversell` (tras tocar `OrderCreator::lockSlots`/aforo) · `redsys:verify-sandbox` (refund REST contra sandbox real). | Comandos artisan (gateados a no-producción; limpian lo que crean) | Los propios comandos (incluyen control negativo) |
| **Paralelo:** `php artisan test --parallel` (paratest, BD `:memory:` por proceso; ~6,8× más rápido en el origen). Resultado idéntico a secuencial es parte del contrato de aislamiento. `npm run build` una vez antes de la suite completa (vistas → `ViteManifestNotFound` sin manifest). | `phpunit.xml` + `brianium/paratest` | Comparar recuento secuencial vs paralelo |
| **Entorno de test:** `CACHE_STORE=array`, `SESSION_DRIVER=array`, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=array`, `BCRYPT_ROUNDS=4`. Detalle en `TESTING.md`. | `phpunit.xml` | — |

---

## Cómo usar este documento

- Antes de tocar dinero, aforo, PII o el composer global: relee el grupo correspondiente.
- Si un cambio legítimo del refactor NECESITA alterar un invariante: registra la decisión en
  `DECISIONES.md`, actualiza aquí el enunciado y asegúrate de que la verificación equivalente
  (test o comando) sigue existiendo. Un invariante sin verificación es una regresión en espera.
- Los nombres de clase/tabla citados son de la base heredada; si el refactor los renombra,
  el invariante VIAJA con el código renombrado (actualiza la referencia, no borres la fila).
