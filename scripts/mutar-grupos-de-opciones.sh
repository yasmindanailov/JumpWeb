#!/usr/bin/env bash
# Arnés de mutación de LOS GRUPOS DE OPCIONES (P3 y P4 de `specs/fiesta-sistema-nuevo.md` §4.21, `[DECIDIDO owner]` `#914`).
# Lo que protege: «elige una» bajo el lock (una quita las demás, dos a la vez no, «hay que elegir» no se vacía, lo comprado al
# reservar es del parque), «No se les pide» (un grupo creado después de vender no se le pide a esa fiesta), las reglas 2–4 y 8
# abiertas SOLO para un grupo de la tabla, lo que falta por elegir en el parque, la lista y la API, y el correo de la víspera.
#
# ⚠️ Cada mutación corre SOLO los tests que la tienen que ver (su 5.º argumento), como `mutar-plazo-unico.sh`: la
# verificación de una tanda dura minutos. La base verde sí se mide con la unión.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la mutación SE APLICÓ y que su
# ancla es ÚNICA · un CONTROL que no debe morder · restaurar por COPIA DE SEGURIDAD y no con `git checkout` (`#181`).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"
LISTA='test_a_choice_group_is_one_question|test_an_optional_group_offers_no_thanks'
API='test_the_form_publishes_its_choice_groups'
TODOS="PostFormChoiceGroupsTest|ChoiceGroupsParkTest|ChoiceGroupsPanelTest|ChoiceReminderNoticeTest|$LISTA|$API"
correr() { docker compose exec -u sail -T laravel.test php artisan test --filter="$1" >/dev/null 2>&1; }
TMP="$(mktemp -d)"
FICHEROS=(
    app/Domain/Booking/Services/PostFormAddons.php
    app/Domain/Booking/Models/ProductAddon.php
    app/Domain/Booking/Models/AddonChoiceGroup.php
    app/Domain/Booking/Services/DailyReservationsSummary.php
    resources/views/filament/orders/items-list.blade.php
    app/Http/Controllers/GuestFormController.php
    app/Http/Fiesta/ListaDeInvitados.php
    app/Http/Resources/Api/V1/GuestFormResource.php
    app/Console/Commands/SendChoiceReminders.php
    app/Notifications/ChoiceReminderNotice.php
)
restaurar() { for f in "${FICHEROS[@]}"; do cp "$TMP/$(basename "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"; docker compose exec -u sail -T laravel.test php artisan view:clear >/dev/null 2>&1' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$TMP/$(basename "$f")"; done
if ! correr "$TODOS"; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde'

muerden=0; total=0; control_ok=1

aplicar() {
    python3 -c 'import sys; p=sys.argv[1]; s=open(p,encoding="utf-8").read(); n=s.count(sys.argv[2]); open(p,"w",encoding="utf-8").write(s.replace(sys.argv[2], sys.argv[3], 1)); sys.exit(0 if n == 1 else 3)' \
        "$1" "$2" "$3"
}

mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4" filtro="$5"
    total=$((total + 1))
    aplicar "$fichero" "$buscar" "$poner"; local unico=$?
    if cmp -s "$fichero" "$TMP/$(basename "$fichero")"; then
        echo "  ⚠ «$nombre» NO SE APLICÓ (el patrón no casa): el veredicto no vale"
        return
    fi
    if [[ $unico -ne 0 ]]; then
        echo "  ⚠ «$nombre»: el ancla NO es única; se mutó la primera y el veredicto es dudoso"
    fi
    touch "$fichero"
    if correr "$filtro"; then
        echo "  ✗ NO muerde: $nombre"
    else
        echo "  ✓ muerde:    $nombre"
        muerden=$((muerden + 1))
    fi
    cp "$TMP/$(basename "$fichero")" "$fichero"; touch "$fichero"
}

control() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4" filtro="$5"
    aplicar "$fichero" "$buscar" "$poner"
    if cmp -s "$fichero" "$TMP/$(basename "$fichero")"; then
        echo "  ⚠ CONTROL «$nombre» NO SE APLICÓ"; control_ok=0; return
    fi
    touch "$fichero"
    if correr "$filtro"; then
        echo "  ✓ control:   $nombre (sigue verde, como debe)"
    else
        echo "  ✗ CONTROL ROJO: $nombre — se pone rojo por algo que no es la regla"; control_ok=0
    fi
    cp "$TMP/$(basename "$fichero")" "$fichero"; touch "$fichero"
}

