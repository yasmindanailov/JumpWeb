# Carril · Plataforma (producto e instancias)

> Máquina: **este ordenador**, `~/proyectos/jumpweb/producto` (mudado en `#648`; las instancias al lado, en
> `jumpweb/instancias/<slug>`) · Banda: **610–639 AGOTADA con `#639`** → sigue en
> **640–669 AGOTADA con `#669`** → **670–699 EN CURSO** · Último usado: **`#678`** · Spec: `docs/specs/producto-e-instancias.md` (§0 y §4.9) y, para lo
> que viene, **`docs/specs/analitica.md`** · Actualizado: **2026-09-23**
> (**segunda sesión del 23-09**: `#677` los TRAMOS DE GRUPO y el menú COMPLETO, contrato **1.17.0**; `#678` la
> dirección de la ANALÍTICA, decidida por el owner, con su spec; T1a→T1e del libro hechas, contrato 1.19.0).
> ⚠️ **El techo de 32 KB apretó SIETE veces el 23-09** y se resolvió siempre mudando, nunca subiéndolo (es
> del owner): **se muda, no se raspa** — y si vuelve a pasar tres veces seguidas, llévaselo con la medida
> como hizo el SPA en `#724`.
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
- ❗❗❗ **LAS DOS DECISIONES DEL OWNER QUE ORDENAN TODO LO DEMÁS, Y VAN PRIMERO**:
  ▶ **La VÍA A** (21-09): termina su diseño en Claude Design y **estrena la arquitectura nueva con una
  landing que consuma la API** —el destino que §3 de la spec hermana ya declaraba—. ✅ El 23-09 eligió
  arrancar por **los platos**, no por el kit de widgets de `#632`·P2.
  ▶ **`#670` (23-09): NO SE DESPLIEGA EN PIEZAS.** Producción sigue en **v1.1.0** hasta el final del
  programa; **v2.0.0 es UNA versión grande** con todo dentro (los platos, la landing nueva, el cajón, el
  justificante, la invitación y las analíticas), de noche y con él pendiente. Su motivo: un despliegue
  cuesta ATENCIÓN aunque salga bien, y la quiere para diseñar.
  ⚠️ Consecuencias en «retomar» 2. `#627`: la app en React Native + Expo.
- **`#628` · la promo «−20 % online» sigue EN PRODUCCIÓN** (cuatro filas `promo.*`; receta de fin en
  `ENTORNOS.md` §6 y en «retomar» 5).
- ⚠️ **Tras la mudanza de `#648` una sesión ya abierta PIERDE skills y hooks** (el registro del plugin se
  resuelve al arrancar): basta con sesión nueva en `producto/`. ⚠️ Y en la sesión del 20-09 el harness no
  listó las skills del plugin: `/carril` y `/handoff` se siguieron a mano desde su `SKILL.md`.

## Por dónde retomar, en orden

1. **F4 · CERRADA el 19-09** (`specs/cajon-empaquetable.md`: sus cinco tandas y sus seis trampas). ⚠️ **Le
   falta un ojo humano sobre la COMPRA de la T5** (medida, no vista): se enseña con el banco de su §4.8,
   **que se BORRA al terminar** o la guarda 9 del despliegue aborta.
