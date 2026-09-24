<?php

namespace Tests\Feature\Isla;

use Illuminate\Support\Arr;
use Tests\TestCase;

/**
 * **Los textos de la isla existen, en los tres idiomas y con sus marcadores** (`isla-y-landing-nueva.md` §4.9).
 *
 * La isla lee sus textos con `t()`/`tp()` (`resources/js/sidebar/i18n.js`), que devuelven cadena VACÍA cuando la
 * clave no existe: un texto que falta no rompe nada, solo pinta un hueco. Eso es lo que esta guarda impide, como
 * `SidebarTranslationKeysExistTest` hace con el cajón. Y como las horas llegan del horario (`:hora`), una
 * traducción que perdiera el marcador diría «Abrimos mañana a las .» sin que nada fallara.
 */
class IslaTextosTest extends TestCase
{
    private const IDIOMAS = ['es', 'en', 'fr'];

    /** @return array<string, string> clave con puntos => texto */
    private function grupo(string $idioma): array
    {
        return Arr::dot(require lang_path("{$idioma}/isla.php"));
    }

    public function test_the_three_languages_have_exactly_the_same_keys(): void
    {
        $es = array_keys($this->grupo('es'));
        sort($es);

        foreach (['en', 'fr'] as $idioma) {
            $otras = array_keys($this->grupo($idioma));
            sort($otras);
            $this->assertSame($es, $otras, "El grupo `isla` de «{$idioma}» no tiene las mismas claves que el español.");
        }
    }

    public function test_every_key_the_island_asks_for_exists(): void
    {
        $textos = $this->grupo('es');
        $pedidas = [];
        foreach (glob(resource_path('js/isla/{,*/}*.{js,vue}'), GLOB_BRACE) ?: [] as $fichero) {
            if (str_ends_with($fichero, '.test.js')) {
                continue;
            }
            preg_match_all("/\\bt[p]?\\(\\s*(?:m\\s*,\\s*)?'([a-z_]+(?:\\.[a-z_]+)+)'/", (string) file_get_contents($fichero), $m);
            foreach ($m[1] as $clave) {
                $pedidas[$clave][] = basename($fichero);
            }
        }

        $this->assertNotEmpty($pedidas, 'No se encontró ninguna llamada a t()/tp(): el patrón de búsqueda ya no casa con el código.');
        foreach ($pedidas as $clave => $donde) {
            $this->assertArrayHasKey($clave, $textos, "«isla.{$clave}» la pide ".implode(', ', array_unique($donde)).' y no existe: la isla pintaría un hueco.');
        }
    }

    public function test_a_placeholder_in_spanish_is_in_every_translation(): void
    {
        foreach ($this->grupo('es') as $clave => $texto) {
            preg_match_all('/:([a-z_]+)/', $texto, $m);
            foreach (['en', 'fr'] as $idioma) {
                $traduccion = $this->grupo($idioma)[$clave];
                foreach ($m[1] as $marcador) {
                    $this->assertStringContainsString(":{$marcador}", $traduccion, "«isla.{$clave}» en «{$idioma}» perdió `:{$marcador}`.");
                }
            }
        }
    }
}
