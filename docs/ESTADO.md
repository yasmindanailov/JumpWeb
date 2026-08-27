# Estado del proyecto — foto viva

> Documento CORTO (carga obligatoria al arrancar). Solo «dónde estamos / qué sigue».
> **El «qué pasó» de cada paso vive en `00-REFACTOR.md` (tracker) y `DECISIONES.md` (el porqué):
> aquí solo se enlaza.** Última actualización: **2026-08-27**.
>
> ❗❗ **ATENCIÓN: desde el 2026-08-27 hay TRES CARRILES sobre `main` (no dos).** El B cerró ese día a
> las 07:30 con todo empujado y verde; el A cerró a las 18:40 con las TANDAS 1, 2 y 3 de «menores a
> cargo» empujadas (`#191` · `#198` · `#199`) y **su siguiente sesión hace la TANDA 4, la asignación en
> el embudo** (`[DECIDIDO owner]`; el mapa de arranque está en su fila); y **nace el carril C, el
> TEMA**, que cerró y empujó su tanda 1 esa misma tarde (`#192`,
> `specs/tema-por-instalacion.md`). Los tres tienen su fila abajo. Antes de planificar nada,
> `git fetch`. El reparto vigente es el bloque de aquí abajo — **es el único**: hasta el
> 2026-08-26 había también un resumen en esta cabecera que se quedó atrás y **contradecía al de
> abajo** (decía que el agente A estaba en panel/dinero cuando lleva dos días en el waiver). Se
> retiró: dos repartos son un reparto que no se puede creer.
>
> ❗❗ **REPARTO VIGENTE — LÉELO ANTES DE ELEGIR TAREA.** (reescrito el 2026-08-26 por la tarde, por
> indicación del owner: los dos carriles cambian de trabajo, no de máquina)
> · **Agente A (la máquina de los 24 + 9 pedidos, la del waiver) → SESIÓN ABIERTA desde el 2026-08-27
>   por la noche: la TANDA 4 de «menores a cargo» (la asignación en el embudo) ES DE ESTE CARRIL y su
>   DISEÑO DE EJECUCIÓN está en el árbol** (`specs/menores-a-cargo.md` **§9.9**, `#202`). Se escribió
>   MIDIENDO antes (seis lectores + crítico: 296 hechos, 14 afirmaciones de la spec falsas o
>   imprecisas) y el owner decidió las cuatro ambigüedades a pregunta simple: **(1)** quien se
>   identifica en el paso 5 vuelve al CARRITO con aviso si tiene menores y entradas sin asignar ·
>   **(2)** ❗ **la exención firmada es CONDICIÓN para asignar** (el servidor lo exige) · **(3)** la
>   purga de la cesta se arregla AHORA como unidad 0 · **(4)** el panel **NO entra** (el owner lo
>   RECTIFICÓ la misma noche: «tendrá su propia sesión; ahora solamente la gestión de menores»).
>   ✅ **U0 HECHA y empujada**: el defecto medido en headless —**la cesta del PROPIO titular se PURGABA
>   cuando el cajón nace abierto** (`/entradas`, `/mi-cuenta`, `/login`, `/registro`,
>   `/recuperar-contrasena`; 2/2 purga, 4/4 se conservaba desde la home; causa: `props.userId` llegaba
>   del HTML y no la leía nadie)— está cerrado: el dueño se siembra en `index.js` antes de montar, guarda
>   estructural con 2 mutaciones que muerden, sonda re-corrida 10/10 (spec §9.9.6; ficha de `DEUDA.md`
>   retirada). ✅ **U1 HECHA y empujada (madrugada del 28)**: el SERVIDOR entero (spec **§9.9.7**):
>   `dependent_assignments` + `DependentAssignment` · `Dependent::referenced()` (UN predicado para
>   `remove()`/`anonymize()`/poda) · `Booking\Contracts\CheckoutLines` + reader + doble · `DependentAssigner`
>   (`check()` ANTES del dinero → 422 por campo; `assign()` tras el `allow` bajo el lock del titular,
>   idempotente, sin lanzar) · `CartLine.dependent_ids` en el contrato y en `CartPayload` (Booking no lo
>   ve) · `OrdersController::store()` · `event-data` con `dependents[]` · `anonymize()` y el export ·
>   `api.dependents.*` ×3 · la paridad mínima de `cart.js`. **9 mutaciones, las 9 muerden · 6 escenarios
>   + Redsys sobre MySQL ✓ · sonda HTTP de 10 pasos ✓.** ▶ **Orden de trabajo**: ~~U0~~ → ~~U1~~ →
>   **U2 (el cajón: `toCheckoutItems()`, `assignment.js`, casillas en los pasos 3 y 4, la puerta 2 en
>   `admission.js`, paso 6 y tarjeta, rótulos ×3, manifiesto con caso de ENTRADA, techos medidos)** →
>   U4 (el ojo del owner). Cada unidad se empuja verde. ⚠️ **U2 entra en `layout.blade.php`** (la lista
>   de claves con sesión): aviso al carril C ya dado abajo.
>   ▶ **Para el agente del C (el tema/armazón)**: esta tanda tocará **`resources/views/components/layout.blade.php`**
>   (solo la lista de claves `account.dependents.*` que viajan con sesión, en U2) y **NO toca**
>   `public/css/*`, `nav.blade.php`, `menu.blade.php` ni `app.js` salvo, en U0, la siembra del dueño de
>   la cesta si acaba viviendo en `app.js` (te lo avisaré aquí antes). El selector del embudo se
>   compone con clases que ya existen (`eventfields`, `cart__pending`, `form`): **cero CSS nuevo**,
>   como la zona de menores. Si tu 2c·2 toca `layout.blade.php`, `git pull --rebase` antes de empujar.
>   (El aviso al carril B que hubo aquí se retira: el owner rectificó y esta tanda NO toca `app/Filament/**`.)
>   Lo anterior de esta fila sigue siendo cierto y se conserva como historia: cierre de la sesión del
>   27 a las 18:40 con las TANDAS 1, 2 y 3 EMPUJADAS (`#191` · `#198` · `#199`;
>   `specs/menores-a-cargo.md` §9.1/§9.7/§9.8) **y la revisión de las cinco decisiones del owner hecha
>   (`#197`)**. Cierre sobre el árbol final: suite 3062 / 17.694 · JS 724 · Pint ✓ · docs-check ✓ ·
>   build ✓ · `audit-clock` ✓ 12/12 fronteras.
>   ▶ ❗ **POR DÓNDE RETOMA la siguiente sesión de ESTE carril: la TANDA 4 — la asignación de entradas a
>   un menor EN EL EMBUDO** (`[DECIDIDO owner, 2026-08-27]`: «seguimos la tanda 4 en el siguiente chat»).
>   Por dónde: `/arranque-sesion` → `docs/INVARIANTES.md` §1 (PAY-04, PAY-12) y §2 (AFORO-01, AFORO-10)
>   → la spec **§4.6, §4.7, §4.8 con §8.1/§8.2, §4.9, §4.10** y **§9.4** → `specs/checkout-orquestado.md`.
>   Lo que hay que saber ANTES de escribir: **(1)** la asignación la posee Identity con el ítem por id
>   ENTERO (Booking no puede mirar a Identity; `ModuleBoundariesTest`) · **(2)** dos puertas, CERO pasos
>   nuevos en `machine.js` (con sesión en el paso 3 al elegir cantidad; sin ella en el paso 5) ·
>   **(3)** el hueco viaja en la LISTA BLANCA de `cart.js::save()` **manteniendo `v: 1`** (subir
>   `STORAGE_VERSION` purga todas las cestas vivas) y `reconcile` deja la línea sin asignar si el menor
>   se retiró · **(4)** el servidor re-valida la pertenencia de cada id (anti-IDOR, la guarda más
>   importante de la spec, con su mutación) · **(5)** se escribe DESPUÉS de que `OrderCreator` devuelva
>   y FUERA de la transacción de los locks (post-commit, idempotente); si falla, el pedido sigue en pie ·
>   **(6)** toca `OrderCreator`/`CheckoutOrchestrator` → `CRITICAL_RE`: los CINCO escenarios de
>   `purchase:verify-oversell` y `VERIFY_CONC=1` · **(7)** solo entradas, no packs · **(8)** el chunk
>   del cajón tiene 0,59 KiB: se mide y se sube por feature (`#197`·2). Primera unidad recomendada: la
>   tabla + el contrato de escritura post-commit + la re-validación, SIN tocar el cajón; el cajón
>   después, medido. ⚠️ **Antes de la tanda 4 conviene el OJO del owner sobre la zona del cajón**
>   (guion §5.decies), porque la tanda 4 la usa.
>   **Lo que fue esta sesión — TANDA 1** (`#191`, spec **§9**): el núcleo en
>   Identity + la API, **sin firmas de menor todavía** — `dependents` + `DependentRegistry` (solo
>   menores; tope de servidor bajo el lock de la fila del titular) + `GET|POST|DELETE /me/dependents`
>   contra el contrato + `anonymize()`/export/purga de go-live/poda + el tope en Ajustes → «Puerta».
>   34 casos, 7/7 mutaciones muerden, 14 comprobaciones HTTP sobre MySQL con Bearer, BD local migrada.
>   ▶ ✅ **`[DECIDIDO owner, 2026-08-27]` (`#197`) — las cinco decisiones de la spec §9.5, tomadas:**
>   (1) **cadena por (titular, sujeto)** · (2) la zona del cajón se construye, se MIDE y el techo sube
>   por FEATURE · (3) la firma de un menor se conserva N meses tras su 18.º cumpleaños, ajuste propio
>   `waiver.dependent_retention_months` · (4) declarar NO exige correo verificado · (5) a un adulto no
>   se le declara. ▶ ✅ **TANDA 2 EMPUJADA (misma sesión, noche): la FIRMA DEL MENOR** (`#198`, spec
>   **§9.7**): cadena por (titular, sujeto) en `WaiverSigner` —`CRITICAL_RE`: `waiver:verify-chain`
>   REHECHO (mide la idempotencia bajo el lock), 8/16 PASA y **visto FALLAR sin el lock**, `VERIFY_CONC=1`—,
>   la FK `subject_id → dependents` RESTRICT (medida bloqueando un `DELETE` crudo), la identidad del
>   menor copiada en la firma (v3), `POST /me/dependents/{id}/waiver`, `Dependent.waiver`, el PDF y el
>   panel nombrando al menor, la retención del menor desde los 18 en Ajustes. +15 casos, 5/5 mutaciones,
>   sonda HTTP sobre MySQL. NUC-3 CERRADA.
>   ▶ ✅ **TANDA 3 EMPUJADA (misma sesión, noche): la ZONA DEL CAJÓN** (`#199`, spec **§9.8**):
>   `DependentsZone` + `DependentCard` + su store y su módulo plano, la entrada del índice con el icono
>   `users` (nuevo, copiado byte a byte), 17 rótulos ES/EN/FR solo con sesión. **Cero CSS nuevo** y
>   ≤ 40 líneas por componente. **Medido y subido por FEATURE** (`#197`·2): chunk 226,34 → 234,41 KiB
>   (techo 235, quedan 0,59) · payload con sesión 6.668 → 7.602 B (techo 7.700). JS 702 → 724.
>   **Guion en headless 20/20** (`VERIFICACION-E2E-CAJON.md` §5.decies; ⚠️ publica versiones `[E2E-DEP]`
>   en la BD local: v10 y v11 ya existen).
>   ▶ ❗ **POR DÓNDE SIGUE este carril: la TANDA 4 — la asignación en el embudo** (spec §4.7–§4.10 y
>   §9.4): la tabla de Identity con el ítem por id entero, las dos puertas sin paso nuevo, el hueco en
>   la lista blanca de `cart.js::save()` sobre `v: 1` (§8.1/§8.2), la re-validación en servidor y la
>   escritura post-commit fuera del lock. **Toca el checkout**: `OrderCreator`/`CheckoutOrchestrator`
>   están en el `CRITICAL_RE`. **Del owner**: su ✅ en navegador de la zona (guion §5.decies) y los DOS
>   valores de retención en meses (titular y menor), criterio jurídico.
>   **Ficheros de este trabajo** (además de los del carril, abajo): `database/migrations/*dependents*` ·
>   `app/Domain/Identity/{Models/Dependent,Services/DependentRegistry,Services/DependentSettings,Exceptions/Dependent*,Contracts/DependentRemoval}.php`
>   · `app/Http/Controllers/Api/V1/MeDependentsController.php` · `app/Http/Resources/Api/V1/DependentResource.php`
>   · `tests/Feature/Dependents/**` · `tests/Feature/Api/V1/MeDependentsTest.php` ·
>   `tests/Feature/Admin/Settings/DependentsCapSettingTest.php` · `openapi/v1.yaml`.
>   ⚠️ **Y tocó SEIS ficheros COMPARTIDOS, en un punto cada uno** (ya en `origin/main`):
>   `app/Providers/AppServiceProvider.php` (una línea del morphMap) · `app/Domain/Platform/Models/AuditLog.php`
>   (dos acciones) · `app/Filament/Pages/Settings.php` (un campo en «Puerta» + su clave en `MANAGED`) ·
>   `app/Console/Commands/PurgeCustomerData.php` (una línea antes de `users`) · `routes/console.php`
>   (`Dependent` en el `model:prune`) · `lang/{es,en,fr}/api.php` + `lang/es/admin.php` + `validation.php`.
>   ▶ **Para el agente del B**: nada pendiente de ti; si tocas alguno de esos seis, `git pull --rebase`
>   antes.
>   ✅ Lo anterior de este carril: el waiver, de agente, TERMINADO** (`#169` revisión adversarial del subsistema
>   + guion en headless · la **tanda 4** que esa revisión exigía: `#171` anti-bot · `#174` servidor ·
>   `#175` cajón · `#178` casilla del alta manual + casilla OBLIGATORIA en interno · `#179` correo
>   verificado para firmar · `#180` texto del PDF · **`#183` la revisión de la propia tanda, aplicada**
>   —24 confirmados, 0 refutados— con el guion §5.nonies **reescrito con la conducta definitiva y
>   re-recorrido en headless: 111/111 ✓**). El «qué pasó» vive en `00-REFACTOR.md` (Fase 6), en cada
>   decisión y en la spec §9.11/§9.12. Al cerrar, además: el único «PHPUnit notice» de la suite,
>   identificado y retirado, y el `pre-push` corregido para leer también «OK (N tests, M assertions)»
>   (commit `e5df6dd`: sin eso el gate se quedaba ciego con la suite verde).
>   ❗ **Lo que queda del waiver es del OWNER**: su ✅ en navegador (guion §5.nonies, **en local y con las
>   claves de PRUEBA de Turnstile en Ajustes**, receta en §5.bis — el defecto del anti-bot está
>   arreglado desde `#171`), el **texto definitivo** (spec §8.1: publicar la v1 real es irreversible) y
>   el **plazo de retención** (§4.6). Hasta eso, Fase 6 sigue 🟦.
>   ▶ ✅ **`[DECIDIDO owner, 2026-08-27]`: LA SIGUIENTE SESIÓN DE ESTE CARRIL HACE «MENORES A CARGO»**
>   (`specs/menores-a-cargo.md`, C). Por dónde: `/arranque-sesion` → `docs/INVARIANTES.md` §2 (AFORO)
>   → la spec entera **empezando por §8.1 y §8.2** (el mecanismo es la lista blanca de
>   `cart.js::save()`, y subir `STORAGE_VERSION` purga TODAS las cestas vivas) → §4.6 (Booking NO
>   mira a Identity; la asignación la posee Identity) → §4.7 (al elegir cantidad no hay sesión) →
>   hereda **NUC-3** de `DEUDA.md` (la poda con firmas de menor). Es spec-first: la spec está revisada
>   y sin código; la primera tanda se recorta con el owner delante, con número y coste, como el waiver.
>   Ficheros del carril: los de siempre del waiver y el cajón (`resources/js/sidebar/` ·
>   `resources/css/` · `storage/ssr/` · `lang/*/account.php` · `tests/Feature/Sidebar/` ·
>   `tests/Feature/Waiver/` · `app/Domain/Identity/**` · `app/Http/**/Api/V1/**` · `routes/api.php`)
>   más `docs/specs/waiver-probatorio.md` · `docs/specs/menores-a-cargo.md` ·
>   `docs/VERIFICACION-E2E-CAJON.md`.
>   ⚠️ **La BD local de esta máquina**: `waiver.mode = interno`, **v1→v9** publicadas en es/en/fr (las
>   de prueba llevan marcadores `[E2E-…]` en el texto), firmas de cuentas `e2e-waiver-*@jumpweb.test` y
>   `probe-*@jumpweb.test`, y **el anti-bot ENCENDIDO con las claves de prueba de Cloudflare** en
>   `settings` (`security.turnstile_site_key`/`_secret` = `1x000…AA`): es justo lo que necesita el ojo
>   del owner en `/registro`. La migración de `#183` está aplicada aquí; en el portátil, `migrate`
>   tras el pull.
>   ⚠️ **El techo del chunk del cajón** cedió por una FEATURE el 26/08 (221,5 → 226,5 KiB) y otra vez el
>   27 por la noche (226,5 → **235**, la zona de menores, `#199`; medido **234,41 KiB: quedan 0,59**), las
>   dos por decisión del owner. Lo siguiente que entre en el cajón —y
>   «menores a cargo» entra— lo mide `SidebarBundleBudgetTest` y casi seguro obliga a podar o a
>   decidir con el owner ANTES de escribir el componente.
>   ⚠️ Medido: ni `routes/api.php` ni `MeController` están en el `CRITICAL_RE` (son controles
>   NEGATIVOS en `CriticalPathGateTest`); **`WaiverSigner` SÍ** (`#174`): tocarlo exige
>   `waiver:verify-chain` y `VERIFY_CONC=1`.
>
> · **Agente B (el portátil) → SESIÓN CERRADA el 2026-08-27 a las 07:30. ✅ Nada a medias: la
>   extracción 4b ENTERA empujada y verde en seis tandas** (`#182` plan · `#184` A · `#185` B ·
>   `#186` C0 · `#187` C · `#188` D+E · `#189` F+G) **— el desmontaje de `ViewOrder`, de agente,
>   TERMINÓ**: 2.355 líneas y 50 métodos (de 5.280 y 98), sin ninguna orquestación de dinero/aforo;
>   todo vive en `Booking\Services\{OrderItemEditor,ItemEditPricing,OrderItemEventDataWriter,
>   OrderItemCanceller,OrderItemRefunder,ZoneDaySlotLock}` con `ItemActionOutcome`/`ItemRefundRequest`
>   como contratos. **74 mutaciones, 74 muerden; 15 tests nuevos son reglas que la página no podía
>   alcanzar.** Detalle sub-paso a sub-paso y cifras: spec **§9.6.1**.
>   ❗ **Lo que queda es del OWNER: la pasada de NAVEGADOR por las 10 acciones del panel** (spec
>   §6·5) — la suite no ve un formulario de Filament que deje de montarse. Hasta ese ✅ el desmontaje
>   sigue 🟦. ⚠️ En el portátil el panel de un pedido necesita las migraciones del waiver aplicadas
>   (hechas el 26/08 en A0); tras un pull, `migrate:status`.
>   ▶ **Siguiente trabajo de agente en este carril**: ninguno pendiente del desmontaje. Lo que hay
>   abierto está en «Lo que NO depende de nosotros» y en `DEUDA.md`; o lo que el owner diga.
>   Ficheros del carril B, para el reparto: `app/Filament/Resources/Orders/**` (⚠️ `#174` del waiver
>   entró en `Schemas/OrderInfolist.php`: es del A) · `app/Domain/Booking/Services/{OrderItemEditor,
>   ItemEditPricing,OrderItemEventDataWriter,OrderItemCanceller,OrderItemRefunder,ZoneDaySlotLock}.php`
>   · `app/Domain/Booking/Contracts/{ItemActionOutcome,ItemRefundRequest}.php` ·
>   `app/Console/Commands/VerifyPurchaseConcurrency.php` · `tests/Feature/Admin/Orders/**` ·
>   `tests/Feature/Architecture/{CriticalPathGateTest,OrderItemEditorSingleLockPointTest}.php` ·
>   `docs/specs/desmontar-view-order.md`.
>   ▶ **Para el agente del A**: `#174` entró en `app/Filament/Resources/Orders/Schemas/OrderInfolist.php`
>   (directorio del B). No choca con la 4b —no toco `Schemas/`—; sigue limitándote a ese fichero ahí.
>   Las 3 migraciones del waiver estaban PENDIENTES en el portátil (aplicadas en A0): no es un choque,
>   es esta máquina, que no había pasado por ellas.
>   Estado al retomar: el desmontaje de `ViewOrder` estaba **a UN paso del final**: paso 0 (`#170`)
>   · extracción 1 (`#172`, calendario→trait) · 2 (`#173`, presentación→trait) · 3 (`#176`, la
>   oferta de re-programación al DOMINIO con las tres reglas del owner y `VERIFY_CONC` en verde) ·
>   el instrumento de la 4 (`#177`, `--scenario=panel-edit`, **visto fallar**: 2 donde cabía 1).
>   `ViewOrder` en **3.907 líneas** (de 5.280, −26 %) y ya no compone ninguna oferta.
>   ▶ ❗ **POR DÓNDE RETOMA la siguiente sesión de ESTE carril (el portátil): la extracción 4b** —
>   mover la orquestación del dinero. **El handoff está sub-paso a sub-paso en la spec §9.5**
>   (orden A→H por riesgo creciente: puros → event_data → cambio de franja con el instrumento
>   corriendo → cancelación → refund batch → `executeItemEdit` el último → fronteras que no cambian
>   → `CRITICAL_RE` + verificadores + navegador del owner). Empezar por §4.3 (el mapa transaccional
>   REAL) y §9.5; commitear en local ANTES de cada mutación (§9.1).
>   ✅ **`[DECIDIDO owner]` (`#181`): con la 4b el desmontaje TERMINA** — las ~2.200 líneas de
>   composición Filament que queden NO se parten (§4.4 cerrada).
>   ❗ **La revisión adversarial del WAIVER NO es de este carril**: la lleva el A (fila de arriba),
>   junto con el guion headless — no se empieza dos veces (`CONVENCIONES §10·7`).
>   Ficheros del carril B: `app/Filament/Resources/Orders/**` (`ViewOrder.php` y lo que se extraiga
>   de él) · los ficheros de test que conducen `ViewOrder` (spec §1.5) ·
>   `docs/specs/desmontar-view-order.md` · `docs/DEUDA.md` (su ficha) · `docs/DECISIONES.md`
>   (número al empujar). ⚠️ El paso 3 entra en el `CRITICAL_RE`
>   (`SlotOffer`/`SlotAvailability`/`PackAvailability`): `VERIFY_CONC=1`.
>   ▶ Del cierre anterior de este carril sigue vigente: al cerrar una tanda que toque fixtures con
>   calendario, `bash scripts/audit-clock.sh` (está en `/cierre-sesion`; NO en el `pre-push`) — la
>   primera pasada cazó un fixture que iba a tumbar el gate de los DOS agentes seis días después.
> · 🆕 **Agente C (el TEMA) → tandas 1, 2a, 2b y la ELEVACIÓN, empujadas el 2026-08-27 (`#192`, `#193`, `#194`, `#195`).**
>   Hay un **TERCER carril**, por encargo del owner: la capa de tema
>   (`specs/tema-por-instalacion.md`). La tanda 1 —las dos superficies como ámbito, `--sheet`, los dos
>   grises, los tintes, 21 radios y las fuentes por instalación— está hecha, verificada y en `main`.
>   La **2a** —la escala de canto, la ley del motivo cuadrado, el anillo de foco y la tira del pie—
>   también (§10 de la spec).
>   ▶ **Ficheros de este carril**: `public/css/*.css` · `resources/views/components/layout.blade.php`
>   y `focused-layout.blade.php` · `resources/views/components/site/footer.blade.php` ·
>   `resources/views/errors/maintenance.blade.php` ·
>   `config/theme.php` · `app/Domain/Content/Services/ThemeFonts.php` ·
>   `tests/Feature/Architecture/{SurfaceScopeTest,ShapeScaleTest}.php` · `tests/Feature/Theme/**` ·
>   `docs/specs/tema-por-instalacion.md` · `docs/INSTALACION-CLIENTE.md`.
>
>   ❗❗ **LO MÁS IMPORTANTE QUE ESTE CARRIL APRENDIÓ EL 27, y afecta a cualquiera que toque el canvas:**
>   **la copia local de `mockup_playjumppark/` CADUCA sin avisar.** La del 27 a las 07:30 ya no valía
>   a las 11:00: `Landing PJP Modos` con **386 líneas de diff** (el hero gana una tira de colores; la
>   sección de entradas pierde su fondo cian y sus goterones) y `Colores de Marca PJP` también movido.
>   ▶ **`DesignSync · list_files` + diff ANTES de implementar nada desde ahí.** Una copia vieja se lee
>   igual de bien que una fresca.
>
>   ❗❗ **Y la premisa de la spec del tema §1.2 CADUCÓ**: la regla «el sistema alterna dos superficies,
>   nunca dos papeles seguidos» **ya no existe**. El hallazgo `S-00` del owner —severidad Alta,
>   Aplicado— la sustituye por **papel continuo de arriba abajo, y el contraste lo dan las TARJETAS**.
>   Verificado en el canvas: **cero** fondos a sangre en los 218 KB del mockup (antes había tres).
>   ▶ **Esto NO tira la tanda 1: la hace más útil.** `[data-surface]` es un selector de atributo, no
>   está atado a `.section`, así que sirve igual para una tarjeta oscura dentro de una sección clara.
>
>   ⚠️ **Y una que los otros dos carriles tienen que saber**: la escala de radios GANÓ dos escalones
>   (`--r-xs: 5px`, `--r-md: 10px`) y `ShapeScaleTest` **prohíbe escribir un canto en literal**. Si tu
>   tanda mete un `border-radius: 12px` a mano, el gate muerde. Usa un token, o justifica en qué
>   familia cae (motivo cuadrado / dibujo) — **las dos listas solo encogen**.
>
> ❗❗❗ **PARA LOS TRES CARRILES, Y ES LO MÁS IMPORTANTE DE ESTA SESIÓN: `docs-check.sh` PUEDE DARTE
> UN FALSO VERDE.** Medido el 2026-08-27: el gate daba **✓** en la shell del agente y **✗** con el
> `grep` real, y así se coló en `origin/main` una doc rota desde el **26/08** —37 citas
> `fichero.php:línea` que `CONVENCIONES §4` prohíbe—. La causa: **en la shell de trabajo `grep` es
> una FUNCIÓN de bash** interpuesta por el harness, no `/usr/bin/grep` (`declare -F grep` lo
> confirma). El `pre-push` **sí** usa el binario, así que el gate acaba mordiendo — pero te muerde
> cuando ya has hecho el trabajo, y por deuda que puede no ser tuya.
> ▶ **La regla, hasta que se arregle**: `docs-check` **solo cuenta con el binario real** —
> `env -i PATH=/usr/bin:/bin bash scripts/docs-check.sh` —, o déjaselo al `pre-push`.
> ▶ **Las 37 citas ya están convertidas a símbolo** (`#193`, con permiso del owner: son ficheros del
> carril A). ⏳ **Lo que sigue abierto es que el gate pueda mentir**, y tiene ficha **Alta** en
> `DEUDA.md`. ▶ Es el **quinto** instrumento ciego de este repo y **el primero que era el propio
> GATE**: los otros cuatro daban un inventario incompleto; éste daba **permiso**.
>
>   ❗❗ **TRES cosas que los otros carriles necesitan saber, porque tocan terreno compartido:**
>   1. ⚠️ **`SidebarTokenBudgetTest::MAX_RAW_COLOURS` bajó de 5 a 3.** El cajón comparte `site.css`,
>      así que si tu tanda mete un color crudo ahí, el trinquete muerde antes que antes. Los alfa
>      salen con `color-mix(in srgb, var(--fg) X%, transparent)`, y el blanco de una tarjeta ya tiene
>      token: **`--sheet`**.
>   2. ⚠️ **`--line`/`--line-strong` ya NO son `rgba(20,19,15,α)`**: derivan de `--fg`. Si copias una
>      línea de otro sitio, cópiala con `var()`, no con el literal — `RawColourIsNotATokenTest` lo caza.
>   3. ⚠️ **Existe `[data-surface="ink"|"paper"]`** y re-escopa siete tokens. **Todavía no lo usa
>      ninguna sección** (eso es la tanda 2), pero si tu componente pinta un color de superficie a
>      mano en vez de por token, dejará de seguir a su sección el día que la haya.
>
>   ⚠️ **Y una que despista**: `mockup_playjumppark/` (el canvas del 2.º cliente) está **gitignorada y
>   excluida del `rsync`** (`DECISIONES #1`), así que **en tu máquina no existe** aunque la doc la
>   cite. La receta para regenerarla con el MCP `DesignSync` está en la **§1 de la spec del tema**.
>   ❗❗ **El armazón está COMPLETO salvo el MENÚ EN MÓVIL**, que espera el artboard del owner. El
>   **CTA doble** de la barra inferior ya está (`#205`), y con él el idioma salió del pie —con
>   `<noscript>` de suelo— y el menú ganó su eslogan. Detalle de escritorio: 2c·0 (`#200`), 2c·1 (`#201`), 2c·2 (`#203`) y
>   2c·3 (`#204`) en el árbol y verdes — la red, 66 reglas de CSS fuera, **el menú a pantalla
>   completa**, **la barra DISUELTA en dos racimos**, el salto al contenido en las 12 y la cuenta en
>   icono con su punto de aviso. ▶ **Lo que queda de la 2c es la 2c·4, el MÓVIL, y la bloquea el
>   ARTBOARD DEL OWNER**: hasta que exista, el cajón lateral sigue intacto por debajo de 1080 px.
>   ▶ **Siguiente de agente en este carril si el owner no sube el artboard**: la tanda **2d, el
>   MOVIMIENTO** (237 declaraciones, 48 duraciones, 20 curvas; el sistema declara 7 y 4).
>   ▶ **Contestación al carril A (2026-08-27, noche)**: recibido el aviso de la tanda 4. **La 2c·2 NO
>   ha tocado `layout.blade.php`** —ni lo tocará la 2c·3—, así que no hay choque. Lo que sí ha
>   cambiado el carril C y conviene que sepas: `nav.blade.php` y `menu.blade.php` (el armazón entero),
>   `public/css/{site,landing}.css`, `resources/js/app.js` (en el componente `landing`, **`mobileOpen`
>   se llama ahora `menuOpen` y `trapMobile`, `trapMenu`**) y **las 12 vistas públicas, que ganan
>   `id="main"`**. Si tu U2 toca alguna de esas, `git pull --rebase` antes.
>   ▶ Contexto de aquella spec, que **YA ESTABA ESCRITA**:
>   **`specs/armazon-y-menu.md`** (2026-08-27 por la tarde), con **cuatro decisiones del owner
>   tomadas** y **seis pendientes**. `[DECIDIDO owner]`: la barra fija se retira en las **12** vistas
>   y la sustituyen **dos racimos flotantes + un menú a pantalla completa**; la lista del menú es
>   **PLANA** y la sigue mandando la BD; con sesión, icono de cuenta con **punto naranja/verde**;
>   **el MÓVIL lo guía el owner con un artboard** y hasta que exista no se empieza la 2c·4.
>   ▶ Medido: **194 reglas distintas · 733 declaraciones** de CSS en 7 familias, **31 aserciones**
>   en 4 ficheros, y **33 reglas (el 17 %) MUERTAS**. ⚠️ **La primera cifra publicada —«232 · 819»,
>   «41 muertas»— era FALSA y se corrigió al ejecutar**: sumaba COINCIDENCIAS, no reglas (una regla
>   que cita dos familias contaba dos veces). ✅ **La tanda 2c·0 está HECHA**: no cambia un píxel —
>   construye la red que el cajón móvil no tenía y retira las 33 reglas muertas. ⚠️ **Y el menú del mockup
>   trae tres defectos que se importan solos** (spec §1.7): deja sus enlaces en el orden de
>   tabulación estando cerrado, y el hallazgo `M-05` del propio cliente **choca con `--shadow-float`,
>   que `#196` creó ese mismo día**. ⚠️ **La auditoría del cliente EXCLUYE el menú y el logotipo**:
>   es la misma trampa que con el hero. Necesita el OJO del owner, no solo el gate.
> ▶ Protocolo de los carriles: **`CONVENCIONES §10`**.
> ⚠️⚠️ **El número de `DECISIONES.md` se elige mirando el REMOTO, y NO BASTA con mirarlo al empezar.**
> Ha colisionado **NUEVE** veces en dos días: `#142` duplicado · `#148` (el agente A renumeró al
> fusionar) · los del agente B, que fueron `#149`/`#150` → `#152`/`#153` → `#154` → **`#156`/`#157`**
> porque el agente A empujó **seis veces** mientras se escribían · `#158`, tomado mientras se escribía
> esa tanda · y el 2026-08-26 otra vez: `#163` se lo llevó la tanda 3a del waiver y el agente B tuvo
> que renumerar a `#164` **con el número ya escrito dentro de dos ficheros**.
> ❗ **La regla, corregida por el precio pagado**: el número **no se fija al escribir, se fija al
> EMPUJAR** — se vuelve a mirar el remoto justo antes del push. **Corolario: empujar PRONTO.**
> ⚠️ **Y la DÉCIMA, el 26/08 por la tarde, con la regla aplicada**: el carril A miró el remoto (`#167`),
> escribió `#168`, y en los veinte minutos hasta el push el portátil empujó SU `#168`. El rebase chocó
> en `ESTADO.md` y `DECISIONES.md` y hubo que renumerar a `#169` en ocho ficheros. ▶ Lo que ahorra
> tiempo: renumerar **solo las líneas AÑADIDAS** (`git diff HEAD` filtra las del otro) con un script de
> diez líneas, y **no confiar en «El último usado»** del fichero: mirar `origin/main` en el mismo
> comando que empuja.
> ❗❗ **Y el 2026-08-25 el precio dejó de ser solo el número**: los DOS agentes arreglaron **el mismo
> defecto** (el desglose EN/FR en crudo) **en paralelo, sin saberlo**. Se salvó la mitad que no
> coincidía —la guarda— y se tiró el resto. **Antes de abrir una ficha de `DEUDA.md`, mira si el otro
> la tiene abierta**: el reparto por carriles no basta cuando una ficha cae en la frontera.
> El último usado es **`#205`**.
>
> ❗ **LO PRIMERO que es de DINERO: los CUATRO defectos del cambio de precio (`#146`) están
> CERRADOS** (`#149`, `#150`) y el pack CON señal quedó MEDIDO. La peor ficha derivada —el pedido
> CANCELADO sin vía de reembolso— está **CERRADA** (`#152`, owner: la línea se abre para
> cancelados con deuda), y «¿devolver en el parque?» **DECIDIDO** (modo manual, sin canal nuevo).
> Quedan TRES fichas menores en `DEUDA.md`. Punto **0.bis**.
>
> ✅ **Lo segundo quedó DECIDIDO por el owner** (`#151`): el consumo que `#148` midió —una fiesta
> consume plazas de su franja— **es CORRECTO**. La regla: **la independencia de cupos se hace POR
> ZONA** (cumpleaños en la suya; el futuro «excursiones de colegio» tendrá la suya). Detalle en
> el punto **1.bis** de «Lo que está ABIERTO».
>
> ▶ **Landing y TEMA — son dos cosas distintas y esta línea las mezclaba.**
> · **El TEMA ya no está parado**: `specs/tema-por-instalacion.md`, **tanda 1 en `main`** (`#192`).
>   El sistema visual del 2.º cliente está **cerrado y auditado** por el owner; lo que él sigue
>   terminando (27/08 por la noche) es el diseño **de las secciones** y el mockup del layout de una
>   página nueva — o sea, lo que necesita la tanda **3**, no la 2.
> · **El CONTENIDO sí sigue parado**: tanda B de `landing-white-label.md` (`testimonials`, el copy al
>   CMS). Su tanda A está cerrada (`#138`→`#143`).
> ⚠️ **«Lo medido del mockup viejo sobre COLOR está caducado» sigue siendo cierto, pero se quedó
> corto**: medido el 27/08, son **10 de los 18 artboards** los que están sin migrar —entre ellos los
> de logotipo, menú y hero—, y **los mismos elementos están rehechos con el sistema vigente dentro de
> `Landing PJP Modos`**. De los sin migrar se saca la FORMA, nunca el color (spec del tema §1).
> Redis ya no bloquea nada (`#137`) y el desglose de dinero está CERRADO (`#127`→`#134`) — **cerrado
> el DESGLOSE, no el cambio de precio: eso es `#146`**.
>
> ⚠️ **Y este documento ADELGAZÓ el 2026-08-25, de 824 líneas a menos de la mitad.** Se retiró el
> índice de la Fase 4 —83 líneas que duplicaban el tracker de una fase CERRADA—, se movió el mapa del
> cajón a `specs/sidebar-spa.md` §8 y se borró el histórico de deltas de tests, que ya vive en cada
> `DECISIONES`. `CONVENCIONES` dice que esta foto **resume** el tracker y nunca lo contradice: con 824
> líneas eso era imposible de sostener, y de hecho **había contradicciones vivas** —afirmaba que la
> Fase 5 seguía bloqueada por falta de Redis horas después de haberlo activado y verificado—.

