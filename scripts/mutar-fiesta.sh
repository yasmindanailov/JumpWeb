#!/usr/bin/env bash
# EL CONTROL del banco de la fiesta (`specs/fiesta-sistema-nuevo.md` §4.4): una mutación de UN píxel en la hoja del
# producto tiene que tumbar los pares de su pieza, y al restaurar el juez vuelve a 0. Sin esto, un banco que da 0 por
# comparar A consigo misma (o por no cargar la hoja) pasaría por bueno.
#
# Se corre DENTRO del contenedor, con el servidor estático del banco levantado (ver `scripts/banco-fiesta.php`):
#   docker compose exec -u sail -T -e PLAYWRIGHT_BROWSERS_PATH=0 laravel.test bash scripts/mutar-fiesta.sh
#
# Dos etapas: una PIEZA (`botones`, contra la hoja fuente) y una PÁGINA (`lista-recien`, contra la hoja CONSTRUIDA: con
# `npm run build` en medio, T1b). Restaura por COPIA de seguridad y con la fecha del fichero tocada (nunca
# `git checkout`), y sale con el código del veredicto: 0 si cada mutación se vio Y cada restauración vuelve a 0.
# ⚠️ Deja `public/build` reconstruida con la hoja restaurada: no se corre con la suite en marcha (lee el manifiesto).
set -uo pipefail
cd "$(dirname "$0")/.."

HOJA=resources/js/fiesta/fiesta.css
BANCO=storage/app/pixel/banco-fiesta
DISENO=/var/www/instancias/playjump/diseno/playjump-design-system
BASE=http://127.0.0.1:8132
COPIA="$(mktemp)"
cp "$HOJA" "$COPIA"

juez() {
    node scripts/pixel.mjs --lote "$BANCO/lote.json" --reloj 2026-09-23T16:05:00+02:00 --rehacer --reintentos 2 --salida "$BANCO/juicio-control" > "$BANCO/control.log" 2>&1
    return $?
}

restaura() {
    cp "$COPIA" "$HOJA"
    touch "$HOJA"
    rm -f "$COPIA"
}
trap restaura EXIT

php scripts/banco-fiesta.php "$DISENO" "$BANCO" "$BASE" botones > /dev/null || { echo '✗ no se pudo generar el banco'; exit 1; }

# La mutación: el relleno del botón pequeño, 18 → 19 px. Comprobada como APLICADA antes de creerla.
sed -i 's/\.pz-boton--sm { height: var(--control-sm); padding: 0 18px;/.pz-boton--sm { height: var(--control-sm); padding: 0 19px;/' "$HOJA"
if ! grep -q 'padding: 0 19px' "$HOJA"; then
    echo '✗ la mutación NO SE APLICÓ'; exit 1
fi
if juez; then
    echo '✗ el juez dio 0 con la hoja MUTADA: el banco no ve la hoja del producto'; exit 1
fi
echo "✓ la mutación de 1 px tumba «botones» ($(grep -c '^✗' "$BANCO/control.log") pares en rojo)"

restaura
if ! juez; then
    echo '✗ restaurada la hoja, el juez sigue en rojo'; exit 1
fi
echo '✓ restaurada la hoja, «botones» vuelve a 0'

# ── SEGUNDA ETAPA (T1b): la PÁGINA. B carga la hoja CONSTRUIDA (`public/build`, por el manifiesto), no la fuente: la
# mutación pasa por `npm run build` y el banco se regenera después (el hash del fichero cambia). 1 px en `.pli-cab`
# tiene que tumbar `lista-recien` (la primera pantalla) y volver a 0 al restaurar y reconstruir.
cp "$HOJA" "$COPIA"
construye() { npm run build > /dev/null 2>&1 && php scripts/banco-fiesta.php "$DISENO" "$BANCO" "$BASE" lista-recien > /dev/null; }
sed -i 's/^\.pli-cab{display:grid;gap:10px;/.pli-cab{display:grid;gap:11px;/' "$HOJA"
if ! grep -q '^\.pli-cab{display:grid;gap:11px;' "$HOJA"; then
    echo '✗ la mutación de la página NO SE APLICÓ'; exit 1
fi
construye || { echo '✗ no se pudo construir la hoja mutada'; exit 1; }
if juez; then
    echo '✗ el juez dio 0 con la página MUTADA: el banco de página no ve la hoja construida'; exit 1
fi
echo "✓ la mutación de 1 px tumba «lista-recien» ($(grep -c '^✗' "$BANCO/control.log") pares en rojo)"

restaura
trap - EXIT
construye || { echo '✗ no se pudo reconstruir la hoja restaurada'; exit 1; }
if ! juez; then
    echo '✗ restaurada y reconstruida la hoja, el juez sigue en rojo'; exit 1
fi
echo '✓ restaurada y reconstruida la hoja, «lista-recien» vuelve a 0'
exit 0
