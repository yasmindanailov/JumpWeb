<tr>
<td>
<table class="footer" align="center" width="600" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="content-cell" align="center">
{{ Illuminate\Mail\Markdown::parse($slot) }}
{{-- Enlaces a POLÍTICAS + CONTACTO (#251), compartidos por TODOS los correos. URLs absolutas
     (`route()` las genera absolutas en email) y etiquetas data-driven/i18n (reusan las del footer
     web, `landing.footer.legal`). Estilos inline (email-safe), color atenuado del footer. --}}
@php($legal = (array) __('landing.footer.legal'))
<p style="margin:10px 0 0;font-family:'Space Grotesk',-apple-system,BlinkMacSystemFont,Helvetica,Arial,sans-serif;font-size:12px;line-height:1.7;color:#6B675D;">
<a href="{{ route('legal.privacidad') }}" style="color:#6B675D;text-decoration:underline;">{{ $legal[1] ?? 'Privacidad' }}</a>
&nbsp;&middot;&nbsp;
<a href="{{ route('legal.condiciones') }}" style="color:#6B675D;text-decoration:underline;">{{ $legal[2] ?? 'Condiciones' }}</a>
&nbsp;&middot;&nbsp;
<a href="{{ route('legal.cookies') }}" style="color:#6B675D;text-decoration:underline;">{{ $legal[3] ?? 'Cookies' }}</a>
&nbsp;&middot;&nbsp;
<a href="{{ route('contacto') }}" style="color:#6B675D;text-decoration:underline;">{{ __('landing.footer.contact_link') }}</a>
</p>
</td>
</tr>
</table>
</td>
</tr>
