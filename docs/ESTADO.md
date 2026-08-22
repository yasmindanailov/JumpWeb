# Estado del proyecto — foto viva

> Documento CORTO (carga obligatoria al arrancar). Solo «dónde estamos / qué sigue».
> El «qué pasó» de cada paso vive en `00-REFACTOR.md` (tracker) y `DECISIONES.md` (el porqué):
> aquí solo se enlaza. Última actualización: **2026-08-22** (4.7 validado por el owner · el cajón reorganizado en tres capas ·
> el área de cliente DISEÑADA y partida en tandas, `#120`).

## ▶ Dónde estamos

**Fase 0 ✅ · Fase 1 ✅ · Fase 2 ✅ · Fase 3 (API v1) ✅ · Fase 4 (sidebar SPA) 🟦 — EN CURSO.**

**Fase 4**: 4.0a–4.0c ✅ · 4.1 ✅ · 4.2 ✅ · 4.3 ✅ · 4.4a ✅ · **4.4b ✅ (·1 y ·2)** · 4.5 ✅ · 4.6 ✅ ·
**4.7 ✅ (validado por el owner el 2026-08-22)** → **los ONCE pasos están transcritos**, el extremo a extremo con navegador
y pasarela real se hizo (`#59`), **los cuatro caminos que `#100` exigía están VERIFICADOS en staging**
(`#110`) y **`Purchase.php` está RETIRADO** (`#112`). El corte del diseño está en
`docs/specs/sidebar-spa.md` §4.10.

✅ **Las dos comprobaciones de navegador que faltaban están HECHAS y el owner ha validado el cajón**
(2026-08-22). La fase sigue 🟦 solo por lo que viene después —el área de cliente (`#66`)—, no por
`4.7`: eso está cerrado.

🟩 **EL CAJÓN SPA ES EL MOTOR ÚNICO.** Con el componente se fueron su blade, el placeholder y el flag
`sidebar.engine`: **no hay vuelta atrás sin desplegar**, que es lo que `#100` pedía asegurar antes y
`#110` verificó. Está **en `main`**. ⚠️ La rama `wip/4.7-2b-3-retirada-purchase` sigue en el remoto, **fusionada y sin nada propio**
(`git log main..wip/…` → 0): si `/arranque-sesion` la saca, no es trabajo perdido. Borrarla es seguro.

✅ **STAGING está desplegado y al día**, sirviendo el cajón SPA con el anti-bot activo. Canal:
`scripts/deploy.sh` (dry-run por defecto). Detalle en `ENTORNOS.md` §4; el porqué, en `#105`–`#110`.

⚠️ **Los pasos se parten por DEPENDENCIA, no por pantalla** — es la regla que ha ordenado toda la fase.
El detalle de cada corte está en el tracker; el índice de abajo enlaza cada uno con su decisión.

