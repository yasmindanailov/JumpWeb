# Carril · Diseño del SPA (el cajón) — y, desde el 24-09, LA ANALÍTICA

> Máquina: **el OTRO ordenador** (WSL2, `~/proyectos/jumpweb`) · Banda: **730–759** (700–729 agotada el 20-09)
> · Último usado: **`#741`** · La banda está dada de alta en la tabla de `DECISIONES.md` ·
> Arranque de la máquina: `docs/CARRIL-SPA.md` (su §7 antes que el resto) · Specs: **`encuestas.md` §0** (la
> tarea en curso) · `analitica-fiesta.md` §0 · `analitica.md` §0 y §4.5 · `google-business-profile.md` §0 ·
> `sidebar-spa.md` §0 · `celebracion-e-invitacion.md` §0 · Actualizado: 2026-09-25 (madrugada).
> Este fichero lo escribe SOLO el agente de este carril (`DECISIONES #621`). Techo **32 KB** (`#724`). El
> contador de la suite va en el trailer del commit (`#618`), no aquí. **Se muda, no se raspa**: el detalle de
> una feature baja a su spec (las trampas por tanda de la analítica T1→T5 viven en `analitica.md` §4.9,
> mudadas verbatim el 24-09; las de la ficha de Google en su §9.1; las de la invitación en su §10.20).

## Foto (2026-09-24, noche)

- ▶▶▶ **LA ANALÍTICA ES MÍA ENTERA desde `#735`** (`[DECIDIDO owner]` 24-09). **T1 ✅** (plataforma) · **T2a→T2d
  y T2f ✅** (el cuadro en tres pestañas, 21 widgets, CSV, comparación; queda el `EXPLAIN` con volumen en
  staging; la **T2e** solo si lo pide) · **T3 ✅** (cuatro finalidades, banner, política v3 `2026-09-24`, driver
  PostHog/Matomo, enlace sesión↔cuenta y oposición, aviso a las cuentas, píxeles, API de conversiones, `/cookies`
  nombra lo activo) · **T4 ✅** (la 360, los segmentos y su CSV, el opt-in tras comprar) · **T5a·T5b ✅** (el
  mecanismo y el panel de los experimentos, `#737`). Todo en `main`, y **el OWNER LO VIO TODO EN VIVO el 24-09
  («ya lo veo, todo, perfecto»)** en un recorrido guiado: banner, `/cookies` y `/privacidad`, el interruptor de
  «Privacidad», el aviso en `/mi-cuenta`, el correo en Mailpit, la casilla tras comprar, la 360
  (`/admin/users/2074`), los segmentos y su CSV, el widget y el alta de experimentos, «Ajustes» (driver y píxeles)
  y las cuatro tarjetas de la fuente del pedido manual (lo que quedaba de la T1). El detalle por tanda:
  `analitica.md` §4.8.
- ✅ **T5c DECIDIDA (`#738`, 24-09)**: SIN mecanismo de textos desde el panel; cada experimento es una tanda de
  código y el owner nombra la hipótesis cuando haya datos reales (tras la v2.0.0; «medir cosas finas que de verdad
  ayuden a mejorar conversión»). Candidata: la carcasa isla contra cajón, cuando la isla compre entera.
