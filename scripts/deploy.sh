#!/usr/bin/env bash
# =============================================================================
# JumpWeb — despliegue a STAGING (`docs/ENTORNOS.md` §4 · `DECISIONES #105`)
# -----------------------------------------------------------------------------
# «Construir + sincronizar», no «clonar y compilar»: en el servidor NO hay node
# (medido, `ENTORNOS.md` §4), así que los assets se construyen aquí y se suben ya
# compilados.
#
# ⚠️ LO VALIOSO DE ESTE SCRIPT ES LO QUE **NO** DEJA HACER. Cada guarda sale de algo
# medido, no de una precaución genérica:
#
#   · **DRY-RUN POR DEFECTO.** Sin `--go` no toca el servidor. Misma convención que
#     `app:purge-customers`.
#   · **PHP ≥ 8.4.1**: el requisito lo fija el LOCK, no `composer.json` (17 paquetes
#     `symfony/*`). Con 8.3 `composer install` aborta a medias — `#103(f)`.
#   · **NUNCA sube el `.env`**: lo lee y lo VALIDA. Ningún secreto vive en el repo
#     (`ENTORNOS.md` §1), y subir el local tumbaría cuatro guardas de golpe.
#   · **`robots.txt`**: el del repo PERMITE indexar a propósito (una instalación de
#     cliente debe indexarse), así que cada `rsync` tumba la guarda 4. Se repone y se
#     verifica **por HTTP y comparando CONTENIDO, no tamaño**: medido el 2026-08-19,
#     el servido (26 B) y el del repo (25 B) pesan casi igual.
#   · **`public/hot`**: si existe, Vite reescribe TODOS los assets a `localhost:5274`
#     y la web queda sin CSS ni JS **sin ningún error de servidor**. Se excluye y se
#     borra en destino.
#   · **`public/uploads`**: son las subidas del panel y están gitignoradas. Excluirlas
#     las protege también del `--delete` (rsync no borra lo excluido).
#   · **`storage/`**: nunca viaja. Además de logs y cachés del servidor, contiene
#     `framework/testing/disks/*` (un árbol por worker de la suite) y `storage/ssr`,
#     que es un artefacto de TEST. El esqueleto se crea con `mkdir -p`.
#   · **`redsys_environment`**: es un `Setting` de BD, no una variable de entorno, así
#     que solo se puede comprobar DESPUÉS de migrar. Se comprueba ahí, antes de servir
#     tráfico, y si estuviera en `live` el sitio se queda en mantenimiento.
#   · **Basic-auth global: PROHIBIDA** (no la pone este script y no debe ponerse a
#     mano). `/pago/redsys/notificacion` es una S2S y no puede llevar auth: un 401 a
#     Redsys deja el pedido caducando con la tarjeta cobrada — `PAY-02`, `#103(h)`.
#   · **PRODUCCIÓN despliega SOLO ETIQUETAS** (guarda 8, `#613`): con `DEPLOY_PRODUCTION=1`
#     HEAD tiene que ser una etiqueta anotada `vX.Y.Z` que ya esté en `origin`. Staging
#     despliega `main`. La versión queda escrita en `storage/app/version` del servidor.
#
# ORDEN QUE NO ES NEGOCIABLE (cada uno con su porqué medido):
#   `down` ANTES de nada  → `public/index.php` comprueba `maintenance.php` ANTES del
#                            autoloader, así que la página de 503 sobrevive a un
#                            `vendor/` roto a mitad de `composer install`.
#   drenar la cola        → los payloads serializados llevan FQCN; un job encolado con
#                            el código viejo cae a `failed_jobs` y el cliente pagó y no
#                            recibe nada.
#   migrar ANTES de servir→ el morphMap de Fase 2 es requisito, y con `APP_ENV=production`
#                            `tableExists()` NO comprueba: servir antes de migrar da 500
#                            duro, no degradación.
#   `composer install` ANTES de `optimize` → dispara `filament:upgrade`, que hace
#                            `config:clear`/`route:clear`/`view:clear` y se llevaría por
#                            delante cualquier caché horneada antes.
#
# USO
#   scripts/deploy.sh                      # DRY-RUN: comprueba todo y enseña el plan
#   scripts/deploy.sh --go                 # despliega de verdad
#   scripts/deploy.sh --go --seed          # + `ProductionSeeder` (SOLO arranque en frío)
#   scripts/deploy.sh --go --admin-email=… # + crea el admin del panel
#   scripts/deploy.sh --env-template       # imprime el `.env` de staging a rellenar
#
# ⚠️ Este script despliega a STAGING, que es **0 LIVE · 0 PRODUCCIÓN**. Instalar a un
#    cliente real exige revisar las guardas 1 y 3 (Redsys `live`, correo real): eso es
#    una decisión, no una bandera.
# =============================================================================
set -Eeuo pipefail

# ── Destino (staging por defecto; overridable para no ser un snowflake) ──────────
SSH_HOST="${DEPLOY_SSH_HOST:-jumpweb-staging}"
SITE_URL="${DEPLOY_URL:-https://jumpweb.sites.aelium.app}"
REMOTE_SUBDIR="${DEPLOY_SUBDIR:-public_html}"

# El LOCK exige >= 8.4.1 (17 paquetes symfony/*). No es `composer.json` (`^8.3`).
readonly PHP_MIN="8.4.1"
readonly CRON_MARKER="# jumpweb:scheduler"
readonly ROBOTS_GUARD=$'User-agent: *\nDisallow: /'

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT"

GO=0; DO_SEED=0; ADMIN_EMAIL=""; ADMIN_NAME="Administrador"; SKIP_BUILD=0

# ── Salida ───────────────────────────────────────────────────────────────────────
c_red=$'\033[31m'; c_grn=$'\033[32m'; c_yel=$'\033[33m'; c_dim=$'\033[2m'; c_off=$'\033[0m'
step() { printf '\n%s▶ %s%s\n' "$c_grn" "$*" "$c_off"; }
info() { printf '   %s\n' "$*"; }
dim()  { printf '   %s%s%s\n' "$c_dim" "$*" "$c_off"; }
warn() { printf '%s   ⚠ %s%s\n' "$c_yel" "$*" "$c_off"; }
die()  { printf '\n%s✗ %s%s\n\n' "$c_red" "$*" "$c_off" >&2; exit 1; }

