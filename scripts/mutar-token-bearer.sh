#!/usr/bin/env bash
# Arnés de mutación del EMISOR DE TOKENS BEARER (F4, `docs/specs/token-bearer.md` §6, `DECISIONES #630`).
#
# Un token es una credencial nueva, y lo que la acota son reglas pequeñas que se caen sin ruido: que la
# puerta nueva comparta los dos limitadores del login, que el token nazca con UNA ability y con caducidad,
# que la superficie EXIJA esa ability, que cada cuenta tenga tope, que rotar mate al anterior, que la
# respuesta no se cachee y que el rastro no lleve PII. Cada mutación quita una y un test tiene que morir.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la
# mutación SE APLICÓ · restaurar por COPIA DE SEGURIDAD y no con `git checkout` (`#181`).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

SAIL="docker compose exec -u sail -T laravel.test"
TESTS="$SAIL php artisan test --filter=AuthTokenTest|ApiTokenAbilityTest"

LOGIN=app/Domain/Identity/Services/PasswordLogin.php
ISSUER=app/Domain/Identity/Services/ApiTokenIssuer.php
USER=app/Domain/Identity/Models/User.php
ROUTES=routes/api.php
BOOT=bootstrap/app.php
BASE=tests/TestCase.php

TMP="$(mktemp -d)"
FICHEROS=("$LOGIN" "$ISSUER" "$USER" "$ROUTES" "$BOOT" "$BASE")
restaurar() { for f in "${FICHEROS[@]}"; do cp "$TMP/$(basename "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$TMP/$(basename "$f")"; done

verde() { $TESTS >/dev/null 2>&1; }

if ! verde; then
    echo '✗ los tests del token NO están verdes antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde'

muerden=0; total=0

mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4"
    total=$((total + 1))
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

# ── La puerta nueva no es una segunda oportunidad (`SEC-06`) ───────────────────────────────────
mutar "un fallo deja de contar en el cubo (correo, IP)" "$LOGIN" \
  "            RateLimiter::hit(\$compositeKey, self::WINDOW);
" ""

mutar "el limitador por IP sola deja de bloquear (el barrido pasa entero)" "$LOGIN" \
  "
            || RateLimiter::tooManyAttempts(\$ipKey, self::MAX_ATTEMPTS_PER_IP)" ""

mutar "verificar ABRE sesión (\`attempt\` en vez de \`validate\`)" "$LOGIN" \
  "Auth::guard('web')->validate(['email' => \$email, 'password' => \$password])" \
  "Auth::guard('web')->attempt(['email' => \$email, 'password' => \$password])"

# ── Con qué nace un token ──────────────────────────────────────────────────────────────────────
mutar "el token nace con el comodín en vez de con su ability" "$ISSUER" \
  "createToken(\$deviceName, [self::ABILITY], \$this->expiresAt())" \
  "createToken(\$deviceName, ['*'], \$this->expiresAt())"

mutar "el token nace SIN caducidad propia" "$ISSUER" \
  "createToken(\$deviceName, [self::ABILITY], \$this->expiresAt())" \
  "createToken(\$deviceName, [self::ABILITY])"

mutar "sin configuración, el token nace ya caducado (se pierde la caducidad de reserva)" "$ISSUER" \
  "\$minutes > 0 ? \$minutes : self::FALLBACK_LIFETIME" \
  "\$minutes"

mutar "el tope por cuenta desaparece" "$ISSUER" \
  "            \$user->revokeStalestTokens(self::MAX_TOKENS);
" ""

mutar "el tope retira el más ANTIGUO en vez del más olvidado" "$USER" \
  "->orderByRaw('COALESCE(last_used_at, created_at) DESC')" \
  "->orderByRaw('created_at DESC')"

mutar "rotar deja vivo el token anterior" "$ISSUER" \
  "            \$user->revokeCurrentAccessToken();
" ""

mutar "el rastro lleva el correo del titular" "$ISSUER" \
  "Log::info('auth.token_issued', ['user_id' => \$user->id, 'ip' => \$ip]);" \
  "Log::info('auth.token_issued', ['user_id' => \$user->id, 'email' => \$user->email, 'ip' => \$ip]);"

# ── La superficie ──────────────────────────────────────────────────────────────────────────────
mutar "el grupo autenticado deja de exigir la ability" "$ROUTES" \
  "Route::middleware(['auth:sanctum', \$tokenAbility])->group(" \
  "Route::middleware(['auth:sanctum'])->group("

mutar "la respuesta con el token se puede cachear (sin \`no-store\`)" "$ROUTES" \
  "        ->middleware('no-store')
        ->name('auth.tokens.issue');" \
  "        ->name('auth.tokens.issue');"

mutar "el alias \`abilities\` desaparece del arranque" "$BOOT" \
  "            'abilities' => CheckAbilities::class,
" ""

# ── La red de la suite: `actingAs()` sobre `sanctum` se parece al guard real ───────────────────
mutar "\`TestCase::be()\` deja de adjuntar el \`TransientToken\` (24 tests caían con un 401 de mentira)" "$BASE" \
  "            \$user->withAccessToken(new TransientToken);
" ""

echo
echo "mutaciones que muerden: ${muerden}/${total}"
if [ "$muerden" -eq "$total" ]; then
    exit 0
fi
exit 1
