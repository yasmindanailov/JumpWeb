# Carril · Plataforma (producto e instancias)

> Máquina: **este ordenador** (`~/proyectos/JumpWeb`) · Banda: **610–639 AGOTADA con `#639`** → sigue en
> **640–669** · Último usado: **`#644`** · Spec: `docs/specs/producto-e-instancias.md` (§0 y §4.9) y, para lo
> que viene, `docs/specs/instancia-y-landing-fuera.md` · Actualizado: 2026-09-19 (F4 cerrada, F5 abierta).
> Este fichero lo escribe SOLO el agente de este carril (`DECISIONES #621`): foto, retomar, ficheros y buzón.
> Techo 24 KB (check 10). El contador de la suite no vive aquí: va en el trailer del commit.

## Foto

- **Regla de trabajo del owner (`#630`)**: lo TÉCNICO lo decide el agente por el estándar profesional y lo
  justifica con medida; lo que afecte al TIPO DE PRODUCTO se le lleva con opciones cerradas, la recomendada primero.
- **F0, F1, F2 y F3 CERRADAS.** F2 la cerró el owner el 18-09 (`#633`: dio por revisadas las frases; el agente
  midió 5 de 6 y los cuatro arreglos del mapa, no el 6 de 6 en sesión nueva). Las tres skills viejas del repo,
  retiradas; el plugin `jumpweb-agente` (repo `~/proyectos/jumpweb-agente`, GitHub `yasmindanailov/…`) va por
  `07076ac`: arnés `pruebas/probar-hooks.sh` 52/52, mutación `pruebas/mutar-frases.py` 9/9. Se actualiza con
  `claude plugin marketplace update jumpweb-agente` + `claude plugin update jumpweb-agente@jumpweb-agente --scope
  project` (el agente puede, `#626`) y pide REINICIAR la sesión. F3: v1.0.0 = `1272cb93`, guarda 8.
  ▶ Tarea propia de F1 aún abierta: podar `DEUDA.md` (277 KB) y `VERIFICACION-E2E-CAJON.md` (186 KB).
- **Análisis estático ENTERO en el gate** (`#625`, `#629`): Larastan nivel 5 (línea base 459) y ESLint del cajón
  (`flat/essential`, línea base nativa de 12 en `eslint-suppressions.json`), las dos con trinquete en
  `StaticAnalysisGateTest`; `scripts/mutar-analisis-estatico.sh` 20/20.
- **F4 CERRADA** (`#633`: la implementó este carril, no el SPA). El token (`specs/token-bearer.md` ✅, `#630`,
  contrato 1.1.0) cerró con ella la Fase 3; el cajón empaquetado, en cinco tandas. Detalle en sus specs.
  · **F5, su principio `[DECIDIDO owner]`** (`#631`, `#632`): la landing consume un MENÚ DE HECHOS por API y
  TODO es opcional; ficha de producto y de zona con descripción e imagen; kit declarativo cuando lo pida una
  segunda instancia; atracciones y widget de ofertas FUERA del panel («oferta» = hecho de precio).
- **v1.2.0 CORTADA el 19-09 y SIN DESPLEGAR** (`f581c791`, etiqueta anotada y en origin; changelog con sus dos
  mitades). Para las instancias **no hay nada que tocar**. El despliegue lo decide el owner y sería el décimo.
  ⚠️ `main` se movió después, así que se despliega desde **`git checkout v1.2.0`** (comprobado en seco: desde
  la etiqueta, la guarda 8 contesta «versión a desplegar: v1.2.0»). `#627`: la app en React Native + Expo.
  ▶ Lo hecho DESPUÉS de esa etiqueta (F5 · el menú de hechos, contrato 1.7.0) irá en la siguiente versión.
