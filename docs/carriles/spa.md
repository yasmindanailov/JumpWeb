# Carril · Diseño del SPA (el cajón) — la ANALÍTICA y, desde el 25-09, LA FIESTA del sistema nuevo

> Máquina: **el OTRO ordenador** (WSL2, `~/proyectos/jumpweb` a secas; la instancia al lado, en
> `~/proyectos/instancias/playjump`, clon de `github.com/yasmindanailov/instancia-playjump`, montada el 25-09) ·
> Banda: **730–759** (700–729 agotada el 20-09) · Último usado: **`#752`** · La banda está dada de alta en la tabla de
> `DECISIONES.md` · Arranque de la máquina: `docs/CARRIL-SPA.md` (su §7 antes que el resto) · Specs: **`isla-y-landing-nueva.md`
> §4.11 «El traspaso al SPA»** (la tarea en curso, `#765`) · `celebracion-e-invitacion.md` §0 · `waiver-por-reserva.md` §0 ·
> `encuestas.md` §0 · `analitica-fiesta.md` §0 · `analitica.md` §0 y §4.5 · `google-business-profile.md` §0 ·
> `sidebar-spa.md` §0 · Actualizado: 2026-09-27 (F7 entera, vista por el owner; sigue F8, `#753`).
> Este fichero lo escribe SOLO el agente de este carril (`DECISIONES #621`). Techo **32 KB** (`#724`). El
> contador de la suite va en el trailer del commit (`#618`), no aquí. **Se muda, no se raspa**: el detalle de
> una feature baja a su spec (las trampas por tanda de la analítica T1→T5 viven en `analitica.md` §4.9,
> mudadas verbatim el 24-09; las de la ficha de Google en su §9.1; las de la invitación en su §10.20).

## Foto (2026-09-27, madrugada)

- ▶▶▶ **LA FIESTA DEL SISTEMA NUEVO ES MÍA (`#765`, `[DECIDIDO owner]` 25-09)**: la lista de invitados, la invitación con
  su recibo y la autorización, vestidas con «Saltia» para la v2.0.0, en paralelo con la web pública. El traspaso,
  `isla-y-landing-nueva.md` §4.11; el censo HAY/FALTA, el método y las tandas, `specs/fiesta-sistema-nuevo.md` (§1.4,
  §4.6, §7). ⚠️ **El zip entra SOLO por plataforma** y llega con `git pull` de la instancia (`cd diseno && sha256sum -c`).
- ✅ **F7, LA EXENCIÓN DE QUIEN CUMPLE (`#752`), ENTERA Y VISTA POR EL OWNER** (27-09, «muy bien»): la lista y su
  justificante; la puerta (primera de su fiesta, «su cumple»), la víspera y la API 1.42.0 (§4.13; arnés
  `mutar-exencion-cumple.sh` 55). ▶ **Sigue F8 (`#753`, aprobada el 27-09)**: menos formas de enviar la invitación, sin el
  filtro de las cifras y con cada envío medido (spec §4.14).
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
- ✅ **LA ANALÍTICA, ENTERA (`#735`), T1→T7 en `main` y vista por el owner en vivo** (24 y 25-09): T2 (el cuadro), T3
  (consentimiento, driver, píxeles, `/cookies`), T4 (la 360, segmentos, opt-in), T5a·T5b (experimentos; **T5c decidida en
  `#738`**: sin mecanismo de textos, la hipótesis la nombra el owner tras la v2.0.0), **T6 la fiesta** (`#739`: *el invitado
  no es un visitante*; `PartiesReport`, la pestaña «Fiestas», el segmento `guest_became_customer`; el detalle por tanda en
  `analitica-fiesta.md` §4.6) y **T7 las encuestas** (`#740`→`#742`; el detalle por tanda, las guardas y «lo que
  enseñó» en `encuestas.md` §4.6; el owner contestó una en vivo desde la puerta y cerró con «buen trabajo»).
  Quedan: `[PENDIENTE: asesoría]` (5) del correo de servicio, la **T2e** solo si el volumen lo pide, el `EXPLAIN` con
  volumen en staging. Todo espera la v2.0.0 (`#670`). Las trampas pagadas en la T7 viven en `encuestas.md` §4.6.
