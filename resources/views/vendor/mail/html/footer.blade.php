@props(['utm' => null])
<tr>
<td>
<table class="footer" align="center" width="600" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="content-cell" align="center">
{{-- ❗ DÓNDE ESTÁ EL PARQUE Y SU TELÉFONO (`#503`; artboard `Correos PJP` 1a). Medido antes:
     **ni un correo de los 23 decía dónde está el parque**. Además de ser el dato que más se busca
     al recibir una confirmación, es lo que un correo transaccional necesita para no parecer
     publicidad — y lo que pide la ley en un envío comercial.
     ⚠️ Cada línea se pinta solo si su dato existe: una instalación sin dirección no imprime una
     coma huérfana. Los datos salen del PANEL (`business.name`, `address.*`, `contact.phone`). --}}
@php
    $negocio = \App\Domain\Platform\Models\Setting::businessName();
    $dir = array_filter([
        (string) \App\Domain\Platform\Models\Setting::value('address.line1', ''),
        (string) \App\Domain\Platform\Models\Setting::value('address.line2', ''),
    ], static fn (string $t): bool => trim($t) !== '');
    $tel = trim((string) \App\Domain\Platform\Models\Setting::value('contact.phone', ''));
    $sello = "font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:#626A72;line-height:1.7;margin:0;";
@endphp
<p style="{{ $sello }}">{{ trim($negocio.($dir ? ' · '.implode(', ', $dir) : '')) }}</p>
@if ($tel !== '')
<p style="{{ $sello }}">{{ $tel }}</p>
@endif
{{ Illuminate\Mail\Markdown::parse($slot) }}
{{-- Enlaces a POLÍTICAS + CONTACTO (#251), compartidos por TODOS los correos. URLs absolutas
     (`route()` las genera absolutas en email) y etiquetas data-driven/i18n (reusan las del footer
     web, `landing.footer.legal`). Estilos inline (email-safe), color atenuado del footer. --}}
{{-- Y con la UTM del correo (`EmailUtm::tag()`, analítica §4.1): cada uno de los cuatro es un enlace a esta
     casa, y quien llega por él ha llegado por ESTE correo. Sin clave, las URL tal cual. --}}
@php($legal = (array) __('landing.footer.legal'))
@php($con = static fn (string $url): string => \App\Domain\Platform\Services\Analytics\EmailUtm::tag($url, $utm))
<p style="margin:10px 0 0;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.7;color:#626A72;">
<a href="{{ $con(route('legal.privacidad')) }}" style="color:#626A72;text-decoration:underline;">{{ $legal[1] ?? 'Privacidad' }}</a>
&nbsp;&middot;&nbsp;
<a href="{{ $con(route('legal.condiciones')) }}" style="color:#626A72;text-decoration:underline;">{{ $legal[2] ?? 'Condiciones' }}</a>
&nbsp;&middot;&nbsp;
<a href="{{ $con(route('legal.cookies')) }}" style="color:#626A72;text-decoration:underline;">{{ $legal[3] ?? 'Cookies' }}</a>
&nbsp;&middot;&nbsp;
<a href="{{ $con(route('contacto')) }}" style="color:#626A72;text-decoration:underline;">{{ __('landing.footer.contact_link') }}</a>
</p>
</td>
</tr>
</table>
</td>
</tr>
