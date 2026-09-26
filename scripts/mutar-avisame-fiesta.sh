#!/usr/bin/env bash
# Arnés de mutación de F6b, «TUS RESPUESTAS» y «AVÍSAME DE FECHAS» (`specs/fiesta-sistema-nuevo.md` §4.12,
# `specs/avisame-de-fechas.md`, `[DECIDIDO owner]` `#750`).
#
# Protege lo que calla al romperse: que la casilla solo se ofrezca (y solo se guarde) con la prueba detrás —un «sí»
# firmado desde ese recibo, con correo—, que su envío no pase por la rama de «Su ficha», marcar/desmarcar/revivir, la
# baja (firmada, solo por POST, a todo ese correo), la ventana del correo y su «una vez por cumpleaños», a quién no se
# escribe, lo que el correo lleva al pie y en su cabecera, el permiso del panel, y el hueco de «Tus respuestas» con su
# lógica en el navegador (`node --test`).
#
# Reglas de la casa dentro (`/mutar`): verde antes de mutar · veredicto por código de salida · ancla ÚNICA · comprobar
# que la mutación SE APLICÓ · restaurar por COPIA DE SEGURIDAD (por ruta entera) y `touch`, nunca `git checkout`.
#
#   bash scripts/mutar-avisame-fiesta.sh        (desde el host, con el contenedor en marcha)
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

FILTER='AvisameDeFechasTest|AvisameDeFechasEnvioTest|InvitacionPaginaTest|OcultoSinJavaScriptTest'
RUN="docker compose exec -u sail -T laravel.test php artisan test --filter=${FILTER}"
RUNJS="docker compose exec -u sail -T laravel.test node --test resources/js/fiesta/logica.test.js"

TMP="$(mktemp -d)"
FICHEROS=(
    app/Domain/Identity/Services/BirthdayReminders.php
    app/Http/Controllers/InvitationPageController.php
    app/Http/Controllers/BirthdayReminderController.php
    app/Console/Commands/SendBirthdayReminders.php
    app/Notifications/BirthdayComingNotice.php
    app/Filament/Resources/BirthdayReminders/BirthdayReminderResource.php
    app/Http/Fiesta/InvitacionPagina.php
    routes/web.php
    resources/views/fiesta/invitacion/avisame.blade.php
    resources/views/fiesta/invitacion/mias.blade.php
    resources/js/fiesta/logica.js
    resources/js/fiesta/fiesta.css
)
copia() { echo "$TMP/$(echo "$1" | tr '/' '_')"; }
restaurar() { for f in "${FICHEROS[@]}"; do cp "$(copia "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$(copia "$f")"; done

verde() { $RUN >/dev/null 2>&1; }
verdejs() { $RUNJS >/dev/null 2>&1; }

if ! verde || ! verdejs; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde'

muerden=0; total=0

# mutar <nombre> <fichero> <buscar> <poner> [js]
mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4" juez="${5:-php}"
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
        echo "  ⚠ «$nombre» NO SE APLICÓ (el patrón no casa): el veredicto no vale"
        return
    fi
    touch "$fichero"
    local sigue=1
    if [[ "$juez" == js ]]; then verdejs || sigue=0; else verde || sigue=0; fi
    if [[ $sigue -eq 1 ]]; then
        echo "  ✗ NO muerde: $nombre"
    else
        echo "  ✓ muerde:    $nombre"
        muerden=$((muerden + 1))
    fi
    cp "$(copia "$fichero")" "$fichero"; touch "$fichero"
}

S=app/Domain/Identity/Services/BirthdayReminders.php
C=app/Http/Controllers/InvitationPageController.php
BRC=app/Http/Controllers/BirthdayReminderController.php
CMD=app/Console/Commands/SendBirthdayReminders.php
N=app/Notifications/BirthdayComingNotice.php
R=app/Filament/Resources/BirthdayReminders/BirthdayReminderResource.php
IP=app/Http/Fiesta/InvitacionPagina.php
W=routes/web.php
AV=resources/views/fiesta/invitacion/avisame.blade.php
MV=resources/views/fiesta/invitacion/mias.blade.php
LJ=resources/js/fiesta/logica.js
FC=resources/js/fiesta/fiesta.css

# ── 1 · A quién se ofrece la casilla, y el POST que mira lo mismo ────────────────────────────────
mutar "se ofrece sin la firma detrás (podada)" "$S" \
  $'            ->whereHas(\'waiverSignatures\')\n' \
  ''
