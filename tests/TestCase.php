<?php

namespace Tests;

use App\Domain\Platform\Models\Setting;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
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
    }
}
