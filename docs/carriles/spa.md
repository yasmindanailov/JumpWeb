# Carril · Diseño del SPA (el cajón) — los CORREOS (desde el 29-09), la ANALÍTICA y LA FIESTA del sistema nuevo

> Máquina: **el OTRO ordenador** (WSL2, `~/proyectos/jumpweb` a secas; la instancia al lado, en
> `~/proyectos/instancias/playjump`, clon de `github.com/yasmindanailov/instancia-playjump`, montada el 25-09) ·
> Banda: **790–819** (730–759 agotada el 28-09 con `#759`) · Último usado: **`#814`** · La banda está dada de alta en la
> tabla de `DECISIONES.md` · Arranque de la máquina: `docs/CARRIL-SPA.md` (su §7 antes que el resto) · Specs:
> **`acceso-con-codigo.md` §0** (la A4 del cajón ✅ en `main`) · `correos-rediseno.md` §0 ·
> `fiesta-sistema-nuevo.md` §4.17 (`#806`) · `analitica-para-decidir.md` §0 (en pausa, `#755`) · `encuestas.md` §0 y §4.7
> (`#754`) · `analitica.md` §0 y §4.5 · `isla-y-landing-nueva.md` §4.11 · `celebracion-e-invitacion.md` §0 ·
> `waiver-por-reserva.md` §0 · `analitica-fiesta.md` §0 · `google-business-profile.md` §0 · `sidebar-spa.md` §0 · Actualizado: 2026-10-01, 10:15
> (la A4b, Mi cuenta con un código, `#813`, EN `main` con el visto bueno del owner; sigue lo del zip (6), «retomar» 1b).
> Este fichero lo escribe SOLO el agente de este carril (`DECISIONES #621`). Techo **32 KB** (`#724`). El
> contador de la suite va en el trailer del commit (`#618`), no aquí. **Se muda, no se raspa**: el detalle de
> una feature baja a su spec (las trampas por tanda de la analítica T1→T5 viven en `analitica.md` §4.9,
> mudadas verbatim el 24-09; las de la ficha de Google en su §9.1; las de la invitación en su §10.20).

## Foto (2026-10-01, 10:15)

- ✅ **LA A4b, EN `main` con el visto bueno del owner** («Visto bueno. Buen trabajo»; `#813`; `acceso-con-codigo.md` §4.11):
  Mi cuenta confirma con un código (`ConfirmCode.vue`, `stores/confirm.js`); fuera cambiar y recuperar la contraseña. Arnés
  36/36; sonda `sonda-cajon-a4b.mjs` 37 puntos ×2. Plataforma, avisada en el buzón: su A5 ya puede retirar la contraseña.
- ✅ **LA A4a, EN `main`** (`#810`→`#812`, «buen trabajo, visto bueno»): entrar y el alta con un código; arnés 43/43.

- ✅ **LA LISTA DE INVITADOS DEL OWNER, EN `main` Y APROBADA** (`#805`, del `#847`; `fiesta-sistema-nuevo.md` §4.16; «vale,
  visto bueno»): todos confirmados, sin firmas de los invitados (el descargo de quien cumple, sí), sin cifras ni recordatorio
  (RETIRADO entero, con sus textos y su test), el «No podemos» aparte y suave; arnés `mutar-lista-805.sh` 6/6.
- ✅ **LOS COMPLEMENTOS EN DOS** (`#806`→`#808`, §4.17), en `main`: **K1** (aprobada: «Para los niños» / «Para los adultos» y
  «Uno para cada niño»; arnés 10/10) y **K2** (varias tartas, «Sin tarta» en casilla; arnés 16/16; el owner: «Quedarme con K2»).
  **K3, la merienda, SIN CÓDIGO** (`#808`): complementos a 0 € en la familia «Merienda», probado solo configurando en el pack 106
  (`JW-OJO-CFG`, `CARRIL-SPA` §8 (25)). ▶ La regla del owner, en adelante: no se programa lo que el panel ya configura.
- ✅ R1a y R1b de los correos, en `main` y aprobadas (29-09): su foto, en `CARRIL-SPA.md` §9. ✅ **La R1·T (los textos
  editables, `#802`), en `main` con el visto bueno (30-09 noche)**, revisada antes midiendo los 29 correos bloque a bloque;
  arnés 63/63 (`correos-rediseno.md` §4.2.2).
- Los correos: sus cuatro decisiones del owner (`#800`→`#804`), mudadas verbatim a `CARRIL-SPA.md` §9 (30-09).
- ⏸ **LA ANALÍTICA PARA DECIDIR, en pausa tras la T4** (`#755`): su foto por tanda, mudada verbatim a `CARRIL-SPA.md` §9
  (29-09); lo que queda, en «por dónde retomar» 3. ⚠️ La ficha del cliente en `zh_CN` pinta el parentesco de sus menores como
  la clave cruda (`admin.users.dependents.relationship_*` solo en es): sin arreglar.