- **`#628` · la promo «−20 % online» sigue EN PRODUCCIÓN**: precios × 0,8 y badge como DATO, y el tachado y el
  recuadro por cuatro filas de `settings` (`promo.percent`, `promo.banner.{es,en,fr}`). Receta de fin en
  `ENTORNOS.md` §6; el mecanismo aparcado en `archivo/promo-precio-anterior.md`.
- El enrutador está a pocos bytes de su techo de 12 KB: una fila nueva exige acortar otra.

## Por dónde retomar, en orden

1. **F4 · CERRADA el 19-09** (`specs/cajon-empaquetable.md`): una página que no es del producto monta el cajón,
   lo abre y COMPRA, con la landing del producto idéntica píxel a píxel. El anfitrión mínimo (§4.4) es de F5
   por diseño, no un pendiente. ⚠️ **Lo único que le falta es un ojo humano sobre la COMPRA de la T5**: está
   medida en Chromium (42/42), no vista. Se le enseña con el banco de pruebas local que describe la spec
   (§4.8, última viñeta): un HTML en `public/` con las dos líneas del paquete — **y se BORRA al terminar**,
   o la guarda 9 del despliegue aborta.
   Las cinco tandas y **las seis trampas que costaron una pasada cada una** —el proxy de Alpine, el
   `build:ssr` tras un arnés, el juez que nació mintiendo, el tema del panel que casi se despliega mal, el
   cliente de pruebas que no viaja en el repo y el banco de pruebas que hay que borrar— están en la spec,
   §4.1→§4.8. Al tocar el cajón, se leen de ahí.
2. **LO SIGUIENTE: DESPLEGAR la v1.2.0, y eso lo decide el owner.** La etiqueta está cortada y empujada
   (`v1.2.0` = `f581c791`, anotada, 19-09) con su changelog de dos mitades; la guarda 8 en seco ya contesta
   «versión a desplegar: v1.2.0». **Para las instancias no hay nada que hacer**: sin migraciones, sin claves
   de `.env`, sin ajustes; el contrato de la API sube a 1.2.0 solo añadiendo. ⚠️ Producción, de noche o con
   el parque cerrado (`#594`), y sería el **décimo** despliegue. Cortar la versión y desplegarla son dos
   actos: el segundo es suyo.
   ▶ **`main` ya se movió tras etiquetar** (commits de doc), así que la guarda 8 dice «HEAD no es una
   versión»: se despliega desde **`git checkout v1.2.0`** y al terminar se vuelve con `git checkout main`.
   Comprobado en seco: desde la etiqueta, el pre-vuelo contesta «versión a desplegar: v1.2.0».
