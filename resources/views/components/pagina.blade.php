{{--
    **EL LAYOUT LIMPIO DE LAS PÁGINAS DECLARADAS** (T4b de `specs/isla-y-landing-nueva.md` §4.2; nace con su primera
    página, como pedía la T1c de §4.8). La cabecera que necesita cualquier página —título, descripción, Google y
    WhatsApp, canónica, favicon, JSON-LD y CSRF— y SOLO las hojas que la página declara (`hojas`: rutas bajo
    `public/`, las del paquete de la instancia). Nada de `landing.css`, `site.css` ni el `client.css` viejo: el tema
    de estas páginas es el de su paquete, y el producto no nombra el de ningún cliente.

    ⚠️ PENDIENTE, avisado al SPA (T4b·4): el estado del `<body>` —consentimiento, analítica y píxeles— compartido con
    `components/layout.blade.php` por un componente. Hasta entonces estas páginas no cargan analítica ni píxeles, y no
    salen de local: nada se despliega antes de la v2.0.0 (`#670`).
--}}
@props(['titulo', 'descripcion' => null, 'imagen' => null, 'hojas' => [], 'scripts' => [], 'noindex' => false])
@php
    $canonical = url()->current();
    $imagenOg = $imagen ? asset($imagen) : ($site['og_image'] ?? null ?: asset('og-image.jpg'));
    // Las entradas del PRODUCTO que una página puede pedir, por su NOMBRE (T4d, contrato de página): la instancia no
    // nombra ficheros del producto, y un nombre que no está aquí no carga nada. `cajon`: el cargador del paquete (la
    // compra se abre en la isla o en el lateral, según `sidebar.shell`); `calculadora`: la de la pieza de precio.
    $entradas = array_values(array_intersect_key([
        'cajon' => 'resources/js/cajon/paquete.js',
        'calculadora' => 'resources/js/isla/calculadora/montar.js',
    ], array_flip($scripts)));
    // Lo que la calculadora necesita y solo sabe el producto: sus textos, el TITULAR de la cesta —el mismo que da el
    // arranque del motor (`SidebarBoot`: leer la cesta con otro la purgaría)— y el idioma. ⚠️ En una variable: `@json`
    // parte su argumento por las comas, y un arreglo escrito dentro no se compila.
    $motorCalculadora = in_array('calculadora', $scripts, true)
        ? ['textos' => ['pieza' => __('isla.pieza'), 'calculadora' => __('isla.calculadora')], 'owner' => auth()->id(), 'locale' => app()->getLocale()]
        : null;
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $titulo }}</title>
    @if ($descripcion)
        <meta name="description" content="{{ $descripcion }}">
    @endif
    <link rel="canonical" href="{{ $canonical }}">
    <meta name="robots" content="{{ $noindex ? 'noindex, nofollow' : 'index, follow' }}">

    <x-site.favicon />

    {{-- Lo que se ve al compartir el enlace (en Kids y Jump la decisión es de grupo: es lo que ve el resto). --}}
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:title" content="{{ $titulo }}">
    @if ($descripcion)
        <meta property="og:description" content="{{ $descripcion }}">
    @endif
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:image" content="{{ $imagenOg }}">
    <meta name="twitter:card" content="summary_large_image">

    <x-site.json-ld :site="$site" />

    @foreach ($hojas as $hoja)
        <link rel="stylesheet" href="{{ asset($hoja) }}?v={{ @filemtime(public_path($hoja)) }}">
    @endforeach
    @if ($entradas !== [])
        @vite($entradas)
    @endif
</head>
<body>
    {{ $slot }}
    @if ($motorCalculadora !== null)
        <script type="application/json" id="jw-calculadora-motor">@json($motorCalculadora)</script>
    @endif
</body>
</html>
