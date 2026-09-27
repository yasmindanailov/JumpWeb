{{--
    UNA tabla de «Analítica», con sus celdas YA formateadas (`tables.blade.php` las recorre; «Calidad del dato» pinta la suya
    bajo sus tarjetas, T3a). Nada se calcula aquí y todo va escapado. Los importes no se parten: las celdas numéricas van sin
    salto y la tabla se desplaza sola si no cabe.

    @var array{heading: string, columns: list<string>, rows: list<list<string>>, wide?: bool} $table
--}}
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
