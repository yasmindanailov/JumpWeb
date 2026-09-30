# Carril del SPA · el diseño del cajón en el SEGUNDO ordenador

> **Para el agente de Claude Code del otro ordenador.** Este documento es tu arranque: qué montar,
> de dónde sale el diseño, qué te ata y cómo convivir con el carril de la web sin pisarlo.
> `[DECIDIDO owner, 2026-09-11]` (`DECISIONES #530`). Banda de decisiones: **550–579**.

Eres el **carril del SPA**: el rediseño del **cajón de compra y de cuenta** (Vue, `resources/js/sidebar/**`),
que es la **Fase 4** del rediseño desde el canvas (`docs/specs/rediseno-desde-canvas.md` §5). El otro
agente, en el primer ordenador, sigue con la **Fase 3** (las páginas públicas: `/precios`, `/normas`,
`/servicios`, `/contacto`…). Los dos empujáis a `main`.

---

## 1 · Montar el ordenador (una vez)

1. Clonar `https://github.com/yasmindanailov/JumpWeb` (privado) en `~/proyectos/JumpWeb`, **dentro de
   WSL** (no en `/mnt/c`).
2. `.env` desde `.env.example`, con los puertos propios del proyecto: web **8081**, MySQL **3308**,
   Mailpit **8028**. Tus credenciales de desarrollo (Google, Redsys de pruebas) son **tuyas**: no vienen
   en ningún paquete.
3. `docker compose up -d` · `composer install` · `npm install` · `php artisan key:generate` ·
   `php artisan migrate` (todo con `docker compose exec -u sail laravel.test …`; **siempre `-u sail`**).
4. **El material del cliente** (paquete de tema, marca, kit, copia del canvas y datos del catálogo) está
   en la rama **`cliente/playjump`** del mismo repo. Desde la raíz del clon:
   ```bash
   git fetch origin cliente/playjump
   git show origin/cliente/playjump:aplicar.sh | bash -s -- --datos
   ```
   Lee antes su `README.md` (`git show origin/cliente/playjump:README.md`). Luego pon en tu `.env` la
   línea de `entorno/cliente.env` de esa rama y crea tu administrador: `php artisan app:create-admin`.
5. `npm run build` **y** `npm run build:ssr` (el segundo lo exige `SidebarDomContractTest`, abajo).
6. El gate: `git config core.hooksPath .githooks` (el `pre-push` corre docs-check + Pint + build + suite
   en cada push a `main`).
7. **La sonda de navegador** (la vas a necesitar mucho): la receta está en la cabecera de
   `scripts/sonda-geometria.mjs` — Chromium en el contenedor, `playwright-core@1.49.0` con `--no-save`
   y el puente `socat` 8081→80. ⚠️⚠️ **Cualquier `npm install`/`npm uninstall` PODA `playwright-core`**
   (no está en `package.json` a propósito): medido el 2026-09-11, hay que reinstalarlo después.
   ⚠️ **El Chromium de la sonda NO reproduce H.264**: un `<video>` mp4 sale con `readyState 0` y no es
   un defecto (`DECISIONES #529`).
8. **La capa de agente** (F2, `#623`): cuando el plugin `jumpweb-agente` esté en GitHub, tu máquina lo añade e
   instala al confiar en la carpeta (lo declara `.claude/settings.json`); si no ves `/carril` y `/handoff` en el
   menú `/`, **desde la TERMINAL** (medido el 17-09: en la extensión de VSCode `/plugin` contesta «isn't
   available in this environment»): `claude plugin marketplace add https://github.com/yasmindanailov/jumpweb-agente.git`
   y `claude plugin install jumpweb-agente@jumpweb-agente --scope project`, y abre una sesión NUEVA. Si `claude`
   no está en el PATH, el binario vive en `~/.vscode-server/extensions/anthropic.claude-code-<v>/resources/native-binary/claude`.
   Lo corre el owner: al agente se lo deniega el clasificador (`#626`). El primer prompt es «Hola, lee la doc y
   arranca». Detalle: `docs/sistemas/CAPA-DE-AGENTE.md`. Las skills viejas del repo se retiraron con F2 (`#633`).

## 2 · De dónde sale el diseño

- **El canvas de Claude Design** es la fuente de verdad: MCP **`DesignSync`**,
  `projectId = 8c37d2d2-7e9c-43a9-bc25-aacb6607f2ad`, con la cuenta del owner. Trampas en
  `docs/specs/tema-por-instalacion.md` §1 (truncado a 256 KiB, binarios a 192 KiB, sin avisar).
- **La copia local** (`mockup_playjumppark_v2/`, llega con la rama del cliente) es una foto: **compárala
  con el canvas antes de construir**. `mockup_playjumppark/` es el ARCHIVO: **no copies colores de ahí**.
- **Artboards del cajón**: `Pasos Compra PJP` · `Pago y Desenlaces PJP` · `Navegacion Cuenta PJP`, sobre el
  sistema (`Sistema PJP`, `Componentes PJP` —«los cuatro controles del cajón»—, `Iconos PJP`,
  `Microanimaciones PJP`). En el canvas vive además **`Auditoria Sistema SPA PJP`**: una auditoría del
  cajón REAL con nueve grietas — léela en `rediseno-desde-canvas.md` §3.1. Y `HANDOFF.md` de la copia es
  el acta del diseñador («Lo que queda, por orden»: el SPA va después de la landing).
- **El filtro de todo** (`rediseno-desde-canvas.md` §2): el canvas es de PlayJump y **este repo es el
  PRODUCTO**. Cada pieza pasa por «¿es un mecanismo o es de este cliente?» antes de copiarse. Lo del
  cliente entra por su hueco (`client.css`, `public/img/client-*`, el kit), **nunca en el código**.

## 3 · Dónde empiezas

> ⚠️⚠️ **ESTA SECCIÓN ES DEL 2026-09-11 Y SUS DOS PRIMEROS PUNTOS YA ESTÁN HECHOS.** Se conservan
> porque explican de dónde viene el carril, pero **el estado real está en `ESTADO.md`** (bloque
> «🧩 CARRIL DEL SPA»), que es lo que hay que leer para saber por dónde seguir. Al 2026-09-12: el
> ARMAZÓN completo y **LAS SEIS PARADAS CERRADAS** (`#550`→`#566`), o sea **las 25 pantallas del cajón
> construidas**. ▶ **Lo siguiente que el canvas deja escrito es el CUADERNO DE ENTREGA** del cajón,
> como el que se hizo con la portada — y antes, el **OJO del owner en un teléfono de verdad**, que es
> lo único que no puede hacer un agente y que ninguna de las 25 ha tenido.

- ~~**Grieta 00** · el cuerpo del cajón está a **13 px** y el suelo del sistema son **16**~~ **CERRADA
  en `#550`** (`[DECIDIDO owner]`: 16 en las 25 pantallas).
- ~~**Grieta 01** · el botón que avanza la compra se pinta con `var(--zone-1)`~~ **CERRADA en `#551`**:
  `.btn--zone` está retirada del producto y el censo de `--zone-*` del cajón bajó de 29 a 13.
