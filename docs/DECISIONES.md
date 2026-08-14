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

## #33 · 2026-08-13 · Fase 3 paso 4b: la disponibilidad sale de la UI, y lleva la cesta
Segunda unidad del paso 4. Nace `Booking\Contracts\AvailabilityOffer` (+`OfferedDate`/`OfferedTime`)
con `AvailabilityReader` detrás, y encima `GET availability/{product}/dates` y
`POST availability/{product}/times`. La web consume el contrato desde el mismo commit. Detalle en
`api-v1.md` §10.nonies.

**(a) Lo que se extrajo no eran las reglas de oferta —esas ya vivían bien en `SlotOffer`
(`AFORO-02`)— sino la derivación de la CESTA a ocupantes provisionales**, que estaba dentro de
`Livewire\Tickets\Purchase`. Es la pieza que decide si una hora se puede vender: sin ella, una
segunda línea sobre la misma franja ve libres las plazas que la primera ya retiene. Cualquier otro
cliente habría tenido que reescribirla, y una copia que cuente distinto ofrece horas que el checkout
rechaza.

**(b) Las horas van por `POST` con la cesta; las fechas por `GET` sin ella.** No es una rareza REST:
la cesta —líneas con complementos anidados— no cabe con garantías en una URL, y el endpoint no
cambia nada del servidor. Las fechas no dependen de la cesta (un día se ofrece si tiene franjas), y
hacerlas depender obligaría a evaluar el cupo de todas las horas de todos los días del horizonte
para pintar un calendario. La cesta es **opcional**: la primera compra empieza sin nada elegido.

**(c) El hallazgo: la fuente única calculaba el número correcto y publicaba el otro.**
`offerableTimes()` devolvía un `available` que en un PACK son las plazas del cupo **sin topar** por
el máximo del pack, mientras que lo CONTRATABLE sí está topado — y ese segundo número lo calculaba
ahí dentro y lo descartaba, obligando a la web a recomputarlo. Medido en la instalación de
demostración: `available: 60`, `max_quantity: 20`. Un cliente que hubiera acotado su selector con el
primero habría dejado pedir 60 invitados que el checkout rechaza. Ahora la fuente publica los dos y
el contrato los separa con su porqué.

**(d) La tarifa por día se resuelve en LOTE, sin duplicar la regla.** El presupuesto por pendiente
destapó que `RateResolver::for()` consulta `special_dates` en cada llamada: un calendario costaba
una consulta por celda. Se añadió `RateResolver::forDates()` **en la misma clase que tiene la
regla**, con `for()` delegando en él; copiar la resolución dentro del read-model habría creado dos
precios posibles para el mismo día.

**(e) De regalo, `RateResolver` salió entero de la capa de UI.** Al mover el calendario al contrato,
su único uso restante era `dayPriceCents()`, que resolvía la tarifa del día elegido por su cuenta y
podía no coincidir con el precio pintado en su propia celda. Ahora lee de la misma oferta.
`Purchase` ya no importa ningún servicio de precio ni de aforo: solo contratos.

**(f) `SlotOffer` entra en el `CRITICAL_RE` del `pre-push`.** `CriticalPathGateTest` ya lo trataba
como núcleo —un controlador de API que lo tocara debía casar con el patrón— pero el fichero en sí
quedaba fuera: se podía cambiar la fuente única de oferta y empujar sin correr un verificador. Y sí
importa, porque `OrderCreator` llama a `SlotOffer::passesIntradayFloor()` como backstop del corte
intra-día.

**(g) Renombre en el contrato público**: `QuoteRequestItem`/`QuoteRequestAddon` pasan a `CartLine`/
`CartLineAddon`, porque desde este paso los comparten el presupuesto y la disponibilidad (y en 4c,
la creación del pedido). `v1` es evolutiva hasta Fase 6 y no hay ningún cliente escrito todavía; el
nombre correcto ahora es más barato que arrastrar uno que miente.

**Verificación empírica**: suite **2404 verde** (9211 aserciones, `--parallel` ~62 s) · Pint limpio ·
`docs-check` verde · **contrato verificado por mutación** (renombrar `max_quantity` en el Resource
deja el test del endpoint en rojo con «The required properties (max_quantity) are missing») · **los
dos verificadores de concurrencia VERDES sobre MySQL real** con 16 workers, corridos después de
tocar `SlotOffer` y `RateResolver` (1 compra + 15 `sold_out`; 1 `authorized` + 15 `idempotent_paid`)
· **paridad web↔API con cesta NO vacía y su mutación** en la suite · endpoints ejercidos **contra el
servidor real con `curl`**: 14 días con su tarifa (el sábado sale `special` a 11,90 €), 40 plazas sin
cesta y **35 con una línea de 5 en esa franja** —y las demás intactas—, el pack publicando 60 y 20, y
404 tanto para un id inexistente como para uno no numérico.

## #34 · 2026-08-13 · Fase 3 paso 4c: crear el pedido y abrir el cobro por API
Tercera unidad del paso 4. Nacen `POST orders`, `GET orders/{code}` y `POST orders/{code}/payment`
sobre las tres piezas que ya existían —`ReservationAdmission`, `OrderCreator` y `PaymentInitiator`—,
más los **códigos de error de negocio** del contrato público. Detalle en `api-v1.md` §10.decies.

**(a) A diferencia de 4a y 4b, aquí no se extrae nada: se ORQUESTA.** Y por eso es el trozo de más
riesgo, porque una secuencia no la protege ninguna guarda de arquitectura — un controlador que llama
a los tres servicios correctos en el orden equivocado pasa `ApiBoundariesTest` igual. La red son
cuatro tests, uno por punto del orden: la admisión va antes y **consume** ficha; el pedido nace
**siempre** con su ventana de retención (`AFORO-10`); el cobro se abre sobre el pedido ya
persistido; y un cobro que no abre suelta el pedido en el primer intento y **no** lo toca en un
reintento. Se comprobó que muerden mutando el controlador: las tres mutaciones dejan tests en rojo.

**(b) La orquestación se queda en la capa de entrega, y no es pereza.** Extraerla a un servicio de
dominio cruzaría Booking → Payments con una flecha de ORQUESTACIÓN, y la baseline de
`ModuleBoundariesTest` **solo encoge**: añadirle una entrada es la señal de que algo está mal hecho.
La capa de entrega es el *composition root* declarado en Fase 2 · paso 3. El arreglo de fondo ya
tiene nombre en el backlog de la fase —la abstracción `PaymentProvider`, que convertiría la ida del
pago en un contrato como el del reembolso— y meterla aquí habría mezclado dos trabajos en un diff.
Queda anotado en `DEUDA.md` con este razonamiento.

**(c) Doce códigos de error de negocio, y no uno genérico.** `ReservationException` lanza doce claves
distintas; agruparlas habría sido cómodo hoy e incompatible mañana, porque **partir un código
existente rompe a todo cliente ramificado sobre él** mientras que añadir uno es evolutivo. El mapa
clave-i18n → código (`Http\Api\ReservationErrorMap`) es la indirección que el spec §4.3 pedía —el
contrato público no puede ser una clave de `lang/`— y es **exhaustivo por test**: `ReservationErrorMapTest`
lee el dominio con el tokenizador y falla si alguien lanza un motivo sin mapear, y también si el
mapa traduce uno que ya nadie lanza.

**(d) Statuses**: 422 para todo rechazo de cesta (petición bien formada, cesta no vendible; la
precisión la lleva el `code`), **409** para lo que impide reservar y no depende de los datos
—pausa del operador, tope de pendientes, pedido no reintentable—, **429** para el límite de
frecuencia (es lo único que se arregla esperando) y **502** para «la pasarela no abrió el cobro».

