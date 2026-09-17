#!/usr/bin/env bash
# Arnés de mutación de la GUARDA 8 del despliegue: «producción despliega SOLO etiquetas»
# (`specs/producto-e-instancias.md` §4.6, `DECISIONES #613` y `#624`).
#
# Lo que protege: que en producción solo pase una etiqueta ANOTADA `vX.Y.Z`, exacta sobre HEAD y ya
# en `origin`; que staging no la pida; que en seco avise y no aborte; y que la versión quede escrita
# en el servidor y la salud la relea. `DeployScriptGateTest` lo comprueba EJECUTANDO el script en un
# repo de usar y tirar, así que cada mutante de aquí cambia un comportamiento, no un texto.
#
# ⚠️ Lo que este arnés NO muta: el ORDEN (la guarda antes de la primera conexión, la escritura tras
# el rsync). Mover un bloque no es un reemplazo de texto; lo cubre el caso de orden del test, que se
# ancla al sitio de llamada.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la
# mutación SE APLICÓ · restaurar por COPIA DE SEGURIDAD y no con `git checkout` (`#181`).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

RUN="docker compose exec -u sail -T laravel.test php artisan test --filter=DeployScriptGateTest"

TMP="$(mktemp -d)"
FICHEROS=(
    scripts/deploy.sh
)
restaurar() { for f in "${FICHEROS[@]}"; do cp "$TMP/$(basename "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$TMP/$(basename "$f")"; done

verde() { $RUN >/dev/null 2>&1; }

if ! verde; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde'

muerden=0; total=0

mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4"
    total=$((total + 1))
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

DEP=scripts/deploy.sh

# ── Qué ES una versión ─────────────────────────────────────────────────────────────────────────
mutar "una etiqueta LIGERA pasa por versión" "$DEP" \
  '        [[ "$(git cat-file -t "refs/tags/$tag" 2>/dev/null)" == "tag" ]] || continue' \
  '        true'

mutar "cualquier nombre que empiece por v pasa por versión (v1.0, v1.0.0-rc1)" "$DEP" \
  '        [[ "$tag" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]] || continue' \
  '        [[ "$tag" == v* ]] || continue'

mutar "un commit POR DELANTE de la etiqueta pasa por versión" "$DEP" \
  '    done < <(git tag --points-at HEAD --sort=-v:refname)' \
  '    done < <(git tag --sort=-v:refname)'

mutar "la etiqueta ya no necesita estar en origin" "$DEP" \
  '    elif [[ "$(git ls-remote --tags origin' \
  '    elif false && [[ "$(git ls-remote --tags origin'

# ── Cuándo aborta y a quién se le pide ─────────────────────────────────────────────────────────
mutar "un --go de producción sin versión deja de abortar" "$DEP" \
  '        [[ $GO -eq 1 ]] && die "GUARDA 8' \
  '        [[ $GO -eq 2 ]] && die "GUARDA 8'

mutar "en seco también aborta (el plan ya no se puede mirar antes de versionar)" "$DEP" \
  '        [[ $GO -eq 1 ]] && die "GUARDA 8' \
  '        die "GUARDA 8'

mutar "staging también tiene que ser una etiqueta" "$DEP" \
  'if [[ "${DEPLOY_PRODUCTION:-0}" == "1" ]]; then
    guard8=""' \
  'if true; then
    guard8=""'

# ── La versión, escrita y releída ──────────────────────────────────────────────────────────────
mutar "la versión deja de escribirse en el servidor" "$DEP" \
  "> '\$REMOTE_ROOT/storage/app/version'\"" \
  "> /dev/null\""

mutar "la salud deja de comparar la versión que dice el servidor" "$DEP" \
  '    "$([[ "$remote_version" == "$DEPLOY_VERSION" ]] && echo 0 || echo 1)"' \
  '    0'

echo
echo "mutaciones que muerden: ${muerden}/${total}"
if [ "$muerden" -eq "$total" ]; then
    exit 0
fi
exit 1
