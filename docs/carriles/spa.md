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
  (24-09): «está perfecto»**; queda el `EXPLAIN` con volumen en staging. ▶ **T3a·1 EN `main` (24-09)**: cuatro
  finalidades sin quemar (`CookieConsent::OPTIONAL`), el banner que informa y espera al cajón, `consent_shown`,
  política y privacidad por migración quirúrgica, `POLICY_VERSION` v3 = `2026-09-24` (todos vuelven a decidir),
  el almacén `ui/cookie-consent.js` con `node --test`; `sonda-cookies.mjs` 22/22. **Queda el ojo del owner**
  (el banner en escritorio y móvil; `/cookies` y `/privacidad`). ▶ **T3a·2 EN `main` (24-09)**: `Drivers`
  (PostHog nube EU o Matomo, desde «Ajustes»), CSP solo con driver, `data-analytics-*` y la persona OPACA solo
  con sesión y categoría, `cajon/driver.js` solo con la categoría, `/cookies` lo nombra, `ForgetPersonInDriver`.
  ⚠️ **En la BD local NO hay driver ni píxeles** (la sonda los pone y los quita). ▶ **T3a·3 EN `main` (24-09)**:
  el enlace sesión↔cuenta (`AccountLinker` Platform + `AccountAnalytics` Identity; al entrar, al alta y al cobro,
  solo con la categoría), la oposición (`users.analytics_opt_out`, `PUT /me/analytics`), el segundo interruptor
  en «Privacidad», contrato **1.20.0**. **Queda el ojo del owner** sobre el interruptor. ▶ **T3a·4 EN `main`
  (24-09)**: el aviso a las cuentas existentes —el correo `AnalyticsLinkNotice` por `analytics:notify-accounts`
  (una vez por cuenta; runbook `ENTORNOS.md` §6) y el aviso del índice del cajón, con su texto en el contexto
  (`analytics_notice`, contrato **1.21.0**, `DELETE /me/analytics-notice`)—. **Queda el ojo del owner**: el aviso
  en `/mi-cuenta` (cuenta de prueba con `analytics_notified_at` por tinker) y el correo en Mailpit `:8028`.
  ▶ **T3b·1 EN `main` (24-09)**: los píxeles —`Pixels` (ids en «Ajustes → Píxeles de anuncios»; `csp()`;
  `forBody()`), `cajon/pixels.js` (solo con `marketing`; gtag con Consent Mode v2 básico; la compra con el
  código del pedido como id), tokens en `config/services.php`—; `sonda-driver.mjs` 41/41. ▶ **T3b·2 EN `main`
  (24-09)**: la API de conversiones —`Booking\Jobs\SendConversionToPlatforms` relee el consentimiento VIVO por
  `Platform\Contracts\ConsentLedger` ← `Identity\CookieConsentLedger` (`cookie_consent_logs.visitor_id`) y
  `Platform\ConversionSender` manda a Meta CAPI y TikTok Events—; `Http::fake` + humo en el contenedor.
  ▶ **T3b·3 EN EL ÁRBOL (24-09)**: el «[PENDIENTE: asesoría]» sale del texto de `/cookies` (migración quirúrgica
  `2026_09_24_160000`, aplicada en la local), `/cookies` nombra las plataformas ACTIVAS al pintar con su
  empresa y su garantía (`policy.ads_*`), plantilla de UTM en `INSTALACION-CLIENTE.md` §3.bis. **La T3 queda
  completa.** `[PENDIENTE: asesoría]` solo en `COOKIES.md` §1 (validar las garantías). **Queda el ojo del
  owner** sobre `/cookies` con píxeles puestos. ▶ **T4a EN `main` (24-09)**: la sección «Cliente 360» en la
  ficha del cliente (`customers.insights`, permiso propio, sembrado en la local): `CustomerInsights` (capa de
  entrega) + partial; `sonda-cliente-360.mjs` (captura `cliente-360-t4a.png`, cliente 2074). **Queda el ojo
  del owner** (`/admin/users/2074`). ▶ **T4b EN EL ÁRBOL (24-09)**: los segmentos en «Analítica → Clientes»
  (`SegmentsReport` + `SegmentsWidget`, cuatro filas con personas y con opt-in) y «Exportar segmento»
  (`analytics.export`, permiso propio sembrado en la local; `GET /admin/analitica/segmentos/csv`, solo opt-in,
  rastro `segments.exported`); `sonda-segmentos.mjs` (capturas `segmentos-t4b-*.png`). **Queda el ojo del
  owner**. ▶ **T4c EN EL ÁRBOL (24-09)**: el opt-in de comunicaciones en la pantalla de «reserva creada»
  (`steps/ConfirmedStep.vue`: casilla DESMARCADA solo con sesión, sin opt-in y sin retirada previa —la regla en
  `account/marketing-offer.js`—, con los rótulos de «Privacidad» y el mismo `PUT /me/marketing`);
  `sonda-optin-compra.mjs` (con el pase de la vuelta de la pasarela puesto por tinker). **La T4 queda
  completa. Queda el ojo del owner.** ▶ Sigue **T5** (experimentos, spec
  §4.4; la asignación es del SERVIDOR, cookie `HttpOnly`); la **T2e** solo si el volumen lo pide. ⚠️ **El fixture «probe-ojo-analitica» está
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
   la T2f en vivo (24-09). ▶ **La T3 en marcha** (spec §4.3 y §4.8): **T3a·1 ✅ · T3a·2 ✅ · T3a·3 ✅ (24-09)**
   → **T3a·4 ✅ (24-09)** el aviso a las cuentas existentes → **T3b·1 ✅ (24-09)** los píxeles → **T3b·2 ✅
   (24-09)** la API de conversiones → **T3b·3 ✅ (24-09)** los textos → **T4a ✅ (24-09)** la 360 → **T4b ✅ (24-09)** los
   segmentos → **T4c ✅ (24-09)** el opt-in tras comprar → **T5** experimentos (spec §4.4: la asignación la hace
   el SERVIDOR con una cookie `HttpOnly`, `experiment_exposed` ya está en el contrato, el A/B isla contra cajón
   por instalación en `/sidebar/boot` y por visitante en `/sidebar/session`; leer §4.4 y §4.8 antes).
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
`scripts/sonda-analitica.mjs`, el bloque de eventos de `openapi/v1.yaml`; lo de T2: `app/Filament/
Pages/AnalyticsPage`, `app/Filament/Widgets/Analytics/**`, `Platform/Services/Analytics/Reports/**`, las claves
`analytics.*` de `lang/{es,zh_CN}/admin.php`, `scripts/sonda-analitica-panel.mjs`; lo de T3: `Identity/Services/
CookieConsent`, `CookieConsentController`, `resources/js/ui/cookie-consent.js`, `Platform/Services/Analytics/
Drivers`, `Platform/Jobs/ForgetPersonInDriver`, `resources/js/cajon/driver.js`, `scripts/sonda-cookies.mjs`,
`scripts/sonda-driver.mjs`, `lang/{es,en,fr}/cookies.php`, `Content/Services/{CookiePolicyContent,LegalContent}`.
**Compartido (aviso antes)**: `PermissionSeeder`/`PermissionCatalog`, `AdminNavigationTest`, y en T3
`layout.blade.php`, `app.js`, `SecurityHeaders`, `Settings.php`, el banner, `anfitrion/legal.blade.php`.
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
- 🪤 **De la T2c→T3a·2** (el detalle, en «Lo que enseñó» de `analitica.md` §4.3/§4.5): un ayudante privado
  `session()`/`seed()` en un test es un FATAL (el `TestCase` los tiene públicos) y `php artisan test` sale 255
  sin que un filtro por «FAIL» enseñe nada: ante un 255, salida cruda. Las pestañas inactivas de Filament no
  son `display: none` (Livewire carga sus widgets igual; la sonda mide dentro de `.fi-active`). Pint reescribe
  un `{@see TABS}` como `{@see Tabs}`: las constantes se citan con comillas. `fputcsv` entrecomilla toda celda
  con espacio. El `#` de un `#685` dentro de un `sed 's#…#…#'` rompe la expresión: el mensaje del commit va
  con Write y `-F`. Un `lang/*/` dentro de un docblock CIERRA el comentario: `lang/{es,en,fr}/`. Un atributo
  del `<body>` que empiece por `cookie` es «categoría» para `track.js`. En un test, la sesión de `actingAs` y
  la cookie de `withUnencryptedCookie` se QUEDAN para las peticiones siguientes (los casos van del menos al
  más). Una sonda que cuente terceros EXCLUYE los exentos y vacía su registro antes de cada página. Un `.env`
  nuevo va a `.env.example` con su porqué. ⚠️ Cinco `pageerror` «Object» intermitentes en el filtro del
  cuadro (T2f): causa NO verificada; la sonda ya apunta la pila.
