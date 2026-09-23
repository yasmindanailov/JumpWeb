#!/usr/bin/env bash
# Arnés de mutación de la ANALÍTICA — T1, el libro de eventos
# (`specs/analitica.md` §6, `DECISIONES #678`; `RGPD-07`, `PAY-21`, `RGPD-01` ampliada).
#
# Lo que la T1 promete y que no se ve leyendo: que ningún dato personal entre en el libro, que el
# cliente no fabrique hechos de servidor, que el sello nazca con el pedido, que la analítica NUNCA
# tumbe un pago, que un enlace firmado con UTM abra, que el pedido manual no se cobre sin fuente, que
# la supresión desate el libro y que el tracker reintente de verdad. Cada mutación es una forma de
# romper una de esas promesas SIN que nada falle a simple vista; el arnés dice si la suite la caza.
#
# Reglas de la casa: verde antes de mutar · comprobar que la mutación SE APLICÓ · veredicto por CÓDIGO
# DE SALIDA · restaurar por COPIA y no con `git checkout` (`#181`) · árbol byte a byte (sha1) · CONTROL
# · superviviente DECLARADO con su razón (esconderlo bajando el denominador es mentir en el informe).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

CON=app/Domain/Platform/Services/Analytics/Contract.php
ING=app/Domain/Platform/Services/Analytics/EventIngestor.php
REC=app/Domain/Platform/Services/Analytics/Recorder.php
OBS=app/Domain/Booking/Observers/OrderAnalyticsObserver.php
ATT=app/Domain/Platform/Services/Analytics/AttributionContext.php
CMO=app/Filament/Pages/CreateManualOrderPage.php
UTM=app/Domain/Platform/Services/Analytics/EmailUtm.php
BOOT=bootstrap/app.php
CLK=app/Http/Middleware/RecordEmailClick.php
USR=app/Domain/Identity/Models/User.php
PRV=app/Domain/Identity/Services/AccountPrivacy.php
TRK=resources/js/cajon/track.js

TMP="$(mktemp -d)"
FICHEROS=("$CON" "$ING" "$REC" "$OBS" "$ATT" "$CMO" "$UTM" "$BOOT" "$CLK" "$USR" "$PRV" "$TRK")
restaurar() { for f in "${FICHEROS[@]}"; do cp "$TMP/$(basename "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$TMP/$(basename "$f")"; done
SHA_ANTES="$(sha1sum "${FICHEROS[@]}" | sha1sum)"

# Las guardas de la T1 entera, en un solo conjunto: quien toca el libro se mide contra todas.
verde() { docker compose exec -u sail -T laravel.test php artisan test \
    --filter='AnalyticsEventsTest|AnalyticsContractTest|AttributionSealTest|ServerEventsTest|EmailUtmTest|MePrivacyTest|CreateManualOrderPageTest' >/dev/null 2>&1; }
# El tracker se prueba sin navegador (`node --test`), y por eso su verde es otro.
verde_js() { docker compose exec -u sail -T laravel.test node --test resources/js/cajon/track.test.js >/dev/null 2>&1; }

if ! verde || ! verde_js; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde (PHP y JS)'

muerden=0; mutantes=0; controles=0; control_ok=1; no_aplicados=0; declarados=0; declarado_ok=1

# Tres clases de mutación:
#   · `muerde`    — tiene que poner la suite en rojo.
#   · `control`   — no cambia conducta; tiene que quedarse en verde.
#   · `declarado` — sobrevive por diseño, con su razón escrita. Si un día MUERDE, ha nacido una guarda.
# El sexto parámetro es el VERDE que juzga (`verde` o `verde_js`).
mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4" espera="${5:-muerde}" juez="${6:-verde}"
    case "$espera" in
        control) controles=$((controles + 1)) ;;
        declarado) declarados=$((declarados + 1)) ;;
        *) mutantes=$((mutantes + 1)) ;;
    esac
    python3 -c 'import sys; p=sys.argv[1]; s=open(p,encoding="utf-8").read(); open(p,"w",encoding="utf-8").write(s.replace(sys.argv[2], sys.argv[3], 1))' \
        "$fichero" "$buscar" "$poner"
    if cmp -s "$fichero" "$TMP/$(basename "$fichero")"; then
        echo "  ⚠ «$nombre» NO SE APLICÓ (el patrón no casa): el veredicto no vale"
        case "$espera" in
            control) controles=$((controles - 1)) ;;
            declarado) declarados=$((declarados - 1)) ;;
            *) mutantes=$((mutantes - 1)) ;;
        esac
        no_aplicados=$((no_aplicados + 1))
        return
    fi
    touch "$fichero"
    if "$juez"; then
        case "$espera" in
            control) echo "  ✓ CONTROL en verde, como debe: $nombre" ;;
            declarado) echo "  ◦ SOBREVIVE, declarado: $nombre" ;;
            *) echo "  ✗ NO muerde: $nombre" ;;
        esac
    else
        case "$espera" in
            control)
                echo "  ✗ CONTROL EN ROJO — el arnés acusa a lo que no cambia conducta: $nombre"
                control_ok=0 ;;
            declarado)
                echo "  ⚠ EL DECLARADO MUERDE — ha nacido una guarda: reclasifícalo: $nombre"
                declarado_ok=0 ;;
            *)
                echo "  ✓ muerde:    $nombre"
                muerden=$((muerden + 1)) ;;
        esac
    fi
    cp "$TMP/$(basename "$fichero")" "$fichero"; touch "$fichero"
}

