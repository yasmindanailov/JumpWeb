<?php

namespace Tests\Feature\Display;

use App\Models\Setting;
use App\Support\DisplayTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Decisión #111 (2026-05-28) — Zona horaria de presentación al cliente.
 *
 * Arquitectura: BD en UTC (white-label friendly), presentación en
 * `settings.display_timezone` (default `Europe/Madrid`). El helper
 * `App\Support\DisplayTime::format()` aplica la TZ al formatear.
 *
 * Estos tests blindan los tres aspectos críticos:
 *   1. Conversión correcta UTC → Madrid (CEST en mayo = UTC+2).
 *   2. Setting respetado: cambiar `display_timezone` cambia la salida.
 *   3. Fallback no destructivo: setting vacío o TZ inválida → no rompe vista.
 */
class DisplayTimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_formats_utc_datetime_in_europe_madrid_by_default(): void
    {
        // Sin setting → default Europe/Madrid (DEFAULT_TIMEZONE).
        // 28-05-2026 05:47:00 UTC = 28-05-2026 07:47:00 CEST (mayo, +2h).
        $utc = Carbon::create(2026, 5, 28, 5, 47, 0, 'UTC');

        $this->assertSame('28/05/2026 07:47', DisplayTime::format($utc));
    }

    public function test_respects_display_timezone_setting(): void
    {
        Setting::create(['key' => 'display_timezone', 'value' => 'America/New_York', 'group' => 'display']);
        // 28-05-2026 12:00:00 UTC = 28-05-2026 08:00:00 EDT (verano NY, -4h).
        $utc = Carbon::create(2026, 5, 28, 12, 0, 0, 'UTC');

        $this->assertSame('28/05/2026 08:00', DisplayTime::format($utc));
    }

    public function test_accepts_custom_format(): void
    {
        $utc = Carbon::create(2026, 5, 28, 5, 47, 0, 'UTC');

        // Solo fecha (sin hora) — caso `account/index.blade.php` consents.
        $this->assertSame('28/05/2026', DisplayTime::format($utc, 'd/m/Y'));
        // Formato ISO con offset (para auditoría / debug).
        $this->assertSame('2026-05-28 07:47:00 +0200', DisplayTime::format($utc, 'Y-m-d H:i:s O'));
    }

    public function test_accepts_string_input(): void
    {
        // Carbon::parse devuelve UTC por defecto cuando el string no trae offset.
        // (Útil porque algunos consumidores pasan el valor raw de BD sin instanciar Carbon.)
        $this->assertSame('28/05/2026 07:47', DisplayTime::format('2026-05-28 05:47:00'));
    }

    public function test_null_or_empty_returns_empty_string(): void
    {
        // La vista decide qué mostrar — el helper no inventa contenido.
        $this->assertSame('', DisplayTime::format(null));
        $this->assertSame('', DisplayTime::format(''));
    }

    public function test_invalid_setting_falls_back_to_default_timezone(): void
    {
        // Defensa en profundidad: si el panel de admin (Fase 7) deja escribir un valor
        // arbitrario en `display_timezone`, una TZ que no exista no debe romper la vista.
        Setting::create(['key' => 'display_timezone', 'value' => 'Mars/Olympus_Mons', 'group' => 'display']);
        $utc = Carbon::create(2026, 5, 28, 5, 47, 0, 'UTC');

        // Fallback al default (Europe/Madrid) → 07:47 CEST.
        $this->assertSame('28/05/2026 07:47', DisplayTime::format($utc));
    }

    public function test_unparseable_string_returns_empty_string(): void
    {
        // No queremos que la vista pete si alguien pasa basura.
        $this->assertSame('', DisplayTime::format('not-a-date'));
    }

    public function test_returns_configured_timezone_identifier(): void
    {
        Setting::create(['key' => 'display_timezone', 'value' => 'Europe/Paris', 'group' => 'display']);

        $this->assertSame('Europe/Paris', DisplayTime::timezone());
    }

    public function test_returns_default_when_setting_missing(): void
    {
        $this->assertSame('Europe/Madrid', DisplayTime::timezone());
    }
}
