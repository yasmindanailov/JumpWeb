#!/usr/bin/env bash
# Arnés de mutación de la SECCIÓN 05 «Antes de venir» (`DECISIONES #485`, carril de diseño Fase 2 ·
# T2f, `specs/rediseno-desde-canvas.md` §5.4).
#
# Las propiedades que protege, en una línea cada una:
#   · el código de la portada es de EJEMPLO y no puede escanearse;
#   · con sesión no se ofrece crear cuenta, se ofrece VER el QR — y en su zona;
#   · la salida a `/normas` existe (es la que cierra el agujero de `#480`);
#   · la línea del niño invitado es DATO y sigue al catálogo;
#   · la sección de normas se fue ENTERA: ni ancla, ni ranuras de dibujo, ni asomo.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por CÓDIGO DE SALIDA (nunca
# `grep passed`) · comprobar que la mutación SE APLICÓ · restaurar por COPIA en RUTA FIJA, que se
# repara al arrancar (`#448`: un `trap … EXIT` no corre con SIGKILL y deja el árbol mutado).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

FILTER='BeforeVisitSectionTest|CmsLandingFlowTest|HomePageTest|SeoTest'
RUN="docker compose exec -u sail -T laravel.test php artisan test --filter=${FILTER}"

TMP="storage/app/mutaciones/antes-de-venir"
FICHEROS=(
    resources/views/anfitrion/portada.blade.php
    resources/views/components/site/sample-qr.blade.php
    app/Domain/Platform/Services/SampleQrCode.php
    app/Http/Controllers/HomeController.php
)
restaurar() {
    for f in "${FICHEROS[@]}"; do
        [ -f "$TMP/$(basename "$f")" ] || continue
        cp "$TMP/$(basename "$f")" "$f"; touch "$f"
    done
}

if [ -d "$TMP" ]; then
    sucios=0
    for f in "${FICHEROS[@]}"; do
        [ -f "$TMP/$(basename "$f")" ] || continue
        cmp -s "$f" "$TMP/$(basename "$f")" || sucios=$((sucios + 1))
    done
    if [ "$sucios" -gt 0 ]; then
        echo "⚠️  Una ejecución anterior murió sin restaurar: ${sucios} fichero(s) MUTADOS en el árbol."
        restaurar
        echo '✓ restaurados desde la copia. Comprueba con `git diff` antes de seguir.'
    fi
    rm -rf "$TMP"
fi

mkdir -p "$TMP"
trap 'restaurar; rm -rf "$TMP"' EXIT INT TERM
for f in "${FICHEROS[@]}"; do cp "$f" "$TMP/$(basename "$f")"; done

verde() { $RUN >/dev/null 2>&1; }

if ! verde; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde'
echo

muerden=0; total=0

mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4"
    total=$((total + 1))

    if [ ! -f "$TMP/$(basename "$fichero")" ]; then
        echo "✗ «$nombre» quiere mutar «$fichero», que NO está en FICHEROS: sin copia no hay" >&2
        echo "  restauración, y el veredicto de toda la tanda deja de valer. Añádelo al array." >&2
        exit 1
    fi
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

# ⚠️ Desde `#666` la portada de PlayJump vive en la instancia: se muta el ANFITRIÓN MÍNIMO.
HOME_V=resources/views/anfitrion/portada.blade.php
SQR=resources/views/components/site/sample-qr.blade.php
GEN=app/Domain/Platform/Services/SampleQrCode.php
CTL=app/Http/Controllers/HomeController.php

echo '── El código es de EJEMPLO ──'

# 1 · el defecto que esta sección puede sufrir en silencio: que alguien lo cambie por el generador
#     de verdad «porque ya existe». La portada pasaría a emitir un código que escanea.
mutar "el código pasa a ser el GENERADOR de verdad" "$SQR" \
  '\App\Domain\Platform\Services\SampleQrCode::path()' \
  '\App\Domain\Platform\Services\QrCode::svg(url("/"))'

