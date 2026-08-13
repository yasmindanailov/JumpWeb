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
      **`MODELO-DATOS.md` regenerado desde el código** (30 modelos y las 71 migraciones de
      entonces; el recuento vivo lo verifica `docs-check`) ·
      **`INVARIANTES.md`** destilado (54 invariantes de no-regresión). Sin datos del cliente
      (verificado); cabecera «base heredada, verificar contra código» en todos. Índice en
      `docs/README.md` + tabla de enrutado en `CLAUDE.md`.
- [x] **Vocabulario de dominio: diseño entregado** (2026-08-12): spec en
      `docs/specs/vocabulario-dominio.md` (estreno de la convención de specs) — enfoque
      CONSERVADOR para el sector «ocio con aforo» (`DECISIONES #12.d`); la decisión final
      viaja con el diseño de módulos de Fase 2 (morphMap como prerequisito).

### Fase 2 — Modularización del dominio ✅
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
- [x] **Paso 3 — Content mudado** (2026-08-12, `DECISIONES #16`; spec §4.quater): 18 clases a
      `app/Domain/Content/{Models,Services}` + primer renombre de vocabulario
      **`ParkRule`→`VenueRule`** (clase sí; tabla `park_rules`, alias morph `park_rule` y
      nombres del panel CONGELADOS — verificado en MySQL dev). Se decidió **con datos** la
      puerta de entrada que el paso 2 dejó abierta: la capa de entrega es el *composition root*
      y usa la superficie pública de cualquier módulo; el código de dominio aún sin mudar solo
      entra por `Contracts`/Platform (baseline `PENDING`, 3 entradas). El arch-test destapó que
      **Content depende de Booking** en 3 frentes (calendario, zona, precio de referencia):
      anotados en `LEGACY` para decidir en el paso 6. Suite 2170 verde.
- [x] **Paso 4 — Identity mudado** (2026-08-12, `DECISIONES #17`; spec §4.quinquies): 10 clases a
      `app/Domain/Identity/{Models,Services}` (`User`, `Consent`, `CookieConsentLog`, `Role`,
      `Permission`, `CookieConsent`, `CustomerRegistrar`, `CustomerAccountContext`,
      `PuertaSettings`, `PermissionCatalog`). Se desactivó ANTES de mover la **trampa de las
      factories** (rompe en los DOS sentidos: modelo→factory y factory→modelo; `UserFactory` es
      la única del repo y la usa media suite) con resolver por nombre corto + `$model` explícito,
      guardado por `FactoryResolutionTest`. El arch-test ganó dos exenciones **con nombre**:
      `SHARED_KERNEL` (`User`, ya declarado kernel compartido en el spec §4) y `OUTBOUND`
      (`Notifications`/`Mail`, el canal de salida del framework). Suite 2177 verde.
- [x] **Paso 5 — Payments mudado** (2026-08-12, `DECISIONES #18`; spec §4.sexies): 12 clases a
      `app/Domain/Payments/{Models,Services,Concerns}` (`Payment`, `PaymentRefund`, `Redsys` +
      `Redsys/Vendor/*`, `RedsysReturnHandler`, `RedsysCardCodes`, `RedsysResponseCode`,
      `RedsysReturnOutcome`, `PaymentSettings`, `IncidentSettings`, `OrderRefundFlags`) + la
      **partición del trait** `HasItemActionGuards` (mitad refund → `GuardsItemRefunds`; corte
      verificado sin solapes antes de cortar). MUDANZA PURA: ni una línea de lógica de dinero.
      La **costura Payments↔Booking** queda enumerada en el arch-test en las dos direcciones,
      incluida la orquestación (`RedsysReturnHandler` conduce el ciclo de vida de la Order por
      `PAY-01`/`PAY-03`) → candidato nº1 a evento de dominio. La baseline **encogió** por primera
      vez. Suite 2191 verde + los dos verificadores de concurrencia en verde sobre MySQL real.
