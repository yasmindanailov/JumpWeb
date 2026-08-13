# Decisiones — JumpWeb

> Registro cronológico: qué se decidió y por qué. No cargar entero; buscar por número.

## #1 · 2026-08-12 · Origen de la base y relación con el cliente origen
JumpWeb nace como **fork generalizado** de la base en producción del proyecto jumpingjump
(sistema de reservas de un parque de saltos): se preservan el núcleo endurecido de dinero/aforo
y su suite de 2132 tests, que son el activo principal. **El repo del cliente queda intacto y es
la única vía de trabajo y deploy para ese cliente** (está live y en producción). El repo de
JumpWeb arranca con **historia git limpia** (commit fundacional desde el árbol, no `git clone`):
la historia del origen contiene datos de infraestructura del cliente (IP/SSH/rutas del servidor
en los scripts de deploy) que no deben viajar al producto; la arqueología completa sigue
disponible en el repo origen. Quedaron fuera del árbol: `deploy-prod.sh`, `artisan-prod.sh`,
`docs/`, `design_mockup/` (verificado por grep: sin IP/credenciales/emails del cliente).

## #2 · 2026-08-12 · Modelo de despliegue: 1 instalación por cliente
White-label por instalación (cada negocio su despliegue, BD y dominio), como asume el código.
Multi-tenant SaaS DESCARTADO por ahora (exigiría rediseño profundo de esquema/auth/storage);
el dominio se mantiene razonablemente agnóstico para no cerrar esa puerta.

## #3 · 2026-08-12 · El sidebar de reservas se rehace como SPA contra la API
El sistema del sidebar (login, reservas, gestión de usuario) pasa de Livewire a **SPA (Vue 3)
consumiendo la API v1** — el mismo contrato que usará la app móvil: un solo backend de verdad.
De paso se retira la deuda documentada de `Purchase.php` (~2.000 líneas + puente Alpine frágil).
La landing sigue **Blade server-rendered** por SEO; el panel sigue en Filament.

## #4 · 2026-08-12 · API-first desde ya
La API v1 (REST + Sanctum, OpenAPI, versionada) se construye como parte del refactor y la web
es su **primer consumidor**; la app móvil llegará después consumiendo lo mismo sin tocar
backend. «API-ready» sin consumidor real quedó descartado: un contrato sin cliente no se
verifica. Incluye abstracción `PaymentProvider` (Redsys = primer driver).

## #5 · 2026-08-12 · Nombre del producto: JumpWeb
Decidido por el owner. Repo GitHub privado `JumpWeb`; el nombre de cada instalación sigue
siendo data-driven (`business.name` en BD).

## #6 · 2026-08-12 · Entorno local dual
El stack Sail de JumpWeb convive con el del cliente origen en la misma máquina WSL2:
puertos propios (web 8081 · MySQL 3308 · Mailpit 8028) vía `.env` no versionado.

## #7 · 2026-08-12 · JumpWeb se desarrolla al 100% por agentes IA (repo agent-first)
Requisito del owner: en este proyecto trabajan exclusivamente agentes IA (Claude Fable 5 /
Opus). El repo se optimiza para ellos: `CLAUDE.md` enrutador (leer solo lo necesario, ahorro
de tokens), `CONVENCIONES.md` con el protocolo de agentes (DoD, handoff, empirismo, «nada vive
solo en la conversación»), `INVARIANTES.md` (endurecimiento que no se puede regresar), skills
de proyecto (`.claude/skills/`: `cierre-sesion`, `dod`) y permisos preconfigurados
(`.claude/settings.json`). El handoff impecable es crítico: ningún humano rellena huecos.

## #8 · 2026-08-12 · La doc técnica del origen se PORTA adaptada (no se copia en bloque)
El corpus técnico del origen (arquitectura, seguridad, testing, flujos, panel, sistemas
implementados) se porta a `docs/` **adaptado**: sin datos del cliente, sin narrativa de su
ciclo de vida (validaciones/despliegues/handoffs), con cabecera «describe la base heredada,
verificar contra el código». NO se copian los trackers del ciclo de vida del cliente
(00-PRODUCCION, handoffs, audits de copys…): doc desfasada en un repo 100% agentes es
contexto que miente. `MODELO-DATOS.md` se REGENERA desde el código (el del origen estaba
desfasado) y `AUDIT-FASE-1` + endurecimiento se destilan en `INVARIANTES.md`.

## #9 · 2026-08-12 · Sin GitHub Actions: el CI es un gate local de pre-push
El owner NO va a pagar facturación de GitHub (ni ahora ni más adelante) → GitHub Actions
queda DESCARTADO y `ci.yml` se elimina (un workflow muerto es contexto que confunde a los
agentes). Sustituto: **hook `pre-push` versionado en `.githooks/`** que corre Pint (repo
completo) + la suite completa `--parallel` (~75 s) y bloquea el push si algo falla.
Activación por clon (una vez): `git config core.hooksPath .githooks` (documentado en
README y CLAUDE.md). Refuerza el protocolo `/cierre-sesion`; `main` no puede quedar rojo.
**Ratificada por el owner el 2026-08-12 (preguntado explícitamente): SIN PRs — trunk +
gate local.** Un PR sin CI en la nube sería un botón sin checks, con escritor único se
auto-mergearía, y la revisión ya ocurre ANTES de cada commit (pasadas adversariales
multi-agente). Revisable si algún día se activa Actions o entran más escritores.

## #10 · 2026-08-12 · Endurecimiento del sistema documental (auditoría multi-agente)
Una auditoría adversarial (7 lentes, 51 hallazgos) demostró que la doc dependía al 100% de
disciplina en prosa y ya tenía drift el día 1 (Fase 0 contradictoria entre ESTADO y tracker,
cifras desviadas, ancla rota ×18). Se decide: **(a)** la doc entra al gate — `scripts/docs-check.sh`
(enlaces, anclas §N, rutas de código citadas, recuentos, coherencia de fases, sin citas
`fichero:línea`) corre en el pre-push; **(b)** el gate de pre-push aplica **solo a `main`**
(lee las refs de stdin) — las ramas `wip/…` se pueden empujar en rojo como backup, resolviendo
la contradicción con §8; **(c)** guarda de rutas críticas: un push de `main` que toque
`OrderCreator`/`RedsysReturnHandler`/`SlotGenerator` exige confirmar (`VERIFY_CONC=1`) que se
corrieron los comandos de concurrencia (la suite SQLite es ciega a esas carreras,
`INVARIANTES §6`); **(d)** precedencia de estado: los marcadores de `00-REFACTOR.md` mandan
sobre `ESTADO.md`; **(e)** reglas de cita (por `§N` y por símbolo, nunca `fichero:línea`) y
de cifras (siempre con su comando reproducible). Además se acotó la afirmación de Fase 0
«sin datos del cliente» a **infraestructura**: los datos de negocio (dirección, SEO,
jurisdicción en `ProductionSeeder`/`LegalContent`) siguen en el árbol y su retirada es ítem
explícito de Fase 1. Y **(f)** la capa agent-first se VERSIONA: `.claude/settings.json` y
`.claude/skills/` salen del ignore global `/.claude/` — hasta hoy, un clon nuevo perdía las
skills y los permisos que `DECISIONES #7` y la doc daban por presentes en el repo.

## #11 · 2026-08-12 · Ciclo de vida del agente completo y escala documental (Paquetes C y D)
Cierra el plan de la auditoría de `#10`. **(a)** Skill `/arranque-sesion`, espejo del cierre:
base verde VERIFICADA (árbol, push pendiente, wip, hook, stack, gates) antes de trabajar —
nacida del apagón real de hoy, que demostró el hueco. **(b)** El DoD pasa de 3 a 4
condiciones: la doc del sistema tocado se actualiza AL TERMINAR la tarea, no al cierre
(si la sesión muere antes, el conocimiento se perdía). **(c)** CONVENCIONES §9: lista
CERRADA de cuándo parar y preguntar al owner (invariantes, revertir decisiones,
dependencias, borrados, producto/alcance, gasto). **(d)** Permisos con fuerza técnica en tres capas:
allow para todos los comandos literales del protocolo · deny declarativo (`rm -r*` pide
confirmación; `migrate:fresh`/`db:wipe` y el repo origen vía Read/Edit/Write, vetados) ·
**hook PreToolUse `scripts/guard-bash.sh`** que bloquea CUALQUIER Bash que toque
`proyectos/jumpingjump` (también lectura: grep/rg/find) o contenga `migrate:fresh`/`db:wipe`
en cualquier forma (`bash -c`, etc.). Límite conocido y asumido: `tinker` interactivo no es
inspeccionable — lo cubre CONVENCIONES §9.4 (borrados = owner). **(e)** Los 54 invariantes llevan ID estable
(`PAY-`/`AFORO-`/`RGPD-`/`SEC-`/`PERF-`/`SUITE-NN`) citable desde tests, commits y
enrutado. **(f)** `docs/specs/` con plantilla: los diseños pre-implementación son artefacto
de primera clase revisado por otro agente. **(g)** Plantilla de cabecera con estado y
«verificado contra código» + fuentes únicas declaradas (suite→ESTADO, puertos→README).
**(h)** Decisión revertida → «Sustituida por #N» en la antigua. **(i)** Docs nuevos con
contenido verificado por agentes: `GLOSARIO.md` (término↔código), `DEUDA.md` (registro
único; destapó que morphMap es prerequisito NO listado de Fase 2 y que la 2FA de admin no
existe ni tiene plan) e `INSTALACION-CLIENTE.md` (la promesa white-label como runbook),
más `TESTING.md` §datos de prueba (el contrato de conteos del seeder-fixture, que rompía
la suite en silencio). **(j)** `AGENTS.md` = symlink a `CLAUDE.md` (estándar abierto de la
Linux Foundation; coste cero, evita reabrir la pregunta). **(k)** Enrutado con granularidad
de sección/ID y la fila Landing partida.