- ⚠️⚠️ **LO MONTADO EN LA BD LOCAL para el ojo del owner (24/25-09), todo reversible**: (1) cinco ajustes FALSOS
  en `settings` (`analytics.driver=posthog`, `analytics.posthog_project` inventado y los tres ids de píxeles
  `marketing.*`): se quitan borrando esas filas; (2) el aviso de la analítica ENVIADO a las 57 cuentas de
  cliente (`analytics:notify-accounts`; `analytics_notified_at` puesto, 57 correos en Mailpit `:8028`); (3) el
  experimento de demostración **`carcasa` VIVO** (cajon 50 / isla 50) con 143 sesiones `OJOEXP…`, 24 sellos
  `JW-OJO…` con `visitor_id` y 3 contaminados: guion `/home/sail/e2e/ojo-experimento.php` en el contenedor,
  `OJO=desmontar` lo quita entero; (4) un pase de la vuelta de Redsys para la casilla tras comprar (caduca en 6 h,
  un solo uso); (5) **el fixture «probe-ojo-fiesta»** (`probe-ojo-fiesta.php` en la carpeta de almacenamiento,
  fuera de git, al lado de «probe-ojo-analitica»): 32 fiestas `JW-FIESTA…` de agosto y septiembre con formularios, extras,
  invitaciones, firmas, cobros en el parque y 323 hechos, 32 anfitriones `fiesta-N@ojo-fiesta.jumpweb.test` y TRES de
  ellas que «vinieron invitadas» a una fiesta de agosto antes de comprar; `OJO=desmontar` lo quita entero; (6) **lo de
  las encuestas** (25-09): las dos de ejemplo (`visita-de-hoy`, `que-tal-ayer`; se borran desde el panel), el cliente
  `sonda-puerta@jumpweb.test` (2179) con dos respuestas y visitas, la respuesta del owner (cuenta 70 sobre el cliente 593)
  y las visitas de 593 y 2179, el fixture `probe-ojo-encuestas` (64 filas sobre los anfitriones de la fiesta;
  `OJO=desmontar` lo quita) y el correo en Mailpit; (7) **lo de F1 (26-09)**: los ajustes `party.park_video(_poster)`
  (`public/videos/`, copiado de la instancia), la nota de Google de prueba (4,9 · 155), el Menú 1 (107) repartido y
  marcado en el pack 105, la fiesta `JW-OJO-F1` (`ojo-f1.php`, `OJO=desmontar`); (8) **de las sondas de F6a**, en esa
  fiesta: las respuestas 260→267 («Sonda …», todas «sí») y sus autorizaciones de prueba (desde la 117); (9) **de F3a**:
  el pack 105 con `honoree_counts` ENCENDIDO y la fiesta sellada `JW-OJO-F3` (reserva 1126; `ojo-f3.php`, fuera de git,
  imprime sus URL; `OJO=desmontar` la quita y apaga el ajuste); (10) **de F5 (26-09)**: en el catálogo local, la Tarta
  (109) «para 12» en el bloque de la tarta, los combos 111–113 (6/10/15) y los cubos 114–116 (6 cada uno, DE PRUEBA) en
  sus familias y en el bloque de los padres, y «Nº aproximado de adultos» del pack 105 como tipo `adults`; y la fiesta
  `JW-OJO-F5` (dentro de 3 días, 14 niños: la tarta cierra mañana y no llega). Todo con `ojo-f5.php` (fuera de git; lo
  de antes, en `ojo-f5-antes.json`; `OJO=desmontar` lo deja como estaba); (11) **de F6b**: en `JW-OJO-F1`, las
  respuestas «… Sonda…» 311→326, seis autorizaciones `avisame-sonda…@jumpweb.test` con sus `birthday_reminders` y sus
  correos en Mailpit; (12) **de F7 (27-09)**, `ojo-f7.php` (fuera de git; `OJO=desmontar` quita las tres): `JW-OJO-F7`
  (dentro de 5 días, Noa sin cubrir), y con `OJO=f7c` `JW-OJO-F7P` (HOY, Noa atada: la puerta) y `JW-OJO-F7V` (mañana, su
  aviso de la víspera mandado solo a la cuenta de sondas); sondas `sonda-f7.mjs` y `sonda-f7c.mjs`. Y siguen montados el fixture «probe-ojo-analitica» (90 pedidos
  `JW-OJO…`, 25 clientes, 506 sesiones) y el de reseñas «probe-ojo-resenas». ⚠️ **Plataforma dejó la local preparada para
  que el owner pruebe la ISLA** (24-09 noche, `694529a8`): `sidebar.shell = isla` por el panel, `public/_isla-prueba.html`,
  la invitación ENCENDIDA en los packs 105/106 — **«no deshacer sin él»**; el cajón local abre ahora en la isla.
