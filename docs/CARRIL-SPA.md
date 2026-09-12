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

- **Grieta 00** · el cuerpo del cajón está a **13 px** y el suelo del sistema son **16**: subirlo es
  revisar el reflujo de 25 pantallas. **Es decisión del owner y está ABIERTA** — pregúntala primero.
- **Grieta 01** · el botón que avanza la compra se pinta con `var(--zone-1)`, el color de una ZONA (de los
  datos): tiene que pasar al rol de acción (`--action`/`--interactive`, `#436`, `#209`).
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

**Los documentos calientes**:
- `DECISIONES.md`: tus números salen de **550–579**, y la entrada se añade **al final del fichero**.
- `ESTADO.md`: toca **solo tu fila** del cuadro de carriles y tu propio bloque; los avisos al otro van
  ahí como «▶ Para el agente de la web: …», y quien lo lee y actúa lo retira.
- **La línea «Suite N en verde»** de `ESTADO.md` la lee el `pre-push` y tiene que coincidir con la suite
  REAL. Con dos carriles cambiando tests, **quien empuja la vuelve a medir tras su `git pull --rebase`**;
  un conflicto en esa línea no se resuelve eligiendo una cifra, se resuelve **corriendo la suite**.
  ⚠️⚠️ **Y NO la busques con `grep 'Suite [0-9]'`: no la encuentra** (`#563`, un push rechazado). El
  formato real lleva el número dentro de negritas —`Suite **4792 en verde** (30.187 aserciones…)`—, así
  que el patrón obvio da cero resultados y se concluye que la línea no existe. Búscala **por la cifra
  vieja** o por `en verde`. El hook dice exactamente qué declara y qué midió, así que si llegas ahí se
  arregla en un minuto: lo caro es creer que no hay nada que actualizar.
- `CLAUDE.md`, `DEUDA.md`, `00-REFACTOR.md`: tus filas y tus secciones; no reescribas las del otro.

**El ritmo**: `git pull --rebase` antes de cada push · empuja cada unidad verde pronto · **commit por
NOMBRE de fichero, nunca `git add -A`** (en `main` hay material del cliente ignorado y a veces ficheros
del otro a medias).

**El material del cliente**: si cambias algo suyo (un token del paquete, un logotipo, una copia nueva del
canvas), va en tu clon **y** en la rama `cliente/playjump` (su `README.md` lo explica). Nunca a `main`.

## 6 · Cómo trabaja el owner contigo

Esto vive en la memoria del agente del primer ordenador, y aquí queda escrito para ti:

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

⚠️ **Y el reparto por TÍTULO de sección de §5 se queda corto**: medido, **31 declaraciones** de clases
del cajón viven fuera de esos bloques (`.auth__*`, `.acct__*`, `.whoblock__*`, `.guardnote__*`,
`.acc-tile__name`…), más dos familias enteras (`.qr-pass__*`, `.dep-pick__*`). ▶ *Lo que define al cajón
es qué clase EMITE, no dónde está escrita su regla* — el censo bueno está en `SidebarBodySizeTest`.
