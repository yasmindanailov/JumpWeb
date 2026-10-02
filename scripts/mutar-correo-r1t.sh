#!/usr/bin/env bash
# Arnés de mutación de la R1·T — LOS TEXTOS DE LOS CORREOS EDITABLES DESDE EL PANEL (`[DECIDIDO owner]` `#802`;
# `specs/correos-rediseno.md` §4.2.1): las reglas de un texto del parque (y la negrita solo donde el molde la pinta), el
# cargador que lo superpone al traductor (y sus tres filtros: el tercero, las reglas de hoy), el almacén (su caché por
# versión, su rastro, «igual que el de fábrica = de fábrica», el borrador de la vista previa), la página (todo o nada, solo lo
# que cambió, el permiso en cada acción, «Volver al de fábrica» solo donde hace algo, la bandeja encima), lo que el catálogo
# nunca deja editar (y los tramos que son un texto) y la vista previa que no deja nada en la base, pinta el correo que SALE y
# en el idioma de la pestaña (medido el 30-09 antes de `main`, `§4.2.2`).
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la mutación SE APLICÓ y que
# su ancla es ÚNICA · restaurar por COPIA DE SEGURIDAD y `touch`, no con `git checkout` (`#181`) · copia por RUTA. Cada
# mutación se juzga con las pruebas de SU capa (un filtro más estrecho solo puede esconder un «muerde», nunca inventarlo).
#
#   bash scripts/mutar-correo-r1t.sh                      (todas, ~15 min)
#   SOLO='borrador' bash scripts/mutar-correo-r1t.sh      (las que llevan eso en su nombre)
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

ART="docker compose exec -u sail -T laravel.test php artisan"
TODO='MailTextsTest|MailTextCatalogTest|MailPreviewsTest|EmailTextsPageTest'
R='MailTextsTest'
P='EmailTextsPageTest'
C='MailTextCatalogTest'
V='MailPreviewsTest|EmailTextsPageTest'

TMP="$(mktemp -d)"
FICHEROS=(
    app/Domain/Content/Services/MailTextRules.php
    app/Domain/Content/Services/MailTextLoader.php
    app/Domain/Content/Services/MailTexts.php
    app/Domain/Content/ContentServiceProvider.php
    app/Filament/Pages/EmailTexts.php
    app/Notifications/Support/MailTextCatalog.php
    app/Notifications/Support/MailPreviews.php
    app/Notifications/Support/MailSituations.php
)
copia() { echo "$TMP/${1//\//__}"; }
restaurar() { for f in "${FICHEROS[@]}"; do cp "$(copia "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"; $ART view:clear >/dev/null 2>&1' EXIT
for f in "${FICHEROS[@]}"; do cp -p "$f" "$(copia "$f")"; done

verde() { $ART test --filter="$1" >/dev/null 2>&1; }

if ! verde "$TODO"; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde'

muerden=0; total=0

# mutar <nombre> <fichero> <buscar> <poner> <filtro>
mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4" filtro="$5"
    [[ -n "${SOLO:-}" && "$nombre" != *"$SOLO"* ]] && return
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
        echo "  ⚠ «$nombre» NO SE APLICÓ: el veredicto no vale"
        return
    fi
    touch "$fichero"
    if verde "$filtro"; then
        echo "  ✗ NO muerde: $nombre"
    else
        echo "  ✓ muerde:    $nombre"
        muerden=$((muerden + 1))
    fi
    cp "$(copia "$fichero")" "$fichero"; touch "$fichero"
}

REGLAS=app/Domain/Content/Services/MailTextRules.php
LOADER=app/Domain/Content/Services/MailTextLoader.php
ALMACEN=app/Domain/Content/Services/MailTexts.php
PROVIDER=app/Domain/Content/ContentServiceProvider.php
PAGINA=app/Filament/Pages/EmailTexts.php
CATALOGO=app/Notifications/Support/MailTextCatalog.php
VISTA=app/Notifications/Support/MailPreviews.php

