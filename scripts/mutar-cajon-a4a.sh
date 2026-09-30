#!/usr/bin/env bash
# Arnés de mutación de la A4a — EL CAJÓN ENTRA Y CREA CUENTA CON UN CÓDIGO (`[DECIDIDO owner]` `#848`/`#849`/`#858`;
# `specs/acceso-con-codigo.md` §4.11): la puerta (el correo decide; el tope del correo lleva al código sin aviso), entrar con
# el código (al correo al que FUE, recortado, «recordar» solo marcado), sus «no» (el código mal escrito bajo su campo, la
# espera del limitador), la cara en el store (la espera de «Pedir otro código», «Cambiar el correo», salir de la pantalla
# como PII), el alta sin contraseña, las puertas por URL (`/registro` y `/recuperar-contrasena` abren la puerta), lo que
# viaja en el montaje (recuperar solo con sesión, `auth` podado) y los árboles congelados de la puerta y del alta.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la mutación SE APLICÓ y que
# su ancla es ÚNICA · restaurar por COPIA DE SEGURIDAD y `touch`, no con `git checkout` (`#181`) · copia por RUTA · al salir,
# el bundle SSR se reconstruye (el último juez lo dejó construido con una mutación dentro).
#
# ⚠️ Lo que NO juzga (sin guarda en la suite; lo mira la sonda del navegador, `scripts/sonda-cajon-a4a.mjs`): el cableado de
# las zonas y del paso 5 (`LoginZone`, `RegisterZone`, `PurchaseSection`: qué acción dispara cada evento).
#
#   bash scripts/mutar-cajon-a4a.sh                     (todas, ~12 min)
#   SOLO='código' bash scripts/mutar-cajon-a4a.sh       (las que llevan eso en su nombre)
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

EXEC="docker compose exec -u sail -T laravel.test"
JS="$EXEC node --test resources/js/sidebar/login.test.js resources/js/sidebar/stores/auth.test.js resources/js/sidebar/register.test.js resources/js/sidebar/account/navigation.test.js resources/js/sidebar/stores/account.test.js resources/js/sidebar/code-input.test.js"
PHP='SidebarMountTest|SidebarBootTest|AccountAccessTest|SidebarAuthScreensTest|GoogleSignInPlacementTest|SidebarVerifyScreenTest'

