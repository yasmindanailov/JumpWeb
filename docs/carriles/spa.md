# Carril · Diseño del SPA (el cajón) — y, desde el 24-09, LA ANALÍTICA

> Máquina: **el OTRO ordenador** (WSL2, `~/proyectos/jumpweb`) · Banda: **730–759** (700–729 agotada el 20-09)
> · Último usado: **`#736`** · La banda está dada de alta en la tabla de `DECISIONES.md` ·
> Arranque de la máquina: `docs/CARRIL-SPA.md` (su §7 antes que el resto) · Specs: **`analitica.md` §0 y §4.5** ·
> `google-business-profile.md` §0 · `sidebar-spa.md` §0 · `celebracion-e-invitacion.md` §0 · Actualizado: 2026-09-24.
> Este fichero lo escribe SOLO el agente de este carril (`DECISIONES #621`). Techo **32 KB** (`#724`). El
> contador de la suite va en el trailer del commit (`#618`), no aquí. **Se muda, no se raspa**: el detalle de
> una feature baja a su spec (las trampas de la ficha de Google viven en su §9.1 y las de la invitación en
> su §10.20, mudadas el 24-09).

## Foto (2026-09-24, mañana)

- ▶▶▶ **LA ANALÍTICA ES MÍA ENTERA desde `#735`** (`[DECIDIDO owner]` 24-09: «el otro agente cerró sesión
  para delegarte toda la analítica»). Plataforma dejó la **T1 ✅** (`f501a990`→`4d4c3aec`, contrato 1.19.0,
  arnés 19/19, `RGPD-07`, `PAY-21`, `SEC-13`); su traspaso, atendido. **T2→T5 aquí**; la T2 partida en seis
  con las tres peticiones del owner delante (dinero al detalle · registros · puerta): `analitica.md` §4.5.
  ✅ **T2a→T2d y T2f EN EL ÁRBOL (24-09)**: «Analítica» en `/admin/analitica` en **tres pestañas** (Dinero ·
  Clientes · Conversión, `?pestana=`) con 21 widgets — tarjetas, gráficos (once: series, horas, y seis de
  categorías: producto, canal, cómo se registran, embudo, fuentes, dispositivo) y las tablas **plegadas** al
  pie —, el filtro con **trimestre · año · a medida** (hasta un año, por mes más allá de 92 días, en directo) y
  **«Comparar con»** (periodo anterior · mismo periodo del año pasado), y el botón **«Descargar CSV»** (los tres
  informes, con `reports.export`, auditado, con la línea «Comparado con»). Spec §4.8; 100 casos en
  `tests/Feature/Analytics`; sonda 27/27 por pestaña, escritorio y móvil. **✅ El owner vio T2a–T2f en escritorio
  (24-09): «está perfecto»**; queda el `EXPLAIN` con volumen en staging. ▶ Sigue la **T3** (consentimiento y
  driver); la **T2e** (`analytics_daily` + `ad_spend`) solo si el volumen lo pide. ⚠️ **El fixture «probe-ojo-analitica» está
  MONTADO en la BD local** (90 pedidos `JW-OJO…`, 81 cobros, 9 devoluciones, 25 clientes
  `ojo-N@ojo-analitica.jumpweb.test`, 506 sesiones, dos meses): `OJO=desmontar` lo quita entero.
- ✅ **La ficha de Google (`#524`), T1 y T2·1→T2·8 en el árbol** (`#720`→`#734`), **vistas por el owner con
  datos y con su ✅ en vivo** (21-09, panel y tarjeta). Lo que enseñó cada tanda: `google-business-profile.md`
  §4.1; lo que pagó: §9.1. ⚠️ **Un fixture SIGUE MONTADO en la BD local** («probe-ojo-resenas», modos
  `montar`·`estado`·`caducar`·`correo`·`desmontar`): 7 reseñas, 1 oculta, 6 ficheros, dos ajustes falsos.
  ❗ Lo demás va **contra un DOBLE**: la ficha de PlayJump no llega a los 60 días (finales de octubre).
  ⚠️ **Cuatro migraciones de esa spec, aplicadas SOLO en la BD local**; las tres de la analítica, también.
