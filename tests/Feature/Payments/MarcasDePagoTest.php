<?php

namespace Tests\Feature\Payments;

use App\Domain\Identity\Models\User;
use App\Domain\Payments\Services\MarcasDePago;
use App\Domain\Platform\Models\Setting;
use App\Filament\Pages\Settings;
use Database\Seeders\DatabaseSeeder;
use Livewire\Livewire;
use Tests\Feature\Api\ApiTestCase;

/**
 * **Las formas de pago con sus logotipos OFICIALES** (`DECISIONES #784`, `isla-y-landing-nueva.md` §4.14).
 *
 * Qué acepta un parque es dato de su negocio: se marca en el panel y viaja en el arranque de la compra como
 * `urls.mark_<id>`, en su orden, solo si hay venta online y el producto trae el fichero oficial de esa marca. Y el
 * fichero es el de su dueño: la huella de su dibujo está aquí para que un «retoque» que se ve igual se ponga en rojo.
 */
class MarcasDePagoTest extends ApiTestCase
{
    /**
     * La huella del DIBUJO oficial de cada marca: el sha1 de los `d` de sus trazados fuera de los `<clipPath>` (ésos son
     * el marco del recorte, no la marca), en el orden del fichero, tomada del ORIGINAL de su dueño —Bizum,
     * `Bizum_Logotipo_VP_RGB_Azul.ai` de su portal; Visa, `VBM_bluRGB_2025.svg` de su paquete digital 2025— y con el color
     * de su guía. Si una marca cambia, quien la traiga cambia esto A SABIENDAS y deja dicho en la cabecera del fichero de
     * dónde la sacó (la doctrina de `GoogleButtonBrandingTest`).
     */
    private const OFICIALES = [
        'bizum' => ['dibujo' => '71b05c8f536f7a78b4f65e442166b85c16e2b00c', 'color' => '#05c0c7', 'original' => '1b252dde31f3de4b4f1b8dcb6e8a01c4ad541876492f1e506fc376f3fdc75705'],
        'visa' => ['dibujo' => '5a493fe9e8e55913a54f281888a92d3775e73509', 'color' => '#1434cb', 'original' => '733ef827287b926d3bd37907787859f810ef44ad9fc09f0252661d58c44e22c1'],
    ];

    private function elegir(string $valor): void
    {
        Setting::query()->updateOrCreate(['key' => MarcasDePago::KEY], ['value' => $valor, 'group' => 'payment']);
    }

    public function test_without_a_row_no_mark_is_shown_nor_travels(): void
    {
        $this->assertSame([], MarcasDePago::elegidas(), 'qué acepta un parque no se da por supuesto');
        $this->assertSame([], MarcasDePago::activas());

        $urls = $this->getJson(self::ROOT.'/sidebar/boot?lang=es')->assertOk()->assertValidResponse(200)->json('urls');
        $this->assertSame([], array_filter(array_keys($urls), fn (string $k): bool => str_starts_with($k, 'mark_')));
    }

    public function test_the_chosen_marks_travel_in_the_product_order_and_only_with_their_official_file(): void
    {
        // Desordenadas, con una que el producto no conoce y espacios: se leen en el orden del diseño y sin la extraña.
        $this->elegir(' Visa, bizum,paypal ');
        $this->assertSame(['bizum', 'visa'], MarcasDePago::elegidas());

        // Solo viajan las que tienen su fichero: la que no, no se pinta rota, no se pinta.
        $conFichero = array_values(array_filter(['bizum', 'visa'], MarcasDePago::tieneFichero(...)));
        $this->assertContains('bizum', $conFichero, 'el logotipo oficial de Bizum viaja con el producto');
        $this->assertSame($conFichero, array_column(MarcasDePago::activas(), 'id'));

        $urls = $this->getJson(self::ROOT.'/sidebar/boot?lang=es')->assertOk()->assertValidResponse(200)->json('urls');
        $marcas = array_filter($urls, fn (string $k): bool => str_starts_with($k, 'mark_'), ARRAY_FILTER_USE_KEY);
        $this->assertSame(array_map(fn (string $id): string => "mark_{$id}", $conFichero), array_keys($marcas), 'en su orden');
        $this->assertSame(asset('images/providers/pago/bizum.svg'), $marcas['mark_bizum']);
    }

    public function test_with_online_sales_closed_no_mark_is_shown(): void
    {
        $this->elegir('bizum');
        $this->assertSame(['bizum'], array_column(MarcasDePago::activas(), 'id'));

        Setting::query()->updateOrCreate(['key' => 'sales.online_enabled'], ['value' => '0', 'group' => 'sales']);
        $this->assertSame([], MarcasDePago::activas(), 'sin venta online no hay dónde pagar');
    }

    public function test_the_panel_saves_the_list_as_the_text_the_service_reads_and_hydrates_it_back(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@jumpweb.test')->firstOrFail();

        Livewire::actingAs($admin)->test(Settings::class)
            ->fillForm([MarcasDePago::KEY => ['bizum']])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('bizum', Setting::value(MarcasDePago::KEY));

        Livewire::actingAs($admin)->test(Settings::class)
            ->assertSchemaStateSet([MarcasDePago::KEY => ['bizum']])
            ->fillForm([MarcasDePago::KEY => []])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('', Setting::value(MarcasDePago::KEY), 'ninguna marcada: ninguna');
        $this->assertSame([], MarcasDePago::elegidas());
    }

    public function test_each_product_file_is_the_official_drawing_in_its_colour_and_says_where_it_came_from(): void
    {
        // Guarda de la guarda: el producto no trae un fichero de marca sin su huella aquí.
        $ficheros = array_map(fn (string $f): string => basename($f, '.svg'), glob(public_path('images/providers/pago/*.svg')) ?: []);
        sort($ficheros);
        $conocidos = array_keys(self::OFICIALES);
        sort($conocidos);
        $this->assertSame($conocidos, $ficheros);

        foreach (self::OFICIALES as $id => $oficial) {
            $svg = (string) file_get_contents(public_path("images/providers/pago/{$id}.svg"));
            $this->assertSame(1, preg_match_all('/\sd="([^"]+)"/', (string) preg_replace('#<clipPath\b.*?</clipPath>#s', '', $svg), $d), "{$id}: un trazado, y se lee");

            $this->assertSame($oficial['dibujo'], sha1(implode('', $d[1])), "{$id}: el dibujo es el suyo, sin retocar");
            preg_match_all('/fill[=:]\s*"?(#[0-9a-fA-F]{6})/', $svg, $f);
            $this->assertSame([$oficial['color']], array_values(array_unique(array_map('strtolower', $f[1]))), "{$id}: en su color, y solo en él");
            $this->assertStringContainsString($oficial['original'], $svg, "{$id}: dice la huella del original");
            $this->assertStringContainsString('Origen:', $svg, "{$id}: y de dónde salió");
        }
    }
}
