# Testing — cómo se ejecutan y reglas de la suite

> Adaptado del proyecto origen (2026-08-12). Describe la BASE HEREDADA: el refactor
> (`00-REFACTOR.md`) puede haberla cambiado. Verifica contra el código antes de construir
> encima (CONVENCIONES §7).

> Cómo correr los tests, ejecución **en paralelo** y las **garantías** de la suite
> (determinismo + sin red). Las convenciones de DÓNDE va cada test (Unit vs Feature) viven
> en `CONVENCIONES.md` §3.bis (DoD) y §3.ter (servicios puros → `tests/Unit`): aquí **no se
> duplican**, se enlazan.

## Entorno JumpWeb
Docker (Laravel Sail) en WSL2, repo en `~/proyectos/jumpweb`. Web `http://localhost:8081` ·
MySQL puerto `3308` · Mailpit `http://localhost:8028`. Servicio del contenedor: `laravel.test`.

## Cómo correr la suite

Comando canónico (Docker/Sail; usa siempre `-u sail`, ver `../CLAUDE.md`):

```bash
docker compose exec -u sail laravel.test php artisan test            # toda la suite
docker compose exec -u sail laravel.test php artisan test --parallel # en paralelo (rápido)
docker compose exec -u sail laravel.test php artisan test tests/Feature/Sales   # un directorio
docker compose exec -u sail laravel.test php artisan test --filter NombreDelTest
```

> Las pruebas que renderizan vistas necesitan el manifest de Vite: ejecuta `npm run build`
> una vez antes de la suite completa (si no, esas vistas dan 500 por `ViteManifestNotFound`).

## Ejecución en paralelo (paratest)

`php artisan test --parallel` reparte los ficheros entre varios procesos (uno por núcleo),
cada uno con su **propia BD SQLite en memoria** — sin estado compartido entre procesos. Se
habilita con la dependencia de desarrollo **`brianium/paratest`** (la vía oficial que sugiere
Laravel; solo `require-dev`, cero impacto en producción).

**Referencia medida en el proyecto origen (2026-08-12, Ryzen 7 2700X, 16 hilos, BD `:memory:`),
con la misma suite heredada:**

| Modo | Tiempo | Tests |
|---|---|---|
| Secuencial (`php artisan test`) | ≈ 8 min 15 s (495 s) | 2132 |
| Paralelo (`--parallel`) | ≈ 1 min 13 s | 2132 |

Resultado idéntico en ambos modos (2132 verde, 7931 aserciones); ≈ 6,8× más rápido. Para el
día a día basta con correr el directorio o el `--filter` del módulo que tocas; la suite
completa, antes de commitear o en CI.

> **Verificado en JumpWeb el 2026-08-12** (Fase 2, paso 2), primera corrida secuencial completa
> de este repo: **2157/2157 en ambos modos** (secuencial 557 s · paralelo 73 s). El contador
> «PHPUnit Notices: 1» que muestra la corrida PARALELA completa **no aparece en la secuencial**
> (0) ni al correr ficheros sueltos en paralelo: es del runner, no del código, y ningún test
> falla por él. Si alguna vez sale un fallo SOLO en secuencial, sospecha primero de haber
> tocado el árbol de trabajo con la suite corriendo — pasó una vez y no era orden de tests.

## Garantías de la suite

### 1. Sin red — `Http::preventStrayRequests()`
`tests/TestCase.php` activa la guarda anti-red en `setUp()`: **ningún test puede hacer una
petición HTTP real**. Si un test nuevo dispara una salida externa sin simularla, **falla en el
acto** en vez de quedar lento o *flaky* (el verificador de contraseñas filtradas de Laravel
tiene un timeout de **30 s** por llamada) o depender de que haya internet.

Cómo simular cada salida externa del proyecto:

| Salida externa | Cómo se simula en los tests |
|---|---|
| **Redsys** (pago/refund REST) | `Http::fake([Redsys::REST_URL_TEST => Http::response(...)])` |
| **Cloudflare Turnstile** (anti-bot) | desactivado sin claves en `settings`, o `Http::fake` |
| **Have I Been Pwned** (`Password::uncompromised`) | sustituir el contrato `UncompromisedVerifier` (`$this->app->instance(UncompromisedVerifier::class, ...)`) |

Validado empíricamente en el origen: la suite completa pasa con la guarda activa (1910/1910 en
el momento de la validación), prueba de que toda salida externa ya está simulada.

### 2. Determinismo del tiempo — `travel()`, nunca `sleep()`
Para forzar el paso del tiempo (p. ej. comprobar que un timestamp no se reescribe) se usa el
reloj de prueba de Laravel: `$this->travel(1)->seconds()` / `$this->travelTo(...)`. **No** se
usa `sleep()`: es determinista y sin coste de reloj real.

