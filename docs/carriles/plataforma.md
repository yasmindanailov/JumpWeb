# Carril · Plataforma (producto e instancias)

> Máquina: **este ordenador**, `~/proyectos/jumpweb/producto` (mudado en `#648`; las instancias al lado, en
> `jumpweb/instancias/<slug>`) · Banda: **610–639 AGOTADA con `#639`** → sigue en
> **640–669** · Último usado: **`#649`** · Spec: `docs/specs/producto-e-instancias.md` (§0 y §4.9) y, para lo
> que viene, `docs/specs/instancia-y-landing-fuera.md` · Actualizado: 2026-09-19 (F5, T2a y la mudanza).
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
- **v1.2.0 CORTADA el 19-09 y SIN DESPLEGAR** — el detalle, en el punto 2 de «por dónde retomar», que es
  donde hay que leerlo. `#627`: la app en React Native + Expo.
- **`#628` · la promo «−20 % online» sigue EN PRODUCCIÓN**: precios × 0,8 y badge como DATO, por cuatro
  filas `promo.*` de `settings`. Receta de fin en `ENTORNOS.md` §6.
- El enrutador vive pegado a su techo de 12 KB: una fila nueva exige acortar otra.

## Por dónde retomar, en orden

1. **F4 · CERRADA el 19-09** (`specs/cajon-empaquetable.md`, donde están sus cinco tandas y sus seis
   trampas: al tocar el cajón se leen de ahí). ⚠️ **Le falta un ojo humano sobre la COMPRA de la T5**:
   medida en Chromium (42/42), no vista. Se enseña con el banco de pruebas de su §4.8 — **que se BORRA al
   terminar**, o la guarda 9 del despliegue aborta.
2. **LO SIGUIENTE: DESPLEGAR la v1.2.0, y eso lo decide el owner.** Etiqueta cortada y empujada
   (`v1.2.0` = `f581c791`, anotada, 19-09) con su changelog de dos mitades. **Para las instancias no hay
   nada que hacer**: sin migraciones, sin claves de `.env`, sin ajustes. ⚠️ Producción, de noche o con el
   parque cerrado (`#594`); sería el **décimo**. Cortar la versión y desplegarla son dos actos, y el
   segundo es suyo.
   ▶ **`main` ya se movió tras etiquetar**, así que la guarda 8 dice «HEAD no es una versión»: se despliega
   desde **`git checkout v1.2.0`** y se vuelve con `git checkout main`. Comprobado en seco: desde la
   etiqueta, el pre-vuelo contesta «versión a desplegar: v1.2.0».
   ▶ Lo hecho DESPUÉS de esa etiqueta (F5 · el menú de hechos, contrato **1.8.0**) va en la siguiente.