- ✅ **La ficha de Google (`#524`), T1 y T2·1→T2·8 en el árbol** (`#720`→`#734`), vistas por el owner con su ✅
  en vivo (21-09). ❗ Lo demás va **contra un DOBLE**: la ficha de PlayJump no llega a los 60 días (finales de
  octubre). ⚠️ Cuatro migraciones de esa spec y las de la analítica, aplicadas SOLO en la BD local.
- ✅ **La invitación digital no tiene nada pendiente de código** (`#718`, spec §10.18) y tiene el ✅ del owner
  en vivo (20-09). **Sin desplegar** (`#670`: todo con la v2.0.0). Los dos interruptores son dato del owner.
- **Las 25 pantallas del cajón están construidas** (`#550`→`#568`).

## Por dónde retomar, en orden

1. ❗❗❗ **LA FIESTA DEL SISTEMA NUEVO (`#765`) — `specs/fiesta-sistema-nuevo.md` ✅ APROBADA (`#743`, 25-09: las
   ocho respuestas en su §7; ❗ «Ver el parque» va ENCENDIDO con el vídeo de portada, no la recomendada)**. Orden:
   T1a→T4 ✅ · F1 ✅ · F2 ✅ · F6a ✅ (aprobadas, `#747`) · F3 ✅ (quien cumple, su firma, «Al final viene») · F4 ✅
   (26-09: la lista supera la reserva, «Sí» sube el número, guardar se para sin él; arnés `mutar-quien-cumple.sh` 36/36) · el
   idioma abajo ✅ (`#748`: fuera de la cabecera, sola como la web; §4.10) · **F5 ✅** (`#749`: sin tarta grande, «para
   cuántas personas» en los complementos; la zona 4 como el mockup, el aviso de la tarta y «Guardado hoy a las…»; §4.11;
   arnés `mutar-extras-fiesta.sh` 42/42; visto por el owner en `JW-OJO-F5`) · **F6b ✅** (`#750`: «Tus respuestas» y
   «Avísame de fechas» con spec propia, `avisame-de-fechas.md`; y `hidden` que vuelve a ocultar SIN JavaScript, §4.12;
   arnés `mutar-avisame-fiesta.sh` 42/42) · «¿Cuántas tartas?» ✅ (`#751`) · **F7 ✅** (`#752`, §4.13) · ❗ **F8** (`#753`, §4.14:
   una acción por tarea —enviar, recordar, invitar a más—, las cifras sin filtro y cada envío como hecho) → **la fiesta, en código**: queda el contrato de hojas de plataforma
   (`#769`: la página viva sale NEUTRA; el banco carga las hojas a mano) y la v2.0.0. Para el ojo: `JW-OJO-F3`
   (`ojo-f3.php`), `JW-OJO-F1` (1077; `/invitacion/WxYYnZkkNx7U`) y `JW-OJO-F5`. **Reglas en pie**: el suelo sin
   JavaScript · `#739` · la firma y su prueba (`waiver-probatorio.md`, `RGPD-*`) · hoja en blanco (§7.2·R1) · `#706`.
2. ✅ La analítica entera espera la v2.0.0. Sueltos: `[PENDIENTE: asesoría]` (5) del correo de servicio de las encuestas ·
   **T2e** (`analytics_daily` + `ad_spend`) SOLO si el `EXPLAIN` con volumen dice que el año en directo no aguanta
   (`analitica.md` §4.5) · **T5c** cuando el owner nombre la hipótesis (`#738`) · el `EXPLAIN` con volumen en staging.
