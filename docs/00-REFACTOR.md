# JumpWeb — Refactor de generalización (tracker VIVO)

> Tracker activo del refactor. Última actualización: **2026-08-13**.
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
      **`INVARIANTES.md`** destilado (54 entonces; hoy **55 invariantes de no-regresión**, el
      recuento vivo lo verifica `docs-check`). Sin datos del cliente
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
      §2). `Livewire/Tickets/Purchase.php` (1.859 líneas, medidas el 2026-08-13; la cifra 2.049 que figuraba aquí estaba inflada) muere con la SPA en **Fase 4** (ya
      listado allí); `Filament/.../ViewOrder.php` (5.028 líneas) es capa de entrega y no se tocó
      — Fase 2 solo documentó sus costuras. Lo que SÍ hizo Fase 2 con `Purchase`: cortar la
      dependencia INVERSA (`OrderCreator` ya no lo importa, paso 6). Sigue vivo en `DEUDA.md`.
- [x] **Contratos entre módulos explícitos** (pasos 1–6): `Booking\Contracts`
      (`CustomerReservations`, `PublishableCatalog`) y `Payments\Contracts` (`RefundGateway`).
      Matiz medido en el paso 3: la capa de ENTREGA es el composition root y sí usa modelos de
      varios módulos — prohibírselo habría exigido reescribir el panel, fuera de alcance.

### Fase 3 — API v1 (API-first) 🟦 — los 6 pasos del corte (spec §9) CERRADOS + el checkout orquestado; lo único abierto viaja a Fase 6 por decisión
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
- [x] **Paso 2 — POLÍTICA DE ADMISIÓN e IDA DE PAGO fuera de la UI** (2026-08-13,
      `DECISIONES #28`): refactor sin endpoints. Nacen `Booking\Contracts\ReservationAdmission`
      (pausa de reservas, tope de pendientes, frecuencia y la extensión atómica del hold de
      `PAY-04`) y `Payments\Services\PaymentInitiator` (crear el `Payment`, firmar el formulario,
      `SUPERSEDED` de los intentos previos y el rastro en `audit_logs`). Los consumen el sidebar y
      «Mis pedidos»; el `POST /orders` del paso 4 será el tercero, y por eso este paso va antes.
      **El hallazgo**: las dos superficies aplicaban políticas DISTINTAS sin que nadie lo hubiera
      decidido y sin ningún test que las fijara —el reintento de «Mis pedidos» no pasaba por ningún
      límite por titular—, y el limitador contaba pantallas en vez de reservas (la 2.ª compra del
      minuto se bloqueaba con el tope en 3). Las tres asimetrías las resolvió el owner. El
      `CRITICAL_RE` del `pre-push` se amplió a las dos clases nuevas: el código de `PAY-04` llevaba
      tiempo fuera del gate por vivir en un Livewire. Suite **2292 verde** + los dos verificadores
      de concurrencia sobre MySQL (16 workers).
- [x] **Paso 3 — AUTH POR API ✅ COMPLETO** (3a + 3b + 3c, `DECISIONES #29`–`#31`):
      - [x] **3a — REVOCACIÓN de credenciales** (2026-08-13): punto único
            `User::revokeAllAccess()`/`revokeOtherAccess()` para sesiones **y** tokens de API.
            El hueco era de CINCO sitios, no cuatro: el quinto es la limpieza de go-live, que
            dejaba tokens **huérfanos** (`personal_access_tokens` es morph y no tiene FK,
            verificado). Guarda de arquitectura que prohíbe una sexta copia + `INVARIANTES
            RGPD-06`. Hecho ANTES de que exista un token emitido. Suite **2302 verde**.
      - [x] **3b — SESIÓN POR API** (2026-08-13, `DECISIONES #30`): `Identity\Services\PasswordLogin`
            (los DOS limitadores de `SEC-06`, credenciales, `last_login_at`) + `POST auth/login` y
            `auth/logout` sobre la sesión stateful; la web lo consume desde el mismo commit.
            `SEC-06` verificado POR MUTACIÓN en las tres capas. Dos hallazgos: sin `Origin`
            *stateful* no hay sesión y el login respondía **500** (guarda + 400), y el logout no
            limpiaba los guards ya resueltos. Suite **2320 verde**.
      - [x] **3c — ALTA y CONTRASEÑA por API** (2026-08-13, `DECISIONES #31`):
            `Identity\Services\SelfSignup` + `PasswordRecovery`, y los cuatro endpoints públicos
            (`auth/register`, `auth/email/resend`, `auth/password/forgot`, `auth/password/reset`).
            **Dos políticas de enumeración distintas y las dos explícitas**: el alta dice que un
            correo ya existe (decisión de producto de la clienta, replicada a propósito) y la
            recuperación no dice nada. El `201` del alta va sin cuerpo **porque el contrato destapó
            que el perfil delataba el señuelo**. Suite **2347 verde**.
      - [ ] La EMISIÓN de tokens Bearer (`POST auth/tokens`) viaja a **Fase 6**, con la app que los
            consuma (`DECISIONES #29`); la infraestructura ya está lista.
