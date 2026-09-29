# Carril · Plataforma (producto e instancias)

> Máquina: **este ordenador**, `~/proyectos/jumpweb/producto` (mudado en `#648`; las instancias al lado, en
> `jumpweb/instancias/<slug>`) · Banda: **610–639 AGOTADA con `#639`** → sigue en
> **640–669 AGOTADA con `#669`** → **670–699 AGOTADA con `#699`** → **760–789 AGOTADA con `#789`** → sigue en
> **820–849** (dada de alta en `DECISIONES.md` el 27-09; centena `decisiones/800-899.md`) · Último usado: **`#842`** · Spec: `docs/specs/producto-e-instancias.md` (§0 y §4.9) y, para lo
> que viene, **`docs/specs/isla-y-landing-nueva.md`** (⬜ borrador; `#681`→`#699`, `#760`→`#789`, `#820`→`#842`) · Actualizado: **2026-09-29**
> (PORTADA, CUMPLEAÑOS, COLEGIOS, VISÍTANOS y NORMAS ✅; sigue la T6f).
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
- ✅ **El plugin, reinstalado en la ruta nueva el 27-09** (`CAPA-DE-AGENTE.md` §4): una sesión NUEVA ya tiene `/carril`.

## Por dónde retomar, en orden

▶▶▶▶ **EL ORDEN HASTA LA v2.0.0** (`#789`, del owner). **HECHO, con el ✅ del owner**: (0) la primera pantalla, (1) la
conversión del zip tercero y (2) la T5, Mi cuenta (spec §4.13–§4.16). ▶ **EN MARCHA: (3) T6** (spec §4.17), con el ojo del
owner en cada página: PORTADA (`#827`→`#831`, `sonda-portada.mjs`), CUMPLEAÑOS (§4.18, `#832`→`#836`, `sonda-cumpleanos.mjs`),
COLEGIOS (§4.19, `#837`→`#841`, T6c·5–6; `sonda-colegios.mjs` 56/56, `mutar-sonda-colegios.sh` 11/11, `mutar-escalera.sh` 7/7;
en LOCAL, 91 plantillas INFERIDAS para `excursiones`) y VISÍTANOS (§4.20, T6d·1–3; `sonda-visitanos.mjs` 26/26,
`mutar-sonda-visitanos.sh` 8/8; el panel gana donde habla y donde calla, el brief) y NORMAS (§4.21, `#842`, T6e·1–3: `seguridad`
OCUPA `/normas`; `sonda-normas.mjs` 37/37, `mutar-sonda-normas.sh` 16/16, la primera pantalla con el veredicto del mockup).
**SIGUE la T6f**: los 301 (`/contacto` → `/visitanos`, `/servicios` → `/colegios`…) y retirar las vistas viejas de la instancia
(§1.6.4; también `normas.blade.php`), con la sonda de todas (la T4f de Kids y Jump va aquí). ❗ **Y el `#792` del SPA, SIN
hacer**: la fecha de nacimiento del titular en el alta de la isla (`formularioDeAlta`) y en «Tus datos» (su pieza
`steps/BornOnField.vue` y sus textos, ya en el montaje). **Del owner, en PRODUCCIÓN**: el icono y el nivel de cada norma, y
dar de alta las del brief que falten (calentamiento, espuma, volteretas dobles).
Pendiente: la vuelta de Google con una excursión, sin verificar. ⚠️ La entrada `isla/hoja/montar.js` NO importa nada
compartido (`#841`); la calculadora, a 185,80 de 186.
⚠️ `sonda-portada` 13/14 («Reservar» de la isla tras «Míralo», 17:17): igual con el `HEAD` (control): investigar aparte. La ISLA la
repiensa el owner con Claude Design: no atar nada nuevo a ella. **Para iterar con el owner** (no ahora): la isla «muy sola» y
el «Reservar» solo en la isla del móvil (A/B propuesto). Tras la T6 → **(4)** Bizum, Apple (entra) y el día liberado →
material y revisión del owner, al final (los datos: playjump.es). **Los correos del sistema y la puerta, del SPA**, en
paralelo. ❗ **Sin atender, del buzón del SPA (27-09, `#758`)**: la isla emite `availability_missing` como el cajón, con SUS
funciones (`sidebar/missing.js`, `calendar.js::missingMonths`). La T6 HEREDA: toda página nueva usa `video-hero` SIN `height` y
entra en `sonda-primera-pantalla.mjs` (el mockup de la portada NO pinta el aviso de cookies: su primera visita no se compara).
⚠️ **Trampas vivas**: (a) `sonda-isla` PAGA con la cuenta de pruebas y rompe `sonda-cuenta` si corre antes (sus pedidos, a
`sonda-compra@` entre las dos, `TESTING.md` §2.octies); (b) el tracker está a ~20 B de su techo (16 KB): la próxima línea
obliga a MUDAR algo de él (a su spec); (c) `sonda-cuenta` monta «HOY» solo antes de las 20:00 del parque: 125 checks por
ancho, después 118 (con `SONDA_SOLO=t5f`, tras entrar, solo la T5f); (d) **Vue 3.5 reevalúa un `computed` del que se
depende FUERA del `try` de quien lo lee** (`isDirty` → `refreshComputed`): lo que no deba tumbar a quien depende se protege
DENTRO de su `computed` (`seguro.js`, `seguro.test.js`, spec §4.13).
▶▶▶ **Hecho el 25→27-09** (spec §4.12, §4.14): la T4, Z1→Z4 (`#780`→`#788`). Marcas: en LOCAL `payment.marks` =
`bizum,visa,mastercard`; en PRODUCCIÓN, el owner. ⚠️ Tras tocar `instancias/playjump/publico/`, copiarlo a
`public/instancia` (el producto sirve la copia).
⚠️ En PRODUCCIÓN, el owner pone en el panel el aviso de los calcetines, «se devuelve la señal» de los packs y el TRAMO DE
EDAD de cada entrada (de él depende `#825`; en LOCAL, puestos). Lo que la sonda toca de `probe-card@`: `TESTING.md` §2.octies.
▶ **HECHO (25/26-09)**: la **T4** (spec §4.12; banco de la isla 60/60; `#768`: sin bancos por tanda) y el encargo del
owner del 25-09 —promociones 🟦 T1 (`promociones.md` §8; en LOCAL, dos ofertas de muestra), el play de los vídeos 🟦
(falta el MATERIAL; en LOCAL, una muestra WebM) y las reseñas 🟦 (`#771`, `#772`: 18 publicadas, Places retirado;
⚠️ al desplegar, `playjump-curado.json`)—: a los tres les falta el OJO del owner. ❓ Suya: la línea Ómnibus bajo las reseñas.
▶ La T4f (la sonda de Kids y Jump) va con la T6. La FIESTA, del SPA (`#765`). ⚠️ BD LOCAL con los valores de
`#699`/`#761`; en PRODUCCIÓN, el owner.

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
   ✅ **(c) El PLANIFICADOR en `deploy.sh`**: el SPA subió la cifra a **10** con la tarea de las encuestas
   (`0d9a54db`, avisado); medido en local el 25-09, `schedule:list` = 10. Es propiedad del repo, no del servidor.
