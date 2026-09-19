#!/usr/bin/env python3
"""Arnés de mutación de la T6·6: «Escribir el recordatorio»
(`docs/specs/celebracion-e-invitacion.md` §4.7 y §10.13; `DECISIONES #713`).

Cada mutación rompe UNA propiedad de la tanda y comprueba que su guarda se pone en rojo:

  · «quien falta» es quien NO ha contestado, con la regla de emparejado de la casa;
  · una respuesta **descartada** es una respuesta: no se le vuelve a reclamar;
  · los **nombres solo si el anfitrión los pide**, y el **enlace siempre dentro del texto**;
  · la cuenta la suma la **BD** (dos pestañas son dos) y **no rebasa** el techo de su columna;
  · escribir el recordatorio **no mueve el testigo** de los extras;
  · cerrado el plazo **no se ofrece**, y una fiesta celebrada **no escribe nada**;
  · el texto vuelve por la **sesión**, y el sello se fecha en la zona del **parque**.

⚠️ Exige VERDE antes de mutar y restaura cada fichero desde su copia en memoria.

Uso: python3 scripts/mutar-invitacion-t6-6.py
"""
import pathlib
import subprocess
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
EXEC = ['docker', 'compose', 'exec', '-T', '-u', 'sail', 'laravel.test']

SERVICE = 'app/Domain/Booking/Services/PartyInvitations.php'
CONTROLLER = 'app/Http/Controllers/GuestFormController.php'
BLADE = 'resources/views/reservation/guests.blade.php'

T = 'InvitationReminderTest::'


def php(test: str) -> list[str]:
    return EXEC + ['php', 'artisan', 'test', '--filter', test]


MUTATIONS = [
    (
        'quien ya contestó NO falta',
        SERVICE,
        "                if (PersonNameKey::cardMatches($card['key'], (string) $childKey)) {",
        '                if (false) {',
        php(T + 'test_the_ones_we_are_missing_are_the_cards_nobody_has_answered_for'),
    ),
    (
        'la ficha «Mateo» la empareja «Mateo Ruiz»',
        SERVICE,
        "                if (PersonNameKey::cardMatches($card['key'], (string) $childKey)) {",
        "                if ($card['key'] === (string) $childKey) {",
        php(T + 'test_a_card_with_only_a_first_name_is_matched_by_the_full_name_the_parent_wrote'),
    ),
    (
        'una respuesta DESCARTADA también es una respuesta',
        SERVICE,
        "            ->where('order_item_id', $reservation->getKey())\n            ->pluck('child_key')",
        "            ->where('order_item_id', $reservation->getKey())\n            ->whereNull('dismissed_at')\n            ->pluck('child_key')",
        php(T + 'test_a_reply_the_host_took_off_the_list_still_counts_as_answered'),
    ),
    (
        'los nombres SOLO si el anfitrión los pide',
        SERVICE,
        '        $names = $withNames ? $this->awaitingNamesIn($reservation) : [];',
        '        $names = $this->awaitingNamesIn($reservation);',
        php(T + 'test_the_text_carries_the_link_and_names_nobody_unless_asked'),
    ),
    (
        'el enlace viaja DENTRO del texto',
        SERVICE,
        '        if ($url !== null) {\n            $lines[] = $url;\n        }\n',
        '',
        php(T + 'test_the_text_carries_the_link_and_names_nobody_unless_asked'),
    ),
    (
        'dos avisos son DOS',
        SERVICE,
        "                    : DB::raw('reminded_count + 1'),",
        '                    : 1,',
        php(T + 'test_writing_the_reminder_records_when_and_how_many_times'),
    ),
    (
        # ⚠️ Esta mutación NO es la de arriba: con la suma en PHP la cuenta sigue subiendo mientras
        # los avisos vayan en fila, y solo se ve cuando **dos pestañas leyeron el mismo número**.
        'la suma la hace la BD, no el modelo',
        SERVICE,
        "                    : DB::raw('reminded_count + 1'),",
        '                    : ((int) $invitation->reminded_count + 1),',
        php(T + 'test_two_tabs_of_the_same_host_do_not_write_the_same_number_twice'),
    ),
    (
        'la cuenta no rebasa el techo de su columna',
        SERVICE,
        '        $capped = (int) $invitation->reminded_count >= self::REMINDED_COUNT_MAX;',
        '        $capped = false;',
        php(T + 'test_the_counter_does_not_overflow_its_column'),
    ),
    (
        'escribir el recordatorio NO mueve el testigo de los extras',
        CONTROLLER,
        '        $invitations->remind($invitation);\n',
        '        $invitations->remind($invitation);\n        $reservation->touch();\n',
        php(T + 'test_writing_the_reminder_does_not_move_the_witness_of_the_extras'),
    ),
    (
        'una fiesta ya celebrada no escribe nada',
        CONTROLLER,
        "        if ($reservation->isFinishedInPractice()) {\n            return redirect()\n                ->to($this->backUrl($request, $reservation))\n                ->with('status', 'guest-form-readonly');\n        }\n\n        $invitations = app(PartyInvitations::class);\n        $invitation = $invitations->forReservation($reservation);\n\n        if ($invitation === null) {\n            abort(404);\n        }\n\n        // La casilla",
        "        $invitations = app(PartyInvitations::class);\n        $invitation = $invitations->forReservation($reservation);\n\n        if ($invitation === null) {\n            abort(404);\n        }\n\n        // La casilla",
        php(T + 'test_a_party_already_celebrated_writes_nothing'),
    ),
    (
        'el texto vuelve por la SESIÓN',
        CONTROLLER,
        "            ->with('status', 'invitation-reminded')\n            ->with('reminder_text', $text);",
        "            ->with('status', 'invitation-reminded');",
        php(T + 'test_the_text_comes_back_on_the_screen_and_never_in_the_url'),
    ),
    (
        'el sello se fecha en la zona del PARQUE',
        CONTROLLER,
        '                : DisplayTime::dayLabel($invitation->reminded_at->copy()->setTimezone(DisplayTime::timezone())),',
        '                : DisplayTime::dayLabel($invitation->reminded_at),',
        php(T + 'test_the_seal_says_how_many_times_and_when'),
    ),
    (
        'cerrado el plazo, el recordatorio no se ofrece',
        BLADE,
        "                    @if ($invitation['shareable'] && $invitation['replies_open'])",
        "                    @if ($invitation['shareable'])",
        php(T + 'test_the_reminder_is_not_offered_once_the_replies_are_closed'),
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
