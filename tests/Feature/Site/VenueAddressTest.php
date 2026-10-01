<?php

namespace Tests\Feature\Site;

use App\Domain\Content\Services\StructuredData;
use App\Domain\Platform\Services\VenueAddress;
use Tests\TestCase;

/**
 * **La dirección del parque, escrita en UN solo sitio** (F5 · T2b, `DECISIONES #650`).
 *
 * ❗❗ **Esta guarda afirma sobre el DATO, no sobre el marcado, y ésa es toda la idea** (`#649`): la
 * regla de cómo se escribe una dirección es del PRODUCTO y tiene que seguir viva el día que la
 * landing se vaya a su instancia. Antes solo la vigilaba un caso que leía el HTML de `/contacto`;
 * con la página fuera, ese caso se quedaba sin sujeto y la regla sin guarda.
 */
class VenueAddressTest extends TestCase
{
    /**
     * ⚠️⚠️ **La coma es la regla, y tiene su porqué**: con un espacio, «Ctra. de Prueba, 1 30000
     * Ciudad» se lee como si el código postal fuera parte del número de portal.
     */
    public function test_the_two_lines_are_written_with_a_comma(): void
    {
        $this->assertSame(
            'Ctra. de Prueba, 1, 30000 Ciudad',
            VenueAddress::written('Ctra. de Prueba, 1', '30000 Ciudad'),
        );
    }

    /**
     * ⚠️ **Con una sola línea no puede salir una coma colgando.** Es lo que haría un `implode` a secas
     * sobre un array con un vacío dentro, y se vería en la página como «Ctra. de Prueba, 1, ».
     */
    public function test_a_single_line_carries_no_dangling_comma(): void
    {
        $this->assertSame('Ctra. de Prueba, 1', VenueAddress::written('Ctra. de Prueba, 1', ''));
        $this->assertSame('30000 Ciudad', VenueAddress::written(null, '30000 Ciudad'));
        $this->assertSame('Ctra. de Prueba, 1', VenueAddress::written('  Ctra. de Prueba, 1  ', '   '));
    }

    /** Sin ninguna línea no hay dirección: `null`, y quien la publica se la calla. */
    public function test_without_lines_there_is_no_address(): void
    {
        $this->assertNull(VenueAddress::written(null, null));
        $this->assertNull(VenueAddress::written('', '   '));
    }

    /**
     * `seo.md` S4: la misma dirección POR CAMPOS, para el `PostalAddress` del JSON-LD. La segunda línea española
     * («30800 Lorca, Murcia») se parte en código postal, localidad y provincia, y la calle es la primera.
     */
    public function test_the_spanish_second_line_splits_into_postal_code_locality_and_region(): void
    {
        $this->assertSame(
            ['street' => 'Pol. Ind. Los Peñones, Ctra. de Granada, km 163', 'postalCode' => '30800', 'locality' => 'Lorca', 'region' => 'Murcia'],
            VenueAddress::parts(' Pol. Ind. Los Peñones, Ctra. de Granada, km 163 ', '30800 Lorca, Murcia'),
        );
        // Sin provincia: la localidad sola.
        $this->assertSame(
            ['street' => 'Ctra. de Prueba, 1', 'postalCode' => '30000', 'locality' => 'Ciudad', 'region' => null],
            VenueAddress::parts('Ctra. de Prueba, 1', '30000 Ciudad'),
        );
    }

    /** Otra forma —u otro país— no se adivina: la calle es la dirección escrita entera y nada se inventa. */
    public function test_a_second_line_in_another_form_is_not_guessed(): void
    {
        $this->assertSame(
            ['street' => 'Ctra. de Prueba, 1, Ciudad 30000', 'postalCode' => null, 'locality' => null, 'region' => null],
            VenueAddress::parts('Ctra. de Prueba, 1', 'Ciudad 30000'),
        );
        $this->assertSame(
            ['street' => '30000 Ciudad', 'postalCode' => null, 'locality' => null, 'region' => null],
            VenueAddress::parts(null, '30000 Ciudad'),
        );
        $this->assertSame(['street' => null, 'postalCode' => null, 'locality' => null, 'region' => null], VenueAddress::parts('', ''));
    }

    /**
     * **El JSON-LD toma la dirección de ESTA clase, no la compone por su cuenta.**
     *
     * ❗❗❗ Este caso es el que cierra el defecto que pagó la duplicación: hasta `#650` la regla estaba
     * en dos sitios —la página y `StructuredData::postalAddress()`— con filtros distintos, y la copia
     * del JSON-LD llegó a producir un **verde falso** (un caso que aseveraba sobre la página entera
     * pasaba porque el `streetAddress` decía lo correcto aunque el bloque visible dijera otra cosa).
     * Con una sola implementación, divergir ya no es posible — y esto lo demuestra.
     * ▶ Desde `seo.md` S4 el JSON-LD la quiere POR CAMPOS ({@see VenueAddress::parts()}); la página la sigue escribiendo
     * en una línea ({@see VenueAddress::written()}). Las dos reglas viven aquí, así que el caso compara con `parts()`.
     */
    public function test_the_structured_data_uses_the_same_rule(): void
    {
        $grafo = StructuredData::businessGraph([
            'address1' => '  Ctra. de Prueba, 1 ',
            'address2' => '30000 Ciudad, Provincia',
            'city' => 'Otra',
        ]);

        $direccion = $this->buscarDireccion($grafo);
        $partes = VenueAddress::parts('Ctra. de Prueba, 1', '30000 Ciudad, Provincia');

        $this->assertNotNull($direccion, 'el grafo ya no publica una `PostalAddress`');
        $this->assertSame(
            [$partes['street'], $partes['postalCode'], $partes['locality'], $partes['region']],
            [$direccion['streetAddress'] ?? null, $direccion['postalCode'] ?? null, $direccion['addressLocality'] ?? null, $direccion['addressRegion'] ?? null],
            'el JSON-LD compone la dirección por su cuenta y puede divergir de la que lee el visitante',
        );
    }

    /** @param array<mixed> $grafo */
    private function buscarDireccion(array $grafo): ?array
    {
        foreach ($grafo as $valor) {
            if (is_array($valor)) {
                if (($valor['@type'] ?? null) === 'PostalAddress') {
                    return $valor;
                }
                if (($encontrada = $this->buscarDireccion($valor)) !== null) {
                    return $encontrada;
                }
            }
        }

        return null;
    }
}
