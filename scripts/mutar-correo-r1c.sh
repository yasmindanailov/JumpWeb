#!/usr/bin/env bash
# Arnés de mutación de la R1c de los correos (`specs/correos-rediseno.md` §4.1.4): el CÓDIGO en su bloque (sus tres correos),
# los dos AVISOS AL EQUIPO con la plantilla (en el idioma del negocio, nada decidido al construirlos) y el 12 sin horas.
#
# ⚠️ Cada mutación corre SOLO los tests que la tienen que ver (su 5.º argumento), como `mutar-grupos-de-opciones.sh`.
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la mutación SE APLICÓ y que su
# ancla es ÚNICA · un CONTROL que no debe morder · restaurar por COPIA DE SEGURIDAD y no con `git checkout` (`#181`).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"
TODOS='CodeMailsTest|StaffMailsTest|AvisameDeFechasEnvioTest|MailInboxLineTest'
correr() { docker compose exec -u sail -T laravel.test php artisan test --filter="$1" >/dev/null 2>&1; }
TMP="$(mktemp -d)"
FICHEROS=(
    app/Notifications/Support/MailDocument.php
    app/Notifications/Support/BrandedMailMessage.php
    resources/views/correo/html/codigo.blade.php
    resources/views/correo/texto/codigo.blade.php
    app/Notifications/ConfirmationCode.php
    app/Mail/ContactMessageMail.php
    app/Mail/PaymentIncidentMail.php
    lang/es/fiesta.php
)
# ⚠️⚠️ La copia, por la RUTA ENTERA y no por el nombre: las dos vistas del bloque se llaman igual (`html/codigo.blade.php` y
# `texto/codigo.blade.php`), y con `basename` la segunda pisaba la copia de la primera —la primera pasada restauró la gemela
# de TEXTO encima de la vista HTML (03-10; se rescató del commit)—.
copia() { printf '%s/%s' "$TMP" "${1//\//__}"; }
restaurar() { for f in "${FICHEROS[@]}"; do cp "$(copia "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"; docker compose exec -u sail -T laravel.test php artisan view:clear >/dev/null 2>&1' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$(copia "$f")"; done
[[ $(find "$TMP" -type f | wc -l) -eq ${#FICHEROS[@]} ]] || { echo '✗ hay copias que se pisan: el árbol no se podría restaurar' >&2; exit 1; }
if ! correr "$TODOS"; then
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
    local nombre="$1" fichero="$2" buscar="$3" poner="$4" filtro="$5"
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
    if correr "$filtro"; then
        echo "  ✗ NO muerde: $nombre"
    else
        echo "  ✓ muerde:    $nombre"
        muerden=$((muerden + 1))
    fi
    cp "$(copia "$fichero")" "$fichero"; touch "$fichero"
}

control() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4" filtro="$5"
    aplicar "$fichero" "$buscar" "$poner"
    if cmp -s "$fichero" "$(copia "$fichero")"; then
        echo "  ⚠ CONTROL «$nombre» NO SE APLICÓ"; control_ok=0; return
    fi
    touch "$fichero"
    if correr "$filtro"; then
        echo "  ✓ control:   $nombre (sigue verde, como debe)"
    else
        echo "  ✗ CONTROL ROJO: $nombre — se pone rojo por algo que no es la regla"; control_ok=0
    fi
    cp "$(copia "$fichero")" "$fichero"; touch "$fichero"
}

MD=app/Notifications/Support/MailDocument.php
BM=app/Notifications/Support/BrandedMailMessage.php
CH=resources/views/correo/html/codigo.blade.php
CT=resources/views/correo/texto/codigo.blade.php
CC=app/Notifications/ConfirmationCode.php
CM=app/Mail/ContactMessageMail.php
PI=app/Mail/PaymentIncidentMail.php
FI=lang/es/fiesta.php

# ── El CÓDIGO, en su bloque ───────────────────────────────────────────────────────────────────────
mutar "el bloque del código no se pinta" "$MD" \
  "if (is_array(\$data['code'] ?? null)) {" \
  "if (false) {" \
  'CodeMailsTest'
mutar "el código sale sin su etiqueta" "$CH" \
  "@if (\$b['etiqueta'] !== '')" \
  "@if (false)" \
  'CodeMailsTest'
mutar "el código sale sin la nota de su caducidad" "$CH" \
  "@if (\$b['nota'] !== null)" \
  "@if (false)" \
  'CodeMailsTest'
mutar "el código no va en la familia mono" "$CH" \
  "\$correo->ty('mono', 40" \
  "\$correo->ty('texto', 40" \
  'CodeMailsTest'
mutar "la versión de texto pierde el código" "$CT" \
  "{!! \$b['codigo'] !!}" \
  "" \
  'CodeMailsTest'
mutar "un correo sin chapa enseña la CLAVE como chapa" "$BM" \
  "Lang::has(\$grupo.'.badge') ? (string) __(\$grupo.'.badge') : ''" \
  "(string) __(\$grupo.'.badge')" \
  'CodeMailsTest'
mutar "«para qué es» va después del código" "$CC" \
  "->line(__('emails.confirmation_code.for'" \
  "->outro(__('emails.confirmation_code.for'" \
  'CodeMailsTest'

# ── Los dos AVISOS AL EQUIPO ──────────────────────────────────────────────────────────────────────
mutar "el aviso de contacto guarda el idioma de quien escribe al construirse" "$CM" \
  "public function __construct(public array \$contact) {}" \
  "public function __construct(public array \$contact) { \$this->locale((string) config('app.locale')); }" \
  'StaffMailsTest'
mutar "el mensaje del visitante, en un solo párrafo" "$CM" \
  "preg_split('/\\R+/u'" \
  "preg_split('/\\x{0}/u'" \
  'StaffMailsTest'
mutar "una fila sin dato sale en el resguardo" "$CM" \
  "static fn (string \$valor): bool => trim(\$valor) !== ''" \
  "static fn (string \$valor): bool => true" \
  'StaffMailsTest'
mutar "la incidencia dice el tipo cambiado" "$PI" \
  "=== 'duplicate' ? 'duplicate' : 'overbooked'" \
  "=== 'duplicate' ? 'overbooked' : 'duplicate'" \
  'StaffMailsTest'

# ── El 12, sin horas ──────────────────────────────────────────────────────────────────────────────
mutar "el 12 vuelve a prometer dos horas" "$FI" \
  "'preheader' => 'Saltan, meriendan y se llevan regalos." \
  "'preheader' => 'Dos horas saltando, merienda y regalos." \
  'AvisameDeFechasEnvioTest'

# ── CONTROL: la misma regla, escrita de otra forma, NO debe morder ────────────────────────────────
control "la nota, con is_null" "$CH" \
  "@if (\$b['nota'] !== null)" \
  "@if (! is_null(\$b['nota']))" \
  'CodeMailsTest'

echo "$muerden/$total muerden"
[[ $muerden -eq $total && $control_ok -eq 1 ]]
