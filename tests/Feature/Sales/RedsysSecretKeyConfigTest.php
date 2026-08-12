<?php

namespace Tests\Feature\Sales;

use App\Domain\Platform\Models\Setting;
use App\Support\Redsys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Auditoría Fase 1 (H3) — La clave secreta de Redsys debe leerse de la capa `config`
 * (`config/services.php`, que es `env('REDSYS_SECRET_KEY')`), NO con `env()` directo en código
 * de app. Motivo: con la config CACHEADA (`php artisan config:cache`, paso del runbook de
 * despliegue) `env()` en runtime devuelve `null` → el cobro caería a la clave de sandbox =
 * apagón de cobros en producción. `config()` SÍ se hornea en la caché y sobrevive.
 */
class RedsysSecretKeyConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_reads_secret_key_from_config_so_it_survives_config_cache(): void
    {
        // `config()` simula el valor horneado por config:cache desde `.env`.
        config(['services.redsys.secret_key' => 'PROD_KEY_32_CHARS_aaaaaaaaaaaaaa']);

        $this->assertSame('PROD_KEY_32_CHARS_aaaaaaaaaaaaaa', (new Redsys)->config()['secret_key']);
    }

    public function test_falls_back_to_sandbox_setting_when_config_key_absent(): void
    {
        // Sin clave en config (entorno de desarrollo sandbox) → fallback a Setting / default público.
        config(['services.redsys.secret_key' => null]);

        $this->assertSame('sq7HjrUOBfKmC576ILgskD5srU870gJ7', (new Redsys)->config()['secret_key']);
    }

    public function test_setting_value_is_used_when_config_absent_and_setting_present(): void
    {
        config(['services.redsys.secret_key' => null]);
        Setting::create(['key' => 'redsys_secret_key', 'value' => 'from-settings-table', 'group' => 'payment']);

        $this->assertSame('from-settings-table', (new Redsys)->config()['secret_key']);
    }
}
