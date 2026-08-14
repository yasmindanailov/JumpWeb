<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * El gate de `pre-push`, hecho FALSABLE (hermano de {@see CriticalPathGateTest}).
 *
 * `DECISIONES #9` descartó GitHub Actions: **el CI de este proyecto es el hook de pre-push**. Eso
 * significa que un paso que desaparezca de un script de shell no lo nota nadie hasta el día que
 * hacía falta, y ese día es tarde. `CriticalPathGateTest` ya trae al terreno ejecutable la parte de
 * concurrencia; esto hace lo mismo con cada paso del gate.
 *
 * El de `npm run build` se añadió el 2026-08-13 **después de que el fallo ocurriera de verdad**: un
 * build interrumpido dejó `public/build/manifest.json` a 0 bytes y toda la web pública respondió
 * 500. Como `public/build` está en `.gitignore`, el manifest no viaja en el commit y ninguna suite
 * lo miraba. `INVARIANTES SUITE-05` ya lo pedía por escrito desde antes.
 */
class PrePushGateTest extends TestCase
{
    /**
     * Los pasos del gate, con el comando que los identifica y por qué están.
     *
     * `build:ssr` entró en Fase 4 · paso 4.2: `SidebarDomContractTest` compara el árbol renderizado
     * de los DOS motores, y el de Vue hay que compilarlo antes porque Node no carga `.vue`. Sin ese
     * paso, el test de paridad visual no puede correr — y es el que sostiene `CE-2`.
     *
     * `test:js` entró en Fase 4 · paso 4.1: la máquina de estados del cajón SPA es un módulo JS
     * plano —lo es justo para poder probarla sin montar un runner de componentes— y su red solo vale
     * si algo la ejecuta. La del sidebar Livewire tiene seis ficheros de test detrás; transcribirla
     * sin equivalente sería una pérdida neta de cobertura (`sidebar-spa.md` §4.8, CE-6).
     *
     * @var array<string, string>
     */
    private const STEPS = [
        'docs-check' => 'scripts/docs-check.sh',
        'Pint' => 'pint --test',
        'build de assets' => 'npm run build',
        'build SSR del cajón' => 'npm run build:ssr',
        'tests JS' => 'npm run test:js',
        'suite' => 'artisan test --parallel',
    ];

    /**
     * ⚠️ **`npm run test:js` solo ve UN nivel de carpeta, y su fallo es silencioso.**
     *
     * El script pasa a `node --test` un patrón con doble asterisco sobre `resources/js`, y quien lo
     * expande es el shell de npm (`sh`/`dash`), donde `globstar` está DESACTIVADO: el doble asterisco
     * equivale a uno solo. Un test colocado en
     * `resources/js/sidebar/steps/` —o en cualquier subcarpeta— **no se ejecutaría nunca**, y la suite
     * imprimiría «pass» sin ejecutarlo. No hay error ni aviso: el fichero simplemente no existe para
     * el runner.
     *
     * Es el modo de fallo más barato del cajón SPA, porque la tentación de agrupar los módulos en
     * subcarpetas crece con cada paso. `test_the_gate_still_runs_every_step` no lo ve: comprueba que
     * la cadena `npm run test:js` sigue en el hook, no que ejecute algo.
     *
     * Esto compara los ficheros que el patrón alcanza con los que existen en el árbol.
     *
     * (Y sí: la primera versión de este docblock cerraba el comentario a media frase al escribir el
     * patrón literal, que lleva un cierre dentro. Es el mismo fallo que `SidebarTokenBudgetTest`
     * vigila en las hojas de estilo, esta vez en PHP.)
     */
    public function test_every_js_test_file_is_reachable_by_the_runner(): void
    {
        $root = base_path('resources/js');

        $onDisk = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if (str_ends_with($file->getFilename(), '.test.js')) {
                $onDisk[] = str_replace($root.'/', '', $file->getPathname());
            }
        }

        // El MISMO patrón del script, expandido con el mismo criterio: un solo nivel.
        $reachable = array_map(
            fn (string $path): string => str_replace($root.'/', '', $path),
            glob($root.'/*/*.test.js') ?: []
        );

        sort($onDisk);
        sort($reachable);

        $this->assertNotEmpty($onDisk, 'no hay tests JS: la red del cajón SPA ha desaparecido');

        $this->assertSame(
            $onDisk, $reachable,
            "Hay ficheros de test JS que `npm run test:js` NO ejecuta.\n".
            'El patrón del script lo expande `sh`, donde el doble asterisco es un solo nivel: un '.
            "test en una subcarpeta no corre NUNCA y la suite dice «pass» igual.\n".
            '  en el árbol : '.implode(', ', array_diff($onDisk, $reachable))."\n".
            '  al alcance  : '.implode(', ', $reachable)
        );
    }

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