echo '── RGPD-07: ningún dato personal entra en el libro ──────────────────────────────────────'

mutar "una clave con pinta de PII (email, phone, age…) deja de vaciar el evento" "$CON" \
  '            if (str_contains($key, $pii)) {
                return true;' \
  '            if (str_contains($key, $pii)) {
                return false;'

mutar "la ruta que falló deja de enmascararse (cada pedido, un valor)" "$ING" \
  "                \$value = \$key === 'route' ? RouteNormalizer::path(\$value) : RouteNormalizer::value(\$value);" \
  "                \$value = RouteNormalizer::value(\$value);"

echo '── El cliente no fabrica hechos de servidor ─────────────────────────────────────────────'

mutar 'un `order_paid` mandado desde el navegador entra en el libro' "$ING" \
  "        if (Contract::isServer(\$name)) {
            return 'server_only';
        }" \
  "        "

echo '── El sello nace con el pedido; la analítica nunca tumba un pago (PAY-21) ───────────────'

mutar 'el observador deja de copiar el sello en `creating`' "$OBS" \
  '                $order->setAttribute($column, $value);' \
  '                continue;'

mutar "⚠ el recorder deja de tragar: un fallo del libro propaga tras el commit del pago" "$REC" \
  "                Log::warning('analytics.record_failed', ['event' => \$row['name'], 'error' => \$e->getMessage()]);" \
  "                throw \$e;"

echo '── El pedido manual lleva la fuente del operador, y solo una del panel ──────────────────'

mutar '`forPanel()` acepta cualquier fuente (un valor tecleado llega al sello)' "$ATT" \
  '        if (! in_array($source, self::PANEL_SOURCES, true)) {
            throw new \InvalidArgumentException("«{$source}» no es una fuente del panel");
        }' \
  '        '

mutar '`create()` cobra sin fuente' "$CMO" \
  '        if (! $this->hasSource()) {' \
  '        if (false) {'

mutar "el botón de cobrar se enciende sin fuente" "$CMO" \
  '            ->disabled(fn (): bool => $this->cart === [] || ! $this->hasSource())' \
  '            ->disabled(fn (): bool => $this->cart === [])'

echo '── Los correos: la UTM se pega y la firma sigue valiendo ────────────────────────────────'

mutar '`EmailUtm::tag()` devuelve la URL sin etiquetar' "$UTM" \
  "        if (\$key === null || \$key === '' || ! self::isOurs(\$url) || str_contains(\$url, 'utm_source=')) {" \
  "        if (true) {"

mutar "la validación de firmas deja de ignorar la atribución (403 al padre)" "$UTM" \
  '    public const IGNORED_QUERY = RouteNormalizer::QUERY_ALLOWLIST;' \
  '    public const IGNORED_QUERY = [];'