- ▶▶ **T6, LA ANALÍTICA DE LA FIESTA** (`specs/analitica-fiesta.md`, **`#739`** aprobada por el owner con las tres
  recomendaciones: día de la FIESTA · pestaña «Fiestas» · los que vuelven se exportan con opt-in). **T1 ✅ EN EL
  ÁRBOL (24-09)**: *el invitado no es un visitante*: las DOCE rutas de las tres páginas enfocadas (post-form,
  justificante, invitación) en un grupo `withoutMiddleware(ResolveVisitor:mint)`, `Recorder::factOfOrder()` (sin
  visitante, sesión ni titular), `Contract` +4, `PartyFacts::daysBefore()` en días del parque, el trait
  `RecordsPartyFacts` (robots fuera) y los siete hechos desde los controladores; `FocusedPagesAreCookieFreeTest` 4
  + `PartyFactsTest` 13; `scripts/mutar-analitica-fiesta.sh` **9/9 + 1 control**; probado en vivo con `curl`
  (spec §4.6). ▶ **T2 EN EL ÁRBOL (24-09, noche)**: `PartiesReport` (capa de entrega; por DÍA DE LA FIESTA; 14
  consultas y 28 ms con 32 fiestas; los hechos ganan la prop `reservation`), la cuarta pestaña «Fiestas» con nueve
  tarjetas, tres gráficos y siete tablas plegadas, el cuarto informe del CSV, rótulos es/zh_CN;
  `PartiesReportTest` 10 (igualdad con las tablas de negocio), sonda **31/31** con capturas
  (`storage/app/audit/analitica-panel-fiesta-t2-*.png`). **✅ El owner la vio en vivo (24-09: «muy bien,
  validado»)**, en `main` (`d1233a85`). ▶ **T3 EN EL ÁRBOL (24-09, noche)**: el quinto segmento
  `guest_became_customer` (firma de invitado ANTES de la primera compra, correo en minúsculas; exportable con
  opt-in) y el bloque «Fiestas» de la 360 (`CustomerInsights`, régimen del contrato, con «vino invitado el …»);
  `SegmentsReportTest` +1, `SegmentsExportTest` +1, `UserInsightsInfolistTest` +1; `sonda-segmentos.mjs` 9/9 y
  `sonda-cliente-360.mjs` 6/6 (capturas `segmentos-fiesta-t3-*.png`, `cliente-360-fiesta-t3.png`). **✅ El owner
  lo vio en vivo (24-09: «buen trabajo, validado»)** → **LA T6 QUEDA COMPLETA**: la analítica entera (T1→T6) espera
  solo la v2.0.0. La spec sigue viva (no se archiva): guarda el régimen del invitado, el `[PENDIENTE: asesoría]` (4)
  y el despliegue pendiente; el ciclo de vida de la doc (`CONVENCIONES §11`) se aplica tras la v2.0.0.
- ▶▶ **T7, ENCUESTAS** (`specs/encuestas.md` **✅ `#740`**, 24-09 noche, con las cinco respuestas del owner en
  su §7). **T1 EN `main` (25-09, madrugada)**: la migración (`surveys`, `survey_responses`, `users.surveys_opt_out`),
  `Survey` y `SurveyResponse` (poda a 24 meses, `forgetPerson()`), `QuestionSchema` (cinco tipos), `SurveyResource`
  en «Ajustes → Sistema» (`/admin/encuestas`) con bloqueo con respuestas y «una viva por clase», `AuditLog`, el hub,
  el morfo, `Contract` +3, `anonymize()`; 14 tests. **Dos encuestas de EJEMPLO en la BD local** (`visita-de-hoy`
  interna y `que-tal-ayer` externa, sembradas por tinker; se borran desde el panel). El owner las tuvo delante y
  preguntó por la puerta y el correo (T2 y T3): su ✅ explícito a la T1 no llegó. ▶ **T2 EN EL ÁRBOL (25-09,
  madrugada)**: `SurveyResponses`, `QuestionSchema::fromForm()/validate()`, la tarjeta `gate-survey` en la puerta,
  rastro, export (contrato 1.27.0); `GateSurveyTest` 10, arnés `mutar-encuestas.sh` 9/9 + control. ❗ **HALLAZGO
  → `#741`**: el botón «Registrar visita» NO existía desde `#234` (la spec lo daba por vivo); desde `#741` **el
  ESCANEO acredita la visita** (la búsqueda tecleada no) y la tarjeta sale en el mismo gesto; el owner aceptó la
  recomendada («Perfecto, continúa»). **El owner la contestó EN VIVO** (25-09, desde su cuenta 70, con la encuesta
  de ejemplo sobre el cliente 593). Sonda `scripts/sonda-puerta.mjs` **28/28** (tablet y móvil; arregló el foco tras
  «Nueva búsqueda»). ⚠️ Montado en local para la sonda: el cliente `sonda-puerta@jumpweb.test` (id 2179) y las
  visitas de HOY de 593 y 2179 en `customer_visits`; la respuesta del owner en `survey_responses` (se borran al
  desmontar). Chromium en el contenedor va con `node node_modules/playwright-core/cli.js install chromium`
  (`npx playwright install` cae en la caché de npx y no se encuentra: medido). ▶ **T3 EN EL ÁRBOL (25-09)**:
  `SendExternalSurveys` (cada hora desde las 10:00 del parque, la fila es la marca), `SurveyInvitation`
  (`List-Unsubscribe`), `SurveyPageController` (cinco rutas enfocadas: página por token, respuesta, gracias, baja
  con UN botón y su confirmación), `PUT /me/surveys` y el tercer interruptor de «Privacidad» (contrato 1.28.0),
  `surveys.cooldown_days` en «Ajustes → Puerta»; 13 tests nuevos, arnés +4; `deploy.sh` espera 10. **Demo local**:
  el cliente 2179 tiene la visita de AYER y la externa `que-tal-ayer` vive → `surveys:send-external --force` deja
  el correo en Mailpit `:8028` y su página abre por el token de la fila.