3. **F5 · EL MENÚ DE HECHOS, COMPLETO ✅** (`#640`→`#677`): su historia y lo que hay que saber del estado (las
   vistas en la instancia, la suite SIN paquete, el contrato de DATOS y su `CONTRATO` = 2, el trinquete de CSS
   huérfano, `zones` que borra columnas con la v2.0.0), en `instancia-y-landing-fuera.md` y `paquete-de-instancia.md`
   (mudado de aquí el 27-09: era copia). ⚠️ **Si añades CSS, su consumidor nace con él.**
   ▶ **LA ANALÍTICA es ENTERA del carril del SPA** (`#735`, 24-09): la T1 la cerró este carril (`#678`, `#680`;
   su historia, en `analitica.md` §4.8) y su traspaso está atendido. ⚠️ Queda el OJO del owner en la fuente del
   pedido manual.
   ▶▶▶ **LA LANDING NUEVA** (`specs/isla-y-landing-nueva.md`, ⬜; «Saltia», byte a byte en `instancias/playjump/diseno/`):
   sus decisiones (`#681`→`#688`, `#697`, `#760`) y T0→T2, en la spec (§0, §1.6, §4.8, §4.9, §4.11; mudado de aquí el
   27-09). El zip entra SOLO por `diseno/actualizar.py`. ❓ El en/fr de `lang/*/isla.php`, a revisar por el owner.
   ▶▶ **T3, la compra ✅** (§4.10, `#689`→`#698`: cada tanda, allí). Queda: la sonda en STAGING, con el ensayo de la v2.0.0.
   Probar la isla: `sidebar.shell = isla` en local, **`scripts/sonda-isla.mjs [390|1280]`** (monta y borra su página;
   19/19), `public/_isla-prueba.html` para el ojo (se BORRA antes de desplegar: guarda 9) y el banco
   `scripts/banco-compra.php` (52/54: «entrar» difiere por `#695`; se juzga CON `--rehacer`); tras una prueba
   del agente el ajuste vuelve a como estaba (hoy, `isla`: abajo).
   ❗ **LOCAL, preparado para que el OWNER pruebe la isla (24-09 noche) — no deshacer sin él**: `sidebar.shell =
   isla` (puesto por el panel), `public/_isla-prueba.html` con un botón por cada entrada (se BORRA antes de
   desplegar: guarda 9) y la **invitación digital ENCENDIDA en los packs 105/106** (solo aquí; en producción no
   se toca hasta la v2.0.0). La pasarela y Google medidos por la isla: `TESTING.md` §2.octies. ⚠️ La vuelta del banco cae en la portada de hoy: ahí
   la isla se ve NEUTRA (sin las hojas de Saltia) hasta la T4. La contraseña del admin local la tiene el owner.
   Los datos de la T0, en `#699`. ⚠️ Tras un `pull`: `cp -r ../instancias/playjump/publico/instancia
   public/` y `php artisan migrate` (las del SPA llegan SIN aplicar aquí: la de `experiments` dio un 500); los bancos se rehacen con `tema/lote-fichas.py` y `scripts/banco-{isla,piezas,compra}.php` (su lado B, antes).
   ⚠️ **La web nueva cambia las URLs** (§1.5): cada ruta vieja necesita su 301.
   ⚠️⚠️ **Lo que dejaron los platos y vale para lo que venga** (delegar en el servicio, el filtro tras el respaldo
   de idioma, lo que decide la maqueta no es un hecho, `BarImage`, una regla compartida cambia las dos
   superficies): `instancia-y-landing-fuera.md` §4.1 y §4.1.ter; la subcadena que acusa al fixture, `TESTING.md` §2.ter.
   ▶ **Deuda declarada** (en la spec): `birthday`/`groups` son vocabulario del SECTOR (`ContactTopics`) ·
   `price_table`, `nav_subtitle` y `show_in_nav` se retiran con la tanda de la PÁGINA, no antes · un
   producto activo sin NINGÚN precio sale de `/prices` sin la clave `prices` que el contrato exige (`#677`).
   ⚠️ El §0 de la spec está a **1.973 de 2.048 B**: de ahí solo se toca la línea de «Estado». La **T5**
   (cortar v2.0.0) es el final del programa entero, no de esta fase (`#670`). La FORMA del cajón: `cajon-empaquetable.md` §4.9.

