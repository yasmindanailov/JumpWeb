# Carril · Diseño del SPA (el cajón) — la ANALÍTICA y, desde el 25-09, LA FIESTA del sistema nuevo

> Máquina: **el OTRO ordenador** (WSL2, `~/proyectos/jumpweb` a secas; la instancia al lado, en
> `~/proyectos/instancias/playjump`, clon de `github.com/yasmindanailov/instancia-playjump`, montada el 25-09) ·
> Banda: **790–819** (730–759 agotada el 28-09 con `#759`) · Último usado: **`#797`** · La banda está dada de alta en la
> tabla de `DECISIONES.md` · Arranque de la máquina: `docs/CARRIL-SPA.md` (su §7 antes que el resto) · Specs: **`analitica-para-decidir.md`
> §0** (la tarea en curso, `#755`) · `encuestas.md` §0 y §4.7 (`#754`) · `analitica.md` §0 y §4.5 · `isla-y-landing-nueva.md`
> §4.11 · `celebracion-e-invitacion.md` §0 · `waiver-por-reserva.md` §0 · `analitica-fiesta.md` §0 ·
> `google-business-profile.md` §0 · `sidebar-spa.md` §0 · Actualizado: 2026-09-29 (la C1 de los correos en `main` y aprobada; sigue la C2).
> Este fichero lo escribe SOLO el agente de este carril (`DECISIONES #621`). Techo **32 KB** (`#724`). El
> contador de la suite va en el trailer del commit (`#618`), no aquí. **Se muda, no se raspa**: el detalle de
> una feature baja a su spec (las trampas por tanda de la analítica T1→T5 viven en `analitica.md` §4.9,
> mudadas verbatim el 24-09; las de la ficha de Google en su §9.1; las de la invitación en su §10.20).

## Foto (2026-09-29)

- ▶▶▶ **LOS CORREOS SALIENTES** (`specs/correos-salientes.md`, `#794`; adelanta la T5 de la analítica, owner 28-09): ✅ **C1 el
  registro y la vista previa, en `main` y APROBADA** (29-09: «buen trabajo, visto bueno»; lo construido y lo que enseñó, §4.7)
  → ✅ **C2 los clics por envío** (`#795`, §4.8) **+ C2b «cuándo»** (`#796`, §4.10–§4.11: la línea de tiempo, el dispositivo, los
  robots, la vista previa desactivada) **+ C3 las aperturas** (`#797`, §4.12: el píxel con su interruptor y el «sí» de
  análisis; Apple no cuenta), en `main` y APROBADAS (29-09) → 🟦 **C4 «cuándo» en Marketing** (§4.14–§4.15: por correo y el
  mapa día × hora con sus sumas), en `wip/correos-c4`, falta el ojo (fixture `c4-ojo.php` montado en local). C2 y
  C3, `[PENDIENTE: asesoría]` antes de producción: el interruptor de la C2 sale APAGADO de fábrica (Ajustes → Avanzado → Correos).
- ▶▶▶ **LA ANALÍTICA PARA DECIDIR es la tarea** (`#755`, spec ✅). En `main` y APROBADAS por el owner: T0a·T0b·T0c (`#756`),
  T1 encuestas anónimas (`#754`/`#757`), T2 ocupación (`#758`) y, el 28-09, **T3a la forma** (`#759`: siete pestañas, solo
  pide la abierta, un catálogo de 58 cifras), **T3b el veredicto** (`#790` `[DECIDIDO owner]`: «normal» es el mín–máx de los 12
  periodos anteriores), **T3c·1 «lo que ha cambiado»** (`#791`) y **T3c·2 los objetivos del mes** (el botón al pie de «Resumen», la
  línea en la tarjeta; permiso `analytics.manage`). Lo construido y lo que enseñó cada una: spec §4.13. ▶ **La TP, el
  público** (`#792` `[DECIDIDO owner]` 28-09: la fecha de nacimiento del titular, entera y opcional; los padres por la edad de sus
  hijos con el opt-in de hoy; §4.14): ✅ **TP·1 la captura, en `main` y APROBADA** (28-09; su «al detalle» y lo que enseñó,
  §4.14; contrato **1.49.0**) · ✅ **TP·2 «Quién viene»** en `main` y aprobada → ▶ **TP·3, rehecha por `#793`** (anónima, sin
  exportar personas; felicitaciones sin vender; sin código hasta que el owner la vea). ⚠️ Visto de paso: la ficha
  del cliente en `zh_CN` pinta el parentesco de sus menores como la clave cruda (`admin.users.dependents.relationship_*` solo en es).
