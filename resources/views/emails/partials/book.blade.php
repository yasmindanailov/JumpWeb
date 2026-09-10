{{-- EL LIBRO del pedido para los correos de dinero (`DECISIONES #305`; T3·3 de `specs/desglose-libro.md`
     §6.3.4, D-T3·5). Tablas y estilos INLINE a propósito (ver `product-card`): Gmail y Outlook no respetan
     CSS externo, y el slot del correo pasa por el parser Markdown. La rinde
     `App\Domain\Booking\Services\EmailBookBlock`; NO compone ni decide un importe: transcribe el libro.
     Los rótulos son los del cliente (`tickets.journal.*`, en su idioma). Props: $book --}}
@php
    use App\Domain\Booking\Services\Balance;
    use App\Domain\Platform\Services\Money;

    $fmt = fn (int $cents): string => Money::format($cents, $book->currency);
    $signed = fn (int $cents): string => ($cents < 0 ? '−' : '+').Money::format(abs($cents), $book->currency);
    $font = "font-family:Arial,Helvetica,sans-serif;";
    $kind = $book->balance->kind;
    /* ❗ UNA DEVOLUCIÓN NO ES UN COLOR: ES UN SIGNO Y UNA FECHA. No es error —nadie ha roto
       nada— y no es éxito —el verde significa «reserva confirmada», y una reserva devuelta se
       leería como confirmada—. Y lo que se DEBE tampoco es un aviso: es dinero pendiente, y va
       en tinta. Hasta el 2026-09-10 esto pintaba «a pagar» en ámbar 700, «a devolver» en ámbar
       800 y «en revisión» en rojo 800 de Tailwind — tres colores que no son de ninguna marca y
       que ninguna guarda miraba. Lo que distingue cada saldo es su RÓTULO y su SIGNO, que ya
       están; el único con color es el que de verdad es una avería.

       ⚠️ Y lo que NO se toca: `settled` y `expired` siguen en Humo. Un saldo saldado —«Nada
       pendiente»— no es dinero que reclamar: es un estado en reposo, y el gris de texto 2º es
       justo el rol de lo inerte. Fundirlo con los otros por simplificar la expresión habría
       subido a tinta plena una línea que no pide nada. */
    $DINERO = '#101418';                 // pendiente y devuelto: tinta
    $AVERIA = '#C83912';                 // Rojo 800: el libro no cuadra, eso sí es una avería
    $INERTE = '#626A72';                 // Humo: saldado o caducado, nada que hacer
    $balanceColor = match ($kind) {
        Balance::KIND_UNDER_REVIEW => $AVERIA,
        Balance::KIND_SETTLED, Balance::KIND_EXPIRED => $INERTE,
        default => $DINERO,
    };
@endphp
<table class="book" width="100%" cellpadding="0" cellspacing="0" role="presentation" data-book style="margin:18px 0;border:1px solid #D6D8D4;border-radius:16px;background:#FFFFFF;border-collapse:separate;">
<tr><td style="padding:14px 16px;">
<div style="{{ $font }}font-weight:700;font-size:12px;color:#626A72;text-transform:uppercase;letter-spacing:0.04em;">{{ __('tickets.journal.email_title') }}</div>
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-top:6px;border-collapse:collapse;">
@foreach ($book->movements as $m)
<tr data-book-movement="{{ $m->kind }}"><td style="{{ $font }}font-size:13px;color:#101418;padding:3px 0;">{{ $m->label }} <span style="color:#626A72;white-space:nowrap;">· {{ $m->occurredLabel }}</span></td><td align="right" style="{{ $font }}font-size:13px;color:#101418;padding:3px 0 3px 12px;white-space:nowrap;">{{ $signed($m->amountCents) }}</td></tr>
@endforeach
<tr data-book-total><td style="{{ $font }}font-size:14px;font-weight:700;color:#101418;padding:7px 0 3px;border-top:1px solid #D6D8D4;">{{ __('tickets.journal.total') }}</td><td align="right" style="{{ $font }}font-size:14px;font-weight:700;color:#101418;padding:7px 0 3px 12px;border-top:1px solid #D6D8D4;white-space:nowrap;">{{ $fmt($book->totalCents) }}</td></tr>
@foreach ($book->settlements as $s)
<tr data-book-settlement="{{ $s->kind }}"><td style="{{ $font }}font-size:13px;color:#101418;padding:3px 0;">{{ $s->label }} <span style="color:#626A72;white-space:nowrap;">· {{ $s->occurredLabel }}</span></td><td align="right" style="{{ $font }}font-size:13px;color:#101418;padding:3px 0 3px 12px;white-space:nowrap;">{{ $signed($s->amountCents) }}</td></tr>
@endforeach
<tr data-book-paid><td style="{{ $font }}font-size:14px;font-weight:700;color:#101418;padding:7px 0 3px;border-top:1px solid #D6D8D4;">{{ __('tickets.journal.paid') }}</td><td align="right" style="{{ $font }}font-size:14px;font-weight:700;color:#101418;padding:7px 0 3px 12px;border-top:1px solid #D6D8D4;white-space:nowrap;">{{ $fmt($book->paidCents) }}</td></tr>
<tr data-book-balance="{{ $kind }}"><td style="{{ $font }}font-size:14px;font-weight:700;color:{{ $balanceColor }};padding:3px 0;">{{ __('tickets.journal.balance_'.$kind) }}</td><td align="right" style="{{ $font }}font-size:14px;font-weight:700;color:{{ $balanceColor }};padding:3px 0 3px 12px;white-space:nowrap;">@if ($book->balance->cents !== 0){{ $book->balance->cents < 0 ? '−' : '' }}{{ $fmt(abs($book->balance->cents)) }}@endif</td></tr>
@if ($kind === Balance::KIND_PAY_ONLINE && $book->balance->restAtParkCents > 0)
<tr><td colspan="2" style="{{ $font }}font-size:12px;color:#626A72;padding:0 0 3px;">{{ __('tickets.journal.balance_rest_at_park', ['amount' => $fmt($book->balance->restAtParkCents)]) }}</td></tr>
@endif
@if ($book->note !== null)
<tr><td colspan="2" style="{{ $font }}font-size:12px;color:#626A72;padding:6px 0 0;">{{ $book->note }}</td></tr>
@endif
</table>
</td></tr>
</table>
