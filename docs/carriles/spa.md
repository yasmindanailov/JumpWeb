# Carril · Diseño del SPA (el cajón) — los CORREOS (desde el 29-09), la ANALÍTICA y LA FIESTA del sistema nuevo

> Máquina: **el OTRO ordenador** (WSL2, `~/proyectos/jumpweb` a secas; la instancia al lado, en
> `~/proyectos/instancias/playjump`, clon de `github.com/yasmindanailov/instancia-playjump`, montada el 25-09) ·
> Banda: **910–939** (del owner, 02-10; 790–819 agotada con `#819`) · Último usado: **`#920`** (el siguiente, `#921`) · La banda está dada de alta en la
> tabla de `DECISIONES.md` · Arranque de la máquina: `docs/CARRIL-SPA.md` (su §7 antes que el resto) · Specs:
> **`acceso-con-codigo.md` §0** (la A4 del cajón ✅ en `main`) · `correos-rediseno.md` §0 ·
> `fiesta-sistema-nuevo.md` §4.17–§4.21 · `analitica-para-decidir.md` §0 (en pausa, `#755`) · `encuestas.md` §0 y §4.7
> (`#754`) · `analitica.md` §0 y §4.5 · `isla-y-landing-nueva.md` §4.11 · `celebracion-e-invitacion.md` §0 ·
> `waiver-por-reserva.md` §0 · `analitica-fiesta.md` §0 · `google-business-profile.md` §0 · `sidebar-spa.md` §0 · Actualizado: 2026-10-03, 11:54
> (la R2a y la R2b de los correos, en `main`; la R2d y la R2e, en `wip/correos-r2d`, esperan el ojo del owner en Mailpit).
> Este fichero lo escribe SOLO el agente de este carril (`DECISIONES #621`). Techo **32 KB** (`#724`). El
> contador de la suite va en el trailer del commit (`#618`), no aquí. **Se muda, no se raspa**: el detalle de
> una feature baja a su spec (las trampas por tanda de la analítica T1→T5 viven en `analitica.md` §4.9,
> mudadas verbatim el 24-09; las de la ficha de Google en su §9.1; las de la invitación en su §10.20).

## Foto (2026-10-02, 23:30)

- ✅ **LA LISTA DEL OWNER DEL 02-10, EN `main` con su visto bueno** («Visto bueno, buen trabajo», 02-10 noche): de `#876`, la
  fila 4 (el KIDS como el JUMP, sin código; la invitación sin complementos cancelados) y la 8 (la TA, `#911`); y P1 un plazo,
  P1·b sin aviso de la tarta, P2 la impar a lo ancho, P3 los grupos de opciones (`#914`, API 1.62.0) y P4 «Falta elegir…»
  (`fiesta-sistema-nuevo.md` §4.20–§4.21; arnés `mutar-grupos-de-opciones.sh` 24/24, verificadores del post-form verdes).

- ✅ **LA PUERTA ENTERA, EN `main` con el visto bueno del owner** (`puerta-nueva.md` §4.4): P1a (`#818`), P1b·P1c (`#819`),
  P2 y P3 (`#910`), y la copiada TRADUCIDA fuera de la reseña del día (`#874`, de plataforma: `->untranslated()` en
  `GateReviewOfTheDay::copied()`, con su prueba y su mutante, 1/1). Cada tanda, con su arnés verde. ▶ Falta el ENTERO, de
  noche («retomar» 1b). En el tracker, su fila nueva bajo «EL CAJÓN · FASE 4».
- ✅ **La Z6c·3 (la medida del B3) y la R1·T2 de los correos (`#809`), EN `main`** con el visto bueno; la Z6c·3, en
  «retomar» 3. La isla ya emite los tres `isla_*` (`useIsla.js`); de punta a punta (navegador → libro → informe), sin medir.
