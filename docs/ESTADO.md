# Estado del proyecto — foto viva

> Documento CORTO (carga obligatoria al arrancar). Solo «dónde estamos / qué sigue».
> El «qué pasó» de cada paso vive en `00-REFACTOR.md` (tracker) y `DECISIONES.md` (el porqué):
> aquí solo se enlaza. Última actualización: **2026-08-15**.

## ▶ Dónde estamos

**Fase 0 ✅ · Fase 1 ✅ · Fase 2 ✅ · Fase 3 (API v1) ✅ · Fase 4 (sidebar SPA) 🟦 — EN CURSO.**

**Fase 4**: 4.0a–4.0c ✅ · 4.1 ✅ · 4.2 ✅ · 4.3 ✅ · 4.4a ✅ · 4.4b·1 ✅ · 4.5 ✅ · 4.6 ✅ →
**los ONCE pasos están transcritos** y el extremo a extremo con navegador y pasarela real ya se hizo
(`#59`). Queda **4.7** (la retirada de `Purchase.php`, EN CURSO) y **4.4b·2** (Turnstile, bloqueado en
claves de Cloudflare). El corte del diseño está en `docs/specs/sidebar-spa.md` §4.10.

⚠️ **Los pasos se parten por DEPENDENCIA, no por pantalla** — es la regla que ha ordenado toda la fase.
El detalle de cada corte está en el tracker; el índice de abajo enlaza cada uno con su decisión.

- Suite **2715 en verde** (15.680 aserciones, `--parallel` ~80 s) · **287 tests JS** (`node --test`) ·
  Pint limpio · `docs-check` verde · `composer audit` y `npm audit` en **0** · `npm run build` OK.
  El contador «PHPUnit Notices: 1» sale solo en la paralela completa y es del runner (ver `TESTING.md`).
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
  ⚠️ **El gate NO cubre las superficies web** (`Livewire\Tickets\Purchase`, `RetryPaymentController`):
  córrelos igual aunque el hook no lo pida.
- **Fase 2 dejó tres cosas que se usan al tocar código hoy** (el resto, en `specs/modulos-dominio.md`):
  `php scripts/module-deps.php [Clase…]` mide las dependencias INVISIBLES · *recibir* una entidad de
  otro módulo es costura de BD, *consultar* sus datos o *repetir* sus reglas exige contrato · las
  baselines del arch-test **solo encogen**.
- ⚠️ **`Sidebar.vue` es el segundo objeto-dios, y ya está VIGILADO** (`DECISIONES #90`): 618 líneas de
  código y las 11 llamadas a la API del cajón, con los mismos métodos que `Purchase.php`. Los otros 18
  componentes suman 236 y ninguno toca la API — **CE-6 lo cumplen 18 de 19**. Desde hoy lo guarda
  `SidebarComponentBudgetTest` (techo por componente + excepción declarada que **solo encoge**), así
  que la deuda deja de crecer. La extracción —patrón `admission.js::runCheckout()`, ~10 secuencias— va
  en `DEUDA.md` como Alta, **fuera de 4.7** a propósito.
- ⚠️ **NOTA DE DESPLIEGUE permanente**: las migraciones corren **ANTES** de servir tráfico (el morphMap
  de Fase 2 es requisito) y hay que **drenar la cola + `queue:restart`** (los payloads serializados
  llevaban los FQCN viejos).

## ▶ Decisión de producto VIGENTE que enmarca todo lo demás

⚠️ **El cajón es el ÁREA DE CLIENTE, no el embudo de compra** (`DECISIONES #66`, owner). Toda la
gestión del cliente vivirá dentro del cajón: entrar y darse de alta, sus entradas y reservas, y las
gestiones de cuenta. Hoy está repartido en tres sitios —el cajón, el modal de auth de la cabecera y las
páginas `/mi-cuenta/…`— y el destino es UNO.

- **El orden es dependencia, no preferencia**: **4.7** → **Turnstile** → **área de cliente**. El modal
  de la cabecera no se puede retirar antes de Turnstile porque es el único que monta el widget y el
  alta del cajón **delega en él** cuando el anti-bot está activo.
- ⚠️ **Lo que obliga a NO hacer desde hoy**: `machine.js` modela once pasos NUMERADOS de un embudo. Un
  área de cliente no es un embudo, así que **no se puede estrechar más la máquina** ni añadir supuestos
  de «siempre se viene del paso anterior». Rediseñar los estados es el primer trabajo de esa fase.
