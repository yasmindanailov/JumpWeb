{{--
    LA INVITACIÓN DIGITAL de una fiesta, del sistema nuevo (`specs/fiesta-sistema-nuevo.md` §4.2, T2; `#743`, `#744`):
    la página que recibe el padre invitado por WhatsApp, con el diseño `paginas/invitacion.card.html`. Sin isla ni menú:
    el logotipo, la tarjeta con su tema y la barra de la respuesta, y abajo el idioma en texto (`#748`); tras contestar,
    el RECIBO (`$m['recibo']`, la misma página con su firma en la URL). Lee SOLO el modelo `$m`
    (`App\Http\Fiesta\InvitacionPagina`).

    ❗❗ HOJA EN BLANCO: no pinta ni una respuesta, ni cuántas hay, ni si un nombre concreto contestó. El enlace se reparte
    a un grupo de clase entero. Y sin `og:url`: el token no se repite en una meta.
    ⚠️ El TEMA pinta la página entera: el fondo toma su tinte (`--inv-tint`, `--inv-accent`, como `InvPagina`).
    ⚠️ Sin JavaScript contesta igual: la barra es un formulario con dos botones de enviar.
--}}
@php($hojas = $hojas ?? [])
<x-pagina-enfocada :titulo="$m['titulo_pagina']" entrada="invitacion" :hojas="$hojas">
    <x-slot:head>
        @if ($m['recibo'] === null)
            {{-- Lo que se ve al PEGAR el enlace en un chat: solo nombre, edad, día, hora y negocio (spec hermana §4.6). --}}
            <meta property="og:site_name" content="{{ $m['og']['sitio'] }}">
            <meta property="og:type" content="website">
            <meta property="og:title" content="{{ $m['og']['title'] }}">
            <meta property="og:description" content="{{ $m['og']['description'] }}">
            @if ($m['og']['image'])
                <meta property="og:image" content="{{ $m['og']['image'] }}">
                @if ($m['og']['width'] && $m['og']['height'])
                    <meta property="og:image:width" content="{{ $m['og']['width'] }}">
                    <meta property="og:image:height" content="{{ $m['og']['height'] }}">
                @endif
                <meta name="twitter:card" content="summary_large_image">
            @endif
        @endif
    </x-slot:head>
    <div class="inv-fondo" style="background-color: {{ $m['tema']['tinte'] }}; --inv-accent: {{ $m['tema']['acento'] }}; --inv-tint: {{ $m['tema']['tinte'] }};" data-invitation-page data-theme="{{ $m['tema']['clave'] }}">
        <main class="inv">
            @include('fiesta.invitacion.cabecera')
            @if ($m['recibo'] !== null)
                <div class="inv-vista">
                    @include('fiesta.invitacion.recibo')
                </div>
            @else
                @include('fiesta.invitacion.invitacion')
            @endif
            @include('fiesta.invitacion.idiomas')
        </main>
        @if ($m['parque']['video'] !== '')
            @include('fiesta.invitacion.visor')
        @endif
    </div>
</x-pagina-enfocada>