- Suite **2666 en verde** (15.306 aserciones, `--parallel` ~42 s medidos el 2026-08-22) ·
  **404 tests JS** (`node --test`) · Pint limpio (822 ficheros) · `docs-check` verde ·
  `composer audit` y `npm audit` en **0** · `npm run build` y `build:ssr` OK. El contador
  «PHPUnit Notices: 1» sale solo en la paralela completa y es del runner (ver `TESTING.md`).
  ✅ **Y desde el 2026-08-21 este número YA TIENE GUARDA**: el `pre-push` compara lo que acaba de dar
  la suite con lo que declara esta línea y **corta si no cuadran** (`DECISIONES #116`). Antes no lo
  vigilaba nadie —`docs-check` no lo mira— y derivó tres veces en un solo día.
  ⚠️ **Sigue siendo el ÚNICO sitio donde vive**: duplicarlo en otro documento crea una copia que no
  guarda nadie. El histórico de cómo llegó hasta aquí está en `DECISIONES #112(a)` y `#116`.
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
- ✅ **El segundo objeto-dios está DESMONTADO** (`DECISIONES #119`, 2026-08-22). `Sidebar.vue` pasó de
  **614 líneas y 11 llamadas a la API** a **16 y 0**: el embudo vive en `sections/PurchaseSection.vue`
  y el estado, en **nueve stores** de `stores/`. Lo siguen guardando `SidebarComponentBudgetTest`
  (techo por componente + excepción declarada que solo encoge) y una guarda de que **la raíz no vuelve
  a pintar pantallas**. ⚠️ **Las cifras vivas están en su `EXCEPTIONS`**, no aquí: copiarlas a este
  documento es drift en espera, y ya pasó una vez.
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
- ✅ **El terreno ya está preparado** (`DECISIONES #119`, 2026-08-22), y lo que hay que saber es dónde
  NO meter la cuenta:
  · el grafo del embudo es `FUNNEL_TRANSITIONS` y está **cerrado con guarda**: colgar ahí una pantalla
    de cuenta pone el test en rojo. Un área de cliente **no es un embudo** — sus pantallas se navegan
    libremente— así que va con su propio modelo, no con el de la compra;
  · el embudo es una **sección** (`sections/PurchaseSection.vue`) y la raíz son 16 líneas que solo
    enrutan: **la cuenta entra al lado, no dentro**;
  · el estado de cada dominio ya tiene su store, así que una sección nueva pide el suyo y no necesita
    que la raíz le pase nada por props.
  ✅ **Y el modelo de navegación ya está DISEÑADO** (`specs/area-cliente.md` §3.1, 2026-08-22): índice
  + zonas libres con pila de retorno, con su propio modelo y sin tocar el grafo del embudo. 🟦 Pendiente
  de que el owner lo valide, junto con el modo `account` del panel (`#120(d)`).
- ⚠️ **El servidor está para LEER, NO para GESTIONAR** — y esta línea decía lo contrario hasta que se
  midió endpoint por endpoint (`#120(a)`, 2026-08-22). ✅ Existen y están probados desde Fase 3:
  `/auth/*`, `/me`, `/me/orders`, `/me/reservations`, `/me/reservation-eligibility` y el post-form por
  firma. ❌ **No existe NADA** para cambiar el perfil, cambiar la contraseña, cerrar sesión en otros
  dispositivos, borrar la cuenta ni exportar los datos: solo `App\Livewire\Account\*` y el controlador
  web del export. Eso es lo que parte el área de cliente en dos tandas.
- ⚠️ **La última frontera es `account-context`**, que sigue siendo **Livewire y hermano** del punto de
  montaje de Vue: el cajón SPA nunca lo ha pintado. Ahí murieron las señales de `#118`, y su fila está
  en `DEUDA.md`. Traerlo a Vue es trabajo de esta fase.

## ▶ Próximo paso

▶▶ **EL ÁREA DE CLIENTE — TANDA 1: SOLO LECTURA** (`DECISIONES #66`/`#120`). El diseño está
**✅ APROBADO por el owner** (`docs/specs/area-cliente.md`) y **el paso 1 de los seis está HECHO**:
el cajón ya tiene DOS secciones. Lo que sigue es el **paso 2**, el modelo de navegación de zonas
(`account/navigation.js`); el orden completo y la red de cada paso, en la **§8** de la spec.

✅ **Pasos 1 y 2 de CINCO, hechos y verificados en navegador el 2026-08-22.**
· **1 · el nivel SECCIÓN** (`V4`): conmutar y volver **no dispara ni una petición**, el embudo conserva
  su paso, el bloque de cuenta se colapsa con el modo **`account`** —y **no es alcanzable con Tab**— y
  el árbol oculto **no atrapa el foco** (12 focusables visibles → 0 ocultos).
· **3a · el CONTRATO de presentación**: las etiquetas que el cliente **no puede** componer
  —`date_label`, `created_label`, `refunded_label`— y `guest_form_url`, con **fuente única**
  (`DisplayTime::dayLabel`, que unificó **cuatro copias** de la misma fórmula) y su guarda mutada.
  ⚠️ Corrige el «hay que pintar, no abrir dominio»: valía para los datos, **no para su presentación**
  (`#120(j)`).
· **2 · la NAVEGACIÓN de zonas** (`V5`, **7/7**): índice + «Mis reservas», pila de retorno que **no
  crece al alternar**, «volver» sin historia **sale a la compra**, entrada directa a zona y reentrada
  con la historia vacía. **0 peticiones** en todo el recorrido.
