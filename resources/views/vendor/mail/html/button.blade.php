@props([
    'url',
    'color' => 'ink',
    'align' => 'center',
])
{{--
    EL BOTÓN del correo (`DECISIONES #503`; artboard `Correos PJP` 1a).

    ❗❗❗ EL MAPA DEL NARANJA GOBIERNA TAMBIÉN AQUÍ (`[DECIDIDO owner, 2026-09-10]`): el naranja solo
    significa COMPRAR. De los veintiuno que lee un cliente solo DOS venden —«Reintentar el pago» y
    «Hacer una nueva reserva»—; los otros diecinueve van en **relleno de TINTA**. Quién es quién lo
    decide `vendor/notifications/email.blade.php` a partir de `level`, no este componente.

    ⚠️ `accion` (los dos que venden) toma el color de MARCA de la instalación
    (`ThemeSettings::brand()`), que es lo único de este correo que sale de la BD. `ink` es el
    secundario del sistema sobre papel y **no** es data-driven: la tinta es del producto.

    ❗❗ EL TEXTO LO DECIDE `onAction()`, NO `onBrand()`, y está MEDIDO (2026-09-10). `onBrand()`
    prefiere blanco y solo cae a tinta por debajo de **3,0**, que es el umbral de texto GRANDE; el
    rótulo de este botón mide **16 px con peso 800**, así que le toca **4,5**. Sobre el naranja del
    producto (`#FF5B22`) eso elegía BLANCO y dejaba el botón principal de los 23 correos en
    **3,10** — incumpliendo AA sin que nada fallara y sin que ninguna guarda lo mirara.
    ▶ `onAction()` no tiene preferencia estética: elige el que más contraste dé. Sobre `#FF5B22` da
    **tinta, 5,96**; sobre el Naranja Salto del sistema (`#F2711C`), **6,30**.

    ⚠️ EL RELLENO SE HACE CON `border`, NO CON `padding`: Outlook no respeta el segundo dentro de un
    `<a>`. Los valores salen del artboard —**56 de alto** y 28 a los lados— repartidos como
    17 arriba/abajo (17 + 22 de línea + 17 = 56) y 28 a los lados.
--}}
@php
    $marca = \App\Domain\Content\Services\ThemeSettings::brand();
    $paleta = [
        'accion' => [$marca, \App\Domain\Content\Services\ThemeSettings::onAction($marca)],
        'ink' => ['#101418', '#F4F4F1'],
        'success' => ['#5FA82E', '#101418'],
        'error' => ['#D93E14', '#FFFFFF'],
        // Compatibilidad con los nombres de Laravel, por si un correo nuevo los usa sin querer.
        'primary' => ['#101418', '#F4F4F1'],
        'green' => ['#5FA82E', '#101418'],
        'red' => ['#D93E14', '#FFFFFF'],
        'blue' => ['#101418', '#F4F4F1'],
    ];
    [$fondo, $texto] = $paleta[$color] ?? $paleta['ink'];
@endphp
<table class="action" align="{{ $align }}" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td>
<a href="{{ $url }}" class="button button-{{ $color }}" target="_blank" rel="noopener" style="background-color: {{ $fondo }}; border-top: 17px solid {{ $fondo }}; border-bottom: 17px solid {{ $fondo }}; border-left: 28px solid {{ $fondo }}; border-right: 28px solid {{ $fondo }}; color: {{ $texto }};">{!! $slot !!}</a>
</td>
</tr>
</table>
</td>
</tr>
</table>
</td>
</tr>
</table>