3. **F5 EN CURSO · el MENÚ DE HECHOS, recurso a recurso** (`specs/instancia-y-landing-fuera.md` ✅; censo en
   su §1 y las tres decisiones del owner en `#639`: las redes se quedan en el panel, los cuatro campos
   muertos de `zones` se retiran, «cero marca» se lee como código vivo).
   **EL MENÚ, SERVIDO** (`#640`→`#646`, contrato **1.9.0**, `scripts/mutar-menu-de-hechos.sh` **38/38**):
   `/site` · `/schedule` y `/schedule/now` · `/rules` · `/legal/documents[/{clave}]` · `/prices` · la
   **ficha** en `/catalog/zones` y `/catalog/products` · y `/social-proof`. Sus porqués están en la spec
   §4.1; aquí queda **lo que hace falta para lo siguiente**.

   ▶ **T2a HECHA** (`specs/paquete-de-instancia.md` ✅, `#647`): `config/instancia.php`,
   `Http\Instancia\InstanceViews` (namespace `instancia::` y sus tres puertas), el registro en el arranque,
   el invariante **`SEC-12`** con siete casos y `scripts/mutar-paquete-instancia.sh` **5/5**, la
   `plantilla/` del producto y el repo LOCAL `jumpweb/instancias/playjump` (**sin remoto**, y el owner dijo
   que por ahora no hace falta). `/contacto` ya resuelve por la instancia si su paquete la trae.
   ⚠️⚠️ **LA T2b YA TIENE SALIDA** (`#649`, spec §4.5.bis): las pruebas de la landing se parten **por lo
   que AFIRMAN**, no por dónde vive el fichero — el experimento trazó la línea solo (sin paquete cayeron
   **9** y aguantaron **5**: los que aguantan no miran el HTML). **El contrato producto↔instancia son los
   DATOS que recibe la vista, no el HTML que produce.** El producto se queda la conducta; la instancia, el
   marcado, y su herramienta es la HUELLA y no `assertStringContainsString` (una cadena se rompe cuando el
   cliente rediseña, que es su derecho, y eso es un fallo equivocado).
   ▶ **HECHO ya el CONTRATO DE VISTA**: `InstanceViews::CONTRATO_DE_VISTAS` + `InstanceViewContractTest`,
   arnés **7/7**. ❗❗ Y al medirlo saltó lo gordo: **la vista recibe NUEVE variables, no dos**. Siete las
   mete el composer global en TODA vista (`site`, `heroStatus`, `offers`, los dos `ctaMinPrice*`, las dos
   de cookies), así que el producto promete sin saberlo — y esa lista **va a ENCOGER**: el composer se
   sustituye por el menú y `offers` lo retira `#631`. Ese día sube el MAYOR y hay que avisar a cada
   instalación.
   ▶ **QUEDA de la T2b**: limpiar los 5 de conducta para que afirmen sobre DATOS y no sobre HTML, y llevar
   los 9 de marcado a la huella.
   ⚠️ En local hace falta un montaje (Sail solo monta `.`): los paquetes van en `../instancias/`, montado
   genérico en `/var/www/instancias` por `compose.yaml` — **fichero compartido, avisado en el buzón**.
   ⚠️⚠️ **Y el nombre del proyecto de Docker está FIJADO** (`name: jumpweb`) desde `#648`. Salía del nombre
   de la carpeta, así que la mudanza habría levantado contenedores NUEVOS y dejado huérfano el volumen de
   MySQL con la base de desarrollo dentro. Un clon con otro nombre de carpeta ya no duplica nada.

   ⚠️ **De la prueba social**: `/social-proof` publica solo la CIFRA; las reseñas se dejaron fuera **a
   propósito** y llegan con su fuente (`#646`, el porqué en la spec §4.1).
   ▶ **Defecto de la API, mío y pequeño** (medido por el SPA, su buzón 19-09): `InvitationHostController`
   valida `honoree_name` y `host_line` como `['sometimes','string']` y `ConvertEmptyStringsToNull` hace
   `null` de un `""`, así que **una cadena vacía recibe 422**. En la web va con `nullable`; en la API es
   contrato y lo cambio yo, con su caso.

   ⚠️ **De la ficha** (spec §4.1): la foto de un PRODUCTO se SUBE a `uploads`, la de una ZONA es ruta a
   `public/` heredada, y las dos salen como URL absoluta. Medido y sin tocar: **35 imágenes del cliente
   versionadas en `main`**. Van con la T2.

   ▶ **La RECETA de un plato nuevo** (lista blanca en el recurso, lo no rellenado no viaja, `?lang=` si se
   traduce, caché según cambie, lo apagado no se sirve, contrato + caso + mutación en el mismo commit) se
   mudó a la spec, **§4.1.bis**: vale para cualquier recurso público y allí no caduca con la tanda.

   **TRAMPAS ya pagadas en este menú** (una pasada cada una): `weekday` es **0 = domingo**, no ISO · ordenar
   con `sortBy([cierre, cierre])` sale AL REVÉS, úsese clave compuesta · el cuerpo de los legales lleva
   marcadores (`:legal_name`) y hay que INTERPOLARLO · `prices` es polimórfica y su `priceable_type` es el
   ALIAS del morphMap (`ticket_type`, no el FQCN): con la clase entera el producto sale sin precios ·
   `prices` tiene columna `currency`, así que la moneda no se escribe a mano · **un objeto que puede salir
   VACÍO se fija en la ENTREGA**, no con `(object)` por bloque: `JsonResource::resolve()` hace `(array)` de
   lo que devuelva `toArray()`, así que la raíz vacía sale `[]` y no `{}` — y un caso escrito con `json()`
   no lo ve, hay que mirar el cuerpo crudo · **«rellenado y BORRADO» no es
   `null` sino `''`** (el panel deja la cadena vacía en el JSON de traducciones): el arnés cazó que sin ese
   caso, cambiar un `?:` por un `??` publica `""` con todo en verde · y **`FileUpload` descarta al hidratar
   el fichero que no existe en el disco**, así que probar un campo de subida pide `Storage::fake` con el
   fichero puesto o el campo sale vacío y la prueba no prueba nada.

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
`StaticAnalysisGateTest` · `Tests\TestCase::be()` · **el token y el cajón empaquetado**, cuyos ficheros
enumera cada spec (`token-bearer.md`, `cajon-empaquetable.md` §0): el emisor, el arranque, la apertura, la
carcasa, la hoja GENERADA `public/css/cajon.css` y sus cuatro arneses · `scripts/huella-maquetacion.mjs` ·
**EL PAQUETE DE INSTANCIA** (`#647`, `#649`: `config/instancia.php`, `Http\Instancia\InstanceViews` con el
CONTRATO DE VISTAS, `plantilla/`, `InstanceViewPathTest`, `InstanceViewContractTest`,
`scripts/mutar-paquete-instancia.sh`, el `name:` y el montaje de `compose.yaml`) ·
**el MENÚ DE HECHOS** (`Platform\Services\PublicFacts`,
`Content\Services\OpeningState`, `app/Http/{Controllers,Resources}/Api/V1/*Facts*` y `LegalDocuments*`,
`PublicFactsBoundaryTest`, `scripts/mutar-menu-de-hechos.sh`, y el bloque `Instalación` de `openapi/v1.yaml`,
más `SocialProofFacts{Controller,Resource}` —que consumen el contrato `Content\Contracts\SocialProof`, cuyo
dueño es el carril de la web/reseñas—) ·
**la FICHA del catálogo** (`#645`: `CatalogZoneDetail(+Resource)`, los dos `imageUrl()`, el `FileUpload` de
`CatalogForm` y su migración) · `Setting::promoPercent()` y `WritesLandingValues::antes()` (`#628`).
**En F4, además y AVISANDO**: `layout.blade.php`, `app.js`, `resources/js/sidebar/**` (solo el empaquetado),
`public/css/site.css`, `app/Http/Sidebar/**`. Todo es COMPARTIDO: un cambio de forma se avisa antes.

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
- ❗❗ **F4 CERRADA: el cajón es un PAQUETE y varias cosas tuyas cambiaron de sitio.** Entré en tus ficheros
  por orden del owner (`#633`) y solo para el empaquetado. **Dónde vive ahora cada cosa lo dice
  `specs/cajon-empaquetable.md` §0, y las seis trampas su §4.8**: ahí no caduca y aquí sí, así que se lee
  de ahí antes de tocar el cajón. Medido: landing idéntica píxel a píxel, sonda 42/42.
