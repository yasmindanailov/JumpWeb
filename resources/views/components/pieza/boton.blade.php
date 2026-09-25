@props(['variant' => 'primary', 'size' => 'md', 'full' => false, 'disabled' => false, 'href' => null, 'type' => 'button', 'loading' => false, 'loadingLabel' => '', 'izquierda' => null, 'derecha' => null])
@php
    /*
     * El botón del sistema (`core/Button.jsx`), con sus mismas variantes y tallas; el estilo, `.pz-boton` en
     * `fiesta.css`. Con `href` y sin bloquear pinta `<a>`; si no, `<button>`. El `hover` y el `press` del diseño son
     * estado de React: aquí, `:hover` y `:active`. Los iconos, en las ranuras `izquierda` y `derecha`. `loading` bloquea
     * el botón y lo marca `aria-busy` (la bola del sistema, `BounceLoader`, no está portada: se declara).
     * ⚠️ Sin espacios alrededor de las ranuras ni del texto: junto a un icono, un salto de línea se pinta como un
     *    espacio y el JSX no deja ninguno.
     */
    $bloqueado = $disabled || $loading;
    $clases = 'pz-boton pz-boton--'.$size.' pz-boton--'.$variant.($full ? ' pz-boton--full' : '');
@endphp
@if ($href && ! $bloqueado)<a href="{{ $href }}" {{ $attributes->class($clases) }}>{{ $izquierda }}{{ $slot }}{{ $derecha }}</a>@else<button type="{{ $type }}" @disabled($bloqueado) @if ($loading) aria-busy="true" @endif {{ $attributes->class($clases) }}>{{ $izquierda }}{{ $slot }}{{ $derecha }}</button>@endif