# Estado del proyecto — foto viva

> Documento corto (carga obligatoria al arrancar). Solo «dónde estamos / qué sigue».
> Última actualización: **2026-08-12**.

## ▶ Dónde estamos
**Fase 0 ✅ · Fase 1 ✅ · Fase 2 ✅ CERRADA · Fase 3 (API v1) 🟦 — diseño v2 revisado y SIN bloqueantes; árbol saneado; listo para implementar el paso 0.**
- Suite **2186 en verde** (8186 aserciones, `--parallel` ~58s tras el saneado de dependencias) · Pint limpio · `docs-check`
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
  10. **PASO 7 — CIERRE de Fase 2** (`DECISIONES #20`, spec §4.octies): resueltas las 5 flechas
     `DEFERRED` con dos contratos de LECTURA extraídos de llamadas reales —
     `Booking\Contracts\OperatingCalendar` (+ DTOs `OperatingWindow`, `WeeklyOpening`,
     `SeasonWindow`, `SpecialDay`) y `ZonePalette`—.
     - **De regalo murieron dos reglas duplicadas**: Content reimplementaba «temporada vigente»
       y «ventana efectiva de una fecha especial» con comentarios que decían «para no divergir
       de lo que aplican las reservas». Misma trampa que la coherencia #226 del paso 1.
     - **Coste cero en consultas**: `OperatingSchedule` ya memoizaba esos datos; el contrato se
       compone sobre lo que había y retira las consultas que Content hacía aparte.
     - **`effectiveFor()` NO cambia de firma**: la consumen `SlotGenerator` y
       `ProductAvailability` (aforo, `AFORO-01`/`AFORO-03`). El contrato tipa la FRONTERA
       (`windowFor()`), no obliga a reescribir el núcleo.
     - **Criterio fijado** para la próxima flecha: *recibir* una entidad de otro módulo es
       costura de BD; *consultar* sus datos o *repetir* sus reglas exige contrato.
     - Baselines finales: `LEGACY`/`PENDING`/`DEFERRED` **vacías**; `SEAM` solo con costura
       documentada. Barrido `App\Support\`/`App\Models\` en código: **cero**.
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
**Fase 3 — API v1. El diseño está en `docs/specs/api-v1.md` (v2, 🟦), ya revisado.**

**Lo que pasó con la v1**: 3 revisores adversariales independientes la devolvieron con
**15 hallazgos GRAVE** (2 veredictos «insuficiente»). Tenía cuatro afirmaciones falsas y una
premisa errónea. La v2 los incorpora todos; **§8 del spec dice qué cambió y por qué** — léelo
antes que nada, porque varios hallazgos cambian el diseño, no el texto.

**Los tres que más cambian el trabajo:**
1. **Hay reglas de servidor FUERA del dominio**: la pausa de reservas (#218) y los topes
   anti-abuso (`MAX_PENDING_PER_USER`, `RESERVATIONS_PER_MINUTE`, que cierran el hallazgo E del
   origen: *agotar el aforo del día sin pagar*) viven en `Purchase.php`, no en `OrderCreator`.
   Exponer `POST /orders` sin extraerlas primero reabre las dos. Mismo patrón que
   `MAX_LINES_PER_CART` en Fase 2.
2. **La disponibilidad depende de la CESTA** (`SlotOffer::offerableTimes` descuenta tus propios
   ocupantes provisionales) → el endpoint la lleva, y el test de paridad debe usar cesta NO vacía:
   con cesta vacía pasaba por construcción.
3. **Falta el endpoint de presupuesto**: sin él el cliente no puede mostrar total ni señal sin
   crear un pedido que ya bloquea aforo.

**La fase va PARTIDA en 6 pasos** (spec §9), como se hizo con la modularización. El paso 0 son
cimientos sin negocio y cierra la instalación de dependencias.

**Decidido y listo (nada bloquea ya):**
- **Dependencias** (`DECISIONES #21`): Sanctum ^4.3 runtime + Spectator ^3.0 y `symfony/yaml` en
  dev. Scramble descartado (generar la doc desde el código invierte la relación de contrato).
- **Árbol saneado** (`DECISIONES #22`): los 26 avisos de seguridad → **0** (`composer audit` y
  `npm audit`), sin tocar restricciones ni añadir paquetes. Framework 13.25.0 · Filament 5.7.6 ·
  Livewire 4.4.0. Verificado con suite, Pint (sin churn), docs-check, los dos verificadores sobre
  MySQL, superficies y **generación real de PDF** con dompdf 3.1.6.
- **Anti-bot en cliente nativo** (`DECISIONES #23`): NO se relaja nada. El Turnstile del registro
  es `SEGURIDAD` regla 5 (no `SEC-06`) y es data-driven; el consumidor de Fase 3/4 es la SPA, que
  ES un navegador. La app nativa es Fase 6 y allí se decide, con las tres salidas ya escritas.

**Empieza por el PASO 0 del spec §9** (cimientos sin negocio): `api:` en `withRouting`, grupo de
middleware (§4.7, incluida la decisión escrita sobre el kill-switch de mantenimiento), Sanctum,
sobre de error, `GET me`, esqueleto OpenAPI + su test verificado por mutación, y **ampliar
`CRITICAL_RE` del pre-push** para que los controladores de checkout de API disparen `VERIFY_CONC`.

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
