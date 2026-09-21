# Carril · Plataforma (producto e instancias)

> Máquina: **este ordenador**, `~/proyectos/jumpweb/producto` (mudado en `#648`; las instancias al lado, en
> `jumpweb/instancias/<slug>`) · Banda: **610–639 AGOTADA con `#639`** → sigue en
> **640–669 AGOTADA con `#669`** → sigue en **670–699** · Último usado: **`#669`** · Spec: `docs/specs/producto-e-instancias.md` (§0 y §4.9) y, para lo
> que viene, `docs/specs/instancia-y-landing-fuera.md` · Actualizado: **2026-09-21**, cierre de sesión
> (**T2b, T2c, T3·1 y T4 cerradas**: la landing entera fuera, el CSS huérfano podado con trinquete,
> el widget de ofertas retirado y `zones` sin sus cuatro columnas muertas).
> ✅ **Banda nueva ya abierta**: `DECISIONES.md` declara **670–699** para este carril (avisado en el
> buzón, que es fichero compartido).
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
   ▶ **EL MÉTODO de una mudanza está en la spec §4.7** —barrido de reglas y de variables muertas →
   partir pruebas por lo que afirman → anfitrión mínimo → huella 0, con la página DENTRO de la huella—, y
   allí está lo que enseñó cada tanda. Barridos cerrados en `#650`, `#651` y `#653` (arnés 50/50).
   ▶ **LAS OCHO DE `pages/` MUDADAS** (`#654`→`#660`), y **esa carpeta ya no existe**: viven en
   `instancias/playjump/web/`, el producto sirve su `anfitrion/…` y **la suite corre SIN paquete**
   (`phpunit.xml`). Medido cada vez: mismo DOM, huella **0/38**, sitemap 11=11; las garantías de cada una,
   en `paginas/<nombre>.md` del paquete. ⚠️ El material que solo pinta una vista mudada se declara en
   `InstanceViews::MATERIAL_CONSUMIDO_POR_LA_INSTANCIA` · el separador de MILLARES de `Money` sigue a mano
   **a propósito** (`#651`): pendiente del owner.
   ▶ **EL BARRIDO DE `home`, HECHO** (`#661`, `#662`; detalle entero en la spec §4.7): fuera el DINERO
   —`Money::showcaseWithSymbol()` recoge SEIS escrituras—, el servicio de horario y la escala de estrellas,
   con **tres guardas que NO existían**, las tres vistas matar a su mutante: nacieron porque cada cambio
   dejaba la suite ENTERA en verde.
   ✅ **EL MATERIAL DEL CLIENTE, FUERA DE `main`** (`#663`, `[DECIDIDO owner]`; spec §4.7): los 37
   ficheros (9,2 MB) viven en `publico/` del paquete —que por eso ganó **remoto PRIVADO**, verificado con
   un 404 anónimo ANTES de empujar— y se copian a mano, como `client.css`. ⚠️⚠️ Las tres piezas
   —exclusión del `rsync`, lista blanca de la GUARDA 9, `.gitignore` + `git rm`— van en el MISMO commit, o
   el primer despliegue borra producción; verificado en seco con control negativo.
   ✅ **Y las CUATRO REGLAS del barrido, resueltas** (`#664`): la de caché del vídeo **no se baja** —
   `@filemtime` sale 37 veces en 15 ficheros con sufijo uniforme: es el IDIOMA de la casa, no una regla
   con dos escrituras.
   ✅✅ **`home` MUDADA y la T2b CERRADA** (`#666`, 21-09; detalle en la spec **§4.7.ter**): huella 0 en
   38 pantallas, mismo DOM en es/en/fr, sitemap 11=11, y `resources/views/` **sin ninguna landing de
   cliente**. ❗❗ `#664` midió 161 rojos y salieron **16** (usó un anfitrión de NUEVE líneas): *la cifra
   mide el anfitrión, no la página*. De ahí el criterio —**consumir el CONTRATO ENTERO**— y que **una
   OBLIGACIÓN no se muda** (la atribución de Places se queda). Arneses **144/144**; nace
   `AnfitrionPortadaTest`.

   ✅✅ **T2c CERRADA: el CSS huérfano, podado y con TRINQUETE** (`#667`, 21-09; detalle en la spec
   hermana **§4.6.bis**). Las **208** clases de `#665` eran **17** —el anfitrión sostiene 174— y lo que
   sí había eran **128 sin consumidor de nadie**: **119 podadas** (−20,3 KB) con **huella 0/38**, y las
   **9 del hero vacío intactas** porque su CSS lo aparcó el owner en `#226`.
   ❗❗❗ **Un censo de CSS huérfano que busca el nombre LITERAL miente**: mintió tres veces (161 → 139 →
   128) porque aquí una clase se compone de **tres formas** —concatenación JS, concatenación PHP e
   interpolación Blade en el atributo—. Lo que queda encendido es `LandingCssHasNoOrphansTest`, con
   deuda declarada de **15** que **solo encoge**.

   ✅ **T3·1 HECHA: el widget de ofertas y el complemento por atracción, fuera** (`#668`, 21-09;
   detalle en la spec hermana **§4.6.ter**). De los seis recursos salen los dos con CERO uso; los otros
   cuatro **esperan a tener plato en el menú** (ver abajo). Huella 0/38. ❗ La tercera pieza del alcance
   —«las secciones de texto»— **no existía**: ninguna de las 71 claves de `settings` está sin consumidor.
   ▶▶ **`InstanceViews::CONTRATO` = 2, la PRIMERA subida**: `offers` salió del composer, o sea del
   contrato de vista de las nueve páginas. Los dos `instancia.json` ya lo declaran.
   ❗❗❗ **PODAR POR CLASE NO PODA UNA FEATURE** (dejó vivas sus `@keyframes`, sus tokens y los
   selectores mezclados): *se poda por su BLOQUE*. ⚠️⚠️ Y **una retirada arrastra su cadena**, aquí de
   cinco eslabones —el panel entero cayó (112 rojos) porque `AdminSettingsHub` listaba el recurso y el
   censo buscó `\bOffer\b`, que no casa con `OfferResource`—.

   ✅ **T4 HECHA: `zones` pierde sus cuatro columnas muertas** (`#669`, 21-09; spec hermana §4.3).
   `subtitle`, `age_label`, `area_sqm` y `rides_count`: re-medidas antes de tocarlas, solo vivían en el
   modelo, el formulario del panel y un test del seeder. **Huella 0/38 · suite 5.567.**
   ❗ **`rides_count` COINCIDÍA con el recuento real** (15=15, 8=8): no mentía, pero era un contador a
   mano de lo que el producto ya calcula donde lo publica. Lo delató quién lo vigilaba —`ZoneImageTest`
   **comparaba las dos fuentes**—, y *que hiciera falta compararlas era el síntoma*.
   ⚠️⚠️ **El SEEDER era el consumidor escondido**: al aplicar la migración, la suite dio **604 errores**
   porque `LandingContentSeeder` seguía escribiendo las cuatro. *El censo de una columna incluye quien la
   SIEMBRA, no solo quien la lee.*
   ⚠️ Se pierden los datos (subtítulos, etiquetas de edad, los metros): no se migran porque no se
   publican. El `down()` recrea la forma, nunca el contenido.

   ▶▶ **LO SIGUIENTE: LA T5 · la v2.0.0** (spec hermana §4.6·5), que es lo único que queda de F5 salvo
   la espera de la T3. El contrato de instancia YA está en **2** (`#668`) y `zones` ya adelgazó, así que
   la T5 es cortar la versión con su changelog y su nota de migración.
   ⚠️⚠️ **El RESTO DE LA T3 está bloqueado por diseño, no por tiempo**: `attractions`, `faqs`,
   `testimonials`, `landing_services` y `bar_images` tienen contenido vivo y **el menú de hechos no
   tiene plato para ellos**; sacarlos convertiría «editar la web» en «desplegar el repo de la
   instancia». ⚠️ `faqs` además NO es solo presentación —de esa tabla cuelgan el JSON-LD `FAQPage`, la
   chapa de `/contacto` y el `lastmod` del sitemap— y `testimonials` es el contrato de prueba social con
   el que **el carril del SPA está trabajando ahora**. ▶ El camino, si se quiere de verdad: primero su
   plato en el menú, después la retirada.
   ⚠️ Y las tablas `offers` y la columna `attractions.ticket_type_id` **siguen ahí**: se borran cuando
   se decida, con la receta de `#669` (migración con `hasColumn`, datos que no se migran, `down()` que
   recrea la forma).

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
`InstanceViewContractTest`, `AnfitrionPortadaTest` (`#666`), `scripts/mutar-paquete-instancia.sh`, el `name:` y el montaje de `compose.yaml`;
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
- 🧪🧪 **UN «NO SE APLICÓ» NO ROMPE EL GATE, Y EL ARNÉS SIGUE CANTANDO SU VEREDICTO** (`#666`). Al
  re-apuntar `mutar-dudas.sh` salió que **dos de sus mutantes llevaban caducados desde `#537`**, que había
  cambiado los tokens del CSS (`--bg-soft`/`--line` → `--tint-attn-border`): los patrones dejaron de casar
  y el arnés seguía diciendo 19/19 con dos guardas del acordeón que no miraban nada. ▶ **El veredicto de
  un arnés solo vale si sus mutaciones se APLICAN**, y esa línea hay que leerla: no es un aviso menor, es
  el veredicto entero. ⚠️ Y distingue los dos casos: un mutante que perdió su SUJETO se poda con su
  motivo; uno cuyo patrón solo cambió de sitio se RE-APUNTA.