3. **F5 EN CURSO · el MENÚ DE HECHOS, recurso a recurso** (`specs/instancia-y-landing-fuera.md` ✅; censo en
   su §1 y las tres decisiones del owner en `#639`: las redes se quedan en el panel, los cuatro campos
   muertos de `zones` se retiran, «cero marca» se lee como código vivo).
   **CINCO PLATOS SERVIDOS** (`#640`→`#644`, contrato **1.7.0**, `scripts/mutar-menu-de-hechos.sh` **24/24**):
   `/site` · `/schedule` y `/schedule/now` · `/rules` · `/legal/documents[/{clave}]` · `/prices`. Sus porqués
   están en la spec §4.1; aquí queda **lo que hace falta para servir el siguiente**.

   ▶ **LO SIGUIENTE: la ficha de producto y de zona** (descripción e imagen, `[DECIDIDO owner]` `#632`), y
   después la prueba social sin avatares (`RGPD-05`). ⚠️ **No se crea un recurso nuevo**: `/catalog/products`
   y `/catalog/zones` YA existen para el cajón y publican el «desde», la chapa, las ventajas y la zona. Lo
   que falta es AÑADIRLES descripción e imagen — un hecho, un sitio. `/catalog/zones` hoy solo da `id`,
   `slug` y `name`, y el censo dice qué de `zones` es hecho (alturas, edades, descripción) y qué es
   presentación (colores, imagen… que `#632` decidió que viaje).

   **RECETA de un plato nuevo**, que es lo que costó aprender:
   - La lista blanca se declara EN el recurso y se lee por `PublicFacts`; `Setting::` a pelo lo prohíbe
     `PublicFactsBoundaryTest`, que barre los 37 recursos de la API (no por nombre: por carpeta).
   - **Lo que la instalación no rellenó NO viaja**, ni como `""`. Y cada objeto se emite con `(object)`: un
     array vacío de PHP sale `[]` y el tipo no puede depender de si alguien rellenó el panel.
   - **Se traduce → `?lang=` obligatorio** y el respaldo lo resuelve el servidor (nunca el mapa de idiomas).
   - **Se cachea según CAMBIE**: lo que toca el panel, 5 min; lo que cambia solo (el «abierto ahora»), 1 min.
   - **Lo apagado en el panel no se sirve**: una norma, una página o un producto desactivados están retirados.
   - Contrato OpenAPI en el mismo commit (sube el MENOR) + caso en `ApiContractTest` + su mutación en el arnés.
   ⚠️ **`ApiContractTest` exige `required` en TODO campo**: lo opcional se declara en `OPTIONAL_BY_DESIGN` con
   su porqué, y ahora también baja a los `items` de las listas.

   **TRAMPAS ya pagadas en este menú** (una pasada cada una): `weekday` es **0 = domingo**, no ISO · ordenar
   con `sortBy([cierre, cierre])` sale AL REVÉS, úsese clave compuesta · el cuerpo de los legales lleva
   marcadores (`:legal_name`) y hay que INTERPOLARLO · `prices` es polimórfica y su `priceable_type` es el
   ALIAS del morphMap (`ticket_type`, no el FQCN): con la clase entera el producto sale sin precios · y
   `prices` tiene columna `currency`, así que la moneda no se escribe a mano.

   **Del censo, medido y sin repetir**: 71 ajustes (5 secretos, 22 hechos, 40 de operación) · sitemap de 11
   URLs · la landing son 3.142 líneas de Blade y ocho rutas sirven la portada · la marca del cliente son 167
   apariciones y **solo UNA viva** (`pjp-salta-record`, una clave de `localStorage`; las otras 166 son citas
   de artboards en comentarios) · `zones` tiene cuatro columnas muertas que `#639` mandó retirar. Sigue
   valiendo lo guardado: un `tokens.json` en la instancia del que salgan `client.css` y el tema de la app.
4. **Deuda del análisis estático**: bajar la línea base de Larastan por familias (`nullsafe.neverNull` es
   mecánico), bajando `FROZEN_ERRORS` en el mismo commit. Los 12 de ESLint los poda quien los arregle.
5. **La promo, cuando el owner la termine** (es suyo el cuándo): subir los precios en el panel (los `from` de
   `audit_logs`: 800, 1000, 1200, 1500, 1800, 1200, 1400, 1800, 2200), quitar el badge y **borrar las cuatro
   filas `promo.*` el mismo día**. Sin desplegar. El sistema de ofertas nace como hecho de precio (`#631`).
6. Después, **F6** (app nativa; hereda del token lo que su spec §2 nombra: Google, alta, dispositivos).
- **Del owner**: las TRES de §7 de la spec de F5 · el fin de la promo · cuándo se despliega la v1.2.0.

## Ficheros de este carril