⚠️ **Son DOS zonas y no tres, y lo decidió una medición de vocabulario**: `account.orders.title` es
literalmente **«Mis reservas»** —la página de pedidos ya se llama así de cara al cliente— y
`/me/reservations` alimenta el bloque de cuenta, no una pantalla. Una zona «próximas reservas» aparte
habría sido inventar producto en una tanda cuyo criterio es la paridad (`specs/area-cliente.md` §4.2).
⚠️ **El botón que lleva ahí NO está cableado todavía, y es a propósito**: la sección es hoy su armazón.
Cablear la puerta antes de que haya habitación llevaría al cliente a una pantalla vacía si esto se
despliega. Se cablea en el paso 5, con las zonas ya pintadas.

⚠️ **Y lo primero que la spec midió corrige lo que esta foto venía repitiendo.** «El servidor ya está,
hay que pintar» **solo vale para LEER**: las cinco gestiones de cuenta —perfil, contraseña, otras
sesiones, borrado y export RGPD— **no tienen ningún endpoint** y viven solo en `App\Livewire\Account\*`.
Por eso el trabajo va en tandas, y esta es la primera (`#120(a)`).

Lo que te vas a encontrar hecho, y lo que no:

| | |
|---|---|
| Diseño | ✅ **escrito** (`specs/area-cliente.md`): navegación, zonas, contrato de datos y plan de verificación. 🟦 dos puntos sin validar |
| Servidor para **LEER** | ✅ `/me`, `/me/orders`, `/me/reservations` y el reintento por `POST /orders/{code}/payment` |
| Servidor para **GESTIONAR** | ❌ **no existe**: cinco superficies de API por abrir. Tanda 2, no ésta |
| Sitio donde entra | ✅ una **SECCIÓN** al lado de `sections/PurchaseSection.vue`; la raíz (16 líneas) solo enruta |
| Estado | ✅ un **store por dominio**; las peticiones van AHÍ y no al componente (techo de 40 líneas, 0 llamadas) |
| Navegación | ✅ **diseñada**: índice + zonas libres con pila de retorno. 🟦 sin validar. El grafo del embudo **no se toca** |
| `/mi-cuenta/…` | 🟩 **condenado** (`#120(c)`, owner), tanda 3. ⚠️ Muere la **VISTA**, **vive la RUTA**: 8 correos ya entregados apuntan ahí y no se pueden editar |
| `account-context` | ❌ sigue en **Livewire**. Aquí solo se le cablea la puerta; migrarlo es trabajo posterior |
| ⚠️ **La red** | **NO es el diff de árbol** (`#120(e)`): aquí no hay original que copiar. Es paridad de DATOS contra **la API** —no contra la página condenada— + navegador |
| ⚠️ **La cadena flex** | `.sidecart__body` → `#sidecart-spa` → `.purchase` → `.purchase__scroll` son **hijos DIRECTOS**: un envoltorio router la parte y **ningún test lo ve** (`specs/area-cliente.md` §4.9) |

⚠️ **Y una regla de trabajo que esta fase dejó pagada con tres fallos**: en un refactor o una feature
del ORQUESTADOR, **el contrato de árbol no es red** —`render-sidebar.mjs` no importa la raíz y su
comentario dice por qué—. La red es el NAVEGADOR. Receta del andamio, con sus trampas medidas, en
`VERIFICACION-E2E-CAJON.md` §5.bis y §5.quater.

### ❗ Bloqueado, y lo desbloquea el owner

❗❗ **EL SCHEDULER NO CORRE EN STAGING** (`DECISIONES #115`). El crontab está instalado y correcto y
`schedule:run` funciona a mano, pero **no hay demonio cron en el contenedor del sitio**. Medido: 6
avisos con 24 h en `jobs` y `attempts = 0`, y un pedido 24 h sin caducar que `orders:expire` caducó al
instante al lanzarlo a mano.
▶ **La entrada exacta que hay que poner en el panel de Enhance, y cómo comprobar que funciona, están
en `ENTORNOS.md` §4.** Mientras tanto se dispara a mano:
`ssh jumpweb-staging "cd ~/public_html && php artisan schedule:run"`.
⚠️ Obliga a matizar `#110`: sus cuatro caminos siguen valiendo —ninguno depende del cron— pero **allí
nunca se ha ejercitado la caducidad de pedidos ni el envío diferido de correo**.

