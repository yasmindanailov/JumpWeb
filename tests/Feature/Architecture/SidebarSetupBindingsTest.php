<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * **Dos fallos de `<script setup>` que Vue TRAGA y ninguna otra guarda del cajón puede ver**
 * (2026-08-28, `DECISIONES #210`). Los dos los encontró el OJO del owner en localhost, los dos
 * llevaban días en `main` con la suite en verde, y los dos son de la misma familia: **el orden y el
 * nombre de los bindings de un `<script setup>` deciden conducta, y el compilador no avisa.**
 *
 * ### 1 · Un binding con el nombre de una prop la SOMBREA en la plantilla
 *
 * `sections/AccountSection.vue` declaraba la prop `auth` —el diccionario con los literales del «no»
 * del servidor— y a la vez `const auth = useAuthStore()`. En la plantilla, `:auth="auth"` resolvía a
 * la CONSTANTE (el store), así que las ocho zonas recibían un store donde esperaban un diccionario,
 * `t(store, 'failed')` devolvía `''` y el `v-if` no pintaba nada. El owner pulsaba «Iniciar sesión»
 * con una contraseña mala y **no ocurría nada** — ni «credenciales incorrectas» ni el aviso del
 * limitador— desde el 2026-08-23. Ni el árbol (descarta el texto, y el área no tiene casos de
 * contrato) ni `node --test` (los `.vue` no se prueban) ni las paridades de texto (comparan CLAVES)
 * podían verlo. ⚠️ **Un `import` sombrea igual que una `const`** —medido con el `@vue/compiler-sfc`
 * del repo en la revisión de `#210`: `bindings.auth = setup-maybe-ref` en los dos casos, `props` en
 * el control—, y por eso los imports también se cruzan con las props.
 *
 * ### 2 · Un `watch` de nivel superior que lee una constante declarada DEBAJO
 *
 * `sections/PurchaseSection.vue` tenía `watch(() => cartStore.owner, …, { immediate: true })` quince
 * líneas por ENCIMA de `const cartStore = useCartStore()`. Es un TDZ: el getter lanza
 * `ReferenceError`, Vue lo captura, lo escribe en consola y **llama al callback con `undefined`**
 * —que `!== null`—, así que la carga de menores saltaba UNA vez para todo el mundo (un
 * `GET /me/dependents → 401` por visitante anónimo) y el observador nacía sin dependencias: nunca
 * volvía a dispararse. Con un `watch` sin `immediate` pasa lo mismo con el getter: Vue lo evalúa al
 * crearlo para recoger dependencias.
 *
 * ### Qué mide, y hasta dónde
 *
 * Es un ESCÁNER del `<script setup>` de cada componente —no un parser—, en tres pasadas que la
 * revisión adversarial de `#210` obligó a endurecer (seis huecos, todos con su muestra abajo):
 *  · los comentarios se quitan **sabiendo de cadenas** (un `'/*'` o un `' // '` dentro de una
 *    cadena no es un comentario), y las cadenas se vacían antes de mirar la estructura;
 *  · los bindings de **profundidad 0** —`const`/`let`/`var` con desestructuración (también
 *    multilínea y de array), varios declaradores, `let x;`, `function` e `import` (default, `* as`,
 *    `{ a, b as c }`)— se leen recorriendo llaves, paréntesis y corchetes, no línea a línea;
 *  · por cada `watch`/`watchEffect`/`watchSyncEffect` de profundidad 0 —también `const stop =
 *    watch(…)`— los identificadores de su getter, y de la llamada ENTERA si `immediate` no es
 *    `false` (`{ immediate: true }`, `{immediate:true}`, `{ immediate }`), se cruzan con las
 *    `const`/`let` declaradas en una línea posterior. No cuentan las propiedades (`store.user`),
 *    las cadenas, los parámetros de las funciones del ámbito ni las claves de objeto.
 *  · y lo mismo en el CUERPO de cada `export function use…` de un módulo `.js` (`#691`): un
 *    composable corre dentro del setup de quien lo llama, y la secuencia de compra vive en uno.
 * `test_the_scanner_sees_both_families` es la guarda de la guarda: cada forma de fallo escrita a
 * mano tiene que ser cazada, y cada forma correcta que se parece a un fallo tiene que pasar. Sin
 * ella, un escáner que no encontrara el bloque `<script setup>` pasaría los dos casos en verde.
 *
 * ▶ Lo que NO cubre, dicho aquí: una `function` (izada) que lea una constante posterior y se llame
 * desde un `watch` inmediato; las referencias dentro de un callback NO inmediato (corren después de
 * montar, no son un TDZ); `watchPostEffect` (su primera vuelta es tras el render); un ternario en el
 * getter (el token antes de `:` se toma por clave de objeto); declaraciones dentro de bloques
 * (`if { const … }` a profundidad 0 no es un binding de setup); y literales de regex con comillas
 * dentro. Si hace falta más, el camino es ESLint (`no-use-before-define` + `vue/no-dupe-keys`), que
 * es una dependencia nueva en el gate y una decisión aparte (`DEUDA.md`).
 */
