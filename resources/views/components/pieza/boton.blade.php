@props(['variant' => 'primary', 'size' => 'md', 'full' => false, 'disabled' => false, 'href' => null, 'type' => 'button', 'loading' => false, 'loadingLabel' => '', 'izquierda' => null, 'derecha' => null, 'llega' => null])
@php
    /*
     * El botón del sistema (`core/Button.jsx`), con sus mismas variantes y tallas; el estilo, `.pz-boton` en
     * `fiesta.css`. Con `href` y sin bloquear pinta `<a>`; si no, `<button>`. El `hover` y el `press` del diseño son
     * estado de React: aquí, `:hover` y `:active`. Los iconos, en las ranuras `izquierda` y `derecha`. `loading` bloquea
     * el botón y lo marca `aria-busy` (la bola del sistema, `BounceLoader`, no está portada: se declara).
     * ⚠️ Sin espacios alrededor de las ranuras ni del texto: junto a un icono, un salto de línea se pinta como un
     *    espacio y el JSX no deja ninguno.
     * ⚠️ El icono y el texto, DENTRO de `.pz-boton__dentro` (el `span` interior del diseño), y el botón siempre recortado
     *    (`position: relative; overflow: hidden` en `.pz-boton`): medido el 01-10 en la pasada ligera de la isla (`#768`),
     *    sin el `span` el icono caía en otra fracción de píxel (un píxel más abajo al pintarse) y sin el recorte su trazo
     *    salía con ±1 de color. `PiezaBotonTest`.
     * ▶ `llega` (F9, el zip tercero `#780`: `arrive`): el PRIMARIO llega con el bote y un brillo la primera vez que se ve,
     *   por defecto (`llega` false lo apaga); `data-llega` es la marca que lee `comun.js::llegadas`, que además lo calla
     *   sobre tinta y con «reducir movimiento». Bloqueado, no llega.
     */
    $bloqueado = $disabled || $loading;
    $clases = 'pz-boton pz-boton--'.$size.' pz-boton--'.$variant.($full ? ' pz-boton--full' : '');
    $llegaDe = ($llega ?? $variant === 'primary') && ! $bloqueado;
@endphp
@if ($href && ! $bloqueado)<a href="{{ $href }}" @if ($llegaDe) data-llega @endif {{ $attributes->class($clases) }}><span class="pz-boton__dentro">{{ $izquierda }}{{ $slot }}{{ $derecha }}</span></a>@else<button type="{{ $type }}" @disabled($bloqueado) @if ($loading) aria-busy="true" @endif @if ($llegaDe) data-llega @endif {{ $attributes->class($clases) }}><span class="pz-boton__dentro">{{ $izquierda }}{{ $slot }}{{ $derecha }}</span></button>@endif