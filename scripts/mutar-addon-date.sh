#!/usr/bin/env bash
# Arnés de mutación de `AddonDateReconcilerTest` (`DECISIONES #417`).
#
# Lo que protege es DINERO de líneas ya vendidas, y sus modos de fallo son mudos: el libro deja de
# cerrar (el pedido pasa a «en revisión» y el cliente se queda sin desglose, `#132`) o se retira una
# línea que no había que tocar. Aquí se rompe cada regla por separado y se comprueba que alguien la ve.
#
# ⚠️ Las mutaciones se aplican con sustitución LITERAL (python), no con regex: la primera versión de
# este arnés usaba `perl -0pi -e 's/…/…/'` y **cinco de seis no llegaron a aplicarse** por el
# escapado. Salieron marcadas como «no aplicó» y no como «no muerde» —la salvaguarda hizo su trabajo—
# pero el arnés no medía nada. *Una mutación que no cambia el fichero no prueba nada sobre la guarda.*
#
# Reglas del repo: CONTROL previo en verde y veredicto por CÓDIGO DE SALIDA (`#317`).
set -uo pipefail
cd "$(dirname "$0")/.."

R=app/Domain/Booking/Services/AddonDateReconciler.php
E=app/Domain/Booking/Services/OrderItemEditor.php
RUN=(docker compose exec -u sail -T laravel.test php artisan test
     --filter='AddonDateReconciler|ExtraHour|ManageItemAddons|MixedPartySurcharge')

BK=$(mktemp -d)
cp "$R" "$BK/r.php"; cp "$E" "$BK/e.php"
restore() { cp "$BK/r.php" "$R"; cp "$BK/e.php" "$E"; }
trap 'restore; rm -rf "$BK"' EXIT INT TERM

echo "── CONTROL · sin mutar tiene que estar VERDE"
"${RUN[@]}" >/dev/null 2>&1 || { echo "✗ CONTROL EN ROJO: el arnés no mide nada."; exit 1; }
echo "  ✓ verde"; echo

ok=0; total=0; ciegos=()
mutar() { # nombre · fichero · buscar · reemplazar
  local nombre="$1" file="$2" buscar="$3" poner="$4"
  local aplicada=0
  total=$((total+1))
  BUSCAR="$buscar" PONER="$poner" FICHERO="$file" python3 -c '
import os, pathlib, sys
p = pathlib.Path(os.environ["FICHERO"]); t = p.read_text(encoding="utf-8")
b, n = os.environ["BUSCAR"], os.environ["PONER"]
if b not in t:
    sys.exit(1)
p.write_text(t.replace(b, n, 1), encoding="utf-8")
' && aplicada=1
  # ⚠️⚠️ El detector de «¿aplicó?» es el CÓDIGO DE SALIDA de la sustitución, **no `git diff`**. La
  # primera versión usaba `git diff --quiet` y daba «no aplicó» para TODO lo de este servicio: es un
  # fichero NUEVO sin commitear, y git no ve cambios en un untracked. El arnés salía ciego justo en
  # el fichero que la tanda crea — el que más falta hace mutar. *Un detector que depende del índice
  # de git no sirve para código que aún no está en él.*
  if [ "$aplicada" -eq 0 ]; then
    echo "  ! NO APLICÓ · $nombre (el texto buscado ya no está: revísalo)"; ciegos+=("$nombre (no aplicó)")
  elif "${RUN[@]}" >/dev/null 2>&1; then
    echo "  ✗ NO MUERDE · $nombre"; ciegos+=("$nombre")
  else
    ok=$((ok+1)); echo "  ✓ muerde   · $nombre"
  fi
  restore
}

echo "── MUTACIONES"

# 1 · El hecho de la re-tarificación: sin él el libro deja de cerrar (el fallo MUDO de §9.6).
mutar "la re-tarificación deja de escribir su hecho" "$R" \
  'foreach ($plan->repricings() as $change) {' \
  'foreach ([] as $change) {'

# 2 · La otra mitad de la asimetría: si la retirada escribiera hecho, restaría DOS veces.
mutar "la retirada escribe hecho (restaría dos veces)" "$R" \
  'foreach ($plan->repricings() as $change) {' \
  'foreach ($plan->changes as $change) {'

# 3 · H2 · dejar de excluir a los PORTADORES: se retirarían en cada cambio de fecha.
mutar "H2: los portadores dejan de excluirse" "$R" \
  '->reject(fn (OrderItem $child): bool => ProductAddon::isMixedPartyCarrierId((int) $child->ticket_type_id))' \
  '->reject(fn (OrderItem $child): bool => false)'

# 4 · H1 · el orden viejo: validar el aterrizaje también de las que se retiran.
mutar "H1: se valida el aterrizaje de las hijas que se retiran" "$E" \
  "fn (array \$c): bool => \$c['child_id'] === null || in_array((int) \$c['child_id'], \$surviving, true)," \
  'fn (array $c): bool => true,'

# 5 · La hija retirada conserva franja y plazas: ocuparía aforo de un día en el que ya no está.
mutar "la retirada no suelta su franja ni sus plazas" "$R" \
  "\$child->forceFill(['cancelled_at' => now(), 'slot_id' => null, 'seats' => 0])->save();" \
  "\$child->forceFill(['cancelled_at' => now()])->save();"

# 6 · Las unidades GRATIS entran en el delta: un complemento incluido movería dinero.
mutar "el delta ignora las unidades GRATIS" "$R" \
  '$chargedUnits = max(0, $quantity - $free);' \
  '$chargedUnits = $quantity;'

# 7 · La decisión deja de gobernar el movimiento: las retiradas se mudarían igual.
mutar "las hijas retiradas se mudan con el padre" "$E" \
  'if (in_array((int) $child->id, $surviving, true)) {' \
  'if (true) {'

echo
echo "── RESULTADO: $ok de $total mutaciones muerden"
if [ "${#ciegos[@]}" -gt 0 ]; then
  echo; echo "⚠️  SIN RED (dicho, no escondido):"; for c in "${ciegos[@]}"; do echo "     · $c"; done
  exit 1
fi
echo "✅ todas muerden."
