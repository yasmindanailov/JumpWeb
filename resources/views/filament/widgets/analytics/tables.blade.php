{{--
    Tablas de un informe de «Analítica» (`specs/analitica.md` §4.5): el widget entrega el título, la nota y
    una lista de tablas con celdas YA formateadas; aquí no se calcula nada y todo va escapado (ninguna
    columna analítica pasa por `->html()`). Una tabla `wide` ocupa las dos columnas. Los importes no se parten
    (la captura móvil del 24-09 los mostraba en dos líneas): las celdas numéricas van sin salto y cada tabla
    se desplaza sola si no cabe.

    T2f: la sección nace PLEGADA —el owner vio el cuadro y pidió menos tablas: los gráficos van arriba y el
    detalle en cifras se abre cuando hace falta— y recuerda si se abrió (por widget, en el navegador). Las
    tablas siguen en el DOM plegadas: son la vista de tabla de los gráficos y lo que lleva el CSV.
--}}
<x-filament-widgets::widget>
    <x-filament::section
        :heading="$heading"
        :description="$description"
        collapsible
        collapsed
        persist-collapsed
        :collapse-id="'analitica-' . \Illuminate\Support\Str::kebab(class_basename($this))"
    >
        <div class="grid gap-6 md:grid-cols-2">
            @foreach ($tables as $table)
                <div @class(['md:col-span-2' => $table['wide'] ?? false])>
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $table['heading'] }}</h3>

                    @if ($table['rows'] === [])
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('admin.analytics.money.empty') }}</p>
                    @else
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
