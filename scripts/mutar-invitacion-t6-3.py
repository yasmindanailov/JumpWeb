#!/usr/bin/env python3
"""Arnés de mutación de la T6·3: los que no vienen, los que no caben y «No lo apuntes»
(`docs/specs/celebracion-e-invitacion.md` §4.7, §7.1·3 y §7.2·R11; `DECISIONES #710`).

Cada mutación rompe UNA propiedad de la tanda y comprueba que su guarda se pone en rojo:

  · un «no» **se ve**, en su grupo y con su nombre;
  · si ese niño está en la lista, **su ficha lo dice** (la chapa va donde empareja);
  · retirar una respuesta **baja el suelo** que impedía bajar el número de invitados;
  · retirar **no borra** (V3): la respuesta vive hasta la poda;
  · retirar **no mueve el testigo** de los extras;
  · la **reserva manda** sobre el id que llegue en el cuerpo;
  · el aviso dice **cuántas ya no caben**;
  · y tras el gesto **se vuelve al formulario**, no a «Mis pedidos».

⚠️ Exige VERDE antes de mutar y restaura cada fichero desde su copia en memoria —NUNCA con
`git checkout`—. Tras tocar un Blade, limpia la vista compilada.

Uso: python3 scripts/mutar-invitacion-t6-3.py
"""
import pathlib
import subprocess
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
EXEC = ['docker', 'compose', 'exec', '-T', '-u', 'sail', 'laravel.test']

CONTROLLER = 'app/Http/Controllers/GuestFormController.php'
DOMAIN = 'app/Domain/Booking/Services/PartyInvitations.php'
BLADE = 'resources/views/reservation/guests.blade.php'


def php(test: str) -> list[str]:
    return EXEC + ['php', 'artisan', 'test', '--filter', test]


MUTATIONS = [
    (
        'un «no» se VE, con su nombre',
        BLADE,
        "                    @if ($invitation['declined'] !== [])",
        '                    @if (false)',
        php('InvitationDeclinedTest::test_a_no_shows_in_its_own_group_and_says_until_when_the_count_can_drop'),
    ),
    (
        'la chapa del «no» va en la ficha que EMPAREJA',
        DOMAIN,
        "                if ($name !== '' && $this->matches([PersonNameKey::for($name)], $childKey)) {",
        "                if (false) {",
        php('InvitationDeclinedTest::test_a_no_that_matches_a_written_card_marks_that_card'),
    ),
    (
        'retirar BAJA EL SUELO que le bloqueaba',
        CONTROLLER,
        "        app(PartyInvitations::class)->dismiss($reservation, is_numeric($reply) ? (int) $reply : 0);",
        '',
        php('InvitationDeclinedTest::test_removing_a_pending_yes_lowers_the_floor_that_was_blocking_the_host'),
    ),
    (
        'retirar NO BORRA (V3)',
        DOMAIN,
        "        $reply->forceFill(['dismissed_at' => now()])->save();\n\n        return true;\n    }",
        "        $reply->delete();\n\n        return true;\n    }",
        php('InvitationDeclinedTest::test_removing_a_pending_yes_lowers_the_floor_that_was_blocking_the_host'),
    ),
    (
        'retirar no mueve el TESTIGO de los extras',
        CONTROLLER,
        '        $reply = $request->input(\'reply\');\n        app(PartyInvitations::class)->dismiss(',
        '        $reply = $request->input(\'reply\');\n        $reservation->touch();\n        app(PartyInvitations::class)->dismiss(',
        php('InvitationDeclinedTest::test_dismissing_does_not_move_the_witness_of_the_extras'),
    ),
    (
        'la RESERVA manda sobre el id del cuerpo',
        DOMAIN,
        "        $reply = InvitationReply::query()\n            ->where('order_item_id', $reservation->getKey())\n            ->whereKey($replyId)\n            ->whereNull('dismissed_at')",
        "        $reply = InvitationReply::query()\n            ->whereKey($replyId)\n            ->whereNull('dismissed_at')",
        php('InvitationDeclinedTest::test_a_reply_of_another_party_is_not_touched'),
    ),
    (
        'el aviso dice cuántas NO CABEN',
        CONTROLLER,
        "                static fn (array $p): bool => $p['attending'] && $p['slot_index'] === null,",
        '                static fn (array $p): bool => false,',
        php('InvitationDeclinedTest::test_the_notice_says_how_many_replies_no_longer_fit'),
    ),
    (
        'tras el gesto se vuelve AL FORMULARIO',
        CONTROLLER,
        "            return route('reservation.guests', ['reservation' => $reservation]);",
        "            return route('account.orders');",
        php('InvitationDeclinedTest::test_the_host_stays_on_the_form_after_the_gesture'),
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