## #12 · 2026-08-12 · Decisiones de Fase 1 del owner: slugs, prefijo de pedidos, semilla, sectores
**(a) Slugs públicos**: se quedan en español AHORA; pasan a ser configurables por
instalación en la Fase 4, cuando la SPA+API rehagan el routing (cero churn de SEO/tests hoy;
el trabajo se haría dos veces). **(b) Prefijo de códigos de pedido**: deja de ser el literal
`JJ-` y pasa a setting por instalación (`sales.order_prefix`, default neutro `R-`),
data-driven puro; los códigos ya emitidos no se reescriben. **(c) Semilla neutra**:
`ProductionSeeder` se transforma en la semilla de instalación del MISMO tipo de negocio
(parque de trampolines de ejemplo) preservando catálogo y configuración — se retiran SOLO
los datos personales/identificativos del cliente origen (dirección, mapa, URL de registro,
SEO con ciudad real, festivos locales). **(d) Sectores objetivo del white-label**: ocio con
aforo y franjas (parques, escape rooms, karting, bolos…) — la generalización de vocabulario
de Fase 2 será CONSERVADORA: zona/atracción/franja encajan casi tal cual (spec en
`docs/specs/`).

## #13 · 2026-08-12 · Arquitectura de módulos de Fase 2 (spec aprobado con revisión multi-agente)
Diseñada con 4 inventariadores (278 clases clasificadas) + 3 arquitecturas rivales
(mínimo-riesgo · contratos-primero · núcleo-primero) + 3 revisores adversariales; la
síntesis vive en `docs/specs/modulos-dominio.md`. Decidido: **(a)** layout
`app/Domain/<Contexto>/` bajo el PSR-4 existente (composer/Filament/Livewire intactos),
modelos DENTRO de cada módulo, capa de entrega quieta en Fase 2 (solo imports);
**(b)** contratos = interfaces + DTOs readonly + enums en `Contracts/` del módulo dueño,
creados en su namespace DEFINITIVO antes de mover implementaciones; relaciones Eloquent
cruzadas exentas como costura de BD documentada; **(c)** orden: contratos → Platform →
Content → Identity → Payments → Booking (el dinero, EL ÚLTIMO, con `VERIFY_CONC` y los
verify-comandos); la opción «núcleo primero» se DESCARTÓ (churn infraestimado +50–220% y
núcleo protegido moviéndose sin contratos); **(d)** frontera ejecutable con arch-test de
grafo permitido y baseline solo-encoge; **(e)** cimientos aplicados YA: el gate
`VERIFY_CONC` del pre-push ancla los críticos por basename (no por ruta), y
`docs-check`/`MorphMapTest` cuentan modelos también en `app/Domain/*/Models` — los tres
gates habrían muerto en silencio con la primera mudanza (hallazgo de la revisión).

## #14 · 2026-08-12 · Paso 1 del spec de módulos: los contratos, medidos contra el código
Ejecutado `docs/specs/modulos-dominio.md` §5.1 (detalle en su §4.bis). Al medir las flechas
reales una a una, cuatro puntos del diseño se concretaron distinto y así quedan decididos:
**(a)** el contrato de Payments es **solo reembolso** (`RefundGateway::executeRefund`) — el
«cobro» no lo consume Booking sino la capa de entrega, y ponerlo habría sido una superficie
inventada, que §4 prohíbe; **(b)** el DTO **viaja con el contrato** (`RedsysRefundResult` →
`App\Domain\Payments\Contracts\RefundResult`): un contrato cuyo tipo de retorno siguiera en
`App\Support` cambiaría de firma en el paso 5 y no sería estable — que es justo lo que el paso 1
existe para evitar; **(c)** para Content e Identity **no había nada que bindear** (las consultas
vivían dentro de los consumidores), así que el paso 1 las EXTRAJO a dos read-models de Booking
(`CustomerReservationsReader`, `PublishableCatalogReader`) que se quedan en `app/Support` hasta
el paso 6; **(d)** los contratos de Booking reciben `int $userId` y no el modelo `User`, con lo
que el grafo se ahorra una flecha Booking→Identity.
Efecto colateral valioso: la regla de comprabilidad #226 estaba **duplicada** en dos consultas
SQL independientes (`Attraction::complementIsPurchasable()` y `LandingComplementResolver`) que
podían divergir en silencio; el contrato las unificó en una.
La frontera (`ModuleBoundariesTest`) escanea con el **tokenizador de PHP**, no con regex —los
docblocks de los contratos citan clases legacy a propósito y una regex las contaría como
dependencias— y sus tres guardas se validaron **por mutación** (introducir la violación a mano y
comprobar que el test cae). `ModuleContractsTest` cubre la otra mitad: sustituye cada contrato
por un doble y exige que el consumidor real cambie de conducta, de modo que nadie pueda volver a
llamar a la implementación legacy por debajo sin que la suite lo cante.

## #15 · 2026-08-12 · Paso 2 (Platform): qué es Platform y qué enseñó la primera mudanza
Ejecutado `docs/specs/modulos-dominio.md` §5.2 (detalle en §4.ter). Decidido:
**(a) Platform NO tiene `Contracts`.** El grafo dice «todos→Platform»: es la base compartida
(settings, audit, formateo de dinero/fechas, i18n), no una costura de dominio que haga falta
poder sustituir. Ponerle interfaces habría significado inventar 12 de una línea —exactamente la
«superficie nueva» que el spec §4 prohíbe—. La tercera guarda de `ModuleBoundariesTest` («desde
fuera solo se tocan los Contracts») se relajó **solo** para Platform, con el porqué escrito en
el propio test. Los `Models/` de los módulos no-Platform NO se preautorizan: lo decide el paso 3
con datos delante.
**(b) La pertenencia a Platform es comprobable, no opinable**: como su lista de permitidos es
vacía, una clase solo puede ser Platform si no depende de nadie más. Da 12 clases, que coinciden
con el recuento del inventario multi-agente (9 de `Support` + 3 ficheros de `Models`). Única
excepción declarada: `AuditLog::user()` es un `belongsTo` → costura Eloquent del §4, y vive en
la allowlist `SEAM`, no en la baseline legacy.
**(c) Dos trampas descubiertas al mover, que gobiernan los pasos 3–6:**
1. **El grafo de imports miente en un namespace plano.** 30+ referencias a clases hermanas no
llevan `use` (no hace falta dentro del mismo namespace) y por tanto son INVISIBLES; al mover la
clase se vuelven «class not found». Se midieron con el tokenizador y 9 ficheros ganaron el
`use` explícito. El checklist del spec incorpora este paso 0: **medir las invisibles antes de
mover**.
2. **Hay FQCN que son DATOS.** La migración `convert_morph_types_to_aliases` guarda
`'App\Models\Setting'`… como los valores que la BD tenía en 2026-08-12. Un `sed` global los
reescribe y la migración deja de reconocer las filas legacy **en silencio** (es idempotente: no
falla, solo abandona los datos). Se excluyó del barrido, se marcó ⛔ CONGELADO y se le puso
guard en `MorphMapTest`, verificado por mutación. El checklist ahora separa **imports** (se
editan) de **cadenas históricas** (se congelan).
**(d)** El paso confirmó que el gate de dinero funciona: tocar `OrderCreator`, `SlotGenerator` y
`RedsysReturnHandler` —aunque fuese solo para añadir un `use`— disparó la exigencia de
`VERIFY_CONC=1`. Se corrieron los dos verificadores sobre MySQL real.

## #16 · 2026-08-12 · Paso 3 (Content): la puerta de entrada, decidida con datos
Ejecutado `docs/specs/modulos-dominio.md` §5.3 (detalle en §4.quater). Decidido:
**(a) Quién puede entrar a un módulo desde fuera.** El paso 2 dejó la pregunta abierta a
propósito («la decide el paso 3 con datos»). Los datos: al mudar Content aparecen ~40
referencias desde Filament (22), controladores (11) y providers (6) a sus modelos **y a sus
servicios**. Es la capa de entrega haciendo su trabajo; prohibírselo habría exigido reescribir
el panel, que Fase 2 declara fuera de alcance. Regla final: la **capa de entrega**
(`Console`, `Exceptions`, `Filament`, `Http`, `Livewire`, `Mail`, `Notifications`, `Providers`)
usa la superficie pública de cualquier módulo —es el *composition root*—, mientras que el
**código de dominio aún sin mudar** (`app/Support`, `app/Models`) solo entra por `Contracts` o
por Platform; lo demás va a la baseline `PENDING`, que solo encoge y se vacía en el paso 7. La
guarda se verificó por mutación en sus DOS direcciones: la MISMA referencia es roja desde
`app/Support` y verde desde `app/Http`.
**(b) `ParkRule` → `VenueRule` renombrando SOLO la clase.** La tabla (`park_rules`), el alias
morph (`'park_rule'`) y los nombres del panel (`ParkRuleResource`, URL `/admin/park-rules`,
claves i18n `admin.park_rules.*`) se CONGELAN: renombrarlos exige migración de datos o cambia
URLs a cambio de nada. Verificado en MySQL dev: el alias resuelve a la clase nueva y las filas
se leen igual. Esto **rectifica** `vocabulario-dominio.md`, que proponía renombrar la tabla;
manda el spec de módulos.
**(c) Content no es autosuficiente, y ahora se ve.** El arch-test destapó tres dependencias
reales de Content hacia Booking, hoy en la baseline `LEGACY`: calendario de operación
(`OpeningHour`/`Season`/`SpecialDate`/`ParkSchedule`), identidad de zona (`Zone`) y precio de
referencia (`TicketType`). En el paso 6 habrá que elegir entre darles contrato o **reclasificar
el calendario**: lo consumen la landing (horarios, SEO) y Booking (franjas) por igual, así que
puede que no pertenezca a Booking sino al recinto. No se decide antes de tener el dato.
**(d)** El «paso 0» del checklist (medir dependencias INVISIBLES) deja de ser artesanal:
`scripts/module-deps.php`, con el mismo tokenizador que el arch-test.