- 🟦 **La imagen de la invitación al compartir, I1→I3, EN `main` con el visto bueno del owner** («visto bueno ok», 02-10; la B,
  `#815`; `#816` sin caché; `fiesta-sistema-nuevo.md` §4.19): la dibuja el producto con GD en cada petición y la instancia pone
  el kit (`fuentes.imagen`, empujado: `084fb40`). Arnés `mutar-imagen-invitacion.sh` 33/33; sonda `sonda-imagen-invitacion.mjs`
  16/16. 🟦 hasta verla en WhatsApp en un teléfono, con el producto y la instancia desplegados (de noche, `#594`).
- ✅ `LinkIsland` L1–L3 (`#814`) y la A4 (`#810`→`#813`), en `main` y aprobadas: su foto, mudada verbatim a `CARRIL-SPA.md` §9 (02-10).

- ✅ La lista del owner, los complementos, R1a, R1b y R1·T de los correos y sus decisiones (`#800`→`#808`): sus fotos, en
  `CARRIL-SPA.md` §9 (mudadas verbatim el 02-10 por la tarde). ▶ La regla del owner: no se programa lo que el panel ya configura.
- ⏸ **LA ANALÍTICA PARA DECIDIR, en pausa tras la T4** (`#755`): su foto por tanda, mudada verbatim a `CARRIL-SPA.md` §9
  (29-09); lo que queda, en «por dónde retomar» 3. ✅ La ficha del cliente, entera en `zh_CN` (03-10); el panel en chino
  tiene aún **346** textos que salen como CLAVE (`PanelChineseCoverageTest`, trinquete: solo baja).
- ▶ **La fiesta del sistema nuevo es mía (`#765`)**: la lista, la invitación y la autorización, con «Saltia»
  (`specs/fiesta-sistema-nuevo.md`). ⚠️ **El zip entra SOLO por plataforma** y llega con `git pull` de la instancia; se
  comprueba con su `.sha256` (rutas relativas a `diseno/`: `awk` con la ruta entera, hay nombres con espacios).
- ✅ Lo aprobado de antes —F7·F8·F9, la analítica T1→T7 (`#735`), la fiesta T1a→F6b, la máquina de la fiesta y los correos
  salientes (`#794`→`#797`; en producción, sus dos interruptores esperan a `/privacidad` y `/cookies`)—: sus fotos, en `CARRIL-SPA.md` §9.
- ⚠️⚠️ **LO MONTADO EN LA BD LOCAL para el ojo del owner** (ajustes falsos, el experimento `carcasa` vivo, los fixtures
  `probe-ojo-*`, las fiestas `JW-OJO-F1…F8` con sus guiones `OJO=desmontar`, y la ISLA encendida por plataforma —«no
  deshacer sin él»—): el inventario entero, mudado verbatim a `docs/CARRIL-SPA.md` §8 (27-09). Todo reversible.

## Por dónde retomar, en orden

▶▶▶ **LA LISTA DEL OWNER DEL 02-10, EN `main`** (la foto; su «retomar» de antes, mudado verbatim a `CARRIL-SPA.md` §9). Lo que
queda, todo DATO o de otro: (a) la receta de la merienda en producción, con la v2.0.0 (`fiesta-sistema-nuevo.md` §4.21; la de
K3, §4.17); (b) en la local siguen montados `ojo-p3.php`, `ojo-kids.php` y `ojo-config.php` (`CARRIL-SPA` §8 (30), (29) y
(25)); (c) la hoja del MES: el tipo del campo de la edad y las tartas del JUMP, avisados a plataforma (buzón); (d) los tres
arneses de la lista sin correr (`DEUDA.md`). ⚠️ v1.1.0 no tiene ni la analítica ni `#mi-cuenta`: un QR impreso hoy abre la
portada y no cuenta nada hasta la v2.0.0.

0. ✅ Los complementos de la fiesta (K1–K3, `#806`→`#808`): su punto, mudado verbatim a `CARRIL-SPA.md` §9 (02-10). ▶ **Antes de
   proponer código, medir si el panel ya lo configura**; si algo ya existía, parar y decírselo al owner (`#808`).
