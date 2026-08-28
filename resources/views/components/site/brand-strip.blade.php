{{-- **LA TIRA DE MARCA** — «C2 · tiras» del sistema del 2.º cliente.

     Cinco franjas de igual ancho y 4 px de alto. Los cinco colores salen de `--strip-1..5`, que
     **DERIVAN de los dos tokens de marca**: el producto no lleva ni un hex, y una instalación los
     redefine desde su `client.css` (`ShapeScaleTest` lo vigila: nada de hex crudos, nada de
     tokens semánticos, y nada de `color-mix` — dos colores casi complementarios mezclan a barro).

     Es puramente decorativa: `aria-hidden` y sin texto, así que no entra en el orden de lectura.

     ⚠️ **Es COMPONENTE, y por eso vive aquí y no dentro del pie** (`#226`). Hasta el 2026-08-28
     existía una sola vez, incrustada en `footer.blade.php`. El mockup la usa **dos veces** —remata
     el pie y encabeza el hero— y con la copia pegada volveríamos a tener dos cosas que deben ser
     idénticas y que nadie obliga a serlo. Es la misma lección que `#225` acaba de pagar con el CTA.

     ▶ El envase decide **dónde va y cuánto mide**; esto decide **qué es**. Quien la coloque pasa
     su clase de colocación por `class`, que se compone con la del componente. --}}
<div {{ $attributes->class(['brand-strip']) }} aria-hidden="true">
    <span></span><span></span><span></span><span></span><span></span>
</div>
