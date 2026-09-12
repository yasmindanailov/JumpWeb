@php
    // Meta description (SEO, INVISIBLE en la página): resumen a partir del contenido real, en vez de
    // repetir el título. ⚠️ Recorre TODOS los grupos y las sin agrupar: componer la frase solo con
    // el primer grupo dejaría fuera justo las de dentro.
    $todas = collect($board['groups'])->flatMap(fn (array $g) => $g['rules'])->concat($board['ungrouped']);
    $rulesMeta = $todas->isNotEmpty()
        ? \Illuminate\Support\Str::limit($todas->take(6)->map(fn ($r) => $r->tr('name'))->implode(' · '), 155)
        : __('site.rules_title');
@endphp
<x-layout :title="__('site.rules_title')" :description="$rulesMeta">
<div x-data="landing">
    <x-site.nav />

    {{-- ══ /normas · LO QUE HAY QUE CUMPLIR ═══════════════════════════════════════════════════════
         Carril de diseño Fase 3 · T3b (`DECISIONES #533`). Artboard `Normas PJP` **1a** (móvil) +
         **1b** (escritorio).

         ▶ **La espina es el MOMENTO**: antes de venir · en la puerta · dentro. Es el único orden que
         el visitante puede usar —las dos primeras deciden si entras, la tercera no cambia ninguna
         decisión—, y sustituye al orden de panel, que mezclaba las dos cosas.

         ⚠️⚠️ **Lo que el artboard pide y NO se dibuja, con su motivo**: su escala lleva una tercera
         banda, «de 1 a 1,30 m con tutor», y **ese 1,00 no es un dato**: vive dentro del texto de la
         norma de Jump, que es donde debe estar (una excepción con condiciones no es un umbral).
         ⚠️ Y sus EDADES son las del catálogo y las zonas —Kids 4–7, Jump desde 8—, no las del
         artboard (2–6 / desde 7): `[DECIDIDO owner]`, manda el catálogo para el producto y la zona
         para el acceso. El texto vive en el panel, así que esto no lo decide la plantilla. --}}
    <main id="main" class="page page--rules wrap">
        <x-site.page-head :title="__('site.rules_headline')" :lede="__('site.rules_intro')">
            {{-- A2 · la trama que se apaga. **UNA por pantalla** (`[DECIDIDO owner, 2026-08-31]`),
                 no una por tarjeta. Va en la CABECERA y dentro del conjunto rótulo + titular, que la
                 recorta: contra la cabecera entera caía detrás de la entradilla (`#525`). --}}
            <x-slot:deco><div class="grain grain--fade" aria-hidden="true"></div></x-slot:deco>
        </x-site.page-head>

        {{-- ══ LA ALTURA, DE UN VISTAZO ════════════════════════════════════════════════════════
             La escala que abre la página. ⚠️ **Sale del DATO** (`zones.height_min_cm/max`) y comparte
             techo con el eje de las tarjetas de zona de la portada: dos escalas con topes distintos
             pondrían el mismo 1,30 a distinta altura en dos pantallas del mismo sitio.
             ⚠️ Sin ninguna zona con altura declarada no se pinta: vacío es una respuesta. --}}
        @if ($scale)
            <section class="rules-axis" aria-labelledby="rules-axis-title">
                <h2 class="rules-axis__title" id="rules-axis-title">{{ __('site.rules_axis_title') }}</h2>
                {{-- ⚠️ `aria-hidden`: es el DIBUJO de lo que las normas de abajo ya dicen con
                     palabras, y una escala de estatura no se recorre con un lector de pantalla. --}}
                <div class="rules-axis__chart" aria-hidden="true">
                    <div class="rules-axis__ruler">
                        <span class="rules-axis__top"><b>{{ __('site.rules_axis_label') }}</b> <i>{{ $scale['ceiling'] }}</i></span>
                        <span class="rules-axis__zero">{{ $scale['floor'] }}</span>
                    </div>
                    <div class="rules-axis__bands">
                        @foreach ($scale['bands'] as $band)
                            <div class="rules-axis__band" style="top: {{ $band['top'] }}%; height: {{ $band['height'] }}%;">
                                <span>{{ $band['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        {{-- ══ LAS NORMAS, POR MOMENTO ═════════════════════════════════════════════════════════
             ⚠️ El porqué se pinta SOLO si la norma lo tiene: no toda norma tiene motivo que contar,
             y una frase inventada para rellenar el hueco es peor que el hueco. --}}
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

        {{-- ❗❗ **LAS QUE EL PANEL DEJÓ SIN MOMENTO SE PUBLICAN IGUAL**, al final y bajo un rótulo
             neutro. Sin este bloque, una norma creada con prisa desaparecería de la web sin fallar y
             sin avisar — que es exactamente el modo de fallo que el momento opcional evita. --}}
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

        {{-- ══ EL CIERRE: lo que se firma y la línea del personal ══════════════════════════════
             ⚠️⚠️ La chapa es **la única superficie de tinta de la página**, y eso es lo que separa
             «lo que hay que cumplir» de «lo que has firmado». `data-surface="ink"` va en la TARJETA
             y no en su contenedor (`#484`): ahí además PINTA, y en un contenedor sin radio dejaría
             un rectángulo detrás.
             ⚠️ Solo si la instalación usa la exención — el MISMO criterio que el pie (`#216`). --}}
        <div class="rules-close">
            @if ($waiverEnabled)
                <aside class="rules-waiver" data-surface="ink">
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
                {{-- La fecha sale de la norma tocada más recientemente, NUNCA de hoy: escribir el mes
                     actual afirmaría una revisión que nadie ha hecho. Sin normas no se escribe. --}}
                @if ($board['updatedAt'])
                    <p class="rules-foot__updated">{{ __('site.rules_updated', ['fecha' => $board['updatedAt']->translatedFormat('F \d\e Y')]) }}</p>
                @endif
            </div>
        </div>
    </main>

    <x-site.footer />
</div>
</x-layout>