class SidebarSetupBindingsTest extends TestCase
{
    public function test_no_setup_binding_shadows_a_declared_prop(): void
    {
        $offenders = [];

        foreach ($this->components() as $relative => $path) {
            foreach ($this->shadowedProps($path) as $offence) {
                $offenders[] = "{$relative}:{$offence}";
            }
        }

        $this->assertSame(
            [], $offenders,
            "Hay componentes del cajón con un binding que SOMBREA una prop en la plantilla:\n  ".
            implode("\n  ", $offenders)."\n\n".
            "⚠️ En `<script setup>` una constante, una función o un import con el nombre de una prop gana\n".
            'en la plantilla, sin aviso. Renombra el binding (`authStore`, no `auth`): la prop es el '.
            'contrato con quien te monta.'
        );
    }

    public function test_no_watcher_reads_a_binding_declared_below_it(): void
    {
        $offenders = [];

        foreach ($this->components() as $relative => $path) {
            foreach ($this->watchersBeforeTheirBindings($path) as $offence) {
                $offenders[] = "{$relative}:{$offence}";
            }
        }

        // ⚠️ **Y el cuerpo de cada composable** (`export function use…`), que corre DENTRO del setup de
        // quien lo llama: el mismo TDZ, tragado igual. Desde `#691` la secuencia de compra vive en
        // `sidebar/usePurchaseFlow.js`, con el `watch` inmediato sobre el titular que dio nombre a esta
        // guarda; dejar el módulo fuera habría sido llevarse el fallo a donde nadie mira.
        foreach ($this->composables() as $relative => $path) {
            foreach ($this->composableWatchersBeforeTheirBindings($path) as $offence) {
                $offenders[] = "{$relative}:{$offence}";
            }
        }

        $this->assertSame(
            [], $offenders,
            "Hay `watch` de nivel superior que leen una constante declarada DEBAJO (TDZ):\n  ".
            implode("\n  ", $offenders)."\n\n".
            "⚠️ Vue evalúa el getter al crear el observador —y el callback, con `immediate`—, captura el\n".
            "`ReferenceError`, lo escribe en consola y sigue con `undefined`. El observador nace SIN\n".
            'dependencias y no vuelve a dispararse. Mueve el `watch` debajo de la declaración.'
        );
    }