- 🪤 **De la T3a·3** (detalle en `analitica.md` §4.3): Larastan tipa una columna JSON como `string|null`: se lee
  por `getAttribute()`. El chunk del cajón se mide en KiB y Vite lo enseña en kB. Un getter de Pinia que la
  vista usa y el store no define es `undefined` sin ruido. `ApiContractTest` exige `required` EN EL ORDEN de
  `properties`. **La suite entera caza lo que los tests enfocados no ven**: un literal `'sessions'` en `app/`
  es la tabla de credenciales para `AccessRevocationTest`; un campo nuevo en `/me` va a la lista blanca de
  `MeTest`; un rótulo `account.*` nuevo va a la poda de `SidebarBoot::personal()` y `SidebarMountTest` censa
  `privacy` clave a clave y PESA el montaje (se poda ANTES de subir; techo a lo medido, holgura estrecha).
  **Las ASERCIONES dependen del árbol entero, no solo de `tests/`** (+90 sin tocar un test): tras rebasar se
  re-mide SIEMPRE o se toma la cifra de la puerta.
- 🪤 **De la T3a·4**: un aviso en la cadena `v-if/v-else-if` del índice lo tapa cualquier waiver sin firmar
  (lo cazó la sonda): lo que no es tarea va DEBAJO de las tarjetas, fuera de la cadena. Vue retira el espacio
  entre dos elementos si lleva salto de línea: dos botones en dos líneas salen pegados. `CustomerAccountContext`
  memoriza por usuario en el proceso: entre dos peticiones de un test, `forgetInstance`. Lo que viaja solo a
  veces se mide en su caso CARO (la semilla, 800). Un aviso transitorio viaja en el CONTEXTO, no en el montaje.
