#!/usr/bin/env python3
"""Arnés de mutación del BORDE `§7.1·5`: qué ficha se pierde al bajar invitados
(`docs/specs/celebracion-e-invitacion.md` §7.1·5 y §10.18; `DECISIONES #718`).

El borde que la T6 dejó abierto: el recorte se lleva las filas del FINAL y el suelo cuenta
**plazas, no posiciones**, así que una bajada PERMITIDA borraba justo a los niños que el suelo
prometía proteger y dejaba en pie fichas vacías. Se compacta antes de recortar.

⚠️⚠️ **La costura tiene DOS puntas y hasta que no se anduvo por HTTP parecía tener una**: el
post-form ajusta la cantidad **y después** guarda las fichas del navegador, en el orden viejo. Por
eso hay mutaciones a los dos lados y una de ellas —la del guardado— es la que destapó el camino real.

Cada mutación rompe UNA propiedad y comprueba que su guarda se pone en rojo:

  · los confirmados van los PRIMEROS, y quién lo es sale de `PartyGuests` —la misma fuente que el
    suelo—, no de «tiene nombre»;
  · las vacías caen las últimas, y el orden del anfitrión se conserva dentro de cada grupo;
  · el ajuste compacta al BAJAR y **solo** al bajar;
  · el aviso de «cuántas fichas pierdes» se cuenta sobre el orden ya compactado;
  · **el guardado también compacta** (sin ello el defecto vuelve entero por HTTP);
  · y compacta DESPUÉS de ordenar las claves, no antes, o deshace el arreglo de `#571`.

⚠️ Exige VERDE antes de mutar y restaura cada fichero desde su copia en memoria.

Uso: python3 scripts/mutar-borde-fichas-al-bajar.py
"""
import pathlib
import subprocess
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
EXEC = ['docker', 'compose', 'exec', '-T', '-u', 'sail', 'laravel.test']

ORDEN = 'app/Domain/Booking/Services/GuestCardOrder.php'
ADJ = 'app/Domain/Booking/Services/GuestCountAdjuster.php'
ITEM = 'app/Domain/Booking/Models/OrderItem.php'

BORDE = 'InvitationPlacesTest::test_lowering_the_count_drops_empty_cards_before_the_children_who_confirmed'
CABEN = 'GuestCountTest::test_a_reduction_that_fits_loses_nothing_and_keeps_the_hosts_order'
SUBIR = 'GuestCountTest::test_raising_the_count_does_not_move_the_cards_around'
GANA = 'InvitationPlacesTest::test_a_child_who_confirmed_outranks_one_the_host_merely_wrote_down'
HTTP = 'InvitationPlacesTest::test_the_whole_path_over_http_keeps_the_children_who_confirmed'
DESORDEN = 'GuestFormManyGuestsTest::test_saving_with_the_cards_out_of_order_keeps_every_guest_in_place'
PROPUESTA = 'InvitationApiTest::test_a_reply_is_proposed_on_the_guest_whose_name_it_matches'
ENCOGE = 'GuestFormManyGuestsTest::test_saving_out_of_order_while_the_list_shrinks_still_orders_by_key_first'


def php(test: str) -> list[str]:
    return EXEC + ['php', 'artisan', 'test', '--filter', test]