- [x] **Paso 4 — EL DINERO ✅ COMPLETO** (4a+4b+4c+4d), el paso de más riesgo de la fase:
      - [x] **4a — TARIFICACIÓN de la cesta** (2026-08-13, `DECISIONES #32`): nace
            `Booking\Contracts\CartPricing` (+3 DTOs) con `CartPricer` detrás, y `POST orders/quote`
            encima; la web lo consume desde el mismo commit. **El hallazgo**: la aritmética del
            dinero no estaba duplicada sino TRIPLICADA dentro del propio componente —`cartLines()`,
            `cartTotalCents()` y `cartDepositCents()` recorrían la misma cesta con las mismas
            reglas—, más una cuarta copia en el panel que hoy coincide y queda medida en `DEUDA.md`.
            El «ESPEJO EXACTO» que el comentario prometía respecto a `Order::onlineDueCents()` no lo
            comprobaba ningún test: ahora `CartPricerTest` crea el pedido real y compara. De regalo,
            una sola pasada dejó el coste en un tercio (presupuestar 12 líneas cuesta lo mismo que
            una) y quedó medido lo que NO se arregló: 2 consultas por complemento, dentro de código
            compartido con `OrderCreator`. Suite **2376 verde** + los dos verificadores de
            concurrencia sobre MySQL (16 workers).
      - [x] **4b — DISPONIBILIDAD con la cesta** (2026-08-13, `DECISIONES #33`): nace
            `Booking\Contracts\AvailabilityOffer` (+2 DTOs) con `AvailabilityReader` detrás, y
            `GET availability/{product}/dates` + `POST availability/{product}/times` encima; la web
            lo consume desde el mismo commit. Lo extraído no eran las reglas de oferta —ya vivían
            bien en `SlotOffer`— sino la derivación de la CESTA a ocupantes, que estaba en la clase
            de UI y es la que decide si una hora se puede vender (`AFORO-02`). **El hallazgo**: la
            fuente única calculaba el máximo CONTRATABLE y publicaba solo el de mostrar — en un pack
            con cupo 60 y máximo 20 son 60 y 20, y un cliente que acotara su selector con el primero
            dejaría pedir invitados que el checkout rechaza. De regalo, `RateResolver` salió entero
            de la capa de UI y `SlotOffer` entró en el `CRITICAL_RE` del `pre-push`, donde
            `CriticalPathGateTest` ya lo daba por incluido. Suite **2404 verde** + los dos
            verificadores de concurrencia sobre MySQL (16 workers).
      - [x] **4c — CREACIÓN y COBRO** (2026-08-13, `DECISIONES #34`): `POST orders`,
            `GET orders/{code}` y `POST orders/{code}/payment` sobre las tres piezas que ya existían,
            más los **códigos de error de negocio** del contrato (12 motivos de rechazo con mapa
            exhaustivo por test). **Aquí no se extrae nada: se orquesta**, y por eso es el trozo de
            más riesgo — una secuencia mal ordenada pasa `ApiBoundariesTest` con nota. La red son
            cuatro tests (admisión antes y consumiendo · hold siempre, `AFORO-10` · cobro sobre el
            pedido persistido · soltar en el primer fallo y no tocar en el reintento), verificados
            por MUTACIÓN. La orquestación se queda en la capa de entrega a propósito: extraerla
            añadiría una flecha a una baseline que solo encoge, y el arreglo de fondo
            (`PaymentProvider`) ya está en el backlog de la fase. Suite **2424 verde** + los dos
            verificadores sobre MySQL (16 workers) + flujo completo por `curl` contra el servidor.
      - [x] **4d — DESENLACE** (2026-08-13, `DECISIONES #35`): `GET orders/{code}/payment-status`
            con los DOS ejes de estado (reserva + último intento de cobro) y el motivo del rechazo
            como código y como texto. **El hueco que cerraba**: el rechazo de tarjeta solo viajaba
            por la SESIÓN de la web, así que la API decía «pendiente» toda la retención y luego
            «caducado», nunca «reintenta». Además, el token de la vuelta se consumía ANTES de
            validar la titularidad: como va atado a su `user_id`, un tercero no podía usarlo pero sí
            QUEMARLO —dejando al cliente sin confirmación tras haber pagado—; ahora se mira, se
            valida y solo entonces se consume. El retorno móvil se resuelve por DECLARACIÓN en el
            propio contrato (sondeo + `redsys_merchant_url` como prerequisito duro). Suite **2438
            verde** + los dos verificadores sobre MySQL + ciclo completo por `curl`.
