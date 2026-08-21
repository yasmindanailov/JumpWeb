# Estado del proyecto — foto viva

> Documento CORTO (carga obligatoria al arrancar). Solo «dónde estamos / qué sigue».
> El «qué pasó» de cada paso vive en `00-REFACTOR.md` (tracker) y `DECISIONES.md` (el porqué):
> aquí solo se enlaza. Última actualización: **2026-08-21** (staging desplegado y el scheduler destapado).

## ▶ Dónde estamos

**Fase 0 ✅ · Fase 1 ✅ · Fase 2 ✅ · Fase 3 (API v1) ✅ · Fase 4 (sidebar SPA) 🟦 — EN CURSO.**

**Fase 4**: 4.0a–4.0c ✅ · 4.1 ✅ · 4.2 ✅ · 4.3 ✅ · 4.4a ✅ · **4.4b ✅ (·1 y ·2)** · 4.5 ✅ · 4.6 ✅ ·
**4.7 ✅ en código y suite** → **los ONCE pasos están transcritos**, el extremo a extremo con navegador
y pasarela real se hizo (`#59`), **los cuatro caminos que `#100` exigía están VERIFICADOS en staging**
(`#110`) y **`Purchase.php` está RETIRADO** (`#112`). El corte del diseño está en
`docs/specs/sidebar-spa.md` §4.10.

⚠️ **La fase sigue 🟦 y no es formalismo**: quedan **DOS comprobaciones en navegador** que nadie ha
hecho —los enlaces profundos (A7) y los iconos de `#113`—, y el DoD (`CONVENCIONES §3.bis`) dice que
una feature visible **no es ✅ hasta que el owner la valida**. Las dos están en «Próximo paso».

🟩 **EL CAJÓN SPA ES EL MOTOR ÚNICO.** Con el componente se fueron su blade, el placeholder y el flag
`sidebar.engine`: **no hay vuelta atrás sin desplegar**, que es lo que `#100` pedía asegurar antes y
`#110` verificó. Está **en `main`**. ⚠️ La rama `wip/4.7-2b-3-retirada-purchase` **está fusionada y no contiene nada
que `main` no tenga** (`git log main..wip/… ` → 0): si `/arranque-sesion` la saca, esta línea es su
explicación. Se conserva solo por si alguien quiere leer el tramo commit a commit; **borrarla es
seguro**.

✅ **STAGING está desplegado, sirviendo el cajón SPA y con el anti-bot activo.** Canal de despliegue:
`scripts/deploy.sh` (dry-run por defecto). Detalle en `ENTORNOS.md` §4; el porqué, en `#105`–`#110`.
⚠️ **Pero lo que corre ALLÍ es anterior a los arreglos de hoy** —enlaces profundos e iconos—: ver
«Hasta dónde llega hoy el motor SPA».

⚠️ **Los pasos se parten por DEPENDENCIA, no por pantalla** — es la regla que ha ordenado toda la fase.
El detalle de cada corte está en el tracker; el índice de abajo enlaza cada uno con su decisión.

- Suite **2650 en verde** (15.262 aserciones, `--parallel` ~39 s medidos el 2026-08-21) ·
  **302 tests JS** (`node --test`) · Pint limpio (818 ficheros) · `docs-check` verde ·
  `composer audit` y `npm audit` en **0** · `npm run build` y `build:ssr` OK. El contador
  «PHPUnit Notices: 1» sale solo en la paralela completa y es del runner (ver `TESTING.md`).
  ✅ **Y desde el 2026-08-21 este número YA TIENE GUARDA**: el `pre-push` compara lo que acaba de dar
  la suite con lo que declara esta línea y **corta si no cuadran** (`DECISIONES #116`). Antes no lo
  vigilaba nadie —`docs-check` no lo mira— y derivó tres veces en un solo día.
  ⚠️ **Sigue siendo el ÚNICO sitio donde vive**: si lo duplicas en otro documento, esa copia no la
  guarda nadie.
  ⚠️ **Bajó de 2773 a 2642 a propósito**: la retirada de `Purchase.php` se llevó 135 casos cuyo sujeto
  era la superficie retirada, y entraron 4 nuevos (el velo de carga y los tres de la paridad de
  iconos). Ninguno se borró sin localizar y EJECUTAR antes su sucesor (`#112(a)`). Los **+3** hasta
  2645 son las guardas del canal de build (`#114`).
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
  ⚠️ **El gate NO cubre la superficie web del reintento** (`RetryPaymentController`): córrelo igual
  aunque el hook no lo pida. (Antes también quedaba fuera `Livewire\Tickets\Purchase`, retirado en
  `#112`; la compra pasa hoy por `POST /api/v1/orders`, que sí entra por el `CRITICAL_RE`.)
