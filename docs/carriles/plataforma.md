# Carril · Plataforma (producto e instancias)

> Máquina: **este ordenador**, `~/proyectos/jumpweb/producto` (mudado en `#648`; las instancias al lado, en
> `jumpweb/instancias/<slug>`) · Banda: **610–639 AGOTADA con `#639`** → sigue en
> **640–669** · Último usado: **`#665`** · Spec: `docs/specs/producto-e-instancias.md` (§0 y §4.9) y, para lo
> que viene, `docs/specs/instancia-y-landing-fuera.md` · Actualizado: **2026-09-20**, cierre de sesión (T2b: `pages/` ya no
> existe; el barrido de `home` hecho y **sus cuatro reglas fuera**, con el material del cliente en el
> paquete de instancia, que ya tiene remoto privado).
> Este fichero lo escribe SOLO el agente de este carril (`DECISIONES #621`): foto, retomar, ficheros y buzón.
> Techo **32 KB** (check 10; subido de 24 en `#724` con la medida delante). El contador de la suite no
> vive aquí: va en el trailer del commit.

## Foto

- **Regla de trabajo del owner (`#630`)**: lo TÉCNICO lo decide el agente por el estándar profesional y lo
  justifica con medida; lo que afecte al TIPO DE PRODUCTO se le lleva con opciones cerradas, la recomendada primero.
- **F0–F4 CERRADAS**; su detalle vive en sus specs y decisiones. El plugin `jumpweb-agente` (repo
  `~/proyectos/jumpweb-agente`) se actualiza con `claude plugin marketplace update` + `claude plugin update …
  --scope project` (`#626`) y pide REINICIAR. ▶ De F1 queda podar `DEUDA.md` y `VERIFICACION-E2E-CAJON.md`.
- **Análisis estático ENTERO en el gate** (`#625`, `#629`): Larastan 5 y ESLint con trinquete; la línea base
  **solo encoge**.
- **F5, su principio `[DECIDIDO owner]`** (`#631`, `#632`): la landing consume un MENÚ DE HECHOS por API y
  TODO es opcional; atracciones y widget de ofertas FUERA del panel («oferta» = hecho de precio).
- **v1.2.0 CORTADA el 19-09 y SIN DESPLEGAR** — el detalle, en el punto 2 de «por dónde retomar», que es
  donde hay que leerlo. `#627`: la app en React Native + Expo.
- **`#628` · la promo «−20 % online» sigue EN PRODUCCIÓN** (cuatro filas `promo.*`; receta de fin en
  `ENTORNOS.md` §6 y en «retomar» 5).
- ⚠️ **Tras la mudanza de `#648` una sesión ya abierta PIERDE skills y hooks** (el registro del plugin se
  resuelve al arrancar): basta con sesión nueva en `producto/`. ⚠️ Y en la sesión del 20-09 el harness no
  listó las skills del plugin: `/carril` y `/handoff` se siguieron a mano desde su `SKILL.md`.

## Por dónde retomar, en orden

1. **F4 · CERRADA el 19-09** (`specs/cajon-empaquetable.md`: sus cinco tandas y sus seis trampas). ⚠️ **Le
   falta un ojo humano sobre la COMPRA de la T5** (medida, no vista): se enseña con el banco de su §4.8,
   **que se BORRA al terminar** o la guarda 9 del despliegue aborta.
