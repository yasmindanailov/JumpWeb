<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * El gate de `pre-push`, hecho FALSABLE (hermano de {@see CriticalPathGateTest}).
 *
 * `DECISIONES #9` descartó GitHub Actions: **el CI de este proyecto es el hook de pre-push**. Eso
 * significa que un paso que desaparezca de un script de shell no lo nota nadie hasta el día que
 * hacía falta, y ese día es tarde. `CriticalPathGateTest` ya trae al terreno ejecutable la parte de
 * concurrencia; esto hace lo mismo con los cuatro pasos del gate.
 *
 * El de `npm run build` se añadió el 2026-08-13 **después de que el fallo ocurriera de verdad**: un
 * build interrumpido dejó `public/build/manifest.json` a 0 bytes y toda la web pública respondió
 * 500. Como `public/build` está en `.gitignore`, el manifest no viaja en el commit y ninguna suite
 * lo miraba. `INVARIANTES SUITE-05` ya lo pedía por escrito desde antes.
 */
class PrePushGateTest extends TestCase
{
    /**
     * Los cuatro pasos, con el comando que los identifica y por qué están.
     *
     * @var array<string, string>
     */
    private const STEPS = [
        'docs-check' => 'scripts/docs-check.sh',
        'Pint' => 'pint --test',
        'build de assets' => 'npm run build',
        'suite' => 'artisan test --parallel',
    ];

    private function hook(): string
    {
        $path = base_path('.githooks/pre-push');

        $this->assertFileExists($path, 'el hook de pre-push ha desaparecido: el proyecto se queda SIN CI');

        return (string) file_get_contents($path);
    }

    public function test_the_gate_still_runs_every_step(): void
    {
        $hook = $this->hook();

        foreach (self::STEPS as $name => $needle) {
            $this->assertStringContainsString(
                $needle, $hook,
                "El pre-push ya no corre «{$name}». Es el ÚNICO CI del proyecto (`DECISIONES #9`): ".
                'lo que salga de aquí deja de comprobarse en ningún sitio.'
            );
        }
    }

    /**
     * El build va ANTES de la suite, no después: las vistas dan `ViteManifestNotFound` sin manifest
     * (`SUITE-05`), así que invertirlo convertiría un fallo de assets en una cascada de tests rojos
     * cuyo mensaje no menciona los assets.
     */
    public function test_assets_are_built_before_the_suite_runs(): void
    {
        $hook = $this->hook();

        $build = mb_strpos($hook, 'npm run build');
        $suite = mb_strpos($hook, 'artisan test --parallel');

        // Se comprueba que AMBOS existen antes de compararlos: `mb_strpos` devuelve `false` cuando
        // no encuentra, y `false < 1234` es `true` en PHP — la comparación sola habría pasado en
        // verde con el build borrado del hook. Verificado por mutación.
        $this->assertIsInt($build, 'el hook ya no construye los assets');
        $this->assertIsInt($suite, 'el hook ya no corre la suite');

        $this->assertLessThan(
            $suite, $build,
            'el build tiene que ir ANTES de la suite: sin manifest, las vistas revientan (SUITE-05)'
        );
    }

    /** El gate solo aplica a `main`: las ramas `wip/…` pueden empujarse en rojo (`CONVENCIONES §8`). */
    public function test_the_gate_is_scoped_to_main(): void
    {
        $this->assertStringContainsString('refs/heads/main', $this->hook());
    }
}