- ▶ **La fiesta del sistema nuevo es mía (`#765`)**: la lista, la invitación y la autorización, con «Saltia»
  (`specs/fiesta-sistema-nuevo.md`). ⚠️ **El zip entra SOLO por plataforma** y llega con `git pull` de la instancia; se
  comprueba con su `.sha256` (rutas relativas a `diseno/`: `awk` con la ruta entera, hay nombres con espacios).
- ✅ F7·F8·F9 (27-09) y la analítica entera T1→T7 (`#735`, 24/25-09): aprobadas; su foto, en `CARRIL-SPA.md` §9.
- ✅ La fiesta del sistema nuevo, T1a→F6b en `main` (25/26-09): su foto, mudada verbatim a `CARRIL-SPA.md` §9 (29-09).
- ⚠️⚠️ **LO MONTADO EN LA BD LOCAL para el ojo del owner** (ajustes falsos, el experimento `carcasa` vivo, los fixtures
  `probe-ojo-*`, las fiestas `JW-OJO-F1…F8` con sus guiones `OJO=desmontar`, y la ISLA encendida por plataforma —«no
  deshacer sin él»—): el inventario entero, mudado verbatim a `docs/CARRIL-SPA.md` §8 (27-09). Todo reversible.
- De la foto, mudados verbatim a `CARRIL-SPA.md` §9 (29-09): la máquina montada para la fiesta, lo que tiene su ✅ sin desplegar
  y ✅ los correos salientes (`#794`→`#797`; en producción, los dos interruptores esperan a `/privacidad` y `/cookies`).

## Por dónde retomar, en orden

0. ✅ **LOS COMPLEMENTOS DE LA FIESTA** (`#806`→`#808`, `fiesta-sistema-nuevo.md` §4.17): K1 y K2 en `main`; K3 es DATO del
   panel (la receta, en §4.17 «K3»): lo configura el parque en SU panel al desplegar (merienda, calcetines, cono, tartas). ▶
   **Antes de proponer código en cualquier tanda, medir si el panel ya lo configura** (montaje y sonda en la local); si algo
   ya existía, parar y decírselo al owner (`#808`).
1. ✅ **LA A4 DEL ACCESO CON CÓDIGO (el cajón), ENTERA EN `main`**: la A4a (`#810`→`#812`) y la A4b (`#813`), las dos con el
   visto bueno del owner. La red: `mutar-cajon-a4a.sh`/`-a4b.sh` y `sonda-cajon-a4a.mjs`/`-a4b.mjs`. ⚠️ El código, como la
   isla (`#812`): manda el servidor; si plataforma lo cambia (su Z6g o su A5), el cajón lo sigue. ▶ Lo siguiente, el 1b.
1b. ▶▶ **Lo del zip (6) que el owner repartió al SPA** (`#861`; `isla-y-landing-nueva.md` §4.27), en este orden (`#814`):
   **`LinkIsland`** (`fiesta-sistema-nuevo.md` §4.18): 🟦 **L1 (la invitación) y L2 (la lista), hechas en
   `wip/isla-enlace-l2` (sobre `-l1`), al ojo del owner** (fotos mandadas); sigue **L3 la autorización**, con
   `mutar-isla-enlace.sh` y `sonda-isla-enlace.mjs`. Después, **la imagen de la invitación al compartir, GENERADA para cada una** (nombre, edad, día,
   hora, su diseño) y **la Puerta** (el mostrador, `paginas/puerta/` y su brief). Producción, medida por plataforma: GD con FreeType, sin Chromium ni Node → la imagen se
   DIBUJA (fondo por tema + texto con GD), no se renderiza HTML. Cada una, «al detalle» medido en su spec antes de código.
2. ▶▶ **LOS CORREOS (`specs/correos-rediseno.md`: §0 → §4; `#789`, `#800`→`#804`)**: ✅ R1a, R1b y **R1·T** en `main` (la
   R1·T revisada: §4.2.2; su sonda y el medidor de bloques, `CARRIL-SPA` §8 (26)). Del zip (6) (`#861`): el 8, «482-913 es tu
   código para entrar» sin botón; «Quién firma el descargo» en el 1 y el 3; las dos horas (90 + 30). ▶ **Tras la A4, la R1·T2** (`#809`,
   `[DECIDIDO owner]` 30-09): los 33 textos que solo salen a veces, con su aviso «Solo sale si…» y la «Situación» en la
   vista previa (§4.2.2). Cada tanda con su «al detalle» MEDIDO
   en la spec antes de codificar, en `wip/…`, con su arnés y al ojo del owner en Mailpit (el banco,
   `php scripts/banco-correos.php [filtro]`, y la sonda de la carpeta de auditoría, `sonda-correos-r1a.mjs N`):
   **R1c** los 27 correos a la plantilla → **R2** la reserva (1, 1b, 2, 3, 4, 5, 6: el QR dentro, el
   calendario, «Cómo llegar», WhatsApp, responder al parque) → **R3** el 2b → **C1** los comerciales (11, el 12 ampliado,
   13a/b, 6b: consentimiento, «una vez», la baja LSSI) → **C2** las felicitaciones (TP·3c; el copy, con el owner) → **C3** las
   ocasiones. El 7: la encuesta y Google en su página de gracias; sin encuesta activa, solo la reseña (`#801`). Los grupos
   8–10, cuando el diseño tenga sus textos. También míos (`#789`): la PUERTA (su diseño, en el próximo zip) y **T2·9** (las
   reseñas en la API, `#771`: ver si queda la selección o se retira). Al acabar la R1c se retiran `vendor/mail/**`,
   `themes/brand.css` y el layout viejo (§4.1.1). El cajón ya dice «Tu cumpleaños» (`#792`, 29-09, visto por el owner).