    /**
     * **La guarda de la guarda.** Cada forma del fallo, escrita a mano, tiene que ser cazada; y cada
     * forma correcta que se le parece tiene que pasar. Las muestras recogen los seis huecos que la
     * revisión adversarial de `#210` encontró en la primera versión del escáner: un `import` que
     * sombrea, la desestructuración multilínea y de array, `let x;` y varios declaradores, `const
     * stop = watch(…)` y `watchSyncEffect`, `{immediate:true}` y `{ immediate }`, las cadenas con
     * `/*` o `//` dentro, y los falsos positivos por propiedad, cadena y parámetro.
     */
    public function test_the_scanner_sees_both_families(): void
    {
        $this->assertNotSame([], $this->components(), 'el escaneo no encuentra ningún componente');

        $shadowing = <<<'VUE'
            <script setup>
            import { useAuthStore } from '../stores/auth.js';
            import { messages, tp as urls2 } from '../i18n.js';

            /** El grupo `auth`, podado: un comentario con dos puntos no es una clave. */
            const props = defineProps({
                messages: { type: Object, default: () => ({}) },
                auth: { type: Object, default: () => ({}) },
                urls: { type: Object, default: () => ({}) },
                locale: { type: String, default: 'es' },
                ui: { type: Object, default: () => ({}) },
                account: { type: Object, default: () => ({}) },
            });

            const marker = '/*';
            const auth = useAuthStore();
            function urls() { return props.urls; }
            const {
                locale,
            } = useLocaleStore();
            const [ui] = [{}];
            let account;
            </script>

            <template><LoginZone :auth="auth" :messages="messages" /></template>
            VUE;

        $tdz = <<<'VUE'
            <script setup>
            import { watch, watchSyncEffect } from 'vue';
            const label = ' // no es un comentario ';
            watch(() => cartStore.owner, (owner) => { if (owner !== null) load(); }, { immediate: true });
            watch(() => store.step, () => {});
            watch(ready, () => { later.value = 1; });
            watch(() => store.user, (user) => { form.value = user; });
            watch(() => store.flag('ready2'), () => {});
            watch(() => store.owner, () => { seen.value = true; }, {immediate:true});
            watch(() => store.owner, () => { seen2.value = true; }, { immediate });
            const stop = watch(() => handle.value, () => {});
            watchSyncEffect(() => { sync.value; });
            // Un `watch` dentro de una función corre después de montar: no es un TDZ.
            function boot() {
                watch(() => cartStore.lines, () => {});
            }

            const cartStore = useCartStore();
            const store = usePurchaseStore();
            const ready = ref(false);
            const later = ref(0);
            const user = computed(() => store.user);
            const ready2 = ref(false);
            const seen = ref(false);
            const seen2 = ref(false);
            const handle = ref(null);
            const sync = ref(0);
            const immediate = true;
            </script>

            <template><div /></template>
            VUE;

        $clean = <<<'VUE'
            <script setup>
            import { watch } from 'vue';
            import { useAuthStore } from '../stores/auth.js';

            const props = defineProps({
                auth: { type: Object, default: () => ({}) },
                messages: { type: Object, default: () => ({}) },
            });

            const authStore = useAuthStore();
            const cartStore = useCartStore();
            const later = ref(0);

            watch(() => cartStore.owner, (owner) => { later.value = owner; }, { immediate: true });
            watch(() => props.messages.title, (title) => { later.value = title; });
            const title = computed(() => props.messages.title);
            </script>

            <template><LoginZone :auth="auth" /></template>
            VUE;

        $this->assertSame(
            [
                '3: el import `messages` sombrea la prop `messages`',
                '16: la constante `auth` sombrea la prop `auth`',
                '17: la función `urls` sombrea la prop `urls`',
                '18: la constante `locale` sombrea la prop `locale`',
                '21: la constante `ui` sombrea la prop `ui`',
                '22: la constante `account` sombrea la prop `account`',
            ],
            $this->withSample($shadowing, fn (string $path): array => $this->shadowedProps($path)),
            'el escáner no ve alguna forma de binding con el nombre de una prop (o una cadena con `/*` se lo ha comido)'
        );

        $this->assertSame(
            [
                '4: el `watch` lee `cartStore`, declarada en la línea 18',
                '5: el `watch` lee `store`, declarada en la línea 19',
                '6: el `watch` lee `ready`, declarada en la línea 20',
                '7: el `watch` lee `store`, declarada en la línea 19',
                '8: el `watch` lee `store`, declarada en la línea 19',
                '9: el `watch` lee `store`, declarada en la línea 19',
                '9: el `watch` lee `seen`, declarada en la línea 24',
                '10: el `watch` lee `store`, declarada en la línea 19',
                '10: el `watch` lee `seen2`, declarada en la línea 25',
                '10: el `watch` lee `immediate`, declarada en la línea 28',
                '11: el `watch` lee `handle`, declarada en la línea 26',
                '12: el `watch` lee `sync`, declarada en la línea 27',
            ],
            $this->withSample($tdz, fn (string $path): array => $this->watchersBeforeTheirBindings($path)),
            'el escáner no ve alguna forma de `watch` que lea una constante declarada debajo, o cuenta '.
            'una propiedad, una cadena o un parámetro como si fuera una referencia'
        );

        $this->assertSame([], $this->withSample($clean, fn (string $path): array => $this->shadowedProps($path)));
        $this->assertSame([], $this->withSample($clean, fn (string $path): array => $this->watchersBeforeTheirBindings($path)));

        // El composable: su CUERPO es el setup. El primero lee su store debajo del `watch`; el segundo
        // es la forma correcta —el store arriba, una función izada y un parámetro— y tiene que pasar.
        $composable = <<<'JS'
            import { watch } from 'vue';

            /** Un comentario con `watch(() => cartStore.owner` dentro no es un observador. */
            export function useMal(props) {
                watch(() => cartStore.owner, (owner) => { if (owner !== null) load(); }, { immediate: true });
                const cartStore = useCartStore();
            }

            export function useBien(props, { isOpen }) {
                const cartStore = useCartStore();
                watch(() => cartStore.owner, () => { stop(); }, { immediate: true });
                watch(() => props.step + isOpen.value, () => {});
                function stop() {}
            }
            JS;

        $this->assertSame(
            ['5: el `watch` lee `cartStore`, declarada en la línea 6'],
            $this->withSample($composable, fn (string $path): array => $this->composableWatchersBeforeTheirBindings($path), '.js'),
            'el escáner no ve el TDZ dentro de un composable, o confunde un parámetro, una función izada '.
            'o un comentario con una lectura'
        );
    }

