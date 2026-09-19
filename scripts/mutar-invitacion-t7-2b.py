#!/usr/bin/env python3
"""Arnés de mutación de la T7·2b: el aviso de la víspera
(`docs/specs/celebracion-e-invitacion.md` §4.9 y §10.17; `DECISIONES #717`).

Cada mutación rompe UNA propiedad de la unidad y comprueba que su guarda se pone en rojo:

  · sale **a la hora del PARQUE** y no antes de las 18:00 —salvo `--force`—;
  · **solo a las reservas de MAÑANA**, y nunca a una cancelada;
  · **una sola vez**, aunque el comando corra cada hora;
  · **solo si queda algo por hacer**, y sin nada pendiente **tampoco se marca**;
  · **sin correo del titular no se avisa ni se marca**;
  · y marcar **no mueve el testigo** de los extras — el `toBase()` que el de Eloquent no da.

⚠️ Exige VERDE antes de mutar y restaura cada fichero desde su copia en memoria.

Uso: python3 scripts/mutar-invitacion-t7-2b.py
"""
import pathlib
import subprocess
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
EXEC = ['docker', 'compose', 'exec', '-T', '-u', 'sail', 'laravel.test']

CMD = 'app/Console/Commands/SendVisitEveNotices.php'
MAIL = 'app/Notifications/VisitEveNotice.php'

T = 'VisitEveNoticeTest::'


def php(test: str) -> list[str]:
    return EXEC + ['php', 'artisan', 'test', '--filter', test]


MUTATIONS = [
    (
        'antes de las 18:00 no se avisa',
        CMD,
        "        if ($now->hour < self::FROM_HOUR && ! $this->option('force')) {",
        "        if (false && ! $this->option('force')) {",
        php(T + 'test_before_six_it_says_nothing_unless_forced'),
    ),
    (
        '`--force` sirve para algo (staging)',
        CMD,
        "        if ($now->hour < self::FROM_HOUR && ! $this->option('force')) {",
        '        if ($now->hour < self::FROM_HOUR) {',
        php(T + 'test_before_six_it_says_nothing_unless_forced'),
    ),
    (
        '⏰ la hora es la del PARQUE, no la del contenedor',
        CMD,
        '        $now = DisplayTime::now();',
        '        $now = Carbon::now();',
        php(T + 'test_the_hour_is_the_park_s_and_not_the_container_s'),
    ),
    (
        'solo las reservas de MAÑANA',
        CMD,
        '        $tomorrow = $now->copy()->addDay()->toDateString();',
        '        $tomorrow = $now->copy()->toDateString();',
        php(T + 'test_only_tomorrow_is_the_eve'),
    ),
    (
        'nunca dos veces, aunque corra cada hora',
        CMD,
        "            ->whereNull('eve_notice_at')\n",
        '',
        php(T + 'test_it_never_warns_twice_although_the_command_runs_every_hour'),
    ),
    (
        'sin nada pendiente no se escribe a nadie',
        CMD,
        '            if (! $work->any()) {\n                continue;\n            }\n',
        '',
        php(T + 'test_with_nothing_pending_nobody_gets_written_to'),
    ),
    (
        'sin correo del titular no se avisa ni se marca',
        CMD,
        "            if ($user === null || trim((string) $user->email) === '') {\n                continue;\n            }\n",
        '            if ($user === null) {\n                continue;\n            }\n',
        php(T + 'test_without_an_email_there_is_nobody_to_warn_and_nothing_to_mark'),
    ),
    (
        # ⚠️⚠️ La mutación que importa de esta tanda: es EXACTAMENTE lo que estaba escrito antes de
        # que el caso del testigo lo cazara. El constructor de Eloquent llama a
        # `addUpdatedAtColumn()`, así que marcar el aviso movía el testigo del post-form.
        'marcar NO mueve el testigo (el `toBase()`)',
        CMD,
        '            OrderItem::query()->whereKey($reservation->getKey())->toBase()\n                ->update([',
        '            OrderItem::query()->whereKey($reservation->getKey())\n                ->update([',
        php(T + 'test_warning_the_customer_does_not_move_the_witness_of_the_extras'),
    ),
    (
        'el correo nombra solo lo que falta',
        MAIL,
        '        if ($this->pending->guestsMissing() > 0) {',
        '        if (true) {',
        php(T + 'test_the_email_names_only_what_is_missing'),
    ),
    (
        'y sí nombra las fichas cuando son lo que falta',
        MAIL,
        '        if ($this->pending->guestsMissing() > 0) {',
        '        if (false) {',
        php(T + 'test_the_email_carries_the_figure_of_the_cards_when_they_are_what_is_missing'),
    ),
    (
        'el saldo del parque se dice, y en su aviso',
        MAIL,
        '        if ($this->pending->balanceAtParkCents > 0) {',
        '        if (false) {',
        php(T + 'test_the_email_names_only_what_is_missing'),
    ),
    (
        'la frase que quita el susto va siempre',
        MAIL,
        "            ->line(__('emails.visit_eve.not_serious'))\n",
        '',
        php(T + 'test_the_email_names_only_what_is_missing'),
    ),
]


# ─── SUPERVIVIENTES DECLARADOS ──────────────────────────────────────────────────────────────────
#
# Mutaciones que NO muerden y se declaran en vez de bajar el denominador (`DECISIONES #577`): bajar
# el denominador para enseñar un arnés limpio es mentir en el informe. Aquí no se ejecutan; se
# escriben para que el siguiente sepa que se miraron y por qué se quedaron fuera.
DECLARADOS = [
    (
        "quitar `->whereNull('cancelled_at')` del filtro de la consulta",
        'No cambia la conducta, y no debe: una reserva cancelada ya sale con «nada pendiente» del '
        'lector (`#716`, y allí sí tiene su mutación, que muerde). Lo de aquí es un filtro que '
        'ahorra cargar filas que se van a descartar, no la guarda. Dejarlo como mutación sería '
        'medir dos veces la misma propiedad y llamarla dos guardas.',
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

    for que, porque in DECLARADOS:
        print(f'DECLARADO  {que} — {porque}')

    print(f'{bitten}/{len(MUTATIONS)} (+{len(DECLARADOS)} declarado)')
    return 0 if bitten == len(MUTATIONS) else 1


if __name__ == '__main__':
    sys.exit(main())