- [x] **Paso 5 — POST-FORM por API ✅** (2026-08-13, `DECISIONES #36`): `GET`/`PUT
      reservations/{id}/guest-form`, el **segundo consumidor**. Su dificultad no era el endpoint
      sino **cómo autentica la API a un portador de firma**: la firma de Laravel cubre la URL
      EXACTA, así que la del correo —de una ruta web— no autoriza un `PUT /api/v1/...`
      (verificado: la misma firma da **403 en la API y 200 en su ruta web**). El canje es firmar
      también la URL de la API con la MISMA caducidad y entregarla a quien ya demostró acceso
      (`OrderItem::guestFormApiUrls()`); el `GET` devuelve la de guardar. De paso, la persistencia
      bajó al dominio (`OrderItem::submitGuestForm()`) —la guarda de frontera prohíbe escribir
      modelos desde un controlador de API, y con razón— y la escalada **403 → 410 → 404** pasó a
      tener un solo sitio (`Http\Concerns\AuthorizesGuestForm`), compartido con la web: lo que se
      comparte no son tres líneas, es el ORDEN, que es la propiedad de seguridad. Suite **2455
      verde**; escalada y canje verificados por MUTACIÓN y con `curl`.
- [x] **Endpoints v1** cubiertos por los pasos 0–5: auth/registro/perfil · catálogo ·
      disponibilidad (fechas/franjas) · presupuesto · pedido y pago (init + reintento +
      desenlace) · mis reservas y mis pedidos · post-form de invitados. **El contenido por API
      NO entra**: el spec §2 lo asigna a Fase 5 a propósito.
- [x] Especificación **OpenAPI** versionada (`openapi/v1.yaml`, escrita a mano) + tests de
      contrato que validan la RESPUESTA REAL en cada endpoint, con estrictez vigilada
      (`ApiContractTest`) y prueba por mutación en cada paso.
- [x] Rate-limiting (suelo del grupo + techos propios donde hacen falta), sobre de error único
      con `code` estable —incluidos los **códigos de negocio** y su mapa exhaustivo por test—,
      paginación (`ApiCollection`) y convenciones → `DECISIONES #24`, `#26`–`#36`.
- [x] **CIERRE — el CHECKOUT ORQUESTADO** (2026-08-13, `DECISIONES #37`; spec
      `docs/specs/checkout-orquestado.md`, aprobado tras revisión adversarial ×3). El owner partió
      en dos la abstracción `PaymentProvider` que quedaba abierta: **la mitad medida ahora, el
      driver a Fase 6**. Nacen `Booking\Contracts\ReservationCheckout` (la SECUENCIA) y
      `Booking\Contracts\PaymentInitiation` (la IDA), con `Booking\Services\CheckoutOrchestrator`
      detrás; las **cinco** llamadas de las cuatro superficies de entrega —sidebar (comprar y
      reintentar), «Mis pedidos», y los dos endpoints de la API— pasan a pedir la secuencia en vez
      de escribirla.
      **Lo que se movió no son llamadas, es el ORDEN**, y por eso no lo veía ninguna guarda: un
      llamante que use los tres servicios correctos en el orden equivocado pasaba
      `ApiBoundariesTest` con nota. Ahora hay `CheckoutOrchestratorTest` (7 casos, uno por punto del
      orden) con las **5 mutaciones comprobadas** —quitar el hold: 4 rojos · `admitReservation`→
      `mayReserve`: 3 · borrar la compensación: 3 · añadirla al reintento: 2 · invertir el orden de
      `PAY-04`: 2— y `CheckoutSequenceTest`, que **prohíbe ejecutablemente** que la secuencia
      reaparezca fuera de `app/Domain` (un `grep` en el commit del refactor caduca al día siguiente;
      esta es su versión falsable).
      **Los dos hallazgos que la revisión destapó y sin los cuales el diseño era inaplicable**: (a)
      el orquestador no podía capturar `PaymentInitiationException` sin violar el grafo de módulos
      —vivía en `Payments\Exceptions` y Booking solo alcanza `Payments\Contracts`—, así que la
      excepción se mudó a `Contracts`, que es donde le tocaba por ser lo que lanza el puerto; (b) las
      constantes `SOURCE_*` eran de `PaymentInitiator` y las nombraban las cinco superficies, así que
      subieron al puerto. **Regla nueva que sale de aquí: el puerto vive en el módulo cuyos tipos
      habla** —por eso `RefundGateway` (habla de `Payment`) está en Payments y `PaymentInitiation`
      (habla de `Order`) está en Booking, aunque lo implemente Payments—.
      Suite **2467 verde** · **cero entradas nuevas en cualquier baseline** (era el criterio rector)
      · los dos verificadores sobre MySQL (16 workers) · ciclo completo por `curl` en las cuatro
      superficies contra el servidor.