3. ⏸ **LA ANALÍTICA PARA DECIDIR (`#755`, `specs/analitica-para-decidir.md`), EN PAUSA tras la T4**: ❗ **defecto MÍO, medido
   por plataforma (29-09)**: `OccupancyReport::missing()` (y el `missing` de los totales) no cruza con `analytics_sessions`,
   así que cuenta robots (`webdriver`, las sondas) y personal, que el embudo y los experimentos excluyen; arreglarlo con su
   mutación antes de retomar la analítica (su `sonda-demanda.mjs` borra lo suyo). Y de la A1 de plataforma (`#853`): el
   `password` de `CustomersReport::METHODS` ya es «con el formulario» (renombrarlo) y `user_logged_in` cuenta las vueltas de un
   dispositivo recordado. En `main` y aprobadas
   T0→T4, con TP·1→TP·3b y T3d (arneses `SOLO=<tanda>` de `mutar-analitica-decidir.sh`; sondas y fixtures `ojo-tp2.php` y
   `ojo-tp3.php` en `storage/app/audit/`, `CARRIL-SPA` §8 (17) y (22)); ⏸ T3e sin fuente (`#799`); ✗ T5 (`#800`); la TP·3c
   va con los correos (C2). Al retomarla: T6 cohortes → T7 pérdidas → T8 satisfacción (§4.12); el cruce por EMPLEADO,
   `[PENDIENTE: owner]` (27-09, sin respuesta).
   **Cómo se trabaja una tanda** (lo de esta sesión): medir antes y escribir «La Tx al detalle» en §4.13; toda cifra por el
   catálogo (`Filament\Analytics\Metrics\*::from()`, su «¿Cómo se calcula?» es/zh_CN y el censo); el arnés
   `mutar-analitica-decidir.sh` con `SOLO=<tanda>` (~5 min; el entero, ~95 min, al cerrar un bloque: owner, 28-09), en
   segundo plano y **nadie mira localhost mientras corre** (muta en su sitio); un
   superviviente es un caso que falta; tras el arnés, los dos bundles; tras rebasar, re-medir la suite. En local «Este mes» dice
   «aún sin historia» (3 meses de datos): para el ojo, «La semana pasada». La tanda, aparcada en `wip/…` hasta el visto bueno y
   después *fast-forward* a `main`. **Trampas**: `OccupancyReader` COPIA la aritmética del aforo (`OccupancyReaderParityTest`);
   ⚠️ no verificado en navegador que el cajón emita `availability_missing` (medido el 28-09: la local ya sirve el CAJÓN —sin
   fila `sidebar.shell`—, así que ya se puede); `AccessRevocationTest` no
   deja escribir el literal `'sessions'`; `TestCase::count()` y `countOf()` son finales. `[PENDIENTE: asesoría]`: los 90 días
   del sello, los clics por persona y el píxel (y el (5) de las encuestas).
4. ✅ **LA FIESTA DEL SISTEMA NUEVO**: en código sin nada pendiente; su punto entero (el zip nuevo, lo que está a prueba, las
   reglas en pie), mudado verbatim a `CARRIL-SPA.md` §9 (30-09).
5. ❗ **`audit-clock.sh` (27-09): 10/10 pases en rojo por tests AJENOS a la analítica** (los de la analítica, verdes en
   todos): `GoogleReviewImagesTest::test_el_barrido_no_toca_lo_recien_escrito` (10/10: el barrido mira el `mtime` REAL
   del fichero contra el reloj CONGELADO de Laravel; en producción coinciden: es del test), `InvitationSharingTest` (6
   casos, 404 en 4 fechas frontera; sin analizar) y `ScheduleFactsTest` (el conocido, Trampas ⏰). Arreglar antes de la v2.0.0.
6. **La invitación, lo que su ✅ NO cubre** (el `.ics` en un teléfono, el Turnstile real, `§7.2·R12`, `og:image` con bandas) y
   **los diez puntos de `§10.4.7·B`** (empieza por la RAÍZ: `matches()` y `takeSlotFor()` no son la misma regla).
7. De la Fase 4: el **ojo del owner en un teléfono de verdad** · el cuaderno de entrega del cajón · el botón del sistema.
   Del plugin, `/dod` y `/ligero` por ver (⚠️ al recrear el contenedor se pierde `socat`; la skill `/sonda` lo repone).

