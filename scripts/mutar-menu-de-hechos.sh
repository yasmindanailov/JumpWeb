#!/usr/bin/env bash
# Arnés de mutación del MENÚ DE HECHOS (F5 · T1, `docs/specs/instancia-y-landing-fuera.md` §4.1 y §6).
#
# La API pública de lectura tiene un modo de fallo que no rompe nada: que salga un dato que no debía. El JSON
# sigue siendo válido, el cliente sigue pintando y nadie se entera — y la tabla que alimenta ese JSON tiene
# `redsys_secret_key` a dos filas de `contact.email`. Estas mutaciones quitan una a una las piezas que lo
# impiden, y una guarda tiene que morir con cada una.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la
# mutación SE APLICÓ · restaurar por COPIA DE SEGURIDAD y no con `git checkout` (`#181`).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

SAIL="docker compose exec -u sail -T laravel.test"
# ⚠️ El último no es una clase sino un MÉTODO: `CatalogEditTest` entero son ~40 casos del panel y el arnés
# corre esta orden una vez por mutación. Se trae solo la guarda de la foto, que es la de esta tanda.
TESTS="$SAIL php artisan test --filter='PublicFactsBoundaryTest|SiteFactsTest|ScheduleFactsTest|RulesFactsTest|LegalDocumentsTest|PricesFactsTest|HeroStatusTest|ApiContractTest|CatalogTest|the_ficha_photo_is_editable_from_the_panel'"

LECTOR=app/Domain/Platform/Services/PublicFacts.php
RECURSO=app/Http/Resources/Api/V1/SiteFactsResource.php
HORARIO=app/Http/Resources/Api/V1/ScheduleFactsResource.php
ENVIVO=app/Http/Resources/Api/V1/OpeningNowResource.php
ESTADO=app/Domain/Content/Services/OpeningState.php
NORMAS=app/Http/Resources/Api/V1/RulesFactsResource.php
NORMASCTRL=app/Http/Controllers/Api/V1/RulesFactsController.php
CONTRATO=tests/Feature/Api/ApiContractTest.php
LEGALES=app/Http/Resources/Api/V1/LegalDocumentsResource.php
LEGALESCTRL=app/Http/Controllers/Api/V1/LegalDocumentsController.php
PRECIOS=app/Http/Resources/Api/V1/PricesFactsResource.php
PRECIOSCTRL=app/Http/Controllers/Api/V1/PricesFactsController.php
RUTAS=routes/api.php
# T6 · la ficha de producto y de zona (`#632` P1).
PANEL=app/Filament/Resources/Catalog/Schemas/CatalogForm.php
FICHAZONA=app/Http/Resources/Api/V1/CatalogZoneDetailResource.php
FICHAPROD=app/Http/Resources/Api/V1/CatalogProductResource.php
LECTORCAT=app/Domain/Booking/Services/CatalogReader.php
MODELOZONA=app/Domain/Booking/Models/Zone.php
MODELOPROD=app/Domain/Booking/Models/TicketType.php
YAML=openapi/v1.yaml

TMP="$(mktemp -d)"
FICHEROS=("$LECTOR" "$RECURSO" "$HORARIO" "$ENVIVO" "$ESTADO" "$NORMAS" "$NORMASCTRL" "$CONTRATO" "$LEGALES" "$LEGALESCTRL" "$PRECIOS" "$PRECIOSCTRL" "$RUTAS" "$FICHAZONA" "$FICHAPROD" "$LECTORCAT" "$MODELOZONA" "$MODELOPROD" "$YAML" "$PANEL")
restaurar() { for f in "${FICHEROS[@]}"; do cp "$TMP/$(basename "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$TMP/$(basename "$f")"; done

verde() { eval "$TESTS" >/dev/null 2>&1; }

if ! verde; then
    echo '✗ las guardas del menú NO están verdes antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde'

muerden=0; total=0

mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4"
    total=$((total + 1))
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

# ── La lista blanca deja de serlo ──────────────────────────────────────────────────────────────
mutar "pedir una clave NO declarada devuelve null en vez de reventar (la lista pasa a ser decorativa)" \
  "$LECTOR" "        if (! in_array(\$clave, \$this->permitidas, true)) {" \
  "        if (false) {"

mutar "un SECRETO se puede declarar en la lista blanca (basta un copiar y pegar)" \
  "$LECTOR" "            if (self::esSecreto(\$clave)) {" "            if (false) {"

mutar "la negación de secretos deja de cubrir a Redsys por familia" \
  "$LECTOR" "        '/^redsys_/i'," "        '/^redsys_no_existe_/i',"

# ── El recurso se salta el camino ──────────────────────────────────────────────────────────────
mutar "el recurso lee la tabla a mano (\`Setting::value\`) en vez de por su lista" \
  "$RECURSO" "        \$hechos = PublicFacts::allowing(self::AJUSTES);" \
  "        \$hechos = PublicFacts::allowing(self::AJUSTES);
        \\App\\Domain\\Platform\\Models\\Setting::value('business.name');"

# ── Lo que hace ÚTIL al menú ───────────────────────────────────────────────────────────────────
mutar "un campo vacío VIAJA como cadena vacía (y cada landing tiene que volver a filtrarlo)" \
  "$LECTOR" "            if (is_string(\$valor) && trim(\$valor) !== '') {
                \$salida[\$nombre] = trim(\$valor);
            }" \
  "            \$salida[\$nombre] = is_string(\$valor) ? trim(\$valor) : '';"

