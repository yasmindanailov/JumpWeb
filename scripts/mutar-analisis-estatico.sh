#!/usr/bin/env bash
# Arnés de mutación del ANÁLISIS ESTÁTICO del gate (`DECISIONES #625`).
#
# Tres sujetos, y por eso tres veredictos distintos:
#  · **Larastan**: un error NUEVO en `app/` tiene que sacar `phpstan analyse` con código ≠ 0 aunque
#    haya 459 errores congelados en la línea base. Si no, la línea base se lo está comiendo todo.
#  · **ESLint** (el cajón): lo mismo con `npm run lint:js` y los 12 congelados de
#    `eslint-suppressions.json` —también en un fichero que YA tiene errores congelados de esa regla,
#    que es donde una línea base por recuento se lo podría tragar—; y un error congelado que se
#    ARREGLA sin podar también corta (el trinquete que trae la herramienta).
#  · **Las guardas del gate** (`StaticAnalysisGateTest`, `PrePushGateTest`): bajar el nivel, estrechar
#    las rutas, soltar la línea base, apagar una regla, ablandar el comando, congelar un error nuevo o
#    quitar el paso del hook tienen que poner un test en rojo. Son las maneras de apagar la red sin
#    que el análisis se queje.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la
# mutación SE APLICÓ · restaurar por COPIA DE SEGURIDAD y no con `git checkout` (`#181`).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

SAIL="docker compose exec -u sail -T laravel.test"
TESTS="$SAIL php artisan test --filter=StaticAnalysisGateTest|PrePushGateTest"
PHPSTAN="$SAIL ./vendor/bin/phpstan analyse --no-progress --memory-limit=2G"
ESLINT="$SAIL npm run lint:js"

TMP="$(mktemp -d)"
FICHEROS=(
    app/Providers/AppServiceProvider.php
    phpstan.neon
    phpstan-baseline.neon
    .githooks/pre-push
    eslint.config.js
    eslint-suppressions.json
    package.json
    resources/js/sidebar/steps/LoginForm.vue
    resources/js/sidebar/account/zones/DependentsZone.vue
    resources/js/cajon/controller.js
)
restaurar() { for f in "${FICHEROS[@]}"; do cp "$TMP/$(basename "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$TMP/$(basename "$f")"; done

verde_tests()   { $TESTS >/dev/null 2>&1; }
verde_phpstan() { $PHPSTAN >/dev/null 2>&1; }
verde_eslint()  { $ESLINT >/dev/null 2>&1; }

if ! verde_phpstan; then
    echo '✗ Larastan NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
if ! verde_eslint; then
    echo '✗ ESLint NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
if ! verde_tests; then
    echo '✗ las guardas del gate NO están verdes antes de mutar.' >&2
    exit 1
fi
echo '✓ base verde (Larastan, ESLint y guardas)'

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

LOGIN=resources/js/sidebar/steps/LoginForm.vue
DEPS=resources/js/sidebar/account/zones/DependentsZone.vue

# ── ESLint ve un error NUEVO aunque haya 12 congelados ─────────────────────────────────────────
mutar verde_eslint "una variable sin definir en un fichero del cajón" "$LOGIN" \
  "<script setup>" \
  "<script setup>
variableQueNoExisteEnNingunSitio();"

mutar verde_eslint "un TERCER \`no-undef\` en el fichero que ya tiene dos congelados" "$DEPS" \
  "function toggleAdd() {" \
  "function toggleAdd() { otraQueNoExiste.value;"

mutar verde_eslint "un error congelado se arregla SIN podar la línea base (el trinquete de la herramienta)" "$LOGIN" \
  "import { computed, ref } from 'vue';" \
  "import { computed } from 'vue';"

mutar verde_eslint "una constante usada ANTES de declararse en el mismo ámbito (\`no-use-before-define\`)" "$LOGIN" \
  "<script setup>" \
  "<script setup>
console.log(usadaAntesDeExistir);
const usadaAntesDeExistir = 1;"

# ── Las maneras de apagar ESLint sin que ESLint se queje ───────────────────────────────────────
mutar verde_tests "\`no-use-before-define\` se cae de la config" eslint.config.js \
  "        rules: { 'no-use-before-define': ['error', { functions: false, classes: true, variables: false }] },
" \
  ""

mutar verde_tests "una regla se apaga en la config" eslint.config.js \
  "    ...vue.configs['flat/essential']," \
  "    ...vue.configs['flat/essential'],
    { rules: { 'no-undef': 'off' } },"

mutar verde_tests "una carpeta del cajón se ignora" eslint.config.js \
  "    js.configs.recommended," \
  "    { ignores: ['resources/js/sidebar/account/**'] },
    js.configs.recommended,"

mutar verde_tests "las reglas de Vue desaparecen (ESLint a secas no entiende una plantilla)" eslint.config.js \
  "    ...vue.configs['flat/essential'],
" \
  ""

mutar verde_tests "el comando del script se ablanda (|| true)" package.json \
  "\"lint:js\": \"eslint resources/js/sidebar resources/js/cajon\"" \
  "\"lint:js\": \"eslint resources/js/sidebar resources/js/cajon || true\""

mutar verde_tests "el alcance se estrecha a una subcarpeta" package.json \
  "\"lint:js\": \"eslint resources/js/sidebar resources/js/cajon\"" \
  "\"lint:js\": \"eslint resources/js/sidebar/steps\""

mutar verde_tests "el controlador sin framework (\`cajon/\`, F4 · T2) se cae del alcance" package.json \
  "\"lint:js\": \"eslint resources/js/sidebar resources/js/cajon\"" \
  "\"lint:js\": \"eslint resources/js/sidebar\""

mutar verde_eslint "una variable sin definir en el controlador del cajón" resources/js/cajon/controller.js \
  "        open() {" \
  "        open() {
            variableQueNoExisteEnElControlador();"

mutar verde_tests "un error nuevo se CONGELA en la línea base del cajón" eslint-suppressions.json \
  "\"count\": 2" \
  "\"count\": 3"

mutar verde_tests "el paso de ESLint desaparece del pre-push" .githooks/pre-push \
  "docker compose exec -u sail -T laravel.test npm run lint:js" \
  "true"

echo
echo '⚠ este arnés toca la fecha de dos `.vue` al restaurarlos: corre `npm run build:ssr` antes de la suite, o'
echo '  `SidebarDomContractTest` dará 36 fallos de «bundle SSR rancio» (medido el 2026-09-18; el pre-push lo reconstruye).'
echo "mutaciones que muerden: ${muerden}/${total}"
if [ "$muerden" -eq "$total" ]; then
    exit 0
fi
exit 1