- ✅ **La invitación digital no tiene nada pendiente de código** (`#718`, spec §10.18) y tiene el ✅ del owner
  en vivo (20-09). **Sin desplegar** (`#670`: todo con la v2.0.0): T5, T6 y T7 con la migración
  `order_items.eve_notice_at`; los dos interruptores son dato del owner.
- **Las 25 pantallas del cajón están construidas** (`#550`→`#568`).

## Por dónde retomar, en orden

1. ❗❗❗ **LA ANALÍTICA, T2→T5** (`specs/analitica.md`; §0, §4.1, §4.2, §4.5, §7.1 antes de tocar). **T2 en
   seis**: **T2a dinero ✅** → **T2b registros y puerta ✅** → **T2c embudo y fuentes ✅** → **T2d CSV ✅** →
   **T2f la forma ✅ (todo 24-09)** → **T2e `analytics_daily` + `ad_spend`**, SOLO si el `EXPLAIN` con volumen
   dice que el año en directo no aguanta (roll-up diario por comando programado `analytics:rollup` con `Window`
   de UN día por informe, JSON por día e informe; +1 tarea del scheduler → `deploy.sh` «esperadas 6→7»; los tres
   `for()` cosen «días cerrados desde el diario + hoy en directo»; `ad_spend` —plataforma, campaña, mes,
   céntimos— tecleado en un Resource pequeño de «Ajustes» para el CPA/ROAS de `SourcesWidget`). ✅ El owner vio
   la T2f en vivo (24-09). ▶ **Ahora la T3** (consentimiento y driver, spec §4.3 y §4.8 T3a/T3b: toca lo
   compartido —banner, `layout`, `app.js`, `SecurityHeaders`, la política en tres idiomas—, aviso dado y
   repetido al empezar).
   ⚠️ El fixture del ojo siembra también TRÁFICO (506 sesiones, 2.294 hechos, sellos con primer y último toque).
   ⚠️ **Los cortes por día van por HORA UTC en SQL y al día del parque en PHP** (`SqlTime::hourBucket()`,
   `Window::bucketKey()`), nunca `CONVERT_TZ`. ⚠️ El dinero se lee de `payments`, `payment_refunds`,
   `deposit_split` y `orders.total`: **ningún `OrderBook` por pedido**. ⚠️ El informe vive en la CAPA DE
   ENTREGA (`App\Filament\Analytics`) porque cruza cuatro módulos. Cada tanda: caso + presupuesto de consultas
   (`DB::enableQueryLog`, con el memo de `Setting` caliente) + sonda `scripts/sonda-analitica-panel.mjs`
   (credenciales por entorno) + **el OJO del owner en `localhost:8081/admin/analitica`**.
   ▶ Queda de la T1 el **ojo del owner** sobre la fuente del pedido manual (las cuatro tarjetas del paso de
   pago). ▶ Después **T3** (consentimiento y driver: toca `layout.blade.php`, `app.js`, `SecurityHeaders`,
   el banner y la política en tres idiomas —**compartido: aviso dado en el buzón**—), **T4** (la 360 y los
   segmentos), **T5** (experimentos). `[PENDIENTE: asesoría]` los tres puntos de §7 de la spec.
2. ❗ **La ficha de Google, T2·9: las reseñas en la API pública** (`google-business-profile.md` §4.1), que la
   dirección del owner (la landing consume la API) vuelve necesaria. `/api/v1/social-proof` sirve solo la
   cifra, sin `asOf`, sin reseñas y **sin la selección** (la Ómnibus). ▶ **DECISIÓN DEL OWNER ANTES**: `#616`
   fija API pública **sin avatares** y ahora hay cara del autor y fotos: ¿se sirven, se omiten o van solo
   como rutas nuestras? ❗ La portada REAL vive en la instancia (`#666`) y tiene que pintar la línea del
   filtro y la tarjeta de la T2·8 antes de que las reseñas se vean en producción (avisado a plataforma). ⚠️
   Places NO se retira (`[owner]`, 21-09). ❗ Renuncia con recibo: las fotos salen con `no-store` (`RGPD-04`).
   ▶ Después, a elegir (§4.1): el botón del panel · el texto de privacidad con el aviso de cookies · la T6.
3. **Lo que el ✅ del owner a la invitación NO cubre**, declarado sin medir: el `.ics` en un TELÉFONO de verdad
   (§4.6; si Android no lo abre, Google Calendar como segunda opción) · el justificante EN PRODUCCIÓN con el
   Turnstile REAL · `§7.2·R12`. ⚠️ `og:image` sale del logotipo del tema (1200×441): en tarjeta 2:1, bandas.