**(e) El mismo 502 tiene dos consecuencias opuestas, y el contrato las escribe.** En un primer cobro
el pedido **se suelta** (no puede retener una plaza que nadie va a pagar) y hay que empezar de nuevo;
en un reintento el pedido **sigue vivo** con su hold recién extendido y basta con reintentar. Es la
misma asimetría que el paso 2 dejó decidida en el dominio.

**(f) `PaymentTicket::formData` no era lo que su nombre decía.** Su docblock afirmaba ser «el
conjunto de campos `<input>` de la pasarela» y en realidad es el payload crudo del proveedor, con la
URL dentro y claves que no son los nombres de los campos. Esa traducción solo vivía en las
plantillas Blade, y un cliente de API no tiene plantilla donde mirarla: ahora la dicen
`gatewayUrl()` y `gatewayFields()`. **El contrato NO enumera los campos**: los declara como un mapa
opaco que el cliente reenvía sin tocar —van firmados— y eso es lo que permitirá cambiar de proveedor
sin romper a nadie.

**(g) `GET orders/{code}` entra en este paso** aunque el spec lo listara suelto: sin él, un cliente
que pierde la respuesta de la creación solo puede recuperar su pedido paginando `me/orders`. Un
código ajeno responde **404**, no 403 — decir «existe pero no es tuyo» sería un oráculo de códigos.

**Verificación empírica**: suite **2424 verde** (9340 aserciones, `--parallel` ~63 s) · Pint limpio ·
`docs-check` verde · **contrato y secuencia verificados POR MUTACIÓN** (quitar el hold → 3 tests en
rojo; no soltar el pedido → 1; usar la admisión que no consume → 1; quitar una entrada del mapa de
errores → el test de exhaustividad lo nombra) · **los dos verificadores de concurrencia VERDES sobre
MySQL real** con 16 workers · **flujo completo ejercido contra el servidor real con `curl` y tarro de
cookies**: `csrf-cookie` → `login` (200) → `POST orders` (**201**, pedido `R-ASPXEW` pendiente, 19,80 €,
con `expires_at` y formulario firmado de Redsys) → `GET orders/{code}` (200) → `POST orders/{code}/payment`
(200, **retención extendida** y firma nueva) → 999 plazas (**422 `line_sold_out`** con
`params.product` y `params.when`) → código inventado (404 en el GET, **409 `order_not_retryable`** en
el reintento) → anónimo (401). El pedido de prueba se borró de la BD de dev.

## #35 · 2026-08-13 · Fase 3 paso 4d: el desenlace del pago (y el token que se quemaba solo)
Cierra el paso 4. Nace `GET orders/{code}/payment-status`, se arregla el consumo del token de la
vuelta y se DECLARA cómo averigua el desenlace un cliente nativo. Detalle en `api-v1.md`
§10.undecies.

**(a) Dos ejes de estado, porque uno no distingue «rechazado» de «nadie lo ha intentado».** Mirando
solo a `Order.status`, un pedido con la tarjeta denegada y uno que nunca llegó a la pasarela son
idénticos: `pending`. Ese matiz vivía solo en la SESIÓN de la web (`purchase.failed_code`), así que
la API habría dicho «pendiente» durante toda la retención y luego «caducado» —**nunca
«reintenta»**—, teniendo el reintento disponible desde 4c. Ahora `order_status` dice qué ha sido de
la reserva y `payment_status` qué ha sido del último intento.

**(b) El motivo del rechazo se publica como CÓDIGO y como texto.** El código (`card_expired`,
`bank_denied`, `user_cancelled`…) es lo que el cliente programa; el texto, lo que muestra. Es la
misma distinción que el sobre de error hace entre `code` y `message`, y por el mismo motivo:
con solo el texto habría que comparar cadenas traducidas.

**(c) Un rechazo anterior deja de anunciarse en cuanto hay otro cobro en curso.** La regla vive en
el dominio (`Order::declinedResponseCode()` solo responde si el ÚLTIMO intento es el fallido), no en
el serializador: es una decisión sobre qué es verdad, no sobre cómo se pinta.

**(d) El token de la vuelta se MIRA antes de consumirse.** Se leía con `Cache::pull` antes de validar
la titularidad, buscando que uno capturado no fuera reutilizable. Pero el token va atado a su
`user_id`: un tercero **nunca pudo usarlo, solo QUEMARLO** — bastaba abrir la URL de la vuelta sin
sesión para que el cliente legítimo perdiera su confirmación y se encontrara el carrito vacío tras
haber pagado. Ahora se lee, se valida y solo entonces se consume; la ventana de reutilización sigue
cerrada (el `pull` es atómico, TTL 5 min). Verificado por mutación.

**(e) El retorno móvil se resuelve DECLARÁNDOLO, no construyéndolo.** La vuelta de la pasarela es una
redirección de navegador y `DS_MERCHANT_URLOK` no admite parámetros donde colgar un `state`: hoy no
hay deep link que disparar. En vez de inventar una página puente para un lector que no existe hasta
Fase 6, la decisión es explícita y viaja **en el propio contrato OpenAPI**, que es donde la leerá
quien construya la app: el cliente nativo abre la pasarela en un navegador del sistema y **averigua
el desenlace sondeando**. ⚠️ Con una condición que es **prerequisito DURO de instalación**: sin
`redsys_merchant_url` (notificación server-to-server) y con terminal data-less, el único camino a
`paid` es la vuelta del navegador —que en nativo no ocurre— y el pedido caducaría con la tarjeta ya
cobrada (`PAY-02`). Escrito también en `sistemas/REDSYS.md` §8.

**(f) `PAY-01` intacto**: sondear no confirma. El único autorizado a pasar una `Order` a `paid` sigue
siendo `RedsysReturnHandler` con la firma delante; hay test de que consultar en bucle no transiciona
nada ni abre cobros.

**Verificación empírica**: suite **2438 verde** (9419 aserciones, `--parallel` ~63 s) · Pint limpio ·
`docs-check` verde · **verificado por mutación** (volver a consumir el token antes de validar deja en
rojo el test del dueño) · **los dos verificadores de concurrencia VERDES sobre MySQL real** con 16
workers · **ciclo completo del desenlace ejercido contra el servidor real con `curl`**: crear pedido
(201, 11,90 € — tarifa de sábado) → sondeo `pending/pending` sin motivo → marcar el cobro rechazado
con `Ds_Response 0101` → **el MISMO endpoint devuelve `pending/failed`, `declined_reason: card_expired`,
`can_be_retried: true`** y el mensaje traducido en es/en → reintento (200, retención extendida) →
sondeo `pending/pending` con el motivo anterior ya oculto. `Cache-Control: no-store` presente
(`RGPD-04`). El pedido de prueba se borró de la BD de dev.

## #36 · 2026-08-13 · Fase 3 paso 5: el post-form por API, y cómo se autentica un portador de firma
Último paso del corte de la fase (spec §9). `GET`/`PUT reservations/{id}/guest-form`, el **segundo
consumidor** de la API. Detalle en `api-v1.md` §10.duodecies.

**(a) El problema real no era el endpoint: era la CREDENCIAL.** Por eso este consumidor se eligió
(spec §3c). La vía sin sesión del post-form es una URL firmada, y **la firma de Laravel cubre la URL
exacta**: reenviar la `signature` del enlace del correo a `/api/v1/...` simplemente no valida.
Verificado contra el servidor real: **la misma firma da 403 en la API y 200 en su ruta web**.

**(b) El canje: firmar también la URL de la API, con la MISMA caducidad.** No hace falta un almacén
de credenciales nuevo ni un emisor de tokens (que sigue aplazado a Fase 6, `DECISIONES #29`):
`OrderItem::guestFormApiUrls()` firma las rutas de la API con la expiración del enlace del correo
—fecha del evento + 14 días, `RGPD-03`— y se la entrega a quien ya demostró acceso. El `GET`
devuelve además la URL de guardar, así que un cliente solo necesita UNA URL firmada para completar
el flujo. Es el mismo modelo que la web ya usaba (su formulario POSTea a una ruta firmada con esa
misma expiración), portado sin relajar nada: mismo alcance, misma vida, misma prueba.