⚠️ **Y hay una segunda mitad de esto que costó una sesión: un test puede depender de la fecha
sin nombrarla nunca.** El 2026-08-15 la suite amaneció con **tres fallos que nadie había
causado** —el árbol quedó limpio y el commit anterior fallaba igual—, y las dos causas valen
como patrón:
- **Una FOTO de un árbol que contiene el calendario caduca cada día.**
  `SidebarDomContractTest` compara contra el manifiesto congelado de 4.7·1, y la rejilla del mes
  depende de HOY: cada día que pasa añade una casilla deshabilitada y cada mes cambia la forma
  entera. La foto valía **exactamente un día**. Se arregla congelando el reloj en `setUp()`
  (`FROZEN_NOW`) y regenerando el manifiesto; verificado por mutación: quitar el congelado
  devuelve los dos casos a rojo, y al regenerar **solo cambian las 2 entradas del calendario**
  de las 30, que es la prueba de que el resto ya era estable.
- **Un fixture con tarifas por día de la semana hace que el fichero falle en fin de semana.**
  `CatalogTest` declara una tarifa `special` con `weekdays: [0, 6]`; `CatalogReader` resuelve la
  tarifa de los complementos con `Carbon::today()` y **descarta el complemento de pago sin precio
  para esa tarifa**. Sábado y domingo, tres complementos se quedaban en uno. La conducta del
  servidor es correcta: el que dependía del calendario era el test.

**La regla**: si el sujeto del test NO es el tiempo pero el fixture o el dato tienen calendario
(tarifas por día, rejilla de mes, franjas relativas a `now()`), **congela el reloj en `setUp()`
con una constante documentada**. Un test que solo pasa los martes está rojo, aún no lo sabes.
⚠️ Y el corolario para cualquier fixture congelado: **una foto que incluye el tiempo hay que
tomarla con el reloj parado**, o no es una red — es una alarma diaria que se aprende a ignorar.

### 2.bis. Un test que compara contra un ARTEFACTO tiene que comprobar que no está rancio
⚠️ **Medido el 2026-08-15** (`DECISIONES #69`): `SidebarDomContractTest` no renderiza las fuentes del
cajón, sino `storage/ssr/render-sidebar.js`, que compila Vite. Con una fuente **rota** y el bundle sin
reconstruir, el gate **pasó en verde**. Un rojo espurio cuesta una hora; un verde falso cuesta el
contrato visual entero. Que el `pre-push` construya antes de la suite no basta: el modo de fallo
peligroso es LOCAL y silencioso, justo mientras se itera sobre esos módulos.
La guarda es `assertBundleIsNotStale()`: compara la fecha del artefacto con la de cada fuente que entra
en él. Si añades un test que compare contra algo compilado, generado o congelado, ponle la suya.

### 2.ter. En una página con el cajón, `assertSee` de un texto del `data-boot` NO PRUEBA NADA
⚠️⚠️ **Medido el 2026-08-21** (`DECISIONES #112(e)`). El punto de montaje del cajón SPA va en
**todas** las páginas públicas y lleva `__('tickets')` **entero** dentro de su atributo `data-boot`
—unos 11 kB—, porque la SPA no tiene canal de i18n propio. Consecuencia: cualquier
`assertSee(__('tickets.loquesea'))` contra una respuesta de página **pasa siempre**, pinte la página
lo que pinte, y el `assertDontSee` simétrico **falla siempre**. Se auditó tras retirar el motor
Livewire —que era lo que hasta entonces tapaba el montaje— y salieron **13 claves vacuas** y 4
literales que coinciden con valores del grupo, repartidos en 5 ficheros.

**La regla**: contra una respuesta de página, usa **`assertSeeText` / `assertDontSeeText`**. Aplican
`strip_tags`, y el payload viaja en un ATRIBUTO, así que desaparece: miden lo que el usuario ve, que
es lo que esos casos querían decir. `assertSee` sigue siendo lo correcto para fragmentos de HTML
—clases, `href`, `aria-*`—, que es justo lo que `assertSeeText` no puede ver.

⚠️⚠️ **Y NO es solo el grupo `tickets`: es TODO lo que viaje en el `data-boot`, que crece.** El
2026-08-22, el área de cliente (`specs/area-cliente.md`) añadió al montaje `account.account.title`,
`account.orders.title` y `account.orders.empty` — tres claves, 150 B— y con ellas **cuatro casos de
tres ficheros distintos** quedaron tocados de golpe: uno rojo y **tres en verde falso**. El rojo
avisa; los verdes falsos, no. ▶ **Al añadir una clave al `data-boot`, audita quién asevera ese texto
contra una página**: `grep -rn "assertSee(__('grupo.clave')" tests/` y su literal.