# ── Las reglas de un texto del parque ─────────────────────────────────────────────────────────────
mutar "un texto vacío se guarda" "$REGLAS" \
  "        if (trim(\$texto) === '') {" "        if (false) {" "$R"
mutar "justo el tope ya es largo" "$REGLAS" \
  "if (mb_strlen(\$texto) > \$tope) {" "if (mb_strlen(\$texto) >= \$tope) {" "$R"
mutar "una etiqueta HTML que abre pasa" "$REGLAS" \
  "preg_match('/<\\s*[a-zA-Z\\/!]/', \$texto)" "preg_match('/<\\s*[\\/!]/', \$texto)" "$R"
mutar "una llave que abre suelta pasa" "$REGLAS" \
  "if (str_contains(\$sinVariables, '{') || str_contains(\$sinVariables, '}')) {" "if (str_contains(\$sinVariables, '}')) {" "$R"
mutar "una variable que el correo no conoce pasa" "$REGLAS" \
  "        if (\$desconocidas !== []) {" "        if (false) {" "$R"
mutar "un dato del cliente puede desaparecer" "$REGLAS" \
  "        if (\$faltan !== []) {" "        if (false) {" "$R"
mutar "una negrita sin cerrar pasa" "$REGLAS" \
  "        if (substr_count(\$texto, '**') % 2 !== 0) {" "        if (false) {" "$R"
mutar "«:day_label» se queda en «{day}_label»" "$REGLAS" \
  $'        usort($vars, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));\n        $patron = \'/:(\'.implode(\'|\', array_map(static fn (string $v): string => preg_quote($v, \'/\'), $vars)).\')(?![a-zA-Z0-9_])/\';' \
  $'        $patron = \'/:(\'.implode(\'|\', array_map(static fn (string $v): string => preg_quote($v, \'/\'), $vars)).\')/\';' "$R"
mutar "el traductor recibe «code» sin sus dos puntos" "$REGLAS" \
  "\$texto = str_replace('{'.\$v.'}', ':'.\$v, \$texto);" "\$texto = str_replace('{'.\$v.'}', \$v, \$texto);" "$R"
mutar "el adelanto con el tope de un párrafo" "$REGLAS" \
  "\$ultimo === 'preheader' => self::TOPE_ADELANTO," "\$ultimo === 'preheader' => self::TOPE," "$R"
mutar "«subject_no_date» sin el tope de un asunto" "$REGLAS" \
  "str_starts_with(\$ultimo, 'subject'), \$ultimo === 'badge'" "\$ultimo === 'subject', \$ultimo === 'badge'" "$R"
mutar "una hora («17:00») cuenta como variable" "$REGLAS" \
  "preg_match_all('/:([a-zA-Z_][a-zA-Z0-9_]*)/', \$fabrica, \$m);" "preg_match_all('/:([a-zA-Z0-9_]+)/', \$fabrica, \$m);" "$R"
# La negrita, solo donde el molde la pinta (30-09: 141 de 278 textos la enseñaban con sus asteriscos).
mutar "una negrita donde no se pinta se guarda" "$REGLAS" \
  "        if (str_contains(\$texto, '**') && ! self::admiteNegrita(\$clave)) {" "        if (false) {" "$R"
mutar "la negrita se admite en todos los bloques" "$REGLAS" \
  "        return ! (str_starts_with(\$ultimo, 'subject') ||" "        return true || ! (str_starts_with(\$ultimo, 'subject') ||" "$R"
mutar "«headline_no_date» admite negrita" "$REGLAS" \
  "|| str_starts_with(\$ultimo, 'headline') ||" "|| \$ultimo === 'headline' ||" "$R"
# Estas dos, SOLO con la vista previa: la guarda que pinta cada correo con su marca tiene que verlas sin la lista de la regla.
mutar "los títulos de aviso admiten negrita (lo ve el molde)" "$REGLAS" \
  "|| str_ends_with(\$ultimo, '_title') ||" "||" "MailPreviewsTest"