    /**
     * Bindings de profundidad 0 que llevan el nombre de una prop declarada.
     *
     * @return list<string> `línea: mensaje`, con la línea del FICHERO
     */
    private function shadowedProps(string $path): array
    {
        [$script, $offset] = $this->scriptSetup($path);

        if ($script === null) {
            return [];
        }

        $props = $this->propNames($script);
        $offences = [];

        foreach ($this->topLevelBindings($script) as $name => $binding) {
            if (! in_array($name, $props, true)) {
                continue;
            }

            $kind = ['function' => 'la función', 'import' => 'el import'][$binding['kind']] ?? 'la constante';
            $offences[] = ($binding['line'] + $offset).": {$kind} `{$name}` sombrea la prop `{$name}`";
        }

        return $offences;
    }

    /**
     * `watch`/`watchEffect`/`watchSyncEffect` de profundidad 0 cuyo getter —o cuya llamada entera,
     * con `immediate`— lee una `const`/`let` de profundidad 0 declarada en una línea posterior.
     *
     * @return list<string> `línea: mensaje`, con la línea del FICHERO
     */
    private function watchersBeforeTheirBindings(string $path): array
    {
        [$script, $offset] = $this->scriptSetup($path);

        if ($script === null) {
            return [];
        }

        return $this->tdzOffences($script, $offset);
    }

    /**
     * Lo mismo, en el cuerpo de cada `export function use…` de un módulo `.js`.
     *
     * @return list<string> `línea: mensaje`, con la línea del FICHERO
     */
    private function composableWatchersBeforeTheirBindings(string $path): array
    {
        $offences = [];

        foreach ($this->composableBodies($path) as [$body, $offset]) {
            array_push($offences, ...$this->tdzOffences($body, $offset));
        }

        return $offences;
    }

    /**
     * El cruce de los dos: cada `watch` de profundidad 0 contra las `const`/`let` declaradas debajo.
     *
     * @return list<string>
     */
    private function tdzOffences(string $script, int $offset): array
    {
        // Las funciones y los imports se izan: leerlos antes de su línea no es un TDZ.
        $bindings = array_filter(
            $this->topLevelBindings($script),
            fn (array $binding): bool => $binding['kind'] === 'const',
        );
        $offences = [];

        foreach ($this->topLevelWatchers($script) as $watcher) {
            $scope = $this->runsAtSetup($watcher['call']) ? $watcher['call'] : $watcher['getter'];

            foreach ($this->referencedNames($scope) as $name) {
                if (isset($bindings[$name]) && $bindings[$name]['line'] > $watcher['line']) {
                    $offences[] = ($watcher['line'] + $offset).": el `watch` lee `{$name}`, declarada en la línea ".($bindings[$name]['line'] + $offset);
                }
            }
        }

        return $offences;
    }

