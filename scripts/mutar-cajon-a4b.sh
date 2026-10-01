#!/usr/bin/env bash
# Arnés de mutación de la A4b — MI CUENTA DEL CAJÓN CONFIRMA CON UN CÓDIGO (`#813`; `specs/acceso-con-codigo.md` §4.11):
# pedir el código de una acción (`account/confirm-code.js`: el tope enseña el campo con su espera, el «no» sin texto no queda
# mudo), su estado (`stores/confirm.js`: el primer toque pide y el segundo usa, las seis antes de actuar, el «no» del código
# vacía el campo, «otro» solo si salió, otra acción empieza de cero, el código del correo nuevo por su camino, la pregunta
# antes de borrar), lo que viaja a la API (`code` y nunca `current_password`), el código que se vacía al cambiar de zona,
# las zonas retiradas (`PASSWORD`, `FORGOT`, `forgot` a la puerta) y lo que viaja en el montaje con sesión.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la mutación SE APLICÓ y que
# su ancla es ÚNICA · restaurar por COPIA DE SEGURIDAD y `touch`, no con `git checkout` (`#181`) · copia por RUTA.
#
# ⚠️ Lo que NO juzga (sin guarda en la suite; lo mira la sonda del navegador, `scripts/sonda-cajon-a4b.mjs`): el cableado de
# las tres zonas y de su pieza (`SessionsZone`, `PrivacyZone`, `ProfileZone`, `ConfirmCode`: qué hace cada botón y la sexta
# cifra). Las zonas de cuenta no tienen árbol congelado: el contrato del DOM renderiza los pasos de la compra.
#
#   bash scripts/mutar-cajon-a4b.sh                     (todas)
#   SOLO='código' bash scripts/mutar-cajon-a4b.sh       (las que llevan eso en su nombre)
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

EXEC="docker compose exec -u sail -T laravel.test"
JS="$EXEC node --test resources/js/sidebar/account/confirm-code.test.js resources/js/sidebar/stores/confirm.test.js resources/js/sidebar/stores/credentials.test.js resources/js/sidebar/stores/privacy.test.js resources/js/sidebar/stores/profile.test.js resources/js/sidebar/stores/account.test.js resources/js/sidebar/account/navigation.test.js resources/js/sidebar/stores/auth.test.js"
PHP='SidebarMountTest|SidebarBootTest|SidebarAuthScreensTest'