2. **EL DESPLIEGUE, DECIDIDO Y APARCADO** (`#670`, 23-09; el porqué, arriba en la Foto). Producción se
   queda en **v1.1.0**: un defecto allí obliga a `cherry-pick` sobre la etiqueta o a desplegar
   igualmente, no hay tercera salida.
   ⚠️⚠️ **LOS DOS INTERRUPTORES DE LA INVITACIÓN NO SE TOCAN** (`ticket_types.guest_invitation` y
   `guardian_authorization`). Medido contra el git: **v1.2.0 lleva T1→T5 y NO T6 ni T7** —entraron
   después de la etiqueta, igual que `#718`—, así que encenderlos daría media feature. ❗ El `CHANGELOG`
   decía «entra ENTERA» y era **falso**: corregido en `#670`.
   ▶ **Cuando llegue**: desde la etiqueta **v2.0.0** que se corte entonces (la guarda 8 rechaza `HEAD`),
   de noche (`#594`), y con **ENSAYO en staging** antes: siete migraciones y subiendo, una destructiva.
   ❗ **Y dos comprobaciones ANTES de cortarla**: (a) contar en PRODUCCIÓN los tramos cuyo `min_qty`
   supera el `max_qty` de su pack —desde `#677` la tabla de `/servicios` deja de anunciarlos, y aquí no se
   puede medir—; (b) la portada que vaya a producción pinta la línea del FILTRO de reseñas
   (`socialSelection`, la Ómnibus) y la tarjeta de la T2·8 — buzón del SPA del 21/22-09, abajo en Atendido.
3. **F5 · EL MENÚ DE HECHOS, COMPLETO ✅** (`specs/instancia-y-landing-fuera.md`; la historia de cada tanda
   vive allí, que es donde no caduca). T1→T4 (`#640`→`#669`) · los cuatro platos de la vía A
   (`#671`→`#674`) · el censo y sus lotes (`#675`, `#676`) · y **los TRAMOS DE GRUPO** (`#677`, contrato
   **1.17.0**): dentro de `/prices` y no en ruta propia, en **céntimos** y sin importe escrito —
   `[DECIDIDO owner]`: el dinero de una página lo escribe una sola mano, la de la landing— (§4.1.sexies).
   ▶ **Lo que hay que saber del estado**: las NUEVE vistas viven en `instancias/playjump/web/` y el
   producto sirve su `anfitrion/…`; la suite corre SIN paquete. El contrato producto↔instancia son los
   **DATOS** que recibe la vista, no el HTML (§4.5.bis) · `InstanceViews::CONTRATO` = **2** · el
   trinquete de CSS huérfano está encendido, así que **si añades CSS su consumidor nace con él** · la
   migración de `zones` **borra columnas** y viaja en la v2.0.0.
   ▶▶▶ **LA ANALÍTICA** (`specs/analitica.md`, `#678` `[DECIDIDO owner]`, 23-09): T0 la spec ✅ y su v2 tras
   la revisión adversarial (16 agentes, 72 hallazgos; §7.1). ✅ **T1 ENTERA (23-09)**, cinco commits
   `f501a990`→`4d4c3aec`, cada tanda con su párrafo en §4.8 (el libro · `cajon/track.js` diferido +
   `scripts/sonda-analitica.mjs` · correos con UTM tras firmar · la fuente del pedido manual · `anonymize()` y
   el export; contrato **1.19.0**) y su cierre: `scripts/mutar-analitica.sh` **19/19**, `redsys:verify-concurrency`
   ✓, `RGPD-07` + `PAY-21`, y **`trustProxies '*'` RETIRADO** (`SEC-13`: sin proxy delante de PHP una XFF falsa
   se honraba; medido en staging y producción). ⚠️ Queda el OJO del owner en la fuente del pedido manual.
   ▶ **`[DECIDIDO owner]` 23-09: la analítica sigue en el OTRO ordenador (T2→T5; el traspaso, en mi buzón) y
   AQUÍ arranca la LANDING NUEVA.** Sus palabras: *«el nuevo diseño no tiene nada que ver con el antiguo, es
   totalmente diferente y tal vez debamos hacer lógica nueva, no es solo diseño; lo subiré directamente a la
   instancia o tal vez con DesignSync, es un sistema de diseño completo»*. Lo demás se valora en la PRÓXIMA
   sesión, con el diseño delante: cómo llega (a la instancia o por `/design-login` + DesignSync — hoy no está
   autorizado en esta máquina), con qué se construye la vía A (mi recomendación: Astro), cómo entran los hechos
   (mi recomendación: horneados al construir + refresco en vivo; pidió que se lo explique con la opción más
   profesional), y la primera tanda (probablemente la portada; «lo iteramos cuando la veas»). ⚠️ Es una SPEC
   nueva (`/spec`), no una portada más: si trae lógica nueva, primero entra al menú de hechos (§4.1.bis).
   `[DECIDIDO owner]` 23-09: todo con la v2.0.0 y sin la pregunta tras pagar; `[PENDIENTE: asesoría]` los tres
   puntos de la spec §7.
   ⚠️⚠️ **Lo que dejaron los platos y vale para lo que venga** (detalle en la spec §4.1 y §4.1.ter): si
   el dato tiene **servicio de dominio**, el recurso **delega** · el filtro de «lo que no viaja» va
   **DESPUÉS** del respaldo de idioma (`Translated::pick()` encadena con `??`) · lo que decide la MAQUETA
   no es un hecho · `BarImage` re-mide contra el DISCO · una aserción de subcadena acusa al fixture que
   la nombra (`#553`): se aserta sobre CLAVES · y una regla compartida **cambia las dos superficies**: la
   del máximo de `#677` la destapó un fixture de «30 a 20» en `ServicesPageTest`.
   ▶ **Deuda declarada** (en la spec): `birthday`/`groups` son vocabulario del SECTOR (`ContactTopics`) ·
   `price_table`, `nav_subtitle` y `show_in_nav` se retiran con la tanda de la PÁGINA, no antes · un
   producto activo sin NINGÚN precio sale de `/prices` sin la clave `prices` que el contrato exige (`#677`).
   ⚠️ El §0 de la spec está a **1.925 de 2.048 B**: de ahí solo se toca la línea de «Estado». La **T5**
   (cortar v2.0.0) es el final del programa entero, no de esta fase (`#670`).

   ▶ **Lo que se le contestó al owner sobre la FORMA del cajón y sobre los widgets** (medido el 21-09)
   vive ahora en `specs/cajon-empaquetable.md` **§4.9**, que es donde no caduca: el cajón no tiene que
   ser un lateral, ya ES un widget, y cambiar su forma es una tanda de DISEÑO.

