#!/usr/bin/env bash
# Arnés de mutación de LA IMAGEN DE LA INVITACIÓN AL COMPARTIR (`#815`; `fiesta-sistema-nuevo.md` §4.19), por tandas:
# I1 · el dibujo — el contrato de las fuentes (`InstanceViews::fuentes`), el lector de las variables de las hojas
# (`VariablesDeHoja`), la cobertura de una fuente (`CoberturaDeFuente`), y el dibujo de la B (`ImagenInvitacion`: limpiar,
# ajustar el nombre, la chapa, el kit y sus colores). (La I2, la ruta y la `og:image`, se añade aquí con su tanda.)
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la mutación SE APLICÓ y que
# su ancla es ÚNICA · restaurar por COPIA DE SEGURIDAD y `touch`, no con `git checkout` (`#181`) · copia por RUTA.
#
# ⚠️ Lo que NO juzga (es ojo): cómo se ve. Para eso, las fotos de cada tanda.
#
#   bash scripts/mutar-imagen-invitacion.sh                 (todas)
#   SOLO='I1' bash scripts/mutar-imagen-invitacion.sh       (las que llevan eso en su nombre)
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

EXEC="docker compose exec -u sail -T laravel.test"
PHP='InstanceFontsTest|InstanceSheetsTest|VariablesDeHojaTest|ImagenInvitacionTest|CoberturaDeFuenteTest|ImagenInvitacionRutaTest|InvitacionPaginaTest'

TMP="$(mktemp -d)"
FICHEROS=(
    app/Http/Instancia/InstanceViews.php
    app/Http/Instancia/VariablesDeHoja.php
    app/Http/Fiesta/CoberturaDeFuente.php
    app/Http/Fiesta/ImagenInvitacion.php
    app/Http/Controllers/InvitationPageController.php
)
copia() { echo "$TMP/${1//\//__}"; }
restaurar() { for f in "${FICHEROS[@]}"; do cp "$(copia "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp -p "$f" "$(copia "$f")"; done

verde_php() { $EXEC php artisan test --filter="$PHP" >/dev/null 2>&1; }

if ! verde_php; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde'

muerden=0; total=0

# mutar <nombre> <fichero> <buscar> <poner>
mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4"
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
    if verde_php; then
        echo "  ✗ NO muerde: $nombre"
    else
        echo "  ✓ muerde:    $nombre"
        muerden=$((muerden + 1))
    fi
    cp "$(copia "$fichero")" "$fichero"; touch "$fichero"
}

VISTAS=app/Http/Instancia/InstanceViews.php
VARIABLES=app/Http/Instancia/VariablesDeHoja.php
COBERTURA=app/Http/Fiesta/CoberturaDeFuente.php
IMAGEN=app/Http/Fiesta/ImagenInvitacion.php

# ── I1 · el contrato de las fuentes (y el de las hojas: las mismas puertas, `rutaValidada`) ────────────────
mutar "I1 · una fuente web (WOFF2) pasa por una del servidor" "$VISTAS" \
  "self::rutaValidada(\$ruta, \$base, 'ttf|otf')" "self::rutaValidada(\$ruta, \$base, 'ttf|otf|woff2')"
mutar "I1 · una hoja que no es .css pasa" "$VISTAS" \
  "self::rutaValidada(\$ruta, \$base, 'css')" "self::rutaValidada(\$ruta, \$base, 'css|txt')"
mutar "I1 · una ruta con «..» pasa (hojas y fuentes)" "$VISTAS" \
  "            && ! str_contains(\$ruta, '..');
        \$real = \$valida ?" "            ;
        \$real = \$valida ?"
mutar "I1 · un enlace que sale de public/instancia pasa (hojas y fuentes)" "$VISTAS" \
  'return $real !== false && is_file($real) && str_starts_with($real, $base.DIRECTORY_SEPARATOR) ? $real : null;' \
  'return $real !== false && is_file($real) ? $real : null;'

# ── I1 · las variables de las hojas ──────────────────────────────────────────────────────────────────────
mutar "I1 · el neutro de :where(:root) le gana al :root de la instalación" "$VARIABLES" \
  '$crudas = array_merge($neutras, $crudas);' '$crudas = array_merge($crudas, $neutras);'
mutar "I1 · :where(:root) no se lee (la hoja del producto da cero variables)" "$VARIABLES" \
  "(:where\\(\\s*:root\\s*\\)|:root)" "(:root)"
mutar "I1 · lo de dentro de un @media cuenta (el modo oscuro pisa el claro)" "$VARIABLES" \
  "\$css = (string) preg_replace('/@[a-z-]+[^{;]*\\{(?:[^{}]*\\{[^{}]*\\})*[^{}]*\\}/i', '', \$css);" ""
mutar "I1 · una cadena de var() sin límite (un ciclo, sin fin)" "$VARIABLES" \
  'for ($vuelta = 0; $vuelta <= self::VUELTAS; $vuelta++) {' 'for ($vuelta = 0; $vuelta <= self::VUELTAS + 4; $vuelta++) {'
mutar "I1 · un hex de cinco cifras vale como color" "$VARIABLES" \
  "'/^#([0-9a-f]{3}|[0-9a-f]{6})\$/i'" "'/^#([0-9a-f]{3,6})\$/i'"

# ── I1 · la cobertura de la fuente ───────────────────────────────────────────────────────────────────────
mutar "I1 · Unicode completo: todo carácter de un grupo cuenta, también el .notdef" "$COBERTURA" \
  'return $glifo + ($c - $inicio) !== 0;' 'return true;'
