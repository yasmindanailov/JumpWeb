{{-- ══ EL LABORATORIO DE FACHADA · armazón ════════════════════════════════════════════════════
     `DECISIONES #545` · pasada de vestido (`#497`). Dos pantallas para que el owner elija MIRANDO
     cómo se coloca el material de fachada: `/_diseno/splash` y `/_diseno/siluetas`.

     ❗❗❗ **ES TEMPORAL Y NO SE SIRVE EN PRODUCCIÓN.** La ruta está cerrada por entorno; cuando el
     owner elija, estas vistas se van con las 18 ranuras de laboratorio que las alimentan.

     ⚠️⚠️ **El CSS va EN LÍNEA aquí y no en `landing.css`, y no es pereza.** Las hojas del producto
     tienen guarda de huérfanos (`FacadeCssHasNoOrphansTest`): meter ~40 clases de prototipo ahí
     dejaría la hoja llena de reglas sin consumidor el día que estas vistas se borren, que es
     exactamente la deuda que ese test existe para no acumular. Aquí desaparecen con su pantalla.

     ⚠️ Carga las MISMAS hojas y las MISMAS fuentes que la web (`landing.css` → `site.css` →
     `client.css`, en ese orden): un laboratorio con otros tokens enseña otra cosa. --}}
@props(['titulo', 'lede'])
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ $titulo }} · laboratorio de fachada</title>

<link rel="preconnect" href="https://fonts.bunny.net">
<link rel="stylesheet" href="{{ \App\Domain\Content\Services\ThemeFonts::stylesheetUrl() }}">
<link rel="stylesheet" href="{{ asset('css/landing.css') }}?v={{ @filemtime(public_path('css/landing.css')) }}">
<link rel="stylesheet" href="{{ asset('css/site.css') }}?v={{ @filemtime(public_path('css/site.css')) }}">
@php($clientTheme = @filemtime(public_path('css/client.css')))
@if ($clientTheme)
    <link rel="stylesheet" href="{{ asset('css/client.css') }}?v={{ $clientTheme }}">
@endif

<style>
    /* ── El armazón del laboratorio ─────────────────────────────────────────────────────── */
    body { margin: 0; background: var(--bg); color: var(--fg); font-family: var(--font-body); }
    .lab { max-width: var(--col-max, 1120px); margin: 0 auto; padding: var(--sp-28) var(--col-gutter, 20px) 120px; }
    .lab__bar { display: flex; flex-wrap: wrap; align-items: baseline; gap: var(--sp-16); margin-bottom: var(--sp-28); }
    .lab__kicker { font-family: var(--font-mono); font-size: var(--fs-11); letter-spacing: .16em; text-transform: uppercase; color: var(--fg-mute); margin: 0; }
    .lab__title { font-family: var(--font-display); font-weight: 400; font-size: var(--fs-title); line-height: 1.05; margin: 0; }
    .lab__lede { margin: var(--sp-10) 0 0; font-size: var(--fs-body); line-height: 1.55; color: var(--fg-mute); max-width: 72ch; }
    .lab__nav { margin-left: auto; display: flex; gap: var(--sp-10); }
    .lab__nav a { font-family: var(--font-mono); font-size: var(--fs-12); letter-spacing: .1em; text-transform: uppercase;
                  color: var(--interactive); text-decoration: none; border-bottom: 1.5px solid currentColor; padding-bottom: 2px; }
    .lab__nav a[aria-current] { color: var(--fg); }

    /* ── Una variante ───────────────────────────────────────────────────────────────────── */
    .lab-v { margin: 0 0 56px; }
    .lab-v__head { display: flex; align-items: baseline; gap: var(--sp-12); margin-bottom: var(--sp-12); }
    .lab-v__code { font-family: var(--font-mono); font-size: var(--fs-12); font-weight: 700; letter-spacing: .1em;
                   background: var(--fg); color: var(--bg); border-radius: var(--r-xs); padding: 3px 8px; }
    .lab-v__name { font-size: var(--fs-17); font-weight: 800; }
    .lab-v__note { margin: var(--sp-12) 0 0; font-size: var(--fs-15); line-height: 1.5; color: var(--fg-mute); max-width: 78ch; }
    /* La CAJA es el sujeto: un bloque realista donde cae la pieza. Recorta como recortaría de
       verdad, porque la mitad de estas variantes viven precisamente del recorte. */
    .lab-box { position: relative; isolation: isolate; overflow: hidden; border-radius: var(--r-lg);
               background: var(--bg-card); border: 1px solid var(--line); padding: 40px var(--sp-28); min-height: 260px; }
    .lab-box--ink { background: var(--fg); border-color: transparent; }
    .lab-box--tall { min-height: 420px; }
    .lab-box--bleed { overflow: visible; }
    .lab-box__eyebrow { font-family: var(--font-mono); font-size: var(--fs-12); letter-spacing: .16em; text-transform: uppercase; color: var(--fg-mute); margin: 0 0 var(--sp-10); }
    .lab-box__title { font-family: var(--font-display); font-weight: 400; font-size: var(--fs-title); line-height: 1.05; margin: 0; }
    .lab-box__p { margin: var(--sp-16) 0 0; font-size: var(--fs-body); line-height: 1.6; color: var(--fg-mute); max-width: 46ch; }
    .lab-box--ink .lab-box__title { color: var(--ink-fg); }
    .lab-box--ink .lab-box__p,
    .lab-box--ink .lab-box__eyebrow { color: var(--ink-fg-body); }

    /* La pieza, siempre por detrás del contenido y siempre inerte. */
    .lab-p { position: absolute; z-index: -1; pointer-events: none; height: auto; }

    /* Rejilla para los muestrarios (las seis manchas, las doce poses). */
    .lab-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: var(--sp-20); }
    .lab-cell { display: grid; gap: var(--sp-8); justify-items: center; padding: var(--sp-16);
                background: var(--bg-card); border: 1px solid var(--line); border-radius: var(--r-lg); }
    .lab-cell__n { font-family: var(--font-mono); font-size: var(--fs-11); letter-spacing: .1em; color: var(--fg-mute); }
    .lab-cell .ilu { width: 92px; }
</style>
</head>
<body>
<div class="lab">
    <div class="lab__bar">
        <div>
            <p class="lab__kicker">Laboratorio de fachada · no es la web</p>
            <h1 class="lab__title">{{ $titulo }}</h1>
        </div>
        <nav class="lab__nav">
            <a href="{{ route('lab.splash') }}" @if (request()->routeIs('lab.splash')) aria-current="page" @endif>Splash</a>
            <a href="{{ route('lab.siluetas') }}" @if (request()->routeIs('lab.siluetas')) aria-current="page" @endif>Siluetas</a>
        </nav>
    </div>
    <p class="lab__lede">{{ $lede }}</p>

    {{ $slot }}
</div>
</body>
</html>
