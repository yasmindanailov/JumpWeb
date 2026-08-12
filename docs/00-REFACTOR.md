# JumpWeb — Refactor de generalización (tracker VIVO)

> Tracker activo del refactor. Última actualización: **2026-08-12**.
> Leyenda: ⬜ pendiente · 🟦 en curso · ✅ hecho · ❗ bloqueado.
> Regla: **la suite en verde es la red** — ninguna fase se cierra con tests rotos.

## Visión

Convertir la base heredada (sistema de reservas de un parque de saltos, en producción)
en **JumpWeb**: plataforma white-label de reservas **multi-sector**, con tres superficies
bien separadas sobre un núcleo único, y preparada para app móvil.

```
┌─ Landing pública ─┐  ┌─ Sidebar de reservas ─┐  ┌─ Panel admin ─┐  ┌─ App móvil ─┐
│ Blade SSR (SEO)   │  │ SPA (login, reservas, │  │ Filament      │  │ (futura)    │
│ contenido del CMS │  │ cuenta de usuario)    │  │               │  │             │
└────────┬──────────┘  └──────────┬────────────┘  └──────┬────────┘  └──────┬──────┘
         │ capa de contenido      │        API v1 (REST · Sanctum · OpenAPI)│
         └───────────┬────────────┴───────────────┬──────┴───────────────────┘
                     ▼                            ▼
     ┌──────────────────────────────────────────────────────────────┐
     │  NÚCLEO DE DOMINIO (monolito modular)                        │
     │  Catalog&Booking · Content/CMS · Identity · Payments · Platform │
     └──────────────────────────────────────────────────────────────┘
```

**Lo que se preserva a toda costa:** el núcleo endurecido de dinero/aforo
(`OrderCreator` con locks anti-sobreventa verificados, `RedsysReturnHandler` idempotente,
`SlotOffer` fuente única de oferta, `AddonResolver`) y la **suite de 2132 tests**.

## Fases

### Fase 0 — Fundación ✅
- [x] Exportar la base (árbol versionado + fix de aislamiento de la suite) sin datos de
      **infraestructura** del cliente origen (deploy scripts con IP/SSH, docs, mockup) —
      verificado por grep. ⚠️ Los datos de **negocio** del cliente (dirección, mapa, SEO,
      jurisdicción legal) siguen en `ProductionSeeder`/`LegalContent` → ítem de Fase 1.
- [x] Doc fundacional propia (README · CLAUDE.md · este tracker · ESTADO · DECISIONES).
- [x] Repo GitHub privado `JumpWeb` creado (`yasmindanailov/JumpWeb`) y push del commit fundacional.
- [x] Entorno local propio levantado (web 8081 · MySQL 3308 · Mailpit 8028), conviviendo con el
      stack origen (ambos responden 200 a la vez; verificado 2026-08-12).
- [x] **Suite completa en verde en el repo nuevo** + Pint + `npm run build` (✓ built).
      Único fallo del primer run: `MailThemeTest` asertaba el literal de la marca origen →
      brand-agnostic **solo en el wordmark** (aserta `config('app.name')`); el test aún fija
      el tema `'jumpingjump'` y sus tokens → viaja con el renombrado del tema (Fase 1).
      2132 esperados.
- [x] **CI = gate local de pre-push** (2026-08-12, `DECISIONES #9`): GitHub Actions
      DESCARTADO (el owner no pagará facturación de GitHub); `ci.yml` eliminado. Hook
      versionado `.githooks/pre-push` (Pint repo completo + suite `--parallel`); activación
      por clon: `git config core.hooksPath .githooks`.
- [x] **Capa agent-first** (2026-08-12, `DECISIONES #7`): el repo lo desarrollan al 100%
      agentes IA → `CLAUDE.md` enrutador con tabla de contexto, `CONVENCIONES.md` (protocolo
      de agentes: DoD, handoff, empirismo), skills `/cierre-sesion` y `/dod`
      (`.claude/skills/`), permisos preconfigurados (`.claude/settings.json`).

### Fase 1 — Desbranding y generalización superficial ✅
- [x] **Marca retirada del código** (2026-08-12): `git grep -i jumpingjump -- ':!docs' ':!.claude'
      | wc -l` → **6 líneas, todas intencionales** (regla 7 de CLAUDE.md, guard-bash ×2, cron
      de ejemplo neutro, y el `assertDontSee('jumpingjump.com')` que actúa de GUARD contra
      regresiones). Los fixtures `JJ-…` autoconsistentes de tests y los códigos forenses en
      comentarios se conservan a propósito (sin valor de marca).