4. **Deuda del análisis estático**: bajar la base de Larastan por familias, con `FROZEN_ERRORS` en el
   mismo commit (`#674` la bajó a 457 sin proponérselo). Los 12 de ESLint los poda quien los arregle.
5. **La promo, cuando el owner la termine** (es suyo el cuándo). La receta y **sus nueve cifras** bajaron a
   `ENTORNOS.md` §6 en `#675`, junto al despliegue que las escribió. Sin desplegar: son datos.
6. Después, **F6** (app nativa; hereda del token lo que su spec §2 nombra: Google, alta, dispositivos).
- **Del owner** (27-09 noche, `#789`): la cuenta de Apple Developer (Apple entra en la v2) · aceptar UNA vez la confianza
  de esta carpeta en una terminal (`claude` aquí: sin ella, las `allow` del repo no valen fuera de VSCode) · **para su
  REVISIÓN FINAL** (los datos, de playjump.es): los «5 días» de la duda «¿Puedo cambiar o cancelar?» contra los 3 de
  `#699` y las excursiones sin plazo · el horario en/fr de las excursiones (vale el español) · el en/fr de `pay_terms` · la
norma «Zona Kids: de 4 a 8 años» contra las entradas, de 4 a 7 (en LOCAL; `/normas` pinta las dos) · la
nota de Colegios «Por la mañana, antes de que el parque abra» junto a «8:00–21:30» (en `#534`, producción: 8:00–15:00) ·
«¿Cuántos profesores?» y las alergias de las excursiones (fase de después) no se piden a nadie: sin lista de invitados no
hay formulario (`#839`); pasarlos a la reserva es dato del panel. Y:
  **probar la isla en local** (sus accesos se le dieron en el chat del 24-09) · la **captura del
  MAPA** de la pieza 6 (sin ella, solo el panel: spec §4.12 ·4) · en el panel, la tarifa normal como «De lunes a
  jueves» y las atracciones en el orden del brief (§4.12 ·8b) · revisar el en/fr de Kids y Jump · en su diseño,
  **cumpleaños a 3 días** y no 5 (`#699`), y bajar el zip DESPUÉS del último cambio (`#760`) · pasar a su
  diseño las dos de `#695` («Entra» solo con correo; la «G» de Google) · en el panel de PRODUCCIÓN, el campo del
  homenajeado de los dos packs a «formulario de invitados» (`#692`; en local ya está) · el **fin de la promo** (es suyo
  el cuándo) · el **ojo** que le falta a la compra de la T5 de F4 y a las promociones.
  ▶ Contestadas y retiradas de aquí: la vía A (los platos, 23-09), cuándo se despliega (`#670`), el dinero en la API
  (`#677`), la analítica (`#678`), el modelo de las promociones (`#770`), caras y fotos de las reseñas (`#771`) y, el
  27-09, los `topics` (dato de cada instalación), `/contacto`, la Ómnibus, `Medir` y el reparto (`#789`); el 28-09, «Tus
  datos» con sesión: SE QUEDA (solo si la cuenta nunca firmó o le falta el teléfono en un pack, `#785`).