- [ ] La EMISIÓN de tokens Bearer sigue siendo lo único abierto de la fase, y viaja a **Fase 6**
      (`DECISIONES #29`). Es el mismo ítem listado dentro del paso 3.

### Fase 4 — Sidebar SPA 🟦 — diseño APROBADO (`specs/sidebar-spa.md` v2, revisión ×3); paso 4.0a a medias
- [x] **Diseño escrito y REVISADO adversarialmente** (2026-08-13): `docs/specs/sidebar-spa.md`
      **v2**. Tres revisores independientes (paridad funcional · tema y contrato visual · riesgo de
      implementación) declararon la v1 **INSUFICIENTE · SÓLIDO-CON-CAMBIOS ×2**; los hallazgos se
      verificaron uno a uno contra el código. Los tres que la hacían inaplicable: «cero endpoints
      nuevos» era falso (faltan cinco, dos bloqueantes), la vuelta de Redsys no tenía camino hacia
      la SPA, y «el contrato son las clases» era falso —90 de 292 selectores son estructurales, así
      que el contrato es el ÁRBOL—. Decisiones del owner: alcance = solo el cajón · tema = tokens +
      hoja por instalación · Vue 3 + Pinia · cesta sin `event_data` (RGPD) · tokenizar `site.css`
      dentro de esta fase.
- [x] **Paso 4.0a (1.ª mitad) — la landing deja de conocer el motor** (2026-08-13): la intención de
      entrada al cajón se declara (`$store.purchase.openWith({…})`) y cada motor registra su
      adaptador; antes tres vistas despachaban eventos de Livewire que **con otro motor no fallan:
      no hacen nada**. Red: `SidebarSeamTest`, verificado por mutación. Escrito el contrato de
      `mode`/`identifying` (los consume gente de FUERA del cajón) y retirado `alpinejs` de
      `package.json`, que estaba declarado sin que nadie lo importara. Suite **2470 verde**.
- [x] **Paso 4.0a (2.ª mitad) — el desenlace del pago tiene un solo dueño** (2026-08-13): nace
      `Http\Sidebar\SidebarEntry`, que posee las tres claves de sesión de la vuelta de la pasarela
      —escribirlas, mirarlas y olvidarlas—. Antes se nombraban a mano en cinco ficheros y **solo
      `Purchase::mount()` las olvidaba**: con otro motor, el cajón se auto-abriría en cada página
      hasta que caducara la sesión. La distinción que lo hace posible es `peek()` (layout, NO
      consume) vs `consume()` (el motor), y existe por un dato MEDIDO: el componente es `lazy`, así
      que su `mount()` corre en una petición POSTERIOR a la del layout — consumir en el layout
      dejaría al motor sin nada que enseñar.
      Red: `SidebarEntryTest` (9 casos) con guarda ejecutable de «un solo dueño», verificada por
      mutación en PHP **y** en Blade. La guarda ignora comentarios con el tokenizador: tres docblocks
      citan las claves a propósito y una búsqueda de texto los contaba como infracciones.
      De regalo, una fuga menor cerrada: el login solo descartaba el desenlace «confirmado» al
      cambiar de titular, así que a Bob podía aparecerle el «pago denegado» de Alice.
      Suite **2479 verde**; los 14 tests que siembran esas claves a mano siguen pasando sin tocarse.