`CLAUDE.md` · `docs/ESTADO.md` · `docs/00-REFACTOR.md` · `docs/CONVENCIONES.md` · `docs/README.md` ·
`docs/DECISIONES.md` y la estructura de `docs/decisiones/` y `docs/carriles/` · `scripts/docs-check.sh` ·
`.githooks/pre-push` · `scripts/huella-enrutador.py` · `scripts/partir-decisiones.py` · `scripts/deploy.sh` (la
guarda 8) · `scripts/mutar-guarda8.sh` · `CHANGELOG.md` · `phpstan.neon` · `phpstan-baseline.neon` ·
`eslint.config.js` · `eslint-suppressions.json` (la poda quien arregla) · `scripts/mutar-analisis-estatico.sh` ·
`StaticAnalysisGateTest` · el emisor de tokens (`ApiTokenIssuer`, `AuthTokenController`,
`PasswordLogin::verify()`, `AuthTokenTest`, `ApiTokenAbilityTest`, `scripts/mutar-token-bearer.sh`) ·
`Tests\TestCase::be()` · el arranque del cajón (`Http\Sidebar\SidebarBoot`, `SidebarBootController`,
`SidebarBootTest`, `scripts/mutar-cajon-arranque.sh`) · su apertura, su carcasa y el paquete (`resources/js/cajon/**`,
`sidebar/host-bridge.js`, `scripts/sonda-cajon-apertura.mjs`, `scripts/mutar-cajon-apertura.sh`) · la hoja del
paquete (`public/css/cajon.css` GENERADA, `scripts/hoja-del-cajon.py`, `scripts/huella-maquetacion.mjs`,
`HojaDelCajonTest`, `scripts/mutar-hoja-del-cajon.sh`) · **el MENÚ DE HECHOS** (`Platform\Services\PublicFacts`,
`Content\Services\OpeningState`, `app/Http/{Controllers,Resources}/Api/V1/*Facts*` y `LegalDocuments*`,
`PublicFactsBoundaryTest`, `scripts/mutar-menu-de-hechos.sh`, y el bloque `Instalación` de `openapi/v1.yaml`) ·
`Setting::promoPercent()` y `WritesLandingValues::antes()` (`#628`).
**En F4, además y AVISANDO**: `resources/views/components/layout.blade.php`, `resources/js/app.js`,
`resources/js/sidebar/**` (solo lo del empaquetado), `public/css/site.css`, `app/Http/Sidebar/**`.
Todo es COMPARTIDO por naturaleza: un cambio de forma se anuncia en el buzón antes de empujarlo.

## Trampas de este carril

- El harness en modo «auto» ordena preferir Bash a Read/Edit/Write; manda la regla 8 de `CLAUDE.md`.
- **El clasificador «auto» y producción**: deniega escribir hooks, manifiestos y reglas del plugin salvo con las
  reglas `allow` de `#626`; con la orden del owner EN EL TURNO deja pasar escrituras por `ssh` y el `--go`; deniega
  el ensayo en seco con la salida redirigida a fichero («Blind Apply»). No se rodea: se le pide al owner.
- **Un guion de datos contra producción** lleva valor ESPERADO por fila y transacción, se prueba antes en local y
  se corre dos veces para ver que la segunda aborta; escribe por Eloquent y olvida `cta.min_price_cents`
  (`PERF-05`). A tinker por `ssh`: `tail -n +2 guion.php | ssh host 'cd public_html && php artisan tinker
  --execute="$(cat)"'`; `require "php://stdin"` NO funciona.
- **Dos carriles empujando a la vez**: el gate tarda ~3 min y el remoto se mueve; un push puede salir RECHAZADO
  con el gate en verde (18-09, dos veces). `pull --rebase`, **re-medir la suite sobre el árbol fusionado**,
  corregir el trailer con `--amend` y volver a empujar. La etiqueta se lleva `main` ENTERO.
- **El código de salida de una tarea en segundo plano con `; tail` al final es el del `tail`**: los de `pull` y
  `push` se imprimen con `echo "… exit=$?"` y se LEEN.
- **Los cuatro ficheros de doc viven pegados a su techo** (enrutador 12 KB, tracker 16, carril 24, §0 de una
  spec 2). Cada tanda obliga a rascar, y rascar tres veces seguidas es la señal de que algo tiene que MUDARSE
  a su spec —no de que el techo esté mal—: así se fueron las seis trampas del cajón a `cajon-empaquetable.md`
  §4.8 y el historial del menú a `instancia-y-landing-fuera.md` §4.1.
