{{-- 4 · ¿UN CUMPLEAÑOS? (forma A) --}}
@if ($packages->isNotEmpty())
<section id="events" class="section wrap pg-q">
    <h2 class="pg-q__ask pg-q__ask--pregunta">¿Un cumpleaños para {{ $packages->first()->min_qty ?: 8 }} o más?</h2>
    <p class="pg-q__answer">Un pack por zona, <strong>lado a lado</strong>. Reservas con una señal; el menú, la tarta y los nombres, después.</p>
    @include('prototipos.partials.packs')
    <p style="margin-top: var(--sp-16)"><a class="pg-link" href="{{ route('cumpleanos') }}">Cómo se organiza un cumple, paso a paso →</a></p>
</section>
<hr class="pg-hairline wrap">
@endif
