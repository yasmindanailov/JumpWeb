{{-- **EL CTA DOBLE DE MÓVIL** (`docs/specs/armazon-y-menu.md` §4.9 y §13, armazón · tanda 2c·4).

     `[DECIDIDO owner, 2026-08-27]`: la barra de abajo deja de ser un botón y pasa a ser **el CTA
     doble del mockup** — uno expandido con su subtítulo y el otro colapsado a solo icono; pulsar
     el colapsado lo expande y colapsa al otro; pulsar el expandido **actúa**.

     ⚠️⚠️ **Y desde el 2026-08-28 (`#225`) NO es «un botón parecido al del nav»: es EL MISMO
     COMPONENTE** (`[DECIDIDO owner]`). Aquí vivía `.cta-prime`, una pieza propia que tenía que
     parecerse a `.cta-med`/`.cta-ghost` y no se parecía en nada — **medido en el navegador a
     390 px: 100 px de alto contra 54, chip de icono de 64×42 contra 30×26, y las DOS mitades
     naranjas** cuando en el nav la colapsada es un fantasma de tarjeta. No era un defecto de
     ajuste: eran dos componentes que alguien tenía que mantener sincronizados a mano, y no se
     mantuvieron. Ahora la barra **no aporta forma, solo COLOCACIÓN**: qué mitad se estira y que
     el racimo ocupe el ancho del pulgar. Todo lo demás —altura, chip, tipografía, relleno,
     intercambio e invitación— lo pone `.cta-pair`, que es el mismo que pinta el nav.

     ▶ **Consecuencia de sistema, y es deliberada**: con `.cta-prime` retirado, el rol de ACCIÓN
     (`theme.action`, `#209`) **ya no pinta esta barra**. El naranja del cliente se queda en
     `.btn`, `.cartbar` y los avisos; el CTA del armazón es tinta en las doce vistas, arriba y
     abajo. Es lo mismo que `#213` decidió para el nav, aplicado al hermano que quedaba fuera.

     ▶ **Comprar sigue siendo UN solo gesto**: arranca expandido. Registrarse cuesta dos, y eso es
     la jerarquía, no un descuido.

     ⚠️⚠️ **UN BOTÓN QUE CAMBIA DE SIGNIFICADO AL PULSARLO ES UN BOTÓN QUE SE PULSA POR ERROR**, y
     por eso el nombre accesible **dice qué hace AHORA**, no a dónde lleva: cuando está colapsado
     se llama «cambiar a…», no «reservar». Sin esto, un lector de pantalla anunciaría dos botones
     que dicen lo mismo y hacen cosas distintas — y el segundo no llevaría a ninguna parte.

     ⚠️ **Y SIN JAVASCRIPT el doble paso no existe, a propósito**: los dos son `<a href>` de verdad,
     así que sin JS cada uno navega a su destino de una sola pulsación. El `href` no es decorativo
     —`/entradas` y `/registro` son PUERTAS que sirven la home y abren el cajón en su zona—, y el
     nombre accesible que se sirve es el de ACTUAR, que es lo que hacen sin JS. Alpine lo
     sustituye por el de «cambiar» solo en el que quede colapsado.

     ⚠️ El aspecto (qué mitad es ancha) lo decide el CSS a partir de UNA clase en el contenedor.
     El JS no reparte anchos: publica el estado y nada más — misma regla que el hero (`#195`) y
     que el recorte del menú (`#201`).

     La visibilidad de la barra —aparece tras el hero, se esconde en el pie, con el cajón abierto,
     con el banner de cookies— no cambia: sigue en `Alpine.data('mobileBookBar')`. --}}
{{-- ⚠️ **Aquí se escribía `body.book-bar-visible` y se retira en `#668`** (F5 · T3). Esa clase
     existía para UNA cosa: apartar el lanzador del widget de ofertas cuando la barra aparecía en
     móvil. El widget se fue, y con él su única regla — así que la barra estaba marcando el `<body>`
     en cada cambio de visibilidad **para nadie**. *Una clase de estado sin consumidor no se nota: se
     escribe igual y no pinta nada.* --}}
<div class="book-bar" x-data="mobileBookBar"
     :class="[visible && 'book-bar--on', $store.ctaPair.mode === 'account' && 'book-bar--signup']">
    {{-- ⚠️ **El par se fue a `<x-site.cta-pair>` en `#227`**: es el mismo botón que la cabecera y
         que la primera pantalla, y ya iban dos veces que dos copias divergían.
         `place="bar"` trae la colocación (ancho de pulgar, cuál se estira) y el perfil de copy de
         la barra —que SUSTITUYE el subtítulo cuando no hay precio en vez de omitirlo, porque aquí
         sí hay sitio y un botón mudo es peor—. --}}
    <x-site.cta-pair place="bar" />
</div>