TMP="$(mktemp -d)"
FICHEROS=(
    resources/js/sidebar/login.js
    resources/js/sidebar/stores/auth.js
    resources/js/sidebar/register.js
    resources/js/sidebar/account/navigation.js
    resources/js/sidebar/stores/account.js
    resources/js/sidebar/code-input.js
    resources/js/sidebar/steps/CodeInput.vue
    resources/js/sidebar/steps/EntryForm.vue
    resources/js/sidebar/steps/IdentifyStep.vue
    resources/js/sidebar/steps/RegisterForm.vue
    app/Http/Sidebar/AccountDoor.php
    app/Http/Sidebar/SidebarBoot.php
)
copia() { echo "$TMP/${1//\//__}"; }
restaurar() { for f in "${FICHEROS[@]}"; do cp "$(copia "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"; $EXEC npm run build:ssr >/dev/null 2>&1' EXIT
for f in "${FICHEROS[@]}"; do cp -p "$f" "$(copia "$f")"; done

verde_js()  { $JS >/dev/null 2>&1; }
verde_php() { $EXEC php artisan test --filter="$PHP" >/dev/null 2>&1; }
verde_ssr() { $EXEC npm run build:ssr >/dev/null 2>&1 && $EXEC php artisan test --filter=SidebarDomContractTest >/dev/null 2>&1; }

if ! verde_js || ! verde_php || ! verde_ssr; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde (node, PHP y el contrato del DOM)'

muerden=0; total=0

# mutar <juez: js|php|ssr> <nombre> <fichero> <buscar> <poner>
mutar() {
    local juez="$1" nombre="$2" fichero="$3" buscar="$4" poner="$5"
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
    if "verde_$juez"; then
        echo "  ✗ NO muerde: $nombre"
    else
        echo "  ✓ muerde:    $nombre"
        muerden=$((muerden + 1))
    fi
    cp "$(copia "$fichero")" "$fichero"; touch "$fichero"
}

LOGIN=resources/js/sidebar/login.js
AUTH=resources/js/sidebar/stores/auth.js
REG=resources/js/sidebar/register.js
NAV=resources/js/sidebar/account/navigation.js
CUENTA=resources/js/sidebar/stores/account.js
REGLA=resources/js/sidebar/code-input.js
CASILLAS=resources/js/sidebar/steps/CodeInput.vue
ENTRY=resources/js/sidebar/steps/EntryForm.vue
IDENT=resources/js/sidebar/steps/IdentifyStep.vue
ALTA=resources/js/sidebar/steps/RegisterForm.vue
DOOR=app/Http/Sidebar/AccountDoor.php
BOOT=app/Http/Sidebar/SidebarBoot.php

# ── La puerta, entrar con el código y sus «no» (`login.js`) ────────────────────────────────────────
mutar js "la puerta manda el correo sin recortar" "$LOGIN" \
  "api.post('/auth/code', { email: String(email ?? '').trim() });" "api.post('/auth/code', { email: String(email ?? '') });"
mutar js "el tope del correo (429 con next) ya no lleva al código" "$LOGIN" \
  "const next = response.ok ? response.data?.next : response.error?.params?.next;" "const next = response.ok ? response.data?.next : null;"
mutar js "un next que no es del contrato se da por bueno" "$LOGIN" \
  "if (next === NEXT_CODE || next === NEXT_REGISTER) {" "if (next) {"
mutar js "el código mal escrito se avisa bajo el correo" "$LOGIN" \
  "fields: { code: t(account, 'login.code_wrong') } };" "fields: { email: t(account, 'login.code_wrong') } };"
mutar js "el código viaja sin recortar" "$LOGIN" \
  "        code: String(code ?? '').trim()," "        code: String(code ?? ''),"
mutar js "el dispositivo se recuerda con cualquier valor que parezca verdad" "$LOGIN" \
  "        remember: remember === true," "        remember: Boolean(remember),"
mutar js "el limitador de la IP pierde su espera" "$LOGIN" \
  "return { global: tp(auth, 'throttle', { seconds: response?.error?.params?.retry_after ?? 0 }), fields: {} };" \
  "return { global: tp(auth, 'throttle', { seconds: 0 }), fields: {} };"

# ── La cara de la puerta en el store (`stores/auth.js`) ────────────────────────────────────────────
mutar js "la casilla de recordar nace marcada" "$AUTH" \
  "    email: '', code: '', remember: false," "    email: '', code: '', remember: true,"
mutar js "Continuar dos veces manda dos códigos" "$AUTH" \
  $'        async requestCode({ api, messages, auth, account }) {\n            if (this.busy) {' \
  $'        async requestCode({ api, messages, auth, account }) {\n            if (false) {'
mutar js "Pedir otro código se ofrece durante la espera" "$AUTH" \
  "if (this.busy || this.codeWait > 0 || this.codeSentTo === '') {" "if (this.busy || this.codeSentTo === '') {"
mutar js "el reenvío no dice otro" "$AUTH" \
  "                    this.showCode(email, true);" "                    this.showCode(email, false);"
mutar js "entrar manda el código al correo del campo y no al que se envió" "$AUTH" \
  "email: this.codeSentTo, code: this.form.code," "email: this.form.email, code: this.form.code,"
mutar js "la espera no arranca al mandar el código" "$AUTH" \
  $'            this.startCodeWait();\n' ""
mutar js "Cambiar el correo deja el código escrito" "$AUTH" \
  $'            this.codeResent = false;\n            this.form.code = \'\';\n            this.loginError = NO_LOGIN_ERROR();' \
  $'            this.codeResent = false;\n            this.loginError = NO_LOGIN_ERROR();'
mutar js "Cambiar el correo no para el reloj" "$AUTH" \
  $'            this.registerError = NO_REGISTER_ERROR();\n            this.stopCodeWait();' \
  $'            this.registerError = NO_REGISTER_ERROR();'
mutar js "salir de la pantalla no vuelve a la primera cara" "$AUTH" \
  $'            this.resendArmed = false;\n            this.stage = STAGE_EMAIL;' $'            this.resendArmed = false;'
mutar js "salir de la pantalla deja el código a medio escribir" "$AUTH" \
  $'            this.codeResent = false;\n            this.form.code = \'\';\n            this.stopCodeWait();' \
  $'            this.codeResent = false;\n            this.stopCodeWait();'
mutar js "salir de la pantalla no para el reloj" "$AUTH" \
  $'            this.form.code = \'\';\n            this.stopCodeWait();\n        },' $'            this.form.code = \'\';\n        },'
mutar js "un no al código deja el malo escrito" "$AUTH" \
  "                if (result.errors?.fields?.code) this.form.code = '';" ""
mutar js "un corte de red vacía el código" "$AUTH" \
  "                if (result.errors?.fields?.code) this.form.code = '';" "                if (true) this.form.code = '';"

# ── La regla del `CodeInput` del diseño (`code-input.js`) ─────────────────────────────────────────
mutar js "pegar el código con guion deja cinco cifras" "$REGLA" \
  "String(raw ?? '').replace(/\\D/g, '')" "String(raw ?? '').replace(/ /g, '')"
mutar js "más de seis cifras pasan" "$REGLA" \
  ".replace(/\\D/g, '').slice(0, CODE_LENGTH);" ".replace(/\\D/g, '');"
mutar js "el mismo código completo vuelve a avisar" "$REGLA" \
  "return digits.length === CODE_LENGTH && digits !== lastAnnounced;" "return digits.length === CODE_LENGTH;"
mutar js "la espera pierde su cero" "$REGLA" \
  "String(s % 60).padStart(2, '0')" "String(s % 60)"

# ── El alta sin contraseña y la zona que ya no existe ───────────────────────────────────────────────
mutar js "el alta vuelve a mandar la contraseña" "$REG" \
  $'        ...bornOnField(form),\n' $'        ...bornOnField(form),\n        password: form?.password ?? \'\',\n'
mutar js "crear cuenta vuelve a ser una zona" "$NAV" \
  "    FORGOT: 'forgot'," $'    FORGOT: \'forgot\',\n    REGISTER: \'register\','
# Los cinco CTA de alta de la landing piden `register` desde fuera: sin el alias, un invitado cae en el índice en blanco.
mutar js "el nombre register ya no lleva a la puerta" "$NAV" \
  "const RETIRED_ZONES = { register: ZONES.LOGIN };" "const RETIRED_ZONES = {};"
mutar js "abrir desde fuera se salta el alias de las zonas retiradas" "$CUENTA" \
  "            const target = zoneFor(zone);" "            const target = zone;"

# ── Las puertas por URL y lo que viaja en el montaje (PHP) ─────────────────────────────────────────
mutar php "/recuperar-contrasena vuelve a abrir la zona de recuperar" "$DOOR" \
  "        'password.request' => 'login'," "        'password.request' => 'forgot',"
mutar php "/registro abre una zona que ya no existe" "$DOOR" \
  "        'registro' => 'login'," "        'registro' => 'register',"
mutar php "recuperar la contraseña vuelve a viajar sin sesión" "$BOOT" \
  "                ...(\$withGoogleSignup ? ['google' => __('account.google')] : [])," \
  "                'forgot' => __('account.forgot'), ...(\$withGoogleSignup ? ['google' => __('account.google')] : []),"
mutar php "con sesión no viajan los textos de recuperar" "$BOOT" \
  "                'forgot' => __('account.forgot')," ""
mutar php "auth vuelve a viajar entero" "$BOOT" \
  "            'auth' => Arr::only(__('auth'), ['throttle'])," "            'auth' => __('auth'),"
mutar php "el alta vuelve a llevar la contraseña en el montaje" "$BOOT" \
  "                    'title', 'subtitle', 'name', 'email'," "                    'title', 'subtitle', 'name', 'email', 'password',"
mutar php "la pista del código viaja sin componer" "$BOOT" \
  "__('account.login.code_hint', ['site' => Setting::businessName(), 'minutes' => LoginCodes::TTL_MINUTES])" \
  "__('account.login.code_hint')"

# ── Los árboles congelados de la puerta y del alta (SSR + `SidebarDomContractTest`) ─────────────────
mutar ssr "el aviso del código pierde su role=status" "$ENTRY" \
  '<span role="status">{{ translateWith(account, resent' '<span>{{ translateWith(account, resent'
mutar ssr "Entrar se enciende antes de las seis cifras" "$ENTRY" \
  ':disabled="submitting || ! complete"' ':disabled="submitting"'
mutar ssr "Cambiar el correo se va de la cara del código" "$ENTRY" \
  $'                    <button type="button" class="auth__link" @click="$emit(\'change-email\')">{{ a(\'login.change_email\') }}</button>\n' ""
mutar ssr "Google sale también en la cara del código" "$ENTRY" \
  "<GoogleButton v-if=\"stage !== 'code'\"" "<GoogleButton"
mutar ssr "Reenviar el código se ofrece durante la espera" "$CASILLAS" \
  '<span v-if="wait > 0">{{ waitLabel }}</span>' '<span v-if="false">{{ waitLabel }}</span>'
mutar ssr "las casillas pierden el guion entre los dos grupos" "$CASILLAS" \
  '<span v-if="i === 4" class="code-input__dash"' '<span v-if="false" class="code-input__dash"'
mutar ssr "la pista del código no se pinta" "$CASILLAS" \
  '<span v-else-if="hint" :id="message" class="form__hint">' '<span v-else-if="false" :id="message" class="form__hint">'
mutar ssr "la cara del código pinta el alta" "$IDENT" \
  "v-if=\"stage === 'register'\"" "v-if=\"stage !== 'email'\""
mutar ssr "el alta pierde Cambiar el correo" "$ALTA" \
  $'                    <button type="button" class="auth__link" @click="$emit(\'change-email\')">{{ a(\'login.change_email\') }}</button>\n' ""

echo
echo "$muerden/$total muerden"
[[ $muerden -eq $total ]]
