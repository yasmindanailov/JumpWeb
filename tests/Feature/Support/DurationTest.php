<?php

namespace Tests\Feature\Support;

use App\Support\Duration;
use Tests\TestCase;

/**
 * Helper de formato humanizado de duración (decisión #148 iter): el empleado
 * piensa en horas, no en minutos. Tests cubren los casos límites del flujo
 * operativo (1h Jump, 1h 30min Cumpleaños XL, 3h Cumpleaños XXL).
 */
class DurationTest extends TestCase
{
    public function test_null_or_zero_returns_null(): void
    {
        $this->assertNull(Duration::formatHumane(null));
        $this->assertNull(Duration::formatHumane(0));
        $this->assertNull(Duration::formatHumane(-5));
    }

    public function test_under_one_hour_returns_minutes_only(): void
    {
        $this->assertSame('30 min', Duration::formatHumane(30));
        $this->assertSame('45 min', Duration::formatHumane(45));
        $this->assertSame('59 min', Duration::formatHumane(59));
    }

    public function test_exact_hours_returns_hours_only(): void
    {
        $this->assertSame('1h', Duration::formatHumane(60));
        $this->assertSame('2h', Duration::formatHumane(120));
        $this->assertSame('3h', Duration::formatHumane(180));
    }

    public function test_mixed_returns_hours_and_minutes(): void
    {
        $this->assertSame('1h 30min', Duration::formatHumane(90));
        $this->assertSame('2h 15min', Duration::formatHumane(135));
        $this->assertSame('3h 5min', Duration::formatHumane(185));
    }

    public function test_zh_locale_uses_chinese_units(): void
    {
        app()->setLocale('zh_CN');

        $this->assertSame('1 小时', Duration::formatHumane(60));
        $this->assertSame('30 分钟', Duration::formatHumane(30));
        $this->assertSame('1 小时 30 分钟', Duration::formatHumane(90));
    }
}
