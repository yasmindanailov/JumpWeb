<?php

namespace Tests;

use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\Turnstile;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * **Instrumento de auditoría del RELOJ** (`DECISIONES #162`, ficha «la suite no está auditada
     * contra la FECHA» de `DEUDA.md`). Con `TEST_CLOCK` en el entorno, la suite entera corre con el
     * reloj congelado en ese instante **UTC**:
     *
     *     docker compose exec -u sail -T -e TEST_CLOCK='2026-09-05 12:00:00' laravel.test \
     *         php artisan test
     *
     * ⚠️ **Sin la variable NO hace absolutamente nada** — ni una llamada, ni un `if` con efecto—,
     * así que la conducta por defecto de la suite es idéntica a antes. Es la condición para que esto
     * pueda vivir en `TestCase`, que es infraestructura compartida por los dos carriles.
     *
     * ⚠️ **Y si la variable trae basura, EXPLOTA.** Un instrumento que se autodesactiva en silencio
     * al no entender su entrada da un verde que no significa nada — que es exactamente el modo de
     * fallo que esta auditoría existe para cerrar.
     *
     * ▶ Un test que congela su propio reloj **gana**: `parent::setUp()` corre antes que el suyo. Es
     * lo correcto — esos ya son deterministas y la auditoría no tiene nada que decirles.
     */
    private const CLOCK_ENV = 'TEST_CLOCK';

    /**
     * **El otro modo: reloj DESPLAZADO pero EN MARCHA** (`DECISIONES #164`).
     *
     * ⚠️⚠️ `TEST_CLOCK` congela, y con el reloj congelado **el tiempo no avanza nunca**: por
     * construcción no puede reproducir el modo de fallo en que la suite **cruza la medianoche a
     * mitad de ejecución** —un test que lee `today()` dos veces y obtiene días distintos—, que es
     * justo la hipótesis que se le dio a `#97` y que nadie había probado.
     *
     * Con `TEST_CLOCK_START` el proceso calcula UNA vez el desfase entre ese instante y el reloj
     * real, y a partir de ahí el tiempo **corre normal, desplazado**. Poniendo el arranque unos
     * segundos antes de una medianoche, la suite la cruza mientras corre.
     *
     *     docker compose exec -u sail -T -e TEST_CLOCK_START='2026-08-27 23:59:20' laravel.test \
     *         php artisan test --parallel
     *
     * ⚠️ El desfase se calcula **una vez por proceso** (`self::$clockOffset`). Si se recalculara en
     * cada `setUp`, cada test volvería al instante de arranque y el reloj no avanzaría nunca — que
     * es exactamente el modo que este modo existe para NO tener.
     * ⚠️ Y la clausura no puede usar fábricas de Carbon que consulten el «ahora» de prueba
     * (`createFromTimestamp`): se llaman a sí mismas. **Medido: segfault por recursión infinita.**
     * Por eso construye con `DateTimeImmutable` puro y solo después envuelve.
     */
    private const CLOCK_START_ENV = 'TEST_CLOCK_START';

    /** Desfase en segundos del modo «en marcha». Se fija en el primer `setUp` del proceso. */
    private static ?int $clockOffset = null;

    /**
     * El cliente HTTP de testing de Laravel envía por defecto
     * `Accept-Language: en-us,en;q=0.5`. Como el middleware `SetLocale` autodetecta
     * por ese header (2026-05-26, auto-detect), todos los tests sin override
     * acabarían en inglés mientras el sitio real arranca en español.
     *
     * Anulamos ese header en setUp para que los tests respeten `config('app.locale')`
     * por defecto. Los tests que quieran probar el auto-detect (LocaleDetectionTest)
     * setean el header explícitamente con `withHeaders` y eso sigue funcionando.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('Accept-Language', '');

        // Guarda anti-red (ver `docs/TESTING.md`): NINGÚN test debe hacer una petición HTTP
        // real. Las salidas externas (Redsys REST, Cloudflare Turnstile, Have I Been Pwned vía
        // `Password::uncompromised`) se simulan con `Http::fake` o sustituyendo el contrato
        // (`UncompromisedVerifier`). Si un test nuevo dispara una petición sin simular, falla
        // aquí en vez de quedar lento/flaky (el verificador HIBP tiene timeout de 30 s) o
        // dependiente de la red. Validado: la suite completa pasa con esta guarda activa.
        Http::preventStrayRequests();

        // El memo estático de `Setting` sobrevive entre tests del mismo proceso: el rollback
        // de `RefreshDatabase` revierte la tabla sin disparar `saved`/`deleted`, así que un
        // ajuste creado por un test contaminaba los siguientes (p. ej. `display_timezone` o
        // la config Redsys). Se vacía aquí para que cada test parta de cero.
        Setting::flushMemo();

        // Y el de `Turnstile`, por el mismo motivo (`SUITE-02` lo exige para todo memo estático
        // nuevo). Lo llevaba sin purga desde que se añadió: dos ficheros de test acabaron con la
        // misma copia de un reset por Reflection porque, sin esto, unas claves configuradas por un
        // test decidían el resultado de los siguientes según el ORDEN en que corrieran.
        Turnstile::flushCache();

        $this->applyAuditClock();
    }

    /**
     * Congela el reloj si —y solo si— `TEST_CLOCK` viene en el entorno. Ver la constante.
     */
    private function applyAuditClock(): void
    {
        $raw = getenv(self::CLOCK_ENV);
        $start = getenv(self::CLOCK_START_ENV);

        $frozen = $raw !== false && trim($raw) !== '';
        $running = $start !== false && trim($start) !== '';

        if ($frozen && $running) {
            throw new RuntimeException(
                self::CLOCK_ENV.' y '.self::CLOCK_START_ENV.' son EXCLUYENTES: uno congela el reloj y '
                .'el otro lo deja correr desplazado. Con los dos puestos, el resultado dependería del '
                .'orden en que se apliquen, y una medición así no dice nada.'
            );
        }

        if ($running) {
            $this->applyRunningClock(trim($start));

            return;
        }

        if (! $frozen) {
            return;
        }

        try {
            $at = Carbon::parse(trim($raw), 'UTC');
        } catch (\Throwable $e) {
            throw new RuntimeException(
                self::CLOCK_ENV." = «{$raw}» no es un instante que Carbon entienda. "
                .'Usa algo como «2026-09-05 12:00:00» (se interpreta en UTC). '
                .'Esto explota a propósito: un instrumento que se autodesactiva en silencio da un '
                .'verde que no significa nada.',
                0, $e
            );
        }

        Carbon::setTestNow($at);
    }

    /**
     * Reloj desplazado y EN MARCHA. Ver {@see self::CLOCK_START_ENV}.
     */
    private function applyRunningClock(string $raw): void
    {
        if (self::$clockOffset === null) {
            try {
                self::$clockOffset = Carbon::parse($raw, 'UTC')->getTimestamp() - time();
            } catch (\Throwable $e) {
                throw new RuntimeException(
                    self::CLOCK_START_ENV." = «{$raw}» no es un instante que Carbon entienda. "
                    .'Usa algo como «2026-08-27 23:59:20» (se interpreta en UTC).',
                    0, $e
                );
            }
        }

        $offset = self::$clockOffset;

        Carbon::setTestNow(static fn (): Carbon => Carbon::instance(
            new \DateTimeImmutable('@'.(time() + $offset))
        )->setTimezone('UTC'));
    }
}