4. **Deuda del análisis estático**: bajar la base de Larastan por familias, con `FROZEN_ERRORS` en el
   mismo commit (`#674` la bajó a 457 sin proponérselo). Los 12 de ESLint los poda quien los arregle.
5. **La promo, cuando el owner la termine** (es suyo el cuándo). La receta y **sus nueve cifras** bajaron a
   `ENTORNOS.md` §6 en `#675`, junto al despliegue que las escribió. Sin desplegar: son datos.
6. Después, **F6** (app nativa; hereda del token lo que su spec §2 nombra: Google, alta, dispositivos).
- **Del owner, HOY**: el **fin de la promo** (es suyo el cuándo) · el **ojo** que le falta a la compra de
  la T5 de F4 · **`topics`**: con la landing fuera, ¿de quién son los asuntos del formulario de contacto?
  (hoy son constante del producto, y `birthday`/`groups` son vocabulario del SECTOR).
  ▶ Contestadas y retiradas de aquí: por dónde arrancar la vía A (los platos, 23-09), cuándo se
  despliega (`#670`: no en piezas), el registro del dinero en la API (`#677`: céntimos) y **la analítica
  entera** (`#678`: dirección, «todo con la v2.0.0» y sin la pregunta tras pagar). Las TRES de §7 de la
  spec de F5 lo están desde `#639`.

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

- 🪤 **Un teléfono se cuenta por CIFRAS, no por caracteres** (T1b): la regex de PII de T1a tomaba `2026-09-23`
  por un teléfono y habría rechazado todo `date_chosen` y toda ruta con fecha; lo cazó el primer test de
  `request_failed`. Y en `track.js`, **el envío por número de eventos tiene que retirar el temporizador**: si
  no, el reintento con espera doblada no se programa nunca (`schedule` no dobla un temporizador vivo).