- **El botón del sistema** (16/800 con borde) se aplazó expresamente a esta fase (`rediseno` §5,
  «`[DECIDIDO owner]` espera a la Fase 4»): estrenarlo mueve la familia `.btn` entera, que llega al cajón.
- Specs que mandan en el cajón: `docs/specs/sidebar-spa.md` (§4.2: **el contrato visual es el ÁRBOL, no
  las clases**), `docs/specs/cajon-en-movil.md` (lo abierto: §7.4), `docs/specs/auth-en-cajon.md`,
  `docs/specs/area-cliente.md`, y `docs/VERIFICACION-E2E-CAJON.md` para verlo en navegador.

## 4 · Lo que te ata (las guardas del cajón)

- **`SidebarDomContractTest` renderiza el BUNDLE SSR, no las fuentes**: tras tocar un `.vue` o un `.js`
  del cajón, `npm run build:ssr` antes de la suite, o compara código VIEJO (35 casos en rojo con el árbol
  limpio, medido dos veces).
- **Presupuestos**: `SidebarBundleBudgetTest` (techo del chunk del cajón) y `SidebarComponentBudgetTest`;
  **se poda antes de subir un techo**, y la cifra la dice el test, no el ojo.
- **`SidebarTokenBudgetTest`** y su `SIN_ESTRENAR`: la lista de niveles tipográficos sin consumidor solo
  ENCOGE — si estrenas uno, sácalo de ahí en el mismo cambio.
- **Paridad**: `tests/Feature/Sidebar/*Parity*`, `SidebarIconParityTest` (el set de iconos lo comparten
  la web y el cajón), `SidebarTranslationKeysExistTest` (una clave mal escrita deja el texto VACÍO sin
  fallar), `SidebarSetupBindingsTest` (una `const` con el nombre de una prop la sombrea), y los de cableado
  (`SidebarStyleWiringTest`, `SidebarSeamTest`, `SidebarImportWiringTest`…).
- Los tests de JS: `npm run test:js` (`node --test`).

## 5 · Cómo no chocar con el carril de la web

`docs/CONVENCIONES.md` §10 manda (*«el canal es el REPO: lo que no está en `origin/main`, el otro no lo
sabe»*). Lo concreto para estos dos carriles:

**Tuyo** (el de la web no entra sin avisar): `resources/js/sidebar/**` · `resources/js/ui/*` que solo use
el cajón · `lang/*/tickets.php` y `lang/*/account.php` · `tests/Feature/Sidebar/**` · los tests
`tests/Feature/Architecture/Sidebar*` · y, en `public/css/site.css`, **los bloques del cajón por su
TÍTULO de sección**: «Autenticación (Fase 4)», «Mi cuenta (Fase 4.5)», «Sidebar de compra (Fase 5.2)»,
«SIDEBAR v2», «Feedback de carga», «Carrito de visitas», «Paso 1: catálogo», «Confirmación de la reserva»,
«Mis pedidos», «`.prod-ico`» y el «Formulario post-reserva».

**De la web** (tú no entras sin avisar): `resources/views/pages/**`, `resources/views/components/site/**`,
`resources/views/home.blade.php`, `public/css/landing.css` (salvo el `:root`), `lang/*/landing.php` y
`lang/*/site.php`, `tests/Feature/Site/**` y `tests/Feature/Landing/**`, y los bloques de la web de
`site.css` (servicios, registro, precios, pulido/hero, CTAs, barra de móvil, cookies, ofertas, menú).

**Compartido — se avisa ANTES en `ESTADO.md`**: los **tokens** (`:root` de `landing.css`: un token del
cajón mueve la web entera), `resources/views/components/layout.blade.php` (la poda del payload de textos),
`resources/js/app.js` (el cargador del cajón), `package.json`/`package-lock.json`, y las **listas
globales de las guardas** (`MotionBudgetTest`, `ShapeScaleTest`, `TouchTargetTest`,
`InteractionColourIsNotAZoneTest`, `ActionFillTest`).

**Los documentos calientes** (desde F1 del programa, `DECISIONES #617`→`#621`, 2026-09-16):
- Decisiones: tus números salen de **550–579**, y la entrada se añade **al final de
  `docs/decisiones/500-599.md`** (≤ 1,5 KB; `DECISIONES.md` es solo el índice).
- **`docs/carriles/spa.md` es TU fichero de estado** y nadie más lo escribe: foto, por dónde retomar,
  ficheros y buzón. Los avisos a la web van en tu buzón («Para el carril de la web: …»); la web anota
  «atendido» en el suyo y tú lo retiras en tu siguiente cierre. `docs/ESTADO.md` es el índice de carriles
  y no se toca al trabajar.
- **El contador de la suite ya NO está en ningún documento**: va en el trailer del commit de cierre
  («Verificación: suite N tests / M aserciones (Xs) · …») y el `pre-push` lo compara con la suite que
  acaba de correr (`#618`). Con dos carriles cambiando tests, **quien empuja la vuelve a medir tras su
  `git pull --rebase`** y escribe esa cifra en el commit; sin trailer, o con la cifra de otra suite, el
  push se corta y el hook dice qué declara y qué midió. (Hasta el 2026-09-16 era una línea «Suite N en
  verde» de `ESTADO.md` con el número dentro de negritas, y `#563` fue un push rechazado por no encontrarla.)
- `CLAUDE.md` (una línea por fila), `DEUDA.md`, `00-REFACTOR.md`: tus filas y tus secciones; no reescribas
  las del otro. Las trampas del cajón van al `§0` de `specs/sidebar-spa.md`, nunca a la fila.

**El ritmo**: `git pull --rebase` antes de cada push · empuja cada unidad verde pronto · **commit por
NOMBRE de fichero, nunca `git add -A`** (en `main` hay material del cliente ignorado y a veces ficheros
del otro a medias).

**El material del cliente**: si cambias algo suyo (un token del paquete, un logotipo, una copia nueva del
canvas), va en tu clon **y** en la rama `cliente/playjump` (su `README.md` lo explica). Nunca a `main`.

## 6 · Cómo trabaja el owner contigo

Desde F2 (`#623`) estas reglas viajan en el plugin `jumpweb-agente` (`reglas/owner.md`) y el hook de arranque
las inyecta en cada sesión de cualquier máquina; esta lista es su copia legible:

- **Edita con las herramientas de Read / Write / Edit**, no con `sed` ni con heredocs (lo pidió así).
- **Las decisiones de producto se PREGUNTAN, en simple** (con opciones y la recomendada primero), nunca
  se deciden por él. Cualquier ambigüedad, se pregunta.
- **Nada de workflows ni de enjambres de agentes sin que él lo pida.**
- **Lo visual se enseña EN VIVO** en `localhost:8081`, en móvil y escritorio, **antes de commitear**: una
  captura de ventana no enseña lo que ocupa más de una pantalla (eligió sobre capturas y lo revirtió al
  verlo, `#526`→`#527`).
- **Si repite que algo se ve mal**, deja de medir y **enséñale opciones renderizadas** para que elija; y
  si las rechaza todas, propón **quitar la variante**, no una cuarta.
