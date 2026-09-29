#!/usr/bin/env bash
# Arnés de mutación de LOS CORREOS SALIENTES (`docs/specs/correos-salientes.md`, `DECISIONES #794`). Crece por tanda.
#
# C1 · EL REGISTRO Y LA VISTA PREVIA: una fila por correo AL CLIENTE con la copia EXACTA; el reintento que sale bien completa
# la MISMA fila; los avisos al negocio no se apuntan; apuntar NUNCA rompe el envío ni corre dentro de la transacción de quien
# envía; la copia vive 6 meses y la fila 24; la
# supresión se lleva lo personal; el export lo lleva; la página, la vista previa aislada y su rastro, con permiso propio.
#
#
# C2 · LOS CLICS POR ENVÍO (§4.8): la marca `jw_e` solo a una cuenta que no se opuso, con el interruptor (apagado de fábrica) y
# nunca en la encuesta; se re-comprueba al pulsar; la URL se limpia con un 302 sobre la query cruda; el escáner por el ritmo
# (`early`, `sweep`, `repeat`); la marca, ignorada por la firma y NUNCA en la analítica anónima; el panel, el export y la poda.
# ⚠️ El BLOQUEO por envío (`lockForUpdate`) no lo puede ver PHPUnit (no hay peticiones en paralelo): se mide con la sonda de
# concurrencia de la C2 contra MySQL, con y sin él (spec §4.9).
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la mutación SE APLICÓ · un
# CONTROL que no debe morder · restaurar por COPIA DE SEGURIDAD y no con `git checkout` (`#181`).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

FILTER='EmailSendsRecordTest|EmailSendsPanelTest|EmailClicksTest|EmailUtmTest'
# Modo «solo una tanda» (`SOLO=C2 bash scripts/mutar-correos-salientes.sh`), como en `mutar-analitica-decidir.sh`: corre SOLO
# las mutaciones de esa sección y con SUS pruebas. La base verde y el CONTROL de esa sección corren siempre.
declare -A FILTRO_DE=(
    [C1]='EmailSendsRecordTest|EmailSendsPanelTest'
    [C2]='EmailClicksTest|EmailSendsPanelTest|EmailSendsRecordTest|EmailUtmTest'
)
SOLO="${SOLO:-}"
SECCION=''
if [[ -n "$SOLO" ]]; then
    [[ -n "${FILTRO_DE[$SOLO]:-}" ]] || { echo "✗ SOLO=$SOLO no es una tanda de este arnés (${!FILTRO_DE[*]})" >&2; exit 2; }
    FILTER="${FILTRO_DE[$SOLO]}"
fi
RUN="docker compose exec -u sail -T laravel.test php artisan test --filter=${FILTER}"

TMP="$(mktemp -d)"
FICHEROS=(
    app/Notifications/Support/BrandedMailMessage.php
    app/Domain/Platform/Listeners/RecordEmailSend.php
    app/Domain/Platform/Models/EmailSend.php
    app/Domain/Identity/Models/User.php
    app/Domain/Identity/Services/AccountPrivacy.php
    app/Filament/Resources/EmailSends/EmailSendResource.php
    app/Filament/Resources/EmailSends/Tables/EmailSendTable.php
    resources/views/filament/email-sends/preview.blade.php
    app/Filament/Resources/Users/Schemas/UserInfolist.php
    app/Filament/Resources/Users/Pages/ListUsers.php
    routes/console.php
    app/Domain/Platform/Services/Analytics/EmailClickMarks.php
    app/Domain/Platform/Services/Analytics/EmailClicks.php
    app/Domain/Platform/Services/Analytics/EmailUtm.php
    app/Domain/Platform/Services/Analytics/RouteNormalizer.php
    app/Http/Middleware/RecordEmailClick.php
    app/Providers/AppServiceProvider.php
    resources/views/vendor/mail/html/footer.blade.php
    database/migrations/2026_09_29_050000_add_email_click_tracking.php
)
copia() { echo "$TMP/$(echo "$1" | tr '/' '_')"; }
restaurar() { for f in "${FICHEROS[@]}"; do cp "$(copia "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$(copia "$f")"; done

verde() { $RUN >/dev/null 2>&1; }

if ! verde; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde'

muerden=0; total=0; control_ok=1

aplicar() {
    python3 -c 'import sys; p=sys.argv[1]; s=open(p,encoding="utf-8").read(); n=s.count(sys.argv[2]); open(p,"w",encoding="utf-8").write(s.replace(sys.argv[2], sys.argv[3], 1)); sys.exit(0 if n == 1 else 3)' \
        "$1" "$2" "$3"
}

mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4"
    if [[ -n "$SOLO" && "$SECCION" != "$SOLO" ]]; then
        return
    fi
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
    if verde; then
        echo "  ✗ NO muerde: $nombre"
    else
        echo "  ✓ muerde:    $nombre"
        muerden=$((muerden + 1))
    fi
    cp "$(copia "$fichero")" "$fichero"; touch "$fichero"
}

control() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4"
    if [[ -n "$SOLO" && "$SECCION" != "$SOLO" ]]; then
        return
    fi
    aplicar "$fichero" "$buscar" "$poner"
    if cmp -s "$fichero" "$(copia "$fichero")"; then
        echo "  ⚠ CONTROL «$nombre» NO SE APLICÓ"; control_ok=0; return
    fi
    touch "$fichero"
    if verde; then
        echo "  ✓ control:   $nombre (sigue verde, como debe)"
    else
        echo "  ✗ CONTROL ROJO: $nombre — la suite se pone roja por algo que no es la regla"; control_ok=0
    fi
    cp "$(copia "$fichero")" "$fichero"; touch "$fichero"
}

L=app/Domain/Platform/Listeners/RecordEmailSend.php
M=app/Domain/Platform/Models/EmailSend.php
T=app/Filament/Resources/EmailSends/Tables/EmailSendTable.php

# ══ C1 ══════════════════════════════════════════════════════════════════════════════════════════
SECCION=C1

# ── El registro ─────────────────────────────────────────────────────────────────────────────────
mutar "el correo sale sin la marca del envío" "app/Notifications/Support/BrandedMailMessage.php" \
  '            $message->getHeaders()->addTextHeader(EmailSend::HEADER, $send);' \
  ''

mutar "se apunta también lo que no va al cliente" "$L" \
  '        return EmailUtm::isCustomerKey($key) ? $key : null;' \
  '        return $key;'

mutar "apuntar puede romper el envío (la excepción sube: correo DOBLE)" "$L" \
  "            Log::warning('email_sends.record_failed', ['error' => \$e::class]);" \
  '            throw $e;'

mutar "se apunta DENTRO de la transacción de quien envía (un deadlock la desharía entera)" "$L" \
  '        DB::afterCommit(static function () use ($record): void {' \
  '        call_user_func(static function () use ($record): void {'

mutar "la copia no se guarda" "$L" \
  "                'html' => \$this->htmlOf(\$email)," \
  "                'html' => null,"

mutar "el reintento que sale bien no marca la fila como enviada" "$L" \
  "            ]], ['send_key'], ['user_id', 'recipient', 'mail_key', 'subject', 'html', 'attachments', 'sent_at', 'updated_at']);" \
  "            ]], ['send_key'], ['user_id', 'recipient', 'mail_key', 'subject', 'html', 'attachments', 'updated_at']);"

mutar "los fallos no se cuentan" "$L" \
  "                ->update(['failures' => DB::raw('failures + 1'), 'failed_at' => \$now, 'updated_at' => \$now]);" \
  "                ->update(['failures' => 1, 'failed_at' => \$now, 'updated_at' => \$now]);"

# ── Los plazos, la supresión y el export ────────────────────────────────────────────────────────
mutar "la copia vive un año, no seis meses" "$M" \
  '    public const COPY_MONTHS = 6;' \
  '    public const COPY_MONTHS = 12;'

mutar "la fila vive tres años, no dos" "$M" \
  '    public const RETENTION_MONTHS = 24;' \
  '    public const RETENTION_MONTHS = 36;'

mutar "la supresión no toca los correos" "app/Domain/Identity/Models/User.php" \
  '            EmailSend::forgetPerson((int) $this->getKey());' \
  ''

mutar "la supresión se deja la copia" "$M" \
  "            'user_id' => null, 'recipient' => null, 'subject' => null, 'html' => null, 'attachments' => null," \
  "            'user_id' => null, 'recipient' => null, 'subject' => null, 'attachments' => null,"

mutar "el export no dice qué correo era" "app/Domain/Identity/Services/AccountPrivacy.php" \
  "                    'mail' => (string) \$send->mail_key," \
  "                    'mail' => 'correo',"

