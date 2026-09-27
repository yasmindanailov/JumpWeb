{{--
    UNA cifra del cuadro con su anatomía (T0b de `specs/analitica-para-decidir.md` §4.2, `#755`): el marcado de la
    tarjeta de Filament (`filament-widgets::stats-overview-widget.stat`, las mismas clases: se ve igual) y, debajo del
    cambio, las notas (el intervalo de una tasa, «pocos datos», «no es un cambio claro»), el contexto y «¿Cómo se
    calcula?». Este último es un `<details>`: se abre con el dedo en la tablet (sin hover) y sin JavaScript, con una
    zona de toque de 44 px. Todo lo decide `Metric`; aquí solo se pinta.

    En «Resumen» (T3a, `#759`) la tarjeta lleva además un enlace a su pestaña: un enlace aparte, al pie, y no la tarjeta
    entera (dentro va el `<details>`, que no puede vivir dentro de un `<a>`).

    @var \App\Filament\Analytics\Metric $metric
    @var array{line: ?string, color: string, icon: ?\Filament\Support\Icons\Heroicon, notes: list<string>} $reading
    @var array{url: string, label: string}|null $link
--}}
@php
    use Filament\Support\View\ComponentAttributeBag as FilamentComponentAttributeBag;
    use Filament\Widgets\View\Components\StatsOverviewWidgetComponent\StatComponent\DescriptionComponent;
@endphp

<div
    {{
        $getExtraAttributeBag()
            ->class(['fi-wi-stats-overview-stat'])
            ->merge([
                'data-metric' => $metric->key,
                'data-metric-color' => $reading['color'],
            ])
    }}
>
    <div class="fi-wi-stats-overview-stat-content">
        <div class="fi-wi-stats-overview-stat-label-ctn">
            <span class="fi-wi-stats-overview-stat-label">
                {{ $getLabel() }}
            </span>
        </div>

        <div class="fi-wi-stats-overview-stat-value">
            {{ $getValue() }}
        </div>

        @if ($reading['line'] !== null)
            <div
                {{ (new FilamentComponentAttributeBag)->color(DescriptionComponent::class, $reading['color'])->class(['fi-wi-stats-overview-stat-description']) }}
                data-metric-change
            >
                <span>{{ $reading['line'] }}</span>

                @if ($reading['icon'] !== null)
                    {{ \Filament\Support\generate_icon_html($reading['icon'], attributes: new \Filament\Support\View\ComponentAttributeBag) }}
                @endif
            </div>
        @endif

        @foreach ($reading['notes'] as $note)
            <p class="text-xs text-gray-500 dark:text-gray-400" data-metric-note>{{ $note }}</p>
        @endforeach

        @if ($metric->detail !== null)
            <p class="text-sm text-gray-500 dark:text-gray-400" data-metric-detail>{{ $metric->detail }}</p>
        @endif

        <details class="text-sm text-gray-600 dark:text-gray-300" data-metric-how>
            <summary class="inline-flex min-h-11 cursor-pointer select-none items-center font-medium text-primary-600 dark:text-primary-400">
                {{ __('admin.analytics.metric.how') }}
            </summary>
            <p class="pb-1">{{ $metric->how }}</p>
        </details>

        @if (($link ?? null) !== null)
            <a href="{{ $link['url'] }}" class="inline-flex min-h-11 items-center text-sm font-medium text-primary-600 hover:underline dark:text-primary-400" data-metric-link>
                {{ $link['label'] }}
            </a>
        @endif
    </div>
</div>
