#!/usr/bin/env bash
# Arnés de mutación del MENÚ DE HECHOS (F5 · T1, `docs/specs/instancia-y-landing-fuera.md` §4.1 y §6).
#
# La API pública de lectura tiene un modo de fallo que no rompe nada: que salga un dato que no debía. El JSON
# sigue siendo válido, el cliente sigue pintando y nadie se entera — y la tabla que alimenta ese JSON tiene
# `redsys_secret_key` a dos filas de `contact.email`. Estas mutaciones quitan una a una las piezas que lo
# impiden, y una guarda tiene que morir con cada una.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la
# mutación SE APLICÓ · restaurar por COPIA DE SEGURIDAD y no con `git checkout` (`#181`).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

SAIL="docker compose exec -u sail -T laravel.test"
TESTS="$SAIL php artisan test --filter='PublicFactsBoundaryTest|SiteFactsTest|ApiContractTest'"

LECTOR=app/Domain/Platform/Services/PublicFacts.php
RECURSO=app/Http/Resources/Api/V1/SiteFactsResource.php
RUTAS=routes/api.php

TMP="$(mktemp -d)"
FICHEROS=("$LECTOR" "$RECURSO" "$RUTAS")
restaurar() { for f in "${FICHEROS[@]}"; do cp "$TMP/$(basename "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$TMP/$(basename "$f")"; done

verde() { eval "$TESTS" >/dev/null 2>&1; }

if ! verde; then
    echo '✗ las guardas del menú NO están verdes antes de mutar: el veredicto de abajo no valdría nada.' >&2
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

# ── La lista blanca deja de serlo ──────────────────────────────────────────────────────────────
mutar "pedir una clave NO declarada devuelve null en vez de reventar (la lista pasa a ser decorativa)" \
  "$LECTOR" "        if (! in_array(\$clave, \$this->permitidas, true)) {" \
  "        if (false) {"

mutar "un SECRETO se puede declarar en la lista blanca (basta un copiar y pegar)" \
  "$LECTOR" "            if (self::esSecreto(\$clave)) {" "            if (false) {"

mutar "la negación de secretos deja de cubrir a Redsys por familia" \
  "$LECTOR" "        '/^redsys_/i'," "        '/^redsys_no_existe_/i',"

# ── El recurso se salta el camino ──────────────────────────────────────────────────────────────
mutar "el recurso lee la tabla a mano (\`Setting::value\`) en vez de por su lista" \
  "$RECURSO" "        \$hechos = PublicFacts::allowing(self::AJUSTES);" \
  "        \$hechos = PublicFacts::allowing(self::AJUSTES);
        \\App\\Domain\\Platform\\Models\\Setting::value('business.name');"

# ── Lo que hace ÚTIL al menú ───────────────────────────────────────────────────────────────────
mutar "un campo vacío VIAJA como cadena vacía (y cada landing tiene que volver a filtrarlo)" \
  "$LECTOR" "            if (is_string(\$valor) && trim(\$valor) !== '') {
                \$salida[\$nombre] = trim(\$valor);
            }" \
  "            \$salida[\$nombre] = is_string(\$valor) ? trim(\$valor) : '';"

mutar "un bloque vacío sale como LISTA y no como objeto (el tipo depende de si rellenaron el panel)" \
  "$RECURSO" "            'seo' => (object) \$hechos->compact([" "            'seo' => \$hechos->compact(["

mutar "el domicilio FISCAL vuelve a servirse como la dirección del parque" \
  "$RECURSO" "                'jurisdiction' => 'legal.jurisdiction',
                'fiscal_address' => 'business.address'," \
  "                'jurisdiction' => 'legal.jurisdiction',"

# ── La caché, que es media promesa del recurso ─────────────────────────────────────────────────
mutar "la respuesta deja de cachearse en público" \
  "$RUTAS" "    Route::get('/site', SiteFactsController::class)
        ->middleware('cache.headers:public;max_age=300;etag')" \
  "    Route::get('/site', SiteFactsController::class)"

echo
echo "mutaciones: $muerden/$total muerden"
[ "$muerden" -eq "$total" ]
