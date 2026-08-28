<!DOCTYPE html>
{{-- `fi` en <html> es lo que da `min-height: 100dvh` al documento (regla `html.fi` del bundle del
     panel): sin ella `.fi-body` estira el body pero el html queda corto y el fondo se corta al
     hacer scroll. --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="fi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ \App\Domain\Platform\Models\Setting::value('business.name') ?: config('app.name') }} · {{ __('admin.puerta.validar.title') }}</title>

    {{-- Reusa el theme custom del panel (decisión #124): mismo bundle Tailwind 4
         con `@source` que escanea `resources/views/livewire/admin/**/*`. Sin esto
         las clases utility de la vista no compilarían. --}}
    @vite(['resources/css/filament/admin/theme.css'])

    {{-- ⚠️ PRERREQUISITO DE TODO EL DISEÑO (`specs/identidad-qr-puerta.md` §9.7 C·5). El bundle de
         arriba **usa** `var(--gray-500)`, `var(--primary-600)`, `var(--success-600)`… pero **no las
         declara**: quien las emite es `@filamentStyles`, y esta página no lo llamaba. Medido en
         navegador antes del cambio: `--gray-500` vacía → `text-gray-500` calculaba `rgb(0,0,0)`
         (negro puro, no gris), el `<body>` calculaba `rgba(0,0,0,0)` en vez del gris de fondo y el
         `ring-1 ring-gray-950/5` del formulario salía como un borde NEGRO SÓLIDO de 1 px.
         `ValidarRegistro::boot()` deja el panel «actual» y booteado en CADA petición — sin eso
         `primary` no seguiría la marca del cliente (`theme.brand`) sino el ámbar por defecto de
         Filament. ⚠️ Lo que NO trae es la tipografía: eso lo monta el bloque de abajo. --}}
    @filamentStyles

    {{-- ⚠️ MEDIDO: `@filamentStyles` **NO** trae la tipografía del panel, al contrario de lo que
         suponía la spec (§9.7 C·5). La fuente la montan estas otras líneas, que en el panel viven en
         `filament/filament/…/components/layout/base.blade.php` justo debajo. Sin ellas el `<body>`
         calculaba `ui-sans-serif, system-ui…` (la pila del sistema, no la del panel) y —peor—
         `--mono-font-family` quedaba SIN DEFINIR, con lo que `var(--font-mono)` era un valor inválido
         y el código de pedido salía en sans. El `<link>` a Bunny Fonts ya está permitido por la CSP
         (`font-src`/`style-src`); si la red del recinto falla, la pila de reserva sigue leyéndose. --}}
    {{ \Filament\Facades\Filament::getFontPreloadHtml() }}
    {{ \Filament\Facades\Filament::getMonoFontPreloadHtml() }}
    {{ \Filament\Facades\Filament::getFontHtml() }}
    {{ \Filament\Facades\Filament::getMonoFontHtml() }}
    <style>
        :root {
            --font-family: '{!! \Filament\Facades\Filament::getFontFamily() !!}';
            --mono-font-family: '{!! \Filament\Facades\Filament::getMonoFontFamily() !!}';
        }
    </style>

    @livewireStyles

    {{-- Mismo modo claro/oscuro que el panel, con SU misma clave de `localStorage` (`theme`): el
         empleado llega aquí desde la barra lateral del panel y la pantalla no puede cambiarle el
         tema por el camino. Copiado de `filament/filament/resources/views/components/layout/base.blade.php`
         (el panel usa `defaultThemeMode = System`), sin el conmutador: aquí no hay dónde ponerlo. --}}
    <script>
        (() => {
            const theme = localStorage.getItem('theme') ?? 'system'

            if (theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark')
            }
        })()
    </script>
</head>
{{-- `.fi-body` ya pinta fondo, color de texto, `min-h-dvh` y el antialiasing en los dos modos: las
     utilidades `bg-gray-50 dark:bg-gray-950 antialiased min-h-screen` que había aquí eran una copia
     manual de esa misma regla (y la copia era la que se veía rota mientras faltaban los tokens). --}}
<body class="fi-body">
    <main class="gate-shell">
        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