## Ficheros de este carril

`CLAUDE.md` · `docs/ESTADO.md` · `docs/00-REFACTOR.md` · `docs/CONVENCIONES.md` · `docs/README.md` ·
`docs/DECISIONES.md` y la estructura de `docs/decisiones/` y `docs/carriles/` · `scripts/docs-check.sh` ·
`.githooks/pre-push` · `scripts/huella-enrutador.py` · `scripts/partir-decisiones.py` · `scripts/deploy.sh` (las
guardas 8 y 9) · `scripts/mutar-guarda8.sh` · `CHANGELOG.md` · `phpstan.neon` · `phpstan-baseline.neon` ·
`eslint.config.js` · `eslint-suppressions.json` (la poda quien arregla) · `scripts/mutar-analisis-estatico.sh` ·
**LA ISLA Y LA LANDING NUEVA** (`#681`, `#682`): la spec, la isla `resources/js/isla/**`, sus bancos y sondas
(`scripts/banco-{isla,piezas,compra}*`, `scripts/pixel.mjs`, `scripts/sonda-{embudo,isla,cuenta,movimiento,isla-movimiento,banco-movimiento,isla-rendimiento,compra-directa}.mjs`,
`scripts/sonda-cuenta-datos.php`, `scripts/mutar-{t5f,hijos-de-producto}.sh`), `sidebar/reanudar.js`,
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
- ▶ **Mudadas el 27-09** (el techo): el clasificador y `rm -rf`, a `CAPA-DE-AGENTE.md` §6; Sanctum y
  `currentAccessToken()`, a `token-bearer.md` §4.2; taquilla y la tabla `prices`, a `promociones.md` §1.1.
