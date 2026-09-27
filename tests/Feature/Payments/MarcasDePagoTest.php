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
     * La huella del DIBUJO oficial de cada fichero: el sha1 de los `d` de sus trazados fuera de los `<clipPath>` (ésos son
     * el marco del recorte, no la marca), en el orden del fichero, tomada del ORIGINAL de su dueño —Bizum, sus `.ai` de su
     * portal (azul y negativo); Visa, `VBM_bluRGB_2025.svg` y `VBM_whtRGB_2025.svg` de su paquete digital 2025;
     * Mastercard, `ma_symbol.svg` de su Brand Center— con los colores de su guía. La versión para fondo oscuro
     * (`-tinta`, `#786`) es el MISMO dibujo que la clara, en otro color. Si una marca cambia, quien la traiga cambia esto A
     * SABIENDAS y deja dicho en la cabecera del fichero de dónde la sacó (la doctrina de `GoogleButtonBrandingTest`).
     */
    private const OFICIALES = [
        'bizum' => ['dibujo' => '71b05c8f536f7a78b4f65e442166b85c16e2b00c', 'trazos' => 1, 'colores' => ['#05c0c7'], 'original' => '1b252dde31f3de4b4f1b8dcb6e8a01c4ad541876492f1e506fc376f3fdc75705'],
        'bizum-tinta' => ['dibujo' => '71b05c8f536f7a78b4f65e442166b85c16e2b00c', 'trazos' => 1, 'colores' => ['#ffffff'], 'original' => 'b53ce954b4a40f6e4c0d910dd1888c522b0e69b4fe49df9b065939a5aa195d5f'],
        'visa' => ['dibujo' => '5a493fe9e8e55913a54f281888a92d3775e73509', 'trazos' => 1, 'colores' => ['#1434cb'], 'original' => '733ef827287b926d3bd37907787859f810ef44ad9fc09f0252661d58c44e22c1'],
        'visa-tinta' => ['dibujo' => '5a493fe9e8e55913a54f281888a92d3775e73509', 'trazos' => 1, 'colores' => ['#fff'], 'original' => 'c06c40f5cf35db969561ecf666a1fb6cca23dd32b828b4eab1270eed62bb516b'],
        'mastercard' => ['dibujo' => '16c315dbe0f3676a01a75260133d60a0115a88a1', 'trazos' => 2, 'colores' => ['#ff5f00', '#eb001b', '#f79e1b'], 'original' => '05df092590d7d56dd1531f25c8830ba2507d3d3aa2d19ad1649a59ce5a1a16f8'],
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
        // Las claves de cada marca (`mark_<id>`; su versión oscura, `mark_<id>_ink`, en la prueba de abajo).
        $marcas = array_filter($urls, fn (string $k): bool => preg_match('/^mark_[a-z]+$/', $k) === 1, ARRAY_FILTER_USE_KEY);
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
            $this->assertSame($oficial['trazos'], preg_match_all('/\sd="([^"]+)"/', (string) preg_replace('#<clipPath\b.*?</clipPath>#s', '', $svg), $d), "{$id}: sus trazados, y se leen");

            $this->assertSame($oficial['dibujo'], sha1(implode('', $d[1])), "{$id}: el dibujo es el suyo, sin retocar");
            preg_match_all('/fill[=:]\s*"?(#[0-9a-fA-F]{3,6})\b/', $svg, $f);
            $this->assertSame($oficial['colores'], array_values(array_unique(array_map('strtolower', $f[1]))), "{$id}: en sus colores, y solo en ellos");
            $this->assertStringContainsString($oficial['original'], $svg, "{$id}: dice la huella del original");
            $this->assertStringContainsString('Origen:', $svg, "{$id}: y de dónde salió");
        }
    }

    /**
     * **Cada marca, con su versión para fondo OSCURO** (`#786`: dentro de la isla, `data-surface="ink"`, va la oficial para
     * él y no una chapa blanca): `srcTinta` es su `-tinta.svg` si el producto lo trae, y si no la misma (el símbolo de
     * Mastercard, que su guía da por bueno sobre oscuro). Viaja en el arranque (`mark_<id>_ink`) y en el HECHO del sitio
     * (`payment_marks`, 1.41.0), que es de donde el pie de una landing las pinta.
     */
    public function test_each_mark_travels_with_its_dark_version_in_the_boot_and_as_a_site_fact(): void
    {
        $this->elegir('mastercard,visa,bizum');
        $activas = MarcasDePago::activas();
        $this->assertSame(['bizum', 'visa', 'mastercard'], array_column($activas, 'id'), 'en el orden del producto');
        $this->assertSame(asset('images/providers/pago/bizum-tinta.svg'), $activas[0]['srcTinta']);
        $this->assertSame(asset('images/providers/pago/mastercard.svg'), $activas[2]['srcTinta'], 'sin su versión oscura, la misma');

        $urls = $this->getJson(self::ROOT.'/sidebar/boot?lang=es')->assertOk()->assertValidResponse(200)->json('urls');
        $this->assertSame(asset('images/providers/pago/visa-tinta.svg'), $urls['mark_visa_ink'] ?? null);

        $hechos = $this->getJson(self::ROOT.'/site')->assertOk()->assertValidResponse(200)->json('payment_marks');
        $this->assertSame([
            ['id' => 'bizum', 'name' => 'Bizum', 'src' => asset('images/providers/pago/bizum.svg'), 'src_ink' => asset('images/providers/pago/bizum-tinta.svg')],
            ['id' => 'visa', 'name' => 'Visa', 'src' => asset('images/providers/pago/visa.svg'), 'src_ink' => asset('images/providers/pago/visa-tinta.svg')],
            ['id' => 'mastercard', 'name' => 'Mastercard', 'src' => asset('images/providers/pago/mastercard.svg'), 'src_ink' => asset('images/providers/pago/mastercard.svg')],
        ], $hechos);

        Setting::query()->where('key', MarcasDePago::KEY)->delete();
        Setting::flushMemo();
        $this->assertSame([], $this->getJson(self::ROOT.'/site')->assertOk()->assertValidResponse(200)->json('payment_marks'), 'sin ninguna, una lista vacía: la clave va siempre');
    }
}
