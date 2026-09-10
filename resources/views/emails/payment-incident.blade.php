@php
    $isDuplicate = ($incident['kind'] ?? '') === 'duplicate';
@endphp
<h2 style="color:#C83912">⚠️ Incidencia de cobro Redsys</h2>

@if ($isDuplicate)
    <p>El banco <strong>capturó un cobro</strong> que NO casa con una reserva cumplible (el pedido
    ya estaba pagado por otro pago, cancelado o reembolsado). Es un <strong>cargo
    duplicado/huérfano</strong>: el cliente ha sido cobrado y procede una <strong>devolución
    manual</strong> desde el portal de Redsys.</p>
@else
    <p>Una notificación de pago <strong>autorizada</strong> llegó <strong>después de que la reserva
    caducara</strong>. El cobro se capturó en el banco, pero la plaza pudo cederse a otro cliente.
    Hay que <strong>contactar al cliente</strong> para reagendar o devolver el cobro.</p>
@endif

<table style="border-collapse:collapse; font-size:14px; margin-top:12px">
    <tr><td style="padding:2px 12px 2px 0"><strong>Pedido</strong></td><td>{{ $incident['order_code'] ?? '—' }}</td></tr>
    <tr><td style="padding:2px 12px 2px 0"><strong>Estado del pedido</strong></td><td>{{ $incident['order_status'] ?? '—' }}</td></tr>
    <tr><td style="padding:2px 12px 2px 0"><strong>Nº de operación (gateway)</strong></td><td>{{ $incident['gateway_order'] ?? '—' }}</td></tr>
    <tr><td style="padding:2px 12px 2px 0"><strong>ID de pago</strong></td><td>{{ $incident['payment_id'] ?? '—' }}</td></tr>
    <tr><td style="padding:2px 12px 2px 0"><strong>Origen</strong></td><td>{{ $incident['source'] ?? '—' }}</td></tr>
</table>

<hr>
<p style="color:#626A72; font-size:12px">
    Aviso automático del sistema · queda registrado en el panel (Sistema → Incidencias) ·
    {{ now()->format('d/m/Y H:i') }}
</p>
