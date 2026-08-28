<?php

namespace Tests\Feature\Architecture;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * **`<body>` no puede ser un contenedor de scroll: rompe todos los `position: sticky` de la web.**
 *
 * ⚠️⚠️ **Esto no es teoría de CSS: es un fallo que estuvo servido y nadie vio** (`#226`,
 * 2026-08-28). `html, body { overflow-x: hidden }` llevaba ahí desde siempre, y `hidden` —a
 * diferencia de `clip`— convierte al elemento en **contenedor de scroll**. Un `sticky` que cuelga
 * de `<body>` pasa entonces a anclarse al scrollport del body en vez de al del viewport; y como
 * quien scrollea de verdad no es el body, **no se pega nunca**.
 *
 * ▶ **Lo que costó, medido en navegador antes de arreglarlo:** el hero de la portada es un sticky
 * que encoge mientras la página baja (`#195`, `#216`, `#220`). No se pegaba: se iba con el scroll
 * y dejaba **569 px de hueco vacío** entre el hero y el contenido, porque el recorrido
 * (`--hero-runway`, 420 px) es altura real del `<header>`. Tres decisiones construyeron esa
 * coreografía y **ninguna se veía en un navegador**.
 *
 * ▶ **Y por qué no lo cazó nadie**: las guardas del hero aseveran el CSS —que los `calc` estén,
 * que los tokens existan, que el JS publique `--hero-p`—, y todo eso era CIERTO. El CSS estaba
 * bien escrito. Lo que fallaba era una regla a 1.200 líneas de distancia, en otro fichero, que no
 * habla del hero. **Una hoja de estilos correcta no garantiza una página correcta.**
 *
 * ⚠️ `.no-scroll` SÍ puede usar `overflow: hidden`, y no es una excepción incómoda: ahí el
 * objetivo *es* impedir el scroll, sólo se aplica mientras hay un modal o el cajón abierto, y en
 * ese estado no hay ningún sticky que importe.
 */
class StickySurvivesTheRootOverflowTest extends TestCase
{
    /** Valores de `overflow` que convierten un elemento en contenedor de scroll. `clip` NO. */
    private const HACEN_SCROLLPORT = ['hidden', 'auto', 'scroll', 'overlay'];

    /** @return array<string, string> selector normalizado → cuerpo de la regla */
    private function reglas(): array
    {
        $out = [];

        foreach ($this->hojas() as $ruta) {
            $css = (string) preg_replace('#/\*.*?\*/#s', ' ', (string) file_get_contents($ruta));

            preg_match_all('/([^{}]*)\{([^{}]*)\}/', $css, $m, PREG_SET_ORDER);

            foreach ($m as $regla) {
                foreach (explode(',', $regla[1]) as $selector) {
                    $selector = trim((string) preg_replace('/\s+/', ' ', $selector));

                    if ($selector !== '' && ! str_starts_with($selector, '@')) {
                        $out[$selector] = ($out[$selector] ?? '').' '.$regla[2];
                    }
                }
            }
        }

        return $out;
    }

    /** @return list<string> */
    private function hojas(): array
    {
        return array_values(array_filter([
            public_path('css/landing.css'),
            public_path('css/site.css'),
        ], 'is_file'));
    }

    #[Test]
    public function body_no_puede_convertirse_en_contenedor_de_scroll(): void
    {
        $reglas = $this->reglas();

        // Guarda de la guarda, y va PRIMERO: si el escaneo no viera la regla del `<body>`, la
        // aserción de abajo se cumpliría sola. Es el modo de fallo que este repo ya ha pagado
        // varias veces —una guarda que pasa sin mirar nada—.
        $conOverflow = array_filter(
            $reglas,
            fn (string $cuerpo, string $sel): bool => $this->esRaizDesnuda($sel)
                && preg_match('/(^|[;{\s])overflow(-[xy])?\s*:/', $cuerpo) === 1,
            ARRAY_FILTER_USE_BOTH,
        );

        $this->assertNotEmpty(
            $conOverflow,
            'el escaneo no ve NINGUNA regla de `html`/`body` que declare `overflow`, y la hay '.
            '(`html, body { overflow-x: clip }` en landing.css). El localizador se ha roto y la '.
            'comprobación de abajo pasaría sin mirar nada.',
        );

        foreach ($reglas as $selector => $cuerpo) {
            if ($selector !== 'body') {
                continue;   // `html` propaga al viewport y no estorba; lo medido es el `<body>`.
            }

            preg_match_all(
                '/(^|[;{\s])overflow(-[xy])?\s*:\s*([a-z-]+)/i',
                $cuerpo, $declaraciones, PREG_SET_ORDER,
            );

            foreach ($declaraciones as $d) {
                $this->assertNotContains(
                    strtolower($d[3]), self::HACEN_SCROLLPORT,
                    "`body` declara `overflow{$d[2]}: {$d[3]}`, y eso lo convierte en CONTENEDOR ".
                    "DE SCROLL.\n\n".
                    'Con eso, TODO `position: sticky` de la web se ancla al body en vez de al '.
                    'viewport y deja de pegarse — en silencio, sin fallar y sin avisar. Ya pasó '.
                    '(`#226`): el hero de la portada nunca se pegó y dejaba 569 px de hueco '.
                    "vacío debajo.\n\n".
                    '▶ Si lo que hace falta es RECORTAR el desbordamiento horizontal (la '.
                    'marquesina se sale hasta x=2533 en un viewport de 1280), el valor es '.
                    '`clip`: recorta igual y no crea contenedor de scroll.',
                );
            }
        }
    }

    /**
     * **Y lo que la regla de arriba PROTEGE sigue existiendo.**
     *
     * Sin esto, alguien podría retirar el sticky del hero y la guarda de arriba se quedaría
     * defendiendo algo que ya no está: seguiría verde y habría dejado de servir para nada.
     */
    #[Test]
    public function el_hero_de_la_portada_sigue_siendo_pegajoso(): void
    {
        $reglas = $this->reglas();

        $this->assertArrayHasKey(
            '.hero--full .hero__sticky', $reglas,
            'ha desaparecido la regla del escenario pegajoso del hero.',
        );

        $this->assertMatchesRegularExpression(
            '/(^|[;{\s])position\s*:\s*sticky/', $reglas['.hero--full .hero__sticky'],
            'el escenario del hero ha dejado de ser `sticky`. Si es a propósito, esta guarda y la '.
            'de `body` sobran juntas; si no lo es, la coreografía del hero ha dejado de existir.',
        );
    }

    /** ¿Es `html` o `body` a secas, sin clase, sin pseudo y sin descendencia? */
    private function esRaizDesnuda(string $selector): bool
    {
        return $selector === 'html' || $selector === 'body';
    }
}
