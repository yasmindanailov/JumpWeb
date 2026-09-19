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
     * **El JSON-LD y la página escriben LA MISMA dirección.**
     *
     * ❗❗❗ Este caso es el que cierra el defecto que pagó la duplicación: hasta `#650` la regla estaba
     * en dos sitios —la página y `StructuredData::postalAddress()`— con filtros distintos, y la copia
     * del JSON-LD llegó a producir un **verde falso** (un caso que aseveraba sobre la página entera
     * pasaba porque el `streetAddress` decía lo correcto aunque el bloque visible dijera otra cosa).
     * Con una sola implementación, divergir ya no es posible — y esto lo demuestra.
     */
    public function test_the_structured_data_uses_the_same_rule(): void
    {
        $grafo = StructuredData::businessGraph([
            'address1' => '  Ctra. de Prueba, 1 ',
            'address2' => '30000 Ciudad',
            'city' => 'Ciudad',
        ]);

        $direccion = $this->buscarDireccion($grafo);

        $this->assertNotNull($direccion, 'el grafo ya no publica una `PostalAddress`');
        $this->assertSame(
            VenueAddress::written('Ctra. de Prueba, 1', '30000 Ciudad'),
            $direccion['streetAddress'] ?? null,
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