mutar "un bloque vacío sale como LISTA y no como objeto (el tipo depende de si rellenaron el panel)" \
  "$RECURSO" "            'seo' => (object) \$hechos->compact([" "            'seo' => \$hechos->compact(["

mutar "el domicilio FISCAL vuelve a servirse como la dirección del parque" \
  "$RECURSO" "                'jurisdiction' => 'legal.jurisdiction',
                'fiscal_address' => 'business.address'," \
  "                'jurisdiction' => 'legal.jurisdiction',"

# ── La caché, que es media promesa del recurso ─────────────────────────────────────────────────
mutar "la respuesta deja de cachearse en público" \
  "$RUTAS" "    Route::get('/site', SiteFactsController::class)
        ->middleware('cache.headers:public;max_age=300;etag')" \
  "    Route::get('/site', SiteFactsController::class)"

# ── El horario: lo que una landing no puede comprobar por su cuenta ────────────────────────────
mutar "el día de la semana pasa a la convención ISO (la semana entera, desplazada, sin que falle nada)" \
  "$HORARIO" "                'weekday' => \$dia->weekday," "                'weekday' => (\$dia->weekday + 6) % 7,"

mutar "las horas salen con segundos (\`21:30:00\` en vez de \`21:30\`)" \
  "$HORARIO" "        return \$hora === null ? null : substr(\$hora, 0, 5);" "        return \$hora;"

mutar "un parque CERRADO anuncia su hora de cierre (un cartel que miente)" \
  "$ENVIVO" "            ...(\$ahora->openNow
                ? ['closes_at' => \$ahora->closesAt]
                : array_filter(['opens_at' => \$ahora->opensAt?->toIso8601String()]))," \
  "            'closes_at' => \$ahora->closesAt,
            'opens_at' => \$ahora->opensAt?->toIso8601String(),"

mutar "el estado en vivo se cachea como el calendario (cinco minutos diciendo «abierto» tras cerrar)" \
  "$RUTAS" "    Route::get('/schedule/now', [ScheduleFactsController::class, 'now'])
        ->middleware('cache.headers:public;max_age=60;etag')" \
  "    Route::get('/schedule/now', [ScheduleFactsController::class, 'now'])
        ->middleware('cache.headers:public;max_age=300;etag')"

# ⚠️ Ésta es la que dice si el chip y la API pueden separarse: con el hecho calculado dos veces, el día raro
# —un festivo, un cierre a media tarde— cada uno diría una cosa y ningún test lo vería.
mutar "sin horario configurado se afirma que está ABIERTO" \
  "$ESTADO" "        if (\$abre === null || \$cierra === null) {
            return false;
        }" \
  "        if (\$abre === null || \$cierra === null) {
            return true;
        }"

# ── Las normas: el orden ES el dato, y lo retirado no vuelve ───────────────────────────────────
mutar "el orden pasa a ser el de la tabla (la visita, contada de atrás adelante)" \
  "$NORMAS" "            ->sortBy(fn (VenueRule \$r): string => sprintf(
                '%d-%05d', \$this->ordenDelMomento(\$r->momentOrNull()), (int) \$r->position,
            ))" \
  "            ->sortBy(fn (VenueRule \$r): int => (int) \$r->id)"

mutar "una norma DESACTIVADA vuelve a la web por la API" \
  "$NORMASCTRL" "VenueRule::query()->where('is_active', true)->orderBy('position')->get()" \
  "VenueRule::query()->orderBy('position')->get()"

mutar "el idioma deja de ser obligatorio (y la caché sirve uno por otro)" \
  "$NORMASCTRL" "        \$datos = \$request->validate(['lang' => ['required', 'string', Rule::in(SetLocale::SUPPORTED)]]);

        app()->setLocale(\$datos['lang']);" ""

# ⚠️ Y la guarda del CONTRATO, que tenía su propio punto ciego: sin bajar a los `items`, tres esquemas de
# lista se colaban sin declarar un solo `required`.
mutar "el contrato deja de mirar dentro de las listas" "$CONTRATO" \
  "        if ((\$schema['type'] ?? null) === 'array' && is_array(\$schema['items'] ?? null)) {
            \$this->assertObjectSchemaIsStrict(\"{\$name}.items\", \$schema['items']);

            return;
        }" ""

# ── Los legales: el texto que se publica es el correcto, y completo ────────────────────────────
# ⚠️ La primera es EL caso: sin interpolar, una landing publica «El responsable es :legal_name» en su
# política de privacidad, que es el peor sitio donde puede quedar un marcador sin resolver.
mutar "el cuerpo sale SIN interpolar (\`:legal_name\` publicado tal cual)" \
  "$LEGALES" "            'h' => LegalIdentity::interpolate(\$seccion['h'] ?? null) ?: null,
            'p' => LegalIdentity::interpolate(\$seccion['p'] ?? null) ?: null," \
  "            'h' => (\$seccion['h'] ?? null) ?: null,
            'p' => (\$seccion['p'] ?? null) ?: null,"

