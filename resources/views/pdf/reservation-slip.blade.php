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

    @var \App\Domain\Booking\Services\ReservationSlip $slip
--}}
@php
    use App\Domain\Booking\Models\Order;
    use App\Domain\Platform\Services\DisplayTime;
    use App\Domain\Booking\Services\ReservationSlip;

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
        /* Aviso impreso (§12.6 de `waiver-por-reserva.md`): más justificantes que plazas. Va en
           tinta oscura sobre gris y NO en color: esta hoja se imprime en blanco y negro. */
        .warn {
            font-size: 9.5px; font-weight: bold; color: #111827;
            background: #f3f4f6; border: 1px solid #9ca3af;
            padding: 5px 7px; margin-bottom: 6px;
        }
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

        /* Fiesta MIXTA (T3 · E): las líneas escritas del suplemento + el total; avisos en ámbar. */
        .mixed-line { font-size: 10px; margin-bottom: 2px; }
        .mixed-total { font-size: 11px; font-weight: bold; margin-top: 2px; }
        .mixed-note { font-size: 9px; color: #92400e; font-weight: bold; margin-top: 3px; }
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

        /* T3·2 del LIBRO: la caja neutra de «nada pendiente» y el detalle de cada línea del libro. */
        .settled-box {
            margin-top: 12px;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            border-radius: 8px;
            padding: 8px 11px;
        }
        .settled-title { font-size: 11px; font-weight: bold; color: #374151; }
        .totals .t-date { color: #6b7280; font-weight: normal; font-size: 10px; }
        .totals .t-neg { color: #92400e; }

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
                {{-- ⚠️⚠️ **El logotipo entra en PNG y NO en SVG, y no es pereza.** El del cliente trae 26 pasos
                     de extrusión por palabra, dos degradados y `background-clip: text` (`DECISIONES #275`):
                     dompdf soporta un subconjunto pequeño de SVG y eso **no falla con un error, sale mal
                     impreso** — que en una hoja que se lleva a una fiesta es peor. El PNG a 4x lo pinta igual
                     en cualquier motor, y el paquete de instalación ya trae las dos formas.
                
                     ⚠️ **Se referencia por RUTA DE DISCO, no por URL**: dompdf corre sin red
                     (`enable_remote` desactivado por defecto) y `public_path()` cae dentro de su `chroot`.
                     Una URL saldría como hueco en blanco.
                
                     ⚠️ **El suelo es el wordmark de siempre**: sin logotipo instalado la hoja se sigue
                     imprimiendo con el nombre del negocio, que es lo que el producto sabe pintar. --}}
                {{-- ⚠️ Forma de BLOQUE y no `@php(…)`: este fichero usa la de bloque, y MEZCLARLAS rompe el
                     compilador — Blade emparejó el `@php(` de aquí con el `@endphp` de 66 líneas más abajo
                     y se tragó media plantilla (`$resRows` indefinida). Es la trampa de `#298` al revés. --}}
                @php $slipLogo = public_path('img/client-logo@4x.png'); @endphp
                @if (is_file($slipLogo))
                    <img src="{{ $slipLogo }}" alt="" style="height: 34px; width: auto;" />
                @else
                    <div class="wordmark">{{ \Illuminate\Support\Str::upper((string) (\App\Domain\Platform\Models\Setting::value('business.name') ?: config('app.name'))) }}<span class="dot">.</span></div>
                @endif
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
                    {{-- ▶ T6·4: una fila puede traer lo que contestó un padre y el anfitrión todavía no
                         ha repasado. Se MARCA en su número —la hoja es papel, no tiene colores fiables
                         ni iconos— y la nota de debajo dice qué significa. Sin esto, un anfitrión que no
                         vuelve a guardar deja niños fuera del papel. --}}
                    @foreach ($guestRows as $i => $row)
                        <tr>
                            <td class="g-num">{{ $i + 1 }}@if ($row['proposed'])*@endif</td>
                            @foreach ($row['cells'] as $cell)
                                <td class="g-cell">{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if (collect($guestRows)->contains(fn (array $row): bool => $row['proposed']))
                <div class="guests-pending-note">{{ __('admin.orders.slip.guests_proposed') }}</div>
            @endif
        </div>
    @endif

    {{-- Menores INVITADOS con justificante (`specs/waiver-por-reserva.md` §4.12, `#337`): niños que
         NO son menores a cargo de quien reservó y por los que un adulto sin cuenta firmó la descarga
         de responsabilidad. La sala necesita saber quién viene cubierto y quién no.
         ⚠️ SIN correo ni teléfono de ningún adulto: la forma del operador no los lleva
         (`GuardianRoster`). Quien necesite la prueba completa abre el registro probatorio, que tiene
         permiso propio y consulta auditada.
         ⚠️ Se pinta solo si hay alguno: en una reserva normal esta sección sería ruido en una hoja
         que se imprime en papel. --}}
    @if (($guestMinors ?? []) !== [])
        <div class="sec">
            <div class="sec-title">{{ __('admin.orders.slip.guest_minors_heading') }}</div>
            {{-- §12.6: bajar la cantidad NO borra justificantes —son firmas con valor probatorio— así
                 que la hoja puede acabar listando más menores que plazas tiene el pedido. Antes lo
                 imprimía sin decir nada. Se avisa AQUÍ porque esta hoja es la que la sala tiene en la
                 mano cuando llegan los niños. --}}
            @if (($guestMinorsOverflow ?? false))
                <div class="warn">{{ __('admin.orders.slip.guest_minors_overflow', [
                    'count' => count($guestMinors),
                    'capacity' => $guestMinorsCapacity ?? 0,
                ]) }}</div>
            @endif
            <table class="guests">
                <thead>
                    <tr>
                        <th class="g-num">#</th>
                        <th>{{ __('admin.orders.slip.guest_minor') }}</th>
                        <th>{{ __('admin.orders.slip.guest_minor_guardian') }}</th>
                        <th>{{ __('admin.orders.slip.guest_minor_state') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($guestMinors as $i => $g)
                        <tr>
                            <td class="g-num">{{ $i + 1 }}</td>
                            <td>{{ $g['minor'] }}</td>
                            <td>{{ $g['guardian'] }} ({{ __('guardian.relationships.'.$g['relationship']) }})</td>
                            <td>{{ $g['waiver'] === null ? '—' : __('admin.orders.slip.guest_minor_waiver_'.$g['waiver']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Fiesta MIXTA (T3 · E, `specs/cumple-mixto.md` §23.2): lo ESCRITO del suplemento — la
         diferencia por cabeza que el operador hacía de memoria con el cliente delante. Se imprime
         lo escrito y NUNCA el veredicto derivado: es lo que se cobra (`PAY-19`); del veredicto solo
         salen el caso barato y las edades sin producto, que el dinero no dice.
         ⚠️⚠️ REVISADO en la T5 (`[DECIDIDO owner, 2026-08-31]`, §25.10 adenda 3, y CORRIGE la
         decisión de la T3 que aquí decía «va en la hoja operativa a sabiendas»): la hoja OPERATIVA
         lleva los HECHOS de la mezcla y NI UN EURO — la sala sabe que hay tema mixto y quién
         corresponde a qué pack; los importes viven solo en «Con precios y desglose». --}}
    @php $mixedParty = $slip->mixedParty(); @endphp
    @if ($mixedParty !== null)
        <div class="sec">
            <div class="sec-title">{{ __('admin.orders.slip.mixed_party_heading') }}</div>
            @if ($showPrices)
                @foreach ($mixedParty['lines'] as $line)
                    <div class="mixed-line">{{ __('admin.orders.mixed_party.line', ['count' => $line['count'], 'name' => $line['name'], 'unit' => $fmt($line['unit'])]) }}</div>
                @endforeach
                @if ($mixedParty['creditLabel'] !== null)
                    {{-- T4 (§24.5): el DESCUENTO escrito, con la frase compuesta por el dominio. --}}
                    <div class="mixed-line">{{ $mixedParty['creditLabel'] }}: −{{ $fmt($mixedParty['creditCents']) }}</div>
                @endif
                @if ($mixedParty['chargeCents'] > 0 && $mixedParty['creditCents'] > 0)
                    <div class="mixed-total">{{ __('admin.orders.mixed_party.net', ['amount' => ($mixedParty['netCents'] < 0 ? '−' : '+').$fmt(abs($mixedParty['netCents']))]) }}</div>
                @elseif ($mixedParty['chargeCents'] > 0)
                    <div class="mixed-total">{{ __('admin.orders.mixed_party.applied', ['amount' => $fmt($mixedParty['chargeCents'])]) }}</div>
                @endif
            @else
                @foreach ($mixedParty['lines'] as $line)
                    <div class="mixed-line">{{ __('admin.orders.slip.mixed_party_fact_line', ['count' => $line['count'], 'name' => $line['name']]) }}</div>
                @endforeach
                @if ($mixedParty['creditLabel'] !== null)
                    {{-- La frase del descuento SIN su importe: nombra a quiénes y hacia qué pack. --}}
                    <div class="mixed-line">{{ $mixedParty['creditLabel'] }}</div>
                @endif
            @endif
            @if ($mixedParty['withoutProduct'] > 0)
                <div class="mixed-note">{{ __('admin.orders.mixed_party.out_of_range', ['count' => $mixedParty['withoutProduct']]) }}</div>
            @endif
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

    {{-- Desglose ECONÓMICO: las líneas de producto y EL LIBRO de la reserva con UNA caja de saldo
         (`DECISIONES #305`; T3·2, D-T3·7). Solo en la hoja CON precios (`?precios=1`); la hoja
         operativa de sala lo omite por completo (decisión clienta 2026-06-14; guarda R de la T5:
         ni un euro). Los complementos contratados ya se listan arriba, sin importes. --}}
    @if ($showPrices)
    {{-- HOJA A4 APARTE: el wrapper `.desglose-page` fuerza un salto de página, así los importes
         van solos en su hoja, separados de los datos operativos de arriba (petición clienta). --}}
    <div class="desglose-page">
    {{-- Las LÍNEAS de producto (qué se compró: cantidad × unitario, principal y complementos) y
         debajo EL LIBRO de la reserva: movimientos con su fecha, Total, pagos y devoluciones,
         Pagado — las mismas líneas que ve el cliente en «Mis pedidos» y el operador en la ficha.
         Lo compone `OrderBook` (`$slip->book()`); la hoja no suma nada. El Total es el del LIBRO
         (con una cortesía, la suma de las líneas ya no es lo que vale). --}}
    @php
        $pb = $slip->principalBreakdown();
        $book = $slip->book();
        $signed = fn (int $cents): string => ($cents < 0 ? '−' : '+').$fmt(abs($cents));
        $kind = $book->balance->kind;
        [$box, $boxTitle, $boxAmount] = match ($kind) {
            \App\Domain\Booking\Services\Balance::KIND_PAY_AT_PARK, \App\Domain\Booking\Services\Balance::KIND_PAY_ONLINE => ['gate-box', 'gate-title', 'gate-amount'],
            \App\Domain\Booking\Services\Balance::KIND_REFUND_AT_PARK, \App\Domain\Booking\Services\Balance::KIND_REFUND_PENDING, \App\Domain\Booking\Services\Balance::KIND_UNDER_REVIEW => ['refund-box', 'refund-title', 'refund-amount'],
            default => ['settled-box', 'settled-title', 'gate-amount'],
        };
    @endphp
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
        </table>
    </div>

    <div class="sec totals" data-book>
        <div class="sec-title">{{ __('admin.orders.book.movements') }}</div>
        <table>
            @foreach ($book->movements as $m)
                <tr data-book-movement="{{ $m->kind }}">
                    <td class="t-label">{{ $m->label }} <span class="t-date">· {{ $m->occurredLabel }}</span></td>
                    <td class="t-value {{ $m->amountCents < 0 ? 't-neg' : '' }}">{{ $signed($m->amountCents) }}</td>
                </tr>
            @endforeach
            <tr class="t-total" data-book-total>
                <td class="t-label {{ $cancelled ? 'struck' : '' }}">{{ __('admin.orders.book.total') }}</td>
                <td class="t-value {{ $cancelled ? 'struck' : '' }}">{{ $fmt($book->totalCents) }}</td>
            </tr>
        </table>
    </div>

    <div class="sec totals">
        <div class="sec-title">{{ __('admin.orders.book.settlements') }}</div>
        <table>
            @foreach ($book->settlements as $s)
                <tr data-book-settlement="{{ $s->kind }}" class="{{ $s->amountCents < 0 ? 't-refund' : '' }}">
                    <td class="t-label">{{ $s->label }} <span class="t-date">· {{ $s->occurredLabel }}</span></td>
                    <td class="t-value">{{ $signed($s->amountCents) }}</td>
                </tr>
            @endforeach
            <tr class="t-total" data-book-paid>
                <td class="t-label">{{ __('admin.orders.book.paid') }}</td>
                <td class="t-value">{{ $fmt($book->paidCents) }}</td>
            </tr>
        </table>
    </div>

    {{-- UNA caja de saldo, por clase (spec §4.4; D-T3·7): lo que se cobra o se devuelve en el parque
         con la reserva delante, o «nada pendiente». La clase la decide el libro; aquí solo se elige
         el color de la caja. --}}
    <div class="{{ $box }}" data-book-balance="{{ $kind }}">
        <table>
            <tr>
                <td style="width: 60%;">
                    <div class="{{ $boxTitle }}">{{ __('admin.orders.book.balance_'.$kind) }}</div>
                    @if ($kind === \App\Domain\Booking\Services\Balance::KIND_PAY_ONLINE && $book->balance->restAtParkCents > 0)
                        <div class="gate-detail">{{ __('admin.orders.book.balance_rest_at_park', ['amount' => $fmt($book->balance->restAtParkCents)]) }}</div>
                    @endif
                </td>
                <td style="width: 40%; text-align: right;">
                    @if ($book->balance->cents !== 0)
                        <span class="{{ $boxAmount }}">{{ $book->balance->cents < 0 ? '−' : '' }}{{ $fmt(abs($book->balance->cents)) }}</span>
                    @endif
                </td>
            </tr>
        </table>
    </div>
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
