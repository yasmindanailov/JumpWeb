<?php

namespace Tests\Feature\Landing;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * **EL TITULAR DE UNA SECCIÓN: UNA LÍNEA, SIN ETIQUETA ENCIMA** (`#303`, `[DECIDIDO owner]`:
 * *«quiero todos los titulares solo 1 línea […] el eyebrow de los titulares lo vamos a quitar»*).
 *
 * ▶ Es la primera pieza del molde editorial que `#297` diagnosticó y que sigue `[DECIDIDO]`: las
 * SIETE secciones de la portada abrían con `ETIQUETA` → titular partido en dos con coma, y en
 * **cuatro de siete la etiqueta repetía una palabra del titular** («Tarifas» sobre «Tarifas
 * claras», «FAQ» sobre «Preguntas frecuentes»).
 *
 * ⚠️⚠️ **Esto se vigila con análisis del MARCADO y no con una captura, porque es exactamente lo que
 * vuelve sin que nadie lo note**: alguien añade una sección nueva copiando la de al lado, y con ella
 * vuelven la etiqueta y el `<br />`. La página carga, la suite pasa y el molde regresa entero.
 *
 * ⚠️ **Las pantallas de servicio quedan FUERA a propósito** —`auth/`, `errors/`, `payments/`—: ahí
 * el `eyebrow` no es la etiqueta de un titular editorial, es la rotulación de una pantalla de
 * utilidad («Recuperar contraseña», «Página en mantenimiento»). Meterlas aquí sería aplicar una
 * decisión de la landing a un sitio donde no se tomó.
 */
class SectionHeadlineTest extends TestCase
{
    /** Las vistas PÚBLICAS de la landing, sin las pantallas de servicio. */
    private const FUERA = ['auth/', 'errors/', 'payments/', 'livewire/', 'filament/', 'emails/', 'pdf/', 'vendor/'];

