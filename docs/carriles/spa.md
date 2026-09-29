# Carril · Diseño del SPA (el cajón) — los CORREOS (desde el 29-09), la ANALÍTICA y LA FIESTA del sistema nuevo

> Máquina: **el OTRO ordenador** (WSL2, `~/proyectos/jumpweb` a secas; la instancia al lado, en
> `~/proyectos/instancias/playjump`, clon de `github.com/yasmindanailov/instancia-playjump`, montada el 25-09) ·
> Banda: **790–819** (730–759 agotada el 28-09 con `#759`) · Último usado: **`#804`** · La banda está dada de alta en la
> tabla de `DECISIONES.md` · Arranque de la máquina: `docs/CARRIL-SPA.md` (su §7 antes que el resto) · Specs: **`correos-rediseno.md`
> §0** (la tarea en curso, `#800`→`#802`) · `analitica-para-decidir.md` §0 (en pausa, `#755`) · `encuestas.md` §0 y §4.7 (`#754`) · `analitica.md` §0 y §4.5 · `isla-y-landing-nueva.md`
> §4.11 · `celebracion-e-invitacion.md` §0 · `waiver-por-reserva.md` §0 · `analitica-fiesta.md` §0 ·
> `google-business-profile.md` §0 · `sidebar-spa.md` §0 · Actualizado: 2026-09-29, noche (cierre: los correos empiezan en la sesión siguiente, por la R1).
> Este fichero lo escribe SOLO el agente de este carril (`DECISIONES #621`). Techo **32 KB** (`#724`). El
> contador de la suite va en el trailer del commit (`#618`), no aquí. **Se muda, no se raspa**: el detalle de
> una feature baja a su spec (las trampas por tanda de la analítica T1→T5 viven en `analitica.md` §4.9,
> mudadas verbatim el 24-09; las de la ficha de Google en su §9.1; las de la invitación en su §10.20).

## Foto (2026-09-29)

- ✅ **LA R1a DE LOS CORREOS, EN `main` Y APROBADA** (29-09, el owner: «visto bueno, buen trabajo»; `correos-rediseno.md` §4.1.1
  y §4.1.2, `#803` el botón como el diseño, `#804` los enlaces legales se quedan): la plantilla del diseño en el molde, los 28
  sin tocarlos; arnés 39/39, sonda 28/28; la hoja de PlayJump, empujada al repo de la instancia. ⚠️ **MONTADO EN LOCAL**:
  `instancia.json` de la instancia con `hojas.correo` a mano, SIN commitear (lo declara plataforma, buzón: antes de un `pull`
  de la instancia, devolverlo con `git -C ../instancias/playjump checkout instancia.json`); ~70 filas de `email_sends` del
  cliente de sondas (`scripts/banco-correos.php`).
- **LOS CORREOS** (`specs/correos-rediseno.md`, `#800`→`#804`): la spec, medida con el
  censo HAY/FALTA contra el zip (§1), y cuatro decisiones del owner: el 7 sigue siendo la encuesta (`#801`); las ocasiones, al
  final; el orden plantilla → textos editables → reserva → comerciales → felicitaciones; y **los TEXTOS, editables desde el
  panel** con estructura fija, en es/en/fr y con permiso propio (`#802`: «profesional y robusta, sin chapuzas»). La analítica,
  en pausa tras la T4 (T5 no se hace, `#800`; T6–T8 después).
- ✅ **LOS CORREOS SALIENTES, CERRADOS** (`specs/correos-salientes.md`, `#794`→`#797`, 29-09): C1 el registro y la vista previa,
  C2 los clics por envío, C2b la actividad y el aparato, C3 las aperturas y C4 «cuándo» en Marketing, todo en `main` y
  APROBADO por el owner (lo construido y lo que enseñó cada una, §4.7–§4.15). Queda en PRODUCCIÓN: encender los dos
  interruptores (Ajustes → Avanzado → Correos, APAGADOS de fábrica) cuando `/privacidad` y `/cookies` los nombren
  (`[PENDIENTE: asesoría]`). Fixture `c4-ojo.php` montado en local (`OJO=desmontar`).
