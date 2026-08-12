# [SPEC] Modularización del dominio (Fase 2)

> Estado: ✅ aprobado (revisión multi-agente 2026-08-12: 4 inventariadores + 3 arquitecturas
> rivales + 3 revisores adversariales; 24 problemas de revisión incorporados aquí) ·
> Última actualización: 2026-08-12 · Decisión asociada: `DECISIONES #13`.

## 1. Contexto y problema
`app/Support` (57 ficheros) y `app/Models` (30) forman un namespace PLANO: las llamadas
cruzadas no aparecen como imports (estáticas/mismo namespace), así que las fronteras entre
contextos son invisibles e inejecutables. El inventario clasificado (4 pasadas sobre 278
clases): Support → Booking 21 · Content 12 · Payments 10 · Platform 9 · Identity 3;
modelos → Booking 15 · Content 6 · Identity 5 · Platform 3 · Payments 2; capa de entrega
(Http/Livewire/Console 65 + Filament 122) consume de varios contextos a la vez.
Acoplamientos mayores detectados: `Order` importa `Redsys` directamente y concentra la
contabilidad de reembolsos; `OrderCreator` importa la god-class de UI `Purchase` (por una
constante); el corte Booking↔Payments pasa por `Order`/`OrderAdjustment` y los traits
`OrderRefundFlags`/`HasItemActionGuards`.

## 2. Objetivo
Fronteras de módulo EJECUTABLES (un test las impone), movimientos 100% mecánicos con la
suite verde en cada paso, y el núcleo de dinero/aforo moviéndose EL ÚLTIMO contra contratos
ya estables. Fuera de alcance: romper `Purchase.php` (muere en Fase 4) y `ViewOrder.php`
(Fase 2 solo documenta sus costuras); mover la capa de entrega.

## 3. Opciones consideradas
- **Núcleo primero** (cortar Catalog&Booking ya): DESCARTADA — la revisión midió churn
  infraestimado (+50–220% en sus pasos) y mueve el núcleo protegido sin contratos previos.
- **`app/Modules/` vs `app/Domain/`**: equivalentes técnicamente; se elige `app/Domain/`.
- **Elegida: síntesis** «mínimo riesgo» + «contratos en destino» (las dos con veredicto
  sólida-con-cambios; cambios incorporados en §6).

## 4. Diseño elegido
**Layout**: `app/Domain/<Contexto>/{Contracts,Models,Services,Console,Exceptions}` bajo el
PSR-4 existente (`App\ → app/`; composer.json no se toca; Filament v5 y Livewire v4
descubren solo en sus rutas → las capas de entrega se quedan y solo actualizan imports).
Módulos: `Booking` (Catalog&Booking) · `Content` · `Identity` · `Payments` · `Platform`.
`app/Models` y `app/Support` MUEREN al final (paso 7).

**Contratos**: interfaces + DTOs `readonly` + enums en `Contracts/` del módulo DUEÑO,
creados en su namespace DEFINITIVO antes de mover implementaciones (bind a la clase legacy
en el ServiceProvider del módulo); se EXTRAEN de llamadas existentes, nunca superficies
nuevas. Excepción documentada (costura de BD): las relaciones Eloquent cruzadas
(`Order::belongsTo(User)`, `Payment::morphTo`) siguen siendo referencias de clase directas.

**Frontera ejecutable** (futuro) — se crea en el paso 1 como `tests/Feature/Architecture/ModuleBoundariesTest.php`: grafo
permitido: todos→Platform; Content/Identity→`Contracts` de Booking/Payments;
Booking↔Payments SOLO por allowlist de costura explícita en el test. La baseline se mide
tras el PRIMER movimiento (el namespace plano esconde dependencias: aflorarán entradas al
mover — la regla «la baseline solo encoge» aplica desde el paso 2) y solo puede encoger.

