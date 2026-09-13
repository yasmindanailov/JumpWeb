{{-- ══ LA FACHADA · el mural de la instalación sobre la web ═══════════════════════════════════
     `DECISIONES #580` · pasada de vestido (`specs/pasada-de-vestido.md` §3.quater). Es la variante
     2 «mural» (`#546`→`#548`), que el owner eligió sobre la portada real con `?fachada=2`, pasada a
     producción y extendida a las páginas interiores.

     ❗❗ **EL DIBUJO ES DEL CLIENTE Y LA COLOCACIÓN ES DEL PRODUCTO.** Cada fila dice qué pieza del
     kit (`client-kit.svg`), con qué tratamiento y con qué clase; **dónde y con cuánta presencia lo
     dice `landing.css`** («LA FACHADA · EL MURAL»). Sin kit —o sin esa pieza dentro— `<x-site.ilu>`
     no emite nada y aquí queda una capa vacía de cero bytes visibles (`#286`).

     ⚠️⚠️ **La posición NO va en `style` en línea, y el prototipo sí la llevaba.** Un atributo `style`
     gana a cualquier regla de hoja, así que colocar una pieza distinta en móvil obligaba a
     `!important` —y sin él la mancha de «Antes de venir» se quedaba encima del titular con la media
     query aplicándose, sin fallar—.
     ⚠️ **Y la clase va con DOS selectores en la hoja** (`.fac-p.fac-p--x`): `site.css` se carga
     DESPUÉS de `landing.css` y declara `.ilu { height: auto }`, que a igual especificidad ganaría.

     ⚠️ **Una clave desconocida LANZA**, como el tratamiento de `<x-site.ilu>`: una errata en `en`
     pintaría nada sin avisar, y una sección sin su figura no la echa en falta ninguna guarda.

     ⚠️ **La capa necesita un anfitrión que sea contexto de apilamiento** (`z-index: -1` se hundiría
     por detrás del fondo de la página). No se pide a quien la usa: lo pone la hoja con
     `:has(> .fac-slot)`, así que la pieza se lleva su requisito consigo. --}}
@props(['en'])

@php
    /*
     * clave de colocación => [pieza del kit, tratamiento, clases, textura detrás de la figura]
     *
     * ⚠️⚠️ **02 «Cuánto» y 03 «Qué hay dentro» no tienen fila, y no es un olvido**: sus artboards lo
     * prohíben —«cero superficies nuevas y cero manchas» y «la foto de apertura es la mancha grande:
     * aquí no entra ninguna otra»—. «Cuánto» lleva el ARCO (`<x-site.arc>`), que va en su carril y
     * no en la cabecera. 04 lleva el TRÍO (`<x-site.trio>`), que es una composición y no una pieza.
     * 06 y 08 llevan su mancha tras el titular desde `#544`, y el CIERRE no lleva nada
     * (`[DECIDIDO owner]`: «en el hero del footer no ponemos nada»).
     *
     * ▶ **Las figuras de adulto van en Cian y las de niño en Lima** (`--strip-1` · `--strip-2`): es
     * la única distinción de color del reparto, y se lee sin explicarla.
     *
     * ⚠️ **Las páginas no repiten la pose de la portada que habla de lo mismo**: `/atracciones` no
     * es «Para quién», y una pose que sale dos veces se nota (la regla 05 del kit).
     */
    $mapa = [
        // ── PORTADA ─────────────────────────────────────────────────────────────────────────
        'zones' => ['slot-pose-p5', 'plano', 'fac-p--zones', null],
        // El NIÑO al lado del adulto, más bajo y en Lima (`[DECIDIDO owner]`, `#547`).
        'zones-nino' => ['slot-pose-k3', 'plano', 'fac-p--zones-nino', null],
        // Mancha y no figura: la sección ya tiene dibujo (el QR y el teléfono).
        'before' => ['slot-splash-4', 'contorno', 'fac-p--before', null],
        'info' => ['slot-pose-p3', 'plano', 'fac-p--info', null],

        // ── PÁGINAS · una pieza por cabecera, en el papel que el titular deja a la derecha ──
        'page-atracciones' => ['slot-pose-p1', 'plano', 'fac-p--page fac-p--page-atracciones', null],
        /*
         * ❗❗ **A3 · LOS RAYOS van AQUÍ, detrás de la figura, y no en la cabecera** (`#580`). Su nota
         * los define como «foco detrás de un precio o de una silueta», y ésta es la silueta. Vivían
         * en la ranura `deco` del conjunto rótulo + titular, que RECORTA (`#525`): un abanico de 360
         * dentro de una caja de 80 px de alto salía como una BANDA con los dos cantos rectos —lo vio
         * el owner—, y su desvanecido circular no llegaba a terminar en ningún lado.
         * ⚠️ Los rayos son MECANISMO (un degradado), no arte del kit: se pintan también sin kit.
         */
        'page-precios' => ['slot-pose-p6', 'plano', 'fac-p--page fac-p--page-precios', 'rays fac-rays--precios'],
        'page-normas' => ['slot-pose-p4', 'plano', 'fac-p--page fac-p--page-normas', null],
        'page-contacto' => ['slot-pose-p7', 'plano', 'fac-p--page fac-p--page-contacto', null],
        // Mancha y no figura: en el bar no se salta.
        'page-bar' => ['slot-splash-6', 'contorno', 'fac-p--page fac-p--page-bar', null],
    ];

    [$clave, $trato, $clases, $textura] = $mapa[$en]
        ?? throw new \InvalidArgumentException("Colocación de fachada desconocida: «{$en}».");
@endphp

{{-- ⚠️ Las clases van ESCRITAS ENTERAS en el mapa y no compuestas: una clase armada por
     concatenación no la ve ningún inventario de CSS (`FacadeCssHasNoOrphansTest`, `#287`). --}}
<div class="fac-slot" aria-hidden="true">
    @if ($textura)
        <div class="{{ $textura }}"></div>
    @endif
    <x-site.ilu :clave="$clave" :trato="$trato" :class="'fac-p '.$clases" />
</div>