mutar "el borrado de copias no está en el planificador" "routes/console.php" \
  "Schedule::command('email-sends:trim')" \
  "Schedule::command('email-sends:trim-no')"

# ── El panel ────────────────────────────────────────────────────────────────────────────────────
mutar "la página se abre sin su permiso" "app/Filament/Resources/EmailSends/EmailSendResource.php" \
  '        return auth()->user()?->hasPermission(self::PERMISSION) ?? false;' \
  '        return auth()->check();'

mutar "ver un correo no deja rastro" "$T" \
  "                AuditLogger::log('emails.previewed', \$record, ['mail_key' => \$record->mail_key]);" \
  ''

mutar "la vista previa se ofrece sin copia" "$T" \
  '            ->visible(static fn (EmailSend $record): bool => EmailSendResource::canViewAny() && $record->hasCopy())' \
  '            ->visible(static fn (EmailSend $record): bool => EmailSendResource::canViewAny())'

mutar "la vista previa ejecuta scripts y abre enlaces" "resources/views/filament/email-sends/preview.blade.php" \
  '        sandbox=""' \
  '        sandbox="allow-scripts allow-popups"'

mutar "la ficha enseña los correos sin el permiso" "app/Filament/Resources/Users/Schemas/UserInfolist.php" \
  '                        ->visible(fn (): bool => auth()->user()?->hasPermission(EmailSendResource::PERMISSION) ?? false)' \
  '                        ->visible(fn (): bool => true)'

mutar "«Clientes» enlaza a la página sin el permiso" "app/Filament/Resources/Users/Pages/ListUsers.php" \
  '                ->visible(fn (): bool => EmailSendResource::canViewAny())' \
  '                ->visible(fn (): bool => true)'

# ── El CONTROL: tocar un comentario no puede poner nada en rojo ─────────────────────────────────
control "un comentario del oyente" "$L" \
  'Apunta cada correo AL CLIENTE que sale' \
  'Apunta cada correo AL CLIENTE que salió'

# ══ C2 ══════════════════════════════════════════════════════════════════════════════════════════
SECCION=C2
K=app/Domain/Platform/Services/Analytics/EmailClickMarks.php
C=app/Domain/Platform/Services/Analytics/EmailClicks.php
U=app/Domain/Platform/Services/Analytics/EmailUtm.php
W=app/Http/Middleware/RecordEmailClick.php

# ── La marca, al enviar ─────────────────────────────────────────────────────────────────────────
mutar "el botón sale sin la marca del envío" "app/Notifications/Support/BrandedMailMessage.php" \
  '        parent::action($text, EmailUtm::tag((string) $url, $this->campaign, $this->clickMark));' \
  '        parent::action($text, EmailUtm::tag((string) $url, $this->campaign));'

mutar "el pie sale sin la marca del envío" "resources/views/vendor/mail/html/footer.blade.php" \
  'EmailUtm::tag($url, $utm, $clickMark))' \
  'EmailUtm::tag($url, $utm))'

mutar "nadie decide la marca (el oyente no se registra)" "app/Providers/AppServiceProvider.php" \
  '        Event::listen(NotificationSending::class, [EmailClickMarks::class, '"'"'sending'"'"']);' \
  ''

mutar "sin el evento también hay marca (un toMail a mano la llevaría)" "$K" \
  '        return self::map()[$notification] ?? null;' \
  "        return self::map()[\$notification] ?? '00000000-0000-4000-8000-000000000000';"

mutar "el interruptor, ENCENDIDO de fábrica" "$K" \
  "        return (string) Setting::value(self::SETTING, '0') === '1';" \
  "        return (string) Setting::value(self::SETTING, '1') === '1';"

mutar "la marca a quien se opuso a la analítica" "$K" \
  '            && ! $recipient->analytics_opt_out' \
  ''

mutar "la encuesta anónima lleva la marca de una persona" "$K" \
  "    public const NEVER = ['survey_invitation'];" \
  '    public const NEVER = [];'

mutar "el envío no apunta que llevó la marca («no se mide» mentiría)" "$L" \
  "                'tracks_clicks' => EmailClickMarks::for(\$event->notification) !== null," \
  "                'tracks_clicks' => false,"

