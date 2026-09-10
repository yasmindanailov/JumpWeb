#!/usr/bin/env bash
# Arnés de mutación de la SECCIÓN 06 «Reseñas» · mitad `a`, las opiniones PROPIAS
# (`DECISIONES #490`, carril de diseño Fase 2 · T2i·a, `specs/rediseno-desde-canvas.md` §5.4).
#
# Las propiedades que protege, en una línea cada una:
#   · con cero opiniones activas la sección ENTERA desaparece;
#   · la cifra agregada NO se compone con opiniones propias —sería la nota de Google inventada—;
#   · una opinión propia no sale vestida de Google: ni avatar, ni enlace, ni la entradilla suya;
#   · la primera nace visible desde el SERVIDOR, que es el suelo sin JavaScript;
#   · los controles piden más de una opinión;
#   · la inicial se corta por caracteres y no por bytes;
#   · las estrellas leen `--attn-ink` y no `--attn` (1,5 de contraste sobre la tarjeta blanca);
#   · la portada pide el CONTRATO y no el modelo.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por CÓDIGO DE SALIDA (nunca
# `grep passed`) · comprobar que la mutación SE APLICÓ · restaurar por COPIA en RUTA FIJA que se
# repara al arrancar (`#448`: un `trap … EXIT` no corre con SIGKILL y deja el árbol mutado).
# ⚠️ Sin comillas invertidas en los rótulos: dentro de comillas dobles bash las ejecuta (`#489`).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

FILTER='ReviewsSectionTest|ModuleContractsTest'
RUN="docker compose exec -u sail -T laravel.test php artisan test --filter=${FILTER}"

TMP="storage/app/mutaciones/resenas"
FICHEROS=(
    resources/views/home.blade.php
    app/Http/Controllers/HomeController.php
    app/Domain/Content/Services/CmsSocialProof.php
    app/Domain/Content/Contracts/Testimonial.php
    public/css/landing.css
)
restaurar() {
    for f in "${FICHEROS[@]}"; do
        [ -f "$TMP/$(basename "$f")" ] || continue
        cp "$TMP/$(basename "$f")" "$f"; touch "$f"
    done
}

if [ -d "$TMP" ]; then
    sucios=0
    for f in "${FICHEROS[@]}"; do
        [ -f "$TMP/$(basename "$f")" ] || continue
        cmp -s "$f" "$TMP/$(basename "$f")" || sucios=$((sucios + 1))
    done
    if [ "$sucios" -gt 0 ]; then
        echo "⚠️  Una ejecución anterior murió sin restaurar: ${sucios} fichero(s) MUTADOS en el árbol."
        restaurar
        echo '✓ restaurados desde la copia. Comprueba con `git diff` antes de seguir.'
    fi
    rm -rf "$TMP"
fi

mkdir -p "$TMP"
trap 'restaurar; rm -rf "$TMP"' EXIT INT TERM
for f in "${FICHEROS[@]}"; do cp "$f" "$TMP/$(basename "$f")"; done

verde() { $RUN >/dev/null 2>&1; }

if ! verde; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde'
echo

muerden=0; total=0

mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4"
    total=$((total + 1))

    if [ ! -f "$TMP/$(basename "$fichero")" ]; then
        echo "✗ «$nombre» quiere mutar «$fichero», que NO está en FICHEROS: sin copia no hay" >&2
        echo "  restauración, y el veredicto de toda la tanda deja de valer. Añádelo al array." >&2
        exit 1
    fi
    python3 -c 'import sys; p=sys.argv[1]; s=open(p,encoding="utf-8").read(); open(p,"w",encoding="utf-8").write(s.replace(sys.argv[2], sys.argv[3], 1))' \
        "$fichero" "$buscar" "$poner"
    if cmp -s "$fichero" "$TMP/$(basename "$fichero")"; then
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
    cp "$TMP/$(basename "$fichero")" "$fichero"; touch "$fichero"
}

HB=resources/views/home.blade.php
HC=app/Http/Controllers/HomeController.php
CS=app/Domain/Content/Services/CmsSocialProof.php
DT=app/Domain/Content/Contracts/Testimonial.php
CSS=public/css/landing.css

echo '── El panel vacío ──'

mutar "la sección se pinta sin ninguna opinión" "$HB" \
  '    @if ($socialProof->isNotEmpty())' \
  '    @if (true)'

echo
echo '── La cifra agregada ──'

# La media de lo que el parque ha escrito de sí mismo es un número REAL, y publicarlo junto a las
# cinco estrellas del sistema lo hace pasar por la nota de Google.
mutar "el respaldo compone su propia media agregada" "$CS" \
  '    public function rating(): ?Rating
    {
        return null;
    }' \
  '    public function rating(): ?Rating
    {
        return new Rating(5.0, Testimonial::query()->where("is_active", true)->count(), null, "cms");
    }'

echo
echo '── La atribución no se hereda ──'

mutar "una opinión propia sale con enlace a Google" "$CS" \
  '                url: null,' \
  '                url: "https://maps.google.com/?cid=1",'

mutar "la entradilla pasa a ser la de Google" "$HB" \
  "{{ __('landing.reviews.lede_own') }}" \
  "{{ __('landing.reviews.lede_google') }}"

echo
echo '── El suelo sin JavaScript ──'

# Con `x-show` + `x-cloak` la sección se queda VACÍA sin JS. Aquí el equivalente: que ninguna nazca.
mutar "ninguna opinión nace visible sin JavaScript" "$HB" \
  "{{ \$k === 0 ? ' is-on' : '' }}" \
  "{{ '' }}"

mutar "todas las opiniones nacen visibles a la vez" "$HB" \
  "{{ \$k === 0 ? ' is-on' : '' }}" \
  "{{ ' is-on' }}"

echo
echo '── Los controles ──'

mutar "los controles salen con una sola opinión" "$HB" \
  '@if ($socialProof->count() > 1)' \
  '@if ($socialProof->count() > 0)'

mutar "la nota pierde su nombre accesible" "$HB" \
  '                                    <p class="rev__stars" role="img"' \
  '                                    <p class="rev__stars"'

echo
echo '── La inicial y el contraste ──'

mutar "la inicial se corta por BYTES" "$DT" \
  'mb_strtoupper(mb_substr($limpio, 0, 1))' \
  'strtoupper(substr($limpio, 0, 1))'

mutar "las estrellas vuelven al relleno del aviso" "$CSS" \
  '.rev__stars-on { color: var(--attn-ink); }' \
  '.rev__stars-on { color: var(--attn); }'

echo
echo '── La portada pide el contrato ──'

mutar "la portada vuelve a consultar el modelo" "$HC" \
  "            'socialProof' => app(SocialProof::class)->testimonials()," \
  "            'socialProof' => app(SocialProof::class)->testimonials(),
            'cuantas' => \\App\\Domain\\Content\\Models\\Testimonial::count(),"

echo
echo "── ${muerden}/${total} mutaciones muerden ──"
[ "$muerden" -eq "$total" ] || exit 1
