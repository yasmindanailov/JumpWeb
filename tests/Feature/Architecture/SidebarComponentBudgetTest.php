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
        //
        // ⚠️⚠️ **La excepción cambia de FICHERO el 2026-08-22, y ese es el titular**: el embudo dejó de
        // ser la raíz. `Sidebar.vue` se renombró a `sections/PurchaseSection.vue` y nació una raíz
        // nueva de **16 líneas y CERO llamadas a la API**, que no necesita excepción ninguna. Lo hizo
        // por el ÁREA DE CLIENTE (`DECISIONES #66`): sus pantallas entran como otra sección, al lado,
        // y no dentro del componente del embudo.
        // ⚠️ **438 → 431 el 2026-08-22**, al subir el puente de `mode`/`identifying` a la raíz: desde
        // que el cajón tiene dos SECCIONES esas señales dependen de cuál está activa, y un `watch`
        // sobre el paso no se dispara al conmutar (`specs/area-cliente.md` §4.5). Aquí se queda el
        // confeti, que sí es de la compra.
        // ⚠️ **431 → 432 el 2026-08-23, y es UNA línea: el `import` de `account/session-gained.js`**
        // (`specs/account-context-vue.md` §4.6). El evento `logged-in` de Livewire murió con el
        // bloque de cuenta, y lo que hay que hacer al conseguir sesión —repintar el bloque, invalidar
        // las próximas reservas y marcar `authChanged`— se fue a un módulo plano con su `node --test`.
        // ▶ **Aquí subió a 436 y se BAJÓ a 432 antes de commitear**, que es la mitad de la regla que
        // casi nunca se cumple: la primera versión pasaba los dos stores desde el componente —dos
        // imports y dos instanciaciones que no son suyos—, y el módulo los resuelve él por defecto
        // (mismo patrón que `api = httpClient`). El gate hizo justo lo que existe para hacer:
        // **provocar la pregunta**.
        // ⚠️ **432 → 429 el 2026-08-27 por la noche, la tanda 4 de menores a cargo (`DECISIONES #202`):
        // y otra vez SUBIÓ primero y se BAJÓ antes de commitear.** Cablear la asignación —el store de
        // menores, `loadDependents()`, la puerta 2 en `enterWith()`, el 422 aplicado a la cesta, los
        // ids de la línea en construcción— lo puso en **440**. La regla dice que solo encoge, así que
        // salieron DOS cosas que nunca debieron vivir aquí: `today()` (a `cart.js::todayIso()`) y
        // `showLineProblems` entero (a `line-problems.js`, con `node --test` — era una regla de
        // presentación sin ningún caso). El gate volvió a hacer su trabajo: provocar la pregunta.
        // Y a 428 el mismo día: el guion headless cazó que el cajón nacido abierto con sesión no pedía
        // los menores (una línea de `watch` sobre el titular), y la pagó `showLineProblems`, que pasó
        // a UNA llamada porque «aplicar lo decidido a la cesta» es del store (`applyLineProblems`).
        // 428 → 429 el 2026-08-28 (`#239`): UNA línea, y es cableado, no lógica —`timeStore.setLowMax()`
        // junto a las otras dos lecturas de `/config`—. La regla del aviso «casi llena» vive en
        // `offer.js::isAlmostFull()`, que es un módulo plano con sus propios casos (`CE-6`).
        // 429 → 425 el 2026-08-30 (`#278`): **BAJA**, y por una retirada. Al quitarse el confeti
        // (`[DECIDIDO owner]`) el `watch` que lo disparaba se queda sin sujeto y se va entero — un
        // observador que no observa nada es ruido que el siguiente agente tiene que descartar.
        // ⚠️ La baseline se aprieta EN EL MISMO COMMIT, que es lo que este test exige: dejarla en 429
        // regalaría cuatro líneas de crecimiento futuro sin que nadie se enterara.
        'sidebar/sections/PurchaseSection.vue' => ['code' => 425, 'api' => 2],
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

    /**
     * **Y la guarda de la guarda para el otro contador**: que ve los CINCO verbos de `api.js`.
     *
     * Sin este caso, el contador podría volver a quedarse corto al añadirse un verbo —que es
     * exactamente lo que pasó entre el paso 6b y el 8— y los dos casos de arriba seguirían verdes
     * contando de menos. Se enumeran a propósito uno a uno: leerlos del propio `api.js` haría que un
     * verbo mal escrito allí se «comprobara» contra sí mismo.
     */
    public function test_the_api_counter_sees_every_verb_the_client_offers(): void
    {
        $sample = "<script setup>\n".
            "api.get('/a'); api.post('/b', {}); api.put('/c', {}); api.patch('/d', {}); api.delete('/e');\n".
            "</script>\n";

        $path = tempnam(sys_get_temp_dir(), 'vue').'.vue';
        file_put_contents($path, $sample);

        try {
            $this->assertSame(5, $this->apiCalls($path), 'el contador no ve alguno de los verbos de `api.js`');
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
    /**
     * ⚠️⚠️ **La RAÍZ del cajón enruta; no pinta pantallas** (2026-08-22).
     *
     * Hasta hoy el embudo de compra ERA la raíz: 438 líneas y once ramas por paso en un solo fichero.
     * `DECISIONES #66` dice que el cajón hospedará también el ÁREA DE CLIENTE, y meter sus pantallas
     * ahí habría puesto **dos dominios en el mismo componente** — con cada pantalla nueva encareciendo
     * la separación posterior.
     *
     * El techo general de 40 líneas ya la vigila por tamaño. Este caso vigila lo que el tamaño no
     * dice: que **no vuelva a conocer los pasos del embudo ni a hablar con la API**. Una raíz con un
     * `v-if="store.step === ..."` dentro es una raíz que ha vuelto a ser una pantalla.
     */
    public function test_the_root_routes_sections_and_does_not_paint_screens(): void
    {
        $relative = 'sidebar/Sidebar.vue';
        $path = base_path('resources/js/'.$relative);

        $this->assertFileExists($path, 'La raíz del cajón ha desaparecido.');

        $this->assertArrayNotHasKey(
            $relative, self::EXCEPTIONS,
            'La raíz ha vuelto a necesitar una excepción de tamaño. Si ha crecido tanto es que ha '.
            'vuelto a hacer trabajo de pantalla: ese trabajo va en una SECCIÓN.',
        );

        $this->assertSame(
            0, $this->apiCalls($path),
            'La raíz no habla con la API. Pedir datos es trabajo de una sección o de su store.',
        );

        $source = (string) file_get_contents($path);

        $this->assertStringNotContainsString(
            'STEPS.', $source,
            'La raíz conoce los pasos del EMBUDO otra vez. Los pasos son de la sección de compra; una '.
            'raíz que los conoce es una raíz que ha vuelto a ser una pantalla — y es exactamente lo '.
            'que impide que el área de cliente entre al lado en vez de dentro.',
        );

        // ⚠️⚠️ **La sección de compra se OCULTA, no se desmonta — y desde el 2026-08-23 hay DOS
        // motivos, no uno.** El primero lleva escrito en la raíz desde `#119`: su `ref` sostiene el
        // puente de `defineExpose`, y sin él las dos señales que `index.js` invoca en cada apertura
        // se las come el `?.` en silencio. El segundo lo trajo la auth (`specs/auth-en-cajon.md`
        // §4.3): el `onMounted` de esa sección es **el único** que pide `GET /config`, y de ahí sale
        // la clave del anti-bot que usa también el ALTA del área de cliente. Con `v-if`, un invitado
        // que entrara directo a la zona de registro montaría el formulario **sin widget** y el
        // servidor rechazaría su alta con «no eres un robot» — sin correo, sin log y sin nada en
        // pantalla que lo explique (`DECISIONES #108`).
        $this->assertMatchesRegularExpression(
            '/<PurchaseSection\s+v-show=/', $source,
            "La sección de compra ha dejado de ocultarse con `v-show`.\n".
            "⚠️ Con `v-if` se DESMONTA, y con ella se van dos cosas que no se ven desde el marcado: el\n".
            "puente de señales hacia fuera del cajón, y el `GET /config` del que sale la clave del\n".
            'anti-bot que necesita el alta del área de cliente.',
        );
    }

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

    /**
     * ⚠️⚠️ **Cuenta los CINCO verbos, y hasta el paso 8 solo miraba dos.** El contador se escribió
     * cuando `api.js` únicamente tenía `get` y `post`; `put` llegó en el paso 6b y `patch`/`delete`
     * en el 7b (`DECISIONES #120(r)`), y nadie volvió a mirar aquí. Un componente que llamara a
     * `api.delete(...)` pasaba la guarda **sin que faltara nada** — la forma exacta de hueco que
     * `TESTING.md` §2.quater describe, y encima sin declarar.
     *
     * Medido al ampliarlo: ningún componente usaba los tres verbos nuevos, así que la baseline de
     * `PurchaseSection.vue` no se mueve. Es la diferencia entre cerrar un hueco y arreglar un fallo:
     * esto era lo primero, y por eso se puede hacer sin tocar ningún número.
     */
    private function apiCalls(string $path): int
    {
        return preg_match_all('/\bapi\.(?:get|post|put|patch|delete)\s*\(/', (string) file_get_contents($path));
    }
}
