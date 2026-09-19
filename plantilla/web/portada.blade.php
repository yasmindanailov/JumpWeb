{{--
    La PORTADA de ejemplo de una instancia recién estrenada.

    ⚠️ **No usa ni un componente del producto, a propósito.** Es lo que demuestra que una instancia puede
    pintarse sola: el producto presta el motor (las rutas, el cajón, el panel), pero no obliga a nadie a
    heredar su maquetación. Una landing de verdad SÍ puede usar `<x-layout>`, `<x-site.nav>` y los demás
    —es la vía B— pero entonces queda atada a esos nombres, y eso se elige a sabiendas.

    Se sustituye entera. Es un punto de partida, no una base que mantener.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $nombre ?? 'Instalación sin nombre' }}</title>
    {{-- `noindex` mientras sea la de ejemplo: una portada de plantilla indexada es peor que ninguna. --}}
    <meta name="robots" content="noindex">
</head>
<body>
    <main>
        <h1>{{ $nombre ?? 'Instalación sin nombre' }}</h1>
        <p>
            Esta es la portada de ejemplo de la plantilla de instancia. Sustitúyela por la landing de la
            instalación en <code>web/</code>.
        </p>
    </main>
</body>
</html>