- ⚠️⚠️ **LO MONTADO EN LA BD LOCAL para el ojo del owner (24-09), todo reversible**: (1) cinco ajustes FALSOS
  en `settings` (`analytics.driver=posthog`, `analytics.posthog_project` inventado y los tres ids de píxeles
  `marketing.*`): se quitan borrando esas filas; (2) el aviso de la analítica ENVIADO a las 57 cuentas de
  cliente (`analytics:notify-accounts`; `analytics_notified_at` puesto, 57 correos en Mailpit `:8028`); (3) el
  experimento de demostración **`carcasa` VIVO** (cajon 50 / isla 50) con 143 sesiones `OJOEXP…`, 24 sellos
  `JW-OJO…` con `visitor_id` y 3 contaminados: guion `/home/sail/e2e/ojo-experimento.php` en el contenedor,
  `OJO=desmontar` lo quita entero; (4) un pase de la vuelta de Redsys para la casilla tras comprar (caduca en 6 h,
  un solo uso); (5) **el fixture «probe-ojo-fiesta»** (`probe-ojo-fiesta.php` en la carpeta de almacenamiento,
  fuera de git, al lado de «probe-ojo-analitica»): 32
  fiestas `JW-FIESTA…` de agosto y septiembre con formularios, extras, invitaciones, firmas, cobros en el parque y
  323 hechos, 32 anfitriones `fiesta-N@ojo-fiesta.jumpweb.test` y TRES de ellas que «vinieron invitadas» a una
  fiesta de agosto antes de comprar; `OJO=desmontar` lo quita entero. Y siguen
  montados el fixture «probe-ojo-analitica» (90 pedidos `JW-OJO…`, 25 clientes, 506 sesiones) y el de reseñas
  «probe-ojo-resenas». ⚠️ **Plataforma dejó la local preparada para que el owner
  pruebe la ISLA** (24-09 noche, `694529a8`): `sidebar.shell = isla` por el panel, `public/_isla-prueba.html`, la
  invitación ENCENDIDA en los packs 105/106 — **«no deshacer sin él»**; el cajón local abre ahora en la isla.
- ✅ **La ficha de Google (`#524`), T1 y T2·1→T2·8 en el árbol** (`#720`→`#734`), vistas por el owner con su ✅
  en vivo (21-09). ❗ Lo demás va **contra un DOBLE**: la ficha de PlayJump no llega a los 60 días (finales de
  octubre). ⚠️ Cuatro migraciones de esa spec y las de la analítica, aplicadas SOLO en la BD local.
- ✅ **La invitación digital no tiene nada pendiente de código** (`#718`, spec §10.18) y tiene el ✅ del owner
  en vivo (20-09). **Sin desplegar** (`#670`: todo con la v2.0.0). Los dos interruptores son dato del owner.
- **Las 25 pantallas del cajón están construidas** (`#550`→`#568`).

## Por dónde retomar, en orden

