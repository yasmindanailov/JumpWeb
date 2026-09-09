@props([
    // Una fila de `LandingAddonPresenter::rows()`.
    'row',
    // La unidad del PRODUCTO al que se le añade («por persona», «por niño»), o `null`.
    'unit' => null,
])

{{-- **UN COMPLEMENTO, EN PÍLDORA** (`Precios PJP` 10a · `DECISIONES #479`).

     La forma del artboard: cápsula con borde de 1,5, «+ nombre · precio» en Hanken 600 y la unidad
     en mono al lado. Sustituye, **solo en la sección «Cuánto»**, a la lista de `<x-site.addon-chip>`
     —rótulo, enlace «Más info» y una fila por complemento—, que sigue viva en `/precios` y en la
     banda de cumpleaños porque esas superficies tienen sus propios artboards.

     ⚠️ **El dato es el mismo presentador**: aquí no se decide qué complementos admite una entrada,
     solo cómo se escriben.

     ⚠️⚠️ **La UNIDAD que se pinta es la del PRODUCTO, y solo cuando el complemento no trae la suya.**
     Un complemento por invitado ya dice «/invitado» dentro de su propia nota, y añadirle encima el
     «por persona» de la entrada afirmaría dos unidades para el mismo precio. *Una unidad de más no
     se ve como ruido: se lee como otro precio.*

     ⚠️ **Sin precio se dice el badge, no un hueco**: un complemento incluido o gratis no lleva
     cifra, y la píldora escribe lo que es. --}}
{{-- ⚠️ La misma clave que ya usa `<x-site.addon-chip>` (`tickets.addon_badge_*`): dos rótulos para
     el mismo estado se separan en cuanto alguien traduce uno. --}}
@php($etiqueta = $row['badge'] ? __('tickets.addon_badge_'.$row['badge']) : null)

<span class="addon-pill">
    <span class="addon-pill__t">+&nbsp;{{ $row['name'] }}@if ($row['price'] ?? null) · {{ $row['price'] }}&nbsp;€@elseif ($etiqueta) · {{ $etiqueta }}@endif</span>
    @if ($unit && ! ($row['perGuest'] ?? false))
        <span class="addon-pill__u">{{ $unit }}</span>
    @endif
</span>
