#!/usr/bin/env bash
# Arnés de mutación de LA PORTADA DECLARADA (T6a de `specs/isla-y-landing-nueva.md` §4.17): una página del paquete marcada
# `'portada' => true` la pintan `/` y sus puertas (`HomeController`), nunca su slug; una sola; la vuelta del banco se
# consume igual; y las puertas de entrar, sin indexar. Y desde `#832` (T6b, §4.18), cualquier RUTA DEL PRODUCTO que ceda
# su sitio (`'ocupa' => 'cumpleanos'`, `EventsController`): solo las de la lista, una página por ruta.
#
# Reglas de la casa dentro (`/mutar`): verde antes de mutar · veredicto por código de salida · ancla ÚNICA · comprobar
# que la mutación SE APLICÓ · restaurar por COPIA DE SEGURIDAD (por ruta entera) y `touch`, nunca `git checkout`.
#
#   bash scripts/mutar-portada-declarada.sh        (desde el host, con el contenedor en marcha)
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

PHP="docker compose exec -u sail -T laravel.test php artisan test --filter=InstancePagesTest"

TMP="$(mktemp -d)"
FICHEROS=(
    app/Http/Controllers/HomeController.php
    app/Http/Controllers/EventsController.php
    app/Http/Controllers/InstancePageController.php
    app/Http/Instancia/InstancePages.php
)
copia() { echo "$TMP/$(echo "$1" | tr '/' '_')"; }
restaurar() { for f in "${FICHEROS[@]}"; do cp "$(copia "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$(copia "$f")"; done

verde() { $PHP >/dev/null 2>&1; }

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
        muerden=$((muerden + 1))
    fi
    cp "$(copia "$fichero")" "$fichero"; touch "$fichero"
}

HC=app/Http/Controllers/HomeController.php
EC=app/Http/Controllers/EventsController.php
PC=app/Http/Controllers/InstancePageController.php
IP=app/Http/Instancia/InstancePages.php

mutar "/ no pinta la portada declarada" "$HC" "if ((\$portada = \$this->paginas->portada()) !== null) {" "if (false) {"
mutar "las puertas de entrar se indexan" "$HC" "route('home'), AccountDoor::isAuthDoor());" "route('home'), false);"
mutar "la portada se pinta ANTES de consumir la vuelta del banco" "$HC" "        \$this->maybeConsumeRedsysReturn(\$request);

        /*" "        /*"
mutar "la página que ocupa una ruta tiene también ruta propia" "$IP" "            if (\$pagina->ocupa !== null) {
                continue;
            }

            if (isset(\$ocupadas" "            if (isset(\$ocupadas"
mutar "dos páginas en la misma ruta" "$IP" "if (\$pagina->ocupa !== null && isset(\$ocupadas[\$pagina->ocupa])) {" "if (false) {"
mutar "cualquier cosa vale como marca" "$IP" "! is_bool(\$declarada['portada'] ?? false) => 'la marca de portada no es verdadero o falso'," ""
mutar "la vista no recibe su URL de portada" "$HC" "InstancePageController::pintar(\$portada, \$hechos, route('home')," "InstancePageController::pintar(\$portada, \$hechos, route(\$portada->ruta())"
# `#832`: la ruta que ocupa.
mutar "/cumpleanos no pinta la página que la ocupa" "$EC" "if ((\$pagina = \$this->paginas->queOcupa('cumpleanos')) !== null) {" "if (false) {"
mutar "cualquier ruta vale" "$IP" "isset(\$declarada['ocupa']) && ! in_array(\$declarada['ocupa'], self::OCUPABLES, true) => 'la ruta que ocupa no es una que el producto ceda'," ""
mutar "la portada puede ocupar otra ruta" "$IP" "(\$declarada['portada'] ?? false) === true && isset(\$declarada['ocupa']) && \$declarada['ocupa'] !== 'home' => 'es la portada y ocupa otra ruta'," ""
mutar "la marca de portada ya no es el caso home" "$IP" "ocupa: (\$declarada['portada'] ?? false) ? 'home' : (\$declarada['ocupa'] ?? null)," "ocupa: \$declarada['ocupa'] ?? null,"
mutar "queOcupa da cualquier página que ocupe algo" "$IP" "if (\$pagina->ocupa === \$ruta) {" "if (\$pagina->ocupa !== null) {"

echo
echo "$muerden de $total mutantes muertos"
[[ "$muerden" == "$total" ]]
