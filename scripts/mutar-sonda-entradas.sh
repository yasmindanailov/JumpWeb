#!/usr/bin/env bash
# Arnés de mutación de KIDS Y JUMP (T4f de `specs/isla-y-landing-nueva.md` §4.12): la guarda es `scripts/sonda-entradas.mjs`
# —las dos páginas del molde de entradas en un navegador, cada dato contra su hecho de la API—, y cada mutación mete UN defecto
# realista en el modelo, las piezas o los textos de la instancia: una cifra tecleada donde iba su hecho, un filtro que se
# pierde, una marca que se cae. Cada una tiene que tumbar SU comprobación (la salida dice cuál cae).
#
# ⚠️ Muta la INSTANCIA (`../instancias/playjump`, montada en el contenedor: el producto lee sus vistas, su modelo y sus
# textos al pintar). Sin compilar nada; Blade recompila la vista por su fecha (por eso el `touch` al restaurar).
# ⚠️ Se ESPERA 3 s tras mutar y tras restaurar: opcache revalida los ficheros cada 2 s (`opcache.revalidate_freq`, la
# trampa pagada en `mutar-sonda-visitanos.sh`).
# ⚠️ Antes de cada corrida se VACÍA el limitador de la API de la IP del contenedor: una corrida hace ~25 peticiones sin
# sesión y el suelo es de 60 por minuto (`ApiServiceProvider`), así que tres seguidas lo agotan.
# ⚠️ La de «[Hoy] del horario» solo muerde con el parque abierto (de la apertura al cierre de hoy): fuera de esa hora la
# línea dice que ya cerró y esa mutación no se ve. El arnés lo dice en vez de contarla.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la mutación SE APLICÓ ·
# restaurar por COPIA DE SEGURIDAD (por RUTA, nunca por `basename`) y `touch`, nunca con `git checkout`.
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

ANCHO="${1:-390}"
INSTANCIA="${INSTANCIA:-../instancias/playjump}"
DC="docker compose exec -u sail -T"
RUN="$DC -e PLAYWRIGHT_BROWSERS_PATH=/home/sail/pw-browsers laravel.test node scripts/sonda-entradas.mjs ${ANCHO}"
LIMITADOR="$DC laravel.test php artisan tinker --execute=Illuminate\\Support\\Facades\\RateLimiter::clear(md5('api'.'ip:127.0.0.1'));"

MODELO="$INSTANCIA/web/entradas/modelo.php"
PIEZA4="$INSTANCIA/web/entradas/pieza-4.blade.php"
PIEZA8="$INSTANCIA/web/entradas/pieza-8.blade.php"
TEXTOS_EN="$INSTANCIA/lang/en/paginas.php"
FICHEROS=("$MODELO" "$PIEZA4" "$PIEZA8" "$TEXTOS_EN")

TMP="$(mktemp -d)"
copia() { echo "$TMP/${1//\//__}"; }
restaurar() { for f in "${FICHEROS[@]}"; do cp "$(copia "$f")" "$f"; touch "$f"; done; sleep 3; }
for f in "${FICHEROS[@]}"; do cp "$f" "$(copia "$f")"; done
trap 'restaurar; rm -rf "$TMP"' EXIT

# La salida de cada corrida, para decir QUÉ comprobación cae: que muerda por la razón que la mutación rompe.
SALIDA="$TMP/salida.txt"
verde() { $LIMITADOR >/dev/null 2>&1; $RUN >"$SALIDA" 2>&1; }

if ! verde; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    sed 's/^/    /' "$SALIDA" | grep '✗' >&2
    exit 1
fi
echo "✓ base verde (sonda-entradas a ${ANCHO})"

muerden=0; total=0

# `SOLO=<trozo del nombre>`: solo esa mutación, y con la salida ENTERA de la sonda (para depurar una que no muerde).
SOLO="${SOLO:-}"

mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4"
    if [[ -n "$SOLO" && "$nombre" != *"$SOLO"* ]]; then return; fi
    total=$((total + 1))
    if [[ "$(grep -cF -- "$buscar" "$fichero")" != 1 ]]; then
        echo "  ⚠ «$nombre» NO APLICA: el ancla no está UNA vez en $fichero"
        return
    fi
    python3 -c 'import sys; p=sys.argv[1]; s=open(p,encoding="utf-8").read(); open(p,"w",encoding="utf-8").write(s.replace(sys.argv[2], sys.argv[3], 1))' \
        "$fichero" "$buscar" "$poner"
    if cmp -s "$fichero" "$(copia "$fichero")"; then
        echo "  ⚠ «$nombre» NO SE APLICÓ (el patrón no casa): el veredicto no vale"
        return
    fi
    touch "$fichero"
    sleep 3
    if verde; then
        echo "  ✗ NO muerde: $nombre"
        if [[ -n "$SOLO" ]]; then sed 's/^/                 /' "$SALIDA"; fi
    else
        echo "  ✓ muerde:    $nombre"
        grep '^✗' "$SALIDA" | head -3 | sed 's/^/                 /'
        muerden=$((muerden + 1))
    fi
    cp "$(copia "$fichero")" "$fichero"; touch "$fichero"
    sleep 3
}

# ── La página ─────────────────────────────────────────────────────────────────────────────────────────────────────
mutar "la ciudad del título, tecleada" "$MODELO" \
  "'ciudad' => \$hechos['site']['address']['city'] ?? null," \
  "'ciudad' => 'Murcia',"

mutar "el «desde» de la zona, el de la entrada más CARA" "$MODELO" \
  "\$desde = \$productos->pluck('from_price_cents')->filter(fn (\$c): bool => \$c !== null)->min();" \
  "\$desde = \$productos->pluck('from_price_cents')->filter(fn (\$c): bool => \$c !== null)->max();"

mutar "la foto para compartir se pierde" "$MODELO" \
  "\$z['media']['foto'] = \$zonaHecho['image_url'] ?? null;" \
  "\$z['media']['foto'] = null;"

# ── La cabecera ───────────────────────────────────────────────────────────────────────────────────────────────────
mutar "«Hoy» en la tarifa que no es la de hoy" "$MODELO" \
  "\$filaHoy = \$abreHoy ? (\$tarifaHoyEspecial && \$especial !== null ? 1 : 0) : null;" \
  "\$filaHoy = \$abreHoy ? (\$tarifaHoyEspecial && \$especial !== null ? 0 : 1) : null;"

mutar "el botón no mira los huecos de hoy" "$MODELO" \
  "\$huecos = \$abreHoy && collect(\$hechos['availability_today'] ?? [])" \
  "\$huecos = false && collect(\$hechos['availability_today'] ?? [])"

mutar "el plazo, tecleado («48 h»)" "$MODELO" \
  "'plazo' => \$base['cancellation']['written'] ?? null," \
  "'plazo' => 'hasta 48 h antes',"

mutar "la unidad de la tarifa, tecleada («por niño» también en Jump)" "$MODELO" \
  "'unidad' => \$precios[\$base['id'] ?? 0]['unit'] ?? (\$base['period_label'] ?? '')," \
  "'unidad' => 'por niño',"

# ── El precio ─────────────────────────────────────────────────────────────────────────────────────────────────────
mutar "el ahorro de la de 2 horas, mal contado" "$MODELO" \
  "? null : 2 * \$una[\$i] - \$cent[\$i])" \
  "? null : 3 * \$una[\$i] - \$cent[\$i])"

mutar "los días de la tarifa especial, tecleados" "$MODELO" \
  "'dias_especiales' => \$especial === null ? null : \$minuscula(\$especial['label'])," \
  "'dias_especiales' => 'viernes y fines de semana',"

mutar "la calculadora sin los calcetines de su ficha" "$MODELO" \
  "'calcetin' => \$esta['calcetin'] === null ? null :" \
  "'calcetin' => true ? null :"

# ── La zona ───────────────────────────────────────────────────────────────────────────────────────────────────────
mutar "las atracciones de las DOS zonas en cada página" "$MODELO" \
  "collect(\$hechos['attractions']['attractions'] ?? [])->where('zone', \$zona)" \
  "collect(\$hechos['attractions']['attractions'] ?? [])"

mutar "el «play» también en las que solo tienen foto" "$PIEZA4" \
  "'src' => \$a['video'] ?? null]" \
  "'src' => \$a['video'] ?? \$a['foto'] ?? null]"

# ── Tranquilidad ──────────────────────────────────────────────────────────────────────────────────────────────────
mutar "la altura de Kids, tecleada (1,40 m)" "$MODELO" \
  "'altura_max' => isset(\$zonaHecho['height']['up_to_cm']) ? \$altura(\$zonaHecho['height']['up_to_cm']) : null," \
  "'altura_max' => \$altura(140),"

