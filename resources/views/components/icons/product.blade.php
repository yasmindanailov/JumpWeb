@props([
    'isPack' => false,
])

{{-- Marcador de producto para listados: reutiliza los iconos de marca del CATÁLOGO
     (tarta B1 = cumpleaños · ticket «tear-off» = entrada) justo antes del nombre, en el
     sidecart y en «Mis pedidos» — patrón «(icono) Nombre». Discreto: la clase `.prod-ico`
     (public/css/site.css) lo deja monocromo (hereda el color del título), atenuado y SIN
     animación, para reforzar sin robar atención. Decorativo (los iconos ya ponen aria-hidden);
     la semántica la da el nombre del producto. --}}
@if ($isPack)
    <x-icons.ic-b1 class="prod-ico" :size="20" />
@else
    <x-icons.ticket-tear-off class="prod-ico" :width="22" :height="13" />
@endif
