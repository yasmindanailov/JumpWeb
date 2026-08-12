# Estado del proyecto — foto viva

> Documento corto (carga obligatoria al arrancar). Solo «dónde estamos / qué sigue».
> Última actualización: **2026-08-12**.

## ▶ Dónde estamos
**Fase 0 ✅ · Fase 1 ✅ · Fase 2 (modularización) 🟦 — contratos en pie; las mudanzas empiezan en el paso 2.**
- Suite **2155 en verde** (8084 aserciones, `--parallel` ~1m18s, run del cierre 2026-08-12) ·
  Pint limpio · `docs-check` verde · `redsys:verify-concurrency` y `purchase:verify-oversell`
  EN VERDE sobre MySQL real.
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
**Spec de módulos, PASO 2 — mudar Platform** (leer `docs/specs/modulos-dominio.md` §5.2 y el
**checklist mecánico** del final de §5 antes de tocar nada): `git mv` de `Setting`,
`AuditLog`+`AuditLogger`, `DisplayTime`, `Money`, `MaintenanceSettings`, `PhoneNormalizer`,
`Duration`… a `app/Domain/Platform/{Models,Services}` + namespaces + imports (app **y** tests)
+ grep de FQCN en **blades** + migraciones históricas que los importen + citas en docs.
Es el paso de MAYOR churn y riesgo semántico NULO: sirve para rodar el checklist.
Al mudar, dos cosas del paso 1 entran en juego por primera vez:
- añadir `'Platform' => …` ya está en `ModuleBoundariesTest::ALLOWED`, pero **aflorarán
  entradas nuevas** en la baseline `LEGACY` (el namespace plano las escondía). A partir de
  aquí la baseline **solo encoge**.
- si mudas un modelo: nota de deploy «cola drenada + `queue:restart`» (payloads con FQCN).

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
