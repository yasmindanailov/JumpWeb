{{--
    «Por atender» (`specs/encuestas.md` §4.4, T4): las respuestas con una escala baja de los últimos 30 días. Misma
    caja que las tablas del cuadro; la ÚNICA diferencia es la última columna, que enlaza a la ficha del cliente
    cuando quien mira tiene `customers.insights` (el widget lo decide; aquí solo se pinta lo que llega). Todo
    escapado: el texto libre lo escribió un cliente.
--}}
<x-filament-widgets::widget>
    <x-filament::section :heading="$heading" :description="$description">
        <div data-surveys-attention="{{ count($rows) }}">
            @if ($rows === [])
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('admin.analytics.surveys.attention_empty') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr>
                                @foreach ($columns as $column)
                                    <th class="py-1 pe-3 text-start font-medium text-gray-500 dark:text-gray-400">{{ $column }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr class="border-t border-gray-100 dark:border-white/10 align-top" data-surveys-attention-row>
                                    <td class="whitespace-nowrap py-1 pe-3 tabular-nums text-gray-950 dark:text-white">{{ $row['on'] }}</td>
                                    <td class="whitespace-nowrap py-1 pe-3 text-gray-950 dark:text-white">{{ $row['channel'] }}</td>
                                    <td class="py-1 pe-3 text-gray-950 dark:text-white">{{ $row['survey'] }}</td>
                                    <td class="whitespace-nowrap py-1 pe-3 tabular-nums font-medium text-danger-600 dark:text-danger-400">{{ $row['score'] }}</td>
                                    <td class="py-1 pe-3 text-gray-700 dark:text-gray-300">{{ $row['text'] }}</td>
                                    <td class="whitespace-nowrap py-1 pe-3">
                                        @if ($row['url'] !== null)
                                            <a href="{{ $row['url'] }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400" data-surveys-attention-person>{{ $row['person'] }}</a>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