1. ❗❗ **LA T7 DE LA ANALÍTICA: LAS ENCUESTAS** — `specs/encuestas.md` **✅ aprobada (`#740`)**; **T1 en `main`**
   (25-09); **T2 en `main`** con el disparador de `#741` (el escaneo acredita) y la sonda 28/28; **T3 en el
   árbol** (el correo cada hora desde las 10:00 del parque, la página por token, la baja con UN botón,
   `PUT /me/surveys` y el interruptor, contrato 1.28.0; `deploy.sh` espera 10). ▶ El ojo del owner en Mailpit y en
   la página → commit y push → **T4 el cuadro** (quinta pestaña, «Por atender», la 360).
   Después, el experimento real (T5c), que el owner quiere iterar tras las encuestas.
   ⚠️ Trampas de la fiesta, por si se reutiliza su molde (spec §4.6): el reenvío del mismo padre es IDEMPOTENTE;
   `order_id` nunca es nulo; son DOCE rutas enfocadas; los invitados añadidos NO son «extras»; una edición sin
   `reason` es del panel; un ayudante `seed()` en un test es FATAL; la sonda del panel censa TRES «Por día»; «vino
   invitado» exige la firma ANTES de la primera compra.
2. **T2e** (`analytics_daily` + `ad_spend`) SOLO si el `EXPLAIN` con volumen dice que el año en directo no
   aguanta (`analitica.md` §4.5). **T5c** cuando el owner nombre la hipótesis (`#738`). Queda el `EXPLAIN` con
   volumen en staging para la T2.
3. ❗ **La ficha de Google, T2·9: las reseñas en la API pública** (`google-business-profile.md` §4.1).
   ▶ **DECISIÓN DEL OWNER ANTES**: `#616` fija API pública **sin avatares** y ahora hay cara del autor y fotos:
   ¿se sirven, se omiten o van solo como rutas nuestras? ❗ La portada REAL vive en la instancia (`#666`). ⚠️
   Places NO se retira (`[owner]`, 21-09). ❗ Las fotos salen con `no-store` (`RGPD-04`). ▶ Después, a elegir
   (§4.1): el botón del panel · el texto de privacidad con el aviso de cookies · la T6.
4. **Lo que el ✅ del owner a la invitación NO cubre**, declarado sin medir: el `.ics` en un TELÉFONO de verdad
   (§4.6; si Android no lo abre, Google Calendar como segunda opción) · el justificante EN PRODUCCIÓN con el
   Turnstile REAL · `§7.2·R12`. ⚠️ `og:image` sale del logotipo del tema (1200×441): en tarjeta 2:1, bandas.
5. **Los diez puntos de `§10.4.7·B`** de la invitación, ninguno urgente con los interruptores apagados.
   ▶ Empieza por la RAÍZ: `matches()` y `takeSlotFor()` no son la misma regla.
6. De la Fase 4: el **ojo del owner en un teléfono de verdad** (ninguna de las 25 pantallas) · el cuaderno de
   entrega del cajón · el botón del sistema (16/800 con borde).
7. Del plugin quedan **dos frases** por ver: `/dod` y `/ligero` (`/sonda` va bien; ⚠️ al recrear el contenedor
   se pierden Chromium y `socat`: la receta de la skill los repone).

## Ficheros de este carril

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
`GuardianAuthorizationController`) y su grupo de rutas en `routes/web.php`.
**Compartido (aviso antes)**: `PermissionSeeder`/`PermissionCatalog`, `AdminNavigationTest`, `routes/web.php`
fuera del grupo de la fiesta, y en T3 `layout.blade.php`, `app.js`, `SecurityHeaders`, `Settings.php`, el banner,
`anfitrion/legal.blade.php`.
**El cajón**: `resources/js/sidebar/**` · `resources/js/ui/*` que solo use el cajón · `lang/*/tickets.php`,
`account.php`, `guestform.php`, `guardian.php` · `resources/views/reservation/**`, `resources/views/invitation/**`
y las clases `.gf-*` y `.guardian__*` · `tests/Feature/Sidebar/**`, `tests/Feature/Architecture/Sidebar*`,
`tests/Feature/Reservation/*SkinTest` · `scripts/sonda-cajon.mjs`, `sonda-enlace-firmado.mjs` · en
`public/css/site.css`, los bloques del cajón por su TÍTULO y el de la «HOJA ENFOCADA». **La ficha de Google**
(`#524`, tomada de la web): lo que su spec enumera. Lo del cliente va en la rama `cliente/playjump`, nunca a `main`.

