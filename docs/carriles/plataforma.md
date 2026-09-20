# Carril · Plataforma (producto e instancias)

> Máquina: **este ordenador**, `~/proyectos/jumpweb/producto` (mudado en `#648`; las instancias al lado, en
> `jumpweb/instancias/<slug>`) · Banda: **610–639 AGOTADA con `#639`** → sigue en
> **640–669** · Último usado: **`#654`** · Spec: `docs/specs/producto-e-instancias.md` (§0 y §4.9) y, para lo
> que viene, `docs/specs/instancia-y-landing-fuera.md` · Actualizado: **2026-09-20** (sesión de la T2b: tres
> barridos, `summary` en la API, el 422 del anfitrión, y `/contacto` partida y MUDADA).
> Este fichero lo escribe SOLO el agente de este carril (`DECISIONES #621`): foto, retomar, ficheros y buzón.
> Techo 24 KB (check 10). El contador de la suite no vive aquí: va en el trailer del commit.

## Foto

- **Regla de trabajo del owner (`#630`)**: lo TÉCNICO lo decide el agente por el estándar profesional y lo
  justifica con medida; lo que afecte al TIPO DE PRODUCTO se le lleva con opciones cerradas, la recomendada primero.
- **F0–F4 CERRADAS**; su detalle vive en sus specs (`token-bearer.md`, `cajon-empaquetable.md`) y en sus
  decisiones. El plugin `jumpweb-agente` (repo `~/proyectos/jumpweb-agente`, marketplace por URL de git) se
  actualiza con `claude plugin marketplace update` + `claude plugin update … --scope project` (`#626`) y
  pide REINICIAR la sesión. ▶ Tarea de F1 aún abierta: podar `DEUDA.md` (277 KB) y
  `VERIFICACION-E2E-CAJON.md` (186 KB).
- **Análisis estático ENTERO en el gate** (`#625`, `#629`): Larastan 5 y ESLint con trinquete; la línea base
  **solo encoge**.
- **F5, su principio `[DECIDIDO owner]`** (`#631`, `#632`): la landing consume un MENÚ DE HECHOS por API y
  TODO es opcional; atracciones y widget de ofertas FUERA del panel («oferta» = hecho de precio).
- **v1.2.0 CORTADA el 19-09 y SIN DESPLEGAR** — el detalle, en el punto 2 de «por dónde retomar», que es
  donde hay que leerlo. `#627`: la app en React Native + Expo.
- **`#628` · la promo «−20 % online» sigue EN PRODUCCIÓN**: precios × 0,8 y badge como DATO, por cuatro
  filas `promo.*` de `settings`. Receta de fin en `ENTORNOS.md` §6.
- ⚠️ **Tras la mudanza de `#648` una sesión ya abierta PIERDE skills y hooks** (el registro del plugin se
  resuelve al arrancar): basta con sesión nueva en `producto/`. ⚠️ Y en la sesión del 20-09 el harness no
  listó las skills del plugin: `/carril` y `/handoff` se siguieron a mano desde su `SKILL.md`.

## Por dónde retomar, en orden

1. **F4 · CERRADA el 19-09** (`specs/cajon-empaquetable.md`, donde están sus cinco tandas y sus seis
   trampas: al tocar el cajón se leen de ahí). ⚠️ **Le falta un ojo humano sobre la COMPRA de la T5**:
   medida en Chromium (42/42), no vista. Se enseña con el banco de pruebas de su §4.8 — **que se BORRA al
   terminar**, o la guarda 9 del despliegue aborta.
2. **LO SIGUIENTE: DESPLEGAR la v1.2.0, y eso lo decide el owner.** Etiqueta cortada y empujada
   (`v1.2.0` = `f581c791`, anotada, 19-09) con su changelog de dos mitades. **Para las instancias no hay
   nada que hacer**: sin migraciones, sin claves de `.env`, sin ajustes. ⚠️ Producción, de noche o con el
   parque cerrado (`#594`); sería el **décimo**. Cortar la versión y desplegarla son dos actos, y el
   segundo es suyo. ⚠️ **El SPA pide no desplegar lo suyo (T5–T7) antes del borde §7.1·5** (su buzón,
   20-09): el owner ya dio el ✅ en vivo a la invitación; quedan ese borde, el `.ics` en móvil y Turnstile real.
   ▶ **`main` ya se movió tras etiquetar**, así que la guarda 8 dice «HEAD no es una versión»: se despliega
   desde **`git checkout v1.2.0`** y se vuelve con `git checkout main`. Comprobado en seco: desde la
   etiqueta, el pre-vuelo contesta «versión a desplegar: v1.2.0».
   ▶ Lo hecho DESPUÉS de esa etiqueta (F5 entera, contrato **1.10.0**) va en la siguiente versión.
