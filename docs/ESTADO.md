# Estado del proyecto — foto viva

> Documento corto (carga obligatoria al arrancar). Solo «dónde estamos / qué sigue».
> Última actualización: **2026-08-12**.

## ▶ Dónde estamos
**Fase 0 ✅ · Fase 1 ✅ · Fase 2 (modularización) 🟦 — pasos 1 y 2 hechos; quedan Content, Identity, Payments y Booking.**
- Suite **2157 en verde EN LOS DOS MODOS** (8122 aserciones): `--parallel` ~1m13s y
  **secuencial 2157/2157 en 557s** (comprobado en el cierre 2026-08-12, la primera vez en este
  repo) · Pint limpio · `docs-check` verde · `redsys:verify-concurrency` y
  `purchase:verify-oversell` EN VERDE sobre MySQL real.
  Detalle sin consecuencias: el contador «PHPUnit Notices: 1» aparece **solo** en la corrida
  paralela completa; la secuencial da 0 y ningún test falla. Es del runner, no del código.
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
**Spec de módulos, PASO 3 — mudar Content** (leer `docs/specs/modulos-dominio.md` §5.3 y el
**checklist mecánico** del final de §5, que el paso 2 afinó): páginas/legales (`LegalContent`,
`LegalIdentity`, `CookiePolicyContent`), landing (`HeroStatus`, `MapsEmbed`, `SocialEmbed`,
`ThemeSettings`, presenters) y los modelos `Faq`/`Page`/`LandingService`/`Offer`/`Attraction`
+ el renombre `ParkRule`→`VenueRule` (SIN renombrar la tabla: `$table='park_rules'` fijo).

**Empieza por el paso 0 del checklist**: medir las dependencias INVISIBLES (hermanas del mismo
namespace, sin `use`) de lo que mueves y de lo que se queda. En el paso 2 fueron la única causa
real de rotura. Ya se sabe de al menos dos: `EmailProductCard`→`ThemeSettings` (costura
Booking→Content, spec §6.7) y `ProductAvailability`→`ParkSchedule`.

Dos decisiones que TOCA tomar en el paso 3, no antes:
- ¿puede la capa de entrega (Filament/Livewire/Blade) referirse a `App\Domain\Content\Models\*`
  directamente? Hoy la 3ª guarda de `ModuleBoundariesTest` solo exime a Platform; Content es el
  primer módulo con modelos propios y ahí se decide con datos (el spec §4 anticipa que la capa
  de entrega sigue hablando con modelos).
- `Zone` es de **Booking**: a Content va solo su CMS visual (ver inventario del spec §5.3).

Cada paso del spec = una unidad de sesión con suite verde.
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