- **Fase 2 dejó tres cosas que se usan al tocar código hoy** (el resto, en `specs/modulos-dominio.md`):
  `php scripts/module-deps.php [Clase…]` mide las dependencias INVISIBLES · *recibir* una entidad de
  otro módulo es costura de BD, *consultar* sus datos o *repetir* sus reglas exige contrato · las
  baselines del arch-test **solo encogen**.
- ⚠️ **`Sidebar.vue` es el segundo objeto-dios, y ya está VIGILADO** (`DECISIONES #90`): concentra
  **todas** las llamadas a la API del cajón y los otros 18 componentes no tocan ninguna, así que
  **CE-6 lo cumplen 18 de 19**. Lo guarda `SidebarComponentBudgetTest` (techo por componente +
  excepción declarada que **solo encoge**), y **las cifras vivas están en su `EXCEPTIONS`**, no aquí:
  copiarlas a este documento es drift en espera, y ya había pasado. La extracción —patrón
  `admission.js::runCheckout()`, ~10 secuencias— va en `DEUDA.md` como Alta.
- ⚠️ **NOTA DE DESPLIEGUE permanente**: las migraciones corren **ANTES** de servir tráfico (el morphMap
  de Fase 2 es requisito) y hay que **drenar la cola + `queue:restart`** (los payloads serializados
  llevaban los FQCN viejos).

## ▶ Decisión de producto VIGENTE que enmarca todo lo demás

⚠️ **El cajón es el ÁREA DE CLIENTE, no el embudo de compra** (`DECISIONES #66`, owner). Toda la
gestión del cliente vivirá dentro del cajón: entrar y darse de alta, sus entradas y reservas, y las
gestiones de cuenta. Hoy está repartido en tres sitios —el cajón, el modal de auth de la cabecera y las
páginas `/mi-cuenta/…`— y el destino es UNO.

- **El orden es dependencia, no preferencia**: **4.7** → **Turnstile** → **área de cliente**.
  ✅ **Turnstile ya no ata nada** (4.4b·2, 2026-08-20): el cajón monta su propio widget y la delegación
  en el modal de la cabecera **está retirada**. ⚠️ Pero el modal **no se retira aquí ni en `4.7·2b·3`**:
  vive en `layout.blade.php`, no en `purchase.blade.php`, y sigue siendo la puerta de auth de la web
  fuera del cajón. Retirarlo es trabajo del área de cliente.
- ⚠️ **Lo que obliga a NO hacer desde hoy**: `machine.js` modela once pasos NUMERADOS de un embudo. Un
  área de cliente no es un embudo, así que **no se puede estrechar más la máquina** ni añadir supuestos
  de «siempre se viene del paso anterior». Rediseñar los estados es el primer trabajo de esa fase.
- **El servidor ya está** (medido contra `openapi/v1.yaml`): `/auth/*`, `/me`, `/me/orders`,
  `/me/reservations`, `/me/reservation-eligibility` y el post-form por firma existen y están probados
  desde Fase 3. Hay que pintar, no abrir dominio.

## ▶ Próximo paso

✅ **DESPLEGADO el 2026-08-21**: commit **`1977db7`** en staging, las **6 comprobaciones de salud en
verde**, y verificado por fuera del script —bundle nuevo servido, API en 200, las 3 zonas, Turnstile
todavía configurado y `Purchase.php` fuera del servidor—. 🟩 **El cajón SPA es ya el motor único
también ALLÍ.**

▶ **LO QUE QUEDA SON DOS COSAS, Y LAS DOS LAS TIENE QUE MIRAR EL OWNER EN UN NAVEGADOR.** Hasta que se
hagan, `4.7` **no es ✅** (`CONVENCIONES §3.bis`). **El guion está escrito y listo en
`VERIFICACION-E2E-CAJON.md` §5.quater** (V1 iconos · V2 enlaces profundos), con el «antes» medido a los
dos lados para que se sepa qué se está mirando:

| | Lo que se servía antes | Lo que se sirve ahora |
|---|---|---|
| Geometrías de iconos (`#113`) | **0** en 33 `<svg>` | **40** en 42 |
| Refs a `Livewire` en `app.js` (`#111(h)`) | **6** | **3** |

⚠️ **Las dos fallan de la misma forma —«no falla, no hace nada»— y por eso hay que saber qué esperar**:
en V2 el cajón SÍ se abre; lo que estaba roto es **dónde**. Si abre en el catálogo raíz en vez de en la
zona o en los packs, la regresión sigue viva.