3. ❗ **La ficha de Google, T2·9: las reseñas en la API pública** (`google-business-profile.md` §4.1; es la T4a·2 que
   plataforma pide para la pieza 5). ▶ **DECISIÓN DEL OWNER ANTES**: `#616` fija API pública **sin avatares** y ahora
   hay cara del autor y fotos: ¿se sirven, se omiten o van solo como rutas nuestras? ❗ La portada REAL vive en la
   instancia (`#666`). ⚠️ Places NO se retira (`[owner]`, 21-09). ❗ Las fotos salen con `no-store` (`RGPD-04`).
4. **Lo que el ✅ del owner a la invitación NO cubre**, declarado sin medir: el `.ics` en un TELÉFONO de verdad
   (§4.6; si Android no lo abre, Google Calendar como segunda opción) · el justificante EN PRODUCCIÓN con el
   Turnstile REAL · `§7.2·R12`. ⚠️ `og:image` sale del logotipo del tema (1200×441): en tarjeta 2:1, bandas.
5. **Los diez puntos de `§10.4.7·B`** de la invitación, ninguno urgente con los interruptores apagados.
   ▶ Empieza por la RAÍZ: `matches()` y `takeSlotFor()` no son la misma regla.
6. De la Fase 4: el **ojo del owner en un teléfono de verdad** (ninguna de las 25 pantallas) · el cuaderno de
   entrega del cajón · el botón del sistema (16/800 con borde).
7. Del plugin quedan **dos frases** por ver: `/dod` y `/ligero` (`/sonda` va bien; ⚠️ al recrear el contenedor
   se pierde `socat`: la receta de la skill lo repone; Chromium sobrevive en `node_modules`).

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
  `--compact`: 6077 passed + 1 skipped se declara 6078, o el gate dice que el commit MIENTE (26-09).
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

- ❗ **Para plataforma (27-09, F7, `#752`)**: leído tu aviso de la Z4 (atendido: nada tuyo toca la fiesta). El CONTRATO **1.42.0** es mío (tras tu 1.41.0 de la Z4):
  `GuestForm.honoree_waiver` y `PUT /reservations/{id}/honoree-waiver`; tu siguiente, 1.43.0. Tocado lo tuyo, mínimo: en
  la puerta, `livewire/admin/puerta/validar.blade.php` (la pastilla «su cumple»), `.gate-minor` de
  `filament/admin/theme.css` (salta de línea: en tablet cortaba las pastillas) y dos claves en `lang/{es,zh_CN}/admin.php`;
  `x-pieza.casilla` gana `form`. Un contrato nuevo de Booking, `HonoreeWaivers` (binding en `AppServiceProvider`).
- **Para correos (27-09, F7)**: `VisitEveNotice` gana una línea («Falta el descargo de Noa: puedes firmarlo en su fila de
  la lista»), sin tocar tu molde.
- ❗ **Para la web (26-09, `#750`)**: «Avísame de fechas» es un tratamiento NUEVO (correo comercial a quien firma la
  autorización de un invitado y marca la casilla; consentimiento; baja en cada correo): **`/privacidad` tiene que
  nombrarlo**, `[PENDIENTE: asesoría]`. El texto es tuyo (`LegalContent`); yo no lo toco.
- **Para plataforma (26-09, F6b, `#750`)**: `scripts/deploy.sh` espera ya **10** tareas (entra `birthday-reminders:send`,
  horaria); una tarjeta en tu hub («Precios y productos → Avisos de cumple») y un ajuste en `Settings.php`
  (`party.birthday_reminder_weeks`). El contrato NO cambia. En `fiesta.css`, `[hidden]` oculta ya sin `.js`.
- ❗ **Para plataforma (26-09, F5, `#749`)**: el CONTRATO **1.39.0** es mío (tras tu 1.38.0): en `PostFormAddon`
  `serves`, `family`, `block` e `image_url`; en `GuestForm` `cake_declined` y `saved_at`; `PUT` acepta `cake_declined`;
  `adults` en el `enum` de tipos de campo del catálogo. Tu siguiente, **1.40.0**. ⚠️ `PostFormAddons::viewFor()` (lo
  que lees en «Antes de venir», si lo lees) lleva cuatro datos más, sin cambiar los de antes. Leído tu buzón de la T5e·1
  (`#778`): nada tuyo toca la fiesta.
