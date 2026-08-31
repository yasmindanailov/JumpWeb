{{-- **EL HUECO DE ILUSTRACIÓN** (`specs/hueco-ilustracion.md` §6) — el dibujo lo trae la
     instalación en `client-kit.svg`; aquí solo se decide CUÁL, de qué TAMAÑO y con qué TRATAMIENTO.

     ▶ **Sin paquete no se emite NADA** (`[DECIDIDO owner]`, §7). Ni el `<svg>`: así no queda caja
     vacía ni rectángulo de color. El producto no dibuja un juego genérico propio, porque un suelo de
     ilustraciones sería arte versionado del PRODUCTO — que es justo por donde `#257` metió 43
     dibujos del artboard de un cliente y por donde `favicon.svg` conserva el naranja del primero.

     ⚠️ `IllustrationKit::version()` hace las DOS cosas en una sola llamada a disco —existencia y
     cache-busting—: devuelve `false` si no está. Mismo recurso que `client.css` en el layout.

     ⚠️⚠️ **EL TROQUEL ES DONDE ESTO SE ROMPE PEOR, y por eso lleva DOS defensas.** Medido en
     navegador: sobre un símbolo que declara `fill="currentColor"` propio —que es como exporta el
     artboard— el troquel **pinta el 100,0 % de la caja del color de marca** (controles: 51,5 % con
     símbolo limpio, 0,0 % en celda vacía). Es el rectángulo que `site.css` documenta como *peor que
     no tener default*, entrando por otra puerta.
       1. `kit:build` rechaza un `fill` propio en el símbolo (`IllustrationKit`), y
       2. aquí el negro de la máscara va **EXPLÍCITO en el `<use>`**, sin depender de heredar.
     Con una sola de las dos, un sprite copiado a mano en el servidor pinta el rectángulo.

     ⚠️ **El `id` de la máscara se DERIVA de la clave, no es aleatorio.** Un marcado no determinista
     no lo puede aseverar ninguna comparación byte a byte ni ningún diff de árbol, que son dos de las
     tres redes que este repo usa para los dibujos. Dos instancias de la misma clave comparten `id`
     con contenido idéntico, así que resuelven igual.

     ⚠️ Va `aria-hidden`: es DECORACIÓN. El significado lo pone el texto que acompaña al dibujo —el
     nombre de la zona—, y un dibujo que repitiera ese nombre sería un nombre accesible duplicado.
     Es la misma decisión que `.menu__blob`. --}}

@props(['clave', 'trato' => 'plano'])

@php
    if (! in_array($trato, ['plano', 'contorno', 'troquel'], true)) {
        // Un tratamiento desconocido es un fallo de quien escribe la vista. Caer aquí es preferible
        // a pintar `plano` por defecto: eso lo escondería hasta que alguien mirase la pantalla.
        throw new \InvalidArgumentException("Tratamiento de ilustración desconocido: «{$trato}».");
    }

    $kitVersion = \App\Domain\Content\Services\IllustrationKit::version();
    $src = $kitVersion === false
        ? null
        : asset(\App\Domain\Content\Services\IllustrationKit::PATH).'?v='.$kitVersion;

    // `null` es una RESPUESTA, no una falta: sin `data-stroke` manda el grosor del CSS.
    $grosor = $trato === 'contorno'
        ? \App\Domain\Content\Services\IllustrationKit::stroke($clave)
        : null;

    // ⚠️⚠️ **El `viewBox` se COPIA del símbolo al envoltorio, y sin él el dibujo sale deformado.**
    // Un `<svg>` sin `viewBox` no tiene relación de aspecto intrínseca, así que `height: auto` cae
    // a los 150 px por defecto de un elemento reemplazado. Medido en la tarjeta de zona: la caja
    // daba **638×150** donde debía ser ~190 de ancho con la proporción del dibujo.
    $viewBox = \App\Domain\Content\Services\IllustrationKit::viewBox($clave);
@endphp

@if ($src)
    @if ($trato === 'troquel')
        <svg {{ $attributes->class(['ilu', 'ilu--troquel']) }}@if ($viewBox) viewBox="{{ $viewBox }}"@endif aria-hidden="true" focusable="false">
            <mask id="ilu-troquel-{{ $clave }}" maskUnits="userSpaceOnUse" x="0" y="0" width="100%" height="100%">
                <rect x="0" y="0" width="100%" height="100%" style="fill:#fff" />
                {{-- ⚠️⚠️ **`color:#000` junto al `fill:#000`, y NO es redundante.** Si el símbolo
                     trae `fill="currentColor"`, su atributo gana al `fill` heredado y la máscara se
                     pinta del color del documento: si ése es claro, el recorte se INVIERTE y el
                     troquel pinta la caja entera. Medido con un `color` magenta: **100,0 % de la
                     caja** sin esta línea y **64,4 %** con ella —el valor exacto del símbolo
                     limpio—. ⚠️ La primera medición dio esto por bueno **por suerte**: `color`
                     valía la tinta oscura del producto, que como máscara se lee casi igual que el
                     negro. La sonda no probaba el defecto, probaba un caso donde no se nota. --}}
                <use href="{{ $src }}#{{ $clave }}" style="fill:#000;color:#000" />
            </mask>
            <rect x="0" y="0" width="100%" height="100%" mask="url(#ilu-troquel-{{ $clave }})" />
        </svg>
    @else
        <svg {{ $attributes->class(['ilu', 'ilu--'.$trato]) }}@if ($viewBox) viewBox="{{ $viewBox }}"@endif aria-hidden="true" focusable="false">
            <use href="{{ $src }}#{{ $clave }}"@if ($grosor) stroke-width="{{ $grosor }}"@endif />
        </svg>
    @endif
@endif
