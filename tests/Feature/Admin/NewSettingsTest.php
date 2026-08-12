<?php

namespace Tests\Feature\Admin;

use App\Domain\Platform\Models\Setting;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 7.0 — Settings nuevos sembrados (decisiones #118 y #120).
 *
 * Ver `docs/PLAN-FASE-7-PANEL.md` §2.3.
 */
class NewSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_fiscal_placeholders_are_seeded(): void
    {
        $this->seed(LandingContentSeeder::class);

        // [PENDIENTE] = los aporta la clienta cuando los tenga. El panel los pinta
        // como placeholders y permite edición; la web pública sigue mostrando
        // los neutros mientras tanto.
        $this->assertSame('[PENDIENTE]', Setting::value('business.legal_name'));
        $this->assertSame('[PENDIENTE]', Setting::value('business.nif'));
        $this->assertSame('[PENDIENTE]', Setting::value('business.address'));

        // Grupo "business" — el panel agrupa así para "Datos del negocio e información fiscal".
        $this->assertSame(
            ['business.address', 'business.city', 'business.domain', 'business.legal_name', 'business.name', 'business.nif'],
            Setting::where('group', 'business')->orderBy('key')->pluck('key')->all(),
        );
    }

    public function test_manual_hold_minutes_default_is_24h(): void
    {
        $this->seed(LandingContentSeeder::class);

        $this->assertSame('1440', Setting::value('sales.manual_hold_minutes'),
            'sales.manual_hold_minutes default = 1440 min (24h) para pedidos manuales con enlace email.');

        // Sigue distinto del hold síncrono de Redsys (15 min, decisión #62).
        $this->assertSame('15', Setting::value('sales.hold_minutes'));
    }
}
