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
#   · la portada pide el CONTRATO y no el modelo;
#   · Google se pide en UN idioma, las tres versiones lo leen sin fingirlo y la caché dura más que
#     el hueco entre dos refrescos (`#591`).
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por CÓDIGO DE SALIDA (nunca
# `grep passed`) · comprobar que la mutación SE APLICÓ · restaurar por COPIA en RUTA FIJA que se
# repara al arrancar (`#448`: un `trap … EXIT` no corre con SIGKILL y deja el árbol mutado).
# ⚠️ Sin comillas invertidas en los rótulos: dentro de comillas dobles bash las ejecuta (`#489`).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

FILTER='ReviewsSectionTest|SocialProofNeverHitsTheRenderPathTest|ModuleContractsTest'
RUN="docker compose exec -u sail -T laravel.test php artisan test --filter=${FILTER}"

TMP="storage/app/mutaciones/resenas"
FICHEROS=(
    resources/views/home.blade.php
    app/Http/Controllers/HomeController.php
    app/Domain/Content/Services/CmsSocialProof.php
    app/Domain/Content/Contracts/Testimonial.php
    public/css/landing.css
    app/Domain/Content/Services/GoogleSocialProof.php
    app/Domain/Content/Services/FallingBackSocialProof.php
    app/Console/Commands/RefreshSocialProof.php
    routes/console.php
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
GS=app/Domain/Content/Services/GoogleSocialProof.php
FB=app/Domain/Content/Services/FallingBackSocialProof.php
CM=app/Console/Commands/RefreshSocialProof.php
RC=routes/console.php

echo '── El panel vacío ──'

mutar "la sección se pinta sin ninguna opinión" "$HB" \
  '    @if ($socialProof->isNotEmpty() || $resenasPorPermiso)' \
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

# ⚠️ La entradilla dejó de ser un literal al entrar la cifra: es un ternario. Se fuerza la rama.
# ⚠️ `#592` metió la condición entre paréntesis (`… || $resenasPorPermiso`). La mutación de antes ponía un
# ternario delante SIN paréntesis, que en PHP 8 es un error de compilación: mordía por el FATAL y no por
# la regla. Ésta deja el código válido y la condición siempre cierta.
mutar "la entradilla pasa a ser la de Google" "$HB" \
  '<p class="sec-head__lede">{{ ($socialProof->first()?->source' \
  '<p class="sec-head__lede">{{ (true || $socialProof->first()?->source'

# El defecto REAL que encontró la captura: la entradilla atada a la CHAPA y no a las opiniones, con
# lo que la sección dice «no las elegimos nosotros» sobre una opinión propia.
mutar "la entradilla se ata a la chapa y no a las opiniones" "$HB" \
  '{{ ($socialProof->first()?->source === \App\Domain\Content\Contracts\Testimonial::SOURCE_GOOGLE' \
  '{{ ($socialRating'

echo
echo '── El suelo sin JavaScript ──'

# Con `x-show` + `x-cloak` la sección se queda VACÍA sin JS. Aquí el equivalente: que ninguna nazca.
# ⚠️ Estos dos casos mutaban `is-on`, la clase de la PILA, y llevaban sin aplicarse desde `#549`, que
# retiró la pila por un carril: el arnés lo decía («NO SE APLICÓ») y nadie lo había vuelto a correr.
# Hoy la propiedad es que el servidor sirve TODAS las opiniones y ninguna nace escondida.
mutar "el servidor sirve una sola opinión" "$HB" \
  '                    @foreach ($socialProof as $k => $op)' \
  '                    @foreach ($socialProof->take(1) as $k => $op)'

mutar "vuelve la clase de la pila" "$HB" \
  '                        <article class="rev__card">' \
  '                        <article class="rev__card is-on">'

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
echo '── Google: el camino del render ──'

# La mutación NATURAL: el `Cache::remember` que uno escribiría sin pensar, y que mete la latencia de
# un tercero en el camino crítico de la portada.
mutar "la portada llama a Google cuando la caché está fría" "$GS" \
  '        $datos = Cache::get(self::cacheKey());' \
  '        $datos = Cache::remember(self::cacheKey(), 60, fn () => $this->refresh()->ok() ? [] : null);'

echo
echo '── Google: el umbral y la caché ──'

mutar "el umbral desaparece" "$GS" \
  '        if ($count < self::MIN_REVIEWS || $value <= 0) {' \
  '        if ($count < 0 || $value <= 0) {'

mutar "la caché conserva una cifra que ya no es publicable" "$GS" \
  '            Cache::forget(self::cacheKey());

            return new SocialProofRefresh(SocialProofRefresh::BELOW_THRESHOLD);' \
  '            return new SocialProofRefresh(SocialProofRefresh::BELOW_THRESHOLD);'

mutar "una reseña sin autor se publica igual" "$GS" \
  "            if (\$texto === '' || \$autor === '') {" \
  "            if (\$texto === '') {"

mutar "una URL de tercero llega sin sanear" "$GS" \
  "        return (\$valor !== '' && preg_match('#^https?://#i', \$valor) === 1) ? \$valor : null;" \
  "        return \$valor !== '' ? \$valor : null;"

echo
echo '── Google: el idioma ──'

# El defecto REAL que encontró renderizar con la reseña de verdad: sin `languageCode`, Google
# contesta «a week ago» y la portada en español lo publica tal cual.
mutar "no se le pide a Google ningún idioma" "$GS" \
  "['languageCode' => \$locale]" \
  '[]'

# `#591`: el idioma es el de la INSTALACIÓN. Sacado de la petición en curso, el comando —que corre sin
# página— pediría el que le tocara.
mutar "el idioma sale de la petición y no de la instalación" "$GS" \
  '        $locale = self::sourceLocale();' \
  '        $locale = app()->getLocale();'

# Y su otra mitad: UNA caché que leen las tres versiones. Con una por idioma, dos se quedan sin reseñas.
mutar "cada versión busca su propia caché" "$GS" \
  '        return self::CACHE_PREFIX.self::sourceLocale();' \
  '        return self::CACHE_PREFIX.app()->getLocale();'

mutar "otra versión publica la fecha que Google escribió en el idioma de la caché" "$GS" \
  "                when: \$mismoIdioma ? \$r['when'] : RelativeAge::of(self::instant(\$r['published'] ?? null))," \
  "                when: \$r['when'],"

mutar "el texto no dice su idioma fuera de él" "$GS" \
  '                language: ($delAutor || $mismoIdioma) ? null : $idiomaCache,' \
  '                language: null,'

mutar "la tarjeta no pinta el idioma del texto" "$HB" \
  '                                   @if ($op->language) lang="{{ $op->language }}" @endif' \
  ''

mutar "se enseña la traducción aunque la página hable el idioma del autor" "$GS" \
  '            $delAutor = $original !== null && self::sameLanguage($original->language, $pagina);' \
  '            $delAutor = false;'

mutar "en-US deja de ser inglés" "$GS" \
  "explode('-', str_replace('_', '-', trim(\$codigo)))[0]" \
  'trim($codigo)'

echo
echo '── Google: la cadencia (#591) ──'

# El defecto que vio el owner: una caché más corta que el hueco entre dos refrescos apaga las reseñas
# un rato de cada ciclo sin que falle nada. La guarda lee las DOS mitades; hay que morder por las dos.
mutar "la caché vuelve a durar lo mismo que el hueco" "$GS" \
  '    public const CACHE_TTL_SECONDS = 2100;' \
  '    public const CACHE_TTL_SECONDS = 1800;'

mutar "el refresco vuelve a cada tres horas" "$RC" \
  "Schedule::command('social-proof:refresh')->everyThirtyMinutes()" \
  "Schedule::command('social-proof:refresh')->everyThreeHours()"

mutar "el comando vuelve a llamar una vez por idioma" "$CM" \
  '        $r = $google->refresh();' \
  '        $google->refresh(); $google->refresh();
        $r = $google->refresh();'

echo
echo '── Google: las reseñas que esperan el permiso (#592) ──'

mutar "la sección vuelve a desaparecer entera por un permiso" "$HB" \
  '    @if ($socialProof->isNotEmpty() || $resenasPorPermiso)' \
  '    @if ($socialProof->isNotEmpty())'

mutar "la cascada no avisa de que las reseñas esperan el permiso" "$FB" \
  '        return ! ($this->terceroPermitido)() && $this->google->testimonials()->isNotEmpty();' \
  '        return false;'

mutar "avisa también con el permiso dado" "$FB" \
  '        return ! ($this->terceroPermitido)() && $this->google->testimonials()->isNotEmpty();' \
  '        return $this->google->testimonials()->isNotEmpty();'

mutar "el aviso tapa las opiniones propias" "$HB" \
  '    @php($resenasPorPermiso = $socialProof->isEmpty() && $socialLocked)' \
  '    @php($resenasPorPermiso = $socialLocked)'

mutar "al conceder el permiso la página no se recarga" "$HB" \
  '                     x-on:cookies-updated.window="$event.detail && $event.detail.maps && window.location.reload()">' \
  '                     >'

mutar "la entradilla habla de opiniones propias sobre reseñas de Google" "$HB" \
  ' || $resenasPorPermiso)' \
  ')'

echo
echo '── Google: la cascada ──'

mutar "las reseñas de Google salen sin consentimiento" "$FB" \
  '        if (($this->terceroPermitido)()) {' \
  '        if (true) {'

mutar "la cifra pasa a pedir consentimiento" "$FB" \
  '        return $this->google->rating() ?? $this->cms->rating();' \
  '        return ($this->terceroPermitido)() ? ($this->google->rating() ?? $this->cms->rating()) : null;'

echo
echo "── ${muerden}/${total} mutaciones muerden ──"
[ "$muerden" -eq "$total" ] || exit 1