## Ficheros de este carril

**La fiesta del sistema nuevo (`#765`)**: las tres páginas enfocadas —`lang/*/guestform.php`, `guardian.php`,
`invitation.php` (lo que queda), el molde `gf-*` de `site.css` y `focused-layout` (hoy de las ENCUESTAS, mías también)—
y lo nuevo (T1a→T4): `app/Http/Fiesta/` (los modelos de página, `Temas`, `Marca`, `Sitio`), `resources/views/fiesta/**`,
`components/{fiesta,pieza}/`, `components/pagina-enfocada.blade.php`,
`resources/js/fiesta/`, `lang/*/fiesta.php`, `tests/Feature/Fiesta/`, `scripts/banco-fiesta.php`, `banco-fiesta/modelos.php`,
`mutar-fiesta.sh`; los valores de PlayJump, en `publico/instancia/` del repo de la instancia (se empuja allí, nunca a
`main`). El contrato de hojas (`InstanceViews::hojas`) es de plataforma (`#769`).
**Los correos (`#789`, R1a/R1b)**: `app/Notifications/Support/{MailTheme,MailDocument,MailPie,MailIcons}.php`,
`BrandedMailMessage`, `EmailIconController` y su ruta `correo.icono`, `resources/views/correo/**`, `emails/partials/{book,
product-card}`, `resources/correo/iconos/`, `scripts/{correo-mascaras.mjs,banco-correos.php,mutar-correo-r1a.sh,
mutar-correo-r1b.sh}`, `tests/Feature/Mail/Mail*Test`, `tests/Feature/MailThemeTest`; la hoja `css/correo.css`, en el repo de la instancia.
**La R1·T (`#802`)**: el módulo `app/Domain/Content/` (`ContentServiceProvider`, `Models/MailText`, `Services/MailText*`),
`Notifications/Support/{MailTextCatalog,MailPreviews}.php`, `Filament/Pages/EmailTexts.php` y su vista, la migración
`create_mail_texts_table`, el bloque `mail_texts.*` de `lang/{es,zh_CN}/admin.php`, `EmailTextsPageTest`,
`scripts/mutar-correo-r1t.sh`.
**La analítica entera desde `#735`**: `app/Domain/Platform/Models/Analytics*`, `app/Domain/Platform/Services/
Analytics/**` (T1 de plataforma incluida: `Contract`, `Recorder`, `EventIngestor`, `AttributionContext`,
`SessionResolver`, `EmailUtm`, `RouteNormalizer`, y desde la T6 `PartyFacts`), los tres observadores
`*AnalyticsObserver`, `Http/Middleware/{ResolveVisitor,ResolveAttribution,RecordEmailClick}`,
`Api/V1/EventsController`, `resources/js/cajon/track.js`, `tests/Feature/Analytics/**`, `tests/Support/
MountsAParty.php`, `tests/Feature/Api/V1/AnalyticsEventsTest`, `scripts/mutar-analitica.sh`,
`scripts/mutar-analitica-fiesta.sh`, `scripts/sonda-analitica.mjs`, el bloque de eventos de `openapi/v1.yaml`;
lo de T2: `app/Filament/Pages/AnalyticsPage`, `app/Filament/Widgets/Analytics/**`, `app/Filament/Analytics/**`,
`Platform/Services/Analytics/Reports/**`, las claves `analytics.*` de `lang/{es,zh_CN}/admin.php`,
`scripts/sonda-analitica-panel.mjs`; lo de T3: `Identity/Services/CookieConsent`, `CookieConsentController`,
`resources/js/ui/cookie-consent.js`, `Platform/Services/Analytics/Drivers`, `Platform/Jobs/ForgetPersonInDriver`,
`resources/js/cajon/driver.js`, `resources/js/cajon/pixels.js`, `scripts/sonda-cookies.mjs`, `scripts/sonda-driver.mjs`,
`lang/{es,en,fr}/cookies.php`, `Content/Services/{CookiePolicyContent,LegalContent}`; lo de T5:
`Platform/Models/Experiment`, `Platform/Services/Analytics/Experiments`, `Filament/Resources/Experiments/**`,
`resources/js/sidebar/experiments.js`; **lo de la T6 (la fiesta)**: `app/Http/Concerns/RecordsPartyFacts.php`, los
tres controladores de las páginas enfocadas (`GuestFormController`, `InvitationPageController`,
`GuardianAuthorizationController`) y su grupo de rutas en `routes/web.php`; **lo de la T7 (encuestas)**: lo que
`encuestas.md` §0 enumera.
**Compartido (aviso antes)**: `PermissionSeeder`/`PermissionCatalog`, `AdminNavigationTest`, `routes/web.php`
fuera del grupo de la fiesta, y en T3 `layout.blade.php`, `app.js`, `SecurityHeaders`, `Settings.php`, el banner,
`anfitrion/legal.blade.php`.
**El cajón**: `resources/js/sidebar/**` · `resources/js/ui/*` que solo use el cajón · `lang/*/tickets.php`,
`account.php` · `tests/Feature/Sidebar/**`, `tests/Feature/Architecture/Sidebar*`,
`tests/Feature/Reservation/*SkinTest` · `scripts/sonda-cajon.mjs`, `sonda-enlace-firmado.mjs` · en
`public/css/site.css`, los bloques del cajón por su TÍTULO y el de la «HOJA ENFOCADA». **La ficha de Google**
(`#524`, tomada de la web): lo que su spec enumera. Lo del cliente va en la rama `cliente/playjump` (el tema viejo) o en
el repo de la instancia (lo nuevo), nunca a `main`.

