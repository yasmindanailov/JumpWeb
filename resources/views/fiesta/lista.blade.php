{{--
    LA LISTA DE INVITADOS del sistema nuevo (`specs/fiesta-sistema-nuevo.md` §4.2, `#743`, `#765`): la página del
    anfitrión después de pagar su fiesta, con el diseño `paginas/lista-invitados.card.html`. Vive fuera de la web
    pública (sin isla ni menú), se abre por un enlace firmado sin sesión, y lee SOLO el modelo de página `$m`
    (`App\Http\Fiesta\ListaDeInvitados`), que es lo mismo que pinta el banco con los datos del diseño.

    ⚠️ El formulario es el de SIEMPRE: posicional (`guests[i][columna]`), con `adopt[]`, `guest_count`,
    `expected_version`, `addons[i][…]` y `general[…]`; y UN solo Guardar (zona 5). Los gestos que escriben otra cosa
    —«No lo apuntes» (`invitation_replies`), el recordatorio— van por SUS formularios, fuera de éste, con `form=`.
    ⚠️ Sin JavaScript es un formulario completo: las fichas salen abiertas, el número es un campo numérico y las
    cantidades de los extras también. El JS de la página (`resources/js/fiesta/lista.js`) lo enciende al final.
--}}
@php($hojas = $hojas ?? [])
<x-pagina-enfocada :titulo="__('fiesta.lista.titulo_pagina').' · '.$m['marca']" entrada="lista" :hojas="$hojas">
@if ($m['primero'])
    @include('fiesta.lista.primero')
@else
    <form class="pli" method="post" action="{{ $m['accion'] }}" id="fiesta-form" novalidate data-lista data-reserva="{{ $m['reserva']['codigo'] }}" data-textos="{{ json_encode(array_merge(__('fiesta.lista'), ['fila' => __('fiesta.fila'), 'anadir' => __('fiesta.anadir')]), JSON_UNESCAPED_UNICODE) }}">
        @csrf
        {{-- El TESTIGO de la reserva: si el parque la movió con la página abierta, el servidor rechaza el envío entero. --}}
        <input type="hidden" name="expected_version" value="{{ $m['testigo'] }}">
        @include('fiesta.lista.cabecera')
        @include('fiesta.lista.avisos')
        @if ($m['invitacion'] !== null)
            @include('fiesta.lista.zona-1')
        @endif
        @include('fiesta.lista.zona-2')
        @include('fiesta.lista.zona-3')
        @if ($m['extras']['lista'] !== [])
            @include('fiesta.lista.zona-4')
        @endif
        @if ($m['generales'] !== [])
            @include('fiesta.lista.generales')
        @endif
        @include('fiesta.lista.zona-5')
    </form>
    @include('fiesta.lista.auxiliares')
@endif
</x-pagina-enfocada>