- ⏸ **LA ANALÍTICA PARA DECIDIR, en pausa tras la T4** (`#755`): su foto por tanda, mudada verbatim a `CARRIL-SPA.md` §9
  (29-09); lo que queda, en «por dónde retomar» 2. ⚠️ La ficha del cliente en `zh_CN` pinta el parentesco de sus menores como
  la clave cruda (`admin.users.dependents.relationship_*` solo en es): sin arreglar.
- ▶ **LA FIESTA DEL SISTEMA NUEVO ES MÍA (`#765`, `[DECIDIDO owner]` 25-09)**: la lista de invitados, la invitación con
  su recibo y la autorización, vestidas con «Saltia» para la v2.0.0, en paralelo con la web pública. El traspaso,
  `isla-y-landing-nueva.md` §4.11; el censo HAY/FALTA, el método y las tandas, `specs/fiesta-sistema-nuevo.md` (§1.4,
  §4.6, §7). ⚠️ **El zip entra SOLO por plataforma** y llega con `git pull` de la instancia (`cd diseno && sha256sum -c`).
- ✅ F7·F8·F9 (27-09) y la analítica entera T1→T7 (`#735`, 24/25-09): aprobadas; su foto, en `CARRIL-SPA.md` §9.
- ✅ La fiesta del sistema nuevo, T1a→F6b en `main` (25/26-09): su foto, mudada verbatim a `CARRIL-SPA.md` §9 (29-09).
- ⚠️⚠️ **LO MONTADO EN LA BD LOCAL para el ojo del owner** (ajustes falsos, el experimento `carcasa` vivo, los fixtures
  `probe-ojo-*`, las fiestas `JW-OJO-F1…F8` con sus guiones `OJO=desmontar`, y la ISLA encendida por plataforma —«no
  deshacer sin él»—): el inventario entero, mudado verbatim a `docs/CARRIL-SPA.md` §8 (27-09). Todo reversible.
- De la foto, mudados verbatim a `CARRIL-SPA.md` §9 (29-09): la máquina montada para la fiesta y lo que tiene su ✅ sin desplegar.

## Por dónde retomar, en orden

0. ▶▶▶ **LA R1b, los iconos** (`correos-rediseno.md` §4.1: máscaras PNG de Lucide teñidas con GD, ⚠️ GD en producción sin
   verificar; la columna de iconos del pie y el círculo de la lista): su «al detalle» medido en la spec antes de codificar. El
   banco (`php scripts/banco-correos.php [filtro]`) y la sonda (`node storage/app/audit/sonda-correos-r1a.mjs N`) sirven igual.
   ⬜ **Y la LISTA DE INVITADOS del owner** (`#847`, buzón de plataforma del 29-09): nada de la autorización para quien invita,
   confirmado sin otra variante, «No podemos» aparte y suave, los adultos como el mockup (`fiesta-sistema-nuevo.md`).
