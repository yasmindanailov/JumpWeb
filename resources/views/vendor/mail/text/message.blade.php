{{-- ⚠️ `preheader` se declara aquí para IGNORARLO, y eso es la decisión: en texto plano no hay
     bandeja a la que adelantarse, así que pintarlo repetiría la primera frase del correo. Se
     declara —en vez de dejarlo caer en `$attributes`— para que quede escrito que es deliberado:
     la trampa de esta casa es justo la contraria, componentes de correo que nacen a medias. --}}
@props(['preheader' => null])
<x-mail::layout>
    {{-- Header --}}
    <x-slot:header>
        <x-mail::header :url="config('app.url')">
            {{-- ❗❗ EL NEGOCIO, NO EL PRODUCTO — y aquí el slot SÍ se usa (`text/header` lo pinta),
                 al revés que en la versión HTML, donde el componente lo ignora y lee la BD él mismo.
                 Por eso la fuga de `#500` sobrevivió en texto plano: se arreglaron las cuatro
                 superficies del HTML y todo correo lleva TAMBIÉN una parte de texto. --}}
            {{ \App\Domain\Platform\Models\Setting::businessName() }}
        </x-mail::header>
    </x-slot:header>

    {{-- Body --}}
    {{ $slot }}

    {{-- Subcopy --}}
    @isset($subcopy)
        <x-slot:subcopy>
            <x-mail::subcopy>
                {{ $subcopy }}
            </x-mail::subcopy>
        </x-slot:subcopy>
    @endisset

    {{-- Footer --}}
    <x-slot:footer>
        <x-mail::footer>
            © {{ date('Y') }} {{ \App\Domain\Platform\Models\Setting::businessName() }}. @lang('All rights reserved.')
        </x-mail::footer>
    </x-slot:footer>
</x-mail::layout>