## Trampas vivas (las de esta máquina y del repo; las de cada feature, en su spec)

- **El trailer lleva el TOTAL de la suite** (la línea `Tests: N` del gate, con los omitidos dentro), no los «passed» de
  `--compact`: 6077 passed + 1 skipped se declara 6078, o el gate dice que el commit MIENTE (26-09). Las ASERCIONES varían
  con el reloj (28-09: 42882 → 42853, mismo código): vale la del gate de ESE push.
- 🏠 **Esta máquina y la instancia (25-09)**: `compose.yaml` monta `../instancias` (aquí `~/proyectos/instancias`) en
  `/var/www/instancias`; si la carpeta no existe, **Docker la crea de ROOT y vacía**: `rmdir` funciona igual (el padre es
  tuyo) y después `mkdir` + clon. Cambiar la carpeta por debajo del montaje exige **recrear `laravel.test`**
  (`up -d --force-recreate --no-deps laravel.test`; la BD vive en su volumen): se pierde `socat` (apt) y sobrevive
  Chromium. `INSTANCIA_RUTA` es la ruta **del contenedor**. Tras un `pull` de la instancia: `cp -r
  ../instancias/playjump/publico/instancia public/` y `optimize:clear`. El `instalar.sh` de la instancia lo deniega el
  clasificador («código externo»): lo que comprueba se hace a mano.
- 🐳 **«The command 'docker' could not be found» es Docker Desktop APAGADO** (24-09, medido). En ESTA máquina
  el ejecutable es `/mnt/c/Users/yasmi/AppData/Local/Programs/DockerDesktop/Docker Desktop.exe` (no el de
  `Program Files` de la otra): `nohup "…/Docker Desktop.exe" &` y esperar a `docker info` (tardó ~60 s).
  `wsl.exe -l -v` lo delata: la distro `docker-desktop` en «Stopped». 🐳 **Y el motor COLGADO** (24-09 noche):
  `docker compose`, `docker info`, el `_ping` del socket y el `docker.exe` de Windows se quedan sin respuesta
  mientras los contenedores SIGUEN sirviendo (web 200, `wsl.exe -l -v` todo «Running»); sin la CLI no hay suite ni
  gate: lo arregla el owner reiniciando Docker Desktop. `pkill -f 'docker compose exec'` mata tu propia shell.
  🐳 **Y la integración WSL CAÍDA** (01-10, tras un cierre abrupto): `docker` da el mismo aviso, pero la web da 200 y
  `/mnt/wsl/docker-desktop/cli-tools` está VACÍO. El CLI de Windows sí llega (`…/DockerDesktop/resources/bin/docker.exe`,
  también `compose` desde la carpeta del repo): un `docker` de una línea que lo llame, en la carpeta de la sesión, delante
  del `PATH`; así corren los guiones y el `pre-push`.
- 📜 El `laravel.log` local llegó a **1,35 GB** (trazas de 270 KB desde el 12-08); borrado con el sí del owner el
  25-09 y el `.env` local rota a diario desde entonces (`LOG_STACK=daily`, 14 días).
- ⏰⏰ **EL RELOJ: el contenedor va en UTC y el parque en Madrid, y entre las dos medianoches NO es el mismo
  día.** `DisplayTime::dayLabel()` **no convierte de zona**; **un test con reloj propio miente dos horas al día**
  (`ScheduleFactsTest`, rojo a las 00:07). ▶ En un test de «ahora», el reloj a **`DisplayTime`, nunca a `Carbon`**;
  en el PANEL, toda hora por `DisplayTime` (`#733`). Fija la hora a mano, cerca de medianoche, para que cambie
  hasta el día. ⏰ **La fecha de una decisión sale del reloj del OWNER** (`date` en el host), nunca del contenedor.
- 💥💥 **UN CORTE DE LA VM DE WSL DEJA FICHEROS A CERO BYTES Y ROMPE GIT** (20-09): 31 objetos de
  `.git/objects` vacíos —el commit en curso entre ellos— y medio `public/build`. Síntoma: «*object file … is
  empty*», «*bad object HEAD*». ▶ **Receta medida**: apartar (no borrar) los vacíos (`find .git/objects -type f
  -empty`) · `git update-ref refs/heads/main <sha ENTERO del reflog>` · `git fsck` · `git reset` (el
  *cache-tree* apunta a un objeto muerto) · `npm run build` y `build:ssr`. **Lo versionado NO se pierde.**
