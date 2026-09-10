@props([
    /**
     * La superficie sobre la que cae: `ink` (tinta) o `paper` (papel).
     *
     * ⚠️ **Elige el FICHERO, no un color.** Google publica una variante por fondo y sus directrices
     * prohíben recolorear la marca, así que aquí no hay ningún token ni ningún `currentColor`: hay
     * dos assets suyos y se sirve el que toca.
     */
    'surface' => 'paper',
])

{{-- ══ ATRIBUCIÓN DE GOOGLE MAPS ═══════════════════════════════════════════════════════════════
     `DECISIONES #494` · `specs/google-reviews.md` §4.6.

     ❗❗❗ **ES OBLIGATORIA, NO DECORACIÓN.** La política de Places dice, literal: *«When displaying
     Places API data without a Google Map, you must include the Google logo»*. Y aquí ese supuesto
     es el caso NORMAL: el mapa de «Visítanos» nace bloqueado hasta que se aceptan cookies de
     terceros y la chapa de la cifra se sirve sin ellas (`#491`), de modo que **para el visitante que
     no consiente la portada enseña dato de Places y cero mapas**.

     ❗❗❗ **VA DENTRO DEL CONTENEDOR DEL DATO QUE ACREDITA, Y NUNCA EN LA CABECERA DE LA SECCIÓN.**
     La política pide la atribución *«within the same visual container»*, y aquí hay una razón más
     fuerte: **la chapa y las opiniones no vienen de la misma fuente**, y el caso frecuente es
     justamente el cruce —chapa de Google sobre opiniones propias (medido: así está esta instalación
     ahora mismo)—. Un logotipo arriba marcaría como de Google unas opiniones que escribió el parque,
     que es *«misrepresent Google Maps by attributing it with non-Google Maps Platform content»*.
     ▶ Es el mismo defecto que la captura cazó en `#491` con la entradilla, pero peor: aquél era una
     frase inexacta y éste es un incumplimiento de marca.

     ⚠️⚠️ **NO es la «G» de `google.svg`.** Aquélla es la marca de Google Sign-In; la atribución de
     Places pide el logotipo de **Google Maps**. Usar una por otra no rompe nada: solo incumple.

     ⚠️ **`alt` con texto y no vacío**, contra lo que hace el botón de acceso: allí el rótulo daba el
     nombre accesible y aquí el logotipo **es** toda la atribución. Sus directrices lo piden con esas
     palabras — *«Include an accessibility label with the text Google Maps»*—, así que el texto es
     suyo y no se traduce: es una marca.

     ⚠️ **`width`/`height` explícitos y iguales al `viewBox`**: mantienen la proporción que sus
     directrices exigen y reservan el hueco (sin ellos la tarjeta salta al cargar la imagen). El alto
     de 18 px cae dentro de su rango publicado, que es 16–19. --}}
<img {{ $attributes->class(['gmaps-attr']) }}
     src="{{ asset($surface === 'ink' ? 'images/providers/google-maps-white.svg' : 'images/providers/google-maps-gray.svg') }}"
     alt="Google Maps" width="98" height="18" loading="lazy" decoding="async">
