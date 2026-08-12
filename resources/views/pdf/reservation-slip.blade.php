{{--
    Hoja de reserva — PDF A4 imprimible para la operativa física del parque
    (decisión #183). Documento OPERATIVO para el personal: identificación de la
    reserva, cuándo/qué/quién, complementos, totales del producto y un checkbox
    "Preparado" para tachar a mano. NUNCA incluye datos de cobro sensibles
    (tarjeta, gateway_order, auth_code).

    Renderizado con dompdf: SOLO el subconjunto de CSS que soporta (layout por
    <table>, nada de flexbox/grid). Fuente DejaVu Sans (acentos + € correctos).
    Se fuerza en español desde el controlador; el presenter `$slip` trae todo
    formateado.

    @var \App\Support\ReservationSlip $slip
--}}
@php
    use App\Models\Order;
    use App\Domain\Platform\Services\DisplayTime;
    use App\Support\ReservationSlip;

    // Badge de estado de pago: SOLO se muestra cuando el pedido NO está pagado
    // (pulido clienta: "Completado" no aporta). Para pending/cancelled/refunded/
    // expired sí es información operativa relevante (no entregar lo no pagado).
    $orderStatus = $slip->orderStatus();
    $statusPalette = [
        Order::STATUS_PENDING => ['#fef3c7', '#92400e'],
        Order::STATUS_CANCELLED => ['#fee2e2', '#991b1b'],
        Order::STATUS_REFUNDED => ['#fee2e2', '#991b1b'],
        Order::STATUS_EXPIRED => ['#f3f4f6', '#374151'],
    ];
    [$stBg, $stFg] = $statusPalette[$orderStatus] ?? ['#f3f4f6', '#374151'];

    $cancelled = $slip->isCancelled();
    $zoneColor = $slip->zoneColor();
    $fmt = fn (int $cents) => ReservationSlip::money($cents);

    // ¿Mostrar el desglose económico? Por defecto NO (hoja operativa de sala, sin importes ni
    // totales). El controlador lo activa con `?precios=1`. Los complementos contratados se listan
    // SIEMPRE (arriba); aquí solo se gobierna la sección de totales / a cobrar / a devolver.
    $showPrices = $showPrices ?? false;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ __('admin.orders.slip.title') }} · {{ $slip->code() }}</title>
    <style>
        @page { margin: 16mm 15mm; }
        * { box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            color: #14130f;
            font-size: 11px;
            line-height: 1.45;
            margin: 0;
        }
        table { border-collapse: collapse; width: 100%; }
        td { vertical-align: top; }

        .wordmark { font-size: 17px; font-weight: bold; letter-spacing: .3px; color: #14130f; }
        .wordmark .dot { color: #ff5b22; }
        .doc-title { font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; margin-top: 2px; }
        .code { font-size: 26px; font-weight: bold; letter-spacing: 1px; color: #14130f; }

        .badge {
            display: inline-block;
            padding: 3px 9px;
            border-radius: 9px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        .rule { border: none; border-top: 1.5px solid #e5e7eb; margin: 10px 0; }

        .prod-box {
            border: 1px solid #e5e7eb;
            border-left: 6px solid {{ $zoneColor }};
            border-radius: 8px;
            padding: 10px 12px;
            margin-bottom: 12px;
        }
        .prod-name { font-size: 16px; font-weight: bold; }

        /* Checkbox "Preparado" para tachar a mano (recuadro vacío + etiqueta). */
        .prepared-box {
            display: inline-block;
            width: 15px; height: 15px;
            border: 1.5px solid #14130f;
            vertical-align: middle;
        }
        .prepared-label { font-size: 12px; font-weight: bold; vertical-align: middle; padding-left: 6px; }

        .facts td {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 8px 10px;
        }
        .facts .gap { border: none; padding: 0; width: 8px; }
        .k { font-size: 9px; color: #6b7280; text-transform: uppercase; letter-spacing: .6px; }
        .v { font-size: 14px; font-weight: bold; margin-top: 2px; }
        .v-sub { font-size: 10px; color: #6b7280; font-weight: normal; }

        .sec { margin-top: 14px; }
        .sec-title {
            font-size: 10px; font-weight: bold; color: #374151;
            text-transform: uppercase; letter-spacing: .8px;
            border-bottom: 1px solid #e5e7eb; padding-bottom: 3px; margin-bottom: 6px;
        }
        .kv td { padding: 2px 0; }
        .kv .kv-label { color: #6b7280; width: 42%; }
        .kv .kv-value { font-weight: bold; }

        ul.addons { margin: 0; padding-left: 16px; }
        ul.addons li { margin-bottom: 2px; }
        .struck { text-decoration: line-through; color: #9ca3af; }
        .tag-cancel { font-size: 9px; color: #991b1b; font-weight: bold; }

        /* Badge INCLUIDO/GRATIS del complemento (verde sutil). */
        .badge-inc {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 8px;
            background: #d1fae5;
            color: #047857;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: .3px;
        }
        .t-free { color: #6b7280; font-weight: normal; font-size: 9px; }

        /* Totales del producto (espejo de la sub-card, con desglose por línea). */
        .totals td { padding: 2px 0; }
        .totals .t-label { color: #374151; }
        .totals .t-value { text-align: right; font-weight: bold; white-space: nowrap; padding-left: 12px; }
        .totals .t-total td { border-top: 1px solid #e5e7eb; padding-top: 5px; }
        .totals .t-total .t-label,
        .totals .t-total .t-value { color: #14130f; font-weight: bold; font-size: 12px; }
        .totals .t-refund .t-label,
        .totals .t-refund .t-value { color: #92400e; }

        /* Desglose económico en HOJA A4 APARTE (petición clienta 2026-06-15): un salto de página
           antes del bloque de importes, para que NUNCA comparta hoja con los datos operativos de la
           reserva. dompdf soporta `page-break-before`. Solo se renderiza con `?precios=1`; sin
           desglose no existe este wrapper → ni salto ni hoja en blanco extra. */
        .desglose-page { page-break-before: always; }

        .gate-box {
            margin-top: 12px;
            border: 1px solid #fcd34d;
            background: #fffbeb;
            border-radius: 8px;
            padding: 8px 11px;
        }
        .gate-title { font-size: 11px; font-weight: bold; color: #92400e; }
        .gate-amount { font-size: 15px; font-weight: bold; color: #92400e; }
        .gate-detail { font-size: 10px; color: #92400e; }

        .refund-box {
            margin-top: 12px;
            border: 1px solid #fca5a5;
            background: #fef2f2;
            border-radius: 8px;
            padding: 8px 11px;
        }
        .refund-title { font-size: 11px; font-weight: bold; color: #991b1b; }
        .refund-amount { font-size: 15px; font-weight: bold; color: #991b1b; }
        .refund-detail { font-size: 10px; color: #991b1b; }

        .cancel-banner {
            margin-bottom: 12px;
            border: 1px solid #fecaca;
            background: #fef2f2;
            color: #991b1b;
            border-radius: 8px;
            padding: 7px 11px;
            font-weight: bold;
            font-size: 12px;
        }

        .footer {
            margin-top: 18px;
            border-top: 1px solid #e5e7eb;
            padding-top: 6px;
            font-size: 9px;
            color: #9ca3af;
        }

        /* Tabla por-niño (#217): una fila por invitado. `thead` con
           `display: table-header-group` repite la cabecera si la tabla desborda a la 2.ª hoja
           A4 (patrón del listado del día); `page-break-inside: avoid` evita partir una fila. */
        table.guests { margin-top: 4px; }
        table.guests th, table.guests td {
            border: 1px solid #e5e7eb;
            padding: 5px 7px;
            text-align: left;
            font-size: 10px;
            vertical-align: top;
        }
        table.guests thead { display: table-header-group; }
        table.guests thead th {
            background: #f9fafb;
            font-size: 9px; color: #374151; text-transform: uppercase; letter-spacing: .4px; font-weight: bold;
        }
        table.guests tr { page-break-inside: avoid; }
        table.guests .g-num { width: 26px; text-align: center; color: #6b7280; font-weight: bold; }
        table.guests td.g-cell { height: 16px; }
        .guests-pending-note { font-size: 9px; color: #92400e; font-weight: bold; margin-bottom: 4px; }
    </style>
</head>
<body>

    {{-- Cabecera: wordmark + título (izq); código (dcha) con el estado SOLO si no
         está pagado (el "Completado" no aporta — pulido clienta). --}}
    <table>
        <tr>
            <td style="width: 55%;">
                <div class="wordmark">{{ \Illuminate\Support\Str::upper((string) (\App\Domain\Platform\Models\Setting::value('business.name') ?: config('app.name'))) }}<span class="dot">.</span></div>
                <div class="doc-title">{{ __('admin.orders.slip.title') }}</div>
            </td>
            <td style="width: 45%; text-align: right;">
                @if (! $slip->isPaid())
                    <span class="badge" style="background: {{ $stBg }}; color: {{ $stFg }};">{{ __('admin.orders.status.'.$orderStatus) }}</span>
                @endif
                <div class="code">{{ $slip->code() }}</div>
            </td>
        </tr>
    </table>

    <hr class="rule">

    @if ($cancelled)
        <div class="cancel-banner">{{ __('admin.orders.slip.cancelled_notice') }}</div>
    @endif

    {{-- Producto (el nombre ya incluye la zona, p. ej. "Cumpleaños Jump"; el color
         de zona = pulsera va en el acento lateral de la caja) + checkbox manual
         "Preparado" a la derecha para tachar a bolígrafo. --}}
    <div class="prod-box">
        <table>
            <tr>
                <td style="width: 65%;">
                    <span class="prod-name">{{ $slip->productName() }}</span>
                </td>
                <td style="width: 35%; text-align: right;">
                    <span class="prepared-box"></span><span class="prepared-label">{{ __('admin.orders.slip.prepared_check') }}</span>
                </td>
            </tr>
        </table>
    </div>

    {{-- Hechos clave: fecha/hora · cantidad · duración. --}}
    <table class="facts">
        <tr>
            <td style="width: 40%;">
                <div class="k">{{ __('admin.orders.slip.datetime_heading') }}</div>
                @if ($slip->hasSlot() && $slip->slotDate())
                    <div class="v">{{ ucfirst($slip->slotDate()->isoFormat('ddd D MMM YYYY')) }}</div>
                    <div class="v-sub">{{ $slip->slotWindow() }}</div>
                @else
                    <div class="v">{{ __('admin.orders.slip.no_slot') }}</div>
                @endif
            </td>
            <td class="gap"></td>
            <td style="width: 28%;">
                <div class="k">{{ $slip->quantityHeading() }}</div>
                <div class="v">{{ $slip->quantityLabel() }}</div>
            </td>
            <td class="gap"></td>
            <td style="width: 28%;">
                <div class="k">{{ __('admin.orders.slip.duration_heading') }}</div>
                <div class="v">{{ $slip->durationLabel() ?? '—' }}</div>
            </td>
        </tr>
    </table>

    {{-- Datos de la reserva: cumpleañero → padre/tutor (cliente) → teléfono →
         resto de datos del evento (orden pedido por la clienta). --}}
    @php $resRows = $slip->reservationDataRows(); @endphp
    @if (count($resRows) > 0)
        <div class="sec">
            <div class="sec-title">{{ __('admin.orders.slip.reservation_data_heading') }}</div>
            <table class="kv">
                @foreach ($resRows as $row)
                    <tr>
                        <td class="kv-label">{{ $row['label'] }}</td>
                        <td class="kv-value">{{ $row['value'] }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

    {{-- Formulario de reserva (post-form #217): una fila por invitado, justo tras los datos de la
         reserva (agrupado con ellos, ANTES de complementos/totales). Si el cliente aún no lo ha
         rellenado, las celdas van EN BLANCO para completarlas a mano en el parque. Puede ocupar una
         2.ª hoja A4 (hasta 20 niños): la cabecera se repite y las filas no se parten. --}}
    @if ($slip->hasGuestForm())
        @php $guestCols = $slip->guestColumns(); $guestRows = $slip->guestRows(); @endphp
        <div class="sec">
            <div class="sec-title">{{ __('admin.orders.slip.guests_heading') }}</div>
            @unless ($slip->guestFormComplete())
                <div class="guests-pending-note">{{ __('admin.orders.slip.guests_pending') }}</div>
            @endunless
            <table class="guests">
                <thead>
                    <tr>
                        <th class="g-num">#</th>
                        @foreach ($guestCols as $col)
                            <th>{{ $col['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($guestRows as $i => $row)
                        <tr>
                            <td class="g-num">{{ $i + 1 }}</td>
                            @foreach ($row as $cell)
                                <td class="g-cell">{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Complementos (pulseras de zona, calcetines, tarta, …). --}}
    @php $addons = $slip->addons(); @endphp
    @if (count($addons) > 0)
        <div class="sec">
            <div class="sec-title">{{ __('admin.orders.slip.addons_heading') }}</div>
            <ul class="addons">
                @foreach ($addons as $addon)
                    <li class="{{ $addon['cancelled'] ? 'struck' : '' }}">
                        {{ $addon['quantity'] }} × {{ $addon['name'] }}
                        @if (! empty($addon['badge']))
                            <span class="badge-inc">{{ __('tickets.addon_badge_'.$addon['badge']) }}</span>
                        @endif
                        @if ($addon['cancelled'])
                            <span class="tag-cancel">· {{ __('admin.orders.item_status.cancelled') }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Desglose ECONÓMICO (totales · a cobrar en puerta · pendiente de devolución). Solo en la
         hoja CON precios (`?precios=1`); la hoja operativa de sala lo omite por completo (decisión
         clienta 2026-06-14). Los complementos contratados ya se listan arriba, sin importes. --}}
    @if ($showPrices)
    {{-- HOJA A4 APARTE: el wrapper `.desglose-page` fuerza un salto de página, así los importes
         van solos en su hoja, separados de los datos operativos de arriba (petición clienta). --}}
    <div class="desglose-page">
    {{-- Totales del producto (espejo de la sub-card del pedido) DESGLOSADO:
         cada línea = "cantidad × precio unitario" → importe de la línea
         (principal y cada complemento), luego total + devuelto + pendiente. --}}
    @php $pb = $slip->principalBreakdown(); @endphp
    <div class="sec totals">
        <div class="sec-title">{{ __('admin.orders.item_financial.heading') }}</div>
        <table>
            <tr>
                <td class="t-label {{ $pb['cancelled'] ? 'struck' : '' }}">{{ __('admin.orders.item_financial.principal') }} · {{ $pb['quantityLabel'] }} × {{ $fmt($pb['unitPriceCents']) }}</td>
                <td class="t-value {{ $pb['cancelled'] ? 'struck' : '' }}">{{ $fmt($pb['totalCents']) }}</td>
            </tr>
            @foreach ($slip->addonBreakdown() as $ab)
                <tr>
                    <td class="t-label {{ $ab['cancelled'] ? 'struck' : '' }}">
                        {{ $ab['name'] }}@if (! empty($ab['badge'])) <span class="badge-inc">{{ __('tickets.addon_badge_'.$ab['badge']) }}</span>@endif
                        · {{ $ab['quantity'] }} × {{ $fmt($ab['unitPriceCents']) }}@if (! empty($ab['partialFreeNote'])) <span class="t-free">({{ $ab['partialFreeNote'] }})</span>@endif
                    </td>
                    <td class="t-value {{ $ab['cancelled'] ? 'struck' : '' }}">{{ $fmt($ab['totalCents']) }}</td>
                </tr>
            @endforeach
            <tr class="t-total">
                <td class="t-label {{ $cancelled ? 'struck' : '' }}">{{ __('admin.orders.item_financial.total') }}</td>
                <td class="t-value {{ $cancelled ? 'struck' : '' }}">{{ $fmt($slip->grandTotalCents()) }}</td>
            </tr>
            {{-- Split del total (#196): pagado online + lo de puerta. Lo PENDIENTE a
                 cobrar en puerta se destaca aparte en la caja inferior; aquí solo
                 explicitamos lo ya pagado online (y lo cobrado en puerta si finalizó). --}}
            @php $rf = $slip->financials(); @endphp
            @if ($rf->aCobrarPuerta > 0 || $rf->cobradoPuerta > 0 || $rf->devuelto > 0 || $rf->pendienteReembolso > 0)
                <tr>
                    <td class="t-label">{{ __('admin.orders.item_financial.paid_online') }}</td>
                    <td class="t-value">{{ $fmt($rf->pagadoOnline) }}</td>
                </tr>
                @if ($rf->cobradoPuerta > 0)
                    <tr>
                        <td class="t-label">{{ __('admin.orders.item_financial.collected_at_gate') }}</td>
                        <td class="t-value">{{ $fmt($rf->cobradoPuerta) }}</td>
                    </tr>
                @endif
            @endif
            {{-- "Devuelto" (ya reembolsado) permanece en la tabla, como "Cobrado en
                 puerta". Lo PENDIENTE de devolver se destaca aparte en su propia caja
                 (simétrico a "A cobrar en puerta"). --}}
            @if ($slip->refundedCents() > 0)
                <tr class="t-refund">
                    <td class="t-label">{{ __('admin.orders.item_financial.refunded_label') }}</td>
                    <td class="t-value">−{{ $fmt($slip->refundedCents()) }}</td>
                </tr>
            @endif
        </table>
    </div>

    {{-- A cobrar en puerta (ajustes pendientes de esta reserva). --}}
    @if ($slip->hasPendingAtGate())
        <div class="gate-box">
            <table>
                <tr>
                    <td style="width: 60%;">
                        <div class="gate-title">{{ __('admin.orders.slip.pending_at_gate') }}</div>
                        @php $breakdown = $slip->pendingAtGateBreakdown(); @endphp
                        @if (count($breakdown) > 0)
                            <div class="gate-detail">{{ implode(' · ', $breakdown) }}</div>
                        @endif
                    </td>
                    <td style="width: 40%; text-align: right;">
                        <span class="gate-amount">{{ $fmt($slip->pendingAtGateCents()) }}</span>
                    </td>
                </tr>
            </table>
        </div>
    @endif

    {{-- Pendiente de devolución (importe pagado de más aún no devuelto). Misma
         prominencia que "A cobrar en puerta", en su propia caja (tono rojo: dinero
         que vuelve al cliente). Solo si queda algo por devolver (si ya se devolvió,
         aparece como "Devuelto" en la tabla de arriba). --}}
    @if ($slip->pendingRefundCents() > 0)
        <div class="refund-box">
            <table>
                <tr>
                    <td style="width: 60%;">
                        <div class="refund-title">{{ __('admin.orders.slip.pending_refund') }}</div>
                        <div class="refund-detail">{{ __('admin.orders.slip.pending_refund_caption') }}</div>
                    </td>
                    <td style="width: 40%; text-align: right;">
                        <span class="refund-amount">−{{ $fmt($slip->pendingRefundCents()) }}</span>
                    </td>
                </tr>
            </table>
        </div>
    @endif
    </div>{{-- .desglose-page --}}
    @endif {{-- $showPrices --}}

    {{-- Pie: referencia del pedido + marcas de tiempo. --}}
    <div class="footer">
        {{ __('admin.orders.slip.order_ref') }} {{ $slip->code() }}
        @if ($slip->createdAt())
            · {{ __('admin.orders.slip.created_at', ['when' => DisplayTime::format($slip->createdAt(), 'd/m/Y')]) }}
        @endif
        · {{ __('admin.orders.slip.printed_at', ['when' => DisplayTime::format(now(), 'd/m/Y H:i')]) }}
    </div>

</body>
</html>
