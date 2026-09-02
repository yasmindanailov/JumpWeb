#!/usr/bin/env bash
# Arnés de mutación de la T8·c: **las dos altas ya no aceptan nada** (`#350`,
# `specs/auth-con-google.md` §21.4.3).
#
# Lo que se persigue aquí NO es que el formulario se rompa: es que alguien **devuelva** la fila
# `terms` al alta. Eso no rompe ninguna pantalla, no lo enseña ninguna captura y **desactiva la T8·b
# entera** —la regla de gracia de `TermsAcceptance` empataría esa fila con la v1 y el checkout no
# volvería a pedir las condiciones a ninguna cuenta nueva—. Es el mismo modo de fallo que
# `GoogleButtonBrandingTest` vigila: el defecto que entra disfrazado de arreglo.
#
# ⚠️⚠️ Las dos reglas que este repo ya pagó y que están construidas aquí dentro:
#   · **exige VERDE antes de mutar** — un filtro que no casa con ningún test también sale con código
#     ≠ 0, así que sin esta puerta «N de N muerden» puede significar «el filtro está mal escrito»
#     (`waiver-por-reserva.md` §8.2);
#   · **veredicto por CÓDIGO DE SALIDA**, nunca buscando una cadena en la salida del runner
#     (`desglose-libro.md` §6.4.1).
#
# El restaurado es `git checkout` del fichero mutado, así que **el árbol tiene que estar commiteado**
# antes de correr esto (`#181`).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

FILTER='AuthRegistrationTest|GoogleSignupTest|OrdersBuyerDutiesTest'
RUN="docker compose exec -u sail -T laravel.test php artisan test --filter=${FILTER}"

S=app/Domain/Identity/Services/SelfSignup.php
G=app/Domain/Identity/Services/GoogleSignup.php
T=app/Domain/Identity/Services/TermsAcceptance.php

verde() { $RUN >/dev/null 2>&1; }

if ! git diff --quiet -- "$S" "$G" "$T"; then
    echo "✗ hay cambios sin commitear en los ficheros que se van a mutar: commitea antes (#181)." >&2
    exit 1
fi

if ! verde; then
    echo "✗ la suite del filtro NO está verde antes de mutar: el veredicto de abajo no valdría nada." >&2
    exit 1
fi
echo "✓ base verde con el filtro ${FILTER}"

muerden=0; total=0

# $1 = qué defecto se introduce · $2 = fichero · $3 = texto a buscar · $4 = con qué sustituirlo
#
# ⚠️⚠️ **Buscar y sustituir viajan por `argv`, NO interpolados dentro del python.** La primera versión
# los metía en la cadena del `-c` y el `$` de una variable PHP (`$now`) llegaba escapado a medias: el
# patrón no casaba, el fichero no cambiaba y **el veredicto habría dicho «no muerde» sobre una
# mutación que nunca se aplicó**. Lo cazó la guarda de dos líneas de abajo, que es la que convierte
# «no muerde» en «no se aplicó» — sin ella este arnés habría mentido con una cifra creíble.
mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4"
    total=$((total + 1))
    python3 -c 'import sys; p=sys.argv[1]; s=open(p,encoding="utf-8").read(); open(p,"w",encoding="utf-8").write(s.replace(sys.argv[2], sys.argv[3], 1))' \
        "$fichero" "$buscar" "$poner" \
        || { echo "  ⚠ la mutación «$nombre» no se pudo aplicar"; git checkout -- "$fichero"; return; }
    if git diff --quiet -- "$fichero"; then
        echo "  ⚠ la mutación «$nombre» NO SE APLICÓ (el patrón no casa): el veredicto no vale"
        return
    fi
    if verde; then
        echo "  ✗ NO muerde: $nombre"
    else
        echo "  ✓ muerde:    $nombre"
        muerden=$((muerden + 1))
    fi
    git checkout -- "$fichero"
}

# ── El defecto que motiva el arnés: devolver la aceptación de condiciones al alta ──────────────
mutar "el alta con contraseña vuelve a escribir la fila 'terms'" "$S" \
  "'type' => Consent::TYPE_PRIVACY," "'type' => Consent::TYPE_TERMS,"

mutar "el alta con contraseña vuelve a sellar terms_accepted_at" "$S" \
  "'privacy_accepted_at' => \$now," "'privacy_accepted_at' => \$now, 'terms_accepted_at' => \$now,"

mutar "el alta con Google vuelve a escribir la fila 'terms'" "$G" \
  "'type' => Consent::TYPE_PRIVACY," "'type' => Consent::TYPE_TERMS,"

mutar "el alta con Google vuelve a sellar terms_accepted_at" "$G" \
  "'privacy_accepted_at' => \$now," "'privacy_accepted_at' => \$now, 'terms_accepted_at' => \$now,"

# ── Y el CONTROL de la otra dirección: la regla de gracia sigue viva ───────────────────────────
# Sin esta mutación, los cuatro casos de arriba pasarían igual habiendo roto el indulto de `#348` a
# quien aceptó antes del versionado — que es justo lo que el owner decidió conservar.
mutar "la regla de gracia desaparece (a quien ya aceptó se le vuelve a pedir)" "$T" \
  "if (\$number === null && \$current === 1) {" "if (false) {"

# ── Y que el rastro de privacidad no se pueda perder de paso ───────────────────────────────────
mutar "el alta con contraseña deja de escribir la constancia de privacidad" "$S" \
  "'type' => Consent::TYPE_PRIVACY," "'type' => 'nada',"

echo
if [ "$muerden" -eq "$total" ]; then
    echo "✓ ${muerden}/${total} mutaciones muerden"
    exit 0
fi
echo "✗ solo ${muerden}/${total} mutaciones muerden" >&2
exit 1
