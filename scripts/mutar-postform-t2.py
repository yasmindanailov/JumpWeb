#!/usr/bin/env python3
"""Arnés de mutación de la T2 del formulario post-reserva (`docs/specs/celebracion-e-invitacion.md` §10.2, `#571`).

Cada mutación rompe UNA propiedad de la tanda y comprueba que su guarda se pone en rojo:

  · el guardado ordena las fichas por CLAVE (sin eso, pintar las pendientes arriba reordena a los invitados);
  · el número de invitados pertenece al FORMULARIO (`form="gf-form"`; sin eso el navegador no lo envía);
  · el pegado solo va a las fichas VACÍAS;
  · «Falta …» solo sale en una ficha que ya tiene algún dato;
  · las fichas listas se PLIEGAN en su grupo.

⚠️ Exige VERDE antes de mutar (un filtro que no casa con ningún caso sale con código ≠ 0 y parecería que
muerde) y restaura cada fichero desde su copia en memoria —NUNCA con `git checkout`, que se llevaría el
trabajo sin commitear (la trampa pagada en `#298`)—.

Uso: python3 scripts/mutar-postform-t2.py
"""
import pathlib
import subprocess
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
EXEC = ['docker', 'compose', 'exec', '-T', '-u', 'sail', 'laravel.test']


def php(test: str) -> list[str]:
    return EXEC + ['php', 'artisan', 'test', '--filter', test]


NODE = EXEC + ['node', '--test', 'resources/js/guest-form/logic.test.js']

MUTATIONS = [
    (
        'el guardado ordena las fichas por clave',
        'app/Domain/Booking/Models/TicketType.php',
        '            ksort($rows);\n',
        '',
        php('GuestFormManyGuestsTest::test_saving_with_the_cards_out_of_order_keeps_every_guest_in_place'),
    ),
    (
        'el número de invitados pertenece al formulario',
        'resources/views/reservation/guests.blade.php',
        ' inputmode="numeric" form="gf-form"',
        ' inputmode="numeric"',
        php('GuestCountSurfacesTest::test_the_signed_page_offers_the_control_with_its_bounds'),
    ),
    (
        'el pegado solo va a las fichas vacías',
        'public/js/guest-form/logic.js',
        '    const empty = fiches.filter((fiche) => fiche.empty);',
        '    const empty = fiches;',
        NODE,
    ),
    (
        '«Falta …» solo con algún dato',
        'public/js/guest-form/logic.js',
        '        missing: hasData && firstEmpty !== undefined ? firstEmpty.label : null,',
        '        missing: firstEmpty !== undefined ? firstEmpty.label : null,',
        NODE,
    ),
    (
        'las fichas listas se pliegan en su grupo',
        'resources/views/reservation/guests.blade.php',
        "$doneIdx = $readonly ? [] : array_keys(array_filter($ficheStates, fn ($state) => $state['complete']));",
        '$doneIdx = [];',
        php('GuestFormManyGuestsTest::test_pending_cards_come_first_and_ready_ones_fold_into_a_group'),
    ),
]


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

    print(f'{bitten}/{len(MUTATIONS)}')
    return 0 if bitten == len(MUTATIONS) else 1


if __name__ == '__main__':
    sys.exit(main())