    // ── El escáner ────────────────────────────────────────────────────────────────────────────

    /**
     * El bloque `<script setup>` SIN comentarios y el número de líneas que lo preceden en el fichero.
     *
     * @return array{0: string|null, 1: int}
     */
    private function scriptSetup(string $path): array
    {
        $source = (string) file_get_contents($path);

        if (! preg_match('#<script\b[^>]*\bsetup\b[^>]*>(.*?)</script>#s', $source, $match, PREG_OFFSET_CAPTURE)) {
            return [null, 0];
        }

        return [$this->withoutComments($match[1][0]), substr_count(substr($source, 0, $match[1][1]), "\n")];
    }

    /**
     * El cuerpo de cada `export function use…(…) { … }` de un módulo, SIN comentarios, y el número de
     * líneas que lo preceden: a efectos del TDZ, ese cuerpo es el `<script setup>` de quien lo llama.
     * Los dos pasos de limpieza conservan la longitud, así que las posiciones valen en los tres textos.
     *
     * @return list<array{0: string, 1: int}>
     */
    private function composableBodies(string $path): array
    {
        $source = (string) file_get_contents($path);
        $clean = $this->withoutComments($source);
        $flat = $this->withoutStrings($clean);
        $bodies = [];

        preg_match_all('/\bexport\s+(?:async\s+)?function\s+use\w*\s*\([^)]*\)\s*\{/', $flat, $headers, PREG_OFFSET_CAPTURE);

        foreach ($headers[0] as [$header, $at]) {
            $open = $at + strlen($header);
            $bodies[] = [substr($clean, $open, strlen($this->callArguments($flat, $open))), substr_count($source, "\n", 0, $open)];
        }

        return $bodies;
    }

    /**
     * Comentarios → blancos, conservando los saltos de línea (las líneas que se reportan son las del
     * fichero) y **sin tocar el interior de las cadenas**: un `'/*'` o un `' // '` dentro de una cadena
     * no abre un comentario. Una pasada con tres estados: código, cadena, comentario.
     */
    private function withoutComments(string $script): string
    {
        $out = '';

        for ($i = 0, $n = strlen($script); $i < $n; $i++) {
            $char = $script[$i];
            $next = $script[$i + 1] ?? '';

            if ($char === "'" || $char === '"' || $char === '`') {
                $out .= $char;

                for ($i++; $i < $n; $i++) {
                    $out .= $script[$i];

                    if ($script[$i] === '\\') {
                        $out .= $script[++$i] ?? '';
                    } elseif ($script[$i] === $char) {
                        break;
                    }
                }

                continue;
            }

            if ($char === '/' && $next === '*') {
                $end = strpos($script, '*/', $i + 2);
                $end = $end === false ? $n : $end + 2;
                $out .= preg_replace('/[^\n]/', ' ', substr($script, $i, $end - $i));
                $i = $end - 1;

                continue;
            }

            if ($char === '/' && $next === '/') {
                $end = strpos($script, "\n", $i);
                $end = $end === false ? $n : $end;
                $out .= str_repeat(' ', $end - $i);
                $i = $end - 1;

                continue;
            }

            $out .= $char;
        }

        return $out;
    }

    /** El contenido de cada cadena → blancos (las comillas se quedan): la estructura, sin su texto. */
    private function withoutStrings(string $script): string
    {
        $out = '';

        for ($i = 0, $n = strlen($script); $i < $n; $i++) {
            $char = $script[$i];
            $out .= $char;

            if ($char !== "'" && $char !== '"' && $char !== '`') {
                continue;
            }

            for ($i++; $i < $n; $i++) {
                $inner = $script[$i];

                if ($inner === '\\') {
                    $out .= '  ';
                    $i++;
                } elseif ($inner === $char) {
                    $out .= $inner;
                    break;
                } else {
                    $out .= $inner === "\n" ? "\n" : ' ';
                }
            }
        }

        return $out;
    }

