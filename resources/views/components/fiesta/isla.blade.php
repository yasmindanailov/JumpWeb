@props(['label' => '', 'delay' => 0, 'ocultaSi' => '', 'cara' => 'barra'])
@php
    /*
     * LA ISLA DE LAS PÁGINAS DE ENLACE (`navigation/LinkIsland.jsx` del zip (6), `#861`; `fiesta-sistema-nuevo.md` §4.18,
     * `#814`): la invitación, la lista y la autorización llegan por un enlace, fuera de la web, y su ÚNICA pieza que flota
     * es ésta, con la tarea de la página y ninguna más (sin menú ni cuenta). Sustituye a la barra de la respuesta, a la de
     * guardar y al «Firmar» suelto de la autorización.
     *
     * ⚠️ Sale del SERVIDOR con su cara de partida (`$slot`: la respuesta, la línea o una barra), que es un formulario o un
     *    botón de enviar de verdad: sin JavaScript la página contesta, guarda y firma igual, y la isla va en el flujo, donde
     *    se monta (`.no-js`). Con JavaScript (`resources/js/fiesta/isla.js`) va FIJA abajo —también en escritorio, centrada a
     *    560 px—, llega tras `delay`, se aparta al escribir en un campo que no es suyo y con una capa abierta, se esconde
     *    mientras la página ya enseña lo que diría (`ocultaSi`, un selector) y cambia de cara con su morph.
     * ⚠️ Su hueco al pie es otra pieza (`x-fiesta.isla-hueco`), al FINAL del contenido: así, sin JavaScript, la isla se
     *    queda donde estaba la barra de antes y el orden de lectura no cambia.
     * ⚠️ Es una pieza CON estado (sale, entra, crece): sus estilos van por clases (`.fi-isla*` en `fiesta.css`), no en línea.
     * `cara`: `respuesta` (el radio de 28 de la cara alta) o `barra`/`linea` (34, la píldora).
     */
@endphp
<div role="region" aria-label="{{ $label }}" {{ $attributes->class(['fi-isla']) }} data-isla-enlace data-delay="{{ (int) $delay }}"@if ($ocultaSi !== '') data-oculta-si="{{ $ocultaSi }}"@endif><div class="fi-isla-caja {{ $cara === 'respuesta' ? 'fi-isla-caja--alta' : '' }}" data-surface="ink" data-isla-caja><div class="fi-isla-dentro" data-isla-dentro><div data-isla-cara="{{ $cara }}">{{ $slot }}</div></div></div></div>