**(c) La escalada 403 → 410 → 404 pasa a tener un solo sitio.** Vivía en el controlador web y la API
habría sido la segunda copia. **Lo que se comparte no son tres líneas: es el ORDEN**, que aquí es
una propiedad de seguridad — autorizar ANTES de comprobar elegibilidad es lo que impide deducir por
el código de estado si una reserva existe, si está pagada o si su titular ejerció la supresión.
Ahora es `Http\Concerns\AuthorizesGuestForm`, y hay test por MUTACIÓN de que invertir el orden cae.

**(d) La API resuelve la reserva A MANO, sin *route model binding*.** Con binding implícito un id
inexistente daría 404 ANTES de la autorización, y ese 404 —frente al 403 de uno existente— sería un
oráculo de reservas. Verificado: id existente e id inventado responden **el mismo 403** sin firma.

**(e) La persistencia baja al dominio** (`OrderItem::submitGuestForm()`). Lo forzó la guarda de
frontera —`ApiBoundariesTest` prohíbe escribir modelos desde un controlador de API— y es lo
correcto: quien decide qué se persiste de un formulario con datos de MENORES no puede ser la capa
HTTP. La web consume el mismo método desde este commit, así que el saneado contra el esquema, la
mezcla que preserva los datos de la fase de reserva, el sello de completado y el rastro sin PII ya
no pueden divergir entre superficies.

**(f) El formulario viaja con su ESQUEMA.** Las columnas por invitado las configura cada instalación
(`guest_fields`), así que un cliente que las llevara quemadas dejaría de funcionar en cuanto alguien
añadiera una. Van con la etiqueta ya traducida y su `required`. Y se devuelven solo los campos
generales de la fase `postform`: los de la reserva no los edita este formulario.

**(g) `no-store` explícito en la ruta** (`RGPD-04`). La respuesta lleva nombres y alergias de menores
(art. 9) y la ruta es accesible SIN sesión, así que el `no-store` por defecto de la superficie
autenticada no la cubriría. **409 `guest_form_closed`** cuando la fiesta ya se celebró: el permiso no
ha cambiado, ha cambiado el momento.

**Verificación empírica**: suite **2455 verde** (9508 aserciones, `--parallel` ~63 s) · Pint limpio ·
`docs-check` verde · **verificado por MUTACIÓN** (invertir la escalada deja en rojo el test de
no-enumeración) · **flujo completo contra el servidor real con `curl`**: el enlace firmado de la API
abre el formulario **sin cuenta ninguna** (esquema real de 4 columnas por invitado + 2 generales,
`no-store` presente) → se guarda con la `save_url` que él mismo entrega (3 fichas, estado `ok`, y la
clave inventada descartada por el saneado) → sin firma da 403, y un id inexistente da **el mismo
403** → **la firma del enlace web da 403 en la API y 200 en su ruta web**, que es exactamente el
motivo del canje. La reserva de prueba se borró de la BD de dev.

---

## #37 · 2026-08-13 · Cierre de Fase 3: la secuencia del dinero baja al dominio (y el driver espera)

**Decisión del owner**: la abstracción `PaymentProvider` que quedaba abierta en `00-REFACTOR` se
**parte en dos** y se hace ahora solo la mitad MEDIDA — la orquestación—, dejando el segundo driver
de pasarela para Fase 6, con la app, que es su primer lector real. El argumento que decidió: la
duplicación estaba medida (cinco puntos de llamada en cuatro clases de entrega) mientras que la
forma del enchufe de driver, con un solo driver existente, es especulación.

Diseño en `docs/specs/checkout-orquestado.md` (v2, aprobado tras revisión adversarial de 3 agentes).

**(a) El bloqueo de `#34b` era real y tenía fecha de caducidad.** Aquel paso razonó que extraer
«admitir → crear → abrir cobro» al dominio cruzaría Booking → Payments con una flecha de
ORQUESTACIÓN y que la baseline de `ModuleBoundariesTest` solo encoge. Correcto **mientras no
existiera el contrato de la ida**: el grafo ya sanciona `Booking → Payments\Contracts` —lo abrió el
`RefundGateway` del paso 1 de Fase 2, que en su propio docblock se declara «la semilla» de esto—.
Creado el puerto, la flecha deja de ser una excepción y pasa a ser el canal previsto. **Cero
entradas nuevas en cualquier baseline**, que era el criterio rector del diseño y se verificó
publicando el diff (vacío).

**(b) El puerto vive en el módulo cuyos tipos habla — regla nueva, y sale de un dato.** Lo simétrico
habría sido poner la ida en `Payments\Contracts`, junto a `RefundGateway`. No se puede: su firma
habla de `Booking\Models\Order` y un contrato de Payments que nombre `Order` exige entrada en
`SEAM`. `RefundGateway` habla de `Payment`, un tipo del propio Payments, y por eso sí puede vivir
allí. Así que `Booking\Contracts\PaymentInitiation` es un **puerto REQUERIDO** —lo que Booking
necesita— en una carpeta donde todo lo demás es OFRECIDO, y lo implementa Payments. Se marca como
tal en su docblock y el bind vive en `PaymentsServiceProvider`, con un comentario recíproco en el
de Booking para que nadie lo dé por no atado.

**(c) La revisión encontró DOS afirmaciones falsas que hacían el spec inaplicable**, las dos
unánimes entre los tres revisores. La v1 se habría estrellado al primer test:
· `PaymentInitiationException` vivía en `Payments\Exceptions`, así que el `catch` del orquestador
  —el disparador de la compensación— era una flecha prohibida. Las alternativas eran peores
  (capturar `Throwable` compensaría fallos que no son de la pasarela; añadir a `SEAM` viola el
  criterio rector), así que la excepción se mudó a `Contracts`: es lo que lanza el puerto, luego es
  parte de su contrato. Precedente en la misma carpeta, `PaymentTicket`.
· Las constantes `SOURCE_*` eran de `PaymentInitiator` y las nombraban las cinco superficies, así
  que la ida seguiría citada desde la entrega hiciera lo que hiciera. Subieron a
  `ReservationCheckout`, que es el símbolo que la entrega consume; el initiator las conserva como
  ALIAS para no tocar los tests que las usan por ese nombre. Los literales no cambian: viajan a
  `audit_logs` y son lo que la operadora lee (`PAY-05`).
De regalo, la revisión también destapó que **el propio spec rompía el gate documental** (cita por
línea + una entrada de `DECISIONES` que aún no existía). Se arregló antes de escribir código.

**(d) El orquestador NO abre transacción, y eso es una regla escrita, no un olvido.** Envolver la
secuencia tendría dos consecuencias silenciosas: el `lockForUpdate` de `lockSlots()` quedaría
sostenido durante la firma del payload —contención en el punto que `AFORO-01` llama «el más
sutil»— y el rastro `orders.payment_init_failed` haría rollback junto con la compensación, que es
justo lo que `PAY-05` existe para impedir. Tiene test propio: si alguien envuelve la secuencia, la
incidencia desaparece y el test cae.

**(e) La ventana de retención la POSEE el dominio, y colocarla costó pensar.** Recibirla por
parámetro habría sido lo cómodo, pero deja `AFORO-10` en manos del llamante —una superficie futura
podría pasar `now()->addYears(1)`—. Leerla desde el orquestador exigía `PaymentSettings`, que es de
Payments: la quinta entrada `SEAM` de la misma config. La salida fue `OrderCreator::
checkoutHoldUntil()`: esa clase **ya** importa `PaymentSettings` con su costura declarada, y ya
tenía un helper hermano (`verificationHoldUntil()`). Cero flechas nuevas, y de paso muere el
`now()->addMinutes(...)` que estaba escrito a mano en las dos superficies que creaban pedidos.
⚠️ Alcanza al checkout, no a toda creación: el pedido manual del panel nace FIRME a propósito, y
por eso el default `?Carbon $hold = null` se conserva.