mutar "las voces, sin filtrar por la zona" "$MODELO" \
  "->filter(fn (array \$r): bool => in_array(\$zona, \$r['tags'] ?? [], true))" \
  "->filter(fn (array \$r): bool => true)"

# ── Dónde y cuándo ────────────────────────────────────────────────────────────────────────────────────────────────
mutar "la hora del fin de semana del titular de Kids, tecleada (9)" "$MODELO" \
  "? \$hora(\$sabado['opens_at']) : null," \
  "? \$hora('09:00') : null,"

mutar "la dirección, sin la ciudad" "$MODELO" \
  "\$z['p6']['direccion'] = trim((\$direccion['line1'] ?? '').(isset(\$direccion['city']) ? ', '.\$direccion['city'] : '')).'.';" \
  "\$z['p6']['direccion'] = trim(\$direccion['line1'] ?? '').'.';"

if [[ -z "$SOLO" || "[Hoy]" == *"$SOLO"* ]]; then
    # ¿Hoy abre y aún no ha cerrado? De `/schedule` (el día especial manda sobre el de la semana), en la hora del parque.
    ABIERTO="$(curl -s -H 'Accept: application/json' "${WEB:-http://localhost:8081}/api/v1/schedule" | python3 -c '
import datetime, json, sys, zoneinfo
s = json.load(sys.stdin)
n = datetime.datetime.now(zoneinfo.ZoneInfo(s.get("timezone") or "Europe/Madrid"))
d = next((x for x in s.get("special_days", []) if x["date"] == n.strftime("%Y-%m-%d")), None) \
    or next((x for x in s.get("weekly", []) if x["weekday"] == n.isoweekday() % 7), None)
print(1 if d and not d["closed"] and n.strftime("%H:%M") < d["closes_at"] else 0)' 2>&1)"
    if [[ "$ABIERTO" == 1 ]]; then
        mutar "[Hoy] del horario, con las horas del revés" "$MODELO" \
          "'comun.hoy_abrimos', ['abre' => \$horarioHoy['opens_at'], 'cierra' => \$horarioHoy['closes_at']]" \
          "'comun.hoy_abrimos', ['abre' => \$horarioHoy['closes_at'], 'cierra' => \$horarioHoy['opens_at']]"
    else
        echo "  · «[Hoy] del horario» no se cuenta: el parque ya cerró hoy (o no pude saberlo: «$ABIERTO»)"
    fi
fi

# ── Las dudas ─────────────────────────────────────────────────────────────────────────────────────────────────────
mutar "la altura con la que Jump pide adulto, tecleada (1,20 m)" "$MODELO" \
  "'escolta_altura' => isset(\$escolta['below_cm']) ? \$altura(\$escolta['below_cm']) : null," \
  "'escolta_altura' => \$altura(120),"

mutar "los calcetines, a precio tecleado" "$MODELO" \
  "'calcetines' => \$calcetines === null ? null : \$dinero(\$calcetines['price_cents'] / 100)," \
  "'calcetines' => \$calcetines === null ? null : \$dinero(2.5),"

mutar "el cumpleaños, el mismo pack para las dos zonas" "$MODELO" \
  "&& isset(\$base['guest_age_min']) && (\$p['guest_age_min'] ?? null) === \$base['guest_age_min']);" \
  ");"

# ── El cierre, el pie y la isla ───────────────────────────────────────────────────────────────────────────────────
mutar "el WhatsApp del cierre, sin su mensaje" "$PIEZA8" \
  "\$contacto['whatsapp'].'?text='.rawurlencode(\$z['p8']['whatsapp'])" \
  "\$contacto['whatsapp']"

mutar "la promesa de Bizum, encendida" "$MODELO" \
  "'bizum' => false," \
  "'bizum' => true,"

mutar "el pie enlaza a la página en la que se está" "$MODELO" \
  "'href' => \$p[1] === route('instancia.'.\$zona) ? '#' : \$p[1]]" \
  "'href' => \$p[1]]"

mutar "la isla, sin la zona activa en su menú" "$MODELO" \
  "'active' => \$p[1] === route('instancia.'.\$zona)]" \
  "'active' => false]"

# ── En inglés ─────────────────────────────────────────────────────────────────────────────────────────────────────
mutar "un marcador que el modelo no llena, en inglés" "$TEXTOS_EN" \
  "'My child is :edad_bajo: can they come in?'" \
  "'My child is :edad_menor: can they come in?'"

echo "$muerden/$total muerden"
[[ $muerden -eq $total ]]
