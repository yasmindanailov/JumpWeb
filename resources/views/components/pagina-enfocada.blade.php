{{--
    **LA HOJA LIMPIA DE UNA PÁGINA ENFOCADA** (`specs/fiesta-sistema-nuevo.md` §3.4·c y §4.3): las tres páginas de la
    fiesta del sistema nuevo —la lista de invitados, la invitación con su recibo y la autorización—. Sin isla, sin
    menú, sin banner de cookies, sin analítica ni píxeles (`#739`: el invitado no es un visitante; el hecho lo deja el
    controlador). Página privada con datos de menores: `noindex`, y `Referrer-Policy: no-referrer` para que un
    «Cómo llegar» no lleve el enlace a un tercero (`RGPD-04`; el `no-store` lo pone el controlador).

    Carga SOLO: la entrada de la fiesta del producto (`resources/js/fiesta/<entrada>.js`, que trae `fiesta.css` con
    los roles NEUTROS) y, después, las hojas del paquete de la instancia (`hojas`: rutas bajo `public/`, las que dé el
    contrato de hojas de `instancia.json`; vacío = sin paquete, y la página se ve entera y neutra). Nada de
    `landing.css`, `site.css` ni el `client.css` viejo: ese es `focused-layout`, que sigue sirviendo a las páginas
    que aún no tienen diseño (las encuestas).

    ⚠️ La clase `js` la pone el PROPIO script de la página al final de inicializar, no esta hoja: si el script falla,
    el documento se queda en `no-js` (fichas abiertas, formulario usable). `head` es un hueco para la cabecera de
    UNA página (la invitación: su `og:*`); vacío no pinta nada.
--}}
@props(['titulo', 'entrada' => 'lista', 'hojas' => [], 'cuerpo' => ''])
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="no-js">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">

    <title>{{ $titulo }}</title>

    <x-site.favicon />

    @vite(['resources/js/fiesta/'.$entrada.'.js'])
    @foreach ($hojas as $hoja)
        <link rel="stylesheet" href="{{ asset($hoja) }}?v={{ @filemtime(public_path($hoja)) }}">
    @endforeach

    {{ $head ?? '' }}
</head>
<body @class(['fiesta-'.$entrada, $cuerpo => $cuerpo !== ''])>
    {{ $slot }}
</body>
</html>
