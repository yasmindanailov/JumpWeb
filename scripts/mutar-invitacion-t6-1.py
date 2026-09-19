#!/usr/bin/env python3
"""Arnés de mutación de la T6·1, el bloque de la invitación en el post-form
(`docs/specs/celebracion-e-invitacion.md` §4.7 y §10.8, `DECISIONES #708`).

Cada mutación rompe UNA propiedad de la tanda y comprueba que su guarda se pone en rojo:

  · personalizar NO mueve el testigo de la reserva (`order_items.updated_at`);
  · sin nombre de quien cumple, el enlace NO se reparte;
  · un texto con enlace se rechaza **y se dice**;
  · el `hidden` de delante de la casilla es lo que permite APAGAR el teléfono;
  · se entra por la MISMA puerta que el formulario (un desconocido no personaliza);
  · una fiesta celebrada no pinta el bloque ni materializa la invitación;
  · el plazo se escribe como FECHA;
  · los dos textos admiten vacío (`ConvertEmptyStringsToNull` los convierte en `null`).

⚠️ Exige VERDE antes de mutar (un filtro que no casa con ningún caso sale con código ≠ 0 y parecería
que muerde) y restaura cada fichero desde su copia en memoria —NUNCA con `git checkout`, que se
llevaría el trabajo sin commitear (la trampa pagada en `#298`)—.

⚠️ Tras tocar un Blade hay que limpiar la vista compilada, o la mutación siguiente mediría la
plantilla vieja.

Uso: python3 scripts/mutar-invitacion-t6-1.py
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
        'personalizar no mueve el testigo de la reserva',
        CONTROLLER,
        '        $invitations->personalize($invitation, $data);\n',
        '        $invitations->personalize($invitation, $data);\n        $reservation->touch();\n',
        php('InvitationHostBlockTest::test_personalising_writes_only_the_invitation_and_never_the_witness'),
    ),
    (
        'sin nombre de quien cumple el enlace no se reparte',
        BLADE,
        "                    @if ($invitation['shareable'])",
        '                    @if (true)',
        php('InvitationHostBlockTest::test_without_a_name_it_says_what_is_missing_instead_of_handing_over_the_link'),
    ),
    (
        'un texto con enlace se rechaza Y SE DICE',
        CONTROLLER,
        "            ->with('status', $rejected ? 'invitation-text-rejected' : 'invitation-saved');",
        "            ->with('status', 'invitation-saved');",
        php('InvitationHostBlockTest::test_a_text_with_a_link_is_refused_and_the_page_says_so'),
    ),
    (
        'el hidden de delante es lo que permite APAGAR el teléfono',
        BLADE,
        '                            <input type="hidden" name="show_host_phone" value="0">\n',
        '',
        php('InvitationHostBlockTest::test_unticking_the_phone_turns_it_off'),
    ),
    (
        'se entra por la misma puerta que el formulario',
        CONTROLLER,
        '    public function updateInvitation(Request $request, OrderItem $reservation): RedirectResponse\n    {\n        $this->authorizeGuestFormAccess($request, $reservation);\n',
        '    public function updateInvitation(Request $request, OrderItem $reservation): RedirectResponse\n    {\n',
        php('InvitationHostBlockTest::test_a_stranger_cannot_personalise_it'),
    ),
    (
        'una fiesta celebrada no pinta el bloque ni crea la fila',
        CONTROLLER,
        '        if ($reservation->isFinishedInPractice()) {\n            return null;\n        }\n\n',
        '',
        php('InvitationHostBlockTest::test_a_party_already_held_neither_paints_the_block_nor_personalises'),
    ),
    (
        'el plazo se escribe como fecha',
        CONTROLLER,
        "            'deadline' => $deadline === null ? '' : DisplayTime::dayLabel($deadline),",
        "            'deadline' => '',",
        php('InvitationHostBlockTest::test_the_block_hands_over_the_link_the_deadline_as_a_date_and_the_tally'),
    ),
    (
        'los dos textos admiten vacío (el vacío llega como null)',
        CONTROLLER,
        "            'host_line' => ['sometimes', 'nullable', 'string', 'max:'.PartyInvitation::HOST_LINE_MAX],",
        "            'host_line' => ['sometimes', 'string', 'max:'.PartyInvitation::HOST_LINE_MAX],",
        php('InvitationHostBlockTest::test_an_empty_line_is_not_a_refusal'),
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