**Resolución de AMBIGUOS del inventario**: `CookieConsent`→Identity (autoridad de
consentimiento; la landing lo consume vía composer) · `PuertaSettings`→Identity (valida
registros/waiver) · `OrderAdjustment`→Booking (ledger pegado al pedido; Payments lee
`deposit_remainder` vía contrato) · `OrderRefundFlags` y la mitad refund de
`HasItemActionGuards`→Payments (read-model de reembolsos; el trait se PARTE en el paso 5).

**Renombres** (spec `vocabulario-dominio.md`, en el paso que mueve cada clase, nunca en
commit aparte): `ParkRule`→`VenueRule` (paso 3; SIN rename de tabla — `$table='park_rules'`
fijo, evita migración y riesgo) · `ParkSchedule`→`OperatingSchedule` (paso 6).

## 4.bis Paso 1 EJECUTADO (2026-08-12) — lo que el código enseñó
El inventario del diseño se hizo sobre 278 clases; al implementar el paso 1 se midieron las
flechas REALES una a una. Cuatro cosas no eran como el spec las anticipaba, y así quedaron
(`DECISIONES #14`):

1. **El contrato de Payments es SOLO reembolso.** El «cobro» (`Redsys::buildPaymentFormData`)
   no lo consume Booking: sus únicos llamantes son la capa de entrega (`Livewire\Tickets\
   Purchase`, `RetryPaymentController`), que en Fase 2 no se mueve. Ponerlo en el contrato
   habría sido una superficie nueva, prohibida por §4. La única llamada Booking→Payments del
   sistema es `Order::execute{Full,Partial}Refund` → `executeRefund`.
2. **El DTO viaja CON el contrato.** `RedsysRefundResult` → `App\Domain\Payments\Contracts\
   RefundResult` (mismos 5 campos, mismos 4 constructores). Si el contrato devolviera un tipo
   de `App\Support`, su firma cambiaría en el paso 5 y los consumidores con ella: no sería un
   contrato estable y se perdería el objetivo de §2. «Sin mover nada» del paso 1 aplica a las
   IMPLEMENTACIONES; §4 ya decía que los DTOs se crean en `Contracts/`.
3. **Había que EXTRAER, no solo declarar.** Para Content e Identity no existía ninguna clase de
   Booking a la que bindear: las consultas vivían DENTRO de los consumidores. El paso 1 las
   sacó a dos read-models propiedad de Booking —`CustomerReservationsReader` y
   `PublishableCatalogReader`— que se quedan en `app/Support` hasta el paso 6 (mismo patrón
   «implementación legacy, contrato en destino»).
4. **Los contratos de Booking reciben `int $userId`, no `User`.** La consulta siempre fue por
   `user_id`; así Booking no importa un modelo de Identity y el grafo queda sin esa flecha.

**Hallazgo de regalo**: la regla de comprabilidad #226 estaba DUPLICADA en dos consultas SQL
distintas (`Attraction::complementIsPurchasable()` y `LandingComplementResolver::compute()`)
que podían divergir en silencio. El contrato las unificó en una sola.

**Frontera ejecutable** (`tests/Feature/Architecture/ModuleBoundariesTest.php`): escanea con el
TOKENIZADOR de PHP (no regex: los docblocks de los contratos citan clases legacy a propósito).
Tres guardas — grafo permitido · baselines `SEAM`/`LEGACY` que solo encogen · «desde fuera de
`app/Domain` solo se tocan `Contracts`». Las tres se verificaron **por mutación** (se introdujo
a mano cada violación y se comprobó que el test cae). Comportamiento: `ModuleContractsTest`
sustituye cada contrato por un doble y exige que el consumidor real cambie de conducta.

## 4.ter Paso 2 EJECUTADO (2026-08-12) — Platform, y lo que costó de verdad
Primera MUDANZA real (12 clases, 236 ficheros tocados). Cuatro lecciones, todas aplicables a
los pasos 3–6 (`DECISIONES #15`):