- [x] **Artefactos renombrados** (2026-08-12): tema mail → `themes/brand.css` (+ config +
      `MailThemeTest`) · `jj:purge-customers` → `app:purge-customers` · **prefijo de pedidos
      → setting `sales.order_prefix`** (default `R-`, editable en panel, `PaymentSettings::orderPrefix()`
      con fallback defensivo; `JJ-CONC` → `CONC-`) · cookie → `cookie_consent` (re-consent de
      navegadores antiguos, asumido) · memo → `app.shared_view_data` · `.env*.example`
      genericizados · vídeo del hero → `header_hero.mp4` (+ `.gitignore` + vista).
      Verificado: `purchase:verify-oversell` y `redsys:verify-concurrency` EN VERDE sobre
      MySQL real tras tocar `OrderCreator` (primera ejecución de ambos en este repo; de paso
      se arregló un falso negativo del verificador: su producto-sonda no tenía precio para la
      tarifa del día elegido si caía en festivo).
- [x] **Datos de NEGOCIO del cliente origen fuera del producto** (2026-08-12): `ProductionSeeder`
      = semilla neutra «SaltoPark» (ficticio, mismo sector; sin dirección/mapa/URL de
      registro/SEO con ciudad/festivos locales reales — incluida la URL de maps embed con
      coordenadas que el grep de marca no veía); jurisdicción → setting `legal.jurisdiction`
      vía token `:jurisdiction` (+ `:business_name`) de `LegalIdentity`; barrido
      `git grep -i 'san javier\|mirador\|hipos'` → 0 fuera de docs.
- [x] **Wordmark de los correos → data-driven** (2026-08-12): header de mail, marca del panel,
      título de puerta y wordmark de los 2 PDFs leen `business.name` (BD) con fallback
      `config('app.name')`; descripción del TPV con `:name` data-driven.
- [x] **Semilla demo neutra separada del fixture** (2026-08-12, `DECISIONES #12.c`):
      `ProductionSeeder` (SaltoPark, instalación en frío) ≠ `LandingContentSeeder` (fixture
      de la suite, contrato de conteos 8/2/4/14 INTACTO — ver `TESTING.md` §datos).
- [x] **Slugs de rutas públicas decididos** (`DECISIONES #12.a`): español ahora; configurables
      por instalación en Fase 4 con la SPA.
- [x] **Doc técnica portada y adaptada** (2026-08-12, `DECISIONES #8`; workflow de 14 agentes
      + verificación por grep): ARQUITECTURA · SEGURIDAD · TESTING · FLUJOS · PANEL-ADMIN ·
      REQUISITOS · MAPA-PAGINAS · OPERATIVA-SECTOR-ORIGEN · 8 docs de `sistemas/` ·
      **`MODELO-DATOS.md` regenerado desde el código** (30 modelos · 71 migraciones) ·
      **`INVARIANTES.md`** destilado (54 invariantes de no-regresión). Sin datos del cliente
      (verificado); cabecera «base heredada, verificar contra código» en todos. Índice en
      `docs/README.md` + tabla de enrutado en `CLAUDE.md`.
- [x] **Vocabulario de dominio: diseño entregado** (2026-08-12): spec en
      `docs/specs/vocabulario-dominio.md` (estreno de la convención de specs) — enfoque
      CONSERVADOR para el sector «ocio con aforo» (`DECISIONES #12.d`); la decisión final
      viaja con el diseño de módulos de Fase 2 (morphMap como prerequisito).

### Fase 2 — Modularización del dominio 🟦
- [x] **Diseño de contextos con revisión multi-agente** (2026-08-12, `DECISIONES #13`):
      spec aprobado en `docs/specs/modulos-dominio.md` — 4 inventariadores (278 clases) +
      3 arquitecturas rivales + 3 revisores adversariales. Layout `app/Domain/<Contexto>/`,
      contratos en namespace de destino, orden contratos→Platform→Content→Identity→
      Payments→Booking (dinero al final). Cimientos aplicados: gate `VERIFY_CONC` por
      basename y `docs-check`/`MorphMapTest` preparados para `app/Domain/*/Models` (los
      tres gates habrían muerto en silencio con la primera mudanza).
- [x] **Prerequisito — morphMap** (2026-08-12): `Relation::enforceMorphMap` con alias
      snake_case para los 30 modelos (`AppServiceProvider`) + migración
      `convert_morph_types_to_aliases` (idempotente y reversible; verificada en MySQL dev:
      31 filas convertidas, 0 FQCN restantes) + barrido de TODAS las comparaciones `::class`
      contra columnas morph (app y tests → `getMorphClass()`) + `MorphMapTest` (alias para
      todo modelo, round-trip, fila legacy FQCN sigue resolviendo, clase sin alias lanza).
      Mover/renombrar modelos ya NO rompe datos.