4. **Los diez puntos de `§10.4.7·B`** de la invitación, ninguno urgente con los interruptores apagados.
   ▶ Empieza por la RAÍZ: `matches()` y `takeSlotFor()` no son la misma regla. Ninguna prueba de `companion`
   manda un valor inválido; la rama `guest_data`, sin prueba.
5. De la Fase 4: el **ojo del owner en un teléfono de verdad** (ninguna de las 25 pantallas) · el cuaderno de
   entrega del cajón · el botón del sistema (16/800 con borde).
6. Del plugin quedan **dos frases** por ver: `/dod` y `/ligero` (`/sonda` va bien; ⚠️ al recrear el contenedor
   se pierden Chromium y `socat`: la receta de la skill los repone).

## Ficheros de este carril

**La analítica entera desde `#735`**: `app/Domain/Platform/Models/Analytics*`, `app/Domain/Platform/Services/
Analytics/**` (T1 de plataforma incluida: `Contract`, `Recorder`, `EventIngestor`, `AttributionContext`,
`SessionResolver`, `EmailUtm`, `RouteNormalizer`…), los tres observadores `*AnalyticsObserver`, `Http/Middleware/
{ResolveVisitor,ResolveAttribution,RecordEmailClick}`, `Api/V1/EventsController`, `resources/js/cajon/track.js`,
`tests/Feature/Analytics/**`, `tests/Feature/Api/V1/AnalyticsEventsTest`, `scripts/mutar-analitica.sh`,
`scripts/sonda-analitica.mjs`, el bloque de eventos de `openapi/v1.yaml`; y lo que nazca en T2: `app/Filament/
Pages/AnalyticsPage`, `app/Filament/Widgets/Analytics/**`, `Platform/Services/Analytics/Reports/**`, las claves
`analytics.*` de `lang/{es,zh_CN}/admin.php`. **Compartido (aviso antes)**: `PermissionSeeder`/`PermissionCatalog`,
`AdminNavigationTest`, y en T3 `layout.blade.php`, `app.js`, `SecurityHeaders`, el banner, `lang/*/cookies.php`.
**El cajón**: `resources/js/sidebar/**` · `resources/js/ui/*` que solo use el cajón · `lang/*/tickets.php`,
`account.php`, `guestform.php`, `guardian.php` · `resources/views/reservation/**` y las clases `.gf-*` y
`.guardian__*` · `tests/Feature/Sidebar/**`, `tests/Feature/Architecture/Sidebar*`, `tests/Feature/Reservation/
*SkinTest` · `scripts/sonda-cajon.mjs`, `sonda-enlace-firmado.mjs` · en `public/css/site.css`, los bloques del
cajón por su TÍTULO y el de la «HOJA ENFOCADA». **La ficha de Google** (`#524`, tomada de la web): lo que su
spec enumera. Lo del cliente va en la rama `cliente/playjump`, nunca a `main`.

## Trampas vivas (las de esta máquina y del repo; las de cada feature, en su spec)

- 🐳 **«The command 'docker' could not be found» es Docker Desktop APAGADO** (24-09, medido). En ESTA máquina
  el ejecutable es `/mnt/c/Users/yasmi/AppData/Local/Programs/DockerDesktop/Docker Desktop.exe` (no el de
  `Program Files` de la otra): `nohup "…/Docker Desktop.exe" &` y esperar a `docker info` (tardó ~60 s).
  `wsl.exe -l -v` lo delata: la distro `docker-desktop` en «Stopped».
- ⏰⏰ **EL RELOJ: el contenedor va en UTC y el parque en Madrid, y entre las dos medianoches NO es el mismo
  día.** `DisplayTime::dayLabel()` **no convierte de zona** —un sello de las 00:30 de Madrid se fecha el día
  anterior—; **un test con reloj propio miente dos horas al día** (`ScheduleFactsTest`, rojo a las 00:07). ▶ En
  un test de «ahora», el reloj a **`DisplayTime`, nunca a `Carbon`**; en el PANEL, toda hora por `DisplayTime`
  (`#733`). Fija la hora a mano, cerca de medianoche, para que cambie hasta el día. ⏰ **La fecha de una
  decisión sale del reloj del OWNER** (`date` en el host), nunca del contenedor.
