{{-- LO PRIMERO, si la reserva no trae el nombre de quien cumple: sin él no hay invitación ni titular. Una pregunta,
     un campo y un botón, con la invitación de verdad al lado, que se escribe sola mientras se teclea (JS). Es un
     `<form>` de verdad: Intro envía, y sin JavaScript también. Escribe SOLO `party_invitations` (`updateInvitation`). --}}
@php($inv = $m['invitacion'])
<form class="pli" method="post" action="{{ $inv['accion'] }}" novalidate data-primero>
    @csrf
    <header class="pli-cab">
        @if ($m['logo']['src'] !== null)
            <img class="pli-logo" src="{{ $m['logo']['src'] }}" alt="{{ $m['logo']['alt'] }}">
        @else
            <span class="pli-logo" style="display: inline-flex; align-items: center; font: 900 1.375rem/1 var(--font-display); color: var(--fiesta-tinta-900);">{{ $m['logo']['alt'] }}</span>
        @endif
        <h1 id="pli-primero-h" class="pli-h1">{{ __('fiesta.lista.primero.titulo') }}</h1>
        <p class="pli-res"><span>{{ __('fiesta.lista.resguardo', ['corto' => $m['reserva']['corto'], 'hora' => $m['reserva']['hora'], 'pack' => $m['reserva']['pack'], 'codigo' => $m['reserva']['codigo']]) }}</span></p>
    </header>
    <section class="pli-zona pli-primero" aria-labelledby="pli-primero-h">
        <div class="pli-primero-q">
            <p class="pli-primero-t">{{ $m['cumple']['edad'] !== '' ? __('fiesta.lista.primero.texto_edad', ['edad' => $m['cumple']['edad']]) : __('fiesta.lista.primero.texto') }}</p>
            <x-pieza.campo id="pli-primero-nombre" name="honoree_name" :label="__('fiesta.lista.primero.label')" value="" required autocomplete="off" autocapitalize="words" enterkeyhint="go" :maxlength="$inv['honoree_max']" data-primero-nombre />
            <input type="hidden" name="honoree_age" value="{{ $m['cumple']['edad'] }}">
            <input type="hidden" name="theme" value="{{ $inv['tema'] }}">
            <input type="hidden" name="host_line" value="{{ $inv['invita'] }}">
            <x-pieza.boton type="submit" variant="primary" size="lg" full data-primero-boton>{{ __('fiesta.lista.primero.boton') }}</x-pieza.boton>
        </div>
        <div class="pli-primero-vista">
            <p class="pli-sub">{{ __('fiesta.lista.primero.vista') }}</p>
            <x-fiesta.invitacion :theme="$inv['tema']" name="…" :age="$m['cumple']['edad']" :date="$m['reserva']['dia']" :time="__('fiesta.lista.de_a', ['hora' => $m['reserva']['hora'], 'fin' => $m['reserva']['fin']])" :place="$m['reserva']['lugar']" :host="$inv['invita']" data-primero-vista />
        </div>
    </section>
</form>
