@props(['conCumple' => true])

{{-- CÓMO FUNCIONA UNA VISITA — la única secuencia numerada de la web (`guion` §3.5). Cada regla, donde
     muerde: aquí se PROMETE; en el cajón y en la puerta se PIDE. Copia del prototipo (sin i18n). --}}
<ol class="pg-steps">
    <li class="pg-step">
        <span class="pg-step__t">Eliges y reservas</span>
        <span class="pg-step__d">Zona, día, hora y cuántos sois. El precio es el del día que eliges.</span>
        <span class="pg-step__time">2 minutos · online o en taquilla</span>
    </li>
    <li class="pg-step">
        <span class="pg-step__t">Te registras y firmas</span>
        <span class="pg-step__d">Todos los que saltan se registran una vez y aceptan el descargo. Si vienen menores, los añades a tu cuenta y firmas por ellos.</span>
        <span class="pg-step__time">2 minutos · desde el móvil</span>
    </li>
    <li class="pg-step">
        <span class="pg-step__t">Calcetines antideslizantes</span>
        <span class="pg-step__d">Obligatorios para saltar. Tráelos de casa o añádelos a la entrada.</span>
        <span class="pg-step__time">+2 € si no los traes</span>
    </li>
    <li class="pg-step">
        <span class="pg-step__t">En la puerta, tu QR</span>
        <span class="pg-step__d">Enseñas el carné de tu cuenta y entras. Sin colas, sin papeles.</span>
        <span class="pg-step__time">al llegar</span>
    </li>
</ol>
@if ($conCumple)
    <p class="pg-steps__plus">Y si es un cumpleaños: reservas con una señal, y <strong style="color: var(--fg)">después</strong> te pedimos los nombres de los niños.</p>
@endif