- **«Todo en tarjetas»** en lo posible, con la pegatina que ya existe. Público: madres, familias y
  jóvenes — **sorpresa en los momentos, calma en el camino del dinero** (y el cajón ES ese camino).
- **Lo del cliente vive en su hueco** (su BD, su `.env`, sus ficheros ignorados): al repo solo entran
  mecanismos.
- **Commit, push y mutaciones se deciden por el CÓDIGO DE SALIDA** del test, nunca por un `grep passed`.
- **La shell conserva el `cd`**: no hagas `cd` dentro de un comando; usa rutas absolutas.
- **Rigor y empirismo**: mide antes de afirmar, con control; una guarda se escribe con su mutación.

## 7 · Correcciones medidas al montar el carril (2026-09-12, `#550`)

Cuatro cosas de este documento no se sostuvieron al medirlas desde el segundo ordenador. Se corrigen
aquí y no se reescribe lo de arriba: **la corrección va delante del texto que corrige**.

1. **La grieta 00 NO estaba abierta.** §3 dice «es decisión del owner y está ABIERTA»; el `doc/spa.md`
   del canvas la registra **cerrada por él** en su parada 05 («el cuerpo a 16 en todo el cajón»). Se le
   preguntó con las dos versiones delante y confirmó: **16**. Hecho en `#550`.
2. **Son DIECISÉIS grietas y SEIS paradas, no nueve y cinco.** §2 cita «una auditoría del cajón real con
   nueve grietas»: ésa es la primera pasada. El canvas lleva **16** (las 11-14 nacieron en su parada 06)
   y las 25 pantallas están dibujadas en seis paradas.
3. **Los artboards del cajón son más de tres, y la copia local solo trae tres.**
   `mockup_playjumppark_v2/` tiene `Pasos Compra`, `Pago y Desenlaces` y `Navegacion Cuenta`; en el
   canvas están además `Armazon Cajon`, `Identificacion`, `Mi Play Jump`, `Entrar y Crear Cuenta`,
   `Mapa SPA`, `Decisiones SPA` y `Auditoria Sistema SPA`. Se leen con `DesignSync`.
   ⚠️ **Y no se les raspa un `font-size` con un `grep`**: sus ficheros mezclan la pantalla dibujada con
   el aparato de anotación (rótulos y notas a 10-12 px), así que la cifra sale creíble y falsa.
