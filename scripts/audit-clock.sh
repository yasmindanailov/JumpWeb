#!/usr/bin/env bash
# =============================================================================
# JumpWeb — AUDITORÍA DEL RELOJ de la suite (`DECISIONES #162`)
# -----------------------------------------------------------------------------
# Corre la suite entera varias veces con el reloj CONGELADO en fechas frontera, y
# dice cuáles se ponen rojas. Existe porque la suite ha amanecido roja DOS veces
# sin que nadie tocara nada (`DECISIONES #64` y `#97`), y de la segunda ni siquiera
# se supo qué falló: la salida se perdió y el reintento salió verde.
#
# ⚠️⚠️ **LAS FECHAS SE CALCULAN RELATIVAS A HOY, Y ESO ES EL DISEÑO.** Una lista de
# fechas fijas caduca igual que los fixtures que persigue. Lo aprendimos aquí: el
# primer fallo que encontró esta auditoría no era «los sábados» — era un fixture con
# una ventana de temporada escrita a mano (`2026-07-01`…`2026-08-31`) que **iba a
# romper la suite el 1 de septiembre**, seis días después de medirlo, sin que nadie
# tocara nada. Un conjunto de fechas fijo no lo habría vuelto a encontrar.
#
# ⚠️ **NO entra en el `pre-push`**: son N pases completos de la suite (~2 min cada
# uno). Se corre a mano, y conviene hacerlo al cerrar una tanda que añada fixtures
# con calendario. `docs/TESTING.md` §2.septies.
#
# ⚠️ **Alcance**: solo llega a los tests que extienden `Tests\TestCase`. Los de
# `tests/Unit/` extienden `PHPUnit\Framework\TestCase` por convención
# (`CONVENCIONES §3.ter`) y el reloj NO les llega. Medido el 2026-08-26: son 4 y
# ninguno depende de la fecha, así que hoy no es un hueco — pero si algún día un
# test Unit gana calendario, esta auditoría no lo verá.
#
# Uso:
#   bash scripts/audit-clock.sh              # todas las fronteras
#   bash scripts/audit-clock.sh --filter=X   # una clase/patrón (rápido, para iterar)
# =============================================================================

set -uo pipefail
cd "$(git rev-parse --show-toplevel 2>/dev/null)" \
    || { echo '✗ audit-clock: fuera de un repo git' >&2; exit 1; }

FILTER=""
for arg in "$@"; do
    case "$arg" in
        --filter=*) FILTER="${arg#--filter=}" ;;
        *) echo "✗ audit-clock: opción desconocida «$arg»" >&2; exit 1 ;;
    esac
done

# ── Las fronteras, cada una con lo que cubre ─────────────────────────────────
# `date -d` hace la aritmética; todas se expresan en UTC, que es como las
# interpreta el hook de `Tests\TestCase`.
d() { date -u -d "$1" '+%Y-%m-%d %H:%M:%S'; }

declare -a ETIQUETAS=() INSTANTES=()
add() { ETIQUETAS+=("$1"); INSTANTES+=("$2"); }

add "próximo sábado"        "$(d 'next Saturday 12:00')"
add "próximo domingo"       "$(d 'next Sunday 12:00')"
add "próximo lunes"         "$(d 'next Monday 12:00')"
# Fixtures con ventanas escritas a mano: caducan solos. Es lo que encontró el
# primer pase de esta auditoría, y por eso hay tres horizontes y no uno.
add "dentro de 1 mes"       "$(d 'today +1 month 12:00')"
add "dentro de 6 meses"     "$(d 'today +6 months 12:00')"
add "dentro de 1 año"       "$(d 'today +1 year 12:00')"
# Bordes de calendario.
add "fin de mes"            "$(d "$(date -u -d 'today +1 month' '+%Y-%m-01') -1 day 23:59:30")"
add "fin de año"            "$(d "$(date -u -d 'today' '+%Y')-12-31 23:59:30")"
# ⚠️ LAS DOS MEDIANOCHES. La app guarda en UTC y MUESTRA en `Europe/Madrid`
# (`DisplayTime::DEFAULT_TIMEZONE`), así que hay dos fronteras distintas y solo una
# se había barrido: `#64` fue la de UTC; `#97` cayó a las 00:02 de Madrid, que en
# verano son las 22:02 UTC y NO es un cruce de medianoche UTC.
add "medianoche UTC"        "$(d 'tomorrow 23:59:30')"
add "medianoche de MADRID"  "$(date -u -d "$(TZ=Europe/Madrid date -d 'tomorrow 23:59:30' '+%Y-%m-%d %H:%M:%S') $(TZ=Europe/Madrid date -d 'tomorrow' '+%z')" '+%Y-%m-%d %H:%M:%S')"

# ── Ejecución ────────────────────────────────────────────────────────────────
FAIL=0

