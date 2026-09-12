{{-- ══ FACHADA · LAS DOS VARIANTES SOBRE LA PORTADA REAL ══════════════════════════════════════
     `DECISIONES #546` · pasada de vestido (`#497`). El laboratorio (`/_diseno/…`) enseña las
     colocaciones **sueltas**; esto las pone en la portada de verdad para poder juzgar el conjunto,
     que es lo único que dice si la página está «sosa» o no.

         localhost:8081/?fachada=1     «dentro»  · el material vive dentro de las cajas
         localhost:8081/?fachada=2     «mural»   · el material toma la pantalla

     ❗❗❗ **ES TEMPORAL Y NO SE SIRVE EN PRODUCCIÓN.** El parámetro solo se lee en `local`, así que
     fuera de ahí la portada es exactamente la de siempre — ni una pieza, ni un `<style>`, ni un
     byte de más. Cuando el owner elija, aquí queda **una** colocación y este `@if` se va.

     ⚠️⚠️ **TODO EL DISEÑO VIVE EN EL MAPA DE ABAJO, y es a propósito.** Cada pieza es una línea:
     qué dibujo, con qué tratamiento, dónde y con cuánta presencia. Iterar es editar una fila —no
     buscar por siete vistas—, que es justo lo que esta pieza necesita mientras se decide.

     ⚠️ **El `z-index: -1` necesita que la sección sea un contexto de apilamiento.** `.section` es
     `position: relative` pero sin `z-index`, así que no lo crea: sin `isolation` la pieza se hunde
     por detrás del fondo de la página y desaparece (el patrón de `#286`). La regla se inyecta aquí
     y **solo con variante activa**, para no tocar una declaración que comparten las doce vistas.

     ⚠️ El recorte es de la SECCIÓN: `inset: 0` + `overflow: hidden` hacen que una figura más alta
     que su caja se corte por el borde en vez de empujar el alto de la página. Las que el mapa marca
     como `escapa` lo apagan a sabiendas. --}}
@props(['en'])