1. ▶▶▶ **LOS CORREOS (`specs/correos-rediseno.md`: §0 → §1 el censo → §4 las tandas → §4.1 la R1 → §4.2 los textos;
   `#789`, `#800`→`#802`)**: el rediseño desde el zip de la instancia (la carpeta `paginas/correos` y su brief en `uploads`;
   `git pull` de la instancia y `sha256sum -c` antes de cada tanda). En orden, cada tanda con su «al detalle» MEDIDO en la spec
   antes de codificar, en `wip/…`, con su arnés y al ojo del owner en Mailpit (claro y oscuro, móvil y escritorio):
   **R1a** el documento y los bloques del diseño en el molde (`BrandedMailMessage`), con ROLES de color neutros en el producto
   y la hoja de correo de la instancia (✅ plataforma contestó el 29-09: `hojas.correo`, sin contrato nuevo; **al detalle en
   §4.1.1**, medido el 29-09; el botón principal, como el diseño: `#803`; aviso previo a correos en el buzón) · **R1b** los iconos (máscaras PNG de Lucide teñidas con GD; ⚠️ GD en producción, sin
   verificar) · **R1·T** los textos editables (`#802`: por correo, bloque e idioma; variables declaradas por correo y
   rechazadas al guardar si no existen; sin HTML salvo negrita y enlace; vista previa con datos de ejemplo —la de
   `correos-salientes.md` C1—; rastro; permiso propio; es/en/fr con «sin traducir»; NO editables lo legal, los datos ni los
   hechos que cambian) · **R1c** los 27 correos a la plantilla → **R2** la reserva (1, 1b, 2, 3, 4, 5, 6: el QR dentro, el
   calendario, «Cómo llegar», WhatsApp, responder al parque) → **R3** el 2b → **C1** los comerciales (11, el 12 ampliado,
   13a/b, 6b: consentimiento, «una vez», la baja LSSI) → **C2** las felicitaciones (TP·3c; el copy, con el owner) → **C3** las
   ocasiones. El 7: la encuesta y Google en su página de gracias; sin encuesta activa, solo la reseña (`#801`). Los grupos
   8–10, cuando el diseño tenga sus textos. También míos (`#789`): la PUERTA (su diseño, en el próximo zip) y **T2·9** (las
   reseñas en la API, `#771`: ver si queda la selección o se retira). ✅ **Lo pequeño de antes de la R1, hecho (29-09)**: el
   cajón dice «Tu cumpleaños» con solo «Opcional», como la isla (`#792`; fr «Ton anniversaire», el tú del cajón); visto en vivo
   por el owner («perfecto»). ▶ **La R1a empieza** por lo que no espera a plataforma: el molde con los roles neutros del producto.
2. ⏸ **LA ANALÍTICA PARA DECIDIR (`#755`, `specs/analitica-para-decidir.md`), EN PAUSA tras la T4**: en `main` y aprobadas
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
3. ✅ **LA FIESTA DEL SISTEMA NUEVO (`#765`, `fiesta-sistema-nuevo.md` ✅ `#743`): T1a→T4 y F1→F9 en `main` y aprobadas
   (`#747`→`#753`, §4.6–§4.15) — en código NO TIENE NADA PENDIENTE**; espera la v2.0.0 (las páginas vivas se visten con
   las hojas de la instancia, `#769`). **Si llega un zip nuevo**: `git pull` en la instancia, `git diff <antes>..HEAD` de
   `diseno/`, copiar `publico/instancia` a `public/`, `optimize:clear`, y una tanda F10 en §4. **A prueba**: «Invitar a
   más» (`#753`: si a los meses casi nadie lo usa, se quita). Ofrecido y sin pedir: las `transiciones` de `<x-pagina>`
   (`#781`). Para el ojo: `JW-OJO-F8`, `F7`, `F5`, `F3`, `F1`. **Reglas en pie**: el suelo sin JavaScript · `#739` · la
   firma y su prueba (`waiver-probatorio.md` §4.4) · hoja en blanco (§7.2·R1) · `#706`. Sueltos de la analítica de
   antes: **T5c** cuando el owner nombre la hipótesis (`#738`) · el `EXPLAIN` con volumen en staging.
4. ❗ **`audit-clock.sh` (27-09): 10/10 pases en rojo por tests AJENOS a la analítica** (los de la analítica, verdes en
   todos): `GoogleReviewImagesTest::test_el_barrido_no_toca_lo_recien_escrito` (10/10: el barrido mira el `mtime` REAL
   del fichero contra el reloj CONGELADO de Laravel; en producción coinciden: es del test), `InvitationSharingTest` (6
   casos, 404 en 4 fechas frontera; sin analizar) y `ScheduleFactsTest` (el conocido, Trampas ⏰). Arreglar antes de la v2.0.0.
