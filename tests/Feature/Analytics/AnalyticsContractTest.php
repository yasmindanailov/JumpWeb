<?php

namespace Tests\Feature\Analytics;

use App\Domain\Platform\Services\Analytics\Contract;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * **EL CONTRATO DE EVENTOS TIENE UNA VERDAD, EN PHP, Y EL YAML LA COPIA** (`docs/specs/analitica.md` §4.2).
 *
 * El `enum` de `name` en `openapi/v1.yaml` es lo que un cliente móvil o una landing leen; `Contract::EVENTS`
 * es lo que la ingesta acepta. Si divergen, un evento válido para uno es inválido para el otro y nadie se
 * entera (molde: `test_the_event_field_types_are_the_same_in_the_domain_and_in_the_contract`). El JS entra
 * en esta guarda con `track.js` (T1b).
 */
class AnalyticsContractTest extends TestCase
{
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
        $this->assertFalse(Contract::looksLikePii('verano-2026'));
        $this->assertFalse(Contract::looksLikePii('12'));
    }
}
