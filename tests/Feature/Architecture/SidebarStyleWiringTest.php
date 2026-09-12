<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * **Toda clase que el cajón EMITE tiene una regla que la vista** (2026-08-23,
 * `docs/specs/account-context-vue.md`).
 *
 * ⚠️⚠️ **Nace de un fallo REAL y visible, y es el mismo cuño que `DECISIONES #113`.** Allí el cajón
 * se sirvió con 20 `<svg>` VACÍOS porque el diff de árbol no desciende dentro de un `<svg>`; aquí la
 * zona «Mis reservas» se servía **sin tarjetas** —cada pedido plano, sin borde, sin fondo y sin
 * padding— y la de privacidad con sus dos derechos pegados, porque la transcripción a Vue **inventó
 * nombres de clase** (`orders__card`, `orders__pagination`, `bk-error`, y un `auth` de envoltorio)
 * en vez de reutilizar los que las páginas retiradas ya tenían (`orders__item`, `pagination`,
 * `auth__errors`, `account__card`). **Ninguna tenía una sola regla en ninguna hoja.**
 *
 * ▶ **Y ningún gate podía verlo**: el contrato de árbol compara ESTRUCTURA —una clase sin regla es un
 * nodo idéntico a uno con ella—, el presupuesto de tokens mide la CALIDAD de las reglas que existen,
 * y el de iconos mira dentro de los `<svg>`. Nadie preguntaba lo más simple: si lo que se emite
 * significa algo.
 *
 * ⚠️ **Solo se miran clases ESTÁTICAS** (`class="…"`), nunca las de un binding (`:class`): aquéllas
 * se componen en ejecución —`'orders__status--' + row.status`— y exigirles una regla obligaría a
 * enumerar valores que solo existen en los datos.
 *
 * ⚠️ **Se ignora el INTERIOR de los `<svg>` pero no su etiqueta de apertura**: dentro, `class`
 * describe el dibujo (`<g class="body">`); en el `<svg>` mismo sí es un gancho de CSS —`.arrow-ico`
 * lo es—, y saltárselo abriría aquí el mismo punto ciego que la paridad de iconos tuvo con `glob`.
 */
class SidebarStyleWiringTest extends TestCase
{
    /** Las hojas que la página carga de verdad. Una regla en otro sitio no pinta nada. */
    private const STYLESHEETS = ['landing.css', 'site.css', 'spinner.css'];

    /**
     * Clases que se emiten a propósito SIN regla, con su motivo.
     *
     * ⚠️ **Es una lista con nombres, no una amnistía.** Lo que un gate declara que no mira es un
     * hueco con nombre (`TESTING.md` §2.quater); lo que no declara es un hueco a secas. Añadir una
     * entrada aquí es una decisión, y por eso cada una lleva escrito para qué sirve la clase si no es
     * para pintar.
     *
     * @var array<string, string>
     */
    private const NOT_FOR_STYLING = [
        // Centinelas del bundle: `SidebarBundleBudgetTest::ENGINE_MUST_KNOW` los busca en el chunk
        // construido para demostrar que esas pantallas siguen cableadas. Su trabajo es EXISTIR.
        'purchase__redirecting' => 'centinela del bundle: la pantalla que sale hacia la pasarela',
        'purchase__failed' => 'centinela del bundle: el pago denegado con su motivo',
        'purchase__verifying' => 'centinela del bundle: la espera del desenlace',

        // Envoltorio estructural compartido por el paso 5 y seis zonas del área. No pinta: el ritmo
        // lo ponen sus hijos (`auth__form`, `form__field`, `account__grid`). Se conserva porque es el
        // gancho por el que las tres pantallas de auth se reconocen entre sí.
        'auth' => 'envoltorio estructural del bloque de auth; el espaciado lo ponen sus hijos',

        // ⚠️ HUECOS REALES del EMBUDO, anteriores a este trabajo y NO tocados aquí a propósito: son
        // pantallas que el owner ya validó en navegador, y cambiarles el aspecto sin pedirlo sería
        // meter riesgo visual en un sitio que funciona. Quedan nombrados para que se decidan aparte.
        'cart__pending' => '⚠️ HUECO: modificador sin regla en el aviso de respuestas pendientes del carrito',
        // ⚠️ `catalog__per` SALIÓ de esta lista en `#552`, y el hueco no era inofensivo: sin regla propia
        // heredaba el `white-space: nowrap` de `.catalog__price`, así que «desde 14,95 € por niño» era
        // una sola línea irrompible y la columna del precio de un pack se llevaba **159 px de 324**,
        // dejando su descripción en 55 y recortada a mitad de palabra. Hoy tiene su regla y su línea.
        'addons__check' => '⚠️ HUECO: la casilla de un complemento',
        'addons__perguest-toggle' => '⚠️ HUECO: el conmutador de complemento por invitado',
        'addons__features--single' => '⚠️ HUECO: modificador de la lista de características',
        'purchase__note--reason' => '⚠️ HUECO: modificador del motivo del pago denegado',
    ];

