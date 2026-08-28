{{-- **EL ICONO DE PESTAÑA** — el hueco por donde entra el favicon de una instalación.

     Tercera pieza del mismo patrón que `client.css` (`DECISIONES #143`) y el logotipo (`#206`), y
     con dos de las tres parece que funciona: el fichero **no se versiona**, se carga **si existe**,
     y `deploy.sh` lo **excluye del `rsync --delete`** — sin esa exclusión el primer despliegue lo
     borra y vuelve el icono del producto, en silencio.

     ▶ **El suelo es `public/favicon.svg`, el icono DEL PRODUCTO.** ⚠️ Y hoy lleva `#FF5B22`
     quemado, que es el naranja del PRIMER cliente: la misma fuga que `DECISIONES #139` cerró en el
     CSS, sobrevivida dentro de un fichero que ninguna guarda de color mira. Ficha en `DEUDA.md`.

     ⚠️⚠️ **Solo se sustituye el SVG, y eso tiene una consecuencia que hay que saber**
     (`[DECIDIDO owner, 2026-08-28]`): los navegadores modernos usan el SVG, pero **iOS y el
     «añadir a pantalla de inicio» de Android usan los PNG**, que siguen siendo los del producto.
     Es el alcance elegido a propósito; ampliarlo es añadir dos ficheros más al mismo patrón.
     ▶ Por eso, cuando la instalación trae su SVG, **el PNG de 64 px se retira del `<head>`**: un
     navegador que entienda los dos elegiría el PNG por ser más específico en tamaño, y volvería a
     enseñar la «J» del producto teniendo el icono del cliente al lado. El `apple-touch-icon` se
     queda porque no hay alternativa vectorial para iOS.

     ⚠️ `@filemtime` hace las DOS cosas en una sola llamada a disco —existencia y cache-busting—:
     devuelve `false` si no está. Mismo recurso que `client.css` y que el logotipo. --}}

@php($clientIcon = @filemtime(public_path('img/client-favicon.svg')))

@if ($clientIcon)
    <link rel="icon" href="{{ asset('img/client-favicon.svg') }}?v={{ $clientIcon }}" type="image/svg+xml">
@else
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('favicon-64.png') }}" sizes="64x64" type="image/png">
@endif
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
