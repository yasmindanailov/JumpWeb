# Carril · Plataforma — los ficheros de este carril

> Mudado VERBATIM de `carriles/plataforma.md` el 2026-09-29 (`[DECIDIDO owner]`, `DECISIONES #852`): el carril no cabía en su
> techo de 32 KB (cuatro veces ese día) y esta lista cambia poco. La escribe SOLO el agente de este carril (`#621`), como
> su fichero principal. Lo que se lista aquí es de plataforma; lo COMPARTIDO se avisa en el buzón ANTES de tocarlo.

`CLAUDE.md` · `docs/ESTADO.md` · `docs/00-REFACTOR.md` · `docs/CONVENCIONES.md` · `docs/README.md` ·
`docs/DECISIONES.md` y la estructura de `docs/decisiones/` y `docs/carriles/` · `scripts/docs-check.sh` ·
`.githooks/pre-push` · `scripts/huella-enrutador.py` · `scripts/partir-decisiones.py` · `scripts/deploy.sh` (las
guardas 8, 9 y 10) · `scripts/mutar-guarda8.sh` · `CHANGELOG.md` · `phpstan.neon` · `phpstan-baseline.neon` ·
`eslint.config.js` · `eslint-suppressions.json` (la poda quien arregla) · `scripts/mutar-analisis-estatico.sh` ·
**EL ACCESO Y EL PANEL A SALVO** (`#847`→`#851`): sus dos specs, el guard `admin` de `config/auth.php`, `config/panel.php`,
`App\Http\PanelPath`, `RequiresAdminAppAuthentication`, `panel:quitar-authenticator`, `Panel{OwnGuard,SecretPath,AppAuthentication}Test`,
`scripts/{sonda-panel.mjs,mutar-panel-{guard,direccion,authenticator}.sh}` (y, AVISANDO, lo compartido: `layout.blade.php`, `routes/web.php`, `TestCase`) ·
**EL ACCESO CON CÓDIGO, la A1** (`#853`/`#854`): `Identity\Models\LoginCode`, `Identity\Services\{LoginCodes,EmailCodeLogin,LoginGate,RememberedDevice}`,
`Identity\Contracts\CodeRequestResult`, `Platform\Contracts\HidesSecretsInCopy`, `Notifications\LoginCode`, `AuthCodeController`,
`RefreshRememberedDevice`, sus dos migraciones, `{LoginCodes,RememberedDevice}Test`, `Api\V1\AuthCodeTest` y `scripts/mutar-acceso-codigo.sh`
(y, AVISANDO, del SPA: `RecordEmailSend`, `EmailTiming`) · **y la A2a** (`#855`): `Identity\Contracts\Reconfirmation`,
`Identity\Services\{CodeMail,SessionBinding}`, `Identity\Listeners\BindSessionOnLogin`, `EnsureSessionIsCurrent`,
`Notifications\ConfirmationCode`, `Auth\LogoutController`, `Api\V1\MeConfirmationCodeTest` y `Auth\SessionBindingTest` · **y la
A2b** (`#856`): `Identity\Contracts\EmailChangeOutcome`, `Notifications\Support\ChoosesRecipient`, `Account\EmailChangeController`,
`Api\V1\MePendingEmailCodeTest` y `Account\EmailChangeRecipientsTest` · **y la A3b** (`#857`): `isla/cuenta/CampoCodigoConfirmar.vue` ·
**EL SEO** (`specs/seo.md`): la spec, `scripts/{sonda-seo.mjs,mutar-seo.sh}`, `RobotsController`, `MapsEmbed::coordinates()`,
`VenueAddress::parts()` y el grafo de `StructuredData` (y, AVISANDO, `components/{pagina,layout}.blade.php` y `deploy.sh`) ·
**LA POLÍTICA DE COOKIES de producción** (`#858`/`#859`): su spec, `App\Http\Legal\CookieInventory`, la migración
`cookie_policy_for_production`, `tests/Support/CookiePolicyV4.php`, `Content\CookieInventoryTest`,
`scripts/{sonda-inventario-cookies.mjs,mutar-politica-cookies.sh}` y `inventory.*` de `lang/*/cookies.php` (y, AVISANDO, del
SPA: `CookiePolicyContent`, el resto de `cookies.php`, `CookieConsent` y sus tests) ·
**LOS TEXTOS LEGALES** (`#863`→`#865`): `specs/textos-legales.md` (el código, tras la asesoría: su §4.3) ·
**LA ISLA Y LA LANDING NUEVA** (`#681`, `#682`): la spec, la isla `resources/js/isla/**`, sus bancos y sondas
(`scripts/banco-{isla,piezas,compra}*`, `scripts/pixel.mjs`, `scripts/sonda-{embudo,isla,cuenta,movimiento,isla-movimiento,banco-movimiento,isla-rendimiento,compra-directa,demanda}.mjs`,
`scripts/sonda-cuenta-datos.php`, `scripts/mutar-{t5f,hijos-de-producto,demanda-isla,isla-z6a}.sh`; las de las PÁGINAS:
`scripts/sonda-{primera-pantalla,calculadora,visor,conversion,portada,cumpleanos,colegios,visitanos,normas,entradas,web}.mjs`
y `scripts/mutar-sonda-{colegios,entradas,normas,visitanos,web}.sh`), `sidebar/reanudar.js`,
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

## Lo del SPA que este carril usa sin tocarlo

> Mudado VERBATIM del buzón del carril el 30-09 (`#861`: el carril no cabía en sus 32 KB). Era el aviso al SPA del 25→26-09
> y es un registro vivo: si el SPA cambia algo de aquí, avisa. (Su «Mapa y reseñas (Google)» ya dice «Mapa (Google)»: `#859`.)

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