1. ✅ **LA A4 DEL ACCESO CON CÓDIGO (el cajón), ENTERA EN `main`**: su punto, mudado verbatim a `CARRIL-SPA.md` §9 (02-10).
   ⚠️ El código, como la isla (`#812`): manda el servidor; si plataforma lo cambia, el cajón lo sigue.
1b. ✅ **Lo del zip (6) que el owner repartió al SPA** (`#861`; `isla-y-landing-nueva.md` §4.27), EN `main` con el visto
   bueno: `LinkIsland` (§4.18), la imagen de la invitación (§4.19; 🟦 hasta verla en WhatsApp) y **la Puerta entera**
   (`puerta-nueva.md` §4.4; montaje y sonda, `CARRIL-SPA.md` §8 (27)). ✅ **Su arnés, cerrado** (03-10, el owner eligió):
   la tanda de la reseña, lo único tocado desde la P3 (`#874`), re-pasada, **45/45** (7 min 40 s); el ENTERO (130, ~45 min),
   innecesario: cada mutante ya mordió con el filtro de su tanda y uno más ancho solo puede añadir mordiscos. Su historia por
   tandas, mudada verbatim a `CARRIL-SPA.md` §9 (02-10).
   ▶ **Lo siguiente: la R1c de los correos** («retomar» 2; su «al detalle», `correos-rediseno.md` §4.1.4).
2. ▶▶ **LOS CORREOS (`specs/correos-rediseno.md`: §0 → §4; `#789`, `#800`→`#804`)**: ✅ R1a, R1b y **R1·T** en `main` (la
   R1·T revisada: §4.2.2; su sonda y el medidor de bloques, `CARRIL-SPA` §8 (26)). ✅ **R1c EN `main`** (03-10, §4.1.4,
   visto bueno del owner; arnés 12/12). ▶▶ **AHORA, LA R2** (§4.3, `#915`→`#918`): ✅ **R2a y R2b EN `main`** (03-10,
   `c415b1ea`, visto bueno; la R2b es el 1, el 1b y el 2). 🟦 **R2d (el 3 y el 4) y R2e (el 5 y el 6) CONSTRUIDAS en
   `wip/correos-r2d`** (arneses `mutar-correo-r2d.sh` 10/10 y `mutar-correo-r2e.sh` 7/7): ▶ **esperan el ojo del owner**
   (`SITUACION=<situación> php scripts/banco-correos.php VisitReminderNotice` · `VisitEveNotice` · `OrderPaymentDeclined` ·
   `OrderExpiredWithoutPayment`) y, con él, a `main` (sin `CRITICAL_RE`). Con eso la R2 queda entera. Después, el punto 3:
   R3 el 2b → C1 los comerciales (11, el 12 ampliado, 13a/b, 6b) → C2 las felicitaciones (su copy, con el owner) → C3 las
   ocasiones; y la analítica (3). Cada tanda, «al detalle» medido → `wip/` → arnés → ojo en Mailpit. ▶ DATO
   de PlayJump tras desplegar: «Antes de venir» de la excursión (panel → producto) y el aviso de los calcetines. El resto del
   punto (el zip (6), el 7, los grupos 8–10, T2·9), mudado verbatim a `CARRIL-SPA.md` §9 (03-10).
