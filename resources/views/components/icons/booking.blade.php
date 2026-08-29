{{-- Reserva hecha · `ui/reserva` (variante **1d**) del artboard `Iconos PJP`, sección
     «00 REVISIÓN · LOTE 2».
     ⚠️⚠️ **Este glifo NO está en la sección 02 del set**: el cliente lo dibujó con tres variantes,
     lo razonó y **nunca llegó a decidirlo** — su texto cierra el lote con «dime los códigos que te
     quedas y los sustituyo en el set de la sección 02», y esa respuesta no llegó.
     ▶ La variante la elige **su criterio, no el nuestro**: de 1d dice «reserva hecha; **es el gesto
     que se lee más rápido y el que mejor aguanta a 20**». Las otras dos —1e «día marcado», 1f
     «franja cogida»— siguen en el artboard y cambiarlo es un fichero.
     ⚠️ Y su motivo para que exista: «la landing usa `ui/fecha` para reservar, y **un calendario a
     secas no dice que la plaza ya esté cogida**».
     ⚠️⚠️ **No confundir con la celda «ACTUAL» de esa fila**: ahí el artboard enseña lo que hay HOY
     —`ui/fecha`, el calendario pelado— para comparar, no una propuesta. Un extractor que emparejó
     por posición sacó justamente esa y produjo un `booking` idéntico a `calendar`.
     Rejilla 24, área viva 20, masa mínima 3, `currentColor`. Copiado con un guion, no transcrito. --}}
<svg {{ $attributes->merge(['width' => 24, 'height' => 24]) }} viewBox="0 0 24 24" fill="currentColor"
     aria-hidden="true" focusable="false">
    <path fill-rule="evenodd" clip-rule="evenodd" d="M7.4 2.6a1.6 1.6 0 0 1 1.6 1.6v.6h6v-.6a1.6 1.6 0 0 1 3.2 0v.6h.6a2.8 2.8 0 0 1 2.8 2.8v10.8a2.8 2.8 0 0 1-2.8 2.8H5.2a2.8 2.8 0 0 1-2.8-2.8V7.6a2.8 2.8 0 0 1 2.8-2.8h.6v-.6a1.6 1.6 0 0 1 1.6-1.6zM5.6 10.6v8h12.8v-8z" />
    <path d="M8.4 14.6 10.9 17.1 15.6 12.4" fill="none" stroke="currentColor" stroke-width="2.9" stroke-linecap="round" stroke-linejoin="round" />
</svg>