    /**
     * Las claves de primer nivel del objeto de `defineProps({…})`.
     *
     * @return list<string>
     */
    private function propNames(string $script): array
    {
        $start = strpos($script, 'defineProps(');
        $open = $start === false ? false : strpos($script, '{', $start);

        if ($open === false) {
            return [];
        }

        // Solo el texto a profundidad 1 del objeto: las claves de un `default: () => ({...})` o de un
        // validador anidado no son props.
        $depth = 0;
        $topLevel = '';

        for ($i = $open, $length = strlen($script); $i < $length; $i++) {
            $char = $script[$i];

            if ($char === '{' || $char === '(' || $char === '[') {
                $depth++;
            } elseif ($char === '}' || $char === ')' || $char === ']') {
                if (--$depth === 0) {
                    break;
                }
            } elseif ($depth === 1) {
                $topLevel .= $char;
            }
        }

        preg_match_all('/(?:^|[,\s])[\'"]?([A-Za-z_$][\w$-]*)[\'"]?\s*:/', $topLevel, $keys);

        return array_values(array_unique($keys[1]));
    }

    /**
     * Los bindings declarados a PROFUNDIDAD 0 del script —`const`/`let`/`var` (con desestructuración y
     * varios declaradores), `function` e `import`— con su línea (1-based dentro del script) y su clase.
     *
     * @return array<string, array{line: int, kind: string}>
     */
    private function topLevelBindings(string $script): array
    {
        $flat = $this->withoutStrings($script);
        $bindings = [];
        $depth = 0;

        for ($i = 0, $n = strlen($flat); $i < $n; $i++) {
            $char = $flat[$i];

            if ($char === '{' || $char === '(' || $char === '[') {
                $depth++;

                continue;
            }

            if ($char === '}' || $char === ')' || $char === ']') {
                $depth = max(0, $depth - 1);

                continue;
            }

            if ($depth !== 0 || ! ctype_alpha($char) || ($i > 0 && preg_match('/[\w$.]/', $flat[$i - 1]))) {
                continue;
            }

            if (! preg_match('/(?:(const|let|var)\s+|(?:async\s+)?(function)\s+([A-Za-z_$][\w$]*)|(import)\b)/A', $flat, $m, 0, $i)) {
                continue;
            }

            $line = substr_count($flat, "\n", 0, $i) + 1;

            if (($m[2] ?? '') === 'function') {
                $bindings[$m[3]] = ['line' => $line, 'kind' => 'function'];

                continue;
            }

            $statement = $this->statementFrom($flat, $i + strlen($m[0]));
            $kind = ($m[4] ?? '') === 'import' ? 'import' : 'const';
            $names = $kind === 'import' ? $this->importedNames($statement) : $this->declaredNames($statement);

            foreach ($names as $name) {
                $bindings[$name] = ['line' => $line, 'kind' => $kind];
            }
        }

        return $bindings;
    }

    /**
     * Los `watch(`/`watchEffect(`/`watchSyncEffect(` de profundidad 0 —también los que guardan su
     * handle, `const stop = watch(…)`—, con la línea, el texto de su PRIMER argumento y el de la
     * llamada entera.
     *
     * @return list<array{line: int, getter: string, call: string}>
     */
    private function topLevelWatchers(string $script): array
    {
        $flat = $this->withoutStrings($script);
        $watchers = [];
        $depth = 0;

        for ($i = 0, $n = strlen($flat); $i < $n; $i++) {
            $char = $flat[$i];

            if ($char === '{' || $char === '(' || $char === '[') {
                $depth++;

                continue;
            }

            if ($char === '}' || $char === ')' || $char === ']') {
                $depth = max(0, $depth - 1);

                continue;
            }

            if ($depth !== 0 || $char !== 'w' || ($i > 0 && preg_match('/[\w$.]/', $flat[$i - 1]))) {
                continue;
            }

            if (! preg_match('/(?:watch|watchEffect|watchSyncEffect)\s*\(/A', $flat, $m, 0, $i)) {
                continue;
            }

            $call = $this->callArguments($flat, $i + strlen($m[0]));
            $watchers[] = [
                'line' => substr_count($flat, "\n", 0, $i) + 1,
                'getter' => $this->splitAtDepthZero($call, ',')[0] ?? $call,
                'call' => $call,
            ];
        }

        return $watchers;
    }