- **El servidor ya está** (medido contra `openapi/v1.yaml`): `/auth/*`, `/me`, `/me/orders`,
  `/me/reservations`, `/me/reservation-eligibility` y el post-form por firma existen y están probados
  desde Fase 3. Hay que pintar, no abrir dominio.

## ▶ Próximo paso

**4.7 · la retirada de `Purchase.php`.** Lo ordena un número: **`PurchaseRetirementTest` declara 20
dependientes** (eran 32 al corregir el escáner). ⚠️ **Pero la meta NO es 0** — ver abajo.

**Las nueve paridades: auditoría CERRADA** (histórico, no hay nada que hacer aquí). Van **ocho
auditadas**: seis fuera del inventario
—`SidebarPayParityTest` (`#75`), `SidebarAddonsParityTest` (`#77`), `SidebarAdmissionParityTest`
(`#78`), `SidebarCartParityTest` (`#80`), `SidebarPausedParityTest` (`#81`) y
`SidebarProgressParityTest` (`#82`)— y dos que **mueren con el componente** y por tanto no bajan el
contador: `SidebarCalendarParityTest` (`#79`) y `SidebarDomContractTest` (`#83`), las dos operando
DENTRO en ·2b·3, no borradas enteras.

✅ **El tramo `·C` —los dependientes que NO son paridades— también está CERRADO** (`#86`→`#98`): los
**once** auditados, y con ellos **todas las entradas del inventario están CLASIFICADAS**. Contador 21 → **19**.

⚠️⚠️ **Esa cifra es la esperada, no un fracaso.** `#86` midió que la mayoría de lo que queda tiene por
sujeto la SUPERFICIE del motor —`PurchasePanelTest` (40 casos), el diff de árbol (34), el spinner…— y
eso **solo puede salir del inventario en el commit que borra el componente**. La meta nunca fue un 0;
era **«todo clasificado»**, y ya está. Escrito también en el docblock de `PurchaseRetirementTest`.

**Lo que el tramo produjo de verdad, que no es el contador:**
- **Tres huecos reales cerrados**, los tres del mismo tipo —campos que el servidor PUBLICA y cuyo único
  test conducía la UI—: las reglas `can_*` de los complementos (`#89`), pagar sin el correo verificado
  (`#93`) y el `type` del catálogo, que decide en qué sección aparece cada producto (`#96`).
- **Un falso positivo evitado** (`#92`): parecía que el límite anti-abuso de crear reservas no lo
  guardaba nadie. Lo guardan nueve casos; la mutación estaba mal apuntada.
- **Y `CE-6` con dientes** (`#90`), que salió de una observación del owner: `Sidebar.vue` era el segundo
  objeto-dios y nada lo vigilaba.

✅ **El bloqueador `#74` está RESUELTO** (`#99`): `SidebarTokenBudgetTest` ya no rasca el Blade —su
ámbito es por familias del cajón— y salió del inventario. Era lo único que impedía borrar.

▶ **EMPIEZA AQUÍ: 4.7·2b·3 — borrar el componente.** Ya no falta clasificar nada ni decidir nada. El tramo consiste en
un commit único con `Purchase.php`, `purchase.blade.php`, el placeholder, la línea de
`layout.blade.php`, el puente `$wire.step`↔store y **todos los tests que mueren con él**.

⚠️ **Tres ficheros exigen operar DENTRO, no borrarlos enteros**, y cada uno lleva las instrucciones en
su propio docblock: `SidebarCalendarParityTest` (`#79` — sobrevive el caso de los husos),
`SidebarDomContractTest` (`#83` — tres operaciones exactas, incluida **mover el anclaje del manifiesto
a `$vue`**) y `ReservationPauseTest` (`#98` — se quedan sus seis casos de `MaintenanceSettings`).
`DepositSurfacesTest` y `ModuleContractsTest` también conservan casos.

⚠️ **Y arrastra el modo `embedded` de la auth**: `purchase.blade.php` es el ÚNICO sitio que lo monta.
Decidir si se retira ahí o en Fase 5 es parte del tramo, no un descubrimiento del final.

### Lo que enseñó la auditoría, y sigue valiendo

**La regla de clasificación** (`#75`, `#86`): separa lo que **compara entre motores** (muere), lo que
**afirma del contrato** (se queda) y lo que usa el motor viejo como **intermediario de una fuente que
sobrevive** (se re-apunta). Y hazle la pregunta al **SUJETO del caso, no a la regla que menciona**
(`#87`): una regla que sobrevive no salva un caso que prueba una superficie que se va.

