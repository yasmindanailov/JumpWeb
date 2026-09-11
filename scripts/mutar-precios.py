#!/usr/bin/env python3
"""Arnés de mutación de `#531` — la página `/precios` rehecha desde su artboard (Fase 3 · T3b): la
tabla por zona con las dos columnas de precio entero, la hora extra como FILA, la semana dibujada,
la lista de festivos con el hecho de cada fecha y el bloque de complementos con la ventaja del panel.

Mismo molde endurecido que `mutar-cabecera.py` (`#525`) y `mutar-pie.py` (`#522`):
  · en PYTHON, y cada mutación verifica que el fichero CAMBIÓ antes de correr nada;
  · EXIGE VERDE antes de mutar (`#337`) y ÁRBOL LIMPIO en los ficheros que muta (`#181`);
  · restaura SIEMPRE, también si el proceso revienta (`#448`).

    python3 scripts/mutar-precios.py
"""
import subprocess
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
FILTRO = 'PricingPageTest'
TABLA = 'app/Domain/Booking/Services/RateTable.php'
VISTA = 'resources/views/pages/pricing.blade.php'
HORARIO = 'app/Domain/Content/Services/ScheduleDisplay.php'
CALENDARIO = 'app/Domain/Booking/Services/OperatingSchedule.php'
FICHEROS = [TABLA, VISTA, HORARIO, CALENDARIO, 'app/Domain/Booking/Concerns/ReadsRateFacts.php']

# (nombre, fichero, texto que se busca, texto por el que se cambia)
MUTACIONES = [
    # ── El defecto REAL que abrió la tanda: «solo» colgando de la pregunta equivocada ──
    ("la frase «solo» vuelve a colgar de «¿tiene recargo?» (el defecto de #531)",
     TABLA,
     "'note' => ($especial === null && $dias !== null)",
     "'note' => ($ticket->specialRateSurcharges() === [] && $dias !== null)"),

    ("una entrada que no se vende el finde deja de decirlo",
     TABLA,
     "'note' => ($especial === null && $dias !== null)\n                ? __('landing.rates.days_only', ['days' => $dias])\n                : null,",
     "'note' => null,"),

    # ── Una columna por tarifa pregunta por SU tarifa ──
    ("la columna normal de la hora extra escribe el precio de REFERENCIA (su cifra especial)",
     TABLA,
     "'normal' => $this->writeCents($this->normalPriceCents($addon)),",
     "'normal' => $this->writeCents($addon->displayPriceCents()),"),

    ("la columna especial repite la normal",
     TABLA,
     "'special' => $this->writeCents($especial),",
     "'special' => $this->writeCents($this->normalPriceCents($ticket)),"),

    # ── El chip marca a UNA ──
    ("el chip se pinta en todas las filas, no solo en la que lidera",
     TABLA,
     "'badge' => $lidera ? $badge : null,",
     "'badge' => $badge,"),

    # ── La semana dibujada ──
    ("ningún día de la semana se marca como especial",
     TABLA,
     "'special' => ! in_array($weekday, $normales, true),",
     "'special' => false,"),

    ("la tira se dibuja aunque no se pueda saber qué día es especial",
     TABLA,
     "if ($normales === null || $normales === []) {\n            return null;\n        }\n\n        return array_map(",
     "if ($normales === []) {\n            return null;\n        }\n\n        return array_map("),

    ("los días pierden su nombre completo (la inicial no es un nombre accesible)",
     VISTA,
     "<span class=\"sr-only\">{{ $dia['name'] }}: {{ $dia['special'] ? __('landing.pricing.week_special') : __('landing.pricing.week_normal') }}</span>",
     ""),

    # ── La hora extra: fila de la tabla, NO ficha del escaparate ──
    ("la hora extra vuelve al bloque de complementos",
     VISTA,
     "LandingAddonPresenter::unique($tickets, false, true)",
     "LandingAddonPresenter::unique($tickets, false, false)"),

    ("el complemento de tiempo deja de reconocerse por su mecanismo",
     TABLA,
     "if (! $addon->occupiesAfterParent()) {",
     "if (false) {"),

    # ── Lo que se añade: la ventaja la escribe el panel ──
    ("la fila del complemento se queda sin la ventaja que escribe el panel",
     VISTA,
     "<span class=\"extras__note\">{{ $extra['features'][0] }}</span>",
     ""),

    # ── Los festivos: cada fecha dice SU hecho ──
    ("el bloque de festivos se pinta aunque no haya ninguna fecha cargada",
     VISTA,
     "@if ($holidays !== [])",
     "@if (true)"),

    ("una fecha CERRADA anuncia su horario en vez de decir que está cerrado",
     HORARIO,
     "'fact' => $row['is_closed'] ? __('landing.info.closed') : ($row['rate'] ?? $row['detail']),",
     "'fact' => $row['rate'] ?? $row['detail'],"),

    ("la lista de festivos deja de decir qué tarifa aplica ese día",
     HORARIO,
     "'fact' => $row['is_closed'] ? __('landing.info.closed') : ($row['rate'] ?? $row['detail']),",
     "'fact' => $row['is_closed'] ? __('landing.info.closed') : $row['detail'],"),

    ("la fecha especial viaja sin su tarifa desde el dominio",
     CALENDARIO,
     "rateLabel: $special->rateType?->tr('label') ?: null,",
     "rateLabel: null,"),

    # ── La explicación nombra la tarifa del PANEL ──
    ("la explicación escribe los días a mano en vez de leer el rótulo del panel",
     VISTA,
     "{!! __('landing.pricing.special_text', ['label' => '<b>'.e($specialLabel).'</b>']) !!}",
     "{!! __('landing.pricing.special_text', ['label' => '<b>viernes y findes</b>']) !!}"),

    # ── Lo que la página NO hace ──
    ("la página recupera un CTA propio (la acción la trae el armazón)",
     VISTA,
     "        <div class=\"rate-page__birthdays\">",
     "        <div class=\"page__cta\"></div>\n        <div class=\"rate-page__birthdays\">"),

    ("la salida a cumpleaños deja de llevar a su página",
     VISTA,
     "<a href=\"{{ route('cumpleanos') }}\" class=\"rate-page__birthdays-link\">",
     "<a href=\"#\" class=\"rate-page__birthdays-link\">"),
]