## ▶ Dónde estamos

**Fase 0 ✅ · 1 ✅ · 2 ✅ · 3 (API v1) ✅ · 4 (sidebar SPA) ✅ · 6 🟦 (el waiver: CÓDIGO COMPLETO, revisado
dos veces y con su guion recorrido en headless con la conducta definitiva —`#183`—; espera SOLO al owner ·
**menores a cargo: tanda 1 en el árbol, `#191`**; la 2 espera cinco decisiones del owner, spec §9.5)** — el detalle paso a paso de cada una está en `00-REFACTOR.md`, que es el tracker. Aquí
solo la foto.

🟩 **CERRADO y sin nada pendiente:** el cajón SPA como motor único con `Purchase.php` retirado
(`#111`, `#112`) · el área de cliente entera, incluidas la auth y `account-context` (`#66`, `#120`,
`#122`, `#123`) · «Mis reservas» por reserva (`#126`) · **el desglose de dinero del cliente**, las tres
tandas y los cuatro defectos de lectura (`#127`→`#134`, `specs/desglose-dinero-cliente.md`).
⚠️ **No se resume aquí lo que pasó en cada uno**: está en el tracker y en su decisión. Repetirlo en la
foto viva es crear una segunda verdad que envejece sola — ya pasó con la revisión de staging y con el
contador de tests JS.