PF=app/Domain/Booking/Services/PostFormAddons.php
PA=app/Domain/Booking/Models/ProductAddon.php
CG=app/Domain/Booking/Models/AddonChoiceGroup.php
DS=app/Domain/Booking/Services/DailyReservationsSummary.php
IL=resources/views/filament/orders/items-list.blade.php
GC=app/Http/Controllers/GuestFormController.php
LI=app/Http/Fiesta/ListaDeInvitados.php
GR=app/Http/Resources/Api/V1/GuestFormResource.php
SC=app/Console/Commands/SendChoiceReminders.php
CN=app/Notifications/ChoiceReminderNotice.php

# ── «ELIGE UNA», bajo el lock ─────────────────────────────────────────────────────────────────────
mutar "elegir una ya no quita las demás (la API manda solo la nueva: dos meriendas)" "$PF" \
  "if (\$id !== \$chosen[0]) {" \
  "if (false) {" \
  'PostFormChoiceGroupsTest'
mutar "dos elegidas a la vez se dejan pasar" "$PF" \
  "if (count(\$chosen) > 1) {" \
  "if (count(\$chosen) > 2) {" \
  'PostFormChoiceGroupsTest'
mutar "un grupo con «hay que elegir» se puede vaciar" "$PF" \
  "if ((bool) \$groups->get(\$key)?->is_required && array_intersect(\$ids, array_keys(\$governed)) !== []) {" \
  "if (false) {" \
  'PostFormChoiceGroupsTest'
mutar "con una opción comprada al reservar, el cliente cambia el grupo" "$PF" \
  "if (array_intersect(\$ids, array_keys(\$frozen)) !== []) {" \
  "if (false) {" \
  'PostFormChoiceGroupsTest'
mutar "una línea CANCELADA cuenta como la elegida" "$PF" \
  "if (\$key !== null && ! \$child->isCancelled() && (int) \$child->quantity > 0) {" \
  "if (\$key !== null && (int) \$child->quantity > 0) {" \
  'PostFormChoiceGroupsTest|ChoiceGroupsParkTest'

# ── «NO SE LES PIDE»: un grupo creado después de vender ───────────────────────────────────────────
mutar "la fecha del grupo deja de contar (en todas partes)" "$CG" \
  "return \$soldAt === null || \$this->created_at === null || \$this->created_at->lte(\$soldAt);" \
  "return true;" \
  'PostFormChoiceGroupsTest|ChoiceGroupsParkTest'
mutar "la lista ofrece las opciones de un grupo posterior a la venta" "$PF" \
  "return \$key === null || (\$groups->get(\$key)?->appliesToSaleAt(\$soldAt) ?? false);" \
  "return true;" \
  'PostFormChoiceGroupsTest'
mutar "el parque dice «sin elegir» de un grupo posterior a la venta" "$PF" \
  " && \$g->appliesToSaleAt(\$soldAt))" \
  ")" \
  'ChoiceGroupsParkTest'

# ── Las REGLAS del enganche: abiertas solo para un grupo de la tabla ──────────────────────────────
mutar "las reglas 2–4 se abren con una clave cualquiera, sin su fila" "$PA" \
  "\$inGroup = \$group !== null && (\$inDefinedGroup ?? \$pivot->hasDefinedChoiceGroup());" \
  "\$inGroup = \$group !== null;" \
  'PostFormChoiceGroupsTest'
mutar "la regla 8 vuelve a pedir tope a la opción por niño" "$PA" \
  "if (! \$pivot->isPerGuest() && (\$pivot->max_qty === null || (int) \$pivot->max_qty < 1)) {" \
  "if (\$pivot->max_qty === null || (int) \$pivot->max_qty < 1) {" \
  'PostFormChoiceGroupsTest'