TMP="$(mktemp -d)"
FICHEROS=(
    resources/js/sidebar/account/confirm-code.js
    resources/js/sidebar/stores/confirm.js
    resources/js/sidebar/stores/credentials.js
    resources/js/sidebar/stores/privacy.js
    resources/js/sidebar/stores/profile.js
    resources/js/sidebar/stores/account.js
    resources/js/sidebar/account/navigation.js
    app/Http/Sidebar/SidebarBoot.php
)
copia() { echo "$TMP/${1//\//__}"; }
restaurar() { for f in "${FICHEROS[@]}"; do cp "$(copia "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp -p "$f" "$(copia "$f")"; done

verde_js()  { $JS >/dev/null 2>&1; }
verde_php() { $EXEC php artisan test --filter="$PHP" >/dev/null 2>&1; }

if ! verde_js || ! verde_php; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde (node y PHP)'

muerden=0; total=0

# mutar <juez: js|php> <nombre> <fichero> <buscar> <poner>
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

PEDIR=resources/js/sidebar/account/confirm-code.js
CODIGO=resources/js/sidebar/stores/confirm.js
CRED=resources/js/sidebar/stores/credentials.js
PRIV=resources/js/sidebar/stores/privacy.js
PERFIL=resources/js/sidebar/stores/profile.js
CUENTA=resources/js/sidebar/stores/account.js
NAV=resources/js/sidebar/account/navigation.js
BOOT=app/Http/Sidebar/SidebarBoot.php

# ── Pedir el código (`account/confirm-code.js`) ────────────────────────────────────────────────────
mutar js "el código se pide a otro endpoint" "$PEDIR" \
  "api.post('/me/confirm-code', { action })" "api.post('/me/confirm', { action })"
mutar js "el código se pide sin decir para qué acción" "$PEDIR" \
  "api.post('/me/confirm-code', { action })" "api.post('/me/confirm-code', {})"
mutar js "un código que salió no se da por enviado" "$PEDIR" \
  "if (response?.ok) return { ...base, sent: true, shown: true };" "if (response?.ok) return { ...base, shown: true };"
mutar js "el tope esconde el campo (y hay uno recién enviado)" "$PEDIR" \
  "return { ...base, shown: true, error: tp(" "return { ...base, error: tp("
mutar js "el tope no dice cuánto esperar" "$PEDIR" \
  "{ n: response?.error?.params?.retry_after ?? 60 }" "{ n: 0 }"
mutar js "un no sin texto deja el aviso mudo" "$PEDIR" \
  "outcome.notice || t(messages, 'errors.try_later')" "outcome.notice"

# ── El estado (`stores/confirm.js`) ────────────────────────────────────────────────────────────────
mutar js "el botón se enciende antes de las seis" "$CODIGO" \
  "ready: (state) => codeDigits(state.code).length === CODE_LENGTH," "ready: (state) => codeDigits(state.code).length > 0,"
mutar js "el código pedido vale para cualquier acción" "$CODIGO" \
  "isShown: (state) => (action) => state.shown && state.action === action," "isShown: (state) => () => state.shown,"
mutar js "el primer toque ya no pide el código" "$CODIGO" \
  "                if (action !== NEW_EMAIL) await this.request(action, ctx);" ""
mutar js "el código del correo nuevo se pide por el endpoint de confirmar" "$CODIGO" \
  "                if (action !== NEW_EMAIL) await this.request(action, ctx);" "                await this.request(action, ctx);"
mutar js "la acción se hace con el código a medias" "$CODIGO" \
  "            if (! this.ready || this.busy) return false;" "            if (this.busy) return false;"
mutar js "el no del código deja lo escrito" "$CODIGO" \
  "if (error) Object.assign(this, { error, code: '' });" "if (error) Object.assign(this, { error });"
mutar js "el no viejo sigue a la vista mientras se prueba otro" "$CODIGO" \
  $'            this.error = \'\';\n\n            const done' $'            const done'
mutar js "salir bien esconde el código del correo nuevo" "$CODIGO" \
  "                if (this.action === action) this.reset();" "                this.reset();"
mutar js "pedir otro que no salió dice otro" "$CODIGO" \
  "if (r.sent && again) Object.assign(this, { resends: this.resends + 1, code: '' });" "if (again) Object.assign(this, { resends: this.resends + 1, code: '' });"
mutar js "otra acción hereda el contador de la anterior" "$CODIGO" \
  "Object.assign(this, { action, shown: true, resends: 0, code: '' });" "Object.assign(this, { action, shown: true, code: '' });"
mutar js "otra acción hereda el código escrito" "$CODIGO" \
  "Object.assign(this, { action, shown: true, resends: 0, code: '' });" "Object.assign(this, { action, shown: true, resends: 0 });"
mutar js "dos toques seguidos piden dos códigos" "$CODIGO" \
  "            if (this.busy) return false;" ""
mutar js "otro código del correo nuevo va por el endpoint de confirmar" "$CODIGO" \
  "(api) => api.post('/me/pending-email/resend', {})" "(api) => api.post('/me/confirm-code', { action: NEW_EMAIL })"
mutar js "otro código del correo nuevo no relee la caducidad" "$CODIGO" \
  "            if (sent) await useProfileStore().refresh(ctx);" ""
mutar js "volver a enseñar el código del nuevo borra lo escrito" "$CODIGO" \
  "            if (! this.isShown(NEW_EMAIL)) Object.assign(this, blank(), { action: NEW_EMAIL, shown: true });" \
  "            Object.assign(this, blank(), { action: NEW_EMAIL, shown: true });"
mutar js "la sexta borra sin preguntar (o pregunta a medias)" "$CODIGO" \
  "            if (this.ready) next();" "            next();"

# ── Lo que viaja a la API: el código, nunca la contraseña ──────────────────────────────────────────
mutar js "cerrar las otras manda la contraseña" "$CRED" \
  "api.post('/me/sessions/revoke-others', { code })" "api.post('/me/sessions/revoke-others', { current_password: code })"
mutar js "desvincular manda la contraseña" "$CRED" \
  "api.delete('/me/identities/' + provider, { code })" "api.delete('/me/identities/' + provider, { current_password: code })"
mutar js "borrar la cuenta manda la contraseña" "$PRIV" \
  "() => api.delete('/me', { code })," "() => api.delete('/me', { current_password: code }),"
mutar js "el perfil manda un código vacío sin cambiar el correo" "$PERFIL" \
  "    if (form?.code) body.code = form.code;" "    body.code = form?.code;"
mutar js "el perfil vuelve a mandar la contraseña" "$PERFIL" \
  "    if (form?.code) body.code = form.code;" $'    if (form?.code) body.code = form.code;\n    if (form?.currentPassword) body.current_password = form.currentPassword;'
mutar js "confirmar el correo nuevo va a otro endpoint" "$PERFIL" \
  "api.post('/me/pending-email/confirm', { code })" "api.post('/me/pending-email/verify', { code })"
mutar js "el correo ocupado se avisa bajo el correo de ahora" "$PERFIL" \
  "                this.notice = fieldError(this.fields, 'email');" ""
mutar js "releer el perfil no lo relee" "$PERFIL" \
  $'            const response = await api.get(\'/me\');\n\n            if (response.ok) this.user = response.data;\n        },\n\n        /**\n         * Guarda' \
  $'            await api.get(\'/me\');\n        },\n\n        /**\n         * Guarda'

# ── La navegación: el código es de su zona; las zonas retiradas ─────────────────────────────────────
mutar js "el código sobrevive al cambio de zona" "$CUENTA" \
  "            if (this.zone !== this.nav.zone) useConfirmStore().reset();" ""
mutar js "cambiar la contraseña vuelve a ser una zona" "$NAV" \
  "    SESSIONS: 'sessions'," $'    PASSWORD: \'password\',\n    SESSIONS: \'sessions\','
mutar js "el nombre forgot ya no lleva a la puerta" "$NAV" \
  "const RETIRED_ZONES = { register: ZONES.LOGIN, forgot: ZONES.LOGIN };" "const RETIRED_ZONES = { register: ZONES.LOGIN };"

# ── Lo que viaja en el montaje con sesión (PHP) ─────────────────────────────────────────────────────
mutar php "con sesión no viajan los textos del código de confirmar" "$BOOT" \
  "                    'confirm' => __('account.account.confirm')," ""
mutar php "con sesión vuelven a viajar los textos de recuperar" "$BOOT" \
  "                'purchases' => __('account.purchases')," $'                \'purchases\' => __(\'account.purchases\'),\n                \'forgot\' => __(\'account.reset\'),'
# (Un mutante que AÑADÍA a la lista de privacidad claves que ya estaban era equivalente —`Arr::only` no repite—: sobrevivió
# en la primera corrida y se cambió por éste, que es la guarda que la A4b añadió.)
mutar php "sesiones vuelve a llevar el rótulo de la contraseña" "$BOOT" \
  "                    'sessions' => __('account.account.sessions')," \
  "                    'sessions' => __('account.account.sessions') + ['current_password' => __('account.account.sessions.title')],"

echo
echo "$muerden/$total muerden"
[[ $muerden -eq $total ]]
