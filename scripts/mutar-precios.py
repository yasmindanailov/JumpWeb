#!/usr/bin/env python3
"""Arnés de mutación de `#531` — la página `/precios` rehecha desde su artboard (Fase 3 · T3b): la
tabla por zona con las dos columnas de precio entero, la semana dibujada y la lista de festivos con
el hecho de cada fecha.

Mismo molde endurecido que `mutar-cabecera.py` (`#525`) y `mutar-pie.py` (`#522`):
  · en PYTHON, y cada mutación verifica que el fichero CAMBIÓ antes de correr nada;
  · EXIGE VERDE antes de mutar (`#337`) y ÁRBOL LIMPIO en los ficheros que muta (`#181`);
  · restaura SIEMPRE, también si el proceso revienta (`#448`).

⚠️⚠️ **Desde `#658` (F5 · T2b) la VISTA de PlayJump vive en la instancia** y las guardas se parten por lo
que afirman (`#649`): `Site\\PricingPageTest` afirma sobre los DATOS de la vista —`rateTable`, `week`,
`specialLabel`, `holidays`— y mata los mutantes del servicio, del horario y del calendario; los de VISTA
apuntan al ANFITRIÓN MÍNIMO del producto (`anfitrion/precios.blade.php`) y los mata `AnfitrionPreciosTest`.

⚠️ **Seis mutantes se RETIRARON con su sujeto** (`#658`), no por comodidad: los tres del bloque de
complementos y la fila de la hora extra (`#583` los sacó de la web), el del chip que solo marcaba a la que
lidera (`#585` lo cambió a toda fila con etiqueta) y los dos de `.rate-page__birthdays`, la línea escrita a
mano que sustituyó la banda de enlace. Un mutante cuyo texto ya no existe sale «NO APLICADA» y lo único que
mide es que nadie lo mira.

    python3 scripts/mutar-precios.py
"""
import subprocess
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
FILTRO = 'Site\\\\PricingPageTest|AnfitrionPreciosTest'
TABLA = 'app/Domain/Booking/Services/RateTable.php'
VISTA = 'resources/views/anfitrion/precios.blade.php'
CONTROLADOR = 'app/Http/Controllers/PricingController.php'
HORARIO = 'app/Domain/Content/Services/ScheduleDisplay.php'
CALENDARIO = 'app/Domain/Booking/Services/OperatingSchedule.php'
FICHEROS = [TABLA, VISTA, CONTROLADOR, HORARIO, CALENDARIO, 'app/Domain/Booking/Concerns/ReadsRateFacts.php']

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
    ("la columna especial repite la normal",
     TABLA,
     "'special' => $this->writeCents($especial),",
     "'special' => $this->writeCents($this->normalPriceCents($ticket)),"),

    # ── La etiqueta del panel ──
    ("la etiqueta se inventa cuando el panel no la ha escrito",
     TABLA,
     "$badge = $ticket->tr('badge') ?: null;",
     "$badge = $ticket->tr('badge') ?: ($lidera ? 'La favorita' : null);"),

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

    # ── El anfitrión mínimo (T2b, `#658`): lo que el producto sirve SIN paquete tiene que funcionar ──
    ("el rótulo del panel entra SIN escapar (marcado del cliente en la página pública)",
     VISTA,
     "'<b>'.e($specialLabel).'</b>'",
     "'<b>'.$specialLabel.'</b>'"),

    # ⚠️ Las DOS columnas escriben la misma regla, así que se mutan las dos: dejar una sostendría la
    # página y el mutante saldría vivo sin haber cambiado nada que importe.
    ("la raya deja de decir que ese día no se vende (una celda con «—» y nadie que la lea)",
     VISTA,
     "<span class=\"sr-only\">{{ __('landing.pricing.not_sold') }}</span>",
     "", 2),

    ("el anfitrión recupera un CTA propio (la acción la trae el armazón)",
     VISTA,
     "        <x-site.link-bands />",
     "        <div class=\"page__cta\"></div>\n        <x-site.link-bands />"),

    # ── El contrato de vista: lo que se le promete a CADA instancia ──
    ("el controlador vuelve a pasar una variable que la vista NO usa (contrato tácito otra vez)",
     CONTROLADOR,
     "            'rateTable' => $tabla->compose($zonas, $entradas, Setting::promoPercent()),",
     "            'tickets' => $entradas,\n            'rateTable' => $tabla->compose($zonas, $entradas, Setting::promoPercent()),"),

    ("el controlador deja de pasar una variable que el contrato promete (todas las landings a la vez)",
     CONTROLADOR,
     "            'holidays' => $schedule->pricingCalendar(),",
     ""),
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
        for mutacion in MUTACIONES:
            nombre, rel, busca, cambia = mutacion[:4]
            # Cuántas copias se esperan: una salvo que la mutación declare otra cosa (`mutar-normas.py`).
            esperadas = mutacion[4] if len(mutacion) > 4 else 1
            ruta = RAIZ / rel
            antes = ruta.read_text(encoding='utf-8')
            n = antes.count(busca)
            if n != esperadas:
                # ⚠️ Una mutación que no se aplica NO es una guarda que aguanta — y una que se aplica a
                # MENOS copias de las que hay tampoco: la copia intacta sostiene la página.
                print('  ⚠ NO APLICADA (%d coincidencias, se esperaban %d)  %s' % (n, esperadas, nombre))
                vivas.append(nombre + ' [no aplicada]')
                continue
            ruta.write_text(antes.replace(busca, cambia, esperadas), encoding='utf-8')
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