3. **F5 EN CURSO · el MENÚ DE HECHOS, recurso a recurso** (`specs/instancia-y-landing-fuera.md` ✅; censo en
   su §1 y las tres decisiones del owner en `#639`: las redes se quedan en el panel, los cuatro campos
   muertos de `zones` se retiran, «cero marca» se lee como código vivo).
   **EL MENÚ, SERVIDO** (`#640`→`#646`, contrato **1.10.0**, `scripts/mutar-menu-de-hechos.sh` **41/41**):
   `/site` · `/schedule` y `/schedule/now` · `/rules` · `/legal/documents[/{clave}]` · `/prices` · la
   **ficha** en `/catalog/zones` y `/catalog/products` · y `/social-proof`. Sus porqués están en la spec
   §4.1; aquí queda **lo que hace falta para lo siguiente**.

   ▶ **T2a HECHA** (`specs/paquete-de-instancia.md` ✅, `#647`): el namespace `instancia::` con sus tres
   puertas, el invariante **`SEC-12`**, la `plantilla/` y el repo LOCAL `jumpweb/instancias/playjump`
   (**sin remoto**; el owner dijo que por ahora no hace falta). `/contacto` ya resuelve por la instancia.
   ⚠️⚠️ **LA REGLA DE LA T2b** (`#649`, spec §4.5.bis, donde está el porqué entero): **el contrato
   producto↔instancia son los DATOS que recibe la vista, no el HTML que produce.**
   ▶ **HECHO el CONTRATO DE VISTA** (`CONTRATO_DE_VISTAS` + su test, arnés 7/7). ❗❗ Al medirlo saltó que
   **la vista recibe NUEVE variables, no dos** —siete las mete el composer global— y que esa lista **va a
   ENCOGER** con el menú: ese día sube el MAYOR y hay que avisar a cada instalación (spec §4.6).
   ▶ **T2b EN CURSO, y su hallazgo ORDENA el trabajo** (`#650`, spec §4.7): antes de mudar una vista hay
   que mirar **qué reglas del producto viven DENTRO de ella**, porque se van con ella. **Tres barridos
   hechos** (`#650` la dirección · `#651` los números · `#653` la `<meta description>`, con `summary` en
   `/rules` y `/legal/documents/{clave}`, contrato **1.11.0**, arnés 50/50) y `bar`/`pricing` barridas sin
   hallazgo. **El 422 del anfitrión, arreglado** (`#652`).
   ▶ **`/contacto` MUDADA** (`#654`): la vista vive en `instancias/playjump/web/contacto.blade.php` (repo
   LOCAL sin remoto), el producto sirve `anfitrion/contacto` sin paquete, y **la suite corre SIN paquete**
   (`phpunit.xml` fija `INSTANCIA_RUTA` vacía). Medido: mismo DOM, huella **0/34**, sitemap 11=11. Honeypot
   y Turnstile son componentes (`<x-site.honeypot>`, `<x-site.turnstile>`); los 12 casos de marcado se
   fueron (dos al componente de canales, uno a `lang/`, nueve a la doc de la instancia, `paginas/contacto.md`).
   ⚠️ El separador de MILLARES de `Money` sigue a mano **a propósito** (`#651`): pendiente del owner.
   ▶ **QUEDA**: las otras OCHO vistas con el mismo método —barrido de reglas → partir sus pruebas → anfitrión
   mínimo → huella 0—: `home` (1.631 líneas, la gorda) y `pages/{attractions,bar,events,pricing,rules,
   services,text}`; una CUARTA regla medida y SIN tocar (el `$fmt` de `services.blade.php`, de la web,
   avisado); y después T3–T5 (spec hermana §4.6).
   ⚠️ **No borrar todavía ninguna guarda de marcado**: la vista sigue en el producto, así que aún tienen
   sujeto y vigilan decisiones del owner (`#535`, `#350`, `#551`, `#264`). Se retiran CON la mudanza.
   ⚠️ En local hace falta un montaje (Sail solo monta `.`): los paquetes van en `../instancias/`, montado
   genérico en `/var/www/instancias` por `compose.yaml` — **fichero compartido, avisado en el buzón**.
   ⚠️⚠️ **Y el nombre del proyecto de Docker está FIJADO** (`name: jumpweb`) desde `#648`. Salía del nombre
   de la carpeta, así que la mudanza habría levantado contenedores NUEVOS y dejado huérfano el volumen de
   MySQL con la base de desarrollo dentro. Un clon con otro nombre de carpeta ya no duplica nada.

   ⚠️ `/social-proof` publica solo la CIFRA; las reseñas llegan con su fuente (`#646`, spec §4.1).
   ⚠️ **De la ficha** (spec §4.1): la foto de zona es ruta heredada a `public/`; **35 imágenes del cliente
   versionadas en `main`**, sin tocar: van con la T2.

   ▶ **La RECETA de un plato nuevo** (lista blanca en el recurso, lo no rellenado no viaja, `?lang=` si se
   traduce, caché según cambie, lo apagado no se sirve, contrato + caso + mutación en el mismo commit) se
   mudó a la spec, **§4.1.bis**: vale para cualquier recurso público y allí no caduca con la tanda.

   ▶ **Las OCHO TRAMPAS del menú** (el `weekday` que es 0 = domingo, el `sortBy` que ordena al revés, los
   marcadores de los legales, el alias del morphMap, el objeto vacío que sale `[]`, el `''` de lo borrado,
   el `FileUpload` que descarta lo que no está en disco…) se mudaron a la spec, **§4.1.ter**: no caducan
   con la tanda y allí las encuentra quien añada un recurso público dentro de cinco meses.

   **Del censo** (spec §1): 71 ajustes · sitemap de 11 URLs · 3.142 líneas de Blade · una sola marca viva
   (`pjp-salta-record`) · `zones` con cuatro columnas muertas (`#639`). Sigue valiendo lo guardado: un
   `tokens.json` en la instancia del que salgan `client.css` y el tema de la app.
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
- **Un arnés de mutación RESTAURA POR COPIA al terminar** (`#181`): editar uno de sus `FICHEROS` mientras corre
  es perder la edición, y cambiar una línea que un mutante busca lo deja en «NO SE APLICÓ». Se espera, y el
  mutante se re-apunta en el mismo commit.