⚠️ **Tres avisos, uno por medición:**
- **La colisión de subcadena sobrevive al cambio**: «Reembolsado» es prefijo de «Reembolsado el …»,
  así que el caso seguía verde con el distintivo borrado. Si el texto es prefijo de otro de la misma
  página, ancla además en algo estructural (su clase).
- ⚠️⚠️ **Y la colisión de GEMELOS no la arregla `assertSeeText` en absoluto.** Medido el 2026-08-22:
  `AccountAccessTest::test_verified_users_can_view_the_account_page` comprobaba que `/mi-cuenta`
  enseña su título, y **llevaba inerte desde `#231 p8`** — `landing.footer.account_link` es
  literalmente el mismo texto («Mi cuenta») y el footer va en esa misma página, así que el caso
  pasaba por el ENLACE DEL PIE con el `<h1>` borrado. Lo destapó una mutación, no el cambio que lo
  rodeaba. **Cuando el mismo texto sale dos veces en la página, la única aserción que mide algo es la
  estructural**: `assertSee('<h1 class="page__title">'.__('…').'</h1>', false)`.
- **No vale solo con convertirlo**: hay que MUTAR la vista y ver el caso caer. Convertir sin mutar
  cambia un verde falso por otro (`#65`). Las cuatro conversiones del 2026-08-22 se mutaron una a una,
  y **la primera pasó igualmente** — que es cómo se encontró lo del párrafo anterior.

### 2.quater. Lo que un gate declara que NO mira es un hueco CON NOMBRE
⚠️⚠️ **Medido el 2026-08-21** (`DECISIONES #113`): el cajón SPA se sirvió con sus **20 iconos
vacíos** —`<svg>` sin dibujo dentro— y la suite entera en verde. El motivo estaba escrito en el
propio código: `SidebarDomContractTest` **no desciende dentro de un `<svg>`** (su interior es
geometría, no estructura estilable), así que un envoltorio vacío y uno lleno son el mismo nodo para
el manifiesto congelado. La regla del normalizador es correcta; lo que falló fue transcribir hasta
donde el gate mira y parar ahí.

**La regla**: cuando un test declare explícitamente que no comprueba algo, eso NO es una nota al pie
— es un hueco con nombre, y necesita su propia guarda el mismo día que se escribe. En el cajón la
lista de lo que el diff de árbol no ve ya estaba en `ESTADO.md` (el texto, `href`, `action`, los
`name` de un formulario…) y cada elemento tenía su paridad; los iconos no la tenían, y por eso se
cayeron sin ruido. Hoy la tienen: `SidebarIconParityTest`.

⚠️ **Y la guarda se formula para que no se rompa al reordenar**: no compara icono por icono por
posición, sino que exige que el cajón **no invente dibujos** — cada geometría que emite es la de un
`<x-icons.*>` o está declarada como propia con su motivo.

### 2.quinquies. Una lista blanca que nadie verifica es una PROMESA, no una guarda
⚠️ **Medido el 2026-08-24** (`DECISIONES #128`). `SidebarTextParityTest` exige que ninguna clave del
grupo `tickets` lleve una barra vertical, porque el cajón solo resuelve `singular|plural` para
`cart_items` y cualquier otra se pintaría con la barra dentro. Al añadir dos claves pluralizadas que
**compone el servidor** —la cantidad con su sustantivo—, la salida fácil era ampliar la lista blanca:
«éstas no las pinta el cliente». Eso es una promesa que caduca el día que alguien las pinte.

**La regla**: cuando una guarda necesite una excepción, **la excepción se demuestra en el mismo
test**. Aquí se comprueba sobre las FUENTES del cajón que la clave exenta no se nombra ahí; el día que
alguien la use, la exención se cae sola y el test lo dice. Verificado por mutación: nombrarla en un
módulo del cajón pone el caso en rojo.

⚠️ **Y el corolario**: una excepción que no se puede comprobar es una señal de que la guarda está mal
formulada, no de que el caso sea especial.

