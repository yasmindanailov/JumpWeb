#!/usr/bin/env bash
# Arnés de mutación de EL PANEL EN SU DIRECCIÓN SECRETA (P2 de `docs/specs/panel-a-salvo.md` §4.2, `DECISIONES #850`):
# `tests/Feature/Admin/PanelSecretPathTest.php` contra cada pieza que tiene que seguir a `PANEL_PATH` —la configuración,
# `PanelPath`, Filament, las trece rutas del personal, los tres middleware que excluyen el panel por ruta y los dos
# enlaces al panel—: si una vuelve a `admin` a mano, la prueba tiene que caer.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la mutación SE APLICÓ y
# que su ancla está UNA vez · restaurar por COPIA DE SEGURIDAD (por RUTA) y `touch` (Blade compara fechas), nunca con
# `git checkout`.
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

RUN="docker compose exec -u sail -T laravel.test php artisan test --filter=PanelSecretPathTest"

CONFIG=config/panel.php
CLASE=app/Http/PanelPath.php
PANEL=app/Providers/Filament/AdminPanelProvider.php
RUTAS=routes/web.php
MANT=app/Http/Middleware/EnsureSiteAvailable.php
CLIC=app/Http/Middleware/RecordEmailClick.php
VISITA=app/Http/Middleware/ResolveVisitor.php
LAYOUT=resources/views/components/layout.blade.php
PUERTA=resources/views/livewire/admin/puerta/validar.blade.php
FICHEROS=("$CONFIG" "$CLASE" "$PANEL" "$RUTAS" "$MANT" "$CLIC" "$VISITA" "$LAYOUT" "$PUERTA")

TMP="$(mktemp -d)"
copia() { echo "$TMP/${1//\//__}"; }
restaurar() { for f in "${FICHEROS[@]}"; do cp "$(copia "$f")" "$f"; touch "$f"; done; }
for f in "${FICHEROS[@]}"; do cp "$f" "$(copia "$f")"; done
trap 'restaurar; rm -rf "$TMP"' EXIT

verde() { $RUN >/dev/null 2>&1; }

if ! verde; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde'

muerden=0; total=0

mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4"
    total=$((total + 1))
    if [[ "$(python3 -c 'import sys; print(open(sys.argv[1],encoding="utf-8").read().count(sys.argv[2]))' "$fichero" "$buscar")" != 1 ]]; then
        echo "  ⚠ «$nombre» NO APLICA: el ancla no está UNA vez en $fichero"
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

mutar "la configuración ignora PANEL_PATH" "$CONFIG" \
  "'path' => trim((string) env('PANEL_PATH', 'admin'), '/') ?: 'admin'," \
  "'path' => 'admin',"

mutar "PanelPath no lee la configuración" "$CLASE" \
  "return (string) config('panel.path', 'admin');" \
  "return 'admin';"

mutar "Filament vuelve a /admin" "$PANEL" \
  "->path(PanelPath::path())" \
  "->path('admin')"

mutar "las rutas del personal vuelven a /admin" "$RUTAS" \
  "\$panel = PanelPath::path();" \
  "\$panel = 'admin';"

mutar "el mantenimiento excluye /admin y no el panel" "$MANT" \
  "return PanelPath::matches(\$request) || \$request->is(" \
  "return \$request->is('admin', 'admin/*') || \$request->is("

mutar "los clics de los correos excluyen /admin y no el panel" "$CLIC" \
  "|| PanelPath::matches(\$request)) {" \
  "|| \$request->is('admin', 'admin/*')) {"

mutar "el visitante de la analítica excluye /admin y no el panel" "$VISITA" \
  "&& ! PanelPath::matches(\$request)) {" \
  "&& ! \$request->is('admin', 'admin/*')) {"

mutar "el aviso del mantenimiento enlaza a /admin" "$LAYOUT" \
  "\\App\\Http\\PanelPath::url('configuracion/maintenance')" \
  "url('/admin/configuracion/maintenance')"

mutar "la vuelta de la puerta va a /admin" "$PUERTA" \
  "\\App\\Http\\PanelPath::url()" \
  "url('/admin')"

echo "$muerden/$total muerden"
[[ $muerden -eq $total ]]