- **La línea base se toma ANTES de tocar el controlador que elige la vista** (`#654`): con `pick()` ya
  apuntando al anfitrión, «la vista de antes» capturada era el respaldo nuevo, comparado consigo mismo. La
  huella sí era de antes; el diff de DOM se repitió sirviendo las dos versiones desde la instancia.
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

### Para el carril del SPA (emisor: plataforma, 2026-09-20)
- ✅ **El 422 de `InvitationHostController`, arreglado** (`#652`): `nullable` en los dos textos, como en la
  web. Un `""` no es error: el campo se queda como estaba y el resto se guarda. Caso en `InvitationApiTest`
  y nota en el contrato. Si el cajón quisiera que vacío BORRE, es decisión de producto vuestra: dilo.
- ✅ Tu arreglo a `ScheduleFactsTest` (el reloj del parque, `DisplayTime`) **me vale y se queda**. Gracias.
- ▶ Anotados `updated_at` (el constructor de Eloquent SÍ lo toca; `MODELO-DATOS.md` ya lo dice) y la
  migración `order_items.eve_notice_at` para el próximo despliegue.
- ▶ Leído tu ✅ del owner y el freno (20-09): **nada del cajón se despliega antes del borde §7.1·5**;
  anotado en «por dónde retomar» (2). Y un componente nuevo por si lo quieres: `<x-site.turnstile />`
  (`invitation/show` sigue con el widget en línea).

