#!/usr/bin/env bash
# Arnés de mutación de la T8·d y del aviso de privacidad (`#350`):
# `GoogleSignInPlacementTest` + `PrivacyNoticeIsVisibleTest`.
#
# Las dos guardas existen por el mismo motivo y conviene tenerlo delante al leer esto: **el contrato
# de árbol NO ve esta pieza**. `#345` lo midió — el manifiesto congelado de `SidebarDomContractTest`
# tiene cero ocurrencias de «google», porque sus fixtures no pasan la URL y el componente es un
# `v-if="href"` —, así que mover el botón, quitarle el separador o dejar el enlace legal del color
# del párrafo **no pone en rojo nada** si estas dos no muerden.
#
# ⚠️⚠️ Las tres reglas que este repo ya pagó y que están construidas aquí dentro:
#   · **exige VERDE antes de mutar** (`waiver-por-reserva.md` §8.2);
#   · **veredicto por CÓDIGO DE SALIDA**, no buscando una cadena en la salida del runner
#     (`desglose-libro.md` §6.4.1);
#   · **comprueba que la mutación SE APLICÓ**: un patrón que no casa deja el fichero intacto y el
#     veredicto diría «no muerde» sobre un defecto que nunca se introdujo. Le pasó al arnés hermano
#     de esta misma tanda (`mutar-alta-sin-casillas.sh`) con el `$` de una variable PHP.
#
# El restaurado es `git checkout` del fichero mutado: **el árbol tiene que estar commiteado** (`#181`).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

FILTER='GoogleSignInPlacementTest|PrivacyNoticeIsVisibleTest'
RUN="docker compose exec -u sail -T laravel.test php artisan test --filter=${FILTER}"

B=resources/js/sidebar/steps/GoogleButton.vue
L=resources/js/sidebar/steps/LoginForm.vue
R=resources/js/sidebar/steps/RegisterForm.vue
S=public/css/site.css

verde() { $RUN >/dev/null 2>&1; }

if ! git diff --quiet -- "$B" "$L" "$R" "$S"; then
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

# ── La colocación: el defecto es que alguien lo devuelva abajo ─────────────────────────────────
mutar "el botón vuelve DEBAJO del formulario (entrar)" "$L" \
  '        <GoogleButton :href="googleUrl" :label="a('"'"'register.google_cta'"'"')" :separator="a('"'"'register.or'"'"')" />
' ''
# (queda sin botón: la guarda de anclajes lo caza igual — es la mitad de «no está donde tiene que estar»)

mutar "el botón se sube ENCIMA del título (crear cuenta)" "$R" \
  '    <div class="auth">
        <div class="auth__head">' \
  '    <div class="auth">
        <GoogleButton :href="googleUrl" :label="a('"'"'register.google_cta'"'"')" :separator="a('"'"'register.or'"'"')" />
        <div class="auth__head">'

mutar "el botón pierde su separador (entrar)" "$L" \
  ':label="a('"'"'register.google_cta'"'"')" :separator="a('"'"'register.or'"'"')"' \
  ':label="a('"'"'register.google_cta'"'"')"'

# ── El separador huérfano: el modo de fallo que solo se ve en la instalación SIN Google ────────
mutar "el «o» deja de depender del botón (se pinta sin claves de Google)" "$B" \
  'v-if="href && separator"' 'v-if="separator"'

# ── El aviso de privacidad: que vuelva a ser casilla, o que su enlace se vuelva invisible ──────
mutar "la privacidad vuelve a ser CASILLA en el alta con contraseña" "$R" \
  '<p class="form__hint" v-html="a('"'"'register.privacy_notice'"'"')"></p>' \
  '<label class="check"><input type="checkbox"><span v-html="a('"'"'register.privacy_notice'"'"')"></span></label>'

mutar "el enlace legal pierde el subrayado" "$S" \
  '.form__hint a { color: var(--zone-1); text-decoration: underline; }' \
  '.form__hint a { color: var(--zone-1); }'

mutar "el enlace legal vuelve al gris del párrafo" "$S" \
  '.form__hint a { color: var(--zone-1); text-decoration: underline; }' \
  '.form__hint a { color: var(--fg-mute); text-decoration: underline; }'

mutar "el enlace legal deja de declarar color propio" "$S" \
  '.form__hint a { color: var(--zone-1); text-decoration: underline; }' \
  '.form__hint a { text-decoration: underline; }'

echo
if [ "$muerden" -eq "$total" ]; then
    echo "✓ ${muerden}/${total} mutaciones muerden"
    exit 0
fi
echo "✗ solo ${muerden}/${total} mutaciones muerden" >&2
exit 1