- 🪤 **Firmar CON el UTM dentro e ignorarlo al validar es un 403 seguro** (T1c): el HMAC cubre la query entera
  y `hasCorrectSignature()` retira lo ignorado antes de recalcularlo. Se pega DESPUÉS de firmar y se ignora
  (`EmailUtm::IGNORED_QUERY`); la spec lo decía al revés y se corrigió con el framework delante.
- 🪤 **El dispatcher instancia un observador `Clase@método` EN CADA evento** (T1a de la analítica): un
  estado capturado en `saving` no llega al `saved` salvo que el observador sea `singleton()`. Medido en
  tinker: `order_created` entraba y `order_cancelled` no. Y **`postJson` no manda cookies sin
  `withCredentials()`**: cada lote parecía un visitante nuevo y lo primero que pareció fallar fue la cookie.
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

### ❗❗ Para el SPA y la WEB (emisor: plataforma, 2026-09-23) — LA ANALÍTICA va a tocar lo compartido
- ▶ `#678` (`specs/analitica.md`): el producto gana un libro de eventos propio. **Aviso previo**, como manda
  `CONVENCIONES §10`, de lo que la T1 y la T3 tocan de vuestro reparto:
- **SPA**: `resources/js/sidebar/machine.js` (cada transición emite `step_entered`), `cajon/controller.js`
  e `index.js` (`drawer_opened`/`drawer_closed` con el paso) y `api.js` (la cabecera `X-Visitor` solo fuera
  del mismo origen). Lo mínimo, con sus tests, sin mover un píxel. Si preferís emitirlo vosotros desde
  vuestro carril, decidlo y os paso el contrato de eventos (spec §4.2).
  ✅ **T1b y T1c hechas (23-09)**: tocados `machine.js`, `sidebar/index.js`, `cajon/controller.js`, `cajon/index.js`
  y `api.js` (con tests), y los 25 `toMail()` + las vistas del correo (`new BrandedMailMessage($this)`, la UTM
  se pega tras firmar). Contrato **1.19.0** (T1e): `GET /me/export` gana `analytics` y `orders[].attribution`;
  la zona de privacidad solo lo descarga, nada que tocar. **Lo vuestro, cuando queráis**:
  los eventos de los stores (`product_chosen`, `date_chosen`, `line_added`, `identify_started`, `pay_started`…)
  por `window.JumpWeb.track(name, props)` con las `props` de `Contract::EVENTS` — cualquier otra se descarta.
- **WEB**: la T1 mete `track.js` dentro de `/cajon/paquete.js` (la landing no añade código) y atributos
  `data-track` en las vistas de la instancia; la T3 toca el banner (`layout.blade.php`, `app.js`,
  `consent-frame`), `SecurityHeaders` (orígenes por ajuste) y los textos de la política en tres idiomas, y
  sube `POLICY_VERSION`: se re-pide el consentimiento a todos.

### ❗ Para el carril de la WEB (emisor: plataforma, 2026-09-23) — dos huecos de contenido, MEDIDOS
- ▶ Publicar `/servicios` como hechos (`#672`) destapó dos cosas **tuyas**, que son de tu T6 de contenido
  y que no toco yo (`#621`). Las dos en `landing_services.specs` de `excursionescolegio`, dato del panel:
- ⚠️ **Faltan fichas en en/fr**: el español trae **tres** («Duración», «Horario», «Grupo · De 30 a 100
  alumnos») y el inglés y el francés solo **dos** — «Grupo» no existe en ninguno de los dos.
- ❗❗ **Y «Horario» NO dice lo mismo en cada idioma**, que es peor que faltar: en español es «Todos los
  días, de 8:00 a 21:30» y en inglés «Outside opening» / en francés «Hors ouverture». Son afirmaciones
  distintas sobre CUÁNDO se hacen las excursiones, y una de las dos está mal. **No sé cuál**: lo decide
  quien conozca la operación.
- ▶ Hoy no hay exposición —la página está apagada y los packs inactivos—, pero la API ya lo sirve tal cual
  a cualquier landing que lo pida.