mutar "se publica el texto FIRMADO en vez de la página (y se pierden las secciones que explican)" \
  "$LEGALES" "            'sections' => \$this->conCuerpo ? \$this->secciones(\$pagina) : null," \
  "            'sections' => \$this->conCuerpo ? (\$version?->body ?? \$this->secciones(\$pagina)) : null,"

mutar "una página DESACTIVADA se sirve por la API" \
  "$LEGALESCTRL" "return Page::query()->where('is_active', true)->orderBy('slug')->get()->collect();" \
  "return Page::query()->orderBy('slug')->get()->collect();"

mutar "el índice se lleva el cuerpo de los cinco documentos" \
  "$LEGALESCTRL" "return new LegalDocumentsResource(\$this->activas(), conCuerpo: false);" \
  "return new LegalDocumentsResource(\$this->activas(), conCuerpo: true);"

# ── Los precios: un cero es un precio, y lo apagado no se anuncia ──────────────────────────────
mutar "una tarifa sin precio viaja como 0 (una landing pintaría «gratis los festivos»)" \
  "$PRECIOS" "            if (\$cents !== null) {
                \$salida[] = ['rate' => (string) \$tarifa->key, 'cents' => (int) \$cents];
            }" \
  "            \$salida[] = ['rate' => (string) \$tarifa->key, 'cents' => (int) \$cents];"

mutar "se anuncian precios de productos APAGADOS en el panel" \
  "$PRECIOSCTRL" "            ->where('is_active', true)
            ->whereHas('zone', fn (\$q) => \$q->where('is_active', true))" ""

mutar "la moneda se escribe a mano en vez de salir de la fila de precio" \
  "$PRECIOS" "            'currency' => \$this->moneda()," "            'currency' => 'USD',"

# ── La FICHA de producto y de zona (T6): lo no rellenado no viaja, y la URL es absoluta ────────
# El modo de fallo de esta tanda no rompe nada: publicar `""` donde no hay dato, o una ruta que
# solo resuelve quien esté en el mismo dominio. Las dos dejan el JSON válido y la app en blanco.

mutar "la ficha de zona publica lo no rellenado (la clave viaja aunque esté vacía)" \
  "$FICHAZONA" "            fn (mixed \$valor): bool => \$valor !== null," \
  "            fn (mixed \$valor): bool => true,"

mutar "una descripción de zona en blanco viaja como cadena vacía en vez de callarse" \
  "$LECTORCAT" "            description: \$zone->tr('description') ?: null," \
  "            description: \$zone->tr('description') ?? null,"

mutar "una descripción de PRODUCTO en blanco viaja como cadena vacía en vez de callarse" \
  "$LECTORCAT" "            description: \$product->tr('description') ?: null," \
  "            description: \$product->tr('description') ?? null,"

mutar "la foto de la ZONA viaja como ruta cruda, no como URL absoluta" \
  "$MODELOZONA" "        return \$ruta === '' ? null : asset(\$ruta);" \
  "        return \$ruta === '' ? null : \$ruta;"

mutar "la foto del PRODUCTO viaja como ruta del disco, no como URL absoluta" \
  "$MODELOPROD" "        return \$this->image ? asset('uploads/'.ltrim((string) \$this->image, '/')) : null;" \
  "        return \$this->image ? ltrim((string) \$this->image, '/') : null;"

mutar "el producto sin foto publica «image_url: null» en vez de omitir la clave" \
  "$FICHAPROD" "            \$this->resource->imageUrl === null ? [] : ['image_url' => \$this->resource->imageUrl]" \
  "            ['image_url' => \$this->resource->imageUrl]"

# Y las dos guardas del CONTRATO que esta tanda estrena.
mutar "la foto deja de estar declarada como opcional POR DISEÑO (y nadie exige su porqué)" \
  "$CONTRATO" "        'CatalogProduct' => ['image_url']," "        'CatalogProduct' => [],"

mutar "la ficha de zona DIVERGE de la identidad que viaja anidada en cada producto" \
  "$YAML" "        name:
          type: string
        description:
          type: string
          description: |
            Qué es esta zona" \
  "        name:
          type: string
          description: Nombre de la zona.
        description:
          type: string
          description: |
            Qué es esta zona"

# Y el otro extremo del campo: el PANEL. Una API que publica un dato que nadie puede rellenar no es un dato.
mutar "el campo de la foto desaparece del formulario del panel (la API publica algo irrellenable)" \
  "$PANEL" "                FileUpload::make('image')" "                FileUpload::make('imagen')"

mutar "la foto se sube fuera del hueco de la instalación (el despliegue se la lleva)" \
  "$PANEL" "                    ->disk(TicketType::IMAGE_DISK)" "                    ->disk('public')"

echo
echo "mutaciones: $muerden/$total muerden"
[ "$muerden" -eq "$total" ]
