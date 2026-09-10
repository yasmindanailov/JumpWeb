#!/usr/bin/env bash
# Arnés de mutación de LA ATRIBUCIÓN DE GOOGLE en la sección 06
# (`DECISIONES #494`, `specs/google-reviews.md` §4.6, carril de diseño Fase 2).
#
# Lo que aquí se protege NO es diseño: son requisitos de la política de Places que hasta `#494` se
# incumplían, medidos contra su documentación y contra la API real del parque el 2026-09-10.
#
#   · el logotipo de Google Maps acompaña a TODO dato de Places, y es el suyo sin tocar;
#   · sobre tinta va la variante blanca y sobre papel la gris — se elige el FICHERO, no el color;
#   · el logotipo NUNCA sale sobre una opinión propia ni en la cabecera de la sección;
#   · la atribución del autor son TRES cosas: avatar, nombre y enlace al perfil;
#   · una reseña traducida lo dice, y la señal es el IDIOMA y no comparar textos;
#   · «verificado por Google» no se escribe: su documentación dice lo contrario;
#   · el umbral de reseñas se queda en 1 (`[DECIDIDO owner]`).
#
# ⚠️⚠️ La mutación más importante de este fichero es la del XML: mete dos guiones en el comentario
# del SVG y el logotipo se queda INVISIBLE con HTTP 200, marcado correcto y la caja midiendo 98×18.
# Es el defecto real que costó una vuelta encontrar, y ninguna medida de geometría lo veía.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por CÓDIGO DE SALIDA (nunca
# `grep passed`) · comprobar que la mutación SE APLICÓ · restaurar por COPIA en RUTA FIJA que se
# repara al arrancar (`#448`: un `trap … EXIT` no corre con SIGKILL y deja el árbol mutado).
# ⚠️ Sin comillas invertidas en los rótulos: dentro de comillas dobles bash las ejecuta (`#489`).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

FILTER='GoogleAttributionTest|ReviewsSectionTest|SocialProofNeverHitsTheRenderPathTest'
RUN="docker compose exec -u sail -T laravel.test php artisan test --filter=${FILTER}"

TMP="storage/app/mutaciones/atribucion-google"
FICHEROS=(
    resources/views/home.blade.php
    resources/views/components/site/google-attribution.blade.php
    public/css/landing.css
    public/images/providers/google-maps-gray.svg
    public/images/providers/google-maps-white.svg
    app/Domain/Content/Services/GoogleSocialProof.php
    app/Domain/Content/Contracts/Testimonial.php
    app/Domain/Content/Contracts/OriginalText.php
    lang/es/landing.php
)
restaurar() {
    for f in "${FICHEROS[@]}"; do
        [ -f "$TMP/$(basename "$f")" ] || continue
        cp "$TMP/$(basename "$f")" "$f"; touch "$f"
    done
}

if [ -d "$TMP" ]; then
    sucios=0
    for f in "${FICHEROS[@]}"; do
        [ -f "$TMP/$(basename "$f")" ] || continue
        cmp -s "$f" "$TMP/$(basename "$f")" || sucios=$((sucios + 1))
    done
    if [ "$sucios" -gt 0 ]; then
        echo "⚠️  Una ejecución anterior murió sin restaurar: ${sucios} fichero(s) MUTADOS en el árbol."
        restaurar
        echo '✓ restaurados desde la copia. Comprueba con git diff antes de seguir.'
    fi
    rm -rf "$TMP"
fi

mkdir -p "$TMP"
trap 'restaurar; rm -rf "$TMP"' EXIT INT TERM
for f in "${FICHEROS[@]}"; do cp "$f" "$TMP/$(basename "$f")"; done

verde() { $RUN >/dev/null 2>&1; }

if ! verde; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde'
echo

muerden=0; total=0

mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4"
    total=$((total + 1))

    if [ ! -f "$TMP/$(basename "$fichero")" ]; then
        echo "✗ «$nombre» quiere mutar «$fichero», que NO está en FICHEROS: sin copia no hay" >&2
        echo "  restauración, y el veredicto de toda la tanda deja de valer. Añádelo al array." >&2
        exit 1
    fi
    python3 -c 'import sys; p=sys.argv[1]; s=open(p,encoding="utf-8").read(); open(p,"w",encoding="utf-8").write(s.replace(sys.argv[2], sys.argv[3], 1))' \
        "$fichero" "$buscar" "$poner"
    if cmp -s "$fichero" "$TMP/$(basename "$fichero")"; then
        echo "  ⚠ «$nombre» NO SE APLICÓ (el patrón no casa): el veredicto no vale"
        return
    fi
    touch "$fichero"
    if verde; then
        echo "  ✗ NO muerde: $nombre"
    else
        echo "  ✓ muerde:    $nombre"
        muerden=$((muerden + 1))
    fi
    cp "$TMP/$(basename "$fichero")" "$fichero"; touch "$fichero"
}

