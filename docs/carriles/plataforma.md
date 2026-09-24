# Carril · Plataforma (producto e instancias)

> Máquina: **este ordenador**, `~/proyectos/jumpweb/producto` (mudado en `#648`; las instancias al lado, en
> `jumpweb/instancias/<slug>`) · Banda: **610–639 AGOTADA con `#639`** → sigue en
> **640–669 AGOTADA con `#669`** → **670–699 AGOTADA con `#699`** → **760–789 EN CURSO** (su centena,
> `decisiones/700-799.md`) · Último usado: **`#761`** · Spec: `docs/specs/producto-e-instancias.md` (§0 y §4.9) y, para lo
> que viene, **`docs/specs/isla-y-landing-nueva.md`** (⬜ borrador; `#681`→`#699`, `#760`, `#761`) · Actualizado: **2026-09-24**
> noche (la T3 cerrada; la T4 medida y planificada, §4.12 y `#761`; empieza la T4a).
> ⚠️ El techo de 32 KB aprieta a diario: **se muda, no se raspa** (es del owner; si aprieta tres veces seguidas,
> llévaselo con la medida, como el SPA en `#724`).
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
  resuelve al arrancar): basta con sesión nueva en `producto/`. ⚠️ Y en las sesiones del 20-09 y del 24-09 el
  harness no listó las skills del plugin: `/carril` y `/handoff` se siguieron a mano desde su `SKILL.md`
  (`~/proyectos/jumpweb-agente/plugins/jumpweb-agente/skills/<skill>/SKILL.md`).

## Por dónde retomar, en orden