**Se hace MUTANDO, no leyendo**, y estas son las cuatro trampas que costaron tiempo — todas de errores
propios:
- comprueba **dónde cayó** la mutación: un nombre puede aparecer dos veces en el fichero (`#77`);
- comprueba el **contenido**, no el código de salida: `git checkout` no revierte un fichero sin
  trackear (`#90`);
- exige que el **ancla sea única** antes de creerte un hueco: una mutación mal apuntada puede dar un
  resultado **coherente con la hipótesis equivocada** (`#92`);
- no muteS **una rama** de una propiedad de indistinguibilidad —la anti-enumeración— porque el verde no
  dice nada (`#95`).

**Y dos criterios que se ganaron midiendo:**
- **Si un dato viaja al cliente y su único test conduce el motor viejo, el contrato NO lo está
  fijando** — así salieron los tres huecos.
- **Un `⚠️ sin medir` en un fichero condenado es deuda con fecha de caducidad** (`#93`): las dos veces
  que se cerró uno, había hueco.
- **Tras iterar sobre `resources/js/`: `npm run build:ssr`.** La guarda del bundle rancio saltó cuatro
  veces en tres días.

**Notas por fichero que ·2b·3 necesita** (el detalle, en el tracker y en cada docblock):
- `SidebarSeamTest` **no se puede re-apuntar**: su exclusión `ENGINE_VIEW` desaparece sola con el Blade.
- `SpinnerTest`, `DuplicateEmailEdgeCaseTest` y `ReservationPauseGuardTest` **mueren enteros**, cada uno
  con su cobertura equivalente ya localizada.

⚠️ **Y una lección de handoff que salió cara** (`#83`): esta misma nota afirmaba durante dos días que
`SidebarDomContractTest` podía convertirse ya en «Vue contra el manifiesto» y que eso decidía sobre otra
paridad. Las dos mitades eran falsas, y estaban escritas **leyendo el código**. **Una nota de handoff
escrita leyendo es una hipótesis, no un plan** — márcala como tal o mídela antes de dejarla.

### Lo que NO depende de nosotros

- ✅ **Ya hay servidor de PRUEBAS**: `jumpweb.sites.aelium.app` (`DECISIONES #76`, reglas en
  `docs/ENTORNOS.md`). **0 LIVE, 0 PRODUCCIÓN.** Desbloquea las cuatro cosas que estaban atascadas por
  falta de URL pública: **Turnstile** (4.4b·2), la **notificación S2S** de Redsys, el **3DS con
  challenge** y el **móvil real**.
  ⚠️ **Pero `#62` NO se reabre**: retiró «esperar a que ruede en producción» por vacía, y un staging
  **no tiene tráfico**. Lo que ordena 4.7 sigue siendo el CONTADOR, no el calendario.
  ⚠️ **El bucle de trabajo sigue siendo LOCAL**; staging se toca en bloque y con guion.
  ▶ **La máquina ya está MEDIDA** (`ENTORNOS.md` §4, acceso por clave verificado). Dos diferencias con
  local que condicionan el trabajo: **la BD es MariaDB 11.4, no MySQL 8.4** —así que «verificado en
  staging» **NO** equivale a «verificado en MySQL», y ninguna conclusión sobre concurrencia sale de
  ahí— y **no hay node/npm**, así que los assets se construyen fuera y se suben compilados.
  ▶ Pendiente: el **procedimiento de despliegue**, que cierra el `[DECISION-PENDIENTE]` de
  `INSTALACION-CLIENTE.md` §1 y se escribe **midiendo**, no a ojo.
- **Pendiente del owner** (❗): 2FA del panel · mecanismo del primer admin (`INSTALACION-CLIENTE.md` §5)
  · backlog de producto de Fase 6.

## ▶ Hasta dónde llega hoy el motor SPA, dicho sin optimismo

Con `sidebar.engine = spa` el cajón **recorre el embudo entero y vuelve**: catálogo → día → hora →
cantidad → complementos → carrito → identificarse (entrar o **crear cuenta** dentro del cajón) → pagar
→ auto-POST firmado a Redsys → y los **tres desenlaces** (reserva creada con su resumen, rechazo con su
motivo y reintento, y el sondeo cada 5 s del terminal *data-less*). Con las reservas pausadas sustituye
el flujo por el aviso de mantenimiento. La cesta sobrevive a la recarga.

