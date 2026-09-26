{{-- EL IDIOMA, abajo y en texto (`#748`, el owner): la página lo elige sola, como la web (`SetLocale`: lo que eligió
     antes, el de su cuenta o el de su navegador), y esta línea es para quien quiera otro. El que se lee no es enlace; los
     otros van a `lang.switch`, que vuelve aquí desde la sesión (la página no manda `Referer`). Sin JavaScript, igual.
     Cada nombre va en su propio idioma y con su `lang`: quien no lee el de la página reconoce el suyo. --}}
<nav class="inv-idiomas" aria-label="{{ __('fiesta.invitacion_pagina.idioma') }}" data-idiomas>
    <x-lucide name="globe" :size="14" />
    @foreach ($m['idiomas']['lista'] as $i)
        @if ($i['clave'] === $m['idiomas']['actual'])
            <span lang="{{ $i['clave'] }}" aria-current="true">{{ $i['nombre'] }}</span>
        @else
            <a href="{{ $i['enlace'] }}" hreflang="{{ $i['clave'] }}" lang="{{ $i['clave'] }}">{{ $i['nombre'] }}</a>
        @endif
    @endforeach
</nav>
