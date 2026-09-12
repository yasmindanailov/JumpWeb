{{-- **LA «i» DE LA TARIFA ESPECIAL** (`DECISIONES #542`).

     Sustituye a `<x-site.special-rate-note>`, que escribía los días al pie de cada sección. El
     término se explica ahora **donde se usa** —pegado a la cifra— y bajo demanda, que es lo que el
     owner pidió al retirar los días de la tarifa normal.

     ⚠️ **No se pinta si no hay nada que afirmar**: sin tarifas especiales activas o sin `weekdays`
     declarados, `lines()` viene vacío y aquí no sale ninguna «i». Vacío es una respuesta. --}}
@php($tip = app(\App\Domain\Booking\Services\SpecialRateExplainer::class))

@if ($tip->exists())
    <x-site.info-tip :label="$tip->label()">
        @foreach ($tip->lines() as $linea)
            <p class="infotip__p">{{ $linea }}</p>
        @endforeach
    </x-site.info-tip>
@endif
