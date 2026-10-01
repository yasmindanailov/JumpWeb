#!/usr/bin/env bash
# Arnés de mutación del SEO técnico (`docs/specs/seo.md` §4, S4): el `robots.txt` que anuncia el sitemap y el JSON-LD de
# negocio local con lo que Google recomienda (`geo`, `hasMap`, `priceRange`, la dirección por campos).
#
# Cada mutación quita una de esas reglas y un test tiene que morir.
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la mutación SE APLICÓ ·
# restaurar por COPIA DE SEGURIDAD —por RUTA completa, nunca por nombre— y no con `git checkout` (`#181`).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

SAIL="docker compose exec -u sail -T laravel.test"
TESTS="$SAIL php artisan test --filter=SeoTest|StructuredDataTest|VenueAddressTest|MapsEmbedTest|test_a_declared_page_serves_the_business_json_ld_with_its_price_from"

ROBOTS=app/Http/Controllers/RobotsController.php
MAPS=app/Domain/Content/Services/MapsEmbed.php
ADDRESS=app/Domain/Platform/Services/VenueAddress.php
LD=app/Domain/Content/Services/StructuredData.php
COMPONENT=resources/views/components/site/json-ld.blade.php
LAYOUT=resources/views/components/layout.blade.php
PAGINA=resources/views/components/pagina.blade.php

TMP="$(mktemp -d)"
FICHEROS=("$ROBOTS" "$MAPS" "$ADDRESS" "$LD" "$COMPONENT" "$LAYOUT" "$PAGINA")
copia() { echo "$TMP/${1//\//__}"; }
restaurar() { for f in "${FICHEROS[@]}"; do cp "$(copia "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$(copia "$f")"; done

verde() { $TESTS >/dev/null 2>&1; }

if ! verde; then
    echo '✗ los tests del SEO NO están verdes antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde'

muerden=0; total=0

# $1 nombre · $2 fichero · $3 buscar · $4 poner · $5 veces (1 por defecto; 0 = todas)
mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4" veces="${5:-1}"
    total=$((total + 1))
    python3 -c 'import sys; p=sys.argv[1]; n=int(sys.argv[4]); s=open(p,encoding="utf-8").read(); open(p,"w",encoding="utf-8").write(s.replace(sys.argv[2], sys.argv[3], n if n > 0 else -1))' \
        "$fichero" "$buscar" "$poner" "$veces"
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

# ── robots.txt ─────────────────────────────────────────────────────────────────────────────────────
mutar "el robots.txt no anuncia el sitemap" "$ROBOTS" \
  "            'Sitemap: '.route('sitemap')," \
  "            '',"

mutar "el robots.txt cierra la web entera" "$ROBOTS" \
  "            'Disallow: /api/'," \
  "            'Disallow: /',"

# ── Las coordenadas (MapsEmbed) ────────────────────────────────────────────────────────────────────
mutar "latitud y longitud cambiadas de sitio" "$MAPS" \
  "preg_match('/!2d(-?\d{1,3}\.\d{5,})/', \$url, \$lng)" \
  "preg_match('/!3d(-?\d{1,3}\.\d{5,})/', \$url, \$lng)"

mutar "coordenadas con menos de 5 decimales" "$MAPS" \
  "\.\d{5,})/'" \
  "\.\d{1,})/'" 0

mutar "coordenadas fuera de rango" "$MAPS" \
  "return abs(\$latitude) <= 90 && abs(\$longitude) <= 180" \
  "return true"

# ── La dirección por campos (VenueAddress) ────────────────────────────────────────────────────────
mutar "la provincia se pierde" "$ADDRESS" \
  "'region' => (\$m[3] ?? '') !== '' ? \$m[3] : null," \
  "'region' => null,"

mutar "se parte la segunda línea aunque no haya calle" "$ADDRESS" \
  "if (\$calle !== '' && preg_match(" \
  "if (preg_match("

# ── El JSON-LD (StructuredData y su componente) ───────────────────────────────────────────────────
mutar "sin geo" "$LD" \
  "            'geo' => self::geo(\$site)," \
  "            'geo' => null,"

mutar "sin hasMap" "$LD" \
  "            'hasMap' => self::externalUrl(\$site['maps'] ?? null)," \
  "            'hasMap' => null,"

mutar "sin priceRange" "$LD" \
  "            'priceRange' => self::priceRange(\$minPriceLabel)," \
  "            'priceRange' => null,"

mutar "el JSON-LD escribe la calle por su cuenta (con el código postal dentro)" "$LD" \
  "            'streetAddress' => \$partes['street']," \
  "            'streetAddress' => VenueAddress::written(self::clean(\$site['address1'] ?? null), self::clean(\$site['address2'] ?? null)),"

mutar "el componente no le pasa el «desde»" "$COMPONENT" \
  "businessGraph(\$site, \$desde)" \
  "businessGraph(\$site)"

mutar "<x-layout> no le pasa el «desde»" "$LAYOUT" \
  '<x-site.json-ld :site="$site" :desde="$ctaMinPriceLabel ?? null" />' \
  '<x-site.json-ld :site="$site" />'

mutar "<x-pagina> no le pasa el «desde»" "$PAGINA" \
  '<x-site.json-ld :site="$site" :desde="$ctaMinPriceLabel ?? null" />' \
  '<x-site.json-ld :site="$site" />'

echo
echo "mutaciones que muerden: ${muerden}/${total}"
if [ "$muerden" -eq "$total" ]; then
    exit 0
fi
exit 1