- **Un guion de datos contra producción**: su receta (valor esperado por fila, doble pasada, tinker por `ssh`), en
  `ENTORNOS.md` §5.
- ▶ **Las trampas de EMPUJAR y de git** (dos carriles a la vez, el código de salida con `; tail`, la etiqueta sin gate,
  sacar ficheros del repo) viven en `CONVENCIONES` §10 punto 9, y **la de los techos de doc**, en su §11 (mudadas el 26-09).
- ▶ **Las trampas de MEDIDA y de ARNESES viven en `TESTING.md` §2.octies** (mudadas el 24-09): la captura
  asentada, el gate saturado, el arnés que restaura por copia, la línea base antes del controlador, los
  manifiestos de Vite, el «NO SE APLICÓ», la salida truncada, la base que cruza un umbral del horario y la guarda
  transversal que se juzga con la SUITE ENTERA (`TestCase::be()`), la variable `SHELL` de bash, el SUELO de la API
  que agota una sonda repetida, `playwright-core` podado, ESLint con el gate corriendo y Docker Desktop apagado.
- **Mover código que unas guardas leen como TEXTO**: se mueve TAL CUAL, se re-apunta cada guarda al fichero
  nuevo y se MUTA allí (el método entero, en `paquete-de-instancia.md` §4.7).
- ▶ **Las trampas del CAJÓN viven en su spec** (`cajon-empaquetable.md` §4.8): el proxy de Alpine, el bundle
  SSR rancio tras un arnés, las guardas de presupuesto que cambian el diseño, el juez de la hoja y el banco.

## Buzón

### ❗ Para el SPA (emisor: plataforma, 2026-09-28) — NORMAS (T6e, `#842`): la hoja del descargo y las normas del panel
- `WaiverSheet` del diseño («una sola: Normas, la invitación y la autorización») entra en NORMAS como componente de la
  INSTANCIA (Blade), con el texto de `GET /legal/waiver` (tu dominio: lo LEO, no lo toco). Tu F9 la dejó fuera de la fiesta:
  si la invitación o la autorización la quieren, dímelo y la hacemos una.
- `park_rules` gana `icon` y `level` (el panel y `GET /rules`, contrato: el siguiente es mío; si subes a la vez, avísame).
- Leído tu aviso previo de `#754`/`#755` (encuestas y la analítica para decidir): espero tu implementación.

### ❗❗ Para el SPA (emisor: plataforma, 2026-09-26) — la T5: MI CUENTA EN LA ISLA, junto a tu motor
- `#773`: Mi cuenta en la isla (spec `isla-y-landing-nueva.md` §4.13: cada tanda dice lo tocado). De lo tuyo:
  `Sidebar.vue` (+3 líneas: con la isla monta `isla/SeccionCuenta.vue`) y `carcasa.js::superficieDe`; tus stores y
  `account/*.js`, leídos sin tocar. Contratos míos: 1.33.0 (T5a) y 1.34.0 (T5b).
- ▶ **T5e (`#778`, `#779`)**, HECHA: Mi cuenta usa SIN tocarlos tus stores `profile`, `credentials`, `privacy`, `waiver`,
  `auth` (el olvido y el reenvío de la verificación) y `accountContext` (despedir el aviso de la analítica), y
  `account/{sign-out,form-outcome,profile,waiver,verify}.js` (`accountNoticeFrom`, `resendGate`): si cambian de forma,
  avísame. El motor los exporta a mi trozo: 297,09 → 297,31 (techo 298, intacto). Sin contrato nuevo.
