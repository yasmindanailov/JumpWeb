@props(['label'])

{{-- **EL CÓDIGO DE EJEMPLO de la sección «Antes de venir»** (Fase 2 · T2f).

     ⚠️⚠️ **No es un QR y no puede serlo** (`[DECIDIDO owner, 2026-09-10]`: «de ejemplo, no lleva a
     nada»). El porqué, el mecanismo y la propiedad que hay que conservar viven en
     {@see \App\Domain\Platform\Services\SampleQrCode} — se leen ANTES de tocar esto.
     ▶ Aquí lo único que hay que saber: **no lo cambies por `QrCode::svg()`**, que es el generador
     de verdad y produce un código que escanea.

     ⚠️ **El nombre accesible dice «de ejemplo»**, y no es adorno: sin él, un lector de pantalla
     anuncia «código QR» y quien lo oiga entenderá que ahí hay algo que escanear.

     ⚠️⚠️ **EL ICONO CALADO ES EL HUECO DE LA INSTALACIÓN, y sin paquete NO se dibuja.** Es la
     doctrina de {@see \App\Domain\Platform\Services\QrLogo}: enseñar la «J» del producto dentro del
     código de un cliente que entregó su marca es una fuga de white-label, y `public/favicon.svg`
     todavía lleva quemado el naranja del PRIMER cliente (ficha en `DEUDA.md`). Un código liso no es
     una fuga; uno con la marca de otro, sí. **La degradación baja de calidad, nunca de marca.**

     ⚠️ `@filemtime` hace las dos cosas en una sola llamada a disco —existencia y cache-busting—,
     igual que `<x-site.favicon>` y que el hueco de `client.css`. --}}

@php($marca = @filemtime(public_path('img/client-favicon.svg')))

<span {{ $attributes->merge(['class' => 'sqr']) }} role="img" aria-label="{{ $label }}">
    {{-- ⚠️ **El `fill` lo pone el CSS, no el marcado**, que es la doctrina de `QrCode::svg()`: el
         color de un código es contraste, no tema. Y ahí `--fg` NO vale: esta pieza vive dentro de
         una superficie de TINTA sobre una pantalla BLANCA, así que `var(--fg)` saldría claro sobre
         blanco — el defecto que `#483` y `#484` pagaron dos veces. Lee `--paper-fg`. --}}
    <svg class="sqr__code" viewBox="0 0 {{ \App\Domain\Platform\Services\SampleQrCode::MODULES }} {{ \App\Domain\Platform\Services\SampleQrCode::MODULES }}"
         aria-hidden="true" focusable="false">
        <path d="{{ \App\Domain\Platform\Services\SampleQrCode::path() }}" />
    </svg>

    @if ($marca)
        {{-- ⚠️ **`aria-hidden` va TAMBIÉN en el `<img>`, no solo en su envoltorio**, y lo dijo
             `SeoTest`: un `alt=""` a secas deja a quien no ve sin saber si eso era contenido. La
             pareja completa (`alt=""` + `aria-hidden`) es la forma canónica de decir «decorativa»,
             y aquí lo es de verdad: el nombre accesible lo pone el `role="img"` de fuera. --}}
        <span class="sqr__mark" aria-hidden="true">
            <img src="{{ asset('img/client-favicon.svg').'?v='.$marca }}" alt="" aria-hidden="true" width="64" height="64">
        </span>
    @endif
</span>