🟦 **EN CURSO: la landing white-label** (`specs/landing-white-label.md`, `#136`). Nace del mockup del
**segundo cliente**. La línea: **data-driven el DATO, no la PÁGINA**.
▶ ✅ **Tanda A CERRADA** (`#138`→`#143`): el acento de zona ya no viaja por el nombre de la clase, la
paleta del primer cliente sale del producto, el icono por producto ya no es un booleano, **los 144
literales que repetían un token existente pasan a `var()`/`color-mix`**, el **spinner es sustituible
por instalación** y —lo que faltaba para que todo lo anterior sirviera— **existe el hueco por donde
entra el paquete de tema del cliente**.
⚠️ **Lo que la tanda A enseñó y no se puede no saber**: tres afirmaciones de la propia spec eran
falsas y las tres se creyeron hasta medirlas. La última (`#143` §8): **el «76 colores en crudo» salió
de un `grep` línea a línea que no veía ni los `rgba()` ni los valores multilínea — y el primer
instrumento que se escribió para corregirlo tenía EL MISMO defecto.** Eran 234, y en las 12 que
faltaban estaban las dos fugas de marca del hero.
▶ 🟦 **Tanda B EN CURSO (1 de 4)**: el «0 m²» hecho (`#144`); `park_stats` **descartada con su medida**
(`[DECIDIDO owner]`), y quedan `testimonials`, el copy y la landing del 2º cliente.
⏸️ **Y B está PARADA a la espera del DISEÑO**: el owner rehace el sistema visual. Ver «Próximo paso».
▶ **C** (servicios como producto real) sin empezar. ⚠️ **Toca AFORO y PAY y necesita spec propia.**
⏸️ **APARCADO por el owner**: zonas y cupos se quedan como están hasta ver cómo se comportan las
reservas de packs distintos con gente real (`#139`).

✅ **Redis: requisito DURO y solo para CACHÉ** (`#137`), activado y verificado en staging. Sus dos
obligaciones —el pase de Redsys fuera de la caché y Redis en el stack local y en la suite— están
**hechas**. Detalle de la máquina en `ENTORNOS.md` §4.

✅ **STAGING SIRVE `7776370`** desde el 2026-08-25 (cierre del agente A) — trae la línea entera del
cambio de precio (`#149`→`#155`: el importe elegido en «Reembolsar», la reconstrucción por precio
original, cancelados con deuda reembolsables, etiquetas EN/FR y el email de la bajada) más el
verificador de aforo de 5 escenarios del agente B. **Sin migraciones nuevas** («Nothing to
migrate» — las tres de la ola anterior ya estaban).
Auto-verificado por el script: `/up` y `/` en 200, guarda del `robots.txt`,
`redsys_environment = 'test'`, 0 migraciones pendientes, 0 `failed_jobs`, 0 jobs varados, 5 tareas
registradas y 1.420 franjas. Canal: `scripts/deploy.sh`, dry-run por defecto.
⚠️ **Lo de esta ola es PANEL y EMAIL**: no hay nada nuevo que ver en la web pública de staging; el
modal nuevo de «Reembolsar» se prueba con login de admin (y la BD de staging tiene sus 6 pedidos
de siempre, no las sondas locales).
▶ El histórico del salto anterior (`6437c48`, con `#145` verificado sobre `R-S9XDYB`) queda en
`DECISIONES` y en el tracker.
⚠️ **Lo que hay que recordar del canal**: los assets se construyen AQUÍ y se suben compilados —en
staging no hay node/npm— y el `.env` **nunca viaja**: se lee y se valida.
⚠️ **El único aviso del despliegue**: el script no ve ningún demonio cron, así que el crontab instalado
puede no ejecutarse nunca. Se comprueba en el panel del hosting (`#115`). No es nuevo.
❗ **LA REGLA QUE ESTA LÍNEA PAGÓ DOS VECES: la revisión NO se copia, se MIDE.** Llegó a decir «no hay
diferencia de código» con 68 ficheros de diferencia. Antes de creerte lo de arriba:
`git log --oneline e551851..HEAD` y
`git diff --stat e551851..HEAD -- . ':(exclude)docs' ':(exclude)*.md'`, sustituyendo `e551851` por lo
que sirva staging de verdad.

- **El único «PHPUnit notice» de la suite está IDENTIFICADO y RETIRADO** (cierre del carril A, 2026-08-27):
  `RequiresStaffOrAdminTest::test_can_access_panel_mirrors_middleware` hacía `createMock(Panel::class)`
  sin expectativas y PHPUnit 12 lo avisa; es `createStub`. Se encontró bisecando por carpetas con
  `artisan test --parallel <ruta>` (⚠️ con una ruta el resumen es el de PHPUnit, «OK (N tests…)», no
  «Tests: N») y se confirmó con `vendor/bin/phpunit --display-all-issues` (`--display-notices` NO enseña
  los notices de PHPUnit: son `--display-phpunit-notices`). El carril B ya no tiene que hacerlo en A0.
  ⚠️ **Y retirarlo dejó CIEGO al `pre-push`**: PHPUnit resume «Tests: N, Assertions: M…» solo cuando hay
  issues y «OK (N tests, M assertions)» cuando no; el hook solo entendía la primera forma y llevaba
  meses leyendo el contador gracias al notice. Desde este cierre lee las dos (fail-closed intacto).
