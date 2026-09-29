{{--
    LA REJILLA de un mapa de calor día × hora y su leyenda (la de la ocupación, `heatmap.blade.php`, y las de los correos,
    `email-heatmaps.blade.php`): cada celda con su cifra ESCRITA y su tooltip (`title`, que también lee el lector de pantalla
    por `aria-label`); el color es magnitud en UN tono (`color-mix()` con transparente), y el texto en tinta salvo en el paso
    lleno, en blanco. Nada se calcula aquí: el widget entrega las celdas ya escritas.

    @var list<string> $hours
    @var list<array{day: string, cells: list<array<string, mixed>>}> $rows
    @var string $hue
    @var list<array{mix: int, label: string}> $legend
--}}
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