### 3. Guardas de arquitectura — `tests/Feature/Architecture/`
Tests que no prueban una feature sino una REGLA estructural; sin ellos el refactor de Fase 2 se
degrada en silencio.
- **`ModuleBoundariesTest`** — la frontera entre módulos de `app/Domain`
  (`docs/specs/modulos-dominio.md` §4). Escanea con el **tokenizador de PHP**, no con regex
  (los docblocks de los contratos citan clases legacy a propósito). Tres guardas: grafo
  permitido (`ALLOWED`) · baselines `SEAM`/`LEGACY` que **solo pueden encoger** (una entrada
  que deja de usarse hace fallar el test → hay que borrarla) · desde fuera de `app/Domain` solo
  se tocan los `Contracts`. Además falla si el escaneo se queda vacío o si un módulo nuevo no
  declara sus flechas.
- **`ModuleContractsTest`** — la otra mitad: sustituye cada contrato por un doble y exige que el
  consumidor real cambie de conducta. Si alguien vuelve a llamar a la implementación legacy por
  debajo, el doble se queda sin usar y el test cae.
- **`MorphMapTest`** (en `tests/Feature/`) — alias de morph estables para todo modelo, en
  `app/Models` **y** `app/Domain/*/Models`. Incluye el guard de la **migración congelada**:
  los FQCN de `convert_morph_types_to_aliases` son los valores que la BD tenía en 2026-08-12
  (DATOS, no rutas de código) y un `sed` global de la modularización los rompería en silencio.

- **`ApiBoundariesTest`** (Fase 3 · paso 0) — un controlador de `/api/v1` traduce HTTP ↔ dominio y
  nada más: prohíbe transacciones, escrituras de Eloquent y contadores de intentos en la capa HTTP.
  Hacía falta una guarda propia porque `ModuleBoundariesTest` **no** vigila esto (su lista
  `DELIVERY` incluye `Http`). Es la versión falsable del criterio «ninguna regla de negocio nace en
  un controlador de API».
- **`CriticalPathGateTest`** (Fase 3 · paso 0) — trae al terreno de la suite la regex
  `CRITICAL_RE` del hook `pre-push`: comprueba que sigue cubriendo el núcleo de dinero/aforo, que
  **no** es un comodín (un gate que salta siempre acaba desactivado a mano) y que ningún
  controlador de API alcanza ese núcleo con un nombre fuera del patrón.
- **`ApiContractTest`** (Fase 3 · paso 0) — rutas registradas ↔ `paths` del OpenAPI en las DOS
  direcciones, códigos de error del enum ↔ los del documento, y que los esquemas sean lo bastante
  estrictos (`additionalProperties: false` + `required` completo) para que renombrar un campo
  ponga en rojo el test del endpoint. Esa estrictez ES la prueba por mutación.

> Al tocar estos tests: modificar una baseline para AÑADIR una entrada es casi siempre la
> señal de que la mudanza está mal hecha, no de que la lista se haya quedado corta.

### 4. Contrato de la API — Spectator + `openapi/v1.yaml` *(Fase 3)*
La especificación **manda sobre el código** (`DECISIONES #21`): se escribe a mano y el test cae
cuando la implementación se desvía, no al revés.
- Un test de endpoint hereda de **`Tests\Feature\Api\ApiTestCase`**: eso activa la validación de la
  **respuesta real** contra el esquema en cada petición (`assertValidResponse(200)`), no solo la
  existencia de la ruta. Configura Spectator desde el propio caso base —y no publicando su fichero
  de configuración en `config/`— porque es dependencia de desarrollo y su config no debe viajar a
  producción.
- Para probar CIMIENTOS con rutas sintéticas (validación, fallos, límites) hay que heredar de
  `Tests\TestCase` y montar la ruta con `Route::middleware('api')`: si no, Spectator falla por
  «path no declarado» en lugar de probar lo que toca.
- `Tests\TestCase::setUp()` vacía `Accept-Language`, así que **un test de negociación de idioma que
  no ponga la cabecera está probando el fallback**, no la negociación.