mutar 'el middleware `signed` valida a secas (la verificación de correo con UTM da 403)' "$BOOT" \
  '        $middleware->validateSignatures(except: EmailUtm::IGNORED_QUERY);' \
  '        '

mutar '`email_clicked` cuenta cada recarga' "$CLK" \
  '        if (in_array($key, $seen, true)) {
            return;
        }' \
  '        '

mutar '`email_clicked` acepta una clave inventada en la URL' "$CLK" \
  '        if (! is_string($key) || ! EmailUtm::isCustomerKey($key) || ! $request->hasSession()) {' \
  '        if (! is_string($key) || ! $request->hasSession()) {'

echo '── RGPD-01: la supresión desata el libro — y solo el del titular ────────────────────────'

mutar "las sesiones del titular siguen atadas a él tras la supresión" "$USR" \
  "            AnalyticsSession::query()->where('user_id', \$this->getKey())->update(['user_id' => null]);" \
  "            "

mutar "SIN ACOTAR: la supresión desata las sesiones de TODO el mundo (el control del otro titular)" "$USR" \
  "            AnalyticsSession::query()->where('user_id', \$this->getKey())->update(['user_id' => null]);" \
  "            AnalyticsSession::query()->update(['user_id' => null]);"

mutar "el sello de sus pedidos conserva la cookie y los click ids" "$USR" \
  "                    'attribution' => array_diff_key((array) \$order->attribution, array_flip(['visitor_id', 'session_id', 'click_ids'])),
                ])->saveQuietly();" \
  "                    'attribution' => (array) \$order->attribution,
                ])->saveQuietly();"

mutar 'el export del art. 20 se queda sin el bloque `analytics`' "$PRV" \
  "            'analytics' => \$this->analyticsFor(\$user)," \
  "            "

echo '── El tracker (node --test): reintenta de verdad y sus ids son ULID ─────────────────────'

mutar "el envío por número de eventos deja vivo el temporizador (el reintento no se programa)" "$TRK" \
  '            clearTimer(timer);
            timer = null;' \
  '' \
  muerde verde_js

mutar "el ULID pierde el tiempo (deja de ordenar)" "$TRK" \
  '        out = B32[t % 32] + out;' \
  '        out = B32[0] + out;' \
  muerde verde_js

echo '── DECLARADO: el rastro de lo rechazado ─────────────────────────────────────────────────'

# ◦ Nadie asevera sobre el `Log` de los rechazos: es OBSERVABILIDAD para operar (cuántos y por qué), no
# una regla. Su ausencia no cambia ninguna respuesta ni ninguna fila; por eso sobrevive, y se dice.
mutar "la ingesta deja de registrar cuántos eventos rechazó y por qué" "$ING" \
  "            Log::info('analytics.events_rejected', ['count' => count(\$rejected), 'reasons' => array_count_values(array_column(\$rejected, 'reason'))]);" \
  "            " \
  declarado

echo '── CONTROL: cambiar prosa NO puede poner nada en rojo ────────────────────────────────────'

mutar "un comentario reescrito" "$CON" \
  'El nombre sigue `objeto_verbo` en pasado y va en `snake_case` ASCII: es una clave, no un rótulo.' \
  '(prosa mutada por el arnés) el nombre es una clave y no un rótulo.' \
  control

echo
restaurar
SHA_DESPUES="$(sha1sum "${FICHEROS[@]}" | sha1sum)"
if [[ "$SHA_ANTES" != "$SHA_DESPUES" ]]; then
    echo "✗ el árbol NO quedó byte a byte como estaba." >&2
    exit 1
fi
echo "✓ árbol restaurado byte a byte (sha1 $SHA_ANTES)"
echo "▶ mutaciones que muerden: $muerden/$mutantes · controles en verde: $controles · supervivientes declarados: $declarados · sin aplicar: $no_aplicados"
[[ "$muerden" == "$mutantes" && "$control_ok" == 1 && "$declarado_ok" == 1 && "$no_aplicados" == 0 ]] || exit 1
