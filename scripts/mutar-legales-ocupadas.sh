#!/usr/bin/env bash
# Arnés de mutación de LAS LEGALES QUE UNA PÁGINA OCUPA (T6h de `specs/isla-y-landing-nueva.md` §4.23, `#844`): una página del
# paquete con `'ocupa' => 'legal'` pinta las cinco rutas legales, cada una con SU texto en el hecho `legal` (el mismo JSON que
# `GET /legal/documents/{clave}`), sin ruta propia; un texto desactivado sigue en 404; fuera de una ruta legal, el hecho es
# `null`. La guarda es `InstancePagesTest`.
#
# Reglas de la casa dentro (`/mutar`): verde antes de mutar · veredicto por código de salida · ancla ÚNICA · comprobar
# que la mutación SE APLICÓ · restaurar por COPIA DE SEGURIDAD (por ruta entera) y `touch`, nunca `git checkout`.
#
#   bash scripts/mutar-legales-ocupadas.sh        (desde el host, con el contenedor en marcha)
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

PHP="docker compose exec -u sail -T laravel.test php artisan test --filter=InstancePagesTest"

PC=app/Http/Controllers/PageController.php
PF=app/Http/Instancia/PageFacts.php
IP=app/Http/Instancia/InstancePages.php

TMP="$(mktemp -d)"
FICHEROS=("$PC" "$PF" "$IP")
copia() { echo "$TMP/$(echo "$1" | tr '/' '_')"; }
restaurar() { for f in "${FICHEROS[@]}"; do cp "$(copia "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$(copia "$f")"; done

SALIDA="$TMP/salida.txt"
verde() { $PHP >"$SALIDA" 2>&1; }

if ! verde; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde'

muerden=0; total=0

mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4"
    total=$((total + 1))
    local veces
    veces=$(python3 -c 'import sys; print(open(sys.argv[1],encoding="utf-8").read().count(sys.argv[2]))' "$fichero" "$buscar")
    if [[ "$veces" != "1" ]]; then
        echo "  ⚠ «$nombre»: el ancla aparece $veces veces (tiene que ser UNA): el veredicto no vale"
        return
    fi
    python3 -c 'import sys; p=sys.argv[1]; s=open(p,encoding="utf-8").read(); open(p,"w",encoding="utf-8").write(s.replace(sys.argv[2], sys.argv[3], 1))' \
        "$fichero" "$buscar" "$poner"
    if cmp -s "$fichero" "$(copia "$fichero")"; then
        echo "  ⚠ «$nombre» NO SE APLICÓ (el patrón no casa): el veredicto no vale"
        return
    fi
    touch "$fichero"
    if verde; then
        echo "  ✗ NO muerde: $nombre"
    else
        echo "  ✓ muerde:    $nombre"
        grep -E '⨯' "$SALIDA" | head -2 | sed 's/^/                 /'
        muerden=$((muerden + 1))
    fi
    cp "$(copia "$fichero")" "$fichero"; touch "$fichero"
}

mutar "las legales no preguntan quién las ocupa" "$PC" \
  "if ((\$pagina = app(InstancePages::class)->queOcupa('legal')) !== null) {" \
  "if (false) {"
mutar "la ruta no le pasa su clave al hecho (sin texto)" "$PC" \
  "route('legal.'.\$slug), contexto: ['legal' => \$slug]);" \
  "route('legal.'.\$slug));"
mutar "la página se pinta con otra URL que la de su ruta" "$PC" \
  "InstancePageController::pintar(\$pagina, app(PageFacts::class), route('legal.'.\$slug)," \
  "InstancePageController::pintar(\$pagina, app(PageFacts::class), route('home'),"
mutar "un texto desactivado se pinta" "$PC" \
  "\$page = Page::where('slug', \$slug)->where('is_active', true)->firstOrFail();" \
  "\$page = Page::where('slug', \$slug)->firstOrFail();"
mutar "el hecho trae siempre el mismo texto, no el de su ruta" "$PF" \
  "\$pedir(['clave' => \$contexto['legal']])" \
  "\$pedir(['clave' => 'privacidad'])"
mutar "fuera de una ruta legal el hecho trae un texto" "$PF" \
  "isset(\$contexto['legal']) ? \$pedir(['clave' => \$contexto['legal']]) : null" \
  "\$pedir(['clave' => \$contexto['legal'] ?? 'privacidad'])"
mutar "legal no está entre lo que se puede ocupar" "$IP" \
  "public const OCUPABLES = ['home', 'cumpleanos', 'normas', 'legal'];" \
  "public const OCUPABLES = ['home', 'cumpleanos', 'normas'];"

echo
echo "$muerden de $total mutantes muertos"
[[ "$muerden" == "$total" ]]
