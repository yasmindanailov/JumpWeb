#!/usr/bin/env bash
# Arnés de mutación de la SECCIÓN 02 · «CUÁNTO» (`specs/rediseno-desde-canvas.md` §5.4 · T2c,
# `DECISIONES #479`).
#
# Las propiedades que se protegen son cuatro, y ninguna la ve una captura:
#   1. **Todo sale del catálogo** — nombre, unidad, precio, chip y complementos.
#   2. **El nombre manda y la zona se dice UNA vez**, en el botón (`Precios PJP` 9a/10a).
#   3. **Los días se DERIVAN de `rate_types.weekdays`**, y una entrada sin tarifa especial dice
#      «solo» porque ese día **no se vende** (el dominio devuelve `null`, verificado).
#   4. **Ninguna pieza se pinta con el color de una zona** — la grieta 01 que el propio canvas nos
#      reportó— y **la pestaña no es la del cajón**, que es Fase 4.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por CÓDIGO DE SALIDA (nunca
# `grep passed`) · comprobar que la mutación SE APLICÓ · restaurar por COPIA y no con `git checkout`
# (`#181`: un `checkout` se llevó trabajo sin commitear).
#
# ❗❗ **LA COPIA VA EN RUTA FIJA Y SE REPARA AL ARRANCAR** (`#448`): el molde con `mktemp` + `trap`
# deja el árbol MUTADO si el proceso muere sin ejecutar el trap, y con la copia en un temporal que ya
# nadie encuentra. Con la copia en sitio conocido, la siguiente ejecución REPARA y lo dice.
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

FILTER='RateRailSectionTest|SpecialRateSurchargeTest|HomePageTest'
RUN="docker compose exec -u sail -T laravel.test php artisan test --filter=${FILTER}"

TMP="storage/app/mutaciones/seccion-tarifas"
FICHEROS=(
    app/Domain/Booking/Services/RateCards.php
    resources/views/components/site/rate-rail.blade.php
    resources/views/components/site/addon-pill.blade.php
    resources/views/components/site/special-rate-chips.blade.php
    public/css/landing.css
)
restaurar() {
    for f in "${FICHEROS[@]}"; do
        [ -f "$TMP/$(basename "$f")" ] || continue
        cp "$TMP/$(basename "$f")" "$f"; touch "$f"
    done
}

if [ -d "$TMP" ]; then
    sucios=0
    for f in "${FICHEROS[@]}"; do
        [ -f "$TMP/$(basename "$f")" ] || continue
        cmp -s "$f" "$TMP/$(basename "$f")" || sucios=$((sucios + 1))
    done
    if [ "$sucios" -gt 0 ]; then
        echo "⚠️  Una ejecución anterior murió sin restaurar: ${sucios} fichero(s) MUTADOS en el árbol."
        restaurar
        echo '✓ restaurados desde la copia. Comprueba con `git diff` antes de seguir.'
    fi
    rm -rf "$TMP"
fi

mkdir -p "$TMP"
trap 'restaurar; rm -rf "$TMP"' EXIT INT TERM
for f in "${FICHEROS[@]}"; do cp "$f" "$TMP/$(basename "$f")"; done

verde() { $RUN >/dev/null 2>&1; }

if ! verde; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde'
echo

muerden=0; total=0

mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4"
    total=$((total + 1))

    # Mutar un fichero SIN copia lo deja mutado para siempre y, peor, invalida todo el veredicto
    # posterior: cada mutación siguiente correría sobre código ya mutado (`#449`). Por eso aborta.
    if [ ! -f "$TMP/$(basename "$fichero")" ]; then
        echo "✗ «$nombre» quiere mutar «$fichero», que NO está en FICHEROS: sin copia no hay" >&2
        echo "  restauración, y el veredicto de toda la tanda deja de valer. Añádelo al array." >&2
        exit 1
    fi
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

CAR=app/Domain/Booking/Services/RateCards.php
VIS=resources/views/components/site/rate-rail.blade.php
PIL=resources/views/components/site/addon-pill.blade.php
CHI=resources/views/components/site/special-rate-chips.blade.php
CSS=public/css/landing.css

echo '── 1 · El nombre, su matiz y la zona ──'

mutar "el nombre CONSERVA el prefijo de la zona" "$CAR" \
  '        if ($nombreZona !== '"''"' && mb_stripos($nombre, $prefijo) === 0) {' \
  '        if (false) {'

mutar "el matiz se pinta AUNQUE repita la duración" "$CAR" \
  '        if ($matiz !== null && ($matiz === '"''"' || preg_match('"'"'/min\.?$/iu'"'"', $matiz))) {' \
  '        if (false) {'

mutar "el botón pierde la zona" "$CAR" \
  "            'cta' => __('landing.rates.book_in', ['name' => \$nombre, 'zone' => \$nombreZona])," \
  "            'cta' => \$nombre,"