**(f) Los criterios de éxito dejaron de ser greps.** La v1 medía «nadie escribe la secuencia» con
`grep` → 0, que es exactamente el error que el trabajo denuncia: se cumple el día del commit y
caduca al siguiente. Peor aún, tras el refactor una sexta superficie tendría un camino MÁS cómodo
—inyectar el puerto directamente en un controlador— y ninguna guarda lo vería, porque
`ModuleBoundariesTest` exime la capa de entrega entera y permite cualquier `Contracts`. Nace
`CheckoutSequenceTest`: fuera de `app/Domain` nadie nombra la ida ni llama a `createPendingOrder`/
`releaseAfterFailedPaymentStart`, con **una** excepción con nombre —el verificador de concurrencia,
que llama a `OrderCreator` a propósito porque lo que mide es la carrera de `lockSlots()` y pasar por
el orquestador cambiaría lo que la prueba mide—.

**(g) Verificación.** Suite **2467 verde** (9567 aserciones) sin cambiar **ni una aserción** de los
tests de las cuatro superficies · las **5 mutaciones del orden comprobadas** (quitar el hold: 4
rojos · `admitReservation`→`mayReserve`: 3 · borrar la compensación: 3 · añadirla al reintento: 2 ·
invertir admitir↔reabrir: 2) · los dos verificadores de concurrencia sobre MySQL con 16 workers ·
ciclo completo por `curl` contra el servidor: crear (201, con `expires_at` puesto), reintentar (200,
hold extendido y el intento previo `superseded` **comprobado en BD**), `payment-status` coherente, y
el reintento de la web devolviendo su formulario firmado. El pedido de prueba se liberó.

---

## #38 · 2026-08-13 · Fase 4: alcance del sidebar SPA, modelo de tema y las seis decisiones del owner

Apertura de Fase 4. Diseño en `docs/specs/sidebar-spa.md` (**v3**), escrito, revisado
adversarialmente por 3 agentes —que declararon la v1 **INSUFICIENTE**— y luego rediseñado hueco a
hueco por 5 agentes más con una revisión de coherencia. Aquí quedan las decisiones; el porqué
detallado y lo medido, en el spec.

**(a) Alcance: SOLO el cajón del sidebar.** `/mi-cuenta` y `/mi-cuenta/pedidos` siguen en Blade.
Lo que la fase retira es `Purchase.php`, que es su objetivo declarado; reescribir además unas
páginas que hoy funcionan y no son deuda solo añade superficie que reprobar.

**(b) Tema: tokens + una hoja de estilos POR INSTALACIÓN.** Mismo HTML, CSS potencialmente muy
distinto por cliente. La consecuencia arquitectónica es la que gobierna toda la fase: **lo que
emite el sidebar es un contrato público**, igual que `openapi/v1.yaml` lo es para la API. Eso
descarta CSS-in-JS, estilos *scoped* y utilidades dentro del cajón.
⚠️ Y al medirlo, la premisa de la v1 resultó falsa: **el contrato no son las clases, es el ÁRBOL**
— 90 de 292 selectores son estructurales o dependen del tipo de elemento, así que un `<div>` donde
había un `<button>` pierde el estilo con el contrato de clases cumplido al 100%. Se verifica por
diff de DOM renderizado entre los dos motores, no extrayendo `class=` del código fuente.

**(c) Dependencias: Vue 3 + Pinia** (`CONVENCIONES §9.3`). La recomendación inicial era Vue sin
Pinia; el owner aportó el requisito que faltaba —el cajón hospedará **tres dominios** (compra,
cuenta y gestión de entradas) que comparten sesión y saltan entre sí—, y con tres dominios los
stores separados dejan de ser ceremonia. ⚠️ Queda dicho para que nadie lo confunda: **Pinia no
resuelve el salto entre estados**; eso es diseño de máquina de estados e intención pendiente.

**(d) La cesta se persiste SIN `event_data` (RGPD).** Hoy vive en la sesión del servidor y por eso
sobrevive a irse a leer el correo de verificación; en la SPA vivirá en el navegador. Medido: los
campos de etapa *booking* del pack sembrado son **nombre del homenajeado (un menor), su edad y
«Notas (alergias…)»** — dato de salud, art. 9. Dejarlos en `localStorage` los pone fuera del
alcance de `User::anonymize()` (`RGPD-01`), sin caducidad y en un dispositivo que puede ser
compartido. Al restaurar, las líneas de pack piden esos campos otra vez: es la **única desviación
consciente de la paridad** de toda la fase.
⚠️ Con la misma decisión aparece una regresión de seguridad que la v1 no vio: `localStorage` no
pertenece a ninguna sesión, así que la cesta persistida guarda el id de su titular y el store de
auth la purga al cambiar de identidad — sin eso, la cesta de Alice sobreviviría al login de Bob en
la tablet del parque, que es una defensa que **hoy existe**.

**(e) Tokenizar `site.css` entra en Fase 4, no en Fase 5.** Es el único momento en que alguien
recorre ese CSS nodo a nodo; hacerlo después, con el marcado ya en Vue, obliga a una **segunda**
verificación completa de paridad visual — el mismo coste con el que se descartó reescribir el CSS.
⚠️ Y medirlo bien cambió el tamaño del problema: el «75% quemado» incluye ESTRUCTURA (`display`,
`flex-direction`) que no debe ser token nunca. De las 1.084 declaraciones del sidebar, las
tematizables son 663 y estaban al **43%**. Ese es el número honesto y el que mide el presupuesto.

**(f) El sexto hueco será un ENDPOINT, no una regla transcrita al cliente.** Hoy el servidor valida
los campos del pack al añadir a la cesta; con la cesta en el navegador no queda ningún ida y vuelta.
Copiar `missingRequiredEventFields` y su saneador a la SPA es una segunda implementación de una
regla de servidor —deuda por definición— y de las caras: `sanitizeAnswerValue()` aplica
`preg_replace('/\D+/','')` a los campos `number`, así que la EDAD contestada «cinco» el servidor la
ve **vacía** y cualquier validación ingenua en el cliente la ve contestada. No lo encuentra ninguna
revisión de código, solo un cliente enfadado.

**(g) Dos afirmaciones del spec que eran FALSAS, corregidas contra el código antes de implementar
nada**: los CTA del aviso de pausa **no son una cascada** (teléfono y WhatsApp se pintan a la vez;
`/contacto` solo si faltan los dos), y la resolución de complementos **no depende de la fecha de la
línea** (los cuatro llamantes de producción pasan `Carbon::today()`). Implementar cualquiera de las
dos como estaba escrito habría sido una regresión funcional silenciosa.

## #39 · 2026-08-14 · Las respuestas del pack salen del pedido: endpoint aparte y solo la fase `booking`
Cierre del hueco 4 de Fase 4 · paso 4.0b (`sidebar-spa.md` §4.4.1 y §4.4.6). Lo que el cliente
contesta al reservar un pack —en la instalación sembrada, **el nombre de un MENOR, su edad y sus
alergias**— se publica en `GET /orders/{code}/event-data`, no como campos de `OrderItem`.

