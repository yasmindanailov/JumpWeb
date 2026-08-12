# Estado del proyecto — foto viva

> Documento corto (carga obligatoria al arrancar). Solo «dónde estamos / qué sigue».
> Última actualización: **2026-08-12**.

## ▶ Dónde estamos
**Fase 0 ✅ · Fase 1 ✅ · Fase 2 (modularización) 🟦 — MUDANZA COMPLETA (pasos 1–6). `app/Models` y `app/Support` ya no existen. Queda el paso 7 (cierre).**
- Suite **2190 en verde** (8188 aserciones, `--parallel` ~1m11s) · Pint limpio · `docs-check`
  verde · `redsys:verify-concurrency` y `purchase:verify-oversell` EN VERDE sobre MySQL real.
  La corrida SECUENCIAL completa se verificó en el paso 2 (2157/2157 en 557s): los dos modos
  dan lo mismo. El contador «PHPUnit Notices: 1» sale solo en la paralela completa y es del
  runner, no del código (ver `TESTING.md`).
- **Fase 2 — hecho** (detalle en el tracker):
  1. **morphMap FORZADO** (`AppServiceProvider`, alias para los 30 modelos) + migración
     `convert_morph_types_to_aliases` verificada en MySQL dev (0 FQCN restantes) + barrido
     `::class`→`getMorphClass()` en app y tests + `MorphMapTest` (5 guardas).
  2. **Spec de módulos APROBADO** con revisión multi-agente: `docs/specs/modulos-dominio.md`
     (`DECISIONES #13`) — layout `app/Domain/<Contexto>/`, contratos en namespace de destino,
     orden contratos→Platform→Content→Identity→Payments→Booking (dinero al final), checklist
     mecánico por paso (blades FQCN, migraciones Legacy*, colas, docs).
  3. **Cimientos de gates**: `VERIFY_CONC` del pre-push por basename; `docs-check` y
     `MorphMapTest` cuentan modelos también en `app/Domain/*/Models`.
  4. **PASO 1 — contratos en destino** (`DECISIONES #14`, spec §4.bis). Existe
     `app/Domain/{Booking,Payments}/` con las **tres costuras reales** (medidas contra el
     código, no supuestas):
     - Booking→Payments: `RefundGateway` + `RefundResult` — `Order` ya **no** importa `Redsys`.
       Solo REEMBOLSO: el cobro no lo consume Booking, sino la capa de entrega.
     - Identity→Booking: `CustomerReservations` + `UpcomingReservation`/`PendingGuestForm` —
       `CustomerAccountContext` ya **no** consulta `Order`/`OrderItem`/`TicketType`.
     - Content→Booking: `PublishableCatalog` + `ComplementPlacement` — la regla #226, que
       estaba DUPLICADA en dos consultas SQL que podían divergir, ahora es una sola.
     Implementaciones legacy bindeadas en `Booking/PaymentsServiceProvider` (registrados en
     `bootstrap/providers.php`); `CustomerReservationsReader` y `PublishableCatalogReader` son
     de Booking y se quedan en `app/Support` hasta el paso 6.
     Frontera EJECUTABLE: `ModuleBoundariesTest` (grafo · baselines `SEAM`/`LEGACY` que **solo
     encogen** · «desde fuera solo se tocan `Contracts`»; las 3 guardas verificadas por
     MUTACIÓN) + `ModuleContractsTest` (cada contrato sustituido por un doble).
  5. **PASO 2 — Platform MUDADO** (`DECISIONES #15`, spec §4.ter): 12 clases en
     `app/Domain/Platform/{Models,Services,Concerns,Enums}` (`Setting`, `AuditLog`,
     `AuditLogger`, `DisplayTime`, `Money`, `Duration`, `PhoneNormalizer`,
     `MaintenanceSettings`, `QrCode`, `Turnstile`, `HasTranslations`, `DashboardPeriod`).
     236 ficheros tocados, 26 blades con FQCN inline. **Sin `Contracts` a propósito**: Platform
     es la base común («todos→Platform»), no una costura sustituible.
     Dos trampas encontradas que MANDAN en los pasos 3–6:
     - **dependencias invisibles**: 30+ llamadas a clases hermanas del mismo namespace no
       llevan `use` → al mover, «class not found». Se miden con el tokenizador ANTES de mover
       (paso 0 del checklist del spec); 9 ficheros ganaron el `use` explícito.
     - **FQCN que son DATOS**: la migración `convert_morph_types_to_aliases` guarda los FQCN
       que la BD tenía en 2026-08-12. Un `sed` global la rompe EN SILENCIO. ⛔ Congelada, con
       guard en `MorphMapTest` verificado por mutación.
  6. **PASO 3 — Content MUDADO** (`DECISIONES #16`, spec §4.quater): 18 clases en
     `app/Domain/Content/{Models,Services}` + primer renombre de vocabulario
     **`ParkRule`→`VenueRule`** (la CLASE; tabla `park_rules`, alias morph `park_rule` y
     nombres del panel se CONGELAN — verificado en MySQL dev: el alias resuelve a la clase
     nueva y las 5 filas se leen igual).
     - **Puerta de entrada decidida CON DATOS** (lo que el paso 2 dejó abierto): la capa de
       entrega (`Filament`, `Http`, `Livewire`, `Providers`, `Mail`, `Notifications`,
       `Console`, `Exceptions`) es el *composition root* y usa la superficie pública de
       cualquier módulo; el código de dominio AÚN SIN MUDAR (`app/Support`, `app/Models`) solo
       entra por `Contracts` o Platform → baseline `PENDING` (3 entradas), que solo encoge.
     - **Content NO es autosuficiente**: el arch-test destapó 3 dependencias reales hacia
       Booking (calendario de operación · identidad de zona · precio de referencia), anotadas
       en la baseline `LEGACY` para decidir en el paso 6.
     - Herramienta nueva: **`scripts/module-deps.php`** — el «paso 0» del checklist (medir las
       dependencias INVISIBLES) ya no es artesanal.
  7. **PASO 4 — Identity MUDADO** (`DECISIONES #17`, spec §4.quinquies): 10 clases en
     `app/Domain/Identity/{Models,Services}` (`User`, `Consent`, `CookieConsentLog`, `Role`,
     `Permission`, `CookieConsent`, `CustomerRegistrar`, `CustomerAccountContext`,
     `PuertaSettings`, `PermissionCatalog`). La «puerta» es capa de entrega y no se movió.
     - **Trampa de las factories DESACTIVADA antes de mover**: rompe en los DOS sentidos
       (modelo→factory y factory→modelo) y `UserFactory` es la única del repo. Resolver por
       nombre corto en `AppServiceProvider` + `$model` explícito en la factory + `composer
       dump-autoload`. Lo vigila `FactoryResolutionTest`.
     - **Dos exenciones CON NOMBRE** en el arch-test (no entradas anónimas de baseline, porque
       son reglas y no deuda): `SHARED_KERNEL` = `User` (spec §4) y `OUTBOUND` =
       `Notifications`/`Mail` (canal de salida del framework). Verificadas por mutación.
     - **La supresión RGPD cruza contextos** (`User::purge…` vacía PII de terceros en
       `order_items`): anotado en `SEAM`; el diseño limpio (evento «usuario anonimizado») queda
       para más adelante.
  8. **PASO 5 — Payments MUDADO** (`DECISIONES #18`, spec §4.sexies): 12 clases en
     `app/Domain/Payments/{Models,Services,Concerns}` + la **partición** de
     `HasItemActionGuards` (mitad refund → `GuardsItemRefunds`). **MUDANZA PURA**: ni una línea
     de lógica de dinero cambió; `INVARIANTES §1` y `§6` leídas antes de tocar.
     - **La costura del dinero ya no se esconde**: enumerada en el arch-test en las DOS
       direcciones. Payments→Booking incluye ORQUESTACIÓN — `RedsysReturnHandler` es por
       `PAY-01` el único que pasa una Order a `paid` y por `PAY-03` dispara `TicketIssuer`:
       hoy Payments conduce el ciclo de vida de la reserva. **Candidato nº1 a evento de
       dominio**, cuando haya motivo (reestructurar el núcleo estaba prohibido aquí).
     - **La baseline ENCOGIÓ por primera vez** (su único movimiento legal): las 2 entradas de
       `RefundGateway`/`PaymentsServiceProvider` dejaron de ser legacy. El mecanismo del paso 1
       funcionó tal cual se diseñó.
  9. **PASO 6 — Booking MUDADO; `app/Models` y `app/Support` RETIRADOS** (`DECISIONES #19`,
     spec §4.septies): 40 clases a `app/Domain/Booking/{Models,Services,Concerns,Exceptions}`
     + último renombre de vocabulario `ParkSchedule`→`OperatingSchedule`.
     - **Dos inversiones se ARREGLARON en vez de perdonarse**: `ReservationException` (vivía en
       `app/Exceptions`, capa de entrega, siendo excepción de dominio) y
       `OrderCreator`→`Purchase` (importaba la god-class de UI solo por `MAX_LINES_PER_CART`;
       la constante vuelve a `OrderCreator`, donde `PAY-12` dice que vive; valor y enforcement
       idénticos, API pública de `Purchase` intacta).
     - **Baselines ajustadas**: `PENDING` y `LEGACY` **vacías**; `SEAM` recoge la costura del
       dinero en los dos sentidos; `ALLOWED` reconoce Booking↔`Payments\Contracts` como canal
       sancionado; nace **`DEFERRED`** con las 5 flechas Content→Booking del paso 7.
     - ⚠️ **La migración `convert_morph_types_to_aliases` pasa a REQUISITO DE DESPLIEGUE**: al
       desaparecer `App\Models\*`, el fallback de FQCN legacy ya no resuelve. Actualizar código
       sin migrar revienta al leer un `payable`/`priceable`/`target` antiguo. `MorphMapTest` lo
       fija (antes aseveraba lo contrario, cierto hasta este paso).