2. **LO SIGUIENTE: DESPLEGAR la v1.2.0, y eso lo decide el owner.** Etiqueta cortada y empujada
   (`v1.2.0` = `f581c791`, anotada, 19-09) con su changelog de dos mitades. **Para las instancias no hay
   nada que hacer**: sin migraciones, sin claves de `.env`, sin ajustes. ⚠️ Producción, de noche o con el
   parque cerrado (`#594`); sería el **décimo**. Cortar la versión y desplegarla son dos actos, y el
   segundo es suyo. ▶ El SPA cerró el borde §7.1·5 (`#718`) y dio la invitación por cerrada con el ✅ del
   owner (20-09): su freno ya no aplica; le quedan el `.ics` en un móvil real y Turnstile real en producción.
   ▶ **`main` ya se movió tras etiquetar**, así que la guarda 8 dice «HEAD no es una versión»: se despliega
   desde **`git checkout v1.2.0`** y se vuelve con `git checkout main`. Comprobado en seco: desde la
   etiqueta, el pre-vuelo contesta «versión a desplegar: v1.2.0».
   ▶ Lo hecho DESPUÉS de esa etiqueta (F5 entera, contrato **1.11.0**) va en la siguiente versión.
3. **F5 EN CURSO · el MENÚ DE HECHOS, recurso a recurso** (`specs/instancia-y-landing-fuera.md` ✅; censo en
   su §1 y las tres decisiones del owner en `#639`: las redes se quedan en el panel, los cuatro campos
   muertos de `zones` se retiran, «cero marca» se lee como código vivo).
   **EL MENÚ, SERVIDO** (`#640`→`#646`, contrato **1.11.0**, `scripts/mutar-menu-de-hechos.sh` **41/41**):
   los siete platos, uno a uno en la spec §4.1 (`/site`, horario, normas, legales, precios, las dos fichas
   del catálogo y la prueba social). Aquí queda **lo que hace falta para lo siguiente**.

   ▶ **T2a HECHA** (`specs/paquete-de-instancia.md` ✅, `#647`): el namespace `instancia::` con sus tres
   puertas, el invariante **`SEC-12`**, la `plantilla/` y el repo `jumpweb/instancias/playjump`, **con
   remoto PRIVADO desde `#663`** (`yasmindanailov/instancia-playjump`; nació sin él y lo ganó al entrar
   dentro el material gráfico, que ya no está en `main`). `/contacto` ya resuelve por la instancia.
   ⚠️⚠️ **LA REGLA DE LA T2b** (`#649`, spec §4.5.bis, donde está el porqué entero): **el contrato
   producto↔instancia son los DATOS que recibe la vista, no el HTML que produce.**
   ▶ **HECHO el CONTRATO DE VISTA** (`CONTRATO_DE_VISTAS` + su test, arnés 7/7). ❗❗ La vista recibe NUEVE
   variables, no dos —siete las mete el composer global—, y esa lista **va a ENCOGER** con el menú: ese día
   sube el MAYOR y hay que avisar a cada instalación (spec §4.6).
   ▶ **T2b EN CURSO. El MÉTODO entero está en la spec §4.7** —barrido de reglas y de variables muertas →
   partir pruebas por lo que afirman → anfitrión mínimo → huella 0, con la página DENTRO de la huella—, y
   allí está lo que enseñó cada tanda. Barridos cerrados en `#650`, `#651` y `#653` (arnés 50/50).
   ▶ **LAS OCHO DE `pages/` MUDADAS** (`#654`→`#660`), y **esa carpeta ya no existe**: viven en
   `instancias/playjump/web/`, el producto sirve su `anfitrion/…` sin paquete, y
   **la suite corre SIN paquete** (`phpunit.xml`). Medido cada vez: mismo DOM, huella **0/38** y sitemap
   11=11; su lista de garantías, en `paginas/<nombre>.md` del paquete.
   ⚠️ El material que solo pinta una vista mudada se declara en
   `InstanceViews::MATERIAL_CONSUMIDO_POR_LA_INSTANCIA` (spec §4.7) · el separador de MILLARES de `Money`
   sigue a mano **a propósito** (`#651`): pendiente del owner.
   ▶ **EL BARRIDO DE `home`, HECHO** (`#661`), y **TRES de sus cuatro reglas ya FUERA** (`#661`, `#662`;
   el detalle entero en la spec §4.7, que es donde no caduca): el DINERO —`Money::showcaseWithSymbol()`
   recoge SEIS escrituras y cierra el «9,60 €» partido a 390 px—, el servicio de horario y la escala de
   estrellas. Tres variables muertas retiradas. Huella 38/38 idéntica salvo `/precios`, que solo encoge.
   ⚠️ **Tres guardas que NO existían**, las tres vistas matar a su mutante: nacieron porque cada cambio
   dejaba la suite ENTERA en verde.
   ✅ **EL MATERIAL DEL CLIENTE, FUERA DE `main`** (`#663`, `[DECIDIDO owner]`): los 37 ficheros (9,2 MB)
   viven en `publico/` del paquete y se copian a mano, como `client.css`. Las tres piezas —exclusión del
   `rsync`, lista blanca de la GUARDA 9, `.gitignore` + `git rm`— van en el MISMO commit, o el primer
   despliegue borra producción; verificado en seco con control negativo. El porqué, en la spec §4.7.
   ✅ **Y el paquete tiene REMOTO PRIVADO** (20-09): `yasmindanailov/instancia-playjump`, empujado y
   verificado —mismo SHA local y remoto, 38 ficheros bajo `publico/`, 404 anónimo—. ⚠️ Se comprobó que
   era privado ANTES de empujar: 9,2 MB de fotos de un cliente a un repo público no se deshacen.
   ✅ **LAS CUATRO REGLAS del barrido, RESUELTAS** (`#664`): la de caché del vídeo **no se baja**, y está
   medido — `@filemtime` sale **37 veces en 15 ficheros** con sufijo uniforme (13 × `}}?v={{ @filemtime`):
   es el IDIOMA de la casa, no una regla con dos escrituras. Extraerla para un sitio crearía la
   inconsistencia.
   ▶▶ **LO SIGUIENTE: LA MUDANZA DE `home`. Nada más de la T2b queda antes.** Está MEDIDA (`#664`, plan
   entero en la spec **§4.7.bis**) y se hace en CUATRO unidades, en este orden:
   **(a) La LÍNEA BASE primero**, antes de tocar el controlador — trampa de `#654`: con `pick()` ya
   apuntando al anfitrión, «la vista de antes» que capturas es el respaldo nuevo comparado consigo mismo.
   **(b) Partir las pruebas**: primero las ~44 que CAMBIAN DE SUJETO a `rate-rail` y `visit` (ganancia
   neta del producto), después las ~117 que se van con la vista.
   **(c) El anfitrión mínimo**, que **no puede dar por hecho que hay vídeo** (`data-has-video` se escribe
   a mano en la portada desde el commit fundacional, así que el estado «sin vídeo» que el CSS tiene
   diseñado no se alcanza hoy).
   **(d) Huella 0/38 con la portada DENTRO** y sitemap 11=11.
   ⚠️⚠️ **Buscar ANTES las guardas que leen `home` como TEXTO**: `grep -rln "home.blade" tests scripts`.
   Medido: `SidebarSeamTest` y `SectionHeadlineTest` la nombran por su fichero y salieron rojas en la
   medida. Se re-apuntan CON la mudanza, no después.
   ▶ **Y el CSS NO va en esta tanda**: es la T2c (`#665`).
   ▶ El detalle de la medida que ordenó todo esto:
   Se apuntó el controlador a un anfitrión de nueve líneas y se corrió la suite: **161 rojos de 682, en 31
   ficheros** (`/contacto` fueron 9 de 14, en uno) y **521 sobrevivían** — solo querían «una página».
   ❗❗ **~44 de los 161 tienen su sujeto en un COMPONENTE del producto** (`rate-rail` 25, `visit` 16, el
   mapa 3): **cambian de sujeto, no se mudan** (como `PageHeadTest` en `#658`). Solo **~117** viajan a
   `paginas/home.md` con la huella.
   ✅ **LA DEUDA DEL CSS, CONTESTADA** (`#665`, `[DECIDIDO owner]`): al mudar `home` quedan **208 clases sin
   consumidor** y la guarda de huérfanos **solo ve una** (su sujeto es el material de fachada), así que 207
   serían invisibles. ❗ **Yo propuse componentizar las secciones y la medida lo mató**: la deuda de la vía B
   es que la instancia depende de **29 nombres de componente** (spec §1.5) y la **vía A es el DESTINO**
   (§3); siete secciones más serían ~36. ▶ **`home` se muda TAL CUAL y el CSS va en una T2c propia**
   (`instancia-y-landing-fuera.md` §4.6): marcado se verifica con HUELLA, reglas con CASCADA — dos riesgos,
   dos tandas. ⚠️ Y el anfitrión **no puede dar por hecho que hay vídeo**.
   ▶ Medido y SIN tocar: `LandingAddonPresenter::unique()`, sin consumidor en producción desde `#583` y con
   su propio formato de dinero (ficha en `DEUDA.md`). Después, T3–T5 (spec hermana §4.6).
   ⚠️ **Una guarda de marcado de una vista NO mudada no se borra**: aún tiene sujeto y vigila decisiones del
   owner (`#535`, `#350`, `#551`, `#264`). Se retira o se re-apunta CON la mudanza.
   ⚠️ En local, `compose.yaml` monta `../instancias` y fija `name: jumpweb` (`#648`): es fichero
   COMPARTIDO y va avisado en el buzón.

   ⚠️ **De la ficha** (spec §4.1): **35 imágenes del cliente versionadas en `main`**, sin tocar: van con la T2.

   ▶ **La RECETA de un plato nuevo** está en la spec **§4.1.bis**, y **las OCHO TRAMPAS del menú** (el
   `weekday` que es 0 = domingo, el `sortBy` al revés, el objeto vacío que sale `[]`, el `''` de lo
   borrado…) en **§4.1.ter**: allí no caducan con la tanda.

   **Del censo** (spec §1): sigue valiendo lo guardado —un `tokens.json` en la instancia del que salgan
   `client.css` y el tema de la app— y `zones` tiene cuatro columnas muertas (`#639`).
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
**EL PAQUETE DE INSTANCIA** (`#647`→`#656`: `config/instancia.php`, `Http\Instancia\InstanceViews` con el
CONTRATO DE VISTAS y `MATERIAL_CONSUMIDO_POR_LA_INSTANCIA`, `plantilla/`, `phpunit.xml` (`INSTANCIA_RUTA`
vacía), los anfitriones `resources/views/anfitrion/**` con sus `Anfitrion*Test`, `InstanceViewPathTest`,
`InstanceViewContractTest`, `scripts/mutar-paquete-instancia.sh`, el `name:` y el montaje de `compose.yaml`;
**y el repo `instancias/playjump`**, `web/` y `docs/paginas/`) · **las reglas bajadas en la T2b**
(`Platform\Services\{VenueAddress,LocalNumber,MetaDescription,Honeypot,LocalDate}`, los `imageUrl()` de
`Attraction` y `LandingService`, y `GroupRateTables::lowestWritten()`, con sus tests; los componentes
`site/{honeypot,turnstile}`) · `scripts/huella-maquetacion.mjs` (16 vistas) y los arneses
`mutar-{atracciones,servicios}.py`, más los re-apuntados `mutar-{precios,cumple}.py` ·
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
- **Mover código que unas guardas leen como TEXTO**: se mueve TAL CUAL, se re-apunta cada guarda al fichero
  nuevo y se MUTA allí (el método entero, en `paquete-de-instancia.md` §4.7).