**(a) Endpoint aparte, porque el pedido se LISTA.** Como campo de `OrderItem` las respuestas
viajarían en cada página de `me/orders`, que es una lista paginada de hasta 50 pedidos que se pide
para ver el historial, no para leer alergias de niños. Con un endpoint, pedirlas es un acto
explícito. ⚠️ **La guarda que sostiene la decisión no es la del endpoint nuevo, es la de los otros
dos**: `me/orders` y `GET orders/{code}` no las llevan nunca, comprobado sobre el CUERPO ENTERO de
la respuesta —no campo a campo—, para que renombrar el dato no esquive la prueba. Añadirlas a
`OrderItemResource` sería una línea y ahorraría una petición: por eso la prohibición es ejecutable
y no una nota.

**(b) Solo la fase `booking`.** `event_data` guarda juntas las respuestas de las dos fases, y las
del post-form ya tienen endpoint propio —que además se abre con FIRMA—, con la simetría escrita al
revés en `GuestFormResource::generalAnswers()`. Publicarlas también aquí sería un segundo camino
hacia el mismo dato del art. 9. **No hay pérdida de paridad medible**: al paso 6 del sidebar solo se
llega volviendo de la pasarela, y ahí `event_data` solo tiene respuestas de `booking` (`OrderCreator`
las filtra al persistir; las de post-form llegan semanas después). ⚠️ Sí cambia la conducta en un
caso: si un operador rellena campos de post-form desde el panel, ese dato no sale por aquí. Aceptado.

**(c) Devuelve la PII y NADA MÁS.** Ni nombre de producto, ni fecha, ni importes: eso ya lo sirve
`GET orders/{code}` y el cliente empareja por `reservation_id`. Repetirlo invitaría a usar **este**
endpoint —el que devuelve datos de un menor— para pintar el resumen entero. Las reservas sin
respuestas sí aparecen con la lista vacía: distinguir «este pack no pedía nada» de «esta línea no
vino» es lo que permite al cliente saber si la petición cubrió su pedido.

**(d) Emparejar respuesta con etiqueta baja al dominio.** La composición vivía copiada en
`Purchase::resolveEventData()` y en `ReservationSlip::eventDataRows()`, y el endpoint iba a ser la
tercera. Ahora es `TicketType::eventAnswers()`, hermana de firma de `sanitizeEventData()` y
`missingRequiredEventFields()`. La del panel **no se unificó a propósito** —enseña las claves
huérfanas, que el operador necesita ver, y una respuesta huérfana no tiene `stage` que filtrar—:
queda anotada en `DEUDA.md` en vez de resuelta a medias dentro de un paso de API.

## #40 · 2026-08-14 · Validar una línea antes de la cesta: contrato de dominio, y la web lo consume
Ejecución de `#38(f)` (Fase 4 · paso 4.0b·6, `sidebar-spa.md` §4.4.2). Nace
`POST /api/v1/cart/validate-line` y, debajo, `Booking\Contracts\CartLineValidation`.

**(a) La regla baja al DOMINIO, no se copia a un controlador.** Lo que decide si una línea entra en
la cesta —producto elegible, franja ofrecida, cantidad, tope de líneas, campos obligatorios del
pack— vivía en el cuerpo de `Livewire\Tickets\Purchase::addToCart()`, es decir en una clase de
interfaz. Escribirlo otra vez en un endpoint habría sido la segunda copia; escribirlo en JavaScript,
la tercera. **Y la compra web lo consume**: `addToCart()` ya no decide, pide el veredicto y traduce
el «no» a lo que enseña (error por campo + resumen que nombra lo que falta). Esa delegación es lo
que convierte «fuente única» en algo comprobable, con la suite de la web de testigo.

**(b) Hacer que la web delegara encontró un fallo que ninguna revisión habría visto.**
`Cart::sanitize()` fuerza `max(1, qty)` —correcto para una cesta guardada, donde una cantidad 0 es
corrupción—. Aplicado a una línea CANDIDATA convertía «todavía no he elegido cuántos» en un 1, y la
web habría añadido una entrada que nadie pidió. La candidata tiene por eso su propia normalización.
Lo cazó `PurchasePanelTest` al primer intento: es el argumento entero a favor de que la superficie
vieja consuma la extracción en el mismo paso, en vez de dejarla «para después».

**(c) La franja se comprueba contra la OFERTA, no contra el aforo.** La web no lo necesitaba —su
hora venía siempre de `availableTimes()`—, pero un cliente de API puede enviar cualquiera, y
`AvailabilityOffer::maxQuantity()` responde de una franja concreta **aunque no se ofrezca**: no mira
día pasado, corte intradía, ventana del producto ni antelación mínima. Validar solo por aforo habría
dado por buena una línea que `OrderCreator` rechaza después. Un validador que miente es peor que no
tenerlo. Se consulta `times()` una sola vez, que ya trae el `max_quantity` de cada hora.

**(d) 200 aunque la línea no sirva.** El veredicto va en el cuerpo (`valid` + `problems`), como en
`GET me/reservation-eligibility`: preguntar «¿puedo?» y que te digan «no, y por esto» no es un error
de la petición. La FORMA sigue siendo 422 y la decide `CartPayload`, que ahora expone también las
reglas de UNA línea para que no nazca una segunda idea de qué es una línea.

**(e) Los `problems` viajan SIN contexto.** El dominio adjunta los datos para componer el aviso
—mínimo, tope, etiqueta del campo— porque cualquier consumidor puede necesitarlos, y el sidebar los
usa. La API no los republica: `GET catalog/products/{id}` ya da `min_quantity` y los `event_fields`
con su etiqueta, y `GET config` da `cart_max_lines`. Reenviarlos sería un segundo sitio del que leer
el mismo número y —al ser un mapa libre— obligaría a relajar `additionalProperties: false` justo en
el esquema más nuevo.

**(f) Lo que NO se cambió, a propósito**: el tope de cesta se aplica aunque la línea fuese a
fundirse con otra, igual que en la web. Eximir la fusión parece más fino —la cesta no crece— pero es
un cambio de conducta en una defensa anti-abuso (`PAY-12`), y colarlo dentro de una extracción es
justo lo que este proyecto no hace (mismo criterio que la divergencia de `contact.phone`).

## #41 · 2026-08-14 · Los complementos resueltos: publicar la regla, con el dinero en la misma respuesta
Cierre del hueco 5 —y de los seis— de Fase 4 · paso 4.0b (`sidebar-spa.md` §4.4.1 y §4.4.3). Nace
`POST /api/v1/catalog/products/{product}/addons` sobre `Booking\Contracts\AddonOffer`.

**(a) Publicar, no extraer — y por eso la web NO se tocó.** Era el hueco declarado «más arriesgado»
porque cambia la semántica de `AddonResolver::viewModel()`, consumido por dos superficies vivas (la
compra pública y el alta manual del panel). Al medirlo, el riesgo desaparecía: la regla **ya vivía en
el dominio** y las dos superficies **ya la comparten**, así que no había ninguna copia que unificar.
Lo que faltaba era exponerla con DTOs que un contrato público pueda publicar. Hacer pasar además al
sidebar por esos DTOs solo cambiaría el tipo de dato que consume la plantilla del paso con más clics
del embudo, sin retirar deuda: se dejó como estaba. ⚠️ **Es la decisión opuesta a la de `#40`, y por
la razón contraria**: allí la regla estaba dentro de un componente Livewire y no delegar habría sido
copiarla; aquí delegar no arregla nada y sí mueve una plantilla frágil.

**(b) El dinero de la línea viaja en la misma respuesta, y está MEDIDO.** Es la pantalla con más
clics (8–12 por configuración) y cada clic cambia el importe: con dos endpoints serían 16–24
peticiones contra un `throttle:api` de 60/min **compartido** con disponibilidad, catálogo y
presupuesto. Se midió antes de decidirlo: componer las dos cosas cuesta **las mismas consultas** que
pedirlas por separado (9 + 5 con 4 complementos), con la mitad de viajes y de fichas de límite.
`ApiOverheadTest::test_resolving_addons_pays_the_known_slope_and_no_more` fija esa pendiente.
⚠️ El importe **no se calcula aquí**: se pide a `CartPricing`, la misma implementación que sirve
`orders/quote`. `PAY-12` exige una sola fuente de CÁLCULO, no una sola URL.

