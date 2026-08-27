<?php

namespace Tests\Feature\Theme;

use App\Domain\Content\Services\ThemeFonts;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **LA TIPOGRAFÍA ES DE LA INSTALACIÓN; EL ORIGEN, DEL PRODUCTO**
 * (`docs/specs/tema-por-instalacion.md` §4.6).
 *
 * Hasta esta tanda la lista de familias estaba escrita a mano en TRES layouts, así que una
 * instalación podía redefinir `--font-display` desde su `client.css` y **el fichero no llegaba a
 * descargarse**: el token cambiaba y el navegador caía al `system-ui` del stack. El tema de
 * tipografía era un token sin fuente detrás.
 *
 * ⚠️⚠️ **Lo que más vigila este fichero no es que funcione: es que el HOST no se pueda mover.**
 * `SecurityHeaders` permite un único origen de fuentes. Apuntar a otro **no da error**: la CSP lo
 * bloquea en silencio y la instalación se queda sin tipografía sin que nada falle. Por eso el host
 * vive en una constante y no en config, y por eso las familias pasan por allowlist — acaban dentro
 * de una URL en el `<head>`, que es un sink de inyección (`SEC-07`).
 */
class ThemeFontsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * **El origen es el que la CSP permite, y solo ese.**
     *
     * Si alguien cambia uno de los dos sin el otro, esto cae — que es exactamente el aviso que
     * hoy no existiría, porque el síntoma es «no se ven las fuentes» y no un error.
     */
    public function test_the_font_origin_is_the_one_the_csp_allows(): void
    {
        $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString(
            ThemeFonts::HOST, $csp,
            'el host de fuentes de `ThemeFonts` no está en la CSP: el navegador bloquearía la hoja '.
            'de fuentes EN SILENCIO y la instalación se quedaría con `system-ui`.',
        );

        $this->assertStringStartsWith(ThemeFonts::HOST.'/css?family=', ThemeFonts::stylesheetUrl());
    }

    /** **Sin configurar nada, se sirve la lista del producto.** */
    public function test_without_configuration_the_product_list_is_served(): void
    {
        config()->set('theme.fonts', null);

        $this->assertSame(ThemeFonts::DEFAULT_FAMILIES, ThemeFonts::families());
    }

    /** **Una instalación declara la suya y se sirve tal cual.** */
    public function test_an_installation_can_declare_its_own_families(): void
    {
        $suya = 'bungee:400|hanken-grotesk:400,500,700,800|lilita-one:400';
        config()->set('theme.fonts', $suya);

        $this->assertSame($suya, ThemeFonts::families());
        $this->assertStringContainsString($suya, ThemeFonts::stylesheetUrl());
    }

    /**
     * **Un valor inválido cae al del producto — y la lista ENTERA, no solo la parte mala.**
     *
     * Servir media tipografía es peor que servir la del producto: la página se pinta con familias
     * que nadie eligió y el operador no tiene forma de notar cuál falta.
     *
     * ⚠️ Los tres primeros casos son **inyección**: si alguno se colara, saldría dentro de un
     * `href` en el `<head>`.
     */
    public function test_an_invalid_value_falls_back_to_the_product_list(): void
    {
        foreach ([
            'comillas' => 'bungee:400"><script>alert(1)</script>',
            'otro host' => 'bungee:400|//evil.tld/x:400',
            'ruta' => '../../etc/passwd:400',
            'peso inventado' => 'bungee:450',
            'sin peso' => 'bungee',
            'mayúsculas' => 'Bungee:400',
            'espacio' => 'hanken grotesk:400',
            'una sola mala entre buenas' => 'bungee:400|hanken grotesk:400|lilita-one:400',
            'lista absurda' => implode('|', array_fill(0, 20, 'bungee:400')),
        ] as $motivo => $valor) {
            config()->set('theme.fonts', $valor);

            $this->assertSame(
                ThemeFonts::DEFAULT_FAMILIES, ThemeFonts::families(),
                "«{$motivo}» no se ha rechazado: acabaría dentro de un `href` en el `<head>`.",
            );
        }
    }

    /**
     * **Las tres portadas sirven la MISMA hoja, y ninguna lleva la lista escrita a mano.**
     *
     * Es la mitad que impide que esto se deshaga solo: basta con que alguien copie el `<link>`
     * viejo en un layout para que esa página deje de obedecer a la instalación, y no fallaría nada.
     */
    public function test_no_layout_hardcodes_the_family_list(): void
    {
        foreach ([
            'resources/views/components/layout.blade.php',
            'resources/views/components/focused-layout.blade.php',
            'resources/views/errors/maintenance.blade.php',
        ] as $vista) {
            $blade = (string) file_get_contents(base_path($vista));

            $this->assertStringNotContainsString(
                '/css?family=', $blade,
                "`{$vista}` lleva la lista de familias escrita a mano: esa página dejaría de obedecer ".
                'a la instalación y nada fallaría.',
            );
            $this->assertStringContainsString('ThemeFonts::stylesheetUrl()', $blade);
        }
    }

    /** **Y la home sirve de verdad la lista configurada**, no solo el servicio en aislamiento. */
    public function test_the_home_serves_the_configured_families(): void
    {
        $this->seed(LandingContentSeeder::class);
        config()->set('theme.fonts', 'bungee:400|lilita-one:400');

        $this->get('/')
            ->assertOk()
            ->assertSee(ThemeFonts::HOST.'/css?family=bungee:400|lilita-one:400', false);
    }
}