⚠️ **Pero el flag NO está activado: su default es `livewire` y ese es el motor que vende.** Lo que falta
para activarlo es Turnstile y los tres caminos de navegador de arriba, no pantalla.

⚠️ **Residual de la pausa**: el estado se relee al cargar la página, en cada apertura del cajón y al
pulsar «Ir a pagar». Un cajón ABIERTO y quieto no se entera del interruptor hasta cerrarlo, reabrirlo o
intentar pagar; Livewire sí, porque reevalúa su guarda en cada render.

## ▶ El MAPA del cajón SPA (para no buscarlo a ciegas)

`resources/js/sidebar/` — **la lógica vive en módulos PLANOS sin Vue** (`CE-6`), y los componentes solo
pintan. Esa separación es lo que hace que todo lo de abajo se pruebe con `node --test` y se compare
contra el servidor desde PHP ejecutándolo en Node.

| Módulo | De qué responde | Su red |
|---|---|---|
| `machine.js` | En qué paso está el cajón y a cuál puede ir | `machine.test.js` · `SidebarProgressParityTest` |
| `api.js` | El cliente HTTP y sus cuatro trampas medidas (cookie, `Accept`, CSRF url-decodificado, reintento del 419) | — ⚠️ **sin test propio** |
| `i18n.js` · `money.js` | Textos por CAMINO con plural de Laravel · importes que espejan `number_format` | `SidebarTextParityTest` · `SidebarMoneyParityTest` |
| `catalog.js` | El paso 1: agrupar el catálogo en secciones y renombrar campos | `catalog.test.js` · **el diff de árbol lo EJECUTA** (`#67`) |
| `calendar.js` | La rejilla del mes, los meses navegables y el mes en que abre | `calendar.test.js` (18 casos, `#68`) · el diff lo EJECUTA |
| `offer.js` | El paso 3: hora elegida, suelo y techo del selector, precio del día | `offer.test.js` (`#69`) · el diff lo EJECUTA |
| `progress.js` · `foot.js` | La banda de fases · el pie de cada paso | sus `*.test.js` · **el diff los EJECUTA desde `#71`** · `SidebarCartParityTest` |
| `cart.js` | Cesta: saneado, persistencia con su dueño, reconciliación y **qué respuestas faltan** | `cart.test.js` · `SidebarCartParityTest` · `SidebarPendingFieldsParityTest` |
| `paused.js` | El aviso de reservas en pausa y en qué pasos tapa | `paused.test.js` · `SidebarPausedParityTest` |
| `admission.js` | El paso del carrito al pago: identidad + elegibilidad + destino | `admission.test.js` · `SidebarAdmissionParityTest` |
| `login.js` · `register.js` | Identificarse y darse de alta desde el cajón | sus `*.test.js` · `SidebarLoginParityTest` · `SidebarRegisterParityTest` |
| `pay.js` | Crear el pedido y componer el formulario firmado de la pasarela | `pay.test.js` · `SidebarPayParityTest` |
| `outcome.js` | La VUELTA entera: resumen del 6, motivo del 10 con su reintento, sondeo del 11 | `outcome.test.js` · `SidebarOutcomeParityTest` |
| `store.js` | El estado compartido (Pinia) y la secuencia de montaje | `store.test.js` (nace tras `#59`) |

Fuera de `sidebar/`: **`resources/js/ui/scroll-lock.js`**, el dueño ÚNICO de `body.no-scroll` con llaves
por superpuesto. Lo vigila `ScrollLockOwnerTest`; nadie más puede tocar esa clase (`#58`).

⚠️ **La regla que enseñaron las paridades**: cuando algo NO es atributo de contrato del normalizador
—`href`, `action`, `method`, los `name` de un formulario, **el texto**— el diff de árbol **lo da por
bueno**. Si transcribes algo de esa clase necesita paridad propia. Demostrado por mutación en el paso 9.

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

⚠️ **Tres trampas de la API que la SPA pisa** (las 83 medidas están en `specs/api-v1.md` §10):
`Origin`/`Referer` hacen falta en TODAS las peticiones stateful, no solo en el login (§10.sexies 28) ·
la disponibilidad LLEVA la cesta y publica dos números (§10.nonies 46) · **la firma cubre la URL
EXACTA**, así que las URLs de API se firman aparte (§10.duodecies 64).

