# Estado del proyecto — foto viva

> Documento corto (carga obligatoria al arrancar). Solo «dónde estamos / qué sigue».
> Última actualización: **2026-08-12**.

## ▶ Dónde estamos
**Fase 0 ✅ · Fase 1 ✅ · Fase 2 (modularización) 🟦 — pasos 1, 2 y 3 hechos; quedan Identity, Payments y Booking.**
- Suite **2170 en verde** (8149 aserciones, `--parallel` ~1m11s) · Pint limpio · `docs-check`
  verde · `redsys:verify-concurrency` y `purchase:verify-oversell` EN VERDE sobre MySQL real.
  La corrida SECUENCIAL completa se verificó en el paso 2 (2157/2157 en 557s): los dos modos
  dan lo mismo. El contador «PHPUnit Notices: 1» sale solo en la paralela completa y es del
  runner, no del código (ver `TESTING.md`).
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
**Spec de módulos, PASO 4 — mudar Identity** (leer `docs/specs/modulos-dominio.md` §5.4 y el
**checklist mecánico** del final de §5): `User`, `Consent`, `CookieConsent`+`CookieConsentLog`,
`Role`, `Permission`, `PermissionCatalog`, `CustomerRegistrar`, `CustomerAccountContext`,
`PuertaSettings` y la puerta.

**Paso 0 primero** (ya es un comando):
`docker compose exec -u sail laravel.test php scripts/module-deps.php User Consent Role Permission …`
— lista qué arrastra cada clase y quién la referencia, marcando las INVISIBLES (las que no
llevan `use` por ser del mismo namespace; en el paso 2 fueron la única causa real de rotura).

⚠️ **Trampa conocida que muerde en ESTE paso**: `Model::factory()` resuelve la factory por la
convención `App\Models\X` → `Database\Factories\XFactory`. Al mover `User` a
`App\Domain\Identity\Models` esa resolución se rompe — y `UserFactory` es la ÚNICA factory del
repo, usada por media suite. Hay que mover la factory al namespace espejo o registrar un
resolver ANTES de tocar `User`. (Los pasos 2 y 3 no la tocaron: ningún modelo mudado usaba
`HasFactory`.)

`User` es **kernel compartido**: vive en Identity y sus relaciones cruzadas quedan exentas como
costura de BD (spec §4). Ya hay una entrada suya en la allowlist `SEAM`
(`Platform/Models/AuditLog.php` → `App\Models\User`) que habrá que reapuntar al namespace nuevo.

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
