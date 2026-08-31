# JumpWeb — Refactor de generalización (tracker VIVO)

> Tracker activo del refactor. Última actualización: **2026-08-14**.
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
      **`INVARIANTES.md`** destilado (54 entonces; hoy **59 invariantes de no-regresión**, el
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

### Fase 4 — Sidebar SPA ✅ — de 4.0a a 4.6, HECHOS: **los ONCE pasos transcritos** con Vue 3 + Pinia, y el extremo a extremo con navegador y pasarela REAL ya realizado (`#59`, que destapó que el motor no vendía y se arregló). **4.7 CERRADO el 2026-08-21** (`#111`, `#112`): manifiesto congelado (·1), inventario (·2a), corrección del contador (·2b·1), la migración (B) con la que el diff de árbol se alimenta del servidor (`#67`–`#73`), el re-apunte (·2b·2), la independización del contrato de árbol (·2b·3·0) y **el BORRADO de `Purchase.php` con el flag (·2b·3 + ·3)**. **4.4b·2, HECHO** el 2026-08-20 (`#108`). ✅ **El cajón SPA es el motor ÚNICO**: la paridad estaba cerrada desde `#73` y la sustitución lo está desde hoy. ✅ **A7 verificado en navegador el 2026-08-22** —y destapó que la costura de intención **nunca se cableó** (`#117`), arreglado el mismo día— y **`4.7` VALIDADO POR EL OWNER**, que es la cuarta condición del DoD. ✅ **Y el cajón queda REORGANIZADO antes del área de cliente** (`#119`, a petición del owner): tres capas —módulos planos, nueve stores de Pinia, componentes—, el embudo fuera de la raíz (`sections/PurchaseSection.vue`, raíz de 16 líneas) y el grafo del embudo CERRADO con guarda. ✅ **Y el ÁREA DE CLIENTE queda TERMINADA el 2026-08-22** (`#66`, `#120`), en tres tandas —leer, gestionar y retirar—: las cinco gestiones viven en el cajón, `/mi-cuenta/…` se retiró y **sus rutas sobreviven como PUERTA** que abre el cajón en su zona, porque 8 correos ya entregados apuntan ahí. ⚠️ **Con eso la fase CIERRA**: no queda ninguna casilla suya sin marcar. Lo que el área NO se llevó —la **auth** y **`account-context`**— quedó fuera de las tres tandas **a propósito** y tenía ficha propia en `DEUDA.md`, junto con las cinco secuencias transversales del embudo (deuda sin intereses: su coste no crece) ✅ **Y el 2026-08-23 cae el ÚLTIMO trozo de `#66`: la AUTH entra en el cajón y el MODAL de la cabecera se RETIRA** (`DECISIONES #122`, `specs/auth-en-cajon.md`): entrar, darse de alta y recuperar contraseña son tres zonas más, el paso 5 del embudo gana «he olvidado mi contraseña» con vuelta a la compra, y las tres rutas sobreviven como PUERTAS. ⚠️ **Su valor no fue el código sino lo que MIDIÓ**: la revisión adversarial paró dos bloqueantes —los textos del área viajan solo con sesión, y el contexto del alta estaba quemado— y la auditoría de los 36 tests del modal destapó **un hueco de seguridad vivo** (el desenlace de pago de otra persona sobrevivía a un login en dispositivo compartido) más dos huecos de guardia. Validado por el owner en navegador. ⚠️ **Lo que sigue fuera del cajón es `account-context`**, hoy el ÚNICO componente Livewire del layout — y del que cuelga que `livewire.js` llegue a la página.
- [x] **2026-08-28 · el OJO del owner en localhost, dos detalles y un TDZ** (`DECISIONES #210`,
      carril A): el «no» del login era **INVISIBLE en el área de cliente desde el 2026-08-23** —una
      `const auth` sombreaba la prop `auth` en `AccountSection.vue`; ninguna guarda podía verlo—,
      el **carrito no tenía «Volver»** (paso 4 sin banda ni `bk-back`; ahora lo tiene, también con
      la cesta vacía, +3 nodos en 3 claves del manifiesto) y el `watch` de menores seguía por encima
      de su `const` (TDZ). Guarda nueva `SidebarSetupBindingsTest` (3 mutaciones muerden; endurecida
      en la misma sesión por una revisión adversarial de 19 agentes: 15 hallazgos, todos aplicados),
      headless 9/9 + 9/9, spec `auth-en-cajon.md` **§8.ter**, guion §5.terdecies.
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
- [x] **Paso 4.0b — los huecos de API** (CERRADO 2026-08-14, **los 6**; diseño en `sidebar-spa.md` §4.4 v3,
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
      **2 de 5**: `GET /config`, los cuatro ajustes de instalación que Blade inyectaba a la vista y
      que ningún endpoint publicaba — sin ellos un cliente aprende las reglas CHOCÁNDOSE (descubre
      el tope de cesta con un 422). Los dos números viajan con su OPERADOR en la descripción
      (medido: el buscador usa `total > umbral` sobre el catálogo sin filtrar; el servidor rechaza
      `líneas > tope`), porque publicar el número sin él reparte la regla entre servidor y cliente.
      ⚠️ Su caso crítico es `SEC-07`: la URL de registro la edita un operador, y en la web el
      escape de Blade remataba la defensa — **un cliente JSON no tiene escape que la remate**, así
      que el saneado tiene que ocurrir antes de serializar o no ocurre. Verificado por mutación.
      De prerrequisito salió un incumplimiento de `SUITE-02` que llevaba tiempo: `Turnstile` tenía
      un memo **estático sin purga**, y dos ficheros de test habían acabado con la misma copia de un
      reset por Reflection. Ahora hay `Turnstile::flushCache()` en `TestCase::setUp()`, como manda
      el invariante, y las dos copias se retiraron.
      **3 de 5**: `GET /booking/status`, la pausa de reservas y su aviso traducido. Hasta ahora la
      pausa solo existía por API como el código de un 409 —el cliente se enteraba DESPUÉS de
      intentar crear el pedido—, mientras que la web sustituye el flujo entero por el aviso. Va
      separado de `/config` porque es ESTADO y se relee: la dueña acciona el interruptor con
      clientes navegando. Dos reglas quedan en el servidor y verificadas por mutación: los canales
      se ofrecen **a la vez y no en cascada** (una cascada escondería el WhatsApp de toda
      instalación con teléfono) y `contact_url` es **el último recurso ya decidido**, no «la página
      de contacto», para que el cliente no evalúe ninguna condición. De paso quedó anotada en
      `DEUDA.md` una divergencia preexistente que la extracción destapó: el mismo `contact.phone` se
      normaliza de dos formas distintas según quién lo pinte.
      **3,5 de 5**: la mitad SIN PII del resumen del paso 6 — seis campos que el sidebar componía y
      ningún endpoint publicaba. El desglose de señal es **por RESERVA y no por pedido** (#232 F3:
      en una cesta mixta entrada+pack, etiquetar el agregado engaña), y la composición del aviso
      «señal pagada · resto en el parque» baja a `ReservationFinancials`, que ya se declaraba fuente
      única del bloque de totales — son tres condiciones y cuatro superficies pintándolo.
      ⚠️ `Order.guest_form_pending` **no es** `any(items[].needs_guest_form)`, y por eso no comparte
      nombre: el servidor descarta antes las líneas CANCELADAS, así que un cliente que lo agregara
      prometería un formulario que nadie va a pedir. Verificado por mutación.
      **4 de 5 (2026-08-14)**: `GET /orders/{code}/event-data`, la mitad B — las respuestas del pack
      (nombre, edad y alergias de un menor, art. 9) en endpoint aparte y **solo las de la fase
      `booking`** (`DECISIONES #39`, spec §4.4.6). ⚠️ **La guarda que sostiene el diseño no es la del
      endpoint nuevo, es la de los otros dos**: `me/orders` y `GET orders/{code}` no las llevan
      NUNCA, comprobado sobre el cuerpo entero de la respuesta —no campo a campo—, porque añadirlas a
      `OrderItemResource` sería una línea y pondría datos de salud de un menor en cada página del
      historial. Verificado por mutación (colar el campo en el pedido, quitar el filtro de fase y
      quitar el scoping por titular ponen en rojo tres guardas distintas) y con `curl` contra el
      servidor real: sesión de la SPA, pack sembrado de SaltoPark con las dos fases rellenas,
      etiquetas desde BD en `es`/`en`, `no-store` presente y 404 —no 403— para un titular ajeno.
      De la implementación salió una extracción: emparejar respuesta con etiqueta estaba copiado en
      `Purchase::resolveEventData()` y en `ReservationSlip::eventDataRows()`, y este endpoint iba a
      ser la TERCERA copia. La fuente única es ahora `TicketType::eventAnswers()`; la del panel no se
      unificó a propósito (enseña las claves huérfanas, que no tienen fase que filtrar) y quedó
      anotada en `DEUDA.md`.
      **El SEXTO hueco (2026-08-14)**: `POST /cart/validate-line` —el que ninguno de los
      cinco diseños vio y el único que, ignorado, se descubre en producción y no en la suite
      (`DECISIONES #40`)—. Con la cesta de la SPA en `localStorage` no queda ida y vuelta al añadir,
      así que la regla o se pregunta o se transcribe a JavaScript; el caso que lo decide es que un
      campo `number` se sanea a dígitos, así que la EDAD contestada «cinco» el servidor la ve VACÍA.
      La regla baja al dominio (`Booking\Contracts\CartLineValidation`) **y la compra web la
      consume**: `addToCart()` ya no decide, pide el veredicto y solo traduce el «no».
      ⚠️ **Esa delegación encontró un fallo que ninguna revisión del diff habría visto**:
      `Cart::sanitize()` fuerza `max(1, qty)` —correcto para una cesta guardada— y aplicado a una
      línea CANDIDATA convertía «todavía no he elegido cuántos» en un 1; la web habría añadido una
      entrada que nadie pidió. Lo cazó `PurchasePanelTest` al primer intento. Es el argumento entero
      a favor de hacer consumir la extracción en el mismo commit.
      La otra diferencia con la web es deliberada: la franja se comprueba contra la **oferta** y no
      contra el aforo a secas, porque `maxQuantity()` responde de una franja concreta aunque no se
      ofrezca (no mira día pasado, corte intradía, ventana ni antelación) y validar solo con él daría
      por buenas líneas que el checkout rechaza.
      Verificado por mutación (validar solo por aforo → 3 rojos; quitar la guarda de la fusión → 2;
      sanear la candidata como cesta → rojo en la web) y con `curl` sobre los datos de SaltoPark.

      **El QUINTO hueco, y con él la fase de API cerrada (2026-08-14)**:
      `POST /catalog/products/{product}/addons` (`DECISIONES #41`), los complementos RESUELTOS y el
      pie de la línea en la misma respuesta. Estaba declarado como el más arriesgado del paso porque
      «cambia la semántica de un método del dominio consumido por dos superficies vivas», y al
      medirlo el riesgo se disolvió: la regla **ya vivía en el dominio y las dos superficies ya la
      compartían**, así que no había copia que unificar — solo una fuente que publicar con DTOs.
      Nace `Booking\Contracts\AddonOffer`, que no reimplementa nada, y **ni `AddonResolver` ni sus
      dos consumidores se tocaron**. ⚠️ Es la decisión OPUESTA a la de 4.0b·6 y por la razón
      contraria: allí no delegar habría sido copiar; aquí delegar no arregla nada y mueve la
      plantilla del paso con más clics del embudo.
      El dinero viaja en la misma respuesta —delegando en `CartPricing`— y la decisión quedó MEDIDA:
      componer resolución y tarificación cuesta las mismas consultas que pedirlas por separado
      (9 + 5 con 4 complementos), con la mitad de viajes y de fichas de `throttle`. La pendiente la
      fija `ApiOverheadTest::test_resolving_addons_pays_the_known_slope_and_no_more`.
      De regalo cierra por construcción el fallo silencioso que el spec §4.4.3 describía: lo que se
      tarifica es la selección que el dominio acaba de resolver, así que el `catch (Throwable)` de
      `CartPricer::resolveAddons()` no puede dispararse por lo que mande el cliente.
      ⚠️ **Y la verificación destapó un test que no probaba lo que decía**: el de la poda en cadena
      pasaba igual con una poda de un solo nivel, porque el orden natural ya la resolvía en una
      pasada. Se invirtieron las posiciones para que exija el punto fijo. Lo encontró la mutación.

- [x] **Paso 4.0c — tokenizar `site.css`** (CERRADO 2026-08-14). Primera mitad, la de
      riesgo cero: todas las sustituciones son **equivalentes por construcción** —un script aborta
      si el token no vale EXACTAMENTE el literal que sustituye— y solo dentro de las reglas del
      sidebar. Nace la escala `--fw-*` (medida: 51 usos con solo 5 valores, tres cubren 49), se
      empiezan a usar los `--r-pill`/`--r-sm` que **ya existían sin usarse**, y 10 de los 13 colores
      crudos pasan a token (6 alfa de `--fg`, 2 `--bg-soft`, 1 `--err`, 1 `--warn`, comprobados por
      aritmética RGB). Tokenización de propiedades TEMATIZABLES: **43% → 49%**; colores crudos:
      **13 → 3**. Red: `SidebarTokenBudgetTest`, presupuesto que solo puede mejorar.
      **2.ª mitad hecha (2026-08-14, `DECISIONES #42`): 49% → 75%.** ⚠️ Y el plan de arriba estaba
      equivocado en lo esencial: «decidir una escala» habría movido el **52-55%** de los tamaños de
      letra (medido: 40 de 73 usos, 47px de desviación) y el 25% del espaciado. Eso es **rediseño
      visual**, que el spec §2 declara FUERA de alcance. La salida es una escala **multiplicativa
      sobre una unidad** —`--fs-13: calc(var(--fs-unit) * 13)`—, que da las dos cosas que parecían
      incompatibles: cero píxeles movidos y un punto de control real (cambiar `--sp-unit` airea el
      cajón entero conservando proporciones). Solo entran los valores que se REPITEN: 12 escalones de
      tipografía y 17 de espaciado cubren 202 de 210 literales.
      La revisión «a ojo» se sustituye por algo más fuerte: revertir los tokens a sus literales
      devuelve los dos CSS **byte a byte idénticos** a los originales.
      ⚠️ **La verificación destapó un fallo REAL preexistente en producción**: un comentario de
      `site.css` se cerraba a media frase por una pareja asterisco-barra dentro del texto, el resto
      pasaba a leerse como selector y el navegador **descartaba la regla siguiente** —`.gf-sr-only`,
      la que oculta el texto para lectores de pantalla en la hoja del post-form—, así que el aviso
      «has completado X de N fichas» **se veía**. Corregido, con guarda propia en
      `SidebarTokenBudgetTest` (ningún selector puede contener un cierre de comentario ni ser prosa).
- [x] **Paso 4.1 — cimientos SPA** (2026-08-14, `DECISIONES #43`). **Sin negocio**: dependencias
      (Vue 3 + Pinia), montaje, cliente HTTP, máquina de estados y las redes que la fase necesita
      antes de transcribir un paso.
      · **El motor se carga al ABRIR, no con la página**: `import()` dinámico como ya hacía
        `html2canvas`. Medido: el enganche cuesta **medio kB** en la landing (15,8 → 16,3 kB) y el
        motor son 69 kB en chunk propio. Con el flag activo se enviarían si no los DOS motores.
      · **Nace el techo de bundle**, que el repo no tenía (`SidebarBundleBudgetTest`, CE-7). Lo que
        de verdad vigila es que el chunk SIGA existiendo: un `import` estático en `app.js` lo funde
        con el entry y la landing engorda sin que el diff lo enseñe. Verificado por mutación — con
        el import estático el entry salta a 85 kB y caen cuatro guardas.
      · **La máquina de estados es un módulo JS plano** (`resources/js/sidebar/machine.js`), sin un
        `import` de Vue, probado con `node --test` (15 casos). No es purismo: la del Livewire tiene
        seis ficheros de test detrás y transcribirla sin red sería pérdida neta de cobertura (CE-6).
        `npm run test:js` entra en el `pre-push`.
      · **Flag `sidebar.engine`** con fallback ASIMÉTRICO a `livewire`: un typo en el panel no puede
        dejar la web sin la única superficie que vende.
      · ⚠️ **Con la SPA, el que CONSUME el desenlace del pago es el layout** — el matiz que 4.0a
        dejó anotado. `SidebarEntry::consume()` está memoizado por petición, así que el `peek()` del
        `<body>` sigue viendo lo suyo.
      · ⚠️ **`SidebarDomContractTest` NO entra en este paso**: compara el árbol de los dos motores
        paso a paso, y sin negocio el motor SPA solo emite el andamio. Nace con **4.2**, el primer
        paso transcrito, que es cuando empieza a poder romperse.
      · ⚠️ **Dos fallos los encontró la verificación, no la lectura**: una guarda propia buscaba
        `node_modules/vue/` y **pasaba sin mirar nada** (un build de producción no conserva las rutas
        de origen), y un test del flag fallaba porque **Livewire memoiza que ya emitió sus assets** y
        ese estado estático sobrevive entre peticiones del mismo test (familia `SUITE-02`).
- [x] **Paso 4.2 — pasos 1–3 (CERRADO 2026-08-14, `DECISIONES #44`–`#46`).** Entra el
      **catálogo** con su paridad DEMOSTRADA, y con él la red que sostiene toda la transcripción:
      `SidebarDomContractTest` compara el ÁRBOL renderizado de los dos motores en el gate y **sin
      navegador** —`@vue/server-renderer` viene con Vue; Node no carga `.vue` sin compilar, así que
      el renderizador se construye con Vite y `npm run build:ssr` entra en el `pre-push`—.
      · **Se normaliza el andamiaje de cada motor y se conserva lo que el CSS mira**: etiqueta,
        clases, anidamiento y accesibilidad. Dentro de un `<svg>` no se desciende (es geometría), pero
        que HAYA un `<svg>` sí se comprueba: de eso dependen selectores como `.catalog__go svg`.
      · **El test trae su propia guarda**, porque un diff que normaliza de más pasa siempre.
        Verificado por mutación sobre el componente real: un `<div>` donde el Blade pone `<button>`, o
        una clase renombrada, ponen el diff en rojo con la línea exacta.
      · ⚠️ **Lo que la paridad obligó a copiar y no se habría adivinado**: los iconos son componentes
        Blade que envuelven su SVG en un `<span class="icon …">`, y ese envoltorio ES contrato.
      **Paso 2 hecho (2026-08-14, `DECISIONES #45`)**: calendario + banda de progreso, con paridad de
      árbol Y de composición. La rejilla la compone el cliente (`calendar.js`, módulo plano) porque
      repartir días en semanas es presentación; qué días se ofrecen lo sigue diciendo `SlotOffer`.
      ⚠️ **El diff de árbol NO habría visto una rejilla mal compuesta** —allí a Vue se le pasa el
      view-model del servidor—, así que `SidebarCalendarParityTest` compara las dos composiciones
      dato a dato. Y sus dos casos frontera salieron de MEDIR, no de razonar: un mes que empieza en
      domingo, y el huso del navegador (`new Date('YYYY-MM-DD')` es UTC, y al oeste es la víspera).
      La primera versión del caso de husos **pasaba con el bug dentro**: el desfase solo mueve el
      lunes de la semana si el día 1 ya era lunes.
      **Paso 3 hecho, y con él 4.2 CERRADO** (2026-08-14, `DECISIONES #46`): hora, cantidad, campos
      del pack y complementos.
      ⚠️ **El paso destapó el límite estructural del diff de árbol**: compara lo que emite cada motor,
      pero alimenta a Vue con datos del SERVIDOR, así que **no ve** lo que el cliente recibe de otra
      fuente ni lo que compone él. Mordió dos veces: los complementos llegaban con los nombres del
      view-model de Livewire y no con los del endpoint —diff verde, cajón real con filas vacías—, y
      la acotación del selector no se comprobaba porque la cantidad no estaba en sus topes.
      · **Regla para el resto de la fase**: si el cliente recibe un dato de la API o lo compone él,
        hace falta una paridad de DATOS aparte. Ya van dos: `SidebarCalendarParityTest` y
        `SidebarAddonsParityTest` (endpoint ↔ view-model, campo a campo).
      · **Confirmado con datos reales**: en el pack sembrado `available` = 60 y `max_quantity` = 20.
        No son el mismo número (`AFORO-02`).
- [x] **Paso 4.3·1 — el armazón y los cimientos de texto** (2026-08-14, `DECISIONES #47`). El paso
      4.3 «cesta y presupuesto» resultó demasiado grande para un commit y se parte en tres, como se
      partió 4.2: **·1 el armazón · ·2 el pie · ·3 la cesta**. Este tramo **no transcribe ningún paso
      nuevo**: cierra lo que 4.2 dejó abierto sin que nadie lo viera.
      · ⚠️ **El motor SPA no emitía NADA del armazón y los nueve casos del gate salían verdes**: sin
        velo de carga, sin banda de progreso y sin la zona scrollable. La causa es estructural —**todos
        los casos anclan DENTRO** (`catalog-acc`, `wiz__title`)—, así que nunca miraban a los hermanos
        de arriba. Nace `Shell.vue` (SSR-renderizable: todo por props, sin `document` ni `window`) y su
        caso ancla en `.jj-loading` **con hermanos**, que es la única forma de comparar también el
        ORDEN — de él dependen selectores de adyacencia. Verificado por mutación ×3.
      · ⚠️ **La banda de progreso estaba escrita, verde en el gate y NO se pintaba**: `Sidebar.vue` le
        pasaba `progress: null` y `TimeStep` ni la importaba, o sea que el cajón vivo **no tenía
        «Volver» ni contador de fases en ningún paso**. Es el límite del diff de árbol otra vez
        (`#46(a)`). La composición baja a `progress.js` con paridad de datos en sus tres estados.
      · ⚠️ **`#sidecart-spa` partía la cadena flex del panel** y este paso es el que lo destapa: Vue
        monta DENTRO del hueco, ese `<div>` queda entre `.sidecart__body` y `.purchase` y **no tenía ni
        una regla CSS** (medido: 0 coincidencias). Ningún diff de árbol puede verlo, así que la regla
        viene con guarda ejecutable verificada por mutación.
      · **Los importes: tres copias, una rota y el gate ciego.** El normalizador descarta los nodos de
        texto, así que «1000,00 €» frente a «1.000,00 €» pasaba verde. Y las dos salidas obvias de JS
        fallan las dos: `toFixed` no agrupa nunca e `Intl.NumberFormat('es-ES')` no agrupa **entre
        1.000 y 9.999** (`minimumGroupingDigits: 2`), o sea que arregla las cifras grandes y rompe las
        que más aparecen en una cesta. Nace `money.js` y su barrido contra `number_format`, que
        **encontró un fallo en la primera ejecución**: el signo se decide sobre el RESULTADO
        (`number_format(-0.05, 0)` es «0», no «-0»).
      · **El diccionario se leía mal de tres formas**: cuatro de las 121 claves son SUBARRAYS
        (`errors`, `paused`, `statuses`, `payment_failed`) y se leían por clave literal → error pintado
        **vacío**; `String.replace` sustituye solo la primera aparición y `cart_items` lleva `:count`
        dos veces; y `cart_items` es la única clave con pluralización de Laravel, servida cruda.
        ⚠️ El selector de plural **no es `n === 1`**: en francés el CERO cae en el singular.
      · **El «modo» del paso de PAGO divergía** (`modeOf(8)` decía `result`, el servidor dice `cart`) y
        su clase se pinta FUERA del cajón, así que ningún árbol la alcanzaba. Fijado recorriendo el
        mapa ENTERO, que es como apareció.
      · **La divergencia de fechas de §4.5 queda ACOTADA**: con el patrón fijado por nosotros —un
        preajuste de `Intl` invierte día y mes en inglés— `en` y `fr` salen **idénticos** y solo el
        español difiere en la ortografía de la abreviatura. En `DEUDA.md` con su forma de cierre.
      · **Red**: 3 paridades PHP↔JS nuevas (`SidebarMoneyParityTest`, `SidebarTextParityTest`,
        `SidebarProgressParityTest`), 2 casos nuevos de diff de árbol, 1 guarda de CSS y 22 casos de
        `node --test`. Suite **2622 verde**; chunk del cajón 95,5 kB de 120.
      · ⚠️ **Lo que NO cierra**: el pie no existe todavía, así que el cajón sigue **auto-avanzando**
        del calendario a la hora donde Livewire exige «Continuar». Eso es 4.3·2.
      · ❗ **Quedó declarado y lo cierra 4.3·3: el aviso de reservas EN PAUSA.** Verificado: la SPA no
        consulta `GET /booking/status` (que existe desde 4.0b). En el Blade la misma condición
        gobierna la banda, la banda de pago y el pie, y sustituye el contenido scrollable entero por
        `.purchase__maint` en los pasos 1-5 y 8. Con la pausa activa el cajón SPA seguiría vendiendo
        mientras Livewire enseña el aviso. **No es fuga de dinero** (`ReservationAdmissionPolicy`
        rechaza en servidor), pero **ningún test puede cazarlo**: ningún caso de `tests/Feature/Sidebar`
        siembra la pausa. Se reasignó a 4.3·3: el pie de 4.3·2 comparte su guarda y, al transcribirlo,
        la divergencia pasó de «falta una pantalla» a «el cajón SPA ofrece pagar durante la pausa».
- [x] **Paso 4.3·2 — el pie y la cesta en memoria** (2026-08-14, `DECISIONES #48`). Entra
      `.bk-foot` con sus TRES árboles, el paso 4 y el flujo que los une. La cesta vive **en memoria**;
      la persistencia en `localStorage` es 4.3·4, y `cart.js` nace ya sin tocar el almacén.
      · **El pie no se podía partir de la cesta**, y ese fue el corte real: el CTA del paso 3 es
        «Añadir al carrito» y `disabled` **es un atributo que el diff compara**, así que dejarlo
        inactivo ponía el gate en rojo y dejarlo activo sin cesta era un botón mudo.
      · **El séptimo hueco de API era otro, y este sí existía**: el endpoint de complementos construía
        un `CartQuote` completo y **tiraba sus totales**, dejando al cliente sin más salida que sumar
        `subtotal_cents` + `addons_total_cents` — dos recorridos DISTINTOS del servidor. Se publica
        `line.total_cents` desde el mismo presupuesto (`PAY-12`: una sola fuente de CÁLCULO).
      · ⚠️ **Dos rótulos que el diff daba por buenos**: el desglose es «Pagas ahora (señal)» en el paso
        3 y «Pagas ahora» —NEUTRO— en la cesta, porque en una cesta mixta no todo lo que se cobra ahora
        es señal (#232). El normalizador descarta el texto: lo fija `SidebarCartParityTest`.
      · **La cesta ya viaja en la consulta de horas** (`AFORO-02`). Iba `items: []` desde 4.2 con un
        comentario que decía que la clave estaba puesta «para que no se olvide»: este era el momento.
      · ⚠️ **Un caso del gate pasaba con el fallo dentro, y lo dijo la mutación**: `.cart__lines` se
        emite SIEMPRE —el condicional del Blade está DENTRO del `<div>`— y el caso de la cesta llena no
        lo detectaba porque todas sus líneas tienen fecha. El estado es alcanzable y se midió:
        `Cart::sanitize()` conserva `date: ''` y el presupuesto **la tarifica igual**. Tiene caso propio.
      · **La baseline del armazón ENCOGIÓ sola**: `SHELL_BLOCKS_NOT_YET_IN_SPA` pasa de dos entradas a
        una porque el test cayó al transcribir el pie. Es el patrón de baseline que solo mengua,
        aplicado al marcado.
      · **Red**: 6 casos nuevos de diff de árbol (cesta llena, cesta vacía, línea sin fecha, pie en sus
        cuatro estados, ausencia de pie), `SidebarCartParityTest` (5 casos: pie del paso 3, pie de la
        cesta mixta, ausencia, filas con HUECO de índice y emparejado de respuestas del pack) y 24
        casos de `node --test`. **Verificado por mutación seis veces**: popover con `v-if`, icono sin
        envoltorio, `.cart__lines` condicional, formateador sin millares, rótulo del desglose
        intercambiado y emparejado por posición — los seis en rojo.
      · Suite **2633 verde**; chunk del cajón 105,7 kB de 120.
      · ⚠️ **Lo que NO cierra**: (1) «Ir a pagar» no lleva a ninguna parte (el paso 5 es 4.4a); (2) la
        cesta no sobrevive a una recarga (4.3·4); y (3) **el aviso de reservas EN PAUSA sigue sin
        existir, y ahora pesa más**: el pie comparte su guarda, así que con la pausa activa el cajón
        SPA enseña una barra de «Ir a pagar» donde Livewire enseña el aviso de mantenimiento. Es la
        divergencia más visible que la fase tiene abierta. La cerró 4.3·3.
- [x] **Paso 4.3·3 — el cajón deja de vender durante la pausa** (2026-08-14, `DECISIONES #49`).
      Cierra la divergencia que 4.3·2 dejó declarada.
      · **La pausa apaga CUATRO bloques, no uno**: el contenido scrollable, la banda, el pie y la banda
        de desglose del pago. ⚠️ Y la guarda va en la VISTA: con la pausa activa el servidor **sigue**
        componiendo `bookingProgress()` y `footer()` no nulos, así que anularlos en los módulos habría
        puesto en rojo las paridades que los comparan campo a campo.
      · ⚠️ **Tapa SEIS pasos y no se puede derivar**: `[1,2,3,4,5,8]`. Los de RESULTADO rinden
        normales —son acciones YA iniciadas—, así que un `v-if="paused"` en la raíz taparía el «pago
        confirmado» de quien acaba de pagar. Se compara el mapa ENTERO, que es como apareció la
        divergencia del «modo» en 4.3·1.
      · ⚠️ **El título y el mensaje NO son literales de i18n**: son ajustes del panel POR IDIOMA, y sin
        override coinciden EXACTAMENTE con el literal — o sea que el fallo sale idéntico en desarrollo
        y solo se ve en la instalación que haya escrito el suyo. Tiene caso propio.
      · **Los canales van a la vez y `contact_url` llega ya decidido** (`#38(g)`): el cliente pinta lo
        que no sea nulo y no evalúa nada.
      · ⚠️ **El estado se RELEE en cada apertura**, y eso es lo que de verdad cierra la divergencia: el
        motor se monta una sola vez por carga de página, así que leerlo solo en `onMounted` habría
        sido el mismo snapshot que el endpoint existe para evitar. **Residual declarado**: un cajón ya
        ABIERTO cuando se acciona el interruptor no se entera hasta reabrirlo.
      · **Tres huecos de red que encontró la revisión adversarial, los tres con su mutante**: el caso
        de árbol recomponía el aviso EN PHP (poner los canales en cascada dejaba la suite entera
        verde); `primary`/`external` —el color del botón principal y el `rel="noopener"`— no los
        comparaba nadie; y **nada ejecutaba `Sidebar.vue`**, así que borrar la llamada a
        `/booking/status` pasaba en verde y devolvía el cajón a vender en pausa con la casilla marcada.
        Nace una guarda sobre el chunk CONSTRUIDO, del tipo de la que vigila que Vue no viaje con la
        landing.
      · **Red**: 2 casos de árbol (el aviso sustituyendo el flujo entero; los seis pasos tapados con
        sus tres bloques ausentes), `SidebarPausedParityTest` (6 casos: mapa de once pasos, los cuatro
        estados de canales con sus enlaces, las dos formas del teléfono, el override del panel y los
        tres idiomas), 12 de `node --test` y la guarda del chunk. **Verificado por mutación 8 veces.**
      · Suite **2642 verde**; chunk del cajón 107,3 kB de 120.
- [x] **Paso 4.3·4 — la cesta sobrevive a la recarga** (2026-08-14, `DECISIONES #50`). Cierra el paso
      4.3 y ejecuta `DECISIONES #38(d)`: la cesta pasa a `localStorage`, **sin `event_data`** y con su
      dueño dentro.
      · **La identidad sale del SERVIDOR por dos canales**: `userId` en el `data-boot` (12 bytes, con
        el HTML) y `GET /me` al abrir el cajón y al oír `logged-in`. Se descartó que el id viajara en
        el evento —`dispatch('logged-in')` va sin payload— porque una defensa de seguridad no puede
        colgarse del bus de eventos del navegador. ⚠️ Un fallo de red NO es un logout: solo el 401.
      · ⚠️ **La purga tiene CINCO casillas y una no existe en el servidor**: (X → anónimo) → PURGAR.
        En sesión el logout vacía cesta y marcador a la vez; `localStorage` no tiene `invalidate`, así
        que esa casilla es la fuga que introduce la persistencia y la más probable en una tablet. Las
        otras cuatro espejan al servidor, **incluida la que sostiene el flujo principal**: una cesta de
        invitado sobrevive al login.
      · ⚠️ **El saneador espeja `CartPayload`, NO `Cart::sanitize()`, y DESCARTA en vez de corregir.**
        Los dos saneadores del servidor son opuestos: uno convierte `qty: 0` en 1 —una compra que nadie
        pidió, con precio— y el otro tira el cuerpo entero con un 422 —cajón inservible por UNA línea
        mala, y sin botón para quitarla—. La síntesis la documenta el propio `CartPayload`.
      · **Caducidad propia**: medido, el presupuesto tarifica con importes completos una fecha de hace
        19 meses. La sesión caducaba a los 120 minutos; `localStorage` no caduca nunca.
      · ⚠️ **Reconciliar y re-presupuestar son UNA operación**: podar desplaza los índices, y las filas
        y el botón de quitar se emparejan por el `index` del presupuesto. Y se poda por dos criterios,
        no uno: el hueco de `index` **y** `unit_price_cents: null` (el bug P8 por otra puerta).
      · **El CANARIO del RGPD**: el doble de almacén graba todas las escrituras de cualquier clave y se
        buscan centinelas en el volcado entero — mirar la clave `event_data` de la primera línea lo
        pasarían en verde cuatro mutaciones distintas.
      · **Dos huecos de red que encontró la revisión**: las guardas de `toApiItems` no estaban probadas
        (quitarlas dejaba la suite JS entera verde y el módulo lanzaba con la primera cesta restaurada),
        y **`npm run test:js` solo alcanza un nivel de carpeta** — un test en una subcarpeta no correría
        nunca y la suite diría «pass». Los dos cerrados con guarda propia.
      · **El ORÁCULO DIFERENCIAL** es la pieza central de la red: un corpus de 22 líneas pasa por el
        `Validator` REAL con `CartPayload::lineRules()` y por el saneador en Node, y se comparan los
        veredictos. Verificado por mutación ×3, nombrando el caso exacto que diverge.
      · **Red**: 27 casos nuevos de `node --test` (saneador, tabla de purga, restaurar, guardar con
        canario, reconciliar), 3 de paridad diferencial, el `userId` del montaje y la guarda del glob.
      · Suite **2647 verde**; chunk del cajón 111,0 kB de 120.
      · ⚠️ **Lo que NO entra**: una línea de PACK restaurada vuelve **sin sus respuestas** y el servidor
        la rechazará al crear el pedido. El camino para volver a rellenarla no se construye porque **no
        se puede recorrer** —«Ir a pagar» no lleva a ninguna parte hasta 4.4a—; queda como precondición
        del paso de pago, con el dato ya disponible (al restaurar se piden los `event_fields`).
- [x] **Paso 4.4a·1 — el CTA de pagar aprende quién eres y si puedes reservar** (2026-08-14,
      `DECISIONES #51`). El paso 4.4a se parte en dos por el mismo criterio que 4.2 y 4.3 —la
      DEPENDENCIA—: de las **cinco** salidas de `Purchase::checkout()`, medidas una a una antes de
      escribir nada, solo dos tienen pantalla hoy (el aviso de admisión denegada y el cartel de pausa
      se pintan en el paso 4, ya transcrito). Las otras tres llevan a los pasos 5 y 8, que son 4.4b y
      4.5.
      · **Se transcribe la DECISIÓN, no la navegación**: el CTA sigue mudo donde ya lo estaba, y las
        dos salidas que sí se ven quedan cerradas. Navegar a un paso sin transcribir dejaría el cajón
        **en blanco**, que es peor que un botón que no responde.
      · ⚠️ **Son DOS preguntas y la segunda no es la obvia**: `GET /me/reservation-eligibility` da el
        aviso temprano, pero `GET /me` es el que sostiene la seguridad — con la cesta en
        `localStorage`, este clic es **el único momento** en que el cajón puede enterarse de que la
        sesión cambió en OTRA pestaña (`logged-in` es del mismo documento; el `userId` del montaje es
        de la carga de la página). Van **en paralelo**, y hay caso que lo mide contando peticiones en
        vuelo. La identidad se aplica **antes** del veredicto: si el titular cambió no hay compra que
        continuar.
      · ⚠️ **La PAUSA no se enseña como error de carrito, y se MIDIÓ**: Livewire escribe
        `errors.reservations_paused` en su bag y **nunca se pinta** —`showPausedNotice()` sustituye el
        flujo entero—. El veredicto de pausa pide **releer `GET /booking/status`** en vez de componer
        mensaje, y de regalo **cierra el residual de 4.3·3**: un cajón ya ABIERTO cuando se acciona el
        interruptor ya no espera a que lo cierren para enterarse.
      · **La secuencia vive en `admission.js`, no en el `.vue`** (`CE-6`), con `api` y `applyIdentity`
        inyectados como `cart.js` recibe el almacén: un árbol no dice a quién se preguntó ni en qué
        orden, así que esa lógica dentro del componente no tendría red.
      · **Red**: 31 casos de `node --test` (decisión, degradados, secuencia con dobles) y
        `SidebarAdmissionParityTest`, que recorre las **cinco** situaciones comparando el paso destino
        con el del componente Livewire y los avisos **palabra por palabra en los tres idiomas**, con
        las respuestas **REALES** de la API. El cableado lo vigila `SidebarBundleBudgetTest` sobre el
        bundle construido. **Verificado por mutación 6 veces** y en vivo con `curl`.
      · Suite **2652 verde** · 139 tests JS · chunk del cajón 112,3 kB de 120.
- [x] **Paso 4.4a·2 — la pantalla de identificación** (2026-08-14, `DECISIONES #52`). Cierra 4.4a: el
      invitado que pulsa «Ir a pagar» ya llega a su pantalla, y el login habla con
      `POST /api/v1/auth/login` —el mismo `PasswordLogin` que el modal de la web—.
      · ⚠️ **El árbol del paso 5 son 31 nodos, y llegar por el camino equivocado enseña 9**: con
        `->set('authMode', …)` Livewire deja el hijo `<div wire:name="auth.login"></div>` **VACÍO** y el
        diff habría comparado armazón contra armazón — un motor SPA sin formulario pasaba en verde. El
        caso llega pulsando la pestaña, y un segundo caso fija el hecho medido.
      · ⚠️ **Los dos motores decían cosas DISTINTAS para el mismo rechazo** (`auth.failed` vs
        `invalid_credentials`, y lo mismo con el limitador). El cajón ramifica sobre el CÓDIGO y pinta
        el literal del diccionario; pintar el `message` habría cambiado la copia en las tres lenguas
        sin que ningún gate lo dijera. La validación sí se pinta tal cual: los dos motores usan las
        mismas reglas y sus textos ya coinciden.
      · **El montaje inyecta `account` PODADO y `auth`**: el grupo entero son 9,6 kB —tanto como
        `tickets`— para pintar diez rótulos, y viajaría en cada página pública. Medido en vivo: 538 B.
        Guarda de que lleva todo lo que el paso pinta (una clave que falte se pinta VACÍA) y de que
        sigue podado.
      · **Al entrar pasan tres cosas**: se avisa a Livewire (`logged-in`, que es lo que hace repintar
        `account-context` fuera del cajón), se aplica la identidad con la respuesta del propio login
        —trae el perfil con la forma de `GET /me`— y se continúa el checkout, como `onAuthenticated()`.
      · **El techo del bundle sube de 120 a 135 kB**, medido: el paso costó 9,97 kB y casi todo es
        runtime de formularios de Vue que entra por primera vez. Con 120 el margen quedaba en 0,30 kB.
      · **Red**: 20 casos de `node --test` (reparto de avisos, validación, envío), `SidebarLoginParityTest`
        (textos en los tres idiomas contra el componente Livewire real + el payload del montaje) y dos
        casos nuevos de árbol. **Verificado por mutación 3 veces** y en vivo con el flag activo.
      · Suite **2660 verde** · 158 tests JS · chunk del cajón 122,6 kB de 135.
      · ⚠️ **Lo que NO cierra**: el registro embebido es 4.4b y el pago 4.5, así que quien se identifica
        **se queda en el paso 5** —navegar al 8 dejaría el cajón en blanco—. `TRANSCRIBED_STEPS` declara
        en el código a qué pasos se puede navegar y **solo crece**.
- [x] **Paso 4.4b·2 — el widget del anti-bot en el cajón** (2026-08-20, `DECISIONES #108`). Retira la
      delegación en el modal de auth de la cabecera: el cajón monta su propio Turnstile.
      · La lógica va a `resources/js/sidebar/turnstile.js`, **módulo plano** (`CE-6`) — y a ese NIVEL a
        propósito: `js/sidebar/*.js` es el único glob que vigila la rancidez del bundle SSR, así que una
        subcarpeta habría escapado a la guarda y el diff de árbol daría **verde con el widget roto**.
      · ⚠️ **El contenedor va con `v-if` y PELADO**, y las dos cosas son contrato de árbol: el Blade lo
        envuelve en `@if ($turnstileEnabled)` y la suite **nunca siembra las claves**, así que un `<div>`
        incondicional —o con clase— pone en rojo el diff Y el manifiesto congelado a la vez.
      · ⚠️ **Tres cosas que NO son copia-pega del motor Livewire**: el guard `__cfTurnstileLoading` se
        comparte pero **no sirve para cortar** (el modal de la cabecera va *eager*, así que ya vale
        `true` cuando el cajón abre: cortar por él dejaría el widget sin pintar SIEMPRE) · se guarda el
        `widgetId` para poder **resetear** (el token es de un solo uso y el servidor lo quema antes de
        mirar si el correo existe) · y **no se rinde en silencio**.
      · ⚠️⚠️ **El modo de fallo más probable es INVISIBLE**: `Turnstile::verify('')` corta antes del POST
        y antes de su `Log::warning`, así que un widget que no se pinte da un 422 con **cero rastro** en
        los logs y **cero** en el panel de Cloudflare. Por eso el aviso por consola no es cosmética.
      · **Medido mutando (12 mutaciones), y salieron DOS cosas inertes, las dos propias**: un test que
        cubría una guarda inalcanzable, y un CENTINELA de bundle (`turnstile_token`) que **no
        discriminaba** porque el identificador vive también en el cableado del formulario — la misma
        trampa `/payment` vs `/payment-status` que ese fichero documenta. Detalle en `#108(e)`.
      · **Coste: 1,76 KiB de bundle; quedan 1,76.** El ledger del presupuesto llevaba caduco desde
        4.6·2. Y `Sidebar.vue` ENCOGIÓ: 618 → 614.
      · ⚠️ **Lo que NO cierra**: contra Cloudflare real no lo ha visto nadie. El reset está probado con
        dobles, no con el proveedor. Va a la sesión de navegador (`VERIFICACION-E2E-CAJON.md`).
- [x] **Paso 4.4b·1 — el alta desde el cajón** (2026-08-14, `DECISIONES #53`). El paso 5 pinta su
      formulario de registro —51 nodos— y habla con `POST /api/v1/auth/register` con
      `context: purchase`, la política **pay-first** que el servidor ya conocía. Entra también el paso 7.
      · ⚠️ **Turnstile se aplaza a ·2 porque NO SE PUEDE VERIFICAR** (exige claves de Cloudflare y
        navegador), y en su lugar entra la **guarda**: `GET /config` dice si el alta exige captcha y, si
        lo exige, el cajón delega en el modal de Livewire, que sí monta el widget. Sin ella, activar el
        anti-bot con el motor SPA puesto dejaba un registro que **rechaza a todo el mundo**, sin log.
      · ⚠️ **Tres bugs de SERVIDOR destapados por el primer cliente real** (los tres con su regresión):
        (1) `/config` publicaba la clave del anti-bot aunque faltara la secreta —estado en que la web no
        pinta el widget y el servidor no verifica nada—; (2) el señuelo VACÍO, que es lo que manda todo
        cliente legítimo, provocaba un **422 sobre un campo que el usuario no ve** (`ConvertEmptyStringsToNull`
        + `sometimes|string`); (3) las dos puertas del alta **decían cosas distintas al usuario**, porque
        el componente declara `validationAttributes()`/`messages()` y el controlador no.
      · ⚠️ **El 201 no dice si hubo cuenta**, y es a propósito: distinguirlo delataría el señuelo. El
        cajón pregunta `GET /me` después — con sesión sigue la compra, sin ella va a «revisa tu correo».
        Verificado en vivo: las dos respuestas son byte a byte idénticas.
      · **Tres nodos invisibles que solo vigila el diff**: el honeypot (`.hp`), la fila `.form__row` de
        email+teléfono y el `<small class="form__hint">`. Un motor sin honeypot deja al servidor sin su
        señuelo y no se nota mirando la pantalla.
      · **Red**: 23 casos de `node --test`, `SidebarRegisterParityTest` (literales de negocio y
        validación en los tres idiomas, pay-first, indistinguibilidad del señuelo, payload y la guarda
        del captcha de punta a punta) y tres casos nuevos de árbol —incluido el banner de errores, que
        se compara con el formulario VACÍO porque con un campo en rojo un `<li>` de más no se vería—.
      · Suite **2673 verde** · 176 tests JS · chunk del cajón 131,7 kB de 135.
      · ⚠️ **Lo que NO cierra**: el widget de Turnstile (·2) y el pago (4.5). Quien crea su cuenta con
        la cesta lista se queda en el paso 5, igual que quien inicia sesión.
- [x] **Paso 4.5·1 — la cesta restaurada pide lo que le falta** (2026-08-14, `DECISIONES #54`). Cierra
      el bloqueante que el ESTADO llevaba tres pasos declarando y ejecuta `#38(d)`, que lo había
      decidido sin construirlo: «al restaurar, las líneas de pack piden esos campos otra vez».
      · ⚠️ **El problema no avisaba**: medido, `POST /orders/quote` tarifica la línea sin respuestas
        —200, con su total correcto— y solo `POST /orders` la rechaza con 422 `line_event_required`. El
        cliente veía una cesta perfecta y se chocaba **en el botón de pagar**, sin pantalla donde
        arreglarlo.
      · **Se eligió PEDIR, no descartar.** Persistir las respuestas se descartó con el owner tras medir
        dónde quedarían: `localStorage` en texto plano, sin caducidad real, legible por cualquier JS del
        mismo origen y **fuera del alcance de `User::anonymize()`**, con un agujero que ninguna purga
        tapa (el invitado que se va). Descartar la línea se descartó porque perder un cumpleaños
        configurado por recargar es peor que reescribir un nombre. ⚠️ Medido en vivo: del pack sembrado
        solo `celebrant` es obligatorio, así que se vuelve a pedir **un** campo.
      · ⚠️ **La frontera de `#38(f)`: el cliente ENUMERA, el servidor DECIDE.** El módulo mira si hay
        algo escrito, nada más — `sanitizeEventData()` deja solo los dígitos en los `number`, así que una
        edad «cinco» el servidor la ve VACÍA y el cliente no puede saberlo sin copiar la regla. Hay caso
        que fija las dos mitades a la vez.
      · **Los campos pedidos son los que el servidor exige**, comparados contra
        `missingRequiredEventFields()` con el esquema real del catálogo; y los de POST-FORM no se piden
        nunca aunque sean obligatorios (hay caso con uno).
      · **Red**: 12 casos de `node --test` y `SidebarPendingFieldsParityTest` (el quote que no avisa, la
        paridad de campos, la fase, y la frontera del «cinco»). Verificado por mutación ×2 y en vivo.
      · Suite **2678 verde** · 189 tests JS · chunk del cajón 133,1 kB de 135.
- [x] **Paso 4.5·2 — el cajón ya VENDE: pagar y salir a la pasarela** (2026-08-14, `DECISIONES #55`).
      Transcribe los pasos 8 y 9. El motor SPA recorre ya el embudo entero.
      · ⚠️ **EL HALLAZGO: el diff de árbol NO puede verificar el paso 9, y se demostró por mutación.**
        `action`, `method` y los `name` de los campos **no son atributos de contrato**, así que
        renombrar los campos firmados —lo que rompe el cobro con SIS0042, con el pedido ya creado y el
        aforo retenido— **pasa el gate en VERDE**. Nace `SidebarPayParityTest`, que los compara campo a
        campo contra la respuesta real de la API.
      · **Una sola petición**: `POST /orders` admite, crea con su hold y abre el cobro en ese orden
        —regla del dominio— y devuelve el formulario firmado en la misma respuesta. Partirlo habría
        reimplementado `CheckoutOrchestrator` en el cliente.
      · **`payment.fields` es un mapa OPACO**: el cajón itera y emite, sin conocer los nombres. Impide
        «normalizar» un valor que la firma cubre **y** deja el paso listo para el segundo driver de F6.
      · ⚠️ **El paso 8 se parece al carrito lo justo para equivocarse**: `cart--summary`, sin botón de
        quitar, precio en un `<span>` SIN clase y pie de aviso sin «añadir otra».
      · **A partir del 201 el pedido EXISTE**: un formulario mal formado no se trata como «no ha pasado
        nada» —se avisa y se conserva el código—, y la cesta se vacía y se persiste vacía para que una
        recarga no la resucite.
      · **La banda `bk-paybreakdown` sale de `SHELL_BLOCKS_NOT_YET_IN_SPA`** y la pausa cierra su último
        residual: `reservations_paused` pide releer el estado, que es lo que el contrato pedía tras un 409.
      · **El techo del bundle sube de 135 a 150 KiB**, medido: los dos pasos costaron 5,51 KiB y con 135
        el chunk se pasaba por 1,09. Quedan 13,9 KiB para 4.6 y Turnstile.
      · **Red**: 21 casos de `node --test`, `SidebarPayParityTest` (formulario campo a campo, el enum
        entero de errores y los textos en tres idiomas) y tres casos nuevos de árbol. **Verificado por
        mutación ×2** y en vivo con un pedido real (`R-KB8ONS`, borrado después).
      · Suite **2688 verde** · 205 tests JS · chunk del cajón 139,4 kB (136,1 KiB) de 150.
      · ⚠️ **Entre 4.5 y 4.6 NO se despliega el flag**: quien pague en medio vuelve a un cajón mudo, y
        ahora el cajón sí puede cobrar.
- [x] **Paso 4.6·1 — la vuelta de la pasarela pinta la reserva creada** (2026-08-14, `DECISIONES #56`).
      Transcribe el paso **6** y cierra la costura del desenlace por el lado del cliente. Corte por
      DEPENDENCIA: el sondeo del paso 11 aterriza en el 6, así que el 6 va primero.
      · ⚠️ **EL HALLAZGO: un test de cadena volvió a pasar sin probar la cadena.** El caso que afirmaba
        «cada respuesta del pack cae bajo SU reserva» **pasaba con el cliente emparejando por POSICIÓN**
        —medido por mutación—, porque hoy el endpoint devuelve las reservas en el mismo orden que las
        líneas y llave y posición coinciden por casualidad. El caso bueno **le da la vuelta al sobre** y
        exige el mismo resumen. Lo que tapaba: el nombre de un niño bajo la reserva de otro, con el árbol
        idéntico.
      · ⚠️ **Y la primera mutación tampoco valía**: `Object.values(answers)[i]` **pasa**, porque en JS
        las claves que parecen enteros se ordenan ascendentemente y el mapa se recolocaba solo. Una
        mutación que no rompe nada no demuestra nada.
      · **La fila del resumen se EXTRAE** a `SummaryLine.vue`: los pasos 6 y 8 emiten el MISMO árbol y la
        pregunta que decide es «¿hay dos copias?» (4.0b·5). Verificado por mutación: tocarla deja en rojo
        los dos pasos a la vez.
      · ⚠️ **El desenlace MANDA sobre la cesta**, y el orden natural de la SPA era el contrario: con la
        cesta en `localStorage`, otra pestaña puede haberla llenado mientras se pagaba en ésta y quien
        volvía de pagar aterrizaba en el carrito. La precedencia vive en `machine.js` (`isOutcome()`),
        no en el componente (`CE-6`).
      · **`confirmation: null` es un estado legítimo**: el Blade pinta la pantalla igual sin resumen, y
        es lo que ve quien perdió la sesión por el camino. Tiene su propio caso de árbol.
      · **Dos DIVERGENCIAS declaradas con caso**: la API acota las respuestas a la fase `booking`
        (§4.4.6) y publica el estado EFECTIVO (`displayStatus()`), así que un hold vencido sale
        `expired` donde el Blade dice `pending`.
      · **El techo del bundle NO sube**: el paso costó 4,70 KiB (140,79 de 150). Quedan **9,21 KiB**
        para 4.6·2 y Turnstile.
      · **Red**: 14 casos nuevos de `node --test`, `SidebarOutcomeParityTest` (7 casos: resumen campo a
        campo contra la API real, orden del sobre, enlace de registro y las dos divergencias) y 3 casos
        de árbol. **Verificado por mutación ×6** y en vivo con `sidebar.engine = spa` (payload de
        montaje, login por API a través de nginx y las dos respuestas reales pasadas por el módulo real).
      · Suite **2698 verde** · 219 tests JS · chunk del cajón 144,2 kB (140,8 KiB) de 150.
      · ⚠️ **El flag sigue sin desplegarse**: quien vuelva con un pago denegado o con un terminal
        *data-less* todavía se encuentra un cajón mudo (pasos 10 y 11 → 4.6·2).
- [x] **Paso 4.6·2 — los otros dos desenlaces: denegado y verificando** (2026-08-14, `DECISIONES #57`).
      Transcribe los pasos **10** (con su reintento) y **11** (con su sondeo). **La transcripción de la
      fase queda COMPLETA**: los once pasos existen en los dos motores.
      · ⚠️ **EL HALLAZGO: la máquina llevaba desde 4.1 con una transición INVENTADA.**
        `TRANSITIONS[DECLINED]` decía `[CATALOG, PAY]` y las dos mitades estaban mal, medido contra
        `retryPayment()`: el reintento sale **directo a la pasarela** (paso 9), no vuelve a la pantalla
        de pago, y faltaba la salida a IDENTIFICARSE (`$user ? 1 : 5`). Sin `DECLINED → REDIRECTING`, el
        reintento habría compuesto su formulario firmado y el cajón **se habría quedado quieto**, porque
        `go()` rechaza en silencio.
      · **El motivo del rechazo no necesita tabla**: `declined_reason` ES la clave de
        `tickets.payment_failed.reasons.*`, que ya viaja en el montaje. Lo que sí hace falta es la caída
        a `default` — sin ella, un motivo nuevo pinta el rótulo «Motivo:» **vacío**.
      · ⚠️ **El bloque del motivo se pinta SIEMPRE que hay sesión**, aunque el Blade parezca
        condicionarlo: `reasonText()` nunca devuelve null.
      · ⚠️ **El sondeo solo mira `paid` y `expired`**: un intento `failed` con el pedido pendiente **no
        mueve nada**, porque la notificación puede estar en vuelo. Ampliarlo diría «no has pagado» a
        quien sí pagó.
      · ⚠️ **El centinela obvio del bundle NO discriminaba**: `/payment-status` lo usan los dos pasos, así
        que desconectar el bucle dejaba el gate en verde. Medido: `setInterval` aparece una sola vez en
        el chunk y desaparece con él.
      · ⚠️ **Deuda de PRODUCTO destapada** (no creada): un reintento denegado deja el botón **mudo** en
        los dos motores — `errors.cart` no se pinta en el paso 10. Fila en `DEUDA.md`.
      · **Red**: 13 casos nuevos de `node --test`, 8 de `SidebarOutcomeParityTest` (los cuatro «no» del
        reintento provocados de verdad contra la API, el mapa entero de motivos en tres idiomas, el
        sondeo y los dos enlaces) y 3 de árbol. **Verificado por mutación ×7** y en vivo con
        `sidebar.engine = spa` (payload con sus `urls`, y las respuestas reales de `payment-status` y
        del reintento pasadas por el módulo real).
      · ⚠️ **Dos casos volvieron a pasar por casualidad** y se arreglaron midiendo: el titular
        equivocado tras la segunda compra y la pausa pegándose entre iteraciones.
      · Suite **2709 verde** · 232 tests JS · chunk del cajón 149,3 kB (145,9 KiB) de 150.
      · ⚠️ **El flag sigue sin desplegarse**, y ya no por falta de pantalla: falta el extremo a extremo
        con la pasarela en sandbox y navegador (§6) y el widget de Turnstile (4.4b·2).
- [x] **§6 · accesibilidad — el bloqueo de scroll tiene UN SOLO DUEÑO** (2026-08-14, `DECISIONES #58`).
      Cierra el último ítem del plan de verificación de la fase que seguía sin cumplirse. No era
      pulcritud: eran **seis escritores** de `body.no-scroll` en tres ficheros, y el fallo se alcanza con
      dos clics —cerrar el modal de auth abierto ENCIMA del cajón desbloqueaba el scroll con el panel
      todavía delante—. Nace `resources/js/ui/scroll-lock.js` (cerrojo con llaves, store `scrollLock`) y
      su guarda ejecutable `ScrollLockOwnerTest`.
      · **La llave es por INSTANCIA**: «Mis pedidos» pinta un modal por pedido; con una compartida,
        cerrar A soltaría el scroll con B delante.
      · **El estado inicial también es del dueño**, y ahí había una asimetría: el modal de auth abierto
        al cargar (`/registro`) no bloqueaba nada. De paso se retira el `x-init` del layout.
      · ⚠️ **Ampliar el contrato de árbol no basta**: `aria-current` entró en la lista y por mutación se
        vio que era INERTE —el caso del calendario no elige día—. Ahora hay un caso que sí.
      · ⚠️ **`aria-expanded` NO puede ser atributo de contrato**, medido: binding de Alpine descartado
        como andamiaje frente a atributo renderizado por el SSR de Vue. Tiene caso propio, y al hacerlo
        apareció que el cajón SPA **no anunciaba** el estado del desglose de la señal. Arreglado.
      · ⚠️ **Queda ABIERTO el foco al cambiar de paso**, que **ninguno de los dos motores** hace: hueco
        heredado, no regresión. Ficha en `DEUDA.md`.
      · **Red**: 9 casos de `node --test`, 3 de `ScrollLockOwnerTest` y 2 de árbol. **Verificado por
        mutación ×6** y en vivo: en el bundle SERVIDO, `no-scroll` aparece **una sola vez**.
      · Suite **2714 verde** · 241 tests JS · entry de la landing 16,24 KiB de 20.
- [x] **§6 · el EXTREMO A EXTREMO con navegador y pasarela real** (2026-08-14, `DECISIONES #59`).
      Se ejecutó por fin la verificación que la fase pedía desde el principio. **Encontró cuatro cosas y
      tres estaban rotas de raíz**: la fase se daba por transcrita, con paridades y mutaciones en verde, y
      **el motor SPA no funcionaba en producción**.
      · ⚠️ **EL FALLO MAYOR: el desenlace del pago no llegaba al STORE.** `index.js` aplicaba
        `machine.enterOutcome()` DESPUÉS de `store.boot()`, y el store no observa la máquina: la copia.
        Máquina en el paso 6, store en el 1, y Vue pinta del store → **quien volvía de pagar veía el
        catálogo**. Toda la 4.6, invisible. Arreglado + `store.test.js` (red que se podía tener desde 4.1).
      · ⚠️ **El motor no montaba con el cajón nacido abierto**: `bootSpaEngine()` colgaba solo de
        `open()`, y `/entradas` y la vuelta del pago abren el cajón sin llamarlo → hueco VACÍO.
      · ⚠️ **El catálogo SPA no enseñaba ni un producto**: falta `is-open` y el CSS colapsa el cuerpo.
        El diff no podía verlo —en el Blade la ponía un `:class` de Alpine, que el normalizador descarta—.
        Arreglado en el ORIGEN (clase estática en los dos motores), así que **ahora el gate SÍ la ve**.
      · **Divergencia declarada, no arreglada**: elegir día avanza solo en la SPA y no en Livewire, con
        lo que el CTA «Continuar» del paso 2 es inalcanzable. Ficha en `DEUDA.md`: es producto.
      · ✅ **Verificado en los DOS motores**: embudo entero contra la pasarela REAL, cobro de la **señal**
        (30 € de 165 €), vuelta al paso 6 y **pedidos IDÉNTICOS en BD**. Paso 11 con vuelta *data-less*:
        sondeo cada 5 s, salto solo al 6 y **parada** del sondeo.
      · **El andamio (Playwright) es desechable y NO entra en el repo ni en el gate**; la receta y las
        ocho trampas de la pasarela quedan en `VERIFICACION-E2E-CAJON.md` §5.bis.
      · Suite **2714 verde** · 247 tests JS.
- [x] **Paso 4.7·1 — el manifiesto de DOM, congelado** (2026-08-14, `DECISIONES #60`). No retira nada:
      pone la red que tiene que existir ANTES de retirar, y mide el paso.
      · ⚠️ **Toda la red de la fase compara contra Livewire, y Livewire se va**: al borrarlo, catorce
        paridades y el diff de árbol se quedan sin uno de los dos motores y **pasarían en verde para
        siempre**. La foto solo se puede tomar mientras conviven.
      · **Mientras convivan se comprueban las DOS cosas** (motor↔motor y motor↔manifiesto), que es lo
        único que impide que envejezca en silencio. Verificado por mutación con el caso que importa: el
        mismo cambio en los DOS motores —invisible para el diff entre ellos— lo caza el manifiesto.
      · 30 entradas · 696 nodos · `tests/Fixtures/sidebar-dom-manifest.json`. Se regenera a propósito
        (`MANIFEST_REFRESH=1`) y hay guarda contra entradas huérfanas.
      · ⚠️ **La foto valía UN DÍA y se arregló el 2026-08-15** (`DECISIONES #64`): dos de las treinta
        entradas son el calendario, y la rejilla del mes depende de HOY. Ahora el reloj se congela en
        `setUp()` (`FROZEN_NOW`) y el manifiesto se regeneró con él. La regla que deja: **una foto que
        incluye el tiempo se toma con el reloj parado**, o no es una red.
      · ⚠️ **Y el dato que cambia la planificación**: **26 ficheros de test ejecutan el componente** —medido
        con un patrón que resultó corto; el acoplamiento real eran 32 (`#63`)—, con
        ~160 casos, en tres familias —los que mueren con él, los que deben re-apuntarse al servidor y las
        nueve paridades—. La retirada NO es borrar un fichero; eso es 4.7·2.
      · Suite **2715 verde**.
- [x] **Paso 4.7·2a — el inventario de la retirada deja de crecer** (2026-08-15, `DECISIONES #61`).
      Sin borrar nada del motor que hoy vende: ataca el riesgo de un desmontaje largo, que es que alguien
      siga construyendo encima.
      · Nace `PurchaseRetirementTest`: la lista de dependientes **solo puede encoger**, y vigila las dos
        direcciones (ni tests nuevos conduciendo por el componente, ni entradas que ya no dependan).
        Verificado por mutación ×2. ⚠️ Se excluye a sí misma del escaneo: declara el patrón.
      · **Primer fichero fuera**: `SlotOfferTest` conducía por el componente para probar una regla de
        DOMINIO; se re-apunta a `POST availability/{p}/times`, que es el flujo público que sobrevive.
      · **Y una clasificación resuelta midiendo**: los dos casos de `AddonDependencyTest` mueren sin
        pérdida — `CatalogAddonsTest` ya cubre la poda **mejor** (la cadena entera). Escrito, no ejecutado.
      · Suite **2718 verde** · dependientes: **25** (eran 26). ⚠️ Ese recuento resultó CORTO:
        el escaneo solo veía una de las tres formas de acoplamiento. Corregido a 32 en ·2b·1 (`#63`).
- [x] **Paso 4.7·2b·1 — el contador medía menos de la mitad de las formas** (2026-08-15,
      `DECISIONES #63`). Arregla el instrumento antes de usarlo, y saca los tres primeros dependientes.
      · ⚠️ **El inventario declaraba 25 y el acoplamiento real eran 32.** El escaneo reconocía un solo
        literal (`Livewire::test(Purchase::class)`) y no veía: **cuatro** que conducen con
        `Livewire::actingAs($u)->test(…)` —21 llamadas—, **uno** que lee `Purchase::MAX_LINES_PER_CART`
        y **dos** que dependen de `purchase.blade.php`. Con siete invisibles, un contador a 0 no
        levantaba ningún bloqueo: rompía la suite al borrar.
      · **El escaneo tokeniza y descarta comentarios**, y mira TRES formas. Dos mutaciones: la lista
        vieja contra el escáner nuevo cae nombrando **exactamente los siete**; sin descartar comentarios
        entran **dos falsos positivos** que solo lo mencionan como historia. La guarda de la guarda pasa
        a ser **una por forma** —la vieja («más de 10») pasaba con la mitad de las formas ciegas—.
      · **Tres fuera, cada uno por su motivo** (32 → **29**): `OrderCreatorTest` lee el tope de
        `OrderCreator`, que es quien lo define **y su propio sujeto**; `AvailabilityTest` pierde su
        paridad porque **la pregunta desaparece** al quedar un solo motor y sus controles ya están
        fijados por dos casos que existían; `QuoteTest` **se re-apunta** porque su paridad era el único
        caso del fichero que ejerce la suma de una cesta mixta.
      · ⚠️ **Y destapó una trampa de importes**: `deposit_cents` de una línea SIN señal **no** incluye
        sus complementos, pero esos complementos **sí** se cobran online → sumar los `deposit_cents` no
        da `online_amount_cents` (110,00 vs 125,00). Fijado, con la mutación que demuestra que es el
        único caso que lo caza.
      · **No borra ni un test del motor que hoy vende**: eso es 4.7·2b·3, en el mismo commit que el
        componente. Suite **2715 verde**.
- [x] **Paso 4.7·2b·2 — re-apuntar lo que SOBREVIVE** (✅ **CERRADO**; casilla cerrada el 2026-08-21:
      su condición terminal la declaró cumplida el tramo `·C` —«LAS ENTRADAS DEL INVENTARIO ESTÁN
      CLASIFICADAS», `DECISIONES #98`— y se quedó abierta por descuido).
      2026-08-15, `DECISIONES #65`: los
      ficheros cuyo sujeto es el dominio o el servidor y que solo usan el componente como conductor, más
      las paridades que pueden compararse contra el manifiesto congelado o contra el contrato (`lang/`,
      `openapi/v1.yaml`).
      · ✅ **`RedsysIdaTest` re-apuntado a `POST /api/v1/orders` y FUERA del inventario** (29 → **28**).
        Sus dieciséis casos son sobre el payload que sale a la pasarela, o sea servidor puro. La
        equivalencia se comprobó, no se supuso: mismo `CheckoutOrchestrator`, mismo `source`
        (`checkout`), mismo `$user->locale`.
      · ⚠️ **La mutación descubrió que re-apuntar había DEBILITADO un caso.** El del idioma seguía verde
        con el orquestador mutado para no pasar el idioma del titular: `ApiLocale` cae a `users.locale`,
        así que una petición muda ya dejaba la app en `fr` y el fallback daba el mismo `004`. Con
        Livewire no pasaba (no cruza middleware HTTP). **Regla: al re-apuntar un caso hay que volver a
        mutarlo** — la superficie nueva puede traer por su cuenta el valor que el caso creía verificar.
      · ✅ **El único caso que prueba la VISTA se muda con los suyos** (`PurchasePanelTest`), no se
        borra: es lo único que cubre el marcado del auto-POST en el lado Livewire —el diff de árbol
        normaliza `action`, `method` y los `name`—. Agruparlo es lo que permite que ·2b·3 borre ficheros
        enteros en vez de operar dentro de ficheros que sobreviven.
      · ✅ **`ModuleContractsTest` gana la mitad de API que le faltaba** (sigue en el inventario a
        propósito): sus tres guardas de «la web no reimplementa» ahora comprueban también
        `POST orders/quote`, `availability/{id}/dates|times` y `GET catalog/products`. Hueco real, y
        las tres verificadas por mutación. ·2b·3 solo tendrá que borrar la mitad Livewire.
      · **Pendientes de este tramo**: `DepositSurfacesTest` (2 usos re-apuntables + 4 de UI),
        `PurchaseRetryAndPollingTest`, `SidebarSeamTest`/`SidebarTokenBudgetTest` (leen
        `purchase.blade.php`; hay que apuntarlos a las fuentes Vue) y el grupo de las paridades.
      · **Inventario de las paridades, medido el 2026-08-15**: de las trece, **CINCO ya no tocan
        Livewire** y sobreviven intactas —`SidebarLoginParityTest`, `SidebarRegisterParityTest`,
        `SidebarMoneyParityTest`, `SidebarTextParityTest`, `SidebarPendingFieldsParityTest`: comparan
        contra el diccionario y el contrato—. Quedan **ocho** más el diff de árbol:
        `SidebarOutcomeParityTest` (10 usos) · `SidebarPausedParityTest` (4) ·
        `SidebarProgressParityTest` (4) · `SidebarCalendarParityTest` (3) · `SidebarCartParityTest` (3)
        · `SidebarAdmissionParityTest` (2) · `SidebarAddonsParityTest` (1) · `SidebarPayParityTest` (1)
        · `SidebarDomContractTest` (26).
      · ⚠️ **LA PIEZA GRANDE, y hay que decidirla antes de tocar ninguna paridad**: `SidebarDomContractTest`
        alimenta a Vue con props que salen **TODAS del componente Livewire** —trece helpers
        `…Props(Testable $component)` que leen `viewData()`—. Sin componente no hay props, así que el
        diff de árbol **no sobrevive tal cual**. Hay dos salidas y no son equivalentes:
        · **(A) congelar también las props** → el test pasa a comparar «Vue con props de fichero»
          contra el manifiesto. Barato, y detecta regresiones de Vue. Pero es foto contra foto: no
          vería que el SERVIDOR cambie la forma de un dato.
        · **(B) alimentar a Vue con las respuestas REALES de la API** → el test pasa a decir «la SPA,
          servida por el servidor de verdad, emite el árbol congelado». Más caro y **estrictamente
          mejor**: cierra el punto ciego que hoy obliga a que existan `SidebarAddonsParityTest` y
          `SidebarCalendarParityTest` —el diff le pasa a Vue el view-model de Livewire traducido, así
          que un componente que lea nombres de campo equivocados pasa en verde; ya mordió una vez—.
          **Con (B), varias paridades dejan de hacer falta por el motivo correcto**, no por descuido.
        ▶ **Recomendación: (B)**, y hacerla ANTES que las ocho paridades, porque decide cuáles de ellas
        siguen teniendo pregunta que responder.
      · ✅ **·B ARRANCADO — el paso 1 ya se alimenta del servidor** (2026-08-15, `DECISIONES #67`).
        Se eligió la opción (B). Nace `resources/js/sidebar/catalog.js` (12 casos `node --test`) con la
        traducción que vivía DENTRO de `Sidebar.vue` sin red; `Sidebar.vue` lo consume y
        `render-sidebar.mjs` gana un modo `"api"` que construye las props con ese mismo módulo a partir
        de las respuestas CRUDAS de `GET /catalog/products` y `GET /config`.
        · ⚠️ **El motivo real no era «sin componente no hay props»**: era que el gate **nunca ejecutaba
          la traducción que corre en el navegador**. Medido: **21 de las 25 funciones de `Sidebar.vue`
          no tenían ningún test**.
        · ✅ **Fidelidad demostrada: el manifiesto NO cambió.** Los 34 casos pasan sin regenerarlo, así
          que alimentar desde la API produce el MISMO árbol. Una entrada distinta habría sido una
          divergencia real, no un fixture que actualizar.
        · ✅ **Mutación en las dos direcciones**: renombrar `from_price_cents` en `toItem()` deja el
          gate **ROJO** en el modo nuevo y **VERDE** en el viejo. Las dos mitades ejecutadas.
        · ⚠️ **Consecuencia para el resto**: cada paso migrado hace redundante —por el motivo correcto—
          la paridad que existía para tapar este hueco (`SidebarAddonsParityTest`,
          `SidebarCalendarParityTest`). **No se pueden retirar todavía**: solo el paso 1 está migrado.
        · ✅ **Paso 2 (calendario) migrado** (`DECISIONES #68`). El mes se DERIVA de la oferta, no se
          pasa. Manifiesto sin cambios; dos mutaciones (relleno fuera de mes, navegación desacotada)
          lo ponen rojo. ⚠️ **`calendar.js` no tenía ningún test**: nace `calendar.test.js` (18 casos)
          con los dos frontera que la paridad declaraba medidos.
        · ⚠️ **Y otra vez un caso frontera VACUO, como en 4.2**: el de husos pasaba con la mutación de
          entrada (`new Date(month + '-01')`) porque el peligro tiene DOS puertas —salida
          (`toISOString`) y entrada (parseo UTC)— y el desfase de la entrada **solo mueve el arranque
          si el día 1 ya era lunes**. Añadido un mes que empieza en lunes: ahora cada mutación deja
          rojo exactamente un caso. **Regla: el caso frontera se elige por el MECANISMO del fallo, no
          por el síntoma.**
        · **Fallo real arreglado de paso**: el mes inicial se derivaba con `toISOString()` (UTC), así
          que en Madrid entre las 00:00 y las 02:00 del día 1 el respaldo daba el mes anterior. Ahora
          es `initialMonth()`, en local, con su caso.
        · **`SidebarCalendarParityTest` ya es candidata a retirarse** (su hueco está cerrado y sus
          fronteras viven en `calendar.test.js`), **pero no se retira hasta ·2b·3**: mientras Livewire
          viva es el único sitio que compara las dos composiciones entre sí.
        · ✅ **Paso 3 (hora, cantidad y complementos) migrado** (`DECISIONES #69`). Se alimenta de sus
          cuatro respuestas reales y **desaparece el `addonsAsApi()` que el test hacía por su cuenta**,
          que era el punto ciego con nombre y apellidos. Nace `offer.js` (9 casos). Manifiesto intacto.
        · ⚠️ **Divergencia que parecía existir y NO existe**: `qty = techo >= mín ? mín : 0` (servidor)
          frente a `min(mín, techo)` (SPA) solo difieren con `0 < techo < mín`, y ese caso **no es
          alcanzable** —la oferta retira la hora entera cuando el mínimo no cabe; medido con un pack de
          mínimo 8 y el aforo casi agotado—. Escrito en `initialQuantity()` para que nadie lo «arregle».
        · ⚠️⚠️ **EL GATE PODÍA DAR VERDE FALSO**: renderiza `storage/ssr/render-sidebar.js`, un
          ARTEFACTO. Medido: con `catalog.js` roto y el bundle sin reconstruir, el caso del catálogo
          pasa en verde. En el `pre-push` no ocurre (construye antes), pero **al iterar en local sí**.
          Arreglado con `assertBundleIsNotStale()`, verificado en las dos direcciones.
          **Regla: un test que compara contra un artefacto tiene que comprobar que no está rancio.**
        · ✅ **Paso 4 (cesta) migrado** (`DECISIONES #70`). Cae `cartProps()`, la tercera y mayor de las
          traducciones a mano —inventaba `product_id: 0`—, así que el gate ya ejecuta `cartRows()`.
        · ⚠️ **Un caso NO se migra a propósito**: «línea sin fecha» siembra un estado que el cliente no
          puede alcanzar (la API lo rechaza con 422 y el saneador lo descarta). **Alimentar desde la API
          solo tiene sentido para estados alcanzables.**
        · ⚠️ **Y una mutación que el gate NO cazó, y está bien**: emparejar por posición en vez de por
          `index` deja el diff verde porque sus fixtures tienen UNA línea. Esa regla la cubre
          `cart.test.js` con un caso hecho para ella, y **se verificó que la misma mutación lo pone
          rojo**. Cada nivel prueba lo suyo.
        · ✅ **El ARMAZÓN migrado** (`DECISIONES #71`): el pie y la banda los compone el cliente
          (`foot.js`, `progress.js`) a partir del presupuesto, el catálogo y la línea de complementos.
          De los doce montajes quedan **tres**, todos del paso 8 y del bucle de la pausa.
          · ⚠️ **Cubre por fin el fallo de 4.3·1**: la banda estaba escrita, el gate verde y el cajón
            vivo sin «Volver». Mutar `buildProgress()` para que no emita deja hoy **tres casos rojos**.
          · **La migración es explícita** (`shellFromServer: false`), no automática: mezclarlas en
            silencio escondería cuál de los doce sigue comparando contra el servidor.
          · ⚠️ **Otra mutación mal apuntada**: tumbar `splitMode` en la rama del paso 3 salió verde
            porque su fixture es una ENTRADA (sin señal); la rama con desglose es la de la cesta.
            Misma lección que los husos de `#68`.
        · ✅ **Pasos 5, 8 y 9 migrados** (`DECISIONES #72`). Con ellos **el test ya no traduce nada del
          servidor**: caen `gatewayFormProps()` (el mapa `payment.fields` → lista, que hace `pay.js`),
          `registerErrorsFrom()` (el orden del banner, que fija `register.js`) y `payProps()`.
          · ⚠️ **El paso 9 enseñó un orden no negociable**: pedir el sobre de pago DESPUÉS de
            `confirmReservation()` da 422, porque confirmar **vacía la cesta** (a partir del 201 el
            pedido existe y retiene aforo). Se pide antes, y está escrito en el caso.
          · ⚠️ **El límite del sobre de `api.js` queda declarado**: sus cuatro trampas son del
            TRANSPORTE y no se pueden ejercer sin red; lo que se ejerce es quien LEE el sobre.
          · Mutaciones: vaciar los campos firmados (paso 9) y vaciar `summaryOf()` (paso 5) dejan su
            caso en rojo.
        · ✅ **(B) COMPLETA — pasos 6, 10, 11 y el último montaje del armazón** (`DECISIONES #73`).
          **Ya no queda un solo sitio donde el diff le pase a Vue algo compuesto por Livewire.**
          · Paso 6: `buildConfirmation()` desde el pedido + las respuestas del pack, que llegan por
            endpoints distintos (`#39`). Mutar `answersByReservation()` deja dos casos rojos.
          · Paso 10: `declinedReasonText()` sobre `declined_reason`. Paso 11 no traduce nada.
          · ⚠️ **Otra mutación que el diff no caza y está bien**: quitar la caída a `default` del motivo
            sale verde porque ningún fixture trae una clave DESCONOCIDA; la cubre `outcome.test.js` y se
            verificó que allí sí cae. Segunda vez que la respuesta es «mira si lo ve quien debe».
          · ⚠️ **La guarda del bundle rancio se cobró su primera pieza en vivo**: restaurar un módulo con
            `cp` le puso fecha nueva y el gate cayó ENTERO nombrándolo. Sin ella: verde contra código
            viejo.
          · **Balance de (B)**: 7 tramos · `catalog.js`, `calendar.js` (con test propio) y `offer.js` ·
            **286 tests JS** frente a 247 · 4 traducciones a mano retiradas · **manifiesto sin cambiar
            ni una vez en los siete**.
      · ⚠️⚠️ **`SidebarTokenBudgetTest`: RE-MEDIDO con la lógica real del test (`DECISIONES #74`), y la
        primera lectura era IMPRECISA — no es una tarea de 4.0c que falte.** El test deriva el ámbito CSS del cajón escaneando
        `class="…"` de `purchase.blade.php`. Los `.vue` traen **41 clases que ese escaneo no ve**
        —casi todas de parciales que el Blade incluye y el escáner no sigue: `jj-spinner*`, `jj-loading`,
        el bloque `auth__*`/`form__*`, los iconos— y el Blade tiene **4 que los `.vue` no**
        (`is-selected`, `is-invalid`, `active`, `catalog__item--feat`).
        **Al añadir las fuentes Vue: el ámbito pasa de 1.073 a 1.307 declaraciones, la tokenización cae
        del 75% al 70% y los crudos suben de 3 a 5.**
        ▶ **Pero lo que entra NO es CSS del cajón mal tokenizado**, y esto solo se ve desglosando: son
        **60** declaraciones del bloque `auth__*`/`form__*`/`check`/`pwd-input` —los formularios de
        login y alta, compartidos con el modal de auth y con `/mi-cuenta`— más `eyebrow`, `icon` y `tk`,
        que son del sitio. **Del cajón solo había UNA**: el velo `.jj-loading`, con su
        `rgba(244,239,227,0.82)` que es exactamente `--bg` al 82% — **ya tokenizada** con el patrón de
        4.0c, y los crudos del ámbito ancho bajan de 5 a 4.
        ▶ **Lo que esto impone es una DECISIÓN, no una tarea**: definir el ámbito por las familias
        PROPIAS del cajón (`purchase__`, `cart__`, `bk-`, `cal__`, `wiz__`, `qtybox`, `jj-`, `sidecart`…)
        en vez de rascar una plantilla — así el presupuesto deja de depender del motor. Tokenizar el
        bloque de formularios del sitio es **Fase 5**, no la retirada. Ficha en `DEUDA.md`.
      · **`SidebarSeamTest` NO se puede re-apuntar, y conviene saber por qué** (medido el 2026-08-15):
        su `ENGINE_VIEW` es una EXCLUSIÓN («en la vista del propio motor el evento está en su sitio»).
        Ampliarla a todo `livewire/` la debilitaría —otro componente, como `account-context`, podría
        despachar un evento del motor y no se vería—. La exclusión **desaparece sola con el Blade**,
        junto con los dos eventos. Es de ·2b·3, no de ·2b·2.
      **Clasificación ya medida** para los que se inspeccionaron en ·b1, para no repetir el trabajo:
      · `ReservationPauseGuardTest` (3 casos) — **muere con el componente sin pérdida**: su sujeto es el
        guard de servidor y la superficie que sobrevive ya lo cubre en `Api/V1/OrdersTest`
        (`test_a_paused_installation_refuses_to_create_and_leaves_no_order` y su gemelo del reintento).
      · `Ui/SpinnerTest` (1 caso + el del placeholder) — **muere**: el velo del cajón SPA lo cubre el
        caso del armazón de `SidebarDomContractTest`, anclado en `.jj-loading` **con hermanos**.
      · `Auth/DuplicateEmailEdgeCaseTest` (1 caso) — **muere**: prueba `Purchase::onSwitchToLoginTab`, y
        ese escape **no existe embebido en ninguno de los dos motores** —el paso 5 deja de renderizarse
        en cuanto el cajón salta al 7—, hecho ya verificado y documentado en `VerifyStep.vue`. Los otros
        dos casos del fichero (los de `Register`) sobreviven intactos.
      ⚠️ **Sin inspeccionar todavía**: `DepositSurfacesTest`, `PurchaseRetryAndPollingTest`,
      `RedsysIdaTest`, `SidebarSeamTest`, `SidebarTokenBudgetTest` y el grupo de las nueve paridades.
      · ✅ **Arranca la auditoría de las nueve paridades** (2026-08-15, `DECISIONES #75`), con la
        pregunta que (B) hace posible: «¿qué afirma esto que el diff, ya alimentado del servidor, no
        afirme?». **`SidebarPayParityTest` FUERA del inventario** (28 → **27**): su comparación entre
        motores desaparece —la mitad Livewire la fija `PurchasePanelTest` sobre el marcado real— y su
        caso de los tres idiomas se **re-apunta al diccionario**, que es la referencia correcta.
        · ⚠️ **Medido: `error.message` de la API NO sirve como referencia** — es una cadena fija de
          desarrollador y **no se traduce** (idéntica en `es`/`en`/`fr`). El cliente compone desde la
          CLAVE con los `params` del rechazo, así que la referencia que sobrevive es `__()`.
        · **La regla para las ocho restantes**: separa lo que compara ENTRE MOTORES (muere), lo que
          afirma del CONTRATO (se queda) y lo que usa el motor viejo como INTERMEDIARIO de una fuente
          que sobrevive (se re-apunta a la fuente — y casi siempre mejora el test).
      · ✅ **`SidebarAddonsParityTest` re-apuntada y FUERA del inventario** (2026-08-15,
        `DECISIONES #77`). 27 → **26**. El motivo por el que nació lo cerró (B) en `#69`, pero lo que
        queda no es comparación entre motores: el componente solo conducía `AddonResolver::viewModel()`,
        que **sobrevive con dos consumidores** —este endpoint y el alta manual del panel—. El sujeto
        pasa a ser **la capa de publicación** (`toDto()` + `ResolvedAddonsResource`, 21 traducciones de
        clave), y el `asApi()` del test es su segunda escritura deliberada.
        · ⚠️ **No se podía borrar, y está medido**: mutando campo a campo contra los 2715 casos,
          `note`, `min_quantity` y `max_quantity` dan **1 fallo en toda la suite** y es este. El
          contrato fija los NOMBRES publicados, nunca de qué campo sale cada valor.
        · ⚠️⚠️ **El caso era VACUO en dos campos** (mecanismo de `#68`): sin obligatorio ni tope en el
          fixture, `min` era 0 y `max` `null` en los cinco complementos, así que cruzar `min` ← `max`
          salía **verde** (`(int) null` es `0`). Ahora hay uno de cada (`min` = 2, `max` = 3) y tres
          aserciones de frontera sobre lo publicado impiden que se vuelva a perder en silencio.
        · ⚠️ **Dos mutaciones mal apuntadas antes de acertar**: `product_name` y `quantity` viven dos
          veces en el Resource —fila resuelta y `line.addons`— y la línea con menos sangría es
          subcadena de la otra. **Al mutar hay que comprobar DÓNDE cayó la mutación.** De paso quedó
          medido que `line.addons` sí está cubierto (3 rojos en `CatalogAddonsTest`).
      · ✅ **`SidebarAdmissionParityTest` re-apuntada y FUERA del inventario** (2026-08-15,
        `DECISIONES #78`). 26 → **25**. Primer caso en que la respuesta NO es «re-apuntar la
        referencia» sino **«la referencia no aportaba nada»**: cinco mutaciones sobre `admission.js`
        dejan rojos a la vez la paridad y `admission.test.js`, así que comparar destinos contra
        Livewire no añadía red. Lo que se rescata es otra cosa: **la cadena entera es REAL** —las
        respuestas las da el servidor sobre una instalación pausada / un tope lleno / el limitador
        agotado, y el diccionario es el de `lang/`—.
        · ⚠️ **Medido**: renombrar `errors.too_many_pending` en `lang/es/tickets.php` deja los 2715
          casos verdes **salvo uno**, y es este. `admission.test.js` no puede verlo (su diccionario es
          fabricado) y `SidebarTextParityTest` no lleva esas claves.
        · **El sujeto nuevo es el CABLEADO** endpoint ↔ módulo, que ninguno de los dos prueba solo:
          las dos mutaciones de deriva de contrato —publicar el nombre interno del dominio en vez del
          código público, y dejar de publicar `max_pending_orders`— lo dejan rojo.
        · **Lo que se pierde, dicho sin adornos**: el caso que demostraba sobre el HTML que el mensaje
          de pausa se escribe y **no se pinta** muere con el Blade —era su única prueba posible—. Se
          queda la conducta que aquello justificaba, ejercida con la instalación realmente pausada.
        · ⚠️ **La guarda del bundle rancio saltó por TERCERA vez en tres días**: mutar un módulo y
          restaurarlo con `git checkout` le pone fecha nueva, y tumbó los 30 casos del diff de golpe.
          **Tras iterar sobre `resources/js/`, `npm run build:ssr` antes de leer nada.**
      · ✅ **`SidebarCalendarParityTest` auditada: NO es homogénea** (2026-08-15, `DECISIONES #79`).
        **El contador no se mueve** —lo que muere con el componente sigue en el inventario hasta
        ·2b·3—, y aun así había que hacerlo: sin esta separación ·2b·3 borraría un caso VIVO.
        · Los **dos primeros casos mueren**: su referencia es `viewData('weeks')`, que compone
          `Purchase` y no existe en ningún otro sitio. No hay fuente a la que re-apuntar, y su hueco
          lo cerró (B) en `#68`.
        · ⚠️ **El caso de los HUSOS sobrevive**: no compara motores, compara el cliente consigo mismo
          con `TZ` forzado en dos procesos Node. `calendar.test.js` **no puede hacerlo** (un solo
          proceso; el huso se lee al arrancar), así que es la única red de una defensa que ya se
          comprobó inerte con el huso del contenedor.
        · **Conducía el componente sin usarlo**: montaba `Livewire::test(Purchase::class)` y sembraba
          un producto que no aparece en ninguna aserción. Medido y retirados los dos.
        ▶ **·2b·3 tiene que operar DENTRO de este fichero**, no borrarlo entero. Anotado en su propio
          docblock, que es donde lo leerá quien lo borre.
      · ✅ **`SidebarCartParityTest` re-apuntada y FUERA del inventario** (2026-08-15,
        `DECISIONES #80`). 25 → **24**. El caso más claro de la tercera categoría: `viewData('footer')`
        y `viewData('cartLines')` **no eran fuentes**, eran un ensamblaje de `__()`, `number_format` y
        el presupuesto — las tres sobreviven.
        · ⚠️ **Medido**: renombrar `footer_pay_now` en `lang/es/tickets.php` deja los 2715 verdes
          **salvo uno**, y es el del pie de la cesta.
        · **Se compara MENOS y mejor**: solo textos e importes, que es lo único que ni el diff de árbol
          (descarta el texto) ni `foot.test.js` (diccionario fabricado) pueden mirar. La estructura ya
          tiene dueño desde `#71`; duplicarla aquí era medir el andamiaje.
        · **El caso de las filas conserva lo suyo**: el HUECO en la secuencia de `index` cuando un
          producto deja de venderse. El árbol no puede verlo —sus fixtures tienen UNA línea (`#70`)—.
          Referencia nueva: el presupuesto real, más la aserción que faltaba (que la fila del pack
          lleve SUS respuestas, no las de la línea que ocupa su posición).
        · **La cesta se compone directamente**, sin conducir ningún motor: es una lista de líneas, y lo
          que el fichero prueba es qué se PINTA con una cesta dada, no cómo se llena.
        · Siete mutaciones, las siete rojas (dos claves ausentes, rótulo cruzado, plural ingenuo,
          marcador «—» → «0,00 €», separador de millares, emparejar por la primera línea).
      · ✅ **`SidebarPausedParityTest` re-apuntada y FUERA del inventario** (2026-08-15,
        `DECISIONES #81`). 24 → **23**. El título y el mensaje eran intermediarios de un renglón
        (`Purchase::pausedTitle()` es `return MaintenanceSettings::reservationTitle();`).
        · ⚠️ **Y los enlaces se CONGELARON contra el motor vivo**, porque no había fuente a la que
          re-apuntarlos: la composición (`tel:` sin espacios, `wa.me` solo con dígitos, el rótulo con
          el número tal y como se escribió, el respaldo a `/contacto` solo sin canales directos) vivía
          en el Blade. Se volcó lo que emitía DE VERDAD en los cuatro estados de canales y se escribió
          una composición que lo reproduce, verificada contra el motor todavía en pie. **Mismo criterio
          que `#60`: una referencia que se va se mide antes de perderla.**
        · La lista de pasos tapados pasa a ser la DECISIÓN escrita, no un espejo de
          `showPausedNotice()`: en qué pasos se tapa el flujo no lo publica ningún endpoint.
        · Cinco mutaciones, las cinco rojas (tapar un paso de resultado, WhatsApp a otro destino,
          `tel:` con el número rotulado, título desde el diccionario, el canal de llamar deja de ser
          el principal).
      · ✅ **`SidebarProgressParityTest` reducida y FUERA del inventario** (2026-08-15,
        `DECISIONES #82`). 23 → **22**. De sus tres casos uno se va, uno no se toca y uno se congela.
        · **Primer caso RETIRADO por redundancia demostrada**: la comparación de la banda existía por
          el hueco que (B) cerró en `#71` —el árbol ya ejecuta `progress.js`—, y sus dos mutaciones
          dejan rojo **también** `progress.test.js`.
        · ⚠️ **Y se midió qué se iba con él**: `ucfirst()` solo se ejerce desde `buildProgress()`;
          mutarlo deja dos rojos en `progress.test.js` y el árbol verde. Cobertura intacta.
          **Retirar un caso obliga a medir qué dejaba de cubrirse, no solo que la suite siga verde.**
        · El mapa de «modo» se **congela** contra el motor vivo (técnica de `#81`): su consumidor
          sobrevive —`layout.blade.php` pinta `is-{modo}`— y el 8 (pago) es `cart`, no `result`.
        · La fecha del contexto se queda intacta: Carbon frente a `Intl`, con la divergencia del
          español acotada. No depende de ningún motor.
      · ✅ **`SidebarDomContractTest` auditado: se queda hasta ·2b·3, y muere DENTRO** (2026-08-15,
        `DECISIONES #83`). **El contador no se mueve**, y la conclusión CORRIGE la nota de handoff de
        `#82`, que se escribió leyendo.
        · ⚠️ **El manifiesto está anclado en `$livewire`**, no en `$vue`: la SPA solo queda cubierta
          por transitividad. Y `SidebarSettings::engine()` devuelve `livewire` por defecto Y como
          fallback, así que el motor anclado **es el que hoy sirve** — quitarle su red ahora dejaría
          sin prueba de DOM justo al que vende.
        · **Tampoco decide nada sobre `SidebarOutcomeParityTest`**: (B) cambiaba de qué se ALIMENTA el
          diff; esto solo quita un motor cuando desaparezca. Esa paridad se audita por su cuenta.
        · **Lo que deja hecho: las tres operaciones exactas de ·2b·3**, escritas dentro de
          `assertTree()`, que es donde se leerán. Y el riesgo asumido: sin segundo motor,
          `MANIFEST_REFRESH=1` acepta cualquier deriva.
      · ✅ **`SidebarOutcomeParityTest` MEDIDA entera, con el plan escrito** (2026-08-15,
        `DECISIONES #84`). **El contador no se mueve todavía**, y no por falta de decisión: el
        re-apunte **no se puede hacer por partes**.
        · ⚠️ **Los catorce casos cuelgan de `purchase()`**, que fabrica la compra conduciendo el
          componente. O se cambia esa pieza a `POST /api/v1/orders` —receta probada en `#65`— o no se
          mueve ninguno. Esa es la única razón por la que el tramo no cerró.
        · **Lo único que solo caza este fichero son los motivos del rechazo**: renombrar
          `payment_failed.reasons.cvv_wrong` deja los 2714 verdes salvo DOS, los dos de aquí. Y su
          referencia SOBREVIVE (`RedsysResponseCode::reasonText()`), así que es re-apunte, no pérdida.
        · **Redundantes** (mutación: rojos a la vez que `outcome.test.js`): los destinos del reintento
          y del sondeo. **Mueren**: los dos casos del enlace de registro —`PublicConfigTest` ya lo
          cubre mejor, con las cuatro URLs hostiles de `SEC-07`—. **Se parte**: el caso de los enlaces
          del desenlace (la mitad del servidor sobrevive; la del Blade muere).
        · ⚠️ **Residual a DECIDIR en ·2b·3**: la mitad del Blade es lo único que hoy comprueba que un
          motor PINTA esas URLs; `href` no es atributo de contrato y los módulos planos no las tocan.
      · ✅ **`SidebarOutcomeParityTest` re-apuntada y FUERA del inventario** (2026-08-15,
        `DECISIONES #85`). 22 → **21**. `purchase()` crea el pedido con `POST /api/v1/orders` (receta
        de `#65`): deshecho el nudo, los catorce casos encontraron su referencia sin pelea.
        · **Cuatro casos retirados, cada uno medido**: los dos del resumen contra el view-model
          —`outcome.test.js` cubre la composición, `OrderSummaryFieldsTest`/`OrderEventDataTest` los
          campos, y componer `park_cents` restando resultó ser un mutante EQUIVALENTE para su
          fixture— y los dos del enlace de registro, que `PublicConfigTest` cubre mejor.
        · Las tres divergencias declaradas se quedan con su mitad viva; los destinos del reintento y
          del sondeo se **volcaron** del motor vivo (técnica de `#81`).
        · ⚠️ **Un mutante verde comprobado, no asumido**: quitar la caída a `default` no pone rojo
          este test (hoy toda clave de `REASON_MAP` existe en `lang/`); lo caza `outcome.test.js`.
        · ✅ **La auditoría de las NUEVE paridades queda CERRADA**: siete re-apuntadas y fuera del
          inventario, dos que mueren con el componente operando DENTRO (`#79`, `#83`). **27 → 21.**

- [x] **Paso 4.7·2b·2·C — los dependientes que NO son paridades** (✅ **CERRADO** el 2026-08-16 con
      `DECISIONES #98`; casilla cerrada el 2026-08-19 — se quedó abierta con la condición terminal ya
      cumplida, ver el balance al final del ítem. Contador hoy: **19**).
      Arrancó el 2026-08-15 (`DECISIONES #86`): cerrada la auditoría de las nueve paridades, quedaban
      **20** entradas y la
      pregunta cambia de forma: **«¿su sujeto es el DOMINIO —y el componente solo conduce— o es la
      SUPERFICIE Livewire?»**. Los primeros se re-apuntan; los segundos mueren.
      · ⚠️⚠️ **La meta NO es que el contador llegue a 0**, y la doc decía que sí. **Nueve** de los que
        quedan tienen por sujeto la superficie del motor y **solo pueden salir en el commit que lo
        borra**. Condición terminal real: **todas las entradas restantes CLASIFICADAS como que
        mueren**. Escrito en el docblock de `PurchaseRetirementTest`.
      · ⚠️ **Aquí NO hay nudo**: la lección de `#85` se aplicó y dio negativo — los once que quedan por
        auditar tienen cada uno sus ayudantes propios, sin nada compartido. Se van de uno en uno.
      · ✅ **`AddonDependencyTest`: dos casos RETIRADOS por redundantes** (21 → **20**). Las dos
        mutaciones sobre `AddonResolver::buildSelection()` que los ponían rojos dejan rojos **tres**
        casos, y **dos sobreviven** —uno en el mismo fichero llamando al dominio directo, y
        `CatalogAddonsTest::test_the_dependency_pruning_follows_the_whole_chain`, que además recorre la
        cadena a punto fijo—. La regla queda mejor cubierta donde se queda.
      · ✅ **`CatalogVisibilityAndCartPruneTest` clasificado** (`DECISIONES #87`). **El contador no se
        mueve**: su caso acoplado muere con el componente y los otros dos no lo tocan → ·2b·3 opera
        DENTRO, como en `#79`.
        · **La distinción que enseña**: su regla SOBREVIVE, lo que muere es **dónde se aplica**
          (`Purchase::mount()`, sobre la cesta de sesión). **La pregunta hay que hacérsela al SUJETO
          del caso, no a la regla que menciona.**
        · Medido: mutar el filtro `sellable()` de `CartPricer` tumba tres casos, **los tres
          supervivientes** (`QuoteTest`, `CartPricerTest`, `SidebarCartParityTest`) — y **este NO**,
          que es la prueba de que ejerce otro camino. La otra mitad es `cart.js::reconcile()`.
        · ⚠️ **Medición DESCARTADA por ruidosa, anotada para no repetirla**: mutar
          `Order::displayStatus()` para clasificar `PurchaseConfirmationStatusTest` tumba media docena
          de casos del panel que no vienen al caso. Ese fichero queda **sin clasificar** a propósito:
          hace falta una mutación dirigida al lado SPA (`buildConfirmation()`).
      · ✅ **`PurchaseConfirmationStatusTest` clasificado** (`DECISIONES #88`): el fichero **ENTERO
        muere** con el componente —sus tres casos prueban lo que PINTA el paso 6—, así que ·2b·3 lo
        borra entero. Contador sin cambio.
        · **La mutación dirigida sí concluye**: clavar `buildConfirmation()` a `'pending'` tumba dos
          casos y **los dos sobreviven** (el paso 6 del diff de árbol y la paridad del desenlace); este
          fichero **no cae**, que es la prueba de que ejerce el camino del Blade. **Una mutación se
          apunta al MECANISMO que se quiere clasificar, no al dato que ambos leen.**
        · ⚠️ **Y destapó un hueco en lo que se QUEDA**: con `status` clavado, `outcome.test.js` seguía
          verde — el módulo no fijaba su propio mapeo del estado. Nace el caso (los tres estados más
          el pedido ausente), verificado por mutación. **287 tests JS** (antes 286).
      · ✅ **`AddonInclusionPurchaseTest` clasificado: muere ENTERO** (`DECISIONES #89`) — su sujeto es
        el puente UI → carrito. Contador sin cambio. **Pero la auditoría destapó tres reglas de dominio
        publicadas que NO guardaba nadie**, y esa es la ganancia del paso.
        · ⚠️⚠️ Medido: quitarle a `can_decrease` su `$qty > $min`, a `can_increase` su tope `max_qty` y
          a `can_toggle` su exigencia de por-invitado deja la suite **entera en verde**.
        · **El punto ciego tenía forma**: `SidebarAddonsParityTest` no puede verlo —sus dos mitades
          salen del mismo `viewModel()`, así que mutar la REGLA es equivalente para él (mutar la
          TRADUCCIÓN sí lo caza, `#77`)— y los casos Livewire ejercen la guarda PROPIA del componente.
        · **Cobertura escrita ANTES de clasificarlo**: tres casos en `Api\V1\CatalogAddonsTest`, con
          sus fronteras y verificados por mutación. **La cobertura equivalente no siempre existe: a
          veces hay que escribirla.**
      · ✅ **`DepositSurfacesTest` clasificado y adelgazado** (`DECISIONES #91`): de 6 usos a **4**.
        Contador sin cambio —los cuatro de UI mueren y ·2b·3 opera DENTRO—.
        · **Los dos casos del canario, retirados por redundantes**: `cartDepositCents()` es
          literalmente `$this->quote()->onlineAmountCents`, un intermediario de `CartPricing`.
        · Medido en las dos direcciones: mutar `onlineDueCents()` tumba **14** casos, y los que hacen
          esa pregunta sobreviven (`CartPricerTest`, `DepositChargeTest`). Repetida **sin** los
          canarios siguen cayendo **12**: cobertura intacta.
        · ⚠️ **El primer corte se llevó UN canario, no dos** —el segundo iba después del caso del
          email— y el docblock ya afirmaba que los dos estaban fuera. **Al cortar por rangos, cuenta lo
          que queda; el texto se escribe con el resultado, no con la intención.**
      · ✅ **`PurchaseLimitsTest` clasificado: muere con el componente** (`DECISIONES #92`). Sus reglas
        sobreviven —son de dominio— pero él las ejerce conduciendo la UI, y cada una tiene guarda donde
        se queda: tope de líneas (`OrderCreatorTest`, `CartLineValidationTest`), tope de pendientes
        (SIETE supervivientes) y límite de frecuencia al crear (NUEVE).
        · ⚠️⚠️ **Y una mutación mal apuntada me hizo creer que había un agujero de abuso**:
          `ReservationAdmissionPolicy` tiene DOS `tooManyAttempts` —reintento y creación— y la
          sustitución posicional cambió el primero. Todo encajaba con la hipótesis equivocada porque
          caían justo los casos de reintento, que eran los que había roto. **Tercera cara de la regla:
          cuando el ancla se repite, exige que sea ÚNICA antes de creerte el resultado.**
        · ⚠️ **Dos casos quedan SIN medir a propósito** y anotados en el fichero, para que ·2b·3 no los
          borre a ciegas: el del usuario sin verificar y el del correo hasta que la pasarela autoriza.
      · ✅ **Los dos cabos de `#92`, cerrados** (`DECISIONES #93`), y uno era otro hueco real:
        · **el correo hasta que autoriza**: lo guardan SIETE supervivientes
          (`RedsysNotificationEndpointTest` ×3, `RedsysReturnHandlerTest` ×4). Se va sin pérdida.
        · ⚠️ **pagar sin verificar el correo: NO lo guardaba nadie** — añadir un
          `abort_if(! $user->hasVerifiedEmail(), 403)` al endpoint dejaba los 2713 casos en verde. Es
          una decisión de producto (el pago auto-verifica la cuenta) que vivía escrita solo en el
          fichero condenado. Fijada en `OrdersTest`, verificada por mutación.
        · **La lección, segunda vez** (`#89` fueron tres reglas): **un caso «sin medir» en un fichero
          condenado es deuda con fecha de caducidad**. Hay que cerrarlos ANTES del borrado.
      · ✅ **`PurchaseRetryAndPollingTest` clasificado: muere con el componente** (`DECISIONES #94`).
        Es el que más dinero toca de los que quedaban, así que se midieron sus dos reglas críticas:
        · **`gateway_order` nuevo en cada reintento** (reusarlo = firma duplicada = cobro rechazado):
          tumbarlo cae **10** casos, **9 supervivientes**;
        · **defensa IDOR del reintento**: cae **5**, **4 supervivientes**.
        · El resto —ventana del hold, los tres desenlaces del sondeo, el motivo del rechazo— lo cubre
          `SidebarOutcomeParityTest` desde `#85`. Sin equivalente solo `addAnother`: superficie pura.
        · **Resultado NEGATIVO, y se anota**: aquí **no había hueco**. El terreno del dinero se
          endureció solo en Fase 3, así que los ficheros que solo lo CONDUCÍAN desde la UI son los de
          menos riesgo al retirarse. **Medir para no encontrar nada sigue siendo medir.**
      · ✅ **`PurchaseRegistrationPromptTest` y `PurchaseIdentificationTest` clasificados: mueren**
        (`DECISIONES #95`). El primero **ya es redundante hoy** —`PublicConfigTest` cubre sus cuatro
        reglas, y su caso de URL hostil prueba CUATRO esquemas frente a uno—. El segundo conduce el
        modo `embedded` de `Auth\Register`, que se queda sin usuario con el Blade; sus reglas viven en
        `AuthRegistrationTest`, `OrdersTest` y **el caso que nació en `#93`**.
        · ⚠️ **Mutante equivalente POR DISEÑO, anotado para no repetirlo**: mutar `SelfSignup` para
          intercambiar las dos respuestas del alta deja la suite verde, pero eso **es** la
          anti-enumeración. **Una propiedad de INDISTINGUIBILIDAD no se prueba mutando una rama: se
          prueba comparando las dos respuestas entre sí.** Cuarta cara de la disciplina, con `#77(e)`,
          `#90(f)` y `#92(a)`.
      · ✅ **`PurchaseCatalogGroupingTest` y `SidebarV2Test` clasificados: mueren** (`DECISIONES #96`).
        El segundo es el más grande del inventario (17 casos) y **el que menos deja**: sus tres piezas
        —modo, banda y pie— tienen dueño propio con su red desde `#71`, `#80` y `#82`.
        · ⚠️ **Tercer hueco del tramo: el `type` publicado del catálogo no lo fijaba nadie.** Cruzar
          entrada↔pack en `CatalogReader` dejaba `CatalogTest` en verde. **El tipo es la SECCIÓN en la
          que aparece cada producto.** Fijado en `CatalogTest::test_each_product_publishes_its_own_type`.
        · **Los tres huecos del tramo son del mismo tipo** (`#89`, `#93`, `#96`): campos que el
          servidor PUBLICA y que solo probaba el motor que se va. **Criterio de búsqueda para lo que
          queda: si un dato viaja al cliente y su único test conduce la UI, el contrato no lo fija.**
      · ✅ **`ReservationPauseTest` clasificado — y con él el tramo `·C` queda CERRADO**
        (`DECISIONES #98`). No es homogéneo: sus seis casos de `MaintenanceSettings` se quedan y los
        cinco `sidecart_*` mueren, cubiertos por `SidebarPausedParityTest` desde `#81` (medido: quitar
        el WhatsApp de `BookingStatusResource` tumba tres casos y **dos sobreviven**).
      ▶ **BALANCE DEL TRAMO: once ficheros, contador 21 → 20 — la cifra esperada**, porque `#86` ya
        midió que la mayoría muere con el componente. Lo que produjo de verdad: **tres huecos reales
        cerrados** (`#89` las reglas `can_*`, `#93` pagar sin verificar, `#96` el `type` del catálogo),
        **un falso positivo evitado** (`#92`) y **cuatro caras de la disciplina de mutación**
        (`#77`, `#90`, `#92`, `#95`).
      · ✅ **`#74` RESUELTO y `SidebarTokenBudgetTest` FUERA del inventario** (`DECISIONES #99`).
        20 → **19**, el primer movimiento del tramo. El ámbito CSS pasa a definirse por **familias**
        del cajón en vez de rascando `purchase.blade.php` —la plantilla que ·2b·3 borra—, así que
        deja de depender del motor. Excluye a propósito `auth__`/`form__`/`pwd-`, compartidos con el
        modal de la cabecera y `/mi-cuenta` (eso es Fase 5).
        · **Los números NO son comparables**: ámbito 1.073 → **1.127**, tokenización 75 → **71 %**,
          crudos 3 → **6**. Cambió lo que se mide, no la calidad. Escrito en las dos constantes.
        · **Los seis crudos quedan NOMBRADOS**, no escondidos en un número, y **no se tokenizan aquí**:
          cambiar un color cambia píxeles y exige verificación visual (DoD §4). Ficha en `DEUDA.md`.
        · **Y el caso pasa a ser un trinquete de verdad**: se llamaba `..._only_shrink` y aseveraba con
          `<=`, así que tokenizar uno no obligaba a bajar el número. Ahora `assertSame`, verificado en
          las dos direcciones.
      ✅ **LAS ENTRADAS DEL INVENTARIO ESTÁN CLASIFICADAS.** Es la condición terminal de `#86`:
        ·2b·3 puede borrar el componente y, con él, todas las que mueren.

- [x] **Paso 4.7·2b·3 — borrar el componente** (✅ **HECHO** el 2026-08-21, `DECISIONES #111` y
      **`#112`**; desbloqueado el 2026-08-20 por `#110`, que cerró los cuatro caminos de navegador que
      `#100` exigía). **Arrastra `4.7·3`**: el flag se fue en el mismo movimiento, porque dejarlo sin
      su segunda rama no tenía sujeto.
      **Borrado**: `Purchase.php` (1.904 líneas), `purchase.blade.php` (713),
      `purchase-placeholder.blade.php`, `SidebarSettings.php` y la bifurcación de `layout.blade.php`.
      **El cajón SPA queda como motor ÚNICO.**
      ▶ **Sus dos decisiones propias, resueltas**: `SidebarEngineTest` → renombrado a
      **`SidebarMountTest`** (mueren los tres casos del flag y el que comparaba motores; quedan seis
      sobre lo que el SERVIDOR le pone al cajón) · el modo **`embedded` NO se retira**: en producción
      no lo monta nadie, pero es la REFERENCIA viva de `SidebarLoginParityTest` y
      `SidebarRegisterParityTest`. Lo que sí se retiró es `Register::requestSwitchToLogin()`, que se
      quedaba sin oyente **y ya era inalcanzable antes** (`#112(f)`).
      ⚠️⚠️ **Lo que hay que llevarse de este paso NO es el borrado, son las TRES guardas que estaban
      en verde sin medir nada** —y ninguna se habría visto sin mutar (`#112`)—:
      · `assertSee('livewire.js')`, que `ESTADO` daba por la guarda crítica, era **inerte**: dos
        fuentes redundantes (la directiva y la auto-inyección de Livewire). Trampa 4 de `§3.quater`.
      · **dos tests de SEGURIDAD** de la vuelta de Redsys quedaron inertes por el propio borrado: el
        layout consume la sesión al pintar, así que su `assertNull(session(...))` es cierto pase lo
        que pase. Medido desactivando la comprobación de titularidad: seguían verdes.
      · y el efecto sistémico: **`assertSee(__('tickets.*'))` contra página completa es VERDE FALSO**,
        porque el montaje incondicional lleva el grupo `tickets` entero en cada página. 13 claves
        vacuas en 5 ficheros, convertidas a `assertSeeText`/`assertDontSeeText`.
      ⚠️ **Y los contadores se MIDEN, no se ajustan**: los seis de `ModuleContractsTest` se pusieron a
      `999` para leer el valor real en el fallo.
      · **Medido en ·b1 y confirmado ahora**: fuera de `tests/`, el único acoplamiento EJECUTABLE al
        componente era `layout.blade.php`. El resto que lo nombra en `app/` son docblocks de
        procedencia; se corrigieron **solo los que hablaban de él en PRESENTE**, y uno resultó falso
        de antes (`OrderCreator` citaba un `#[Locked]` que nunca se aplicó, `#112(g)`).
      ⚠️ **Deuda NUEVA que destapó**: el cajón se abre **sin feedback de carga** (`#112(h)`).
- [x] **Paso 4.7·2b·3·0 — independizar el contrato de árbol ANTES de borrar** (2026-08-21,
      `DECISIONES #111`). Cuatro entregas verdes sobre `main`.
      ⚠️ **El orden es contraintuitivo y es lo que hay que llevarse de aquí**: `SidebarDomContractTest`
      sacaba sus fixtures **del motor Livewire que él mismo comparaba** —65 usos de `$component->`—.
      Con los dos motores vivos el `assertSame($livewire, $vue)` lo tapaba; al quedar uno, la
      circularidad se vuelve invisible y el manifiesto congelaría lo que Vue emitiera ese día. Por eso
      se independiza PRIMERO, que es la única ventana en la que se puede comprobar que el fixture
      declarado produce el mismo árbol que el derivado.
      · Cada valor se midió instrumentando el helper ANTES de sustituirlo, y cada entrega se mutó. La
        validación cazó **cuatro errores propios al vuelo**.
      · **Regla que salió de mutar**: el diff ve ESTRUCTURA, no valores — **con excepciones que solo
        aparecen mutando una a una** (el día del calendario y los topes del selector SÍ son contrato).
      · Y **cuatro nombres de campo estaban supuestos mal**: `online_amount_cents < total_cents`,
        `guest_form_pending`, `pending_at_gate_cents`, `reservations_paused`.
- [x] **Paso 4.7·2b·4 — los DIBUJOS de los iconos** (2026-08-21, `DECISIONES #113`). Sub-paso que no
      estaba en ningún plan: lo destapó el owner mirando el cajón. **Los 20 `<svg>` estaban VACÍOS**
      —envoltorio sin dibujo— desde la transcripción de Fase 4, así que el cajón se servía sin un solo
      icono. No lo rompió la retirada; la retirada lo **destapó** (en local el default `livewire`
      servía los del Blade; en staging llevaba roto desde el flag, y `#110` verificó cuatro caminos de
      navegador sin verlo).
      ⚠️ **Ningún gate podía cazarlo, y estaba escrito**: el diff de árbol **no desciende dentro de un
      `<svg>`**, de modo que uno vacío y uno lleno son el mismo nodo. Comprobado: la corrección no
      cambió ni un byte del manifiesto congelado.
      ▶ Transcrita la geometría de los 20 desde sus `<x-icons.*>`, y **guarda nueva**:
      `SidebarIconParityTest`, formulada como «**el cajón no inventa dibujos**» —cada geometría es la
      de un componente del sistema de diseño o está declarada como propia con su motivo—, que es lo
      que la hace inmune a reordenar ficheros. Mutada tres veces: vaciar un icono, cambiar el original
      en Blade y estrenar un dibujo sin declararlo; los tres, rojos.
      ⚠️ Costaron **7,26 KiB** (medido con y sin ellos): el chunk pasa de 148,24 a 155,50 KiB y el techo
      de `SidebarBundleBudgetTest` sube 150 → **160**, con el matiz que importa: no es una función
      nueva que presupuestar, es **la que ya se creía entregada**. De paso quedó medido que 2,36 de
      esos KiB son duplicación literal — oportunidad anotada con cifra.
- [x] **Paso 4.7·3 — retirar el flag** (✅ **HECHO** el 2026-08-21, dentro de `·2b·3`): `SidebarSettings`,
      el ajuste y su fijación en el fixture, fuera.
      ⚠️ **[DECIDIDO 2026-08-19] Este era el paso que ACTIVA el motor SPA, no `·2b·3`** — y por eso
      acabaron siendo el mismo commit: con el default y el fallback en `livewire`, borrar el componente
      dejaba el cajón vacío (camino A) o rompía el caso hermano (camino B), así que separarlos habría
      significado dejar `main` roto entre los dos. Heredaba entera la condición de `#100`, cumplida el
      2026-08-20 (`#110`). Los dos casos de `SidebarEngineTest` que comparaban motores se fueron con él.
- [x] **SPA embebida (Vue 3 + Pinia) para el cajón completo** — HECHO: los ONCE pasos (fecha/hora,
      cesta, login/registro, pago, vuelta y reintento) con Vue 3.5 y Pinia 3.0, y verificado de punta a
      punta con navegador y la pasarela REAL (`#59`). **Primer consumidor real de la API v1**, cumplido.
- [x] **Paridad funcional ANTES de retirarlo, con feature-flag por instalación** — HECHO: `sidebar.engine`
      (default `livewire`) permite comparar los dos motores en vivo, y la paridad son el diff de árbol
      contra manifiesto congelado (`#60`) más trece paridades de datos, textos e importes.
      ✅ **Cerrado del todo**: Turnstile entregado (4.4b·2, `#108`), los caminos de navegador
      verificados en staging (`#110`) y el flag RETIRADO con el componente (`#112`) — hoy no hay dos
      motores que comparar porque solo queda uno.
- [x] **El cajón como ÁREA DE CLIENTE** (`DECISIONES #66`, decisión del owner) — ✅ **HECHO** el
      2026-08-22, en TRES tandas (`#120`). La gestión del cliente vive dentro del cajón: sus entradas
      y reservas, y las cinco gestiones de cuenta. De los TRES sitios en que estaba repartida quedan
      **DOS**: `/mi-cuenta/…` se retiró (`#120(u)`) y el **modal de auth de la cabecera** sigue siendo
      la puerta de quien no tiene sesión — con ficha propia en `DEUDA.md`, porque no era de ninguna
      tanda.
      ✅ **El terreno se preparó a propósito antes de empezar** (`#119`, 2026-08-22): tres capas
      (módulos planos · nueve stores · componentes), el embudo fuera de la raíz como SECCIÓN y su grafo
      CERRADO con guarda, para que las pantallas de cuenta entren al lado y no dentro.
      ✅ **El DISEÑO está escrito** (`docs/specs/area-cliente.md`, 2026-08-22): modelo de navegación
      (índice + zonas libres con pila de retorno, **no** el grafo del embudo), zonas, contrato de datos
      y plan de verificación. 🟦 Pendiente de que el owner valide §3.1 y §3.3 (`DECISIONES #120(d)`).
      ⚠️⚠️ **Y el diseño destapó que «el servidor ya está» solo vale para LEER** (`#120(a)`, medido
      endpoint por endpoint): ✅ `/auth/*`, `/me`, `/me/orders`, `/me/reservations`,
      `/me/reservation-eligibility` y el post-form por firma; ❌ **cero endpoints** para perfil,
      contraseña, otras sesiones, borrado y export RGPD —solo `App\Livewire\Account\*`—. Por eso va en
      TANDAS: **1 = solo lectura** (mis reservas + mis pedidos), 2 = las gestiones (abre API nueva),
      3 = la retirada de `/mi-cuenta/…`, que el owner ha decidido que **desaparece** (`#120(c)`).
      ⚠️ **Aquí la red NO es el diff de árbol** (`#120(e)`): el embudo fue una transcripción con un
      original que copiar y esto no lo tiene —una página ancha no es un panel estrecho—. La red es la
      paridad de DATOS y de REGLAS más el NAVEGADOR.
      - [x] **Tanda 1 · paso 1 — EL NIVEL SECCIÓN** (✅ **HECHO** el 2026-08-22). El cajón deja de tener
            una sola sección: `section.js` (módulo plano) + `stores/section.js`, la raíz enruta —compra
            con `v-show`, cuenta con `v-if`— y **sube a ella el puente de `mode`/`identifying`**, que
            desde hoy depende de la sección además del paso. Nace el modo **`account`** con su regla
            CSS y el armazón `AccountSection.vue` sobre `Shell` (9 líneas de código).
            ⚠️ **Tres guardas EXISTENTES mordieron y las tres tenían razón**: el presupuesto de
            componentes (`PurchaseSection` 438 → **431** al soltar el puente), el presupuesto de bundle
            (`<KeepAlive>` costaba **2,3 KiB** y él solo lo hacía saltar → se retiró, `#120(g)`) y la
            poda del payload del montaje (`SidebarLoginParityTest`, que exigía declarar la clave nueva).
            ✅ Verificado en NAVEGADOR (`VERIFICACION-E2E-CAJON.md` **V4**): memoria del embudo intacta,
            **0 peticiones al conmutar**, `.acct` colapsado y **no alcanzable con Tab**, la cadena flex
            del panel entera y **0 fugas de foco** al árbol oculto (12 focusables visibles → 0 ocultos).
      - [x] **Tanda 1 · paso 2 — LA NAVEGACIÓN DE ZONAS** (✅ **HECHO** el 2026-08-22).
            `account/navigation.js` (zonas, pila de retorno con recorte, rótulos) + `stores/account.js`,
            la sección enruta zonas y nacen `AccountHomeZone` y `OrdersZone`. **26 casos de
            `node --test`, verificados por MUTACIÓN**; navegador **V5, 7/7 y 0 peticiones**.
            ⚠️ **Son DOS zonas y no tres**: `account.orders.title` es literalmente «Mis reservas»
            —el cliente no distingue pedido de reserva— y `/me/reservations` alimenta el bloque de
            cuenta, no una pantalla (`#120(i)`).
            ⚠️⚠️ **Y destapó cuatro aserciones tocadas, tres de ellas en VERDE FALSO** — más una que
            llevaba **inerte desde `#231 p8`** y que ni `assertSeeText` arreglaba: «Mi cuenta» sale
            dos veces en `/mi-cuenta` (título y footer), así que solo la mide una aserción
            estructural (`#120(h)`, `TESTING.md` §2.ter ampliada).
      - [x] **Tanda 1 · paso 3a — EL CONTRATO DE PRESENTACIÓN** (✅ **HECHO** el 2026-08-22). La API
            publicaba las fechas CRUDAS y el cliente **no puede** componer sus etiquetas: `Intl` no
            reproduce en español lo que compone Carbon, y la del pedido lleva la **zona horaria de la
            instalación** —un ajuste del panel—. Nacen `date_label`, `created_label`,
            `refunded_label` y `guest_form_url`, con el contrato (`openapi/v1.yaml`) actualizado.
            ⚠️⚠️ **Y destapó que la fórmula del rótulo estaba COPIADA en cuatro superficies
            públicas** —bloque de cuenta, página «Mis reservas», post-form y correos—: nace
            `DisplayTime::dayLabel()` como fuente única y `DayLabelSingleSourceTest` la vigila, con
            excepciones **con motivo** y **mutada tres veces** (`#120(j)`).
            ⚠️ La deuda de fechas del EMBUDO **no se cierra aquí a propósito** —mezclaría dos
            trabajos—, pero queda anotada como más barata y más urgente en `DEUDA.md`.
      - [x] **Tanda 1 · paso 3b — LOS DATOS EN EL CLIENTE** (✅ **HECHO** el 2026-08-22).
            `account/orders.js` compone las filas, `stores/orders.js` y `stores/reservations.js` piden
            —**«solo si no hay datos»**, que es la regla que sustituyó a `<KeepAlive>`— y las dos zonas
            pintan: índice con la próxima reserva y «Mis reservas» con su detalle, señal, post-form,
            reintento y paginación. **38 casos de `node --test`**, mutados uno a uno.
            ✅ **`SidebarAccountParityTest`**: el módulo ejecutado en Node contra la **respuesta REAL**
            de `GET /me/orders` —no contra la página condenada, que es el error que `#67` corrigió—,
            con las expectativas compuestas por los MISMOS servicios de PHP que usa la web.
            ⚠️ Y enseñó algo que nadie había escrito: **`paid_online_cents` incluye los complementos**
            (68,00 € y no 60,00 en el aviso de señal). Recomponerlo en el cliente habría dado 60.
            ✅ Navegador **V6, 2/2** con datos reales y **exactamente dos peticiones**.
            ⚠️⚠️ **Los textos del área viajan SOLO con sesión**: un invitado no puede abrir esa sección,
            así que sus ~700 B eran desperdicio en la ruta de más tráfico (`PERF-02`). Medido: anónimo
            **1.608 B**, con sesión **2.309**.
            ⚠️ Y volvió a morder `TESTING.md` §2.ter —ahora **predicho** por la propia nota—: dos
            aserciones de página más, convertidas y **mutadas**.
      - [x] **Tanda 1 · paso 4 — LA PUERTA** (✅ **HECHO** el 2026-08-22). «Mis reservas» del bloque de
            cuenta abre la SECCIÓN en vez de navegar, cruzando Blade/Livewire → Alpine → Vue.
            ⚠️ **El `href` se conserva y no es decorativo**: entre abrir el panel y que el `import()`
            del motor acabe hay una ventana real con `spaHandle` a `null`. Verificado **cortando el
            chunk del motor** en el navegador (`V7`): el enlace lleva a `/mi-cuenta/pedidos`. Sin él
            sería un botón que «no falla, no hace nada» — `#117` otra vez.
            ✅ `AccountDoorWiringTest` vigila los CUATRO eslabones y el HTML servido, mutado uno a uno.
            ⚠️⚠️ Y destapó que **`SidebarAccountVisibilityTest` se había quedado corto en el paso 1**:
            su título decía «EXACTAMENTE los del proceso de compra» mientras la regla del CSS ya
            cubría `is-account`, que no lo es. Pasaba **por omisión**. Corregido y mutado.
      - [x] **Tanda 1 · paso 5 — LA CAPTURA DE HUECOS** (✅ **HECHO** el 2026-08-22). `#111` dice
            *independizar el contrato ANTES de borrar*, y esto lo hace **ejecutable**:
            `AccountPageCaptureTest` inventaría lo que la página pinta y lo clasifica en tres —lo que
            la API ya publica (se asevera equivalencia), los **HUECOS con nombre** (lista que **solo
            encoge**) y lo que está fuera **a propósito** (`event_data`, art. 9)—.
            ▶ **Cuatro huecos medidos**: el resto de señal por producto, el desglose de puerta con su
            etiqueta, el total final tras los cambios y el pendiente de devolución. **La lista vacía
            es la condición 1 de la tanda 3.**
            ⚠️ Se sondea **por VALOR y no por nombre de campo** —publicarlo con otro nombre dejaría un
            test verde mintiendo—, y lo que no se puede sondear así se **declara aparte** en vez de
            fingirlo: a eso lo cubre la **congelación del esquema `Order`** del contrato.
            ⏳ **El fichero muere con la página**, y lo dice en su primera línea.
      - [x] **Tanda 2 · paso 6 — CONTRASEÑA Y OTRAS SESIONES** (✅ **HECHO** el 2026-08-22, 6a+6b).
            La lógica baja a `Identity\Services\AccountCredentials` —con el limitador que la web no
            tenía—, nacen `PUT /me/password` y `POST /me/sessions/revoke-others`, y sus dos pantallas
            entran en el cajón. Navegador `V8`: **5/5 + 3/3**.
            ⚠️ **La web heredó el limitador sin tocar la web** (`#120(o)`): dos de los cuatro sitios
            que reconfirman contraseña, cubiertos de golpe. Ésa es la prueba de que bajar la lógica al
            dominio no era ceremonia.
            ⚠️ Y cazó dos duplicaciones antes de que crecieran: la **política de contraseñas** (seis
            copias, `#120(n)`) y el **campo de contraseña con su toggle** (dos, y este paso iba a
            hacer cuatro) — extraído sin cambiar **ni un nodo** del árbol, verificado por las
            paridades (`#120(p)`).
      - [x] **Tanda 2 · paso 7a — EL PERFIL, en el servidor** (✅ **HECHO** el 2026-08-22).
            `AccountProfile` reúne la gestión más grande: reglas con **doble** unicidad —también el
            `pending_email` de otros—, el ciclo entero de `pending_email`, las **dos** notificaciones
            y la carrera de UNIQUE. Nacen `PATCH /me`, `DELETE /me/pending-email` y
            `POST /me/pending-email/resend`. **11 casos, 6 mutaciones.**
            ⚠️ **Tres guardas de arquitectura mordieron** (`#120(q)`): `ApiBoundariesTest` (el método
            se llamaba `update` → `apply()`), `ModuleBoundariesTest` (el servicio importaba un
            middleware → la lista de idiomas baja a `Platform\Services\SiteLocales`) y `MeTest` (la
            lista CERRADA de campos de `/me`).
            ⚠️ Y dos veces habría cambiado producción escribir de memoria: `maskEmail()` reescrita
            salía distinta, y al moverla **desapareció del componente** con un controlador usándola —
            lo cazó la suite.
      - [x] **Tanda 2 · paso 7b — LA PANTALLA DEL PERFIL** (✅ **HECHO** el 2026-08-22). Zona
            `PROFILE` con el ciclo del correo pendiente; navegador `V9`, **7/7**.
            ⚠️⚠️ **El techo de componentes obligó al rediseño que tocaba** (`#120(r)`): la sección
            estaba en **38/40** porque conocía los datos de sus zonas —un `watch` con cadena de `if` y
            un `computed` por lista, los dos creciendo con cada pantalla—. Hoy **cada zona pide y
            compone lo suyo** y la sección bajó a **20**. No costó ni una petición: `ensure()` ya
            garantizaba que volver a entrar no repitiera nada.
            ⚠️ Dos duplicaciones más cazadas: `credentials.js` → `form-outcome.js` (lo usan tres
            pantallas) y los **tres idiomas quemados en el marcado** → `SiteLocales::options()`.
      - [x] **Tanda 2 · paso 8 — LOS DOS DERECHOS RGPD** (✅ **HECHO** el 2026-08-22). Nace
            `Identity\Services\AccountPrivacy` con el borrado (art. 17) y el documento de
            portabilidad (art. 20); nacen `DELETE /me` y `GET /me/export`, y la zona `PRIVACY` los
            pinta. **La tanda 2 queda CERRADA**: las cinco gestiones están en el cajón.
            ⚠️ **El export exigió un CONTRATO nuevo** (`#120(s)`): su composición vivía en la capa de
            ENTREGA —exenta del grafo— y al bajarla a Identity habría tenido que recorrer `Order`,
            `OrderItem`, `Slot` y `TicketType` a mano. Nace `Booking\Contracts\CustomerOrderHistory`,
            hermano de `CustomerReservations`.
            ⚠️ **La guarda que sostiene el paso** es que la descarga de la web y `GET /me/export`
            sirven el MISMO documento, comparado campo a campo: sin ella, la duplicación que este
            paso evita podría volver sin que nada la delatara.
            ⚠️⚠️ **Y salieron tres cosas de medir, no de leer**: el export publicaba `tickets[].code`
            —**columna que no existe**, `null` desde el commit fundacional—; el limitador de la web
            estaba puesto y **su aviso no se pintaba en ninguna parte** (tres vistas sin banner
            `_global`, `#117` otra vez); y el propio `SidebarComponentBudgetTest` contaba **dos de
            los cinco verbos** de `api.js`, así que un `api.delete()` en un componente pasaba la
            guarda sin que faltara nada.
            ✅ Navegador `V10`, **17/17** — y ahí apareció que tras borrar desde el cajón **la home
            salía muda**: la despedida se deja ahora en la sesión nueva, después de `invalidate()`.
      - [x] **Tanda 3 · pasos 9, 10 y 11 — LAS CONDICIONES DE ENTRADA** (✅ **HECHO** el 2026-08-22,
            `#120(t)`). La tanda no empieza borrando: primero se publica lo que solo sabía la página.
            ✅ **Paso 9**: `Order` gana `pending_at_gate_lines`, `total_final_cents`,
            `pending_refund_cents` y `has_deposit` — los **cuatro huecos cerrados**. Nace
            `Order::gateBreakdownLines()` porque la etiqueta del resto de la señal **se componía en
            Blade** y la API habría sido su segunda copia.
            ⚠️ **`AccountPageCaptureTest` se BORRÓ, y lo pedía él mismo**; lo releva
            `AccountFinancialParityTest`, que compara los DOS lados mientras convivan.
            ⚠️ **El cuarto hueco, declarado «no sondeable», sí lo era**: lo que faltaba no era una
            sonda mejor sino una fila de `payments` pagada en el fixture.
            ✅ **Paso 10**: el ledger dentro del cajón, con el desglose plegado. No calcula ni un
            importe: los seis los publicó el paso 9.
            ✅ **Paso 11**: `GET /me/consents` y la lista en la zona `PRIVACY` — **sin la IP**, que
            viaja solo en el export. El rótulo del documento lo publica el servidor.
            ✅ Navegador `V11`, **22/22**: el cajón y la página dicen los mismos seis importes.
      - [x] **Tanda 3 · EL BORRADO** (✅ **HECHO** el 2026-08-22, `#120(u)`). Mueren las VISTAS
            —`resources/views/account/`, los cuatro componentes Livewire de cuenta y
            `RetryPaymentController`— y **viven las RUTAS** como puerta que abre el cajón en su zona
            (`Http\Sidebar\AccountDoor`, el patrón de `/entradas`). **8 correos ya entregados** y
            **11 redirecciones** siguen aterrizando donde deben.
            ⚠️⚠️ **La AUDITORÍA fue la mitad del trabajo y encontró DOS huecos reales**
            (`CONVENCIONES §3.quater`, mutando): el **reintento de la API no tenía techo vigilado**
            —quitarle el `throttle` dejaba la suite entera verde— y **la API publicaba las líneas
            FANTASMA** que la página, el panel y el PDF ocultan. Los dos, cerrados con su caso.
            ⚠️ Y un tercero que decidió el owner: las **respuestas del pack** que la página pintaba en
            línea se piden ahora **bajo demanda** (`GET orders/{code}/event-data`), que es para lo que
            ese endpoint existe.
            ▶ **63 casos menos y ninguno perdido por descuido**: los que conducían la vista mueren,
            los que la usaban de intermediario se **re-apuntan** —incluidas las tres vías de `RGPD-06`
            que pasaban por Livewire— y los que afirmaban del dominio se **extraen**
            (`OrderRetryEligibilityTest`, `MeOrdersFinancialsTest`).
            ✅ Navegador `V12` **13/13** y `V13` **6/6**. ⚠️ Y `V12` cazó el fallo de siempre: la zona
            se aplicaba en `open()`, y **el cajón que llega por una puerta nace abierto**.
      ⚠️ **Lo que el área de cliente NO se llevó** —quedó fuera de las tres tandas a propósito, con
      ficha propia en `DEUDA.md`— eran DOS cosas, y hoy queda UNA:
- [x] **La AUTH dentro del cajón** (`DECISIONES #122`) — ✅ **HECHO** el 2026-08-23, en diez pasos por
      dependencia (`specs/auth-en-cajon.md` §8). Entrar, darse de alta y recuperar contraseña son
      zonas del cajón; el **modal de la cabecera está retirado**; las tres rutas sobreviven como
      PUERTAS; y el paso 5 del embudo gana «he olvidado mi contraseña» con vuelta a la compra.
      **Validado por el owner en navegador**, en dos pasadas.
      ▶ **Su valor no fue el código sino lo que midió**: la revisión adversarial paró dos bloqueantes
      —los textos del área viajan solo con sesión; el contexto del alta estaba quemado— y la auditoría
      de los 36 tests del modal destapó **un hueco de seguridad vivo** (el desenlace de pago de otra
      persona sobrevivía a un login en dispositivo compartido) más dos huecos de guardia.
      El método —clasificar **mutando**, no leyendo— está en `#122(j)`.
- [x] **El BLOQUE DE CUENTA, a Vue — el último Livewire del layout** (2026-08-23, `DECISIONES #123`;
      diseño en `specs/account-context-vue.md`, ✅ EJECUTADO y validado por el owner en navegador).
      El bloque `.acct` lo pinta `sidebar/account/AccountPanel.vue`, teletransportado desde la raíz al
      hueco que emite el layout; la clase Livewire y su Blade se retiran y la página sirve **0
      atributos `wire:`**. Nace `GET /api/v1/me/account-context` —el agregado que la web ya componía y
      la API no publicaba— y la **semilla del montaje sale del MISMO Resource**, así que el store ve
      una sola forma venga de la carga de página o del refresco.
      ⚠️ **Livewire NO se retira**: sigue trayendo Alpine, así que `@livewireScripts` se queda. Lo que
      cambia es que pasa a ser la **fuente única** y su guarda **por fin discrimina** — medido: hasta
      ese día el caso pasaba igual sin la directiva.
      ⚠️⚠️ **Y lo que encontró vale más que lo que migró.** La revisión adversarial ×3 declaró el
      diseño **INSUFICIENTE** y destapó dos cosas que nadie vigilaba: el **`no-store` de TODAS las
      páginas web lo ponía un accidente de Livewire** —un hook de componente encendía el flag que un
      middleware global del paquete usaba, así que retirar el último lo habría borrado del sitio
      entero **con la suite en verde**, porque ninguna de sus 12 aserciones miraba una página del
      layout— y **`route('logout')` aparece UNA sola vez en toda la aplicación**, dentro del bloque
      que se retiraba (lo que desmiente la premisa escrita de `#120(t)`). De propina,
      `SidebarIconParityTest` estaba **ciego a 10 de los 32 `.vue`**.
      Suite **2669 verde** · **624 tests JS** · chunk 207,2/208 KiB · la home anónima adelgaza
      **−1.412 B por visita**.
- [x] **El PULIDO del cajón, y lo que destapó** (2026-08-23, a petición del owner tras validar el
      relevo en navegador). Nueve puntos, de los que **cuatro no eran cosméticos**:
      · **el bloque de cuenta reaparecía al reabrir el cajón** — `close()` escribía el modo del panel
        a pelo, saltándose el puente del motor, y el `watch` no lo corregía porque nada reactivo
        cambiaba. Era herencia del motor Livewire, donde reabrir provocaba un round-trip;
      · **«Mis reservas» se servía SIN TARJETAS y privacidad con sus dos derechos pegados**: la
        transcripción a Vue **inventó nombres de clase** (`orders__card`, `orders__pagination`,
        `bk-error`, un `auth` de envoltorio) y **ninguno tenía una sola regla**. Las reglas existían
        desde siempre con el nombre de la página retirada. Nace `SidebarStyleWiringTest`;
      · **el scroll del cajón arrastraba la página**: el bloqueo solo alcanzaba al `<body>` y
        `overscroll-behavior` **no aparecía ni una vez en el repo**;
      · **el título de las pantallas de auth salía DOS veces**, porque reutilizan el formulario del
        paso 5 —que trae el suyo— y el armazón ponía además el de la zona, con el mismo literal.
      ▶ Y lo demás: los tres botones del bloque (reservas · cuenta · salir solo icono), **cinco iconos
      en el índice** con cuatro componentes nuevos del sistema de diseño, un spinner en las cuatro
      zonas que piden datos —el velo existía y el área nunca lo cableó— y las pestañas de auth
      **ocultas durante la verificación**, que era la forma accidental de destruir esa pantalla.
      ⚠️ **La defensa de PII que borra el correo pendiente NO se tocó**: está decidida por escrito.
      Suite **2678 verde** · **633 tests JS** · chunk 210,8/211 KiB.
- [x] **`#125` · Recorrer el guion de navegador que `#122` y `#124` dejaron pendiente** (2026-08-23).
      `V17`, `V18` y `V23`: **60 comprobaciones, 58 verdes y 2 rojas**, y las dos rojas eran fallos
      reales — el reenvío del correo prometía envíos que el servidor tiraba (4 reenvíos, 2 correos) y
      el velo de la zona de privacidad no se pintaba nunca—. Los dos arreglados y **60/60**.
      ⚠️ **Su lección transversal**: la guarda del reenvío **existía, cruzaba las dos fuentes y estaba
      bien razonada**, y aun así leía el limitador equivocado. Al auditar una guarda la pregunta no es
      «¿mira algo?» sino **«¿mira TODO lo que hay ahí?»**.
- [x] **`#126` · «Mis reservas» se lista POR RESERVA, con el pasado en su propia pantalla**
      (2026-08-23, encargo del owner). Endpoint `GET /me/reservations/{scope}` con los **dos ámbitos
      como los dos lados de UN predicado** —si se separan, una reserva puede no salir en ninguna de las
      dos pantallas y eso no falla, no avisa y no se ve—. Suite **2704** · **648 tests JS** ·
      **16/16** en navegador · chunk 213,57/214,5 KiB.

### Fase 5 — Capa de contenido profesional ⬜
✅ **DESBLOQUEADO el 2026-08-25** (`DECISIONES #137`): su primer punto pedía caché **etiquetada** y
llevaba dos días declarado no implementable —el store es `database`, que **lanza** al usar tags—. El
owner decide **Redis, requisito DURO, y SOLO para caché**. Activado y **verificado en staging**:
instancia propia por sitio dentro del contenedor PHP, Laravel conecta **sin tocar una sola variable**,
y los **tags funcionan contra el Redis real** —también por el camino WEB, no solo por la CLI—.
⚠️⚠️ **Y trae dos obligaciones que no son opcionales**: el token de la vuelta de Redsys **sale de la
caché** (un pase de un solo uso no puede vivir donde algo puede desalojarlo) y **Redis entra en el
stack local y en la suite** —medido: `array` soporta tags y `database` lanza, así que hoy un test con
tags **sale VERDE y revienta en producción**, la misma trampa de `#129`—.
⚠️ Sesión y cola **se quedan en base de datos**: Redis vive dentro del contenedor PHP y cada reinicio
se lo lleva. Para una caché es un arranque en frío; para las sesiones, echar a todos a la vez.
⚠️ Y **no es lo siguiente**: el orden acordado en `#136` es tema → contenido → servicios.
- [ ] Sustituir el composer global `'*'` por **query services de contenido** con caché
      etiquetada e invalidación por evento de modelo (hoy: memo por request tras el W1).
- [ ] Theming como paquete coherente (tokens CSS + tema BD + assets por instalación).
- [ ] Contenido consumible también vía API (para que la app móvil pinte lo mismo que la landing).

### Fase 6 — Móvil + features nuevas 🟦 — el waiver (subsistema B) EN EJECUCIÓN desde el 2026-08-25 · menores a cargo (C) desde el 2026-08-27
- [ ] **Segundo driver de pasarela** (Stripe u otros) sobre el puerto `Booking\Contracts\
      PaymentInitiation` que dejó el cierre de Fase 3: selección de driver por configuración, e
      imprescindible para instalar un cliente fuera de España. Se aplazó aquí a propósito
      (`DECISIONES #37`): con un solo driver real, la forma del enchufe es especulación — el segundo
      es quien la revela. El puerto ya existe, así que no hay que tocar a ningún consumidor.
- [ ] **Emisión de tokens Bearer** (`POST auth/tokens`): la infraestructura de Sanctum y toda la
      revocación están hechas y probadas desde Fase 3 · paso 3a (`DECISIONES #29`).
- [ ] Congelar contrato API v1; guía de integración móvil (auth, refresh, push, deep-links a pago).
- [ ] Features nuevas y modificaciones sobre el sistema actual (backlog a definir con el owner).

#### La VISIÓN DE PRODUCTO de la app: cuatro subsistemas 🟦 — diseñados; **B (waiver) y C (menores a cargo) en ejecución**

> Decisión que los enmarca: **`DECISIONES #142`** (2026-08-25, sesión de arquitectura con el owner).
> La app móvil existe para **fidelizar**, y de ese propósito salen cuatro subsistemas.
> ⚠️ **El orden es por DEPENDENCIA, no por atractivo** — la regla que ordenó toda la Fase 4:
> **B → C → A → D**.
> ⚠️ **Ninguno toca la landing**: no solapan con `specs/landing-white-label.md` (`#136`). El único
> punto de contacto es que el carné de **A** viaja en el correo de confirmación.
> ✅ **La revisión adversarial que `CONVENCIONES` §5 exige está HECHA** (2026-08-25, **`#156`**): cada
> spec tiene su **§8** con los hallazgos. **Ninguna hay que rehacerla**, y de todas sus afirmaciones
> verificables sobre el código **ninguna resultó falsa**.
> ❗❗ **Dos BLOQUEANTES, y el peor no es de ingeniería**: (1) el **texto del waiver es literalmente un
> borrador** y publicar una versión es irreversible por diseño — `[PENDIENTE: owner]` nuevo; (2)
> **JumpPoints descansaba sobre un hecho no observable** (nadie sabe si un cliente vino), **resuelto
> por el owner** con puntos por FUENTE.
> ❗ **Ninguna spec está aprobada todavía**: las cuatro siguen 🟦 esperando el **✅ del owner**.

- [ ] **B · Waiver con valor probatorio** — `docs/specs/waiver-probatorio.md`.
      ▶ 🟦 **CÓDIGO COMPLETO (agente A, 2026-08-25 → 26: `#160` · `#161` · `#163` · `#166`)** — el
      detalle vive en la spec **§9**. Las cuatro tandas empujadas: **1 · el NÚCLEO** ✅ · **2 · el
      PANEL** ✅ · **3a · el cliente por API** ✅ · **3b · el CAJÓN** ✅ · **el guion §5.nonies en
      headless** ✅ (§9.10) · **la revisión adversarial del subsistema** ✅ (§10, `#169`). Sigue 🟦 y
      no ✅ por lo que NO es de agente: el **OJO del owner en navegador** (guion §5.nonies), **el
      texto definitivo** (§8.1: ninguna versión publicada), **el periodo de retención**
      (`waiver.retention_months`) y **las decisiones de §10.11** — y por el código acotado que la
      revisión exige antes de que una instalación entre en `interno` (fichas en `DEUDA.md`).
  - [x] **B · el guion en headless + la revisión adversarial del SUBSISTEMA** (2026-08-26, `#169`,
        carril A, **sin escribir código**): 99 comprobaciones del guion, 94 ✓ —los dos ✗ reales son
        `CAJ-3`—; 5 PDF leídos; `waiver:verify-chain` lineal sobre MySQL. Revisión: 6 lentes + escépticos
        (55 agentes) → 37 hallazgos confirmados (**1 alta**: el alta manual registra una firma «declarada»
        que el operador no declara · 21 medias · 15 bajas), 3 refutados, 68 afirmaciones que aguantaron.
        ❗❗ **Y un defecto que no es del waiver**: con Turnstile activo el alta suelta de `/registro`
        **no termina** (el widget nunca se monta) — `DEUDA.md` Alta, spec §9.10.
  - [x] **Tanda 4 · (1) el anti-bot del alta suelta** (2026-08-26 noche, `#171`, carril A):
        `mountTurnstile` acepta funciones para el nodo y la clave, espera a que existan y no carga el
        script de Cloudflare antes; `RegisterForm` cambia dos líneas (37/40). +5 casos JS (695), 4
        mutaciones muerden, chunk +0,13 KiB (225,85/226). **Verificado en headless con Turnstile
        encendido**: script tras `/config`, token a 3,1 s, `201`. ▶ Siguen (2)…(5) de la tanda.
  - [x] **Tanda 4 · (2) el servidor** (2026-08-26 noche, `#174`, carril A): canal por guard (token
        real / sesión), idempotencia por versión y sujeto DENTRO del lock, guarda de borrador con los
        tres marcadores + aviso de palabras, badge del pedido por `WaiverStatus`, throttles con prefijo
        y `WaiverSigner` en el `CRITICAL_RE`. +12 tests, **7 mutaciones, las 7 muerden**;
        `waiver:verify-chain` (un menor por proceso) lineal con 8 y 16 y visto fallar sin el lock.
        Spec **§9.11**. ▶ Siguen (3) el cajón, (4) las decisiones del owner, (5) el texto del PDF.
  - [x] **Tanda 4 · (3) el cajón** (2026-08-26 noche, `#175`, carril A): el id que se firma es el
        del texto ENSEÑADO (y se relee si el estado dice otro), el 422 del alta relee y desmarca, la
        casilla se desmarca tras el 409, y `store.upcoming`. JS 695 → 700, **5 mutaciones, las 5
        muerden**; chunk 226,21 → techo 226,5 por corrección. Verificado en headless con anti-bot.
        ▶ Siguen (4) las decisiones del owner y (5) el texto del PDF.
  - [x] **Tanda 4 · (4b + 4c) dos de las tres decisiones del owner** (2026-08-26 noche, `#178`,
        carril A): la casilla del alta **OBLIGATORIA en interno** con versión publicada (422
        `api.register.waiver_required` sobre `accept_waiver`; opcional en externo e interno sin versión)
        y **la casilla del alta MANUAL** (texto vigente a la vista + `waiver_declared`; sin ella
        `CustomerRegistrar` no firma, también por el camino sin email). +6 tests PHP, +1 JS, **4
        mutaciones, las 4 muerden**; headless 4c 5/5 y 4b 3/3 — y el navegador cazó un 500 del modal
        que la suite no veía (`wire:partial`), ahora con red directa.
  - [x] **Tanda 4 · (4a) correo verificado para firmar** (2026-08-26 noche, `#179`, carril A): la guarda
        en `WaiverSigner` (fila bloqueada, salvo firma declarada), la aceptación PENDIENTE del alta en
        `users` y su firma al verificar (`SignPendingWaiverOnVerification`) o su descarte si el texto
        cambió; `409 waiver_email_unverified`; `anonymize()` + censo. `VERIFY_CONC` con
        `waiver:verify-chain` 8/16. ▶ Queda (5) el texto del PDF.
  - [x] **Tanda 4 · (5) el texto del PDF** (2026-08-26 noche, `#180`, carril A): el PDF dice exactamente
        lo que el diseño garantiza (§10.6) — comprobación INTERNA con su nota de alcance, pie sin
        «inmutable», datos/IP/UA del puesto en mostrador— y `WaiverChain` cruza cada firma con su
        versión. **La tanda 4 queda CERRADA**: las cinco unidades empujadas.
  - [x] **Tanda 4 · (6) la revisión de la propia tanda, aplicada** (2026-08-26 noche, `#183`, carril A;
        spec §9.12): 24 hallazgos confirmados, 0 refutados. La firma pendiente del alta lleva la IP/UA
        de la ACEPTACIÓN y se registra tras el commit (`Verified` también lo emite el cobro), vigencia
        re-comprobada dentro del lock, `pending` en el contrato, el 422 de `accept_waiver` relee, la
        declaración en mostrador vale para la cuenta existente, y ocho bajas. 10 mutaciones muerden;
        **el guion §5.nonies reescrito y re-recorrido en headless con la conducta nueva: **111/111 ✓, 0 desviaciones****.
  - [x] **B · tanda 1 — el NÚCLEO** (2026-08-25, `#160`): `legal_document_versions` +
        `waiver_signatures` (inmutables, en Identity), `LegalDocumentPublisher` (publicar es un ACTO; un
        texto con `[PENDIENTE]` se rechaza —§8.1 hecho mecanismo—), `WaiverSigner` (hash canónico fijado
        + cadena POR TITULAR serializada con el lock de su fila), `WaiverStatus` con los TRES modos
        (`waiver.mode`, que hereda el interruptor de #216 sin cambiar conducta), la puerta señala «versión
        anterior» y deja pasar (§4.8), `anonymize()` conserva la prueba y sigue purgando lo demás (§6·3),
        poda por `waiver.retention_months` (sin valor: nada) y la acción «Publicar versión firmable» en la
        página del waiver. **`waiver:verify-chain` sobre MySQL con 8 y 16 procesos: cadena lineal — y
        visto FALLAR sin el lock, 3 de 3 (1, 9 y 15 `prev_hash` repetidos).**
  - [x] **B · tanda 2 — el PANEL** (2026-08-26, `#161`): **la identidad del firmante viaja EN la
        firma** (`[DECIDIDO owner]`: `holder_name`/`holder_email` dentro del hash, esquema canónico v2
        con la versión guardada por fila —lo firmado con v1 sigue verificando—), permiso PROPIO
        `waiver.view` (no va al staff por defecto), la acción «Registro del waiver» en la ficha del
        usuario —no una sección: abrirla ES la consulta y se audita—, el **PDF del snapshot** en el
        idioma del texto firmado (determinista, `no-store`, IDOR, auditado; `RGPD-04` amplía) con las
        etiquetas en `lang/{es,en,fr}/waiver.php`, y el alta presencial en modo interno como firma
        **declarada por el operador** (§8.4). Detalle y lo medido: spec **§9.6**.
  - [x] **B · tanda 3a — el CLIENTE por API** (2026-08-26, `#163`): `GET /legal/waiver` (público, el
        snapshot vigente con su id), `GET|POST /me/waiver` (estado · aceptar SOLO lo que el servidor
        sirvió: `409 waiver_document_stale` si el texto cambió), el PDF propio auditado, la casilla del
        alta (`accept_waiver` + `waiver_document_id`, comprobada ANTES de crear la cuenta) y `waiver`
        en `me/account-context` (donde vive la re-firma «en el siguiente momento natural»). Contrato
        en `openapi/v1.yaml`; los avisos del alta fuera del montaje del cajón (presupuesto medido).
        Spec **§9.8**, `api-v1.md` §10.septdecies.
  - [x] **B · tanda 3b — el CAJÓN** (2026-08-26, `#166`): la casilla en el alta del paso 5 (opt-in,
        **solo existe si el servidor sirve un documento**; el texto completo plegado; `accept_waiver`
        viaja `true` solo con su `waiver_document_id`), la tarjeta de Privacidad con estado por modo,
        «firmar / re-firmar» y las firmas con su PDF, y el aviso «tu waiver está pendiente» en el
        índice de la cuenta. Un módulo plano (`account/waiver.js`) decide, un store (`stores/waiver.js`)
        pide y RE-LEE ante `409 waiver_document_stale`, los componentes pintan. **Presupuestos**: el
        chunk **+4,94 KiB** —techo 221,5 → 226 **decidido por el owner** con el número delante, porque
        la regla escrita solo cedía por correcciones—; los textos **podados antes de subir** (−508 B:
        tres claves que no leía nadie y cuatro literales del 422 que el servidor ya publica) y el
        anónimo **BAJA** su techo. ⚠️ **El diff de árbol NO ve la casilla** (`v-if` sobre un documento
        que en SSR no existe): la red de lo visible es el navegador → guion **§5.nonies** de
        `VERIFICACION-E2E-CAJON.md`, **recorrido en headless el 26/08 (`#169`); pendiente del OJO
        del owner**. ❗ El aviso **en el paso de pagar**
        NO se construyó (§9.9·1: la puerta deja pasar, y el chunk no tiene margen) — decisión de producto
        abierta. Spec **§9.9**.
      ⚠️ **Revierte una decisión vigente**: el waiver deja de ser solo externo y pasa a tener **tres
      modos** (externo / interno / desactivado). ⚠️ **Y modifica `INVARIANTES` §3 (RGPD-01)**: el
      registro firmado **se conserva** al borrar la cuenta, bajo tratamiento restringido y con plazo.
      ❗ El **plazo** está `[PENDIENTE: owner]` — sale de criterio jurídico.
      ▶ Arregla de paso un defecto VIVO: hoy nadie puede reconstruir qué texto firmó nadie.
      ❗❗ **REVISADA (`#156`, §8): BLOQUEADA para publicar.** El texto del waiver dice de sí mismo que
      es un borrador pendiente de asesor legal, y publicar una versión es **irreversible por diseño**:
      la maquinaria se construye, el acto de publicar la v1 **no**. Segundo `[PENDIENTE: owner]`.
      ⚠️ **Y tres piezas se deciden antes de la primera línea**: la cadena de hashes **se bifurca en
      silencio** bajo concurrencia (recomendación: cadena **por titular**) · el registro va en **tabla
      propia**, no ampliando `consents` (`cascadeOnDelete`) · el **alta presencial** también escribe
      consentimientos y produce una firma **declarada por el operador** (`[DECIDIDO owner]`).
      ⚠️ `RGPD-01` **no contiene hoy la frase que hay que modificar**: primero se le añade lo que el
      código ya hace y ella calla, y **después** se restringe.
- [ ] **C · Menores a cargo** — `docs/specs/menores-a-cargo.md`. Nombre y fecha de nacimiento, tope 20
      de servidor, quitar = desvincular si hay firma detrás; la asignación la posee Identity por id
      entero (§4.6) y entra en el embudo por dos puertas sin paso nuevo (§4.7).
      ▶ 🟦 **EN EJECUCIÓN (carril A, `[DECIDIDO owner]` `#190`): la TANDA 1 está en el árbol** (`#191`,
      spec **§9**) **la TANDA 2 también** (`#198`, §9.7) **y la 3, el cajón** (`#199`, §9.8). Sigue 🟦
      y no ✅ porque falta la tanda 4 (el embudo) y el ✅ del owner en navegador (guion §5.decies).
  - [x] **C · tanda 1 — el NÚCLEO en Identity + la API** (2026-08-27, `#191`, carril A): `dependents`
        (`user_id` RESTRICT · `name` · `born_on` · `removed_at`, y nada más: la edad se DERIVA fecha
        contra fecha en el «hoy» del parque), `DependentRegistry` (solo menores; tope
        `dependents.max_per_account` bajo el lock de la fila del titular; quitar = `unlink()` con firma
        detrás / `delete()` sin ella; ajeno = inexistente), `GET|POST|DELETE /me/dependents` contra el
        contrato (`dependent_not_minor` · `dependents_limit_reached` con `params.max`), `anonymize()`
        que desvincula o borra (`RGPD-01`), `dependents[]` en el export (`RGPD-04`), la purga de go-live
        antes que `users`, la poda de las desvinculadas sin firma y el tope en Ajustes → «Puerta».
        **34 casos · 7 mutaciones, las 7 muerden · 14 comprobaciones HTTP sobre MySQL con Bearer.**
        ⚠️ **Sin ninguna firma de menor todavía, a propósito**: NUC-3 se decide ANTES de la primera.
  - [x] **C · tanda 2 — la FIRMA DEL MENOR** (2026-08-27 noche, `#198`, carril A; spec §9.7): la
        cadena de hashes por (titular, sujeto) en `WaiverSigner` (`#197`; `CRITICAL_RE` → `verify-chain`
        rehecho para medir la idempotencia bajo el lock, 8/16 PASA y **visto FALLAR sin el lock**), la
        FK `waiver_signatures.subject_id → dependents` RESTRICT (medida bloqueando un `DELETE` crudo),
        la identidad del menor copiada en la firma (esquema canónico v3), pertenencia y minoría bajo el
        lock, `POST /me/dependents/{id}/waiver` + `Dependent.waiver` + `dependent_id`/`dependent_name`
        en el resumen, `WaiverStatus::forDependent()`, el PDF y el registro del panel nombrando al menor
        y diciendo que los datos los declaró el titular, y la retención del menor desde los 18
        (`waiver.dependent_retention_months`, en Ajustes). **+15 casos · 5/5 mutaciones muerden · sonda
        HTTP sobre MySQL.** NUC-3 de `DEUDA.md` CERRADA como guarda.
  - [x] **C · tanda 3 — la ZONA DEL CAJÓN** (2026-08-27 noche, `#199`, carril A; spec §9.8):
        `DependentsZone` + `DependentCard` + `stores/dependents.js` + `account/dependents.js`, la
        entrada del índice con el icono `users` (nuevo en el sistema de diseño, copiado byte a byte),
        17 rótulos ES/EN/FR solo con sesión (y seis reutilizados). **Cero CSS nuevo** (`site.css` es
        del carril C) y ≤ 40 líneas por componente. **Medido y subido por FEATURE** (`#197`·2): chunk
        226,34 → 234,41 KiB (techo 235), payload con sesión 6.668 → 7.602 B (techo 7.700). JS 702 → 724.
        **Guion en headless 20/20** (`VERIFICACION-E2E-CAJON.md` §5.decies). Queda el OJO del owner.
  - [ ] **C · tanda 4 — la asignación en el embudo** (§4.7–§4.10): la tabla de Identity con el ítem
        por id entero, las dos puertas, el hueco en la lista blanca de `cart.js::save()` sobre `v: 1`
        (§8.1, §8.2), la re-validación en servidor (§4.9) y la escritura post-commit fuera del lock
        (§4.10). Es la tanda que toca el checkout.
        ▶ 🟦 **EN EJECUCIÓN desde el 2026-08-27 por la noche (carril A, `#202`): el DISEÑO DE EJECUCIÓN
        está en la spec §9.9**, medido contra el código antes de escribir (14 afirmaciones de la spec
        resultaron falsas o imprecisas: no hay pantalla post-login en el paso 5; un campo nuevo por
        línea hoy se descarta en silencio con 201; la cesta del propio titular se PURGA al nacer
        abierto el cajón — medido en headless). **Cuatro decisiones del owner**: la puerta 2 vuelve al
        carrito con aviso · ❗ **la exención firmada es CONDICIÓN para asignar** · la purga se arregla
        como unidad 0 · el panel NO entra (rectificado la misma noche: sesión propia). Unidades: U0 la
        purga · U1 el servidor · U2 el cajón · U4 el ojo del owner.
    - [x] **U0 · la purga de la cesta** (2026-08-27 noche): el dueño se siembra en `index.js` desde
          `boot.userId` antes de montar; caso JS + guarda estructural con 2 mutaciones que muerden;
          sonda headless 10/10 (M1/M1bis conservadas). Ficha de `DEUDA.md` RETIRADA.
    - [x] **U1 · el servidor** (2026-08-28 madrugada, spec §9.9.7): `dependent_assignments` +
          `DependentAssignment` + `Dependent::referenced()` (un predicado) + `Booking\Contracts\CheckoutLines`
          + reader + doble + `DependentAssigner` (`check()` ANTES del dinero → 422 por campo; `assign()`
          tras el `allow`, bajo el lock del titular, idempotente, sin lanzar) + `CartLine.dependent_ids`
          + `OrdersController::store()` + `event-data` con `dependents[]` + `anonymize()`/export +
          `api.dependents.*` ×3 + la paridad mínima de `cart.js`. **+30 tests · 9/9 mutaciones · 6
          escenarios + Redsys ✓ · sonda HTTP 10 pasos ✓.**
    - [x] **U2 · el cajón** (2026-08-27 noche, spec §9.9.8): `assignment.js` + `DependentPicker.vue`
          en los pasos 3 y 4 (cero CSS nuevo), las cuatro listas de `cart.js` + `toCheckoutItems()`, la
          puerta 2 en `admission.js`, «Para:» en el resumen/paso 6 y la tarjeta, rótulos ×3 **en
          `tickets.dependents`** (los del embudo viajan siempre), manifiesto +2, techos re-medidos
          (chunk 243 · payload con sesión 7.800 · orquestador 428), guion §5.undecies **19/19** por
          las dos puertas y sonda de 16 `assign()` simultáneos → 1 fila. ⚠️ Dos defectos cazados por
          el guion, no por la suite (§9.9.8·4 y ·5). ❗ **Y el arreglo de ·4 NO estaba en el árbol**
          (2026-08-28, `#210`): el `watch` seguía por encima de `const cartStore` (TDZ, `ReferenceError`
          en cada montaje, un 401 de menores por visitante anónimo); corregido, con la corrección
          delante del texto en la spec y guarda (`SidebarSetupBindingsTest`).
    - ~~U3 · el panel~~ **FUERA** (rectificación del owner la misma noche: el panel tendrá su propia
          sesión; D14 se conserva como diseño). ▶ **Esa sesión es la del carril A del 2026-08-27 noche**
          (`#207`, `#208`): **EN EJECUCIÓN como tanda 5, spec §9.10** (abajo).
  - [x] **C · tanda 5 — el PANEL (D14)** (2026-08-27 noche, carril A, `#208`; spec **§9.10**, diseño de
        ejecución medido y **§9.10.4 ejecutada**, `3219eb7` → `8ab0f5c`): la ficha del pedido enseña
        «Para: Lucas (9 años · exención ✓)» por línea de entrada (`AssignedDependents`, presupuesto
        constante: asignaciones + `WaiverStatus::forDependents()`), la acción «Asignar menores» de la
        línea (trait `AssignsDependents`, misma familia que Gestionar) fija el CONJUNTO con
        `DependentAssigner::sync()` —reglas de D3 sobre lo que se AÑADE, lo asignado a un menor retirado
        se conserva y cuenta, mismo lock, fail-closed por posición, auditado con el operador— tras las
        cuatro capas de defensa (`orders.edit_item` · `editItemBlockedReason()` con IDOR · solo entradas
        · el dominio), y el alta manual ofrece los menores por línea de entrada, `check()` ANTES de
        cobrar y `assign()` DESPUÉS de `fulfill()`. **+45 tests · 4/4 mutaciones · sonda de 16 `sync()`
        concurrentes sobre MySQL (con lock 4/4 PASA; sin él 3/4 abortan por deadlock) · headless 13/13
        con capturas.** Sigue 🟦 por el OJO del owner (§9.10.4 «lo que queda»).
    - [ ] **U4 · el ojo del owner** (guion §5.undecies).
- [ ] **A · Carné QR + pantalla de puerta** — `docs/specs/identidad-qr-puerta.md`. ▶ 🟦 **CÓDIGO
      COMPLETO (carril A, 2026-08-27 noche → 28 madrugada, `#208`; spec §9.4)**: **A1** el carné
      (`customer_cards`, `CardToken` de 20 caracteres con control mod 31, `CustomerCards`, dentro de
      `revokeAllAccess()` — `RGPD-06` ampliada) y la visita (`customer_visits`, idempotente por día) ·
      **A2** la ficha (`Booking\Contracts\GateReservations` con el dinero del ledger + `GateProfile` →
      `GateProfileData` sin campo para el nombre de un menor; 23 consultas constantes) · **A3** la
      pantalla (el carné por el MISMO input, semáforo + ficha con `puerta.profile`, dos limitadores,
      `ensureFresh()` en servidor, «Registrar visita») · **A4** el PNG en el correo y `GET|POST /me/card`
      contra el contrato. **+~36 tests · 5/5 mutaciones · headless 15/15 con capturas.** ✅ Spec
      APROBADA por el owner (`#208`); §8.2 decidido (20 caracteres); §8.1 construido. Sigue 🟦 por el
      OJO del owner: la pantalla, el correo en clientes reales y **el lector real del recinto** (§6).
      ⚠️ **Segunda reversión**: la puerta deja de ser «privacy-by-design mínima». ⚠️ **Y amplía
      `RGPD-06`**: el carné es una credencial y entra en `User::revokeAllAccess()` desde el primer
      commit — es literalmente el modo de fallo que esa invariante existe para impedir.
      ✅ **REVISADA (`#156`, §8): sólida.** Dos hallazgos acotados —la rotación de `APP_KEY` **lanza**,
      no degrada; y la entropía del carné (`2⁵⁰` en sha256 sin sal) hay que **decidirla o justificarla**—
      ❗ **y una responsabilidad NUEVA**: esta pantalla es donde se **acredita la visita** de un cliente
      y de ahí salen sus JumpPoints (`[DECIDIDO owner]`). ⚠️ **No puede colgar de «se abrió la ficha»**:
      la ficha se abre varias veces por cliente y también tecleando un correo.
      ✅ **Y LAS DOS SUPERFICIES DEL CARNÉ, EN EL ÁRBOL (2026-08-28 por la mañana, `#212`, spec §9.6)**:
      `GET /me/card/png` (los MISMOS bytes que el adjunto del correo; el QR lo dibuja el servidor porque
      el chunk estaba a 0,36 KiB del techo) + `png_url` en el contrato · la zona **«Mi carné»** del cajón
      (tercera del índice, icono `qr`; imagen con `?v=issued_at`, token en grupos de 4 para dictarlo,
      descargar, renovar con confirmación) · **«Rotar carné QR»** en `ViewUser` (el `cards.rotated` lleva al
      OPERADOR de actor). `[DECIDIDO owner]`: **el panel NO declara menores**. +8 tests PHP · JS 773 → 790 ·
      headless **14/14** · techos por feature (chunk 247, payload 8.550). Sigue 🟦 solo por el OJO del owner.
      ✅ **Y EL PULIDO DE LOS OCHO PUNTOS DEL OWNER, EN EL ÁRBOL (2026-08-28 tarde, `#217`, spec §9.7 y
      §9.7.1)**, tras su prueba en staging: el **icono de la instalación dentro del QR** con margen
      (7 módulos tapados; re-medido con 40 carnés REALES porque el primer número se midió con un token
      que no era un carné: 7 → 0 fallos, 9 → 10 de 40) · **«Mi cuenta» en tarjetas** · **«Mi QR» junto
      al nombre** · el **QR como credencial** con aviso permanente y confirmación en el cajón ·
      **«Menores a cargo»** con alta desplegable y paginación de 6 · el **selector del embudo**
      rediseñado · los **menores en la ficha del panel** · la **pantalla de puerta** entera (⚠️ su
      paleta estaba ROTA: los grises y el color de marca computaban vacío) · y la palabra **«QR»**.
      ⚠️ **La casilla del menor NO estaba rota** (modo interno + exención sin firmar, `#202`·2).
      **Revisión adversarial: 23 hallazgos, 9 arreglados y 14 en `DEUDA`** — entre los arreglados, el
      **cuerpo del semáforo de la puerta no se pintaba** y **«Mi QR» salía mudo** para quien gana la
      sesión sin recargar. Suite 3315 / 21.713 · sondeos 38 ✓ y 15/15 · desplegado en staging.
      **Sigue 🟦: falta el OJO del owner** (guion `VERIFICACION-E2E-CAJON.md` §5.octodecies, y en
      particular el LECTOR real del recinto con el PNG descargado).
- [x] ✅ **LA FORMA DEL PANEL — tanda 1: el MENÚ PLANO** (2026-08-28 tarde, `#223`,
      `docs/specs/panel-navegacion.md`). Fuera de roadmap, por encargo directo del owner: «simplificar
      el panel, mejor UI/UX, empezando por el menú». **Medido antes**: 24 entradas en 6 grupos, todos
      desplegados, y solo **4** del día a día (`PANEL-ADMIN.md` §2) → el **83 % del menú era puesta en
      marcha**; cero búsqueda global; «Usuarios» mezclando clientes y equipo; «Calendario» y «Crear
      pedido» duplicados; **1 solo fichero de test miraba la navegación**. ⚠️⚠️ **La primera medición
      fue FALSA**: Filament **memoiza** la navegación y dijo que el empleado veía las 24 del admin —no
      era cierto, el gateo estaba bien—; para medir dos roles, **un proceso por rol**. ❗ **El owner
      corrigió la propuesta del agente** (10 entradas con grupos plegables → **menú PLANO de 5**) y
      mejoró el resultado. **Entró**: menú **Hoy · Calendario · Pedidos · Clientes · Puerta** · las
      **19** de puesta en marcha a **`/admin/ajustes`**, en tarjetas con su descripción, entrando por el
      **menú del avatar** · «Calendario» fuera de la barra superior · «Usuarios» partido en pestañas
      **Clientes**/**Equipo** por `User::PANEL_ROLES`. **Resultado**: admin **24 → 5**, empleado
      **5 → 4** y sin «Ajustes». ⚠️ **Ocultar no es autorizar** (los 19 `canViewAny()`/`canAccess()`,
      intactos y verificados uno a uno) ⚠️⚠️ **y el riesgo real es la pantalla HUÉRFANA**: por eso
      `AdminNavigationTest` exige que toda pantalla registrada esté en el menú, en
      `AdminSettingsHub::areas()` o en `OUTSIDE_HUB`. ⚠️⚠️ **Las utilidades de color de Tailwind
      habrían dejado el aro de foco INVISIBLE** (`--color-primary-500/600` no declaradas aunque sus
      clases compilan): el fallo de `#217`, cazado antes de subirlo. **12 casos nuevos · 3 mutaciones,
      las 3 muerden · suite del panel 1162 / 5125 · headless con capturas.**
      **Sigue 🟦: falta el OJO del owner**, y con él el repaso de los rótulos. ▶ **Lo que el menú NO
      arregla y sigue abierto**: la pantalla **«Hoy»** y la **búsqueda global (⌘K)** (spec §6).
- [x] ✅ **LA FORMA DEL PANEL — tanda 2: el BUSCADOR** (2026-08-28, `#224`, spec §7). `[DECIDIDO
      owner]`: «buscador total del panel, sobre clientes, pedidos y demás». 14 recursos buscables
      **más una categoría que Filament no trae: las PANTALLAS**, sacadas de las mismas fuentes que
      las pintan y buscables **por su descripción** («precio» → Tarifas) y sin tildes («catalogo» →
      Catálogo). ⚠️ **El empleado busca PEDIDOS, no clientes** (`[DECIDIDO owner]`), y se sostiene
      sin código nuevo. ⚠️⚠️ **De regalo, un defecto VIVO**: buscar «jump» en el Catálogo del panel
      no encontraba «Jump · 1 hora» —MySQL extrae el JSON con colación `utf8mb4_bin` y el LIKE
      distinguía mayúsculas; medido 0 vs 5—, desde que se escribieron esas tablas y sin ningún test
      que lo viera. ⚠️ **La palanca de Filament para arreglarlo NO es portable** (rompe en SQLite,
      que es donde corre la suite): se resuelve con `wrap()` de la gramática + `LOWER()` a mano, y
      con DOS comprobaciones, porque **un test en SQLite no puede demostrar la conducta en MySQL**.
      ⚠️ **Y la guarda clave pareció CIEGA al mutarla y no lo era**: la defensa tiene dos capas
      (`canViewAny` abre la búsqueda, `canView` da la URL, y sin URL Filament descarta el
      resultado). **11 casos · 4 mutaciones, las 4 muerden · suite del panel 1174 / 5155.**
- [x] ✅ **LA FORMA DEL PANEL — tanda 3: la PUERTA en TABLET, modo kiosco** (2026-08-28, `#232`,
      spec §8). `[DECIDIDO owner]`: tablet **fija en soporte y horizontal**, y **propia** de la
      puerta. **Medido antes**: 1.298 px de alto contra 1.080 de pantalla, 768 px de ancho usado de
      1.080, y **cero reglas CSS entre 640 y 1280 px**. ⚠️ **El caso peor no era el típico** (1.115
      vs 896: el párrafo extra de la exención vieja). ⚠️⚠️ **Cuatro columnas salieron PEOR que
      tres** —al estrecharse, las tarjetas crecen a lo alto: la rejilla bajó 18 px y el total subió
      3—. Entró, todo CSS: 80rem de ancho, 3 columnas, cabecera en una línea, nombre a 2,5 rem,
      44 px táctiles fuera de todo `@media` y el **buscador pegado arriba**. ⚠️ **«Nueva búsqueda»
      NO se ocultó**: quita de pantalla la ficha del cliente anterior, o sea privacidad. **Resultado:
      Pro y vertical caben, iPad h. se pasa 64 px, y en las cuatro se ve la acción sin desplazar;
      cero controles bajo 44 px.** 4 casos · 4 mutaciones, las 4 muerden · headless en 5 anchos.
      ▶ Fuera a propósito: calendario y tablas (ficha en `DEUDA`).
- [x] ✅ **MENORES: apellidos, relación con el titular, y el NOMBRE en la puerta** (2026-08-28,
      `#236`, spec §10 y §11). `[DECIDIDO owner]`: apellidos en **campo aparte**, relación como **lista
      fija traducida** (es lo que sostiene que ese adulto pueda firmar por el menor), y la **pantalla
      de puerta enseña el NOMBRE** del menor con su edad. ⚠️⚠️ **Esto último REVIERTE una decisión de
      privacidad escrita en cinco sitios**; el motivo es operativo —con tres niños y una firma que
      falta, «7 años ✗» no dice a cuál— y **no era una invariante**. ▶ **Los APELLIDOS siguen fuera y
      es estructural**: `GateProfileData` no tiene campo. ⚠️ Las dos columnas nacen **nulables**: las
      fichas anteriores no las tienen y no se inventan. ⚠️ La firma del waiver guarda el nombre
      completo **cambiando el valor, no el conjunto de campos**, para no romper la cadena de hashes.
      **Cuatro guardas re-apuntadas por sujeto, ninguna borrada** · coste por feature (chunk 253,
      payload 9.100) · suite 3.355 / 22.048 · JS 813 · verificado en navegador.
- [ ] **«CREAR PEDIDO» DEL PANEL, PARA TABLET** — `[DECIDIDO owner, 2026-08-28 tarde]`, y
      ⚠️⚠️ **CORRIGE la premisa de `#232`**: allí dijo «la puerta tiene tablet propia, el resto del
      panel se usa en ORDENADOR» y por eso el calendario y las tablas quedaron fuera. **Ha cambiado:
      el GERENTE creará las reservas desde la tablet.** `/admin/crear-pedido` necesita su pasada, y
      sobre todo de PRESENTACIÓN: son 1.362 líneas de formulario pensadas para un ratón. Con ella
      vuelve parte del calendario en tablet. Ficha: `specs/panel-navegacion.md` §6·U7.
- [ ] **EL SELECTOR DE MENORES DEL EMBUDO HACE DEMASIADO RUIDO** — `[DECIDIDO owner]`: «es demasiado
      llamativo, hay que hacerlo más sutil». No es un fallo (funciona y está probado): es peso visual,
      y lo ve también quien no lleva menores. ⚠️ **Medir antes de tocar**, como en `#237`: sin saber
      cuánto alto se lleva del paso, «más sutil» es una opinión. ❗ No se puede perder: la exención
      firmada es CONDICIÓN para asignar, la fila no marcable **visiblemente** deshabilitada con su
      motivo, y ese motivo anunciado a un lector de pantalla. Ficha: `specs/menores-a-cargo.md` §12.
- [ ] **EL CAJÓN EN MÓVIL** — encargo del owner («es el 90 %»). ▶ **Arrancado con una MEDICIÓN**
      (2026-08-28, `#237`), no con código. ⚠️⚠️ **Hallazgo: el aviso de cookies tapaba el 45 % del
      cajón** —296 px, incluido el botón que avanza la compra— en `/entradas`, donde nace abierto;
      **no lo veía ningún test porque ninguno mide dos capas a la vez**. Arreglado bajando el aviso
      de un `z-index: 1000` sin escala a un sitio por ROL (140), con `LayerOrderTest` y sus dos
      mutaciones. ▶ **Lo medido y pendiente**: fecha **11 de 12** controles bajo 44 px y un mes de 42
      celdas con 2 reservables (se busca en vez de elegir); hora **12 de 13** (chips 68×39), ~400 px
      vacíos y ningún chip dice cómo está de lleno; catálogo **bien**. `[DECIDIDO owner]`: tira
      deslizable con ajuste para la hora y «quedan pocas» bajo umbral configurable.
      ▶ ✅ **UNIDADES 2, 3 y 4 EN EL ÁRBOL** (2026-08-28, `#239`, spec propia
      **`specs/cajon-en-movil.md`**). ⚠️⚠️ **Lo primero fue CORREGIR la premisa de `#237`**: de «2 de
      42» se dedujo que sobraban días, y medido hay **182 días reservables** (horizonte 6 meses) —no
      faltaban días, **sobraba rejilla**; el «2 de 42» solo vale del mes en curso, que abre casi
      entero en el pasado—. Por eso el calendario **no se retira**: queda plegado tras «Ver más
      fechas» para el salto largo (`[DECIDIDO owner]`). Entra la **tira de días**
      (`calendar.js::buildStrip()`), la **tira de horas** con ajuste, el aviso **«Casi llena»** sin
      número con umbral en el panel (`booking.low_availability_max`, `AvailabilitySettings` +
      `GET /config` con su operador escrito), y los objetivos de **44 px** (celdas 43→45, flechas
      32→44, chips 68×39→72×44, «Volver» con 45 px de área sin engordar la banda).
      ⚠️⚠️ **La HORA lleva una objeción MEDIDA que el owner mantuvo**: las 11 horas **cabían a la
      vez** en tres filas y la tira enseña **4 de 11**. Escrito en la spec §4.2 para que no se lea
      como un descuido.
      ⚠️ **La vela sola no bastaba** (`--bg` y `--bg-card` casi coinciden): lo que dice «hay más» es el
      chip **cortado por el borde**, así que las tiras salen a sangre. Y el separador de mes **nacía
      cortado** sin su `scroll-snap-align`.
      **Verificación**: JS 813 → 835 · +20 casos PHP · **10 mutaciones, las 10 muerden** · headless
      **22/22** (`VERIFICACION-E2E-CAJON.md` §5.novodecies) · chunk 252,27 → 255,13 KiB (techo 256).
      ▶ ✅ **Y LAS DOS VUELTAS DEL OWNER, aplicadas** (2026-08-29, `#241` y `#242`; spec §7.bis y
      §7.ter). **`#241`**: con RATÓN no se podía deslizar ninguna tira —la barra va oculta a
      propósito—, así que entran **flechas** en las tres, **solo donde hay ratón** (`hover: hover` Y
      `pointer: fine`) y solo si llevan a algún sitio; y las **fichas de hora suben a 76×76**, como las
      de día, con el aviso en segundo plano. ⚠️⚠️ **Las flechas NACIERON MUERTAS**: el cableado se
      enganchaba en `onMounted` y el carril vive dentro de un `v-if` que espera la oferta —al montar el
      componente el nodo **no existe**—. Ahora observa el NODO.
      **`#242`**: el selector de menores **apaga** las filas que no caben (fuera la línea «No caben
      más»), el asignado lleva **«1 entrada asignada»** en segundo plano y se retira **«exención
      firmada»** por obvia; ⚠️ eso **CORRIGE un comentario del propio componente**, con la corrección
      delante del texto, y **conserva el MOTIVO** de la fila que no se puede marcar nunca — lo único
      que no es obvio. ⚠️⚠️ **Y de regalo un BUG que no era de esta tanda**: «Mi cuenta» abría el
      cajón **en el EMBUDO** (`purchase.open()` deja la sección por defecto), así que con cesta
      guardada aterrizaba en el **CARRITO**. Reproducido en los dos sentidos y con guarda: *un `href`
      es una promesa*.
      ❗ **Queda**: el **OJO del owner**, la unidad 5 (carrito e identificación, **sin medir**) y el
      **hueco vertical** —366 px vacíos en fecha, **452 en hora**, y la tira lo empeoró ~50—, que es
      decisión de producto (`[PENDIENTE: owner]`).
- [x] **«CREAR PEDIDO» DEL PANEL, EN TABLET** — `[DECIDIDO owner]`: el gerente crea las reservas desde
      la tablet, lo que **CORRIGE la premisa de `#232`** para esta pantalla
      (`specs/panel-navegacion.md` §9 y §11, `#240` y `#242`).
      ⚠️⚠️ **La primera medición dijo «cabe» y era FALSA por medir solo el paso 1**: el denso es el 2 —
      **1.292 px en una pantalla de 810**, con el **resumen del pedido y el botón de avanzar FUERA de
      pantalla**, que son las dos cosas que hay que ver con un cliente delante.
      **Lo que entró**: dos columnas con el **resumen pegajoso** y la navegación dentro de él, la hora
      en **chips**, una **tira de 14 días** con el calendario amplio tras un CTA «Abrir calendario»,
      **44 px** en todo, el **titular del pedido** en el resumen, el «Atrás» de **solo icono** y el
      método de cobro en **dos tarjetas con icono**. **Resultado: de 4/8/15 controles bajo 44 px a
      CERO en los tres pasos.**
      ⚠️ **Dos puertas para elegir día** (tira y calendario) → **una** regla: `onDateChosen()`. Y
      cambiar el control de la hora **no cambió la regla**: una franja llena se enseña deshabilitada y
      el servidor la rechaza.
      ❗ **Queda**: el **OJO del owner** con la tablet, el **armazón del panel** (barra, menú y buscador
      siguen bajo 44 px en TODAS las pantallas — ficha en `DEUDA.md`) y **U6** (calendario y tablas).
- [x] **CUMPLEAÑOS MIXTO** 🟦 — **CINCO TANDAS EN EL ÁRBOL el 2026-08-29** (`#243`→`#247`), con el
      **descuento del caso barato APARCADO y su diseño escrito** (`#248`). ▶ Fila de enrutado en
      `CLAUDE.md`; empieza por `specs/cumple-mixto.md` §11 (qué hay) y §12 (cómo se cobra).
      ❗❗ **La pregunta que lo reencuadró la hizo el owner: el sistema NO tenía ninguna conexión entre
      un cumple KIDS y uno JUMP.** `[DECIDIDO owner]` la conexión es **familia + tramo de edad**
      (extremos incluidos), que vale para dos regímenes o para cinco.
      ⚠️⚠️ **Medido sobre un pedido real: un `extra_due` suelto NO cobra, MUEVE dinero ya pagado**
      (valor +0, pagado online −6,00 €, puerta +6,00). La forma que sí cobra es una **LÍNEA**.
      ⚠️ El importe **se reconcilia solo** con las edades declaradas —`[DECIDIDO owner]`, el post-form
      es editable— y se dispara con el **HECHO** (edades, cantidad, producto, fecha), nunca con la
      **configuración** (precios, tramos): lo escrito es lo que se le comunicó al cliente.
      ⚠️ La etiqueta va **pegada al nombre** desde un solo compositor, y la cogen de ahí las ocho
      superficies de texto plano. Su precio: `isMixedParty()` necesita `ticketType` y `slot` cargadas.
      ▶▶ **DESPUÉS (2026-08-29 → 31): la REVISIÓN ADVERSARIAL y la VISIÓN CERRADA — el punto de
      entrada ya NO es §11/§12, es `specs/cumple-mixto.md` §18.** La revisión (`#249`) encontró seis
      defectos que eran UNO —el importe se recalculaba entero del catálogo en cada disparo, y sin
      datos escribía CERO— y se arregló en cinco tandas (`#268`→`#272`: la abstención, el cargo
      huérfano visible, el RECIBO con el «14,00 € y no 24,00 €» del owner, el panel deja de ofrecer
      un gesto que deshacía en el mismo clic, y `MixedPartySurcharge` al `CRITICAL_RE` + `PAY-19`).
      Luego `#282` (tipos por esquema atados al contrato de la API), `#283` (el catálogo AVISA antes
      de mover un tramo con fiestas vendidas, doble guardado con firma).
      ❗❗ **`#284`: la VISIÓN del owner, cerrada — nueve decisiones (D1–D9) y plan por SEIS tandas
      (§18.5)**, con el SELLO en la reserva (D1: el precio viejo solo existe dentro de las reservas
      que lo llevan; sin histórico de precios) y un hueco MAYOR que el caso espejo medido de paso: el
      PRIMER cargo usaba el catálogo del día del formulario, no el de la compra.
      ❗❗ **`#285`: el −X € DISEÑADO con Fable — §20 SUSTITUYE a §16** (el espejo acotado a puerta:
      el clamp nunca muerde, cero mecánica contable nueva; §16 pasa a ser la pieza de la fase 3 del
      cobro). Y la regla que simplifica todo es del owner: **el dinero solo se mueve al guardar el
      formulario COMPLETO, en las dos direcciones** (cambia `#268` para los cargos; va en la T2).
      ✅ **T0 en headless (2026-08-31)**: 14 capturas en `storage/app/t0-capturas/`, el aviso de
      `#283` verificado en el ciclo REAL de Livewire, y el primer hallazgo cazado por el OJO del
      owner (el «hoy:» de la línea de desfase → T5).
      ✅ **T1 · EL SELLO, EN EL ÁRBOL (2026-08-31 tarde, `#288`)** — diseño fino en
      `specs/cumple-mixto.md` §21 (aprobado por el owner con tres decisiones: retirar `#283` y dejar
      la frase · al mover de DÍA se conservan los tramos y solo el precio sigue al día · un hermano
      creado después no existe para lo vendido) y ejecución en §21.13. `order_items.age_family_seal`
      + `AgeFamilySeal`/`SealedRegime`/`AgeFamilySealer` (`CRITICAL_RE`); el lector deriva del
      sello, `unitFor` y el recibo de `#270` desaparecen. ⚠️ **Dos huecos preexistentes cazados de
      paso**: un día sin tarifa CANCELABA el suplemento, y una fiesta mixta con cargo NO PODÍA
      cambiar de pack (`orphan_addons` contaba la línea gobernada). Suite 3547 → 3558, **13/13
      mutaciones muerden**, verificadores sobre MySQL con control negativo, sonda del hueco A en
      navegador. Queda el OJO del owner.
      ✅ **T2 · UNA EDAD SIN PRODUCTO + EL DINERO SOLO AL GUARDAR COMPLETO (2026-08-31 tarde,
      `#289`)** — diseño fino en §22, ejecución en §22.9. «Completo» son DOS preguntas (dinero:
      todas las edades declaradas · formulario: además ninguna edad sin producto); la puerta vive en
      `reconcile()` y vale también para el panel (`[DECIDIDO owner]`, Q1); tres textos por
      instalación en Ajustes («Fiestas por edad») con `:phone`. ⚠️ Cazó un hueco de CONTRATO
      preexistente en la API (`general` vacío serializado como lista). Queda el OJO del owner.
      🟦 **T3 · EL PARQUE DECIDE — DISEÑADA, CÓDIGO NO EMPEZADO** (`specs/cumple-mixto.md` §23,
      2026-08-31 noche, `#291`): lo escrito del suplemento en hoja de sala y puerta (E), la pestaña
      «Invitados» por la MISMA puerta que el cliente con `via = panel` y actor operador (F), el
      interruptor de «bajar del mínimo» con permiso propio y rastro (D7). Q1 decidida: los dos
      permisos nuevos entran en `staff`. **El owner paró antes del código para revisar §23**: nada
      de la T3 está en el árbol.
      ❗ **Queda, por orden del plan §18.5**: **T3** (construirla cuando el owner dé el «adelante» a
      §23; toca `OrderItemEditor` y `MixedPartySurcharge` → `CRITICAL_RE` + verificadores +
      `VERIFY_CONC=1`) · T4 el −X € (diseño cerrado en §20; el crédito deriva de los precios SELLADOS
      y el disparador ya es «solo completo») · T5 las palabras (D9, D8, el email de la devolución —
      el «hoy:» del desfase se disolvió con la T1) · T6 el guardián de solapes fuera del formulario
      (con el sello ya no mueve dinero) · el **AFORO** sigue aparcado por el owner (zonas distintas,
      spec propia).
- [ ] **D · JumpPoints y vales** — `docs/specs/lealtad-jumppoints.md`. Ledger append-only, saldo
      derivado, vale **en especie** canjeado **en puerta**. ⚠️ **No es dinero, pero se protege como si
      lo fuera**: el canje entra en el `CRITICAL_RE` del `pre-push` y necesita su verificador de
      concurrencia sobre MySQL real. ❗ Su **caducidad de puntos depende del cron** (`#115`), que
      `#137` **no desbloquea** —Redis entró solo para caché—, así que va la última.
      ❗❗ **REVISADA (`#156`, §8): su §4.2 descansaba sobre un hecho NO OBSERVABLE.** El sistema no
      sabe si un cliente vino —`tickets` tiene las columnas del ciclo y **cero escritores**—, así que
      «los puntos se abren después de la visita» no tenía de dónde colgar.
      ✅ **Resuelto por el owner**: los puntos tienen **FUENTES configurables** — la **visita**,
      acreditada en la pantalla de puerta, y la **compra**, al confirmarse el pago. El ledger lo
      aguanta sin cambios (`available_from` ya era por apunte).
      ⚠️⚠️ **Y de ahí sale lo que no se puede perder**: la fuente «compra» **reabre el agujero de
      ingresos** si sus puntos se abren al instante. **Lo configurable es CUÁNTOS puntos da cada
      fuente, no CUÁNDO se abren.**
      ⚠️ **El orden `A → D` pasa de preferencia a DEPENDENCIA DURA**: sin la pantalla de puerta no hay
      fuente «visita».

⚠️ **Aplazado a propósito: un sistema de PROMOCIONES** (descuento porcentual sobre productos, sobre el
total y sobre complementos, con caducidad por tiempo o por número de usos). No se parece a la lealtad:
los vales salieron del núcleo de dinero canjeándose en puerta, pero **un descuento sobre el precio no
tiene esa salida** — entra en `PAY-12`, en `OrderCreator`, en los dos ejes del ledger y en la pregunta
de si un reembolso devuelve el precio con descuento o sin él. Y «hasta gastarse N veces» es otra
carrera. Es un proyecto de dinero. ▶ **Los REFERIDOS son la excepción**: son lealtad pura y caben en el
ledger de puntos de **D**.

### El DESGLOSE de dinero que ve el cliente ✅ — **CERRADO: las tres tandas y los CUATRO defectos de lectura**
> Spec: `docs/specs/desglose-dinero-cliente.md` · Decisiones: `DECISIONES #127` y sus apartados
> `(b)`–`(f)`. **Va ANTES de Fase 5.**
> ✅ **Tandas A y B EJECUTADAS el 2026-08-24**: el dominio dice la verdad y el desglose se entiende.
> ✅ **Y los TRES defectos de LECTURA (`L1`·`L2`·`L3`) también** (`DECISIONES #128` · spec §18): el
> desglose pasa de **legible** a **VERIFICABLE** — el cliente ya ve qué salió de su banco, cuándo y
> por qué canal.
> **Medido al cerrar**: la matriz de las 23 acciones del panel deja **0 columnas ilegibles** (eran
> 18), el eje del valor **cierra en 50 de 50** pedidos por HTTP real y el eje de caja **se enseña en
> 37 de 58** (el cliente solo lo veía en 9 de 38 sanos).
> ✅ **Y la TANDA C también** (`#129`): «Mis pedidos» es pantalla propia. ⚠️ Prometía no tocar dinero
> y lo primero que encontró fue que **`GET /me/orders` perdía dos pedidos de 57**.
> ✅ **Y una SEGUNDA VUELTA con el owner delante** (`#130` · §20): «Mis reservas» deja de enseñar
> dinero, el eje de caja solo aparece cuando dice algo NUEVO —repetía el mismo importe con dos nombres
> casi iguales— y ⚠️ **se arregló una línea que faltaba**: los complementos no se pintaban en «Mis
> pedidos», así que un pedido real ponía 120,00 € en la reserva y 124,00 € de total.
> ✅ **Y una TERCERA, revisando `R-L6UTIA` a fondo** (`#131` · §21): la fecha del cobro se pegaba a un
> importe que **no se cobró ese día** (7 pedidos, **6 sanos**) y la línea del cargo por cambios **no
> decía por qué se cobra** — en los OCHO `extra_due` de la BD, cinco del flujo real.
> ✅ **Y la AUDITORÍA DE LAS 25 ACCIONES + la decisión que faltaba** (`#132` · §22): corpus borrado y
> reconstruido por los flujos REALES, un pedido por acción accionable → **25/25 identidades cierran y
> 25/25 lo que se pinta suma**. Y `PAY-16`/`PAY-17` pasan de guarda de TEST a comprobación **en
> ejecución**: al cliente se le oculta la descomposición y se le da una frase honesta, al operador se
> le enseña en rojo, y el parque se entera por log.
> ❗ **DECIDIDO por el owner** (`#133` · §22.2): de las tres cosas planteadas, **`L5` NO era un defecto**
> —«Resto de la señal de X» ya es correcto: X es la RESERVA, no el producto— y **`L4` se aparca**.
> ✅ **Y `L6` EJECUTADO** (`#134` · §23): «Importe al reservar» dice **hacia dónde y cuánto** se movió
> el pedido, con la frase compuesta por el DOMINIO y nombrando la **diferencia**. ⚠️ Y con ella se
> movió la **condición**: la pantalla dejó de comparar los dos importes por su cuenta.
> ▶ 🟩 **CON ESTO LA SECCIÓN QUEDA CERRADA.** Después, la Fase 5.
> ⚠️⚠️ **Las tres tandas ORIGINALES de esta sección estaban MAL DIMENSIONADAS y se sustituyeron**
> (tercera auditoría sobre **58 pedidos en MySQL + 6 en MariaDB**, proyección medida **por HTTP**):
> la vieja «tanda 1» se anunciaba como «riesgo cero» y en realidad tapaba cuatro defectos de dominio.

- ✅ **Tanda A · el DOMINIO y sus guardas — EJECUTADA**
      (2026-08-24 · `specs/desglose-dinero-cliente.md` §15 · `DECISIONES #127`, `#127(c)`, `#127(d)`). **El dominio dice la verdad y tiene guardas.** Seis arreglos, todos
      **verificados por mutación**:
      · **la fecha RE-TARIFICA** al precio del día destino, con su límite (solo al mover el día) —
        cierra el agujero de ingresos de §13, y el previo del operador deja de afirmar «sin cambio»;
      · **un pedido cancelado no tiene valor vivo**, por los dos caminos **y por construcción**;
      · **sin cobro no hay cobro** (las cestas de puerta exigen `paid_at`), en los TRES sitios;
      · **todo reembolso se atribuye a su reserva**, también el total, sin fuga de céntimos;
      · el **MOTIVO** del reembolso, preguntado solo cuando hace falta;
      · el **docblock** de `refundItem` y el **control negativo** de `redsys:verify-sandbox`, que
        salía en verde — ahora verificado contra el sandbox REAL (comercio del owner, terminal 45).
      ▶ **Guardas: 5 invariantes → 13, y 6 escenarios → 11.** `PAY-16` (eje valor, cinco canales),
      `PAY-17` (eje caja) y `PAY-18` (la tarifa). Los cinco escenarios nuevos son el trabajo de
      verdad: los seis viejos ya pasaban porque su hueco estaba en el FIXTURE, no en la aserción.
      ▶ **Medido sobre datos reales, no solo fixtures**: las dos identidades cierran en los 58
      pedidos de MySQL salvo en 20, y **los 20 son datos escritos a mano** (18 `paid` sin fila
      `Payment`, 1 con la columna de reembolso a mano, 1 artefacto de un guion viejo). **Todo pedido
      creado por un flujo real cumple.** Y la matriz del panel: **23 acciones, 0 rompen ninguna
      identidad** (antes 3).
      ⚠️ **NO fue «riesgo cero»**, como prometía la vieja tanda 1: cambió conducta, que era el punto.
- ✅ **Tanda B · la PROYECCIÓN — EJECUTADA** (2026-08-24 · spec §16). **El desglose ya es LEGIBLE.**
      El defecto de fondo era estructural: las ocho superficies componían **cada una la suya**. Ahora
      lo compone `Booking\Services\OrderLedger` y todas pintan; el contrato lo publica en **dos ejes**
      (`Ledger`/`LedgerValue`/`LedgerCash`) y los seis campos sueltos de la raíz se van dentro.
      ▶ **Los cuatro defectos de proyección, cerrados**: aparece «Pagado en el parque» · el eje de
      caja sale de la columna del valor · «Subtotal» pasa a «Importe al reservar», al pie y solo si
      difiere · y «Pagado por web» deja de ocultarse. Más **la FRASE** que explica el estado, que es
      lo que un número no puede decir — y que elige el MOTIVO que registró la tanda A.
      ▶ **LA MEDIDA QUE LO CIERRA**: la matriz de las 23 acciones del panel, re-corrida entera →
      **0 de 23 dejan la columna ilegible** (antes 18). Y el eje del valor **cierra en 50 de 50**
      pedidos medidos por HTTP real.
      ▶ `LedgerSingleSourceTest` prohíbe el MECANISMO —derivar un canal restando otros— aunque el
      resultado sea correcto hoy. **Verificado por mutación, 5/5.**
- ✅ **`L6` · «Importe al reservar» dice hacia DÓNDE y CUÁNTO — EJECUTADO** (2026-08-24 · spec **§23**
      · `DECISIONES #134`). Lo último que quedaba del desglose, con la redacción acordada y **sin
      añadir ninguna línea**: la frase pasa del diccionario del cajón al DOMINIO y nombra la
      **diferencia**, no el valor —«Al reservar se facturaron 180,00 €. El pedido cambió después y
      ahora vale 60,00 € menos.»—.
      ▶ ⚠️⚠️ **Y la CONDICIÓN se movió con la frase**, que es el trozo que importa: `invoiced_hint`
      es `null` exactamente cuando no hay nada que trazar, así que la pantalla **dejó de comparar los
      dos importes por su cuenta**. Es `L1` aplicado antes de que cueste — allí la condición
      re-derivada divergió en 19 de 58 pedidos sin que nada fallara.
      ▶ **Medido por HTTP real y compuesto por el módulo REAL**: 26 pedidos, **11 publican frase**
      (6 subidas, 5 bajadas), **0 se quedan cortos y 0 se pasan**. `R-LVWTRS` y `R-NKEASV` salen
      literales.
      ▶ **[DECIDIDO owner]** el pedido que se queda en 0 —dos cancelados y uno vaciado— usa la MISMA
      frase: una tercera variante diría en tres idiomas lo que la frase de estado ya dice encima.
      ▶ ⚠️⚠️ **Y una guarda NACIÓ DECORATIVA**: la de idiomas salía verde con la clave francesa
      borrada, porque Laravel **cae al idioma de respaldo** — una clave que falta no se ve como una
      clave en crudo, se ve como un francés leyendo castellano. Se arregló con
      `Lang::has(…, false)`. **5 mutaciones, las cinco muerden.**
- ✅ **`L1`·`L2`·`L3` · los TRES defectos de LECTURA — EJECUTADOS** (2026-08-24 · spec **§18** ·
      `DECISIONES #128`). Salieron de que el owner mirara un pedido REAL en pantalla —`R-L6UTIA`,
      §17—, y **el desglose pasa de LEGIBLE a VERIFICABLE**.
      · **`L1` el ancla de caja se ve siempre que haya habido un cobro.** «Cobrado por web» es lo
        ÚNICO que el cliente puede cotejar con su extracto, y en un pedido normal no lo veía nunca.
        ⚠️ **No era un fallo, eran dos**: al predicado (`hasCash()`) le faltaba el término del cobro,
        **y ninguna superficie lo llamaba** —el cliente re-derivaba la condición en JS y el panel en
        su blade—. Ahora se publica (`ledger.cash.has_cash`) y las dos preguntan al mismo sitio.
      · ⚠️ **Y hacerlo visible obligó a publicar el MÉTODO**: el eje de caja suma todos los pagos sin
        mirar el `provider`, así que un pedido de **taquilla** habría dicho «Cobrado por web» de un
        dinero entregado en mano. `charged_method` viaja como **enum y no como rótulo** (dos voces,
        §10.4), y con él la **fecha**: sin ella el importe no se busca en un extracto.
      · **`L2`** la cantidad va con su sustantivo («8 invitados · 216,00 €»), compuesta por el
        dominio: `8×216,00 €` se lee como 1.728 €. El complemento tenía el mismo defecto.
      · **`L3`** la nota de la reserva deja de decir «Señal» a lo que no lo es — con **clave propia**:
        en la CESTA sí es la señal, y cambiar la clave común habría roto la otra pantalla.
      ▶ **Medido**: el eje de caja pasa de verse en **9 de 38** pedidos sanos a **37 de 58**; la
      divergencia panel↔cliente sobre ese número (**19 pedidos**) desaparece; el cambio de condición
      **no altera el panel en ninguno de los 58**. **7 mutaciones, todas muerden.**
      ▶ ⚠️ **Y una cosa que solo el RENDERIZADO enseñó**: el eje de caja heredaba el color de
      REEMBOLSO, así que el ancla se habría pintado en ámbar **como si algo se hubiera devuelto**.
      Hacer visible lo que estaba oculto hereda decisiones tomadas para el caso oculto, y ninguna
      está en el diff.
      ▶ **Coste**: el techo del bundle cede `214,5 → 215,5 KiB` (medido +1,01) — el caso exacto para
      el que su comentario dice que debe ceder: **corrección medida, no «una pantalla más»**.
      ⚠️ **Pendiente de veto del owner**: tres cadenas (§18.6), una palabra cada una.
- ✅ **Tanda C · «Mis pedidos» como pantalla aparte — EJECUTADA** (2026-08-24 · spec **§19** ·
      `DECISIONES #129`). Zona propia (`ZONES.PURCHASES`), en el índice **y** alcanzable desde la
      reserva; el desglose **se MUDA** allí y «Ver pedido» lleva a la pantalla con ese pedido abierto.
      ⚠️⚠️ **Prometía ser la única tanda que no toca dinero, y lo primero que encontró fue que
      `GET /me/orders` PERDÍA PEDIDOS**: ordenaba solo por `created_at`, así que con pedidos creados
      en el mismo segundo `LIMIT/OFFSET` cortaba por donde quisiera. **Medido sobre los 57 reales**:
      57 filas recorridas, **55 distintas**, dos repetidos y **dos invisibles para su dueño**. El
      patrón del arreglo ya existía un fichero más allá (`CustomerReservationsReader::ordered()`).
      Después: **57 de 57, 0 repetidos, 0 invisibles**.
      ⚠️⚠️ **Y la guarda de conducta salía VERDE en SQLite**: hizo falta una estructural —el `ORDER BY`
      termina en columna única— para que muerda en cualquier motor (`TESTING.md` §2.sexies).
      · **`containing`**: la página que contiene un pedido la elige el SERVIDOR. Medido: `R-L6UTIA`
        está en la **página 7 de 12**; abrir la primera no falla nada y **no cumple la decisión**.
        Con dos reglas: un código ajeno se comporta como si no se hubiera enviado —si no, sería un
        **oráculo de códigos**— y `page` explícito gana.
      · **Sin segunda superficie de dinero**: es `orderRow()` tal cual, y la paridad compara los
        `financials` de las dos rutas campo a campo. Se retira `ensureOrder()`, que no tenía pruebas.
      · **Se PODÓ antes de subir techos**: tres rótulos que viajaban en cada página con sesión y que
        **ninguna superficie leía** (205 B); el neto del grupo nuevo es **+93 B** en vez de +298.
      ▶ **8 mutaciones, todas muerden.** Chunk: `215,5 → 219,5 KiB` (+4,10 medido).
- ✅ **El sandbox de Redsys, VERIFICADO en su integración** (2026-08-24, credenciales del owner:
      comercio `263100000`, terminal 45): firma `HMAC_SHA512_V2` **aceptada por el banco**
      (`SIS0054` = denegación esperada sobre operación inexistente; el control `--bad-key` da
      `SIS0042`). ⚠️ **Una medición anterior lo dio por inalcanzable y era un ERROR**: se probó el
      puerto 443 y Redsys sirve el sandbox en el **25443**, que es el que el código usa.
- [ ] **Lo que queda de `PAY-08`**: un `Ds_Response=0900` REAL, que exige una autorización previa en
      el sandbox (pago de prueba con tarjeta) y después `--gateway-order=`. Es navegador sobre staging.
- [ ] ⚠️ **Y arreglar el verificador: su CONTROL NEGATIVO sale en verde.** `report()` da
      «✅ INTEGRACIÓN VÁLIDA» a cualquier `gateway_denied`, incluido el `SIS0042` de firma rechazada.
      Va en la tanda A (spec §9.5).

⚠️ **Lo que las tres auditorías dejan demostrado, para no repetirlas**: **NO hay dos fuentes de
verdad** —panel y cliente enseñan el mismo «Pagado online» en **64 de 64**—, la rama **legacy es
inalcanzable por código** (se retira), y **el modelo decidido cierra sus dos identidades en 58 de 58**
pedidos reales, disparando su estado imposible solo sobre los 19 de datos sucios. El riesgo NO estaba
solo en la proyección: había cuatro defectos en el dominio que ninguna auditoría anterior construyó.

### El CAMBIO DE PRECIO ✅ — la línea `#145`→`#155`, CERRADA el 2026-08-25

> El tronco: `PAY-18` (`#131`) hizo que mover la fecha RE-TARIFIQUE y **seis sitios** seguían
> escritos sobre la premisa vieja («el valor solo baja si baja la cantidad»). Todo medido
> ejecutando (sondas sobre MySQL con los flujos reales), arreglado con mutaciones que muerden
> (**16 en total**, todas verificadas mordiendo) y verificado en vivo.

- ✅ **`#145`** · el registro del pedido legible (sesión anterior; verificado en staging).
- ✅ **`#149`** · la auditoría de los 3 escenarios del owner (6 pedidos-sonda) **+ D5**:
      «Reembolsar» pregunta CUÁNTO — radio remanente/otro importe, sugerencia = «pendiente de
      devolución», topes bajo lock intactos (`PAY-09`). Verificado en vivo: deuda de 10,00 →
      devueltos 10,00 exactos.
- ✅ **`#150`** · **D4+D3+D2 y el sexto sitio**: el cambio de precio viaja estructurado
      (`unit_price_change`), la reconstrucción calcula `cantidad_original × precio_original`
      (primer cambio de cada clase, desempate por `id`), el marcador se dispara con cualquier
      causa, los textos dicen la verdad y «+N producto» exige un `quantity_change` real.
      **El callejón del dinero atrapado, cerrado** — y **el pack CON señal, MEDIDO** (la cascada
      absorbe; cero reembolsos).
- ✅ **`#151`** `[DECIDIDO owner]` · la independencia de cupos se hace **POR ZONA** — el consumo
      que `#148` midió es correcto; `PackConsumesEntrySeatsTest` pasa a guarda de la regla.
- ✅ **`#152`** `[DECIDIDO owner]` · un pedido **CANCELADO con deuda se reembolsa POR LÍNEA**
      (antes: ninguna vía de panel) y «devolver fuera» se registra con el modo manual, sin canal
      nuevo. El banner del cancelado dice cuánto se debe y por dónde.
- ✅ **`#153`** `[DECIDIDO owner]` · el reembolso de PEDIDO **nombra el importe exacto** y, sin
      «también cancelar», señala la vía de los parciales. Sin campo aquí a propósito.
- ✅ **`#154`** · las etiquetas del cargo de puerta que lee el CLIENTE viven en `tickets.*`
      (ES/EN/FR) — un cliente EN recibía la clave LITERAL en su desglose (medido por HTTP).
- ✅ **`#155`** · el email de una BAJADA cuenta el dinero: la deuda que aflora («pendientes de
      devolverte», mismo vocabulario que la pantalla) y lo absorbido en puerta.
- [ ] **Ver en navegador** el modal nuevo de «Reembolsar» (reactividad del campo) — todo lo demás
      está verificado por Livewire + mutación. El owner tiene **9 pedidos-sonda** en su BD local
      para probarlo (`ESTADO.md` § BD de desarrollo).

### La LANDING white-label 🟦 — **tanda A CERRADA (6 de 6) · B EN CURSO (1 de 4) · C sin empezar**

> Spec: `specs/landing-white-label.md` · Decisiones `#136` (el marco), `#138`–`#140`, `#143`
> (el barrido de color y el paquete de tema) y `#144` (el «0 m²»).
> **La línea es: data-driven el DATO, no la PÁGINA.** Nace del mockup del SEGUNDO cliente.
> ⚠️ **Empieza por §1 de la spec**: de los cinco problemas que enunció el owner, tres NO eran lo que
> parecían — y **tres afirmaciones de la propia spec** también resultaron falsas al medirlas
> (§4.5.1 ×2 y §4.5.2). Lee cada corrección antes que el texto que corrige.
>
> ⏸️ **B está PARADA a la espera del DISEÑO, y es lo primero que hay que saber** (2026-08-25): el
> owner está rehaciendo el sistema visual en Claude Design y **los datos del mockup NO son
> fidedignos** —«lo que hay que llevarse es la estructura, las formas, los botones, los colores y
> los layouts; los datos son los que tenemos ahora»—. Detalle y orden acordado en `ESTADO.md`.

- ✅ **A · el acento de zona sale del nombre de la clase** (2026-08-25, `#138`). Diez clases
      `.x--{accent}` retiradas; cada zona pinta su color en línea con `ThemeSettings::zoneStyle()`.
      ⚠️ **Arregló un defecto VIVO**: dos zonas con el mismo `accent` y distinto `color` divergían
      —`cap` salía naranja en la tarjeta y lima en la pestaña— y un acento desconocido **no se pintaba
      en absoluto**. Con ello entran `zones.color_secondary`, el ajuste `theme.brand_secondary` y el
      token semántico `--attn`.
- ✅ **A · la paleta del primer cliente sale del producto** (2026-08-25, `#139`). El acento de un pack
      se deducía **buscando «kids» en su NOMBRE**; ahora sale de su zona. Se van tres ternarios del
      diseñador de invitaciones, dos botones con «Jump»/«Kids» escritos dentro, **una copia de la
      fórmula de contraste en JavaScript** y los `--jump-*`/`--kids-*` del `:root`.
      ▶ `ZoneAccentIsNotAClassNameTest` queda **absoluta**: su lista de excepciones, vacía.
- ✅ **A · el icono por producto** (2026-08-25, `#140`). Lo decidía un booleano escrito **tres veces**,
      así que un catálogo entero se repartía en **dos dibujos**. Ahora `ticket_types.icon` (set curado,
      decisión del owner), resuelto por el dominio, publicado en el contrato y con un registro en el
      cajón que **retira las cuatro copias de geometría**.
- ✅ **A · el inventario de colores en crudo** (2026-08-25, `#143`). ⚠️ **Eran 234 ocurrencias, no 76**:
      la cifra de la spec salió de un `grep` de `#hex` línea a línea, que no contaba `rgba()` —la
      mayoría— ni valores multilínea. Y la pregunta («cuáles suben a token») era la equivocada:
      **144 no necesitaban ningún token nuevo, eran tokens que YA existían reescritos a mano** —114
      de ellas `--fg` como `rgba(20,19,15,α)` con 28 alfas—. Convertidas a `var()`/`color-mix`, que
      premultiplica y rinde el mismo color: **0 píxeles de diferencia fuera del hero**, medido en
      navegador sobre cuatro páginas.
      ▶ **Destapó dos fugas de marca VIVAS**: en `.hero__stage-placeholder` los acentos de las dos
      zonas del primer cliente estaban en DECIMAL dentro de un `background` de tres líneas. `#139`
      los retiró **por su nombre**; el valor sobrevivió. Lo cierra `RawColourIsNotATokenTest`,
      que compara por VALOR RGB.
      ▶ Blanco y negro quedan FUERA por decisión del owner: convertirlos cambia píxeles. `DEUDA.md`.
- ✅ **A · el hueco del paquete de tema del cliente** (2026-08-25, `#143`). ⚠️ **No existía.** El
      layout no tenía por dónde entrar una hoja de instalación, así que tokenizar era trabajo que
      ningún cliente podía usar. Hoy carga `public/css/client.css` **la última**, y el mecanismo son
      **tres piezas** —el orden, el `.gitignore` y la exclusión del `rsync --delete`—, las tres
      aseveradas porque las tres pueden faltar por separado y con dos parece que funciona.
- ✅ **A · el spinner rebrandeable** (2026-08-25, `#143`). ⚠️ **El dibujo NO era un fichero**: dos
      pseudo-elementos y un `@keyframes` dentro de `spinner.css`, cuya doc decía además que «no se
      modifica». Hoy la hoja se parte en **§A contrato** y **§B dibujo**, y una instalación redefine
      §B desde `client.css`.
      ▶ **Y al separarlos apareció un defecto de accesibilidad real**: `prefers-reduced-motion`
      apagaba solo `::before`, la única pieza que anima el dibujo del PRIMER cliente. Cualquier
      dibujo sustituto se habría seguido moviendo, sin fallo y sin aviso.
- 🟦 **B · el contenido** — EN CURSO. ⚠️ **Los tres cortes se midieron antes de empezar y NINGUNO era
      lo que la spec decía** (`#144`):
  - ✅ **B · la home anunciaba «0 m²»** (2026-08-25, `#144`). Apareció midiendo las consultas, no
        buscándolo: `area_sqm`/`rides_count` son NULLABLE y `number_format(null)` devuelve «0», así
        que **dos de las cuatro zonas visibles publicaban que miden cero**. Sin dato ya no se pinta la
        métrica. ⚠️ **Vivía DOS veces** —las dos ramas del bucle de zonas tenían el mismo marcado
        copiado— y el primer arreglo tocó solo una: ahora es un componente compartido.
        ▶ Y en PHP 9 `number_format(null)` deja de ser un aviso y pasa a ser un **500 en la portada**.
  - ⛔ **B · `park_stats` NO SE CONSTRUYE** — `[DECIDIDO owner, 2026-08-25]`, y por una medida.
        ⚠️ **`park_stats` NO era «los números del hero»**: el hero no tiene números, y los de la
        sección de zonas son **CALCULADOS del dominio** (`$zones->sum('area_sqm')`…). El único bloque
        tecleado que existe, `landing.hero.stats`, **no lo usa nadie: es copy muerto en tres lenguas**.
        ▶ Y medido contra el mockup del 2º cliente: sus tres `statsParque` **son exactamente los tres
        que el dominio ya calcula**, así que la tabla nacería VACÍA. §6·6 mide el éxito en **CERO
        migraciones**: crear una que nadie usa va en contra. **Ficha en `DEUDA.md`**; se construye el
        día que un cliente pida un número que el dominio no sepa.
        ▶ Lo que sí falta de ahí son las **ETIQUETAS** editables («m² de parque» vs «M² de diversión»),
        y eso baja con el copy.
  - [ ] ⏸️ **B · `testimonials`** — **APLAZADO por el owner (2026-08-25 tarde, `#158`) hasta que la
        landing esté terminada y se pueda VISUALIZAR**. SÍ se construye, y no solo para la landing: es el **respaldo de las
        reseñas de Google** (`specs/google-reviews.md` §4.4.bis). Campos medidos del mockup:
        `texto`/`nombre`/`meta` + valoración. ⚠️ **Su ayuda en el panel NO puede decir «por si Google
        falla»**: es lo que ve **todo visitante que no acepta cookies de terceros**, cada día.
  - [ ] **B · el copy al CMS.** ⚠️ **244 claves, no 142**; 174 referenciadas en **24 ficheros**, no en
        `home.blade.php` — entre ellos el footer de los **CORREOS**, las páginas de **error** y cuatro
        servicios PHP. Y hay claves **dinámicas** (`__('landing.info.weekdays.'.$dow)`) que ningún
        grep estático ve: bajarlas al CMS las rompe sin que nada falle.
        ⏸️ **Conviene esperar al diseño**: es la landing la que dice qué copy necesita ser editable.
  - [ ] **B · la landing del 2º cliente** como primer paquete de tema (medida de éxito: §6·6).
        ⏸️ **PARADA: el owner está rehaciendo el sistema visual.** El mockup que se midió el
        2026-08-25 lleva la paleta **ANTERIOR** —su propia tabla de migración lo dice: `#2FB6DE` →
        `#1AA9DE`—, así que **todo lo medido de él sobre color está CADUCADO**.
        ✅ **Lo que sigue valiendo**: sus secciones (`zonas · cumpleanos · antes · info · opiniones ·
        reservar`) y que **la tanda A ya se validó contra su paleta**: con un `client.css` de esa
        gama, `/aviso-legal` retiñó al **100 % con 0 px del primer cliente** y la home al 84 % — lo que
        sobrevivía es `zones.color`, que es **dato del panel**, no código.
        ▶ **El sistema de color NUEVO ya está leído y medido**: artboard «Colores de Marca PJP» del
        canvas de diseño, con 8 colores de núcleo, 9 neutros, roles por elemento, auditoría WCAG de
        20 pares y tabla de migración. Encaja **casi 1:1** con los tokens que ya existen. Resumen y
        los dos huecos que abre, en `ESTADO.md`.
- [ ] ⚠️⚠️ **C · los servicios como PRODUCTO REAL.** Toca `AFORO` y `PAY`, exige `VERIFY_CONC=1` y
      **NO se diseña desde la spec de la landing**: necesita la suya. Medido: los tres servicios de
      `/servicios` tienen **0 productos vinculados** y dos llevan **tablas de precios TECLEADAS**.
      ▶ **Y su alcance ENCOGIÓ**: lo de «franjas fuera del horario de apertura» era una afirmación
      falsa —`SlotGenerator` lee solo `slot_templates`; `opening_hours` es presentación—, así que ya se
      pueden crear. Queda el eje de **precio por tramo de cantidad**, que es la misma pieza que el
      sistema de promociones que el owner quiere.
- ⏸️ **APARCADO por el owner (2026-08-25): zonas y cupos se quedan como están** hasta ver cómo se
      comportan las reservas de packs distintos con gente real. Lo medido en esa conversación —que una
      zona hace CINCO cosas y no una, y que dos zonas son dos pozos que no saben que comparten suelo—
      queda en la spec §4.4 para no volver a deducirlo.

### La CAPA DE TEMA 🟦 — **los SEIS mecanismos, el ARMAZÓN entero en los doce anchos, el paquete real y la MARCA del 2.º cliente instalada**

> Spec: `specs/tema-por-instalacion.md` (empieza por **§14**, que es el paquete real y lo que
> destapó; luego §1.7 y §10) · Decisiones `#192`, `#193`, `#194`, `#195`, `#196` y **`#206`**.
> El armazón tiene spec propia: `specs/armazon-y-menu.md` (`#200`, `#201`, `#203`, `#204`, `#205`,
> `#211`, `#213`, `#214` y **`#216`**).
>
> ✅ **La 2c·8 CIERRA el armazón en escritorio** (`#216`, 2026-08-28): **el armazón nace bajo el
> hero** —logo, CTA y hamburguesa entran juntos al encoger el hero, con la aritmética del mockup
> verificada (0,5617 calculado / 0,561 medido)—, **el hero recupera sus dos botones** (lo que
> REABRE `#195`, porque las dos mitades no se pueden separar), el **CTA doble se alinea en sus ocho
> medidas** y entra **el paquete de MARCA del 2.º cliente**: los huecos del producto pasan de 2 a 9.
> ✅ **La 2c·4b TAMBIÉN está hecha** (`#221`): el menú a pantalla completa manda en los doce anchos
> y la barra inferior se queda como el owner la validó (`[DECIDIDO owner]`). El cajón lateral queda
> **apagado, no retirado** — ficha en `DEUDA.md`.
> ✅ **Y la 2d, el MOVIMIENTO** (`#222`, §16): el SEXTO y último mecanismo. Medido antes —239
> declaraciones, 53 duraciones, 20 curvas, y **200 de los 220 usos de curva eran `ease`**— y
> después: **608 usos de token, cero literales fuera de la escala**.
>
> ✅ **EL HUECO DE ILUSTRACIÓN POR INSTALACIÓN, Y LA PRIMERA PASADA DEL MATERIAL DE FACHADA**
> (2026-08-31, `#286`). El **tercer** hueco por instalación —tras el logotipo y el icono— y el
> primero para ILUSTRACIÓN y no para marca; cierra la deuda de `#257`. Spec propia:
> **`specs/hueco-ilustracion.md`**, que **CORRIGE tres afirmaciones de `elementos-fachada.md`**.
> ▶ El vehículo es **`<use>` externo**, y sale de medir los cinco en navegador con control: es el
> único **INERTE** que además da **los tres tratamientos desde una geometría**.
> ⚠️⚠️ **La fuga de `currentColor` afecta a LOS TRES**, no solo al troquel: plano filtra 35,6 % de
> píxeles ajenos, el contorno **sale MACIZO** y el troquel **pinta el 100 % de la caja**. Se cierra
> fijando `color` en el `<use>`.
> ▶ **Entra en la web**: la trama generalizada fuera del menú (refactor **neutro al píxel**), la que
> se apaga en `/normas`, la niebla en `/contacto`, los rayos quietos en `/precios`, la tira en cuñas
> y **las poses de zona** en las tarjetas de la portada.
> ❗ **La regla que dejó la jornada**: *la decoración va en la PANTALLA, no en el componente que se
> repite* — tres piezas rechazadas por el owner, y las tres por lo mismo.
> ⚠️ **La lista de ranuras decorativas está VACÍA a propósito**: una ranura sin consumidor es lo que
> dejó los 19 dibujos de `#257` esperando años.
>
> ✅ **IDIOMA VISUAL · T1: LAS NORMAS DE LA PORTADA** (2026-08-31, `#292`). Fuera el **pliego de
> pictogramas del cliente antiguo** y el carrusel con todas las normas; quedan **tres** en texto y el
> CTA a `/normas`. El tope de tres se declara en la VISTA —el panel decide QUÉ normas, el diseño
> CUÁNTAS caben— y hay guarda con mutación.
> ▶ **Primera ranura decorativa del producto** (`slot-normas`, mancha `B1·03`), con su consumidor en
> el mismo cambio. **Presupuesto: una pieza por sección, tres en toda la portada.**
> ❗❗ **Y una FUENTE retirada**: `[owner]` «de la landing mockup solo sacaremos las reseñas», lo que
> **corrige a `tema-por-instalacion.md` §1** y tumbó el registro fino de badges de la misma jornada —
> `[DECIDIDO owner]` «todo al registro del mural»: una sola voz.
> ⛔ **El bar / zona de Ocio NO entra**: era lo único del encargo que tocaba el modelo de datos.
>
> ✅ **EL IDIOMA VISUAL HEREDADO, TANDA A** (2026-08-31, `#290`) — y **el encargo cambia de marco**:
> `[owner]` *«primero hay que cambiar lo que tenemos»*. La landing lleva elementos del cliente ANTIGUO
> y hay que sustituirlos, no decorar encima. Spec propia: `specs/idioma-visual-heredado.md`.
> ⚠️⚠️ **No había sistema de etiquetas**: cinco formas, cinco paddings, cinco tallas y cuatro
> rotaciones para la misma función. Ahora es UNO, con dos registros repartidos por lo que el badge
> HACE —**SEÑAL** (E2 del mural) y **DATO** (el mono fino de su landing)—, porque **sus dos artboards
> las visten distinto** y `[DECIDIDO owner]` entran los dos.
> ▶ **`.jj-block` fuera**: el «foam» del cliente antiguo, con **sus iniciales en el nombre de la
> clase** y cinco de seis variantes sin usar. Separa la punteada de `C2`.
> ⚠️⚠️ **La guarda de `#287` cazó a quien la escribió**: el separador nuevo repetía una textura por
> fila. *Un separador es puntuación, y la puntuación la pinta el CSS.*
> ⚠️ **`TagSystemTest` vigila la EROSIÓN** —el «pelín más de padding»— y ya cazó dos tallas dentro de
> la misma etiqueta.
>
> ✅ **Y ESA PRIMERA PASADA, SANEADA** (2026-08-31, `#287`) — sesión sin material nuevo
> (`[DECIDIDO owner]`). Tres defectos que la suite no veía, los tres medidos en navegador:
> ▶ **`<x-site.ilu>` emitía un `<svg>` VACÍO** cuando el kit no traía ese dibujo —solo preguntaba por
> el fichero, no por la clave—: sin `viewBox` la caja cae a los **150 px** por defecto de un elemento
> reemplazado, y en la portada eran **dos de 190×150** (zonas `cap` y `cap2`). ⚠️ **El caso es el
> NORMAL**: `kit:build` no exige un dibujo por zona, y hace bien.
> ▶ **Dos reglas nacieron MUERTAS** —`.brand-dots` y `.grain--zona`, `dots: 0` en las doce capturas—
> y se retiran: vuelven con su consumidor.
> ▶ **`/normas` pintaba la trama dentro del `@foreach`**: cinco copias en una pantalla, que es la
> forma que el owner rechazó tres veces. Pasa a **una, en la cabecera**, y de paso vuelve **su**
> parada del 74 % — estaba bajada al 30 % por el párrafo de la tarjeta, y ese párrafo ya no está.
> *Un ajuste sobrevive a la razón que lo justificaba si nadie lo revisa cuando la pieza cambia de sitio.*
> ▶ **Dos trinquetes nuevos**: `FacadeCssHasNoOrphansTest` (ninguna regla de fachada sin pantalla) y
> `FacadeDecorationIsPerScreenTest` (ninguna textura dentro de un bucle, ningún dibujo del kit con
> clave literal dentro de un bucle). Las dos nacieron **rojas con el defecto real puesto**.
> ⚠️⚠️ **Y una clase compuesta no la ve ningún inventario**: `.ilu--plano` y `.ilu--contorno` salían
> huérfanas con el producto sano por armarse con `'ilu--'.$trato`. Se escriben enteras — es el motivo
> por el que el cajón arrastra ~50 reglas que *parecen* muertas y nadie puede confirmar.
>
> ✅ **EL CARRIL C REABRE Y HACE EL MOCKUP 1:1** (2026-08-28 tarde, `#225` → `#235`,
> `[DECIDIDO owner]`: «lo quiero idéntico 1:1 — hero, menú, transiciones, animaciones, y lo mismo
> en el footer y el hero del footer»). Once cortes:
> - `#225` la barra de móvil **es el mismo botón** que el racimo (medido: 100 px de alto contra 54,
>   chip 64×42 contra 30×26 y las dos mitades naranjas). `.cta-prime` retirado.
> - `#226` la **tira de marca** sube al hero y **viaja** · ⚠️⚠️ y aparece que
>   `html, body { overflow-x: hidden }` **rompía TODOS los `sticky`**: el hero de la portada
>   **nunca se pegó** desde `#195` y dejaba **569 px de banda vacía**.
> - `#227` el CTA de la **primera pantalla**, con relevo al armazón · ⚠️ destapa que once reglas
>   pintaban el CTA de amarillo dentro del hero (selector más ancho que su intención).
> - `#228` el **menú a dos columnas** con vista previa · `#229`+`#233` el **hero del cierre** ·
>   `#230` el estado de apertura, que **había desaparecido de la web entera**.
> - `#231` el **minijuego** «Salta la ciudad», en trozo aparte (12 kB) · `#235` el cierre **1:1 de
>   verdad**: tag de la ciudad, rol de acción, CTA que se apartan al jugar y el espacio que arranca.
> ▶ **La lección que más se repitió: leer el bloque ENTERO del artboard sale más barato que
> reconstruirlo de memoria dos veces.**
>
> ✅ **Y un corte más, `#238` — LA COLUMNA** (2026-08-28 noche). El owner volvió al hero del cierre:
> «en estado normal no tiene el width correcto, tiene demasiada altura y oculta el footer». Dos
> defectos que se sumaban, ninguno visible a 1280 px:
> - ❗❗ **`--wrap-gutter` dentro de una caja ACOTADA mide al PADRE** (su `100%` es un porcentaje).
>   La tarjeta del cierre medía **700 px a 1920** y **160 a 2560** — y **`.menu__inner` tenía el
>   MISMO fallo desde `#201`**, con **60 px de columna a 2560**, sin que lo viera nadie. Entra
>   `--col-gutter` (longitud fija) y `CappedContainerGutterTest`, con sus 4 mutaciones.
> - **El hueco del minijuego estaba reservado DOS veces** (`.reserve__box` y `.reserve__body`):
>   **220 px de aire muerto** a 1280, y eso era lo que tapaba el pie.
> - Y dos divergencias más: el lienzo mide **150 px** fijos (no un `clamp`, y el alto ES el zoom) y
>   el titular tiene **otra escala en teléfono**, que es `#220` otra vez.
> ▶ **La lección de método, cuarta de este carril: no basta con medir, hay que medir DONDE el fallo
> puede aparecer.** Todas las sondas corrían a 1280 y 390 — los dos anchos donde no se ve.
> ❗ `[DECIDIDO owner]` la tarjeta va en la columna del **mockup** (1176). Queda su decisión sobre el
> pie, que sigue en `.wrap` (1380): igualarlos son las doce vistas (`tema-por-instalacion.md` §17.4).
>
> ✅ **Y `#250` — LA COLUMNA DEL SITIO** (2026-08-28 noche, `[DECIDIDO owner]`: «procede así,
> idéntico al mockup»). La columna pasa de **1380 a 1176**, la del mockup, en las doce vistas.
> - ⚠️ **No se ensancha: se ESTRECHA** — y había prueba interna de que la suya era la buena: el hero
>   ya acababa en 1240 (su número) mientras las secciones iban a 1380.
> - **No fue «cambiar la estructura»**: medido antes, `.wrap`, el pie y el `.nav` ya daban el mismo
>   número en las 12 vistas. Son **tres declaraciones y dos borrados**.
> - El ancho se escribe **una vez** (`--col-max`) y de ahí salen sus tres formas —`width`, sangrado y
>   caja exterior—. Guarda: `ColumnIsDeclaredOnceTest`, 6 mutaciones.
> - **El reposo del hero del cierre: 27 de 28 dimensiones idénticas al artboard.** La única real es
>   el canto de sus CTA (10 vs 14) y es contradicción del cliente consigo mismo (`DEUDA`).
> ▶ **Dos lecciones de instrumento**: un comparador que mide la envolvente de un GIRO miente, y un
> barrido de roturas **sin pasada de control** inventa roturas (31, las mismas antes y después).
>
> ✅ **Y `#251` — LA TRANSICIÓN DEL CIERRE**, a pregunta del owner («¿es idéntica al mockup?»).
> Se transcribió su `aplicaCierre` a la sonda y se comparó **fotograma a fotograma**: el
> crecimiento ya era el suyo (17 posiciones × 2 anchos; ancho 0,0 px, alto ≤ 0,5), pero **la
> retirada del armazón no** —curva LINEAL donde el mockup la hace CÚBICA, y leyendo el progreso
> equivocado—. ▶ **La coreografía publica DOS progresos**: el CRUDO manda la retirada y los
> umbrales, el SUAVIZADO manda la geometría. Al 7 % del crecimiento su armazón valía 0,19 y el
> nuestro 0,82. `CierreChoreographyTest`, 6 mutaciones.
> ⚠️ Una guarda propia se puso roja con el producto sano por aseverar el NOMBRE de un token.
>
> ✅ **Y `#252` — EL IMÁN, EL PIE A UNA FILA Y FUERA LA MARQUESINA** (2026-08-29, tres encargos del
> owner). El scroll **encaja en los dos puntos estáticos** de la portada; **no es el `freno` del
> mockup**, que bajando por el cierre te lleva a pantalla completa: aquí te **retiene** hasta que
> insistes. Vive en `ui/scroll-magnet.js` (mitad pura, 19 casos de `node --test`).
> - ⚠️⚠️ **El rumbo NO sale del último evento de scroll.** La portada crece 34 px al llegar al final
>   —55 imágenes sin proporción declarada— y el anclaje del navegador compensa: eso llega como un
>   evento hacia abajo. **Pasó los 16 casos unitarios y falló en el navegador.** El rumbo es el
>   movimiento NETO desde la última parada.
> - ⚠️ **El hueco del racimo se CALCULA**, no se estima en `vh`: a 900 px de alto el logotipo se
>   metía 5 px dentro del hero. Ahora, **12 px de aire en once ventanas**. Y apareció un token
>   **fuera de alcance** (la hamburguesa nunca bajaba a 48 en teléfono).
> - ⚠️ **La guarda de ese botón nació CIEGA** —resolvía la cadena de `var()` y bendecía el fallo—:
>   cuarta vez en tres días. Se asevera la lectura DIRECTA y el alcance del token.
> - El pie: **una fila que se desliza**, porque el número de destinos lo manda la instalación.
>   La marquesina de palabras, **retirada entera**; siguen la de `/servicios` y la de la galería.
>
> ✅ **Y `#254` — EL CIERRE A LA ALTURA DEL HUECO, EL HERO CON UN SOLO CTA, Y EL LOGOTIPO SALTA**
> (2026-08-29, cuatro encargos del owner). Con esto la portada queda como él la pidió.
> - **La tarjeta del cierre ocupa el hueco que le deja el pie** (`min-height` contra `--foot-h`,
>   que publica la coreografía): 757 px a 1920 —era 434— y la composición cabe con **20 px de
>   margen en 8 de 9 ventanas**. Y **el armazón se retira en cuanto la tarjeta se ancla**, que es
>   el sitio que había que liberar. `[DECIDIDO owner]`.
> - **El hero se queda con UN CTA**: el par del armazón, debajo del titular y de 224×54 a ~320×74.
>   Los dos botones propios de `#253` duraron una tanda — eran una TERCERA pieza de compra.
> - **«Diversión ON»**, con el ON como interruptor encendido. ⚠️⚠️ Usa `--ok` y **no** el rol de
>   acción, y lo dijo `ActionFillTest` con su propio argumento: acción es el control que hace
>   AVANZAR, y «encendido» es un ESTADO — el chip de «Abierto ahora» ya lo había resuelto así.
> - ❗❗ **El logotipo se sirve EN LÍNEA**, porque un `<img>` no se anima por dentro. **Eso cambia el
>   modelo de amenaza**: dentro de un `<img>` un SVG es inerte y en línea no. `InlineSvg` es lista
>   blanca y **todo o nada**; el logotipo desaparece antes que servir algo ejecutable, y la
>   plantilla tiene su suelo de texto. Coste fichado: ~64 KB de marcado en las doce vistas.
>
> ⚠️ **Y al cerrar sesión apareció un test ROJO UN MINUTO AL DÍA** (`#255`): afirmaba que una
> franja de `00:00` a `00:01` **de hoy** ya había terminado. No era del sujeto —la conducta estaba
> bien— sino de la pregunta. Arreglado con `travelTo` y con su simétrico, que faltaba.
>
> ❗❗ **EL CARRIL C NO TIENE NADA PENDIENTE DE AGENTE en hero, menú, pie y cierre.** Lo que queda es
> del owner: su ojo en navegador, cuál de las tres variantes de «El parque», y el aviso de contraste
> AA del color de acción. ▶ **Y quedan DOS encargos suyos sin empezar**: los **iconos** del canvas
> (47 UI + 3 cargadores + 14 zonas — con la decisión previa de si entran en el PRODUCTO o en el
> paquete de tema) y el **contenido real del cliente**. ▶ La tanda **3 (las secciones)** sigue **fuera de alcance por decisión
> suya**: «del canvas solo se toma el sistema de diseño; el resto son pruebas».
>
> ❗❗ **Lo que falta para que la landing sea 1:1 con el mockup, medido**: el **QUINTO mecanismo, el
> relleno de ACCIÓN** —el CTA del cliente es naranja y el del producto está atado a
> `background: var(--fg)`; 50 reglas rellenan con tinta y **no todas son acción**—; la tanda **2d**;
> el **menú en móvil** (espera artboard); y la tanda **3**, donde **opiniones** y **el minijuego del
> castillo** no existen y **`D9`/`B4` las bloquea el ARTE, no el código**.
> Hermana de `landing-white-label.md` §4.5: aquélla decidió que el tema son TRES mecanismos y
> ejecutó su tanda A; ésta construye **lo que aquélla dio por supuesto y no existía**.
>
> ❗❗ **DOS cosas de esta sección envejecieron el mismo día y la corrección va primero:**
> 1. **La premisa de §1.2 CADUCÓ.** «El sistema alterna dos superficies, nunca dos papeles seguidos»
>    **ya no es cierto**: el hallazgo `S-00` del owner —Alta, Aplicado— lo sustituye por **papel
>    continuo de arriba abajo, y el contraste lo dan las TARJETAS**. Verificado: **cero** fondos a
>    sangre en los 218 KB del mockup. ▶ El mecanismo de la tanda 1 no se pierde: **se usa más**,
>    porque `[data-surface]` vale igual en una tarjeta que en una sección.
> 2. **La tanda 2 se partió en cuatro** con el owner delante: **2a** (forma + pie, hecha), **2b** (el
>    hero, con la escala de sombra), **2c** (el menú → **spec propia**, toca 12 vistas) y **2d** (el
>    movimiento: 237 declaraciones, 48 duraciones y 20 curvas).

- ✅ **1 · Cimientos** (2026-08-27, `#192`). Las dos superficies como **ámbito**, `--sheet`, los dos
      grises, los tintes, 21 radios y las fuentes por instalación.
      ▶ **Salió barato por una medida**: el CSS ya estaba tokenizado, así que re-escopar siete tokens
      invierte **866 de los 1.915 usos de `var()`** sin tocar una regla; lo que no invertía eran
      **52 literales en 44 declaraciones**, en tres familias con rol distinto.
      ▶ **La promesa era no mover un píxel y se verificó regla a regla** en navegador, con control
      negativo. Guardas nuevas vistas morder: **13 mutaciones, las 13 muerden**.
      ⚠️ **Tres cosas que no se ven leyendo el CSS**: la paleta de tinta se declara en `:root` o es un
      **CICLO** (y un ciclo no falla, deja el color en el inicial); los dos grises son **aritmética**
      —el gris único daba 3,28 sobre tinta y ninguno pasa en las dos superficies—; y la tipografía
      eran **dos mitades que no se hablaban** (el token se redefinía y el fichero no se descargaba).
      ⚠️ **[CADUCADO — desde `#194` SÍ lo usa alguien]** Esta línea decía «el mecanismo aún no lo usa
      nadie». El `.hero__stage` declara `data-surface="ink"` y es su primer consumidor real; ahí se
      vio funcionar por primera vez, y ahí se cazaron tres conversiones a medias (spec §11.4).
- ✅ **2a · La FORMA y el pie** (2026-08-27, `#193`). La **escala de canto cerrada** (`--r-xs: 5px` y
      `--r-md: 10px` nuevos → `5·8·10·14·16·28·999`), la **ley del motivo cuadrado**, el **anillo de
      foco tokenizado** y la **tira de marca del pie**.
      ▶ **Los dos escalones no son de gusto**: salen de minimizar el movimiento sobre los 23 cantos en
      literal. Con la escala anterior se movían **23 de 23 y 53 px**; con éstos, **11 y 20 px**. Un
      tercer escalón bajaba a 6 y 10 y **se descartó a propósito** — con saltos de 2 px deja de ser una
      escala y pasa a ser un continuo, y una escala que no disciplina es una que el siguiente rodea.
      ▶ **Los 56 «huérfanos» eran DOS leyes mezcladas.** 16 siguen `radio ≈ lado / 4` (mediana EXACTA
      4,00): es la forma del bloque de espuma a cada tamaño, no deuda. Meterlos en la escala habría
      roto una familia proporcional. Literales: **95 → 70**; huérfanos: **56 → 31**.
      ▶ **El anillo de foco** cierra en nuestro código el `M-01` de la auditoría del cliente (Alta).
      El `outline-offset` NO se tokeniza: es encaje de cada componente.
      ⚠️ **Dos instrumentos propios salieron rotos, y las dos veces parecía lo contrario.** El
      clasificador de sombras usaba `(-?\d+)px`, que **no ve un `0` sin unidad** —justo donde acaba
      una sombra dura—; y el arnés de mutación dio **«0 de 12 muerden»** con el test perfecto, porque
      decidía con `grep -q "A\|B"` y aquí `grep` es **ugrep en ERE** (`\|` = pipe literal). Con ambos
      arreglados: **13 de 13 muerden**. ▶ **Cuando un instrumento dice que nada funciona, la primera
      hipótesis es el instrumento.**
      ⚠️ **La tira CICLA, no interpola**, y eso se decidió midiendo: con dos colores casi
      complementarios la franja del medio salía barro (`#868D7D`), y **`oklab` no lo arreglaba**
      (croma 0,024 vs 0,025 de `srgb`). El producto no elige la marca de su cliente.
      ❗ **Falta la pasada de NAVEGADOR del owner** sobre las 11 declaraciones que se mueven.
- ✅ **2b · El HERO, pasos 1 y 2** (2026-08-27, `#194`). ▶ **El owner lo verificó en navegador**: «el hero está como estaba antes» — que era exactamente la promesa de estos dos pasos.
      ✅ **Paso 1**: 17 reglas de **6 clases MUERTAS** fuera (cero usos en `resources/`), 56 líneas.
      ✅ **Paso 2**: el `.hero__stage` **declara `data-surface="ink"`** — la PRIMERA vez que el
      producto consume el mecanismo de la tanda 1, que hasta hoy no usaba nadie— y `--onvideo` deja
      de pintar color: de sus 10 reglas restantes, **ninguna declara uno**.
      ▶ **`--onvideo` eran TRES cosas con un nombre**: superficie (se la lleva el ámbito), tamaño
      (el titular del hero es mayor que un `h1`; se queda) y sombra sobre oscuro (se queda).
      ▶ Verificado por **aritmética**, no por vista: **14/14 declaraciones rinden idéntico**, dentro
      y fuera del hero. Y tres van **sobre el BOTÓN**, que invierte respecto a la superficie: leen
      los alias `--paper-*`, primer uso real de lo que la tanda 1 creó «para volver a papel».
      ⚠️⚠️ **La conversión estaba a MEDIAS en tres sitios y la guarda nació CIEGA.** El scrim tiene
      CUATRO paradas y se convirtieron dos —la mitad inferior quedaba en crema—; `.hero__stage-content`
      seguía forzando texto oscuro; el telón del placeholder, crema. Y la guarda escrita para cazarlo
      **ciclaba** al resolver los tokens, devolvía `null` y **pasaba sin mirar nada**: dio verde ante
      las mutaciones que reproducían el fallo que la motivó. ▶ **Regla que sale de ahí: una guarda no
      se sabe si sirve hasta que se muta con el fallo REAL.** 4 mutaciones, 3 son reproducciones.
      ❗❗ **Paso 3 PARADO, y no es de pintura**: la coreografía pone el stage en `sticky`, y ahí vive
      `.hero__stage-bottom`, que es el **SENTINEL de los dos CTAs de compra** (nav en escritorio y
      barra flotante en móvil). Dentro de un sticky no abandona el viewport, así que **cambiaría el
      momento en que se le ofrece comprar al visitante**. Tres opciones con su coste en la spec
      **§11.5**; recomendada la **B**. Con el paso 3 entran la FORMA y **la escala de sombra**.
      ⚠️ **Y la auditoría del cliente EXCLUYE el hero por indicación suya** («organización y efectos
      del hero de cabecera… quedan fuera»): sus COLORES sí están revisados, su forma y su
      coreografía **no**. El paso 2 es terreno firme; el 3 es el único de toda la capa que copia
      algo no normativo.
      ❗ **Nadie ha MIRADO el hero**: esta máquina no tiene navegador headless.
- 🟦 **2b · paso 3 — el hero adopta la ESTRUCTURA del mockup** (2026-08-27, `#195`). ❗ **Sigue 🟦 y no ✅ a propósito**: cambia el aspecto de la primera pantalla y **nadie lo ha MIRADO** — falta la cuarta condición del DoD, la verificación empírica.
      `[DECIDIDO owner]`: «en el mockup el CTA sale DESPUÉS del hero; en el hero no hay CTA, solo
      texto y el vídeo». El hero queda **eslogan → titular → estado**, es una **tarjeta** con
      margen y radio (no un sangrado) y **encoge al bajar**. Comprar se ofrece en el nav y en la
      barra de móvil, con su anclaje «desde X €» intacto.
      ❗ **La duda del sentinel que paraba este paso se resolvió con la propia decisión**: sin CTA
      en el hero, `.hero__stage-bottom` —el elemento que dos comportamientos de COMPRA observaban—
      desaparece. Ahora hay un `.hero__sentinel` propio, vacío y fuera del `sticky`. ▶ Que aquello
      funcionara era una **coincidencia**, y una coincidencia sostiene hasta que algo se mueve.
      ▶ **El JS publica UNA custom property (`--hero-p`) y no decide nada de diseño**: los dos
      estados los define el CSS con `calc()`. El mockup lo hace al revés (estilos inline desde JS)
      y copiarlo habría dejado los números del efecto en el único sitio que un cliente no puede
      tocar. En móvil **solo cambian cuatro tokens**, ni una regla.
      ▶ Robustez: `prefers-reduced-motion` no monta el componente **y** el CSS pone el recorrido a
      0 (si no, quedaría una pantalla de scroll vacío); scroll `passive` en un `rAF`; no escribe si
      el valor no cambia; `100svh` tras `100vh`.
      ⚠️⚠️ **Lo que costó: el test re-apuntado NO fijaba nada.** Se auditó por sujeto
      (`CONVENCIONES §3.quater`) y se re-apuntó a la barra de móvil… pero `assertSee('cta-prime')`
      casaba con `cta-prime__ico` y `assertSeeText('desde X')` lo satisfacía **el CTA del NAV**,
      que dice el mismo texto con otra clave. Dos mutaciones pasaban. Acotado al botón real,
      **4 de 4 muerden**.
      ❗ **Falta la pasada de NAVEGADOR**, y aquí pesa: este paso SÍ cambia la primera pantalla.
- 🟦 **2b.bis · La ELEVACIÓN: TRES ROLES, no una escala** (2026-08-27, `#196`). ❗ **Sigue 🟦 y no ✅**: **19 elementos pierden su sombra** y eso cambia media web sin que nadie lo haya visto.
      ⚠️⚠️ **Corrige la medición de §10.3**, que miró solo el difuminado. Con las cuatro dimensiones
      de una sombra, el producto tenía **53 vivas y 42 formas distintas**, y la mejor escala de
      cinco escalones movía **47 de 53** con grupos deformes (22·14·10·6·**1**). No era una escala
      con ruido: **no había ninguna**.
      ❗ Y al ir a copiar el número de escalones del cliente apareció que **él tampoco tiene**:
      declara DOS formas. Con 42 de un lado y 2 del otro, la pregunta era **«¿para qué sirve cada
      sombra?»**, y salen tres: `--shadow-lift` (17) · `--shadow-float` (8) · `--shadow-modal` (3).
      Y **19 pierden la sombra**: una tarjeta quieta no está elevada, está apoyada.
      ▶ **De 53 formas propias a 6**, todas justificadas: tres DIRECCIONALES, la tarjeta de
      invitación (artefacto imprimible), el pulgar de un interruptor y el badge del widget.
      ⚠️ Leen **`--paper-fg`**, no `--fg`: dentro del hero `--fg` vale CLARO y la sombra se volvería
      clara — el mismo defecto que `#194` cazó tres veces. Guarda propia, mutación que muerde.
      ❗ **Falta el navegador**: 19 elementos pierden su sombra y eso cambia media web. `[DECIDIDO owner]`: el producto adopta la **ESTRUCTURA** del mockup, **neutra en
      valores**. **Aquí el producto SÍ cambia de aspecto de verdad**: su red es el ojo en navegador.
      ▶ La sombra entra aquí porque **no hay escala extraíble sin coste**: probadas de 3 a 6
      escalones, la mejor mueve **35 de 55 y 125 px de blur**. Crearla es decidirla.
      ▶ Y aquí el hero declara `[data-surface="ink"]`, que es **la primera vez que el mecanismo de la
      tanda 1 se usa de verdad** — y el día que las tres excepciones del anillo de foco se retiran.
- 🟦 **2c · El ARMAZÓN → spec propia `specs/armazon-y-menu.md`** (2026-08-27, `#200` → `#205`).
      **Completo salvo el menú en MÓVIL**, que espera el artboard del owner. Siete decisiones suyas,
      dos de ellas corrigiendo a la propia spec.
      · **2c·0** (`#200`) la RED —el cajón móvil **estrenó test**: no lo tocaba ninguno— y 33 reglas
        muertas fuera. **Cero píxeles, medido** en el HTML de 16 páginas.
      · **2c·1** (`#201`) el **menú a pantalla completa** sustituye a los dos desplegables; 33 reglas
        más fuera. ⚠️ Lo único del mockup que NO se copia es cómo oculta su menú: `clip-path` sin
        `visibility` deja los enlaces en el orden de tabulación.
      · **2c·2** (`#203`) la barra se **DISUELVE** en dos racimos y el salto al contenido pasa de
        **1 a 12** vistas. ⚠️ `pointer-events:none` en el contenedor y `auto` en los racimos: sin eso
        la franja vacía se traga los clics de todo el ancho.
      · **2c·3** (`#204`) la cuenta en icono con punto de aviso en amarillo, y tres glifos al set.
      · **2c·4a** (`#205`) el **CTA DOBLE** de móvil; el idioma sale del pie con `<noscript>` de suelo.
      · **2c·5** (`#211`) ❗❗ **el menú no se podía CERRAR con el ratón** —la hamburguesa solo abría—
        y **abrirlo desde la portada dejaba la pantalla sin ningún botón de comprar** (el CTA nace
        oculto). Las dos las cazó el OJO del owner: **ninguna de las 31 aserciones las veía, porque
        todas comprobaban que el menú se ABRE.** ▶ Medido: el menú del mockup **tampoco lleva CTA
        propio** — usa el de la cabecera, que en el suyo está siempre visible. No faltaba un botón:
        faltaba REVELAR el que ya hay.
      · **2c·6** (`#213`) el CTA **cambia de ROL dentro del menú**: tinta fuera, **AVISO** dentro,
        como su mockup. ▶ Por eso **`.cta-med` SALE del rol de acción** de `#209` (13 reglas → 12):
        su color no lo manda un rol sino una coreografía. ⚠️⚠️ Y **su anillo de foco es el MISMO
        amarillo**: sin cambiarlo, el foco de teclado sobre ese botón queda **invisible**.
      · **2c·7** (`#214`) el racimo es un **PAR**: uno ancho, el otro reducido a su icono; **1.er
        clic expande, 2.º actúa**, y mientras nadie lo toca la mitad colapsada **asoma** con un
        **aro** que late. ▶ El estado sube a `$store.ctaPair`, **compartido con la barra de móvil**:
        son la misma decisión. ⚠️ La rama **con sesión era la ÚNICA del par sin suelo sin JS**.
      ❗ **Falta la pasada de NAVEGADOR de las OCHO tandas visuales**, y el owner eligió mirarlas
      juntas sabiendo el coste de la atribución.
      ❗ **Y lo único que queda de la 2c es la 2c·4b, el menú en MÓVIL** — que **ya no la bloquea el
      artboard**: llegó el 2026-08-28 dentro de `Landing PJP Modos`. ⚠️ Su barra inferior son **3
      iconos + 1 CTA** y la nuestra es el **CTA doble** que el owner validó el día antes: eso hay
      que preguntárselo antes de rehacerla.
- 🟦 **El PRIMER PAQUETE DE CLIENTE REAL** (2026-08-28, `#206`). ✅ **Demuestra que los cuatro
      mecanismos COMPONEN**: la web sale con la marca del 2.º cliente y la suite entera pasa, con
      cero migraciones y cero dominio. **El paquete NO está en el repo y no puede estarlo**: vive en
      `public/css/client.css` (gitignorado, excluido del `rsync`), dos ajustes en el panel y
      `THEME_FONTS` en el `.env`.
      ❗❗ **Y destapó lo que ninguna guarda podía dar: el anillo de foco del cliente es INVISIBLE en
      papel** —1,49 frente al 3,0 que exige WCAG—, y **su auditoría de 20 pares no incluía ese par**.
      `[DECIDIDO owner]`: un color por superficie, y **no costó código de producto** — su
      `--focus-color` ya valía `var(--fg)` y era el paquete el que lo rompía.
      ❗❗ **Tres defectos del producto que solo aparecen con una instalación encima**: una guarda
      exigía que la hoja del cliente NO existiera; **el primer arreglo lo empeoró** —saltar movía el
      contador de aserciones y el `pre-push` bloqueaba a una de las dos máquinas SIEMPRE—; y las
      cinco guardas de CSS juzgaban `client.css`, que **está hecho de literales**. Arreglado de
      fondo y verificado midiendo con paquete y sin él: **idéntico**.
      ▶ Y entra el hueco del **LOGOTIPO de instalación**, con las mismas tres piezas.
- 🟦 **El QUINTO MECANISMO — el relleno de ACCIÓN** (2026-08-28, `#209`, spec **§15**). ✅ **Lo
      único que impedía que la landing fuese 1:1 con el mockup en color, cerrado.** El botón que
      hace avanzar la compra deja de estar atado a `var(--fg)` y pasa a ser un **ROL** con cuatro
      tokens (`--action`, `--on-action`, `--action-hover`, `--on-action-hover`), configurable desde
      el panel con **un solo dato** (`theme.action`).
      ▶ **La pregunta fue «¿para qué sirve cada relleno de tinta?»**, no «¿qué botones son oscuros?»
      —la receta que ya funcionó en `#193` y `#196`—. Medido con **dos instrumentos independientes
      que coincidieron**: **52 reglas** rellenan con `var(--fg)` sólido y solo **13 son acción**;
      las otras 39 (superficie invertida, hover que invierte, estado seleccionado, decoración) se
      quedan en tinta **a propósito**, porque el propio sistema del cliente dice «en claro el
      secundario es TINTA».
      ❗ **La decisión que merece defenderse**: los tokens llevan `var(--action-brand, …)` y se
      declaran en `:root` **y en las dos superficies**. Con eso el rol tiene DOS conductas desde una
      sola línea — sin color de acción **sigue a la superficie** (el CTA del nav se invierte al abrir
      el menú, que si no sería oscuro sobre oscuro) y con él es **idéntico en los dos fondos**, que es
      lo que exige el sistema del cliente.
      ❗ **El hover se DERIVA ×0,88, y el número se midió antes**: es el factor exacto que lleva el
      `#F2711C` del cliente a su `#D56319` en los tres canales, **y el mismo que el `--err-hover` que
      el producto ya tenía**.
      ⚠️⚠️ **Lo que costó, y lo dijo la guarda con el color del propio cliente**: reutilizar
      `onBrand()` daba **3,73** sobre `#D56319` —falla AA— porque su umbral es **3,0**, el de texto
      GRANDE, y un rótulo de botón no lo es. `onAction()` elige el de más contraste: 4,99. Y quedó
      medido que **con luminancia ≈ 0,19 no existe texto que pase AA** (empate en 4,31).
      **11 mutaciones, las 11 muerden · sonda de navegador 12/12.** ❗ Sigue 🟦 por el ojo del owner.
- [ ] **2d · El MOVIMIENTO** — 4 curvas y 7 duraciones como tokens. Medido: **237 declaraciones, 48
      duraciones distintas y 20 curvas** (el sistema del cliente declara 7 y 4); `200ms` sola tiene
      110 usos. No mueve píxeles, mueve TIEMPO: **no se revisa con una captura, se revisa
      interactuando**.
- [x] **2e · El INTERRUPTOR del titular y el LOGOTIPO** (`#262`, `#263`, 2026-08-29). El
      interruptor deja de ser dibujo propio y pasa a ser el **`6d` del LOTE 6** del canvas, con su
      rótulo dentro y su bucle: geometría 1:1 por construcción (sus diez medidas son múltiplos
      exactos de 1/16), duración propia como token AMBIENTAL y colores por ROL. Y el logotipo, que
      **no saltaba en ONCE de las doce vistas** porque su regla exigía una clase que solo pone el
      hero. ❗ Sigue pendiente el ojo del owner y **el color de la pista** (`--ok` verde contra el
      Lima Bote del artboard).
- [x] **2f · El OBJETIVO TÁCTIL de 44 en la landing** (`#264`, 2026-08-29, `[DECIDIDO owner]`). Lo
      que `#259` §6 dejó medido y sin tocar. **37 → 1** control bajo 44 en las siete vistas públicas
      (el que queda es el enlace EN LÍNEA del texto de cookies, exento por WCAG).
      ▶ **Dos decisiones del owner**: el objetivo crece **al dedo y no a la vista** donde el dibujo
      está 1:1 con el mockup (`[data-tap]`, un pseudo centrado bajo `(pointer: coarse)`), y **el
      bloque legal del pie pasa a TIRA que se desliza** —el patrón de `#252`—, con lo que el pie
      **encoge 30 px** en teléfono y queda **idéntico** en escritorio.
      ⚠️⚠️ **El mecanismo YA EXISTÍA desde el «Lote 9» y ENCOGÍA**: su `width: var(--tap-min)` a
      secas recorta el área de cualquier control que ya midiera más de 44, sin que nada falle. Lo
      cazó la guarda de unicidad del token, no la memoria. Corregido al `max()`.
      ⚠️⚠️ **La sonda mintió DOS veces con números creíbles**: recortando en coordenadas de viewport
      (áreas **negativas**) y sin aplicar el `transform` del pseudo (altos de 58 donde son 44).
      **9 casos · 8 mutaciones, las 8 muerden.** ❗ Sigue pendiente el ojo del owner.
- [x] **2g · El CTA flotante y la FÍSICA del salto del logotipo** (`#265`, 2026-08-29,
      `[DECIDIDO owner]`). El CTA de móvil pasa de **48 a 56** (token propio, `--book-bar-h`) y
      **pierde una sombra que era el rol equivocado** —`--shadow-float`, que con este paquete vale
      `5px 5px 0`, pisaba la que el componente ya traía—. Y el salto del logotipo recupera **las
      siete curvas por tramo**, el **asentamiento** del lockup y el **tempo** (`v = 0.9`): medido
      contra la fórmula del mockup, **0,000 px en 21 muestras**.
      ⚠️⚠️ **Los ocho fotogramas ya eran los suyos**: lo que faltaba era la gravedad entre ellos. Es
      `#262` por el otro lado — *hay que saber si el movimiento vive en los fotogramas o en la curva
      antes de tocar ninguno*.
      ⚠️⚠️ **Y el `fill: both` del asentamiento MATABA el hover del logotipo**, sin fallar nada.
      ❗ **Queda del owner**: el **relevo de la Y** necesita que exporte el logo con esa pieza
      (`INSTALACION-CLIENTE.md` §4.a.sexies). Eso **corrige a `#263`**, que lo declaró imposible.
- [x] **2h · El salto del logotipo, que NUNCA se había visto** (`#266`, 2026-08-29). Salido de una
      **revisión adversarial de `#265` antes de empujarlo**. La animación caía sobre `#fig`, que vive
      en `<defs>` y **no se pinta**: `#254` la introdujo, `#263` la dio por arreglada en once vistas
      y `#265` le puso la física, y **el dibujo no se movió ni un píxel** en las tres.
      ⚠️⚠️ **Lo zanjó un CONTROL**: `style` en línea repinta 773 px, la misma transformación por
      `@keyframes`, cero. *Que una animación exista y compute no es que el dibujo se mueva* — es la
      lección de `#263` un nivel más abajo, y **un cero sin control no distingue «no se mueve» de
      «no lo estoy mirando bien»**.
      ❗ Y la **amplitud estaba 7,5× corta**: dentro de un SVG los `px` son unidades del `viewBox`.
      Ahora va en **% de la figura**, que es más white-label que el mockup.
      ⚠️ El «0,000 px» de `#265` era **adimensional**, y estaba escrito en seis sitios.
      **+2 casos · 7 mutaciones, las 7 muerden.**
- [x] **2i · La SOMBRA del logotipo, medida contra el mockup** (`#267`, 2026-08-29). Tercera vuelta
      del owner sobre lo mismo. `#253` y `#263` ajustaron nuestro filtro y lo compararon **consigo
      mismo**; el original no entró en ninguna de las dos. Con su lockup delante —lo entregó en
      HTML— se midió la densidad de sombra: **su lockup 1.193.218 · nuestro SVG con SU filtro
      1.252.968 · lo que teníamos 645.997**. La nuestra era **la mitad**, y el razonamiento de
      `#253` («el sujeto es otro») queda refutado: el mismo filtro sobre nuestro sujeto da su
      sombra, con un 5 % de diferencia.
      ⚠️ *Cuando el owner dice tres veces que algo se ve distinto, lo que falta no es otro ajuste:
      es la comparación que nadie ha hecho.*
      ⚠️ De paso, la geometría del asset resultó estar a **2-3 %** de su mockup: lo que se veía
      distinto era la sombra, no el dibujo. Guarda con mutación.
- [x] **2j · El logotipo SIN sombra CSS** (`#273`, 2026-08-30, `[DECIDIDO owner]`). Cuarta vuelta,
      y la que cierra. El SVG ya trae **26 pasos** de extrusión horneada; el lockup del mockup, que
      es texto vivo, sólo **6** — por eso él necesita filtro y nosotros no.
      ⚠️⚠️ **La medición de `#267` era correcta y la conclusión no**: densidad y halo daban 4-5 % de
      diferencia, pero *una métrica agregada puede decir «equivalente» sobre dos cosas que el ojo
      separa al instante*. Eso **devuelve la razón a `#253`**, que había diagnosticado bien y
      corregido a medias.
      ❗ **La lección de método**: a la cuarta vuelta la respuesta no era otra medición, era enseñar
      opciones y dejar elegir. Guarda con control positivo y mutación.
- [x] **2k · El CONTORNO del logotipo y el HERO de móvil** (`#274`, 2026-08-30, `[DECIDIDO owner]`).
      Los dos son lo mismo: **un número nuestro puesto encima de uno suyo**.
      ⚠️⚠️ **La mitad del contorno quedó REVERTIDA por `#275`**: el 65 % no era el defecto —las
      bandas del export ya eran las del mockup al dígito— y el factor **creó** un anillo marino por
      fuera del cian. La aritmética de esta tanda tenía razón; lo que faltaba era medir el MECANISMO.
      El diagnóstico del hero, en cambio, sigue bueno.
      ▶ El hero de móvil llevaba un `min(72vh, **520px**)` que **no sale del mockup** (el suyo es
      una sola fórmula, `Math.min(vh * 0.78, 660)`). Ese tope mordía por encima de 722 px de
      ventana: **252 px vacíos a 390×844 y 340 a 390×932**. Retirado → 114 y 200.
      ⚠️ `--hero-h-end` era además la única de las tres expresiones de ventana del hero **sin el par
      `vh` → `svh`**. **+1 caso · 2 mutaciones, las 2 muerden.**
- [x] **2l · EL LOGOTIPO, IDÉNTICO** (`#275`, 2026-08-30, `[DECIDIDO owner]`: «lo quiero IDÉNTICO»).
      Quinta sesión sobre el mismo dibujo y la que la cierra: **los tres defectos estaban en la
      EXPORTACIÓN del lockup a SVG**, no en nuestro CSS — que es donde las cuatro tandas anteriores
      buscaron.
      ▶ **`text-shadow` NO arrastra el `-webkit-text-stroke`** (control: el mismo glifo con y sin
      trazo da la MISMA sombra, 14,13 px): la extrusión salía 3,25 px más gorda por lado. Faldón azul
      **7,1 px contra sus 4,0**, y en **1121 de 1121** columnas contra 852 de 1137. Ahora 4,13.
      ▶ **El velo de dentro**, α **0,377 contra 0,112** y frío bajo las dos palabras: la caja de
      línea no es la caja de tinta, y el lockup usa **un velo por palabra**. Corregido: **6 de 8
      muestras idénticas**.
      ▶ **La silueta venía restada de las letras**: a la «A» le faltaba el **16,8 %**, invisible en
      reposo y a la vista durante toda la animación. Reconstruida desde Lilita One con la afín
      recuperada del propio trazado (control 0,001 %; la L, independiente, a 0,52 % de área).
      ⚠️⚠️ Esa geometría vive en **DOS** sitios y arreglar uno la deja **BLANCA**.
      ▶ Guiones `logo-sombra.php` y `logo-letra-a.php`, idempotentes; `logo-contorno.php` retirado.
      **+10 casos · 6 mutaciones, las 6 muerden** (y una séptima **no**, por débil: el guion tiene dos
      capas de defensa). ⚠️ **Falta el OJO del owner y subir el asset a staging.**
- [x] **2m · El eslogan pegado al titular y el CTA de móvil** (`#276`, 2026-08-30, `[DECIDIDO owner]`).
      Dos ajustes pequeños sobre NUESTRA landing, y los dos destapan un número escrito a mano.
      ▶ El eslogan comparte caja con el titular (`.hero__headline`) para pegarse a su filo izquierdo,
      y **`margin: 0` no deja el hueco en cero**: cada texto paga su parte en su PROPIO `em` porque
      los dos cuerpos escalan distinto. Tope por CHOQUE, barrido en siete ventanas: **−0,18 em**,
      mínimo 0,2 px, cero columnas solapadas.
      ▶ El CTA de móvil sube 9 px y **de él colgaban dos literales** —el `92px` del lanzador de
      ofertas y el `84px` de la reserva del hero—: ahora los dos derivan de `--book-bar-block`.
      **+1 caso · 4 mutaciones, las 4 muerden** (una guarda nació LAXA por un `.*?` perezoso).
- [x] **2n · Las MICROANIMACIONES del cliente, valoradas · U2 y U3** (`#277`, 2026-08-30).
      El owner entrega `Microanimaciones PJP` y pide valorarlo antes de implementar. **El vocabulario
      ya estaba**: las 4 curvas y las 7 duraciones son idénticas (`#222` tomó este mismo artboard).
      ▶ **U2**: su norma mantiene el fundido con movimiento reducido y nosotros hacíamos
      `transition: none` — el rótulo del CTA aparecía de golpe y el bloque de cuenta perdía su
      `visibility` diferido. ⚠️ Un segundo bloque 200 líneas más abajo lo volvía a matar.
      ▶ **U3 · la cascada de franjas** con sus números (32 px, LONA, 420 ms, 1,12/0,76, desfase 90),
      verificada en el cajón real. ⚠️⚠️ Destapó que **`sellable` estaba en el contrato y nadie lo
      leía**: las franjas llenas se pintaban clicables. El PANEL sí lo respeta.
      **+1 caso PHP · +2 JS · 4 mutaciones, las 4 muerden.**
      ⏸️ Quedan en `DEUDA.md`: los 22 bucles (contradicen su propio `6d`), el hover pegatina (toca
      `#209`) y el sello + check.
- [x] **2ñ · El DESENLACE: dos piezas y sin confeti** (`#278`, 2026-08-30, `[DECIDIDO owner]`).
      El confeti ya marcaba lo mismo que la pegatina de éxito, y con el sello serían tres donde el
      techo del artboard son dos. Queda la pegatina entrando y **el código de la reserva SELLADO**,
      secuenciados. ⚠️ El sello va NEUTRO —desviación decidida: su artboard lo estampa en amarillo
      porque allí es la única pieza—.
      ⚠️⚠️ Retirar el confeti habría dejado **ciega una guarda ajena** que lo usaba de delimitador, y
      destapó que la hora COMPLETA de `#277` **no la cubría nadie** (ninguna fixture tenía una franja
      llena). **+2 casos · 4 mutaciones, las 4 muerden.**
- [x] **2o · El INTERRUPTOR del titular PARA, y para ENCENDIDO** (`#280`, 2026-08-30,
      `[DECIDIDO owner]`). Lo que `#279` dejó decidido y sin hacer: tres ciclos y a reposo, y vuelve
      a saltar cuando el hero regresa al viewport.
      ⚠️⚠️ **Medido antes de tocar nada**: acotando iteraciones sobre el CSS de entonces la pieza no
      quedaba «apagada», quedaba **apagada con la palabra ON al 100 % encima** —el rótulo no tenía
      `opacity` propia—. El estado encendido entero vivía **solo dentro del bloque de movimiento
      reducido**: el reposo de la pieza escrito como una concesión de accesibilidad.
      ▶ Dos mitades: **el reposo sube a la regla base** y **el corte cae DENTRO del tramo encendido**
      (`calc(var(--switch-cycles) - 1 + 0.6)`), donde el valor es constante y coincide con el reposo
      — así el `fill: none` no da respingo. Medido: **0 px de salto** en las tres piezas.
      ⚠️⚠️ El rearranque **no puede ser WAAPI**: una animación terminada con `fill: none` desaparece
      de `getAnimations()` (medido, 1 → 0). Se descarta y se recrea desde `ui/hero-switch.js`, que
      **no decide nada de diseño**.
      ▶ **Presupuesto de `#279`**: `/` pasa de **5 a 2** bucles —cumple el techo de su artboard— y
      `/entradas` de **6 a 4**. ⚠️ Y corrige a `#279`: el interruptor eran **3** bucles, no 2 —contar
      en un instante subestima una pieza cuyo ciclo apaga una de sus partes—.
      **+5 casos PHP · +13 JS · 14 mutaciones, las 14 muerden** (⚠️ la guarda nació ROJA con el
      producto sano: un `[^{]*` se tragaba las listas de selectores separadas por comas).
      ⏸️ Falta el **OJO del owner**.
- [x] **2p · «Elementos Fachada» VALORADO, y el kit acotado a 32 de 40** (`#281`, 2026-08-30,
      `[DECIDIDO owner]`). El owner entrega el kit de material gráfico del mural y pide valorarlo
      antes de implementar — método de `#277`. Spec: `specs/elementos-fachada.md`. **No se implementa
      nada.**
      ▶ **El vocabulario de base YA ESTABA**: la trama A1 es `.menu__grain` **al dígito** (1,4 · 1,6 ·
      20×20), la tira C2 coincide **en orden 5 de 5** con `--strip-1..5`, la sombra dura de E1 **es**
      `--shadow-float` y las cuatro tipografías son los cuatro tokens. Lo nuevo es el REPERTORIO.
      ⚠️⚠️ **La copia local del canvas está CADUCADA** (20 piezas contra 40) **y el fichero pegado
      llega con la codificación rota** mientras la local está en UTF-8 sano: *el daño lo trae el
      pegado, no el canvas*. Se subió dos veces y las dos igual. `DesignSync` sigue sin autorización.
      ▶ **Tres decisiones del owner**: se construye el **hueco de ilustración por instalación** —que
      cierra la deuda de `#257`— · el **grupo D se retira entero y sin excepciones** · y `D4`/`D6`,
      los dos únicos sin gemelo en F/G, **se van también**.
      ⚠️⚠️ **Coste declarado**: `D6` era **la única pieza del kit que contaba un movimiento**, así que
      **de este artboard no sale ninguna animación**; el movimiento lo sigue decidiendo `#277`.
      ⚠️ **Y una medición MÍA salió falsa**: dije que `D4` apuntaba a un `saltador.png` inexistente y
      **existe (98.508 B)** — un `head` truncó el listado a diez líneas. *Un listado truncado no dice
      que algo no exista: dice que no lo has visto.*
- [ ] **3 · Las secciones**, pieza a pieza. ⏸️ **FUERA DE ALCANCE hasta que el owner lo diga**
      (`[DECIDIDO owner, 2026-08-28]`: del canvas solo se toma el sistema de diseño —colores,
      elementos, iconos, formas, menú, hero y pie—; el resto «son pruebas»).
      ⚠️ El owner subió **tres artboards** de la misma sección, no dos: `Descubre-el-Parque`,
      `Recorrido-Parque` y —el 28— `Elige tu Zona`, una maqueta isométrica en SVG de todo el parque.
      Las **tres** son «02 · El parque» y **cuál se queda sigue SIN DECIDIR** (preguntado el 28:
      «todavía no lo decido»). `Elementos Fachada` sigue **sin migrar** —de ahí
      se saca la FORMA, nunca el color: medido, **cero** de sus nueve literales coinciden con
      `client.css`— y **ya está VALORADO** en `specs/elementos-fachada.md` (`#281`): el vocabulario
      de base ya estaba, lo nuevo es el REPERTORIO, y lo que decide si entra o no es **el hueco de
      ilustración por instalación** que `#257` dejó anotado. ⏸️ Pendiente del owner.

## Relación con el proyecto origen
El cliente origen (jumpingjump) sigue vivo en **su** repo con su canal de deploy; este repo no
le despliega nada. Mejoras de JumpWeb aplicables allí se portan **solo por decisión explícita**,
como cambios independientes en aquel repo (ver `DECISIONES #1`).
