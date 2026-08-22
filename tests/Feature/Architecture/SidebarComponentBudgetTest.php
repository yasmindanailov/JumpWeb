<?php

namespace Tests\Feature\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * **CE-6 con dientes: los componentes del cajón PINTAN, y la lógica vive en módulos planos**
 * (Fase 4 · paso 4.7·2b·2·C, `DECISIONES #90`).
 *
 * `CE-6` estaba escrito en `specs/sidebar-spa.md` desde el principio y **no lo vigilaba nada**: las
 * únicas guardas del cajón eran presupuestos de KiB del bundle, que un `<script>` de 1.363 líneas pasa
 * sin despeinarse. Así fue como `Sidebar.vue` llegó a tener los MISMOS métodos que el `Purchase.php`
 * que estamos retirando —`selectProduct`, `addToCart`, `checkout`, `confirmReservation`,
 * `retryPayment`, `goBack`…—: se estaba construyendo el segundo objeto-dios mientras se desmontaba el
 * primero, y ninguna prueba lo decía.
 *
 * ### Qué se mide, y por qué NO son líneas crudas
 *
 * Se cuentan **líneas de CÓDIGO** dentro de los bloques `<script>`: fuera blancos y fuera comentarios.
 * No es un detalle de implementación, es lo que hace que la guarda no se pueda «cumplir» de la peor
 * manera posible. Este proyecto documenta muchísimo —de las 1.477 líneas de `Sidebar.vue`, **solo 618
 * son código**— y un contador de líneas crudas habría convertido «borra los comentarios» en una forma
 * legítima de pasar el test. La guarda mide lógica; la prosa que la explica no estorba.
 *
 * ### La forma de la regla: un techo, y excepciones CON NOMBRE que solo encogen
 *
 * Medido el 2026-08-15: **18 de los 19 componentes ya cumplen** —ninguno pasa de 24 líneas de código y
 * ninguno llama a la API—. La violación es UN fichero, así que la regla se escribe como regla y no
 * como promedio: techo general para todos, y `Sidebar.vue` como excepción declarada con su número
 * exacto. Es el mismo patrón que las baselines de `ModuleBoundariesTest`.
 *
 * ⚠️ **La excepción SOLO PUEDE ENCOGER, y el test lo exige en las dos direcciones**: si crece, es una
 * regresión; si baja y nadie actualiza el número, la baseline deja de apretar y el siguiente
 * crecimiento pasa desapercibido. Bajar el número es parte del commit que extrae la lógica.
 *
 * ▶ **El camino para bajarlo ya existe y está probado**: `admission.js::runCheckout()` es una
 * secuencia del cajón extraída a un módulo plano, con sus dependencias por parámetro para poder
 * doblarlas y sus casos en `admission.test.js`. Quedan ~10 secuencias en `Sidebar.vue` esperando el
 * mismo trato. Ficha en `DEUDA.md`.
 */
class SidebarComponentBudgetTest extends TestCase
{
    /**
     * El techo de un componente que solo pinta.
     *
     * El mayor de los que cumplen hoy es `TimeStep.vue` con 24, así que 40 deja sitio de sobra para
     * un paso nuevo legítimo sin legitimar que vuelva a crecer nada parecido a un orquestador.
     */
    private const MAX_CODE_LINES = 40;

