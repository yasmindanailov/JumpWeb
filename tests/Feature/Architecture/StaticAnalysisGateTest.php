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
    private const FROZEN_ERRORS = 457;

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

    /**
     * La otra mitad de `#625`: ESLint con las reglas de Vue sobre el cajón, con su línea base en
     * `eslint-suppressions.json` (12 errores el 2026-09-18). Las mismas tres puertas falsas que Larastan, en
     * JavaScript: apagar una regla o ignorar una carpeta en la config, estrechar o ablandar el comando del
     * script, y congelar un error nuevo regenerando la línea base.
     *
     * ⚠️ Esta cifra SOLO BAJA. Si arreglas un error congelado, ESLint sale con código 2 hasta que podas
     * (`npm run lint:js -- --prune-suppressions`): baja el número aquí en el mismo commit.
     */
    /**
     * ▶ **12 → 10 el 2026-09-19** (`DECISIONES #707`): declarar el `addBtn` que faltaba en
     * `DependentsZone.vue` no arregló un error, sino DOS —la referencia aparecía en `closeAdd()` y en
     * el alta correcta—. El trinquete solo baja.
     */
    private const FROZEN_JS_ERRORS = 10;

    private function jsConfig(): string
    {
        $path = base_path('eslint.config.js');

        $this->assertFileExists($path, 'No existe `eslint.config.js`: ESLint no corre sin config plana, y el paso del gate fallaría o no miraría nada.');

        // Sin comentarios, como `config()`: la cabecera NOMBRA el juego de reglas y una aguja que casa en el
        // comentario sobrevive a que se borre la línea de verdad.
        $lines = preg_split('/\R/', (string) file_get_contents($path)) ?: [];

        return implode("\n", array_filter($lines, static fn (string $l): bool => ! preg_match('#^\s*//#', $l)));
    }

    public function test_the_js_analysis_keeps_its_rules_its_scope_and_its_command(): void
    {
        $config = $this->jsConfig();

        $this->assertStringContainsString('js.configs.recommended', $config, 'Sin las reglas base de ESLint no hay `no-undef` ni `no-unused-vars`: las dos que encontraron algo el día que entró.');
        $this->assertMatchesRegularExpression(
            "/vue\.configs\['flat\/(essential|strongly-recommended|recommended)'\]/",
            $config,
            'Sin un juego de reglas de Vue, ESLint no entiende una plantilla: `flat/essential` es el suelo (`#625`).',
        );
        $this->assertStringContainsString("'resources/js/{sidebar,cajon}/**/*.{js,vue}'", $config, 'La config ya no declara el cajón entero: el motor (`sidebar/`) y lo que lo abre sin framework (`cajon/`, F4 · T2), `.js` y `.vue`.');
        $this->assertMatchesRegularExpression(
            "/'no-use-before-define':\s*\[\s*'error'/",
            $config,
            '`no-use-before-define` es la regla que pedía `DEUDA.md` (`#210`) y no viene en las reglas base: si se cae de la config, nada avisa.',
        );

        $this->assertDoesNotMatchRegularExpression(
            '/[\'"]off[\'"]|:\s*0\s*[,}\]]|\bignores\s*:/',
            $config,
            'La config apaga una regla o ignora ficheros: es pasar el gate mirando menos. Un error se arregla; si de verdad '.
            'sobra una regla, se quita AQUÍ con su porqué, no en silencio.',
        );

        $scripts = json_decode((string) file_get_contents(base_path('package.json')), true)['scripts'] ?? [];

        $this->assertSame(
            'eslint resources/js/sidebar resources/js/cajon',
            $scripts['lint:js'] ?? null,
            'El comando del gate es exactamente este: una subcarpeta estrecha el alcance, y `--quiet`, `|| true` o '.
            '`--pass-on-unpruned-suppressions` ablandan el veredicto.',
        );
    }

    public function test_the_js_baseline_can_only_shrink(): void
    {
        $path = base_path('eslint-suppressions.json');

        $this->assertFileExists($path, 'Sin `eslint-suppressions.json` el gate cortaría por los errores congelados y alguien lo «arreglaría» quitando el paso.');

        $suppressions = json_decode((string) file_get_contents($path), true);

        $this->assertIsArray($suppressions, '`eslint-suppressions.json` no parsea: el trinquete estaría ciego.');

        $frozen = 0;

        foreach ($suppressions as $rules) {
            foreach ((array) $rules as $rule) {
                $frozen += (int) ($rule['count'] ?? 0);
            }
        }

        $this->assertGreaterThan(0, $frozen, 'No se ha podido leer ningún `count` de la línea base del cajón: el trinquete estaría ciego.');

        $this->assertLessThanOrEqual(
            self::FROZEN_JS_ERRORS,
            $frozen,
            "La línea base del cajón congela {$frozen} errores y el techo es ".self::FROZEN_JS_ERRORS.'. Solo BAJA: '.
            'un error nuevo se arregla, no se congela con `--suppress-all`.',
        );

        $this->assertSame(
            self::FROZEN_JS_ERRORS,
            $frozen,
            "La línea base del cajón ha bajado a {$frozen}: bien. Baja `FROZEN_JS_ERRORS` a esa cifra en este mismo commit.",
        );
    }

    public function test_the_js_analysis_runs_before_the_expensive_steps(): void
    {
        $hook = (string) file_get_contents(base_path('.githooks/pre-push'));

        $lint = mb_strpos($hook, 'laravel.test npm run lint:js');
        $build = mb_strpos($hook, 'laravel.test npm run build');
        $suite = mb_strpos($hook, 'artisan test --parallel');

        $this->assertIsInt($lint, 'El pre-push ya no corre ESLint sobre el cajón.');
        $this->assertIsInt($build, 'El pre-push ya no construye los assets.');
        $this->assertIsInt($suite, 'El pre-push ya no corre la suite.');
        $this->assertLessThan($build, $lint, 'ESLint (2 s) va ANTES de los builds: no se compila un cajón que no pasa el análisis.');
        $this->assertLessThan($suite, $lint, 'ESLint (2 s) va ANTES de la suite (80 s): lo barato corta primero.');
    }
}
