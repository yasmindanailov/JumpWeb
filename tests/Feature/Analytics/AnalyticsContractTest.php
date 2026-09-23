<?php

namespace Tests\Feature\Analytics;

use App\Domain\Platform\Services\Analytics\Contract;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * **EL CONTRATO DE EVENTOS TIENE UNA VERDAD, EN PHP, Y EL YAML LA COPIA** (`docs/specs/analitica.md` §4.2).
 *
 * El `enum` de `name` en `openapi/v1.yaml` es lo que un cliente móvil o una landing leen; `Contract::EVENTS`
 * es lo que la ingesta acepta. Si divergen, un evento válido para uno es inválido para el otro y nadie se
 * entera (molde: `test_the_event_field_types_are_the_same_in_the_domain_and_in_the_contract`).
 *
 * Y el JS (T1b): los nombres que `track.js` emite y los que las vistas declaran en `data-jw-track` se leen del
 * FUENTE (molde: `SidebarMountTest`, sin comentarios) y tienen que ser eventos de CLIENTE del contrato. Un
 * nombre que no lo sea no rompe nada en el navegador —la ingesta lo descarta evento a evento— y por eso hace
 * falta esta guarda: sin ella, un evento mal escrito se perdería en silencio para siempre.
 */
class AnalyticsContractTest extends TestCase
{
    /** Lo que `track.js` tiene que emitir por sí mismo (spec §4.2); lo demás llega por `data-jw-track` o por `JumpWeb.track()`. */
    private const EMITTED_BY_THE_TRACKER = [
        'page_viewed', 'section_viewed', 'call_clicked', 'whatsapp_clicked', 'map_clicked',
        'drawer_opened', 'step_entered', 'drawer_closed', 'request_failed', 'client_error', 'consent_updated', 'batch_dropped',
    ];

    /** @return list<string> los nombres de evento escritos en `track.js`, sin comentarios */
    private function nombresDelTracker(): array
    {
        $js = (string) file_get_contents(resource_path('js/cajon/track.js'));
        $code = (string) preg_replace(['~/\*.*?\*/~s', '~^\s*//.*$~m'], '', $js);

        preg_match_all("~(?:track|event)\\('([a-z_]+)'|'([a-z]+_[a-z_]+)'\\]~", $code, $m);

        return array_values(array_unique(array_filter([...$m[1], ...$m[2]])));
    }

    /** @return list<string> los valores de `data-jw-track` en las vistas */
    private function nombresDeLasVistas(): array
    {
        $names = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            preg_match_all('~data-jw-track="([^"]*)"~', (string) file_get_contents($file->getPathname()), $m);
            array_push($names, ...$m[1]);
        }

        return array_values(array_unique($names));
    }

    public function test_the_tracker_emits_only_client_events_and_all_it_promises(): void
    {
        $emitted = $this->nombresDelTracker();
        $client = Contract::names(Contract::CLIENT);

        $this->assertSame([], array_values(array_diff($emitted, $client)), '`track.js` emite un nombre que el contrato no conoce como evento de cliente');
        $this->assertSame([], array_values(array_diff(self::EMITTED_BY_THE_TRACKER, $emitted)), '`track.js` ha dejado de emitir un evento que la spec le encarga');
    }

    public function test_the_views_only_declare_client_events(): void
    {
        $this->assertSame([], array_values(array_diff($this->nombresDeLasVistas(), Contract::names(Contract::CLIENT))), 'una vista lleva un `data-jw-track` que la ingesta descartaría');
    }

    /** @return list<string> */
    private function enumDelYaml(): array
    {
        $yaml = Yaml::parseFile(base_path(config('api.openapi.directory').'/'.config('api.openapi.file')));

        return $yaml['components']['schemas']['EventsBatch']['properties']['events']['items']['properties']['name']['enum'];
    }

    public function test_the_yaml_enum_lists_exactly_the_client_events(): void
    {
        $this->assertSame(Contract::names(Contract::CLIENT), $this->enumDelYaml(), 'el `enum` del contrato y los eventos de cliente de PHP no coinciden');
    }

    /** Un hecho de SERVIDOR no puede estar en el `enum`: si estuviera, el contrato prometería una ingesta que el servidor rechaza. */
    public function test_no_server_event_is_offered_to_the_client(): void
    {
        $this->assertSame([], array_intersect(Contract::names(Contract::SERVER), $this->enumDelYaml()));
        $this->assertNotEmpty(Contract::names(Contract::SERVER));
    }

    public function test_every_event_declares_a_source_and_a_closed_list_of_props(): void
    {
        foreach (Contract::EVENTS as $name => $definition) {
            $this->assertMatchesRegularExpression('/^[a-z][a-z0-9_]+$/', $name, "«{$name}» no es una clave snake_case");
            $this->assertContains($definition['source'], [Contract::CLIENT, Contract::SERVER], "«{$name}» no dice quién lo emite");
            foreach ($definition['props'] as $prop) {
                $this->assertFalse(Contract::isPiiKey($prop), "«{$name}.{$prop}» es una prop con pinta de dato personal");
            }
        }
    }

    public function test_the_pii_guards_catch_what_they_promise(): void
    {
        $this->assertTrue(Contract::isPiiKey('guest_age_min'));
        $this->assertTrue(Contract::isPiiKey('Email'));
        $this->assertFalse(Contract::isPiiKey('product'));
        $this->assertTrue(Contract::looksLikePii('escríbeme a ana@example.com'));
        $this->assertTrue(Contract::looksLikePii('llama al +34 600 123 456'));
        $this->assertTrue(Contract::looksLikePii('600123456'));
        $this->assertTrue(Contract::looksLikePii('(0034) 91-123-45-67'));
        $this->assertFalse(Contract::looksLikePii('verano-2026'));
        $this->assertFalse(Contract::looksLikePii('12'));
        // ⚠️ Una fecha NO es un teléfono: ocho cifras. Contando caracteres lo era, y se llevaba `date_chosen` entero.
        $this->assertFalse(Contract::looksLikePii('2026-09-23'));
        $this->assertFalse(Contract::looksLikePii('/availability/395/2026-09-23'));
        $this->assertFalse(Contract::looksLikePii('R-L6UTIA9Z8X7W6V5U4T3S'));
    }
}
