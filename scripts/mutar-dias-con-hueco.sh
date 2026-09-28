#!/usr/bin/env bash
# Arnés de mutación de LOS PRÓXIMOS DÍAS CON HUECO de cualquier día (T6c·2 de `specs/isla-y-landing-nueva.md` §4.19):
# `availability_days` de `PageFacts`, el hermano de `availability_weekends` para la página de colegios —un día entre
# semana cuenta, cuenta si ALGÚN pack de la zona tiene hora, seis como mucho, tres semanas, cada día con su tarifa,
# guardado por día y aparte de su hermano, y servido pasado mientras se rehace (los dos hermanos)—. Los mutantes de la
# regla común que son de los fines de semana, en `mutar-hechos-de-la-fiesta.sh`.
#
# Reglas de la casa dentro (`/mutar`): verde antes de mutar · veredicto por código de salida · ancla ÚNICA · comprobar
# que la mutación SE APLICÓ · restaurar por COPIA DE SEGURIDAD (por ruta entera) y `touch`, nunca `git checkout`.
#
#   bash scripts/mutar-dias-con-hueco.sh        (desde el host, con el contenedor en marcha)
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

PHP="docker compose exec -u sail -T laravel.test php artisan test --filter=InstancePagesTest"

TMP="$(mktemp -d)"
FICHEROS=(
    app/Http/Instancia/PageFacts.php
)
copia() { echo "$TMP/$(echo "$1" | tr '/' '_')"; }
restaurar() { for f in "${FICHEROS[@]}"; do cp "$(copia "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$(copia "$f")"; done

verde() { $PHP >/dev/null 2>&1; }

if ! verde; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde'

muerden=0; total=0

mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4"
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
    cp "$(copia "$fichero")" "$fichero"; touch "$fichero"
}

PF=app/Http/Instancia/PageFacts.php
LLAMADA="self::DIAS_SEMANAS, self::DIAS_DIAS, soloFinDeSemana: false"

mutar "availability_days no es un hecho" "$PF" "        'availability_days' => [AvailabilityController::class, 'dates', false],
" ""
mutar "solo cuentan los fines de semana" "$PF" "$LLAMADA" "self::DIAS_SEMANAS, self::DIAS_DIAS, soloFinDeSemana: true"
mutar "sin tope de seis" "$PF" "$LLAMADA" "self::DIAS_SEMANAS, 99, soloFinDeSemana: false"
mutar "sin horizonte de tres semanas" "$PF" "$LLAMADA" "99, self::DIAS_DIAS, soloFinDeSemana: false"
mutar "solo mira el primer pack de la zona" "$PF" "collect(\$packsDelDia)->first(" "collect(array_slice(\$packsDelDia, 0, 1))->first("
mutar "un día que se vende cuenta aunque no tenga hueco" "$PF" "if (\$conHora === null) {" "if (false) {"
mutar "la regla común no se para" "$PF" "if (count(\$conHueco) === \$cuantos) {" "if (false) {"
mutar "la tarifa no viaja" "$PF" "'rate_key' => \$dia->rateKey" "'rate_key' => 'normal'"
mutar "comparte lo guardado con su hermano" "$PF" "'instancia:hechos:availability_days:'.\$hoy->toDateString()" "'instancia:hechos:availability_weekends:'.\$hoy->toDateString()"
mutar "lo guardado vale para siempre" "$PF" "'instancia:hechos:availability_days:'.\$hoy->toDateString()" "'instancia:hechos:availability_days'"
# Lo guardado se sirve pasado y se rehace tras responder (`Cache::flexible`), en los DOS hermanos.
mutar "los días esperan al cálculo en frío" "$PF" "Cache::flexible('instancia:hechos:availability_days:'.\$hoy->toDateString(), self::CON_HUECO_CACHE_S," "Cache::remember('instancia:hechos:availability_days:'.\$hoy->toDateString(), 300,"
mutar "los fines de semana esperan al cálculo en frío" "$PF" "Cache::flexible('instancia:hechos:availability_weekends:'.\$hoy->toDateString(), self::CON_HUECO_CACHE_S," "Cache::remember('instancia:hechos:availability_weekends:'.\$hoy->toDateString(), 300,"
mutar "lo pasado no se sirve nunca" "$PF" "private const CON_HUECO_CACHE_S = [300, 1800];" "private const CON_HUECO_CACHE_S = [300, 300];"
mutar "fresco media hora" "$PF" "private const CON_HUECO_CACHE_S = [300, 1800];" "private const CON_HUECO_CACHE_S = [1800, 1800];"

echo
echo "$muerden de $total mutantes muertos"
[[ "$muerden" == "$total" ]]
