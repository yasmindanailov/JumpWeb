{{--
    **EL LAYOUT LIMPIO DE LAS PÁGINAS DECLARADAS** (T4b de `specs/isla-y-landing-nueva.md` §4.2; nace con su primera
    página, como pedía la T1c de §4.8). La cabecera que necesita cualquier página —título, descripción, Google y
    WhatsApp, canónica, favicon, JSON-LD y CSRF— y SOLO las hojas que la página declara (`hojas`: rutas bajo
    `public/`, las del paquete de la instancia). Nada de `landing.css`, `site.css` ni el `client.css` viejo: el tema
    de estas páginas es el de su paquete, y el producto no nombra el de ningún cliente.

    ▶ El estado del `<body>` —consentimiento, analítica y píxeles— es el MISMO que el de `components/layout.blade.php`
    (T4b·4, con el visto bueno del SPA): los dos incluyen `components/site/body-state.blade.php`. Lo que LO LEE —el
    aviso de cookies dentro de la isla y los cargadores del driver y los píxeles— llega con la T4e.
--}}
@props(['titulo', 'descripcion' => null, 'imagen' => null, 'hojas' => [], 'scripts' => [], 'isla' => null, 'noindex' => false])
@php
    $canonical = url()->current();
    $imagenOg = $imagen ? asset($imagen) : ($site['og_image'] ?? null ?: asset('og-image.jpg'));
    // Las entradas del PRODUCTO que una página puede pedir, por su NOMBRE (T4d, contrato de página): la instancia no
    // nombra ficheros del producto, y un nombre que no está aquí no carga nada. `cajon`: el cargador del paquete (la
    // compra se abre en la isla o en el lateral, según `sidebar.shell`); `calculadora`: la de la pieza de precio;
    // `isla`: la isla EN REPOSO de la página (T4e), con lo que la página le da en `isla`.
    $entradas = array_values(array_intersect_key([
        'cajon' => 'resources/js/cajon/paquete.js',
        'calculadora' => 'resources/js/isla/calculadora/montar.js',
        'isla' => 'resources/js/isla/pagina/montar.js',
    ], array_flip($scripts)));
    // La isla de la página (T4e): lo que da la página —su tipo, su acción, su «desde», hoy, el menú, el contacto— más lo
    // que solo sabe el producto: sus textos (los de la isla, sin los de la compra ni la calculadora, que viajan con
    // ellas, ni los de Mi cuenta, que viven en el motor y viajan con la sesión: eran 4 KB en cada página, `PERF-02`),
    // si hay sesión y dónde está la política de cookies.
    $islaDePagina = in_array('isla', $scripts, true) && is_array($isla)
        ? [
            // `cookiesPanel`: los textos LEGALES de cada finalidad (los de la web de siempre) para la segunda capa.
            // `cuenta` (T5c, `#776`): con sesión, si la próxima es hoy y su primera tarea pendiente, ya escritas.
            // `aviso` (T5e·2, `#779`): el `status` que dejó el servidor al volver aquí (Google, el correo…), con su tono.
            // Solo esta petición lo ve: el motor pide su arranque después, con el `status` ya gastado.
            'config' => $isla + [
                'owner' => auth()->id(), 'cookiesUrl' => route('legal.cookies'), 'cookiesPanel' => __('cookies.panel'),
                'cuenta' => app(\App\Http\Cuenta\AntesDeVenir::class)->paraLaIsla(auth()->user()),
                'aviso' => app(\App\Http\Cuenta\AvisoDeSesion::class)->paraLaIsla(session('status')),
            ],
            'textos' => \Illuminate\Support\Arr::except((array) __('isla'), ['compra', 'calculadora', 'mi_cuenta', 'mi_cuenta_alta']),
        ]
        : null;
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

    {{-- Con el cajón, SU hoja (`#636`: el paquete son dos líneas, la hoja y el cargador): la cuenta se abre en el lateral
         hasta la T5, y sin ella el lateral salía sin estilo al pie de la página (medido, T4e·2). La hoja no toca la página
         que la aloja (F4, idéntica a 0 píxeles), y va antes que las de la página. --}}
    @if (in_array('cajon', $scripts, true))
        <link rel="stylesheet" href="{{ asset('css/cajon.css') }}?v={{ @filemtime(public_path('css/cajon.css')) }}">
    @endif
    @foreach ($hojas as $hoja)
        <link rel="stylesheet" href="{{ asset($hoja) }}?v={{ @filemtime(public_path($hoja)) }}">
    @endforeach
    @if ($entradas !== [])
        @vite($entradas)
    @endif
</head>
{{-- T4b·4: el MISMO estado del `<body>` que las páginas de siempre —consentimiento, analítica y píxeles— para que los
     lean el aviso de cookies y los cargadores (`components/site/body-state.blade.php`). --}}
<body
      @include('components.site.body-state')>
    {{ $slot }}
    @if ($motorCalculadora !== null)
        <script type="application/json" id="jw-calculadora-motor">@json($motorCalculadora)</script>
    @endif
    @if ($islaDePagina !== null)
        <script type="application/json" id="jw-isla-pagina">@json($islaDePagina)</script>
    @endif
</body>
</html>