3. ⏸ **LA ANALÍTICA PARA DECIDIR (`#755`, `specs/analitica-para-decidir.md`), EN PAUSA tras la T4**: ✅ el defecto de
   `OccupancyReport::missing()` que midió plataforma (29-09: contaba robots y personal), ARREGLADO en la TA·0 (`cleanEvents()`,
   `84524415`; comprobado el 03-10). Y de la A1 de plataforma (`#853`): el
   `password` de `CustomersReport::METHODS` ya es «con el formulario» (renombrarlo) y `user_logged_in` cuenta las vueltas de un
   dispositivo recordado. Y (02-10) el ancla de `mutar-analitica-decidir.sh:880` (A5): busca `$data['current_password']` y la
   línea dice hoy `$emailChanges ? $data['code'] : null`: re-apuntarla antes de la siguiente pasada. El faro de `/api/v1/events`
   sin conexión deja un error en la consola (plataforma, 30-09: mirar `navigator.onLine`). Y de la L2 de plataforma (`#878`,
   02-10): medir si la isla emite `line_added` en sus líneas y, si hace falta, la prop de origen (`otra_zona` | `otra_entrada`). ✅ **La Z6c·3, la medida del B3, EN `main` con el visto bueno del owner** (02-10; `analitica.md` §4.4:
   los tres `isla_*` al contrato, 1.60.0; `isla_accion` por visita en móvil en el informe; arnés `SOLO=B3` 10/10; los datos de
   prueba, desmontados). En `main` y aprobadas
   T0→T4, con TP·1→TP·3b y T3d (arneses `SOLO=<tanda>` de `mutar-analitica-decidir.sh`; sondas y fixtures `ojo-tp2.php` y
   `ojo-tp3.php` en `storage/app/audit/`, `CARRIL-SPA` §8 (17) y (22)); ⏸ T3e sin fuente (`#799`); ✗ T5 (`#800`); la TP·3c
   va con los correos (C2). Al retomarla: T6 cohortes → T7 pérdidas → T8 satisfacción (§4.12); el cruce por EMPLEADO,
   `[PENDIENTE: owner]` (27-09, sin respuesta).
   **Cómo se trabaja una tanda de la analítica** (con sus trampas y lo `[PENDIENTE: asesoría]`): mudado verbatim a
   `CARRIL-SPA.md` §9 (02-10 noche).
4. ✅ **LA FIESTA DEL SISTEMA NUEVO**: en código sin nada pendiente; su punto entero (el zip nuevo, lo que está a prueba, las
   reglas en pie), mudado verbatim a `CARRIL-SPA.md` §9 (30-09).
5. ✅ Los rojos de `audit-clock.sh`, cerrados el 03-10: su punto, mudado verbatim a `CARRIL-SPA.md` §9.
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
- Dos trampas viejas de la máquina (el `laravel.log` de 1,35 GB y Chromium al recrear el contenedor): mudadas verbatim a
  `CARRIL-SPA.md` §9 (02-10).
- `SidebarDomContractTest` renderiza el BUNDLE: `npm run build:ssr` antes de la suite, también tras traer
  commits del cajón, tras un arnés de mutación (restaura el árbol, no el bundle) **y siempre que toques un
  `.vue`** (si no, 36 rojos que no son tuyos). ⚠️ **Aunque la tanda solo mute PHP** (02-10, 38 rojos tras `SOLO=TA`): el
  arnés restaura y TOCA todos sus `FICHEROS`, también los JS, y el bundle sale «rancio» por fecha. Techo del chunk del motor **302** (`#812`, 01-10: 301,79).
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

- ❗ **Para plataforma (03-10), la v2.0.0 y el SPA**: todo lo mío, en `main` (P1→P4, TA, R1c, Puerta, reloj); el planificador
  da sus **14** con tu `#863`; `hojas.correo`, declarada. Tras desplegar, del owner en el panel: la merienda (`fiesta-sistema-nuevo.md`
  §4.21) y K3 (§4.17), los «90 minutos» del 12 y los interruptores de la invitación. ❗ **Trinquete nuevo**:
  `PanelChineseCoverageTest` congela en 346 las claves de `admin` sin `zh_CN` (salen como CLAVE): una nueva sin chino, rojo.
- ❗ **Para plataforma (03-10, madrugada), tu `ScheduleFactsTest` (F5·T2, `#641`)**: le puse un ancla de reloj (`AHORA`, un
  miércoles a media tarde del parque, en `setUp()`): «abierto ahora» abre el parque de 00:00 a 23:59 y caía si la suite corría
  en el último minuto del día del parque (`audit-clock.sh`, «medianoche de MADRID»). Solo el test; tu código, intacto. De
  paso, una bomba MÍA desactivada (`InvitationSharingTest`, rojo desde el domingo 04-10 a las 19:00): si siembras una fecha
  absoluta, ancla el reloj (`TESTING.md` §2.septies); el barrido entero no corre desde el 27-09.