mutar "las opciones de un grupo, en fases distintas" "$PA" \
  "->contains(fn (self \$member): bool => \$member->saleStage() !== \$pivot->saleStage());" \
  "->contains(fn (self \$member): bool => false);" \
  'PostFormChoiceGroupsTest'
mutar "un grupo con opciones se borra (las deja huérfanas)" "$CG" \
  "if (\$group->memberCount() > 0) {" \
  "if (false) {" \
  'PostFormChoiceGroupsTest|ChoiceGroupsPanelTest'

# ── LO QUE FALTA POR ELEGIR: el parque, la lista y la API ─────────────────────────────────────────
mutar "un grupo opcional sale «sin elegir»" "$PF" \
  "filter(fn (AddonChoiceGroup \$g): bool => \$g->is_required)" \
  "filter(fn (AddonChoiceGroup \$g): bool => true)" \
  'ChoiceGroupsParkTest'
mutar "la columna «Merienda» del resumen no dice lo que falta" "$DS" \
  "foreach (PostFormAddons::unansweredRequiredGroups(\$item) as \$grupo) {" \
  "foreach ([] as \$grupo) {" \
  'ChoiceGroupsParkTest'
mutar "la ficha del pedido en el panel no dice «sin elegir»" "$IL" \
  "@if (\$sinElegir !== [])" \
  "@if (false)" \
  'ChoiceGroupsParkTest'
mutar "la lista ignora la respuesta del grupo (choices[])" "$GC" \
  "self::desiredQuantities(\$desired ?? []) + self::choiceQuantities(\$fresh, \$choices ?? [])," \
  "self::desiredQuantities(\$desired ?? [])," \
  "$LISTA"
mutar "la lista pinta las opciones del grupo también como tarjetas sueltas" "$LI" \
  "\$sueltasDeGrupo = array_values(array_filter(\$addons, fn (PostFormAddonView \$a): bool => \$a->group === null));" \
  "\$sueltasDeGrupo = \$addons;" \
  "$LISTA"
mutar "la API no publica los grupos" "$GR" \
  "'choice_groups' => PostFormChoiceGroupResource::collection(app(PostFormAddons::class)->choiceGroupsFor(\$item, \$addons))->resolve(\$request)," \
  "'choice_groups' => []," \
  "$API"

# ── «FALTA ELEGIR…», la víspera del cierre (P4) ───────────────────────────────────────────────────
mutar "el aviso sale antes de su ventana" "$SC" \
  "if (\$now->lt(\$opens) || \$now->gte(\$closes)) {" \
  "if (\$now->gte(\$closes)) {" \
  'ChoiceReminderNoticeTest'
mutar "el aviso sale con la lista ya cerrada" "$SC" \
  "if (\$now->lt(\$opens) || \$now->gte(\$closes)) {" \
  "if (\$now->lt(\$opens)) {" \
  'ChoiceReminderNoticeTest'
mutar "una fiesta vendida dentro de la ventana también lo recibe" "$SC" \
  "if (\$soldAt === null || \$soldAt->gte(\$opens)) {" \
  "if (\$soldAt === null) {" \
  'ChoiceReminderNoticeTest'
mutar "sale cada hora (la marca no se mira)" "$SC" \
  "->whereNull('choice_reminder_at')" \
  "->whereNotNull('id')" \
  'ChoiceReminderNoticeTest'
mutar "avisa aunque no falte nada" "$SC" \
  "if (\$pending === []) {" \
  "if (false) {" \
  'ChoiceReminderNoticeTest'
mutar "en la cola no vuelve a mirar si ya se eligió" "$CN" \
  "return \$fresh !== null && PostFormAddons::unansweredRequiredGroups(\$fresh) !== [];" \
  "return true;" \
  'ChoiceReminderNoticeTest'

# ── CONTROL: la misma regla, escrita de otra forma, NO debe morder ────────────────────────────────
control "«dos a la vez», escrito con >= 2" "$PF" \
  "if (count(\$chosen) > 1) {" \
  "if (count(\$chosen) >= 2) {" \
  'PostFormChoiceGroupsTest'

echo "$muerden/$total muerden"
[[ $muerden -eq $total && $control_ok -eq 1 ]]