**(c) Cierra un fallo silencioso por construcción.** `CartPricer::resolveAddons()` captura cualquier
error del resolutor y **tarifica la línea sin complementos**: el pie mostraría un total sin ellos
mientras las filas muestran sus importes. Aquí lo que se tarifica es la selección que el propio
dominio acaba de resolver —obligatorios inyectados, huérfanos podados—, así que ese `catch` no puede
dispararse por lo que mande el cliente. Verificado por mutación: pasarle la selección CRUDA reproduce
exactamente el fallo descrito.

**(d) Un grupo sin elegir usa su opción por defecto.** Un grupo excluyente sin miembro activo no es
un estado que exista, así que la primera llamada puede ir solo con `quantity` y ya devuelve el estado
inicial correcto. Eso evita un segundo método «dame los valores por defecto» y evita que un cliente
pinte la pantalla con todos los grupos vacíos y un total que no es el real.

**(e) `selection` viaja además de lo pintado.** Traducir lo que se enseña en lo que se guarda exige
saber qué miembro de cada grupo cuenta, inyectar los obligatorios que nadie marcó y podar los
dependientes huérfanos. Publicar la selección ya resuelta —en la forma exacta de los `addons` de una
línea de cesta— evita que cada cliente escriba su propia versión de esa regla.

**(f) Lo que la verificación destapó**: el test de la poda en cadena **pasaba igual con una poda de
un solo nivel**, porque el orden natural de los complementos ya la resolvía en una pasada. Se
invirtieron las posiciones para que exija el punto fijo de verdad. Lo encontró la mutación, no la
lectura — y es la clase de test verde que el proyecto considera peor que no tener test. Aparte, quedó
anotada en `DEUDA.md` una divergencia preexistente que el endpoint hereda: el formato de importe
(`2,00 €`) está quemado en español y no mira el locale, así que con `Accept-Language: en` el nombre
llega traducido y el precio no.

## #42 · 2026-08-14 · Las escalas del sidebar son múltiplos de una unidad, no una escala redondeada
Cierre de Fase 4 · paso 4.0c (2.ª mitad): tipografía y espaciado (`sidebar-spa.md` §4.3.bis).

**(a) NO se redondea a una escala canónica, y la medición lo decide.** El plan escrito decía
«decidir una escala» y avisaba de que redondear cambia el diseño. Medido antes de tocar nada: una
escala de 6–7 pasos movería el **52-55%** de los tamaños de letra del cajón (40 de 73 usos, 47px de
desviación) y una de espaciado en pares el 25%. Eso no es tokenizar: es **rediseñar**, y el spec §2
declara el rediseño visual **fuera de alcance** de la fase. La escala canónica queda como decisión de
producto abierta, ya con sus números.

**(b) La escala es MULTIPLICATIVA sobre una unidad**: `--fs-13: calc(var(--fs-unit) * 13)` con
`--fs-unit: 1px`. Con eso se consiguen las dos cosas a la vez que parecían incompatibles: **cero
píxeles movidos** —`calc(1px * 13)` es `13px`, no «casi»— y **un punto de control real**, que es lo
que el white-label pedía: una instalación que quiera el cajón un 15% más aireado cambia `--sp-unit`
y se mueve todo a la vez conservando las proporciones. Con literales por escalón habría que tocar
diecisiete. Y el nombre no miente cuando la unidad cambia: `--sp-12` son doce unidades.

**(c) Solo entran los valores que se REPITEN.** 12 escalones de tipografía y 17 de espaciado cubren
202 de los 210 literales; los ocho restantes aparecen una vez cada uno y se quedan literales. Un
valor único no forma parte de ninguna escala, y meterlo fingiría un sistema que el producto no tiene.
Resultado: tokenización de lo tematizable **49% → 75%**.

**(d) La verificación sustituye al «a ojo» que el plan asumía, y es más fuerte.** Como no se redondea
nada, la equivalencia se demuestra: revertir cada `var(--fs-N)`/`var(--sp-N)` a su literal devuelve
los dos ficheros **byte a byte idénticos** a los originales (190.668 y 95.055 bytes). Eso prueba que
la única diferencia introducida es la indirección.

**(e) ⚠️ Y la verificación destapó un fallo REAL que llevaba tiempo en producción.** Al parsear
`site.css` con PostCSS —para comprobar que ninguna declaración quedaba inválida— salió un error de
sintaxis **anterior** a este cambio: un comentario enumeraba tokens con comodines y una de esas
parejas asterisco-barra **cerraba el comentario a media frase**. El texto restante se leía como
selector, se pegaba al de la regla siguiente y el navegador **descartaba la regla entera**: era
`.gf-sr-only`, la que oculta visualmente el texto para lectores de pantalla en la hoja del post-form.
Efecto real: el aviso «has completado X de N fichas», pensado como `role="status"` solo accesible,
**se veía**, duplicando lo que el medidor de al lado ya decía. Se corrige aquí —restaura la conducta
que el propio Blade documenta, no cambia ningún diseño— y **viene con guarda**: ningún selector puede
contener un cierre de comentario ni ser prosa.

## #43 · 2026-08-14 · Cimientos de la SPA: el motor entra sin pesar en la landing
Fase 4 · paso 4.1 (`sidebar-spa.md` §4.7–§4.9). **Sin negocio**: dependencias, montaje, cliente HTTP,
máquina de estados y las redes que la fase necesita antes de transcribir un solo paso.

**(a) El motor se carga al ABRIR el cajón, no con la página.** Vue + Pinia + once pasos en el bundle
de todas las páginas públicas es un orden de magnitud sobre los 16 kB que la landing sirve hoy — y
**con el flag activo se enviarían los DOS motores**. El entry va tras un `import()` dinámico, como ya
hacía `html2canvas`. Medido: el enganche cuesta **medio kB** en la landing (15,8 → 16,3) y el motor
son 69 kB en su propio chunk. Montar al abrir cierra además el riesgo que sí toca `PERF-02`: una raíz
ávida pidiendo catálogo en cada carga añadiría una petición por visita en la ruta de más tráfico.

**(b) Nace el techo de bundle, que el repo no tenía.** `SidebarBundleBudgetTest` fija el peso del
entry y del chunk, y sobre todo comprueba que **el chunk sigue existiendo**: en cuanto alguien
escriba un `import` estático en `app.js`, Rollup lo funde con el entry y la landing engorda sin que
el diff lo enseñe. Verificado por mutación: con el import estático, el entry salta a 85 kB y caen
cuatro guardas.

**(c) La máquina de estados es un módulo JS PLANO, sin un solo `import` de Vue.** No es purismo: la
del sidebar Livewire tiene hoy seis ficheros de test detrás, y transcribirla sin red sería una
pérdida neta de cobertura (`CE-6`). Plana se prueba con `node --test`, que ya está disponible porque
Vite 8 exige Node 20+ — cero dependencias nuevas. Los componentes quedan sin test unitario **a
propósito**: son marcado, y de eso responde el diff de árbol. `npm run test:js` entra en el
`pre-push`, porque una red que no se ejecuta no es una red.

**(d) El flag `sidebar.engine` tiene fallback ASIMÉTRICO**: cualquier valor que no se reconozca cae a
`livewire`. Un typo en el panel no puede dejar la web sin la única superficie que vende, y el
fallback no puede ser el motor en obras.

