@props(['product', 'isPack' => false])

{{-- Complementos aplicables a un producto (entrada o pack), de forma COMPACTA y SECUNDARIA
     bajo la tarjeta del producto. Coherente con el pivote `product_addons` (lo que de verdad
     se puede añadir) vía `LandingAddonPresenter`. Menos peso visual que la tarjeta principal.
     Los complementos en un mismo `choice_group` se agrupan bajo «Elige una opción» (excluyentes,
     espejo del checkout) — no se listan como si se sumaran. (#194) --}}
@php
    $rows = \App\Domain\Content\Services\LandingAddonPresenter::rows($product, $isPack);
    // Sueltos (sin grupo) vs grupos de elección excluyente (misma clave `choice_group`).
    $singles = array_values(array_filter($rows, fn ($r): bool => $r['group'] === null));
    $groups = [];
    foreach ($rows as $r) {
        if ($r['group'] !== null) {
            $groups[$r['group']][] = $r;
        }
    }
@endphp

@if (! empty($rows))
    <div class="addons-mini">
        <span class="addons-mini__label">{{ __('landing.addons.label') }}</span>
        <ul class="addons-mini__list">
            @foreach ($singles as $row)
                <x-site.addon-chip :row="$row" />
            @endforeach

            @foreach ($groups as $members)
                <li class="addons-mini__group">
                    <span class="addons-mini__group-label">{{ __('tickets.addon_choose_one') }}</span>
                    <ul class="addons-mini__list addons-mini__list--group">
                        @foreach ($members as $row)
                            <x-site.addon-chip :row="$row" />
                        @endforeach
                    </ul>
                </li>
            @endforeach
        </ul>
    </div>
@endif
