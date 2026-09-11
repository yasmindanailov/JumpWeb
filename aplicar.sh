#!/usr/bin/env bash
# Coloca el paquete del cliente PlayJump Park en un clon de `main` de JumpWeb.
#
#   (desde la RAÍZ de tu clon de main)
#   git fetch origin cliente/playjump
#   git show origin/cliente/playjump:aplicar.sh | bash              # solo los ficheros
#   git show origin/cliente/playjump:aplicar.sh | bash -s -- --datos # ficheros + datos del catálogo
#
# ⚠️ Los ficheros se extraen con `git archive | tar`, NUNCA con `git checkout`: un checkout de otra
#    rama escribe en el ÍNDICE y dejaría el material del cliente preparado para entrar en `main`.
set -euo pipefail

RAMA="${RAMA:-origin/cliente/playjump}"

[[ -f artisan && -f .gitignore ]] || { echo "✗ Ejecútalo desde la raíz del clon de JumpWeb (main)."; exit 1; }
[[ "$(git rev-parse --abbrev-ref HEAD)" != "cliente/playjump" ]] || { echo "✗ Estás EN la rama del cliente: esto se aplica sobre un clon de main."; exit 1; }

git fetch -q origin cliente/playjump

# 1. Los ficheros, en sus rutas (todas IGNORADAS por el .gitignore de main).
git archive "$RAMA" public mockup_playjumppark mockup_playjumppark_v2 | tar -x

# 2. La guarda: si alguna de estas rutas NO está ignorada, el paquete podría entrar en main. Para.
for f in public/css/client.css public/img/client-logo.svg public/img/client-kit.svg mockup_playjumppark_v2 mockup_playjumppark; do
    git check-ignore -q "$f" || { echo "✗ $f NO está ignorado en este clon: no sigas y avisa."; exit 1; }
done
echo "✓ Paquete del cliente colocado: public/css/client.css · public/img/client-* · mockup_playjumppark_v2/ (canvas) · mockup_playjumppark/ (archivo)"

# 3. Los datos (opcional): catálogo y ajustes, sin usuarios, pedidos, pagos ni secretos.
if [[ "${1:-}" == "--datos" ]]; then
    git show "$RAMA:datos/catalogo-playjump.sql" \
        | docker compose exec -T mysql bash -c 'mysql -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'
    echo "✓ Datos del catálogo importados."
fi

cat <<'FIN'
▶ Te falta, a mano (una vez):
  1. En tu .env, la línea de entorno/cliente.env de la rama:
       git show origin/cliente/playjump:entorno/cliente.env
  2. docker compose exec -u sail laravel.test php artisan optimize:clear
  3. Si la base era nueva: un administrador →  docker compose exec -u sail laravel.test php artisan app:create-admin
  4. docker compose exec -u sail laravel.test npm run build && docker compose exec -u sail laravel.test npm run build:ssr
FIN
