#!/usr/bin/env bash
# Arnés de mutación de `UsedTokenIsDeclaredTest` (`DECISIONES #414`).
#
# La guarda nació de un defecto que NINGUNA guarda veía: `border-radius: var(--r-card)` con
# `--r-card` sin declarar, o sea 0px en pantalla y la suite en verde. Aquí se comprueba que muerde —
# con el defecto REAL puesto, no con uno inventado — y que su instrumento no está roto.
#
# Reglas del repo: CONTROL previo (verde antes de mutar) y veredicto por CÓDIGO DE SALIDA, nunca por
# `grep passed` (`DECISIONES #317`). Restaura siempre, incluso si se interrumpe.
set -uo pipefail
cd "$(dirname "$0")/.."

CSS=public/css/site.css
TEST=tests/Feature/Theme/UsedTokenIsDeclaredTest.php
RUN=(docker compose exec -u sail -T laravel.test php artisan test --filter=UsedTokenIsDeclared)

BK=$(mktemp -d)
cp "$CSS" "$BK/site.css"; cp "$TEST" "$BK/test.php"
restore() { cp "$BK/site.css" "$CSS"; cp "$BK/test.php" "$TEST"; rm -rf "$BK"; }
trap restore EXIT INT TERM

echo "── CONTROL · sin mutar tiene que estar VERDE"
if ! "${RUN[@]}" >/dev/null 2>&1; then
  echo "✗ CONTROL EN ROJO: la guarda ya falla sin mutación. El arnés no mide nada."; exit 1
fi
echo "  ✓ verde"; echo

ok=0; total=0
mutar() { # nombre · comando de mutación
  local nombre="$1"; shift
  total=$((total+1))
  "$@"
  if "${RUN[@]}" >/dev/null 2>&1; then
    echo "  ✗ NO MUERDE · $nombre"
  else
    ok=$((ok+1)); echo "  ✓ muerde   · $nombre"
  fi
  cp "$BK/site.css" "$CSS"; cp "$BK/test.php" "$TEST"
}

echo "── MUTACIONES"

# 1 · EL DEFECTO REAL: el token que el owner vio cuadrado.
mutar "el defecto real: .gf-extra vuelve a var(--r-card)" \
  perl -0pi -e 's/(\.gf-extra \{[^}]*?border-radius: )var\(--r\)/${1}var(--r-card)/s' "$CSS"

# 2 · La misma clase de fallo en otra propiedad y otra pieza: un color inexistente.
mutar "un COLOR inexistente en otra pieza (var(--bg-tarjeta))" \
  perl -0pi -e 's/(\.gf-extra \{[^}]*?background: )var\(--bg-card\)/${1}var(--bg-tarjeta)/s' "$CSS"

# 3 · Instrumento: si deja de blanquear comentarios, `--jump-1`/`--jump-2` (que solo viven dentro de
#     comentarios que explican por qué ya no se usan) entran como falsos positivos.
mutar "el escáner deja de blanquear comentarios" \
  perl -0pi -e 's/(private function stripComments\(string \$css\): string\n    \{\n)        return[^;]+;/${1}        return \$css;/s' "$TEST"

# 4 · Instrumento: si confunde `var(--x, fallback)` con `var(--x)`, acusa a los 52 usos deliberados
#     de `#209` y el control de `test_the_scan_sees_the_corpus` tiene que cazarlo.
mutar "el escáner confunde el uso CON fallback con el uso sin él" \
  perl -0pi -e "s/'\/var\\\\\(\\\\s\*\(--\[A-Za-z0-9_-\]\+\)\\\\s\*\\\\\)\/'/'\/var\\\\(\\\\s*(--[A-Za-z0-9_-]+)\\\\s*[,)]\/'/" "$TEST"

# 5 · El arreglo preexistente que más se veía (`--muted` heredaba el color del padre en 8 sitios):
#     si vuelve, la guarda tiene que cazarlo como cualquier token nuevo.
mutar "vuelve el defecto preexistente --muted" \
  perl -0pi -e 's/(\.guestform__privacy \{[^}]*?color: )var\(--fg-mute\)/${1}var(--muted)/s' "$CSS"

# 6 · Una excepción SIN SUJETO en KNOWN_BROKEN no puede pasar en verde: la lista solo encoge, y un
#     caso sin sujeto no vigila nada (`#295`). Con la lista vacía, éste es el que la protege.
mutar "se cuela una excepción sin sujeto en KNOWN_BROKEN" \
  perl -0pi -e "s/private const KNOWN_BROKEN = \[\];/private const KNOWN_BROKEN = ['--token-que-nadie-usa'];/" "$TEST"

echo
echo "── RESULTADO: $ok de $total mutaciones muerden"
[ "$ok" -eq "$total" ] || { echo "✗ hay mutaciones que NO muerden: la guarda tiene puntos ciegos."; exit 1; }
echo "✅ la guarda muerde en todas."