def git(*args):
    return subprocess.run(['git', *args], cwd=RAIZ, capture_output=True, text=True)


def restaura():
    git('checkout', '-q', '--', *FICHEROS)


def verde():
    r = subprocess.run(
        ['docker', 'compose', 'exec', '-u', 'sail', '-T', 'laravel.test',
         'php', 'artisan', 'test', '--filter=' + FILTRO],
        cwd=RAIZ, capture_output=True, text=True)
    return r.returncode == 0, r.stdout + r.stderr


def main():
    sucio = git('status', '--porcelain', '--', *FICHEROS).stdout.strip()
    if sucio:
        print('✗ hay cambios sin commitear en los ficheros que se mutan — commitea antes:')
        print(sucio)
        return 2

    print('── CONTROL: la guarda tiene que estar VERDE antes de mutar ──')
    ok, salida = verde()
    if not ok:
        print('✗ el árbol limpio ya sale ROJO: el arnés no mide nada')
        print(salida[-1500:])
        return 2
    print('✓ verde\n')

    print('── mutaciones ──')
    vivas = []
    try:
        for nombre, rel, busca, cambia in MUTACIONES:
            ruta = RAIZ / rel
            antes = ruta.read_text(encoding='utf-8')
            n = antes.count(busca)
            if n != 1:
                # ⚠️ Una mutación que no se aplica NO es una guarda que aguanta.
                print('  ⚠ NO APLICADA (%d coincidencias)  %s' % (n, nombre))
                vivas.append(nombre + ' [no aplicada]')
                continue
            ruta.write_text(antes.replace(busca, cambia, 1), encoding='utf-8')
            assert ruta.read_text(encoding='utf-8') != antes, 'el fichero no cambió'

            ok, _ = verde()
            print(('  ✗ SOBREVIVE  ' if ok else '  ✓ muere      ') + nombre)
            if ok:
                vivas.append(nombre)
            restaura()
    finally:
        restaura()

    print('\n' + '─' * 60)
    print('%d/%d mutaciones mueren' % (len(MUTACIONES) - len(vivas), len(MUTACIONES)))
    for v in vivas:
        print('   sobrevive: ' + v)
    return 1 if vivas else 0


if __name__ == '__main__':
    sys.exit(main())