    /**
     * Excepciones declaradas, con su medida EXACTA. Solo pueden encoger.
     *
     * @var array<string, array{code: int, api: int}>
     */
    private const EXCEPTIONS = [
        // 618 → 614 en 4.4b·2: retirar la delegación en el modal de Livewire quitó cuatro líneas y
        // el vaciado del token del anti-bot añadió una. La lógica del widget vive en `turnstile.js`.
        //
        // 614 → 600 el 2026-08-22, primer clic del trinquete de la reorganización: el estado del DÍA
        // se muda a `stores/date.js`. ⚠️ **La bajada es pequeña a propósito y el dato importa**: se
        // midió que el ESTADO es solo el **9%** de este fichero (55 líneas de 614) y las FUNCIONES el
        // **72%** (441). Mover estado a stores da poco por sí solo; lo que baja el número de verdad es
        // sacar las secuencias — 184 líneas son de un solo dominio y pueden ser acciones de su store,
        // y 214 son transversales y piden el patrón `admission.js::runCheckout()`.
        //
        // ⚠️ **614 → 438 y 11 → 2 llamadas a la API** en la reorganización del 2026-08-22 (nueve
        // stores). El segundo número es el que de verdad mide `CE-6`; las líneas son el síntoma.
        //
        // ⚠️⚠️ **Y SUBE DE 434 A 438, que es lo que esta regla existe para hacer visible.** No es el
        // objeto-dios recreciendo: son las cuatro líneas de `actOnIdentity()`, que RESTAURAN una
        // conducta perdida al mudar la identidad al store — si la cesta se purga hay que volver al
        // catálogo, o el cliente se queda mirando un carrito vacío. Verificado en navegador entrando y
        // cerrando sesión: sin ellas, purga y se queda en el paso 4.
        // Una subida sin este párrafo detrás sería exactamente lo que la regla prohíbe.
        'sidebar/Sidebar.vue' => ['code' => 438, 'api' => 2],
    ];

    /**
     * ⚠️ **Un componente no habla con la API.** Pedir datos es una secuencia —qué se pide, en qué
     * orden, qué se hace si falla— y una secuencia dentro de un `.vue` pierde su red: los componentes
     * se comparan por su ÁRBOL, y un árbol no dice a quién se le preguntó. Es literalmente el fallo
     * que 4.3·1 pagó con la banda de progreso (módulo verde, cableado roto, gate sin verlo).
     */
    public function test_no_component_talks_to_the_api(): void
    {
        $offenders = [];

        foreach ($this->components() as $relative => $path) {
            $calls = $this->apiCalls($path);
            $allowed = self::EXCEPTIONS[$relative]['api'] ?? 0;

            if ($calls > $allowed) {
                $offenders[] = "{$relative}: {$calls} llamada(s), permitidas {$allowed}";
            }
        }

        $this->assertSame(
            [], $offenders,
            "Hay componentes del cajón que llaman a la API directamente:\n  ".implode("\n  ", $offenders)."\n\n".
            "⚠️ CE-6: la secuencia va en un módulo plano, con `api` por parámetro para poder doblarla.\n".
            'El patrón probado es `admission.js::runCheckout()`.'
        );
    }

    /** El techo de código por componente. Los que están en `EXCEPTIONS` se miden aparte. */
    public function test_every_component_stays_a_painter(): void
    {
        $offenders = [];

        foreach ($this->components() as $relative => $path) {
            if (isset(self::EXCEPTIONS[$relative])) {
                continue;
            }

            $lines = $this->codeLines($path);

            if ($lines > self::MAX_CODE_LINES) {
                $offenders[] = "{$relative}: {$lines} líneas de código (techo ".self::MAX_CODE_LINES.')';
            }
        }

        $this->assertSame(
            [], $offenders,
            "Hay componentes del cajón con demasiada lógica dentro:\n  ".implode("\n  ", $offenders)."\n\n".
            "⚠️ CE-6: los componentes PINTAN. Lo que decide —secuencias, reglas, composición— va a un\n".
            "módulo plano `.js`, que es lo que se puede probar con `node --test` y comparar contra el\n".
            'servidor. Si el componente es nuevo y de verdad necesita más, la excepción se DECLARA.'
        );
    }