HB=resources/views/home.blade.php
CMP=resources/views/components/site/google-attribution.blade.php
CSS=public/css/landing.css
SVGG=public/images/providers/google-maps-gray.svg
SVGW=public/images/providers/google-maps-white.svg
GS=app/Domain/Content/Services/GoogleSocialProof.php
DT=app/Domain/Content/Contracts/Testimonial.php
OT=app/Domain/Content/Contracts/OriginalText.php
ES=lang/es/landing.php

echo '── El asset: es el suyo, sin tocar, y se puede pintar ──'

# ⚠️⚠️ LA MUTACIÓN QUE MÁS IMPORTA. XML prohíbe dos guiones seguidos dentro de un comentario, y un
# SVG servido como image/svg+xml se parsea como XML estricto. Con esto el fichero sigue dando 200,
# el marcado sigue bien y la caja sigue midiendo 98x18: el logotipo queda INVISIBLE y no lo ve
# ninguna medición de geometría. Es el defecto real que se encontró al construir esta tanda.
mutar "el comentario del SVG rompe el XML (dos guiones seguidos)" "$SVGG" \
  'color de marca de la instalación' \
  'color de la variable --fg de la instalación'

mutar "se redibuja la geometría del logotipo" "$SVGG" \
  'M7.08,13.96c' \
  'M7.08,13.97c'

mutar "se recolorea la variante de papel con el color de la casa" "$SVGG" \
  'fill="#5E5E5E"' \
  'fill="#C6FF3A"'

mutar "la variante de tinta deja de ser blanca" "$SVGW" \
  'fill="#FFFFFF"' \
  'fill="#5E5E5E"'

# `#275`: la misma geometría en dos ficheros se desincroniza y nada falla.
mutar "una variante se actualiza y la otra no" "$SVGW" \
  'M7.08,13.96c' \
  'M7.08,14.06c'

mutar "el logotipo puede heredar el color del tema" "$SVGG" \
  'fill="#5E5E5E" d="M7.08' \
  'fill="currentColor" d="M7.08'

echo
echo '── La piel: los valores son de la GUÍA de Google, no del tema ──'

mutar "el logotipo baja del alto mínimo que publica Google" "$CSS" \
  '.gmaps-attr { display: block; flex: none; width: 98px; height: 18px; max-width: none; }' \
  '.gmaps-attr { display: block; flex: none; width: 98px; height: 12px; max-width: none; }'

mutar "el logotipo pierde el ancho fijo y puede deformarse" "$CSS" \
  '.gmaps-attr { display: block; flex: none; width: 98px; height: 18px; max-width: none; }' \
  '.gmaps-attr { display: block; flex: none; height: 18px; max-width: 100%; }'

mutar "el logotipo puede encogerse dentro de un flex" "$CSS" \
  '.gmaps-attr { display: block; flex: none; width: 98px; height: 18px; max-width: none; }' \
  '.gmaps-attr { display: block; width: 98px; height: 18px; max-width: none; }'

# Un filtro no toca el color declarado y aun así altera la marca: es la puerta de atrás.
mutar "se armoniza la marca con un filtro" "$CSS" \
  '.gmaps-attr { display: block; flex: none; width: 98px; height: 18px; max-width: none; }' \
  '.gmaps-attr { display: block; flex: none; width: 98px; height: 18px; max-width: none; filter: grayscale(1); }'

echo
echo '── Dónde sale, y sobre todo dónde NO ──'

mutar "la chapa se queda sin su atribución" "$HB" \
  '                            <x-site.google-attribution surface="ink" />' \
  '                            <span></span>'

mutar "la reseña de Google se queda sin su atribución" "$HB" \
  '                                            <x-site.google-attribution surface="paper" />' \
  '                                            <span></span>'

# ❗❗❗ EL CRUCE: chapa de Google sobre opiniones propias es el caso FRECUENTE, y ahí el logotipo
# sobre la opinión del parque es «misrepresent Google Maps by attributing it with non-Google
# Maps Platform content».
mutar "el logotipo sale también sobre una opinión propia" "$HB" \
  '                                        @if ($op->source === \App\Domain\Content\Contracts\Testimonial::SOURCE_GOOGLE)
                                            <x-site.google-attribution surface="paper" />
                                        @endif' \
  '                                            <x-site.google-attribution surface="paper" />'