# ── La firma y la analítica ─────────────────────────────────────────────────────────────────────
mutar "la firma no ignora la marca (un enlace firmado daría 403)" "$U" \
  '    public const IGNORED_QUERY = [...RouteNormalizer::QUERY_ALLOWLIST, self::MARK];' \
  '    public const IGNORED_QUERY = RouteNormalizer::QUERY_ALLOWLIST;'

mutar "la marca entra en la analítica anónima" "app/Domain/Platform/Services/Analytics/RouteNormalizer.php" \
  "'gclid', 'fbclid', 'ttclid'];" \
  "'gclid', 'fbclid', 'ttclid', 'jw_e'];"

# ── El clic ─────────────────────────────────────────────────────────────────────────────────────
mutar "la marca se queda en la URL (sin el 302)" "$W" \
  '                return redirect()->to($clean);' \
  '                return $next($request);'

mutar "la clave codificada (jw%5Fe) no se quita" "$W" \
  "            static fn (string \$part): bool => urldecode(Str::before(\$part, '=')) !== EmailUtm::MARK," \
  "            static fn (string \$part): bool => Str::before(\$part, '=') !== EmailUtm::MARK,"

mutar "redirige aunque no haya quitado nada (bucle con jw.e)" "$W" \
  '            if ($clean !== null) {' \
  '            if (true) {'

mutar "apuntar el clic puede tumbar la página" "$W" \
  "            Log::warning('email_clicks.record_failed', ['error' => \$e::class]);" \
  '            throw $e;'

mutar "no se re-comprueba la regla al pulsar" "$C" \
  '            if ($send === null || ! $send->tracks_clicks || ! EmailClickMarks::allows($send->user, $send->mail_key)) {' \
  '            if ($send === null || ! $send->tracks_clicks) {'

mutar "cuenta el clic de un envío que salió sin marca" "$C" \
  '            if ($send === null || ! $send->tracks_clicks || ! EmailClickMarks::allows($send->user, $send->mail_key)) {' \
  '            if ($send === null || ! EmailClickMarks::allows($send->user, $send->mail_key)) {'

mutar "sin «early»: lo de antes de poder leerlo cuenta" "$C" \
  '$now->getTimestamp() - $send->sent_at->getTimestamp() < EmailClick::EARLY_SECONDS' \
  '$now->getTimestamp() - $send->sent_at->getTimestamp() < 0'

mutar "sin «sweep»: la ráfaga de un escáner cuenta" "$C" \
  '                $recent->count() + 1 >= EmailClick::SWEEP_HITS => EmailClick::VERDICT_SWEEP,' \
  '                $recent->count() + 1 >= 99 => EmailClick::VERDICT_SWEEP,'

mutar "la ráfaga no se lleva las visitas anteriores de su ventana" "$C" \
  '            if ($verdict === EmailClick::VERDICT_SWEEP) {' \
  '            if (false) {'

mutar "sin «repeat»: el doble paso cuenta dos veces" "$C" \
  '$click->verdict === null && $click->route === $route' \
  'false'

# ── Se ve, se exporta, se poda ──────────────────────────────────────────────────────────────────
mutar "el panel cuenta también las del escáner" "$M" \
  "            'clicks as clicks_counted' => static fn (Builder \$clicks) => \$clicks->whereNull('verdict')," \
  "            'clicks as clicks_counted' => static fn (Builder \$clicks) => \$clicks,"

mutar "sin la marca, el panel dice «Sin clics» (un cero que miente)" "$T" \
  '        if (! $record->tracks_clicks) {' \
  '        if (false) {'

mutar "el export no lleva los clics" "app/Domain/Identity/Services/AccountPrivacy.php" \
  "                    'clicks' => \$send->tracks_clicks ? (int) \$send->clicks_counted : null," \
  "                    'clicks' => null,"

mutar "la poda no arrastra los clics (la FK sin cascada)" "database/migrations/2026_09_29_050000_add_email_click_tracking.php" \
  "            \$table->foreignId('email_send_id')->constrained('email_sends')->cascadeOnDelete();" \
  "            \$table->foreignId('email_send_id')->constrained('email_sends');"

control "un comentario del servicio de los clics" "$C" \
  'Apunta la visita que llega con la marca de un envío' \
  'Apunta la visita que llegó con la marca de un envío'

echo
echo "$muerden/$total muerden${SOLO:+ (solo la tanda $SOLO)}"
[[ $muerden -eq $total && $control_ok -eq 1 ]]