- Fase 1 cerrada esta misma sesión: marca a 6 líneas intencionales, prefijo de pedidos =
  setting `sales.order_prefix` (default `R-`), semilla neutra «SaltoPark», jurisdicción
  legal por token, wordmark data-driven (`DECISIONES #12`).
- Sistema documental y protocolo completos (`DECISIONES #10`/`#11`): gate en pre-push (solo
  `main`), skills arranque/dod/cierre, permisos en 3 capas con `guard-bash`, invariantes con
  ID, GLOSARIO · DEUDA · INSTALACION-CLIENTE · TESTING §datos · specs/.
- Entorno local: web `8081` · MySQL `3308` · Mailpit `8028`; BD dev sembrada con SaltoPark
  (usuarios dev `admin@jumpweb.test` / `empleado@jumpweb.test`, contraseña `password`).
  La BD dev ya corre la migración del morphMap.

## ▶ Próximo paso
**Spec de módulos, PASO 7 — CIERRE de Fase 2** (leer `docs/specs/modulos-dominio.md` §5.7 y
§4.septies). La mudanza está hecha; queda una decisión de diseño y un barrido.

**1. Resolver las 5 flechas `DEFERRED`** (`ModuleBoundariesTest`) — la decisión que el paso 3
aplazó y el paso 6 acotó. Son Content leyendo Booking para PINTARLO:
`HeroStatus`→`OperatingSchedule` · `ScheduleDisplay`→`OpeningHour`/`Season`/`SpecialDate` ·
`StructuredData`→`OpeningHour` · `ThemeSettings`→`Zone` · `LandingAddonPresenter`→`TicketType`.
- La salida (b) «reclasificar el calendario a Platform» está **descartada con medición**:
  `SpecialDate` referencia `RateType` (tarifa) y Platform no puede depender de nadie.