    /**
     * ⚠️ **La guarda de la guarda.** Un escáner que no encontrara clases dejaría el caso de abajo
     * pasando solo y para siempre — es lo que le pasó al contador de `PurchaseRetirementTest`
     * (`DECISIONES #63`) y a la paridad de iconos, que miraba 22 de 32 componentes.
     */
    public function test_the_scan_actually_reads_the_drawer(): void
    {
        $emitted = $this->emittedClasses();

        $this->assertGreaterThan(200, count($emitted), 'el escaneo no está leyendo las plantillas del cajón');
        $this->assertArrayHasKey('acct__inner', $emitted, 'no ve el bloque de cuenta');
        $this->assertArrayHasKey('orders__item', $emitted, 'no ve la zona de «Mis reservas»');

        // ⚠️⚠️ **Y ve los MODIFICADORES de un `:class`, que es donde vive el caso principal de esta
        // guarda** (2026-08-23). `DECISIONES #124` encontró ocho clases servidas sin regla y varias
        // eran modificadores; un escáner que solo mirase el `class` estático estaría ciego justo
        // para ellas. Sin esta línea, la ampliación podría volverse inerte y nadie lo notaría.
        $this->assertArrayHasKey(
            'orders__item--past', $emitted,
            'el escaneo no ve los literales de un `:class`: los modificadores vuelven a ser un punto ciego'
        );

        // Y sigue SIN ver lo que de verdad se compone al vuelo, que es el hueco que sí declara.
        $this->assertArrayNotHasKey('pending', $emitted, 'un operando de comparación se está contando como clase');
    }

    /** Y que las hojas se lean de verdad: si el CSS llegara vacío, «todo tiene regla» sería trivial. */
    public function test_the_scan_actually_reads_the_stylesheets(): void
    {
        $this->assertGreaterThan(5000, mb_strlen($this->css()), 'las hojas no se están leyendo');
        $this->assertStringContainsString('.acct__inner', $this->css(), 'no ve una regla que sí existe');
    }

    public function test_every_class_the_drawer_emits_has_a_rule(): void
    {
        $css = $this->css();
        $orphans = [];

        foreach ($this->emittedClasses() as $class => $files) {
            if (isset(self::NOT_FOR_STYLING[$class])) {
                continue;
            }

            if (preg_match('/\.'.preg_quote($class, '/').'(?![\w-])/', $css) !== 1) {
                $orphans[] = sprintf('.%s  ← %s', $class, implode(', ', $files));
            }
        }

        $this->assertSame(
            [], $orphans,
            "El cajón emite clases que NO tienen ninguna regla en las hojas que la página carga:\n  ".
            implode("\n  ", $orphans)."\n\n".
            "⚠️ Eso no falla, no avisa y no se ve en ningún diff: el contrato de árbol compara\n".
            "ESTRUCTURA, y un nodo con una clase muerta es idéntico a uno con la clase buena. Así se\n".
            "sirvió «Mis reservas» sin tarjetas y privacidad con sus dos derechos pegados.\n\n".
            "▶ Antes de escribir CSS nuevo, MIRA SI YA EXISTE: casi todas las que faltaron tenían su\n".
            "regla escrita con otro nombre, el de la página que el cajón sustituye.\n".
            '▶ Si de verdad no es para pintar, DECLÁRALA en `NOT_FOR_STYLING` con su motivo.'
        );
    }

    /** Y al revés: una excepción que ya tiene regla es una excepción que sobra. */
    public function test_no_declared_exception_is_actually_styled(): void
    {
        $css = $this->css();
        $stale = [];

        foreach (self::NOT_FOR_STYLING as $class => $reason) {
            if (preg_match('/\.'.preg_quote($class, '/').'(?![\w-])/', $css) === 1) {
                $stale[] = ".{$class} — «{$reason}»";
            }
        }

        $this->assertSame(
            [], $stale,
            "Estas clases están declaradas como «no son para pintar» y SÍ tienen regla:\n  ".
            implode("\n  ", $stale)."\n\n".
            'Quita su entrada: una excepción que ya no describe nada acaba tapando el siguiente hueco.'
        );
    }

    // ── Herramientas ──────────────────────────────────────────────────────────────────────────

    private function css(): string
    {
        $css = '';

        foreach (self::STYLESHEETS as $file) {
            $path = public_path('css/'.$file);
            $this->assertFileExists($path);
            $css .= "\n".file_get_contents($path);
        }

        return $css;
    }

