#!/usr/bin/env bash
# Arnés de mutación de LA PUERTA NUEVA, P1 (`docs/specs/puerta-nueva.md` §4.4): el veredicto (`GateVerdict`), el
# enmascarado (`QueryMask`), lo que se pinta de la ficha (`FichaPuerta`), los campos nuevos de cada fila (`GateReservation`,
# `GateReservationsReader`, `GateProfile`), el eco del componente y las decisiones que fijan la vista y la hoja.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la mutación SE APLICÓ y que
# su ancla es ÚNICA · restaurar por COPIA DE SEGURIDAD y `touch`, no con `git checkout` (`#181`) · copia por RUTA.
#
# ⚠️ Lo que NO juzga (es la sonda y el ojo): cómo se ve, el sonido, el solape de tarjetas, los 44 px de verdad. Para eso,
# `scripts/sonda-puerta-p1.mjs`.
#
#   bash scripts/mutar-puerta-p1.sh                  (todas)
#   SOLO='veredicto' bash scripts/mutar-puerta-p1.sh (las que llevan eso en su nombre)
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

EXEC="docker compose exec -u sail -T laravel.test"
PHP='Puerta|GateKioskTest|GateVerdictTest|QueryMaskTest|FichaPuertaTest|WaiverGateTest|MixedPartyParkSurfacesTest|PanelSecretPathTest'

TMP="$(mktemp -d)"
FICHEROS=(
    app/Livewire/Admin/Puerta/GateVerdict.php
    app/Livewire/Admin/Puerta/QueryMask.php
    app/Livewire/Admin/Puerta/FichaPuerta.php
    app/Livewire/Admin/Puerta/ValidarRegistro.php
    app/Domain/Booking/Services/GateReservationsReader.php
    app/Domain/Identity/Services/GateProfile.php
    resources/views/livewire/admin/puerta/validar.blade.php
    resources/css/filament/admin/puerta.css
)
copia() { echo "$TMP/${1//\//__}"; }
restaurar() { for f in "${FICHEROS[@]}"; do cp "$(copia "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp -p "$f" "$(copia "$f")"; done

verde_php() { $EXEC php artisan test --parallel --filter="$PHP" >/dev/null 2>&1; }

if ! verde_php; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde'

muerden=0; total=0

# mutar <nombre> <fichero> <buscar> <poner>
mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4"
    [[ -n "${SOLO:-}" && "$nombre" != *"$SOLO"* ]] && return
    total=$((total + 1))
    local veces
    veces=$(python3 -c 'import sys; print(open(sys.argv[1],encoding="utf-8").read().count(sys.argv[2]))' "$fichero" "$buscar")
    if [[ "$veces" != "1" ]]; then
        echo "  ⚠ «$nombre»: el ancla aparece $veces veces (tiene que ser UNA): el veredicto no vale"
        return
    fi
    python3 -c 'import sys; p=sys.argv[1]; s=open(p,encoding="utf-8").read(); open(p,"w",encoding="utf-8").write(s.replace(sys.argv[2], sys.argv[3], 1))' \
        "$fichero" "$buscar" "$poner"
    if cmp -s "$fichero" "$(copia "$fichero")"; then
        echo "  ⚠ «$nombre» NO SE APLICÓ: el veredicto no vale"
        return
    fi
    touch "$fichero"
    if verde_php; then
        echo "  ✗ NO muerde: $nombre"
    else
        echo "  ✓ muerde:    $nombre"
        muerden=$((muerden + 1))
    fi
    cp "$(copia "$fichero")" "$fichero"; touch "$fichero"
}

VEREDICTO=app/Livewire/Admin/Puerta/GateVerdict.php
MASCARA=app/Livewire/Admin/Puerta/QueryMask.php
FICHA=app/Livewire/Admin/Puerta/FichaPuerta.php
COMPONENTE=app/Livewire/Admin/Puerta/ValidarRegistro.php
LECTOR=app/Domain/Booking/Services/GateReservationsReader.php
PERFIL=app/Domain/Identity/Services/GateProfile.php
VISTA=resources/views/livewire/admin/puerta/validar.blade.php
HOJA=resources/css/filament/admin/puerta.css

