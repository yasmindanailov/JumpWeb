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

        $reachable = $this->reachableByRunner($root);

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

    /**
     * ⚠️ **El glob del runner tiene que expandirlo NODE, no `sh`** (2026-08-22).
     *
     * El glob del runner **sin comillas** lo expande la shell, y en `sh` el doble
     * asterisco **no es recursivo**: vale exactamente un nivel. Medido con un canario en
     * `sidebar/__probe/`: sin comillas el runner sigue diciendo 314 tests y el canario **no corre**;
     * con comillas dice 315.
     *
     * No es teórico: la reorganización del cajón en `stores/` pone tests a dos niveles, y sin esto
     * habrían quedado fuera de la suite **diciendo «pass» igual** — que es el peor modo de fallo
     * posible en un gate.
     */
    public function test_the_js_runner_glob_is_expanded_by_node_and_not_by_the_shell(): void
    {
        $package = (string) file_get_contents(base_path('package.json'));

        $this->assertStringContainsString(
            'node --test \\"resources/js/**/*.test.js\\"',
            $package,
            'El glob de `test:js` ha perdido sus comillas. Sin ellas lo expande `sh`, donde `**` vale '.
            'UN nivel: cualquier test en una subcarpeta deja de ejecutarse y la suite no se entera.',
        );
    }

    /**
     * Qué ficheros ejecuta de verdad `npm run test:js`, **derivado del script** y no copiado a mano.
     *
     * ⚠️ **Quién expande el glob decide el resultado, y por eso se modelan las DOS semánticas**:
     * entrecomillado lo expande NODE, cuyo `**` baja por todo el árbol; sin comillas lo expande `sh`,
     * donde vale exactamente UN nivel. Copiar aquí una de las dos a mano fue lo que dejó este caso
     * describiendo el pasado cuando el script cambió.
     *
     * ⚠️ Y `glob()` de PHP **no sirve** para imitar a Node: su `**` tampoco es recursivo (medido: da
     * exactamente lo mismo que `/*` y no ve un fichero a dos niveles).
     *
     * @return array<int, string>
     */
    private function reachableByRunner(string $root): array
    {
        $package = json_decode((string) file_get_contents(base_path('package.json')), true, 512, JSON_THROW_ON_ERROR);
        $script = $package['scripts']['test:js'] ?? '';

        $this->assertStringContainsString('node --test', $script, 'El script `test:js` ya no invoca al runner de Node.');

        // Con comillas manda Node (recursivo); sin ellas, `sh` (un nivel).
        if (str_contains($script, '"resources/js/')) {
            $files = [];
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

            foreach ($it as $file) {
                if (str_ends_with($file->getFilename(), '.test.js')) {
                    $files[] = str_replace($root.'/', '', $file->getPathname());
                }
            }

            return $files;
        }

        return array_map(
            fn (string $path): string => str_replace($root.'/', '', $path),
            glob($root.'/*/*.test.js') ?: []
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

    /**
     * ⚠️ El contador de la suite es el único número «vivo» que **`docs-check` NO vigila**: sus
     * patrones son modelos, migraciones, invariantes y Resources, y «N tests» no casa con ninguno.
     *
     * Y derivó, con una insistencia que da la medida del problema: el 2026-08-21 la doc decía
     * **2715** con la suite en **2642**, se retiró el duplicado por eso mismo… y en esa MISMA sesión
     * el número se quedó atrás **dos veces más**, una de ellas en el commit que acababa de corregirlo.
     * No es descuido de nadie: es un número sin receta.
     *
     * El gate es el sitio natural para la receta porque **ya tiene la cifra en la mano** — acaba de
     * correr la suite—, así que compararla no cuesta nada (`DECISIONES #116`). ⚠️ **Desde F1
     * (`#618`, 2026-09-16) la copia declarada NO está en `docs/ESTADO.md`**: esa línea era la que los
     * dos carriles reescribían en cada cierre. Vive en el trailer del commit («Verificación: suite N
     * tests / M aserciones», `CONVENCIONES §8`), y el gate lo busca en los commits que el push lleva a
     * `main`, del más nuevo al más viejo. Este caso fija las DOS mitades: que se compara, y de dónde se lee.
     */
    public function test_the_gate_checks_the_suite_counter_declared_in_the_commit_trailer(): void
    {
        $hook = $this->hook();

        $this->assertStringContainsString(
            'MIENTE sobre el tamaño de la suite',
            $hook,
            'El gate ya no compara el contador de la suite con el que declara el commit. '.
            'Sin esa comparación el número vuelve a ser una foto sin receta, y ya demostró que '.
            'deriva en cuestión de horas.',
        );

        $this->assertStringContainsString(
            'suite[[:space:]]+[0-9][0-9.]*[[:space:]]+tests?[[:space:]]*/[[:space:]]*[0-9][0-9.]*[[:space:]]+aserciones',
            $hook,
            'Falta la lectura del trailer «suite N tests / M aserciones» del commit. Si cambias el '.
            'formato del trailer (`CONVENCIONES §8`), cambia también este patrón — o el gate dejará '.
            'de leerlo y no lo dirá.',
        );

        $this->assertStringContainsString(
            'git rev-list',
            $hook,
            'El trailer tiene que buscarse en los COMMITS DEL PUSH (del más nuevo al más viejo), no en '.
            'un documento: la copia en `docs/ESTADO.md` se retiró en `#618` porque era la línea que '.
            'los dos carriles reescribían a la vez.',
        );

        // Solo las líneas de CÓDIGO: un comentario puede citar la historia (`#116` lo leía de ahí).
        $this->assertDoesNotMatchRegularExpression(
            '/^[^#\n]*docs\/ESTADO\.md/m',
            $hook,
            'El hook vuelve a leer algo de `docs/ESTADO.md`: desde `#618` ese fichero es un índice de '.
            'carriles y no lleva el contador. Una segunda copia del número es la que deriva.',
        );
    }

    /**
     * Si no se puede leer alguno de los cuatro números, el gate **corta**. Un contador que no se ha
     * podido comprobar no es un contador comprobado — misma forma que la guarda de Redsys de `#106`,
     * donde preguntar por lo que se teme en vez de exigir lo que se espera bendijo un `PARSE ERROR`.
     * Desde `#618` son dos puertas: la salida del runner y el trailer del commit.
     */
    public function test_the_counter_check_is_fail_closed(): void
    {
        $hook = $this->hook();

        $this->assertStringContainsString(
            '-z "$ran_tests" || -z "$ran_asserts"',
            $hook,
            'La comprobación del contador tiene que cortar cuando la lectura de la SUITE sale VACÍA. '.
            'Sin eso, un cambio de formato en la salida del runner la deja comparando cadenas '.
            'vacías — que son iguales, o sea VERDE, sin haber comprobado nada.',
        );

        $this->assertStringContainsString(
            '-z "$doc_tests" || -z "$doc_asserts"',
            $hook,
            'La comprobación del contador tiene que cortar cuando NINGÚN commit del push declara el '.
            'trailer. Sin eso, un cierre sin la suite corrida pasaría el gate con dos cadenas vacías.',
        );
    }

    /** El gate solo aplica a `main`: las ramas `wip/…` pueden empujarse en rojo (`CONVENCIONES §8`). */
    public function test_the_gate_is_scoped_to_main(): void
    {
        $this->assertStringContainsString('refs/heads/main', $this->hook());
    }
}