- ▶ **LA FIESTA DEL SISTEMA NUEVO ES MÍA (`#765`, `[DECIDIDO owner]` 25-09)**: la lista de invitados, la invitación con
  su recibo y la autorización, vestidas con «Saltia» para la v2.0.0, en paralelo con la web pública. El traspaso,
  `isla-y-landing-nueva.md` §4.11; el censo HAY/FALTA, el método y las tandas, `specs/fiesta-sistema-nuevo.md` (§1.4,
  §4.6, §7). ⚠️ **El zip entra SOLO por plataforma** y llega con `git pull` de la instancia (`cd diseno && sha256sum -c`).
- ✅ F7·F8·F9 (27-09) y la analítica entera T1→T7 (`#735`, 24/25-09): aprobadas; su foto, en `CARRIL-SPA.md` §9.
- ✅ **Esta máquina, montada para la fiesta (25-09)**: la instancia clonada en `~/proyectos/instancias/playjump` (el
  diseño con su sha256 verificado), `INSTANCIA_RUTA=/var/www/instancias/playjump` en el `.env` (ruta DEL CONTENEDOR),
  `public/instancia/` copiado de `publico/instancia/` (ignorado por git); la receta entera, en Trampas vivas 🏠.
- ✅ **LA FIESTA DEL SISTEMA NUEVO: T1a→T4, F1→F5, F6a y F6b EN `main` (25/26-09)**, F1/F2/F6a **aprobadas por el owner**
  (`#747`, menos «Crear mi QR», que no va) — **F3 (`#747`)**: quien cumple es la PRIMERA fila (ajuste del pack
  `honoree_counts`, sello `honoree_row` al reservar, ficha 0 con espejo en la invitación, clavada, con su plaza en el
  suelo y su firma de menor a cargo; la lista guardada a 0 px) y «Al final viene» (web, API 1.36.0 y sin JS) — las
  tres páginas con lo que HAY a
  0 px (`#768`), la piel vieja FUERA (T4), **la invitación ENTERA como el mockup (F1)**, **personalizar en tiempo real
  (F2)** y **la firma DENTRO del recibo (F6a, `#746`)**: una fuente para las dos pantallas (`ComposesGuardianForm`), el
  recibo contra `InvPagina` a 0 px en sus diagnósticos, y seis defectos arreglados que vio el navegador y no la suite
  (el 429 del cupo compartido, el reenvío mudo, el foco tras un ancla, la cabecera que desborda en móvil —el diseño
  también—, el color del botón, la fiesta llena con un «sí» atado). El detalle, spec §4.6; lo que enseñó, §4.7.
- ⚠️⚠️ **LO MONTADO EN LA BD LOCAL para el ojo del owner** (ajustes falsos, el experimento `carcasa` vivo, los fixtures
  `probe-ojo-*`, las fiestas `JW-OJO-F1…F8` con sus guiones `OJO=desmontar`, y la ISLA encendida por plataforma —«no
  deshacer sin él»—): el inventario entero, mudado verbatim a `docs/CARRIL-SPA.md` §8 (27-09). Todo reversible.
- ✅ Con su ✅ en vivo y sin desplegar (`#670`): **la ficha de Google** (`#720`→`#734`; el resto, contra un DOBLE hasta finales
  de octubre: `google-business-profile.md` §0) · **la invitación digital** (`#718`, `celebracion-e-invitacion.md` §10.18) · **las
  25 pantallas del cajón** (`#550`→`#568`).

## Por dónde retomar, en orden

