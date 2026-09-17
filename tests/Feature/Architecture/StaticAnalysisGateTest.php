<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * El análisis estático, hecho FALSABLE (`DECISIONES #625`, hermano de {@see PrePushGateTest}).
 *
 * Larastan corre en el `pre-push` con una LÍNEA BASE: lo que había el día que entró (2026-09-17, 459
 * errores de nivel 5 en `app/`) se congeló en `phpstan-baseline.neon` y solo corta un error NUEVO.
 * Eso deja tres maneras de apagar la red sin que nada se ponga rojo, y este fichero las cierra:
 *
 *  · **bajar el nivel o estrechar las rutas** en `phpstan.neon` —el análisis pasa, mirando menos—;
 *  · **dejar de incluir la línea base y borrar el paso del hook** —lo segundo lo ve `PrePushGateTest`—;
 *  · **regenerar la línea base para tapar un error nuevo**, que es la tentación de verdad: un comando,
 *    el gate en verde y el error dentro. Por eso la línea base lleva TRINQUETE: solo puede bajar.
 */
class StaticAnalysisGateTest extends TestCase
{
    /**
     * Los errores congelados el día que entró el análisis. ⚠️ Esta cifra SOLO BAJA: si arreglas errores
     * de la línea base, regenérala (`phpstan analyse --generate-baseline phpstan-baseline.neon`) y baja
     * el número aquí en el mismo commit. Si el test te pide SUBIRLO, has metido un error nuevo en la
     * línea base en vez de arreglarlo.
     */
    private const FROZEN_ERRORS = 459;

    private function config(): string
    {
        $path = base_path('phpstan.neon');

        $this->assertFileExists($path, 'No existe `phpstan.neon`: el paso del gate correría con los valores por defecto, o sea sin Larastan.');

        // Sin comentarios: la cabecera del fichero NOMBRA la línea base y el nivel, y una aguja que
        // casa en el comentario sobrevive a que se borre la línea de verdad (la lección de `deploy.sh`).
        $lines = preg_split('/\R/', (string) file_get_contents($path)) ?: [];

        return implode("\n", array_filter($lines, static fn (string $l): bool => ! preg_match('/^\s*#/', $l)));
    }

    public function test_the_analysis_keeps_its_level_its_paths_and_the_laravel_extension(): void
    {
        $config = $this->config();

        $this->assertStringContainsString(
            'vendor/larastan/larastan/extension.neon',
            $config,
            'Sin la extensión de Larastan, PHPStan no entiende facades ni Eloquent y la línea base deja de casar.',
        );

        preg_match('/^\s*level:\s*(\d+)\s*$/m', $config, $level);

        $this->assertNotEmpty($level, '`phpstan.neon` ya no declara su nivel.');
        $this->assertGreaterThanOrEqual(5, (int) $level[1], 'El nivel del análisis no baja de 5 (`#625`): bajarlo es pasar el gate mirando menos.');

        $this->assertMatchesRegularExpression(
            '/^\s*paths:\s*\n\s*-\s*app\s*$/m',
            $config,
            'El análisis tiene que seguir cubriendo `app/` entero: estrechar las rutas es la otra forma de mirar menos.',
        );
    }

    public function test_the_baseline_is_included_and_can_only_shrink(): void
    {
        $this->assertStringContainsString(
            '- phpstan-baseline.neon',
            $this->config(),
            '`phpstan.neon` ya no incluye la línea base: el gate cortaría por los errores congelados y alguien lo «arreglaría» quitando el paso.',
        );

        $path = base_path('phpstan-baseline.neon');

        $this->assertFileExists($path);

        preg_match_all('/^\s*count:\s*(\d+)\s*$/m', (string) file_get_contents($path), $counts);

        $frozen = array_sum(array_map('intval', $counts[1]));

        $this->assertGreaterThan(0, $frozen, 'No se ha podido leer ningún `count:` de la línea base: el trinquete estaría ciego.');

        $this->assertLessThanOrEqual(
            self::FROZEN_ERRORS,
            $frozen,
            "La línea base congela {$frozen} errores y el techo es ".self::FROZEN_ERRORS.'. La línea base solo BAJA: '.
            'un error nuevo se arregla, no se congela regenerándola.',
        );

        $this->assertSame(
            self::FROZEN_ERRORS,
            $frozen,
            "La línea base ha bajado a {$frozen}: bien. Baja `FROZEN_ERRORS` a esa cifra en este mismo commit, o el hueco ".
            'entre las dos deja sitio para congelar errores nuevos sin que nada avise.',
        );
    }

    public function test_the_analysis_runs_before_the_expensive_steps(): void
    {
        $hook = (string) file_get_contents(base_path('.githooks/pre-push'));

        $analysis = mb_strpos($hook, 'phpstan analyse --no-progress');
        $suite = mb_strpos($hook, 'artisan test --parallel');

        $this->assertIsInt($analysis, 'El pre-push ya no corre el análisis estático.');
        $this->assertIsInt($suite, 'El pre-push ya no corre la suite.');
        $this->assertLessThan($suite, $analysis, 'El análisis (10 s) va ANTES de la suite (80 s): lo barato corta primero.');
    }
}
