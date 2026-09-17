#!/usr/bin/env bash
# Arnés de mutación del ANÁLISIS ESTÁTICO del gate (`DECISIONES #625`).
#
# Dos sujetos, y por eso dos veredictos distintos:
#  · **Larastan**: un error NUEVO en `app/` tiene que sacar `phpstan analyse` con código ≠ 0 aunque
#    haya 459 errores congelados en la línea base. Si no, la línea base se lo está comiendo todo.
#  · **Las guardas del gate** (`StaticAnalysisGateTest`, `PrePushGateTest`): bajar el nivel, estrechar
#    las rutas, soltar la línea base, congelar un error nuevo o quitar el paso del hook tienen que
#    poner un test en rojo. Son las maneras de apagar la red sin que el análisis se queje.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la
# mutación SE APLICÓ · restaurar por COPIA DE SEGURIDAD y no con `git checkout` (`#181`).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

SAIL="docker compose exec -u sail -T laravel.test"
TESTS="$SAIL php artisan test --filter=StaticAnalysisGateTest|PrePushGateTest"
PHPSTAN="$SAIL ./vendor/bin/phpstan analyse --no-progress --memory-limit=2G"

TMP="$(mktemp -d)"
FICHEROS=(
    app/Providers/AppServiceProvider.php
    phpstan.neon
    phpstan-baseline.neon
    .githooks/pre-push
)
restaurar() { for f in "${FICHEROS[@]}"; do cp "$TMP/$(basename "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$TMP/$(basename "$f")"; done

verde_tests()   { $TESTS >/dev/null 2>&1; }
verde_phpstan() { $PHPSTAN >/dev/null 2>&1; }

if ! verde_phpstan; then
    echo '✗ Larastan NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
if ! verde_tests; then
    echo '✗ las guardas del gate NO están verdes antes de mutar.' >&2
    exit 1
fi
echo '✓ base verde (Larastan y guardas)'

muerden=0; total=0

mutar() {
    local verificador="$1" nombre="$2" fichero="$3" buscar="$4" poner="$5"
    total=$((total + 1))
    python3 -c 'import sys; p=sys.argv[1]; s=open(p,encoding="utf-8").read(); open(p,"w",encoding="utf-8").write(s.replace(sys.argv[2], sys.argv[3], 1))' \
        "$fichero" "$buscar" "$poner"
    if cmp -s "$fichero" "$TMP/$(basename "$fichero")"; then
        echo "  ⚠ «$nombre» NO SE APLICÓ (el patrón no casa): el veredicto no vale"
        return
    fi
    touch "$fichero"
    if "$verificador"; then
        echo "  ✗ NO muerde: $nombre"
    else
        echo "  ✓ muerde:    $nombre"
        muerden=$((muerden + 1))
    fi
    cp "$TMP/$(basename "$fichero")" "$fichero"; touch "$fichero"
}

ASP=app/Providers/AppServiceProvider.php

# ── Larastan ve un error NUEVO aunque haya 459 congelados ──────────────────────────────────────
mutar verde_phpstan "una llamada a un método que no existe" "$ASP" \
  "    public function boot(): void
    {" \
  "    public function boot(): void
    {
        \$this->metodoQueNoExisteEnNingunSitio();"

mutar verde_phpstan "un argumento del tipo equivocado" "$ASP" \
  "    public function boot(): void
    {" \
  "    public function boot(): void
    {
        strlen(['esto', 'no', 'es', 'una', 'cadena']);"

# ── Las maneras de apagar la red sin que el análisis se queje ──────────────────────────────────
mutar verde_tests "el nivel baja de 5 a 1 (pasa el gate mirando menos)" phpstan.neon \
  "    level: 5" \
  "    level: 1"

mutar verde_tests "las rutas se estrechan a una carpeta" phpstan.neon \
  "        - app" \
  "        - app/Http"

mutar verde_tests "la línea base deja de incluirse" phpstan.neon \
  "    - phpstan-baseline.neon
" \
  ""

mutar verde_tests "la extensión de Larastan desaparece (PHPStan a secas)" phpstan.neon \
  "    - vendor/larastan/larastan/extension.neon
" \
  ""

mutar verde_tests "un error nuevo se CONGELA en la línea base en vez de arreglarse" phpstan-baseline.neon \
  "			count: 1" \
  "			count: 2"

mutar verde_tests "el paso desaparece del pre-push" .githooks/pre-push \
  "docker compose exec -u sail -T laravel.test ./vendor/bin/phpstan analyse --no-progress --memory-limit=2G" \
  "true"

echo
echo "mutaciones que muerden: ${muerden}/${total}"
if [ "$muerden" -eq "$total" ]; then
    exit 0
fi
exit 1