▶▶ **AHORA (24-09 noche)**: sistema de diseño nuevo dentro (`#697`); **T3 cerrada** (`#698`); **T4** (Kids y Jump,
spec §4.12, `#761`): **T4a·1 ✅** (reglas de «con un adulto» y plazo, contrato 1.26.0; T4a·2 y ·3, del SPA: pedidas)
→ **T4b·1 ✅** (las páginas que declara el paquete: `/kids` y `/jump` en local con el layout limpio; queda **·4**, el
estado del `<body>`, esperando al SPA) → **T4c las piezas (la SIGUIENTE)** → T4d la calculadora → T4e la isla viva →
T4f la sonda; después, las tres páginas de la FIESTA (avisando al SPA ANTES). La prueba del owner en local, primero.
⚠️ BD LOCAL con los valores de `#699`/`#761` (lo de antes, en el commit de la T4a·1); en PRODUCCIÓN, el owner.

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
   ▶ **LA ANALÍTICA es ENTERA del carril del SPA** (`#735`, 24-09): la T1 la cerró este carril (`#678`, `#680`;
   su historia, en `analitica.md` §4.8) y su traspaso está atendido. ⚠️ Queda el OJO del owner en la fuente del
   pedido manual.
   ▶▶▶ **LA LANDING NUEVA** (`specs/isla-y-landing-nueva.md`, ⬜): el sistema «Saltia» (Claude Design,
   `33397ca2…`), con su referencia byte a byte en `instancias/playjump/diseno/`. `[DECIDIDO owner]` 24-09: `#681`
   las páginas en **Blade dentro de la instancia** · `#682` la **isla**, segunda carcasa del producto, apagada
   por defecto · `#683` Kids y Jump primero, Lucide, pasos compartidos; Bizum, Apple y el aviso en la v2.0.0 ·
   `#684` promociones (modelo a iterar) · `#688` la compra guarda la hora **al pagar** y dice «Esta cuenta ya
   existe» **al enviar**. ✅ T0 (§1.6) · ✅ T1 (§4.8: 82/82 páginas y 84 iconos a 0 px) · ✅ T2 (§4.9: la isla,
   52/52 a 0 px, `CE-6` sin excepción; en/fr de `lang/*/isla.php` a revisar por el owner). ▶ **`#697`** (24-09
   noche): el zip nuevo (la fiesta, Mi cuenta, correos) entra por `diseno/actualizar.py`; la isla vuelve a 0 px con
   el selector de plan rehecho (54/54, piezas 18/18); el censo de la fiesta, en §4.11. El zip es la ÚNICA fuente,
   sin contraste con el vivo (`#760`: la cuenta de Claude Design es otra); `actualizar.py` rechaza uno más viejo.
   ▶▶ **T3, la compra ✅** (§4.10; la historia de cada tanda vive allí): T3a→T3d (`#689`→`#691`) → T3e (`#692`): `#693`
   la carcasa elegible · `#694` la isla compra hasta el banco · `#695` «Entra» y Google · `#696` los cumpleaños con
   señal · `#698` la hora que se llena al pagar y la sonda. Queda: la sonda en STAGING, con el ensayo de la v2.0.0.
   Probar la isla: `sidebar.shell = isla` en local, **`scripts/sonda-isla.mjs [390|1280]`** (monta y borra su página;
   19/19), `public/_isla-prueba.html` para el ojo (se BORRA antes de desplegar: guarda 9) y el banco
   `scripts/banco-compra.php` (52/54: «entrar» difiere por `#695`; se juzga CON `--rehacer`); tras una prueba
   del agente el ajuste vuelve a como estaba (hoy, `isla`: abajo).
   ▶ `lint:js` con `isla/` (el SPA: «hazlo tú», 24-09; hoy limpia a mano): `package.json`, la cadena de
   `StaticAnalysisGateTest` y las tres de `scripts/mutar-analisis-estatico.sh`, en su commit y con `/mutar`.
   ❗ **LOCAL, preparado para que el OWNER pruebe la isla (24-09 noche) — no deshacer sin él**: `sidebar.shell =
   isla` (puesto por el panel), `public/_isla-prueba.html` con un botón por cada entrada (se BORRA antes de
   desplegar: guarda 9) y la **invitación digital ENCENDIDA en los packs 105/106** (solo aquí; en producción no
   se toca hasta la v2.0.0). Medido de verdad por la isla: la pasarela pública de pruebas (pide ya el TITULAR;
   3DS simulado; vuelta firmada → «¡Reservado!», `storage/app/sonda-isla-pasarela.mjs`) y Google, que acepta la
   vuelta de `localhost:8081` y no la del 80 del contenedor. ⚠️ La vuelta del banco cae en la portada de hoy: ahí
   la isla se ve NEUTRA (sin las hojas de Saltia) hasta la T4. La contraseña del admin local la tiene el owner.
   Los datos que faltaban (T0), decididos en `#699`: sin precio de antes ni oferta; el plazo de cambio y
   cancelación, POR PRODUCTO (cumpleaños 3 días naturales); los 90 cm con adulto, en la ZONA; el destacado, el
   `featured` del producto. ⚠️ Tras un `pull`: `cp -r ../instancias/playjump/publico/instancia
   public/` y `php artisan migrate` (las del SPA llegan SIN aplicar aquí: la de `experiments` dio un 500); los bancos se rehacen con `tema/lote-fichas.py` y `scripts/banco-{isla,piezas,compra}.php` (su lado B, antes).
   ⚠️ **La web nueva cambia las URLs** (§1.5): cada ruta vieja necesita su 301.
   ⚠️⚠️ **Lo que dejaron los platos y vale para lo que venga** (delegar en el servicio, el filtro tras el respaldo
   de idioma, lo que decide la maqueta no es un hecho, `BarImage`, una regla compartida cambia las dos
   superficies): `instancia-y-landing-fuera.md` §4.1 y §4.1.ter; la subcadena que acusa al fixture, `TESTING.md` §2.ter.
   ▶ **Deuda declarada** (en la spec): `birthday`/`groups` son vocabulario del SECTOR (`ContactTopics`) ·
   `price_table`, `nav_subtitle` y `show_in_nav` se retiran con la tanda de la PÁGINA, no antes · un
   producto activo sin NINGÚN precio sale de `/prices` sin la clave `prices` que el contrato exige (`#677`).
   ⚠️ El §0 de la spec está a **1.973 de 2.048 B**: de ahí solo se toca la línea de «Estado». La **T5**
   (cortar v2.0.0) es el final del programa entero, no de esta fase (`#670`).

   ▶ **Lo que se le contestó al owner sobre la FORMA del cajón y sobre los widgets** (medido el 21-09)
   vive ahora en `specs/cajon-empaquetable.md` **§4.9**, que es donde no caduca: el cajón no tiene que
   ser un lateral, ya ES un widget, y cambiar su forma es una tanda de DISEÑO.

