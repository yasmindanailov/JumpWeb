<?php

namespace Tests\Feature\Sidebar;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Fase 4 · paso 4.7·2b·4 — **los DIBUJOS de los iconos del cajón**, que el diff de árbol NO puede ver.
 *
 * ⚠️⚠️ **Este fichero nace de un fallo REAL y visible**: el cajón SPA se sirvió con **20 `<svg>`
 * VACÍOS** —todos sus iconos— y ningún gate se enteró. La transcripción de Fase 4 replicó el ÁRBOL y
 * no el dibujo, a propósito y con el motivo escrito en `CatalogStep.vue`: «el interior del `<svg>` es
 * geometría y el diff no desciende en él». Es literalmente cierto —`SidebarDomContractTest::describe()`
 * hace `return` al llegar a un `<svg>`, y por buenas razones: exigir que dos motores emitan los mismos
 * `<path>` convertiría un contrato visual en una copia literal— y por eso mismo un `<svg>` vacío y uno
 * lleno son **el mismo nodo** para el manifiesto congelado.
 *
 * Es la regla que `ESTADO.md` ya tenía escrita, aplicada a un caso nuevo: **cuando algo NO es atributo
 * de contrato del normalizador, necesita paridad propia**. La tenían el texto, los importes y la
 * cesta; los iconos no, y por eso se cayeron sin ruido.
 *
 * ▶ **Lo que se asevera aquí son DOS cosas, y la segunda es la que de verdad protege:**
 *  1. que ningún `<svg>` del cajón esté VACÍO — el modo de fallo exacto que ocurrió;
 *  2. que **el cajón no invente dibujos**: cada geometría que emite tiene que ser, byte a byte tras
 *     normalizar, la de un componente `<x-icons.*>` del sistema de diseño, o estar declarada abajo
 *     como propia del cajón con su motivo. Así, si alguien retoca un icono en Blade y no aquí (o al
 *     revés), la copia deja de coincidir con NINGUNA fuente y este test cae.
 *
 * ⚠️ Se compara contra el FUENTE `.vue`, no contra el bundle SSR, a propósito: el modo de fallo es de
 * autoría —alguien escribe el envoltorio y deja el dibujo para luego—, así que conviene cazarlo antes
 * de construir nada. Que el árbol que sale del bundle sea el correcto ya lo vigila
 * `SidebarDomContractTest`.
 */
class SidebarIconParityTest extends TestCase
{
    /** Elementos que DIBUJAN. Un `<svg>` sin ninguno de estos no pinta nada. */
    private const GEOMETRY_TAGS = ['path', 'line', 'polyline', 'polygon', 'circle', 'ellipse', 'rect', 'text', 'g', 'use'];

    /**
     * Dibujos que son del CAJÓN y no del sistema de diseño, con el motivo de que no haya componente.
     *
     * Los cuatro venían INLINE en el blade del motor retirado (`purchase.blade.php`) —comprobado uno
     * a uno contra él—, no de `<x-icons.*>`, así que al borrarlo su única fuente pasó a ser el
     * componente Vue. Se declaran para que la lista no tenga que mentir, y para que añadir uno nuevo
     * sea una decisión consciente y no un descuido.
     *
     * ⚠️ Van en forma CANÓNICA (atributos en orden alfabético), que es como los deja `canonical()`.
     *
     * @var array<string, string>
     */
    private const DRAWER_OWN = [
        'lupa del buscador del catálogo' => '<circle cx="11" cy="11" r="7"/><line x1="21" x2="16.5" y1="21" y2="16.5"/>',
        'flecha del pie del carrito' => '<line x1="4" x2="19" y1="12" y2="12"/><polyline points="13 6 19 12 13 18"/>',
        'ⓘ del desglose de la señal' => '<circle cx="12" cy="12" r="9"/><line x1="12" x2="12" y1="11" y2="16"/><circle cx="12" cy="8" fill="currentColor" r="0.6"/>',
        'tarjeta del CTA de pagar' => '<rect height="14" rx="2.5" width="20" x="2" y="5"/><line x1="2" x2="22" y1="10" y2="10"/>',
    ];

