#!/usr/bin/env bash
# Arnés de mutación de la T6e·1 (`specs/isla-y-landing-nueva.md` §4.21, `DECISIONES #842`): las normas del PANEL con su
# icono y su nivel (`VenueRule::ICONS`/`LEVELS`, `GET /rules`), `/normas` que una página del paquete puede OCUPAR y el
# descargo como hecho de página (`waiver`). Cada mutación rompe UN mecanismo; la guarda es la suite de esas piezas.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la mutación SE APLICÓ ·
# restaurar por COPIA DE SEGURIDAD (por RUTA, nunca por `basename`) y `touch`, nunca con `git checkout`.
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

RUN="docker compose exec -u sail -T laravel.test php artisan test --filter=RulesFactsTest|ParkRuleResourceTest|InstancePagesTest"

MODELO=app/Domain/Content/Models/VenueRule.php
PAGINAS=app/Http/Instancia/InstancePages.php
NORMAS=app/Http/Controllers/PageController.php
HECHOS=app/Http/Instancia/PageFacts.php
FORM=app/Filament/Resources/ParkRules/Schemas/ParkRuleForm.php
FICHEROS=("$MODELO" "$PAGINAS" "$NORMAS" "$HECHOS" "$FORM")

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
    if [[ "$(grep -cF -- "$buscar" "$fichero")" != 1 ]]; then
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

# ── El icono y el nivel: solo los de las listas del producto ──────────────────────────────────────────────────────
mutar "un icono que no es de la lista viaja igual" "$MODELO" \
  "return in_array(\$this->icon, self::ICONS, true) ? \$this->icon : null;" \
  "return \$this->icon;"

mutar "un nivel que no es de la lista viaja igual" "$MODELO" \
  "return in_array(\$this->level, self::LEVELS, true) ? \$this->level : null;" \
  "return \$this->level;"

mutar "el panel acepta cualquier nivel (sin la lista)" "$FORM" \
  "->options(fn (): array => collect(VenueRule::LEVELS)" \
  "->options(fn (): array => collect([...VenueRule::LEVELS, 'rarisimo'])"

# ── /normas, ocupable ─────────────────────────────────────────────────────────────────────────────────────────────
mutar "/normas no está entre las rutas que se ceden" "$PAGINAS" \
  "public const OCUPABLES = ['home', 'cumpleanos', 'normas'];" \
  "public const OCUPABLES = ['home', 'cumpleanos'];"

mutar "el controlador de /normas no pregunta si la ocupa una página" "$NORMAS" \
  "if ((\$pagina = \$paginas->queOcupa('normas')) !== null) {" \
  "if (false && (\$pagina = \$paginas->queOcupa('normas')) !== null) {"

# ── El descargo, un hecho de página ───────────────────────────────────────────────────────────────────────────────
mutar "el descargo no es un hecho que una página pueda pedir" "$HECHOS" \
  "'waiver' => [LegalWaiverController::class, 'show', false]," \
  ""

echo "$muerden/$total muerden"
[[ $muerden -eq $total ]]