- **El tipo que Sanctum declara para `currentAccessToken()` miente con cookie** (dice `PersonalAccessToken`,
  llega `TransientToken`): el tipo real es `HasAbilities`, con `@var`; no es una entrada más de la línea base.
- ▶ **Las trampas del CAJÓN viven en su spec** (`cajon-empaquetable.md` §4.8): el proxy de Alpine, el bundle
  SSR rancio tras un arnés, las guardas de presupuesto que cambian el diseño, el juez de la hoja y el banco.
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
- **Comparar manifiestos de Vite enteros da un falso «hay algo que desplegar»**: `app.css` cambia de hash con el
  árbol (Tailwind escanea docs y mockups). Se compara entrada a entrada.
- **Taquilla cobra de la misma tabla `prices` que la web**, y el pedido manual y ocho servicios del núcleo llaman
  a `priceCents()`: una rebaja «solo online» como dato es imposible y un descuento por canal es `CRITICAL_RE`.
- `rm -rf` está en el deny del repo y un comando compuesto que lo lleve se deniega entero: `git rm -r` para lo
  versionado, carpeta nueva para lo demás. `claude plugin details` no acepta `--plugin-dir`.
- 💥💥 **`git rm --cached` CONSERVA el fichero, pero el commit REGISTRA UN BORRADO** (`#663`): al rebasar,
  git resetea a `origin/main` —donde sigue rastreado, así que lo **restaura**— y después reaplica tu
  commit, que lo **borra del árbol de trabajo**. Medido: los 37 ficheros del cliente desaparecieron en el
  `pull --rebase` y la web pasó a 404. ▶ **Sacar ficheros del repo tiene un paso previo que no es
  opcional: copiarlos FUERA antes de retirarlos**, y reponerlos verificando con `cmp`.
