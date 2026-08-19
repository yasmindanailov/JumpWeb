# Estado del proyecto — foto viva

> Documento CORTO (carga obligatoria al arrancar). Solo «dónde estamos / qué sigue».
> El «qué pasó» de cada paso vive en `00-REFACTOR.md` (tracker) y `DECISIONES.md` (el porqué):
> aquí solo se enlaza. Última actualización: **2026-08-19**.

## ▶ Dónde estamos

**Fase 0 ✅ · Fase 1 ✅ · Fase 2 ✅ · Fase 3 (API v1) ✅ · Fase 4 (sidebar SPA) 🟦 — EN CURSO.**

**Fase 4**: 4.0a–4.0c ✅ · 4.1 ✅ · 4.2 ✅ · 4.3 ✅ · 4.4a ✅ · 4.4b·1 ✅ · 4.5 ✅ · 4.6 ✅ →
**los ONCE pasos están transcritos** y el extremo a extremo con navegador y pasarela real ya se hizo
(`#59`). Queda **4.7** (la retirada de `Purchase.php`, **bloqueada en la verificación de staging**, `#100`)
y **4.4b·2** (Turnstile, **ya DESBLOQUEADO**: el owner aportó las claves, `#101`). El corte del diseño está en `docs/specs/sidebar-spa.md` §4.10.

⚠️ **Los pasos se parten por DEPENDENCIA, no por pantalla** — es la regla que ha ordenado toda la fase.
El detalle de cada corte está en el tracker; el índice de abajo enlaza cada uno con su decisión.

- Suite **2758 en verde** (15.791 aserciones, `--parallel` ~70 s) · **287 tests JS** (`node --test`) ·
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

**4.7 · la retirada de `Purchase.php`.** Lo ordena un número: **`PurchaseRetirementTest` declara 19
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

✅ **El bloqueador `#74` está RESUELTO** (`#99`) y **el inventario está clasificado entero**.
⚠️ **Pero «clasificado» NO es «borrar no rompe nada»: hay un agujero medido de UN fichero**
(`DECISIONES #103`). `SidebarEngineTest` **no está en las 19 entradas** —el escáner no lo caza porque
no nombra `Purchase::` ni la vista— y sus dos casos comparan los dos motores, así que uno se pone rojo
al borrar (o, peor, se queda VERDE con el cajón en blanco). Hay que decidir su destino **antes**.

⚠️⚠️ **Y borrar no es limpiar: es ACTIVAR** (`DECISIONES #100`) — con un matiz medido el 2026-08-19
que cambia el reparto del trabajo (`#103`): **el borrado por sí solo NO activa la SPA**. El default y
el fallback de `usesSpa()` son `livewire`, así que quitar la rama `@else` deja el cajón **vacío**, no
en SPA. **Lo que activa el motor es `4.7·3` — retirar el flag**, un paso que existe en el tracker y
que la cadena de abajo no contaba. Son dos trabajos, no uno; y `4.7·3` es el que hereda entera la
condición de `#100` (**Turnstile y los tres caminos de navegador, verificados en staging**).

✅ **STAGING APROVISIONADO** (2026-08-16, `#102`): BD `jumpweb_1_test` (MariaDB 11.4.10, verificada
conectando) · PHP 8.3 · `documentRoot` → `public_html/public` · `robots.txt` con `Disallow: /`
sirviéndose por HTTPS. `ENTORNOS.md` §4 tiene el detalle y las cuatro cosas que la doc del panel no
cuenta.

⚠️⚠️ **ANTES de `deploy.sh` había DOS bloqueos que la cadena anterior no veía** (`DECISIONES #103`,
medidos el 2026-08-19). No son «tener cuidado»: sin ellos el despliegue **no puede terminar** o
**no compra lo que dice comprar**. **Ya NO queda ninguno: los dos están resueltos** (2026-08-19).

1. ✅ **RESUELTO (2026-08-19) · el PHP del sitio.** 17 paquetes `symfony/*` exigen `php >=8.4.1` y
   staging se aprovisionó en 8.3, así que `composer install --no-dev` habría abortado. El owner subió
   el sitio y está **verificado por SSH**: `php -v` → **8.5.1**, y `which php` → `/usr/bin/php`, o sea
   que es el binario que usarán `composer`, `artisan` y el cron. Detalle en `ENTORNOS.md` §4, punto 0.