❗❗ **BLOQUEO NUEVO Y SERIO, DEL OWNER: EL SCHEDULER NO CORRE EN STAGING** (`DECISIONES #115`). El
crontab está instalado y correcto y `schedule:run` funciona a mano, pero **no hay demonio cron en el
contenedor del sitio**: nadie lo invoca. Medido — 6 avisos con 24 h en `jobs` y `attempts = 0`, y un
pedido 24 h sin caducar que `orders:expire` caducó al instante al lanzarlo a mano.
⚠️ **Y el despliegue lo daba por SANO**: «5 tareas registradas» mide el REGISTRO, y `failed_jobs` es
**ciego** a esto —un job que nunca se intenta nunca falla—. Ya no: `deploy.sh` mide ahora la EDAD del
trabajo más viejo de la cola, con tres guardas mutadas en `DeployScriptGateTest`.
▶ **Lo tiene que activar el owner en el panel de Enhance** (tareas programadas del sitio). Mientras
tanto, en staging se dispara a mano: `ssh jumpweb-staging "cd ~/public_html && php artisan schedule:run"`.
▶ **Y obliga a releer `#110`**: sus cuatro caminos siguen valiendo —ninguno depende del cron— pero
**allí nunca se ha ejercitado la caducidad de pedidos ni el envío diferido**.

✅ **Dos cosas más que se arreglaron por el camino, las dos de despliegue** (`#114`): el canal de build
era «el npm que haya» y bajo WSL eso es el de **Windows**, que no puede construir (CMD.EXE no admite
rutas UNC) — ahora el canal canónico es **Sail**, el mismo que usa el `pre-push`, y el fallo enseña su
salida en vez de morir mudo. Y el **acceso SSH del 2º puesto**, que no existía: clave dedicada por
puesto (nunca copiada del otro), `known_hosts` fijado —`BatchMode=yes` no pregunta, muere— y el alias
por hostname para no clavar ninguna IP (`DECISIONES #1`).

Luego, `scripts/provision.sh` (`#102(f)`), que necesita un token nuevo del panel: el usado para medir
lo retiró el owner.

**Después, el orden lo manda `#66`**: el **área de cliente** dentro del cajón. Ni Turnstile (4.4b·2)
ni `4.7` atan ya nada.

⚠️ **Tres trampas MEDIDAS que condicionan lo que venga.** No se explican aquí —cada una tiene su sitio
y duplicarlas es lo que envejece esta foto—; se nombran para que no te pillen:
- **Un `assertSee` de un texto del grupo `tickets` contra una página completa NO PRUEBA NADA**: el
  montaje del cajón lo lleva entero en cada página. Receta y porqué: `TESTING.md` **§2.ter**.
- **Lo que un gate declara que NO mira es un hueco con nombre** — así se sirvieron 20 iconos vacíos:
  `TESTING.md` **§2.quater** y `DECISIONES #113`.
- **El modo `embedded` de `auth.login`/`auth.register` ya no lo monta nadie en producción**, pero es
  la REFERENCIA de `SidebarLoginParityTest`/`SidebarRegisterParityTest`: muere cuando el área de
  cliente rehaga la auth dentro del cajón, no antes (`#112(f)`).
- **Una comprobación que mide una cosa y se lee como otra es PEOR que no tenerla**, porque regala
  confianza que no ha ganado. Dos señales de salud en verde con el scheduler muerto: `DECISIONES #115`.

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
- **Pendiente del owner** (❗): ❗❗ **ACTIVAR LAS TAREAS PROGRAMADAS del sitio en el panel de Enhance**
  — sin cron no hay envío de correo ni caducidad de pedidos (`DECISIONES #115`); es lo más grave de la
  lista · 2FA del panel · backlog de producto de Fase 6 · un **token nuevo de la API del panel** para
  `scripts/provision.sh` (el usado para medir se retiró) · y **las DOS comprobaciones de navegador**
  de `VERIFICACION-E2E-CAJON.md` §5.quater, que son las que cierran `4.7`.
  ✅ Resuelto el 2026-08-21: el acceso SSH del 2º puesto (clave propia, no copiada).

## ▶ Hasta dónde llega hoy el motor SPA, dicho sin optimismo

El cajón **recorre el embudo entero y vuelve**: catálogo → día → hora →
cantidad → complementos → carrito → identificarse (entrar o **crear cuenta** dentro del cajón) → pagar
→ auto-POST firmado a Redsys → y los **tres desenlaces** (reserva creada con su resumen, rechazo con su
motivo y reintento, y el sondeo cada 5 s del terminal *data-less*). Con las reservas pausadas sustituye
el flujo por el aviso de mantenimiento. La cesta sobrevive a la recarga.