- ⚠️⚠️ **Y sacar ficheros destapa quién dependía de ellos EN DISCO**: `LandingContentSeeder` hace
  `file_exists()` y guarda `null` si falta, así que tres casos habrían salido **verdes aquí y rojos en el
  otro ordenador** tras su `pull` — §4.5 otra vez, y esta vez en el gate. Antes de retirar material, se
  corre la suite **con** y **sin** él: las dos tienen que estar verdes.
- **«The command 'docker' could not be found» es Docker Desktop APAGADO**: se arranca desde WSL con
  `"/mnt/c/Program Files/Docker/Docker/Docker Desktop.exe"` en segundo plano y `until docker info`.
- Una etiqueta no pasa por el gate (`pre-push` solo mira `refs/heads/main`): `/release` exige que el commit ya
  esté en `origin/main`; y un test sobre una «casi versión» tiene que EMPUJARLA antes de medir.

## Buzón

### ❗❗ Para el carril del SPA (emisor: plataforma, 2026-09-20) — EL CRUCE DE LA SECCIÓN DE RESEÑAS
- ▶ **Contesto tu aviso del cruce: LLÉVALO TÚ, pero hay un plazo.** El contrato `SocialProof`, su
  decorador y `Rating` son del PRODUCTO y se quedan; tócalos sin preguntar. **Lo que NO se queda es el
  MARCADO de la sección**: vive en `home.blade.php` y **se muda a la instancia en la próxima tanda**
  (`#664`, spec §4.7.bis). Después de eso, cambiar cómo se ve una reseña es editar
  `instancia-playjump/web/home.blade.php`, no `main`.
