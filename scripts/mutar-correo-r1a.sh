#!/usr/bin/env bash
# Arnés de mutación de la R1A DE LOS CORREOS (`specs/correos-rediseno.md` §4.1.1): la plantilla del diseño en el molde.
#
# Protege lo que hace fiable al motor nuevo: el LECTOR de roles (`MailTheme`: valida antes de escribir en línea, una
# indirección, la pareja oscura, la franja entera o ninguna, la letra de la acción calculada, la memoria por
# `filemtime`), el DOCUMENTO (`MailDocument`: el orden, el aire por el vecino, el escape, la negrita, el marcado con su
# rol de enlace, el texto de un HTML, el oscuro por clase y el de Outlook.com), el PIE (`MailPie`), las VISTAS (ningún
# color a mano, cada texto con su clase de oscuro, los radios como rol, las gemelas que no escapan) y la analítica que
# viaja en los enlaces (UTM, marca del envío, píxel).
#
# Cada mutante lleva el filtro de la guarda que tiene que matarlo: así el arnés dura minutos (owner, 28-09) y el
# veredicto dice QUÉ guarda muerde.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la mutación SE APLICÓ
# · restaurar por COPIA DE SEGURIDAD y `touch`, no con `git checkout` (`#181`) · copia por RUTA: aquí hay tres
# `texto.blade.php`.
#
#   bash scripts/mutar-correo-r1a.sh
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

ART="docker compose exec -u sail -T laravel.test php artisan"
BASE='MailThemeTest|MailThemeRolesTest|MailDocumentTest|MailPieTest|MailMoldTest|ThemeColorTest|EmailProductCardTest|EmailUtmTest|EmailClicksTest|EmailOpensTest|MailInboxLineTest|MixedPartyMailShapeTest'