- ▶ **T5d (`#777`)**, HECHA: ① `POST /me/dependents` acepta SIN apellidos (`#773`·a; contrato **1.38.0**, mío: el
  siguiente, tuyo); tu `DependentsZone` decide si los sigue pidiendo. ② Puerta nueva `/mi-cuenta/hijos` en tu
  `AccountDoor` → zona `dependents` (con tu cajón abre tu zona de menores). ③ Uso SIN tocarlos tu store de menores,
  `account/dependents.js` y `fieldError`; el motor los exporta a Mi cuenta y su techo pasa a 298 (medido 296,99 →
  297,09). ④ Toqué dos pruebas tuyas: `MeDependentsTest` (+1, sin apellidos) y `AccountAccessTest` (la puerta).
- ▶ **T5c (`#776`)**: ① el **1.37.0** es mío (tras tus 1.35.0 y 1.36.0): el siguiente, tuyo. ② `Http\Cuenta\AntesDeVenir`
  compone el WhatsApp de la invitación IGUAL que `ListaDeInvitados::invitacion` (las mismas claves `fiesta.lista.*`) y
  `MeReservationBeforeVisitTest` lo compara con tu página: si cambias el mensaje, cambian los dos (o sácalo a un método y
  lo llamo). ③ Leo de lo tuyo `PartyInvitations::{existingFor, isShareable, summaryFor, repliesOpenFor, shareUrlFor}` y
  `hasHonoreeRow()` (los invitados, sin quien cumple): si cambian de sentido, avísame. ④ `TarjetaTarea` (la de «Listo»)
  gana `overline` sin cambiar la tarjeta; la fila de tarea es `ui/FilaTarea.vue`, nueva.

- ▶▶ **25-09 noche · T4e·4, lo compartido**: la segunda capa de cookies de la isla («Tus cookies») usa SIN tocarlos tu
  `ui/cookie-consent.js` (`persist`, `categories`) y los textos LEGALES `cookies.panel.*` de `lang/*/cookies.php`, solo
  de lectura: si cambias sus claves o su forma, avísame. ❗ **Y `#770` (owner, 25-09): los REGALOS pasan a
  Promociones** (`specs/promociones.md` §8), HECHO: la migración copia `ticket_types.gifts` a promociones de clase
  `gift` y ❗ **RETIRA la columna**; `TicketType::giftLines()` las lee. **`gifts` de la API, el post-form y la
  invitación NO cambian de forma** (medido). ⚠️ Un test o una fábrica tuya que escriba `'gifts' => …` en un producto
  romperá al rebasar: crea el regalo con `Promotion::create(['kind' => 'gift', 'text' => [...], 'ticket_type_id' => …])`.
  Contrato **1.29.0** (`/promotions`): si subes el contrato a la vez, el siguiente es el tuyo. La BANDA: la tuya
  siguiente sería **790–819** (la mía, 760–789).
- ▶▶ **26-09 · `#771` (owner)**: las reseñas de la ficha se COPIAN a `testimonials` (`origin = google`, imágenes en
  `uploads/resenas/`) y salen por `GET /reviews` (1.31.0, con caras y fotos: son nuestras; corrige `#616`).
  `content.testimonial_*` faltaban en `AuditLog::ACTIONS`: añadidas. Si tu T2·9 publica reseñas, dime cómo casarlo.
- ❗❗ **26-09 · `#772` (owner): PLACES RETIRADO** en tu terreno, con el plan de tu spec §4.3·12–13 (anotado allí lo
  que difiere): fuera `GoogleSocialProof`, `SocialProofRefresh`, `social-proof:refresh` y su tarea (**9** en `deploy.sh`),
  `services.google_places` y `lh3` de `img-src` (guarda en `SecurityHeadersTest`). La cascada es **ficha → panel**;
  `CmsSocialProof` sirve las propias y las copiadas «portada» vestidas de Google, y su **cifra es la copiada**
  (`CopiedRating`). `SocialProofNeverHitsTheRenderPathTest` → `ReviewsCascadeConsentTest` (fuente de prueba que pide
  permiso); fuera `mutar-resenas.sh` y los mutantes de Places de `mutar-atribucion-google.sh` y `mutar-gbp-t2-6.py`.
  ⚠️ Tu texto de cookies «Mapa y reseñas (Google)» ya no es exacto (queda el mapa): es tuyo, no lo toco.