- **Una guarda transversal se da por buena con la SUITE ENTERA, no con sus tests**: la ability `api-v1` dio 24
  rojos en cinco carpetas, todos un 401 de mentira — tras UNA petición a la API, `sanctum` queda como guard por
  defecto del test y `actingAs($u)` planta al titular SIN token, cosa que el guard real no hace (adjunta un
  `TransientToken`). Arreglado en `Tests\TestCase::be()`; producción sigue fallando cerrado. Con tokens REALES,
  `Auth::forgetGuards()` entre peticiones o un token revocado sigue entrando por la caché del guard.
- **Un objeto registrado como store de Alpine tiene DOS caras, y solo una es reactiva**: `Alpine.store('x')`
  devuelve un PROXY; el objeto crudo que se le pasó no avisa a nadie. Una API pública que apunte al crudo
  «funciona» —el estado cambia, no falla nada— y la pantalla no se mueve. Se publica el proxy, se comprueba con
  `window.JumpWeb.cajon === Alpine.store('purchase')` y se demuestra en navegador, no en Node.
- **Una captura solo vale con la pieza ASENTADA, y el limitador de la API puede falsearla**: dos corridas del
  MISMO código daban imágenes distintas (panel a medio entrar, incluso con `reducedMotion`), y tres corridas
  seguidas agotaron el limitador (60/min por IP) hasta que `/entradas` se capturó con «No hay días
  disponibles» — que parecía una diferencia del cambio. Se espera a dos fotogramas con la misma caja + fuentes
  cargadas, se cuentan los 429 como fila de la sonda, y se compara ANTES/DESPUÉS con control de dos corridas.
- **`SHELL` es una variable del propio bash**: llamar así a una ruta en un guion se la cambia a todo lo que se
  lance después. En `mutar-cajon-apertura.sh` se llama `CARCASA`.
- **El gate puede fallar por SATURACIÓN, y su síntoma parece un defecto**: 49 errores de golpe con
  «ProcessTimedOutException … render-sidebar.js exceeded the timeout of 300 seconds» (18-09). No era el
  producto: `--parallel` levanta un proceso por núcleo y cada test de paridad lanza un `node` que renderiza el
  SSR; con la carga a 92 en 20 núcleos, 300 s no bastan. Medido después con la máquina tranquila: los 45 tests
  de paridad en 7,7 s y un render suelto en 0,2 s. Antes de tocar nada se mira `uptime` y se reintenta; la
  salida completa de la suite queda en el `/tmp/tmp.*` que el propio hook nombra.
- **Las guardas de presupuesto del cajón CAMBIAN el diseño, no solo avisan**: la entrada de la landing tiene 26
  kB (`SidebarBundleBudgetTest`) y la raíz del cajón 40 líneas sin conocer los pasos del embudo
  (`SidebarComponentBudgetTest`). Las dos me obligaron a rehacer la T3b: lo que solo usa una página ajena se
  trae con `import()`, y la regla de una señal nueva vive en `section.js` con el hecho derivado en el store.
- **ESLint sobre `resources/js/cajon` cazó un defecto de verdad** (18-09): al reordenar `bootSpaEngine()`,
  `host` quedó fuera del alcance de su `catch` — un fallo del chunk habría lanzado un `ReferenceError` dentro
  del manejador de errores y el velo habría girado para siempre. Una variable que usa un `catch` se declara
  FUERA del `try`.
- **Mover código que unas guardas leen como TEXTO**: se mueve TAL CUAL (las cadenas viajan), se re-apunta cada
  guarda al fichero nuevo y se MUTA allí. Buscar antes con `grep -rln "js/app.js" tests`: salieron once.