- Suite **3127 en verde** (17.991 aserciones, `--parallel` **~45 s** medidos el 2026-08-28 de
  madrugada sobre el árbol FUSIONADO de los dos carriles, en la máquina del carril C) ·
  ▶ **+6 tests PHP en el último corte** (carril C, armazón · tanda **2c·4a**, el CTA doble de
  móvil): que la barra sea un par con destinos distintos, que la mitad colapsada **diga qué hace
  AHORA** y no a dónde lleva, que **las dos funcionen sin JavaScript** con una sola pulsación y con
  el nombre servido correcto, que el reparto por defecto sea comprar, que el idioma tenga **un solo
  sitio y suelo sin JS**, y que el eslogan del menú salga de la clave del hero. **9 mutaciones, las
  9 muerden.**
  ⚠️ **Este contador se fusionó a mano el 2026-08-28**: los dos carriles cortaron a la vez y ninguna
  de las dos cifras previas —3.121 del A, 3.096 del C— valía para el árbol conjunto. **Se volvió a
  MEDIR, no a sumar** — y menos mal: sumar habría dado 3.127 tests (acierta) y **17.991 aserciones
  solo por casualidad**, porque la fusión también movió aserciones dentro de guardas que cuentan por
  regla.
  ⚠️ **Y la trampa del carril A mordió al carril C en cuanto fusionó**: `npm run build` sin
  `build:ssr` desfasa el renderizador SSR y tumba **30 casos** del contrato de árbol. Estaba escrita
  dos párrafos más abajo, se leyó **después** de pagarla. El hook los encadena a propósito. Antes:
  ▶ Y el corte del carril A, con su medida propia (3.121 · 17.936 en su árbol):
  ▶ **+30 tests PHP y +1 JS** (carril A, menores a cargo · tanda 4 · **U1**, el
  servidor — spec §9.9.7): `DependentAssignerTest` (17), `OrdersDependentAssignmentTest` (8),
  +1 `DependentRegistryTest`, +3 `DependentPrivacyTest`, +1 `ModuleContractsTest` (el doble de
  `CheckoutLines` con el orden invertido); `npm run test:js` **733 → 734** (`sanitizeLine` con
  `dependent_ids`). **9 mutaciones, las 9 muerden** (anti-IDOR · firma · minoría en la visita ·
  idempotencia · correlación · `anonymize()` · referencia · el `check()` del controlador · `event-data`).
  Los SEIS escenarios de `purchase:verify-oversell` y `redsys:verify-concurrency` con 16 procesos ✓ (no
  ejercitan la asignación: control de no-regresión por el `CRITICAL_RE`). Sonda HTTP de 10 pasos ✓.
  Chunk 234,43 → **234,70 KiB** (techo 235). `docs-check` **34 modelos · 82 migraciones**.
  ⚠️ **Dos trampas de test pagadas**: el tercer argumento de `assertDatabaseHas` es la CONEXIÓN, no un
  mensaje («Database connection [mensaje] not configured»); y `assertJsonPath` no resuelve claves con
  puntos (`items.0.dependent_ids.1`) — se lee `json('error.fields')` y se compara la clave literal. Antes:
  ▶ **+1 test PHP y +1 JS en el corte anterior** (carril A, menores a cargo · tanda 4 · **U0**, la purga
  de la cesta): `SidebarMountTest::test_the_engine_seeds_the_cart_owner_from_the_boot_before_mounting`
  —guarda ESTRUCTURAL sobre `index.js`, porque la suite no arranca el motor— con **2 mutaciones, las 2
  muerden** (sin siembra · siembra después de montar), y el caso «sembrar el dueño ANTES de restaurar»
  en `stores/cart.test.js`: `npm run test:js` **732 → 733**. Chunk 234,41 → **234,43 KiB** (techo 235).
  ⚠️ **La primera versión de la guarda salió ROJA con el fuente correcto**: `strpos` casó `app.mount(el)`
  con una mención en un comentario anterior a la llamada — limpia comentarios antes de buscar (la
  lección de `#200`). ⚠️ **Y 30 casos del contrato de árbol salieron rojos por correr `npm run build`
  sin `build:ssr`**: el renderizador SSR quedó desfasado; el hook los encadena a propósito. ⚠️ **Y el
  push chocó DOS veces con el carril C** (`#203`, `#204`) mientras el gate corría (~4 min cada vez): el
  precio de «empujar pronto» con dos carriles a la vez, pagado esta noche. Antes:
  ▶ **+4 tests PHP en el corte anterior** (carril C, armazón · tanda **2c·3**, la cuenta): que el
  botón conserve su nombre accesible **en texto** —era el saludo visible, y al quedarse en icono
  solo vive en el `aria-label`—, que el texto visible no vuelva sin ser prefijo del nombre, que el
  punto use el token de aviso, que el glifo del alta siga al DESTINO (trámite externo vs crear
  cuenta) y que el armazón **no dibuje glifos en línea**. **8 mutaciones, las 8 muerden.** Antes:
  ▶ **+4 tests PHP** (carril C, armazón · tanda **2c·2**, los dos racimos):
  el salto al contenido en las 12 y aterrizando, que **todos** los `<main>` lleven el ancla —no solo
  el primero, que es el defecto que se cazó—, que la barra se haya disuelto de verdad (sin fondo,
  sin desenfoque, sin línea, y con los clics renunciados en el contenedor y recuperados en los
  racimos) y el cableado de la coreografía. **+8 tests JS**: `nav-choreography.test.js`, porque la
  lógica del scroll salió de `app.js`, que no lo cubre ningún test. `npm run test:js` **724 → 732**.
  **12 mutaciones, las 12 muerden.** Antes:
  ▶ **+5 tests PHP** (carril C, armazón · tanda **2c·1**, el menú a pantalla completa): **+7** en `ArmazonContractTest` —el overlay accesible del menú, que declare superficie

  de tinta, que todo enlace lo cierre, que los números sean decoración, el orden del parque, el
  orden de los servicios y que la barra ya NO lleve destinos— y **−2 en `HomePageTest`**, que **no
  se retiraron: se MUDARON**. Su sujeto —los dos desplegables— murió, pero lo que comprobaban de
  verdad seguía vivo y nadie más lo fijaba: las etiquetas, las anclas y **el ORDEN**.
  **16 mutaciones, las 16 muerden**, y la que más importa es la del defecto del mockup: quitarle al
  menú el `visibility:hidden` de cerrado. `npm run test:js` 724/724. Antes:
  ▶ **+15 tests PHP** (carril C, armazón · tanda **2c·0**): `ArmazonContractTest`
  (10, la red de conducta del armazón — **y el cajón móvil ESTRENA test: no lo tocaba ninguno**) y
  `ArmazonCssHasNoOrphansTest` (5, el trinquete de CSS sin consumidor). **15 mutaciones, las 15
  muerden**, con control positivo.
  ⚠️⚠️ **Y las aserciones suben SOLO 1 con 15 tests nuevos: 17.694 → 17.695. No es un error, y
  cuadra a la aserción.** Los tres guardas de CSS aseveran **por regla**, así que al retirar las 33
  reglas muertas pierden **86** aserciones (4.025 → 3.939, medido stasheando el cambio y volviendo a
  correr); los tests nuevos aportan **87**. `17 694 − 86 + 87 = 17 695`. ▶ **Un contador que se mueve
  menos de lo que esperabas no es un contador roto hasta que no puedes explicar la diferencia.**
  ⚠️ **Lo que enseñó el arnés de mutación de esta tanda, y afecta a cualquiera que escriba uno**:
  `shutil.copy2` restaura el fuente **con su mtime original**, que queda más viejo que la vista que
  Blade compiló durante la mutación → **el fuente vuelve a estar sano y la aplicación sigue sirviendo
  la versión mutada**. Dos tests salieron rojos por mutaciones ya revertidas, y —peor— una mutación
  puede apuntarse un tanto que ha ganado el residuo de la anterior. **El arnés hace `view:clear` antes
  de cada medición**; sin eso su 15/15 no valía. Antes:
  ▶ **+0 tests PHP y +3 aserciones, y +22 JS** (`#199`, menores a cargo · tanda 3,
  el cajón): `npm run test:js` **702 → 724** (el módulo plano `account/dependents.js` 9, el store 13);
  las tres aserciones son la poda del subgrupo `dependents` en `SidebarMountTest`. Chunk del cajón
  **234,41 KiB (techo 235)**, payload con sesión **7.602 B (techo 7.700)**, los dos subidos por FEATURE
  con su párrafo. Antes:
  ▶ **+15 tests PHP** (`#198`, menores a cargo · tanda 2, la firma del menor):
  `MeDependentWaiverTest` (9, contra el contrato), la cadena por sujeto y ajeno/retirado/adulto en
  `WaiverSignatureChainTest` (+2), la retención del menor desde los 18 y **NUC-3 como guarda** en
  `WaiverRetentionTest` (+2), el PDF y el registro del panel con el nombre (+2). **5 mutaciones, las 5
  muerden · `waiver:verify-chain` 8/16 PASA y visto FALLAR sin el lock.** Antes:
  ▶ **+9 tests PHP** (`#193`, capa de tema · tanda **2a**): `ShapeScaleTest`.
  **13 mutaciones, las 13 muerden** — pero solo después de arreglar el arnés. ⚠️⚠️ **El arnés de
  mutación dio «0 de 12 muerden» con el test funcionando perfectamente**: decidía con
  `grep -q "FAILED\|failed"` y aquí `grep` es **ugrep en ERE**, donde `\|` es un pipe LITERAL — buscaba
  la cadena `FAILED|failed` y no casaba jamás. Se decide por **código de salida** y lleva **control
  positivo** (el test tiene que estar verde antes de mutar). ▶ **Cuando un instrumento dice que NADA
  funciona, la primera hipótesis es el instrumento**: un arnés que nunca detecta el fallo no es
  inofensivo, **certifica** — habría firmado que 12 aserciones eran decorativas.
  ▶ Antes, **+14** (`#192`, tanda 1): `SurfaceScopeTest` (8) y
  `ThemeFontsTest` (6). **13 mutaciones, las 13 muerden.** ⚠️ Y una de ellas destapó que **la guarda de
  la guarda había nacido ciega**: aseveraba un umbral de recuento sobre `:root` y no detectaba que el
  parser se quedara sin la mitad del corpus, porque `site.css` declara el suyo. Se asevera por NOMBRE.
  ⚠️ El trinquete `SidebarTokenBudgetTest::MAX_RAW_COLOURS` bajó **5 → 4 → 3**, avisando él las dos
  veces. Antes:
  ▶ **+34 tests PHP** (`#191`, menores a cargo · tanda 1): `DependentRegistryTest`
  (15: edad derivada y jamás persistida, el 18.º cumpleaños cruzando UTC↔Madrid, solo menores, tope de
  servidor desde el ajuste, quitar = desvincular/borrar, anti-IDOR), `DependentPrivacyTest` (5:
  `anonymize()`, export, poda, purga de go-live), `MeDependentsTest` (11, contra el contrato) y
  `DependentsCapSettingTest` (3, el tope en Ajustes). **7 mutaciones, las 7 muerden.** Antes:
  ▶ **+5 tests PHP** (`#189`, F+G de la 4b): la huella propia excluida para el PACK
  en `edit()`, «con crédito NO hay marcador», los `event_data` en el mismo guardado que una edición
  con dinero, y la guarda de arquitectura del punto único de lock (2 casos). Antes:
  ▶ **+3 tests PHP** (`#188`, D+E de la 4b): los permisos re-exigidos en
  `OrderItemCanceller`/`OrderItemRefunder` (inalcanzables desde la página) y las tres guardas de E
  con su razón estructurada (selección vacía, ítem ajeno, principal bloqueado) — cinco mutaciones
  que salían verdes. Antes:
  ▶ **+3 tests PHP** (`#187`, C de la 4b): la huella propia excluida al mover una
  ENTRADA que se solapa a sí misma, el destino LLENO rechazado bajo el lock con su audit, y el
  rechazo anidado de `event_data` auditado sin deshacer el cambio de franja — tres mutaciones que
  salían verdes. Antes:
  ▶ **+0 tests, +6 aserciones** (`#186`, C0 de la 4b: `CriticalPathGateTest` vigila
  cuatro ficheros más — dos críticos, dos controles negativos). Antes:
  ▶ **+3 tests PHP en el último corte** (`#185`, sub-paso B de la 4b): tres reglas de
  `OrderItemEventDataWriter` que la página NO alcanza (obligatorios ausentes —Filament valida
  antes—, permiso re-exigido en el servicio, «sin cambios» sin `save`) probadas DIRECTAMENTE; sus
  mutaciones salían verdes y ahora muerden. Antes:
  ▶ **+1 test PHP** (`#184`, sub-paso A de la 4b): la regla de bloqueo
  per-invitado/grupo de los complementos (`addon_locked` por BLOQUEO, no por mínimo) no tenía test
  y la mutación salía verde; ahora lo tiene, con control negativo. Antes:
  ▶ **+9 tests PHP y +1 JS** (`#183`, la revisión de la tanda 4 aplicada): la firma
  pendiente lleva la UA de la aceptación y espera al commit; `pending` en el contrato; vigencia dentro
  del lock; NFD y blanco tras `[`; idiomas publicables; cuenta existente declarada; rama negativa del
  PDF; el cambio de correo firma la pendiente; el 422 de `accept_waiver` relee. **10 mutaciones, las 10
  muerden** · **guion completo en headless: **111/111 ✓, 0 desviaciones**** ·
  ▶ **+1 test PHP en el corte anterior** (`#180`, el texto del PDF): la comprobación del PDF se llama
  «interna» y dice su alcance, el PDF de mostrador dice de quién son los datos y la IP, y
  `WaiverChain` cruza cada firma con su versión (una versión alterada por debajo rompe la cadena).
  **3 mutaciones, las 3 muerden** ·
  ▶ **+4 tests PHP en el corte anterior** (`#179`, correo verificado para firmar): la firma del alta
  se aplaza a la verificación (y con el canal del alta), la pendiente caducada se descarta, sin
  verificar no se firma ni desde la cuenta (409), y la declarada en mostrador sí. **5 mutaciones, las
  5 muerden**; `waiver:verify-chain` 8/16 con la guarda ·
  ▶ **+6 tests PHP y +1 JS en el corte anterior** (`#178`, decisiones 4b/4c del waiver): la casilla del
  alta obligatoria en interno (422 sobre `accept_waiver`; opcional en externo e interno sin versión)
  y la casilla del alta manual (con ella firma declarada; sin ella nada, también sin email); JS
  **700 → 701**. **4 mutaciones, las 4 muerden**. ⚠️ El navegador cazó un **500 al abrir el modal**
  del alta manual con la suite en verde (`wire:partial`): ahora el texto del modal se prueba directo ·
  ▶ **+0 tests y +1 aserción en el corte anterior** (`#177`, el instrumento de la extracción 4): el
  inventario de `OversellVerifierCoversEveryQuotaTest` conoce el escenario `panel-edit` ·
  ▶ **+2 tests PHP en el corte anterior** (`#176`, extracción 3 del desmontaje): el del ancla del parque
  que CRUZA la frontera UTC↔Madrid (el caso que `AFORO-09` no tenía) y el de «horas sin aforo se
  ocultan», cuya regla existía desde el origen y su mutación salía VERDE — 4 mutaciones del
  servicio, las 4 muerden ·
  ▶ **+12 tests y +46 aserciones en el corte anterior** (`#174`, unidad 2 de la tanda 4 del waiver):
  el canal por guard (sesión + `Bearer basura` sigue siendo `web`; token real → `api`; alta sin
  sesión → `api`), la aceptación idempotente por versión (una firma, un consentimiento, ninguna
  auditoría de más), los tres marcadores de borrador + el aviso de palabras, el badge del pedido por
  `WaiverStatus` (sello sin registro, versión anterior, modo desactivado) y los throttles con prefijo.
  **7 mutaciones, las 7 muerden.** ⚠️ Tres tests y `waiver:verify-chain` construían la cadena
  re-firmando la misma versión: se corrigieron (versiones nuevas / un menor por proceso) ·
  ▶ **+1 test en el corte anterior** (`#172`, extracción 1 del desmontaje): `calendarGoToItemMonth`
  gana el test que no tenía ANTES de mudarse al Concern (mutación vista morder), y retirar el
  `use ManagesItemCalendar;` tumba 14 tests — la red cubre la extracción entera ·
  ▶ **+0 tests y +3 aserciones en el corte anterior** (`#170`, paso 0 del desmontaje de `ViewOrder`):
  los 3 tests por reflexión sobre métodos MUERTOS se sustituyeron 1:1 por 3 sobre la fuente viva
  del calendario, que aseveran más (**4 mutaciones, las 4 muerden**; spec §9.1) ·
  ▶ **+1 test en el corte anterior** (2026-08-26, tras `#166`, sin número: un fix con su guarda):
  `SeededSettingsAreSaveableTest` — lo que siembra `db:seed` tiene que poder guardarse desde Ajustes;
  el owner lo pilló en navegador (`DEUDA.md` · Baja: «Guardar» mudo por un `#` sembrado) ·
  ▶ **+0 tests PHP y +5 JS en el corte de la unidad 3 del waiver** (`#175`, el cajón): `npm run
  test:js` **695 → 700** (el store del waiver: id enseñado, relectura, `reread`; el store de auth: el
  422 del alta); **5 mutaciones, las 5 muerden**; chunk del cajón **226,21 KiB y el techo sube a
  226,5 por CORRECCIÓN** (ledger en `SidebarBundleBudgetTest`) ·
  ▶ **+0 tests PHP y +5 JS en el corte del 26/08 por la noche** (`#171`, el anti-bot del alta suelta):
  `npm run test:js` **690 → 695**; chunk del cajón 225,72 → 225,85 KiB (corrección, techo intacto) ·
  ▶ **+0 tests y +1 aserción en el corte anterior** (`#166`, la 3b del waiver): lo nuevo es JS —
  `npm run test:js` **671 → 690** (+19: módulo 6 · store 9 · `register.js` 4)— y la aserción es la
  lista exacta de `register` en `SidebarMountTest` ·
  ▶ **+25 en el corte anterior** (`#163`): `LegalWaiverTest` (5), `MeWaiverTest` (14: estado por modo,
  aceptar solo lo servido, `409` caducado / no interno, canal por autenticación, el PDF propio con
  IDOR y auditoría, y que el export NO lleva el registro probatorio), `AuthRegistrationTest` (+5: la
  casilla opt-in, el rechazo ANTES de crear la cuenta) y `MeAccountContextTest` (+1). **5 mutaciones,
  las 5 muerden** (spec §9.8). ⚠️ Y los avisos del alta se movieron a `api.register.*` porque en
  `account.register.*` sacaban de su techo a los DOS presupuestos del montaje del cajón.
  ▶ Antes, **+25** (`#161`): `WaiverProofPdfTest` (14: permiso propio, IDOR, auditoría,
  `no-store`, idioma del texto firmado, **el PDF no cambia al editar la página ni al publicar otra
  versión**, determinismo, la identidad copiada sobrevive a `anonymize()`, tres idiomas distintos),
  `WaiverProofActionTest` (6) y `PresentialWaiverDeclarationTest` (5). **5 mutaciones, las 5
  muerden** (spec §9.6). ⚠️ Y una trampa del arnés medida: el modal de Filament es un `wire:partial`
  y `assertSee` no lo ve tras `mountAction` (`TESTING.md`).
  ▶ Antes, **+47** (`#160`): los seis ficheros de `tests/Feature/Waiver/` — inmutabilidad
  de versiones, cadena de firmas (con la serialización canónica FIJADA como literal), retención tras
  `anonymize()` y poda con el reloj congelado, los tres modos, la puerta en interno y la acción de
  publicar. **5 mutaciones, las 5 muerden**; el verificador de cadena sobre MySQL, visto fallar sin el
  lock (spec §9.3).
  ▶ Antes, **+3** (`#159`): `AnonymizeCoversEveryUserColumnTest`, el **censo** de las 18
  columnas de `users`. Convierte en guarda la última frase de `RGPD-01` —«cualquier PII nueva debe
  añadirse aquí»—, que hasta hoy era una petición: **una columna nueva pone la suite en rojo hasta
  que alguien la declare**. Es simétrico (una conservada que empiece a purgarse cae igual) y lleva
  su guarda-de-la-guarda. **2 mutaciones, las 2 muerden**; `User.php` restaurado y comprobado por md5.
  ▶ Antes, **+4** (`#157`): `ClientMoneyLabelsAreTranslatedTest`, la **segunda** guarda
  del EN/FR. No repite a la de `#154`: añade la guarda-de-la-guarda, la prohibición del **mecanismo**
  (el helper compartido no puede volver a citar `admin.*`), el barrido ancho de todas las claves
  `tickets.*` del dominio con suelo declarado, y —lo que la separa— que **los tres idiomas digan cosas
  DISTINTAS**. ❗ **Medido**: con `lang/fr` relleno de castellano, la guarda de `#154` **pasa con 23
  verdes** y ésta cae. **2 mutaciones, las 2 muerden.**
  ⚠️ **Y las dos nacieron del MISMO defecto arreglado dos veces en paralelo** por los dos agentes sin
  saberlo (`#157`): se tiró el arreglo duplicado y se quedó lo que no coincidía.
  ▶ Antes, **+3** (`#155`): el email de una bajada cuenta el dinero — e2e con la
  línea renderizada, la variante absorbida y la guarda de idiomas. **3 mutaciones muerden.**
  ▶ Antes, **+1** (`#154`): las etiquetas del cargo de puerta que lee el CLIENTE
  viven en `tickets.*` con sus TRES idiomas — guarda `Lang::has(..., false)` + composición bajo
  `en`. **2 mutaciones muerden.**
  ▶ Antes, **+2** (`#153`): el modal del reembolso de PEDIDO nombra el importe
  exacto y, sin «también cancelar», señala la vía de los parciales. **2 mutaciones muerden.**
  ▶ Antes, **+2** (`#152`): el e2e del pedido CANCELADO con deuda reembolsado por
  línea hasta dejar el «pendiente de devolverte» a CERO, el candado del cancelado sin deuda y
  los dos banners. **2 mutaciones, las 2 muerden.**
  ▶ Antes, **+10**: `ItemPriceChangeReconstructionTest` (`#150`) — los CUATRO caminos de
  `#146` (bajar cantidad · bajar precio · las dos · cancelar tras bajada) más la cadena de ediciones,
  el pedido cancelado sin vía, las etiquetas y los toasts. **6 mutaciones, las 6 muerden.**
  ▶ Antes, **+8**: `RefundItemCustomAmountTest` (`#149`) — el escenario del owner de punta a punta
  (40 € → día de 30 € por el CALENDARIO → devolver exactamente 10) más las guardas del importe
  elegido en las TRES capas (form, handler, dominio bajo lock). **5 mutaciones, las 5 muerden.**
  ▶ Antes, **+3**: `PackConsumesEntrySeatsTest` (`#148`), que fija que **una fiesta SÍ consume
  asientos de entrada** (y una entrada NO consume cupo de fiestas), y **+4**:
  `OversellVerifierCoversEveryQuotaTest` (`#147`), la guarda de que el verificador de sobreventa
  **no encoja**. ⚠️ **Ninguno cubre la carrera**: eso exige MySQL y `pcntl_fork`, y vive en comando.
  ▶ Antes, **+14** con las guardas del registro legible de un pedido (`#145`).
  **724 tests JS** (`node --test`) · Pint limpio (935 ficheros) · `docs-check` verde ·
  ⚠️ **Los dos números de esta línea llevaban retraso y se re-MIDIERON el 2026-08-27**, no se
  dedujeron sumando: JS decía 671 (son **702**) y Pint 895 (son **931**). El contador de JS no
  tiene guarda —el de PHP sí— y por eso deriva: mídelo con `npm run test:js`, no lo estimes. ·
  `composer audit` y `npm audit` en **0** · `npm run build` y `build:ssr` OK.
  ⚠️ Sale con **1 `PHPUnit Notice`** que **NO es de ningún trabajo reciente**: viene de antes y es del
  runner (ver `TESTING.md`). No lo persigas creyéndolo nuevo.
  ✅ **El contador de PHP tiene GUARDA**: el `pre-push` compara lo que acaba de dar la suite con lo que
  declara esta línea y **corta si no cuadran** (`#116`). Antes derivó tres veces en un solo día.
  ⚠️ **El de JS NO la tiene**, y por eso llegó a llevar **22 cierres de retraso**: si dudas, mídelo con
  `npm run test:js` en vez de sumar deltas.
  ⚠️ **Éste es el ÚNICO sitio donde vive el contador**: duplicarlo en otro documento crea una copia que
  no guarda nadie.
  ⚠️ **Y puede BAJAR a propósito**: `/mi-cuenta/…` se llevó 63 casos y el modal de auth 42, ninguno por
  descuido —se midió por mutación cuáles cazaba también la API antes de borrar—. **Un contador que solo
  puede subir acaba premiando al test que no se retira.**
  ▶ El histórico de qué aportó cada corte vive en su entrada de `DECISIONES`, no aquí.

- ⚠️ **La suite NO está auditada contra la FECHA, y ya mordió DOS veces** (`DECISIONES #64`, `#97`):
  tres casos amanecieron rojos sin que nadie tocara nada, y el **2026-08-16 a las 00:02 de Madrid** el
  `pre-push` cayó con **1 fallo** en el cruce de medianoche; el reintento salió verde.
  ⚠️ **Y no se supo cuál era**: la salida del gate no se capturó y se perdió. **Si el `pre-push` cae,
  vuelca su salida a fichero antes de reintentar** — un rojo transitorio sin nombre no se puede
  arreglar. Están arreglados congelando el reloj, pero **nadie ha
  barrido el resto**. Si te encuentras un rojo que no viene de tu cambio, **guarda el árbol y prueba en
  el commit anterior antes de tocar nada** — es lo que separó el diagnóstico en minutos de una sesión
  perdida. Ficha en `DEUDA.md`.
- **El gate son SEIS pasos** —docs-check · Pint · `npm run build` · `npm run build:ssr` ·
  `npm run test:js` · suite—, y `PrePushGateTest` los vigila uno a uno, incluido que el build vaya
  ANTES que la suite (se añadió tras un fallo real: un manifest a 0 bytes tumbó la web entera).
