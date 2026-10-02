{{--
    LA RESEÑA DEL DÍA (`docs/specs/puerta-nueva.md` §4.4, la P3, D20; el mockup `PpuResena`): con el campo vacío y en el velo,
    nunca en la ficha. La elige `GateReviewOfTheDay`; aquí solo se pinta.
    ⚠️ El texto lo escribió un desconocido: `{{ }}`, nunca sin escapar, y `dir="auto"` porque puede venir en cualquier idioma.
    En el velo va sin `aria-label`: el velo entero es un botón, y una región dentro de un botón no es una región.

    @var array{id: int, fuente: string, texto: string, autor: ?string, cuando: ?string} $resena
    @var bool $region
--}}
<section class="ppu-card ppu-resena" data-gate-review="{{ $resena['fuente'] }}-{{ $resena['id'] }}"
         @if ($region) aria-label="{{ __('admin.puerta.resena.titulo') }}" @endif>
    {{-- La marca OFICIAL de Google (`#780`, el owner: «para darle más autoridad, como el widget oficial»): su fichero, tal
         cual —sin recolorear ni deformar—, sola en su sitio y nunca dentro de una frase. Las dos fuentes de la Puerta son
         reseñas de la ficha de Google; una opinión propia nunca llega aquí (`#494`). --}}
    <div class="ppu-resena__cab">
        <p class="ppu-over">{{ __('admin.puerta.resena.titulo') }}</p>
        <img class="ppu-resena__marca" src="{{ asset('images/providers/google-logo.svg') }}" alt="Google" width="74" height="24" data-gate-review-brand>
    </div>
    <blockquote class="ppu-resena__cita" dir="auto">«{{ $resena['texto'] }}»</blockquote>
    <p class="ppu-resena__a">
        @if ($resena['autor'] !== null && $resena['cuando'] !== null)
            {{ __('admin.puerta.resena.autor_cuando', ['autor' => $resena['autor'], 'cuando' => $resena['cuando']]) }}
        @elseif ($resena['autor'] !== null)
            {{ __('admin.puerta.resena.autor', ['autor' => $resena['autor']]) }}
        @elseif ($resena['cuando'] !== null)
            {{ __('admin.puerta.resena.anonima_cuando', ['cuando' => $resena['cuando']]) }}
        @else
            {{ __('admin.puerta.resena.anonima') }}
        @endif
    </p>
</section>
