#!/usr/bin/env bash
# Arnés de mutación del ORDEN DE LAS SECCIONES de la portada
# (`DECISIONES #495`, carril de diseño Fase 2, `specs/rediseno-desde-canvas.md` §5.1).
#
# ❗❗ Este arnés existe porque **reordenar secciones no rompe nada**: los anclas siguen existiendo,
# `--hero-air` cuelga de `.hero + .section` y se muda solo, y las demás guardas acotan por `id`. La
# suite entera pasaba en verde con el orden viejo y pasa con el nuevo. Sin `HomeSectionOrderTest`,
# un bloque descolocado llega a producción sin que nada se ponga rojo.
#
# Lo que se protege:
#   · las ocho van en el orden del mockup, comparado como SECUENCIA y no por parejas;
#   · las ocho se PINTAN — una que desaparece también deja el resto «en orden»;
#   · la primera es `#zones` y sigue siendo `.section`, que es de lo que depende el aire del hero.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por CÓDIGO DE SALIDA (nunca
# `grep passed`) · comprobar que la mutación SE APLICÓ · restaurar por COPIA en RUTA FIJA que se
# repara al arrancar (`#448`).
# ⚠️ Sin comillas simples en los patrones: dentro de comillas simples de bash, dos seguidas cierran
#    y abren en vez de producir una comilla (la lección de `#494`).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

FILTER='HomeSectionOrderTest'
RUN="docker compose exec -u sail -T laravel.test php artisan test --filter=${FILTER}"

TMP="storage/app/mutaciones/orden-secciones"
FICHEROS=(resources/views/home.blade.php public/css/landing.css)

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
        echo "✗ «$nombre» quiere mutar «$fichero», que NO está en FICHEROS." >&2
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

# ⚠️⚠️ MUTACIÓN QUE MUEVE UN BLOQUE DE VERDAD. Reordenar con un `replace` de cadena no se puede:
#    lo que se mueve son ~180 líneas. Esta función recorta el bloque de una sección —de su cabecera
#    numerada hasta su cierre— y lo reinserta detrás de otra, que es exactamente el defecto que se
#    persigue: alguien mueve un bloque y nada se pone rojo.
mover_bloque() {
    local nombre="$1" cual="$2" detras_de="$3"
    total=$((total + 1))

    python3 - "$cual" "$detras_de" <<'PY'
import re, sys
p = 'resources/views/home.blade.php'
L = open(p, encoding='utf-8').read().split('\n')
cual, detras = sys.argv[1], sys.argv[2]

def rango(sec):
    """Desde la cabecera numerada del bloque hasta el cierre de su sección (índices 0-based)."""
    i = next(k for k, l in enumerate(L) if re.search(rf'<section id="{sec}"', l))
    # subir hasta la cabecera "══ NN ·"
    a = next(k for k in range(i, -1, -1) if '══ 0' in L[k])
    # bajar hasta `</section>` o `@endif` a 4 espacios
    b = next(k for k in range(i, len(L)) if L[k] in ('    </section>', '    @endif'))
    return a, b

a1, b1 = rango(cual)
bloque = L[a1:b1+1]
resto = L[:a1] + L[b1+1:]
a2, b2 = (lambda s: (lambda i: (i, next(k for k in range(i, len(s)) if s[k] in ('    </section>', '    @endif'))))(
    next(k for k, l in enumerate(s) if re.search(rf'<section id="{detras}"', l))))(resto)
nuevo = resto[:b2+1] + [''] + bloque + resto[b2+1:]
open(p, 'w', encoding='utf-8').write('\n'.join(nuevo))
PY

    if cmp -s resources/views/home.blade.php "$TMP/home.blade.php"; then
        echo "  ⚠ «$nombre» NO SE APLICÓ: el veredicto no vale"
        return
    fi
    touch resources/views/home.blade.php
    if verde; then
        echo "  ✗ NO muerde: $nombre"
    else
        echo "  ✓ muerde:    $nombre"
        muerden=$((muerden + 1))
    fi
    cp "$TMP/home.blade.php" resources/views/home.blade.php; touch resources/views/home.blade.php
}

HB=resources/views/home.blade.php

echo '── El orden ──'

mover_bloque "«Cumpleaños» sube al principio, delante de «Cuánto»"  events pricing
mover_bloque "«Qué hay dentro» baja detrás de «Antes de venir»"     rides-section before
mover_bloque "«Cuánto» se va al final, detrás de «Dudas»"           pricing faq

echo
echo '── Que las ocho estén, y no solo en orden ──'

# ⚠️ Una sección que desaparece deja el resto «en orden»: sin el caso de presencia, media portada
#    podría irse en verde.
mutar "«Qué hay dentro» desaparece de la portada" "$HB" \
  '    <section id="rides-section" class="section wrap">' \
  '    @if (false)<section id="rides-section" class="section wrap">'

echo
echo '── El aire bajo el hero ──'

# `--hero-air` cuelga de `.hero + .section`: si la primera deja de ser `.section`, el aire se pierde
# y NO falla nada — la portada solo queda apretada bajo el hero.
mutar "la primera sección deja de ser .section y pierde el aire del hero" "$HB" \
  '    <section id="zones" class="section wrap">' \
  '    <section id="zones" class="wrap">'

echo
echo "── Veredicto: ${muerden}/${total} mutaciones mordidas ──"
[ "$muerden" -eq "$total" ] || exit 1
