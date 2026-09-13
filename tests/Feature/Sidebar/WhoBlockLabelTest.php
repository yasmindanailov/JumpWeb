<?php

namespace Tests\Feature\Sidebar;

use Tests\TestCase;

/**
 * **El rótulo de «¿Quiénes vienen?»: que exista en los tres idiomas y que el paso de hora le dé al
 * módulo lo que necesita para elegirlo** (`#567`).
 *
 * `assignment.js::whoSummaryKey()` devuelve la CLAVE y el componente la traduce, así que ninguna
 * guarda que lea `t('…')` literales la ve: `SidebarTranslationKeysExistTest` deja fuera, a propósito,
 * las claves computadas. Con los rótulos cortos entró un quinto (`guardian_only`), y una clave que
 * falte en un idioma no rompe nada — `t()` pinta cadena vacía y la cabecera se queda con el título
 * solo, en ese idioma y en ninguno más.
 *
 * ⚠️ Las claves se sacan del MÓDULO, no de una lista escrita aquí: una lista a mano se queda atrás en
 * silencio el día que alguien añade un sexto rótulo. El grupo `tickets` viaja entero al cajón
 * (`layout.blade.php`), así que existir en `lang/` es llegar.
 * ⚠️ `has(…, $locale, false)`: sin el tercer parámetro cae al idioma de respaldo y una clave que
 * falte en francés pasaría en verde (la trampa de `#506`).
 */
class WhoBlockLabelTest extends TestCase
{
    private const MODULE = 'resources/js/sidebar/assignment.js';

    private const STEP = 'resources/js/sidebar/steps/TimeStep.vue';

    /** @return list<string> */
    private function keys(): array
    {
        $source = (string) file_get_contents(base_path(self::MODULE));
        preg_match_all("/'(who_block\\.[a-z_]+)'/", $source, $matches);

        return array_values(array_unique($matches[1]));
    }

    public function test_every_label_the_module_can_return_exists_in_every_locale(): void
    {
        $missing = [];

        foreach ($this->keys() as $key) {
            foreach (['es', 'en', 'fr'] as $locale) {
                if (! app('translator')->has("tickets.{$key}", $locale, false)) {
                    $missing[] = "{$locale}: tickets.{$key}";
                }
            }
        }

        $this->assertSame([], $missing,
            "Rótulos de «¿Quiénes vienen?» sin traducir — la cabecera saldría con el título solo:\n".implode("\n", $missing));
    }

    /**
     * CONTROL: sin él, un localizador que no encontrara nada dejaría el caso de arriba en verde sobre
     * un módulo que ya no devuelve ninguna clave. Y la lista exacta obliga a pasar por aquí al añadir
     * un rótulo, que es cuando hay que acordarse de los tres idiomas.
     */
    public function test_the_scanner_finds_the_five_labels(): void
    {
        $this->assertEqualsCanonicalizing(
            ['who_block.none', 'who_block.some', 'who_block.guardian', 'who_block.both', 'who_block.guardian_only'],
            $this->keys(),
        );
    }

    /**
     * ⚠️⚠️ **Y el paso de hora tiene que DECIRLE al módulo si hay menores que ofrecer.** Sin `offers`,
     * `whoSummaryKey()` cae a su valor por defecto (`true`) y el bloque que trae SOLO el justificante
     * vuelve a decir «Menores a cargo» — sin que falle nada: el arnés de `#567` quitó el argumento y
     * la regla, sus casos de `node --test` y los textos siguieron en verde. *Un módulo probado no dice
     * nada sobre si alguien le pasa el dato.*
     *
     * Se vigila también que la condición sea UNA con sus tres lectores —abrir el bloque, pintar el
     * selector, elegir el rótulo—: escrita tres veces, el rótulo puede prometer menores que el bloque no
     * enseña.
     */
    public function test_the_time_step_tells_the_module_whether_it_offers_dependents(): void
    {
        $step = (string) file_get_contents(base_path(self::STEP));

        $this->assertMatchesRegularExpression(
            '/const offersDependents = computed\(\(\) => [^;]*props\.isPack[^;]*props\.dependentOptions\.length[^;]*\);/', $step,
            'La condición «hay menores que ofrecer» ya no es un solo `computed` sobre el pack y la lista de menores.');
        $this->assertMatchesRegularExpression(
            '/whoSummaryKey\(\{[^}]*\boffers:\s*offersDependents\.value\s*\}/', $step,
            'El rótulo ya no le pasa `offers` al módulo: el bloque de solo justificante volvería a decir «menores».');
        $this->assertMatchesRegularExpression('/<details v-if="offersDependents \|\|/', $step,
            'El bloque ya no se abre con la MISMA condición que elige el rótulo.');
        $this->assertMatchesRegularExpression('/<DependentPicker v-if="offersDependents"/', $step,
            'El selector de menores ya no se pinta con la MISMA condición que elige el rótulo.');
    }
}
