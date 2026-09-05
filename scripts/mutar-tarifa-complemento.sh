#!/usr/bin/env bash
# Arnés de mutación de `AddonPricingDateTest` (`DECISIONES #415`).
#
# El cambio que protege es «un complemento se tarifica por el día de la VISITA, no por el de la
# COMPRA», y su modo de fallo es MUDO: con todos los complementos del catálogo a precio plano la
# diferencia vale cero euros, así que la suite entera pasaba con el defecto puesto. Aquí se devuelve
# `Carbon::today()` a cada punto, uno a uno, y se comprueba que alguien se pone rojo.
#
# Reglas del repo: CONTROL previo en verde y veredicto por CÓDIGO DE SALIDA (`#317`). Restaura
# siempre, también si se interrumpe.
set -uo pipefail
cd "$(dirname "$0")/.."

FILES=(
  app/Domain/Booking/Services/OrderCreator.php
  app/Domain/Booking/Services/CartPricer.php
  app/Domain/Booking/Services/AddonOfferReader.php
  app/Domain/Booking/Services/PostFormAddons.php
  app/Filament/Pages/CreateManualOrderPage.php
)
# La suite que juzga: las guardas del día de tarifa MÁS las del post-form y el alta manual, que son
# las otras dos superficies que el cambio toca.
RUN=(docker compose exec -u sail -T laravel.test php artisan test
     --filter='AddonPricingDate|PostFormAddons|PostFormDemandSurfaces|ManualOrder')

BK=$(mktemp -d)
for f in "${FILES[@]}"; do cp "$f" "$BK/$(basename "$f")"; done
restore() { for f in "${FILES[@]}"; do cp "$BK/$(basename "$f")" "$f"; done; rm -rf "$BK"; }
trap restore EXIT INT TERM

echo "── CONTROL · sin mutar tiene que estar VERDE"
if ! "${RUN[@]}" >/dev/null 2>&1; then
  echo "✗ CONTROL EN ROJO: el arnés no mide nada."; exit 1
fi
echo "  ✓ verde"; echo

ok=0; total=0; ciegos=()
mutar() { # nombre · fichero · perl-expr
  local nombre="$1" file="$2" expr="$3"
  total=$((total+1))
  perl -0pi -e "$expr" "$file"
  if ! git diff --quiet -- "$file"; then
    if "${RUN[@]}" >/dev/null 2>&1; then
      echo "  ✗ NO MUERDE · $nombre"; ciegos+=("$nombre")
    else
      ok=$((ok+1)); echo "  ✓ muerde   · $nombre"
    fi
  else
    echo "  ! LA MUTACIÓN NO APLICÓ · $nombre (el patrón ya no casa: revísalo)"; ciegos+=("$nombre (no aplicó)")
  fi
  restore_one "$file"
}
restore_one() { cp "$BK/$(basename "$1")" "$1"; }

echo "── MUTACIONES · cada punto vuelve al reloj de la COMPRA"

mutar "el COBRO (OrderCreator)" app/Domain/Booking/Services/OrderCreator.php \
  "s/(\\\$this->addons->resolve\\(\\\$type, \\(int\\) \\\$line\\['qty'\\], \\\$line\\['addons'\\] \\?\\? \\[\\], )Carbon::parse\\(\\\$line\\['date'\\]\\)/\${1}Carbon::today()/"

# ⚠️ Se muta la fecha que se le PASA a `resolveAddons`, no `$priceDate`. La primera versión de este
# arnés restauraba el `$type->isAddon() ? today() : $line['date']` de antes y salía «no muerde»: en
# estos casos el producto es una ENTRADA, así que esa rama no se toma y la mutación no cambiaba nada.
# *Una mutación que no altera la conducta no prueba que falte una guarda: prueba que está mal escrita.*
mutar "el PRESUPUESTO (CartPricer)" app/Domain/Booking/Services/CartPricer.php \
  "s/(resolveAddons\\(\\\$type, \\\$quantity, \\\$line\\['addons'\\], )Carbon::parse\\(\\\$priceDate\\)\\)/\${1}Carbon::today())/"

mutar "la OFERTA (AddonOfferReader)" app/Domain/Booking/Services/AddonOfferReader.php \
  "s/\\\$date !== null \\? Carbon::parse\\(\\\$date\\) : Carbon::today\\(\\)/Carbon::today()/"

mutar "el POST-FORM · lectura (PostFormAddons::viewFor)" app/Domain/Booking/Services/PostFormAddons.php \
  "s/(\\\$this->rates->priceCents\\(\\\$addon, )self::pricingDate\\(\\\$principal\\)/\${1}Carbon::today()/"

mutar "el POST-FORM · escritura (PostFormAddons::write)" app/Domain/Booking/Services/PostFormAddons.php \
  "s/(\\\$this->rates->priceCents\\(\\\$addon, )self::pricingDate\\(\\\$item\\)/\${1}Carbon::today()/"

mutar "el ALTA MANUAL · desglose del carrito" app/Filament/Pages/CreateManualOrderPage.php \
  "s/(->resolve\\(\\\$type, \\\$qty, \\\$addons, )\\\$date\\)/\${1}Carbon::today())/"

mutar "el ALTA MANUAL · importe estimado" app/Filament/Pages/CreateManualOrderPage.php \
  "s/(->resolve\\(\\\$type, \\\$qty, \\\$addons, )Carbon::parse\\(\\\$date\\)\\)/\${1}Carbon::today())/"

echo
echo "── RESULTADO: $ok de $total mutaciones muerden"
if [ "${#ciegos[@]}" -gt 0 ]; then
  echo
  echo "⚠️  PUNTOS SIN RED (dicho, no escondido):"
  for c in "${ciegos[@]}"; do echo "     · $c"; done
  echo "   Si es deuda aceptada, tiene que estar escrita en \`DEUDA.md\` con su motivo."
fi
