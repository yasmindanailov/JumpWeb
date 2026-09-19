#!/usr/bin/env python3
"""Arnés de mutación de la T7·2a: el lector de «qué queda por hacer» antes de la visita
(`docs/specs/celebracion-e-invitacion.md` §4.9 y §10.16; `DECISIONES #716`).

Cada mutación rompe UNA propiedad del lector y comprueba que su guarda se pone en rojo:

  · las **cuatro cifras** se miden por separado y ninguna anula a otra;
  · **una fiesta cancelada o ya celebrada no tiene nada pendiente**;
  · **sin justificante en el producto no hay menores sin resolver** —si no, reclamaría 20 papeles—;
  · **una devolución pendiente no es trabajo del cliente**: solo cuenta `pay_at_park`;
  · las **respuestas** solo cuentan con la invitación encendida;
  · y el **progreso de fichas** sale de la misma fuente que la pantalla, no de un recuento propio.

⚠️ Exige VERDE antes de mutar y restaura cada fichero desde su copia en memoria.

Uso: python3 scripts/mutar-invitacion-t7-2a.py
"""
import pathlib
import subprocess
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
EXEC = ['docker', 'compose', 'exec', '-T', '-u', 'sail', 'laravel.test']

LECTOR = 'app/Domain/Booking/Services/PendingBeforeVisit.php'
DTO = 'app/Domain/Booking/Contracts/PendingWork.php'

T = 'PendingBeforeVisitTest::'


def php(test: str) -> list[str]:
    return EXEC + ['php', 'artisan', 'test', '--filter', test]


MUTATIONS = [
    (
        'una fiesta ya celebrada no tiene nada pendiente',
        LECTOR,
        '        if ($type === null || $reservation->isCancelled() || $reservation->isFinishedInPractice()) {',
        '        if ($type === null || $reservation->isCancelled()) {',
        php(T + 'test_a_party_already_celebrated_has_nothing_pending'),
    ),
    (
        'una reserva cancelada tampoco',
        LECTOR,
        '        if ($type === null || $reservation->isCancelled() || $reservation->isFinishedInPractice()) {',
        '        if ($type === null || $reservation->isFinishedInPractice()) {',
        php(T + 'test_a_cancelled_reservation_has_nothing_pending'),
    ),
    (
        'sin justificante en el producto, cero menores sin resolver',
        LECTOR,
        '        if ($type->guardianMode() === TicketType::GUARDIAN_NONE) {\n            return 0;\n        }\n\n',
        '',
        php(T + 'test_a_product_without_a_waiver_has_no_minors_to_resolve'),
    ),
    (
        'las plazas sin resolver descuentan las que ya tienen dueño',
        LECTOR,
        '        return max(0, (int) $reservation->quantity - $this->places->takenIn((int) $reservation->getKey()));',
        '        return max(0, (int) $reservation->quantity);',
        php(T + 'test_with_a_waiver_the_unresolved_places_are_the_ones_without_an_owner'),
    ),
    (
        'las respuestas solo cuentan con la invitación encendida',
        LECTOR,
        '        return $type->offersGuestInvitation()\n            ? (int) $this->invitations->summaryFor($reservation)[\'pending\']\n            : 0;',
        "        return (int) $this->invitations->summaryFor($reservation)['pending'];",
        # ⚠️ NO es el caso del «control» de la invitación apagada: una reserva sin invitación tampoco
        # tiene respuestas, así que allí la mutación daba el mismo 0 y SOBREVIVÍA. El que distingue
        # las dos conductas es el interruptor apagado **con respuestas ya dentro** (V5).
        php(T + 'test_replies_stop_counting_when_the_switch_is_turned_off'),
    ),
    (
        # ⚠️⚠️ Contra `pay_online`, no contra la devolución: el `max(0, …)` ya tapa un saldo negativo,
        # así que con una devolución la mutación era EQUIVALENTE. Lo que la mata es la clase cuya
        # cifra es POSITIVA sin ser dinero del parque — y es el defecto que la guarda evita: decirle
        # al cliente que lleve al parque lo que tiene que pagar por web.
        'lo que se debe ONLINE no es dinero para el parque',
        LECTOR,
        "        return $balance->kind === Balance::KIND_PAY_AT_PARK ? max(0, $balance->cents) : 0;",
        '        return max(0, $balance->cents);',
        php(T + 'test_what_is_owed_online_is_not_money_to_bring_to_the_park'),
    ),
    (
        'el saldo a pagar en el parque se cuenta',
        LECTOR,
        "        return $balance->kind === Balance::KIND_PAY_AT_PARK ? max(0, $balance->cents) : 0;",
        '        return 0;',
        php(T + 'test_what_is_left_to_pay_at_the_park_counts'),
    ),
    (
        'el progreso sale de la MISMA fuente que la pantalla',
        LECTOR,
        '        $progress = $reservation->guestFormProgress();',
        "        $progress = ['done' => count($reservation->guestData()), 'total' => (int) $reservation->quantity];",
        php(T + 'test_it_counts_the_cards_the_screen_counts'),
    ),
    (
        '«¿falta algo?» mira las CUATRO cifras',
        DTO,
        '        return $this->guestsMissing() > 0\n            || $this->repliesToReview > 0\n            || $this->minorsUnresolved > 0\n            || $this->balanceAtParkCents > 0;',
        '        return $this->guestsMissing() > 0;',
        # ⚠️ Con las fichas a medias «¿falta algo?» decía que sí por las fichas y la mutación
        # sobrevivía: el caso que la mata es aquel en que lo ÚNICO que falta es el dinero.
        php(T + 'test_the_money_alone_is_enough_to_have_something_pending'),
    ),
    (
        'con todo hecho y nada debido, no hay nada que decir',
        DTO,
        '        return $this->guestsMissing() > 0\n            || $this->repliesToReview > 0\n            || $this->minorsUnresolved > 0\n            || $this->balanceAtParkCents > 0;',
        '        return true;',
        php(T + 'test_with_everything_filled_and_nothing_owed_there_is_nothing_to_say'),
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
