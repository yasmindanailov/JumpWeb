#!/usr/bin/env bash
# Arnés de mutación del waiver del MENOR A CARGO (`DECISIONES #441`).
#
# T0 — «la tarjeta no puede ofrecer un botón que solo puede fallar»: `WaiverSigner` exige el correo
# del titular verificado para firmar por un menor, y la tarjeta no lo miraba (409 medido).
#
# ❗ Lo más importante que protege es la ÚLTIMA mutación: que **la regla del servidor siga**. La
# primera versión del diseño proponía relajarla y la revisión adversarial reprodujo el daño —declarar
# 20 menores reales con la cuenta de un tercero y firmarlos convierte fichas borrables en PII
# indeleble—. Sin un caso que lo fije, alguien la relajará «para que la pantalla sea más cómoda».
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por CÓDIGO DE SALIDA · comprobar que la
# mutación SE APLICÓ · restaurar por COPIA y nunca con `git checkout` (`#181`).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

PHPT='SidebarDependentWaiverOfferTest|MeDependentWaiverTest'
RUN_PHP="docker compose exec -u sail -T laravel.test php artisan test --filter=${PHPT}"
RUN_JS="docker compose exec -u sail -T laravel.test node --test resources/js/sidebar/account/dependents.test.js"

TMP="$(mktemp -d)"
FICHEROS=(
    resources/js/sidebar/account/dependents.js
    resources/js/sidebar/account/zones/DependentCard.vue
    resources/js/sidebar/account/zones/DependentsZone.vue
    app/Domain/Identity/Services/WaiverSigner.php
)
restaurar() { for f in "${FICHEROS[@]}"; do cp "$TMP/$(basename "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$TMP/$(basename "$f")"; done

# ⚠️ Las dos mitades: la CONDUCTA vive en `node --test` y el CABLEADO + el dominio en PHPUnit. Una
# mutación que solo mueva el `.vue` no la ve el runner de JS, y al revés.
verde() { $RUN_JS >/dev/null 2>&1 && $RUN_PHP >/dev/null 2>&1; }

if ! verde; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde (JS + PHP)'

muerden=0; total=0

mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4"
    total=$((total + 1))
    python3 -c 'import sys; p=sys.argv[1]; s=open(p,encoding="utf-8").read(); open(p,"w",encoding="utf-8").write(s.replace(sys.argv[2], sys.argv[3], 1))' \
        "$fichero" "$buscar" "$poner"
    if cmp -s "$fichero" "$TMP/$(basename "$fichero")"; then
        echo "  ⚠ «$nombre» NO SE APLICÓ (el patrón no casa): el veredicto no vale"
        restaurar
        return
    fi
    touch "$fichero"
    if verde; then
        echo "  ✗ NO muerde: $nombre"
    else
        echo "  ✓ muerde:    $nombre"
        muerden=$((muerden + 1))
    fi
    restaurar
}

M=resources/js/sidebar/account/dependents.js
C=resources/js/sidebar/account/zones/DependentCard.vue
Z=resources/js/sidebar/account/zones/DependentsZone.vue
W=app/Domain/Identity/Services/WaiverSigner.php

# ── 1 · La decisión de QUÉ ofrecer ────────────────────────────────────────────────────────────
mutar "la acción ignora el correo verificado (el defecto original)" "$M" \
  '    return emailVerified === false ? DEPENDENT_WAIVER_VERIFY : DEPENDENT_WAIVER_SIGN;' \
  '    return DEPENDENT_WAIVER_SIGN;'

mutar "«=== false» pasa a «!»: el contexto sin cargar esconde la acción" "$M" \
  '    return emailVerified === false ? DEPENDENT_WAIVER_VERIFY : DEPENDENT_WAIVER_SIGN;' \
  '    return ! emailVerified ? DEPENDENT_WAIVER_VERIFY : DEPENDENT_WAIVER_SIGN;'

mutar "se ofrece verificar aunque NO falte ninguna firma" "$M" \
  '    if (! dependentNeedsSignature(dependent)) return null;' \
  '    if (false) return null;'

# ── 2 · El cableado ───────────────────────────────────────────────────────────────────────────
mutar "la tarjeta vuelve al predicado que no mira el correo" "$C" \
  'const action = computed(() => dependentWaiverAction(props.dependent, props.emailVerified));' \
  'const action = computed(() => (dependentNeedsSignature(props.dependent) ? DEPENDENT_WAIVER_SIGN : null));'

mutar "la tarjeta se queda MUDA para quien no puede firmar" "$C" \
  '        <p v-if="mustVerify" class="auth__sub" role="status">{{ a('"'"'account.dependents.waiver_awaiting_verification'"'"') }}</p>' \
  ''

mutar "la zona deja de pasarle el dato a la tarjeta" "$Z" \
  '                :email-verified="context.emailVerified"
' \
  ''

# ── 3 · ❗❗ Y la regla del SERVIDOR, que es lo que hace honesto el aviso ──────────────────────
mutar "WaiverSigner deja de exigir el correo verificado al menor a cargo" "$W" \
  '            $needsVerifiedEmail = $request->declaredBy === null
                && $request->subjectType !== WaiverSignature::SUBJECT_GUEST_MINOR;' \
  '            $needsVerifiedEmail = $request->declaredBy === null
                && $request->subjectType !== WaiverSignature::SUBJECT_GUEST_MINOR
                && $request->subjectType !== WaiverSignature::SUBJECT_DEPENDENT;'

echo
echo "── Veredicto: ${muerden}/${total} mutaciones muerden ──"
[ "$muerden" -eq "$total" ]