TMP="$(mktemp -d)"
FICHEROS=(
    app/Notifications/Support/MailTheme.php
    app/Notifications/Support/MailDocument.php
    app/Notifications/Support/MailPie.php
    app/Notifications/Support/BrandedMailMessage.php
    resources/views/correo/html.blade.php
    resources/views/correo/html/cabecera.blade.php
    resources/views/correo/html/resguardo.blade.php
    resources/views/correo/html/boton.blade.php
    resources/views/correo/html/pie.blade.php
    resources/views/correo/texto/texto.blade.php
    resources/views/emails/partials/book.blade.php
    resources/views/emails/partials/product-card.blade.php
)
copia() { echo "$TMP/${1//\//__}"; }
restaurar() { for f in "${FICHEROS[@]}"; do cp "$(copia "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"; $ART view:clear >/dev/null 2>&1' EXIT
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

TEMA=app/Notifications/Support/MailTheme.php
DOC=app/Notifications/Support/MailDocument.php
PIE=app/Notifications/Support/MailPie.php
MOLDE=app/Notifications/Support/BrandedMailMessage.php
V=resources/views/correo
P=resources/views/emails/partials

# ── El LECTOR de roles ─────────────────────────────────────────────────────────────────────────
mutar "un color deja de validarse (rgba, nombres y url llegan al estilo en línea)" "$TEMA" \
  "'/^#([0-9a-f]{3}|[0-9a-f]{6})\$/i'" "'/^(.*)\$/i'" 'MailThemeRolesTest'
mutar "lo de dentro de un @media se lee como :root" "$TEMA" \
  "\$css = (string) preg_replace('/@media[^{]*\\{(?:[^{}]*\\{[^{}]*\\})*[^{}]*\\}/s', '', \$css);" "" 'MailThemeRolesTest'
mutar "la indirección var(--correo-…) deja de resolverse" "$TEMA" \
  "                \$valor = \$destino;" "" 'MailThemeRolesTest'
mutar "la pareja -oscuro se escribe en el claro" "$TEMA" \
  "                    \$oscuro[\$rol] = \$hex;
                });
            } elseif (isset(self::COLORES[\$nombre])) {" "                    \$claro[\$rol] = \$hex;
                });
            } elseif (isset(self::COLORES[\$nombre])) {" 'MailThemeRolesTest'
mutar "una franja a medias se pinta" "$TEMA" \
  "count(\$franja) === self::FRANJAS ? array_values(\$franja) : []" "\$franja !== [] ? array_values(\$franja) : []" 'MailThemeRolesTest'
mutar "la letra de la acción deja de calcularse (blanco fijo)" "$TEMA" \
  "\$claro['accion-letra'] ??= ThemeSettings::onAction(\$claro['accion']);" "\$claro['accion-letra'] ??= '#FFFFFF';" 'MailThemeRolesTest'
mutar "la memoria olvida el filemtime (un cambio de la hoja no se relee)" "$TEMA" \
  "\$f.'@'.(int) filemtime(\$f)" "\$f" 'MailThemeRolesTest'
mutar "la acción ignora el color de acción del panel (solo la marca)" "$TEMA" \
  "ThemeSettings::action() ?? ThemeSettings::brand()" "ThemeSettings::brand()" 'ThemeColorTest'
mutar "lo descartado deja de avisarse en el log" "$TEMA" \
  "Log::warning(" "Log::debug(" 'MailThemeRolesTest'

# ── El DOCUMENTO ───────────────────────────────────────────────────────────────────────────────
mutar "el aire entre zonas es el de dentro de una zona" "$DOC" \
  "self::ZONAS[\$b['tipo']] !== self::ZONAS[\$siguiente['tipo']] => 28," "self::ZONAS[\$b['tipo']] !== self::ZONAS[\$siguiente['tipo']] => 16," 'MailDocumentTest'
mutar "el último bloque deja aire" "$DOC" \
  "\$siguiente === null => 0," "\$siguiente === null => 16," 'MailDocumentTest'
mutar "las líneas antes de un marcado no se cierran (el orden se pierde)" "$DOC" \
  "                if (\$seguidas !== []) {
                    \$bloques[] = ['tipo' => \$tipo, 'lineas' => \$seguidas];
                    \$seguidas = [];
                }
                \$bloques[] = ['tipo' => 'marcado'" "                \$bloques[] = ['tipo' => 'marcado'" 'MailDocumentTest'
mutar "una línea deja de escaparse" "$DOC" \
  "            e(\$linea)," "            \$linea," 'MailDocumentTest'
mutar "la negrita pierde su rol" "$DOC" \
  "'<strong class=\"pjm-strong\" style=\"font-weight:700;color:'.\$fuerte.';\">\$1</strong>'" "'<b>\$1</b>'" 'MailDocumentTest'
mutar "un enlace del marcado no gana el rol de enlace" "$DOC" \
  "'/<a\\s(?![^>]*\\bstyle=)/i'" "'/<a\\s(?=NUNCA)/i'" 'MailDocumentTest'
mutar "el texto de un HTML pega las celdas" "$DOC" \
  "preg_replace('#</t[dh]>#i', '  ', \$s)" "preg_replace('#</t[dh]>#i', '', \$s)" 'MailDocumentTest'
mutar "el texto de un HTML pierde la dirección de los enlaces" "$DOC" \
  "? \"{\$texto} ({\$href})\" : \$texto" "? \$texto : \$texto" 'MailDocumentTest'
mutar "la firma propia de un correo se pierde" "$DOC" \
  "if (is_string(\$data['salutation'] ?? null) && trim(\$data['salutation']) !== '') {" "if (false) {" 'MailDocumentTest'
mutar "el oscuro no reescribe la letra de los tonos (el defecto de la chapa del diseño)" "$DOC" \
  "            \$reglas[] = [\".pjm-tono-{\$tono}-t\", 'color', \$letra];" "" 'MailThemeTest'
mutar "el oscuro de Outlook.com desaparece" "$DOC" \
  "return \"@media (prefers-color-scheme:dark){{\$media}}\\n{\$outlook}\";" "return \"@media (prefers-color-scheme:dark){{\$media}}\\n\";" 'MailThemeTest'
mutar "los enlaces legales del pie pierden la UTM y la marca" "$DOC" \
  "\$con = static fn (string \$url): string => EmailUtm::tag(\$url, \$utm, \$marca);" "\$con = static fn (string \$url): string => \$url;" 'EmailUtmTest|EmailClicksTest'
mutar "el logotipo pierde la UTM y la marca" "$DOC" \
  "logoUrl: EmailUtm::tag((string) config('app.url'), \$utm, \$marca)," "logoUrl: (string) config('app.url')," 'EmailUtmTest|EmailClicksTest'
mutar "el píxel de apertura desaparece" "$DOC" \
  "pixel: \$pixel !== null ? route('emails.open', ['send' => \$pixel]) : null," "pixel: null," 'EmailOpensTest'

# ── El PIE ─────────────────────────────────────────────────────────────────────────────────────
mutar "el WhatsApp llega con espacios y signos (wa.me roto)" "$PIE" \
  "\$whatsapp = (string) preg_replace('/\\D/', '', (string) Setting::value('contact.whatsapp', ''));" "\$whatsapp = trim((string) Setting::value('contact.whatsapp', ''));" 'MailPieTest'
mutar "un correo mal escrito se hace enlace" "$PIE" \
  "filter_var(\$correo, FILTER_VALIDATE_EMAIL) !== false ? \$correo : null" "\$correo !== '' ? \$correo : null" 'MailPieTest'
mutar "un día cerrado no se dice" "$PIE" \
  "        if (! \$ventana->isOpen) {" "        if (false) {" 'MailPieTest'

# ── El MOLDE ───────────────────────────────────────────────────────────────────────────────────
mutar "el molde vuelve al Markdown de septiembre" "$MOLDE" \
  "        \$this->view = self::VISTAS;
        \$this->markdown = null;" "" 'MailThemeTest|MailMoldTest'

# ── Las VISTAS ─────────────────────────────────────────────────────────────────────────────────
mutar "la letra de la chapa pierde su clase de oscuro" "$V/html/cabecera.blade.php" \
  "class=\"pjm-tono-{{ \$b['tono'] }}-t\" valign" "valign" 'MailThemeTest'
mutar "el alt del logotipo pierde su clase de oscuro" "$V/html/cabecera.blade.php" \
  "<img class=\"pjm-strong\" src" "<img src" 'MailThemeTest'
# ⚠️ La guarda ESTÁTICA, sola: hasta el 29-09 el `>` de `\$correo->ty(…)` le cortaba los atributos y no veía ninguna etiqueta
# estilada con `ty()` (el `alt` de arriba sobrevivía). Filtrada por su caso, para que el recorrido dinámico no la tape.
mutar "la guarda estática ve un texto de ty() sin clase (el rótulo del resguardo)" "$V/html/resguardo.blade.php" \
  "<tr><td class=\"pjm-body\" valign=\"top\"" "<tr><td valign=\"top\"" 'test_every_coloured_text_in_the_templates_has_its_dark_class'
mutar "un enlace legal del pie pierde su clase de oscuro" "$V/html/pie.blade.php" \
  "<a class=\"pjm-muted\" href=\"{{ \$href }}\"" "<a href=\"{{ \$href }}\"" 'MailThemeTest'
mutar "los enlaces legales desaparecen del pie" "$V/html/pie.blade.php" \
  "@foreach (\$correo->legales as \$i => [\$texto, \$href])" "@foreach ([] as \$i => [\$texto, \$href])" 'EmailProductCardTest|EmailUtmTest'
mutar "el botón con un color escrito a mano" "$V/html/boton.blade.php" \
  "bgcolor=\"{{ \$t->claro('accion') }}\" style=\"background:{{ \$t->claro('accion') }};" "bgcolor=\"#FF6A13\" style=\"background:#FF6A13;" 'MailThemeTest'
mutar "el resguardo con un radio fuera de la escala" "$V/html/resguardo.blade.php" \
  "border-radius:{{ \$t->radio('lg') }}px" "border-radius:12px" 'MailThemeTest'
mutar "una fuente web viaja en el correo" "$V/html.blade.php" \
  "<title>{{ \$correo->asunto }}</title>" "<title>{{ \$correo->asunto }}</title><link href=\"https://fonts.googleapis.com/css2?family=Figtree\" rel=\"stylesheet\">" 'MailThemeTest'
mutar "la gemela de texto escapa (&amp; en el texto plano)" "$V/texto/texto.blade.php" \
  "{!! implode(\"\\n\", array_map(static fn (\$l) => \$correo->rico(\$l)['t'], \$b['lineas'])) !!}" "{{ implode(\"\\n\", array_map(static fn (\$l) => \$correo->rico(\$l)['t'], \$b['lineas'])) }}" 'MailThemeTest|MailDocumentTest'
mutar "el libro: la clase del saldo en revisión no es de rol" "$P/book.blade.php" \
  "'pjm-tono-error-t']," "'pjm-nada']," 'MailThemeTest'
mutar "la ficha: el precio pierde su clase de oscuro" "$P/product-card.blade.php" \
  "<td class=\"pjm-strong\" valign=\"top\" align=\"right\"" "<td valign=\"top\" align=\"right\"" 'MailThemeTest'
mutar "la ficha: un filete escrito a mano" "$P/product-card.blade.php" \
  "border:1px solid {{ \$t->claro('filete') }};" "border:1px solid #D6D8D4;" 'MailThemeTest'

echo
echo "$muerden/$total muerden"
[[ $muerden -eq $total ]]
