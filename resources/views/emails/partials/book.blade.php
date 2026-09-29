{{-- EL LIBRO del pedido para los correos de dinero (`DECISIONES #305`; T3·3 de `specs/desglose-libro.md`
     §6.3.4, D-T3·5). Tablas y estilos INLINE a propósito (ver `product-card`): Gmail y Outlook no respetan
     CSS externo. La rinde `App\Domain\Booking\Services\EmailBookBlock`; NO compone ni decide un importe: transcribe el
     libro. Los rótulos son los del cliente (`tickets.journal.*`, en su idioma). Props: $book
     ▶ Desde la R1a del rediseño (`correos-rediseno.md` §4.1.1), sus colores son ROLES del correo (`MailTheme`) y cada
     texto lleva su CLASE de rol, porque el oscuro de la plantilla va por clase: sin ella, el libro quedaba a 1,12 : 1
     en oscuro (`#503`), que es exactamente lo que el oscuro por color de septiembre tuvo que remendar. Y sin margen propio:
     el aire alrededor lo pone la plantilla (su bloque de marcado), y los dos sumados daban 46 px donde el diseño pide 28. --}}
@php
    use App\Domain\Booking\Services\Balance;
    use App\Domain\Platform\Services\Money;
    use App\Notifications\Support\MailTheme;

    $t = MailTheme::current();
    $fmt = fn (int $cents): string => Money::format($cents, $book->currency);
    $signed = fn (int $cents): string => ($cents < 0 ? '−' : '+').Money::format(abs($cents), $book->currency);
    $font = 'font-family:'.$t->fuente('texto').';';
    $filete = $t->claro('filete');
    $kind = $book->balance->kind;
    /* ❗ UNA DEVOLUCIÓN NO ES UN COLOR: ES UN SIGNO Y UNA FECHA. No es error —nadie ha roto
       nada— y no es éxito —el verde significa «reserva confirmada», y una reserva devuelta se
       leería como confirmada—. Y lo que se DEBE tampoco es un aviso: es dinero pendiente, y va
       en el fuerte. Lo que distingue cada saldo es su RÓTULO y su SIGNO; el único con color es el
       que de verdad es una avería (la letra del tono de error).

       ⚠️ Y lo que NO se toca: `settled` y `expired` siguen en el apagado. Un saldo saldado —«Nada
       pendiente»— no es dinero que reclamar: es un estado en reposo, y el apagado es justo el rol
       de lo inerte. */
    [$balanceColor, $balanceClass] = match ($kind) {
        Balance::KIND_UNDER_REVIEW => [$t->claro('error-letra'), 'pjm-tono-error-t'],
        Balance::KIND_SETTLED, Balance::KIND_EXPIRED => [$t->claro('apagado'), 'pjm-muted'],
        default => [$t->claro('fuerte'), 'pjm-strong'],
    };
    [$fuerte, $apagado] = [$t->claro('fuerte'), $t->claro('apagado')];
@endphp
<table class="book pjm-line pjm-bg" width="100%" cellpadding="0" cellspacing="0" role="presentation" data-book style="border:1px solid {{ $filete }};border-radius:{{ $t->radio('lg') }}px;background:{{ $t->claro('fondo') }};border-collapse:separate;">
<tr><td style="padding:14px 16px;">
<div class="pjm-muted" style="{{ $font }}font-weight:700;font-size:12px;color:{{ $apagado }};text-transform:uppercase;letter-spacing:0.04em;">{{ __('tickets.journal.email_title') }}</div>
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-top:6px;border-collapse:collapse;">
@foreach ($book->movements as $m)
<tr data-book-movement="{{ $m->kind }}"><td class="pjm-strong" style="{{ $font }}font-size:13px;color:{{ $fuerte }};padding:3px 0;">{{ $m->label }} <span class="pjm-muted" style="color:{{ $apagado }};white-space:nowrap;">· {{ $m->occurredLabel }}</span></td><td class="pjm-strong" align="right" style="{{ $font }}font-size:13px;color:{{ $fuerte }};padding:3px 0 3px 12px;white-space:nowrap;">{{ $signed($m->amountCents) }}</td></tr>
@endforeach
<tr data-book-total><td class="pjm-strong pjm-line" style="{{ $font }}font-size:14px;font-weight:700;color:{{ $fuerte }};padding:7px 0 3px;border-top:1px solid {{ $filete }};">{{ __('tickets.journal.total') }}</td><td class="pjm-strong pjm-line" align="right" style="{{ $font }}font-size:14px;font-weight:700;color:{{ $fuerte }};padding:7px 0 3px 12px;border-top:1px solid {{ $filete }};white-space:nowrap;">{{ $fmt($book->totalCents) }}</td></tr>
@foreach ($book->settlements as $s)
<tr data-book-settlement="{{ $s->kind }}"><td class="pjm-strong" style="{{ $font }}font-size:13px;color:{{ $fuerte }};padding:3px 0;">{{ $s->label }} <span class="pjm-muted" style="color:{{ $apagado }};white-space:nowrap;">· {{ $s->occurredLabel }}</span></td><td class="pjm-strong" align="right" style="{{ $font }}font-size:13px;color:{{ $fuerte }};padding:3px 0 3px 12px;white-space:nowrap;">{{ $signed($s->amountCents) }}</td></tr>
@endforeach
<tr data-book-paid><td class="pjm-strong pjm-line" style="{{ $font }}font-size:14px;font-weight:700;color:{{ $fuerte }};padding:7px 0 3px;border-top:1px solid {{ $filete }};">{{ __('tickets.journal.paid') }}</td><td class="pjm-strong pjm-line" align="right" style="{{ $font }}font-size:14px;font-weight:700;color:{{ $fuerte }};padding:7px 0 3px 12px;border-top:1px solid {{ $filete }};white-space:nowrap;">{{ $fmt($book->paidCents) }}</td></tr>
<tr data-book-balance="{{ $kind }}"><td class="{{ $balanceClass }}" style="{{ $font }}font-size:14px;font-weight:700;color:{{ $balanceColor }};padding:3px 0;">{{ __('tickets.journal.balance_'.$kind) }}</td><td class="{{ $balanceClass }}" align="right" style="{{ $font }}font-size:14px;font-weight:700;color:{{ $balanceColor }};padding:3px 0 3px 12px;white-space:nowrap;">@if ($book->balance->cents !== 0){{ $book->balance->cents < 0 ? '−' : '' }}{{ $fmt(abs($book->balance->cents)) }}@endif</td></tr>
@if ($kind === Balance::KIND_PAY_ONLINE && $book->balance->restAtParkCents > 0)
<tr><td class="pjm-muted" colspan="2" style="{{ $font }}font-size:12px;color:{{ $apagado }};padding:0 0 3px;">{{ __('tickets.journal.balance_rest_at_park', ['amount' => $fmt($book->balance->restAtParkCents)]) }}</td></tr>
@endif
@if ($book->note !== null)
<tr><td class="pjm-muted" colspan="2" style="{{ $font }}font-size:12px;color:{{ $apagado }};padding:6px 0 0;">{{ $book->note }}</td></tr>
@endif
</table>
</td></tr>
</table>
