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

## #48 · 2026-08-14 · 4.3·2 — el pie es la navegación, no una barra; y el séptimo hueco que sí existía
Fase 4 · paso 4.3 (segundo tramo). Entra el pie (`.bk-foot`), el paso 4 (la cesta) y el flujo que los
une. **La cesta vive en memoria**: la persistencia en `localStorage` —con su dueño, su purga y su
reconciliación— es 4.3·3, y el módulo `cart.js` nace ya sin tocar el almacén para poder recibirlo.

**(a) El pie no se podía partir de la cesta, y esa fue la decisión de alcance.** El plan inicial era
«·2 el pie · ·3 la cesta», pero el CTA del paso 3 es «Añadir al carrito» y **`disabled` es un atributo
que el diff SÍ compara**: dejarlo inactivo habría puesto el gate en rojo, y dejarlo activo sin cesta
habría sido un botón mudo. El corte honesto es por dependencia, no por pantalla.

**(b) El séptimo hueco de API era otro, y este sí.** La propuesta de devolver el `event_data` saneado
se descartó en 4.3·1 con evidencia (revierte una decisión RGPD probada y rompe `RGPD-04`). El hueco
real estaba al lado: `POST /catalog/products/{id}/addons` **construía un `CartQuote` completo y tiraba
sus totales**, así que el cliente no tenía de dónde sacar el importe del pie del paso 3 y la única
salida era sumar `subtotal_cents` con `addons_total_cents` — que salen de **dos recorridos distintos**
del servidor. Se publica `line.total_cents` desde el mismo presupuesto: dos líneas, cero consultas
nuevas, cero PII, y `PAY-12` respetado (una sola fuente de CÁLCULO, no una sola URL).

**(c) Lo que el cliente NO compone, y por qué cada cosa**: el total del paso 3 lo publica el endpoint;
el desglose viene ya partido (`deposit_cents` + `gate_remainder_cents`), porque reconstruirlo restando
sería reimplementar la Opción A de #225 —los complementos de una línea con señal van íntegros al
parque—; y el recuento de la barra-carrito es **pluralización de Laravel**, no `n === 1`. La única
resta que queda es `park = total − online` en la cesta, y es legítima porque son dos AGREGADOS de la
misma fuente y es literalmente lo que hace el servidor.

**(d) Dos rótulos que el diff de árbol daba por buenos.** El desglose se llama «Pagas ahora (señal)»
en el paso 3 y «Pagas ahora» —NEUTRO— en la cesta y en el pago, porque en una cesta mixta lo que se
cobra ahora no es solo señal (#225). El normalizador del gate descarta los nodos de texto, así que
reutilizar el componente sin parametrizar el rótulo pasa verde y cambia la copia. Lo fija
`SidebarCartParityTest`, verificado por mutación.

**(e) La cesta se ofrece ya en la consulta de horas** (`AFORO-02`). `POST availability/{id}/times`
viajaba con `items: []` desde 4.2, con un comentario que decía que la clave estaba puesta «para que no
se olvide al añadirla». Este es ese momento: sin la cesta, `offerableTimes()` no descuenta lo que ella
ya retiene y el cajón ofrece horas y topes que el checkout rechaza.

**(f) Un caso del gate pasaba con el fallo dentro, y lo dijo la mutación.** El contenedor
`.cart__lines` se emite SIEMPRE —el condicional del Blade está DENTRO del `<div>`—, pero el caso de la
cesta completa no lo detectaba porque todas sus líneas tienen fecha. El estado es alcanzable y se
midió: `Cart::sanitize()` conserva una línea con `date: ''` y **el presupuesto la tarifica igual**. Hay
caso propio para ella.

**(g) La baseline de bloques del armazón ENCOGIÓ, que es lo que tenía que pasar.**
`SHELL_BLOCKS_NOT_YET_IN_SPA` pasa de `['bk-paybreakdown', 'bk-foot']` a `['bk-paybreakdown']` sola:
el test cayó al transcribir el pie y obligó a quitarlo de la lista. Es el patrón de baseline que solo
mengua, aplicado al marcado.

**(h) Lo que este tramo NO cierra, dicho sin optimismo**: (1) el CTA «Ir a pagar» **no lleva a ninguna
parte** —el paso 5 es 4.4a—; (2) la cesta **no sobrevive a una recarga**, que es 4.3·3; y (3) el aviso
de **reservas en pausa** sigue sin existir en el motor SPA, y ahora pesa más: el pie comparte su
guarda, así que con la pausa activa el cajón SPA enseña una barra de «Ir a pagar» donde Livewire
enseña el aviso de mantenimiento. No es fuga de dinero —el servidor rechaza— pero es la divergencia
más visible que la fase tiene abierta, y la cierra 4.3·3.

## #49 · 2026-08-14 · 4.3·3 — el cajón SPA deja de vender durante la pausa
Fase 4 · paso 4.3 (tercer tramo). Cierra la divergencia que `#48(h)` dejó declarada: con las reservas
pausadas, el motor SPA seguía vendiendo —catálogo, cesta y «Ir a pagar»— mientras Livewire enseñaba
el aviso de mantenimiento. No era fuga de dinero (`ReservationAdmissionPolicy` rechaza en servidor),
pero sí la divergencia más visible que la fase tenía abierta.

**(a) La pausa apaga CUATRO bloques, no uno.** Lo visible es que el contenido scrollable se sustituye
por el aviso, pero la misma condición gobierna además la banda de progreso, el pie y la banda de
desglose del pago. Un armazón que solo cambiara el contenido dejaría un «Ir a pagar» vivo sobre un
cartel que dice que no se puede comprar. ⚠️ **Y la guarda va en la VISTA, no en los compositores**:
con la pausa activa el servidor **sigue** devolviendo `bookingProgress()` y `footer()` no nulos, así
que anularlos en `foot.js`/`progress.js` habría puesto en rojo las paridades que los comparan.

**(b) El aviso tapa SEIS pasos y no todos, y eso no se puede derivar.** Son `[1,2,3,4,5,8]`: incluye
el de PAGO (que es modo `cart`) y excluye el 7 —verificación de correo, que la máquina ni tiene—.
Los pasos de RESULTADO rinden normales aunque las reservas estén pausadas, porque son acciones **ya
iniciadas**: un `v-if="paused"` en la raíz taparía la pantalla de «pago confirmado» a quien acaba de
pagar. Se compara el mapa ENTERO contra el servidor, que es como apareció la divergencia del «modo».

**(c) El título y el mensaje NO son literales de i18n**, y esta es la trampa invisible del paso: son
ajustes que la dueña edita **por idioma** en el panel, y `tickets.paused.*` es solo su respaldo. Sin
override los dos textos coinciden **exactamente**, así que pintarlos desde el diccionario inyectado
sale idéntico en desarrollo y enseña el texto genérico en toda instalación que haya escrito el suyo.
Salen de `notice.title`/`notice.message`; del diccionario solo salen los tres rótulos de los CTA, que
el endpoint no publica.

**(d) Los canales van A LA VEZ, no en cascada** (`#38(g)`), y `contact_url` es **el último recurso ya
decidido** por el servidor. El cliente pinta lo que no sea nulo y no evalúa ninguna condición. Un
`v-else-if` encadenado escondería el WhatsApp de toda instalación con teléfono, en silencio.

**(e) El estado se RELEE en cada apertura del cajón, y esto es lo que de verdad cierra la
divergencia.** El motor SPA se monta **una sola vez por carga de página** y no se desmonta nunca, así
que una lectura solo en `onMounted` habría sido exactamente el snapshot que el endpoint existe para
evitar: una pestaña abierta antes del interruptor seguiría vendiendo hasta que alguien recargara,
mientras Livewire —que reevalúa su guarda en cada render— entra en el siguiente clic. Nace
`refreshStatus()` en el handle del motor y lo llama `open()`. ⚠️ **Residual declarado**: un cajón que
ya esté ABIERTO cuando se acciona el interruptor no se entera hasta cerrarlo y volver a abrirlo; el
contrato pide además releer tras un 409 `reservations_paused`, y eso llega con el checkout (4.5).

**(f) Tres huecos de red que encontró la revisión adversarial, los tres con su mutante:**
1. **El caso de árbol recomponía el aviso en PHP.** Comparaba el Blade contra un Vue alimentado por
   una réplica de la regla del cliente, así que la regla del cliente no la tocaba nadie: poner los
   canales en cascada dejaba la suite ENTERA en verde. Ahora el caso ejecuta `buildNotice()` **en
   Node**, que es lo que hace el cajón.
2. **`primary` y `external` no los comparaba nadie.** El primero pinta el botón principal con el color
   de la zona; el segundo abre en pestaña nueva **con `rel="noopener"`** — y ni `target` ni `rel` son
   atributos de contrato. Intercambiarlos pasaba en verde. Ahora se extraen del HTML del Blade y se
   comparan.
3. **Nada ejecutaba `Sidebar.vue`.** Es el único sitio que pide `/booking/status` y no puede pasar por
   el renderizador del gate (lee `window.Alpine`): borrar la llamada dejaba la suite entera verde y el
   cajón volvía a vender en pausa **con la casilla marcada**. Nace una guarda sobre el chunk
   CONSTRUIDO —del mismo tipo que la que vigila que Vue no viaje con la landing—: el motor tiene que
   contener las llamadas que no puede decidir por su cuenta.

**(g) Lo que el diff de árbol NO puede ver de este bloque, dicho entero**: `href`, `target` y `rel` no
son atributos de contrato, así que **el enlace de WhatsApp y el de `/contacto` producen árboles byte a
byte idénticos**. Sin la paridad de enlaces, un motor que mandara a la página de contacto donde el
servidor ofrece WhatsApp pasaría el gate en verde.

## #50 · 2026-08-14 · 4.3·4 — la cesta sobrevive a la recarga, y la fuga que eso introduce
Fase 4 · paso 4.3 (cuarto y último tramo). La cesta del cajón SPA pasa a vivir en `localStorage`
—`DECISIONES #38(d)`—, y con ella llegan las defensas que la sesión daba gratis y el almacén del
navegador no.

**(a) La identidad sale del SERVIDOR, y por dos canales.** `userId` viaja en el `data-boot` (12 bytes
medidos, con el HTML y antes de que exista ningún `fetch`) y se re-resuelve con `GET /me` **al abrir el
cajón y al oír `logged-in`**. Se descartó que el id viajara en el evento de Livewire: `dispatch('logged-in')`
va **sin payload**, y colgar una defensa de seguridad de un id que circula por el bus de eventos del
navegador es confiar en el cliente. El `data-boot` no es un lujo: **el logout es una navegación
completa**, y es justo el caso que la sesión resolvía sola con `invalidate()`.
⚠️ Un fallo de red **no** es un cierre de sesión: solo un 401 significa «ya no hay nadie». Purgar por
un corte destruiría la cesta de quien no ha hecho nada.

**(b) La purga tiene CINCO casillas y una no existe en el servidor.** En sesión, el logout vacía cesta
y marcador a la vez, así que nadie tuvo que decidir qué pasa con «había dueño X y ahora no hay nadie».
`localStorage` no tiene `invalidate`: **(X → anónimo) → PURGAR** es la casilla que el cajón inventa
entero, es la más probable en la tablet de un parque y es **la fuga que introduce la persistencia**.
Las otras cuatro espejan al servidor, incluida la que sostiene el flujo principal: **una cesta de
invitado SOBREVIVE al login**. Y la comparación va casteada por los dos lados —`localStorage` solo
guarda texto, y un `70 !== '70'` purgaría la cesta de su propio dueño en cada carga—.

**(c) El saneador espeja `CartPayload`, no `Cart::sanitize()`, y descarta en vez de corregir.** Los dos
saneadores del servidor son OPUESTOS y elegir mal duele de dos formas distintas:
- `Cart::sanitize()` **corrige**: `qty: 0`, `-5` y `'abc'` salen los tres como **1**. En un almacén que
  el usuario puede editar y que sobrevive a los despliegues, eso convierte una línea corrupta en **una
  compra de una unidad que nadie pidió**, con su precio pintado;
- `CartPayload` **rechaza el cuerpo ENTERO** con un 422: medido, **una sola** línea con `date: ''` deja
  la cesta sin presupuesto, sin horas y sin poder preguntar si cabe otra —los tres endpoints comparten
  las reglas—, o sea el cajón inservible y sin botón para quitar la culpable.
La síntesis es la que el propio `CartPayload` documenta para una cesta de sesión: **descartar la línea
mala y restaurar el resto**, con sus criterios de formato. ⚠️ Y una expresión regular NO basta para la
fecha: `date_format` reconstruye la fecha, así que `2026-02-30` se rechaza — fue la única divergencia
que salió al pasar el corpus por los dos lados.

**(d) Caducidad propia, porque el presupuesto no la tiene.** Medido: `POST orders/quote` tarifica **con
importes completos** una fecha de hace 19 meses; no consulta franjas. La sesión caducaba a los 120
minutos y `localStorage` no caduca nunca, así que sin descartar los días pasados al restaurar el cliente
ve un total creíble y el rechazo le llega al pulsar pagar, ya identificado. La comparación es de
CADENAS (`Y-m-d` ordena solo), sin `Date`, para no reabrir el agujero de husos.

**(e) Reconciliar es dos cosas, y la segunda no se adivina.** Se borran del almacén las líneas cuyo
`index` no vuelve —el hueco es la señal de que el producto dejó de venderse— **y** las que vuelven con
`unit_price_cents: null`: se venden, pero no tienen precio para la tarifa de ese día, cuentan en el
badge, suman 0 al total y el checkout las rechaza sin decir cuál son. Es el bug P8 por otra puerta. Y
de las que sobreviven se quitan los complementos si el presupuesto los devolvió vacíos habiéndolos
enviado, porque `CartPricer` atrapa el error del resolutor y tarifica la línea **sin ninguno**: el
total miente a la baja mientras la cesta guardada conserva el complemento roto.
⚠️ **Reconciliar DESPLAZA los índices**, y las filas y el botón de quitar se emparejan por el `index`
del presupuesto: podar y pintar el presupuesto viejo enseña las respuestas de otra línea y deja el
botón mudo. Por eso podar y re-presupuestar es **una sola operación**.

**(f) El canario del RGPD.** `event_data` no se persiste, y comprobarlo mirando esa clave en la primera
línea lo pasarían en verde cuatro mutaciones distintas —guardarlas en una segunda clave, anidarlas en
un complemento o en un `meta`, o dejarlas en un marcador de «línea incompleta»—. El doble de almacén
graba **todas** las escrituras de **cualquier** clave y la aserción busca centinelas únicos en el
volcado entero. Son datos del art. 9 y el cliente es su única fuente: la fuga solo puede salir por ahí.

**(g) Dos huecos de red que encontró la revisión adversarial, los dos con su mutante:**
1. **Las guardas de `toApiItems` no estaban probadas**, y 4.3·4 crea las primeras líneas que llegan sin
   `event_data` ni `addons`: quitar el `?? {}` y el `?.` dejaba la suite JS **entera en verde** y el
   módulo lanzaba `TypeError` con la primera cesta restaurada. Se cubrió **antes** de tocar nada.
2. **`npm run test:js` solo alcanza un nivel de carpeta**: el patrón lo expande `sh`, donde el doble
   asterisco vale por uno. Un test en una subcarpeta no se ejecutaría **nunca** y la suite diría
   «pass». Es el fallo más barato de este paso —cuatro piezas nuevas invitan a agruparlas— y ahora
   `PrePushGateTest` compara los ficheros que el patrón alcanza con los que hay en el árbol.

**(h) Lo que NO entra, y por qué**: una línea de PACK restaurada vuelve **sin sus respuestas** y el
servidor la rechazará al crear el pedido (`line_event_required`). No se construye todavía el camino
para volver a rellenarla porque **ese camino no se puede recorrer**: «Ir a pagar» no lleva a ninguna
parte hasta 4.4a. Queda declarado como **precondición del paso de pago**, y el dato para construirlo ya
está: al restaurar se piden los `event_fields` de los productos de la cesta.

## #51 · 2026-08-14 · 4.4a·1 — el CTA de pagar aprende quién eres y si puedes reservar
Fase 4 · paso 4.4a, primer tramo. Hasta aquí «Ir a pagar» existía porque el diff de árbol lo exigía y
**no llevaba a ninguna parte** (`#48(a)`). Ahora hace las dos preguntas que la web hace desde siempre
al pasar del carrito a la identificación, y el paso queda **partido en dos** porque sus dos destinos
—la pantalla de identificación (5) y la de pago (8)— son 4.4b y 4.5.

**(a) El paso se parte, y el criterio es el mismo que en 4.2 y 4.3: la DEPENDENCIA.** Las cinco
salidas de `Purchase::checkout()` se midieron una a una antes de escribir nada, y solo dos tienen
pantalla hoy: el aviso de admisión denegada y el cartel de pausa se pintan en el paso 4, que ya está
transcrito. Las otras tres llevan a los pasos 5 y 8. **Transcribir la decisión sin navegar** deja el
CTA mudo donde ya lo estaba y cierra las dos que sí se ven; navegar habría dejado el cajón **en
blanco**, que es peor que un botón que no responde.

**(b) Son DOS preguntas y no una, y la segunda no es la obvia.** `GET /me/reservation-eligibility` da
el aviso temprano —para eso nació en 4.0b—, pero `GET /me` es el que sostiene la seguridad: con la
cesta en `localStorage` (`#50`), el clic de «Ir a pagar» es **el único momento** en que el cajón puede
enterarse de que la sesión cambió en OTRA pestaña. `logged-in` es un evento del mismo documento y el
`userId` del montaje es de la carga de la página; sin esta comprobación, la cesta de Alice se convierte
en el pedido de Bob y ninguna de las cinco casillas de la tabla de purga llega a evaluarse.
⚠️ Y van **en paralelo**: en cadena, el clic más caro del embudo paga dos viajes sin que ninguna
dependa de la otra. Hay caso que lo mide contando peticiones en vuelo, no leyendo el código.

**(c) La identidad se aplica ANTES de mirar el veredicto.** Si el titular cambió, la cesta se purga, el
cajón vuelve al catálogo y **no hay veredicto que aplicar**: seguir sería llevar a pagar una cesta que
acaba de dejar de existir. El orden se prueba con un doble que registra qué se le pasó y cuándo.

**(d) La PAUSA no se enseña como error de carrito, y esto se MIDIÓ en vez de leerse.**
`reportAdmissionDenial()` escribe `errors.reservations_paused` —con teléfono o sin él— en el error bag
del componente… y **ese mensaje no se pinta jamás**: al volver al paso 4 se cumple
`showPausedNotice()` y el cartel de mantenimiento sustituye el flujo entero. Verificado sobre el HTML
real: el bag lo tiene, el documento no. Un motor que pintara ese error donde la web pinta el cartel
enseñaría un texto que **no existe en ninguna instalación**, y el gate de árbol lo daría por bueno
—descarta los nodos de texto—. Por eso el veredicto de pausa **no compone mensaje**: pide releer
`GET /booking/status`, que es lo que hace aparecer el cartel.
⚠️ **De regalo, cierra el residual que 4.3·3 dejó declarado**: un cajón ya ABIERTO cuando se acciona el
interruptor no se enteraba hasta cerrarlo y volver a abrirlo. Ahora el clic de comprar lo descubre.

**(e) La secuencia vive en un módulo plano, no en el componente** (`CE-6`, `sidebar-spa.md` §4.8). La
decisión pura ya lo exigía; la SECUENCIA —dos peticiones, aplicar identidad, decidir— se llevó también
a `admission.js` con `api` y `applyIdentity` **inyectados por parámetro**, igual que `cart.js` recibe el
almacén. El motivo es el fallo que 4.3·1 ya pagó: un árbol no dice a quién se preguntó, en qué orden,
ni si la cesta se purgó por el camino, así que esa lógica dentro del `.vue` no tendría red. El
componente queda con seis líneas de cableado.

**(f) El destino se compara aunque las pantallas no existan.** `SidebarAdmissionParityTest` recorre las
**cinco** situaciones —invitado, admitido, cesta vacía, tope de pendientes, límite de frecuencia y
pausa— y compara el paso al que llega el componente Livewire con el que decide el módulo, alimentado
con las respuestas **REALES** de la API (no fabricadas: así un cambio del código público se ve). Cuando
los pasos 5 y 8 se transcriban, enchufarlos es cablear y no volver a decidir.

**(g) Lo que NINGÚN test podía ver, y cómo se cubre.** Los avisos se comparan **palabra por palabra y
en los tres idiomas** —el normalizador del diff descarta los nodos de texto— e incluyen el `:max` del
tope, que sale de dos sitios distintos: del `context` del veredicto en Livewire y de
`max_pending_orders` en el sobre de la API. Y que el CTA siga **cableado** a la pregunta lo vigila
`SidebarBundleBudgetTest` sobre el bundle construido, que es la única red posible de eso.

**(h) Verificado por mutación seis veces**, cada una cazada por el caso que le toca: pintar la pausa
como error de carrito · ramificar sobre el código interno `too_many_pending` en vez del público
`too_many_pending_orders` · mandar al invitado a pagar · encadenar las dos peticiones · resolver la
identidad con la respuesta de la elegibilidad · quitar el cableado del CTA. Y verificado **en vivo**
con `curl` sobre la instalación de desarrollo: 401 sin sesión en los dos endpoints, `allowed: true` con
cookie y `Origin`, y `reservations_paused` con el interruptor puesto — pasado por el módulo real en
Node, que responde «releer el estado».

**(i) Lo que NO entra**: no se navega a los pasos 5 ni 8 (4.4b y 4.5); la pantalla de identificación no
se transcribe; y sigue viva la precondición de `#50(h)` —una línea de PACK restaurada vuelve sin sus
respuestas y `OrderCreator` la rechaza—, que ahora está **un paso más cerca de morder** y no puede
quedar fuera de 4.5.

## #52 · 2026-08-14 · 4.4a·2 — el cajón identifica sin salir de sí mismo, y los dos textos que no coincidían
Fase 4 · paso 4.4a, segundo tramo. El invitado que pulsa «Ir a pagar» ya llega a su pantalla: el paso 5
está transcrito y el login habla con `POST /api/v1/auth/login`, que consume **el mismo**
`Identity\Services\PasswordLogin` que el modal de la web — así que los dos limitadores de `SEC-06`, la
comprobación de credenciales y el sello de última entrada son literalmente el mismo código.

**(a) El árbol del paso 5 son 31 nodos, y llegar a él por el camino equivocado enseña 9.** Medido: con
`->set('authMode', 'login')` sobre un componente recién montado, Livewire deja el hijo como
`<div wire:id=… wire:name="auth.login"></div>` **VACÍO** —los componentes hijos se hidratan en una
petición posterior—, así que el diff habría comparado armazón contra armazón y **un motor SPA sin
formulario habría pasado en verde**. El caso llega al estado pulsando la pestaña, y hay un segundo caso
que fija el hecho medido para que nadie lo «simplifique» de vuelta.

**(b) Los dos motores decían cosas DISTINTAS para el mismo rechazo.** Medido antes de escribir una
línea:
- web (`auth.failed`): «Estas credenciales no coinciden con nuestros registros.»
- API (`invalid_credentials`): «El correo o la contraseña no son correctos.»
- web (`auth.throttle`): «Demasiados intentos. Inténtalo de nuevo en :seconds segundos.»
- API (`too_many_requests`): «Has hecho demasiadas peticiones seguidas…» + `params.retry_after`

**El cajón ramifica sobre el CÓDIGO y pinta el literal del diccionario.** Pintar el `message` del sobre
—lo natural en un cliente de API— habría cambiado la copia del cajón en las tres lenguas sin que ningún
gate lo dijera: el diff de árbol descarta los nodos de texto. Y es además lo que el contrato pide de un
cliente: los códigos son estables, los mensajes son para quien no tiene diccionario. La validación sí
se pinta tal cual llega, porque los dos motores la escriben con **las mismas reglas** y sus textos ya
coinciden (comprobado en los tres idiomas, con el caso vacío y el de formato inválido).
⚠️ Hay un caso que comprueba que los dos textos **siguen siendo distintos**: si algún día se unifican en
el servidor, cae — y estará bien que caiga, porque entonces el rodeo sobra.

**(c) El reparto de los avisos es tan contrato como el texto** (hallazgo L-02 de la auditoría del
origen): el del limitador va al banner `_global` y el de credenciales **bajo el campo email**. Juntarlos
mezcla un mensaje genérico —que no revela si el correo existe— con uno que sí dice algo del sistema.

**(d) El montaje inyecta dos grupos nuevos, PODADOS, y la poda es la decisión.** El paso 5 no usa el
grupo `tickets`: sus rótulos son `account.login.*` y sus avisos `auth.*`. El grupo `account` entero son
**9,6 kB** en español —tanto como `tickets`— y viajaría en el HTML de **todas** las páginas públicas
para pintar diez rótulos; se poda a `login` + el `cta` de `register`, conservando el CAMINO real de
`lang/` porque `i18n.js` lee por camino y aplanarlo sería una tercera forma del diccionario. Medido en
vivo: **538 bytes**. Hay guarda de que lleva todo lo que el paso pinta —una clave que falte se pinta
VACÍA y nada avisa— y de que sigue podado.

**(e) Al entrar pasan TRES cosas y ninguna sobra.** Se avisa a Livewire con `logged-in` —fuera del
cajón, `account-context` es un componente Livewire que lo escucha para repintar «Hola, saltador/a», y
sin el aviso el panel seguiría ofreciendo «Entrar» a quien acaba de entrar—; se aplica la identidad con
la respuesta **del propio login**, que trae el perfil con la misma forma que `GET /me` justo para que
identificarse no cueste una petición más; y se continúa el checkout, que es lo que hace
`Purchase::onAuthenticated()` llamando a `proceed()`. Verificado en vivo que con el motor SPA la página
**sigue cargando Livewire** y que `account-context` está en ella: sin eso, el puente no tendría con
quién hablar.

**(f) La pestaña de «Crear cuenta» NO cambia de modo, a propósito.** Su formulario es 4.4b; marcarla
activa dejaría el rótulo encendido con un formulario de LOGIN debajo, que es peor que un botón que no
responde porque miente sobre lo que va a pasar. El botón existe porque el árbol lo exige y no lleva a
ninguna parte, igual que «Ir a pagar» hasta 4.4a·1.

**(g) El techo del bundle sube de 120 a 135 kB, y es una decisión medida.** El paso costó **9,97 kB**
(3,13 comprimidos), aislado construyendo con y sin él: casi todo es runtime de Vue que hasta ahora no
entraba —`vModelText`, `vModelCheckbox`, `withDirectives`—, porque este es el **primer formulario** del
cajón. Es coste de una vez: el registro y el pago reutilizan ese runtime. Con 120 el margen quedaba en
**0,30 kB**, que no es un presupuesto sino un accidente esperando.

**(h) Verificado por mutación**, cada una cazada por el caso que le toca: un solo icono en el toggle de
contraseña · pintar el `message` del sobre en vez del literal · quitar el puente `logged-in`. Y en vivo
con el flag activo: el `data-boot` real lleva los dos grupos con sus textos, Livewire se carga y
`account-context` está presente.

**(i) Lo que NO entra**: el registro embebido y su restauración de cesta (4.4b) y el paso de pago (4.5).
Quien se identifica con la cesta lista **se queda en el paso 5**, porque el 8 no está transcrito y
navegar a él dejaría el cajón en blanco; `TRANSCRIBED_STEPS` declara en el código a qué pasos se puede
navegar y **solo crece**.

## #53 · 2026-08-14 · 4.4b·1 — el alta desde el cajón, y los tres bugs de servidor que destapó
Fase 4 · paso 4.4b, primer tramo. El cajón ya crea cuentas: el paso 5 pinta su formulario de alta —51
nodos— y habla con `POST /api/v1/auth/register` declarando `context: purchase`, la política
**pay-first** que el servidor ya conocía (`DECISIONES #31`). El widget de Turnstile queda para 4.4b·2,
con una guarda que hace imposible el fallo silencioso.

**(a) Turnstile se aplaza porque NO SE PUEDE VERIFICAR, y eso es un criterio, no una excusa.** Montar
el widget exige claves reales de Cloudflare y un navegador; escribirlo ahora sería entregar una
integración con un tercero sin la «verificación empírica» que el DoD del proyecto exige. Lo que **sí**
entra es la guarda: `GET /config` publica si el alta exige captcha y, si lo exige, el cajón **no pinta
su formulario** — delega en el modal de auth de Livewire, que sí monta el widget. Sin esa guarda,
activar el anti-bot con el motor SPA puesto habría dejado un registro que **rechaza a todo el mundo**
con «no eres un robot», sin correo y sin log. Hay caso que recorre los tres estados del anti-bot con la
respuesta REAL del endpoint pasada por el módulo REAL.

**(b) BUG 1 — `GET /config` anunciaba un anti-bot que no existía.** El anti-bot exige las **dos**
claves; con solo la pública, `Turnstile::enabled()` es `false`, la web **no pinta el widget** y
`verify()` deja pasar el alta… pero el endpoint publicaba la clave igual. Un cliente fiel al contrato
—que es lo que este paso construye— habría pintado un captcha **que su propio servidor no comprueba**:
árbol distinto al de la web, un script de terceros de más y un obstáculo para el usuario a cambio de
ninguna defensa. Y es un estado alcanzable de verdad: se configura una clave y se deja la otra para
luego. Arreglado publicando la clave **solo si el anti-bot está activo**, que es lo que el contrato ya
prometía por escrito; de regalo, el campo pasa a ser el bit exacto que el cliente necesita.

**(c) BUG 2 — el señuelo VACÍO hacía fallar el alta entera.** Un cliente legítimo manda `website: ""`
—el campo existe en el formulario y viaja siempre—. `ConvertEmptyStringsToNull` lo convierte en `null`,
`sometimes` lo veía presente y `string` lo rechazaba: **422 sobre un campo que el usuario no ve** y que
ni siquiera es suyo. Los tests del paso 3c lo esquivaban por los dos únicos caminos que existen
—mandarlo relleno u omitirlo—, así que lo destapó el primer cliente real. Mismo arreglo para
`turnstile_token`, que tiene el mismo problema por la misma razón.

**(d) BUG 3 — las dos puertas del alta decían cosas distintas al usuario.** `Auth\Register` declara
`validationAttributes()` con los rótulos del formulario y `messages()` con el aviso propio de las
casillas legales; el controlador de la API no tenía ni lo uno ni lo otro. Resultado medido: la web
decía «El campo **Nombre y apellidos** es obligatorio.» y la API «El campo **name** es obligatorio.»;
la web «Debes aceptar esta condición para continuar.» y la API el genérico de `accepted`. Lo mismo,
dicho peor, a un cliente que pinta el mismo formulario. Arreglado en el servidor —no en el cliente—,
porque es donde estaba la divergencia y porque beneficia a cualquier consumidor de la API.

**(e) El 201 no dice si hubo cuenta, y por eso hay una segunda petición.** `POST auth/register`
responde **201 sin cuerpo siempre**: si distinguiera un alta buena de un señuelo, un bot lo notaría de
un vistazo y el honeypot dejaría de servir. Así que tras el 201 el cajón pregunta `GET /me`: con
sesión, la compra sigue; sin ella, el señuelo actuó y toca «revisa tu correo» —exactamente lo que hace
la web con `registration-submitted`—. Verificado en vivo: las dos respuestas son **byte a byte
idénticas** y solo la sesión las separa. ⚠️ Y un fallo de red al preguntar **no** cuenta como sesión:
llevaría al pago a quien no ha entrado.

**(f) Los literales del alta se pintan tal cual, al revés que en el login.** Aquí el servidor publica
en `fields.email` exactamente `account.register.already_exists` / `exists_unverified` /
`bot_check_failed` —los mismos que pinta el Blade—, así que reescribirlos sería inventar una segunda
fuente. En el login no: la API tiene un `message` propio que **no** coincide con `auth.failed`, y por
eso allí se ramifica sobre el código. Las dos conductas son correctas y las dos tienen su caso.

**(g) Tres nodos invisibles que solo vigila el diff de árbol**: el **honeypot** (`.hp`, que oculta el
CSS y que es el señuelo del servidor), la fila `.form__row` que agrupa email y teléfono, y el
`<small class="form__hint">` de la contraseña —un `<small>`, no un `<span>`—. Un motor sin honeypot deja
al servidor sin su defensa y **no se nota mirando la pantalla**. Verificado por mutación.

**(h) El banner de errores es un árbol aparte y se compara con el formulario VACÍO**: `<strong>` + `<ul>`
con un `<li>` por aviso, **y además** cada aviso bajo su campo (A11y de formulario largo). Con un solo
campo en rojo, un `<li>` de más o de menos no se vería. El ORDEN de la lista lo fija el cliente por el
de las reglas, para que no dependa de cómo serialice el sobre.

**(i) Los textos legales viajan con su `<a href>` dentro y ya interpolado**, y se pintan con `v-html`.
Partirlos en «texto + enlace» obligaría a recomponer una frase traducida que no ordena igual en cada
idioma; el contenido sale de `lang/` y de `route()`, nunca de una entrada de usuario. El payload del
montaje pasa de 538 B a **1.671 B** (es) con el grupo `register` entero — el grupo `account` COMPLETO
son 9,6 kB, seis veces eso, en cada página pública.

**(j) El paso 7 entra con el alta**, aunque dentro de la compra casi nunca lo vea una persona: solo se
llega cuando el señuelo actuó. Son tres nodos y `role="status"`. ⚠️ **No lleva salida y eso es fiel**:
el escape «¿ya tienes cuenta?» vive en la pantalla `sent` del componente Register, que **embebido no
llega a verse** —el paso 5 deja de renderizarse en cuanto el cajón pasa al 7—. Verificado sobre el HTML.
Con él, `machine.js` gana el paso 7, que le faltaba.

**(k) Lo que NO entra**: el widget de Turnstile (4.4b·2), el pago (4.5) y las pantallas de desenlace
(4.6). Quien crea su cuenta con la cesta lista **se queda en el paso 5**, igual que quien inicia sesión:
el destino es el 8 y todavía no está transcrito.

## #54 · 2026-08-14 · 4.5·1 — la cesta restaurada pide lo que le falta, antes de llegar al pago
Fase 4 · paso 4.5, primer tramo. Ejecuta lo que `DECISIONES #38(d)` dejó decidido y sin construir: «al
restaurar, las líneas de pack piden esos campos otra vez». Es **la única desviación consciente de la
paridad de toda la fase**, y hasta ahora era una nota.

**(a) El problema, medido y no supuesto.** La cesta persistida vuelve **sin `event_data`** (`#38(d)`,
RGPD: en la instalación sembrada son el nombre de un menor, su edad y sus alergias). Medido el
2026-08-14 con el pack real: **`POST /orders/quote` la tarifica igual** —200, con su total correcto—
y solo **`POST /orders` la rechaza**, con un 422 `line_event_required`. Es decir: el cliente veía una
cesta perfecta y descubría el problema **en el botón de pagar**, sin ninguna pantalla donde
arreglarlo. Ese era el bloqueante que el ESTADO llevaba tres pasos declarando.

**(b) Se decidió PEDIR lo que falta, no descartar la línea, y la alternativa se evaluó en serio.** Las
tres opciones estaban sobre la mesa: (1) pedirlo, (2) no restaurar las líneas de pack, (3) persistir
también las respuestas. La (3) se descartó con el owner tras medir dónde quedarían los datos:
`localStorage` en texto plano, **sin caducidad** —la única poda es por fecha de reserva pasada, que
pueden ser meses—, legible por cualquier JS del mismo origen (y la CSP es laxa a propósito, lo exigen
Alpine y Livewire) y **fuera del alcance de `User::anonymize()`**, con un agujero que ninguna purga
tapa: un invitado que configura el cumpleaños y se va no dispara ningún cambio de titular. La (2) se
descartó porque perder el día, la hora, los invitados y los complementos de un cumpleaños por haber
recargado es peor que volver a escribir un nombre.
⚠️ **Y el coste real es mínimo, medido en vivo**: del pack sembrado solo `celebrant` es obligatorio, así
que el cajón vuelve a pedir **un** campo — ni la edad ni las alergias.

**(c) La frontera con `#38(f)`: el cliente ENUMERA, el servidor DECIDE.** `pendingEventFields()` no
valida nada: mira si hay algo escrito. Quién decide si una respuesta vale sigue siendo del servidor, y
no es ceremonia — `sanitizeEventData()` aplica `preg_replace('/\D+/','')` a los campos `number`, así
que una edad contestada «cinco» **el servidor la ve vacía** y cualquier validación ingenua del cliente
la ve contestada. Ese caso exacto está fijado en un test que comprueba las dos mitades a la vez.

**(d) Los campos que se piden son EXACTAMENTE los que el servidor exige**, comparados contra
`TicketType::missingRequiredEventFields()` con el esquema REAL de `GET catalog/products/{id}`. Pedir de
menos deja que el cliente se choque con el 422; pedir de más le exige datos que nadie mirará. ⚠️ Y los
campos de **post-form** no se piden nunca aunque sean obligatorios —se rellenan semanas después, con
firma—: hay caso con uno `required` de esa fase para probarlo.

**(e) La guarda vive en el módulo, no en el `.vue`** (`CE-6`), y **va antes de preguntar nada**: una
cesta que no se puede comprar todavía no gasta dos peticiones ni una ficha de `throttle`. Solo un
`true` explícito bloquea: un valor accidental no puede parar una compra.

**(f) Las respuestas nuevas NO se persisten, y eso está blindado por partida doble.** `updateCartField()`
no llama a `persist()` **y** `saveCart()` las descartaría igualmente; el canario del RGPD de
`cart.test.js` siembra centinelas y los busca en el volcado entero del almacén, no en la clave
`event_data` de la primera línea.

**(g) El bloque nuevo es el único del cajón que la web no tiene**, y por eso reutiliza el marcado del
paso 3 (`.eventfields`): mismo control, mismo estilo, pidiendo lo mismo. El árbol del carrito con
líneas COMPLETAS no cambia —lo comprueba el gate—, así que la desviación solo existe en el estado que
la web no puede tener.

**(h) Verificado por mutación** (pedir también los opcionales → cae la paridad con el servidor; quitar
la guarda → caen dos casos del módulo) y **en vivo** contra el catálogo real.

**(i) Lo que NO entra**: los pasos 8 y 9 (la pantalla de pago y el auto-POST a Redsys), que son 4.5·2.
Con este tramo el CTA de pagar deja de llevar a un rechazo inevitable; a dónde lleva cuando todo está
bien sigue siendo 4.5·2.

## #55 · 2026-08-14 · 4.5·2 — el cajón SPA ya vende: pagar y salir hacia la pasarela
Fase 4 · paso 4.5, segundo tramo. Transcribe los pasos **8** (pantalla de pago) y **9** (auto-POST
firmado). Con él, el motor SPA recorre el embudo entero: catálogo → día → hora → cesta →
identificación → **pago → pasarela**.

**(a) Una sola petición, y no es una simplificación.** `POST /orders` admite consumiendo ficha, crea el
pedido con su ventana de retención (`AFORO-10`) y abre el cobro **en ese orden**, porque el orden es
una regla del dominio (`CheckoutOrchestrator`, `DECISIONES #37`), y devuelve el pedido **y** el
formulario firmado en la misma respuesta. Partirlo en dos llamadas desde el cliente habría
reimplementado esa secuencia en una superficie nueva, que es justo lo que `CheckoutSequenceTest`
prohíbe fuera de `app/Domain`.

**(b) ⚠️ EL HALLAZGO DEL PASO: el diff de árbol NO puede verificar el paso 9, y se demostró.** El
normalizador conserva `role`, `type`, `disabled` y los `aria-*`; **`action`, `method` y los `name` de
los campos no son atributos de contrato**. Medido por mutación: renombrar los campos firmados a
minúsculas —lo que rompe el cobro con SIS0042, con el pedido ya creado y el aforo retenido— **pasa el
diff de árbol en VERDE**. Por eso nace `SidebarPayParityTest`, que compara el formulario campo a campo
contra la respuesta real de la API. Es el mismo agujero que obligó a la paridad de enlaces del aviso de
pausa, con mucho más dinero delante.

**(c) `payment.fields` es un mapa OPACO y se trata como tal.** El cajón no conoce
`Ds_MerchantParameters` ni `Ds_Signature`: itera el mapa y emite un campo oculto por entrada, con sus
valores intactos. Eso hace dos cosas a la vez — impide que nadie «normalice» un valor que la firma
cubre, y deja el paso 9 preparado para el segundo driver de pasarela de Fase 6 sin tocar una línea.
Verificado en vivo: un pedido real devuelve `HMAC_SHA512_V2` y el módulo lo emite tal cual.

**(d) El paso 8 se parece al carrito lo justo para equivocarse.** Cuatro diferencias que el diff sí ve
y que invitan a reutilizar el componente: la lista lleva `cart--summary`, **no hay botón de quitar**
—el pedido está a un clic de retener aforo—, el precio va en un `<span>` **sin clase**, y el pie de
aviso no lleva ni «añadir otra reserva» ni el aviso de «carrito listo».

**(e) La banda `bk-paybreakdown` sale de `SHELL_BLOCKS_NOT_YET_IN_SPA`**, donde estaba declarada desde
4.3·1. Va FUERA del scroll y **pegada encima del pie**: `.bk-paybreakdown + .bk-foot` es un selector de
hermano adyacente, así que un nodo entre las dos le quita el borde que las une sin que falte ninguna
clase. Y la apaga el aviso de pausa, igual que a la banda de progreso y al pie: la misma condición
gobierna los cuatro sitios.

**(f) A partir del 201 el pedido EXISTE, y eso cambia cómo se tratan los fallos.** Si el formulario
viniera mal, fingir que no ha pasado nada dejaría al cliente creyendo que puede reintentar desde cero
con una plaza retenida a su nombre. Se avisa con el mismo texto que el 502 del puerto —«no hemos podido
iniciar el pago»— y **se conserva el código del pedido**, que es lo único que permite recuperarlo. La
cesta se vacía y se persiste vacía en ese mismo momento: una recarga no puede resucitarla y hacer que
alguien compre dos veces lo mismo.

**(g) Los doce motivos de rechazo se traducen desde el CÓDIGO, no desde la clave.** El componente
Livewire recibe la clave del diccionario (`__($e->getMessage(), $e->context)`); un cliente de API
recibe el código estable y tiene que volver a la clave. El mapa inverso vive en `pay.js` y
`SidebarPayParityTest` **recorre el enum entero del servidor**: un motivo nuevo que nadie mapee lo
nombra el test, en vez de salir como un aviso genérico en la pantalla de pagar. Verificado por mutación.
Los `params` (`:product`, `:when`, `:max`) los pone el dominio y el cliente solo los interpola.

**(h) La pausa cierra su último residual.** `reservations_paused` no compone mensaje: pide releer
`GET /booking/status`, que es lo que el contrato pedía tras un 409 y lo que quedaba pendiente desde
4.3·3.

**(i) El techo del bundle sube de 135 a 150 KiB, medido.** Los dos pasos costaron **5,51 KiB**
—aislados construyendo con y sin ellos—, mucho menos que los 9,97 del primer formulario porque el
runtime que aquel trajo ya estaba dentro. Con 135 el chunk se pasaba por 1,09 KiB; 150 deja **13,9 KiB**
para lo único que queda de la fase: las tres pantallas de desenlace (4.6) y el widget de Turnstile.

**(j) Lo que NO entra**: los pasos 6, 10 y 11 y la vuelta de Redsys (4.6). ⚠️ **Entre 4.5 y 4.6 no se
despliega el flag**: quien pague en medio volvería a un cajón mudo, y ahora el cajón sí puede cobrar.

## #56 · 2026-08-14 · 4.6·1 — la vuelta de la pasarela pinta la reserva creada
Fase 4 · paso 4.6, primer tramo. Transcribe el paso **6** —la pantalla a la que se vuelve tras pagar—
y cierra la costura del desenlace por el lado del cliente. El corte es el de siempre, **por
DEPENDENCIA**: el sondeo del paso 11 aterriza en el 6, así que el 6 va primero; los pasos 10 y 11 son
4.6·2.

**(a) Este paso no compone: TRADUCE.** El resumen entero lo publica ya el servidor desde 4.0b —importes
por reserva incluidos—, así que `outcome.js` solo cambia de vocabulario. Nada se recalcula (`PAY-12`):
`park_cents` viene compuesto y **`total − online` no es lo mismo**, `shows_deposit_note` son TRES
condiciones ya resueltas por `ReservationFinancials`, y `guest_form_pending` **no es** el `any()` de
`items[].needs_guest_form` —el servidor descarta antes las líneas canceladas, y por eso los dos campos
se llaman distinto en el contrato—.

**(b) ⚠️ EL HALLAZGO DEL PASO: un test de cadena pasó sin probar la cadena, otra vez.** El caso que
afirmaba «cada respuesta del pack cae bajo SU reserva» **pasaba en verde con el cliente emparejando por
POSICIÓN**, medido por mutación. El motivo es que hoy `GET orders/{code}/event-data` devuelve las
reservas en el mismo orden que las líneas del pedido, así que llave y posición coinciden **por
casualidad**. El contrato no promete ningún orden: el caso bueno **le da la vuelta al sobre** y exige el
mismo resumen. Es la lección de 4.0b·5 repetida, y el fallo que tapaba es feo —el nombre de un niño bajo
la reserva de otro, con el árbol idéntico y el gate en verde—.

**(b.bis) Y la mutación con la que se descubrió también era mala.** El primer intento emparejaba con
`Object.values(answers)[i]`, y **pasó**: en JavaScript las claves de objeto que parecen enteros se
ordenan ASCENDENTEMENTE, así que el mapa se reordenaba solo y devolvía lo correcto. Una mutación que no
rompe nada no demuestra nada; la buena es recorrer las dos listas en paralelo, que es el fallo que un
programador escribiría de verdad.

**(c) La fila del resumen se EXTRAE, y la pregunta que lo decide es «¿hay dos copias?»** (4.0b·5). El
paso 6 y el paso 8 emiten **el mismo árbol** hasta el `<span>` sin clase del precio y el
`<div class="cart__lines">` que se pinta aunque quede vacío. Nace `SummaryLine.vue`, y la extracción se
verificó por mutación: ponerle al precio la clase del carrito deja en rojo **los dos** pasos a la vez.
Dos copias de un marcado que el CSS mira por ESTRUCTURA divergen en silencio, con el diff de cada
pantalla verde por separado.

**(d) ⚠️ El desenlace MANDA sobre la cesta, y el orden natural de la SPA era el contrario.**
`Purchase::mount()` coloca el paso 4 si hay cesta y **después** deja que la vuelta de la pasarela lo
pise; el motor SPA restauraba la cesta al final y navegaba al carrito. No es teórico: la cesta vive en
`localStorage`, así que **otra pestaña puede haberla llenado mientras se pagaba en ésta**, y quien
volvía de pagar aterrizaba en un carrito en vez de en su reserva. La precedencia se escribe en
`machine.js` (`isOutcome()`) y no en el componente, porque dentro de un `.vue` no tendría red (`CE-6`);
su caso la ata a `stepForOutcome()`, que es quien traduce lo que escribe `SidebarEntry`.

**(e) `confirmation: null` es un estado legítimo, no un fallo.** El Blade pinta la pantalla igual sin
resumen —quedan el código del pedido, el aviso del correo y el CTA— y es lo que ve quien perdió la
sesión entre la ida a la pasarela y la vuelta. Enseñarle «ha fallado algo» a quien acaba de pagar sería
mucho peor, así que `loadConfirmation()` devuelve `null` sin componer ningún aviso. Tiene caso propio en
el diff de árbol: es la rama que se olvida.

**(f) Son DOS peticiones y la segunda no es opcional.** Las respuestas del pack son datos de un MENOR y
del art. 9, así que `GET orders/{code}` **no las lleva** (hay test de ello) y viven en su endpoint
aparte. Van en paralelo —son independientes y el cliente acaba de pagar— y un fallo de la segunda **no**
tumba el resumen: es un bloque menos bajo cada línea, no una pantalla rota.

**(g) DIVERGENCIAS DECLARADAS, cada una con su caso** para que no puedan cambiar por el camino de
arreglar otra cosa. **(1)** La API acota el resumen a la fase `booking` (§4.4.6, `#39`): lo que un
operador rellene del post-form desde el panel sale en el cajón Livewire y no en el SPA. **(2)** El Blade
ramifica sobre la columna `status` y la API publica `displayStatus()`, así que un pedido pendiente con
el hold vencido hace que Livewire diga «pendiente de pago» y el cajón SPA **no diga nada** — prometer un
pago pendiente sobre una plaza que ya volvió al inventario es peor que callar.

**(h) El techo del bundle NO sube.** El paso costó **4,70 KiB** medidos (140,79 de 150 KiB), menos de lo
que ocupa su marcado porque la extracción de la fila le quitó la suya a la pantalla de pagar. Quedan
**9,21 KiB** para los pasos 10 y 11 y el widget de Turnstile; las dos pantallas que faltan no llevan
lista ni importes.

**(i) El centinela del chunk cubre el cableado que ningún test ejecuta.** `Sidebar.vue` no lo renderiza
nadie —lee `window.Alpine`—, así que `purchase__confirm` y `/event-data` entran en `ENGINE_MUST_KNOW`.
Verificado por mutación: quitar la llamada a `loadOutcome()` borra `/event-data` del bundle (Rollup poda
el import que deja de usarse) y el test lo nombra.

**(j) Verificado en vivo, no solo en la suite**: con `sidebar.engine = spa`, la home sirve el payload de
montaje con su `orderCode`, el login por API deja sesión a través de nginx y las respuestas REALES de
`GET /orders/{code}` y `/event-data` pasadas por el módulo REAL componen el resumen correcto.

**(k) Lo que NO entra**: los pasos 10 (denegado + reintento) y 11 (verificando + sondeo), que son 4.6·2.
⚠️ **El flag sigue sin desplegarse**: quien vuelva con un pago denegado o con un terminal *data-less*
todavía se encuentra un cajón mudo.

## #57 · 2026-08-14 · 4.6·2 — los otros dos desenlaces, y la máquina que decía otra cosa
Fase 4 · paso 4.6, segundo tramo. Transcribe los pasos **10** (pago denegado, con su reintento) y
**11** (verificando, con su sondeo). **Con él, la transcripción de la Fase 4 queda completa**: el cajón
SPA recorre el embudo entero y sabe volver de los tres desenlaces posibles.

**(a) ⚠️ EL HALLAZGO DEL PASO: la máquina de estados llevaba desde 4.1 con una transición INVENTADA.**
`TRANSITIONS[DECLINED]` decía `[CATALOG, PAY]` y las dos mitades estaban mal, medido contra
`Purchase::retryPayment()`: el reintento **no vuelve a la pantalla de pago** —reabre el cobro sobre un
pedido que ya existe y sale DIRECTO a la pasarela (`$this->step = 9`)—, y faltaba una tercera salida,
IDENTIFICARSE, para la sesión que se perdió entre la vuelta y el clic (`$this->step = $user ? 1 : 5`).
Era una suposición razonable de cuando el paso no estaba transcrito y nadie podía medirlo; con
`DECLINED → REDIRECTING` ausente, el reintento habría compuesto su formulario firmado y **el cajón se
habría quedado quieto en el paso 10**, porque `go()` rechaza en silencio.

**(b) El motivo del rechazo NO necesita tabla de traducción, y saberlo ahorró un mapa.** El servidor
publica `declined_reason` con `RedsysResponseCode::reasonKey()`, que devuelve exactamente la clave bajo
`tickets.payment_failed.reasons.*` — el mismo literal que pinta el Blade, del mismo fichero de `lang/`,
y **ya viaja en el payload de montaje** porque el grupo `tickets` va entero. Lo que sí hace falta es la
caída a `default`: `i18n.js` devuelve cadena vacía si la clave no existe, así que un motivo nuevo en el
servidor pintaría el rótulo «Motivo:» **con nada detrás**. `SidebarOutcomeParityTest` recorre
`REASON_MAP` completo en los tres idiomas.

**(c) ⚠️ El bloque del motivo se pinta SIEMPRE que hay sesión, y el Blade engaña al leerlo.** Su
`@if ($declinedReasonText)` parece condicionar a «hay motivo conocido», pero `reasonText()` **nunca
devuelve null** —cae a `default`—, así que con sesión y con pedido el bloque está siempre. Condicionarlo
en Vue a «motivo conocido» habría emitido un nodo de menos justo en el caso más frecuente: el rechazo
del que la pasarela no dice el porqué. Tiene caso propio.

**(d) ⚠️ El sondeo solo mira DOS salidas, y ampliarlo sería un error.** `checkPaymentStatus()` reacciona
a `paid` y a `expired`; **un intento `failed` con el pedido todavía `pending` no mueve nada**, porque la
notificación server-to-server puede estar en vuelo — y esta pantalla existe precisamente para ese caso.
Saltar al paso 10 ahí le diría «no has pagado» a quien sí pagó. Lo mismo con un fallo al preguntar: un
401 pasajero, un 429 o un corte de red no son un desenlace.

**(e) Un intervalo suelto es un fallo que ningún diff puede ver.** El sondeo se para en TRES sitios: el
observador del paso, `onUnmounted` y el propio `poll()`, que comprueba dónde está el cajón antes de
preguntar —de modo que una fuga dura como mucho un tick—. Y `startPolling()` es idempotente: llamarla
dos veces dejaría dos temporizadores preguntando a la vez.

**(f) ⚠️ El centinela del bundle tuvo que cambiarse porque el obvio NO discriminaba.** `/payment-status`
lo usan los DOS pasos —el 10 pide ahí su motivo—, así que desconectar el bucle del 11 dejaba la cadena
en el chunk y el gate en verde: el cliente se quedaría mirando «verificando» para siempre. Medido:
`setInterval` aparece **una sola vez** en el chunk y desaparece al desconectar el sondeo. Ni Vue ni
Pinia lo usan hoy.

**(g) Las dos URLs de estas pantallas viajan del SERVIDOR**, en un `urls` nuevo del payload de montaje.
`href` no es atributo de contrato del diff de árbol —lo enseñaron el WhatsApp del aviso de pausa y el
enlace de registro—, así que un cajón que mandara «escribirnos» o «ver mis reservas» a un 404 pasaría el
gate en verde. Quemarlas en el JS habría sido la segunda fuente de algo que decide `routes/web.php`.

**(h) ⚠️ DEUDA DE PRODUCTO destapada al transcribir, no creada aquí**: un reintento denegado —pausa,
frecuencia o 502— deja el botón **mudo**. Medido contra el HTML: `retryPayment()` escribe el motivo en
`errors.cart` y el bloque del paso 10 no lo pinta (ni el pie, que es nulo en los pasos de resultado).
El cajón SPA lo transcribe fiel, que es lo que pide la paridad, y la fila queda en `DEUDA.md`: el
arreglo cambia la copia de una pantalla del camino del dinero en tres idiomas, y eso es del owner.

**(i) DIVERGENCIA DECLARADA, la tercera de la fase**: Livewire busca el último `Payment` **fallido** del
pedido y la API solo publica el motivo si el ÚLTIMO intento es el rechazado. Con un reintento en vuelo,
el Blade sigue diciendo «tarjeta caducada» sobre un cobro que está esperando respuesta. Acierta la API
—lo dice su contrato—, así que se declara en vez de copiarse; desde la pantalla no es alcanzable,
porque al reintentar se sale al paso 9.

**(j) Dos casos de test volvieron a pasar por CASUALIDAD**, y los dos se arreglaron midiendo: el de los
cuatro «no» del reintento comparaba con el titular **equivocado** —la segunda compra dejaba a otro
usuario autenticado, así que Livewire respondía `NOT_RETRYABLE` por anti-IDOR y tres de los cuatro
escenarios acertaban por accidente—, y la pausa se pegaba entre iteraciones e impedía comprar. Es la
tercera vez en la fase que un test de cadena pasa sin probar la cadena.

**(k) El techo del bundle NO sube**: el tramo costó **5,06 KiB** (145,85 de 150). Quedan **4,15 KiB**
para el widget de Turnstile (4.4b·2), que es un contenedor y un script EXTERNO, así que basta. En 4.7
este número debería BAJAR: se va el motor Livewire.

**(l) Lo que NO cierra**: el flag **sigue sin desplegarse**. Ya no es porque falte pantalla —están las
once—, sino porque falta la verificación de punta a punta con la pasarela en sandbox y un navegador
(§6 del spec) y el widget de Turnstile (4.4b·2).

## #58 · 2026-08-14 · El bloqueo de scroll tiene un solo dueño, y lo que apareció al mirarlo
Cierra el último ítem del plan de verificación de Fase 4 (§6 del spec) que seguía sin cumplirse: «`no-scroll`
del `<body>` con **un solo dueño** declarado». No es pulcritud, y las tres cosas que aparecieron al
medirlo tampoco.

**(a) ⚠️ Eran SEIS escritores y el fallo se alcanza con dos clics.** `body.no-scroll` lo escribían por su
cuenta el cajón de compra, el modal de auth, el cajón del nav móvil, el modal de ofertas, el modal de
«gestionar reserva» de Mis pedidos y un `x-init` suelto del layout. Con un booleano y varios escritores,
**el último en cerrar manda**: con el cajón de compra abierto, su bloque de cuenta ofrece «Iniciar
sesión» y abre el modal de auth; al cerrarlo, su `remove()` desbloqueaba el scroll **con el panel
todavía delante**. La SPA lo hace más probable, no menos: el cajón se queda montado.
La salida es un cerrojo con LLAVES —`resources/js/ui/scroll-lock.js`, expuesto como store
`scrollLock`—: cada superpuesto pide y suelta la suya y la clase está puesta mientras quede alguna.

**(b) La llave es por INSTANCIA, no por tipo.** «Mis pedidos» pinta un modal por pedido, así que su
llave lleva el id dentro: con una compartida, abrir A, abrir B y cerrar A soltaría el scroll con B
delante — el mismo fallo dentro de una sola pantalla.

**(c) El estado INICIAL también es del dueño, y ahí había una asimetría.** Dos superpuestos pueden venir
ya abiertos del servidor —el cajón por `/entradas` o por un desenlace pendiente, y el modal de auth por
`/registro`— y **solo el primero bloqueaba**, con el `x-init` del layout; el modal de auth abierto al
cargar dejaba la página moviéndose por detrás. Pedir la llave al arrancar Alpine arregla los dos y retira
el sexto escritor.

**(d) La manipulación de la clase vive DENTRO del dueño** (`installScrollLock()`), no en `app.js`. Si el
`apply` que toca el DOM se escribiera fuera —que es lo natural—, ese fichero volvería a ser un escritor
y la guarda no podría distinguir el cableado legítimo del siguiente que se cuele. El núcleo sigue sin
conocer el DOM, que es lo que lo hace probable con `node --test`. Medido sobre el bundle SERVIDO:
`no-scroll` aparece **una sola vez**.

**(e) ⚠️ Ampliar el contrato de árbol NO basta: hay que llevar el caso al estado que lo hace aparecer.**
Al cerrar los apartados de accesibilidad de §6 se añadió `aria-current` a los atributos de contrato del
diff, y por mutación se comprobó que **no servía de nada**: el caso del calendario no elige día, así que
ningún motor emitía el atributo y borrarlo del componente pasaba en verde. Es la lección de la acotación
del selector de cantidad (4.2) otra vez. Ahora hay un caso con día elegido.

**(f) ⚠️ Y `aria-expanded` NO puede ser atributo de contrato, medido.** Los dos motores lo declaran, pero
por caminos que el HTML servido no hace comparables: en Livewire es un binding de Alpine —que el
normalizador descarta como andamiaje— y en Vue lo renderiza el SSR. Compararlo dejaba el pie en rojo por
una diferencia que no existe en el navegador. Tiene caso propio, sobre el marcado de cada motor.

**(g) Al mirarlo apareció una divergencia REAL de accesibilidad, y se arregló**: el botón del desglose de
la señal del pie llevaba `:aria-expanded` en el Blade y **nada** en el cajón SPA, así que un lector de
pantalla no decía si el desglose estaba desplegado. El diff no podía verlo por partida doble.

**(h) Lo que queda ABIERTO y se anota, no se arregla**: §6 pide «foco al cambiar de paso» y **ninguno de
los dos motores lo hace** —cero llamadas a `focus()` al mover de paso, en los dos—. El foco de PANEL sí
está (`a11yPanel`: primer foco al abrir y trampa de Tab). Es un hueco heredado, no una regresión de la
SPA, y por eso va a `DEUDA.md` en vez de a un paso: nombrarlo importa para que nadie lea §6 y crea que
el motor nuevo lo perdió.

## #59 · 2026-08-14 · El extremo a extremo con navegador, y los CUATRO fallos que solo él veía
Se ejecutó por fin la verificación que `specs/sidebar-spa.md` §6 pedía desde el principio: recorrer la
compra con los DOS motores, contra la pasarela REAL en sandbox, con un navegador de verdad. **Encontró
cuatro cosas, tres de ellas rotas de raíz, y ninguna la veía la suite.** La fase se daba por transcrita
y el motor SPA **no funcionaba en producción**.

**(a) ⚠️ EL FALLO MAYOR: el desenlace del pago nunca llegaba al store, así que TODA la Fase 4.6 era
invisible.** `index.js` aplicaba `machine.enterOutcome(boot.outcome)` **después** de `store.boot()`, y
el store no observa la máquina: la COPIA, en `boot()`, `go()` y `enter()`. Resultado: la máquina en el
paso 6 y el store en el 1 — y Vue pinta desde el store. **Quien volvía de pagar veía el catálogo.** Las
tres pantallas de desenlace, sus paridades y sus mutaciones estaban perfectas; nadie ejecutaba la
secuencia de montaje. Arreglado (el desenlace se aplica ANTES de arrancar el store) y con red propia:
`store.test.js` reproduce esa secuencia con Pinia en `node --test` — que se podía hacer desde 4.1 y no
se hizo.

**(b) ⚠️ El motor SPA no montaba cuando el cajón NACÍA abierto.** `bootSpaEngine()` colgaba solo de
`open()`, que en ese camino no se llama nunca. Los dos disparadores son `data-purchase-open`: el enlace
profundo `/entradas` y **la vuelta de la pasarela**. Con `sidebar.engine = spa`, las dos abrían el cajón
con el hueco **VACÍO**. Arreglado en el arranque de Alpine, junto al bloqueo de scroll inicial.

**(c) ⚠️ El catálogo del cajón SPA no enseñaba ni un producto.** El CSS colapsa `.catalog-acc__body`
con `grid-template-rows: 0fr` y solo `.is-open` lo abre. El Blade la emitía **siempre** —vía un
`:class` de Alpine cuyo `isOpen()` devuelve `true` fijo desde #P6— y `CatalogStep.vue` no la emitía
nunca. **El diff de árbol no podía verlo**: el normalizador descarta los `:*` como andamiaje, así que
los dos árboles salían idénticos. Es el mismo agujero que `aria-expanded` (`#58(f)`), esta vez tapando
el paso 1 entero. **Arreglado en el ORIGEN**: la clase pasa a estática en los DOS motores, con lo que el
binding muerto desaparece y **el gate vuelve a verla** — verificado por mutación.

**(c.bis) Y ese arreglo destapó un efecto de alcance en otro gate.** `SidebarTokenBudgetTest` deduce
«qué CSS es del sidebar» leyendo los `class="…"` del Blade; al entrar `is-open` —un modificador de
ESTADO compartido con el velo y los modales— el escaneo se comió medio `site.css` y el recuento de
colores crudos subió de 3 a 4 sin que nadie tocara una línea de CSS. Los `is-…` quedan excluidos.

**(d) DIVERGENCIA de conducta, declarada y NO arreglada**: elegir día. En Livewire `selectDate()` solo
lo MARCA y hay que pulsar «Continuar» (`goToTime`); en la SPA avanza sola al paso 3, así que el CTA
«Continuar» del paso 2 es **inalcanzable**. Ningún test podía verlo: el diff renderiza cada paso por
separado. Se anota en `DEUDA.md` en vez de tocarse: quitar un clic puede ser mejor UX, pero es un cambio
de producto y no estaba decidido.

**(e) Lo que SÍ funciona, medido de punta a punta y en los dos motores**: catálogo → día → hora →
cantidad → campos del pack → carrito → pago → pasarela real → vuelta → **paso 6 con su resumen**. El
cobro online es la **SEÑAL** (30,00 € de una reserva de 165,00 €), y los dos pedidos quedan
**IDÉNTICOS en BD** —estado, total, online, pendiente en puerta, líneas, subtotales y respuestas—, que
es exactamente el criterio que pedía §6. El paso 11 se verificó con la vuelta *data-less* real: pantalla
de verificación, **sondeo cada 5 s**, salto solo al paso 6 al pagarse el pedido y **el sondeo PARA**.

**(f) El andamio es desechable y está fuera del repo**, a propósito: Playwright + Chromium dentro del
contenedor (que ya trae las librerías de Chromium para Dusk), con un puente TCP 8081→80 para que
`APP_URL` y las cookies resuelvan igual que fuera. **No se añade al `package.json` ni al gate**: meter un
navegador de 115 MB y un tercero en el camino crítico del `pre-push` es una decisión aparte, con su
coste. La receta está en `VERIFICACION-E2E-CAJON.md`.

**(g) Lo que el andamio aprendió de la pasarela, y que ahorra una hora**: el botón «Pagar» solo se
habilita si se rellena el **titular** (con la tarjeta perfecta sigue `disabled`); hay que **teclear**, no
`fill()`, porque la pasarela rellena sus campos ocultos desde eventos de teclado; el sandbox mete
**siempre** un simulador EMV 3DS por medio; **denegar el 3DS NO vuelve al comercio** —devuelve al
formulario de tarjeta—, así que el KO se provoca cancelando; y cada recorrido abortado deja un pedido
pendiente, de modo que a los cinco el **tope de pendientes** deniega el checkout y parece un fallo del
andamio cuando es la app haciendo lo correcto.

## #60 · 2026-08-14 · 4.7·1 — el manifiesto congelado, y lo que de verdad cuesta la retirada
Fase 4 · paso 4.7, primer tramo. **No retira nada**: pone la red que tiene que existir ANTES de retirar,
y mide el tamaño real del paso. El corte es por dependencia, como toda la fase.

**(a) ⚠️ El problema que resuelve, dicho sin rodeos: toda la red de la fase compara contra Livewire, y
Livewire se va.** Catorce ficheros de paridad, más el diff de árbol, dicen «los dos motores emiten lo
mismo». Al borrar `Purchase.php` se quedan sin uno de los dos y **pasarían en verde para siempre**. Un
manifiesto congelado —la foto del árbol VERIFICADO, tomada mientras conviven— es lo que hace que el
contrato visual sobreviva a la retirada. Y solo se puede tomar ahora.

**(b) Mientras los dos motores vivan se comprueban las DOS cosas**: motor contra motor y motor contra
manifiesto. Es lo único que impide que la foto envejezca en silencio; al retirar Livewire quedará solo la
segunda, y habrá sido correcta el día que se tomó. Verificado por mutación con **el caso que importa**:
el mismo cambio aplicado a los DOS motores —invisible para el diff entre ellos, que es exactamente el
escenario de después— lo caza el manifiesto.

**(c) La foto son 30 entradas y 696 nodos** (`tests/Fixtures/sidebar-dom-manifest.json`, 26 kB), y se
regenera a propósito: `MANIFEST_REFRESH=1 php artisan test --filter=SidebarDomContractTest`. Un cambio de
interfaz deliberado obliga a regenerarla **y a decirlo en el commit**; uno accidental sale en rojo.
Hay guarda contra entradas HUÉRFANAS —claves de casos borrados o renombrados—, también por mutación:
sin ella el fichero engorda dando una falsa sensación de cobertura.

**(d) ⚠️ Y el dato que cambia la planificación: la retirada NO es «borrar un fichero».** Medido:
**26 ficheros de test ejecutan `Livewire::test(Purchase::class)`**, con ~160 casos. Se reparten en tres
familias y cada una necesita una decisión distinta, que es lo que hará 4.7·2:
· **Mueren con el componente** — los que prueban SU interfaz: `PurchasePanelTest` (39 casos),
  `SidebarV2Test` (17), `PurchaseCatalogGroupingTest` (10)… ⚠️ Ojo: varios prueban CONDUCTA que hoy vive
  en la SPA o en la API; borrarlos sin comprobar el equivalente es pérdida neta de cobertura.
· **Se re-apuntan al servidor** — los que usan el componente como MERO conductor de dominio
  (`SlotOfferTest`, `AvailabilityTest`, `QuoteTest`, `CartPricerTest`…): deben conducir por la API o por
  el dominio, que es donde ya vive la regla.
· **Las nueve PARIDADES** — o congelan (esta es la primera) o se re-apuntan al contrato del servidor
  (`lang/`, OpenAPI), que es contra quien de verdad comparan los textos y los importes.

**(e) Lo que NO entra aquí, y el orden importa**: retirar el componente y el puente es 4.7·2, y el flag
4.7·3. ⚠️ **La condición que este punto añadía —«dejar el flag en `spa` en uso real unos días»— quedó
RETIRADA el 2026-08-15 por VACÍA: no hay instalación viva ni canal de despliegue con los que cumplirla.
Ver `#62`.** Lo que ordena 4.7·2b es el contador de `PurchaseRetirementTest`, no el calendario.

## #61 · 2026-08-15 · 4.7·2a — el inventario de la retirada deja de crecer
Segundo tramo de la retirada, y otra vez sin borrar nada del motor que hoy vende. Ataca el riesgo
propio de un desmontaje LARGO: que mientras dura, alguien siga construyendo encima.

**(a) Nace `PurchaseRetirementTest`, y la lista solo puede ENCOGER.** Vigila las dos direcciones: que no
aparezcan tests NUEVOS conduciendo por `Livewire::test(Purchase::class)` —lo que construyan encima habrá
que rehacerlo o se perderá— y que ninguna entrada declare una dependencia que ya no existe, porque
entonces la lista deja de decir cuánto falta. Es la disciplina de las baselines del arch-test de Fase 2.
Verificado por mutación en los dos sentidos. ⚠️ Y la guarda se excluye a sí misma del escaneo: declara el
patrón, así que se contaba como infractora — la misma trampa que `SidebarEntryTest` ya documentaba.

**(b) ⚠️ El inventario NO clasifica, a propósito.** Qué hacer con cada fichero es una decisión por
fichero y vive en `#60(d)`; fijar la familia en un test sería congelar una decisión que aún no está
tomada, y las que sí lo están se han tomado MIDIENDO, no por el nombre del fichero.

**(c) Primer fichero fuera de la lista, y por el motivo correcto.**
`SlotOfferTest::test_public_purchase_flow_excludes_past_times_today` decía «end-to-end del flujo público
REAL» y conducía por el componente, pero su sujeto es una regla de DOMINIO (`SlotOffer`, `AFORO-02`).
Tras la retirada, el flujo público **es** `POST availability/{producto}/times` — lo que pide el cajón—,
así que ahí se re-apunta. **Un test de dominio no debe morir porque muera una vista**, y ese acoplamiento
estaba mal desde antes de existir 4.7.

**(d) Y una clasificación que se resolvió MIDIENDO, no suponiendo**: los dos casos de
`AddonDependencyTest` que conducen por el componente prueban la poda de dependencias «de punta a punta»,
y `Api\V1\CatalogAddonsTest` ya la cubre **mejor** —recorre la cadena entera, no un solo nivel—. O sea:
mueren con el componente sin pérdida de cobertura. Se deja escrito, no se ejecuta: **no se borran tests
del motor que hoy vende**. Ese borrado es 4.7·2b, después de que el flag lleve tiempo en `spa`.

## #62 · 2026-08-15 · Retirada de una condición VACÍA: no hay producción con la que curtir el motor
Al planificar 4.7·2 se puso una condición sensata en apariencia: **«antes de borrar `Purchase.php`,
dejar el flag en `spa` en uso real unos días»** (`#60(e)`, y repetida en el tracker y en `ESTADO`).
Se comprobó y **no puede cumplirse**. Se retira.

**(a) Lo medido**: este repo es **el PRODUCTO**, sin marca de cliente (`CLAUDE.md`, primera línea);
**no hay canal de despliegue** —ni `.github`, ni script; el de `INSTALACION-CLIENTE.md` §1 sigue siendo
un `[DECISION-PENDIENTE]`—; y las menciones a «producción» de `ESTADO.md` son del **cliente ORIGEN**,
que vive en otro repo al que este no despliega nada (`DECISIONES #1`). **No hay tráfico.**

**(b) Por qué importa y no es una formalidad.** Una condición que no puede cumplirse no es prudencia:
es un bloqueo indefinido disfrazado, y en un repo de agentes es peor todavía —el siguiente lee «espera»
y espera—. `docs-check` la habría dejado pasar sin decir nada: no valida el sentido de una frase.

**(c) Lo que aquel margen protegía de verdad era tener INTERRUPTOR de vuelta**, y el interruptor lo
mata el propio 4.7·2b por construcción: esperar no lo conserva, solo lo aplaza. Lo que sí conserva el
interruptor es el ORDEN —el flag se retira el último, en 4.7·3—, y eso sigue en pie.

**(d) Lo que ordena 4.7·2b no es el calendario, es el CONTADOR.** `PurchaseRetirementTest` dice cuántos
ficheros conducen todavía por el componente (hoy **25**) y no le deja subir. Reclasificar hasta 0 —cada
uno con su evidencia: dónde vive la cobertura si muere, a qué superficie se re-apunta si sobrevive— y
borrar entonces. Eso no es esperar: es el trabajo.

**(e) La regla que deja, y vale para cualquier fase**: una condición de bloqueo tiene que nombrar el
**hecho observable** que la levanta. «Cuando haya rodado un tiempo» no lo es; «cuando el contador llegue
a 0» sí. ⚠️ **Con un corolario que costó descubrir al día siguiente** (`#63`): si el hecho observable lo
mide un test, hay que comprobar que lo mide ENTERO. El contador decía 25 y eran 32.

## #63 · 2026-08-15 · 4.7·2b·1 — el contador de la retirada medía menos de la mitad de las formas
Primer tramo de 4.7·2b. **No borra el componente**: arregla el instrumento que dice cuándo se puede
borrar, y saca los tres primeros dependientes. El corte es por dependencia, como toda la fase: un
contador que miente no ordena nada.

**(a) ⚠️ Lo medido, y por qué importa.** `PurchaseRetirementTest` reconocía **un solo literal**,
`Livewire::test(Purchase::class)`, y declaraba **25** dependientes. El acoplamiento real eran **32**
ficheros. Se le escapaban tres cosas, todas ejecutables:
· **cuatro que conducen con otro receptor** — `Livewire::actingAs($u)->test(Purchase::class)`:
  `ReservationPauseGuardTest`, `DepositSurfacesTest`, `PurchaseRetryAndPollingTest`, `RedsysIdaTest`.
  Son 21 llamadas al componente que el inventario daba por inexistentes;
· **uno que lee una constante suya** — `OrderCreatorTest` usaba `Purchase::MAX_LINES_PER_CART`;
· **dos que dependen de sus VISTAS** — `SidebarSeamTest` y `SidebarTokenBudgetTest` leen
  `purchase.blade.php`, que se va con el componente.
La promesa del contador es **«0 ⟹ `Purchase` se puede borrar»**. Con siete ficheros invisibles, llegar
a 0 no habría levantado ningún bloqueo: habría roto la suite al borrar. **El crecimiento de la lista es
la corrección, no una regresión** — y por eso la disciplina de «solo encoge» se mantiene intacta a
partir de aquí.

**(b) La forma de medir, y las dos mutaciones que la sostienen.** El escaneo **tokeniza y descarta
comentarios**, y mira tres formas (conduce · nombra la clase · depende de sus vistas). Las dos
mutaciones se ejecutaron:
· con la lista vieja de 25 y el escáner nuevo, el test cae **nombrando exactamente los siete**;
· sin descartar comentarios entran **dos falsos positivos** (`SidebarEntryTest`,
  `SidebarMoneyParityTest`), que solo mencionan el componente para contar de dónde salió una regla y
  **no se rompen al borrarlo**. Medir sobre el texto crudo habría inflado el inventario con historia.
⚠️ Y la guarda de la guarda pasa a ser **una por forma**: la vieja («que encuentre más de 10») pasaba
en verde con la mitad de las formas ciegas, que es justo lo que ocurrió.

**(c) Los tres primeros dependientes salen, cada uno por un motivo distinto** (32 → **29**):
· **`OrderCreatorTest` se re-apunta al DUEÑO de la regla.** Leía el tope de líneas del componente, que
  solo lo refleja para la UI; ahora lo lee de `OrderCreator`, que es quien lo define **y el sujeto del
  propio test**. Un test de servidor no debe nombrar una clase de interfaz para leer un invariante de
  servidor.
· **`AvailabilityTest` pierde sus dos casos de paridad, y no hay hueco.** Comparaban las horas y el tope
  del sidebar contra los de la API: **la pregunta desaparece cuando desaparece la segunda
  implementación** —el cajón SPA es cliente de ese mismo `POST availability/{id}/times` y acota el
  selector con `max_quantity`—. Y sus números de control siguen fijados por dos casos que ya existían
  (cesta de 4 → 6 y cesta de 10 → 0), así que el borrado no deja hueco; que la web pida el máximo al
  contrato lo sigue vigilando `ModuleContractsTest`.
· **`QuoteTest` se RE-APUNTA en vez de morir, y ahí está la diferencia.** Su paridad de cesta mixta era
  el **único** caso del fichero que ejerce la SUMA de varias líneas con reglas distintas; se conserva
  como caso de API con los mismos importes (295,00 · 125,00). Su hermano de mutación sí muere: el
  control era 5 × 40,00 y el fichero ya multiplica 2 × 40,00.
⚠️ **La regla que deja este punto**: al retirar una paridad hay que separar **lo que comparaba** de **lo
que además afirmaba**. Lo primero se va con el segundo motor; lo segundo hay que buscarlo en el fichero
antes de borrar, y si no está, se queda.

**(d) Y re-apuntar encontró un fallo en la propia doc del cambio, medido con la mutación.** Al escribir
el caso conservado se dio por hecho que `deposit_cents` de una línea SIN señal incluiría sus
complementos: **no los incluye** (80,00, no 95,00) y aun así **sí se cobran online**
(`CartPricer`: `online += deposit + (hasDeposit ? 0 : addons)`). O sea: **sumar los `deposit_cents` de
las líneas no da `online_amount_cents`** —110,00 frente a 125,00—, que es exactamente la resta que
`#48` prohíbe al cliente. Queda fijado con su par de aserciones, y la mutación demuestra que es el
ÚNICO caso del fichero que lo caza: apagar ese sumando deja los otros diez en verde.

**(e) Lo que este tramo NO hace, y el orden importa.** No borra ni un test del motor que hoy vende: la
familia «muere con el componente» se va **en el mismo commit que el componente** (4.7·2b·3), para que
el motor por defecto no pase ni un día con menos red de la que tiene. Lo que sí queda hecho es su
clasificación con evidencia, en `00-REFACTOR.md`.

## #64 · 2026-08-15 · El manifiesto congelado caducaba a las 24 h, y la suite dependía del calendario
Al cerrar `#63` la suite amaneció con **tres fallos que nadie había causado**. Se comprobó antes de
tocar nada —árbol guardado, vuelta al commit anterior, los mismos tres fallos— así que no era del
trabajo de la sesión: la suite **no era determinista respecto a la fecha**, y llevaba así desde que se
escribió cada pieza. Las dos causas son distintas y las dos dejan regla.

**(a) ⚠️ La red de 4.7·1 valía UN DÍA.** `tests/Fixtures/sidebar-dom-manifest.json` es la foto del árbol
que tiene que **sobrevivir a la retirada** de Livewire (`#60`), y dos de sus treinta entradas son el
calendario. La rejilla del mes depende de HOY: cada día añade una casilla deshabilitada y cada mes
cambia la forma entera. Congelada el 14, **caducó el 15**. Una foto que se mueve sola no es una red: es
una alarma diaria, y en un repo de agentes lo que pasa con una alarma diaria es que el siguiente
aprende a regenerarla sin mirar — y ahí se pierde el contrato visual entero, en silencio.
**Arreglo**: reloj congelado en `setUp()` (`FROZEN_NOW = 2026-08-12 09:00`, miércoles de un mes que
empieza en sábado, para conservar las casillas de relleno; día 12 para que `slotsForNextDays(5)` no
cruce a septiembre) y manifiesto regenerado. **Dos medidas lo sostienen**: quitar el congelado devuelve
los dos casos a rojo, y al regenerar **cambian exactamente 2 entradas de 30** —prueba de que las otras
28 ya eran estables y de que la regeneración no tapó nada más—.

**(b) Un fixture con tarifas por día de la semana rompe el fichero los fines de semana.** `CatalogTest`
declara una tarifa `special` con `weekdays: [0, 6]`, y `CatalogReader` resuelve la tarifa de los
complementos con `Carbon::today()` **descartando el complemento de PAGO sin precio para esa tarifa**
—regla correcta y ya documentada ahí: ofrecerlo acabaría en un checkout rechazado—. Sábado y domingo,
los tres complementos del caso se quedaban en uno. ⚠️ **La conducta del servidor no se tocó**: el que
dependía del calendario era el test, y su sujeto es el catálogo, no las tarifas. Reloj congelado al
mismo día laborable.

**(c) La regla que dejan, y aplica a cualquier fase.** Si el sujeto de un test **no** es el tiempo pero
su fixture o su dato tienen calendario —tarifas por día, rejilla de mes, franjas relativas a `now()`—,
**el reloj se congela en `setUp()` con una constante documentada**. Y su corolario, que es el que
faltaba en `#60`: **una foto que incluye el tiempo hay que tomarla con el reloj parado.** Está escrito
en `TESTING.md` §2, que es donde lo buscará quien congele la próxima.

**(d) Lo que esto NO cierra.** Se arreglaron los tres casos que hoy están rojos; **nadie ha barrido la
suite entera buscando otros que solo fallen ciertos días** (fin de mes, cambio de año, festivos). Que
2715 estén verdes hoy no demuestra que lo estén el día 31. Queda como deuda en `DEUDA.md`.

## #65 · 2026-08-15 · 4.7·2b·2 — la ida del pago deja de conducirse por una vista condenada
Segundo tramo de 4.7·2b. Sigue sin borrarse el componente: lo que se hace es poner **cada caso a su
lado de la línea** —el que prueba servidor se re-apunta a la superficie que sobrevive; el que prueba la
vista se agrupa con los suyos— para que 4.7·2b·3 sea un borrado y no una cirugía.

**(a) `RedsysIdaTest` entero se re-apunta a `POST /api/v1/orders` y SALE del inventario** (29 → 28).
Su sujeto son dieciséis casos sobre el **payload que sale hacia la pasarela** —importe, moneda, datos
de comercio, URLs, idioma, ASCII, firma y su round-trip, el `gateway_order`, el rastro del fallo—, y
eso es servidor puro: no tiene por qué morir con una pantalla. La equivalencia no se supuso, se
comprobó: **las dos superficies pasan por el mismo `CheckoutOrchestrator` → `PaymentInitiation::open()`
(`#37`), con el mismo `source` (`checkout`) y el mismo `$user->locale`**.
⚠️ Y hubo que traducir nombres: el componente publicaba el payload CRUDO del proveedor
(`gatewayUrl`/`params`/`signature`), y la API publica lo mismo con los **nombres reales de los
`<input>`** (`payment.url` + `payment.fields['Ds_*']`), que es lo que documenta
`PaymentTicket::gatewayFields()`.

**(b) ⚠️ La mutación descubrió que re-apuntar había DEBILITADO un caso, y ese es el hallazgo del
tramo.** `test_consumer_language_follows_user_locale` seguía verde con el orquestador mutado para **no
pasar el idioma del titular**. Motivo medido: `ApiLocale` resuelve sesión → `Accept-Language` →
`users.locale`, así que una petición MUDA de un usuario francés ya dejaba la app en `fr` y el fallback
`?? app()->getLocale()` daba el mismo `004` por casualidad. Con Livewire no pasaba —el componente no
cruza middleware HTTP—, o sea que **el cambio de conductor cambió lo que el caso podía ver**.
Arreglado mandando `Accept-Language: es` con un titular `fr`: las dos fuentes discrepan y el 004 solo
puede venir del titular. **Y de paso cubre algo que no cubría nadie**: quien paga desde un dispositivo
negociado en otro idioma ve la pasarela **en el suyo**, que es lo que declara el puerto.
**La regla que deja**: al re-apuntar un caso a otra superficie hay que **volver a mutar**. Verde antes y
verde después no demuestra que siga probando lo mismo — la superficie nueva puede traer por su cuenta
el valor que el caso creía estar verificando.

**(c) El único caso que prueba la VISTA se muda con los suyos, no se borra.**
`test_view_renders_auto_post_form_to_redsys_sandbox` mira el marcado del Blade (el `id`, la `action`,
los tres `name` firmados), así que muere con el componente: se traslada a `PurchasePanelTest`, cuyo
sujeto es el panel. ⚠️ **No es contabilidad: es lo único que cubre ese marcado en el lado Livewire** —el
diff de árbol NORMALIZA `action`, `method` y los `name`, y está demostrado por mutación en
`SidebarPayParityTest`, que cubre el lado SPA—. Agruparlo permite que ·2b·3 borre ficheros enteros.

**(d) `ModuleContractsTest` no sale del inventario, y gana la mitad que le faltaba.** Sus tres guardas
de «la web no reimplementa» conducían SOLO por Livewire; ahora comprueban **también** la superficie que
sobrevive: `POST orders/quote`, `GET availability/{id}/dates` + `POST …/times` y
`GET catalog/products`. Era un hueco real —de las nueve superficies, solo admisión y checkout se
vigilaban en API—, y las tres aserciones nuevas están verificadas por mutación: con los controladores
saltándose el contrato, **los tres casos caen**. Se conserva la mitad Livewire a propósito: mientras el
motor por defecto sea `livewire`, su guarda no se retira. ·2b·3 solo tendrá que borrar esas líneas.

**(e) Y un fixture que bastaba para una superficie y no para la otra.** Añadir la mitad de API destapó
que `sellableProduct()` no sembraba ninguna `RateType`: sin tarifas, `RateResolver::for()` lanza
`ModelNotFoundException`, el catálogo no puede describir el producto y `availability/{id}/dates`
responde **404**. Livewire no lo notaba porque su calendario solo consulta `AvailabilityOffer`, que ahí
está doblado. Es la lección de la fase otra vez: **dos superficies del mismo dato no piden lo mismo**.

**(f) El recuento de aserciones BAJA 8 y está cuadrado**, porque un número que baja sin explicación es
lo que esconde una pérdida: `RedsysIdaTest` −38, `PurchasePanelTest` +13, `ModuleContractsTest` +17.
El −38 es (i) el andamiaje del conductor Livewire —`assertSet('step', 8)` + `assertHasNoErrors()` en
**cada una de las 20 compras** que hace el fichero, sustituido por un `assertCreated()`—, (ii) las 10
aserciones del caso que se MUDÓ, que reaparecen en `PurchasePanelTest`, y (iii) cuatro aserciones de UI
—paso 9, `confirmed`, cesta vacía y `orderCode`— que `PurchasePanelTest` ya fijaba **verbatim**.
Casos: 16 → 15 aquí y 39 → 40 allí; la suite se queda en **2715**.

## #66 · 2026-08-15 · [DECIDIDO] El cajón es el ÁREA DE CLIENTE, no el embudo de compra
Decisión del owner, tomada el 2026-08-15 mientras se retiraba el sidebar Livewire. **No cambia el
trabajo de 4.7 —que sigue siendo terminar la retirada— pero sí cambia lo que 4.7 no puede cerrarse
sin dejar preparado**, y por eso se escribe antes de seguir.

**(a) La decisión, dicha entera.** Toda la gestión del cliente vivirá **dentro del cajón**: entrar y
darse de alta, sus entradas y reservas, y las gestiones de cuenta (perfil, contraseña, cerrar sesión en
otros dispositivos, borrar cuenta). Hoy eso está repartido en tres sitios —el cajón (compra), un modal
de auth en la cabecera y las páginas `/mi-cuenta/…`—, y **el destino es uno solo**.

**(b) Lo que NO decide, y conviene que no se dé por decidido.** No dice *cuándo*: el orden acordado es
**terminar 4.7 primero** (retirar el motor Livewire del cajón), después Turnstile —que necesita claves
de Cloudflare y un hostname que pone el owner— y después esto. Tampoco dice si las páginas
`/mi-cuenta/…` desaparecen o se quedan como vista completa además del cajón: eso es diseño, y necesita
su spec (`CONVENCIONES §5`) antes de una línea de código.

**(c) ⚠️ Lo que esto obliga a NO hacer desde hoy, que es el motivo de escribirlo ahora.**
`machine.js` modela hoy **once pasos numerados de un embudo de compra** y sus transiciones
(`TRANSITIONS`), con `CATALOG` como origen y `CONFIRMED` como final. Un área de cliente **no es un
embudo**: son zonas a las que se entra desde fuera y entre las que se navega sin orden. Quien toque la
máquina de aquí en adelante **no puede estrecharla más** —ni añadir supuestos de «siempre se viene del
paso anterior»—, porque el rediseño de estados es el primer trabajo del área de cliente.

**(d) El servidor ya está, y eso es lo que hace la decisión barata.** Medido contra `openapi/v1.yaml`:
`/auth/login`, `/auth/register`, `/auth/logout`, `/auth/password/forgot`, `/auth/password/reset`,
`/auth/email/resend`, `/me`, `/me/orders`, `/me/reservations`, `/me/reservation-eligibility` y el
post-form por firma **ya existen y están probados** desde Fase 3. El área de cliente es, en lo esencial,
**cliente nuevo de contratos que ya se pagaron**: no hay que abrir dominio, hay que pintar.
⚠️ Lo único que Fase 3 dejó fuera a propósito es la **emisión de tokens Bearer** (`#29a`), que no hace
falta aquí —la SPA vive en el mismo dominio y usa sesión— pero sí para la app móvil de Fase 6.

**(e) La consecuencia inmediata para 4.7, y es solo una.** El modal de auth de la cabecera
(`layout.blade.php`, `@livewire('auth.login|register|forgot-password')`) **sobrevive a 4.7 y no se
toca**: hoy es la única superficie que monta el widget de Turnstile, y el alta del cajón **delega en él**
cuando el anti-bot está activo. Retirarlo antes de montar Turnstile en Vue dejaría el registro sin
salida. O sea: **el orden es 4.7 → Turnstile → área de cliente**, y no es preferencia, es dependencia.

## #67 · 2026-08-15 · 4.7·2b·2·B — el diff de árbol se alimenta del SERVIDOR, no del motor que se va
Tercer tramo de 4.7·2b, y el que desbloquea a los demás. La opción se eligió midiendo (`00-REFACTOR.md`,
paso 4.7·2b·2): entre congelar también las props y **alimentar a Vue con las respuestas reales de la
API**, la segunda es estrictamente mejor y esta decisión la ejecuta, paso a paso.

**(a) ⚠️ El problema, y no era el que parecía.** `SidebarDomContractTest` construía las props de Vue
desde el view-model del componente Livewire (trece helpers que leen `viewData()`). El motivo obvio para
cambiarlo era que **sin componente no hay props**, así que el gate no sobreviviría a la retirada. El
motivo REAL es peor: al entregarle a Vue un dato ya cocinado por el motor que se va, **el gate nunca
ejecutaba la traducción que corre en el navegador**. Medido: de las **25 funciones de `Sidebar.vue`,
21 no tenían ningún test** — y entre ellas `groupIntoSections()`/`toItem()`, que son literalmente
«de la respuesta de la API a lo que se pinta» en el primer paso del embudo.

**(b) La forma: la traducción baja a un módulo PLANO y la ejecutan los dos.** Nace
`resources/js/sidebar/catalog.js` (`sectionsFrom`, `toItem`, `totalItems`, `searchIsEnabled`) con 12
casos de `node --test`. `Sidebar.vue` lo **consume** —extraer sin que el consumidor lo use es copiar,
no extraer (`#40`)— y `scripts/render-sidebar.mjs` gana un segundo modo: con `"api": {…}` recibe las
respuestas **crudas** y construye las props con **ese mismo módulo**. El modo `props` sigue para los
pasos no migrados: esto es incremental, un paso cada vez.

**(c) La verificación de FIDELIDAD, que es la que da confianza: el manifiesto NO cambió.** Los 34 casos
del gate pasan sin regenerar `sidebar-dom-manifest.json`. O sea: alimentar a Vue desde
`GET /catalog/products` + `GET /config` produce **exactamente el mismo árbol** que producía el
view-model de Livewire. Si hubiera cambiado una sola entrada, sería una divergencia real y habría que
mirarla, no regenerarla.

**(d) Y la mutación que demuestra que esto NO es cosmético.** Se cambió en `toItem()` el campo
`from_price_cents` por uno que la API no publica — el fallo de `#46(a)`, con el cajón real pintando
tarjetas sin precio:
· **modo nuevo (alimentado por la API): ROJO**, y nombra la divergencia en la primera línea;
· **modo viejo (props cocinadas por el test), con la MISMA mutación: VERDE.**
Las dos mitades se ejecutaron. Eso es el punto ciego, medido, y cerrado para el paso 1.

**(e) Lo que esto cambia en la planificación de la retirada.** Cada paso que se migre a este modo
(i) deja de depender del componente para sus props y (ii) hace redundante, **por el motivo correcto**,
la paridad que existía para tapar este mismo hueco: `SidebarAddonsParityTest` y
`SidebarCalendarParityTest` nacieron justo porque el gate no ejecutaba la traducción del cliente.
⚠️ **Todavía no se pueden retirar**: solo el paso 1 está migrado. Retirarlas antes de migrar su paso
sería quitar la red y dejar el agujero.

## #68 · 2026-08-15 · 4.7·2b·2·B — el paso 2, y dos husos que el módulo del calendario no cubría
Segundo paso migrado al modo «alimentado por el servidor» (`#67`). Mismo patrón, y otra vez lo que
apareció al mirar de cerca vale más que el propio traslado.

**(a) El paso 2 ya no recibe la rejilla hecha.** Antes se le pasaba a Vue el `weeks` que había
repartido el SERVIDOR, así que `buildWeeks()` —el reparto que corre en el navegador— **no se ejecutaba
nunca en el gate**; el propio docblock de `SidebarCalendarParityTest` lo declara como el hueco que
existe para tapar. Ahora el test entrega la respuesta cruda de `GET availability/{producto}/dates` y
compone el cliente. ⚠️ **El MES tampoco se pasa: se deriva**, igual que hace `Sidebar.vue` al elegir
producto. Pasarlo habría dejado esa regla fuera del gate otra vez, que es el error que este paso
corrige. **El manifiesto no cambió** (fidelidad) y dos mutaciones lo confirman: dejar de marcar los
días de relleno fuera de mes, y desacotar la navegación, ponen el gate en rojo.

**(b) ⚠️ `calendar.js` no tenía NINGÚN fichero de test**, y eso solo se ve al necesitar que sobreviva:
lo cubría `SidebarCalendarParityTest`, en PHP, comparándolo con el servidor — o sea, con el motor que
se va. Nace `calendar.test.js` (18 casos) y ahí se traen en forma ejecutable los dos casos frontera que
aquella paridad declaraba MEDIDOS: el mes que empieza en domingo y el huso del navegador.

**(c) Y la lección de la sesión: la primera versión del caso de husos era VACUA, como la de 4.2.**
La mutación obvia —parsear el mes con `new Date(month + '-01')`— **pasaba en verde**. No porque el
test fuera ciego, sino porque el peligro tiene **dos puertas** y ese caso solo cubría una:
· por la **salida** — derivar `YYYY-MM-DD` con `toISOString()`: el caso sí la caza (medido);
· por la **entrada** — `new Date('2026-08-01')`, medianoche UTC: **no la cazaba con agosto**, porque el
  desfase retrasa un día el «primero de mes» y **el arranque de la rejilla solo se mueve si ese primero
  ya era lunes**. Agosto de 2026 empieza en sábado.
Se añadió el caso con junio de 2026 (empieza en lunes) y ahora cada puerta tiene la suya: **cada
mutación deja ROJO exactamente un caso y verde el otro**. Es el aviso de 4.2 leído del derecho, y
generaliza: *un caso frontera hay que elegirlo por el mecanismo del fallo, no por el síntoma*.

**(d) Un fallo real, pequeño y arreglado al extraer.** El mes en que abre el calendario se derivaba en
`Sidebar.vue` con `new Date().toISOString().slice(0, 10)` — **UTC**, la misma trampa contra la que está
escrito todo `calendar.js`, colada por la única puerta que no pasaba por él. En Madrid, entre las 00:00
y las 02:00 del día 1, el respaldo devolvía el mes ANTERIOR. Vive ahora en `initialMonth()`, en horario
local, con su caso. Solo muerde cuando no hay ninguna oferta, que es por lo que nadie lo había visto.

**(e) Consecuencia: `SidebarCalendarParityTest` ya es candidata a retirarse** — su hueco está cerrado y
sus fronteras están en `calendar.test.js`—, **pero no se retira todavía**: mientras Livewire viva sigue
siendo el único sitio que compara las dos composiciones entre sí. Se va en 4.7·2b·3, con el componente.

## #69 · 2026-08-15 · 4.7·2b·2·B — el paso 3, y el VERDE FALSO que escondía el bundle SSR
Tercer paso migrado (`#67`, `#68`). El traslado en sí fue el de siempre; lo que apareció al hacerlo, no.

**(a) El paso 3 se alimenta de sus CUATRO respuestas reales** —`availability/{p}/times`,
`availability/{p}/dates`, `catalog/products/{p}` y `catalog/products/{p}/addons`— y **desaparece la
traducción que el test hacía por su cuenta**: había en el propio fichero un `addonsAsApi()` que
renombraba el view-model de Livewire a la forma de la API (`id`→`product_id`, `qty`→`quantity`,
`can_inc`→`can_increase`). Esa traducción **era el punto ciego con nombre y apellidos**: el cajón real
recibe la respuesta del endpoint, no una traducción escrita en el test, y es el fallo de `#46(a)`
—diff verde, cajón con filas vacías— por el que `SidebarAddonsParityTest` existe. Nace `offer.js`
(9 casos) con el suelo, el techo, el precio del día y la cantidad inicial. Manifiesto sin cambios.

**(b) ⚠️ Una divergencia que PARECÍA existir y no existe — y por qué conviene dejarlo escrito.**
`Purchase::selectTime()` fija `qty = techo >= mínimo ? mínimo : 0`; la SPA hace `min(mínimo, techo)`.
Difieren solo con `0 < techo < mínimo`. Se buscó ese caso con un pack de mínimo 8 y el aforo de
invitados casi agotado, y **no es alcanzable**: con 5 invitados libres los DOS motores devuelven lista
de horas **vacía** —la oferta retira la hora entera cuando el mínimo no cabe— y con 8 libres los dos
publican `max_quantity = 8`. O sea: para toda hora ofrecida se cumple `techo ≥ mínimo`, y ahí las dos
expresiones coinciden. Queda en el docblock de `initialQuantity()` con la medición, para que nadie
«arregle» una de las dos a ciegas.

**(c) ⚠️⚠️ Y el hallazgo de verdad: el gate podía dar VERDE FALSO, y lo dio.**
`SidebarDomContractTest` no renderiza las fuentes: renderiza `storage/ssr/render-sidebar.js`, que
compila Vite. Al migrar este paso se editó el renderizador y se corrió el test **sin reconstruir**, lo
que produjo un rojo confuso (el selector acotado al revés) que costó una medición entender. Buscando
la causa apareció lo grave, que es el caso simétrico: **con una fuente ROTA y el bundle sin
reconstruir, el gate pasa en verde**. Medido: `catalog.js` leyendo un campo que la API no publica →
el caso del catálogo verde, con 7 aserciones.
· Un rojo espurio cuesta una hora. **Un verde falso cuesta el contrato visual entero**, y este test es
  justamente la red que tiene que sobrevivir a la retirada de Livewire.
· En el `pre-push` no ocurría —el hook construye antes de la suite—, pero **al iterar en local sí**, que
  es exactamente cuando más se tocan estos módulos.
· **Arreglo**: `assertBundleIsNotStale()` compara la fecha del bundle con la de cada fuente que entra en
  él (los `*.test.js` no entran, y por eso se excluyen). Verificado en las dos direcciones: con el
  bundle al día, verde; con una fuente más nueva, rojo **nombrando el fichero**.
· **La regla que deja**: *un test que compara contra un ARTEFACTO tiene que comprobar que el artefacto
  no está rancio.* Que el gate de CI lo construya no basta: el modo de fallo peligroso es local y
  silencioso.

## #70 · 2026-08-15 · 4.7·2b·2·B — el paso 4, y una mutación que NO cazó el gate (y está bien)
Cuarto paso migrado (`#67`–`#69`). Cae la tercera y mayor de las traducciones que el test hacía por su
cuenta, y aparece un caso que **no se migra a propósito**.

**(a) El carrito se alimenta del presupuesto REAL.** `cartProps()` renombraba a mano el view-model de
Livewire a la forma de la API —`qty`→`quantity`, `name`→`product_name`, `subtotal`→`subtotal_cents`, y
**`product_id` inventado a 0**—, así que el gate **nunca ejecutaba `cartRows()`**, que es quien empareja
cada línea tarificada con las respuestas del pack. Ahora se pide `POST /orders/quote` de verdad y
compone el cliente. Manifiesto sin cambios; la mutación de dejar de emparejar las respuestas con sus
etiquetas pone el gate en rojo.

**(b) ⚠️ Lo que sí se traduce aquí es la CESTA, y la diferencia importa.** La cesta **no es una
respuesta del servidor**: es estado del cliente. Livewire la guarda en sesión (`ticket_type_id`/`qty`) y
el cajón en `localStorage` (`product_id`/`quantity`), la misma equivalencia que declara
`Http\Api\CartPayload`. Traducir ESTADO para que los dos motores partan de la misma cesta no es lo
mismo que traducir la RESPUESTA que el cliente tiene que saber leer — lo primero es montar el
escenario, lo segundo es tapar el sujeto del test.

**(c) ⚠️ Un caso que NO se migra, y el motivo es interesante.** «Una línea de carrito sin fecha»
siembra `date: ''`, y ese estado **no es alcanzable por el cliente**: `POST /orders/quote` lo rechaza
con 422 y el saneador del cajón lo descarta antes de guardarlo (`cart.js` espeja `CartPayload`). No hay
camino real que alimentar; lo que comprueba ese caso es que el marcado no se desmorona **si llegara**.
Se queda con props cocinadas y con la explicación al lado. **Regla: alimentar desde la API solo tiene
sentido para estados que el cliente puede alcanzar.**

**(d) ⚠️ Y una mutación que el gate NO cazó — comprobado que está bien así.** Cambiar el emparejamiento
de `cartRows()` de `index` a POSICIÓN dejó los 34 casos en verde, porque los fixtures del diff tienen
**una sola línea** y ahí índice y posición coinciden. No es un agujero: esa regla la cubre
`cart.test.js` con un caso hecho para ella —tres líneas en la cesta y dos en el presupuesto, con el
hueco en medio— y **se verificó que la misma mutación lo pone rojo**. Cada nivel prueba lo suyo: el
diff de árbol, que el marcado sale del cliente; el test del módulo, las reglas del módulo. Perseguir
esa mutación desde el diff habría significado inflar sus fixtures para reprobar algo ya probado.

**(e) Lo que queda del paso 4**: el PIE y la banda de progreso siguen tomándose del servidor en
`shellProps()`, y eso afecta a los **doce** sitios que montan el armazón, no solo al paso 4. Es el
siguiente tramo y va aparte a propósito: mezclar el armazón con la cesta habría hecho un cambio que
nadie puede revisar.

## #71 · 2026-08-15 · 4.7·2b·2·B — el ARMAZÓN lo compone el cliente, y la banda de 4.3·1 queda cubierta
Quinto tramo (`#67`–`#70`), y el primero que no es «un paso»: el armazón se monta en DOCE sitios, así
que su punto ciego valía por doce.

**(a) El hueco, con su propia confesión escrita.** `shellProps()` decía, tal cual: «el pie se toma del
SERVIDOR, igual que la banda: aquí se compara el marcado. Que el cliente componga el mismo view-model
lo comprueba `SidebarCartParityTest`». O sea, el gate comparaba el marcado de un pie que `foot.js` **no
había compuesto**. Ahora `render-sidebar.mjs` construye el armazón con `buildProgress()` y
`buildFooter()` —los módulos que usa `Sidebar.vue`— a partir del presupuesto, del catálogo y de la
línea del endpoint de complementos. **El manifiesto no cambió.**

**(b) ⚠️ Y esto cubre por fin el fallo que 4.3·1 pagó.** Aquel paso encontró que la banda de progreso
estaba escrita, salía VERDE en el gate y **el cajón vivo iba sin «Volver»**, porque `Sidebar.vue` le
pasaba `progress: null`. Era invisible precisamente porque el gate alimentaba a Vue con la banda del
servidor. Medido ahora: mutar `buildProgress()` para que no emita nunca deja **tres casos en rojo**.
Un fallo que costó una sesión entera ya no puede repetirse en silencio.

**(c) La migración es explícita, no automática.** El renderizador solo compone el armazón si se le
pide (`shellFromServer: false`); los montajes que aún no se han migrado siguen pasándolo cocinado.
Mezclar las dos cosas en silencio escondería **cuál** de los doce sigue comparando contra el servidor —
y el recuento es justamente lo que dice cuánto falta. Quedan **tres**, todos del paso 8 y del bucle de
la pausa, y se migran con su paso.

**(d) El aviso de pausa NO entra aquí, y es correcto**: el test ya ejecutaba `paused.js` en Node con la
respuesta real de `GET /booking/status`. Ese lado nunca tuvo el hueco, así que el armazón lo recibe
hecho en vez de recomponerlo — duplicarlo habría sido inventar una segunda fuente.

**(e) ⚠️ Una mutación mal elegida no prueba nada, y volvió a pasar.** El primer intento de tumbar el
pie mutó `splitMode` en la rama del paso 3, que los fixtures ejercitan **con una entrada** —sin señal,
sin desglose—: verde. La rama que sí tiene desglose es la de la CESTA, y mutándola ahí caen dos casos.
Es la misma lección que el caso de husos de `#68`: **la mutación hay que apuntarla a la rama que el
fixture recorre**, o mide otra cosa.

## #72 · 2026-08-15 · 4.7·2b·2·B — pasos 5, 8 y 9: caen las últimas traducciones a mano del test
Sexto tramo (`#67`–`#71`). Con este, **el test ya no traduce nada del servidor**: las cuatro
traducciones que hacía por su cuenta han desaparecido, cada una sustituida por el módulo del cliente
que de verdad corre en el navegador.

**(a) Paso 9 — la más peligrosa de las cuatro.** `gatewayFormProps()` convertía a mano el mapa
`payment.fields` del contrato en la LISTA de `{name, value}` que pinta `RedirectStep`, que es
exactamente lo que hace `pay.js::gatewayForm()`. El gate nunca la ejecutaba, y es el sitio donde un
campo renombrado rompe el cobro con **SIS0042 con el pedido ya creado y el aforo retenido**. Ahora se
crea un pedido de verdad por `POST /api/v1/orders` y traduce el cliente. Mutación: vaciar los campos
firmados deja el caso en rojo.

**(b) ⚠️ Y ese caso enseñó un orden que no es negociable.** La primera versión pedía el sobre DESPUÉS
de `confirmReservation()` y se llevó un **422**: a partir del 201 el pedido existe y retiene aforo, así
que confirmar **vacía la cesta** — y sin cesta no hay con qué crear el pedido equivalente por la API.
Queda escrito en el caso: el sobre se pide antes.

**(c) Paso 5 — el banner de errores del alta.** `registerErrorsFrom()` reimplementaba en PHP el orden
de los avisos, y su propio comentario lo confesaba: «el mismo orden que fija `register.js`». Eso es la
definición de punto ciego. Ahora se le entrega el **422 crudo** de `POST /auth/register` y compone
`register.js`. Mutación: que `summaryOf()` devuelva lista vacía deja el caso en rojo.
⚠️ El sobre de `api.js` (`{ok, status, data, error, offline}`) se reproduce en el renderizador, y el
límite queda escrito: las cuatro trampas de `api.js` son del TRANSPORTE —cookie, `Accept`, CSRF
url-decodificado, reintento del 419— y no se pueden ejercer sin red. Lo que sí se ejerce es quien LEE
ese sobre, que es lo que este tramo perseguía.

**(d) Paso 8 — sin sorpresa, y es buena señal.** Pinta las mismas filas que el carrito
(`SummaryLine.vue` es compartida), así que se compone con `cartRows()` sobre el presupuesto real, igual
que el paso 4.

**(e) El recuento del tramo**: de las trece props cocinadas que tenía el test quedan **tres**, y las
tres con motivo escrito — la línea de carrito sin fecha (estado inalcanzable por el cliente, `#70`),
las dos del paso 5 sin errores (no traducen nada: mode, formulario vacío y los dos grupos de
diccionario que inyecta el montaje) y el bucle del aviso de pausa, que aún monta el armazón del
servidor porque recorre pasos sin migrar. **El manifiesto no ha cambiado en ninguno de los seis
tramos**, que es la prueba acumulada de que la migración es fiel.

## #73 · 2026-08-15 · 4.7·2b·2·B COMPLETA — los tres desenlaces, y el gate deja de mirarse al espejo
Séptimo y último tramo de (B) (`#67`–`#72`). Con él, **los once pasos y el armazón se alimentan del
servidor**: no queda un solo sitio donde el diff de árbol le pase a Vue algo que haya compuesto el
motor que se retira.

**(a) Paso 6 — el resumen de la reserva creada.** Lo compone `buildConfirmation()` desde
`GET /orders/{code}` y `GET /orders/{code}/event-data`, que llegan **por separado** porque las
respuestas de un menor no viajan en el pedido (`#39`). El test lo traducía a mano, y ese emparejado ya
mordió una vez: el caso de `#56` pasaba **con el cliente emparejando por POSICIÓN**, porque llave y
posición coinciden por casualidad cuando el orden natural es el mismo. Mutación: anular
`answersByReservation()` deja **dos casos** en rojo.

**(b) Paso 10 — el motivo del rechazo.** Se pide `payment-status` y lo resuelve `declinedReasonText()`
sobre `declined_reason`, que **es** la clave del diccionario. Paso 11 no se migra porque no traduce
nada: código de pedido y una URL.

**(c) ⚠️ Otra mutación que el gate NO caza, y también está bien.** Quitar la caída a `default` del
motivo deja los 34 casos verdes, porque ninguno de los dos fixtures produce una clave **desconocida**
—uno trae `0101` y el otro `null`, y los dos resuelven—. Esa caída la cubre `outcome.test.js` con un
caso hecho para ella («un motivo desconocido, nulo o vacío cae en el genérico y NUNCA pinta vacío»), y
**se verificó que la misma mutación lo pone rojo**. Segunda vez en esta migración que la respuesta
correcta a «el diff no lo ve» es *mira si lo ve quien debe*, no *infla los fixtures del diff*.

**(d) El último montaje del armazón.** El bucle del aviso de pausa era el único que seguía tomándolo
del servidor. Con el aviso puesto el armazón tapa el paso entero, así que no hace falta cargar la API
de cada uno — pero dejarlo así habría escondido el único que faltaba. **Hoy son cero.**

**(e) ⚠️ Y la guarda del bundle rancio se cobró su primera pieza, en vivo.** Restaurar `outcome.js` con
`cp` tras una mutación le puso fecha nueva y el gate cayó **entero** nombrando el fichero. Sin ella
habría comparado contra código viejo y habría salido verde. Es exactamente el modo de fallo que `#69`
describía, ocurriendo dos horas después de escribir la guarda.

**(f) El balance de (B), medido.** Siete tramos · cuatro módulos nuevos (`catalog.js`, `calendar.js`
con test propio, `offer.js`) · **286 tests JS** frente a 247 al empezar · cuatro traducciones a mano
retiradas del test · y **el manifiesto sin cambiar ni una vez en los siete**. Eso último es la prueba
acumulada: cada vez que se sustituyó «lo que compone el servidor» por «lo que compone el cliente», el
árbol salió idéntico.

## #74 · 2026-08-15 · El presupuesto de tokens define «el cajón» rascando una plantilla, y eso no escala
Corrección de una lectura propia, hecha el mismo día. **Vale sobre todo como método**: la primera
conclusión salió de una sonda que no reproducía la línea base del test, y por eso era falsa.

**(a) ⚠️ El error de método, primero, porque es el que enseña.** Al ver que ampliar el escaneo de
`SidebarTokenBudgetTest` a las fuentes Vue bajaba la tokenización de 75% a 70%, se escribió una sonda
aparte para desglosar QUÉ entraba. La sonda daba **70% y 11 colores crudos para el ámbito de HOY**,
cuando el test dice **75% y 3**: le faltaba la restricción de `prefix` sobre `layout.blade.php` —del
layout solo cuentan las clases `sidecart*`—, así que arrastraba medio sitio. **Una sonda que no
reproduce la línea base del instrumento no mide lo mismo que él**, y sus conclusiones no valen. Se
rehízo el desglose sobre el test REAL, volcando desde dentro.

**(b) Lo medido de verdad.** Añadir los `.vue` lleva el ámbito de **1.073 a 1.307 declaraciones**
(tematizables 658 → 767, sin token 160 → 224) y los crudos de 3 a 5. Pero desglosado:
· **60** de las nuevas sin token son el bloque `auth__*`/`form__*`/`check`/`pwd-input` — los
  formularios de login y alta, **compartidos con el modal de auth y con `/mi-cuenta`**;
· otras 14 son `eyebrow`, `icon`, `tk`, `account__card`: del sitio;
· **una sola era del cajón**: el velo `.jj-loading`.
O sea: **no es que 4.0c dejara el cajón a medias.** Es que el marcado de la SPA reutiliza clases del
sitio —los formularios de auth viven ahora DENTRO del cajón— y rascarlas de la plantilla las mete en
«el ámbito del cajón».

**(c) Lo que sí se arregla aquí, y es una línea.** `.jj-loading` llevaba `rgba(244, 239, 227, 0.82)`
con un comentario que ya decía «`var(--bg)` translúcido»: `--bg` es `#F4EFE3`, así que es EXACTAMENTE
ese token al 82%. Convertido a `color-mix(in srgb, var(--bg) 82%, transparent)`, el patrón que 4.0c
verificó para los otros diez alfa. Con el ámbito ancho, los crudos bajan de **5 a 4**; con el de hoy no
se mueve nada, porque el velo no estaba en él —el Blade lo emite por un componente y el escáner solo
lee literales—, que es otra muestra del mismo problema.

**(d) La decisión que esto deja pendiente, y NO es una tarea de tokenización.** El día que se borre el
Blade hay que cambiarle la fuente al escaneo, y la salida buena no es «escanear los `.vue`»: es
**definir el ámbito por las familias PROPIAS del cajón** (`purchase__`, `cart__`, `bk-`, `cal__`,
`wiz__`, `qtybox`, `catalog-acc`, `jj-`, `sidecart`…). Así el presupuesto deja de depender del motor
—que es justo lo que la retirada necesita— y deja de crecer cada vez que el marcado reutiliza una clase
compartida. Tokenizar el bloque de formularios del sitio es **Fase 5** («theming como paquete
coherente»), no 4.7. Ficha en `DEUDA.md`.

## #75 · 2026-08-15 · 4.7·2b·2 — la primera paridad sale del inventario, y su referencia MEJORA
Arranca la auditoría de las nueve paridades con la pregunta que ahora se puede hacer: **«¿qué afirma
esto que el diff de árbol, ya alimentado del servidor (`#73`), no afirme?»**. `SidebarPayParityTest`
es la primera en responderla del todo: de sus siete casos, solo dos tocaban el componente.

**(a) El caso de los nombres de campo pierde su mitad de comparación.** Afirmaba dos cosas: que la API
publica las tres llaves firmadas (sobrevive) y que **los dos motores mandan el pago al mismo sitio**
(desaparece con el segundo motor). La mitad Livewire no se pierde: `PurchasePanelTest` fija sobre el
marcado REAL el `action` al sandbox y los tres `name=` — se mudó allí en `#72` precisamente para esto.

**(b) ⚠️ Y el caso de los tres idiomas se re-apunta al DICCIONARIO, que es la referencia correcta.**
Comparaba el texto del cliente con el error bag de Livewire. Lo primero que se probó fue usar
`error.message` de la API, y **se midió que no sirve**: es una cadena fija de desarrollador —«No quedan
plazas para esa hora»— y **ni siquiera se traduce**: sale idéntica en `es`, `en` y `fr` mientras el
servidor dice «se ha agotado», «has sold out» y «est complet».
Por eso el cliente compone desde la CLAVE con los `params` del rechazo (`pay.js`: `ERROR_KEYS[code]` +
`tp(messages, key, error.params)`), y la referencia que sobrevive es **`__()` con esos mismos params**.
El re-apunte no es un apaño para salvar el caso de la retirada: es la referencia que debió tener desde
el principio, porque el error bag de Livewire era **un intermediario** del mismo diccionario.
**Verificado por mutación ×2**: que el cliente deje de interpolar los params, y que use la clave de otro
código, dejan el caso en rojo. No se ha vuelto tautológico.

**(c) La regla que deja para las ocho restantes.** Al auditar una paridad hay que separar tres cosas:
lo que **compara entre motores** (muere con el segundo), lo que **afirma del contrato** (se queda) y lo
que usa el motor viejo como **intermediario de una fuente que sobrevive** (se re-apunta a la fuente).
El tercer caso es el interesante y es el que hay que buscar: casi siempre mejora el test.

**(d) Contador: 28 → 27.** Y una nota de proceso: la guarda del bundle rancio (`#69`) volvió a saltar
—restaurar un módulo con `cp` le pone fecha nueva— cazando 30 casos que habrían comparado contra código
viejo. Van dos veces en dos días.

## #76 · 2026-08-15 · [DECIDIDO] Hay un servidor de PRUEBAS, y no es producción
El owner levanta `jumpweb.sites.aelium.app` (infra propia, panel enhanceCP). Cambia una premisa que
estaba escrita, así que se decide qué cambia y —sobre todo— **qué no**.

**(a) Qué es.** Un banco de pruebas del PRODUCTO: **0 LIVE, 0 PRODUCCIÓN**, sin clientes, sin dinero
real y sin datos de personas reales. No es la instalación de nadie. Existe **solo** para lo que
necesita una URL pública o un navegador de verdad y no se puede ver en local. Reglas en
`docs/ENTORNOS.md`.

**(b) Lo que desbloquea, que era exactamente lo que estaba atascado**: el **widget de Turnstile**
(4.4b·2 — Cloudflare emite las claves contra un hostname), la **notificación S2S de Redsys**
(`redsys_merchant_url`, el «bloque B» del e2e que hasta hoy exigía un túnel), el **3DS con challenge**
y el **móvil real**. Los cuatro estaban declarados como pendientes de navegador desde `#59`.

**(c) ⚠️ Lo que NO cambia, y es lo importante: `#62` no se reabre.** Aquella decisión retiró la
condición «dejar el flag en `spa` en uso real unos días» **por vacía**, y sigue siéndolo: un staging
**no tiene tráfico**. La retirada de `Purchase.php` la sigue ordenando el CONTADOR de
`PurchaseRetirementTest`, no el calendario ni «que ruede un poco». Se escribe aquí y en `ENTORNOS.md`
porque el modo de fallo es previsible: alguien lee «ya hay servidor» y reintroduce el bloqueo.

**(d) Seis guardas, cada una atada a algo que este código hace hoy** (detalle en `ENTORNOS.md` §2):
Redsys en `test` —el único fallo de la lista que cuesta DINERO— · **nunca un volcado del cliente
origen**, porque traería nombres, edades y alergias de menores a un servidor de pruebas · el correo no
sale · no indexable · `APP_URL` con el dominio real y HTTPS · cola en `database` con worker vivo.

**(e) Y un dato práctico verificado al escribir esto**: **las claves de Turnstile se leen SOLO de
`settings` (BD), nunca de `.env`** (`Platform\Services\Turnstile`). En staging se configuran por el
panel de admin. Es la contradicción que `INSTALACION-CLIENTE.md` §3 ya declaraba abierta.

**(f) El procedimiento de despliegue queda [PENDIENTE DE MEDIR]**, a propósito: cierra el
`[DECISION-PENDIENTE]` de `INSTALACION-CLIENTE.md` §1 y **no se escribe a ojo**. Se ejecuta una vez, se
anota lo que de verdad pasó y se deja reproducible. El principio sí está decidido: **staging se levanta
con el mismo procedimiento que levantaría la instalación de un cliente**; configurado a mano sería un
*snowflake* y no demostraría nada sobre lo que instalará el siguiente.

## #77 · 2026-08-15 · 4.7·2b·2 — la segunda paridad sale del inventario, y su fixture tapaba dos campos
Sigue la auditoría de `#75` con `SidebarAddonsParityTest` (1 uso). La regla de las tres categorías
—entre motores / del contrato / intermediario de una fuente que sobrevive— vuelve a dar el tercer
caso, y esta vez la medición encuentra además un caso **vacuo**.

**(a) El motivo por el que nació ya está cerrado, y por eso su mitad de comparación muere.** Existía
porque el diff de árbol le pasaba a Vue el view-model del servidor traducido por el propio test: un
componente que leyera `id`/`qty`/`can_inc` en vez de `product_id`/`quantity`/`can_increase` pasaba en
verde con el cajón pintando filas vacías. Eso lo cerró (B) en `#69`, cuando el paso 3 pasó a
alimentarse de las respuestas REALES.

**(b) Lo que queda es INTERMEDIARIO, y la fuente sobrevive dos veces.** El componente solo conducía
`AddonResolver::viewModel()`, que es dominio y conserva dos consumidores tras la retirada: este
endpoint (vía `AddonOfferReader`) y el **alta manual del panel** (`CreateManualOrderPage`). Se
re-apunta ahí: el sujeto pasa a ser **la capa de publicación** —`toDto()` + `ResolvedAddonsResource`,
21 traducciones de clave a mano—, y el `asApi()` del test es su segunda escritura DELIBERADA, que es
lo único que hace visible un cruce de claves.

**(c) ⚠️ La medición dice que no se podía borrar: tres campos los caza SOLO este test.** Mutando
campo a campo contra los 2715 casos: `note`, `min_quantity` y `max_quantity` dan **1 fallo en toda la
suite**, y es este. `features`, `can_toggle` y `can_increase` los comparte con el diff de árbol
(llegan al DOM); `price_cents` y `charged_cents`, con `CatalogAddonsTest`; `free_quantity` **solo lo
cazaba `CatalogAddonsTest`**, porque aquí el fixture no lo distinguía. El contrato
(`required` + `additionalProperties: false`) fija los NOMBRES publicados, nunca que el valor de cada
uno venga del campo que le toca: por eso un cruce es silencioso.

**(d) ⚠️⚠️ Y el caso era VACUO en dos campos, por el mecanismo de `#68`.** El fixture viejo no tenía
ningún complemento obligatorio ni ninguno con tope, así que `min` era 0 y `max` era `null` en los
cinco: cruzar `min` ← `max` salía **VERDE**, porque `(int) null` es `0`. Pinchar un campo en su valor
trivial no es fijarlo. Con un obligatorio (`min` = 2) y uno con tope (`max` = 3) los dos cruces caen,
y tres aserciones de frontera sobre lo PUBLICADO impiden que el fixture vuelva a perderlos en
silencio. **Regla, otra vez: el caso se elige por el MECANISMO del fallo, no por el síntoma.**

**(e) Dos mutaciones MAL APUNTADAS antes de acertar, y las dos por la misma causa.** `product_name` y
`quantity` viven **dos veces** en `ResolvedAddonsResource` —en la fila resuelta y en `line.addons`—,
y una sustitución de texto por la línea con menos sangría casa primero con la otra, que es subcadena
suya. Salió «verde» dos veces sin probar nada. De paso quedó medido que esa otra mitad **sí está
cubierta**: mutarla deja tres casos rojos en `CatalogAddonsTest`. **Al mutar hay que comprobar dónde
cayó la mutación, no solo que el fichero cambió.**

**(f) Contador: 27 → 26.** El guardián del inventario nombró el fichero él solo en cuanto dejó de
tocar el componente, que es exactamente para lo que está.

## #78 · 2026-08-15 · 4.7·2b·2 — una paridad donde el motor viejo NO era la referencia de nada
Tercera de la auditoría: `SidebarAdmissionParityTest` (2 usos). Es el primer caso en que la respuesta
de la regla de `#75` no es «re-apuntar la referencia» sino **«la referencia no aportaba nada, y lo que
hay que rescatar es otra cosa»** — y se vio midiendo, no leyendo.

**(a) La mitad que comparaba destinos contra Livewire era redundante, y está medido.** Cinco
mutaciones sobre `admission.js` —la clave del aviso del tope, el `max` que sale del sobre, la clave de
la cesta vacía, que la pausa componga mensaje y que el invitado vaya a pagar— dejan **rojos a la vez**
la paridad y `admission.test.js`. El reparto del módulo ya tiene su red, con más fronteras que esta.

**(b) Lo único que este fichero puede decir es que la cadena entera es REAL.** `admission.test.js`
prueba el módulo con un diccionario y unas respuestas fabricadas por quien escribió el módulo; aquí
las respuestas las da el servidor de verdad sobre una instalación pausada, un titular con el tope
lleno o el limitador agotado, y el diccionario es el de `lang/`. **Medido: renombrar
`errors.too_many_pending` en `lang/es/tickets.php` deja los 2715 casos verdes salvo UNO, y es este.**
`admission.test.js` no puede verlo —su diccionario es de mentira— y `SidebarTextParityTest` no lleva
esas claves.

**(c) La re-apuntada es la misma de `#75(b)`: el error bag era intermediario del diccionario.**
Verificado en el código: `Purchase::checkout()` escribe literalmente `__('tickets.errors.cart_empty')`.
La referencia pasa a ser `__()`, y la del tope interpola la constante de la POLÍTICA mientras el
cliente interpola `max_pending_orders` **del sobre de la API** — así que el caso sigue cazando un
desajuste entre esos dos números sin necesitar el motor que se va.

**(d) Lo que se pierde, dicho sin adornos.** El caso que demostraba sobre el HTML que el mensaje de
pausa se escribe y **no se pinta** muere con el Blade: era su única prueba posible. Lo que se queda es
la conducta que aquello justificaba —sin mensaje y releyendo el estado—, ejercida con la instalación
realmente pausada. La decisión sigue documentada aquí y en `admission.js`.

**(e) Siete mutaciones para demostrar que la re-apuntada no es tautológica**, las siete rojas: la
clave que falta en el diccionario, cuatro sobre el módulo y **dos de deriva de contrato en el
servidor** —publicar el nombre interno del dominio (`too_many_pending`) en vez del código público, y
dejar de publicar `max_pending_orders`—. Esas dos últimas son el sujeto nuevo del fichero: el
**cableado** entre el endpoint y el módulo, que ninguno de los dos prueba por su cuenta.

**(f) Y los pasos se leen de `machine.js`, no se escriben en PHP.** El test afirma a qué PASO se va;
un número suelto no dice cuál es ni se entera si el embudo se renumera.

**(g) Contador: 26 → 25.** Nota de proceso: **la guarda del bundle rancio saltó por tercera vez en
tres días** —mutar un módulo y restaurarlo con `git checkout` le pone fecha nueva—, y esta vez tumbó
los 30 casos del diff de árbol de golpe. Sin ella habrían comparado contra un bundle viejo: cuesta un
`npm run build:ssr` y evita un verde falso, que es exactamente el trato que `#69` buscaba.

## #79 · 2026-08-15 · 4.7·2b·2 — una paridad que NO es homogénea: dos casos mueren y uno sobrevive
Cuarta de la auditoría: `SidebarCalendarParityTest` (3 usos). La doc ya la daba por «candidata a
retirarse», pero eso se había **leído**; medida, resulta que el fichero mezcla dos cosas y hay que
tratarlas distinto. **El contador NO se mueve** —lo que muere con el componente sigue en el inventario
hasta ·2b·3—, y aun así el paso vale: sin esta separación, ·2b·3 borraría un caso vivo.

**(a) Los dos primeros casos comparan ENTRE MOTORES y mueren.** Su referencia es `viewData('weeks')`,
que compone `Purchase` y no existe en ningún otro sitio: no hay fuente a la que re-apuntar. El hueco
que tapaban lo cerró (B) en `#68` —el diff de árbol ejecuta `calendar.js`— y las dos fronteras que
declaraban medidas viven en `calendar.test.js`.

**(b) ⚠️ El caso de los husos NO compara motores: compara el cliente consigo mismo.** Corre
`buildWeeks()` en dos procesos Node con `TZ` distinto y exige la misma rejilla. Eso
`calendar.test.js` **no puede hacerlo** —corre en un solo proceso y el huso se lee al arrancarlo—, así
que es la única red de una defensa que ya se comprobó **inerte** con el huso del contenedor. Sobrevive.

**(c) Y conducía el componente sin usarlo.** El caso montaba `Livewire::test(Purchase::class)` y
sembraba un producto que no aparece en ninguna aserción. Medido: quitando los dos, los cinco casos
siguen verdes. Retirados — un acoplamiento vestigial en un fichero condenado es exactamente lo que
hace que una clasificación se lea mal al borrar.

**(d) Lo que esto deja escrito para ·2b·3**: este fichero es de los pocos donde hay que **operar
DENTRO** en vez de borrarlo entero. Está anotado en su propio docblock, que es donde lo va a leer
quien lo borre.

## #80 · 2026-08-15 · 4.7·2b·2 — el view-model que servía de referencia no era una fuente
Quinta de la auditoría: `SidebarCartParityTest` (3 usos). Es el caso más claro de la tercera categoría
de `#75`: la referencia (`Purchase::viewData('footer')` y `viewData('cartLines')`) **no era una
fuente**, era un ensamblaje de `__()`, `number_format` y el presupuesto — las tres sobreviven.

**(a) Los tres casos del pie se re-apuntan al diccionario y a `number_format`.** Y no a la ligera:
**medido, renombrar `footer_pay_now` en `lang/es/tickets.php` deja los 2715 casos verdes salvo UNO**, y
es el del pie de la cesta. `foot.test.js` no puede verlo —su diccionario es fabricado— y
`SidebarTextParityTest` no lleva esas claves. Los tres fallos que el fichero nació para impedir siguen
cubiertos: el separador de millares, el rótulo NEUTRO de la cesta frente al «(señal)» del paso 3, y el
recuento pluralizado por Laravel.

**(b) Se compara MENOS y mejor.** Antes se comparaba el view-model entero contra el de Livewire; ahora
solo los **textos e importes**, que es lo único que ni el diff de árbol ni `foot.test.js` pueden mirar
—el normalizador descarta los nodos de texto y el árbol ya ejecuta `foot.js` desde `#71`—. La
estructura tiene dueño; duplicarla aquí era medir el andamiaje.

**(c) El caso de las filas conserva lo suyo cambiando de referencia.** Su valor es el **hueco en la
secuencia de `index`** cuando un producto deja de venderse: el presupuesto salta esa línea y el
`index` es lo único que ata cada fila a su cesta. El diff de árbol no puede verlo —sus fixtures tienen
UNA línea, y con una línea emparejar por posición sale verde (`#70`)—. La referencia pasa a ser el
presupuesto REAL, que es de donde `Purchase` lo sacaba, y se añade la aserción que faltaba: que la
fila del pack lleve SUS respuestas y no las de la línea que ocupa su posición.

**(d) Y la cesta se compone directamente, sin conducir ningún motor.** Es una lista de líneas: lo que
este fichero prueba es qué se PINTA con una cesta dada, no cómo se llena. Eso retira de paso la última
razón por la que necesitaba un componente.

**(e) Siete mutaciones, las siete rojas**: dos claves ausentes del diccionario (`footer_pay_now`,
`iva_note`), el rótulo cruzado, el plural ingenuo, el marcador «—» sustituido por «0,00 €», el
separador de millares y emparejar las filas por la primera línea de la cesta.

**(f) Contador: 25 → 24.**

## #81 · 2026-08-15 · 4.7·2b·2 — congelar la referencia ANTES de que el motor se vaya
Sexta de la auditoría: `SidebarPausedParityTest` (4 usos). Contador **24 → 23**. Mismo patrón que
`#80` —la referencia no era una fuente— con una técnica nueva que conviene dejar escrita.

**(a) El título y el mensaje eran un intermediario de un renglón.** `Purchase::pausedTitle()` es
literalmente `return MaintenanceSettings::reservationTitle();`. Se re-apunta ahí: es dominio, lo lee
el panel y sobrevive. Lo mismo con el mensaje, y en los tres idiomas —el override es POR IDIOMA—.

**(b) ⚠️ Los enlaces no se podían re-apuntar a nada: se CONGELARON contra el motor vivo.** Los `href`
los componía el Blade con `contact.phone` y `contact.whatsapp`. Los ajustes sobreviven; la composición
(`tel:` sin espacios, `wa.me` solo con dígitos, el rótulo con el número tal y como lo escribió la
dueña, el respaldo a `/contacto` **solo** si no hay ningún canal directo) vivía en la plantilla. Antes
de sustituirla se **volcó lo que emitía de verdad en los cuatro estados de canales** y se escribió una
composición que reproduce exactamente eso, verificada contra el motor todavía en pie. Es el mismo
criterio de `#60` al congelar el manifiesto de DOM: **una referencia que se va se mide antes de
perderla, no se reconstruye de memoria después**.

**(c) Lo que muere es la mitad «lo mismo que el servidor», no la cobertura.** Cinco mutaciones sobre
`paused.js`, las cinco rojas: tapar además un paso de resultado, mandar el WhatsApp a otro destino,
poner en el `tel:` el número rotulado (con espacios), sacar el título del diccionario en vez del panel,
y que el canal de llamar deje de ser el principal. Ninguna la ve el diff de árbol —`href`, `target` y
`rel` no son atributos de contrato y el texto se descarta—, que es la razón de ser del fichero y sigue
intacta.

**(d) Y la lista de pasos tapados pasa a ser la DECISIÓN, no un espejo.** Antes se afirmaba contra
`showPausedNotice()` del componente; ahora se escribe paso a paso, porque en qué pasos se tapa el flujo
no lo publica ningún endpoint: es una regla de interfaz. Los de resultado (6, 7, 9, 10 y 11) quedan
fuera porque son acciones ya iniciadas que deben poder completarse.

## #82 · 2026-08-15 · 4.7·2b·2 — el primer caso que se RETIRA por redundante, medido
Séptima de la auditoría: `SidebarProgressParityTest` (4 usos). Contador **23 → 22**. De sus tres casos
uno se va, uno no se toca y uno se congela.

**(a) La comparación de la banda se retira, y no por descuido.** Nació porque el diff de árbol
alimentaba a Vue con el view-model del servidor; ese hueco lo cerró (B) en `#71`, cuando el árbol pasó
a **ejecutar** `progress.js`. **Medido**: las dos mutaciones que esta comparación cazaba —clavar la
fase activa y vaciar el producto del contexto— dejan rojo **también** `progress.test.js`. Es el primer
caso de la auditoría que se retira entero por redundancia demostrada, no por clasificación.

**(b) ⚠️ Y se comprobó qué se iba con él, que es la parte que se olvida.** `ucfirst()` solo se ejerce
desde `buildProgress()`, así que al retirar la comparación había que mirar si alguien más lo cubría:
mutarlo deja **dos casos rojos en `progress.test.js`** y el diff de árbol verde —descarta el texto—.
Cobertura intacta. **Retirar un caso obliga a medir qué dejaba de estar cubierto, no solo que la suite
siga verde.**

**(c) El mapa de «modo» se congela contra el motor vivo** (misma técnica que `#81`): `stepModeMap()`
era la única declaración que existía y su consumidor **sobrevive** —`layout.blade.php` pinta
`is-{modo}` en `.sidecart__panel`—. Se volcó y se escribió como decisión, con el 8 (pago) en `cart` y
no en `result`, que es la divergencia que este caso destapó en 4.3·1. Dos mutaciones sobre `modeOf()`
lo dejan rojo.

**(d) La fecha del contexto se queda intacta**: es el único texto del cajón cuya fuente NO comparten
los dos lados —Carbon frente a `Intl`— y el caso acota hasta dónde llega la divergencia del español.
Eso no depende de ningún motor.

## #83 · 2026-08-15 · 4.7·2b·2 — el manifiesto está anclado en el motor que se va (y en el que vende)
Octava de la auditoría: `SidebarDomContractTest` (26 usos). **El contador no se mueve**, y la
conclusión CORRIGE la nota de handoff que dejó `#82`: aquella decía que el candidato natural era que
el test «dejara de comparar y pasara a afirmar el manifiesto congelado», y que convenía hacerlo ANTES
de `SidebarOutcomeParityTest`. Escrito leyendo. Medido, las dos mitades de esa frase están mal.

**(a) No se puede hacer hoy, y el motivo está en dos líneas de `assertTree()`.** El manifiesto se
compara contra **`$livewire`**, no contra `$vue`; la SPA solo queda cubierta por transitividad, a
través del `assertSame($livewire, $vue)` que va justo antes. Y `SidebarSettings::engine()` devuelve
`livewire` **por defecto y como fallback**, así que el motor anclado es además **el que hoy sirve**.
Quitarle su red ahora dejaría sin prueba de DOM justo al que vende, a cambio de nada.

**(b) Tampoco decide nada sobre `SidebarOutcomeParityTest`.** El razonamiento de `#82` copiaba la
forma de (B) —«resolverlo primero decide cuáles de las otras siguen teniendo pregunta»—, pero (B)
cambiaba **de qué se alimenta** el diff, y esto no cambia nada de lo que el diff ve: solo quita un
motor cuando desaparezca. `SidebarOutcomeParityTest` se audita por su cuenta.

**(c) Lo que sí deja hecho este paso: las TRES operaciones exactas de ·2b·3**, escritas donde se van a
leer —en el propio `assertTree()`—. (1) borrar la comparación entre motores **y mover el anclaje a
`$vue`** en sus dos apariciones, incluida la rama de `MANIFEST_REFRESH`; (2) retirar los
renderizadores del motor viejo; (3) **conservar `assertBundleIsNotStale()`**, que pasa a ser la única
guarda de que el artefacto no está rancio. Si solo se hiciera (1) a medias —borrar la comparación y
dejar el anclaje— el manifiesto vigilaría un motor inexistente y Vue se quedaría sin nada.

**(d) Y un riesgo asumido, escrito para que nadie lo descubra tarde**: hoy regenerar el manifiesto es
seguro porque la igualdad entre motores lo respalda. Sin segundo motor, `MANIFEST_REFRESH=1` acepta
cualquier deriva sin que nada la contradiga. Es el precio del árbol congelado que `#60` ya aceptó; a
partir de ·2b·3 la única guarda es la disciplina de declararlo en el commit.

**(e) La lección de proceso, que es la misma de toda la auditoría**: una nota de handoff escrita
leyendo el código es una hipótesis, no un plan. Esta llevaba dos días en `ESTADO.md` y habría costado
una sesión entera de trabajo mal dirigido.

## #84 · 2026-08-15 · 4.7·2b·2 — la última paridad, medida entera: cuelga de UNA pieza
Novena y última de la auditoría: `SidebarOutcomeParityTest` (10 usos, 14 casos, 863 líneas). Queda
**medida entera y con el plan escrito**, pero **el contador no se mueve todavía** y conviene decir por
qué con precisión: no es que falte decidir nada, es que el re-apunte **no se puede hacer por partes**.

**(a) Todo el fichero cuelga de `purchase()`.** Ese ayudante fabrica una compra REAL conduciendo el
componente —catálogo, día, hora, respuestas del pack, complemento, carrito, `checkout()`,
`confirmReservation()`— y devuelve `[componente, usuario, código]`. **Los catorce casos parten de ahí**,
así que no hay ningún caso que se pueda re-apuntar solo: o se cambia esa pieza a `POST /api/v1/orders`
—receta ya probada en `#65`, con `Origin` y la cesta en el cuerpo— o no se mueve ninguno. Esa es la
única razón por la que este tramo no cerró hoy, y está escrita para que nadie vuelva a medirlo.

**(b) Lo que sí queda medido, caso por caso:**
· **Los motivos del rechazo son lo ÚNICO que solo caza este fichero.** Renombrar
  `payment_failed.reasons.cvv_wrong` en `lang/es/tickets.php` deja los 2714 casos verdes salvo **dos**,
  y los dos son de aquí. `outcome.test.js` no puede verlo —su diccionario es fabricado—.
· **Y su referencia SOBREVIVE**: `Purchase::resolveDeclinedReason()` termina en
  `RedsysResponseCode::reasonText()`. Es un intermediario, como el de `#81`. De hecho el caso que
  recorre `REASON_MAP` entero **ya compara contra el dominio** y no toca el componente.
· **Los destinos del reintento y del sondeo son redundantes**: mutar `answersByReservation()` y
  `pollVerdict()` deja rojos **a la vez** la paridad y `outcome.test.js`. Misma conclusión que `#78`;
  lo que sobrevive no es el destino sino que lo provoquen respuestas REALES de la API.
· **Los dos casos del enlace de registro MUEREN**: `PublicConfigTest` ya cubre lo mismo y mejor —el
  saneado de `SEC-07` con cuatro URLs hostiles, que aquí no se prueba—.
· **El caso de los enlaces del desenlace se parte**: su primera mitad ya es del servidor
  (`urls.contact`/`urls.my_orders` contra `route()`) y sobrevive; la segunda comprueba que el Blade
  pinta esos `href` y muere con él.
· **La divergencia declarada del rechazo anterior** (Livewire enseña el motivo de un intento viejo con
  otro cobro en vuelo; la API no) **desaparece sola**: sin segundo motor no hay divergencia, y lo que
  queda es la conducta de la API, que es la que acierta.

**(c) ⚠️ Un residual que hay que decidir en ·2b·3, no descubrir**: la mitad del Blade del caso de los
enlaces es lo único que hoy comprueba que un motor PINTA esas URLs. El diff de árbol no puede
sustituirla —`href` no es atributo de contrato— y los módulos planos no las tocan: las consumen los
`.vue`. O se añade una aserción sobre el HTML de Vue al reestructurar `SidebarDomContractTest`
(`#83`), o se acepta que solo se verifica que el servidor las publica.

**(d) Nota de proceso: la guarda del bundle rancio saltó por CUARTA vez en tres días**, otra vez por
mutar un módulo y restaurarlo con `git checkout`. Ya está en `ESTADO.md` como aviso; van cuatro.

## #85 · 2026-08-15 · 4.7·2b·2 — deshecho el nudo: la compra se crea por la API
Cierra lo que `#84` dejó medido y planificado. `SidebarOutcomeParityTest` **fuera del inventario**:
contador **22 → 21**. Y confirma que el diagnóstico de `#84` era correcto: cambiada UNA pieza, los
catorce casos encontraron su referencia sin pelea.

**(a) `purchase()` crea el pedido con `POST /api/v1/orders`** (receta de `#65`, con `Origin` y la cesta
en el cuerpo) en vez de conducir el componente. Además de desatar el nudo es **más fiel**: el pedido
nace por el mismo camino que usará el cajón, con su señal, sus respuestas de evento y un complemento
INCLUIDO con una unidad extra.

**(b) Cuatro casos RETIRADOS, cada uno con su medición.** Los dos que comparaban el resumen contra el
view-model: su composición la cubre `outcome.test.js` y sus campos `OrderSummaryFieldsTest` y
`OrderEventDataTest` —y se midió que **componer `park_cents` restando es un mutante EQUIVALENTE para
este fixture**, así que esa comparación ni siquiera cazaba el error que el propio módulo advierte—. Y
los dos del enlace de registro: `PublicConfigTest` cubre lo mismo **mejor**, con las cuatro URLs
hostiles del saneado de `SEC-07` que aquí no se probaban. El emparejado por `reservation_id` no se
pierde: lo prueba el caso que da la vuelta al sobre, que nunca tocó el componente.

**(c) Las tres divergencias declaradas se quedan con su mitad viva**: el resumen acotado a la fase
`booking` (RGPD), el estado EFECTIVO de un hold caducado y el silencio de la API sobre un rechazo
anterior con otro cobro en vuelo. En las tres la API es la que acierta, así que al irse el otro motor
la divergencia desaparece y queda la conducta correcta.

**(d) Los destinos del reintento y del sondeo se VOLCARON del motor vivo** antes de retirarlo (técnica
de `#81`): eran su única declaración escrita. Quedan como decisión —pausa y límite **no mueven a
nadie**; solo `order_not_retryable` devuelve al catálogo— con el porqué al lado.

**(e) La referencia de los motivos es el DOMINIO.** `Purchase::resolveDeclinedReason()` terminaba en
`RedsysResponseCode::reasonText()`: intermediario puro. Y la razón de ser del fichero sigue medida —
renombrar `payment_failed.reasons.cvv_wrong` deja los 2710 casos verdes **salvo dos, los dos de aquí**.

**(f) Un mutante verde, comprobado y no asumido**: quitar la caída a `default` de `declinedReasonText`
no pone rojo este test, porque hoy toda clave de `REASON_MAP` existe en `lang/`. Lo caza
`outcome.test.js` —verificado, 1 rojo—, que es quien debe. Cada nivel prueba lo suyo.

**(g) Balance de la auditoría, que aquí termina.** De las nueve paridades: **siete re-apuntadas y
fuera del inventario** (`#75`, `#77`, `#78`, `#80`, `#81`, `#82`, `#85`) y **dos que mueren con el
componente** operando DENTRO, con sus instrucciones escritas donde se leerán (`#79`, `#83`).
Contador **27 → 21**.

## #86 · 2026-08-15 · 4.7·2b·2 — la meta no era 0, y el tramo de `Sales/` no tiene nudo
Arranca la auditoría de los dependientes que **no son paridades**, y lo primero que da la medición son
dos correcciones al plan antes de tocar ningún test.

**(a) ⚠️⚠️ El contador NO puede llegar a 0, y la doc decía que sí.** Un fichero cuyo SUJETO es la
superficie del motor —`PurchasePanelTest` (40 casos), el diff de árbol (34), el aviso del spinner…—
no se puede re-apuntar a nada: **muere CON el componente**, así que solo sale del inventario en el
mismo commit que lo borra. Medido hoy: **nueve de los que quedaban ya están en ese grupo**. La
condición terminal real es **«todas las entradas restantes están CLASIFICADAS como que mueren»**, y
entonces ·2b·3 las borra de golpe. Queda escrito en el propio `PurchaseRetirementTest`, que es donde
lo va a leer quien persiga el número.

**(b) Y aquí NO hay nudo, al contrario que en `#85`.** La lección de aquel paso —«busca la pieza de la
que cuelgan todos»— se aplicó y **dio negativo**: los doce ficheros de `Sales/`+`Maintenance/` tienen
cada uno sus propios ayudantes pequeños (`withCart`, `setupFailedOrder`, `orderFor`, `pause`…), sin
nada compartido. Se auditan uno a uno; el apalancamiento está en otro sitio.

**(c) La pregunta de la auditoría CAMBIA de forma.** Para las paridades era «¿qué afirma esto que el
diff ya no afirme?». Para estos es **«¿su sujeto es el DOMINIO —y el componente solo conduce— o es la
SUPERFICIE Livewire?»**. Los primeros se re-apuntan (y bajan el contador); los segundos mueren, como
`PurchasePanelTest`.

**(d) Primer fichero: `AddonDependencyTest`, dos casos RETIRADOS por redundantes.** Sus dos casos de
«checkout público» ejercían la poda de dependencias conduciendo el componente hasta el carrito. Dos
mutaciones sobre `AddonResolver::buildSelection()` —que deje de podar huérfanos, y que un dependiente
no viaje nunca ni con su requisito puesto— dejan rojos **tres** casos, y **dos de los tres
sobreviven**: `test_build_selection_excludes_an_orphan_dependent` (en el mismo fichero, llamando al
dominio directo) y `CatalogAddonsTest::test_the_dependency_pruning_follows_the_whole_chain`, que
además recorre la cadena A→B→C a punto fijo con las posiciones invertidas a propósito. **La regla está
mejor cubierta donde se queda que donde estaba**; lo único que se pierde es la travesía por la UI del
motor que se va, y eso se va igual.

**(e) Contador: 21 → 20.**

## #87 · 2026-08-15 · 4.7·2b·2·C — cuando el sujeto no es la regla sino DÓNDE se aplica
Segundo fichero del tramo: `CatalogVisibilityAndCartPruneTest` (1 uso, 3 casos). **El contador no se
mueve**: su caso acoplado **muere con el componente**, y los otros dos no lo tocan — así que ·2b·3
opera DENTRO, como en `#79`.

**(a) La distinción que este fichero enseña.** Su regla —una línea cuyo producto dejó de venderse no
puede contar en la cesta— **sí sobrevive**; lo que muere es **dónde se aplica**: en
`Purchase::mount()`, leyendo la cesta de la SESIÓN. En el cajón esa regla vive partida en dos sitios y
los dos tienen red. **La pregunta de la clasificación hay que hacérsela al SUJETO del caso, no a la
regla que menciona**: una regla que sobrevive no salva un caso que prueba una superficie que se va.

**(b) Medido, no leído.** Mutando el filtro `sellable()` de `CartPricer` —que el presupuesto tarifique
lo que ya no se vende— caen exactamente tres casos, **los tres supervivientes**: `Api\V1\QuoteTest`,
`Sales\CartPricerTest` y `Sidebar\SidebarCartParityTest` (el que se re-apuntó en `#80`). **Y el caso de
este fichero NO cae**, que es la prueba de que ejerce otro camino: el de `mount()`. La otra mitad —que
el cliente descarte la línea que no volvió tarificada— es `cart.js::reconcile()`, con sus casos en
`cart.test.js`.

**(c) ⚠️ Y una medición que se DESCARTA por inconcluyente, anotada para que nadie la repita.** Para
clasificar `PurchaseConfirmationStatusTest` se probó a mutar `Order::displayStatus()` para que mintiera
siempre: la mutación es **demasiado ancha** —tumba media docena de casos del panel de administración
que no tienen nada que ver con la pantalla final de la compra— y su lista de cazadores no dice nada
sobre este tramo. Ese fichero queda **sin clasificar** a propósito: hace falta una mutación dirigida al
lado SPA (`buildConfirmation()`), no al modelo. Una medición ruidosa no es una medición.

## #88 · 2026-08-15 · 4.7·2b·2·C — la mutación dirigida cierra el cabo, y destapa un hueco
Cierra lo que `#87(c)` dejó abierto a propósito: `PurchaseConfirmationStatusTest` (3 usos, 3 casos).
**El contador no se mueve** —el fichero ENTERO muere con el componente— pero el paso deja dos cosas.

**(a) La mutación dirigida sí concluye.** En vez de mutar `Order::displayStatus()` —que tumba media
docena de casos del panel y no dice nada—, se clava `buildConfirmation()` a `'pending'`: que el
resumen del cajón deje de distinguir pagado de pendiente. Caen **dos** casos y **los dos sobreviven**:
el paso 6 del diff de árbol —que a partir de ·2b·3 afirma el manifiesto congelado— y
`SidebarOutcomeParityTest::test_an_expired_hold_is_reported_as_expired_by_the_api`. Y
`PurchaseConfirmationStatusTest` **no cae**, que es exactamente la prueba de que ejerce otro camino:
el del Blade. Sujeto = superficie → muere, y ·2b·3 lo borra ENTERO.
**Lección: una mutación se apunta al mecanismo que se quiere clasificar, no al dato que ambos leen.**

**(b) ⚠️ Y de paso destapó un hueco en el lado JS.** Con `status` clavado, `outcome.test.js` seguía en
**verde**: el test del propio módulo no fijaba su mapeo del estado, aunque el módulo entero existe
para componer esa pantalla. Lo cubrían dos casos PHP, pero **no quien debe**. Nace el caso —los tres
estados más el pedido ausente—, verificado por mutación: 27 verdes limpios, 26+1 rojo con `status`
clavado. **Auditar para retirar también encuentra huecos en lo que se queda.**

## #89 · 2026-08-15 · 4.7·2b·2·C — tres reglas publicadas que no guardaba nadie
Tercer fichero del tramo: `AddonInclusionPurchaseTest` (7 usos, 7 casos). **Muere ENTERO** —su sujeto
es el puente UI → carrito, lo dice su propio docblock— y el contador no se mueve. Pero el paso vale
por lo que la auditoría encontró de camino, que no era lo que se buscaba.

**(a) ⚠️⚠️ Tres reglas de dominio que la API publica y que NADIE verificaba.** Medido, una a una,
contra los 2708 casos: quitarle a `can_decrease` su `$qty > $min`, a `can_increase` su tope `max_qty`
y a `can_toggle` su exigencia de por-invitado deja la suite **entera en verde**. Son los tres botones
del paso con más clics del embudo: un valor equivocado pinta un `−` que no se puede pulsar, o —peor—
uno que sí y lleva la cesta por debajo de lo que el producto EXIGE.

**(b) Y el punto ciego tenía forma, no era descuido.** `SidebarAddonsParityTest` compara la fila
publicada contra el modelo de vista del dominio… y **las dos mitades salen del mismo `viewModel()`**,
así que mutar la REGLA es un mutante equivalente para él (mutar la TRADUCCIÓN sí lo caza — es lo que
`#77` midió). Y los casos de este fichero, que parecían cubrirlo, ejercen **la guarda propia del
componente**: por eso tampoco caían. Dos redes distintas, y el hueco justo en medio.

**(c) La cobertura se escribió ANTES de clasificar el fichero como prescindible.** Tres casos nuevos
en `Api\V1\CatalogAddonsTest` —quien publica esos campos—: el mínimo del obligatorio cierra el `−` y
solo en el mínimo, el tope cierra el `+` y solo en el tope, y el interruptor lo es solo el
por-invitado opcional. Verificados con las tres mutaciones, las tres rojas.
**La cobertura equivalente no siempre existe: a veces hay que escribirla.**

**(d) Los otros cuatro casos del fichero sí tenían equivalente**, y está localizado: los defaults del
obligatorio y del grupo, el complemento de pago sin tarifa que ni se ofrece, la cantidad del
por-invitado y el cero que retira — los cuatro en `CatalogAddonsTest` desde 4.0b·5.

## #90 · 2026-08-15 · [DECIDIDO] CE-6 con dientes: `Sidebar.vue` era el segundo objeto-dios
Lo levanta el owner mirando el fichero: 1.477 líneas. La sospecha era **«estamos limpiando un
objeto-dios para crear otro»**, y medida resulta exacta — con un matiz que cambia la forma de la
solución.

**(a) La medición.** `Sidebar.vue` tiene **618 líneas de CÓDIGO** (de 1.477 crudas: el resto es
documentación) y **las 11 llamadas a la API del cajón**. Los otros **18 componentes** suman 236 entre
todos, ninguno pasa de **24** y ninguno toca la API. Y sus 41 funciones son los MISMOS métodos de
`Purchase.php`: `selectProduct`, `addToCart`, `checkout`, `confirmReservation`, `retryPayment`,
`goBack`… Se estaba construyendo el segundo mientras se desmontaba el primero.

**(b) El matiz que salva el trabajo hecho**: los 18 módulos planos (3.060 líneas, 287 tests) **sí**
existen y tienen la lógica pura. Lo que queda dentro del `.vue` es **orquestación**, y el patrón para
sacarla ya está probado —`admission.js::runCheckout()`, con sus dependencias por parámetro—. No hay
que rediseñar nada: hay que aplicarlo ~10 veces más.

**(c) ⚠️ La causa raíz era que CE-6 no tenía dientes.** Llevaba escrito en la spec desde el principio
y **no lo vigilaba nada**: las únicas guardas del cajón son presupuestos de KiB del bundle, que un
`<script>` de 1.363 líneas pasa sin despeinarse. Es exactamente cómo creció `Purchase.php` en su día.
Nace `SidebarComponentBudgetTest`: techo de código por componente, cero llamadas a la API desde un
`.vue`, y `Sidebar.vue` como **excepción declarada que solo puede encoger** —en las dos direcciones:
crecer es regresión, y bajar sin actualizar el número deja la baseline floja—.

**(d) Se cuentan líneas de CÓDIGO, no crudas, y no es un detalle.** Un contador de líneas crudas
habría convertido **«borra los comentarios» en una forma legítima de pasar el test**, en un proyecto
cuya documentación es la mitad del fichero. La guarda mide lógica; la prosa que la explica no estorba.
Hay un caso que lo fija sobre una muestra sintética.

**(e) Cuatro mutaciones, las cuatro rojas**: un componente que cruza el techo, uno que llama a la API,
la excepción que crece, y el escáner roto —esta última cae por partida doble, incluida la guarda de la
guarda—.

**(f) ⚠️ Y una trampa de proceso que casi cuela una guarda ROTA**: al revertir la mutación del escáner
con `git checkout`, el comando falló **porque el fichero era nuevo y no estaba trackeado** — el
`Updated 1 path` de las otras tres no salió, pero entre el ruido no se ve. La mutación siguió dentro
hasta que se comprobó a mano. **Al mutar un fichero SIN TRACKEAR, `git checkout` no revierte nada:
comprueba el contenido, no el código de salida.**

**(g) No se mezcla con 4.7.** La extracción queda en `DEUDA.md` como **Alta** y sin plan de fase: no
bloquea la retirada y meterla en medio ensuciaría los dos trabajos. Lo que sí cambia desde hoy es que
la deuda **deja de crecer** y pasa a ser un número que baja.

## #91 · 2026-08-15 · 4.7·2b·2·C — el canario era un intermediario, y el fichero no es homogéneo
Cuarto fichero del tramo: `DepositSurfacesTest` (6 usos, 12 casos). **El contador no se mueve** —los
cuatro casos de UI mueren con el componente y ·2b·3 opera DENTRO— pero el fichero baja de 6 usos a 4 y
queda clasificado entero.

**(a) Los dos casos del CANARIO anti doble-fuente, retirados por redundantes.** Comparaban
`Purchase::cartDepositCents()` con `Order::onlineDueCents()` para la misma cesta. Pero ese método es
**literalmente** `return $this->quote()->onlineAmountCents;`: un intermediario de `CartPricing`, igual
que el `pausedTitle()` de `#81`. El salto por el componente no añadía nada.

**(b) Medido, con la comprobación en las dos direcciones.** Mutando `onlineDueCents()` para que cobre
el total en vez de la señal caen **catorce** casos, y los que hacen exactamente esta pregunta
**sobreviven**: `CartPricerTest::test_the_quote_of_a_mixed_cart_matches_what_the_order_will_charge` —el
canario sin componente— y `DepositChargeTest::test_addons_of_a_deposit_product_are_fully_charged_at_
the_park`, que es el segundo caso (Opción A). Repetida la mutación **ya sin los canarios**, siguen
cayendo **doce**: la cobertura está intacta y no se perdió nada al retirarlos.

**(c) Los cuatro casos de UI mueren, con su equivalente localizado**: el desglose del pie lo fija
`SidebarCartParityTest` desde `#80` —`nowLabel`/`now`/`park` contra el diccionario y `number_format`—,
el estado del ⓘ lo compara el diff de árbol, y el anuncio del catálogo sale de `deposit_catalog`, que
está en la lista de `SidebarTextParityTest`. Los otros seis casos del fichero **no tocan el
componente** y se quedan tal cual: el email, el PDF, el desglose por producto y los dos de sobrecobro.

**(d) ⚠️ Nota de proceso: el primer corte se llevó UN canario, no dos.** El segundo estaba después del
caso del email, así que la rebanada «del primero al siguiente método» no lo incluía — y el docblock que
se escribió a la vez ya afirmaba que los dos estaban fuera. Se vio contando los métodos después, no
antes. **Al cortar por rangos, cuenta lo que queda; el texto que lo explica se escribe con el
resultado, no con la intención.**

## #92 · 2026-08-15 · 4.7·2b·2·C — la tercera mutación mal apuntada, y por qué la disciplina se paga sola
Quinto fichero del tramo: `PurchaseLimitsTest` (13 usos, 10 casos). **Muere con el componente** y el
contador no se mueve. Pero lo que hay que contar de este paso es cómo estuvo a punto de salir mal.

**(a) Una mutación mal apuntada me hizo creer que había un agujero de abuso.** Al desactivar el
límite de frecuencia, `PurchaseLimitsTest` seguía **entero en verde** —incluidos sus tres casos que
dicen probarlo— y los únicos que caían eran de REINTENTO. La lectura obvia era grave: *«el límite de
crear reservas no lo detecta nadie»*.

**Era falso.** `ReservationAdmissionPolicy` tiene **DOS** `tooManyAttempts`: uno en
`admitPaymentRetry()` (línea 61) y otro en `evaluate()` (línea 94). El `str_replace(..., 1)` cambió el
PRIMERO, o sea el del reintento. Todo encajaba: caían los de reintento porque eran justo los que había
roto. **La coincidencia entre lo que rompes y lo que cae puede ser una explicación completa de un
experimento que no hiciste.**

**(b) Bien apuntada, la respuesta es la contraria: cobertura de sobra.** Caen **once** casos y **nueve
sobreviven** —`ReservationAdmissionPolicyTest` ×2, `OrdersTest`, `ReservationEligibilityTest` ×2,
`CheckoutOrchestratorTest` ×2 y `SidebarAdmissionParityTest` ×2 (la re-apuntada en `#78`)—. Los otros
dos topes, igual: el de líneas lo guardan `OrderCreatorTest` y `CartLineValidationTest`; el de
pendientes, siete supervivientes.

**(c) La regla, por tercera vez en dos días, y ahora con su forma exacta.** Ya estaba escrita en
`#77(e)` («comprueba dónde cayó») y en `#90(f)` («comprueba el contenido, no el código de salida»).
Le falta una tercera cara: **cuando el mismo símbolo aparece más de una vez en el fichero, una
sustitución posicional muta el que no es — y el resultado puede ser coherente con la hipótesis
equivocada**. Antes de creerse un hueco: `grep -c` del ancla, y exigir que sea única.

**(d) ⚠️ Dos casos quedan SIN medir a propósito**, anotados en el fichero para que ·2b·3 no los borre a
ciegas: `test_unverified_user_can_confirm_pay_first` y, sobre todo,
`test_confirm_does_not_send_email_until_redsys_authorises` — una regla de producto (el correo no sale
hasta que la pasarela autoriza) de la que **no se ha comprobado que quede guarda**.

## #93 · 2026-08-15 · 4.7·2b·2·C — cerrar los cabos de `#92`, y uno era otro hueco real
`#92` dejó dos casos de `PurchaseLimitsTest` **sin medir a propósito**, anotados para no borrarlos a
ciegas. Medidos ahora, uno se va sin pérdida y el otro era un agujero.

**(a) «El correo no sale hasta que la pasarela autoriza» — cubierto, se va sin pérdida.** Haciendo que
`RedsysReturnHandler` notifique **autorice o no**, caen **siete** casos y los siete SOBREVIVEN:
`RedsysNotificationEndpointTest` ×3 y `RedsysReturnHandlerTest` ×4. El punto de aplicación está bien
guardado; lo que se va es la travesía por la UI.

**(b) ⚠️ «Se paga primero y se verifica después» — NO lo guardaba nadie.** Añadir un
`abort_if(! $user->hasVerifiedEmail(), 403)` a `POST /api/v1/orders` deja los **2713 casos en verde**.
Y no es una regla menor: `hasVerifiedEmail()` rebotaba al carrito y se retiró **a propósito** porque el
pago auto-verifica la cuenta al volver. Exigirlo dejaría fuera justo a quien acaba de darse de alta en
el cajón —el camino normal de un cliente nuevo— y el cobro es precisamente lo que confirma que ese
correo es suyo. La regla vivía escrita **solo** en un test del motor que se retira.

**(c) Se fijó donde se aplica**: `OrdersTest::test_an_unverified_holder_may_pay_first_and_verify_later`,
verificado por mutación en las dos direcciones.

**(d) La lección, que ya va por su segunda vez** (`#89` fueron tres reglas `can_*`): **un caso «sin
medir» en un fichero condenado es deuda con fecha de caducidad**. Si nadie lo mide antes de ·2b·3, la
regla se va con el fichero y nadie se entera — y las dos veces que se ha medido, había hueco. Los
`⚠️ sin medir` de los ficheros ya clasificados hay que cerrarlos **antes** del borrado, no durante.

## #94 · 2026-08-15 · 4.7·2b·2·C — el reintento y el sondeo, con su dinero ya guardado en otro sitio
Sexto fichero del tramo: `PurchaseRetryAndPollingTest` (11 usos, 10 casos). **Muere con el componente**
y el contador no se mueve. Es el que más superficie de dinero toca de los que quedaban, así que se
midieron sus dos reglas críticas antes de darlo por prescindible — y esta vez **no había hueco**.

**(a) El reintento abre un `gateway_order` NUEVO.** Reusar el anterior significa firma duplicada y
cobro rechazado por la pasarela. Mutando `PaymentInitiator` para que reutilice el último caen **diez**
casos y **nueve sobreviven**: `PaymentInitiatorTest` ×2, `Account\PaymentRetryFromOrdersTest` ×4,
`Api\V1\OrdersTest::test_the_retry_opens_a_new_payment_and_extends_the_hold`,
`SidebarOutcomeParityTest` (el re-apuntado en `#85`) y `ReservationPauseGuardTest`.

**(b) La defensa IDOR del reintento.** Quitando el filtro por titular de `extendHold()` caen **cinco**
y **cuatro sobreviven**, con los dos que la nombran explícitamente:
`OrdersTest::test_a_stranger_cannot_retry_someone_elses_order` y
`ReservationAdmissionPolicyTest::test_a_denied_retry_does_not_touch_another_users_order`.

**(c) El resto, con su equivalente localizado**: la ventana del hold la fijan los mismos dos
endpoints; los tres desenlaces del sondeo y el motivo del rechazo —incluido el código desconocido que
cae al genérico— los cubre `SidebarOutcomeParityTest` desde `#85`. Lo único sin equivalente es
`addAnother`, que limpia el estado residual del componente: superficie pura.

**(d) Y conviene anotar el resultado NEGATIVO.** Tras `#89` y `#93`, la pregunta al abrir un fichero
era «¿qué hueco esconde?». Aquí la respuesta es **ninguno**, y eso también es información: el terreno
del dinero se endureció por su cuenta (Fase 3 lo publicó por API y le puso tests propios), así que los
ficheros que solo lo *conducían* desde la UI son los que menos riesgo tienen al retirarse. **Medir para
no encontrar nada sigue siendo medir.**

## #95 · 2026-08-15 · 4.7·2b·2·C — los dos de identificación, y un mutante equivalente POR DISEÑO
Séptimo y octavo del tramo: `PurchaseRegistrationPromptTest` (5 usos) y `PurchaseIdentificationTest`
(3). **Los dos mueren con el componente**; el contador sigue en 20.

**(a) `PurchaseRegistrationPromptTest` ya es redundante HOY.** Sus cuatro reglas las cubre
`Api\V1\PublicConfigTest`, que es quien publica el bloque —y mejor: su caso de URL hostil prueba
**cuatro** esquemas peligrosos y aquí solo se prueba uno—. Medido quitando los `?:` de
`RegistrationLink::current()`: caen dos casos y el superviviente es el de `PublicConfigTest`. El quinto
caso, en qué pasos se enseña el bloque, es superficie pura.

**(b) `PurchaseIdentificationTest` conduce el modo `embedded`**, que se queda sin usuario al
desaparecer `purchase.blade.php` (`#61`). Sus reglas sobreviven repartidas: el alta y la
anti-enumeración en `AuthRegistrationTest`, la pausa en `OrdersTest`, y **pagar sin verificar en el
caso que nació de esta auditoría** (`#93`) — o sea que uno de sus cinco casos protegía algo que, hasta
hace dos commits, **solo protegía él**.

**(c) ⚠️ Y una medición que NO concluyó, escrita para que nadie la repita.** Para probar la
anti-enumeración se mutó `SelfSignup` cambiando `alreadyRegistered()` por `pendingVerification()`: la
suite entera se quedó **verde**. La lectura fácil sería «nadie guarda la anti-enumeración». Es falso:
esas dos respuestas son **indistinguibles a propósito** —eso ES la anti-enumeración—, así que la
mutación es **equivalente por diseño** y no puede probar nada.
**Regla nueva: una propiedad de INDISTINGUIBILIDAD no se prueba mutando una de las dos ramas; se
prueba comparando las dos respuestas entre sí.** Es la cuarta cara de la disciplina de mutación de
estos dos días, junto a `#77(e)`, `#90(f)` y `#92(a)`.

## #96 · 2026-08-15 · 4.7·2b·2·C — el catálogo y el sidebar v2, y el `type` que nadie fijaba
Noveno y décimo del tramo: `PurchaseCatalogGroupingTest` (11 usos) y `SidebarV2Test` (20 usos, el
fichero más grande del inventario). **Los dos mueren con el componente**; contador en 20.

**(a) `SidebarV2Test` lo decía en su propio docblock sin saberlo**: «cubre los VM que lo proyectan y el
MARKUP que los consume». Eso es la superficie. Sus tres piezas tienen hoy dueño propio con su red: el
«modo» en `machine.js` con el mapa congelado en `#82`, la banda en `progress.js` —que el diff de árbol
EJECUTA desde `#71`— y el pie en `foot.js`, con sus textos fijados por `SidebarCartParityTest` desde
`#80`. **Es el que más casos tiene (17) y el que menos deja al irse**: todo lo suyo se rehízo en Fase 4.

**(b) `PurchaseCatalogGroupingTest`: reglas de datos cubiertas, presentación que muere.** El filtro de
zona operativa lo guardan dos casos de `CatalogTest`; el umbral del buscador,
`PublicConfigTest::test_the_search_threshold_follows_the_setting`; el agrupado en secciones es del
cliente (`catalog.js`, `#67`) y lo ejecuta el diff de árbol.

**(c) ⚠️ Pero el `type` publicado no lo fijaba nadie.** Cruzando la traducción de `CatalogReader`
—entrada↔pack— `CatalogTest` se quedaba **en verde**: solo caían el diff de árbol y este fichero
condenado. No es cosmético: **el tipo es la sección en la que aparece cada producto**, así que un pack
de cumpleaños se ofrecería bajo «Entradas» con un árbol perfectamente válido. Y es un campo del
CONTRATO (`CatalogProduct::TYPE_*`, no la constante interna del modelo). Fijado en
`CatalogTest::test_each_product_publishes_its_own_type`, verificado por mutación.

**(d) Tercer hueco del tramo, y los tres del mismo tipo.** `#89` (tres reglas `can_*`), `#93` (pagar
sin verificar) y este: **campos que el servidor PUBLICA y que solo probaba el motor que se va**. El
patrón es reconocible y vale como criterio de búsqueda para lo que queda: *si un dato viaja al cliente
y su único test conduce la UI, el contrato no lo está fijando*.

**(e) Recuento corregido**: quedaba **uno**, no ninguno — `Maintenance/ReservationPauseTest` (5 usos)
sigue sin auditar. El anuncio anterior de «quedan dos» estaba mal contado.

## #97 · 2026-08-16 · La deuda de la FECHA muerde por segunda vez, y esta vez sin nombre
El `pre-push` del commit de `#96` cayó con **1 fallo** a las **00:02 de Madrid** (22:02 UTC del día
anterior en el contenedor). La suite había salido verde un minuto antes y el reintento inmediato
volvió a salir verde: **fallo transitorio en el cruce de medianoche**, el patrón exacto que `#64`
documentó y que `DEUDA.md` tiene abierto como barrido pendiente.

**(a) Lo que confirma.** No fue casualidad de aquella vez: hay al menos un caso que compone su fixture
con fechas relativas y cruza el día entre el montaje y la aserción. Con esto van **dos incidencias
medidas**, y el barrido deja de ser una precaución teórica.

**(b) ⚠️ Y lo que se hizo mal, que es lo aprovechable.** **No se supo qué caso fue**: la salida del
gate no se volcó a fichero y se perdió al reintentar. Un rojo transitorio sin nombre no se puede
arreglar, y el siguiente cruce de medianoche volverá a empezar de cero.
**Procedimiento desde hoy: si el `pre-push` cae, `git push > log 2>&1` ANTES de reintentar.** Está
anotado en `ESTADO.md` y en la ficha de `DEUDA.md`.

**(c) No se persigue ahora**: el reintento es verde, `main` está sano y el tramo en curso es otro.
Queda en `DEUDA.md` con las dos incidencias detrás y el criterio de búsqueda escrito —tests que
siembran franjas con `now()->addDays(n)` y aseveran sobre `today` sin congelar el reloj—.

## #98 · 2026-08-16 · 4.7·2b·2·C — el último del inventario, y el tramo queda CERRADO
Undécimo y último: `Maintenance/ReservationPauseTest` (5 usos, 11 casos). **No es homogéneo** y ·2b·3
opera DENTRO. Con él, **las 20 entradas del inventario quedan clasificadas** — que es la condición
terminal real de `#86`, no un contador a 0.

**(a) Seis casos no tocan el componente y se quedan.** Son de `MaintenanceSettings`: el fail-safe (solo
el literal «1» pausa), el valor corrupto que deja las reservas abiertas, la ausencia de banner global y
los dos de textos. Su guarda no depende de la retirada.

**(b) Los cinco `sidecart_*` mueren, con equivalente medido.** `SidebarPausedParityTest` los cubre
desde `#81`: título y mensaje contra `MaintenanceSettings`, los canales en sus **cuatro** estados
—incluido el respaldo a contacto cuando no hay ninguno— y en qué pasos se tapa el flujo. Medido
quitando el WhatsApp de `BookingStatusResource`: caen tres casos y **dos sobreviven**
(`Api\V1\BookingStatusTest::test_both_channels_travel_together` y esa paridad). **Este fichero no
cae**, que es la prueba positiva de que ejerce el camino del Blade.

**(c) ⚠️ Una segunda mutación DESCARTADA por ruidosa.** Se probó a quitar el fail-safe de
`reservationsPaused()` y la salida salió llena de casos del diff de árbol sin relación aparente. **No
se concluye nada de ella**, y además no hacía falta: esa regla la guardan los propios casos **no
acoplados** de este fichero, que sobreviven. Es la segunda vez en el tramo (`#87(c)` fue la primera).
**Una mutación que no separa el mecanismo que quieres clasificar no es una medición, aunque dé rojo.**

### Balance del tramo `·C` (`#86` → `#98`)

**Once ficheros auditados, contador 21 → 20.** Esa cifra es la esperada, no un fracaso: `#86` ya midió
que la mayoría muere con el componente y solo puede salir del inventario en el commit que lo borra.
Lo que el tramo produjo de verdad:

- **Tres huecos reales cerrados**, los tres del mismo tipo —campos que el servidor PUBLICA y cuyo
  único test conducía la UI—: las tres reglas `can_*` de los complementos (`#89`), pagar sin el correo
  verificado (`#93`) y el `type` del catálogo (`#96`).
- **Un falso positivo evitado** (`#92`): una mutación mal apuntada hacía creer que el límite anti-abuso
  de crear reservas no lo guardaba nadie. Lo guardan nueve casos.
- **Cuatro caras de la disciplina de mutación**, todas nacidas de errores propios: comprueba DÓNDE cayó
  (`#77`), comprueba el CONTENIDO y no el código de salida (`#90`), exige que el ancla sea ÚNICA
  (`#92`) y no muteS una rama de una propiedad de INDISTINGUIBILIDAD (`#95`).
- **Y el criterio de búsqueda para el futuro**: si un dato viaja al cliente y su único test conduce el
  motor viejo, el contrato no lo está fijando.

## #99 · 2026-08-16 · [DECIDIDO] El presupuesto de tokens define «el cajón» por FAMILIAS, no rascando una plantilla
Cierra el `[DECISION-PENDIENTE]` que dejó `#74`, y era **bloqueador de ·2b·3**:
`SidebarTokenBudgetTest` derivaba el ámbito CSS leyendo los `class="…"` de `purchase.blade.php`, o sea
de la plantilla que ·2b·3 borra. **Contador 20 → 19**, el primer movimiento del tramo `·C`.

**(a) La regla pasa a ser una propiedad del CSS, no de una vista.** Una clase es del cajón si pertenece
a una de sus **familias** —`sidecart`, `purchase`, `cart`, `cartbar`, `catalog`, `addons`, `cal__`,
`bk-`, `jj-`, `qtybox`, `wiz__`, `eventfields`, `entry__`—. Así el ámbito deja de depender del motor.

**(b) ⚠️ Y excluye a propósito lo que NO es del cajón**, que es lo que `#74` había medido: `auth__*`,
`form__*`, `pwd-*` y `check` son los formularios de login y alta **compartidos con el modal de la
cabecera y con `/mi-cuenta`**; `eyebrow`, `icon`, `tk`, `btn--` y `zone-` son del sitio. Tokenizarlos es
Fase 5. Si entraran aquí, el presupuesto del cajón subiría y bajaría por cambios que no son suyos.

**(c) Los números cambian y NO son comparables con los de antes.** El ámbito pasa de **1.073**
declaraciones (Blade) a **1.127** —y no a las 1.307 del escaneo ancho, porque la diferencia es
justamente lo compartido—. La tokenización queda en **71 %** (era 75 sobre el ámbito estrecho) y los
crudos en **6** (eran 3). **La guarda no se ha relajado: mide otra cosa, y bien.** Está escrito en las
dos constantes, porque el modo de fallo es previsible —alguien ve «75 → 71» y cree que hubo regresión—.

**(d) Los seis crudos son del cajón y quedan NOMBRADOS**, no escondidos en un número:
`.cal__day--normal`, `.cal__day--special .cal__day-price`, `.addons__badge--included`,
`.addons-mini__badge`, `.addons-mini__badge--included` y `.purchase__note--guestform`. Son de las 41
clases que solo viven en los `.vue` y que el escaneo del Blade nunca vio.
⚠️ **No se tokenizan aquí a propósito**: cambiar un color cambia PÍXELES y eso exige verificación
visual (DoD §4). Meterlo en un refactor de tests sería colar un cambio de interfaz sin mirarlo. Tarea
propia, con los seis ya localizados. Ficha en `DEUDA.md`.

**(e) Y el caso pasa a ser un TRINQUETE de verdad.** Se llamaba `..._only_shrink` y aseveraba con un
`<=`: tokenizar un color no obligaba a bajar el número, así que el siguiente crudo entraba gratis.
Ahora es `assertSame`, verificado en las dos direcciones —añadir un crudo y tokenizar uno sin bajar la
baseline dejan el caso rojo—. **Un nombre que promete un trinquete y una aserción que es un techo son
la misma clase de mentira que una baseline que no aprieta.**

## #100 · 2026-08-16 · [DECIDIDO] Borrar `Purchase.php` no es limpiar: es ACTIVAR el motor SPA
Al preparar `·2b·3` —con el inventario ya clasificado y `#74` resuelto, o sea sin nada que lo
frenara— apareció una consecuencia que **el tramo no menciona en ningún sitio** y que cambia su orden.

**(a) El borrado activa, no solo limpia.** `layout.blade.php` bifurca con
`SidebarSettings::usesSpa()`, y su propio comentario dice: *«El default y el fallback son Livewire: el
fallback no puede ser el motor en construcción»*. Borrar el componente elimina esa rama, así que
**el cajón SPA pasa a ser el único motor y sin vuelta atrás sin desplegar**.

**(b) Y `ESTADO.md` declara que ese motor NO está listo para eso**: «el flag NO está activado… lo que
falta para activarlo es **Turnstile y los tres caminos de navegador**». Los tres —la notificación S2S
de Redsys, el 3DS con challenge y el móvil real— están listados en `VERIFICACION-E2E-CAJON.md` §6 como
**lo que el guion NO cubre**, y `#76` los desbloqueó con el servidor de pruebas, pero **ninguno se ha
hecho**.

**(c) El tramo estaba escrito como si lo ordenara solo el contador.** `·2b·3` dice «reclasificar hasta
0 y borrar entonces», y `#62` retiró por vacía la condición de «curtirlo en producción». Las dos cosas
siguen siendo ciertas y **ninguna cubre esto**: el contador dice cuándo se PUEDE borrar sin romper la
suite; no dice nada sobre qué motor queda sirviendo después. **Son dos preguntas distintas y solo
estaba escrita una.**

**(d) La decisión (owner): verificar en staging ANTES de borrar.** El orden de `·2b·3` pasa a ser:
1. **levantar staging** con el procedimiento de despliegue —`[PENDIENTE DE MEDIR]` de `ENTORNOS.md`
   §4, que cierra a su vez el `[DECISION-PENDIENTE]` de `INSTALACION-CLIENTE.md` §1—;
2. **poner el flag en `spa`** allí y cerrar los tres caminos de navegador + Turnstile (4.4b·2);
3. **y entonces** borrar el componente, sus vistas, el puente y los tests que mueren con él.

**(e) Lo que NO cambia**: `#62` no se reabre —no hay tráfico que esperar, y esto no es «dejarlo
rodar»: es cerrar tres verificaciones concretas y nombradas—. Y el contador sigue ordenando la parte
de la suite: sin él clasificado, borrar rompería la red aunque staging estuviera verde.

**(f) La lección, que ya es la tercera del refactor**: una condición escrita en un sitio
(`ESTADO.md`: «falta Turnstile para activarlo») y un tramo escrito en otro (`00-REFACTOR.md`: «lo
ordena el contador») **pueden ser ambos correctos y aun así dejar un hueco entre ellos**. Lo que
faltaba no era información: era la frase que las une.

## #101 · 2026-08-16 · Turnstile deja de estar bloqueado, y lo que falta es MENOS de lo que parecía
El owner aporta las claves de Cloudflare, que era el único bloqueo declarado de **4.4b·2**. Medido el
estado real antes de planificar, el trabajo restante es más pequeño de lo que la ficha sugería.

**(a) Lo que YA está hecho, y es casi todo:**
- **El servidor entero**: `Platform\Services\Turnstile` tiene `siteKey()`, `secret()`, `enabled()` y
  `verify()` contra `challenges.cloudflare.com`, y `SelfSignup` ya lo exige.
- **El contrato**: `GET /config` publica `turnstile_site_key` **solo si el anti-bot está completo**
  —la equivalencia «no nulo ⟺ activo» costó un arreglo y está fijada por `PublicConfigTest`, incluido
  que **la secreta nunca viaja**—.
- **El cliente ya lo lee**: `register.js::signupRequiresCaptcha()` con sus casos en
  `register.test.js`.
- **⚠️ Y la CSP ya lo permite**: `SecurityHeaders` incluye `challenges.cloudflare.com` en `script-src`,
  `connect-src` y `frame-src`. No hay trabajo de cabeceras.

**(b) Lo único que falta es el widget en el cajón.** Hoy, con el anti-bot activo, `setAuthMode()`
**delega en el modal de auth de la cabecera** —que sí lo monta— en vez de pintar su formulario. Esa
degradación es deliberada y honesta: sin token, `SelfSignup` rechazaría **todas** las altas con «no
eres un robot», sin correo y sin log.
▶ 4.4b·2 = montar el widget en `RegisterForm.vue`, mandar el token con el alta y **retirar la
delegación**.

**(c) ⚠️ Y la delegación NO se rompe con `·2b·3`**, cosa que convenía comprobar antes de tocar nada: el
modal al que delega es el de la **cabecera**, que no vive en `purchase.blade.php` y por tanto
sobrevive al borrado. El modo `embedded` que `·2b·3` retira es otro.

**(d) Las claves NO entran en el repo.** `#76(e)` ya lo había medido: se leen **solo de `settings`**,
nunca de `.env`. En local quedan en la BD de desarrollo y `git status` no ve nada; en staging se
configuran por el panel de admin. Si alguna vez se filtran, se rotan en Cloudflare.

**(e) Encaja en el orden de `#100`**: Turnstile es uno de los cuatro puntos que hay que cerrar en
staging antes de borrar `Purchase.php`. Deja de estar bloqueado en un tercero y pasa a ser trabajo.

## #102 · 2026-08-16 · Staging APROVISIONADO, y cuatro cosas que la doc del panel no cuenta
Ejecutado el aprovisionamiento de `jumpweb.sites.aelium.app` —lo que `ENTORNOS.md` §4 tenía como
`[PENDIENTE DE MEDIR]`— y anotado lo que de verdad pasó, que es como esa doc exige escribirlo.

**(a) Hecho y verificado**: BD `jumpweb_1_test` con su usuario (`ALL PRIVILEGES`, **MariaDB 11.4.10**,
probada conectando desde el contenedor) · sitio en **PHP 8.3** · `documentRoot` → `public_html/public`
· `robots.txt` con `Disallow: /` sirviéndose por HTTPS con el certificado correcto.

**(b) ⚠️ Desde el sitio NO se puede aprovisionar, y está bien diseñado.** `appinit` es **PID 1**: cada
sitio es un contenedor. Su usuario Unix tiene `USAGE` y nada más —el propio `.my.cnf` de enhance avisa
de que **no es el usuario de BD del sitio**—, así que `CREATE DATABASE` responde `ERROR 1044`. Si el
usuario de un sitio pudiera crear bases o cambiar su PHP, podría hacerlo con los de otros clientes.
**Consecuencia**: automatizar el aprovisionamiento exige la **API del panel**, no la shell del sitio —
y eso fue lo que reordenó el plan.

**(c) ⚠️⚠️ El fallo que costó el sitio, y su regla.** Cambiar el `documentRoot` por API **cuando el
directorio destino todavía no existe** deja el vhost apuntando a la nada: el sitio cae al vhost por
defecto de LiteSpeed —sirviendo **su** certificado, que parece un fallo de TLS y no lo es— y enhance
**no avisa**. Aislado con un A/B: volver al docroot viejo lo revivió; repetir el cambio con el
directorio ya creado entró limpio, con el certificado correcto.
▶ **Regla para `provision.sh`: crear el directorio ANTES de mover el docroot.** No es «ten cuidado»:
es un paso con orden obligatorio que ninguna documentación del panel menciona.

**(d) ⚠️ La guarda 4 estaba en falso, y no por descuido.** `ENTORNOS.md` daba «`robots.txt` con
`Disallow: /` → cumplida de fábrica». Era cierto **solo mientras el docroot fuera `public_html`**, donde
había uno puesto a mano. El del repo dice `Disallow:` (vacío = **permitir todo**) porque la instalación
de un cliente **debe** indexarse. Al mover el docroot al `public/` de la app, la guarda se caía sola.
▶ **El `robots.txt` de staging es responsabilidad del DESPLIEGUE, no del producto**: `deploy.sh` tendrá
que reescribirlo en cada ejecución **y verificarlo por HTTP**, o el primer `rsync` lo tumba.

**(e) ⚠️ La API del panel no publica OpenAPI, y creí que sí.** `/swagger/v1/swagger.json` devuelve
`200`… con HTML: es una SPA y responde `200` a **cualquier** ruta. Un verde que no significa nada —el
mismo modo de fallo que esta sesión lleva documentando— y esta vez lo dio por bueno el agente hasta que
falló el parseo. Los endpoints se descubren y se verifican uno a uno.
**Verificados**: base `https://cp.hosturbo.net/api`, `Authorization: Bearer` + `orgId`;
`GET orgs/{org}` · `GET orgs/{org}/websites` · `GET …/websites/{ws}` ·
`GET|PATCH …/websites/{ws}/domains[/{id}]` → el `PATCH` de `documentRoot` responde `204`.

**(f) Lo que queda, y en qué orden.** El **despliegue** sigue sin medir: es lo que cierra
`INSTALACION-CLIENTE.md` §1 y lo que desbloquea la verificación de `#100` (Turnstile + los tres caminos
de navegador) y, tras ella, el borrado de `Purchase.php`. El `provision.sh` se escribe **después** del
`deploy.sh`, con este procedimiento delante: su trabajo es dejar el servidor en el estado exacto que el
despliegue espera, y ese estado solo se conoce habiéndolo alcanzado una vez.

## #103 · 2026-08-19 · [DECIDIDO] La auditoría doc↔código antes de desplegar: dos bloqueos y un paso que faltaba
Antes de escribir `deploy.sh` se auditó el corpus documental **contra el código**, midiendo cada
afirmación falsificable en vez de leerla: **794 comprobaciones** repartidas en siete familias, cada
hallazgo pasado por un verificador que intentaba REFUTARLO. Resultado: **52 desfases confirmados**
(1 de dinero, 18 importantes, 33 menores) y **11 descartados** por el refutador — el refutador es el
que hace que la cifra signifique algo.

**(a) ⚠️ El desfase más caro era de DINERO, y llevaba desde el origen.** `DEPOSITO.md` §5 documentaba
`refundableCapacityCents` = `max(0, Payment.amount − totalRefunded)`, y `totalRefunded` está definido
en el propio doc como «Σ `payment_refunds.succeeded`». **El código reserva ADEMÁS los refunds en
`pending`**, y su comentario dice por qué: *«dos refunds concurrentes no puedan devolver más de lo
cobrado (auditoría Fase 1, M5)»*. Es decir: la doc describía la guarda **más débil de lo que es**, en
la dirección en la que equivocarse cuesta dinero real. Un agente que «corrigiera» el código para que
coincidiera con la doc reabría el hueco M5. Corregido en la tabla, con el mecanismo y el porqué.

**(b) ⚠️ `REDSYS.md` §14 mandaba deshacer una invariante.** Decía que el rate-limit del endpoint de
notificación va «en el edge/WAF … **NO en la app**». Pero las tres rutas `/pago/redsys/*` llevan
`throttle:120,1` desde la auditoría Fase 1, y eso es **`PAY-15`, registrada como NO deshacer**. Y la
agravante: **`PAY-15` no tiene test dedicado** (así consta en `INVARIANTES.md`), así que nada habría
cazado la regresión. Dos docs del mismo repo diciendo lo contrario, y el que se lee al ir a producción
era el que estaba mal.

**(c) ⚠️ El enrutador enseñaba lo contrario de lo medido.** `CLAUDE.md` mandaba leer «§4.2–§4.3: **los
nombres de clase son CONTRATO**». §4.2 se titula literalmente *«El contrato visual es el ÁRBOL, no las
clases»* y abre con «medido: **90 de 292 selectores** no se satisfacen emitiendo la clase correcta»;
la revisión adversarial del propio spec ya había registrado «*el contrato son las clases* era falso» y
`SidebarClassContractTest` fue **sustituido por no ser falsable**. La lección aprendida estaba escrita
en el spec y **la fila del enrutador se quedó con la versión anterior** — que es la que todo el mundo
lee primero.

**(d) ⚠️⚠️ Borrar `Purchase.php` NO activa la SPA, y `#100` daba eso por hecho.** Medido con el código
delante: `usesSpa()` es `false` por DEFAULT **y** por FALLBACK, y el `<div class="sidecart__body">`
está FUERA del condicional. Hay dos caminos y los dos tienen trampa:
- **A · borrar solo la rama `@else`**: con el motor por defecto el cajón se abre **EN BLANCO**. La
  página no rompe, así que no hay error que mirar — y **la suite lo da VERDE**, porque
  `SidebarEngineTest` solo asevera `assertSee('sidecart__body')`, que se sigue emitiendo.
- **B · colapsar el condicional**: entonces sí queda la SPA sola, pero se pone **ROJO** el caso hermano
  de ese mismo fichero.
▶ **Lo que de verdad activa el motor es `4.7·3` — retirar el flag**, un paso que YA EXISTÍA en el
tracker y que la cadena del próximo paso **no contaba**. Son dos trabajos y estaban contados como uno.
`4.7·3` hereda entera la condición de `#100`.

**(e) ⚠️ Y la promesa «todo clasificado ⟹ borrar no rompe la suite» tiene un agujero de UN fichero.**
`SidebarEngineTest` **no está en las 19 entradas** de `DEPENDENTS`: el escáner no lo caza porque no
nombra `Purchase::` ni la vista (`COUPLINGS`). Sus dos casos existen para comparar los dos motores, así
que dejan de tener sujeto cuando solo queda uno. **Hay que decidir su destino ANTES de borrar**, no al
ver el rojo — o peor, al no verlo (camino A).

**(f) ⚠️❗ El despliegue no podía terminar: PHP 8.3 no instala este `composer.lock`.** `ENTORNOS.md` §4
afirmaba «PHP 8.3.29 … cumple `composer.json` (`^8.3`)». Cierto sobre `composer.json`, **falso sobre lo
que se instala**: el requisito efectivo lo fija el LOCK, y `composer why-not php 8.3.29` devuelve **17
paquetes de producción** (todo `symfony/*` 8.1) exigiendo `php >=8.4.1`. No hay `config.platform` que
lo amortigüe y `--ignore-platform-reqs` no vale (Symfony 8.1 usa sintaxis de 8.4). El sitio se
aprovisionó en 8.3 (`#102(a)`), así que `composer install --no-dev` **aborta**.
▶ **Decisión (owner): subir el sitio a PHP 8.5** por la API del panel, ANTES del primer despliegue —
lo cual además iguala staging a local (8.5.9), que es el principio ya escrito al final de `ENTORNOS.md`
§4. Se **descarta** fijar `config.platform.php=8.3` y re-resolver: degradaría el lock del PRODUCTO para
acomodar un servidor de pruebas. **Necesita un token nuevo del panel; el usado en `#102` lo retiró el owner.**
⚠️ **La lección es la misma de `#102(e)`**: se midió contra la restricción equivocada y el resultado
*parecía* verde. `composer.json` es la intención; el lock es lo que se instala.

**(g) ⚠️❗ Y no compraba lo que dice comprar: tras desplegar no hay quien entre a `/admin`.**
`ProductionSeeder` crea **0 usuarios** · `DatabaseSeeder` solo crea admin `if (! isProduction())` ·
`canAccessPanel()` exige rol `admin`/`staff`, que `make:filament-user` **no** da · y
`Turnstile::keys()` lee **solo** de `settings`. La cadena completa: **sin admin → sin panel → sin
claves → 4.4b·2 sigue bloqueado**. O sea: el «mecanismo del primer admin», que `ESTADO.md` clasificaba
como *pendiente del owner, no depende de nosotros*, es **camino crítico** desde el momento en que
staging existe para verificar Turnstile.
▶ **Decisión (owner): un comando artisan propio e idempotente** (`app:create-admin`) que cree el
usuario con su rol. Lo invoca `deploy.sh` y cierra el `[DECISION-PENDIENTE]` de
`INSTALACION-CLIENTE.md` §5 **con código y prueba**, no con prosa.

**(h) ⚠️ La guarda 4 recomendaba algo que rompe la guarda de dinero.** `ENTORNOS.md` §2 decía «noindex
y, **mejor, autenticación básica delante**». Pero `/pago/redsys/notificacion` **no lleva auth y no
puede llevarla**: es una S2S máquina-a-máquina. Un basic-auth sobre `/` devuelve **401 a Redsys** → el
pedido caduca **con la tarjeta ya cobrada** (`PAY-02`), que es exactamente el bloque B del e2e y una de
las cuatro razones por las que este servidor existe. **La autenticación básica global queda PROHIBIDA**;
la guarda 4 es el `robots.txt`, que es responsabilidad del DESPLIEGUE (`#102(d)`).

**(i) Lo demás, corregido en su doc**: `INVARIANTES` RGPD-03 y SEC-06 apuntaban a métodos que Fase 2/3
movieron (`AuthorizesGuestForm`, `PasswordLogin`/`PasswordRecovery`) · `MODELO-DATOS` contaba 6 modelos
con allowlist y son **7** (`User` la declara con el atributo `#[Fillable]`, que el `grep` en minúscula
del propio doc no ve) y daba por venir en Fase 3 la emisión de tokens (aplazada a Fase 6, `#29a`)
diciendo además que faltaba la revocación, **que ya existe** · `checkout-orquestado` llamaba `ready()`
a un constructor que se llama `allow()` · `ARQUITECTURA` daba por abierto el paso 7 de Fase 2, cerrado
desde el 2026-08-12 (`DEFERRED` es `[]`) · `COOKIES` documentaba `reopen()`/`save()`, que no existen ·
`POSTFORM` y `DEPOSITO` citaban tres símbolos FANTASMA nunca portados del origen
(`missingRequiredGuestFields`, `stepDepositHint`, y la creación del `Payment` en `Purchase.php`, que
vive en `PaymentInitiator::start()`) · `INSTALACION-CLIENTE` decía `schedule:list` = 4 tareas (son
**5**) y mandaba `npm run build` en un servidor sin node · `DEUDA` seguía en «27 ficheros … hasta 0».

**(j) La lección transversal, y es nueva.** Todos los desfases graves son del mismo tipo: **la doc y el
código dijeron lo mismo el día que se escribieron, y luego el código se movió** (Fase 2 modularizó,
Fase 3 extrajo servicios) **o se midió algo que la doc ya no reflejaba**. Ninguno se detecta leyendo la
doc: los siete salieron de **ejecutar la afirmación**. ▶ **`docs-check.sh` valida estructura y citas,
no CONTENIDO** — y eso es justo lo que se le escapó aquí. Candidato a deuda: llevar al gate las
afirmaciones que son mecánicamente comprobables (símbolos citados que deben existir, cifras derivables).

## #104 · 2026-08-19 · [DECIDIDO] El primer admin es un COMANDO, y con eso cae el último bloqueo del despliegue
`#103` midió que tras desplegar **no hay por dónde entrar al panel**, y que eso no es un detalle de
comodidad: es el único camino para meter las claves de Turnstile —`Turnstile::keys()` las lee **solo**
de `settings`—, o sea que sin admin el servidor de pruebas **no compra lo que dice comprar**. El
«mecanismo del primer admin» dejó de ser un pendiente del owner para ser camino crítico. Aquí se cierra.

**(a) Por qué un comando y no `make:filament-user`.** Medido: `User::canAccessPanel()` exige
`hasRole('admin') || hasRole('staff')`, y ese rol vive en la pivote `role_user`, que el comando de
Filament **no toca**. Crearía una cuenta que existe y **no entra** — el modo de fallo más caro de
todos, porque parece hecho. Por eso el caso central del test no asevera «existe el usuario» sino
`canAccessPanel()`.

**(b) La contraseña se GENERA y se imprime una vez.** `--password` existe, pero no es el camino: una
contraseña en la línea de órdenes queda en `ps`, en el historial del shell y en el log del despliegue.
Generar 24 caracteres y enseñarlos una sola vez no deja rastro en ningún sitio.
⚠️ **Y a la generada NO se le aplica `uncompromised()` a propósito**: una cadena aleatoria de 24
caracteres no está en ningún corpus de filtraciones, así que la comprobación no compraría nada — y sí
metería una llamada a Have I Been Pwned **en el camino feliz del despliegue**, que pasaría a depender
de que el servidor tenga salida a Internet y de que HIBP conteste (su verificador tiene 30 s de
timeout). A la explícita, que sí la elige un humano, se le exige **la misma política que al registro
real** (`min(8)->uncompromised()`): una cuenta con todos los permisos no puede tener menos exigencia
que un cliente.

**(c) La idempotencia es ASIMÉTRICA, y es la decisión de diseño que más importa.** Re-ejecutarlo
**repara el rol** si falta, pero **NO toca la contraseña**. La simetría ingenua —«idempotente = deja
el mismo estado»— aquí sería un fallo: rotar la contraseña en cada redespliegue **echa al owner de su
propio panel**, y lo descubriría el día que necesita entrar. Para rotarla hay que pedirlo
(`--reset-password`). Y el rol se ata con `syncWithoutDetaching`, no con `sync`: reparar el acceso no
puede desatar los roles que la cuenta ya tuviera.

**(d) Aborta si el rol no existe, en vez de crearlo.** Un `admin` sin fila en `roles` significa que los
seeders no han corrido, y entonces falta mucho más que el rol: fabricarlo dejaría la instalación en un
estado intermedio que nadie ha probado. Sale con código **1** —verificado ejecutándolo, no leyéndolo—
así que `deploy.sh` puede encadenarlo con `set -e`.

**(e) Medido mutando, que es como este repo comprueba que un verde significa algo** (`CONVENCIONES`
§3.quater). **Once mutaciones, las once muertas**, y cada una mató exactamente el caso que la vigila:
no atar el rol (2 rojos: admin y staff) · no marcar el email como verificado · `sync` en vez de
`syncWithoutDetaching` · rotar la contraseña siempre · match de email sensible a mayúsculas · aceptar
cualquier rol · crear el rol en vez de abortar · quitar la política de contraseña · no validar el email
· **imprimir una contraseña distinta de la que se guarda** · **imprimir contraseña al solo reparar**.
**Ningún caso quedó inerte**, que es el fallo que `#65` documentó y que solo se ve mutando.

**(e.bis) Y la garantía que más pesa la destapó el propio método**: la contraseña se enseña **una
sola vez** y no queda guardada en ningún sitio, así que si lo impreso NO fuera lo que abre, el owner se
quedaría fuera **sin recuperación posible** salvo volver a ejecutar el comando. El primer test decía
solo «está hasheada», que es mucho más débil y **no habría cazado ese fallo**. Se reforzó capturando la
salida REAL y autenticando con ella; verificado además a mano contra MySQL (`Auth::attempt` con la
impresa → `true`; con otra → `false`), y fijado con dos mutantes: imprimir una contraseña distinta de
la guardada, e imprimir contraseña al solo reparar (que sería MENTIR: la suya no ha cambiado).

**(f) El match de email es case-insensitive, y tiene su caso propio.** No es cosmética: `deploy.sh`
puede recibir el email escrito de cualquier forma, y con un match sensible a mayúsculas el segundo
despliegue **crearía una cuenta duplicada** en vez de reconocer la suya. Se mutó para comprobarlo.

**(g) Y el email queda VERIFICADO al crear.** Un admin recién creado tiene que poder operar sin pasar
por el correo — que además en staging **no sale** (guarda 3 de `ENTORNOS.md` §2, `MAIL_MAILER=log`).
Nota de implementación que costará descubrir dos veces: `email_verified_at` **no está en el
`#[Fillable]` de `User`**, así que no se puede asignar en masa; hay que ponerla como propiedad.

**(h) Lo que esto desbloquea.** Con `#103(f)` (PHP 8.5, ya subido y verificado por SSH) y esto, **el
despliegue deja de tener bloqueos**: `scripts/deploy.sh` es el siguiente trabajo, y ya con el pliego
medido delante. Cierra además el `[DECISION-PENDIENTE]` de `INSTALACION-CLIENTE.md` §5 **con código y
prueba**, no con prosa — que era la forma en que llevaba abierto desde Fase 0.

## #105 · 2026-08-19 · [DECIDIDO] El despliegue es un script, y su valor es lo que NO deja hacer
Escrito `scripts/deploy.sh`, que cierra el `[PENDIENTE]` de `ENTORNOS.md` §4 y el
`[DECISION-PENDIENTE]` de `INSTALACION-CLIENTE.md` §1 —abierto desde Fase 0—. No es un port del
`deploy-prod.sh` del origen: se escribió **midiendo la máquina**, que es como esa doc exige hacerlo.

**(a) DRY-RUN por defecto.** Sin `--go` no toca el servidor: comprueba todo, enseña el plan de `rsync`
y sale. Misma convención que `app:purge-customers`, y por la misma razón — en un canal de despliegue,
el modo destructivo se pide, no se hereda de un tecleo.

**(b) Nunca sube el `.env`: lo LEE y lo VALIDA.** Ningún secreto vive en el repo (`ENTORNOS.md` §1), y
subir el local tumbaría cuatro guardas de golpe. Las seis se comprueban una a una y el error dice la
CONSECUENCIA, no solo el valor: «MAIL_MAILER='smtp' — EL CORREO SALDRÍA. Los seeds llevan direcciones
con pinta de reales». Verificado con un `.env` sonda deliberadamente mal: **cazó las seis**.
▶ Y `--env-template` imprime el `.env` de staging con las guardas ya puestas, para no escribirlo a ojo.

**(c) La guarda 1 no se puede comprobar donde uno esperaría.** `redsys_environment` es un `Setting` de
BD, no una variable de entorno, así que **antes de migrar no se puede leer**. Se comprueba justo
después de `migrate` y **antes de `up`**: si estuviera en `live`, el sitio se queda en mantenimiento en
vez de levantarse cobrando con tarjetas de verdad.

**(d) El orden no es preferencia, es corrección.** `down` antes de nada —`public/index.php` mira
`maintenance.php` ANTES del autoloader, así que la 503 sobrevive a un `vendor/` roto a mitad de
`composer install`—; drenar la cola antes del `rsync`; migrar antes de servir (con `APP_ENV=production`,
`tableExists()` no comprueba: servir sin migrar da **500 duro**, no degradación); y `composer install`
antes de `optimize`, porque dispara `filament:upgrade` → `config:clear`/`route:clear`/`view:clear` y se
llevaría por delante cualquier caché horneada antes. Los cuatro órdenes tienen su caso en el test.

**(e) Las exclusiones del `rsync`, verificadas ejecutándolas** contra un destino local antes de apuntar
al servidor: **0 entradas** de `.env`, `vendor`, `node_modules`, `tests`, `docs`, `storage`, `openapi`,
`.git` y `public/uploads`; y sí viajan `public/build` (manifest + 7 assets), migraciones, `lang`,
vistas, `composer.lock` y `artisan`. **1086 entradas**, y el dry-run contra staging dio el mismo número.
⚠️ `storage/` nunca viaja, y no solo por los logs: contiene `framework/testing/disks/*` —un árbol por
worker de la suite— y `storage/ssr`, que es artefacto de TEST.

**(f) ⚠️ Dos fallos REALES que solo aparecieron al ejecutarlo, y que ninguna revisión de lectura habría
visto:**
1. **SIGPIPE.** El dry-run encadenaba `rsync … | head -40`; `head` cierra el pipe, `rsync` muere con
   SIGPIPE y, con `set -o pipefail`, **el script entero abortaba con código 141**. Arreglado volcando a
   fichero y recortando después (que además ejecuta el `rsync` una vez en lugar de dos).
2. **El `APP_KEY` era huevo y gallina.** La plantilla decía «genera con `php artisan key:generate`»,
   pero en arranque en frío **no hay `vendor/`**. Se cambia por `openssl rand -base64 32`, verificado
   presente en el servidor.

**(g) ⚠️⚠️ Y la lección más transferible salió de mutar el TEST del script.** `DeployScriptGateTest`
nació con 23 casos en verde… y al mutar `deploy.sh` salieron **TRES INERTES**: quitar la reposición del
`robots.txt`, quitar el drenaje de cola y romper el orden de `composer install` **no ponían nada rojo**.
La causa era la misma en los tres: las agujas se encontraban **en los COMENTARIOS que explican por qué
cada paso está ahí**, y en los mensajes de error. Es decir: **documentar bien el script hacía que su
propio test dejara de morder** — el texto sobrevivía al código.
▶ **Regla, y vale para cualquier test sobre un fichero de texto: asevera sobre lo EJECUTABLE (fuera las
líneas de comentario) y ancla las agujas al SITIO DE LLAMADA, no a un substring.** Tras endurecerlo,
las **10 mutaciones mueren**. Es la cuarta cara de la disciplina de mutación en este repo (`#65`, `#77`,
`#92`, y ahora esta), y la primera en la que el inerte lo causaba la propia documentación.

**(h) Y el test que impide repetir `#103(f)`**: un caso **deriva del `composer.lock`** el mayor
`php >=` que exige cualquier paquete de producción (hoy 8.4.1, por `symfony/clock`) y lo compara con el
`PHP_MIN` del script. Aquel desfase vivió en la doc sin que nada lo cazara; ahora, el día que un
`composer update` suba el suelo, el rojo llega en la suite y no en mitad de un despliegue.

**(i) Lo que NO hace, a propósito**: no pone basic-auth (`#103(h)`: rompería la S2S de Redsys y con ella
`PAY-02`), no corre `storage:link` (`INSTALACION-CLIENTE.md` §1 lo prohíbe y el código lo confirma: 0
usos del disco `public`), y no siembra ni crea admin salvo que se le pida con `--seed` / `--admin-email`
— `ProductionSeeder` borra y recrea el catálogo entero, y eso no puede ser el default de nada.

**(j) Estado: escrito y probado, NO ejecutado.** El despliegue real está esperando dos datos que solo
tiene el owner: las **credenciales del usuario de BD** de `jumpweb_1_test` (el `.my.cnf` del servidor es
el usuario ADMINISTRATIVO y él mismo avisa de que no debe usarse para la web — coincide con `#102(b)`)
y el **email del admin** del panel. El dry-run contra staging ya pasa entero.

## #106 · 2026-08-19 · STAGING DESPLEGADO — y la guarda del DINERO daba un VERDE FALSO
Ejecutado el primer despliegue real con `scripts/deploy.sh`. El sitio quedó sirviendo, pero el propio
script traía **dos comprobaciones rotas**, y una era la única que cuesta dinero. Lo que sigue importa
más que el despliegue.

**(a) ⚠️⚠️ La guarda 1 decía ✓ sin haber leído nada.** Concurrieron TRES causas independientes, y hacen
falta las tres para entender por qué no saltó ninguna alarma:
1. leía el ajuste con `Setting::value(...)`, cuyo **FQCN lleva backslashes que NO sobreviven** a la capa
   local → ssh → shell remoto: llegaba mutilado y `tinker` devolvía un **PARSE ERROR**;
2. el `tr -dc 'a-z'` **convirtió ese error en una cadena de basura** con pinta de valor
   (`arseerroryntaxerrorunexpected…`), que es lo que el script imprimió como si fuera el entorno; y
3. la condición preguntaba **«¿CONTIENE `live`?»** — y la basura no lo contiene.
▶ **Con el entorno en `live`, la guarda habría pasado igual.** Comprobado por SQL directo que la
realidad era `test`, así que no hubo consecuencia; pero la guarda no lo sabía, y eso es el defecto.

**(b) La regla que sale de aquí, y vale para TODA guarda de dinero: pregunta «¿es lo que ESPERO?»,
nunca «¿es lo que TEMO?».** Lo primero falla cerrado ante un error; lo segundo lo bendice. La guarda
ahora exige `test` **exacto** (o vacío = el default del código) y aborta ante cualquier otra cosa,
incluido un error de lectura. Y se lee por `DB::table('settings')`, que **no necesita namespaces** y
por tanto no se puede mutilar.

**(c) El corolario, que este repo ya conocía y volvió a morder**: `#102(e)` documentó «un 200 con HTML
es un verde que no significa nada». Aquí la forma fue otra y la lección la misma: **un `tr`/`grep` que
SANEA la salida de un comando puede convertir un ERROR en un valor plausible**. Si una comprobación
limpia lo que recibe, tiene que validar la FORMA de lo que queda, no solo mirarlo.

**(d) La menor, que dio un rojo falso**: `grep -c X || echo 0` imprime **dos** ceros cuando no hay
coincidencias —`grep -c` ya emite «0» y ADEMÁS sale con 1—, así que la comprobación de migraciones
comparaba `"0\n0"` contra `"0"` y fallaba **con el sitio perfectamente sano**. `|| true` conserva el 0
y traga el código. Un falso rojo cuesta menos que un falso verde, pero enseña lo mismo: el comando que
verifica también hay que verificarlo.

**(e) Los cuatro arreglos, medidos MUTANDO**: restaurar cada bug pone en rojo exactamente su caso
(`DeployScriptGateTest`, 26 casos). El de la guarda de dinero es el que más valía fijar, porque su modo
de fallo es **silencioso y solo se manifiesta el día que el entorno está mal**.

**(f) Lo desplegado y verificado POR FUERA del script** (`#59`: verde no es funciona). Las **12 páginas
públicas en 200** (home, precios, cumpleaños, servicios, normas, contacto, entradas y las 5 legales) ·
**los tres idiomas en vivo**, comprobando el `<html lang>` tras `/lang/{locale}` — y de paso queda
medido que **el idioma va por SESIÓN, no por prefijo de URL**, así que `/en` y `/fr` dan 404 y eso es
correcto (`INSTALACION-CLIENTE.md` §7 se leía como si hubiera prefijo) · `/admin` → 302 y
`/admin/login` → 200 · `/api/v1/config` → 200 · `robots.txt` con `Disallow: /` · 5 tareas del
scheduler · ninguna migración pendiente · `failed_jobs` vacía.

**(g) El script es idempotente, y se probó ejecutándolo dos veces**: la segunda dijo «Nothing to
migrate», no duplicó el cron (marcador) y dejó el sitio arriba igual.

**(h) Estado del contenido**: `ProductionSeeder` sembró la semilla neutra SaltoPark y
`slots:generate-rolling` materializó **1440 franjas**. Admin creado (`app:create-admin`, id 1) con su
contraseña impresa una sola vez.

**(i) Lo que ESTO desbloquea, y es el motivo de todo el tramo**: `/api/v1/config` publica hoy
`turnstile_site_key: null` —o sea, **el anti-bot está inactivo por falta de claves**—. Ese es
exactamente el siguiente trabajo: cargar las claves de Cloudflare en el panel (ya hay admin para
entrar) y cerrar **4.4b·2**, y con él los tres caminos de navegador que `#100` exige antes de borrar
`Purchase.php`.

## #107 · 2026-08-19 · Turnstile CONFIGURADO en staging — y no se configura donde la doc decía
Cargadas las claves de Cloudflare en staging. La pregunta del owner («¿dónde la añado?») destapó que
la respuesta escrita era falsa.

**(a) ⚠️ `ENTORNOS.md` §3 decía «van por el panel de admin». El panel NO las puede editar.** Medido:
`security.turnstile_*` no aparece en ningún formulario de `app/Filament/`, y el docblock de la página
de ajustes dice justo lo contrario — «**Fuera de alcance por seguridad (NUNCA editables aquí): los
SECRETOS (`redsys_secret_key`, `security.turnstile_secret`)**». O sea: la exclusión es **deliberada y
correcta**, y lo que estaba mal era la doc que mandaba buscarlas ahí. Quien entrara al panel no las
encontraría y no sabría por qué.

**(b) El mecanismo real, escrito donde se busca**: la fila de `settings` (`group` = `security`), por
`tinker` o SQL en el servidor. No hace falta limpiar caché: `Setting` memoiza **solo por proceso**
(`flushMemo` en `saved`/`deleted`), no de forma persistente — comprobado leyendo el modelo antes de
escribir, no después de que fallara.

**(c) Las claves ya estaban… en la BD de desarrollo LOCAL.** El owner las aportó el 2026-08-16 y
`#101(d)` había predicho exactamente dónde acabarían: «en local quedan en la BD de desarrollo y
`git status` no ve nada». Se transfirieron de local a staging **sin que el secreto pasara por la
salida** de ninguna herramienta.

**(d) Verificado de tres formas, y la segunda es la que de verdad cierra la duda:**
1. `/api/v1/config` publica `turnstile_site_key` y **NO publica la secreta** — la invariante de
   `PublicConfigTest`, confirmada en vivo y no solo en la suite.
2. ⚠️ **`siteverify` de Cloudflare devuelve `invalid-input-response`, NO `invalid-input-secret`**. Esa
   distinción es el hallazgo: significa que **Cloudflare reconoce el secreto como válido** y solo
   rechazó el token de prueba que se le mandó a propósito. Sin esto, «la clave está puesta» sería una
   suposición: una clave equivocada se ve exactamente igual desde el lado de la app.
3. De paso queda probado que **el servidor tiene salida a `challenges.cloudflare.com`**, que es lo que
   `Turnstile::verify()` necesita y nadie había comprobado.

**(e) Lo que NO se ha verificado, y hay que decirlo**: el **hostname**. Cloudflare ata las claves a un
dominio y lo valida al canjear un token REAL, cosa que no se puede hacer sin navegador. `Turnstile.php`
registra ese modo de fallo (`Log::warning('turnstile.verify_failed', … 'hostname')`), señal de que ya
mordió una vez. Queda para la sesión de navegador de `VERIFICACION-E2E-CAJON.md`.

**(f) Y una observación de mecanismo que no es defecto**: el HTML inicial **no** trae el `<script>` de
Cloudflare ni el contenedor `cf-turnstile`. No es un fallo: el modal de auth es Livewire y el widget se
monta **al abrirlo**, cargando el script bajo demanda (`window.__cfTurnstileLoading`). La CSP ya lo
permite en `script-src`, `frame-src` y `connect-src`, verificado en la CABECERA REAL del servidor.

**(g) Deuda que esto deja anotada**: configurar un secreto de BD es hoy **un paso a mano, no probado**,
y **cada instalación de cliente lo necesita** — sin claves el anti-bot se autodesactiva en silencio.
Merece un comando hermano de `app:create-admin`. Ficha en `DEUDA.md`.

## #108 · 2026-08-20 · 4.4b·2 — el widget del anti-bot en el cajón, y dos centinelas que no mordían
Montado el widget de Cloudflare Turnstile en el cajón SPA y **retirada la delegación** en el modal de
auth de la cabecera. Con esto 4.4b·2 queda cerrado y Turnstile deja de atar nada.

**(a) La lógica va a un módulo PLANO, `resources/js/sidebar/turnstile.js`** (`CE-6`), no al `.vue`. Y
la ubicación es una decisión, no una carpeta cualquiera: `js/sidebar/*.js` es el **único glob de nivel
1** que vigila la rancidez del bundle SSR, así que un módulo en `js/sidebar/antibot/` habría escapado a
esa guarda y el diff de árbol habría comparado código viejo **dando verde con el widget roto**.

**(b) ⚠️ El contenedor va con `v-if` y PELADO, y las dos cosas son contrato de árbol.** El Blade lo
envuelve en `@if ($turnstileEnabled)` y **la suite nunca siembra las claves**, así que con el anti-bot
apagado el motor Livewire no emite nada ahí. Un `<div>` incondicional pone en rojo el diff de árbol Y
el manifiesto congelado a la vez; y con clase también, porque el normalizador SÍ imprime las clases.
Además `class="cf-turnstile"` no serviría: el auto-render de Cloudflare solo ve los `.cf-turnstile` de
la carga inicial, no los inyectados — por eso los dos motores renderizan EXPLÍCITAMENTE sobre el nodo.

**(c) Tres cosas que NO son copia-pega del motor Livewire, y por qué:**
1. **El guard `__cfTurnstileLoading` se comparte pero NO sirve para cortar.** El modal de la cabecera
   se renderiza *eager* en toda página pública, así que cuando el cajón abre el flag **ya vale `true`**.
   Un `if (flag) return;` habría dejado el widget del cajón sin pintar SIEMPRE. El único predicado
   válido es `win.turnstile && win.turnstile.render`, con sondeo.
2. **Se guarda el `widgetId`**, que la referencia Alpine descarta. Sin él no hay `reset()`, y hace
   falta de verdad: el token es de un solo uso y `SelfSignup` lo quema **antes** de mirar si el correo
   ya existe. Sin reset, quien se equivoca de correo recibe «ya tienes cuenta», corrige, reenvía con el
   mismo token y Cloudflare lo rechaza por duplicado → «no eres un robot» **con el tick verde puesto**
   y sin salida que no sea recargar. Por eso el vaciado del token va en **cualquier** rama de fallo, no
   solo en la del captcha: los tres desenlaces llegan como `validation_failed` bajo `fields.email` y el
   cliente **no puede distinguirlos**.
3. **No se rinde en silencio.** Agotado el sondeo, avisa por consola y fuerza el token vacío.

**(d) ⚠️⚠️ Y el porqué de (c.3) es el hallazgo que más vale de este paso: el modo de fallo más probable
del anti-bot es INVISIBLE.** `Turnstile::verify('')` corta **antes** del POST a Cloudflare y **antes**
de su `Log::warning`, así que un widget que no llegue a pintarse produce un 422 con **cero líneas en
`storage/logs` y cero en el panel de Cloudflare**. No hay dónde mirar. La consola del navegador es el
único sitio donde ese fallo deja huella, y por eso el aviso no es cosmético.

**(e) Medido MUTANDO, y salieron DOS cosas inertes — las dos mías.**
- **Un test inerte**: la guarda `widgetId !== null` de `render()` es hoy **inalcanzable** (el sondeo se
  para antes de pintar), así que quitarla no ponía nada en rojo. El caso se re-apuntó al mecanismo
  REAL y se dejó escrito que quien añada un tercer sitio de llamada tendrá que traer su propio caso.
- **Un CENTINELA inerte**, y este costó una reconstrucción descubrirlo: `turnstile_token` parecía el
  candidato natural para vigilar «el token viaja al servidor», pero al quitarlo del payload las
  ocurrencias en el chunk bajan de 6 a **4**, no a 0 —el mismo identificador vive en `emptyForm`, en el
  `v-model` del paso y en el vaciado tras un fallo—, así que el caso seguía VERDE con el token sin
  mandarse. **Es exactamente la trampa `/payment` vs `/payment-status` que ese fichero documenta, y
  caí en ella igual.** Se retiró: queda un solo centinela, `challenges.cloudflare.com`, verificado
  mutando (con el widget fuera baja a 0 y el caso se pone rojo). El cableado del payload lo cubre
  `register.test.js`, que sí muere al quitar la línea.
▶ **La lección: un centinela que busca un identificador COMPARTIDO no vigila nada.** Antes de añadir
uno hay que medir que con la función fuera baje a CERO, y eso exige reconstruir, no razonar.

**(f) El presupuesto de bundle, y un ledger que llevaba caduco.** El docblock decía «margen 4,15 KiB»
desde 4.6·2; medido antes de tocar nada, el chunk ya estaba en 146,48 KiB, o sea **3,52** de margen —
creció durante 4.7·2b·2·B/C sin que nadie anotara la cifra. El widget costó **1,76 KiB** (148,24 KiB)
y **quedan 1,76**. ⚠️ El siguiente que añada algo al cajón tiene el margen muy corto; la decisión
escrita del proyecto sigue siendo **subir el techo con motivo**, no adelgazar a ciegas.

**(g) `Sidebar.vue` ENCOGIÓ, que es la dirección buena**: 618 → **614** líneas de código (la delegación
quitó cuatro, el vaciado del token añadió una). Baseline actualizada en el mismo commit, como exige
`SidebarComponentBudgetTest`. Y `RegisterForm.vue` se pasó del techo en el primer intento (43 sobre 40):
se resolvió **compactando el painter y dejando la decisión en el módulo** —`mountTurnstile` ya es
inerte sin clave— en vez de subir el techo.

**(h) La rama que no cubría nadie, ahora cubierta**: con el anti-bot ACTIVO, un token vacío se rechaza
y **no crea a nadie**; y con token válido el alta pasa. Este segundo exige `Http::fake` de siteverify:
`TestCase` tiene `preventStrayRequests()` y con token no vacío `verify()` **sí sale a la red**.

**(i) Lo que sigue sin verificar y solo lo puede ver un navegador**: que Cloudflare invalide el token
tras el primer canje (el reset está probado con dobles, no contra el proveedor), que el widget se pinte
dentro del cajón con claves reales, y el comportamiento de la caducidad. Anotado en
`VERIFICACION-E2E-CAJON.md` como lo que hay que mirar en la sesión de staging.

## #109 · 2026-08-20 · [DECIDIDO] `app:set-setting` — la puerta CLI a lo que el panel no expone
Al ir a poner el flag en `spa` en staging, el mismo hueco mordió por tercera vez: **hay ajustes que
el panel no expone y solo se podían tocar a mano**. Se cierra con un comando, hermano de
`app:create-admin`.

**(a) Los tres casos que lo hacían falta, y ninguno es hipotético:**
· `security.turnstile_site_key` / `security.turnstile_secret` — el anti-bot se lee SOLO de `settings` y
  la página del panel los excluye **a propósito** («NUNCA editables aquí»). Sin ellos el anti-bot se
  autodesactiva **en silencio** (`#107`). Ficha abierta en `DEUDA.md`, que esto cierra.
· `sidebar.engine` — el flag del motor del cajón. Tampoco está en el panel, y la única receta escrita
  usaba `docker compose exec`: en un servidor real no existe. Era el último bloqueo para la sesión de
  verificación en staging.

**(b) Es una puerta trasera al panel, así que trae guardas — y la lista NO es genérica.** Tres claves
exigen `--force` escrito a mano, cada una por un daño medido: `redsys_environment` (en `live` la
instalación **cobra de verdad**: el único ajuste de esa tabla que cuesta dinero) · `redsys_secret_key`
(vive en el vault/`.env`; escribirla en BD la mete en todos los backups) · `redsys_next_gateway_order`
(contador operativo: retrocederlo colisiona pedidos en la pasarela, SIS0051/0913). Es la misma lista
que el panel se niega a editar, traída a la CLI.

**(c) Nunca imprime un secreto, y no es paranoia: este comando se ejecuta por SSH desde `deploy.sh`,
así que su salida acaba en el log del despliegue.** Enmascara por el NOMBRE de la clave
(`secret`/`password`/`token`), no por el valor. Y tiene su espejo: los valores que **no** son secretos
sí se imprimen, porque enmascararlo todo dejaría el comando inútil para verificar a ojo.

**(d) Dos detalles que solo se ven habiendo leído el modelo:**
· **El grupo de una fila existente NO se pisa.** `--group` solo aplica al CREARLA; si no, actualizar
  el valor de un ajuste desde la CLI lo movería de grupo y desaparecería de su pestaña del panel.
· **Vaciar y borrar no son lo mismo.** `Turnstile::enabled()` distingue «clave vacía» de «sin fila»
  solo por el valor; el comando escribe la cadena vacía y conserva la fila (y su grupo).

**(e) Relee de la BD antes de dar el verde.** No confía en lo que acaba de escribir: si algo lo pisara
—otro proceso, un memo— el comando lo diría en vez de dar un verde que no significa nada. Es la misma
disciplina que `#106` obligó a aprender en la guarda del despliegue.

**(f) Medido mutando: cinco mutaciones, las cinco muertas** —quitar la guarda de claves protegidas
(3 rojos), pisar el grupo, imprimir el secreto en claro, aceptar una clave vacía y no detectar el
sin-cambios—. 13 casos.

## #110 · 2026-08-20 · Los CUATRO caminos de `#100`, VERIFICADOS — y `4.7·2b·3` queda desbloqueado
El owner recorrió en staging el guion de `VERIFICACION-E2E-CAJON.md` §5.ter con el motor en `spa`.
Con esto se cierra la condición que `#100` puso para borrar `Purchase.php`, y que llevaba abierta
desde el 2026-08-16.

**(a) Lo verificado, los cuatro:**
1. **Turnstile DENTRO del cajón SPA** — el widget se pinta en el paso de alta del propio cajón, en
   mitad del flujo de compra, y **ya no delega** en el modal de la cabecera. Era la incógnita real de
   4.4b·2: el widget se había escrito y probado con dobles, y contra Cloudflare no lo había visto nadie.
2. **El RESET del widget** (T2), que es el que ningún test del repo puede ver: el token es de un solo
   uso y `SelfSignup` lo quema **antes** de comprobar si el correo ya existe, así que sin reset un
   segundo intento daría «no eres un robot» con el tick verde puesto. Probado con dobles; ahora,
   contra el proveedor.
3. **El pago completo con la notificación S2S** (T3) — el bloque B, que hasta hoy exigía un túnel.
4. **3DS con challenge** (T4) y **móvil real** (T5), que **nadie había recorrido nunca** en este proyecto.

**(b) Corroborado en la BD de staging, no solo en el relato:** 3 pedidos, **2 en `paid`**
(`R-0PT9LJ`, `R-C7AHJS`), 16 entradas y 2 usuarios. Y un detalle que confirma otra cosa de paso: los
pagos son de **30,00 €** sobre totales de 151,20 y 127,20, o sea que **la señal (depósito parcial)
funcionó de punta a punta** — `PAY-10` ejercitado sin buscarlo.

**(c) ⚠️ El LÍMITE de esa corroboración, y hay que escribirlo.** Que un pedido esté en `paid` **NO
distingue** si lo cerró la notificación S2S o el retorno del navegador: `RedsysReturnHandler` es
idempotente y atiende los dos caminos. Se intentó aislar y **no se pudo**: el usuario Unix del sitio no
tiene acceso a ningún access log del vhost, y en `storage/logs` la única línea de Redsys es un
`redsys.return.malformed` que dejó la propia comprobación de la ruta. Así que **T3 se apoya en que el
owner siguió la receta del bloque B**, no en una medición independiente.
▶ Si algún día hace falta certeza sobre el S2S, la vía es un terminal *data-less* de verdad —donde el
retorno **no** lleva datos y solo la notificación puede cerrar el pedido— o acceso al access log.

**(d) Lo que esto desbloquea.** `4.7·2b·3` —el borrado de `Purchase.php`— deja de estar bloqueado por
`#100`. Siguen abiertas sus DOS decisiones propias, ya medidas y ninguna sorpresa: el destino de
`SidebarEngineTest` (que **no está en el inventario** y compara los dos motores, `#103(e)`) y el del
modo `embedded` de `auth.login`/`auth.register`, que el propio tramo declara «parte de este tramo».

**(e) Y una cosa que NO cambia**: el modal de auth de la cabecera **sobrevive** al borrado. Vive en
`layout.blade.php`, no en `purchase.blade.php`, y sigue siendo la puerta de auth de la web fuera del
cajón. Retirarlo es trabajo del área de cliente (`#66`), no de 4.7.

## #111 · 2026-08-21 · [DECIDIDO] Independizar el contrato de árbol ANTES de borrar, y por qué el orden inverso no funcionaba
El borrado de `Purchase.php` (`4.7·2b·3`) se intentó de frente, se PARÓ a mitad con una medición, y se
rehízo con el orden invertido. Lo que sigue es esa lección, que es lo transferible.

**(a) ⚠️ El primer intento se paró por una señal de aborto puesta a propósito, y funcionó.** El pliego
predecía «exactamente 2 rojos» al colapsar la bifurcación; salieron **4**. Se paró a medir en vez de
seguir: **no había mecanismo nuevo** —eran los dos previstos, y el pliego había contado los casos de
menos (`SidebarEntry::consume()` corriendo ahora en cada render del layout afecta a TRES casos, no a
uno)—. Se siguió. La señal de aborto vale precisamente porque a veces se levanta sin drama.

**(b) ⚠️⚠️ Lo que sí paró el intento: `Purchase` no era «el otro motor», era la FÁBRICA DE FIXTURES.**
El pliego decía que re-apuntar `SidebarDomContractTest` era «retirar los renderizadores del motor viejo
y la firma de `$livewire`». Medido: quedaban **65 usos de `$component->`** que no eran comparación —
`cartApiPayload($component)`, `confirmedApiPayload($component)`, `$component->get('qty'|'date'|'step')`,
`viewData(...)`—. Reconstruirlos con un solo motor es hacerlo **sin nada contra lo que validarlos**, en
el fichero que protege 90 de los 292 selectores estructurales del cajón.

**(c) El problema de fondo no era el acoplamiento: era la CIRCULARIDAD.** El payload que se le daba a
Vue salía del motor Livewire que ese mismo fichero comparaba. Con los dos motores vivos el
`assertSame($livewire, $vue)` lo tapaba —los dos lados nacían del mismo sitio—; al quedar uno, la
circularidad se vuelve invisible y el manifiesto congelaría lo que Vue emitiera ese día.

▶ **De ahí el orden, que es contraintuitivo y es la decisión de esta entrada: independizar PRIMERO,
con los dos motores vivos —la única ventana en la que se puede comprobar que el fixture declarado
produce el mismo árbol que el derivado— y borrar DESPUÉS.** Al revés se reconstruyen 65 fixtures a
ciegas. Es el mismo error de método que `#106`: preguntar «¿pasa el test?» en vez de «¿sigue midiendo
lo mismo?».

**(d) Hecho en cuatro entregas verdes sobre `main`**, cada una medida instrumentando el helper para
volcar lo que el motor producía ANTES de sustituirlo, y cada una mutada. La validación cazó **cuatro
errores propios al vuelo** (dos casos de cesta vacía que recibieron la llena, y dos fixtures mal
declarados), que es exactamente para lo que se hizo así.

**(e) Y una regla que salió de mutar, y que no se puede deducir:** el diff de árbol es sensible a la
**FORMA** (una línea de cesta, un complemento, `event_data`: 2, 1 y 6 rojos) pero **no a los valores**
(cambiar `qty` de 6 a 7: verde). ⚠️ **Con excepciones que solo aparecen mutando una a una**: el día del
paso de FECHA sí muerde —`aria-current="date"` marca la celda del calendario— y los topes del selector
también —`disabled` es atributo de contrato—. **Dos fechas en el mismo fichero, una es contrato y la
otra no.** Deducirlo de una regla general habría dejado fixtures inertes.

**(f) Cuatro nombres de campo que se habían SUPUESTO mal**, y los cuatro se cazaron midiendo antes de
escribir: la señal en el presupuesto es `online_amount_cents < total_cents` (no existe `deposit_cents`)
· el post-form es `guest_form_pending` (no `has_guest_form`) · lo pendiente en el parque es
`pending_at_gate_cents` · la pausa es `reservations_paused` (no `paused`).

**(g) ⚠️ Restricción que manda sobre el resto del borrado: el NOMBRE de cada caso es la CLAVE del
manifiesto congelado.** Los `…_in_both_engines` **no se pueden renombrar** sin regenerarlo, y
regenerarlo sin segundo motor congelaría como contrato lo que Vue emitiera ese día. Se quedan con su
nombre histórico, y aquí queda escrito por qué no es descuido.

**(h) Un BUG VIVO destapado y arreglado.** El adaptador de intención de Livewire (`app.js`) se
registraba **sin mirar el motor**, y `flushIntent()` CONSUME la intención antes de aplicarla: con el
cajón SPA se quedaba con el primer `openWith()` de cada carga y lo despachaba a un componente que ya no
se renderizaba. Los tres enlaces profundos de la landing (zona, packs, eventos) abrían el cajón en el
catálogo raíz. ⚠️ **No estaba entre los cuatro caminos que `#100` exigió verificar**, así que se
desplegó roto y nadie lo vio. El borrado del adaptador es su arreglo, y está en la rama.

**(i) Estado: `main` VERDE con las cuatro entregas; el borrado APARCADO en
`wip/4.7-2b-3-retirada-purchase`** con el motor ya retirado y —lo que más costaba— el contrato de árbol
independiente y en verde (33 casos). Lo que queda son ~12 ficheros ya identificados uno a uno en el
mensaje de ese commit.

## #112 · 2026-08-21 · `Purchase.php` RETIRADO — y tres guardas que llevaban tiempo sin medir nada
Cierra `4.7·2b·3` (y con él `4.7·3`): el motor Livewire del cajón ya no existe, el cajón SPA es el
motor ÚNICO y la suite queda verde. La retirada en sí fue lo previsible; **lo transferible es lo que
apareció al mutar**, porque las tres cosas estaban en verde y ninguna medía lo que su nombre decía.

**(a) Cómo se operaron los ~12 ficheros.** Cada caso se clasificó por su **SUJETO**
(`CONVENCIONES §3.quater`), no por la regla que menciona, y **antes de borrar ninguno se localizó y
se ejecutó su sucesor**: los cinco `sidecart_*` de `ReservationPauseTest` contra
`SidebarPausedParityTest` (sus cuatro estados de canal, incluido el respaldo sin teléfono), los
cuatro de UI de `DepositSurfacesTest` contra `SidebarCartParityTest`/`SidebarTextParityTest`, los
tres del flujo público de `ReservationPauseGuardTest` contra `Api\V1\OrdersTest` y
`BookingStatusTest`. Cuatro de ellos ya venían clasificados y medidos por `#79`, `#87`, `#91` y
`#98`: **operar dentro del fichero, no borrarlo entero**, y esa previsión ahorró la sesión.

**(b) ⚠️ Los CONTADORES se miden, no se ajustan.** Los seis de `ModuleContractsTest` se pusieron a
`999` a propósito y se leyó el valor real en el fallo: pricing 1 · offer 1 · catalog 1 · admission 3
· `starts` 1 · `retries` 2. Ajustarlos «hasta que pasen» habría escondido cualquier superficie que
hubiera dejado de preguntar al contrato. Después se mutaron los cinco dobles: los cinco casos caen,
luego el re-apunte NO quedó inerte (que es lo que enseñó `#65`).

**(c) ⚠️⚠️ `assertSee('livewire.js')` estaba INERTE, y `ESTADO.md` la daba por la guarda crítica.**
La nota heredada decía que retirar `@livewireScripts` dejaría la web sin Alpine y que esas dos
aserciones eran la única red. **Falso, medido con la tabla de verdad entera**: hay DOS fuentes
redundantes —la directiva, que emite siempre, y la **auto-inyección** de Livewire, que actúa si algún
componente llegó a renderizarse (`SupportAutoInjectedAssets`, y hoy el layout renderiza cuatro)—.
Directiva NO + componentes SÍ → verde. Directiva SÍ + componentes NO → verde. Solo con las dos fuera
se pone rojo. Es la trampa 4 de `§3.quater` en estado puro: **mutar UNA rama de algo redundante no
dice nada**. La guarda se conserva porque asevera el RESULTADO —que el bundle llegue— y morderá el
día que el **área de cliente** (`#66`) retire el último componente Livewire del layout; corregidos el
docblock y el comentario de `layout.blade.php`, que afirmaban lo contrario.

**(d) ⚠️⚠️ Dos tests de SEGURIDAD de la vuelta de Redsys también estaban inertes, y por culpa del
propio borrado.** Con el motor SPA el layout **consume** `SidebarEntry` al pintar, así que
`session('purchase.confirmed_code')` está SIEMPRE vacía al terminar la respuesta. Los dos casos que
defienden que un tercero no aplique el desenlace ajeno aseveraban justo eso, o sea un valor que ya no
puede ser otra cosa (`§3.quater`: **un valor trivial no está fijado**). Medido: desactivando la
comprobación de titularidad de `HomeController::maybeConsumeRedsysReturn()` —la vulnerabilidad real—
los dos seguían VERDES. Re-apuntados al `data-boot` del cajón, la misma mutación los pone rojos.

**(e) ⚠️⚠️ Y el efecto sistémico: `assertSee(__('tickets.*'))` contra una página completa pasó a ser
VERDE FALSO.** El montaje del cajón es ahora incondicional y lleva `__('tickets')` **entero (11 kB)
en el HTML de todas las páginas**. Auditado resolviendo cada clave contra el payload real: **13
claves vacuas** y 4 literales que coinciden con valores del grupo, repartidos en 5 ficheros. El
instrumento correcto es `assertSeeText`/`assertDontSeeText`: `strip_tags` deja fuera el payload
porque viaja en un ATRIBUTO, así que mide lo que el usuario ve, que es lo que esos casos querían
decir. Verificado mutando la vista: ahora caen. ⚠️ Uno seguía sin morder por **colisión de
subcadena** —«Reembolsado» es prefijo de «Reembolsado el …»—, y se ancló además a su clase.
▶ **Regla para el futuro**: en una página que monte el cajón, un `assertSee` de cualquier texto del
grupo `tickets` no prueba nada. Es el mismo cuño de `#106`: preguntar «¿pasa el test?» en vez de
«¿sigue midiendo lo mismo?».

**(f) `Register::requestSwitchToLogin()` retirado, y el modo `embedded` NO.** El método se quedaba
sin oyente al morir `Purchase`, pero además **ya era inalcanzable antes de borrar nada**: el cajón
salta al paso 7 en cuanto `registration-submitted` se dispara, así que la pantalla `sent` no llega a
verse embebida — algo que `VerifyStep.vue` ya tenía documentado y que se confirmó leyendo el
componente borrado. Era, además, un método Livewire **público**: superficie invocable desde el
navegador. ⚠️ El modo `embedded` en cambio **se queda**: aunque en producción no lo monte nadie, es
la REFERENCIA viva contra la que `SidebarLoginParityTest` y `SidebarRegisterParityTest` comparan las
pantallas de auth del cajón. Retirarlo habría borrado siete comparaciones para limpiar una rama que
no molesta; muere solo cuando el área de cliente rehaga la auth dentro del cajón.

**(g) Un error de doc que venía de antes, cazado al barrer.** `OrderCreator` citaba un `#[Locked]`
sobre la cesta del componente Livewire «como la otra capa» de defensa del cap de líneas. Ese atributo
**nunca se aplicó**: se evaluó y se descartó a propósito porque la cesta es *client-syncable*, y así
lo dice `PAY-12` y lo dejaba anotado el propio componente. La única capa era, y es, `OrderCreator`.

**(h) Un HUECO destapado al retirar, y cerrado el mismo día: el cajón se abría SIN feedback de
carga.** `app.js::bootSpaEngine()` hace `await import()` del chunk del motor y durante esa espera no
se pintaba nada —`spaLoading` es solo guarda de reentrada—; el velo `.jj-loading` no podía taparlo
porque vive DENTRO de la app Vue que aún no ha montado. El motor Livewire sí tenía `placeholder()`, y
se fue con él sin que nadie lo notara: **`UI-SPINNER.md` seguía marcando ✅ ese caso**, que es el
recordatorio de que retirar una superficie se lleva por delante capacidades que su doc sigue
prometiendo.
▶ **Arreglado con cero JavaScript**: el velo va estático DENTRO de `#sidecart-spa`, con el mismo
marcado que servía el placeholder, y **Vue lo borra al montar** (`app.mount()` hace
`container.textContent = ''`, verificado en el runtime instalado, no supuesto). El `catch` de
`bootSpaEngine()` lo vacía si el chunk no carga, porque un spinner eterno miente.
⚠️ **Y el caso que lo vigila tuvo que reescribirse tras mutarlo**: la primera versión anclaba con una
expresión regular de `id="sidecart-spa"` a `</div></div>` y **daba verde con el velo FUERA del hueco**
—donde Vue nunca lo retiraría y se quedaría pegado para siempre—. Con `DOMDocument` sí muerde. Es
`#65` otra vez: un caso nuevo también hay que mutarlo.

**(i) Lo que queda para el siguiente**: desplegar a staging y comprobar en navegador el punto **A7 ·
enlaces profundos** de `VERIFICACION-E2E-CAJON.md` — el arreglo de `#111(h)`, que **nadie ha visto
funcionar**.

## #113 · 2026-08-21 · El cajón se servía SIN ICONOS, y el contrato de árbol no podía verlo
Reportado por el owner mirando el cajón: no se veían los iconos. Medido: los **20 `<svg>` del cajón
SPA estaban VACÍOS** —envoltorio sin dibujo—, así que ninguno pintaba nada. Es un fallo de Fase 4, no
de la retirada; lo que hizo `#112` fue **destaparlo**.

**(a) La causa, y está escrita en el código que lo provocó.** La transcripción a Vue replicó el
ÁRBOL y no el dibujo, con el motivo anotado en `CatalogStep.vue`: «el interior del `<svg>` es
geometría y el diff no desciende en él». Es **literalmente cierto** —`SidebarDomContractTest::
describe()` hace `return` al llegar a un `<svg>`, y por buenas razones: exigir a dos motores los
mismos `<path>` convertiría un contrato visual en una copia literal de los iconos—. El error no fue
la regla del normalizador: fue **transcribir hasta donde el gate mira y parar ahí**.

**(b) ⚠️ Por qué nadie lo vio antes, dicho sin adornos.** El diff de árbol da los 20 envoltorios por
buenos; el manifiesto congelado también, porque un `<svg>` vacío y uno lleno son **el mismo nodo**
para él (comprobado: la corrección no cambió ni un byte del manifiesto). En local el default era
`livewire`, así que se veían los iconos del Blade. En **staging** llevaba roto desde que se puso el
flag en `spa` — y `#110` verificó allí **cuatro caminos de navegador** sin reparar en ello. Es la
tercera vez en dos entradas que aparece el mismo cuño: **verde no es funciona** (`#59`), y un ojo
humano mirando una pantalla no sustituye a una guarda.

**(c) La regla que ya estaba escrita y no se aplicó.** `ESTADO.md` lo dice desde las paridades:
**cuando algo NO es atributo de contrato del normalizador, necesita paridad propia.** La tenían el
texto (`SidebarTextParityTest`), los importes, la cesta, el calendario… y los iconos no. La lista de
«lo que el diff no ve» estaba, pero se leía como una advertencia y no como un inventario que hay que
cerrar.

▶ **De ahí `SidebarIconParityTest`, y su formulación es la decisión**: no compara icono por icono
—un mapeo por posición se rompe al reordenar un fichero— sino que exige que **el cajón no invente
dibujos**. Cada geometría que emite tiene que ser, tras normalizar, la de un `<x-icons.*>` del
sistema de diseño, o estar declarada como propia del cajón con su motivo (son cuatro, las que venían
inline en el blade retirado y se quedaron sin componente). Así una copia que derive del original no
coincide con NADA y cae, y un icono nuevo obliga a una decisión consciente. Mutado tres veces: vaciar
un icono, cambiar el original en Blade, y estrenar un dibujo sin declararlo — los tres, rojos.

**(d) ⚠️ Y saltó una guarda buena que conviene no leer como un estorbo.** Los dibujos costaron
**7,26 KiB** —medido construyendo con y sin ellos, no restando— y el chunk pasó de 148,24 a 155,50
KiB, por encima del techo de 150 de `SidebarBundleBudgetTest`. El techo sube a **160** siguiendo la
regla escrita de ese fichero (subirlo con su motivo, no adelgazar a ciegas), con una precisión que
importa: **esto no es una función nueva que presupuestar, es la que ya se creía entregada**. De paso
quedó medido que **2,36 de esos 7,26 KiB son duplicación literal** (el taco y el pack viajan tres
veces, la flecha tres, los ojos dos): oportunidad anotada con cifra, no sospecha.

**(e) Lo que hay que llevarse.** Cuando un gate declare explícitamente que **no** mira algo —y este
lo declaraba, en su docblock y en su código—, esa frase no es una nota al pie: es un **hueco con
nombre**, y hay que cerrarlo con una guarda propia el día que se escribe, no cuando alguien mira la
pantalla. `#112` cerró tres guardas que no medían nada; esta es la cuarta, y la única que se veía a
simple vista.

## #114 · 2026-08-21 · [DECIDIDO] El canal de build del despliegue es SAIL, no «el npm que haya»
`scripts/deploy.sh` elegía cómo construir los assets con `if command -v npm`. Desplegando desde el
**segundo puesto de trabajo** murió en el paso 1/9 con «npm run build FALLÓ», y el npm de Docker
funcionaba perfectamente al lado. Medido, no supuesto:

- `command -v npm` → `/mnt/c/Program Files/nodejs/npm`. Bajo WSL, el interop de `/mnt/c` mete los
  binarios de **Windows** en el `PATH`, y ese npm existe y contesta `11.11.0` a `--version`.
- Pero al ejecutarlo lanza `CMD.EXE`, que **no admite rutas UNC**: «`'\\wsl.localhost\Ubuntu\…'`
  CMD.EXE se inició con esta ruta como el directorio actual. No se permiten rutas UNC», se cae al
  directorio de Windows, y ahí `vite` no existe. `node`, además, no estaba en el `PATH` de WSL.
- Y el script ejecutaba el build con **`>/dev/null 2>&1`**, así que nada de lo anterior se veía.

**(a) La decisión no es «detectar mejor el npm»: es que el canal canónico es SAIL.** Se prueba Sail
primero y el npm del host queda como respaldo. El motivo es de corrección, no de comodidad: **Sail es
el canal que usa el `pre-push`** (`.githooks/`), así que solo construyendo ahí se cumple que los
assets que el gate verificó son EXACTAMENTE los que viajan al servidor. Con dos cadenas de
herramientas distintas, «verde en local» deja de decir nada sobre lo que hay en staging — y eso es
divergencia silenciosa, la clase de fallo que este proyecto ya conoce.

**(b) `command -v npm` no es un test válido de «hay un npm usable aquí».** La guarda discrimina por
la RUTA del binario (`!= /mnt/*`) y exige además un `node` resoluble, porque el modo de fallo medido
es justamente un npm que existe, responde y no puede construir. Preguntar «¿existe?» es el mismo
error de forma que la guarda de Redsys de `#106`: preguntar por lo que se teme en vez de exigir lo
que se espera.

**(c) Y la otra mitad, que es la que costó el diagnóstico: el rojo no decía por qué.** El motivo
estaba a un `2>&1` de distancia y explicaba el problema entero en tres líneas. Es **literalmente la
lección de `#97`** —el `pre-push` que cayó a las 00:02, cuya salida no se capturó y se perdió—
aplicada al otro script del proyecto. Ahora el build vuelca a fichero y, si falla, se enseñan sus
últimas 25 líneas antes de abortar. **Un rojo sin nombre no se puede arreglar.**

▶ Lo guardan tres casos nuevos en `DeployScriptGateTest`, mutados los tres (invertir el orden de los
canales · devolver `command -v npm` · devolver el `>/dev/null 2>&1`): rojos los tres.

**(d) Lo que esto enseña sobre el proyecto, y no sobre WSL.** El fallo no apareció al escribir el
script: apareció al ejecutarlo desde **otra máquina**. Un canal de despliegue solo está probado en el
puesto donde se escribió hasta que alguien lo corre en otro, y `deploy.sh` nació y se midió entero en
el primer puesto (`#105`–`#110`). Al montar un segundo puesto conviene correr los dos guiones
—`deploy.sh` en dry-run y el gate— antes de necesitarlos.

## #115 · 2026-08-21 · El scheduler de staging llevaba 24 h MUERTO, y el despliegue lo daba por sano
Tras desplegar `1977db7`, el drenaje de cola de `deploy.sh` avisó de **6 jobs pendientes**. Tirando
del hilo salió algo bastante peor que 6 correos:

- Los 6 eran `OrderConfirmation` ×2, `GuestFormRequest` ×2 y `AccountAlreadyExists` ×2, en la cola
  `default`, sin reservar y con **`attempts = 0`**: nunca se habían intentado. Llevaban **~24,4 h**.
- Había además **un pedido en `pending` desde hacía 24 h**, con `orders:expire` programado cada 5
  minutos.
- El crontab estaba **instalado y correcto** (una entrada, sin duplicados), `php` resolvía a
  `/usr/bin/php` 8.5.1, y `schedule:run` ejecutado A MANO funcionaba y lanzaba `queue:work`.
- **Observado 6,5 minutos** —una ventana completa de `orders:expire` y seis de `queue:work`—: el
  pedido seguía `pending`.
- **Control, para descartar que el pedido no fuera elegible**: `orders:expire` a mano → «Pedidos
  caducados: 1», al instante.

▶ Conclusión: **no hay demonio cron en el contenedor del sitio.** El crontab se escribe y nadie lo
ejecuta (`pgrep -x cron` → nada; los `/etc/cron.*` existen pero vacíos de proceso). Es cosa del
hosting/panel, **no del script ni de la app**.

**(a) Lo que esto rompe si pasa en casa de un cliente**, que es el motivo de escribirlo: `queue:work`
no corre → **el cliente paga y no recibe nada** (`OrderConfirmation` es `ShouldQueue`) · `orders:expire`
no corre → las franjas retenidas **no se liberan** y el aforo se fuga en silencio (`AFORO-10`) ·
`slots:generate-rolling` no corre → la ventana de franjas deja de avanzar y llega el día en que no hay
nada que comprar · `model:prune` no corre → la retención de `CookieConsentLog` deja de cumplirse.

**(b) ⚠️⚠️ Y el despliegue lo declaró SANO, con las 6 comprobaciones en verde.** Esta es la parte que
hay que llevarse, porque no es un descuido sino **dos señales que miden otra cosa**:
- **«scheduler: 5 tareas registradas»** mide que la APP conoce sus tareas. No dice **nada** de que
  alguien las dispare. Se leía como «el scheduler funciona».
- **`failed_jobs = 0`** es **estructuralmente ciego** a este fallo: un job que nunca se intenta nunca
  falla. Y el comentario de `routes/console.php` —heredado, escrito con buen criterio— mandaba
  vigilar exactamente esa señal para exactamente este caso. Estaba corregido en el sitio equivocado.

**(c) La señal que sí lo ve, y por qué.** La **edad del trabajo más viejo de `jobs`**: con el worker
vivo la cola se drena cada minuto, así que algo disponible desde hace más de 5 minutos significa que
nadie lo está sacando. Es una comprobación de EJECUCIÓN, no de configuración, y no depende de por qué
mecanismo se dispare el scheduler. Fail-closed: si la lectura falla, el contador queda vacío y la
comprobación cae —misma forma que la guarda de Redsys de `#106`—. Se añade a `deploy.sh` con un aviso
secundario informativo si no se ve demonio cron. Tres casos nuevos en `DeployScriptGateTest`, mutados.

**(d) Lo que esto obliga a releer.** `#110` verificó cuatro caminos de navegador en staging con el
scheduler muerto. **No los invalida** —ninguno depende del cron— pero significa que **allí nunca se ha
ejercitado la caducidad de pedidos ni el envío diferido**, y la doc no lo decía porque nadie lo sabía.

**(e) La regla, que ya es la tercera vez que aparece con otro disfraz.** `#113` la dejó escrita: **lo
que un gate declara que NO mira es un hueco con nombre.** Aquí ni siquiera lo declaraba: lo insinuaba
al revés, con una etiqueta que prometía más de lo que medía. **Una comprobación que mide una cosa y se
lee como otra es peor que no tenerla**, porque regala confianza que no ha ganado.

▶ **PENDIENTE DEL OWNER**: activar las tareas programadas del sitio en el panel de Enhance. Hasta
entonces, en staging hay que disparar a mano lo que haga falta:
`ssh jumpweb-staging "cd ~/public_html && php artisan schedule:run"`.

## #116 · 2026-08-21 · [DECIDIDO] El contador de la suite deja de ser una foto y pasa a tener receta
`#112`/`6e6d56b` retiraron cifras que mentían con una regla explícita: **una foto sin receta que la
vigile es drift en espera, así que se retira**. Se aplicó a las líneas de `Sidebar.vue` y, hoy, al
duplicado del contador de tests en «Herencia» (decía **2715** con la suite en **2642**).

**(a) Y aun así volvió a derivar DOS veces el mismo día**, lo cual es el dato que importa: una de
ellas **en el commit que acababa de retirar el duplicado por haber derivado**, y otra al añadir tres
casos nuevos. No es descuido de nadie. Es que retirar la copia arregla la duplicación, **no** el
número: el original sigue sin vigilancia porque `docs-check` no lo mira —sus cuatro patrones son
modelos, migraciones, invariantes y Resources, y «N tests» no casa con ninguno—.

**(b) La decisión: darle la receta en vez de retirarlo también.** El contador tiene valor real —es la
señal de «la suite no ha encogido sin que nadie lo note»— así que la salida no era borrarlo sino
vigilarlo. Y el sitio natural es el `pre-push`, **porque ya tiene la cifra en la mano**: acaba de
correr la suite. Comparar cuesta cero. Si la suite dice 2650 y `ESTADO.md` dice 2645, el push se corta
con las dos cifras y la línea exacta a corregir.

**(c) Fail-closed, y no por simetría.** Se comprueban CUATRO lecturas (tests y aserciones, medidos y
declarados) y **si alguna sale vacía el gate corta**. Sin eso, un cambio de formato en la salida del
runner o en `ESTADO.md` dejaría al gate comparando dos cadenas vacías —que son iguales, o sea VERDE,
sin haber comprobado nada—. Es exactamente el modo de fallo de `#106`, donde una guarda comparaba
contra basura y la bendecía.

▶ Dos casos en `PrePushGateTest`, mutados los dos: neutralizar la comparación → rojo; sustituir la
condición de fail-closed por `if false` → rojo.

**(d) La regla generalizable, que es la tercera versión de la misma.** `#113`: «lo que un gate declara
que NO mira es un hueco con nombre». `#115`: «una comprobación que mide una cosa y se lee como otra es
peor que no tenerla». Y ahora: **retirar una copia no vigila el original**. Las tres son la misma
pregunta —¿quién comprueba esto, y cuándo se entera de que ha dejado de ser cierto?— y las tres se
resuelven igual: dándole al dato un sitio donde se mida solo.

## #117 · 2026-08-21 · A7 no estaba «roto a medias»: la costura de intención nunca se cableó
Verificando A7 en staging con navegador headless —el andamio de `VERIFICACION-E2E-CAJON.md` §5.bis,
que solo se había usado en local (`#59`)— salió algo que ni el gate ni yo habíamos visto.

**(a) Lo medido, en vivo.** Tras pulsar «ver packs» en `/cumpleanos`, con el cajón ABIERTO, el
catálogo CARGADO y 1,5 s de margen:

    machine.takeIntent()  →  { "type": "packs" }     ← nadie la había consumido
    machine.step          →  1                        ← catálogo raíz
    Alpine store.intent   →  null                     ← Alpine SÍ la había entregado

La cadena estaba entera hasta el penúltimo eslabón: `@click` → `openWith()` → `flushIntent()` →
`applyIntent()` → `queueIntent()` → **nada**. `takeIntent()` —lo ÚNICO que consume la intención— no
lo llamaba nadie en producción: solo `machine.test.js`. Los tres enlaces profundos abrían el catálogo
raíz, y **no fallaban: no hacían nada**, que es literalmente el modo de fallo que la costura de 4.0a
se creó para impedir.

**(b) Por qué ningún test lo vio, que es lo que hay que llevarse.** `machine.test.js` prueba
`queueIntent` y `takeIntent` **como par, en aislamiento**, y pasa. **Los dos extremos estaban
probados y nadie cableaba el medio.** Un test unitario verde no dice absolutamente nada sobre si
alguien llama a lo que prueba. Es primo del fallo de la banda de progreso en 4.3·1 —módulo verde,
cableado roto, gate sin verlo— y de las tres guardas inertes de `#112`.

**(c) Por qué `#111(h)` no lo cerró, sin reproche.** Diagnosticó bien —el adaptador de Livewire
CONSUMÍA la intención y la despachaba a un componente que ya no se renderizaba— y concluyó «el
borrado del adaptador es su arreglo». **Lo era, para la mitad de dejar de perderla.** La otra mitad
—aplicarla— nunca se transcribió a la SPA, y como el síntoma era el mismo antes y después (el cajón
abre en el catálogo raíz), nada distinguía «arreglado» de «arreglado a medias».

**(d) ⚠️ Y por qué mi propia verificación previa tampoco lo vio.** Yo había medido el bundle
desplegado —«refs a `Livewire` en `app.js`: 6 → 3»— y di el arreglo por bueno. El adaptador SÍ se
había ido; eso era cierto y era irrelevante. **Es exactamente la clase de evidencia que ya falló en
`#113`**: medir el artefacto en lugar del resultado. Un grep de un bundle no puede ver una función
que nadie llama, igual que no podía ver un `<svg>` vacío.

**(e) El arreglo, y su límite DECLARADO.** `intent.js`, módulo plano (`CE-6`, y además el techo de
`Sidebar.vue` en `SidebarComponentBudgetTest` **solo encoge**): traduce la intención a un destino y lo
aplica con el mundo inyectado —paso, ancla y desplazamiento—, así que se prueba entero sin DOM ni Vue.
`index.js` lo cablea conservando el paso por la máquina —sigue siendo la dueña única de «hay una
intención pendiente»— pero **drenándola acto seguido**.
⚠️ **`{type:'zone'}` queda a medias, y se devuelve marcado `exact: false` en vez de fingir paridad**:
el motor retirado hacía scroll a la ZONA concreta y la SPA no puede, porque `catalog.js::toItem()`
**descarta el campo `zone`** que la API sí publica. Aterriza en la sección «Entradas», que es lo
correcto hasta donde el modelo alcanza. Completarlo exige devolver la dimensión de zona al modelo del
catálogo, y eso **toca el manifiesto de árbol CONGELADO**: es una decisión de producto, no un parche.

**(f) Verificado tras desplegar, en el navegador y contra staging:**

    packs  → { applied: true, anchor: "catalog-sec-services", exact: true }
    pendiente tras aplicarla → null (CONSUMIDA)   ← el inverso exacto de (a)
    zona   → { applied: false, reason: "anchor_missing", anchor: "catalog-sec-entries" }
    basura → { applied: false, reason: "no_intent" }

⚠️ **Y una limitación del ENTORNO que conviene saber**: staging tiene 4 productos, **todos `pack` y
ninguno `entry`**, así que solo se pinta una sección y **la posición del scroll no puede distinguir el
arreglo**. Por eso la verificación se hizo sobre el VALOR DEVUELTO por el cableado y no sobre la
pantalla. Para ver el efecto visual hace falta un catálogo con las dos secciones.

**(g) La guarda: `SidebarIntentWiringTest`.** Alguien de PRODUCCIÓN tiene que consumir la intención, y
los `*.test.js` **no cuentan** — si contaran, el test pasaría en verde con el fallo intacto, que es
justo lo que pasaba. Escrita ANTES del arreglo y vista en ROJO; verde después.

**(h) La regla, que generaliza más allá de esto.** `#113` dejó «lo que un gate declara que NO mira es
un hueco con nombre» y `#115` «una comprobación que mide una cosa y se lee como otra es peor que no
tenerla». Esta añade la que faltaba: **probar los dos extremos de una costura no la cablea.** Cuando
una pieza existe para que OTRA la llame, hay que vigilar la llamada, no la pieza.

## #118 · 2026-08-22 · El bloque de cuenta se OCULTA en la compra — y al implementarlo salió que el «modo» del panel llevaba muerto
Petición del owner: el saludo «Hola, saltador/a · Inicia sesión y guarda tus reservas» **solo genera
ruido dentro del embudo**; que se oculte con una animación al entrar en la compra y vuelva al
terminar.

**(a) Lo que había, medido antes de tocar.** El panel ya publicaba una clase de modo
(`is-catalog`/`is-booking`/`is-cart`/`is-result`) y el CSS «minimizaba» la cuenta — **pero solo en
`booking`**. O sea que el bloque **reaparecía ENTERO** justo en carrito, identificación y pago, que es
donde más estorba; y en identificación enseñaba sus dos botones **deshabilitados**
(`$store.purchase.identifying` los bloquea porque el flujo ya pide identificarse abajo): ruido con
botones muertos.

**(b) La implementación.** Se oculta en `booking` (día, hora) y `cart` (carrito, identificación,
pago); sigue visible en `catalog` —aún no ha entrado, y ahí el CTA sí sirve— y en `result` —ya
terminó, que es literalmente el «hasta finalizar» que se pidió—.
- **Se colapsa con `grid-template-rows: 1fr → 0fr`**, el idioma que este repo ya usa en
  `.catalog-acc__body`, y no con `max-height`: un techo mayor que la altura real deja dos tercios de
  la animación sin que se mueva nada y luego da un tirón. Exige un hijo que recorte, de ahí
  `.acct__inner` — el root no puede serlo porque ahí viven el fondo, el padding y el borde que también
  se colapsan, y Livewire exige un único root.
- ⚠️ **`visibility: hidden`, y no es adorno: el panel tiene TRAMPA DE FOCO** (`a11yPanel`). Colapsar
  solo con la rejilla y la opacidad dejaría dos botones invisibles pero TABULABLES dentro de la
  trampa. Verificado pulsando Tab de verdad en un navegador: con el bloque oculto, **25 tabulaciones
  y ninguna aterriza dentro**; en catálogo sí es alcanzable.

**(c) ⚠️⚠️ Y entonces salió lo gordo: el «modo» del panel NO CAMBIABA.** Al verificarlo en navegador,
el panel seguía en `is-catalog` en los pasos 2, 3, 4 y 5. Aislado paso a paso:

    paso 5 →  machine.mode = "cart"   ·  store.mode = "catalog"
              machine.identifying = true ·  store.identifying = false

La máquina estaba PERFECTA; el store de Pinia publicaba valores rancios. **Causa**: los getters eran
`state.machine?.mode` / `state.machine?.identifying`. Un getter de Pinia es un `computed` y solo se
recalcula cuando cambia algo REACTIVO que haya leído; `state.machine` es un objeto plano cuya
identidad nunca cambia, y su `get mode()` devuelve `modeOf(current)` sobre una variable de **closure**
que Vue no puede observar. El valor se cacheaba en el primer render y no se invalidaba jamás.

▶ Son **exactamente las dos regresiones silenciosas** que los comentarios de `app.js` y `machine.js`
avisaban por escrito («un motor que no publique estas dos señales deja el panel en `is-catalog` para
siempre y los botones de invitado activos durante la identificación»). Estaban avisadas, escritas… y
vivas. La segunda es de verdad seria: en la identificación los botones del bloque de cuenta seguían
ACTIVOS, y **pulsarlos CIERRA el cajón** y abre el modal de login en mitad del checkout.

**(d) El arreglo**: derivar de `state.step` —que sí es estado reactivo de Pinia— con `modeOf()` e
`isIdentifying()`, las MISMAS funciones puras que usa la máquina. No hay segunda fuente de verdad: se
lee el mismo dato por el lado que Vue puede observar.

**(e) ⚠️ Por qué el test que ya existía no lo cazó, que es la lección.** `store.test.js` **ya**
comprobaba `store.mode` tras un `enter()` y pasaba **con la señal muerta**: un `computed` que nunca se
ha evaluado no puede estar rancio, así que leerlo UNA sola vez, después del cambio, siempre da el
valor bueno. En el navegador se lee en cada render, se cachea en el primer pintado y ahí se queda.
▶ **Un test de una señal derivada tiene que ejercitar la INVALIDACIÓN, no el valor**: leer ANTES y
DESPUÉS de la transición. El caso nuevo lo hace, y con la primera lectura comentada como lo que es —la
que crea la caché—. Mutado devolviendo los getters a la forma antigua: rojo.

**(f) Y una tercera del mismo árbol.** `#117` dejó «probar los dos extremos de una costura no la
cablea». Esta añade el matiz: **aquí la costura SÍ estaba cableada** —el `watch` existía, el getter
existía, la clase se pintaba— y aun así no funcionaba, porque el cable pasaba por un punto que el
framework no puede observar. No basta con que las piezas se llamen: hay que comprobar que el valor
LLEGA, y eso solo se ve ejecutando.

**(g) De paso, `DECISIONES #42` queda completo.** Su plan decía «en `transition` no se tokeniza la
declaración, pero sí la duración y las 2-3 curvas», y esa mitad seguía pendiente. Nacen
`--dur-collapse`, `--dur-fade`, `--ease-panel` y `--ease-bounce`, y las **22** apariciones de las dos
curvas del cajón pasan a token (mismo valor exacto → riesgo visual cero). El suelo de
`SidebarTokenBudgetTest` sube de 71 a **72**, que es lo que su propio mensaje pide al subir el ratio.

## #119 · 2026-08-22 · [DECIDIDO] El cajón se organiza en TRES capas y por SECCIONES, antes del área de cliente
El owner paró el avance de funcionalidad con un argumento correcto —«si metemos «mi cuenta» en el
cajón con la arquitectura de hoy, después será más difícil»— y pidió reorganizar primero. La
reorganización se hizo **midiendo antes de decidir**, y la medición corrigió dos veces el plan.

**(a) El punto de partida, medido.** `Sidebar.vue` eran **614 líneas de código —el 29% de todo el
cajón (2.085 en 39 ficheros)— y 11 de sus 22 llamadas a la API**. Pero el problema NO era «hay lógica
en el componente»: la lógica pura ya estaba fuera, en 18 módulos planos sanos. Eran **103
declaraciones, 55 de estado y 48 funciones**, ninguna de más de 30 líneas. Anchura, no profundidad.
Lo que no tenía casa era el ESTADO.

**(b) ⚠️ Y la casa estaba decidida desde el principio, sin construir.** `#38c`: «la recomendación
inicial era Vue SIN Pinia; el owner aportó el requisito que faltaba —tres dominios que comparten
sesión— y con tres dominios **los stores separados** dejan de ser ceremonia». Había **un** store de 27
líneas con `step` y `machine`, y 55 piezas de estado en el componente. La capa por la que se adoptó
Pinia no existía.

**(c) La primera corrección de la medición: el estado no es lo que pesa.** Extraído el primer dominio,
la bajada fue de **catorce líneas**. Al medir por qué: el estado son **55 líneas de 614 (9%)** y las
funciones **441 (72%)**. Mi estimación por dominio medía *proximidad*, no *movilidad*. Se reordenó el
plan para ir donde estaban las funciones, y el dominio siguiente (`auth`) bajó 44 de golpe.

**(d) El resultado: TRES capas con responsabilidades distintas.**
- **módulos planos** (`calendar.js`, `cart.js`, `admission.js`…) — las REGLAS, probadas con
  `node --test` y con paridad contra el servidor. No cambian.
- **stores de Pinia** (`stores/`, **nueve**) — el ESTADO de cada dominio y las secuencias que no salen
  de él, incluidas sus llamadas a la API. Se prueban sin DOM ni Vue.
- **componentes** — pintan.

    Sidebar.vue:  614 → 438 líneas · 11 → 2 llamadas a la API
    y luego         438 →  16 líneas ·      0 llamadas   (al partirlo por secciones)

**(e) La segunda decisión, y la que responde a la pregunta del owner: qué se hace AHORA y qué no.**
Se midió función por función qué dominios toca cada secuencia pendiente. Las cinco que quedan
—`addToCart`, `confirmReservation`, `retryPayment`, `poll`, `showLineProblems`— viven **íntegramente
en el embudo**: el área de cliente no las rozará, así que **su coste no crece**. Son deuda SIN
intereses y esperan (ficha en `DEUDA.md`).
▶ Lo que sí decae, y por eso se hizo ahora:
- **el GRAFO**: `TRANSITIONS` era el mapa explícito de los once pasos del embudo. Un área de cliente
  **no es un embudo** —sus pantallas se navegan libremente— y colgarla ahí mezclaría dos modelos en un
  mapa. Pasa a `FUNNEL_TRANSITIONS`, se exporta con `FUNNEL_STEPS` y una guarda falla si aparece un
  paso ajeno. El nombre no es cosmético: a secas parecía «las transiciones del cajón».
- **la RAÍZ**: eran once ramas `v-else-if` con ~9,5 líneas de cableado por pantalla en un fichero.
  Cinco pantallas de cuenta habrían sido +45 líneas ahí más sus manejadores. El embudo pasa a
  `sections/PurchaseSection.vue` y nace una raíz de 16 líneas que solo enruta.

⚠️ **NO se ha inventado el modelo del área de cliente**, y es deliberado: sin pantallas sería
especulación, y su diseño es trabajo de `#66` con el owner delante. Lo que se ha hecho es cerrar el
embudo y darle casa propia, para que la cuenta pueda tener la suya.

**(f) Lo que el refactor destapó, que es la mitad de su valor.** Tres fallos VIVOS que ninguna prueba
veía —la navegación de mes muerta desde 4.2·2 (`SidebarEmitWiringTest`), y antes los enlaces
profundos (`#117`) y las señales del panel (`#118`)— más **dos regresiones propias**, las dos cazadas
al revisar sitios de llamada y verificadas en navegador: la navegación al purgar la cesta, perdida al
mudar la identidad y **a punto de perderse otra vez** al partir la raíz.

**(g) La lección que ordena a todas las anteriores.** `#113` («lo que un gate declara que no mira es
un hueco»), `#115` («una comprobación que mide otra cosa es peor que no tenerla»), `#117` («probar los
dos extremos no cablea el medio») y `#118` («que las piezas se llamen no significa que el valor
llegue»). Esta fase añade la quinta: **el contrato de árbol NO ejerce `Sidebar.vue`** —el renderer no
lo importa, y su propio comentario dice por qué—, así que en un refactor del orquestador **la única
red real es el navegador**. Desde que se asumió, cada tramo se recorrió en uno, y ahí aparecieron los
tres fallos vivos.

## #120 · 2026-08-22 · [DECIDIDO] El área de cliente entra en DOS tandas, y `/mi-cuenta/…` desaparece
Decisiones del owner tomadas al abrir el trabajo de `#66`, más lo que se midió antes de proponérselas.
El diseño completo está en `docs/specs/area-cliente.md`; aquí queda lo que decidió el owner y por qué
la propuesta llegó partida en dos.

**(a) ⚠️ La medición que partió el trabajo: «el servidor ya está» solo es cierto A MEDIAS.** `#66(d)`,
`ESTADO.md` y `00-REFACTOR.md` repiten que el área de cliente es «pintar, no abrir dominio». Medido
endpoint por endpoint contra `routes/api.php` el 2026-08-22: **cierto para LEER** —`GET /me`,
`/me/orders`, `/me/reservations`, `/me/reservation-eligibility`, `/auth/*` y el reintento por
`POST /orders/{code}/payment` existen y están probados— y **falso para GESTIONAR**: cambiar el perfil,
cambiar la contraseña, cerrar sesión en otros dispositivos, borrar la cuenta y exportar los datos **no
tienen ningún endpoint**. Viven solo en `App\Livewire\Account\*` y en el controlador web del export.
▶ Son dos trabajos de tamaño distinto —uno es pintar, el otro es abrir cinco superficies de API sobre
dominio existente—, así que se presentaron por separado en vez de como «el área de cliente».

**(b) La decisión de alcance: TANDA 1 = solo lectura.** «Mis reservas» y «Mis pedidos» dentro del
cajón, con su acordeón de detalle y el reintento de pago. Las gestiones de cuenta y la auth dentro del
cajón quedan para la tanda 2. El motivo de aceptar el corte es el de siempre en esta fase: **un paso
que se puede terminar y verificar entero** vale más que uno que deja media superficie a medias.

**(c) La decisión de destino: `/mi-cuenta/…` DESAPARECE.** No conviven; el cajón es el único sitio,
que es lo que `#66(a)` decía y `#66(b)` dejó explícitamente sin decidir. Se decide ahora porque cambia
el diseño: con las páginas condenadas, el área de cliente tiene que llegar a **paridad completa** —las
siete zonas—, y eso descartó un modelo de navegación de pestañas que con dos zonas habría bastado.
⚠️ **La retirada NO es la tanda 1.** Es la tanda 3, con cuatro condiciones de entrada escritas en la
spec (§4.8), y hereda literalmente la lección de `#111`: **independizar el contrato ANTES de borrar**.
⚠️⚠️ **Y «desaparecer» hay que decirlo con precisión, porque en crudo rompe cosas ya entregadas.**
Medido después de tomar la decisión: **8 notificaciones por correo** apuntan a `route('account.orders')`
—están en buzones de clientes y **un correo enviado no se puede editar**—, **11 redirecciones del
servidor** aterrizan ahí con un `->with('status', …)` que la página pinta, y hay **27 referencias** en
total. ▶ Lo que muere es la **VISTA**; la **RUTA sobrevive como puerta de entrada** que abre el cajón
en su zona —el patrón de `/entradas`, probado desde la Fase 5.2— y el mensaje flash viaja por
`Http\Sidebar\SidebarEntry`, que ya resuelve exactamente ese problema para el desenlace del pago.

**(d) ✅ Lo que el owner validó, ese mismo día y tras la revisión de (f).** El **modelo de navegación**
—índice + zonas libres con pila de retorno, `spec §3.1`—, el **modo `account`** del panel (`spec §3.3`)
y **no tocar la URL del navegador en la tanda 1** (`spec §3.4`: el embudo tampoco lo hace, y meter un
router solo en la cuenta crearía una asimetría difícil de defender; se reevalúa en la tanda 3, cuando
existan las siete zonas). La spec pasa a **✅ APROBADA → a implementar**.

**(e) Una diferencia de naturaleza con TODO lo hecho en la Fase 4, que cambia dónde está la red.** El
embudo fue una transcripción: existía un original que pintaba en el mismo sitio, así que el contrato
podía ser el ÁRBOL y el gate compararlos nodo a nodo. **El área de cliente no tiene original**:
`/mi-cuenta/pedidos` es una página ancha y el destino es un panel estrecho. Un diff de árbol entre los
dos sería un test que no se puede pasar sin romper el diseño.
▶ Por eso la red de esta tanda es **paridad de DATOS y de REGLAS** —¿se enseña lo mismo, se ofrece lo
mismo, se oculta lo mismo?— más el **navegador**, que es lo que `#119(g)` dejó dicho: el contrato de
árbol no ejerce el orquestador porque `render-sidebar.mjs` no importa la raíz.

**(f) La revisión crítica del propio diseño, el mismo día, y lo que cambió.** La v1 de la spec se
revisó a petición del owner («¿es esta la mejor manera?») y **cuatro puntos no aguantaron la
medición**:
· **la conmutación de sección**: proponía `v-show`. Medido `PurchaseSection.vue` —**1 solo `ref` local,
  pero cinco cargas en `onMounted`**— la forma correcta es **`<KeepAlive>`**: no repite el arranque, no
  deja dos árboles dentro del mismo `role="dialog"` y **no emite nodo**, así que no parte la cadena
  flex del panel. Con su trampa escrita: `onUnmounted` no corre al conmutar, y copiar
  `onDeactivated(stopPolling)` por simetría **rompería la verificación del pago** de quien cambia de
  sección mientras espera;
· **la paridad de datos**: proponía comparar contra `/mi-cuenta/pedidos` — **una red alimentada por la
  página que va a morir**, que es literalmente el error que `#67` corrigió. Se parte en dos: una
  paridad **permanente contra la respuesta de la API** (la fuente que sobrevive) y una **captura
  temporal con caducidad declarada** cuyo único valor es encontrar los huecos antes del borrado;
· **la retirada**: ver (c) — 8 correos entregados no admiten un 404;
· **la cadena flex del panel** (`.sidecart__body` → `#sidecart-spa` → `.purchase` →
  `.purchase__scroll`, hijos DIRECTOS): no estaba en la v1 y **ningún test puede verla** —lo dice el
  propio CSS—. Entra como requisito vinculante del enrutado y como comprobación explícita del
  recorrido en navegador.
▶ **La lección, que es de método**: las cuatro salieron de MEDIR el código, no de releer el diseño. Un
spec revisado contra sí mismo solo encuentra incoherencias; contra el código encuentra defectos.

**(g) Y una QUINTA corrección, que la trajo IMPLEMENTAR y no diseñar: `<KeepAlive>` se cayó.** La
revisión (f) lo había elegido para conmutar de sección. Al escribir el paso 1 aparecieron dos cosas
que ningún análisis de diseño podía ver:
· **anula la template `ref`** de la sección desactivada, y de esa ref cuelga el puente de
  `defineExpose` —`refreshBookingStatus` y `refreshIdentity`, que `index.js` invoca en CADA apertura
  del cajón—: el `?.` se habría comido las dos señales **en silencio**;
· **cuesta 2,3 KiB de chunk** (medido con y sin él), y él solo hacía saltar el techo de
  `SidebarBundleBudgetTest` — cuyo propio comentario advierte que el área de cliente «tendrá que
  decidir su propio presupuesto, no colarse por el margen de esta».
▶ Lo elegido: **`v-show` en la compra** (nunca se desmonta ⇒ la ref y el puente intactos, y la trampa
de `onUnmounted`/`onDeactivated` que (f) declaraba deja de existir) y **`v-if` a secas en la cuenta**,
con «pedir solo si no hay datos» viviendo en su store —regla explícita y probable con `node --test`,
en vez de una caché del framework—. Sin `KeepAlive`, el paso 1 **cabe en el presupuesto que ya
existía** y no consume margen de nadie.
▶ **La lección**: un diseño se valida midiendo el código; una decisión de framework, además,
**construyendo**. El coste en KiB y la anulación de una ref no se leen en ninguna documentación de
arquitectura: salen del build y del comportamiento.

**(h) Lo que el paso 2 destapó, que es la mitad de su valor: TRES casos en verde falso y uno inerte
desde hacía meses.** Añadir tres claves al `data-boot` del cajón —150 B para que el área de cliente
pinte sus rótulos— tocó **cuatro aserciones de tres ficheros distintos**: una se puso **roja**
(`AccountOrdersPaginationTest`, un `assertDontSee` que ya no podía pasar) y **tres quedaron en verde
falso**, porque el texto que aseveraban pasó a viajar en cada página. Es `TESTING.md` §2.ter, que
estaba escrito para el grupo `tickets` y **vale para todo lo que entre en el montaje**.
⚠️⚠️ **Y al mutarlas una a una —que la regla exige— apareció lo que ningún cambio había destapado**:
`AccountAccessTest::test_verified_users_can_view_the_account_page` **llevaba INERTE desde `#231 p8`**.
Comprobaba que `/mi-cuenta` enseña su título, y `landing.footer.account_link` es literalmente el mismo
texto —«Mi cuenta»— en el footer de esa misma página: con el `<h1>` borrado, el caso seguía verde,
**y también seguía verde tras convertirlo a `assertSeeText`**. Solo lo mide una aserción estructural.
▶ **La lección, que amplía la §2.ter**: `assertSeeText` arregla la colisión con un ATRIBUTO, no la
colisión con un GEMELO visible. Y **convertir sin mutar cambia un verde falso por otro** (`#65`): las
cuatro se mutaron, y la primera pasó igualmente — que es exactamente cómo se encontró.

**(i) Y una corrección de ALCANCE que salió de leer `lang/`: son DOS zonas, no tres.** El diseño
proponía `RESERVATIONS` («próximas») y `ORDERS` («mis pedidos») como pantallas distintas. Medido:
**`account.orders.title` es literalmente «Mis reservas»** —la página `/mi-cuenta/pedidos` ya se llama
así de cara al cliente— y el vocabulario del cliente **no distingue** pedido de reserva.
`GET /me/reservations` no alimenta ninguna pantalla hoy: alimenta el **bloque de cuenta** (próxima
reserva, contador, aviso de post-form). ▶ Crear la zona separada habría sido **inventar producto** en
una tanda cuyo criterio es la paridad; los dos endpoints se usan igual —`/me/orders` en la zona y
`/me/reservations` en el índice—, que es donde esa información vive hoy.

**(j) El paso 3 destapó que «hay que pintar» valía para los DATOS, no para su PRESENTACIÓN — y de
paso, cuatro copias de la misma fórmula.** Medido el 2026-08-22 antes de escribir una línea de
cliente: `me/orders` y `me/reservations` publicaban las fechas **crudas**, y el cliente **no puede**
componer sus etiquetas.
· **El día**: `Intl.DateTimeFormat` no reproduce lo que compone Carbon. ⚠️ Y el matiz importa, porque
  la primera medición fue con el PREAJUSTE `{weekday, day, month}` —que en inglés invierte el orden—
  y eso ya lo sabía el proyecto: `progress.js` lo evita fijando el patrón por partes, y con el patrón
  fijado la divergencia queda **acotada al español** («Sáb 5 sept» de ICU frente a «Sáb. 5 sep.» de
  Carbon). Está en `DEUDA.md` desde el 2026-08-14, **con esta misma solución escrita**: publicar la
  etiqueta ya formateada.
· **La hora del pedido**: `DisplayTime::format()` aplica `display_timezone`, un **ajuste del panel**.
  Un cliente en otra zona formatearía el mismo pedido con una hora distinta de la que enseña el panel.
▶ Por eso `date_label`, `created_label` y `refunded_label` viajan **al lado** de sus valores crudos —el
mismo criterio que `time_window` lleva usando desde Fase 3— y `guest_form_url` la compone el servidor,
porque componerla en el cliente es quemar el enrutador de Laravel en JavaScript.

⚠️⚠️ **Y al buscar dónde vivía la fórmula apareció el hallazgo de verdad: estaba COPIADA en cuatro
superficies públicas** —el bloque de cuenta del cajón, la página «Mis reservas», el post-form de
invitados y la tarjeta de producto de los correos—, con el `Str::ucfirst(...isoFormat('ddd D MMM'))`
escrito a mano en cada una. Es literalmente lo que `openapi/v1.yaml` describe para
`shows_deposit_note`: «hay cuatro superficies pintando este bloque, y recomponerlo en cada una es como
divergen». Nace `DisplayTime::dayLabel()` como fuente única, las cuatro pasan a usarla y
`DayLabelSingleSourceTest` lo vigila con excepciones **con motivo** (el panel usa otro formato;
`ScheduleDisplay` compone sin mayúscula). ▶ Cuatro copias no divergen el día que se escriben: divergen
el día que alguien arregla una.
⚠️ Con una trampa que tiene caso propio: **una fecha de franja es una fecha CIVIL, no un instante**, y
`dayLabel()` **no** le aplica zona horaria — hacerlo pintaría el «23 de agosto» como 22 en cualquier
zona al oeste de UTC. `format()` sí la aplica, porque un `created_at` sí es un instante.

⚠️ **Lo que NO se hizo, y es deliberado**: cerrar la deuda de fechas del EMBUDO. Tocar la banda de
progreso, su paridad y su manifiesto dentro del paso del área de cliente mezclaría dos trabajos —la
regla que ha ordenado la fase entera—. Queda en `DEUDA.md`, anotada como **más barata** (el mecanismo
ya existe) y **más urgente** (el área de cliente hace visible la incoherencia: el embudo pinta «Sáb 5
sept» y la zona de reservas «Sáb. 5 sep.», en el mismo cajón).

**(k) El paso 3b, y las tres cosas que enseñó al construirlo.**
· **`paid_online_cents` incluye los complementos.** El aviso de señal de una reserva con un pack de
  60,00 € y 8,00 € de calcetines dice «Señal **68,00 €** · 30,00 € en el parque», no 60,00. Lo fijó
  `SidebarAccountParityTest` **contra la respuesta real** antes de que nadie lo viera en pantalla, y
  después el navegador lo confirmó. Recomponer ese número en el cliente —que es lo que la spec
  prohíbe— habría dado 60,00 € y **nadie lo habría notado**: es un importe plausible.
· **Los textos del área de cliente viajan SOLO con sesión.** Un invitado no puede abrir esa sección,
  así que sus bytes eran desperdicio **en la ruta de más tráfico del sitio**, que es justo la que
  `PERF-02` existe para proteger. Medido: montaje anónimo **1.608 B**, con sesión **2.309** — el área
  cuesta **701 B a quien tiene sesión y 0 al resto**. Lo vigila la guarda de poda, ahora con un caso
  por cada estado de sesión.
· **`TESTING.md` §2.ter volvió a morder, y esta vez estaba PREDICHO.** La nota que (h) añadió decía
  «al añadir una clave al `data-boot`, audita quién asevera ese texto contra una página»; se hizo, y
  aun así aparecieron dos aserciones más al correr la suite. Convertidas y **mutadas** — y la mutación
  volvió a pagar: la primera apuntaba al fichero equivocado (la paginación vive en un componente
  aparte, no en la vista) y habría dado por buena una comprobación que no medía nada.

⚠️ **Y una decisión de arquitectura que se corrigió a mitad**: el store empezó importando `api` y así
**no se podía doblar** —el resto de los stores del cajón la reciben por parámetro—. Hoy es inyectable
**con el cliente real por defecto**, que resuelve las dos mitades: `node --test` puede doblarla y el
componente no necesita conocer la API. Un componente que importa `api` para pasársela a un store ya
está orquestando peticiones, que es lo que `SidebarComponentBudgetTest` existe para impedir.

**(l) El paso 4 —la puerta— y el test que se había quedado corto.** «Mis reservas» del bloque de
cuenta abre ahora la sección dentro del cajón, cruzando tres piezas y **dos tecnologías**:
Blade/Livewire → `$store.purchase.followAccountLink()` (Alpine) → `spaHandle.showAccount()` (Vue).
Es la forma de cadena que `#117` dejó rota durante meses, así que se vigila **eslabón a eslabón**
(`AccountDoorWiringTest`, cuatro mutaciones) y se verifica en navegador.

⚠️ **El `href` del enlace se conserva, y eso se MIDIÓ en vez de suponerlo.** Entre que el panel se abre
y el `import()` del motor termina hay una ventana real con `spaHandle` a `null`; en ella, un botón sin
`href` **no falla y no hace nada**. Cortando el chunk del motor en el navegador
(`route("**/assets/sidebar-*.js", abort)`), el clic navega a `/mi-cuenta/pedidos`, que es lo correcto.
Ese `href` parece decorativo al lado de un `x-on:click`, y quitarlo no rompería ningún test de
conducta: por eso tiene caso propio.

⚠️⚠️ **Y destapó un test propio que se había quedado corto: `SidebarAccountVisibilityTest`.** Su
título decía «los modos que ocultan son EXACTAMENTE los del proceso de compra», pero el paso 1 había
añadido `is-account` a la regla del CSS y **el test pasó por omisión** —solo aseveraba sobre cuatro
modos y el nuevo no era ninguno—. No estaba roto: **decía algo que ya no era verdad**, que es peor
(`#115`). Ahora enumera los tres, cada uno con su motivo: en la compra el bloque **estorba**; en el
área de cliente **sobra** —sus botones llevan a donde el cliente ya está— y además duplicaría la
próxima reserva que el índice pinta.
▶ **La lección**: al añadir un caso a una regla existente hay que releer **qué afirma su guarda**, no
solo si sigue verde. Un test puede quedarse obsoleto sin ponerse rojo.

**(m) El paso 5 cierra la tanda 1, y convierte una regla en un TEST.** `#111` dejó escrito
*independizar el contrato ANTES de borrar*; hasta hoy eso era una nota que alguien tenía que acordarse
de leer el día de la retirada. `AccountPageCaptureTest` lo hace ejecutable: inventaría lo que la página
«Mis reservas» pinta y lo clasifica en tres —lo que la API **ya publica** (se asevera equivalencia,
para que no se pierda antes del borrado), los **HUECOS con nombre** y lo que está fuera **a propósito**—
y su lista de huecos **solo puede encoger**. ▶ **La lista vacía es la condición 1 de la tanda 3**: ya no
hay que recordar qué faltaba, el test lo enumera.

**Cuatro huecos medidos**, todos del desglose financiero: el resto de la señal **por producto**, el
desglose de «a cobrar en el parque» con su etiqueta, el **total final** tras los cambios —que no es
`total_cents`, porque `total` es inmutable— y el **pendiente de devolución**, distinto de `refund`.

⚠️ **Se sondea por VALOR, no por nombre de campo**, y la diferencia importa: comprobar «no existe la
clave `gate_lines`» dejaría el test verde el día que alguien la publique como `breakdown`. Se monta un
pedido con importes **únicos** y se busca el número en el cuerpo de la respuesta — da igual con qué
nombre se publique.
⚠️ **Y lo que no se puede sondear así se declara aparte en vez de fingirlo.** `pendienteDevolucion()`
exige un reembolso a medio procesar y con cualquier pedido normal vale 0: sondear con 0 daría verde
contra cualquier respuesta. A ese lo cubre la **congelación del esquema `Order`** del contrato — si
crece, hay que volver a la lista—, que es una red más floja y se dice.

⚠️⚠️ **Y el fixture costó tres correcciones, todas del mismo tipo: sondas que no medían.**
· la primera buscaba **céntimos en el HTML**, y la página pinta importes formateados;
· la segunda daba `totalFinalNeto` por publicado **porque coincidía con `charged_subtotal_cents`** —con
  una sola línea viva, la suma es esa línea—: hizo falta una segunda línea viva para desambiguar;
· la tercera sondeaba `pendienteDevolucion` **con 0**, que cualquier respuesta contiene.
▶ **Las tres las cazó la guarda de la guarda del propio test** (`test_every_probe_actually_measures_
something`), escrita antes que el fixture. Es la lección de `#63` aplicada por adelantado: sin ella,
tres sondas inertes habrían dejado la lista de huecos «verificada» sin verificar nada.

**(n) Antes de abrir la tanda 2: la política de contraseñas estaba copiada en SEIS sitios.** Al medir
qué haría falta para exponer el cambio de contraseña apareció que `Password::min(8)->uncompromised()`
estaba escrito a mano en el registro (web y API), el restablecimiento (web y API), el cambio de
contraseña de «Mi cuenta» y la contraseña explícita de `app:create-admin`. Es la misma familia que la
fórmula del rótulo de día (`#120(j)`) **con un agravante: esto es seguridad**. El fallo que impide no
es un error de programa, es una **erosión**: el día que la política suba, alguien tocará cinco de seis
y el que quede será la puerta más floja del producto — sin romper nada y sin ponerse rojo.
▶ Nace `Identity\Services\PasswordPolicy` y `PasswordPolicySingleSourceTest` la vigila. La
contraseña **generada** de `app:create-admin` sigue fuera, con el motivo que ya tenía escrito: son 24
caracteres aleatorios y comprobarlos contra Have I Been Pwned metería una llamada de red en el camino
feliz del despliegue.
⚠️ **Y `confirmed` NO entra en la política**, a propósito: repetir la contraseña es del FORMULARIO —la
API de registro no lo pide y la web sí, y las dos están en lo cierto—. Meterlo obligaría a una de las
dos a saltarse la fuente única.

⚠️⚠️ **Y el caso que vigila el anti-filtración nació INERTE.** Comprobaba
`json_encode((array) $rule)` buscando «uncompromised»; la propiedad es `protected` y no sale ahí, así
que **pasaba igual con `uncompromised()` y sin él**. Lo descubrió la mutación —no la revisión— y hoy
lo lee por reflexión. Es `#65` en su forma más pura, y van tres veces en esta fase: una aserción que
no se ha visto fallar no prueba nada, por evidente que parezca lo que afirma.

**(o) Tanda 2 · paso 6a — el patrón queda estrenado, y la web heredó una defensa sin escribir nada.**
`Identity\Services\AccountCredentials` reúne lo que era dominio dentro de `UpdatePassword` y
`LogoutOtherDevices`, devuelve un **veredicto** (`CredentialChangeResult`) como hace `PasswordLogin`, y
la API lo consume sin reescribirlo. Nacen `PUT /me/password` y `POST /me/sessions/revoke-others`.

⚠️ **Lo que de verdad justifica bajarlo al dominio se vio al terminar**: al pasar los dos componentes
por el servicio, **la web heredó el limitador de `current_password` sin tocar una línea de la web**.
De los cuatro sitios que reconfirman contraseña, dos quedaron cubiertos de golpe; los otros dos —el
perfil y el borrado— se cubrirán solos en los pasos 7 y 8, por el mismo mecanismo. Si la API hubiera
copiado la lógica, habría dos superficies con defensas distintas y una ficha de deuda que nadie cierra.

⚠️ **Tres decisiones que tienen caso propio porque podían salir al revés:**
· **la contraseña equivocada es un 422 POR CAMPO, no un 401.** En el login, un 401 es correcto; aquí el
  cliente **sí está autenticado**, y un 401 le diría «tu sesión no vale» cuando lo que pasa es que se
  ha equivocado escribiendo. Mismo criterio que el registro cuando el correo ya existe;
· **el formato se valida ANTES del limitador**: una contraseña nueva que no cumple la política no es un
  intento de adivinar la actual, y gastar intento por ella bloquearía a quien solo escribe mal;
· **un solo limitador, por (titular, IP), y no uno por IP sola como en el login.** Aquél existe porque
  un atacante prueba una contraseña contra mil cuentas **sin tener sesión**; aquí cada intento exige ya
  una sesión válida de esa cuenta. Se dice para que no se lea como un olvido.

⚠️ Y **las rutas van sin `throttle` propio a propósito**: el techo lo pone el servicio contando **solo
los fallos**. Un `throttle` de ruta contaría también los aciertos y castigaría a quien se equivoca una
vez y acierta a la segunda.

**(p) Tanda 2 · paso 6b — las dos pantallas, y otra duplicación cazada antes de crecer.** Nacen las
zonas `PASSWORD` y `SESSIONS`, el módulo `account/credentials.js` (traduce la respuesta: los tres
modos que **no** son «datos que corregir» —red caída, sesión perdida y límite— tienen salida propia) y
`stores/credentials.js`.

⚠️ **El campo de contraseña con su botón de mostrar estaba escrito DOS veces** —`LoginForm` y
`RegisterForm`, dieciséis líneas con dos `<svg>` dentro— y este paso iba a añadir **dos copias más**.
Se extrajo a `steps/PasswordInput.vue`. ▶ Y lo que hizo seguro el movimiento fue la red que ya
existía: `SidebarDomContractTest` y las dos paridades de auth comparan el **árbol renderizado**, así
que un nodo de más las habría puesto en rojo. **47 casos en verde sin tocar el manifiesto**: la
extracción no cambió ni un nodo.

⚠️ **El índice pasó a ser un DATO** (`HOME_ENTRIES`): añadir una zona es una línea, no dieciséis de
marcado. Era la promesa de §4.2 y la tanda 2 la cobró — de una entrada a tres sin escribir marcado.

⚠️⚠️ **Y tres cosas que solo aparecieron construyendo:**
· **`defineModel()` devuelve la variable; usar la prop en el `v-model` NO compila.** Lo cazó el
  **build**, no `npm run test:js` —que no compila componentes—. Es por eso que el build está en el
  gate, y por eso va ANTES que la suite;
· **el error del intento anterior se quedaba en pantalla** cuando el envío se cortaba localmente por
  «las copias no coinciden». Peor: **el primer caso de navegador daba verde** porque solo miraba que
  *hubiera* error, y lo había — el viejo. Se arregló en las dos puntas: la zona limpia lo que dijo el
  servidor al cortar, y el caso exige el texto exacto. **Mirar «hay un error» nunca distingue el nuevo
  del viejo**;
· **el aviso de «no coinciden» lo compone el SERVIDOR** con `validation.confirmed` y su atributo
  interpolado, así que dice literalmente lo mismo que la página web. Redactarlo en JS habría sido una
  segunda versión del mismo mensaje.

⚠️ **Y una trampa del andamio que costó dos ejecuciones**: el recorrido **cambia la contraseña de
verdad**, así que la pasada siguiente no podía ni iniciar sesión — y como los textos del área **solo
viajan con sesión**, todo salía vacío y parecía que el paso estuviera roto. Se restaura desde el
servidor antes de cada pasada, no desde el propio script: si algo falla antes, la restauración no
llega. Escrito en `VERIFICACION-E2E-CAJON.md` **V8**.

**(q) Tanda 2 · paso 7a — el perfil baja al dominio, y tres guardas de arquitectura mordieron.**
`Identity\Services\AccountProfile` reúne la gestión más grande de la tanda: las reglas —con la
**doble** comprobación de unicidad, que mira también el `pending_email` de OTROS—, el ciclo de
`pending_email` entero, las **dos** notificaciones y el manejo de la carrera de UNIQUE. Nacen
`PATCH /me`, `DELETE /me/pending-email` y `POST /me/pending-email/resend`.

⚠️ **La defensa de fondo, dicha entera**: el correo **no se cambia al guardar, se SOLICITA**. El
vigente sigue valiendo hasta que el titular abra el enlace del buzón nuevo. Si alguien entra en una
sesión ajena y pide el cambio, **el dueño no pierde el acceso** — y recibe un aviso al buzón viejo con
la dirección nueva **enmascarada**. Tiene tres casos con mutación.

⚠️ **Y algo que la reconfirmación protege y no era obvio**: al fallar la contraseña **tampoco se
guardan los campos «inocentes»**. Si se aplicaran igual, bastaría adjuntar un cambio de correo fallido
para editar el perfil ajeno sin saber la contraseña. Tiene caso y mutación.

⚠️⚠️ **Tres guardas de arquitectura mordieron, y las tres tenían razón:**
· **`ApiBoundariesTest`** vio `->update()` en un controlador de API y no puede distinguir un servicio
  de un modelo de Eloquent. ▶ Se **renombró el método a `apply()`** en vez de declararle una
  excepción: un método de servicio llamado `update` provoca esa confusión cada vez, y renombrarlo
  cuesta una palabra y no debilita la guarda;
· **`ModuleBoundariesTest`** vio un servicio de dominio importando `Http\Middleware\SetLocale`. ▶ La
  lista de idiomas del sitio **no es una regla de HTTP**: baja a `Platform\Services\SiteLocales` y el
  middleware la usa como alias. Si el dominio depende de la capa HTTP, deja de poder usarse fuera;
· **`MeTest`** vigila que `/me` publique una lista CERRADA de campos (`SEC-10`), y el campo nuevo
  —`pending_email_expires_at`— hubo que declararlo con su motivo.

⚠️ **La caducidad se publica en vez de dejar que el cliente la calcule**, y de paso la ventana bajó al
dominio: vivía en `EmailChangeController::HOLD_MINUTES` y la usan **tres** superficies. Mismo criterio
que las etiquetas de fecha (`#120(j)`).

⚠️⚠️ **Y dos veces esta sesión, escribir de memoria habría cambiado producción:**
· `maskEmail()` se reescribió «igual» y salía distinta —dejaba visible también la última letra—, lo que
  habría cambiado el texto de un correo que ya se envía, **sin que ningún test lo dijera**: la
  notificación recibe la cadena ya enmascarada y no comprueba su forma. Se copió mirándola, y se
  comprobó que las dos coinciden en cuatro casos incluidos los frontera;
· y al mover el método, `maskEmail` **desapareció del componente** —se la llevó un reemplazo por
  rango— con `EmailChangeController` usándola: **lo cazó la suite**, no la revisión.

⚠️ Y una mutación que **no mordió a la primera**: quitar `Rule::unique('pending_email')` dejaba el test
verde, porque la UNIQUE de la base captura el choque igual y el servicio lo traduce al **mismo 422**.
Los dos caminos acaban en la misma respuesta, así que mirar la respuesta no dice cuál actuó. ▶ La regla
se prueba ahora **donde vive**, validando directamente.

**(r) Tanda 2 · paso 7b — la pantalla del perfil, y el techo de componentes obligó al rediseño que
tocaba.** Nace la zona `PROFILE` con el ciclo del correo pendiente, `account/profile.js` (el
formulario y los minutos que le quedan al enlace) y `stores/profile.js`.

⚠️⚠️ **Lo importante pasó al llegar a 38 de 40 líneas en la sección.** `AccountSection` tenía un
`watch` con una cadena de `if` —«al entrar en pedidos, pide pedidos»— y un `computed` por cada lista.
Las dos cosas **crecían con cada pantalla**, y el paso 8 la habría reventado.
▶ **La respuesta correcta no era subir el techo: era que cada zona sepa qué necesita.** Hoy cada zona
llama a su `ensure()` al montarse y compone lo suyo; la sección **enruta y nada más**, y bajó de 38 a
**20**. Y no costó ni una petición: `ensure()` («pedir solo si no hay datos») ya garantizaba que
volver a entrar no repitiera nada. Ése es exactamente el trabajo que un presupuesto hace bien —
**provocar la pregunta**, no cobrar un peaje.

⚠️ **`account/credentials.js` se renombró a `form-outcome.js`**: no quedaba nada suyo que fuera de
credenciales —es la traducción de CUALQUIER formulario del área— y el perfil iba a ser su segunda
copia. Misma doctrina que el rótulo de día, la política de contraseñas y el campo de contraseña:
**extraer antes de la segunda copia, no después de la cuarta**. Van cinco en esta tanda.

⚠️ **Y una quinta duplicación cazada**: los tres idiomas estaban **quemados en el marcado** de
`update-profile.blade.php` con sus nombres nativos. Suben a `Platform\Services\SiteLocales::options()`
—que ya tenía la lista de códigos— y los usan la web y el cajón. Dos listas de idiomas es cómo se
acaba ofreciendo uno que la otra no reconoce.

⚠️ **`api.js` gana `PATCH` y `DELETE`**, que no tenía. Pasan por el MISMO `request()`, así que heredan
las cuatro trampas ya resueltas —la cookie, el `Accept`, el CSRF url-decodificado y el reintento del
419—: añadir un cliente aparte para dos verbos habría sido volver a pisarlas.

⚠️ Y un fallo propio que cazó el test y no la revisión: la relectura tras cancelar o reenviar usaba el
**cliente global** en vez del inyectado, porque a `run()` se le pasaba solo `{messages, auth}`. En
`node --test` eso es una petición de verdad que nadie puede doblar.

✅ **Verificado en navegador** (`V9`, 7/7). Lo que de verdad importaba mirar: tras pedir el cambio, el
campo de email **vuelve a mostrar el VIGENTE** y el aviso dice «sigues usando …». Es la señal visible
de que el titular no ha perdido el acceso a su cuenta.

**(s) Tanda 2 · paso 8 — los DOS derechos RGPD, y la tanda queda CERRADA.** Nace
`Identity\Services\AccountPrivacy` con el borrado (art. 17) y el documento de portabilidad (art. 20);
la web y `DELETE /me` + `GET /me/export` lo consumen sin reescribir nada. La purga sigue siendo
`User::anonymize()` (`RGPD-01`) y la reconfirmación, `AccountCredentials::verify()`.

⚠️ **Y con esto la web quedó con los CUATRO sitios limitados sin escribir una línea de limitador en
la web.** Era la promesa de `(o)` —«los otros dos se cubrirán solos en los pasos 7 y 8, por el mismo
mecanismo»— y se cumplió literal. Su ficha de `DEUDA.md` se cierra.

⚠️⚠️ **El export exigió un CONTRATO nuevo, y ahí está la parte de diseño que no era obvia.** La
composición vivía en `Http\Controllers\Account\AccountController`, que es capa de ENTREGA y por eso
quedaba exenta del grafo de módulos; al bajarla a Identity esa exención desaparece y el servicio
habría tenido que recorrer `Order`, `OrderItem`, `Slot` y `TicketType` a mano — justo lo que la regla
de Fase 2 prohíbe (*consultar los datos de otro módulo exige contrato*). ▶ Nace
`Booking\Contracts\CustomerOrderHistory` con su lector, hermano de `CustomerReservations` y con la
misma forma: `int $userId`, para que Booking no importe un modelo de Identity.
⚠️ **Devuelve el documento, no DTOs**, y se dice por escrito: un export de portabilidad **es** un
documento, su forma la publica `openapi/v1.yaml` y la valida Spectator contra la respuesta REAL.
Envolverlo en DTOs para aplanarlos otra vez al serializar pondría esa forma en dos sitios.

⚠️ **`GET /me/export` devuelve el CUERPO y no un adjunto.** El `Content-Disposition` de la página web
es presentación de navegador; la SPA lo convierte en fichero y un cliente nativo decide él. La guarda
que sostiene que no haya dos versiones del documento es
`MePrivacyTest::test_the_web_download_and_the_api_serve_the_same_document`: compara las dos
respuestas campo a campo quitando solo el sello de la hora.

**Y tres hallazgos que salieron de MEDIR, no de leer el diseño:**

⚠️⚠️ **1. El export publicaba un campo MUERTO desde el commit fundacional.** El bloque `tickets`
llevaba `code`, y **la tabla `tickets` no tiene esa columna** —ni el modelo un accesor—: el campo
salía `null` siempre y lo único que comunicaba era cuántas entradas hay. Decidido por el owner:
publicar lo que la entrada SÍ es (`status` e `issued_at`). **No se publica `qr_token`**: es la
credencial que la canjea en la puerta, y un export es un fichero que el titular guarda y reenvía.
▶ La regla que deja escrita: **un campo que nunca lleva valor es la familia de `#115`** —algo que se
lee como un dato y no lo es—, y congelarlo en un contrato de API lo hace permanente. Se mira antes de
publicar, no después.

⚠️⚠️ **2. El limitador de la web estaba puesto y su aviso no se pintaba en ninguna parte.** Los
cuatro componentes de «Mi cuenta» mandan el aviso del límite a la clave `_global` y **tres de las
cuatro vistas no pintaban ninguna clave global**: agotar los cinco intentos dejaba el formulario sin
hacer nada y sin decir nada. Es `#117` otra vez, y lo destapó escribir el cuarto. ▶ Nace
`<x-ui.global-errors>` y un caso por vista, **mutado uno a uno**. `login`/`register` conservan su
bloque a mano a propósito: son la referencia de las dos paridades de árbol (`#112(f)`).
⚠️ Y al escribir esos tres casos apareció que **la aserción dependía del RELOJ**: el aviso lleva los
segundos que quedan y `availableIn()` devuelve 59 en cuanto el bucle cruza un segundo. Con el reloj
parado (`#64`) los tres son deterministas — sin eso habrían sido tres rojos intermitentes nuevos.

⚠️ **3. El propio gate de componentes tenía un hueco sin declarar.** `SidebarComponentBudgetTest`
contaba `api.get|post` y **no** `put|patch|delete`: el contador se escribió cuando `api.js` solo tenía
dos verbos, y los otros tres llegaron en los pasos 6b y 7b sin que nadie volviera a mirar. Un
componente que llamara a `api.delete(...)` pasaba la guarda sin que faltara nada — `TESTING.md`
§2.quater, y encima sin declarar. Ampliado a los cinco (ningún componente los usaba, así que la
baseline no se movió) **y con una guarda de la guarda** que enumera los verbos.

⚠️ **Cuatro duplicaciones más cazadas antes de crecer**, que ya van diez en la tanda: la traducción
del veredicto de credenciales a HTTP (`TranslatesCredentialVerdicts`, iba a ser la segunda copia), el
guardián de los formularios del cajón (`account/form-run.js`, iba a ser la **tercera**), la clave del
aviso de despedida (`AccountPrivacy::FAREWELL_STATUS`, la usan las dos superficies) y el banner de
errores globales. La regla sigue siendo la misma: **antes de la segunda copia, no después de la
cuarta**.

⚠️⚠️ **Y una guarda que faltaba y este paso podía haber incumplido**: dentro del cajón **no hay URL**
(§3.4), así que el índice es la ÚNICA puerta a una zona. Declararla en `ZONES` y olvidarla en
`HOME_ENTRIES` la deja **inalcanzable** — código muerto que pasa todos los tests de navegación y al
que ningún cliente llega. Ahora hay un caso que recorre `ZONES` entero y lo exige.

✅ **Verificado en navegador** (`V10`, **17/17**), y ahí es donde apareció lo que ninguna suite vio:
**tras borrar la cuenta desde el cajón, la home salía MUDA**. La web termina en
`redirect('/')->with('status', 'account-deleted')` y el layout pinta ese aviso; el cajón sale a `/`
por su cuenta, así que sin dejar la despedida en la sesión NUEVA el titular aterrizaba sin saber si
su cuenta se había borrado de verdad. ▶ Se deja **después** de `invalidate()` —que vacía la sesión—,
tiene caso propio y su mutación (ponerlo antes) lo tumba.
⚠️ **Y dos de los tres rojos de la primera pasada eran del ANDAMIO, no de la app**: el caso del
borrado esperaba `waitForURL('/')` **estando ya en `/`**, así que la espera se cumplía al instante y
la comprobación siguiente medía el estado de antes. Es `#115` dentro del propio guion de
verificación. Se entra desde `/entradas` para que la navegación sea real.

**(t) Tanda 3 · pasos 9, 10 y 11 — las CONDICIONES DE ENTRADA de la retirada, cumplidas.** La tanda 3
no empieza borrando: `#111` dejó escrito *independizar el contrato ANTES de borrar*, y
`AccountPageCaptureTest` lo había convertido en una lista ejecutable de **cuatro huecos**. Estos tres
pasos la vacían.

**Paso 9 — el desglose financiero baja al contrato.** `Order` publica cuatro campos nuevos:
`pending_at_gate_lines` (el desglose de puerta, línea a línea y **con su etiqueta ya compuesta**),
`total_final_cents` (lo que el cliente acaba pagando, que **no es** `total_cents` en cuanto hay una
cancelación), `pending_refund_cents` (lo que aún se le debe, que **no es** `refund`, lo ya devuelto) y
`has_deposit`.

⚠️ **La etiqueta del resto de la señal se componía en BLADE**, juntando dos claves de `lang/` en la
plantilla. Publicarla por la API habría hecho que esa fórmula viviera en dos sitios — el mismo
mecanismo por el que divergieron las cuatro copias del rótulo de día (`#120(j)`). Nace
`Order::gateBreakdownLines()` como fuente única y la consumen la página y el contrato.

⚠️ **`has_deposit` NO se deduce de que quede algo pendiente**, y tiene caso frontera propio: un pedido
con señal **cuya franja ya pasó** tiene el resto cobrado en recepción —nada pendiente— y **sigue
siendo** un pedido con señal. Sin ese caso, publicar `pending_at_gate_cents > 0` en su lugar habría
pasado toda la suite.

⚠️⚠️ **Y el cuarto hueco, que el test declaraba «no sondeable», resultó sondeable.**
`pendienteDevolucion()` vale 0 contra cualquier pedido normal, y por eso `AccountPageCaptureTest` lo
dejó fuera de sus sondas. Lo que faltaba no era una sonda mejor: era **una fila de `payments`
pagada**. Con el pago delante, el dinero de una línea cancelada sigue retenido y el campo mide.
▶ La lección: *«no se puede sondear»* suele querer decir *«el fixture no reproduce el escenario»*.

**`AccountPageCaptureTest` se BORRA, y lo pedía él mismo** —«si ya no quedan huecos, borra este
fichero: su trabajo terminó»—. Lo releva `AccountFinancialParityTest`, temporal igual que aquél, con
la mitad que NO terminó: mientras la página siga viva hay **dos superficies enseñando el mismo
dinero**, y nada garantizaba que dijeran lo mismo.

⚠️ **Y ahí una mutación destapó una comparación tautológica**: el caso del ORDEN comparaba la API
contra **el mismo helper** que la alimenta, así que reordenar el helper movía las dos a la vez y
pasaba. La referencia del orden pasa a ser el HTML de la página, con las posiciones de cada etiqueta.
Es la misma familia que `#115`: algo que se lee como una comprobación y no lo es.

**Paso 10 — el ledger en el cajón.** `financialsOf()` decide qué líneas se enseñan y con qué rótulo
—«Subtotal» o «Total» arriba, y cuál de las dos leyendas acompaña al desglose— y **no calcula ni un
importe**: los seis los publicó el paso 9. El desglose entra plegado, como en la web.

**Paso 11 — los consentimientos**, que era el hueco que este trabajo descubrió y no estaba en la
lista. `GET /me/consents` y la lista dentro de la zona `PRIVACY`.
⚠️ **Sin la IP**, igual que la página: es parte de la prueba del art. 7.1 y viaja en el export, que es
un acto explícito del titular. Publicarla en una lista que se pinta sola sería añadir un dato técnico
a la pantalla sin decirle nada nuevo.
⚠️ **El rótulo del documento lo publica el SERVIDOR** (`type_label`) para que el cajón no lleve una
segunda tabla de cuatro nombres que envejece sola al añadirse un quinto tipo. Y **un tipo desconocido
devuelve su identificador, nunca la clave cruda ni cadena vacía**: tiene caso propio porque con los
cuatro tipos conocidos las tres implementaciones coinciden.

**Dos decisiones del owner, tomadas al medirlas:**
· **«Cerrar sesión» NO entra en el índice del cajón**: el botón ya existe dos veces fuera —en el nav y
  en el bloque `.acct` del propio panel, siempre a la vista— y una tercera entrada llevaría a donde el
  cliente ya puede ir con un clic. Retirar la página no pierde nada;
  ⚠️⚠️ **CORRECCIÓN DE HECHO (2026-08-23): «dos veces» era FALSO — existe UNA.** Medido con
  `git grep "route('logout')" -- ':!tests' ':!docs'`: **un solo resultado**, el del bloque `.acct`.
  La rama con sesión de `nav.blade.php` es un `<button>` que solo abre el cajón: ni `href` ni salida.
  **La decisión no cambia** —una tercera entrada seguiría siendo redundante— pero su premisa sí, y
  con ella el riesgo: la única salida de sesión del sitio vive dentro de un cajón que solo abre con
  JavaScript. Lo destapó la revisión adversarial de `specs/account-context-vue.md` §4.8, que es donde
  se resuelve;
· **los consentimientos SÍ se publican y se pintan**: sin ellos, el borrado le quitaría al cliente la
  prueba visible de a qué dijo que sí.

✅ **Verificado en navegador** (`V11`, **22/22**): el cajón y la página enseñan los **mismos seis
importes** del mismo pedido, el desglose entra plegado y sale con sus dos líneas, y los
consentimientos salen con su nombre y su fecha **y sin la IP**.
⚠️ Y otra vez un rojo del ANDAMIO y no de la app: el guion buscaba `.orders__card` —la clase del
cajón— en la PÁGINA, que usa `.orders__item`. Son dos marcados distintos **a propósito** (§1.3: aquí
la paridad es de datos, no de árbol), y confundirlos dio seis rojos que parecían una divergencia de
importes.

**(u) Tanda 3 — `/mi-cuenta/…` RETIRADA: mueren las VISTAS, viven las RUTAS.** Con las condiciones de
entrada cumplidas en `(t)`, se borran `resources/views/account/`, los **cuatro** componentes Livewire
de cuenta y `RetryPaymentController`. Las **rutas** siguen: sirven la home y el cajón se abre solo en
su zona, que es el mecanismo de `/entradas` desde la Fase 5.2 — no se inventa nada. El mapa
ruta→zona vive en `Http\Sidebar\AccountDoor`, con un test que cruza sus zonas contra las de
`navigation.js`: una errata ahí no rompería nada visible y el cliente aterrizaría en otra pantalla.

⚠️ **Por qué las rutas no se borran, dicho con números**: **8 notificaciones ya entregadas** apuntan a
`account.orders` —un correo enviado no se puede editar— y **11 redirecciones del servidor** aterrizan
en `account` con un `->with('status', …)` que el layout pinta. En crudo, todo eso sería un 404.

**LA AUDITORÍA FUE LA MITAD DEL TRABAJO, y encontró DOS huecos reales.** `CONVENCIONES §3.quater`
manda clasificar cada test por su SUJETO y buscar activamente «el tercero»: lo que usaba la superficie
vieja como intermediario de una regla que sobrevive. Se hizo MUTANDO, y salieron dos cosas que nadie
vigilaba:

⚠️⚠️ **1. El reintento de pago de la API no tenía techo vigilado.** La ruta lleva `throttle:6,1` desde
que se abrió y su único guardián era un test de la página que iba a morir. **Medido por mutación: al
quitarle el middleware, la suite ENTERA seguía verde.** Cada intento abre un cobro real contra la
pasarela, así que el hueco no era teórico. Hoy tiene caso propio en `OrdersTest`.

⚠️⚠️ **2. La API publicaba las líneas FANTASMA que las otras tres superficies ocultan.**
`Order::isVoidedLeftoverItem()` —un item cancelado que nunca se cobró ni se reembolsó— lo aplicaban la
página, el panel y el PDF: **todas menos el recurso de API**. El cajón enseñaba líneas net-cero
(«0,00 € · Cancelada») que el producto decidió esconder por confusas. Es literalmente el criterio de
§3.quater: *si un dato viaja al cliente y su único test conduce la superficie vieja, el contrato NO lo
está fijando*.

**Y un TERCER hallazgo, que decidió el owner**: la página pintaba en línea las **respuestas del pack**
(«Nombre del homenajeado/a: …») y el cajón no —la API las excluye de la lista a propósito, art. 9—.
Sobrevivían en el post-form… **salvo si un pack tiene campos de evento y no tiene post-form**.
▶ Se cerró: el cajón las pide **bajo demanda** con `GET orders/{code}/event-data`, que es exactamente
para lo que ese endpoint existe. Se piden una vez por pedido y **solo si el titular las despliega**;
no viajan con la lista y no se persisten.

**Qué se retiró de la suite, y con qué criterio.** 63 casos menos, ninguno perdido por descuido:
· **mueren** los que conducían la vista (la página de pedidos, su paginación, sus distintivos);
· **se re-apuntan** los que usaban la superficie vieja como intermediario — y ahí está lo importante:
  las **tres vías de `RGPD-06`** que pasaban por los componentes Livewire (`ApiTokenRevocationTest`),
  el bloqueo del reintento por pausa, y los dos contadores de `ModuleContractsTest`, que bajan de 3
  puertas a 2 y de 2 reintentos a 1;
· **se extraen** los que afirmaban del dominio: `Order::canBeRetried()` y `displayStatus()` nacen como
  `OrderRetryEligibilityTest`, y la mitad API↔dominio del desglose financiero como
  `MeOrdersFinancialsTest`. Los dos ficheros temporales de captura y paridad mueren con la página,
  como decía su primera línea.

⚠️ **`AccountAccessTest` cambia de sujeto sin cambiar de sitio**: antes comprobaba que la página se
pintaba, ahora que la puerta **sigue siendo zona privada** y **abre en su zona**. Y que **ninguna otra
página emite zona** — un `data-account-zone` colgado en toda la web abriría la cuenta en cada apertura
del cajón, que es la trampa que `SidebarEntry` pagó en 4.0a.

✅ **Verificado en navegador**: `V12` **13/13** (las puertas) y `V13` **6/6** (las respuestas bajo
demanda).

⚠️⚠️ **Y el navegador volvió a cazar lo que ninguna suite vio, en el mismo camino de siempre**: la
primera versión aplicaba la zona en `open()`, y **el cajón que llega por una puerta NACE ABIERTO**, así
que `open()` no se llama nunca — el cliente que venía de un correo aterrizaba en el índice en vez de
en sus reservas. Es literalmente el camino que dejó el hueco vacío en `#59(b)`, documentado en el
propio `app.js` tres párrafos más abajo de donde estaba el fallo. Hoy la zona se aplica en
`bootSpaEngine()`, que es por donde pasan los DOS caminos, y `AccountDoorWiringTest` lo ancla ahí con
tres mutaciones.
▶ **La lección, que es la de toda la fase**: leer el aviso no es lo mismo que aplicarlo. Estaba
escrito, y aun así se repitió.

⚠️ **Los dos presupuestos, bajados a lo medido al cerrar**: payload del montaje **4.708 B** (techo
4.800) y chunk **189,5 KiB** (techo 190, con 0,5 de holgura). El área de cliente entera —tres tandas,
siete pantallas, once endpoints— ha costado **27,9 KiB** sobre el cierre de la reorganización.

## #121 · 2026-08-22 · El barrido de cierre: una invariante llevaba un día citando un test muerto

Cerrando la sesión de la tanda 3 se hizo una comprobación que no estaba en el protocolo: **que cada
test citado en `INVARIANTES.md` exista**. Un `grep` de nombres de clase contra `tests/`, dos líneas.

**Encontró dos, los dos en `PAY-04`** —el reintento de pago: supersede, extensión atómica del hold y
el ORDEN entre ambos—: `PurchaseRetryAndPollingTest`, que murió con `Purchase.php` en `#112`
(2026-08-21), y `PaymentRetryFromOrdersTest`, con la página en `#120(u)` (hoy).

⚠️ **La cobertura estaba intacta**: los tres guardianes de dominio siguen ahí y la superficie la
cubren `Api\V1\OrdersTest` y `Api\V1\PaymentStatusTest`. Lo que estaba viejo era **la cita**. Pero esa
es exactamente la forma en que una invariante se queda sin red sin que nadie lo note: el día que
alguien retire uno de los que sí existen, la columna seguirá nombrando cinco y solo habrá tres.

⚠️⚠️ **Y por qué el gate no lo vio**: `docs-check` valida **rutas de fichero** citadas
(`app/…/Foo.php`) y **no nombres de clase sueltos** (`FooTest`). Es la ficha de `DEUDA.md` que dice
que el gate valida estructura y citas, no contenido — con un caso medido encima.

▶ **La regla que deja para el protocolo de cierre**: al RETIRAR una superficie, el barrido de
`§3.quater` no termina en los tests; hay que mirar **quién los nombraba**. Un test borrado deja dos
huellas: la que el gate ve —su fichero— y la que no —su nombre, escrito en la columna «Verificación»
de una invariante—. La segunda es la que importa.

▶ **Y es barato convertirlo en gate**: el mismo `grep` que lo encontró vale como comprobación
mecánica. Queda anotado como el candidato más concreto de la ficha de `docs-check` en `DEUDA.md`.

## #122 · 2026-08-23 · [DECIDIDO] La AUTH entra en el cajón — y el diseño lo cambió su REVISIÓN, no su autor

El owner elige, de los tres candidatos que dejó abiertos el cierre del área de cliente, **traer la
auth dentro del cajón y retirar el modal de la cabecera**: es el último trozo de `#66`. El diseño
está en `specs/auth-en-cajon.md`; aquí quedan las decisiones y lo que costó averiguarlas.

**(a) Las tres decisiones de PRODUCTO, del owner.** **1)** Quien entra o se da de alta desde la
cabecera aterriza en **el índice de Mi cuenta**, no en el embudo ni cerrando el cajón. **2)** El paso
5 del embudo **gana «he olvidado mi contraseña»** con vuelta a la compra: hoy no lo tiene —era una
rama `@unless ($embedded)`— y quien compra sin recordar su contraseña **tiene que abandonar el
cajón**, perdiendo de vista la cesta. **3)** El `noindex` que faltaba se cierra ya, aunque sea ajeno.

**(b) El servidor no pone un solo obstáculo, y conviene decirlo porque no siempre es así.**
`POST /api/v1/auth/password/forgot` y `/reset` existen desde Fase 3 · paso 3c sobre
`Identity\Services\PasswordRecovery` —**el mismo servicio que consume el modal**—, con su limitador,
su no-enumeración y su rotación de credenciales. Comparados los tests de API con los del componente
uno a uno: **ninguna regla de dominio vive solo en los tests de Livewire**. Lo que falta es pantalla.

**(c) Un HUECO vivo, cerrado el mismo día: tres URLs de auth `noindex` por ACCIDENTE, y dos que ni
eso.** El `<meta robots>` de `/login`, `/registro` y `/recuperar-contrasena` no lo pone nadie a
propósito: **es un efecto lateral del prop `authModal`**, el mismo que decide si se pinta el modal. O
sea que retirar el modal —el objetivo de este trabajo— habría entregado tres URLs de auth al índice
de Google **sin que ningún test fallara** (`grep -rn noindex tests/` daba **un** resultado, y era del
503 de mantenimiento).
▶ Y al fijarlo salió lo de al lado: **`/restablecer-contrasena/{token}` —una URL con un token de
restablecimiento dentro— y `/email/verificar` se servían `index, follow`**, porque ponen `authModal`
a `null`. Medido sobre HTTP real, no deducido.
▶ Cerrado con `SeoTest::test_the_auth_doors_are_never_indexable` sobre **las cinco** superficies, con
**control negativo** (la home sigue `index, follow`) y su gemelo del sitemap. **Medido por mutación
en las dos direcciones**: quitar `$authModal` tumba el caso; forzar `noindex` en toda la web tumba el
control. Sin ese control, un layout que no distinguiera pasaría con matrícula.
▶ **La lección**: *una conducta que existe por efecto lateral no tiene dueño*. El día que se retire la
causa, se va con ella. Es la familia de `#89`/`#93` —un dato que viaja al cliente cuyo único test
conduce la superficie vieja— y de `#112` —una guarda en verde que dejó de medir—.

**(d) La revisión adversarial cambió el diseño de fondo, y ese es el titular.** `CONVENCIONES §5`
pide que otro agente revise la spec antes de escribir código. Aquí no fue un trámite: encontró **dos
bloqueantes** que la habrían hecho fracasar en el navegador.
· **Los textos del área viajan SOLO con sesión** —el montaje los envuelve en `auth()->check()`, igual
que `locales`— y el cajón los recibe como props estáticas de esa carga. Quien consigue sesión DENTRO
del cajón no recarga: aterrizaría en el índice con **el título y las seis entradas en blanco**,
porque `i18n.js::t()` devuelve `''` cuando falta la clave. Es literalmente el fallo que
`account/navigation.js` documenta. ▶ **Resuelto navegando a `route('account')`**, que ya es puerta:
la página se recarga con sesión y el cajón **nace abierto** en el índice. Cero API nueva, cero bytes
en las demás páginas. La alternativa —mandar el grupo `account` a todo el mundo— costaba **~660 B en
cada página pública y para cada anónimo**, contra un presupuesto que está a 92 B de su techo.
· **`register.js` lleva `context: 'purchase'` QUEMADO**, y ese campo es el que hace que el servidor
no mande verificación e inicie sesión (`notifyByEmail: ! $inPurchase`). Reutilizar el formulario «tal
cual» habría convertido **el alta suelta en pay-first** sin que nadie lo decidiera. ▶ `context` pasa
a ser parámetro.

**(e) Y una corrección de la propia spec, medida antes de que llegara la revisión.** La primera
versión daba por muertos los **14** casos de las dos paridades de árbol. Son **6**: los otros ocho
tienen sujeto propio —cinco fijan el **payload del montaje**, uno el bit del anti-bot, uno el señuelo
(100 % API), y otro está partido en dos mitades—.
⚠️⚠️ **Uno de esos cinco es el que más duele**: el **techo del payload del montaje** —4.708 B medidos,
techo 4.800— **vive dentro de `SidebarLoginParityTest`**, no en un fichero de presupuestos. Borrar el
fichero se habría llevado el único guardián de lo que viaja en el HTML de **todas** las páginas
públicas, y nada habría fallado.
▶ **Y de ahí salió una dependencia invertida en el orden**: ese caso asevera que el payload del
invitado son exactamente `login` y `register`, así que **añadir los textos de recuperar lo pone en
rojo**. La mudanza va PRIMERO (paso A2), no al final. Con el orden anterior, la suite habría estado
en rojo desde el segundo paso hasta el penúltimo — la situación en la que un rojo deja de decir nada.

**(f) Tres guardas más caen con el borrado, y ninguna estaba en el inventario inicial**:
`ScrollLockOwnerTest` (itera la llave `auth`), `SpinnerTest` (monta `Livewire\Auth\Login`) y
**`SidebarEntry::clear()`, que se queda sin su ÚNICO llamante** —`Livewire\Auth\Login::login`—: es la
defensa de que a Bob no le aparezca el «pago denegado» de Alice en un dispositivo compartido. Se
decide explícitamente, no se pierde por descuido.

**(g) Lo que NO se toca, y por qué.** `/restablecer-contrasena/{token}` y `/email/verificar` siguen
siendo **páginas**: se llega desde un correo, con token en la URL, y el cajón no es direccionable
(`specs/area-cliente.md` §3.4). Las tres rutas puerta **sobreviven** —`route('login')` es el destino
del middleware `auth` de Laravel—. Y `account-context` sigue en Livewire: es la otra ficha de
`DEUDA.md`.

---

### #122 · EJECUTADO (A1–A10, 2026-08-23) — y lo que la ejecución encontró que el diseño no vio

El corte de `specs/auth-en-cajon.md` §8 se recorrió entero en el día. Lo que sigue **no repite el
diseño**: recoge lo que solo apareció al construirlo y al medirlo, que es lo que tiene valor para
quien venga después.

**(h) ⚠️⚠️ UN HUECO DE SEGURIDAD VIVO, destapado por la auditoría de tests.** Que entrara OTRA persona
**no descartaba el desenlace de pago de la anterior**. `SidebarEntry` vive en SESIÓN y
`Session::regenerate()` **conserva los datos**, así que en un dispositivo compartido Bob se encontraba
el cajón abierto con el «pago denegado» de Alice y su código de pedido.
▶ La defensa vivía **solo en `Livewire\Auth\Login`** y su único guardián era un test que se iba con
él: la mutación tumbaba **un** caso de toda la suite. **El login de la API nunca la tuvo, y es el que
usa el cajón desde 4.4a·2**, así que el hueco llevaba abierto desde entonces. El propio controlador lo
daba por sabido en un comentario —«el sidebar Livewire hace lo mismo, más lo suyo con la cesta»— sin
que nadie lo leyera como el hueco que era. Arreglado en `AuthSessionController::login` con su control
negativo, y medido por mutación.
▶ **La lección**: *una defensa que solo tiene una superficie no es una defensa del sistema*. Y su
corolario para retirar código: **antes de borrar un test, mide qué caza; no leas qué dice**.

**(i) Dos huecos más, ambos en la superficie que SOBREVIVE.** La **validación del correo** de
`POST /auth/password/forgot` no la comprobaba nadie —relajarla a `sometimes` dejaba los 96 casos del
alcance en verde, y el endpoint habría contestado 202 «revisa tu correo» a quien no escribió ninguno—;
y el **escape «¿ya tienes cuenta?»** de la pantalla de verificación del cajón tampoco: `SidebarMountTest`
asevera que el texto VIAJA en el payload, **no que la pantalla lo pinte**, que es exactamente el hueco
de los 20 iconos vacíos (`#113`).

**(j) El método de la auditoría, que es reutilizable.** Los 36 casos no se clasificaron leyendo: se
**mutó cada regla de dominio compartida** —quince mutaciones sobre los tres servicios de Identity, la
notificación y los dos controladores— y se apuntó **qué ficheros la cazaban**. Redundante con la API →
muere; único → es el guardián y se re-apunta. Resultado: **32 mueren, 3 se re-apuntan, 1 no guardaba
nada**. La suite baja de 2690 a 2648, −42 exactos.
⚠️ **Y el andamio también hubo que medirlo**: la primera tanda no aplicó ni una mutación, porque
`\Q…\E` de perl protege los metacaracteres **pero no impide la interpolación**. Lo delató el `grep` de
comprobación. *Un mutador que no muta da el mismo verde que un test que no mide.*

**(k) Tres presupuestos hicieron su trabajo, y uno cambió de criterio.** El techo de componentes paró
la zona de alta en **43 de 40** líneas y la respuesta correcta no fue subirlo (`#120(r)`): fue mudar la
secuencia al store, donde se prueba con `node --test`; bajó a 25. El del chunk subió **190 → 199 KiB**
y el del payload **+923 B**, siempre con su medida.
▶ **El criterio nuevo**: este trabajo subió los techos **paso a paso**, no por adelantado para toda la
tanda. Cuesta un rojo más por paso y a cambio **ningún tramo queda sin guardia** — que es lo que pasaba
con el patrón anterior entre la subida preventiva y la bajada del cierre.

**(l) Dos fallos propios, y los dos los cazó el propio método.** Una guarda aseveraba «el HTML contiene
el `href`» y una mutación que se lo quitó al CTA de escritorio **pasó en verde**, porque el del cajón
móvil lo conservaba (ancla no única, trampa 3 de `§3.quater`). Y `resendGate()` recibía el store entero
cuando esperaba otros nombres de campo: **ignoraba la cuenta atrás sin fallar**. Hoy esa función
**falla cerrada** — un campo que no es un número deja el botón deshabilitado, no habilitado.

**(m) Lo que «volver» significa, que no era una sola cosa.** La pantalla de recuperar contraseña se
alcanza desde tres sitios y cada uno necesita algo distinto: desde entrar deshace la pila; desde una
**puerta por URL** hay que sembrar `LOGIN` debajo —sin eso, «volver a iniciar sesión» sacaba al
**catálogo de compra**—; y desde el **paso 5 del embudo** la pila queda vacía **a propósito**, porque
de donde viene no es una zona y «volver» tiene que devolver la compra donde estaba, con su cesta.
▶ La regla vive en `account/navigation.js::parentZoneFor()` y **la decide quien llama**, no `enter()`.

**(n) El barrido de citas de `#121` volvió a pagar.** `INVARIANTES` SEC-06 nombraba dos tests que
dejaban de existir; **tres tests más** no estaban en el inventario porque el `grep` de `$store.auth` se
hizo sobre `resources/` y no sobre `tests/`; y siete citas de código y doc describían un modal
retirado. ▶ **Ampliación de la regla**: el barrido se hace sobre **todo el repo**, no sobre las
carpetas donde uno espera encontrarlo.

**(o) Consecuencia que hereda quien siga.** `account-context` es ahora el **ÚNICO** componente Livewire
que renderiza el layout, así que la redundancia que hace llegar `livewire.js` —y con él Alpine, y con
Alpine el cajón entero— **cuelga de él**. El día que migre a Vue, `@livewireScripts` pasa a ser la
fuente única y retirarla deja la web sin cajón. Anotado en su ficha de `DEUDA.md`, en el layout y en
`SidebarMountTest`.

## #123 · 2026-08-23 · [DECIDIDO] El bloque de cuenta pasa a Vue — y lo que encontró vale más que lo que se migró

**Qué se decidió** (owner): migrar `account-context` —el ÚLTIMO componente Livewire del layout— a Vue,
en la forma **«solo Vue»** (sin conservar el Blade bajo paridad) y con un **endpoint nuevo**
`GET /api/v1/me/account-context`. Diseño en `docs/specs/account-context-vue.md`.

⚠️ **La revisión adversarial paró la v1 del diseño, y con razón.** Tres revisores independientes:
*sólida-con-cambios · sólida-con-cambios · **INSUFICIENTE***. Cuatro afirmaciones del autor no salían
del código, el inventario de guardas se dejaba la mitad —incluido el **guardián único de
`identifying`**— y **no vio el efecto colateral más grave del trabajo**. La lista completa, en §8.1 de
la spec.

**Los DOS hallazgos que justifican la sesión, y ninguno es del trabajo pedido:**

· ⚠️⚠️ **El `no-store` de TODAS las páginas web lo ponía un ACCIDENTE de Livewire.** No lo emitía
  ningún middleware del proyecto: `SupportDisablingBackButtonCache::boot()` es un hook de componente
  que enciende un flag, y un middleware global del paquete estampa la cabecera **solo si un componente
  Livewire llegó a renderizarse**. Retirar el último la habría borrado del sitio entero — con la suite
  en verde, porque **ninguna de las 12 aserciones de `no-store` miraba una página web del layout**.
  Medido A/B: con componente `…no-store, private`; sin él, `no-cache, private`. Nace
  `NoStoreWebResponses` (**global**, con la puerta de `/api/v1` para no romper la caché del catálogo
  anónimo) y `NoStoreWebResponsesTest` con su **tabla de verdad de cinco combinaciones**, que deja
  escrito que hasta hoy el caso pasaría igual sin el middleware.

· ⚠️⚠️ **`route('logout')` aparece UNA sola vez en toda la aplicación**, y era el bloque que se
  retiraba. Eso desmiente la premisa escrita de `#120(t)` («ya existe dos veces fuera, en el nav y en
  el bloque»): existe una, y está dentro de un cajón que solo abre con JavaScript. **El owner descartó
  subirla al nav** —`#66`: toda la gestión del cliente vive dentro del cajón— y el suelo va **dentro
  del hueco**: el servidor pinta ahí el formulario de salir y nada más, colapsado, y solo se ve si el
  motor no llega.

**Y un tercero, ajeno a este trabajo:** `SidebarIconParityTest` estaba **ciego a 10 de los 32 `.vue`**
—las diez zonas del área de cliente— porque `**` no es recursivo en el `glob()` de PHP. Es el modo de
fallo exacto de `#113`, en el fichero que existe para impedirlo. Arreglado, con guarda de la guarda.

**Cómo se hizo**: siete pasos por DEPENDENCIA (spec §6.1), cada uno con su medición y su mutación. Lo
que la ejecución encontró y no estaba en el diseño vive en la propia spec (§6.0); no se repite aquí.

⚠️ **Lo que este trabajo NO compra, y hay que decirlo**: no ahorra `livewire.js` —Alpine lo trae
Livewire— así que la directiva se queda. Y no elimina «la frontera», elimina **una de las dos**: la
costura Vue↔Alpine sigue viva y aquí gana un escritor.

**Lo medido, de un vistazo**: chunk del motor **198,77 → 207,21 KiB** (techo 208) · HTML de la home
anónima **−1.412 B por visita** · techos de texto **2.688 → 3.200** y **5.720 → 6.272**, los dos con su
medida · suite **2669** y **624 tests JS** · **0 atributos `wire:`** en la página. Validado por el
owner en navegador (`VERIFICACION-E2E-CAJON.md` §5.sexies, `V20`–`V22`).

## #124 · 2026-08-23 · El PULIDO del cajón — cuatro de los nueve puntos no eran cosméticos

**Qué pidió el owner**, tras validar el relevo en navegador: nueve retoques de interfaz del cajón.
**Qué resultaron ser**: cuatro fallos reales, y tres de ellos de la misma familia que este repo ya ha
pagado —una pieza que se emite y nada la recoge—.

⚠️⚠️ **El bloque de cuenta reaparecía al reabrir el cajón** (punto 3). `close()` escribía
`this.mode = 'catalog'` directamente en el store de Alpine, **saltándose el puente del motor** que es
su dueño único. Al reabrir, el panel decía «catálogo» mientras el cajón seguía en «mi cuenta» o en el
paso de la fecha, y **el `watch` no lo corregía**: no había cambiado nada reactivo. Era herencia del
motor Livewire, donde reabrir provocaba un round-trip que re-publicaba el modo — y el propio `close()`
llevaba escrito desde 4.0a por qué `identifying` NO se resetea ahí. A `mode` nadie se lo aplicó.

⚠️⚠️ **«Mis reservas» se servía SIN TARJETAS, y privacidad con sus dos derechos pegados** (puntos 2 y
4). No había que diseñar nada: **la transcripción a Vue inventó nombres de clase** —`orders__card`,
`orders__pagination`, `bk-error`, un `auth` de envoltorio— y **ninguno tenía una sola regla en ninguna
hoja**. Las reglas existían desde siempre con el nombre de la página que el cajón sustituyó
(`orders__item`, `pagination`, `auth__errors`, `account__card`). Y **ningún gate podía verlo**: el
contrato de árbol compara ESTRUCTURA, y un nodo con una clase muerta es idéntico a uno con la buena.
▶ Nace `SidebarStyleWiringTest`: **toda clase que el cajón emite tiene una regla que la vista**. Es el
`#113` aplicado al CSS. Encontró **seis huecos más en el EMBUDO**, anteriores a este trabajo, que
quedan **declarados con nombre y motivo** en vez de tocados: son pantallas ya validadas.

⚠️⚠️ **El scroll del cajón arrastraba la página** (punto 9). Dos causas, y arreglar una sola deja el
síntoma: el bloqueo era solo `body.no-scroll { overflow: hidden }` —insuficiente cuando quien scrollea
es el DOCUMENTO, que es lo normal en táctil— y el gesto **se encadenaba** al llegar al tope.
`overscroll-behavior` **no aparecía ni una vez en todo el repo**. Se respeta el dueño único: una
clase, un escritor, dos elementos.

⚠️ **El título de auth salía dos veces** (punto 7): esas pantallas reutilizan el formulario del paso 5
—que trae su encabezado porque allí no hay armazón— y el armazón ponía además el de la zona, con el
MISMO literal. La regla vive en `navigation.js::bringsOwnHeading()`, no como un `v-if` que envejece.

**Y lo que sí era interfaz**: los TRES botones del bloque —reservas · cuenta · salir **solo icono**,
con su `aria-label`, sin peso visual y al final porque es la única acción de la que no se vuelve—;
**cinco iconos en el índice**, con **cuatro componentes nuevos** del sistema de diseño (`calendar`,
`lock`, `devices`, `shield`) y las copias del cajón **generadas desde el render real de Blade**, no a
ojo; un **spinner** en las cuatro zonas que piden datos —el velo estaba diseñado y el área nunca lo
cableó—; y las pestañas de auth **ocultas durante la verificación**.

⚠️ **Una decisión que NO se tocó**, y conviene decirlo: que salir de la pantalla borre el correo
pendiente es **deliberado y está defendido por escrito** (es PII de alguien que puede no ser el
siguiente en usar el dispositivo). Lo que se retiró es la forma **accidental** de dispararla — las
pestañas —, no la defensa. La salida deliberada sigue dentro de la propia pantalla.

**Lo medido**: suite **2678** · **633 tests JS** · chunk **207,3 → 210,8 KiB** (techo 211, con su
ledger) · textos del montaje **3.137 B** anónimo (techo 3.200) y **6.160** con sesión (techo 6.272).
Cada arreglo con su guarda y **verificado por mutación**.


## #125 · 2026-08-23 · [DECIDIDO] Dos fallos que solo el navegador podía ver — y una guarda bien escrita que miraba el número equivocado

**De dónde salen**: de recorrer `V17` y `V23`, los dos casos del guion de navegador que `#122` y `#124`
dejaron marcados como «no recorrido» (`VERIFICACION-E2E-CAJON.md` §5.quinquies y §5.sexies). **60
comprobaciones en navegador, 58 en verde y 2 rojas.** Las dos rojas eran fallos reales.

### 1 · El botón de reenviar prometía correos que el servidor tiraba

**Medido, dos veces idéntico**, con la bandeja de Mailpit como único oráculo:

    reenvio 1 a los  32s — HTTP 202 — bandeja 1 -> 2
    reenvio 2 a los  63s — HTTP 202 — bandeja 2 -> 2   <<< NO SALIÓ NINGÚN CORREO
    reenvio 3 a los  93s — HTTP 202 — bandeja 2 -> 3
    reenvio 4 a los 123s — HTTP 202 — bandeja 3 -> 3   <<< NO SALIÓ NINGÚN CORREO

`SelfSignup::resendVerification()` aplica **DOS** cooldowns —por IP (30 s) y por CORREO destinatario
(60 s)— y `verify.js` espejaba el de IP, **el más corto**. El endpoint responde **202 mande o no
mande** (`SEC-06`), así que el titular pulsa, lee «reenviado», **gasta uno de sus cuatro reenvíos** y
no le llega nada. Quien no recibe el correo puede quemar la mitad de sus intentos en vano y acabar en
«has alcanzado el límite de reenvíos».

⚠️⚠️ **Lo grave no es el número: es que había una guarda escrita para esto exactamente, y también
eligió mal.** `SidebarVerifyScreenTest` **cruzaba las dos fuentes** en vez de copiar una —su propio
comentario dice «un test que solo aseverara 30 se quedaría en verde el día que alguien suba el
limitador del servidor a 60»— y llevaba una defensa razonada para no confundirse de `$ipKey`, porque
`SelfSignup` tiene dos limitadores por IP. Y aun así **no vio el segundo limitador**, que estaba tres
líneas más abajo con otra variable. ▶ **Un `preg_match` acotado a un nombre solo encuentra lo que ya
sabías que buscabas**, y por eso el arreglo no es cambiar el número: los dos cooldowns se promueven a
constantes con nombre, el test lee las CONSTANTES en vez del código, toma **el máximo** —no el primero
que encuentra— y hay un caso nuevo que vigila **cuál de los dos ata**, porque si algún día el de
correo cayera por debajo del de IP la aserción seguiría verde y la defensa del buzón ajeno habría
desaparecido.

**Descartadas dos alternativas, y las dos por el mismo sitio**: bajar el cooldown del servidor a 30 s
debilita lo único que impide bombardear el buzón de un tercero con IPs rotativas; y hacer que el
servidor diga la verdad (429 / `Retry-After`) choca con `SEC-06`, que es la razón de que sea mudo.

**Verificado**: 4 reenvíos → **bandeja 1 → 2 → 3 → 4 → 5**. Ni uno se pierde. Y por mutación en los
dos lados: volver el cliente a 30 pone el test rojo; bajar el cooldown por correo a 15, también.

### 2 · El spinner de Privacidad no se pintaba nunca

`#124` puso velo en «las cuatro zonas que piden datos». En `privacy` **no aparecía**: el `v-if` era
`store.busy && ! store.consentsLoaded` y `ensureConsents()` **no levantaba ninguna bandera**, así que
las tres ramas del `v-if/v-else-if` eran falsas a la vez y la tarjeta se pintaba **vacía**. Medido
reteniendo `GET /me/consents`: **2,6 s** con solo el título y el botón de exportar.

Es bandera **propia** (`consentsLoading`) y no `busy`, por dos razones y **la segunda es la que cierra
la elección**: `busy` es el estado de los dos FORMULARIOS de la pantalla —bloquearlos por leer una
lista de cortesía es un efecto que nadie pidió—, y `reset()` llama a `resetForm()`, que pone `busy` a
`false`: **reentrar en la zona con una carga en vuelo apagaría el velo a mitad**. Es el mismo patrón
que `stores/orders.js` y `stores/reservations.js`; el outlier era esta pantalla.

⚠️ **Ningún test podía verlo, y tampoco por descuido**: los de `privacy.test.js` hacen `await` y solo
miran el desenlace. El caso nuevo **retiene la promesa a propósito** y mira el estado intermedio, que
es el único sitio donde el fallo existía.

### 3 · Las pestañas de auth, 50/50 — `#124` había aplicado media regla

Medido: la barra ocupaba sus 400 px y dentro los botones salían a **103 y 156 px**, dimensionados por
la longitud del rótulo. En `landing.css` las dos mitades van juntas —`.zone-tabs { width: 100% }` y
`.zone-tab { flex: 1 }`, con un comentario que dice «los 2 botones repartidos 50/50»— y `#124` sacó del
media query de móvil **solo la primera**. Hoy son **193 y 193 px**.

### 4 · [DECIDIDO, owner] Conmutar de pestaña no es navegar

«Volver» desde «Crear cuenta» cambiaba de pestaña: mismo armazón, misma barra, otro formulario — un
botón que aparentaba no hacer nada. **`LOGIN` y `REGISTER` no son dos pantallas: son las dos caras de
una**, presentadas al mismo nivel por `AuthTabs`.

El síntoma tenía **DOS causas y arreglar una sola lo deja vivo**: `parentZoneFor()` sembraba `LOGIN`
debajo del alta, y las pestañas llamaban a `go()`, que apila. Ahora la siembra queda **solo para
recuperar contraseña** —que sí es una pantalla aparte: se llega por un enlace dentro de «entrar» y no
tiene sitio en la barra— y nace `navigation.js::replace()`, que **sustituye la cima sin cambiar el
tamaño de la pila** y conserva lo que hay debajo, para que quien llegó con historia ajena no la pierda
al conmutar.

⚠️ **Efecto secundario aceptado por el owner**: llegar en frío a `/registro` y pulsar «Volver» ahora
sale al catálogo en vez de a «entrar».

### Lo medido

Suite **2679** (15.268 aserciones) · **641 tests JS** · chunk **210,83 → 211,02 KiB**, +190 bytes
exactos: se agotaron los 0,17 KiB de holgura y **el techo sube a 212 con su párrafo en el ledger**.
Los 190 bytes son el precio de `replace()` y de `consentsLoading` — **ninguno es una feature**: son dos
fallos, que es el único caso en que este techo debe ceder.
**60/60 en navegador** tras el arreglo: `V17` 15/15 · `V18` 11/11 · `V23` 22/22 · tanda A 12/12.

## #126 · 2026-08-23 · [DECIDIDO] «Mis reservas» se lista POR RESERVA, y el pasado se va a su propia pantalla

**Encargo del owner**, con sus decisiones tomadas: listar por reserva individual con la referencia del
pedido · el ledger y el reintento plegados tras «Ver pedido» · ordenar y paginar **en el servidor** ·
**5** tarjetas por página · las terminadas **fuera** de la lista principal, tras un CTA «Ver historial
de reservas» y de la más reciente a la más antigua · las reservas **sin franja, arriba del todo**.
Spec: `docs/specs/mis-reservas-por-reserva.md` (✅ EJECUTADA).

**Qué dolía, medido** sobre el cliente de desarrollo: `GET /me/orders` ordena con `->latest()` —fecha
de creación del PEDIDO—, así que se servían **tres reservas ya pasadas por encima de la única que aún
no había llegado**, y una de ellas cancelada sin distinción visual. Un pedido puede llevar tres
reservas de tres fechas distintas y para el cliente eso no es una unidad: es un detalle contable.

### 1 · Partir la pantalla en dos quita un problema y pone otro peor

Con una lista mezclada, un predicado mal calculado da un **mal orden** — se ve. Con dos listas
alimentadas por **dos consultas independientes**, da una reserva que **no sale en ninguna de las dos**,
y eso no lo nota nadie: una lista a la que le falta una fila se lee perfectamente.

▶ Por eso el ámbito es un **enum sobre UN predicado** (`Booking\Contracts\ReservationScope`) y no dos
métodos: `where` en un lado, `whereNot` en el otro. La partición es exhaustiva y disjunta **por
construcción**, no por coincidencia. Y lo sostiene una guarda que no comprueba listas sino la
PROPIEDAD: `upcoming.total + past.total === total de reservas` y ninguna id en los dos.

⚠️⚠️ **Y la NULL-safety no es pulcritud: sin ella se pierde una fila.** `whereNot()` sobre una
comparación con columna NULLable devuelve *unknown*, y `NOT unknown` sigue siendo *unknown*: la reserva
sin franja se cae de los DOS lados. **Medido: la partición pasa de 9 a 8 y nada más falla.**

⚠️⚠️ **Y una lección que costó tres mutaciones.** La primera versión guardaba con `whereNotNull` las
**dos** columnas del slot. Como en el esquema las dos son `NOT NULL` y solo valen NULL a la vez
—cuando el `leftJoin` no encuentra franja—, **cada una tapaba a la otra y ninguna se podía medir
mutándola**: quitar cualquiera dejaba el test en verde. Es `#112` otra vez —«una guarda con dos
fuentes redundantes no se puede medir mutando una sola»— y se descubrió porque **las mutaciones se
corrieron de verdad**, no porque se leyera el código.

### 2 · El corte de «disfrutada» resultó ser EXACTO, y el diseño lo daba por perdido

La spec proponía agrupar por DÍA y aceptar 24 h de discrepancia, dando por hecho que el corte fino de
`isFinishedInPractice()` no era expresable en SQL sin concatenar fecha y hora —lo que ataría la
consulta al dialecto, con la suite en SQLite y producción en MySQL—. **Comparando DOS COLUMNAS no hace
falta concatenar**: `date < hoy OR (date = hoy AND end_time < ahora)`.
▶ Y no es un detalle: con precisión de día, esta partición y `upcomingFor()` —que alimenta el bloque
de cuenta— **habrían discrepado**, y el panel diría «1 reserva próxima» de algo que la pantalla enseña
en el historial. Hay caso que lo fija.

### 3 · Tres cosas que la implementación quitó del diseño, y las tres a mejor

- **El LEDGER no viaja con la tarjeta.** El caso real `DEMO-LEDGER` tiene **tres** reservas: el
  desglose viajaría tres veces con importes que no cuadran con la tarjeta que los rodea. Se pide al
  desplegar con **`GET /orders/{code}`, que YA EXISTÍA**. Efecto que vale más que el ahorro: **no nace
  una segunda superficie de dinero** — se reutiliza `financialsOf()`/`orderRow()` tal cual.
- **Ni store nuevo ni módulo nuevo.** `stores/orders.js` se repurpone —`pages` por ámbito, más
  `ensureOrder()`— y `account/orders.js` gana `cardRow()`, que **reutiliza `lineRow()` entera**: una
  reserva suelta y una reserva dentro de su pedido son la misma cosa.
- **Ni zona nueva de componente.** `OrdersZone.vue` sirve las DOS pantallas con un `scope` por prop:
  lo único que las distingue es qué ámbito piden, si atenúan y si ofrecen el CTA.
- **`is_terminated` se descartó** aunque el diseño lo pedía: sería un eco del ámbito que el cliente
  acaba de pedir, o una segunda definición del predicado que reparte las pantallas.

### 4 · Dos guardas que hubo que cambiar de FORMA, no relajar

⚠️ **La alcanzabilidad de zonas.** La regla empezó siendo «toda zona está en `HOME_ENTRIES`», el
2026-08-23 pasó a «en el índice o en `GUEST_ZONES`», y el historial no cabe en ninguna: se llega a él
desde «Mis reservas». Nace `ZONE_PARENTS` —la tercera puerta, declarada como DATO— con casos de que el
padre exista, sea alcanzable y no haya ciclos. La alternativa era una excepción escrita a mano dentro
del test, y ahí es «donde se acaba metiendo cualquier cosa».

⚠️⚠️ **Y `SidebarStyleWiringTest` estaba CIEGO justo para su caso principal.** Su escáner declaraba
que no mira `:class` «porque se componen en ejecución», y eso es cierto solo a medias:
`:class="{ 'orders__item--past': dimmed }"` lleva el nombre **escrito entero**, y así es exactamente
como se emite un MODIFICADOR — que es lo que `#124` encontró servido sin regla. Ahora lee los
literales que son **clave de objeto** o **rama de un ternario**, y sigue sin leer lo que de verdad se
compone (`'orders__status--' + estado`). ▶ La primera versión los recogía TODOS y declaraba huérfanas
`.login`, `.register` y `.pending` —tres operandos de comparación—: un test que grita por algo que no
existe se acaba silenciando entero.

### 5 · Un fallo que solo vio el navegador

La poda del payload del montaje es **clave a clave** (`Arr::only`), así que las cuatro claves nuevas
viajaban vacías: `i18n.js` devuelve `''` cuando falta una y **el botón «Ver pedido» se pintó mudo y la
referencia del pedido en blanco**, sin que nada fallara. La suite estaba entera en verde. Es la misma
familia de los 20 iconos vacíos (`#113`).

### Lo medido

Suite **2704** (15.368 aserciones, +25: 13 del reparto en el dominio y 12 del endpoint) · **648 tests
JS** · chunk **211,02 → 213,57 KiB**, techo a **214,5** con su ledger · payload del montaje dentro de
su techo. **16/16 en navegador** con datos sembrados para ver paginación en los dos ámbitos, y las
tres tandas anteriores re-corridas sin regresión (`V23` 22/22 · `V18` 11/11 · tanda A 12/12).
Cada guarda **verificada por mutación**; dos de ellas resultaron no medir nada y se rehicieron.

---

## #127 · 2026-08-24 · [DECIDIDO] El desglose del cliente son DOS EJES CERRADOS — y la auditoría encontró CUATRO defectos de dominio que nadie había construido

**Contexto.** `#125` cerró una auditoría del desglose con un veredicto tranquilizador: «la aritmética
no está mal, el riesgo está en la proyección». El owner pidió una tercera vuelta —auditar **todo**,
en local **y en staging**, con pruebas de verdad— y fijó los objetivos de producto: *que el cliente
entienda la situación económica de su pedido, con trazabilidad y explicación ante cualquier
situación, y que la entienda fácilmente*. Con esos objetivos delante, delegó la decisión en el agente.

**Corpus medido**: 58 pedidos en local (MySQL; 36 preexistentes + **22 creados por los flujos
reales**: `OrderCreator` → vuelta de Redsys FIRMADA → edición del panel → reembolsos REST/manual/total
→ `orders:expire`) y 6 en staging (**MariaDB**). La proyección, **por HTTP** contra
`GET /api/v1/me/orders` con token Bearer, no leyendo el Resource.

**Lo que se confirmó de `#125`**: no hay dos fuentes de verdad —panel y cliente enseñan el mismo
«Pagado online» en **64 de 64**—, y siete de las nueve identidades se cumplen en los dos motores.

**⚠️⚠️ Lo que `#125` no vio, porque no llegó a construirlo.** Cuatro defectos de DOMINIO, ninguno
legacy, todos producidos por el flujo ordinario (detalle en `specs/desglose-dinero-cliente.md` §9.2):

1. **Un pedido cancelado sin reembolsar no anuncia nada pendiente de devolver.** El parque retiene el
   dinero y el cliente lee «Total 19,80 €» y ni una palabra. **Ninguna identidad lo caza, ni la ley de
   caja**: el dominio cree que el producto sigue vivo, así que la caja cuadra. Causa: cancelar el
   PEDIDO no cancela sus LÍNEAS, ni por la acción del panel (`ViewOrder`) ni por `executeFullRefund`.
   ▶ Y su alcance operativo es peor: `cancellationBlockedReason()` **permite** cancelar un pedido ya
   cobrado, y después `refundBlockedReason()` **bloquea el reembolso sobre cancelados**. Un clic deja
   el dinero retenido, sin camino de vuelta en el panel y sin avisar al cliente.
2. **Un pedido reembolsado ENTERO sigue diciendo que su total es el precio completo.** Misma causa.
   Incumple lo que `openapi/v1.yaml` promete para `total_final_cents` («lo que el cliente acaba pagando»).
3. **Un pedido que nunca se pagó declara dinero cobrado**: «Pagado online 30,00 € + Pagado en el
   parque 90,00 €» con **0,00 € realmente cobrados**. Lo destapó `R-VYXKRD` en STAGING y se reprodujo
   en local con el `orders:expire` real. Causa: las cestas de puerta se resuelven solo con que la
   franja haya pasado. ⚠️ La comprobación **ya existía tres líneas más arriba**, en `showsDepositNote()`.
4. **Un reembolso total no se atribuye a ninguna reserva** (`order_item_id => null`): la tarjeta dice
   «devuelto 0,00 €» con 19,80 € devueltos. ⚠️ **Corregido el 2026-08-24: SÍ se ve hoy** — lo consumen
   la sub-tarjeta por reserva del panel y la hoja PDF; solo el bloque de totales del pedido lo esquiva
   a mano. La primera lectura lo dio por latente y era una divergencia viva.

**Y la proyección no es un defecto, son cuatro** (§9.3): falta «Pagado en el parque» (falla en el
**100 %** de los que tienen algo cobrado en puerta, no en 1 de 11); el eje de la caja se pinta como
resta dentro de la columna del valor; «Subtotal» y «Total» son bases distintas; y **«Pagado online» se
OCULTA** cuando el producto no lleva señal —defecto que no estaba registrado en ningún documento—.
Medido por HTTP: **30 de 50 columnas se leen sin ambigüedad, 20 no**.

**LA DECISIÓN — dos ejes cerrados, y cada euro con su línea.** El principio: el desglose no se deriva
en ninguna superficie; el dominio publica un desglose **cerrado** y las superficies solo pintan.

    EJE VALOR   valor = pagadoWeb + pendienteWeb + pagadoParque + pendienteParque + compensado
    EJE CAJA    cobradoWeb − devuelto = pagadoWeb + pendienteDevolución

▶ **El eje del valor tiene CINCO canales, no tres**, y los dos que aparecen resuelven defectos viejos:
**`pendienteWeb`** separa cobrado de pendiente y mata el «Pagado online 11,90 €» de un pedido que nadie
pagó; **`compensado`** —dinero devuelto sin quitar producto— era la «excepción» de la ley de caja, que
en realidad **era un término que faltaba**. Y el eje de la caja se ancla en lo REALMENTE cobrado, que
es lo único que el cliente puede cotejar con su banco.

**⚠️ Verificado ANTES de escribir producción**: las dos identidades **cierran en 58 de 58** pedidos
reales, y el único estado que el modelo declara imposible (`pendienteDevolución < 0`) dispara
**exactamente** sobre los 19 de datos sucios y sobre **ninguno** de los 22 creados por flujos reales.

**Consecuencias que hay que aceptar, y por qué son correctas:**
- **La tanda 1 NO era «riesgo cero»**: sus invariantes nacían en rojo sobre los cuatro defectos.
- **La tanda 2 no podía cumplir «sin tocar un número del panel»**: el panel miente en los mismos
  cuatro casos. Cambian **3** pedidos de los creados por flujos reales, los tres de un número falso a
  uno cierto, y el reparto por canal en todos los no cobrados.
- **Publicar `devuelto` por reserva está BLOQUEADO** hasta arreglar el defecto 4.

**Decisiones de producto que quedan cerradas con esto**: §6.1 (se añade lo que falta, sin cambiar el
concepto del panel), §6.2 («Subtotal» sale de la columna y pasa a línea de trazabilidad), §6.3 y §6.4
(una lista única de conceptos, dos voces — tabla en §10.4). Y **cada estado lleva su FRASE**, compuesta
por el dominio: un número no explica, y explicar era el encargo.

**Y dos cosas medidas que corrigen la doc anterior:**
- ⚠️ **El sandbox de Redsys es INALCANZABLE desde los tres entornos** — `sis-t.redsys.es:443` da
  timeout de TCP desde local, desde el contenedor y desde staging, mientras `github.com:443` responde
  en los tres. **Redsys filtra por IP de origen.** El pendiente del tracker no es «una pasada por el
  sandbox»: es que **el banco autorice la IP**. Acción del owner.
- ⚠️ **No hay ninguna semilla que arreglar** (la §8 de la spec lo daba por hecho): **ningún seeder del
  repo crea pedidos**. Los `DEMO-*` son datos escritos a mano en sesiones anteriores, y una
  instalación limpia no tiene ninguno.
- ✅ **La rama LEGACY de reembolso es código muerto** y se retira: ningún escritor la produce, y el
  owner decidió que **JumpWeb solo instala limpio**. Se sustituye por una guarda de construcción —«la
  columna es siempre Σ filas»— en vez de debilitar el invariante con una excepción.

**Plan revisado (§11): TRES tandas correctamente dimensionadas** — **A** dominio + invariantes (los
cuatro arreglos y sus guardas en el mismo paso: un arreglo de dinero y su guarda no se separan) ·
**B** proyección (contrato primero) · **C** pantalla.

⚠️ **Tres correcciones de MÉTODO que esta auditoría pagó** (§9.4): la excepción de la ley de caja se
escribió mal DOS veces y cada versión floja excusó un defecto real —la condición correcta es «no ha
movido dinero», ni entró por web ni el dominio afirma haber cobrado en puerta—; `R-L6UTIA` **parecía**
un defecto y es un artefacto de un guion anterior (la trampa de §2 al revés); y `dates()`/`times()` de
`AvailabilityOffer` devuelven **value objects**, no arrays.

### #127(b) · 2026-08-24 · La MATRIZ DE GESTIÓN DEL PANEL, medida sobre las acciones REALES

El owner pidió, antes de arrancar la tanda A, listar **toda** la gestión que un admin puede accionar
sobre un pedido y sobre una reserva y medir cómo afecta al desglose. Se hizo **conduciendo las
acciones reales del panel** (Livewire sobre `ViewOrder`), no replicando su lógica. Detalle en
`specs/desglose-dinero-cliente.md` §12.

    23 acciones medidas · 0 fallaron
     2 bloqueadas legítimamente (un COMPLEMENTO no se cancela ni se reembolsa suelto)
     2 sin efecto financiero, correctamente (cambiar la franja)
     3 rompen la identidad B5 (los tres reembolsos a nivel de pedido)
    18 dejan la columna del cliente ILEGIBLE
     0 rompen las dos identidades del modelo decidido en #127

▶ **Eso reencuadra la urgencia**: no falla un caso raro — **casi cualquier gestión ordinaria del
operador rompe el desglose del cliente**. «Subtotal ≠ Total» aparece en **las 10 ediciones, sin
excepción**.

**La prueba más limpia del defecto D1**, porque es la MISMA operación a dos niveles: cancelar la
RESERVA deja `valor 24,00 → 0,00` y `pendiente de devolución 0,00 → 24,00` (correcto); cancelar el
PEDIDO deja `valor 24,00 → 24,00` y `pendiente 0,00` (falso). **La conducta correcta ya existe un
nivel más abajo**: no hay que inventarla.

⚠️⚠️ **Y un defecto NUEVO que la matriz destapó: `refundItem` no cancela la línea, y su propio
docblock afirma que sí** («*devolver = el operador entiende que ese item ya no se entrega*»); el
código llama `executePartialRefundBatch(alsoCancelItems: false)`. Código y documentación se
contradicen **en dinero**. Medido sobre un pack con señal: se devuelven los 30,00 € de la señal, la
reserva sigue viva y las DOS superficies siguen contando esos 30,00 € como «pagados online».
▶ Detrás hay una decisión de producto: «reembolsar sin cancelar» cubre **dos** situaciones —
compensación de cortesía y canje en persona— y el sistema **no distingue cuál**, así que ni el ledger
ni la puerta saben si el cliente sigue debiendo el dinero.

**Tres trampas de método más** (§12.5): el `slot_time` del payload **se ignora** (las propiedades del
calendario tienen prioridad y `mountUsing` las rellena, así que el fallback «para automatización» de
su docblock nunca se alcanza) · las franjas del fixture deben ser **contiguas y abiertas a venta
online** o toda edición se bloquea con un falso `insufficient_capacity_at_save` · y `Order.total` de
un pedido con señal es el **valor completo**, no la señal.

⚠️ **Corrección a la propia `#127`**: afirmó que el sandbox de Redsys era inalcanzable desde los tres
entornos. **Era un error de medida** —se probó el puerto 443 y Redsys sirve el sandbox en el
**25443**, que es el que usa `Redsys::REST_URL_TEST`—. Con las credenciales del owner (comercio
`263100000`, terminal 45) la integración queda **VERIFICADA**: firma `HMAC_SHA512_V2` aceptada por el
banco (`SIS0054`), y el control `--bad-key` da `SIS0042`. ⚠️ Pero **el verificador tiene un defecto:
su control negativo sale en VERDE** — `report()` bendice cualquier `gateway_denied`, incluido el
`SIS0042` de firma rechazada. Una guarda cuyo control negativo no falla no es una guarda; se arregla
en la tanda A.

### #127(c) · 2026-08-24 · La TARIFA al cambiar de fecha, y el campo de MOTIVO del reembolso

**La pregunta del owner**: «si un producto tiene un precio el sábado y se cambia la fecha a lunes, el
precio debería cambiar. ¿No está reflejado? ¿Es un hueco?». Medido (spec §13): **no es un olvido, es
una regla** —«producto SIN cambio → conserva el `unit_price` HISTÓRICO … sin sorpresas»— pero **vive
solo en un docblock de `ViewOrder` y en dos aserciones de test**. Una regla que mueve dinero en las
dos direcciones y no está en el registro de decisiones.

**Medido con dos tarifas reales (12,00 laborable / 20,00 fin de semana) y la acción real del panel:**

    Sábado → Lunes : unit_price 20,00 → 20,00 (catálogo del destino: 12,00)  +16,00 € a favor del PARQUE
    Lunes → Sábado : unit_price 12,00 → 12,00 (catálogo del destino: 20,00)  −16,00 € a favor del CLIENTE
    Y el desglose del cliente es IDÉNTICO antes y después: ni una línea lo menciona.
    Control: cambiar de PRODUCTO sí re-tarifica al catálogo del día (25,00 el sábado) → la sonda mide.

⚠️⚠️ **Lo grave no es la regla, es que el panel AFIRMA que no hay diferencia.** `priceDiffPreview`
comparte el cómputo con el guardado y muestra **«sin cambio de precio» (0,00 €)** cuando la diferencia
real de catálogo son 16,00 €. **No es silencio: es una afirmación falsa**, y el operador mueve la
reserva creyéndola.

⚠️ **Y hay una INCONSISTENCIA dentro del mismo formulario**: con la misma fecha destino, cambiar el
PRODUCTO re-tarifica y cambiar solo la FECHA no. Dos reglas distintas en el mismo modal.

▶ **[DECIDIDO, owner] LA REGLA CAMBIA: mover la fecha RE-TARIFICA al catálogo del día destino.**
El agente propuso conservarla («política defendible») y **el owner la revocó** con el argumento que la
cierra: *«si el cliente elige un día más caro, se le cobra; el cliente elige el día sabiendo su precio.
Imagínate a todos comprando el día barato y llamando para cambiarlo a sábado porque no se les cobra
nada. Esto es inviable.»* **No es una política, es un arbitraje abierto**: el descuento es exactamente
la diferencia de tarifa, y la regla solo sería defendible si el precio no dependiera del día — y aquí
depende por diseño. Se acaba además la asimetría del mismo formulario (cambiar de producto ya
re-tarificaba). ▶ Va en la **tanda A**: cambia importes y es la que cierra el agujero. El previo
honesto del operador y la trazabilidad del cliente la acompañan.

**Reembolsar y cancelar siguen siendo INDEPENDIENTES** [DECIDIDO, owner 2026-08-24]: se puede
reembolsar sin cancelar y cancelar sin reembolsar, a propósito, para que el operador tenga
flexibilidad al entenderse con el cliente en las instalaciones. Se revisará cuando haya clientes
reales. ⚠️ **Eso convierte en incorrecta la lectura de `#127(b)` que trataba `refundItem` como un
defecto**: la conducta es deliberada. **Lo que sigue siendo defecto es el docblock**, que afirma
`alsoCancelItem=true` cuando el código pasa `false` — la doc, no el código.

⚠️⚠️ **Pero esa flexibilidad tiene una consecuencia que el desglose no puede resolver solo, y la
descubrió el propio diseño de `#127`**: el modelo enseña «Pendiente de devolverte X €» en un pedido
cancelado sin reembolsar. Si la política del parque es «cancelar no da derecho a devolución», esa
línea **promete algo que no va a pasar**. Y en el caso simétrico —reembolsar sin cancelar— ni el
cliente ni la puerta saben si sigue debiendo el dinero.
▶ **Decisión: se añade el MOTIVO, y es el owner quien lo autorizó a prueba.** Dos intenciones, una por
acción, obligatorias porque son acciones de dinero:
- al **reembolsar sin cancelar**: ¿*compensación* (no debe nada) o *lo pagará en persona* (sigue debiendo)?
- al **cancelar sin reembolsar**: ¿*se le devolverá* o *no procede devolución*?
▶ **Coste medido**: `payment_refunds` **no tiene** columna para esto (solo `failure_reason`, que es del
gateway) → una migración aditiva y nullable. `order_adjustments` sí tiene `reason`/`context`.
▶ **Reparto**: la tanda A **registra** el motivo (es un hecho del dominio); la tanda B lo **enseña**
como frase. Sin ese dato, la frase del cliente solo puede ser vaga — y «que lo entienda claramente»
era el encargo.

### #127(d) · 2026-08-24 · [DECIDIDO] La regla de TARIFA al mover la fecha, cerrada en sus tres direcciones

Cerrando el agujero de `#127(c)`, el owner fija las tres reglas que faltaban. **Las tres son
decisiones suyas, tomadas con el riesgo de cada opción escrito delante.**

1. **El precio que se aplica es el de HOY del DÍA DESTINO.** «Pagas el precio del día que elijas» — la
   regla más simple de explicar al cliente y de vigilar con una guarda.
   ⚠️ **Efecto lateral ACEPTADO, para que nadie lo redescubra como un fallo**: si el parque sube
   precios y un cliente mueve su reserva de un sábado a OTRO sábado, **se le cobra la subida** aunque
   no haya cambiado de tipo de día; y **un descuento previo se pierde** al cambiar de fecha. Se
   descartó la alternativa —cobrar solo la diferencia entre el tipo de día de origen y el de destino,
   que habría respetado precio y descuento— por ser más difícil de explicar y de guardar.
   ⚠️ **Y el límite de la regla, que hay que escribir para que no se desborde**: se re-tarifica
   **SOLO cuando cambia la FECHA**. Una edición que no mueve el día (subir cantidad, añadir
   complemento) **conserva la tarifa histórica del ítem**, como hoy. Aplicar el catálogo actual a
   toda edición sería un cambio mucho mayor que nadie ha pedido.
2. **Al mover a un día MÁS CARO, la diferencia se cobra EN EL PARQUE**, exactamente como cualquier
   otra subida (subir cantidad, añadir complemento, cambiar de producto). No toca la pasarela: el
   segundo cobro online se descartó a propósito por su fricción (PSD2/SCA). **Cero mecanismos nuevos.**
3. **Al mover a un día MÁS BARATO, se le abona la diferencia** y aflora como «pendiente de
   devolución», igual que cualquier bajada de valor. Simétrico.
   ▶ **Se evaluó el «arbitraje espejo» y NO existe**: comprar sábado y moverse a lunes deja una
   reserva de lunes a precio de lunes, que es lo que se habría comprado directamente — no hay
   ganancia. El único coste real es **operativo**: el reembolso lo ejecuta un operador a mano, como
   toda bajada (`#225` D8: bajar = solo cancelar, el reembolso va aparte).

**Dónde se toca, medido** (spec §14.2·A1). El despacho del modal está localizado y el arreglo REUSA la
maquinaria de dinero que ya existe, sin añadir ninguna:

    si (producto || cantidad || complementos cambiaron) → executeItemEdit()       ← ya cobra y abona
    si no, y solo cambió la fecha                       → executeItemSlotChange() ← hoy NO roza el precio

▶ Basta con que **un cambio de fecha cuya tarifa difiere entre por el camino que ya sabe aplicar
cargos y abonos**, y que `computeEditPricing` resuelva el precio por fecha en ese caso. El previo del
operador se arregla solo: comparte ese mismo cómputo, que es justo por lo que hoy **afirma** «sin
cambio de precio».

### #127(e) · 2026-08-24 · TANDA A ejecutada — y tres cosas que solo se supieron al hacerla

Los seis arreglos y sus guardas están en `specs/desglose-dinero-cliente.md` §15. Aquí lo que la
ejecución ENSEÑÓ, que es lo que no estaba en el plan:

1. ⚠️⚠️ **Una guarda sobre el helper suelto NO ve el cableado.** La mutación de «cancelar el pedido
   cancela sus reservas» **no mordió**: el escenario llamaba a `cancelLiveItems()` directamente en
   vez de conducir las acciones del panel. Es la trampa nº1 de `CONVENCIONES §3.quater` —comprobar
   DÓNDE cayó la mutación— y costó dos guardas nuevas que conducen las acciones REALES. **Sin
   verificar por mutación, ese arreglo habría quedado sin red y nadie lo habría sabido.**
2. ⚠️⚠️ **`compensado` NO se puede definir por línea**, y se intentó. La versión por-línea
   sobre-reporta cuando la pérdida de valor no deja huella en el ítem —un cambio a producto más
   barato deja un `unit_price` nuevo, así que «cantidad_original × unit_price» miente—, que es
   exactamente el caso que `#225` arregló anclando `pendienteDevolucion` a caja. Rompía su test. Va
   anclado a CAJA a nivel de pedido y se REPARTE por reserva: una sola fórmula, un solo número.
3. ⚠️ **La identidad `D` cazó un defecto que acababa de introducir el propio arreglo**: al exigir
   «cobrado» para resolver las cestas de puerta, el desglose ↳ se quedó con el predicado viejo y dejó
   de sumar su titular. Son TRES sitios que tienen que decir lo mismo, no dos. Justo para eso existe.

▶ **Y una decisión de robustez tomada al medir**: «un pedido cancelado no tiene valor vivo» se aplica
**en dos capas** —la cascada al cancelar deja el dato explícito, y una segunda capa de lectura lo hace
cierto también sobre filas anteriores—. Sin la segunda, la regla dependería del camino de escritura,
que es literalmente el hallazgo ALTO («coinciden por disciplina, no por construcción») del que nació
todo este fichero de invariantes.

▶ **Verificado sobre datos reales, no solo fixtures**: las dos identidades cierran en los 58 pedidos
de MySQL salvo en 20, y los 20 son datos escritos a mano —18 `paid` sin fila `Payment`, 1 con la
columna de reembolso a mano y 1 artefacto de un guion viejo—. **Todo pedido creado por un flujo real
cumple.** Un invariante que solo se queja de lo que ya estaba roto es exactamente lo que se buscaba.

⚠️ **Y el contador de columnas ilegibles de la matriz del panel subió de 18 a 19, que es BUENA
noticia**: cancelar el pedido antes «se leía bien» porque mentía en silencio —«Total 19,80 €» y nada
más—; ahora dice la verdad («pendiente de devolverte 19,80 €») y lo que falla es la maquetación, que
es lo que arregla la tanda B.

### #127(f) · 2026-08-24 · TANDA B ejecutada — el defecto era ESTRUCTURAL, no de rótulos

Detalle en `specs/desglose-dinero-cliente.md` §16. Lo que la ejecución dejó claro:

⚠️⚠️ **El problema nunca fue cómo se llamaban las líneas: era que OCHO superficies componían cada
una su desglose.** Cambiar rótulos habría dejado el mecanismo intacto y la divergencia habría vuelto
con la siguiente pantalla. La corrección es que `Booking\Services\OrderLedger` lo compone y las ocho
**pintan**; el contrato lo publica en **dos ejes** y los seis campos sueltos de la raíz se van dentro.

▶ **Y la guarda vigila el MECANISMO, no el síntoma**: `LedgerSingleSourceTest` tumba a una superficie
que vuelva a derivar un canal restando otros **aunque su resultado sea correcto hoy** — porque
«correcto hoy» era exactamente el estado del panel, que calculaba `pagadoOnline = valorFinal −
aCobrar − pagadoPuerta` mientras la API usaba otra fórmula. Coincidían por álgebra.

▶ **La medida que cierra la tanda**: la matriz de las 23 acciones del panel, re-corrida entera →
**0 de 23 dejan la columna ilegible**, cuando eran 18. Y el eje del valor **cierra en 50 de 50**
pedidos medidos por HTTP real.

**Tres cosas que solo se supieron al hacerlo:**
1. **Los CORREOS tenían el mismo defecto por otra razón: se REENVÍAN.** `onlineDueCents()` es «lo que
   se cobraría al pagar ahora» y `Order.total` es lo facturado; los dos envejecen mal en un reenvío
   hecho meses después. El propio código ya lo había razonado para lo pendiente en puerta y no lo
   había aplicado a los otros dos importes del mismo correo.
2. **Dos guardas de ARQUITECTURA cazaron cosas que el autor no vio**: la de fronteras de módulos
   —`Booking` no puede nombrar un modelo de `Payments`, así que la intención del reembolso llega como
   cadena desde `Order`— y la de estilo, con cinco clases nuevas sin una sola regla CSS. Ninguna de
   las dos habría fallado en la suite ni se habría visto en el navegador hasta mucho después.
3. **Y el cuarto fixture irreal de esta serie**: escribía la columna de reembolso a mano dejando una
   fila del importe completo. Es la cuarta vez que se anota lo mismo en este trabajo — **un fixture
   que no reproduce el flujo real inventa defectos tan bien como los oculta**.

⚠️ **Una desviación consciente de la tabla de vocabulario de §10.4**: el ledger que ve el cliente es
el del PEDIDO —se abre desde «Ver pedido»— así que su total se rotula «Valor del pedido» y no «Valor
de tu reserva», que engañaría en un pedido con dos reservas.

---

## #128 · 2026-08-24 · [DECIDIDO] Los TRES defectos de lectura del desglose — y el ancla que lo hace VERIFICABLE, no solo legible

**Contexto.** `#127` dejó el desglose cerrado en dos ejes y legible. El owner miró entonces un pedido
REAL en pantalla —`R-L6UTIA`— y no lo entendió. **Tenía razón en no entenderlo**: decía «Pagado por
web 114,00 €» sobre un cobro real de 30,00 €. El dato está roto en la BD (un guion de auditoría subió
el precio 96,00 € y registró 12,00 € de `extra_due`; el flujo real no puede producirlo, y `PAY-17` ya
lo marcaba). Pero mirarlo en pantalla destapó **tres defectos de LECTURA que sí eran nuestros**, y los
tres estaban en la superficie del cliente. Detalle en `specs/desglose-dinero-cliente.md` §17 y §18.

**`L1` — el ancla de caja no se enseñaba si no había devoluciones.** Es el grave. «Cobrado por web»
es **lo único que el cliente puede cotejar con su extracto bancario**, y en un pedido normal no lo
veía nunca: el desglose era legible pero **no verificable**. Y no era solo un fallo de diseño, eran
dos cosas:

1. **al predicado le faltaba un término**: `OrderLedger::hasCash()` miraba devoluciones y no el cobro;
2. **nadie llamaba al predicado.** Existía en el dominio y cada superficie re-derivaba la condición
   por su cuenta —el cliente en JavaScript, el panel en su blade—, que es la forma de divergencia que
   `OrderLedger` existe para cerrar. **Medido sobre los 58 pedidos: el panel enseñaba el ancla en 28
   de 38 pedidos sanos y el cliente en 9 → divergían en 19, la mitad del corpus.**

▶ Ahora la condición se **publica** (`ledger.cash.has_cash`) y las dos superficies preguntan al mismo
sitio. Medido después: **37 de 58 pedidos enseñan el eje de caja** —exactamente los 37 que han movido
dinero— y el cambio de condición **no altera el panel en ninguno de los 58**.

⚠️ **Y hacerlo visible obligó a arreglar algo que estaba latente: el MÉTODO.** El eje de caja suma
**todos** los pagos cobrados sin mirar el `provider` —eso es correcto: mide dinero movido, no medios—,
así que un pedido cobrado en **taquilla** (efectivo o datáfono, `ManualOrderFulfiller`) habría dicho
«Cobrado por web» de un dinero entregado en mano. El panel ya distinguía el método desde `P1/P10`; el
cliente no. Con el ancla escondida la divergencia era inocua; con el ancla visible pasaba a ser una
**afirmación falsa en pantalla**. Se publica `charged_method` como **enum y no como rótulo**: el panel
habla en tercera persona y el cliente en segunda (§10.4), así que la voz la pone cada superficie.
▶ Y con él la **fecha** (`charged_at_label`): «cobrado 30,00 €» no se busca en un extracto bancario;
«30,00 € el 24/08/2026» sí. Sin fecha el ancla no es conciliable, que era el objetivo entero.

**`L2` — `8×216,00 €`.** La tarjeta pintaba el número de cantidad pegado al importe de la LÍNEA, y eso
se lee como «8 unidades a 216 € cada una» = 1.728 € cuando son **8 invitados y 216 € en total**. Un
número sin sustantivo no distingue cantidad de importe. Se publica `quantity_label` compuesta por el
dominio (`OrderItem::displayQuantityLabel()`), por el mismo criterio que `date_label` y `time_window`:
el sustantivo depende del tipo de producto y del idioma, y **era la quinta copia** de una regla que el
panel ya rehacía en cuatro blades y un widget. El complemento tenía el mismo defecto una fila más
abajo, y se arregla con él.

**`L3` — «Señal 114,00 €» no es la señal.** El primer importe de la nota es **lo pagado por web de esa
reserva**, que en un pack con complementos cobrados íntegros no coincide con la señal. Se resuelve con
una **clave propia**: la de la CESTA (`deposit_card_note`) se queda diciendo «Señal» porque allí los
dos números **sí** son la señal y su resto; la de una reserva ya comprada pasa a llamar al concepto
como lo llama su línea del desglose (§10.4: un concepto, un nombre), y el método decide cuál.

**Cómo se verificó.** Los tres arreglos van con guarda y **las siete mutaciones matan a su guarda**:
revertir `hasCash()`, quemar el método, quitar la fecha, devolver el número pelado, volver al rótulo
«Señal», re-derivar la condición en el cliente y hacer que el cliente nombre una clave pluralizada.
La red más fuerte es `SidebarAccountParityTest`, que alimenta la composición REAL del cliente con la
respuesta REAL del servidor.

**Cuatro cosas que solo se supieron al hacerlo:**
1. **Un fixture de la paridad tenía la forma exacta de los 18 `DEMO-*` sucios**: `paid` **sin ninguna
   fila `Payment`**, que el flujo real no puede producir. Con esa forma el ancla vale 0 y el caso no
   podía ejercitarla. Es la **quinta** vez que este trabajo tropieza con lo mismo.
2. **`SidebarTextParityTest` cazó un defecto real de la clave nueva**: `entries_count` se escribió con
   la sintaxis de RANGOS de Laravel (`{1}…|[2,*]…`), que el cajón **no resuelve** y habría pintado con
   las llaves dentro. Y su segunda mitad —solo `cart_items` puede llevar barra— obligó a algo mejor
   que una excepción: la exención **se demuestra** comprobando sobre las fuentes del cliente que la
   clave no se nombra ahí. Una lista blanca que nadie verifica es una promesa.
3. **El techo del bundle cedió, y es el caso para el que se escribió**: `214,5 → 215,5 KiB` (medido
   `213,57 → 214,53`, **+0,96**). Su propio comentario dice que **no cede por «una pantalla más», cede
   por corrección medida** — y estos tres salieron de mirar un pedido real. Ni un importe ni una
   condición se calculan en el cliente: recomponerlos habría costado menos bytes y una divergencia.
4. **El panel tenía el método escrito como literal suelto** (`provider === 'redsys'`) y el cliente no
   lo tenía en absoluto: dos definiciones de la misma regla. Ahora la decide el dominio y las dos la
   leen; el literal es una constante (`Payment::PROVIDER_REDSYS`).

⚠️ **Pendiente de veto del owner (una palabra, un sitio cada una)**: `tickets.reservation_paid_note`
(«Pagado por web … · … en el parque»), su variante de taquilla («Ya pagado …») y
`tickets.ledger.cash_caption`. Se decidieron aquí porque el owner delegó explícitamente el rótulo de
§6.3, que llevaba parado desde `#127`.

▶ **Y una quinta cosa, que no está en ningún diff: el eje de caja heredaba el color de REEMBOLSO.**
Sus tres filas usan `.orders__refund-amount`, en ámbar. Con el bloque apareciendo solo cuando había
devoluciones eso era coherente; con el ancla visible en **todo** pedido cobrado, un cargo corriente se
lee como «te devolvimos algo» — el mismo error de fondo que la tanda B quitó del eje del valor,
reaparecido por herencia de estilo. **Hacer visible algo que estaba oculto hereda las decisiones que
se tomaron para el caso oculto**, y ninguna de ellas aparece en el cambio.

---

## #129 · 2026-08-24 · [DECIDIDO] «Mis pedidos» como pantalla propia — y el suelo sobre el que se iba a construir PERDÍA PEDIDOS

**Contexto.** Última de las tres tandas del desglose (`#127` §11). El owner ya tenía sus decisiones
tomadas: «Ver pedido» de una reserva **abre la pantalla de pedidos EN ese pedido, desplegado**; «Mis
pedidos» entra en el índice **y** se llega desde la reserva; «Mis reservas» se queda como está y el
desglose **se muda**. Era, sobre el papel, la única tanda que no toca dinero.

⚠️⚠️ **Y lo primero que apareció no fue la pantalla: fue que `GET /me/orders` perdía pedidos.**
Ordenaba con `->latest()`, o sea **solo por `created_at`**, así que dos pedidos creados en el mismo
segundo no tienen orden entre sí y `LIMIT/OFFSET` corta por donde quiera. **Medido en MySQL sobre los
57 pedidos reales del cliente demo**, con grupos de hasta 11 compartiendo `created_at`: recorriendo
las 6 páginas salían **57 filas y 55 distintas** — dos repetidos y **dos pedidos que su dueño no podía
ver por muchas páginas que pasara** (`R-3FLAXW`, `R-Z167AI`). El arreglo no se inventa:
`CustomerReservationsReader::ordered()` ya cerraba su orden con `order_items.id` por esta razón, un
fichero más allá. Después: **57 de 57, 0 repetidos, 0 invisibles.**

⚠️⚠️ **Y la guarda de CONDUCTA no basta, medido.** La suite corre en SQLite y el barrido sale **verde
sin el desempate**: ese motor devuelve los empates en un orden estable por casualidad. Hizo falta una
guarda **estructural** —el `ORDER BY` de la paginación termina en una columna única— para que muerda
en cualquier motor. Sin ella el caso habría quedado verde con el defecto dentro, que es exactamente el
tipo de red que este proyecto lleva desmontando. Lección en `TESTING.md` §2.sexies.

**`containing`: la página la elige el SERVIDOR.** «Ver pedido» tiene que abrir la pantalla en ese
pedido, y solo el servidor sabe en qué página cae. **Medido**: `R-L6UTIA` está en la **página 7 de
12**; abrir la primera **no falla nada** y deja la decisión incumplida por seis páginas — la forma
silenciosa de no hacer lo que se prometió. Dos reglas de diseño lo acompañan: un código **ajeno o
inexistente se comporta como si no se hubiera enviado** (si respondiera distinto sería un **oráculo de
códigos de pedido**, justo lo que `GET /orders/{code}` evita dando 404 en los dos casos), y **`page`
explícito gana** sobre `containing`.

**El desglose se MUDA, no se copia.** El marcado del ledger sale de `ReservationCard` y entra en
`PurchaseCard`; la composición **no se toca**: es `orderRow()` tal cual, porque el pedido que se
desplegaba desde una reserva y el de esta lista son **el mismo**, servidos por el MISMO
`OrderResource` por dos rutas. Una segunda composición habría sido la **novena** superficie de dinero,
y la paridad lo asevera comparando los `financials` de las dos rutas campo a campo. Con ello se retira
`ensureOrder()` y su caché por código, que no tenían ninguna prueba.

⚠️ **La trampa de nombres, que NO se pudo arreglar y por eso se documenta donde se cae en ella.** La
zona nueva no puede llamarse `orders`: ese valor ya es «Mis reservas» porque es la ruta
`/mi-cuenta/pedidos`, a la que apuntan **8 correos ya entregados** (`AccountDoor`). Reasignarlo
mandaría a esos clientes a otra pantalla sin que nada fallara. Se llama **`purchases`**, y la regla
para no equivocarse queda escrita en `navigation.js`: **el nombre técnico dice de dónde salen los
datos** (`orders` ← `/me/reservations`, `purchases` ← `/me/orders`).

**Se PODÓ antes de subir ningún techo**, que es lo que el propio mensaje del presupuesto pide. Medido:
`orders.subtitle`, `orders.order_hide` y `orders.history.back` viajaban en **cada página con sesión** y
**ninguna superficie del repo los leía** — 205 B devueltos. El grupo nuevo son 298 B que la pantalla sí
pinta, así que el neto es **+93 B** en vez de +298.

**Las OCHO mutaciones matan a su guarda.** Y una decisión de forma que salió de ahí: **la navegación se
movió al STORE**. La primera versión repartía «sembrar el pedido» y «navegar» entre el store y
`OrdersZone.vue`, y la mitad del componente **no tiene red** (un componente pinta y no se prueba con
`node --test`). El precedente es `retry()`, que ya conmuta de sección desde el store.

**Coste**: el chunk del cajón sube `215,5 → 219,5 KiB` (medido +4,10) — es una pantalla entera, y el
desglose no se duplicó: se mudó.

---

## #130 · 2026-08-24 · [DECIDIDO] El owner leyó las dos pantallas — y las dos cosas que dijo eran ciertas

**Contexto.** Con `#129` cerrado, el owner miró «Mis reservas» y «Mis pedidos» con datos reales y dijo
dos cosas: que la pantalla de reservas **no debe enseñar dinero**, y que **seguía sin entender el
desglose**. Las dos tenían razón, y comprobarlas destapó además un defecto que la suite no veía.

**⚠️ El defecto: a «Mis pedidos» le faltaba una línea.** Medido sobre `R-UPFQAB`, un pedido REAL y
correcto: las reservas ponían **120,00 €** y «Valor del pedido» **124,00 €**. Los 4,00 € que faltaban
son un complemento que la API publica y que `PurchaseCard` **no pintaba** —la lista recorría los
principales y no sus `addons`, que sí se pintaban en la tarjeta de la reserva y se quedaron por el
camino al mudar el desglose—. **Un desglose al que le falta una línea no es un desglose**: cuadra por
dentro (`PAY-16` cerraba, la suite verde) y no cuadra para quien lo lee, que es su única promesa.
▶ ⚠️⚠️ **Y la guarda tuvo que ir al MARCADO**: la de composición mira `purchaseRows()`, que **sí**
compone los complementos, así que un `v-for` que dejara de recorrerlos la deja verde. Medido por
mutación: `v-for="addon in []"` pasaba los 2.740 casos.

**«Mis reservas» deja de enseñar dinero.** Fuera el importe de la línea y la nota «Pagado por web X ·
Y en el parque». Esa pantalla responde a «¿qué tengo y cuándo?»; el dinero es del PEDIDO y vive entero
a un clic. Se quedan la fecha, la cantidad, los complementos **sin precio** —dicen qué llevas
contratado—, el post-form, los datos del evento y la referencia con «Ver pedido».
▶ ⚠️ **Con ello muere `depositNoteOf()` y las dos claves de `L3`**: el rótulo que `#128` arregló, y
cuya palabra estaba pendiente de veto del owner, **se queda sin sujeto en el cliente**. La de la CESTA
sigue viva y sigue diciendo «Señal», que allí es correcto. `shows_deposit_note` sigue publicado: es un
hecho de dominio y el contrato lo consume también la app móvil.
▶ ⚠️ **Su guarda mira el MARCADO y no la composición**, y no hay alternativa: `lineRow()` sigue
componiendo `priceLabel` porque «Mis pedidos» la pinta. Lo que se decidió es qué pinta cada pantalla.

**«Sigo sin entender el desglose»: el mismo importe salía DOS veces.** `#128` (`L1`) puso el eje de
caja en «siempre que haya habido un cobro», y en un pedido corriente los dos importes **coinciden por
construcción** (`PAY-17`): «Pagado por web 30,00 €» arriba y «Cobrado por web 30,00 €» abajo son la
misma frase con las palabras en otro orden. Un bloque que repite lo de arriba **no se lee como una
reconciliación: se lee como ruido**, y enseña a saltarse el bloque el día que sí importa.
▶ **La regla correcta es «¿dice algo que el eje del valor NO diga ya?»**: hubo devolución, se debe
una, o **lo cobrado no coincide con lo pagado**.
▶ **Y ese tercer término conserva ENTERO lo que `L1` vino a arreglar**: en un pedido sano los dos
importes no pueden diferir, así que solo difieren **cuando el dato está roto**. Medido sobre
`R-L6UTIA`: el bloque sale, con 30,00 € cobrados frente a 114,00 € «pagados».
▶ **Y lo verificable no se pierde: la FECHA se muda a la línea del canal.** «30,00 €» no se busca en
un extracto bancario; «Pagado por web · 24/08/2026 — 30,00 €», sí. ⚠️ Es lo que el **panel ya hacía**
desde `P1/P10`: las dos superficies convergen otra vez, ahora en la forma buena.

⚠️⚠️ **Lo que esto enseña, y no es sobre estas dos pantallas.** Al diseñar `L1` quedó escrito que el
número repetido «es la prueba de que todo cuadra». El owner lo leyó y no entendió la pantalla. La
conclusión no es que la redundancia fuera mala idea: es que **el autor no puede decidir solo si algo
se lee**, y en una pantalla de dinero eso no es una cuestión de gusto.
▶ Y los dos defectos de esta vuelta tienen algo en común: **los dos pasaban la suite entera**. El
primero porque la guarda miraba la composición y el defecto estaba en el marcado; el segundo porque
no hay test que mida si algo se entiende.

---

## #131 · 2026-08-24 · Revisar `R-L6UTIA` a fondo destapó DOS defectos más — y uno lo había metido yo el día anterior

**Contexto.** El owner pidió revisar con rigor el pedido cuyo desglose «no se entiende». Ya estaba
escrito que el DATO está roto (`#128` §17). Mirarlo otra vez, línea por línea y sin dar nada por
bueno, encontró **dos defectos de producto que nada tienen que ver con ese dato**, y uno de ellos era
una regresión de `#130`.

**⚠️⚠️ 1 · LA FECHA SE PEGABA A UN IMPORTE QUE NO SE COBRÓ ESE DÍA.** `#130` mudó la fecha del cobro a
la línea «Pagado por web» para que el importe se pudiera buscar en un extracto. Pero `paid_online` es
el canal del VALOR —«lo cobrado por web que respalda producto vivo, **neto de compensación**»—, así
que **en cuanto hay una devolución deja de ser lo que se cobró ese día**.
▶ **Medido sobre los 58 pedidos: pasaba en 7, y SEIS estaban SANOS.** `R-BEMOOI` decía «Pagado por web
· 24/08/2026 — 9,90 €» cuando ese día se cobraron 19,80 € y se devolvieron 9,90 €. El cliente miraría
su extracto y **no encontraría ese importe** — justo lo contrario de lo que la fecha venía a lograr.
▶ **La regla**: la fecha acompaña al importe **solo cuando coinciden**, que es exactamente cuando el
bloque «Tu dinero» no se pinta (`!has_cash` ⟹ `charged_online === paid_online`, porque ese término es
uno de los tres del predicado). Cuando difieren, la fecha va abajo, con el importe que sí se cobró.
**Un solo predicado del dominio gobierna las dos mitades.**

**⚠️⚠️ 2 · «↳ Cumpleaños Jump 12,00 €» no dice POR QUÉ se cobra.** Bajo «Pendiente de pagar en el
parque», el nombre pelado del producto se lee como «te cobramos 12,00 € de Cumpleaños Jump» sin decir
de dónde sale — mientras su línea hermana, «Resto de la señal de X», sí se explica sola. Y la única
leyenda que se pinta es la de la señal, así que el cargo por cambios **se queda sin explicación**.
▶ **Y no es una rama de datos sucios, que es lo que parecía**: medido sobre los OCHO `extra_due` de la
BD, **los ocho** caen en ese respaldo — incluidos los **cinco escritos por el flujo REAL del panel**,
que guarda `context = {"changes": []}` y por tanto no permite a ninguna rama describir el cambio. Es
decir: **es la etiqueta que sale en producción**, no la excepción.
▶ Pasa a ser «Diferencia por cambios en X». Las otras tres ramas —«+4 X», «Cambio a X», la lista de
complementos— se explican solas y no se tocan; hay caso que lo asevera para que el respaldo no se las
coma.

**⚠️ 3 · Y una tercera cosa, que NO se ha tocado porque es decisión de producto.** `PAY-16` y `PAY-17`
son guardas **de test, no de ejecución**: un pedido cuyo desglose no cierra —`R-L6UTIA`: 3.000 ≠
11.400— se sirve al cliente **como si nada**, con dos importes que se contradicen y una frase que
habla de otra cosa. Ni se registra, ni se avisa, ni el contrato lo publica. Medido: el `LedgerResource`
no lleva ningún campo de consistencia y `nota` dice «Te quedan 102,00 € por pagar en recepción».
▶ Queda planteado al owner: qué se le enseña a un cliente cuando su desglose no cuadra, y si el parque
se entera. Lo único que no admite discusión es que **hoy no se entera nadie**.

⚠️ **Lo que esto enseña, y es la tercera vez en dos días**: los tres defectos —la línea que faltaba de
`#130`, la fecha mentirosa y la etiqueta muda— **pasaban la suite entera**, y los tres salieron de
mirar una pantalla concreta con datos reales. Las guardas cubren que los números cuadren; **ninguna
cubre que lo que se lee sea cierto**.

---

## #132 · 2026-08-24 · [DECIDIDO] Un desglose que NO CUADRA deja de servirse como si nada — y la auditoría de las 25 acciones

**Contexto.** El owner pidió dos cosas: borrar todos los pedidos y **reconstruirlos ejecutando cada
acción accionable** para ver qué falta, y resolver lo que `#131` §21.3 dejó planteado —qué ve un
cliente cuyo desglose no cuadra y cómo se entera el parque—.

### La auditoría: 25 casos por los flujos REALES

Se borró el corpus entero (58 pedidos) y se reconstruyó **por los flujos reales** —`OrderCreator` →
vuelta de Redsys FIRMADA → acciones del panel conducidas con Livewire—, un pedido por acción:
5 estados base · 13 acciones sobre la RESERVA · 5 sobre el PEDIDO · 2 que deben bloquearse.

    25 casos · 0 acciones fallaron · 2 bloqueadas correctamente (complemento suelto), con su audit
    25/25 · las DOS identidades cierran
    25/25 · lo que la pantalla LISTA suma el total, y los CANALES suman el total

▶ **Es la primera vez que se mide sobre un corpus construido acción por acción**, y el modelo aguanta.

⚠️⚠️ **Cuatro trampas de MÉTODO que costaron cuatro corridas** y que la receta de §12.6 no recogía:

| Trampa | Qué pasaba |
|---|---|
| **El cambio de FECHA lo conduce el CALENDARIO, no el formulario** | Con `slot_date`/`slot_time` en `data`, la acción se ejecuta, **no da error y no cambia nada**. Hay que poner tres propiedades Livewire (`calendarItemId`, `calendarSelectedDate`, `calendarSelectedTime`) |
| **Un doble con la firma equivocada = «Premature end of PHP process»** | `RefundGateway::executeRefund(Payment, int)`, no `refund(string, int, string)`. PHPUnit lo reporta sin clase, sin línea y sin pista |
| **El MOTIVO del reembolso es obligatorio** (`#127(c)`) y `refundItem` solo acepta DOS de los tres | `value_returned` no es opción ahí: esa acción no cancela la línea |
| **QUINTA puerta de `OrderCreator`**: rechaza una fecha PASADA | Para el caso «franja ya disfrutada» hay que crear en su día real y mover el reloj al leer |

⚠️ Y una de repetición: **cada corrida consume aforo**. Sin borrar antes y sin repartir los packs por
horas distintas, la segunda corrida muere con `pack_sold_out_line`.

### Lo que la auditoría dejó claro que FALTA (spec §22.2)

Tres cosas de LEGIBILIDAD, ninguna de aritmética: **«Compensación devuelta» como única línea de un
pedido de 19,80 €** —el cliente lee que su pedido «vale» una devolución—, **el resto de la señal de un
complemento escondido dentro de la línea del principal**, y **una bajada que no deja más rastro que
«Importe al reservar»**. Quedan planteadas, no resueltas: son decisiones de producto.

### La decisión: qué se enseña cuando el desglose no cierra

`PAY-16` y `PAY-17` eran guardas **de TEST**. Un test dice que el CÓDIGO está bien hoy; **no dice que
ESTE pedido esté bien ahora**, y es la segunda pregunta la que ve el cliente. Un pedido con el dato
corrupto se servía con dos importes que se contradicen, sin aviso, y con una frase que hablaba de otra
cosa. Ni log, ni campo en el contrato, ni nada: **no se enteraba nadie**.

▶ **Ahora las dos identidades se evalúan EN EJECUCIÓN** (`OrderLedger::cuadra`, publicado como
`ledger.is_consistent`) y hay **asimetría deliberada**:

- **al CLIENTE se le oculta la descomposición**: si los números no cierran, ninguna línea por canal es
  cierta. Se queda lo que **sí es un hecho** —lo que vale el pedido y lo que se le cobró, con su
  fecha— y una frase honesta: «*Estamos revisando el detalle de este pedido…*». Esa frase va **la
  primera** en `noteFor()`, antes que todas: sobre un desglose roto, ninguna otra puede ser cierta;
- **al OPERADOR se le ENSEÑA**, con un aviso en rojo sobre el bloque de totales: es quien puede
  arreglarlo, y esconderle la contradicción sería quitarle justo el dato que necesita;
- **y el parque se entera**: `Log::warning('ledger.no_cuadra', …)` con el código y las dos identidades.
  Va como aviso y no como excepción porque el desglose se compone **al pintar**: reventar dejaría al
  cliente sin pantalla por un dato que ya estaba mal.

▶ **Tres mutaciones, las tres muerden**: dar `cuadra` por cierto, quitar la frase de revisión, y que el
cliente ignore `is_consistent`.

⚠️ **Y la deuda que esto NO paga**: el aviso dice que algo está mal, no QUÉ. Un pedido roto sigue
necesitando que alguien lo mire. Lo que cambia es que ahora **hay alguien a quien avisar**.

---

## #133 · 2026-08-24 · [DECIDIDO, owner] De las tres cosas que la auditoría dejó planteadas, solo entra UNA — y una NO era un defecto

**Contexto.** §22.2 dejó tres asuntos de legibilidad planteados tras la auditoría de las 25 acciones,
con el encargo explícito del owner de **no añadir más complicación**. Recomendación pedida y dada; la
decisión, tomada.

**⚠️ `L5` NO ERA UN DEFECTO, y el que leyó mal fue el agente.** «Resto de la señal de Cumpleaños Jump
94,00 €» parecía esconder los 4,00 € de unos calcetines dentro de la línea del pack. Medido sobre el
pedido real (`R-FTC08J`): la línea agrega el principal (90,00) **y sus complementos** (4,00), y
«Cumpleaños Jump» ahí **no es el producto de 120,00 €: es la RESERVA**, que es exactamente como el
cliente la ve listada arriba («Cumpleaños Jump · Mar. 25 ago. · 8 invitados»). La etiqueta ya es
correcta. Separarla habría añadido una línea de 4,00 € a una pantalla de dinero.
▶ **Se queda como está.** Y queda escrito para que nadie vuelva a «arreglarlo».

**`L4` se APARCA.** «Compensación devuelta» como única línea del valor sigue leyéndose como un canal
de pago, y la propuesta era cambiar un rótulo («Ya no tienes que pagarlo»). El owner decide **dejarlo
como está por ahora**: la frase de estado ya dice lo que el cliente necesita («Te devolvimos X y
conservas tu reserva: no tienes que pagar nada más»).

**✅ `L6` ENTRA, y es lo único que entra.** La línea «Importe al reservar» dice *que* el pedido cambió
pero no **en qué dirección ni cuánto**, y es justo lo que el cliente quiere saber al ver 180,00 €
donde espera 120,00 €. La frase pasa a componerla el DOMINIO con dirección e importe:

    «Al reservar se facturaron 180,00 €. El pedido cambió después y ahora vale 60,00 € menos.»

▶ **Ni una línea nueva, ni un bloque nuevo, ni un concepto nuevo**: la misma línea y la misma frase,
diciendo algo útil. Es el criterio que el owner puso —«que lo entienda sin complicación ni fricción»—
y el que ordena las tres decisiones.

⚠️ **NO se implementa en esta sesión** (decisión del owner: «será en el siguiente chat»). Queda como
el próximo paso, con su redacción ya acordada.

## #134 · 2026-08-24 · `L6` EJECUTADO — «Importe al reservar» dice hacia DÓNDE y CUÁNTO, y la condición se va con la frase

**Qué se hizo.** Lo único que `#133` dejó aprobado, con la redacción ya acordada por el owner y sin
añadir ninguna línea: la frase del pie del desglose pasa a decir **dirección e importe**.

    ANTES  «Es lo que se facturó al hacer la reserva. Si no coincide con el valor de arriba
            es porque el pedido cambió después.»                       (fija, en JavaScript)
    AHORA  «Al reservar se facturaron 180,00 €. El pedido cambió después y ahora vale
            60,00 € menos.»                        (compuesta por el DOMINIO, dos claves)

La compone `OrderLedger::invoicedNoteFor()` y viaja publicada como `Ledger.invoiced_hint`, igual que
la frase de estado: elegir entre «más» y «menos» es decidir qué caso es, y eso es regla.
⚠️ **El importe que nombra es la DIFERENCIA, no el valor.** El valor ya está dos líneas más arriba;
publicarlo otra vez daría una frase que suma bien y no dice nada.

**⚠️⚠️ Y lo que de verdad importa: la CONDICIÓN se movió con la frase.** Publicar la frase habría
bastado para cumplir el encargo. Lo que se hizo además fue quitarle al cliente la comparación:
`invoiced_hint` vale `null` **exactamente** cuando lo facturado coincide con el valor, así que la
pantalla ya no pregunta «¿difieren los importes?» sino «¿me han mandado frase?».
▶ Es `L1` aplicado antes de que cueste (`#128`): allí el predicado existía en el dominio, **ninguna
superficie lo llamaba**, y las dos que lo re-derivaban divergieron en **19 de 58** pedidos sin que
nada fallara. Aquí los dos importes siguen viajando en el mismo objeto, así que una recaída daría el
mismo resultado en todos los casos normales — y solo se notaría cuando ya hubiera divergido.

**Medido, no afirmado**, sobre el corpus de las 25 acciones servido por HTTP real y compuesto por el
módulo REAL del cajón con el diccionario REAL: **26 pedidos, 11 publican frase** (6 subidas, 5
bajadas), **0 con importes distintos y sin frase**, **0 con importes iguales y con frase**. Los dos
casos canónicos salen literales: `R-LVWTRS` (180 → 120) «… vale 60,00 € menos» y `R-NKEASV`
(120 → 180) «… vale 60,00 € más».

**[DECIDIDO owner] El pedido que se queda en 0 usa la MISMA frase.** Tres de los 11 son cancelados o
vaciados por un reembolso, y ahí se lee «ahora vale 19,80 € menos» justo debajo de «Tu reserva se
canceló el 24/08/2026». Consultado antes de implementar: **una tercera variante sería un concepto más
en tres idiomas para decir lo que la frase de estado ya dice una línea antes**, que es exactamente lo
que `#133` vino a evitar.

**⚠️⚠️ Y una guarda que NACIÓ DECORATIVA, que es lo que este trabajo enseñó.** La primera versión de
la comprobación de idiomas miraba el resultado de `__('…', [], 'fr')` y verificaba que no fuera la
clave en crudo. **Borrando `invoiced_hint_more` de `lang/fr` seguía en verde**: Laravel cae al
`fallback_locale`, así que una clave que falta **no se manifiesta como una clave en crudo** sino como
un cliente francés leyendo castellano en su pantalla de dinero — un fallo más silencioso todavía,
porque parece texto. La comprobación correcta es `Lang::has($clave, $locale, false)`.
▶ La lección no es sobre idiomas: **una frase que compone el dominio falla distinto que una del
cliente**, y la guarda tiene que mirar el mecanismo del fallo real. Va por la cuarta vez en esta
spec, y las cuatro se descubrieron rompiendo el código a propósito.

**Guardas: +4 PHP y +2 JS, y las CINCO mutaciones muerden** — publicar el valor en vez de la
diferencia · una sola clave para los dos sentidos · que el cliente re-derive la comparación · que el
cliente lea la frase del diccionario · que a un idioma le falte una clave.

**▶ Con esto el DESGLOSE DE DINERO DEL CLIENTE queda CERRADO.** `L4` aparcado y `L5` retirado
(`#133`). Detalle en `specs/desglose-dinero-cliente.md` **§23**.

## #135 · 2026-08-25 · `app:create-admin` imprimía una contraseña que NO era la que abría el panel — 1 de cada 158

**Cómo apareció.** El `pre-push` del despliegue a staging se puso **rojo** en
`CreateAdminTest::the_printed_password_is_the_one_that_actually_authenticates`, un test que había
pasado en verde toda la sesión anterior. Corriéndolo solo: verde. En paralelo: verde. Tenía toda la
pinta de un test intermitente al que se le vuelve a dar a «empujar».

**⚠️⚠️ No era un test intermitente: era un DEFECTO DEL PRODUCTO que solo se manifiesta el 0,63 % de
las veces.** El formateador de consola de Symfony trata `\<` y `\>` como delimitadores de etiqueta
**escapados** y se come la barra invertida. `Str::password(24)` —la que genera este comando— incluye
`\`, `<` y `>` en su alfabeto, así que una contraseña con esa pareja **se imprimía distinta de como
se guardaba**.

    generada: [i[xTz&5|Ud\<8n8}]VR-;Md
    impresa:  [i[xTz&5|Ud<8n8}]VR-;Md      ← ésta es la que lee el operador, y NO abre

▶ **Medido: 200.000 contraseñas generadas, 1.263 (0,63 %) no sobreviven a la consola.** Una de cada
**158**.

**Por qué importa, y no es cosmético.** `app:create-admin` es el mecanismo canónico del PRIMER ADMIN
de una instalación (`#103`, `deploy.sh --go --admin-email=…`). La contraseña **se enseña una sola vez
y no queda guardada en claro en ningún sitio**. En esos casos el owner se quedaba **fuera de su
propio panel sin ninguna forma de recuperarlo** salvo volver a lanzar el comando — que es
literalmente el modo de fallo que el docblock del test dice vigilar.

**El arreglo**: la contraseña se escribe en **RAW** (`OutputInterface::OUTPUT_RAW`) en vez de por
`line()`, que la pasa por el formateador. El rótulo y el usuario siguen igual.

**⚠️ Y la guarda existía y era la EQUIVOCADA.** `the_printed_password_is_the_one_that_actually_
authenticates` genera la contraseña al azar, así que **acertaba el 0,63 % de las veces**: no
protegía, sorteaba. Y su forma de fallar —roja de vez en cuando, verde al re-lanzar— es la que
**enseña a ignorarla**. Se le añade al lado `the_printed_password_survives_the_console_formatter`,
que fija la pareja `\<` y `\>` en la cadena en vez de esperar a que salga premiada.
▶ **Verificado por mutación: con el defecto puesto, la guarda nueva cae 10 de 10 y la vieja pasa
10 de 10.**

**▶ La lección, que no es sobre consolas**: un test que solo falla a veces está diciendo algo, y lo
primero que hay que preguntarle es **qué es lo que varía entre corridas**. Aquí lo que variaba era el
dato, y el dato era el producto. Re-lanzar el `pre-push` habría enterrado un fallo que deja al
cliente sin panel.

**⚠️ Pendiente, del mismo tipo pero sin bloquear** (queda anotado, no arreglado): `app:set-setting`
imprime «comprobado en BD: «…»» pasando el valor por el mismo formateador. Es un eco de
VERIFICACIÓN, así que con un valor que lleve `\<` mostraría algo distinto de lo guardado. Los
secretos van enmascarados, y el operador escribe el valor él mismo, así que no hay bloqueo — pero un
eco de verificación que puede mentir merece decidirse, no ignorarse.

## #136 · 2026-08-25 · [DECIDIDO, owner] La landing white-label: data-driven el DATO, no la PÁGINA

**Contexto.** Preparando la landing del **segundo cliente**, el owner enunció cinco problemas: el CMS
incompleto, lógica de reservas escrita en la landing, datos duplicados entre landing y CMS, `/servicios`
sin leer del sistema de reservas, y que cada cliente quiere otra organización y otro diseño. Se
midieron los cinco contra el código antes de diseñar nada. Spec: `specs/landing-white-label.md`.

**⚠️⚠️ Tres de los cinco no eran lo que parecían, y eso cambió el diseño:**

- **«El CMS está incompleto» era PARCIALMENTE FALSO.** De todo lo que pide el mockup del cliente
  nuevo, **solo faltan DOS modelos** (los números del hero y los testimonios): `zones` ya tiene
  `age_range`, `area_sqm` y `rides_count`; `attractions` ya tiene `age` y `badge`; `ticket_types` ya
  tiene `min_qty`/`max_qty`, `deposit_*` y `features`. El trabajo no es construir un CMS: es
  **enchufar la landing a lo que ya sabe**.
- **«Datos de la landing duplicados en el CMS» era AL REVÉS**: el copy vive en el REPO — **142 claves**
  en `lang/es|en|fr/landing.php`—, así que un cliente **no puede cambiar su titular sin desplegar**.
- **«/servicios no lee del sistema» era CIERTO y peor.** Los tres servicios tienen **0 productos
  vinculados** y dos llevan una tabla de precios **tecleada a mano** (`landing_services.price_table`).
  ▶ Y se midió el porqué: de los tres ejes de esa tabla el catálogo ya expresa dos —la duración es un
  producto, entre semana/finde es un `rate_type`— y **no expresa el tramo por tamaño de grupo**. Por
  ese único hueco se copió la tabla entera, y los precios de finde quedaron en dos sitios que pueden
  separarse sin que nada avise.

**La medida que da la línea.** Secciones de la landing actual contra las del mockup nuevo: `zones ·
rides · pricing · info · gallery · reserve` frente a `zonas · cumpleaños · normas · info · opiniones ·
reservar`. **Coinciden 3 de 6, y son exactamente las tres que cuelgan del motor de reservas.** La
mitad estable es la del dominio; la volátil es editorial.

**▶ DECIDIDO: data-driven el DATO, no la PÁGINA.** Todo lo que la landing enseñe sale del dominio o
del CMS; la composición de la página es un **paquete de tema por cliente, en código**. Cliente nuevo =
plantilla nueva, no modelo de datos nuevo.
⚠️ **Descartado un maquetador visual**: es lo más caro de construir y mantener, y **no resolvería el
caso que lo motiva** —el mockup trae minijuego, menú de velas y hero propio; ningún maquetador
genérico produce eso—.

**⚠️ Y el TEMA no es un binario: son TRES mecanismos.** Fue el owner quien corrigió el encuadre.
Valores (color, radio, tipografía) → **tokens desde BD**: la costura existe —28 variables y 1.271 usos
de `var()`— y **nada las alimenta**. Ficheros (logo, dibujo del spinner) → **assets por instalación**:
`spinner.css` ya es un fichero, una clase y 15 consumidores, con tres tokens propios. Dibujos
compartidos cajón↔landing → **el sistema de iconos, que YA EXISTE**: 22 iconos usados por las dos
superficies, y `SidebarIconParityTest` impide que se separen —nació de que el cajón se sirvió con 20
`<svg>` VACÍOS sin que ningún gate se enterara—.
▶ **Lo que el owner temía imposible ya se puede hacer hoy**, una sola vez y con guarda.

**[DECIDIDO] El icono por producto: SET CURADO, no subida libre.** Hoy lo decide un booleano (tarta si
es pack, ticket si no) y `ticket_types` no tiene columna de icono. Pasa a ser una clave del sistema de
diseño, elegible en el panel. A cambio de que añadir uno nuevo sea un despliegue, el catálogo no se ve
descuidado, **la paridad cajón↔landing sigue pudiendo comprobar el dibujo** —con SVG subidos sería
imposible— y se evita sanear código ejecutable subido por el operador.

**[DECIDIDO] Los servicios pasan al sistema de reservas DE VERDAD**, no se maquillan en la página. Los
tres son la misma forma de producto: **reserva de grupo privada fuera del horario de apertura**, con
precio por persona y por tramo de grupo.
⚠️⚠️ **Y por eso va en su propia tanda y necesita su propia spec**: toca `AFORO-01`, `AFORO-02`,
`AFORO-03` y las identidades de `PAY`, exige `VERIFY_CONC=1` y **no se puede verificar en SQLite ni en
staging** (MariaDB). Medido: **18 ficheros** tocan la resolución de precio, así que el tramo se
consulta solo donde se cobra y las demás superficies siguen rotulando «desde». Anunciarla como «tres
extensiones aditivas» sería repetir el error de dimensionado que `specs/desglose-dinero-cliente.md`
§11 ya documenta.

**Orden acordado**: **A** el tema (barato, no toca dominio) · **B** el contenido y la landing del
segundo cliente como primer paquete de tema · **C** los servicios como producto real, la última y con
spec propia.

## #137 · 2026-08-25 · [DECIDIDO, owner] Redis: SÍ, requisito DURO, y SOLO para caché

**Contexto.** El primer punto de la Fase 5 pedía caché **etiquetada** y llevaba desde el 2026-08-23
declarado no implementable: el store es `database`, que **lanza** al usar tags. El owner decide usar
Redis («el hosting EnhanceCP lo permite») y pide valorarlo **con medida, no con suposición**. Detalle
de la máquina en `ENTORNOS.md` §4.

**⚠️⚠️ Y lo primero medido fue que una inferencia razonable era FALSA.** El host tiene una unidad
`redis-server@.service` —la plantilla de Debian: instancia por sitio, `port 0`, socket unix en
`/run/redis-<sitio>/`— y **Enhance no la usa**. El panel arranca el demonio **dentro del contenedor
PHP del sitio**, en `127.0.0.1:6379` y sin contraseña. Leer la plantilla del sistema y dar por hecho
que era el mecanismo habría llevado a configurar una conexión por socket que no existe.

**Lo verificado, punto por punto** (staging, 2026-08-25):

| Qué | Resultado |
|---|---|
| Aislamiento | **Instancia propia** — `config_file` = `<HOME>/redis.conf`, `pid 4`, keyspace vacío |
| Configuración de Laravel | **Cero variables que tocar**: los valores por defecto (`127.0.0.1:6379`, `REDIS_CACHE_DB=1`) ya coinciden |
| **Tags contra el Redis REAL** | ✅ escribir con tags, leer, `flush()` de UN tag → invalida la entrada |
| **Camino WEB** (no solo la CLI) | ✅ una petición HTTP real dejó `cta.min_price_cents` en la db 1 vía PHP-FPM |
| `phpredis` | **6.3.0, SIN igbinary** — configurar ese serializador reventaría |
| **Redis caído** | ❗ **500 en 0,14 s** — falla rápido, pero es dependencia DURA |

**[DECIDIDO] Requisito DURO: sin Redis no se instala un cliente.** Es la consecuencia honesta de usar
tags —no hay repliegue: `database` lanza—. `deploy.sh` gana guarda (`CACHE_STORE=redis` + un PING en
vivo **antes** de servir tráfico) y `INSTALACION-CLIENTE.md` lo declara. Sube el listón de dónde se
puede vender, y lo sube de verdad.
⚠️ Se descartó el camino blando (dos rutas, tags o versión de clave) porque **hay que mantener las
dos y la que casi nunca se usa es la que se rompe sin que nadie lo note**.

**[DECIDIDO] SOLO caché. Sesión y cola se quedan en base de datos**, y el argumento que lo cierra
salió de medir, no del manual: **Redis vive DENTRO del contenedor PHP**, así que cualquier reinicio
—incluido el botón del propio panel para aplicar `redis.conf`— se lo lleva por delante.

- Para una **caché** eso es un arranque en frío y ya está.
- Para las **sesiones** sería cerrar la sesión de todos a la vez en cada reinicio, incluido quien esté
  en el paso de pago.
- Para la **cola** sería perder jobs encolados: los correos de pedido pagado (`PAY-14`), y además
  `deploy.sh` tiene la `GUARDA 6` exigiendo `QUEUE_CONNECTION=database`.

▶ Y moverlas después cuesta **una variable de entorno y una guarda**: no se cierra ninguna puerta.

**⚠️⚠️ DOS COSAS QUE ESTO OBLIGA, y no son opcionales:**

1. **El token de la vuelta de Redsys SALE de la caché.** `Cache::put('redsys.return:…', …, 5 min)` es
   un pase de un solo uso que decide si quien acaba de pagar ve «¡Reserva creada!» o la home vacía.
   Hoy vive en `database`, donde **no se puede desalojar**; en una caché con `allkeys-lru`, sí. Pasa a
   `Cache::store('database')` **explícitamente**: tres líneas, cero esquema nuevo. Poner `noeviction`
   para protegerlo sería peor —la caché daría error al llenarse en vez de desalojar—.
2. **Redis en el stack LOCAL y en la suite, o los tags no se pueden verificar.** Medido ejecutándolo:
   `array` **soporta tags** y `database`/`file` **lanzan**. La suite corre en `array`, así que
   **un test con tags saldría VERDE y reventaría en la primera petición de producción**. Es la misma
   trampa que `#129` («la guarda de conducta salía VERDE en SQLite»), y sin cerrarla no hay forma de
   saber si funciona hasta que lo descubra un cliente.

**⚠️ APLAZADO a propósito (owner): afinar `redis.conf`.** De fábrica son 14 bytes y se comporta como
un almacén: `maxmemory 0` (sin techo), `noeviction`, snapshots activos y
`stop-writes-on-bgsave-error yes` —si falla un volcado, **deja de aceptar escrituras**—. El contenido
acordado, para cuando toque: `maxmemory 256mb` · `maxmemory-policy allkeys-lru` · `save ""`. Ficha
abierta en `DEUDA.md` con su riesgo medido.
⚠️ Aplicarlo exige **reiniciar el contenedor PHP desde el panel**, que es acción del owner.

## #138 · 2026-08-25 · TANDA A · El acento de zona deja de viajar por el nombre de una clase

**Contexto.** Primer corte de la tanda A de `specs/landing-white-label.md`. Y lo primero que la
medición hizo fue **desmentir a la propia spec**.

**⚠️⚠️ LA SPEC AFIRMABA «cero variables se inyectan desde BD», Y ERA FALSO.** El tema **sí** se
inyecta: `layout.blade.php` y `focused-layout.blade.php` emiten `<style id="jj-theme">:root{…}</style>`
con `ThemeSettings::cssRootDeclarations()`, hay un ajuste `theme.brand` editable en el panel, el
contraste se calcula por luminancia (`onBrand()`) y lo consumen también el panel Filament, el botón de
los correos y los avatares.
▶ **El error fue de MEDICIÓN, no de lectura**: el `grep` buscaba `style="--`, `--c-` y `setProperty`,
y la forma real no casa con ninguno. Un `grep` que no encuentra **no demuestra que no exista** — es la
misma lección que el `pre-push` de `#135` y la guarda decorativa de `#137`, tres veces en dos días.

**El defecto REAL, y estaba vivo.** El color de una zona viajaba por **dos caminos**:

- la **tarjeta** lo tomaba de `zones.color` —el suyo—, saneado por `colorForAccent()`;
- la **pestaña** lo tomaba de una regla `.zone-tab--{accent}` que leía `--kids-1`, o sea **el color de
  la PRIMERA zona con ese acento**.

▶ Medido sobre la BD de desarrollo: las zonas `cap` y `cap2` tienen `accent=kids` y `color=#FF5B22`,
así que **su tarjeta salía naranja y su pestaña lima**. El mismo sitio, dos colores, y nada fallaba.

**Y la otra mitad era peor para un producto white-label:** esas reglas **solo existían para `jump` y
`kids`** —los acentos del primer cliente—, en **diez clases** repartidas por dos hojas. Una
instalación con zonas propias perdía el tinte **en silencio**: sin error, solo una landing sosa.

**▶ DECIDIDO (owner): el acento se generaliza a «acento de la zona activa».** Las diez clases
desaparecen y cada superficie **pinta el color en línea**, compuesto una sola vez por
`ThemeSettings::zoneStyle()` — `--zone-1`, `--zone-2` y `--on-brand`, que es exactamente lo que esas
reglas re-escopaban a mano. Misma lección que `OrderLedger`: una composición, N superficies que la
pintan.
⚠️ Y **quita CSS en vez de añadirlo**: `.zone-tab.active` ya era genérica en `landing.css`, así que las
seis reglas que la sobrescribían por acento sobraban.

**Tres cosas más que la medición obligó a arreglar:**

1. **`zones.color_secondary`** (migración aditiva, nullable). La paleta de una zona son DOS colores y
   solo el primero tenía casa; el segundo estaba escrito en `landing.css` como `--jump-2`/`--kids-2`.
   ⚠️ Vacío ⇒ **se usa el primario**, nunca el de otra zona: una zona sin paleta doble se pinta plana,
   que es correcto, en vez de pedir prestado un acento ajeno.
2. **`theme.brand_secondary`** (ajuste nuevo, ColorPicker). El `--zone-2` de página valía
   `var(--jump-2)`: el amarillo de una zona del primer cliente, quemado para toda instalación.
3. **Un color con significado no puede vivir en la paleta de una zona.** `.cal__day--special`,
   `.cal__dot--special` y `.orders__status--pending` usaban `--jump-2` como «atención». Pasan a
   `--attn`, con token propio: cambiar la paleta de Jump repintaba el calendario y el estado de un
   pedido sin que nadie lo pidiera.

**⚠️ Una trampa de Blade que costó una vista rota, y conviene no volver a pagarla.** Blade **no
compila `@endif` cuando va pegado a un carácter de palabra**: `style="…@if (! $loop->first)display:none@endif"`
compila el `@if` y **deja el `@endif` como texto**, y el error sale como `ParseError: unexpected token
"endforeach"` en la vista COMPILADA, lejos del sitio. El estilo se compone ahora en PHP.

**Guardas: +7, y las mutaciones muerden.** `ZoneAccentIsNotAClassNameTest` prohíbe el **mecanismo**
—ninguna plantilla mete el acento en un nombre de clase— con su guarda-de-la-guarda, y lleva una
**lista que solo ENCOGE** de los ficheros que aún nombran la paleta del primer cliente (queda el
diseñador de invitaciones, `.bd-*`). En `ThemeColorTest`, los cuatro casos de conducta: dos zonas con
el mismo acento ya no comparten color, un acento desconocido igual se pinta, el secundario cae a su
propio primario y el secundario de marca llega al `:root`.
⚠️ **Y dos tests existentes aseveraban el NOMBRE DE LA CLASE** (`zone-tab--jump`): decía que la
plantilla escribió el acento, no que llegara el color. Ahora aseveran **el color**, que es lo que se
ve — más fuerte, y válido para cualquier acento.

**Verificado sobre HTTP real**: `/precios` sirve un estilo por zona con su color propio —`cap` ya sale
naranja en pestaña **y** tarjeta— y **cero clases acopladas a zona** en el HTML servido.

## #139 · 2026-08-25 · TANDA A · La paleta del primer cliente sale del producto — y el acento de un pack deja de deducirse de su NOMBRE

**Contexto.** Segundo corte de la tanda A, continuación de `#138`. Cierra lo que aquél dejó anotado:
el diseñador de invitaciones era **lo último** que nombraba la paleta del cliente origen.

**⚠️⚠️ Y lo que había debajo era peor que el CSS.** El acento de un pack se decidía así:

    $packAccent = fn ($p) => str_contains(Str::lower($p->tr('name')), 'kids') ? 'kids' : 'jump';

**Buscar una subcadena en el NOMBRE del producto.** Funcionaba con los dos packs del primer cliente y
con nadie más: en una instalación cuyos packs se llamen de otra forma **todos** caían al acento
`jump` y la sección entera se pintaba con el naranja de una marca ajena, sin que nada fallara. Y la
respuesta estaba en la BD todo el tiempo — un pack tiene `zone_id`, y las dos rutas que pintan esa
sección ya cargaban la relación.

**Lo que se retira, y ya no vuelve:**

- el heurístico del nombre → la paleta sale de la **zona del pack**, compuesta por `zoneStyle()`;
- **tres ternarios** en el diseñador de invitaciones que nombraban las cuatro variables de las dos
  zonas del origen → la paleta se pinta en el escenario y la tarjeta la repite en línea (html2canvas
  la captura resolviendo `--inv`/`--inv2` sobre ella);
- **dos botones escritos a mano** con las palabras «Jump» y «Kids» dentro → **uno por zona con pack**;
- ⚠️ **una copia de la fórmula de contraste en JavaScript.** `applyZoneAccent()` recibía solo el color
  primario, así que tenía que elegir `--zone-2` con un ternario **y repetir la luminancia de
  `ThemeSettings::onBrand()`**. Ahora el slider trae la paleta ya compuesta (`data-zone-style`) y el
  JS solo la aplica: una regla escrita dos veces son dos reglas, y la segunda no se entera cuando la
  primera cambia;
- y del `:root`, `--jump-1`/`--kids-1`/`--on-jump`/`--on-kids`. **Que `cssRootDeclarations()` tuviera
  que conocer los nombres de las zonas de alguien era la señal de que el color viajaba por el sitio
  equivocado.**

▶ **`ZoneAccentIsNotAClassNameTest` pasa a ser ABSOLUTA**: su lista de excepciones queda **vacía**.

**⚠️ DOS trampas de tokenización pagadas, de la misma familia que el `@endif` de `#138`:**

1. **PHP admite los bytes altos en los identificadores**, así que `"…«$acento»…"` se lee como la
   variable `$acento»` y da «Undefined variable». Con llaves, `{$acento}`, resuelto.
2. **La guarda saltaba con sus PROPIOS COMENTARIOS**: el `grep` casaba la nota que explica por qué
   esos tokens se retiraron. Una guarda que se dispara con su documentación obliga a borrar el
   porqué y acaba silenciada — ahora se limpian los comentarios antes de mirar.

**❗ LO QUE ESTE CORTE DESTAPÓ Y NO SE DECIDE AQUÍ: el color de un pack, y por debajo, el
acoplamiento pack↔zona.**

Medido: los dos packs de cumpleaños **comparten la zona `cumpleanos`** (magenta), así que con el
acento tomado de la zona **salen del mismo color** — el heurístico del nombre los distinguía en
naranja y lima. No hay dónde apoyarse: `wristband_color` es texto libre en castellano («Naranja»,
«Verde») para la pulsera física y **no lo consume ningún código**.

▶ **Planteado por el owner** (2026-08-25): el modelo que quiere es *entrada por zona, con el cupo de
la zona* y *pack con cupo PROPIO*, y no solo de cumpleaños —excursión de colegio, comunión…—, con la
landing anunciando **un** pack y el resto en `/servicios`, **alternable**.

⚠️⚠️ **Y la medición cambia el diagnóstico: la mayor parte YA ESTÁ CONSTRUIDA.**

| Lo que el owner pide | Estado medido |
|---|---|
| El pack tiene cupo **propio**, distinto del de las entradas | ✅ ya: `packs.max_per_slot` y `packs.max_guests_per_slot`, con **override por zona** (`zones.max_per_slot`, `zones.max_guests_per_slot`) |
| Poder **alternar** qué pack va a la landing y cuál a `/servicios` | ✅ ya existe… **pero por AUSENCIA**: `birthdaySurfacePacks()` filtra `whereDoesntHave('landingService')`, o sea que crear una ficha de servicio **mueve el pack de sitio**. Nadie lo adivinaría |
| Packs que no sean de cumpleaños | ⚠️ el MODELO aguanta N tipos; lo que está casado con «cumpleaños» es el **vocabulario y la superficie**: el scope, la sección y los textos |
| El acento del pack | ⚠️ va con la zona, y por eso duele: la zona es dónde se celebra, no la identidad comercial del pack |

▶ **Conclusión honesta: no hace falta un cambio de arquitectura, hace falta hacer EXPLÍCITO lo que ya
es implícito** —la conmutación por ausencia de `LandingService`— y **desacoplar el vocabulario**
(«cumpleaños» → «pack destacado en la landing»). Queda como entrada de la **tanda C**, que es la que
toca el catálogo, y **no se decide sobre la marcha**.
⚠️ Consecuencia visible mientras tanto: los dos packs de cumpleaños se pintan del color de su zona
(magenta) en vez de naranja y lima. Es lo coherente con el modelo de hoy —«el color va con la zona»—
y se revierte solo cuando el pack tenga identidad propia, si el owner decide dárseela.

## #140 · 2026-08-25 · TANDA A · El icono de un producto deja de ser un BOOLEANO

**Contexto.** Tercer corte de la tanda A y el hueco que el owner señaló al decidir el tema
(`#136` §4.6): *«los iconos de los productos»*. Con la decisión ya tomada de **set curado, no subida
libre**.

**El defecto, medido.** El icono lo decidía esto:

    @if ($isPack)  <x-icons.ic-b1 />          {{-- tarta --}}
    @else          <x-icons.ticket-tear-off /> {{-- entrada --}}

Un **booleano**, y escrito **tres veces**: en `components/icons/product.blade.php` —que además **no
tenía ni un llamante en todo el repo**— y, con **la geometría entera copiada dentro**, en
`CartStep.vue` y en `SummaryLine.vue`. Cuatro copias de dos dibujos.

▶ **Y la regla era peor que la duplicación**: reparte un catálogo entero en **dos dibujos**. La
tirolina, la tarta y los calcetines son «no-pack», así que los tres salían como un ticket — teniendo
el set un icono de calcetines desde siempre.

**Lo que se hizo:**

- **`ticket_types.icon`** (migración aditiva, nullable): la CLAVE de un icono del set. `null` ⇒ el que
  le toca por su tipo, o sea **ninguna fila cambia de aspecto al migrar**.
- **`Booking\Services\ProductIcon`**: la decisión, en un solo sitio, con la lista ofrecida —**un
  subconjunto deliberado**: solo los iconos de marca del catálogo, para que nadie marque un producto
  con un candado—. Defensivo como `ThemeSettings`: una clave corrupta degrada al icono por tipo en vez
  de servir un `<svg>` vacío.
- **Se PUBLICA** (`OrderItem.icon`, `QuoteLine.icon` en el contrato) para que el cajón deje de
  derivarlo. Es el mismo criterio que `charged_method`, `quantity_label` o `invoiced_hint`.
- **`sidebar/ProductIcon.vue`**: un registro clave → dibujo que **retira las cuatro copias**.
- **El panel** ofrece el desplegable, y `components/icons/product.blade.php` —muerto— se retira.

**⚠️ Una decisión de diseño que la guarda obligó a corregir.** El registro tenía un `v-else` genérico,
así que `ticket-tear-off` **no aparecía escrito en ninguna rama** y la comprobación «el cajón sabe
dibujar todo lo que el panel ofrece» no podía verificarlo: añadir una opción al desplegable habría
servido el genérico sin que nada fallara. Ahora la clave se **normaliza** en el `script` y las seis
ramas son explícitas.

**Guardas: +5, con su guarda-de-la-guarda.** `ProductIconSingleSourceTest` prohíbe el **mecanismo**
—que una superficie elija el dibujo mirando `is_pack`—, comprueba que **toda clave ofrecida existe en
el set** y que **el cajón sabe dibujarlas todas** (que es lo que la paridad de iconos NO cubre: aquélla
compara lo que el cajón dibuja, no lo que el panel deja elegir).

**⚠️ Y dos gates hicieron su trabajo por el camino, los dos correctamente:**

1. **`SidebarDomContractTest` cazó un bundle SSR RANCIO**: había construido el de cliente y no el de
   SSR, así que la comparación de árboles habría corrido contra código viejo. 31 casos en rojo con el
   mensaje exacto de qué hacer.
2. **`SidebarBundleBudgetTest` cazó el techo del chunk.** Medido **218,68 → 220,78 KiB (+2,10)**, y el
   desglose importa: **salen dos geometrías duplicadas** y **entran cuatro dibujos nuevos** que el
   cajón no sabía pintar. Se podó antes de subir el techo, como en `#129`; **219,5 → 221,5**, que deja
   0,72 KiB.

**Verificado sobre HTTP real**: con la tirolina puesta en «cañón de confeti» y los calcetines en su
icono, `GET /me/orders` publica `icon` por línea y los productos sin elegir conservan el suyo.

## #141 · 2026-08-25 · Los DOS CONTADORES de aforo entran al gate de `pre-push` — y la mitad que faltaba

**Cómo apareció, porque importa para el protocolo.** Al preparar el commit de `#140` aparecieron dos
ficheros modificados que **el agente no recordaba haber tocado**: `.githooks/pre-push` y
`CriticalPathGateTest`. Se dejaron FUERA del commit y se preguntó al owner antes de firmarlos.
▶ La explicación: el owner pulsó **compact** sin querer, la sesión continuó trabajando sobre el
contexto compactado, y después hizo **`/rewind`**. **El `/rewind` deshace la conversación, no los
ficheros ya escritos en disco.** Ésos eran los restos.

⚠️ **Regla que conviene dejar escrita**: tras un `/rewind`, el árbol puede llevar trabajo huérfano de
una rama de conversación que ya no existe. No se firma por parecido de estilo — **se verifica y se
completa, o se descarta**.

❗❗ **CORRECCIÓN (mismo día, antes de cerrar): esta entrada decía «se comprobó que no había más restos:
solo esos dos ficheros» y ERA FALSO. Eran TRES.** `docs/DEUDA.md` llevaba también una fila huérfana —la
del verificador de sobreventa, ver abajo— y **se coló en el commit `762f299`** (el del icono), cuyo
mensaje no la menciona. El agente la vio en la misma ventana horaria al buscar restos y **dio por hecho
que era suya de esa mañana** sin comprobarlo.
▶ La lección es la misma que el resto de la semana, aplicada al propio método de verificar: **un
`find` por fecha te da los candidatos, no la autoría**. Lo que distingue es `git log -S` sobre el
contenido, que es lo que acabó respondiéndolo.

**Y lo que la rama perdida estaba haciendo de verdad, reconstruido de su propio rastro**: no era la
tanda A, era una **auditoría de los verificadores de concurrencia**. Su fila de `DEUDA.md` lo dice y
está medida: **`purchase:verify-oversell` solo ejercita ENTRADAS** —siembra un `TYPE_ENTRY` con
`online_capacity = 1` y forka N compras— y **el aforo de PACKS no lo ejecuta ningún verificador**.
Editó el gate porque, auditando eso, encontró que los dos contadores ni siquiera lo disparaban.
▶ **Ese hallazgo sigue ABIERTO y es el mayor de los dos**: no hay ninguna evidencia de que dos
cumpleaños simultáneos por la última plaza no se vendan los dos. La suite lo da por bueno porque
SQLite no reproduce esas carreras. Ficha con receta en `DEUDA.md`.

**El cambio, verificado y correcto.** `SlotAvailability` y `PackAvailability` entran al `CRITICAL_RE`
del hook. Son **los dos contadores de aforo** —quienes deciden cuántas plazas quedan— y dependencias
DIRECTAS del constructor de `OrderCreator`. El docblock de `PackAvailability` describe literalmente el
contrato que los verificadores existen para comprobar —«pensado para correr bajo `lockForUpdate` en
`OrderCreator` (anti-sobreventa): el bloqueo de las franjas de la zona/día serializa las compras
concurrentes»— y **aun así tocarlo no disparaba nada**. Mismo modo de fallo que `SlotOffer` en su día:
el gate vigilaba a quien LLAMA y no a quien CUENTA.

⚠️ **Y el control negativo se afila**: `ProductAvailability`, la tercera dependencia de aforo, se
declara explícitamente NO crítica. Comprobado ejecutándolo: su único constructor es
`OperatingSchedule` y su único método es `allowsStart()` — **no cuenta plazas ni cupo**, solo si una
hora cae en la ventana del día. Nada que una carrera pueda corromper.

**❗ Y le faltaba la mitad, que es lo que este corte añade.** Los ficheros se habían añadido al patrón
del hook y al control negativo, **pero no a `CRITICAL_FILES`**, que es la lista con la que el test
comprueba que el gate sigue cubriendo el núcleo.
▶ **Medido por mutación**: quitar `PackAvailability` del hook dejaba `CriticalPathGateTest` **en
verde**. El gate se había ampliado **sin red**: cualquiera podía encogerlo mañana y nadie se enteraba
— que es exactamente el modo de fallo que este repo lleva documentando toda la semana.
▶ Con la lista completa, las **dos mutaciones muerden** y con el mensaje exacto: «El gate de pre-push
ya NO cubre …: un cambio ahí se empujaría sin correr los verificadores de concurrencia».

## #142 · 2026-08-25 · [DECIDIDO, owner] La visión de producto de Fase 6 — cuatro subsistemas, y las DOS reversiones que traen

**Qué se decidió** (owner, en sesión de arquitectura del 2026-08-24/25): la app móvil que motivó la
API v1 tiene un propósito de negocio —**fidelizar**— y de ahí salen cuatro subsistemas nuevos que
entran en **Fase 6** («features nuevas», que es donde el tracker ya los esperaba). No son una
feature: son cuatro, con perfiles de riesgo muy distintos, y se ordenan **por dependencia**:

| | Subsistema | Spec | Riesgo dominante |
|---|---|---|---|
| **B** | Waiver con valor probatorio | `specs/waiver-probatorio.md` | **Legal** |
| **C** | Menores a cargo | `specs/menores-a-cargo.md` | RGPD (datos de menores) |
| **A** | Carné QR + pantalla de puerta | `specs/identidad-qr-puerta.md` | Fuga de PII de terceros |
| **D** | JumpPoints y vales | `specs/lealtad-jumppoints.md` | Concurrencia (no dinero) |

**El orden es B → C → A → D**, y no es preferencia: B define el modelo de consentimiento del que
cuelga C; A solo tiene sentido cuando hay waiver propio y menores que enseñar; D necesita el carné de
A para canjear. Es la misma regla que ordenó toda la Fase 4.

⚠️ **Ninguno toca la landing**, así que no solapan con `specs/landing-white-label.md` (`#136`): el
único punto de contacto es que el carné del subsistema A viaja en el correo de confirmación.

### Las DOS reversiones, que es lo que hay que leer antes de nada

⚠️ **1 · El waiver deja de ser EXTERNO.** Hoy lo gestiona el sistema de la clienta: el alta lo sacó
del flujo a propósito (comentario dentro de `Identity\Services\SelfSignup`),
`PuertaSettings::waiverCheckEnabled()` existe para apagarlo y `INSTALACION-CLIENTE.md` lo trata como
opcional por instalación. Pasa a poder gestionarse **dentro**. Para no romper el white-label son
**TRES modos** —externo / interno / desactivado—, no dos: el mecanismo de opcionalidad ya existe y se
amplía, no se sustituye.

⚠️ **2 · La puerta deja de ser «privacy-by-design mínima».** El docblock de
`Livewire\Admin\Puerta\ValidarRegistro` dice hoy, literalmente: «devuelve únicamente el estado del
waiver — **sin nombre**, sin email/phone completo, **sin historial**». La pantalla nueva enseña
nombre, reservas, menores a cargo, waivers y vales. Es legítimo —la operativa nueva no es la de hoy—
pero **es una reversión**, y sin esta entrada alguien leería ese docblock dentro de seis meses sin
saber cuál de los dos manda.

### Las decisiones del owner, una a una

**Waiver (B)**
- ⚠️ **Se CONSERVA al borrar la cuenta**, y no como «anonimizado»: eso lo inutilizaría como prueba.
  Es **conservación con tratamiento restringido** (RGPD art. 17.3.e + art. 18): vinculado, con permiso
  propio, auditado, fuera de toda superficie normal, **y con plazo configurable y purga automática**.
  ❗ **El PLAZO está `[PENDIENTE: owner]`**: sale de criterio jurídico, no técnico, y en un menor puede
  empezar a contar cuando cumple 18. Esto **modifica `INVARIANTES` §3 (RGPD-01)**, que hoy borra los
  consentimientos en `User::anonymize()`.
- **Sin geolocalización de navegador.** Se guarda IP, user-agent, fecha/hora con zona, versión y
  **hash del texto**. Medido: `SecurityHeaders` emite `Permissions-Policy: geolocation=()`, así que
  hoy el navegador no la concede ni con permiso del usuario — y probatoriamente no aporta: no
  demuestra que la persona estuviera en el recinto.
- **Snapshot, no referencia**: versiones inmutables de documento legal. Arregla de paso un defecto
  VIVO —`Consent::CURRENT_VERSION` es una constante escrita a mano mientras el texto es una `Page`
  editable desde el panel, así que **hoy nadie puede reconstruir qué texto firmó nadie**.
- **eIDAS: hoy no.** Pero el **hash canónico y el `prev_hash` se dejan puestos desde el primer
  commit**, porque anclar más tarde sobre registros que no puedes demostrar que no tocaste no vale
  nada. Misma doctrina que `#37` con el puerto de pasarela.
- **RETIRADO**: la obligación de abrir un modal para poder registrarse. Un booleano que envía el
  navegador no prueba nada, **nada prueba que lo leyó**, y bloquear un botón hasta abrir un modal
  rompe el flujo de teclado. Lo que sí se conserva: casilla separada, desmarcada, y el servidor emite
  la aceptación **solo si la petición trae el identificador de versión que él sirvió**.

**Menores a cargo (C)**
- **Nombre y fecha de nacimiento, nada más.** La edad se **deriva**, nunca se persiste.
- **Tope 20 por cuenta**, configurable por instalación. No es ilimitado: misma doctrina que `PAY-12`
  (el tope del carrito es invariante de servidor, no de interfaz).
- **«Quitar» es desvincular, no borrar**, si hay waiver o reservas detrás. Mismo patrón que
  `DELETE /me`, que no borra la fila.
- **La asignación de una entrada a un menor va EN EL EMBUDO**, al elegir la cantidad.
- **RETIRADO**: los grupos escolares (alta manual de menores por un admin, estado «pendiente de
  waiver», correo de aviso, aceptación en bloque). Se retira **por criterio del owner**: un profesor
  no ostenta la patria potestad, así que el registro que produciría ese mecanismo puede no valer lo
  que aparenta — y un sistema que hace *sentir* protegido al negocio sin protegerlo es peor que no
  tenerlo. Se lleva cinco piezas del subsistema.

**Carné QR (A)**
- **Uno estable por usuario, opaco y rotable.** Sin número de socio visible.
- **Escanear NO autentica: busca.** La autoridad la pone la sesión del empleado, como hoy.
- **Rotar mata el carné viejo en el acto** (nada de ventana de gracia: una credencial que el titular
  pidió revocar y sigue valiendo no está revocada).
- **La búsqueda por email/teléfono también abre la ficha completa** — decisión del owner, tomada
  sobre la alternativa de reservarla al QR escaneado. Va con contrapesos obligatorios (spec §4.6).
- **La pantalla enseña también el importe ya pagado**, además del pendiente en puerta.
- **Sin historial propio de búsquedas**, CTA de reinicio, **velo por inactividad** y **cierre a los
  5 minutos** con vuelta a la búsqueda.

**JumpPoints (D)**
- **El vale se canjea EN PUERTA, no en el checkout.** Ésa es la decisión que mantiene todo el
  subsistema fuera del núcleo de dinero.
- **Recompensa en especie**, no importe en euros: así el vale no tiene valor monetario, no entra en
  el desglose financiero y no arrastra implicaciones contables.
- **Los puntos se abren DESPUÉS de la visita.** Elimina **por construcción** el agujero de
  comprar → canjear → reembolsar, y **no necesita tarea programada** (se deriva).
- **Caducan por inactividad**, configurable. ❗ Esta pieza —y solo ésta— **depende del cron**.
  ⚠️ Y `#137` (Redis) **no lo desbloquea**: Redis entró solo para CACHÉ, las colas siguen en base de
  datos y el demonio cron de staging sigue sin verse (`#115`).

### Lo que la sesión MIDIÓ, y condiciona lo que venga

- ⚠️ **`Ticket.qr_token` es una credencial MUERTA**: se genera en `TicketIssuer`, es aleatoria e
  impredecible, y **no la lee ni la publica nadie** (0 consumidores; el ciclo de canje se retiró). La
  doc y el contrato la describen como «la credencial que canjea la entrada en la puerta» y no hay
  puerta que canjee. El carné personal es terreno virgen, y **no debe ser el mismo objeto**.
- ⚠️ **`RGPD-06` es la trampa que este subsistema va a repetir si nadie avisa**: la invariante nació
  porque la purga de sesiones estaba copiada en cuatro ficheros y **ninguna revocaba tokens de
  Sanctum, porque Sanctum llegó después**. Un carné QR es exactamente la siguiente credencial que
  llega después → **entra en `User::revokeAllAccess()` en el primer commit**, con su caso.
- ⚠️ **Booking NO puede mirar a Identity.** Medido en el grafo de `ModuleBoundariesTest`: Booking solo
  ve `Platform` y `Payments\Contracts`. Una FK a menores en `order_items` pone el arch-test en rojo.
  La salida ya la inventó este repo (`specs/modulos-dominio.md` §4.bis, hallazgo 4): **Identity posee
  la asignación y referencia el ítem por su id ENTERO**, no por su clase.
- **Tamaño del QR, medido con la librería ya vendorizada**: un token de 13 caracteres en mayúsculas
  con ECC **H** da **versión 2** (25×25 módulos); una URL de 33 caracteres da **versión 4** (33×33).
  Para el mismo tamaño impreso, cada módulo es un ~32% más pequeño. **El payload es el token pelado.**
- **PNG para el correo sin dependencia nueva**: el contenedor trae **GD e Imagick** y
  `chillerlan/php-qrcode` ya vendorizada incluye salida PNG. ⚠️ Pendiente: verificar GD en staging, y
  medir el soporte de SVG en clientes de correo antes de dar por buena ninguna de las dos vías.
- **RFC 3161 sin dependencia nueva**: el contenedor trae **OpenSSL 3.0.13 con el subcomando `ts`** y
  la extensión `openssl` de PHP. ⚠️ Verificar que `shell_exec` no está capado en el hosting.
- ⚠️ **`QrCode::svg()` usa `quietzoneSize = 0` A PROPÓSITO** (el marco de la tarjeta de la landing
  hace de margen). Para un lector de mostrador eso es un riesgo real: **el carné necesita su propio
  perfil de generación**, no reutilizar ese método.
- ⚠️ **El escáner del recinto es un *keyboard wedge***: escribe lo que lee y pulsa Enter. Por eso el
  alfabeto es **mayúsculas y dígitos** (las teclas que no cambian entre distribuciones) y por eso el
  payload **no puede ser una URL**.
- **Presupuesto del cajón**: el techo vivo es `SidebarBundleBudgetTest::SIDEBAR_CHUNK_MAX_KB` y la
  holgura al diseñar esto era **inferior a 1 KiB**. Los cuatro subsistemas suben ese techo, cada uno
  con su medición y su párrafo. ⚠️ La cifra NO se copia aquí: vive en su test.

⚠️ **Las diez afirmaciones de arriba se RE-VERIFICARON contra `main` el 2026-08-25**, tras los 14
commits del otro puesto de trabajo: las diez siguen siendo ciertas.

### Lo aplazado a propósito

**Un sistema de promociones** (descuento porcentual sobre productos, sobre el total y sobre
complementos, con caducidad por tiempo o por número de usos) queda **fuera de estas cuatro specs**, y
la razón importa: **un descuento sobre el precio NO tiene la salida que tuvo la lealtad.** Los vales
salieron del núcleo de dinero canjeándose en puerta; un descuento entra de lleno en `PAY-12`, en
`OrderCreator`, en los dos ejes del ledger y en la pregunta de si un reembolso devuelve el precio con
descuento o sin él. Y «hasta gastarse N veces» es **otra carrera**: el uso N+1 en paralelo. Es un
proyecto de dinero con `VERIFY_CONC=1`, no una feature de marketing.
▶ **La excepción son los REFERIDOS**, que son lealtad pura y caben en el ledger de puntos de **D**.

### ❗ Pendiente del owner antes de implementar

1. **El plazo de conservación del waiver** (criterio jurídico).
2. **El cron de staging** (`#115`): sin él no se puede verificar la caducidad de puntos ni ningún
   envío diferido.
3. **Revisión adversarial de las cuatro specs** por otro agente, que `CONVENCIONES` §5 exige y que en
   este proyecto ha parado bloqueantes reales tres veces (`#122` encontró dos; `#123` declaró el
   diseño INSUFICIENTE).
---

## #143 · 2026-08-25 · TANDA A CERRADA · El color que ya tenía token dejaba de tenerlo al escribirse a mano — y el paquete del cliente no tenía por dónde entrar

Cierra los **dos últimos cortes de la tanda A** de `specs/landing-white-label.md` (`#136`): el
inventario de colores en crudo y el spinner rebrandeable. Y los dos empezaron desmintiendo lo que la
propia spec afirmaba.

### 1 · La pregunta del inventario era la pregunta equivocada

La spec decía **«58 colores en crudo en `site.css` y 18 en `landing.css`»** y planteaba la tarea como
«decidir cuáles suben a token». Medido con un instrumento que ve además los `rgba()` y los valores
**multilínea**: **234 ocurrencias**, 86 formas distintas. Y el reparto no era el que la pregunta
suponía:

| | |
|---|---|
| **144** | **tokens que YA existían, reescritos a mano.** 114 eran `--fg` escrito `rgba(20,19,15,α)` con **28 alfas distintas** |
| 34 | *declaraciones* de token (`--ok: #1f7a3d`…): su único sitio legítimo |
| 8 | `#000` en `mask`: no es color, es un recorte |
| 48 | huérfanos de verdad — 39 son blanco y negro puros |

▶ **Lo que le pasaba a un cliente**: cambiaba `--fg` en su paquete de tema y **113 sombras y bordes
seguían siendo del primer cliente**. Nada fallaba, nada avisaba, y por eso duró desde el commit
fundacional.
▶ **No hacía falta ningún token nuevo.** Hacía falta que los que ya existían dejaran de reescribirse.

**[DECIDIDO owner]** de las tres opciones ofrecidas: **todo lo que ya tiene token** (ni solo los
defectos, ni además tokens nuevos para el blanco). Blanco y negro quedan fuera **a propósito**: el
blanco de una polaroid no es `--bg` (crema) ni `--on-brand` (sigue al acento, y sobre un acento claro
es tinta oscura), así que darles cualquiera de los dos **repinta**. Ficha en `DEUDA.md`.

### 2 · Dos fugas de marca VIVAS que `#139` no podía ver

`.hero__stage-placeholder` —el fondo tras el vídeo del hero— llevaba **los acentos de las DOS zonas
del primer cliente**, escritos **en decimal** dentro de un `background` de tres líneas:
`rgba(255,91,34,.22)` (el naranja de Jump) y `rgba(198,255,58,.16)` (la lima de Kids).

⚠️ **`#139` retiró `--jump-*`/`--kids-*` y `ZoneAccentIsNotAClassNameTest` vigila que no vuelvan POR
SU NOMBRE. El valor sobrevivió a las dos cosas**: al barrido y al inventario, porque aquel `grep` de
`#hex` iba línea a línea y esto es un `rgba()` en la segunda línea de un valor.
▶ Y había una tercera, más callada: `.map-pin` pintaba el fondo con `var(--zone-1)` y el halo con
`rgba(255,91,34,.18)`. Cambias la marca y **el pin sale bicolor** — el mismo defecto que `#138`.

**La lima NO tenía token equivalente**, así que pasa a `--zone-2` y ése es el único sitio donde el
píxel se mueve a propósito: el segundo radial del hero deja de ser lima y pasa al amarillo de marca.
Quien la quiera, la pone en `--zone-2`. Es el white-label funcionando.

### 3 · El hueco que no existía

Todo lo anterior daba por supuesto que un cliente puede traer su hoja. **Medido: no había hueco.** El
layout cargaba `landing.css` → `<style id="jj-theme">` → `spinner.css` → `site.css` y ahí se acababa.
**Tokenizar sin ese hueco es trabajo que ningún cliente puede usar.**

**[DECIDIDO owner]**: se abre en esta tanda. `public/css/client.css`, cargado **el último** si existe.
⚠️ **Y el mecanismo son TRES piezas, no una** —las tres pueden faltar por separado y con dos parece
que funciona—: el `<link>` va el último (por delante de `site.css` carga y **no pinta nada**) · la
hoja **no se versiona** (este repo es el producto) · `deploy.sh` la **excluye del `rsync --delete`**
(sin eso, el primer despliegue la borra del servidor **en silencio**).

❗ **La aserción de la tercera nació ROTA y la cazó su propia mutación.** Comprobaba que el texto
`--exclude='/public/css/client.css'` apareciera en el script; con la línea **comentada** seguía en
verde. Un `--exclude` comentado no excluye nada. Es el modo de fallo que este repo lleva escrito
—«un grep mal escrito queda verde para siempre sin mirar nada»— y **volvió a pasar, en la guarda que
lo estaba previniendo**. La misma trampa tenía la del `.gitignore`. Las dos van ancladas a principio
de línea, y hay un caso que comprueba que **rechazan una línea comentada**.

### 4 · El spinner: el dibujo no era un fichero

La spec: «cambiar el dibujo es sustituir un fichero. No hay que construir nada: hay que usarlo».
**Falso en las dos mitades.** El dibujo son **dos pseudo-elementos y un `@keyframes`** dentro de
`spinner.css` — y `UI-SPINNER.md` §3 decía además que esa hoja **«no se modifica»**, o sea que el
producto llevaba escrito que el encargo no se podía hacer.

**[DECIDIDO owner]** de tres opciones: **punto de sustitución declarado** (ni máscara SVG —perdería
el salto de dos piezas— ni set curado de variantes —un despliegue por variante y ningún cliente
pidiéndola—). La hoja se parte por un marcador de máquina en **§A CONTRATO** y **§B DIBUJO**; una
instalación redefine §B desde `client.css` y nada más.

⚠️⚠️ **Y al separarlos apareció un defecto de accesibilidad real.** `prefers-reduced-motion` apagaba
`.jj-spinner::before`, que es **exactamente la única pieza que anima el dibujo del PRIMER cliente**.
En cuanto una instalación traiga un dibujo que anime `::after` o el propio elemento, quien pidió
reducir movimiento **lo sigue viendo girar**: no falla, no avisa, y no se ve desde el producto.
▶ **Un contrato de accesibilidad que solo cubre el dibujo de quien lo escribió no es un contrato.**
Hoy cubre los tres selectores con `!important` —`client.css` carga después y no debe poder
reactivarlo por descuido— y el `transform` de reposo, que sí es del dibujo, baja a §B.

### 5 · Lo que se midió, y con qué

- **Aritmética**: las 144 sustituciones verificadas componente a componente. `color-mix(in srgb, C
  p%, transparent)` **premultiplica**, así que rinde exactamente `rgba(C, p)`. **144 de 144 exactas.**
- **Estructura**: `postcss` antes y después. `site.css` **1144 reglas / 4370 declaraciones** las dos
  veces; `landing.css` **665 / 2644** las dos veces. Ni una declaración perdida — que es como se
  detecta un `color-mix` mal escrito, porque el navegador lo descarta **sin avisar**.
- **NAVEGADOR** (`VERIFICACION-E2E-CAJON.md` §5.bis, Playwright en el contenedor), capturas a página
  completa antes/después de `/`, `/servicios`, `/entradas` y `/aviso-legal`:
  **fuera del hero, `0` píxeles distintos.** `/servicios` y `/aviso-legal`, 0 en la página entera.
  ⚠️ **Y con su control de RUIDO, que es lo que hace la medida interpretable**: el hero lleva vídeo, y
  **dos capturas del MISMO código** difieren ahí en 520.551 px — más que las 92.000 de mi cambio. Sin
  ese control, el diff del hero se habría leído como una regresión.
  ▶ El único cambio real se aisló bloqueando el vídeo: `rgb(30,31,19) → rgb(32,30,20)`, el radial
  pasando de lima a amarillo. Del signo esperado y de ±2/255.
- **Mutación**: 4 sobre `RawColourIsNotATokenTest`, 3 sobre `ClientThemePackageTest` y 6 sobre el
  contrato del spinner. **Todas caen, y cada una solo en su aserción.**

### 6 · Las guardas, y lo que el trinquete ya existente hizo solo

- **`RawColourIsNotATokenTest`** (nueva) — prohíbe el MECANISMO: ningún literal puede repetir un
  color que ya tiene token. ⚠️ **Compara por VALOR RGB y no por nombre**, que es el hueco por el que
  se coló el hero. Lee los valores del `:root` en tiempo de test para no describir una paleta que el
  CSS ya no tenga. Con guarda-de-la-guarda por partida doble: que el escaneo ve el corpus y que el
  detector caza sus propios ejemplos —**incluido el `rgba()` multilínea dentro de un
  `radial-gradient()`**, que es el que el primer instrumento no veía—.
- **`ClientThemePackageTest`** (nueva) — las tres piezas del hueco, más el caso que comprueba que sus
  dos aserciones de fichero rechazan una línea comentada.
- **`SpinnerTest`** — cinco casos nuevos: el dibujo vive entero bajo la línea, el contrato conserva
  sus piezas, reducir movimiento cubre CUALQUIER dibujo, el producto no se da especificidad de más
  (si la tuviera, el paquete del cliente **cargaría y no pintaría**) y el dibujo pinta con el token.
- ✅ **`SidebarTokenBudgetTest::MAX_RAW_COLOURS` baja 6 → 5 SOLO.** Nadie eligió tocar
  `.cal__day--normal`: entró con las otras 143 por coincidir con `--fg` al 5 %, y **fue el trinquete
  quien avisó**. Es exactamente para lo que `#99` lo hizo estricto.
- 🐛 **Y ese mismo fichero llevaba un memo que no memoizaba**: `return $memo = $declarations;`
  asignaba a una variable **local** que se descartaba, así que `$this->declarations` seguía `null` y
  los dos CSS se releían en cada caso. No falseaba nada —de ahí que durara— pero encima de él había
  un párrafo explicando con detalle por qué el memo era de instancia y no `static`.

### 7 · Cuatro afirmaciones falsas retiradas del código

Ninguna rompía nada; todas describían un producto que ya no existe, y un agente siguiente las habría
dado por buenas:
1. `site.css:3` — «El CSS del mockup (`landing.css`) se mantiene intacto». **Cuatro commits lo han
   tocado**, `#138` y `#139` entre ellos (la paleta del primer cliente vivía dentro).
2. `layout.blade.php` — citaba `--jump-1`/`--kids-1` como «color de cada zona». Los retiró `#139`.
3. `UI-SPINNER.md` §3 — «es copia fiel del mockup: no se modifica».
4. La spec §4.5.2 — «no hay que construir nada: hay que usarlo».

### 8 · La lección de método, que se pagó DOS veces en la misma sesión

**Un instrumento que no ve una parte del corpus da un inventario que parece completo y no lo es.**
▶ El «76» de la spec salió de un `grep` de `#hex` línea a línea: no contaba `rgba()` —que era la
mayoría— ni valores multilínea.
▶ Y **el primer instrumento que se escribió aquí tenía el mismo defecto**: también iba línea a línea
y no vio 12 ocurrencias, entre ellas **las dos fugas de marca del hero**. Solo se supo al cruzarlo
con un `grep` que sí encontraba lo que él no. Reescrito por offsets absolutos: 234 en vez de 222, y
**cero falsos positivos** respecto al primero.
▶ Corolario para quien venga: **cuando dos medidas del mismo corpus no coinciden, la que sobra no es
la que da más — es la que no puede explicar la diferencia.**

---

## #144 · 2026-08-25 · TANDA B · La home anunciaba «0 m²» — y el defecto vivía DOS veces porque el marcado estaba escrito dos veces

Apareció **midiendo, no buscando**: al contar las consultas de la home para dimensionar la tanda B,
el runner escupió un `number_format(): Passing null to parameter #1` que no venía de ningún trabajo
reciente.

### Lo que se estaba sirviendo, medido en el HTML de la portada

`zones.area_sqm` y `zones.rides_count` son **NULLABLE** y opcionales en el panel, y las dos
superficies que las pintan hacían `number_format($zone->area_sqm, 0, ',', '.')` a pelo.
`number_format(null)` **no falla: devuelve «0»**.

▶ Medido sobre la BD de desarrollo: de las **cuatro** zonas con `show_in_landing = true`, **dos
(`cap`, `cap2`) tenían las dos columnas a `null`**, y la portada anunciaba literalmente:

    <span class="v">0 m²</span><span class="l">Superficie</span>
    <span class="v"></span><span class="l">Atracciones</span>

**Un cero no es un hueco: afirma algo, y es falso.** Un cliente que aún no ha rellenado los metros de
una zona estaba publicando que mide cero — que es exactamente lo contrario de lo que quiere un
producto white-label recién instalado, donde lo normal es que falten datos.

⚠️ **Y no era solo cosmético**: `number_format(null)` es `DEPRECATED` en PHP 8 y **TypeError en
PHP 9**. Hoy un aviso en el log; mañana, un 500 en la portada.

### [DECIDIDO owner] Sin dato, no se pinta la métrica

De tres opciones —ocultar, guion, ficha en deuda— se eligió **ocultar**, y es el patrón que el
producto ya usaba: `heroStatus` vale `null` si no hay horario configurado y entonces la vista no
pinta el chip. Aquí igual, **contenedor incluido**: media fila es peor que ninguna.

### ⚠️⚠️ Vivía DOS veces, y ésa es la parte que importa

El bucle de zonas tiene dos ramas —`.zone-photo-card__meta` si la zona tiene foto,
`.zone-intro__meta` si no— y **el mismo marcado estaba escrito en las dos**. El primer arreglo tocó
solo una: al medirlo en el navegador salieron las zonas con dato pintando bien y **las de la otra
rama sin pintar nada**, que es el resultado más probable de arreglar un defecto duplicado.

▶ Por eso nace `<x-site.zone-metrics>`, **compartido desde el primer commit**. Es la misma lección
de `ThemeSettings::zoneStyle()` (`#138`) y de `Booking\Services\OrderLedger`: **una composición, N
superficies que la pintan**. Un defecto que vive en dos sitios es un defecto que volverá.

### ❗ Y una trampa de Blade que costó un 500 y conviene dejar escrita

El primer intento usó `@php(…)` de una línea con un array multilínea: Blade **no cierra bien los
paréntesis** y la vista muere con «syntax error, unexpected end of file» — o sea, 500 en la portada.
Al pasarlo a bloque, el 500 cambió de forma: `Undefined variable $zoneMetrics`.

⚠️⚠️ **La causa era el COMENTARIO.** Dentro del bloque había un comentario PHP que decía, en prosa,
«usa un bloque `@php … @endphp`, no `@php(…)`». **Blade sustituye esas directivas también dentro de
un comentario**, así que compiló el `@endphp` de la prosa a `?>` y **cerró el bloque en la primera
línea**: la asignación quedó fuera y se emitió como texto.

    // ⚠️ Bloque `@php … ?>`, NO `<?php(…)`:      ← el bloque muere AQUÍ
    $zoneMetrics = collect([…]);                   ← esto ya es HTML

▶ Es **exactamente** el modo de fallo que este repo ya tenía documentado para CSS en
`SidebarTokenBudgetTest::test_no_stylesheet_rule_is_swallowed_by_a_broken_comment` —«un comentario
mal cerrado no rompe el CSS: se come la regla siguiente, en silencio»—, aquí con Blade y con un 500
en vez de en silencio. **No escribas `@php`/`@endphp` literales dentro de un bloque Blade**, ni
siquiera comentados.

### Verificación

- **Navegador**, contra la portada servida: antes `<span class="v">0 m²</span>` ×2; después **0**, y
  las dos zonas CON dato (`5.000 m²`/`15`, `2.000 m²`/`8`) **intactas byte a byte**.
- **El `DEPRECATED` desaparece del log** (era el síntoma que lo destapó).
- **Tres casos en `HomePageTest`, y el segundo es el que impide el arreglo perezoso**: sin
  `test_a_zone_with_measurements_still_shows_them`, ocultar el bloque SIEMPRE también habría pasado.
- **Mutación**: devolver el `number_format` sin guarda → caen 2 · ocultar siempre → caen 2 · duplicar
  otra vez el marcado en la rama sin foto → cae 1. **Cada una solo en su aserción.**

## #145 · 2026-08-25 · El registro de un pedido no decía NADA — y el dato estaba entero desde el principio

**De dónde sale.** El owner abrió el registro del pedido `R-S9XDYB` en staging, después de mover una
reserva a un día más barato, y encontró esto **completo**:

    Producto
    orders.gate_credit_applied
    Motivo: item_edit_reduction

Sin importe, sin fechas, sin precios. Y con el badge equivocado. *«Con eso yo no entiendo ni hago
nada.»*

⚠️⚠️ **El defecto NUNCA fue de datos.** La entrada hermana `orders.item_edited` guardaba, desde el
primer día, `from_unit_price 1890 → to_unit_price 1590`, `price_diff_cents -2400` y
`changes: ["slot_change"]`; y `orders.item_slot_changed`, las fechas de origen y destino. **El
registro tenía todo y no pintaba nada.**

### Los SEIS defectos, todos medidos sobre `R-S9XDYB`

· **1 · El contexto del ajuste tiraba `slot_change` a la basura.** `executeItemEdit` filtraba a
  `array_flip(['product_change', 'quantity_change'])`, así que el ajuste se guardaba con
  `context = {"changes": []}` mientras su hermano del registro llevaba `["slot_change"]`. El mismo
  hecho, dos registros, y **el pobre era el que colgaba del dinero**.
  ⚠️ **Causa raíz con fechas**: ese filtro es del **commit fundacional (2026-08-12)**, cuando mover
  la fecha **no re-tarificaba** — un cambio de franja no podía generar diferencia de precio y no
  había nada que anotar. **`PAY-18` (`#131`, 2026-08-24) creó esa causa nueva y nadie extendió el
  filtro.** Es también lo que `#131` no llegó a ver: allí se midió que «los ocho `extra_due` caían
  al texto de respaldo» y se mejoró **ese texto**; esto quita la necesidad de recurrir a él.

· **2 · Casi ninguna acción tenía etiqueta.** Medido sobre las acciones **realmente emitidas en
  staging**: de las **9 de pedido, 8 salían como clave cruda**. Solo `orders.slip_printed` tenía.

· **3 · El renderizador conocía seis formas y ninguna era la de estas acciones.** `item_edited`
  traía precios y diferencia → **nada**. `item_slot_changed` traía las fechas → **nada**.
  `gate_credit_applied` traía motivo **e importe** → pintaba el motivo en crudo y **ocultaba el
  importe**.

· **4 · De las 18 etiquetas escritas, 8 no se usaban.** Y no todas por el mismo motivo: **4 estaban
  MAL ARCHIVADAS** —bajo `order_items.*` mientras el código emite `orders.item_*`—, o sea escritas,
  correctas y **jamás mostradas**; y 4 etiquetaban cosas que nadie emite.

· **5 · ⚠️ El badge decía «Producto» SIEMPRE.** `$isOrderLevel = $entry->target_type === Order::class`
  no puede ser cierto: la columna guarda el **alias del morphMap** (`order`). `enforceMorphMap` entró
  el **2026-08-12**, el mismo día que se escribió el blade, así que **llevaba casi dos semanas
  mintiendo** — y arrastraba que la otra rama buscara un `OrderItem` con el **id del pedido**.

· **6 · El cliente veía MÁS que el operador.** El cajón decía «Al reservar se facturaron 202,20 €. El
  pedido cambió después y ahora vale 37,00 € menos» y el panel **no tenía nada equivalente** — siendo
  el operador quien lo explica con el cliente delante.

### Qué se hizo

**A + B + C + D, las cuatro** (decisión del owner), más la guarda.

- **Catálogo `AuditLog::ACTIONS`** (87 acciones) y **validación en `AuditLogger`**: lanza fuera de
  producción, acepta en silencio dentro. ⚠️ **La validación va ANTES del `try` de `write()`**, que se
  traga cualquier `Throwable` a propósito: metida dentro habría quedado muda para siempre.
- **Etiquetas**: las que faltaban, las 4 misarchivadas movidas a su grupo real, las 4 muertas fuera.
  Y los **motivos** dejan de salir en crudo.
- **Renderizador**: precio unitario antes/después, la diferencia con signo y color, qué cambió en
  palabras, el importe del ajuste y las fechas del cambio de franja.
- **`slot_change` viaja al contexto** del ajuste, y `breakdownLabel()` lo dice. Va **el último** de
  las tres ramas a propósito: producto y cantidad explican el importe mejor, y no se desplaza
  ninguna etiqueta que hoy funcione.
- **La frase de resumen, en el panel.** ⚠️ **La compone `OrderLedger`, no el blade**: es la MISMA que
  publica `LedgerResource::invoiced_hint`, y **la condición es que la frase exista**, no comparar
  importes — la lección de `#134`/`L6`.

### ⚠️ Lo que la guarda cazó, incluida una equivocación mía

**Un escaneo estático NO puede enumerar las acciones**: tres se construyen concatenando
(`'orders.item_'.$actionKey.'_blocked'`) y dos llegan por constante dentro de un array. La primera
extracción de la sesión se dejó `orders.refund_blocked`, que viaja por un helper.

▶ Al activar la validación, **la suite cazó en el acto tres acciones que ningún grep había visto**:
`order_items.prepared`, `orders.expired` y `test.action` —las tres escritas por **tests** como
fixture—, y en la segunda vuelta una cuarta, `order_items.unprepared`. **Yo había declarado
`order_items.prepared` «etiqueta muerta»**: en producción lo es, pero había código escribiéndola, y
eso solo se ve ejecutando. Las cuatro fixtures pasan a usar acciones reales, que además hace esos
tests más fieles.

### Por qué esto convivió tanto tiempo sin que nada lo dijera

⚠️ **`OrderAuditModalTest` tiene once casos y NINGUNO renderiza el modal**: todos ejercitan el
paginador. Es cobertura buena de la CONSULTA y ciega a la PRESENTACIÓN — la misma familia de hueco
que `#113` (veinte iconos servidos vacíos) y `#119(f)` (los botones de mes sin cablear). Nace
`OrderAuditReadabilityTest`, que **renderiza el partial de verdad**.

### ❗ Un SÉPTIMO defecto encontrado y NO arreglado, con nombre

Al escribir el test del cableado salió esto: en un pedido **pagado íntegro online**, mover la fecha a
un día más barato **no crea ningún ajuste** —la bajada aflora como «pendiente de devolución»— y el
marcador de reducción que existe justo para dejar rastro solo se dispara
`if isset($itemEditContext['quantity_change'])`. **Otra condición que `PAY-18` dejó atrás.**
Consecuencia: en ese caso el desglose no tiene ninguna fila que explique la bajada; el registro sí.
▶ **Queda `[PENDIENTE: owner]`**: extenderlo añade una fila de 0 € donde hoy no hay ninguna, y eso
cambia lo que ve el operador en todos los pedidos pagados íntegros cuya fecha se mueva. Es una
decisión de producto, no una corrección obvia.

### Lo que esta guarda declara que NO mira

⚠️⚠️ **Solo el español — y la primera versión de esta entrada daba un motivo FALSO.** Decía que
`lang/es/admin.php` era el único `admin.php` del repo. Existe también **`lang/zh_CN/admin.php`**
(112 KB, versionado) y el chino **es un idioma soportado del panel** (`SetAdminLocale::SUPPORTED`).
La medición había mirado solo `es`, `en` y `fr`. **Es la segunda vez en esta misma sesión que un
`grep` de alcance corto produce una conclusión de más** — la primera fue el catálogo de acciones,
que la validación en ejecución desmintió.
▶ **El motivo real, y la decisión del owner (2026-08-25)**: el chino es herencia del origen y **no lo
usa nadie**, así que se arregla solo el español y el chino queda como **hueco con nombre**. Medido:
de las 25 acciones, `zh_CN` etiqueta 18 → **15 saldrían en crudo** y **8 son huérfanas** (las mismas
que tenía el español). Ficha en `DEUDA.md`.
⚠️ **Solo el registro DEL PEDIDO**: el visor global de incidencias usa otro espacio de nombres y solo
etiqueta las 8 acciones críticas, que son las que filtra por defecto. El resto sale en crudo allí, y
es un hueco con nombre.

**Lo medido**: suite **2764 → 2778** (+14) y **16.066 → 16.098** aserciones · **7 mutaciones, las 7
muerden** (verificadas por CONTENIDO, no por código de salida: dos dieron «no se aplicó» y «muerde» a
la vez, que es la trampa de `#92`) · y el partial renderizado con **los payloads exactos de
`R-S9XDYB`** para comprobar qué habría visto el owner.

## #146 · 2026-08-25 · La AUDITORÍA del cambio de precio: `PAY-18` creó una causa nueva y CINCO sitios siguen contando la vieja

**De dónde sale.** El owner preguntó lo que parecía una duda de producto —*«si el cliente pagó el
total y cambio a una fecha más barata, ¿qué pasa con la diferencia? ¿se reembolsa? ¿cómo se la
devuelvo sin cancelar el pedido?»*— y midiéndolo salieron **cuatro defectos más** del mismo tronco
que `#145`. Ninguno está arreglado: esta entrada existe para que no se pierdan.

⚠️⚠️ **El tronco, dicho una vez**: `PAY-18` (`#131`, 2026-08-24) hizo que **mover la fecha
re-tarifique**. Antes de eso, **la única forma de que el valor de una línea bajara era bajar la
cantidad**. Cinco sitios se escribieron sobre esa premisa y ninguno se revisó al cambiarla.

### Lo que SÍ funciona (medido, no supuesto)

**Subida** (2 × 20,00 → 2 × 24,00): impecable. `pendientePuerta = 8,00` → «Falta pagar en el
parque», el cliente lee «…ahora vale 8,00 € más», y desde `#145` la línea del desglose dice
**«Cambio de fecha a 06/06/2026 10:00»**. Nada que tocar.

**Bajada, el ledger**: los dos ejes cierran y los importes son correctos. `facturado 40,00 ·
valor 36,00 · cobradoOnline 40,00 · pendienteDevolucion 4,00`, y la nota al cliente dice «…ahora
vale 4,00 € menos». **El dinero está bien contado.** Lo que falla es lo que se puede HACER con él.

### Los cuatro defectos abiertos

· **D2 · Dos textos cuentan la causa vieja.** El aviso al operador tras la edición dice
  «✓ Producto actualizado: **unidades canceladas**» —no se canceló ninguna unidad— y el pie de
  «Pendiente de devolución» dice que la causa fue «**una reducción de cantidad o una cancelación**»
  —fue un cambio de fecha—. Los lee **cada vez** que se mueve una fecha a la baja.

· **D3 · El marcador de reducción no se dispara.** `recordReductionMarker` solo se llama
  `if isset($itemEditContext['quantity_change'])`. Medido en la bajada por fecha: **«(ninguna fila
  de ajuste)»** — el desglose no tiene ni una línea que explique de dónde salen los 4,00 €.
  ⚠️ **Y NO es cosmético, como se creyó al abrirlo**: su propio comentario dice que el marcador
  existe «para que `itemOriginalOnlineCents` reconstruya la cantidad original y el sobre-cobro
  aflore». Es el mecanismo del que depende D4.

· **D4 · ⚠️ El tope de reembolso por línea es INCORRECTO tras un cambio de precio.**
  `itemOriginalOnlineCents` reconstruye lo cobrado por la línea **solo si encuentra un
  `quantity_change`**; si no, cae a lo cobrado ACTUAL — que la re-tarificación ya sobrescribió. Su
  docblock avisa de que es «aproximado solo si hubo cambio de PRODUCTO»: **no menciona la fecha,
  porque cuando se escribió la fecha no cambiaba el precio.**
  ▶ **Medido**: pagó 40,00 → movido a día barato (24,00) → **cancelada la reserva entera**. El panel
  dice que se le deben **40,00** y el tope por línea se queda en **24,00**: hay 16,00 € del propio
  dinero del cliente que **no salen por esa vía**. El tope del PEDIDO sí es correcto (40,00), así
  que hay salida — pero nada le dice al operador que la vía por línea se queda corta.

· **D5 · ⚠️⚠️ «Reembolsar» no deja elegir el importe, y eso puede regalar dinero.** El lote hace
  `$amountCents = $this->fresh()->…->itemRefundableRemainderCents($item)`: **devuelve siempre el
  remanente entero de la línea**. No hay campo de importe.
  ▶ **Medido ejecutándolo**: se le debían **4,00 €**; el operador pulsa «Reembolsar» en esa línea y
  se le devuelven **36,00 €**. Después, la reserva **sigue viva y vale 36,00** y el cliente ha
  pagado 4,00. El parque ha regalado **32,00 €**, que el ledger registra —honestamente— como
  `compensado: 32,00`. Los dos ejes siguen cerrando: **el ledger dice la verdad sobre un error.**
  ▶ **No es una trampa oculta**: el modal avisa («se devolverá su importe completo») y el radio de
  intención dice «conserva su reserva sin tener que pagar nada más». El sistema **declara lo que va
  a hacer**; simplemente **no ofrece hacer lo que hace falta**.
  ▶ **Y el dominio SÍ sabe**: `Order::executePartialRefund(OrderItem, int $amountCents, …)` acepta
  cualquier importe. **Falta un campo en el panel, no un mecanismo.**

### La respuesta a la pregunta del owner, en una línea

**Hoy no se pueden devolver esos 4,00 € desde el panel sin cancelar.** El único botón por línea
devuelve 36,00.

### El cumpleaños (pack con señal): distinto, y normalmente ni hace falta

La bajada acredita **en cascada**: primero el `extra_due` de ediciones, luego el resto de la señal,
y **solo lo que cae por debajo de lo ya cobrado online** aflora como «pendiente de devolución». Es
decir: en un pack con señal, una bajada pequeña **se absorbe contra lo que quedaba por pagar en el
parque** y el cliente simplemente paga menos al llegar — sin reembolso ninguno.
⚠️ **Leído en el código, NO ejecutado.** Antes de darlo por bueno hay que medirlo con un pack real,
como se hizo con los otros dos casos.

### El orden que propongo

**D4 y D3 se arreglan con el mismo cambio**: que `itemOriginalOnlineCents` sepa reconstruir también
por **precio unitario original**, no solo por cantidad. El dato ya existe —el registro guarda
`from_unit_price`/`to_unit_price` desde `#145`, y el ajuste lleva el `slot_change` en su contexto—.
Con eso, **D2** se arregla de paso porque ya se sabe la causa correcta.
**D5 va aparte y primero**: es el único que puede costar dinero de verdad, y es un campo de
formulario más el paso del importe elegido a `executePartialRefund`, que ya lo acepta.

⚠️ Toca `Order.php`, que es dinero. **NO entra en el `CRITICAL_RE`** (comprobado), así que no exige
`VERIFY_CONC`, pero sí exige escenarios por los cuatro caminos —bajar cantidad, bajar precio, las
dos a la vez, y cancelar después de una bajada— con su mutación cada uno.

---

## #147 · 2026-08-25 · El aforo de PACKS nunca se había probado bajo concurrencia — ahora sí, y el instrumento sabe cazar

**El hueco, dicho como estaba.** `purchase:verify-oversell` llevaba desde el origen siendo la prueba
de que dos compras simultáneas de la última plaza no sobrevenden. Medido el 2026-08-25: sembraba
**una entrada** con `online_capacity = 1` y forkaba N compras. Eso cubre **uno** de los **tres**
aforos del producto.

⚠️⚠️ Los cumpleaños se cuentan por **otro camino entero** —`PackAvailability`, con **pool propio** y
**dos** topes por franja (`zones.max_per_slot` fiestas y `zones.max_guests_per_slot` invitados)— y
**ningún verificador lo ejecutaba**.
▶ Traducido: **no había ninguna evidencia de que dos cumpleaños simultáneos por la última plaza no se
vendieran los dos.** Y el silencio no era prueba: la suite corre en SQLite, que no reproduce las
carreras de InnoDB (`SUITE-04`).

### Lo que se construyó

**`--scenario=entry | pack | pack-guests`**, y son **tres y no uno mixto** porque son **tres
invariantes distintos**: un cupo de fiestas correcto no dice nada del de invitados, y en un escenario
mezclado el que se rompiera se escondería detrás del que aguantara.

⚠️ **El escenario de packs se aísla en una ZONA propia**, no en los ajustes globales: los dos topes y
`prep_blocks_cupo` se pueden fijar por zona (`zones.*`, con fallback a `packs.*`). Así el verificador
**no toca la configuración de la BD de desarrollo**, que tiene pedidos reales.

⚠️ **`prep_blocks_cupo` se apaga y la fiesta dura una sola franja, a propósito.** Este comando existe
para medir **la carrera**, no la aritmética del tramo —que sí cubre la suite—. Menos superficie donde
un fallo de siembra pueda disfrazarse de «no hubo sobreventa».

### ❗ La guarda del instrumento, que es la mitad del trabajo

**Un verificador que no consigue vender ni una vez sale verde por construcción.** Si el escenario está
mal sembrado —el pack no cabe en la rejilla, falta el precio de la tarifa del día, la hora cae fuera
del horario— los N compradores son rechazados por un motivo que **no es la carrera**, y el resultado
se lee como «nadie sobrevendió».

▶ **No es hipotético**: la primera ejecución de este comando en JumpWeb falló exactamente así, por un
precio ausente. Allí lo delató el conteo; con packs hay tramo, preparación y dos topes, así que ahora
se comprueba **explícitamente y ANTES de forkar** que el dominio ofrece hueco para lo que se va a
pedir. Y `OversellVerifierCoversEveryQuotaTest` asevera que esa llamada sigue estando **antes** del
fork: después ya no distingue un escenario mal sembrado de una carrera bien serializada.

### Lo MEDIDO, que es lo que esta entrada viene a dejar escrito

Sobre **MySQL real** (no SQLite), con `pcntl_fork`:

| Escenario | 8 workers | 16 workers |
|---|---|---|
| `entry` | 1 compra · 7 `sold_out` · asientos = 1 | 1 · 15 · asientos = 1 |
| `pack` | 1 compra · 7 `sold_out` · **1 fiesta viva** | 1 · 15 · 1 fiesta |
| `pack-guests` | 1 compra · 7 `sold_out` · **6 invitados** | 1 · 15 · 6 invitados |

▶ **El aforo de packs aguanta.** `lockSlots` bloquea **todas** las franjas de la zona×fecha —no solo
las de la cesta—, así que serializa igual de bien un cumpleaños multi-franja que una entrada.

### ❗❗ Y el CONTROL NEGATIVO, sin el cual el verde de arriba no significaría nada

Se retiró el `lockForUpdate()` de `OrderCreator::lockSlots` y se repitieron los dos escenarios de
pack. El instrumento **cazó las dos sobreventas**, y con margen:

| Escenario | Esperado | Con el lock retirado |
|---|---|---|
| `pack` | 1 fiesta | **8 fiestas vendidas donde cabía 1** |
| `pack-guests` | 6 invitados | **48 invitados donde caben 10** |

▶ `OrderCreator.php` se restauró y se verificó **por md5 y por `git status`**: sin cambios respecto a
git y con su único `lockForUpdate()` en su sitio.
▶ **Ésta es la parte que convierte el verde en evidencia.** Un verificador que nunca se ha visto
fallar no ha demostrado que pueda.

### Lo que esto cambia en la doc

- `DEUDA.md`: la ficha «el aforo de PACKS nunca se ha probado bajo concurrencia» **se retira**, con su
  medición.
- `INVARIANTES.md` `AFORO-01` y `SUITE-04`: el comando ya no es «uno», son **tres escenarios**, y
  tocar el aforo de packs obliga a correr los suyos.
- ⚠️ **Lo que NO cambia**: `Order.php` y `ViewOrder.php` siguen fuera del `CRITICAL_RE` (comprobado
  con control negativo: `OrderCreator.php` sí lo dispara).

### Lo que sigue SIN medir, dicho para que no se dé por hecho

- **El tramo multi-franja bajo concurrencia**: el escenario usa una sola franja a propósito. Que
  `lockSlots` bloquee zona×fecha lo hace muy improbable, pero **improbable no es medido**.
- **La mezcla**: una cesta con entrada + pack a la vez, compitiendo por dos pools distintos.
- **`prep_blocks_cupo` activo**, que es el valor por defecto en producción.

---

## #148 · 2026-08-25 · Los tres huecos que `#147` dejó escritos, cerrados — y uno destapó que los DOS aforos no son independientes

`#147` cerró el aforo de packs bajo concurrencia y dejó **tres cosas escritas como no medidas**, a
propósito, para que nadie las diera por hechas. Ésta las cierra. **Y una de ellas encontró algo.**

### Los dos escenarios nuevos

- **`pack-prep`** — la fiesta abarca **varias franjas** (120 min de duración + 60 de montaje + 60 de
  limpieza) y los compradores piden **horas DISTINTAS que se pisan**: una fiesta de las 11:00 ocupa
  de 10:00 a 14:00 y otra de las 12:00 ocuparía de 11:00 a 15:00.
  ⚠️⚠️ **Y `prep_blocks_cupo` va ACTIVO, que es el valor por defecto en producción.** Hasta ahora se
  medía siempre con la preparación apagada — o sea, con una configuración que ningún parque usa.
  ▶ **Exigió que cada worker lleve su PROPIA cesta**: con una cesta común no hay forma de expresar
  «11:00 contra 12:00», y ése era el caso que ningún escenario podía representar.
  ▶ Y su contador cuenta las fiestas **de todo el día en la zona**, no las de la franja sembrada: al
  pisarse sin compartir hora de inicio, contar una sola franja habría dado verde con dos vendidas.

- **`mixed`** — una entrada y un cumpleaños compitiendo **a la vez**, en la misma zona y franja.

### ❗❗ Lo que `mixed` destapó, y no se buscaba

**Dos ejecuciones idénticas dieron 2 y 1 ganadores.** No es un fallo del verificador: es que **los
dos aforos NO son independientes**, y lo son **en una sola dirección**.

▶ **Medido en frío**, sin concurrencia: franja de **10 plazas** → se crea una fiesta de **8
invitados** → quedan **2 plazas de entrada**.
▶ **La causa**: `SlotAvailability::occupancyMap()` suma los `seats` de **todos** los `order_items` de
la zona/día **sin filtrar por tipo**, y una línea de pack lleva `seats` como cualquier otra.
▶ **La asimetría**: una entrada **no** consume cupo de fiestas; una fiesta **sí** consume asientos
de entrada. Por eso el orden de llegada decide, y por eso el nº de ganadores varía.

❗ **Y contradecía la doc.** El docblock de `PackAvailability` afirmaba: «POOL PROPIO … un cumpleaños
**no resta plazas de entrada** ni viceversa». **La mitad de esa frase era falsa** y llevaba ahí desde
`#82`. Corregida, con la medida y la causa dentro.

⚠️ **PENDIENTE DEL OWNER, y es de producto, no de ingeniería**: ¿debe una fiesta ocupar plazas de
entrada? **Puede ser lo correcto** —los niños están físicamente en el parque y ocupan sitio— y
entonces solo faltaba decirlo. O puede ser un defecto, y el arreglo sería filtrar por tipo en
`occupancyMap`. **El verificador no decide eso: lo mide y lo deja escrito.**
▶ Lo fija `PackConsumesEntrySeatsTest`, que **no juzga**: fija el comportamiento medido en sus dos
direcciones para que el día que cambie, cambie porque alguien lo decidió.

### Y por qué `mixed` mide TOPES y no ganadores

El primer diseño exigía «exactamente 2 ganadores» y **falló**, correctamente: pedía determinismo a
una carrera legítima. El invariante bueno es el que se cumple siempre:
**ningún tope superado** (`entradas ≤ online_capacity`, `fiestas ≤ max_per_slot`) **y al menos una
venta** —si no vendiera nadie, el escenario no habría medido nada—.
▶ Es la misma lección que la guarda del instrumento de `#147`, aplicada al invariante en vez de a la
siembra: **un criterio que no puede fallar tampoco puede demostrar nada.**

### Lo MEDIDO

Sobre **MySQL real**, `pcntl_fork`, 8 y 16 workers — los **cinco** escenarios:

| Escenario | Resultado |
|---|---|
| `entry` · `pack` · `pack-guests` | 1 compra · N−1 `sold_out` (sin cambios respecto a `#147`) |
| **`pack-prep`** | **1 fiesta viva en el día**, aunque los compradores pedían dos horas distintas |
| **`mixed`** | ningún tope superado; **ganadores 2, 2 y 1 en tres ejecuciones** — y es correcto |

### El CONTROL NEGATIVO de los dos nuevos

Retirado el `lockForUpdate()` de `OrderCreator::lockSlots`:

| Escenario | Con el lock retirado |
|---|---|
| `pack-prep` | **8 fiestas donde cabía 1** |
| `mixed` | **4 entradas y 4 fiestas**, donde cabía **1 de cada** — los dos pools sobrevendidos |

▶ `OrderCreator` restaurado y verificado por **md5** y por **`git status`**.

### Un defecto del instrumento que se vio y se arregló

El primer contador de `mixed` sumaba asientos y líneas sin filtrar por tipo, y dio **11** donde debía
dar **2**. **Parecía una sobreventa y era un fallo de la medida.** Hoy `liveLines()` filtra por
`ticket_types.type` y su docblock lo explica.
▶ La regla que esto deja: cuando un verificador da un número raro, **lo primero que hay que dudar es
del contador**, no del sistema.
## #149 · 2026-08-25 · [DECIDIDO] Los escenarios del owner, MEDIDOS con sus números — y D5 EJECUTADO: «Reembolsar» ya pregunta CUÁNTO

**De dónde sale.** El owner bajó `#146` a tres casos concretos —entrada de 40 € movida a un día de
30 € · movida a uno de 50 € · cumpleaños pagado entero SIN señal— y pidió saber qué ve el operador,
qué ve el cliente, qué email llega, si el dinero «sale en el parque» o por Redsys, y si cancelar y
reembolsar el total devuelve 40 ó 30. **Todo se midió ejecutando** (sonda sobre el MySQL local:
`OrderCreator` → vuelta de Redsys FIRMADA → acciones del panel por Livewire; pasarela doblada,
emails capturados con `Notification::fake`), y después **se arregló D5** con su red y sus mutaciones.

### La medición, con los números del owner (7 pedidos, quedan en la BD local)

**BAJADA 40→30 (entrada, pagada entera online).** El previo del operador dice la verdad
(`40,00 → 30,00 · −10,00`); al guardar, el ledger cuenta bien (`valor 30 · cobradoWeb 40 ·
pendienteDevolucion 10`) y la pantalla del cliente es honesta («Cobrado por web 40,00 · Tenemos
pendiente devolverte 10,00 €», más la frase de `#134`). Lo roto era la caja de herramientas:
- **D2 confirmado**: el toast dice «unidades canceladas» y el pie «reducción de cantidad o
  cancelación» — fue un cambio de fecha.
- **D3 confirmado**: «(ninguna fila de ajuste)» — ni una línea explica los 10 €.
- **D5 medido con estos números**: «Reembolsar» la línea devolvía **30,00** debiendo 10,00 →
  **20,00 € regalados** (`compensado 20`, y el cliente leía «Te devolvimos 20,00 € y conservas tu
  reserva»). ⚠️⚠️ **Y no es solo la línea**: «Reembolsar el pedido» SIN cancelar devuelve SIEMPRE
  `payment.amount` — medido: devolvió **40,00** debiendo 10,00 (**30,00 regalados**). `#146` listaba
  D5 solo en la línea; **son las DOS acciones**.
- **Los 10 € NO «salen en el parque»**: no existe ningún mecanismo de pagar en puerta al cliente
  (los créditos de puerta solo REDUCEN lo pendiente de cobrar allí, y aquí es 0). La única vía es el
  panel → Redsys (REST) o «manual».
- **¿Cancelar y reembolsar el total: 40 ó 30?** En UNA acción («Reembolsar el pedido» + «también
  cancelar»): **40,00 €, correcto**. ⚠️⚠️ **Si se CANCELA PRIMERO, callejón sin salida medido**: el
  total queda bloqueado para siempre (`already_cancelled`), la línea devuelve solo 30,00 (el tope
  reconstruye por el precio YA re-tarificado — **D4** con estos números) y **los 10,00 restantes
  quedan ATRAPADOS**: línea a 0, total bloqueado, también en manual. El cliente lee «Tenemos
  pendiente devolverte 10,00 €» **eternamente**. El orden de dos clics decide entre correcto e
  irreversible.
  ⚠️⚠️ **CORREGIDO en `#150`**: los 30,00 «que devolvía la línea» salieron del DOMINIO (el batch,
  que es donde esta sonda midió al no completar Livewire la acción en tinker). **En el PANEL, un
  pedido CANCELADO no tiene NI ESA vía**: `refundItemBlockedReason` devuelve `order_not_paid` sobre
  cancelados — con el pedido entero cancelado, el panel no puede devolver NADA (ficha en `DEUDA.md`).
  El callejón real de D4 era vía **cancelar la RESERVA** (pedido sigue `paid`), y ése es el que
  `#150` cierra.
- ❗ **HALLAZGO NUEVO — el email de la bajada no menciona el dinero**: `OrderItemModified` dice solo
  «Nueva fecha y hora: … → …». La línea de importe existe para la subida (`extra_due`) y la de
  reembolso va cableada a `null` desde D8. El cliente solo se entera de que le deben 10 € si entra
  a su desglose. Ficha en `DEUDA.md`.

**SUBIDA 40→50 (entrada).** Impecable, como decía `#146`: previo `+10,00`, ajuste con causa
(«Cambio de fecha a …»), cliente «ahora vale 10,00 € más» + «Te quedan 10,00 € por pagar en
recepción», y **el email SÍ lleva la línea** («Pendiente de pago al llegar al parque: 10,00 €»).
Cancelar + reembolso total después: **40,00** (los 10 nunca se cobraron; su cargo muere con la
cancelación). Sin nada que arreglar.

**CUMPLEAÑOS pagado entero SIN señal (40→30 y 40→50).** **Idéntico a la entrada en los dos
sentidos** — todo lo anterior aplica — con un defecto extra que llega al CLIENTE:
- ❗ **HALLAZGO NUEVO (el SEXTO sitio del tronco `PAY-18`)**: la línea de puerta de la subida dice
  **«+2 S146 Pack SUBE»** — narra un cambio de FECHA como si se hubieran añadido 2 packs. Es la
  heurística de `Order::gateLineLabel` («importe múltiplo del precio unitario ⇒ cambio de
  cantidad»), escrita cuando solo la cantidad podía crear cargos; con la re-tarificación por fecha
  en packs dispara casi siempre (diff = invitados × Δprecio). `#146` contó cinco sitios; éste es el
  sexto. Ficha en `DEUDA.md`.
⚠️ El pack **CON señal** (los reales llevan `deposit fixed`) **sigue sin medir**: la cascada absorbe
contra lo pendiente del parque y es otro comportamiento.

### Más hallazgos de la sonda

- ❗ **El cliente EN/FR ve claves en crudo en su desglose**: `admin.orders.order_financial.breakdown.
  slot_change`/`product_change` existen **solo en ES** (`Lang::has`, verificado), y la etiqueta de
  las líneas de puerta del CLIENTE se compone con ese espacio de nombres del ADMIN — medido por
  HTTP: un cliente en inglés recibe la clave literal como etiqueta. La guarda del retariff no lo
  caza porque compara `__()` contra `__()` (la lección de `#134` §23.6, otra vez). Ficha en `DEUDA.md`.
- La nota del cliente tras estos reembolsos narra solo la parte de compensación («Te devolvimos
  20,00 €» cuando salieron 30,00) — el eje de caja enseña el total; la frase se queda corta.
  Observación de producto, menor.
- ⚠️ **La BD local llevaba TRES migraciones sin aplicar** (`payment_refunds.intent` —de `#131`—,
  `zones.color_secondary`, `ticket_types.icon`): el panel de reembolsos ni siquiera podía escribir.
  Aplicadas. Si el panel local hace cosas raras tras un pull: `migrate:status` ANTES de depurar.
- Método, pagado tres veces: **el kernel in-process contamina el locale del proceso** (una petición
  API sin `Accept-Language` deja `en` y los `__()` posteriores salen crudos — parecía un defecto del
  panel y era de la sonda) · **la action `refundItem` no completa bajo Livewire en tinker** (capa de
  form de Filament; el resto de acciones sí) — el dinero se midió en el punto EXACTO que llama el
  panel, `executePartialRefundBatch(alsoCancelItems: false)` · **`git restore` en mitad de una
  mutación borra el trabajo sin commitear** — las mutaciones se deshacen con `sed`, no con git.

### D5 EJECUTADO — el diseño y sus porqués

Un `Radio` «¿Cuánto devolvemos?» con dos opciones — **el remanente completo** (la conducta de
siempre, ahora dicha en voz alta) y **«otro importe» con campo** — más la SUGERENCIA delante del
operador: el pie del campo dice cuánto tiene el pedido «pendiente de devolución», y el campo llega
pre-rellenado con esa cifra. Decisiones dentro del diseño:
1. **El importe elegido exige UNA línea.** Con varias sería ambiguo a cuál se atribuye, y la
   atribución por línea es lo que el eje de caja (`PAY-17`) explota para explicar el desglose.
2. **Ningún tope se relaja**: el form valida, el handler re-valida server-side (con su audit de
   bloqueo por capa, como el resto de la acción) y `executePartialRefund` re-valida BAJO LOCK el
   remanente del item y la capacidad del pedido (`PAY-09` intacto). Exceder el remanente **falla en
   voz alta**, no se capa en silencio: devolver menos de lo tecleado sería un error callado.
3. **El default sigue siendo el remanente** — el payload viejo (sin `amount_mode`) se comporta
   idéntico; hay caso que lo fija.
4. ⚠️ De paso cayó **una mentira preexistente del propio modal**: `modal_description` decía «cada
   uno se marcará como cancelado» — FALSO desde `#157` (reembolsar no cancela; esta sonda lo midió).
   El operador leía una cancelación que no iba a ocurrir. Es de la familia D2 y no estaba contada.

**Medido**: suite **2801 → 2809** (+8, `RefundItemCustomAmountTest`) y **16.211 → 16.264**
aserciones · **5 mutaciones, las 5 muerden** (ignorar el override → 2 rojos · quitar el tope por
línea bajo lock → 1 · permitir multi+override → 1 · perder el ×100 al parsear euros → 2 · quitar el
pre-check del handler → 1, cazado por la capa del dominio) · Pint ✓ · **y verificación empírica
sobre MySQL real con el flujo real** (`R-ZDRAYL`: cobrados 40,00 → movida a día de 30,00 →
devueltos EXACTAMENTE 10,00 → `pendienteDevolucion 0 · compensado 0 · devuelto 10` — nada regalado,
nada atrapado; el cliente lee «devuelto 10,00» con su frase de `#134` e `is_consistent: true`).

### Las preguntas del owner sobre el diseño, respondidas

- *«¿Podré devolver el resto de la entrada, o se devolverá el total?»* — **Ya puedes devolver el
  resto exacto** (es el caso de `R-ZDRAYL`). El reembolso del PEDIDO sigue devolviendo el total
  cobrado — su variante sin cancelar sigue sin preguntar importe (ficha en `DEUDA.md`).
- *«¿Sería mejor marcarlo como a devolver en el parque?»* — **Recomendación: NO**, y queda
  [PENDIENTE: owner ratificar]. Sería un canal de dinero NUEVO contra `PAY-16`/`PAY-17` (hoy la
  puerta solo cobra), con caja física sin rastro bancario — rompe el ancla de conciliación de `#128`
  («puedes cotejarlo con tu extracto»)—, y con la superficie de fraude/descuadre de pagar efectivo
  en puerta. Redsys ya devuelve a la MISMA tarjeta, trazable. Si el negocio quiere «te lo dejamos
  como saldo para otra visita», eso es la spec de JumpPoints/vales (Fase 6, `#142`), no un apaño en
  el reembolso.

### Lo que queda ABIERTO de esta línea

**D4 + D3 con el mismo cambio** (que `itemOriginalOnlineCents` reconstruya también por precio
unitario original; el dato existe desde `#145`) y **D2 de paso** — el plan de `#146` no cambia. Más
las fichas nuevas: el «+2» de `gateLineLabel` (sexto sitio) · las claves de desglose solo-ES
servidas al cliente EN/FR · el email de bajada mudo · el importe en el reembolso a nivel PEDIDO.
El callejón cancelar-primero **lo cierra D4** (con el tope correcto, la línea podrá devolver los
40,00 enteros). Y el pack CON señal, sin medir. ⚠️ El modal nuevo está verificado por Livewire y
mutación; **verlo en navegador** (reactividad del campo) queda para el siguiente pase de panel.

## #150 · 2026-08-25 · D4+D3+D2 CERRADOS — la reconstrucción sobrevive al cambio de PRECIO, y el pack CON señal queda MEDIDO

**De dónde sale.** El owner aprobó continuar el plan de `#146` tras `#149`. Esta entrada cierra los
tres defectos restantes del tronco `PAY-18` **y el sexto sitio** que `#149` encontró, con la
verificación empírica sobre MySQL y la medición que faltaba (pack con señal).

### El arreglo, en una frase

**El cambio de precio unitario viaja como cambio ESTRUCTURADO** (`unit_price_change: {old, new}`)
en el contexto de los ajustes — igual que ya viajaban cantidad, producto y franja — y todo lo que
antes solo sabía leer cantidad ahora lee las dos causas:

- **D3** · `recordReductionMarker` se dispara con CUALQUIER cambio reconstruible (cantidad o
  precio), no solo `quantity_change`. Una bajada por fecha en un pedido pagado íntegro deja su
  marcador de 0 € con la causa entera — medido en vivo: `changes=[slot_change,unit_price_change]`,
  etiqueta «Cambio de fecha a 07/09/2026 12:00». Se acabó el «(ninguna fila de ajuste)».
- **D4** · `itemOriginalOnlineCents` reconstruye `cantidad_original × precio_original`, cada
  término del **PRIMER** cambio de su clase (con desempate por `id`: dos ediciones pueden caer en
  el mismo segundo y el orden de una relación sin `orderBy` no es contrato). Los topes de línea
  vuelven a decir lo que se COBRÓ: medido en vivo (`R-VLRYUV`): 40→30, `originalOnline=40 · tope
  línea=40 · pendiente de línea=10` — antes 30/30/0.
- **El SEXTO sitio** (`#149`, «+2 Pack») · la etiqueta «+N producto» de `gateLineLabel` **exige un
  `quantity_change` real** además de la divisibilidad. Medido en vivo sobre `R-8STAH6` (pedido
  PRE-arreglo): la línea que el cliente leía como «+2 S146 Pack SUBE» ahora dice «Cambio de fecha a
  05/09/2026 10:00» — también para ajustes viejos, porque el contexto de `#145` ya llevaba el
  `slot_change`. ⚠️ Cargos ANTERIORES a `#145` (contexto vacío) caen al respaldo honesto
  («Diferencia por cambios en X») en vez de a una cantidad inventada.
- **D2** · el toast elige la variante que es VERDAD (la causa ya viaja): bajada de cantidad →
  «unidades canceladas» (cierto ahí); bajada de precio → clave nueva `success_edited_reduced_price`
  («el nuevo precio es más bajo y la diferencia queda pendiente de devolver…»). Y los TRES pies de
  «pendiente de devolución» añaden la causa que faltaba («…o un cambio a un precio menor»). El
  registro del pedido etiqueta el cambio nuevo (`change_kinds.unit_price_change`).

### ⚠️⚠️ EL CALLEJÓN, cerrado — y una CORRECCIÓN a `#149`

El camino «cancelar tras una bajada» que atrapaba dinero era vía **cancelar la RESERVA** (el pedido
sigue `paid`): ahora el tope alcanza y la línea devuelve TODO. Medido en vivo (`R-VLRYUV`): bajada
40→30 → cancelar reserva → «Reembolsar» → **40,00 devueltos, pendiente 0, nada atrapado**, con el
cliente leyendo «te devolvimos 40,00» e `is_consistent: true`.

⚠️⚠️ **La corrección**: `#149` afirmó que con el PEDIDO entero cancelado «la línea devuelve 30,00».
Eso lo midió en el DOMINIO (el batch). **En el panel, un pedido CANCELADO no tiene NINGUNA vía**:
el total se bloquea (`already_cancelled`) y la línea también (`refundItemBlockedReason` →
`order_not_paid`, y el banner lo declara). El dominio sí sabe; el panel lo veta. Hay un caso de
suite que FIJA esta realidad para que no cambie en silencio, y una ficha nueva en `DEUDA.md`:
decidir si la línea se abre para cancelados con deuda (recomendación: sí, es la mitad que le falta
al arreglo de D1 en `#127` — el cliente se entera de la deuda pero el parque no puede pagarla desde
el panel). Mientras tanto, la vía operativa es **reembolsar ANTES de cancelar, o en UNA acción**.

### El pack CON señal, MEDIDO (lo que `#146` dejó sin ejecutar)

`R-DWFRDP` (señal fija 15,00 sobre 40,00; cobrados 15,00 online, 25,00 al parque) movido a un día
de 30,00: **la cascada absorbe** — crédito de −10,00 contra el resto de la señal (con su causa:
«Cambio de fecha a …»), el resto del parque baja a 15,00, `pendienteDevolucion 0` y el cliente
simplemente paga menos al llegar («Te quedan 15,00 € por pagar en recepción»). **No hace falta
reembolsar nada**, las dos identidades cierran, y la reconstrucción respeta la señal
(`originalOnline = 15,00`, el tope de línea también). Lo que `#146` leyó en el código es lo que
pasa.

### Método — tres trampas nuevas, pagadas aquí

- **`callMountedAction($data)` NO rellena el form**: su parámetro son ARGUMENTS. Los datos van por
  `setActionData()`. El override de cantidad se ignoraba en silencio y el caso combinado probaba
  otra cosa (el fixture del retariff no lo sufría porque `mountUsing` pre-rellena).
- **Un `Http::fake()` pelado registrado ANTES se come el fake específico posterior**: el catch-all
  respondía 200 vacío a la REST de Redsys y el reembolso salía `failed` sin que nada explicara por
  qué.
- **Con el reloj CONGELADO, «el primero» y «el último» degeneran al orden de iteración**: la
  mutación earliest→latest salía VERDE porque los dos ajustes compartían `created_at`. El caso de
  la cadena adelanta el reloj entre ediciones — y el código desempata por `id`.

### Lo medido

Suite **2809 → 2819** (+10, `ItemPriceChangeReconstructionTest`: los CUATRO caminos de `#146` —
bajar cantidad · bajar precio · las dos · cancelar tras bajada— más la cadena de ediciones, el
pedido cancelado sin vía, las dos etiquetas y los dos toasts) y **16.264 → 16.352** aserciones ·
**6 mutaciones, las 6 muerden** (ignorar el precio → 4 rojos · coger el último → 1 · marcador solo
cantidad → 3 · filtro sin la clave → 4 · etiqueta por divisibilidad → 1 · toast sin causa → 1) ·
Pint ✓ · verificación en vivo sobre MySQL con los flujos reales (`R-VLRYUV`, `R-8STAH6`,
`R-DWFRDP`).

### Lo que queda ABIERTO de esta línea (fichas en `DEUDA.md`)

El importe en el reembolso a nivel PEDIDO (regala si no se cancela) · el pedido CANCELADO sin vía
de panel (decisión de producto, recomendación escrita) · las claves de desglose solo-ES al cliente
EN/FR · el email de bajada sin la línea de dinero · y la ratificación del owner sobre «a devolver
en el parque» (`#149`, recomendado NO).

## #151 · 2026-08-25 · [DECIDIDO, owner] La independencia de cupos se hace POR ZONA — el consumo medido en `#148` es CORRECTO

**La pregunta que `#148` dejó esperando**: una fiesta de 8 invitados en una franja de 10 plazas
deja 2 plazas de entrada — ¿correcto o defecto?

**La decisión del owner (2026-08-25), literal**: *«La configuración está diseñada así para que esos
cupos los separe la ZONA; más adelante crearé otro producto —excursiones de colegio— y su zona,
para gestionar las plazas independientes de entradas o cumpleaños.»*

▶ **Traducido a regla**:
1. **Dentro de una zona, `seats` cuenta ocupación FÍSICA real, sea del producto que sea.** Que una
   fiesta consuma asientos de su zona no es un defecto: es el contador diciendo la verdad del suelo.
2. **La independencia de cupos se consigue con ZONAS, no filtrando por tipo.** Un producto que
   necesite plazas propias se lleva a su zona — exactamente como está configurado hoy (cumpleaños
   en `cumpleanos`, entradas en `jump`/`kids`) y como se hará con el siguiente (excursiones de
   colegio → zona propia). `SlotAvailability::occupancyMap()` **no se toca**.
3. `PackConsumesEntrySeatsTest` deja de ser «comportamiento fijado sin juzgar» y pasa a ser **la
   guarda de la regla decidida**.

▶ **Consecuencia para instalar/configurar (white-label)**: productos que comparten zona comparten
sitio físico. Si al montar un cliente se quiere que un pack no muerda las plazas de las entradas,
la respuesta es «zona propia», no un ajuste ni un filtro. (Es la advertencia que `#148` dejó para
la landing del 2º cliente, ahora con la regla decidida en vez de abierta.)

Sin código: la decisión CONFIRMA el comportamiento existente. Se actualizan los tres sitios que
decían «pendiente de decisión» (el 1.bis de `ESTADO`, el docblock de `PackAvailability` y el del
test).

## #152 · 2026-08-25 · [DECIDIDO, owner] Un pedido CANCELADO con deuda se reembolsa POR LÍNEA — y «devolver fuera» ya tenía su vía

**Las dos decisiones del owner (2026-08-25), sobre las fichas que `#149`/`#150` dejaron:**

**1 · Los pedidos cancelados con deuda se reembolsan POR LÍNEA.** Hasta hoy, un pedido cancelado
no tenía NINGUNA vía de panel (total `already_cancelled` + línea `order_not_paid`, medido en
`#150`): el cliente leía «tenemos pendiente devolverte X €» para siempre. La regla nueva: cancelar
cancela el PRODUCTO; el dinero cobrado se sigue debiendo y se devuelve con «Reembolsar» por línea
— con la elección de importe de `#149` incluida. **El TOTAL sigue vetado a propósito**: devolvería
`payment.amount` entero sin preguntar, y la atribución por línea es la que explica el desglose.

**2 · «¿Marcar a devolver en el parque?» — no hace falta construir NADA: ya existe.** La pregunta
del owner («se deja una línea de que está devuelto fuera y ya está, ¿no?») es exactamente el modo
**«Solo registrar (ya devuelto fuera)»** del modal, cuyo texto lleva «efectivo» desde el origen y
que desde `#149` acepta importe exacto. Queda decidido: devolver en mano se REGISTRA con el modo
manual; no se construye ningún canal automático de salida de dinero en puerta (los motivos, en
`#149`). ⚠️ El único matiz: un reembolso en efectivo no aparece en el extracto bancario del
cliente — el registro del panel es su único rastro.

### La ejecución de la 1

- `GuardsItemRefunds::refundItemBlockedReason` acepta `CANCELLED` además de `PAID`. **Ningún tope
  cambia**: capacidad del pedido y remanente por línea siguen mandando bajo lock (`PAY-09`), y un
  cancelado con TODO devuelto sigue bloqueado (`already_fully_refunded`). Un `pending` sigue
  siendo `order_not_paid`.
- **El banner del pedido cancelado deja de ser el cartel del callejón.** Decía «reembolsar ya no
  aplica» — con deuda ahora dice **cuánto se debe y por dónde se devuelve** (importe delante); sin
  deuda conserva el texto cerrado, que ahí sí es verdad.
- El botón por línea se abre solo (su visibilidad ya preguntaba a `canRefundItem`).

**Lo medido**: suite **2822 → 2824** (+2, 16.361 → 16.379) — el caso e2e del cancelado con deuda
(bajada → cancelar pedido → reembolsar línea → 40,00 fuera y «pendiente de devolverte» a CERO), el
candado del cancelado sin deuda, y los dos banners · **2 mutaciones, las 2 muerden** (guard a
solo-PAID → 3 rojos · banner ciego a la deuda → 1 rojo) · Pint ✓ · docs-check ✓.
⚠️ Método: el «icono ↩ por línea» ya no existe en la lista (#171) — la entrada es el pie del modal
Gestionar, gateado por `canRefundItem`; las guardas de UI se aseveran sobre ESE contrato, no sobre
un marcado que ya no se emite.

## #153 · 2026-08-25 · [DECIDIDO, owner] El reembolso a nivel PEDIDO se queda SIN campo de importe — pero NOMBRA lo que va a devolver y señala la vía de los parciales

**La ficha**: «Reembolsar el pedido» SIN cancelar devolvía siempre `payment.amount` entero — medido
en `#149` con los números del owner: devolvió 40,00 debiendo 10,00 (30,00 regalados como
`compensado`). El modal ni siquiera decía CUÁNTO iba a devolver.

**La decisión (owner, 2026-08-25)**: **aviso claro, sin campo**. El uso legítimo de esta acción sin
cancelar es el canje —devolver todo lo pagado online porque el cliente pagará en recepción, donde
el total ES lo correcto—; para devolver una DIFERENCIA ya está la línea (`#149`), cuya atribución
es la que explica el desglose. Duplicar el campo a nivel pedido añadía mecanismo con peor
atribución (el parcial de pedido se reparte a prorrata).

**La ejecución**:
- El `modalDescription` **nombra el importe exacto**: «Se devolverán 18,15 € — TODO lo cobrado de
  este pedido…» (las dos variantes, con y sin servicio prestado). Cuando esta acción es alcanzable
  nunca hay reembolsos previos (`refundBlockedReason` bloquea tras cualquier parcial), así que el
  importe es siempre el pago entero.
- Con «también cancelar» DESACTIVADO —la forma exacta del error medido— aparece el aviso:
  «devuelve el pedido ENTERO; para una parte usa Reembolsar dentro de Gestionar del producto».
  Con el toggle activo no estorba: devolver todo y cancelar es el uso correcto.

**Lo medido**: suite **2824 → 2826** (+2, 16.379 → 16.387) · **2 mutaciones, las 2 muerden**
(descripción sin importe · aviso nunca visible) · Pint ✓ · docs-check ✓.
⚠️ Método: **el HTML de un modal de acción NO viaja en el render del page-component** (Filament
v4) — `assertSee` sobre la página sale vacío aunque el texto exista. La descripción se asevera
sobre `getMountedAction()->getModalDescription()` y la visibilidad del aviso con
`assertSchemaComponentHidden/Visible`, que evalúan el objeto real.

## #154 · 2026-08-25 · El cliente EN/FR deja de leer claves en CRUDO — la etiqueta que lee el cliente vive en el espacio del cliente

**La ficha** (`#149`): las etiquetas específicas del cargo de puerta —«Cambio de fecha a :when» y
«Cambio a :name»— vivían en `admin.orders.order_financial.breakdown.*`, que solo existe en ESPAÑOL
(el panel es es+zh). Pero `breakdownLabel()` alimenta también las `gate_lines` del ledger del
CLIENTE — medido por HTTP: un cliente en inglés recibía
`admin.orders.order_financial.breakdown.slot_change` LITERAL como etiqueta en su desglose de dinero.

**El arreglo es una mudanza, no una traducción suelta**: etiqueta que lee el cliente ⇒ espacio del
cliente. Las dos claves pasan a `tickets.gate_change_line_slot` / `_product`, junto a su hermana de
respaldo (`gate_change_line`, `#131`), **en los tres idiomas** (ES/EN/FR). El panel comparte el
texto, que es neutro de voz. Las claves de `admin.*` se retiran (muertas — la lección de `#145`).

**La guarda aplica la lección de `#134` §23.6**: `Lang::has(clave, locale, fallback: false)` por
clave × idioma —una clave que falta NO sale en crudo: sale un francés leyendo castellano en su
pantalla de dinero— **más la composición real bajo `en`** («Date changed to …»), que es la que
muere si la etiqueta vuelve al espacio admin.

**Lo medido**: suite **2826 → 2827** (+1, 16.387 → 16.403) · **2 mutaciones, las 2 muerden**
(borrar la clave EN → rojo · volver al espacio `admin.*` → rojo) · Pint ✓ · docs-check ✓.
⚠️ zh_CN queda como estaba: hueco con nombre (`#145`, owner) — un panel en chino lee ahora el
respaldo EN en vez de la clave cruda, que es estrictamente mejor.
⚠️ Método: en una cadena PHP entre comillas dobles, «`$locale»`» se parsea como UNA variable — los
bytes multibyte son válidos en identificadores. Interpolación con llaves siempre que haya
tipografía pegada.

## #155 · 2026-08-25 · El email de una BAJADA ya cuenta el dinero — con el mismo vocabulario que la pantalla

**La ficha** (`#149`): tras mover la fecha a un día más barato, el cliente al que se le debían
10,00 € recibía un email que solo decía la fecha nueva — la línea de reembolso de
`OrderItemModified` iba cableada a `null` desde D8 (la bajada ya no auto-reembolsa) y no existía
ninguna línea para «lo que se te debe».

**El arreglo cuenta las DOS mitades de una bajada** (el reparto real que `#150` midió):
- lo que AFLORA como deuda → «Este cambio deja :amount € pendientes de devolverte. Lo verás en
  «Mis reservas» y te avisaremos por email cuando procesemos la devolución.» — el MISMO
  vocabulario que la pantalla, para que email y desglose digan lo mismo;
- lo ABSORBIDO contra el parque (packs con señal) → «Con el nuevo precio pagarás :amount € menos
  al llegar al parque.»
El reparto lo calcula el call-site del panel con los créditos REALES aplicados
(`reduction − extraCredit − depositCredit`), no re-derivándolo; en ES/EN/FR, con la voz de cada
fichero de emails.

**Lo medido**: suite **2827 → 2830** (+3, 16.403 → 16.414) — el e2e de la bajada online (la
notificación porta 16,00 y la línea se RENDERIZA), la variante absorbida, y la guarda de idiomas
`Lang::has(..., false)` × 3 (§23.6) · **3 mutaciones, las 3 muerden** (el call-site deja de pasar
el importe · `toMail` pierde la línea · borrar la clave FR) · Pint ✓ · docs-check ✓.

**Con esta entrada, las CUATRO fichas derivadas de `#146` están cerradas** (`#152`–`#155`). De la
línea del cambio de precio no queda nada abierto salvo lo ya decidido y lo aparcado con nombre.

## #156 · 2026-08-25 · La revisión adversarial de los CUATRO subsistemas de Fase 6: dos bloqueantes, y el peor no era de ingeniería

> ⚠️⚠️ **Esta entrada nació como `#149`, pasó por `#152`, `#154` y `#155`, y acabó en `#156`** — CINCO renumeraciones
> en una tarde, porque el agente A fue tomando `#149`→`#153` en sesiones que se solapaban con ésta.
> El aviso de `ESTADO` («elige el número mirando el REMOTO») **funciona y aun así no basta**: los
> números se eligieron contra un remoto correcto **en ese instante**, y dejó de serlo mientras se
> escribía la entrada.
> ❗ **La regla que hay que sacar de esto, y ya está en `ESTADO`**: con dos agentes vivos, el número
> **no se fija al escribir: se fija al EMPUJAR**, y entre lo uno y lo otro se vuelve a mirar. Corolario
> del mismo precio: **empujar pronto**. Cada hora que un commit se queda en local es una hora en la que
> el otro agente elige números y toca ficheros sin poder verlo.
> ▶ **Y traía una SEGUNDA entrada que se ha RETIRADO**: la decisión del owner sobre el aforo («una
> fiesta consume plazas de entrada — es correcto») la registró el agente A en **`#151`**, con las
> palabras literales del owner y derivando la regla general —**la independencia de cupos se hace POR
> ZONA**—, además de dejar hechos los dos docblocks. **Manda `#151`**; duplicarla aquí habría creado
> exactamente la segunda verdad que este proyecto persigue.

`CONVENCIONES` §5 exige que **otro agente revise una spec antes de que se escriba código**. Las cuatro
specs de `#142` llevaban desde el 2026-08-24 esperándolo. Ésta es esa revisión.

**Método, y es la mitad del valor**: no se leyeron las specs buscando errores de razonamiento — se
intentó **refutar cada afirmación suya sobre el repo, ejecutando**. La regla de la casa: *la revisión
no se copia, se MIDE*.

▶ **Resultado global: las cuatro specs son sólidas y ninguna hay que rehacerla.** De todas sus
afirmaciones verificables sobre el código, **ninguna resultó falsa** —lo que en este proyecto no es lo
normal: `#143` encontró tres falsas en una sola spec—. Lo que salió son **huecos**, no errores.

### Los dos bloqueantes

1. ❗❗ **El waiver: el texto que se publicaría es literalmente un borrador.**
   `LandingContentSeeder` lo sirve, en ES/EN/FR, con la cláusula *«Este texto es un borrador y será
   revisado por un asesor legal antes de su publicación. [PENDIENTE: redacción definitiva]»*. Y el
   diseño hace que **publicar sea irreversible**: una versión publicada no se edita ni se borra —que
   es justo lo que le da valor probatorio—. Publicar la v1 sobre ese texto **graba un borrador en la
   cadena para siempre**, y la regla de re-firma («deja pasar») hace que quien lo firmó siga entrando
   con él. ▶ **La maquinaria se puede construir; el acto de publicar, no.** Es un `[PENDIENTE: owner]`
   nuevo, anterior al del plazo de conservación, y **no estaba escrito en ninguna parte**.

2. ❗❗ **JumpPoints: «después de la visita» no era un hecho observable.** Toda la defensa del agujero
   *comprar → ganar → canjear → reembolsar* colgaba de ese instante. Medido: `tickets` **tiene** las
   columnas del ciclo (`prepared_at`, `redeemed_at`, `status`) y **nadie las escribe** —cero
   escritores en `app/`, `resources/` y `routes/`—; el ciclo de canje se retiró y la spec del carné QR
   lo declara **fuera de su alcance**. El único sustituto era «ya pasó la hora de la franja», que es
   otro hecho: **un no-show cobraría puntos**, lo contrario de lo que el programa dice premiar.

### La resolución del owner, que reencuadra JumpPoints

✅ **[DECIDIDO owner]**: los puntos tienen **FUENTES configurables**. Una es la **visita**, acreditada
cuando el cliente enseña el QR y el empleado abre su ficha en la pantalla de puerta; otra es la
**compra**, al confirmarse el pago.

▶ Eso **hace observable la visita** —la observa la pantalla de puerta, que es el subsistema **A**— y
convierte el orden `A → D` del tracker en una **dependencia dura**.
▶ Y el modelo del ledger lo aguanta **sin cambios**: `available_from` ya era **por apunte**.
⚠️⚠️ **Pero abre un frente nuevo que queda escrito antes de construir nada**: la fuente «compra»
**reabre el agujero** si sus puntos son gastables al instante —`#146` midió un reembolso que devolvió
36,00 € donde se debían 4,00; **ese defecto ya está arreglado (`#149`)**, pero la lección de que el
dinero puede volver en cantidades que nadie previó **sigue en pie**—. **Lo configurable es CUÁNTOS
puntos da cada fuente, no
CUÁNDO se abren**: un ajuste que permita «compra → disponible ya» reabre el agujero desde un
formulario, sin que nada falle y sin que nadie lo revise.
⚠️ **Y la acreditación no puede colgar de «se abrió la ficha»**: la ficha se abre varias veces por
cliente y también tecleando un correo, así que el saldo dependería de cuántas veces mire el empleado.
Acto explícito, **idempotente por cliente y día**, auditado.

### Los huecos de mecanismo (no bloquean, pero se deciden ANTES de la primera línea)

- **La cadena de hashes del waiver no tiene punto de serialización.** Pide `hash` + `prev_hash`
  append-only y **no menciona la concurrencia**. Dos firmas simultáneas escriben el mismo `prev_hash`
  y **bifurcan la cadena en silencio** — y una cadena bifurcada no prueba nada, que es la única
  propiedad por la que existe. ▶ Recomendación: **cadena POR TITULAR**, no global: serializa sin lock
  en el camino del alta y es la unidad que se audita de verdad. **Migrar una cadena después es
  rehacerla.**
- **Dónde vive el registro de firma**, que la spec dejó explícitamente «a la revisión»: **tabla
  propia**, no ampliar `consents`. Tres razones medidas, y la primera es que `consents.user_id` es
  `cascadeOnDelete` —hoy inofensivo, porque el panel **no borra usuarios**, solo anonimiza— y una
  tabla cuyo propósito es sobrevivir no cuelga de un CASCADE.
- **El alta PRESENCIAL también escribe consentimientos.** `CustomerRegistrar` crea la fila de
  `privacy` con «el operador confirma haber informado al cliente en persona» — y la spec solo conocía
  `SelfSignup`. Choca con dos reglas suyas a la vez: exige «la versión que el servidor sirvió» (en el
  mostrador no hay petición del cliente) y dice que la re-firma no se pide «nunca en el mostrador»
  (que es donde vive `CustomerRegistrar`). ✅ **[DECIDIDO owner]: el alta presencial produce una firma
  DECLARADA POR EL OPERADOR**, como hoy con `privacy`, con `created_by` guardado y **el PDF diciéndolo
  con todas las letras** — es más débil que el resto del diseño y fingir lo contrario es peor que
  decirlo.

### Dos correcciones que afectan a doc ya escrita

- ⚠️ **`RGPD-01` no contiene la frase que la spec del waiver dice modificar.** La invariante enumera
  **cinco** operaciones de `anonymize()` y **el borrado de consentimientos no está entre ellas**
  (tampoco `roles()->detach()` ni la nulificación de las columnas legales). Es un desfase
  **preexistente**, exactamente la familia que `DEUDA.md` describe: *el gate documental valida
  estructura, no contenido*. ▶ El orden correcto son **dos pasos**: añadir a `RGPD-01` lo que el
  código ya hace y la invariante calla, y **después** restringir la parte del waiver.
- ⚠️ **`anonymize()` borra la prueba del waiver en DOS sitios, no en uno.** Además de
  `consents()->delete()`, hace `'waiver_accepted_at' => null` — **y ése es el sello que la puerta lee
  de verdad** (`ValidarRegistro` decide sobre esa columna). Exceptuar solo el primero deja al titular
  anonimizado como `REGISTERED_NO_WAIVER` en el mostrador, con la prueba conservada y la puerta
  diciendo que no existe.

### Y dos hallazgos del carné QR

- **«Si rota `APP_KEY` solo se pierde el repintado» no sale gratis**: el cast `encrypted` **lanza
  `DecryptException`**, no devuelve `null`, así que cualquier superficie que toque el token da **500**
  en vez de degradar. La degradación elegante hay que construirla.
- **La entropía es fina para lo que ese carné dura**: 10 caracteres Crockford = `32¹⁰ ≈ 2⁵⁰`, en
  **sha256 sin sal** (rápido por diseño, porque tiene que servir para buscar). Un volcado permite
  enumerar el espacio entero. ▶ Hoy lo salva la mejor decisión de esa spec —**escanear no autentica**,
  así que poseer el carné no da acceso a nada— pero es una credencial impresa que vive años. **O se
  sube la longitud con la misma tabla de módulos que ya usaron para descartar la URL, o se escribe por
  qué 2⁵⁰ basta.** Lo que no puede es quedarse sin decidir pareciendo decidido.

### Lo que se verificó y ERA CIERTO (para no re-medirlo)

`consents` sin user-agent ni hash · `CURRENT_VERSION` a mano · `PuertaSettings::waiverCheckEnabled()`
con su fallback no destructivo · la `Page` del waiver dentro de `Page::PROTECTED_ACTIVE_SLUGS` ·
`CookieConsentLog` con `Prunable` y su `model:prune` en `routes/console.php` · **OpenSSL 3.0.13 con
subcomando `ts`** y `openssl` de PHP · **GD e Imagick** presentes · `chillerlan/php-qrcode`
vendorizada · los cinco pasos de `machine.js` exactos · `Identity → Booking\Contracts` permitido por
el grafo (así que la asignación por id entero no crea ninguna flecha) · `ALLOWED` +
`test_every_module_declares_its_allowed_arrows` hacen que `Loyalty` cueste **una línea** ·
`ValidarRegistro` con sus tres defensas · **`Ticket.qr_token` muerta de verdad** (0 consumidores) ·
`QrCode::svg()` con `quietzoneSize = 0` · `AuditLog::CRITICAL_ACTIONS` ya trae
`registrations.validate_rate_limited` · `users.search_minimal` existe · `seats_taken` es el contador
muerto que describen · **0 artefactos de lealtad** en todo el árbol.

▶ **Detalle completo en el §8 de cada spec.** Ninguna de las cuatro pasa a ✅: siguen 🟦 esperando el
✅ del owner, que ahora tiene **dos** cosas más que decidir (la redacción legal del waiver y el plazo
de conservación) y **una** que ya decidió aquí (las fuentes de puntos).


## #157 · 2026-08-25 · Los DOS agentes arreglaron el mismo defecto a la vez — se salvó la mitad que no coincidía

⚠️⚠️ **Esto es, sobre todo, una entrada de MÉTODO.** El arreglo del desglose EN/FR lo hicieron **los
dos agentes en paralelo, sin saberlo**, en la misma tarde. El del panel lo empujó como `#154`; el de
landing lo tenía commiteado en local. Al fusionar, **el arreglo duplicado se tiró entero** y solo
sobrevivió lo que no coincidía: la guarda.

### Cómo pasó, sin adornos

La ficha la abrió `#149` en `DEUDA.md` y la dejó anotada **porque no era de su carril** (es i18n de
cara al cliente, no panel/dinero). El agente de landing la leyó ahí, la midió y la arregló. El del
panel, que la había escrito, volvió sobre ella y la arregló también.
▶ **El reparto por carriles no falló: falló su borde.** Una ficha que un agente abre *fuera* de su
carril es exactamente la que los dos creen suya.
❗ **La regla que sale de aquí, y ya está en `ESTADO`**: antes de abrir una ficha de `DEUDA.md`,
**mirar si el otro agente la tiene abierta** — y decirlo en la foto viva al empezarla, no al cerrarla.

### Qué se tiró y qué se quedó, y por qué

**Se tiró el arreglo entero del agente de landing.** Los dos habían llegado al mismo diagnóstico
—`OrderAdjustment::breakdownLabel()` lo comparten panel y cliente, y traducía desde `admin.*`, que
solo existe en ES— y a la misma decisión: mudar las claves a `tickets.*`.
▶ **Y las suyas eran mejores**: `gate_change_line_slot` / `gate_change_line_product` siguen a la
hermana que ya vivía ahí (`gate_change_line`), mientras que `breakdown_*` inventaba una familia
nueva. Cuando dos soluciones son equivalentes, **manda la que ya está en `main`**; discutir el nombre
habría costado más que el defecto.

**Se quedó la guarda**, porque **no coincidía**. `OrderTotalsBreakdownTest` (`#154`) comprueba que las
tres claves existen en ES/EN/FR con `Lang::has(…, false)` y que la composición real bajo `en` devuelve
la frase inglesa. `ClientMoneyLabelsAreTranslatedTest` añade **cuatro cosas que aquélla no puede ver**:

1. **La guarda de la guarda** — que `Lang::has()` sepa decir que NO. Sin ella, un instrumento roto
   deja verde todo lo demás sin mirar nada.
2. ❗❗ **Que los tres idiomas digan cosas DISTINTAS.** Es el modo de fallo que **el propio docblock de
   `#154` nombra** —«un cliente francés leyendo castellano, que parece texto y no falla nada»— **y que
   su test no asevera**: rellenar `lang/fr` con el texto español deja verde cualquier comprobación de
   existencia. Y ése es justo el arreglo que hace quien tiene prisa por poner un test en verde.
3. **El MECANISMO**: el helper compartido no puede volver a citar `admin.*`, exista o no la traducción
   ese día.
4. **El barrido ANCHO**: toda clave `tickets.*` que cite el dominio, no solo las tres de hoy — con
   **suelo declarado**, porque un escáner que deje de encontrar nada daría verde sin mirar el corpus
   (`#143` §8).

### ❗ La prueba de que no es un duplicado, ejecutada

No se afirma: se midió. Con `lang/fr` relleno con el texto castellano —la mutación del punto 2—:

    OrderTotalsBreakdownTest        → 23 passed  ✅ (no lo ve)
    ClientMoneyLabelsAreTranslated  →  1 failed  ⨯  (lo caza)

▶ Y la otra mutación —devolver la clave al espacio `admin.*`— tumba la del mecanismo, con la
traducción presente: cae por dónde lee, no por qué lee.
▶ **Ésta es la única razón por la que la guarda sobrevivió a la fusión**: sin esa medida, la decisión
honesta habría sido tirarla también.

### Lo que NO se decidió aquí

⚠️ Si esto merece **invariante propia** («lo que lee un cliente se traduce desde un espacio que existe
en los tres idiomas; el dominio nunca traduce desde `admin.*`»). Hoy la regla vive en dos docblocks,
en esta entrada y en dos guardas. Crear una **familia nueva** en `INVARIANTES.md` es un cambio
estructural de doc —toca el recuento del gate y la tabla de enrutado—, así que **se deja al owner**.

Verificación: suite verde sobre el estado FUSIONADO · Pint limpio · `docs-check` verde · las dos
mutaciones ejecutadas y con su salida arriba.

## #158 · 2026-08-25 · [DECIDIDO, owner] Dos agentes sobre `main` a la vez: el canal es el REPO y el protocolo es `CONVENCIONES §10` — y `testimonials` se APLAZA detrás de la landing

**Qué decidió el owner** (2026-08-25 por la tarde): dos agentes trabajan **a la vez** sobre `main`,
cada uno con UNA tarea, y se comunican **por la doc, al hacer push**. Reparto de esta tarde:
**agente A → Fase 6 · waiver** (`specs/waiver-probatorio.md`) y después menores a cargo;
**agente B → `RGPD-01`** (primero la VERDAD de lo que `anonymize()` ya hace, sin tocar conducta) +
la ficha `#121` de `DEUDA.md` (`docs-check` valida que las clases de test citadas en `INVARIANTES`
existan — medido esta tarde: **4 citas muertas de 64**, en `PAY-04` y `SEC-06`).
▶ Y **`testimonials` se APLAZA** hasta que la landing esté terminada: el owner no puede visualizarlo
ahora y una sección que no se puede ver no se puede validar (cuarta condición del DoD,
`CONVENCIONES §3.bis`). `ESTADO` lo decía como «LO SIGUIENTE» y ya no lo es.

**Por qué un protocolo y no otro aviso en `ESTADO`**: el precio ya pagado —siete renumeraciones y un
defecto arreglado dos veces (`#157`)— salió con el aviso puesto. Lo que faltaba no era énfasis sino
**mecanismo**: reclamar = empujar, carriles por FICHERO, mensajes con destinatario que se retiran al
leerse, número al empujar, `pull --rebase` antes de cada push. Vive en `CONVENCIONES §10`; §8 pasa a
decir «una sesión de escritura por CLON», que es lo que siempre quiso decir.

**La dependencia que ordena esta tarde** (la fijó B en `ESTADO`): `RGPD-01` **no contiene hoy la
frase que el waiver modifica** (`waiver-probatorio.md` §8.3). B la corrige primero; el waiver **no
toca esa fila** hasta que B empuje, y entonces añade el paso (2) citando `WaiverRetentionTest`.

Verificación: `docs-check` ✓ (el ancla `§10` resuelve) · gate del `pre-push` en el push de esta entrada.

## #159 · 2026-08-25 · `RGPD-01` describía CINCO operaciones y el código hacía OCHO — y la frase «cualquier PII nueva debe añadirse aquí» pasa de petición a guarda

Lo destapó la revisión adversarial del waiver (`#156`, `waiver-probatorio.md` §8.3): la spec decía
«**`RGPD-01` SE MODIFICA**» y, al ir a buscar la frase que había que modificar, **no estaba**. Esta
entrada la escribe, y de paso convierte en mecanismo lo que era una petición escrita.

### El desfase, medido contra el código

`User::anonymize()` hace **ocho** grupos de operaciones. La invariante enumeraba **cinco**. Callaba
las tres que un lector daría por supuestas y por eso nadie echó de menos:

1. **El borrado del PROPIO titular** — 14 columnas: nombre, email, teléfono, correo pendiente y su
   sello, `last_login_at`, `email_verified_at`, `remember_token`, `locale`, `marketing_opt_in`, la
   contraseña (un `Str::random(60)` que el cast `hashed` vuelve inservible) y **los TRES sellos
   legales**. La celda que se llama a sí misma «la purga CENTRAL y completa» **no mencionaba la purga
   del titular**.
2. `consents()->delete()` y `roles()->detach()`.
3. La **idempotencia**: sobre una cuenta ya anónima devuelve `false` sin ejecutar nada.

❗ **Y lo importante para calibrar la gravedad: los tests SÍ las cubrían.**
`PrivacyTest::test_anonymize_neutralises_the_account_and_clears_consents_and_roles` asevera las tres.
▶ **Así que el agujero era el MAPA, no la red.** En un repo que desarrollan solo agentes eso no es
menor: `RGPD-01` es lo que se lee **antes** de tocar el borrado, y su última línea —«cualquier PII
nueva que se persista debe añadirse aquí»— la convierte en una **lista de comprobación**. Una lista de
comprobación a la que le falta el punto principal enseña a comprobar mal.

### El censo: la frase, hecha ejecutable

`AnonymizeCoversEveryUserColumnTest` declara **columna a columna** de `users` qué se purga
(`SCRUBBED`), qué se conserva y **por qué** (`PRESERVED`), y qué cambia por el mero hecho de guardar
(`INCIDENTAL`). Tres propiedades, y las tres son el diseño:

- **Una columna NUEVA pone la suite en ROJO hasta que alguien la declare.** Eso es lo que faltaba: el
  art. 17 no se incumple con un fallo ruidoso, se incumple con una columna que nadie recordó.
- **Es simétrico**: una `SCRUBBED` que deje de limpiarse cae, y una `PRESERVED` que empiece a
  limpiarse **también**. Un censo de una sola dirección se degrada a comentario.
- Lleva **su guarda de la guarda**: un caso que comprueba que la comparación discrimina y que la purga
  es idempotente. Sin él, un `anonymize()` que devolviera `false` en silencio dejaría verde el resto.

▶ **Verificado por mutación**: retirar `panel_locale` del censo → cae el caso del censo; **quitar
`'phone' => null` de `anonymize()`** → cae el del comportamiento, nombrando la columna. `User.php`
restaurado y comprobado **por md5 y por `git status`**.

⚠️⚠️ **Y el censo deja armado un interlock con el trabajo del waiver**: `waiver_accepted_at` está hoy
en `SCRUBBED` **porque es lo que el código hace**, no lo que debería. Cuando el waiver la mueva a
conservación restringida (`waiver-probatorio.md` §4.6), **este test se pondrá rojo, y tiene que
ponerse**: es la señal de que el cambio llegó, y obliga a moverla de lista **con su razón escrita** en
vez de que la conducta cambie en silencio.

### Y una columna que nadie había declarado nunca

Construir el censo obligó a mirar las 18 columnas de `users` una a una, y apareció **`panel_locale`**:
`anonymize()` no la toca y **nadie lo había decidido** — simplemente no estaba en la lista de nadie.
▶ Queda **declarada como `PRESERVED` con su razón**: es la preferencia de idioma del PANEL, y sobre
una fila ya anonimizada no identifica a nadie. **No se cambia conducta aquí a propósito**: el método
lo va a tocar el waiver, y meter un cambio de comportamiento en una tanda que se llama «hacer que la
doc diga la verdad» sería exactamente la chapuza que este proyecto no hace.
⚠️ Su asimetría con `locale` —que sí se normaliza a «es»— queda escrita como **incidental**: `locale`
se toca por higiene de la fila neutra, no por privacidad.

---

## Y la segunda mitad: el MAPA que lleva a las guardas

`DEUDA.md` tenía en **Alta** un candidato «acotado, barato y ya MEDIDO» (`#121`): que cada nombre de
clase de test citado por una invariante exista de verdad.

❗❗ **Al medirlo, mi primer instrumento dio CUATRO citas muertas y era FALSO.** Tenía dos defectos, y
los dos daban de más: contaba **menciones históricas** como citas vivas, y partía las filas por `|`
sin respetar los `\|` escapados, así que en `SEC-06` leía la columna equivocada.
▶ **La medida correcta: hoy hay CERO citas muertas.** `PAY-04` re-apuntó las suyas el 2026-08-22 y
`SEC-06` las suyas el 23, las dos con su mutación. Las cuatro «muertas» eran los nombres retirados que
esas mismas celdas nombran **como historia**, y lo dicen con todas las letras.
⚠️ Es la regla de la casa aplicada a mí mismo: *cuando dos medidas del mismo corpus no coinciden, la
que sobra no es la que da más — es la que no puede explicar la diferencia.*

### Por qué ese gate no existía, y qué lo hace posible

**Una mención histórica y una cita viva se escriben igual.** Ése era el obstáculo real, no la falta de
ganas: cualquier comprobación automática las confunde, y por eso `#121` se quedó en «candidato».

▶ **La convención que las separa: el TACHADO.** Un test retirado que se nombra como historia se
escribe `~~ClaseTest~~`. Se lee bien para una persona y es inequívoco para la máquina.
▶ Con eso, `docs-check` gana su **check 8**: toda clase `*Test` citada entre backticks en
`INVARIANTES.md` tiene que existir en `tests/`, salvo que vaya tachada. **Push bloqueado si no.**
⚠️ **Alcance deliberado: solo `INVARIANTES.md`.** `DECISIONES` y las specs son narrativa histórica y
están llenas de nombres retirados a propósito; exigirles el tachado sería ruido, no señal.

▶ **Verificado por mutación, en sus dos mitades**: renombrar una cita VIVA (`PrivacyTest` →
`PrivacyRenombradoTest`) pone el gate en rojo; y **quitarle el tachado a una mención histórica**,
también. La segunda importa tanto como la primera: demuestra que lo que hace el trabajo es la
convención, no la casualidad de que esos cuatro nombres estén donde están.

▶ La fila de `DEUDA.md` **no se cierra**: `docs-check` sigue sin validar CONTENIDO —las 794
afirmaciones de `#103` siguen sin instrumento—. Lo que se cierra es su candidato acotado, que era la
mitad barata y la que protege las invariantes.

Verificación: suite **2837 / 16.451** verde (era 2834: +3 del censo) · Pint limpio (858 ficheros) ·
`docs-check` verde **con el check 8 nuevo** · las cuatro mutaciones ejecutadas y con su salida arriba
· `User::anonymize()` **sin cambios** (md5 comprobado).

### Y un tercer hallazgo, que salió de usar el gate contra mí mismo

Al escribir la fila de `DEUDA.md` de esta misma entrada, **`docs-check` me dio un error que era suyo,
no mío**: decía «ancla rota — `§14` no existe en CONVENCIONES/INVARIANTES» (ejemplo), y ese `§14`
era una cita **preexistente y correcta** a `REDSYS §14` (ejemplo). Saltó porque mi texto metió la
palabra «CONVENCIONES» en la misma línea.

▶ **La causa**: el check 2 validaba **por LÍNEA**, no por cita —su propio comentario lo decía—, así
que una línea que nombra dos documentos empareja el `§N` con el equivocado.
❗ **Y esto no es un error benigno**: un gate que da falsos positivos **enseña a reescribir la doc para
contentarlo**. El agente siguiente no arregla el ancla —no hay nada que arreglar—: mueve la frase de
sitio. Eso es peor que no tener el check, porque degrada la doc y encima parece rigor.

▶ **Arreglado**: cada `§N` se ata ahora al documento que lo **PRECEDE** en la línea; si el dueño es
otro documento, el check no opina; y si no hay ninguno delante, cae al criterio viejo para no perder
cobertura.
⚠️ **Y el arreglo tuvo su propia regresión, cazada por el propio gate**: al reconstruir el mapa de
secciones asumí que las de `INVARIANTES` eran familias (`PAY`, `RGPD`) y son **numéricas**
(`## 3 · RGPD / PII`). Tres specs salieron en rojo al instante. Corregido y re-verificado.
▶ **Verificado por mutación, en las tres direcciones.** Un `CONVENCIONES §99` roto → cae (ejemplo).
Un `INVARIANTES §99` roto → cae (ejemplo). Y el caso que lo motivó —`REDSYS §14` junto a
`CONVENCIONES §4` en la misma línea (ejemplo)— **queda verde**.

## #160 · 2026-08-25 · El waiver probatorio, tanda 1 — el NÚCLEO en el árbol: versiones inmutables, firmas encadenadas por titular, los tres modos y la prueba que sobrevive al art. 17

**Qué se hizo** (agente A, spec `specs/waiver-probatorio.md` **§9**): la maquinaria del subsistema B
de Fase 6 sin publicar ninguna versión — que es exactamente la línea que la revisión trazó (`#156`
§8.1: «la maquinaria se puede construir; publicar la v1, no»). Dos tablas inmutables en Identity,
`LegalDocumentPublisher` (publicar es un acto con fecha), `WaiverSigner` (hash canónico fijado +
cadena POR TITULAR con el lock de su fila como primera sentencia), `WaiverStatus` con los TRES modos
de `#142`, la puerta que señala «versión anterior» y deja pasar, `anonymize()` que conserva la
prueba, la poda por plazo y la acción «Publicar versión firmable» en la página del waiver.

**Tres decisiones de ingeniería, dentro del margen de la spec** (detalle y porqué en §9.2):
1. **Sin `retención_hasta`**: el plazo es retroactivo y por instalación → se aplica al podar; sin plazo
   fijado no se poda nada.
2. **Las tablas en Identity, no en Content**: `ModuleBoundariesTest` no deja a Identity mirar a
   Content; el texto llega ya interpolado desde la capa de entrega, así que el snapshot es lo que se
   ENSEÑÓ, no la plantilla.
3. **El sello ≠ la prueba**: en modo interno la puerta lee el registro, no `waiver_accepted_at`. Y
   por eso **un sello sin registro no cuenta** al pasar a interno — aseverado.

**§8.1 dejó de ser un aviso y pasó a ser un MECANISMO**: un texto con `[pendiente` (el marcador del
seeder o el neutro que deja la interpolación sin datos fiscales) no se publica, en ningún idioma.

**Lo medido, que es lo que vale**: 47 casos nuevos · `waiver:verify-chain` sobre MySQL con 8 y 16
procesos, cadena lineal · **y visto FALLAR sin el lock, 3 de 3** (1, 9 y 15 `prev_hash` repetidos) ·
**cinco mutaciones, las cinco muerden** (§9.3) · la serialización canónica fijada como literal.

**`RGPD-01`, paso (2), escrito sobre la corrección (1) de `#159`** (el agente B empujó primero; según
`CONVENCIONES §10`, quien empuja segundo escribe la restricción): `anonymize()` **no toca
`waiver_signatures`** — conservación vinculada con tratamiento restringido y plazo—, y la cita viva es
`WaiverRetentionTest`. ▶ **Y el «interlock» que `#159` dejó esperando NO dispara, y es correcto**: su
censo declara `waiver_accepted_at` como purgada, y **sigue purgándose a propósito** — el sello es
presentación; la prueba vive en su propia tabla (`user_id` RESTRICT), no en una columna de `users`.
El censo se queda como está y en verde.

**Lo que NO se decidió aquí**: el plazo de conservación y el texto definitivo (`[PENDIENTE: owner]`,
los dos).

Verificación: suite verde sobre el estado FUSIONADO (contador en `ESTADO`) · Pint ✓ · `docs-check` ✓
(entonces 32 modelos y ~76 migraciones; con los checks nuevos de `#159`) · BD MySQL de desarrollo
migrada · verificador de cadena y mutaciones con su salida en §9.3.

## #161 · 2026-08-26 · [DECIDIDO, owner] La identidad del firmante viaja EN la firma — y la tanda 2 del waiver: el registro probatorio en el panel, el PDF del snapshot y el alta presencial declarada

**Lo que decidió el owner** (2026-08-26, a pregunta del agente): **sí, la firma guarda el nombre y el
email del titular tal y como estaban al firmar** (no el teléfono). Nació de un hueco de la spec que
apareció al diseñar el PDF: tras `User::anonymize()` la fila de `users` dice «Cliente eliminado», así
que una prueba que solo apuntara al `user_id` **dejaba de identificar a la persona** — y `#142` la
quiere «conservada vinculada, no anonimizada». Es PII conservada **a propósito** bajo el régimen
restringido de §4.6: solo la purga el plazo, nunca la baja de la cuenta. `RGPD-01` lo dice.

**Cómo entra sin invalidar nada**: `holder_name`/`holder_email` entran en el hash con un **esquema
canónico v2**, y cada fila guarda `canonical_version`; `WaiverSignature::canonical()` verifica cada
fila con su versión, así que lo firmado con v1 sigue verificando (no había ninguna firma real; el
verificador limpia las suyas). La serialización de las DOS versiones queda fijada como literal en
`WaiverSignatureChainTest`.

**Y con ella, la tanda 2 (el panel), spec §9.6:**
- Permiso PROPIO **`waiver.view`** (§4.6), fuera de los del staff por defecto, con su etiqueta en
  es y zh_CN (`AccessI18nParityTest`) e insertado idempotente por migración para lo ya desplegado.
- En la ficha del usuario, **una ACCIÓN, no una sección**: «Registro del waiver». Abrirla ES la
  consulta y queda auditada (`waiver.proof_viewed`); lista las firmas con versión, canal, quién la
  declaró, integridad y su PDF. Visible también sobre cuentas anonimizadas.
- **El PDF del snapshot** (`WaiverProof` + `WaiverProofController` + `pdf/waiver-proof`): texto
  íntegro, identidad copiada, fecha con zona, ip, user-agent, canal, hashes y comprobación de
  integridad; **en el idioma del texto firmado** (`lang/{es,en,fr}/waiver.php`, tres idiomas que
  dicen cosas distintas); determinista (sin «generado el»); `no-store` + throttle + IDOR + auditoría.
  **`RGPD-04` amplía.** §6·2 queda demostrada por mutación: editar la página y publicar otra versión
  después de firmar no cambia ni un byte del documento.
- **El alta presencial** (`CustomerRegistrar`) en modo interno deja una firma **declarada por el
  operador** (§8.4) — solo con versión publicada y operador con sesión; el PDF lo dice con todas las
  letras y es «sustancialmente más débil».

**Lo que enseñó, y va a `TESTING.md`**: en Livewire 4 el modal de Filament es un `wire:partial` y
`assertSee` no ve su contenido tras `mountAction()` — se mide dónde sí es observable (§9.7).

**Lo que NO se decidió aquí**: el plazo de conservación y el texto definitivo (`[PENDIENTE: owner]`).
Tanda 3 (API, casilla del alta, cajón, re-firma) sin empezar.

Verificación: suite verde (contador en `ESTADO`) · Pint ✓ · `docs-check` ✓ (32 modelos · 77
migraciones) · BD MySQL de desarrollo migrada · cinco mutaciones de la tanda 2 con su salida en §9.6.

## #162 · 2026-08-26 · La suite auditada contra el RELOJ — y lo que encontró no era «los sábados», era una bomba con fecha para el 1 de septiembre

La ficha llevaba abierta desde `#64` con la etiqueta «sin plan»: *la suite no está auditada contra la
fecha, y ya mordió DOS veces*. Ésta la audita.

### Por qué importaba, en una frase

Un test que solo falla ciertos días **está rojo y aún no lo sabes**. Y el daño no es la tarde perdida:
es que **se aprende a desconfiar del portero**. El día que la suite se ponga roja de verdad, alguien
pensará «será otra vez lo de la fecha». Con **dos agentes** empujando a `main`, además, un rojo que no
es tuyo ya no es solo confuso: no sabes si es tu cambio, el del otro o el reloj.

### El instrumento, y por qué se construyó ANTES de barrer

El proxy estático decía **54 ficheros sospechosos** de 301 (fixture de tarifa por día de la semana +
fecha relativa a hoy + reloj libre). ⚠️ **Barrer 54 ficheros a ciegas habría sido la chapuza**:
congelar en una fecha mal elegida convierte un test que pasa en uno que **pasa por el motivo
equivocado**. Así que primero el instrumento y después la medida.

`Tests\TestCase` lee **`TEST_CLOCK`** del entorno y congela el reloj de la suite entera en ese
instante UTC. Dos propiedades, y las dos son el diseño:
- **Sin la variable no hace nada.** Es la condición para que pueda vivir en infraestructura
  compartida con el carril del waiver.
- **Con basura EXPLOTA.** Un instrumento que se autodesactiva al no entender su entrada da un verde
  que no significa nada — y eso es peor que no tenerlo. ▶ Verificado: con `TEST_CLOCK='esto-no-es-una-fecha'`
  la suite muere nombrando la variable.

⚠️ **Hueco declarado**: el reloj **no llega a `tests/Unit/`**, que por `CONVENCIONES §3.ter` extienden
`PHPUnit\Framework\TestCase`. Medido: son 4 y ninguno depende de la fecha. Hoy no es un agujero; el día
que un test Unit gane calendario, esta auditoría no lo verá.

### Lo que encontró: DOS defectos, y ninguno era el que se buscaba

**1 · ❗❗ Un fixture con una ventana escrita a mano es una BOMBA CON FECHA.**
`ModuleContractsTest` declaraba una temporada «Verano» del `2026-07-01` al `2026-08-31`, y
`ScheduleDisplay::seasons()` descarta las temporadas ya terminadas (`endsOn >= hoy`). Medido al día:
**verde el 31 de agosto, ROJO el 1 de septiembre**. Iba a tumbar el gate de los **dos** agentes seis
días después de medirlo, sin que nadie tocara nada y sin ninguna pista de por qué.
▶ **Y no hay defecto de diseño detrás**: filtrar lo que ya pasó es presentación, y `is_current` lo
sigue decidiendo Booking. El que dependía del calendario era el fixture.

**2 · ❗❗ La aritmética de meses de PHP DESBORDA, y en un fixture eso no es teórico.**

    hoy 2026-08-31 → +2 meses = 2026-10-31 · +3 meses = **2026-12-01**   (se salta noviembre)
    hoy 2026-12-31 → +2 meses = 2027-03-03 · +3 meses = **2027-03-31**   (los dos en marzo)

`ManageItemSlotChangeTest` construye su fixture con `today()->addMonths(2)` y `addMonths(3)` dando por
hecho que distan **un** mes. Varios días al año distan **dos, o cero**, y sus dos casos del calendario
caían en fin de mes y en fin de año.
▶ **El código de producción está BIEN**: `ViewOrder::calendarPrevMonth()` solo se niega a ir antes del
mes actual, que es correcto. Lo comprobé antes de tocar nada, porque la diferencia entre «el test está
mal» y «el panel no deja retroceder los días 31» es la diferencia entre un fixture y un defecto de
producto.

▶ **Arreglados los dos con la regla que `TESTING.md` §2 ya tenía escrita** —congelar con una constante
documentada—, no inventando otra. Y la constante del calendario es **un día 15**: así `+2`, `+3` y
`+7` meses caen todos en día 15 y ningún salto puede desbordar.

### El barrido, hecho repetible

`scripts/audit-clock.sh` corre la suite entera en **diez fronteras**: tres días de la semana, tres
horizontes futuros (1 mes, 6 meses, 1 año), fin de mes, fin de año y **las DOS medianoches**.
⚠️⚠️ **Sus fechas se calculan RELATIVAS a hoy, y eso es el hallazgo de método de esta tanda**: una
lista de fechas fijas **caduca igual que los fixtures que persigue**. El defecto 1 se encontró porque
una de las fronteras cayó en septiembre; con fechas fijas escritas hoy, dentro de un año no
encontraría nada.

❗ **Y las DOS medianoches, que es lo que la ficha no decía.** La app guarda en UTC y **muestra en
`Europe/Madrid`** (`DisplayTime::DEFAULT_TIMEZONE`), así que en verano hay dos fronteras: la de UTC
cae a las **02:00** de Madrid y la de Madrid a las **22:00** UTC (verificado dentro del contenedor).
`#64` fue la de UTC. **`#97` cayó a las 00:02 de Madrid —22:02 UTC—, que NO es un cruce de medianoche
UTC**, así que la explicación que se le dio entonces no cuadra con la hora. Su causa sigue sin nombre:
esta auditoría no la reprodujo.

### Dos errores de método propios, anotados porque valen más que el resultado

1. ⚠️ **Mutar el árbol mientras la medición corría.** Apliqué el primer arreglo con los ocho pases aún
   en marcha, así que los cuatro primeros miden el estado ANTES y los siguientes el de DESPUÉS. Se
   lee igual sabiéndolo, pero **la matriz autoritativa hubo que rehacerla limpia**. Una medición no se
   toca mientras mide.
2. ⚠️ **Dos veces el instrumento me dio cifras falsas** (`ugrep` tomando `->next(` como opción, y una
   cuenta que mezclaba menciones con citas). Las dos se descartaron al no poder explicar la
   diferencia. Es la misma regla de siempre: *la medida que sobra no es la que da más, es la que no
   puede explicar por qué da más*.

### Lo que NO se hizo, y por qué

⛔ **No hay guarda estática nueva.** El proxy que señalaba 54 ficheros resultó **27 veces más ruidoso
que la realidad** (2 defectos reales). Construir un gate sobre un proxy así es fabricar el mismo
«portero que llora lobo» que `#159` acababa de arreglar en `docs-check`. El instrumento correcto es el
barrido, y el barrido ya existe.
⛔ **No entra en el `pre-push`**: son diez pases completos: multiplicaría por diez el tiempo de cada
push. Se dispara desde `/cierre-sesion` cuando la tanda tocó fixtures con calendario, y eso queda
escrito allí.
⚠️ **Residuo honesto, en `DEUDA.md`**: la ficha **sigue abierta**, ahora por otro motivo — el barrido
existe pero **nadie garantiza que se ejecute**.

### La matriz final, medida con el árbol quieto

`bash scripts/audit-clock.sh`, suite completa en las diez fronteras, **después** de los dos arreglos:

    próximo sábado           2026-08-29 12:00:00   ✓ 2909 tests
    próximo domingo          2026-08-30 12:00:00   ✓ 2909 tests
    próximo lunes            2026-08-31 12:00:00   ✓ 2909 tests
    dentro de 1 mes          2026-09-26 12:00:00   ✓ 2909 tests      ← aquí moría el defecto 1
    dentro de 6 meses        2027-02-26 12:00:00   ✓ 2909 tests
    dentro de 1 año          2027-08-26 12:00:00   ✓ 2909 tests
    fin de mes               2026-08-31 23:59:30   ✓ 2909 tests      ← aquí morían los dos del defecto 2
    fin de año               2026-12-31 23:59:30   ✓ 2909 tests      ← y aquí también
    medianoche UTC           2026-08-27 23:59:30   ✓ 2909 tests
    medianoche de MADRID     2026-08-27 21:59:30   ✓ 2909 tests

⚠️ **El contador NO sube, y es lo correcto**: esta tanda no añade casos, **arregla dos que ya
existían**. Una tanda de robustez que engorda el contador suele estar añadiendo tests en vez de
arreglar los que mienten.

### El instrumento se vio FALLAR, y su fallo destapó otro defecto suyo

Retirando el congelado de `ModuleContractsTest`, `audit-clock.sh` se pone en rojo en las cuatro
fronteras futuras. ❗ **Pero la primera vez que se ejercitó esa rama, la lista de «culpables» salió
VACÍA**: la extracción solo conocía el formato `N) Clase::caso` del bloque final de PHPUnit, y con
`--filter` la salida es `FAILED  Clase > caso`. **Un gate que falla sin decir qué falló es exactamente
lo que costó no saber la causa de `#97`.** Corregido —conoce los dos formatos y, si ninguno casa, lo
dice y manda al log en vez de callar— y re-verificado: ahora nombra la clase.
⚠️ **Y el fichero mutado se restauró comprobando md5 — la primera restauración NO cuadró**: se había
comido un paréntesis. Por eso la regla es comprobar el md5 y no decir «restaurado».

## #163 · 2026-08-26 · El waiver por API (tanda 3a): el texto vigente con su id, aceptar solo lo que se sirvió, el PDF propio y «hay que firmar» en el contexto de cuenta

**Qué se hizo** (agente A, spec `specs/waiver-probatorio.md` **§9.8**, contrato en `openapi/v1.yaml`,
lo que enseñó en `specs/api-v1.md` §10.septdecies): la mitad servidor de la tanda 3 — `GET /legal/waiver`
(público: el snapshot vigente en el idioma negociado, con su `id`), `GET /me/waiver` (estado según el
modo y mis firmas con su PDF), `POST /me/waiver` (aceptar con el id servido; `409 waiver_document_stale`
si el texto cambió, `409 waiver_not_internal` fuera del modo interno), `GET /me/waiver/{signature}/pdf`
(el PDF PROPIO, auditado), la casilla del alta (`accept_waiver` + `waiver_document_id`, opt-in) y
`waiver: {mode, required, outdated, document_id}` en `me/account-context` — el sitio de la re-firma
«en el siguiente momento natural» (§4.8).

**La regla que vale todo**: «el identificador de la versión que el servidor sirvió» es, por definición,
el VIGENTE (`WaiverAcceptance::currentDocument()`), y las dos puertas la comparten sin copiarla. En el
alta se rechaza **antes de crear la cuenta** —un 422 después dejaría al cliente sin saber si la tiene—
y como aviso bajo el campo; en `/me/waiver`, como un 409 con código.

**Lo que enseñó y hubo que corregir en el mismo commit**: los avisos por campo del alta, puestos en
`account.register.*`, **sacaron de su techo a los dos presupuestos del montaje del cajón** (anónimo:
3.381 B sobre 3.200 · con sesión: 6.794 sobre 6.600) — ese grupo viaja en cada página y esos textos
solo los emite el servidor. Se movieron a `api.register.*` y solo el rótulo de la casilla se queda.
Y `waiver` entró en la semilla del montaje **a propósito**, con su clave declarada en
`SidebarMountTest` y el endpoint publicándolo igual.

**Lo medido**: +25 casos · contrato en verde (rutas ↔ `paths`, códigos ↔ `enum`, esquemas
estrictos; los dos campos nuevos del alta en `OPTIONAL_BY_DESIGN`) · cinco mutaciones, las cinco
muerden (§9.8).

**Lo que NO se decidió aquí**: el plazo y el texto definitivo (`[PENDIENTE: owner]`). 3b (el cajón)
sin empezar.

Verificación: suite verde sobre el estado FUSIONADO con `#162` (contador en `ESTADO`) · Pint ✓ ·
`docs-check` ✓ · mutaciones con su salida en §9.8.

## #164 · 2026-08-26 · La caza del fallo sin nombre de `#97`: el reloj queda EXONERADO, y el gate pasa a decir dónde está su propia salida

`#162` auditó la suite contra el reloj y **no reprodujo `#97`** —el rojo del 2026-08-16 a las 00:02
de Madrid cuya causa nunca se supo—. Esta entrada cierra la caza: qué se descartó, qué queda vivo, y
el arreglo que sí tiene sentido cuando la causa no se puede reproducir.

### Lo primero: el instrumento de `#162` NO podía cubrir la hipótesis que se le atribuía a `#97`

⚠️⚠️ **`TEST_CLOCK` CONGELA el reloj, y con el reloj congelado el tiempo no avanza nunca.** Así que
por construcción no puede reproducir el modo en que la suite **cruza la medianoche a mitad de pase**
—un test que lee `today()` dos veces y obtiene días distintos—, que es exactamente lo que la doc
llevaba desde agosto suponiendo. **Una auditoría que no puede fallar por la causa que investiga no ha
investigado nada.**

▶ De ahí sale el **segundo modo, `TEST_CLOCK_START`**: el proceso calcula UNA vez el desfase con el
reloj real y a partir de ahí el tiempo **corre desplazado**. Arrancando ~30 s antes de una medianoche,
la suite la cruza mientras corre.
⚠️ **Costó un segfault entenderlo**: la clausura no puede usar fábricas de Carbon que consulten el
«ahora» de prueba (`createFromTimestamp`) porque se llaman a sí mismas — **recursión infinita,
exit 139**. Se construye con `DateTimeImmutable` puro y se envuelve después.
⚠️ Y el desfase se fija **una vez por proceso**: recalcularlo en cada `setUp` devolvería el reloj al
instante de arranque en cada test, y el tiempo no avanzaría nunca — el modo que este modo existe para
NO tener.

### La caza, y su resultado NEGATIVO

| Hipótesis | Prueba | Resultado |
|---|---|---|
| El reloj en un **instante** frontera | 10 fronteras congeladas, suite entera (`#162`) | verde en las 10 |
| El reloj **cruzando** la medianoche a mitad de pase | 4 pases con `TEST_CLOCK_START` (Madrid ×2, UTC ×2) | **verde en los 4** |
| **Dependencia de ORDEN** entre tests | suite entera con `--order-by=random`, semillas 4242 y 1337 | **verde en las 2** |

❗ **`#97` NO se reproduce, y el reloj queda exonerado.** La explicación que la doc le daba —«el cruce
de medianoche»— **no sobrevive a la medición**, ni congelando el instante ni cruzándolo en marcha. La
aritmética ya lo sugería (00:02 de Madrid son las 22:02 UTC, que no es medianoche de nada en UTC),
pero una sospecha no es una medida.
▶ **Medir para no encontrar nada sigue siendo medir** (`#94`): lo que queda descartado son las dos
familias más probables, y eso estrecha lo que queda.
⚠️ **Y la sonda de orden se verificó ANTES de creerle el verde**: dos semillas sobre un subconjunto,
comprobando que el orden de los casos CAMBIA. `artisan test` reenvía a PHPUnit las opciones que no
conoce, y una opción que no llegara habría dejado una sonda que mide nada.

### El arreglo que sí procede cuando la causa no se puede reproducir

**La razón por la que `#97` sigue sin nombre no es que fuera indetectable: es que se perdió la
salida.** Y eso, mirado de cerca, era un defecto del propio gate:

- El `pre-push` ya escribía la suite a un fichero (`tee "$suite_log"`).
- Con `set -euo pipefail`, un rojo mata el script en esa línea, así que el `rm -f` final **no llega a
  ejecutarse** y el fichero **sobrevivía**…
- …y **nadie decía su nombre**. Lo único que veía el operador era la salida por pantalla, que es justo
  lo que se pierde al hacer scroll o cerrar la terminal.

▶ **Ahora un `trap` lo anuncia al caer**, con la ruta y la advertencia de no borrarlo — «si reintentas
y sale verde, ese fichero es la única prueba de qué falló». Y el `rm -f` solo se ejecuta por el camino
bueno: **un log que se borra en el camino de error es un log que no existe.**
▶ Verificado en aislamiento simulando la suite en rojo: imprime la ruta y sale con 1.
⚠️ **Alcance honesto**: el `trap` se arma en el paso de la suite. Un fallo anterior (docs-check, Pint,
los dos builds, los tests JS) sale antes y su salida es corta y cabe en pantalla.
⚠️ **Y un detalle que casi se cuela**: el cuerpo del `trap` va entre comillas simples, pero sus `echo`
llevan comillas dobles — unos backticks dentro se habrían ejecutado como comando al dispararse.
Retirados antes de probarlo.

### Lo que queda vivo

⚠️ **`#97` sigue sin causa.** Descartados el reloj y el orden, lo que queda son hipótesis que no se
pueden provocar a voluntad (un recurso del contenedor, una carrera rarísima). **No se persigue más
por ahora**: la próxima vez que ocurra, el gate dejará su log con nombre y la pregunta se responderá
con datos en vez de con suposiciones. Es el cambio de «no se supo» a «se sabrá».

## #165 · 2026-08-26 · La spec para desmontar `ViewOrder` — y lo que la medición cambió del diagnóstico

`DEUDA.md` tiene el god-class del panel en **Alta** con «sin plan» desde el 2026-08-14. Esta entrada
le pone plan. **Solo diseño: no se ha tocado una línea de `app/`**, y no debe tocarse hasta que otro
agente revise la spec y el owner la apruebe — es el fichero más cerca del dinero y del aforo que
tiene el producto.

### Lo primero que apareció al abrirlo: la ficha mentía en su única cifra

    DEUDA.md decía   5.029 líneas   (medido el 2026-08-14)
    medido hoy       5.280 líneas   → +251 en doce días

Y no crece solo: crece **porque cada tanda de dinero entra por ahí** (`#146`, `#149`, `#150`). La
regla de la casa aplicada a la casa: *una fila que nombra una cifra hay que abrirla, no creerla*.

### ❗ El dato que cambió el diseño: es GRANDE, no está ENMARAÑADO

Iba a diseñar una reescritura. La medición dijo otra cosa: **el fichero tiene CINCO propiedades
públicas de Livewire, y cuatro son del calendario**. La composición de la ficha ya vive fuera
(`Schemas/OrderInfolist.php`).

▶ **El estado mutable compartido es mínimo**, así que las 5.280 líneas no están entrelazadas: están
amontonadas. Eso convierte el trabajo en **una serie de extracciones acotadas** en vez de una
reescritura — un god-class con veinte propiedades compartidas habría sido otro proyecto, y con otro
riesgo.
▶ Y hay **red**: 16 ficheros de test y **~285 casos** conducen la clase. Sin eso, desmontarla sería
temerario; con eso, cada extracción se verifica por mutación.

### El reparto medido, que es lo que ordena el plan

    1.699 líneas (32 %) · 14 métodos → orquestación de DOMINIO (execute/compute/validate/save)
    1.223 líneas (23 %) · 20 métodos → acciones y composición de Filament   ← entrega legítima
      524 líneas ( 9 %) · 13 métodos → calendario (y las 4 propiedades)     ← estado de UI
      ~460 líneas ( 9 %)             → presentación y notificaciones        ← entrega legítima
      ~219 líneas ( 4 %) ·  3 métodos → lecturas de disponibilidad
      ~1.150 (22 %)                  → ayudantes, de las dos naturalezas

`executeItemEdit` sola tiene **475 líneas**. Los doce métodos mayores suman **el 39 % del fichero**.

### Y un hallazgo que no se buscaba

**`ViewOrder` no usa ni un solo `Booking\Contracts\*`** —cero referencias— y compone su propia
disponibilidad con **`Slot::query()` a mano**. Fase 3 creó `AvailabilityOffer` justo para que web y app
ofrecieran las mismas fechas; **el panel se quedó fuera**. Es la misma familia que la ficha «cuarta
copia de la aritmética de cesta», en otro fichero.

⚠️ **Matiz medido antes de acusar**: no todo está duplicado. `computeAddonPricing()` **sí** reutiliza
las primitivas del dominio (`AddonResolver`, `RateResolver`); lo que compone por su cuenta es la
orquestación. La duplicación completa es **solo** la de disponibilidad.

### El plan: cuatro extracciones por RIESGO CRECIENTE

Calendario (mínimo, y es el ensayo del método) → presentación → **disponibilidad, sustituyéndola por
el contrato que ya existe** → **la orquestación de dinero, la última**, con el método ya rodado tres
veces.

⚠️⚠️ **El paso de la disponibilidad puede destapar una diferencia de CONDUCTA** entre el panel y el
contrato: llevan meses evolucionando por separado. **Eso es un hallazgo, no un error de la mudanza**,
y la spec fija que solo tiene una salida válida —medir cuál de las dos es correcta y decidirlo— y que
la comparación se hace **ANTES** de mover nada. Si difieren, el paso se para.

### Dos descartes con medida detrás

- ⛔ **Partirlo en traits por tamaño**: mueve líneas, no responsabilidades. El dominio seguiría en la
  capa de entrega, ahora repartido en cinco sitios donde se ve peor.
- ⛔ **Mover la orquestación al modelo `Order`**: `Order.php` ya tiene **2.349 líneas**. Cambiaría un
  god-class de entrega por un god-model, que es peor porque al modelo lo usa todo el mundo.

### Lo que la spec NO decide, a propósito

El **nombre y la forma exacta del contrato** del paso 4. Sale de lo que enseñen las tres extracciones
anteriores; fijarlo hoy sería especulación — misma doctrina que `#37` con el segundo driver de
pasarela. Y si `ViewOrder` debe seguir siendo una sola clase al final: se decide **con la cifra en la
mano**.

❗ **Y una decisión que queda abierta y es de gate**: hoy `ViewOrder` **no** está en el `CRITICAL_RE`
del `pre-push`, así que tocarlo no exige `VERIFY_CONC`. Si su orquestación de dinero pasa a un
servicio de dominio, **ese servicio probablemente sí deba entrar**. Se decide en el paso 4.

Verificación: docs-check ✓ · solo doc (`app/` intacto) · spec registrada en `docs/README.md` y en la
tabla de enrutado de `CLAUDE.md`, como exige `CONVENCIONES §5`.

## #166 · 2026-08-26 · El waiver en el cajón (tanda 3b): la casilla del alta, la tarjeta de Privacidad y el aviso del índice — y un techo que cedió por una FEATURE, decidido por el owner con el número delante

**Qué se hizo** (agente A, spec `specs/waiver-probatorio.md` **§9.9**, mapa en `specs/sidebar-spa.md` §8,
guion de navegador en `VERIFICACION-E2E-CAJON.md` **§5.sexies**): la mitad visible de la tanda 3, en las
tres capas de siempre del cajón. Un módulo plano, `account/waiver.js`, decide **qué frase se pinta**
(`external` · `unsigned` · `current` · `outdated`) y **si hay algo pendiente** —una sola lectura del
estado, compartida—. Un store, `stores/waiver.js`, pide el texto vigente (`GET /legal/waiver`), el
estado propio (`GET /me/waiver`) y acepta (`POST /me/waiver`) con el `document_id` **que el servidor
sirvió**; ante `409 waiver_document_stale` **RE-LEE** texto y estado y no da la firma por hecha. Y tres
sitios pintan: la **casilla del alta** en `RegisterForm` —opt-in, con el texto completo plegado, y que
**solo existe si hay documento servido**: en modo externo o desactivado, o sin versión publicada, el
alta de hoy no cambia—; la **tarjeta de Privacidad** (estado por modo, firmar o re-firmar, las firmas
con su PDF, y refresco del contexto de cuenta al firmar); y el **aviso del índice** cuando
`accountContext.waiver.required|outdated`, que lleva a Privacidad. `register.js` manda
`accept_waiver: true` **solo** con su `waiver_document_id`.

**Las decisiones, y su porqué:**

1. **El techo del chunk sube de 221,5 a 226 KiB por una FEATURE, y lo decidió el owner.** Medido:
   **220,78 → 225,72 KiB, +4,94**, más que «Mis pedidos» (`#129`, +4,10). `ESTADO.md` decía que ese
   techo solo cede por correcciones, así que el agente no lo subió: le puso al owner el número delante
   con las tres salidas —subir; partir el waiver en un chunk aparte, con el alta costando aún ~1,5 KiB
   en éste; o aparcar la tanda— y el owner eligió subir. El párrafo del test lo deja escrito con lo
   que cuesta y de dónde NO sale (una sola lectura del estado; el texto del waiver no viaja en el chunk
   ni en el arranque, lo pide el store). **Quedan 0,28 KiB**: lo siguiente que entre lo mide, sin
   margen para un arrastre.
2. **Los textos del montaje se PODARON antes de subir, y el anónimo BAJÓ su techo.** Con lo nuevo y
   sin poda, el montaje con sesión medía 7.176 B (techo 6.600). La poda son **508 B en dos capas**:
   fuera de `lang/` tres claves que **no leía nadie** —`login.no_account`, `register.has_account`,
   `profile.email_resend_throttle`—, y fuera del ARRANQUE, pero no de `lang/`, `register.must_accept`,
   `already_exists`, `exists_unverified` y `bot_check_failed`: los publica el **servidor dentro del
   422** y `register.js::registerErrors()` los pinta tal cual, así que el cajón nunca los leía de aquí
   y viajaban en **todas** las páginas públicas. `register` pasa de viajar entero a `Arr::only`, con
   su lista exacta en `SidebarMountTest`. Con sesión: **6.668 B**, techo 6.600 → 6.760 (neto +193 por
   +701 brutos). Sin sesión: medido **2.742 B** por la guarda, y el techo **baja** de 3.200 a 2.850,
   porque *un techo con margen sobrante deja de apretar*.
3. **El aviso de re-firma vive en el índice de la cuenta, NO en el paso de pagar.** La spec decía «al
   entrar o al ir a pagar»; se construyó lo primero. La puerta **deja pasar** con una versión
   anterior (§4.8): no es una condición de compra y no debe parecer una en el checkout; y un segundo
   aviso en `PurchaseSection` (432 líneas, que solo puede adelgazar) costaría más que los 0,28 KiB
   que quedan. **Queda abierto como decisión de producto**, con su precio dicho.
4. **El diff de árbol NO ve la casilla del waiver, y hay que decirlo en voz alta.** Cuelga de
   `v-if="waiverStore.document"`, y en SSR el store está vacío (`onMounted` no corre): el manifiesto
   congelado del formulario de alta **no cambia** y seguiría verde con la casilla rota o sin ella.
   La red de lo visible es el **navegador** —como para todas las zonas de la cuenta—, y por eso el
   guion §5.sexies existe y el subsistema sigue 🟦 hasta que el owner lo recorra.

**Lo medido**: `npm run test:js` **671 → 690** (+19: 6 del módulo, 9 del store, 4 de `register.js`);
suite PHP sin tests nuevos —cambian `SidebarMountTest` (la lista de `register`, los dos techos) y
`SidebarBundleBudgetTest`— y el resto de guardas del cajón (árbol tras `build:ssr`, estilo, iconos,
texto, componentes ≤ 40 líneas) en verde sin tocarlas. ⚠️ **Una trampa nueva, medida**: `git pull
--rebase --autostash` reescribe los ficheros guardados con mtime nuevo, y el bundle SSR pasa a estar
«rancio» sin que cambie una línea —`SidebarDomContractTest` cayó **19/19**—. `npm run build:ssr` y en
paz; está en `TESTING.md` §2.bis.

**Lo que queda del subsistema, y NO es de agente**: el ✅ del owner en navegador (§5.sexies) · el
texto definitivo (§8.1: ninguna versión publicada en ninguna instalación) · el periodo de retención
(`waiver.retention_months`, hoy sin valor) · y la decisión 3 de arriba. **Lo siguiente del agente A:
menores a cargo** (`specs/menores-a-cargo.md`, subsistema C).

Verificación: docs-check ✓ · Pint ✓ · `npm run test:js` 690 ✓ · guardas del cajón ✓ · suite ✓ (el
contador vive en `ESTADO.md`) · **pendiente el ✅ del owner en navegador**.

## #167 · 2026-08-26 · La revisión adversarial de la spec de `ViewOrder`: el diagnóstico sobrevive, y tres piezas del plan describían un código que no existe

`CONVENCIONES §5` exigía que un agente distinto del autor revisara
`docs/specs/desmontar-view-order.md` (`#165`) antes de escribir código. Hecha: 7 medidores
independientes por lote, cada afirmación con dos instrumentos, y una pasada de escépticos que
intentó refutar cada hallazgo antes de publicarlo — **8 hallazgos sobrevivieron, 0 cayeron**. El
detalle entero vive en la spec **§8**; aquí el porqué de lo decidido.

### El veredicto: NI rehacerla NI aprobarla tal cual

**El diagnóstico (§1) y la opción C con las cuatro extracciones por riesgo creciente SOBREVIVEN** —
las cifras estructurales son exactas y la tesis «grande, no enmarañado» aguantó dos instrumentos
(censo de asignaciones `$this->` incluido). Pero **tres hallazgos son bloqueantes** y la spec no va
al owner sin las correcciones de su §8.12:

1. **El paso 3 no es una «sustitución por el contrato»** (§8.3): `AvailabilityOffer` responde «¿qué
   se puede COMPRAR?» y el panel «¿a dónde se puede MOVER este ítem ya comprado?». La divergencia
   está garantizada estáticamente (producto retirado, slot actual, `seats`, sin antelación ni suelo
   intradía, `excludeItemId` que el contrato no expone, ancla UTC vs parque) y varias diferencias
   son decisiones documentadas de la clienta del ORIGEN en los docblocks: «medir cuál es correcta»
   no aplica — ambas son correctas para su pregunta. Se re-diseña como EXTENSIÓN, riesgo Alto, y
   dispara `VERIFY_CONC` ya en ese paso (toca `SlotOffer`/`SlotAvailability`/`PackAvailability`,
   que SÍ están en el `CRITICAL_RE`).
2. **§4.3 invierte el mapa transaccional real** (§8.4): describe DENTRO de la transacción
   exactamente las piezas que corren FUERA (guardas pre-txn; secuencia financiera post-commit con
   transacciones propias en `Order`). Quien «preserve» esa envolvente al extraer **la crearía**:
   savepoints financieros, lock sostenido durante audit/email/REST y rastro en alcance de rollback —
   `PAY-05` y `AFORO-01` a la vez, con la suite en verde.
3. **El plan de verificación del paso 4 no ejecuta el código mudado** (§8.5): los dos verificadores
   de concurrencia conducen `OrderCreator` y `RedsysReturnHandler`, no `ViewOrder`. El lock mudado
   pertenece a `AFORO-05` —cuyo alcance no tiene assert, y la spec ni la nombra—.

### Lo que además quedó medido (y corregido donde ya estaba escrito)

- **Las «25 llamadas a dinero/aforo» eran 8 invocaciones reales**: el grep contaba comentarios y
  definiciones, `executePartialRefund` a secas tiene CERO llamadas (el método real es
  `executePartialRefundBatch`) y el lock se llama ×2, no ×3. Corregido con marcador en la spec y en
  `DEUDA.md`; `#165` no repetía la cifra. Es la lección de `#143` otra vez, en un fichero PHP:
  **el instrumento que no distingue comentario de código da un inventario que parece medido y no lo es.**
- **Las extracciones 1 y 3 están acopladas** y la 1 no es autocontenida (`executeManageItemSave`
  lee las props del calendario; la disponibilidad VIVA está dentro del calendario) — y de los 3
  métodos de la categoría «disponibilidad», **DOS son código muerto** (cero llamadores de
  producción; solo los mantienen vivos tests por reflexión). Se retiran por `§3.quater`, no se mudan.
- **Solo la rama Concern cumple «cero cambios de conducta»**: 75 interacciones de test + el runtime
  dependen de que el calendario siga en el mismo componente Livewire. Y **36 tests usan
  `ReflectionMethod` contra la clase**: cualquier extracción los rompe por construcción, así que el
  criterio «un test reescrito = alarma» necesita la cláusula de reflexión.
- **Hay una SEGUNDA duplicación de dominio** que §1.4 negaba: `lockZoneDaySlots` re-implementa a
  mano la receta anti-sobreventa de `AFORO-01` («MISMO alcance que `OrderCreator::lockSlots`», dice
  su docblock), con el porqué documentado solo en el dominio.
- **La tabla de invariantes estaba incompleta**: cuatro con cita literal a `ViewOrder` fuera
  (`AFORO-05`, `AFORO-06`, `RGPD-02`, `SEC-04`) y cuatro de roce (`AFORO-02`, `AFORO-09`, `PAY-13`,
  `PAY-16`).

### Las reglas de método que esta revisión deja

- **Contar apariciones no es contar llamadas**: toda cifra de acoplamiento se mide con un grep de
  INVOCACIÓN (`->nombre(`) y leyendo el contexto de cada hit, no con la subcadena.
- **Un plan de verificación se audita mirando QUÉ ejecuta el instrumento**, no si el instrumento
  existe: dos verificadores en verde no median nada del código que se iba a mudar.
- **Los números de decisión de los docblocks heredados son del ORIGEN**: `#173`/`#164`/`#167` del
  código no son entradas de nuestro registro. Citarlos exige decir de qué numeración son.

### Lo que queda

La spec queda 🟦 con la revisión HECHA: le falta **incorporar las correcciones de §8.12** (trabajo
de agente, una tanda de doc) y **después** el ✅ del owner — que ahora decide con un plan que
describe el código que existe. `ViewOrder` sigue sin tocarse.
▶ **Hecho el mismo día: la incorporación es `#168`.**

Verificación: docs-check ✓ · solo doc (`app/` intacto, `git status` limpio de código) · revisión
por 7 medidores + refutación cruzada, cifras clave re-verificadas a mano en sesión.

## #168 · 2026-08-26 · Las correcciones de la revisión, incorporadas: la spec de `ViewOrder` ya describe el código que existe

Ejecuta el checklist §8.12 de `docs/specs/desmontar-view-order.md` (`#167`), el mismo día y solo
doc. El cuerpo de la spec queda corregido y **§8 se conserva entero como registro** — la evidencia
de por qué cada cosa estaba mal es lo que impide re-cometerla.

Lo que cambió DE VERDAD el plan (el resto son cifras y matices):

1. **Nace un paso 0**: retirar el código muerto (`availableDatesForItem`, `availableTimesForItem`,
   `calendarMatrixForItem`) por `CONVENCIONES §3.quater` ANTES de mudar nada.
2. **El paso 3 deja de ser «sustitución por el contrato»** y pasa a ser una consulta de
   RE-PROGRAMACIÓN nueva en el dominio (extensión de la familia `SlotOffer`/primitivas con
   `excludeItemId`), riesgo Alto, con `VERIFY_CONC=1` ya en ese paso, y el diff previo POR EJE
   separando decisión documentada del origen de deriva accidental — las derivas (ancla temporal
   `AFORO-09`, exención `PAY-13` del panel) las zanja el owner cuando el diff las destape.
3. **§4.3 describe el mapa transaccional REAL** (guardas pre-txn · txn SOLO de aforo · secuencia
   financiera post-commit con transacciones propias en `Order` · email) y declara el patrón híbrido
   del servicio extraído: dueño de la txn de aforo Y orquestador de la secuencia financiera.
4. **El paso 4 gana instrumento**: extender `purchase:verify-oversell` (o hermano) con el escenario
   de edición de panel concurrente (`AFORO-05`), visto fallar antes de creerle el verde.
5. **La rama Concern queda FIJADA** para el calendario («componente» descartado) y el criterio de
   éxito lleva la cláusula de reflexión: lo intocable es lo ASEVERADO, no el cableado del arnés.
6. **La tabla de invariantes de §5 queda completa** (once filas, con `AFORO-05`/`AFORO-06`/
   `RGPD-02`/`SEC-04` y las cuatro de roce).

❗ **Pendiente SOLO el ✅ del owner** (y su «¿ahora o espera?»). `ViewOrder` sigue sin tocarse:
`app/` intacto también en esta tanda.

Verificación: docs-check ✓ · solo doc.

## #169 · 2026-08-26 · El waiver, cerrado hasta donde llega un agente: el guion recorrido en headless, el subsistema entero revisado de forma adversarial — y el alta suelta ROTA por el anti-bot, que no era del waiver y ninguna lente podía ver

El owner pidió cerrar el waiver **sin escribir código de producto** desde este carril, y dejó el
god-class del panel en el otro. Lo que era de agente eran dos cosas: recorrer el guion de navegador
(`VERIFICACION-E2E-CAJON.md` §5.nonies) y revisar el subsistema completo como exige `CONVENCIONES §5`
(quien no lo escribió). Las dos están hechas; el detalle vive en la spec **§9.10** y **§10**. Aquí,
el porqué de lo decidido y lo que la sesión enseñó.

### Cómo se hizo, y por qué así

- **El guion, con navegador headless dentro del contenedor** (receta §5.bis: Playwright + Chromium en
  `/root/e2e`, fuera del repo; el Chromium del host no arranca —cinco librerías ausentes—). El
  script conduce **la UI real**: el cajón, Mailpit por API, los PDF **leídos** con `pdf-parse`, y el
  panel de verdad —editar el texto, **la acción «Publicar versión firmable» con su modal**, Ajustes
  → Avanzado → Puerta → «Gestión del waiver», y la pantalla de puerta—. Un headless **mide, no
  valida** (`CONVENCIONES §3.bis`): el ojo del owner sigue pendiente, pero ya no va a descubrir nada.
- **La revisión, con 6 lentes independientes + escépticos** (55 agentes, solo lectura: nada de
  mutaciones mientras una prueba usaba el árbol y la BD). 69 hallazgos brutos → 37 confirmados, 3
  refutados, 29 bajas sin verificar por tope. Los de más peso los re-verifiqué a mano antes de
  escribir una línea (fichero:línea en la spec).
- **El reparto se reescribió y se empujó ANTES de empezar** (`4e1b723`), y chocó a la primera: el
  portátil había empujado `#167` con una fila que decía como «siguiente» *«…y/o la revisión del
  waiver»* — exactamente el trabajo que este carril arrancaba. Se fusionó a mano conservando sus
  hechos y dejando inequívoco de quién es cada cosa. `CONVENCIONES §10·7`, otra vez.

### El guion: 94 de 99, y los dos ✗ que importan son UN defecto

Todo lo que el guion pide del waiver **pasa** (§9.10): la casilla opt-in con el texto plegado, el
alta que manda `accept_waiver` **solo** con el id servido, el correo y su enlace que abre sesión, el
aviso del índice, los cuatro estados de la tarjeta, «Firmando…» → «Firma registrada ✓» y el aviso
apagado sin recargar, v2 y v3 publicadas desde el panel, la re-firma con **el PDF de la v1 intacto**,
el `409 waiver_document_stale` con relectura y segunda firma, los tres idiomas con el PDF en el
idioma **en que se firmó**, la puerta («versión anterior… puede pasar») y el modo externo por Ajustes.
En BD: 7 firmas `web`, cadena por titular OK, `waiver:verify-chain --workers=8` lineal sobre MySQL.
**Los dos ✗ reales**: tras el 409 **la casilla sigue marcada y el botón habilitado** — es `CAJ-3` de
la revisión (`PrivacyZone.vue:73-80`), visto antes leyendo y después en pantalla. Los otros tres ✗
eran del script o aserciones que el guion no pide (spec §9.10 los nombra uno a uno).

### ❗❗ Lo más grave del día no era del waiver: con Turnstile activo, NADIE puede crear cuenta desde `/registro`

La primera pasada murió en el alta con «no eres un robot», dos de dos. No era el andamio: en
`/registro` el cajón nace abierto y `RegisterForm` se monta a los **756 ms**; `GET /config` —que
trae la `turnstile_site_key`— responde a los **794 ms**. El widget se monta **solo en `onMounted`**
(`RegisterForm.vue:97-104`), con la clave vacía → apaño inerte. Cuando la clave llega, el `v-if` pinta
el contenedor (en el DOM, **vacío**) y nadie monta nada: sin script de Cloudflare, sin iframe, sin
token. El paso 5 del embudo no lo sufre (monta el formulario mucho después). ⚠️ **Por qué nadie lo
vio**: en local las claves se pusieron el 26/08 a las 14:48; el guion V17 del 23/08 corrió sin ellas;
y el diff de árbol no monta el widget. **Staging tiene claves.** Es `DEUDA.md` Alta, del carril A, y
el arreglo es acotado (montar cuando la clave existe, con su test de `node --test`). Para la prueba
se apagó el anti-bot y **se restauró** al terminar.

▶ **Regla que deja**: **un guion de navegador se recorre con la instalación configurada como
producción** (anti-bot incluido). Un entorno «cómodo» esconde exactamente los fallos de secuencia.

### La revisión: el diseño aguanta, la FRONTERA no

**68 afirmaciones aguantaron** (§10.0) — inmutabilidad, cadena por titular, snapshot, retención,
permisos, contrato, i18n, presupuestos. Lo que no aguantó:

1. **ALTA (§10.1)** — el alta manual del panel registra una firma «declarada por el operador» **que
   el operador no declara**: el formulario tiene una sola casilla (privacidad) y `CustomerRegistrar`
   firma igual. El PDF le atribuye un acto que no hizo. **Decisión de producto** (spec §7·4).
2. **Lo que el cliente decide sobre su propia prueba (§10.2)** — el `channel` sale de si hay cabecera
   `Bearer` (con cookie válida, Sanctum ni la mira); la IP es el primer `X-Forwarded-For` mientras
   `trustProxies('*')`; se firma **con el correo sin verificar** (§4.7 decía lo contrario: corregido
   en el cuerpo); y `POST /me/waiver` **no es idempotente** (dos envíos → dos firmas, dos consents).
3. **El cajón (§10.3)** — el id que se firma sale de `status` y el texto pintado de un `legal`
   cacheado (pueden divergir en la ruta del embudo); el 422 `waiver_stale` del alta no relee; y el
   409 deja la casilla marcada. Más un 401 mudo y, **fuera del waiver**, el contador de reservas del
   índice que **no se pinta nunca** (`upcoming` sin declarar).
4. **La guarda de borrador caza `[pendiente` y nada más (§10.4)**: los marcadores `en`/`fr` del seeder
   pasan. `#160` la vendía como guarda de BORRADOR y es de MARCADOR.
5. **Dominio y panel (§10.5)** — el badge del pedido lee el sello y la puerta el registro;
   `declared_by_user_id` entra en el hash con FK `nullOnDelete`; el PDF lee `business.name` y el
   operador en vivo; la poda con firmas de menor intercaladas rompe la cadena (**condiciona la spec
   C**); el lock solo es «primera sentencia» cuando `sign()` abre la transacción; y **ningún gate
   vigila ese lock** (SQLite no emite `FOR UPDATE`, `WaiverSigner` no está en el `CRITICAL_RE`).
6. **Los `throttle:N,1` sin nombre comparten cubo por usuario — y el reintento del pago está dentro.**
7. **El PDF afirma de más (§10.6)**: «Verificada» con un `sha256` sin secreto ni anclaje.

**Tres refutados**, y el porqué importa: «la maquinaria rechaza el borrador — resuelto» (los hechos
son ciertos, la v1 local es una preparación del guion, no un incidente) y «conservación sin plazo =
hueco» (×2: es **el `[PENDIENTE: owner]` declarado**, no un defecto; lo que sí queda para el owner es
que el plazo cuenta **desde la firma** y afecta a cuentas vivas).

### Lo que se corrigió en la doc, hoy

La cabecera y la intro de §9 de la spec decían «3b pendiente» y «ninguna versión publicada» un día
después de `#166` (DOC-3); §4.7 apoyaba la identidad en un correo verificado que no lo está; el bloque
del guion se llamaba «5.sexies» como el del bloque de cuenta (DOC-4 → **§5.nonies**, citas vivas
corregidas); §6 no tenía estado por ítem (§10.10 lo tiene). Y `docs-check` **no ve marcadores de
conflicto**: se midió con un `ESTADO.md` en `UU` y salió verde (DOC-5, ficha).

### Las reglas de método que esta sesión deja

- **Lo que ninguna lente puede ver es lo que hay que ejecutar**: seis lentes y 55 agentes leyendo no
  vieron el alta rota; un navegador la vio en el primer minuto. Leer y ejecutar son dos instrumentos.
- **Un headless que falla no es «el andamio»** hasta que se demuestra: la primera pasada parecía un
  fallo de Turnstile en headless y era el producto. Se sondeó el DOM (contenedor vacío, sin script)
  antes de tocar el script.
- **Los refutados también enseñan**: dos agentes vieron un hueco donde había una decisión pendiente
  declarada. La severidad la da el DISEÑO que falta, no el dato que se conserva.

### Lo que queda, y de quién es

**Del owner** (spec §7, seis): el texto definitivo · el plazo (desde la firma, mín. 1 · máx. 600 meses)
· el aviso en el paso de pagar (0,28 KiB de margen) · la casilla del alta manual · firmar con correo
sin verificar, sí o no · su ojo en navegador (con el anti-bot apagado o arreglado). **Del carril A**,
código acotado (spec §10.11, fichas en `DEUDA.md`): **el widget del alta suelta primero**. Después,
«menores a cargo», que hereda NUC-3.

### Las seis que el owner decidió al cierre (2026-08-26, con el número y el coste delante)

1. **El anti-bot del alta suelta lo arregla el carril A, lo primero de su próxima sesión** (frente a
   cruzar carriles ahora o aparcar con staging sin altas sueltas).
2. **Casilla del waiver en el alta MANUAL del panel**, con el texto a la vista; sin marcarla, no hay
   firma (frente a «el mostrador no firma» o dejarlo como está).
3. **Se exige correo VERIFICADO para firmar**: el alta con casilla firma al verificar, no al crear, y
   `POST /me/waiver` exige cuenta verificada (frente a guardar el estado en la fila, o dejarlo).
   ⚠️ Cambia §9.8 y el test del alta con casilla.
4. **El aviso de re-firma en el paso de pagar NO se construye**: basta el del índice (cerrado; el
   chunk se queda en 225,72 de 226).
5. **La casilla del waiver del alta pasa a ser OBLIGATORIA en modo interno** (era opt-in por §4.4:
   cuenta ≠ firma, la firma se exige en la puerta). El owner preguntó por qué no era obligatoria; se
   le puso el porqué y el coste —quien crea cuenta para comprar sin saltar también acepta— y eligió
   obligatoria. Sigue desmarcada por defecto; la firma se registra al verificar el correo (3).
6. **Su pasada del guion va DESPUÉS del arreglo del anti-bot, en local y con Turnstile encendido.**
   Preguntó si subirlo a staging: **no sirve**, medido — staging sirve `7776370`, 21 commits por
   detrás y **sin ninguna tanda del waiver**; tiene claves de Turnstile (el alta moriría igual); y
   publicar allí una versión es irreversible sobre un texto borrador.

Quedan del owner las dos jurídicas —**el texto definitivo y el plazo**—.

Verificación: docs-check ✓ · **solo doc** (`app/`, `resources/`, `tests/` intactos) · suite sin
cambios (2935) · el andamio y su evidencia (capturas, textos de PDF, `resultado.json`) en el
scratchpad de la sesión y en `/root/e2e/out` del contenedor · BD local: v1→v3, 7 firmas, anti-bot
restaurado.

## #170 · 2026-08-26 · El ✅ del owner al desmontaje, y el paso 0 EJECUTADO: −268 líneas de política muerta — que resultó ser la política VIEJA

✅ **El owner aprobó la spec y autorizó la ejecución** («procede con ello. Sé riguroso y empírico…
es una tarea crítica»). Con eso arranca el desmontaje de `ViewOrder`; el paso a paso vive en la spec
**§9** y aquí solo el porqué de lo decidido en el paso 0.

**Lo ejecutado**: los tres métodos muertos censados por la revisión (`#167`) **más los dos helpers
que quedaban huérfanos** al retirarlos (`buildCurrentSlotOnlyTimeOption`, `formatTimeOption` — solo
los llamaban los muertos, medido por grep de invocación). `ViewOrder` pasa de **5.280 a 5.012
líneas**. Los 3 tests que mantenían vivo aquello por reflexión se **re-apuntaron a la fuente viva**
(`§3.quater`, categoría 3) y los tres mejoran la red — el detalle y la tabla, en la spec §9.1.

**Las tres cosas que este paso enseñó:**

1. **Un test verde puede estarlo por el motivo equivocado**: el del horizonte creaba su slot lejano
   sin `online_sales_open`, así que lo excluía `sellableOnline()` y el horizonte nunca se probó.
   El re-apuntado crea el slot plenamente vendible — y su mutación (quitar el recorte de horizonte)
   se vio MORDER, cosa que con el fixture viejo no habría pasado.
2. **La 5ª trampa de la mutación, pagada aquí**: restaurar con `git checkout` durante una mutación
   devuelve el fichero al HEAD **commiteado** — y el borrado entero era árbol sucio: se perdió y
   hubo que re-aplicarlo (verificado byte-idéntico por md5). **Regla: commitear la extracción en
   local ANTES de mutar.** Va también en la spec §9.1.
3. **El código muerto no era ruido, era la política VIEJA**: el `availableTimesForItem` retirado
   **inflaba** las plazas del slot actual (`available += seats`), la conducta que la decisión
   «fidedigno, plazas reales» de la clienta del origen sustituyó. Dos implementaciones de la misma
   pregunta = una siempre está desactualizada, que es el argumento entero del paso 3.

Verificación: suite **2935 / 16.959 en verde** (3 tests sustituidos 1:1, +3 aserciones) · mutación
**4/4 muerden** (anclas únicas verificadas, rojo comprobado, restauración por md5) · Pint ✓ ·
`php -l` ✓ · docs-check ✓ · referencias colgantes re-apuntadas (`PaymentSettings`,
`validateNewSlot`) · la clave `current_marker` conservada (la usa el blade vivo).

## #171 · 2026-08-26 · El alta suelta vuelve a funcionar con el anti-bot encendido: `mountTurnstile` espera a que EXISTAN la clave y el nodo — y no carga Cloudflare antes

**Qué se hizo** (carril A, tanda 4 del waiver, unidad 1 — la ficha Alta que dejó `#169`): en
`/registro` el cajón nace abierto y `RegisterForm` se montaba a los ~756 ms, **antes** de que
`GET /config` (~794 ms) trajera la `turnstile_site_key`. `mountTurnstile(el, {sitekey})` recibía
`null` y `''`, devolvía su apaño inerte y nadie volvía a llamar; al llegar la clave, el `v-if` pintaba
el contenedor —vacío para siempre— y el servidor respondía «no eres un robot» a **todo** alta suelta
en cualquier instalación con claves (staging las tiene). El paso 5 del embudo no lo sufría porque monta
el formulario mucho después.

**La decisión de diseño, y su porqué**: la decisión vive en el MÓDULO, no en el componente. Los tests
JS son `node --test` sobre módulos planos —ningún `.vue` se testea— y `RegisterForm.vue` está en **37
de 40** líneas de código. Así que `mountTurnstile` acepta ahora **funciones** para el nodo y la clave
(los valores siguen valiendo, con la conducta de siempre), las re-lee en cada sondeo y añade una
**fase de espera** (150 ms × 400 ≈ 60 s) antes de la de carga: **el script de Cloudflare no se inyecta
hasta tener clave y nodo delante** —con el anti-bot apagado la clave no llega nunca, y no se carga un
tercero por si acaso—; si no llegan, se rinde **en silencio** (no hay nada que avisar); y la espera
**no consume** los intentos de carga, que siguen avisando al agotarse. El componente cambia dos
líneas: pasa `() => captchaEl.value` y `() => props.turnstileSiteKey`. Sigue siendo un pintor.

**Lo medido**: `turnstile.test.js` **13 → 18** (espera sin script y monta al llegar ambos · clave que
no llega nunca → silencio · la espera no consume intentos · destroy durante la espera · funciones que
ya resuelven = valores) · `npm run test:js` **690 → 695** · **4 mutaciones, las 4 muerden** (sin fase
de espera → 2 rojos · inyectar durante la espera → 2 · la espera consume intentos → 2 · `render` con
el nodo de la llamada en vez del actual → 1), fichero restaurado por `cmp` · chunk del cajón
**225,72 → 225,85 KiB** (+0,13, corrección: el techo de 226 no se toca, quedan 0,15) · las cuatro
guardas del cajón 61/61 tras `build` + `build:ssr` · y **la verificación que vale: el alta suelta en
headless con Turnstile ENCENDIDO** — formulario a 1.083 ms, `/config` a 1.178, **script inyectado a
1.323 (después de la clave)**, token a 3.089, `POST /auth/register → 201`, «revisa tu correo», 1
correo en Mailpit. Ayer, en las mismas condiciones, 422 dos de dos.

**Regla de método que deja, pagada con un push rojo**: **no se edita NADA mientras corre el gate del
`pre-push`**. El commit de reclamación (solo doc) se empujó mientras se editaba `RegisterForm.vue`, y
`SidebarDomContractTest` cayó por bundle rancio (`#69`): el gate hace `build:ssr` y luego la suite, y
un fichero tocado entre las dos es un artefacto viejo. Se reconstruyó y se re-verificó todo.

Verificación: Pint ✓ · `node --test` 695/695 ✓ · guardas del cajón ✓ · headless con anti-bot ✓ ·
docs-check ✓ · suite (el contador vive en `ESTADO.md`, sin cambios en PHP).

## #172 · 2026-08-26 · Extracción 1: el calendario de `ViewOrder` a su Concern — y la fidelidad de una mudanza se puede MEDIR

Segunda tanda de la ejecución (`#170`). `ViewOrder` baja de 5.012 a **4.505 líneas** (−507): las 4
propiedades Livewire del calendario y sus 12 métodos viven ahora en
`Pages/Concerns/ManagesItemCalendar` (544 líneas). El detalle, en la spec **§9.2**.

**Por qué trait y no componente** (fijado por la revisión, spec §8.8): un componente Livewire hijo
cambiaría el dueño de las properties y de los wire methods — 75 interacciones de test y el camino
de runtime de `executeManageItemSave` rotos. Con el trait, la clase aplanada es idéntica y **nada
lo nota**: cero asserts tocados, la suite pasó a la primera.

**Las tres verificaciones que valen la pena copiar:**

1. **La pieza sin red ganó su test ANTES del movimiento** (`calendarGoToItemMonth`, cero tests):
   mover código sin red es mover a ciegas; el test se escribió, se vio morder su mutación, y
   DESPUÉS se movió.
2. **La fidelidad de la mudanza se midió, no se supuso**: diferencia de conjuntos entre las líneas
   borradas del origen y el contenido del trait — cero ausencias, y el destino solo añade su
   cabecera. Un `comm -23` de dos minutos caza el renglón que un copia-pega de 500 líneas pierde
   en silencio.
3. **La mutación de la extracción es retirar el `use`**: 14 tests del calendario caen de golpe —
   la red cubre la pieza entera, no un método suelto.

▶ El trait declara en su docblock su deuda con la extracción 3: `selectableDatesInRange` y
`displayAvailableFor` (la disponibilidad) se quedan en `ViewOrder` y el trait los llama por
`$this->` — ese es el punto de costura que la consulta de re-programación del dominio sustituirá.

Verificación: suite **2936 / 16.961 en verde** (+1 test, +2 aserciones) · fidelidad por diferencia
de conjuntos = 0 ausencias · mutación de pieza y de extracción vistas morder · Pint ✓ · `php -l` ✓ ·
docs-check ✓.

## #173 · 2026-08-26 · Extracción 2: la presentación de `ViewOrder` a su Concern — y la palabra de la spec cedió ante la medida

Tercera tanda de la ejecución (`#170`, `#172`). `ViewOrder` baja de 4.505 a **4.014 líneas** (−491):
los 14 métodos de presentación (6 `*Notification` · 3 `render*` · 2 `*Preview` · 3 `*Options`)
viven en `Pages/Concerns/PresentsOrderActions` (535 líneas). Detalle en la spec **§9.3**.

**La decisión que esta tanda deja escrita**: la spec decía «un `*Presenter`» y §4.1 afirmaba «no
toca dominio; solo compone textos». **La medición de dependencias dijo otra cosa**: los dos
`*Preview` componen SOBRE los cómputos del dominio que la extracción 4 va a mover
(`computeEditPricing`, `computeAddonPricing`, `applyGroupChoices`), y `buildRefundItemsOptions`
cuelga de `record`/`resolveItem`. Una clase Presenter habría fijado HOY firmas que la 4 rompería —
la especulación del enchufe que `#37` enseñó a no cometer. **Trait de entrega** (mismo patrón que
la extracción 1), con las costuras declaradas en su docblock; el Presenter-clase, si procede, se
decide en el paso 4 con las firmas reales delante. La desviación queda declarada en la spec §9.3,
no escondida.

▶ De regalo, la mecánica ya rodada en `#172` salió igual de limpia: fidelidad por diferencia de
conjuntos = 0 ausencias · la reflexión de `sameScopeProductOptions` ni se enteró (los traits se
aplanan) · retirar el `use` deja 19 de 61 tests de `OrderAdminActionsTest` en rojo · un import
huérfano cazado (`OrderItemRefunded`).

Verificación: suite **2936 / 16.961 en verde** (pura mudanza, cero asserts tocados) · Pint ✓ ·
`php -l` ✓ · docs-check ✓.

## #174 · 2026-08-26 · Tanda 4 · unidad 2 del waiver: lo que el cliente ya NO decide sobre su prueba, la aceptación idempotente dentro del lock, y `WaiverSigner` bajo el gate

**Qué se hizo** (carril A; las seis piezas de servidor que la revisión `#169` §10.11 exigía antes del
✅, cada una con su test y su mutación — **7 de 7 muerden**; spec **§9.11**):

1. **El canal sale del token REAL, no de una cabecera.** `MeWaiverController` decidía `api` con
   `bearerToken() !== null`, y con cookie de sesión el guard de Sanctum ni mira esa cabecera: `Bearer
   basura` bastaba para que la firma constara como «app nativa». Hoy: `currentAccessToken()
   instanceof PersonalAccessToken` → `api`; la sesión deja un `TransientToken` → `web`. En el alta
   (anónima) decide `hasSession()`: el cajón llega por el grupo stateful con sesión; la app nativa,
   sin ella. ⚠️ **`Sanctum::actingAs()` también deja un `TransientToken`**: el test del canal `api`
   emite ahora un token de verdad (`createToken()->plainTextToken` + `withToken()`), y hay un caso
   para el ataque exacto (sesión + `Bearer basura` → `web`).
2. **Aceptar dos veces la misma versión ya no duplica la prueba.** `WaiverSigner::sign()` es
   **idempotente por versión y sujeto**: si el mismo sujeto ya firmó esa versión —en cualquier idioma,
   el texto publicado es el mismo— devuelve la fila que hay, sin fila, consentimiento ni auditoría
   nuevos. **Va DENTRO del lock a propósito**: dos envíos simultáneos del mismo `document_id` pasan
   los dos la comprobación de vigencia, y solo el lock del titular los pone en fila.
   ▶ **Lo que costó, y era de esperar**: tres tests y `waiver:verify-chain` construían la cadena
   **re-firmando la misma versión**, y cayeron (el verificador esperaba 9 filas y encontró 1 — la
   correcta). La cadena crece con VERSIONES nuevas (tests corregidos así), y el verificador firma
   ahora **un menor a cargo por proceso** (`subject_id` = i): misma propiedad —el lock serializa la
   cabeza— y **visto FALLAR sin el lock** otra vez: 16 procesos, 17 filas, **15 `prev_hash`
   repetidos**, cadena ROTA. Con el lock: lineal con 8 y con 16.
3. **La guarda de borrador caza los TRES marcadores** del seeder (`[PENDIENTE…]`, `[PENDING…]`,
   `[À COMPLÉTER…]`) más el `[pendiente]` neutro; y las PALABRAS («borrador», «draft», «brouillon»)
   **avisan sin bloquear**: `mentionsDraftWords()` alimenta el modal de «Publicar versión firmable».
   Un texto definitivo puede mencionarlas; quien publica tiene que verlo.
4. **El badge del pedido va por `WaiverStatus`**, como la puerta: en interno un sello sin registro es
   «No firmado», una firma de versión anterior lo dice, y en `desactivado` no se enseña. §8.7 exigía
   migrar «las dos» superficies y una se había quedado.
5. **Throttles con PREFIJO** en las dos rutas del waiver (`throttle:10,1,waiver-sign` / `waiver-pdf`):
   el tercer parámetro del middleware es el prefijo del cubo, y sin él siete descargas del PDF
   dejaban 60 s sin poder reintentar un pago. Los demás sin prefijo (pago, guest-form, reenvío)
   quedan en `DEUDA.md` con nombre: el del pago está en `PAY-04` y se toca con su test.
6. **`WaiverSigner` entra en el `CRITICAL_RE`** y en `CriticalPathGateTest::CRITICAL_FILES` (con
   `WaiverAcceptance` de control negativo), y el aviso del hook nombra `waiver:verify-chain`. Retirar
   el lock pasaba 72/72 y pasaba el push; ahora exige `VERIFY_CONC=1` tras correr el verificador —
   **este push es el primero que lo hace**.

**Lo medido**: +12 tests (MeWaiverTest +3, AuthRegistrationTest +2, WaiverSignatureChainTest +2,
LegalDocumentVersionTest +2, OrderInfolistEnrichedTest +3) · 7 mutaciones, las 7 muerden (canal por
cabecera ×2, sin idempotencia, un solo marcador, badge por el sello, throttle sin prefijo,
`WaiverSigner` fuera del patrón), ficheros restaurados por `cmp` · `waiver:verify-chain` 8 y 16 OK
sobre MySQL, y ROTA sin el lock · contrato OpenAPI en verde (`ApiContractTest`) con la descripción
de la idempotencia · Pint ✓ · docs-check ✓.

**Lo que enseñó**: **un cambio de conducta correcto rompe los tests que se apoyaban en la conducta
vieja sin saberlo** — tres tests y un verificador usaban «firmar dos veces» como atajo para tener
dos eslabones. Ninguno estaba mal; los tres describían el mundo anterior. Se corrigen los tests, no
la conducta.

Verificación: suite (el contador vive en `ESTADO.md`) · `VERIFY_CONC=1` con `waiver:verify-chain`
(8/16, y visto fallar) · Pint ✓ · docs-check ✓ · `php -l` ✓.

## #175 · 2026-08-26 · Tanda 4 · unidad 3 del waiver: lo que se ENSEÑA es lo que se FIRMA — el cajón cierra sus tres grietas, y un test antiguo era la grieta escrita

**Qué se hizo** (carril A; CAJ-1/2/3 y CAJ-5 de la revisión `#169` §10.3; spec **§9.11**):

1. **El id que se acepta es el del texto ENSEÑADO, y solo ese** (CAJ-1). `currentDocumentId` prefería
   `status.current_document_id` —otra respuesta, pedida en otro momento— y en la ruta del embudo
   (alta en el paso 5 → sesión sin recarga → Privacidad) podía enseñar la versión vieja y firmar la
   nueva sin que el servidor viera nada raro. Hoy sale de `legal.document.id`, y si el estado propio
   dice que el vigente es OTRO, `ensureStatus()` **relee el texto antes** de que nadie lo firme; si esa
   relectura falla, **no queda ningún id** que aceptar (el del estado no vale: nadie lo ha leído).
   ⚠️ **Un test afirmaba lo contrario** —«el estado propio manda sobre el texto público para el id que
   se acepta»— y pasaba: era la grieta, escrita como test. Se reescribió, y el caso que faltaba (la
   relectura fallida) fue el que hizo morder a la primera mutación, que con los otros dos pasaba.
2. **El 422 del alta relee y desmarca** (CAJ-2). «Vuelve a leerlo y acéptalo de nuevo» solo se puede
   cumplir si se RELEE: `stores/auth.js::register()` ve el 422 sobre `waiver_document_id`, desmarca
   `accept_waiver` y pide `reloadLegal()`; el texto plegado pasa a ser el vigente y el reenvío lleva
   el id nuevo. Un 422 por otro campo no toca nada.
3. **Tras el 409 la casilla se desmarca** (CAJ-3): el store deja `reread = true` en toda relectura
   (409, 422, estado apuntando a otro id) y lo apaga al firmar bien; `PrivacyZone` desmarca solo si
   hubo relectura — un fallo de red no obliga a marcar otra vez. Es lo que el guion V34 vio fallar por
   la tarde (2 ✗), ahora ✓.
4. **`store.upcoming`** (CAJ-5): una palabra en `AccountHomeZone.vue`; el contador de reservas del
   índice compilaba a `t.upcoming>0` (`undefined`) y no se pintaba nunca. Verificado en el bundle:
   ahora compara con el store.

**El techo del chunk sube de 226 a 226,5 KiB por CORRECCIÓN** (225,85 → 226,21, +0,36), por la
regla escrita —cede por correcciones, con su medida— y con su párrafo en el ledger de
`SidebarBundleBudgetTest`. Quedan 0,29 KiB.

**Lo medido**: `npm run test:js` **695 → 700** · **5 mutaciones, las 5 muerden** (id desde el estado ·
sin relectura ante otro id · `reloadLegal` sin marcar · el 422 del alta sin releer · —y la primera
solo mordió tras añadir el caso de la relectura fallida—), ficheros restaurados por `cmp` · guardas
del cajón 69/69 tras `build` + `build:ssr` (componentes ≤ 40 líneas: `PrivacyZone` sigue en 38) ·
**headless con el anti-bot ENCENDIDO, 14 de 14**: alta con texto caducado → `422 waiver_document_id`
→ `GET /legal/waiver` → casilla desmarcada, texto nuevo en pantalla, banner → reenvío con el id nuevo
→ `201` → cuenta verificada; Privacidad con casilla marcada → se publica otra versión → «Firmar» →
`409` → **casilla desmarcada y botón deshabilitado**, texto nuevo → marcar y firmar → `201`.

**Lo que enseñó**: **un test puede ser la descripción fiel de un defecto**. «El estado manda» se
escribió como intención y era el mecanismo por el que se firmaba lo no leído. Cuando un cambio de
conducta rompe un test, antes de arreglar el test hay que preguntarse cuál de los dos tenía razón.

Verificación: `node --test` 700/700 ✓ · guardas del cajón ✓ · headless 14/14 ✓ · docs-check ✓ · Pint ✓
(el contador PHP vive en `ESTADO.md`, sin cambios en PHP salvo el ledger del techo).

## #176 · 2026-08-26 · Extracción 3: la oferta de re-programación al dominio — tres reglas DECIDIDAS antes de escribirla, y una que nadie probaba

Cuarta tanda de la ejecución (`#170`, `#172`, `#173`). Nace
**`Booking\Services\ItemRescheduleOffer`** (252 líneas): la respuesta del dominio a «¿a dónde se
puede MOVER este ítem ya comprado?» — otra pregunta que la de compra, como fijó la revisión
(`#167`, spec §8.3), y por eso servicio propio y no extensión de `AvailabilityOffer`. `ViewOrder`
baja a **3.907 líneas** y **ya no compone NINGUNA oferta** (`AFORO-02` aplicada al panel; su fila
y la de `AFORO-09` actualizadas). Detalle completo en la spec **§9.4**.

**Lo que hay que retener:**

1. **Las tres reglas del contrato se preguntaron al owner ANTES de escribir el servicio** y quedaron
   decididas: ancla en la zona del PARQUE (cambia conducta solo en la ventana 00:00–02:00, con el
   test que CRUZA la frontera UTC↔Madrid que `AFORO-09` declaraba no tener) · la exención de
   antelación/corte del panel DECLARADA en el contrato (mostrador) · horas sin aforo OCULTAS.
2. **Una regla ratificada resultó no tener red**: la mutación de «ocultar horas sin aforo» salió
   VERDE en 82 tests. Existía desde el origen y no la probaba nadie — ganó su test y la mutación se
   vio morder. Un contrato recién decidido se verifica regla a regla, no en bloque.
3. **La sonda A/B antes de conmutar**: composición vieja vs servicio nuevo sobre los 27 ítems reales
   de la BD — 0 divergencias. Es el «diff antes de mover» de §4.2/§6·3, ejecutado.
4. **El arch-test hizo su trabajo**: el servicio nuevo no pudo abrir otra flecha Booking→Payments
   (la baseline SOLO ENCOGE) — la familia de la oferta comparte fuente por
   `SlotOffer::horizonMonths()`, y tocar `SlotOffer` disparó `VERIFY_CONC`: **los 5 escenarios de
   `purchase:verify-oversell` (8 workers) y `redsys:verify-concurrency`, PASA sobre MySQL real**.
5. **La validación re-ancló con la MISMA fuente que la oferta** (`validateNewSlot` →
   `ItemRescheduleOffer::today/horizon`): oferta y backstop no pueden divergir de reloj. Los
   fallbacks de fecha de TARIFICACIÓN siguen en UTC a propósito — son dinero (`PAY-18`) y se
   examinan en la extracción 4.

Verificación: suite **2950 / 17.012 en verde** (+2 tests sobre el corte del carril A) · sonda A/B
27/27 sin divergencias · mutación 4/4 · `VERIFY_CONC` ganado con los dos verificadores en verde ·
Pint ✓ · docs-check ✓ · `INVARIANTES.md` al día.

## #177 · 2026-08-26 · El instrumento de la extracción 4: `panel-edit`, el escenario que ejecuta el lock que se va a mudar — visto FALLAR antes de creerle el verde

Quinta tanda de la ejecución (`#170` · `#172` · `#173` · `#176`), y la condición previa del paso 4
(spec §6·4): **los verificadores existentes no ejecutan el código que el paso 4 muda**, así que
antes de mover el dinero se construyó el instrumento que sí. Detalle en la spec **§9.5**.

**Qué es**: `purchase:verify-oversell --scenario=panel-edit` — N ediciones de panel CONCURRENTES
por el camino real (`ViewOrder::executeItemSlotChange` entero: permiso, capas, lock zona/día,
revalidación bajo lock), con ítems de 120 min hacia dos destinos cuyas ventanas pisan una franja
intermedia de UNA plaza. Es el hueco de `AFORO-05` y la forma exacta del bug L3 que motivó
`lockZoneDaySlots`.

**La evidencia que lo hace creíble:**
- Con el lock real: **PASA** (1 comprometido · 7 bloqueados · franja intermedia 1/1).
- **Visto FALLAR** con el lock mutado a solo-la-fila-destino: **2 comprometidos donde cabía 1**
  (2/1 en la franja intermedia). La regla de `#147`: un verificador que nunca ha fallado no ha
  demostrado que pueda.
- Su guarda propia mueve y deshace por el camino entero antes de forkar; el personal y su rol son
  DESECHABLES (el rol `staff` global no se toca) y cada ejecución deja **cero restos** (comprobado
  contando).

**Y un defecto PREEXISTENTE del comando, pagado en vivo y arreglado**: una guarda de instrumento
fallida salía por `return` antes del `finally` y **fugaba la siembra entera** a la BD de
desarrollo — se midió con una zona, un rol y nueve pedidos huérfanos tras un fallo de sonda. La
guarda vive ahora dentro del `try`, y la fuga se limpió.

▶ **Lo que queda del desmontaje es solo la 4b** (spec §9.5): mover las cuatro fases de §4.3 al
servicio de dominio, con este instrumento como red de concurrencia y el navegador del owner al
cerrar. `ViewOrder` está hoy en **3.907 líneas** (de 5.280).

Verificación: escenario nuevo PASA en limpio y FALLA con la mutación (md5 de la restauración) ·
suite **2950 / 17.013 en verde** (+1 aserción: el inventario de la guarda) · Pint ✓ · docs-check ✓
· `OversellVerifierCoversEveryQuotaTest` al día · cero restos `pe-probe` en la BD.

## #178 · 2026-08-26 · Tanda 4 · dos de las tres decisiones del owner: la casilla del waiver es OBLIGATORIA en interno, y el mostrador solo firma si el operador enseña el texto y lo declara

**Qué se hizo** (carril A; spec **§7·4** y **§7·7**, `[DECIDIDO owner, 2026-08-26]`; §9.11):

1. **La casilla del alta es obligatoria en modo interno con versión publicada** (§7·7). El owner
   preguntó por qué no lo era; §4.4 la había dejado opt-in a propósito (cuenta ≠ firma: la firma se
   exige en la puerta) y se le puso el coste delante —quien compra sin saltar también acepta—; eligió
   obligatoria. Regla `accepted` sobre `accept_waiver` **exactamente cuando `GET /legal/waiver` sirve
   un documento** (`WaiverSettings::isInternal()` y versión en el idioma de la petición); mensaje
   `api.register.waiver_required` en es/en/fr bajo `accept_waiver`, que el cajón pinta en el hueco de
   la casilla (`FIELD_ORDER` lo conoce). En externo, y en interno SIN versión, sigue opcional — no
   se puede exigir una casilla que no existe. Sigue desmarcada por defecto.
2. **El mostrador solo firma si el operador enseña el texto y lo declara** (§7·4; la ALTA de la
   revisión `#169` §10.1). El modal «Registrar al cliente» del pedido manual enseña, solo en interno
   con versión, el **texto vigente** (`Placeholder`, escapado) y la casilla «Le he enseñado al cliente
   la exención vigente y declara que la acepta». `CustomerRegistrar::register(…, waiverDeclared:)`
   firma **solo con ella**; sin marcarla no hay firma, ni consentimiento, ni auditoría —el cliente
   firmará desde su cuenta y la puerta le pedirá la tablet—. ⚠️ El flag viaja también por el camino
   del cliente **sin email** (`pendingNoEmailCustomer` → `performRegistration`): la primera versión lo
   leía de un `$data` que no existía en ese método y `?? false` lo tapaba — lo cazó el test antes
   que nadie.
   ⚠️ **El modal es un `wire:partial`** (trampa de `#161`): su presencia no se asevera con
   `assertSee`; se prueba por sus efectos (firma / no firma) y la visibilidad es la misma condición
   que decide la firma.

**Lo medido**: +6 tests PHP (`AuthRegistrationTest` +2 · `PresentialWaiverDeclarationTest` +1 ·
`RegisterCustomerActionTest` +3) y +1 JS (el aviso de `accept_waiver` en el orden del banner) ·
**4 mutaciones, las 4 muerden** (la casilla nunca obligatoria · el registrador ignora la
declaración · la página no pasa la declaración · la propiedad en vez del método), restaurado por
`cmp` · **headless con anti-bot**: 4c 5/5 (422 bajo la casilla y en el banner; 201 al marcarla) y
4b 3/3 (el modal enseña el texto vigente y la casilla; con las dos marcadas, firma `panel`
declarada por el operador en BD) ·
❗ **Y el navegador cazó un 500 que la suite NO podía ver**: el modal del alta manual reventaba al
abrirse (`$version->sections` como propiedad: Laravel lo toma por una relación) y los tests pasaban
en verde, porque el modal es un `wire:partial` y el arnés no lo renderiza. Arreglado, y con red
nueva: `counterWaiverText()` es público y `RegisterCustomerActionTest` lo ejecuta directo (mutación
vista morder). **Regla**: lo que vive en un modal de Filament se prueba llamando al código que lo
pinta, no al componente · las guardas del cajón tras
`build` + `build:ssr` (el hueco del aviso no cambia el árbol SSR) · contrato OpenAPI actualizado
(`accept_waiver`: obligatoria cuando hay documento) en verde · Pint · docs-check.

**Queda de la unidad 4**: **4a · correo verificado para firmar** (§7·5): toca `WaiverSigner` (en el
`CRITICAL_RE` desde `#174`) y va en su propio push con `VERIFY_CONC=1`.

Verificación: suite (contador en `ESTADO.md`) · `node --test` · headless 4b/4c (spec §9.11) ·
Pint ✓ · docs-check ✓.

## #179 · 2026-08-26 · Tanda 4 · el waiver se firma con el correo VERIFICADO: el alta deja la aceptación pendiente y la firma nace al verificar — la guarda vive en el dominio

**Qué se hizo** (carril A; spec **§7·5**, `[DECIDIDO owner, 2026-08-26]`; §9.11): la revisión `#169`
§10.2·3 midió que la firma del alta nacía con `email_verified_at = null` —§4.7 decía «cuenta con
correo verificado» y era falso— y que cualquiera podía aceptar «en nombre» del correo de un tercero.
El owner eligió exigir el correo verificado (frente a guardar el estado en la fila, o dejarlo).

1. **La guarda vive en `WaiverSigner`, sobre la fila BLOQUEADA**: `email_verified_at === null` →
   `WaiverEmailUnverifiedException`, **salvo firma declarada** (mostrador, §8.4): ahí la identidad la
   asegura el operador y el cliente de agenda puede no tener correo. Un único escritor, una única
   regla — ninguna superficie puede olvidarla.
2. **El alta ya no firma**: `SelfSignup::createAccount()` guarda la aceptación PENDIENTE —qué texto y
   por qué canal— en dos columnas nuevas de `users` (`waiver_pending_document_id`, FK RESTRICT a la
   versión inmutable, y `waiver_pending_channel`). Responde 201 igual que antes.
3. **La firma nace al verificar**: `SignPendingWaiverOnVerification` (evento `Verified`; vive en
   `Identity\Listeners` y lo registra `AppServiceProvider` — el arch-test no deja dominio fuera de `app/Domain`) limpia la pendiente ANTES de nada —una segunda verificación no firma dos
   veces— y firma con el canal del alta y la IP/UA del clic **si el texto sigue vigente**
   (`WaiverAcceptance::currentDocument`); si se publicó otra versión entre medias, la descarta sin
   firmar: nada se firma sin releer, y el índice de la cuenta pedirá el nuevo. Un fallo aquí se
   registra y no impide la verificación. ⚠️ Consecuencia dicha: `accepted_at` es el momento de la
   verificación, no el del alta — es cuando la persona demostró ser dueña del buzón que la firma copia.
4. **La API dice por qué**: `POST /me/waiver` sin correo verificado → `409 waiver_email_unverified`
   (código nuevo en el enum del contrato, mensaje en es/en/fr).
5. **La pendiente es PII del alta**: `anonymize()` la nulifica y el censo de
   `AnonymizeCoversEveryUserColumnTest` la declara (su fixture la siembra con una versión real: el
   censo cazó la columna sin sembrar a la primera, que es exactamente para lo que existe).
   `MODELO-DATOS` e `INVARIANTES` (RGPD-01) al día. `waiver:verify-chain` siembra el titular verificado.

**Lo medido**: +4 tests (`AuthRegistrationTest`: aplazada hasta verificar · pendiente caducada
descartada · los dos del canal verifican antes; `MeWaiverTest`: 409; `WaiverSignatureChainTest`:
sin verificar no firma, declarada sí) · **5 mutaciones, las 5 muerden** (sin guarda · el alta no
guarda la pendiente · el listener firma sin comprobar vigencia · no limpia la pendiente · `anonymize()`
no la purga), restaurado por `cmp` · `waiver:verify-chain` **8 y 16, lineal** sobre MySQL con la guarda
(`VERIFY_CONC=1`: es lo que aplica; el push no toca `OrderCreator` ni Redsys) · contrato en verde ·
migración aplicada en la BD local · Pint · docs-check.

**Lo que enseñó**: **un censo que siembra cada columna con un valor es una guarda que muerde en la
dirección correcta**: no bastó con declarar las columnas nuevas, hubo que sembrarlas — y eso es lo que
prueba que `anonymize()` las toca de verdad.

Verificación: suite (contador en `ESTADO.md`) · `VERIFY_CONC=1` con `waiver:verify-chain` ·
headless (alta con casilla → sin firma hasta verificar → firma tras el enlace) · Pint ✓ · docs-check ✓.

## #180 · 2026-08-26 · Tanda 4 · el PDF del waiver dice exactamente lo que el diseño garantiza — y la cadena cruza cada firma con su versión. La tanda 4 queda CERRADA

**Qué se hizo** (carril A; la revisión `#169` **§10.6**: lo que el PDF afirmaba DE MÁS; §9.11):

1. **«Verificada» → «Coincide (comprobación interna)»**, y una nota nueva bajo la tabla de integridad
   dice qué es esa comprobación y qué NO: una cadena SHA-256 por titular, sin secreto, guardada en la
   propia base de datos, sin sello de tiempo cualificado ni anclaje en un tercero — detecta
   alteraciones accidentales o hechas por la aplicación; **no protege frente a quien escriba en la BD**.
   Es lo que §4.7 asume al aplazar el sello RFC 3161, y el documento lo tiene que decir (WAI-02, con
   dos escépticos detrás). El pie deja de decir «fila inmutable»: dice «solo se añade, con hash
   encadenado» y que el registro no está anclado en un tercero.
2. **En una firma de mostrador, el PDF ya no atribuye a la persona lo que hizo el operador** (WAI-07):
   los datos de identidad «los tecleó el operador en el mostrador», y las filas de IP y navegador se
   rotulan «del puesto de mostrador». Hasta hoy imprimía «los declaró la persona al crear su cuenta»
   y una IP que era la del puesto, sin decirlo.
3. **`WaiverChain::verify()` cruza cada firma con su VERSIÓN** (NUC-8): hash de la versión y
   `document_hash` = `body_hash`. Una versión alterada por debajo daba «cadena OK» en la herramienta
   que se usa para auditar la cadena entera y solo el PDF lo veía, fila a fila.
4. Todo en **es/en/fr con paridad** (la guarda de los tres idiomas cubre las claves nuevas y exige
   que la nota diga cosas distintas en cada uno).

**Lo medido**: WaiverProofPdfTest ampliado (firma del titular: nota de comprobación + nota de
identidad de siempre, sin «del puesto»; firma declarada: datos tecleados por el operador, IP/UA del
puesto, y NUNCA «los declaró la persona»; paridad de cinco claves nuevas) · WaiverSignatureChainTest
+1 (una versión alterada por debajo rompe la cadena) · mutaciones que muerden (nota de identidad sin
condición · rótulo de IP sin condición · `WaiverChain` sin cruzar la versión) · Pint · docs-check.

**Regla de método que dejó la 4a, pagada con un push rechazado**: `bash scripts/docs-check.sh | tail -1
&& git commit …` **no encadena el gate: encadena `tail`**. La migración nueva subió el recuento a 78,
la doc decía 77, `docs-check` salió rojo… y el `&&` siguió porque el exit era el de `tail`. El
`pre-push` lo paró (es exactamente para lo que existe). Desde hoy el exit del gate documental se lee
del propio script (`$?` sin tubería) antes de commitear.

**Lo que queda del waiver, y de quién es**: del owner, **el texto definitivo** (§8.1), **el plazo**
(§4.6) y **su ojo en navegador** (§9.10; ya con el anti-bot encendido); de agente, las bajas de §10.7 y
§10.9 que no bloquean (la IP por `trustProxies` es infra), y **«menores a cargo»** (C), que hereda
NUC-3. **La tanda 4 —cinco unidades, `#171` → `#180`— queda cerrada.**

Verificación: suite (contador en `ESTADO.md`) · Pint ✓ · docs-check ✓ · mutaciones ✓.

## #181 · 2026-08-26 · [DECIDIDO owner] Tras la 4b, `ViewOrder` se queda como quede — y el cierre del carril B con el desmontaje a un paso del final

Dos cosas, al cerrar la sesión del carril B (el portátil):

1. **`[DECIDIDO owner]`: el desmontaje TERMINA con la 4b.** La pregunta que §4.4 de la spec dejaba
   abierta a propósito («¿debe `ViewOrder` seguir siendo una sola clase?») queda respondida: las
   ~2.200 líneas que quedarán tras mover el dinero son **composición Filament y entrega legítima**
   (los 10 botones con sus formularios, ayudantes de pantalla), y **no se parten** en clases por
   acción — no quitaría riesgo ni líneas del producto, solo las repartiría. La cifra final se
   declara MEDIDA al cerrar la 4b, como exige §2·3.

2. **El estado del desmontaje al cierre** (todo empujado y verde): paso 0 (`#170`) · extracción 1
   (`#172`) · extracción 2 (`#173`) · extracción 3 (`#176`, con `VERIFY_CONC` ganado) · el
   instrumento de la 4 (`#177`, visto fallar). `ViewOrder` en **3.907 líneas** (de 5.280, −26 %).
   **Queda SOLO la 4b** — mover la orquestación del dinero — y su handoff está escrito sub-paso a
   sub-paso en la spec **§9.5** (orden A→H por riesgo creciente, con el instrumento `panel-edit`
   como red y la pasada de navegador del owner al cerrar). La retoma el MISMO carril (el portátil)
   en la siguiente sesión; el carril A sigue con el waiver en el otro ordenador.

Verificación del cierre: suite **2956 / 17.033 en verde** tras `git pull --rebase` del `#178` del
carril A (⚠️ los 30 rojos iniciales eran el bundle SSR RANCIO de `#69` — `npm run build:ssr` y
verde; ni un assert tocado) · `audit-clock` corrido por los fixtures de calendario nuevos ·
`composer audit` y `npm audit` en 0 · Pint ✓ · docs-check ✓.

## #182 · 2026-08-26 · [DECIDIDO agente B] La forma del contrato de la 4b: un servicio por forma TRANSACCIONAL, el lock consolidado, y cinco correcciones a la letra del handoff

Retoma del carril B (el portátil) a las 23:00, con el encargo del owner de proceder «según tu
valoración profesional». §4.4 de `specs/desmontar-view-order.md` dejaba a este paso el nombre y la
forma del contrato; se decidió **con el código entero leído antes de tocar una línea** y está
escrito sub-paso a sub-paso en la spec **§9.6** (el handoff original de §9.5 se conserva, corregido).

**Lo que decide, y por qué:**
1. **Cuatro servicios, no uno.** Lo que se muda tiene CUATRO formas transaccionales distintas
   (txn de aforo + secuencia financiera post-commit · txn de aforo sola · txn mínima · sin txn
   propia). Meterlas en un servicio de ~1.300 líneas es reproducir `ViewOrder` en `app/Domain/`.
   `OrderItemEditor` (edit + changeSlot, dueño del lock) · `OrderItemEventDataWriter` ·
   `OrderItemCanceller` · `OrderItemRefunder` · `ItemEditPricing` (solo lectura, control negativo)
   · `ZoneDaySlotLock` · `Booking\Contracts\ItemActionOutcome`.
2. **La consolidación de §8.9 se hace**: `ZoneDaySlotLock` compartido con `OrderCreator::lockSlots`,
   y en el editor UN solo punto de lock (`withZoneDayLock()`) para que `panel-edit` cubra los dos
   caminos. Verificación: helper mutado → `entry` Y `panel-edit` en rojo.
3. **Cinco correcciones a la letra de §9.5**, todas medidas: (i) el refund no puede importar
   `PaymentRefund` desde Booking (baseline de módulos «solo encoge») → `mode`/`intent` los resuelve
   la página como ya hace el reembolso de PEDIDO · (ii) `applyGroupChoices`/`normalizeAddonEdits`
   son traducción form→intención y se quedan en la entrega · (iii) la asimetría del waterfall es
   sintáctica (el contexto per-invitado lleva siempre `quantity_change`) · (iv) el lock, punto
   único · (v) la autorización es la primera guarda del servicio con `User $by` explícito y el
   servicio devuelve un outcome que la página traduce.

**Base medida al retomar** (portátil): árbol = `origin/main` (`#181`), suite **2960 / 17.087 en
verde** (49 s; 1 notice de PHPUnit por identificar en A0), `docs-check` ✓, web 200, y **3
migraciones del waiver PENDIENTES** en esta BD (se aplican en A0; sin ellas el panel de un pedido
falla por el badge de `#174`).

## #183 · 2026-08-26 · Tanda 4 · la revisión adversarial de la PROPIA tanda, aplicada — y el guion re-recorrido con la conducta definitiva

**Qué se hizo** (carril A; spec **§9.12**): la tanda 4 (`#171`→`#180`) se revisó como se revisó el
subsistema (§10.0): 28 agentes, **24 hallazgos confirmados, 0 refutados, 52 afirmaciones que
aguantaron**. Todo lo confirmado se arregló o se fichó en la misma pasada:

1. **S-1 (alta) — `Verified` no lo emite solo el enlace del correo.** También lo emite
   **`RedsysReturnHandler::autoVerifyBuyer()`** al cobrar en pay-first, **dentro de la transacción del
   cobro**, y si gana la notificación S2S la petición es la de Redsys: la firma pendiente del alta
   (`#179`) habría llevado la IP y el navegador **del servidor de Redsys**, y se habría escrito —con su
   lock y su transacción— dentro de la transacción del dinero. Desde hoy la aceptación pendiente guarda
   **la IP y el navegador del momento de marcar la casilla** (`waiver_pending_ip`/`_user_agent`; PII
   del alta: `anonymize()` las nulifica y el censo las declara) y el listener firma con ellos en
   **`DB::afterCommit`**: nunca dentro de la transacción de otro; si el cobro se deshace, no hay firma.
   `accepted_at` sigue siendo el momento de la verificación, y el PDF lo dice.
2. **D1 (alta) — el guion §5.nonies describía la conducta ANTERIOR** (V31·2 «se crea igual: es opt-in»,
   V31·3 «Firmada» nada más crear la cuenta). Reescrito con `#178` (casilla obligatoria en interno) y
   `#179` (la firma nace al verificar), **y re-recorrido en headless de V31 a V35 y V31·4: 111/111 ✓,
   0 desviaciones**, sin presuponer la versión de partida. Lo nuevo que midió: el 422 sin casilla con
   su error visible y el `GET /legal/waiver` de relectura; la aceptación **pendiente y sin firma en BD**
   antes de verificar; la firma nacida al verificar; y la aceptación **descartada** cuando el texto
   cambia antes del enlace.
3. **Medias**: `pending` en `WaiverStatus` y en `account-context.waiver` (S-2; `api-v1.md` pt. 94) ·
   el 422 de `accept_waiver` también relee el texto (CAJ-422: con `document: null` cacheado la casilla
   ni existía) · la declaración en mostrador vale para una cuenta que YA existía por correo o elegida
   por teléfono (F-01, `CustomerRegistrar::declareAtCounter()`, idempotente por versión) · y los
   desfases de doc (tracker, `CLAUDE.md`, README, `ESTADO` `#176`→`#178`, spec §7·2/§9.8/§10.4/§10.11).
4. **Bajas que cambiaban conducta**: la vigencia se re-comprueba DENTRO del lock (S-3, TOCTOU
   publicar-vs-firmar) · `EmailChangeController` emite `Verified` (S-5: confirmar el correo nuevo es
   verificarlo, y la aceptación pendiente no se queda colgada) · la guarda de borrador compara texto y
   no bytes: NFC y blanco tras `[` (S-6) · re-marcar apaga la relectura pegajosa (CAJ-REREAD) · el aviso
   del modal de publicar se ve (`HtmlString`, F-02) · los idiomas anunciados son los que se publican
   (F-07) · «versión anterior» en ámbar (F-03) · la rama negativa del PDF con el mismo vocabulario y
   renderizada en test (F-04) · ayuda `zh_CN` completa (F-05) · la versión del mostrador memoizada por
   petición (F-06). **Bajas que no cambian conducta**, fichadas en `DEUDA.md`: S-4 (el canal `web` del
   alta lo decide el `Origin`: es el diseño), TURN-CLAVE-VACÍA y TURN-ESPERA (inalcanzables hoy), y
   `pending` que el cajón no pinta (una cuenta sin verificar no entra en Mi cuenta por web).

**Lo medido**: +9 tests PHP, +1 JS · **10 mutaciones, las 10 muerden** (UA del clic en vez del alta ·
sin `afterCommit` · sin vigencia en el lock · cuenta existente sin declarar · sin NFC · sin colapsar el
blanco · idiomas vacíos contados · sin `Verified` en el cambio de correo · `pending` siempre `false` ·
sin releer ante `accept_waiver`) · `waiver:verify-chain` 8/16 lineal sobre InnoDB (`WaiverSigner` tocado:
`VERIFY_CONC`) · Pint · docs-check con el exit leído del script · el guion completo en headless.

**Dos trampas del arnés, pagadas con tres pasadas** (guion §5.nonies): tras el 422 el cajón relee el
texto y re-renderiza la casilla —hay que esperar ese `GET` antes de marcarla, o la re-render la
desmarca—; y con «Leer el texto completo» desplegado el botón queda bajo el pliegue de un contenedor
con scroll suave: un clic sin `scrollIntoViewIfNeeded()` no envía nada. Ninguna es un defecto de la
app: se midieron con un sondeo aparte antes de tocar el script.

**Lo que queda del waiver, y de quién es**: del owner, **el texto definitivo** (§8.1), **el plazo**
(§4.6) y **su ojo en navegador** (guion §5.nonies, ya con el anti-bot encendido y la conducta
definitiva); de agente, «menores a cargo» (C), que hereda NUC-3. **Las decisiones de §7 y §10.11 están
todas tomadas y ejecutadas.**

Verificación: suite (contador en `ESTADO.md`) · Pint ✓ · docs-check ✓ · mutaciones ✓ · headless 111/111 ✓.

## #184 · 2026-08-27 · Extracción 4b · sub-paso A: lo PURO al dominio — y la regla de bloqueo per-invitado/grupo no tenía test

Primer sub-paso del plan de `#182` (spec §9.6.1). Nacen **`ItemEditPricing`** (la tarificación de
una edición: catálogo del día, diff del producto/cantidad, complementos — la misma aritmética para
la vista previa y el guardado) y **`OrderItemEditor`** con su mitad pura (los validadores de
destino, producto, complementos y franja, ahora PÚBLICOS: los 5 tests que entraban por reflexión
llaman al método). `ViewOrder` pasa de 3.907 a **3.350 líneas**.

**Lo que enseñó:**
1. **Una regla de defensa sin red.** La mutación «nada bloqueado» en `childAddonMeta` salió VERDE
   en los 591 tests de `Admin/Orders`: `addon_locked` se aseveraba una sola vez y por la rama del
   MÍNIMO (incluido obligatorio que no se quita), no por la del BLOQUEO (un complemento
   per-invitado o de grupo no admite que le cambien la cantidad; la fija el aforo o «elige menú»).
   Ganó su test con control negativo antes de cerrar; ahora **11 de 11 mutaciones muerden**.
2. **Pint conserva un `use` si el nombre aparece en un comentario**: cuatro imports de `ViewOrder`
   (`RateResolver`, `OperatingSchedule`, `ProductAvailability`, `ItemRescheduleOffer`) solo vivían
   en docblocks y hubo que retirarlos a mano. Medir con `grep -v '^use '`, no fiarse del fixer.
3. **La fidelidad se mide por diferencia de conjuntos** (`comm -23` de las líneas borradas contra
   los ficheros nuevos): el residuo son exactamente los call-sites, las inyecciones, las
   visibilidades y los docblocks reescritos — cero lógica.
4. Corrección a `#182`: son **6** entradas por reflexión (5 en tests + el instrumento), no siete.

Verificación: suite **2961 / 17.090 en verde** (+1 test) · Pint global ✓ · `php -l` ✓ · 11/11
mutaciones con ancla única y restauración por md5 · A0 previo: 6/6 escenarios + Redsys PASAN sobre
MySQL, migraciones aplicadas.

## #185 · 2026-08-27 · Extracción 4b · sub-paso B: los datos del evento al dominio — y tres reglas que la página no alcanza ganan su test directo

Segundo sub-paso del plan de `#182` (spec §9.6.1). Nacen el contrato **`Booking\Contracts\ItemActionOutcome`**
(`ok / changed / reason / extra` — el resultado de todas las acciones de ítem de la 4b: el dominio dice
QUÉ pasó y la entrega traduce el rechazo y el «sin cambios») y **`OrderItemEventDataWriter`**, la
única de las cuatro formas transaccionales que no toca dinero ni aforo. `ViewOrder` pasa de 3.350 a
**3.173 líneas**: las dos copias de guardas/sanitize/txn/audit se funden en una llamada, y cada
variante conserva exactamente lo que hacía con el rechazo.

**Lo que enseñó:**
1. **Tres reglas de defensa que la red de PÁGINA no puede ejercitar.** La mutación «sin obligatorios»
   salió verde con un test llamado `…required_missing…_logs_blocked` delante: la validación `required()`
   de Filament rechaza el formulario antes de que el handler vea nada, y el test lo traga con un
   `try/catch` sin aseverar el audit. El permiso re-exigido en el servicio (`SEC-04`) es inalcanzable
   desde el despachador, que ya filtra por permiso. Y «sin cambios» solo se medía por el audit.
   **Un servicio de dominio se puede llamar sin Filament**: tres tests directos (`test_writer_*`) y
   las tres mutaciones muerden. Es el argumento empírico de la extracción entera.
2. **La topología de cada variante es conducta**: la consolidada calla ante permiso/no-pack/sin-campos
   y audita solo `stale_version` y `required_missing`; la suelta exige el permiso con `abort_unless`
   ANTES de resolver el ítem (moverlo detrás cambiaría un 403 por un `not_found`). La mudanza las
   respeta una a una en vez de «normalizarlas».
3. **`RGPD-02` cambia de casa**: `eventDataDiffKeys` vive en el servicio; «Dónde vive» re-apuntado.

Verificación: suite **2973 / 17.139 en verde** (+3 tests) · Pint global ✓ · `php -l` ✓ · 6/6
mutaciones con ancla única y restauración por md5 · fidelidad por diferencia de conjuntos: cero lógica.

## #186 · 2026-08-27 · Extracción 4b · sub-paso C0: la receta anti-sobreventa vive UNA vez (`ZoneDaySlotLock`) — y el fósil que describía el fix insuficiente

Tercer sub-paso del plan de `#182` (spec §9.6.1), el que decide lo que §8.9 dejaba abierto:
**se consolida**. `ZoneDaySlotLock::acquire(zoneIds, dates)` es la receta de `AFORO-01`/`AFORO-05`
—literales resueltos fuera de la transacción, `orderBy('id')`, `FOR UPDATE` como primera sentencia—
con su porqué escrito UNA vez; `OrderCreator::lockSlots` (compra) y `ViewOrder::lockZoneDaySlots`
(panel) la llaman. Hasta hoy vivía dos veces y el porqué solo en el dominio: si alguien corregía
una, la otra derivaba en silencio.

**Lo que enseñó:**
1. **El fósil.** `lockSlots` llevaba DOS docblocks apilados; el primero explicaba que «resuelve las
   zonas con una SUBCONSULTA dentro de la propia sentencia de bloqueo» — el fix INSUFICIENTE que
   `#246` reprodujo como sobreventa y que `AFORO-01` prohíbe. Un lector fiel al primer docblock
   habría reintroducido el bug creyendo cumplir la invariante. Retirado.
2. **Una mutación, dos puertas rojas.** Con el helper leyendo sin `FOR UPDATE`, `entry` FALLA
   (compra) y `panel-edit` FALLA (panel): la prueba de que las dos puertas comparten de verdad la
   receta. Con el helper real, 6/6 escenarios y Redsys PASAN sobre MySQL, cero restos.
3. **El binding de la fecha.** La página ataba un Carbon (`'Y-m-d 00:00:00'`): MySQL lo compara bien
   con la columna DATE, SQLite no — la suite nunca había visto ese lock (ni lo necesita). El helper
   recibe `Y-m-d`, exacto en ambos motores; el conjunto bloqueado en MySQL no cambia.
4. **El `CRITICAL_RE` crece aquí, no al cerrar**: `ZoneDaySlotLock` y `OrderItemEditor` entran en el
   hook y en `CriticalPathGateTest::CRITICAL_FILES` cuando NACE el lock, para que los pushes de C→F
   exijan `VERIFY_CONC`; `ItemEditPricing` y `OrderItemEventDataWriter` quedan como controles negativos.

Verificación: suite **2973 / 17.145 en verde** (mismos tests; +6 aserciones del gate) · Pint global ✓
· `php -l` ✓ · `bash -n` del hook ✓ · `purchase:verify-oversell` 6/6 · mutación vista FALLAR en `entry` y `panel-edit`
(restauración por md5) · `redsys:verify-concurrency` PASA · empujado con `VERIFY_CONC=1`.

## #187 · 2026-08-27 · Extracción 4b · sub-paso C: el cambio de franja al dominio con UN punto de lock — y el instrumento invoca el servicio por su contrato

Cuarto sub-paso del plan de `#182` (spec §9.6.1). `OrderItemEditor::changeSlot()` es la operación
entera —las cinco capas, la transacción bajo **`withZoneDayLock()`** (abre la txn y toma
`ZoneDaySlotLock` como PRIMERA sentencia; será el mismo punto para `edit()` en F, así que el
escenario `panel-edit` vigila las dos), el audit, los datos del evento con el token refrescado y el
email—; la página traduce el outcome. `ViewOrder` pasa de 3.173 a **3.008 líneas**.

**Lo que enseñó:**
1. **El instrumento ya no necesita la página.** `purchase:verify-oversell --scenario=panel-edit`
   invoca `changeSlot()` por contrato (sin `ReflectionMethod`, sin `new ViewOrder`, sin
   `Auth::login`) y **FALLA con `withZoneDayLock` sin tomar el lock, PASA con el lock real** sobre
   MySQL. Es la red de concurrencia de C y de F.
2. **Tres reglas sin red, otra vez** (la constante de la 4b): la huella propia excluida solo tenía
   test para el PACK (`AFORO-06`), no para la entrada multi-franja; la revalidación de aforo bajo el
   lock la prometía el docblock del fichero de tests («aforo insuficiente — blocked») y no la probaba
   nadie; y el audit del rechazo anidado de `event_data` era inalcanzable desde Filament. Tres
   tests nuevos y las mutaciones muerden: 8 de 8 observables.
3. **Una mutación INOBSERVABLE es un ahorro, no una conducta**: sin el atajo «misma franja = no-op»,
   la misma franja pasa la revalidación con la huella excluida y guarda lo mismo. No se prueba.
4. **Dos desviaciones más de §9.5·G, medidas**: `humanSlotLabel` se PERSISTE (contexto de los
   ajustes y audit) → dominio; el audit del rechazo anidado es parte del rastro de la operación →
   `OrderItemEventDataWriter::auditBlocked()`, público para la página y el editor.
5. **Trampa de instrumento**: `perl 's/\Q$this->…\E/'` interpola `$this` antes de `\Q` y la mutación
   «no cambia nada» — el diff vacío lo delató; la sustitución va por `$ENV`.

Verificación: suite **2976 / 17.168 en verde** (+3 tests) · Pint global ✓ · `php -l` ✓ · `panel-edit`
FALLA mutado / PASA real (MySQL, 8 workers) · 7 mutaciones más sobre la red SQLite, todas muerden ·
fidelidad por diferencia de conjuntos: cero lógica · empujado con `VERIFY_CONC=1`.

## #188 · 2026-08-27 · Extracción 4b · sub-pasos D y E: cancelar y reembolsar un ítem al dominio — y cinco guardas que la red de página no distinguía del dominio

Quinto y sexto sub-pasos del plan de `#182` (spec §9.6.1). `OrderItemCanceller::cancel()` (la
transacción con lock de pedido y de ítem, la cascada a los complementos y el audit de éxito DENTRO
—§4.3/§8.4—, el email después) y `OrderItemRefunder::refund()` con el contrato `ItemRefundRequest`
(las guardas y la selección; el dinero sigue en `Order::executePartialRefundBatch`). `ViewOrder`
pasa de 3.008 a **2.848 líneas**. Los dos son controles negativos del `CRITICAL_RE`: uno libera
aforo, el otro no toma locks (viven en `Order`).

**Lo que decide y lo que enseñó:**
1. **E no importa `PaymentRefund`** (la corrección 1 de `#182`, ejecutada): el modo —forzado a
   manual sin pasarela— y la intención los resuelve la PÁGINA, como ya hacía el reembolso de
   PEDIDO, y llegan al dominio como cadenas válidas. La baseline de módulos no crece.
2. **La página resuelve el ítem y el servicio recibe el modelo**: `not_found` es de la entrega
   (nunca tuvo audit) y así ni se consulta dos veces ni el outcome tiene que devolver un modelo.
3. **Cinco guardas sin red propia.** Los dos permisos (inalcanzables desde la página: `visible()`
   filtra y Filament no monta una acción oculta) y **tres de E cuyos tests de página existían y
   salían verdes con la guarda retirada**: «selección vacía» e «ítem de otro pedido» solo aseveraban
   «sin REST, sin reembolso» —y eso lo garantiza también el dominio por su cuenta—, y la guarda del
   principal la cubre el `mountUsing`, no el handler. Un test directo fija la RAZÓN de cada una
   (`no_items_selected` · `invalid_item_selection` con el id enviado · `item_is_addon`), que es lo
   que la página audita. **16 de 16 mutaciones muerden.**
4. **Una rareza conservada y declarada**: si el ítem resultó cancelado entre la guarda y el lock,
   la cancelación sale sin escribir y el email se envía igual. Era la conducta de la página; esta
   mudanza no la cambia (la arreglaría una ficha, no un refactor).

Verificación: suite **2979 / 17.185 en verde** (+3 tests) · Pint global ✓ · `php -l` ✓ · 16/16
mutaciones con ancla única y restauración por md5 · fidelidad por diferencia de conjuntos: cero
lógica · Admin/Orders 601 · Architecture 197.

## #189 · 2026-08-27 · Extracción 4b · sub-pasos F y G: la edición con dinero al dominio con UN punto de lock y el waterfall como UNA pieza — el desmontaje de `ViewOrder`, de agente, TERMINA

Séptimo y último sub-paso de código del plan de `#182` (spec §9.6.1). `OrderItemEditor::edit()`
lleva las cuatro fases de §4.3 con su frontera intacta: guardas ANTES de abrir transacción · la txn
SOLO de aforo/mutación bajo `withZoneDayLock()` (el mismo punto que `changeSlot()`) · POST-commit el
audit, la secuencia financiera —cada paso con su transacción corta en `Order`— y los datos del
evento · el email. `ViewOrder` pasa de 2.848 a **2.355 líneas y 50 métodos: cifra FINAL, medida**
(de 5.280 y 98 al abrir la spec; 3.907 al empezar la 4b). G, medido: cero métodos privados sin
llamador en la página; lo que queda es composición Filament y traducción de outcomes, y `#181`
decidió que no se parte.

**Lo que decide y lo que enseñó:**
1. **El waterfall es UNA pieza** (`creditReduction`, §8.9): `extra_due` → resto de la señal →
   marcador 0 € solo sin crédito y con cambio reconstruible. La «asimetría» era sintáctica — el
   contexto per-invitado lleva siempre `quantity_change` — y las mutaciones MF5/MF6 lo confirman.
2. **Una guarda de arquitectura para el punto único de lock** (`OrderItemEditorSingleLockPointTest`):
   el editor abre UNA transacción y toma el lock UNA vez, las dos operaciones pasan por ahí, y la
   página solo abre la de cancelar el PEDIDO. Es lo único que puede ver una segunda transacción antes
   de que una carrera la mida.
3. **Tres reglas sin red, la última tanda**: la huella propia excluida para el PACK en `edit()` (la
   entrada sí la tenía), «con crédito NO hay marcador» (un marcador de más duplicaría el contexto que
   reconstruye lo cobrado) y los datos del evento en el MISMO guardado que una edición con dinero.
   Tres tests; **16 de 16 mutaciones muerden** — 74 de 74 en toda la 4b, más una inobservable.
4. **Dos fósiles retirados**: el docblock de «Handler UNIFICADO» vivía encima de `lockZoneDaySlots`,
   no de su método; y `PAY-18` en `INVARIANTES` citaba `ViewOrder::computeEditPricing()`, caducado
   desde A sin que lo viera nadie — re-apuntado.
5. **Un solo `fresh()` donde había cinco**: medido que ningún escritor de `Order` muta el `OrderItem`
   que recibe; misma conducta, menos consultas.
6. **Lo que enseñó la 4b entera**: 15 tests nuevos son reglas de defensa que la página NO podía
   alcanzar —Filament valida antes, el despachador filtra el permiso, el dominio garantiza lo mismo
   por otro camino— y que solo un servicio invocable sin Filament permite probar. El valor de la
   extracción no es la cifra de líneas: es que esas reglas tienen red por primera vez.

❗ **Queda H, de owner**: la pasada de NAVEGADOR por las 10 acciones del panel (spec §6·5). Hasta ese
✅ el desmontaje sigue 🟦 (`CONVENCIONES §3.bis`, cuarta condición del DoD).

Verificación: suite **2984 / 17.208 en verde** (+5 tests) · Pint global ✓ · `php -l` ✓ · docs-check ✓
· `purchase:verify-oversell` **6/6 PASAN** (8 workers, MySQL) · `redsys:verify-concurrency` PASA ·
cero restos en la BD · 16/16 mutaciones con ancla única y restauración por md5 · fidelidad por
diferencia de conjuntos: cero lógica · empujado con `VERIFY_CONC=1`.