2. ✅ **RESUELTO (2026-08-19, `DECISIONES #104`) · el primer admin.** Tras desplegar no había forma
   de entrar a `/admin`, y ahí es donde se configuran las claves de Turnstile. `ProductionSeeder` crea 0 usuarios · `DatabaseSeeder` solo crea admin
   `if (! isProduction())` · `canAccessPanel()` exige rol `admin`/`staff` (que `make:filament-user`
   no da) · `Turnstile::keys()` lee **solo** de `settings`. Cadena: sin admin → sin panel → sin claves
   → 4.4b·2 quedaba bloqueado. ▶ **Hecho: `app:create-admin`** (17 casos, **11 mutaciones muertas**).
   Genera la contraseña y la enseña UNA vez · **idempotencia ASIMÉTRICA**: repara el rol si falta pero
   **NO** toca la contraseña (rotarla en cada redespliegue echaría al owner de su panel) · aborta con
   código 1 si el rol no está sembrado o si se le pide un rol que no abre el panel.
   ⚠️ **`make:filament-user` NO servía**: `canAccessPanel()` exige el rol de la pivote `role_user`, que
   ese comando no toca — habría creado una cuenta que existe y **no entra**.
   Cierra el `[DECISION-PENDIENTE]` de `INSTALACION-CLIENTE.md` §5 **con código y prueba**.

✅✅ **STAGING DESPLEGADO Y VERIFICANDO** (2026-08-19, `DECISIONES #105` + `#106`).
`scripts/deploy.sh` existe, se ejecutó **dos veces** (idempotente: la 2.ª dijo «Nothing to migrate» y
no duplicó el cron) y el sitio sirve. Lo guarda `DeployScriptGateTest` (26 casos, **14 mutaciones
muertas**).
**Verificado POR FUERA del script** (`#59`: verde no es funciona): las **12 páginas públicas en 200**
· los **tres idiomas en vivo** · `/admin` 302 y `/admin/login` 200 · `/api/v1/config` 200 ·
`robots.txt` con `Disallow: /` · 5 tareas del scheduler · `failed_jobs` vacía.
Contenido: semilla neutra SaltoPark + **1440 franjas** · admin creado.
⚠️ **Y el despliegue destapó que la guarda del DINERO daba un VERDE FALSO** (`#106`): leía
`redsys_environment` por un FQCN que no sobrevive a ssh, el `tr` convertía el `PARSE ERROR` en basura
con pinta de valor, y la condición preguntaba «¿contiene `live`?» — así que **habría pasado con el
entorno en `live`**. Arreglado y **fail-closed**: ahora exige `test` exacto y aborta ante cualquier
otra cosa. ▶ **La regla, para toda guarda de dinero: pregunta «¿es lo que ESPERO?», nunca «¿es lo que
TEMO?».**
⚠️ **Medido de paso**: el idioma va por SESIÓN (`/lang/{locale}`), **no por prefijo de URL** — `/en` y
`/fr` dan 404 y es correcto.
Construir assets en local (**no hay node en el servidor**), `rsync`, `.env` con las seis guardas de
`ENTORNOS.md` §2, `composer install --no-dev`, `migrate --force`, `ProductionSeeder`,
**`app:create-admin`**, `slots:generate-rolling`, permisos y cron del scheduler (**el `crontab` del
sitio SÍ se puede escribir, y está VACÍO**).
**La máquina ya está medida entera** (`ENTORNOS.md` §4): el `rsync` entra como `jumpweb_1`, así que
**no hace falta `chown`**; la raíz de la app es `~/public_html/` y el docroot su `public/`. ⚠️ **`storage:link` NO va**: `INSTALACION-CLIENTE.md` §1 lo prohíbe y el código lo
confirma (0 usos del disco `public`; la única subida es `Offer::IMAGE_DISK='uploads'` →
`public/uploads`, que además hay que **excluir del `--delete`** o el segundo despliegue borra las
subidas del panel).

⚠️ **Cinco cosas que el script tiene que hacer y no son obvias:**
- **reescribir `public/robots.txt` con `Disallow: /` y verificarlo por HTTP** — el del repo permite
  indexar a propósito (el producto debe indexarse en casa de un cliente), así que el primer `rsync`
  tumba la guarda 4 si el script no lo repone;
- **negarse** si el destino no es staging, si `redsys_environment` quedaría en `live`, si el correo
  saldría o si `APP_DEBUG` es `true` — lo valioso del script es lo que **no** deja hacer;
