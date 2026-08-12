# Testing — cómo se ejecutan y reglas de la suite

> Adaptado del proyecto origen (2026-08-12). Describe la BASE HEREDADA: el refactor
> (`00-REFACTOR.md`) puede haberla cambiado. Verifica contra el código antes de construir
> encima (CONVENCIONES §Verificación).

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