4. **Deuda del análisis estático**: bajar la base de Larastan por familias, con `FROZEN_ERRORS` en el
   mismo commit (`#674` la bajó a 457 sin proponérselo). Los 12 de ESLint los poda quien los arregle.
5. **La promo, cuando el owner la termine** (es suyo el cuándo). La receta y **sus nueve cifras** bajaron a
   `ENTORNOS.md` §6 en `#675`, junto al despliegue que las escribió. Sin desplegar: son datos.
6. Después, **F6** (app nativa; hereda del token lo que su spec §2 nombra: Google, alta, dispositivos).
- **Del owner, HOY**: **probar la isla en local** (sus accesos se le dieron en el chat del 24-09) · en su diseño,
  **cumpleaños a 3 días** y no 5 (`#699`), y bajar el zip DESPUÉS del último cambio (`#760`) · pasar a su
  diseño las dos de `#695` («Entra» solo con correo; la «G» de Google) · en el panel de PRODUCCIÓN, el campo del
  homenajeado de los dos packs a «formulario de invitados» (`#692`; en local ya está) · el **modelo de las promociones**
  (`#684`: dónde va cada etiqueta en la landing) · el **fin de la promo** (es suyo el cuándo) · el **ojo** que le falta a la compra de la T5 de F4 · **`topics`**: con la landing fuera, ¿de quién son los asuntos del formulario de contacto?
  (hoy son constante del producto, y `birthday`/`groups` son vocabulario del SECTOR).
  ▶ Contestadas y retiradas de aquí: por dónde arrancar la vía A (los platos, 23-09), cuándo se
  despliega (`#670`: no en piezas), el registro del dinero en la API (`#677`: céntimos) y **la analítica
  entera** (`#678`: dirección, «todo con la v2.0.0» y sin la pregunta tras pagar). Las TRES de §7 de la
  spec de F5 lo están desde `#639`.

## Ficheros de este carril