## #17 · 2026-08-12 · Paso 4 (Identity): la trampa de las factories y dos reglas con nombre
Ejecutado `docs/specs/modulos-dominio.md` §5.4 (detalle en §4.quinquies). Decidido:
**(a) Las factories se quedan PLANAS en `database/factories/`.** Mover `User` rompía la
resolución por los DOS sentidos: modelo→factory (Laravel adivina a partir del namespace
completo) y factory→modelo (adivina `App\Models\{Basename}`). Se resuelve con un
`Factory::guessFactoryNamesUsing` por nombre CORTO en `AppServiceProvider` + `protected $model`
explícito en `UserFactory`. La alternativa —espejar el árbol de módulos dentro de
`database/factories/`— se descartó: no aporta nada y multiplicaría el churn de cada mudanza.
Lo cubre `FactoryResolutionTest`, que vigila los dos sentidos y que la factory CONSTRUYE.
Aprendizaje de método: la trampa se verificó y desactivó ANTES de mover (estaba anotada desde el
paso 3), pero el guard inicial solo cubría un sentido y el otro lo destapó la suite — un guard
escrito «de memoria» sobre una API ajena hay que contrastarlo contra la API, no contra la idea
que uno tiene de ella. Hizo falta además `composer dump-autoload`: con el classmap viejo,
`class_exists()` intentaba incluir el fichero borrado y el fallo ni siquiera era limpio.
**(b) Dos exenciones con NOMBRE en el arch-test, no entradas anónimas en una baseline.** Las 10
flechas nuevas que destapó el paso no son deuda, son reglas ya decididas; meterlas en una
allowlist habría escondido el porqué y sugerido que hay que retirarlas:
· `SHARED_KERNEL` = `User` — el §4 del spec ya lo declaró kernel compartido (auth, autoría,
`belongsTo` de pedidos y reembolsos);
· `OUTBOUND` = `App\Notifications\*` + `App\Mail\*` — un servicio de dominio que avisa al cliente
construye un `Notification`/`Mailable`: es el canal de salida del framework, no una llamada a
otro contexto. Invertirlo (evento de dominio + listener) es reestructurar, y Fase 2 es mudanza.
Ambas verificadas por mutación: `Identity\Models\Role` (que NO es kernel) sigue siendo rojo desde
`app/Support`, y un módulo importando `App\Http\Controllers\*` también.
**(c) La supresión RGPD cruza contextos, y queda escrito.** `User::purge…` (art. 17) vacía
`order_items.guest_data`/`event_data` — PII de TERCEROS (alergias de menores, art. 9). No es una
relación Eloquent sino una operación transversal por naturaleza; el diseño limpio sería que
Identity emitiera «usuario anonimizado» y cada contexto borrase lo suyo. Anotado en `SEAM` con su
porqué para que la decisión exista y no se pierda en el paso 6.

