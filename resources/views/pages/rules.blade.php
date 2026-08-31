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

    <main id="main" class="page wrap">
        <div class="page__head">
            <div class="eyebrow">{{ __('site.rules_eyebrow') }}</div>
            <h1 class="page__title">{{ __('site.rules_title') }}</h1>
        </div>

        <div class="rules-grid">
            @foreach ($rules as $rule)
                <div class="rule">
                    {{-- A2 · la trama que se apaga. Su nota la manda aquí: «para tarjetas con
                         mucho texto». Entra por una esquina y se desvanece antes de llegar al
                         párrafo, que es lo que su regla 02 exige. --}}
                    <div class="grain grain--fade" aria-hidden="true"></div>
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