mutar "se ofrece sin correo" "$S" \
  $'            ->where(\'guardian_email\', \'!=\', \'\')\n' \
  ''
mutar "el ajuste a 0 no apaga la casilla" "$S" \
  'if (! self::enabled()) {' \
  'if (false) {'
mutar "el envío de la casilla cae en la rama de «Su ficha»" "$C" \
  "if (\$request->has('dates')) {" \
  "if (false && \$request->has('dates')) {"
mutar "el POST no mira si se ofrece" "$C" \
  '$authorization = $reply->attending ? $reminders->offerableFor((int) $reply->getKey()) : null;' \
  "\$authorization = \\App\\Domain\\Identity\\Models\\GuardianAuthorization::query()->where('invitation_reply_id', \$reply->getKey())->first();"
mutar "un «no» pide avisos" "$C" \
  '$reply->attending ? $reminders->offerableFor' \
  'true ? $reminders->offerableFor'
mutar "el recibo la pinta marcada estando de baja" "$C" \
  "'marcada' => \$row !== null && \$row->isLive()," \
  "'marcada' => \$row !== null,"
mutar "la casilla viene marcada de serie" "$AV" \
  ":checked=\"\$av['marcada']\"" \
  ':checked="true"'

# ── 2 · Marcar, desmarcar, revivir y la baja ─────────────────────────────────────────────────────
mutar "desmarcar no da de baja" "$S" \
  'if ($row !== null && $row->isLive()) {' \
  'if (false) {'
mutar "marcar de nuevo no revive" "$S" \
  'if (! $row->isLive()) {' \
  'if (false) {'
mutar "revivir sin su nueva fecha de aceptación" "$S" \
  "['revoked_at' => null, 'accepted_at' => now(), 'locale' => \$locale]" \
  "['revoked_at' => null, 'locale' => \$locale]"
mutar "la baja no alcanza a todo el correo" "$S" \
  "\$ids = \$email === ''" \
  '$ids = true'
mutar "abrir la baja (GET) ya da de baja" "$W" \
  "Route::get('/avisos-cumple/{reminder}/baja', [BirthdayReminderController::class, 'show'])" \
  "Route::get('/avisos-cumple/{reminder}/baja', [BirthdayReminderController::class, 'confirm'])"
mutar "la página de baja sin firma" "$W" \
  "->middleware(['signed', 'throttle:60,1', 'no-store'])
        ->missing(fn () => abort(404))
        ->name('birthday-reminder.unsubscribe');" \
  "->middleware(['throttle:60,1', 'no-store'])
        ->missing(fn () => abort(404))
        ->name('birthday-reminder.unsubscribe');"
mutar "el botón de baja sin firma" "$W" \
  "->middleware(['signed', 'throttle:20,1', 'no-store'])
        ->missing(fn () => abort(404))
        ->name('birthday-reminder.unsubscribe.confirm');" \
  "->middleware(['throttle:20,1', 'no-store'])
        ->missing(fn () => abort(404))
        ->name('birthday-reminder.unsubscribe.confirm');"
mutar "la página de baja corta el nombre compuesto" "$BRC" \
  "'nino' => trim((string) (\$reminder->authorization->minor_name ?? ''))," \
  "'nino' => trim(\\Illuminate\\Support\\Str::before(trim((string) (\$reminder->authorization->minor_name ?? '')), ' ')),"

# ── 3 · Cuándo sale el correo, una vez por cumpleaños ────────────────────────────────────────────
mutar "la ventana sin techo" "$S" \
  '$dias <= $weeks * 7' \
  '$dias <= $weeks * 70'
mutar "tan cerca que llega tarde" "$S" \
  '$dias >= $minDaysAhead' \
  '$dias >= 0'
mutar "dos veces el mismo cumpleaños" "$S" \
  'if (! $yaSalio && ' \
  'if ('
mutar "el mismo niño en dos fiestas, dos correos" "$S" \
  "\$clave = \$email.'|'.\$a->minor_key" \
  "\$clave = \$row->getKey().'|'.\$a->minor_key"
mutar "solo se marca una fila del grupo" "$S" \
  'static fn (BirthdayReminder $r): int => (int) $r->getKey(), $rows))' \
  'static fn (BirthdayReminder $r): int => (int) $r->getKey(), array_slice($rows, 0, 1)))'