- ⚠️ **`SidebarDomContractTest` compara contra un ARTEFACTO** (`storage/ssr/render-sidebar.js`). Tiene
  guarda contra bundle rancio (`#69`) porque un bundle viejo daba **verde falso**; ha saltado **tres
  veces en tres días** —la última tumbando sus 30 casos de golpe (`#78`)—. Si tocas un módulo del cajón,
  o lo mutas y lo restauras, `npm run build:ssr` **antes** de leer ningún resultado.
- **Auditorías de dependencias = verificación de CIERRE, no de instalación** (`#25`): el árbol npm pasó
  de 0 a 5 avisos en unas horas sin que el lock cambiara. Correrlas en cada cierre.
- **Los dos verificadores de concurrencia: VERDES sobre MySQL real** (2026-08-14, 8+8 workers).
  **La lista viva de lo que exige `VERIFY_CONC=1` es el `CRITICAL_RE` de `.githooks/pre-push`** — no se
  copia aquí para que no envejezca, y `CriticalPathGateTest` vigila que siga cubriendo lo que debe.
  ✅ **Y desde `#147` `purchase:verify-oversell` cubre los TRES aforos, no uno**: `--scenario=entry`
  (asientos) · `pack` (cupo de FIESTAS) · `pack-guests` (cupo de INVITADOS). Hasta el 2026-08-25 solo
  existía el primero, y el aforo de packs **no lo probaba nadie**. Medido sobre MySQL con 8 y 16
  procesos: **los tres aguantan**.
  ❗❗ **Y su verde vale porque el instrumento se vio FALLAR**: retirando el `lockForUpdate()` de
  `OrderCreator::lockSlots`, el mismo comando cazó **8 fiestas donde cabía 1** y **48 invitados donde
  caben 10**. Un verificador que nunca se ha visto fallar no ha demostrado que pueda.
  ⚠️ **Lo que sigue SIN medir y no se da por hecho**: el tramo multi-franja bajo concurrencia, la
  cesta MIXTA entrada+pack, y `prep_blocks_cupo` **activo** (el valor por defecto en producción).
  ✅ **Y desde `#141` los dos CONTADORES de aforo disparan el gate**: `SlotAvailability` y
  `PackAvailability` llevaban fuera desde el principio — el gate vigilaba a quien LLAMA y no a quien
  CUENTA. Verificado por mutación, y con `ProductAvailability` como control negativo declarado.
  ✅ **Y desde la tanda 3 el gate ya no deja fuera ninguna superficie de dinero** (`#120(u)`): el
  reintento web —el último que quedaba sin cubrir— se retiró con la página que lo servía, porque el
  cajón reintenta por `POST /api/v1/orders/{code}/payment`, que sí entra por el `CRITICAL_RE`. (La
  compra la había dejado antes `#112`, al retirar `Livewire\Tickets\Purchase`.)
- **Fase 2 dejó tres cosas que se usan al tocar código hoy** (el resto, en `specs/modulos-dominio.md`):
  `php scripts/module-deps.php [Clase…]` mide las dependencias INVISIBLES · *recibir* una entidad de
  otro módulo es costura de BD, *consultar* sus datos o *repetir* sus reglas exige contrato · las
  baselines del arch-test **solo encogen**.
- ✅ **El segundo objeto-dios está DESMONTADO** (`DECISIONES #119`, 2026-08-22). `Sidebar.vue` pasó de
  **614 líneas y 11 llamadas a la API** a **16 y 0**: el embudo vive en `sections/PurchaseSection.vue`
  y el estado, en `stores/` —la reorganización creó **nueve** y el área de cliente ha ido añadiendo
  los suyos, uno por dominio—. Lo siguen guardando `SidebarComponentBudgetTest`
  (techo por componente + excepción declarada que solo encoge) y una guarda de que **la raíz no vuelve
  a pintar pantallas**. ⚠️ **Las cifras vivas están en su `EXCEPTIONS`**, no aquí: copiarlas a este
  documento es drift en espera, y ya pasó una vez.
- ⚠️ **NOTA DE DESPLIEGUE permanente**: las migraciones corren **ANTES** de servir tráfico (el morphMap
  de Fase 2 es requisito) y hay que **drenar la cola + `queue:restart`** (los payloads serializados
  llevaban los FQCN viejos).

## ▶ Decisión de producto VIGENTE que enmarca todo lo demás

⚠️ **El cajón es el ÁREA DE CLIENTE, no el embudo de compra** (`DECISIONES #66`, owner). Toda la
gestión del cliente vive dentro del cajón: sus entradas y reservas, y las gestiones de cuenta.
✅ **De los tres sitios en que estaba repartido, quedan DOS**: las páginas `/mi-cuenta/…` se retiraron
(`#120(u)`) y el **modal de auth de la cabecera** sigue siendo la puerta de entrada de quien no tiene
sesión. Ése es el último trozo, y su ficha está en `DEUDA.md`.

- **El orden es dependencia, no preferencia**: **4.7** → **Turnstile** → **área de cliente**.
  ✅ **Turnstile ya no ata nada** (4.4b·2, 2026-08-20): el cajón monta su propio widget y la delegación
  en el modal de la cabecera **está retirada**. ⚠️ Pero el modal **no se retira aquí ni en `4.7·2b·3`**:
  vive en `layout.blade.php`, no en `purchase.blade.php`, y sigue siendo la puerta de auth de la web
  fuera del cajón. Retirarlo es trabajo del área de cliente.
- ✅ **El terreno ya está preparado** (`DECISIONES #119`, 2026-08-22), y lo que hay que saber es dónde
  NO meter la cuenta:
  · el grafo del embudo es `FUNNEL_TRANSITIONS` y está **cerrado con guarda**: colgar ahí una pantalla
    de cuenta pone el test en rojo. Un área de cliente **no es un embudo** — sus pantallas se navegan
    libremente— así que va con su propio modelo, no con el de la compra;
  · el embudo es una **sección** (`sections/PurchaseSection.vue`) y la raíz son 16 líneas que solo
    enrutan: **la cuenta entra al lado, no dentro**;
  · el estado de cada dominio ya tiene su store, así que una sección nueva pide el suyo y no necesita
    que la raíz le pase nada por props.
  ✅ **Y el modelo de navegación está DISEÑADO, VALIDADO por el owner y CONSTRUIDO** (`#120(d)`,
  `specs/area-cliente.md` §3.1): índice + zonas libres con pila de retorno, con su propio modelo y sin
  tocar el grafo del embudo. Añadir una zona es **una línea** en `ZONES` más su rótulo.
- ✅ **El servidor está COMPLETO para las cinco gestiones** (`#120(a)` lo midió endpoint por endpoint,
  y por eso el área fue en tandas): **leer** —`/auth/*`, `/me`, `/me/orders`, `/me/reservations`,
  `/me/reservation-eligibility` y el post-form por firma, desde Fase 3— y **gestionar**: contraseña,
  sesiones, perfil (`PUT /me/password`, `POST /me/sessions/revoke-others`, `PATCH /me` y los dos del
  correo pendiente) y, desde el paso 8, los **dos derechos RGPD** (`DELETE /me` y `GET /me/export`).
  ⚠️ **`DELETE /me` NO borra la fila**: llama a `anonymize()` (`RGPD-01`). El pedido y su historia
  contable se conservan sin PII, porque la FK es `RESTRICT` y la factura tiene que seguir vinculada.
  ⚠️ **`GET /me/export` es el cuerpo con más PII del producto** —lleva `event_data` en claro: nombre
  y alergias de un menor, art. 9— y por eso `RGPD-04` exige `no-store`, que en `/api/v1` va por
  defecto en toda respuesta autenticada.
- ✅ **`account-context` YA ES VUE** (`#123`, 2026-08-23): el bloque lo pinta
  `sidebar/account/AccountPanel.vue`, teletransportado al hueco que emite el layout, y la frontera
  Livewire↔Vue del cajón **desaparece**. ⚠️ **Pero Livewire NO se puede retirar**: sigue trayendo
  Alpine, así que `@livewireScripts` se queda — lo que cambia es que ahora es la **fuente única**, y
  su guarda por fin discrimina (medido: retirarla la pone roja; hasta hoy no).
- ✅ **La puerta de entrada de quien no tiene sesión es el CAJÓN** desde `#122` (2026-08-23): el modal
  de la cabecera se retiró y las tres pantallas de auth son zonas de la sección de cuenta.

## ▶ Próximo paso

# ❗ SI ENTRAS NUEVO (2026-08-27, 18:40): los TRES carriles cerrados y empujados — y el A ya tiene su siguiente tarea DECIDIDA: la tanda 4 de «menores a cargo»

**Los tres cerraron el 2026-08-27 con todo en `origin/main` y verde.** El B a las 07:30; el **A a las
18:40 con las tandas 1, 2 y 3 de «menores a cargo»** (`#191` · `#198` · `#199`, `specs/menores-a-cargo.md`
§9) **y la tanda 4 decidida como siguiente** (`[DECIDIDO owner]`; el mapa de arranque, con los ocho
puntos que hay que saber antes de escribir, está en su fila de la cabecera); y el **C, el TEMA**, por
la noche con la tanda 1 de la capa de tema (`#192`). `git fetch` antes de nada y **lee las tres filas
de la cabecera antes de elegir tarea**. Del owner: su ojo en navegador (el waiver, la zona de menores,
el hero), el texto del waiver y los dos plazos de retención. ⚠️ En el carril A, **antes de la tanda 4
conviene ese ojo sobre la zona de menores** (guion `VERIFICACION-E2E-CAJON.md` §5.decies): la tanda 4
la usa.

| Carril | Qué espera, exactamente |
|---|---|
| **A · menores** | **La tanda 4 (la asignación en el embudo) EN EJECUCIÓN desde el 27 por la noche**: diseño medido en `specs/menores-a-cargo.md` §9.9 (`#202`), decisiones del owner tomadas (❗ la exención firmada es CONDICIÓN para asignar · el panel NO entra, rectificado: sesión propia), **U0 hecha** (la purga de la cesta, §9.9.6) y **U1 hecha** (el servidor entero, §9.9.7); sigue **U2, el cajón**. Del owner siguen: su ✅ en navegador de la zona (guion §5.decies) y los DOS valores de retención en meses |
| **B · panel/dinero** | Nada de agente. La pasada de NAVEGADOR del owner por las 10 acciones (`specs/desmontar-view-order.md` §6·5) |
| **C · tema** | **Dos cosas, y las dos son suyas.** ① **La pasada de NAVEGADOR**, que sigue sin hacerse: **`#195`, el hero entero** (pierde su CTA, gana un eslogan, es una tarjeta y ENCOGE al bajar) y **`#196`, 19 elementos que pierden su sombra** (cambia media web; mira `/cumpleanos` y `/precios`, las más afectadas, y pasa el ratón por las tarjetas). Ya validó `#193` («la tira está y es correcta, las esquinas») y `#194` («el hero está como estaba antes»). ② **El ✅ a `specs/armazon-y-menu.md`** (escrita el 27 por la tarde) y sus **seis pendientes** §5 — la 1.ª es el **artboard de MÓVIL**, que él mismo anunció que guiaría, y bloquea una tanda entera. ▶ Y para la tanda 3, **cuál de las dos variantes de «El parque»**. ⚠️ **Las dos cosas se pisan**: la 2c cambia el mismo terreno que `#195`/`#196`, así que **si se apila sin haber mirado lo anterior, cuando algo se vea raro no habrá forma de saber cuál de las tres tandas lo hizo** |

⚠️ **«La landing sigue bloqueada» dejó de ser cierto y esta sección lo decía**: la capa de tema ya
tiene las tandas **1, 2a, 2b y la ELEVACIÓN** en `main` (`#192`→`#196`) y la **2c escrita**. Lo que
sigue parado es el CONTENIDO (la tanda B de `landing-white-label.md`: `testimonials` y el copy al
CMS), no el tema.

▶ **Por dónde retoma el carril C**: **el armazón está COMPLETO salvo el MENÚ EN MÓVIL** —2c·0
(`#200`), 2c·1 (`#201`), 2c·2 (`#203`), 2c·3 (`#204`) y 2c·4a, el CTA doble (`#205`), todas en el
árbol y verdes—. Lo único que queda es la **2c·4b, el menú a pantalla completa en móvil**, y la
bloquea el **artboard del owner**: hasta que exista, el cajón lateral sigue intacto por debajo de
1080 px. ▶ Lo siguiente de agente sin depender de él es la tanda **2d, el MOVIMIENTO** (medido: 237
declaraciones, 48 duraciones y 20 curvas; el sistema del cliente declara 7 y 4).
▶ **Y de la LANDING (tanda 3), lo medido el 2026-08-27 contra el artboard normativo**: la única
sección del mockup que **no existe** es **OPINIONES** (3 tarjetas `texto`/`nombre`/`meta` + cápsula
de valoración = el `testimonials` parado en `#158`); **el minijuego del castillo** tampoco existe y
en el mockup vive dentro del CTA final; tenemos **galería y FAQ**, que el mockup **no tiene**; y sus
juegos van **dentro de Zonas**, no en una sección «Atracciones» aparte. Del **kit de fachada** solo
hay **2 de 24 piezas** (la trama de puntos y la tira del pie) — y dos de las que faltan (`D9` las
poses, `B4` foto dentro de mancha) **las bloquea el ARTE, no el código**. Del set de iconos, **25 de
48** componentes.
⚠️⚠️ **La 2c·2 corrigió DOS VECES a su propia spec y las correcciones van delante del texto que
corrigen**: `M-05` **ya estaba cumplido** —nuestro mobiliario perdió la sombra en `#196`, así que lo
que hacía falta era lo contrario: darle a las piezas con qué sostenerse— y la coreografía «nace
oculto bajo el hero» **no se implementó**, porque el hero dejó de ser a sangre en `#195` y aplicarla
dejaría la portada sin logotipo y sin ☰.
▶ **Lo que el owner ya decidió** (con la medida delante): armazón flotante en las **12** vistas ·
lista de menú **PLANA** con los destinos que ya hay, y la BD sigue al mando · con sesión, **icono de
cuenta con punto naranja/verde** · **el móvil lo guía él con un artboard**.
▶ **Lo medido** (instrumento con guarda de la guarda, spec §1): **194 reglas distintas · 733
declaraciones** de CSS en 7 familias · **31 aserciones** en 4 ficheros · **33 reglas (17 %) MUERTAS**
· **12** vistas · el salto al contenido existe en **1 de 12**.
⚠️ **La primera cifra publicada era FALSA y se corrigió al ejecutar**: «232 · 819» y «41 muertas»
sumaban COINCIDENCIAS, no reglas —una regla que cita dos familias contaba dos veces, y `.cta-prime`
se contaba como del armazón cuando la comparte con el hero—. **Un inventario que suma por etiqueta
cuenta etiquetas, no sujetos.**
❗ **Y el hallazgo de método de esta tanda**: el instrumento dijo **9** clases muertas en vez de 11
porque dos «vivían» **dentro de un comentario de Blade** que explicaba que ya no se usan. La regla
escrita —«un `grep` que no encuentra no demuestra que no exista»— **tiene simétrica**: un `grep` que
SÍ encuentra tampoco demuestra que exista. El corpus ahora limpia comentarios y lleva el control
positivo que muerde ese caso.
❗❗ **Tres cosas del mockup NO se copian** (spec §1.7): su menú cerrado **deja los enlaces en el
orden de tabulación** (medido: cero `inert`, cero `aria-hidden`, cero `visibility`) — nuestro cajón
ya resolvió eso y el porqué está escrito; el hallazgo **`M-05`** del propio cliente —difusa fuera de
modal en el CTA fijo y el botón de registro— **choca con `--shadow-float`, creado el mismo día en
`#196`**; y el eslogan a rotulador sale dos veces (`T-02`).
⚠️ **La auditoría del cliente EXCLUYE el menú y el logotipo** por indicación suya: sus colores están
revisados, su forma y su coreografía **no**. Es la misma trampa que con el hero.

✅ **La capa de tema tiene ya sus CUATRO mecanismos** (`#192` → `#196`): color y superficie · forma
(cantos, motivo, foco, tira) · el hero · y ahora la **ELEVACIÓN**.
❗❗ **La elevación son TRES ROLES, no una escala, y eso CORRIGE la medición de `#193`.** Aquella
miró solo el difuminado; con las cuatro dimensiones, el producto tenía **53 sombras y 42 formas
distintas** y la mejor escala de cinco escalones movía **47 de 53**. No era una escala con ruido:
**no había ninguna**. ▶ Y al ir a copiar el número de escalones del cliente apareció que **él
tampoco tiene**: declara DOS formas. Con 42 de un lado y 2 del otro, la pregunta era **«¿para qué
sirve cada sombra?»** — y salen tres respuestas: `lift`, `float`, `modal`. **19 pierden la sombra**:
una tarjeta quieta no está elevada, está apoyada. De **53 formas propias a 6**, todas justificadas.
⚠️ **Los tres leen `--paper-fg`, no `--fg`**: dentro del hero `--fg` vale CLARO y la sombra se
volvería clara. Es el mismo defecto que `#194` cazó tres veces.

❗❗ **LO QUE ESPERA AL OWNER, y ahora es lo único que bloquea**: la **pasada de NAVEGADOR**. Se ha
acumulado mucho cambio visual sin que nadie lo mire: el hero entero (`#195`: pierde su CTA, gana un
eslogan, es una tarjeta y encoge al bajar) y **19 elementos que pierden su sombra** (`#196`), que
cambia el aspecto de media web. Lo verificado es aritmética y estructura — **no vista**.
▶ Y de `#193` sigue pendiente su ✅ a las 11 declaraciones de canto que se movieron.