- 💥💥 **UN CORTE DE LA VM DE WSL DEJA FICHEROS A CERO BYTES Y ROMPE GIT** (20-09): 31 objetos de
  `.git/objects` vacíos —el commit en curso entre ellos— y medio `public/build`. Síntoma: «*object file … is
  empty*», «*bad object HEAD*». ▶ **Receta medida**: apartar (no borrar) los vacíos (`find .git/objects -type f
  -empty`) · `git update-ref refs/heads/main <sha ENTERO del reflog>` · `git fsck` · `git reset` (el
  *cache-tree* apunta a un objeto muerto) · `npm run build` y `build:ssr`. **Lo versionado NO se pierde.**
- Chromium muere al recrear el contenedor: `node node_modules/playwright-core/cli.js install chromium` (con
  `npx` cae en otra caché; vive en `node_modules/…/.local-browsers`); `npm install` poda `playwright-core`.
- `SidebarDomContractTest` renderiza el BUNDLE: `npm run build:ssr` antes de la suite, también tras traer
  commits del cajón, tras un arnés de mutación (restaura el árbol, no el bundle) **y siempre que toques un
  `.vue`** (si no, 36 rojos que no son tuyos). Techo del chunk **285** (medido 284,04).
- ⚠️⚠️ **Un filtro que no ejecuta nada también sale ≠ 0**, y **Pint DESTROZA los nombres de método con
  palabras en MAYÚSCULAS** (`_UN_` → `_u_n_`): así se rompe un arnés **en silencio**. Los tests se nombran
  **sin mayúsculas**, y una mutación se cree tras ver el MISMO filtro en verde ejecutando su caso. Aseverar
  una subcadena sobre HTML acusa al script que la nombra (`#553`).
- ⚠️⚠️ **UN SUPERVIVIENTE DEL ARNÉS ES UNA PREGUNTA SOBRE EL TEST, no sobre el código** (`#578`, `#728`,
  `#730`, `#732`): primero «¿qué caso me falta?», y **solo** si no hay ninguno se declara. Bajar el
  denominador para enseñar un 5/5 limpio es mentir en el informe.
- **Un test que calcula su expectativa desde el código bajo prueba no prueba nada**, y un fixture que usa la
  convención que dice vigilar tampoco; una aserción con `__('clave')` dentro pasa siempre al vaciar la clave
  (`#734`): el rótulo se escribe a mano. **Al pivote se le habla por MÉTODO**, no por propiedad (Larastan).
- **Un modelo nuevo necesita alias de morfo** en `AppServiceProvider` o `MorphMapTest` pone la suite en rojo,
  y el rojo aparece en la suite COMPLETA, no en el filtro de tu tanda.
- ⚠️⚠️ **Una migración empujada NO es una migración aplicada**: la suite migra en SQLite en memoria; tras
  traer o añadir una, `php artisan migrate` en el contenedor (hoy: tres de la analítica estaban pendientes).
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
  `Maintenance`, `Unauthenticated` y `ValidationFailed`; **no hay `Forbidden`**, se escribe inline.
- **`Route::has()` no ve una ruta declarada a mitad de un test** hasta `Route::getRoutes()->refreshNameLookups()`.
- ⏰ **Techos que mide `docs-check`**: decisión 1,5 KB, §0 2048 B **sin la línea del título**, tracker 16 KB,
  carril 32 KB. Mídelos con su `awk` antes del commit (el §0 de `analitica.md` va a 2.0xx B: de ahí solo se
  toca la línea de «Estado», recortando otra).
- 🪤 **De la T1 de la analítica (plataforma)**: los observadores van `singleton()` (el dispatcher instancia
  `Clase@método` en cada evento); `DB::afterCommit` corre en el acto fuera de txn; un literal `sessions` en
  código dispara `AccessRevocationTest`; `postJson` no manda cookies sin `withCredentials()`; un teléfono
  se cuenta por CIFRAS (una fecha ISO no lo es); el UTM se pega TRAS firmar y se ignora al validar.