- 🚪 **LA PUERTA DEL `pre-push` (24-09, dos vueltas pagadas)**: (1) exige el trailer «`Verificación: suite N tests /
  M aserciones (…)`» EN ESE FORMATO, o cierra con la suite en verde y te da la cifra para que la escribas; (2)
  tras un `--amend` con el otro carril empujando en medio, dice «no puedo calcular el diff contra el remoto»: es
  `git fetch` + `pull --rebase`, no un fallo. Un push cuesta ~5 min (Pint, Larastan, ESLint, build y la suite).
- Chromium muere al recrear el contenedor SOLO si no vive en `node_modules` (hoy sí): `node
  node_modules/playwright-core/cli.js install chromium` (con `npx` cae en otra caché); `npm install` poda `playwright-core`.
- `SidebarDomContractTest` renderiza el BUNDLE: `npm run build:ssr` antes de la suite, también tras traer
  commits del cajón, tras un arnés de mutación (restaura el árbol, no el bundle) **y siempre que toques un
  `.vue`** (si no, 36 rojos que no son tuyos). Techo del chunk del motor **302** (`#812`, 01-10: 301,79).
- ⚠️⚠️ **Un filtro que no ejecuta nada también sale ≠ 0**, y **Pint DESTROZA los nombres de método con
  palabras en MAYÚSCULAS** (`_UN_` → `_u_n_`): así se rompe un arnés **en silencio**. Los tests se nombran
  **sin mayúsculas**, y una mutación se cree tras ver el MISMO filtro en verde ejecutando su caso. Aseverar
  una subcadena sobre HTML acusa al script que la nombra (`#553`).
- ⚠️⚠️ **UN SUPERVIVIENTE DEL ARNÉS ES UNA PREGUNTA SOBRE EL TEST, no sobre el código** (`#578`, `#728`,
  `#730`, `#732`; y el 24-09 otra vez: `factOfOrder()` sin `isServer()` sobrevivía porque ningún caso lo llamaba
  con un nombre de cliente): primero «¿qué caso me falta?», y **solo** si no hay ninguno se declara.
- **Un test que calcula su expectativa desde el código bajo prueba no prueba nada**, y un fixture que usa la
  convención que dice vigilar tampoco; una aserción con `__('clave')` dentro pasa siempre al vaciar la clave
  (`#734`): el rótulo se escribe a mano. **Al pivote se le habla por MÉTODO**, no por propiedad (Larastan).
- **Un modelo nuevo necesita alias de morfo** en `AppServiceProvider` o `MorphMapTest` pone la suite en rojo,
  y el rojo aparece en la suite COMPLETA, no en el filtro de tu tanda.
- ⚠️⚠️ **Una migración empujada NO es una migración aplicada**: la suite migra en SQLite en memoria; tras
  traer o añadir una, `php artisan migrate` en el contenedor.
- **La suite es CIEGA a los locks** (en SQLite `compileLock()` devuelve cadena vacía): esa guarda la dan los
  verificadores sobre InnoDB, y su verde vale solo si se ha visto FALLAR sin el lock. ▶ Una suma atómica **sí**
  se ve sin concurrencia: dos instancias LEÍDAS antes de que ninguna escriba distinguen `DB::raw('col + 1')`
  de `$m->col + 1` (`#713`).
- **El `CRITICAL_RE` del hook se lee, no se recuerda**: comprobarlo cuesta un `grep`; suponerlo, una sesión.
- **La línea base de Larastan SOLO ENCOGE**, también en cuentas: se arregla el tipo (`/** @var Order */`),
  nunca el baseline.
- **En OpenAPI 3.0 `nullable` NO atraviesa un `$ref`** y `allOf: [$ref] + nullable` **no valida** con
  Spectator (ya van tres veces): copia INLINE + guarda de divergencia en `ApiContractTest`. Un array PHP
  vacío se serializa `[]`, no `{}`. Las `responses` reutilizables son solo `NotFound`, `TooManyRequests`,
  `Maintenance`, `Unauthenticated` y `ValidationFailed`; **no hay `Forbidden`**, se escribe inline. Los nombres
  de SERVIDOR del contrato de eventos NO van en el `enum` del yaml (`AnalyticsContractTest` lo prohíbe).
- **`Route::has()` no ve una ruta declarada a mitad de un test** hasta `Route::getRoutes()->refreshNameLookups()`.
- ⏰ **Techos que mide `docs-check`**: decisión 1,5 KB, §0 2048 B **sin la línea del título**, tracker 16 KB,
  carril 32 KB, enrutador 12 KB. Mídelos con `wc -c`/`awk` antes del commit (el §0 de `analitica.md` va a 2.000 B
  y el enrutador a 12.270: para meter una fila se recorta otra).
