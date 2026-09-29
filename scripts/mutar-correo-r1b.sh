#!/usr/bin/env bash
# Arnés de mutación de la R1B DE LOS CORREOS (`specs/correos-rediseno.md` §4.1.3): los iconos.
#
# Protege lo que hace fiable el icono de un correo sin GD en producción: que teñir cambie SOLO la paleta y deje el PNG
# válido (su CRC), que la URL lleve la versión de las máscaras y solo exista para un icono y un color válidos, que la ruta
# no deje cookie (aislada como el píxel), que el pie pinte sus iconos decorativos del rol `icono` y solo los de los datos
# que existen, que el rol se vea en los dos modos, y que las máscaras cuadren con su manifiesto y con lo que pinta la
# plantilla.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la mutación SE APLICÓ y que
# su ancla es ÚNICA · restaurar por COPIA DE SEGURIDAD y `touch`, no con `git checkout` (`#181`) · copia por RUTA.
#
#   bash scripts/mutar-correo-r1b.sh
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

ART="docker compose exec -u sail -T laravel.test php artisan"
BASE='MailIconsTest|MailThemeTest|MailPieTest'

TMP="$(mktemp -d)"
FICHEROS=(
    app/Notifications/Support/MailIcons.php
    app/Notifications/Support/MailDocument.php
    app/Notifications/Support/MailTheme.php
    routes/web.php
    resources/views/correo/html/pie.blade.php
    resources/correo/iconos/MANIFIESTO.json
)
copia() { echo "$TMP/${1//\//__}"; }
restaurar() { for f in "${FICHEROS[@]}"; do cp "$(copia "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"; $ART route:clear >/dev/null 2>&1; $ART view:clear >/dev/null 2>&1' EXIT
for f in "${FICHEROS[@]}"; do cp -p "$f" "$(copia "$f")"; done

verde() { $ART test --filter="$1" >/dev/null 2>&1; }

if ! verde "$BASE"; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde'

muerden=0; total=0

mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4" filtro="$5"
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
    if verde "$filtro"; then
        echo "  ✗ NO muerde: $nombre   [$filtro]"
    else
        echo "  ✓ muerde:    $nombre"
        muerden=$((muerden + 1))
    fi
    cp "$(copia "$fichero")" "$fichero"; touch "$fichero"
}

ICO=app/Notifications/Support/MailIcons.php
DOC=app/Notifications/Support/MailDocument.php
TEMA=app/Notifications/Support/MailTheme.php
RUTAS=routes/web.php
PIE=resources/views/correo/html/pie.blade.php
MAN=resources/correo/iconos/MANIFIESTO.json

# ── Teñir: solo la paleta, y el PNG sigue siendo válido ───────────────────────────────────────────────────────────────
mutar "el CRC de la paleta no se recalcula (un PNG que unos gestores enseñan y otros no)" "$ICO" \
  "pack('N', crc32('PLTE'.\$datos))" "substr(\$png, \$pos + 8 + 768, 4)" 'MailIconsTest'
mutar "se tiñe solo la primera entrada de la paleta" "$ICO" \
  "\$datos = str_repeat(\$rgb, 256);" "\$datos = \$rgb.str_repeat(\"\\0\\0\\0\", 255);" 'MailIconsTest'

# ── La URL y la ruta ──────────────────────────────────────────────────────────────────────────────────────────────────
mutar "un icono fuera del manifiesto se intenta servir (500 en vez de 404)" "$ICO" \
  "if (\$hex === null || strlen(\$hex) !== 7 || ! self::existe(\$nombre)) {" "if (\$hex === null || strlen(\$hex) !== 7) {" 'MailIconsTest'
mutar "la URL se da con un color que no es un hex" "$ICO" \
  "        \$hex = MailTheme::hex(\$color);
        if (\$hex === null || ! self::existe(\$nombre)) {" "        \$hex = MailTheme::hex(\$color) ?? '#000000';
        if (\$hex === null || ! self::existe(\$nombre)) {" 'MailIconsTest'
mutar "la URL pierde la versión de las máscaras (las cachés se quedan con la vieja)" "$ICO" \
  "'v' => self::manifiesto()['version']," "'v' => '1'," 'MailIconsTest'
mutar "la ruta de los iconos arranca la sesión (deja cookie en quien abre)" "$RUTAS" \
  "        StartSession::class,
        ShareErrorsFromSession::class,
        PreventRequestForgery::class,
        SetLocale::class," "        ShareErrorsFromSession::class,
        PreventRequestForgery::class,
        SetLocale::class," 'MailIconsTest'

# ── El rol y el pie ───────────────────────────────────────────────────────────────────────────────────────────────────
mutar "los iconos toman el apagado en vez de su rol" "$DOC" \
  "MailIcons::url(\$nombre, \$this->tema->claro('icono'))" "MailIcons::url(\$nombre, \$this->tema->claro('apagado'))" 'MailIconsTest'
mutar "el neutro del icono vuelve al apagado (2,84 sobre el sutil oscuro)" "$TEMA" \
  "'icono' => ['#737B83', '#737B83']," "'icono' => ['#626A72', '#626A72']," 'MailIconsTest'
mutar "un icono decorativo lleva texto alternativo" "$DOC" \
  "'\" width=\"'.\$px.'\" height=\"'.\$px.'\" alt=\"\" style=\"'" "'\" width=\"'.\$px.'\" height=\"'.\$px.'\" alt=\"icono\" style=\"'" 'MailIconsTest'
mutar "el icono de WhatsApp sale aunque no haya WhatsApp" "$PIE" \
  "\$p->whatsapp !== null ? ['message-circle', 'WhatsApp', 'https://wa.me/'.\$p->whatsapp] : null," "['message-circle', 'WhatsApp', 'https://wa.me/'.\$p->whatsapp]," 'MailIconsTest'
mutar "la plantilla pinta un icono sin máscara (queda el hueco sin que nada falle)" "$PIE" \
  "\$correo->icono('clock', 18)" "\$correo->icono('clock-3', 18)" 'MailIconsTest'

# ── El manifiesto ─────────────────────────────────────────────────────────────────────────────────────────────────────
mutar "una máscara que no es la del manifiesto" "$MAN" \
  "\"sha256\": \"cd970e0845ec815c2909d08aaab01d4f83e1ee062e84aae943364c8e9b05f3f9\"" "\"sha256\": \"0000000000000000000000000000000000000000000000000000000000000000\"" 'MailIconsTest'

echo
echo "$muerden/$total muerden"
[[ $muerden -eq $total ]]