## #18 · 2026-08-12 · Paso 5 (Payments): mudar el dinero sin tocarlo, y la costura al descubierto
Ejecutado `docs/specs/modulos-dominio.md` §5.5 (detalle en §4.sexies). Regla que gobernó el paso:
**es una MUDANZA** — ni una línea de lógica de cobro o reembolso podía cambiar en el mismo commit
(§2 + `INVARIANTES` §1, leídas antes de tocar nada). Decidido:
**(a) El trait `HasItemActionGuards` se parte, y se comprobó que el corte era limpio ANTES de
cortar.** Mezclaba editar/cancelar item (BOOKING: liberan plaza y NO tocan la pasarela, #157/#172)
con reembolsar item (PAYMENTS). Verificado: `refundItemBlockedReason` **no** llama a
`editItemBlockedReason` (repite a propósito sus dos comprobaciones con distinto criterio) y el
único helper privado lo usa solo la mitad de refund → sin solapes. Resultado:
`App\Domain\Payments\Concerns\GuardsItemRefunds` (3 métodos) + el resto en Booking; `Order`
compone ambos traits y su superficie pública se verificó por reflexión (9 métodos presentes).
**(b) La costura del dinero queda ENUMERADA, no escondida.** Con todo en un namespace plano,
Payments↔Booking era invisible; ahora está en el arch-test con su naturaleza:
· **Payments→Booking** (`SEAM`): FKs/tipos **y ORQUESTACIÓN** — `RedsysReturnHandler` es por
`PAY-01` el ÚNICO autorizado a pasar una Order a `paid` y por `PAY-03` dispara `TicketIssuer`.
Hoy Payments conduce el ciclo de vida de la reserva. El diseño limpio sería «pago confirmado»
como evento de dominio y Booking reaccionando; reestructurar el núcleo endurecido está prohibido
en este paso, así que queda anotado como **candidato nº1 a evento** cuando haya motivo real.
· **Booking→Payments** (`PENDING`, 11 flechas): `Order` es el punto de encuentro, y
`PaymentSettings` lo consumen `OrderCreator`/`SlotOffer`/`SlotGenerator` porque de ahí sale la
ventana de retención del pedido — candidata a contrato en el paso 6.
**(c) La baseline ENCOGIÓ por primera vez**, su único movimiento legal: `RefundGateway`→`Payment`
y `PaymentsServiceProvider`→`Redsys` dejaron de ser legacy al mudar sus clases dentro de Payments,
y el guard «solo encoge» habría fallado de no borrarlas. El mecanismo del paso 1 funcionó tal cual
se diseñó.
**(d) NO se corrió `redsys:verify-sandbox`.** Ejecuta una devolución REAL contra el TPV sandbox de
Redsys: es un efecto externo y no aporta a una mudanza. `PAY-08` (firma obligatoria en la respuesta
REST) lo cubre `OrderExecutePartialRefundTest::test_rest_refund_with_unsigned_response_is_rejected_not_accepted`.
Sí se corrieron los dos obligatorios (`redsys:verify-concurrency`, `purchase:verify-oversell`) sobre
MySQL real. Queda a decisión del owner si quiere esa pasada extra contra el sandbox.

## #19 · 2026-08-12 · Paso 6 (Booking): fin de `app/Support`, y el ajuste de cuentas
Ejecutado `docs/specs/modulos-dominio.md` §5.6 (detalle en §4.septies). 40 clases mudadas;
**`app/Models` y `app/Support` dejan de existir** — el objetivo de §2 cumplido. Decidido:
**(a) Dos flechas se ARREGLAN, no se perdonan.** La frontera las destapó y meterlas en una
allowlist habría bendecido dos inversiones reales: `ReservationException` vivía en
`app/Exceptions` (entrega) siendo excepción de DOMINIO → a `Booking/Exceptions/`; y `OrderCreator`
importaba `Livewire\Tickets\Purchase` **solo para leer `MAX_LINES_PER_CART`** —el acoplamiento que
el spec §1 señalaba desde el principio—, así que la constante vuelve a `OrderCreator`, donde vive
su enforcement (`PAY-12` la llama invariante de SERVIDOR), y `Purchase` la referencia desde allí:
mismo valor (50), misma aplicación, API pública intacta. Es el único cambio no-mudanza del paso y
va explicado en el commit.
**(b) `ALLOWED` reconoce el canal sancionado**: Booking→`Payments\Contracts` (y viceversa). La
llamada al gateway va por `RefundGateway`; el resto de Booking↔Payments sigue exigiendo entrada
explícita en `SEAM`. El contrato del paso 1 pasa de «preparación» a ser la vía normal.
**(c) Nace `DEFERRED`** para las 5 flechas Content→Booking que el paso 3 aplazó. No son costura
aceptada (`SEAM`) ni deuda con código legacy (`LEGACY`): son una decisión de diseño pendiente, con
nombre y fecha, que resuelve el paso 7. `PENDING` y `LEGACY` quedan **vacías**.
**(d) La opción (b) del paso 3 queda DESCARTADA con medición**: reclasificar el calendario a
Platform no es viable porque `SpecialDate` referencia `RateType` (tarifa) y Platform no puede
depender de nadie; haría falta un módulo «recinto» nuevo, más de lo que el spec aprobó. La
evidencia empuja a **contratos de lectura en Booking**, que es lo que decidirá el paso 7.
**(e) ⚠️ CAMBIO DE CONTRATO OPERATIVO — la migración del morphMap pasa a OBLIGATORIA.** Al
desaparecer `App\Models\*`, el fallback de lectura de Laravel para filas morph con FQCN legacy ya
no funciona: la clase no existe. `convert_morph_types_to_aliases` deja de ser un cinturón y se
convierte en **requisito de despliegue** — una instalación que actualice el código sin migrar
reventará al leer un `payable`/`priceable`/`target` antiguo. `MorphMapTest` fija exactamente eso
(antes aseveraba lo contrario, que era cierto hasta este paso).

## #20 · 2026-08-12 · Paso 7 (cierre de Fase 2): las 5 flechas aplazadas, resueltas
Ejecutado `docs/specs/modulos-dominio.md` §5.7 (detalle en §4.octies). **Fase 2 CERRADA.**
Decidido:
**(a) Vía (a) — contratos de lectura en Booking**, que era adonde apuntaba la evidencia del paso 6
(la vía (b), reclasificar el calendario a Platform, quedó descartada porque `SpecialDate`
referencia `RateType`). Nacen `Booking\Contracts\OperatingCalendar` (+ DTOs `OperatingWindow`,
`WeeklyOpening`, `SeasonWindow`, `SpecialDay`) y `Booking\Contracts\ZonePalette`, extraídos de lo
que la landing YA hacía: el hero, el horario de la landing, el JSON-LD de buscadores y el color de
zona. Ni un método más.
**(b) De regalo murieron DOS reglas duplicadas.** Content reimplementaba «cuál es la temporada
vigente» y «cuál es la ventana efectiva de una fecha especial», con comentarios que decían
literalmente *«para no divergir de lo que aplican las reservas»*. Es exactamente la trampa que el
paso 1 encontró en la coherencia #226 (dos consultas SQL con la misma regla): dos copias acaban
separándose. Ahora la regla la aplica su dueño y Content solo formatea. Coste en consultas: CERO
—`OperatingSchedule` ya memoizaba horarios, temporadas y excepciones—, y de hecho retira las que
Content hacía por su cuenta.
**(c) La línea entre costura y acoplamiento, fijada**: `LandingAddonPresenter` conserva su
`TicketType` en `SEAM` y no pasa a contrato, porque **recibir** una entidad de otro módulo es
costura de BD (§4), mientras que **consultar** sus datos o **repetir** sus reglas exige contrato.
Es el criterio con el que se decidieron las cinco, y el que debe aplicarse a la siguiente.
**(d) `effectiveFor()` NO cambia de firma.** La consumen `SlotGenerator` y `ProductAvailability`
—código de aforo protegido por `AFORO-01`/`AFORO-03`— así que el contrato añade `windowFor()`
tipado y el interior de Booking se queda como estaba. El contrato tipa la FRONTERA, no obliga a
reescribir el núcleo.
**Estado final**: `LEGACY`, `PENDING` y `DEFERRED` vacías; `SEAM` solo con costura documentada
(relaciones Eloquent, el dinero Booking↔Payments con su orquestación, y el `User` kernel). El
barrido de `App\Support\`/`App\Models\` en código da **cero**.

## #21 · 2026-08-13 · Dependencias de la API v1: Sanctum (runtime) + Spectator (dev)
El owner delegó explícitamente la elección (`CONVENCIONES §9.3` la reserva a él; la delegación
queda registrada aquí). Decidido, con verificación empírica:
**(a) `laravel/sanctum` ^4.3 — runtime.** Comprobado por resolución real contra las restricciones
de este repo: instala **v4.3.3** sin conflictos. La doc oficial de Laravel 13 describe exactamente
los dos modos que el spec diseña —SPA de primera parte por cookie de sesión con CSRF, móvil por
Bearer— y **desaconseja expresamente** usar tokens para la propia SPA. Descartados: Passport
(OAuth2 completo para dos clientes propios: servidor de autorización, scopes y llaves que nadie
usaría) y tokens a mano (reescribir emisión, rotación, hashing y revocación es superficie de
seguridad escrita a mano justo donde no conviene).
**(b) OpenAPI: especificación A MANO + `hotmeteor/spectator` ^3.0 en `require-dev`.** Comprobado
que resuelve limpio (v3.0.0, 7 paquetes, todos de desarrollo). Descartado **Scramble**
(generación desde el código) por una razón de contrato, no de ergonomía: `DECISIONES #4` hace de
la especificación el artefacto contra el que se construye la app móvil, y con generación el
documento es un REFLEJO del código — cualquier refactor cambiaría el contrato en silencio, que es
justo lo que un contrato con un cliente que despliega aparte no puede hacer. Spectator invierte la
relación (el spec manda, el test cae si el código se desvía), es el mismo patrón de gates que ya
usa el repo (`docs-check`, `ModuleBoundariesTest`, el guard del morphMap) y **no viaja a
producción**. Se declarará además `symfony/yaml` como dependencia de desarrollo explícita: hoy
está solo como transitiva de `packages-dev` y depender de ella sin declararla es frágil.
**(c) Sin dependencia de OpenAPI en runtime**: la especificación se sirve como fichero estático
versionado, no por un paquete que registre rutas.
Coste total: **1 dependencia de runtime + 2 de desarrollo**.
⚠️ Condición previa registrada en `DEUDA.md §Alta`: el árbol arrastra **26 avisos de seguridad**
(4 altas en `league/commonmark`) que un `composer update` normal resuelve dentro de las
restricciones actuales. No se instala nada nuevo sobre un árbol sin sanear.

## #22 · 2026-08-13 · Saneado de dependencias antes de tocar la API
`composer audit` (2026-08-13) daba **26 avisos en 5 paquetes**: `league/commonmark` (4 ALTAS: DoS
cuadrático y bypass del filtro de enlaces), `guzzlehttp/guzzle` (9, 1 alta), `dompdf/dompdf` (6),
`guzzlehttp/psr7` (4) y `laravel/framework` (1). No era teórico: guzzle es el cliente HTTP del
reembolso REST de Redsys, dompdf genera los 2 PDFs y commonmark renderiza Markdown. Decidido
ejecutar el saneado **como paso propio y verificado, ANTES de instalar nada nuevo** — no se añade
una dependencia a un árbol con avisos altos, y menos en la fase que abre el dominio al exterior.
Un `composer update` **sin tocar ninguna restricción de `composer.json` ni añadir paquetes** los
cerró: framework 13.11.2→13.25.0 · Filament 5.6.6→5.7.6 · Livewire 4.3.0→4.4.0 · guzzle
7.10.4→7.15.3 · psr7 2.10.1→2.13.0 · commonmark 2.8.2→2.10.0 · dompdf 3.1.5→3.1.6 · phpunit
12.5.26→12.5.33 · Pint 1.29.1→1.30.5.
Verificación empírica: `composer audit` y `npm audit` → **0 avisos** · suite **2186 verde** (y más
rápida: 58 s vs 71 s) · **Pint 1.30.5 pasa los 654 ficheros sin reformatear** (no hay churn de
estilo) · `docs-check` OK · `redsys:verify-concurrency` y `purchase:verify-oversell` EN VERDE sobre
MySQL real —con el guzzle nuevo en el camino de Redsys— · superficies `/`, `/normas`, `/servicios`,
`/sitemap.xml`, `/admin/login` → 200 · **PDF generado de verdad con dompdf 3.1.6** (cabecera
`%PDF-`, con acentos y €) · `npm run build` OK. Los 18 ficheros de `public/js|css/filament/*` del
diff son los assets que Filament republica al actualizar.

## #23 · 2026-08-13 · Anti-bot del registro en cliente nativo: NO se relaja nada, se aplaza a Fase 6
La revisión adversarial del spec de la API lo marcó como bloqueante de owner por relajar un
invariante (`CONVENCIONES §9.1`); el owner delegó la decisión. Al comprobarlo contra la doc, el
planteamiento se disuelve, y por dos motivos:
**(a) Precisión**: el Turnstile del REGISTRO no es `SEC-06` —cuyo texto cubre el 2.º limitador del
login, la no-enumeración del reset y el `throttle`+Turnstile de `/contacto`— sino `SEGURIDAD`
regla 5. El revisor lo atribuyó a `SEC-06`; es regla de seguridad, no invariante numerado. Además
es **data-driven**: sin clave configurada se desactiva solo, que es el principio white-label.
**(b) El consumidor de Fase 3/4 es la SPA, que ES un navegador.** `POST auth/register` exigirá el
token de Turnstile exactamente igual que la web. La app nativa es **Fase 6**: decidir hoy cómo
resuelve el anti-bot un cliente que no existe sería inventar diseño de seguridad para un lector
inexistente — el error que Fase 2 prohibió en sus contratos, cometido en el peor dominio posible.
Decidido: **no se relaja nada, no hay bloqueante y el paso 3 del spec queda desbloqueado.** La
pregunta se traslada a Fase 6 con las salidas ya escritas para que no haya que redescubrirlas:
(i) challenge en webview durante el alta, (ii) attestation de plataforma (Play Integrity / App
Attest) como anti-abuso equivalente, (iii) exigir alta por web y dejar la app solo para iniciar
sesión — que es legítimo y común, y hoy ya funcionaría: el login (`auth/tokens`) nunca dependió de
Turnstile, solo de los dos limitadores. Si en Fase 6 se eligiera relajar la regla, ESO sí vuelve a
ser decisión del owner.

## #24 · 2026-08-13 · Fase 3 paso 0: cimientos de la API, y dos cosas que solo se ven al montarlas
Ejecutado el paso 0 del corte de `docs/specs/api-v1.md` §9 (cimientos sin negocio). Lo previsto
salió como estaba diseñado; lo que sigue son las decisiones que hubo que **tomar** porque el spec
no podía verlas desde el papel.

**(a) Grupo `api` reemplazado, no ampliado.** El spec pedía declararlo «pieza a pieza»; se hace con
`$middleware->group('api', […])` en vez de `append`/`prepend`, porque en un grupo donde el ORDEN es
el diseño —`ApiLocale` necesita la sesión que inyecta Sanctum, `EnsureSiteAvailable` necesita el
idioma ya resuelto para su 503— depender de dónde inserte cada helper es un bug esperando.

**(b) Idioma: se LEE la sesión, nunca se escribe.** `SetLocale` (web) persiste la elección en
sesión, y por eso el spec dijo que no era reutilizable. La API resuelve
sesión → `Accept-Language` → `users.locale` → `config`. El primer escalón no estaba en el spec y se
añadió por un problema real: la SPA de Fase 4 comparte dominio y sesión con la web, así que sin él
un visitante que elige «FR» en el pie vería la landing en francés y los datos del sidebar en otro
idioma —la misma pantalla, dos idiomas—. Escribirla, en cambio, convertiría cada petición de un
cliente sin sesión en estado de servidor nuevo. Ambas mitades verificadas **por mutación**.

**(c) Códigos de error con indirección explícita.** `ApiErrorCode` es un enum cerrado y el mensaje
sale de `lang/<idioma>/api.php` por una clave derivada. Además, el mensaje **nunca** procede de la
excepción: `ModelNotFoundException` se convierte en un 404 cuyo `getMessage()` regala el FQCN del
modelo y el id ajeno. Con test.

**(d) `Cache-Control: no-store` decidido en la RESPUESTA, no ruta a ruta.** Se aplica cuando
`$request->user()` resuelve al volver del pipeline —`auth:sanctum` ya ha llamado a
`Auth::shouldUse()`, así que no cuesta ninguna consulta—. Ruta a ruta se acaba olvidando en alguna;
así `RGPD-04` es el defecto de la superficie. Efecto aceptado: un endpoint público pedido por un
visitante con sesión también sale `no-store`, lo que sacrifica caché para usuarios identificados
pero no para el tráfico anónimo, que es el que `PERF-02` protege.

**(e) Caducidad de tokens fijada YA, sin emisor.** Sanctum trae `expiration => null` (tokens
eternos). Se pone techo de 30 días y `sanctum:prune-expired` semanal aunque `POST auth/tokens` no
llegue hasta el paso 3: el default inseguro es justo el que nadie recuerda cambiar. La política
fina (abilities, renovación) se decide allí, con el flujo delante.

**(f) HALLAZGO — abrir `routes/api.php` abrió CORS a todo el mundo.** `HandleCors` es middleware
**global** de Laravel y su config por defecto —que vive en el framework hasta que se publica— trae
`'paths' => ['api/*']` con `'allowed_origins' => ['*']`. Verificado con `curl -I`:
`Access-Control-Allow-Origin: *`, presente solo en `/api/v1`. Nadie lo decidió. El daño inmediato
era acotado (sin `supports_credentials` el navegador no envía cookies cross-origin), pero el
catálogo del paso 1 habría quedado legible desde cualquier web y era superficie regalada en la fase
que abre el dominio al exterior. Cerrado publicando `config/cors.php` con orígenes **exactos**
derivados de `APP_URL` (nunca `*`), `supports_credentials: true` —seguro precisamente porque el
comodín no existe— y `CORS_ALLOWED_ORIGINS` para la instalación que sirva la SPA en otro dominio.

**(g) HALLAZGO — el limitador no ve las peticiones sin credencial.** Laravel ordena
`AuthenticatesRequests` **antes** que `ThrottleRequests` en `$middlewarePriority`, así que en una
ruta con `auth:` el 401 se lanza sin pasar por `throttle:api`. Se **conserva el estándar**: forzar
la prioridad afectaría también a la web, y allí sería PEOR —rutas como `verification.send` usan la
clave por defecto del throttle, y un anónimo pasaría a consumir el cubo por IP de los usuarios
legítimos tras el mismo NAT—. Lo que necesita techo de verdad (login, registro, reset, catálogo,
disponibilidad, quote) es público y sí lo recibe. Queda fijado por test para que nadie lo
redescubra.

**(h) Migración de Sanctum PUBLICADA al repo.** Comprobado que Sanctum solo declara
`publishesMigrations()` sin `loadMigrationsFrom()`: sin publicarla no habría tabla. Además, en un
producto que se instala cliente a cliente, el esquema tiene que estar versionado aquí (72
migraciones; `MODELO-DATOS.md` §5).

**(i) `CRITICAL_RE` ampliado por NOMBRE, no por carpeta.** El gate de concurrencia cubre ahora
`app/Http/Controllers/Api/**/(Order|Payment|Checkout|Quote|Availability)*.php`. Incluir la carpeta
entera obligaría a correr dos verificadores de 16 workers por tocar `MeController`, y un gate que
salta de más es un gate que se acaba saltando a mano. Que la lista siga cubriendo lo que debe lo
vigila `CriticalPathGateTest`, que además falla si un controlador de API llega al núcleo con un
nombre fuera del patrón.

**Verificación empírica**: suite **2224 verde** (8292 aserciones, 59,0 s) · Pint limpio (679
ficheros, sin reformatear) · `docs-check` verde · `composer audit` en 0 · superficies
`/`, `/api/v1/me` (401 con sobre), 404/405/429/503 de la API y 404 HTML de la web comprobados con
`curl` · idioma negociado en es/en/fr por `Accept-Language` · CORS comprobado con `Origin` propio y
ajeno · guardas de frontera, de contrato y de gate **verificadas por mutación** (se introdujo la
infracción, se vio el rojo, se revirtió). NO se corrieron los verificadores de concurrencia: el
paso 0 no toca `OrderCreator`/`RedsysReturnHandler`/`SlotGenerator` ni ningún controlador de
checkout, y el gate del pre-push lo confirma.

⚠️ **Hallado al verificar, y NO causado por este trabajo**: `npm audit` pasó de 0 (`#22`, esa misma
mañana) a **5 avisos (2 críticas, 3 altas)** sin que `package.json` ni `package-lock.json` cambiaran
— son avisos publicados en el intervalo. Se sanearon **aparte**, para no romper la unidad de trabajo
de este paso: `DECISIONES #25`.

⚠️ **Arreglado de paso, en el entorno local (no versionado)**: `.env` tenía `APP_URL=…:8080` con
`APP_PORT=8081`. Enlaces absolutos de correo, URLs firmadas y —ahora— la derivación de CORS y de
los dominios stateful de Sanctum salían con el puerto equivocado.

## #25 · 2026-08-13 · Saneado del árbol npm: 5 avisos → 0, sin tocar restricciones
Al verificar el cierre del paso 0 de Fase 3, `npm audit` daba **5 avisos (2 críticas, 3 altas)**
donde `DECISIONES #22` había dejado 0 esa misma mañana, y **sin que `package.json` ni
`package-lock.json` hubieran cambiado**: son avisos publicados en el intervalo, no una regresión
nuestra. Los cinco, en el árbol de HERRAMIENTAS DE BUILD: `shell-quote` (crítica, vía
`concurrently`), `vite`, `postcss` y `nanoid`. Ninguno en los paquetes que viajan al navegador
(`alpinejs`, `@fullcalendar/*`, `html2canvas`).

Decidido sanearlo **como unidad propia**, separada del paso 0, aplicando el mismo criterio que `#22`:
`npm audit fix` **sin `--force`**, es decir solo actualizaciones semver-compatibles, sin alterar
ninguna restricción de `package.json` ni añadir paquetes. Resultado: `package.json` **intacto**,
solo cambia el lock. `vite` 8.0.14→8.2.1 · `postcss` 8.5.15→8.5.26 · `nanoid` 3.3.12→3.3.18 ·
`shell-quote` 1.8.3→1.9.0 · `concurrently` 9.2.1→9.2.4.

**Por qué se sanea algo que «solo» afecta al build**: la cadena de construcción de assets es un
vector de suministro —lo que ejecuta compila el JavaScript que sí llega a los usuarios— y el
proyecto ya fijó en `#22` que no se construye sobre un árbol con avisos altos. El coste era un
`audit fix` y una verificación.

**Verificación empírica**: `npm audit` → **0** · `npm run build` OK con Vite 8.2.1 (23 módulos, 2,44 s)
y **los mismos nombres de fichero** que antes, señal de que el output no cambió · suite **2224
verde** (8292 aserciones, 60,0 s) · superficies `/`, `/normas`, `/servicios`, `/precios`,
`/admin/login` → 200 con sus hojas de estilo · **assets realmente servidos**, comprobados uno a uno
por HTTP: el `app.js` del manifest en la landing y los 8 del panel, incluido el tema Filament
compilado por Vite (`theme-…​.css`) que usa la pantalla de puerta.

Nota para quien lea esto en el futuro: que el árbol pasara de 0 a 5 avisos en unas horas sin tocar
nada es el argumento de por qué `composer audit`/`npm audit` son parte de la verificación de CIERRE
y no un trámite de instalación.

## #26 · 2026-08-13 · Fase 3 paso 1a: «mis reservas» y «mis pedidos» por API
Primera mitad del paso 1 del spec (`api-v1.md` §9). Se parte en 1a (cuenta) y 1b (catálogo) porque
son trabajos de dificultad distinta: esto es lectura sobre contratos y modelos que ya existen; el
catálogo exige **extraer un read-model de `Purchase.php`** y separar dominio de presentación
(`catalogSection` mezcla hoy ambas cosas: lleva una cadena `search` normalizada para el buscador en
cliente y un `zone_anchor` para el deep-link de la landing). Mezclarlos habría dado un commit que
nadie puede revisar.

**(a) La API no reimplementa ninguna regla.** `me/reservations` delega en
`Booking\Contracts\CustomerReservations` —el mismo contrato que alimenta el sidebar web desde Fase
2—, y todos los campos derivados de `me/orders` salen de métodos del dominio
(`displayStatus`, `displayStatusForCustomer`, `chargedSubtotalCents`, `guestFormStatus`,
`onlineDueCents`, `financialSummary`). Comprobado además que esos métodos devuelven **claves
estables** (`paid`/`expired`, `active`/`finished`, `ok`/`pending`) y no etiquetas traducidas: sirven
como contrato público tal cual. Los tests fijan las reglas heredadas (un pedido sin pagar no es
reserva próxima; lo pasado no se lista) para que se note si alguna vez divergen.

**(b) `ApiCollection`: una sola forma de lista, construida a mano.** El primer intento extendía
`ResourceCollection`; con un paginador, Laravel responde por `PaginatedResourceResponse`, que ignora
`$wrap` y añade sus propios `links` y `meta` → la respuesta salía con `data.data` y **dos** `meta`
distintos. Lo destapó el test de contrato en su primera ejecución, no una revisión. Ahora la clase
implementa `Responsable` y devuelve exactamente `{data, meta}`, pagine o no el endpoint. Sin `links`:
las URLs absolutas no le sirven a un cliente que construye sus peticiones.

**(c) `online_due_cents` era un nombre que MENTÍA.** Se diseñó como «lo que falta por pagar» y el
test lo puso en rojo: `Order::onlineDueCents()` es el importe que se cobra online —la señal si el
producto la usa, el total si no—, se deriva de los items y **no baja a 0 al pagar**. Renombrado a
`online_amount_cents` y documentado por lo que es: la misma fuente que consumirán `Payment.amount` y
el `DS_MERCHANT_AMOUNT` del reintento (paso 4), así que el cliente puede anticipar el cargo sin
recalcularlo. Es el tipo de error que un contrato validado caza y una revisión de código no.

**(d) Gotcha de OpenAPI 3.0**: un `enum` rechaza `null` aunque el campo se declare `nullable`, salvo
que `null` esté dentro del propio `enum`. Afectaba a `guest_form_status`, donde `null` significa «esta
línea no pide datos por invitado» y es distinto de «pendiente».

**(e) `MeOrdersController`, no `OrdersController`.** El gate de concurrencia del `pre-push` dispara
con los controladores de API cuyo nombre empieza por `Order` (`#24i`), y ahí vivirá el checkout del
paso 4. Este endpoint es de solo lectura: exigir dos verificadores de 16 workers por tocarlo
entrenaría a saltarse el gate. Que la separación es legítima —y no un truco para esquivarlo— lo
comprueba `CriticalPathGateTest`, que falla si un controlador fuera del patrón alcanza `OrderCreator`,
`SlotGenerator` o `RedsysReturnHandler`.

**(f) Tamaño de página**: 10 por defecto, techo 50, `?per_page=`. La web pagina de 3 en 3 por decisión
de producto **para esa pantalla**; un cliente que sincroniza historial necesita otro orden de
magnitud, y el techo impide que `?per_page=100000` convierta el endpoint en una descarga completa.

**Verificación empírica**: suite **2242 verde** (8398 aserciones, 59,3 s) · Pint limpio (688
ficheros) · `docs-check` verde · **contrato verificado por mutación** (renombrar `total_cents` en
`OrderResource` deja el test en rojo señalando el campo que falta) · los tres endpoints ejercidos
**contra MySQL real** con el pipeline HTTP completo (200, forma correcta y `no-store` en los tres),
no solo contra el SQLite de la suite.

## #27 · 2026-08-13 · Fase 3 paso 1b: el CATÁLOGO por API, extraído del flujo de compra
Segunda mitad del paso 1 (`api-v1.md` §9): `GET catalog/zones`, `catalog/products` y
`catalog/products/{id}`, públicos y de solo lectura. Detalle de lo aprendido en `api-v1.md` §10.ter.

**(a) Extracción, no copia: la web consume el mismo read-model desde el primer commit.** El spec
§4.6.4 pedía un «read-model de catálogo en Booking»; hacerlo solo para la API habría creado la
segunda definición de «qué se vende» que esa misma sección manda evitar. Nace
`Booking\Contracts\ProductCatalog` (+ DTOs `CatalogZone`, `CatalogProduct`, `CatalogProductDetail`,
`CatalogEventField`, `CatalogAddon`) con `CatalogReader` detrás, y `Livewire\Tickets\Purchase`
—que era el dueño de la regla— pasa a pedírselo. Lo comprueba `ModuleContractsTest` con un doble
que devuelve un producto inexistente: si la web volviera a consultar por su cuenta, la pantalla
saldría vacía y el test caería.

**(b) La frontera dominio/presentación se decidió campo a campo.** De los diez campos que producía
`catalogSection()`, dos se quedaron en la web: `search` (índice del buscador progresivo, que se
filtra EN CLIENTE) y `zone_anchor` (ancla de scroll del deep-link de la landing). Los dos se
DERIVAN de lo que da el contrato, así que no cuestan nada donde están y no obligan a un cliente
futuro a cargar con la interfaz de otro.

**(c) El catálogo NO puede consumir `AddonResolver::viewModel()`, como suponía el spec.** Su firma
exige el estado de la selección (cantidades, miembro elegido de cada grupo, invitados) porque su
trabajo es decir qué está activo y cuánto suma; un catálogo describe la OFERTA y no tiene ese
estado. Lo que sí se reutiliza son las reglas: qué complementos llegan a ofrecerse —un extra de
pago sin precio para la tarifa no se ofrece— y la selección por defecto (`defaultSelection()`).

**(d) Gotcha de OpenAPI 3.0, hermano del `enum` de `#26d`**: un `$ref` no admite `nullable` a su
lado, y la forma canónica (`allOf: [$ref]` + `nullable`) **la ignora el validador**: con ella una
zona nula falla y una zona presente también. La única que valida las dos es el objeto escrito
inline con su `nullable`; la copia resultante la vigila `ApiContractTest` campo a campo, así que no
puede divergir en silencio.

**(e) La validación de contrato hay que pedirla explícitamente.** Heredar de `ApiTestCase` deja
Spectator cableado, pero solo compara cuando el test llama a `assertValidResponse()`. Los primeros
tests del catálogo estaban en verde sin validar nada contra el documento — se descubrió al hacer la
mutación de comprobación, que solo tumbó el test que asertaba el campo a mano.

**(f) N+1 REAL encontrado midiendo, no leyendo.** El presupuesto de consultas se fija por PENDIENTE
(mismo coste con 1 elemento que con N) en vez de por techo fijo, y así destapó que
`RateResolver::priceCents()` hace su propia consulta: llamarlo dentro del bucle de complementos
costaba dos consultas por complemento. Arreglado resolviendo la tarifa una vez y leyendo el precio
de la relación ya cargada, con resultado idéntico. Dos trampas de medición documentadas en §10.ter
(`DB::listen` no se desregistra; la primera petición paga el `select` de `settings`).

**(g) `CatalogProductsController`, no `Availability*`.** Mismo criterio que dio nombre a
`MeOrdersController` (`#26e`): ese prefijo dispara el gate de concurrencia del `pre-push`, pensado
para el código que orquesta carreras de aforo. Un catálogo de solo lectura no toca ninguna. La
disponibilidad real del paso 4 sí lo llevará y sí lo disparará.

**(h) Lo que NO se tocó, a propósito.** El «precio desde» sigue siendo el mínimo de los precios
configurados **sin filtrar por tarifa activa**, igual que hoy: filtrarlo cambiaría el importe que se
le anuncia al cliente en una instalación con precios colgando de una tarifa desactivada, y eso es
decisión del owner, no de una extracción (`DEUDA.md`). Tampoco se decide aún la cacheabilidad HTTP
del catálogo público, que hoy sale con el `no-cache, private` por defecto de Symfony.

**Verificación empírica**: suite **2266 verde** (8541 aserciones, 63 s) · Pint limpio ·
`docs-check` verde · **contrato verificado por mutación** (un campo de más en `CatalogProductResource`
deja en rojo los tests que validan contra el documento) · los tres endpoints ejercidos **contra
MySQL real** con el pipeline HTTP completo: 200 y forma correcta, 404 con sobre de error, 422 del
filtro inválido, y textos en `es`/`en`/`fr` según `Accept-Language`.

## #28 · 2026-08-13 · Fase 3 paso 2: política de admisión e ida de pago, fuera de la capa de UI
Refactor SIN endpoints (`api-v1.md` §9, paso 2): las reglas que decidían si una reserva se admite y
el código que abre el cobro salen de `Livewire\Tickets\Purchase` y de `RetryPaymentController`, y
pasan a `Booking\Contracts\ReservationAdmission` (+ `ReservationAdmissionPolicy`) y a
`Payments\Services\PaymentInitiator`. Sin esto, el `POST /orders` del paso 4 habría sido «delgado
sobre `OrderCreator`» y habría reabierto el hallazgo E del origen —`OrderCreator` no contiene
ninguno de los topes—, además de ser la tercera copia de la ida del pago. Detalle en §10.quater.

**(a) Las dos superficies aplicaban políticas DISTINTAS, y nadie lo había decidido.** Poner las dos
copias en paralelo —lo que el spec describía como «duplicadas casi línea a línea»— destapó que el
reintento del sidebar aplicaba el tope de pendientes y el limitador por titular, y el de «Mis
pedidos» ninguno de los dos (solo el `throttle:6,1` por IP de su ruta). **Ningún test fijaba ni una
conducta ni la otra**: era un accidente de la historia. Se llevó al owner como tres decisiones:

**(b) El reintento NO cuenta contra `MAX_PENDING_PER_USER`** (decisión del owner). El tope protege
el aforo retenido por pedidos NUEVOS; un reintento reusa la plaza que ese mismo pedido ya retiene y
que ya cuenta en el contador. Aplicarlo dejaba sin poder pagar justo a quien más pendientes
acumulaba —con 5 vivos, el sidebar rechazaba el pago de cualquiera de ellos y encima devolvía al
carrito vacío—. «Mis pedidos» ya funcionaba así; ahora las dos coinciden y es a propósito.

**(c) El reintento SÍ pasa por el limitador por titular** en las dos superficies (decisión del
owner): se conserva la clave que ya existía (`reservation-confirm:{userId}`, 3/min) en vez de
inventar un cubo nuevo con un número que nadie ha medido. El `throttle:6,1` por IP de la ruta web se
mantiene: son capas distintas.

**(d) El limitador contaba pantallas, no reservas** (decisión del owner: corregirlo). Se consumía
una ficha al pasar del carrito al paso de pago —que no crea nada— y otra al confirmar, así que la
SEGUNDA compra del mismo minuto se bloqueaba con el tope nominal en tres. Ahora el contrato lo dice
explícito: `mayReserve()` consulta sin consumir y `admitReservation()` consume. El techo real sigue
siendo 3 reservas/min (test propio) y el aviso temprano se conserva, que era su otra función.

**(e) El veredicto es un DTO con clave estable, no una excepción ni un texto.** El dominio dice por
qué no admite; el efecto —`addError` y paso 4 en el sidebar, flash y redirect en «Mis pedidos», y
en el paso 4 un código público de la API— es de cada superficie. Lo justificó el propio refactor:
al heredar el límite de frecuencia, «Mis pedidos» iba a reutilizar el flash de «la reserva ha
caducado y la plaza se ha liberado», que le habría dicho a quien pulsó dos veces que había perdido
su reserva. Se añadió `order-retry-throttled` en los tres idiomas.

**(f) El gate de concurrencia se muda con el código.** El UPDATE atómico de `PAY-04` y el
`SUPERSEDED` de los intentos previos vivían en un componente Livewire y en un controlador web: dos
sitios que el `CRITICAL_RE` del `pre-push` nunca miró. Al darles nombre propio entran en el patrón,
en `CriticalPathGateTest::CRITICAL_FILES` y en `CRITICAL_SYMBOLS` —verificado por mutación: quitar
la entrada deja el test en rojo—. `INVARIANTES PAY-04` se actualizó al sitio nuevo, como manda su
propia regla de que el invariante viaja con el código.

**(g) Dos costuras declaradas en `SEAM`, ninguna nueva de verdad.** `PaymentInitiator → Order` es
hermana exacta de las de `Redsys` y `RedsysReturnHandler`; `ReservationAdmissionPolicy →
PaymentSettings` es la cuarta lectura de la misma config de retención (`OrderCreator`,
`SlotGenerator`, `SlotOffer`). Las dos EXISTÍAN ya, en ficheros de la capa de entrega que la guarda
de módulos exime — moverlas al dominio no las crea, las hace visibles y las reduce a un fichero.

**Verificación empírica**: suite **2292 verde** (8645 aserciones, 58 s) · Pint limpio ·
`docs-check` verde · **los dos verificadores de concurrencia sobre MySQL real con 16 workers**:
`purchase:verify-oversell` (1 compra + 15 `sold_out`, asientos == aforo) y
`redsys:verify-concurrency` (1 `authorized` + 15 `idempotent_paid`, 1 pago, 1 ticket) ·
política ejercida **contra MySQL** con tinker: extiende el hold vivo a la ventana completa, NO
resucita un hold ya cruzado y NO toca el pedido de otro titular ni acertando su código.

## #29 · 2026-08-13 · Fase 3 paso 3a: revocación de credenciales (y la emisión de tokens a Fase 6)
El paso 3 se parte en **3a** (revocación), **3b** (sesión por API) y **3c** (alta y contraseña),
por el mismo motivo que el paso 1: mezclar seguridad, extracción y endpoints en un diff lo vuelve
irrevisable. Detalle de lo aprendido en `api-v1.md` §10.quinquies.

**(a) La EMISIÓN de tokens Bearer se aplaza a Fase 6** (decisión del owner). El spec se
contradecía: §4.2 y §4.4 ponían `POST auth/tokens` en el paso 3, §9 solo pedía «revocación». El
consumidor de Fase 3 y 4 es la SPA, que usa cookie de sesión; la app nativa es Fase 6. Un emisor de
Bearers de 30 días que nadie consume durante dos fases es superficie de ataque sin contrapartida, y
`DECISIONES #4` ya descartó «API sin consumidor». La infraestructura queda lista (trait
`HasApiTokens`, caducidad de 30 días, `sanctum:prune-expired` semanal); lo que se aplaza es abrir
la puerta. El spec se corrigió para dejar de contradecirse.

**(b) La revocación SÍ se hace ahora, y el hueco era mayor de lo descrito.** §4.2 enumeraba cuatro
sitios donde un Bearer sobreviviría (supresión RGPD, cambio de contraseña, «cerrar otras sesiones»,
reset). Al medirlos aparecieron **cinco**: el quinto es `PurgeCustomerData`, la limpieza de go-live,
que borra la fila de `users` y dejaría los tokens **huérfanos** — `personal_access_tokens` es una
tabla morph **sin FK** (verificado: 0 claves foráneas). El comando ya borraba a mano `sessions` y
`password_reset_tokens` por esa misma razón; los tokens son la tercera tabla sin FK y llegaron
después de escribirlo.

**(c) Punto único, no cinco parches.** Nacen `User::revokeAllAccess()` (todas las credenciales) y
`User::revokeOtherAccess()` (todas menos la que hace la petición). Arreglar las cuatro copias por
separado habría dejado el mismo terreno para la quinta: la purga de sesiones estaba duplicada
precisamente porque no había un sitio donde ponerla. Ahora `AccessRevocationTest` prohíbe que nadie
más nombre `sessions` o `personal_access_tokens`, con dos excepciones declaradas por nombre (el
propio `User` y el borrado por CONJUNTO del comando de go-live, que no puede usar un método de
instancia).

**(d) Con sesión caen todos los tokens, y es lo correcto.** En `revokeOtherAccess()`,
`currentAccessToken()` devuelve un `TransientToken` sin id cuando la petición viene por navegador,
así que no hay ninguno que preservar: quien cambia su contraseña desde la web espera que cualquier
app conectada deje de estarlo. La rama simétrica —entrar por token y conservar solo ese— tiene test
propio.

**(e) `INVARIANTES RGPD-06`** recoge la regla, y `RGPD-01` se actualizó: la supresión del art. 17
invalida ahora las dos credenciales, no solo la sesión.

**Verificación empírica**: suite **2302 verde** (8664 aserciones) · Pint limpio · `docs-check`
verde · **las cinco vías verificadas por mutación** (revertir la revocación deja los 7 tests en
rojo; reintroducir una purga de sesiones a mano en un quinto fichero pone en rojo la guarda, con
fichero y línea) · revocación ejercida **contra MySQL real**: 2 tokens antes de anonimizar, 0
después y 0 filas huérfanas en la tabla.

## #30 · 2026-08-13 · Fase 3 paso 3b: sesión por API, con `SEC-06` heredado y no reimplementado
`Identity\Services\PasswordLogin` recoge lo que era dominio dentro de `Livewire\Auth\Login` —los
DOS limitadores de `SEC-06`, la comprobación de credenciales, `last_login_at` y el rastro en el
log— y nacen `POST /api/v1/auth/login` y `auth/logout` sobre la sesión stateful de Sanctum. La web
consume el servicio desde el mismo commit. Detalle en `api-v1.md` §10.sexies.

**(a) Los DOS limitadores viajan juntos, y por eso se extraen.** `MAX_ATTEMPTS = 5` por (correo, IP)
y `MAX_ATTEMPTS_PER_IP = 30` por IP sola. El segundo es el que frena el password-spraying: un
intento por cuenta nunca acumula cinco en la clave compuesta, así que una API que copiara solo el
primero —el error natural al reimplementar— dejaría pasar el barrido entero sin que nada lo
delatara. Verificado por mutación: quitar el de IP deja en rojo los tres testigos (servicio, web y
API). La asimetría de limpieza también se conserva y ahora tiene test propio: al acertar se limpia
la clave del titular, **nunca la de la IP** —es compartida, y limpiarla dejaría que un login válido
intercalado reiniciara el contador del atacante—.

**(b) Qué NO viajó al servicio**: `Session::regenerate()` y el anti-cesta-cruzada
(`purchase.user_id`, hallazgo D). Son efectos de la sesión WEB; un cliente de API no tiene cesta en
sesión. Criterio del spec §4.6.3, el mismo del paso 2.

**(c) `invalid_credentials`, código público nuevo.** Se separa de `unauthenticated` porque el
cliente los programa distinto («vuelve a intentarlo» vs «identifícate otra vez»), y **nunca**
distingue si el correo existe: hay test que compara byte a byte la respuesta de «contraseña
incorrecta» y la de «correo inexistente».

**(d) Sin origen *stateful* no hay sesión, y el login respondía 500.** Hallazgo real:
`EnsureFrontendRequestsAreStateful` solo monta `StartSession` si la petición trae `Origin`/`Referer`
de un dominio declarado, así que un cliente mal configurado llegaba al controlador sin sesión y
`$request->session()` reventaba. Ahora hay guarda explícita (400) antes de validar y antes de tocar
el limitador. **Nota para Fase 4**: el encabezado hace falta en TODAS las peticiones de la SPA, no
solo en el login — un `GET /me` sin `Origin` da 401 aunque la cookie sea válida (verificado).

**(e) El logout cierra también los guards ya resueltos.** `auth('web')->logout()` deja `web` limpio
pero el guard de la petición (`sanctum`) seguía cacheando al usuario (medido). Se añade
`Auth::forgetGuards()`.

**(f) La revocación del token del logout vive en `User`, no en el controlador.**
`ApiBoundariesTest` marcó el `$token->delete()` como escritura de dominio en la capa HTTP y tenía
razón: el paso 3a ya había decidido dónde va eso. Nace `User::revokeCurrentAccessToken()`.

**Verificación empírica**: suite **2320 verde** (8808 aserciones) · Pint limpio · `docs-check`
verde · `SEC-06` **verificado por mutación** en las tres capas · **ciclo completo ejercido con
`curl` y tarro de cookies contra el servidor real**: `GET /sanctum/csrf-cookie` → `POST auth/login`
(200 con el perfil) → `GET me` (200) → `POST auth/logout` (204) → `GET me` (**401**). Ese ciclo la
suite no lo puede probar: corre con `SESSION_DRIVER=array` y la sesión no viaja entre peticiones
(`SUITE-06`), así que un `/me` posterior daría 200 por el guard cacheado, no por la cookie.

## #31 · 2026-08-13 · Fase 3 paso 3c: alta y contraseña por API (y las dos políticas de enumeración)
Cierra el paso 3. Nacen `Identity\Services\SelfSignup` (las cuatro capas de defensa del alta y la
creación atómica de cuenta+rol+consents) y `PasswordRecovery` (limitadores propios, rotación del
`remember_token`, invalidación total de credenciales y no-enumeración), más los cuatro endpoints
públicos: `auth/register`, `auth/email/resend`, `auth/password/forgot` y `auth/password/reset`. La
web consume los dos servicios desde el mismo commit. Detalle en `api-v1.md` §10.septies.

**(a) La API DICE que un correo ya existe, igual que la web** (decisión del owner). Es una decisión
de producto de la clienta —prima la conversión sobre ocultar qué correos hay— y replicarla evita
que la misma persona vea respuestas distintas según por dónde entre, o que la SPA de Fase 4 cambie
el comportamiento visible sin que nadie lo haya pedido. Lo que acota la enumeración masiva es el
límite de **tres altas por hora y correo**, no el mensaje.

**(b) Y la recuperación de contraseña NO dice nada** (`SEC-06` estricto): pedir el enlace responde
202 exista o no la cuenta, y un token inventado, uno de otra cuenta y un correo inexistente
devuelven el MISMO 422 —con test que compara las tres respuestas byte a byte—. No es incoherente
con (a): allí hay alguien intentando comprar y esconderlo cuesta la venta; aquí no se gana nada
diciéndolo.

**(c) El contexto del alta lo declara el cliente y lo aplica el servidor** (decisión del owner).
`standalone` → 201 sin sesión y con verificación por correo; `purchase` → 201 con sesión y sin
verificación (pay-first: lo sustituye el pago, y un bot no paga). No es un interruptor de
seguridad: quedar identificado sin verificar es lo que el pay-first ya permite en la web, y sin
verificar no se accede a «mi cuenta».

**(d) El 201 del alta va SIEMPRE sin cuerpo.** El primer borrador devolvía el perfil cuando creaba
cuenta y nada cuando fingía (honeypot o límite por correo): dos respuestas que un bot distingue de
un vistazo, con lo que el señuelo dejaba de servir. **Lo destapó el test de contrato**, no una
revisión. Quien se registra en la compra ya tiene sesión y pide su perfil a `GET /me`.

**(e) La trampa de la sesión, por segunda vez y peor.** Igual que en el login del paso 3b, sin
`Origin`/`Referer` *stateful* no hay sesión y `$request->session()` revienta con 500; en el alta
con contexto `purchase` **la cuenta se creaba y el 500 llegaba después**. Las dos veces lo encontró
un `curl` contra el servidor real y ninguna la suite, que siempre manda el encabezado. La
comprobación se centraliza en `Http\Api\Concerns\RequiresStatefulSession` y se aplica ANTES de crear
nada.

**(f) El orden de las cuatro capas es la regla, no un detalle.** Señuelo → límite por IP → límite
por correo → anti-bot. Validar la forma antes del señuelo le diría al bot qué campos están mal
antes de que la trampa actúe; comprobar la existencia del correo antes de los límites convertiría
el endpoint en el oráculo que esos límites acotan. En el componente el orden estaba implícito en la
secuencia del método; al extraerlo hubo que escribirlo como decisión.

**Verificación empírica**: suite **2347 verde** (8955 aserciones) · Pint limpio · `docs-check`
verde · **honeypot y no-enumeración verificados por mutación** (que el señuelo cree la cuenta, o
que el reset distinga correo inexistente de token inválido, deja en rojo los testigos de la API y
de la web) · los cuatro endpoints ejercidos **contra el servidor real con `curl`**: alta suelta y
honeypot devuelven `201` con **0 bytes** (indistinguibles), el correo repetido da 422, `forgot`
responde igual para un correo existente y uno inventado, y el alta en compra sin origen *stateful*
da 400 sin crear cuenta (antes: 500 con la cuenta ya creada).

## #32 · 2026-08-13 · Fase 3 paso 4a: la tarificación de la cesta sale de la UI
Abre el paso 4, el del dinero y el de más riesgo de la fase. Se parte en cuatro unidades
committeables —4a tarificación, 4b disponibilidad con cesta, 4c creación y cobro, 4d desenlace— por
el mismo motivo que los pasos 1 y 3: trabajos de naturaleza distinta en un solo diff son
irrevisables. Esta entrada cubre **4a**. Detalle en `api-v1.md` §10.octies.

**(a) Nace `Booking\Contracts\CartPricing`** (+ DTOs `CartQuote`, `CartQuoteLine`, `CartQuoteAddon`),
con `CartPricer` detrás, y **la web lo consume desde el mismo commit**. Es la extracción que §4.6.4
exigía ANTES de exponer nada: sin ella, `POST orders/quote` habría tenido que sumar por su cuenta y
esa es la segunda fuente de verdad del precio que el spec quiere evitar. `ModuleContractsTest` lo
comprueba con un doble: si `Purchase` volviera a calcular importes por su cuenta, el test cae.

**(b) La aritmética estaba TRIPLICADA dentro del componente, y había una cuarta copia en el panel.**
`cartLines()`, `cartTotalCents()` y `cartDepositCents()` recorrían la misma cesta con las mismas
reglas; `CreateManualOrderPage` mantiene la suya, que precalcula al añadir la línea. **Se comprobó
caso a caso y hoy coinciden**, así que no había bug: había el terreno del hallazgo del paso 2, donde
dos copias «iguales» resultaron aplicar políticas distintas. La del panel **no se unifica aquí** —
calcula al añadir y no al pintar, y cambiarlo alteraría su conducta— y queda medida en `DEUDA.md`.

**(c) El «espejo exacto» pasa de comentario a test.** `cartDepositCents()` se documentaba como
espejo de `Order::onlineDueCents()` y nada lo comprobaba. `CartPricerTest` crea ahora el pedido real
con la misma cesta y compara los dos pares de importes, con una cesta **mixta y no vacía** y su
mutación (§6.3: el test de paridad de la v1 usaba la cesta vacía y pasaba por construcción). De paso
queda fijada una regla de dinero que no era obvia: **una señal fija se cobra una vez por LÍNEA, no
por unidad**.

**(d) `POST orders/quote` es público, sin estado y `POST`.** Público porque la web deja llegar hasta
el pago como invitado. Sin estado porque un presupuesto no reserva: no bloquea aforo, no comprueba
disponibilidad y no admite la reserva —eso es `OrderCreator` bajo lock (`AFORO-01`) y llega en 4c—.
`POST` porque la cesta no cabe con garantías en una URL, no porque tenga efectos.

**(e) Dos diferencias deliberadas con el checkout, y las dos visibles en el contrato.** Una línea sin
precio para la tarifa del día llega con `unit_price_cents: null` en vez de lanzar (el rechazo de
`PAY-12` lo pone el checkout), y una línea cuyo producto ya no se vende no aparece: su hueco en la
secuencia de `index` es la señal. Presupuestar no es poder comprar, y el documento lo dice.

**(f) El presupuesto acepta `event_data` y NO lo devuelve.** Se acepta para que el mismo cuerpo
sirva luego para crear el pedido; no se devuelve porque son las respuestas del formulario del pack
—nombre de un menor y a veces alergias, `RGPD` §3— y este endpoint es público. El cliente ya tiene
esos datos y las etiquetas están en `GET catalog/products/{id}`.

**(g) La forma de la cesta en la API tiene un solo sitio** (`Http\Api\CartPayload`): la comparten
`orders/quote`, la disponibilidad de 4b y `POST orders` de 4c. Valida con reglas en vez de sanear en
silencio —un cliente con un `date` mal formado recibe un 422 que NOMBRA el campo, no un presupuesto
con menos líneas—, y traduce el vocabulario público (`product_id`, `quantity`) al del dominio.

**(h) El coste bajó a un tercio sin buscarlo, y lo que no bajó está medido.** Una sola pasada con la
tarifa resuelta una vez por fecha: presupuestar 12 líneas del mismo día cuesta lo mismo que una
(fijado por PENDIENTE, no por techo). Lo que **sigue costando** son 2 consultas por complemento
(medido: 1 → 8, 3 → 12, 6 → 18) porque `AddonResolver::resolve()` pide el precio de cada uno por
separado; es código compartido con `OrderCreator`, que lo ejecuta dentro de la transacción de los
locks, así que su arreglo es un paso propio. Hay test que impide que empeore y entrada en `DEUDA.md`.

**Verificación empírica**: suite **2376 verde** (9094 aserciones, `--parallel` ~63 s) · Pint limpio ·
`docs-check` verde · **contrato verificado por mutación** (renombrar `online_amount_cents` en el
Resource deja en rojo 9 tests con «The required properties (online_amount_cents) are missing») ·
**los dos verificadores de concurrencia VERDES sobre MySQL real** con 16 workers (`QuoteController`
entra en el `CRITICAL_RE` del pre-push) · endpoint ejercido **contra el servidor real con `curl`**:
presupuesto simple, pack con señal (180,00 € de valor → 30,00 € online y 150,00 € en el parque),
422 con el campo nombrado, `Accept-Language: en` traduciendo el nombre del producto, cabeceras de
`SEC-01` presentes y CORS acotado a `APP_URL`. Funciona **sin `Origin`/`Referer`**, y es correcto:
no toca `session()` (§10.septies 34).