`CLAUDE.md` · `docs/ESTADO.md` · `docs/00-REFACTOR.md` · `docs/CONVENCIONES.md` · `docs/README.md` ·
`docs/DECISIONES.md` y la estructura de `docs/decisiones/` y `docs/carriles/` · `scripts/docs-check.sh` ·
`.githooks/pre-push` · `scripts/huella-enrutador.py` · `scripts/partir-decisiones.py` · `scripts/deploy.sh` (las
guardas 8 y 9) · `scripts/mutar-guarda8.sh` · `CHANGELOG.md` · `phpstan.neon` · `phpstan-baseline.neon` ·
`eslint.config.js` · `eslint-suppressions.json` (la poda quien arregla) · `scripts/mutar-analisis-estatico.sh` ·
**LA ISLA Y LA LANDING NUEVA** (`#681`, `#682`): la spec, la isla `resources/js/isla/**`, sus bancos y sondas
(`scripts/banco-{isla,piezas,compra}*`, `scripts/pixel.mjs`, `scripts/sonda-{embudo,isla}.mjs`), `sidebar/reanudar.js`,
`sidebar/marca-compra.js`, `app/Http/Sidebar/PurchaseResume.php` y
las vistas nuevas de `instancias/playjump/web/`; ⚠️ **el motor del cajón es del SPA**: se le avisa ANTES de tocarlo ·
`StaticAnalysisGateTest` · `Tests\TestCase::be()` · **el token y el cajón empaquetado**, cuyos ficheros
enumera cada spec (`token-bearer.md`, `cajon-empaquetable.md` §0): el emisor, el arranque, la apertura, la
carcasa, la hoja GENERADA `public/css/cajon.css` y sus cuatro arneses · `scripts/huella-maquetacion.mjs` ·
**EL PAQUETE DE INSTANCIA** (`#647`→`#656`: `config/instancia.php`, `Http\Instancia\*` —`InstanceViews` con el
CONTRATO DE VISTAS; desde la T4b, `InstancePages` y `PageFacts`, con `InstancePageController`, `components/pagina`
y `InstancePagesTest`—, `plantilla/`, `phpunit.xml` (`INSTANCIA_RUTA`
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

- ▶ Las trampas de la T1 de la analítica (PII por cifras, UTM tras firmar, observadores `singleton()`,
  `withCredentials()`) se MUDARON con ella: viven en `carriles/spa.md`, `analitica.md` y `#680`.
- 🪤 **`DesignSync` corta a 256 KiB y el README del diseño se contradice** (`isla-y-landing-nueva.md` §0): el
  vídeo y el logotipo, del original; y mandan los ficheros, no el índice del README.
- El harness en modo «auto» ordena preferir Bash a Read/Edit/Write; manda la regla 8 de `CLAUDE.md`.
- **El clasificador «auto» y producción**: deniega escribir hooks, manifiestos y reglas del plugin salvo con las
  reglas `allow` de `#626`; con la orden del owner EN EL TURNO deja pasar escrituras por `ssh` y el `--go`; deniega
  el ensayo en seco con la salida redirigida a fichero («Blind Apply»). No se rodea: se le pide al owner.
- **Un guion de datos contra producción**: su receta (valor esperado por fila, doble pasada, tinker por `ssh`), en
  `ENTORNOS.md` §5.
- **Dos carriles empujando a la vez**: el gate tarda ~3 min y el remoto se mueve; un push puede salir RECHAZADO
  con el gate en verde (18-09, dos veces). `pull --rebase`, **re-medir la suite sobre el árbol fusionado**,
  corregir el trailer con `--amend` y volver a empujar. La etiqueta se lleva `main` ENTERO.
- **El código de salida de una tarea en segundo plano con `; tail` al final es el del `tail`**: los de `pull` y
  `push` se imprimen con `echo "… exit=$?"` y se LEEN.
- **Los cuatro ficheros de doc viven pegados a su techo** (enrutador 12 KB, tracker 16, carril 24, §0 de una
  spec 2). Cada tanda obliga a rascar, y rascar tres veces seguidas es la señal de que algo tiene que MUDARSE
  a su spec —no de que el techo esté mal—: así se fueron las seis trampas del cajón a `cajon-empaquetable.md`
  §4.8 y el historial del menú a `instancia-y-landing-fuera.md` §4.1.
- ▶ **Las trampas de MEDIDA y de ARNESES viven en `TESTING.md` §2.octies** (mudadas el 24-09): la captura
  asentada, el gate saturado, el arnés que restaura por copia, la línea base antes del controlador, los
  manifiestos de Vite, el «NO SE APLICÓ», la salida truncada, la base que cruza un umbral del horario y la guarda
  transversal que se juzga con la SUITE ENTERA (`TestCase::be()`).
- **`SHELL` es una variable del propio bash**: llamar así a una ruta en un guion se la cambia a todo lo que se
  lance después. En `mutar-cajon-apertura.sh` se llama `CARCASA`.
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
- **Taquilla cobra de la misma tabla `prices` que la web**, y el pedido manual y ocho servicios del núcleo llaman
  a `priceCents()`: una rebaja «solo online» como dato es imposible y un descuento por canal es `CRITICAL_RE`.
- `rm -rf` está en el deny del repo y un comando compuesto que lo lleve se deniega entero: `git rm -r` para lo
  versionado, carpeta nueva para lo demás. `claude plugin details` no acepta `--plugin-dir`.
- 💥💥 **Sacar ficheros del repo** (`#663`): `git rm --cached` REGISTRA un borrado que el `pull --rebase` aplica
  al disco (se copian FUERA antes), y lo que dependía de ellos en disco sale verde aquí y rojo en el otro
  ordenador (la suite, con y sin ellos). Las dos, enteras, en `paquete-de-instancia.md` (la trampa que costó la tanda).
- **«The command 'docker' could not be found» es Docker Desktop APAGADO**: se arranca desde WSL con
  `"/mnt/c/Program Files/Docker/Docker/Docker Desktop.exe"` en segundo plano y `until docker info`.
- Una etiqueta no pasa por el gate (`pre-push` solo mira `refs/heads/main`): `/release` exige que el commit ya
  esté en `origin/main`; y un test sobre una «casi versión» tiene que EMPUJARLA antes de medir.

## Buzón

### ❗❗ Para el SPA — el OTRO ordenador (emisor: plataforma, 2026-09-24) — LA ISLA sobre TU motor, y LA FIESTA
- ▶ `#682`: la **isla** va en el producto como **segunda carcasa** sobre el motor del cajón, apagada por defecto, y
  la construye ESTE carril (spec `isla-y-landing-nueva.md` §4.1, §4.3 y §4.4). Lo que haga falta del motor te lo
  propongo AQUÍ antes de tocarlo. Tus respuestas del 24-09 (las cookies en el mismo almacén y `lint:js` «hazlo tú»)
  están recogidas en la spec §4.11; la T5c, en tu `#738`.
- ❗❗ **`#697` (24-09 noche), AVISO PREVIO: las tres páginas de la FIESTA del sistema nuevo las viste ESTE carril**
  (`[DECIDIDO owner]`). El zip nuevo trae montadas la lista de invitados, la invitación con su recibo y la
  autorización; el owner decidió que las vista este carril con el método de Saltia (referencia, banco de píxeles,
  piezas portadas). **Su lógica y sus controladores siguen siendo TUYOS.** No empieza hasta después de la T4 (Kids
  y Jump); antes de tocar un fichero tuyo (vistas `.gf-*`, controladores, `lang/guestform.php`…) te dejo AQUÍ la
  lista. El censo, en spec §4.11: casi todo HAY (los tres temas, adoptar al guardar, el borrador, el corte por
  complemento); lo que FALTA (pegar una lista, combos por adultos, la tarta por raciones, «Crear mi QR» desde el
  recibo, «Avísame de fechas», el QR de la fiesta en la puerta…) se le lleva al owner y lo hablamos aquí antes de
  que nadie lo empiece. Van sin isla ni menú: encaja con tu `#739`. Si quieres hacer tú algún trozo, dilo.
- ▶▶ **24-09 · T3d HECHA (`#691`) en tus ficheros, con el visto bueno del owner.** La secuencia de compra
  salió LITERAL de `sections/PurchaseSection.vue` a `sidebar/usePurchaseFlow.js` (stores arriba, mismo orden de
  registro); en la sección queda lo del cajón (banda, pie, pausa, «Volver», cuenta, `defineExpose`) y su
  plantilla byte a byte: 464 → 89 líneas y 0 llamadas. Reapuntados: `SidebarSignupContextTest` (mira el
  módulo), `SidebarSetupBindingsTest` (escanea también el cuerpo de todo `export function use…`),
  `SidebarComponentBudgetTest` (89/0) y el comentario de `foot.test.js`. ESLint 10 → 8 (tus dos `no-unused-vars`
  de la sección no viajaron) y el chunk, techo **291** (+1.256 B: las claves que el composable devuelve). En
  navegador, la compra entera con el build de antes y el de después da la MISMA traza (13 pasos, 39 peticiones:
  `scripts/sonda-embudo.mjs`, úsala tú también). ⚠️ **Tuyo, heredado sin tocar**: `loadOutcome()` lee
  `props.locale` y la sección no la declara → la hora de retención del paso 10 sale siempre en formato `es`.
  Tus eventos de compra de la analítica van al módulo: contarán en el cajón y en la isla.
- ▶▶ **24-09 · T3e·5 (`#696`), lo que tocó de lo tuyo, sin cambiar una conducta del cajón**:
  `outcome.js::confirmationLine()` lleva además `guest_form_url`, `guest_count_deadline` e `invitation_url` (la
  tarea de la fiesta en «Listo»), con su caso; `usePurchaseFlow` devuelve `configuracion` (el `GET /config` que ya
  pedía al montarse). Contrato **1.25.0**: `OrderItem.guest_count_deadline` e `invitation_url` (requeridos,
  anulables) y `PublicConfig.guest_count_cutoff_hours`; con ellos tu «Mis reservas» podría decir el plazo sin
  calcularlo. Traza del cajón, idéntica. El motor, 294,70: techo **295**.
- ▶▶ **24-09 noche · T3e·6 (`#698`), dos líneas en lo tuyo, solo añaden**: `pay.js::confirmError` devuelve también
  el `code` del «no», y `usePurchaseFlow::confirmReservation` DEVUELVE el resultado cuando el servidor dice que no.
  El cajón no lee ninguno de los dos; la isla, con `line_sold_out`, ofrece las horas cercanas. Su caso, en
  `pay.test.js`. ⚠️ **Y la BANDA**: la mía (670–699) se acaba en `#699`; **reclamo 760–789** (índice de
  `DECISIONES.md`). Cuando agotes la 730–759, la tuya sería **790–819**.
- ❗ **24-09 noche · AVISO PREVIO de la T4a·3 (`#699`, `#761`)**: tu paso de pagar dice `tickets.pay_policy` —«Cumpleaños
  y excursiones: te devolvemos la señal si cancelas con **5 días**»— y el owner fijó **3 días naturales** (mandan las
  condiciones). Desde el contrato **1.26.0** el plazo es un dato POR PRODUCTO (`/catalog/products`: `cancellation
  {cutoff_hours, written}`; y la zona gana `escort`, las reglas de «con un adulto»). Te propongo que el paso lea
  `cancellation.written` de sus líneas en vez de la frase fija; no lo toco sin tu visto bueno. Si prefieres hacerlo
  tú, dilo.
- ❗ **Y dos PETICIONES para Kids y Jump (T4)**: (1) **tu T2·9** de `google-business-profile.md` —las reseñas en
  `/social-proof`, con la selección de la Ómnibus y sin avatares (`#616`; qué caras y fotos entran lo decide el owner
  antes del contrato)—: la pieza 5 de las páginas nuevas las pinta; la construyo contra el banco y la conecto cuando
  esté. (2) **AVISO PREVIO de lo compartido (T4b)**: el layout limpio de las páginas nuevas necesita el MISMO estado
  del `<body>` que `components/layout.blade.php` (las `data-cookie-*`, `data-analytics-*`, `data-pixel-*`). Propongo
  sacarlo TAL CUAL a un componente (`site/body-state`) que usen los dos layouts, sin cambiar un atributo; no lo toco
  hasta que lo veas.

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

### Atendido
- Vaciado el 23-09 (lo de la SPA del 20→22-09): lo duradero vive en «retomar» 2(b) —la línea del filtro de
  reseñas es condición de la v2.0.0, y si la landing nueva llega antes va en ELLA—, en las trampas y en las specs.
- **Retirados el 24-09** el traspaso de la analítica y su aviso previo: el SPA los atendió y se quedó la
  analítica ENTERA (`#735`); lo que decían vive en `analitica.md` y en `carriles/spa.md`.
- **Retirados el 24-09 noche** los avisos de T3e·2b, T3e·3 y T3e·4 (`#693`→`#695`) y los avisos previos de la isla
  (cookies, `lint:js`, el A/B): el SPA los atendió o contestó en su buzón; su detalle, en la spec §4.10 y §4.11.