# `printf %-24s` cuenta BYTES, no caracteres: con acentos la tabla se descuadra. `${#s}` sí
# cuenta caracteres bajo un locale UTF-8, así que el relleno se calcula a mano.
pad() { local s="$1" n="$2"; printf '%s%*s' "$s" $(( n - ${#s} > 0 ? n - ${#s} : 0 )) ''; }

printf '\n%s %s %s\n' "$(pad 'FRONTERA' 24)" "$(pad 'INSTANTE (UTC)' 21)" 'RESULTADO'
printf '%s\n' '----------------------------------------------------------------------------------'

for i in "${!ETIQUETAS[@]}"; do
    etiqueta="${ETIQUETAS[$i]}"; instante="${INSTANTES[$i]}"
    log="$(mktemp)"

    # Con `--filter` NO se paraleliza: paratest reparte por fichero y con un solo caso el
    # arranque de los workers cuesta más que el test.
    local_args=()
    if [[ -n "$FILTER" ]]; then local_args=(--filter "$FILTER"); else local_args=(--parallel); fi

    docker compose exec -u sail -T -e TEST_CLOCK="$instante" laravel.test \
        php artisan test "${local_args[@]}" > "$log" 2>&1
    code=$?

    resumen=$(sed 's/\x1b\[[0-9;]*m//g' "$log" | grep -aoE 'Tests:.*' | tail -1)
    [[ -z "$resumen" ]] && resumen='(la suite no llegó a informar — mira el log)'

    if [[ $code -eq 0 ]]; then
        printf '%s %s ✓ %s\n' "$(pad "$etiqueta" 24)" "$(pad "$instante" 21)" "$resumen"
        rm -f "$log"
    else
        FAIL=1
        printf '%s %s ✗ %s\n' "$(pad "$etiqueta" 24)" "$(pad "$instante" 21)" "$resumen"
        # ⚠️ Los nombres se extraen de DOS formas a propósito: `php artisan test` los lista como
        # «FAILED  Clase > caso» y el bloque final de PHPUnit como «N) Clase::caso». Con `--parallel`
        # sale uno y con `--filter` el otro, y una extracción que solo conociera una **dejaba la lista
        # de culpables VACÍA justo cuando hace falta** — que es literalmente lo que costó no saber qué
        # falló en `#97`. Si ninguna casa, se dice y se manda al log en vez de callar.
        echo "   ▶ culpables:"
        culpables=$(sed 's/\x1b\[[0-9;]*m//g' "$log" \
            | grep -aoE '^[0-9]+\) [A-Za-z0-9_\\]+::[A-Za-z0-9_]+|FAILED +[A-Za-z0-9_\\]+ *> *[^ ]([^…]*)' \
            | sed -E 's/^[0-9]+\) //; s/^FAILED +//' | sort -u)
        if [[ -n "$culpables" ]]; then
            sed 's/^/     /' <<<"$culpables"
        else
            echo "     (no se pudo extraer el nombre — el formato de la salida cambió; mira el log)"
        fi
        echo "   ▶ log completo: $log"
    fi
done

# ── Segunda fase: el CRUCE de medianoche a mitad de ejecución ────────────────
# ⚠️⚠️ Las diez fronteras de arriba CONGELAN el reloj, y con el reloj congelado el tiempo
# no avanza nunca: por construcción **no pueden** reproducir el modo en que la suite cruza
# la medianoche MIENTRAS corre —un test que lee `today()` dos veces y obtiene días
# distintos—. Ésa era la hipótesis que se le dio a `#97` y no la había probado nadie.
# Con `TEST_CLOCK_START` el reloj corre desplazado: arrancando ~30 s antes de una
# medianoche, la suite la cruza a mitad de pase.
if [[ -z "$FILTER" ]]; then
    echo
    printf '%s %s %s\n' "$(pad 'CRUCE A MITAD DE PASE' 24)" "$(pad 'ARRANQUE (UTC)' 21)" 'RESULTADO'
    printf '%s\n' '----------------------------------------------------------------------------------'

    # 23:59:30 de Madrid en el offset de mañana (en verano, 21:59:30 UTC) y 23:59:30 UTC.
    madrid_utc=$(date -u -d "$(TZ=Europe/Madrid date -d 'tomorrow 23:59:30' '+%Y-%m-%d %H:%M:%S') $(TZ=Europe/Madrid date -d 'tomorrow' '+%z')" '+%Y-%m-%d %H:%M:%S')
    for par in "cruza medianoche MADRID|$madrid_utc" "cruza medianoche UTC|$(d 'tomorrow 23:59:30')"; do
        etiqueta="${par%%|*}"; inicio="${par#*|}"
        log="$(mktemp)"
        docker compose exec -u sail -T -e TEST_CLOCK_START="$inicio" laravel.test \
            php artisan test --parallel > "$log" 2>&1
        code=$?
        resumen=$(sed 's/\x1b\[[0-9;]*m//g' "$log" | grep -aoE 'Tests:.*' | tail -1)
        [[ -z "$resumen" ]] && resumen='(la suite no llegó a informar — mira el log)'
        if [[ $code -eq 0 ]]; then
            printf '%s %s ✓ %s\n' "$(pad "$etiqueta" 24)" "$(pad "$inicio" 21)" "$resumen"
            rm -f "$log"
        else
            FAIL=1
            printf '%s %s ✗ %s\n' "$(pad "$etiqueta" 24)" "$(pad "$inicio" 21)" "$resumen"
            echo "   ▶ log completo: $log"
        fi
    done
fi

echo
if [[ $FAIL -eq 0 ]]; then
    echo '✓ audit-clock: la suite es verde en todas las fronteras.'
else
    echo '✗ audit-clock: hay tests que dependen de CUÁNDO se ejecutan (arriba).'
    echo '  La regla y las dos causas conocidas están en `docs/TESTING.md` §2.'
    echo '  ⚠️ No regeneres el fixture sin mirar: congela el reloj con una constante documentada.'
    exit 1
fi
