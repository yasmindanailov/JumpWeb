#!/usr/bin/env bash
# Arnés de mutación del ACCESO CON CÓDIGO, tandas A1 a A3 y la A5 (`docs/specs/acceso-con-codigo.md` §6, `DECISIONES #853`–`#857`, `#869`).
#
# Entrar con un código al correo es una credencial nueva, y lo que la acota son reglas pequeñas que se caen sin ruido:
# que el código caduque, se gaste y muera al quinto intento; que su huella lleve clave y correo; que pedirlo tenga techo
# por IP y por correo; que el correo salga TRAS la respuesta y no por la cola; que verificar comparta los cubos de la
# contraseña; que el código no quede en la copia del registro ni en el rastro; que la supresión se lleve las filas; y que
# el dispositivo recordado 90 días siga siendo una credencial que la palanca única alcanza (`RGPD-06`). Cada mutación
# quita una y un test tiene que morir.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la mutación SE APLICÓ ·
# restaurar por COPIA DE SEGURIDAD —por RUTA completa, nunca por nombre— y no con `git checkout` (`#181`).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

SAIL="docker compose exec -u sail -T laravel.test"
TESTS="$SAIL php artisan test --filter=AuthCodeTest|LoginCodesTest|RememberedDeviceTest|MeConfirmationCodeTest|SessionBindingTest|MePendingEmailCodeTest|EmailChangeRecipientsTest|MeCredentialsTest|MePrivacyTest|CustomerRegistrarTest"

CODES=app/Domain/Identity/Services/LoginCodes.php
LOGIN=app/Domain/Identity/Services/EmailCodeLogin.php
CODEMAIL=app/Domain/Identity/Services/CodeMail.php
COPY=app/Domain/Platform/Listeners/RecordEmailSend.php
USER=app/Domain/Identity/Models/User.php
BOOT=bootstrap/app.php
SESSION=app/Http/Controllers/Api/V1/AuthSessionController.php
SIGNUP=app/Http/Controllers/Api/V1/AuthRegistrationController.php
AUTHCONF=config/auth.php
# La A2a (`#855`)
CREDS=app/Domain/Identity/Services/AccountCredentials.php
BINDING=app/Domain/Identity/Services/SessionBinding.php
PROVIDER=app/Providers/AppServiceProvider.php
VERDICTS=app/Http/Api/Concerns/TranslatesCredentialVerdicts.php
WEBOUT=app/Http/Controllers/Auth/LogoutController.php
CONFIRMMAIL=app/Notifications/ConfirmationCode.php
# La A2b (`#856`)
PROFILE=app/Domain/Identity/Services/AccountProfile.php
PENDINGMAIL=app/Notifications/VerifyPendingEmail.php
# La A3 (`#857`)
REMEMBER=app/Domain/Identity/Services/RememberedDevice.php
# La A5c (`#869`)
COUNTER=app/Domain/Identity/Services/CustomerRegistrar.php