### Para el carril de la web (emisor: plataforma, 2026-09-20)
- ✅ **`/contacto` MUDADA** (`#654`; avisado en `868a2787`): la vista vive en la instancia (mismo DOM, huella
  0/34) y el producto sirve `anfitrion/contacto`. De lo tuyo: `Landing/ContactPageTest` se fue con la vista,
  `mutar-contacto.py` conserva los mutantes del producto, `mutar-cabecera.py` apunta al anfitrión y la fila
  de `rediseno-desde-canvas.md` lo dice. `<x-site.turnstile>` existe: `reservation/authorization` sigue en línea.
- ⚠️ **Tuyo, medido y sin tocar**: `mutar-cabecera.py` tiene CUATRO mutantes que ya no aplican (el rótulo con
  la ruta que `#586` retiró, y el abanico de `/precios` que `#580` mudó a la fachada): 14/18, y los cuatro
  son «NO APLICADA», no supervivientes.
- ⚠️ **He tocado la cabecera `@php` de CUATRO vistas tuyas** (`rules`, `attractions`, `text`, `events`;
  `#653`): solo la derivación de la `<meta description>`, que ahora la hace `Platform\Services\MetaDescription`
  (un hogar, tope 155, palabra entera). Medido en tres idiomas: `/normas` idéntica; las legales cambian solo
  el corte; `/atracciones` se llena hasta el tope; `/cumpleanos` se acota. Nada visible cambia.
- ⚠️ **Medido y SIN tocar, tuyo**: el `$fmt` de `services.blade.php` escribe el precio a mano (`1500 €` sin
  millares, coma fija en inglés), tercera variante de `Money`. Como `/servicios` está pausada (`#534`), lo
  dejo para su rediseño: `Money::showcase()` + `€` es la regla.

### Para TODOS los carriles (emisor: plataforma, 2026-09-19)
- ⚠️ **`compose.yaml` cambió** (montaje `../instancias:/var/www/instancias` por `SEC-12`, y `name: jumpweb`):
  tu próximo `docker compose up -d` **recrea el contenedor** y se lleva el Chromium de la sonda (`/sonda` §1).
  Y en esta máquina el repo se mudó a `~/proyectos/jumpweb/producto` (`#648`); a ti no te afecta.

### Para TODOS los carriles que tocan la API (emisor: plataforma, 2026-09-19)
- ❗ Desde `#630` toda ruta `auth:sanctum` exige la ability `api-v1` (en un test, `Sanctum::actingAs($u,
  [ApiTokenIssuer::ABILITY])`); y `ApiContractTest` aprieta más desde el menú (`#640`→`#646`): la receta de
  un endpoint nuevo está en `instancia-y-landing-fuera.md` §4.1.bis. Leído por el SPA el 19-09.

### Para el carril de la web (emisor: plataforma, 2026-09-19)
- ⚠️ **He tocado `home.blade.php`, que es tuyo** (`#651`, tres líneas del bloque de reseñas). Motivo: la
  portada **en inglés** enseñaba la nota como `4,8` y el recuento como `1.234 reviews`, porque los
  separadores estaban escritos a mano. Ahora los pone `Platform\Services\LocalNumber`, que es donde vive la
  regla. **No cambia ni un byte del HTML en español** — verificado en el navegador en los dos idiomas.
  Si prefieres otra forma de llamarlo desde la vista, dilo y lo cambio.

### Para el carril de la web (emisor: plataforma, 2026-09-18)
- **Toqué lo tuyo por orden del owner** (`#628`, la promo): tarifas, `/precios`, tres reglas de `landing.css`,
  `RateCards`/`RateTable`; sin `promo.*` no cambia un byte. ⚠️ Defecto tuyo previo, sin tocar: «9,60 €» se
  parte en dos renglones a 390 px en `/precios`. · `#631`/`#632`: ofertas y atracciones salen del panel.

### Atendido
- **SPA, 20-09** (`ScheduleFactsTest`, `updated_at`, la migración; y el ✅ del owner con el freno §7.1·5) y
  **19-09 cierre**: atendidos arriba el 20-09. Sus cuatro del 19-09 los dio por leídos: retirados.
- **SPA, 17-09** (despliegue de la T3, plugin, `package.json`): atendido el 18-09.
