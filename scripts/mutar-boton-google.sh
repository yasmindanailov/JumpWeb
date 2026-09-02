#!/usr/bin/env bash
# Arnés de mutación de `GoogleButtonBrandingTest` (`#345`).
#
# ⚠️⚠️ Las dos reglas que este repo ya pagó y que están construidas aquí dentro:
#   · **exige VERDE antes de mutar** — un filtro que no casa con ningún test también sale con código
#     ≠ 0, así que sin esta puerta «14 de 14 muerden» puede significar «el filtro está mal escrito»
#     (`waiver-por-reserva.md` §8.2);
#   · **veredicto por CÓDIGO DE SALIDA**, nunca buscando una cadena en la salida del runner
#     (`desglose-libro.md` §6.4.1: un arnés dio 12/12 buscando «OK (» sobre un runner que imprime
#     «Tests: N passed»).
#
# El restaurado es `git checkout` del fichero mutado, así que **el árbol tiene que estar commiteado**
# antes de correr esto (`#181`).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

FILTER='GoogleButtonBrandingTest'
RUN="docker compose exec -u sail -T laravel.test php artisan test --filter=${FILTER}"

verde() { $RUN >/dev/null 2>&1; }

if ! git diff --quiet -- public/images resources/js/sidebar/steps/GoogleButton.vue public/css/site.css; then
    echo "✗ hay cambios sin commitear en los ficheros que se van a mutar: commitea antes (#181)." >&2
    exit 1
fi

if ! verde; then
    echo "✗ la suite del filtro NO está verde antes de mutar: el veredicto de abajo no valdría nada." >&2
    exit 1
fi
echo "✓ base verde con el filtro ${FILTER}"

muerden=0; total=0

# $1 = qué defecto se introduce · $2 = fichero · $3 = python que lo aplica
mutar() {
    local nombre="$1" fichero="$2" script="$3"
    total=$((total + 1))
    python3 -c "$script" || { echo "  ⚠ la mutación «$nombre» no se pudo aplicar"; git checkout -- "$fichero"; return; }
    if verde; then
        echo "  ✗ NO muerde: $nombre"
    else
        echo "  ✓ muerde:    $nombre"
        muerden=$((muerden + 1))
    fi
    git checkout -- "$fichero"
}

A=public/images/providers/google.svg
C=resources/js/sidebar/steps/GoogleButton.vue
S=public/css/site.css

mutar "el dibujo se redibuja (una coordenada movida)" "$A" \
  "p='$A';s=open(p).read();open(p,'w').write(s.replace('M60,120 C76.2,120','M60,120 C76.3,120',1))"

mutar "la 'G' se recolorea al azul de la marca" "$A" \
  "p='$A';s=open(p).read();open(p,'w').write(s.replace('fill=\"#34A853\"','fill=\"#1AA9DE\"',1))"

mutar "la 'G' pasa a heredar color del tema (currentColor)" "$A" \
  "p='$A';s=open(p).read();open(p,'w').write(s.replace('fill=\"#4285F4\"','fill=\"currentColor\"',1))"

mutar "alguien mete un manejador dentro de la marca" "$A" \
  "p='$A';s=open(p).read();open(p,'w').write(s.replace('<svg ','<svg onload=\"x()\" ',1))"

mutar "el botón vuelve a vestirse del color de la instalación (btn--zone)" "$C" \
  "p='$C';s=open(p).read();i=s.index('<template>');open(p,'w').write(s[:i]+s[i:].replace('class=\"btn auth__google\"','class=\"btn btn--zone auth__google\"',1))"

mutar "se le pone alt='Google' (nombre accesible duplicado)" "$C" \
  "p='$C';s=open(p).read();i=s.index('<template>');open(p,'w').write(s[:i]+s[i:].replace('alt=\"\"','alt=\"Google\"',1))"

mutar "se retira la marca del botón" "$C" \
  "p='$C';s=open(p).read();i=s.index('<template>');import re;open(p,'w').write(s[:i]+re.sub(r'<img[^>]*>','',s[i:],count=1))"

mutar "el fondo se 'armoniza' con el color de acción del cliente" "$S" \
  "p='$S';s=open(p).read();open(p,'w').write(s.replace('background: #fff; color: #1f1f1f;','background: var(--action); color: var(--on-action);',1))"

mutar "el hover vuelve a heredar el color de acción" "$S" \
  "p='$S';s=open(p).read();open(p,'w').write(s.replace('.auth__google:hover { background: #f7f8f8;','.auth__google:hover { background: var(--action-hover);',1))"

echo
echo "── ${muerden}/${total} mutaciones muerden ──"
verde && echo "✓ árbol restaurado y verde" || { echo "✗ el árbol NO volvió a verde: revisa el restaurado" >&2; exit 1; }
[ "$muerden" -eq "$total" ] || exit 1
