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

    {{-- ⚠️ SOLO MODO CLARO (`specs/puerta-nueva.md` §4.2·D3, el mockup: «solo modo claro por ahora»): la Puerta ya no
         sigue el modo oscuro del panel. Sin la clase `dark` en el `<html>`, `@filamentStyles` pinta los tokens claros.

         Lo del NAVEGADOR de la Puerta, en un componente de Alpine (`puertaPantalla`, en la raíz de la vista):
         · el SONIDO (Web Audio, el del mockup): dos notas que suben en verde, dos iguales en ámbar, una grave en rojo; el
           primer toque de la pantalla lo desbloquea (el navegador no deja sonar sin un gesto);
         · la DOBLE LECTURA: el mismo código en menos de 3 s no reabre la ficha ni vuelve a sonar. --}}
    <script>
        document.addEventListener('alpine:init', () => {
            window.Alpine.data('puertaPantalla', () => ({
                ctx: null,
                previo: { v: '', t: 0 },
                desbloquear() {
                    try {
                        this.ctx = this.ctx || new (window.AudioContext || window.webkitAudioContext)()
                        if (this.ctx.state === 'suspended') this.ctx.resume()
                    } catch (e) {}
                },
                sonar(tono) {
                    const notas = { verde: [[880, 0, .09, 'sine'], [1320, .1, .14, 'sine']], ambar: [[660, 0, .1, 'triangle'], [660, .16, .1, 'triangle']], rojo: [[196, 0, .32, 'sawtooth']] }[tono]
                    if (! notas) return
                    try {
                        this.desbloquear()
                        notas.forEach(([f, t0, d, w]) => {
                            const o = this.ctx.createOscillator(), g = this.ctx.createGain(), t = this.ctx.currentTime + t0
                            o.type = w; o.frequency.value = f
                            g.gain.setValueAtTime(0.0001, t); g.gain.exponentialRampToValueAtTime(w === 'sawtooth' ? .08 : .22, t + .01); g.gain.exponentialRampToValueAtTime(0.0001, t + d)
                            o.connect(g).connect(this.ctx.destination); o.start(t); o.stop(t + d + .02)
                        })
                    } catch (e) {}
                },
                doble(valor) {
                    const v = (valor || '').trim(), ahora = Date.now()
                    if (v !== '' && v === this.previo.v && ahora - this.previo.t < 3000) { this.previo.t = ahora; return true }
                    this.previo = { v, t: ahora }
                    return false
                },
            }))
        })
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