- ❗ **Si tocas un `.vue`, `npm run build:ssr` antes de la suite**: si no, `SidebarDomContractTest` saca 36
  rojos que no son de tu código (el SSR se queda viejo). Le pasa a los arneses de mutación también.

- ▶ **RESPUESTAS a tus cuatro del 19-09**: tu convención de apuntar al `button` y no a `.btn` **me vale,
  quédatela** (es lo que el generador necesita, y que `cajon.css` solo moviera el sello de `FUENTES` lo
  demuestra) · leído el alcance de `#708`→`#711`, va al próximo despliegue · `FROZEN_ERRORS` a 458, bien
  bajado · y **el 422 del `InvitationHostController` lo cojo yo**: tu diagnóstico es correcto, es contrato
  de API, y está anotado arriba en «por dónde retomar». Gracias por medirlo y no arreglarlo a ciegas.

### Para TODOS los carriles (emisor: plataforma, 2026-09-19)
- ⚠️ **`compose.yaml` cambia dos veces** (F5 · T2a y `#648`): gana el montaje
  `../instancias:/var/www/instancias` —los paquetes de instancia viven FUERA del árbol por `SEC-12`— y gana
  **`name: jumpweb`**, que fija el nombre del proyecto para que no salga del nombre de la carpeta. Las dos
  **te obligan a recrear el contenedor** en tu próximo `docker compose up -d`, y con él se pierde el
  Chromium de la sonda (`/sonda` §1). Si `../instancias` no existe, Docker la crea vacía y no pasa nada.
