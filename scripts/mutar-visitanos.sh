#!/usr/bin/env bash
# Arnés de mutación de la SECCIÓN 07 «Visítanos» (`DECISIONES #487`, carril de diseño Fase 2 · T2g,
# `specs/rediseno-desde-canvas.md` §5.4).
#
# Las propiedades que protege, en una línea cada una:
#   · los CUATRO estados —«ya hemos cerrado» y «hoy cerrado» son hechos distintos—;
#   · el día se resalta SOLO mientras su horario está vigente;
#   · la dirección vive SIEMPRE fuera del marco, con mapa y sin mapa;
#   · cero enlaces y cero botones con el mapa cargado, y la puerta a Maps solo con el mapa bloqueado;
#   · la entradilla se DERIVA del horario y no afirma un horario que otra instalación no tiene;
#   · sin horario no se inventa ni el estado ni la tabla.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por CÓDIGO DE SALIDA (nunca
# `grep passed`) · comprobar que la mutación SE APLICÓ · restaurar por COPIA en RUTA FIJA que se
# repara al arrancar (`#448`: un `trap … EXIT` no corre con SIGKILL y deja el árbol mutado).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

FILTER='VisitSectionTest|CmsLandingFlowTest|CookieGateBlockingTest|HomePageTest'
RUN="docker compose exec -u sail -T laravel.test php artisan test --filter=${FILTER}"

TMP="storage/app/mutaciones/visitanos"
FICHEROS=(
    app/Domain/Content/Services/HeroStatus.php
    app/Domain/Content/Services/ScheduleDisplay.php
    resources/views/components/site/visit.blade.php
    resources/views/components/site/consent-frame.blade.php
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

HS=app/Domain/Content/Services/HeroStatus.php
SD=app/Domain/Content/Services/ScheduleDisplay.php
VW=resources/views/components/site/visit.blade.php
CF=resources/views/components/site/consent-frame.blade.php

echo '── Los cuatro estados ──'

# 1 · el defecto que esta tanda arregla: los dos cierres vuelven a decir lo mismo.
mutar "los dos cierres se funden en uno" "$HS" \
  "        // Hoy abría y la ventana ya pasó.
        if (\$today->isOpen) {
            return ['face' => 'closed_now', 'title' => (string) __('landing.info.state.closed_now'), 'line' => \$siguiente];
        }" \
  "        // Hoy abría y la ventana ya pasó."

# 2 · «abre hoy» pierde su cara y cae en un cierre.
mutar "«abre hoy» deja de distinguirse" "$HS" \
  "        if (\$today->isOpen && \$today->opensAt !== null && \$now->lessThan(\$now->copy()->setTimeFromTimeString(\$today->opensAt))) {" \
  '        if (false) {'

# 3 · la ventana entera: sin la hora de cierre, quien planifica no sabe si le da tiempo.
mutar "«abre hoy» deja de decir la hora de cierre" "$HS" \
  "                    'closes' => substr((string) \$today->closesAt, 0, 5)," \
  "                    'closes' => '',"

echo
echo '── El resaltado de hoy ──'

# 4 · la propiedad que más se rompe en silencio: resaltar la fila de hoy con el parque ya cerrado.
mutar "hoy se resalta aunque su horario ya haya pasado" "$VW" \
  "    \$vigente = \$hayHorario && in_array(\$heroStatus['face'] ?? '', ['open', 'later'], true);" \
  "    \$vigente = \$hayHorario;"

echo
echo '── La dirección y el mapa ──'

# 5 · la regla que cerró la sección: un bloqueador tumba el widget con las cookies aceptadas.
mutar "la dirección se mete DENTRO del marco" "$VW" \
  '        @if ($hasAddress)' \
  '        @if ($hasAddress && false)'

# 6 · la puerta a Maps con el mapa bloqueado.
mutar "el marco bloqueado pierde su salida" "$CF" \
  '                @if ($fallback)' \
  '                @if (false)'

# 7 · y su otra mitad: la salida no puede colarse con el mapa cargado.
mutar "la salida a Maps se pinta también con el mapa cargado" "$VW" \
  '            <x-slot:fallback>' \
  '            </x-site.consent-frame><a href="{{ $site[\"maps\"] }}">x</a><x-site.consent-frame category="maps" :src="$site[\"maps_embed\"]"><x-slot:fallback>'

echo
echo '── La entradilla, derivada ──'

# 8 · un texto fijo afirma para toda instalación algo que solo es cierto en una.
mutar "la entradilla pasa a ser un texto fijo" "$SD" \
  "        return match (count(\$abiertos)) {" \
  "        return (string) __('landing.info.lede_two', ['a' => 'lunes a viernes', 'b' => 'sábado a domingo']) ?: match (count(\$abiertos)) {"

# 9 · contar filas en vez de horarios: un día cerrado pasaría a contar como horario.
mutar "la entradilla cuenta filas y no horarios" "$SD" \
  "            static fn (array \$row): bool => ! \$row['is_closed']," \
  '            static fn (array $row): bool => true,'

echo
echo '── Lo que la sección NO dice ──'

# 10 · sin horario, el producto no se inventa ni el estado ni la tabla.
mutar "sin horario se pinta el estado igual" "$VW" \
  "    \$hayHorario = \$rows !== [] && ! empty(\$heroStatus);" \
  '    $hayHorario = true;'

# 11 · el pliegue con una sola fecha: 48 px para no decir nada.
mutar "el pliegue aparece con una sola fecha" "$VW" \
  '            @if (count($specials) > 1)' \
  '            @if (count($specials) > 0)'

# 12 · el aviso de la fecha próxima deja de mirar la distancia.
mutar "una fecha lejana se anuncia como si viniera" "$VW" \
  "\$s['days_away'] >= 0 && \$s['days_away'] <= 21" \
  "\$s['days_away'] >= 0"

# 13 · vuelve el teléfono, que el owner sacó de la sección.
mutar "vuelve el teléfono a la sección" "$VW" \
  '        <p class="visit__credit">' \
  '        <a href="tel:{{ $site["phone_tel"] }}">{{ $site["phone"] }}</a><p class="visit__credit">'

echo
echo "── ${muerden}/${total} mutaciones muerden ──"
[ "$muerden" -eq "$total" ] || exit 1
