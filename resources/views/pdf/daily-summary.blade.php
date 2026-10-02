{{--
    Resumen — PDF A4 HORIZONTAL imprimible para la operativa física. Listado de las
    reservas/entradas de UN día o —desde la L5 de `#876` (`#879`)— de su SEMANA o de su
    MES, por días y por hora, con una casilla ☐ por fila para tachar a mano. Con lo que el
    owner pidió al verla: el homenajeado con su edad, la merienda que será y la tarta; sin
    tipo, lista ni cobro. Renderizado con dompdf (subset de CSS: layout por <table>). Se
    fuerza en español desde el controlador.

    @var \App\Domain\Booking\Services\DailyReservationsSummary $summary
--}}
@php
    use App\Domain\Booking\Services\DailyReservationsSummary;
    use App\Domain\Platform\Services\DisplayTime;

    $esDia = $summary->period === DailyReservationsSummary::PERIOD_DAY;
    $titulo = __(match ($summary->period) {
        DailyReservationsSummary::PERIOD_WEEK => 'admin.calendar.day_summary.title_week',
        DailyReservationsSummary::PERIOD_MONTH => 'admin.calendar.day_summary.title_month',
        default => 'admin.calendar.day_summary.title',
    });
    $cuando = match ($summary->period) {
        DailyReservationsSummary::PERIOD_WEEK => __('admin.calendar.day_summary.week_range', [
            'from' => $summary->from->isoFormat('D [de] MMMM'), 'to' => $summary->to->isoFormat('D [de] MMMM YYYY'),
        ]),
        DailyReservationsSummary::PERIOD_MONTH => ucfirst($summary->from->isoFormat('MMMM [de] YYYY')),
        default => ucfirst($summary->date->isoFormat('dddd D [de] MMMM YYYY')),
    };
    $columnas = 9;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }} · {{ $cuando }}</title>
    <style>
        @page { margin: 12mm 12mm; }
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #14130f; font-size: 9.5px; line-height: 1.35; margin: 0; }
        table { border-collapse: collapse; width: 100%; }

        .wordmark { font-size: 14px; font-weight: bold; letter-spacing: .3px; }
        .wordmark .dot { color: #ff5b22; }
        .doc-title { font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; }
        .date { font-size: 20px; font-weight: bold; }
        .meta { font-size: 11px; color: #374151; margin-top: 2px; }
        .meta b { color: #14130f; }

        .rule { border: none; border-top: 1.5px solid #e5e7eb; margin: 9px 0; }

        table.list { margin-top: 2px; }
        table.list thead { display: table-header-group; }
        table.list th {
            background: #f3f4f6;
            text-align: left;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: #6b7280;
            padding: 5px 5px;
            border-bottom: 1.5px solid #d1d5db;
        }
        table.list td {
            padding: 5px 5px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }
        table.list tr.even td { background: #fafafa; }
        table.list tr.dia td {
            background: #ffffff;
            padding: 9px 5px 4px;
            border-bottom: 1.5px solid #14130f;
            font-size: 11px;
            font-weight: bold;
        }
        .col-c { text-align: center; }
        .check {
            display: inline-block;
            width: 13px; height: 13px;
            border: 1.5px solid #14130f;
        }
        .swatch {
            display: inline-block;
            width: 8px; height: 8px;
            border-radius: 2px;
            margin-right: 5px;
        }
        .hora { font-weight: bold; white-space: nowrap; }
        .product { font-weight: bold; }
        .muted { color: #6b7280; }

        .empty {
            margin-top: 24px;
            text-align: center;
            color: #6b7280;
            font-size: 13px;
            padding: 20px;
            border: 1px dashed #d1d5db;
            border-radius: 8px;
        }

        .footer { margin-top: 12px; border-top: 1px solid #e5e7eb; padding-top: 5px; font-size: 8.5px; color: #9ca3af; }
    </style>
</head>
<body>

    {{-- Cabecera: wordmark + título (izq); el día o el periodo + filtro + totales (dcha). --}}
    <table>
        <tr>
            <td style="width: 45%;">
                <div class="wordmark">{{ \Illuminate\Support\Str::upper((string) (\App\Domain\Platform\Models\Setting::value('business.name') ?: config('app.name'))) }}<span class="dot">.</span></div>
                <div class="doc-title">{{ $titulo }}</div>
            </td>
            <td style="width: 55%; text-align: right;">
                <div class="date">{{ $cuando }}</div>
                <div class="meta">
                    <b>{{ trans_choice('admin.calendar.day_summary.count_reservations', $summary->count(), ['count' => $summary->count()]) }}</b>
                    @if ($summary->guestsTotal() > 0)
                        · {{ __('admin.calendar.day_summary.total_guests', ['count' => $summary->guestsTotal()]) }}
                    @endif
                    @if ($summary->entriesTotal() > 0)
                        · {{ __('admin.calendar.day_summary.total_entries', ['count' => $summary->entriesTotal()]) }}
                    @endif
                    <span class="muted">· {{ $summary->filterLabel() }}</span>
                </div>
            </td>
        </tr>
    </table>

    <hr class="rule">

    @if ($summary->isEmpty())
        <div class="empty">{{ __($esDia ? 'admin.calendar.day_summary.empty' : 'admin.calendar.day_summary.empty_period') }}</div>
    @else
        <table class="list">
            <thead>
                <tr>
                    <th class="col-c" style="width: 3%;">&nbsp;</th>
                    <th style="width: 9%;">{{ __('admin.calendar.day_summary.col_time') }}</th>
                    <th style="width: 21%;">{{ __('admin.calendar.day_summary.col_product') }}</th>
                    <th style="width: 13%;">{{ __('admin.calendar.day_summary.col_customer') }}</th>
                    <th style="width: 9%;">{{ __('admin.calendar.day_summary.col_phone') }}</th>
                    <th style="width: 8%;">{{ __('admin.calendar.day_summary.col_qty') }}</th>
                    <th style="width: 13%;">{{ __('admin.calendar.day_summary.col_celebrant') }}</th>
                    <th style="width: 12%;">{{ __('admin.calendar.day_summary.col_snack') }}</th>
                    <th style="width: 12%;">{{ __('admin.calendar.day_summary.col_cake') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($summary->days() as $dia)
                    {{-- En la semana y el mes, un título por día; en el del día, la cabecera ya lo dice. --}}
                    @unless ($esDia)
                        <tr class="dia"><td colspan="{{ $columnas }}">{{ ucfirst($dia['date']->isoFormat('dddd D [de] MMMM')) }}</td></tr>
                    @endunless
                    @foreach ($dia['rows'] as $i => $row)
                        <tr class="{{ $i % 2 === 1 ? 'even' : '' }}">
                            <td class="col-c"><span class="check"></span></td>
                            <td><span class="hora">{{ $row['time'] ?? '—' }}</span></td>
                            <td>
                                <span class="swatch" style="background: {{ $row['zoneColor'] }};"></span><span class="product">{{ $row['product'] }}</span>
                                {{-- Complementos VIVOS de la reserva (`specs/hora-extra.md` §8.6): la hora
                                     extra es inventario del día y sin esta línea el resumen no la decía. --}}
                                @if ($row['addons'] !== [])
                                    <br><span class="muted">@foreach ($row['addons'] as $addon){{ $loop->first ? '' : ' · ' }}+ {{ $addon['quantity'] }} × {{ $addon['name'] }}@endforeach</span>
                                @endif
                            </td>
                            <td>{{ $row['customer'] }}</td>
                            <td class="muted">{{ $row['phone'] ?? '—' }}</td>
                            <td>{{ $row['quantityLabel'] }}</td>
                            <td>{{ $row['celebrant'] ?? '—' }}@if ($row['age'] !== null)<br><span class="muted">{{ $row['age'] }}</span>@endif</td>
                            <td>{{ $row['snack'] ?? '—' }}</td>
                            <td>{{ $row['cake'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        {{ __('admin.calendar.day_summary.printed_at', ['when' => DisplayTime::format(now(), 'd/m/Y H:i')]) }}
    </div>

</body>
</html>
