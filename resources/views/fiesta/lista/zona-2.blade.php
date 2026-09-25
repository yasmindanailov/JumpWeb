{{-- ZONA 2 · La lista. Una fila por niño (`fila-invitado`): nombre, edad, alergias y Firmada · Falta. Las respuestas
     que llegan por la invitación van justo debajo, en «Por repasar», con la chapa, y entran en la lista al guardar
     (`adopt[]`). Las fichas VACÍAS del formulario posicional salen abiertas sin JavaScript y escondidas con él:
     «Añadir a mano» las rellena una detrás de otra. --}}
@php
    $inv = $m['invitacion'];
    $ninos = $m['ninos'];
    $repasar = array_values(array_filter($ninos, fn (array $n): bool => $n['pendiente']));
    $resto = array_values(array_filter($ninos, fn (array $n): bool => ! $n['pendiente'] && ! $n['vacia']));
    $vacias = array_values(array_filter($ninos, fn (array $n): bool => ! $n['pendiente'] && $n['vacia']));
    $noVienenSueltos = $inv === null ? [] : array_values(array_filter($inv['no_vienen'], fn (array $r): bool => $r['indice'] === null));
    $conDatos = $repasar !== [] || $resto !== [];
    // Se añade gente mientras el formulario se pueda editar: con lo que HAY, añadir es rellenar una ficha VACÍA de la
    // reserva, que no mueve el número. (Sumar por encima de la reserva es lo que FALTA, spec §7·3.)
    $sumar = ! $m['solo_lectura'];
    $estado = static fn (array $n): string => $n['respuesta'] === 'si' ? 'confirmado' : ($n['respuesta'] === 'no' ? 'no' : 'sin-contestar');