    public function test_no_icon_in_the_drawer_is_an_empty_shell(): void
    {
        $vacios = [];

        foreach ($this->drawerSvgs() as [$file, $open, $inner]) {
            if ($this->geometryElements($inner) === []) {
                $vacios[] = $file.' → '.$open;
            }
        }

        $this->assertSame([], $vacios,
            "Hay iconos del cajón que son un `<svg>` sin nada dentro, así que NO SE VEN.\n".
            "El diff de árbol no puede cazarlo: no desciende dentro de un `<svg>`, de modo que un\n".
            'envoltorio vacío le parece idéntico a uno con su dibujo.'
        );
    }

    public function test_the_drawer_does_not_invent_drawings(): void
    {
        $sistema = $this->designSystemGeometries();
        $propios = array_flip(self::DRAWER_OWN);
        $huerfanos = [];

        foreach ($this->drawerSvgs() as [$file, $open, $inner]) {
            foreach ($this->geometries($inner) as $geometria) {
                if ($geometria === '' || isset($propios[$geometria]) || in_array($geometria, $sistema, true)) {
                    continue;
                }

                $huerfanos[] = $file.":\n      ".$geometria;
            }
        }

        $this->assertSame([], $huerfanos,
            "El cajón está pintando un dibujo que no es el de ningún `<x-icons.*>` ni está declarado\n".
            "como propio suyo. O el icono del sistema de diseño cambió y esta copia se quedó vieja, o\n".
            "es un icono nuevo que hay que declarar en DRAWER_OWN diciendo por qué no tiene componente.\n".
            'Geometrías del sistema disponibles: '.count($sistema)
        );
    }

    /**
     * ⚠️⚠️ **LA GUARDA DE LA GUARDA: que este fichero MIRE todos los componentes del cajón.**
     *
     * Nace de que no los miraba. El descubrimiento era `glob('js/sidebar/**\/*.vue')` y dejaba fuera
     * **10 de 32** —las diez zonas de `sidebar/account/zones/`, con un `<svg>` ya sin paridad—,
     * porque `**` no es recursivo en el `glob()` de PHP. **El resto del fichero seguía verde**: un
     * conjunto más pequeño pasa igual de bien.
     *
     * ▶ Por eso ancla en un fichero de **TRES** niveles de profundidad y no en un recuento: un número
     * hay que actualizarlo cada vez que nace un componente —y quien lo actualiza sin mirar lo sube y
     * ya está—, mientras que el ancla se rompe justo cuando el patrón deja de descender. Verificado
     * por mutación: con el `glob` anterior este caso es **rojo**.
     */
    public function test_the_parity_actually_looks_at_every_component_of_the_drawer(): void
    {
        $vistos = array_map(
            fn (string $ruta): string => str_replace(resource_path('js/'), '', $ruta),
            $this->drawerComponents(),
        );

        $this->assertContains(
            'sidebar/account/zones/AccountHomeZone.vue', $vistos,
            'La paridad de iconos ha dejado de descender hasta las zonas del área de cliente. Con el '.
            'descubrimiento ciego, un `<svg>` vacío en cualquiera de ellas se sirve sin que nada '.
            'falle — que es literalmente `DECISIONES #113` repitiéndose en el mismo repo.'
        );

        $this->assertContains('sidebar/Sidebar.vue', $vistos, 'ha dejado de mirar la raíz del cajón');
        $this->assertContains('sidebar/steps/CatalogStep.vue', $vistos, 'ha dejado de mirar los pasos');
    }

