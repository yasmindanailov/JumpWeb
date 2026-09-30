#!/usr/bin/env bash
# Arnés de mutación de la POLÍTICA DE COOKIES de producción (`docs/specs/politica-de-cookies.md`; el encargo del owner, 30-09).
#
# La política no puede mentir: su LISTADO lo compone la configuración (`CookieInventory`), viaja en la API y lo pintan las
# vistas; el texto nuevo llega a una BD ya sembrada solo donde es EXACTAMENTE el del producto (su huella). Cada mutación
# quita una de esas reglas y un test tiene que morir. (La guarda del navegador —lo medido está declarado— la muerde a mano
# `sonda-inventario-cookies.mjs`: su nota, en la spec.)
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la mutación SE APLICÓ ·
# restaurar por COPIA DE SEGURIDAD —por RUTA completa, nunca por nombre— y no con `git checkout` (`#181`).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

SAIL="docker compose exec -u sail -T laravel.test"
TESTS="$SAIL php artisan test --filter=CookieInventoryTest|CookiePolicyContentTest|DriversTest|PixelsTest"

INVENTORY=app/Http/Legal/CookieInventory.php
RESOURCE=app/Http/Resources/Api/V1/LegalDocumentsResource.php
MIGRATION=database/migrations/2026_09_30_150000_cookie_policy_for_production.php
VIEW=resources/views/anfitrion/legal.blade.php

TMP="$(mktemp -d)"
FICHEROS=("$INVENTORY" "$RESOURCE" "$MIGRATION" "$VIEW")
copia() { echo "$TMP/${1//\//__}"; }
restaurar() { for f in "${FICHEROS[@]}"; do cp "$(copia "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$(copia "$f")"; done

verde() { $TESTS >/dev/null 2>&1; }

if ! verde; then
    echo '✗ los tests de la política de cookies NO están verdes antes de mutar: el veredicto de abajo no valdría nada.' >&2
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

# ── El listado (`CookieInventory`) ─────────────────────────────────────────────────────────────────
mutar "el mapa sale en la política aunque no esté configurado" "$INVENTORY" \
  "if (MapsEmbed::clean(Setting::value('address.maps_embed_url')) !== null) {" \
  "if (true) {"

mutar "el anti-bot no sale aunque esté encendido" "$INVENTORY" \
  "if (Turnstile::enabled()) {" \
  "if (false) {"

mutar "los píxeles activos no salen en la política" "$INVENTORY" \
  "        foreach (Pixels::active() as \$platform) {" \
  "        foreach ([] as \$platform) {"

mutar "la herramienta de análisis activa no sale" "$INVENTORY" \
  "if ((\$tool = Drivers::config()) !== null) {" \
  "if ((\$tool = null) !== null) {"

mutar "el nombre de la sesión escrito a mano, no el que se pone" "$INVENTORY" \
  "self::row('session', (string) config('session.cookie')," \
  "self::row('session', 'laravel_session',"

mutar "la duración de la sesión escrita a mano, no la configurada" "$INVENTORY" \
  "\$session = \$minutes % 60 === 0 ? \$t('hours', ['n' => intdiv(\$minutes, 60)]) : \$t('minutes', ['n' => \$minutes]);" \
  "\$session = \$t('hours', ['n' => 2]);"

mutar "la cookie de recuerdo con un nombre que no es el suyo" "$INVENTORY" \
  "\$guard instanceof SessionGuard ? \$guard->getRecallerName() : 'remember_web'" \
  "'remember_web'"

mutar "la cookie de recuerdo se hace pasar por necesaria (#858: no está exenta)" "$INVENTORY" \
  "\$t('remember.duration', ['n' => RememberedDevice::DAYS]), \$t('category.on_request')," \
  "\$t('remember.duration', ['n' => RememberedDevice::DAYS]), \$t('category.necessary'),"

mutar "la fila de la medición cambia de clave (las landings la buscan por \`key\`)" "$INVENTORY" \
  "self::row('visitor', Visitor::COOKIE," \
  "self::row('medicion', Visitor::COOKIE,"

# ── La API y la vista ──────────────────────────────────────────────────────────────────────────────
mutar "el listado viaja en todos los documentos legales" "$RESOURCE" \
  "\$this->conCuerpo && \$pagina->slug === 'cookies' ? self::inventario() : null" \
  "\$this->conCuerpo ? self::inventario() : null"

mutar "la vista pierde la marca de la herramienta activa" "$VIEW" \
  "@if (\$herramienta !== null) data-analytics-tool=\"{{ \$herramienta }}\" @endif" \
  ""

mutar "la vista no pinta el listado" "$VIEW" \
  "            @if (\$page->slug === 'cookies')" \
  "            @if (false)"

# ── La migración (el texto nuevo, solo donde es el del producto) ───────────────────────────────────
mutar "la migración pisa el texto que editó la clienta" "$MIGRATION" \
  "            if (self::fingerprint(\$body[\$locale]) === \$huella) {" \
  "            if (true) {"

mutar "la migración calla lo que no pudo actualizar" "$MIGRATION" \
  "            Log::warning('cookies.policy_not_updated', ['locales' => \$editados, 'reason' => 'edited_in_the_panel']);" \
  ""

mutar "la huella de la v4 no es la del texto que dejaban las migraciones viejas" "$MIGRATION" \
  "'es' => 'c6f46795b47a509a2b3e42af292fe26c6d963a0bb09feb6de2951d6ed80212df'" \
  "'es' => 'c6f46795b47a509a2b3e42af292fe26c6d963a0bb09feb6de2951d6ed80212de'"

echo
echo "mutaciones que muerden: ${muerden}/${total}"
if [ "$muerden" -eq "$total" ]; then
    exit 0
fi
exit 1
