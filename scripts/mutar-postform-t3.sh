#!/usr/bin/env bash
# Arnés de mutación de la T3 de `docs/specs/complementos-post-reserva.md` (`DECISIONES #413`):
# las superficies del cliente — la página con su suelo sin JS, el correo agrupado y las tres
# superficies de DEMANDA de D15.
#
# Lo que protege es distinto de lo que protegía la T2. Allí, que el LIBRO cerrase; aquí, que **lo que
# el cliente ve sea verdad**: que un extra fuera de plazo no se cancele solo al guardar, que un envío
# hecho con la pantalla vieja se DIGA en vez de callarse, que el correo cuente la retirada, y que
# ninguna de las tres superficies de demanda prometa extras en una instalación que no tiene ninguno.
#
# Reglas de la casa construidas dentro: verde antes de mutar · veredicto por código de salida · y
# comprobar que la mutación SE APLICÓ. Restaura con `git checkout`: el árbol tiene que estar
# commiteado (`#181`). El `touch` posterior es por la caché de vistas compiladas de Blade.
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

FILTER='PostFormAddonsTest|PostFormDemandSurfacesTest|Reservation\\GuestFormTest|Api\\V1\\GuestFormTest|CustomerAccountContextTest'
RUN="docker compose exec -u sail -T laravel.test php artisan test --filter=${FILTER}"

verde() { $RUN >/dev/null 2>&1; }

for f in app/Domain/Booking/Services/PostFormAddons.php \
         app/Http/Concerns/AuthorizesGuestForm.php \
         app/Providers/ApiServiceProvider.php \
         app/Domain/Booking/Services/CustomerReservationsReader.php \
         app/Http/Controllers/GuestFormController.php \
         app/Notifications/PostFormAddonsChanged.php \
         app/Notifications/GuestFormRequest.php \
         resources/views/reservation/guests.blade.php; do
    if ! git diff --quiet -- "$f"; then
        echo "✗ hay cambios sin commitear en $f: commitea antes (#181)." >&2
        exit 1
    fi
done

if ! verde; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo "✓ base verde"

muerden=0; total=0

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
    touch "$fichero"
}

S=app/Domain/Booking/Services/PostFormAddons.php
R=app/Domain/Booking/Services/CustomerReservationsReader.php
W=app/Http/Controllers/GuestFormController.php
M=app/Notifications/PostFormAddonsChanged.php
G=app/Notifications/GuestFormRequest.php
B=resources/views/reservation/guests.blade.php
T=app/Http/Concerns/AuthorizesGuestForm.php
P=app/Providers/ApiServiceProvider.php

# ── Lo que el cliente VE de sus extras ────────────────────────────────────────────────────────
mutar "el PLAZO deja de cerrar la fila (se ofrece lo que el servidor va a rechazar)" "$S" \
  '                closed: $bornWithOrder || ! $withinWindow,' \
  '                closed: $bornWithOrder,'

mutar "la lectura ESCONDE los extras de una fiesta pasada en vez de ensenarlos" "$S" \
  '        if ($type === null || ! $principal->acceptsGuestForm()) {' \
  '        if ($type === null || ! $principal->acceptsGuestForm() || $principal->isFinishedInPractice()) {'

mutar "la lectura tarifica a HOY en vez de al precio de la LINEA" "$S" \
  '            $unit = $line !== null ? (int) $line->unit_price : $this->rates->priceCents($addon, Carbon::today());' \
  '            $unit = $this->rates->priceCents($addon, Carbon::today());'

mutar "la retirada informa delta CERO (el correo y el audit inflan la cifra)" "$S" \
  '            return -$charged;' \
  '            return 0;'

# ── La página ─────────────────────────────────────────────────────────────────────────────────
mutar "el guardado deja de mandar el testigo (el cliente pisa al operador sin enterarse)" "$W" \
  '                is_string($request->input('"'"'expected_version'"'"')) ? $request->input('"'"'expected_version'"'"') : null,' \
  '                null,'

mutar "lo bloqueado se CALLA (el cliente cree que pidió las tapas)" "$W" \
  '            if ($changes->blocked !== []) {' \
  '            if (false) {'

mutar "el cerrado deja de mandar su cantidad (un guardado normal lo cancelaria)" "$B" \
  '                                        <input type="hidden" name="addons[{{ $i }}][quantity]" value="{{ $addon->quantity }}">' \
  ''

mutar "el control deja de ser un input number (sin JS no se puede comprar)" "$B" \
  '                                                type="number"' \
  '                                                type="hidden"'

# ── El correo agrupado ────────────────────────────────────────────────────────────────────────
mutar "la RETIRADA no se cuenta en el correo" "$M" \
  "                \$move['to'] === 0 => __('emails.postform_addons.removed', ['name' => \$move['name']])," \
  "                \$move['to'] === 0 => ''," 

mutar "el correo pierde donde se paga" "$M" \
  "        \$message->line(__('emails.postform_addons.where_to_pay'));" \
  "        // "

mutar "el correo pierde el bloque del LIBRO" "$M" \
  "        \$message->line(EmailBookBlock::forOrder(\$this->item->order));" \
  "        // "

# ── Las superficies de DEMANDA (D15) ──────────────────────────────────────────────────────────
mutar "el correo del post-form nombra extras SIEMPRE (tambien donde no hay)" "$G" \
  '        if (app(PostFormAddons::class)->offerableFor($this->reservation)->isNotEmpty()) {' \
  '        if (true) {'

mutar "la invitacion ignora el PLAZO (invita a lo que ya no se puede)" "$R" \
  '                if ($extras === null && $addons->offerableFor($item)->isNotEmpty()) {' \
  '                if ($extras === null && $item->ticketType?->addons->isNotEmpty()) {'

# ── El testigo contra NUESTRA propia escritura, y el limitador por reserva ────────────────────
mutar "el testigo no se ajusta a nuestra escritura (ningun guardado normal compra nada)" "$T" \
  '        return $seen === $before ? PostFormAddons::versionOf($after) : $seen;' \
  '        return $seen;'

mutar "el limitador por RESERVA deja de acotar (30 correos por minuto y por IP)" "$P" \
  '            return Limit::perMinute(12)->by('"'"'guest-form:'"'"'.$key);' \
  '            return Limit::none();'

echo
echo "── Veredicto: ${muerden}/${total} mutaciones muerden ──"
[ "$muerden" -eq "$total" ]
