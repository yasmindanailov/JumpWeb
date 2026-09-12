{{-- **LA «i» DEL PRODUCTO — UNA SOLA, Y SU TOOLTIP** (`DECISIONES #542`).

     ❗❗❗ **Unifica TRES «i» que existían con tres aspectos distintos** (`[DECIDIDO owner,
     2026-09-12]`: «la "i" debe ser siempre la misma»): la del pie del cajón (16 px, gris, panel con
     Alpine), la decorativa de la sección 05 (24 px, sin panel) y la que nació con la tarifa
     especial. El ASPECTO vive en `.info-i`, una clase que comparten los tres — el cajón es Vue y no
     puede usar este componente, así que lo compartido tiene que ser la clase, no el Blade.

     ⚠️⚠️ **El panel es un `popover` NATIVO, y ése es el requisito que decide la técnica**: el owner
     pidió «que esté encima siempre y no se recorte por falta de pantalla o porque haya un elemento
     encima». Un panel `absolute` lo recorta cualquier ancestro con `overflow` y lo tapa cualquier
     `z-index` mayor; el `popover` se pinta en la **top layer**, que está por encima de todo el
     documento y fuera de todo recorte. Y es HTML: cierra con Escape, cierra al pulsar fuera y entra
     en el orden de tabulación **sin una línea de JS**.

     ⚠️ **Sin sombra dura** (`[DECIDIDO owner]`): la dura es la de las pegatinas, que dicen «esto se
     coge». Un tooltip no se coge, así que lleva la difusa de los modales, que es lo que el sistema
     reserva para lo que flota por encima de la página.

     @param $label  el nombre accesible: QUÉ se va a explicar, no «más información»
     @param $slot   el contenido del panel
--}}
@props(['label'])

@php($id = 'tip-'.\Illuminate\Support\Str::random(8))

<span class="infotip">
    <button type="button" class="info-i" popovertarget="{{ $id }}" aria-label="{{ $label }}" data-tap>
        <x-icons.info class="info-i__ico" width="16" height="16" />
    </button>
    {{-- ⚠️ `role="note"`: no es un diálogo —no atrapa el foco ni bloquea la página—, y anunciarlo
         como tal prometería una conducta que no tiene. --}}
    <div class="infotip__panel" id="{{ $id }}" popover role="note">
        {{ $slot }}
    </div>
</span>