**(e) Con la SPA, el que CONSUME el desenlace del pago es el layout.** El paso 4.0a dejó anotado que
el layout usa `peek()` porque el componente Livewire es `lazy` y su `mount()` corre en una petición
posterior; con la SPA el motor es ese mismo documento. `SidebarEntry::consume()` está memoizado por
petición, así que el `peek()` del `<body>` sigue viendo lo suyo y el cajón se auto-abre igual.

**(f) La i18n viaja en el montaje, no por endpoint** (§4.5): son 121 claves del grupo `tickets` ya
resueltas al pintar la página. Un endpoint sería una petición más en el arranque para algo que el
servidor acaba de calcular.

**(g) ⚠️ Dos fallos que encontró la verificación, no la lectura.** El primero, en una guarda propia:
`test_vue_never_travels_with_the_landing` buscaba `node_modules/vue/` y **pasaba sin mirar nada**,
porque un build de producción no conserva las rutas de origen — la cadena no estaba ni en el chunk
que sí lleva Vue. Ahora usa marcadores internos medidos contra el bundle real, y tiene **una guarda
de la guarda** que comprueba que esas firmas siguen existiendo. El segundo, en un test del flag:
comprobar los dos motores en un mismo caso fallaba porque **Livewire memoiza que ya emitió sus assets
y ese estado estático sobrevive entre peticiones del mismo test** — misma familia que `SUITE-02`. Se
separó en dos casos.

## #44 · 2026-08-14 · La paridad visual se demuestra con un diff de árbol, no con un screenshot
Fase 4 · paso 4.2 (primer tramo): nace `SidebarDomContractTest` y con él el **paso 1, el catálogo**.

**(a) El diff corre en el gate, sin navegador.** `CE-2` exige comparar el ÁRBOL renderizado de los
dos motores, no extraer `class=` del código —la v1 del spec daba por hecho lo segundo y se midió que
era falso: 90 de 292 selectores son estructurales o dependen del tipo de elemento—. La forma barata
de hacerlo es `@vue/server-renderer`, que **ya viene con Vue**: se renderiza el componente en Node y
se compara con lo que emite Livewire. Un navegador headless habría sido una dependencia pesada, lenta
y con su propio modo de fallo, para responder la misma pregunta.
⚠️ Node no carga `.vue` sin compilar, así que el renderizador se construye con Vite (`npm run
build:ssr`) y ese paso entra en el `pre-push`: sin él, el test que sostiene `CE-2` no puede correr.

**(b) Qué se normaliza y qué no, que es donde está el valor.** Fuera el andamiaje de cada motor
(`wire:*`, `x-*`, `@*`, `:*`, `data-v-*`); dentro la etiqueta, las clases, el anidamiento y los
atributos de accesibilidad. **No se desciende dentro de un `<svg>`**: su interior es geometría, y
exigir los mismos `<path>` convertiría un contrato visual en una copia literal de los iconos — que
HAYA un `<svg>` donde toca sí se comprueba, porque de eso dependen selectores como `.catalog__go svg`.

**(c) Un diff que normaliza de más pasa siempre**, así que el test trae **su propia guarda**: se
comprueba que el normalizador distingue un `<div>` de un `<button>` y detecta una clase que falta.
Verificado por mutación en el componente real: cambiar el `<button>` de la ficha por un `<div>`, o
renombrar `catalog__pricecol`, ponen el diff en rojo señalando la línea exacta.

**(d) El árbol se compara contra el view-model REAL del Livewire**, tomado del propio componente en
vez de escrito a mano en el test. Escribirlo a mano compararía Vue contra una idea del catálogo, no
contra el catálogo.

**(e) Lo que la paridad obligó a copiar y no se habría adivinado**: los iconos del sidebar son
componentes Blade que envuelven su SVG en un `<span class="icon …">`, y ese envoltorio es contrato
—`.catalog-acc__head span` lo mira—. El primer intento emitía el `<svg>` suelto: mismas clases en los
nodos con clase, y el estilo perdido igual.

⚠️ **(f) El paso 4.2 queda ABIERTO a propósito**: entra el paso 1 (catálogo) con su paridad
demostrada; los pasos 2 (calendario) y 3 (hora, cantidad y complementos) siguen pendientes. La red ya
está puesta, así que cada uno se cierra contra ella.

## #45 · 2026-08-14 · El calendario: repartir días es presentación, decidir cuáles se ofrecen no
Fase 4 · paso 4.2 (segundo tramo): el **paso 2**, con su banda de progreso.

**(a) La rejilla la compone el CLIENTE, y eso no rompe `CE-4`.** Qué días se pueden reservar lo dice
`SlotOffer` a través de `GET availability/{producto}/dates` (`AFORO-02`) y llega con su precio y su
clave de tarifa. Repartir esos días en semanas de lunes a domingo y rellenar los huecos del mes
anterior es presentación pura, así que vive en `resources/js/sidebar/calendar.js` — módulo plano, sin
Vue, por el mismo motivo que la máquina de estados.

**(b) Pero «presentación» no significa «sin verificar».** El diff de árbol compara Vue contra el
view-model del SERVIDOR, así que no vería una rejilla compuesta mal por el cliente: el test pasaría y
el cajón enseñaría otro calendario. `SidebarCalendarParityTest` cierra ese hueco comparando las dos
composiciones **dato a dato** para el mismo mes.

**(c) ⚠️ Dos casos frontera que se descubrieron midiendo, no razonando.**
1. **Un mes que empieza en domingo**: `getDay()` devuelve 0 para el domingo, así que una semana que
   empiece en lunes necesita retroceder seis días y no cero. Se busca un mes real que cumpla la
   condición en vez de darlo por supuesto.
2. **El huso horario del navegador**: `new Date('2026-08-01')` se interpreta como medianoche **UTC**,
   y al oeste eso es el día anterior. La primera versión del test comparaba husos con el mes en
   curso y **pasaba con el bug dentro**: el desfase de un día solo cambia el lunes de la semana si el
   día 1 ya era lunes. Corregido a ese mes, la mutación cae en los dos husos al oeste. Un test que no
   distingue es peor que no tenerlo.

**(d) La banda de progreso tiene su propio caso de diff**, porque **no es del paso 2**: la comparten
los pasos 2 y 3, vive fuera del bloque de cada uno en el Blade y trae el «volver» del flujo. Un diff
anclado en el título del paso no la vería, y un motor que no la emitiera dejaría al cliente sin
salida y sin contador de fases.

**(e) Lo que la paridad volvió a enseñar**: un día no reservable es un `<span>`, no un `<button>`
deshabilitado — `.cal__day` se estila según el tipo de elemento—; y el precio del día se pinta **sin
decimales**, que en una rejilla de siete columnas no es un descuido sino la única forma de que quepa.

⚠️ **(f) Queda declarado, no resuelto**: las cabeceras de día y el nombre del mes los compone el
servidor con Carbon y el cliente con `Intl`, así que **el texto visible puede diferir** (§4.5 ya lo
avisaba). Es el único punto del paso 2 donde los dos motores no comparten la fuente del texto.

## #46 · 2026-08-14 · El paso 3 cierra 4.2, y enseña dónde el diff de árbol NO llega
Fase 4 · paso 4.2 (último tramo): hora, cantidad, campos del pack y complementos.

**(a) El diff de árbol tiene un límite estructural, y este paso lo destapó dos veces.** Compara lo
que emite cada motor, pero **alimenta al componente Vue con datos del SERVIDOR**. Todo lo que el
cliente reciba de otra fuente —o componga él— queda fuera de su alcance:
1. **Los complementos llegaban con otros nombres.** El componente usaba los del view-model de
   Livewire (`id`, `qty`, `can_inc`) y el endpoint publica `product_id`, `quantity`, `can_increase`.
   El diff seguía **verde** mientras el cajón real habría pintado filas vacías. Corregido: el
   componente habla el lenguaje de su fuente —la API— y el test traduce.
