{{-- Nota general de «calcetines antideslizantes obligatorios» bajo el grid de precios.
     Reactiva el patrón previsto (`.pricing__note`, #6 · 2026-05-31, retirado en #195) ahora como
     callout con el icono de marca S1 (`<x-icons.socks>`). Aplica a TODAS las entradas, por eso vive
     con el catálogo (`ticket-prices`) → aparece coherente en la landing y en /precios. El texto es
     editorial multiidioma → `lang/{es,en,fr}/landing.php` (`pricing.socks_*`). Sin lógica. --}}
<div class="socks-note">
    <x-icons.socks class="socks-note__icon" :width="56" :height="56" />
    <div class="socks-note__body">
        <p class="socks-note__title">{{ __('landing.pricing.socks_title') }}</p>
        <p class="socks-note__text">{{ __('landing.pricing.socks_text') }}</p>
    </div>
</div>
