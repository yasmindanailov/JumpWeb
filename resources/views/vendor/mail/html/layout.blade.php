<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
{{-- El NEGOCIO, no el producto. No se ve en la bandeja, pero sí en el «ver en el navegador» de
     algunos clientes y en el nombre con el que se guarda el correo. --}}
<title>{{ \App\Domain\Platform\Models\Setting::businessName() }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
{{-- El modo oscuro NO es un apaño en este producto: la superficie nativa de la marca ES la
     tinta, así que el correo invertido sale entero de los tokens que ya existen (fondo tinta,
     tarjeta Tinta 800, borde Tinta 700, cuerpo Papel 200) sin inventar un solo valor.
     Declararlo solo como `light` no impedía que el cliente de correo invirtiera por su
     cuenta: lo que hacía era que invirtiera SIN nuestras reglas, y el crema se volvía barro. --}}
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light dark">
{{-- ❗❗ ESTE `<style>` ES EL ÚNICO SITIO DEL CORREO DONDE UNA `@media` SOBREVIVE, y está
     medido: Laravel pasa el tema (`themes/brand.css`) por `CssToInlineStyles`, que pega cada
     regla al atributo `style=""` de su etiqueta y **descarta lo que no puede inlinear** — y una
     media query no se puede inlinear. El bloque de modo oscuro se escribió primero en el tema y
     el HTML enviado salió SIN una sola `@media (prefers-color-scheme)`, sin que fallara nada.
     ▶ La regla que sale de ahí: *lo que no se puede inlinear no puede vivir en el tema.* --}}
<style>
@media only screen and (max-width: 600px) {
.inner-body {
width: 100% !important;
}

.footer {
width: 100% !important;
}
}

@media only screen and (max-width: 500px) {
.button {
width: 100% !important;
}
}

{{-- ════════════ MODO OSCURO — la superficie NATIVA de esta marca ════════════
   No es un apaño: el modo de la marca ES la tinta, así que el correo invertido sale entero de los
   tokens que ya existen y NO hay un solo valor nuevo aquí — fondo Tinta 900 · tarjeta Tinta 800 ·
   borde Tinta 700 · cuerpo Papel 200 · texto 2º Humo Claro · enlace Cian.

   ❗❗ POR QUÉ TODO LLEVA `!important`: el tema ya se ha inlineado sobre cada etiqueta, así que
   `color` y `background-color` viajan como `style=""` y ganan a cualquier regla de este bloque.
   Sin `!important` esto no pinta nada — y no falla: el correo sale igual de claro.

   ❗❗❗ Y POR QUÉ EL TEXTO SE MAPEA **POR SU COLOR**, NO POR SU ETIQUETA. La primera versión de
   este bloque seleccionaba `body, p, .table td, h1…` y **dejaba el LIBRO DEL PEDIDO INVISIBLE**:
   `emails/partials/book.blade.php` y `product-card.blade.php` pintan su color inline sobre `<td>`,
   `<div>` y `<span>` **sin clase**, que ningún selector de aquéllos alcanzaba. Medido sobre el
   correo real: **11 elementos a 1,12 : 1** —los diez `<td>` del desglose de dinero y el título del
   producto— y otros 8 a 3,02. O sea que el bloque del dinero desaparecía entero.
   ▶ La relación que de verdad existe no es «este elemento es un párrafo», es **«este texto es
   TINTA» y «este texto es HUMO»**, y eso es exactamente lo que el atributo dice. Mapear por color
   cubre también el marcado que aún no existe, que es donde volvería a pasar.
   ⚠️ Se declaran las DOS formas —con y sin espacio tras los dos puntos— porque los partials lo
   escriben pegado y el inliner lo escribe separado.

   ⚠️ El BOTÓN queda fuera a propósito: su texto lo calcula `ThemeSettings::onAction()` contra el
   relleno de marca, que es el MISMO en los dos modos, así que aclararlo lo rompería. Hoy devuelve
   `#14130F` y no casaría igualmente, pero eso es suerte y no se deja como defensa.

   ⚠️ El botón de acción tampoco cambia de relleno. En tinta el sistema manda el secundario a Cian,
   pero eso nace con el mapa del naranja, en su propia tanda: una pieza no se declara antes que su
   consumidor. --}}
@media (prefers-color-scheme: dark) {

{{-- ── Superficies, TAMBIÉN POR SU COLOR ──
   ⚠️ Iban por CLASE (`body, .wrapper, .inner-body…`) y el resto del mapa por color: **dos
   mecanismos para lo mismo**, y la guarda solo sabía leer uno — así que daba por descubiertos
   fondos que sí estaban cubiertos, y habría dejado pasar los que no. Un solo mecanismo: el color
   claro dice cuál es su par oscuro, sea texto, fondo o borde. --}}
[style*="background-color:#F4F4F1"],
[style*="background-color: #F4F4F1"] {
background-color: #101418 !important;
}

[style*="background-color:#FFFFFF"],
[style*="background-color: #FFFFFF"] {
background-color: #1A1F25 !important;
}

{{-- El borde de Línea, que sobre tinta es Tinta 700. --}}
[style*="#D6D8D4"] {
border-color: #2A3138 !important;
}

.subcopy {
border-top-color: #2A3138 !important;
}

{{-- ── Texto, por su color claro ── --}}
{{-- Tinta → cuerpo sobre tinta (Papel 200) --}}
[style*="color:#101418"]:not(.button),
[style*="color: #101418"]:not(.button) {
color: #C9CDD1 !important;
}

{{-- Humo → Humo Claro --}}
[style*="color:#626A72"]:not(.button),
[style*="color: #626A72"]:not(.button) {
color: #9AA1A8 !important;
}

{{-- Rojo 800 → Rojo Claro. En tinta el 500 solo aguanta a ≥19px bold, y esto no lo es.
   Su único uso hoy es el titular del aviso de incidencia de cobro, que lee el PARQUE. --}}
[style*="color:#C83912"]:not(.button),
[style*="color: #C83912"]:not(.button) {
color: #FF8A6B !important;
}

{{-- Titulares y wordmark: texto pleno. Más específicos que el mapa de arriba, a propósito. --}}
h1[style*="#101418"],
h2[style*="#101418"],
h3[style*="#101418"],
.header a[style*="#101418"] {
color: #F4F4F1 !important;
}

{{-- Enlaces del cuerpo: en superficie de tinta el rol de enlace es Cian. --}}
.inner-body a[style*="#101418"],
.subcopy a[style*="#101418"] {
color: #1AA9DE !important;
}

{{-- ❗❗❗ LOS FONDOS TEÑIDOS DEL AVISO TAMBIÉN SE INVIERTEN, Y ESTO ES UN DEFECTO QUE YA PASÓ.
   El aviso lleva un tinte CLARO fijo (`tintePapel` de v1.10) y el mapa de arriba sube su texto de
   Tinta a Papel 200 — así que en oscuro quedaba **texto claro sobre fondo claro: 1,28 : 1**, la
   caja entera ilegible. Lo vio el owner, no la guarda: *un mapa de color que solo mira el TEXTO
   está hecho a medias, porque el contraste lo hacen los dos lados.*
   ▶ Los cuatro pares salen enteros del sistema —`tintePapel` sobre papel, `tinte` sobre tinta— y
   no hay ningún valor nuevo. Medido: **9,45 · 9,04 · 9,66 · 10,37**. --}}
[style*="background-color:#D5EAEE"],
[style*="background-color: #D5EAEE"] {
background-color: #112934 !important;
border-color: #1A4657 !important;
}

[style*="background-color:#DFE9D6"],
[style*="background-color: #DFE9D6"] {
background-color: #252C19 !important;
border-color: #3E4A21 !important;
}

[style*="background-color:#F4EDCF"],
[style*="background-color: #F4EDCF"] {
background-color: #2A2413 !important;
border-color: #4A4020 !important;
}

[style*="background-color:#F0DBD2"],
[style*="background-color: #F0DBD2"] {
background-color: #2C1A17 !important;
border-color: #4A2620 !important;
}

{{-- ── Divisores ── --}}
.table th {
border-bottom-color: #2A3138 !important;
}
}
</style>
{!! $head ?? '' !!}
</head>
<body>

{{-- ══════════ LA LÍNEA DE ADELANTO (`#506`; artboard `Correos PJP`, decisión 23 del canvas) ══════════
   Lo que el gestor de correo enseña DETRÁS DEL ASUNTO en la lista de la bandeja. Hasta hoy no
   existía en ninguno de los 23 (medido: cero ocurrencias), así que el gestor cogía lo primero del
   cuerpo — y lo primero era el saludo: **los veintiuno se anunciaban con «¡Hola!»**.

   ❗❗❗ VA AQUÍ Y NO EN EL CUERPO, y el motivo es medible: lo primero que se lee es lo primero del
   DOCUMENTO, y encima del cuerpo va la cabecera, cuyo logotipo lleva `alt="{nombre del negocio}"`.
   Puesta en el slot, la bandeja leería «SaltoPark» y luego la frase. Aquí no hay nada delante.

   ❗❗ POR QUÉ SEIS DECLARACIONES PARA ESCONDER UN `<div>`: ningún cliente de correo las respeta
   todas y basta con que respete una. `display:none` lo tapa en la mayoría; Outlook lo ignora y ahí
   muerden `max-height`/`overflow`; los que respetan `opacity` lo tapan aunque pinten la caja; y
   `font-size:1px` con `line-height:1px` evita que deje una franja de aire si todo lo demás falla.
   ⚠️ Va INLINE y no en el tema porque `CssToInlineStyles` descarta lo que no puede pegar a una
   etiqueta — la misma regla que obliga a que el modo oscuro viva en el `<style>` de arriba.

   ❗❗ Y EL RELLENO NO ES ADORNO. Sin él, el gestor pinta la línea de adelanto y **sigue leyendo el
   cuerpo detrás**, así que la bandeja acaba diciendo «…y llegar 10 min antes. Reserva confirmada
   Nos vemos el sábado 4…». Los espacios de ancho cero ocupan el resto de la previsualización sin
   pintar nada. Se repite lo justo: son ~200 bytes en un correo, y viajan en los veintiuno. --}}
@isset($preheader)
@if ($preheader !== null && $preheader !== '')
<div style="display:none; font-size:1px; line-height:1px; max-height:0; max-width:0; opacity:0; overflow:hidden; mso-hide:all;">{{ $preheader }}{!! str_repeat('&#8199;&#65279;&#847; ', 40) !!}</div>
@endif
@endisset

<table class="wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="center">
<table class="content" width="100%" cellpadding="0" cellspacing="0" role="presentation">
{!! $header ?? '' !!}

<!-- Email Body -->
<tr>
<td class="body" width="100%" cellpadding="0" cellspacing="0" style="border: hidden !important;">
<table class="inner-body" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
<!-- Body content -->
<tr>
<td class="content-cell">
{!! Illuminate\Mail\Markdown::parse($slot) !!}

{!! $subcopy ?? '' !!}
</td>
</tr>
</table>
</td>
</tr>

{!! $footer ?? '' !!}
</table>
</td>
</tr>
</table>
</body>
</html>
