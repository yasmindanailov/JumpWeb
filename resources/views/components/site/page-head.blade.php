{{-- ══ LA CABECERA DE PÁGINA · la del armazón de las interiores ══════════════════════════════════
     `DECISIONES #525` · carril de diseño Fase 3 · T3a·3. Artboard `Layout Paginas PJP` 1a y 1b.

     El canvas: «una página no tiene hero: tiene CABECERA DE PÁGINA» — rótulo en Etiqueta con la
     RUTA, titular en Display L y entradilla. Y «una página no estrena tipografía»: es la misma
     cabecera de las ocho secciones de la portada, así que su CSS comparte declaración con
     `.sec-head` y no tiene reglas propias de tamaño (lo vigila `PageHeadTest`).

     ▶ **Una página interior NO lleva rótulo** (`#586`, `[DECIDIDO owner]`: la ruta escrita «/precios»
     era jerga de quien construyó la web). Hasta entonces el rótulo por defecto era la ruta.
     ⚠️ Las pantallas de SERVICIO (mantenimiento, pago, contraseña, verificar el correo) pasan su
     rótulo a mano y lo siguen pintando: nombran lo que está pasando, no una dirección.

     ⚠️ **Sin entradilla no se pinta el párrafo**: un párrafo vacío deja 16/20 px de aire colgando
     debajo del titular, y es lo que tienen hoy las páginas legales.

     ❗❗ **LA DECORACIÓN VA EN SU RANURA (`deco`) Y VIVE DENTRO DEL CONJUNTO rótulo + titular**
     (`.page__lockup`), que la recorta — la ENTRADILLA queda fuera. La trama de `/normas` tiene la
     regla escrita «detrás del titular y NUNCA detrás de un párrafo», y la incumplía en cuanto la
     cabecera tuvo entradilla (medido en `#525`). *Colocarla contra la cabecera entera la dejaba a
     merced de lo largo que fuera el texto; contra el conjunto, no puede alcanzarlo.*
     ⚠️⚠️ **Y por eso SOLO vale para lo que cabe en el alto del conjunto** (unos 80 px en escritorio).
     El abanico de `/precios` vivía aquí y salía como una banda con los dos cantos rectos: se mudó
     detrás de la figura de la fachada (`#580`). Una pieza más alta que el titular no va en esta
     ranura.

     El `$slot` normal va al FINAL y es para una línea de dato de una pantalla de servicio. --}}
@props([
    'title',
    'lede' => null,
    'eyebrow' => null,
])
<div {{ $attributes->class('page__head') }}>
    <div @class(['page__lockup', 'page__lockup--deco' => isset($deco)])>
        {{ $deco ?? '' }}
        @if (filled($eyebrow))
            <p class="page__eyebrow">{{ $eyebrow }}</p>
        @endif
        <h1 class="page__title">{{ $title }}</h1>
    </div>
    @if (filled($lede))
        <p class="page__lede">{{ $lede }}</p>
    @endif
    {{ $slot }}
</div>