5. **La invitación, lo que su ✅ NO cubre** (el `.ics` en un teléfono, el Turnstile real, `§7.2·R12`, `og:image` con bandas) y
   **los diez puntos de `§10.4.7·B`** (empieza por la RAÍZ: `matches()` y `takeSlotFor()` no son la misma regla).
6. De la Fase 4: el **ojo del owner en un teléfono de verdad** · el cuaderno de entrega del cajón · el botón del sistema.
   Del plugin, `/dod` y `/ligero` por ver (⚠️ al recrear el contenedor se pierde `socat`; la skill `/sonda` lo repone).

## Ficheros de este carril

**La fiesta del sistema nuevo (`#765`)**: las tres páginas enfocadas —`lang/*/guestform.php`, `guardian.php`,
`invitation.php` (lo que queda), el molde `gf-*` de `site.css` y `focused-layout` (hoy de las ENCUESTAS, mías también)—
y lo nuevo (T1a→T4): `app/Http/Fiesta/` (los modelos de página, `Temas`, `Marca`, `Sitio`), `resources/views/fiesta/**`,
`components/{fiesta,pieza}/`, `components/pagina-enfocada.blade.php`,
`resources/js/fiesta/`, `lang/*/fiesta.php`, `tests/Feature/Fiesta/`, `scripts/banco-fiesta.php`, `banco-fiesta/modelos.php`,
`mutar-fiesta.sh`; los valores de PlayJump, en `publico/instancia/` del repo de la instancia (se empuja allí, nunca a
`main`). El contrato de hojas (`InstanceViews::hojas`) es de plataforma (`#769`).
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
  `.vue`** (si no, 36 rojos que no son tuyos). Techo del chunk **297** (plataforma, `#695` y la calculadora del 25-09).
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
  reglas de botón apuntan al `button` y **no a `.btn`**.
- Commit por NOMBRE de fichero, nunca `git add -A`. La decisión, al final de `decisiones/700-799.md`.

## Buzón

- ❗ **Para plataforma (29-09, la R1a de los correos)**: la hoja de correo de PlayJump está escrita en el repo de la
  instancia, `publico/instancia/css/correo.css` (roles `--correo-*` en hex, con su pareja `-oscuro`; los valores del `tema()`
  de la plantilla sobre `tokens/colors.css` y `shape.css`). Cuando la empuje, **declárala en `instancia.json`**:
  `"hojas": { "correo": ["css/correo.css"] }` (junto a las de la fiesta). Sin la declaración, los correos salen neutros.

- Mis avisos a plataforma del 27→29-09 (`#754`/`#757`, la TP·1 `#792`, las C1→C3 `#794`→`#797`, la TP·3b `#793`, la T3d):
  ATENDIDOS por plataforma (su «Atendido», 29-09: «leídos y migrado»); retirados de aquí. El detalle, en el `git log`.
- ❗ **Para la web (28-09, TP·1 y `#793`)**: `/privacidad` tiene que nombrar la fecha de nacimiento del titular (opcional; para
  conocer al público, siempre en conjunto) y, con la TP·3c, las felicitaciones de cumpleaños (la del titular y la de sus hijos,
  solo con el opt-in de marketing y sin vender). Ya NO habrá exportación de personas (`#793`). `[PENDIENTE: asesoría]`.
- ❗❗ **Para plataforma (27-09 noche, `#754`/`#757`, YA EN CÓDIGO)**: (1) `scripts/deploy.sh` espera **11** tareas (entra
  `surveys:resolve-returns`, 04:20); toqué solo esa línea y su comentario. (2) El CONTRATO **1.46.0** es mío: te respeté la
  1.45.0 reservada; `PersonalDataExport.surveys` pasa a `ExportedSurveys` `{participations, sealed_responses}`. Tu
  siguiente, 1.47.0. (3) Un contrato nuevo de Booking, `PaidVisits` (`PaidVisitsReader`, binding en `BookingServiceProvider`),
  y uno de Platform, `VisitFacts` (en `AppServiceProvider`). (4) La tabla `survey_responses` se REHACE (migración
  `make_survey_responses_anonymous`): tras el `pull`, `php artisan migrate`.
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