⚠️ Sigue abierto en `DEUDA.md`: **`--onvideo` sobrevive en 10 reglas y su nombre ya miente** (solo
declara tamaño y sombra) · **la excepción del anillo de foco del chip ya se puede retirar** (el hero
declara `ink`) · y **~50 reglas de CSS que PARECEN muertas y no se pueden confirmar con un `grep`**:
el cajón construye sus clases por concatenación en Vue, así que `cal__day--normal` no aparece ni en
`resources/` ni en el bundle minificado **y sin embargo está viva** (sale en el manifiesto DOM).
Auditarlas necesita su propia pasada, con el rigor de `CONVENCIONES §3.quater`.



✅ **El mecanismo de la tanda 1 YA lo usa alguien**, desde `#194`: el `.hero__stage` declara
`data-surface="ink"` y es el primer —y por ahora único— consumidor. Este párrafo decía lo contrario
y se corrige aquí.
⚠️ **Pero las tres excepciones del anillo de foco NO se retiraron.** Este documento daba por hecho
que caerían «ese mismo día» y no fue así: se dejaron **a propósito**, para no mezclar un cambio de
foco con la conversión de superficie. ▶ **La del chip del hero ya se puede retirar** —su fondo sí
declara superficie—; las de `.skip-link` y `.gf-fiche__head`, no. Ficha en `DEUDA.md`.

**Y en el carril de calidad no queda trabajo de valor alto — está medido, no supuesto.** El reloj está
cerrado (`#162`, `#164`), `RGPD-01` corregida (`#159`) y la siguiente rebanada del gate documental se
midió y da **cero** (`#164`: las 35 citas de la columna «Dónde vive» resuelven). Así que **antes de
inventarte una tarea, mira la lista de «Lo que NO depende de nosotros»**: casi todo lo que queda lo
desbloquea el owner.

▶ **Las dos cosas que eran trabajo de agente YA TIENEN CARRIL** (reparto de la cabecera):
1. ✅ **El waiver, de agente, está TERMINADO** → **carril A, 2026-08-26** (`#169` → `#183`): revisión
   adversarial del subsistema (spec §10), **la tanda 4 que exigía** (§9.11: `#171` anti-bot · `#174`
   servidor · `#175` cajón · `#178` casilla del alta manual + casilla OBLIGATORIA en interno · `#179`
   correo verificado para firmar · `#180` texto del PDF) **y la revisión adversarial de la propia
   tanda, aplicada** (§9.12, `#183`: 24 confirmados, 0 refutados; lo peor, `Verified` emitido por el
   COBRO dentro de su transacción). El guion `VERIFICACION-E2E-CAJON.md` §5.nonies está **reescrito con
   la conducta definitiva y recorrido en headless: 111/111 ✓**. **Lo que queda es del owner y solo
   del owner**: su ✅ en navegador (guion §5.nonies, en local, con las claves de PRUEBA de Turnstile
   en Ajustes —receta en §5.bis—), el **texto definitivo** (spec §8.1: publicar la v1 real es
   irreversible) y el **plazo de retención** (§4.6). ▶ ✅ **`[DECIDIDO owner, 2026-08-27]`: la
   SIGUIENTE SESIÓN de este carril hace «menores a cargo»** (`specs/menores-a-cargo.md`, C; hereda
   NUC-3 de `DEUDA.md`) — spec revisada, sin código: leer antes `docs/INVARIANTES.md` §2 (AFORO) y, en
   la spec, sus secciones 8.1 y 8.2 —la lista blanca de `cart.js::save()` y el `STORAGE_VERSION`—. El
   detalle del arranque está en la fila A de la cabecera. ▶ ✅ **HECHO por la tarde: la tanda 1 está
   EMPUJADA** (`#191`, spec §9: la entidad, el registro con tope, la API, RGPD y el ajuste; sin firmas
   de menor). **Lo siguiente son las cinco decisiones de §9.5**, no código.
   ⚠️ En la máquina del carril A hay **v1→v9 publicadas** localmente por las dos pasadas del guion y
   los sondeos (y cuentas `e2e-waiver-*@jumpweb.test` / `probe-*@jumpweb.test`); en la del portátil,
   no. Tras un pull en el portátil: **`migrate`** (`#183` añade una migración).
2. ✅ **El desmontaje de `ViewOrder`, de agente, está TERMINADO** → **carril B, 2026-08-27**
   (`#165` spec · `#167`/`#168` revisión · `#170`→`#177` paso 0 a instrumento · **`#182`→`#189` la
   extracción 4b entera en una noche**): `ViewOrder` en **2.355 líneas y 50 métodos** (de 5.280 y
   98), sin orquestación de dinero ni de aforo — seis servicios y dos contratos en
   `app/Domain/Booking/`, la receta anti-sobreventa UNA vez (`ZoneDaySlotLock`, compartida con la
   compra), 74/74 mutaciones, 6/6 escenarios + Redsys, y **15 tests nuevos de reglas que la página
   no podía alcanzar**. `[DECIDIDO owner]` (`#181`): no se parte más. **Lo que queda es del owner y
   solo del owner**: la pasada de NAVEGADOR por las 10 acciones del panel (spec §6·5). ▶ Siguiente
   trabajo de agente en este carril: ninguno del desmontaje; ver `DEUDA.md` o lo que el owner diga.

▶ **La línea de panel/dinero está CERRADA y DESPLEGADA** (`#149`→`#155`, staging en `7776370`): no
hay siguiente paso de agente ahí. Lo único pendiente es HUMANO: el owner prueba el modal nuevo de
«Reembolsar» en navegador con sus 9 pedidos-sonda (decidió conservarlos para eso).

# ❗ LO SIGUIENTE: **`testimonials`** — lo único de la landing que NO está bloqueado

⏸️ **[DECIDIDO owner, 2026-08-25 tarde] APLAZADO hasta que la landing esté terminada** (`#158`): el
owner no puede visualizarlo ahora, y una sección que no se puede ver no se puede validar (cuarta
condición del DoD, `CONVENCIONES §3.bis`). Ya no hay nada en marcha —los dos carriles cerraron el
27/08— y lo siguiente decidido es «menores a cargo» (reparto de arriba). Lo que sigue de este apartado describe el trabajo
tal y como quedó MEDIDO, para cuando toque.

⏸️ **Por qué no es «seguir con la tanda B»**: el owner está **rehaciendo el sistema visual** en Claude
Design y ha dicho que **los datos y textos del mockup NO son fidedignos** —«lo que hay que llevarse es
la estructura, las formas, los botones, los colores y los layouts; los datos son los que tenemos
ahora»—. Maquetar ahora es trabajo que se tira.

**`testimonials` sí se puede hacer entero hoy**, y hace falta decida lo que decida el owner sobre las
reseñas: es el **respaldo** de `specs/google-reviews.md` (§4.4.bis).
✅ **[DECIDIDO owner, 2026-08-25]: va DETRÁS del contrato `Content\Contracts\SocialProof`** que diseña
`google-reviews.md` §4.1, no como clon liso de `faqs`. Cuesta una interfaz más y evita retrofitear la
vista el día que Google se encienda — que es la rama que, por definición, solo se ejecuta cuando algo
va mal.
⚠️ **Medido el 2026-08-25**: `testimonial` no aparece en **ningún** fichero de `app/`, `database/`,
`resources/`, `routes/`, `lang/`, `config/` ni `tests/`. Es construcción desde cero.
⚠️ **Y roza `lang/es/admin.php`, que es del agente A**: el recurso necesita sus claves, y su bloque de
pedidos vive en la misma zona del fichero. **`git pull --rebase` antes de empujar** — esta sesión ya
pagó ese peaje seis veces con los números de `DECISIONES`, y una séptima con un arreglo duplicado.
▶ Tabla + modelo + recurso de panel
+ sección, siguiendo el patrón exacto de `faqs` (permiso `content.manage`, grupo «Contenido», campos
i18n en JSON con `HasTranslations`). Campos medidos del mockup: `texto` · `nombre` · `meta` +
valoración.
⚠️ **Su ayuda en el panel NO puede decir «por si Google falla»**: por `google-reviews.md` §3.3, es lo
que ve **todo visitante que no acepta cookies de terceros**, cada día. Si se documenta como plan de
emergencia, el parque lo dejará vacío creyendo que nunca se usa.

### El orden acordado con el owner para cuando el diseño esté listo

1. **Cimientos visuales** — la paleta del cliente + los patrones de forma que faltan. Van ANTES que el
   armazón porque el nav y el footer los consumen: hacerlos después obliga a rehacerlos.
2. **El armazón** — nav/menú, logo y footer, **con NUESTROS elementos**. Es lo que se ve en todas las
   páginas y fija los patrones de botón que luego reutilizan las secciones.
3. **`<x-page>` + una página de ejemplo** — pedido explícitamente por el owner («las dos cosas»).
   Medido: las 6 páginas re-maquetan su cabecera a mano (`page__head` ×4, `page__title` ×4,
   `page__body` ×3, `page__back` ×3). No hay nada entre «el armazón del sitio» y «el contenido».
4. **Sección por sección**, con los datos reales de ahora.

⚠️ **Y el minijuego del castillo ENTRA** (`[DECIDIDO owner]`), idéntico, pero se acepta hacerlo más
eficiente. Medido en el mockup: **52 `setState` en el bucle de animación** —re-renderiza el árbol 60
veces por segundo, y ahí está el coste, no en las 105 llamadas de canvas—, **~66 KB de JS** que hoy
pagaría todo visitante, y **`tabindex` 0 · `role` 0 · `aria-label` 0**. Las tres cosas se arreglan sin
mover un píxel: estado fuera del ciclo de render, chunk con carga diferida y accesibilidad.

▶ **Después**: el copy al CMS (que gana esperando a la landing) y luego la tanda **C**.

### ❗ El sistema de color NUEVO, ya leído y medido — y lo que cambia

Vive en el **canvas de Claude Design del owner, NO en el repo**. Para leerlo:

    DesignSync · method=list_files · projectId=8c37d2d2-7e9c-43a9-bc25-aacb6607f2ad
    DesignSync · method=get_file  · path="Colores de Marca PJP.dc.html"

⚠️ **No sirve WebFetch** (da 403) ni `Artifact action:read` (no es un artifact publicado): **solo el
MCP `DesignSync`**. Los `.jpg` de `assets/` vienen en base64 y **truncados a 256 KiB**, pero un JPEG
parcial se decodifica y se ve.
▶ El canvas tiene **14 artboards**: además del de color, `Logotipo variantes`, `Menu PJP`,
`Boton Reservar variantes`, `Hero PJP variantes`, `Info PJP variantes`, `Landing PJP Modos`,
`Elementos Fachada`, `App PJP`, `Marquesina Castillo`, `Salta la Ciudad`, `Tag Lorca`.
▶ Y hay un artifact aparte, **«Landing page parque trampolines»**, con el mockup completo de la
landing — **pero lleva la paleta VIEJA** (ver el aviso de abajo).

⚠️⚠️ **Lo primero: la paleta que se midió del mockup de la landing está CADUCADA.** Su propia tabla de
migración lo dice —`#2FB6DE` → `#1AA9DE`, «se iba de claro y perdía 0,4 de contraste sobre tinta»—.

**No es una paleta: es un sistema con 15 secciones**, derivado de la fachada real con la masa
cromática medida (46 % azules, 36 % naranjas, 13 % verdes), con **8 colores de núcleo** (cada uno con
`hover`, `press` y su variante oscura), **9 neutros**, roles por elemento, **auditoría WCAG de 20
pares** y prohibiciones explícitas.

▶ **La buena noticia: encaja casi 1:1 con los tokens que ya existen** — Tinta`#101418`→`--fg`,
Papel`#F4F4F1`→`--bg`, Cian`#1AA9DE`→`--brand`, Verde Salta→`--ok`, Rojo Goteo→`--err`,
Amarillo Aviso→`--attn`. **Cuatro cosas no tienen token**: Lima Bote (precios y cifras), Azul Muro
(el único azul legible sobre claro), Magenta Chispa y los `hover`/`press` de cada color.

❗❗ **Y DOS cosas que no son «otra paleta», son otra ARQUITECTURA:**
1. **«La marca es oscura por naturaleza: el color vive sobre negro.»** El sistema alterna **dos
   fondos por sección** —tinta y papel— con la regla «nunca dos papeles seguidos». Nuestro CSS asume
   fondo claro. **Eso no se resuelve redefiniendo tokens: es un MODO**, y hay un artboard llamado
   precisamente «Landing PJP Modos».
2. **«Texto secundario: dos grises distintos — el claro falla en papel.»** Nosotros tenemos **un
   solo** `--fg-mute`. Con un único gris sobre los dos fondos, uno de los dos incumple AA. El sistema
   ya lo trae medido: Humo `#626A72` (4,98 en papel) y Humo Claro `#9AA1A8` (7,08 en tinta).

▶ Y del sistema de FORMA: sombra **dura** `5px 5px 0` «o ninguna», borde 2px solo en la pieza
protagonista, y escala de radios `0 · 6 · 10 · 16 · 24 · 999` (la nuestra es otra).
⚠️ Medido en el producto: **68 declaraciones `box-shadow`, 58 formas distintas y CERO tokens**, y solo
el 26 % se repite. **No hay escala de elevación de facto**: habría que decidirla, no extraerla.
⚠️⚠️ **C necesita SPEC PROPIA antes de una línea de código**: toca `AFORO-01/02/03` y las identidades
de `PAY`, exige `VERIFY_CONC=1` y **no se puede verificar ni en SQLite ni en staging** (MariaDB).

⚠️⚠️ **Antes de tocar el tema, lee la spec §4.5.1 y §4.5.2 — LAS DOS LLEVAN UNA CORRECCIÓN.** La
primera afirmaba «cero variables se inyectan desde BD» (falso: el tema sí se inyecta desde
`theme.brand`). La segunda, «cambiar el dibujo del spinner es sustituir un fichero; no hay que
construir nada» (falso: eran dos pseudo-elementos y un `@keyframes`, y la doc del propio sistema decía
que esa hoja no se modifica).
❗ **La lección que esta semana se ha pagado CUATRO veces, y la última en el mismo trabajo que la
escribió**: un `grep` que no encuentra no demuestra que no exista, y **un instrumento que no ve una
parte del corpus da un inventario que parece completo y no lo es**. Cuando dos medidas del mismo
corpus no coinciden, la que sobra **no es la que da más: es la que no puede explicar la diferencia**.

---

### Lo que la tanda A dejó montado, y hay que saber ANTES de tocar CSS

- 🆕 **`public/css/client.css` es el paquete de tema de la instalación** (`#143`). Se carga **el
  último**, **no se versiona** y **`deploy.sh` lo excluye del `rsync --delete`**. ⚠️ **Las tres cosas
  son el mecanismo**, no tres detalles: por delante de `site.css` carga y no pinta nada; sin la
  exclusión, el primer despliegue lo borra **en silencio**. `ClientThemePackageTest`.
- 🆕 **Un literal que repita un token existente ya no puede entrar**: `RawColourIsNotATokenTest`
  compara **por VALOR RGB, no por nombre de token** — que es el hueco por el que dos acentos del
  primer cliente sobrevivieron a `#139` escritos en decimal dentro de un degradado.
  ⚠️ Su lista de excepciones (`ALLOWED_SELECTORS`) **solo encoge**, y hay un caso que tumba una
  entrada que se quede sin sujeto.
- 🆕 **`spinner.css` tiene dos mitades y la frontera es un marcador de máquina** (`>>> SPINNER:… >>>`).
  §A contrato, §B dibujo. Meter geometría en §A pone `SpinnerTest` en rojo, y con razón: es lo que
  hace sustituible el dibujo.
- ⚠️ **Blanco y negro NO se tokenizaron, y es decisión del owner**: el blanco de papel no es `--bg`
  (crema) y el blanco sobre acento no es `--on-brand` (sobre un acento claro es tinta oscura).
  Convertirlos **cambia píxeles**. Ficha con los tres grupos en `DEUDA.md`.
- 🐛 **Y queda un defecto de coherencia de una línea**: `.addons-mini__badge` pinta `color: var(--ok)`
  sobre un fondo verde de otra familia. Cambiar `--ok` mueve el texto y deja el fondo quieto. Está
  en `DEUDA.md` porque arreglarlo cambia píxeles: es decisión de producto, no refactor.

## ▶ Lo que está ABIERTO y no es de la tanda A

✅ **0.bis · Los CUATRO defectos del cambio de precio están CERRADOS — y lo que queda son fichas
con nombre** (`DECISIONES #146` → `#149` → `#150`, 2026-08-25. Todo medido ejecutando, arreglado y
verificado en vivo sobre MySQL. La nota del cierre del agente B «D5 informado, no verificado»
queda superada: manda `git log`, y ahora el arreglo entero está en el árbol.)

⚠️⚠️ **El tronco, para la historia**: `PAY-18` (`#131`) hizo que **mover la fecha re-tarifique**, y
**SEIS sitios** estaban escritos sobre la premisa vieja («el valor solo baja si baja la cantidad»).
`#145` arregló el filtro del contexto · `#149` D5 (el importe elegido en «Reembolsar», con el
«pendiente de devolución» sugerido delante) · `#150` D4 (la reconstrucción calcula
`cantidad_original × precio_original`), D3 (el marcador se dispara con cualquier cambio
reconstruible), D2 (toast y pies dicen la causa verdadera) y el sexto («+N producto» exige un
`quantity_change` real). **El callejón del dinero atrapado está cerrado**: bajada → cancelar la
RESERVA → la línea devuelve TODO (verificado en vivo, `R-VLRYUV`: 40,00 fuera, pendiente 0).
✅ **Y el pack CON señal quedó MEDIDO** (`R-DWFRDP`): la cascada absorbe la bajada contra el resto
de la señal, cero reembolsos necesarios, identidades cerrando — lo que `#146` leyó es lo que pasa.