# ── El veredicto ─────────────────────────────────────────────────────────────────────────────────────────────
mutar "veredicto · los menores a cargo no cuentan" "$VEREDICTO" \
  "if (is_array(\$minor) && (\$minor['waiver'] ?? null) === 'missing') {" "if (false) {"
mutar "veredicto · una versión anterior frena" "$VEREDICTO" \
  "(\$minor['waiver'] ?? null) === 'missing'" "in_array(\$minor['waiver'] ?? null, ['missing', 'outdated'], true)"
mutar "veredicto · con el descargo apagado no es verde" "$VEREDICTO" \
  "        if (! (\$waiver['enabled'] ?? false)) {
            return true;
        }
        if (! (\$waiver['signed'] ?? false)) {" "        if (! (\$waiver['signed'] ?? false)) {"
mutar "veredicto · «No encontrado» deja de ser el rojo" "$VEREDICTO" \
  "STATUS_NOT_REGISTERED => [self::ROJO," "STATUS_NOT_REGISTERED => [self::GRIS,"
mutar "veredicto · el gris suena" "$VEREDICTO" \
  "'sound' => \$notice || \$tone === self::GRIS ? null : \$tone," "'sound' => \$notice ? null : \$tone,"
mutar "veredicto · sin ficha, el semáforo pierde la fecha del descargo" "$VEREDICTO" \
  "ValidarRegistro::STATUS_REGISTERED_WITH_WAIVER => trim(" "'_' => trim("

# ── El enmascarado ───────────────────────────────────────────────────────────────────────────────────────────
mutar "máscara · el correo enseña una letra de más" "$MASCARA" "mb_substr(\$user, 0, 2)" "mb_substr(\$user, 0, 3)"
mutar "máscara · el teléfono enseña una cifra de más" "$MASCARA" "substr(\$digits, -3)" "substr(\$digits, -4)"
mutar "máscara · el eco va entero" "$COMPONENTE" \
  "\$echo = \$type === self::INPUT_EMAIL ? QueryMask::email(mb_strtolower(\$raw)) : QueryMask::phone(\$raw);" "\$echo = \$raw;"

# ── Lo que se pinta de la ficha ──────────────────────────────────────────────────────────────────────────────
mutar "ficha · un cumpleaños cuenta su dinero" "$FICHA" \
  "'dinero' => \$fiesta ? null : self::dinero(\$r)," "'dinero' => self::dinero(\$r),"
mutar "ficha · un libro que no cuadra dice «Pagado»" "$FICHA" \
  "if (\$cents <= 0 || (\$r['balance_kind'] ?? Balance::KIND_UNDER_REVIEW) === Balance::KIND_UNDER_REVIEW) {" "if (\$cents <= 0) {"
mutar "ficha · lo que se devuelve sale con su signo" "$FICHA" \
  "Money::showcaseWithSymbol(-\$cents)" "Money::showcaseWithSymbol(\$cents)"
mutar "ficha · una clase que falta se lee como pagado" "$FICHA" \
  "\$kind = (string) (\$r['balance_kind'] ?? Balance::KIND_UNDER_REVIEW);" "\$kind = (string) (\$r['balance_kind'] ?? Balance::KIND_SETTLED);"
mutar "ficha · el descargo vigente también se rotula (#320)" "$FICHA" \
  "            'outdated' => __(self::T.'descargo_antiguo'),
            default => null," "            'outdated' => __(self::T.'descargo_antiguo'),
            default => 'vigente',"
mutar "ficha · quien cumple no va el primero" "$FICHA" \
  "        usort(\$hijos, static fn (array \$a, array \$b): int => (int) \$b['cumple'] <=> (int) \$a['cumple']);
" ""
mutar "ficha · los niños de la fiesta salen con nombre (#817)" "$FICHA" \
  " || in_array(\$g['order_code'] ?? null, \$fiestas, true)" ""
mutar "ficha · «añade a tus hijos» también si puede entrar un adulto" "$FICHA" \
  "(bool) (\$r['minors_only'] ?? false) && ! (\$r['is_party'] ?? false)" "! (\$r['is_party'] ?? false)"
mutar "ficha · la fila no separa las horas" "$FICHA" \
  "(string) (\$r['zone_slug'] ?? \$zona), (string) (\$r['start_time'] ?? '')," "(string) (\$r['zone_slug'] ?? \$zona), '',"
mutar "ficha · los de solo menores no van primero" "$FICHA" \
  "'orden' => [(bool) (\$r['minors_only'] ?? false) ? 0 : 1," "'orden' => [1,"
mutar "ficha · un cumpleaños sin duración «llega cuando llegue»" "$FICHA" \
  "if ((\$r['duration_minutes'] ?? null) === null && ! (\$r['is_party'] ?? false)) {" "if ((\$r['duration_minutes'] ?? null) === null) {"
mutar "ficha · a la hora en punto dice «empieza en 0 min»" "$FICHA" \
  "            \$minutos > 0 => __(self::T.'hora_empieza'" "            \$minutos >= 0 => __(self::T.'hora_empieza'"
mutar "ficha · el otro día se cuenta aunque haya reserva hoy" "$FICHA" \
  "'otros_dias' => \$today === [] ? array_map(" "'otros_dias' => true ? array_map("
mutar "ficha · la fiesta no cuenta sus autorizaciones" "$FICHA" \
  "__(self::T.'fiesta', ['firmados' => (int) (\$c['signed'] ?? 0)," "__(self::T.'fiesta', ['firmados' => 0,"

# ── Los campos nuevos de cada fila ───────────────────────────────────────────────────────────────────────────
mutar "fila · la mayoría de edad cuenta como menor" "$PERFIL" \
  "\$r->guestAgeMax < Dependent::ADULT_AGE" "\$r->guestAgeMax <= Dependent::ADULT_AGE"
mutar "fila · la ilimitada tiene duración" "$LECTOR" \
  "\$item->ticketType === null || \$item->ticketType->isUnlimited() ? null : (int) \$item->ticketType->duration_min" "(int) \$item->ticketType?->duration_min"
mutar "fila · la hora de inicio con segundos" "$LECTOR" \
  "substr((string) \$item->slot->start_time, 0, 5)" "substr((string) \$item->slot->start_time, 0, 8)"
mutar "fila · un pack no es un cumpleaños" "$LECTOR" \
  "isParty: \$item->ticketType?->type === TicketType::TYPE_PACK," "isParty: false,"
mutar "fila · la zona por consulta suelta (N+1)" "$LECTOR" \
  "'ticketType.zone', 'slot'," "'ticketType', 'slot',"

# ── La vista y la hoja (las decisiones que fijan las pruebas) ────────────────────────────────────────────────
mutar "vista · «Nueva búsqueda» solo con ficha (privacidad, #817)" "$VISTA" \
  "    @if (\$verdict !== null)
        <footer class=\"ppu-pie gate-foot\">" "    @if (\$ficha !== null)
        <footer class=\"ppu-pie gate-foot\">"
mutar "vista · la doble lectura llega a Livewire" "$VISTA" \
  "\$event.preventDefault(); \$event.stopImmediatePropagation();" "\$event.preventDefault();"
mutar "vista · «Sus hijos» sin el estado de su descargo" "$VISTA" \
  " data-gate-minor-waiver=\"{{ \$hijo['descargo'] }}\"" ""
mutar "vista · el veredicto sin la marca del descargo del titular" "$VISTA" \
  " data-gate-waiver=\"{{ \$ficha['descargo'] }}\"" ""
mutar "hoja · un botón de 40 px" "$HOJA" \
  "    min-width: 44px;
    min-height: 44px;
    padding: 0 18px;" "    min-width: 44px;
    min-height: 40px;
    padding: 0 18px;"
mutar "hoja · la pantalla se desplaza como documento" "$HOJA" \
  "    height: 100dvh;" "    min-height: 100dvh;"

echo
echo "$muerden/$total muerden"
[[ $muerden -eq $total ]]