    /**
     * ⚠️ **Y la excepción solo encoge**, comprobado en las dos direcciones: crecer es una regresión, y
     * bajar sin actualizar el número deja la baseline floja para el siguiente.
     *
     * @param  array{code: int, api: int}  $budget
     */
    #[DataProvider('exceptionProvider')]
    public function test_the_declared_exception_only_shrinks(string $relative, array $budget): void
    {
        $path = resource_path('js/'.$relative);

        $this->assertFileExists($path, "la excepción «{$relative}» ya no existe: quita su entrada");

        $this->assertSame(
            $budget['code'], $this->codeLines($path),
            "«{$relative}» ya no mide {$budget['code']} líneas de código.\n".
            "⚠️ Si ha CRECIDO, es una regresión: la lógica nueva va a un módulo plano (CE-6).\n".
            "Si ha BAJADO —enhorabuena—, actualiza el número en `EXCEPTIONS` en este mismo commit: una\n".
            'baseline que no aprieta deja pasar el siguiente crecimiento sin que nadie lo note.'
        );

        $this->assertSame(
            $budget['api'], $this->apiCalls($path),
            "«{$relative}» ya no hace {$budget['api']} llamadas a la API. Misma regla: actualiza el número."
        );
    }

    /**
     * **La guarda de la guarda.** Sin esto, un escáner roto —que no encuentre el `<script>`, o que
     * cuente 0 en todo— dejaría los dos casos de arriba pasando solos y el presupuesto mentiría por lo
     * bajo. Es lo que le pasó al contador de `PurchaseRetirementTest` en `#63`.
     *
     * Y comprueba lo que de verdad importa del contador: **que los comentarios y los blancos NO
     * cuentan**, que es lo que impide que «borra la documentación» sea una forma de pasar el test.
     */
    public function test_the_counter_measures_code_and_ignores_prose(): void
    {
        $this->assertNotSame([], $this->components(), 'el escaneo no encuentra ningún componente');

        $sample = <<<'VUE'
            <script setup>
            // un comentario de línea
            import { ref } from 'vue';

            /**
             * Un bloque de documentación como los de este proyecto,
             * que ocupa varias líneas y no es lógica.
             */
            const value = ref(0);
            </script>

            <template><div /></template>
            VUE;

        $path = tempnam(sys_get_temp_dir(), 'vue').'.vue';
        file_put_contents($path, $sample);

        try {
            $this->assertSame(2, $this->codeLines($path), 'solo el `import` y el `const` son código');
            $this->assertSame(0, $this->apiCalls($path));
        } finally {
            @unlink($path);
        }
    }

    /** @return iterable<string, array{string, array{code: int, api: int}}> */
    public static function exceptionProvider(): iterable
    {
        foreach (self::EXCEPTIONS as $relative => $budget) {
            yield $relative => [$relative, $budget];
        }
    }

    /**
     * Los componentes del cajón, por ruta relativa a `resources/js/`.
     *
     * @return array<string, string>
     */
    private function components(): array
    {
        $root = resource_path('js');
        $found = [];

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file->getExtension() === 'vue') {
                $found[str_replace($root.DIRECTORY_SEPARATOR, '', $file->getPathname())] = $file->getPathname();
            }
        }

        ksort($found);

        return $found;
    }

    /**
     * Líneas de CÓDIGO dentro de los bloques `<script>`: fuera blancos y fuera comentarios.
     *
     * La heurística es deliberadamente simple y visible —una línea es prosa si empieza por `//`, `/*`
     * o `*`— porque una guarda de arquitectura tiene que poder leerse de un vistazo. No intenta
     * quitar comentarios al final de una línea de código: esa línea SÍ es código.
     */
    private function codeLines(string $path): int
    {
        $source = (string) file_get_contents($path);
        $lines = 0;

        preg_match_all('#<script\b[^>]*>(.*?)</script>#s', $source, $blocks);

        foreach ($blocks[1] as $block) {
            foreach (explode("\n", $block) as $line) {
                $trimmed = trim($line);

                if ($trimmed === '' || str_starts_with($trimmed, '//') || str_starts_with($trimmed, '/*') || str_starts_with($trimmed, '*')) {
                    continue;
                }

                $lines++;
            }
        }

        return $lines;
    }

    private function apiCalls(string $path): int
    {
        return preg_match_all('/\bapi\.(?:get|post)\s*\(/', (string) file_get_contents($path));
    }
}