- Queda (a): **contratos de LECTURA en Booking**, extraídos de estas llamadas reales —
  «calendario de operación», «identidad de zona», «precio de referencia»—, igual que se hizo en
  el paso 1 con `PublishableCatalog` y `CustomerReservations`. NO inventar superficie: mirar qué
  usan exactamente esos 5 ficheros y exponer eso.

**2. Barrido final**: `git grep -n 'App\\Support\\\|App\\Models\\'` debe dar solo prosa
histórica (docblocks que explican por qué la migración del morphMap está congelada) y la propia
migración. Revisar rutas citadas en la doc (`docs-check` cubre las rutas, NO los namespaces en
prosa: eso hay que barrerlo a mano, como en cada paso).

**3. `ARQUITECTURA.md` §4 ya está reescrito** con el árbol final (se hizo en el paso 6 para no
dejar el doc mintiendo). Repasar que sigue fiel al cerrar.

Con eso, **Fase 2 cerrada** y el tracker pasa a Fase 3 (API v1).

**Pendiente del owner** (❗): 2FA del panel (sin plan — `DEUDA.md`) · mecanismo del primer
admin de producción (`INSTALACION-CLIENTE.md` §5) · backlog de producto de Fase 6.

## Entorno (local)
- Docker (Sail) en WSL2, repo en `~/proyectos/jumpweb`. Web `localhost:8081` ·
  Mailpit `localhost:8028` · MySQL `localhost:3308`. Siempre `-u sail` en `exec`.
- Si tocas `OrderCreator`/`RedsysReturnHandler`/`SlotGenerator`: el push exige
  `VERIFY_CONC=1` tras correr los comandos de INVARIANTES §6.

## Herencia
Base: Laravel 13 · 30 modelos · 71 migraciones · 17 Filament Resources · Livewire v4 ·
Redsys (sandbox) · suite 2132 verde heredada del origen (2026-08-12; hoy 2141 con los
tests de Fases 1–2).
