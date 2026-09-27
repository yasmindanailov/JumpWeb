{{--
    El MAPA DE CALOR de la ocupación (`specs/analitica-para-decidir.md` §4.8 y §4.11, la T2): día × hora, con el %
    ESCRITO en cada celda y su tooltip (`title`, que también lee el lector de pantalla por `aria-label`). El color es
    magnitud en UN tono, `color-mix()` del azul validado con transparente: sobre el papel y sobre el fondo oscuro sale su
    propio paso, sin invertir nada. Texto en tinta (gris 950 / blanco en oscuro) salvo en el paso lleno, en blanco.
    Nada se calcula aquí: el widget entrega las celdas ya escritas.
--}}
<x-filament-widgets::widget>
    <x-filament::section :heading="$heading" :description="$description">
        @if ($rows === [])
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('admin.analytics.money.empty') }}</p>
        @else
            <div class="overflow-x-auto" data-occupancy-heatmap>
                <table class="w-full border-separate text-xs" style="border-spacing: 2px">
                    <thead>
                        <tr>
                            {{-- La columna de los días va FIJA a la izquierda: en móvil la tabla se desplaza en su caja
                                 y, sin esto, al mirar las 18 h ya no se sabe de qué día es la fila. --}}
                            <th class="sticky start-0 z-10 bg-white py-1 pe-2 text-start font-medium text-gray-500 dark:bg-gray-900 dark:text-gray-400"></th>
                            @foreach ($hours as $hour)
                                <th scope="col" class="px-1 py-1 text-center font-medium tabular-nums text-gray-500 dark:text-gray-400">{{ $hour }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <th scope="row" class="sticky start-0 z-10 whitespace-nowrap bg-white py-1 pe-2 text-start font-medium text-gray-700 dark:bg-gray-900 dark:text-gray-300">{{ $row['day'] }}</th>
                                @foreach ($row['cells'] as $cell)
                                    @if ($cell['empty'])
                                        <td class="min-w-11 rounded px-1 py-2 text-center text-gray-400 dark:text-gray-500 bg-gray-100 dark:bg-white/5"
                                            title="{{ $cell['title'] }}" aria-label="{{ $cell['title'] }}" data-heat="empty">{{ $cell['label'] }}</td>
                                    @else
                                        <td @class([
                                                'min-w-11 rounded px-1 py-2 text-center font-medium tabular-nums',
                                                'text-white' => $cell['strong'],
                                                'text-gray-950 dark:text-white' => ! $cell['strong'],
                                            ])
                                            style="background-color: color-mix(in srgb, {{ $hue }} {{ $cell['mix'] }}%, transparent)"
                                            title="{{ $cell['title'] }}" aria-label="{{ $cell['title'] }}" data-heat="{{ $cell['mix'] }}">{{ $cell['label'] }}</td>
                                    @endif
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
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