- 🔤 **Un arnés que decodifica la salida de otro puede reventar antes de dar veredicto** (`#666`):
  `mutar-pie.py` murió con `UnicodeDecodeError` porque el HTML que PHPUnit vuelca al fallar lo **trunca
  por longitud**, a media secuencia UTF-8. Se lee con `encoding='utf-8', errors='replace'`. *Quien
  decodifica la salida de otro no puede dar por hecho que está bien formada.*
- ⏰⏰⏰ **UNA LÍNEA BASE QUE CRUZA UN UMBRAL DEL HORARIO MIDE DOS ESTADOS** (`#666`, ampliada en
  `#668`). No es «la medianoche»: son **los dos umbrales de CADA DÍA**. En `#668` la huella «antes»
  se tomó a las 20:40 con el parque ABIERTO y la comparación a las 21:57, ya CERRADO: **1.603 nodos
  distintos en 38 pantallas** que no eran del cambio —el verde de «abierto» pasaba a gris, la
  sección crecía 32 px—. ▶ Antes de creerse una diferencia se mira `GET /api/v1/schedule/now`, y si
  el estado cambió **se retoma la base dentro de la misma franja**. La de la noche dura hasta que
  abre, así que es la ventana cómoda. *Una medida con dos estados dentro no es un juez.*
