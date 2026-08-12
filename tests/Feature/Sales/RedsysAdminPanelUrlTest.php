<?php

namespace Tests\Feature\Sales;

use App\Models\Setting;
use App\Support\Redsys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sub-fase 7.2a — Helper Redsys::adminPanelUrl() para que el panel admin enlace al
 * portal del comercio Redsys del entorno activo. Sin deep-link a transacción concreta
 * (Redsys no lo soporta; ver docs/PLAN-REDSYS.md §14.7).
 */
class RedsysAdminPanelUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_sandbox_url_when_environment_is_test(): void
    {
        Setting::updateOrCreate(['key' => 'redsys_environment'], ['value' => 'test', 'group' => 'payment']);

        $this->assertSame(Redsys::ADMIN_URL_TEST, app(Redsys::class)->adminPanelUrl());
    }

    public function test_returns_live_url_when_environment_is_live(): void
    {
        Setting::updateOrCreate(['key' => 'redsys_environment'], ['value' => 'live', 'group' => 'payment']);

        $this->assertSame(Redsys::ADMIN_URL_LIVE, app(Redsys::class)->adminPanelUrl());
    }

    public function test_defaults_to_sandbox_when_setting_missing(): void
    {
        // Sin setting → fallback no destructivo: sandbox (defensa #113 patrón).
        $this->assertSame(Redsys::ADMIN_URL_TEST, app(Redsys::class)->adminPanelUrl());
    }

    public function test_falls_back_to_sandbox_for_unknown_environment_value(): void
    {
        // Setting corrupto desde el panel (Fase 7) o por error de tipeo: no debe abrir
        // el portal de producción por accidente — solo 'live' es producción explícita.
        Setting::updateOrCreate(['key' => 'redsys_environment'], ['value' => 'foobar', 'group' => 'payment']);

        $this->assertSame(Redsys::ADMIN_URL_TEST, app(Redsys::class)->adminPanelUrl());
    }
}