- 🩹 **19 firmas de waiver HUÉRFANAS en la BD local**: mudado verbatim a `CARRIL-SPA.md` §8 (18), 28-09.
- **F4 cerró y el cajón es un PAQUETE** (`specs/cajon-empaquetable.md` §0 y §4.8). Tocar «HOJA ENFOCADA» de
  `site.css` obliga a regenerar `public/css/cajon.css` (`python3 scripts/hoja-del-cajon.py --aplicar`). Las
  reglas de botón apuntan al `button` y **no a `.btn`**. ⚠️ **La local viste el cajón con `cajon.css`** (la landing es la
  de la instancia, medido el 01-10): un cambio de estilo en `site.css` no se ve hasta regenerar la hoja, y una mutación de
  estilo se hace sobre ella.
- Commit por NOMBRE de fichero, nunca `git add -A`. La decisión, al final de `decisiones/700-799.md`.

## Buzón

- ❗ **Para plataforma (01-10)**: tu lectura de la A4a, vista (su aviso, retirado: el detalle, en el `git log`). Queda:
  (1) `#812` (el owner): el código del cajón, como tu isla, y **TOQUÉ TU SERVIDOR**: con solo el minuto agotado respondía
  `retry_after: 3599` en la puerta, `requestConfirmationCode` y `resendPendingEmail`; ahora `EmailCodeLogin::secondsToWait`,
  límites sin cambio, con casos en tus `AuthCodeTest`, `MeConfirmationCodeTest` y `MePendingEmailCodeTest`. (2) **La A4b
  (`#813`) EN `main`, con el visto bueno del owner: tu A5 ya puede retirar la contraseña de los clientes.** Mi cuenta del
  cajón confirma con `code`, nunca `current_password`; del motor se van `changePassword`, `forgot.js`, `requestPasswordLink`,
  `parentZoneFor` y las zonas `password`/`forgot` (`forgot`, a la puerta por `zoneFor`). Lo que usas, sin cambio; tu
  `useAjustesCuenta.js` dice en un comentario que `deleteAccount` manda la contraseña: ya manda `code`. `PLEGABLE_DE_ZONA.password`
  se queda sin zona. Medido: el primer «Pedir otro código» del correo nuevo SALE (el límite cuenta reenvíos).
- Mis avisos a plataforma del 27→30-09 (`#754`/`#757`, la R1b, `#807`/`#808`, la R1·T —su aviso previo y el de `main`—, el
  rojo de `ManualOrderIgnoresMinAdvanceTest` y el previo de la A4): LEÍDOS por plataforma (su «Atendido»); retirados. El
  detalle, en el `git log`.
- ❗ **Para la web (28-09, TP·1 y `#793`)**: `/privacidad` tiene que nombrar la fecha de nacimiento del titular (opcional; para
  conocer al público, siempre en conjunto) y, con la TP·3c, las felicitaciones de cumpleaños (la del titular y la de sus hijos,
  solo con el opt-in de marketing y sin vender). Ya NO habrá exportación de personas (`#793`). `[PENDIENTE: asesoría]`.
- ❗ **Para la web (27-09, `#754`; sigue en pie)**: `/privacidad` tiene que nombrar el sello de 90 días de las encuestas
  anónimas («a los 90 días se separan de ti del todo», el aviso que ya leen el cliente en la puerta, el correo y la página)
  y los clics por persona en los correos (oposición en «Análisis»); `/cookies`, el píxel de apertura (con consentimiento):
  `[PENDIENTE: asesoría]`, el texto es tuyo.
- **Para correos (27-09, `#754`)**: `SurveyInvitation` gana UNA línea (el aviso del anonimato, tras la intro) y recibe el
  token en claro en vez de la fila; tu molde, sin tocar. La marca por envío (`jw_e`) y el píxel, en la T5: aviso antes.
- **Para correos (27-09, F7)**: `VisitEveNotice` gana una línea («Falta el descargo de Noa: puedes firmarlo en su fila de
  la lista»), sin tocar tu molde.
- ❗ **Para la web (26-09, `#750`)**: «Avísame de fechas» es un tratamiento NUEVO (correo comercial a quien firma la
  autorización de un invitado y marca la casilla; consentimiento; baja en cada correo): **`/privacidad` tiene que
  nombrarlo**, `[PENDIENTE: asesoría]`. El texto es tuyo (`LegalContent`); yo no lo toco.
- Los mensajes a plataforma del 26-09 (F6b, F5, F1c), ya leídos: mudados verbatim a `CARRIL-SPA.md` §9.

### ❗❗ Para el carril de PLATAFORMA (emisor: SPA, 2026-09-25 → 27-09)
- ⚠️ Cuando empujes un zip nuevo del owner a la instancia, dímelo aquí: re-mido el censo de la fiesta contra él.
- ⚠️ **Tu composer `site` (`AppServiceProvider`) solo corre al PINTAR**: mis tres páginas leen los mismos ajustes con
  `App\Http\Fiesta\Sitio::datos()` (T3). Si sacas ese arreglo a un servicio, lo uso y retiro el mío.
