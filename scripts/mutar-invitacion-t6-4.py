#!/usr/bin/env python3
"""Arnés de mutación de la T6·4: la PUERTA y la HOJA DE SALA ven a los niños de la fiesta
(`docs/specs/celebracion-e-invitacion.md` §4.8 y §4.5·10; `DECISIONES #711`).

Cada mutación rompe UNA propiedad de la tanda y comprueba que su guarda se pone en rojo:

  · la puerta lista **las fichas y los «sí» sin apuntar**, no solo los justificantes firmados;
  · el emparejado **no es igualdad de claves** («Mateo» es «Mateo Ruiz»), o el niño sale dos veces;
  · una firma **emparejada por nombre** cuenta como firmada aunque no venga atada;
  · un «no» **no llega** a la puerta;
  · la cuenta mide sobre **lo contratado** («8 de 12»), no sobre la lista a medias;
  · un escaneo **sin fiesta no paga** una consulta de respuestas;
  · los estados **solo existen** con el justificante interno;
  · y el papel de la sala trae lo contestado —**marcado**— sin pisar lo que escribió el anfitrión.

⚠️ Exige VERDE antes de mutar y restaura cada fichero desde su copia en memoria —NUNCA con
`git checkout`—.

Uso: python3 scripts/mutar-invitacion-t6-4.py
"""
import pathlib
import subprocess
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
EXEC = ['docker', 'compose', 'exec', '-T', '-u', 'sail', 'laravel.test']

GATE = 'app/Domain/Identity/Services/GateProfile.php'
READER = 'app/Domain/Booking/Services/PartyGuestsReader.php'
SLIP = 'app/Domain/Booking/Services/ReservationSlip.php'


def php(test: str) -> list[str]:
    return EXEC + ['php', 'artisan', 'test', '--filter', test]


MUTATIONS = [
    (
        'la puerta ve a los niños SIN firma',
        GATE,
        '        $parties = array_values(array_filter($today, static fn (GateReservation $r): bool => $r->invitationOffered));',
        '        $parties = [];',
        php('GateInvitedGuestsTest::test_the_gate_lists_the_cards_and_the_yeses_the_host_has_not_written_down'),
    ),
    (
        'el emparejado NO es igualdad de claves',
        GATE,
        "                if ($child['reply_id'] === null && PersonNameKey::cardMatches($key, $reply['key'])) {",
        "                if ($child['reply_id'] === null && $key === $reply['key']) {",
        php('GateInvitedGuestsTest::test_the_gate_lists_the_cards_and_the_yeses_the_host_has_not_written_down'),
    ),
    (
        'una firma emparejada POR NOMBRE cuenta',
        GATE,
        "            if (PersonNameKey::cardMatches($child['key'], (string) $a->minor_key)) {\n                return $a;\n            }\n",
        '',
        php('GateInvitedGuestsTest::test_a_signature_matched_by_name_counts_even_without_the_tie'),
    ),
    (
        'un «no» no llega a la puerta',
        READER,
        "            ->whereIn('order_item_id', $ids)\n            ->where('attending', true)",
        "            ->whereIn('order_item_id', $ids)",
        php('GateInvitedGuestsTest::test_a_no_never_reaches_the_gate'),
    ),
    (
        'la cuenta mide sobre lo CONTRATADO',
        GATE,
        '            $expected += max(0, $r->quantity);',
        '            $expected += 0;',
        php('GateInvitedGuestsTest::test_the_count_measures_against_what_was_contracted'),
    ),
    (
        'un escaneo sin fiesta no paga la consulta',
        GATE,
        '        $repliesByItem = $parties === []\n            ? []\n            : $this->guests->partyGuestsIn(array_map(static fn (GateReservation $r): int => $r->orderItemId, $parties));',
        '        $repliesByItem = $this->guests->partyGuestsIn(array_map(static fn (GateReservation $r): int => $r->orderItemId, $today));',
        php('GateInvitedGuestsTest::test_a_reservation_without_the_invitation_asks_nothing_about_replies'),
    ),
    (
        'los estados solo con el justificante INTERNO',
        GATE,
        "                    'entry' => ! $internal || ! $r->waiverOffered",
        "                    'entry' => false",
        php('GateInvitedGuestsTest::test_the_states_only_exist_when_the_waiver_is_internal'),
    ),
    (
        'el papel trae lo contestado, MARCADO',
        SLIP,
        "                if ($value === '' && $proposal !== null) {",
        '                if (false) {',
        php('InvitationProposalsTest::test_a_pending_yes_reaches_the_paper_marked_as_not_reviewed'),
    ),
    (
        'el papel no pisa lo que escribió el anfitrión',
        SLIP,
        "                if ($value === '' && $proposal !== null) {",
        '                if ($proposal !== null) {',
        php('InvitationProposalsTest::test_the_paper_never_overwrites_what_the_host_wrote'),
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