@php
    /*
     * ⚠️ **La variante se lee AQUÍ y no se pasa por prop**: con siete llamadas, un prop obligaría a
     * calcularla en el controlador y a enhebrarla por la vista, y este bloque tiene que poder
     * borrarse de un tirón sin dejar una variable huérfana detrás.
     * ⚠️ Y se lee solo en `local`: en producción `$v` es siempre 0 y no se emite nada.
     */
    $v = app()->environment('local') ? (int) request()->query('fachada', 0) : 0;

    /*
     * ── EL MAPA ──────────────────────────────────────────────────────────────────────────────
     * sección → variante → [clave del kit, tratamiento, estilo, ¿se sale de la caja?]
     *
     * **VARIANTE 1 · «dentro»** — la lectura literal del canvas: *«el fondo es papel y el material
     * vive dentro de las tarjetas»*. Opacidades bajas, nada se sale, la figura se lee como textura.
     * **VARIANTE 2 · «mural»** — lo que el parque tiene pintado en su fachada: figuras grandes, a
     * plena opacidad donde el fondo lo aguanta, y una que se sale de su caja.
     *
     * ⚠️⚠️ **Ni 02 «Cuánto» ni 03 «Qué hay dentro» llevan pieza en NINGUNA de las dos**, y no es un
     * olvido: sus artboards lo prohíben con todas las letras —«cero superficies nuevas y cero
     * manchas» y «la foto de apertura es la mancha grande: aquí no entra ninguna otra»—. Son las dos
     * únicas secciones donde el canvas dice que no.
     *
     * ⚠️ Las manchas de 06 · 07 · 08 **no están aquí**: ya viven en el árbol (`#544`) detrás de sus
     * titulares. Lo que estas variantes añaden es lo que iría ENCIMA de eso.
     *
     * ⚠️ La tinta sale de `--strip-*`, los cinco colores de marca del cliente. Las figuras de niño
     * van en Lima (`--strip-2`) y las de adulto en Cian (`--strip-1`): es la única distinción de
     * color del reparto, y se lee sin que nadie la explique.
     */
    $mapa = [
        /*
         * ⚠️⚠️ **TODAS SE ANCLAN A LA BANDA DE LA CABECERA, y la primera versión no.** Estaban
         * puestas con `bottom`, y medido eso las mandaba **al fondo de secciones de 1.150 px**,
         * detrás de las tarjetas y de sus fotos: una figura de 640 px invisible entera. El hueco de
         * papel de una sección está ARRIBA, al lado del titular — medido: 512 px libres a la derecha
         * en 04, 05 y 07, y 200 en 01.
         */

        // 01 · Para quién — el hueco a la derecha del titular es el más estrecho de las cinco (200).
        'zones' => [
            1 => ['slot-pose-p5', 'plano', 'right:0; top:70px; height:320px; --ilu-fg:var(--strip-1); opacity:.12', false],
            2 => ['slot-pose-p5', 'plano', 'right:0; top:50px; height:400px; --ilu-fg:var(--strip-1); opacity:.30', false],
        ],

        // 04 · Cumpleaños — la única sección con figura de NIÑO, y la única que se sale en la 2.
        'events' => [
            1 => ['slot-pose-k2', 'plano', 'right:40px; top:30px; height:300px; --ilu-fg:var(--strip-2); opacity:.16', false],
            2 => ['slot-pose-k1', 'plano', 'right:60px; top:-80px; height:290px; --ilu-fg:var(--strip-2); opacity:1', true],
        ],

        // 05 · Antes de venir — mancha, no figura: aquí ya hay dibujo (el QR y el teléfono).
        'before' => [
            1 => ['slot-splash-4', 'plano', 'right:40px; top:30px; width:300px; --ilu-fg:var(--strip-1); opacity:.18', false],
            2 => ['slot-splash-4', 'contorno', 'right:10px; top:0; width:420px; --ilu-fg:var(--strip-1); opacity:.55', false],
        ],

        // 07 · Visítanos — el canvas deja esta sección explícitamente a la pasada de vestido.
        'info' => [
            1 => ['slot-pose-p3', 'plano', 'right:10px; top:40px; height:260px; --ilu-fg:var(--strip-1); opacity:.14', false],
            2 => ['slot-pose-p3', 'plano', 'right:0; top:20px; height:340px; --ilu-fg:var(--strip-1); opacity:.32', false],
        ],

        /*
         * ⚠️⚠️ **08 · Dudas NO lleva pieza en ninguna de las dos, y se intentó.** Su cabecera vive en
         * una columna de 352 de la rejilla de doce y **el resto de la fila lo ocupa el acordeón**
         * (`#488`), así que el único papel libre es una franja de ~150 px bajo el titular. Medido
         * con la sonda de cobertura: una mancha ahí queda **14 % visible** — el 86 % son bytes que
         * no se ven. Se retira. ▶ La sección no se queda pelada: ya lleva su mancha tras el titular
         * desde `#544`.
         */

        /*
         * El CIERRE — la pieza va DENTRO de `.reserve__box`, no en la sección: la tarjeta no llena su
         * sección y anclada fuera la figura caía en el papel de al lado (medido: solo asomaba un
         * fragmento por el borde). Sobre tinta aguanta plena opacidad, que es lo que hace el mural.
         */
        'reserve' => [
            1 => ['slot-pose-p8', 'plano', 'right:40px; bottom:0; height:300px; --ilu-fg:var(--strip-1); opacity:.20', false],
            2 => ['slot-pose-p8', 'plano', 'right:60px; bottom:0; height:420px; --ilu-fg:var(--strip-1); opacity:1', false],
        ],
    ];

    $pieza = $mapa[$en][$v] ?? null;
@endphp

@if ($pieza)
    @php([$clave, $trato, $estilo, $escapa] = $pieza)
    <div class="fac-slot" @if ($escapa) style="overflow: visible" @endif aria-hidden="true">
        <x-site.ilu :clave="$clave" :trato="$trato" class="fac-p" :style="$estilo" />
    </div>
@endif