    /** ¿Corre el callback en el setup? `immediate` con cualquier valor que no sea `false`, o en forma corta. */
    private function runsAtSetup(string $call): bool
    {
        return preg_match('/\bimmediate\b\s*(?::\s*(?!false\b)|[,}])/', $call) === 1;
    }

    /**
     * Los identificadores que un ámbito LEE: fuera las propiedades (`store.user`, `a?.b`), las claves de
     * objeto (`immediate: true`) y los parámetros de las funciones que el propio ámbito declara. Las
     * cadenas ya llegan vacías.
     *
     * @return list<string>
     */
    private function referencedNames(string $scope): array
    {
        $params = [];

        preg_match_all('/\(([^()]*)\)\s*=>/', $scope, $arrows);
        preg_match_all('/\bfunction\b[^(]*\(([^()]*)\)/', $scope, $functions);
        preg_match_all('/(?<![\w$.)\]])([A-Za-z_$][\w$]*)\s*=>/', $scope, $singles);

        foreach ([...$arrows[1], ...$functions[1]] as $list) {
            preg_match_all('/[A-Za-z_$][\w$]*/', $list, $names);
            array_push($params, ...$names[0]);
        }

        array_push($params, ...$singles[1]);

        preg_match_all('/(?<![\w$.])([A-Za-z_$][\w$]*)\b(?!\s*:)/', $scope, $identifiers);

        return array_values(array_diff(array_unique($identifiers[1]), $params));
    }

    /** El texto desde `$start` hasta el `;` a profundidad 0 (o el final). */
    private function statementFrom(string $flat, int $start): string
    {
        $depth = 0;

        for ($i = $start, $n = strlen($flat); $i < $n; $i++) {
            $char = $flat[$i];

            if ($char === '{' || $char === '(' || $char === '[') {
                $depth++;
            } elseif ($char === '}' || $char === ')' || $char === ']') {
                $depth--;
            } elseif ($char === ';' && $depth <= 0) {
                break;
            }
        }

        return substr($flat, $start, $i - $start);
    }

    /** Los argumentos de una llamada: desde justo después de `(` hasta su `)` de cierre. */
    private function callArguments(string $flat, int $start): string
    {
        $depth = 0;

        for ($i = $start, $n = strlen($flat); $i < $n; $i++) {
            $char = $flat[$i];

            if ($char === '(' || $char === '{' || $char === '[') {
                $depth++;
            } elseif ($char === ')' || $char === '}' || $char === ']') {
                if ($depth === 0) {
                    break;
                }

                $depth--;
            }
        }

        return substr($flat, $start, $i - $start);
    }

    /**
     * Parte un texto por `$separator` solo a profundidad 0.
     *
     * @return list<string>
     */
    private function splitAtDepthZero(string $text, string $separator): array
    {
        $parts = [];
        $current = '';
        $depth = 0;

        for ($i = 0, $n = strlen($text); $i < $n; $i++) {
            $char = $text[$i];

            if ($char === '{' || $char === '(' || $char === '[') {
                $depth++;
            } elseif ($char === '}' || $char === ')' || $char === ']') {
                $depth--;
            } elseif ($char === $separator && $depth === 0) {
                $parts[] = $current;
                $current = '';

                continue;
            }

            $current .= $char;
        }

        $parts[] = $current;

        return $parts;
    }