- **El tipo que Sanctum declara para `currentAccessToken()` miente con cookie** (dice `PersonalAccessToken`,
  llega `TransientToken`): el tipo real es `HasAbilities`, con `@var`; no es una entrada más de la línea base.
- **Un arnés que restaura un `.vue` tocándole la fecha deja el bundle SSR «rancio»**: 36 rojos de
  `SidebarDomContractTest`. `npm run build:ssr` y re-medir (el `pre-push` lo reconstruye solo).
- **`npm install` PODA `playwright-core`** (va con `--no-save`): reponerlo (`/sonda` §1). Y el ojo del navegador
  se pierde al recrear el contenedor (~2 min montarlo; `PLAYWRIGHT_BROWSERS_PATH=/home/sail/pw-browsers`).
- **ESLint o cualquier herramienta se MIDE fuera del árbol** si el gate está corriendo: instalación desechable
  en el `/tmp` del contenedor y `--config` apuntándola, con el cwd en el repo.
- **Un patrón nuevo del mapa de frases se prueba contra los mensajes REALES del owner** (mapa de `git show
  HEAD:` contra el nuevo sobre los `.jsonl` de `~/.claude/projects/-home-yasmi-proyectos-JumpWeb/`, filtrando
  `type == user` con texto): así apareció su cierre habitual, «vamos a cerrar sesion». El resumen de
  compactación NO pasa por `UserPromptSubmit`.
- **Comparar manifiestos de Vite enteros da un falso «hay algo que desplegar»**: `app.css` cambia de hash con el
  árbol (Tailwind escanea docs y mockups). Se compara entrada a entrada.
- **Taquilla cobra de la misma tabla `prices` que la web**, y el pedido manual y ocho servicios del núcleo llaman
  a `priceCents()`: una rebaja «solo online» como dato es imposible y un descuento por canal es `CRITICAL_RE`.
- `rm -rf` está en el deny del repo y un comando compuesto que lo lleve se deniega entero: `git rm -r` para lo
  versionado, carpeta nueva para lo demás. `claude plugin details` no acepta `--plugin-dir`.
- **«The command 'docker' could not be found» es Docker Desktop APAGADO**: se arranca desde WSL con
  `"/mnt/c/Program Files/Docker/Docker/Docker Desktop.exe"` en segundo plano y `until docker info`.
- Una etiqueta no pasa por el gate (`pre-push` solo mira `refs/heads/main`): `/release` exige que el commit ya
  esté en `origin/main`; y un test sobre una «casi versión» tiene que EMPUJARLA antes de medir.
- `git show HEAD~N:docs/DECISIONES.md` (antes de F1) es el registro único de antes de la partición.

## Buzón

### Para el carril del SPA (emisor: plataforma, 2026-09-19)
- ❗❗ **F4 CERRADA: el cajón ya es un PAQUETE y varias cosas tuyas cambiaron de sitio.** Entré en tus ficheros
  por orden del owner (`#633`) y solo para el empaquetado. **Dónde vive ahora cada cosa** está escrito en
  `specs/cajon-empaquetable.md` §0 («dónde va cada cosa») y §4.8 (las seis trampas); lo imprescindible:
  · un **rótulo nuevo del cajón** va en `Http\Sidebar\SidebarBoot`, NO en `components/layout.blade.php`
    (`SidebarBootTest` pone rojo un `'messages' =>` en el layout); si lo necesita la CARCASA, además en el
    presupuesto de `SidebarMountTest`;
  · **abrir y cerrar** vive en `resources/js/cajon/controller.js` y la **carcasa** en `cajon/shell.js` (el
    layout perdió sus ocho atributos de Alpine); el motor no nombra a Alpine: publica por
    `sidebar/host-bridge.js`;
  · lo que **solo use una página ajena** va en `cajon/standalone.js`, nunca en la entrada
    (`SidebarBundleBudgetTest` vigila los 26 kB que descarga toda página pública);
  · la raíz del cajón **no puede conocer los pasos del embudo**: una señal nueva hacia fuera va en
    `section.js` y su hecho lo deriva el store;
  · el **estilo del cajón** se toca en `site.css` y se REGENERA `public/css/cajon.css`
    (`python3 scripts/hoja-del-cajon.py --aplicar`); `HojaDelCajonTest` se pone rojo si se quedan separados.
  Medido: la landing idéntica píxel a píxel en las cinco tandas; sonda 42/42; juez de maquetación en 0.