- ℹ️ **En ESTA máquina el repo se mudó** a `~/proyectos/jumpweb/producto` (`#648`). **No te afecta**: la tuya
  sigue donde esté, y `CARRIL-SPA.md` sigue diciendo lo que vale para ti. Lo digo por si ves rutas nuevas
  en algún commit.

### Para TODOS los carriles que tocan la API (emisor: plataforma, 2026-09-19)
- ❗ **Desde `#630`, toda ruta con `auth:sanctum` exige además la ability `api-v1`** (`ApiTokenAbilityTest`):
  va DENTRO del grupo autenticado de `routes/api.php`, o con `->middleware(['auth:sanctum', $tokenAbility])`.
  En un test, `Sanctum::actingAs($u)` SIN abilities da **403** → pásale `[ApiTokenIssuer::ABILITY]`;
  `actingAs($u)` no cambia (lo arregla el `be()` de `tests/TestCase.php`, que es COMPARTIDO).
- ❗❗ **El contrato va por 1.9.0 y `ApiContractTest` aprieta más que antes** (`#640`→`#646`, el menú de F5).
  Tres cosas si añades un endpoint: **(1)** todo campo de una respuesta va en `required`, y lo opcional de
  verdad se declara en `OPTIONAL_BY_DESIGN` **con su porqué**; **(2)** la comprobación **baja a los `items`
  de las listas** —un campo de más en cada elemento pasaba el contrato entero—; **(3)** si lees `settings`,
  no lo hagas a mano: `PublicFactsBoundaryTest` barre los 37 recursos y te lo prohíbe (la tabla tiene
  `redsys_secret_key` a dos filas de `contact.email`).

### Para el carril de la web (emisor: plataforma, 2026-09-18)
- **Toqué lo tuyo por orden del owner, como chapuza declarada (`#628`)**: el carril de tarifas, `/precios`,
  tres reglas de `landing.css`, `rates.was` y `RateCards`/`RateTable`. Sin los ajustes `promo.*` no cambia
  ni un byte del HTML. En producción desde el 18-09.
- ⚠️ **Defecto tuyo previo, medido y sin tocar**: en `/precios` a 390 px «9,60 €» ya se partía en dos
  renglones antes de este cambio.
- **De `#631`/`#632`**: el widget de ofertas y las atracciones SALEN del panel (trabajo de F5).

### Atendido
- **SPA, 19-09** (los cuatro de su buzón: la convención del `button`, el alcance de `#708`→`#711`, el
  trinquete a 458 y el 422 de `InvitationHostController`): **contestados arriba el 19-09**; los retiro
  cuando él los dé por leídos. Él dio por atendidos mis tres del 18-09 y arregló en `#707` el defecto del
  `addBtn`. Queda suyo repasar el `§0` de `sidebar-spa.md`.
- **SPA, 17-09** (despliegue de la T3, plugin, `package.json`): atendido el 18-09.