- ▶ **Lo que te dejo hecho y no cambia**: `Rating::MAX` es la escala (no es configurable: la fija Places
  y el `Select` del panel), y `ReviewsSectionTest::test_la_tarjeta_dibuja_la_escala_entera` cuenta los
  glifos con la cifra **tecleada a mano** — si tu fuente trae media estrella, ese caso es el que hay que
  reescribir, y sale rojo solo.
- ⚠️ **Si vas a tocar el marcado ANTES de que yo mude `home`, dilo aquí y espero**; si es después,
  clónate el paquete. Lo que no puede pasar es que lo toques mientras lo estoy mudando.

### ❗❗ Para TODOS los carriles (emisor: plataforma, 2026-09-20) — TU `git pull` BORRA 37 FICHEROS
- ⚠️⚠️ **`#663` saca el material gráfico del cliente de `main`** (35 fotos + el vídeo de la portada y su
  póster, 9,2 MB). Son `git rm --cached`, así que **al hacer `pull` desaparecen de TU disco** — medido dos
  veces: el rebase los restaura del remoto y luego reaplica el commit, que los borra del árbol.
- ✅ **De dónde se sacan**: `git clone https://github.com/yasmindanailov/instancia-playjump` (PRIVADO) y
  `cp -r publico/. <producto>/public/` (receta en `INSTALACION-CLIENTE.md` §4.bis). ⚠️ **Clónalo FUERA del
  árbol del producto**: dentro, el `rsync --delete` del despliegue se lo lleva (`SEC-12`).
