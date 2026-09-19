#!/usr/bin/env bash
# Arnés de mutación de `SEC-12` — la ruta de vistas de una INSTANCIA
# (`docs/specs/paquete-de-instancia.md` §4.2 y §6, `DECISIONES #647`).
#
# El modo de fallo de esta pieza no da un error: da una web que funciona y un servidor desde el que se puede
# ejecutar código ajeno. Blade compila a PHP, así que registrar un directorio de vistas es registrar un
# directorio de código: si la ruta pudiera venir de una petición, `?paquete=…` sería ejecución remota; si
# pudiera vivir bajo `public/`, el `.blade.php` se descargaría en crudo. Estas mutaciones quitan una a una
# las puertas que lo impiden, y una guarda tiene que morir con cada una.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la
# mutación SE APLICÓ · restaurar por COPIA DE SEGURIDAD y no con `git checkout` (`#181`).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

SAIL="docker compose exec -u sail -T laravel.test"
TESTS="$SAIL php artisan test --filter='InstanceViewPathTest|InstanceViewContractTest'"

VISTAS=app/Http/Instancia/InstanceViews.php
CONTACTO=app/Http/Controllers/ContactController.php

TMP="$(mktemp -d)"
FICHEROS=("$VISTAS" "$CONTACTO")
restaurar() { for f in "${FICHEROS[@]}"; do cp "$TMP/$(basename "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$TMP/$(basename "$f")"; done

verde() { eval "$TESTS" >/dev/null 2>&1; }

if ! verde; then
    echo '✗ las guardas de SEC-12 NO están verdes antes de mutar: el veredicto de abajo no valdría nada.' >&2
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

# ── Las tres puertas del invariante ────────────────────────────────────────────────────────────
mutar "la ruta deja de exigirse ABSOLUTA (y pasa a depender del cwd de quien renderice)" \
  "$VISTAS" "        if (! str_starts_with(\$ruta, DIRECTORY_SEPARATOR)) {" \
  "        if (false) {"

mutar "se acepta un paquete DENTRO del árbol del producto (crudo bajo public, o borrado por el despliegue)" \
  "$VISTAS" "        if (self::dentroDelProducto(\$real)) {" \
  "        if (false) {"

mutar "el prefijo del árbol se compara SIN separador (y \`html-instancia\` se confunde con \`html\`)" \
  "$VISTAS" "        return \$real === \$arbol
            || str_starts_with(\$real.DIRECTORY_SEPARATOR, \$arbol.DIRECTORY_SEPARATOR);" \
  "        return str_starts_with(\$real, \$arbol);"

# ── Y la elección de vista, que es lo que hace útil al namespace ───────────────────────────────
mutar "se sirve SIEMPRE la vista de la instancia, exista o no (y el respaldo del producto muere)" \
  "$VISTAS" "        return \$this->vistas->exists(\$nombre) ? \$nombre : \$respaldo;" \
  "        return \$nombre;"

# ── El contrato: avisa, no tumba ───────────────────────────────────────────────────────────────
mutar "un contrato viejo DEJA LA WEB EN BLANCO en vez de avisar (una landing menos por un número)" \
  "$VISTAS" "            Log::warning('instancia: el paquete está hecho para otra versión del producto', [
                'paquete' => (int) \$manifiesto['contrato'],
                'producto' => self::CONTRATO,
            ]);" \
  "            throw new \RuntimeException('contrato de instancia incompatible');"

# ── El CONTRATO DE VISTA: lo que el producto promete a la landing de cada instancia ────────────
# El modo de fallo es el más silencioso de todos: aquí no se rompe nada y las N instalaciones se rompen a
# la vez, cada una en su servidor.
mutar "el controlador DEJA DE PASAR una variable que el contrato promete (todas las landings a la vez)" \
  "$CONTACTO" "            'topics' => self::TOPICS," ""

mutar "el controlador pasa una variable de MÁS sin declararla (contrato tácito otra vez)" \
  "$CONTACTO" "            'topics' => self::TOPICS," \
  "            'topics' => self::TOPICS,
            'sinDeclarar' => 1,"

echo
echo "mutaciones: $muerden/$total muerden"
[ "$muerden" -eq "$total" ]