- [x] **Paso 6 — Booking mudado; `app/Support` y `app/Models` RETIRADOS** (2026-08-12,
      `DECISIONES #19`; spec §4.septies): 40 clases a `app/Domain/Booking/{Models,Services,
      Concerns,Exceptions}` + renombre `ParkSchedule`→`OperatingSchedule`. Dos inversiones se
      ARREGLARON en vez de perdonarse (`ReservationException` a Booking; `MAX_LINES_PER_CART`
      de vuelta a `OrderCreator`, donde `PAY-12` dice que vive). Baselines ajustadas:
      `PENDING`/`LEGACY` vacías, `SEAM` con la costura del dinero en ambos sentidos, y nace
      `DEFERRED` con las 5 flechas Content→Booking del paso 7. ⚠️ La migración del morphMap pasa
      a **requisito de despliegue**. Suite 2190 verde + los 2 verificadores sobre MySQL real.
- [x] **Paso 7 — CIERRE** (2026-08-12, `DECISIONES #20`; spec §4.octies): resueltas las 5
      flechas `DEFERRED` con dos contratos de lectura extraídos de llamadas reales —
      `OperatingCalendar` (+4 DTOs) y `ZonePalette`—. De regalo murieron **dos reglas
      duplicadas** que Content mantenía «para no divergir» de las reservas. Baselines finales:
      `LEGACY`/`PENDING`/`DEFERRED` vacías, `SEAM` solo con costura documentada; barrido de
      `App\Support`/`App\Models` a **cero**. Suite 2184 verde.
- [x] **God-class: fuera del alcance de Fase 2, y así estaba spec'ado** (`modulos-dominio.md`
      §2). `Livewire/Tickets/Purchase.php` (2.049 líneas) muere con la SPA en **Fase 4** (ya
      listado allí); `Filament/.../ViewOrder.php` (5.028 líneas) es capa de entrega y no se tocó
      — Fase 2 solo documentó sus costuras. Lo que SÍ hizo Fase 2 con `Purchase`: cortar la
      dependencia INVERSA (`OrderCreator` ya no lo importa, paso 6). Sigue vivo en `DEUDA.md`.
- [x] **Contratos entre módulos explícitos** (pasos 1–6): `Booking\Contracts`
      (`CustomerReservations`, `PublishableCatalog`) y `Payments\Contracts` (`RefundGateway`).
      Matiz medido en el paso 3: la capa de ENTREGA es el composition root y sí usa modelos de
      varios módulos — prohibírselo habría exigido reescribir el panel, fuera de alcance.

### Fase 3 — API v1 (API-first) 🟦
- [x] **Diseño escrito y REVISADO adversarialmente**: `docs/specs/api-v1.md` **v2** (2026-08-13).
      3 revisores independientes (invariantes/seguridad · arquitectura · riesgo de implementación):
      veredictos sólida-con-cambios · insuficiente · insuficiente, **15 hallazgos GRAVE**, todos
      incorporados. La v1 tenía cuatro afirmaciones falsas y una premisa errónea; el spec §8 lista
      qué cambió y por qué, y §9 parte la fase en 6 pasos.
- [x] **Dependencias DECIDIDAS** (`DECISIONES #21`, el owner delegó la elección): `laravel/sanctum`
      ^4.3 runtime + `hotmeteor/spectator` ^3.0 y `symfony/yaml` en dev. Scramble descartado
      (generar la doc desde el código invierte la relación de contrato).
- [x] **Anti-bot en cliente nativo RESUELTO sin relajar nada** (2026-08-13, `DECISIONES #23`): el
      Turnstile del registro es `SEGURIDAD` regla 5 (no `SEC-06`) y es data-driven; el consumidor
      de Fase 3/4 es la SPA, que ES un navegador. La app nativa es Fase 6 y allí se decide, con
      las tres salidas ya escritas. **Sin bloqueantes: el paso 3 queda desbloqueado.**
- [x] **Árbol de dependencias SANEADO** (2026-08-13, `DECISIONES #22`): 26 avisos → 0
      (`composer audit` y `npm audit`), sin tocar restricciones ni añadir paquetes. Suite 2186
      verde, Pint sin churn, verificadores sobre MySQL, PDF real generado con dompdf 3.1.6.