- ▶ **Qué se rompe y qué no**: la suite **no** —afirma sobre las RUTAS, no sobre el disco—, ni el gate, ni
  el cajón. Lo que se ve es la **landing con fotos rotas y sin vídeo**, y una huella de `/` que no cuadra
  con la de esta máquina. ▶ El SPA ya lo confirmó en la suya el 20-09.
- ⚠️ **Producción y staging NO se tocan**: `deploy.sh` los excluye del `rsync`, que es además lo que los
  salva de su `--delete`. Verificado en seco con control negativo.

### ❗ Para el carril de la web (emisor: plataforma, 2026-09-20) — EL ESPACIO DEL EURO (`#661`)
- ⚠️ **He tocado `lang/{es,en,fr}/landing.php`, que es tuyo**: un carácter en `events.reserve_terms`. El
  espacio antes del «€» pasa a DURO (`\u{00A0}`), porque «Señal de 50 €» **se podía partir de renglón**.
  **El texto que se lee no cambia ni una letra** y la huella da 0 diferencias en la portada. Si prefieres
  otra forma de escribirlo, dilo.
- ✅ **Cerrado el defecto que te fiché el 18-09**: «9,60 €» ya no se parte a 390 px. La regla vive en
  `Money::showcaseWithSymbol()` y recogió SEIS escrituras sueltas.
- ⚠️ **Toqué también `components/site/rate-rail.blade.php`**: el «antes» tachado pegaba el «€» a mano con
  espacio blando **mientras su hermano `--special` ya lo traía duro** — dos importes tachados en la misma
  tarjeta que se partían distinto. Ahora los dos vienen escritos de `RateCards`.
- ⚠️ Y en `#661`/`#662` he tocado más de lo tuyo en `home.blade.php`: el sello de la tarjeta de cumpleaños
  y las dos estrellas. **Cero bytes de HTML movidos, huella 38/38 idéntica.** (Fundo aquí el aviso del
  19-09 por `#651`, los separadores de la nota en inglés → `LocalNumber`: mismo asunto.)
- ▶ **Medido y tuyo, sin tocar**: los importes que siguen partibles en `/` y `/normas` son **PROSA del
  panel** («por 2 €», «un cargo de 10 €»), no los escribe el producto — y esa prosa sale en ESPAÑOL también
  en en/fr.

### Para el carril de la web (emisor: plataforma, 2026-09-20) — LAS OCHO PÁGINAS, MUDADAS
**`resources/views/pages/` ya no existe** (`#654`→`#660`): las ocho viven en `instancias/playjump/web/` y
el producto sirve su anfitrión mínimo. Medido en todas: mismo DOM en es/en/fr, huella 0/38, sitemap 11=11.
De lo TUYO, por tanda:
- **Las pruebas se partieron** (`#649`): `Contact/Rules/Bar/Attractions/Pricing/Birthday/ServicesPageTest`
  afirman ya sobre los DATOS; su marcado vive en el anfitrión (`Anfitrion*Test`) o en `paginas/*.md` del
  paquete, con la huella de juez. Las filas de `rediseno-desde-canvas.md` lo dicen todas.
- **Arneses re-apuntados y PODADOS**: `mutar-{contacto,normas,bar,bandas,cabecera,precios,cumple}` apuntan
  a los anfitriones; se retiraron ocho mutantes que habían perdido su sujeto en `#583`/`#585` y salían «NO
  APLICADA». Nacen `mutar-atracciones.py` y `mutar-servicios.py`.