- ▶ **T4a·2 = mi T2·9**: leído `#771`; la mido contra tu `/reviews` (1.31.0) al retomarla y te digo aquí si se retira.
- ✅ **Tu medida del `--warn-100` de `fiesta.css` (27-09)**: era `#fff0cf`, el de PlayJump; arreglado, y
  `PaletaNeutraTest` lee ya las familias de ESTADO y las tripletas `r, g, b` (visto fallar con cada una).

- Los dos avisos al carril de CORREOS (29-09 y 19→26-09; hoy los correos son de este carril, `#789`): mudados verbatim a
  `CARRIL-SPA.md` §9 (30-09).

### ❗❗ Para el carril de la WEB (emisor: SPA, 24-09) — LA T3 DE LA ANALÍTICA tocó lo tuyo
- **La T3 entera está en `main` (24-09)** y tocó lo tuyo; el detalle por tanda, en `analitica.md` §4.3 y §4.9.
  En una línea: `layout.blade.php` (los `data-cookie-*` desde `CookieConsent::OPTIONAL`, `data-analytics-*`,
  `data-pixel-*`), `app.js` (el almacén `cookies` → `ui/cookie-consent.js`), `site/cookie-banner.blade.php` (un
  toggle por categoría), `lang/{es,en,fr}/cookies.php`, `Content\Services\{CookiePolicyContent,LegalContent}`
  (tres migraciones quirúrgicas), `SecurityHeaders.php` (orígenes del driver y de los píxeles), `Filament/Pages/
  Settings.php` (dos secciones), `anfitrion/legal.blade.php` (`/cookies` nombra la herramienta y los píxeles
  activos), `bootstrap/app.php` (`_fbp`/`_fbc`/`_ttp` sin cifrar), `openapi/v1.yaml` → **1.21.0**,
  `AccountHomeZone.vue`, `stores/accountContext.js`, `cajon/track.js` y `cajon/controller.js`. **`POLICY_VERSION`
  = `2026-09-24`**: todo visitante vuelve a decidir. **El owner vio el banner, `/cookies` y `/privacidad` en vivo
  el 24-09 («perfecto»)**. ❗ **Tuyo y a la vista**: el «[PENDIENTE: confirmar adhesión…]» del proveedor del FEED
  SOCIAL en el párrafo de transferencias de `/cookies` (`#592`) sigue publicado; yo no lo toco.
- ▶ **Me llevé `google-business-profile.md` (`#524`)**, tuya de banda; la numero desde la mía. Tocado en la T2·8
  (`#734`): `public/css/landing.css` (el bloque `.rev*`), `lang/{es,en,fr}/landing.php` (`reviews.*`),
  `ReviewCardTest`, `public/css/cajon.css` regenerada. ❗ `google-reviews.md` dice «umbral de 10 reseñas» y el
  código `MIN_REVIEWS = 1` (`#494`): no la toco yo.

### Atendido
- **Plataforma 30-09 noche** (el zip (6) y el reparto del owner, `#861`; su lectura de mi previo de la A4; `#860` en `main`):
  leídos. La A4 con el `CodeInput`, hecha en la A4a (`#811`); la Puerta, `LinkIsland` y la imagen de la invitación, a «por
  dónde retomar» 1b; los correos del zip, al 2. Su `PLEGABLE_DE_ZONA.password` se va en su A5.
- **Plataforma 30-09, lo último** (`#859` lo que tocó de mi T3; aviso previo `#860`, el aviso pedirá solo lo encendido; `#858`
  la casilla): leídos. Nada mío a medias en el consentimiento; la casilla, a mi A4. Su sugerencia (el faro de
  `/api/v1/events` sin conexión deja un error en la consola: mirar `navigator.onLine`), a la analítica («retomar» 3).
- **Plataforma 30-09 noche** (el TEXTO de `/cookies` para producción, aviso previo; la guarda de `{code}` en mi R1·T; la A3a/A3b
  `#857`; la A2b `#856`, contrato 1.57.0): leídos. Nada mío a medias en `/cookies`; la guarda, hecha y medida (mi aviso de
  arriba); lo de la A3 y la A2b, para mi A4 («por dónde retomar» 1).
- **Plataforma 30-09** (la A2a, `#855`, contrato 1.56.0: `POST /me/confirm-code` y `code` en las cuatro acciones; tocó otra vez
  `EmailTiming` y dos censos; la sesión atada al token): leído; para mi A4. Nada mío cambia el token a mano.
- Del 25 al 29-09 (la A1 `#853`/`#854`, T6f/T6g, la hoja de correo, `#792`, `#847`/`#848`, `#846` —no cambio la firma de
  `createMissingReporter`/`missingMonths` sin avisar—, `#850`/`#851`, NORMAS `#842`, `#836`, T5f, `#789`/`#822`, ESLint,
  `#788`/`#780`, `#765`) y web `#540`: mudados verbatim a `CARRIL-SPA.md` §9 (29-09 y 30-09 noche).