mutar "I1 · plano básico: el .notdef por delta cuenta como cubierto" "$COBERTURA" \
  'return (($c + $delta) & 0xFFFF) !== 0;' 'return true;'
mutar "I1 · plano básico: el .notdef de la tabla de glifos cuenta como cubierto" "$COBERTURA" \
  'return $glifo !== null && $glifo !== 0 && (($glifo + $delta) & 0xFFFF) !== 0;' 'return $glifo !== null;'
mutar "I1 · la subtabla de Unicode completo no se lee (todo por el plano básico)" "$COBERTURA" \
  'if ($completa !== null) {' 'if (false) {'
mutar "I1 · un carácter fuera de todo tramo cuenta como cubierto" "$COBERTURA" \
  "
        return false;
    }

    private static function leer" "
        return true;
    }

    private static function leer"

# ── I1 · el dibujo ───────────────────────────────────────────────────────────────────────────────────────
mutar "I1 · lo que la fuente no tiene se dibuja (el emoji, como basura)" "$IMAGEN" \
  '} elseif ($cobertura->cubre($caracter)) {' '} elseif (true) {'
mutar "I1 · sin componer (NFC): una tilde suelta sigue suelta" "$IMAGEN" \
  '$texto = class_exists(\Normalizer::class) ? (string) \Normalizer::normalize($texto, \Normalizer::FORM_C) : $texto;' ''
mutar "I1 · un nombre largo no se parte en dos" "$IMAGEN" \
  '$partes = self::partir($nombre, $fuente);' '$partes = null;'
mutar "I1 · la segunda línea del nombre sube bajo el logotipo" "$IMAGEN" \
  "'bases' => [380 - 0.95 * \$px, 380.0]" "'bases' => [355 - 0.95 * \$px, 355.0]"
mutar "I1 · sin edad, la chapa se dibuja igual (vacía)" "$IMAGEN" \
  "if (\$edad !== '') {" 'if (true) {'
mutar "I1 · el nombre no va en el acento del tema" "$IMAGEN" \
  "\$c['acento'], \$linea, -0.035);" "\$c['tinta'], \$linea, -0.035);"
mutar "I1 · la tarjeta no es blanca (toma la banda)" "$IMAGEN" \
  "\$t['radio'] * \$k, self::color(\$im, \$c['nieve']));" "\$t['radio'] * \$k, self::color(\$im, \$c['banda']));"
mutar "I1 · el logotipo, fuera de su sitio" "$IMAGEN" \
  "(\$t['x'] + \$t['ancho'] - 250) * \$k" "(\$t['x'] + 40) * \$k"
mutar "I1 · con el kit a medias, imagen igual" "$IMAGEN" \
  'if (array_diff(self::FUENTES, array_keys($fuentes)) !== []) {' 'if ($fuentes === []) {'
mutar "I1 · un color que no llega a hex, imagen igual" "$IMAGEN" \
  'if (in_array(null, $colores, true) || in_array(null, $bits, true)) {' 'if (false) {'
mutar "I1 · sin los neutros del producto debajo" "$IMAGEN" \
  "\$hojas = [resource_path('js/fiesta/fiesta.css'), ...array_map(public_path(...), InstanceViews::hojas('fiesta'))];" \
  "\$hojas = array_map(public_path(...), InstanceViews::hojas('fiesta'));"

# ── I2 · la ruta y la og:image ───────────────────────────────────────────────────────────────────────────
# ⚠️ No hay mutante del `no-store` de la ruta: lo pone también el middleware GLOBAL (`RGPD-04`), sería equivalente.
CONTROL=app/Http/Controllers/InvitationPageController.php
mutar "I2 · sin un nombre que escribir, la ruta intenta dibujar (un 500)" "$CONTROL" \
  'abort_if($estilo === null || $datos === null, 404);' 'abort_if($estilo === null, 404);'
mutar "I2 · la imagen sale sin noindex" "$CONTROL" \
  "            'X-Robots-Tag' => 'noindex',
" ""
mutar "I2 · el idioma de la URL no manda" "$CONTROL" \
  "            app()->setLocale(\$idioma);
" ""
mutar "I2 · un idioma que no está se usa igual" "$CONTROL" \
  "if (is_string(\$idioma) && in_array(\$idioma, SiteLocales::SUPPORTED, true)) {" "if (is_string(\$idioma)) {"
mutar "I2 · la og:image sin el idioma en su URL" "$CONTROL" \
  "'l' => app()->getLocale(), 'v' =>" "'v' =>"
mutar "I2 · la imagen se guarda en disco" "$CONTROL" \
  "        return response(ImagenInvitacion::dibujar(\$datos, \$estilo), 200, [" "        \\Illuminate\\Support\\Facades\\Storage::put('invitaciones/'.\$invitation->getKey().'.jpg', ImagenInvitacion::dibujar(\$datos, \$estilo));
        return response(ImagenInvitacion::dibujar(\$datos, \$estilo), 200, ["
mutar "I2 · la huella no cambia con los datos" "$IMAGEN" \
  'self::VERSION, $datos, $estilo' 'self::VERSION, $estilo'
mutar "I2 · un nombre que la fuente no escribe da imagen igual" "$IMAGEN" \
  "if (\$nombre === '' || self::limpiar(\$nombre, \$estilo['fuentes']['titular']) === '') {" "if (\$nombre === '') {"

echo
echo "$muerden/$total muerden"
[[ $muerden -eq $total ]]
