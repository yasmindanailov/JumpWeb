{{--
    «Lo que ha cambiado» (T3c·1 de `specs/analitica-para-decidir.md` §4.5; `#791`): las frases que elige `Changes`, con el
    icono y la palabra del veredicto (el color, de refuerzo, como en la tarjeta) y el enlace a su pestaña; «y N más» si no
    caben; y, sin ninguna, POR QUÉ no hay ninguna. Nada se calcula aquí y todo va escapado.

    @var list<array{key: string, state: string, tone: string, sentence: string, link: array{url: string, label: string}|null}> $items
    @var int $more
    @var string|null $empty
--}}
<x-filament-widgets::widget>
    <x-filament::section :heading="__('admin.analytics.changes.heading')" :description="__('admin.analytics.changes.note')">
        @if ($items === [])
            <p class="text-sm text-gray-700 dark:text-gray-300" data-analytics-changes-empty>{{ $empty }}</p>
        @else
            <ul class="divide-y divide-gray-100 dark:divide-white/10" data-analytics-changes>
                @foreach ($items as $item)
                    <li class="flex flex-col gap-1 py-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4" data-change="{{ $item['key'] }}" data-change-tone="{{ $item['tone'] }}">
                        <p @class([
                            'flex items-start gap-1.5 text-sm font-medium',
                            'text-success-700 dark:text-success-400' => $item['tone'] === \App\Filament\Analytics\Metric::TONE_GOOD,
                            'text-warning-700 dark:text-warning-400' => $item['tone'] === \App\Filament\Analytics\Metric::TONE_WATCH,
                            'text-gray-700 dark:text-gray-300' => $item['tone'] === \App\Filament\Analytics\Metric::TONE_NEUTRAL,
                        ])>
                            {{ \Filament\Support\generate_icon_html(match ($item['tone']) {
                                \App\Filament\Analytics\Metric::TONE_GOOD => \Filament\Support\Icons\Heroicon::OutlinedCheckCircle,
                                \App\Filament\Analytics\Metric::TONE_WATCH => \Filament\Support\Icons\Heroicon::OutlinedExclamationTriangle,
                                default => \Filament\Support\Icons\Heroicon::OutlinedArrowsUpDown,
                            }, attributes: (new \Filament\Support\View\ComponentAttributeBag)->class(['mt-0.5 h-4 w-4 shrink-0'])) }}
                            <span>{{ $item['sentence'] }}</span>
                        </p>
                        @if ($item['link'] !== null)
                            <a href="{{ $item['link']['url'] }}" class="inline-flex min-h-11 shrink-0 items-center text-sm font-medium text-primary-600 hover:underline sm:min-h-0 dark:text-primary-400">
                                {{ $item['link']['label'] }}
                            </a>
                        @endif
                    </li>
                @endforeach
            </ul>
            @if ($more > 0)
                <p class="pt-2 text-sm text-gray-600 dark:text-gray-400" data-analytics-changes-more>{{ trans_choice('admin.analytics.changes.more', $more, ['n' => $more]) }}</p>
            @endif
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