### ❗❗❗ Para TODOS los carriles (emisor: plataforma, 2026-09-23) — `#670`: NO SE DESPLIEGA EN PIEZAS
- ▶ **Decisión del owner**: producción se queda en **v1.1.0** hasta el final del programa. **v2.0.0 es
  UNA versión grande** con todo dentro —los cuatro platos, la landing nueva, el cajón, el justificante,
  la invitación y las analíticas—, de noche y con él pendiente. Su motivo: un despliegue cuesta ATENCIÓN
  aunque salga bien, y la quiere para diseñar. **No pidáis despliegues sueltos.**
- ❗❗ **SPA, esto te toca directo: los dos interruptores de la invitación NO se encienden todavía.**
  Medido contra el git, y corrige lo que dice el `CHANGELOG`: **v1.2.0 lleva T1→T5 y NO lleva T6 ni T7**
  —tu T6 (`#708`→`#713`), tu T7 (`#714`→`#717`) y `#718` entraron DESPUÉS de la etiqueta del 19-09 a las
  09:52—. Encenderlos sobre esa etiqueta daría media feature: la página del padre sin el aterrizaje del
  anfitrión y sin los tres correos. ▶ Tu trabajo no está en cuestión: está esperando a la versión grande.
- ⚠️ **He corregido el `CHANGELOG.md` de v1.2.0**, que afirmaba «la invitación digital entra ENTERA».
  Es fichero mío (`#624`), pero la frase hablaba de lo tuyo y por eso te lo digo.
- ▶ **Contrato de la API en `1.17.0`** (`#671`→`#677`; el último, `tiers` en `/prices`): **solo añade**,
  ninguna ruta existente cambia de forma. `ApiContractTest` gana una entrada en `OPTIONAL_BY_DESIGN`; el
  cajón no se entera.

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

### ❗❗❗ Para el SPA — el OTRO ordenador (emisor: plataforma, 2026-09-23) — TE TRASPASO LA T2 DE LA ANALÍTICA
- `[DECIDIDO owner]` 23-09: **la T2, el cuadro de mando (`specs/analitica.md` §4.5), la haces TÚ**, con tu
  banda y en tu carril; aquí arranca el diseño de la landing nueva (vía A). Lee §0, §4.1, §4.5 y §7.1 (rgpd,
  seguridad y rendimiento del cuadro) de esa spec antes de tocar nada.
- **Lo que te dejo hecho (T1 ✅, `f501a990`→`4d4c3aec`)**: `analytics_sessions`/`analytics_events` en
  `Platform\Models` (sin FK, poda a 25 meses por `model:prune`), `Contract::EVENTS` (la verdad de nombres y
  `props`), `Recorder` (hechos de servidor; **nunca lanza**), el sello `orders.attribution_{channel,source,
  medium,campaign}` + `attribution` json (`NULL` = «anterior a la medición», nunca «directo»),
  `AttributionContext::touch()` (fuente/medio/campaña de una sesión, con `first_touch` a 30 días),
  `email_sent`/`email_clicked` por clave de correo, `is_bot`/`is_internal` en la sesión, `visits` = sesiones.
- **Lo que la T2 exige** está entero en §4.5 (permisos, zona horaria con test de medianoche, saneado y CSV,
  caché ≤ 90 días, `analytics_daily`/`ad_spend` nacen contigo, `EXPLAIN` en staging, `es`/`zh_CN`). Ingresos =
  Σ `paid_cents` − Σ `refunded_cents`; el canal `panel` cuenta en ingresos y **no** en el embudo web.
- 🪤 **Mis trampas**: observadores como singleton (el dispatcher instancia `Clase@método` por evento) ·
  `DB::afterCommit` corre en el acto fuera de txn · un literal `sessions` dispara `AccessRevocationTest`.

### Atendido
- Vaciado el 23-09 (lo de la SPA del 20→22-09): lo duradero vive en «retomar» 2(b) —la línea del filtro de
  reseñas es condición de la v2.0.0, y si la landing nueva llega antes va en ELLA—, en las trampas y en las specs.