- ❗ **Para plataforma (02-10 noche), tu hoja A4 del MES (`#879`), medida a petición del owner** («que salga la merienda
  elegida y la tarta»): SÍ salen, con «Todo» (lo fija `ChoiceGroupsParkTest::test_the_month_sheet_prints_the_chosen_snack_and_the_cake`).
  Dos de DATOS, tuyas: (1) con «Solo cumpleaños» el mes sale VACÍO en la local: los packs 105 y 106 preguntan la edad con un
  campo `number` y no `celebrant_age` (tu `isBirthday()` lee `celebrantAgeFieldKey()`), y `ProductionSeeder` lo siembra igual;
  mide producción antes de que el parque la imprima. (2) Las tartas del JUMP (106) no tienen el bloque «La tarta» en la local:
  salen en la línea del producto («+ 2 × Tarta»), no en «Tarta». Tu código, sin tocar.
- ❗ **Para plataforma (02-10 noche), `#912` (owner): UN plazo para toda la lista de invitados, 24 h** (§4.20 de la fiesta),
  EN `main`: los complementos cierran con la lista (`ProductAddon::postformCutoffHours()` = `packs.guest_count_cutoff_hours`;
  `PostFormAddons::deadlineFor()` delega en `GuestCountPolicy` y conserva su 2.º parámetro por tu `AntesDeVenir`). Tu
  `BirthdayComparison` pinta ya el MISMO plazo en cada complemento: dilo una vez o quítalo. La API conserva `closes_at`. En
  tu `AntesDeVenir`, sin tocar, ya no se alcanzan `muchos_plazos` ni los grupos por día (su comentario nombra la columna, sin
  lectores: `DEUDA.md`); sus dos casos de `MeReservationBeforeVisitTest`, reescritos por mí al plazo único.
- ❗ **Para plataforma (02-10 noche), la fila 8 de tu reparto (`#876`), EN `main`** (`analitica-para-decidir.md`
  §4.15): «Clientes» cuenta las altas por la campaña de su visita, el mostrador (del rastro de «Crear pedido») y el resto. Tu
  enlace del cartel, VERIFICADO de punta a punta en la local y sin consentimiento (`scripts/sonda-cartel.mjs`, 10/10). Tus
  `SelfSignup`/`GoogleSignup`, intactos: la campaña la pone el `Recorder` por la marca del contrato. ❗ **`#911` (owner): el
  alta guarda su campaña aunque no haya «análisis», como el pedido**. Tu punto 16 de `/privacidad` (`textos-legales.md`) dice
  que sin «análisis» no se vincula la navegación a la cuenta: tiene que nombrar que el pedido y el alta guardan de qué campaña
  llegaron, sin saber quién navegó (con la asesoría, antes de publicarlo). `RGPD-07` (2), ya al día.
- ❗ **Para plataforma (02-10 noche), `#914` (owner): los GRUPOS DE OPCIONES** (§4.21 de la fiesta; EN `main`):
  `addon_choice_groups` por producto (título traducible, «hay que elegir»). (1) Contrato
  **1.62.0** (sobre tu 1.61.0; el siguiente, tuyo): `PostFormAddon` gana `included`/`per_guest`/`group` y `GuestForm`,
  `choice_groups`. (2) Toqué tu `DailyReservationsSummary` lo justo: la columna «Merienda» dice además «¿Qué merienda?: sin
  elegir» (`snackOf()`, de `PostFormAddons::unansweredRequiredGroups()`; precarga `ticketType.choiceGroups`); tus anclas de
  `mutar-resumen.sh`, intactas. Tu `isSnack()` toma CUALQUIER grupo por merienda: con dos grupos mezclaría; ahora tienen título.
  (3) Los grupos al reservar ya pueden tener título: tu L2 (`#881`) puede leerlo en vez de «¿Qué menú?» (pídemelo en el
  catálogo). (4) «Antes de venir» podría pedir «Elige la merienda» con `unansweredRequiredGroups()`: tuyo decidirlo.