✅ **Las CUATRO fichas derivadas quedaron CERRADAS el mismo día (`#152`–`#155`):**

| | Ficha | Estado |
|---|---|---|
| 1 | ~~Un pedido CANCELADO no tenía vía de reembolso~~ — ✅ **CERRADA** (`#152`, owner): la LÍNEA se abre para cancelados con deuda (topes intactos; el TOTAL sigue vetado a propósito) y el banner dice cuánto se debe y por dónde | ✅ hecha |
| 2 | ~~El reembolso a nivel PEDIDO regalaba sin avisar~~ — ✅ **CERRADA** (`#153`, owner): sin campo (los parciales van por línea); el modal nombra el importe exacto y avisa de la vía de los parciales al desactivar «también cancelar» | ✅ hecha |
| 3 | ~~El cliente EN/FR veía claves en crudo~~ — ✅ **CERRADA** (`#154`): las etiquetas viven en `tickets.*` (ES/EN/FR) con guarda `Lang::has` sin respaldo | ✅ hecha |
| 4 | ~~El email de una BAJADA no mencionaba el dinero~~ — ✅ **CERRADA** (`#155`): cuenta la deuda que aflora Y lo absorbido en puerta, en tres idiomas con guarda | ✅ hecha |

✅ Y «¿devolver en el parque?» quedó **DECIDIDO** (`#152`): no se construye canal nuevo — devolver en mano se registra con el modo «manual» («ya devuelto fuera»), que ya existía y desde `#149` acepta importe exacto.

🟦 **0 · La VISIÓN DE PRODUCTO de la app está DISEÑADA, REVISADA y EN EJECUCIÓN** (`DECISIONES
#142`, revisión en **`#156`**, 2026-08-25). Cuatro subsistemas en Fase 6, ordenados por
**dependencia**: waiver probatorio → menores a cargo → carné QR y pantalla de puerta → JumpPoints.
✅ **El waiver arrancó el 2026-08-25 por la tarde y su tanda 1 —el núcleo— está EMPUJADA** (`#160`,
`specs/waiver-probatorio.md` **§9**): versiones inmutables, firmas encadenadas por titular (verificadas
bajo concurrencia sobre MySQL, y el verificador visto fallar sin el lock), los tres modos, la prueba que
sobrevive a `anonymize()` y la acción de publicar — **sin publicar ninguna versión** (§8.1 es ahora un
mecanismo: un `[PENDIENTE]` no se publica). ✅ **Y la tanda 2 —el panel— también** (`#161`, 2026-08-26):
la identidad del firmante viaja EN la firma (`[DECIDIDO owner]`), permiso propio `waiver.view`, el
registro en la ficha como acción auditada, el PDF del snapshot en el idioma firmado y el alta
presencial declarada. ✅ **Y la 3a —el cliente por API— también** (`#163`): `GET /legal/waiver`,
`GET|POST /me/waiver` (aceptar SOLO el texto que el servidor sirvió), el PDF propio, la casilla del
alta y `waiver` en el contexto de cuenta. ✅ **Y la 3b —el cajón— también** (`#166`, 2026-08-26): la
casilla del alta (opt-in, y **solo si hay documento servido**), la tarjeta de Privacidad con firmar /
re-firmar y los PDF, y el aviso del índice; el store RE-LEE ante `409 waiver_document_stale`. Los
textos del montaje se **podaron antes de subir** (−508 B) y el chunk subió su techo **por decisión del
owner**. **El código del waiver está COMPLETO**, ✅ **el guion §5.nonies está recorrido en headless
y el subsistema REVISADO de forma adversarial** (`#169`, 2026-08-26: spec §9.10 y §10); **el código
acotado que exigió esa revisión está HECHO** (tanda 4, `#171`→`#180`) **y la revisión de la propia
tanda, aplicada** (`#183`, spec §9.12; guion re-recorrido con la conducta definitiva, **111/111 ✓, 0 desviaciones**).
Sigue 🟦 solo por lo humano —el ojo del owner en navegador, el texto definitivo y la retención—: las
decisiones de §7 están tomadas y ejecutadas. Las dos altas que dejó la revisión —el alta manual que
«declaraba» sin declarar y el alta suelta sin anti-bot— están arregladas (`#178`, `#171`). **No toca la landing**. Detalle
en el tracker; las cuatro specs, en `docs/specs/` y en la tabla de enrutado de `CLAUDE.md`.
▶ **C (menores a cargo) arrancó el 2026-08-27 por la tarde: tanda 1 EMPUJADA** (`#191`, spec §9) —
la entidad, el registro con tope de servidor, la API contra el contrato y el RGPD, sin firmas de menor
todavía—; **la tanda 2 espera NUC-3** y las otras cuatro decisiones de §9.5.

✅ **La revisión adversarial que `CONVENCIONES` §5 exigía está HECHA** (`#156`): cada spec tiene su
**§8** con los hallazgos, y **ninguna hay que rehacerla**. De todas sus afirmaciones verificables
sobre el código, **ninguna resultó falsa** — lo que aquí no es lo normal (`#143` encontró tres falsas
en una sola spec).
❗❗ **Pero destapó DOS bloqueantes, y el peor no es de ingeniería:**
1. **El texto del waiver es literalmente un borrador** —lo dice él mismo, en los tres idiomas— y
   **publicar una versión es irreversible por diseño**. La maquinaria se puede construir; publicar la
   v1, no. Es un `[PENDIENTE: owner]` NUEVO y anterior al del plazo de conservación.
2. **JumpPoints descansaba sobre un hecho que el sistema no podía observar**: nadie sabe si un
   cliente vino (`tickets` tiene las columnas del ciclo y **cero escritores**). ✅ **Resuelto por el
   owner**: los puntos tienen **FUENTES configurables** — visita acreditada en la pantalla de puerta
   + compra pagada. Eso convierte el orden `A → D` en **dependencia dura**.
   ⚠️⚠️ **Y lo que no se puede perder**: la fuente «compra» **reabre el agujero de ingresos** si sus
   puntos se abren al instante. **Lo configurable es CUÁNTOS puntos da cada fuente, no CUÁNDO se
   abren** — un ajuste que permita «compra → disponible ya» lo reabre desde un formulario, sin que
   nada falle y sin que nadie lo revise.
⚠️ **Tres huecos de mecanismo que se deciden ANTES de la primera línea** (todos en `#156`): la cadena
de hashes del waiver **no tiene punto de serialización** —se bifurca en silencio bajo concurrencia, y
una cadena bifurcada no prueba nada—; el registro de firma va en **tabla propia**, no ampliando
`consents` (`cascadeOnDelete`); y el **alta presencial** también escribe consentimientos, así que
produce una firma **declarada por el operador** (`[DECIDIDO owner]`), que el PDF tiene que decir con
todas las letras.
⚠️ **Dos cosas tienen consecuencias fuera de su alcance**: el waiver **modifica `RGPD-01`** —⚠️ **y
`RGPD-01` no contiene hoy la frase que hay que modificar**: hay que añadirle primero lo que el código
ya hace y la invariante calla— y el carné QR **entra en `User::revokeAllAccess()`** desde el primer
commit, que es el modo de fallo exacto que `RGPD-06` existe para impedir.
❗ **Ninguna de las cuatro está aprobada todavía**: siguen 🟦 esperando el **✅ del owner**.

✅ **1 · El aforo, VERIFICADO bajo concurrencia — los CINCO caminos** (2026-08-25, `#147` + `#148`).
Era el mayor riesgo abierto: `purchase:verify-oversell` solo sembraba **entradas**, y los cumpleaños
se cuentan por otro camino entero (`PackAvailability`, pool propio y dos topes) que **no ejercitaba
ningún verificador**. Hoy son cinco escenarios —`entry` · `pack` · `pack-guests` · `pack-prep`
(tramo multi-franja **con montaje y limpieza ACTIVOS**, que es la configuración de producción) ·
`mixed` (los dos pools a la vez)— y **los cinco pasan** sobre MySQL con 8 y 16 procesos.
❗❗ **El verde vale porque el instrumento se vio FALLAR.** Con el `lockForUpdate()` retirado, el
mismo comando cazó **8 fiestas donde cabía 1**, **48 invitados donde caben 10** y **4 entradas + 4
fiestas donde cabía 1 de cada**. `OrderCreator` restaurado y comprobado por md5 y `git status`.
▶ Lo guarda `OversellVerifierCoversEveryQuotaTest`: si alguien retira un escenario, quita un contador
o mueve la guarda del instrumento a después del fork, la suite cae.
▶ **El detalle, las dos lecciones de método y lo que sigue sin medir están en `#147` y `#148`.**

✅ **1.bis · [DECIDIDO owner, `#151`] El consumo medido en `#148` es CORRECTO: la independencia de
cupos se hace POR ZONA.** Una fiesta de 8 en una franja de 10 deja 2 plazas de entrada — y eso es
el contador diciendo la verdad física: **dentro de una zona, `seats` cuenta ocupación real, sea del
producto que sea**. Un producto que necesite plazas propias se lleva a SU zona (así está hoy:
cumpleaños en `cumpleanos`, entradas en `jump`/`kids`; y así irá el siguiente — excursiones de
colegio → zona propia). `occupancyMap()` **no se filtra por tipo**, y `PackConsumesEntrySeatsTest`
pasa de «fijar sin juzgar» a **guarda de la regla decidida**.
▶ **Regla de instalación (white-label)**: productos que comparten zona comparten sitio físico;
independencia ⟹ zona propia. Es lo que hay que saber al configurar los aforos del 2º cliente.

✅ Y antes, el 2026-08-25 (`#141`): los dos contadores de aforo **ya disparan el gate** del
`pre-push`, con su control negativo y verificado por mutación.

❗ **2 · Pendiente del OWNER: un `Ds_Response=0900` REAL de Redsys.** Exige un pago de prueba con
tarjeta en el sandbox desde el navegador (staging) y después `redsys:verify-sandbox --gateway-order=…`.
Todo lo demás de la cadena está verificado con sus credenciales. ⚠️ Una medición anterior dio el
sandbox por inalcanzable y **era un error de medida**: se probó el 443 y Redsys sirve el suyo en el
**25443**.

⚠️ **3 · El `redis.conf` de staging sigue de fábrica**, aplazado a propósito por el owner: sin techo de
memoria y **deja de aceptar escrituras si falla un volcado**. Contenido acordado y riesgo medido en su
ficha de `DEUDA.md`; aplicarlo exige reiniciar el contenedor PHP desde el panel.

---

## ▶ El estado de la BD de desarrollo, antes de mirar nada

❗❗ **«LA» BD de desarrollo no existe: hay DOS, una por máquina, y NO comparten datos.** Medido el
2026-08-25 al fusionar las dos líneas de trabajo (`#150`): **el corpus documentado abajo vive SOLO
en la máquina del agente B** (26 pedidos de `cliente.demo`). En la máquina del agente A hay **24
pedidos de `admin@jumpweb.test` con CERO solapamiento** con los códigos que cita la spec —ni uno—,
más los 9 de las sondas `#149`/`#150`. Es también la razón de que esa máquina llevara TRES
migraciones sin aplicar (`#149`): el trabajo de corpus nunca pasó por ella. **Todo lo que este
apartado dice del corpus aplica a UNA máquina; antes de fiarte de nada, cuenta en la tuya.**

- **El corpus se construyó con 25 pedidos**, uno por acción accionable, por los **flujos REALES**
  (`OrderCreator` → vuelta de Redsys FIRMADA → acciones del panel por Livewire). Titular:
  `cliente.demo@jumpweb.test`. Los 58 anteriores **se borraron** el 2026-08-24 y no hay copia.
  ⚠️ **MEDIDO el 2026-08-25 al cerrar: hay 26, no 25.** Los 26 son de `cliente.demo` y **ninguno se
  creó ese día** (0 pedidos del 25/08, 0 de usuarios `@deleted.local`), así que **no vienen de los
  verificadores de concurrencia**, que limpian lo que crean. El desfase es anterior y **no se ha
  determinado su origen**: puede ser un pedido de prueba de otra sesión o que el índice de §22 esté
  incompleto. Se anota como medida, no como explicación. **Antes de fiarte del índice, cuenta.**
- **Los 25 cuadran**, así que el aviso de «desglose que no cierra» (`#132`) **no se puede ver en
  pantalla con estos datos**: para verlo hay que romper uno a mano.
- **El índice de los 25, con su código y su acción, está en `specs/desglose-dinero-cliente.md` §22.**
  ⚠️ Y §22.3 recoge cuatro trampas para conducir el panel desde un test — la peor: el cambio de FECHA
  lo mueve el CALENDARIO y no el formulario, y con `slot_date` solo la acción **no da error y no cambia
  nada**.
- La sonda que los creó **no está en el repo** a propósito (instrumento de medida, no guarda). Su
  receta sí, en §22.3.
- ⚠️ Dos productos llevan icono propio desde `#140` (tirolina → confeti, calcetines → calcetines); el
  resto usa el de su tipo.
- 🆕 **Además viven 9 pedidos de las SONDAS `#149`/`#150`** (los 6 escenarios del owner, la
  verificación de D5 y las de D4/pack-señal: `R-P4NA2I` `R-DKKV3J` `R-REM7YW` `R-ITHNOJ` `R-MOTEHE`
  `R-8STAH6` `R-VLRYUV` `R-ZDRAYL` `R-DWFRDP`), con su zona, productos y usuarios `sonda146*`.
  ✅ **[DECIDIDO owner, cierre 2026-08-25]: SE QUEDAN — los usa para probar el panel** (el modal
  nuevo de «Reembolsar» incluido). **NO limpiar.** La sonda de limpieza queda en `storage/app/`
  (sonda146-clean, vía tinker; no versionada) para cuando ÉL diga.
  ⚠️ Los de `#149` retratan defectos que ENTONCES estaban abiertos (dinero regalado/atrapado): no
  son corpus, no cuadran como él. Los de `#150` (`R-VLRYUV`, `R-ZDRAYL`, `R-DWFRDP`) retratan el
  comportamiento ARREGLADO.
- ⚠️ **La BD local llevaba TRES migraciones sin aplicar** (`payment_refunds.intent`,
  `zones.color_secondary`, `ticket_types.icon`) — el panel de reembolsos ni podía escribir. Aplicadas
  el 2026-08-25 (`#149`). **Tras un pull: `migrate:status` antes de depurar nada raro del panel.**

---

### El estado del cajón, para lo que venga

🟩 **EL CAJÓN ESTÁ COMPLETO, PULIDO Y VERIFICADO EN NAVEGADOR.** El bloque de cuenta es Vue (`#123`),
el layout no renderiza **ningún** componente Livewire, los nueve retoques que el owner pidió están
hechos (`#124`) y el guion de navegador que quedaba **se recorrió el 2026-08-23** (`#125`). Las cuatro
specs del cajón están ✅ EJECUTADAS.

⚠️⚠️ **LO QUE ESE TRABAJO ENCONTRÓ, y condiciona lo que venga. Léelo antes de tocar el cajón:**
- **El `no-store` de TODAS las páginas web lo ponía un accidente de Livewire** —un hook de componente
  encendía el flag que usaba un middleware global del paquete—, así que retirar el último componente
  lo habría borrado del sitio entero **con la suite en verde**: ninguna de sus 12 aserciones miraba
  una página del layout. Hoy lo pone `NoStoreWebResponses` (global, con puerta para `/api/v1`).
- **`route('logout')` aparece UNA sola vez en toda la aplicación**, y vive como **suelo servido dentro
  del hueco** del bloque: colapsado e invisible mientras todo va bien, a la vista si el motor no
  llega. Desmiente la premisa escrita de `#120(t)`, ya corregida.
- **Ocho clases se emitían sin una sola regla** —entre ellas la tarjeta de «Mis reservas»—, porque la
  transcripción a Vue **inventó nombres** en vez de reutilizar los de las páginas retiradas. Ahora lo
  vigila `SidebarStyleWiringTest`, que además dejó **seis huecos del EMBUDO declarados con nombre**:
  `cart__pending`, `catalog__per`, los tres de complementos y el motivo del pago denegado. **Están sin
  arreglar a propósito** —son pantallas ya validadas— y son el candidato natural a un pulido del
  embudo.
- **`SidebarIconParityTest` estaba ciego a 10 de los 32 `.vue`** (`**` no es recursivo en `glob()`).

⚠️ **Antes de añadir NADA al cajón, mira su presupuesto.** Es la holgura más estrecha de todo el
ledger, y **la cifra viva NO se copia aquí**: vive en `SidebarBundleBudgetTest::SIDEBAR_CHUNK_MAX_KB`
con su ledger al lado —ya envejeció una vez en esta tabla—. El techo subió dos veces el 2026-08-23
(`#125` y `#126`), las dos con su medición y su párrafo, y las dos por **corrección**, no por features.
⚠️ **El 2026-08-26 cedió por una FEATURE** (`#166`, el waiver en el cajón: **+4,94 KiB**, 221,5 → 226)
**y lo decidió el owner**, no el agente: se le pusieron delante el número y las tres salidas —subir,
partir en un chunk aparte, aparcar— y eligió subir. **La regla sigue siendo ésa**: el agente no sube
este techo por una feature; **pregunta**, con la medida y el coste de cada alternativa. Quedan 0,28 KiB.