▶ Y luego, `scripts/provision.sh` (`#102(f)`), que necesita un token nuevo del panel: el que se usó
para medir lo retiró el owner.

⚠️ **CINCO trampas MEDIDAS que condicionan lo que venga.** No se explican aquí —cada una tiene su
sitio y duplicarlas es lo que envejece esta foto—; se nombran para que no te pillen:
- **Un `assertSee` de un texto del grupo `tickets` contra una página completa NO PRUEBA NADA**: el
  montaje del cajón lo lleva entero en cada página. Receta y porqué: `TESTING.md` **§2.ter**.
- **Lo que un gate declara que NO mira es un hueco con nombre** — así se sirvieron 20 iconos vacíos:
  `TESTING.md` **§2.quater** y `DECISIONES #113`.
- **Una comprobación que mide una cosa y se lee como otra es PEOR que no tenerla**: dos señales de
  salud en verde con el scheduler muerto (`#115`).
- **Que las piezas se llamen no significa que el valor LLEGUE**, y que los dos extremos estén probados
  no significa que el medio esté cableado: `#117`, `#118` y `#119(f)` son tres fallos vivos distintos
  de la misma familia, todos encontrados en un navegador y ninguno por la suite.
- **El modo `embedded` de `auth.login`/`auth.register` ya no lo monta nadie en producción**, pero es
  la REFERENCIA de `SidebarLoginParityTest`/`SidebarRegisterParityTest`: muere cuando el área de
  cliente rehaga la auth dentro del cajón, no antes (`#112(f)`).

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
  3. **Validar la spec del área de cliente** (`specs/area-cliente.md`): el modelo de navegación (§3.1)
     y el modo `account` del panel (§3.3) son propuesta del agente y están 🟦 sin validar (`#120(d)`).
  4. 2FA del panel · backlog de producto de Fase 6.
  ✅ Resueltos: el acceso SSH del 2º puesto (2026-08-21) y **las dos comprobaciones de navegador que
  cerraban `4.7`** (2026-08-22, validadas por el owner).

## ▶ Hasta dónde llega hoy el motor SPA, dicho sin optimismo

El cajón **recorre el embudo entero y vuelve**: catálogo → día → hora →
cantidad → complementos → carrito → identificarse (entrar o **crear cuenta** dentro del cajón) → pagar
→ auto-POST firmado a Redsys → y los **tres desenlaces** (reserva creada con su resumen, rechazo con su
motivo y reintento, y el sondeo cada 5 s del terminal *data-less*). Con las reservas pausadas sustituye
el flujo por el aviso de mantenimiento. La cesta sobrevive a la recarga.

⚠️ **Residual de la pausa**: el estado se relee al cargar la página, en cada apertura del cajón y al
pulsar «Ir a pagar». Un cajón ABIERTO y quieto no se entera del interruptor hasta cerrarlo, reabrirlo o
intentar pagar.

## ▶ El MAPA del cajón SPA (para no buscarlo a ciegas)

`resources/js/sidebar/` — **la lógica vive en módulos PLANOS sin Vue** (`CE-6`), y los componentes solo
pintan.

⚠️ **Desde la reorganización del 2026-08-22 hay TRES capas y conviene no confundirlas**: los módulos
planos (las reglas, probados con `node --test`), los **stores de Pinia** (`stores/`, el estado de cada
dominio) y los componentes (pintan). Y **el embudo ya no es la raíz**: `Sidebar.vue` son 16 líneas que
solo enrutan, y la compra vive en `sections/PurchaseSection.vue`. El ÁREA DE CLIENTE (`#66`) entra como
**otra sección**, al lado, no dentro. Esa separación es lo que hace que todo lo de abajo se pruebe con
`node --test` y se compare contra el servidor desde PHP ejecutándolo en Node.

    Sidebar.vue                16 líneas · 0 llamadas a la API   ← enruta y expone hacia fuera
    sections/PurchaseSection    438 líneas · 2 llamadas          ← el embudo entero
    stores/  (nueve)                                             ← el estado, por dominio
    steps/   (once)             3 a 33 líneas cada uno           ← pintan y solo pintan

