#!/usr/bin/env python3
"""Arnés de mutación de la T6·2, las respuestas propuestas y su adopción
(`docs/specs/celebracion-e-invitacion.md` §4.7 y §10.9, `DECISIONES #709`).

Cada mutación rompe UNA propiedad de la tanda y comprueba que su guarda se pone en rojo:

  · se prerrellenan **solo los campos vacíos**: lo que el anfitrión escribió manda;
  · la marca de adopción viaja **fuera de la fila** (`adopt[]`, no `guests[i][…]`);
  · un «no» **no se pinta** sobre ninguna ficha;
  · guardar **adopta** lo que se pintó;
  · y adopta **solo lo que se pintó** — el caso INTERCALADO: lo que llegó después sigue pendiente;
  · al guardar se **reconcilia**: una adoptada cuyo nombre ya no está, se descarta.

⚠️ Exige VERDE antes de mutar y restaura cada fichero desde su copia en memoria —NUNCA con
`git checkout`—. Tras tocar un Blade, limpia la vista compilada.

Uso: python3 scripts/mutar-invitacion-t6-2.py
"""
import pathlib
import subprocess
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
EXEC = ['docker', 'compose', 'exec', '-T', '-u', 'sail', 'laravel.test']

CONTROLLER = 'app/Http/Controllers/GuestFormController.php'
BLADE = 'resources/views/reservation/guests.blade.php'


def php(test: str) -> list[str]:
    return EXEC + ['php', 'artisan', 'test', '--filter', test]


MUTATIONS = [
    (
        'se prerrellena solo lo VACÍO',
        CONTROLLER,
        "                if (trim((string) ($row[$key] ?? '')) === '' && trim((string) $value) !== '') {",
        "                if (trim((string) $value) !== '') {",
        php('InvitationProposalsTest::test_a_pending_yes_lands_on_a_card_and_fills_only_what_is_empty'),
    ),
    (
        'la marca viaja FUERA de la fila',
        BLADE,
        '                                    <input type="hidden" name="adopt[]" value="{{ $mark[\'id\'] }}">',
        '                                    <input type="hidden" name="guests[{{ $i }}][reply_id]" value="{{ $mark[\'id\'] }}">',
        php('InvitationProposalsTest::test_the_adoption_mark_travels_outside_the_row'),
    ),
    (
        # ⚠️ La mutación apunta al DOMINIO y no al controlador: ahí es donde vive la propiedad —un
        # «no» no recibe ficha—, y la comprobación que había en la pantalla era una copia que
        # ninguna prueba podía poner en rojo, así que se retiró (`#704`).
        'un «no» no se pinta sobre ninguna ficha',
        'app/Domain/Booking/Services/PartyInvitations.php',
        "                'slot_index' => $isYes ? $this->takeSlotFor($slots, $childKey) : null,",
        "                'slot_index' => $this->takeSlotFor($slots, $childKey),",
        php('InvitationProposalsTest::test_a_no_is_not_painted_over_any_card'),
    ),
    (
        'guardar ADOPTA lo que se pintó',
        CONTROLLER,
        '        if ($adopt !== []) {\n            app(PartyInvitations::class)->adopt($reservation, array_map(intval(...), $adopt));\n        }\n',
        '',
        php('InvitationProposalsTest::test_saving_adopts_what_was_painted_with_the_key_of_the_card'),
    ),
    (
        'adopta SOLO lo que se pintó (el intercalado)',
        CONTROLLER,
        '            app(PartyInvitations::class)->adopt($reservation, array_map(intval(...), $adopt));',
        '            app(PartyInvitations::class)->adopt($reservation, \\App\\Domain\\Booking\\Models\\InvitationReply::query()'
        "->where('order_item_id', $reservation->getKey())->pluck('id')->all());",
        php('InvitationProposalsTest::test_a_yes_that_arrives_after_painting_stays_pending_and_nothing_is_deleted'),
    ),
    (
        'al guardar se RECONCILIA lo adoptado',
        CONTROLLER,
        "        if ($this->submittedGuestFormArray($request, 'guests') !== null) {\n            app(PartyInvitations::class)->reconcileAdopted($reservation->fresh(['ticketType']) ?? $reservation);\n        }\n",
        '',
        php('InvitationProposalsTest::test_an_adopted_reply_whose_name_disappears_is_discarded'),
    ),
]


def green(cmd: list[str]) -> bool:
    return subprocess.run(cmd, cwd=ROOT, capture_output=True, text=True).returncode == 0


def clear_views() -> None:
    subprocess.run(EXEC + ['php', 'artisan', 'view:clear'], cwd=ROOT, capture_output=True, text=True)


def main() -> int:
    clear_views()
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
        clear_views()
        try:
            red = not green(cmd)
        finally:
            file.write_text(original, encoding='utf-8')
            clear_views()
        print(('MUERDE     ' if red else 'NO MUERDE  ') + name)
        bitten += int(red)

    print(f'{bitten}/{len(MUTATIONS)}')
    return 0 if bitten == len(MUTATIONS) else 1


if __name__ == '__main__':
    sys.exit(main())