1. **El grafo de imports MIENTE en un namespace plano.** Medido con el tokenizador: 30+
   referencias a clases HERMANAS del mismo namespace no necesitan `use` y por tanto **no
   aparecen como dependencia**. Mover la clase las convierte en «class not found». En Platform
   afectaba a 9 ficheros (`OrderCreator`, `SlotGenerator`, `SlotOffer`, `HeroStatus`,
   `ScheduleDisplay`, `RedsysReturnHandler`, `ManualOrderFulfiller`, `CustomerRegistrar`,
   `ReservationSlip`), que ganaron el `use` explícito en el mismo commit.
   👉 **Antes de cada mudanza: medir las invisibles**, no fiarse de los `use`. El paso 2 usó un
   script de un solo uso (tokeniza y compara nombres cortos contra las clases del namespace).
2. **Hay FQCN que son DATOS y no se tocan.** `2026_08_12_100000_convert_morph_types_to_aliases`
   guarda `'App\Models\Setting'`… como **valores que la BD tenía**, no como referencias a
   código. Un `sed` global los reescribe y la migración deja de reconocer las filas legacy —
   **en silencio**, porque es idempotente y no falla. Se excluyó del barrido, se marcó
   `⛔ CONGELADO` y lo vigila `MorphMapTest` (guard verificado por mutación).
   👉 El checklist de §5 distingue ahora **imports** (se editan) de **cadenas FQCN históricas**
   (se congelan).
3. **Platform no tiene `Contracts`, y es correcto.** El grafo dice «todos→Platform»: es la base
   compartida (settings, audit, formateo, i18n), no una costura sustituible. Ponerle interfaces
   habría significado inventar 12 de una línea — la «superficie nueva» que §4 prohíbe. La
   tercera guarda del arch-test se relajó SOLO para Platform, con el porqué escrito en el test.
   Los `Models/` de los módulos NO-Platform siguen sin preautorizar: lo decide el paso 3.
4. **La pertenencia a Platform es comprobable, no opinable**: como su lista de permitidos es
   vacía, una clase solo es Platform si no depende de nadie más. Resultado: 12 clases
   (9 de `Support` + `Setting`, `AuditLog`, `HasTranslations`), que coinciden con el recuento
   del inventario multi-agente. Única excepción declarada: `AuditLog::user()` es un `belongsTo`
   → costura Eloquent de §4, y vive en la allowlist `SEAM`, no en la baseline legacy.

**Nota de deploy** (aplica a este paso por mover 2 modelos): drenar la cola y `queue:restart`
antes de desplegar — los payloads serializados llevan el FQCN viejo.

## 5. Orden de migración (un paso = una unidad committeable, suite verde + gates)
0. **Cimientos** (con este spec): pre-push ancla los críticos por BASENAME (no por ruta);
   `docs-check` y `MorphMapTest` cuentan modelos en `app/Models` + `app/Domain/*/Models`;
   regla «diff solo-imports» para pasos de movimiento.
1. ✅ **Contratos en destino** (2026-08-12, ver §4.bis): `App\Domain\Payments\Contracts`
   (`RefundGateway` + `RefundResult`), `App\Domain\Booking\Contracts` (`PublishableCatalog` +
   `ComplementPlacement` para Content; `CustomerReservations` + `UpcomingReservation` +
   `PendingGuestForm` para Identity), bindings en `Booking/Payments ServiceProvider` a las
   implementaciones legacy, y el arch-test de frontera con sus baselines.
2. ✅ **Platform** (2026-08-12, ver §4.ter): `Models/`(`Setting`, `AuditLog`) ·
   `Services/`(`AuditLogger`, `DisplayTime`, `Money`, `Duration`, `PhoneNormalizer`,
   `MaintenanceSettings`, `QrCode`, `Turnstile`) · `Concerns/`(`HasTranslations`) ·
   `Enums/`(`DashboardPeriod`). Sin `Contracts` a propósito. 236 ficheros tocados.
3. **Content** (+`VenueRule`): páginas/legales (`LegalContent`, `LegalIdentity`,
   `CookiePolicyContent`), landing (`HeroStatus`, `MapsEmbed`, `SocialEmbed`,
   `ThemeSettings`, presenters), `Faq`/`Page`/`LandingService`/`Offer`/`Attraction`/`Zone`*
   (*Zone es de Booking: solo su CMS visual va aquí — ver inventario).
