<?php

namespace Tests\Feature\Sales;

use App\Domain\Platform\Models\Setting;
use App\Support\PaymentSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit hardening #113 (M2) — Helpers defensivos para settings de pago.
 *
 * El panel admin (Fase 7) o tinker pueden escribir cualquier valor en `settings`. Estos
 * tests blindan que:
 *  - Valores correctos se devuelven tal cual.
 *  - Valores corruptos (string, fuera de rango, negativo) NO rompen el flujo: caen al default.
 *  - Setting ausente cae al default.
 */
class PaymentSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_hold_minutes_returns_default_when_setting_missing(): void
    {
        $this->assertSame(PaymentSettings::HOLD_MINUTES_DEFAULT, PaymentSettings::holdMinutes());
    }

    public function test_hold_minutes_respects_valid_setting(): void
    {
        Setting::create(['key' => 'sales.hold_minutes', 'value' => '30', 'group' => 'payment']);

        $this->assertSame(30, PaymentSettings::holdMinutes());
    }

    public function test_hold_minutes_falls_back_when_not_an_integer(): void
    {
        // Defense in depth: si el panel admin permite escribir 'abc', no queremos que
        // (int)'abc'=0 cause que todas las Orders caduquen inmediatamente.
        Setting::create(['key' => 'sales.hold_minutes', 'value' => 'abc', 'group' => 'payment']);

        $this->assertSame(PaymentSettings::HOLD_MINUTES_DEFAULT, PaymentSettings::holdMinutes());
    }

    public function test_hold_minutes_falls_back_when_zero_or_negative(): void
    {
        Setting::create(['key' => 'sales.hold_minutes', 'value' => '0', 'group' => 'payment']);
        $this->assertSame(PaymentSettings::HOLD_MINUTES_DEFAULT, PaymentSettings::holdMinutes());

        Setting::where('key', 'sales.hold_minutes')->update(['value' => '-5']);
        $this->assertSame(PaymentSettings::HOLD_MINUTES_DEFAULT, PaymentSettings::holdMinutes());
    }

    public function test_hold_minutes_falls_back_when_above_max(): void
    {
        // 4 horas (240 min) es el tope sensato: una retención mayor sería antiabuso (un
        // cliente podría retener una plaza media jornada sin pagar).
        Setting::create(['key' => 'sales.hold_minutes', 'value' => '999999', 'group' => 'payment']);

        $this->assertSame(PaymentSettings::HOLD_MINUTES_DEFAULT, PaymentSettings::holdMinutes());
    }

    public function test_redsys_currency_returns_default_when_setting_missing(): void
    {
        $this->assertSame('978', PaymentSettings::redsysCurrency());
    }

    public function test_redsys_currency_respects_valid_iso_4217_numeric(): void
    {
        Setting::create(['key' => 'redsys_currency', 'value' => '840', 'group' => 'payment']); // USD

        $this->assertSame('840', PaymentSettings::redsysCurrency());
    }

    public function test_redsys_currency_falls_back_when_non_numeric(): void
    {
        Setting::create(['key' => 'redsys_currency', 'value' => 'EUR', 'group' => 'payment']); // alfa-3 NO numérico

        $this->assertSame('978', PaymentSettings::redsysCurrency());
    }

    public function test_redsys_currency_falls_back_when_wrong_length(): void
    {
        Setting::create(['key' => 'redsys_currency', 'value' => '9788', 'group' => 'payment']);
        $this->assertSame('978', PaymentSettings::redsysCurrency());

        Setting::where('key', 'redsys_currency')->update(['value' => '12']);
        $this->assertSame('978', PaymentSettings::redsysCurrency());
    }
}