@endphp
<section class="pli-zona" data-zona="2" aria-labelledby="pli-h-lista" data-la-lista>
    <div class="pli-cab-z">
        <h2 id="pli-h-lista" class="pli-h2">{{ __('fiesta.lista.la_lista.titulo') }}</h2>
        <span class="pli-filtro" hidden data-filtro-chip><x-pieza.etiqueta selected data-filtro-etiqueta>{{ '' }}</x-pieza.etiqueta><x-pieza.enlace size="sm" underline="always" data-filtro-quitar>{{ __('fiesta.lista.la_lista.ver_todos') }}</x-pieza.enlace></span>
    </div>
    {{-- El progreso, para el lector de pantalla (el diseño no lo dibuja): la fuente de verdad del servidor. --}}
    @if ($m['progreso']['total'] > 0)
        <p class="pz-sr" role="status">{{ $m['progreso']['done'] >= $m['progreso']['total'] ? __('guestform.progress_complete', ['total' => $m['progreso']['total']]) : __('guestform.progress', ['done' => $m['progreso']['done'], 'total' => $m['progreso']['total']]) }}</p>
    @endif
    @php($ley = __('fiesta.lista.la_lista.leyenda'))
    <p class="pli-leyenda">{{ $ley[0] }} <span class="ok"><x-lucide name="circle-check" :size="15" />{{ $ley[1] }}</span> {{ $ley[2] }} <span><x-lucide name="circle-dashed" :size="15" />{{ $ley[3] }}</span></p>
    @if ($repasar !== [])
        <div class="pli-repasar" data-repasar>
            <p class="pli-repasar-cab"><strong>{{ __('fiesta.lista.la_lista.por_repasar') }} · <span data-repasar-n>{{ count($repasar) }}</span></strong><span>{{ __('fiesta.lista.la_lista.por_repasar_nota') }}</span></p>
            <ul class="pli-ul">
                @foreach ($repasar as $k => $n)
                    @include('fiesta.lista.fila', ['n' => $n, 'ultima' => $k === count($repasar) - 1])
                @endforeach
            </ul>
        </div>
    @endif
    <ul class="pli-ul" data-filas>
        @foreach ($resto as $k => $n)
            @include('fiesta.lista.fila', ['n' => $n, 'ultima' => $k === count($resto) - 1 && $vacias === [] && $noVienenSueltos === []])
        @endforeach
        @foreach ($vacias as $k => $n)
            @include('fiesta.lista.fila', ['n' => $n, 'ultima' => $k === count($vacias) - 1 && $noVienenSueltos === []])
        @endforeach
        {{-- Los «no» que NO emparejan con ninguna ficha: no son filas del formulario, pero el anfitrión tiene que verlos. --}}
        @foreach ($noVienenSueltos as $k => $r)
            <x-fiesta.fila-invitado :id="'no'.$r['id']" :name="$r['nombre']" state="no" viaInvite :editable="false" :last="$k === count($noVienenSueltos) - 1" omitir :omitirForm="$sumar ? 'fiesta-descartar' : null" :omitirValue="$r['id']" />
        @endforeach
    </ul>
    <p class="pli-sub" hidden data-nadie>{{ __('fiesta.lista.la_lista.nada_aqui') }}</p>
    @if (! $conDatos && $sumar)
        <p class="pli-vacia" data-vacia><x-lucide name="users-round" :size="20" />{{ __('fiesta.lista.la_lista.vacia') }}</p>
    @endif
    <p class="pli-deshacer" role="status" hidden data-deshacer><span data-deshacer-texto></span><x-pieza.enlace size="sm" underline="always" data-deshacer-boton>{{ __('fiesta.lista.la_lista.deshacer') }}</x-pieza.enlace></p>
    @if ($sumar)
        <div hidden data-panel="anadir"><x-fiesta.anadir-invitado id="nuevo" :defaultAge="$m['cumple']['edad']" :autoFocus="false" /></div>
        <div hidden data-panel="pegar" class="pli-pegar">
            <x-pieza.area id="pli-pegar" :label="__('fiesta.lista.pegar.label')" :hint="__('fiesta.lista.pegar.hint')" :rows="4" autoGrow data-pegar-texto />
            <p class="pli-sub" hidden data-pegar-repetidos></p>
            <div class="pli-acciones"><x-pieza.boton variant="secondary" size="sm" disabled data-pegar-aplicar>{{ trans_choice('fiesta.lista.pegar.boton', 0, ['count' => 0]) }}</x-pieza.boton><x-pieza.boton variant="ghost" size="sm" data-panel-cerrar="pegar">{{ __('fiesta.lista.pegar.cancelar') }}</x-pieza.boton></div>
        </div>
        <div class="pli-acciones" data-acciones-lista>
            <x-pieza.boton variant="quiet" size="sm" aria-expanded="false" data-panel-abrir="anadir"><x-slot:izquierda><x-lucide name="user-round-plus" :size="17" /></x-slot:izquierda>{{ __('fiesta.lista.la_lista.anadir') }}</x-pieza.boton>
            <x-pieza.boton variant="quiet" size="sm" aria-expanded="false" data-panel-abrir="pegar"><x-slot:izquierda><x-lucide name="clipboard-paste" :size="17" /></x-slot:izquierda>{{ __('fiesta.lista.la_lista.pegar') }}</x-pieza.boton>
        </div>
    @endif
    @if ($sumar && ! $m['numero']['editable'] && $vacias === [])
        @php($cerrada = __('fiesta.lista.la_lista.cerrada'))
        <p class="pli-sub">{{ $cerrada[0] }}<a href="tel:{{ $m['reserva']['tel'] }}">{{ $cerrada[1] }}</a>{{ $cerrada[2] }}</p>
    @endif
    @if ($inv !== null && $inv['no_vienen'] !== [] && $sumar && $inv['plazo'] !== '')
        <p class="pli-sub">{{ __('fiesta.lista.la_lista.no_vienen_baja', ['plazo' => $inv['plazo']]) }}</p>
    @endif
</section>
