<?php

namespace Tests\Feature\Fiesta;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\DataProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * LA GUARDA DE CLAVES de los textos de la fiesta (`specs/fiesta-sistema-nuevo.md` §4.2, T4): lo que lee un
 * desconocido vive en los TRES idiomas del cliente con las MISMAS claves (`fiesta.php`, y lo que queda de
 * `guardian.php`, `invitation.php` y `guestform.php`), y en esos tres ficheros viejos NO queda texto muerto: cada
 * clave la lee alguien del producto (app, vistas, rutas, config). Nació de la T4, que retiró 160 claves que solo
 * pintaba la piel vieja: sin esta guarda volverían a acumularse sin que nada fallara.
 *
 * ⚠️ La búsqueda es ESTÁTICA: una clave se da por usada si su nombre con punto aparece entre comillas, o si un
 *    prefijo suyo aparece concatenado (`'guestform.count_error_'.$motivo`). `fiesta.php` no entra en ese censo: sus
 *    claves viajan enteras al JS de la página (`data-textos`) y allí se leen por ruta (`t('guardar.cambios')`).
 * ⚠️ Los tests NO cuentan como uso: una clave que solo nombra un test es texto muerto que el test mantiene vivo.
 */
class ClavesDeIdiomaTest extends TestCase
{
    private const IDIOMAS = ['es', 'en', 'fr'];

    private const CARPETAS = ['app', 'resources', 'routes', 'config'];

    /** @return array<string, array{0: string}> */
    public static function ficheros(): array
    {
        return ['fiesta' => ['fiesta'], 'guardian' => ['guardian'], 'invitation' => ['invitation'], 'guestform' => ['guestform']];
    }

    #[DataProvider('ficheros')]
    public function test_the_three_languages_have_the_same_keys(string $fichero): void
    {
        $es = array_keys($this->claves($fichero, 'es'));
        $this->assertNotSame([], $es, "lang/es/{$fichero}.php no tiene claves");

        foreach (['en', 'fr'] as $idioma) {
            $otras = array_keys($this->claves($fichero, $idioma));
            $this->assertSame([], array_values(array_diff($es, $otras)), "claves de es que faltan en {$idioma}/{$fichero}.php");
            $this->assertSame([], array_values(array_diff($otras, $es)), "claves de {$idioma}/{$fichero}.php que no existen en es");
        }
    }

    /** @return array<string, array{0: string}> */
    public static function ficherosViejos(): array
    {
        return ['guardian' => ['guardian'], 'invitation' => ['invitation'], 'guestform' => ['guestform']];
    }

    #[DataProvider('ficherosViejos')]
    public function test_every_key_of_an_old_file_is_still_read_by_the_product(string $fichero): void
    {
        $codigo = $this->codigo();
        preg_match_all('/[\'"]('.preg_quote($fichero, '/').'\.[a-z0-9_.]*)[\'"]\s*\./', $codigo, $m);
        $prefijos = array_unique($m[1]);

        $muertas = [];
        foreach (array_keys($this->claves($fichero, 'es')) as $clave) {
            $completa = $fichero.'.'.$clave;
            $usada = str_contains($codigo, "'".$completa."'") || str_contains($codigo, '"'.$completa.'"');
            foreach ($prefijos as $prefijo) {
                $usada = $usada || str_starts_with($completa, $prefijo);
            }
            if (! $usada) {
                $muertas[] = $clave;
            }
        }

        $this->assertSame([], $muertas, "claves de lang/*/{$fichero}.php que ya no lee nadie del producto (texto muerto):\n - ".implode("\n - ", $muertas));
    }

    /** @return array<string, true> las claves con punto, de hoja (un arreglo con claves numéricas es una hoja: `leyenda`). */
    private function claves(string $fichero, string $idioma): array
    {
        $ruta = lang_path($idioma.'/'.$fichero.'.php');
        $this->assertFileExists($ruta);

        return $this->aplana(require $ruta);
    }

    /**
     * @param  array<int|string, mixed>  $a
     * @return array<string, true>
     */
    private function aplana(array $a, string $prefijo = ''): array
    {
        $out = [];
        foreach ($a as $k => $v) {
            $clave = $prefijo === '' ? (string) $k : $prefijo.'.'.$k;
            if (is_array($v) && $v !== [] && ! array_is_list($v)) {
                $out += $this->aplana($v, $clave);
            } else {
                $out[$clave] = true;
            }
        }

        return $out;
    }

    /** Todo el código del producto de una vez, sin los ficheros de idioma. */
    private function codigo(): string
    {
        static $codigo = null;
        if ($codigo !== null) {
            return $codigo;
        }
        $codigo = '';
        foreach (self::CARPETAS as $carpeta) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($carpeta), FilesystemIterator::SKIP_DOTS));
            /** @var \SplFileInfo $f */
            foreach ($it as $f) {
                $ruta = $f->getPathname();
                if (preg_match('/\.(php|js|vue|json)$/', $ruta) === 1 && ! str_contains($ruta, '/lang/')) {
                    $codigo .= "\n".file_get_contents($ruta);
                }
            }
        }

        return $codigo;
    }
}
