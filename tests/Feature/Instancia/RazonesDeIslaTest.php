<?php

namespace Tests\Feature\Instancia;

use App\Domain\Content\Services\Lucide;
use App\Http\Instancia\RazonesDeIsla;
use Tests\TestCase;

/**
 * **Las razones de la isla llegan con su icono DIBUJADO** (Z6b de `isla-y-landing-nueva.md` §4.27).
 *
 * El icono de cada razón es dato de la instalación; la isla no carga su dibujo (cero bytes en el paquete): se lo da el
 * servidor, con el mismo `Lucide::svg()` que pinta los iconos de las páginas. Lo que no es un icono del set no dibuja nada,
 * y lo demás de lo que la página da a su isla no se toca.
 */
class RazonesDeIslaTest extends TestCase
{
    protected function tearDown(): void
    {
        Lucide::forget();
        parent::tearDown();
    }

    public function test_each_reason_gets_the_drawing_of_its_icon_and_nothing_else_changes(): void
    {
        $isla = [
            'page' => ['kind' => 'producto', 'from' => 'Desde 8 €'],
            'razones' => [
                'llegada' => ['type' => 'razon', 'icon' => 'shield-check', 'text' => 'Su zona, a su medida'],
                'precio' => ['type' => 'razon', 'icon' => 'wallet', 'text' => 'Hoy solo pagas 50 €', 'decision' => true],
                'vivo' => ['type' => 'vivo', 'icon' => null, 'text' => 'Sáb 3 y dom 4, libres'],
            ],
            'frases' => ['calcula' => ['text' => 'Solo pagas los niños que vengan.', 'decision' => true]],
        ];

        $con = RazonesDeIsla::conDibujos($isla);

        $this->assertSame(Lucide::svg('shield-check'), $con['razones']['llegada']['svg']);
        $this->assertSame(Lucide::svg('wallet'), $con['razones']['precio']['svg']);
        $this->assertStringContainsString('<svg', $con['razones']['precio']['svg'], 'el fichero del set, con su licencia delante');
        $this->assertStringContainsString('width="100%"', $con['razones']['precio']['svg'], 'el dibujo del diseño: manda su caja');
        $this->assertArrayNotHasKey('svg', $con['razones']['vivo'], 'lo vivo no lleva icono: lleva su punto');
        // Lo demás, tal cual.
        $sin = fn (array $a): array => [...$a, 'razones' => array_map(fn (array $r): array => array_diff_key($r, ['svg' => true]), $a['razones'])];
        $this->assertSame($isla, $sin($con));
    }

    public function test_a_name_that_is_not_an_icon_draws_nothing_and_a_page_without_reasons_is_left_alone(): void
    {
        $con = RazonesDeIsla::conDibujos(['razones' => [
            'a' => ['icon' => 'no-existe-este-icono', 'text' => 'x'],
            'b' => ['icon' => '../../.env', 'text' => 'y'],
            'c' => 'no es una razón',
            // Un camino que SÍ lleva a un dibujo del set: solo el nombre lo para.
            'd' => ['icon' => '../icons/wallet', 'text' => 'z'],
        ]]);

        $this->assertArrayNotHasKey('svg', $con['razones']['a']);
        $this->assertArrayNotHasKey('svg', $con['razones']['b'], 'el nombre es una ruta de disco: se valida antes de leer');
        $this->assertSame('no es una razón', $con['razones']['c']);
        $this->assertArrayNotHasKey('svg', $con['razones']['d'], 'un nombre con «../» no es un icono, aunque el fichero exista');

        $this->assertSame(['page' => ['kind' => 'apoyo']], RazonesDeIsla::conDibujos(['page' => ['kind' => 'apoyo']]));
        $this->assertSame(['razones' => []], RazonesDeIsla::conDibujos(['razones' => []]));
    }
}
