#!/usr/bin/env bash
# `instalar.sh` — lo que hay que correr EN LA INSTALACIÓN cuando cambia este paquete.
#
# No instala el producto: instala ESTE paquete sobre un producto que ya está. Se corre desde la raíz del
# repo de la instancia, y le hace falta saber dónde está el producto.
#
#   PRODUCTO=/ruta/al/producto bash instalar.sh          # comprueba y dice qué haría
#   PRODUCTO=/ruta/al/producto bash instalar.sh --go     # lo hace
#
# ⚠️ En seco por defecto, como `deploy.sh`: un paquete que se aplica solo al invocarlo es un paquete que se
# aplica por error.
set -uo pipefail
cd "$(dirname "$0")"

PAQUETE="$(pwd)"
GO=0
[ "${1:-}" = '--go' ] && GO=1

if [ -z "${PRODUCTO:-}" ]; then
    echo '✗ falta PRODUCTO=/ruta/al/producto' >&2
    exit 1
fi

if [ ! -f "$PRODUCTO/artisan" ]; then
    echo "✗ «$PRODUCTO» no parece el producto (no hay artisan)" >&2
    exit 1
fi

# ⚠️⚠️ **SEC-12**: el paquete vive FUERA del árbol del producto. Blade compila a PHP, así que `web/` es un
# directorio de código: dentro de `public/` el servidor lo serviría en crudo, y en cualquier otro sitio del
# árbol el `rsync --delete` del despliegue se lo llevaría. Se comprueba aquí ADEMÁS de en el producto,
# porque quien corre esto es quien todavía puede moverlo de sitio.
ARBOL="$(cd "$PRODUCTO" && pwd -P)"
if [ "$PAQUETE" = "$ARBOL" ] || [ "${PAQUETE#"$ARBOL"/}" != "$PAQUETE" ]; then
    echo "✗ este paquete está DENTRO del árbol del producto ($ARBOL): el despliegue lo borraría" >&2
    exit 1
fi

CONTRATO="$(python3 -c 'import json,sys; print(json.load(open("instancia.json"))["contrato"])' 2>/dev/null)"
if [ -z "$CONTRATO" ]; then
    echo '✗ `instancia.json` no declara `contrato`' >&2
    exit 1
fi

echo "paquete:  $PAQUETE"
echo "producto: $ARBOL"
echo "contrato: $CONTRATO"
echo "▶ en el .env del producto:  INSTANCIA_RUTA=$PAQUETE"

if [ "$GO" -eq 0 ]; then
    echo '(en seco: nada hecho. Repite con --go)'
    exit 0
fi

# Lo único que hace de verdad: vaciar las cachés del producto para que relea las vistas.
( cd "$PRODUCTO" && php artisan optimize:clear )
echo '✓ cachés del producto vaciadas. Comprueba la portada.'
