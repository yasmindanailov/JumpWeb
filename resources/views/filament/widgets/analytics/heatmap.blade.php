{{--
    El MAPA DE CALOR de la ocupación (`specs/analitica-para-decidir.md` §4.8 y §4.11, la T2): día × hora, con el %
    ESCRITO en cada celda y su tooltip (`title`, que también lee el lector de pantalla por `aria-label`). El color es
    magnitud en UN tono, `color-mix()` del azul validado con transparente: sobre el papel y sobre el fondo oscuro sale su
    propio paso, sin invertir nada. Texto en tinta (gris 950 / blanco en oscuro) salvo en el paso lleno, en blanco.
    Nada se calcula aquí: el widget entrega las celdas ya escritas. La rejilla es la de `heatmap-grid` (la comparten los
    mapas de los correos, `email-heatmaps`).
--}}
<x-filament-widgets::widget>
    <x-filament::section :heading="$heading" :description="$description">
        @if ($rows === [])
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('admin.analytics.money.empty') }}</p>
        @else
            <div class="overflow-x-auto" data-occupancy-heatmap>
                @include('filament.widgets.analytics.heatmap-grid')
            </div>
            <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400" aria-hidden="true">
                @foreach ($legend as $step)
                    <span class="inline-flex items-center gap-1">
                        <span class="inline-block h-3 w-5 rounded" style="background-color: color-mix(in srgb, {{ $hue }} {{ $step['mix'] }}%, transparent)"></span>
                        {{ $step['label'] }}
                    </span>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
