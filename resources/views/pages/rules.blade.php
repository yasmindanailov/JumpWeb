@php
    // Meta description (SEO, INVISIBLE en la página): resumen de las normas a partir de su
    // contenido real, en vez de repetir el título.
    $rulesMeta = $rules->isNotEmpty()
        ? \Illuminate\Support\Str::limit($rules->take(6)->map(fn ($r) => $r->tr('name'))->implode(' · '), 155)
        : __('site.rules_title');
@endphp
<x-layout :title="__('site.rules_title')" :description="$rulesMeta">
<div x-data="landing">
    <x-site.nav />

    <main id="main" class="page page--rules wrap">
        <x-site.page-head :title="__('site.rules_title')" :lede="__('site.rules_intro')">
            {{-- A2 · la trama que se apaga. **UNA por pantalla** (`[DECIDIDO owner, 2026-08-31]`),
                 no una por tarjeta: la decoración va en la PANTALLA, no en el componente que se
                 repite — la regla que dejaron los tres rechazos de `#286`. Antes se pintaba dentro
                 del `@foreach` y en esta instalación salían CINCO copias.
                 ⚠️ Va en la CABECERA y no sobre la página entera, y se probó al revés: sobre toda
                 la página el degradado a 115° no llega a apagarse en una caja tan alta y deja una
                 tira de puntos bajando por el margen izquierdo hasta el pie. Aquí entra por la
                 esquina y desaparece, que es lo que dice su propia descripción de A2. --}}
            <x-slot:deco><div class="grain grain--fade" aria-hidden="true"></div></x-slot:deco>
        </x-site.page-head>

        <div class="rules-grid">
            @foreach ($rules as $rule)
                <div class="rule">
                    <div class="rule__icon">!</div>
                    <span class="rule__name">{{ $rule->tr('name') }}</span>
                    <span class="rule__desc">{{ $rule->tr('description') }}</span>
                </div>
            @endforeach
        </div>

        <a href="{{ url('/') }}" class="page__back" data-tap>{{ __('site.back_home') }}</a>
    </main>

    <x-site.footer />
</div>
</x-layout>