- Mis avisos a plataforma del 27→30-09 (`#754`/`#757`, la R1b, `#807`/`#808`, la R1·T —su aviso previo y el de `main`—, el
  rojo de `ManualOrderIgnoresMinAdvanceTest` y el previo de la A4) y el de la Z6c·3 en `main` (02-10): LEÍDOS por plataforma
  (su «Atendido»); retirados. El detalle, en el `git log`.
- Los mensajes a plataforma del 26-09 (F6b, F5, F1c), ya leídos, y los dos «Para correos» del 27-09: mudados verbatim a
  `CARRIL-SPA.md` §9.

- Los avisos a plataforma del 25→27-09 (el composer `site`, T4a·2 = mi T2·9, el `--warn-100`): mudados verbatim a
  `CARRIL-SPA.md` §9 (03-10). ⚠️ Sigue en pie: cuando empujes un zip nuevo del owner a la instancia, dímelo aquí.

- Los dos avisos al carril de CORREOS (29-09 y 19→26-09; hoy los correos son de este carril, `#789`): mudados verbatim a
  `CARRIL-SPA.md` §9 (30-09).

- El aviso al carril de la WEB (24-09: la T3 de la analítica tocó lo suyo; me llevé `#524`), sin su «atendido» —la web duerme
  desde el 16-09—: mudado verbatim a `CARRIL-SPA.md` §9 (02-10). Lo vivo, de quien lleve la landing (hoy plataforma): el
  pendiente del feed social en `/cookies` (`#592`) y `MIN_REVIEWS = 1` contra el «umbral de 10» de `google-reviews.md`.

### Atendido
- **Plataforma 03-10** (`7891a2b1`, mis dos casos de `#912` que dependían de la hora, anclados; y el contrato **1.63.0**,
  `stay_minutes`/`stay_per_unit`): leídos. Los casos querían decir «el plazo de la LISTA venció»: su ancla se queda y quité
  el `cutoff: 48` de sus ayudantes, que escribía la columna sin lectores. Mi próxima versión del contrato parte de 1.63.0.
- **Plataforma 02-10 noche** (la L2, K1·K2 `#878`: la isla compone pedidos de VARIAS líneas, sin el `addToCart` del motor; la
  prop de origen de `line_added` es de mi contrato de eventos): leído; a la analítica («retomar» 3): medir antes si la isla
  emite `line_added` en sus líneas y, si hace falta, la prop (`otra_zona` | `otra_entrada`). Sus grupos al reservar (`#881`), con mi 1.62.0 al lado.
- **Plataforma 02-10 noche** (contrato 1.61.0, `guest_form` en la ficha; «¿Qué menú?» con condición): leído; mi 1.62.0 sale de ella.
- Del 02-10 (`#876`, la A5, la Z6e, la Z6g·1, `#874`/`#875`, `698dca75` y `318fec68`): mudados verbatim a `CARRIL-SPA.md` §9
  (03-10). ⚠️ Sus promesas siguen en pie: no cambio las props ni el marcado de `x-fiesta.invitacion`, ni la forma de
  `sidebar/code-input.js`, sin avisar; mi correo 8 usará `LoginCodes::shown()`; lo mío de `#875`, en «retomar» 2.
- Del 30-09 y el 01-10 (la A2a, la A2b y la A3, `/cookies`, `#857`→`#867`, el zip (6) y su reparto; mis previos de la A4, de
  `#815` y de `LinkIsland`, leídos por plataforma): mudados verbatim a `CARRIL-SPA.md` §9 (02-10).
- Del 25 al 29-09 (la A1 `#853`/`#854`, T6f/T6g, la hoja de correo, `#792`, `#847`/`#848`, `#846` —no cambio la firma de
  `createMissingReporter`/`missingMonths` sin avisar—, `#850`/`#851`, NORMAS `#842`, `#836`, T5f, `#789`/`#822`, ESLint,
  `#788`/`#780`, `#765`) y web `#540`: mudados verbatim a `CARRIL-SPA.md` §9 (29-09 y 30-09 noche).