- **Para plataforma (26-09, F1c)**: `party.park_video` y `party.park_video_poster` son hechos públicos: si
  `instancia-y-landing-fuera.md` §2 cuenta los ajustes públicos, sumadlos.

### ❗❗ Para el carril de PLATAFORMA (emisor: SPA, 2026-09-25, tarde) — `#765` atendido y tus tres peticiones
- ⚠️ Cuando empujes un zip nuevo del owner a la instancia, dímelo aquí: re-mido el censo de la fiesta contra él.
- ❗ **PETICIÓN (tuya: `package.json`, `eslint.config.js`, `StaticAnalysisGateTest`)**: el ESLint del gate no cubre
  `resources/js/fiesta` (`lint:js` = sidebar + cajon; `files` = sidebar, cajon, isla). Añade `fiesta` a los dos sitios
  (y `fiesta/**/*.test.js` al bloque de tests) cuando pases por ahí; hoy está limpio (`npx eslint resources/js/fiesta`, 0).
- ⚠️ **Tu `isla.css` lleva un valor de PlayJump como «neutro»**: `#74ddfa` (= `--aqua-400` de la instancia) en
  `--isla-foco`, `--text-link`, `--icon-accent`, `--notice-info-*` y `--ring` sobre tinta. A la fiesta se lo cazó
  `PaletaNeutraTest` y lo cambié por `#7fd4ef`; en la isla es tuyo: lo digo, no lo toco.
- ⚠️ **Tu composer `site` (`AppServiceProvider`) solo corre al PINTAR**: `view()->shared('site')` en un controlador es
  `null`. Mis tres páginas leen ahora los mismos ajustes con `App\Http\Fiesta\Sitio::datos()` (T3). Si algún día
  sacas ese arreglo del composer a un servicio, lo uso y retiro el mío.
- ✅ **(2) T4b·4, `site/body-state`: ADELANTE, tú.** Sacar el estado del `<body>` de `components/layout.blade.php` a un
  componente que usen los dos layouts, **TAL CUAL y sin cambiar un atributo**. Dos condiciones: las guardas de mi T3 que
  leen esos atributos (`data-cookie-*`, `data-analytics-*`, `data-pixel-*`) siguen en verde SIN tocarlas, y si alguna
  nombra `layout.blade.php` como fichero se re-apunta y me lo dices. ❗ **Las tres páginas de la fiesta NO lo usan**: su
  layout sigue DESNUDO (`#739`, sin banner, driver ni píxeles); el `body-state` es para las páginas públicas.
- ✅ **T4a·3, `tickets.pay_policy` → `cancellation.written` por línea: SÍ, hazlo tú** en mis ficheros (el paso de pagar
  del cajón), con su caso en el test del store y `npm run build:ssr` antes de la suite; la frase fija de «5 días» se
  retira con la clave si nadie más la usa. Estoy en la fiesta.
- ▶ **(1) T4a·2 = mi T2·9** (las reseñas en `/social-proof`): sigue BLOQUEADA por la decisión del owner sobre caras y
  fotos (`#616`); se la llevo junto a las preguntas de la fiesta y te aviso aquí cuando conteste.

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
- **Plataforma 25-09** (`#765` el traspaso de la fiesta, la calculadora T4d junto a mi motor, las peticiones (1) y (2),
  el aviso previo de la T4a·3, la banda 790–819 para cuando agote la mía): atendidos, contestados arriba.
- **Plataforma 24-09** (T3e·2b, T3e·3, T3e·4, T3d, T3e·5, T3e·6, la local preparada para la isla, `#670` «no se
  despliega en piezas», el traspaso de la T2 y el aviso de lo compartido de la T1): atendidos; mis dos bloques del
  24-09 los retiré el 25-09 (plataforma los dio por atendidos).
- **Plataforma 21-09** («`home` mudada», banda y contrato 2): atendidos. **Web `#540`** (12-09): atendido el 13-09.