on_error() {
    local code=$? line=${1:-?}
    printf '\n%s✗ ABORTADO en la línea %s (código %s).%s\n' "$c_red" "$line" "$code" "$c_off" >&2
    if [[ $GO -eq 1 && ${SITE_IS_DOWN:-0} -eq 1 ]]; then
        printf '%s  El sitio ha quedado EN MANTENIMIENTO a propósito: no se sirve nada roto.\n' "$c_yel"
        printf '  Arregla la causa y vuelve a lanzar el script, o levántalo a mano con:\n'
        printf '    ssh %s "cd %s && php artisan up"%s\n\n' "$SSH_HOST" "${REMOTE_ROOT:-<ruta>}" "$c_off" >&2
    fi
}
trap 'on_error $LINENO' ERR

# ── Argumentos ───────────────────────────────────────────────────────────────────
print_env_template() {
    cat <<'TPL'
# ── .env de STAGING para JumpWeb ────────────────────────────────────────────────
# Créalo en <HOME>/public_html/.env EN EL SERVIDOR. NO se sube por rsync (a propósito:
# ningún secreto vive en el repo, `ENTORNOS.md` §1) y `deploy.sh` lo VALIDA, no lo escribe.
#
# ⚠️ EL APP_KEY, EN ARRANQUE EN FRÍO, NO SE GENERA CON ARTISAN: `key:generate` necesita
# `vendor/`, que todavía no existe (huevo y gallina). Genéralo con openssl, que SÍ está
# en el servidor (verificado):
#     echo "base64:$(openssl rand -base64 32)"
APP_NAME=JumpWeb
APP_ENV=production
APP_KEY=            # ← base64:… (ver arriba). NUNCA reutilices el de desarrollo.
APP_DEBUG=false
APP_URL=https://jumpweb.sites.aelium.app

APP_LOCALE=es
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=es_ES
APP_MAINTENANCE_DRIVER=file
BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
# ⚠️ `localhost`, NO `127.0.0.1` — y la diferencia NO es cosmética: medido el 2026-08-19, MariaDB
# en esta máquina escucha SOLO por socket UNIX (0 sockets TCP en el 3306), así que `127.0.0.1` da
# «ERROR 2002 … Can't connect (115)». `localhost` hace que el cliente use el socket.
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=jumpweb_1_test
DB_USERNAME=            # ← el usuario DEDICADO del sitio (no el de `~/.my.cnf`, que el panel rota)
DB_PASSWORD=

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
CACHE_STORE=database

# ⚠️ GUARDA 3 — en staging el correo NO SALE. Los seeds llevan direcciones con pinta de
# reales y 22 avisos son `ShouldQueue`: con SMTP real se enviarían de verdad.
MAIL_MAILER=log
MAIL_FROM_ADDRESS="no-reply@jumpweb.sites.aelium.app"
MAIL_FROM_NAME="${APP_NAME}"

API_RATE_LIMIT_PER_MINUTE=60
SANCTUM_TOKEN_EXPIRATION_MINUTES=43200

# ⚠️ GUARDA 1 — el ENTORNO de Redsys NO se decide aquí: es el `Setting` `redsys_environment`
# (default `test`), y `deploy.sh` lo comprueba tras migrar. Esta clave es la del comercio;
# vacía = se usa la de sandbox pública, que es justo lo que queremos en staging.
REDSYS_SECRET_KEY=
TPL
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --go) GO=1 ;;
        --seed) DO_SEED=1 ;;
        --skip-build) SKIP_BUILD=1 ;;
        --admin-email=*) ADMIN_EMAIL="${1#*=}" ;;
        --admin-name=*) ADMIN_NAME="${1#*=}" ;;
        --env-template) print_env_template; exit 0 ;;
        -h|--help) sed -n '2,70p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) die "Opción desconocida: $1 (usa --help)" ;;
    esac
    shift
done

SSH_OPTS=(-o BatchMode=yes -o ConnectTimeout=15 -o LogLevel=ERROR)
sshx() { ssh "${SSH_OPTS[@]}" "$SSH_HOST" "$@"; }
remote_php() { sshx "cd '$REMOTE_ROOT' && php $*"; }

printf '%s\n' "════════════════════════════════════════════════════════════════════"
printf ' JumpWeb · despliegue → %s\n' "$SITE_URL"
printf ' destino: %s  ·  modo: %s\n' "$SSH_HOST" \
    "$([[ $GO -eq 1 ]] && printf 'EJECUTAR' || printf 'DRY-RUN (sin --go no se toca nada)')"
printf '%s\n' "════════════════════════════════════════════════════════════════════"

# =============================================================================
# 1 · PRE-VUELO LOCAL — si algo falla aquí, el servidor ni se entera
# =============================================================================
step "1/9 · Pre-vuelo local"

# ── GUARDA 8 · PRODUCCIÓN despliega SOLO ETIQUETAS (`DECISIONES #613`, `#624`) ──────────────────
# Con dos agentes empujando a `main` cada hora, «lo último» no es «lo listo», y la etiqueta es lo
# que los separa. Ocho despliegues a producción se identificaron por hash; el noveno ya no puede.
#
# ⚠️ Pregunta «¿es lo que espero?», como la guarda 1 (`#106`): HEAD tiene que SER una versión
# —una etiqueta ANOTADA `vMAYOR.MENOR.PARCHE` que apunta EXACTAMENTE a este commit— y esa etiqueta
# tiene que estar en `origin`, porque una etiqueta solo local es un hash con nombre: la otra máquina
# no sabría qué corre en producción. Todo lo demás —sin etiqueta, etiqueta ligera, un commit por
# delante de la etiqueta, `origin` sin contestar— aborta con `--go`. En seco se AVISA y se sigue,
# igual que con el árbol sucio: el plan se puede mirar antes de cortar la versión.
#
# Va lo PRIMERO del pre-vuelo: es local, no necesita nada, y si falla el servidor ni se entera.
# Staging despliega `main`, y su versión es la descripción (`v1.0.0-12-gc4471bb9`).
release_tag() {  # la etiqueta de versión ANOTADA que apunta exactamente a HEAD; si no la hay, falla
    local tag
    while IFS= read -r tag; do
        [[ "$tag" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]] || continue
        [[ "$(git cat-file -t "refs/tags/$tag" 2>/dev/null)" == "tag" ]] || continue
        printf '%s' "$tag"
        return 0
    done < <(git tag --points-at HEAD --sort=-v:refname)
    return 1
}

