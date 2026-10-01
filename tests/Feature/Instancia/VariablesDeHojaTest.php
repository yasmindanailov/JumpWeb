<?php

namespace Tests\Feature\Instancia;

use App\Http\Instancia\VariablesDeHoja;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * **LAS VARIABLES DE UNAS HOJAS, resueltas en el servidor** (`#815`): lo que valdría cada propiedad del `:root` si el
 * navegador cargara esas hojas en ese orden. La imagen de la invitación pinta con GD los colores de la instancia, y sus
 * roles encadenan prefijos (`--fiesta-agua-500: var(--aqua-500)` → `--aqua-500: #17c8f5`).
 */
class VariablesDeHojaTest extends TestCase
{
    private string $carpeta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->carpeta = sys_get_temp_dir().'/variables-de-hoja-'.getmypid();
        File::ensureDirectoryExists($this->carpeta);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->carpeta);
        parent::tearDown();
    }

    private function hoja(string $nombre, string $css): string
    {
        File::put($this->carpeta.'/'.$nombre, $css);

        return $this->carpeta.'/'.$nombre;
    }

    public function test_a_role_follows_its_chain_across_sheets_and_the_later_sheet_wins(): void
    {
        $producto = $this->hoja('producto.css', ':root { --fiesta-agua-500: #1aa6c9; --fiesta-nieve: #fff; }');
        $marca = $this->hoja('saltia.css', "/* :root { --aqua-500: #000000; } */\n:root {\n  --aqua-500: #17C8F5;\n  --ink-900: #0b2e4a;\n}");
        $roles = $this->hoja('fiesta.css', ':root{--fiesta-agua-500:var(--aqua-500);--fiesta-tinta-900: var(--ink-900)}');

        $v = VariablesDeHoja::de([$producto, $marca, $roles]);

        $this->assertSame('#17C8F5', $v['fiesta-agua-500'], 'La cadena se sigue hasta el valor, como en el navegador.');
        $this->assertSame('#0b2e4a', $v['fiesta-tinta-900']);
        $this->assertSame('#fff', $v['fiesta-nieve'], 'Lo que la marca no redefine se queda con el neutro del producto.');
        $this->assertSame('#1aa6c9', VariablesDeHoja::de([$roles, $marca, $producto])['fiesta-agua-500'], 'En otro orden gana la última.');
    }

    /**
     * La hoja del producto declara sus neutros en `:where(:root)`, que pesa CERO: la instalación gana aunque su hoja vaya
     * antes. Medido: un lector solo de `:root` daba cero variables de la hoja del producto.
     */
    public function test_where_root_is_read_and_a_plain_root_beats_it_whatever_the_order(): void
    {
        $producto = $this->hoja('producto.css', "@import \"../isla/isla.css\";\n:where(:root) { --fiesta-agua-500: #1aa6c9; --fiesta-nieve: #ffffff; }");
        $marca = $this->hoja('marca.css', ':root { --fiesta-agua-500: #17c8f5; }');

        $this->assertSame('#1aa6c9', VariablesDeHoja::de([$producto])['fiesta-agua-500'], 'Solo el producto: sus neutros.');
        $this->assertSame('#17c8f5', VariablesDeHoja::de([$producto, $marca])['fiesta-agua-500'], 'La instalación, después: gana.');
        $this->assertSame('#17c8f5', VariablesDeHoja::de([$marca, $producto])['fiesta-agua-500'], 'Y antes también: `:where()` pesa cero.');
        $this->assertSame('#ffffff', VariablesDeHoja::de([$marca, $producto])['fiesta-nieve'], 'Lo que la instalación no redefine, el neutro.');
        $this->assertNotEmpty(VariablesDeHoja::de([resource_path('js/fiesta/fiesta.css')]), 'La hoja de verdad del producto se lee.');
    }

    public function test_media_rules_compound_selectors_and_other_at_rules_are_not_read(): void
    {
        $hoja = $this->hoja('h.css', ":root { --tinta: #101418; }\n"
            ."@media (prefers-color-scheme: dark) { :root { --tinta: #ffffff; } }\n"
            .":root:not([data-theme=\"light\"]) { --tinta: #eeeeee; }\n"
            ."[data-theme=\"dark\"] { --tinta: #dddddd; }\n"
            ."@supports (color: red) { :root { --tinta: #cccccc; } }\n"
            ."@font-face { font-family: X; src: url(x.woff2); }\n"
            ."html:root { --otra: #123456; }\n");

        $v = VariablesDeHoja::de([$hoja]);

        $this->assertSame('#101418', $v['tinta'], 'Solo el `:root` de primer nivel: ni el modo oscuro, ni un selector compuesto, ni lo de una regla `@`.');
        $this->assertArrayNotHasKey('otra', $v);
    }

    public function test_a_cycle_a_missing_variable_or_a_too_long_chain_has_no_value_and_a_fallback_is_used(): void
    {
        $largo = implode('', array_map(static fn (int $i): string => "--l{$i}: var(--l".($i + 1).');', range(0, VariablesDeHoja::VUELTAS + 1)));
        $hoja = $this->hoja('h.css', ':root { --a: var(--b); --b: var(--a); --suelta: var(--no-esta); '
            .'--con-respaldo: var(--no-esta, #abcdef); '.$largo.' --l'.(VariablesDeHoja::VUELTAS + 2).': #111111; '
            .'--corto: var(--l'.(VariablesDeHoja::VUELTAS + 1).'); }');

        $v = VariablesDeHoja::de([$hoja, $this->carpeta.'/no-existe.css']);

        $this->assertArrayNotHasKey('a', $v, 'Un ciclo se queda fuera, nunca a medias.');
        $this->assertArrayNotHasKey('suelta', $v, 'Una variable que no está y sin respaldo, fuera.');
        $this->assertSame('#abcdef', $v['con-respaldo'], 'El respaldo de `var()` cuenta.');
        $this->assertArrayNotHasKey('l0', $v, 'Más saltos que VUELTAS, fuera.');
        $this->assertSame('#111111', $v['corto'], 'La misma cadena, empezada a dos saltos del final, llega.');
    }

    public function test_hex_takes_only_a_three_or_six_digit_colour(): void
    {
        $this->assertSame('#17c8f5', VariablesDeHoja::hex('#17C8F5'));
        $this->assertSame('#aabbcc', VariablesDeHoja::hex(' #abc '));
        foreach ([null, 'rgba(0,0,0,.5)', 'color-mix(in srgb, red, blue)', 'red', '#12345', '#1234567', 'var(--x)'] as $valor) {
            $this->assertNull(VariablesDeHoja::hex($valor), (string) $valor);
        }
    }
}
