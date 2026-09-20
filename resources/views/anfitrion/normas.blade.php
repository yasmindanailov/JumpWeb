{{-- ══ EL ANFITRIÓN MÍNIMO de /normas · lo que el producto sirve SIN paquete de instancia ═══════
     F5 · T2b (`specs/paquete-de-instancia.md` §4.4, `DECISIONES #655`). La página de PlayJump (artboard
     `Normas PJP`, `#533`) vive en su instancia; esto es lo que queda en el producto: el armazón, las normas
     por MOMENTO con su porqué, las sin momento al final, la escala de altura sacada del dato, la chapa del
     descargo si la instalación lo usa, y la fecha de la última revisión. Sin arte: ni fachada, ni trama, ni
     iconos del kit, ni superficies de tinta. Es marcado del PRODUCTO (`AnfitrionNormasTest` lo mira).

     ⚠️ Lo que se pinta aquí es EXACTAMENTE el contrato de vista (`InstanceViews::CONTRATO_DE_VISTAS`):
     `board`, `scale`, `waiverEnabled` y lo que reparte el composer. Una instancia que pinte lo mismo con
     su diseño no necesita saber nada más. --}}
@php
    $todas = collect($board['groups'])->flatMap(fn (array $g) => $g['rules'])->concat($board['ungrouped']);
    $rulesMeta = \App\Domain\Platform\Services\MetaDescription::fromNames($todas->map(fn ($r) => $r->tr('name')))
        ?? __('site.rules_title');
@endphp
<x-layout :title="__('site.rules_title')" :description="$rulesMeta">
<div x-data="landing">
    <x-site.nav />
    <main id="main" class="page page--rules wrap">
        <x-site.page-head :title="__('site.rules_headline')" :lede="__('site.rules_intro')" />

        {{-- La escala de altura sale del DATO (`RuleBoard::heightScale`): sin ninguna zona con altura
             declarada no se pinta, y el techo es el mismo que el del eje de la portada. --}}
        @if ($scale)
            <section class="rules-axis" aria-labelledby="rules-axis-title">
                <h2 class="rules-axis__title" id="rules-axis-title">{{ __('site.rules_axis_title') }}</h2>
                <div class="rules-axis__chart" aria-hidden="true">
                    <div class="rules-axis__ruler">
                        @foreach ($scale['ticks'] as $tick)
                            <span @class(['rules-axis__tick', 'rules-axis__tick--strong' => $tick['strong']]) style="top: {{ $tick['top'] }}%;">{{ $tick['label'] }}</span>
                        @endforeach
                    </div>
                    <div class="rules-axis__bands">
                        @foreach ($scale['bands'] as $band)
                            <div @class(['rules-axis__band', 'rules-axis__band--overlap' => $band['overlap']])
                                 style="top: {{ $band['top'] }}%; height: {{ $band['height'] }}%;@if ($band['color']) --band: {{ $band['color'] }};@endif">
                                <span>{{ $band['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
                @if ($site['zones_access'] ?? null)
                    <p class="rules-axis__note">{{ $site['zones_access'] }}</p>
                @endif
            </section>
        @endif

        {{-- Las normas por momento: el orden lo da `board` (la constante, no el panel). El porqué se
             pinta SOLO si la norma lo tiene. --}}
        @foreach ($board['groups'] as $group)
            <section class="rules-group" aria-labelledby="rules-{{ $group['moment'] }}">
                <div class="rules-group__head">
                    <p class="rules-group__label">{{ __('site.rules_moment.'.$group['moment'].'.label') }}</p>
                    <h2 class="rules-group__title" id="rules-{{ $group['moment'] }}">{{ __('site.rules_moment.'.$group['moment'].'.title') }}</h2>
                </div>
                <ul class="rules-list" role="list">
                    @foreach ($group['rules'] as $rule)
                        <li class="rule-card">
                            <h3 class="rule-card__name">{{ $rule->tr('name') }}</h3>
                            <p class="rule-card__desc">{{ $rule->tr('description') }}</p>
                            @if ($rule->tr('reason'))
                                <p class="rule-card__why">{{ $rule->tr('reason') }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach

        {{-- ❗❗ LAS QUE EL PANEL DEJÓ SIN MOMENTO SE PUBLICAN IGUAL, al final y bajo un rótulo neutro:
             sin este bloque, una norma creada con prisa desaparecería de la web sin fallar. --}}
        @if ($board['ungrouped']->isNotEmpty())
            <section class="rules-group" aria-labelledby="rules-other">
                <div class="rules-group__head">
                    <h2 class="rules-group__title" id="rules-other">{{ __('site.rules_other') }}</h2>
                </div>
                <ul class="rules-list" role="list">
                    @foreach ($board['ungrouped'] as $rule)
                        <li class="rule-card">
                            <h3 class="rule-card__name">{{ $rule->tr('name') }}</h3>
                            <p class="rule-card__desc">{{ $rule->tr('description') }}</p>
                            @if ($rule->tr('reason'))
                                <p class="rule-card__why">{{ $rule->tr('reason') }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <div class="rules-close">
            {{-- La chapa del descargo sigue al MISMO ajuste que el pie (`#216`): sin exención, sin chapa. --}}
            @if ($waiverEnabled)
                <aside class="rules-waiver">
                    <h2 class="rules-waiver__title">{{ __('site.rules_waiver_title') }}</h2>
                    <p class="rules-waiver__text">{{ __('site.rules_waiver_text') }}</p>
                    <a class="rules-waiver__cta" href="{{ route('legal.waiver') }}" data-tap>
                        <span>{{ __('site.rules_waiver_cta') }}</span>
                        <span aria-hidden="true">&rarr;</span>
                    </a>
                </aside>
            @endif

            <div class="rules-foot">
                <p class="rules-foot__staff">{{ __('site.rules_staff') }}</p>
                {{-- La fecha sale de la norma tocada más recientemente, NUNCA de hoy; y cómo se escribe el
                     mes con su año lo decide el producto (`LocalDate`, `#656`). --}}
                @if ($board['updatedAt'])
                    <p class="rules-foot__updated">{{ __('site.rules_updated', ['fecha' => \App\Domain\Platform\Services\LocalDate::monthYear($board['updatedAt'])]) }}</p>
                @endif
            </div>
        </div>
        <x-site.link-bands />
    </main>

    <x-site.footer />
</div>
</x-layout>