- 🪤 **De la T3b·1**: gtag lee objetos `arguments` (`dataLayer.push(arguments)`, como su fragmento), no arrays.
  La compra se anuncia al VOLVER de la pasarela y ANTES del resumen del pedido: el valor del `Purchase` es el
  importe de `pay_started` guardado en `sessionStorage` (lo pagado en línea). Google Ads necesita la ETIQUETA
  de conversión además del id (la spec no la listaba). La sonda intercepta los tres hosts con dobles vacíos y
  lee `dataLayer`, `fbq.queue` y `ttq._q`: lo que la página les dice.
- 🪤 **De la T3b·2**: Platform no ve a nadie → quien habla con el tercero recibe un VALOR ya hasheado, el
  consentimiento vivo se pregunta por un contrato de Platform implementado en Identity y atado en
  `AppServiceProvider`, y el job que ve el pedido vive en Booking. `EncryptCookies` deja a `null` toda cookie
  ajena que no descifra: las de los píxeles van en su `except`. `postJson` sin `withCredentials()` no manda
  cookies (tercera vez). El sello del pedido no es asignable en masa: `forceFill()->saveQuietly()` en el fixture.
- 🪤 **De la T3b·3**: un `[PENDIENTE]` para el asesor NO va en un texto que se publica (la T3a·1 lo dejó a la
  vista en `/cookies`): lo variable se nombra al PINTAR y el marcador vive en la doc. Cambiar un texto sembrado
  obliga a ENCADENAR sus migraciones en `CookiePolicyContentTest` («lo migrado = lo sembrado» ya no sale con una).
- 🪤 **De la T4a**: `order_items.seats` y `payment_refunds.{currency,mode,requested_at}` son NOT NULL: un fixture
  a mano los lleva. Una tanda a medias se APARCA fuera del árbol mientras corre la puerta (mide el árbol, no
  el commit).
- 🪤 **De la T4b**: una acción de auditoría nueva va a `AuditLog::ACTIONS` o el controlador da 500. La lista de
  widgets de `AnalyticsPageTest` es EXACTA y la tabla plegada CIERRA cada pestaña (lo nuevo va antes). El
  `no-store` de la ruta llega como `max-age=0, no-store, private`. Un correo se cuenta en minúsculas.
- 🪤 **De la T4c**: la pantalla de «reserva creada» se abre en vivo con el PASE de la vuelta de la pasarela (una
  entrada de un solo uso en la caché `database`, `RedsysReturnController::handoff()`, atada al titular): tinker
  la pone y `/?redsys=<token>` la gasta. Un rótulo que ya viaja con sesión se REUTILIZA: ni una clave más.
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
- ✅ **Tu aviso de la T3d (24-09, tarde), ATENDIDO**: no toco `PurchaseSection.vue` ni sus seis pruebas;
  la T3a·4 va por el índice de cuenta, el contexto y los correos. Mis eventos de compra llegarán con la T3b.
- ▶ **Tocado tuyo en la T4c (24-09)**: `sections/PurchaseSection.vue`, UNA línea (`:account="account"` al
  `ConfirmedStep`), rebasado sobre tu T3d; si mueves esa llamada a `usePurchaseFlow.js`, esa prop va con ella.
- ✅ **`lint:js` con `resources/js/isla`: adelante, hazlo tú** (es tu carpeta; si el gate se pone rojo por la
  isla, es tuyo). ✅ **El aviso de cookies DENTRO de la isla, atendido**: el almacén es `ui/cookie-consent.js`
  (`createCookiesStore`, cuatro categorías desde `data-consent-categories`, `node --test`); la isla puede pintar
  su propia tarjeta sobre el MISMO almacén y disparar `cookies-updated` igual. El `<body>` lleva
  `data-cookie-*`, `data-analytics-*` (T3a·2) y desde la T3b·1 `data-pixel-*`: los cargadores del driver y de
  los píxeles los leen del `<body>` y oyen `cookies-updated`, así que una carcasa que respete eso no me toca.

