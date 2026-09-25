#!/usr/bin/env bash
# EL CONTROL del banco de la fiesta (`specs/fiesta-sistema-nuevo.md` §4.4): una mutación de UN píxel en la hoja del
# producto tiene que tumbar los pares de su pieza, y al restaurar el juez vuelve a 0. Sin esto, un banco que da 0 por
# comparar A consigo misma (o por no cargar la hoja) pasaría por bueno.
#
# Se corre DENTRO del contenedor, con el servidor estático del banco levantado (ver `scripts/banco-fiesta.php`):
#   docker compose exec -u sail -T laravel.test bash scripts/mutar-fiesta.sh
#
# Restaura por COPIA de seguridad y con la fecha del fichero tocada (nunca `git checkout`), y sale con el código
# del veredicto: 0 si la mutación se vio Y la restauración vuelve a 0; 1 si algo de eso no pasó.
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
trap - EXIT
if ! juez; then
    echo '✗ restaurada la hoja, el juez sigue en rojo'; exit 1
fi
echo '✓ restaurada la hoja, «botones» vuelve a 0'
exit 0