2. **La acotación del selector no se estaba comprobando.** Con la cantidad lejos de sus topes, los
   dos botones salen habilitados en cualquier motor: se verificó por mutación que cambiar el techo
   **no** ponía el diff en rojo. Hace falta llevar la cantidad a los extremos, que es donde el
   `disabled` deja de coincidir.

**(b) De ahí sale una regla para el resto de la fase**: *si el cliente recibe un dato de la API o lo
compone él, el diff de árbol no lo verifica y hace falta una paridad de DATOS aparte.* Ya van dos:
`SidebarCalendarParityTest` (composición de la rejilla) y `SidebarAddonsParityTest` (endpoint ↔
view-model, campo a campo). La segunda muerde señalando el campo exacto: se comprobó renombrando
`can_increase` en el recurso y quitando la poda de un dependiente.

**(c) La trampa medida se confirmó con datos reales**: en el pack de SaltoPark, `available` = 60 y
`max_quantity` = 20. **No son el mismo número** (`AFORO-02`), y un selector construido sobre el
primero dejaría pedir 60 invitados que el checkout rechaza. El componente usa el segundo.

**(d) Lo que la paridad de árbol volvió a enseñar**: un complemento bloqueado emite un stepper
**inerte**, no ninguno — los tres controles posibles (stepper, interruptor y el rótulo de
por-invitado) tienen árboles distintos, y `.entry__stepper button` depende del tipo de elemento.

Con esto **4.2 queda cerrado**: los tres pasos transcritos, cada uno con su paridad demostrada.

## #47 · 2026-08-14 · 4.3·1 — el cajón SPA tenía armazón de cartón, y el gate no podía verlo
Fase 4 · paso 4.3 (primer tramo de tres). El paso 4.3 «cesta y presupuesto» resultó demasiado grande
para un commit y se parte como se partió 4.2: **·1 el armazón y los cimientos de texto · ·2 el pie ·
·3 la cesta**. Este tramo no transcribe ningún paso nuevo: cierra lo que 4.2 dejó abierto sin que
nadie lo viera.

**(a) El motor SPA no emitía NADA del armazón, y los nueve casos del gate salían verdes.** Su raíz era
`<div class="purchase">` con el paso colgando directamente: sin velo de carga, sin banda de progreso y
sin la zona scrollable. La razón es estructural y conviene retenerla: **todos los casos de
`SidebarDomContractTest` anclan DENTRO** (`catalog-acc`, `wiz__title`), así que nunca miraban a los
hermanos de arriba. Ahora hay un caso anclado en `.jj-loading` con hermanos —el primer hijo de
`.purchase`—, que es la única forma de comparar también el ORDEN entre ellos: de él dependen selectores
de adyacencia como `.bk-paybreakdown + .bk-foot`. Verificado por mutación: quitar el velo, quitar la
zona scrollable o quitar la banda ponen el diff en rojo.

**(b) La banda de progreso estaba escrita, comparada en verde y NO se pintaba.** `BookingProgress.vue`
existe desde 4.2 y su caso pasaba, pero `Sidebar.vue` le pasaba `progress: null` y `TimeStep` ni lo
importaba: **el cajón vivo no tenía «Volver» ni contador de fases en ningún paso**. Es el límite del
diff de árbol otra vez (`#46(a)`): alimenta a Vue con el view-model del SERVIDOR. La composición baja
a `progress.js`, módulo plano, con paridad de datos contra `bookingProgress()` en sus tres estados y
para entrada y pack.

**(c) `#sidecart-spa` partía la cadena flex del panel, y este paso es el que lo destapa.** Vue monta
DENTRO de su hueco, no lo reemplaza, así que ese `<div>` queda entre `.sidecart__body` y `.purchase` —
y no tenía **ni una regla CSS** (medido: 0 coincidencias en `site.css`). Lo que sostiene «el contenido
scrollea y el pie queda anclado» es una cadena de HIJOS DIRECTOS, y un `display:block` en medio corta
la altura. No se notaba porque el motor SPA aún no emitía scroll ni pie. ⚠️ **Ningún diff de árbol
puede ver esto**, así que la regla viene con guarda ejecutable (`SidebarTokenBudgetTest`, verificada
por mutación).

**(d) El formato de importes era una divergencia silenciosa, y las dos salidas «obvias» de JS fallan.**
El cajón SPA tenía DOS copias de `(céntimos/100).toFixed(2)`, que **no agrupa millares**, y una tercera
variante en el calendario. Medido contra `number_format`:
- `toFixed(2)` → «1000,00» donde PHP escribe «1.000,00» (desde 1.000 €, que un pack de 20 invitados
  cruza a diario);
- `Intl.NumberFormat('es-ES')` **tampoco vale**: el español declara `minimumGroupingDigits: 2`, así que
  no agrupa entre 1.000 y 9.999 — arregla los importes de cinco cifras y deja rotos los de cuatro.
⚠️ **Y el gate es CIEGO a esto**: el normalizador descarta los nodos de texto a propósito. La red es
`SidebarMoneyParityTest`, que barre todos los céntimos de 0 a 2.000 más los cruces de millar contra
`number_format` — y **encontró un fallo en la primera ejecución** que ninguna lectura habría dado: el
signo hay que decidirlo sobre el RESULTADO y no sobre la entrada, porque `number_format(-0.05, 0)`
devuelve «0», no «-0».

**(e) El diccionario del cajón se leía mal de tres formas, las tres en silencio.** Medido sobre el
payload real: `__('tickets')` son 121 claves de primer nivel de las que **cuatro son subarrays**
(`errors`, `paused`, `statuses`, `payment_failed`), así que `messages['errors.choose_one']` era
`undefined` y el helper devolvía `''` — el aviso de error se pintaba **vacío**. Además `String.replace`
con patrón de texto sustituye solo la PRIMERA aparición, y `cart_items` lleva `:count` dos veces. Y
`cart_items` es la única clave del grupo con pluralización de Laravel, servida CRUDA: sin resolverla la
barra-carrito enseñaría «2 artículo|2 artículos». Nace `i18n.js` con las tres cosas y su paridad contra
`__()`/`trans_choice()` en los tres idiomas. ⚠️ **El selector de plural no es `n === 1`**: en francés el
CERO cae en el singular (medido), que es justo el número que más se ve en una barra de carrito.

**(f) El «modo» del paso de PAGO divergía, y ninguna prueba podía verlo.** `modeOf(8)` devolvía
`result` y `Purchase::stepModeMap()` dice `cart`. La clase `is-{modo}` se pinta **fuera** del cajón
(`.sidecart__panel`), así que ningún diff de árbol la alcanza. Corregido y fijado recorriendo el mapa
ENTERO del servidor, no una muestra — que es como apareció.

**(g) La divergencia de fechas de §4.5 queda ACOTADA, no solo declarada.** El contexto de la banda lo
compone el servidor con Carbon y el cliente con `Intl`. Medido: **el patrón hay que fijarlo nosotros**,
porque un preajuste de `Intl` INVIERTE día y mes en inglés («Sat, Sep 5» frente a «Sat 5 Sep»). Con el
patrón fijo, **inglés y francés coinciden EXACTAMENTE** y solo el español difiere, y solo en los puntos
de abreviatura y en `sept`/`sep`. Queda en `DEUDA.md` con la forma de cerrarlo. ⚠️ Y los puntos de
`Intl` **no se recortan** aunque a primera vista lo pidan: recortarlos rompería el francés, que hoy
coincide. Hay caso que lo vigila.

**(h) Lo que este tramo NO cierra, dicho sin optimismo**: el pie (`.bk-foot`) sigue sin existir en el
motor SPA, así que el cajón sigue **auto-avanzando** del calendario a la hora donde Livewire exige
pulsar «Continuar», y no hay CTA para añadir a la cesta. Eso es 4.3·2.
