<?php

namespace Tests\Feature\Support;

use App\Models\Setting;
use App\Support\IncidentSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Visibilidad de incidencias (recomendación C, 2026-06-15) — `IncidentSettings::alertEmail()`:
 * cadena de resolución del destinatario de los avisos de incidencia de cobro, con fallback no
 * destructivo (mismo patrón defensivo que `PaymentSettings`).
 */
class IncidentSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_null_when_nothing_configured(): void
    {
        $this->assertNull(IncidentSettings::alertEmail());
    }

    public function test_falls_back_to_contact_email(): void
    {
        Setting::create(['key' => 'contact.email', 'value' => 'negocio@jumpingjump.test', 'group' => 'contact']);

        $this->assertSame('negocio@jumpingjump.test', IncidentSettings::alertEmail());
    }

    public function test_override_takes_precedence_over_contact_email(): void
    {
        Setting::create(['key' => 'contact.email', 'value' => 'negocio@jumpingjump.test', 'group' => 'contact']);
        Setting::create(['key' => 'incidents.alert_email', 'value' => 'tecnico@jumpingjump.test', 'group' => 'payment']);

        $this->assertSame('tecnico@jumpingjump.test', IncidentSettings::alertEmail());
    }

    public function test_invalid_override_falls_back_to_contact_email(): void
    {
        Setting::create(['key' => 'contact.email', 'value' => 'negocio@jumpingjump.test', 'group' => 'contact']);
        Setting::create(['key' => 'incidents.alert_email', 'value' => 'no-es-un-email', 'group' => 'payment']);

        $this->assertSame('negocio@jumpingjump.test', IncidentSettings::alertEmail());
    }

    public function test_returns_null_when_all_values_invalid(): void
    {
        Setting::create(['key' => 'contact.email', 'value' => '', 'group' => 'contact']);
        Setting::create(['key' => 'incidents.alert_email', 'value' => 'garbage', 'group' => 'payment']);

        $this->assertNull(IncidentSettings::alertEmail());
    }
}
