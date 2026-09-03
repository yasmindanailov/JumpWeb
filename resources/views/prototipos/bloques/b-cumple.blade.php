{{-- CUMPLEAÑOS (forma B) --}}
@if ($packages->isNotEmpty())
<section id="events" class="section wrap">
    <div class="pg-split">
        <div class="pg-split__text">
            <h2 class="pg-q__ask">Cumpleaños</h2>
            <p class="pg-q__answer">Un pack por zona. Reservas con una señal; el menú, la tarta y los nombres de los niños, <strong>después</strong>.</p>
            <p><a class="pg-link" href="{{ route('cumpleanos') }}">Paso a paso →</a></p>
        </div>
        <div class="pg-split__proof">
            @include('prototipos.partials.packs')
        </div>
    </div>
</section>
@endif
