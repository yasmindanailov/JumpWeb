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

### Fase 4 — Sidebar SPA 🟦 — de 4.0a a 4.6, HECHOS: **los ONCE pasos transcritos** con Vue 3 + Pinia, y el extremo a extremo con navegador y pasarela REAL ya realizado (`#59`, que destapó que el motor no vendía y se arregló). Queda **4.7**, la retirada de `Purchase.php`, EN CURSO —hechos el manifiesto congelado (·1), el inventario (·2a), la corrección del contador (·2b·1) y **la migración (B)**, con la que el diff de árbol se alimenta del servidor (`#67`–`#73`); en curso el re-apunte (·2b·2), pendientes el borrado (·2b·3) y el flag (·3)— y **4.4b·2**, el widget de Turnstile, bloqueado en claves de Cloudflare. ⚠️ El flag sigue en `livewire`: **la paridad está cerrada, la sustitución no**
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
      ningún endpoint publicaba. El desglose de señal es **por RESERVA y no por pedido** (#225 F3:
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
        es señal (#225). El normalizador descarta el texto: lo fija `SidebarCartParityTest`.
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
- [ ] **Paso 4.7·2b·2 — re-apuntar lo que SOBREVIVE** (EN CURSO; 2026-08-15, `DECISIONES #65`): los
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

- [ ] **Paso 4.7·2b·3 — borrar el componente, sus vistas y el puente** (pendiente): `Purchase.php`,
      `purchase.blade.php`, `purchase-placeholder.blade.php`, la línea de `layout.blade.php` y el puente
      `$wire.step`↔store, **en el mismo commit** que los tests que mueren con él —para que el motor por
      defecto no pase ni un día con menos red de la que tiene—.
      ⚠️ **La condición de «curtirlo en producción» se RETIRÓ el 2026-08-15 por vacía** (`DECISIONES
      #62`): no hay instalación viva ni canal de despliegue, así que no hay tráfico que esperar. Lo que
      de verdad ordena este tramo es el CONTADOR de `PurchaseRetirementTest`: reclasificar hasta 0 y
      borrar entonces, no antes.
      · **Medido en ·b1**: fuera de `tests/`, el único acoplamiento EJECUTABLE al componente es
        `resources/views/components/layout.blade.php` (`<livewire:tickets.purchase lazy />`). Todo lo
        demás que lo nombra en `app/` son docblocks que cuentan de dónde salió una regla.
      · ⚠️ **Y arrastra más de lo que su nombre dice, también medido en ·b1**: `purchase.blade.php` es
        el ÚNICO sitio que monta `<livewire:auth.login|register :embedded="true">`, así que el **modo
        `embedded` de los dos componentes de auth se queda sin usuario** — y con él el evento
        `purchase:switch-to-login` (`Register::requestSwitchToLogin` → `Purchase::onSwitchToLoginTab`),
        cuyo disparador ya hoy es inalcanzable: el escape vive en la pantalla `sent` del Register y el
        paso 5 deja de renderizarse en cuanto `registration-submitted` salta al 7. Decidir si el modo
        `embedded` se retira aquí o se deja para Fase 5 es parte de este tramo, no un descubrimiento
        para el final.
- [ ] **Paso 4.7·3 — retirar el flag** (pendiente): `SidebarSettings`, el ajuste y su fijación en el fixture.
- [x] **SPA embebida (Vue 3 + Pinia) para el cajón completo** — HECHO: los ONCE pasos (fecha/hora,
      cesta, login/registro, pago, vuelta y reintento) con Vue 3.5 y Pinia 3.0, y verificado de punta a
      punta con navegador y la pasarela REAL (`#59`). **Primer consumidor real de la API v1**, cumplido.
- [x] **Paridad funcional ANTES de retirarlo, con feature-flag por instalación** — HECHO: `sidebar.engine`
      (default `livewire`) permite comparar los dos motores en vivo, y la paridad son el diff de árbol
      contra manifiesto congelado (`#60`) más trece paridades de datos, textos e importes.
      ⚠️ **Que la paridad esté cerrada NO significa que el flag esté activado**: falta Turnstile
      (4.4b·2) y los tres caminos de navegador, ver `ESTADO.md`.

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