DEPLOY_VERSION="$(git describe --always --match 'v[0-9]*' HEAD)"
if [[ "${DEPLOY_PRODUCTION:-0}" == "1" ]]; then
    guard8=""
    if ! DEPLOY_VERSION="$(release_tag)"; then
        guard8="HEAD ($(git rev-parse --short HEAD)) NO es una versión: ninguna etiqueta ANOTADA vMAYOR.MENOR.PARCHE apunta exactamente a este commit."
    elif [[ "$(git ls-remote --tags origin "refs/tags/$DEPLOY_VERSION" 2>/dev/null | cut -f1)" != "$(git rev-parse "refs/tags/$DEPLOY_VERSION")" ]]; then
        guard8="la etiqueta $DEPLOY_VERSION NO está en origin (o allí es otro objeto): git push origin $DEPLOY_VERSION"
    fi

    if [[ -n "$guard8" ]]; then
        warn "GUARDA 8 · $guard8"
        [[ $GO -eq 1 ]] && die "GUARDA 8 · producción despliega SOLO etiquetas (DECISIONES #613).
   ▶ Corta la versión con /release (etiqueta HEAD y la empuja) o sitúate en una: git checkout vX.Y.Z"
        warn "Con --go esto ABORTA. El plan de abajo es orientativo: no hay versión que desplegar."
        DEPLOY_VERSION="sin-version"
    else
        info "guarda 8 ✓ · versión a desplegar: $DEPLOY_VERSION (etiqueta anotada, y en origin)"
    fi
fi

command -v rsync >/dev/null || die "rsync no está instalado en local."

if [[ -n "$(git status --porcelain)" ]]; then
    warn "El árbol tiene cambios SIN COMMITEAR. Se desplegaría código que no está en el historial,"
    warn "y entonces «lo que hay en staging» deja de ser reproducible."
    [[ $GO -eq 1 ]] && die "Commitea (o guarda) antes de desplegar con --go."
fi
info "commit a desplegar: $(git rev-parse --short HEAD) ($(git rev-parse --abbrev-ref HEAD))"

