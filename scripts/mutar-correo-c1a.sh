#!/usr/bin/env bash
# Arnés de mutación de la C1a de los correos (`specs/correos-rediseno.md` §4.4, `[DECIDIDO owner]` `#920`): A QUIÉN se le
# escribe un comercial (solo con «novedades», con correo y sin anonimizar; elegido por el comando Y releído al salir), el 12
# AMPLIADO (la ventana, el menor activo y menor de 18, una vez por cumpleaños, nunca dos veces el mismo cumple por los dos
# caminos, y nada por «novedades» a quien se dio de baja de «Avísame de fechas»), el PIE comercial (su baja, sin UTM, en
# `List-Unsubscribe` y en texto) y LA BAJA de «novedades» (firmada, solo el botón escribe, con su prueba, en su idioma).
#
# ⚠️ Cada mutación corre SOLO los tests que la tienen que ver (su 5.º argumento). Reglas de la casa dentro: verde antes de
# mutar · veredicto por código de salida · comprobar que la mutación SE APLICÓ y que su ancla es ÚNICA · CONTROLES que no
# deben morder · restaurar por COPIA DE SEGURIDAD, guardada por la RUTA ENTERA (`#181`; `mutar-correo-r1c.sh`, 03-10).
#
#   bash scripts/mutar-correo-c1a.sh                    # entero: 35 mutaciones, ~4 min 10 s (medido el 03-10)
#   SOLO='vista previa' bash scripts/mutar-correo-c1a.sh   # solo las mutaciones cuyo nombre lo contiene (y sus controles)
set -uo pipefail
SOLO="${SOLO:-}"
cd "$(git rev-parse --show-toplevel)"
DOCE='CumpleSeAcercaPorNovedadesTest'
PIE='CommercialFooterTest|CumpleSeAcercaPorNovedadesTest|AvisameDeFechasEnvioTest'
TODOS='MarketableAccountsTest|CumpleSeAcercaPorNovedadesTest|AvisameDeFechas|MarketingUnsubscribeTest|CommercialFooterTest|MailPreviewsTest'
correr() { docker compose exec -u sail -T laravel.test php artisan test --filter="$1" >/dev/null 2>&1; }
TMP="$(mktemp -d)"
U=app/Domain/Identity/Models/User.php
BR=app/Domain/Identity/Services/BirthdayReminders.php
CMD=app/Console/Commands/SendBirthdayReminders.php
N12=app/Notifications/BirthdayComingNotice.php
BMM=app/Notifications/Support/BrandedMailMessage.php
MM=app/Notifications/Support/MarketingMail.php
MP=app/Notifications/Support/MailPreviews.php
VPIE=resources/views/correo/html/pie.blade.php
VPIET=resources/views/correo/texto/pie.blade.php
CTRL=app/Http/Controllers/MarketingUnsubscribeController.php
RUT=routes/web.php
FICHEROS=("$U" "$BR" "$CMD" "$N12" "$BMM" "$MM" "$MP" "$VPIE" "$VPIET" "$CTRL" "$RUT")
copia() { printf '%s/%s' "$TMP" "${1//\//__}"; }
restaurar() { for f in "${FICHEROS[@]}"; do cp "$(copia "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$(copia "$f")"; done
[[ $(find "$TMP" -type f | wc -l) -eq ${#FICHEROS[@]} ]] || { echo '✗ hay copias que se pisan: el árbol no se podría restaurar' >&2; exit 1; }
if ! correr "$TODOS"; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde'

muerden=0; total=0; control_ok=1; controles=0

aplicar() {
    python3 -c 'import sys; p=sys.argv[1]; s=open(p,encoding="utf-8").read(); n=s.count(sys.argv[2]); open(p,"w",encoding="utf-8").write(s.replace(sys.argv[2], sys.argv[3], 1)); sys.exit(0 if n == 1 else 3)' \
        "$1" "$2" "$3"
}

mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4" filtro="$5"
    [[ -n "$SOLO" && "$nombre" != *"$SOLO"* ]] && return
    total=$((total + 1))
    aplicar "$fichero" "$buscar" "$poner"; local unico=$?
    if cmp -s "$fichero" "$(copia "$fichero")"; then
        echo "  ⚠ «$nombre» NO SE APLICÓ (el patrón no casa): el veredicto no vale"
        return
    fi
    if [[ $unico -ne 0 ]]; then
        echo "  ⚠ «$nombre»: el ancla NO es única; se mutó la primera y el veredicto es dudoso"
    fi
    touch "$fichero"
    if correr "$filtro"; then
        echo "  ✗ NO muerde: $nombre"
    else
        echo "  ✓ muerde:    $nombre"
        muerden=$((muerden + 1))
    fi
    cp "$(copia "$fichero")" "$fichero"; touch "$fichero"
}

control() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4" filtro="$5"
    [[ -n "$SOLO" && "$nombre" != *"$SOLO"* ]] && return
    controles=$((controles + 1))
    aplicar "$fichero" "$buscar" "$poner"
    if cmp -s "$fichero" "$(copia "$fichero")"; then
        echo "  ⚠ CONTROL «$nombre» NO SE APLICÓ"; control_ok=0; return
    fi
    touch "$fichero"
    if correr "$filtro"; then
        echo "  ✓ control:   $nombre (sigue verde, como debe)"
    else
        echo "  ✗ CONTROL ROJO: $nombre — se pone rojo por algo que no es la regla"; control_ok=0
    fi
    cp "$(copia "$fichero")" "$fichero"; touch "$fichero"
}

# ── A quién: el público del comando y la cuenta al salir ──────────────────────────────────────────
mutar "el público, sin «novedades»" "$U" \
  "\$query->where('marketing_opt_in', true)" "\$query->whereRaw('1 = 1')" \
  "MarketableAccountsTest|$DOCE"
mutar "el público, con la dirección de una cuenta suprimida" "$U" \
  "->where('email', 'not like', '%@'.self::ANONYMIZED_EMAIL_DOMAIN)" "->whereRaw('1 = 1')" \
  "MarketableAccountsTest|$DOCE"
mutar "el público, con el correo vacío" "$U" \
  "->where('email', '!=', '')" "->whereRaw('1 = 1')" \
  'MarketableAccountsTest'
mutar "al salir, sin «novedades»" "$U" \
  "return (bool) \$this->marketing_opt_in && trim(" "return true && trim(" \
  "MarketableAccountsTest|$DOCE"
mutar "al salir, aunque esté suprimida" "$U" \
  "trim((string) \$this->email) !== '' && ! \$this->isAnonymized();" "trim((string) \$this->email) !== '';" \
  'MarketableAccountsTest'
mutar "al salir, sin correo" "$U" \
  "trim((string) \$this->email) !== '' && ! \$this->isAnonymized();" "! \$this->isAnonymized();" \
  'MarketableAccountsTest'
mutar "al salir, sin releer «novedades»" "$N12" \
  "return \$notifiable instanceof User && \$notifiable->canReceiveMarketing();" "return true;" \
  "$DOCE"
mutar "al salir, sin releer la casilla de «Avísame de fechas»" "$N12" \
  "return BirthdayReminder::query()->whereKey(\$this->reminderId)->whereNull('revoked_at')->exists();" "return true;" \
  'AvisameDeFechasEnvioTest'
mutar "al salir, una casilla de baja cuenta como viva" "$N12" \
  "->whereKey(\$this->reminderId)->whereNull('revoked_at')->exists();" "->whereKey(\$this->reminderId)->exists();" \
  'AvisameDeFechasEnvioTest'

# ── El 12 ampliado ────────────────────────────────────────────────────────────────────────────────
mutar "fuera de la ventana" "$BR" \
  "if (\$dias > \$weeks * 7 || \$dias < \$minDaysAhead ||" "if (\$dias < \$minDaysAhead ||" \
  "$DOCE"
mutar "tan cerca que llegaría tarde" "$BR" \
  "\$dias < \$minDaysAhead || \$age >= Dependent::ADULT_AGE" "\$age >= Dependent::ADULT_AGE" \
  "$DOCE"
mutar "el menor que cumple 18" "$BR" \
  "\$age >= Dependent::ADULT_AGE" "false" \
  "$DOCE"
mutar "otra vez el mismo cumpleaños" "$BR" \
  "\$d->birthday_mail_for?->toDateString() === \$next->toDateString()" "false" \
  "$DOCE"
mutar "un menor retirado" "$BR" \
  "Dependent::query()->active()" "Dependent::query()" \
  "$DOCE"
mutar "la marca no se escribe" "$BR" \
  "Dependent::query()->whereKey(\$dependent->getKey())->toBase()->update(['birthday_mail_for' => \$next->toDateString()]);" "unset(\$dependent, \$next);" \
  "$DOCE"
mutar "la cuenta, aunque ya celebre aquí" "$CMD" \
  "if (\$this->alreadyCelebrates(\$email, \$next) || \$reminders->leftOrSentFor(" "if (\$reminders->leftOrSentFor(" \
  "$DOCE"
mutar "la cuenta, aunque la casilla ya lo mandara o se diera de baja" "$CMD" \
  " || \$reminders->leftOrSentFor(\$email, \$d->born_on, \$next)) {" ") {" \
  "$DOCE"
mutar "por «novedades», aunque se diera de baja de «Avísame de fechas»" "$BR" \
  "\$q->whereNotNull('revoked_at')->orWhere(" "\$q->whereRaw('1 = 0')->orWhere(" \
  "$DOCE"
mutar "por «novedades», el cumple que la casilla ya mandó" "$BR" \
  "->whereDate('sent_for', \$next->toDateString())" "->whereDate('sent_for', '1900-01-01')" \
  "$DOCE"
mutar "por la casilla, el cumple que la cuenta ya recibió" "$BR" \
  "->whereDate('birthday_mail_for', \$next->toDateString())" "->whereDate('birthday_mail_for', '1900-01-01')" \
  "$DOCE"
mutar "la casilla, sin mirar la cuenta" "$CMD" \
  "if (\$reminders->sentToAccountFor(\$email, \$a->minor_born_on, \$g['next'])) {" "if (false) {" \
  "$DOCE"
mutar "el comando no marca" "$CMD" \
  "\$reminders->markDeclaredSent(\$d, \$next);" "" \
  "$DOCE"
mutar "a la cuenta, el porqué de «Avísame de fechas»" "$N12" \
  "return MarketingMail::footer(\$mail, \$notifiable);" \
  "return \$mail->commercial((string) __('fiesta.cumple_mail.porque', ['nombre' => \$this->nombre]), MarketingMail::unsubscribeUrl(\$notifiable));" \
  "$DOCE"
mutar "la vista previa, con el precio de ejemplo aunque haya catálogo" "$MP" \
  "((new PartyCards)->cheapest(TicketType::birthdaySurfacePacks()->with(['prices.rateType'])->get()) ?? (string) __('admin.mail_texts.ejemplo_desde'))" \
  "((string) __('admin.mail_texts.ejemplo_desde'))" \
  'MailPreviewsTest'

# ── El pie comercial ──────────────────────────────────────────────────────────────────────────────
mutar "una frase sin su baja, aceptada" "$BMM" \
  "if (preg_match('/\\]\\(baja\\)/', \$porque) !== 1) {" "if (false) {" \
  'CommercialFooterTest'
mutar "sin \`List-Unsubscribe\`" "$BMM" \
  "addTextHeader('List-Unsubscribe', '<'.\$baja.'>')" "addTextHeader('X-Sin-Baja', '<'.\$baja.'>')" \
  "$PIE"
mutar "la baja, con la UTM" "$BMM" \
  "\$this->viewData['enlaces']['baja'] = \$baja;" "\$this->viewData['enlaces']['baja'] = \$this->etiquetada(\$baja);" \
  'CommercialFooterTest'
mutar "el pie comercial, sin su enlace" "$VPIE" \
  "{!! \$correo->rico(\$correo->comercial)['h'] !!}" "{{ \$correo->comercial }}" \
  "$PIE"
mutar "en texto, sin la dirección de la baja" "$VPIET" \
  "{!! \$correo->rico(\$correo->comercial)['t'] !!}" "{!! \$correo->comercial !!}" \
  'CommercialFooterTest'

# ── La baja de «novedades» ────────────────────────────────────────────────────────────────────────
mutar "abrirla da de baja" "$CTRL" \
  "\$this->useLocaleOf(\$user);" "\$this->useLocaleOf(\$user); \$user->forceFill(['marketing_opt_in' => false])->save();" \
  'MarketingUnsubscribeTest'
mutar "la baja, sin su prueba" "$CTRL" \
  "\$privacy->setMarketing(\$user, false, (string) \$request->ip());" "\$user->forceFill(['marketing_opt_in' => false])->save();" \
  'MarketingUnsubscribeTest'
mutar "la página, en el idioma de la instalación" "$CTRL" \
  "app()->setLocale((string) \$user->locale);" "app()->getLocale();" \
  'MarketingUnsubscribeTest'
mutar "la página se abre sin firma" "$RUT" \
  $'[MarketingUnsubscribeController::class, \'show\'])\n        ->whereNumber(\'user\')\n        ->middleware([\'signed\', ' \
  $'[MarketingUnsubscribeController::class, \'show\'])\n        ->whereNumber(\'user\')\n        ->middleware([' \
  'MarketingUnsubscribeTest'
mutar "la baja se escribe sin firma" "$RUT" \
  $'[MarketingUnsubscribeController::class, \'confirm\'])\n        ->whereNumber(\'user\')\n        ->middleware([\'signed\', ' \
  $'[MarketingUnsubscribeController::class, \'confirm\'])\n        ->whereNumber(\'user\')\n        ->middleware([' \
  'MarketingUnsubscribeTest'
mutar "el enlace del correo, sin firmar" "$MM" \
  "URL::signedRoute('marketing.unsubscribe', ['user' => \$user->getKey()])" "route('marketing.unsubscribe', ['user' => \$user->getKey()])" \
  'MarketingUnsubscribeTest'

# ── CONTROLES: la misma regla, escrita de otra forma, NO debe morder ──────────────────────────────
control "la casilla, con su operador escrito" "$U" \
  "\$query->where('marketing_opt_in', true)" "\$query->where('marketing_opt_in', '=', true)" \
  "MarketableAccountsTest|$DOCE"
control "la ventana, con sus factores al revés" "$BR" \
  "if (\$dias > \$weeks * 7 || \$dias < \$minDaysAhead ||" "if (\$dias > 7 * \$weeks || \$dias < \$minDaysAhead ||" \
  "$DOCE"

# Con `SOLO=` puede no correr ningún control: entonces se dice, no se da por verde.
echo "$muerden/$total muerden · $([[ $controles -eq 0 ]] && echo 'sin control (filtrado por SOLO)' || { [[ $control_ok -eq 1 ]] && echo "$controles controles verdes" || echo 'control ROJO'; })"
[[ $total -gt 0 && $muerden -eq $total && $control_ok -eq 1 ]]
