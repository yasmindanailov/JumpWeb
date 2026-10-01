<?php

namespace Tests\Feature\Fiesta;

use App\Http\Fiesta\CoberturaDeFuente;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * **QUÉ CARACTERES TIENE UNA FUENTE**, leído de su `cmap` (`#815`): la imagen de la invitación quita ANTES lo que la fuente no
 * tiene, porque GD no avisa (un emoji sale como basura). Se prueba con FUENTES SINTÉTICAS —los bytes mínimos que el lector
 * lee: la cabecera, el registro `cmap` y una subtabla— para que los casos raros (un carácter que apunta al `.notdef`, el
 * glifo de la tabla de glifos) se puedan escribir; y con DejaVu de la Sail, una fuente de verdad.
 */
class CoberturaDeFuenteTest extends TestCase
{
    private string $carpeta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->carpeta = sys_get_temp_dir().'/cobertura-'.getmypid();
        File::ensureDirectoryExists($this->carpeta);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->carpeta);
        parent::tearDown();
    }

    /** Una «fuente» con una sola tabla, la `cmap`, con una subtabla (plataforma, codificación, sus bytes). */
    private function fuente(string $nombre, int $plataforma, int $codificacion, string $subtabla): string
    {
        $cmap = pack('nn', 0, 1).pack('nnN', $plataforma, $codificacion, 12).$subtabla;
        $datos = pack('Nnnnn', 0x00010000, 1, 16, 0, 0).'cmap'.pack('NNN', 0, 28, strlen($cmap)).$cmap;
        File::put($ruta = $this->carpeta.'/'.$nombre, $datos);

        return $ruta;
    }

    public function test_unicode_full_groups_count_except_those_that_point_to_notdef(): void
    {
        // Formato 12: «A» apunta al glifo 0 (.notdef); «B» y «C», al 5 y al 6; y un carácter de 4 bytes (U+1F388), al 9.
        $grupos = [[0x41, 0x41, 0], [0x42, 0x43, 5], [0x1F388, 0x1F388, 9]];
        $cuerpo = '';
        foreach ($grupos as [$a, $b, $g]) {
            $cuerpo .= pack('NNN', $a, $b, $g);
        }
        $f = CoberturaDeFuente::de($this->fuente('doce.ttf', 3, 10, pack('nnNNN', 12, 0, 16 + strlen($cuerpo), 0, count($grupos)).$cuerpo));

        $this->assertFalse($f->cubre('A'), 'Un carácter que apunta al .notdef NO está.');
        $this->assertTrue($f->cubre('B'));
        $this->assertTrue($f->cubre('C'));
        $this->assertTrue($f->cubre('🎈'), 'La subtabla de Unicode completo ve los de 4 bytes.');
        $this->assertFalse($f->cubre('D'), 'Fuera de todo grupo, no está.');
    }

    public function test_basic_plane_segments_by_delta_and_by_glyph_array(): void
    {
        // Formato 4, tres tramos (ordenados por su final): «A»–«B» por la TABLA de glifos ([0, 7]: «A» es el .notdef),
        // «a»–«b» por DELTA (+1) y el de cierre, U+FFFF, que con su delta da el glifo 0.
        $fin = [0x42, 0x62, 0xFFFF];
        $ini = [0x41, 0x61, 0xFFFF];
        $delta = [0, 1, 1];
        $rango = [6, 0, 0]; // del idRangeOffset[0] a la tabla de glifos: los tres idRangeOffset, 6 bytes
        $glifos = [0, 7];
        $n = count($fin);
        $cuerpo = pack('n*', ...$fin).pack('n', 0).pack('n*', ...$ini).pack('n*', ...$delta).pack('n*', ...$rango).pack('n*', ...$glifos);
        $f = CoberturaDeFuente::de($this->fuente('cuatro.ttf', 3, 1, pack('nnnnnnn', 4, 14 + strlen($cuerpo), 0, 2 * $n, 0, 0, 0).$cuerpo));

        $this->assertFalse($f->cubre('A'), 'La tabla de glifos dice 0: el .notdef, NO está.');
        $this->assertTrue($f->cubre('B'), 'La tabla de glifos dice 7.');
        $this->assertTrue($f->cubre('a'), 'Por delta: 0x61 + 1.');
        $this->assertTrue($f->cubre('b'));
        $this->assertFalse($f->cubre('c'), 'Entre tramos, no está.');
        $this->assertFalse($f->cubre("\u{FFFF}"), 'El tramo de cierre da el glifo 0.');
        $this->assertFalse($f->cubre('🎈'), 'Sin la subtabla de Unicode completo, un carácter de 4 bytes no está.');
    }

    public function test_a_real_font_and_a_file_that_is_not_one(): void
    {
        $dejavu = CoberturaDeFuente::de('/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf');
        foreach (['a', 'ñ', 'Á', '·', '7'] as $sí) {
            $this->assertTrue($dejavu->cubre($sí), $sí);
        }
        foreach (['🎈', '一'] as $no) {
            $this->assertFalse($dejavu->cubre($no), $no);
        }

        File::put($falsa = $this->carpeta.'/falsa.ttf', 'no es una fuente');
        $this->assertFalse(CoberturaDeFuente::de($falsa)->cubre('a'), 'Una fuente que no se lee no cubre nada: sin texto se ve; con cajas, no.');
        $this->assertFalse(CoberturaDeFuente::de($this->carpeta.'/no-existe.ttf')->cubre('a'));
    }
}