mutar "los botones admiten negrita (lo ve el molde)" "$REGLAS" \
  "|| str_starts_with(\$ultimo, 'action') ||" "||" "MailPreviewsTest"

# ── El cargador: sus tres filtros y su conversión ─────────────────────────────────────────────────
mutar "una fila fuera del catálogo se pinta" "$LOADER" \
  "if (! str_starts_with(\$clave, \$group.'.') || ! (\$this->editable)(\$clave)) {" "if (! str_starts_with(\$clave, \$group.'.')) {" "$R"
mutar "una fila sin texto de fábrica en su idioma revienta" "$LOADER" \
  $'            if (! is_string($fabrica)) {\n                continue;\n            }\n' "" "$R"
mutar "una fila con las variables de ayer se pinta" "$LOADER" \
  "if (MailTextRules::problema(\$texto, \$fabrica, \$clave) !== null) {" "if (false) {" "$R"
mutar "el cargador solo mira las variables (se pinta una negrita de antes de la regla)" "$LOADER" \
  "if (MailTextRules::problema(\$texto, \$fabrica, \$clave) !== null) {" \
  "if (MailTextRules::variablesDelParque(\$texto) !== MailTextRules::variablesDeFabrica(\$fabrica)) {" "$R"
mutar "la fila llega al traductor con sus llaves" "$LOADER" \
  "Arr::set(\$lineas, \$dentro, MailTextRules::aTraductor(\$texto, MailTextRules::variablesDeFabrica(\$fabrica)));" "Arr::set(\$lineas, \$dentro, \$texto);" "$R"
mutar "el proveedor da por editable cualquier clave" "$PROVIDER" \
  "static fn (string \$clave): bool => MailTextCatalog::esEditable(\$clave)," "static fn (string \$clave): bool => true," "$R"

# ── El almacén ────────────────────────────────────────────────────────────────────────────────────
mutar "se guarda un texto que no pasa sus reglas" "$ALMACEN" \
  $'        if ($problema !== null) {\n            return $problema;' $'        if (false) {\n            return $problema;' "$R"
mutar "igual que el de fábrica deja fila (congela el correo)" "$ALMACEN" \
  "if (\$texto === trim(MailTextRules::aParque(\$fabrica))) {" "if (false) {" "$R"
mutar "la fila no dice quién la escribió" "$ALMACEN" \
  "'updated_by' => \$por?->getKey()" "'updated_by' => null" "$R"
mutar "el rastro no dice el texto nuevo" "$ALMACEN" \
  "'from' => \$antes, 'to' => \$texto," "'from' => \$antes, 'to' => \$antes," "$R"
mutar "volver al de fábrica no deja rastro" "$ALMACEN" \
  "            AuditLogger::log('emails.text_restored', \$fila, ['key' => \$clave, 'locale' => \$locale, 'from' => \$fila->text]);" "" "$R"
mutar "guardar no sube la versión de la caché" "$ALMACEN" \
  "Cache::forever(self::VERSION, (int) Cache::get(self::VERSION, 0) + 1);" "Cache::forever(self::VERSION, (int) Cache::get(self::VERSION, 0));" "$R"
mutar "tras guardar, el traductor no relee" "$ALMACEN" \
  $'lee la base en cada carga: sigue siendo correcto.\n        }\n        self::olvidarCargados();' \
  $'lee la base en cada carga: sigue siendo correcto.\n        }' "$R"
mutar "la caché caída devuelve el de fábrica en silencio" "$ALMACEN" \
  "                \$guardados = \$deLaBase();" "                \$guardados = [];" "$R"
mutar "el borrador no llega al cargador" "$ALMACEN" \
  "return array_merge(\$guardados, self::\$borrador[\$locale] ?? []);" "return \$guardados;" "$R"
mutar "el borrador se queda tras la vista previa" "$ALMACEN" \
  "            unset(self::\$borrador[\$locale]);" "" "$R"
