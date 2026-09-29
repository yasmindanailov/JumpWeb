{{-- EL DOCUMENTO de todos los correos del molde (la R1a, `specs/correos-rediseno.md` §4.1.1): el `documento()` de la
     plantilla del diseño. Recibe `$correo` (`App\Notifications\Support\MailDocument`), que `BrandedMailMessage::data()`
     compone de los mismos datos que ya da cada correo. Cada bloque, en `correo/html/<tipo>` con su gemela de texto en
     `correo/texto/<tipo>`.
     ⚠️ Los comentarios, en Blade: los de un `<style>` viajan en cada correo y los paga quien lo recibe (`#503`).
     ⚠️ Sin fuentes web (`MailTheme`): solo las pilas de sistema. --}}
@php($t = $correo->tema)
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="x-apple-disable-message-reformatting">
<meta name="format-detection" content="telephone=no, date=no, address=no, email=no, url=no">
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light dark">
<title>{{ $correo->asunto }}</title>
<!--[if mso]><noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript><style>body,table,td,div,p,a,span,h1,h2{font-family:Arial,Helvetica,sans-serif!important}</style><![endif]-->
<style>
:root{color-scheme:light dark;supported-color-schemes:light dark}
body{margin:0!important;padding:0!important;width:100%!important;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%}
table,td{border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt}
img{border:0;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic}
a[x-apple-data-detectors]{color:inherit!important;text-decoration:none!important;font-size:inherit!important;font-family:inherit!important;font-weight:inherit!important;line-height:inherit!important}
u+#pjm-body a{color:inherit;text-decoration:none;font-size:inherit;font-family:inherit;font-weight:inherit;line-height:inherit}
.pjm-link:hover{color:{{ $t->claro('enlace-hover') }}!important}
@media (max-width:480px){.pjm-pad{padding-left:20px!important;padding-right:20px!important}.pjm-h1{font-size:28px!important;line-height:32px!important}}
{!! $correo->cssOscuro() !!}
@media print{*{-webkit-print-color-adjust:exact;print-color-adjust:exact}}
</style>
</head>
<body id="pjm-body" class="pjm-bg" style="margin:0;padding:0;background:{{ $t->claro('fondo') }};">
{{-- LA LÍNEA DE ADELANTO (`#506`): lo que la bandeja enseña detrás del asunto, ANTES de todo (si fuera en el cuerpo, se
     leería antes el `alt` del logotipo), y con relleno para que la bandeja no siga leyendo el cuerpo detrás. Sin línea
     escrita, nada: falla hacia invisible. --}}
@if ($correo->adelanto !== null)
<div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;">{{ $correo->adelanto }}{!! str_repeat('&#847;&zwnj;&nbsp;', 63) !!}</div>
@endif
<table {!! $correo::TABLA !!} width="100%" class="pjm-bg" bgcolor="{{ $t->claro('fondo') }}" style="background:{{ $t->claro('fondo') }};"><tr><td align="center">
<!--[if mso]><table {!! $correo::TABLA !!} width="600" align="center"><tr><td><![endif]-->
<table {!! $correo::TABLA !!} width="100%" style="max-width:600px;margin:0 auto;">
@foreach ($correo->bloques as $b)
@include('correo.html.'.$b['tipo'], ['b' => $b])
@endforeach
</table>
<!--[if mso]></td></tr></table><![endif]-->
</td></tr></table>
{{-- EL PÍXEL DE APERTURA (`correos-salientes.md` §4.12, `#797`): solo si el correo lo lleva; al final y sin alto. --}}
@if ($correo->pixel !== null)
<img src="{{ $correo->pixel }}" width="1" height="1" alt="" style="display:block;width:1px;height:1px;border:0;margin:0;padding:0;">
@endif
</body>
</html>