| Módulo | De qué responde | Su red |
|---|---|---|
| `machine.js` | En qué paso está el cajón y a cuál puede ir. ⚠️ `FUNNEL_TRANSITIONS` está **cerrado**: una pantalla que no sea del embudo no va ahí (`#119`) | `machine.test.js` · `SidebarProgressParityTest` |
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
| `stores/purchase.js` | El paso y las dos señales que el cajón publica hacia fuera | `stores/purchase.test.js` (nace tras `#59`) |
| `stores/date.js` | El estado del paso 2: días ofrecidos, día elegido, mes visible y lo que de ahí se deriva | `stores/date.test.js` (nace en la reorganización, `#119`) |
| `stores/time.js` | El estado del paso 3: horas ofrecidas, hora elegida y el tope del selector (**`max_quantity`, no `available`**) | `stores/time.test.js` |
| `stores/auth.js` | El paso 5 entero: los dos formularios, sus avisos, la pestaña, el bit del anti-bot y las dos peticiones | `stores/auth.test.js` |
| `stores/cart.js` | La cesta: líneas, dueño, presupuesto, avisos y persistencia (**las respuestas del evento NO se guardan**, RGPD) | `stores/cart.test.js` |
| `stores/outcome.js` | El desenlace: formulario de la pasarela, código del pedido, resumen, motivo del rechazo | `stores/outcome.test.js` |
| `stores/catalog.js` | El paso 1 y el producto elegido: secciones, fila del listado, ficha y etiquetas del evento | `stores/catalog.test.js` |
| `stores/selection.js` | La LÍNEA en construcción: cantidad, complementos y respuestas del evento (**nunca se persiste**) | `stores/selection.test.js` |
| `stores/booking.js` | Si las reservas están pausadas. Se PIDE, no se inyecta: la dueña acciona el interruptor con clientes dentro | `stores/booking.test.js` |

Fuera de `sidebar/`: **`resources/js/ui/scroll-lock.js`**, el dueño ÚNICO de `body.no-scroll` con llaves
por superpuesto. Lo vigila `ScrollLockOwnerTest`; nadie más puede tocar esa clase (`#58`).

⚠️ **La regla que enseñaron las paridades**: cuando algo NO es atributo de contrato del normalizador
—`href`, `action`, `method`, los `name` de un formulario, **el texto**, y **el interior de un
`<svg>`**— el diff de árbol **lo da por bueno**. Si transcribes algo de esa clase necesita paridad
propia. Demostrado por mutación en el paso 9.
⚠️⚠️ **Y esa lista mordió DOS veces**: los 20 iconos se sirvieron VACÍOS (`#113`, hoy los cubre
`SidebarIconParityTest`) y los botones de mes **no emitían nada** desde 4.2·2 — un `@click` tampoco es
un atributo del DOM, así que los dos botones salían idénticos con y sin cableado. Hoy lo cubre
`SidebarEmitWiringTest` (`#119(f)`).

⚠️⚠️⚠️ **Y hay un límite MAYOR que ese, y conviene saberlo antes de confiar en el contrato**:
`scripts/render-sidebar.mjs` **NO importa la raíz del cajón** —no puede, lee `window.Alpine` y el
idioma del documento— y monta los ONCE componentes de paso con props que construyen los módulos
planos. O sea que el contrato prueba **los pasos y los módulos**, y **no ejerce el orquestador**.
▶ Para un cambio en el orquestador (`sections/PurchaseSection.vue`, `Sidebar.vue`) la red es el
NAVEGADOR: receta en `VERIFICACION-E2E-CAJON.md` §5.bis.

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
| Verificación | **A7 y los iconos, en navegador**: los enlaces profundos **nunca se cablearon** · el bloque de cuenta se oculta en la compra, y el «modo» del panel llevaba muerto | `#117`, `#118` |
| **Reorganización** | 🟩 **El cajón, en TRES capas y por secciones**: nueve stores, el embudo fuera de la raíz y el grafo cerrado — antes del área de cliente, a petición del owner | **`#119`** |

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
