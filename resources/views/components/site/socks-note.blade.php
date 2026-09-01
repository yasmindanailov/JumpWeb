{{-- Nota general de «calcetines antideslizantes obligatorios» bajo el grid de precios.
     Reactiva el patrón previsto (`.pricing__note`, #6 · 2026-05-31, retirado en #195) ahora como
     callout con el icono de marca S1 (`<x-icons.socks>`). Aplica a TODAS las entradas, por eso vive
     con el catálogo (`ticket-prices`).
     ⚠️ **Desde `#309` ya NO se pinta en la portada**: allí el dato tiene tarjeta propia dentro de la
     sección de normas, con su CTA de compra (`[DECIDIDO owner]`). Aquí sigue **para `/precios`**,
     que no tiene sección de normas donde recogerlo — lo gobierna el prop `:socks` de
     `<x-site.ticket-prices>`.
     ⚠️ El texto se MOVIÓ de `landing.pricing.socks_*` a `landing.rules.socks_*` en el mismo cambio:
     una clave que nombra la sección de la que la nota acaba de salir induce a error al siguiente
     que la lea. Es la MISMA copia, no una segunda. --}}
<div class="socks-note">
    <x-icons.socks class="socks-note__icon" :width="56" :height="56" />
    <div class="socks-note__body">
        <p class="socks-note__title">{{ __('landing.rules.socks_title') }}</p>
        <p class="socks-note__text">{{ __('landing.rules.socks_text') }}</p>
    </div>
</div>