✅ **Y es el motor que se sirve en staging**, con el anti-bot activo y los cuatro caminos de navegador
verificados (`#110`). 🟩 **Desde `#112` es el ÚNICO**: no queda flag, ni segundo motor, ni vuelta atrás
sin desplegar.

⚠️ **OJO con lo que hay ALLÍ ahora mismo**: staging sirve una versión **anterior** a los dos arreglos
de esta sesión, así que **hoy tiene los enlaces profundos rotos y los iconos invisibles**. No es una
regresión nueva: es lo que lleva desde que se puso el flag en `spa`. Se corrige al desplegar, y es
justo lo que hay que mirar entonces (ver «Próximo paso»).

⚠️ **Residual de la pausa**: el estado se relee al cargar la página, en cada apertura del cajón y al
pulsar «Ir a pagar». Un cajón ABIERTO y quieto no se entera del interruptor hasta cerrarlo, reabrirlo o
intentar pagar.

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
—`href`, `action`, `method`, los `name` de un formulario, **el texto**, y **el interior de un
`<svg>`**— el diff de árbol **lo da por bueno**. Si transcribes algo de esa clase necesita paridad
propia. Demostrado por mutación en el paso 9.
⚠️⚠️ **Y esa lista mordió**: los 20 iconos se sirvieron VACÍOS (`#113`). Hoy los cubre
`SidebarIconParityTest`.

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
| 4.7·2b·2·C | Los dependientes que no son paridades · el inventario queda CLASIFICADO | `#86`–`#98` |
| 4.4b·2 | **El widget del anti-bot en el cajón**, y dos centinelas que no mordían | `#108` |
| Staging | Despliegue (`deploy.sh`) · la guarda del dinero en verde falso · los CUATRO caminos verificados | `#105`–`#110` |
| 4.7·2b·3·0 | **Independizar el contrato de árbol ANTES de borrar** (el fixture salía del motor) | `#111` |
| **4.7·2b·3 + ·3** | 🟩 **`Purchase.php` RETIRADO y el flag con él** · y tres guardas que estaban en verde **sin medir nada** | **`#112`** |
| 4.7·2b·4 | **El cajón se servía SIN ICONOS**: 20 `<svg>` vacíos que el diff de árbol no podía ver | **`#113`** |

⚠️ **Las lecciones transversales que más se repiten**, por si solo lees esto:
**una guarda con DOS fuentes redundantes no se puede medir mutando una sola** (`#112`: la aserción de
`livewire.js` llevaba tiempo inerte y la doc la daba por crítica) ·
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
  ⚠️ **Eso es de la máquina donde se hizo el import, no del producto**: un clon nuevo sembrado con
  `migrate --seed` NO tiene ese par (medido el 2026-08-19 al montar el segundo puesto de trabajo).
  Si `User::first()` te devuelve un usuario limpio no es un fallo: es que estás en un clon nuevo.
- ⚠️ Si clonas de cero, comprueba que **`APP_URL` coincide con `APP_PORT`** en el `.env` (no versionado):
  con el puerto desalineado salen mal los enlaces absolutos de correo, las URLs firmadas y la derivación
  de CORS y de los dominios stateful de Sanctum.
- **El push exige `VERIFY_CONC=1`** —tras correr los dos comandos de `INVARIANTES §6`— si tocas el
  núcleo de dinero/aforo.

## Herencia

Base heredada del origen (2026-08-12): 30 modelos, 71 migraciones, 17 Filament Resources, Redsys en
sandbox y suite **2132** verde al importarla.
Recuento VIVO: 30 modelos · 72 migraciones · 17 Filament Resources. La migración añadida es
`personal_access_tokens` (Sanctum).
⚠️ **El contador de tests NO se repite aquí**: vive arriba, en «Dónde estamos», con su contexto.
`docs-check` vigila los tres números de esta línea y las invariantes —son sus cuatro patrones—, pero
**«N tests» no casa con ninguno**, así que repetirlo es drift en espera. Ya mordió: esta línea decía
**2715** mientras el cuerpo y la suite decían **2642** (medido el 2026-08-21, no ajustado). Misma
doctrina que se aplicó a las líneas de `Sidebar.vue`: una foto sin receta que la vigile, se retira.
Stack: Laravel **13.25** · Filament **5.7** · Livewire **4.4** · PHPUnit 12.5 · Sanctum **4.3** ·
Spectator **3.0** (dev) · Vite **8.2** · 0 avisos de seguridad (`composer audit` y `npm audit`).