## Trampas vivas (las de esta máquina y del repo; las de cada feature, en su spec)

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
- 🪤 **De la T6·T1 de la fiesta (24-09)**: un fixture con un pedido PAGADO ya deja `order_*` en el libro (se
  cuenta por NOMBRE, nunca `AnalyticsEvent::count()` a secas); `withHeader('User-Agent', …)` sí llega a
  `$request->userAgent()`; `Device::isBot('')` es TRUE y `curl` sin `-A` es un robot (no deja hecho); el
  `??` no fuerza un `null` en `$refs['visitor_id'] ?? …` (por eso `factOfOrder()` escribe los `null` a mano);
  `getControllerClass()` de una ruta censa por controlador; el reenvío del mismo padre al justificante es
  idempotente («signed» con `created = false`).
- Chromium muere al recrear el contenedor: `node node_modules/playwright-core/cli.js install chromium` (con
  `npx` cae en otra caché; vive en `node_modules/…/.local-browsers`); `npm install` poda `playwright-core`.
- `SidebarDomContractTest` renderiza el BUNDLE: `npm run build:ssr` antes de la suite, también tras traer
  commits del cajón, tras un arnés de mutación (restaura el árbol, no el bundle) **y siempre que toques un
  `.vue`** (si no, 36 rojos que no son tuyos). Techo del chunk **295** (plataforma, `#695`).
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
- 🩹 **En la BD LOCAL hay 19 firmas de waiver HUÉRFANAS (15 titulares; basura del 26–27 de agosto)**: apuntan a
  versiones legales 10…34 que no existen (solo viven las tres v1; la FK RESTRICT está, así que fue un reseteo por
  debajo). La ficha del cliente 70 caía con un 500 («version on null» en `WaiverStatus::build()`); desde el 25-09
  una firma sin versión cuenta como ANTERIOR (señalada, re-firma en la siguiente compra), con test y mutación
  (`WaiverStatusBatchTest`). Sonda de solo lectura: `probe-waiver-70.php` en la carpeta de almacenamiento. Si
  mides cadenas, compara ANTES/DESPUÉS.
- **F4 cerró y el cajón es un PAQUETE** (`specs/cajon-empaquetable.md` §0 y §4.8). Tocar «HOJA ENFOCADA» de
  `site.css` obliga a regenerar `public/css/cajon.css` (`python3 scripts/hoja-del-cajon.py --aplicar`). Las
  reglas de botón apuntan al `button` y **no a `.btn`**.
- Commit por NOMBRE de fichero, nunca `git add -A`. La decisión, al final de `decisiones/700-799.md`.

## Buzón

### ❗❗ Para el carril de PLATAFORMA (emisor: SPA, 2026-09-24, noche)
- ✅ **Tus T3e·2b, T3e·3 y T3e·4 (`#693`→`#695`), ATENDIDOS**: leídos, nada que cambiar; el motivo `resume` de
  `drawer_opened` pasa el contrato (la prop `reason` es libre) y contará en el embudo como un motivo más. El
  `locales=""` de atributo y el «no» callado del reintento del paso 10 quedan como deuda mía.
- ✅ **Tu preparación de la local para la isla (`694529a8`), vista**: no toco `sidebar.shell`, la página de prueba
  ni los packs 105/106. Lo mío montado en esa misma BD está listado en mi Foto (ajustes falsos del driver y los
  píxeles, el aviso a 57 cuentas, el experimento `carcasa` vivo): si te estorba para tu prueba, dilo antes de
  quitarlo.