    /**
     * Los nombres que declara una sentencia `const`/`let`/`var` (sin la palabra clave): `a = 1, b`,
     * `{ a, b: c = 1, ...rest } = x`, `[a, , b] = y`, `x;`.
     *
     * @return list<string>
     */
    private function declaredNames(string $statement): array
    {
        $names = [];

        foreach ($this->splitAtDepthZero($statement, ',') as $declarator) {
            $declarator = trim($declarator);

            if ($declarator === '') {
                continue;
            }

            if ($declarator[0] !== '{' && $declarator[0] !== '[') {
                if (preg_match('/^([A-Za-z_$][\w$]*)/', $declarator, $m)) {
                    $names[] = $m[1];
                }

                continue;
            }

            $close = $declarator[0] === '{' ? strrpos($declarator, '}') : strrpos($declarator, ']');
            $inner = substr($declarator, 1, ($close === false ? strlen($declarator) : $close) - 1);

            foreach ($this->splitAtDepthZero($inner, ',') as $part) {
                $part = trim($part);

                if ($part === '' || preg_match('/^[\w$\'"]+\s*:\s*[\[{]/', $part)) {
                    continue; // hueco de un array, o un patrón anidado (no se baja)
                }

                if (preg_match('/^\.\.\.\s*([A-Za-z_$][\w$]*)/', $part, $m)) {
                    $names[] = $m[1];
                } elseif ($declarator[0] === '{' && preg_match('/^[\'"]?[\w$]+[\'"]?\s*:\s*([A-Za-z_$][\w$]*)/', $part, $m)) {
                    $names[] = $m[1]; // `clave: local`
                } elseif (preg_match('/^([A-Za-z_$][\w$]*)/', $part, $m)) {
                    $names[] = $m[1];
                }
            }
        }

        return $names;
    }

    /**
     * Los nombres LOCALES que declara un `import` (sin la palabra clave): el default, `* as ns` y los
     * de `{ a, b as c }`. Un `import 'x'` de efectos o un `import('x')` dinámico no declaran nada.
     *
     * @return list<string>
     */
    private function importedNames(string $statement): array
    {
        $clause = trim((string) preg_replace('/\bfrom\b.*$/s', '', $statement));

        if ($clause === '' || $clause[0] === '(' || $clause[0] === "'" || $clause[0] === '"') {
            return [];
        }

        $names = [];

        if (preg_match('/^([A-Za-z_$][\w$]*)/', $clause, $m)) {
            $names[] = $m[1];
        }

        if (preg_match('/\*\s*as\s+([A-Za-z_$][\w$]*)/', $clause, $m)) {
            $names[] = $m[1];
        }

        if (preg_match('/\{(.*)\}/s', $clause, $m)) {
            foreach (explode(',', $m[1]) as $specifier) {
                $specifier = trim($specifier);

                if (preg_match('/\bas\s+([A-Za-z_$][\w$]*)$/', $specifier, $a)) {
                    $names[] = $a[1];
                } elseif (preg_match('/^([A-Za-z_$][\w$]*)$/', $specifier, $a)) {
                    $names[] = $a[1];
                }
            }
        }

        return $names;
    }

    /**
     * Los componentes del cajón, por ruta relativa a `resources/js/`. Mismo barrido que
     * `SidebarComponentBudgetTest`.
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
     * Los módulos `.js` con algún `export function use…` (sin sus `.test.js`), por ruta relativa a
     * `resources/js/`.
     *
     * @return array<string, string>
     */
    private function composables(): array
    {
        $root = resource_path('js');
        $found = [];

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            $path = $file->getPathname();

            if ($file->getExtension() !== 'js' || str_ends_with($path, '.test.js')) {
                continue;
            }

            if (preg_match('/\bexport\s+(?:async\s+)?function\s+use\w*\s*\(/', (string) file_get_contents($path))) {
                $found[str_replace($root.DIRECTORY_SEPARATOR, '', $path)] = $path;
            }
        }

        ksort($found);

        return $found;
    }

    /**
     * Escribe una muestra en un fichero temporal, la escanea y lo borra.
     *
     * @template T
     *
     * @param  callable(string): T  $scan
     * @return T
     */
    private function withSample(string $source, callable $scan, string $extension = '.vue'): mixed
    {
        $path = tempnam(sys_get_temp_dir(), 'vue').$extension;
        file_put_contents($path, $source);

        try {
            return $scan($path);
        } finally {
            @unlink($path);
        }
    }
}