- [x] **Paso 0 — CIMIENTOS, sin negocio** (2026-08-13, `DECISIONES #24`; detalle en
      `docs/specs/api-v1.md` §10): `routes/api.php` con prefijo `/api/v1` (`ApiSurface::PREFIX`,
      fuente única) · **grupo de middleware declarado pieza a pieza** (SecurityHeaders ·
      Sanctum stateful · `ApiLocale` · `EnsureSiteAvailable` con render JSON · `no-store`
      autenticado · `throttle:api`) · **sobre de error único** con códigos estables
      (`ApiErrorCode`) desacoplados de las claves i18n · `GET me` · **OpenAPI `openapi/v1.yaml`
      escrito a mano** + validación de la respuesta REAL (Spectator) · guarda de frontera de API
      (§6.5) y `CRITICAL_RE` del pre-push ampliado a los controladores de checkout, ambos
      **verificados por mutación**. Dependencias instaladas (`#21`): Sanctum 4.3.3, Spectator
      3.0.2, symfony/yaml 7.4.15 — `composer audit` sigue en 0.
      **Dos hallazgos del código**, ninguno previsto por el spec: (a) crear `routes/api.php`
      activó el `HandleCors` GLOBAL de Laravel, cuya config por defecto abre `api/*` a
      `Access-Control-Allow-Origin: *` → cerrado con `config/cors.php` derivado de `APP_URL`;
      (b) Laravel ordena `AuthenticatesRequests` ANTES que `ThrottleRequests`, así que en rutas
      con `auth:` el 401 no consume el limitador — se conserva el estándar (invertirlo empeoraría
      los `throttle` de la web) y queda fijado por test. Suite **2223 verde**.
- [x] **Paso 1a — SOLO LECTURA de la cuenta** (2026-08-13, `DECISIONES #26`): `GET me/reservations`
      (sobre el contrato `CustomerReservations` de Fase 2, sin reimplementar su filtrado) y
      `GET me/orders` (paginado, **todos los estados**, con líneas y complementos anidados). Nace
      `ApiCollection`: forma única de lista `data` + `meta` — el intento de apoyarse en
      `ResourceCollection` produjo `data.data` y dos `meta`, y **lo cazó el test de contrato en la
      primera ejecución**. Dos correcciones que solo aparecen al contrastar con el dominio: el campo
      que la v1 llamó `online_due_cents` NO era «lo pendiente» sino el importe que se cobra online
      (renombrado a `online_amount_cents`), y un `enum` de OpenAPI 3.0 rechaza `null` aunque el
      campo sea `nullable` si no se lista dentro. Suite **2242 verde**.
- [x] **Paso 1b — CATÁLOGO por API** (2026-08-13, `DECISIONES #27`): `GET catalog/zones`,
      `catalog/products` (con filtro `?type=`) y `catalog/products/{id}`, públicos y de solo
      lectura. No es una copia para la API: nace el contrato `Booking\Contracts\ProductCatalog`
      (+5 DTOs) con `CatalogReader` detrás, y **`Livewire\Tickets\Purchase` pasa a consumirlo** en
      el mismo commit — la web y la API sirven el mismo catálogo, y lo comprueba
      `ModuleContractsTest` con un doble. La frontera dominio/presentación se decidió campo a campo:
      `search` (índice del buscador en cliente) y `zone_anchor` (ancla del deep-link) se quedan en
      la web porque se DERIVAN de lo que da el contrato. Tres hallazgos: el catálogo **no** podía
      consumir `AddonResolver::viewModel()` como suponía el spec (su firma exige el estado de la
      selección; lo reutilizable son sus REGLAS) · un `$ref` con `nullable` **no valida en ninguna
      de las dos direcciones**, así que la zona anidada va inline con una guarda que impide que
      diverja · el presupuesto de consultas medido por PENDIENTE (1 vs N) destapó un **N+1 real**
      en el propio read-model, corregido. Suite **2266 verde**.
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