- ⏰⏰ **Y el caso original, que sigue valiendo** (`#666`). La huella «antes» empezó un
  domingo a las 23:52 y acabó el lunes a las 00:00: el estado del horario (`heroStatus`) viaja en el menú
  de **las doce vistas**, así que las 38 pantallas quedaron contaminadas y el DOM daba 18 líneas de
  diferencia —«Ya hemos cerrado» → «Abre hoy»— que no eran del cambio. ▶ Se retoma con el reloj lejos del
  borde y **apartando el cambio con `git stash -u`**: revertir solo el controlador no basta, la vista
  tiene que volver a su sitio. *Una medida con dos días dentro no es un juez: es ruido con forma de
  diferencia.*
- Una etiqueta no pasa por el gate (`pre-push` solo mira `refs/heads/main`): `/release` exige que el commit ya
  esté en `origin/main`; y un test sobre una «casi versión» tiene que EMPUJARLA antes de medir.

## Buzón

### ❗❗❗ Para el carril del SPA (emisor: plataforma, 2026-09-21) — `home` YA ESTÁ MUDADA
- ▶ **El plazo que te di se cumplió: la portada vive en la instancia** (`#666`). El marcado de la
  sección de reseñas **ya no está en `main`**: es `instancia-playjump/web/portada.blade.php`. Para
  cambiar cómo se ve una reseña, clónate el paquete (`yasmindanailov/instancia-playjump`, PRIVADO) y
  edítalo allí; la huella del producto sigue siendo el juez.
- ✅ **Lo tuyo no se ha tocado y no se rompe**: `SocialProof`, su decorador, `Rating` y `GoogleReviewReader`
  son del producto y se quedan. Tu `#729` entró sin rozar nada de esta tanda.
- ⚠️⚠️ **Y hay una copia que NO es un duplicado: el ANFITRIÓN** (`resources/views/anfitrion/portada.blade.php`).
  Pinta la sección de reseñas porque el producto tiene que poder demostrar que cumple la licencia de
  Places **en la superficie que él sirve**: atribución con su variante, autor acreditado con enlace y
  foto, aviso de traducción **con el original servido** y la frase de la política. Si tu T2 cambia la
  forma del dato —fotos, respuesta del parque, anónimos, la línea del filtro—, **ese fichero también se
  toca**, y sus guardas son `GoogleAttributionTest` y `ReviewsSectionTest`, que ya apuntan ahí.