**Pendiente que hereda Fase 6** (`#35`): un cliente NATIVO averigua el desenlace del pago **solo
sondeando** `payment-status`, y eso exige `redsys_merchant_url` configurada — sin ella y con terminal
data-less, el pedido caducaría con la tarjeta ya cobrada (`PAY-02`).

## ▶ Índice de la Fase 4, paso a paso

El «qué se hizo y qué enseñó» de cada paso NO se repite aquí: vive en `00-REFACTOR.md` (checklist
verificable) y en `DECISIONES.md` (el porqué, con sus mediciones). Este índice es solo para llegar.

| Paso | Qué cerró | Decisión |
|---|---|---|
| 4.0a–4.0c | La costura, los seis huecos de API, la tokenización de `site.css` | `#39`–`#42` |
| 4.1 · 4.2 | Cimientos SPA · los tres primeros pasos con paridad de árbol | `#43`–`#46` |
| 4.3·1 → ·4 | Armazón y textos · pie y cesta · aviso de pausa · persistencia | `#47`–`#50` |
| 4.4a·1 · ·2 | El CTA de pagar decide · la pantalla de identificación | `#51`, `#52` |
| 4.4b·1 | El alta desde el cajón (y tres bugs de servidor que destapó) | `#53` |
| 4.5·1 · ·2 | La cesta pide lo que le falta · pagar y salir a la pasarela | `#54`, `#55` |
| 4.6·1 · ·2 | La reserva creada · denegado y verificando | `#56`, `#57` |
| §6 | Bloqueo de scroll con dueño único · **el extremo a extremo con navegador** | `#58`, `#59` |
| 4.7·1 · ·2a | El manifiesto congelado · el inventario deja de crecer | `#60`, `#61` |
| 4.7·2b·1 | El contador medía 25 de 32 · la suite dependía de la fecha | `#63`, `#64` |
| 4.7·2b·2 | Re-apuntar al servidor: `RedsysIdaTest`, `ModuleContractsTest`, `SidebarPayParityTest` | `#65`, `#75` |
| 4.7·2b·2·B | **El diff de árbol se alimenta del SERVIDOR** en los once pasos y el armazón | `#67`–`#73` |

⚠️ **Las lecciones transversales que más se repiten**, por si solo lees esto:
**verde no es funciona** (`#59`: la fase entera transcrita y el motor no vendía) · **una foto que
incluye el tiempo hay que tomarla con el reloj parado** (`#64`) · **un test que compara contra un
artefacto tiene que comprobar que no está rancio** (`#69`) · **al re-apuntar un caso hay que volver a
mutarlo** (`#65`: pasó a ser inerte sin que nadie lo notara) · **el caso frontera se elige por el
MECANISMO del fallo, no por el síntoma** (`#68`).

## Entorno (local)

- Docker (Sail) en WSL2, repo en `~/proyectos/jumpweb`. Web `localhost:8081` · MySQL `localhost:3308` ·
  Mailpit `localhost:8028`. **Siempre `-u sail` en `exec`** (como root deja ficheros de root en
  `storage/` → 500 por permisos).
- BD dev sembrada con SaltoPark: `admin@jumpweb.test` / `empleado@jumpweb.test`, contraseña `password`.
  ⚠️ La BD dev arrastra ADEMÁS el par `…@jumpingjump.test` del import, así que `User::first()` devuelve
  uno del origen — usa el email completo al probar a mano.
- ⚠️ Si clonas de cero, comprueba que **`APP_URL` coincide con `APP_PORT`** en el `.env` (no versionado):
  con el puerto desalineado salen mal los enlaces absolutos de correo, las URLs firmadas y la derivación
  de CORS y de los dominios stateful de Sanctum.
- **El push exige `VERIFY_CONC=1`** —tras correr los dos comandos de `INVARIANTES §6`— si tocas el
  núcleo de dinero/aforo.

## Herencia

Base heredada del origen (2026-08-12): 30 modelos, 71 migraciones, 17 Filament Resources, Redsys en
sandbox y suite **2132** verde al importarla.
Recuento VIVO (lo verifica `docs-check` contra el código): 30 modelos · 72 migraciones ·
17 Filament Resources · **2715** tests. La migración añadida es `personal_access_tokens` (Sanctum).
Stack: Laravel **13.25** · Filament **5.7** · Livewire **4.4** · PHPUnit 12.5 · Sanctum **4.3** ·
Spectator **3.0** (dev) · Vite **8.2** · 0 avisos de seguridad (`composer audit` y `npm audit`).