    /**
     * Los iconos del sistema de diseño que el cajón usa tienen que seguir estando: si alguien retira
     * un componente `<x-icons.*>`, la copia del cajón se quedaría sin fuente contra la que compararse
     * y el test de arriba dejaría de significar nada (pasaría a validar contra un conjunto más chico).
     */
    public function test_the_design_system_icons_the_drawer_copies_still_exist(): void
    {
        // ⚠️ Los cinco de abajo son los del ÍNDICE de «Mi cuenta» (`account/ZoneIcon.vue`), copiados del
        // sistema de diseño el 2026-08-23. Si alguno desaparece, esa copia se queda sin fuente.
        foreach ([
            'ic-e5', 'ic-b1', 'ic-b7', 'ticket-tear-off', 'arrow-left', 'arrow-right',
            'calendar', 'user', 'lock', 'devices', 'shield', 'login', 'logout',
            // `receipt` («Mis pedidos», 2026-08-24) y `users` («Menores a cargo», Fase 6 · C, 2026-08-27):
            // los dos nacieron con su zona, y el cajón lleva su copia.
            'receipt', 'users',
        ] as $icono) {
            $this->assertFileExists(
                resource_path("views/components/icons/{$icono}.blade.php"),
                "El cajón lleva una copia del dibujo de `{$icono}`; si el componente desaparece, esa ".
                'copia se queda sin fuente y la paridad deja de comprobar nada.'
            );
        }
    }

    // ── Herramientas ──────────────────────────────────────────────────────────────────────────

