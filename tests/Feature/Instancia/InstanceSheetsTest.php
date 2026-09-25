<?php

namespace Tests\Feature\Instancia;

use App\Http\Instancia\InstanceViews;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * **El CONTRATO DE HOJAS** (`#769`): las hojas de la instancia que carga una superficie del producto, declaradas en
 * `instancia.json` y validadas contra `public/instancia/`. Lo pidió el SPA para la fiesta del sistema nuevo
 * (`fiesta-sistema-nuevo.md` §3.3·b): sus vistas son del producto, con roles neutros, y los valores de PlayJump van en
 * hojas de su paquete.
 *
 * ⚠️ El paquete de prueba vive FUERA del árbol (`SEC-12`) y las hojas en una carpeta PROPIA bajo `public/instancia/`,
 * que se borra al acabar —y `public/instancia/` también, si no existía: en otra máquina puede no estar—.
 */
class InstanceSheetsTest extends TestCase
{
    private string $paquete;

    private string $carpeta;

    private bool $publicoExistia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paquete = sys_get_temp_dir().'/instancia-hojas-'.getmypid();
        File::ensureDirectoryExists($this->paquete.'/'.InstanceViews::SUBCARPETA);

        $this->publicoExistia = is_dir(public_path(InstanceViews::PUBLICO));
        $this->carpeta = 'prueba-hojas-'.getmypid();
        File::ensureDirectoryExists(public_path(InstanceViews::PUBLICO.'/'.$this->carpeta.'/css'));
        foreach (['fuentes.css', 'saltia.css', 'fiesta.css'] as $hoja) {
            File::put(public_path(InstanceViews::PUBLICO.'/'.$this->carpeta.'/css/'.$hoja), ':root{}');
        }
        File::put(public_path(InstanceViews::PUBLICO.'/'.$this->carpeta.'/css/nota.txt'), 'no es una hoja');

        config(['instancia.ruta' => $this->paquete]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->paquete);
        File::deleteDirectory(public_path(InstanceViews::PUBLICO.'/'.$this->carpeta));
        if (! $this->publicoExistia) {
            File::deleteDirectory(public_path(InstanceViews::PUBLICO));
        }

        parent::tearDown();
    }

    /** @param  array<string, mixed>  $manifiesto */
    private function manifiesto(array $manifiesto): void
    {
        File::put($this->paquete.'/'.InstanceViews::MANIFIESTO, (string) json_encode($manifiesto));
    }

    public function test_the_declared_sheets_come_back_in_their_order_ready_for_the_page_layout(): void
    {
        $c = $this->carpeta;
        $this->manifiesto(['contrato' => InstanceViews::CONTRATO, 'hojas' => [
            'fiesta' => ["{$c}/css/fuentes.css", "{$c}/css/saltia.css", "{$c}/css/fiesta.css"],
        ]]);

        $this->assertSame(
            ["instancia/{$c}/css/fuentes.css", "instancia/{$c}/css/saltia.css", "instancia/{$c}/css/fiesta.css"],
            InstanceViews::hojas('fiesta'),
            'La ruta es la que `<x-pagina>` recibe en su prop `hojas`: bajo `public/`, en el orden declarado.',
        );
        $this->assertSame([], InstanceViews::hojas('mi-cuenta'), 'Una superficie que el paquete no declara no carga nada.');
    }

    public function test_nothing_outside_public_instancia_nor_a_missing_or_non_css_file_gets_through(): void
    {
        $c = $this->carpeta;
        // Un ENLACE que parece una hoja de la carpeta y apunta fuera de ella: solo `realpath` lo delata.
        File::put($this->paquete.'/fuera.css', ':root{}');
        symlink($this->paquete.'/fuera.css', public_path(InstanceViews::PUBLICO."/{$c}/css/enlace.css"));
        $this->manifiesto(['contrato' => InstanceViews::CONTRATO, 'hojas' => ['fiesta' => [
            "{$c}/css/saltia.css",
            "{$c}/css/enlace.css",
            "{$c}/css/../css/fiesta.css",
            "{$c}/../../../.env",
            '/etc/passwd',
            "{$c}/css/no-existe.css",
            "{$c}/css/nota.txt",
            "{$c}\\css\\fiesta.css",
            ['no', 'es', 'una', 'ruta'],
            "{$c}/css/saltia.css",
        ]]]);

        $this->assertSame(
            ["instancia/{$c}/css/saltia.css"],
            InstanceViews::hojas('fiesta'),
            'Solo una hoja `.css` que EXISTE dentro de `public/instancia/` pasa, y una vez: lo demás se queda fuera y las '
            .'válidas siguen.',
        );
    }

    public function test_without_package_key_or_a_valid_surface_name_there_are_no_sheets(): void
    {
        $this->manifiesto(['contrato' => InstanceViews::CONTRATO]);
        $this->assertSame([], InstanceViews::hojas('fiesta'), 'Un paquete sin `hojas` sigue igual: la vista, neutra.');

        $this->manifiesto(['contrato' => InstanceViews::CONTRATO, 'hojas' => ['fiesta' => 'css/saltia.css']]);
        $this->assertSame([], InstanceViews::hojas('fiesta'), 'La lista es una LISTA, no una cadena suelta.');

        $this->manifiesto(['hojas' => ['../fiesta' => ["{$this->carpeta}/css/saltia.css"]]]);
        $this->assertSame([], InstanceViews::hojas('../fiesta'), 'El nombre de la superficie es un nombre, no una ruta.');

        config(['instancia.ruta' => null]);
        $this->assertSame([], InstanceViews::hojas('fiesta'), 'Sin paquete, el producto arranca y la vista sale neutra.');
    }
}