- **borrar `public/hot` en destino y excluirlo del envío**: si existe, Vite sirve TODOS los assets
  desde `localhost:5274` y la web queda sin CSS ni JS **sin ningún error de servidor**;
- **comprobar la salud del sitio al terminar** (`/up` → 200, `robots.txt`, `schedule:list`), no dar
  por hecho que fue bien;
- ⚠️ **al verificar el `robots.txt`, comparar el CONTENIDO y por HTTP, no el tamaño**: medido en las
  dos puntas el 2026-08-19, el servido (26 B) y el del repo (25 B) **pesan casi igual**.
⚠️ **`ProductionSeeder` no deja nada comprable por sí solo**: crea `SlotTemplate`s pero **0 franjas**
y marca las entradas `is_sellable => false` (solo venden los packs). Hay que correr
`slots:generate-rolling` después, o no habrá qué comprar para verificar Turnstile, S2S ni 3DS.

✅ **Turnstile CONFIGURADO en staging** (2026-08-19, `#107`): `/api/v1/config` publica ya la site key
y **no** la secreta, y `siteverify` de Cloudflare responde `invalid-input-response` —**no**
`invalid-input-secret`—, o sea que **reconoce el secreto como válido**. El servidor tiene salida a
`challenges.cloudflare.com`.
⚠️ **NO se configuran por el panel**, y la doc decía que sí: son fila de `settings` escrita por
`tinker`/SQL (`ENTORNOS.md` §3). Ficha en `DEUDA.md` — merece un comando propio.
⚠️ **Sin verificar: el HOSTNAME.** Cloudflare ata las claves a un dominio y solo lo valida al canjear
un token REAL, lo que exige navegador.

▶▶ **EMPIEZA AQUÍ: 4.4b·2 — montar el widget en el cajón SPA**, que es lo único que falta de Turnstile.
Lo que falta de 4.4b·2 son **seis piezas, no tres** (`#101(b)` decía tres): el widget en
`RegisterForm.vue` · mandar `turnstile_token` en `register.js::runRegister` (hoy NO viaja) · retirar la
delegación de `Sidebar.vue::setAuthMode` · el cargador del script externo (hoy solo existe para
Livewire) · re-apuntar las **tres redes** que hoy afirman lo contrario · y medir el coste en bundle
contra **4,15 KiB de margen** (`SidebarBundleBudgetTest`).
⚠️ **Se desarrolla en LOCAL**: Cloudflare emite claves de prueba que aceptan cualquier hostname y
`Turnstile::verify()` se ejercita con `Http::fake`. Staging es para verificarlo, no para construirlo.

**Y en staging, lo que `#100` exige antes de borrar nada:**
1. **Turnstile** (4.4b·2), la
   **notificación S2S** de Redsys, el **3DS con challenge** y el **móvil real**.
   ⚠️ Ojo con la referencia: el S2S **no está en `VERIFICACION-E2E-CAJON.md` §6** —§6 lista 3DS, los
   tres idiomas, móvil y Turnstile—; su receta es el **bloque B (§3)**, y está escrita para un túnel
   `cloudflared` local, sin variante de staging todavía.
   ⚠️ **Y falta la receta de cómo poner el flag en `spa` ALLÍ**: la única escrita usa
   `docker compose exec`, y en staging no hay docker (el flag tampoco es editable por panel).
2. **`4.7·2b·3`**: el borrado. ⚠️ **NO está «sin decisiones abiertas»**: quedan el destino de
   `SidebarEngineTest` (fuera del inventario) y el del modo `embedded` de auth, que el propio tramo
   declara «parte de este tramo».
3. **`4.7·3` — retirar el flag**, que es *lo que de verdad activa el motor SPA* (`#103`). Faltaba en
   esta cadena.
4. **`scripts/provision.sh`** contra la API del panel, **después** del `deploy.sh` (`#102(f)`): su
   trabajo es dejar el servidor en el estado que el despliegue espera, y ese estado solo se conoce
   habiéndolo alcanzado una vez. Necesita un token nuevo, con el menor alcance posible.

⚠️ **El token de API usado para medir esto era temporal y el owner lo retira.** El panel es
`https://cp.hosturbo.net/api`; los identificadores del sitio están en `ENTORNOS.md` §4.

**El MÉTODO de la auditoría —clasificar por sujeto y medir mutando— vive ahora en
`CONVENCIONES.md` §3.quater**, que es donde se busca un protocolo. Aquí solo el estado.

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