    /**
     * Las clases estáticas que emiten las plantillas del cajón: clase → ficheros que la emiten.
     *
     * @return array<string, list<string>>
     */
    private function emittedClasses(): array
    {
        $root = resource_path('js/sidebar');
        $found = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'vue') {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());
            $template = mb_substr($source, (int) mb_strpos($source, '<template>'));

            // Fuera los comentarios: citan clases al explicar por qué las cosas son como son.
            $template = (string) preg_replace('/<!--.*?-->/s', '', $template);

            // ⚠️ Y fuera el INTERIOR de los `<svg>`, pero **no su etiqueta de apertura**. Dentro,
            // `class` describe el dibujo (`<g class="body">`) y no engancha con ninguna hoja; en el
            // `<svg>` mismo sí es un gancho de CSS —`.arrow-ico` lo es— y saltárselo abriría en esta
            // guarda el mismo punto ciego que la paridad de iconos tenía con `glob('**')`.
            $template = (string) preg_replace('/(<svg\b[^>]*>).*?<\/svg>/s', '$1</svg>', $template);

            $name = basename($file->getPathname());

            // El `class` estático de siempre.
            preg_match_all('/(?<![:\w-])class="([^"{}]+)"/', $template, $matches);

            foreach ($matches[1] as $attribute) {
                foreach (preg_split('/\s+/', trim($attribute)) ?: [] as $class) {
                    if ($class !== '') {
                        $found[$class] = array_values(array_unique([...($found[$class] ?? []), $name]));
                    }
                }
            }

            // ⚠️⚠️ **Y los LITERALES de un `:class`, que hasta el 2026-08-23 este escáner no miraba**
            // (`specs/mis-reservas-por-reserva.md` §4.4). Su comentario declaraba el hueco —«esas se
            // componen en ejecución»— y eso es cierto solo a medias: `:class="{ 'orders__item--past':
            // dimmed }"` lleva el nombre **escrito entero**, y es exactamente la forma en que se
            // emite un MODIFICADOR — que es lo que `DECISIONES #124` encontró servido sin regla.
            // Dejarlo fuera convertía esta guarda en ciega justo para su caso principal.
            // ▶ Lo que sigue fuera es lo que de verdad se compone: `'orders__status--' + estado`
            // no tiene nombre completo en ninguna parte, así que un literal acabado en `-` se
            // descarta por ser un PREFIJO y no una clase. Es la parte que el gate declara que no
            // mira, ahora acotada a lo que de verdad no puede mirar.
            foreach ($this->boundClassLiterals($template) as $class) {
                $found[$class] = array_values(array_unique([...($found[$class] ?? []), $name]));
            }
        }

        ksort($found);

        return $found;
    }

    /**
     * Los nombres de clase escritos ENTEROS dentro de un `:class` / `v-bind:class`.
     *
     * ⚠️ Se descarta lo que acaba en `-`: es un prefijo de una clase que se compone al vuelo
     * (`'orders__status--' + estado`), y exigirle regla obligaría a enumerar valores que solo están
     * en los datos. Y se descarta lo que no parece una clase —espacios, mayúsculas, rutas— para no
     * arrastrar cadenas sueltas de una expresión.
     *
     * @return list<string>
     */
    private function boundClassLiterals(string $template): array
    {
        preg_match_all('/(?::|v-bind:)class="([^"]*)"/', $template, $bound);

        $classes = [];

        foreach ($bound[1] as $expression) {
            // ⚠️⚠️ **Se acota por POSICIÓN sintáctica, no por aspecto.** Una expresión de `:class`
            // lleva literales que NO son clases: `estado === 'pending' ? …` compara, no pinta. La
            // primera versión de esto los recogía todos y declaraba huérfanas `.login`, `.register`
            // y `.pending` —tres operandos—, que es peor que no mirar: un test que grita por algo
            // que no existe se acaba silenciando entero.
            // ▶ Un literal es una clase si es **clave de objeto** (`{ 'x': cond }`) o **rama de un
            //   ternario** (`cond ? 'x' : 'y'`). Un operando de comparación no es ninguna de las dos.
            preg_match_all("/'([^']+)'\s*:/", $expression, $keys);
            preg_match_all("/[?:]\s*'([^']+)'/", $expression, $branches);

            foreach ([...$keys[1], ...$branches[1]] as $literal) {
                if (preg_match('/^[a-z][a-z0-9]*(?:[-_]+[a-z0-9]+)*$/', $literal) === 1 && ! str_ends_with($literal, '-')) {
                    $classes[] = $literal;
                }
            }
        }

        return array_values(array_unique($classes));
    }
}