- ❗ **Si tocas un `.vue`, `npm run build:ssr` antes de la suite**: si no, `SidebarDomContractTest` saca 36
  rojos que no son de tu código (el SSR se queda viejo). Le pasa a los arneses de mutación también.

### Para TODOS los carriles que tocan la API (emisor: plataforma, 2026-09-19)
- ❗ **Desde `#630`, toda ruta con `auth:sanctum` exige además la ability `api-v1`** (`ApiTokenAbilityTest`): una
  ruta autenticada nueva va DENTRO del grupo autenticado de `routes/api.php` o con `->middleware(['auth:sanctum',
  $tokenAbility])`. En un test, `Sanctum::actingAs($u)` SIN abilities da **403** → se pasa
  `[ApiTokenIssuer::ABILITY]`; `actingAs($u)` no cambia. Toqué por eso 9 líneas de `OrderGuestMinorsTest` y
  `MeWaiverGuestMinorTest`, y lo COMPARTIDO `tests/TestCase.php` (un `be()` que adjunta el `TransientToken` que
  adjunta el guard real).
- ❗❗ **El contrato va por 1.7.0 y `ApiContractTest` aprieta más que antes** (`#640`→`#644`, el menú de hechos
  de F5). Dos cosas que te van a tocar si añades un endpoint: **(1)** todo campo de una respuesta tiene que
  estar en `required`, y lo que sea opcional de verdad se declara en `OPTIONAL_BY_DESIGN` **con su porqué**;
  **(2)** desde el 19-09 la comprobación **baja también a los `items` de las listas** —antes un `type: array`
  se iba sin mirar el objeto de dentro, así que un campo de más en cada elemento pasaba el contrato entero—.
  Y si tu endpoint lee `settings`, no lo hagas a mano: `PublicFactsBoundaryTest` barre los 37 recursos de la
  API y te lo prohíbe (la tabla tiene `redsys_secret_key` a dos filas de `contact.email`).

### Para el carril de la web (emisor: plataforma, 2026-09-18)
- **Toqué lo tuyo por orden del owner, como chapuza declarada (`#628`)**: `rate-rail.blade.php`,
  `pages/pricing.blade.php`, tres reglas de `landing.css`, `lang/*/landing.php` (`rates.was`) y `RateCards`/
  `RateTable`. Sin los ajustes `promo.*` no cambia ni un byte del HTML. En producción desde el 18-09.
- ⚠️ **Defecto tuyo previo, medido y sin tocar**: en `/precios` a 390 px la cifra «9,60 €» ya se partía en dos
  renglones (celda de 84 px) antes de este cambio.
- **De `#631`/`#632`**: el widget flotante de ofertas y las atracciones SALEN del panel (trabajo de F5, no de hoy).

### Atendido
- **SPA, 17-09** («la T3 pide despliegue»; «plugin instalado, 1 de 6»; «`package.json` libre»): atendido el 18-09.
- **SPA, 19-09**: dio por atendidos mis tres mensajes del 18-09 (despliegue de v1.1.0, ESLint en el gate y el
  plugin) y **arregló en `#707` el defecto del `addBtn`** que le pasé; retirados de mi buzón. Queda suyo
  repasar el `§0` de `sidebar-spa.md`. Su T3 del justificante SÍ está en producción desde v1.1.0: la casilla
  del tracker que pedía desplegarla mentía y la corregí el 19-09.