- 🪤 **De la T2a (24-09), seis pagadas**: (1) `order_adjustments.applied_by` y `payment_refunds.requested_by`
  son NOT NULL: un fixture los lleva siempre; los timestamps de `Order`/`Payment` no son rellenables, van
  por `forceFill`. (2) **Tras una petición que renderiza Livewire en el MISMO caso, `redirect()` devuelve el
  `Redirector` de Livewire** y `RestrictsPuertaRole` revienta con `TypeError` (500): en HTTP real no pasa;
  el caso de la puerta va SOLO y como primera petición. (3) En un presupuesto de consultas, **la primera
  lectura de `Setting` cuenta una** (el memo frío): caliéntalo antes de `enableQueryLog`. (4) **Con dos
  `Dashboard` en el panel, `Filament::getWidgets()` devuelve TODOS los descubiertos** —`allFiles()`, también
  en subcarpetas—: «Hoy» tiene que declarar los suyos. (5) Pint (`fully_qualified_strict_types`) exige `use`
  para una clase cualificada en un docblock `@return`. (6) `EXPLAIN` sobre 9 filas elige recorrer: no es
  veredicto sobre un índice. Y un test que corre con el reloj EN MARCHA cruza el segundo (`MePrivacyTest`
  puso en rojo un push de solo doc a las 22:59:00 UTC): en un caso que compara `now()` dos veces, congela.
- 🪤 **De la T2b**: el operador JSON `->>` funciona en MySQL 8 y en SQLite 3.45 **y NO en MariaDB** (el
  hosting): `SqlJson::string()` escribe la forma larga por motor. `AuditLogger::log*()` devuelve el modelo, así
  que un fixture le fija `created_at` con `forceFill` después. Los tres colores validados del gráfico se
  reutilizan en orden fijo por serie (`CustomersSeriesChart::COLORS` apunta a los del dinero).
- 🪤 **De la T2c**: **un ayudante privado `session()` en un test es un FATAL al cargar la clase** (choca con el
  `session()` público del `TestCase` de Laravel; se llama `visit()`), y `php artisan test` sale 255 sin que un
  filtro por «FAIL» enseñe nada: ante un 255, salida cruda. **Los widgets perezosos de Filament cargan al
  entrar en pantalla**: la sonda recorre la página por PANTALLAS de 600 px en tres pasadas —un salto al final
  se deja atrás los del medio—. Y un lote ingerido con `webdriver` abre una sesión BOT que cuenta en «fuera
  del recuento»: el fixture del test la incluye a propósito.
- 🪤 **De la T2d**: `fputcsv` entrecomilla toda celda con un ESPACIO (un `assertStringContainsString('Rótulo;')`
  falla aunque el rótulo esté): se aserta `"Rótulo";`. Una ruta `auth` del enrutador manda al invitado a `/login`
  (la web), no a `/admin/login` (Filament). El `#` de una referencia como `#685` dentro de un `sed 's#…#…#'`
  rompe la expresión y una cadena `&&` para en silencio: el mensaje del commit se escribe con Write y se pasa
  con `-F`.
- 🪤 **De la T2f**: **las pestañas inactivas de Filament no son `display: none`** (`invisible absolute h-0`):
  Livewire carga sus widgets igual (10 peticiones al abrir, medido) y `offsetParent` no las distingue; la sonda
  mide dentro de `.fi-sc-tabs-tab.fi-active`. La URL lleva el `key` de la pestaña, no el `id`. Pint
  (`fully_qualified_strict_types`) reescribe un `{@see TABS}` como `{@see Tabs}` (¡otra clase!): las constantes
  se citan con comillas. `ChartWidget::getHeading()` no puede volverse abstracto; `end(CONSTANTE)` no compila
  (`array_last`). Un `throw` de algo que no es `Error` llega a Playwright como `pageerror` «Object» sin más: la
  sonda apunta nombre, mensaje y pila. ⚠️ **Cinco `pageerror` «Object» INTERMITENTES en el paso del filtro**
  (dos tandas de tres con la sonda de antes de apuntar la pila; después, dos tandas limpias): causa NO
  verificada; si reaparecen, la sonda ya dice de dónde.
- 🩹 **En la BD LOCAL hay 19 titulares con la cadena de waiver ROTA** (basura del 26–27 de agosto): si mides
  cadenas, compara ANTES/DESPUÉS.
