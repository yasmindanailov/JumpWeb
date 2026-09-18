#!/usr/bin/env python3
"""Arnés de mutación de la T5·4 — compartir y calendario (`docs/specs/celebracion-e-invitacion.md` §4.6, `#705`).

Cada mutación rompe UNA propiedad de la tanda y comprueba que su guarda se pone en rojo:

  · la hora del `.ics` es de PARED, con la zona del PARQUE (`§7.2·R13`): escribirla en UTC le mueve la
    fiesta al padre una o dos horas, y ésa es la mutación que más importa;
  · la duración es la EFECTIVA, no la de la franja (la trampa de `#426`);
  · el fichero trae la definición de su zona (`VTIMEZONE`), que es lo que pide la norma;
  · lo que escribe una persona no puede romper el fichero (escapado de TEXT);
  · sin duración no hay fichero ni botón;
  · la vista previa no publica la dirección;
  · el `.ics` se sirve como calendario, no como texto.

⚠️ Exige VERDE antes de mutar (un filtro que no casa con ningún caso sale ≠ 0 y parecería que muerde) y
restaura cada fichero desde su copia en memoria —NUNCA con `git checkout`—.

Uso: python3 scripts/mutar-compartir-invitacion.py
"""
import pathlib
import subprocess
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
EXEC = ['docker', 'compose', 'exec', '-T', '-u', 'sail', 'laravel.test']

CALENDARIO = 'app/Domain/Platform/Services/CalendarFile.php'
CONTROLADOR = 'app/Http/Controllers/InvitationPageController.php'
VISTA = 'resources/views/invitation/show.blade.php'


def php(test: str) -> list[str]:
    return EXEC + ['php', 'artisan', 'test', '--filter', test]


UNIT = php('CalendarFileTest')
PARED = php('InvitationSharingTest::test_the_calendar_file_keeps_the_wall_clock_of_the_park')
SERVIDO = php('InvitationSharingTest::test_the_file_is_served_as_a_calendar_and_is_never_stored')
SIN_DURACION = php('InvitationSharingTest::test_without_a_duration_there_is_neither_a_button_nor_a_file')
PREVIA = php('InvitationSharingTest::test_the_preview_carries_the_party_and_nothing_else')

MUTATIONS = [
    (
        '❗ la hora va con la ZONA y no en UTC',
        CALENDARIO,
        "'DTSTART;TZID='.$timezone.':'.$start->format('Ymd\\THis'),",
        "'DTSTART:'.$start->format('Ymd\\THis').'Z',",
        PARED,
    ),
    (
        '❗ la zona es la del PARQUE, no la del servidor',
        CONTROLADOR,
        '            timezone: DisplayTime::timezone(),',
        "            timezone: 'UTC',",
        PARED,
    ),
    (
        'la duración es la EFECTIVA, no la de la franja',
        CONTROLADOR,
        'return $inicio === false ? null : [$inicio, $inicio->addMinutes($minutos)];',
        'return $inicio === false ? null : [$inicio, $inicio->addMinutes(60)];',
        PARED,
    ),
    (
        'el fichero trae la definición de su zona',
        CALENDARIO,
        "        $lines = ['BEGIN:VTIMEZONE', 'TZID:'.$zone->getName()];",
        "        $lines = [];",
        UNIT,
    ),
    (
        'lo que escribe una persona no rompe el fichero',
        CALENDARIO,
        "            ['\\\\\\\\', '\\\\;', '\\\\,', '\\\\n', '\\\\n', '\\\\n'],",
        "            ['\\\\\\\\', ';', ',', '\\\\n', '\\\\n', '\\\\n'],",
        UNIT,
    ),
    (
        'sin duración no hay fichero ni botón',
        CONTROLADOR,
        'if ($date === null || $hora === \'\' || $minutos === null || $minutos <= 0) {',
        'if ($date === null || $hora === \'\') {',
        SIN_DURACION,
    ),
    (
        'la vista previa no publica la dirección',
        CONTROLADOR,
        "                : __('invitation.og.description', ['time' => $hora, 'business' => $negocio]),",
        "                : __('invitation.og.description', ['time' => $hora, 'business' => $negocio]).' '.trim((string) Setting::value('address.line1')),",
        PREVIA,
    ),
    (
        'el .ics se sirve como calendario',
        CONTROLADOR,
        "            'Content-Type' => CalendarFile::MIME,",
        "            'Content-Type' => 'text/plain',",
        SERVIDO,
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
