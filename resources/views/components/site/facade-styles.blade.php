{{-- ══ FACHADA · el andamio de las variantes ══════════════════════════════════════════════════
     Se emite UNA vez y solo con variante activa (`?fachada=1|2` en `local`). Sin ella, la portada
     no lleva ni este `<style>` ni una sola regla de más — que es la condición para que un prototipo
     pueda vivir dentro de la vista de producción sin ensuciarla.

     ⚠️⚠️ **`isolation` es lo que hace que la pieza se vea.** `.section` es `position: relative` sin
     `z-index`, así que NO crea contexto de apilamiento: una pieza a `z-index: -1` dentro se hunde
     por detrás del fondo de la página y desaparece —sin fallar, que es como este defecto se cuela—.
     La regla va aquí y no en `landing.css` porque afecta a una declaración que comparten las doce
     vistas, y crear un contexto de apilamiento puede mover cualquier `z-index` de dentro.

     ⚠️ El recorte es de la SECCIÓN (`inset: 0` + `overflow: hidden`): una figura más alta que su
     caja se corta por el borde en vez de empujar el alto de la página. Las piezas marcadas como
     `escapa` lo apagan en su propio `style`. --}}
@php($v = app()->environment('local') ? (int) request()->query('fachada', 0) : 0)

@if ($v === 1 || $v === 2)
    <style>
        /* Solo lo que recibe pieza: no se toca el apilamiento de nada más.
           ⚠️ En el cierre el contexto lo crea la TARJETA y no la sección, porque la pieza vive
           dentro de ella — y `.reserve__box` pinta su propio fondo de tinta, así que sin esto la
           figura se hunde por detrás de ese fondo y desaparece. */
        #zones, #events, #before, #info { isolation: isolate; }
        #reserve .reserve__box { isolation: isolate; }

        .fac-slot { position: absolute; inset: 0; z-index: -1; overflow: hidden; pointer-events: none; }
        .fac-p { position: absolute; height: auto; }

        /* En teléfono las figuras se encogen y se apartan: a 390 px una silueta de 560 tapa media
           sección. No es una variante distinta, es la misma con el tamaño que cabe. */
        @media (max-width: 700px) {
            .fac-p { max-height: 46vh; max-width: 62vw; }
        }

        /* El rótulo de la variante, para no confundir una captura con la portada de verdad. */
        .fac-flag {
            position: fixed; z-index: 2147483000; left: 12px; bottom: 12px;
            font-family: var(--font-mono); font-size: 11px; letter-spacing: .12em; text-transform: uppercase;
            background: var(--fg); color: var(--bg); padding: 7px 11px; border-radius: 999px;
            text-decoration: none; display: inline-flex; gap: 10px; align-items: center;
        }
        .fac-flag a { color: var(--bg); text-decoration: underline; text-underline-offset: 3px; }
    </style>

    <p class="fac-flag">
        Fachada · variante {{ $v }} «{{ $v === 1 ? 'dentro' : 'mural' }}»
        <a href="{{ url('/?fachada='.($v === 1 ? 2 : 1)) }}">ver la {{ $v === 1 ? 2 : 1 }}</a>
        <a href="{{ url('/') }}">sin fachada</a>
    </p>
@endif