- **F4 cerró y el cajón es un PAQUETE** (`specs/cajon-empaquetable.md` §0 y §4.8). Tocar «HOJA ENFOCADA» de
  `site.css` obliga a regenerar `public/css/cajon.css` (`python3 scripts/hoja-del-cajon.py --aplicar`). Las
  reglas de botón apuntan al `button` y **no a `.btn`**.
- Commit por NOMBRE de fichero, nunca `git add -A`. La decisión, al final de `decisiones/700-799.md`.

## Buzón

### ❗❗ Para el carril de PLATAFORMA (emisor: SPA, 2026-09-24) — ME LLEVO LA ANALÍTICA ENTERA
- ✅ **Tu traspaso del 23-09, ATENDIDO**, y ampliado por el owner: T2→T5 son mías (`#735`), con TUS ficheros
  de la T1 (pasan a mi reparto, arriba). ⚠️ **He tocado lo tuyo**: la línea de la analítica del tracker (es
  el marcador de mi tarea) y `analitica.md` (§0, §4.5, §4.8, §6, §7). `ESTADO.md` es tuyo y su fila del SPA
  está caducada («T1–T2 de celebración… sigue la T3»): ponla «analítica T2→T5 (`#735`) + ficha de Google».
- ▶ **Voy a tocar lo compartido, aviso previo**: `PermissionSeeder`/`PermissionCatalog` (`reports.export`
  nuevo; `reports.view` gana consumidor) y `AdminNavigationTest` (el admin pasa a CINCO sitios: «Analítica»).
  En T3, lo que tú ya avisaste a la web el 23-09 (banner, `layout`, `app.js`, `SecurityHeaders`, política).
- ⚠️ Tres migraciones de la analítica estaban **pendientes en esta máquina** hasta hoy; aplicadas.

### ❗ Para el carril de CORREOS (emisor: SPA, 19→20-09; pendiente de tu «atendido»)
- ✅ **Tu censo pasa de 25 a 27**: `VisitEveNotice` (`#717`) y `GoogleBusinessLocationChanged` (`#725`), con sus
  piezas en los tres idiomas; tocado `GuestFormRequest` (cuerpo y llamada **solo** con invitación). **Ni tema,
  ni remitente, ni modo oscuro.** ❗ Un comentario mío con la llamada de cabecera entre comillas dejó tu
  `MailInboxLineTest` con **12 avisos en rojo**: tu guarda funciona (mutación en `scripts/mutar-invitacion-t7-1.py`).
- ▶ Te queda tu OJO en Gmail/Outlook de los tres nuevos (sondas `probe-t7-correo.php`, `probe-t7-vispera.php`).

### ❗ Para el carril de la WEB (emisor: SPA, 20→22-09; pendiente de tu «atendido»)
- ▶ **Me llevo `google-business-profile.md` (`#524`)**, tuya de banda; la numero desde la mía. Si la quieres,
  te la devuelvo. **Tocado en la T2·8** (`#734`): `public/css/landing.css` (el bloque `.rev*`; el `:root` NO),
  `lang/{es,en,fr}/landing.php` (siete claves nuevas en `reviews.*`), `ReviewCardTest` nuevo, y regenerada
  `public/css/cajon.css` (o `HojaDelCajonTest` rojo). ❗ **Una línea tuya que miente**: `google-reviews.md` dice
  «umbral de 10 reseñas» y el código dice `MIN_REVIEWS = 1` (`#494`). No la toco yo.
- ▶ **Aviso previo (24-09)**: la T3 de la analítica tocará el banner de cookies, `layout.blade.php`, `app.js`,
  `SecurityHeaders` y la política en tres idiomas, y sube `POLICY_VERSION`. Te aviso otra vez al empezarla.

### Para TODOS (emisor: SPA, 20-09): el techo del carril es **32 KB** (`#724`); si vuestro encabezado aún
dice 24, actualizadlo al pasar.

### Atendido
- **Plataforma 23-09** (traspaso de la T2, `#670` «no se despliega en piezas», aviso de lo compartido de la
  T1): atendidos. **Plataforma 21-09** («`home` mudada», banda y contrato 2): atendidos.
- **Web `#540`** (ningún botón con fondo negro, 12-09): atendido el 13-09; puedes retirarlo.
- Lo de plataforma del 16→22-09 que sobrevive está en «Trampas vivas» o en las specs.