if [[ $SKIP_BUILD -eq 0 ]]; then
    info "construyendo assets (en el servidor NO hay node)…"

    # ⚠️ EL CANAL CANÓNICO ES SAIL, y no es preferencia de estilo: es el que usa el `pre-push`
    # (`.githooks/`), así que los assets que el gate verificó son EXACTAMENTE los que se suben.
    # Construir con otra cadena de herramientas es divergencia silenciosa entre «verde en local»
    # y «lo que hay en staging».
    #
    # ⚠️⚠️ Y `command -v npm` NO SIRVE PARA ELEGIR. Medido el 2026-08-21 en el 2º puesto de
    # trabajo: bajo WSL, `command -v npm` encuentra el npm de **Windows** por el interop de
    # `/mnt/c`. Ese npm lanza `CMD.EXE`, que **no admite rutas UNC** (`\\wsl.localhost\…`), se
    # cae al directorio de Windows y no encuentra `vite`. El script moría con «npm run build
    # FALLÓ» **y sin motivo** —stderr iba a /dev/null— mientras el npm de Docker funcionaba
    # perfectamente. Un rojo sin nombre no se puede arreglar; es la misma lección que el
    # `pre-push` perdido de `DECISIONES #97`.
    build_log="$(mktemp)"
    npm_path="$(command -v npm || true)"

    if docker compose ps --status=running --services 2>/dev/null | grep -qx 'laravel.test'; then
        build_via="Sail (canal canónico, el mismo que el pre-push)"
        build_cmd=(docker compose exec -u sail -T laravel.test npm run build)
    elif [[ -n "$npm_path" && "$npm_path" != /mnt/* ]] && command -v node >/dev/null; then
        build_via="npm del host ($npm_path)"
        build_cmd=(npm run build)
    elif [[ "$npm_path" == /mnt/* ]]; then
        die "El único npm del PATH es el de Windows ($npm_path), y no puede construir aquí:
   lanza CMD.EXE, que no admite rutas UNC (\\wsl.localhost\…) y no encuentra vite.
   ▶ Arranca el stack y se usa el canal canónico:  docker compose up -d"
    else
        die "No hay forma de construir los assets: ni el contenedor 'laravel.test' está arriba
   ni hay un npm/node usable en el host. En el servidor NO hay node (ENTORNOS §4), así que sin
   build local no se despliega.
   ▶ docker compose up -d"
    fi

    dim "vía: $build_via"

    if ! "${build_cmd[@]}" >"$build_log" 2>&1; then
        printf '\n%s── salida del build ──%s\n' "$c_dim" "$c_off" >&2
        tail -25 "$build_log" | sed 's/^/     /' >&2
        rm -f "$build_log"
        die "npm run build FALLÓ (vía $build_via). No se despliega sin assets."
    fi
    rm -f "$build_log"
fi

# ⚠️ Medido el 2026-08-13: un build interrumpido dejó `manifest.json` a 0 bytes y TODA la web
# dio 500 (`ViteManifestNotFound`). Por eso se comprueba el TAMAÑO, no solo la existencia.
[[ -f public/build/manifest.json ]] || die "Falta public/build/manifest.json: el build no llegó a emitirlo."
manifest_bytes=$(wc -c < public/build/manifest.json)
[[ "$manifest_bytes" -gt 0 ]] || die "public/build/manifest.json está a 0 BYTES (build interrumpido). Toda la web daría 500."
info "manifest.json: ${manifest_bytes} B · $(du -sh public/build | cut -f1) en public/build"

[[ -f public/hot ]] && { rm -f public/hot; warn "public/hot existía en LOCAL (npm run dev interrumpido). Borrado."; }

# =============================================================================
# 2 · PRE-VUELO REMOTO — versión de PHP, `.env` y sus guardas
# =============================================================================
step "2/9 · Pre-vuelo remoto"

sshx true 2>/dev/null || die "No hay acceso SSH a '$SSH_HOST'. Revisa ~/.ssh/config (ENTORNOS §1)."

REMOTE_HOME=$(sshx 'echo $HOME')
REMOTE_ROOT="${REMOTE_HOME}/${REMOTE_SUBDIR}"
info "raíz remota: $REMOTE_ROOT"

remote_php_version=$(sshx 'php -r "echo PHP_VERSION;"')
if [[ "$(printf '%s\n%s\n' "$PHP_MIN" "$remote_php_version" | sort -V | head -1)" != "$PHP_MIN" ]]; then
    die "PHP remoto $remote_php_version < $PHP_MIN.
   El mínimo NO lo fija composer.json (^8.3) sino el LOCK: 17 paquetes symfony/* exigen >=8.4.1.
   Con esta versión 'composer install --no-dev' aborta a medias (DECISIONES #103(f)).
   ▶ Sube el PHP del sitio desde el panel ANTES de desplegar."
fi
info "PHP remoto: $remote_php_version (≥ $PHP_MIN ✓)"

if ! sshx "test -f '$REMOTE_ROOT/.env'"; then
    die "No hay .env en $REMOTE_ROOT.
   Este script NUNCA lo sube: ningún secreto vive en el repo (ENTORNOS §1).
   ▶ Créalo en el servidor con la plantilla:  scripts/deploy.sh --env-template"
fi

env_get() { sshx "grep -E '^[[:space:]]*$1=' '$REMOTE_ROOT/.env' | tail -1 | cut -d= -f2- | tr -d '\"'\\''[:space:]'" || true; }

r_env=$(env_get APP_ENV);      r_debug=$(env_get APP_DEBUG)
r_url=$(env_get APP_URL);      r_queue=$(env_get QUEUE_CONNECTION)
r_mail=$(env_get MAIL_MAILER); r_key=$(env_get APP_KEY)

guard_errors=()
[[ "$r_key" != "" ]]                        || guard_errors+=("APP_KEY vacío → generar con: echo \"base64:\$(openssl rand -base64 32)\"  (en frío NO vale key:generate: necesita vendor/)")
[[ "$r_env" == "production" ]]              || guard_errors+=("APP_ENV='$r_env' (debe ser 'production')")
[[ "$r_debug" == "false" ]]                 || guard_errors+=("GUARDA · APP_DEBUG='$r_debug' (debe ser 'false': filtraría trazas y config)")
[[ "$r_url" == https://* ]]                 || guard_errors+=("GUARDA 5 · APP_URL='$r_url' debe ser HTTPS (rompe a la vez emails, URLs firmadas, CORS y Sanctum)")
[[ "$r_queue" == "database" ]]              || guard_errors+=("GUARDA 6 · QUEUE_CONNECTION='$r_queue' (debe ser 'database'; con 'sync' los 22 ShouldQueue se envían en la petición)")
# ⚠️ PRODUCCIÓN (`DEPLOY_PRODUCTION=1`, 2026-09-01 · playjump.es): el correo TIENE que salir
# (el registro exige verificar el email para firmar) y el `robots.txt` del repo (permisivo) es el
# bueno. Las guardas 3 y 4 son de STAGING —su razón es que allí los seeds llevan direcciones con
# pinta de reales y el sitio no debe indexarse— y en producción se INVIERTEN, no se relajan.
if [[ "${DEPLOY_PRODUCTION:-0}" == "1" ]]; then
    [[ "$r_mail" != "log" && "$r_mail" != "array" ]] \
        || guard_errors+=("PRODUCCIÓN · MAIL_MAILER='$r_mail' — el correo NO saldría y nadie podría verificar su cuenta ni firmar.")
else
    [[ "$r_mail" == "log" || "$r_mail" == "array" ]] \
        || guard_errors+=("GUARDA 3 · MAIL_MAILER='$r_mail' — EL CORREO SALDRÍA. Los seeds llevan direcciones con pinta de reales. Usa 'log' o un buzón trampa.")
fi

if [[ ${#guard_errors[@]} -gt 0 ]]; then
    printf '\n%s✗ El .env remoto NO cumple las guardas de ENTORNOS.md §2:%s\n' "$c_red" "$c_off" >&2
    printf '   · %s\n' "${guard_errors[@]}" >&2
    die "Corrige el .env en el servidor y vuelve a lanzar."
fi
info "guardas del .env ✓  (env=$r_env · debug=$r_debug · queue=$r_queue · mail=$r_mail)"

IS_COLD=0
sshx "test -d '$REMOTE_ROOT/vendor'" || IS_COLD=1
if [[ $IS_COLD -eq 1 ]]; then
    warn "ARRANQUE EN FRÍO: no hay vendor/ en el servidor (nada desplegado todavía)."
    [[ $DO_SEED -eq 0 ]] && warn "Sin --seed no habrá catálogo: la web quedará vacía."
    [[ -z "$ADMIN_EMAIL" ]] && warn "Sin --admin-email no habrá forma de entrar a /admin (y ahí van las claves de Turnstile)."
fi

# =============================================================================
# 3 · EL PLAN — exclusiones del rsync, cada una con su motivo medido
# =============================================================================
step "3/9 · Plan de sincronización"

RSYNC_EXCLUDES=(
    --exclude='/.env'                # se valida, jamás se sube (secretos)
    --exclude='/.git/'
    --exclude='/.githooks/'
    --exclude='/.claude/'
    --exclude='/node_modules/'       # 97 MB y no hay node en el servidor
    --exclude='/vendor/'             # lo construye composer allí; excluirlo lo protege del --delete
    --exclude='/tests/'              # 0 valor en runtime; lleva fixtures y credenciales de prueba
    --exclude='/docs/'
    --exclude='/openapi/'            # solo lo leen los tests (config/api.php → tests/)
    --exclude='/storage/'            # logs y cachés DEL SERVIDOR + basura de test + storage/ssr
    --exclude='/public/hot'          # si llega, Vite sirve todo desde localhost:5274 y la web queda muda
    --exclude='/.htaccess'           # PRODUCCIÓN (#325): puente public_html → public/ mientras el panel no apunte al docroot bueno; no está en el repo y el --delete lo tumbaría
    --exclude='/public/uploads/'     # subidas del panel: excluirlas las salva del --delete
    --exclude='/public/css/client.css'  # paquete de tema DEL CLIENTE (#143): gitignorado, vive solo
                                        # en el servidor. Sin esta línea el --delete se lo lleva en el
                                        # primer despliegue y la web vuelve al tema del producto
    --exclude='/public/img/client-logo.svg'  # el LOGOTIPO de la instalación: mismo motivo exacto
                                             # que la hoja de arriba. Sin la exclusión, el primer
                                             # despliegue lo borra y la marca vuelve a ser texto
    --exclude='/public/img/client-favicon.svg'  # el ICONO DE PESTAÑA de la instalación: idem. Es la
                                                # tercera pieza del mismo patrón; sin ella el
                                                # despliegue devuelve el icono del producto
    # ⚠️ **UN PATRÓN, UNA LÍNEA POR FICHERO** (#216). El resto del paquete de marca: la variante
    # del logotipo sobre TINTA, su respaldo raster, y el set de icono que iOS y Android SÍ leen
    # (el SVG no lo miran). Cada uno con su exclusión, porque cada uno se carga por separado y una
    # instalación puede traer solo algunos. Sin estas líneas el --delete se los lleva EN SILENCIO
    # y la instalación vuelve a enseñar el icono del producto en el móvil.
    --exclude='/public/img/client-logo-ink.svg'
    --exclude='/public/img/client-logo@4x.png'
    --exclude='/public/img/client-favicon.ico'
    --exclude='/public/img/client-apple-touch-icon.png'
    --exclude='/public/img/client-icon-192.png'
    --exclude='/public/img/client-icon-512.png'
    --exclude='/public/img/client-icon-512-maskable.png'
    # El TAG de la ciudad del hero del cierre (`--deco-tag`, #235): mismo motivo exacto que los
    # de arriba. Sin esta línea el --delete se lo lleva y la esquina del cierre se queda vacía.
    --exclude='/public/img/client-tag.svg'
    # La LETRA que el export del cliente se comió (#275): geometría SUYA con la que
    # `logo-letra-a.php` repara el logotipo. Mismo motivo; sin la exclusión se pierde en el
    # primer despliegue y la reparación deja de poder rehacerse en el servidor.
    --exclude='/public/img/client-logo-a.path'
    # El KIT DE ILUSTRACIÓN de la instalación (`specs/hueco-ilustracion.md`): el sprite de <symbol>
    # del que salen TODOS sus dibujos. Mismo motivo exacto que los de arriba, y aquí muerde más
    # porque no es una pieza: son todas a la vez. Sin esta línea el --delete se lo lleva y la
    # instalación se queda sin ilustración en cada vista, EN SILENCIO (el hueco falla hacia
    # invisible a propósito: no hay caja rota que delate la pérdida).
    --exclude='/public/img/client-kit.svg'
    # El RESPALDO de la vista previa del MENÚ (#341): la foto que usa todo destino sin una suya.
    # Mismo motivo exacto; sin esta línea el --delete se lo lleva y la columna del menú vuelve a
    # quedarse rayada entera, también en silencio.
    --exclude='/public/img/client-menu.webp'
    --exclude='/bootstrap/cache/*'   # llevaría la config local horneada; se regenera allí
    --exclude='/.phpunit.result.cache'
    --exclude='/compose.yaml'
    --exclude='/phpunit.xml'
    --exclude='/package-lock.json'
    --exclude='/design_mockup/'
    --exclude='/mockup_playjumppark*/'  # canvas de diseño del 2º cliente (3 MB): material de un
                                       # cliente, y el servidor no lo necesita para nada.
                                       # ⚠️ Está gitignorado, pero eso NO basta: el rsync sincroniza
                                       # el árbol de TRABAJO, no lo que git sigue. Sin esta línea
                                       # viaja igual — el mismo motivo por el que está `design_mockup`
)
dim "excluidos: .env · .git · vendor · node_modules · tests · docs · openapi · storage · public/hot · public/uploads · public/css/client.css · bootstrap/cache · mockup_playjumppark"
dim "SÍ viajan: app · bootstrap · config · database · lang · public (con build) · resources · routes · artisan · composer.*"

rsync_run() {  # $1 = extra flags
    rsync -az --delete --human-readable $1 "${RSYNC_EXCLUDES[@]}" \
        -e "ssh ${SSH_OPTS[*]}" \
        ./ "${SSH_HOST}:${REMOTE_ROOT}/"
}

if [[ $GO -eq 0 ]]; then
    info "cambios que se enviarían (rsync --dry-run):"
    # ⚠️ La salida se vuelca a fichero y LUEGO se recorta. Encadenar `rsync | head` mata el
    # rsync con SIGPIPE y, con `set -o pipefail`, tumba el script entero con código 141
    # (medido el 2026-08-19: el primer dry-run real abortó justo aquí). De paso, así el
    # rsync se ejecuta UNA vez en vez de dos para contar.
    plan="$(mktemp)"; trap 'rm -f "$plan"' EXIT
    rsync_run "--dry-run --itemize-changes" >"$plan" 2>&1
    sed -n '1,40p' "$plan" | sed 's/^/     /'
    total=$(grep -c '^[<>ch.]' "$plan" || true)
    [[ "$total" -gt 40 ]] && dim "… y $((total - 40)) más"
    printf '\n'
    info "TOTAL de entradas que cambiarían: $total"
    printf '\n%s── DRY-RUN: no se ha tocado el servidor. Repite con --go para desplegar. ──%s\n\n' "$c_yel" "$c_off"
    exit 0
fi

# =============================================================================
# 4 · MANTENIMIENTO + DRENAJE DE COLA
# =============================================================================
step "4/9 · Mantenimiento y drenaje de cola"

SITE_IS_DOWN=0
if [[ $IS_COLD -eq 0 ]]; then
    # `public/index.php` mira `maintenance.php` ANTES del autoloader: la 503 sobrevive a un
    # `vendor/` a medias. Por eso `down` va PRIMERO y `up` al final.
    remote_php "artisan down --retry=60" >/dev/null && SITE_IS_DOWN=1
    info "sitio en mantenimiento (503)"

    # Un job encolado con el código viejo referencia FQCN que ya no existen y cae a
    # `failed_jobs`: el cliente pagó y no recibe nada. Drenar es lo que compra la seguridad.
    remote_php "artisan queue:work --stop-when-empty --max-time=120" >/dev/null 2>&1 || true
    pending=$(remote_php "artisan tinker --execute='echo DB::table(\"jobs\")->count();'" 2>/dev/null | tr -dc '0-9' || echo "?")
    info "cola drenada · jobs pendientes: ${pending:-0}"
    [[ "${pending:-0}" != "0" ]] && warn "Quedan jobs en cola; se desplegará igual, pero revisa failed_jobs después."
else
    dim "arranque en frío: nada que parar ni que drenar"
fi

# =============================================================================
# 5 · SINCRONIZAR
# =============================================================================
step "5/9 · Sincronizando código y assets"

sshx "mkdir -p '$REMOTE_ROOT'"
rsync_run "" | tail -4 | sed 's/^/     /'

# El esqueleto de storage NO viaja (ver exclusiones): se crea aquí, vacío y con permisos.
sshx "cd '$REMOTE_ROOT' && mkdir -p \
    storage/app/private storage/app/public \
    storage/framework/cache/data storage/framework/sessions storage/framework/views \
    storage/logs bootstrap/cache public/uploads && \
    chmod -R ug+rwX storage bootstrap/cache public/uploads"
info "esqueleto de storage/ y bootstrap/cache asegurado"

# La versión desplegada queda ESCRITA en el servidor (`DECISIONES #613`): allí no hay `.git`, así
# que este fichero es lo único que contesta «¿qué corre aquí?». Vive en `storage/`, que no viaja:
# el `--delete` del rsync no lo toca. Se escribe AQUÍ, justo tras sincronizar, porque es el momento
# en que el código del servidor cambia; la salud (9/9) lo relee y lo compara.
sshx "printf '%s\n' '$DEPLOY_VERSION $(git rev-parse --short HEAD) $(date -u +%Y-%m-%dT%H:%M:%SZ)' > '$REMOTE_ROOT/storage/app/version'"
info "versión escrita en storage/app/version: $DEPLOY_VERSION"

sshx "rm -f '$REMOTE_ROOT/public/hot'"
info "public/hot borrado en destino"

# =============================================================================
# 6 · GUARDA 4 · robots.txt
# =============================================================================
step "6/9 · Reponiendo el robots.txt (guarda 4)"

# El del repo dice `Disallow:` (vacío = permitir TODO) porque la instalación de un cliente
# DEBE indexarse. Cada rsync lo pisa, así que esto no es opcional ni una sola vez.
if [[ "${DEPLOY_PRODUCTION:-0}" == "1" ]]; then
    info "PRODUCCIÓN: se conserva el robots.txt permisivo del repo (la instalación DEBE indexarse)"
else
    sshx "printf '%s\n' 'User-agent: *' 'Disallow: /' > '$REMOTE_ROOT/public/robots.txt'"
    info "robots.txt reescrito (se verifica por HTTP al final)"
fi

# =============================================================================
# 7 · DEPENDENCIAS Y BASE DE DATOS
# =============================================================================
step "7/9 · composer install · migrate · seed"

# `composer install` dispara `post-autoload-dump` → `filament:upgrade`, que hace
# config:clear/route:clear/view:clear. Por eso va ANTES de `optimize`, nunca después.
sshx "cd '$REMOTE_ROOT' && composer install --no-dev --optimize-autoloader --no-interaction --no-progress" \
    2>&1 | tail -3 | sed 's/^/     /'
info "dependencias de producción instaladas"

remote_php "artisan migrate --force" 2>&1 | tail -5 | sed 's/^/     /'
info "migraciones aplicadas"

# ── GUARDA 1 · solo comprobable AQUÍ: `redsys_environment` es un Setting de BD ──────
# ⚠️⚠️ ESTA GUARDA DIO UN VERDE FALSO EL 2026-08-19 y hay que saber por qué (`DECISIONES #106`):
#   (1) leía el Setting con `Setting::value(...)`, cuyo FQCN lleva backslashes que NO sobreviven a
#       la capa local→ssh→shell remoto: llegaba mutilado y `tinker` devolvía un **PARSE ERROR**;
#   (2) el `tr -dc 'a-z'` convertía ese error en una cadena de basura; y
#   (3) la condición preguntaba «¿CONTIENE *live*?», así que la basura pasaba.
# Es decir: **con el entorno en `live` la guarda habría pasado igual**. Los dos arreglos:
#   · se lee por `DB::table('settings')`, que NO necesita namespaces y por tanto no se puede mutilar;
#   · y es FAIL-CLOSED: se exige `test` EXACTO (o vacío = el default del código). Cualquier otra cosa
#     —incluido un error— aborta. Preguntar «¿es lo que espero?» en vez de «¿es lo que temo?».
# ▶ `#594` (`[DECIDIDO owner, 2026-09-13]`): en PRODUCCIÓN `live` es el estado BUSCADO desde que el owner
#   activó el TPV real, y la guarda paraba todo despliegue con el sitio ya en mantenimiento (medido: tres
#   minutos de 503 con un pago real recién iniciado). En producción se admite EXACTO `test` o `live`, y se
#   dice cuál; en staging, solo `test`. Sigue siendo FAIL-CLOSED: cualquier otro valor, o un error, aborta.
# ⚠️ La comparación va con el valor ENTRE COMILLAS dentro de `[[ ]]`: así es literal y un valor con `*`
#   no se interpreta como patrón.
redsys_env=$(remote_php "artisan tinker --execute='echo DB::table(\"settings\")->where(\"key\",\"redsys_environment\")->value(\"value\");'" 2>/dev/null | tr -d '[:space:]' || echo "")
if [[ "${DEPLOY_PRODUCTION:-0}" == "1" ]]; then
    redsys_ok="test live"
else
    redsys_ok="test"
fi
if [[ -n "$redsys_env" && " $redsys_ok " != *" $redsys_env "* ]]; then
    die "GUARDA 1 · redsys_environment = '${redsys_env}' — se esperaba: ${redsys_ok// / o }.
   En staging, 'live' cobraría de verdad con tarjetas de verdad: es el único fallo de la lista
   que cuesta DINERO. Y si es un valor raro o un error, tampoco se sigue: esta guarda es FAIL-CLOSED
   a propósito (un verde que no se ha podido comprobar NO es un verde).
   El sitio queda en mantenimiento. ▶ Revísalo en el panel y vuelve a lanzar."
fi
info "guarda 1 ✓ · redsys_environment = '${redsys_env:-test (default)}' — exacto, no 'contiene'"
[[ "$redsys_env" == "live" ]] && warn "PRODUCCIÓN con Redsys en live: los pagos son REALES."

if [[ $DO_SEED -eq 1 ]]; then
    warn "Sembrando con ProductionSeeder (borra y recrea el CATÁLOGO; aborta solo si ya hay PEDIDOS)."
    remote_php "artisan db:seed --class=Database\\\\Seeders\\\\ProductionSeeder --force" 2>&1 | tail -4 | sed 's/^/     /'
    info "semilla neutra aplicada"
fi

# `ProductionSeeder` deja SlotTemplates pero 0 franjas materializadas: sin esto no hay
# absolutamente nada que comprar, y entonces no se puede verificar ni Turnstile ni el S2S.
remote_php "artisan slots:generate-rolling" 2>&1 | tail -2 | sed 's/^/     /'
info "franjas generadas"

if [[ -n "$ADMIN_EMAIL" ]]; then
    step "7b/9 · Cuenta de acceso al panel"
    warn "La contraseña se imprime UNA sola vez. Anótala AHORA en el vault."
    remote_php "artisan app:create-admin --email='$ADMIN_EMAIL' --name='$ADMIN_NAME'" | sed 's/^/     /'
fi

# =============================================================================
# 8 · CACHÉS, CRON Y LEVANTAR
# =============================================================================
step "8/9 · Cachés, cron del scheduler y levantar"

remote_php "artisan optimize" >/dev/null
info "config/route/view/event cacheadas (+ filament:optimize)"

# Un solo cron lo mueve todo: `schedule:run` dispara las CINCO tareas, incluido el worker de
# cola (`queue:work --stop-when-empty`), así que NO hace falta supervisor ni systemd.
# Idempotente por marcador: se reescribe la línea, nunca se acumula.
cron_line="* * * * * cd $REMOTE_ROOT && php artisan schedule:run >/dev/null 2>&1 $CRON_MARKER"
sshx "( crontab -l 2>/dev/null | grep -vF '$CRON_MARKER' || true; echo '$cron_line' ) | crontab -"
info "cron instalado: $(sshx "crontab -l | grep -cF '$CRON_MARKER'") entrada (sin duplicados)"

remote_php "artisan up" >/dev/null; SITE_IS_DOWN=0
info "sitio levantado"

# =============================================================================
# 9 · SALUD — no se da por hecho que fue bien
# =============================================================================
step "9/9 · Verificación de salud"

fails=0
check() { # $1=descripción  $2=condición ya evaluada (0 ok)
    if [[ "$2" -eq 0 ]]; then printf '   %s✓%s %s\n' "$c_grn" "$c_off" "$1"
    else printf '   %s✗ %s%s\n' "$c_red" "$1" "$c_off"; fails=$((fails+1)); fi
}

http_code=$(curl -s -o /dev/null -m 20 -w '%{http_code}' "$SITE_URL/up" || echo 000)
check "GET /up → $http_code (esperado 200)" "$([[ "$http_code" == "200" ]] && echo 0 || echo 1)"

home_code=$(curl -s -o /dev/null -m 20 -w '%{http_code}' "$SITE_URL/" || echo 000)
check "GET / → $home_code (esperado 200)" "$([[ "$home_code" == "200" ]] && echo 0 || echo 1)"

# ⚠️ Se compara el CONTENIDO, no el tamaño: el servido (26 B) y el permisivo del repo (25 B)
# pesan casi igual, así que un check por tamaño daría verde con la guarda caída.
robots=$(curl -s -m 20 "$SITE_URL/robots.txt" || echo "")
if [[ "${DEPLOY_PRODUCTION:-0}" == "1" ]]; then
    check "PRODUCCIÓN · robots.txt NO contiene 'Disallow: /' (el sitio se indexa)" \
        "$(grep -qx 'Disallow: /' <<<"$robots" && echo 1 || echo 0)"
else
    check "GUARDA 4 · robots.txt contiene 'Disallow: /'" \
        "$(grep -qx 'Disallow: /' <<<"$robots" && echo 0 || echo 1)"
fi

# ── GUARDA 7 · el KIT DE ILUSTRACIÓN instalado sigue siendo servible ──────────────────────────
# `specs/hueco-ilustracion.md` §5. ⚠️⚠️ **Este es el ÚNICO punto del sistema que ve el fichero
# REAL.** `kit:build` valida al INSTALAR, pero si alguien copia un sprite a mano en el servidor no
# lo revisa nadie — y las fugas de este hueco son SILENCIOSAS: medido en navegador, un `<style>`
# interno del cliente GANA al color que pone el producto, y con un símbolo que trae
# `fill="currentColor"` el tratamiento troquel pasa a pintar el 100 % de la caja. En los tres casos
# el dibujo aparece y nada falla.
#
# ⚠️ **Sin kit NO falla, y es a propósito**: es el estado normal de una instalación que no trae
# ilustración, y el hueco existe justamente para poder no tenerlo. Lo que esta guarda persigue es un
# kit PRESENTE y ROTO. Fail-closed por SSH: si la orden no llega, el código de salida no es 0.
kit_out=$(remote_php "artisan kit:build --check" 2>&1); kit_rc=$?
check "GUARDA 7 · $(head -1 <<<"$kit_out")" "$kit_rc"

# ── GUARDA 8 · lo que el servidor DICE que corre es lo que se acaba de desplegar ────────────────
# Fail-closed: si la lectura falla, `remote_version` queda vacío y la comprobación cae.
remote_version=$(sshx "cat '$REMOTE_ROOT/storage/app/version'" 2>/dev/null | cut -d' ' -f1 || true)
check "GUARDA 8 · versión escrita en el servidor: ${remote_version:-?} (esperada $DEPLOY_VERSION)" \
    "$([[ "$remote_version" == "$DEPLOY_VERSION" ]] && echo 0 || echo 1)"

# ⚠️ `grep -c X || echo 0` imprime DOS ceros cuando no hay coincidencias: `grep -c` ya emite «0» y
# ADEMÁS sale con 1, así que el `||` añade otro. Eso rompió la comprobación de migraciones el
# 2026-08-19 (`0\n0` != `0` → rojo con el sitio sano). `|| true` conserva el 0 y traga el código.
tasks=$(remote_php "artisan schedule:list" 2>/dev/null | grep -c 'artisan' || true)
# ⚠️ Esto mide el REGISTRO, no la EJECUCIÓN: dice que la app conoce sus 5 tareas, NO que nadie las
# dispare. Se deja porque sigue valiendo, pero con el nombre correcto — leerlo como «el scheduler
# funciona» es justo lo que pasó el 2026-08-21 (`DECISIONES #115`).
# Son 6 desde `#491` (`social-proof:refresh`): el 2026-09-11 el despliegue a staging salió en ROJO
# con el sitio sano porque este número seguía en 5. Si añades una tarea a `routes/console.php`,
# súbelo aquí en el mismo commit: la cifra es una PROPIEDAD del repo, no del servidor.
check "scheduler: $tasks tareas REGISTRADAS en la app (esperadas 6)" "$([[ "$tasks" == "6" ]] && echo 0 || echo 1)"

migr=$(remote_php "artisan migrate:status" 2>/dev/null | grep -c 'Pending' || true)
check "migraciones pendientes: $migr (esperadas 0)" "$([[ "$migr" == "0" ]] && echo 0 || echo 1)"

failed=$(remote_php "artisan tinker --execute='echo DB::table(\"failed_jobs\")->count();'" 2>/dev/null | tr -dc '0-9' || echo 0)
check "failed_jobs: ${failed:-0}" "$([[ "${failed:-0}" == "0" ]] && echo 0 || echo 1)"

# ── ¿ALGUIEN DISPARA el scheduler? — la comprobación que faltaba (`DECISIONES #115`) ──────────────
# ⚠️⚠️ MEDIDO el 2026-08-21: el crontab estaba instalado y correcto, `schedule:run` funcionaba a
# mano, y aun así **nada lo invocaba**: no hay demonio cron en el contenedor del sitio. 6 avisos
# llevaban 24 h en `jobs` con `attempts=0` y un pedido llevaba 24 h sin caducar.
#
# ⚠️ Y las DOS comprobaciones que ya había dieron VERDE: «5 tareas registradas» mide el registro, y
# `failed_jobs` **no puede ver esto** —un job que nunca se INTENTA nunca falla—. El comentario de
# `routes/console.php` mandaba vigilar `failed_jobs` precisamente para este caso, y es ciego a él.
#
# La señal que SÍ lo ve es la EDAD del trabajo más viejo de la cola: con el worker vivo, `jobs` se
# drena cada minuto, así que un job disponible desde hace más de 5 minutos significa que nadie lo
# está sacando. Fail-closed: si la lectura falla, `stale` queda vacío y la comprobación cae.
stale=$(remote_php "artisan tinker --execute='echo DB::table(\"jobs\")->where(\"available_at\", \"<\", time() - 300)->count();'" 2>/dev/null | tr -dc '0-9')
check "cola: ${stale:-?} jobs VARADOS >5 min (esperados 0 — si hay, nadie corre \`schedule:run\`)" \
    "$([[ "${stale}" == "0" ]] && echo 0 || echo 1)"

# Señal secundaria, informativa: sin demonio cron, el crontab que acabamos de escribir es papel
# mojado. No se hace fallar por esto —un host puede disparar el scheduler por otro medio— pero se
# DICE, porque es la causa raíz que costó media sesión encontrar.
if ! sshx 'pgrep -x cron >/dev/null 2>&1 || pgrep -x crond >/dev/null 2>&1'; then
    warn "No se ve ningún demonio cron en el servidor: el crontab instalado puede no ejecutarse nunca."
    warn "Compruébalo en el panel (sección de tareas programadas del sitio) — DECISIONES #115."
fi

printf '\n'
if [[ $fails -gt 0 ]]; then
    die "$fails comprobación(es) de salud FALLARON. El despliegue terminó pero el sitio NO está sano."
fi

printf '%s════════════════════════════════════════════════════════════════════%s\n' "$c_grn" "$c_off"
printf '%s ✓ DESPLIEGUE COMPLETO Y VERIFICADO — %s%s\n' "$c_grn" "$SITE_URL" "$c_off"
printf '%s════════════════════════════════════════════════════════════════════%s\n\n' "$c_grn" "$c_off"
info "versión desplegada: $DEPLOY_VERSION · commit $(git rev-parse --short HEAD)"
dim "Recuerda: staging es 0 LIVE · 0 PRODUCCIÓN. Y «verificado aquí» ≠ «verificado en MySQL»:"
dim "la BD es MariaDB 11.4, así que ninguna conclusión sobre concurrencia sale de este servidor."