- [x] **Paso 1 — contratos en namespace de destino** (2026-08-12, `DECISIONES #14`; detalle en
      `docs/specs/modulos-dominio.md` §4.bis): `app/Domain/{Booking,Payments}/Contracts` con las
      TRES costuras reales medidas contra el código —Booking→Payments `RefundGateway`+`RefundResult`
      (`Order` ya no importa `Redsys`), Booking→Identity `CustomerReservations`+2 DTOs
      (`CustomerAccountContext` ya no consulta `Order`/`OrderItem`/`TicketType`), Booking→Content
      `PublishableCatalog`+`ComplementPlacement`—, bindings a implementaciones legacy en
      `Booking/PaymentsServiceProvider`, y la **frontera ejecutable**
      (`ModuleBoundariesTest`: 3 guardas verificadas POR MUTACIÓN + `ModuleContractsTest`: cada
      contrato sustituido por un doble). De regalo, la regla de comprabilidad #226 dejó de estar
      duplicada en dos consultas SQL que podían divergir. Suite 2155 verde.
- [x] **Paso 2 — Platform mudado** (2026-08-12, `DECISIONES #15`; detalle en la spec §4.ter):
      12 clases a `app/Domain/Platform/{Models,Services,Concerns,Enums}` (`Setting`, `AuditLog`,
      `AuditLogger`, `DisplayTime`, `Money`, `Duration`, `PhoneNormalizer`,
      `MaintenanceSettings`, `QrCode`, `Turnstile`, `HasTranslations`, `DashboardPeriod`),
      **236 ficheros** tocados (26 blades con FQCN inline). Sin `Contracts`: Platform es la base
      común («todos→Platform»), no una costura sustituible. Dos hallazgos que valen para los
      pasos 3–6: las **dependencias invisibles** del namespace plano (30+, sin `use`, que al
      mover revientan) y los **FQCN que son DATOS** (la migración del morphMap, ⛔ congelada y
      con guard propio verificado por mutación). Suite 2157 verde.
- [ ] Pasos 3–6 — mudar el resto de `app/Support/` y `app/Models/` a los módulos, con la suite
      como red (sin big-bang: Content → Identity → Payments → Booking).
- [ ] Romper los god-class documentados: `Livewire/Tickets/Purchase.php` (2.049 líneas; muere
      con la SPA en Fase 4) y `Filament/.../ViewOrder.php` (5.028 líneas; `wc -l` 2026-08-12).
- [ ] Contratos entre módulos explícitos (el panel y la web solo hablan con servicios de
      aplicación, nunca con modelos de otro módulo directamente).

### Fase 3 — API v1 (API-first) ⬜
- [ ] Autenticación por tokens (Sanctum) + flujo SPA (cookie) y móvil (token).
- [ ] Endpoints v1: auth/registro/perfil · catálogo · disponibilidad (fechas/franjas) ·
      carrito/pedido · pago (init + retorno; la notificación server-to-server ya existe) ·
      mis reservas · post-form de invitados · contenido (para la app).
- [ ] **Abstracción `PaymentProvider`** (driver Redsys primero; deja el enchufe para Stripe u
      otros — imprescindible para multi-sector fuera de España).
- [ ] Especificación **OpenAPI** versionada + tests de contrato (la doc de la API es artefacto
      de primera clase: la app móvil se construye contra ella).
- [ ] Rate-limiting, formato de errores único, paginación y convenciones de la API → `DECISIONES`.

### Fase 4 — Sidebar SPA ⬜
- [ ] SPA embebida (Vue 3 + Vite) para el sistema completo del sidebar: login/registro,
      compra/reserva, gestión de cuenta y reservas. **Primer consumidor real de la API v1.**
- [ ] Paridad funcional con el sidebar Livewire actual ANTES de retirarlo (feature-flag por
      instalación para poder convivir/comparar).
- [ ] Retirar `Purchase.php` + puente Alpine frágil cuando la paridad esté validada.

### Fase 5 — Capa de contenido profesional ⬜
- [ ] Sustituir el composer global `'*'` por **query services de contenido** con caché
      etiquetada e invalidación por evento de modelo (hoy: memo por request tras el W1).
- [ ] Theming como paquete coherente (tokens CSS + tema BD + assets por instalación).
- [ ] Contenido consumible también vía API (para que la app móvil pinte lo mismo que la landing).

### Fase 6 — Móvil + features nuevas ⬜
- [ ] Congelar contrato API v1; guía de integración móvil (auth, refresh, push, deep-links a pago).
- [ ] Features nuevas y modificaciones sobre el sistema actual (backlog a definir con el owner).

## Relación con el proyecto origen
El cliente origen (jumpingjump) sigue vivo en **su** repo con su canal de deploy; este repo no
le despliega nada. Mejoras de JumpWeb aplicables allí se portan **solo por decisión explícita**,
como cambios independientes en aquel repo (ver `DECISIONES #1`).