1. ❗❗❗ **LA ANALÍTICA PARA DECIDIR (`#755`, `specs/analitica-para-decidir.md`)**: ✅ T0a·T0b·T0c · T1 · T2 · T3a · T3b ·
   T3c·1 · T3c·2 y **TP·1** (la fecha de nacimiento, `#792`; arnés `SOLO=TP1`, sondas `storage/app/audit/sonda-tp1-*.mjs`),
   **TP·2** («Quién viene»; arnés `SOLO=TP2`; fixture `ojo-tp2.php` montado, `OJO=desmontar`), todas en `main` y aprobadas →
   ▶ owner 28-09, «cerrar lo que queda», en este orden: **los correos salientes PRIMERO** (`specs/correos-salientes.md` ✅
   `#794`; ✅ C1, C2, C2b y C3 en `main`, aprobadas 29-09; 🟦 C4 «cuándo» en Marketing en `wip/correos-c4`) · **TP·3a** los tramos de los anuncios y **TP·3b** retirar «Exportar segmento» (`#793`) ·
   la TP·3c (felicitaciones) y el gasto en anuncios, ⏸ con el rediseño de la plantilla / aplazado; §4.14 y §4.9 → T3d el texto para IA (§4.7; lee `Changes` y
   los veredictos; sin PII ni celdas < 5) → T3e el SECTOR (primera búsqueda en §4.13: casi todo son medias, no rangos; AL OWNER
   antes de sembrar) → T4 cartera → T5 marketing y correos → T6 cohortes → T7 pérdidas → T8 satisfacción (§4.12). Los CRUCES de
   encuestas, con la T8; el cruce por EMPLEADO, `[PENDIENTE: owner]` (27-09, sin respuesta).
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
2. ✅ **LA FIESTA DEL SISTEMA NUEVO (`#765`, `fiesta-sistema-nuevo.md` ✅ `#743`): T1a→T4 y F1→F9 en `main` y aprobadas
   (`#747`→`#753`, §4.6–§4.15) — en código NO TIENE NADA PENDIENTE**; espera la v2.0.0 (las páginas vivas se visten con
   las hojas de la instancia, `#769`). **Si llega un zip nuevo**: `git pull` en la instancia, `git diff <antes>..HEAD` de
   `diseno/`, copiar `publico/instancia` a `public/`, `optimize:clear`, y una tanda F10 en §4. **A prueba**: «Invitar a
   más» (`#753`: si a los meses casi nadie lo usa, se quita). Ofrecido y sin pedir: las `transiciones` de `<x-pagina>`
   (`#781`). Para el ojo: `JW-OJO-F8`, `F7`, `F5`, `F3`, `F1`. **Reglas en pie**: el suelo sin JavaScript · `#739` · la
   firma y su prueba (`waiver-probatorio.md` §4.4) · hoja en blanco (§7.2·R1) · `#706`. Sueltos de la analítica de
   antes: **T5c** cuando el owner nombre la hipótesis (`#738`) · el `EXPLAIN` con volumen en staging.
3. ❗❗ **LOS CORREOS Y LA PUERTA, MÍOS desde `#789`** (owner con plataforma, 27-09 noche; en paralelo): los quince
   correos rehechos del diseño (`paginas/correos*.card.html` y `paginas/correos/` de la instancia) y la puerta (su diseño,
   en el PRÓXIMO zip). El orden frente a la analítica lo dice el owner. **T2·9** (las reseñas en la API): caras y fotos,
   decididas en `#771`, y `/reviews` (1.31.0) ya las sirve: al retomarla, ver si queda algo (la selección) o se retira.
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

- ❗ **Para plataforma (29-09, la C3 de los correos, `#797`)**: contrato **1.54.0**, mío (`ExportedEmailSend.opens`): la
  **1.53.0 sigue siendo tuya**. Toqué lo compartido: `routes/web.php` (el píxel `GET /e/{send}.gif`, fuera de sesión, cookies y
  visitante con `withoutMiddleware`: ojo, en Laravel 13 el CSRF del grupo es `PreventRequestForgery`), el contrato
  `ConsentLedger` (`accountConsentedNow()`, implementado en `CookieConsentLedger`), `Settings.php` (otro interruptor,
  `emails.track_opens`) y `AppServiceProvider` (un oyente y el morfo `email_open`). Migra (`email_opens`, `tracks_opens`).