mutar "el borrador no obliga al traductor a releer" "$ALMACEN" \
  $'        self::$borrador[$locale] = $borrador;\n        self::olvidarCargados();' $'        self::$borrador[$locale] = $borrador;' "$R"

# ── La página ─────────────────────────────────────────────────────────────────────────────────────
mutar "con un bloque roto se guardan los buenos (no es todo o nada)" "$PAGINA" \
  "        if (\$problemas > 0) {" "        if (false) {" "$P"
mutar "se reescribe también lo que no cambió" "$PAGINA" \
  "                if (\$texto === trim((string) Arr::get(\$guardados, \$locale.'.'.\$clave, ''))) {" "                if (false) {" "$P"
# (El permiso en cada acción NO se muta aquí: lo re-exige Filament en cada petición —`hydrateCanAuthorizeAccess`— y la copia
# que tuvo la página sobrevivía por equivalente, medido el 30-09; se retiró. La propiedad la fija
# `test_the_permission_is_asked_again_on_every_action`.)
mutar "la vista previa pinta un bloque roto" "$PAGINA" \
  "if (\$texto !== '' && MailTextRules::problema(\$texto, (string) MailTextCatalog::fabrica(\$clave, \$this->vistaIdioma), \$clave) === null) {" "if (\$texto !== '') {" "$P"
mutar "la vista previa no deja rastro" "$PAGINA" \
  "        AuditLogger::log('emails.text_previewed', null, ['mail' => \$this->correo, 'locale' => \$this->vistaIdioma]);" "" "$P"
mutar "la vista previa no es inerte" "$PAGINA" \
  "EmailSendTable::inert(\$resultado['html'], '')" "\$resultado['html']" "$P"
mutar "«Volver al de fábrica» en todos los bloques" "$PAGINA" \
  "->visible(static fn (Textarea \$component): bool => trim((string) \$component->getState()) !== trim(MailTextRules::aParque(\$fabrica)))" "->visible(true)" "$P"
mutar "«Volver al de fábrica» pone los dos puntos del traductor" "$PAGINA" \
  "\$component->state(MailTextRules::aParque(\$fabrica))" "\$component->state(\$fabrica)" "$P"
mutar "tras guardar, la pantalla enseña lo de antes" "$PAGINA" \
  $'        $this->filas = null;\n        $this->form->fill($this->cargar());' $'        $this->form->fill($this->cargar());' "$P"
mutar "«No se ha guardado nada» sigue encima de «Guardado»" "$PAGINA" \
  "            \$this->dispatch('close-notification', id: \$this->avisoFallido);" "" "$P"
mutar "el aviso del error no queda apuntado" "$PAGINA" \
  "            \$this->avisoFallido = \$aviso->getId();" "" "$P"
mutar "un correo que no existe abre un editor vacío" "$PAGINA" \
  "if (\$this->correo !== null && ! MailTextCatalog::existe(\$this->correo)) {" "if (false) {" "$P"
mutar "«desfasado» solo mira las variables (y no es lo que no sale)" "$PAGINA" \
  "return \$fabrica !== null && MailTextRules::problema(\$texto, \$fabrica, \$clave) === null;" \
  "return \$fabrica !== null && MailTextRules::variablesDelParque(\$texto) === MailTextRules::variablesDeFabrica(\$fabrica);" "$P"
mutar "la vista previa no dice el asunto de la bandeja" "$PAGINA" \
  "        \$this->vistaAsunto = \$resultado['asunto'] ?? null;" "        \$this->vistaAsunto = null;" "$P"

# ── Lo que el catálogo nunca deja editar ──────────────────────────────────────────────────────────
mutar "lo legal y el saludo se editan" "$CATALOGO" \
  "        if (in_array(end(\$tramos), self::NUNCA, true)) {" "        if (false) {" "$C"
mutar "los datos y los fragmentos se editan" "$CATALOGO" \
  "        if (array_intersect(array_slice(\$tramos, 0, -1), self::NUNCA_TRAMOS) !== []) {" "        if (false) {" "$C"