4. **La receta del navegador no funciona tal cual** (§1·7). `npx playwright install chromium` deja el
   navegador en la caché de `npx` y el `playwright-core` de `node_modules` **no lo encuentra**
   («Executable doesn't exist»). Lo que funciona:
   `node node_modules/playwright-core/cli.js install chromium`. Y hacía falta instalar **`socat`**
   (`apt-get install -y socat`, como root) para el puente 8081→80.

5. **La banda de fases NO es trabajo de servidor, y el canvas lo dice en TRES sitios** (`#554`). Su
   `doc/spa.md` la lista como «lo único de la lista que no es cliente» y habla de «una prueba que
   congela sus tres fases». Medido: **`Purchase.php` está retirado** desde que el cajón es motor único,
   así que hoy la compone **`progress.js` en el cliente**, y la comparación contra el servidor «se fue
   con el motor» —lo dice el docblock de `SidebarProgressParityTest`—. Lo que queda del servidor son
   las claves de `lang/` y los manifiestos congelados del contrato de árbol.
6. **Dos sondas y un arnés tienen trampas que ya se pagaron** (`#554`): tras un clic **el ratón se queda
   donde pulsó**, así que la pantalla siguiente se mide con su CTA en `:hover` (apártalo antes de medir)
   · `scrollHeight` **no mide una caja recortada** y el hueco sale 0 (se suman hijos + huecos + relleno)
   · desde `#553` **las categorías del catálogo salen cerradas y sus productos se pintan igual**, así
   que esperar a `.catalog__item` pasa en verde y el clic agota el tiempo · y **el arnés de mutación
   deja el bundle SSR rancio** por su `touch` al restaurar → 35 casos del contrato en rojo con el árbol
   limpio.

7. **La sonda no llegaba al paso de PAGAR, y llevaba rota desde `#553`** (`#562`). El recorrido moría
   en el primer clic del catálogo: desde aquella tanda las categorías nacen **cerradas** y sus
   productos **siguen en el DOM**, así que `waitForSelector('.catalog__item')` pasa en verde y el clic
   siguiente agota el tiempo con el cajón sano. `#554` dejó el diagnóstico escrito y la sonda sin
   arreglar. ▶ *Que un nodo esté en el árbol no es que se pueda pulsar.* Hoy `abrirCategoria()` mira
   `aria-expanded` —el estado REAL del acordeón; una clase la lee solo el CSS— y el recorrido termina
   en `20-pagar`, **la pantalla que no medía nadie**: ni esta sonda ni `sonda-geometria.mjs`, que solo
   llega a lo público. ⚠️ Ese bloque va **después** del de la cuenta a propósito: a pagar solo se
   llega CON sesión, y la sesión la consigue ese bloque.

8. **El suelo táctil del cajón NO está cumplido, y no lo ve ninguna guarda** (`#563`). Medido sobre
   **32 pantallas**: `.sidecart__close` **26 px** en las 32 · `.bk-back` **20** en 30 ·
   `.pwd-input__toggle` **32** · `.acct__btn` **45**, contra los **48** del producto. Son los dos
   controles que existen en las 25 pantallas. ▶ `TouchTargetTest` recorre **RUTAS** de la web pública
   y el cajón no es una ruta suya — la misma lección que `#557` pagó con el stepper. **Antes de
   arreglarlo hace falta una guarda que vea el cajón**; el censo bueno lo da la sonda. Ficha en
   `DEUDA.md` (Alta) y es decisión del owner: toca el armazón, o sea las 25 a la vez.

   ⚠️⚠️ **CORREGIDO Y CERRADO A MEDIAS POR `#566`, y la corrección va delante del texto de arriba.**
   **La mitad de esa medición era FALSA**: `.bk-back` **cumple** —su pseudo absoluto le da 48
   exactos— y lo que aquella pasada leyó fue la **CAJA**, no el **ÁREA** efectiva. Es la trampa de
   `#264` y `#307`, pagada por **tercera** vez. ⚠️ Y el que sí fallaba estaba **peor**: el aspa mide
   **15 × 26**, o sea que tampoco llegaba de ancho — esa columna no la mira la sonda.
   ▶ **Lo que la frase «el censo bueno lo da la sonda» tiene de engañoso**: la sonda mide cajas, así
   que acusa a **cuatro** controles que cumplen por su pseudo (`.bk-back`, `.cart__remove`,
   `.cal__nav`, `.bk-foot__info-btn`). *Un instrumento que acusa a lo que ya está bien no sirve para
   escribir una guarda*: lo que vale es lo DECLARADO, y eso se lee del CSS.
   ▶ La guarda que este punto pedía ya existe: **`SidebarTouchTargetTest`**, con el censo sacado del
   MARCADO y una lista de 24 nominados que **solo encoge**.

9. **Antes de dar por bueno un censo del canvas, cuéntalo en el código** (`#566`). En una sola tanda,
   **tres** de sus cifras se quedaron cortas: «las únicas tres pantallas con antetítulo» eran cinco
   —y su propio artboard dibuja cuatro—, «el alta» eran las dos, y el `<summary>` que daba por
   resuelto estaba en cinco sitios con los mismos 20 px. ▶ *Un artboard describe el código del día en
   que se dibujó*, y la parada 05 ya lo había pagado con tres de sus ocho puntos.

9. **Hay un PEDIDO PAGADO sembrado en local, y sin él media cuenta no se puede mirar** (`#565`). El
   cliente de sonda no tenía ninguno, así que «Mis pagos», el historial y el desenlace de la reserva
   creada salían **vacíos** — y ahí es donde vive todo el dinero del cajón. Se sembró `R-UNPIRD`
   (119,60 € con señal de 50: total · pagado online · a pagar en el parque, los tres importes) con el
   DOMINIO (`OrderCreator`) y un `Payment` real por `onlineDueCents()`: **un pedido `paid` sin cobro lo
   rechaza el libro** (identidad I2) y la pantalla diría «en revisión». ⚠️ Si tu BD local se recrea,
   hay que volver a sembrarlo o esas pantallas vuelven a no tener sujeto.

10. **Un token que por defecto vale tinta esconde su propio error** (`#565`). `--money` —y cualquier
    rol que el producto declare como `var(--fg)`— **no cambia nada en la suite ni en un clon limpio**:
    solo se ve con el paquete del cliente instalado. Se colocó mal en cinco importes y **ni la suite ni
    una relectura lo vieron; lo vio la captura**. ▶ Con roles de este tipo, la guarda no puede mirar el
    color: tiene que mirar **qué regla lleva el rol**.

⚠️ **Y el reparto por TÍTULO de sección de §5 se queda corto**: medido, **31 declaraciones** de clases
del cajón viven fuera de esos bloques (`.auth__*`, `.acct__*`, `.whoblock__*`, `.guardnote__*`,
`.acc-tile__name`…), más dos familias enteras (`.qr-pass__*`, `.dep-pick__*`). ▶ *Lo que define al cajón
es qué clase EMITE, no dónde está escrita su regla* — el censo bueno está en `SidebarBodySizeTest`.

## 8 · Lo montado en la BD LOCAL de esta máquina para el ojo del owner (mudado de `carriles/spa.md` el 2026-09-27)

> Se mudó VERBATIM cuando el carril pasó de su techo de 32 KB: es inventario de esta máquina, no estado del carril.
> Todo reversible; lo nuevo se añade al final con su número.

⚠️⚠️ **LO MONTADO EN LA BD LOCAL para el ojo del owner (24/25-09), todo reversible**: (1) cinco ajustes FALSOS
en `settings` (`analytics.driver=posthog`, `analytics.posthog_project` inventado y los tres ids de píxeles
`marketing.*`): se quitan borrando esas filas; (2) el aviso de la analítica ENVIADO a las 57 cuentas de
cliente (`analytics:notify-accounts`; `analytics_notified_at` puesto, 57 correos en Mailpit `:8028`); (3) el
experimento de demostración **`carcasa` VIVO** (cajon 50 / isla 50) con 143 sesiones `OJOEXP…`, 24 sellos
`JW-OJO…` con `visitor_id` y 3 contaminados: guion `/home/sail/e2e/ojo-experimento.php` en el contenedor,
`OJO=desmontar` lo quita entero; (4) un pase de la vuelta de Redsys para la casilla tras comprar (caduca en 6 h,
un solo uso); (5) **el fixture «probe-ojo-fiesta»** (`probe-ojo-fiesta.php` en la carpeta de almacenamiento,
fuera de git, al lado de «probe-ojo-analitica»): 32 fiestas `JW-FIESTA…` de agosto y septiembre con formularios, extras,
invitaciones, firmas, cobros en el parque y 323 hechos, 32 anfitriones `fiesta-N@ojo-fiesta.jumpweb.test` y TRES de
ellas que «vinieron invitadas» a una fiesta de agosto antes de comprar; `OJO=desmontar` lo quita entero; (6) **lo de
las encuestas** (25-09): las dos de ejemplo (`visita-de-hoy`, `que-tal-ayer`; se borran desde el panel), el cliente
`sonda-puerta@jumpweb.test` (2179) con dos respuestas y visitas, la respuesta del owner (cuenta 70 sobre el cliente 593)
y las visitas de 593 y 2179, el fixture `probe-ojo-encuestas` (64 filas sobre los anfitriones de la fiesta;
`OJO=desmontar` lo quita) y el correo en Mailpit; (7) **lo de F1 (26-09)**: los ajustes `party.park_video(_poster)`
(`public/videos/`, copiado de la instancia), la nota de Google de prueba (4,9 · 155), el Menú 1 (107) repartido y
marcado en el pack 105, la fiesta `JW-OJO-F1` (`ojo-f1.php`, `OJO=desmontar`); (8) **de las sondas de F6a**, en esa
fiesta: las respuestas 260→267 («Sonda …», todas «sí») y sus autorizaciones de prueba (desde la 117); (9) **de F3a**:
el pack 105 con `honoree_counts` ENCENDIDO y la fiesta sellada `JW-OJO-F3` (reserva 1126; `ojo-f3.php`, fuera de git,
imprime sus URL; `OJO=desmontar` la quita y apaga el ajuste); (10) **de F5 (26-09)**: en el catálogo local, la Tarta
(109) «para 12» en el bloque de la tarta, los combos 111–113 (6/10/15) y los cubos 114–116 (6 cada uno, DE PRUEBA) en
sus familias y en el bloque de los padres, y «Nº aproximado de adultos» del pack 105 como tipo `adults`; y la fiesta
`JW-OJO-F5` (dentro de 3 días, 14 niños: la tarta cierra mañana y no llega). Todo con `ojo-f5.php` (fuera de git; lo
de antes, en `ojo-f5-antes.json`; `OJO=desmontar` lo deja como estaba); (11) **de F6b**: en `JW-OJO-F1`, las
respuestas «… Sonda…» 311→326, seis autorizaciones `avisame-sonda…@jumpweb.test` con sus `birthday_reminders` y sus
correos en Mailpit; (12) **de F7 (27-09)**, `ojo-f7.php` (fuera de git; `OJO=desmontar` quita las tres): `JW-OJO-F7`
(dentro de 5 días, Noa sin cubrir), y con `OJO=f7c` `JW-OJO-F7P` (HOY, Noa atada: la puerta) y `JW-OJO-F7V` (mañana, su
aviso de la víspera mandado solo a la cuenta de sondas); sondas `sonda-f7.mjs` y `sonda-f7c.mjs`; (13) **de F8**:
`JW-OJO-F8` (`ojo-f8.php`, enviada, con respuestas; `OJO=desmontar`) y `sonda-f8.mjs`. Y siguen montados el fixture «probe-ojo-analitica» (90 pedidos
`JW-OJO…`, 25 clientes, 506 sesiones) y el de reseñas «probe-ojo-resenas». ⚠️ **Plataforma dejó la local preparada para
que el owner pruebe la ISLA** (24-09 noche, `694529a8`): `sidebar.shell = isla` por el panel, `public/_isla-prueba.html`,
la invitación ENCENDIDA en los packs 105/106 — **«no deshacer sin él»**; el cajón local abre ahora en la isla.

(14) **De la T1 de la analítica para decidir, las encuestas ANÓNIMAS (27-09, `#754`/`#757`)**: la migración
`make_survey_responses_anonymous` partió las 67 filas locales (67 participaciones, 52 respuestas sin sello, 18 tokens
gastados; copia de antes en el scratchpad de la sesión, `antes-t1-encuestas.sql`). El fixture viejo `probe-ojo-encuestas.php`
ya NO sirve (escribe la forma de antes): lo sustituye `probe-ojo-anonimas.php` (misma carpeta, fuera de git; 64
participaciones, 46 respuestas SELLADAS sobre los 32 anfitriones, vueltas al parque y `surveys:resolve-returns`;
`OJO=desmontar` borra sus participaciones, TODAS las respuestas de las dos encuestas de ejemplo y las visitas de esos
anfitriones). La cuenta de sonda `sonda-puerta@jumpweb.test` (2179) quedó SIN participaciones, para que la puerta le vuelva a
ofrecer la interna. ⚠️ El `OJO=desmontar` previo a la siembra se llevó TAMBIÉN la respuesta de prueba que el owner contestó
en vivo el 25-09 (ya anónima, no había forma de separarla de las del fixture); su participación (cliente 593) sigue, así
que la puerta no le vuelve a ofrecer la interna a esa cuenta.

(15) **De la T3c·2, los objetivos del mes (28-09, `#759`)**: la sonda del panel (`sonda-analitica-panel.mjs`) GUARDA en la BD
local, por el formulario, dos objetivos del mes en curso —Ingresos netos 3.000 € y Conversión 8 %— en cada pasada (la segunda
dice «No había nada que cambiar»). Se quitan vaciando esos dos campos en «Objetivos del mes» o borrando sus filas de
`analytics_goals`; quedan dos filas `analytics.goals_updated` en el rastro.

(16) **De la TP·1, la fecha de nacimiento (28-09, `#792`)**: el cliente de sondas 2179 (`sonda-puerta@jumpweb.test`) lleva
`born_on = 1988-03-12` para enseñar la ficha («12/03/1988 · 38 años»); se quita poniéndola a `null`. La sonda del panel vive
fuera de git, en `storage/app/audit/sonda-tp1-panel.mjs` (no guarda nada: escribe la fecha en el modal y lo cierra); la del
cajón, `sonda-tp1-cajon.mjs`, pone y QUITA la fecha de `probe-card` (termina sin ella). ⚠️ **Medido el 28-09: la local ya NO
abre la isla** —no hay fila `sidebar.shell` y el valor por defecto es el cajón—; lo de arriba sobre la isla es de su fecha.

(17) **De la TP·2, quién viene (28-09, `#792`)**: `ojo-tp2.php` (en la carpeta de auditoría de `storage`, fuera de git) puso fecha de nacimiento a 53
titulares con visita pagada de julio a octubre que no la tenían y 81 menores con `surname = OJO-TP2`; lo de antes, en
`ojo-tp2-antes.json`. `OJO=desmontar` borra esos menores y devuelve esas fechas a `null`.

(18) **Mudado verbatim del carril el 28-09 (su techo)**: 🩹 **En la BD LOCAL hay 19 firmas de waiver HUÉRFANAS (15 titulares;
basura del 26–27 de agosto)**: apuntan a versiones legales 10…34 que no existen (solo viven las tres v1; la FK RESTRICT está,
así que fue un reseteo por debajo). La ficha del cliente 70 caía con un 500 («version on null» en `WaiverStatus::build()`);
desde el 25-09 una firma sin versión cuenta como ANTERIOR (señalada, re-firma en la siguiente compra), con test y mutación
(`WaiverStatusBatchTest`). Sonda de solo lectura: `probe-waiver-70.php` en la carpeta de almacenamiento. Si mides cadenas,
compara ANTES/DESPUÉS.

(19) **De la C1 de los correos salientes (28-09, `#794`)**: `c1-enviar.php` (en la carpeta de auditoría de `storage`, fuera de
git) manda «Ya tienes cuenta» de verdad al cliente de sondas 593 —una fila en `email_sends` y un correo en Mailpit— y la sonda
`sonda-c1-correos.mjs` compara la copia con Mailpit y mira el panel. Se quitan borrando esas filas de `email_sends` (el 29-09,
las filas 1 y 2: una por lanzamiento).

(20) **De la C2 (29-09, `#795`)**: el interruptor `emails.track_clicks` ENCENDIDO en local (`php artisan app:set-setting
emails.track_clicks 0` lo apaga) y los envíos de sonda 3–8 y 11–12 del cliente 593 con sus clics (`c2-enviar.php`,
`sonda-c2-clics.mjs` y `c2-veredictos.php`, en la carpeta de auditoría). Los 9 y 10 salieron del control SIN bloqueo, con
«3 clics» falsos, y se borraron. Se quitan borrando esas filas de `email_sends`: sus clics se van en cascada. De la C2b
(`sonda-c2b-cuando.mjs`, con `BASE=http://localhost:8081` y el puente `socat` dentro del contenedor): los envíos 13 y 18; los
14–17 salieron de los controles SIN la protección de la vista previa y se borraron. De la C3 (`#797`,
`sonda-c3-aperturas.mjs` y `c3-aperturas.php`): el interruptor `emails.track_opens` ENCENDIDO (`app:set-setting
emails.track_opens 0` lo apaga), una fila de `cookie_consent_logs` del cliente 593 con «análisis» aceptado (se quita
borrándola) y los envíos 19 y 20 con sus aperturas; el 21, del control sin la protección, se borró.

(21) **De la C4 (29-09, `#796`)**: `c4-ojo.php` (en la carpeta de auditoría) monta un mes de correos con el destinatario
`ojo-c4@jumpweb.test` y sin cuenta —vísperas a las 18:00, cancelaciones con la ráfaga de un escáner, confirmaciones—, con sus
clics y aperturas repartidos, para ver «Marketing → Los correos» y «Cuándo abren y pulsan». `OJO=desmontar` lo borra todo
(clics y aperturas en cascada). La sonda: `sonda-c4-cuando.mjs`, con `ESPERADO` de `c4-esperado.php`.

(22) **De la TP·3a (29-09, `#793`)**: `ojo-tp3.php` (en la carpeta de auditoría) va ENCIMA de `ojo-tp2.php`: reparte las fechas
de sus 53 titulares entre los seis tramos de Google y las de sus 81 menores (`OJO-TP2`) entre las cinco etapas; lo de antes, en
`ojo-tp3-antes.json`. Para verlo, «Últimos 90 días». `OJO=desmontar` devuelve las fechas: ⚠️ **desmontar este ANTES que el
de la TP·2**. La sonda: `sonda-tp3-panel.mjs`, con los `ESPERADO_*` de tinker (spec §4.14).

(23) **De K1 de los complementos (29-09, `#807`)**: `ojo-k1.php` (en la carpeta de auditoría) va ENCIMA de `ojo-f5.php`: en el
pack 105, los calcetines (110) pasan a `postform` (tope 20, plazo 0 h, «para 1») y un «Cono de chuches» NUEVO de prueba
(1,50 €); y la fiesta `JW-OJO-K1` (reserva 11225, 14 niños, el 04-10). Lo de antes, en `ojo-k1-antes.json`. `OJO=desmontar`
lo deja como estaba: ⚠️ **desmontar este ANTES que el de F5**. La sonda: `sonda-k1.mjs "<url>"`.

(24) **De K2 (30-09, `#807`)**: `ojo-k2.php` va ENCIMA de `ojo-k1.php`: dos tartas NUEVAS de prueba en el bloque de la tarta del
pack 105 («Tarta de chocolate», 12 raciones, 28 €; «Traemos la nuestra», 10 €), en `ojo-k2-antes.json`. `OJO=desmontar` las
borra (y sus líneas): ⚠️ **desmontar este ANTES que el de K1 y el de F5**. La sonda: `sonda-k2.mjs "<url de JW-OJO-K1>"`.

(25) **«Solo configuración» (30-09, `#808`)**: `ojo-config.php` va ENCIMA de `ojo-k2.php`: el PACK 106 configurado solo con lo que
el panel escribe —la merienda como tres complementos NUEVOS a 0 € (familia «Merienda», tope 1, en la invitación), el Menú 1/2
DESENGANCHADO, las tartas sin bloque en «Tartas», calcetines y cono en la lista, combos y cubos en «Para los adultos», la
invitación ENCENDIDA— y la fiesta `JW-OJO-CFG` (reserva 11236, 14 niños, el 06-10). Lo de antes, en `ojo-config-antes.json`.
`OJO=desmontar` lo deja como estaba: ⚠️ **desmontar este el PRIMERO**. La sonda: `sonda-config.mjs "<lista>" "<invitación>"`.

(26) **De la R1·T (30-09, `#802`)**: la migración `mail_texts` APLICADA en local y el permiso `emails.edit_texts` sembrado;
`ojo-r1t.php` (en la carpeta de auditoría) crea el rol `ojo-correos` (solo `emails.edit_texts` y `settings.manage`) y su empleado
`ojo-correos@jumpweb.test` / `ojo-correos-2026` (con `staff` para poder entrar al panel, sin authenticator). `OJO=desmontar`
borra el rol y el empleado; los textos que se guarden en `mail_texts` se quedan (volver al de fábrica los borra). La sonda:
`sonda-r1t.mjs` (deja la tabla como la encontró).

## 9 · La foto vieja del carril (mudada VERBATIM de `carriles/spa.md` el 2026-09-29, su techo)

- ✅ **27-09, TODO EN `main` Y APROBADO POR EL OWNER**: **F7** (`#752`, la exención de quien cumple: la lista y su
  justificante, la puerta, la víspera y la API 1.42.0; §4.13; arnés `mutar-exencion-cumple.sh` 55) · **F8** (`#753`, una
  acción por tarea para la invitación, las cifras sin filtro, cada envío medido; API 1.44.0; §4.14; arnés
  `mutar-envio-invitacion.sh` 28/28) · **F9** (el zip tercero `#780`: «¿Querías decir …?» en el correo de la firma y el
  primario que llega; `WaiverSheet` NO, el texto se presenta en el flujo, `waiver-probatorio.md` §4.4; §4.15; arnés
  `mutar-zip-tercero.sh` 12/12) · y el `--warn-100` de PlayJump fuera de `fiesta.css` (`PaletaNeutraTest` lee ya las
  familias de estado y las tripletas `r, g, b`). La instancia de esta máquina, al día (`100dd09`, el zip tercero dentro).
- ✅ **LA ANALÍTICA, ENTERA (`#735`), T1→T7 en `main` y vista por el owner en vivo** (24 y 25-09): T2 (el cuadro), T3
  (consentimiento, driver, píxeles, `/cookies`), T4 (la 360, segmentos, opt-in), T5a·T5b (experimentos; **T5c decidida en
  `#738`**: sin mecanismo de textos, la hipótesis la nombra el owner tras la v2.0.0), **T6 la fiesta** (`#739`: *el invitado
  no es un visitante*; `PartiesReport`, la pestaña «Fiestas», el segmento `guest_became_customer`; el detalle por tanda en
  `analitica-fiesta.md` §4.6) y **T7 las encuestas** (`#740`→`#742`; el detalle por tanda, las guardas y «lo que
  enseñó» en `encuestas.md` §4.6; el owner contestó una en vivo desde la puerta y cerró con «buen trabajo»).
  Quedan: `[PENDIENTE: asesoría]` (5) del correo de servicio, la **T2e** solo si el volumen lo pide, el `EXPLAIN` con
  volumen en staging. Todo espera la v2.0.0 (`#670`). Las trampas pagadas en la T7 viven en `encuestas.md` §4.6.

De la foto, mudado VERBATIM el 2026-09-29 al cerrar la sesión (su techo, con `#802`):

- ⏸ **LA ANALÍTICA PARA DECIDIR, en pausa tras la T4** (`#755`, spec ✅; T6–T8 después de los correos). En `main` y APROBADAS por el owner: T0a·T0b·T0c (`#756`),
  T1 encuestas anónimas (`#754`/`#757`), T2 ocupación (`#758`) y, el 28-09, **T3a la forma** (`#759`: siete pestañas, solo
  pide la abierta, un catálogo de 58 cifras), **T3b el veredicto** (`#790` `[DECIDIDO owner]`: «normal» es el mín–máx de los 12
  periodos anteriores), **T3c·1 «lo que ha cambiado»** (`#791`) y **T3c·2 los objetivos del mes** (el botón al pie de «Resumen», la
  línea en la tarjeta; permiso `analytics.manage`). Lo construido y lo que enseñó cada una: spec §4.13. ▶ **La TP, el
  público** (`#792` `[DECIDIDO owner]` 28-09: la fecha de nacimiento del titular, entera y opcional; los padres por la edad de sus
  hijos con el opt-in de hoy; §4.14): ✅ **TP·1 la captura, en `main` y APROBADA** (28-09; su «al detalle» y lo que enseñó,
  §4.14; contrato **1.49.0**) · ✅ **TP·2 «Quién viene»** en `main` y aprobada → ✅ **TP·3a y TP·3b en `main` y aprobadas**
  (`#793`: los tramos de los anuncios y fuera «Exportar segmento»; arnés `SOLO=TP3` 12/12, sonda 20/20; 29-09) · ⏸ TP·3c ·
  ✅ **T3d «Explícamelo con IA»** en `main` y aprobada (techo 12 KB, `#798` `[DECIDIDO owner]`; 29-09) · ⏸ T3e (`#799`) ·
  ✅ **T4 la cartera** en `main` y aprobada (§4.8.quater; arnés `SOLO=T4` 16/16, sonda `sonda-t4-panel.mjs` 17/17) · ✗ T5 (`#800`). ⚠️ Visto de paso: la ficha
  del cliente en `zh_CN` pinta el parentesco de sus menores como la clave cruda (`admin.users.dependents.relationship_*` solo en es).

De la foto, mudado VERBATIM el 2026-09-29 por la noche (su techo, con `#800`):

- ✅ **LA FIESTA DEL SISTEMA NUEVO: T1a→T4, F1→F5, F6a y F6b EN `main` (25/26-09)**, F1/F2/F6a **aprobadas por el owner**
  (`#747`, menos «Crear mi QR», que no va) — **F3 (`#747`)**: quien cumple es la PRIMERA fila (ajuste del pack
  `honoree_counts`, sello `honoree_row` al reservar, ficha 0 con espejo en la invitación, clavada, con su plaza en el
  suelo y su firma de menor a cargo; la lista guardada a 0 px) y «Al final viene» (web, API 1.36.0 y sin JS) — las
  tres páginas con lo que HAY a
  0 px (`#768`), la piel vieja FUERA (T4), **la invitación ENTERA como el mockup (F1)**, **personalizar en tiempo real
  (F2)** y **la firma DENTRO del recibo (F6a, `#746`)**: una fuente para las dos pantallas (`ComposesGuardianForm`), el
  recibo contra `InvPagina` a 0 px en sus diagnósticos, y seis defectos arreglados que vio el navegador y no la suite
  (el 429 del cupo compartido, el reenvío mudo, el foco tras un ancla, la cabecera que desborda en móvil —el diseño
  también—, el color del botón, la fiesta llena con un «sí» atado). El detalle, spec §4.6; lo que enseñó, §4.7.

De la foto, mudado VERBATIM el 2026-09-29 por la tarde (su techo, con la TP·3):

- ✅ **Esta máquina, montada para la fiesta (25-09)**: la instancia clonada en `~/proyectos/instancias/playjump` (el
  diseño con su sha256 verificado), `INSTANCIA_RUTA=/var/www/instancias/playjump` en el `.env` (ruta DEL CONTENEDOR),
  `public/instancia/` copiado de `publico/instancia/` (ignorado por git); la receta entera, en Trampas vivas 🏠.
- ✅ Con su ✅ en vivo y sin desplegar (`#670`): **la ficha de Google** (`#720`→`#734`; el resto, contra un DOBLE hasta finales
  de octubre: `google-business-profile.md` §0) · **la invitación digital** (`#718`, `celebracion-e-invitacion.md` §10.18) · **las
  25 pantallas del cajón** (`#550`→`#568`).

Del buzón del carril, mudado VERBATIM el 2026-09-29 (su techo; mensajes a plataforma del 26-09, ya leídos):

- **Para plataforma (26-09, F6b, `#750`)**: `scripts/deploy.sh` espera ya **10** tareas (entra `birthday-reminders:send`,
  horaria); una tarjeta en tu hub («Precios y productos → Avisos de cumple») y un ajuste en `Settings.php`
  (`party.birthday_reminder_weeks`). El contrato NO cambia. En `fiesta.css`, `[hidden]` oculta ya sin `.js`.
- ❗ **Para plataforma (26-09, F5, `#749`)**: el CONTRATO **1.39.0** es mío (tras tu 1.38.0): en `PostFormAddon`
  `serves`, `family`, `block` e `image_url`; en `GuestForm` `cake_declined` y `saved_at`; `PUT` acepta `cake_declined`;
  `adults` en el `enum` de tipos de campo del catálogo. Tu siguiente, **1.40.0**. ⚠️ `PostFormAddons::viewFor()` (lo
  que lees en «Antes de venir», si lo lees) lleva cuatro datos más, sin cambiar los de antes. Leído tu buzón de la T5e·1
  (`#778`): nada tuyo toca la fiesta.
- **Para plataforma (26-09, F1c)**: `party.park_video` y `party.park_video_poster` son hechos públicos: si
  `instancia-y-landing-fuera.md` §2 cuenta los ajustes públicos, sumadlos.

Del «Atendido» del buzón, mudado VERBATIM el 2026-09-29 al cerrar la R1a de los correos (el carril, en su techo; los mensajes
de plataforma a los que responden, ya retirados por su emisor):

- **Plataforma 28-09** (NORMAS, `#842`: la hoja del descargo, componente de la instancia): leído; la fiesta NO la pide —F9 la
  dejó fuera a propósito: el texto se presenta en el flujo (`waiver-probatorio.md` §4.4)—. `park_rules` con `icon` y `level`: visto.
- **Plataforma 28-09** (`#836`): `openWith()` pasa el producto de la intención `fiesta` a `drawer_opened`: correcto.
- **Plataforma 27-09 noche, 2.º** (T5f `#824` y los hijos por producto `#825`: `outcome.js::confirmationLine` con
  `minors_only`, la 1.45.0 suya y la 1.46.0 mía, `catalog.js` al trozo `card`): atendido, nada mío choca.
- **Plataforma 27-09 noche** (`#789`: correos y puerta, míos; el zip (4) sin correos ni fiesta; `Medir` aplazado; T2·9;
  `#822`: `sugerirCorreo` mudada a `ui/correo.js`, `logica.js` la reexporta, 19/19 y 12/12): atendidos.
- **Plataforma 27-09** (ESLint sobre la fiesta y mis `/* global */` fuera, la isla sin valores de PlayJump, `#788` con la
  1.43.0, la Z4 y el zip tercero `#780`): atendidos; el zip tercero, portado a la fiesta en F9 (§4.15).
- **Plataforma 25-09** (`#765` el traspaso de la fiesta, la calculadora T4d junto a mi motor, las peticiones (1) y (2),
  el aviso previo de la T4a·3, la banda 790–819 para cuando agote la mía): atendidos, contestados arriba.
- **Web `#540`** (12-09): atendido el 13-09. (Los de plataforma del 21 y el 24-09, retirados por su emisor: en git.)

De la foto, mudado VERBATIM el 2026-09-29 a las 23:00 al cerrar la lista del owner (`#805`; el carril, en su techo):

- ✅ **LOS CORREOS SALIENTES, CERRADOS** (`specs/correos-salientes.md`, `#794`→`#797`, 29-09): C1 el registro y la vista previa,
  C2 los clics por envío, C2b la actividad y el aparato, C3 las aperturas y C4 «cuándo» en Marketing, todo en `main` y
  APROBADO por el owner (lo construido y lo que enseñó cada una, §4.7–§4.15). Queda en PRODUCCIÓN: encender los dos
  interruptores (Ajustes → Avanzado → Correos, APAGADOS de fábrica) cuando `/privacidad` y `/cookies` los nombren
  (`[PENDIENTE: asesoría]`). Fixture `c4-ojo.php` montado en local (`OJO=desmontar`).

De la foto, mudado VERBATIM el 2026-09-29 a las 23:10 al contestar el owner los complementos (`#807`; el carril, en su techo):

- ✅ **LA R1b (los iconos), EN `main` Y APROBADA** (29-09: «buen trabajo, acepto los correos»; §4.1.3): máscaras de paleta
  teñidas por su `PLTE`, sin GD; arnés 12/12. El `--correo-icono` de PlayJump, empujado a la instancia con la hoja.
- ✅ **LA R1a DE LOS CORREOS, EN `main` Y APROBADA** (29-09, el owner: «visto bueno, buen trabajo»; `correos-rediseno.md` §4.1.1
  y §4.1.2, `#803` el botón como el diseño, `#804` los enlaces legales se quedan): la plantilla del diseño en el molde, los 28
  sin tocarlos; arnés 39/39, sonda 28/28; la hoja de PlayJump en la instancia y declarada por plataforma (`da0f84d`). En la BD
  local, ~100 filas de `email_sends` del cliente de sondas (`scripts/banco-correos.php`).

De la foto, mudado VERBATIM el 2026-09-30 al cerrar K1 de los complementos (el carril, en su techo):

- **LOS CORREOS** (`specs/correos-rediseno.md`, `#800`→`#804`): la spec, medida con el
  censo HAY/FALTA contra el zip (§1), y cuatro decisiones del owner: el 7 sigue siendo la encuesta (`#801`); las ocasiones, al
  final; el orden plantilla → textos editables → reserva → comerciales → felicitaciones; y **los TEXTOS, editables desde el
  panel** con estructura fija, en es/en/fr y con permiso propio (`#802`: «profesional y robusta, sin chapuzas»). La analítica,
  en pausa tras la T4 (T5 no se hace, `#800`; T6–T8 después).

De «por dónde retomar», mudado VERBATIM el 2026-09-30 al empezar la R1·T (el carril, en su techo):

3. ✅ **LA FIESTA DEL SISTEMA NUEVO (`#765`, `fiesta-sistema-nuevo.md` ✅ `#743`): T1a→T4 y F1→F9 en `main` y aprobadas
   (`#747`→`#753`, §4.6–§4.15) — en código NO TIENE NADA PENDIENTE**; espera la v2.0.0 (las páginas vivas se visten con
   las hojas de la instancia, `#769`). **Si llega un zip nuevo**: `git pull` en la instancia, `git diff <antes>..HEAD` de
   `diseno/`, copiar `publico/instancia` a `public/`, `optimize:clear`, y una tanda F10 en §4. **A prueba**: «Invitar a
   más» (`#753`: si a los meses casi nadie lo usa, se quita). Ofrecido y sin pedir: las `transiciones` de `<x-pagina>`
   (`#781`). Para el ojo: `JW-OJO-F8`, `F7`, `F5`, `F3`, `F1`. **Reglas en pie**: el suelo sin JavaScript · `#739` · la
   firma y su prueba (`waiver-probatorio.md` §4.4) · hoja en blanco (§7.2·R1) · `#706`. Sueltos de la analítica de
   antes: **T5c** cuando el owner nombre la hipótesis (`#738`) · el `EXPLAIN` con volumen en staging.

- Mudados VERBATIM del buzón de `carriles/spa.md` el 2026-09-30 (su techo): los dos avisos al carril de CORREOS, que desde
  `#789` son de este carril:

### ❗ Para el carril de CORREOS (emisor: SPA, 29-09) — AVISO PREVIO: la R1a del rediseño cambia TU molde (`#789`, `#800`)
- `correos-rediseno.md` §4.1.1: `BrandedMailMessage` deja el Markdown y pinta con vistas propias (documento + bloques del
  diseño, oscuro por clase, roles de la hoja `hojas.correo` de la instancia); `vendor/mail/**`, `themes/brand.css` y el
  layout se retiran cuando los 28 pasen; `MailThemeTest` se reescribe por las MISMAS propiedades; `emails/partials/book` y
  `product-card` ganan clases de rol. Los verbos del molde (`hero`, `line`, `notice`, `outro`, `action`) no cambian. En
  `wip/correos-r1a` hasta el ojo del owner. Si algo tuyo está a medias ahí, dímelo aquí antes. Y `#803` (owner, 29-09)
  sustituye tu mapa del naranja en los correos: el principal de cada correo va en el color de acción, como el diseño.

### ❗ Para el carril de CORREOS (emisor: SPA, 19→26-09; pendiente de tu «atendido»)
- ▶ **Correos nuevos sobre tu molde, SIN tocarlo, todos en tus censos**: `VisitEveNotice` (`#717`),
  `GoogleBusinessLocationChanged` (`#725`), `AnalyticsLinkNotice` (T3a·4; el owner lo vio en Mailpit el 24-09), el del
  día siguiente de las encuestas (T7) y, **el 26-09, `BirthdayComingNotice`** («El cumple se acerca», `#750`: comercial,
  con la baja al pie y en `List-Unsubscribe`; `EmailUtmTest` 27 → 28; el asunto pasa sus datos en línea, como pide
  `MailInboxLineTest`). Te queda tu OJO en Gmail/Outlook. Tocado `GuestFormRequest` (solo con invitación). El diseño del
  24-09 trae **quince correos** rehechos (`paginas/correos.card.html` de la instancia): tuyos cuando llegue su tanda.

## Anexo · La fila del enrutador, mudada el 2026-09-16

> Lo que decía la fila **«El carril del SPA en el OTRO ordenador · rediseñar el cajón (Fase 4) · el material de PlayJump en otra máquina · la rama `cliente/playjump` · qué ficheros son de cada carril»** de `CLAUDE.md` cuando el enrutador bajó a una línea por fila
> (`DECISIONES #619`). Se conserva **verbatim** porque es historia de trampas medidas: léelo
> después del §0 y no lo reescribas. Documentos que la fila citaba: `docs/CARRIL-SPA.md` · `docs/ESTADO.md`.

- **`docs/CARRIL-SPA.md`** (`#530`) —
- ❗❗❗ **LO DEL CLIENTE NO ESTÁ EN `main` Y NO PUEDE ESTARLO** (`#1`): paquete de tema, marca, kit, copias del canvas y datos del catálogo viajan en la rama **huérfana `cliente/playjump`**, que se coloca con `git show origin/cliente/playjump:aplicar.sh \| bash` (extrae con `git archive \| tar`, **nunca con checkout**, que lo dejaría en el índice) y **nunca se fusiona**.
- ⚠️⚠️ **Si cambias algo del cliente, cámbialo también en la rama.**
- ▶ **El reparto por FICHERO** está en su §5 —cajón (`resources/js/sidebar/**`, `lang/*/tickets.php` y `account.php`, sus bloques de `site.css`) contra la web (`pages/**`, `components/site/**`, `landing.css`)— y **lo compartido (tokens, `layout.blade.php`, `app.js`, las listas globales de las guardas) se avisa ANTES en `ESTADO.md`**.
- ⚠️⚠️ **La línea «Suite N en verde» la cambian los dos carriles**: quien empuja la re-mide tras su `git pull --rebase`.
- ⚠️ **`npm install`/`uninstall` poda `playwright-core`** (va sin guardar).
- ▶ Su §6 son las reglas de trabajo del owner, que antes vivían solo en la memoria de un ordenador