    /** @return array<string, string> ruta relativa → contenido sin comentarios de Blade */
    private function vistas(): array
    {
        $out = [];

        foreach (File::allFiles(resource_path('views')) as $f) {
            $rel = str_replace(resource_path('views').'/', '', $f->getPathname());

            if (! str_ends_with($rel, '.blade.php')) {
                continue;
            }

            foreach (self::FUERA as $prefijo) {
                if (str_starts_with($rel, $prefijo)) {
                    continue 2;
                }
            }

            // ⚠️ Sin comentarios: una lápida que NOMBRA lo retirado lo haría parecer vivo. Es el
            // fallo que `#293` documentó con el motivo copiado a mano.
            $out[$rel] = (string) preg_replace('/\{\{--.*?--\}\}/s', '', (string) file_get_contents($f->getPathname()));
        }

        return $out;
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Guarda de la guarda
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **El corpus contiene las vistas que tiene que contener.**
     *
     * ⚠️ Sin este caso, un cambio de rutas o un filtro de más dejaría `vistas()` devolviendo un
     * array vacío y los dos casos de abajo pasarían **sin mirar nada**: en el vacío no hay
     * infracciones. Es el fallo que este proyecto ha cometido cuatro veces.
     */
    public function test_the_corpus_holds_the_public_views(): void
    {
        $vistas = $this->vistas();

        // ⚠️ Desde `#666` la portada de PlayJump vive en la instancia; el centinela es el ANFITRIÓN,
        // que es la portada que el producto sirve y la que tiene que respetar el molde de cabecera.
        $this->assertArrayHasKey('anfitrion/portada.blade.php', $vistas, 'la portada no está en el corpus');
        // ⚠️ Desde `#660` (F5 · T2b) **`resources/views/pages/` ya no existe**: las ocho páginas viven en
        // la instancia y el producto sirve su anfitrión mínimo. El centinela se re-apunta a uno de ellos,
        // que es la página pública que el producto SÍ tiene.
        $this->assertArrayHasKey('anfitrion/servicios.blade.php', $vistas, 'falta una página pública');
        $this->assertArrayNotHasKey('auth/reset-password.blade.php', $vistas, 'se ha colado una pantalla de servicio');

        $this->assertGreaterThan(15, count($vistas), 'el corpus es sospechosamente corto');
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Lo que se vigila
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **Ninguna vista pública emite la etiqueta de un titular.**
     *
     * ⚠️ Se mira `class="eyebrow"` EXACTA, no por subcadena: `bd-card__eyebrow` es la etiqueta de una
     * TARJETA de pack —otra cosa— y `cookie__eyebrow` la del banner. Aseverar por subcadena habría
     * cazado las tres y obligado a excepciones que tapan la regla. *Es la trampa de `#206`.*
     */
    public function test_no_public_view_emits_a_headline_eyebrow(): void
    {
        $culpables = [];

        foreach ($this->vistas() as $ruta => $html) {
            if (preg_match('/class="eyebrow"/', $html)) {
                $culpables[] = $ruta;
            }
        }

        $this->assertSame(
            [], $culpables,
            'Vuelve la ETIQUETA sobre el titular en: '.implode(', ', $culpables)."\n".
            "▶ `[DECIDIDO owner, 2026-08-31]`: los titulares van sin etiqueta encima. Medido antes:\n".
            "  en 4 de las 7 secciones de la portada la etiqueta REPETÍA una palabra del titular.\n".
            '▶ Si una pantalla de servicio la necesita, va en `FUERA`, con su motivo escrito.',
        );
    }

    /**
     * **Ningún titular parte sus dos mitades con `<br />`.**
     *
     * El titular de sección se compone de `title` + `title_em`, y el molde heredado los separaba con
     * un salto de línea forzado. ⚠️ Esto **no** dice que el texto quepa en una línea —eso depende de
     * la ventana y del texto, y se mide en navegador—: dice que **el marcado no obliga** a dos.
     */
    public function test_no_headline_forces_a_second_line(): void
    {
        /**
         * ⚠️⚠️ **LA ÚNICA EXCEPCIÓN, y va con su motivo porque las listas de excepción SOLO ENCOGEN.**
         *
         * `landing.reserve.title` es el titular de la TARJETA DEL CIERRE, y **no es un par
         * etiqueta+titular**: son TRES partes —el texto, una palabra en contorno (`.stroke`) y otra
         * rellena (`.fill`)—, apiladas a propósito. Es el dispositivo que `#252`, `#253` y `#254`
         * construyeron y MIDIERON contra el alto de ventana (su punto estático necesita 921 px);
         * ponerlo en una línea cambia las proporciones de una coreografía verificada, no el molde
         * editorial que esta guarda persigue.
         * ▶ Queda dicho para que nadie lo «arregle» sin saber lo que toca, y **pendiente del owner**:
         * si decide que también va a una línea, se retira de aquí y se rehace el cierre con él.
         */
        $excepciones = ['landing.reserve.title'];

        $culpables = [];

        foreach ($this->vistas() as $ruta => $html) {
            preg_match_all("/__\('([\w.]*title)'\) \}\}\s*<br\s*\/?>/", $html, $m);

            $reales = array_values(array_diff($m[1], $excepciones));

            if ($reales !== []) {
                $culpables[] = $ruta.' ('.implode(', ', $reales).')';
            }
        }

        // Y la excepción tiene que seguir teniendo SUJETO: si el cierre deja de partirse solo, la
        // línea de arriba sobra y hay que borrarla en vez de arrastrarla.
        $todos = implode("\n", $this->vistas());

        foreach ($excepciones as $e) {
            $this->assertMatchesRegularExpression(
                "/__\('".preg_quote($e, '/')."'\) \}\}\s*<br\s*\/?>/", $todos,
                "la excepción `{$e}` ya no tiene sujeto: nadie parte ese titular, así que sobra.",
            );
        }

        $this->assertSame(
            [], $culpables,
            'Un titular vuelve a partirse con `<br />` en: '.implode(', ', $culpables)."\n".
            '▶ `[DECIDIDO owner]`: los titulares van a UNA línea.',
        );
    }
}
