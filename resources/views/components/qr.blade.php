@props(['data', 'label' => ''])
{{--
    **EL QR de una dirección, para las páginas de la instancia** (T6c·4b de `isla-y-landing-nueva.md` §4.19: la hoja para
    dirección de colegios, que lleva a la página con su cálculo). Un MECANISMO del producto: la vista de la instancia da la
    dirección y no toca el generador (`QrCode::svg`: SVG en el servidor, sin JavaScript, que se imprime nítido a cualquier
    tamaño). El color lo pone quien lo pinta (el SVG no lo fija: contraste oscuro sobre claro, para escanear). El dato NO
    aparece como texto en el SVG: incrustarlo sin escapar es seguro.
--}}
<span {{ $attributes->merge(['role' => 'img']) }} @if ($label !== '') aria-label="{{ $label }}" @endif>{!! \App\Domain\Platform\Services\QrCode::svg((string) $data) !!}</span>