mutar "un plural se edita con un editor de una frase" "$CATALOGO" \
  "        return ! str_contains(\$texto, '|');" "        return true;" "$C"
mutar "un correo al cliente se cae del catálogo" "$CATALOGO" \
  "        'login_code' => [N\\LoginCode::class, 'cuenta', ['emails.login_code']]," "" "$C"
mutar "un fragmento suelto se edita (el nombre de reserva va al asunto)" "$CATALOGO" \
  "'reason_prefix', 'product_fallback', 'amount_discount', 'amount_surcharge'];" "'reason_prefix'];" "$C"
mutar "un tramo que es un texto no da su bloque" "$CATALOGO" \
  "\$hojas = \$unTexto !== null ? ['' => \$unTexto] : \$cargador->hojasDeFabrica(self::IDIOMA_BASE, \$tramo);" \
  "\$hojas = \$cargador->hojasDeFabrica(self::IDIOMA_BASE, \$tramo);" "$C"
mutar "el correo nuevo vuelve a ofrecer la versión sin código" "$CATALOGO" \
  "['emails.verify_pending_email_code', 'emails.verify_pending_email.action', 'emails.verify_pending_email.ignore']" \
  "['emails.verify_pending_email', 'emails.verify_pending_email_code']" "$C"
mutar "el correo nuevo vuelve a ofrecer la versión sin código (lo ve el molde)" "$CATALOGO" \
  "['emails.verify_pending_email_code', 'emails.verify_pending_email.action', 'emails.verify_pending_email.ignore']" \
  "['emails.verify_pending_email', 'emails.verify_pending_email_code']" "MailPreviewsTest"

# ── La vista previa ───────────────────────────────────────────────────────────────────────────────
mutar "la vista previa deja en la base lo que escribe un toMail()" "$VISTA" \
  "            DB::rollBack();" "            DB::commit();" "$V"
mutar "la vista previa no pinta el borrador" "$VISTA" \
  "MailTexts::conBorrador(\$locale, \$borrador, static function" "MailTexts::conBorrador(\$locale, [], static function" "$V"
mutar "la vista previa no cambia de idioma" "$VISTA" \
  $'                app()->setLocale($locale);\n' "" "$V"
mutar "la página se queda en el idioma de la vista previa" "$VISTA" \
  "            app()->setLocale(\$antes);" "" "$V"
mutar "el oscuro sale claro" "$VISTA" \
  "        if (\$oscuro) {" "        if (false) {" "$V"
mutar "la vista previa del correo nuevo, sin su código (la versión que ya no sale)" "$VISTA" \
  "new N\\VerifyPendingEmail('482913')" "new N\\VerifyPendingEmail" "$V"
mutar "la firma de otro idioma sirve de caso (lo escrito en la pestaña no se ve)" "$VISTA" \
  $'                    ->whereHas(\'version\', static fn ($q) => $q->where(\'locale\', $locale))\n' "" "$V"
mutar "la vista previa no trae el asunto" "$VISTA" \
  "(string) \$mensaje->subject," "''," "$V"

# ── La R1·T2 (`#809`): las situaciones y el aviso «Solo sale si…» (`specs/correos-rediseno.md` §4.2.3) ─────────────────
SIT=app/Notifications/Support/MailSituations.php
mutar "situación · la de entradas toma cualquier pedido" "$VISTA" \
  "'entradas' => \$pedidoDe(false)," "'entradas' => \$pedido()," "$V"
mutar "situación · la de cumpleaños toma cualquier pedido" "$VISTA" \
  "'cumpleanos' => \$pedidoDe(true)," "'cumpleanos' => \$pedido()," "$V"
mutar "situación · sin un día único, con sus franjas" "$VISTA" \
  "\$o->items->each(static fn (OrderItem \$i) => \$i->setRelation('slot', null));" "" "$V"