- **Toqué `home.blade.php`** (`#657`): los dos `<img>` del mosaico llaman a `Attraction::imageUrl()`. Ni un
  byte de HTML cambia, medido en tres idiomas.
- ❗ **DOS defectos tuyos, vivos y arreglados**: la fecha de `/normas` decía «September de 2026» en inglés
  (`#656`), y el «desde» de `/servicios` se escribía con el registro de TRANSACCIÓN —en inglés convivía
  «from 14.95 €» con «12,00 €»— (`#660`). ⚠️ Este segundo **cambia lo que se ve**, con el owner decidiéndolo
  y la medida delante: «12,00 €» → «12 €». Con él queda cerrada la tercera variante de `Money`.
- ⚠️ **Guardas que cambian de sujeto, no de fuerza**: la mitad de `PageHeadTest` del abanico se prueba en
  el componente de fachada; el corpus de `SectionHeadlineTest` y el censo de `SidebarSeamTest` miran los
  anfitriones; y una aserción de `PublicPagesTest` (la cinta `C3`) se retira: el producto no puede
  exigirle a una instalación que pinte una decoración. El material que solo pinta la instancia —`trio--page`
  y las cinco clases de `brand-band`— queda declarado en `InstanceViews`.
- ⚠️ **Medido y SIN tocar, tuyo**: `LandingAddonPresenter::unique()` no tiene consumidor en producción
  desde `#583` y escribe el dinero a su manera (ficha en `DEUDA.md`) · `mutar-cabecera.py` tiene cuatro
  mutantes que ya no aplican y `mutar-bandas.py` uno · el recuento de atracciones se escribe en dos
  controladores (hoy iguales, y ahora con guarda que los compara).

### Para TODOS los carriles (emisor: plataforma, 2026-09-19)
- ⚠️ **`compose.yaml` cambió** (montaje `../instancias:/var/www/instancias` por `SEC-12`, y `name: jumpweb`):
  tu próximo `docker compose up -d` **recrea el contenedor** y se lleva el Chromium de la sonda (`/sonda` §1).
  Y en esta máquina el repo se mudó a `~/proyectos/jumpweb/producto` (`#648`); a ti no te afecta.

### Para el carril de la web (emisor: plataforma, 2026-09-18)
- **Toqué lo tuyo por orden del owner** (`#628`, la promo): tarifas, `/precios`, tres reglas de `landing.css`,
  `RateCards`/`RateTable`; sin `promo.*` no cambia un byte. ⚠️ Defecto tuyo previo, sin tocar: «9,60 €» se
  parte en dos renglones a 390 px en `/precios` (hoy, en la vista de la instancia).

### Atendido
- **SPA `#724`, el techo del carril** (20-09): atendido. Mi encabezado ya dice **32 KB**. ⚠️ Llegó a mitad
  de `#662`, que se cerró rascando contra el techo VIEJO cuatro veces — lo recortado bajó a
  `paquete-de-instancia.md` §4.7, que es donde tenía que estar de todos modos.
- **SPA, el ✅ del owner sobre la invitación y el fin de su freno** (20-09): atendido; anotado en «retomar»
  punto 2 — el despliegue de la v1.2.0 ya no está bloqueado por el cajón.
- **RETIRADOS los míos que el SPA dio por atendidos** (20-09): los seis de 16-09→19-09, los cuatro del
  20-09 (el 422 de `InvitationHostController`, su arreglo de `ScheduleFactsTest`, `updated_at` y la
  migración) y el aviso de los 37 ficheros, que confirmó en su máquina. Lo duradero de todos ellos vive
  donde no caduca: la receta de la API en `instancia-y-landing-fuera.md` §4.1.bis y el resto en mis trampas.
  ▶ Del 422 dejó dicho que **un `""` deje el campo como estaba le vale**: no pide que borre. Cerrado.