- [ ] **Paso 4.0b — los huecos de API** (EN CURSO, 2026-08-13; diseño en `sidebar-spa.md` §4.4 v3,
      hecho por cinco agentes en paralelo y revisado en coherencia). **1 de 5 cerrado**:
      `GET /me/reservation-eligibility`, el aviso temprano de «¿puedo reservar?» que la web tiene
      desde siempre y la API no. Va primero porque su guarda protege a los otros cuatro:
      `admitReservation`/`admitPaymentRetry` —las variantes que CONSUMEN ficha— quedan prohibidas
      fuera de `app/Domain`. Sin eso, el error de «contar pantallas en vez de reservas» que el paso 2
      de Fase 3 ya pagó una vez volvería con el primer endpoint que preguntara por elegibilidad.
      Nace `Http\Api\AdmissionCodeMap`: el código público (`too_many_pending_orders`) NO es la
      constante del dominio (`too_many_pending`), y el `match` que traducía vivía privado en un
      controlador. Verificado por mutación: cambiar `mayReserve` por `admitReservation` —dos
      palabras— deja **dos** guardas en rojo, la de conducta y la de arquitectura.
- [ ] **Paso 4.0c — tokenizar `site.css`** (EN CURSO, 2026-08-13). Primera mitad hecha, la de
      riesgo cero: todas las sustituciones son **equivalentes por construcción** —un script aborta
      si el token no vale EXACTAMENTE el literal que sustituye— y solo dentro de las reglas del
      sidebar. Nace la escala `--fw-*` (medida: 51 usos con solo 5 valores, tres cubren 49), se
      empiezan a usar los `--r-pill`/`--r-sm` que **ya existían sin usarse**, y 10 de los 13 colores
      crudos pasan a token (6 alfa de `--fg`, 2 `--bg-soft`, 1 `--err`, 1 `--warn`, comprobados por
      aritmética RGB). Tokenización de propiedades TEMATIZABLES: **43% → 49%**; colores crudos:
      **13 → 3**. Red: `SidebarTokenBudgetTest`, presupuesto que solo puede mejorar.
      ⚠️ Queda la otra mitad: las escalas de `font-size` (74 usos, 17 valores) y de espaciado
      (`gap` 52 + `padding` 47). Esas SÍ exigen decidir una escala y revisar el resultado a ojo,
      porque redondear un valor cambia el diseño.
- [ ] SPA embebida (Vue 3 + Pinia) para el cajón completo: catálogo, fecha/hora, cesta,
      login/registro, pago, vuelta y reintento. **Primer consumidor real de la API v1.**
- [ ] Paridad funcional con el sidebar Livewire actual ANTES de retirarlo (feature-flag por
      instalación para poder convivir/comparar).
- [ ] Retirar `Purchase.php` + puente Alpine frágil cuando la paridad esté validada.

### Fase 5 — Capa de contenido profesional ⬜
- [ ] Sustituir el composer global `'*'` por **query services de contenido** con caché
      etiquetada e invalidación por evento de modelo (hoy: memo por request tras el W1).
- [ ] Theming como paquete coherente (tokens CSS + tema BD + assets por instalación).
- [ ] Contenido consumible también vía API (para que la app móvil pinte lo mismo que la landing).

### Fase 6 — Móvil + features nuevas ⬜
- [ ] **Segundo driver de pasarela** (Stripe u otros) sobre el puerto `Booking\Contracts\
      PaymentInitiation` que dejó el cierre de Fase 3: selección de driver por configuración, e
      imprescindible para instalar un cliente fuera de España. Se aplazó aquí a propósito
      (`DECISIONES #37`): con un solo driver real, la forma del enchufe es especulación — el segundo
      es quien la revela. El puerto ya existe, así que no hay que tocar a ningún consumidor.
- [ ] **Emisión de tokens Bearer** (`POST auth/tokens`): la infraestructura de Sanctum y toda la
      revocación están hechas y probadas desde Fase 3 · paso 3a (`DECISIONES #29`).
- [ ] Congelar contrato API v1; guía de integración móvil (auth, refresh, push, deep-links a pago).
- [ ] Features nuevas y modificaciones sobre el sistema actual (backlog a definir con el owner).

## Relación con el proyecto origen
El cliente origen (jumpingjump) sigue vivo en **su** repo con su canal de deploy; este repo no
le despliega nada. Mejoras de JumpWeb aplicables allí se portan **solo por decisión explícita**,
como cambios independientes en aquel repo (ver `DECISIONES #1`).