mutar "situación · quien cumple sin nombre conserva el homenajeado" "$VISTA" \
  "\$f->setAttribute('event_data', [\$homenajeado => ''] + (is_array(\$f->event_data) ? \$f->event_data : []));" "" "$V"
mutar "situación · los extras de cualquier fiesta" "$VISTA" \
  "\$f = \$s === 'con_extras'" "\$f = false" "$V"
mutar "situación · la invitación no cambia" "$VISTA" \
  "\$f->ticketType?->setAttribute('guest_invitation', \$s === 'con_invitacion');" "" "$V"
mutar "situación · la firma de una reserva es cualquiera" "$VISTA" \
  "\$firma = \$s === 'firma_de_reserva'" "\$firma = false" "$V"
mutar "situación · el extra que se quita no baja" "$VISTA" \
  "'to' => 0], -1200]," "'to' => 0], 1200]," "$V"
mutar "situación · el suplemento que se quita se queda" "$VISTA" \
  "'suplemento_se_quita' => [400, 0]," "'suplemento_se_quita' => [400, 600]," "$V"
mutar "situación · el parque no lo cambia nunca" "$VISTA" \
  "\$s !== 'por_parque'" "true" "$V"
mutar "situación · sin precio desde" "$VISTA" \
  "\$s === 'con_desde' ? (string) __('admin.mail_texts.ejemplo_desde') : null" "null" "$V"
mutar "situación · la cantidad no cambia" "$VISTA" \
  "'cambio_cantidad' => ['quantity_change' =>" "'cambio_cantidad' => ['event_data_change' => true, 'x' =>" "$V"
mutar "situación · no caen complementos" "$VISTA" \
  "\$s === 'caen_complementos' ? 2 : 0" "0" "$V"
mutar "situación · el pedido devuelto a mano no lo dice" "$VISTA" \
  "new N\\OrderRefunded(\$o, null, \$s === 'tambien_cancelado', \$s === 'devolucion_a_mano')" "new N\\OrderRefunded(\$o, null, \$s === 'tambien_cancelado', false)" "$V"
mutar "situación · Google nunca verificó" "$VISTA" \
  "new N\\SocialIdentityLinked('google', \$s === 'google_verifico')" "new N\\SocialIdentityLinked('google', false)" "$V"
mutar "situación · la encuesta conserva su entrada" "$VISTA" \
  "\$encuesta->setAttribute('intro', []);" "" "$V"
mutar "situación · sin elegir, ninguna" "$SIT" \
  "? \$situacion : (\$suyas[0] ?? null);" "? \$situacion : null;" "$V"
mutar "situación · una de otro correo se pinta" "$SIT" \
  "return in_array(\$situacion, \$suyas, true) ?" "return \$situacion !== null ?" "$V"
mutar "situación · el correo pierde una" "$SIT" \
  "'order_item_modified' => ['cambio_dia', 'cambio_cantidad'," "'order_item_modified' => ['cambio_dia'," "$V"
mutar "aviso · un texto que sale a veces no lo dice" "$SIT" \
  "        'emails.order_refunded.when_manual' => 'devolucion_a_mano',
" "" "$V"
mutar "aviso · la página no lo enseña" "$PAGINA" \
  "\$partes = \$condicion === null ? [] : [__('admin.mail_texts.solo_si.'.\$condicion)];" "\$partes = [];" "$P"
mutar "página · la situación del navegador sin validar" "$PAGINA" \
  "        \$this->vistaSituacion = MailSituations::elegida(\$this->correo, \$this->vistaSituacion);
" "" "$P"
mutar "página · la vista previa no pasa la situación" "$PAGINA" \
  "\$this->vistaOscuro, \$this->vistaSituacion);" "\$this->vistaOscuro);" "$P"
mutar "página · el rastro sin la situación" "$PAGINA" \
  ", 'situation' => \$this->vistaSituacion]" "]" "$P"

echo
echo "$muerden/$total muerden"
[[ $muerden -eq $total ]]