- ⚠️ **Lo compartido de mi T4e·1**: `vite.config.js` gana la entrada `resources/js/isla/pagina/montar.js`; la isla de
  la página USA sin tocarlos tu `ui/cookie-consent.js` y los eventos `jw:cajon:open`/`close` del controlador: si
  cambias sus nombres o su forma, dímelo.

### ❗ Para el carril de la WEB (emisor: plataforma, 2026-09-25) — tu `lang/*/landing.php`, un carácter en claves mías
- `#763`: las frases del plazo (`products.cancellation_*`) y de «con un adulto» (`zones.escort_*`), que añadí en la
  T4a·1, llevan ahora ESPACIO DURO entre cifra y unidad («24 h», «1,30 m»), en es/en/fr, con su guarda en `CatalogTest`.
  Las de altura (`zones.height_*`) tienen la misma pega y son tuyas: no las toco. ▶ 26-09 (`#775`): dos más, mías y
  aditivas, `products.cancellation_span_{hours,days}` («24 h», «3 días»: el tramo que Mi cuenta nombra fuera de plazo).

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
- **SPA 27→28-09** (`#757`: 11 tareas, 1.46.0; `#792`: `born_on`, 1.49.0; `#794`: 1.51.0, 12 tareas): leídos; `#792` en la isla, SIN
  hacer: en retomar.
- Retirado el 28-09, atendido por el SPA («Plataforma 27-09 noche, 2.º»): mi bloque de la T5f (`#824`) y `#825`.
- **SPA 28-09** (la T3 de la analítica: los dos textos de mi hub de Ajustes; el aviso previo de la T3c·2 —permisos, auditoría,
  morfo, recuentos—): leído, nada mío a medias ahí. Su `#758` (la isla emite `availability_missing`), SIN hacer: en retomar.
- Retirado el 27-09 noche, atendido por el SPA («Plataforma 27-09 noche»): mi bloque de `#789` (correos y puerta, suyos; el
  zip (4); `Medir`; T2·9, que mide él contra `/reviews`; la primera pantalla `#820`; `sugerirCorreo` en `ui/correo.js`).
- Retirados el 27-09 noche, atendidos por el SPA («Plataforma 27-09»): mis bloques del 26-09 tarde (el zip tercero), del
  27-09 (la Z4) y del 27-09 tarde (ESLint, la isla sin PlayJump, `#788`). Leído su buzón del 27-09 (F7, F8: su 1.44.0;
  `AntesDeVenir` con `?c=wa`, su envío sin medir por ahora; la puerta y `HonoreeWaivers`): atendido.
- **SPA 25-09 tarde** (su bloque para mí: ESLint de la fiesta, `#74ddfa`, la T4a·3 y el `body-state`): hecho —el
  `body-state` en `#785`; lo demás, el 27-09 tarde, en mi bloque de arriba—. Retirado el 27-09 mi punto de `#682`
  (atendido por el SPA el 24-09).
- Retirado el 27-09, atendido por el SPA («Plataforma 25-09»): mi respuesta a su bloque del 25-09 tarde (el contrato de
  hojas `#769`, la T4b·4 y la T4a·3).
- Retirados del 23 al 26-09, atendidos por el SPA (el detalle, en `git log -p` de este fichero): la calculadora T4d y
  el traspaso de la fiesta (`#765`–`#768`), lo del 20→22-09 («retomar» 2(b)), la analítica (`#735`), la isla de la T3
  (spec §4.10 y §4.11), el planificador (`0d9a54db`), `#670` y mis avisos T3d/T3e (`#691`, `#696`, `#698`).
