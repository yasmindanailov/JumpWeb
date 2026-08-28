@props(['up' => false, 'trigger' => ''])

{{-- **EL SELECTOR DE IDIOMA** — un componente para sus dos sitios (`#233`).

     ⚠️⚠️ **Existe porque `#205` lo RETIRÓ del pie con un argumento que ya no se sostiene.** Allí se
     razonó que «dos selectores del mismo idioma en la misma página son dos sitios que mantener y
     uno que se queda atrás», y era verdad **mientras fueran dos copias**. Con un componente hay
     una sola cosa que mantener, y el mockup lo tiene en los dos: en las cápsulas del menú y en el
     bloque inferior del pie. `[DECIDIDO owner, 2026-08-28]`: «el footer tiene menos elementos».

     ▶ Es la tercera vez esta sesión que la respuesta a «esto está en dos sitios» es un componente
     y no una copia — el par de CTA (`#227`) y la tira de marca (`#226`) fueron las otras dos.

     ⚠️ **La lista de idiomas sale de `SiteLocales`, que es la fuente única.** El pie llegó a tener
     su propia copia, y **dos listas de idiomas es cómo se acaba ofreciendo uno que la otra no
     reconoce** — lo dice el docblock de esa clase.

     ⚠️ **`--up` no es cosmético**: en el pie el panel tiene que abrirse HACIA ARRIBA o se sale de
     la página. En el menú se abre hacia abajo, que es donde hay sitio. --}}
<div {{ $attributes->class(['lang-dd', 'lang-dd--up' => $up]) }}
     x-data="{ open: false }"
     :class="open && 'lang-dd--open'"
     @click.outside="open = false"
     @keydown.escape.window="open = false">
    <button type="button" class="lang-dd__trigger {{ $trigger }}"
            @click="open = !open"
            :aria-expanded="open.toString()"
            aria-haspopup="menu"
            aria-label="{{ __('landing.footer.language') }}">
        {{ \App\Domain\Platform\Services\SiteLocales::NAMES[app()->getLocale()] ?? strtoupper(app()->getLocale()) }}
        <svg class="chev" width="9" height="9" viewBox="0 0 10 10" fill="none" aria-hidden="true">
            <path d="M2 4l3 3 3-3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    </button>
    <div class="lang-dd__panel" role="menu">
        @foreach (\App\Domain\Platform\Services\SiteLocales::SUPPORTED as $l)
            @php($isActive = app()->getLocale() === $l)
            <a href="{{ route('lang.switch', $l) }}"
               class="{{ $isActive ? 'active' : '' }}"
               role="menuitem"
               hreflang="{{ $l }}"
               @if ($isActive) aria-current="true" @endif>
                <span>{{ strtoupper($l) }}</span><span class="name">{{ \App\Domain\Platform\Services\SiteLocales::NAMES[$l] ?? strtoupper($l) }}</span>
            </a>
        @endforeach
    </div>
</div>