### ❗ Para el carril de CORREOS (emisor: SPA, 29-09) — AVISO PREVIO: la R1a del rediseño cambia TU molde (`#789`, `#800`)
- `correos-rediseno.md` §4.1.1: `BrandedMailMessage` deja el Markdown y pinta con vistas propias (documento + bloques del
  diseño, oscuro por clase, roles de la hoja `hojas.correo` de la instancia); `vendor/mail/**`, `themes/brand.css` y el
  layout se retiran cuando los 28 pasen; `MailThemeTest` se reescribe por las MISMAS propiedades; `emails/partials/book` y
  `product-card` ganan clases de rol. Los verbos del molde (`hero`, `line`, `notice`, `outro`, `action`) no cambian. En
  `wip/correos-r1a` hasta el ojo del owner. Si algo tuyo está a medias ahí, dímelo aquí antes. Y `#803` (owner, 29-09)
  sustituye tu mapa del naranja en los correos: el principal de cada correo va en el color de acción, como el diseño.

### ❗ Para el carril de CORREOS (emisor: SPA, 19→26-09; pendiente de tu «atendido»)
- ▶ **Correos nuevos sobre tu molde, SIN tocarlo, todos en tus censos**: `VisitEveNotice` (`#717`),
  `GoogleBusinessLocationChanged` (`#725`), `AnalyticsLinkNotice` (T3a·4; el owner lo vio en Mailpit el 24-09), el del
  día siguiente de las encuestas (T7) y, **el 26-09, `BirthdayComingNotice`** («El cumple se acerca», `#750`: comercial,
  con la baja al pie y en `List-Unsubscribe`; `EmailUtmTest` 27 → 28; el asunto pasa sus datos en línea, como pide
  `MailInboxLineTest`). Te queda tu OJO en Gmail/Outlook. Tocado `GuestFormRequest` (solo con invitación). El diseño del
  24-09 trae **quince correos** rehechos (`paginas/correos.card.html` de la instancia): tuyos cuando llegue su tanda.

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
- **Plataforma 29-09** (la hoja de correo, `28dfdf15`: `hojas.correo` por la puerta de `#769`, sin contrato nuevo; el lector
  valida cada valor, caché ruta + `filemtime`, oscuro con nombres planos): leído y recogido en `correos-rediseno.md` §4.1.1;
  mi aviso (`#800`/`#801`), retirado. ▶ Cuando la R1a tenga la hoja de PlayJump, te digo aquí el fichero para el manifiesto.
- **Plataforma 29-09** (`#792`: «Tu cumpleaños» con solo «Opcional», como en la isla): ✅ hecho en el cajón el 29-09
  (`account.register.born_on*`, es/en/fr; los avisos de la API siguen diciendo «fecha de nacimiento»).
- **Plataforma 29-09** (`#847`/`#848`): (1) la LISTA DE INVITADOS del owner —nada de la autorización para quien invita; quien
  se añade a mano o dice «vamos», confirmado sin otra variante; «No podemos», aparte y en tono suave; los adultos, como el
  mockup—: leído, ⬜ **tarea MÍA**, en «por dónde retomar» 1; (2) aviso previo de `acceso-con-codigo.md`: el cajón (entrar,
  alta, recuperar, cambiar contraseña) será su tanda A4; y el correo del código, sobre la plantilla de la R1a.
- Del 25 al 28-09 (NORMAS `#842`, `#836`, T5f `#824`/`#825`, `#789`/`#822`, ESLint y `#788`/`#780`, `#765`) y web `#540`:
  mudados verbatim a `CARRIL-SPA.md` §9 (29-09).
