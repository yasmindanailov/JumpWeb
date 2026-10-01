<?php

namespace Tests\Feature\Instancia;

use App\Http\Instancia\InstanceViews;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * **LAS FUENTES DE UN USO DEL SERVIDOR** (`#815`): los TTF fijos con los que el producto DIBUJA la imagen de la invitación,
 * declarados por la instancia con su rol en `instancia.json` (`fuentes.imagen`) y validados contra `public/instancia/` con
 * las mismas puertas que el contrato de hojas (`InstanceSheetsTest`).
 *
 * ⚠️ El paquete de prueba vive FUERA del árbol (`SEC-12`) y las fuentes en una carpeta PROPIA bajo `public/instancia/`, que
 * se borra al acabar —y `public/instancia/` también, si no existía—.
 */
class InstanceFontsTest extends TestCase
{
    private string $paquete;

    private string $carpeta;

    private bool $publicoExistia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paquete = sys_get_temp_dir().'/instancia-fuentes-'.getmypid();
        File::ensureDirectoryExists($this->paquete.'/'.InstanceViews::SUBCARPETA);

        $this->publicoExistia = is_dir(public_path(InstanceViews::PUBLICO));
        $this->carpeta = 'prueba-fuentes-'.getmypid();
        File::ensureDirectoryExists(public_path(InstanceViews::PUBLICO.'/'.$this->carpeta.'/fuentes'));
        foreach (['titular.ttf', 'texto.otf', 'etiqueta.ttf'] as $fuente) {
            File::put(public_path(InstanceViews::PUBLICO.'/'.$this->carpeta.'/fuentes/'.$fuente), 'no hace falta que sea una fuente');
        }
        File::put(public_path(InstanceViews::PUBLICO.'/'.$this->carpeta.'/fuentes/web.woff2'), 'una woff2 no es de este uso');

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

    public function test_the_declared_fonts_come_back_by_their_role_as_absolute_paths_for_the_server(): void
    {
        $c = $this->carpeta;
        $this->manifiesto(['contrato' => InstanceViews::CONTRATO, 'fuentes' => ['imagen' => [
            'titular' => "{$c}/fuentes/titular.ttf", 'texto' => "{$c}/fuentes/texto.otf", 'etiqueta' => "{$c}/fuentes/etiqueta.ttf",
        ]]]);

        $base = (string) realpath(public_path(InstanceViews::PUBLICO));
        $this->assertSame(
            ['titular' => "{$base}/{$c}/fuentes/titular.ttf", 'texto' => "{$base}/{$c}/fuentes/texto.otf", 'etiqueta' => "{$base}/{$c}/fuentes/etiqueta.ttf"],
            InstanceViews::fuentes('imagen'),
            'Las lee el SERVIDOR (GD): rutas absolutas y reales, por su rol.',
        );
        $this->assertSame([], InstanceViews::fuentes('otra-cosa'), 'Un uso que el paquete no declara no trae nada.');
    }

    public function test_nothing_outside_public_instancia_nor_a_missing_or_web_font_gets_through(): void
    {
        $c = $this->carpeta;
        // Un ENLACE que parece una fuente de la carpeta y apunta fuera de ella: solo `realpath` lo delata.
        File::put($this->paquete.'/fuera.ttf', 'fuera');
        symlink($this->paquete.'/fuera.ttf', public_path(InstanceViews::PUBLICO."/{$c}/fuentes/enlace.ttf"));
        $this->manifiesto(['contrato' => InstanceViews::CONTRATO, 'fuentes' => ['imagen' => [
            'titular' => "{$c}/fuentes/titular.ttf",
            'enlace' => "{$c}/fuentes/enlace.ttf",
            'vuelta' => "{$c}/fuentes/../fuentes/texto.otf",
            'fuera' => "{$c}/../../../.env",
            'absoluta' => '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            'falta' => "{$c}/fuentes/no-existe.ttf",
            'web' => "{$c}/fuentes/web.woff2",
            'Rol Raro' => "{$c}/fuentes/etiqueta.ttf",
            'lista' => ['no', 'es', 'una', 'ruta'],
        ]]]);

        $base = (string) realpath(public_path(InstanceViews::PUBLICO));
        $this->assertSame(
            ['titular' => "{$base}/{$c}/fuentes/titular.ttf"],
            InstanceViews::fuentes('imagen'),
            'Solo un `.ttf`/`.otf` que EXISTE dentro de `public/instancia/`, con un rol que es un nombre, pasa: lo demás se queda '
            .'fuera y lo válido sigue.',
        );
    }

    public function test_without_package_key_or_a_valid_use_name_there_are_no_fonts(): void
    {
        $this->manifiesto(['contrato' => InstanceViews::CONTRATO]);
        $this->assertSame([], InstanceViews::fuentes('imagen'), 'Un paquete sin `fuentes` sigue igual: la imagen de antes.');

        $this->manifiesto(['contrato' => InstanceViews::CONTRATO, 'fuentes' => ['imagen' => "{$this->carpeta}/fuentes/titular.ttf"]]);
        $this->assertSame([], InstanceViews::fuentes('imagen'), 'Las fuentes van por ROL, no una ruta suelta.');

        $this->manifiesto(['fuentes' => ['../imagen' => ['titular' => "{$this->carpeta}/fuentes/titular.ttf"]]]);
        $this->assertSame([], InstanceViews::fuentes('../imagen'), 'El nombre del uso es un nombre, no una ruta.');

        config(['instancia.ruta' => null]);
        $this->assertSame([], InstanceViews::fuentes('imagen'), 'Sin paquete, el producto arranca sin fuentes.');
    }
}