- ▶ `ReviewsSectionTest::test_la_tarjeta_dibuja_la_escala_entera` sigue contando glifos con la cifra
  tecleada a mano: si tu fuente trae media estrella, ése es el caso que hay que reescribir.

### ❗❗ Para TODOS los carriles (emisor: plataforma, 2026-09-21) — BANDA NUEVA Y CONTRATO 2
- ▶ **He tocado `docs/DECISIONES.md`, que es COMPARTIDO**: mi banda 640–669 se agotó con `#669` y el
  carril sigue en **670–699**. Solo cambia mi fila y la de la centena; vuestras bandas, intactas.
- ⚠️⚠️ **El CONTRATO DE INSTANCIA es 2** (`#668`): `offers` salió del payload del composer global, así
  que **ya no llega a ninguna vista**. Si alguien pintaba `$offers` en algo, se quedó sin dato — medido:
  nadie lo hacía. Los dos `instancia.json` (plantilla y PlayJump) ya lo declaran.
- ⚠️ **Vuestro `git pull` os traerá una MIGRACIÓN que borra columnas** (`zones`: `subtitle`,
  `age_label`, `area_sqm`, `rides_count`). En vuestra BD local se pierden esos valores; no los lee
  nadie. Si la web os da 500 tras el `pull`, es que os faltan migraciones: `php artisan migrate`.

### ❗❗ Para el carril de la WEB (emisor: plataforma, 18→21-09; los CUATRO avisos, fundidos)
- ▶ **`resources/views/home.blade.php` NO EXISTE** (`#666`), como ya no existe `pages/`: las NUEVE vistas
  viven en `instancia-playjump/web/`, tal cual y sin un byte de HTML cambiado (huella 0 en 38 pantallas,
  mismo DOM en es/en/fr). **El diseño de la landing se toca ahí**, no en `main`. El producto conserva sus
  `anfitrion/*.blade.php`, que son SU versión —sin fachada, sin manchas, sin trío y sin vídeo— y no son
  donde se viste PlayJump. Las garantías que viajaron están en `paginas/*.md` del paquete.
- ⚠️ **He tocado lo tuyo, y en tres sitios**: `lang/{es,en,fr}/landing.php` (un carácter: el espacio antes
  del «€» pasa a DURO en `events.reserve_terms`, porque «Señal de 50 €» se partía de renglón; el texto no
  cambia ni una letra), `components/site/rate-rail.blade.php` (el «antes» tachado pegaba el «€» a mano
  mientras su hermano `--special` ya lo traía duro) y la promo de `#628` —tarifas, `/precios`, tres reglas
  de `landing.css`, `RateCards`/`RateTable`—. Si prefieres otra forma de escribirlo, dilo.
- ✅ **Cerrado el defecto que te fiché el 18-09**: «9,60 €» ya no se parte a 390 px; la regla vive en
  `Money::showcaseWithSymbol()` y recogió SEIS escrituras sueltas.
- ❗ **Y DOS defectos tuyos, vivos y arreglados**: la fecha de `/normas` decía «September de 2026» en inglés
  (`#656`) y el «desde» de `/servicios` se escribía con el registro de TRANSACCIÓN —en inglés convivía «from
  14.95 €» con «12,00 €»— (`#660`). ⚠️ El segundo **cambia lo que se ve** («12,00 €» → «12 €»), con el owner
  decidiéndolo y la medida delante.
- ⚠️ **Las clases CSS sin consumidor ya no son invisibles** (`#667`): se podaron 119 (−20,3 KB) y lo que
  queda lo vigila `LandingCssHasNoOrphansTest`, con deuda declarada de 15. **Las 9 del hero vacío siguen
  intactas** porque las aparcó el owner (`#226`). Si añades CSS, su consumidor tiene que nacer con él.
- ▶ **Medido y TUYO, sin tocar**: los importes que siguen partibles en `/` y `/normas` son PROSA del panel
  («por 2 €», «un cargo de 10 €»), y esa prosa sale en ESPAÑOL también en en/fr ·
  `LandingAddonPresenter::unique()` no tiene consumidor desde `#583` y escribe el dinero a su manera ·
  `mutar-cabecera.py` tiene cuatro mutantes que ya no aplican y `mutar-bandas.py` uno.

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
- **RETIRADOS los míos que el SPA dio por atendidos** (16-09→21-09), incluido el aviso de los 37
  ficheros del material, que confirmó en su máquina. Lo duradero vive donde no caduca: la receta de la
  API en `instancia-y-landing-fuera.md` §4.1.bis y el resto en mis trampas.
