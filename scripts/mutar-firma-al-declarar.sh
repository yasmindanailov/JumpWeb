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

PHPT='SidebarDependentWaiverOfferTest|MeDependentWaiverTest|DependentWaiverAtSignupTest|DependentPrivacyTest'
RUN_PHP="docker compose exec -u sail -T laravel.test php artisan test --filter=${PHPT}"
RUN_JS="docker compose exec -u sail -T laravel.test node --test resources/js/sidebar/account/dependents.test.js"

TMP="$(mktemp -d)"
FICHEROS=(
    resources/js/sidebar/account/dependents.js
    resources/js/sidebar/account/zones/DependentCard.vue
    resources/js/sidebar/account/zones/DependentsZone.vue
    app/Domain/Identity/Services/WaiverSigner.php
    app/Domain/Identity/Services/DependentRegistry.php
    app/Domain/Identity/Models/Dependent.php
    app/Domain/Identity/Listeners/SignPendingWaiverOnVerification.php
    app/Http/Controllers/Api/V1/MeDependentsController.php
    app/Domain/Identity/Services/CustomerAccountContext.php
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
R=app/Domain/Identity/Services/DependentRegistry.php
D=app/Domain/Identity/Models/Dependent.php
L=app/Domain/Identity/Listeners/SignPendingWaiverOnVerification.php
K=app/Http/Controllers/Api/V1/MeDependentsController.php
X=app/Domain/Identity/Services/CustomerAccountContext.php

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

# ── T1 · declarar y aceptar son UN SOLO GESTO ─────────────────────────────────────────────────
mutar "el alta deja de EXIGIR la aceptación (el encargo del owner, deshecho)" "$R" \
  '        if ($exigible && $waiver === null) {
            throw new DependentWaiverRequiredException;
        }' \
  '        if (false) {
            throw new DependentWaiverRequiredException;
        }'

mutar "se EXIGE también donde no hay nada que firmar (externo se queda sin altas)" "$R" \
  '        $exigible = WaiverSettings::isInternal()
            && LegalDocuments::latestVersionNumber(WaiverSettings::SLUG) !== null;' \
  '        $exigible = true;'

mutar "❗ FIRMA aunque el correo no esté verificado (el escenario P12)" "$R" \
  '                if ($locked->email_verified_at !== null) {' \
  '                if (true) {'

mutar "la aceptación NO se retiene: se pierde en silencio" "$R" \
  '                } else {
                    // La aceptación RETENIDA, hermana exacta de la del titular' \
  '                } elseif (false) {
                    // La aceptación RETENIDA, hermana exacta de la del titular'

mutar "verificar el correo NO sella las aceptaciones de los menores" "$L" \
  '        $this->signPendingDependents($user);' \
  '        // sin sellar'

# ⚠️ **Una mutación retirada, y por qué**: cambiar el `if ($document === null)` del listener por
# `if (false)` NO muerde, y **no es un hueco de la guarda**: el `sign()` recibiría `null`, lanzaría, y
# el `catch (Throwable)` —deliberado desde `#181`: «un fallo aquí NO puede impedir la verificación»—
# lo absorbe. El efecto observable es el MISMO (cero firmas), así que es una mutación EQUIVALENTE en
# el dominio: lo único que cambia es si el log dice `pending_dropped` o `pending_failed`. Atar un caso
# al texto de un log para matarla sería vigilar el instrumento en vez de la regla.

mutar "la pendiente NO se limpia antes de sellar (una 2.ª verificación firmaría dos veces)" "$L" \
  '            $dependent->forceFill([
                '"'"'waiver_pending_document_id'"'"' => null,
                '"'"'waiver_pending_channel'"'"' => null,
                '"'"'waiver_pending_ip'"'"' => null,
                '"'"'waiver_pending_user_agent'"'"' => null,
            ])->save();' \
  '            // sin limpiar'

mutar "retirar un menor DEJA su aceptación pendiente viva" "$D" \
  "                'waiver_pending_document_id' => null," \
  "                'waiver_pending_document_id' => \$this->waiver_pending_document_id,"

mutar "la API deja de pedir la casilla" "$K" \
  "            'accept_waiver' => \$exigible ? ['required', 'accepted'] : ['nullable', 'boolean']," \
  "            'accept_waiver' => ['nullable', 'boolean'],"

mutar "la API acepta un texto CADUCADO (no comprueba vigencia)" "$K" \
  '            $waiver = WaiverAcceptance::currentDocument((int) $data['"'"'waiver_document_id'"'"']);

            if ($waiver === null) {' \
  '            $waiver = \App\Domain\Identity\Models\LegalDocumentVersion::find((int) $data['"'"'waiver_document_id'"'"']);

            if (false) {'

# ── T2 · el aviso del ÍNDICE ──────────────────────────────────────────────────────────────────
mutar "el índice deja de mirar a los menores (el aviso no existe)" "$X" \
  "                'dependentsPending' => \$vigente !== null && \$this->hasUnsignedDependents(\$user, \$vigente)," \
  "                'dependentsPending' => false,"

mutar "el aviso ignora la VIGENCIA: una firma vieja lo apaga" "$X" \
  "                    ->where('waiver_signatures.legal_document_version_id', \$vigente->getKey());" \
  "                    ->whereNotNull('waiver_signatures.legal_document_version_id');"

mutar "el aviso cuenta también a los menores RETIRADOS" "$X" \
  '            ->active()
            ->whereNotExists(' \
  '            ->whereNotExists('

echo
echo "── Veredicto: ${muerden}/${total} mutaciones muerden ──"
[ "$muerden" -eq "$total" ]