4. **Identity**: `User`, `Consent`, `CookieConsent(+Log)`, `CustomerRegistrar`,
   `CustomerAccountContext`, `PuertaSettings`, puerta. `User` es kernel compartido: vive en
   Identity, las relaciones cruzadas quedan exentas (costura).
5. **Payments** (`VERIFY_CONC=1`): `Redsys*`, `RedsysReturnHandler`, `PaymentRefund`,
   `IncidentSettings`, `OrderRefundFlags` + split del trait de guards.
6. **Booking** (lo más referenciado, al final; `VERIFY_CONC=1` + verify-comandos):
   `Order`/`OrderItem`/`Ticket`/`TicketType`/`Slot*`/`Price`/`RateType`…, `OrderCreator`,
   `SlotOffer`, `*Availability`, `AddonResolver`, `TicketIssuer`, `OperatingSchedule`.
7. **Cierre**: retirar `app/Support`/`app/Models` vacíos, baseline final en la allowlist,
   reescribir `ARQUITECTURA.md` y rutas citadas en docs.

**Checklist mecánico de CADA paso de movimiento** (afinado tras el paso 2 — §4.ter):
0. **Medir las dependencias INVISIBLES** de lo que se mueve y de lo que se queda: referencias a
   clases hermanas del mismo namespace que no necesitan `use`. Son la causa nº1 de rotura y no
   se ven en el grafo de imports. Se añade el `use` explícito en el MISMO commit.
1. `git mv` + namespace + imports (app y tests).
2. Grep de FQCN inline en **blades** (26 reales en el paso 2, la estimación era ~30).
3. **Migraciones históricas**: distinguir dos casos opuestos —
   · las que **importan** la clase (`Legacy*`/`*Backfill`) → se editan sus imports;
   · las que llevan el FQCN como **cadena de datos** (`convert_morph_types_to_aliases`) →
     ⛔ **se congelan**: describen filas ya guardadas, no código de hoy.
4. Citas en docs (DoD-4) — lo caza `docs-check`, que vetó los dos pasos.
5. Suite + Pint + docs-check; si el diff toca `OrderCreator`/`RedsysReturnHandler`/
   `SlotGenerator` —aunque sea solo para añadir un `use`— el gate exige `VERIFY_CONC=1`.
6. Si toca modelos: nota de deploy «cola drenada + `queue:restart`» (payloads serializados con
   FQCN; `failed_jobs` legacy se reintenta o vacía ANTES).

## 6. Riesgos (de la revisión adversarial, con mitigación)
1. Gate `VERIFY_CONC` moría al mover el núcleo (ancla por ruta) → resuelto en paso 0.
2. `docs-check`/`MorphMapTest` acoplados a `app/Models` → resuelto en paso 0.
3. Blades con FQCN y migraciones que importan Support → en el checklist de cada paso.
4. Colas/failed_jobs con FQCN serializado → nota de deploy por paso con modelos.
5. Baseline engañosa por namespace plano → baseline post-primer-movimiento, solo-encoge.
6. Contratos que devuelven Eloquent (patrón cero-drift ya decidido) → el arch-test prohíbe
   importar `Services/` ajenos; mutación cross-módulo queda visible en revisión.
7. `EmailProductCard` (Booking) usa `ThemeSettings` (Content) → costura en allowlist hasta
   que el color de zona se exponga por contrato de Content.

## 7. Verificación empírica
Por paso: suite completa + Pint + docs-check (pre-push); pasos 5-6 además
`redsys:verify-concurrency` y `purchase:verify-oversell` en verde sobre MySQL. Final:
`ModuleBoundariesTest` con baseline = allowlist de costura y cero flechas fuera del grafo;
`git grep -c 'App\\\\Support'` → 0 (ejemplo); superficies vivas (home, /admin, PDF, email).

## 8. Revisión y decisión
Revisado adversarialmente por 3 agentes (veredictos: sólida-con-cambios ×3; los cambios de
alta gravedad están incorporados en §5.0 y §6). Decisión registrada: `DECISIONES #13`.
