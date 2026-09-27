#!/usr/bin/env bash
# Arnés de mutación de F9, EL ZIP TERCERO (`#780`) EN LA FIESTA (`specs/fiesta-sistema-nuevo.md` §4.15): «¿Querías decir …?»
# en los correos (`sugerirCorreo` y su marcado) y el primario que llega (`data-llega`, solo el primario y sin bloquear).
#
# Reglas de la casa dentro (`/mutar`): verde antes de mutar · veredicto por código de salida · ancla ÚNICA · comprobar
# que la mutación SE APLICÓ · restaurar por COPIA DE SEGURIDAD (por ruta entera) y `touch`, nunca `git checkout`.
#
#   bash scripts/mutar-zip-tercero.sh        (desde el host, con el contenedor en marcha)
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

PHP="docker compose exec -u sail -T laravel.test php artisan test --filter=ZipTerceroTest"
JS="docker compose exec -u sail -T laravel.test node --test resources/js/fiesta/logica.test.js"

TMP="$(mktemp -d)"
FICHEROS=(
    resources/views/components/pieza/campo.blade.php
    resources/views/components/pieza/boton.blade.php
    resources/js/fiesta/logica.js
)
copia() { echo "$TMP/$(echo "$1" | tr '/' '_')"; }
restaurar() { for f in "${FICHEROS[@]}"; do cp "$(copia "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$(copia "$f")"; done

verde() { $PHP >/dev/null 2>&1 && $JS >/dev/null 2>&1; }

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
    docker compose exec -u sail -T laravel.test php artisan view:clear >/dev/null 2>&1
    if verde; then
        echo "  ✗ NO muerde: $nombre"
    else
        echo "  ✓ muerde:    $nombre"
        muerden=$((muerden + 1))
    fi
    cp "$(copia "$fichero")" "$fichero"; touch "$fichero"
}

CA=resources/views/components/pieza/campo.blade.php
BO=resources/views/components/pieza/boton.blade.php
LO=resources/js/fiesta/logica.js

# ── 1 · El marcado ───────────────────────────────────────────────────────────────────────────────
mutar "la sugerencia en cualquier campo" "$CA" "@if (\$type === 'email')@php" "@if (true)@php"
mutar "la sugerencia sin atar a su campo" "$CA" 'data-sugerencia="{{ $fid }}"' 'data-sugerencia=""'
mutar "la sugerencia visible sin JavaScript" "$CA" 'class="pz-campo__sug" hidden data-sugerencia' 'class="pz-campo__sug" data-sugerencia'
mutar "llega cualquier botón" "$BO" "(\$llega ?? \$variant === 'primary')" "(\$llega ?? true)"
mutar "llega bloqueado" "$BO" "\$llegaDe = (\$llega ?? \$variant === 'primary') && ! \$bloqueado;" "\$llegaDe = (\$llega ?? \$variant === 'primary');"
mutar "no se puede apagar la llegada" "$BO" "(\$llega ?? \$variant === 'primary')" "(\$variant === 'primary')"

# ── 2 · La lógica del correo (`sugerirCorreo`, el diseño tal cual) ──────────────────────────────
mutar "sin trasposición: «gmial» ya no es «gmail»" "$LO" \
  'if (i > 1 && j > 1 && a[i - 1] === b[j - 2] && a[i - 2] === b[j - 1]) d[i][j]' \
  'if (false) d[i][j]'
mutar "se «corrige» un proveedor real bien escrito" "$LO" "return t === tld ? null : \`\${local}@\${nombre}.\${t}\`;" "return \`\${local}@\${nombre}.\${t}\`;"
mutar "una terminación real de otro país se cambia" "$LO" '(UNICA[p] || TLDS.indexOf(tld) < 0) ? t[0] : tld' 't[0]'
mutar "los cortos también se corrigen (ya → yahoo…)" "$LO" "const tope = p.length >= 6 ? 2 : p.length === 5 ? 1 : 0;" "const tope = p.length >= 6 ? 2 : 1;"
mutar "sin el punto que falta" "$LO" "distancia(dom, \`\${p}.\${t}\`) <= 1" "false"
mutar "un correo con espacios se corrige" "$LO" "at === v.length - 1 || /\\s/.test(v)" "at === v.length - 1"

echo "$muerden/$total muerden"
[[ $muerden -eq $total ]]