# ⚠️ Sin comillas simples en el patrón: dentro de comillas simples de bash, dos seguidas NO producen
#    una comilla — cierran y abren—, así que un patrón con `__('clave')` llega sin ellas y no casa.
#    Cuatro mutaciones de la primera versión de este arnés salieron «NO SE APLICÓ» por esto.
mutar "el logotipo sube a la cabecera de la sección" "$HB" \
  '</h2>
                    {{-- ⚠️⚠️ **La entradilla sigue a la fuente de las OPINIONES' \
  '</h2><x-site.google-attribution surface="paper" />
                    {{-- ⚠️⚠️ **La entradilla sigue a la fuente de las OPINIONES'

mutar "la superficie de tinta recibe la variante de papel" "$CMP" \
  "\$surface === 'ink' ? 'images/providers/google-maps-white.svg' : 'images/providers/google-maps-gray.svg'" \
  "'images/providers/google-maps-gray.svg'"

echo
echo '── La atribución del AUTOR ──'

mutar "la reseña deja de enlazar el perfil de su autor" "$GS" \
  "'author_url' => \$this->safeUrl(\$r['authorAttribution']['uri'] ?? null)," \
  "'author_url' => null,"

mutar "la URL del perfil llega al DOM sin sanear" "$GS" \
  "'author_url' => \$this->safeUrl(\$r['authorAttribution']['uri'] ?? null)," \
  "'author_url' => \$r['authorAttribution']['uri'] ?? null,"

echo
echo '── La traducción ──'

# ⚠️⚠️ Esta mutación no borra el aviso: lo SIRVE OCULTO. La primera versión de la guarda pasaba en
#    verde porque solo comprobaba que el marcado estuviera. Que la pieza llegue no es que se vea.
mutar "el aviso de traducción se sirve oculto" "$HB" \
  '                                    <p class="rev__xlat">' \
  '                                    <p class="rev__xlat" hidden>'

mutar "una reseña traducida deja de decirlo" "$HB" \
  '                                    <p class="rev__xlat">
                                        <span class="rev__xlat-note">' \
  '                                    <p class="rev__xlat">
                                        <span class="rev__xlat-NO">'

# La señal tiene que ser el IDIOMA: Google devuelve originalText SIEMPRE, traducida o no, así que
# «hay original» no significa «está traducida».
mutar "la traducción deja de mirar el idioma y se fía de que haya original" "$GS" \
  '            && $idiomaServido !== $idiomaOriginal;' \
  '            && true;'

mutar "el original viaja sin declarar su idioma" "$HB" \
  'lang="{{ $op->originalText->language }}"' \
  'lang="es"'

mutar "un código de idioma desconocido se imprime en crudo" "$OT" \
  ' || strcasecmp($nombre, $this->language) === 0) {
            return null;' \
  ') {
            return $this->language;'

echo
echo '── Lo que NO se puede decir, y lo que sí ──'

# ❗❗❗ Lo que el owner pidió con esas palabras. Su documentación dice literalmente lo contrario:
# «Reviews aren't verified by Google».
mutar "la sección afirma que Google verifica las reseñas" "$ES" \
  "'author_on_google' => ':name en Google Maps'," \
  "'author_on_google' => ':name · Verificado por Google',"

mutar "la nota sobre la política de Google desaparece" "$HB" \
  '                @if ($cifraDeGoogle || $opinionesDeGoogle)' \
  '                @if (false)'

# Con solo chapa la nota sigue haciendo falta: la política nombra la valoración media.
mutar "la nota solo sale si hay RESEÑAS de Google, no con la chapa sola" "$HB" \
  '                @if ($cifraDeGoogle || $opinionesDeGoogle)' \
  '                @if ($opinionesDeGoogle)'

echo
echo '── Los tres retoques del owner ──'

# `[DECIDIDO owner, 2026-09-10]`: el carril se recorre con los puntos.
mutar "vuelven las flechas del carril" "$HB" \
  '                            <div class="rev__dots">' \
  '                            <button type="button" class="rev__arrow">x</button>
                            <div class="rev__dots">'

# ⚠️ Y la mitad que de verdad importa: que retirarlas no haya costado recorrido. Si los puntos
#    pierden su nombre, el carril se queda **solo alcanzable con el ratón** y nada más falla.
# ⚠️⚠️ El patrón lleva comillas simples de PHP, así que va en `$'…'` (ANSI-C quoting): dentro de
#    comillas simples normales, bash no deja escribir una comilla simple —dos seguidas cierran y
#    abren—, que es lo que dejó cuatro mutaciones en «NO SE APLICÓ» la primera vez.
mutar "sin flechas, los puntos pierden su nombre accesible" "$HB" \
  $'aria-label="{{ __(\'landing.reviews.go\', [\'n\' => $k + 1]) }}"' \
  'data-sin-nombre="1"'

mutar "«Ver en Google» pierde su alineación a la derecha" "$CSS" \
  '.rev__acts > .rev__more { margin-left: auto; }' \
  '.rev__acts > .rev__more { margin-left: 0; }'

echo
echo '── El umbral ──'

mutar "el umbral vuelve a subir y apaga el widget de Google" "$GS" \
  '    public const MIN_REVIEWS = 1;' \
  '    public const MIN_REVIEWS = 10;'

echo
echo "── Veredicto: ${muerden}/${total} mutaciones mordidas ──"
[ "$muerden" -eq "$total" ] || exit 1