mutar "el 29-F se va al 1 de marzo" "$S" \
  '! CarbonImmutable::create($year)->isLeapYear() ? 28 : $bornOn->day' \
  'false ? 28 : $bornOn->day'
mutar "hoy no cuenta como su cumpleaños" "$S" \
  '$thisYear->lessThan($today->startOfDay())' \
  '$thisYear->lessThanOrEqualTo($today->startOfDay())'
mutar "antes de las 10 del parque, ya manda" "$CMD" \
  "if (DisplayTime::now()->hour < self::FROM_HOUR && ! \$this->option('force')) {" \
  'if (false) {'
mutar "el ensayo manda de verdad" "$CMD" \
  $'            if ($dryRun) {\n                $sent++;' \
  $'            if (false) {\n                $sent++;'
mutar "sin la marca antes de encolar" "$CMD" \
  $'            $reminders->markSent($g[\'rows\'], $g[\'next\']);\n' \
  ''
mutar "escribe a quien ya celebra aquí" "$CMD" \
  "if (\$this->alreadyCelebrates(\$email, \$g['next'])) {" \
  'if (false) {'
mutar "«ya celebra» olvida la fiesta de hace unos meses" "$CMD" \
  "->where('slots.date', '>=', \$next->subYear()->toDateString())" \
  "->where('slots.date', '>=', \$next->toDateString())"
mutar "la edad, un año de menos" "$CMD" \
  "edad: \$g['next']->year - \$a->minor_born_on->year," \
  "edad: \$g['next']->year - \$a->minor_born_on->year - 1,"
mutar "el correo corta el nombre compuesto" "$CMD" \
  'nombre: trim((string) $a->minor_name),' \
  "nombre: trim(\\Illuminate\\Support\\Str::before(trim((string) \$a->minor_name), ' ')),"

# ── 4 · El correo: el precio, la baja al pie y en la cabecera ────────────────────────────────────
mutar "sin precio, la frase del precio sale igual" "$N" \
  'if ($this->desde !== null) {' \
  'if (true) {'
mutar "la baja fuera del pie" "$N" \
  ".' <a href=\"'.e(\$baja).'\">'.e(__('fiesta.cumple_mail.baja')).'</a>'" \
  ".''"
mutar "sin List-Unsubscribe" "$N" \
  "\$message->getHeaders()->addTextHeader('List-Unsubscribe', '<'.\$baja.'>');" \
  '// sin cabecera'

# ── 5 · El panel ─────────────────────────────────────────────────────────────────────────────────
mutar "el panel sin el permiso de clientes" "$R" \
  'return auth()->user()?->hasPermission(self::PERMISSION) ?? false;' \
  'return true;'

# ── 6 · «Tus respuestas»: el hueco del servidor y su lógica en el navegador ─────────────────────
mutar "«hidden» solo vale con JavaScript (el visor tapa la página)" "$FC" \
  '[hidden] { display: none !important; }' \
  '.js [hidden] { display: none !important; }'
mutar "el hueco se ve sin JavaScript" "$MV" \
  'data-mias hidden' \
  'data-mias'
mutar "el hueco lleva el token de la invitación" "$IP" \
  "'fiesta' => (string) \$inv->getKey()," \
  "'fiesta' => (string) \$inv->token,"
mutar "el chip lleva el nombre entero" "$IP" \
  "'nombre' => trim(Str::before(\$recibo['nino'], ' '))," \
  "'nombre' => trim(\$recibo['nino']),"
mutar "las caducadas se quedan" "$LJ" \
  "&& typeof r.nombre === 'string' && Number(r.hasta) > ahora);" \
  "&& typeof r.nombre === 'string');" js
mutar "renovar la manda al final" "$LJ" \
  'return vivas.some(misma) ? vivas.map((r) => (misma(r) ? nueva : r)) : [...vivas, nueva];' \
  'return [...vivas.filter((r) => !misma(r)), nueva];' js
mutar "salen las de otra fiesta" "$LJ" \
  'return lista.filter((r) => r.fiesta === fiesta);' \
  'return lista;' js
mutar "la caducidad en segundos" "$LJ" \
  'Number(e) * 1000' \
  'Number(e)' js

echo "$muerden/$total muerden"
[[ $muerden -eq $total ]]
