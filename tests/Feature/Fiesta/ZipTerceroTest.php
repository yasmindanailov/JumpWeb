<?php

namespace Tests\Feature\Fiesta;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\Support\MountsAParty;
use Tests\TestCase;

/**
 * F9 de `specs/fiesta-sistema-nuevo.md` §4.15: **el zip tercero (`#780`) en la fiesta** — «¿Querías decir …?» en los
 * correos (`Field suggest`) y el primario que llega (`Button arrive`). La conducta la hace el JS de la página (su lógica,
 * `sugerirCorreo`, con sus casos en `logica.test.js`); aquí, el marcado del que depende: sin él, el JS no tiene nada que
 * encender y la página sigue igual sin que falle nada.
 */
class ZipTerceroTest extends TestCase
{
    use MountsAParty;
    use RefreshDatabase;

    public function test_an_email_field_carries_its_hidden_suggestion_tied_to_its_input(): void
    {
        $html = Blade::render('<x-pieza.campo id="correo" type="email" name="guardian_email" label="Correo" />');

        $this->assertMatchesRegularExpression('#<button type="button" class="pz-campo__sug" hidden data-sugerencia="correo">#', $html, 'escondido y atado a SU campo');
        $this->assertStringContainsString('data-sugerencia-valor', $html);
        $this->assertStringContainsString('role="status" aria-live="polite"', $html, 'se anuncia al aparecer');
        $this->assertStringContainsString('¿Querías decir ', $html);

        // CONTROL: un campo que no es de correo no lleva nada.
        $this->assertStringNotContainsString('data-sugerencia', Blade::render('<x-pieza.campo id="nombre" name="guardian_name" label="Nombre" />'));
    }

    public function test_the_primary_arrives_and_nothing_else_does(): void
    {
        $this->assertStringContainsString('data-llega', Blade::render('<x-pieza.boton>Firmar</x-pieza.boton>'), 'el primario, por defecto');
        $this->assertStringContainsString('data-llega', Blade::render('<x-pieza.boton href="/x">Ir</x-pieza.boton>'), 'también como enlace');

        foreach ([
            '<x-pieza.boton variant="secondary">Enviar</x-pieza.boton>',
            '<x-pieza.boton variant="quiet">Cerrar</x-pieza.boton>',
            '<x-pieza.boton disabled>Firmar</x-pieza.boton>',
            '<x-pieza.boton loading>Firmar</x-pieza.boton>',
            '<x-pieza.boton :llega="false">Firmar</x-pieza.boton>',
        ] as $plantilla) {
            $this->assertStringNotContainsString('data-llega', Blade::render($plantilla), $plantilla);
        }
    }

    public function test_the_authorization_page_suggests_the_email_and_its_sign_button_arrives(): void
    {
        ['reservation' => $r] = $this->mountParty();

        $html = $this->get($r->guardianAuthorizationSignedUrl())->assertOk()->getContent();

        $this->assertSame(1, preg_match('#<input id="([^"]+)" type="email" name="guardian_email"#', $html, $m), 'el correo del adulto');
        $this->assertStringContainsString('data-sugerencia="'.$m[1].'"', $html, 'su sugerencia, atada a él');
        $this->assertMatchesRegularExpression('#<button type="submit"[^>]*data-llega[^>]*data-firma-boton#', $html, '«Firmar» llega');
    }
}
