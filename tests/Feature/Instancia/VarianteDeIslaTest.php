<?php

namespace Tests\Feature\Instancia;

use App\Http\Instancia\VarianteDeIsla;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * **La cara de la isla en una página** (la Z6c de `specs/isla-y-landing-nueva.md` §4.27, el experimento B3): la asigna el
 * servidor por visitante con la clave `isla`, y la variante asignada viaja para contar la exposición (`isla/medir.js`); sin
 * asignación, la de hoy y SIN exposición; la vista previa `?isla=b3`, tampoco es una exposición. Las variantes de quien mira
 * van por parámetro: aquí no se prueba el reparto (es del SPA, `ExperimentsTest`), sino lo que la página hace con él.
 */
class VarianteDeIslaTest extends TestCase
{
    private function pagina(string $url = '/kids'): Request
    {
        return Request::create($url);
    }

    public function test_without_the_island_on_the_page_there_is_nothing(): void
    {
        $this->assertNull(VarianteDeIsla::paraLaPagina(false, $this->pagina('/kids?isla=b3'), ['isla' => 'b3']));
    }

    public function test_assigned_by_the_server_it_shows_its_face_and_carries_the_assigned_variant(): void
    {
        $this->assertSame(['variante' => 'b3', 'experimento' => 'b3'], VarianteDeIsla::paraLaPagina(true, $this->pagina(), ['isla' => 'b3']));
        $this->assertSame(['variante' => 'hoy', 'experimento' => 'hoy'], VarianteDeIsla::paraLaPagina(true, $this->pagina(), ['isla' => 'hoy']));
        $this->assertSame(
            ['variante' => 'hoy', 'experimento' => 'control'],
            VarianteDeIsla::paraLaPagina(true, $this->pagina(), ['isla' => 'control']),
            'Una variante que no es la del B3 enseña la isla de hoy, y la exposición se cuenta con la que se ASIGNÓ.'
        );
    }

    public function test_without_a_live_experiment_the_island_of_today_and_no_exposure(): void
    {
        $this->assertSame(['variante' => 'hoy', 'experimento' => null], VarianteDeIsla::paraLaPagina(true, $this->pagina(), []));
        $this->assertSame(['variante' => 'hoy', 'experimento' => null], VarianteDeIsla::paraLaPagina(true, $this->pagina(), ['otro' => 'b3']));
    }

    public function test_the_preview_shows_b3_but_is_never_an_exposure(): void
    {
        $this->assertSame(['variante' => 'b3', 'experimento' => null], VarianteDeIsla::paraLaPagina(true, $this->pagina('/kids?isla=b3'), []));
        $this->assertSame(
            ['variante' => 'b3', 'experimento' => null],
            VarianteDeIsla::paraLaPagina(true, $this->pagina('/kids?isla=b3'), ['isla' => 'hoy']),
            'A quien le tocó la de hoy y mira la vista previa no se le cuenta: vio otra cosa.'
        );
        $this->assertSame(['variante' => 'hoy', 'experimento' => null], VarianteDeIsla::paraLaPagina(true, $this->pagina('/kids?isla=otra'), []));
    }
}
