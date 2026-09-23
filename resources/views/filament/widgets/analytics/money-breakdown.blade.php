{{--
    El desglose del dinero (`specs/analitica.md` §4.5, T2a): la serie por día o semana (a todo el ancho) y
    cinco tablas pequeñas —canal, método, producto, la señal y lo perdido— con celdas YA formateadas por el
    widget. Aquí no se calcula nada y todo va escapado: ninguna columna analítica pasa por `->html()`.
--}}
<x-filament-widgets::widget>
    <x-filament::section
        :heading="__('admin.analytics.money.breakdown_heading')"
        :description="__('admin.analytics.money.method_note')"
    >
        <div class="grid gap-6 md:grid-cols-2">
            @foreach ($tables as $table)
                <div @class(['md:col-span-2' => $table['wide'] ?? false])>
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $table['heading'] }}</h3>

                    @if ($table['rows'] === [])
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('admin.analytics.money.empty') }}</p>
                    @else
                        {{-- Los importes no se parten (la captura móvil del 24-09 los mostraba en dos líneas):
                             las celdas numéricas van sin salto y la tabla se desplaza sola si no cabe. --}}
                        <div class="mt-2 overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr>
                                        @foreach ($table['columns'] as $i => $column)
                                            <th class="{{ $i === 0 ? 'text-start' : 'text-end whitespace-nowrap' }} py-1 pe-2 font-medium text-gray-500 dark:text-gray-400">{{ $column }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($table['rows'] as $row)
                                        <tr class="border-t border-gray-100 dark:border-white/10">
                                            @foreach ($row as $i => $cell)
                                                <td class="{{ $i === 0 ? 'text-start' : 'text-end whitespace-nowrap tabular-nums' }} py-1 pe-2 text-gray-950 dark:text-white">{{ $cell }}</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