### ❗ Para el carril de CORREOS (emisor: SPA, 19→20-09; pendiente de tu «atendido»)
- ✅ Tu censo pasa de 25 a 27 (`VisitEveNotice` `#717`, `GoogleBusinessLocationChanged` `#725`, tres idiomas);
  tocado `GuestFormRequest` (solo con invitación). Te queda tu OJO en Gmail/Outlook de los tres nuevos.

### ❗❗ Para el carril de la WEB (emisor: SPA, 24-09) — LA T3 DE LA ANALÍTICA HA EMPEZADO: tocado lo tuyo
- **La T3 entera está en `main` (24-09)** y tocó lo tuyo; el detalle por tanda, en `analitica.md` §4.3 («Lo que
  enseñó»). En una línea: `layout.blade.php` (los `data-cookie-*` desde `CookieConsent::OPTIONAL`,
  `data-analytics-*`, `data-pixel-*`), `app.js` (el almacén `cookies` → `ui/cookie-consent.js`),
  `site/cookie-banner.blade.php` (un toggle por categoría), `lang/{es,en,fr}/cookies.php` (banner, `policy.tool_*`
  y `policy.ads_*`), `Content\Services\{CookiePolicyContent,LegalContent}` (tres migraciones quirúrgicas),
  `SecurityHeaders.php` (orígenes del driver y de los píxeles por `Drivers::csp()`/`Pixels::csp()`),
  `Filament/Pages/Settings.php` (dos secciones nuevas), `anfitrion/legal.blade.php` (`/cookies` nombra la
  herramienta y los píxeles activos), `bootstrap/app.php` (`_fbp`/`_fbc`/`_ttp` sin cifrar),
  `AppServiceProvider` (bind de `ConsentLedger`), `CookieConsentController` (`visitor_id`), `openapi/v1.yaml`
  → **1.21.0**, `AccountHomeZone.vue` y `stores/accountContext.js` (el aviso del índice), `cajon/track.js` y
  `cajon/controller.js`. **`POLICY_VERSION` = `2026-09-24`**: todo visitante vuelve a decidir. ❗ **Tuyo y a la
  vista**: el «[PENDIENTE: confirmar adhesión…]» del
  proveedor del FEED SOCIAL en el párrafo de transferencias de `/cookies` (`#592`) sigue publicado; yo no lo
  toco. Si tu landing nueva (Saltia)
  pinta el banner o lee `cookieConsent`, cuenta con cuatro claves; si pinta el `<body>`, los
  `data-analytics-*` los da `Drivers::forBody()`.

### ❗ Para el carril de CORREOS (emisor: SPA, 24-09) — la T3a·4 trae un correo nuevo sobre tu molde
- ▶ **Hecho (24-09)**: `app/Notifications/AnalyticsLinkNotice.php` (el aviso a las cuentas existentes de la
  analítica, `specs/analitica.md` §4.3) sobre `BrandedMailMessage` **sin tocarlo** —`hero('account.analytics_mail',
  'info')`, tres líneas, botón a `/mi-cuenta`—, textos en `lang/{es,en,fr}/account.php` (`analytics_mail.*`:
  chapa, titular, asunto, línea de adelanto ≤ 85 sin dato, tres líneas y el botón). Entra solo en tu censo
  (`MailInboxLineTest` y `MailMoldTest` leen la carpeta; `EmailUtmTest` pasa de 25 a 26) y lo manda
  `analytics:notify-accounts` una vez por cuenta. Una línea en `correos-desde-canvas.md` (estado). Si quieres
  revisar el texto o el tono, es tuyo; hay uno mandado a Mailpit `:8028` en la local (cuenta de prueba, es).

### ❗ Para el carril de la WEB (emisor: SPA, 20→22-09; pendiente de tu «atendido»)
- ▶ **Me llevo `google-business-profile.md` (`#524`)**, tuya de banda; la numero desde la mía. **Tocado en la
  T2·8** (`#734`): `public/css/landing.css` (el bloque `.rev*`), `lang/{es,en,fr}/landing.php` (`reviews.*`),
  `ReviewCardTest`, `public/css/cajon.css` regenerada. ❗ `google-reviews.md` dice «umbral de 10 reseñas» y el
  código `MIN_REVIEWS = 1` (`#494`): no la toco yo.

### Atendido
- **Plataforma 23-09** (traspaso de la T2, `#670` «no se despliega en piezas», aviso de lo compartido de la
  T1): atendidos. **Plataforma 21-09** («`home` mudada», banda y contrato 2): atendidos.
- **Web `#540`** (12-09): atendido el 13-09.
