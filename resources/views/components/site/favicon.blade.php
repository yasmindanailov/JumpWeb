{{-- **EL ICONO DE LA INSTALACIÓN** — el hueco por donde entra el icono de un cliente.

     Cuarta pieza del mismo patrón que `client.css` (`DECISIONES #143`) y el logotipo (`#206`), y
     con dos de las tres parece que funciona: el fichero **no se versiona**, se carga **si existe**,
     y `deploy.sh` lo **excluye del `rsync --delete`** — sin esa exclusión el primer despliegue lo
     borra y vuelve el icono del producto, en silencio.

     ▶ **El suelo es `public/favicon.svg`, el icono DEL PRODUCTO.** ⚠️ Y hoy lleva `#FF5B22`
     quemado, que es el naranja del PRIMER cliente: la misma fuga que `DECISIONES #139` cerró en el
     CSS, sobrevivida dentro de un fichero que ninguna guarda de color mira. Ficha en `DEUDA.md`.

     ⚠️⚠️ **CORRECCIÓN (`#216`): hasta el 2026-08-28 este hueco era SOLO el SVG**, y eso dejaba
     **iOS y el «añadir a pantalla de inicio» de Android con la «J» del producto** — los dos
     ignoran el SVG y van a los PNG. `[DECIDIDO owner, 2026-08-28]`: se amplía al set completo, con
     el paquete del 2.º cliente ya entregado. El aviso de antes («ampliarlo es añadir dos ficheros
     más al mismo patrón») queda cumplido: son cinco, y cada uno es una línea aquí, una en
     `.gitignore` y una en `deploy.sh`.

     ❗ **Cada pieza se comprueba POR SEPARADO, no en bloque.** Una instalación puede entregar el
     SVG y no el `.ico`, o al revés: si se decidiera con una sola condición, faltar uno dejaría
     fuera a los demás. Cada `@if` cae a su suelo del producto por su cuenta.

     ▶ Por eso, cuando la instalación trae su SVG, **el PNG de 64 px se retira del `<head>`**: un
     navegador que entienda los dos elegiría el PNG por ser más específico en tamaño, y volvería a
     enseñar la «J» del producto teniendo el icono del cliente al lado.

     ⚠️ **`client-icon-512-maskable.png` NO se declara aquí y es a propósito**: un icono maskable
     solo lo lee un **manifiesto de aplicación web**, y este producto no sirve ninguno. El fichero
     se acepta en el paquete —para que el día que exista el manifiesto ya esté— pero declararlo con
     un `<link rel="icon">` no haría nada. Ficha en `DEUDA.md`.

     ⚠️ `@filemtime` hace las DOS cosas en una sola llamada a disco —existencia y cache-busting—:
     devuelve `false` si no está. Mismo recurso que `client.css` y que el logotipo. --}}

@php($clientIcon = @filemtime(public_path('img/client-favicon.svg')))
@php($clientIco = @filemtime(public_path('img/client-favicon.ico')))
@php($clientApple = @filemtime(public_path('img/client-apple-touch-icon.png')))
@php($clientPng192 = @filemtime(public_path('img/client-icon-192.png')))
@php($clientPng512 = @filemtime(public_path('img/client-icon-512.png')))

@if ($clientIcon)
    <link rel="icon" href="{{ asset('img/client-favicon.svg') }}?v={{ $clientIcon }}" type="image/svg+xml">
@else
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('favicon-64.png') }}" sizes="64x64" type="image/png">
@endif

{{-- El `.ico` es lo que pide un navegador antiguo y lo que se sirve a quien va a `/favicon.ico` a
     pelo. El producto tiene uno de **0 bytes** y sin `<link>` (ficha en `DEUDA.md`), así que aquí
     no hay suelo que declarar: o está el del cliente, o no se declara ninguno. --}}
@if ($clientIco)
    <link rel="icon" href="{{ asset('img/client-favicon.ico') }}?v={{ $clientIco }}" sizes="16x16 32x32 48x48">
@endif

{{-- iOS: NO hay alternativa vectorial, así que este `<link>` sale siempre. Con paquete lleva el
     icono del cliente; sin él, el del producto. --}}
@if ($clientApple)
    <link rel="apple-touch-icon" href="{{ asset('img/client-apple-touch-icon.png') }}?v={{ $clientApple }}">
@else
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
@endif

{{-- Android sin manifiesto: Chrome elige para «añadir a pantalla de inicio» el icono declarado más
     grande, así que estos dos son los que sacan la «J» del producto de la pantalla de un móvil. --}}
@if ($clientPng192)
    <link rel="icon" href="{{ asset('img/client-icon-192.png') }}?v={{ $clientPng192 }}" sizes="192x192" type="image/png">
@endif
@if ($clientPng512)
    <link rel="icon" href="{{ asset('img/client-icon-512.png') }}?v={{ $clientPng512 }}" sizes="512x512" type="image/png">
@endif
