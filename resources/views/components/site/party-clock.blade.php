@props([
    // La duración del pack, ya escrita («2 h»). Es el TITULAR del reloj.
    'duration',
    // Nivel del encabezado: `h3` dentro de la sección 04 de la portada, `h2` en `/cumpleanos`.
    'level' => 3,
])

{{-- ══ EL RELOJ DE LAS DOS HORAS · UNA PIEZA PARA LAS DOS SUPERFICIES ══════════════════════════════
     Nace extrayéndolo de la sección 04 de la portada (`#483`) cuando `/cumpleanos` lo pidió también
     (`DECISIONES #528`): el artboard `Cumpleanos Pagina PJP` 1a/1b dice que está *«copiado del
     marcado de 04 y no redibujado»*, y con dos copias el día que alguien cambie la regla de una, la
     portada y la página contarían las dos horas de dos formas.

     ❗❗ **NO REPARTE, y ésa es toda la pieza.** Las dos horas son para todo —merienda, tarta y
     saltos— y **no hay hora para nada**: si meriendan rápido, saltan más. Los tres tramos con sus
     minutos que había antes contaban un horario que no existe, y *un diagrama de tramos promete
     horario aunque la letra diga lo contrario*. Se dibuja el TOTAL entero con las tres cosas encima.
     ⚠️ **La duración la trae el llamante desde el catálogo**; sin ella el reloj no se pinta, porque
     su titular es la duración. --}}
<div class="party__clock" data-surface="ink">
    <div class="party__clock-said">
        <h{{ $level }} class="party__clock-title">{{ __('landing.events.clock_title', ['duration' => $duration]) }}</h{{ $level }}>
        <p class="party__clock-rule">{{ __('landing.events.clock_rule') }}</p>
        <p class="party__clock-rule">{{ __('landing.events.clock_monitor') }}</p>
    </div>
    {{-- ⚠️ `aria-hidden`: las tres cápsulas y el filete son el DIBUJO de lo que la frase de al lado
         ya dice. Anunciarlas repetiría la regla en desorden. --}}
    <div class="party__clock-rail" aria-hidden="true">
        <ul class="party__clock-caps" role="list">
            <li>{{ __('landing.events.clock_a') }}</li>
            <li>{{ __('landing.events.clock_b') }}</li>
            <li>{{ __('landing.events.clock_c') }}</li>
        </ul>
        <span class="party__clock-line"></span>
    </div>
</div>