TMP="$(mktemp -d)"
FICHEROS=("$CODES" "$LOGIN" "$CODEMAIL" "$COPY" "$USER" "$BOOT" "$SESSION" "$SIGNUP" "$AUTHCONF" "$CREDS" "$BINDING" "$PROVIDER" "$VERDICTS" "$WEBOUT" "$CONFIRMMAIL" "$PROFILE" "$PENDINGMAIL" "$REMEMBER" "$COUNTER")
copia() { echo "$TMP/${1//\//__}"; }
restaurar() { for f in "${FICHEROS[@]}"; do cp "$(copia "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$(copia "$f")"; done

verde() { $TESTS >/dev/null 2>&1; }

if ! verde; then
    echo '✗ los tests del acceso con código NO están verdes antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde'

muerden=0; total=0

# $1 nombre · $2 fichero · $3 buscar · $4 poner · $5 veces (1 por defecto; 0 = todas)
mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4" veces="${5:-1}"
    total=$((total + 1))
    python3 -c 'import sys; p=sys.argv[1]; n=int(sys.argv[4]); s=open(p,encoding="utf-8").read(); open(p,"w",encoding="utf-8").write(s.replace(sys.argv[2], sys.argv[3], n if n > 0 else -1))' \
        "$fichero" "$buscar" "$poner" "$veces"
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

# ── El código (`LoginCodes`) ───────────────────────────────────────────────────────────────────
mutar "el código no caduca" "$CODES" \
  "            ->where('expires_at', '>', now())
" "" 0

mutar "un código se usa dos veces" "$CODES" \
  "        return LoginCode::query()
            ->whereKey(\$live->getKey())
            ->whereNull('used_at')
            ->update(['used_at' => now()]) === 1;" \
  "        return true;"

# La que SOLO caza la prueba del hueco (`test_two_uses_in_the_gap…`): el segundo uso SEGUIDO lo sigue parando la lectura;
# dos a la vez, no. La carrera real contra MySQL no la discrimina (su control también da un solo 201).
mutar "dos usos a la vez entran los dos (el intento y el uso sin condicionar)" "$CODES" \
  "            ->whereKey(\$live->getKey())
            ->whereNull('used_at')
" "            ->whereKey(\$live->getKey())
" 0

mutar "los intentos no tienen tope" "$CODES" \
  "            ->where('attempts', '<', self::MAX_ATTEMPTS)
" "" 0

mutar "pedir otro no anula el anterior" "$CODES" \
  "            LoginCode::query()->where('email', \$email)->where('purpose', \$purpose)->delete();
" ""

mutar "la huella no lleva el correo" "$CODES" \
  "hash_hmac('sha256', \$purpose.'|'.\$email.'|'.\$code, (string) config('app.key'))" \
  "hash_hmac('sha256', \$purpose.'|'.\$code, (string) config('app.key'))"

mutar "la huella no lleva clave (un millón de candidatos, en un instante)" "$CODES" \
  "hash_hmac('sha256', \$purpose.'|'.\$email.'|'.\$code, (string) config('app.key'))" \
  "hash('sha256', \$purpose.'|'.\$email.'|'.\$code)"

mutar "la tabla guarda el código en claro" "$CODES" \
  "'code_hash' => self::fingerprint(\$email, \$purpose, \$code)," \
  "'code_hash' => str_pad(\$code, 64, '0'),"

# ── La puerta y verificar (`EmailCodeLogin`) ────────────────────────────────────────────────────
mutar "la puerta no tiene techo por IP (el barrido de correos pasa entero)" "$LOGIN" \
  "        if (RateLimiter::tooManyAttempts(\$ipKey, self::MAX_PER_IP)) {" \
  "        if (false) {"

mutar "un correo recibe códigos en ráfaga (sin el de un minuto)" "$LOGIN" \
  "RateLimiter::tooManyAttempts(\$minuteKey, self::MAX_PER_EMAIL_PER_MINUTE)
            || " ""

mutar "el buzón de la víctima no tiene techo por hora" "$LOGIN" \
  "
            || RateLimiter::tooManyAttempts(\$hourKey, self::MAX_PER_EMAIL_PER_HOUR)" ""

# El envío vive en `CodeMail` desde la A2a (extraído en la segunda copia): los dos mutantes, re-apuntados allí.
mutar "el correo sale DURANTE la respuesta (el SMTP hace esperar)" "$CODEMAIL" \
  "        defer(static function () use (\$user, \$mail): void {" \
  "        call_user_func(static function () use (\$user, \$mail): void {"

mutar "el correo espera a la cola (hasta 60 s en producción)" "$CODEMAIL" \
  "\$user->notifyNow(\$mail);" \
  "\$user->notify(\$mail);"

mutar "verificar con cubos propios (intentos gratis además de los de la puerta)" "$LOGIN" \
  "\$result = \$this->gate->guarded(\$email, \$ip, 'auth.code_login', function (string \$email) use (\$code): ?User {" \
  "\$result = (static fn (string \$email, \\Closure \$check): LoginResult => (\$u = \$check(Str::lower(trim(\$email)))) ? LoginResult::success(\$u) : LoginResult::invalidCredentials())(\$email, function (string \$email) use (\$code): ?User {"

mutar "un código bueno no confirma el correo" "$LOGIN" \
  "        if (\$user !== null && ! \$user->hasVerifiedEmail()) {" \
  "        if (false) {"

mutar "el rastro lleva el correo" "$LOGIN" \
  "Log::info('auth.code_requested', ['user_id' => \$user->id, 'ip' => \$ip]);" \
  "Log::info('auth.code_requested', ['user_id' => \$user->id, 'email' => \$email, 'ip' => \$ip]);"

# ── Lo que no se guarda ─────────────────────────────────────────────────────────────────────────
mutar "la copia del registro de correos guarda el código" "$COPY" \
  "'html' => self::hide(\$this->htmlOf(\$email), \$secrets)," \
  "'html' => \$this->htmlOf(\$email),"

mutar "la supresión deja los códigos del titular" "$USER" \
  "            LoginCode::query()->whereIn('email', array_values(array_filter([\$originalEmail, (string) \$this->pending_email])))->delete();
" ""

# ── El dispositivo recordado y la palanca (`RGPD-06`) ───────────────────────────────────────────
mutar "cerrar las demás sesiones no toca las cookies de recuerdo (el hueco de antes)" "$USER" \
  "        \$this->forgetRememberedDevices(keepCurrent: true);
" ""

mutar "cerrar las demás sesiones echa también a quien lo pide" "$USER" \
  "            RememberedDevice::keepCurrent(\$this);
" ""

mutar "la palanca de «me han entrado» deja cookies de recuerdo vivas" "$USER" \
  "        \$this->forgetRememberedDevices(keepCurrent: false);
" ""

mutar "una cuenta sin contraseña pasa NULL al hash del recuerdo" "$USER" \
  "    public function getAuthPassword(): string
    {
        return (string) \$this->password;
    }
" ""

mutar "la cookie de recuerdo no se alarga con el uso" "$BOOT" \
  "            RefreshRememberedDevice::class,
" ""

mutar "el recuerdo dura lo de Laravel (400 días) y no 90" "$AUTHCONF" \
  "            'remember' => RememberedDevice::MINUTES,
" ""

mutar "salir en un dispositivo echa a todos los demás" "$SESSION" \
  "RememberedDevice::logOutHere(\$request);" \
  "Auth::guard('web')->logout();"

mutar "salir rota el token aunque se salga por la pieza común" "$REMEMBER" \
  "\$guard->logoutCurrentDevice();" \
  "\$guard->logout();"

mutar "salir deja la cookie de recuerdo en la petición (y se vuelve a entrar con ella)" "$REMEMBER" \
  "        \$request->cookies->remove(\$guard->getRecallerName());
" ""

# `#858` (el owner): recordado SOLO si se pide —la casilla «Mantener la sesión iniciada»—; el alta, nunca.
mutar "con la casilla, entrar con el código no recuerda el dispositivo" "$SESSION" \
  "Auth::guard('web')->login(\$user, remember: (bool) (\$credentials['remember'] ?? false));" \
  "Auth::guard('web')->login(\$user, remember: false);"

mutar "sin pedirlo, entrar con el código recuerda igual (la cookie persistente sin consentimiento)" "$SESSION" \
  "Auth::guard('web')->login(\$user, remember: (bool) (\$credentials['remember'] ?? false));" \
  "Auth::guard('web')->login(\$user, remember: true);"

mutar "el alta recuerda el dispositivo sin que nadie lo pida" "$SIGNUP" \
  "            Auth::login(\$result->user);
            \$request->session()->regenerate();" \
  "            Auth::login(\$result->user, remember: true);
            \$request->session()->regenerate();"

# ── A2a · reconfirmar con un código (`#855`; desde la A5, `#869`, la única forma) ───────────────
mutar "un código de ENTRAR confirma acciones sensibles" "$CREDS" \
  "if (! \$this->codes->consume((string) \$user->email, LoginCode::PURPOSE_CONFIRM, \$code)) {" \
  "if (! \$this->codes->consume((string) \$user->email, LoginCode::PURPOSE_LOGIN, \$code)) {"

mutar "un código fallido no cuenta en el limitador (intentos gratis sobre una sesión robada)" "$CREDS" \
  "            RateLimiter::hit(\$key, self::WINDOW);

            return CredentialChangeResult::wrongCode();" \
  "            return CredentialChangeResult::wrongCode();"

mutar "el código de confirmar no tiene techo (el buzón del dueño, lleno)" "$CREDS" \
  "        if (RateLimiter::tooManyAttempts(\$minuteKey, EmailCodeLogin::MAX_PER_EMAIL_PER_MINUTE)
            || RateLimiter::tooManyAttempts(\$hourKey, EmailCodeLogin::MAX_PER_EMAIL_PER_HOUR)) {" \
  "        if (false) {"

mutar "el correo del código no dice para qué es" "$CONFIRMMAIL" \
  "            ->line(__('emails.confirmation_code.for', ['action' => __('emails.confirmation_code.actions.'.\$this->action)]))
" ""

mutar "el 422 de un código malo no va sobre \`code\` (la pantalla no sabe dónde pintarlo)" "$VERDICTS" \
  "fields: ['code' => [__('api.confirm.wrong_code')]]" \
  "fields: ['current_password' => [__('api.confirm.wrong_code')]]"

# ── A2a · la revocación que no depende del driver (`RGPD-06`) ───────────────────────────────────
mutar "la sesión no se ata al entrar" "$PROVIDER" \
  "        Event::listen(Login::class, BindSessionOnLogin::class);
" ""

mutar "nadie mira si la sesión sigue atada (la API)" "$BOOT" \
  "            EnsureFrontendRequestsAreStateful::class,
            EnsureSessionIsCurrent::class," \
  "            EnsureFrontendRequestsAreStateful::class,"

mutar "nadie mira si la sesión sigue atada (la web)" "$BOOT" \
  "            EnsureSessionIsCurrent::class,
            SetLocale::class," \
  "            SetLocale::class,"

mutar "en la web se autentica ANTES de mirar la atadura (el reordenado por prioridad)" "$BOOT" \
  "        \$middleware->prependToPriorityList(AuthenticatesRequests::class, EnsureSessionIsCurrent::class);
" ""

mutar "la sesión desatada se cierra en el guard web y la caché de sanctum la deja dentro" "$BINDING" \
  "        Auth::forgetGuards();
" ""

mutar "una sesión de antes de la atadura no se ata nunca (no se podría cerrar)" "$BINDING" \
  "        if (! is_string(\$bound)) {
            self::bind(\$session, \$user);

            return;
        }" \
  "        if (! is_string(\$bound)) {
            return;
        }"

mutar "cerrar las demás echa también la sesión de quien lo pide" "$USER" \
  "            SessionBinding::rebindCurrent(\$this);
" ""

mutar "salir en la web rota el token y echa a todos los dispositivos" "$WEBOUT" \
  "RememberedDevice::logOutHere(\$request);" \
  "Auth::guard('web')->logout();"

# ── A2b · el correo nuevo, con un código a ESE buzón (`#856`) ───────────────────────────────────
mutar "los correos del cambio salen al correo de la cuenta (el defecto de siempre)" "$USER" \
  "        return \$notification instanceof ChoosesRecipient ? \$notification->recipientFor(\$this) : \$this->email;" \
  "        return \$this->email;"

mutar "el aviso al buzón viejo pierde el correo viejo en la cola" "$PROFILE" \
  "new EmailChangeCompleted(self::maskEmail(\$newEmail), \$previousEmail)" \
  "new EmailChangeCompleted(self::maskEmail(\$newEmail))"

mutar "el correo nuevo se confirma con un código de otro propósito" "$PROFILE" \
  "\$this->codes->consume((string) \$user->pending_email, LoginCode::PURPOSE_NEW_EMAIL, \$code)" \
  "\$this->codes->consume((string) \$user->pending_email, LoginCode::PURPOSE_LOGIN, \$code)"

mutar "confirmar el correo nuevo no tiene limitador" "$PROFILE" \
  "        if (RateLimiter::tooManyAttempts(\$key, self::MAX_CONFIRM_ATTEMPTS)) {" \
  "        if (false) {"

mutar "el código del correo nuevo espera a la cola" "$PROFILE" \
  "        CodeMail::sendAfterResponse(\$user, new VerifyPendingEmail(\$code));" \
  "        \$user->notify(new VerifyPendingEmail(\$code));"

mutar "reenviar no manda un código nuevo" "$PROFILE" \
  "        \$user->forceFill(['pending_email_sent_at' => now()])->save();
        \$this->sendNewEmailCode(\$user, \$ip);" \
  "        \$user->forceFill(['pending_email_sent_at' => now()])->save();"

mutar "reenviar no tiene techo por hora (el buzón de un tercero, lleno)" "$PROFILE" \
  " || RateLimiter::tooManyAttempts(\$hourKey, EmailCodeLogin::MAX_PER_EMAIL_PER_HOUR)" ""

mutar "la copia del registro guarda el código del correo nuevo" "$PENDINGMAIL" \
  "        return [LoginCodes::shown(\$this->code)];" \
  "        return [];"

# ── Z6g·1a · «482-913»: una sola forma de enseñar el código, y la que el servidor acepta (`#867`) ────────────
mutar "los correos enseñan el código sin guion (el aviso del móvil dice «482 913»)" "$CODES" \
  "        return substr(\$code, 0, 3).'-'.substr(\$code, 3);" \
  "        return substr(\$code, 0, 3).' '.substr(\$code, 3);"

mutar "el guion de los correos no se acepta al escribirlo" "$CODES" \
  "preg_replace('/[\\s\\-]+/u', '', \$code)" \
  "preg_replace('/[\\s]+/u', '', \$code)"

# ── A5c · el mostrador, sin contraseña (`#869`) ───────────────────────────────────────────────────
mutar "el mostrador vuelve a fabricar una contraseña (la que viajaba en claro en la bienvenida)" "$COUNTER" \
  "                'password' => null," \
  "                'password' => Str::password(12),"

echo
echo "mutaciones que muerden: ${muerden}/${total}"
if [ "$muerden" -eq "$total" ]; then
    exit 0
fi
exit 1
