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
**LA POLÍTICA DE COOKIES de producción** (`#858`/`#859`): su spec, `App\Http\Legal\CookieInventory`, la migración
`cookie_policy_for_production`, `tests/Support/CookiePolicyV4.php`, `Content\CookieInventoryTest`,
`scripts/{sonda-inventario-cookies.mjs,mutar-politica-cookies.sh}` y `inventory.*` de `lang/*/cookies.php` (y, AVISANDO, del
SPA: `CookiePolicyContent`, el resto de `cookies.php`, `CookieConsent` y sus tests) ·
**LA ISLA Y LA LANDING NUEVA** (`#681`, `#682`): la spec, la isla `resources/js/isla/**`, sus bancos y sondas
(`scripts/banco-{isla,piezas,compra}*`, `scripts/pixel.mjs`, `scripts/sonda-{embudo,isla,cuenta,movimiento,isla-movimiento,banco-movimiento,isla-rendimiento,compra-directa,demanda}.mjs`,
`scripts/sonda-cuenta-datos.php`, `scripts/mutar-{t5f,hijos-de-producto,demanda-isla}.sh`), `sidebar/reanudar.js`,
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