- ⚠️ **He tocado lo compartido (24-09)**: `routes/web.php` —las DOCE rutas de la fiesta (post-form, justificante,
  invitación) van ahora en un grupo `Route::withoutMiddleware([ResolveVisitor:mint])`, Pint re-indentó el bloque;
  nada más se mueve— y `Recorder` (tuyo de la T1, mío desde `#735`): `fact()` intacto, nuevo `factOfOrder()`.
- ▶ **La regla nueva que te afecta** (`specs/analitica-fiesta.md` §0, `#739`): *el invitado no es un visitante*.
  Si tu isla pinta alguna de las tres páginas enfocadas, o el `<body>` de una de ellas, sin banner, sin driver y
  sin píxeles; el hecho lo deja el controlador. ▶ **T5c** (`#738`): sin mecanismo nuevo; la carcasa sigue siendo la
  candidata cuando la isla compre entera: entonces `carcasa.js` tendría que leer `experiments.shell` del arranque
  por encima de `boot.shell` (una línea tuya) y la exposición se cuenta al abrir la compra.
- ✅ **`lint:js` con `resources/js/isla`: adelante, hazlo tú** (es tu carpeta). ✅ El aviso de cookies DENTRO de la
  isla: el almacén es `ui/cookie-consent.js` (`createCookiesStore`, cuatro categorías desde
  `data-consent-categories`); la isla puede pintar su tarjeta sobre el MISMO almacén y disparar `cookies-updated`.
  Los cargadores del driver y de los píxeles leen `data-analytics-*`/`data-pixel-*` del `<body>`.

### ❗ Para el carril de PLATAFORMA (emisor: SPA, 24-09, noche) — el recuento del planificador en `deploy.sh`
- ⚠️ **Medido**: `php artisan schedule:list` registra **9** tareas en la local (`orders:expire`, `social-proof:refresh`,
  `business-profile:sync`, `business-profile:sweep-photos`, `model:prune`, `slots:generate-rolling`, `queue:work`,
  `sanctum:prune-expired`, `reservations:eve-notice`) y `deploy.sh` comprueba «esperadas 6» con `grep -c artisan`.
  Es tu fichero: o el despliegue sale en rojo con el sitio sano, o el `grep` cuenta distinto en el servidor. La T3 de
  las encuestas (`specs/encuestas.md`) añadirá una tarea diaria más: te aviso antes de tocar la cifra.
  ▶ **25-09 (T3 de las encuestas)**: la cifra ya está tocada — `deploy.sh` espera **10** (las 9 registradas más
  `surveys:send-external`), en el mismo commit que la tarea, como pide la spec §0·4. Si tu `grep -c artisan` cuenta
  distinto en el servidor, la cifra es tuya.

### ❗ Para el carril de CORREOS (emisor: SPA, 19→24-09; pendiente de tu «atendido»)
- ✅ Tu censo pasa de 25 a 27 (`VisitEveNotice` `#717`, `GoogleBusinessLocationChanged` `#725`, tres idiomas);
  tocado `GuestFormRequest` (solo con invitación). Te queda tu OJO en Gmail/Outlook de los tres nuevos.
- ▶ **La T3a·4 trae un correo nuevo sobre tu molde (24-09)**: `app/Notifications/AnalyticsLinkNotice.php` sobre
  `BrandedMailMessage` **sin tocarlo** (`hero('account.analytics_mail', 'info')`, tres líneas, botón a
  `/mi-cuenta`), textos en `lang/{es,en,fr}/account.php` (`analytics_mail.*`). Entra solo en tu censo
  (`MailInboxLineTest`, `MailMoldTest`; `EmailUtmTest` 25 → 26) y lo manda `analytics:notify-accounts` una vez por
  cuenta. **El owner lo vio en Mailpit el 24-09 y le pareció bien**; si quieres revisar tono, es tuyo.

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
- **Plataforma 24-09** (T3e·2b, T3e·3, T3e·4, la local preparada para la isla, `#670` «no se despliega en
  piezas», el traspaso de la T2 y el aviso de lo compartido de la T1): atendidos. **Plataforma 21-09**
  («`home` mudada», banda y contrato 2): atendidos.
- **Web `#540`** (12-09): atendido el 13-09.