## Entorno de pruebas (`phpunit.xml`)
- **BD:** SQLite `:memory:` (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`). Las migraciones
  se aplican **una vez por proceso** (`RefreshDatabase`), no por test.
- **Hash:** `BCRYPT_ROUNDS=4` (rápido en test; producción usa 12).
- **Drivers efímeros:** `CACHE_STORE=array`, `SESSION_DRIVER=array`, `QUEUE_CONNECTION=sync`,
  `MAIL_MAILER=array`. Telescope/Pulse/Nightwatch desactivados.
- **Aislamiento del memo de `Setting`:** `tests/TestCase.php::setUp()` llama a
  `Setting::flushMemo()` — el rollback de `RefreshDatabase` no dispara eventos
  `saved`/`deleted`, así que sin el flush un ajuste creado por un test contaminaría los
  siguientes del mismo proceso (decisión #272 del origen; histórico en el repo origen).

## Dónde va cada test
- **Servicio/value-object puro** (sin BD ni facades) → `tests/Unit` extendiendo
  `PHPUnit\Framework\TestCase`, **sin** `RefreshDatabase` (feedback instantáneo). Ver
  `CONVENCIONES.md` §3.ter.
- **Todo lo demás** (BD/HTTP/Livewire/Filament) → `tests/Feature` extendiendo `Tests\TestCase`.
- **Definición de "Hecho"**: código + prueba automática + validación empírica
  (`CONVENCIONES.md` §3.bis).

## Verificación de concurrencia REAL (fuera de la suite)
La suite corre en **SQLite `:memory:`** (`phpunit.xml`), que **no reproduce los locks de
InnoDB**: las carreras de concurrencia (doble-cobro, sobreventa) no se ejercitan en CI. Para
verificarlas de verdad hay herramientas **on-demand** (no parte de la suite, exigen MySQL +
`pcntl`; comandos en `app/Console/Commands/Verify*.php`):

```bash
docker compose exec -u sail laravel.test php artisan redsys:verify-concurrency --workers=16   # doble-cobro (handler)
docker compose exec -u sail laravel.test php artisan purchase:verify-oversell  --workers=16   # sobreventa (compra)
docker compose exec -u sail laravel.test php artisan redsys:verify-sandbox                    # reembolso REST (sandbox real)
```

- **`redsys:verify-concurrency`**: N notificaciones Redsys en **paralelo real (`pcntl_fork`)**
  sobre el mismo pago → verifica que el `lockForUpdate` del handler serializa: 1 cobro,
  1 ticket, sin duplicados (1 `authorized` + N-1 `idempotent_paid`). Correr tras tocar
  `RedsysReturnHandler` o los locks del pago.
- **`purchase:verify-oversell`**: N compras de la ÚLTIMA plaza en paralelo → verifica que
  `OrderCreator` NO sobrevende (1 compra + N-1 `sold_out`, asientos == aforo). En el origen
  **reprodujo un bug REAL de sobreventa** (subconsulta del `lockForUpdate` que fijaba el
  snapshot; ya arreglado en la base heredada). Correr tras tocar `OrderCreator::lockSlots`
  o el aforo.
- **`redsys:verify-sandbox`**: llamada REST de devolución de VERDAD contra el sandbox de
  Redsys; valida conectividad + firma + formato. Con `--gateway-order=<op>` sobre una
  autorización real → `Ds_Response=0900`.

Todas son **dev-only** (gateadas a no-producción) y limpian siempre lo que crean.

## Datos de prueba (el fixture de la suite)

> Verificado contra código: 2026-08-12. Recuento vivo de la suite → `ESTADO.md`
> (fuente única, CONVENCIONES §4); las cifras de tiempos de arriba son del origen.

- **127 de 213 ficheros de test siembran** (`grep -rl "this->seed(" tests | wc -l`):
  `RoleSeeder` ×93 · `PermissionSeeder` ×91 (ritual estándar del panel) ·
  `LandingContentSeeder` ×57 · `LandingServicesSeeder` ×4 · `SalesSeeder` ×1.
- **`LandingContentSeeder` ES el fixture de la suite** y a la vez la semilla de demo local
  (hasta la semilla neutra de Fase 1). Tiene un **contrato de conteos** asertado por tests:
  **8 entradas vendibles** (`CatalogTest::test_seeds_eight_sellable_entries`) · **2 packs** y
  **14 vendibles totales** (`PackFoundationTest`) · **4 addons** (`AddonFoundationTest`) ·
  **2 destacadas, una por zona** (`CatalogTest`) · **15/8 atracciones con foto real**
  (`ZoneImageTest`) · **2 packs sin LandingService** (`LandingServiceTest`).
  ⚠️ **Tocar el seeder «para mejorar la demo» rompe la suite**: cualquier cambio renegocia
  el contrato JUNTO con esos tests. Por eso `DemoPaidAttractionSeeder` vive fuera del
  seeder de contenido (comentario en `DatabaseSeeder`).
- **Factories: solo `UserFactory`** (592 usos, todos `User::factory()`); el resto del
  fixture se monta con `::create` directo (~1.100 usos: `TicketType` 189 · `Zone` 142 ·
  `Order` 125 · `Slot` 122…). No hay trait compartido: el helper `admin()`/`staff()`
  (factory + sync de rol) está duplicado en ~36 ficheros — deuda registrada en `DEUDA.md`.
- **Escenarios nuevos**: seguir el patrón dominante — sembrar roles/permisos si el test toca
  panel, y construir catálogo/franjas con `::create` explícito del caso (no ampliar el
  seeder-fixture para un test).
