#!/usr/bin/env bash
# Arnés de mutación de la T0 de `docs/specs/complementos-post-reserva.md` (`DECISIONES #413`).
#
# Qué protege, y por qué hacía falta escribirlo: los cuatro arreglos de esta tanda son de los que
# **pasan en verde con el defecto puesto**. La suite entera pasaba antes de tocarlos —126 casos del
# post-form, del justificante y del contrato— mientras un `PUT` parcial borraba las fichas de ocho
# menores. Un verde que no se ha visto en rojo no dice nada.
#
# ⚠️⚠️ Las tres reglas que este repo ya pagó y que están construidas aquí dentro:
#   · **exige VERDE antes de mutar** (`waiver-por-reserva.md` §8.2): si la base ya está roja, todas
#     las mutaciones «muerden» y el informe es basura;
#   · **veredicto por CÓDIGO DE SALIDA**, nunca buscando una cadena en la salida del runner
#     (`desglose-libro.md` §6.4.1, y `#412`: una tubería se comió el `exit` y el arnés dijo verde);
#   · **comprueba que la mutación SE APLICÓ**: un patrón que no casa deja el fichero intacto y el
#     veredicto diría «no muerde» sobre un defecto que nunca se introdujo.
#
# El restaurado es `git checkout` del fichero mutado: **el árbol tiene que estar commiteado** (`#181`).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

FILTER='Api\\V1\\GuestFormTest|Reservation\\GuestFormTest|GuestFormLinkCopyTest|GuardianAuthorizationScreenTest'
RUN="docker compose exec -u sail -T laravel.test php artisan test --filter=${FILTER}"

A=app/Http/Concerns/AuthorizesGuestForm.php
O=app/Domain/Booking/Models/OrderItem.php
R=routes/web.php
V=app/Filament/Resources/Orders/Pages/ViewOrder.php

verde() { $RUN >/dev/null 2>&1; }

if ! git diff --quiet -- "$A" "$O" "$R" "$V"; then
    echo "✗ hay cambios sin commitear en los ficheros que se van a mutar: commitea antes (#181)." >&2
    exit 1
fi

if ! verde; then
    echo "✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada." >&2
    exit 1
fi
echo "✓ base verde con el filtro de la T0"

muerden=0; total=0

# $1 = qué defecto se introduce · $2 = fichero · $3 = texto a buscar · $4 = con qué sustituirlo
mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4"
    total=$((total + 1))
    python3 -c 'import sys; p=sys.argv[1]; s=open(p,encoding="utf-8").read(); open(p,"w",encoding="utf-8").write(s.replace(sys.argv[2], sys.argv[3], 1))' \
        "$fichero" "$buscar" "$poner" \
        || { echo "  ⚠ la mutación «$nombre» no se pudo aplicar"; git checkout -- "$fichero"; return; }
    if git diff --quiet -- "$fichero"; then
        echo "  ⚠ la mutación «$nombre» NO SE APLICÓ (el patrón no casa): el veredicto no vale"
        return
    fi
    if verde; then
        echo "  ✗ NO muerde: $nombre"
    else
        echo "  ✓ muerde:    $nombre"
        muerden=$((muerden + 1))
    fi
    git checkout -- "$fichero"
    # `#219`: restaurar NO basta — hay que tocar la fecha, o una caché que se guíe por mtime sirve
    # el fichero mutado con el fuente correcto en disco.
    touch "$fichero"
}

# ── 1 · La ausencia de una clave vuelve a significar «vacíalo» ─────────────────────────────────
mutar "la ausencia vuelve a ser [] (el guardado parcial borra las fichas)" "$A" \
  '        if (! array_key_exists($key, $source)) {
            return null;
        }' \
  '        if (! array_key_exists($key, $source)) {
            return [];
        }'

mutar "un cuerpo malformado vuelve a borrar en vez de conservar" "$A" \
  '        return is_array($value) ? $value : null;' \
  '        return is_array($value) ? $value : [];'

mutar "el dominio deja de respetar el null de las fichas" "$O" \
  '        if ($guests !== null) {
            $attributes['"'"'guest_data'"'"'] = $type->sanitizeGuestData($guests, (int) $this->quantity);
        }' \
  '        $attributes['"'"'guest_data'"'"'] = $type->sanitizeGuestData($guests ?? [], (int) $this->quantity);'

mutar "el dominio deja de respetar el null de los generales" "$O" \
  '        if ($general !== null) {' \
  '        if (true) {
            $general ??= [];'

# ── 2 · El sello vuelve a mover el token optimista del operador ────────────────────────────────
mutar "el sello se re-estampa aunque nada haya cambiado" "$O" \
  'if ($this->isGuestFormComplete() && ($this->guest_form_completed_at === null || $changed)) {' \
  'if ($this->isGuestFormComplete()) {'

# ── 3 · La escalada 403 → 410 → 404 en la web ─────────────────────────────────────────────────
mutar "el post-form vuelve a filtrar qué reservas existen (GET)" "$R" \
  "Route::get('/reserva/{reservation}/datos-invitados', [GuestFormController::class, 'show'])
    ->middleware('no-store')
    ->missing(fn () => abort(403))" \
  "Route::get('/reserva/{reservation}/datos-invitados', [GuestFormController::class, 'show'])
    ->middleware('no-store')"

mutar "el justificante vuelve a filtrar qué reservas existen (GET)" "$R" \
  "Route::get('/autorizacion/{reservation}', [GuardianAuthorizationController::class, 'show'])
    ->middleware('no-store')
    ->missing(fn () => abort(403))" \
  "Route::get('/autorizacion/{reservation}', [GuardianAuthorizationController::class, 'show'])
    ->middleware('no-store')"

# ── 4 · La rotación del enlace ────────────────────────────────────────────────────────────────
mutar "la firma vuelve a bastar (rotar deja de cerrar el enlace viejo)" "$A" \
  '        return (int) $request->query('"'"'v'"'"', 0) === ($reservation?->guestFormLinkVersion() ?? -1);' \
  '        return true;'

mutar "la versión deja de viajar en el enlace web (se abre y no se puede guardar)" "$O" \
  "            ['reservation' => \$this, 'v' => \$this->guestFormLinkVersion()]," \
  "            ['reservation' => \$this],"

mutar "la versión deja de viajar en los enlaces de la API (rotar cierra la web y deja la app abierta)" "$O" \
  "\$params = ['reservation' => \$this->id, 'v' => \$this->guestFormLinkVersion()];" \
  "\$params = ['reservation' => \$this->id];"

mutar "rotar deja de subir la versión" "$O" \
  '        $next = $this->guestFormLinkVersion() + 1;' \
  '        $next = $this->guestFormLinkVersion();'

mutar "el gesto del operador deja de exigir permiso" "$V" \
  "                \$allowed = (auth()->user()?->hasPermission('orders.edit_guest_data') ?? false)
                    && \$item !== null" \
  "                \$allowed = \$item !== null"

mutar "rotar se lleva por delante lo ya rellenado" "$O" \
  '        $this->forceFill(['"'"'guest_form_link_version'"'"' => $next])->save();' \
  '        $this->forceFill(['"'"'guest_form_link_version'"'"' => $next, '"'"'guest_data'"'"' => []])->save();'

echo
echo "── Veredicto: ${muerden}/${total} mutaciones muerden ──"
[ "$muerden" -eq "$total" ]