mutar "el chip vuelve a colgar SOLO del badge (chip sin líder)" "$CAR" \
  "            'badge' => \$lidera ? \$badge : null," \
  "            'badge' => \$badge,"

mutar "el badge de una tarjeta que no lidera se PIERDE" "$CAR" \
  "            'nuance' => \$matiz ?? (\$lidera ? null : \$badge)," \
  "            'nuance' => \$matiz,"

mutar "el badge le gana al matiz propio del nombre" "$CAR" \
  "            'nuance' => \$matiz ?? (\$lidera ? null : \$badge)," \
  "            'nuance' => (\$lidera ? null : \$badge) ?? \$matiz,"

mutar "lideran TODAS las que el panel marque, no una" "$CAR" \
  '                    ->map(fn (TicketType $t, int $i): array => $this->card($t, $nombreZona, $diasNormales, $i === $lidera))' \
  '                    ->map(fn (TicketType $t, int $i): array => $this->card($t, $nombreZona, $diasNormales, (bool) $t->featured))'

echo
echo '── 2 · Los días, que son la mitad honesta de la sección ──'

mutar "una entrada SIN tarifa especial deja de decir «solo»" "$CAR" \
  "            'days' => \$diasNormales === null ? null : (\$especial === null" \
  "            'days' => \$diasNormales === null ? null : (false"

mutar "los días se escriben en vez de derivarse" "$CAR" \
  '        $libres = array_values(array_filter($semana, fn (int $d): bool => ! in_array($d, $tomados, true)));' \
  '        $libres = [1, 2, 3, 4];'

mutar "un conjunto NO contiguo se escribe como rango" "$CAR" \
  '        $contiguos = count($libres) - 1 === $posicion[$libres[count($libres) - 1]] - $posicion[$libres[0]];' \
  '        $contiguos = true;'

mutar "con la semana entera especial se inventa una frase" "$CAR" \
  '        if ($libres === []) {' \
  '        if (false) {'

echo
echo '── 3 · La tarifa especial: precio entero, nunca recargo ──'

mutar "vuelve el RECARGO en la sección" "$VIS" \
  '<b>{{ $card['"'"'special'"'"'] }}</b>' \
  '<b>+{{ $card['"'"'special'"'"'] }}</b>'

mutar "vuelve el RECARGO en el chip compartido (packs y /precios)" "$CHI" \
  "Money::showcase(\$sr['priceCents'])" \
  "'+'.Money::showcase(\$sr['surchargeCents'])"

mutar "los días vuelven a repetirse en cada tarjeta" "$VIS" \
  "<span>{{ __('landing.rates.special_suffix') }}</span>" \
  "<span>{{ __('landing.rates.special_suffix') }} {{ \$specialLabel }}</span>"

echo
echo '── 4 · Lo que no se puede compartir ni teñir ──'

mutar "la pestaña vuelve a ser la del CAJÓN" "$VIS" \
  'class="tabset__tab"' \
  'class="zone-tab"'

mutar "la tarjeta se tiñe con el color de la ZONA" "$CSS" \
  '.rate-card.is-focus {
  background: var(--bg-card);' \
  '.rate-card.is-focus {
  background: var(--zone-1);'

mutar "la chapa de zona VUELVE" "$VIS" \
  '            @if ($specialLabel && collect($z['"'"'cards'"'"'])->contains(fn ($c) => $c['"'"'special'"'"'] !== null))' \
  '            <div class="zone-plate"></div>
            @if ($specialLabel && collect($z['"'"'cards'"'"'])->contains(fn ($c) => $c['"'"'special'"'"'] !== null))'

echo
echo '── 5 · Los complementos ──'

mutar "la píldora afirma DOS unidades para el mismo precio" "$PIL" \
  '@if ($unit && ! ($row['"'"'perGuest'"'"'] ?? false))' \
  '@if ($unit)'

mutar "vuelve la LISTA vieja en vez de las píldoras" "$VIS" \
  '<x-site.addon-pill :row="$row" :unit="$card['"'"'unit'"'"']" />
                                @endforeach

                                @foreach ($grupos as $miembros)' \
  '<x-site.addon-chip :row="$row" />
                                @endforeach

                                @foreach ($grupos as $miembros)'

echo
echo "── ${muerden} de ${total} muerden ──"

# ── INTEGRIDAD: el árbol tiene que quedar EXACTAMENTE como estaba ────────────────────────────
sucios=0
for f in "${FICHEROS[@]}"; do cmp -s "$f" "$TMP/$(basename "$f")" || sucios=$((sucios + 1)); done
if [ "$sucios" -gt 0 ]; then
    echo "✗ ${sucios} fichero(s) NO volvieron a su estado original: revisa \`git diff\` AHORA." >&2
    exit 1
fi
echo '✓ árbol idéntico al de partida'
[ "$muerden" -eq "$total" ] || exit 1