🟩 **«MIS RESERVAS» SE LISTA POR RESERVA** (`DECISIONES #126`, `specs/mis-reservas-por-reserva.md` ✅):
una tarjeta por reserva con la referencia de su pedido, las vivas de la más próxima a la más lejana, el
historial en su propia zona tras un CTA y atenuado, **5 por página ordenadas en el SERVIDOR**
(`GET /api/v1/me/reservations/{scope}`).
⚠️⚠️ **Lo que no se puede no saber antes de tocarlo**: los dos ámbitos son **los dos lados de UN
predicado** (`where`/`whereNot` sobre la misma expresión), **no dos consultas**. Si alguien las separa,
una reserva puede **no salir en ninguna de las dos pantallas** — y eso no falla, no avisa y no se ve:
una lista a la que le falta una fila se lee perfectamente. Lo sostiene `Sales\CustomerReservationsPageTest`,
que asevera la PROPIEDAD y no una lista de casos.
⚠️ **El ledger NO viaja con la tarjeta**: es del pedido y se repetiría tantas veces como reservas tenga.
Se pide con `GET /orders/{code}` al desplegar «Ver pedido».

⚠️ Y sigue abierta la decisión aplazada de `specs/area-cliente.md` §3.4: **si la zona activa cambia la
URL**. Hoy el «atrás» del navegador no hace nada dentro del cajón y una zona no se puede enlazar.

✅ **EL GUION DE NAVEGADOR ESTÁ AL DÍA** (2026-08-23, `#125`): `V17`, `V18` y `V23` —los tres que
`#122` y `#124` dejaron sin recorrer— **están HECHOS y en 60/60** tras arreglar los dos fallos reales
que destaparon. El qué pasó, en el tracker; el guion, en `VERIFICACION-E2E-CAJON.md` §5.septies.
⚠️ **Lo único que queda pide un DISPOSITIVO**: `V23·3` en **móvil real** (el scroll que arrastraba la
página) y `V20·6` con «reducir movimiento». Los dos necesitan staging — que **no lleva `#123`–`#126`**.

### Dónde está hoy «Mi cuenta»

🟩 **ENTERA en el cajón.** `/mi-cuenta` y `/mi-cuenta/pedidos` ya no pintan nada: sirven la home y el
cajón se abre solo en su zona (`Http\Sidebar\AccountDoor`).

| Zona del cajón | Qué cubre |
|---|---|
| `ORDERS` | «Mis reservas»: historial, ledger financiero completo, reintento y **las respuestas del pack bajo demanda** |
| `PROFILE` · `PASSWORD` · `SESSIONS` | Tus datos con el ciclo del correo pendiente · contraseña · cerrar las demás sesiones |
| `PRIVACY` | Consentimientos · descargar mis datos (art. 20) · borrar la cuenta (art. 17) |
| `HOME` | El índice, con su próxima reserva |

⚠️ **«Cerrar sesión» NO está en el índice, y es una decisión** (`#120(t)`, owner): sigue vigente.
⚠️⚠️ **Pero su premisa era FALSA y se corrigió el 2026-08-23**: decía «ya existe dos veces fuera, en el
nav y en el bloque `.acct`». Medido: **existe UNA**, la del bloque — `route('logout')` sale una sola
vez en toda la aplicación, y con sesión el nav es un botón que solo abre el cajón. La decisión no
cambia; el riesgo sí, y lo resuelve `specs/account-context-vue.md` §4.8.

⚠️ **Lo único que sobrevive de la web**: `GET /mi-cuenta/exportar`, que es una **DESCARGA** y no una
vista. Sirve el MISMO documento que `GET /api/v1/me/export`, y hay un test que los compara campo a
campo — es lo que impide que vuelvan a divergir.

### Lo hecho, en una línea por tanda

- 🟩 **Tanda 1 (leer)**: cinco pasos, `V4`–`V7`. `#120(g)`–`(m)`.
- 🟩 **Tanda 2 (gestionar)**: contraseña y sesiones, perfil, y los dos derechos RGPD. `V8`–`V10`.
  `#120(n)`–`(s)`. ▶ **Su efecto de fondo**: de los **cuatro** sitios de la web que reconfirmaban
  contraseña **sin techo**, no queda ninguno — y no se escribió una línea de limitador en la web.
- 🟩 **Tanda 3 (retirar)**: primero se publicó lo que solo sabía la página —el desglose financiero y
  los consentimientos— y **después** se borró. `V11`–`V13`. `#120(t)`, `#120(u)`.
  ▶ **La auditoría fue la mitad del trabajo**: encontró **dos huecos reales** que nadie vigilaba —el
  reintento de la API sin techo comprobado y las líneas fantasma que solo la API publicaba— y ambos
  se cerraron antes de borrar nada.

### Cinco cosas que condicionan lo que toques aquí

| | |
|---|---|
| **Las PUERTAS** | `/mi-cuenta` y `/mi-cuenta/pedidos` abren el cajón en su zona. ⚠️ La zona se aplica en `bootSpaEngine()` **y no en `open()`**: el cajón que llega por una puerta **nace abierto**. Es el camino que ya dejó un hueco vacío en `#59(b)` y volvió a morder en `#120(u)` |
| ⚠️ **La red** | **NO es el diff de árbol** (`#120(e)`): es paridad de DATOS contra la API + navegador. `render-sidebar.mjs` no importa la raíz |
| ⚠️ **La cadena flex** | `.sidecart__body` → `#sidecart-spa` → `.purchase` → `.purchase__scroll` son **hijos DIRECTOS**: un envoltorio router la parte y **ningún test lo ve** (`specs/area-cliente.md` §4.9) |
| ⚠️ **Los presupuestos** | ⚠️⚠️ **NO se copian aquí los números: viven en su test y esta tabla ya envejeció una vez.** Decía 190/4.800 cuando el código llevaba un día en **199 KiB** y **5.720 B** —la sesión de la auth los subió y nadie refrescó esta fila (medido el 2026-08-23)—. Los VIVOS son `SidebarBundleBudgetTest::SIDEBAR_CHUNK_MAX_KB` y los dos techos de `SidebarMountTest` (anónimo y con sesión), cada uno con su ledger al lado. Lo siguiente que entre los sube **a propósito, con su medida y su párrafo**, y al cerrar **baja a lo medido** |
| ⚠️ **El techo de componentes** | 40 líneas por `.vue`. Ya obligó al rediseño correcto una vez (`#120(r)`): si vuelve a apretar, la pregunta es qué sobra ahí, no cuánto subirlo |

⚠️ **Y una regla de trabajo que esta fase dejó pagada con tres fallos**: en un refactor o una feature
del ORQUESTADOR, **el contrato de árbol no es red** —`render-sidebar.mjs` no importa la raíz y su
comentario dice por qué—. La red es el NAVEGADOR. Receta del andamio, con sus trampas medidas, en
`VERIFICACION-E2E-CAJON.md` §5.bis y §5.quater.

### ❗ Bloqueado, y lo desbloquea el owner

❗❗ **EL SCHEDULER NO CORRE EN STAGING** (`DECISIONES #115`). El crontab está instalado y correcto y
`schedule:run` funciona a mano, pero **no hay demonio cron en el contenedor del sitio**. Medido: 6
avisos con 24 h en `jobs` y `attempts = 0`, y un pedido 24 h sin caducar que `orders:expire` caducó al
instante al lanzarlo a mano.
⚠️ **RE-CONFIRMADO en los DOS despliegues del 2026-08-25** (y antes el 2026-08-23): el propio
`deploy.sh` reinstaló el crontab
—«1 entrada, sin duplicados»— y su verificación de salud volvió a avisar de que **no se ve ningún
demonio cron**. Las cinco tareas están REGISTRADAS en la app y no hay jobs varados, así que lo único
que falta es quien las dispare.
▶ **La entrada exacta que hay que poner en el panel de Enhance, y cómo comprobar que funciona, están
en `ENTORNOS.md` §4.** Mientras tanto se dispara a mano:
`ssh jumpweb-staging "cd ~/public_html && php artisan schedule:run"`.
⚠️ Obliga a matizar `#110`: sus cuatro caminos siguen valiendo —ninguno depende del cron— pero **allí
nunca se ha ejercitado la caducidad de pedidos ni el envío diferido de correo**.

▶ Y luego, `scripts/provision.sh` (`#102(f)`), que necesita un token nuevo del panel: el que se usó
para medir lo retiró el owner.

⚠️ **SIETE trampas MEDIDAS que condicionan lo que venga.** No se explican aquí —cada una tiene su
sitio y duplicarlas es lo que envejece esta foto—; se nombran para que no te pillen:
- **Un `assertSee` de un texto del grupo `tickets` contra una página completa NO PRUEBA NADA**: el
  montaje del cajón lo lleva entero en cada página. Receta y porqué: `TESTING.md` **§2.ter**.
- **Lo que un gate declara que NO mira es un hueco con nombre** — así se sirvieron 20 iconos vacíos:
  `TESTING.md` **§2.quater** y `DECISIONES #113`. ⚠️ **Y a veces el gate ni lo declara**: el contador
  de `SidebarComponentBudgetTest` miraba `api.get|post` y no los tres verbos que llegaron después
  (`#120(s)`). Al añadir una pieza, relee qué mide su guarda — no si sigue verde.
- **Un campo que NUNCA lleva valor se lee como un dato y no lo es**: el export publicaba
  `tickets[].code` con una columna que no existe, desde el commit fundacional (`#120(s)`).
- **Una comprobación que mide una cosa y se lee como otra es PEOR que no tenerla**: dos señales de
  salud en verde con el scheduler muerto (`#115`).
- **Que las piezas se llamen no significa que el valor LLEGUE**, y que los dos extremos estén probados
  no significa que el medio esté cableado: `#117`, `#118` y `#119(f)` son tres fallos vivos distintos
  de la misma familia, todos encontrados en un navegador y ninguno por la suite.
- ✅ **El modo `embedded` de `auth.login`/`auth.register` MURIÓ el 2026-08-23** (`#122`), como `#112(f)`
  anticipó: era la referencia de dos paridades de árbol y las dos se fueron con él. De sus 14 casos,
  **8 no comparaban superficies** y están mudados a `SidebarMountTest`, `Api\V1\AuthRegistrationTest`
  y `SidebarAntiBotTest` — uno de ellos llevaba dentro el techo del payload del montaje.

### Lo que NO depende de nosotros

- ✅ **Servidor de PRUEBAS**: `jumpweb.sites.aelium.app` (`#76`), **desplegado y sirviendo** (`#106`).
  **0 LIVE · 0 PRODUCCIÓN.** Las cuatro cosas que estaban atascadas por falta de URL pública
  —Turnstile, S2S, 3DS y móvil— **están verificadas** (`#110`).
  ⚠️ **Dos diferencias con local que siguen condicionando el trabajo** (`ENTORNOS.md` §4): la BD es
  **MariaDB 11.4, no MySQL 8.4** —«verificado en staging» **NO** equivale a «verificado en MySQL», y
  ninguna conclusión sobre concurrencia sale de ahí— y **no hay node/npm**, así que los assets se
  construyen fuera y se suben compilados.
  ⚠️ **El bucle de trabajo sigue siendo LOCAL**; staging se toca EN BLOQUE y con guion escrito
  (`VERIFICACION-E2E-CAJON.md` §5.ter).
- **Pendiente del owner** (❗), por gravedad:
  1. ❗❗ **ACTIVAR LAS TAREAS PROGRAMADAS** del sitio en el panel de Enhance — sin cron no hay envío de
     correo ni caducidad de pedidos (`#115`). La entrada exacta, en `ENTORNOS.md` §4.
  2. Un **token nuevo de la API del panel** para `scripts/provision.sh` (el de medir se retiró).
  3. ❗ **Una pasada por el SANDBOX de Redsys para el reembolso REST de punta a punta**: que
     `Redsys::executeRefund()` hable de verdad con la pasarela y su respuesta se parsee bien. La
     auditoría del desglose lo dobló a propósito —una auditoría de dinero no hace llamadas externas—
     y **local no lo puede probar**. Herramienta canónica: `php artisan redsys:verify-sandbox`
     (`PAY-08`). Es el único hueco de esa auditoría que no se cerró.
  3. 2FA del panel · backlog de producto de Fase 6.
  ✅ Resueltos: el acceso SSH del 2º puesto (2026-08-21) · **las dos comprobaciones de navegador que
  cerraban `4.7`** (2026-08-22) · **la spec del área de cliente**, validada el 2026-08-22 —modelo de
  navegación y modo `account`, `#120(d)`— · y las **cuatro decisiones de producto** que el área pidió
  sobre la marcha: publicar la entrada de verdad en el export (`#120(s)`), no llevar «Cerrar sesión»
  al índice, publicar los consentimientos y enseñar las respuestas del pack bajo demanda (`#120(t)`,
  `#120(u)`).

## ▶ Hasta dónde llega hoy el motor SPA, dicho sin optimismo

El cajón **recorre el embudo entero y vuelve**: catálogo → día → hora →
cantidad → complementos → carrito → identificarse (entrar o **crear cuenta** dentro del cajón) → pagar
→ auto-POST firmado a Redsys → y los **tres desenlaces** (reserva creada con su resumen, rechazo con su
motivo y reintento, y el sondeo cada 5 s del terminal *data-less*). Con las reservas pausadas sustituye
el flujo por el aviso de mantenimiento. La cesta sobrevive a la recarga.

⚠️ **Residual de la pausa**: el estado se relee al cargar la página, en cada apertura del cajón y al
pulsar «Ir a pagar». Un cajón ABIERTO y quieto no se entera del interruptor hasta cerrarlo, reabrirlo o
intentar pagar.

## ▶ El MAPA del cajón SPA — **movido**

Vive en `docs/specs/sidebar-spa.md` §8 desde el 2026-08-25. Un mapa de ficheros es referencia para
quien toca el cajón, no «dónde estamos»: en la foto viva solo engordaba la carga obligatoria de cada
arranque.

## ▶ Lo que NO hay que reimplementar (el terreno del dinero está entero)

- **Precio** → `Booking\Contracts\CartPricing`. `CartPricerTest` compara sus importes con el pedido
  REAL: es el espejo verificado de `OrderCreator`.
- **Admisión** → `Booking\Contracts\ReservationAdmission`: pausa, tope de pendientes, frecuencia y la
  extensión atómica del hold. `POST orders` llama a `admitReservation()`, que CONSUME ficha; el
  reintento, a `admitPaymentRetry()`.
- **Creación** → `OrderCreator` (`AFORO-01`: el lock con `zone_id` literal es la PRIMERA sentencia de la
  transacción; no metas ningún SELECT antes).
- **Ida del pago** → `Booking\Contracts\PaymentInitiation` (`open()`/`reopen()`), implementado por
  `Payments\Services\PaymentInitiator`. Lanza `PaymentInitiationException`, que vive en
  `Payments\Contracts` porque es lo que lanza el puerto.
- **LA SECUENCIA** → `Booking\Contracts\ReservationCheckout` sobre `CheckoutOrchestrator` (`#37`). **Es
  el sitio ÚNICO donde vive el orden**: admitir consumiendo ficha → crear con la ventana de retención
  (`AFORO-10`) → abrir el cobro sobre el pedido persistido → soltarlo **solo** si era el primer intento.
  ⚠️ **No lo reescribas en una superficie nueva**: pide `start()`/`retry()` y traduce el resultado
  (`CheckoutSequenceTest` lo prohíbe ejecutablemente fuera de `app/Domain`).
  ⚠️ **No envuelvas la secuencia en una transacción**: el rastro de incidencia haría rollback (`PAY-05`)
  y el lock de franjas quedaría sostenido durante la firma (`AFORO-01`).
- **Oferta de fechas/horas** → `Booking\Contracts\AvailabilityOffer` sobre `SlotOffer` (`AFORO-02`), con
  la cesta descontada. Publica DOS números: `available` para MOSTRAR y `max_quantity` para ACOTAR el
  selector — en un pack **no coinciden**.
- **Desenlace del pago** → `GET orders/{code}/payment-status`, con dos ejes (`order_status` ·
  `payment_status`) y el motivo del rechazo como código y como texto.
- **Errores de negocio** → `Http\Api\ReservationErrorMap`. Añadir un código es evolutivo; **partir uno
  existente rompe a todo cliente ramificado sobre él**.
- **La cesta que viaja por la API** → `Http\Api\CartPayload`, una sola forma para los tres endpoints.

⚠️ **Tres trampas de la API que la SPA pisa** (las **87** medidas están en `specs/api-v1.md` §10):
`Origin`/`Referer` hacen falta en TODAS las peticiones stateful, no solo en el login (§10.sexies 28) ·
la disponibilidad LLEVA la cesta y publica dos números (§10.nonies 46) · **la firma cubre la URL
EXACTA**, así que las URLs de API se firman aparte (§10.duodecies 64).

**Pendiente que hereda Fase 6** (`#35`): un cliente NATIVO averigua el desenlace del pago **solo
sondeando** `payment-status`, y eso exige `redsys_merchant_url` configurada — sin ella y con terminal
data-less, el pedido caducaría con la tarjeta ya cobrada (`PAY-02`).

## ▶ Índice de la Fase 4 — **retirado**

⚠️ Eran 83 líneas que **duplicaban `00-REFACTOR.md`**, y la Fase 4 está CERRADA. Verificado antes de
borrar: los once pasos (`4.0a` … `4.7`) están en el tracker, cada uno con más detalle del que había
aquí. Una foto viva que repite el tracker es una segunda verdad esperando a divergir —y `CONVENCIONES`
dice que ESTADO **resume** el tracker y nunca lo contradice—.

▶ Para el detalle paso a paso: `docs/00-REFACTOR.md`, sección **Fase 4**.