# 2 · sin el aviso, un lector de pantalla anuncia un código que no lo es.
mutar "el nombre accesible deja de decir «de ejemplo»" "$SQR" \
  'aria-label="{{ $label }}"' \
  'aria-label="QR"'

# 3 · rellenar la zona reservada es lo que convertiría el dibujo en un símbolo con formato.
#     ⚠️ **La primera mutación era DÉBIL por precedencia**: `false && A || B || C` es `B || C` en PHP,
#     así que solo desactivaba la primera esquina y las sincronías seguían protegiendo la fila 6. Se
#     desactivan las TRES esquinas de golpe, que es lo que la propiedad dice.
mutar "el dibujo RELLENA la banda del formato" "$GEN" \
  '        return ($x < 8 && $y < 8)' \
  '        return $x === 6 || $y === 6 || ($x < 0 && $y < 8)'

# 4 · un dibujo vacío haría pasar en verde las dos comprobaciones de estructura.
mutar "el dibujo sale VACÍO" "$GEN" \
  '                $s[$y][$x] = $azar() > 0.48 ? 1 : 0;' \
  '                $s[$y][$x] = 0;'

echo
echo '── Las dos salidas ──'

# 5 · la salida a normas es la que cierra el agujero que `#480` dejó abierto.
mutar "la salida a /normas apunta a otra página" "$HOME_V" \
  "<a class=\"before__rules\" href=\"{{ route('normas') }}\">" \
  "<a class=\"before__rules\" href=\"{{ route('precios') }}\">"

# 6 · ofrecer el alta a quien ya tiene cuenta: el defecto que una guarda del nav ya cazó en `#309`.
mutar "con sesión se vuelve a ofrecer el ALTA" "$HOME_V" \
  '            @elseif (auth()->check())' \
  '            @elseif (false)'

# 7 · llevar al índice de la cuenta en vez de al QR obliga a un segundo clic.
mutar "con sesión abre el cajón en otra zona" "$HOME_V" \
  "openAccount(\$event, 'card')" \
  "openAccount(\$event, 'home')"

# 8 · con registro externo la instalación tiene su propio sistema: el alta interna no se ofrece.
mutar "el registro EXTERNO deja de mandar" "$HOME_V" \
  "            @if (! empty(\$site['registration_url']))" \
  '            @if (false)'

echo
echo '── Lo que es DATO ──'

# 9 y 10 · las dos mitades: escrita a fuego, y borrada.
mutar "la línea del niño invitado se escribe SIEMPRE" "$HOME_V" \
  '        @if ($guestWaiverOffered)' \
  '        @if (true)'

mutar "la línea del niño invitado NO se escribe nunca" "$HOME_V" \
  '        @if ($guestWaiverOffered)' \
  '        @if (false)'

# 11 · el predicado del catálogo: si deja de mirar el campo, la línea se apaga para siempre.
mutar "el predicado del justificante deja de mirar el catálogo" "$CTL" \
  "            'guestWaiverOffered' => TicketType::query()" \
  "            'guestWaiverOffered' => false && TicketType::query()"

echo
echo '── La sección de normas se fue ENTERA ──'

# 12 · el ancla vuelve: hoy no la enlaza nadie, y volver a emitirla sin consumidor es ruido.
mutar "vuelve el ancla #rules" "$HOME_V" \
  '<section id="before" class="section wrap">' \
  '<section id="before" class="section wrap"><span id="rules"></span>'

# 13 · un dibujo del kit vuelve a la sección sin su ranura declarada: `<use>` a un símbolo que
#      `kit:build` ya no construye, o sea un `<svg>` vacío de 190×150 (el defecto de `#287`).
mutar "vuelve una pieza de dibujo a la sección" "$HOME_V" \
  '            <div class="before__object">' \
  '            <div class="before__object"><x-site.ilu clave="slot-normas-registro" />'

echo
echo "── ${muerden}/${total} mutaciones muerden ──"
[ "$muerden" -eq "$total" ] || exit 1