MUTATIONS = [
    # ── La regla, en `GuestCardOrder` ───────────────────────────────────────────────────────────
    (
        'los CONFIRMADOS van los primeros, no con el resto de escritas',
        ORDEN,
        '            return self::CONFIRMADA;',
        '            return self::ESCRITA;',
        php(GANA),
    ),
    (
        'quién está protegido sale de PartyGuests, no de «tiene nombre»',
        ORDEN,
        "        if ($name !== '' && $confirmed !== [] && in_array(PersonNameKey::for($name), $confirmed, true)) {",
        "        if ($name !== '') {",
        php(GANA),
    ),
    (
        'las VACÍAS caen las últimas',
        ORDEN,
        '        return array_merge($cubos[self::CONFIRMADA], $cubos[self::ESCRITA], $cubos[self::VACIA]);',
        '        return array_merge($cubos[self::CONFIRMADA], $cubos[self::VACIA], $cubos[self::ESCRITA]);',
        php(CABEN),
    ),
    (
        'el orden del anfitrión se CONSERVA dentro de cada grupo',
        ORDEN,
        '        return array_merge($cubos[self::CONFIRMADA], $cubos[self::ESCRITA], $cubos[self::VACIA]);',
        '        return array_merge(array_reverse($cubos[self::CONFIRMADA]), $cubos[self::ESCRITA], $cubos[self::VACIA]);',
        php(BORDE),
    ),
    # ── La punta del AJUSTE: lo que ya estaba guardado ──────────────────────────────────────────
    (
        'el ajuste compacta AL BAJAR',
        ADJ,
        '        $rows = $desired < $from ? $this->compactForReduction($item, $desired) : null;',
        '        $rows = null;',
        php(BORDE),
    ),
    (
        'el ajuste compacta SOLO al bajar, no al subir',
        ADJ,
        '        $rows = $desired < $from ? $this->compactForReduction($item, $desired) : null;',
        '        $rows = $this->compactForReduction($item, $desired);',
        php(SUBIR),
    ),
    (
        'el aviso se cuenta sobre el orden YA compactado',
        ADJ,
        '        $discarded = $this->filledFormsBeyond($item, $desired, $rows);',
        '        $discarded = $this->filledFormsBeyond($item, $desired);',
        php(CABEN),
    ),
    # ── La punta del GUARDADO: lo que manda el navegador ────────────────────────────────────────
    (
        '❗ el GUARDADO también compacta (la costura, por HTTP)',
        ITEM,
        '            if (count($rows) > (int) $this->quantity) {\n                $rows = app(GuestCardOrder::class)->confirmedFirst($this, $rows);\n            }',
        '            // mutado',
        php(HTTP),
    ),
    (
        '❗ …pero SOLO si se va a recortar: si no, mueve fichas sin que nadie lo pida',
        ITEM,
        '            if (count($rows) > (int) $this->quantity) {',
        '            if (true) {',
        php(PROPUESTA),
    ),
    (
        'y compacta DESPUÉS de ordenar las claves, no antes (`#571`)',
        ITEM,
        '            $rows = TicketType::orderGuestRows($guests);',
        '            $rows = $guests;',
        php(ENCOGE),
    ),
]

# ─── SUPERVIVIENTES DECLARADOS ──────────────────────────────────────────────────────────────────
#
# Mutaciones que NO muerden y se declaran en vez de bajar el denominador (`DECISIONES #577`).
DECLARADOS: list[tuple[str, str]] = []


def green(cmd: list[str]) -> bool:
    return subprocess.run(cmd, cwd=ROOT, capture_output=True, text=True).returncode == 0


def main() -> int:
    for name, _path, _old, _new, cmd in MUTATIONS:
        if not green(cmd):
            print(f'CONTROL en ROJO antes de mutar («{name}»): el arnés no puede medir nada')
            return 2

    bitten = 0
    for name, path, old, new, cmd in MUTATIONS:
        file = ROOT / path
        original = file.read_text(encoding='utf-8')
        if original.count(old) != 1:
            print(f'la mutación «{name}» no encuentra su sitio EXACTO en {path}: el arnés ha caducado')
            return 2
        file.write_text(original.replace(old, new, 1), encoding='utf-8')
        try:
            red = not green(cmd)
        finally:
            file.write_text(original, encoding='utf-8')
        print(('MUERDE     ' if red else 'NO MUERDE  ') + name)
        bitten += int(red)

    for que, porque in DECLARADOS:
        print(f'DECLARADO  {que} — {porque}')

    print(f'{bitten}/{len(MUTATIONS)}' + (f' (+{len(DECLARADOS)} declarado)' if DECLARADOS else ''))
    return 0 if bitten == len(MUTATIONS) else 1


if __name__ == '__main__':
    sys.exit(main())