- ❗ **Para plataforma (29-09, la C2 de los correos, `#795`)**: contrato **1.52.0**, mío (`ExportedEmailSend.clicks`); tu
  siguiente, 1.53.0. Toqué lo compartido: `Settings.php` (una sección «Correos a los clientes» en Avanzado, con el interruptor
  `emails.track_clicks` en `BOOL_KEYS` y `MANAGED`), `AppServiceProvider` (un oyente de `NotificationSending` y el morfo
  `email_click`), `RecordEmailClick` (el 302 que quita `jw_e`) y `EmailUtm::IGNORED_QUERY` (ya NO es la lista de la analítica:
  lleva además `jw_e`). Migra (`email_clicks` y `email_sends.tracks_clicks`). Y la C2b (`#796`): `emails.activity_viewed` en
  `AuditLog::ACTIONS` y `email_clicks.device` (otra migración).
- ❗❗ **Para plataforma (28-09, la TP·1, `#792`)**: `users.born_on`, entera y opcional (`BirthDatePolicy`: no futura, ≥ 18 el
  día del parque, ≤ 120). Contrato **1.49.0**, mío (`User`, `RegisterRequest`, `GoogleSignupRequest`, `ProfileUpdateRequest`
  —AUSENTE no la toca, `null` la borra—, `ExportedProfile`): tu siguiente, 1.50.0. **La isla**: el motor ya la reenvía si tu
  formulario la trae (`runRegister` y `runGoogleSignup` leen `form.born_on`; `profile.apply` solo si la clave viene): falta en
  `formularioDeAlta` y en tus «Tus datos» (la pieza `steps/BornOnField.vue` y sus textos `account.register.born_on[_hint]`,
  ya en el montaje). Toqué lo compartido: techos de `SidebarBundleBudgetTest` (299 → **301**; el motor ya pesaba 298,83) y
  `SidebarMountTest` (10.900), `SidebarBoot` (dos claves), `ApiContractTest`, `phpstan-baseline.neon` (−4, el mostrador),
  `CreateManualOrderPage` (el campo), `README`/`MODELO-DATOS`/`INVARIANTES`. Tras el `pull`, `php artisan migrate`.
- ❗ **Para plataforma (28-09, la C1 de los correos, `#794`)**: acorté la fila de los correos de `CLAUDE.md`; contrato **1.51.0**
  (el export lleva `emails`; tu 1.50.0 sigue siendo tuya). Toqué lo compartido: `AppServiceProvider` (dos oyentes y el morfo
  `email_send`), tu hub (`Correos enviados` en «Sistema»; `AdminNavigationTest` 27 tarjetas), `ListUsers` (un botón), el
  permiso `emails.view` (seeder y catálogo), `AuditLog::ACTIONS`, `routes/console.php` y `deploy.sh` (**12** tareas). Migra.
  29-09, rebasada sobre tu `0cef4365`: la 1.51.0 va encima de tu 1.50.0 (normas) y el recuento del `README` suma tu migración.
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
- **Plataforma 28-09** (`#836`): `openWith()` pasa el producto de la intención `fiesta` a `drawer_opened`: correcto.
- **Plataforma 27-09 noche, 2.º** (T5f `#824` y los hijos por producto `#825`: `outcome.js::confirmationLine` con
  `minors_only`, la 1.45.0 suya y la 1.46.0 mía, `catalog.js` al trozo `card`): atendido, nada mío choca.
- **Plataforma 27-09 noche** (`#789`: correos y puerta, míos; el zip (4) sin correos ni fiesta; `Medir` aplazado; T2·9;
  `#822`: `sugerirCorreo` mudada a `ui/correo.js`, `logica.js` la reexporta, 19/19 y 12/12): atendidos.
- **Plataforma 27-09** (ESLint sobre la fiesta y mis `/* global */` fuera, la isla sin valores de PlayJump, `#788` con la
  1.43.0, la Z4 y el zip tercero `#780`): atendidos; el zip tercero, portado a la fiesta en F9 (§4.15).
- **Plataforma 25-09** (`#765` el traspaso de la fiesta, la calculadora T4d junto a mi motor, las peticiones (1) y (2),
  el aviso previo de la T4a·3, la banda 790–819 para cuando agote la mía): atendidos, contestados arriba.
- **Web `#540`** (12-09): atendido el 13-09. (Los de plataforma del 21 y el 24-09, retirados por su emisor: en git.)