    /**
     * Todos los `<svg>` de los componentes del cajón, como `[fichero, etiqueta de apertura, interior]`.
     *
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function drawerSvgs(): array
    {
        $ficheros = $this->drawerComponents();

        $this->assertNotEmpty($ficheros, 'no se han encontrado componentes del cajón');

        $out = [];

        foreach ($ficheros as $fichero) {
            // ⚠️ Los comentarios van FUERA antes de buscar: varios docblocks del cajón citan
            // «`<svg>`» al explicar por qué el diff no desciende en él, y esa cita se colaba como si
            // fuera un icono (y no era XML válido, que es como se descubrió).
            $fuente = preg_replace('/<!--.*?-->/s', '', (string) file_get_contents($fichero)) ?? '';
            preg_match_all('/(<svg\b[^>]*>)(.*?)<\/svg>/s', $fuente, $m, PREG_SET_ORDER);

            foreach ($m as $svg) {
                $out[] = [str_replace(resource_path('js/'), '', $fichero), $this->squash($svg[1]), $svg[2]];
            }
        }

        return $out;
    }

    /**
     * TODOS los `.vue` del cajón, a cualquier profundidad, ordenados.
     *
     * ⚠️⚠️ **Esto era un `glob('js/sidebar/**\/*.vue')` y dejaba fuera 10 de los 32 ficheros** —las
     * DIEZ zonas del área de cliente, `sidebar/account/zones/`—, con un `<svg>` ya sirviéndose sin
     * ninguna paridad. `**` **no es recursivo en el `glob()` de PHP**: se comporta como un `*`, así
     * que el patrón solo alcanzaba UN nivel de subdirectorio y el `array_merge` de al lado añadía la
     * raíz. Ese `array_merge` era además la señal de que el patrón no bastaba, y nadie la leyó.
     *
     * ▶ **Es el modo de fallo exacto de `DECISIONES #113`**, que es lo que este fichero existe para
     * impedir: un gate que declara cubrir los iconos del cajón mientras un tercio de sus componentes
     * no lo mira nadie. Un test que mide menos de lo que su nombre dice es peor que no tenerlo
     * (`DECISIONES #115`).
     *
     * Se usa el mismo recorrido que los otros cuatro gates del cajón
     * (`SidebarComponentBudgetTest::components()`), en vez de un patrón que ya demostró no cubrir.
     *
     * @return list<string>
     */
    private function drawerComponents(): array
    {
        $root = resource_path('js/sidebar');
        $found = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->getExtension() === 'vue') {
                $found[] = $file->getPathname();
            }
        }

        sort($found);

        return $found;
    }

    /**
     * Las geometrías de un `<svg>`. Normalmente una; si el dibujo se bifurca con `<template v-if>`
     * —el CTA del pie, que alterna tarjeta y flecha en un solo nodo— cada rama es la suya, porque
     * `<template>` no emite nodo y lo que llega al navegador es una u otra.
     *
     * @return list<string>
     */
    private function geometries(string $inner): array
    {
        if (! str_contains($inner, '<template')) {
            return [$this->canonical($inner)];
        }

        preg_match_all('/<template\b[^>]*>(.*?)<\/template>/s', $inner, $m);

        return array_map(fn (string $rama): string => $this->canonical($rama), $m[1]);
    }

    /** Los dibujos de TODOS los `<x-icons.*>`, ya normalizados. @return array<string, string> */
    private function designSystemGeometries(): array
    {
        $out = [];

        foreach (glob(resource_path('views/components/icons/*.blade.php')) ?: [] as $fichero) {
            $nombre = basename($fichero, '.blade.php');
            $html = Blade::render("<x-icons.{$nombre} />");

            if (preg_match('/<svg\b[^>]*>(.*?)<\/svg>/s', $html, $m) === 1) {
                $out[$nombre] = $this->canonical($m[1]);
            }
        }

        // Los dos ojos del campo de contraseña viven INLINE en `<x-ui.password-input>`, no en
        // `components/icons/`, y el cajón los copia igual: sin esto saldrían como huérfanos.
        $pwd = Blade::render('<x-ui.password-input id="p" model="p" />');
        preg_match_all('/<svg\b[^>]*class="pwd-input__icon"[^>]*>(.*?)<\/svg>/s', $pwd, $ojos, PREG_SET_ORDER);

        foreach ($ojos as $i => $ojo) {
            $out['pwd-eye-'.$i] = $this->canonical($ojo[1]);
        }

        $this->assertNotEmpty($out, 'no se ha podido renderizar ningún icono del sistema de diseño');

        return $out;
    }

    /** @return list<string> */
    private function geometryElements(string $inner): array
    {
        preg_match_all('/<([a-zA-Z]+)\b/', $inner, $m);

        return array_values(array_intersect(array_map('strtolower', $m[1]), self::GEOMETRY_TAGS));
    }

    /**
     * Deja un dibujo comparable: mismo XML, mismos atributos ORDENADOS, sin espacios ni comentarios.
     *
     * Hace falta porque los dos lados se escriben a mano en sitios distintos —Blade con `/>` y saltos
     * de línea, Vue con su propia indentación—, y lo que se compara es el DIBUJO, no cómo está
     * formateado.
     */
    private function canonical(string $inner): string
    {
        $inner = preg_replace('/<!--.*?-->/s', '', $inner) ?? $inner;

        if (trim($inner) === '') {
            return '';
        }

        $doc = new \DOMDocument;
        $doc->preserveWhiteSpace = false;

        if (! @$doc->loadXML('<r xmlns:x="urn:x">'.$inner.'</r>')) {
            $this->fail("No se ha podido leer esta geometría como XML:\n".$inner);
        }

        return $this->serialise($doc->documentElement);
    }

    private function serialise(\DOMNode $node): string
    {
        if ($node instanceof \DOMText) {
            return trim($node->nodeValue ?? '');
        }

        if (! $node instanceof \DOMElement) {
            return '';
        }

        $atributos = [];

        foreach (iterator_to_array($node->attributes ?? []) as $atributo) {
            $atributos[$atributo->nodeName] = $atributo->nodeValue;
        }

        ksort($atributos);

        $partes = [];

        foreach ($atributos as $nombre => $valor) {
            $partes[] = ' '.$nombre.'="'.$this->squash((string) $valor).'"';
        }

        $hijos = '';

        foreach ($node->childNodes as $hijo) {
            $hijos .= $this->serialise($hijo);
        }

        $etiqueta = $node->nodeName;

        if ($etiqueta === 'r') {
            return $hijos;   // el envoltorio que se añadió para poder leerlo
        }

        return $hijos === ''
            ? '<'.$etiqueta.implode('', $partes).'/>'
            : '<'.$etiqueta.implode('', $partes).'>'.$hijos.'</'.$etiqueta.'>';
    }

    private function squash(string $valor): string
    {
        return trim(preg_replace('/\s+/', ' ', $valor) ?? $valor);
    }
}
