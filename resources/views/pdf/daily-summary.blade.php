{{--
    Resumen del día — PDF A4 HORIZONTAL imprimible para la operativa física.
    Listado de las reservas/entradas de UN día, ordenado por hora, con una
    casilla ☐ por fila para tachar a mano. Documento operativo: sin datos de
    cobro. Renderizado con dompdf (subset de CSS: layout por <table>). Se fuerza
    en español desde el controlador.

    @var \App\Support\DailyReservationsSummary $summary
--}}
@php
    use App\Domain\Platform\Services\DisplayTime;

    $rows = $summary->rows();
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ __('admin.calendar.day_summary.title') }} · {{ $summary->date->isoFormat('D/MM/YYYY') }}</title>
    <style>
        @page { margin: 12mm 12mm; }
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #14130f; font-size: 10px; line-height: 1.35; margin: 0; }
        table { border-collapse: collapse; width: 100%; }

        .wordmark { font-size: 14px; font-weight: bold; letter-spacing: .3px; }
        .wordmark .dot { color: #ff5b22; }
        .doc-title { font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; }
        .date { font-size: 22px; font-weight: bold; }
        .meta { font-size: 11px; color: #374151; margin-top: 2px; }
        .meta b { color: #14130f; }

        .rule { border: none; border-top: 1.5px solid #e5e7eb; margin: 9px 0; }

        table.list { margin-top: 2px; }
        table.list thead { display: table-header-group; }
        table.list th {
            background: #f3f4f6;
            text-align: left;
            font-size: 8.5px;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: #6b7280;
            padding: 5px 6px;
            border-bottom: 1.5px solid #d1d5db;
        }
        table.list td {
            padding: 6px 6px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }
        table.list tr.even td { background: #fafafa; }
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
        .hora-end { color: #6b7280; font-weight: normal; }
        .product { font-weight: bold; }
        .muted { color: #6b7280; }
        .type-pill {
            display: inline-block;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: .3px;
            padding: 1px 6px;
            border-radius: 7px;
            border: 1px solid #d1d5db;
            color: #374151;
        }

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

    {{-- Cabecera: wordmark + título (izq); fecha + filtro + totales (dcha). --}}
    <table>
        <tr>
            <td style="width: 45%;">
                <div class="wordmark">{{ \Illuminate\Support\Str::upper((string) (\App\Domain\Platform\Models\Setting::value('business.name') ?: config('app.name'))) }}<span class="dot">.</span></div>
                <div class="doc-title">{{ __('admin.calendar.day_summary.title') }}</div>
            </td>
            <td style="width: 55%; text-align: right;">
                <div class="date">{{ ucfirst($summary->date->isoFormat('dddd D [de] MMMM YYYY')) }}</div>
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
        <div class="empty">{{ __('admin.calendar.day_summary.empty') }}</div>
    @else
        <table class="list">
            <thead>
                <tr>
                    <th class="col-c" style="width: 4%;">&nbsp;</th>
                    <th style="width: 11%;">{{ __('admin.calendar.day_summary.col_time') }}</th>
                    <th style="width: 10%;">{{ __('admin.calendar.day_summary.col_type') }}</th>
                    <th style="width: 22%;">{{ __('admin.calendar.day_summary.col_product') }}</th>
                    <th style="width: 18%;">{{ __('admin.calendar.day_summary.col_customer') }}</th>
                    <th style="width: 12%;">{{ __('admin.calendar.day_summary.col_phone') }}</th>
                    <th style="width: 9%;">{{ __('admin.calendar.day_summary.col_qty') }}</th>
                    <th style="width: 14%;">{{ __('admin.calendar.day_summary.col_celebrant') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $i => $row)
                    <tr class="{{ $i % 2 === 1 ? 'even' : '' }}">
                        <td class="col-c"><span class="check"></span></td>
                        <td>
                            <span class="hora">{{ $row['time'] ?? '—' }}</span>
                        </td>
                        <td><span class="type-pill">{{ $row['typeLabel'] }}</span></td>
                        <td>
                            <span class="swatch" style="background: {{ $row['zoneColor'] }};"></span><span class="product">{{ $row['product'] }}</span>
                        </td>
                        <td>{{ $row['customer'] }}</td>
                        <td class="muted">{{ $row['phone'] ?? '—' }}</td>
                        <td>{{ $row['quantityLabel'] }}</td>
                        <td>{{ $row['celebrant'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        {{ __('admin.calendar.day_summary.printed_at', ['when' => DisplayTime::format(now(), 'd/m/Y H:i')]) }}
    </div>

</body>
</html>
